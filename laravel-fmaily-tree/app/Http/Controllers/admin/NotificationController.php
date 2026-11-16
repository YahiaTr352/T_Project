<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendWhatsAppInvitationJob;
use App\Jobs\SendExpoPushNotificationJob;
use App\Models\Notification;
use App\Models\NotificationGroup;
use App\Models\NotificationRecipient;
use App\Models\Person;
use App\Models\Location;
use App\Models\UserDevice;
use App\Models\NotificationSetting;
use App\Models\UltramsgSendLog;
use App\Services\Invitations\InvitationApprovalService;
use App\Services\UltramsgService;
use App\Support\NotificationSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(): View
    {
        $total_devices = UserDevice::count();
        $ios_devices = UserDevice::where('os_platform', 'ios')->count();
        $android_devices = UserDevice::where('os_platform', 'android')->count();
        $total_notifications = Notification::count();
        $sent_notifications = Notification::where('status', 'sent')->count();
        $failed_notifications = Notification::where('status', 'failed')->count();
        $registered_devices = UserDevice::with('person')->latest()->get();

        return view('dashboard.notifications.index', compact(
            'total_devices',
            'ios_devices',
            'android_devices',
            'total_notifications',
            'sent_notifications',
            'failed_notifications',
            'registered_devices'
        ));
    }

    public function send(Request $request): View
    {
        $groups = NotificationGroup::withCount('persons')->orderBy('name')->get();
        $locations = Location::orderBy('name')->get();
        $templates = config('invitation_templates.templates', []);
        $enabledChannels = NotificationSettings::channels();
        $limits = NotificationSettings::limits();
        $quotaStats = NotificationSettings::dailyQuotaStats();

        return view('dashboard.notifications.send', compact(
            'groups',
            'locations',
            'templates',
            'enabledChannels',
            'limits',
            'quotaStats'
        ));
    }

    public function sendSubmit(Request $request): RedirectResponse
    {
        Log::channel('single')->info('=== SEND SUBMIT START ===', [
            'user' => $request->user()->name ?? $request->user()->id,
            'recipient_type' => $request->input('recipient_type'),
            'channels' => $request->input('channels'),
            'media_type' => $request->input('media_type'),
            'has_media_file' => $request->hasFile('media'),
        ]);

        $enabledChannelKeys = NotificationSettings::enabledChannelKeys();

        if (empty($enabledChannelKeys)) {
            return back()->with('error', 'جميع قنوات الإرسال معطّلة. فعّل قناة واحدة على الأقل من إعدادات الإشعارات.');
        }

        $validated = $request->validate([
            'channels' => 'required|array|min:1',
            'channels.*' => ['required', 'string', Rule::in($enabledChannelKeys)],
            'recipient_type' => 'required|in:persons,group,all,location,gender',
            'person_ids' => 'required_if:recipient_type,persons|array|min:1',
            'person_ids.*' => 'integer|exists:persons,id',
            'group_id' => 'required_if:recipient_type,group|nullable|integer|exists:notification_groups,id',
            'location_id' => 'required_if:recipient_type,location|nullable|integer|exists:locations,id',
            'gender' => 'required_if:recipient_type,gender|nullable|string|in:male,female',
            'exclude_deceased' => 'nullable|string', // Checkbox sends string 'on' if checked
            'title' => 'nullable|string|max:255',
            'body' => 'nullable|string|max:65535',
            'media_type' => 'nullable|string|in:image,video,voice',
            'media' => 'nullable|file|max:16384', // 16MB
        ], [], [
            'channels' => 'قنوات الإرسال',
            'recipient_type' => 'نوع المستلمين',
            'person_ids' => 'الأشخاص',
            'group_id' => 'المجموعة',
            'location_id' => 'المنطقة/المدينة',
            'gender' => 'الجنس',
            'body' => 'نص الرسالة',
            'media' => 'الملف المرفق',
        ]);

        $channels = $validated['channels'];
        $excludeDeceased = $request->has('exclude_deceased');

        // Resolve recipients based on filters
        $personQuery = Person::query();

        if ($excludeDeceased) {
            $personQuery->whereNull('death_date');
        }

        if ($validated['recipient_type'] === 'persons') {
            $personQuery->whereIn('id', array_values($validated['person_ids']));
        } elseif ($validated['recipient_type'] === 'group') {
            $group = NotificationGroup::findOrFail($validated['group_id']);
            $personIds = $group->persons()->pluck('persons.id')->toArray();
            $personQuery->whereIn('id', $personIds);
        } elseif ($validated['recipient_type'] === 'location') {
            $personQuery->where('location_id', $validated['location_id']);
        } elseif ($validated['recipient_type'] === 'gender') {
            $personQuery->where('gender', $validated['gender']);
        }

        $persons = $personQuery->with(['contactAccounts', 'parent.parent.parent.parent.parent'])->get();

        if ($persons->isEmpty() && !in_array('push', $channels)) {
            return back()->with('error', 'لا توجد نتائج مطابقة لمرشحات الاستهداف المحددة.');
        }

        // Daily limit safety check for WhatsApp channel
        if (in_array('whatsapp', $channels)) {
            $quotaStats = NotificationSettings::dailyQuotaStats();
            if ($quotaStats['has_limit']) {
                $targetCount = $persons->count();
                if ($quotaStats['remaining'] !== null && $targetCount > $quotaStats['remaining']) {
                    return back()->withInput()->with('error', "تنبيه الأمان: الحملة تتجاوز الحصة اليومية المتبقية لواتساب ({$quotaStats['remaining']} رسالة متبقية من أصل {$quotaStats['limit']}). تم إلغاء الإرسال لحماية رقمك من الحظر.");
                }
            }
        }

        // Handle media upload
        $mediaPath = null;
        $mediaType = $validated['media_type'] ?? null;
        if ($request->hasFile('media')) {
            $file = $request->file('media');
            $mediaPath = $file->store('notifications', 'public');
            $mediaType = $mediaType ?? $this->guessMediaType($file->getMimeType());

            Log::channel('single')->info('Media uploaded', [
                'original_name' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType(),
                'size_kb' => round($file->getSize() / 1024),
                'stored_path' => $mediaPath,
                'media_type' => $mediaType,
            ]);
        }

        // Warn if using media with localhost
        if ($mediaPath && in_array($mediaType, ['video', 'voice'])) {
            $appUrl = config('app.url', 'http://localhost');
            if (str_contains($appUrl, 'localhost') || str_contains($appUrl, '127.0.0.1')) {
                Log::channel('single')->warning('APP_URL is localhost — media will NOT be accessible.');
            }
        }

        $notification = Notification::create([
            'user_id' => $request->user()->id,
            'title' => $validated['title'] ?? null,
            'body' => $validated['body'] ?? '',
            'media_type' => $mediaType,
            'media_path' => $mediaPath,
            'channels' => $channels,
            'targeting_filters' => [
                'recipient_type' => $validated['recipient_type'],
                'location_id' => $validated['location_id'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'exclude_deceased' => $excludeDeceased,
            ],
            'status' => 'sending',
        ]);

        Log::channel('single')->info('Notification created', ['notification_id' => $notification->id]);

        $dispatchedWhatsAppCount = 0;
        $dispatchedPushCount = 0;

        // Channel: WhatsApp Dispatching with anti-ban staggered delays
        if (in_array('whatsapp', $channels)) {
            $eligiblePersons = [];
            foreach ($persons as $person) {
                $numbers = $this->getWhatsAppNumbers($person);
                if (!empty($numbers)) {
                    $eligiblePersons[] = [
                        'person' => $person,
                        'number' => $numbers[0],
                    ];
                }
            }

            $delays = NotificationSettings::calculateStaggeredDelays(count($eligiblePersons));

            foreach ($eligiblePersons as $index => $item) {
                $person = $item['person'];
                $toNumber = $item['number'];
                $delaySeconds = $delays[$index] ?? 0;

                $notification->recipients()->firstOrCreate(['person_id' => $person->id]);
                $messageBody = $this->replacePlaceholders($validated['body'] ?? '', $person);

                SendWhatsAppInvitationJob::dispatch(
                    $notification->id,
                    $person->id,
                    $toNumber,
                    $messageBody,
                    $mediaType,
                    $mediaPath,
                    $person->first_name ?: $person->full_name
                )->delay(now()->addSeconds($delaySeconds));

                $dispatchedWhatsAppCount++;
            }
        }

        // Channel: Expo Push Notifications Dispatching
        if (in_array('push', $channels)) {
            if ($validated['recipient_type'] === 'all') {
                $devices = UserDevice::all();
                foreach ($devices as $device) {
                    $person = $device->person_id ? Person::find($device->person_id) : null;
                    if ($person) {
                        $notification->recipients()->firstOrCreate(['person_id' => $device->person_id]);
                    }
                    $bodyText = $this->replacePlaceholders($validated['body'] ?? '', $person);

                    SendExpoPushNotificationJob::dispatch(
                        $notification->id,
                        $device->person_id ?? 0,
                        $device->expo_push_token,
                        $validated['title'] ?? 'تنبيه عائلي',
                        $bodyText,
                        ['notification_id' => $notification->id]
                    );
                    $dispatchedPushCount++;
                }
            } else {
                $personIds = $persons->pluck('id')->toArray();
                $devices = UserDevice::whereIn('person_id', $personIds)->get();
                foreach ($devices as $device) {
                    $notification->recipients()->firstOrCreate(['person_id' => $device->person_id]);
                    $person = $persons->firstWhere('id', $device->person_id);
                    $bodyText = $this->replacePlaceholders($validated['body'] ?? '', $person);

                    SendExpoPushNotificationJob::dispatch(
                        $notification->id,
                        $device->person_id,
                        $device->expo_push_token,
                        $validated['title'] ?? 'تنبيه عائلي',
                        $bodyText,
                        ['notification_id' => $notification->id]
                    );
                    $dispatchedPushCount++;
                }
            }
        }

        // If no jobs were dispatched
        if ($dispatchedWhatsAppCount === 0 && $dispatchedPushCount === 0) {
            $notification->update(['status' => 'failed']);
            return back()->with('error', 'لم يتم العثور على أرقام هواتف أو أجهزة مسجلة لمرشحات الاستهداف المحددة.');
        }

        Log::channel('single')->info('=== SEND SUBMIT DONE ===', [
            'notification_id' => $notification->id,
            'whatsapp_jobs' => $dispatchedWhatsAppCount,
            'push_jobs' => $dispatchedPushCount,
        ]);

        $msg = "تم تشغيل مهام الإرسال في الخلفية بنجاح.";
        if ($dispatchedWhatsAppCount > 0) $msg .= " (واتساب: {$dispatchedWhatsAppCount} شخص)";
        if ($dispatchedPushCount > 0) $msg .= " (إشعارات جوال: {$dispatchedPushCount} جهاز)";

        return redirect()->route('dashboard.notifications.logs')->with('success', $msg);
    }

    public function logs(Request $request): View
    {
        $query = Notification::query()
            ->with(['user', 'person', 'approvedBy', 'rejectedBy'])
            ->withCount([
                'recipients',
                'ultramsgSendLogs',
                'ultramsgSendLogs as failed_wa_count' => fn ($q) => $q->where('status', 'failed'),
                'deliveries as push_deliveries_count',
            ])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $notifications = $query->paginate(15)->withQueryString();

        return view('dashboard.notifications.logs', compact('notifications'));
    }

    public function logShow(Request $request, Notification $notification): View
    {
        $notification->load(['user', 'person', 'approvedBy', 'rejectedBy']);

        $channel = $request->get('channel', 'all');
        $waStatus = $request->get('wa_status');

        $recipientReadAtByPerson = NotificationRecipient::query()
            ->where('notification_id', $notification->id)
            ->whereNotNull('person_id')
            ->pluck('read_at', 'person_id');

        $waLogs = null;
        if ($channel === 'all' || $channel === 'whatsapp') {
            $waQuery = UltramsgSendLog::query()
                ->where('notification_id', $notification->id)
                ->with('person')
                ->latest('id');

            if ($waStatus === 'sent' || $waStatus === 'failed') {
                $waQuery->where('status', $waStatus);
            }

            $waLogs = $waQuery->paginate(20, ['*'], 'wa_page')->withQueryString();
        }

        $pushLogs = null;
        if ($channel === 'all' || $channel === 'push') {
            $pushLogs = $notification->deliveries()
                ->with('deliverable.person')
                ->latest('id')
                ->paginate(20, ['*'], 'push_page')
                ->withQueryString();
        }

        return view('dashboard.notifications.log-show', compact(
            'notification',
            'waLogs',
            'pushLogs',
            'recipientReadAtByPerson',
            'channel',
            'waStatus',
        ));
    }

    public function settings(): View
    {
        $channels = NotificationSettings::channels();
        $ultramsg = NotificationSettings::ultramsg();
        $limits = NotificationSettings::limits();
        $meta = NotificationSettings::meta();
        $presets = NotificationSettings::presetDefinitions();
        $quotaStats = NotificationSettings::dailyQuotaStats();

        $envInstanceId = config('services.ultramsg.instance_id');
        $envHasToken = filled(config('services.ultramsg.token'));

        $effectiveInstanceId = $ultramsg['instance_id'] ?: $envInstanceId;
        $hasStoredToken = filled($ultramsg['token']);
        $tokenConfigured = $hasStoredToken || $envHasToken;

        return view('dashboard.notifications.settings', compact(
            'channels',
            'ultramsg',
            'limits',
            'meta',
            'presets',
            'quotaStats',
            'envInstanceId',
            'envHasToken',
            'effectiveInstanceId',
            'hasStoredToken',
            'tokenConfigured',
        ));
    }

    public function settingsUpdate(Request $request): RedirectResponse
    {
        $presetKeys = array_keys(NotificationSettings::presetDefinitions());

        $validated = $request->validate([
            'channels.whatsapp' => 'nullable|boolean',
            'channels.push' => 'nullable|boolean',
            'ultramsg.instance_id' => 'nullable|string|max:255',
            'ultramsg.token' => 'nullable|string|max:500',
            'limits.preset' => ['nullable', 'string', Rule::in($presetKeys)],
            'limits.daily_limit' => 'nullable|integer|min:0',
            'limits.send_delay_min' => 'nullable|integer|min:1|max:300',
            'limits.send_delay_max' => 'nullable|integer|min:1|max:300',
            'limits.batch_size' => 'nullable|integer|min:0|max:500',
            'limits.batch_pause_seconds' => 'nullable|integer|min:0|max:1800',
            'limits.auto_retry' => 'nullable|boolean',
        ], [], [
            'ultramsg.instance_id' => 'معرّف Instance',
            'ultramsg.token' => 'رمز UltraMSG',
            'limits.preset' => 'نمط الأمان',
            'limits.daily_limit' => 'الحد اليومي',
            'limits.send_delay_min' => 'أدنى فاصل زمني',
            'limits.send_delay_max' => 'أقصى فاصل زمني',
            'limits.batch_size' => 'حجم الدفعة',
            'limits.batch_pause_seconds' => 'مدة الاستراحة',
            'limits.auto_retry' => 'إعادة المحاولة التلقائية',
        ]);

        $channels = [
            'whatsapp' => $request->boolean('channels.whatsapp'),
            'push' => $request->boolean('channels.push'),
        ];

        $currentUltramsg = NotificationSettings::ultramsg();
        $newToken = trim((string) ($validated['ultramsg']['token'] ?? ''));
        $tokenPlaceholder = '********';

        if ($newToken === '' || $newToken === $tokenPlaceholder) {
            $newToken = $currentUltramsg['token'] ?: config('services.ultramsg.token');
        }

        $ultramsg = [
            'instance_id' => filled($validated['ultramsg']['instance_id'] ?? null)
                ? $validated['ultramsg']['instance_id']
                : null,
            'token' => filled($newToken) ? $newToken : null,
        ];

        $preset = $validated['limits']['preset'] ?? NotificationSettings::PRESET_SAFE;
        $presets = NotificationSettings::presetDefinitions();

        if ($preset !== NotificationSettings::PRESET_CUSTOM && isset($presets[$preset])) {
            $presetDef = $presets[$preset];
            $limits = [
                'preset' => $preset,
                'send_delay_min' => (int) $presetDef['send_delay_min'],
                'send_delay_max' => (int) $presetDef['send_delay_max'],
                'batch_size' => (int) $presetDef['batch_size'],
                'batch_pause_seconds' => (int) $presetDef['batch_pause_seconds'],
                'daily_limit' => (int) $presetDef['daily_limit'],
                'send_delay_ms' => 0,
                'auto_retry' => $request->boolean('limits.auto_retry'),
            ];
        } else {
            $minDelay = max(1, (int) ($validated['limits']['send_delay_min'] ?? 25));
            $maxDelay = max($minDelay, (int) ($validated['limits']['send_delay_max'] ?? 45));

            $limits = [
                'preset' => NotificationSettings::PRESET_CUSTOM,
                'send_delay_min' => $minDelay,
                'send_delay_max' => $maxDelay,
                'batch_size' => max(0, (int) ($validated['limits']['batch_size'] ?? 20)),
                'batch_pause_seconds' => max(0, (int) ($validated['limits']['batch_pause_seconds'] ?? 180)),
                'daily_limit' => max(0, (int) ($validated['limits']['daily_limit'] ?? 0)),
                'send_delay_ms' => 0,
                'auto_retry' => $request->boolean('limits.auto_retry'),
            ];
        }

        NotificationSetting::setValue('channels', $channels);
        NotificationSetting::setValue('ultramsg', $ultramsg);
        NotificationSetting::setValue('limits', $limits);
        NotificationSetting::setValue('meta', [
            'updated_by' => $request->user()?->id,
            'updated_by_name' => $request->user()?->name,
            'updated_at' => now()->toIso8601String(),
        ]);

        NotificationSettings::forgetCache();

        return redirect()
            ->route('dashboard.notifications.settings')
            ->with('success', 'تم حفظ إعدادات الإشعارات والأمان بنجاح.');
    }

    public function testUltramsg(Request $request, UltramsgService $ultramsgService): JsonResponse
    {
        $validated = $request->validate([
            'instance_id' => 'nullable|string|max:255',
            'token' => 'nullable|string|max:500',
        ]);

        $current = NotificationSettings::ultramsg();
        $instanceId = filled($validated['instance_id'] ?? null)
            ? $validated['instance_id']
            : ($current['instance_id'] ?: config('services.ultramsg.instance_id', ''));
        $token = trim((string) ($validated['token'] ?? ''));

        if ($token === '' || $token === '********') {
            $token = $current['token'] ?: config('services.ultramsg.token', '');
        }

        if ($instanceId === '' || $token === '') {
            return response()->json([
                'success' => false,
                'message' => 'يرجى إدخال معرّف Instance ورمز UltraMSG قبل الاختبار.',
            ], 422);
        }

        $result = $ultramsgService->testConnection($instanceId, $token);

        return response()->json([
            'success' => (bool) ($result['success'] ?? false),
            'message' => $result['message'] ?? ($result['success'] ? 'تم الاتصال بنجاح.' : 'فشل الاتصال.'),
            'details' => $result['details'] ?? null,
        ]);
    }

    public function personsWithWhatsApp(Request $request)
    {
        $search = trim((string) $request->get('q', ''));
        $query = Person::whereHas('contactAccounts', function ($q) {
            $q->where('type', 'whatsapp')->whereNotNull('value')->where('value', '!=', '');
        });

        if ($search !== '') {
            $query->searchByName($search);
        }

        $persons = $query->with(['contactAccounts', 'parent.parent.parent.parent.parent'])
            ->limit(50)
            ->get();

        $result = [];
        foreach ($persons as $person) {
            $numbers = $this->getWhatsAppNumbers($person);
            if (!empty($numbers)) {
                $result[] = [
                    'id' => $person->id,
                    'full_name' => $person->full_name,
                    'first_name' => $person->first_name,
                    'whatsapp_numbers' => $numbers,
                ];
            }
        }
        return response()->json(['persons' => $result]);
    }

    public function previewMessage(Request $request)
    {
        $validated = $request->validate([
            'body' => 'nullable|string',
            'person_id' => 'required|integer|exists:persons,id',
        ]);
        $person = Person::with(['parent.parent.parent.parent.parent'])->findOrFail($validated['person_id']);
        $text = $this->replacePlaceholders($validated['body'] ?? '', $person);
        return response()->json(['preview' => $text]);
    }

    public function calculateReach(Request $request)
    {
        $recipientType = $request->input('recipient_type', 'all');
        $groupId = $request->input('group_id');
        $locationId = $request->input('location_id');
        $gender = $request->input('gender');
        $personIds = $request->input('person_ids', []);
        $excludeDeceased = $request->boolean('exclude_deceased', true);

        $personQuery = Person::query();

        if ($excludeDeceased) {
            $personQuery->whereNull('death_date');
        }

        if ($recipientType === 'persons' && !empty($personIds)) {
            $personQuery->whereIn('id', (array) $personIds);
        } elseif ($recipientType === 'group' && $groupId) {
            $group = NotificationGroup::find($groupId);
            if ($group) {
                $personIdsInGroup = $group->persons()->pluck('persons.id')->toArray();
                $personQuery->whereIn('id', $personIdsInGroup);
            } else {
                $personQuery->whereRaw('1 = 0');
            }
        } elseif ($recipientType === 'location' && $locationId) {
            $personQuery->where('location_id', $locationId);
        } elseif ($recipientType === 'gender' && $gender) {
            $personQuery->where('gender', $gender);
        }

        $persons = $personQuery->with('contactAccounts')->get();

        $whatsappCount = 0;
        $personIdsFound = [];
        foreach ($persons as $person) {
            $numbers = $this->getWhatsAppNumbers($person);
            if (!empty($numbers)) {
                $whatsappCount++;
            }
            $personIdsFound[] = $person->id;
        }

        if ($recipientType === 'all') {
            $pushCount = UserDevice::count();
        } else {
            $pushCount = UserDevice::whereIn('person_id', $personIdsFound)->count();
        }

        return response()->json([
            'success' => true,
            'total_persons' => $persons->count(),
            'whatsapp_count' => $whatsappCount,
            'push_count' => $pushCount,
        ]);
    }

    public function retryNotification(Notification $notification): RedirectResponse
    {
        $channels = $notification->channels ?? [];
        $dispatchedWhatsAppCount = 0;
        $dispatchedPushCount = 0;

        if (in_array('whatsapp', $channels)) {
            $failedWaLogs = UltramsgSendLog::where('notification_id', $notification->id)
                ->where('status', 'failed')
                ->get();

            $delays = NotificationSettings::calculateStaggeredDelays($failedWaLogs->count());

            foreach ($failedWaLogs as $index => $log) {
                $delaySeconds = $delays[$index] ?? 0;

                SendWhatsAppInvitationJob::dispatch(
                    $notification->id,
                    $log->person_id,
                    $log->to_number,
                    $log->message_sent ?: ($notification->body ?? ''),
                    $notification->media_type,
                    $notification->media_path,
                    $log->recipient_name
                )->delay(now()->addSeconds($delaySeconds));

                $dispatchedWhatsAppCount++;
            }
        }

        if (in_array('push', $channels)) {
            $failedPushDeliveries = $notification->deliveries()
                ->where('status', 'failed')
                ->with('deliverable')
                ->get();

            foreach ($failedPushDeliveries as $delivery) {
                $device = $delivery->deliverable;
                if ($device && $device->expo_push_token) {
                    SendExpoPushNotificationJob::dispatch(
                        $notification->id,
                        $device->person_id ?? 0,
                        $device->expo_push_token,
                        $notification->title ?? 'تنبيه عائلي',
                        $notification->body ?? '',
                        ['notification_id' => $notification->id]
                    );
                    $dispatchedPushCount++;
                }
            }
        }

        if ($dispatchedWhatsAppCount === 0 && $dispatchedPushCount === 0) {
            return back()->with('error', 'لا توجد رسائل فاشلة لإعادة محاولة إرسالها لهذا الإشعار.');
        }

        $notification->update(['status' => 'sending']);

        return back()->with('success', "تمت إعادة تشغيل مهام الإرسال في الخلفية ({$dispatchedWhatsAppCount} واتساب، {$dispatchedPushCount} إشعار Push).");
    }

    // Groups
    public function groupsIndex(): View
    {
        $groups = NotificationGroup::withCount('persons')->orderBy('name')->get();
        return view('dashboard.notifications.groups.index', compact('groups'));
    }

    public function groupsCreate(): View
    {
        return view('dashboard.notifications.groups.create');
    }

    public function groupsStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:65535',
        ]);
        NotificationGroup::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'user_id' => $request->user()->id,
        ]);
        return redirect()->route('dashboard.notification-groups.index')->with('success', 'تم إنشاء المجموعة.');
    }

    public function groupsEdit(NotificationGroup $notificationGroup): View
    {
        $notificationGroup->load('persons');
        return view('dashboard.notifications.groups.edit', compact('notificationGroup'));
    }

    public function groupsUpdate(Request $request, NotificationGroup $notificationGroup): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:65535',
        ]);
        $notificationGroup->update($validated);
        return redirect()->route('dashboard.notification-groups.index')->with('success', 'تم تحديث المجموعة.');
    }

    public function groupsDestroy(NotificationGroup $notificationGroup): RedirectResponse
    {
        $notificationGroup->delete();
        return redirect()->route('dashboard.notification-groups.index')->with('success', 'تم حذف المجموعة.');
    }

    public function groupsPersons(NotificationGroup $notificationGroup)
    {
        $persons = $notificationGroup->persons()->with(['contactAccounts'])->orderBy('notification_group_person.sort_order')->get();
        $list = [];
        foreach ($persons as $person) {
            $numbers = $this->getWhatsAppNumbers($person);
            $list[] = [
                'id' => $person->id,
                'full_name' => $person->full_name,
                'whatsapp_numbers' => $numbers,
            ];
        }
        return response()->json(['persons' => $list]);
    }

    public function groupsAttachPerson(Request $request, NotificationGroup $notificationGroup): RedirectResponse
    {
        $validated = $request->validate(['person_id' => 'required|integer|exists:persons,id']);
        $maxOrder = $notificationGroup->persons()->max('notification_group_person.sort_order') ?? 0;
        $notificationGroup->persons()->syncWithoutDetaching([
            $validated['person_id'] => ['sort_order' => $maxOrder + 1],
        ]);
        return back()->with('success', 'تمت إضافة الشخص.');
    }

    public function groupsDetachPerson(NotificationGroup $notificationGroup, Person $person): RedirectResponse
    {
        $notificationGroup->persons()->detach($person->id);
        return back()->with('success', 'تمت إزالة الشخص.');
    }

    private function getWhatsAppNumbers(Person $person): array
    {
        $numbers = [];
        if (!$person->relationLoaded('contactAccounts')) {
            $person->load('contactAccounts');
        }
        if ($person->contactAccounts) {
            foreach ($person->contactAccounts->where('type', 'whatsapp') as $account) {
                $clean = preg_replace('/[^0-9]/', '', $account->value);
                if ($clean && !in_array($clean, $numbers)) {
                    $numbers[] = $clean;
                }
            }
        }
        return $numbers;
    }

    private function replacePlaceholders(string $body, ?Person $person = null): string
    {
        $messageService = app(\App\Services\Invitations\InvitationMessageService::class);
        return $messageService->replacePlaceholders($body, $person);
    }

    private function guessMediaType(?string $mime): string
    {
        if (!$mime) {
            return 'image';
        }
        if (str_starts_with($mime, 'video/')) {
            return 'video';
        }
        if (str_starts_with($mime, 'audio/')) {
            return 'voice';
        }
        return 'image';
    }

    /**
     * Show pending invitations requiring approval.
     */
     public function pending(Request $request): View
     {
         $search = trim((string) $request->query('search', ''));
         $channelFilter = (string) $request->query('channel', 'all');

          $query = Notification::with(['person.contactAccounts', 'recipients.person.contactAccounts', 'user'])
              ->where('status', 'pending_approval');

         if ($search !== '') {
             $query->where(function ($q) use ($search) {
                 $q->where('title', 'like', "%{$search}%")
                     ->orWhere('body', 'like', "%{$search}%")
                     ->orWhereHas('person', function ($pq) use ($search) {
                         $pq->searchByName($search);
                     })
                     ->orWhereHas('user', function ($uq) use ($search) {
                         $uq->where('name', 'like', "%{$search}%");
                     });
             });
         }

         if ($channelFilter === 'whatsapp') {
             $query->whereJsonContains('channels', 'whatsapp');
         } elseif ($channelFilter === 'push') {
             $query->whereJsonContains('channels', 'push');
         }

          $notifications = $query->latest()->paginate(15)->withQueryString();

          $pendingCount = Notification::where('status', 'pending_approval')->count();
          $approvedTodayCount = Notification::where('status', 'approved')->whereDate('approved_at', today())->count();
          $rejectedTodayCount = Notification::where('status', 'rejected')->whereDate('rejected_at', today())->count();
          $waitingRecipientsCount = NotificationRecipient::whereHas('notification', function ($q) {
              $q->where('status', 'pending_approval');
          })->count();

          // أسماء مجموعات الاستهداف لعرضها بوضوح في صفحة الاعتماد (الرسالة رايحة لمين)
          $groupIds = $notifications->getCollection()
              ->map(fn ($n) => is_array($n->targeting_filters) ? ($n->targeting_filters['group_id'] ?? null) : null)
              ->filter()
              ->unique()
              ->values()
              ->all();
          $groupNames = $groupIds
              ? NotificationGroup::whereIn('id', $groupIds)->pluck('name', 'id')->all()
              : [];

          return view('dashboard.notifications.pending', compact(
              'notifications',
              'search',
              'channelFilter',
              'pendingCount',
              'approvedTodayCount',
              'rejectedTodayCount',
              'waitingRecipientsCount',
              'groupNames'
          ));
     }

     /**
      * Approve a pending invitation.
      */
     public function approve(Notification $notification, InvitationApprovalService $approvalService): RedirectResponse|JsonResponse
     {
         try {
             $result = $approvalService->approve($notification, auth()->user());

             if (request()->wantsJson()) {
                 return response()->json([
                     'success' => true,
                     'message' => 'تم اعتماد الدعوة وبدأ إرسالها بنجاح.',
                     'data' => $result,
                 ]);
             }

             return back()->with('success', 'تم اعتماد الدعوة وبدأ إرسالها لجميع المستلمين بنجاح.');
         } catch (\Throwable $e) {
             Log::channel('single')->error("Approve notification {$notification->id} failed: " . $e->getMessage());

             if (request()->wantsJson()) {
                 return response()->json([
                     'success' => false,
                     'message' => $e->getMessage(),
                 ], 422);
             }

             return back()->with('error', $e->getMessage());
         }
     }

     /**
      * Reject a pending invitation with reason.
      */
     public function reject(Request $request, Notification $notification, InvitationApprovalService $approvalService): RedirectResponse|JsonResponse
     {
         $validated = $request->validate([
             'reason' => 'required|string|max:1000',
         ], [], [
             'reason' => 'سبب الرفض',
         ]);

         try {
             $rejected = $approvalService->reject($notification, auth()->user(), $validated['reason']);

             if ($request->wantsJson()) {
                 return response()->json([
                     'success' => true,
                     'message' => 'تم رفض الدعوة وحفظ سبب الرفض.',
                     'data' => $rejected,
                 ]);
             }

             return back()->with('success', 'تم رفض الدعوة وحفظ سبب الرفض بنجاح.');
         } catch (\Throwable $e) {
             Log::channel('single')->error("Reject notification {$notification->id} failed: " . $e->getMessage());

             if ($request->wantsJson()) {
                 return response()->json([
                     'success' => false,
                     'message' => $e->getMessage(),
                 ], 422);
             }

             return back()->with('error', $e->getMessage());
         }
     }

     /**
      * Update body/title and approve.
      */
     public function updateAndApprove(Request $request, Notification $notification, InvitationApprovalService $approvalService): RedirectResponse|JsonResponse
     {
         $validated = $request->validate([
             'title' => 'nullable|string|max:255',
             'body' => 'required|string|max:65535',
         ], [], [
             'title' => 'عنوان الدعوة',
             'body' => 'نص الدعوة',
         ]);

         try {
             $result = $approvalService->updateAndApprove($notification, auth()->user(), $validated);

             if ($request->wantsJson()) {
                 return response()->json([
                     'success' => true,
                     'message' => 'تم تعديل الدعوة واعتمادها بنجاح.',
                     'data' => $result,
                 ]);
             }

             return back()->with('success', 'تم تعديل الدعوة واعتمادها وبدأ الإرسال بنجاح.');
         } catch (\Throwable $e) {
             Log::channel('single')->error("Update and approve notification {$notification->id} failed: " . $e->getMessage());

             if ($request->wantsJson()) {
                 return response()->json([
                     'success' => false,
                     'message' => $e->getMessage(),
                 ], 422);
             }

             return back()->with('error', $e->getMessage());
         }
     }

     /**
      * Bulk approve multiple invitations.
      */
     public function bulkApprove(Request $request, InvitationApprovalService $approvalService): RedirectResponse|JsonResponse
     {
         $validated = $request->validate([
             'ids' => 'required|array|min:1',
             'ids.*' => 'integer|exists:notifications,id',
         ]);

         $result = $approvalService->bulkApprove($validated['ids'], auth()->user());

         $msg = "تم اعتماد {$result['approved_count']} دعوة بنجاح.";
         if ($result['failed_count'] > 0) {
             $msg .= " (تعذر اعتماد {$result['failed_count']} دعوة).";
         }

         if ($request->wantsJson()) {
             return response()->json([
                 'success' => true,
                 'message' => $msg,
                 'data' => $result,
             ]);
         }

         return back()->with('success', $msg);
    }

    /**
     * تنظيف ومسح سجل الإشعارات وصناديق الوارد دفعة واحدة
     */
    public function purge(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'scope' => 'required|in:all,older_than',
            'days' => 'required_if:scope,older_than|nullable|integer|min:1',
            'purge_wa_logs' => 'nullable|boolean',
        ]);

        $query = Notification::query();
        $cutoff = null;

        if ($validated['scope'] === 'older_than') {
            $cutoff = now()->subDays((int) $validated['days']);
            $query->where('created_at', '<=', $cutoff);
        }

        DB::beginTransaction();

        try {
            // 1. حذف الملفات المرفقة بالتخزين
            $mediaPaths = (clone $query)->whereNotNull('media_path')->pluck('media_path');
            foreach ($mediaPaths as $path) {
                if ($path && Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }

            $notificationIds = (clone $query)->pluck('id');

            // 2. حذف سجلات الواتساب
            $waCount = 0;
            if ($request->boolean('purge_wa_logs', true)) {
                $waQuery = UltramsgSendLog::query();
                if ($cutoff) {
                    $waQuery->whereIn('notification_id', $notificationIds)
                        ->orWhere(function ($q) use ($cutoff) {
                            $q->whereNull('notification_id')->where('created_at', '<=', $cutoff);
                        });
                }
                $waCount = $waQuery->delete();
            }

            // 3. حذف الإشعارات ومستلميها وتوصيلاتها عبر الـ cascade
            $deletedCount = Notification::whereIn('id', $notificationIds)->delete();

            DB::commit();

            Log::channel('single')->info('[Notifications] Purged by admin ' . auth()->id(), [
                'deleted_notifications' => $deletedCount,
                'deleted_wa_logs' => $waCount,
                'scope' => $validated['scope'],
            ]);

            return redirect()->route('dashboard.notifications.logs')
                ->with('success', "تم تنظيف السجل بنجاح (حُذف {$deletedCount} إشعار، {$waCount} سجل واتساب، وتفريغ صناديق وارد التطبيق بالكامل).");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::channel('single')->error('[Notifications] Purge failed: ' . $e->getMessage());

            return back()->with('error', 'فشلت عملية التنظيف: ' . $e->getMessage());
        }
    }
}

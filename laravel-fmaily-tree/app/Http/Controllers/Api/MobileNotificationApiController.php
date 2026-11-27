<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MobileNotificationResource;
use App\Models\Notification;
use App\Models\Person;
use App\Models\UserDevice;
use App\Services\Invitations\InvitationEligibilityService;
use App\Services\Notifications\MobileNotificationService;
use App\Services\SiteIdentityResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class MobileNotificationApiController extends Controller
{
    public function __construct(
        protected SiteIdentityResolver $identityResolver,
        protected MobileNotificationService $notificationService
    ) {}

    /**
     * POST /api/devices/register
     * Identity token (face/WhatsApp) links the device; phone is a fallback.
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'expo_push_token' => 'required|string',
            'device_uuid' => 'nullable|string|max:64',
            'phone' => 'nullable|string',
            'device_name' => 'nullable|string',
            'device_model' => 'nullable|string',
            'os_platform' => 'nullable|string',
            'os_version' => 'nullable|string',
            'app_version' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'بيانات التسجيل غير صالحة.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $token = $request->input('expo_push_token');
        [$personId, $linkedVia] = $this->resolveLink($request, $request->input('phone'));

        try {
            $attributes = [
                'device_uuid' => $request->input('device_uuid'),
                'device_name' => $request->input('device_name'),
                'device_model' => $request->input('device_model'),
                'os_platform' => $request->input('os_platform'),
                'os_version' => $request->input('os_version'),
                'app_version' => $request->input('app_version'),
                'last_active_at' => now(),
            ];

            if ($personId) {
                $attributes['person_id'] = $personId;
            }

            $device = UserDevice::updateOrCreate(
                ['expo_push_token' => $token],
                $attributes
            )->fresh();

            $linkedPersonId = $device->person_id ? (int) $device->person_id : null;

            Log::channel('single')->info('[MobileNotification API] Registered device token successfully', [
                'device_id' => $device->id,
                'person_id' => $linkedPersonId,
                'linked_via' => $personId ? $linkedVia : 'none',
                'os' => $device->os_platform,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'device_id' => $device->id,
                    'linked_person_id' => $linkedPersonId,
                    'is_linked' => $linkedPersonId !== null,
                    'linked_via' => $personId ? $linkedVia : 'none',
                ],
                'message' => 'تم تسجيل الجهاز بنجاح ربطه بالعضو.',
                'errors' => null,
            ]);
        } catch (\Throwable $e) {
            Log::channel('single')->error('[MobileNotification API] Registration failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'فشل تسجيل الجهاز بسبب خطأ داخلي.',
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }

    /**
     * GET /api/notifications
     */
    public function index(Request $request, InvitationEligibilityService $invitationEligibility): JsonResponse
    {
        [$personId, $verifiedViewer] = $this->resolveViewer($request);
        $viewerPerson = $personId ? Person::find($personId) : null;
        $request->attributes->set('viewer_person', $viewerPerson);
        $canRespondToInvitations = $invitationEligibility->check($viewerPerson, $verifiedViewer)['eligible'];
        $request->attributes->set('invitation_response_person_id', $canRespondToInvitations ? $personId : null);

        $query = $this->notificationService->getInboxQueryForPerson($personId, $verifiedViewer);
        if ($request->filled('notification_id')) {
            $query->whereKey($request->integer('notification_id'));
        }

        $notifications = $query->with(['person', 'recipients' => function ($q) use ($personId) {
            if ($personId) {
                $q->where('person_id', $personId);
            }
        }])
            ->latest()
            ->paginate(30);

        return response()->json([
            'success' => true,
            'data' => MobileNotificationResource::collection($notifications)->response()->getData(true),
            'message' => 'تم استرجاع الإشعارات بنجاح.',
            'errors' => null,
        ]);
    }

    /**
     * GET /api/notifications/unread-count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        [$personId, $verifiedViewer] = $this->resolveViewer($request);

        if (! $personId) {
            return response()->json([
                'success' => true,
                'data' => ['unread_count' => 0],
                'message' => 'الزوار يتتبعون الإشعارات محلياً.',
                'errors' => null,
            ]);
        }

        $unreadCount = $this->notificationService->getUnreadCountForPerson($personId, $verifiedViewer);

        return response()->json([
            'success' => true,
            'data' => ['unread_count' => $unreadCount],
            'message' => 'تم استرجاع عدد الرسائل غير المقروءة.',
            'errors' => null,
        ]);
    }

    /**
     * POST /api/notifications/{id}/read
     */
    public function markAsRead(Request $request, $id): JsonResponse
    {
        [$personId, $verifiedViewer] = $this->resolveViewer($request);

        if (! $personId) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'سجّل الدخول ببصمة الوجه أو واتساب لتعيين الإشعار مقروءاً على السيرفر.',
                'errors' => ['Person not resolved'],
            ], 422);
        }

        $updated = DB::table('notification_recipients')
            ->where('notification_id', $id)
            ->where('person_id', $personId)
            ->when(! $verifiedViewer, fn ($query) => $query->whereIn('notification_id', Notification::withoutContactMessageReplies()->select('id')))
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => (bool) $updated,
            'data' => null,
            'message' => $updated ? 'تم تعيين الإشعار كمقروء.' : 'الإشعار مقروء بالفعل أو غير موجه لهذا العضو.',
            'errors' => null,
        ]);
    }

    /**
     * POST /api/notifications/read-all
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        [$personId, $verifiedViewer] = $this->resolveViewer($request);

        if (! $personId) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'سجّل الدخول ببصمة الوجه أو واتساب.',
                'errors' => ['Person not resolved'],
            ], 422);
        }

        $updated = DB::table('notification_recipients')
            ->where('person_id', $personId)
            ->whereNull('read_at')
            ->when(! $verifiedViewer, fn ($query) => $query->whereIn('notification_id', Notification::withoutContactMessageReplies()->select('id')))
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'data' => ['updated_rows' => $updated],
            'message' => 'تم تحديد كافة الإشعارات كمقروءة.',
            'errors' => null,
        ]);
    }

    /**
     * DELETE /api/notifications/{id}
     * حذف أو إخفاء إشعار معين للمستخدم الحالي
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        [$personId, $verifiedViewer] = $this->resolveViewer($request);

        if (! $personId) {
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'تم استبعاد الإشعار محلياً (زائر).',
                'errors' => null,
            ]);
        }

        if ($verifiedViewer || Notification::withoutContactMessageReplies()->whereKey($id)->exists()) {
            $this->notificationService->dismiss($id, $personId);
        }

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'تم حذف الإشعار بنجاح.',
            'errors' => null,
        ]);
    }

    /**
     * POST /api/notifications/delete-multiple
     * حذف مجموعة محددة من الإشعارات دفعة واحدة
     */
    public function deleteMultiple(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'ids.*' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'معرفات الإشعارات غير صالحة.',
                'errors' => $validator->errors(),
            ], 422);
        }

        [$personId, $verifiedViewer] = $this->resolveViewer($request);
        $ids = $request->input('ids', []);

        if (! $personId) {
            return response()->json([
                'success' => true,
                'data' => ['deleted_count' => count($ids)],
                'message' => 'تم استبعاد الإشعارات محلياً (زائر).',
                'errors' => null,
            ]);
        }

        if (! $verifiedViewer) {
            $ids = Notification::withoutContactMessageReplies()->whereIn('id', $ids)->pluck('id')->all();
        }

        $deletedCount = $this->notificationService->dismissMultiple($ids, $personId);

        return response()->json([
            'success' => true,
            'data' => ['deleted_count' => $deletedCount],
            'message' => 'تم حذف الإشعارات المحددة بنجاح.',
            'errors' => null,
        ]);
    }

    /**
     * POST /api/notifications/clear-all
     * مسح وحذف كافة الإشعارات الظاهرة للمستخدم
     */
    public function clearAll(Request $request): JsonResponse
    {
        [$personId, $verifiedViewer] = $this->resolveViewer($request);

        if (! $personId) {
            return response()->json([
                'success' => true,
                'data' => ['deleted_count' => 0],
                'message' => 'تم مسح الإشعارات محلياً (زائر).',
                'errors' => null,
            ]);
        }

        $deletedCount = $this->notificationService->clearAll($personId, $verifiedViewer);

        return response()->json([
            'success' => true,
            'data' => ['deleted_count' => $deletedCount],
            'message' => 'تم مسح كافة الإشعارات بنجاح.',
            'errors' => null,
        ]);
    }

    /**
     * @return array{0: int|null, 1: string}
     */
    private function resolveLink(Request $request, ?string $phone): array
    {
        $identity = $this->identityResolver->resolve($request);
        if ($identity) {
            return [(int) $identity['person_id'], 'identity'];
        }

        $fromPhone = $this->resolvePersonIdFromPhone($phone);
        if ($fromPhone) {
            return [$fromPhone, 'phone'];
        }

        return [null, 'none'];
    }

    /** @return array{0: int|null, 1: bool} */
    private function resolveViewer(Request $request): array
    {
        $identity = $this->identityResolver->resolve($request);
        if ($identity) {
            return [(int) $identity['person_id'], true];
        }

        return [$this->resolvePhoneViewerId($request), false];
    }

    private function resolvePhoneViewerId(Request $request): ?int
    {
        $phone = $request->input('phone') ?? $request->query('phone');

        return $this->resolvePersonIdFromPhone(is_string($phone) ? $phone : null);
    }

    private function resolvePersonIdFromPhone(?string $phone): ?int
    {
        if (empty($phone)) {
            return null;
        }

        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        if (empty($cleanPhone)) {
            return null;
        }

        $lastNine = substr($cleanPhone, -9);
        if (strlen($lastNine) < 9) {
            return null;
        }

        $contact = DB::table('person_contact_accounts')
            ->whereIn('type', ['phone', 'whatsapp'])
            ->where('value', 'like', '%'.$lastNine)
            ->first();

        if ($contact) {
            return (int) $contact->person_id;
        }

        $person = DB::table('persons')
            ->where('phone', 'like', '%'.$lastNine)
            ->first();

        return $person ? (int) $person->id : null;
    }
}

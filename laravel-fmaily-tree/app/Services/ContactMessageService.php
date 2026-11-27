<?php

namespace App\Services;

use App\Jobs\SendExpoPushNotificationJob;
use App\Models\ContactMessage;
use App\Models\Notification;
use App\Models\Person;
use App\Models\UserDevice;
use App\Services\Auth\WhatsappPersonLookup;
use App\Support\NotificationSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ContactMessageService
{
    public function __construct(
        protected SiteIdentityResolver $identityResolver,
        protected AdminAlertDispatcherService $alertDispatcher,
        private WhatsappPersonLookup $whatsappLookup,
    ) {}

    /**
     * Store a contact message from mobile app or web form.
     *
     * @param  array<string, mixed>  $data
     */
    public function storeMessage(Request $request, array $data, ?UploadedFile $mediaFile = null): ContactMessage
    {
        // 1. Resolve Identity
        $personId = $this->identityResolver->viewerPersonId($request);
        $userId = auth()->id();
        $person = null;

        if ($personId) {
            $person = Person::find($personId);
        }

        $senderName = $data['name'] ?? null;
        $senderPhone = $data['phone'] ?? null;

        if ($person) {
            if (empty($senderName)) {
                $senderName = $person->full_name ?: trim(($person->first_name ?? '') . ' ' . ($person->last_name ?? ''));
            }
            $senderPhone = $person->registeredContactPhone();
        }

        // 2. Handle Media File Upload
        $mediaType = 'none';
        $mediaPath = null;
        $mediaMime = null;
        $mediaSize = null;

        if ($mediaFile instanceof UploadedFile && $mediaFile->isValid()) {
            $mediaMime = $mediaFile->getClientMimeType() ?: $mediaFile->getMimeType();
            $mediaSize = $mediaFile->getSize();
            $ext = strtolower($mediaFile->getClientOriginalExtension());

            $explicitKind = $data['media_type'] ?? null;

            if ($explicitKind === 'voice' || in_array($ext, ['m4a', 'mp3', 'wav', 'aac', 'ogg', 'caf']) || str_starts_with($mediaMime ?? '', 'audio/')) {
                $mediaType = 'voice';
            } elseif ($explicitKind === 'video' || in_array($ext, ['mp4', 'mov', 'webm', 'm4v', '3gp']) || str_starts_with($mediaMime ?? '', 'video/')) {
                $mediaType = 'video';
            } elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif']) || str_starts_with($mediaMime ?? '', 'image/')) {
                $mediaType = 'image';
            } else {
                $mediaType = 'image';
            }

            $folder = 'contact-messages/' . date('Y/m');
            $mediaPath = $mediaFile->store($folder, 'public');
        }

        // 3. Create the Database Record
        $message = ContactMessage::create([
            'person_id'   => $personId,
            'user_id'     => $userId,
            'name'        => $senderName,
            'phone'       => $senderPhone,
            'email'       => $data['email'] ?? null,
            'type'        => $data['type'] ?? 'problem',
            'subject'     => $data['subject'] ?? null,
            'message'     => $data['message'] ?? null,
            'media_type'  => $mediaType,
            'media_path'  => $mediaPath,
            'media_mime'  => $mediaMime,
            'media_size'  => $mediaSize,
            'source'      => $data['source'] ?? ($request->is('api/*') ? 'app' : 'web'),
            'ip_address'  => $request->ip(),
            'user_agent'  => mb_substr((string) $request->userAgent(), 0, 500),
            'status'      => 'pending',
        ]);

        // 4. Send Instant WhatsApp Alert to Family Administrators
        $this->notifyAdminsSafely($message);

        return $message;
    }

    public function whatsAppNumber(ContactMessage $message): ?string
    {
        if (! $message->isMemberSuggestion()) {
            return preg_replace('/\D/', '', (string) $message->sender_display_phone) ?: null;
        }

        return $message->person->registeredWhatsAppMobile($this->whatsappLookup);
    }

    public function suggestionReplies(ContactMessage $message): Builder
    {
        return Notification::query()
            ->where('targeting_filters->data->type', Notification::CONTACT_MESSAGE_REPLY_TYPE)
            ->where('targeting_filters->data->contact_message_id', $message->id)
            ->latest('id');
    }

    public function replyToSuggestion(ContactMessage $message, int $adminId, string $reply): void
    {
        DB::transaction(function () use ($message, $adminId, $reply) {
            $suggestion = ContactMessage::query()->lockForUpdate()->findOrFail($message->id);
            $person = $suggestion->person;

            if (! $suggestion->isMemberSuggestion() || $this->whatsAppNumber($suggestion)) {
                throw ValidationException::withMessages(['reply' => 'الرد عبر التطبيق متاح للمقترحات المرتبطة بعضو بلا رقم واتساب مسجّل.']);
            }

            if ($this->suggestionReplies($suggestion)->first()?->body === $reply) {
                return;
            }

            $data = ['type' => Notification::CONTACT_MESSAGE_REPLY_TYPE, 'contact_message_id' => $suggestion->id];
            $notification = Notification::create([
                'user_id' => $adminId,
                'title' => 'تم الرد على مقترحك',
                'body' => $reply,
                'status' => 'sent',
                'sent_at' => now(),
                'targeting_filters' => ['data' => $data],
            ]);
            $notification->recipients()->create(['person_id' => $person->id]);

            if (! NotificationSettings::canSendPush()) {
                return;
            }

            $tokens = UserDevice::where('person_id', $person->id)->where('expo_push_token', '!=', '')->pluck('expo_push_token');
            foreach ($tokens as $token) {
                SendExpoPushNotificationJob::dispatch(
                    $notification->id,
                    $person->id,
                    $token,
                    $notification->title,
                    'افتح التطبيق لقراءة رد الإدارة.',
                    ['type' => Notification::CONTACT_MESSAGE_REPLY_TYPE, 'notification_id' => $notification->id],
                    isSenderNotice: true,
                )->afterCommit();
            }
        });
    }

    /**
     * Safely dispatch WhatsApp notification to admins without interrupting the response on failures.
     */
    protected function notifyAdminsSafely(ContactMessage $message): void
    {
        try {
            $sender = $message->sender_display_name;
            $phone = $message->sender_display_phone;
            $typeLabel = $message->type_label;
            $sourceLabel = $message->source === 'app' ? 'تطبيق الجوال' : 'الموقع الإلكتروني';

            $alert = "🔔 *تنبيه إدارة العائلة | رسالة جديدة*\n";
            $alert .= "━━━━━━━━━━━━━━━━━━━━\n";
            $alert .= "👤 *المرسل:* {$sender}\n";
            if ($phone) {
                $alert .= "📱 *الجوال:* {$phone}\n";
            }
            $alert .= "🏷️ *النوع:* {$typeLabel}\n";
            $alert .= "🌐 *المصدر:* {$sourceLabel}\n";

            if (!empty($message->message)) {
                $snippet = mb_substr(trim($message->message), 0, 200);
                $alert .= "📝 *الرسالة:*\n{$snippet}\n";
            }

            if ($message->media_type !== 'none') {
                $alert .= "📎 *المرفق:* {$message->media_type_label}\n";
            }

            $alert .= "\n🔗 *المعاينة في لوحة التحكم:*\n" . url("/dashboard/contact-messages/{$message->id}");

            $this->alertDispatcher->sendWhatsAppToAdmins(
                message: $alert,
                permissions: ['site-content.view', 'site-content.update'],
                roles: ['editor', 'admin', 'super_admin'],
                logContext: 'ContactMessageNotification'
            );
        } catch (\Throwable $e) {
            Log::channel('single')->warning('[ContactMessageService] Admin notification failed: ' . $e->getMessage());
        }
    }
}

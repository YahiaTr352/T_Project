<?php

namespace App\Services;

use App\Models\ContactMessage;
use App\Models\Person;
use App\Services\AdminAlertDispatcherService;
use App\Services\SiteIdentityResolver;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ContactMessageService
{
    public function __construct(
        protected SiteIdentityResolver $identityResolver,
        protected AdminAlertDispatcherService $alertDispatcher
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
            if (empty($senderPhone)) {
                $senderPhone = $person->phone;
            }
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

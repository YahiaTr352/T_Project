<?php

namespace App\Jobs;

use App\Contracts\Chatable;
use App\Models\ChatMessage;
use App\Models\UserDevice;
use App\Services\ExpoNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendChatMessageNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        public int $messageId
    ) {}

    public function handle(ExpoNotificationService $expoService): void
    {
        $message = ChatMessage::with(['conversation.conversable', 'sender'])->find($this->messageId);
        if (! $message || ! $message->conversation) {
            return;
        }

        $conversable = $message->conversation->conversable;
        if (! ($conversable instanceof Chatable)) {
            return;
        }

        $recipientPersonIds = $conversable->getChatNotificationRecipients($message->sender_person_id);
        if ($recipientPersonIds->isEmpty()) {
            return;
        }

        $senderName = $message->sender?->full_name ?? 'أحد الأعضاء';
        $chatTitle = $conversable->getChatTitle();
        $messageSnippet = $message->message ?: ($message->attachment_url ? '📷 [صورة]' : 'رسالة جديدة');
        if (mb_strlen($messageSnippet) > 80) {
            $messageSnippet = mb_substr($messageSnippet, 0, 77).'...';
        }

        $notificationTitle = "💬 {$chatTitle}";
        $notificationBody = "{$senderName}: {$messageSnippet}";

        // Deep link action URL for mobile navigation (يختلف حسب نوع المحادثة)
        $chatPayload = $conversable->getChatNotificationPayload();

        $actionUrl = (string) ($chatPayload['action_url'] ?? '');

        $customPayload = array_merge(
            $chatPayload,
            [
                'conversation_id' => $message->conversation_id,
                'message_id' => $message->id,
                'action_url' => $actionUrl,
            ]
        );

        try {
            // Chat notifications are push-only.
            // نحن لا نُنشئ سجلات إشعارات داخل التطبيق للشات إطلاقاً (لا فائدة منها)،
            // ويكفي إرسال Push Notification. المستلمون هنا يستبعدون مسبقاً
            // المُرسِل ومن كتم المحادثة (is_muted) أو عطّل إشعاراتها.
            $tokens = UserDevice::whereIn('person_id', $recipientPersonIds)
                ->whereNotNull('expo_push_token')
                ->where('expo_push_token', '!=', '')
                ->pluck('expo_push_token')
                ->unique()
                ->values()
                ->toArray();

            if (empty($tokens)) {
                return;
            }

            // Send in chunks of 100 as recommended by Expo
            foreach (array_chunk($tokens, 100) as $chunk) {
                $expoService->send($chunk, $notificationTitle, $notificationBody, $customPayload);
            }
        } catch (\Throwable $e) {
            Log::channel('single')->error('[SendChatMessageNotificationJob] Failed: '.$e->getMessage(), [
                'message_id' => $this->messageId,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}

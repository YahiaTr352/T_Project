<?php

namespace App\Services\Chat;

use App\Contracts\Chatable;
use App\Models\ChatMessage;
use App\Models\ChatModerationLog;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Services\Chat\DTO\ContentVerdict;
use App\Services\Chat\Exceptions\ChatModerationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * نقطة الرقابة الوحيدة على إرسال رسائل المحادثات.
 *
 * كل مسارات الإرسال تمر من هنا: ChatService::sendMessage() → assertCanSend()
 * ثم applyContentFilter(). أي مسار جديد يجب أن يمر من نفس البوابة.
 */
class ChatModerationService
{
    /**
     * الرتب التي يُسمح لها بالكتابة في محادثة مقفلة.
     */
    public const MODERATOR_ROLES = ['moderator', 'owner'];

    public function __construct(
        protected ChatSettingsService $settings,
        protected ChatFilterService $filter,
        protected ChatBanService $bans,
    ) {}

    // ── Gate: هل يُسمح بالإرسال؟ ────────────────────────────────

    /**
     * تقييم صلاحية الإرسال دون رمي استثناء.
     *
     * @return array{allowed: bool, reason: string|null, message: string|null, status: int, retry_after: int|null, context: array}
     */
    public function evaluateAccess(Chatable $chatable, int $personId, Conversation $conversation): array
    {
        $allow = fn (string $reason, string $message, int $status = 403, ?int $retryAfter = null, array $context = []): array => [
            'allowed'     => false,
            'reason'      => $reason,
            'message'     => $message,
            'status'      => $status,
            'retry_after' => $retryAfter,
            'context'     => $context,
        ];

        // 1. تعطيل المحادثات على مستوى النظام
        if (! $this->settings->bool('chat.enabled', true)) {
            return $allow('chat_disabled', 'المحادثات معطلة مؤقتاً من قبل الإدارة.');
        }

        // 2. المحادثة غير نشطة
        if (! $conversation->is_active) {
            return $allow('conversation_inactive', 'هذه المحادثة غير نشطة حالياً.');
        }

        // 3. الحظر (عام أو خاص بهذه المحادثة)
        $ban = $this->bans->activeBanFor($personId, $conversation->id);

        if ($ban) {
            return $allow(
                'banned',
                $this->bans->describe($ban) ?? 'تم إيقافك من الإرسال في المحادثات.',
                403,
                null,
                [
                    'ban_id'     => $ban->id,
                    'scope'      => $ban->scope,
                    'expires_at' => $ban->expires_at?->toIso8601String(),
                ]
            );
        }

        $participant = $conversation->participants()->where('person_id', $personId)->first();

        // 4. منع إرسال من الإدارة لمشارك واحد
        if ($participant && ! $participant->can_send) {
            return $allow(
                'participant_restricted',
                'تم إيقافك من الإرسال في هذه المحادثة من قبل الإدارة.'
            );
        }

        // 5. قفل المحادثة
        if ($conversation->is_locked && ! $this->isModerator($conversation, $personId)) {
            return $allow(
                'conversation_locked',
                'تم قفل هذه المحادثة مؤقتاً من قبل الإدارة — القراءة فقط.'
            );
        }

        // 6. الوضع البطيء
        $slowMode = (int) ($conversation->slow_mode_seconds ?: $this->settings->int('chat.slow_mode_seconds', 0));

        if ($slowMode > 0 && $participant?->last_message_at) {
            $elapsed = (int) $participant->last_message_at->diffInSeconds(now());

            if ($elapsed < $slowMode) {
                $retryAfter = $slowMode - $elapsed;

                return $allow(
                    'slow_mode',
                    "الوضع البطيء مفعّل — يمكنك إرسال رسالة بعد {$retryAfter} ثانية.",
                    429,
                    $retryAfter
                );
            }
        }

        // 7. حد الإرسال لكل مشارك
        $perMinute = $this->settings->int('chat.participant_rate_limit_per_minute', 0);

        if ($perMinute > 0) {
            $recent = $conversation->messages()
                ->where('sender_person_id', $personId)
                ->where('created_at', '>=', now()->subMinute())
                ->count();

            if ($recent >= $perMinute) {
                return $allow(
                    'rate_limit',
                    'لقد تجاوزت الحد المسموح من الرسائل في هذه المحادثة. انتظر قليلاً ثم حاول مجدداً.',
                    429,
                    60
                );
            }
        }

        return [
            'allowed'     => true,
            'reason'      => null,
            'message'     => null,
            'status'      => 200,
            'retry_after' => null,
            'context'     => [],
        ];
    }

    /**
     * هل يُسمح لهذا الشخص بالإرسال في هذه المحادثة؟
     */
    public function canSend(Chatable $chatable, int $personId, Conversation $conversation): bool
    {
        if (! $chatable->canSendMessage($personId)) {
            return false;
        }

        return $this->evaluateAccess($chatable, $personId, $conversation)['allowed'];
    }

    /**
     * @throws ChatModerationException
     */
    public function assertCanSend(Chatable $chatable, int $personId, Conversation $conversation): void
    {
        $result = $this->evaluateAccess($chatable, $personId, $conversation);

        if ($result['allowed']) {
            return;
        }

        throw new ChatModerationException(
            $result['message'] ?? 'لا يمكنك الإرسال في هذه المحادثة.',
            $result['reason'] ?? 'blocked',
            $result['status'],
            $result['retry_after'],
            $result['context']
        );
    }

    /**
     * هل يُسمح بإرفاق ملفات في هذه المحادثة؟
     */
    public function attachmentsAllowed(Conversation $conversation): bool
    {
        if (! $conversation->allow_attachments) {
            return false;
        }

        return $this->settings->bool('chat.allow_attachments', true);
    }

    // ── فلتر المحتوى ────────────────────────────────────────────

    /**
     * فحص النص وإرجاع النص النهائي بعد الإخفاء (إن وُجد).
     *
     * @return array{text: string, verdict: ContentVerdict}
     *
     * @throws ChatModerationException
     */
    public function applyContentFilter(string $text, Conversation $conversation, int $personId): array
    {
        $verdict = $this->filter->evaluate($text);

        if ($verdict->isBlocked()) {
            $this->log('warn', $conversation, null, $personId, $verdict->message, $verdict->toArray());

            throw new ChatModerationException(
                $verdict->message,
                'content_blocked',
                422,
                null,
                $verdict->toArray()
            );
        }

        if ($verdict->needsMasking() || $verdict->hasWarnings()) {
            $this->incrementWarnings($conversation, $personId);

            $this->log(
                'warn',
                $conversation,
                null,
                $personId,
                $verdict->message,
                $verdict->toArray()
            );
        }

        return [
            'text' => $verdict->needsMasking() ? (string) $verdict->cleanText : $text,
            'verdict' => $verdict,
        ];
    }

    /**
     * تصعيد التحذيرات على مشارك.
     */
    public function incrementWarnings(Conversation $conversation, int $personId): ConversationParticipant
    {
        $participant = $conversation->getParticipant($personId);
        $participant->increment('warnings_count');

        $threshold = $this->settings->int(
            'chat.escalation.warnings_before_flag',
            (int) config('chat.escalation.warnings_before_flag', 3)
        );

        $fresh = $participant->fresh();

        if ($threshold > 0 && (int) $fresh?->warnings_count >= $threshold) {
            $this->log(
                'warn',
                $conversation,
                null,
                $personId,
                'تم تجاوز حد التحذيرات المسموح به.',
                ['warnings_count' => (int) $fresh?->warnings_count, 'threshold' => $threshold]
            );
        }

        return $participant;
    }

    // ── إجراءات الإدارة ─────────────────────────────────────────

    public function lockConversation(Conversation $conversation, ?int $adminUserId = null, ?string $reason = null): Conversation
    {
        $conversation->update([
            'is_locked' => true,
            'locked_at' => now(),
            'locked_by' => $adminUserId,
            'lock_reason' => $reason,
        ]);

        $this->log('lock_chat', $conversation, null, null, $reason, [], $adminUserId);

        return $conversation->refresh();
    }

    public function unlockConversation(Conversation $conversation, ?int $adminUserId = null): Conversation
    {
        $conversation->update([
            'is_locked' => false,
            'locked_at' => null,
            'locked_by' => null,
            'lock_reason' => null,
        ]);

        $this->log('unlock_chat', $conversation, null, null, null, [], $adminUserId);

        return $conversation->refresh();
    }

    public function setSlowMode(Conversation $conversation, int $seconds, ?int $adminUserId = null): Conversation
    {
        $max = (int) config('chat.limits.max_slow_mode_seconds', 86400);
        $seconds = max(0, min($seconds, $max));

        $conversation->update(['slow_mode_seconds' => $seconds]);

        $this->log('slow_mode', $conversation, null, null, null, ['seconds' => $seconds], $adminUserId);

        return $conversation->refresh();
    }

    public function setAttachmentsAllowed(Conversation $conversation, bool $allowed, ?int $adminUserId = null): Conversation
    {
        $conversation->update(['allow_attachments' => $allowed]);

        $this->log(
            'slow_mode',
            $conversation,
            null,
            null,
            $allowed ? 'السماح بالمرفقات' : 'منع المرفقات',
            ['allow_attachments' => $allowed],
            $adminUserId
        );

        return $conversation->refresh();
    }

    public function restrictParticipant(
        Conversation $conversation,
        int $personId,
        string $reason,
        ?int $adminUserId = null
    ): ConversationParticipant {
        $participant = $conversation->getParticipant($personId);

        $participant->update([
            'can_send' => false,
            'restriction_reason' => $reason,
            'restricted_at' => now(),
            'restricted_by' => $adminUserId,
        ]);

        $this->log('restrict_participant', $conversation, null, $personId, $reason, [], $adminUserId);

        return $participant->refresh();
    }

    public function unrestrictParticipant(Conversation $conversation, int $personId, ?int $adminUserId = null): ConversationParticipant
    {
        $participant = $conversation->getParticipant($personId);

        $participant->update([
            'can_send' => true,
            'restriction_reason' => null,
            'restricted_at' => null,
            'restricted_by' => null,
        ]);

        $this->log('unrestrict_participant', $conversation, null, $personId, null, [], $adminUserId);

        return $participant->refresh();
    }

    public function muteParticipant(Conversation $conversation, int $personId, ?int $adminUserId = null): ConversationParticipant
    {
        $participant = $conversation->getParticipant($personId);
        $participant->update(['is_muted' => true]);

        $this->log('mute_participant', $conversation, null, $personId, null, [], $adminUserId);

        return $participant->refresh();
    }

    public function unmuteParticipant(Conversation $conversation, int $personId, ?int $adminUserId = null): ConversationParticipant
    {
        $participant = $conversation->getParticipant($personId);
        $participant->update(['is_muted' => false]);

        $this->log('unmute_participant', $conversation, null, $personId, null, [], $adminUserId);

        return $participant->refresh();
    }

    public function warnParticipant(Conversation $conversation, int $personId, string $reason, ?int $adminUserId = null): ConversationParticipant
    {
        $participant = $this->incrementWarnings($conversation, $personId);

        $this->log('warn', $conversation, null, $personId, $reason, [], $adminUserId);

        return $participant;
    }

    public function setParticipantRole(
        Conversation $conversation,
        int $personId,
        string $role,
        ?int $adminUserId = null
    ): ConversationParticipant {
        $participant = $conversation->getParticipant($personId);
        $participant->update(['role' => $role]);

        $this->log('restrict_participant', $conversation, null, $personId, 'تغيير رتبة المشارك: '.$role, ['role' => $role], $adminUserId);

        return $participant->refresh();
    }

    // ── إجراءات الرسائل ─────────────────────────────────────────

    public function deleteMessage(ChatMessage $message, ?int $adminUserId = null, ?string $reason = null): ChatMessage
    {
        $conversation = $message->conversation;

        // Clean up attachment from storage if present
        if ($message->attachment_url) {
            try {
                $path = parse_url($message->attachment_url, PHP_URL_PATH);
                if ($path && str_contains($path, 'chat_attachments/')) {
                    $relative = 'chat_attachments/' . basename($path);
                    Storage::disk('public')->delete($relative);
                }
            } catch (\Throwable $e) {
                \Log::warning('[ChatModerationService] Failed to delete attachment file: ' . $e->getMessage());
            }
        }

        $message->delete();

        // Recalculate conversation preview, counts, and unpin if needed
        if ($conversation) {
            $pinnedId = $conversation->last_pinned_message_id === $message->id ? null : $conversation->last_pinned_message_id;
            $latest = $conversation->messages()->latest('id')->first();

            $conversation->update([
                'last_pinned_message_id' => $pinnedId,
                'last_message_at'        => $latest?->created_at,
                'last_message_preview'   => $latest ? ($latest->message ?: ($latest->type === 'video' ? '🎥 مقطع فيديو' : ($latest->attachment_url ? '📷 صورة' : ''))) : null,
                'messages_count'         => max(0, (int) $conversation->messages()->count()),
            ]);
        }

        $this->log('delete_message', $conversation, $message, $message->sender_person_id, $reason, [], $adminUserId);

        return $message;
    }

    /**
     * مسح الشات بأكمله: تفريغ كافة رسائل المحادثة للجميع وإعادة ضبط العدادات وسجل المحادثة.
     */
    public function clearConversation(Conversation $conversation, ?int $adminUserId = null, ?string $reason = null): int
    {
        // 1. حذف جميع المرفقات المرتبطة برسائل المحادثة من القرص
        $messagesWithAttachments = $conversation->messages()
            ->whereNotNull('attachment_url')
            ->get(['id', 'attachment_url']);

        foreach ($messagesWithAttachments as $msg) {
            try {
                $path = parse_url($msg->attachment_url, PHP_URL_PATH);
                if ($path && str_contains($path, 'chat_attachments/')) {
                    $relative = 'chat_attachments/' . basename($path);
                    Storage::disk('public')->delete($relative);
                }
            } catch (\Throwable $e) {
                \Log::warning('[ChatModerationService] Failed to delete attachment: ' . $e->getMessage());
            }
        }

        // 2. حذف الرسائل برمجياً (Soft delete)
        $deletedCount = $conversation->messages()->delete();

        // 3. إعادة ضبط مؤشرات وحالة المحادثة
        $conversation->update([
            'messages_count'         => 0,
            'last_message_at'        => null,
            'last_message_preview'   => null,
            'last_pinned_message_id' => null,
        ]);

        // 4. تسجيل العملية في سجل الإشراف والتدقيق
        $this->log(
            'clear_conversation',
            $conversation,
            null,
            null,
            $reason ?? 'مسح كافة رسائل المحادثة من الإشراف',
            ['deleted_messages_count' => $deletedCount],
            $adminUserId
        );

        return $deletedCount;
    }

    /**
     * مسح الجروب نفسه ككل أو المجموعة نفسها ككل: حذف الكيان الأصلي (المجموعة/التحدي) ومحادثتها.
     */
    public function deleteGroup(Conversation $conversation, ?int $adminUserId = null, ?string $reason = null): bool
    {
        $conversable = $conversation->conversable;
        $metadata = [
            'conversable_type' => $conversation->conversable_type,
            'conversable_id'   => $conversation->conversable_id,
            'conversation_id'  => $conversation->id,
            'group_name'       => $conversable?->name ?? $conversable?->title ?? $conversation->title,
        ];

        // 1. حذف الكيان الأصلي (مثل WalkingGroup)
        if ($conversable) {
            $conversable->delete();
        }

        // 2. إيقاف المحادثة وحذفها
        $conversation->update(['is_active' => false]);
        $conversation->delete();

        // 3. توثيق العملية في سجل التدقيق
        $this->log(
            'delete_group',
            $conversation,
            null,
            null,
            $reason ?? 'حذف المجموعة ككل من لوحة الإشراف',
            $metadata,
            $adminUserId
        );

        return true;
    }

    public function hideMessage(ChatMessage $message, ?int $adminUserId = null, ?string $reason = null): ChatMessage
    {
        $message->update([
            'is_hidden' => true,
            'hidden_at' => now(),
            'hidden_by' => $adminUserId,
            'moderation_note' => $reason,
        ]);

        $this->log('hide_message', $message->conversation, $message, $message->sender_person_id, $reason, [], $adminUserId);

        return $message;
    }

    public function restoreMessage(ChatMessage $message, ?int $adminUserId = null): ChatMessage
    {
        $message->update([
            'is_hidden' => false,
            'hidden_at' => null,
            'hidden_by' => null,
            'moderation_note' => null,
        ]);

        $this->log('restore_message', $message->conversation, $message, $message->sender_person_id, null, [], $adminUserId);

        return $message;
    }

    public function pinMessage(Conversation $conversation, ChatMessage $message, ?int $adminUserId = null): Conversation
    {
        $conversation->update(['last_pinned_message_id' => $message->id]);

        $this->log('pin_message', $conversation, $message, $message->sender_person_id, null, [], $adminUserId);

        return $conversation->refresh();
    }

    public function unpinMessage(Conversation $conversation, ?int $adminUserId = null): Conversation
    {
        $conversation->update(['last_pinned_message_id' => null]);

        $this->log('unpin_message', $conversation, null, null, null, [], $adminUserId);

        return $conversation->refresh();
    }

    // ── سجل التدقيق ─────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function log(
        string $action,
        ?Conversation $conversation = null,
        ?ChatMessage $message = null,
        ?int $personId = null,
        ?string $reason = null,
        array $metadata = [],
        ?int $adminUserId = null
    ): ChatModerationLog {
        return ChatModerationLog::create([
            'conversation_id' => $conversation?->id,
            'message_id' => $message?->id,
            'person_id' => $personId,
            'admin_user_id' => $adminUserId ?? auth()->id(),
            'action' => $action,
            'reason' => $reason,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }

    /**
     * هل المشارك مشرف في هذه المحادثة؟
     */
    public function isModerator(Conversation $conversation, int $personId): bool
    {
        $role = $conversation->participants()
            ->where('person_id', $personId)
            ->value('role');

        return in_array((string) $role, self::MODERATOR_ROLES, true);
    }
}

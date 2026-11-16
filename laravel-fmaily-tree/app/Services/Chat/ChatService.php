<?php

namespace App\Services\Chat;

use App\Contracts\Chatable;
use App\Jobs\SendChatMessageNotificationJob;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ChatService
{
    public function __construct(
        protected ChatModerationService $moderation
    ) {}

    /**
     * Get or create the polymorphic conversation for a chatable entity and return its metadata for the viewer.
     */
    public function getConversationState(Chatable $chatable, int $viewerPersonId): array
    {
        $conversation = $chatable->getOrCreateConversation();
        $conversation->loadMissing('pinnedMessage.sender');
        $participant = $conversation->getParticipant($viewerPersonId);

        $unreadCount = $conversation->getUnreadCountFor($viewerPersonId);

        // حالة الإشراف تُحدّث ما يستطيع المُشاهد فعله (حظر / قفل / وضع بطيء)
        $access = $this->moderation->evaluateAccess($chatable, $viewerPersonId, $conversation);

        $canSend = $chatable->canSendMessage($viewerPersonId) && $access['allowed'];

        return [
            'conversation'  => $conversation,
            'participant'   => $participant,
            'unread_count'  => $unreadCount,
            'is_muted'      => (bool) $participant->is_muted,
            'can_send'      => $canSend,
            'send_blocked_reason'  => $canSend ? null : ($access['reason'] ?? 'not_allowed'),
            'send_blocked_message' => $canSend ? null : ($access['message'] ?? 'لا يمكنك الإرسال في هذه المحادثة.'),
            'retry_after'   => $access['retry_after'],
        ];
    }

    /**
     * Retrieve messages for a conversation with cursor pagination and eager-loaded senders.
     * Supports fetching older messages (before_id) or polling newer messages (after_id).
     * Filters out cleared messages and user-hidden messages when $viewerPersonId is provided.
     * الرسائل المُخفاة من الإدارة لا تظهر للمستخدمين (تبقى في سجل التدقيق).
     */
    public function getMessages(
        Conversation $conversation,
        ?int $beforeId = null,
        ?int $afterId = null,
        int $limit = 30,
        ?int $viewerPersonId = null
    ): Collection {
        $query = $conversation->messages()
            ->with(['sender', 'replyTo'])
            ->where('is_hidden', false)
            ->orderBy('id', 'desc');

        if ($beforeId) {
            $query->where('id', '<', $beforeId);
        }

        if ($afterId) {
            $query->where('id', '>', $afterId);
        }

        if ($viewerPersonId) {
            $participant = $conversation->participants()
                ->where('person_id', $viewerPersonId)
                ->first();

            if ($participant && $participant->cleared_at) {
                $query->where('created_at', '>', $participant->cleared_at);
            }

            $query->whereDoesntHave('hiddenForPersons', function ($q) use ($viewerPersonId) {
                $q->where('persons.id', $viewerPersonId);
            });
        }

        // Limit maximum fetch to 50
        $clampedLimit = min(max($limit, 1), 50);

        return $query->limit($clampedLimit)->get();
    }

    /**
     * Get IDs of messages in this conversation soft-deleted recently (within $seconds)
     * for real-time synchronization during polling.
     */
    public function getRecentlyDeletedMessageIds(Conversation $conversation, int $seconds = 180): array
    {
        return $conversation->messages()
            ->onlyTrashed()
            ->where('deleted_at', '>=', now()->subSeconds($seconds))
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Post a new message inside a chatable entity's conversation.
     *
     * @param  array{message?: string, type?: string, attachment_url?: string, metadata?: array, reply_to_message_id?: int}  $data
     * @throws ValidationException
     */
    public function sendMessage(Chatable $chatable, int $senderPersonId, array $data): ChatMessage
    {
        if (!$chatable->canSendMessage($senderPersonId)) {
            throw ValidationException::withMessages([
                'message' => ['ليس لديك صلاحية لإرسال رسائل في هذه المجموعة أو التحدي.'],
            ]);
        }

        $conversation = $chatable->getOrCreateConversation();

        // ── بوابة الإشراف: الحظر، القفل، الوضع البطيء ──────────────
        $this->moderation->assertCanSend($chatable, $senderPersonId, $conversation);

        // ── فلتر المحتوى: حجب / إخفاء / تحذير ─────────────────────
        $messageText = trim($data['message'] ?? '');

        if ($messageText !== '') {
            $filtered = $this->moderation->applyContentFilter($messageText, $conversation, $senderPersonId);
            $messageText = $filtered['text'];
        }

        // ── سياسة المرفقات ─────────────────────────────────────────
        $attachmentUrl = $data['attachment_url'] ?? null;

        if ($attachmentUrl && ! $this->moderation->attachmentsAllowed($conversation)) {
            throw ValidationException::withMessages([
                'attachment' => ['المرفقات غير مسموح بها في هذه المحادثة حالياً.'],
            ]);
        }

        // ── أمان الرد: يجب أن تكون الرسالة المُقتبسة ضمن نفس المحادثة ──
        $replyToId = isset($data['reply_to_message_id']) && $data['reply_to_message_id'] !== null
            ? (int) $data['reply_to_message_id']
            : null;

        if ($replyToId !== null) {
            $replyTarget = $conversation->messages()
                ->whereKey($replyToId)
                ->where('is_hidden', false)
                ->first();

            if (! $replyTarget) {
                throw ValidationException::withMessages([
                    'reply_to_message_id' => ['لا يمكن الرد على رسالة غير موجودة في هذه المحادثة.'],
                ]);
            }
        }

        $message = DB::transaction(function () use ($conversation, $senderPersonId, $data, $messageText, $attachmentUrl, $replyToId) {
            $type = $data['type'] ?? 'text';
            $metadata = $data['metadata'] ?? null;

            // 1. Create message
            $createdMessage = $conversation->messages()->create([
                'sender_person_id'   => $senderPersonId,
                'type'               => $type,
                'message'            => $messageText,
                'attachment_url'     => $attachmentUrl,
                'metadata'           => $metadata,
                'reply_to_message_id' => $replyToId,
            ]);

            // 2. Update conversation rollups
            if ($messageText) {
                $preview = $messageText;
            } elseif ($type === 'video') {
                $preview = '🎥 مقطع فيديو';
            } elseif ($type === 'audio') {
                $preview = '🎤 رسالة صوتية';
            } elseif ($attachmentUrl) {
                $preview = '📷 صورة';
            } else {
                $preview = '';
            }

            if (mb_strlen($preview) > 100) {
                $preview = mb_substr($preview, 0, 97) . '...';
            }

            // 2. Update conversation rollups atomically
            $conversation->increment('messages_count', 1, [
                'last_message_at'      => now(),
                'last_message_preview' => $preview,
            ]);

            // 3. Update sender participant read marker + last activity (الوضع البطيء)
            ConversationParticipant::updateOrCreate(
                [
                    'conversation_id' => $conversation->id,
                    'person_id'       => $senderPersonId,
                ],
                [
                    'last_read_message_id' => $createdMessage->id,
                    'last_read_at'         => now(),
                    'last_message_at'      => now(),
                ]
            );

            return $createdMessage;
        });

        // 4. Dispatch background notification safely outside the database transaction
        try {
            SendChatMessageNotificationJob::dispatch($message->id);
        } catch (\Throwable $e) {
            \Log::error('[ChatService] Notification dispatch error: ' . $e->getMessage());
        }

        return $message->load(['sender', 'replyTo.sender']);
    }

    /**
     * حالة الإشراف المختصرة التي يحتاجها التطبيق أثناء الاستطلاع (polling)
     * لتحديث شريط القفل / الوضع البطيء / منع الإرسال دون طلب إضافي.
     */
    public function getModerationMeta(Chatable $chatable, int $viewerPersonId): array
    {
        $conversation = $chatable->getOrCreateConversation();
        $access = $this->moderation->evaluateAccess($chatable, $viewerPersonId, $conversation);

        $canSend = $chatable->canSendMessage($viewerPersonId) && $access['allowed'];

        return [
            'is_locked'            => (bool) $conversation->is_locked,
            'lock_reason'          => $conversation->lock_reason,
            'slow_mode_seconds'    => (int) ($conversation->slow_mode_seconds ?? 0),
            'allow_attachments'    => (bool) ($conversation->allow_attachments ?? true),
            'pinned_message_id'    => $conversation->last_pinned_message_id ? (int) $conversation->last_pinned_message_id : null,
            'can_send'             => $canSend,
            'send_blocked_reason'  => $canSend ? null : ($access['reason'] ?? 'not_allowed'),
            'send_blocked_message' => $canSend ? null : ($access['message'] ?? null),
            'retry_after'          => $access['retry_after'] ?? null,
        ];
    }

    /**
     * Delete an individual message (either for me or for everyone within 60 minutes, or anytime by moderators)
     *
     * @param  'me'|'everyone'  $mode
     * @throws ValidationException
     */
    public function deleteMessage(Conversation $conversation, int $messageId, int $personId, string $mode = 'me', bool $isModerator = false): array
    {
        $message = $conversation->messages()->where('id', $messageId)->first();

        if (!$message) {
            $alreadyTrashed = $conversation->messages()->onlyTrashed()->where('id', $messageId)->exists();
            if ($alreadyTrashed) {
                return [
                    'success'    => true,
                    'message_id' => $messageId,
                    'mode'       => $mode,
                    'message'    => 'الرسالة محذوفة بالفعل.',
                ];
            }

            throw ValidationException::withMessages([
                'message_id' => ['الرسالة غير موجودة أو تم حذفها بالفعل.'],
            ]);
        }

        if ($mode === 'everyone') {
            // If not a moderator/admin, enforce strict ownership & time window
            if (!$isModerator) {
                // Strict ownership verification
                if ((int) $message->sender_person_id !== (int) $personId) {
                    throw ValidationException::withMessages([
                        'mode' => ['لا تملك صلاحية حذف هذه الرسالة لدى الجميع لأنك لست كاتبها.'],
                    ]);
                }

                // Strict 60-minute time window enforcement
                if (!$message->isWithinDeleteForEveryoneWindow(60)) {
                    throw ValidationException::withMessages([
                        'mode' => ['لا يمكن حذف الرسالة لدى الجميع بعد مرور أكثر من ساعة على إرسالها.'],
                    ]);
                }
            }

            // Remove attachment file from disk if present
            if ($message->attachment_url) {
                try {
                    $path = parse_url($message->attachment_url, PHP_URL_PATH);
                    if ($path && str_contains($path, 'chat_attachments/')) {
                        $relative = 'chat_attachments/' . basename($path);
                        Storage::disk('public')->delete($relative);
                    }
                } catch (\Throwable $e) {
                    \Log::warning('[ChatService] Failed to delete attachment file: ' . $e->getMessage());
                }
            }

            // Soft-delete the message
            $message->delete();

            // Clear pinned message if this was pinned
            if ($conversation->last_pinned_message_id === $message->id) {
                $conversation->last_pinned_message_id = null;
            }

            // Recalculate conversation preview if needed
            $latest = $conversation->messages()->latest('id')->first();
            $conversation->update([
                'last_pinned_message_id' => $conversation->last_pinned_message_id,
                'last_message_at'      => $latest?->created_at,
                'last_message_preview' => $latest ? ($latest->message ?: ($latest->type === 'video' ? '🎥 مقطع فيديو' : ($latest->attachment_url ? '📷 صورة' : ''))) : null,
                'messages_count'       => max(0, (int) $conversation->messages()->count()),
            ]);

            return [
                'success'    => true,
                'message_id' => $messageId,
                'mode'       => 'everyone',
                'message'    => 'تم حذف الرسالة لدى الجميع بنجاح.',
            ];
        }

        // Mode: 'me'
        DB::table('chat_message_hidden_users')->updateOrInsert(
            [
                'chat_message_id' => $messageId,
                'person_id'       => $personId,
            ],
            [
                'created_at' => now(),
            ]
        );

        return [
            'success'    => true,
            'message_id' => $messageId,
            'mode'       => 'me',
            'message'    => 'تم حذف الرسالة من جهازك بنجاح.',
        ];
    }

    /**
     * Clear all conversation messages from the viewer's screen ("مسح الشات من عندي")
     */
    public function clearChatForPerson(Conversation $conversation, int $personId): void
    {
        $participant = $conversation->getParticipant($personId);
        $participant->update([
            'cleared_at' => now(),
        ]);
    }

    /**
     * Clear all conversation messages for everyone ("مسح الشات بأكمله لدى الجميع")
     */
    public function clearChatForEveryone(Conversation $conversation, ?int $personId = null, ?int $adminUserId = null): int
    {
        $reason = $personId ? "مسح الشات بواسطة المستخدم #{$personId}" : 'مسح الشات بأكمله';
        return $this->moderation->clearConversation($conversation, $adminUserId, $reason);
    }

    /**
     * Mark messages up to a specific message ID as read for a person.
     */
    public function markAsRead(Conversation $conversation, int $personId, int $messageId): void
    {
        ConversationParticipant::updateOrCreate(
            [
                'conversation_id' => $conversation->id,
                'person_id'       => $personId,
            ],
            [
                'last_read_message_id' => $messageId,
                'last_read_at'         => now(),
            ]
        );
    }

    /**
     * Toggle the mute state for a participant in a conversation.
     * Returns the updated mute status.
     */
    public function toggleMute(Conversation $conversation, int $personId): bool
    {
        $participant = $conversation->getParticipant($personId);
        $newMuted = !$participant->is_muted;

        $participant->update([
            'is_muted' => $newMuted,
        ]);

        return $newMuted;
    }

    /**
     * Get messages in this conversation edited recently (within $seconds)
     * for real-time synchronization during polling.
     */
    public function getRecentlyEditedMessages(Conversation $conversation, int $seconds = 180): Collection
    {
        return $conversation->messages()
            ->with(['sender'])
            ->where('is_hidden', false)
            ->where('is_edited', true)
            ->where('edited_at', '>=', now()->subSeconds($seconds))
            ->get();
    }

    /**
     * Edit an individual text message (within 60 minutes of sending)
     *
     * @throws ValidationException
     */
    public function editMessage(Conversation $conversation, int $messageId, int $personId, string $newMessageText): ChatMessage
    {
        $message = $conversation->messages()->where('id', $messageId)->first();

        if (!$message) {
            throw ValidationException::withMessages([
                'message_id' => ['الرسالة غير موجودة أو تم حذفها.'],
            ]);
        }

        if ((int) $message->sender_person_id !== (int) $personId) {
            throw ValidationException::withMessages([
                'message' => ['لا تملك صلاحية تعديل هذه الرسالة لأنك لست كاتبها.'],
            ]);
        }

        if (!$message->isWithinEditWindow(60)) {
            throw ValidationException::withMessages([
                'message' => ['لا يمكن تعديل الرسالة بعد مرور أكثر من 60 دقيقة على إرسالها.'],
            ]);
        }

        $trimmed = trim($newMessageText);
        if ($trimmed === '') {
            throw ValidationException::withMessages([
                'message' => ['نص الرسالة لا يمكن أن يكون فارغاً.'],
            ]);
        }

        // Apply content moderation / filtering
        $filtered = $this->moderation->applyContentFilter($trimmed, $conversation, $personId);
        $cleanText = $filtered['text'];

        $message->update([
            'message'   => $cleanText,
            'is_edited' => true,
            'edited_at' => now(),
        ]);

        // If this was the latest message preview, update it in conversation
        $latest = $conversation->messages()->latest('id')->first();
        if ($latest && (int) $latest->id === (int) $message->id) {
            $conversation->update([
                'last_message_preview' => mb_strlen($cleanText) > 100 ? mb_substr($cleanText, 0, 97) . '...' : $cleanText,
            ]);
        }

        return $message->fresh(['sender']);
    }
}

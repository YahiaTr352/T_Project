<?php

namespace App\Livewire\Admin\Chat;

use App\Models\ChatBan;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\Person;
use App\Services\Chat\ChatBanService;
use App\Services\Chat\ChatModerationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * شاشة تفصيل محادثة: سجل الرسائل، المشاركون، وأدوات الإشراف الفورية.
 */
class ChatConversationDetail extends Component
{
    use AuthorizesRequests;

    public int $conversationId = 0;

    // إعدادات المحادثة
    public int $slowMode = 0;

    public bool $allowAttachments = true;

    // أدوات المشاركين
    public string $participantSearch = '';

    public int $actionPersonId = 0;

    public string $restrictionReason = '';

    public string $banReason = '';

    public int $banDurationDays = 7;

    public string $banScope = 'conversation';

    // إعلان الإدارة
    public string $announcement = '';

    // النوافذ المنبثقة
    public string $modal = '';

    public int $modalPersonId = 0;

    public int $modalMessageId = 0;

    public string $modalReason = '';

    /**
     * آخر رسالة تم treatments عليها (لعرض تنبيه).
     */
    public string $flashMessage = '';

    public string $flashType = 'success';

    public function mount(Conversation $conversation): void
    {
        $this->authorize('chat.moderation.view');

        $this->conversationId = (int) $conversation->id;
        $this->slowMode = (int) $conversation->slow_mode_seconds;
        $this->allowAttachments = (bool) $conversation->allow_attachments;
    }

    public function conversation(): Conversation
    {
        return Conversation::with('conversable')->findOrFail($this->conversationId);
    }

    public function render()
    {
        $conversation = $this->conversation();

        $messages = ChatMessage::query()
            ->with(['sender', 'senderUser'])
            ->where('conversation_id', $conversation->id)
            ->orderByDesc('id')
            ->limit(120)
            ->get()
            ->sortBy('id')
            ->values();

        $participants = $conversation->participants()
            ->with('person')
            ->when($this->participantSearch !== '', function ($query) {
                $term = trim($this->participantSearch);
                $query->whereHas('person', function ($personQuery) use ($term) {
                    $personQuery->where('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('warnings_count')
            ->orderBy('id')
            ->get();

        $bans = ChatBan::query()
            ->with('person')
            ->where('conversation_id', $conversation->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return view('livewire.admin.chat.chat-conversation-detail', [
            'conversation' => $conversation,
            'messages' => $messages,
            'participants' => $participants,
            'bans' => $bans,
            'moderation' => app(ChatModerationService::class),
        ]);
    }

    // ── إعدادات المحادثة ─────────────────────────────────────────

    public function toggleLock(): void
    {
        $this->authorize('chat.moderation.participants');

        $conversation = $this->conversation();
        $service = app(ChatModerationService::class);

        if ($conversation->is_locked) {
            $service->unlockConversation($conversation, auth()->id());
            $this->flash('تم فتح المحادثة والسماح بالكتابة.');
        } else {
            $service->lockConversation($conversation, auth()->id(), 'قفل من لوحة المراقبة');
            $this->flash('تم قفل المحادثة — القراءة فقط.');
        }
    }

    public function saveSlowMode(): void
    {
        $this->authorize('chat.moderation.participants');

        $conversation = $this->conversation();
        app(ChatModerationService::class)->setSlowMode($conversation, $this->slowMode, auth()->id());

        $this->flash($this->slowMode > 0
            ? "تم تفعيل الوضع البطيء: رسالة واحدة كل {$this->slowMode} ثانية."
            : 'تم إلغاء الوضع البطيء.');
    }

    public function toggleAttachments(): void
    {
        $this->authorize('chat.moderation.participants');

        $conversation = $this->conversation();
        $next = ! $conversation->allow_attachments;

        app(ChatModerationService::class)->setAttachmentsAllowed($conversation, $next, auth()->id());
        $this->allowAttachments = $next;

        $this->flash($next ? 'تم السماح بالمرفقات.' : 'تم منع المرفقات في هذه المحادثة.');
    }

    // ── إجراءات المشاركين ────────────────────────────────────────

    public function openRestrict(int $personId): void
    {
        $this->authorize('chat.moderation.participants');

        $this->modal = 'restrict';
        $this->modalPersonId = $personId;
        $this->modalReason = '';
    }

    public function confirmRestrict(): void
    {
        $this->authorize('chat.moderation.participants');

        $reason = trim($this->modalReason) ?: 'مخالفة قوانين المحادثة';

        app(ChatModerationService::class)->restrictParticipant(
            $this->conversation(),
            $this->modalPersonId,
            $reason,
            auth()->id()
        );

        $this->closeModal();
        $this->flash('تم منع المشارك من الإرسال في هذه المحادثة.');
    }

    public function unrestrict(int $personId): void
    {
        $this->authorize('chat.moderation.participants');

        app(ChatModerationService::class)->unrestrictParticipant($this->conversation(), $personId, auth()->id());

        $this->flash('تم السماح للمشارك بالإرسال مجدداً.');
    }

    public function toggleMute(int $personId): void
    {
        $this->authorize('chat.moderation.participants');

        $conversation = $this->conversation();
        $participant = $conversation->participants()->where('person_id', $personId)->first();

        if (! $participant) {
            $this->flash('لا يوجد سجل مشاركة لهذا الشخص.', 'warning');

            return;
        }

        $service = app(ChatModerationService::class);

        if ($participant->is_muted) {
            $service->unmuteParticipant($conversation, $personId, auth()->id());
            $this->flash('تم إلغاء كتم إشعارات المشارك.');
        } else {
            $service->muteParticipant($conversation, $personId, auth()->id());
            $this->flash('تم كتم إشعارات المشارك.');
        }
    }

    public function warn(int $personId): void
    {
        $this->authorize('chat.moderation.participants');

        app(ChatModerationService::class)->warnParticipant(
            $this->conversation(),
            $personId,
            'تحذير من لوحة المراقبة',
            auth()->id()
        );

        $this->flash('تم تسجيل تحذير على المشارك.');
    }

    public function openBan(int $personId): void
    {
        $this->authorize('chat.moderation.bans');

        $this->modal = 'ban';
        $this->modalPersonId = $personId;
        $this->banReason = '';
        $this->banDurationDays = 7;
        $this->banScope = 'conversation';
    }

    public function confirmBan(): void
    {
        $this->authorize('chat.moderation.bans');

        $reason = trim($this->banReason) ?: 'مخالفة قوانين المحادثة';

        app(ChatBanService::class)->ban(
            $this->modalPersonId,
            $this->banScope === 'global' ? null : $this->conversationId,
            $reason,
            auth()->id(),
            $this->banDurationDays > 0 ? $this->banDurationDays : null
        );

        $this->closeModal();
        $this->flash('تم تنفيذ الحظر بنجاح.');
    }

    public function revokeBan(int $banId): void
    {
        $this->authorize('chat.moderation.bans');

        $ban = ChatBan::findOrFail($banId);

        app(ChatBanService::class)->revoke($ban, auth()->id(), 'رُفع الحظر من لوحة المراقبة');

        $this->flash('تم رفع الحظر.');
    }

    public function setRole(int $personId, string $role): void
    {
        $this->authorize('chat.moderation.participants');

        app(ChatModerationService::class)->setParticipantRole($this->conversation(), $personId, $role, auth()->id());

        $this->flash('تم تحديث رتبة المشارك.');
    }

    // ── إجراءات الرسائل ──────────────────────────────────────────

    public function hideMessage(int $messageId): void
    {
        $this->authorize('chat.moderation.messages');

        $message = ChatMessage::findOrFail($messageId);

        app(ChatModerationService::class)->hideMessage($message, auth()->id(), 'إخفاء من لوحة المراقبة');

        $this->flash('تم إخفاء الرسالة من المحادثة (تبقى في سجل التدقيق).');
    }

    public function restoreMessage(int $messageId): void
    {
        $this->authorize('chat.moderation.messages');

        $message = ChatMessage::findOrFail($messageId);

        app(ChatModerationService::class)->restoreMessage($message, auth()->id());

        $this->flash('تم استرجاع الرسالة.');
    }

    public function deleteMessage(int $messageId): void
    {
        $this->authorize('chat.moderation.messages');

        $message = ChatMessage::findOrFail($messageId);

        app(ChatModerationService::class)->deleteMessage($message, auth()->id(), 'حذف من لوحة المراقبة');

        $this->flash('تم حذف الرسالة نهائياً.');
    }

    public function pinMessage(int $messageId): void
    {
        $this->authorize('chat.moderation.messages');

        $conversation = $this->conversation();
        $message = ChatMessage::findOrFail($messageId);

        app(ChatModerationService::class)->pinMessage($conversation, $message, auth()->id());

        $this->flash('تم تثبيت الرسالة.');
    }

    public function unpinMessage(): void
    {
        $this->authorize('chat.moderation.messages');

        app(ChatModerationService::class)->unpinMessage($this->conversation(), auth()->id());

        $this->flash('تم إلغاء تثبيت الرسالة.');
    }

    public function clearChat(): void
    {
        $this->authorize('chat.moderation.messages');

        $reason = trim($this->modalReason) ?: 'مسح المحادثة بالكامل من لوحة الإشراف';
        $conversation = $this->conversation();

        app(ChatModerationService::class)->clearConversation($conversation, auth()->id(), $reason);

        $this->closeModal();
        $this->flash('تم مسح جميع رسائل المحادثة بنجاح.');
    }

    public function deleteGroup(): mixed
    {
        if (! auth()->user()->can('chat.moderation.participants') && ! auth()->user()->can('chat.moderation.messages')) {
            $this->authorize('chat.moderation.participants');
        }

        $reason = trim($this->modalReason) ?: 'حذف المجموعة ككل من لوحة الإشراف';
        $conversation = $this->conversation();

        app(ChatModerationService::class)->deleteGroup($conversation, auth()->id(), $reason);

        session()->flash('success', 'تم حذف المجموعة والمحادثة التابعة لها بنجاح.');

        return redirect()->route('dashboard.chat.conversations');
    }

    // ── إعلان الإدارة ────────────────────────────────────────────

    public function postAnnouncement(): void
    {
        $this->authorize('chat.moderation.messages');

        $text = trim($this->announcement);

        if ($text === '') {
            $this->flash('اكتب نص الإعلان أولاً.', 'warning');

            return;
        }

        $conversation = $this->conversation();

        DB::transaction(function () use ($conversation, $text) {
            $message = $conversation->messages()->create([
                'sender_person_id' => null,
                'sender_user_id' => auth()->id(),
                'type' => 'system',
                'message' => $text,
            ]);

            $conversation->increment('messages_count', 1, [
                'last_message_at' => now(),
                'last_message_preview' => mb_substr($text, 0, 97),
            ]);
        });

        $this->announcement = '';
        $this->flash('تم نشر الإعلان في المحادثة.');
    }

    // ── مساعدات الواجهة ──────────────────────────────────────────

    public function openModal(string $modal, int $personId = 0, int $messageId = 0): void
    {
        $this->modal = $modal;
        $this->modalPersonId = $personId;
        $this->modalMessageId = $messageId;
        $this->modalReason = '';
    }

    public function closeModal(): void
    {
        $this->modal = '';
        $this->modalPersonId = 0;
        $this->modalMessageId = 0;
        $this->modalReason = '';
    }

    public function personName(int $personId): string
    {
        return Person::find($personId)?->full_name ?? ('شخص #'.$personId);
    }

    public function flash(string $message, string $type = 'success'): void
    {
        $this->flashMessage = $message;
        $this->flashType = $type;

        $this->dispatch('chat-moderation-flash', message: $message, type: $type);
    }
}

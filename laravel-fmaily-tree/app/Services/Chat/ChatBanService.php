<?php

namespace App\Services\Chat;

use App\Models\ChatBan;
use Illuminate\Database\Eloquent\Collection;

/**
 * إدارة حظر المشاركين من إرسال الرسائل.
 *
 * نوعان من الحظر:
 *   - محادثة محددة: conversation_id = <id>
 *   - حظر عام:      conversation_id = null
 */
class ChatBanService
{
    /**
     * إنشاء حظر جديد.
     *
     * @param  int|null  $conversationId  null = حظر عام من كل المحادثات
     * @param  int|null  $durationDays  null = دائم
     */
    public function ban(
        int $personId,
        ?int $conversationId,
        string $reason,
        ?int $adminUserId = null,
        ?int $durationDays = null
    ): ChatBan {
        // إلغاء الحظر السابق المطابق حتى لا تتضارب السجلات
        $this->revokeOverlapping($personId, $conversationId, $adminUserId, 'استُبدل بحظر جديد');

        return ChatBan::create([
            'person_id' => $personId,
            'conversation_id' => $conversationId,
            'scope' => $conversationId === null ? 'global' : 'conversation',
            'reason' => $reason,
            'banned_by' => $adminUserId,
            'banned_at' => now(),
            'expires_at' => $durationDays !== null ? now()->addDays($durationDays) : null,
            'is_active' => true,
        ]);
    }

    /**
     * رفع الحظر.
     */
    public function revoke(ChatBan $ban, ?int $adminUserId = null, ?string $reason = null): ChatBan
    {
        $ban->update([
            'is_active' => false,
            'revoked_at' => now(),
            'revoked_by' => $adminUserId,
            'revoke_reason' => $reason,
        ]);

        return $ban->refresh();
    }

    /**
     * الحظر النشط المطابق لشخص (عام أو خاص بالمحادثة).
     */
    public function activeBanFor(int $personId, ?int $conversationId = null): ?ChatBan
    {
        return ChatBan::query()
            ->active()
            ->forPerson($personId)
            ->when(
                $conversationId !== null,
                fn ($q) => $q->where(function ($inner) use ($conversationId) {
                    $inner->whereNull('conversation_id')->orWhere('conversation_id', $conversationId);
                }),
                fn ($q) => $q->whereNull('conversation_id')
            )
            ->orderByDesc('id')
            ->first();
    }

    /**
     * كل الحظر النشطة لشخص.
     *
     * @return Collection<int, ChatBan>
     */
    public function activeBansFor(int $personId)
    {
        return ChatBan::query()
            ->active()
            ->forPerson($personId)
            ->orderByDesc('id')
            ->get();
    }

    public function isBanned(int $personId, ?int $conversationId = null): bool
    {
        return $this->activeBanFor($personId, $conversationId) !== null;
    }

    /**
     * رفع كل الحظر الخاصة بشخص (مفيد عند التراجع عن قرار).
     */
    public function revokeAllFor(int $personId, ?int $adminUserId = null, ?string $reason = null): int
    {
        return ChatBan::query()
            ->active()
            ->forPerson($personId)
            ->update([
                'is_active' => false,
                'revoked_at' => now(),
                'revoked_by' => $adminUserId,
                'revoke_reason' => $reason,
            ]);
    }

    /**
     * إلغاء الحظر المتضارب قبل إنشاء حظر جديد.
     */
    protected function revokeOverlapping(
        int $personId,
        ?int $conversationId,
        ?int $adminUserId,
        string $reason
    ): void {
        $query = ChatBan::query()
            ->active()
            ->forPerson($personId)
            ->when(
                $conversationId === null,
                fn ($q) => $q->whereNull('conversation_id'),
                fn ($q) => $q->where(function ($inner) use ($conversationId) {
                    $inner->whereNull('conversation_id')->orWhere('conversation_id', $conversationId);
                })
            );

        $query->update([
            'is_active' => false,
            'revoked_at' => now(),
            'revoked_by' => $adminUserId,
            'revoke_reason' => $reason,
        ]);
    }

    /**
     * وصف مختصر لسبب الحظر (يُعرض للمستخدم في رسالة الخطأ).
     */
    public function describe(?ChatBan $ban): ?string
    {
        if (! $ban) {
            return null;
        }

        if ($ban->isGlobalBan()) {
            return 'تم إيقاف حسابك من إرسال الرسائل في جميع المحادثات مؤقتاً.';
        }

        return 'تم إيقافك من إرسال الرسائل في هذه المحادثة مؤقتاً.';
    }
}

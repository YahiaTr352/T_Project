<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * سجل تدقيق إجراءات الإشراف على المحادثات.
 */
class ChatModerationLog extends Model
{
    protected $table = 'chat_moderation_logs';

    protected $fillable = [
        'conversation_id',
        'message_id',
        'person_id',
        'admin_user_id',
        'action',
        'reason',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'message_id');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id');
    }

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    // ── Scopes ─────────────────────────────────────────────────────

    public function scopeForConversation(Builder $query, int $conversationId): Builder
    {
        return $query->where('conversation_id', $conversationId);
    }

    public function scopeForPerson(Builder $query, int $personId): Builder
    {
        return $query->where('person_id', $personId);
    }

    public function scopeAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }

    // ── Helpers ────────────────────────────────────────────────────

    public function actionLabel(): string
    {
        return config('chat.actions')[$this->action] ?? $this->action;
    }

    /**
     * لون شارة الإجراء في لوحة التحكم.
     */
    public function actionBadgeClass(): string
    {
        return match ($this->action) {
            'ban'              => 'danger',
            'unban'            => 'success',
            'lock_chat'        => 'danger',
            'unlock_chat'      => 'success',
            'delete_message'   => 'danger',
            'hide_message'     => 'warning',
            'restore_message'  => 'success',
            'warn'             => 'warning',
            'slow_mode'        => 'info',
            'pin_message'      => 'info',
            'unpin_message'    => 'secondary',
            default            => 'secondary',
        };
    }
}

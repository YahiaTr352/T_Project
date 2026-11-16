<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * حظر شخص من إرسال الرسائل — من محادثة محددة أو من كل المحادثات.
 */
class ChatBan extends BaseModel
{
    protected $table = 'chat_bans';

    protected $fillable = [
        'person_id',
        'conversation_id',
        'scope',
        'reason',
        'banned_by',
        'banned_at',
        'expires_at',
        'is_active',
        'revoked_at',
        'revoked_by',
        'revoke_reason',
    ];

    protected $casts = [
        'banned_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function bannedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'banned_by');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    // ── Scopes ─────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expires_at')->where('expires_at', '<=', now());
    }

    public function scopeGlobal(Builder $query): Builder
    {
        return $query->whereNull('conversation_id');
    }

    public function scopeForConversation(Builder $query, int $conversationId): Builder
    {
        return $query->where('conversation_id', $conversationId);
    }

    public function scopeForPerson(Builder $query, int $personId): Builder
    {
        return $query->where('person_id', $personId);
    }

    // ── Helpers ────────────────────────────────────────────────────

    public function isGlobalBan(): bool
    {
        return $this->conversation_id === null || $this->scope === 'global';
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isCurrentlyActive(): bool
    {
        return (bool) $this->is_active && ! $this->isExpired();
    }

    public function scopeLabel(): string
    {
        return $this->isGlobalBan() ? 'حظر عام' : 'حظر من محادثة';
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationParticipant extends Model
{
    use HasFactory;

    protected $table = 'conversation_participants';

    /**
     * قيم افتراضية تُضبط في الذاكرة مباشرة بعد الإنشاء
     * (لا يعيد Eloquent قيم قاعدة البيانات الافتراضية بعد INSERT).
     */
    protected $attributes = [
        'can_send'       => true,
        'is_muted'       => true,
        'warnings_count' => 0,
    ];

    protected $fillable = [
        'conversation_id',
        'person_id',
        'last_read_message_id',
        'last_read_at',
        'cleared_at',
        'is_muted',
        'can_send',
        'restriction_reason',
        'restricted_at',
        'restricted_by',
        'warnings_count',
        'last_message_at',
        'role',
        'joined_at',
    ];

    protected $casts = [
        'is_muted'       => 'boolean',
        'can_send'       => 'boolean',
        'last_read_at'   => 'datetime',
        'cleared_at'     => 'datetime',
        'restricted_at'  => 'datetime',
        'last_message_at' => 'datetime',
        'joined_at'      => 'datetime',
        'warnings_count' => 'integer',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id');
    }

    public function lastReadMessage(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'last_read_message_id');
    }

    // ── Scopes ─────────────────────────────────────────────────────

    public function scopeRestricted(Builder $query): Builder
    {
        return $query->where('can_send', false);
    }

    public function scopeModerators(Builder $query): Builder
    {
        return $query->whereIn('role', ['moderator', 'owner']);
    }
}


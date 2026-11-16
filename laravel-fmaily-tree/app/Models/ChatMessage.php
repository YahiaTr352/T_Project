<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatMessage extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'chat_messages';

    /**
     * قيم افتراضية تُضبط في الذاكرة مباشرة بعد الإنشاء
     * (لا يعيد Eloquent قيم قاعدة البيانات الافتراضية بعد INSERT).
     */
    protected $attributes = [
        'is_hidden'      => false,
        'is_flagged'     => false,
        'is_edited'      => false,
        'reported_count' => 0,
    ];

    protected $fillable = [
        'conversation_id',
        'sender_person_id',
        'sender_user_id',
        'type',
        'message',
        'attachment_url',
        'metadata',
        'is_hidden',
        'hidden_at',
        'hidden_by',
        'moderation_note',
        'is_flagged',
        'flag_reason',
        'ai_score',
        'ai_reviewed_at',
        'is_edited',
        'edited_at',
        'reply_to_message_id',
        'reaction_summary',
        'reported_count',
    ];

    protected $casts = [
        'metadata'         => 'array',
        'reaction_summary' => 'array',
        'is_hidden'        => 'boolean',
        'is_flagged'       => 'boolean',
        'is_edited'        => 'boolean',
        'ai_score'         => 'float',
        'reported_count'   => 'integer',
        'hidden_at'        => 'datetime',
        'edited_at'        => 'datetime',
        'ai_reviewed_at'   => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'sender_person_id');
    }

    /**
     * المرسل الإداري (رسائل الإدارة الرسمية داخل المحادثة).
     */
    public function senderUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    /**
     * هل الرسالة رسمية من الإدارة؟
     */
    public function isSystemMessage(): bool
    {
        return $this->sender_user_id !== null || $this->type === 'system';
    }

    public function hiddenForPersons(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'chat_message_hidden_users', 'chat_message_id', 'person_id')
            ->withPivot('created_at');
    }

    /**
     * بلاغات المستخدمين على هذه الرسالة.
     */
    public function reports(): HasMany
    {
        return $this->hasMany(ChatReport::class, 'message_id');
    }

    /**
     * الرسالة التي رد عليها المُرسِل.
     */
    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_message_id');
    }

    /**
     * الردود على هذه الرسالة.
     */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'reply_to_message_id');
    }

    /**
     * سجل إجراءات الإشراف على هذه الرسالة.
     */
    public function moderationLogs(): HasMany
    {
        return $this->hasMany(ChatModerationLog::class, 'message_id');
    }

    /**
     * الإداري الذي أخفى الرسالة.
     */
    public function hiddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hidden_by');
    }

    // ── Scopes ─────────────────────────────────────────────────────

    /**
     * الرسائل الظاهرة للمستخدمين (لا تشمل المُخفاة من الإدارة).
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_hidden', false);
    }

    public function scopeHidden(Builder $query): Builder
    {
        return $query->where('is_hidden', true);
    }

    public function scopeFlagged(Builder $query): Builder
    {
        return $query->where('is_flagged', true);
    }


    /**
     * Check if message was sent within the deletion window (default 60 minutes)
     */
    public function isWithinDeleteForEveryoneWindow(int $minutes = 60): bool
    {
        if (!$this->created_at) {
            return false;
        }

        // Must be non-negative and <= $minutes
        $diff = $this->created_at->diffInMinutes(now(), false);
        return $diff >= 0 && $diff <= $minutes;
    }

    /**
     * Check if person can delete this message for everyone
     */
    public function canDeleteForEveryone(int $personId): bool
    {
        return (int) $this->sender_person_id === (int) $personId && $this->isWithinDeleteForEveryoneWindow(60);
    }

    /**
     * Check if message was sent within the editing window (default 60 minutes)
     */
    public function isWithinEditWindow(int $minutes = 60): bool
    {
        if (!$this->created_at) {
            return false;
        }

        $diff = $this->created_at->diffInMinutes(now(), false);
        return $diff >= 0 && $diff <= $minutes;
    }

    /**
     * Check if person can edit this message
     */
    public function canEdit(int $personId): bool
    {
        return (int) $this->sender_person_id === (int) $personId
            && $this->isWithinEditWindow(60)
            && !$this->trashed();
    }
}

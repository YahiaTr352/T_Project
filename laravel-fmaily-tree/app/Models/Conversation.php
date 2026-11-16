<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Conversation extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'conversations';

    /**
     * قيم افتراضية تُضبط في الذاكرة مباشرة بعد الإنشاء
     * (لا يعيد Eloquent قيم قاعدة البيانات الافتراضية بعد INSERT).
     */
    protected $attributes = [
        'is_locked'               => false,
        'slow_mode_seconds'       => 0,
        'allow_attachments'       => true,
        'max_message_length'      => 2000,
        'auto_moderation_enabled' => true,
    ];

    protected $fillable = [
        'conversable_type',
        'conversable_id',
        'title',
        'type',
        'is_active',
        'last_message_at',
        'last_message_preview',
        'messages_count',
        'is_locked',
        'locked_at',
        'locked_by',
        'lock_reason',
        'slow_mode_seconds',
        'allow_attachments',
        'max_message_length',
        'auto_moderation_enabled',
        'last_pinned_message_id',
    ];

    protected $casts = [
        'is_active'              => 'boolean',
        'is_locked'              => 'boolean',
        'allow_attachments'      => 'boolean',
        'auto_moderation_enabled' => 'boolean',
        'last_message_at'        => 'datetime',
        'locked_at'              => 'datetime',
        'slow_mode_seconds'      => 'integer',
        'max_message_length'     => 'integer',
    ];

    // ── Relationships ──────────────────────────────────────────────

    /**
     * The owning chatable entity (e.g. WalkingGroup, FamilyEvent, etc.).
     */
    public function conversable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Messages belonging to this conversation.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    /**
     * Participant state records (read markers, mute status).
     */
    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    /**
     * الحظر النشط على هذه المحادثة.
     */
    public function bans(): HasMany
    {
        return $this->hasMany(ChatBan::class);
    }

    /**
     * بلاغات المستخدمين على رسائل هذه المحادثة.
     */
    public function reports(): HasMany
    {
        return $this->hasMany(ChatReport::class);
    }

    /**
     * سجل إجراءات الإشراف على هذه المحادثة.
     */
    public function moderationLogs(): HasMany
    {
        return $this->hasMany(ChatModerationLog::class);
    }

    /**
     * الرسالة المثبتة حالياً.
     */
    public function pinnedMessage(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'last_pinned_message_id');
    }

    /**
     * الإداري الذي قفل المحادثة.
     */
    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    // ── Scopes ─────────────────────────────────────────────────────

    public function scopeLocked(Builder $query): Builder
    {
        return $query->where('is_locked', true);
    }

    public function scopeUnlocked(Builder $query): Builder
    {
        return $query->where('is_locked', false);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    // ── Helper Methods ─────────────────────────────────────────────

    /**
     * Get or create a participant entry for a person.
     */
    public function getParticipant(int $personId): ConversationParticipant
    {
        return $this->participants()->firstOrCreate(
            ['person_id' => $personId],
            ['joined_at' => now(), 'is_muted' => true]
        );
    }

    /**
     * Check if a specific person has muted notifications for this conversation.
     */
    public function isMutedBy(int $personId): bool
    {
        return $this->participants()
            ->where('person_id', $personId)
            ->where('is_muted', true)
            ->exists();
    }

    /**
     * Calculate unread message count for a given person.
     *
     * لا تُحتسب رسائل الشخص نفسه ضمن غير المقروء.
     */
    public function getUnreadCountFor(int $personId): int
    {
        $participant = $this->participants()->where('person_id', $personId)->first();

        $query = $this->messages()->where(function ($q) use ($personId) {
            $q->whereNull('sender_person_id')
                ->orWhere('sender_person_id', '!=', $personId);
        });

        if ($participant && $participant->last_read_message_id) {
            $query->where('id', '>', $participant->last_read_message_id);
        }

        return $query->count();
    }

    /**
     * هل المشارك مشرف في هذه المحادثة؟
     */
    public function isParticipantModerator(int $personId): bool
    {
        $role = $this->participants()->where('person_id', $personId)->value('role');

        return in_array((string) $role, ['moderator', 'owner'], true);
    }
}

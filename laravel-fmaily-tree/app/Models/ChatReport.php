<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * بلاغ من مستخدم على رسالة في محادثة.
 */
class ChatReport extends BaseModel
{
    protected $table = 'chat_reports';

    protected $fillable = [
        'reporter_person_id',
        'conversation_id',
        'message_id',
        'reason',
        'details',
        'status',
        'handled_by',
        'handled_at',
        'admin_note',
    ];

    protected $casts = [
        'handled_at' => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'reporter_person_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'message_id');
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    // ── Scopes ─────────────────────────────────────────────────────

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', ['pending', 'reviewing']);
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeForConversation(Builder $query, int $conversationId): Builder
    {
        return $query->where('conversation_id', $conversationId);
    }

    // ── Helpers ────────────────────────────────────────────────────

    public function isPending(): bool
    {
        return in_array($this->status, ['pending', 'reviewing'], true);
    }

    public function reasonLabel(): string
    {
        return config('chat.report_reasons')[$this->reason] ?? 'سبب آخر';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending'   => 'جديد',
            'reviewing' => 'قيد المراجعة',
            'resolved'  => 'تمت المعالجة',
            'dismissed' => 'مرفوض',
            default     => $this->status,
        };
    }

    /**
     * لون شارة الحالة في لوحة التحكم.
     */
    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'pending'   => 'warning',
            'reviewing' => 'info',
            'resolved'  => 'success',
            default     => 'secondary',
        };
    }
}

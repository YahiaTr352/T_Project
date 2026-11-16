<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * كلمة ممنوعة في المحادثات مع سلوك الإجراء المرتبط بها.
 */
class ChatBlockedWord extends BaseModel
{
    protected $table = 'chat_blocked_words';

    protected $fillable = [
        'word',
        'severity',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Scopes ─────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSeverity(Builder $query, string $severity): Builder
    {
        return $query->where('severity', $severity);
    }

    // ── Helpers ────────────────────────────────────────────────────

    public function severityLabel(): string
    {
        return config('chat.severity')[$this->severity] ?? $this->severity;
    }
}

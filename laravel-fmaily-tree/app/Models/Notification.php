<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Notification extends Model
{
    protected $fillable = [
        'user_id',
        'person_id',
        'title',
        'body',
        'channels',
        'targeting_filters',
        'media_type',
        'media_path',
        'status',
        'rejection_reason',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'scheduled_at',
        'sent_at',
    ];

    protected $appends = [
        'media_url',
        'status_label',
    ];

    protected $casts = [
        'channels' => 'array',
        'targeting_filters' => 'array',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending_approval' => 'قيد المراجعة والاعتماد',
            'approved' => 'معتمدة / جاري الإرسال',
            'rejected' => 'مرفوضة',
            'sending' => 'جاري الإرسال',
            'sent' => 'تم الإرسال',
            'failed' => 'فشل الإرسال',
            'partial' => 'جزئي',
            'delivery_disabled' => 'الإرسال معطّل',
            default => $this->status ?? 'غير معروف',
        };
    }

    public function invitationSubmissionMessage(): string
    {
        return match ($this->status) {
            'delivery_disabled' => 'تم حفظ الدعوة في السجل. الإرسال معطّل في هذه البيئة.',
            'failed' => 'تم حفظ الدعوة، لكن تعذّر إرسالها. تحقق من قنوات الإرسال وبيانات المستلمين.',
            'sent' => 'تم إرسال الدعوة.',
            default => 'تم حفظ الدعوة وبدأت عملية الإرسال.',
        };
    }

    public function getMediaUrlAttribute(): ?string
    {
        if (! $this->media_path) {
            return null;
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->url($this->media_path);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
    public function recipients(): HasMany
    {
        return $this->hasMany(NotificationRecipient::class);
    }

    public function dismissals(): HasMany
    {
        return $this->hasMany(NotificationDismissal::class);
    }

    public function persons()
    {
        return $this->belongsToMany(Person::class, 'notification_recipients')
            ->withTimestamps();
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class);
    }

    public function ultramsgSendLogs(): HasMany
    {
        return $this->hasMany(UltramsgSendLog::class);
    }

    public function scopePendingApproval($query)
    {
        return $query->where('status', 'pending_approval');
    }

    public function scopeReceivedInvitations(Builder $query, int $personId): Builder
    {
        return $query->whereNotNull('person_id')
            ->whereIn('status', ['sent', 'partial'])
            ->whereHas('recipients', fn (Builder $recipients) => $recipients->where('person_id', $personId));
    }

    public function scopeApproved($query)
    {
        return $query->whereIn('status', ['approved', 'sending', 'sent']);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }
}

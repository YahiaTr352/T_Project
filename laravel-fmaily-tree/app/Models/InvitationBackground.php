<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class InvitationBackground extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'name',
        'image_path',
        'uploaded_by',
        'status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
        'is_template',
    ];

    protected $casts = [
        'is_template' => 'boolean',
        'reviewed_at' => 'datetime',
    ];

    protected $appends = [
        'image_url',
        'status_label',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'uploaded_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image_path) {
            return null;
        }
        if (filter_var($this->image_path, FILTER_VALIDATE_URL)) {
            return $this->image_path;
        }
        try {
            return Storage::disk('public')->url($this->image_path);
        } catch (\Throwable) {
            return null;
        }
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED => 'معتمدة',
            self::STATUS_REJECTED => 'مرفوضة',
            default => 'قيد المراجعة',
        };
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function toPickerArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'image_url' => $this->image_url,
            'is_template' => (bool) $this->is_template,
        ];
    }
}
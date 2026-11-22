<?php

namespace App\Models;

use App\Services\Invitations\CreativeTextLayout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class CreativeTemplate extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const MODE_READY = 'ready';
    public const MODE_OVERLAY = 'overlay';
    public const MODES = [
        self::MODE_READY => 'صورة ثابتة',
        self::MODE_OVERLAY => 'قالب قابل للتخصيص',
    ];

    protected $fillable = [
        'invitation_type',
        'name',
        'description',
        'image_path',
        'text_layout',
        'design_mode',
        'uploaded_by',
        'publication_consented_at',
        'status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'publication_consented_at' => 'datetime',
        'text_layout' => 'array',
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
            self::STATUS_APPROVED => 'معتمد',
            self::STATUS_REJECTED => 'مرفوض',
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

    public function scopeForDesignMode(Builder $query, string $mode): Builder
    {
        return $query->where(function (Builder $query) use ($mode) {
            $query->where('design_mode', $mode);
            if ($mode === self::MODE_OVERLAY) {
                $query->orWhereNull('design_mode');
            }
        });
    }

    public function designMode(): string
    {
        return $this->design_mode ?? self::MODE_OVERLAY;
    }

    public function toPickerArray(): array
    {
        return [
            'invitation_type' => $this->invitation_type,
            'id' => $this->id,
            'name' => $this->name,
            'image_url' => $this->image_url,
            'design_mode' => $this->designMode(),
            'variable_keys' => $this->variableKeys(),
        ];
    }

    public function variableKeys(): array
    {
        if ($this->design_mode === self::MODE_OVERLAY && $this->invitation_type) {
            return array_column(array_slice(CreativeTextLayout::fields($this->invitation_type), 1), 'key');
        }
        $layout = $this->text_layout ?? [];
        $variables = array_diff_key($layout, array_flip(['title', 'message', 'note']));

        return array_keys(array_filter($variables, fn (array $box) => $box['enabled'] ?? true));
    }

    public function awaitsPublicationConsent(): bool
    {
        return $this->uploaded_by !== null
            && $this->status === self::STATUS_PENDING
            && $this->publication_consented_at === null;
    }
}

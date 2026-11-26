<?php

namespace App\Models;

use App\Services\Invitations\CreativeTextLayout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreativeTemplate extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const MODE_READY = 'ready';
    public const MODE_OVERLAY = 'overlay';
    public const MODE_ARTWORK = 'artwork';
    public const MODE_SVG = 'svg';
    public const MODE_WEB_ARTWORK = 'web_artwork';
    public const WEB_MODES = [self::MODE_SVG, self::MODE_WEB_ARTWORK];
    public const MODES = [
        self::MODE_READY => 'صورة ثابتة',
        self::MODE_OVERLAY => 'قالب قابل للتخصيص',
        self::MODE_ARTWORK => 'تصميم مع كتابة قابلة للتعديل',
        self::MODE_SVG => 'قالب SVG قابل للتحرير',
        self::MODE_WEB_ARTWORK => 'تصميم من محرّر الأعضاء',
    ];

    protected $fillable = [
        'invitation_type',
        'name',
        'description',
        'image_path',
        'editable_image_path',
        'editable_text',
        'svg_path',
        'svg_inspection',
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
        'editable_text' => 'array',
        'svg_inspection' => 'array',
    ];

    protected $hidden = ['svg_path'];

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
        return url('storage/'.$this->image_path);
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

    public function scopeForEditablePicker(Builder $query): Builder
    {
        return $query->whereNotNull('invitation_type');
    }

    public function scopeAvailableInApp(Builder $query, bool $memberFields = false): Builder
    {
        return $query->where(function (Builder $query) use ($memberFields) {
            $query->whereNull('design_mode')->orWhereNotIn('design_mode', self::WEB_MODES);
            if ($memberFields) {
                $query->orWhere(fn (Builder $prepared) => $prepared->where('design_mode', self::MODE_WEB_ARTWORK)->whereNotNull('editable_text'));
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
            'artwork_revision' => $this->artworkRevision(),
            'design_mode' => $this->designMode(),
            'variable_keys' => $this->variableKeys(),
            'editable_text_fields' => $this->editableTextFields(),
        ];
    }

    public function artworkRevision(): ?string
    {
        if ($this->designMode() === self::MODE_SVG && $this->image_path) {
            return hash('sha256', $this->image_path);
        }

        return $this->hasPreparedArtwork() && $this->editable_image_path
            ? hash('sha256', $this->editable_image_path) : null;
    }

    public function hasPreparedArtwork(): bool
    {
        return in_array($this->designMode(), [self::MODE_ARTWORK, self::MODE_WEB_ARTWORK], true) && ! empty($this->editable_text);
    }

    public function canPrepareArtwork(): bool
    {
        return ! in_array($this->designMode(), self::WEB_MODES, true) && $this->invitation_type !== null && $this->image_path
            && ! filter_var($this->image_path, FILTER_VALIDATE_URL);
    }

    public function svgReadyForReview(): bool
    {
        return $this->designMode() === self::MODE_SVG && $this->svg_path
            && ! empty($this->svg_inspection['fields']) && empty($this->svg_inspection['warnings']);
    }

    public function variableKeys(): array
    {
        if ($this->editable_text) {
            return array_keys($this->editable_text);
        }
        if ($this->design_mode === self::MODE_OVERLAY && $this->invitation_type) {
            return array_column(array_slice(CreativeTextLayout::fields($this->invitation_type), 1), 'key');
        }
        $layout = $this->text_layout ?? [];
        $variables = array_diff_key($layout, array_flip(['title', 'message', 'note']));

        return array_keys(array_filter($variables, fn (array $box) => $box['enabled'] ?? true));
    }

    public function editableTextFields(): array
    {
        $fields = [];
        foreach ($this->editable_text ?? [] as $key => $box) {
            $fields[] = ['key' => $key, 'label' => $box['label'], 'default_text' => $box['text'] ?? '']
                + array_intersect_key($box, array_flip(['x', 'y', 'width', 'height']));
        }

        return $fields;
    }

    public function awaitsPublicationConsent(): bool
    {
        return $this->uploaded_by !== null
            && $this->status === self::STATUS_PENDING
            && $this->publication_consented_at === null;
    }
}

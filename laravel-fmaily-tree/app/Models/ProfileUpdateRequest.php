<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfileUpdateRequest extends Model
{
    use HasFactory;

    /**
     * Fields a member is never allowed to change through a profile_info request.
     * Legacy requests may still carry them in proposed_data, so they are filtered at review time too.
     *
     * @var list<string>
     */
    public const PROFILE_INFO_LOCKED_FIELDS = ['first_name', 'last_name', 'parent_id', 'mother_id'];

    protected $fillable = [
        'person_id',
        'requested_by_auth',
        'change_type',
        'target_id',
        'old_data',
        'proposed_data',
        'attached_files',
        'status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'old_data' => 'array',
        'proposed_data' => 'array',
        'attached_files' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', 'rejected');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function getChangeTypeLabelAttribute(): string
    {
        return match ($this->change_type) {
            'profile_info' => 'تعديل البيانات الشخصية',
            'contact_add' => 'إضافة حساب تواصل',
            'contact_update' => 'تعديل حساب تواصل',
            'contact_delete' => 'حذف حساب تواصل',
            'child_add' => 'إضافة ابن / ابنة',
            'marriage_add' => 'إضافة عقد زواج',
            'marriage_update' => 'تعديل عقد زواج',
            'location_add' => 'إضافة موقع / عنوان',
            'location_update' => 'تعديل موقع / عنوان',
            'location_delete' => 'حذف موقع / عنوان',
            default => 'تعديل بيانات',
        };
    }

    public function getChangeTypeIconAttribute(): string
    {
        return match ($this->change_type) {
            'profile_info' => 'fe-user',
            'contact_add', 'contact_update', 'contact_delete' => 'fe-phone',
            'child_add' => 'fe-users',
            'marriage_add', 'marriage_update' => 'fe-heart',
            'location_add', 'location_update', 'location_delete' => 'fe-map-pin',
            default => 'fe-edit',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'قيد الانتظار',
            'approved' => 'معتمد',
            'rejected' => 'مرفوض',
            default => $this->status,
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'badge-warning text-dark',
            'approved' => 'badge-success text-white',
            'rejected' => 'badge-danger text-white',
            default => 'badge-secondary',
        };
    }

    public function getProposedPhotoUrlAttribute(): ?string
    {
        return $this->storagePathToUrl($this->attached_files['photo_url'] ?? null);
    }

    /**
     * URL of the photo the member originally submitted (kept aside once a reviewer edits it).
     */
    public function getPhotoOriginalUrlAttribute(): ?string
    {
        return $this->storagePathToUrl($this->attached_files['photo_original_url'] ?? null);
    }

    /**
     * Whether a reviewer has already replaced the staged photo with an edited version.
     */
    public function getHasEditedPhotoAttribute(): bool
    {
        $files = $this->attached_files ?? [];

        return !empty($files['photo_edited_at'])
            && !empty($files['photo_url'])
            && ($files['photo_url'] !== ($files['photo_original_url'] ?? null));
    }

    /**
     * Whether the staged photo file still exists on the public disk.
     */
    public function getProposedPhotoExistsAttribute(): bool
    {
        $path = $this->attached_files['photo_url'] ?? null;

        if (empty($path) || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return !empty($path);
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->exists($path);
    }

    /**
     * Name of the reviewer who last edited the staged photo.
     */
    public function getPhotoEditedByAttribute(): ?string
    {
        $id = $this->attached_files['photo_edited_by'] ?? null;

        return $id ? optional(\App\Models\User::find($id))->name : null;
    }

    public function getHasPhotoUpdateAttribute(): bool
    {
        return !empty($this->attached_files['photo_url']);
    }

    /**
     * Keys of profile_info fields whose proposed value genuinely differs from the snapshot taken at submission.
     * Unchanged and locked fields are omitted, so they are neither offered for approval nor reported as excluded.
     *
     * @return list<string>
     */
    public function changedProfileFields(): array
    {
        if ($this->change_type !== 'profile_info') {
            return [];
        }

        $old = $this->old_data ?? [];
        $changed = [];

        $isSystem = in_array($this->requested_by_auth, ['system', 'system_normalization', 'console', 'admin'], true);
        $lockedFields = $isSystem ? ['parent_id', 'mother_id'] : self::PROFILE_INFO_LOCKED_FIELDS;

        foreach ($this->proposed_data ?? [] as $field => $proposedValue) {
            if ($field === 'photo_url' || in_array($field, $lockedFields, true)) {
                continue;
            }

            if (self::normalizeComparableValue($old[$field] ?? null) !== self::normalizeComparableValue($proposedValue)) {
                $changed[] = $field;
            }
        }

        return $changed;
    }

    public function isProfileFieldChanged(string $field): bool
    {
        return in_array($field, $this->changedProfileFields(), true);
    }

    /**
     * Normalise a scalar for change detection.
     * Date-cast attributes were historically snapshotted as ISO-8601 UTC strings (e.g. 1990-05-04T21:00:00.000000Z)
     * while members submit plain Y-m-d, so both are reduced to the app-timezone calendar date.
     */
    public static function normalizeComparableValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (!is_scalar($value)) {
            return json_encode($value) ?: null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $value)) {
            try {
                return Carbon::parse($value)->setTimezone(config('app.timezone'))->toDateString();
            } catch (\Throwable) {
                return $value;
            }
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) {
            return substr($value, 0, 10);
        }

        return $value;
    }

    protected function storagePathToUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return asset('storage/' . ltrim($path, '/'));
    }
}

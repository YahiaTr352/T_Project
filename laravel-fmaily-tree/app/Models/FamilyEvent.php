<?php

namespace App\Models;

use App\Models\Concerns\HasRichTextDescription;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property string|null $city
 * @property string|null $location
 * @property string|null $location_name
 * @property \Carbon\Carbon $event_date
 * @property bool $show_countdown
 * @property int $display_order
 * @property bool $is_active
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property-read bool $is_past
 * @property-read bool $is_upcoming
 * @property-read bool $is_today
 * @property-read string $formatted_date
 * @property-read string $formatted_date_arabic
 * @property-read string $time_remaining
 */
class FamilyEvent extends BaseModel
{
    use HasFactory;
    use HasRichTextDescription;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'description',
        'city',
        'location',
        'location_name',
        'event_date',
        'show_countdown',
        'display_order',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'event_date' => 'datetime',
        'show_countdown' => 'boolean',
        'display_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /* -------------------------------------------------------------------------- */
    /* Scopes                                                                     */
    /* -------------------------------------------------------------------------- */

    /**
     * Scope a query to only include active events.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include upcoming events (on or after now).
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('event_date', '>=', now());
    }

    /**
     * Scope a query to only include past events.
     */
    public function scopePast(Builder $query): Builder
    {
        return $query->where('event_date', '<', now());
    }

    /**
     * Scope a query to only include events with countdown enabled.
     */
    public function scopeWithCountdown(Builder $query): Builder
    {
        return $query->where('show_countdown', true);
    }

    /**
     * Scope a query to search events by title, description, city, or location_name.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $term = trim($term);

        return $query->where(function (Builder $q) use ($term) {
            $q->where('title', 'like', "%{$term}%")
              ->orWhere('city', 'like', "%{$term}%")
              ->orWhere('location_name', 'like', "%{$term}%")
              ->orWhere('description', 'like', "%{$term}%");
        });
    }

    /**
     * Scope to apply multiple filters.
     *
     * @param array{status?: string|null, search?: string|null, city?: string|null} $filters
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (!empty($filters['status'])) {
            match ($filters['status']) {
                'active' => $query->where('is_active', true),
                'inactive' => $query->where('is_active', false),
                'upcoming' => $query->where('is_active', true)->where('event_date', '>=', now()),
                'past' => $query->where('event_date', '<', now()),
                'countdown' => $query->where('show_countdown', true),
                default => null,
            };
        }

        if (!empty($filters['city'])) {
            $query->where('city', $filters['city']);
        }

        return $query;
    }

    /* -------------------------------------------------------------------------- */
    /* Accessors & Helpers                                                        */
    /* -------------------------------------------------------------------------- */

    /**
     * Check if the event date is in the past.
     */
    public function getIsPastAttribute(): bool
    {
        return $this->event_date->isPast();
    }

    /**
     * Check if the event date is in the future.
     */
    public function getIsUpcomingAttribute(): bool
    {
        return $this->event_date->isFuture();
    }

    /**
     * Check if the event date is today.
     */
    public function getIsTodayAttribute(): bool
    {
        return $this->event_date->isToday();
    }

    /**
     * Format date standard: YYYY-MM-DD HH:mm
     */
    public function getFormattedDateAttribute(): string
    {
        return $this->event_date->format('Y-m-d H:i');
    }

    /**
     * Format date in Arabic friendly representation.
     */
    public function getFormattedDateArabicAttribute(): string
    {
        return $this->event_date->locale('ar')->translatedFormat('l، d F Y - h:i A');
    }

    /**
     * Human-readable relative time remaining or elapsed.
     */
    public function getTimeRemainingAttribute(): string
    {
        return $this->event_date->locale('ar')->diffForHumans();
    }

    /**
     * Status badge metadata for UI components.
     *
     * @return array{label: string, class: string, icon: string}
     */
    public function getStatusBadgeAttribute(): array
    {
        if (!$this->is_active) {
            return [
                'label' => 'غير نشط',
                'class' => 'secondary',
                'icon' => 'fe-eye-off',
            ];
        }

        if ($this->is_today) {
            return [
                'label' => 'اليوم',
                'class' => 'danger',
                'icon' => 'fe-zap',
            ];
        }

        if ($this->is_upcoming) {
            return [
                'label' => 'قادمة',
                'class' => 'success',
                'icon' => 'fe-calendar',
            ];
        }

        return [
            'label' => 'منتهية',
            'class' => 'dark',
            'icon' => 'fe-check-circle',
        ];
    }

    /**
     * Legacy method preserved for backwards compatibility.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, static>
     */
    public static function getActiveEvents()
    {
        return self::where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('event_date')
            ->get();
    }
}

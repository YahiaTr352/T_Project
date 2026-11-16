<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class WalkingClub extends BaseModel
{
    public const CACHE_KEY_ACTIVE = 'walking_clubs_active_matrix';

    protected $fillable = [
        'slug',
        'name',
        'short_name',
        'sub_label',
        'min_steps',
        'max_steps',
        'calculation_start_date',
        'icon',
        'badge_color',
        'badge_dark',
        'accent_light',
        'accent_dark',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'min_steps'              => 'integer',
        'max_steps'              => 'integer',
        'calculation_start_date' => 'date:Y-m-d',
        'display_order'          => 'integer',
        'is_active'              => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(function () {
            self::clearCache();
        });

        static::deleted(function () {
            self::clearCache();
        });
    }

    /**
     * Clear cached active walking clubs.
     */
    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY_ACTIVE);
    }

    /**
     * Get all active walking clubs cached for performance.
     * Sorted ascending by min_steps.
     *
     * @return Collection<int, self>
     */
    public static function getActiveClubs(): Collection
    {
        return Cache::rememberForever(self::CACHE_KEY_ACTIVE, function () {
            return self::query()
                ->where('is_active', true)
                ->orderBy('min_steps', 'asc')
                ->orderBy('display_order', 'asc')
                ->get();
        });
    }

    /**
     * Find active club by slug or key.
     */
    public static function findBySlug(string $slug): ?self
    {
        return self::getActiveClubs()->firstWhere('slug', $slug);
    }

    /**
     * Resolve the highest qualifying active club for a given step count.
     * Evaluates descending from highest threshold to lowest.
     */
    public static function resolveForSteps(int $steps): ?self
    {
        $active = self::getActiveClubs()->sortByDesc('min_steps');

        foreach ($active as $club) {
            if ($steps >= $club->min_steps) {
                // If max_steps is defined, check if steps fall within range
                if ($club->max_steps !== null && $steps > $club->max_steps) {
                    continue;
                }
                return $club;
            }
        }

        return null;
    }

    /**
     * Check if a specific date is eligible to be counted for this club's streak.
     * Dates prior to calculation_start_date are strictly excluded.
     */
    public function isDateEligible(string|Carbon $date): bool
    {
        if (! $this->calculation_start_date) {
            return true;
        }

        $dateCarbon = $date instanceof Carbon ? $date : Carbon::parse($date);
        $startCarbon = Carbon::parse($this->calculation_start_date)->startOfDay();

        return $dateCarbon->startOfDay()->gte($startCarbon);
    }

    /**
     * Format club payload for Mobile API.
     */
    public function toMobileArray(): array
    {
        return [
            'id'                     => $this->id,
            'key'                    => $this->slug,
            'slug'                   => $this->slug,
            'name'                   => $this->name,
            'short_name'             => $this->short_name,
            'label'                  => $this->name,
            'short_label'            => $this->short_name,
            'sub_label'              => $this->sub_label ?? 'يومياً',
            'min_steps'              => (int) $this->min_steps,
            'max_steps'              => $this->max_steps !== null ? (int) $this->max_steps : null,
            'calculation_start_date' => $this->calculation_start_date?->format('Y-m-d'),
            'icon'                   => $this->icon ?? '👟',
            'badge_color'            => $this->badge_color ?? '#10B981',
            'badge_dark'             => $this->badge_dark ?? '#059669',
            'accent_light'           => $this->accent_light ?? '#D1FAE5',
            'accent_dark'            => $this->accent_dark ?? '#064E3B',
            'display_order'          => (int) $this->display_order,
            'is_active'              => (bool) $this->is_active,
        ];
    }
}

<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class WalkingTrek extends Model
{
    use HasFactory, SoftDeletes;

    public const KSA_TIMEZONE = 'Asia/Riyadh';

    protected $fillable = [
        'title',
        'description',
        'cover_image',
        'total_distance_km',
        'start_date',
        'end_date',
        'scheduled_at',
        'auto_start_counting',
        'status',
        'target_type',
        'walking_group_id',
        'is_loop',
        'created_by_user_id',
        'created_by_person_id',
    ];

    protected $casts = [
        'total_distance_km'   => 'decimal:2',
        'start_date'          => 'date',
        'end_date'            => 'date',
        'scheduled_at'        => 'datetime',
        'auto_start_counting' => 'boolean',
        'is_loop'             => 'boolean',
    ];

    protected $attributes = [
        'target_type' => 'family_community',
        'status'      => 'inactive',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function waypoints(): HasMany
    {
        return $this->hasMany(WalkingTrekWaypoint::class, 'walking_trek_id')
            ->orderBy('sort_order', 'asc');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(WalkingGroup::class, 'walking_group_id');
    }

    public function creatorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function creatorPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'created_by_person_id');
    }

    // ── Scopes ────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('status', 'inactive');
    }

    public function scopeFamilyCommunity(Builder $query): Builder
    {
        return $query->where('target_type', 'family_community');
    }

    /**
     * Scopes treks that are scheduled and whose scheduled_at datetime has arrived.
     */
    public function scopeReadyForActivation(Builder $query): Builder
    {
        $nowKsa = Carbon::now(self::KSA_TIMEZONE);
        return $query->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', $nowKsa);
    }

    // ── State Check Helpers ───────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    public function isInactive(): bool
    {
        return $this->status === 'inactive';
    }

    /**
     * Remaining seconds until scheduled activation (in KSA timezone).
     */
    public function getRemainingSecondsAttribute(): ?int
    {
        if (! $this->scheduled_at) {
            return null;
        }

        $nowKsa = Carbon::now(self::KSA_TIMEZONE);
        $scheduledKsa = Carbon::parse($this->scheduled_at, self::KSA_TIMEZONE);

        return max(0, $nowKsa->diffInSeconds($scheduledKsa, false));
    }

    /**
     * Formatted scheduled date and time in KSA timezone.
     */
    public function getScheduledAtKsaFormattedAttribute(): ?string
    {
        if (! $this->scheduled_at) {
            return null;
        }

        return Carbon::parse($this->scheduled_at)->setTimezone(self::KSA_TIMEZONE)->format('Y-m-d H:i');
    }

    // ── State Transitions ─────────────────────────────────────────

    /**
     * Activate this trek immediately, deactivating/completing other community treks.
     */
    public function activateNow(bool $updateStartDate = false): bool
    {
        return DB::transaction(function () use ($updateStartDate) {
            $targetType = $this->target_type ?: 'family_community';
            if ($targetType === 'family_community') {
                self::where(function ($q) {
                    $q->where('target_type', 'family_community')
                      ->orWhereNull('target_type');
                })
                    ->where('id', '!=', $this->id)
                    ->where('status', 'active')
                    ->update(['status' => 'inactive']);
            }

            $nowKsa = Carbon::now(self::KSA_TIMEZONE);

            $updates = [
                'status'       => 'active',
                'scheduled_at' => null,
            ];

            if ($updateStartDate || ($this->auto_start_counting && ! $this->start_date)) {
                $updates['start_date'] = $nowKsa->format('Y-m-d');
            }

            return $this->update($updates);
        });
    }

    /**
     * Deactivate this trek immediately.
     */
    public function deactivate(): bool
    {
        return $this->update([
            'status'       => 'inactive',
            'scheduled_at' => null,
        ]);
    }

    /**
     * Schedule this trek to be activated at a specific future datetime in KSA.
     */
    public function scheduleActivation(Carbon|string $datetime, bool $autoStartCounting = true): bool
    {
        $scheduledCarbon = is_string($datetime)
            ? Carbon::parse($datetime, self::KSA_TIMEZONE)
            : $datetime->setTimezone(self::KSA_TIMEZONE);

        return $this->update([
            'status'              => 'scheduled',
            'scheduled_at'        => $scheduledCarbon,
            'auto_start_counting' => $autoStartCounting,
        ]);
    }

    /**
     * Cancel an existing schedule and return to inactive state.
     */
    public function cancelSchedule(): bool
    {
        return $this->update([
            'status'       => 'inactive',
            'scheduled_at' => null,
        ]);
    }

    // ── Distance Calculation Helpers ──────────────────────────────

    /**
     * Calculate geographic Haversine distance in kilometers between two GPS coordinates.
     */
    public static function calculateHaversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(max(0.0, 1.0 - $a)));
        $distance = round($earthRadiusKm * $c, 2);

        if ($distance <= 0.0 && (abs($lat2 - $lat1) > 0.00001 || abs($lng2 - $lng1) > 0.00001)) {
            return 0.1;
        }

        return $distance;
    }

    public function recalculateDistance(): void
    {
        $total = 0.0;
        $cumulative = 0.0;

        $waypoints = $this->waypoints()->orderBy('sort_order', 'asc')->orderBy('id', 'asc')->get()->values();
        foreach ($waypoints as $index => $wp) {
            $wp->sort_order = $index + 1;

            if ($index === 0) {
                $wp->distance_from_prev_km = 0.0;
                $wp->cumulative_km = 0.0;
            } else {
                $distFromPrev = (float) $wp->distance_from_prev_km;
                if ($distFromPrev <= 0.0) {
                    $prevWp = $waypoints[$index - 1];
                    $distFromPrev = self::calculateHaversineKm(
                        (float) $prevWp->latitude,
                        (float) $prevWp->longitude,
                        (float) $wp->latitude,
                        (float) $wp->longitude
                    );
                }
                $wp->distance_from_prev_km = round($distFromPrev, 2);
                $cumulative = round($cumulative + $wp->distance_from_prev_km, 2);
                $wp->cumulative_km = $cumulative;
            }

            $wp->saveQuietly();
            $total = $cumulative;
        }

        $this->total_distance_km = round($total, 2);
        $this->saveQuietly();
    }
}

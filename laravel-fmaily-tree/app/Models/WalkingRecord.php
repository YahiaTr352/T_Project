<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalkingRecord extends Model
{
    use HasFactory;

    /** @var string[] */
    protected $fillable = [
        'user_id',
        'person_id',
        'date',
        'steps',
        'distance_km',
        'duration_minutes',
        'calories',
        'source',
        'device_info',
        'notes',
        'daily_goal_notified_at',
    ];

    protected $casts = [
        'date'                   => 'date',
        'steps'                  => 'integer',
        'distance_km'            => 'decimal:2',
        'duration_minutes'       => 'integer',
        'calories'               => 'integer',
        'daily_goal_notified_at' => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    // ── Scopes ────────────────────────────────────────────────────

    /** Records for a specific person (family-tree identity). */
    public function scopeForPerson($query, int $personId)
    {
        return $query->where('person_id', $personId);
    }

    /** Records for a specific user (admin / legacy). */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /** Records within an inclusive date range. */
    public function scopeForDateRange($query, $from, $to)
    {
        return $query->whereBetween('date', [$from, $to]);
    }

    /** Records for a specific calendar date. */
    public function scopeForDate($query, $date)
    {
        return $query->whereDate('date', $date);
    }

    /**
     * Get the resolved device info for this walking record.
     */
    public function getResolvedDeviceInfoAttribute(): ?string
    {
        if (! empty($this->device_info)) {
            return $this->device_info;
        }

        if ($this->person && $this->person->relationLoaded('devices')) {
            $device = $this->person->devices->sortByDesc('last_active_at')->first();
            if ($device) {
                return trim(($device->device_model ?: $device->device_name) . ' (' . ucfirst($device->os_platform ?: '') . ')');
            }
        }

        return null;
    }
}


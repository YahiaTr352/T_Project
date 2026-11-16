<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalkingGroupMember extends Model
{
    protected $fillable = [
        'walking_group_id',
        'person_id',
        'role',
        'status',
        'is_anonymous',
        'share_details',
        'notifications_enabled',
        'joined_at',
        'completed_at',
    ];

    protected $casts = [
        'joined_at'              => 'datetime',
        'completed_at'           => 'datetime',
        'share_details'          => 'boolean',
        'notifications_enabled'  => 'boolean',
    ];

    protected $appends = [
        'is_goal_reached',
        'completion_duration_text',
        'effective_is_anonymous',
        'is_inherited',
        'total_points_in_challenge',
    ];

    // ── Accessors ──────────────────────────────────────────────────

    public function getIsAnonymousAttribute($value): ?bool
    {
        $group = $this->relationLoaded('group') ? $this->group : null;
        if ($group && ! (bool) $group->is_public) {
            return false;
        }

        return is_null($value) ? null : (bool) $value;
    }

    public function getEffectiveIsAnonymousAttribute(): bool
    {
        $group = $this->relationLoaded('group') ? $this->group : $this->group()->first();
        if ($group && ! (bool) $group->is_public) {
            return false;
        }

        if (! is_null($this->attributes['is_anonymous'] ?? null)) {
            return (bool) $this->attributes['is_anonymous'];
        }

        $person = $this->person;
        return in_array($person?->walking_leaderboard_mode, ['hidden', 'anonymous'], true);
    }

    public function getIsInheritedAttribute(): bool
    {
        return is_null($this->attributes['is_anonymous'] ?? null);
    }

    public function getIsGoalReachedAttribute(): bool
    {
        return ! is_null($this->completed_at);
    }

    /**
     * Human-readable Arabic duration taken to achieve challenge target steps.
     */
    public function getCompletionDurationTextAttribute(): ?string
    {
        if (! $this->completed_at) {
            return null;
        }

        $startDate = $this->joined_at;
        if ($this->group && $this->group->start_date) {
            $groupStart = \Carbon\Carbon::parse($this->group->start_date)->startOfDay();
            if ($this->joined_at && $this->joined_at->lt($groupStart)) {
                $startDate = $groupStart;
            } elseif (! $this->joined_at) {
                $startDate = $groupStart;
            }
        }

        if (! $startDate) {
            $startDate = $this->created_at ?? $this->completed_at;
        }

        $completedAt = \Carbon\Carbon::parse($this->completed_at);
        $totalHours = max(1, (int) round($startDate->diffInHours($completedAt)));
        $days = (int) floor($totalHours / 24);
        $remainingHours = $totalHours % 24;

        if ($days === 0) {
            return $totalHours === 1 ? 'خلال ساعة واحدة' : "خلال {$totalHours} ساعات";
        }

        if ($days === 1) {
            return $remainingHours > 0 ? "خلال يوم و {$remainingHours} ساعة" : 'خلال يوم واحد';
        }

        if ($days === 2) {
            return $remainingHours > 0 ? "خلال يومين و {$remainingHours} ساعة" : 'خلال يومين';
        }

        if ($days >= 3 && $days <= 10) {
            return $remainingHours > 0 ? "خلال {$days} أيام و {$remainingHours} ساعة" : "خلال {$days} أيام";
        }

        return $remainingHours > 0 ? "خلال {$days} يوماً و {$remainingHours} ساعة" : "خلال {$days} يوماً";
    }

    public function getTotalPointsInChallengeAttribute(): int
    {
        if (array_key_exists('total_points_in_challenge', $this->attributes)) {
            return (int) $this->attributes['total_points_in_challenge'];
        }

        if (! $this->walking_group_id || ! $this->person_id) {
            return 0;
        }

        return (int) WalkingGroupDailyScore::where('walking_group_id', $this->walking_group_id)
            ->where('person_id', $this->person_id)
            ->sum('points_earned');
    }

    // ── Relationships ──────────────────────────────────────────────

    public function group(): BelongsTo
    {
        return $this->belongsTo(WalkingGroup::class, 'walking_group_id');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function dailyScores()
    {
        return $this->hasMany(WalkingGroupDailyScore::class, 'person_id', 'person_id')
            ->where('walking_group_id', $this->walking_group_id);
    }
}

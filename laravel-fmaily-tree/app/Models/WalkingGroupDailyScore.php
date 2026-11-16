<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalkingGroupDailyScore extends Model
{
    use HasFactory;

    protected $table = 'walking_group_daily_scores';

    protected $fillable = [
        'walking_group_id',
        'person_id',
        'date',
        'daily_steps',
        'daily_rank',
        'points_earned',
        'group_members_count',
    ];

    protected $casts = [
        'date'                => 'date',
        'daily_steps'         => 'integer',
        'daily_rank'          => 'integer',
        'points_earned'       => 'integer',
        'group_members_count' => 'integer',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(WalkingGroup::class, 'walking_group_id');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id');
    }
}

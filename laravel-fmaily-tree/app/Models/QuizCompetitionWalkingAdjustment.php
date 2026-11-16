<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizCompetitionWalkingAdjustment extends BaseModel
{
    use HasFactory;

    protected $table = 'quiz_competition_walking_adjustments';

    protected $fillable = [
        'quiz_question_id',
        'person_id',
        'walking_group_id',
        'points',
        'type',
        'reason',
        'date',
        'created_by',
    ];

    protected $casts = [
        'points' => 'integer',
        'date'   => 'date',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class, 'quiz_question_id');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(WalkingGroup::class, 'walking_group_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

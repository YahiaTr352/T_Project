<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizCompetitionWalkingScore extends BaseModel
{
    use HasFactory;

    protected $table = 'quiz_competition_walking_scores';

    protected $fillable = [
        'quiz_question_id',
        'person_id',
        'date',
        'steps',
        'daily_rank',
        'points_earned',
        'gender',
    ];

    protected $casts = [
        'date' => 'date',
        'steps' => 'integer',
        'daily_rank' => 'integer',
        'points_earned' => 'integer',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class, 'quiz_question_id');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id');
    }
}

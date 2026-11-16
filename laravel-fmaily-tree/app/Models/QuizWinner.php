<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizWinner extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'quiz_question_id',
        'user_id',
        'person_id',
        'position',
        'score',
        'steps',
        'gender',
    ];

    protected $casts = [
        'position' => 'integer',
        'score' => 'integer',
        'steps' => 'integer',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class, 'quiz_question_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function getPositionNameAttribute(): string
    {
        $positions = [
            1 => 'المركز الأول',
            2 => 'المركز الثاني',
            3 => 'المركز الثالث',
        ];

        return $positions[$this->position] ?? "المركز {$this->position}";
    }
}

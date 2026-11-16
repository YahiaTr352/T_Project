<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizRegistration extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'quiz_competition_id',
        'person_id',
        'user_id',
    ];

    public function competition(): BelongsTo
    {
        return $this->belongsTo(QuizCompetition::class, 'quiz_competition_id');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

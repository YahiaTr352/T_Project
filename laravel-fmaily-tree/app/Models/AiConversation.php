<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AiConversation extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'person_id',
        'session_key',
        'channel',
        'title',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $conversation) {
            if (! filled($conversation->uuid)) {
                $conversation->uuid = (string) Str::uuid();
            }
        });
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class)->orderBy('id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobileAuthSession extends Model
{
    use HasFactory;

    protected $table = 'mobile_auth_sessions';

    protected $fillable = [
        'person_id',
        'auth_method',
        'device_uuid',
        'access_token_hash',
        'refresh_token_hash',
        'expires_at',
        'refresh_expires_at',
        'last_used_at',
        'meta',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'refresh_expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'meta' => 'array',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id');
    }

    public function isAccessTokenValid(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isFuture();
    }

    public function isRefreshTokenValid(): bool
    {
        return $this->refresh_expires_at !== null && $this->refresh_expires_at->isFuture();
    }

    public function scopeByAccessToken(Builder $query, string $plainToken): Builder
    {
        return $query->where('access_token_hash', hash('sha256', $plainToken));
    }

    public function scopeByRefreshToken(Builder $query, string $plainRefreshToken): Builder
    {
        return $query->where('refresh_token_hash', hash('sha256', $plainRefreshToken));
    }

    public function scopeByPersonId(Builder $query, int $personId): Builder
    {
        return $query->where('person_id', $personId);
    }

    public function scopeExceptDevice(Builder $query, ?string $deviceUuid): Builder
    {
        if (empty($deviceUuid)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($deviceUuid) {
            $q->whereNull('device_uuid')->orWhere('device_uuid', '!=', $deviceUuid);
        });
    }
}
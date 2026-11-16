<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonTrustedDevice extends Model
{
    use HasFactory;

    protected $table = 'person_trusted_devices';

    protected $fillable = [
        'person_id',
        'device_uuid',
        'device_name',
        'device_model',
        'os_platform',
        'os_version',
        'app_version',
        'last_ip',
        'verified_at',
        'whatsapp_grace_expires_at',
        'admin_alerted',
        'last_used_at',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
        'whatsapp_grace_expires_at' => 'datetime',
        'admin_alerted' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id');
    }

    public function isTrusted(): bool
    {
        return $this->verified_at !== null;
    }

    public function isGraceActive(): bool
    {
        return $this->whatsapp_grace_expires_at !== null && $this->whatsapp_grace_expires_at->isFuture();
    }

    public function isGraceExpired(): bool
    {
        return $this->whatsapp_grace_expires_at !== null && $this->whatsapp_grace_expires_at->isPast();
    }

    public function scopeTrusted(Builder $query): Builder
    {
        return $query->whereNotNull('verified_at');
    }

    public function scopePendingGrace(Builder $query): Builder
    {
        return $query->whereNull('verified_at')
            ->whereNotNull('whatsapp_grace_expires_at')
            ->where('whatsapp_grace_expires_at', '>', now());
    }

    public function scopeExpiredGrace(Builder $query): Builder
    {
        return $query->whereNull('verified_at')
            ->whereNotNull('whatsapp_grace_expires_at')
            ->where('whatsapp_grace_expires_at', '<=', now())
            ->where('admin_alerted', false);
    }
}

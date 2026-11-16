<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class AiProvider extends Model
{
    protected $fillable = [
        'driver',
        'name',
        'api_key',
        'models',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'models' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $hidden = [
        'api_key',
    ];

    public function setApiKeyAttribute(?string $value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['api_key'] = null;

            return;
        }

        $this->attributes['api_key'] = Crypt::encryptString($value);
    }

    public function getDecryptedApiKey(): ?string
    {
        $raw = $this->attributes['api_key'] ?? null;

        if (! filled($raw)) {
            return null;
        }

        try {
            return Crypt::decryptString($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    public function hasApiKey(): bool
    {
        return filled($this->attributes['api_key'] ?? null);
    }

    public function modelFor(string $purpose): ?string
    {
        $models = $this->models ?? [];

        return $models[$purpose]
            ?? config("ai.models.{$this->driver}.{$purpose}");
    }
}

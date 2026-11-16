<?php

namespace App\Support;

use App\Models\AiSetting;
use Illuminate\Support\Facades\Schema;

class AiSettings
{
    private static ?array $cache = null;

    private static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = self::loadFromDatabase();
        }

        return self::$cache;
    }

    private static function loadFromDatabase(): array
    {
        if (! Schema::hasTable('ai_settings')) {
            return [];
        }

        try {
            return AiSetting::query()
                ->pluck('value', 'key')
                ->map(fn ($value) => is_array($value) ? $value : [])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    public static function forgetCache(): void
    {
        self::$cache = null;
    }

    public static function features(): array
    {
        $defaults = [];
        foreach (config('ai.features', []) as $key => $meta) {
            $defaults[$key] = [
                'enabled' => (bool) ($meta['default_enabled'] ?? false),
                'model' => null,
            ];
        }

        $stored = self::all()['features'] ?? [];

        foreach ($defaults as $key => $default) {
            if (isset($stored[$key]) && is_array($stored[$key])) {
                $defaults[$key] = array_merge($default, array_intersect_key($stored[$key], $default));
            }
        }

        return $defaults;
    }

    public static function isFeatureEnabled(string $feature): bool
    {
        return (bool) (self::features()[$feature]['enabled'] ?? false);
    }

    public static function featureModel(string $feature): ?string
    {
        $model = self::features()[$feature]['model'] ?? null;

        return filled($model) ? $model : null;
    }

    public static function budget(): array
    {
        $defaults = [
            'monthly_usd' => (float) config('ai.budget.monthly_usd', 20),
            'soft_stop' => (bool) config('ai.budget.soft_stop', true),
        ];

        $stored = self::all()['budget'] ?? [];

        return array_merge($defaults, array_intersect_key($stored, $defaults));
    }

    public static function rateLimit(): array
    {
        $defaults = config('ai.rate_limit', [
            'per_user_per_minute' => 10,
            'per_ip_per_minute' => 20,
        ]);

        $stored = self::all()['rate_limit'] ?? [];

        return array_merge($defaults, array_intersect_key($stored, $defaults));
    }


    public static function ttsVoice(): string
    {
        $stored = self::all()['tts_voice'] ?? null;
        if (is_string($stored) && filled($stored)) {
            return $stored;
        }

        return config('ai.default_tts_voice', 'onyx');
    }

    public static function prompts(): array
    {
        $defaults = config('ai.prompts', []);
        $stored = self::all()['prompts'] ?? [];

        return array_merge($defaults, array_intersect_key($stored, $defaults));
    }

    public static function prompt(string $feature): string
    {
        return (string) (self::prompts()[$feature] ?? config("ai.prompts.{$feature}", ''));
    }

    public static function meta(): array
    {
        $defaults = [
            'updated_by' => null,
            'updated_by_name' => null,
            'updated_at' => null,
        ];

        $stored = self::all()['meta'] ?? [];

        return array_merge($defaults, array_intersect_key($stored, $defaults));
    }
}

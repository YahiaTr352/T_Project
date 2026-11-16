<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'group',
        'type',
    ];

    public const CACHE_KEY = 'system_settings_all_matrix';

    /**
     * Get a setting value by key with high-speed forever cache.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::getAllSettings();

        return $all[$key] ?? $default;
    }

    /**
     * Get all settings grouped by their category.
     */
    public static function getGroup(string $group): array
    {
        $records = self::where('group', $group)->get();
        $output = [];

        foreach ($records as $record) {
            $output[$record->key] = $record->value;
        }

        return $output;
    }

    /**
     * Set or update a setting value, invalidating cache immediately.
     */
    public static function set(string $key, mixed $value, ?string $group = null, ?string $type = null): self
    {
        $attributes = ['value' => $value];
        if ($group !== null) {
            $attributes['group'] = $group;
        }
        if ($type !== null) {
            $attributes['type'] = $type;
        }

        $setting = self::updateOrCreate(['key' => $key], $attributes);
        self::clearCache();

        return $setting;
    }

    /**
     * Retrieve all settings as an associative key-value map.
     */
    public static function getAllSettings(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return self::pluck('value', 'key')->toArray();
        });
    }

    /**
     * Flush settings cache.
     */
    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}

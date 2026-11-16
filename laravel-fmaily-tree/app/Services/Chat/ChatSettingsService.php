<?php

namespace App\Services\Chat;

use App\Models\ChatBlockedWord;
use App\Models\SystemSetting;

/**
 * قراءة وكتابة إعدادات المحادثات من مخزن الإعدادات العام (group = chat).
 */
class ChatSettingsService
{
    public const GROUP = 'chat';

    /**
     * مدة صلاحية ذاكرة الكلمات الممنوعة (بالثواني).
     */
    protected const WORDS_CACHE_TTL = 300;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $wordsCache = null;

    /**
     * جلب إعداد واحد (بدون نوع).
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = SystemSetting::get($key, null);

        return $value === null || $value === '' ? $default : $value;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->get($key);

        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->get($key);

        if ($value === null) {
            return $default;
        }

        return is_numeric($value) ? (int) $value : $default;
    }

    public function float(string $key, float $default = 0.0): float
    {
        $value = $this->get($key);

        if ($value === null) {
            return $default;
        }

        return is_numeric($value) ? (float) $value : $default;
    }

    /**
     * @return array<mixed>
     */
    public function array(string $key, array $default = []): array
    {
        $value = $this->get($key);

        if ($value === null) {
            return $default;
        }

        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : $default;
    }

    /**
     * حفظ إعداد واحد.
     */
    public function set(string $key, mixed $value, string $type = 'text'): void
    {
        SystemSetting::set($key, $this->encode($value, $type), self::GROUP, $type);
    }

    /**
     * حفظ مجموعة إعدادات دفعة واحدة.
     *
     * @param  array<string, mixed>  $values
     */
    public function setMany(array $values, string $type = 'text'): void
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, is_array($value) ? 'json' : $type);
        }
    }

    /**
     * كل إعدادات الشات كخريطة key => value.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $all = SystemSetting::getAllSettings();

        return array_filter(
            $all,
            fn ($value, $key) => str_starts_with((string) $key, 'chat.'),
            ARRAY_FILTER_USE_BOTH
        );
    }

    /**
     * @return array<int, string> الكلمات الممنوعة النشطة
     */
    public function blockedWords(): array
    {
        if ($this->wordsCache !== null) {
            return $this->wordsCache;
        }

        $words = ChatBlockedWord::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->pluck('word')
            ->map(fn ($word) => trim((string) $word))
            ->filter(fn ($word) => $word !== '')
            ->values()
            ->all();

        $this->wordsCache = $words;

        return $words;
    }

    public function flush(): void
    {
        $this->wordsCache = null;
        SystemSetting::clearCache();
    }

    protected function encode(mixed $value, string $type): mixed
    {
        if ($type === 'json' || is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        if ($type === 'boolean') {
            return $value ? '1' : '0';
        }

        return $value;
    }
}

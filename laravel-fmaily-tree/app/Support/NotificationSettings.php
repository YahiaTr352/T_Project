<?php

namespace App\Support;

use App\Models\NotificationSetting;
use Illuminate\Support\Facades\Schema;

class NotificationSettings
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
        try {
            if (! Schema::hasTable('notification_settings')) {
                return [];
            }

            return NotificationSetting::query()
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

    public static function channels(): array
    {
        $defaults = ['whatsapp' => true, 'push' => true];
        $stored = self::all()['channels'] ?? [];

        return array_merge($defaults, array_intersect_key($stored, $defaults));
    }

    public static function enabledChannelKeys(): array
    {
        return array_keys(array_filter(self::channels()));
    }

    public static function isChannelEnabled(string $channel): bool
    {
        return (bool) (self::channels()[$channel] ?? false);
    }

    public static function canSendPush(): bool
    {
        return self::isChannelEnabled('push');
    }

    public static function canSendWhatsApp(): bool
    {
        return self::isChannelEnabled('whatsapp') && !self::isDailyLimitExceeded();
    }

    public static function isDailyLimitExceeded(): bool
    {
        return (bool) (self::dailyQuotaStats()['is_exceeded'] ?? false);
    }

    public static function ultramsg(): array
    {
        $defaults = [
            'instance_id' => null,
            'token' => null,
        ];

        $stored = self::all()['ultramsg'] ?? [];

        return array_merge($defaults, array_intersect_key($stored, $defaults));
    }

    public const PRESET_SAFE = 'safe';
    public const PRESET_WARMUP = 'warmup';
    public const PRESET_CUSTOM = 'custom';

    /**
     * Presets configuration with anti-ban best practice parameters.
     *
     * @return array<string, array{
     *     key: string,
     *     name: string,
     *     badge: string,
     *     badge_class: string,
     *     description: string,
     *     send_delay_min: int,
     *     send_delay_max: int,
     *     batch_size: int,
     *     batch_pause_seconds: int,
     *     daily_limit: int
     * }>
     */
    public static function presetDefinitions(): array
    {
        return [
            self::PRESET_SAFE => [
                'key' => self::PRESET_SAFE,
                'name' => 'الوضع الآمن (موصى به)',
                'badge' => 'موصى به',
                'badge_class' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-300',
                'description' => 'حماية متقدمة ضد الحظر — فاصل عشوائي بين 25 و 45 ثانية مع استراحة تبريد 3 دقائق كل 20 رسالة.',
                'send_delay_min' => 25,
                'send_delay_max' => 45,
                'batch_size' => 20,
                'batch_pause_seconds' => 180,
                'daily_limit' => 250,
            ],
            self::PRESET_WARMUP => [
                'key' => self::PRESET_WARMUP,
                'name' => 'وضع تسخين الشريحة (Warm-up)',
                'badge' => 'للأرقام الجديدة',
                'badge_class' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/80 dark:text-amber-300',
                'description' => 'مخصص للشرائح والأرقام الجديدة لتفادي الرقابة الصارمة — فواصل أطول ودفعات صغيرة واستراحات 5 دقائق.',
                'send_delay_min' => 40,
                'send_delay_max' => 70,
                'batch_size' => 10,
                'batch_pause_seconds' => 300,
                'daily_limit' => 50,
            ],
            self::PRESET_CUSTOM => [
                'key' => self::PRESET_CUSTOM,
                'name' => 'الوضع المخصص',
                'badge' => 'يدوي',
                'badge_class' => 'bg-slate-200 text-slate-800 dark:bg-slate-700 dark:text-slate-200',
                'description' => 'تحكم كامل يدوي في الفواصل الدنيا والعليا وحجم الدفعات والاستراحات والحد اليومي.',
                'send_delay_min' => 25,
                'send_delay_max' => 45,
                'batch_size' => 20,
                'batch_pause_seconds' => 180,
                'daily_limit' => 250,
            ],
        ];
    }

    public static function limits(): array
    {
        $defaults = [
            'preset' => self::PRESET_SAFE,
            'send_delay_min' => 25,
            'send_delay_max' => 45,
            'batch_size' => 20,
            'batch_pause_seconds' => 180,
            'daily_limit' => 250,
            'send_delay_ms' => 0,
            'auto_retry' => false,
        ];

        $stored = self::all()['limits'] ?? [];
        $merged = array_merge($defaults, $stored);

        $preset = $merged['preset'] ?? self::PRESET_SAFE;
        $definitions = self::presetDefinitions();

        // Enforce preset canonical values unless explicit custom preset
        if ($preset !== self::PRESET_CUSTOM && isset($definitions[$preset])) {
            $def = $definitions[$preset];
            $merged['send_delay_min'] = (int) $def['send_delay_min'];
            $merged['send_delay_max'] = (int) $def['send_delay_max'];
            $merged['batch_size'] = (int) $def['batch_size'];
            $merged['batch_pause_seconds'] = (int) $def['batch_pause_seconds'];
            $merged['daily_limit'] = (int) $def['daily_limit'];
        } else {
            $merged['send_delay_min'] = max(1, (int) ($merged['send_delay_min'] ?? 25));
            $merged['send_delay_max'] = max($merged['send_delay_min'], (int) ($merged['send_delay_max'] ?? 45));
            $merged['batch_size'] = max(0, (int) ($merged['batch_size'] ?? 20));
            $merged['batch_pause_seconds'] = max(0, (int) ($merged['batch_pause_seconds'] ?? 180));
            $merged['daily_limit'] = max(0, (int) ($merged['daily_limit'] ?? 0));
        }

        return $merged;
    }

    /**
     * Calculate staggered delays for an outgoing batch of messages.
     *
     * @return array<int, int>
     */
    public static function calculateStaggeredDelays(int $count): array
    {
        return app(\App\Services\WhatsApp\WhatsAppThrottleService::class)->calculateStaggeredDelays($count);
    }

    /**
     * Real-time daily quota stats for WhatsApp.
     */
    public static function dailyQuotaStats(): array
    {
        return app(\App\Services\WhatsApp\WhatsAppThrottleService::class)->getDailyQuotaStats();
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

<?php

namespace App\Services;

use App\Models\AppVersion;
use Illuminate\Support\Facades\Cache;

class AppVersionService
{
    public const CACHE_PREFIX = 'app_version_config_';

    /**
     * Get cached AppVersion model instance for platform.
     */
    public function getPlatformConfig(string $platform): ?AppVersion
    {
        $platform = strtolower($platform);

        return Cache::rememberForever(self::CACHE_PREFIX . $platform, function () use ($platform) {
            return AppVersion::where('platform', $platform)->first();
        });
    }

    /**
     * Clear cached config for a platform or all platforms.
     */
    public function clearCache(?string $platform = null): void
    {
        if ($platform) {
            Cache::forget(self::CACHE_PREFIX . strtolower($platform));
        } else {
            Cache::forget(self::CACHE_PREFIX . 'ios');
            Cache::forget(self::CACHE_PREFIX . 'android');
        }
    }

    /**
     * Evaluate incoming client version against the SemVer matrix.
     */
    public function evaluate(string $platform, string $clientVersion, string $locale = 'ar'): array
    {
        $config = $this->getPlatformConfig($platform);

        if (! $config) {
            return [
                'action' => 'UP_TO_DATE',
                'is_mandatory' => false,
                'client_version' => $clientVersion,
                'latest_version' => $clientVersion,
                'min_supported_version' => $clientVersion,
                'store_url' => null,
            ];
        }

        // 1. Maintenance Check (Highest Priority)
        if ($config->is_under_maintenance) {
            $msg = $locale === 'en'
                ? ($config->maintenance_message_en ?: 'The app is undergoing scheduled maintenance.')
                : ($config->maintenance_message_ar ?: 'التطبيق يخضع لعمليات صيانة مجدولة، سنعود قريباً.');

            return [
                'action' => 'MAINTENANCE',
                'is_mandatory' => true,
                'title' => $locale === 'en' ? 'Under Maintenance' : 'التطبيق تحت الصيانة',
                'message' => $msg,
                'client_version' => $clientVersion,
                'latest_version' => $config->latest_version,
                'store_url' => $config->store_url,
            ];
        }

        $title = $locale === 'en'
            ? ($config->title_en ?: 'New Update Available')
            : ($config->title_ar ?: 'تحديث جديد متاح لتطبيق العائلة');

        $notes = $locale === 'en'
            ? ($config->release_notes_en ?: 'Improvements and bug fixes.')
            : ($config->release_notes_ar ?: 'تحسينات في الأداء وإصلاحات وإضافات جديدة.');

        // 2. Force Update Check (Client version < min_supported_version)
        if ($config->requiresForceUpdate($clientVersion)) {
            return [
                'action' => 'FORCE_UPDATE',
                'is_mandatory' => true,
                'client_version' => $clientVersion,
                'latest_version' => $config->latest_version,
                'min_supported_version' => $config->min_supported_version,
                'store_url' => $config->store_url,
                'title' => $title,
                'release_notes' => $notes,
            ];
        }

        // 3. Optional Update Check (min_supported_version <= Client version < latest_version)
        if ($config->hasOptionalUpdate($clientVersion)) {
            return [
                'action' => 'OPTIONAL_UPDATE',
                'is_mandatory' => false,
                'client_version' => $clientVersion,
                'latest_version' => $config->latest_version,
                'min_supported_version' => $config->min_supported_version,
                'store_url' => $config->store_url,
                'title' => $title,
                'release_notes' => $notes,
            ];
        }

        // 4. Up-To-Date
        return [
            'action' => 'UP_TO_DATE',
            'is_mandatory' => false,
            'client_version' => $clientVersion,
            'latest_version' => $config->latest_version,
            'min_supported_version' => $config->min_supported_version,
            'store_url' => $config->store_url,
        ];
    }

    /**
     * Compute smart warnings & discrepancies for dashboard admin/data-entry guidance.
     */
    public function getAdminDiscrepancies(): array
    {
        $platforms = AppVersion::all();
        $discrepancies = [];

        foreach ($platforms as $item) {
            $warnings = [];
            $status = 'synced'; // 'synced', 'newer_on_store', 'danger_min_exceeds_store', 'no_sync_yet'

            if (! $item->store_detected_version) {
                $status = 'no_sync_yet';
                $warnings[] = [
                    'type' => 'info',
                    'message' => 'لم يتم الفحص التلقائي من المتجر بعد. اضغط زر المزامنة لجلب النسخة الحية.',
                ];
            } else {
                // Danger: min_supported > store_detected
                if ($item->isMinSupportedHigherThanStore()) {
                    $status = 'danger_min_exceeds_store';
                    $warnings[] = [
                        'type' => 'danger',
                        'message' => "تحذير حرج: الحد الأدنى الإجباري ({$item->min_supported_version}) أعلى من نسخة المتجر الحية ({$item->store_detected_version})! هذا سيقفل التطبيق على جميع المستخدمين في العالم دون تمكنهم من التحديث!",
                    ];
                }

                // Notice: Store is newer than configured latest_version
                if ($item->isStoreVersionNewer()) {
                    $status = 'newer_on_store';
                    $warnings[] = [
                        'type' => 'warning',
                        'message' => "تنبيه: النسخة على المتجر ({$item->store_detected_version}) أحدث من النسخة المسجلة بالنظام ({$item->latest_version}). يرجى مزامنة الإصدار لتفعيل التحديث للمستخدمين.",
                    ];
                }

                if (empty($warnings)) {
                    $warnings[] = [
                        'type' => 'success',
                        'message' => "النسخة الحالية متطابقة تماماً مع متجر {$item->platform} ({$item->latest_version}).",
                    ];
                }
            }

            $discrepancies[$item->platform] = [
                'model' => $item,
                'status' => $status,
                'warnings' => $warnings,
            ];
        }

        return $discrepancies;
    }
}

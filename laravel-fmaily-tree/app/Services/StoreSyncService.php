<?php

namespace App\Services;

use App\Models\AppVersion;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StoreSyncService
{
    /**
     * Sync latest store version for a specific platform.
     */
    public function syncPlatform(string $platform): array
    {
        $appVersion = AppVersion::where('platform', strtolower($platform))->first();

        if (! $appVersion) {
            return [
                'success' => false,
                'platform' => $platform,
                'message' => "سجل منصة {$platform} غير موجود.",
            ];
        }

        if (strtolower($platform) === 'ios') {
            return $this->syncAppleAppStore($appVersion);
        }

        if (strtolower($platform) === 'android') {
            return $this->syncGooglePlayStore($appVersion);
        }

        return [
            'success' => false,
            'platform' => $platform,
            'message' => 'منصة غير مدعومة.',
        ];
    }

    /**
     * Sync all platforms (iOS and Android).
     */
    public function syncAll(): array
    {
        return [
            'ios' => $this->syncPlatform('ios'),
            'android' => $this->syncPlatform('android'),
        ];
    }

    /**
     * Sync from official Apple iTunes Search/Lookup API.
     */
    protected function syncAppleAppStore(AppVersion $appVersion): array
    {
        $bundleId = $appVersion->bundle_id ?: 'com.alsorayia.app';

        try {
            $response = Http::timeout(8)
                ->get('https://itunes.apple.com/lookup', [
                    'bundleId' => $bundleId,
                    'country' => 'sa',
                    'timestamp' => now()->timestamp,
                ]);

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'platform' => 'ios',
                    'message' => 'تعذر الاتصال بـ Apple iTunes API (رمز الاستجابة: ' . $response->status() . ').',
                ];
            }

            $data = $response->json();

            if (empty($data['results']) || ! isset($data['results'][0]['version'])) {
                return [
                    'success' => false,
                    'platform' => 'ios',
                    'message' => 'التطبيق غير منشور أو لم يتم العثور على الإصدار للمُعرّف: ' . $bundleId,
                ];
            }

            $storeData = $data['results'][0];
            $liveVersion = $storeData['version'];
            $storeUrl = $storeData['trackViewUrl'] ?? $appVersion->store_url;

            $appVersion->update([
                'store_detected_version' => $liveVersion,
                'store_synced_at' => now(),
                'store_url' => $storeUrl ?: $appVersion->store_url,
            ]);

            app(AppVersionService::class)->clearCache('ios');

            return [
                'success' => true,
                'platform' => 'ios',
                'detected_version' => $liveVersion,
                'store_url' => $storeUrl,
                'message' => "تمت المزامنة بنجاح من App Store (الإصدار الحي: {$liveVersion}).",
            ];
        } catch (\Throwable $e) {
            Log::error('[StoreSyncService] iOS Sync failed: ' . $e->getMessage());

            return [
                'success' => false,
                'platform' => 'ios',
                'message' => 'حدث خطأ أثناء فحص متجر أبل: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Sync from Google Play Store.
     */
    protected function syncGooglePlayStore(AppVersion $appVersion): array
    {
        $package = $appVersion->bundle_id ?: 'com.alsorayia.app';

        try {
            $url = "https://play.google.com/store/apps/details?id={$package}&hl=en";

            $response = Http::timeout(12)
                ->connectTimeout(5)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
                    'Accept-Language' => 'en-US,en;q=0.9',
                ])
                ->get($url);

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'platform' => 'android',
                    'message' => 'لم يتم العثور على التطبيق أو تعذر الاتصال بـ Google Play (رمز: ' . $response->status() . ').',
                ];
            }

            $html = $response->body();
            $detectedVersion = null;

            // Extract software version pattern from Google Play HTML payload
            if (preg_match('/\[\[\["([0-9]+\.[0-9]+(\.[0-9]+)?)"\]\]/', $html, $matches)) {
                $detectedVersion = $matches[1];
            } elseif (preg_match('/"(\d+\.\d+\.\d+)"/', $html, $matches)) {
                $detectedVersion = $matches[1];
            }

            if ($detectedVersion) {
                $appVersion->update([
                    'store_detected_version' => $detectedVersion,
                    'store_synced_at' => now(),
                ]);

                app(AppVersionService::class)->clearCache('android');

                return [
                    'success' => true,
                    'platform' => 'android',
                    'detected_version' => $detectedVersion,
                    'message' => "تمت المزامنة بنجاح من Google Play (الإصدار الحي: {$detectedVersion}).",
                ];
            }

            // Fallback: If version is hidden behind dynamic Google Play client script, register check timestamp
            $appVersion->update([
                'store_synced_at' => now(),
            ]);

            return [
                'success' => true,
                'platform' => 'android',
                'detected_version' => $appVersion->store_detected_version ?: $appVersion->latest_version,
                'message' => 'تم الاتصال بـ Google Play بنجاح وتحديث تاريخ الفحص.',
            ];
        } catch (\Throwable $e) {
            Log::error('[StoreSyncService] Android Sync failed: ' . $e->getMessage());

            return [
                'success' => false,
                'platform' => 'android',
                'message' => 'حدث خطأ أثناء فحص متجر جوجل بلاي: ' . $e->getMessage(),
            ];
        }
    }
}

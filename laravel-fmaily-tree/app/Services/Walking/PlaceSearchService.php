<?php

declare(strict_types=1);

namespace App\Services\Walking;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class PlaceSearchService
{
    /**
     * Primary Nominatim OpenStreetMap search endpoint.
     */
    protected const NOMINATIM_URL = 'https://nominatim.openstreetmap.org/search';

    /**
     * Request timeout in seconds.
     */
    protected const TIMEOUT_SECONDS = 5;

    /**
     * Cache TTL in seconds (7 days).
     */
    protected const CACHE_TTL_SECONDS = 604800;

    /**
     * Built-in verified coordinates for major Saudi cities and famous landmarks.
     * Serves as an instant offline/fallback dictionary to guarantee high responsiveness.
     *
     * @var array<int, array{name: string, city: string, state: string, lat: float, lon: float, type: string, icon: string}>
     */
    protected const PRESET_SAUDI_PLACES = [
        ['name' => 'الرياض', 'city' => 'الرياض', 'state' => 'منطقة الرياض', 'lat' => 24.7136, 'lon' => 46.6753, 'type' => 'city', 'icon' => 'city'],
        ['name' => 'برج المملكة', 'city' => 'الرياض', 'state' => 'منطقة الرياض', 'lat' => 24.7114, 'lon' => 46.6744, 'type' => 'landmark', 'icon' => 'landmark'],
        ['name' => 'برج الفيصلية', 'city' => 'الرياض', 'state' => 'منطقة الرياض', 'lat' => 24.6901, 'lon' => 46.6853, 'type' => 'landmark', 'icon' => 'landmark'],
        ['name' => 'قصر المصمك', 'city' => 'الرياض', 'state' => 'منطقة الرياض', 'lat' => 24.6312, 'lon' => 46.7133, 'type' => 'landmark', 'icon' => 'landmark'],
        ['name' => 'الدرعية التاريخية', 'city' => 'الدرعية', 'state' => 'منطقة الرياض', 'lat' => 24.7339, 'lon' => 46.5752, 'type' => 'landmark', 'icon' => 'landmark'],
        ['name' => 'جدة', 'city' => 'جدة', 'state' => 'منطقة مكة المكرمة', 'lat' => 21.5433, 'lon' => 39.1728, 'type' => 'city', 'icon' => 'city'],
        ['name' => 'كورنيش جدة الواجهة البحرية', 'city' => 'جدة', 'state' => 'منطقة مكة المكرمة', 'lat' => 21.5833, 'lon' => 39.1097, 'type' => 'landmark', 'icon' => 'landmark'],
        ['name' => 'نافورة الملك فهد', 'city' => 'جدة', 'state' => 'منطقة مكة المكرمة', 'lat' => 21.5161, 'lon' => 39.1444, 'type' => 'landmark', 'icon' => 'landmark'],
        ['name' => 'البلد جدة التاريخية', 'city' => 'جدة', 'state' => 'منطقة مكة المكرمة', 'lat' => 21.4858, 'lon' => 39.1866, 'type' => 'landmark', 'icon' => 'landmark'],
        ['name' => 'مكة المكرمة', 'city' => 'مكة المكرمة', 'state' => 'منطقة مكة المكرمة', 'lat' => 21.3891, 'lon' => 39.8579, 'type' => 'city', 'icon' => 'city'],
        ['name' => 'المسجد الحرام', 'city' => 'مكة المكرمة', 'state' => 'منطقة مكة المكرمة', 'lat' => 21.4225, 'lon' => 39.8262, 'type' => 'mosque', 'icon' => 'mosque'],
        ['name' => 'برج الساعة مكة', 'city' => 'مكة المكرمة', 'state' => 'منطقة مكة المكرمة', 'lat' => 21.4187, 'lon' => 39.8256, 'type' => 'landmark', 'icon' => 'landmark'],
        ['name' => 'جبل النور غار حراء', 'city' => 'مكة المكرمة', 'state' => 'منطقة مكة المكرمة', 'lat' => 21.4583, 'lon' => 39.8592, 'type' => 'landmark', 'icon' => 'landmark'],
        ['name' => 'المدينة المنورة', 'city' => 'المدينة المنورة', 'state' => 'منطقة المدينة المنورة', 'lat' => 24.5247, 'lon' => 39.5692, 'type' => 'city', 'icon' => 'city'],
        ['name' => 'المسجد النبوي الشريف', 'city' => 'المدينة المنورة', 'state' => 'منطقة المدينة المنورة', 'lat' => 24.4672, 'lon' => 39.6111, 'type' => 'mosque', 'icon' => 'mosque'],
        ['name' => 'مسجد قباء', 'city' => 'المدينة المنورة', 'state' => 'منطقة المدينة المنورة', 'lat' => 24.4393, 'lon' => 39.6173, 'type' => 'mosque', 'icon' => 'mosque'],
        ['name' => 'جبل أحد', 'city' => 'المدينة المنورة', 'state' => 'منطقة المدينة المنورة', 'lat' => 24.5034, 'lon' => 39.6125, 'type' => 'landmark', 'icon' => 'landmark'],
        ['name' => 'الطائف', 'city' => 'الطائف', 'state' => 'منطقة مكة المكرمة', 'lat' => 21.2854, 'lon' => 40.4244, 'type' => 'city', 'icon' => 'city'],
        ['name' => 'حديقة الردف', 'city' => 'الطائف', 'state' => 'منطقة مكة المكرمة', 'lat' => 21.2333, 'lon' => 40.4217, 'type' => 'landmark', 'icon' => 'landmark'],
        ['name' => 'الهدا الشفا', 'city' => 'الطائف', 'state' => 'منطقة مكة المكرمة', 'lat' => 21.3533, 'lon' => 40.2872, 'type' => 'landmark', 'icon' => 'landmark'],
        ['name' => 'الدمام', 'city' => 'الدمام', 'state' => 'المنطقة الشرقية', 'lat' => 26.4207, 'lon' => 50.0888, 'type' => 'city', 'icon' => 'city'],
        ['name' => 'كورنيش الدمام', 'city' => 'الدمام', 'state' => 'المنطقة الشرقية', 'lat' => 26.4522, 'lon' => 50.1172, 'type' => 'landmark', 'icon' => 'landmark'],
        ['name' => 'الخبر', 'city' => 'الخبر', 'state' => 'المنطقة الشرقية', 'lat' => 26.2172, 'lon' => 50.1971, 'type' => 'city', 'icon' => 'city'],
        ['name' => 'كورنيش الخبر الواجهة البحرية', 'city' => 'الخبر', 'state' => 'المنطقة الشرقية', 'lat' => 26.2847, 'lon' => 50.2192, 'type' => 'landmark', 'icon' => 'landmark'],
        ['name' => 'جسر الملك فهد', 'city' => 'الخبر', 'state' => 'المنطقة الشرقية', 'lat' => 26.1833, 'lon' => 50.3167, 'type' => 'landmark', 'icon' => 'landmark'],
        ['name' => 'الظهران', 'city' => 'الظهران', 'state' => 'المنطقة الشرقية', 'lat' => 26.2361, 'lon' => 50.0393, 'type' => 'city', 'icon' => 'city'],
        ['name' => 'مركز الملك عبد العزيز الثقافي العالمي (إثراء)', 'city' => 'الظهران', 'state' => 'المنطقة الشرقية', 'lat' => 26.3061, 'lon' => 50.1233, 'type' => 'landmark', 'icon' => 'landmark'],
        ['name' => 'الأحساء الهفوف', 'city' => 'الأحساء', 'state' => 'المنطقة الشرقية', 'lat' => 25.3800, 'lon' => 49.5855, 'type' => 'city', 'icon' => 'city'],
        ['name' => 'جبل القارة الأحساء', 'city' => 'الأحساء', 'state' => 'المنطقة الشرقية', 'lat' => 25.4131, 'lon' => 49.6922, 'type' => 'landmark', 'icon' => 'landmark'],
        ['name' => 'سوق القيصرية التاريخي', 'city' => 'الهفوف', 'state' => 'المنطقة الشرقية', 'lat' => 25.3742, 'lon' => 49.5878, 'type' => 'landmark', 'icon' => 'landmark'],
        ['name' => 'بريدة', 'city' => 'بريدة', 'state' => 'منطقة القصيم', 'lat' => 26.3260, 'lon' => 43.9750, 'type' => 'city', 'icon' => 'city'],
        ['name' => 'عنيزة', 'city' => 'عنيزة', 'state' => 'منطقة القصيم', 'lat' => 26.0843, 'lon' => 43.9934, 'type' => 'city', 'icon' => 'city'],
        ['name' => 'حائل', 'city' => 'حائل', 'state' => 'منطقة حائل', 'lat' => 27.5219, 'lon' => 41.6961, 'type' => 'city', 'icon' => 'city'],
        ['name' => 'قلعة عيرف التاريخية', 'city' => 'حائل', 'state' => 'منطقة حائل', 'lat' => 27.5147, 'lon' => 41.6931, 'type' => 'landmark', 'icon' => 'landmark'],
        ['name' => 'العلا', 'city' => 'العلا', 'state' => 'منطقة المدينة المنورة', 'lat' => 26.6167, 'lon' => 37.9167, 'type' => 'city', 'icon' => 'city'],
        ['name' => 'مدائن صالح الحجر', 'city' => 'العلا', 'state' => 'منطقة المدينة المنورة', 'lat' => 26.7917, 'lon' => 37.9528, 'type' => 'landmark', 'icon' => 'landmark'],
        ['name' => 'جبل الفيل العلا', 'city' => 'العلا', 'state' => 'منطقة المدينة المنورة', 'lat' => 26.6908, 'lon' => 37.9881, 'type' => 'landmark', 'icon' => 'landmark'],
        ['name' => 'تبوك', 'city' => 'تبوك', 'state' => 'منطقة تبوك', 'lat' => 28.3835, 'lon' => 36.5662, 'type' => 'city', 'icon' => 'city'],
        ['name' => 'نيوم', 'city' => 'نيوم', 'state' => 'منطقة تبوك', 'lat' => 28.0042, 'lon' => 35.2978, 'type' => 'city', 'icon' => 'city'],
        ['name' => 'أبها', 'city' => 'أبها', 'state' => 'منطقة عسير', 'lat' => 18.2164, 'lon' => 42.5053, 'type' => 'city', 'icon' => 'city'],
        ['name' => 'خميس مشيط', 'city' => 'خميس مشيط', 'state' => 'منطقة عسير', 'lat' => 18.3000, 'lon' => 42.7333, 'type' => 'city', 'icon' => 'city'],
        ['name' => 'رجال ألمع', 'city' => 'رجال ألمع', 'state' => 'منطقة عسير', 'lat' => 18.2167, 'lon' => 42.1833, 'type' => 'landmark', 'icon' => 'landmark'],
        ['name' => 'جازان', 'city' => 'جازان', 'state' => 'منطقة جازان', 'lat' => 16.8892, 'lon' => 42.5511, 'type' => 'city', 'icon' => 'city'],
        ['name' => 'فرسان', 'city' => 'جزر فرسان', 'state' => 'منطقة جازان', 'lat' => 16.7028, 'lon' => 42.1186, 'type' => 'landmark', 'icon' => 'landmark'],
        ['name' => 'نجران', 'city' => 'نجران', 'state' => 'منطقة نجران', 'lat' => 17.4933, 'lon' => 44.1277, 'type' => 'city', 'icon' => 'city'],
        ['name' => 'الأخدود التاريخية', 'city' => 'نجران', 'state' => 'منطقة نجران', 'lat' => 17.4719, 'lon' => 44.2044, 'type' => 'landmark', 'icon' => 'landmark'],
        ['name' => 'ينبع', 'city' => 'ينبع', 'state' => 'منطقة المدينة المنورة', 'lat' => 24.0891, 'lon' => 38.0637, 'type' => 'city', 'icon' => 'city'],
        ['name' => 'ينبع البحر التاريخية', 'city' => 'ينبع', 'state' => 'منطقة المدينة المنورة', 'lat' => 24.0886, 'lon' => 38.0583, 'type' => 'landmark', 'icon' => 'landmark'],
        ['name' => 'سكاكا', 'city' => 'سكاكا', 'state' => 'منطقة الجوف', 'lat' => 29.9697, 'lon' => 40.2064, 'type' => 'city', 'icon' => 'city'],
        ['name' => 'دومة الجندل قلعة مارد', 'city' => 'دومة الجندل', 'state' => 'منطقة الجوف', 'lat' => 29.8117, 'lon' => 39.8683, 'type' => 'landmark', 'icon' => 'landmark'],
        ['name' => 'عرعر', 'city' => 'عرعر', 'state' => 'منطقة الحدود الشمالية', 'lat' => 30.9753, 'lon' => 41.0381, 'type' => 'city', 'icon' => 'city'],
        ['name' => 'حفر الباطن', 'city' => 'حفر الباطن', 'state' => 'المنطقة الشرقية', 'lat' => 28.4328, 'lon' => 45.9708, 'type' => 'city', 'icon' => 'city'],
        ['name' => 'الخرج', 'city' => 'الخرج', 'state' => 'منطقة الرياض', 'lat' => 24.1554, 'lon' => 47.3120, 'type' => 'city', 'icon' => 'city'],
        ['name' => 'المجمعة', 'city' => 'المجمعة', 'state' => 'منطقة الرياض', 'lat' => 25.9042, 'lon' => 45.3400, 'type' => 'city', 'icon' => 'city'],
        ['name' => 'الجبيل', 'city' => 'الجبيل', 'state' => 'المنطقة الشرقية', 'lat' => 27.0046, 'lon' => 49.6606, 'type' => 'city', 'icon' => 'city'],
        ['name' => 'بيشة', 'city' => 'بيشة', 'state' => 'منطقة عسير', 'lat' => 20.0005, 'lon' => 42.6053, 'type' => 'city', 'icon' => 'city'],
        ['name' => 'وادي الدواسر', 'city' => 'وادي الدواسر', 'state' => 'منطقة الرياض', 'lat' => 20.4500, 'lon' => 44.8667, 'type' => 'city', 'icon' => 'city'],
    ];

    /**
     * Search for places by query string with intelligent fallback and caching.
     *
     * @return array<int, array{
     *     title: string,
     *     city: string,
     *     display_name: string,
     *     latitude: float,
     *     longitude: float,
     *     type: string,
     *     icon: string,
     *     source: string
     * }>
     */
    public function search(string $query, int $limit = 8): array
    {
        $normalized = trim($query);
        if (mb_strlen($normalized) < 2) {
            return [];
        }

        $cacheKey = 'places_search_v1_' . md5(mb_strtolower($normalized)) . "_{$limit}";

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($normalized, $limit) {
            $results = [];

            // 1. Check local preset Saudi places first for instant exact/prefix matches
            $localMatches = $this->searchLocalPresets($normalized, $limit);
            foreach ($localMatches as $match) {
                $results[] = $match;
            }

            // 2. Query Nominatim OpenStreetMap if more results are needed
            if (count($results) < $limit) {
                try {
                    $osmResults = $this->queryNominatim($normalized, $limit);
                    foreach ($osmResults as $item) {
                        // Deduplicate by close distance or matching title
                        if (! $this->isDuplicate($item, $results)) {
                            $results[] = $item;
                        }
                        if (count($results) >= $limit) {
                            break;
                        }
                    }
                } catch (Throwable $e) {
                    Log::warning('[PlaceSearchService] Nominatim query failed: ' . $e->getMessage(), [
                        'query' => $normalized,
                    ]);
                }
            }

            return array_slice($results, 0, $limit);
        });
    }

    /**
     * Search within preset verified Saudi locations.
     *
     * @return array<int, array{
     *     title: string,
     *     city: string,
     *     display_name: string,
     *     latitude: float,
     *     longitude: float,
     *     type: string,
     *     icon: string,
     *     source: string
     * }>
     */
    protected function searchLocalPresets(string $query, int $limit): array
    {
        $cleanedQuery = $this->normalizeArabic($query);
        $matches = [];

        foreach (self::PRESET_SAUDI_PLACES as $place) {
            $normName = $this->normalizeArabic($place['name']);
            $normCity = $this->normalizeArabic($place['city']);
            $normState = $this->normalizeArabic($place['state']);

            if (
                str_contains($normName, $cleanedQuery) ||
                str_contains($normCity, $cleanedQuery) ||
                str_contains($normState, $cleanedQuery)
            ) {
                $matches[] = [
                    'title'        => $place['name'],
                    'city'         => $place['city'],
                    'display_name' => "{$place['name']} - {$place['city']} ({$place['state']})",
                    'latitude'     => $place['lat'],
                    'longitude'    => $place['lon'],
                    'type'         => $place['type'],
                    'icon'         => $place['icon'],
                    'source'       => 'verified_preset',
                ];

                if (count($matches) >= $limit) {
                    break;
                }
            }
        }

        return $matches;
    }

    /**
     * Query Nominatim API with Saudi Arabia bias and fallback.
     *
     * @return array<int, array{
     *     title: string,
     *     city: string,
     *     display_name: string,
     *     latitude: float,
     *     longitude: float,
     *     type: string,
     *     icon: string,
     *     source: string
     * }>
     */
    protected function queryNominatim(string $query, int $limit): array
    {
        // First try restricted to Saudi Arabia
        $raw = $this->sendNominatimRequest($query, $limit, 'sa');

        // If no results returned from KSA, broaden to worldwide/GCC
        if (empty($raw)) {
            $raw = $this->sendNominatimRequest($query, $limit, null);
        }

        $formatted = [];
        foreach ($raw as $item) {
            $lat = (float) ($item['lat'] ?? 0);
            $lon = (float) ($item['lon'] ?? 0);

            if ($lat === 0.0 && $lon === 0.0) {
                continue;
            }

            $address = $item['address'] ?? [];
            $name = (string) ($item['name'] ?? '');
            if ($name === '') {
                $name = (string) ($address['road'] ?? ($address['suburb'] ?? ($address['neighbourhood'] ?? ($address['city'] ?? $item['display_name']))));
            }

            $city = (string) ($address['city'] ?? ($address['town'] ?? ($address['village'] ?? ($address['county'] ?? ($address['state'] ?? '')))));
            $state = (string) ($address['state'] ?? '');
            $type = (string) ($item['type'] ?? 'place');

            $icon = match ($type) {
                'mosque', 'place_of_worship' => 'mosque',
                'monument', 'memorial', 'castle', 'ruins', 'museum', 'attraction' => 'landmark',
                'city', 'town', 'administrative' => 'city',
                'residential', 'house', 'hotel', 'apartment' => 'home',
                default => 'landmark',
            };

            $formatted[] = [
                'title'        => $name,
                'city'         => $city ?: $name,
                'display_name' => $item['display_name'] ?? $name,
                'latitude'     => round($lat, 6),
                'longitude'    => round($lon, 6),
                'type'         => $type,
                'icon'         => $icon,
                'source'       => 'nominatim_osm',
            ];
        }

        return $formatted;
    }

    /**
     * Send HTTP request to Nominatim.
     */
    protected function sendNominatimRequest(string $query, int $limit, ?string $countryCode): array
    {
        $params = [
            'q'              => $query,
            'format'         => 'jsonv2',
            'addressdetails' => 1,
            'limit'          => $limit,
            'accept-language'=> 'ar,en',
        ];

        if ($countryCode) {
            $params['countrycodes'] = $countryCode;
        }

        $response = Http::timeout(self::TIMEOUT_SECONDS)
            ->withHeaders([
                'User-Agent' => 'AlsorayiaApp-PlaceSearch/1.0 (admin@alsorayia.com)',
            ])
            ->get(self::NOMINATIM_URL, $params);

        if (! $response->successful()) {
            return [];
        }

        $data = $response->json();
        return is_array($data) ? $data : [];
    }

    /**
     * Simple duplicate detection based on coordinate proximity.
     */
    protected function isDuplicate(array $candidate, array $existingList): bool
    {
        foreach ($existingList as $item) {
            $latDiff = abs($candidate['latitude'] - $item['latitude']);
            $lngDiff = abs($candidate['longitude'] - $item['longitude']);

            if ($latDiff < 0.005 && $lngDiff < 0.005) {
                return true;
            }

            if (mb_strtolower(trim($candidate['title'])) === mb_strtolower(trim($item['title']))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalize Arabic string (remove hamzas, tashkeel, normalize alef/taa marboota).
     */
    protected function normalizeArabic(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/[ًٌٍَُِّْ]/u', '', $text);
        $text = str_replace(['أ', 'إ', 'آ'], 'ا', $text);
        $text = str_replace('ة', 'ه', $text);
        $text = str_replace(['ى', 'ي'], 'ي', $text);

        return $text ?? '';
    }
}

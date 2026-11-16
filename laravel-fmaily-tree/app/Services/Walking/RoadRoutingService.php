<?php

declare(strict_types=1);

namespace App\Services\Walking;

use App\Models\WalkingTrek;
use App\Models\WalkingTrekWaypoint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class RoadRoutingService
{
    /**
     * Primary public OSRM routing endpoint for driving profile.
     */
    protected const OSRM_URL = 'https://router.project-osrm.org/route/v1/driving';

    /**
     * Google Directions API base URL.
     */
    protected const GOOGLE_DIRECTIONS_URL = 'https://maps.googleapis.com/maps/api/directions/json';

    /**
     * Request timeout in seconds.
     */
    protected const TIMEOUT_SECONDS = 7;

    /**
     * Calculate a real driving road route between two GPS coordinates.
     * Tries OSRM first (free, open, full KSA road coverage),
     * then Google Directions API (if API key is present),
     * and gracefully falls back to Haversine distance if network fails.
     *
     * @return array{
     *     success: bool,
     *     distance_km: float,
     *     duration_seconds: int,
     *     polyline: ?string,
     *     source: string,
     *     error: ?string
     * }
     */
    public function getDrivingRoute(float $originLat, float $originLng, float $destLat, float $destLng): array
    {
        // 1. Try OSRM Driving Engine (Primary)
        try {
            $osrmResult = $this->queryOsrm($originLat, $originLng, $destLat, $destLng);
            if ($osrmResult !== null) {
                return $osrmResult;
            }
        } catch (Throwable $e) {
            Log::warning('[RoadRoutingService] OSRM query failed, attempting fallback', [
                'origin' => "{$originLat},{$originLng}",
                'dest'   => "{$destLat},{$destLng}",
                'error'  => $e->getMessage(),
            ]);
        }

        // 2. Try Google Directions API (Fallback)
        $googleKey = config('services.google.maps_api_key')
            ?: env('GOOGLE_MAPS_DIRECTIONS_API_KEY')
            ?: env('GOOGLE_MAPS_API_KEY');

        if (! empty($googleKey)) {
            try {
                $googleResult = $this->queryGoogle($originLat, $originLng, $destLat, $destLng, (string) $googleKey);
                if ($googleResult !== null) {
                    return $googleResult;
                }
            } catch (Throwable $e) {
                Log::warning('[RoadRoutingService] Google Directions query failed', [
                    'origin' => "{$originLat},{$originLng}",
                    'dest'   => "{$destLat},{$destLng}",
                    'error'  => $e->getMessage(),
                ]);
            }
        }

        // 3. Graceful Fallback: Haversine straight line
        $haversineKm = WalkingTrek::calculateHaversineKm($originLat, $originLng, $destLat, $destLng);

        return [
            'success'          => false,
            'distance_km'      => $haversineKm,
            'duration_seconds' => 0,
            'polyline'         => null,
            'source'           => 'haversine_fallback',
            'error'            => 'Routing service unavailable. Using straight-line distance.',
        ];
    }

    /**
     * Query OSRM Driving API.
     */
    protected function queryOsrm(float $originLat, float $originLng, float $destLat, float $destLng): ?array
    {
        // OSRM expects coordinates as: {longitude},{latitude};{longitude},{latitude}
        $coordsString = "{$originLng},{$originLat};{$destLng},{$destLat}";
        $url = self::OSRM_URL . "/{$coordsString}?overview=full&geometries=polyline";

        $response = Http::timeout(self::TIMEOUT_SECONDS)
            ->withHeaders(['User-Agent' => 'AlsorayiaApp-TrekRouter/1.0'])
            ->get($url);

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();
        if (($data['code'] ?? null) !== 'Ok' || empty($data['routes'][0])) {
            return null;
        }

        $route = $data['routes'][0];
        $distanceMeters = (float) ($route['distance'] ?? 0);
        $durationSeconds = (int) round((float) ($route['duration'] ?? 0));
        $polyline = $route['geometry'] ?? null;

        if (empty($polyline)) {
            return null;
        }

        return [
            'success'          => true,
            'distance_km'      => round($distanceMeters / 1000.0, 2),
            'duration_seconds' => $durationSeconds,
            'polyline'         => (string) $polyline,
            'source'           => 'osrm',
            'error'            => null,
        ];
    }

    /**
     * Query Google Maps Directions API.
     */
    protected function queryGoogle(float $originLat, float $originLng, float $destLat, float $destLng, string $apiKey): ?array
    {
        $response = Http::timeout(self::TIMEOUT_SECONDS)
            ->get(self::GOOGLE_DIRECTIONS_URL, [
                'origin'      => "{$originLat},{$originLng}",
                'destination' => "{$destLat},{$destLng}",
                'mode'        => 'driving',
                'key'         => $apiKey,
            ]);

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();
        if (($data['status'] ?? null) !== 'OK' || empty($data['routes'][0])) {
            return null;
        }

        $route = $data['routes'][0];
        $leg = $route['legs'][0] ?? [];
        $distanceMeters = (float) ($leg['distance']['value'] ?? 0);
        $durationSeconds = (int) ($leg['duration']['value'] ?? 0);
        $polyline = $route['overview_polyline']['points'] ?? null;

        if (empty($polyline)) {
            return null;
        }

        return [
            'success'          => true,
            'distance_km'      => round($distanceMeters / 1000.0, 2),
            'duration_seconds' => $durationSeconds,
            'polyline'         => (string) $polyline,
            'source'           => 'google',
            'error'            => null,
        ];
    }

    /**
     * Bulk generate or regenerate real driving road routes for all waypoints in a trek.
     * Updates each waypoint's route_polyline, route_metadata, and distance_from_prev_km.
     *
     * @return array{
     *     total_waypoints: int,
     *     updated_routes: int,
     *     total_distance_km: float,
     *     failed_routes: int
     * }
     */
    public function generateRoutesForTrek(WalkingTrek $trek, bool $updateDistances = true): array
    {
        $waypoints = $trek->waypoints()
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->values();

        $updatedCount = 0;
        $failedCount = 0;

        foreach ($waypoints as $index => $wp) {
            $wp->sort_order = $index + 1;

            if ($index === 0) {
                // Starting point has 0 distance and no incoming route
                $wp->distance_from_prev_km = 0.0;
                $wp->route_polyline = null;
                $wp->route_metadata = [
                    'type' => 'start_point',
                    'note' => 'نقطة الانطلاق الرسمية',
                ];
                $wp->saveQuietly();
                continue;
            }

            $prevWp = $waypoints[$index - 1];

            $routeRes = $this->getDrivingRoute(
                (float) $prevWp->latitude,
                (float) $prevWp->longitude,
                (float) $wp->latitude,
                (float) $wp->longitude
            );

            if ($routeRes['success'] && ! empty($routeRes['polyline'])) {
                $wp->route_polyline = $routeRes['polyline'];
                $wp->route_metadata = [
                    'source'           => $routeRes['source'],
                    'duration_seconds' => $routeRes['duration_seconds'],
                    'real_road_km'     => $routeRes['distance_km'],
                    'generated_at'     => now()->toIso8601String(),
                ];

                if ($updateDistances) {
                    $wp->distance_from_prev_km = $routeRes['distance_km'];
                }

                $wp->saveQuietly();
                $updatedCount++;
            } else {
                $failedCount++;
                // Fallback: keep or calculate haversine
                if ((float) $wp->distance_from_prev_km <= 0.0) {
                    $wp->distance_from_prev_km = $routeRes['distance_km'];
                }
                $wp->route_metadata = [
                    'source'       => 'fallback',
                    'error'        => $routeRes['error'] ?? 'No road route available',
                    'generated_at' => now()->toIso8601String(),
                ];
                $wp->saveQuietly();
            }
        }

        // Recalculate cumulative distances for the entire trek
        $trek->recalculateDistance();
        $trek->refresh();

        return [
            'total_waypoints'   => $waypoints->count(),
            'updated_routes'    => $updatedCount,
            'total_distance_km' => (float) $trek->total_distance_km,
            'failed_routes'     => $failedCount,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Walking\RoadRoutingService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RoadRoutingServiceTest extends TestCase
{
    public function test_osrm_driving_route_success(): void
    {
        // Mock OSRM response
        Http::fake([
            'router.project-osrm.org/*' => Http::response([
                'code'   => 'Ok',
                'routes' => [
                    [
                        'geometry' => '_p~iF~ps|U_ulLnnqC_mqNvxq`@',
                        'distance' => 784500.0, // 784.5 km
                        'duration' => 28000,
                    ],
                ],
            ], 200),
        ]);

        $service = new RoadRoutingService();
        $res = $service->getDrivingRoute(24.7136, 46.6753, 21.2854, 40.4244);

        $this->assertTrue($res['success']);
        $this->assertSame(784.5, $res['distance_km']);
        $this->assertSame('_p~iF~ps|U_ulLnnqC_mqNvxq`@', $res['polyline']);
        $this->assertSame('osrm', $res['source']);
        $this->assertSame(28000, $res['duration_seconds']);
    }

    public function test_fallback_to_haversine_when_network_fails(): void
    {
        Http::fake([
            '*' => Http::response([], 500),
        ]);

        $service = new RoadRoutingService();
        $res = $service->getDrivingRoute(24.7136, 46.6753, 21.2854, 40.4244);

        $this->assertFalse($res['success']);
        $this->assertGreaterThan(700.0, $res['distance_km']);
        $this->assertNull($res['polyline']);
        $this->assertSame('haversine_fallback', $res['source']);
    }
}

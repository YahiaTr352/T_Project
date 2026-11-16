<?php

namespace Tests\Unit;

use App\Models\WalkingTrek;
use App\Services\Walking\WalkingLeaderboardEligibilityService;
use App\Services\Walking\WalkingTrekService;
use Illuminate\Support\Collection;
use Mockery;
use PHPUnit\Framework\TestCase;

class WalkingTrekProgressiveTest extends TestCase
{
    private function service(): WalkingTrekService
    {
        return new WalkingTrekService(Mockery::mock(WalkingLeaderboardEligibilityService::class));
    }

    /** Jeddah -> Riyadh (1000 km) -> Dammam (400 km) */
    private function waypoints(): Collection
    {
        $make = fn (int $id, string $title, float $lat, float $lng, float $dist, float $cum) => (object) [
            'id' => $id, 'sort_order' => $id, 'city_name' => $title, 'location_title' => $title,
            'location_description' => null, 'latitude' => $lat, 'longitude' => $lng,
            'distance_from_prev_km' => $dist, 'cumulative_km' => $cum, 'icon_type' => 'city',
            'route_polyline' => null, 'route_metadata' => null,
        ];

        return collect([
            $make(1, 'Jeddah', 21.5, 39.2, 0, 0),
            $make(2, 'Riyadh', 24.7, 46.7, 1000, 1000),
            $make(3, 'Dammam', 26.4, 50.1, 400, 1400),
        ]);
    }

    public function test_partial_distance_stops_pin_inside_first_segment(): void
    {
        $r = $this->service()->resolveCurrentSegmentAndPosition($this->waypoints(), 250.0, 1400.0);

        $this->assertSame(25.0, $r['current_segment']['segment_progress_percentage']);
        $this->assertEqualsWithDelta(21.5 + 0.25 * 3.2, $r['current_position']['latitude'], 0.0001);
        $this->assertSame('start_point', $r['decorated_waypoints'][0]['status']);
        $this->assertSame('current_target', $r['decorated_waypoints'][1]['status']);
        $this->assertSame('upcoming', $r['decorated_waypoints'][2]['status']);
    }

    public function test_riyadh_is_reached_only_after_exactly_1000_km(): void
    {
        $before = $this->service()->resolveCurrentSegmentAndPosition($this->waypoints(), 999.9, 1400.0);
        $this->assertSame('current_target', $before['decorated_waypoints'][1]['status']);

        $at = $this->service()->resolveCurrentSegmentAndPosition($this->waypoints(), 1000.0, 1400.0);
        $this->assertSame('completed', $at['decorated_waypoints'][1]['status']);
        $this->assertSame('current_target', $at['decorated_waypoints'][2]['status']);
        $this->assertEqualsWithDelta(24.7, $at['current_position']['latitude'], 0.0001);
    }

    public function test_last_waypoint_is_final_destination(): void
    {
        $r = $this->service()->resolveCurrentSegmentAndPosition($this->waypoints(), 1400.0, 1400.0);

        $this->assertSame('completed', $r['decorated_waypoints'][2]['status']);
        $this->assertEqualsWithDelta(26.4, $r['current_position']['latitude'], 0.0001);
    }

    public function test_haversine_jeddah_riyadh_is_realistic(): void
    {
        $km = WalkingTrek::calculateHaversineKm(21.5433, 39.1728, 24.7136, 46.6753);
        $this->assertEqualsWithDelta(845, $km, 20);
    }

    public function test_polyline_decoder_decodes_points_correctly(): void
    {
        // Encoded polyline for (38.5, -120.2), (40.7, -120.95), (43.252, -126.453)
        $encoded = '_p~iF~ps|U_ulLnnqC_mqNvxq`@';
        $points = WalkingTrekService::decodePolyline($encoded);

        $this->assertCount(3, $points);
        $this->assertEqualsWithDelta(38.5, $points[0][0], 0.001);
        $this->assertEqualsWithDelta(-120.2, $points[0][1], 0.001);
        $this->assertEqualsWithDelta(40.7, $points[1][0], 0.001);
        $this->assertEqualsWithDelta(-120.95, $points[1][1], 0.001);
        $this->assertEqualsWithDelta(43.252, $points[2][0], 0.001);
        $this->assertEqualsWithDelta(-126.453, $points[2][1], 0.001);
    }

    public function test_polyline_interpolation_finds_exact_intermediate_point(): void
    {
        $encoded = '_p~iF~ps|U_ulLnnqC_mqNvxq`@';

        // At ratio 0: should be start point
        $start = WalkingTrekService::interpolateAlongPolyline($encoded, 0.0);
        $this->assertNotNull($start);
        $this->assertEqualsWithDelta(38.5, $start['lat'], 0.001);
        $this->assertEqualsWithDelta(-120.2, $start['lng'], 0.001);

        // At ratio 1: should be end point
        $end = WalkingTrekService::interpolateAlongPolyline($encoded, 1.0);
        $this->assertNotNull($end);
        $this->assertEqualsWithDelta(43.252, $end['lat'], 0.001);
        $this->assertEqualsWithDelta(-126.453, $end['lng'], 0.001);

        // At ratio 0.5: intermediate point between start and end
        $mid = WalkingTrekService::interpolateAlongPolyline($encoded, 0.5);
        $this->assertNotNull($mid);
        $this->assertGreaterThan(38.5, $mid['lat']);
        $this->assertLessThan(43.252, $mid['lat']);
    }
}

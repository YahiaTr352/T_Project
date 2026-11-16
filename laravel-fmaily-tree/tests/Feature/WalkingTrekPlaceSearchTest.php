<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\WalkingTrek;
use App\Services\Walking\PlaceSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WalkingTrekPlaceSearchTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['walking-program.view', 'walking-program.create', 'walking-program.update', 'walking-program.delete'] as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        $this->adminUser = User::factory()->create();
        $this->adminUser->givePermissionTo([
            'walking-program.view',
            'walking-program.create',
            'walking-program.update',
            'walking-program.delete',
        ]);
    }

    public function test_place_search_requires_authentication(): void
    {
        $response = $this->getJson(route('dashboard.walking.treks.search-places', ['query' => 'الرياض']));
        $response->assertStatus(401);
    }

    public function test_place_search_requires_view_permission(): void
    {
        $userWithoutPermission = User::factory()->create();

        $response = $this->actingAs($userWithoutPermission)
            ->getJson(route('dashboard.walking.treks.search-places', ['query' => 'الرياض']));

        $response->assertStatus(403);
    }

    public function test_place_search_returns_preset_saudi_places(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson(route('dashboard.walking.treks.search-places', ['query' => 'الرياض']));

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);

        // First result should be Riyadh
        $first = $data[0];
        $this->assertStringContainsString('الرياض', $first['title']);
        $this->assertEqualsWithDelta(24.7136, (float) $first['latitude'], 0.1);
        $this->assertEqualsWithDelta(46.6753, (float) $first['longitude'], 0.1);
        $this->assertNotEmpty($first['icon']);
    }

    public function test_place_search_handles_short_query_cleanly(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson(route('dashboard.walking.treks.search-places', ['query' => 'أ']));

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('count', 0)
            ->assertJsonPath('data', []);
    }

    public function test_place_search_service_integrates_with_nominatim(): void
    {
        Http::fake([
            'https://nominatim.openstreetmap.org/search*' => Http::response([
                [
                    'osm_id'       => 123456,
                    'lat'          => '21.543333',
                    'lon'          => '39.172778',
                    'display_name' => 'جدة, منطقة مكة المكرمة, المملكة العربية السعودية',
                    'name'         => 'جدة',
                    'type'         => 'city',
                    'address'      => [
                        'city'    => 'جدة',
                        'state'   => 'منطقة مكة المكرمة',
                        'country' => 'المملكة العربية السعودية',
                    ],
                ],
            ], 200),
        ]);

        $service = new PlaceSearchService();
        $results = $service->search('جدة');

        $this->assertNotEmpty($results);
        $this->assertSame('جدة', $results[0]['city']);
        $this->assertEqualsWithDelta(21.54, (float) $results[0]['latitude'], 0.1);
    }

    public function test_storing_trek_with_initial_start_waypoint_creates_first_waypoint(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('dashboard.walking.treks.store'), [
            'title'                 => 'طواف المنطقة الغربية التجريبي',
            'description'           => 'رحلة تجريبية تنطلق من جدة',
            'target_type'           => 'family_community',
            'status'                => 'active',
            'is_loop'               => 1,
            'start_location_title'  => 'واجهة جدة البحرية',
            'start_city_name'       => 'جدة',
            'start_latitude'        => 21.5833,
            'start_longitude'       => 39.1097,
            'start_icon_type'       => 'landmark',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $trek = WalkingTrek::where('title', 'طواف المنطقة الغربية التجريبي')->first();
        $this->assertNotNull($trek);

        // Verify initial waypoint was created
        $this->assertSame(1, $trek->waypoints()->count());
        $waypoint = $trek->waypoints()->first();
        $this->assertSame(1, (int) $waypoint->sort_order);
        $this->assertSame('واجهة جدة البحرية', $waypoint->location_title);
        $this->assertSame('جدة', $waypoint->city_name);
        $this->assertEqualsWithDelta(21.5833, (float) $waypoint->latitude, 0.001);
        $this->assertEqualsWithDelta(39.1097, (float) $waypoint->longitude, 0.001);
        $this->assertSame(0.0, (float) $waypoint->distance_from_prev_km);
    }

    public function test_treks_index_search_filter_matches_trek_title(): void
    {
        WalkingTrek::create([
            'title'       => 'طواف مسار الصمان الخاص',
            'target_type' => 'family_community',
            'status'      => 'inactive',
            'is_loop'     => true,
        ]);

        WalkingTrek::create([
            'title'       => 'طواف وادي حنيفة التاريخي',
            'target_type' => 'family_community',
            'status'      => 'inactive',
            'is_loop'     => true,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('dashboard.walking.treks.index', ['search' => 'الصمان']));

        $response->assertStatus(200);
        $response->assertSee('طواف مسار الصمان الخاص');
        $response->assertDontSee('طواف وادي حنيفة التاريخي');
    }
}

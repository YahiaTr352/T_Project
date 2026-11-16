<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WalkingClub;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WalkingClubAdminManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions
        foreach (['walking-program.view', 'walking-program.create', 'walking-program.update', 'walking-program.delete'] as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        $this->adminUser = User::factory()->create([
            'email' => 'admin@alsorayia.test',
        ]);
        $this->adminUser->givePermissionTo([
            'walking-program.view',
            'walking-program.create',
            'walking-program.update',
            'walking-program.delete',
        ]);
    }

    public function test_admin_can_store_new_walking_club(): void
    {
        $payload = [
            'name'                   => 'حمادة العب',
            'short_name'             => 'حمادة',
            'sub_label'              => 'يومياً',
            'min_steps'              => 5000,
            'max_steps'              => 9999,
            'calculation_start_date' => Carbon::yesterday()->toDateString(),
            'icon'                   => '🔥',
            'badge_color'            => '#FF5722',
            'badge_dark'             => '#E64A19',
            'accent_light'           => '#FFCCBC',
            'accent_dark'            => '#BF360C',
            'is_active'              => true,
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('dashboard.walking.clubs.store'), $payload);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('club.name', 'حمادة العب')
            ->assertJsonPath('club.min_steps', 5000)
            ->assertJsonPath('club.calculation_start_date', Carbon::yesterday()->toDateString());

        $this->assertDatabaseHas('walking_clubs', [
            'name'      => 'حمادة العب',
            'min_steps' => 5000,
        ]);
    }

    public function test_admin_can_update_club_and_change_start_date(): void
    {
        $club = WalkingClub::firstOrCreate(
            ['slug' => 'club_5k'],
            [
                'name'                   => 'نادي الـ 5,000 خطوة يومياً',
                'short_name'             => 'نادي 5k',
                'sub_label'              => 'يومياً',
                'min_steps'              => 5000,
                'max_steps'              => 9999,
                'calculation_start_date' => null,
                'icon'                   => '🥉',
                'badge_color'            => '#D97706',
                'badge_dark'             => '#F59E0B',
                'accent_light'           => '#FEF3C7',
                'accent_dark'            => '#451A03',
                'display_order'          => 1,
                'is_active'              => true,
            ]
        );

        $newStartDate = Carbon::today()->subDays(5)->toDateString();

        $response = $this->actingAs($this->adminUser)
            ->putJson(route('dashboard.walking.clubs.update', $club->id), [
                'name'                   => 'حمادة العب المعدل',
                'short_name'             => 'حمادة 2',
                'sub_label'              => 'يومياً',
                'min_steps'              => 6000,
                'max_steps'              => 11999,
                'calculation_start_date' => $newStartDate,
                'icon'                   => '👑',
                'badge_color'            => '#4CAF50',
                'is_active'              => true,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('club.name', 'حمادة العب المعدل')
            ->assertJsonPath('club.min_steps', 6000)
            ->assertJsonPath('club.calculation_start_date', $newStartDate);

        $club->refresh();
        $this->assertEquals('حمادة العب المعدل', $club->name);
        $this->assertEquals(6000, $club->min_steps);
        $this->assertEquals($newStartDate, $club->calculation_start_date->format('Y-m-d'));
    }

    public function test_admin_can_toggle_club_status(): void
    {
        $club = WalkingClub::create([
            'slug'                   => 'club_toggle',
            'name'                   => 'نادي تجريبي',
            'short_name'             => 'تجريبي',
            'min_steps'              => 3000,
            'is_active'              => true,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('dashboard.walking.clubs.toggle-status', $club->id));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('is_active', false);

        $this->assertFalse($club->fresh()->is_active);
    }

    public function test_admin_can_delete_club_via_ajax(): void
    {
        $club = WalkingClub::create([
            'slug'        => 'club_to_delete',
            'name'        => 'نادي سيتم حذفه',
            'short_name'  => 'حذف',
            'min_steps'   => 4000,
            'is_active'   => true,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson(route('dashboard.walking.clubs.destroy', $club->id));

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('walking_clubs', [
            'id' => $club->id,
        ]);
    }

    public function test_cannot_delete_last_remaining_club(): void
    {
        WalkingClub::where('id', '>', 0)->delete();

        $singleClub = WalkingClub::create([
            'slug'        => 'only_club',
            'name'        => 'النادي الوحيد',
            'short_name'  => 'وحيد',
            'min_steps'   => 5000,
            'is_active'   => true,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->deleteJson(route('dashboard.walking.clubs.destroy', $singleClub->id));

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('walking_clubs', [
            'id' => $singleClub->id,
        ]);
    }
}

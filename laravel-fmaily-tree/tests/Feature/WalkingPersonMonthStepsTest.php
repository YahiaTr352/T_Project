<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\User;
use App\Models\WalkingDailyReset;
use App\Models\WalkingRecord;
use App\Services\AI\EmbeddingsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WalkingPersonMonthStepsTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Person $person;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        config(['site.password_protection_enabled' => false]);

        $this->mock(EmbeddingsService::class, function ($mock) {
            $mock->shouldIgnoreMissing();
        });

        foreach (['walking-program.view', 'walking-program.create', 'walking-program.update', 'walking-program.delete'] as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        $this->adminUser = User::factory()->create([
            'email' => 'admin@family.test',
        ]);
        $this->adminUser->givePermissionTo([
            'walking-program.view',
            'walking-program.create',
            'walking-program.update',
            'walking-program.delete',
        ]);

        $this->person = Person::factory()->create([
            'first_name' => 'خالد',
            'last_name'  => 'إبراهيم',
            'gender'     => 'male',
        ]);
    }

    public function test_can_fetch_person_monthly_steps_breakdown(): void
    {
        // Create records for specific days in 2026-03
        WalkingRecord::create([
            'person_id'        => $this->person->id,
            'date'             => '2026-03-30',
            'steps'            => 15000,
            'distance_km'      => 11.43,
            'duration_minutes' => 150,
            'calories'         => 600,
            'source'           => 'apple_health',
        ]);

        WalkingRecord::create([
            'person_id'        => $this->person->id,
            'date'             => '2026-03-31',
            'steps'            => 25000,
            'distance_km'      => 19.05,
            'duration_minutes' => 250,
            'calories'         => 1000,
            'source'           => 'manual',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson(route('dashboard.walking.person-month-steps', [
                'person_id' => $this->person->id,
                'month'     => '2026-03',
            ]));

        $response->assertOk()
            ->assertJson([
                'success'           => true,
                'month'             => '2026-03',
                'days_in_month'     => 31,
                'month_total_steps' => 40000,
                'active_days'       => 2,
            ]);

        $days = $response->json('days');
        $this->assertCount(31, $days);

        // Day 30 check
        $day30 = collect($days)->firstWhere('day', 30);
        $this->assertNotNull($day30);
        $this->assertSame(15000, $day30['steps']);
        $this->assertTrue($day30['is_goal']);

        // Day 31 check
        $day31 = collect($days)->firstWhere('day', 31);
        $this->assertNotNull($day31);
        $this->assertSame(25000, $day31['steps']);
        $this->assertTrue($day31['is_goal']);

        // Empty Day 1 check
        $day1 = collect($days)->firstWhere('day', 1);
        $this->assertNotNull($day1);
        $this->assertSame(0, $day1['steps']);
        $this->assertFalse($day1['is_goal']);
    }

    public function test_can_update_day_steps_and_auto_calculates_distance_and_calories(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson(route('dashboard.walking.update-person-day-steps'), [
                'person_id' => $this->person->id,
                'date'      => '2026-03-31',
                'steps'     => 30000,
            ]);

        $response->assertOk()
            ->assertJson([
                'success'           => true,
                'steps'             => 30000,
                'formatted_steps'   => '30,000',
                'distance_km'       => 22.86,
                'calories'          => 1200,
                'month_total_steps' => 30000,
                'is_goal'           => true,
            ]);

        $record = WalkingRecord::where('person_id', $this->person->id)
            ->whereDate('date', '2026-03-31')
            ->first();

        $this->assertNotNull($record);
        $this->assertSame(30000, (int) $record->steps);
        $this->assertEquals(22.86, (float) $record->distance_km);
        $this->assertSame(1200, (int) $record->calories);
    }

    public function test_can_set_steps_to_one_step_and_triggers_daily_reset_on_reduction(): void
    {
        // Existing record with 20,000 steps
        WalkingRecord::create([
            'person_id'        => $this->person->id,
            'date'             => '2026-03-30',
            'steps'            => 20000,
            'distance_km'      => 15.24,
            'duration_minutes' => 200,
            'calories'         => 800,
        ]);

        // Reduce steps down to 1 step
        $response = $this->actingAs($this->adminUser)
            ->postJson(route('dashboard.walking.update-person-day-steps'), [
                'person_id' => $this->person->id,
                'date'      => '2026-03-30',
                'steps'     => 1,
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'steps'   => 1,
                'is_goal' => false,
            ]);

        $record = WalkingRecord::where('person_id', $this->person->id)
            ->whereDate('date', '2026-03-30')
            ->first();

        $this->assertNotNull($record);
        $this->assertSame(1, (int) $record->steps);

        // Verify WalkingDailyReset was created for this reduction
        $reset = WalkingDailyReset::where('person_id', $this->person->id)
            ->whereDate('date', '2026-03-30')
            ->first();

        $this->assertNotNull($reset);
        $this->assertSame('person', $reset->scope);
        $this->assertSame('admin_Correction', $reset->source);
    }

    public function test_validation_prevents_negative_or_invalid_steps(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson(route('dashboard.walking.update-person-day-steps'), [
                'person_id' => $this->person->id,
                'date'      => '2026-03-31',
                'steps'     => -50,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['steps']);
    }
}

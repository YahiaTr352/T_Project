<?php

namespace Tests\Feature;

use App\Models\WalkingTrek;
use App\Services\Walking\WalkingTrekService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalkingTrekSchedulingAndActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduling_trek_sets_scheduled_status_and_timestamp(): void
    {
        $trek = WalkingTrek::create([
            'title' => 'رحلة تجريبية للجدولة',
            'status' => 'inactive',
            'is_loop' => false,
            'total_distance_km' => 100,
            'scope' => 'community',
        ]);

        $future = Carbon::now('Asia/Riyadh')->addHours(5);
        $trek->scheduleActivation($future, true);

        $trek->refresh();
        $this->assertSame('scheduled', $trek->status);
        $this->assertTrue($trek->isScheduled());
        $this->assertFalse($trek->isActive());
        $this->assertNotNull($trek->scheduled_at);
        $this->assertTrue((bool) $trek->auto_start_counting);
    }

    public function test_activating_trek_deactivates_other_community_treks(): void
    {
        $trek1 = WalkingTrek::create([
            'title' => 'مسار أول',
            'status' => 'active',
            'is_loop' => false,
            'total_distance_km' => 200,
            'target_type' => 'family_community',
        ]);

        $trek2 = WalkingTrek::create([
            'title' => 'مسار ثانٍ',
            'status' => 'inactive',
            'is_loop' => false,
            'total_distance_km' => 300,
            'target_type' => 'family_community',
        ]);

        $this->assertTrue($trek1->isActive());
        $this->assertFalse($trek2->isActive());

        // Now activate trek2
        $trek2->activateNow();

        $trek1->refresh();
        $trek2->refresh();

        $this->assertTrue($trek2->isActive());
        $this->assertSame('inactive', $trek1->status);
        $this->assertFalse($trek1->isActive());
    }

    public function test_deactivate_method_marks_trek_inactive(): void
    {
        $trek = WalkingTrek::create([
            'title' => 'مسار للتجربة',
            'status' => 'active',
            'is_loop' => false,
            'total_distance_km' => 150,
            'scope' => 'community',
        ]);

        $trek->deactivate();
        $trek->refresh();

        $this->assertSame('inactive', $trek->status);
        $this->assertTrue($trek->isInactive());
        $this->assertFalse($trek->isActive());
    }

    public function test_cancel_schedule_marks_trek_inactive_and_clears_scheduled_at(): void
    {
        $trek = WalkingTrek::create([
            'title' => 'مسار مجدول للإلغاء',
            'status' => 'scheduled',
            'scheduled_at' => Carbon::now('Asia/Riyadh')->addDay(),
            'is_loop' => false,
            'total_distance_km' => 150,
            'scope' => 'community',
        ]);

        $trek->cancelSchedule();
        $trek->refresh();

        $this->assertSame('inactive', $trek->status);
        $this->assertNull($trek->scheduled_at);
    }

    public function test_promote_ready_scheduled_treks_activates_due_treks(): void
    {
        // Scheduled in the past by 2 minutes
        $dueTrek = WalkingTrek::create([
            'title' => 'مسار حان موعده',
            'status' => 'scheduled',
            'scheduled_at' => Carbon::now('Asia/Riyadh')->subMinutes(2),
            'auto_start_counting' => true,
            'is_loop' => false,
            'total_distance_km' => 500,
            'scope' => 'community',
        ]);

        // Scheduled in the future by 2 hours
        $futureTrek = WalkingTrek::create([
            'title' => 'مسار مستقبلي',
            'status' => 'scheduled',
            'scheduled_at' => Carbon::now('Asia/Riyadh')->addHours(2),
            'auto_start_counting' => true,
            'is_loop' => false,
            'total_distance_km' => 600,
            'scope' => 'community',
        ]);

        /** @var WalkingTrekService $service */
        $service = app(WalkingTrekService::class);
        $promotedCount = $service->promoteReadyScheduledTreks();

        $this->assertGreaterThanOrEqual(1, $promotedCount);

        $dueTrek->refresh();
        $futureTrek->refresh();

        $this->assertSame('active', $dueTrek->status);
        $this->assertTrue($dueTrek->isActive());
        $this->assertNull($dueTrek->scheduled_at);

        $this->assertSame('scheduled', $futureTrek->status);
        $this->assertTrue($futureTrek->isScheduled());
    }

    public function test_artisan_command_activates_scheduled_treks(): void
    {
        $dueTrek = WalkingTrek::create([
            'title' => 'مسار للأمر البرمجي',
            'status' => 'scheduled',
            'scheduled_at' => Carbon::now('Asia/Riyadh')->subMinutes(5),
            'auto_start_counting' => true,
            'is_loop' => false,
            'total_distance_km' => 250,
            'target_type' => 'family_community',
        ]);

        $this->artisan('walking:activate-scheduled-treks')
            ->expectsOutputToContain('تم تفعيل')
            ->assertExitCode(0);

        $dueTrek->refresh();
        $this->assertSame('active', $dueTrek->status);
    }

    public function test_community_trek_top_contributors_strictly_segregated_by_viewer_gender(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        $today = Carbon::today()->toDateString();

        $trek = WalkingTrek::create([
            'title' => 'طواف المملكة الفعلي',
            'status' => 'active',
            'is_loop' => false,
            'total_distance_km' => 500,
            'target_type' => 'family_community',
        ]);

        $maleMember = \App\Models\Person::create([
            'first_name' => 'سعد',
            'last_name' => 'السريّع',
            'gender' => 'male',
            'from_outside_the_family' => false,
        ]);

        $femaleMember = \App\Models\Person::create([
            'first_name' => 'نورة',
            'last_name' => 'السريّع',
            'gender' => 'female',
            'from_outside_the_family' => false,
        ]);

        $maleViewer = \App\Models\Person::create([
            'first_name' => 'محمد',
            'last_name' => 'السريّع',
            'gender' => 'male',
            'from_outside_the_family' => false,
        ]);

        $femaleViewer = \App\Models\Person::create([
            'first_name' => 'سارة',
            'last_name' => 'السريّع',
            'gender' => 'female',
            'from_outside_the_family' => false,
        ]);

        \App\Models\WalkingRecord::create([
            'person_id' => $maleMember->id,
            'steps' => 8000,
            'distance_km' => 5.6,
            'date' => $today,
        ]);

        \App\Models\WalkingRecord::create([
            'person_id' => $femaleMember->id,
            'steps' => 12000,
            'distance_km' => 8.4,
            'date' => $today,
        ]);

        app(\App\Services\Walking\WalkingLeaderboardEligibilityService::class)->clearCache();

        /** @var WalkingTrekService $service */
        $service = app(WalkingTrekService::class);

        // Male viewer: only male contributors in top_contributors
        $maleResult = $service->getActiveTrekData($maleViewer->id);
        $this->assertNotNull($maleResult);
        $maleContributorIds = collect($maleResult['top_contributors'])->pluck('person_id')->toArray();
        $this->assertContains($maleMember->id, $maleContributorIds);
        $this->assertNotContains($femaleMember->id, $maleContributorIds);

        // Female viewer: only female contributors in top_contributors
        $femaleResult = $service->getActiveTrekData($femaleViewer->id);
        $this->assertNotNull($femaleResult);
        $femaleContributorIds = collect($femaleResult['top_contributors'])->pluck('person_id')->toArray();
        $this->assertContains($femaleMember->id, $femaleContributorIds);
        $this->assertNotContains($maleMember->id, $femaleContributorIds);

        // Collective family distance includes both (5.6 + 8.4 = 14.0 km)
        $this->assertEquals(14.0, $maleResult['trek']['total_walked_km']);
        $this->assertEquals(14.0, $femaleResult['trek']['total_walked_km']);
    }
}

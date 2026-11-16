<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Person;
use App\Models\WalkingGroup;
use App\Models\WalkingGroupMember;
use App\Models\WalkingRecord;
use App\Services\SiteMobileAccessToken;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WalkingGroupLeaderboardMixedGenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        config(['site.password_protection_enabled' => false]);
        $this->withoutMiddleware(VerifyCsrfToken::class);
        app(\App\Services\Walking\WalkingLeaderboardEligibilityService::class)->clearCache();
    }

    protected function identityHeaders(Person $person): array
    {
        $issued = SiteMobileAccessToken::issue(60, [
            'person_id'   => $person->id,
            'auth_method' => 'face',
        ]);

        return [
            'X-Site-Access-Token' => $issued['token'],
            'Accept'              => 'application/json',
        ];
    }

    public function test_male_viewer_sees_both_male_and_female_members_with_steps_in_group_leaderboard(): void
    {
        $today = Carbon::today()->toDateString();

        $maleViewer = Person::create([
            'first_name' => 'خالد',
            'last_name'  => 'السريّع',
            'gender'     => 'male',
        ]);

        $femaleMember = Person::create([
            'first_name' => 'سارة',
            'last_name'  => 'السريّع',
            'gender'     => 'female',
        ]);

        $maleMember = Person::create([
            'first_name' => 'فهد',
            'last_name'  => 'السريّع',
            'gender'     => 'male',
        ]);

        $group = WalkingGroup::create([
            'name'                 => 'تحدي الأسرة المشترك',
            'created_by_person_id' => $maleViewer->id,
            'is_public'            => false,
            'status'               => 'active',
            'start_date'           => Carbon::today()->subDays(3)->toDateString(),
            'end_date'             => Carbon::today()->addDays(3)->toDateString(),
        ]);

        WalkingGroupMember::create([
            'walking_group_id' => $group->id,
            'person_id'        => $maleViewer->id,
            'role'             => 'admin',
            'status'           => 'accepted',
            'joined_at'        => Carbon::today()->subDays(3),
        ]);

        WalkingGroupMember::create([
            'walking_group_id' => $group->id,
            'person_id'        => $femaleMember->id,
            'role'             => 'member',
            'status'           => 'accepted',
            'joined_at'        => Carbon::today()->subDays(2),
        ]);

        WalkingGroupMember::create([
            'walking_group_id' => $group->id,
            'person_id'        => $maleMember->id,
            'role'             => 'member',
            'status'           => 'accepted',
            'joined_at'        => Carbon::today()->subDays(1),
        ]);

        // Female member walked 12,000 steps today
        WalkingRecord::create([
            'person_id' => $femaleMember->id,
            'date'      => $today,
            'steps'     => 12000,
        ]);

        // Male member walked 8,000 steps today
        WalkingRecord::create([
            'person_id' => $maleMember->id,
            'date'      => $today,
            'steps'     => 8000,
        ]);

        // Male viewer walked 5,000 steps today
        WalkingRecord::create([
            'person_id' => $maleViewer->id,
            'date'      => $today,
            'steps'     => 5000,
        ]);

        // Test Today period
        $responseToday = $this->withHeaders($this->identityHeaders($maleViewer))
            ->getJson("/api/v1/walking/groups/{$group->id}/leaderboard?period=today");

        $responseToday->assertOk();
        $responseToday->assertJsonPath('success', true);

        $top = $responseToday->json('data.top');
        $this->assertCount(3, $top);

        // Rank 1: Female member with 12,000 steps (NOT 0!)
        $this->assertEquals($femaleMember->id, $top[0]['person_id']);
        $this->assertEquals(12000, $top[0]['total_steps']);
        $this->assertEquals(1, $top[0]['rank']);

        // Rank 2: Male member with 8,000 steps
        $this->assertEquals($maleMember->id, $top[1]['person_id']);
        $this->assertEquals(8000, $top[1]['total_steps']);
        $this->assertEquals(2, $top[1]['rank']);

        // Rank 3: Male viewer with 5,000 steps
        $this->assertEquals($maleViewer->id, $top[2]['person_id']);
        $this->assertEquals(5000, $top[2]['total_steps']);
        $this->assertEquals(3, $top[2]['rank']);

        // Test Weekly period as well
        $responseWeekly = $this->withHeaders($this->identityHeaders($maleViewer))
            ->getJson("/api/v1/walking/groups/{$group->id}/leaderboard?period=weekly");

        $responseWeekly->assertOk();
        $topWeekly = $responseWeekly->json('data.top');
        $this->assertCount(3, $topWeekly);
        $femaleWeekly = collect($topWeekly)->firstWhere('person_id', $femaleMember->id);
        $this->assertNotNull($femaleWeekly);
        $this->assertEquals(12000, $femaleWeekly['total_steps']);

        // Test Monthly period
        $responseMonthly = $this->withHeaders($this->identityHeaders($maleViewer))
            ->getJson("/api/v1/walking/groups/{$group->id}/leaderboard?period=monthly");

        $responseMonthly->assertOk();
        $topMonthly = $responseMonthly->json('data.top');
        $femaleMonthly = collect($topMonthly)->firstWhere('person_id', $femaleMember->id);
        $this->assertNotNull($femaleMonthly);
        $this->assertEquals(12000, $femaleMonthly['total_steps']);
    }

    public function test_group_member_with_global_hidden_mode_is_masked_as_anonymous_in_group_leaderboard(): void
    {
        $today = Carbon::today()->toDateString();

        $viewer = Person::create([
            'first_name' => 'خالد',
            'last_name'  => 'السريّع',
            'gender'     => 'male',
        ]);

        $hiddenFemale = Person::create([
            'first_name'               => 'نورة',
            'last_name'                => 'السريّع',
            'gender'                   => 'female',
            'walking_leaderboard_mode' => 'hidden',
        ]);

        $group = WalkingGroup::create([
            'name'                 => 'تحدي خاص',
            'created_by_person_id' => $viewer->id,
            'is_public'            => false,
            'status'               => 'active',
            'start_date'           => Carbon::today()->subDays(2)->toDateString(),
            'end_date'             => Carbon::today()->addDays(2)->toDateString(),
        ]);

        WalkingGroupMember::create([
            'walking_group_id' => $group->id,
            'person_id'        => $viewer->id,
            'role'             => 'admin',
            'status'           => 'accepted',
            'joined_at'        => Carbon::today()->subDays(2),
        ]);

        WalkingGroupMember::create([
            'walking_group_id' => $group->id,
            'person_id'        => $hiddenFemale->id,
            'role'             => 'member',
            'status'           => 'accepted',
            'joined_at'        => Carbon::today()->subDays(1),
        ]);

        WalkingRecord::create([
            'person_id' => $hiddenFemale->id,
            'date'      => $today,
            'steps'     => 15000,
        ]);

        $response = $this->withHeaders($this->identityHeaders($viewer))
            ->getJson("/api/v1/walking/groups/{$group->id}/leaderboard?period=today");

        $response->assertOk();
        $top = $response->json('data.top');
        $this->assertCount(2, $top);

        $hiddenEntry = collect($top)->firstWhere('is_anonymous', true);
        $this->assertNotNull($hiddenEntry);
        $this->assertNull($hiddenEntry['person_id']);
        $this->assertEquals(15000, $hiddenEntry['total_steps']);
        $this->assertTrue($hiddenEntry['is_anonymous']);
        $this->assertEquals('متسابق متخفي', $hiddenEntry['person']['name']);
    }
}

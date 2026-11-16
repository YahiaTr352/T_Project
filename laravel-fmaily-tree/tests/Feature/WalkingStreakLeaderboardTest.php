<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\WalkingClub;
use App\Models\WalkingRecord;
use App\Services\AI\EmbeddingsService;
use App\Services\SiteMobileAccessToken;
use App\Services\Walking\WalkingLeaderboardEligibilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WalkingStreakLeaderboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        config(['site.password_protection_enabled' => false]);

        $this->mock(EmbeddingsService::class, function ($mock) {
            $mock->shouldIgnoreMissing();
        });

        app(WalkingLeaderboardEligibilityService::class)->clearCache();
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

    /**
     * 1. Streak leaderboard ranks correctly by consecutive days of 10k steps.
     */
    public function test_streak_leaderboard_ranks_by_consecutive_10k_days(): void
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $twoDaysAgo = Carbon::today()->subDays(2);

        $personA = Person::factory()->create([
            'gender'                  => 'male',
            'from_outside_the_family' => false,
            'first_name'              => 'UserA',
        ]);
        $personB = Person::factory()->create([
            'gender'                  => 'male',
            'from_outside_the_family' => false,
            'first_name'              => 'UserB',
        ]);

        // Person A has 3 consecutive days >= 10,000
        WalkingRecord::create(['person_id' => $personA->id, 'date' => $twoDaysAgo->toDateString(), 'steps' => 12000]);
        WalkingRecord::create(['person_id' => $personA->id, 'date' => $yesterday->toDateString(), 'steps' => 10500]);
        WalkingRecord::create(['person_id' => $personA->id, 'date' => $today->toDateString(), 'steps' => 10100]);

        // Person B has only yesterday and today (2 days streak)
        WalkingRecord::create(['person_id' => $personB->id, 'date' => $yesterday->toDateString(), 'steps' => 11000]);
        WalkingRecord::create(['person_id' => $personB->id, 'date' => $today->toDateString(), 'steps' => 15000]);

        $response = $this->withHeaders($this->identityHeaders($personA))
            ->getJson('/api/v1/walking/leaderboard/streak');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.type', 'streak');

        $top = $response->json('data.top');
        $this->assertCount(2, $top);
        $this->assertEquals($personA->id, $top[0]['person_id']);
        $this->assertEquals(3, $top[0]['streak_10k']);
        $this->assertEquals(1, $top[0]['rank']);

        $this->assertEquals($personB->id, $top[1]['person_id']);
        $this->assertEquals(2, $top[1]['streak_10k']);
        $this->assertEquals(2, $top[1]['rank']);
    }

    /**
     * 2. Broken streak drops user out of the active streak list.
     */
    public function test_broken_streak_user_is_not_in_active_streak_list(): void
    {
        $today = Carbon::today();
        $fourDaysAgo = Carbon::today()->subDays(4);

        $personActive = Person::factory()->create([
            'gender'                  => 'male',
            'from_outside_the_family' => false,
        ]);
        $personBroken = Person::factory()->create([
            'gender'                  => 'male',
            'from_outside_the_family' => false,
        ]);

        // Person Active reached 10k today
        WalkingRecord::create(['person_id' => $personActive->id, 'date' => $today->toDateString(), 'steps' => 10000]);

        // Person Broken reached 10k 4 days ago, but didn't walk today or yesterday
        WalkingRecord::create(['person_id' => $personBroken->id, 'date' => $fourDaysAgo->toDateString(), 'steps' => 15000]);

        $response = $this->withHeaders($this->identityHeaders($personActive))
            ->getJson('/api/v1/walking/leaderboard/streak');

        $response->assertOk();
        $top = $response->json('data.top');
        $this->assertCount(1, $top);
        $this->assertEquals($personActive->id, $top[0]['person_id']);
    }

    /**
     * 3. Male viewer cannot see female participants on streak leaderboard.
     */
    public function test_male_viewer_cannot_see_female_in_streak_leaderboard(): void
    {
        $today = Carbon::today();

        $male = Person::factory()->create([
            'gender'                  => 'male',
            'from_outside_the_family' => false,
        ]);
        $female = Person::factory()->create([
            'gender'                  => 'female',
            'from_outside_the_family' => false,
        ]);

        WalkingRecord::create(['person_id' => $male->id, 'date' => $today->toDateString(), 'steps' => 10000]);
        WalkingRecord::create(['person_id' => $female->id, 'date' => $today->toDateString(), 'steps' => 12000]);

        $response = $this->withHeaders($this->identityHeaders($male))
            ->getJson('/api/v1/walking/leaderboard/streak');

        $response->assertOk();
        $top = $response->json('data.top');
        $this->assertCount(1, $top);
        $this->assertEquals($male->id, $top[0]['person_id']);
    }

    /**
     * 4. Female viewer sees ONLY females on streak leaderboard (strict segregation, cannot see males).
     */
    public function test_female_viewer_sees_only_females_on_streak_leaderboard(): void
    {
        $today = Carbon::today();

        $femaleViewer = Person::factory()->create([
            'gender'                  => 'female',
            'from_outside_the_family' => false,
        ]);
        $male = Person::factory()->create([
            'gender'                  => 'male',
            'from_outside_the_family' => false,
        ]);

        WalkingRecord::create(['person_id' => $femaleViewer->id, 'date' => $today->toDateString(), 'steps' => 11000]);
        WalkingRecord::create(['person_id' => $male->id, 'date' => $today->toDateString(), 'steps' => 14000]);

        // Even when passing gender_filter=all, strict segregation returns ONLY females
        $resAll = $this->withHeaders($this->identityHeaders($femaleViewer))
            ->getJson('/api/v1/walking/leaderboard/streak?gender_filter=all');
        $resAll->assertOk();
        $this->assertCount(1, $resAll->json('data.top'));
        $this->assertEquals($femaleViewer->id, $resAll->json('data.top.0.person_id'));

        // Querying female returns only females
        $resFemale = $this->withHeaders($this->identityHeaders($femaleViewer))
            ->getJson('/api/v1/walking/leaderboard/streak?gender_filter=female');
        $resFemale->assertOk();
        $topFemale = $resFemale->json('data.top');
        $this->assertCount(1, $topFemale);
        $this->assertEquals($femaleViewer->id, $topFemale[0]['person_id']);
    }

    /**
     * 5. Hidden person is completely excluded; Anonymous person is masked.
     */
    public function test_hidden_and_anonymous_privacy_on_streak_leaderboard(): void
    {
        $today = Carbon::today();

        $viewer = Person::factory()->create([
            'gender'                  => 'male',
            'from_outside_the_family' => false,
        ]);
        $hiddenUser = Person::factory()->create([
            'gender'                  => 'male',
            'from_outside_the_family' => false,
            'walking_leaderboard_mode' => 'hidden',
        ]);
        $anonUser = Person::factory()->create([
            'gender'                  => 'male',
            'from_outside_the_family' => false,
            'walking_leaderboard_mode' => 'anonymous',
        ]);

        WalkingRecord::create(['person_id' => $viewer->id, 'date' => $today->toDateString(), 'steps' => 10000]);
        WalkingRecord::create(['person_id' => $hiddenUser->id, 'date' => $today->toDateString(), 'steps' => 20000]);
        WalkingRecord::create(['person_id' => $anonUser->id, 'date' => $today->toDateString(), 'steps' => 15000]);

        $response = $this->withHeaders($this->identityHeaders($viewer))
            ->getJson('/api/v1/walking/leaderboard/streak');

        $response->assertOk();
        $top = $response->json('data.top');
        // Hidden user must NOT appear
        $personIds = array_column($top, 'person_id');
        $this->assertNotContains($hiddenUser->id, $personIds);

        // Anon user must be masked
        $anonEntry = collect($top)->firstWhere('streak_10k', 1);
        $this->assertTrue(collect($top)->contains('is_anonymous', true));
        $this->assertContains('متسابق متخفي', array_column(array_column($top, 'person'), 'name'));
    }

    /**
     * 6. Streak leaderboard supports 5k club and 20k club thresholds via min_steps.
     */
    public function test_streak_leaderboard_supports_5k_and_20k_clubs(): void
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        $user5k = Person::factory()->create(['gender' => 'male', 'from_outside_the_family' => false]);
        $user20k = Person::factory()->create(['gender' => 'male', 'from_outside_the_family' => false]);

        // user5k walks 6,000 steps today and yesterday
        WalkingRecord::create(['person_id' => $user5k->id, 'date' => $yesterday->toDateString(), 'steps' => 6000]);
        WalkingRecord::create(['person_id' => $user5k->id, 'date' => $today->toDateString(), 'steps' => 6000]);

        // user20k walks 22,000 steps today and yesterday
        WalkingRecord::create(['person_id' => $user20k->id, 'date' => $yesterday->toDateString(), 'steps' => 22000]);
        WalkingRecord::create(['person_id' => $user20k->id, 'date' => $today->toDateString(), 'steps' => 22000]);

        // In 5k club: ONLY user5k appears (user20k walked 22,000 steps and belongs exclusively to 20k club)
        $res5k = $this->withHeaders($this->identityHeaders($user5k))
            ->getJson('/api/v1/walking/leaderboard/streak?min_steps=5000');
        $res5k->assertOk();
        $top5k = $res5k->json('data.top');
        $this->assertCount(1, $top5k);
        $this->assertEquals($user5k->id, $top5k[0]['person_id']);
        $this->assertEquals(2, $top5k[0]['streak_days']);

        // In 20k club: ONLY user20k appears (steps >= 20000)
        $res20k = $this->withHeaders($this->identityHeaders($user20k))
            ->getJson('/api/v1/walking/leaderboard/streak?min_steps=20000');
        $res20k->assertOk();
        $top20k = $res20k->json('data.top');
        $this->assertCount(1, $top20k);
        $this->assertEquals($user20k->id, $top20k[0]['person_id']);
        $this->assertEquals(2, $top20k[0]['streak_days']);
    }

    /**
     * 7. Broken 10k streak preserves 5k streak if 5k was achieved.
     */
    public function test_broken_10k_streak_preserves_5k_streak_if_5k_achieved(): void
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $twoDaysAgo = Carbon::today()->subDays(2);

        $user = Person::factory()->create(['gender' => 'male', 'from_outside_the_family' => false]);

        // User walked 12,000 steps two days ago, but only 6,000 steps yesterday and today
        WalkingRecord::create(['person_id' => $user->id, 'date' => $twoDaysAgo->toDateString(), 'steps' => 12000]);
        WalkingRecord::create(['person_id' => $user->id, 'date' => $yesterday->toDateString(), 'steps' => 6000]);
        WalkingRecord::create(['person_id' => $user->id, 'date' => $today->toDateString(), 'steps' => 6000]);

        // In 10k club: neither yesterday nor today has >= 10,000 -> user is not in active streak list!
        $res10k = $this->withHeaders($this->identityHeaders($user))
            ->getJson('/api/v1/walking/leaderboard/streak?min_steps=10000');
        $res10k->assertOk();
        $top10k = $res10k->json('data.top');
        $this->assertEmpty($top10k);

        // In 5k club: all 3 days >= 5,000 -> user has an active 3-day streak!
        $res5k = $this->withHeaders($this->identityHeaders($user))
            ->getJson('/api/v1/walking/leaderboard/streak?min_steps=5000');
        $res5k->assertOk();
        $top5k = $res5k->json('data.top');
        $this->assertCount(1, $top5k);
        $this->assertEquals($user->id, $top5k[0]['person_id']);
        $this->assertEquals(3, $top5k[0]['streak_days']);
    }

    /**
     * 8. Exclusive club tiers: 10k walker appears ONLY in 10k club, 9k in 5k, 20k in 20k.
     */
    public function test_exclusive_club_tiers_isolation(): void
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        $user9k  = Person::factory()->create(['gender' => 'male', 'from_outside_the_family' => false, 'first_name' => 'Walker9k']);
        $user10k = Person::factory()->create(['gender' => 'male', 'from_outside_the_family' => false, 'first_name' => 'Walker10k']);
        $user20k = Person::factory()->create(['gender' => 'male', 'from_outside_the_family' => false, 'first_name' => 'Walker20k']);

        // 9,000 steps walker (should be in 5k club [5000-9999] only)
        WalkingRecord::create(['person_id' => $user9k->id, 'date' => $yesterday->toDateString(), 'steps' => 9000]);
        WalkingRecord::create(['person_id' => $user9k->id, 'date' => $today->toDateString(), 'steps' => 9000]);

        // 10,000 steps walker (should be in 10k club [10000-19999] only)
        WalkingRecord::create(['person_id' => $user10k->id, 'date' => $yesterday->toDateString(), 'steps' => 10000]);
        WalkingRecord::create(['person_id' => $user10k->id, 'date' => $today->toDateString(), 'steps' => 10000]);

        // 20,000 steps walker (should be in 20k club [20000+] only)
        WalkingRecord::create(['person_id' => $user20k->id, 'date' => $yesterday->toDateString(), 'steps' => 20000]);
        WalkingRecord::create(['person_id' => $user20k->id, 'date' => $today->toDateString(), 'steps' => 20000]);

        // 1. Check 5k Club (min_steps=5000) -> ONLY user9k must appear!
        $res5k = $this->withHeaders($this->identityHeaders($user9k))
            ->getJson('/api/v1/walking/leaderboard/streak?min_steps=5000');
        $res5k->assertOk();
        $pIds5k = array_column($res5k->json('data.top'), 'person_id');
        $this->assertEquals([$user9k->id], $pIds5k);

        // 2. Check 10k Club (min_steps=10000) -> ONLY user10k must appear!
        $res10k = $this->withHeaders($this->identityHeaders($user10k))
            ->getJson('/api/v1/walking/leaderboard/streak?min_steps=10000');
        $res10k->assertOk();
        $pIds10k = array_column($res10k->json('data.top'), 'person_id');
        $this->assertEquals([$user10k->id], $pIds10k);

        // 3. Check 20k Club (min_steps=20000) -> ONLY user20k must appear!
        $res20k = $this->withHeaders($this->identityHeaders($user20k))
            ->getJson('/api/v1/walking/leaderboard/streak?min_steps=20000');
        $res20k->assertOk();
        $pIds20k = array_column($res20k->json('data.top'), 'person_id');
        $this->assertEquals([$user20k->id], $pIds20k);
    }

    /**
     * 9. Morning preservation and daily tier promotion across clubs.
     */
    public function test_club_tier_transition_and_morning_preservation(): void
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        $promotedUser = Person::factory()->create(['gender' => 'male', 'from_outside_the_family' => false, 'first_name' => 'PromotedUser']);
        $morningUser  = Person::factory()->create(['gender' => 'male', 'from_outside_the_family' => false, 'first_name' => 'MorningUser']);

        // PromotedUser: 7,000 steps yesterday (was 5k), reaches 12,000 steps today -> promoted to 10k club!
        WalkingRecord::create(['person_id' => $promotedUser->id, 'date' => $yesterday->toDateString(), 'steps' => 7000]);
        WalkingRecord::create(['person_id' => $promotedUser->id, 'date' => $today->toDateString(), 'steps' => 12000]);

        // MorningUser: 15,000 steps yesterday (was 10k), has only walked 1,000 steps today -> preserved in 10k club!
        WalkingRecord::create(['person_id' => $morningUser->id, 'date' => $yesterday->toDateString(), 'steps' => 15000]);
        WalkingRecord::create(['person_id' => $morningUser->id, 'date' => $today->toDateString(), 'steps' => 1000]);

        // In 5k club: NEITHER should appear! PromotedUser has 12k today, MorningUser has 15k active streak from yesterday
        $res5k = $this->withHeaders($this->identityHeaders($promotedUser))
            ->getJson('/api/v1/walking/leaderboard/streak?min_steps=5000');
        $res5k->assertOk();
        $pIds5k = array_column($res5k->json('data.top'), 'person_id');
        $this->assertNotContains($promotedUser->id, $pIds5k);
        $this->assertNotContains($morningUser->id, $pIds5k);

        // In 10k club: BOTH should appear!
        $res10k = $this->withHeaders($this->identityHeaders($promotedUser))
            ->getJson('/api/v1/walking/leaderboard/streak?min_steps=10000');
        $res10k->assertOk();
        $pIds10k = array_column($res10k->json('data.top'), 'person_id');
        $this->assertContains($promotedUser->id, $pIds10k);
        $this->assertContains($morningUser->id, $pIds10k);
    }

    /**
     * 10. Midday steps do NOT demote participants from 20k or 10k clubs before midnight.
     */
    public function test_midday_steps_do_not_demote_user_from_higher_club_before_midnight(): void
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        $user20kWalking6k  = Person::factory()->create(['gender' => 'male', 'from_outside_the_family' => false, 'first_name' => 'User20kWalk6k']);
        $user20kWalking12k = Person::factory()->create(['gender' => 'male', 'from_outside_the_family' => false, 'first_name' => 'User20kWalk12k']);
        $user10kWalking6k  = Person::factory()->create(['gender' => 'male', 'from_outside_the_family' => false, 'first_name' => 'User10kWalk6k']);

        // User A was 20k yesterday, has 6,000 steps today (midday)
        WalkingRecord::create(['person_id' => $user20kWalking6k->id, 'date' => $yesterday->toDateString(), 'steps' => 21000]);
        WalkingRecord::create(['person_id' => $user20kWalking6k->id, 'date' => $today->toDateString(), 'steps' => 6000]);

        // User B was 20k yesterday, has 12,000 steps today (afternoon)
        WalkingRecord::create(['person_id' => $user20kWalking12k->id, 'date' => $yesterday->toDateString(), 'steps' => 22000]);
        WalkingRecord::create(['person_id' => $user20kWalking12k->id, 'date' => $today->toDateString(), 'steps' => 12000]);

        // User C was 10k yesterday, has 6,000 steps today
        WalkingRecord::create(['person_id' => $user10kWalking6k->id, 'date' => $yesterday->toDateString(), 'steps' => 13000]);
        WalkingRecord::create(['person_id' => $user10kWalking6k->id, 'date' => $today->toDateString(), 'steps' => 6000]);

        // 1. In 5k club: NONE of them should appear (User C belongs to 10k, Users A & B belong to 20k)
        $res5k = $this->withHeaders($this->identityHeaders($user20kWalking6k))
            ->getJson('/api/v1/walking/leaderboard/streak?min_steps=5000');
        $res5k->assertOk();
        $pIds5k = array_column($res5k->json('data.top'), 'person_id');
        $this->assertNotContains($user20kWalking6k->id, $pIds5k);
        $this->assertNotContains($user20kWalking12k->id, $pIds5k);
        $this->assertNotContains($user10kWalking6k->id, $pIds5k);

        // 2. In 10k club: ONLY User C must appear (User A & B belong to 20k)
        $res10k = $this->withHeaders($this->identityHeaders($user10kWalking6k))
            ->getJson('/api/v1/walking/leaderboard/streak?min_steps=10000');
        $res10k->assertOk();
        $pIds10k = array_column($res10k->json('data.top'), 'person_id');
        $this->assertContains($user10kWalking6k->id, $pIds10k);
        $this->assertNotContains($user20kWalking6k->id, $pIds10k);
        $this->assertNotContains($user20kWalking12k->id, $pIds10k);

        // 3. In 20k club: BOTH User A and User B must appear!
        $res20k = $this->withHeaders($this->identityHeaders($user20kWalking6k))
            ->getJson('/api/v1/walking/leaderboard/streak?min_steps=20000');
        $res20k->assertOk();
        $pIds20k = array_column($res20k->json('data.top'), 'person_id');
        $this->assertContains($user20kWalking6k->id, $pIds20k);
        $this->assertContains($user20kWalking12k->id, $pIds20k);
        $this->assertNotContains($user10kWalking6k->id, $pIds20k);

        // Viewer rank checks for User A (in 20k club with active_club = club_20k)
        $this->assertEquals('club_20k', $res20k->json('data.viewer_rank.active_club'));
        $this->assertTrue($res20k->json('data.viewer_rank.is_qualified'));
        $this->assertFalse($res20k->json('data.viewer_rank.is_today_completed'));
    }

    /**
     * 11. Midnight transition drops unfulfilled club members to lower club or out completely.
     */
    public function test_midnight_transition_drops_unfulfilled_club_member_to_lower_tier_or_out(): void
    {
        $day1 = Carbon::parse('2026-10-01')->startOfDay();
        $day2 = Carbon::parse('2026-10-02')->startOfDay();
        $day3 = Carbon::parse('2026-10-03')->startOfDay();

        $droppedTo10k = Person::factory()->create(['gender' => 'male', 'from_outside_the_family' => false, 'first_name' => 'DroppedTo10k']);
        $droppedOut   = Person::factory()->create(['gender' => 'male', 'from_outside_the_family' => false, 'first_name' => 'DroppedOut']);

        // Day 1: Both achieve 20k steps
        WalkingRecord::create(['person_id' => $droppedTo10k->id, 'date' => $day1->toDateString(), 'steps' => 20500]);
        WalkingRecord::create(['person_id' => $droppedOut->id, 'date' => $day1->toDateString(), 'steps' => 20500]);

        // Day 2: droppedTo10k only reaches 12k, droppedOut only reaches 2k
        WalkingRecord::create(['person_id' => $droppedTo10k->id, 'date' => $day2->toDateString(), 'steps' => 12000]);
        WalkingRecord::create(['person_id' => $droppedOut->id, 'date' => $day2->toDateString(), 'steps' => 2000]);

        try {
            // On Day 2 (before midnight): Both are still preserved in 20k club!
            Carbon::setTestNow($day2);
            $resDay2 = $this->withHeaders($this->identityHeaders($droppedTo10k))
                ->getJson('/api/v1/walking/leaderboard/streak?min_steps=20000');
            $resDay2->assertOk();
            $pIdsDay2 = array_column($resDay2->json('data.top'), 'person_id');
            $this->assertContains($droppedTo10k->id, $pIdsDay2);
            $this->assertContains($droppedOut->id, $pIdsDay2);

            // Midnight strikes -> Now it is Day 3!
            Carbon::setTestNow($day3);

            // In 20k club on Day 3: NEITHER should appear (dropped from 20k club)!
            $resDay3_20k = $this->withHeaders($this->identityHeaders($droppedTo10k))
                ->getJson('/api/v1/walking/leaderboard/streak?min_steps=20000');
            $resDay3_20k->assertOk();
            $pIdsDay3_20k = array_column($resDay3_20k->json('data.top'), 'person_id');
            $this->assertNotContains($droppedTo10k->id, $pIdsDay3_20k);
            $this->assertNotContains($droppedOut->id, $pIdsDay3_20k);

            // In 10k club on Day 3: droppedTo10k (who had 12k on Day 2) must appear! droppedOut must NOT appear!
            $resDay3_10k = $this->withHeaders($this->identityHeaders($droppedTo10k))
                ->getJson('/api/v1/walking/leaderboard/streak?min_steps=10000');
            $resDay3_10k->assertOk();
            $pIdsDay3_10k = array_column($resDay3_10k->json('data.top'), 'person_id');
            $this->assertContains($droppedTo10k->id, $pIdsDay3_10k);
            $this->assertNotContains($droppedOut->id, $pIdsDay3_10k);

            // In 5k club on Day 3: NEITHER should appear (droppedTo10k is in 10k, droppedOut has 0 active streak)
            $resDay3_5k = $this->withHeaders($this->identityHeaders($droppedOut))
                ->getJson('/api/v1/walking/leaderboard/streak?min_steps=5000');
            $resDay3_5k->assertOk();
            $pIdsDay3_5k = array_column($resDay3_5k->json('data.top'), 'person_id');
            $this->assertNotContains($droppedTo10k->id, $pIdsDay3_5k);
            $this->assertNotContains($droppedOut->id, $pIdsDay3_5k);
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * 10. Active clubs API returns configured clubs with custom names, thresholds and colors.
     */
    public function test_walking_clubs_api_returns_active_clubs_list(): void
    {
        WalkingClub::truncate();
        WalkingClub::clearCache();

        WalkingClub::create([
            'slug'                   => 'club_hamada',
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
            'display_order'          => 1,
            'is_active'              => true,
        ]);

        $response = $this->getJson('/api/v1/walking/clubs');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.name', 'حمادة العب')
            ->assertJsonPath('data.0.min_steps', 5000)
            ->assertJsonPath('data.0.icon', '🔥');
    }

    /**
     * 11. calculation_start_date strictly limits streak calculation:
     * A participant with 20 consecutive days of walking 5k steps,
     * when calculation_start_date is set to yesterday,
     * will have streak = 1 (if today not completed) and streak = 2 (if today is completed).
     */
    public function test_calculation_start_date_caps_streak_to_start_date(): void
    {
        $anchorToday = Carbon::today('Asia/Riyadh');
        Carbon::setTestNow($anchorToday);

        try {
            $yesterday = $anchorToday->copy()->subDay();

            WalkingClub::truncate();
            WalkingClub::clearCache();

            $club5k = WalkingClub::create([
                'slug'                   => 'club_5k',
                'name'                   => 'نادي الـ 5,000 خطوة يومياً',
                'short_name'             => 'نادي الـ 5,000',
                'sub_label'              => 'يومياً',
                'min_steps'              => 5000,
                'max_steps'              => 9999,
                'calculation_start_date' => $yesterday->toDateString(), // Start counting ONLY from yesterday!
                'icon'                   => '🥉',
                'display_order'          => 1,
                'is_active'              => true,
            ]);

            $person = Person::factory()->create([
                'gender'                  => 'male',
                'from_outside_the_family' => false,
            ]);

            // Create 20 consecutive days of 5,500 steps ending yesterday (days 1 to 20 in the past)
            for ($i = 1; $i <= 20; $i++) {
                $recordDate = $anchorToday->copy()->subDays($i)->toDateString();
                WalkingRecord::create([
                    'person_id' => $person->id,
                    'date'      => $recordDate,
                    'steps'     => 5500,
                ]);
            }

            // Case A: Today's steps are 0 (not completed yet).
            // Without start date: streak would be 20 days.
            // WITH start date set to yesterday: streak MUST BE exactly 1 day (yesterday only)!
            $resCaseA = $this->withHeaders($this->identityHeaders($person))
                ->getJson('/api/v1/walking/leaderboard/streak?min_steps=5000');

            $resCaseA->assertOk();
            $this->assertEquals(1, $resCaseA->json('data.viewer_rank.streak_days'));

            // Case B: Today's steps are completed (e.g. 6,000 steps today)
            WalkingRecord::create([
                'person_id' => $person->id,
                'date'      => $anchorToday->toDateString(),
                'steps'     => 6000,
            ]);

            // With today completed, streak MUST BE exactly 2 days (yesterday + today)!
            $resCaseB = $this->withHeaders($this->identityHeaders($person))
                ->getJson('/api/v1/walking/leaderboard/streak?min_steps=5000');

            $resCaseB->assertOk();
            $this->assertEquals(2, $resCaseB->json('data.viewer_rank.streak_days'));
        } finally {
            Carbon::setTestNow();
        }
    }
}


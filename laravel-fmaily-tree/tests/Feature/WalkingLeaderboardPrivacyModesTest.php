<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\WalkingRecord;
use App\Services\AI\EmbeddingsService;
use App\Services\SiteMobileAccessToken;
use App\Services\Walking\WalkingLeaderboardEligibilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WalkingLeaderboardPrivacyModesTest extends TestCase
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
     * Test 1: User with walking_leaderboard_mode = 'hidden' is completely excluded from leaderboard.
     */
    public function test_hidden_user_is_completely_excluded_from_leaderboard(): void
    {
        $today = Carbon::today()->toDateString();

        $activeUser = Person::factory()->create([
            'gender'                  => 'male',
            'from_outside_the_family' => false,
            'walking_leaderboard_mode' => 'visible',
        ]);

        $hiddenUser = Person::factory()->create([
            'gender'                  => 'male',
            'from_outside_the_family' => false,
            'walking_leaderboard_mode' => 'hidden',
        ]);

        $viewer = Person::factory()->create([
            'gender'                  => 'male',
            'from_outside_the_family' => false,
        ]);

        // Hidden user walked 25,000 steps (would normally be #1)
        WalkingRecord::create([
            'person_id'        => $hiddenUser->id,
            'steps'            => 25000,
            'date'             => $today,
            'distance_km'      => 18.0,
            'duration_minutes' => 150,
            'calories'         => 1000,
        ]);

        // Active user walked 10,000 steps
        WalkingRecord::create([
            'person_id'        => $activeUser->id,
            'steps'            => 10000,
            'date'             => $today,
            'distance_km'      => 7.0,
            'duration_minutes' => 60,
            'calories'         => 400,
        ]);

        $response = $this->withHeaders($this->identityHeaders($viewer))
            ->getJson('/api/v1/walking/leaderboard?period=today');

        $response->assertStatus(200);
        $top = $response->json('data.top');

        // Only 1 person should be in the top list, and it must be activeUser (#1)
        $this->assertCount(1, $top);
        $this->assertEquals($activeUser->id, $top[0]['person_id']);
        $this->assertEquals(1, $top[0]['rank']);

        // Hidden user must NOT be in top list
        $topPersonIds = collect($top)->pluck('person_id')->toArray();
        $this->assertNotContains($hiddenUser->id, $topPersonIds);
    }

    /**
     * Test 2: User with walking_leaderboard_mode = 'anonymous' appears in ranking, but identity is masked for others.
     */
    public function test_anonymous_user_appears_with_masked_identity_for_others(): void
    {
        $today = Carbon::today()->toDateString();

        $anonUser = Person::factory()->create([
            'gender'                  => 'male',
            'from_outside_the_family' => false,
            'first_name'              => 'سلطان',
            'last_name'               => 'السريّع',
            'walking_leaderboard_mode' => 'anonymous',
        ]);

        $viewer = Person::factory()->create([
            'gender'                  => 'male',
            'from_outside_the_family' => false,
        ]);

        WalkingRecord::create([
            'person_id'        => $anonUser->id,
            'steps'            => 20000,
            'date'             => $today,
            'distance_km'      => 14.0,
            'duration_minutes' => 120,
            'calories'         => 800,
        ]);

        // Request from viewer's perspective
        $response = $this->withHeaders($this->identityHeaders($viewer))
            ->getJson('/api/v1/walking/leaderboard?period=today');

        $response->assertStatus(200);
        $top = $response->json('data.top');

        $this->assertCount(1, $top);
        $this->assertEquals(1, $top[0]['rank']);
        $this->assertEquals(20000, $top[0]['total_steps']);
        $this->assertTrue($top[0]['is_anonymous']);
        $this->assertNull($top[0]['person_id']);
        $this->assertEquals('متسابق متخفي', $top[0]['person']['name']);
        $this->assertNotNull($top[0]['person']['avatar']);
        $this->assertNotNull($top[0]['person']['gender']);

        // Request from anonymous user's own perspective (self view)
        $selfResponse = $this->withHeaders($this->identityHeaders($anonUser))
            ->getJson('/api/v1/walking/leaderboard?period=today');

        $selfResponse->assertStatus(200);
        $selfTop = $selfResponse->json('data.top');

        $this->assertCount(1, $selfTop);
        $this->assertTrue($selfTop[0]['is_self']);
        $this->assertTrue($selfTop[0]['is_anonymous']);
        $this->assertStringContainsString('متخفي', $selfTop[0]['person']['name']);
    }

    /**
     * Test 3: API endpoint to get and update privacy settings.
     */
    public function test_api_can_get_and_update_privacy_settings(): void
    {
        $person = Person::factory()->create([
            'gender'                  => 'male',
            'from_outside_the_family' => false,
            'walking_leaderboard_mode' => 'visible',
        ]);

        // 1. Get current mode
        $getRes = $this->withHeaders($this->identityHeaders($person))
            ->getJson('/api/v1/walking/privacy-settings');

        $getRes->assertStatus(200);
        $this->assertEquals('visible', $getRes->json('data.leaderboard_mode'));

        // 2. Update to anonymous
        $postRes1 = $this->withHeaders($this->identityHeaders($person))
            ->postJson('/api/v1/walking/privacy-settings', [
                'leaderboard_mode' => 'anonymous',
            ]);

        $postRes1->assertStatus(200);
        $this->assertEquals('anonymous', $postRes1->json('data.leaderboard_mode'));
        $this->assertEquals('anonymous', $person->fresh()->walking_leaderboard_mode);

        // 3. Update to hidden
        $postRes2 = $this->withHeaders($this->identityHeaders($person))
            ->postJson('/api/v1/walking/privacy-settings', [
                'leaderboard_mode' => 'hidden',
            ]);

        $postRes2->assertStatus(200);
        $this->assertEquals('hidden', $postRes2->json('data.leaderboard_mode'));
        $this->assertEquals('hidden', $person->fresh()->walking_leaderboard_mode);

        // 4. Reject invalid mode
        $badRes = $this->withHeaders($this->identityHeaders($person))
            ->postJson('/api/v1/walking/privacy-settings', [
                'leaderboard_mode' => 'invalid_mode_xyz',
            ]);

        $badRes->assertStatus(422);
    }
}

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

class WalkingGroupPrivacyInheritanceTest extends TestCase
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

    /**
     * Test 1: Globally hidden member inherits anonymity in group challenges.
     * Regular members see "متسابق متخفي", group admin sees real name with badge, self sees real name with (أنت - متخفي).
     */
    public function test_globally_hidden_member_inherits_anonymity_in_group(): void
    {
        $admin = Person::create([
            'first_name'               => 'خالد',
            'last_name'                => 'السريّع',
            'gender'                   => 'male',
            'walking_leaderboard_mode' => 'visible',
        ]);

        $hiddenMember = Person::create([
            'first_name'               => 'سارة',
            'last_name'                => 'السريّع',
            'gender'                   => 'female',
            'walking_leaderboard_mode' => 'hidden',
        ]);

        $regularMember = Person::create([
            'first_name'               => 'فهد',
            'last_name'                => 'السريّع',
            'gender'                   => 'male',
            'walking_leaderboard_mode' => 'visible',
        ]);

        $group = WalkingGroup::create([
            'name'                 => 'تحدي الأسرة الخاص',
            'created_by_person_id' => $admin->id,
            'is_public'            => false,
            'status'               => 'active',
            'start_date'           => Carbon::today()->subDays(2)->toDateString(),
            'end_date'             => Carbon::today()->addDays(5)->toDateString(),
        ]);

        WalkingGroupMember::create([
            'walking_group_id' => $group->id,
            'person_id'        => $admin->id,
            'role'             => 'admin',
            'status'           => 'accepted',
            'joined_at'        => Carbon::today()->subDays(2),
        ]);

        $hiddenMembership = WalkingGroupMember::create([
            'walking_group_id' => $group->id,
            'person_id'        => $hiddenMember->id,
            'role'             => 'member',
            'status'           => 'accepted',
            'is_anonymous'     => null, // inherited
            'joined_at'        => Carbon::today()->subDays(2),
        ]);

        WalkingGroupMember::create([
            'walking_group_id' => $group->id,
            'person_id'        => $regularMember->id,
            'role'             => 'member',
            'status'           => 'accepted',
            'joined_at'        => Carbon::today()->subDays(2),
        ]);

        WalkingRecord::create([
            'person_id'   => $hiddenMember->id,
            'date'        => Carbon::today()->toDateString(),
            'steps'       => 15000,
            'distance_km' => 10.0,
        ]);

        // A) Regular member view: hidden member identity is masked
        $resRegular = $this->withHeaders($this->identityHeaders($regularMember))
            ->getJson("/api/v1/walking/groups/{$group->id}/leaderboard?period=today");

        $resRegular->assertStatus(200);
        $top = $resRegular->json('data.top');
        $this->assertNotEmpty($top);

        $hiddenEntry = collect($top)->firstWhere('membership_id', $hiddenMembership->id);
        $this->assertNotNull($hiddenEntry);
        $this->assertTrue($hiddenEntry['is_anonymous']);
        $this->assertNull($hiddenEntry['person_id']);
        $this->assertEquals('متسابق متخفي', $hiddenEntry['person']['name']);

        // B) Group Admin view:
        // In the leaderboard: identity stays masked as 'متسابق متخفي' (to prevent accidental leaks via leaderboard screenshots)
        $resAdmin = $this->withHeaders($this->identityHeaders($admin))
            ->getJson("/api/v1/walking/groups/{$group->id}/leaderboard?period=today");

        $resAdmin->assertStatus(200);
        $adminTop = $resAdmin->json('data.top');
        $hiddenEntryForAdmin = collect($adminTop)->firstWhere('membership_id', $hiddenMembership->id);
        $this->assertNotNull($hiddenEntryForAdmin);
        $this->assertTrue($hiddenEntryForAdmin['is_anonymous']);
        $this->assertNull($hiddenEntryForAdmin['person_id']);
        $this->assertEquals('متسابق متخفي', $hiddenEntryForAdmin['person']['name']);

        // In the Members list (GET /api/v1/walking/groups/{id}):
        // Admin sees the real name with (متخفي) to manage the challenge members
        $groupDetailsForAdmin = $this->withHeaders($this->identityHeaders($admin))
            ->getJson("/api/v1/walking/groups/{$group->id}");
        $groupDetailsForAdmin->assertStatus(200);
        $adminMembers = $groupDetailsForAdmin->json('data.members');
        $sarahMemberForAdmin = collect($adminMembers)->firstWhere('id', $hiddenMembership->id);
        $this->assertNotNull($sarahMemberForAdmin);
        $this->assertEquals($hiddenMember->id, $sarahMemberForAdmin['person_id']);
        $this->assertStringContainsString('سارة', $sarahMemberForAdmin['person']['name']);
        $this->assertStringContainsString('(متخفي)', $sarahMemberForAdmin['person']['name']);

        // C) Self view: self sees real name with (أنت - متخفي)
        $resSelf = $this->withHeaders($this->identityHeaders($hiddenMember))
            ->getJson("/api/v1/walking/groups/{$group->id}/leaderboard?period=today");

        $resSelf->assertStatus(200);
        $selfTop = $resSelf->json('data.top');
        $hiddenEntryForSelf = collect($selfTop)->firstWhere('membership_id', $hiddenMembership->id);
        $this->assertNotNull($hiddenEntryForSelf);
        $this->assertTrue($hiddenEntryForSelf['is_anonymous']);
        $this->assertTrue($hiddenEntryForSelf['is_self']);
        $this->assertStringContainsString('سارة', $hiddenEntryForSelf['person']['name']);
        $this->assertStringContainsString('(أنت - متخفي)', $hiddenEntryForSelf['person']['name']);

        // D) Group Members API (GET /api/v1/walking/groups/{id}):
        // Regular member cannot see hiddenMember's real name or person_id
        $groupDetailsForRegular = $this->withHeaders($this->identityHeaders($regularMember))
            ->getJson("/api/v1/walking/groups/{$group->id}");

        $groupDetailsForRegular->assertStatus(200);
        $members = $groupDetailsForRegular->json('data.members');
        $memberEntry = collect($members)->firstWhere('id', $hiddenMembership->id);
        $this->assertNotNull($memberEntry);
        $this->assertTrue($memberEntry['is_anonymous']);
        $this->assertNull($memberEntry['person_id']);
        $this->assertEquals('متسابق متخفي', $memberEntry['person']['name']);
    }

    /**
     * Test 2: Member can explicitly reveal herself inside a specific group
     * while staying completely hidden from the platform-wide general leaderboard.
     */
    public function test_member_can_override_privacy_to_visible_in_group_while_globally_hidden(): void
    {
        $hiddenMember = Person::create([
            'first_name'               => 'سارة',
            'last_name'                => 'السريّع',
            'gender'                   => 'female',
            'walking_leaderboard_mode' => 'hidden',
        ]);

        $otherMember = Person::create([
            'first_name'               => 'منى',
            'last_name'                => 'السريّع',
            'gender'                   => 'female',
            'walking_leaderboard_mode' => 'visible',
        ]);

        $group = WalkingGroup::create([
            'name'                 => 'تحدي البنات',
            'created_by_person_id' => $otherMember->id,
            'is_public'            => false,
            'status'               => 'active',
            'start_date'           => Carbon::today()->subDays(1)->toDateString(),
            'end_date'             => Carbon::today()->addDays(5)->toDateString(),
        ]);

        WalkingGroupMember::create([
            'walking_group_id' => $group->id,
            'person_id'        => $otherMember->id,
            'role'             => 'admin',
            'status'           => 'accepted',
            'joined_at'        => Carbon::today()->subDays(1),
        ]);

        $membership = WalkingGroupMember::create([
            'walking_group_id' => $group->id,
            'person_id'        => $hiddenMember->id,
            'role'             => 'member',
            'status'           => 'accepted',
            'is_anonymous'     => false, // Explicit override: revealed in this group!
            'joined_at'        => Carbon::today()->subDays(1),
        ]);

        WalkingRecord::create([
            'person_id'   => $hiddenMember->id,
            'date'        => Carbon::today()->toDateString(),
            'steps'       => 18000,
            'distance_km' => 12.0,
        ]);

        // In the group challenge, other member sees Sarah revealed
        $resGroup = $this->withHeaders($this->identityHeaders($otherMember))
            ->getJson("/api/v1/walking/groups/{$group->id}/leaderboard?period=today");

        $resGroup->assertStatus(200);
        $top = $resGroup->json('data.top');
        $sarahEntry = collect($top)->firstWhere('membership_id', $membership->id);
        $this->assertNotNull($sarahEntry);
        $this->assertFalse($sarahEntry['is_anonymous']);
        $this->assertEquals($hiddenMember->id, $sarahEntry['person_id']);
        $this->assertStringContainsString('سارة', $sarahEntry['person']['name']);

        // BUT in the platform-wide general leaderboard, Sarah is STILL completely excluded/hidden!
        $resGlobal = $this->withHeaders($this->identityHeaders($otherMember))
            ->getJson('/api/v1/walking/leaderboard?period=today');

        $resGlobal->assertStatus(200);
        $globalTop = $resGlobal->json('data.top');
        $globalPersonIds = collect($globalTop)->pluck('person_id')->toArray();
        $this->assertNotContains($hiddenMember->id, $globalPersonIds);
    }
}

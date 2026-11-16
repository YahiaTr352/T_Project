<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Person;
use App\Models\WalkingGroup;
use App\Models\WalkingGroupMember;
use App\Models\WalkingRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalkingGroupMembersSortingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Queue::fake();
        config(['site.password_protection_enabled' => false]);
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    public function test_group_members_are_sorted_descending_by_steps_and_earliest_joined_on_tie(): void
    {
        $creator = Person::create([
            'first_name' => 'خالد',
            'last_name'  => 'السريّع',
            'gender'     => 'male',
        ]);

        $memberHighSteps = Person::create([
            'first_name' => 'فهد',
            'last_name'  => 'السريّع',
            'gender'     => 'male',
        ]);

        $memberMidSteps = Person::create([
            'first_name' => 'عبدالله',
            'last_name'  => 'السريّع',
            'gender'     => 'male',
        ]);

        $memberZeroEarly = Person::create([
            'first_name' => 'سارة',
            'last_name'  => 'السريّع',
            'gender'     => 'female',
        ]);

        $memberZeroLate = Person::create([
            'first_name' => 'نورة',
            'last_name'  => 'السريّع',
            'gender'     => 'female',
        ]);

        $group = WalkingGroup::create([
            'name'                 => 'تحدي عائلة السريّع',
            'created_by_person_id' => $creator->id,
            'is_public'            => true,
            'status'               => 'active',
            'target_steps'         => 10000,
            'start_date'           => now()->subDays(5)->toDateString(),
            'end_date'             => now()->addDays(5)->toDateString(),
        ]);

        // Memberships with different join dates
        WalkingGroupMember::create([
            'walking_group_id' => $group->id,
            'person_id'        => $memberMidSteps->id,
            'role'             => 'member',
            'status'           => 'accepted',
            'joined_at'        => now()->subDays(4),
        ]);

        WalkingGroupMember::create([
            'walking_group_id' => $group->id,
            'person_id'        => $memberHighSteps->id,
            'role'             => 'member',
            'status'           => 'accepted',
            'joined_at'        => now()->subDays(2),
        ]);

        WalkingGroupMember::create([
            'walking_group_id' => $group->id,
            'person_id'        => $memberZeroLate->id,
            'role'             => 'member',
            'status'           => 'accepted',
            'joined_at'        => now()->subDays(1),
        ]);

        WalkingGroupMember::create([
            'walking_group_id' => $group->id,
            'person_id'        => $memberZeroEarly->id,
            'role'             => 'member',
            'status'           => 'accepted',
            'joined_at'        => now()->subDays(3),
        ]);

        // Walking records inside group challenge date range
        WalkingRecord::create([
            'person_id' => $memberMidSteps->id,
            'date'      => now()->subDays(2)->toDateString(),
            'steps'     => 4500,
        ]);

        WalkingRecord::create([
            'person_id' => $memberHighSteps->id,
            'date'      => now()->subDays(2)->toDateString(),
            'steps'     => 12000,
        ]);

        $response = $this->getJson("/api/v1/walking/groups/{$group->id}");

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $members = $response->json('data.members');
        $this->assertCount(4, $members);

        // 1st: memberHighSteps (12,000 steps)
        $this->assertEquals($memberHighSteps->id, $members[0]['person_id']);
        $this->assertEquals(12000, $members[0]['total_steps_in_challenge']);

        // 2nd: memberMidSteps (4,500 steps)
        $this->assertEquals($memberMidSteps->id, $members[1]['person_id']);
        $this->assertEquals(4500, $members[1]['total_steps_in_challenge']);

        // 3rd: memberZeroEarly (0 steps, joined 3 days ago)
        $this->assertEquals($memberZeroEarly->id, $members[2]['person_id']);
        $this->assertEquals(0, $members[2]['total_steps_in_challenge']);

        // 4th: memberZeroLate (0 steps, joined 1 day ago)
        $this->assertEquals($memberZeroLate->id, $members[3]['person_id']);
        $this->assertEquals(0, $members[3]['total_steps_in_challenge']);
    }
}

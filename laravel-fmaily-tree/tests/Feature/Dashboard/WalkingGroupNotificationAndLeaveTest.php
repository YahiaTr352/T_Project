<?php

namespace Tests\Feature\Dashboard;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Notification;
use App\Models\Person;
use App\Models\PersonContactAccount;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\WalkingGroup;
use App\Models\WalkingGroupMember;
use App\Models\WalkingRecord;
use App\Services\ExpoNotificationService;
use App\Services\SiteMobileAccessToken;
use App\Services\UltramsgService;
use App\Services\Walking\WalkingGroupNotificationService;
use App\Services\Walking\WalkingGroupService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WalkingGroupNotificationAndLeaveTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Person $creator;
    protected Person $member1;
    protected Person $member2;
    protected Person $member3;

    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Support\Facades\Queue::fake();
        config(['site.password_protection_enabled' => false]);
        $this->withoutMiddleware(VerifyCsrfToken::class);

        // Permissions
        $viewPerm   = Permission::firstOrCreate(['name' => 'walking-program.view', 'guard_name' => 'web']);
        $createPerm = Permission::firstOrCreate(['name' => 'walking-program.create', 'guard_name' => 'web']);
        $updatePerm = Permission::firstOrCreate(['name' => 'walking-program.update', 'guard_name' => 'web']);
        $deletePerm = Permission::firstOrCreate(['name' => 'walking-program.delete', 'guard_name' => 'web']);

        $this->adminUser = User::factory()->create();
        $this->adminUser->givePermissionTo([$viewPerm, $createPerm, $updatePerm, $deletePerm]);

        // Create sample persons
        $this->creator = Person::create([
            'first_name' => 'خالد',
            'last_name'  => 'السريّع',
            'gender'     => 'male',
            'birth_date' => '1985-05-15',
        ]);

        $this->member1 = Person::create([
            'first_name' => 'فهد',
            'last_name'  => 'السريّع',
            'gender'     => 'male',
            'birth_date' => '1990-08-20',
        ]);

        $this->member2 = Person::create([
            'first_name' => 'سارة',
            'last_name'  => 'السريّع',
            'gender'     => 'female',
            'birth_date' => '1995-10-10',
        ]);

        $this->member3 = Person::create([
            'first_name' => 'نورة',
            'last_name'  => 'السريّع',
            'gender'     => 'female',
            'birth_date' => '1998-03-01',
        ]);

        // Add WhatsApp contact accounts
        PersonContactAccount::create([
            'person_id' => $this->member1->id,
            'type'      => 'whatsapp',
            'value'     => '+966501234567',
        ]);

        PersonContactAccount::create([
            'person_id' => $this->member2->id,
            'type'      => 'phone',
            'value'     => '0559876543',
        ]);

        // Add Push Device Tokens
        UserDevice::create([
            'person_id'       => $this->member1->id,
            'expo_push_token' => 'ExponentPushToken[test-fahad-device]',
            'os_platform'     => 'ios',
        ]);
    }

    public function test_adding_member_triggers_notifications(): void
    {
        $expoMock = Mockery::mock(ExpoNotificationService::class);
        $expoMock->shouldReceive('send')
            ->once()
            ->with(
                Mockery::mustBe(['ExponentPushToken[test-fahad-device]']),
                Mockery::pattern('/تحدي مشي جديد/'),
                Mockery::any(),
                Mockery::type('array')
            )
            ->andReturn(['success' => true, 'sent_count' => 1]);

        $ultramsgMock = Mockery::mock(UltramsgService::class);
        $ultramsgMock->shouldReceive('sendText')
            ->once()
            ->with('966501234567', Mockery::pattern('/تحدي المشي/'))
            ->andReturn(['success' => true]);

        $this->app->instance(ExpoNotificationService::class, $expoMock);
        $this->app->instance(UltramsgService::class, $ultramsgMock);

        /** @var WalkingGroupService $service */
        $service = $this->app->make(WalkingGroupService::class);

        $group = $service->create([
            'name'         => 'تحدي همة العائلة',
            'description'  => 'تحدي المشي اليومي لجميع أفراد الأسرة',
            'target_steps' => 10000,
            'start_date'   => now()->format('Y-m-d'),
        ], creatorPersonId: $this->creator->id);

        // Add member1 (Fahad)
        $member = $service->addMember($group, $this->member1->id, 'member', shouldNotify: true, inviter: $this->creator);

        $this->assertNotNull($member);
        $this->assertEquals('accepted', $member->status);

        // Assert In-App notification was created in database
        $this->assertDatabaseHas('notifications', [
            'title' => "تحدي مشي جديد: {$group->name} 🏃‍♂️",
        ]);

        $notification = Notification::where('title', "تحدي مشي جديد: {$group->name} 🏃‍♂️")->latest()->first();
        $this->assertNotNull($notification);
        $this->assertDatabaseHas('notification_recipients', [
            'notification_id' => $notification->id,
            'person_id'       => $this->member1->id,
        ]);
    }

    public function test_regular_member_can_leave_group(): void
    {
        /** @var WalkingGroupService $service */
        $service = $this->app->make(WalkingGroupService::class);

        $group = $service->create([
            'name' => 'تحدي اللياقة',
        ], creatorPersonId: $this->creator->id);

        $service->addMember($group, $this->member1->id, 'member', shouldNotify: false);
        $service->addMember($group, $this->member2->id, 'member', shouldNotify: false);

        $this->assertTrue($group->hasMember($this->member1->id));

        // Member1 leaves
        $result = $service->leaveGroup($group, $this->member1->id);

        $this->assertTrue($result['success']);
        $this->assertFalse($result['group_deleted']);
        $this->assertFalse($group->fresh()->hasMember($this->member1->id));
        $this->assertTrue($group->fresh()->hasMember($this->member2->id));
        $this->assertEquals($this->creator->id, $group->fresh()->created_by_person_id);
    }

    public function test_group_creator_leaves_transfers_ownership_to_next_member(): void
    {
        $ultramsgMock = Mockery::mock(UltramsgService::class);
        $ultramsgMock->shouldReceive('sendText')->andReturn(['success' => true]);
        $this->app->instance(UltramsgService::class, $ultramsgMock);

        /** @var WalkingGroupService $service */
        $service = $this->app->make(WalkingGroupService::class);

        $group = $service->create([
            'name' => 'تحدي الأبطال',
        ], creatorPersonId: $this->creator->id);

        $service->addMember($group, $this->member1->id, 'member', shouldNotify: false);
        $service->addMember($group, $this->member2->id, 'admin', shouldNotify: false); // Member2 is admin

        // Creator leaves the group
        $result = $service->leaveGroup($group, $this->creator->id);

        $this->assertTrue($result['success']);
        $this->assertFalse($result['group_deleted']);

        // Since member2 had 'admin' role, member2 should be prioritized as successor
        $this->assertEquals($this->member2->id, $result['new_owner_person_id']);
        $this->assertEquals($this->member2->id, $group->fresh()->created_by_person_id);
        $this->assertFalse($group->fresh()->hasMember($this->creator->id));
        $this->assertTrue($group->fresh()->isAdmin($this->member2->id));
    }

    public function test_sole_creator_leaving_deletes_group(): void
    {
        /** @var WalkingGroupService $service */
        $service = $this->app->make(WalkingGroupService::class);

        $group = $service->create([
            'name' => 'تحدي فردي مؤقت',
        ], creatorPersonId: $this->creator->id);

        $result = $service->leaveGroup($group, $this->creator->id);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['group_deleted']);
        $this->assertNull($result['new_owner_person_id']);
        $this->assertSoftDeleted('walking_groups', ['id' => $group->id]);
    }

    public function test_api_leave_endpoint_authenticates_and_leaves_group(): void
    {
        /** @var WalkingGroupService $service */
        $service = $this->app->make(WalkingGroupService::class);

        $group = $service->create([
            'name' => 'تحدي الشباب',
        ], creatorPersonId: $this->creator->id);

        $service->addMember($group, $this->member1->id, 'member', shouldNotify: false);

        $token = SiteMobileAccessToken::issueIdentityToken($this->member1->id, 'whatsapp')['token'];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept'        => 'application/json',
        ])->postJson(route('api.v1.walking.groups.leave', $group->id));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.group_id', $group->id)
            ->assertJsonPath('data.group_deleted', false);

        $this->assertFalse($group->fresh()->hasMember($this->member1->id));
    }

    public function test_api_remove_member_self_delegates_to_leave_and_transfers_ownership(): void
    {
        $ultramsgMock = Mockery::mock(UltramsgService::class);
        $ultramsgMock->shouldReceive('sendText')->andReturn(['success' => true]);
        $this->app->instance(UltramsgService::class, $ultramsgMock);

        /** @var WalkingGroupService $service */
        $service = $this->app->make(WalkingGroupService::class);

        $group = $service->create([
            'name' => 'تحدي القيادة',
        ], creatorPersonId: $this->creator->id);

        $service->addMember($group, $this->member1->id, 'member', shouldNotify: false);

        $token = SiteMobileAccessToken::issueIdentityToken($this->creator->id, 'whatsapp')['token'];

        // Calling DELETE /api/v1/walking/groups/{group}/members/{creatorId} from creator's own session
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept'        => 'application/json',
        ])->deleteJson(route('api.v1.walking.groups.members.remove', [
            'group'          => $group->id,
            'targetPersonId' => $this->creator->id,
        ]));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.new_owner_person_id', $this->member1->id);

        $this->assertEquals($this->member1->id, $group->fresh()->created_by_person_id);
    }
    public function test_dashboard_group_ranks_only_members_with_steps_during_the_challenge(): void
    {
        $today = Carbon::today('Asia/Riyadh');
        $service = $this->app->make(WalkingGroupService::class);
        $group = $service->create([
            'name' => 'تحدي المشي',
            'start_date' => $today->copy()->subDay()->toDateString(),
            'end_date' => $today->toDateString(),
        ], creatorPersonId: $this->creator->id);
        $service->addMember($group, $this->member1->id, shouldNotify: false);
        $service->addMember($group, $this->member2->id, shouldNotify: false);

        WalkingRecord::create([
            'person_id' => $this->member1->id,
            'date' => $today->copy()->subDays(2)->toDateString(),
            'steps' => 500,
        ]);
        WalkingRecord::create([
            'person_id' => $this->member1->id,
            'date' => $today->toDateString(),
            'steps' => 0,
        ]);

        $url = route('dashboard.walking.groups.show', $group);
        $this->actingAs($this->adminUser)->getJson($url)
            ->assertOk()
            ->assertJsonCount(0, 'leaderboard.top')
            ->assertJsonPath('leaderboard.pagination.total', 0)
            ->assertJsonCount(3, 'members');

        WalkingRecord::create([
            'person_id' => $this->creator->id,
            'date' => $today->toDateString(),
            'steps' => 1,
        ]);

        $response = $this->getJson($url)
            ->assertOk()
            ->assertJsonCount(1, 'leaderboard.top')
            ->assertJsonPath('leaderboard.pagination.total', 1)
            ->assertJsonPath('leaderboard.top.0.person_id', $this->creator->id)
            ->assertJsonPath('leaderboard.top.0.rank', 1)
            ->assertJsonPath('leaderboard.top.0.total_steps', 1);

        $this->assertEqualsCanonicalizing(
            [$this->creator->id, $this->member1->id, $this->member2->id],
            array_column($response->json('members'), 'person_id'),
        );
    }

    public function test_api_show_group_returns_members_and_invite_code(): void
    {
        /** @var WalkingGroupService $service */
        $service = $this->app->make(WalkingGroupService::class);

        $group = $service->create([
            'name'        => 'تحدي اللياقة العام',
            'is_public'   => true,
            'target_steps'=> 80000,
        ], creatorPersonId: $this->creator->id);

        $service->addMember($group, $this->member1->id, 'member', shouldNotify: false);

        $token = SiteMobileAccessToken::issueIdentityToken($this->creator->id, 'whatsapp')['token'];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept'        => 'application/json',
        ])->getJson(route('api.v1.walking.groups.show', $group->id));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $group->id)
            ->assertJsonPath('data.member_count', 2)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'invite_code',
                    'member_count',
                    'members' => [
                        '*' => [
                            'id',
                            'person_id',
                            'role',
                            'person' => [
                                'id',
                                'name',
                            ],
                        ],
                    ],
                ],
            ]);

        $this->assertNotEmpty($response->json('data.invite_code'));
        $this->assertCount(2, $response->json('data.members'));
    }
}

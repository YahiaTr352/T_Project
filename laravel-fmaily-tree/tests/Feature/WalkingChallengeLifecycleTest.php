<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\User;
use App\Models\WalkingGroup;
use App\Models\WalkingGroupMember;
use App\Services\SiteMobileAccessToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WalkingChallengeLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Person $creator;
    protected Person $member;
    protected string $creatorToken;

    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Support\Facades\Queue::fake();
        config(['site.password_protection_enabled' => false]);

        // Permissions
        $viewPerm   = Permission::firstOrCreate(['name' => 'walking-program.view', 'guard_name' => 'web']);
        $updatePerm = Permission::firstOrCreate(['name' => 'walking-program.update', 'guard_name' => 'web']);
        $deletePerm = Permission::firstOrCreate(['name' => 'walking-program.delete', 'guard_name' => 'web']);

        $this->adminUser = User::factory()->create();
        $this->adminUser->givePermissionTo([$viewPerm, $updatePerm, $deletePerm]);

        $this->creator = Person::create([
            'first_name' => 'سعد',
            'last_name'  => 'السريع',
            'gender'     => 'male',
        ]);

        $this->member = Person::create([
            'first_name' => 'محمد',
            'last_name'  => 'السريع',
            'gender'     => 'male',
        ]);

        $tokenData = SiteMobileAccessToken::issue(60, [
            'person_id'   => $this->creator->id,
            'auth_method' => 'whatsapp',
        ]);
        $this->creatorToken = $tokenData['token'];
    }

    public function test_active_and_running_scope_excludes_expired_and_completed_challenges(): void
    {
        // 1. Active & running (end_date in future)
        $runningGroup = WalkingGroup::create([
            'name'                 => 'تحدي نشط ساري',
            'status'               => 'active',
            'start_date'           => now()->subDays(2)->toDateString(),
            'end_date'             => now()->addDays(5)->toDateString(),
            'is_public'            => true,
            'created_by_person_id' => $this->creator->id,
        ]);

        // 2. Expired challenge (status is active but end_date was yesterday)
        $expiredGroup = WalkingGroup::create([
            'name'                 => 'تحدي منتهي التاريخ',
            'status'               => 'active',
            'start_date'           => now()->subDays(10)->toDateString(),
            'end_date'             => now()->subDay()->toDateString(),
            'is_public'            => true,
            'created_by_person_id' => $this->creator->id,
        ]);

        // 3. Completed challenge
        $completedGroup = WalkingGroup::create([
            'name'                 => 'تحدي مكتمل',
            'status'               => 'completed',
            'start_date'           => now()->subDays(10)->toDateString(),
            'end_date'             => now()->toDateString(),
            'is_public'            => true,
            'created_by_person_id' => $this->creator->id,
        ]);

        // Scope Running: only $runningGroup
        $running = WalkingGroup::running()->pluck('id');
        $this->assertTrue($running->contains($runningGroup->id));
        $this->assertFalse($running->contains($expiredGroup->id));
        $this->assertFalse($running->contains($completedGroup->id));

        // Scope Completed: both $expiredGroup and $completedGroup
        $completed = WalkingGroup::completed()->pluck('id');
        $this->assertFalse($completed->contains($runningGroup->id));
        $this->assertTrue($completed->contains($expiredGroup->id));
        $this->assertTrue($completed->contains($completedGroup->id));
    }

    public function test_api_index_filters_running_challenges_by_default(): void
    {
        // Running
        $running = WalkingGroup::create([
            'name'                 => 'تحدي الممشى النشط',
            'status'               => 'active',
            'start_date'           => now()->toDateString(),
            'end_date'             => now()->addDays(7)->toDateString(),
            'is_public'            => true,
            'created_by_person_id' => $this->creator->id,
        ]);

        // Expired
        $expired = WalkingGroup::create([
            'name'                 => 'تحدي منتهي',
            'status'               => 'active',
            'start_date'           => now()->subDays(10)->toDateString(),
            'end_date'             => now()->subDays(2)->toDateString(),
            'is_public'            => true,
            'created_by_person_id' => $this->creator->id,
        ]);

        // Request with default status (active)
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->creatorToken,
            'Accept'        => 'application/json',
        ])->getJson('/api/v1/walking/groups');

        $response->assertOk();
        $groupNames = collect($response->json('data.data'))->pluck('name');

        $this->assertTrue($groupNames->contains('تحدي الممشى النشط'));
        $this->assertFalse($groupNames->contains('تحدي منتهي'));

        // Request specifically for completed status
        $completedResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->creatorToken,
            'Accept'        => 'application/json',
        ])->getJson('/api/v1/walking/groups?status=completed');

        $completedResponse->assertOk();
        $completedNames = collect($completedResponse->json('data.data'))->pluck('name');
        $this->assertTrue($completedNames->contains('تحدي منتهي'));
        $this->assertFalse($completedNames->contains('تحدي الممشى النشط'));
    }

    public function test_creator_can_soft_delete_and_restore_group_via_api(): void
    {
        $group = WalkingGroup::create([
            'name'                 => 'تحدي للحذف والاسترجاع',
            'status'               => 'active',
            'is_public'            => true,
            'created_by_person_id' => $this->creator->id,
        ]);

        WalkingGroupMember::create([
            'walking_group_id' => $group->id,
            'person_id'        => $this->creator->id,
            'role'             => 'admin',
            'status'           => 'accepted',
        ]);

        // 1. Soft Delete
        $deleteRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->creatorToken,
            'Accept'        => 'application/json',
        ])->deleteJson("/api/v1/walking/groups/{$group->id}");

        $deleteRes->assertOk();
        $this->assertSoftDeleted('walking_groups', ['id' => $group->id]);

        // 2. Restore
        $restoreRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->creatorToken,
            'Accept'        => 'application/json',
        ])->postJson("/api/v1/walking/groups/{$group->id}/restore");

        $restoreRes->assertOk();
        $this->assertNotSoftDeleted('walking_groups', ['id' => $group->id]);
    }

    public function test_dashboard_can_restore_and_force_delete_group(): void
    {
        $group = WalkingGroup::create([
            'name'                 => 'تحدي الداشبورد المحذوف',
            'status'               => 'active',
            'is_public'            => true,
            'created_by_person_id' => $this->creator->id,
        ]);

        // Soft delete the group
        $group->delete();
        $this->assertSoftDeleted('walking_groups', ['id' => $group->id]);

        // Restore via Dashboard AJAX
        $restoreRes = $this->actingAs($this->adminUser)
            ->postJson(route('dashboard.walking.groups.restore', $group->id));

        $restoreRes->assertOk();
        $this->assertNotSoftDeleted('walking_groups', ['id' => $group->id]);

        // Force delete via Dashboard AJAX
        $forceRes = $this->actingAs($this->adminUser)
            ->deleteJson(route('dashboard.walking.groups.force-delete', $group->id));

        $forceRes->assertOk();
        $this->assertDatabaseMissing('walking_groups', ['id' => $group->id]);
    }

    public function test_dashboard_active_tab_resolution(): void
    {
        // 1. When group_status is requested, tab should be tab-groups
        $resGroups = $this->actingAs($this->adminUser)
            ->get(route('dashboard.walking.index', ['group_status' => 'trashed']));
        $resGroups->assertOk();
        $resGroups->assertViewHas('activeTab', 'tab-groups');

        // 2. When record filters are requested, tab should be tab-records
        $resRecords = $this->actingAs($this->adminUser)
            ->get(route('dashboard.walking.index', ['date_from' => '2026-01-01']));
        $resRecords->assertOk();
        $resRecords->assertViewHas('activeTab', 'tab-records');

        // 3. When explicit active_tab is requested, it should be honored
        $resDevices = $this->actingAs($this->adminUser)
            ->get(route('dashboard.walking.index', ['active_tab' => 'tab-devices']));
        $resDevices->assertOk();
        $resDevices->assertViewHas('activeTab', 'tab-devices');
    }

    public function test_dashboard_can_bulk_destroy_restore_and_force_delete_groups(): void
    {
        $group1 = WalkingGroup::create([
            'name'                 => 'تحدي جماعي 1',
            'status'               => 'active',
            'is_public'            => true,
            'created_by_person_id' => $this->creator->id,
        ]);

        $group2 = WalkingGroup::create([
            'name'                 => 'تحدي جماعي 2',
            'status'               => 'active',
            'is_public'            => true,
            'created_by_person_id' => $this->creator->id,
        ]);

        $ids = [$group1->id, $group2->id];

        // 1. Bulk Soft Delete
        $destroyRes = $this->actingAs($this->adminUser)
            ->postJson(route('dashboard.walking.groups.bulk-destroy'), ['ids' => $ids]);
        $destroyRes->assertOk();
        $destroyRes->assertJsonFragment(['success' => true, 'count' => 2]);
        $this->assertSoftDeleted('walking_groups', ['id' => $group1->id]);
        $this->assertSoftDeleted('walking_groups', ['id' => $group2->id]);

        // 2. Bulk Restore from Trash
        $restoreRes = $this->actingAs($this->adminUser)
            ->postJson(route('dashboard.walking.groups.bulk-restore'), ['ids' => $ids]);
        $restoreRes->assertOk();
        $restoreRes->assertJsonFragment(['success' => true, 'count' => 2]);
        $this->assertNotSoftDeleted('walking_groups', ['id' => $group1->id]);
        $this->assertNotSoftDeleted('walking_groups', ['id' => $group2->id]);

        // 3. Bulk Force Delete
        $forceRes = $this->actingAs($this->adminUser)
            ->postJson(route('dashboard.walking.groups.bulk-force-delete'), ['ids' => $ids]);
        $forceRes->assertOk();
        $forceRes->assertJsonFragment(['success' => true, 'count' => 2]);
        $this->assertDatabaseMissing('walking_groups', ['id' => $group1->id]);
        $this->assertDatabaseMissing('walking_groups', ['id' => $group2->id]);
    }

    public function test_dashboard_can_bulk_archive_groups(): void
    {
        $group1 = WalkingGroup::create([
            'name'                 => 'تحدي أرشفة 1',
            'status'               => 'active',
            'is_public'            => true,
            'created_by_person_id' => $this->creator->id,
        ]);

        $group2 = WalkingGroup::create([
            'name'                 => 'تحدي أرشفة 2',
            'status'               => 'active',
            'is_public'            => true,
            'created_by_person_id' => $this->creator->id,
        ]);

        $ids = [$group1->id, $group2->id];

        $archiveRes = $this->actingAs($this->adminUser)
            ->postJson(route('dashboard.walking.groups.bulk-archive'), ['ids' => $ids]);

        $archiveRes->assertOk();
        $archiveRes->assertJsonFragment(['success' => true, 'count' => 2]);

        $this->assertEquals('archived', $group1->fresh()->status);
        $this->assertEquals('archived', $group2->fresh()->status);
    }
}

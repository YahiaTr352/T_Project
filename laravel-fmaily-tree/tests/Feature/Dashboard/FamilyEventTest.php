<?php

namespace Tests\Feature\Dashboard;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\FamilyEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class FamilyEventTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['site.password_protection_enabled' => false]);
        $this->withoutMiddleware(VerifyCsrfToken::class);

        // Create permissions
        $viewPerm   = Permission::firstOrCreate(['name' => 'councils.view', 'guard_name' => 'web']);
        $createPerm = Permission::firstOrCreate(['name' => 'councils.create', 'guard_name' => 'web']);
        $updatePerm = Permission::firstOrCreate(['name' => 'councils.update', 'guard_name' => 'web']);
        $deletePerm = Permission::firstOrCreate(['name' => 'councils.delete', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo([$viewPerm, $createPerm, $updatePerm, $deletePerm]);
    }

    public function test_guest_is_redirected_from_events_index(): void
    {
        $response = $this->get(route('dashboard.events.index'));
        $response->assertRedirect('/auth/login');
    }

    public function test_unauthorized_user_cannot_access_events_index(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard.events.index'));
        $response->assertForbidden();
    }

    public function test_authorized_user_can_view_events_index_with_statistics(): void
    {
        FamilyEvent::factory()->upcoming()->create(['title' => 'لقاء العيد']);
        FamilyEvent::factory()->past()->create(['title' => 'اجتماع قديم']);

        $response = $this->actingAs($this->admin)->get(route('dashboard.events.index'));

        $response->assertOk();
        $response->assertSee('إدارة مناسبات العائلة');
        $response->assertSee('لقاء العيد');
        $response->assertSee('إجمالي المناسبات');
    }

    public function test_filtering_and_searching_events(): void
    {
        FamilyEvent::factory()->create([
            'title' => 'حفل تخرج الرياض',
            'city' => 'الرياض',
            'is_active' => true,
        ]);

        FamilyEvent::factory()->create([
            'title' => 'لقاء جدة السنوي',
            'city' => 'جدة',
            'is_active' => false,
        ]);

        // Filter by active status
        $responseActive = $this->actingAs($this->admin)
            ->get(route('dashboard.events.index', ['status' => 'active']));
        $responseActive->assertOk();
        $responseActive->assertSee('حفل تخرج الرياض');
        $responseActive->assertDontSee('لقاء جدة السنوي');

        // Search by query
        $responseSearch = $this->actingAs($this->admin)
            ->get(route('dashboard.events.index', ['search' => 'تخرج']));
        $responseSearch->assertOk();
        $responseSearch->assertSee('حفل تخرج الرياض');
        $responseSearch->assertDontSee('لقاء جدة السنوي');
    }

    public function test_can_create_new_event(): void
    {
        $payload = [
            'title'          => 'ملتقى الشباب السنوي',
            'description'    => '<p>تفاصيل الملتقى والبرنامج</p>',
            'city'           => 'الدمام',
            'location_name'  => 'قاعة الأندلس',
            'location'       => 'https://maps.google.com/?q=26.4207,50.0888',
            'event_date'     => now()->addDays(10)->format('Y-m-d\TH:i'),
            'show_countdown' => '1',
            'is_active'      => '1',
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('dashboard.events.store'), $payload);

        $response->assertRedirect(route('dashboard.events.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('family_events', [
            'title'          => 'ملتقى الشباب السنوي',
            'city'           => 'الدمام',
            'location_name'  => 'قاعة الأندلس',
            'show_countdown' => true,
            'is_active'      => true,
        ]);
    }

    public function test_create_event_validation_fails_for_missing_required_fields(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('dashboard.events.store'), [
                'title' => '',
            ]);

        $response->assertSessionHasErrors(['title', 'event_date']);
    }

    public function test_can_update_event(): void
    {
        $event = FamilyEvent::factory()->create([
            'title' => 'عنوان قديم',
            'city'  => 'الرياض',
        ]);

        $payload = [
            'title'          => 'عنوان معدل للمناسبة',
            'description'    => '<p>وصف محدث</p>',
            'city'           => 'مكة المكرمة',
            'location_name'  => 'فندق الساعة',
            'location'       => 'https://maps.google.com/?q=21.4225,39.8262',
            'event_date'     => now()->addDays(5)->format('Y-m-d\TH:i'),
            'show_countdown' => '0',
            'is_active'      => '1',
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('dashboard.events.update', $event), $payload);

        $response->assertRedirect(route('dashboard.events.index'));
        $response->assertSessionHas('success');

        $event->refresh();
        $this->assertEquals('عنوان معدل للمناسبة', $event->title);
        $this->assertEquals('مكة المكرمة', $event->city);
        $this->assertFalse($event->show_countdown);
    }

    public function test_can_delete_event(): void
    {
        $event = FamilyEvent::factory()->create();

        $response = $this->actingAs($this->admin)
            ->delete(route('dashboard.events.destroy', $event));

        $response->assertRedirect(route('dashboard.events.index'));
        $this->assertDatabaseMissing('family_events', ['id' => $event->id]);
    }

    public function test_can_toggle_active_status_via_ajax(): void
    {
        $event = FamilyEvent::factory()->create(['is_active' => true]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('dashboard.events.toggle-active', $event));

        $response->assertOk();
        $response->assertJson([
            'success'   => true,
            'is_active' => false,
        ]);

        $event->refresh();
        $this->assertFalse($event->is_active);
    }

    public function test_can_toggle_countdown_status_via_ajax(): void
    {
        $event = FamilyEvent::factory()->create(['show_countdown' => false]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('dashboard.events.toggle-countdown', $event));

        $response->assertOk();
        $response->assertJson([
            'success'        => true,
            'show_countdown' => true,
        ]);

        $event->refresh();
        $this->assertTrue($event->show_countdown);
    }

    public function test_can_duplicate_event(): void
    {
        $event = FamilyEvent::factory()->create([
            'title' => 'الاجتماع الدوري',
            'city'  => 'الرياض',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('dashboard.events.duplicate', $event));

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('family_events', [
            'title' => 'الاجتماع الدوري (نسخة)',
            'city'  => 'الرياض',
        ]);
    }

    public function test_can_reorder_events_via_ajax(): void
    {
        $event1 = FamilyEvent::factory()->create(['display_order' => 1]);
        $event2 = FamilyEvent::factory()->create(['display_order' => 2]);
        $event3 = FamilyEvent::factory()->create(['display_order' => 3]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('dashboard.events.reorder'), [
                'orders' => [$event3->id, $event1->id, $event2->id],
            ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertEquals(1, $event3->fresh()->display_order);
        $this->assertEquals(2, $event1->fresh()->display_order);
        $this->assertEquals(3, $event2->fresh()->display_order);
    }

    public function test_can_perform_bulk_actions(): void
    {
        $e1 = FamilyEvent::factory()->create(['is_active' => true]);
        $e2 = FamilyEvent::factory()->create(['is_active' => true]);

        // Bulk deactivate
        $responseDeactivate = $this->actingAs($this->admin)
            ->postJson(route('dashboard.events.bulk-action'), [
                'action' => 'deactivate',
                'ids'    => [$e1->id, $e2->id],
            ]);

        $responseDeactivate->assertOk();
        $this->assertFalse($e1->fresh()->is_active);
        $this->assertFalse($e2->fresh()->is_active);

        // Bulk delete
        $responseDelete = $this->actingAs($this->admin)
            ->postJson(route('dashboard.events.bulk-action'), [
                'action' => 'delete',
                'ids'    => [$e1->id, $e2->id],
            ]);

        $responseDelete->assertOk();
        $this->assertDatabaseMissing('family_events', ['id' => $e1->id]);
        $this->assertDatabaseMissing('family_events', ['id' => $e2->id]);
    }

    public function test_model_accessors_and_helpers(): void
    {
        $upcomingEvent = FamilyEvent::factory()->make([
            'event_date' => now()->addDays(5),
            'is_active'  => true,
        ]);

        $this->assertTrue($upcomingEvent->is_upcoming);
        $this->assertFalse($upcomingEvent->is_past);
        $this->assertEquals('قادمة', $upcomingEvent->status_badge['label']);
        $this->assertNotEmpty($upcomingEvent->formatted_date_arabic);
        $this->assertNotEmpty($upcomingEvent->time_remaining);
    }
}

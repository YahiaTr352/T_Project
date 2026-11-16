<?php

namespace Tests\Feature\Dashboard;

use App\Models\ContactMessage;
use App\Models\Person;
use App\Models\User;
use App\Services\ContactMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ContactMessageDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Queue::fake();
        config(['site.password_protection_enabled' => false]);
    }

    public function test_contact_message_full_name_accessor_uses_person_full_name(): void
    {
        $grandfather = Person::create([
            'first_name' => 'إبراهيم',
            'gender'     => 'male',
        ]);

        $father = Person::create([
            'first_name' => 'خالد',
            'gender'     => 'male',
            'parent_id'  => $grandfather->id,
        ]);

        $person = Person::create([
            'first_name' => 'عبدالله',
            'gender'     => 'male',
            'parent_id'  => $father->id,
        ]);

        $message = ContactMessage::create([
            'person_id'  => $person->id,
            'name'       => null,
            'phone'      => '0555555555',
            'type'       => 'problem',
            'message'    => 'نص رسالة تجريبية',
            'status'     => 'pending',
            'source'     => 'app',
        ]);

        // Person's full_name
        $this->assertEquals('عبدالله خالد الإبراهيم', $person->full_name);

        // ContactMessage's full_name and sender_display_name
        $this->assertEquals('عبدالله خالد الإبراهيم', $message->full_name);
        $this->assertEquals('عبدالله خالد الإبراهيم', $message->sender_display_name);
    }

    public function test_contact_message_full_name_accessor_for_guest(): void
    {
        $message = ContactMessage::create([
            'person_id'  => null,
            'name'       => 'سعد بن فهد الزائر',
            'phone'      => '0501112233',
            'type'       => 'inquiry',
            'message'    => 'استفسار من زائر',
            'status'     => 'pending',
            'source'     => 'web',
        ]);

        $this->assertEquals('سعد بن فهد الزائر', $message->full_name);
        $this->assertEquals('سعد بن فهد الزائر', $message->sender_display_name);
    }

    public function test_dashboard_contact_messages_index_renders_full_name(): void
    {
        $perm = Permission::firstOrCreate(['name' => 'site-content.view', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->givePermissionTo($perm);

        $grandfather = Person::create([
            'first_name' => 'محمد',
            'gender'     => 'male',
        ]);

        $father = Person::create([
            'first_name' => 'عبدالعزيز',
            'gender'     => 'male',
            'parent_id'  => $grandfather->id,
        ]);

        $person = Person::create([
            'first_name' => 'سلطان',
            'gender'     => 'male',
            'parent_id'  => $father->id,
        ]);

        ContactMessage::create([
            'person_id'  => $person->id,
            'name'       => null,
            'phone'      => '0551234567',
            'type'       => 'suggestion',
            'message'    => 'مقترح تطويري لتطبيق العائلة',
            'status'     => 'pending',
            'source'     => 'app',
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard.contact-messages.index'));

        $response->assertOk();
        $response->assertSee('قائمة الرسائل والبلاغات الواردة');
        $response->assertSee('المرسل (الاسم الكامل)');
        $response->assertSee('سلطان عبدالعزيز المحمد');
        $response->assertSee('عضو موثق في الشجرة');
    }

    public function test_dashboard_contact_messages_show_page_renders_full_name(): void
    {
        $perm = Permission::firstOrCreate(['name' => 'site-content.view', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->givePermissionTo($perm);

        $grandfather = Person::create([
            'first_name' => 'سريع',
            'gender'     => 'male',
        ]);

        $person = Person::create([
            'first_name' => 'حمد',
            'gender'     => 'male',
            'parent_id'  => $grandfather->id,
        ]);

        $msg = ContactMessage::create([
            'person_id'  => $person->id,
            'name'       => null,
            'phone'      => '0507778899',
            'type'       => 'problem',
            'message'    => 'تفاصيل بلاغ تجريبي',
            'status'     => 'pending',
            'source'     => 'app',
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard.contact-messages.show', $msg));

        $response->assertOk();
        $response->assertSee('حمد السريع');
        $response->assertSee('عضو موثق في الشجرة');
    }

    public function test_contact_message_service_stores_person_full_name(): void
    {
        $grandfather = Person::create([
            'first_name' => 'ناصر',
            'gender'     => 'male',
        ]);

        $person = Person::create([
            'first_name' => 'فيصل',
            'gender'     => 'male',
            'parent_id'  => $grandfather->id,
            'phone'      => '0559988776',
        ]);

        $service = app(ContactMessageService::class);
        $request = Request::create('/api/v1/contact-messages', 'POST');
        $request->setUserResolver(fn() => null);

        // Simulate viewerPersonId returning this person's ID
        $identityResolver = $this->createMock(\App\Services\SiteIdentityResolver::class);
        $identityResolver->method('viewerPersonId')->willReturn($person->id);

        $serviceWithMock = new ContactMessageService(
            $identityResolver,
            app(\App\Services\AdminAlertDispatcherService::class)
        );

        $stored = $serviceWithMock->storeMessage($request, [
            'type'    => 'problem',
            'message' => 'بلاغ تجريبي',
            'source'  => 'app',
        ]);

        $this->assertEquals('فيصل الناصر', $stored->name);
        $this->assertEquals('فيصل الناصر', $stored->full_name);
    }
}

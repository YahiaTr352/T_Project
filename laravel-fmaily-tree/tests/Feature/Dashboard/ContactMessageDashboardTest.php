<?php

namespace Tests\Feature\Dashboard;

use App\Jobs\SendExpoPushNotificationJob;
use App\Models\ContactMessage;
use App\Models\Notification;
use App\Models\Person;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\AdminAlertDispatcherService;
use App\Services\Auth\WhatsappPersonLookup;
use App\Services\ContactMessageService;
use App\Services\ExpoNotificationService;
use App\Services\SiteMobileAccessToken;
use App\Support\NotificationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ContactMessageDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        NotificationSettings::forgetCache();
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
            app(AdminAlertDispatcherService::class),
            app(WhatsappPersonLookup::class),
        );

        $stored = $serviceWithMock->storeMessage($request, [
            'type'    => 'problem',
            'message' => 'بلاغ تجريبي',
            'source'  => 'app',
        ]);

        $this->assertEquals('فيصل الناصر', $stored->name);
        $this->assertEquals('فيصل الناصر', $stored->full_name);
    }

    public function test_dashboard_sends_private_suggestion_reply_to_verified_member_without_whatsapp(): void
    {
        config(['invitations.delivery_enabled' => false]);
        $this->mock(AdminAlertDispatcherService::class)->shouldReceive('sendWhatsAppToAdmins')->once();
        $member = Person::create(['first_name' => 'محمد', 'gender' => 'male']);
        $phone = '0501234567';
        $member->contactAccounts()->create(['type' => 'phone', 'value' => $phone]);
        $other = Person::create(['first_name' => 'أحمد', 'gender' => 'male']);
        $device = UserDevice::create(['person_id' => $member->id, 'expo_push_token' => 'ExpoPushToken[member]']);
        UserDevice::create(['person_id' => $other->id, 'expo_push_token' => 'ExpoPushToken[other]']);

        $token = SiteMobileAccessToken::issueIdentityToken($member->id, 'face')['token'];
        $memberHeaders = ['Authorization' => 'Bearer '.$token];
        $submitted = $this->postJson(route('api.v1.contact-messages.store'), [
            'type' => 'suggestion',
            'message' => 'مقترح لتحدي المشي',
            'phone' => $phone,
        ], $memberHeaders)->assertCreated();
        $suggestion = ContactMessage::findOrFail($submitted->json('data.id'));
        $this->assertEquals($member->id, $suggestion->person_id);
        $this->assertSame($phone, $suggestion->phone);

        $admin = User::factory()->create();
        foreach (['site-content.view', 'site-content.update', 'notifications.view'] as $permission) {
            $admin->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }

        $showUrl = route('dashboard.contact-messages.show', $suggestion);
        $replyUrl = route('dashboard.contact-messages.reply', $suggestion);
        $this->actingAs($admin)->get($showUrl)->assertOk()
            ->assertSee('إرسال الرد')->assertDontSee('https://wa.me/0501234567');
        $this->get(route('dashboard.contact-messages.index'))->assertOk()->assertDontSee('https://wa.me/0501234567');
        $this->patch(route('dashboard.contact-messages.status', $suggestion), [
            'status' => 'in_progress',
            'admin_notes' => 'للمتابعة داخلياً',
        ])->assertRedirect($showUrl);
        $this->assertDatabaseCount('notifications', 0);
        $this->post($replyUrl, ['reply' => '  '])->assertSessionHasErrors('reply');

        $reply = 'شكراً، سنراجع اقتراحك حول تحدي المشي. {name}';
        $this->post($replyUrl, ['reply' => $reply])->assertRedirect($showUrl);
        $this->post($replyUrl, ['reply' => $reply])->assertRedirect($showUrl);
        $notice = Notification::sole();
        $recipient = $notice->recipients()->sole();
        $this->assertEquals($member->id, $recipient->person_id);
        $this->assertSame($reply, $notice->body);
        $this->assertSame('للمتابعة داخلياً', $suggestion->fresh()->admin_notes);
        $this->get($showUrl)->assertOk()->assertSee($reply);
        $this->get(route('dashboard.notifications.logs.show', $notice))->assertNotFound();

        Queue::assertPushed(SendExpoPushNotificationJob::class, 1);
        $job = Queue::pushed(SendExpoPushNotificationJob::class)->sole();
        $this->assertSame($device->expo_push_token, $job->expoPushToken);
        $this->assertSame($notice->id, $job->data['notification_id']);
        $this->assertSame('افتح التطبيق لقراءة رد الإدارة.', $job->body);

        $targetUrl = '/api/notifications?notification_id='.$notice->id;
        $this->getJson($targetUrl, $memberHeaders)->assertOk()->assertJsonPath('data.data.0.body', $reply);
        $this->getJson('/api/notifications/unread-count', $memberHeaders)->assertJsonPath('data.unread_count', 1);
        $otherToken = SiteMobileAccessToken::issueIdentityToken($other->id, 'face')['token'];
        $this->flushSession();
        $this->getJson($targetUrl, ['Authorization' => 'Bearer '.$otherToken])->assertOk()->assertJsonCount(0, 'data.data');
        $this->flushSession();
        $this->getJson($targetUrl.'&phone='.$phone)->assertOk()->assertJsonCount(0, 'data.data');
        $this->getJson('/api/notifications/unread-count?phone='.$phone)->assertJsonPath('data.unread_count', 0);
        $this->postJson('/api/notifications/read-all', ['phone' => $phone])->assertJsonPath('data.updated_rows', 0);
        $this->postJson('/api/notifications/delete-multiple', ['ids' => [$notice->id], 'phone' => $phone])
            ->assertJsonPath('data.deleted_count', 0);
        $this->getJson($targetUrl, $memberHeaders)->assertJsonPath('data.data.0.read', false);

        $expo = $this->mock(ExpoNotificationService::class);
        $expo->shouldReceive('send')->once()->with($job->expoPushToken, $job->title, $job->body, $job->data)
            ->andReturn(['success' => true, 'sent_count' => 1]);
        $job->handle($expo);
        $device->update(['person_id' => $other->id]);
        $job->handle($expo);

        $recipient->delete();
        $this->flushSession();
        $this->getJson($targetUrl)->assertOk()->assertJsonCount(0, 'data.data');
        $this->getJson($targetUrl, $memberHeaders)->assertOk()->assertJsonCount(0, 'data.data');
    }

    public function test_dashboard_reply_requires_permission_and_suggestion_without_registered_whatsapp(): void
    {
        $member = Person::create(['first_name' => 'عبدالله', 'gender' => 'male']);
        $member->contactAccounts()->create(['type' => 'whatsapp', 'value' => '0505555555']);
        $registered = ContactMessage::create(['person_id' => $member->id, 'type' => 'suggestion']);
        $guest = ContactMessage::create(['type' => 'suggestion', 'phone' => '0501112233']);
        $otherType = ContactMessage::create(['person_id' => $member->id, 'type' => 'problem']);
        $admin = User::factory()->create();
        $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'site-content.view', 'guard_name' => 'web']));

        $this->actingAs($admin)->post(route('dashboard.contact-messages.reply', $registered), ['reply' => 'رد'])
            ->assertForbidden();
        $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'site-content.update', 'guard_name' => 'web']));
        $this->get(route('dashboard.contact-messages.show', $registered))->assertOk()
            ->assertSee('https://wa.me/966505555555')->assertDontSee('إرسال الرد');
        $this->get(route('dashboard.contact-messages.show', $guest))->assertOk()
            ->assertSee('https://wa.me/0501112233')->assertDontSee('إرسال الرد');

        foreach ([$registered, $guest, $otherType] as $message) {
            $this->post(route('dashboard.contact-messages.reply', $message), ['reply' => 'رد'])->assertSessionHasErrors('reply');
        }

        $this->assertDatabaseCount('notifications', 0);
        Queue::assertNotPushed(SendExpoPushNotificationJob::class);
    }

    public function test_reply_remains_in_app_inbox_without_a_registered_device(): void
    {
        $member = Person::create(['first_name' => 'صالح', 'gender' => 'male']);
        $suggestion = ContactMessage::create(['person_id' => $member->id, 'type' => 'suggestion']);
        $admin = User::factory()->create();
        $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'site-content.update', 'guard_name' => 'web']));

        $this->actingAs($admin)->post(route('dashboard.contact-messages.reply', $suggestion), ['reply' => 'وصلنا مقترحك'])
            ->assertRedirect(route('dashboard.contact-messages.show', $suggestion));

        Queue::assertNotPushed(SendExpoPushNotificationJob::class);
        $token = SiteMobileAccessToken::issueIdentityToken($member->id, 'face')['token'];
        $this->getJson('/api/notifications', ['Authorization' => 'Bearer '.$token])->assertOk()
            ->assertJsonPath('data.data.0.body', 'وصلنا مقترحك');
    }

    public function test_member_without_registered_phone_never_displays_or_stores_submitted_placeholder_phone(): void
    {
        $this->mock(AdminAlertDispatcherService::class)->shouldReceive('sendWhatsAppToAdmins')->once();
        $member = Person::create(['first_name' => 'صالح', 'gender' => 'male']);
        $placeholderPhone = $member->resolveQuizPhone();
        $oldSuggestion = ContactMessage::create([
            'person_id' => $member->id,
            'phone' => $placeholderPhone,
            'type' => 'suggestion',
            'message' => 'اقتراح سابق',
        ]);
        $this->assertNull($oldSuggestion->sender_display_phone);

        $token = SiteMobileAccessToken::issueIdentityToken($member->id, 'face')['token'];
        $submitted = $this->postJson(route('api.v1.contact-messages.store'), [
            'phone' => $placeholderPhone,
            'type' => 'suggestion',
            'message' => 'اقتراح جديد',
        ], ['Authorization' => 'Bearer '.$token])->assertCreated();
        $this->assertNull(ContactMessage::findOrFail($submitted->json('data.id'))->phone);
        $this->flushSession();

        $admin = User::factory()->create();
        $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'dashboard.view', 'guard_name' => 'web']));
        $this->actingAs($admin)->get(route('dashboard.contact-messages.index'))->assertOk()
            ->assertSee('لا يوجد رقم مسجّل')->assertDontSee($placeholderPhone);
        $this->get(route('dashboard.contact-messages.show', $oldSuggestion))->assertOk()
            ->assertSee('لا يوجد رقم مسجّل')->assertSee('إرسال الرد')->assertDontSee($placeholderPhone);
        $this->get(route('dashboard.contact-messages.index', ['search' => $placeholderPhone]))->assertOk()
            ->assertViewHas('messages', fn ($messages) => $messages->isEmpty());
    }

    public function test_member_contact_phone_is_used_for_storage_display_and_dashboard_search(): void
    {
        $this->mock(AdminAlertDispatcherService::class)->shouldReceive('sendWhatsAppToAdmins')->once();
        $member = Person::create(['first_name' => 'محمد', 'gender' => 'male']);
        $phone = '0553216789';
        $contact = $member->contactAccounts()->create(['type' => 'phone', 'value' => $phone]);
        $member->contactAccounts()->create(['type' => 'whatsapp', 'value' => '   ']);
        $oldSuggestion = ContactMessage::create([
            'person_id' => $member->id, 'phone' => '0500000001', 'type' => 'suggestion',
        ]);
        $this->assertSame($phone, $oldSuggestion->sender_display_phone);
        $token = SiteMobileAccessToken::issueIdentityToken($member->id, 'face')['token'];
        $submitted = $this->postJson(route('api.v1.contact-messages.store'), [
            'phone' => '0500000001', 'type' => 'suggestion', 'message' => 'اقتراح جديد',
        ], ['Authorization' => 'Bearer '.$token])->assertCreated();
        $this->assertSame($phone, ContactMessage::findOrFail($submitted->json('data.id'))->phone);
        $this->flushSession();

        $admin = User::factory()->create();
        $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'dashboard.view', 'guard_name' => 'web']));
        $this->actingAs($admin)->get(route('dashboard.contact-messages.index', ['search' => $phone]))
            ->assertOk()->assertSee($phone)->assertDontSee('0500000001')
            ->assertViewHas('messages', fn ($messages) => $messages->total() === 2);
        $this->get(route('dashboard.contact-messages.show', $oldSuggestion))->assertOk()->assertSee($phone);

        $contact->update(['type' => 'whatsapp']);
        $this->get(route('dashboard.contact-messages.show', $oldSuggestion))->assertOk()
            ->assertSee('https://wa.me/966553216789')->assertDontSee('إرسال الرد');
        $contact->delete();
        $this->assertNull($oldSuggestion->fresh()->sender_display_phone);

        $guest = ContactMessage::create(['phone' => $phone, 'type' => 'suggestion']);
        $this->assertSame($phone, $guest->sender_display_phone);
        $this->get(route('dashboard.contact-messages.index', ['search' => $phone]))->assertOk()
            ->assertViewHas('messages', fn ($messages) => $messages->total() === 1 && $messages->first()->is($guest));
    }
}

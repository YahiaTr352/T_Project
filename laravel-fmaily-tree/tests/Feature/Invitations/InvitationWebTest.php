<?php

namespace Tests\Feature\Invitations;

use App\Jobs\SendExpoPushNotificationJob;
use App\Jobs\SendWhatsAppInvitationJob;
use App\Models\Notification;
use App\Models\NotificationGroup;
use App\Models\Person;
use App\Models\PersonContactAccount;
use App\Models\UserDevice;
use App\Services\SiteMobileAccessToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InvitationWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['site.password_protection_enabled' => false]);
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }

    public function test_guest_or_pin_sees_locked_page(): void
    {
        $this->get('/invitations')
            ->assertOk()
            ->assertSee('مجموعات الدعوات والإرسال');

        $this->withSession([
            'site_password_verified' => true,
            'site_auth_method' => 'pin',
        ])->get('/invitations')
            ->assertOk()
            ->assertSee('رمز الدخول');
    }

    public function test_pin_cannot_open_groups(): void
    {
        $this->withSession([
            'site_password_verified' => true,
            'site_auth_method' => 'pin',
        ])->get('/invitations/groups')
            ->assertRedirect(route('invitations.index'));
    }

    public function test_identity_can_open_dashboard_and_create_group(): void
    {
        $me = Person::factory()->create(['first_name' => 'سعد', 'gender' => 'male', 'death_date' => null]);
        $withWa = Person::factory()->create(['first_name' => 'فهد', 'gender' => 'male', 'death_date' => null]);
        $this->giveWhatsapp($withWa);

        $this->actingAsIdentity($me)
            ->get('/invitations')
            ->assertOk()
            ->assertSee('لوحة الدعوات')
            ->assertSee('سعد');

        $this->actingAsIdentity($me)
            ->post('/invitations/groups', [
                'name' => 'أعمامي',
                'description' => 'للتواصل السريع',
                'person_ids' => [$withWa->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('notification_groups', [
            'name' => 'أعمامي',
            'person_id' => $me->id,
        ]);
    }

    public function test_cannot_add_person_without_whatsapp(): void
    {
        $me = Person::factory()->create(['gender' => 'male', 'death_date' => null]);
        $noWa = Person::factory()->create(['first_name' => 'بدون', 'gender' => 'male', 'death_date' => null]);

        $this->actingAsIdentity($me)
            ->post('/invitations/groups', [
                'name' => 'تجربة',
                'person_ids' => [$noWa->id],
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('notification_groups', ['name' => 'تجربة']);
    }

    public function test_send_dispatches_whatsapp_and_push_jobs(): void
    {
        Queue::fake();
        config(['invitations.delivery_enabled' => true]);

        $me = Person::factory()->create(['gender' => 'male', 'death_date' => null]);
        $target = Person::factory()->create(['first_name' => 'خالد', 'gender' => 'male', 'death_date' => null]);
        $this->giveWhatsapp($target);

        UserDevice::create([
            'person_id' => $target->id,
            'expo_push_token' => 'ExponentPushToken[test-token]',
            'os_platform' => null,
        ]);

        $group = NotificationGroup::create([
            'name' => 'مجموعة خالد',
            'person_id' => $me->id,
        ]);
        $group->persons()->attach($target->id, ['sort_order' => 1]);

        $this->actingAsIdentity($me)
            ->post('/invitations/send', [
                'channels' => ['whatsapp', 'push'],
                'recipient_type' => 'group',
                'group_id' => $group->id,
                'title' => 'تجربة',
                'body' => 'مرحبا {name} من {sender_name}',
                'exclude_deceased' => 'on',
            ])
            ->assertRedirect(route('invitations.logs'));

        Queue::assertPushed(SendWhatsAppInvitationJob::class);
        Queue::assertPushed(SendExpoPushNotificationJob::class);
        Queue::assertNotPushed(\App\Jobs\NotifyEditorsOfPendingInvitationsJob::class);
    }

    public function test_send_page_uses_sila_tree_markup_and_patronymic_search(): void
    {
        $me = Person::factory()->create(['gender' => 'male', 'death_date' => null]);
        $father = Person::factory()->create([
            'first_name' => 'ناصر',
            'gender' => 'male',
            'parent_id' => null,
            'from_outside_the_family' => false,
            'death_date' => null,
        ]);
        $son = Person::factory()->create([
            'first_name' => 'ممدوح',
            'gender' => 'male',
            'parent_id' => $father->id,
            'from_outside_the_family' => false,
            'death_date' => null,
        ]);
        $this->giveWhatsapp($son);

        $this->actingAsIdentity($me)
            ->get('/invitations/send')
            ->assertOk()
            ->assertSee('inv-sila-tree', false)
            ->assertSee('tree-horizontal', false)
            ->assertSee('tree-column', false)
            ->assertSee('ممدوح بن ناصر');

        $this->actingAsIdentity($me)
            ->getJson('/invitations/persons-search?q='.urlencode('ممدوح بن ناصر'))
            ->assertOk()
            ->assertJsonPath('success', true);

        $ids = collect($this->actingAsIdentity($me)
            ->getJson('/invitations/persons-search?q='.urlencode('ممدوح بن ناصر'))
            ->json('persons'))->pluck('id')->all();

        $this->assertContains($son->id, $ids);
    }

    public function test_send_rejects_all_recipient_type(): void
    {
        $me = Person::factory()->create(['gender' => 'male', 'death_date' => null]);

        $this->actingAsIdentity($me)
            ->post('/invitations/send', [
                'channels' => ['whatsapp'],
                'recipient_type' => 'all',
                'body' => 'مرحبا',
            ])
            ->assertSessionHasErrors('recipient_type');
    }

    public function test_invitations_index_does_not_crash_with_null_os_platform(): void
    {
        $me = Person::factory()->create(['first_name' => 'سعد', 'gender' => 'male', 'death_date' => null]);
        UserDevice::create([
            'person_id' => $me->id,
            'expo_push_token' => 'ExponentPushToken[other]',
            'os_platform' => null,
        ]);

        $this->actingAsIdentity($me)
            ->get('/invitations')
            ->assertOk();
    }

    public function test_web_and_mobile_share_invitation_responses_without_sending(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        config(['invitations.delivery_enabled' => false]);

        $adult = ['birth_date' => '1990-01-01', 'death_date' => null];
        $sender = Person::factory()->createQuietly($adult);
        $recipient = Person::factory()->createQuietly($adult + ['first_name' => 'مستلم تجريبي']);
        $other = Person::factory()->createQuietly($adult + ['first_name' => 'مستلم آخر']);
        $waiting = Person::factory()->createQuietly($adult);
        $invitation = Notification::create([
            'person_id' => $sender->id, 'title' => 'دعوة اختبار', 'status' => 'sent', 'channels' => ['whatsapp'],
        ]);
        $recipientRow = $invitation->recipients()->create(['person_id' => $recipient->id]);
        $invitation->recipients()->create(['person_id' => $other->id]);
        $invitation->recipients()->create(['person_id' => $waiting->id]);
        $invitation->recipients()->create(['recipient_name' => 'ضيف تجريبي']);

        $incoming = route('invitations.logs', ['direction' => 'incoming', 'page' => 1]);
        $replyUrl = route('invitations.logs.respond', ['notification' => $invitation, 'page' => 1]);
        $apiUrl = "/api/v1/invitations/logs/{$invitation->id}";

        $this->actingAsIdentity($sender)->put($replyUrl, ['response' => 'accepted'])->assertForbidden();

        $this->actingAsIdentity($recipient)->get($incoming)->assertOk()
            ->assertSee('قبول الدعوة')->assertSee('رفض الدعوة')->assertSee('بانتظار ردك')
            ->assertSee('بعد التأكيد، لا يمكنك تغيير ردك.')
            ->assertDontSee('مستلم آخر');
        $this->from($incoming)->put($replyUrl, ['response' => 'invalid'])->assertSessionHasErrors('response');
        $this->put($replyUrl, ['response' => 'accepted'])->assertRedirect($incoming)
            ->assertSessionHas('responded_invitation_id', $invitation->id);
        $this->get($incoming)->assertOk()->assertSee('قبلت الدعوة')->assertDontSee('قبول الدعوة');
        $this->getJson("{$apiUrl}?direction=incoming")->assertOk()
            ->assertJsonPath('data.notification.invitation_response', 'accepted');
        $this->putJson("{$apiUrl}/response", ['response' => 'declined'])->assertConflict();

        $this->actingAsIdentity($other)->putJson("{$apiUrl}/response", ['response' => 'declined'])->assertOk();
        $this->put($replyUrl, ['response' => 'accepted'])->assertRedirect($incoming)
            ->assertSessionHas('response_error');
        $this->get($incoming)->assertOk()->assertSee('رفضت الدعوة')
            ->assertDontSee('قبلت الدعوة')->assertDontSee('قبول الدعوة');
        $this->assertSame('accepted', $recipientRow->fresh()->invitation_response);

        $outgoing = $this->actingAsIdentity($sender)->get(route('invitations.logs'))->assertOk()
            ->assertSee('موافق على الحضور')->assertSee('معتذر عن الحضور')
            ->assertSee('خارج التطبيق')->assertDontSee('قبول الدعوة');
        foreach (['موافق', 'معتذر', 'بانتظار الرد'] as $label) {
            $this->assertMatchesRegularExpression('/'.$label.'<\/dt>\s*<dd[^>]*>1<\/dd>/u', $outgoing->getContent());
        }

        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    private function actingAsIdentity(Person $person)
    {
        return $this->withSession([
            'site_password_verified' => true,
            'site_auth_method' => 'whatsapp',
            'site_person_id' => $person->id,
        ]);
    }

    private function giveWhatsapp(Person $person): void
    {
        PersonContactAccount::create([
            'person_id' => $person->id,
            'type' => 'whatsapp',
            'value' => '9665'.str_pad((string) $person->id, 8, '0', STR_PAD_LEFT),
        ]);
    }
}

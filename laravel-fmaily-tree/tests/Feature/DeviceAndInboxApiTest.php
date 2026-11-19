<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Person;
use App\Models\UserDevice;
use App\Services\SiteMobileAccessToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceAndInboxApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['site.password_protection_enabled' => false]);
    }

    public function test_identity_token_links_device_without_phone(): void
    {
        $person = Person::factory()->create(['gender' => 'male', 'death_date' => null]);
        $token = 'ExponentPushToken[identity-link]';

        $this->withHeaders($this->identityHeaders($person))
            ->postJson('/api/devices/register', [
                'expo_push_token' => $token,
                'os_platform' => 'ios',
            ])
            ->assertOk()
            ->assertJsonPath('data.is_linked', true)
            ->assertJsonPath('data.linked_person_id', $person->id)
            ->assertJsonPath('data.linked_via', 'identity');

        $this->assertDatabaseHas('user_devices', [
            'expo_push_token' => $token,
            'person_id' => $person->id,
        ]);
    }

    public function test_pin_token_does_not_link_device(): void
    {
        $pin = SiteMobileAccessToken::issue()['token'];

        $this->withHeader('Authorization', 'Bearer '.$pin)
            ->postJson('/api/devices/register', [
                'expo_push_token' => 'ExponentPushToken[pin-only]',
            ])
            ->assertOk()
            ->assertJsonPath('data.is_linked', false)
            ->assertJsonPath('data.linked_via', 'none');

        $this->assertDatabaseHas('user_devices', [
            'expo_push_token' => 'ExponentPushToken[pin-only]',
            'person_id' => null,
        ]);
    }

    public function test_reregister_without_phone_keeps_existing_person_id(): void
    {
        $person = Person::factory()->create(['gender' => 'male', 'death_date' => null]);
        $token = 'ExponentPushToken[keep-link]';

        UserDevice::create([
            'person_id' => $person->id,
            'expo_push_token' => $token,
            'os_platform' => 'android',
        ]);

        $this->postJson('/api/devices/register', [
            'expo_push_token' => $token,
            'os_platform' => 'android',
            'app_version' => '2.0.0',
        ])
            ->assertOk()
            ->assertJsonPath('data.is_linked', true)
            ->assertJsonPath('data.linked_person_id', $person->id)
            ->assertJsonPath('data.linked_via', 'none');

        $this->assertDatabaseHas('user_devices', [
            'expo_push_token' => $token,
            'person_id' => $person->id,
            'app_version' => '2.0.0',
        ]);
    }

    public function test_identity_inbox_sees_targeted_and_broadcast_guest_sees_broadcast_only(): void
    {
        $me = Person::factory()->create(['gender' => 'male', 'death_date' => null]);
        $other = Person::factory()->create(['gender' => 'male', 'death_date' => null]);

        $targeted = Notification::create([
            'user_id' => null,
            'title' => 'دعوة لك',
            'body' => 'محتوى موجّه',
            'status' => 'sending',
        ]);
        $targeted->recipients()->create(['person_id' => $me->id]);

        $forOther = Notification::create([
            'user_id' => null,
            'title' => 'دعوة لشخص آخر',
            'body' => 'لا تظهر للزائر ولا لي إن لم أكن المستلم',
            'status' => 'sent',
            'sent_at' => now(),
        ]);
        $forOther->recipients()->create(['person_id' => $other->id]);

        $broadcast = Notification::create([
            'user_id' => null,
            'title' => 'خبر عام',
            'body' => 'للجميع',
            'status' => 'published',
        ]);

        $mine = $this->withHeaders($this->identityHeaders($me))
            ->getJson('/api/notifications')
            ->assertOk()
            ->json('data.data');

        $mineIds = collect($mine)->pluck('id')->all();
        $this->assertContains($targeted->id, $mineIds);
        $this->assertContains($broadcast->id, $mineIds);
        $this->assertNotContains($forOther->id, $mineIds);

        $this->flushSession()->flushHeaders();

        $guest = $this->getJson('/api/notifications')
            ->assertOk()
            ->json('data.data');

        $guestIds = collect($guest)->pluck('id')->all();
        $this->assertContains($broadcast->id, $guestIds);
        $this->assertNotContains($targeted->id, $guestIds);
        $this->assertNotContains($forOther->id, $guestIds);
    }

    public function test_unread_count_uses_identity(): void
    {
        $me = Person::factory()->create(['gender' => 'male', 'death_date' => null]);
        $notification = Notification::create([
            'user_id' => null,
            'title' => 'غير مقروء',
            'body' => 'نص',
            'status' => 'sent',
            'sent_at' => now(),
        ]);
        $notification->recipients()->create(['person_id' => $me->id]);

        $this->withHeaders($this->identityHeaders($me))
            ->getJson('/api/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1);

        $this->flushSession()->flushHeaders();

        $this->getJson('/api/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);
    }

    public function test_inbox_exposes_invitation_response_only_to_the_verified_eligible_recipient(): void
    {
        $adult = ['birth_date' => '1990-01-01', 'death_date' => null];
        $recipient = Person::factory()->createQuietly($adult);
        $sender = Person::factory()->createQuietly($adult);
        $minor = Person::factory()->createQuietly(['birth_date' => now()->subYears(16), 'death_date' => null]);
        $recipient->contactAccounts()->create(['type' => 'phone', 'value' => '966500000123']);

        $invitation = Notification::create(['person_id' => $sender->id, 'status' => 'sent']);
        $invitation->recipients()->create(['person_id' => $recipient->id]);
        $invitation->recipients()->create(['person_id' => $minor->id]);
        $sending = Notification::create(['person_id' => $sender->id, 'status' => 'sending']);
        $sending->recipients()->create(['person_id' => $recipient->id]);
        $ordinary = Notification::create(['status' => 'sent']);
        $ordinary->recipients()->create(['person_id' => $recipient->id]);

        $this->withHeaders($this->identityHeaders($recipient));
        $inbox = collect($this->getJson('/api/notifications')->assertOk()->json('data.data'))->keyBy('id');
        $this->assertSame(['id' => $invitation->id, 'response' => null], $inbox[$invitation->id]['invitation']);
        $this->assertNull($inbox[$sending->id]['invitation']);
        $this->assertNull($inbox[$ordinary->id]['invitation']);

        $this->putJson("/api/v1/invitations/logs/{$invitation->id}/response", ['response' => 'accepted'])->assertOk();
        $inbox = collect($this->getJson('/api/notifications')->assertOk()->json('data.data'))->keyBy('id');
        $this->assertSame('accepted', $inbox[$invitation->id]['invitation']['response']);

        $this->flushSession()->flushHeaders();
        $unverified = collect($this->getJson('/api/notifications?phone=966500000123')->assertOk()->json('data.data'))->keyBy('id');
        $this->assertNull($unverified[$invitation->id]['invitation']);

        $minorInbox = collect($this->withHeaders($this->identityHeaders($minor))
            ->getJson('/api/notifications')->assertOk()->json('data.data'))->keyBy('id');
        $this->assertNull($minorInbox[$invitation->id]['invitation']);
    }

    /**
     * @return array<string, string>
     */
    private function identityHeaders(Person $person): array
    {
        $token = SiteMobileAccessToken::issueIdentityToken($person->id, 'whatsapp')['token'];

        return [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ];
    }
}

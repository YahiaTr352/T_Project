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

        $this->flushHeaders();

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

        $this->flushHeaders();

        $this->getJson('/api/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);
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

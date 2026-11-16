<?php

namespace Tests\Feature\Invitations;

use App\Jobs\SendWhatsAppInvitationJob;
use App\Jobs\NotifyEditorsOfPendingInvitationsJob;
use App\Models\Notification;
use App\Models\Person;
use App\Services\Invitations\InvitationMessageService;
use App\Services\SiteMobileAccessToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InvitationExternalAndPlaceholderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['site.password_protection_enabled' => false]);
    }

    protected function identityHeaders(Person $person): array
    {
        $token = SiteMobileAccessToken::issueIdentityToken($person->id, 'whatsapp')['token'];

        return [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ];
    }

    public function test_message_service_replaces_placeholders_for_person(): void
    {
        $service = app(InvitationMessageService::class);
        $person = Person::factory()->createQuietly([
            'first_name' => 'خالد',
            'last_name' => 'السريع',
            'gender' => 'male',
        ]);

        $body = 'السلام عليكم ورحمة الله، مرحباً {name}، نتشرف بدعوتك {full_name}';
        $result = $service->replacePlaceholders($body, $person);

        $this->assertStringContainsString('مرحباً خالد', $result);
        $this->assertStringNotContainsString('{name}', $result);
    }

    public function test_message_service_uses_dignified_guest_fallback_when_person_is_null(): void
    {
        $service = app(InvitationMessageService::class);

        // When person is null (e.g. PIN 112 / guest / broadcast)
        $body = 'مرحباً {name}، نرحب بك {full_name} في تطبيق العائلة';
        $result = $service->replacePlaceholders($body, null);

        $this->assertStringContainsString('مرحباً ضيفنا العزيز', $result);
        $this->assertStringContainsString('زائرنا الكريم', $result);
        $this->assertStringNotContainsString('{name}', $result);
        $this->assertStringNotContainsString('name', $result);
    }

    public function test_message_service_cleans_loose_name_words(): void
    {
        $service = app(InvitationMessageService::class);

        $body = 'مرحباً name، أهلاً بك في العائلة';
        $result = $service->replacePlaceholders($body, null);

        $this->assertStringContainsString('مرحباً ضيفنا العزيز', $result);
        $this->assertStringNotContainsString('name', $result);
    }

    public function test_send_invitation_to_external_guest(): void
    {
        Queue::fake();
        config(['invitations.delivery_enabled' => true]);

        $sender = Person::factory()->createQuietly([
            'first_name' => 'سلطان',
            'gender' => 'male',
            'death_date' => null,
        ]);

        $headers = $this->identityHeaders($sender);

        $response = $this->withHeaders($headers)->postJson('/api/v1/invitations/send', [
            'channels' => ['whatsapp'],
            'recipient_type' => 'external',
            'external_name' => 'أبو فهد الشمري',
            'external_phone' => '0501234567',
            'title' => 'دعوة زواج خاصة',
            'body' => 'السلام عليكم يا {name}، نتشرف بحضوركم لمناسبتنا',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('success', true)
            ->assertJsonPath('data.notification.whatsapp_jobs', 1)
            ->assertJsonPath('data.notification.status', 'sending');

        $notificationId = $response->json('data.notification.id');
        $notification = Notification::find($notificationId);

        $this->assertNotNull($notification);
        $this->assertNull($notification->sent_at);
        $this->assertNull($notification->approved_by);
        $this->assertEquals('external', $notification->targeting_filters['recipient_type']);

        $recipients = $notification->recipients;
        $this->assertCount(1, $recipients);
        $this->assertNull($recipients[0]->person_id);
        $this->assertEquals('أبو فهد الشمري', $recipients[0]->recipient_name);
        $this->assertEquals('966501234567', $recipients[0]->recipient_phone);

        Queue::assertPushed(SendWhatsAppInvitationJob::class, function ($job) {
            return $job->toNumber === '966501234567'
                && str_contains($job->messageBody, 'أبو فهد الشمري')
                && $job->personId === null
                && $job->recipientName === 'أبو فهد الشمري';
        });
        Queue::assertNotPushed(NotifyEditorsOfPendingInvitationsJob::class);
    }

    public function test_delivery_disabled_saves_invitation_without_dispatching_jobs(): void
    {
        Queue::fake();
        config(['invitations.delivery_enabled' => false]);
        $sender = Person::factory()->createQuietly(['death_date' => null, 'birth_date' => '1990-01-01']);

        $response = $this->withHeaders($this->identityHeaders($sender))
            ->postJson('/api/v1/invitations/send', [
                'channels' => ['whatsapp'],
                'recipient_type' => 'external',
                'external_phone' => '0501234567',
                'body' => 'دعوة تجريبية',
            ])
            ->assertCreated()
            ->assertJsonPath('data.notification.status', 'delivery_disabled')
            ->assertJsonPath('data.notification.whatsapp_jobs', 0)
            ->assertJsonPath('data.notification.push_jobs', 0);

        $this->assertDatabaseHas('notifications', [
            'id' => $response->json('data.notification.id'),
            'person_id' => $sender->id,
            'status' => 'delivery_disabled',
            'sent_at' => null,
        ]);
        Queue::assertNothingPushed();
    }

    public function test_preview_external_invitation(): void
    {
        $sender = Person::factory()->createQuietly([
            'first_name' => 'سلطان',
            'gender' => 'male',
            'death_date' => null,
        ]);

        $headers = $this->identityHeaders($sender);

        $response = $this->withHeaders($headers)->postJson('/api/v1/invitations/preview', [
            'recipient_type' => 'external',
            'external_name' => 'عبدالرحمن العتيبي',
            'body' => 'أهلاً بك {name}، يسعدنا تشريفك لنا',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertStringContainsString('أهلاً بك عبدالرحمن العتيبي', $response->json('data.preview'));
    }
}

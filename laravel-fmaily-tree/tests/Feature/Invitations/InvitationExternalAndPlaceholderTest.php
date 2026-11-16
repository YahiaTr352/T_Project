<?php

namespace Tests\Feature\Invitations;

use App\Jobs\SendWhatsAppInvitationJob;
use App\Models\Notification;
use App\Models\Person;
use App\Services\Invitations\InvitationApprovalService;
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
        $response->assertJsonPath('success', true);

        $notificationId = $response->json('data.notification.id');
        $notification = Notification::find($notificationId);

        $this->assertNotNull($notification);
        $this->assertEquals('pending_approval', $notification->status);
        $this->assertEquals('external', $notification->targeting_filters['recipient_type']);

        $recipients = $notification->recipients;
        $this->assertCount(1, $recipients);
        $this->assertNull($recipients[0]->person_id);
        $this->assertEquals('أبو فهد الشمري', $recipients[0]->recipient_name);
        $this->assertEquals('966501234567', $recipients[0]->recipient_phone);

        // Test approval dispatching for external recipient
        $approvalService = app(InvitationApprovalService::class);
        $admin = \App\Models\User::factory()->createQuietly();
        $approvalResult = $approvalService->approve($notification, $admin);

        $this->assertEquals(1, $approvalResult['whatsapp_jobs']);

        Queue::assertPushed(SendWhatsAppInvitationJob::class, function ($job) {
            return $job->toNumber === '966501234567'
                && str_contains($job->messageBody, 'أبو فهد الشمري')
                && $job->personId === null
                && $job->recipientName === 'أبو فهد الشمري';
        });
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

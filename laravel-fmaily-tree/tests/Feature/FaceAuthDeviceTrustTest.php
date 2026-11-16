<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\PersonContactAccount;
use App\Models\PersonTrustedDevice;
use App\Models\User;
use App\Services\AdminAlertDispatcherService;
use App\Services\AI\EmbeddingsService;
use App\Services\FaceRecognitionServiceInterface;
use App\Services\UltramsgService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class FaceAuthDeviceTrustTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['site.password_protection_enabled' => false]);
        \Illuminate\Support\Facades\Queue::fake();

        $this->mock(EmbeddingsService::class, function ($mock) {
            $mock->shouldIgnoreMissing();
        });
    }

    protected function mockFaceRecognitionService(int $matchedPersonId, float $similarity = 95.0)
    {
        $mock = Mockery::mock(FaceRecognitionServiceInterface::class);
        $mock->shouldReceive('searchByImage')
            ->andReturn([
                [
                    'person_id' => $matchedPersonId,
                    'similarity' => $similarity,
                ],
            ]);
        $this->app->instance(FaceRecognitionServiceInterface::class, $mock);
        return $mock;
    }

    protected function mockUltramsgService(bool $success = true)
    {
        $mock = Mockery::mock(UltramsgService::class);
        $mock->shouldReceive('sendText')
            ->andReturn([
                'success' => $success,
                'id' => 'msg_test_123',
            ]);
        $this->app->instance(UltramsgService::class, $mock);
        return $mock;
    }

    protected function createValidSelfie(): UploadedFile
    {
        return UploadedFile::fake()->image('selfie.jpg', 200, 200);
    }

    public function test_trusted_device_bypasses_otp_and_logs_in_directly(): void
    {
        $person = Person::factory()->create(['first_name' => 'إبراهيم', 'last_name' => 'خالد']);
        $deviceUuid = 'device-trusted-111';

        PersonTrustedDevice::create([
            'person_id' => $person->id,
            'device_uuid' => $deviceUuid,
            'device_name' => 'iPhone',
            'device_model' => 'iPhone 15 Pro',
            'verified_at' => now(),
        ]);

        $this->mockFaceRecognitionService($person->id, 96.0);

        $response = $this->postJson('/api/v1/auth/face-verify', [
            'image' => $this->createValidSelfie(),
            'device_uuid' => $deviceUuid,
            'device_model' => 'iPhone 15 Pro',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('requires_otp', false)
            ->assertJsonPath('data.person.id', $person->id)
            ->assertJsonStructure([
                'data' => ['access_token', 'refresh_token', 'person'],
            ]);

        $this->assertDatabaseHas('person_trusted_devices', [
            'person_id' => $person->id,
            'device_uuid' => $deviceUuid,
        ]);
    }

    public function test_new_device_with_whatsapp_triggers_otp_challenge(): void
    {
        $person = Person::factory()->create(['first_name' => 'إبراهيم', 'last_name' => 'خالد']);
        PersonContactAccount::create([
            'person_id' => $person->id,
            'type' => 'whatsapp',
            'value' => '0551234567',
        ]);

        $deviceUuid = 'device-new-222';
        $this->mockFaceRecognitionService($person->id, 92.0);
        $this->mockUltramsgService(true);

        $response = $this->postJson('/api/v1/auth/face-verify', [
            'image' => $this->createValidSelfie(),
            'device_uuid' => $deviceUuid,
            'device_model' => 'Samsung S24',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('requires_otp', true)
            ->assertJsonPath('has_whatsapp', true)
            ->assertJsonPath('data.person.id', $person->id)
            ->assertJsonStructure([
                'data' => ['challenge_token', 'masked_phone', 'expires_in'],
            ]);

        $challengeToken = $response->json('data.challenge_token');
        $this->assertNotNull($challengeToken);
        $this->assertTrue(Cache::has("face_otp_challenge:{$challengeToken}"));
    }

    public function test_confirming_valid_otp_trusts_device_and_subsequent_login_requires_no_otp(): void
    {
        $person = Person::factory()->create(['first_name' => 'إبراهيم', 'last_name' => 'خالد']);
        PersonContactAccount::create([
            'person_id' => $person->id,
            'type' => 'whatsapp',
            'value' => '+966551234567',
        ]);

        $deviceUuid = 'device-verify-333';
        $this->mockFaceRecognitionService($person->id, 94.0);
        $this->mockUltramsgService(true);

        // Step 1: Initial face scan triggers OTP
        $step1 = $this->postJson('/api/v1/auth/face-verify', [
            'image' => $this->createValidSelfie(),
            'device_uuid' => $deviceUuid,
            'device_model' => 'Google Pixel 8',
        ])->assertOk();

        $challengeToken = $step1->json('data.challenge_token');
        $cached = Cache::get("face_otp_challenge:{$challengeToken}");
        $this->assertNotNull($cached);

        // Simulate OTP
        $otpCode = '123456';
        $cached['code_hash'] = hash('sha256', $otpCode);
        Cache::put("face_otp_challenge:{$challengeToken}", $cached, 300);

        // Step 2: Confirm OTP
        $confirmResponse = $this->postJson('/api/v1/auth/face-verify/confirm-otp', [
            'challenge_token' => $challengeToken,
            'otp_code' => $otpCode,
            'device_uuid' => $deviceUuid,
        ]);

        $confirmResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.person.id', $person->id)
            ->assertJsonStructure([
                'data' => ['access_token', 'refresh_token', 'person'],
            ]);

        // Device is now trusted in DB
        $this->assertDatabaseHas('person_trusted_devices', [
            'person_id' => $person->id,
            'device_uuid' => $deviceUuid,
        ]);
        $device = PersonTrustedDevice::where('person_id', $person->id)
            ->where('device_uuid', $deviceUuid)
            ->first();
        $this->assertNotNull($device->verified_at);

        // Step 3: Subsequent login with Face on same device requires NO OTP!
        $step3 = $this->postJson('/api/v1/auth/face-verify', [
            'image' => $this->createValidSelfie(),
            'device_uuid' => $deviceUuid,
        ]);

        $step3->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('requires_otp', false)
            ->assertJsonPath('data.person.id', $person->id);
    }

    public function test_confirming_invalid_otp_fails(): void
    {
        $person = Person::factory()->create();
        PersonContactAccount::create([
            'person_id' => $person->id,
            'type' => 'whatsapp',
            'value' => '+966551234567',
        ]);

        $deviceUuid = 'device-invalid-444';
        $this->mockFaceRecognitionService($person->id, 94.0);
        $this->mockUltramsgService(true);

        $step1 = $this->postJson('/api/v1/auth/face-verify', [
            'image' => $this->createValidSelfie(),
            'device_uuid' => $deviceUuid,
        ])->assertOk();

        $challengeToken = $step1->json('data.challenge_token');

        $res = $this->postJson('/api/v1/auth/face-verify/confirm-otp', [
            'challenge_token' => $challengeToken,
            'otp_code' => '000000',
            'device_uuid' => $deviceUuid,
        ]);

        $res->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_new_device_without_whatsapp_issues_grace_period_and_dispatches_admin_alert(): void
    {
        $person = Person::factory()->create(['first_name' => 'محمد', 'last_name' => 'علي']);

        $deviceUuid = 'device-no-whatsapp-555';
        $this->mockFaceRecognitionService($person->id, 95.0);

        $adminMock = Mockery::mock(AdminAlertDispatcherService::class);
        $adminMock->shouldReceive('sendWhatsAppToAdmins')
            ->once()
            ->andReturn(1);
        $this->app->instance(AdminAlertDispatcherService::class, $adminMock);

        $response = $this->postJson('/api/v1/auth/face-verify', [
            'image' => $this->createValidSelfie(),
            'device_uuid' => $deviceUuid,
            'device_model' => 'Xiaomi 13',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('requires_otp', false)
            ->assertJsonPath('whatsapp_required', true)
            ->assertJsonPath('grace_hours_remaining', 24)
            ->assertJsonPath('data.person.id', $person->id);

        $this->assertDatabaseHas('person_trusted_devices', [
            'person_id' => $person->id,
            'device_uuid' => $deviceUuid,
            'verified_at' => null,
            'admin_alerted' => true,
        ]);
    }
    public function test_resend_face_otp_and_cooldown(): void
    {
        $person = Person::factory()->create(['first_name' => 'سالم']);
        PersonContactAccount::create([
            'person_id' => $person->id,
            'type' => 'whatsapp',
            'value' => '0559988776',
        ]);

        $this->mockFaceRecognitionService($person->id, 93.0);
        $this->mockUltramsgService(true);

        $step1 = $this->postJson('/api/v1/auth/face-verify', [
            'image' => $this->createValidSelfie(),
            'device_uuid' => 'device-resend-test',
        ])->assertOk();

        $challengeToken = $step1->json('data.challenge_token');

        // Immediate resend should be blocked by 60s cooldown
        $resend1 = $this->postJson('/api/v1/auth/face-verify/resend-otp', [
            'challenge_token' => $challengeToken,
        ]);
        $resend1->assertStatus(422)
            ->assertJsonPath('success', false);

        // Fast-forward cooldown by modifying cache timestamp
        $cached = Cache::get("face_otp_challenge:{$challengeToken}");
        $cached['last_sent_at'] = now()->subSeconds(65)->timestamp;
        Cache::put("face_otp_challenge:{$challengeToken}", $cached, 300);

        // Now resend succeeds
        $resend2 = $this->postJson('/api/v1/auth/face-verify/resend-otp', [
            'challenge_token' => $challengeToken,
        ]);
        $resend2->assertOk()
            ->assertJsonPath('success', true);
    }
}
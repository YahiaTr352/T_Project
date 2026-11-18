<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\PersonContactAccount;
use App\Services\AI\EmbeddingsService;
use App\Services\FaceRecognitionServiceInterface;
use App\Services\UltramsgService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class WhatsAppOtpDeliveryToggleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['site.password_protection_enabled' => false]);
        config(['auth.child_max_age' => 15]);
        \Illuminate\Support\Facades\Queue::fake();

        $this->mock(EmbeddingsService::class, function ($mock) {
            $mock->shouldIgnoreMissing();
        });
    }

    public function test_when_otp_delivery_disabled_whatsapp_login_skips_send_and_accepts_111111(): void
    {
        config(['auth.whatsapp_otp_send_enabled' => false]);

        $person = Person::factory()->create([
            'first_name' => 'تيم',
            'last_name' => 'السريع',
            'birth_date' => '1995-01-01',
        ]);
        PersonContactAccount::create([
            'person_id' => $person->id,
            'type' => 'whatsapp',
            'value' => '+966551234567',
        ]);

        $ultramsgMock = Mockery::mock(UltramsgService::class);
        $ultramsgMock->shouldNotReceive('sendText');
        $this->app->instance(UltramsgService::class, $ultramsgMock);

        $sendRes = $this->postJson('/api/v1/auth/whatsapp/send-otp', [
            'phone' => '0551234567',
        ]);

        $sendRes->assertOk()->assertJsonPath('success', true);

        $this->postJson('/api/v1/auth/whatsapp/verify-otp', [
            'phone' => '0551234567',
            'otp_code' => '000000',
        ])->assertStatus(422)->assertJsonPath('success', false);

        $verifyRes = $this->postJson('/api/v1/auth/whatsapp/verify-otp', [
            'phone' => '0551234567',
            'otp_code' => '111111',
            'device_uuid' => 'test-device-whatsapp-disabled',
        ]);

        $verifyRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.person.id', $person->id)
            ->assertJsonStructure(['data' => ['access_token', 'refresh_token']]);
    }

    public function test_when_otp_delivery_enabled_whatsapp_login_sends_notification(): void
    {
        config(['auth.whatsapp_otp_send_enabled' => true]);

        $person = Person::factory()->create([
            'first_name' => 'يحيى',
            'last_name' => 'السريع',
            'birth_date' => '1995-01-01',
        ]);
        PersonContactAccount::create([
            'person_id' => $person->id,
            'type' => 'whatsapp',
            'value' => '+966559998877',
        ]);

        $capturedOtp = null;
        $ultramsgMock = Mockery::mock(UltramsgService::class);
        $ultramsgMock->shouldReceive('sendText')
            ->once()
            ->withArgs(function (string $to, string $body) use (&$capturedOtp) {
                if (preg_match('/(\d{6})/', $body, $m)) {
                    $capturedOtp = $m[1];
                }

                return $to === '+966559998877';
            })
            ->andReturn(['success' => true, 'id' => 'msg_otp_enabled']);
        $this->app->instance(UltramsgService::class, $ultramsgMock);

        $this->postJson('/api/v1/auth/whatsapp/send-otp', [
            'phone' => '0559998877',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertNotNull($capturedOtp);

        $this->postJson('/api/v1/auth/whatsapp/verify-otp', [
            'phone' => '0559998877',
            'otp_code' => $capturedOtp,
            'device_uuid' => 'test-device-whatsapp-enabled',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.person.id', $person->id);
    }

    public function test_when_otp_delivery_disabled_face_new_device_skips_send_and_accepts_111111(): void
    {
        config(['auth.whatsapp_otp_send_enabled' => false]);

        $person = Person::factory()->create([
            'first_name' => 'سالم',
            'last_name' => 'السريع',
            'birth_date' => '1990-01-01',
        ]);
        PersonContactAccount::create([
            'person_id' => $person->id,
            'type' => 'whatsapp',
            'value' => '+966550001122',
        ]);

        $faceMock = Mockery::mock(FaceRecognitionServiceInterface::class);
        $faceMock->shouldReceive('searchByImage')
            ->andReturn([['person_id' => $person->id, 'similarity' => 96.0]]);
        $this->app->instance(FaceRecognitionServiceInterface::class, $faceMock);

        $ultramsgMock = Mockery::mock(UltramsgService::class);
        $ultramsgMock->shouldNotReceive('sendText');
        $this->app->instance(UltramsgService::class, $ultramsgMock);

        $deviceUuid = 'face-dev-otp-disabled';
        $step1 = $this->postJson('/api/v1/auth/face-verify', [
            'image' => UploadedFile::fake()->image('selfie.jpg', 200, 200),
            'device_uuid' => $deviceUuid,
        ])->assertOk()
            ->assertJsonPath('requires_otp', true);

        $challengeToken = $step1->json('data.challenge_token');
        $this->assertTrue(Cache::has("face_otp_challenge:{$challengeToken}"));

        $this->postJson('/api/v1/auth/face-verify/confirm-otp', [
            'challenge_token' => $challengeToken,
            'otp_code' => '111111',
            'device_uuid' => $deviceUuid,
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.person.id', $person->id);
    }
}

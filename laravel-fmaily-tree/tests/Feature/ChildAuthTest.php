<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\PersonContactAccount;
use App\Models\PersonTrustedDevice;
use App\Services\AI\EmbeddingsService;
use App\Services\FaceRecognitionServiceInterface;
use App\Services\UltramsgService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery;
use Tests\TestCase;

class ChildAuthTest extends TestCase
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
                'id' => 'msg_test_child_123',
            ]);
        $this->app->instance(UltramsgService::class, $mock);
        return $mock;
    }

    protected function createValidSelfie(): UploadedFile
    {
        $width = 200;
        $height = 200;
        $image = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($image, 255, 255, 255);
        imagefilledrectangle($image, 0, 0, $width, $height, $white);

        $tmpFile = tempnam(sys_get_temp_dir(), 'child_selfie_') . '.jpg';
        imagejpeg($image, $tmpFile, 90);
        imagedestroy($image);

        return new UploadedFile($tmpFile, 'child_selfie.jpg', 'image/jpeg', null, true);
    }

    public function test_child_parent_lookup_returns_only_children_15_or_younger()
    {
        $father = Person::factory()->create([
            'first_name' => 'محمد',
            'last_name' => 'الغامدي',
            'gender' => 'male',
        ]);
        PersonContactAccount::create([
            'person_id' => $father->id,
            'type' => 'whatsapp',
            'value' => '0551112233',
        ]);

        // طفل 7 سنوات (مؤهل)
        $childYoung = Person::factory()->create([
            'first_name' => 'أحمد',
            'last_name' => 'الغامدي',
            'parent_id' => $father->id,
            'birth_date' => now()->subYears(7)->format('Y-m-d'),
        ]);

        // ابن 15 سنة (مؤهل الآن)
        $teenager = Person::factory()->create([
            'first_name' => 'خالد',
            'last_name' => 'الغامدي',
            'parent_id' => $father->id,
            'birth_date' => now()->subYears(15)->subMonths(1)->format('Y-m-d'),
        ]);

        // ابن 17 سنة (غير مؤهل)
        $older = Person::factory()->create([
            'first_name' => 'سلطان',
            'last_name' => 'الغامدي',
            'parent_id' => $father->id,
            'birth_date' => now()->subYears(17)->format('Y-m-d'),
        ]);

        // ابن بدون تاريخ ميلاد (غير مؤهل)
        $noDob = Person::factory()->create([
            'first_name' => 'فيصل',
            'last_name' => 'الغامدي',
            'parent_id' => $father->id,
            'birth_date' => null,
        ]);

        $response = $this->postJson('/api/v1/auth/child/parent-lookup', [
            'phone' => '0551112233',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.children');

        $returnedChildIds = collect($response->json('data.children'))->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$childYoung->id, $teenager->id], $returnedChildIds);
    }

    public function test_send_and_verify_child_whatsapp_otp_trusts_device()
    {
        $this->mockUltramsgService(true);

        $mother = Person::factory()->create([
            'first_name' => 'فاطمة',
            'last_name' => 'الغامدي',
            'gender' => 'female',
        ]);
        PersonContactAccount::create([
            'person_id' => $mother->id,
            'type' => 'whatsapp',
            'value' => '0559998877',
        ]);

        $child = Person::factory()->create([
            'first_name' => 'سارة',
            'last_name' => 'الغامدي',
            'mother_id' => $mother->id,
            'birth_date' => now()->subYears(6)->format('Y-m-d'),
        ]);

        // 1. إرسال الكود
        $sendRes = $this->postJson('/api/v1/auth/child/send-otp', [
            'parent_phone' => '0559998877',
            'child_id' => $child->id,
        ]);

        $sendRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.child_id', $child->id);

        // استخراج الكود من الكاش للاختبار
        $normPhone = '+966559998877';
        $cacheKey = 'whatsapp_child_otp:' . md5($normPhone . ':' . $child->id);
        $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);
        $this->assertNotNull($cached);
        $otpCode = $cached['code'];

        // 2. التحقق من الكود وتوثيق الجهاز
        $deviceUuid = 'child-ipad-uuid-999';
        $verifyRes = $this->postJson('/api/v1/auth/child/verify-otp', [
            'parent_phone' => '0559998877',
            'child_id' => $child->id,
            'otp_code' => $otpCode,
            'device_uuid' => $deviceUuid,
            'device_name' => 'iPad Air',
        ]);

        $verifyRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.person.id', $child->id)
            ->assertJsonPath('data.person.is_child', true);

        // التأكد من حفظ الجهاز في person_trusted_devices للطفل
        $this->assertDatabaseHas('person_trusted_devices', [
            'person_id' => $child->id,
            'device_uuid' => $deviceUuid,
        ]);
    }

    public function test_child_face_auth_routes_otp_to_father_first_on_new_device()
    {
        $this->mockUltramsgService(true);

        $father = Person::factory()->create(['first_name' => 'عبدالله']);
        PersonContactAccount::create([
            'person_id' => $father->id,
            'type' => 'whatsapp',
            'value' => '0501111222',
        ]);

        $mother = Person::factory()->create(['first_name' => 'نورة']);
        PersonContactAccount::create([
            'person_id' => $mother->id,
            'type' => 'whatsapp',
            'value' => '0503333444',
        ]);

        $child = Person::factory()->create([
            'first_name' => 'عمر',
            'parent_id' => $father->id,
            'mother_id' => $mother->id,
            'birth_date' => now()->subYears(5)->format('Y-m-d'),
        ]);

        $this->mockFaceRecognitionService($child->id, 94.0);

        $response = $this->postJson('/api/v1/auth/face-verify', [
            'image' => $this->createValidSelfie(),
            'device_uuid' => 'brand-new-child-device',
            'device_name' => 'Child Tablet',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('requires_otp', true)
            ->assertJsonPath('is_child', true)
            ->assertJsonPath('guardian_relation', 'الأب')
            ->assertJsonPath('data.person.id', $child->id);
    }

    public function test_child_face_auth_routes_otp_to_mother_when_father_has_no_whatsapp()
    {
        $this->mockUltramsgService(true);

        $father = Person::factory()->create(['first_name' => 'سالم']);
        // الأب لا يملك واتساب

        $mother = Person::factory()->create(['first_name' => 'مريم']);
        PersonContactAccount::create([
            'person_id' => $mother->id,
            'type' => 'whatsapp',
            'value' => '0507777888',
        ]);

        $child = Person::factory()->create([
            'first_name' => 'يوسف',
            'parent_id' => $father->id,
            'mother_id' => $mother->id,
            'birth_date' => now()->subYears(8)->format('Y-m-d'),
        ]);

        $this->mockFaceRecognitionService($child->id, 96.0);

        $response = $this->postJson('/api/v1/auth/face-verify', [
            'image' => $this->createValidSelfie(),
            'device_uuid' => 'another-child-device',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('requires_otp', true)
            ->assertJsonPath('is_child', true)
            ->assertJsonPath('guardian_relation', 'الأم');
    }

    public function test_child_face_auth_on_trusted_device_logs_in_directly_without_otp()
    {
        $child = Person::factory()->create([
            'first_name' => 'حمزة',
            'birth_date' => now()->subYears(4)->format('Y-m-d'),
        ]);

        $trustedUuid = 'child-trusted-phone-uuid';
        PersonTrustedDevice::create([
            'person_id' => $child->id,
            'device_uuid' => $trustedUuid,
            'verified_at' => now(),
        ]);

        $this->mockFaceRecognitionService($child->id, 97.0);

        $response = $this->postJson('/api/v1/auth/face-verify', [
            'image' => $this->createValidSelfie(),
            'device_uuid' => $trustedUuid,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('requires_otp', false)
            ->assertJsonPath('data.person.id', $child->id);
    }

    public function test_standard_whatsapp_send_otp_redirects_to_parent_when_child_enters_phone()
    {
        $this->mockUltramsgService(true);

        $father = Person::factory()->create([
            'first_name' => 'عبدالعزيز',
            'last_name' => 'الغامدي',
        ]);
        PersonContactAccount::create([
            'person_id' => $father->id,
            'type' => 'whatsapp',
            'value' => '0550001111',
        ]);

        // ابن 14 سنة (طفل)
        $child = Person::factory()->create([
            'first_name' => 'بدر',
            'last_name' => 'الغامدي',
            'parent_id' => $father->id,
            'birth_date' => now()->subYears(14)->format('Y-m-d'),
        ]);
        PersonContactAccount::create([
            'person_id' => $child->id,
            'type' => 'whatsapp',
            'value' => '0558887777',
        ]);

        // الطفل يدخل رقم هاتفه هو في شاشة تسجيل الدخول العادية
        $sendRes = $this->postJson('/api/v1/auth/whatsapp/send-otp', [
            'phone' => '0558887777',
        ]);

        $sendRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_child', true)
            ->assertJsonPath('data.guardian_relation', 'الأب')
            ->assertJsonPath('data.child_name', 'بدر');

        // استخراج الكود من الكاش وتأكيده
        $normPhone = '+966558887777';
        $cacheKey = 'whatsapp_otp:' . md5($normPhone);
        $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);
        $this->assertNotNull($cached);
        $this->assertTrue($cached['is_child']);
        $otpCode = $cached['code'];

        $deviceUuid = 'child-phone-uuid-14yo';
        $verifyRes = $this->postJson('/api/v1/auth/whatsapp/verify-otp', [
            'phone' => '0558887777',
            'otp_code' => $otpCode,
            'device_uuid' => $deviceUuid,
            'device_name' => 'Bader iPhone',
        ]);

        $verifyRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_child', true)
            ->assertJsonPath('data.person.id', $child->id);

        $this->assertDatabaseHas('person_trusted_devices', [
            'person_id' => $child->id,
            'device_uuid' => $deviceUuid,
        ]);
    }

    public function test_standard_whatsapp_send_otp_routes_child_with_phone_only_contact()
    {
        $this->mockUltramsgService(true);

        $mother = Person::factory()->create([
            'first_name' => 'هند',
            'last_name' => 'الغامدي',
        ]);
        PersonContactAccount::create([
            'person_id' => $mother->id,
            'type' => 'whatsapp',
            'value' => '0554443333',
        ]);

        // ابن 11 سنة (مسجل كهاتف عادي فقط وليس واتساب)
        $child = Person::factory()->create([
            'first_name' => 'ريان',
            'last_name' => 'الغامدي',
            'mother_id' => $mother->id,
            'birth_date' => now()->subYears(11)->format('Y-m-d'),
        ]);
        PersonContactAccount::create([
            'person_id' => $child->id,
            'type' => 'phone',
            'value' => '0552221111',
        ]);

        $sendRes = $this->postJson('/api/v1/auth/whatsapp/send-otp', [
            'phone' => '0552221111',
        ]);

        $sendRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_child', true)
            ->assertJsonPath('data.guardian_relation', 'الأم')
            ->assertJsonPath('data.child_name', 'ريان');
    }

    public function test_child_face_auth_fails_cleanly_when_no_parent_has_whatsapp()
    {
        $father = Person::factory()->create(['first_name' => 'سعيد']);
        // لا يوجد واتساب للأب

        $child = Person::factory()->create([
            'first_name' => 'طلال',
            'parent_id' => $father->id,
            'birth_date' => now()->subYears(10)->format('Y-m-d'),
        ]);

        $this->mockFaceRecognitionService($child->id, 96.0);

        $response = $this->postJson('/api/v1/auth/face-verify', [
            'image' => $this->createValidSelfie(),
            'device_uuid' => 'new-untrusted-device',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.is_child', true);
    }
}

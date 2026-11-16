<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MobileAuthSession;
use App\Models\Person;
use App\Services\Auth\SingleSessionDisplacementService;
use App\Services\ExpoNotificationService;
use App\Services\SiteIdentityResolver;
use App\Services\SiteMobileAccessToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class SingleActiveSessionDisplacementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['site.password_protection_enabled' => false]);
        \Illuminate\Support\Facades\Queue::fake();

        $this->mock(\App\Services\AI\EmbeddingsService::class, function ($mock) {
            $mock->shouldIgnoreMissing();
        });
    }

    public function test_single_active_session_displaces_old_device_and_revokes_tokens(): void
    {
        $person = Person::factory()->create([
            'first_name' => 'أحمد',
            'last_name' => 'السريّع',
        ]);

        // 1. تسجيل الدخول من الجهاز الأول (Device A)
        \App\Models\UserDevice::create([
            'person_id' => $person->id,
            'expo_push_token' => 'ExponentPushToken[device_a_push_token]',
            'device_name' => 'iPhone A',
        ]);

        $refreshA = Str::random(80);
        $tokenA = SiteMobileAccessToken::issueIdentityToken($person->id, 'face', $refreshA, 'device-A');

        $this->assertTrue(SiteMobileAccessToken::valid($tokenA['token']));
        $this->assertDatabaseHas('mobile_auth_sessions', [
            'person_id' => $person->id,
            'device_uuid' => 'device-A',
        ]);

        // 2. محاكاة طرد الجلسات عند تسجيل الدخول من الجهاز الثاني (Device B)
        $expoMock = Mockery::mock(ExpoNotificationService::class);
        $expoMock->shouldReceive('send')->once()->andReturn(['success' => true]);
        $this->app->instance(ExpoNotificationService::class, $expoMock);

        $displacementService = $this->app->make(SingleSessionDisplacementService::class);
        $displacementService->displace($person->id, 'device-B', 'iPhone 15 Pro');

        // إصدار توكن الجهاز الثاني
        $refreshB = Str::random(80);
        $tokenB = SiteMobileAccessToken::issueIdentityToken($person->id, 'face', $refreshB, 'device-B');

        // 3. التحقق من إبطال الجهاز الأول A
        $this->assertFalse(SiteMobileAccessToken::valid($tokenA['token']), 'Old device token A must be invalid.');
        $this->assertDatabaseMissing('mobile_auth_sessions', [
            'person_id' => $person->id,
            'device_uuid' => 'device-A',
        ]);

        // 4. التحقق من صلاحية الجهاز الثاني B
        $this->assertTrue(SiteMobileAccessToken::valid($tokenB['token']), 'New device token B must be valid.');
        $this->assertDatabaseHas('mobile_auth_sessions', [
            'person_id' => $person->id,
            'device_uuid' => 'device-B',
        ]);

        // 5. محاولة تجديد رمز الجهاز القديم A يجب أن تفشل بـ 401
        $response = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $refreshA,
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'success' => false,
        ]);
    }

    public function test_web_sessions_are_invalidated_when_person_is_displaced(): void
    {
        $person = Person::factory()->create();

        $resolver = $this->app->make(SiteIdentityResolver::class);

        // إنشاء جلسة ويب
        $resolver->establishWebSession($person->id, 'face');

        // التحقق من أنها نشطة
        $this->assertEquals($person->id, $resolver->viewerPersonId());

        // تنفيذ الإبطال
        $resolver->invalidateWebSessionsForPerson($person->id);

        // طلب لاحق يجب أن يجد الجلسة ملغاة
        $request = \Illuminate\Http\Request::create('/api/v1/auth/me', 'GET');
        $this->assertNull($resolver->resolve($request));
    }

    public function test_current_device_is_strictly_excluded_from_displacement_push_notifications(): void
    {
        $person = Person::factory()->create([
            'first_name' => 'خالد',
            'last_name' => 'السريّع',
        ]);

        // الجهاز الأول (Device 1)
        \App\Models\UserDevice::create([
            'person_id' => $person->id,
            'expo_push_token' => 'ExponentPushToken[device_1_token]',
            'device_uuid' => 'uuid-device-1',
            'device_name' => 'iPhone 15',
        ]);

        // الجهاز الثاني (Device 2)
        \App\Models\UserDevice::create([
            'person_id' => $person->id,
            'expo_push_token' => 'ExponentPushToken[device_2_token]',
            'device_uuid' => 'uuid-device-2',
            'device_name' => 'Samsung S24',
        ]);

        // جهاز قديم بدون uuid يتبع نفس العضو
        \App\Models\UserDevice::create([
            'person_id' => $person->id,
            'expo_push_token' => 'ExponentPushToken[device_old_token]',
            'device_uuid' => null,
            'device_name' => 'Old iPad',
        ]);

        // Mock للتحقق من أن الإشعار يُرسل فقط للأجهزة الأخرى، مع استبعاد الجهاز الأول بالكامل
        $expoMock = Mockery::mock(ExpoNotificationService::class);
        $expoMock->shouldReceive('send')
            ->once()
            ->withArgs(function ($to, $title, $body, $data, $sound) {
                // يجب أن تحتوي قائمة المستلمين على أجهزة العضو الأخرى فقط
                $this->assertContains('ExponentPushToken[device_2_token]', $to);
                $this->assertContains('ExponentPushToken[device_old_token]', $to);
                // الجهاز الحالي المسجل منه الدخول يجب ألا يتلقى الإشعار إطلاقاً
                $this->assertNotContains('ExponentPushToken[device_1_token]', $to);
                // حمولة الإشعار يجب أن تحتوي على معرف الجهاز الذي قام بالطرد
                $this->assertEquals('uuid-device-1', $data['displaced_by_device']);
                $this->assertEquals('FORCE_LOGOUT', $data['type']);
                return true;
            })
            ->andReturn(['success' => true]);

        $this->app->instance(ExpoNotificationService::class, $expoMock);

        $displacementService = $this->app->make(SingleSessionDisplacementService::class);
        $displacementService->displace($person->id, 'uuid-device-1', 'iPhone 15', 'ExponentPushToken[device_1_token]');
    }
}

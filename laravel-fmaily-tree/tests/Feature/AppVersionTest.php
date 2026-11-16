<?php

namespace Tests\Feature;

use App\Models\AppVersion;
use App\Models\SystemSetting;
use App\Services\AppVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppVersionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(AppVersionService::class)->clearCache();
        SystemSetting::clearCache();
    }

    public function test_check_version_returns_up_to_date_when_version_matches_or_higher(): void
    {
        AppVersion::updateOrCreate(['platform' => 'ios'], [
            'latest_version' => '1.5.0',
            'min_supported_version' => '1.2.0',
            'is_under_maintenance' => false,
        ]);
        app(AppVersionService::class)->clearCache('ios');

        $response = $this->postJson('/api/v1/app/check-version', [
            'platform' => 'ios',
            'current_version' => '1.5.0',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.action', 'UP_TO_DATE')
            ->assertJsonPath('data.is_mandatory', false);
    }

    public function test_check_version_returns_optional_update_when_between_min_and_latest(): void
    {
        AppVersion::updateOrCreate(['platform' => 'ios'], [
            'latest_version' => '1.5.0',
            'min_supported_version' => '1.2.0',
            'is_under_maintenance' => false,
        ]);
        app(AppVersionService::class)->clearCache('ios');

        $response = $this->postJson('/api/v1/app/check-version', [
            'platform' => 'ios',
            'current_version' => '1.3.0',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.action', 'OPTIONAL_UPDATE')
            ->assertJsonPath('data.is_mandatory', false)
            ->assertJsonPath('data.latest_version', '1.5.0');
    }

    public function test_check_version_returns_force_update_when_below_min_supported(): void
    {
        AppVersion::updateOrCreate(['platform' => 'ios'], [
            'latest_version' => '1.5.0',
            'min_supported_version' => '1.2.0',
            'is_under_maintenance' => false,
        ]);
        app(AppVersionService::class)->clearCache('ios');

        $response = $this->postJson('/api/v1/app/check-version', [
            'platform' => 'ios',
            'current_version' => '1.1.0',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.action', 'FORCE_UPDATE')
            ->assertJsonPath('data.is_mandatory', true);
    }

    public function test_check_version_returns_maintenance_when_maintenance_is_active(): void
    {
        AppVersion::updateOrCreate(['platform' => 'android'], [
            'latest_version' => '1.5.0',
            'min_supported_version' => '1.2.0',
            'is_under_maintenance' => true,
            'maintenance_message_ar' => 'التطبيق يخضع للصيانة',
        ]);
        app(AppVersionService::class)->clearCache('android');

        $response = $this->postJson('/api/v1/app/check-version', [
            'platform' => 'android',
            'current_version' => '1.5.0',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.action', 'MAINTENANCE')
            ->assertJsonPath('data.is_mandatory', true);
    }

    public function test_bootstrap_settings_returns_public_metadata(): void
    {
        SystemSetting::set('app_name_ar', 'تطبيق عائلة السريع', 'general', 'text');

        $response = $this->getJson('/api/v1/app/settings');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.app_name', 'تطبيق عائلة السريع')
            ->assertJsonStructure([
                'success',
                'data' => [
                    'app_name',
                    'privacy_policy_url',
                    'terms_of_service_url',
                ],
            ]);
    }
}

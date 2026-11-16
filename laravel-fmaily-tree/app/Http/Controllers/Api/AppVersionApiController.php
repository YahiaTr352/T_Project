<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AppCheckVersionRequest;
use App\Models\SystemSetting;
use App\Services\AppVersionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppVersionApiController extends Controller
{
    public function __construct(
        protected AppVersionService $versionService
    ) {}

    /**
     * Check app version against the server matrix.
     * High performance, zero DB latency (cached forever in memory/Redis).
     */
    public function checkVersion(AppCheckVersionRequest $request): JsonResponse
    {
        $platform = (string) $request->input('platform');
        $currentVersion = (string) $request->input('current_version');
        $locale = $request->header('Accept-Language') === 'en' ? 'en' : 'ar';

        $result = $this->versionService->evaluate(
            platform: $platform,
            clientVersion: $currentVersion,
            locale: $locale
        );

        return response()->json([
            'success' => true,
            'data' => $result,
            'message' => 'App version check completed.',
            'errors' => null,
        ]);
    }

    /**
     * Get essential mobile public bootstrap settings (App names, privacy/terms URLs, support contact).
     */
    public function bootstrapSettings(Request $request): JsonResponse
    {
        $locale = $request->header('Accept-Language') === 'en' ? 'en' : 'ar';

        $data = [
            'app_name' => SystemSetting::get("app_name_{$locale}", SystemSetting::get('app_name_ar', 'عائلة السريّع')),
            'site_name' => SystemSetting::get("site_name_{$locale}", SystemSetting::get('site_name_ar', 'عائلة السريّع')),
            'tagline' => SystemSetting::get("site_description_{$locale}", SystemSetting::get('site_description_ar')),
            'support_email' => SystemSetting::get('support_email'),
            'support_phone' => SystemSetting::get('support_phone'),
            'support_whatsapp' => SystemSetting::get('support_whatsapp'),
            'privacy_policy_url' => url('/privacy-policy'),
            'terms_of_service_url' => url('/terms-of-service'),
            'about_us_url' => url('/about-us'),
            'pages_status' => [
                'about' => (bool) SystemSetting::get('page_about_enabled', true),
                'privacy' => (bool) SystemSetting::get('page_privacy_enabled', true),
                'terms' => (bool) SystemSetting::get('page_terms_enabled', true),
            ],
            'maintenance' => [
                'is_active' => (bool) SystemSetting::get('maintenance_mode_site', false),
                'message' => SystemSetting::get('maintenance_site_message'),
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'Settings retrieved successfully.',
            'errors' => null,
        ]);
    }
}

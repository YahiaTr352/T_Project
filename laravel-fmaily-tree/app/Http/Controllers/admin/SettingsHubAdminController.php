<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\AppVersion;
use App\Models\SystemSetting;
use App\Services\AppVersionService;
use App\Services\StoreSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SettingsHubAdminController extends Controller
{
    public function __construct(
        protected AppVersionService $versionService,
        protected StoreSyncService $storeSyncService
    ) {}

    /**
     * Display unified Site & App Settings Hub.
     */
    public function index(Request $request): View
    {
        $activeTab = $request->query('tab', 'versions');

        $iosVersion = AppVersion::firstOrCreate(['platform' => 'ios'], [
            'bundle_id' => 'com.alsorayia.app',
            'latest_version' => '1.2.0',
            'min_supported_version' => '1.0.0',
            'store_url' => 'https://apps.apple.com/app/id6738957876',
        ]);

        $androidVersion = AppVersion::firstOrCreate(['platform' => 'android'], [
            'bundle_id' => 'com.alsorayia.app',
            'latest_version' => '1.2.0',
            'min_supported_version' => '1.0.0',
            'store_url' => 'https://play.google.com/store/apps/details?id=com.alsorayia.app',
        ]);

        $discrepancies = $this->versionService->getAdminDiscrepancies();
        $settings = SystemSetting::getAllSettings();

        return view('dashboard.settings.index', compact(
            'activeTab',
            'iosVersion',
            'androidVersion',
            'discrepancies',
            'settings'
        ));
    }

    /**
     * Update App Versions, Store URLs, Force/Optional thresholds, and App Maintenance.
     * Supports both Simplified Human Policies (One-Click) and Advanced Manual SemVer.
     */
    public function updateVersions(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            // Policies
            'ios_update_policy' => ['nullable', 'string', 'in:optional_all,strict_latest,custom'],
            'android_update_policy' => ['nullable', 'string', 'in:optional_all,strict_latest,custom'],

            // iOS
            'ios_latest_version' => ['nullable', 'string', 'max:50'],
            'ios_min_supported_version' => ['nullable', 'string', 'max:50'],
            'ios_store_url' => ['nullable', 'url', 'max:1000'],
            'ios_bundle_id' => ['nullable', 'string', 'max:120'],
            'ios_title_ar' => ['nullable', 'string', 'max:255'],
            'ios_release_notes_ar' => ['nullable', 'string'],
            'ios_is_under_maintenance' => ['nullable', 'boolean'],
            'ios_maintenance_message_ar' => ['nullable', 'string'],

            // Android
            'android_latest_version' => ['nullable', 'string', 'max:50'],
            'android_min_supported_version' => ['nullable', 'string', 'max:50'],
            'android_store_url' => ['nullable', 'url', 'max:1000'],
            'android_bundle_id' => ['nullable', 'string', 'max:120'],
            'android_title_ar' => ['nullable', 'string', 'max:255'],
            'android_release_notes_ar' => ['nullable', 'string'],
            'android_is_under_maintenance' => ['nullable', 'boolean'],
            'android_maintenance_message_ar' => ['nullable', 'string'],
        ]);

        $ios = AppVersion::where('platform', 'ios')->first();
        $android = AppVersion::where('platform', 'android')->first();

        // 1. Calculate iOS versions based on selected policy
        $iosLatest = trim(($validated['ios_latest_version'] ?? '') ?: ($ios->store_detected_version ?: $ios->latest_version));
        $iosPolicy = $validated['ios_update_policy'] ?? 'optional_all';
        if ($iosPolicy === 'strict_latest') {
            $iosMin = $iosLatest; // Hard gate: everyone must be on latest
        } elseif ($iosPolicy === 'optional_all') {
            $iosMin = '1.0.0'; // Soft update: everyone can access, prompt optionally
        } else {
            $iosMin = trim(($validated['ios_min_supported_version'] ?? '') ?: '1.0.0');
        }

        // 2. Calculate Android versions based on selected policy
        $androidLatest = trim(($validated['android_latest_version'] ?? '') ?: ($android->store_detected_version ?: $android->latest_version));
        $androidPolicy = $validated['android_update_policy'] ?? 'optional_all';
        if ($androidPolicy === 'strict_latest') {
            $androidMin = $androidLatest;
        } elseif ($androidPolicy === 'optional_all') {
            $androidMin = '1.0.0';
        } else {
            $androidMin = trim(($validated['android_min_supported_version'] ?? '') ?: '1.0.0');
        }

        // Guard against human errors: min_supported cannot be higher than latest_version
        if (version_compare($iosMin, $iosLatest, '>')) {
            return back()->withInput()->with('error', 'خطأ في إعدادات iOS: الحد الأدنى الإجباري لا يمكن أن يكون أكبر من الإصدار الأحدث!');
        }

        if (version_compare($androidMin, $androidLatest, '>')) {
            return back()->withInput()->with('error', 'خطأ في إعدادات Android: الحد الأدنى الإجباري لا يمكن أن يكون أكبر من الإصدار الأحدث!');
        }

        // Update iOS
        $ios->update([
            'bundle_id' => ($validated['ios_bundle_id'] ?? '') ?: $ios->bundle_id,
            'latest_version' => $iosLatest,
            'min_supported_version' => $iosMin,
            'store_url' => ($validated['ios_store_url'] ?? '') ?: $ios->store_url,
            'title_ar' => ($validated['ios_title_ar'] ?? '') ?: ($ios->title_ar ?: 'تحديث جديد متاح لتطبيق العائلة'),
            'release_notes_ar' => $validated['ios_release_notes_ar'] ?? $ios->release_notes_ar,
            'is_under_maintenance' => $request->boolean('ios_is_under_maintenance'),
            'maintenance_message_ar' => $validated['ios_maintenance_message_ar'] ?? $ios->maintenance_message_ar,
        ]);

        // Update Android
        $android->update([
            'bundle_id' => ($validated['android_bundle_id'] ?? '') ?: $android->bundle_id,
            'latest_version' => $androidLatest,
            'min_supported_version' => $androidMin,
            'store_url' => ($validated['android_store_url'] ?? '') ?: $android->store_url,
            'title_ar' => ($validated['android_title_ar'] ?? '') ?: ($android->title_ar ?: 'تحديث جديد متاح لتطبيق العائلة'),
            'release_notes_ar' => $validated['android_release_notes_ar'] ?? $android->release_notes_ar,
            'is_under_maintenance' => $request->boolean('android_is_under_maintenance'),
            'maintenance_message_ar' => $validated['android_maintenance_message_ar'] ?? $android->maintenance_message_ar,
        ]);

        $this->versionService->clearCache();

        return redirect()->route('dashboard.settings.index', ['tab' => 'versions'])
            ->with('success', 'تم حفظ السياسات وإعدادات التطبيق وتحديث الكاش الفوري بنجاح.');
    }

    /**
     * Trigger on-demand sync from official App Store and Google Play.
     */
    public function syncStore(Request $request): RedirectResponse
    {
        $platform = $request->input('platform', 'all');

        if ($platform === 'all') {
            $results = $this->storeSyncService->syncAll();
            $iosMsg = $results['ios']['message'] ?? '';
            $androidMsg = $results['android']['message'] ?? '';
            $message = "نتيجة المزامنة:\n• iOS: {$iosMsg}\n• Android: {$androidMsg}";
        } else {
            $result = $this->storeSyncService->syncPlatform($platform);
            $message = $result['message'];
        }

        return redirect()->route('dashboard.settings.index', ['tab' => 'versions'])
            ->with('success', $message);
    }

    /**
     * Quick action: Sync detected store version directly into latest_version field.
     */
    public function applyStoreVersion(Request $request): RedirectResponse
    {
        $platform = $request->input('platform');
        $appVersion = AppVersion::where('platform', $platform)->first();

        if ($appVersion && $appVersion->store_detected_version) {
            $appVersion->update([
                'latest_version' => $appVersion->store_detected_version,
            ]);

            $this->versionService->clearCache($platform);

            return redirect()->route('dashboard.settings.index', ['tab' => 'versions'])
                ->with('success', "تم اعتماد نسخة المتجر ({$appVersion->store_detected_version}) لمنصة {$platform} بنجاح!");
        }

        return redirect()->route('dashboard.settings.index', ['tab' => 'versions'])
            ->with('error', 'لا توجد نسخة متجر مكتشفة لاعتمادها.');
    }

    /**
     * Update General & Identity Settings.
     */
    public function updateGeneral(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'site_name_ar' => ['required', 'string', 'max:255'],
            'site_name_en' => ['nullable', 'string', 'max:255'],
            'app_name_ar' => ['required', 'string', 'max:255'],
            'app_name_en' => ['nullable', 'string', 'max:255'],
            'site_description_ar' => ['nullable', 'string', 'max:1000'],
            'site_description_en' => ['nullable', 'string', 'max:1000'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'support_phone' => ['nullable', 'string', 'max:50'],
            'support_whatsapp' => ['nullable', 'string', 'max:50'],
            'site_logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:4096'],
            'site_logo_dark' => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:4096'],
            'site_favicon' => ['nullable', 'file', 'mimes:ico,png,svg', 'max:2048'],
        ]);

        foreach (['site_name_ar', 'site_name_en', 'app_name_ar', 'app_name_en', 'site_description_ar', 'site_description_en', 'support_email', 'support_phone', 'support_whatsapp'] as $key) {
            SystemSetting::set($key, $validated[$key] ?? '', 'general', 'text');
        }

        // Upload Logos & Favicon
        if ($request->hasFile('site_logo')) {
            $path = $request->file('site_logo')->store('settings', 'public');
            SystemSetting::set('site_logo', $path, 'general', 'image');
        }

        if ($request->hasFile('site_logo_dark')) {
            $path = $request->file('site_logo_dark')->store('settings', 'public');
            SystemSetting::set('site_logo_dark', $path, 'general', 'image');
        }

        if ($request->hasFile('site_favicon')) {
            $path = $request->file('site_favicon')->store('settings', 'public');
            SystemSetting::set('site_favicon', $path, 'general', 'image');
        }

        return redirect()->route('dashboard.settings.index', ['tab' => 'general'])
            ->with('success', 'تم حفظ إعدادات الهوية العامة وتحديث الشعار بنجاح.');
    }

    /**
     * Update SEO & Metadata Settings.
     */
    public function updateSeo(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'seo_meta_title' => ['required', 'string', 'max:255'],
            'seo_meta_description' => ['nullable', 'string', 'max:1000'],
            'seo_meta_keywords' => ['nullable', 'string', 'max:500'],
            'seo_robots_index' => ['nullable', 'boolean'],
            'seo_og_image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
        ]);

        SystemSetting::set('seo_meta_title', $validated['seo_meta_title'], 'seo', 'text');
        SystemSetting::set('seo_meta_description', $validated['seo_meta_description'] ?? '', 'seo', 'textarea');
        SystemSetting::set('seo_meta_keywords', $validated['seo_meta_keywords'] ?? '', 'seo', 'text');
        SystemSetting::set('seo_robots_index', $request->boolean('seo_robots_index') ? '1' : '0', 'seo', 'boolean');

        if ($request->hasFile('seo_og_image')) {
            $path = $request->file('seo_og_image')->store('settings', 'public');
            SystemSetting::set('seo_og_image', $path, 'seo', 'image');
        }

        return redirect()->route('dashboard.settings.index', ['tab' => 'seo'])
            ->with('success', 'تم حفظ إعدادات SEO ومحركات البحث بنجاح.');
    }

    /**
     * Update Legal Policies & Content Pages (Privacy Policy, Terms, About).
     */
    public function updateLegal(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'page_about_enabled' => ['nullable', 'boolean'],
            'page_about_title_ar' => ['required', 'string', 'max:255'],
            'page_about_content_ar' => ['required', 'string'],

            'page_privacy_enabled' => ['nullable', 'boolean'],
            'page_privacy_title_ar' => ['required', 'string', 'max:255'],
            'page_privacy_last_updated' => ['nullable', 'string', 'max:100'],
            'page_privacy_content_ar' => ['required', 'string'],

            'page_terms_enabled' => ['nullable', 'boolean'],
            'page_terms_title_ar' => ['required', 'string', 'max:255'],
            'page_terms_content_ar' => ['required', 'string'],
        ]);

        // About
        SystemSetting::set('page_about_enabled', $request->boolean('page_about_enabled') ? '1' : '0', 'legal', 'boolean');
        SystemSetting::set('page_about_title_ar', $validated['page_about_title_ar'], 'legal', 'text');
        SystemSetting::set('page_about_content_ar', $validated['page_about_content_ar'], 'legal', 'textarea');

        // Privacy Policy
        SystemSetting::set('page_privacy_enabled', $request->boolean('page_privacy_enabled') ? '1' : '0', 'legal', 'boolean');
        SystemSetting::set('page_privacy_title_ar', $validated['page_privacy_title_ar'], 'legal', 'text');
        SystemSetting::set('page_privacy_last_updated', $validated['page_privacy_last_updated'] ?? '', 'legal', 'text');
        SystemSetting::set('page_privacy_content_ar', $validated['page_privacy_content_ar'], 'legal', 'textarea');

        // Terms
        SystemSetting::set('page_terms_enabled', $request->boolean('page_terms_enabled') ? '1' : '0', 'legal', 'boolean');
        SystemSetting::set('page_terms_title_ar', $validated['page_terms_title_ar'], 'legal', 'text');
        SystemSetting::set('page_terms_content_ar', $validated['page_terms_content_ar'], 'legal', 'textarea');

        return redirect()->route('dashboard.settings.index', ['tab' => 'legal'])
            ->with('success', 'تم حفظ وتحديث الصفحات والسياسات القانونية بنجاح.');
    }

    /**
     * Update Security & Maintenance settings.
     */
    public function updateSecurity(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'maintenance_mode_site' => ['nullable', 'boolean'],
            'maintenance_site_message' => ['nullable', 'string', 'max:1000'],
        ]);

        SystemSetting::set('maintenance_mode_site', $request->boolean('maintenance_mode_site') ? '1' : '0', 'security', 'boolean');
        SystemSetting::set('maintenance_site_message', $validated['maintenance_site_message'] ?? '', 'security', 'textarea');

        return redirect()->route('dashboard.settings.index', ['tab' => 'security'])
            ->with('success', 'تم تحديث إعدادات الأمان ووضع الصيانة بنجاح.');
    }
}

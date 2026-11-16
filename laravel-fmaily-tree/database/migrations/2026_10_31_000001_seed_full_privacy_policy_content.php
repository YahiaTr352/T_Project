<?php

use App\Models\SystemSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The short placeholder that shipped with the original legal settings.
     */
    private const LEGACY_CONTENT_PREFIX = 'نلتزم في منصة وتطبيق عائلة السّرِيع بالحفاظ التام';

    /**
     * Seed the full Arabic privacy policy, including the Health Data disclosure.
     *
     * Existing admin customisations are preserved: the content is only replaced
     * when the stored value is empty or still the original placeholder.
     */
    public function up(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        $content = (string) config('legal_pages.privacy.content', '');
        $title = (string) config('legal_pages.privacy.title', 'سياسة الخصوصية وحماية البيانات');
        $lastUpdated = (string) config('legal_pages.privacy.last_updated', '31 أكتوبر 2026');

        if (trim($content) === '') {
            return;
        }

        $now = now();
        $existingContent = DB::table('system_settings')
            ->where('key', 'page_privacy_content_ar')
            ->value('value');

        $shouldReplaceContent = $existingContent === null
            || trim((string) $existingContent) === ''
            || str_contains((string) $existingContent, self::LEGACY_CONTENT_PREFIX);

        if ($shouldReplaceContent) {
            $this->putSetting('page_privacy_content_ar', $content, 'textarea', $now);
        }

        $this->putSettingIfMissing('page_privacy_title_ar', $title, 'text', $now);
        $this->putSettingIfMissing('page_privacy_last_updated', $lastUpdated, 'text', $now);
        $this->putSettingIfMissing('page_privacy_enabled', '1', 'boolean', $now);

        if (class_exists(SystemSetting::class)) {
            SystemSetting::clearCache();
        }
    }

    /**
     * Restore the original placeholder when this migration is rolled back.
     */
    public function down(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        $content = trim((string) config('legal_pages.privacy.content', ''));
        $current = DB::table('system_settings')
            ->where('key', 'page_privacy_content_ar')
            ->value('value');

        if ($content !== '' && trim((string) $current) === $content) {
            $this->putSetting(
                'page_privacy_content_ar',
                'نلتزم في منصة وتطبيق عائلة السّرِيع بالحفاظ التام على سرية وأمان بيانات أفراد العائلة. تُستخدم البيانات والمعلومات الشخصية المدخلة حصراً في توثيق الأنساب والتواصل العائلي الداخلي ولا تتم مشاركتها أو بيعها لأي طرف خارجي نهائياً.',
                'textarea',
                now()
            );
        }

        if (class_exists(SystemSetting::class)) {
            SystemSetting::clearCache();
        }
    }

    private function putSetting(string $key, string $value, string $type, mixed $now): void
    {
        $exists = DB::table('system_settings')->where('key', $key)->exists();

        if ($exists) {
            DB::table('system_settings')->where('key', $key)->update([
                'value' => $value,
                'group' => 'legal',
                'type' => $type,
                'updated_at' => $now,
            ]);

            return;
        }

        DB::table('system_settings')->insert([
            'key' => $key,
            'value' => $value,
            'group' => 'legal',
            'type' => $type,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function putSettingIfMissing(string $key, string $value, string $type, mixed $now): void
    {
        if (DB::table('system_settings')->where('key', $key)->exists()) {
            return;
        }

        $this->putSetting($key, $value, $type, $now);
    }
};

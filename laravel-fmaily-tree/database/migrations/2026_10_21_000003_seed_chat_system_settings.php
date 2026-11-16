<?php

use App\Models\SystemSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * إعدادات نظام المحادثات في مخزن الإعدادات العام (system_settings / group = chat).
     */
    public function up(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        $now = now();

        $settings = [
            // ── عام ───────────────────────────────────────────────
            ['key' => 'chat.enabled', 'value' => '1', 'group' => 'chat', 'type' => 'boolean'],
            ['key' => 'chat.max_message_length', 'value' => '2000', 'group' => 'chat', 'type' => 'text'],
            ['key' => 'chat.slow_mode_seconds', 'value' => '0', 'group' => 'chat', 'type' => 'text'],
            ['key' => 'chat.allow_attachments', 'value' => '1', 'group' => 'chat', 'type' => 'boolean'],
            ['key' => 'chat.attachment_max_size_kb', 'value' => '30720', 'group' => 'chat', 'type' => 'text'],
            ['key' => 'chat.participant_rate_limit_per_minute', 'value' => '60', 'group' => 'chat', 'type' => 'text'],
            ['key' => 'chat.retention_days', 'value' => '0', 'group' => 'chat', 'type' => 'text'],

            // ── فلتر المحتوى ─────────────────────────────────────
            ['key' => 'chat.filter.enabled', 'value' => '1', 'group' => 'chat', 'type' => 'boolean'],
            ['key' => 'chat.filter.blocked_words', 'value' => '1', 'group' => 'chat', 'type' => 'boolean'],
            ['key' => 'chat.filter.mask_words', 'value' => '1', 'group' => 'chat', 'type' => 'boolean'],
            ['key' => 'chat.filter.links', 'value' => '0', 'group' => 'chat', 'type' => 'boolean'],
            ['key' => 'chat.filter.link_action', 'value' => 'warn', 'group' => 'chat', 'type' => 'text'],
            ['key' => 'chat.filter.allowed_link_domains', 'value' => '[]', 'group' => 'chat', 'type' => 'json'],
            ['key' => 'chat.filter.spam_patterns', 'value' => '1', 'group' => 'chat', 'type' => 'boolean'],
            ['key' => 'chat.filter.max_mentions_per_message', 'value' => '10', 'group' => 'chat', 'type' => 'text'],

            // ── التصعيد التلقائي ──────────────────────────────────
            ['key' => 'chat.escalation.warnings_before_flag', 'value' => '3', 'group' => 'chat', 'type' => 'text'],
            ['key' => 'chat.escalation.auto_ban_after_reports', 'value' => '0', 'group' => 'chat', 'type' => 'text'],
            ['key' => 'chat.escalation.auto_ban_duration_days', 'value' => '7', 'group' => 'chat', 'type' => 'text'],
            ['key' => 'chat.escalation.auto_ban_scope', 'value' => 'conversation', 'group' => 'chat', 'type' => 'text'],

            // ── أسباب البلاغ ──────────────────────────────────────
            ['key' => 'chat.report_reasons', 'value' => json_encode([
                'spam' => 'رسائل مزعجة أو مكررة',
                'abuse' => 'إساءة أو تنمّر',
                'inappropriate' => 'محتوى غير لائق',
                'personal_info' => 'تسريب بيانات شخصية',
                'impersonation' => 'انتحال شخصية',
                'other' => 'سبب آخر',
            ], JSON_UNESCAPED_UNICODE), 'group' => 'chat', 'type' => 'json'],

            // ── الإشراف الذكي (AI) ───────────────────────────────
            ['key' => 'chat.moderation.ai_enabled', 'value' => '0', 'group' => 'chat', 'type' => 'boolean'],
            ['key' => 'chat.moderation.ai_reported_only', 'value' => '1', 'group' => 'chat', 'type' => 'boolean'],
            ['key' => 'chat.moderation.ai_sample_rate', 'value' => '0', 'group' => 'chat', 'type' => 'text'],
            ['key' => 'chat.moderation.ai_threshold', 'value' => '0.7000', 'group' => 'chat', 'type' => 'text'],
        ];

        foreach ($settings as &$item) {
            $item['created_at'] = $now;
            $item['updated_at'] = $now;
        }
        unset($item);

        DB::table('system_settings')->upsert(
            $settings,
            ['key'],
            ['value', 'group', 'type', 'updated_at']
        );

        if (class_exists(SystemSetting::class)) {
            SystemSetting::clearCache();
        }
    }

    public function down(): void
    {
        DB::table('system_settings')
            ->where('group', 'chat')
            ->delete();

        if (class_exists(SystemSetting::class)) {
            SystemSetting::clearCache();
        }
    }
};

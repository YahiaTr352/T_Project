<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->longText('value')->nullable();
            $table->string('group', 50)->index(); // 'general', 'seo', 'legal', 'security'
            $table->string('type', 30)->default('text'); // 'text', 'textarea', 'boolean', 'image', 'json'
            $table->timestamps();
        });

        // Seed initial system settings
        $settings = [
            // General & Identity
            ['key' => 'site_name_ar', 'value' => 'عائلة السريّع', 'group' => 'general', 'type' => 'text'],
            ['key' => 'site_name_en', 'value' => 'Al-Sorayia Family', 'group' => 'general', 'type' => 'text'],
            ['key' => 'app_name_ar', 'value' => 'عائلة السريّع', 'group' => 'general', 'type' => 'text'],
            ['key' => 'app_name_en', 'value' => 'Al-Sorayia App', 'group' => 'general', 'type' => 'text'],
            ['key' => 'site_description_ar', 'value' => 'البوابة الرسمية وشجرة العائلة لآل سريّع', 'group' => 'general', 'type' => 'textarea'],
            ['key' => 'site_description_en', 'value' => 'Official family tree and portal for Al-Sorayia family', 'group' => 'general', 'type' => 'textarea'],
            ['key' => 'support_email', 'value' => 'info@alsorayia.com', 'group' => 'general', 'type' => 'text'],
            ['key' => 'support_phone', 'value' => '', 'group' => 'general', 'type' => 'text'],
            ['key' => 'support_whatsapp', 'value' => '', 'group' => 'general', 'type' => 'text'],
            ['key' => 'site_logo', 'value' => null, 'group' => 'general', 'type' => 'image'],
            ['key' => 'site_logo_dark', 'value' => null, 'group' => 'general', 'type' => 'image'],
            ['key' => 'site_favicon', 'value' => null, 'group' => 'general', 'type' => 'image'],

            // SEO & Meta
            ['key' => 'seo_meta_title', 'value' => 'بوابة وشجرة عائلة السريّع', 'group' => 'seo', 'type' => 'text'],
            ['key' => 'seo_meta_description', 'value' => 'الموقع الرسمي وبوابة التواصل وشجرة الأنساب لعائلة السريّع', 'group' => 'seo', 'type' => 'textarea'],
            ['key' => 'seo_meta_keywords', 'value' => 'السريّع, عائلة السريّع, شجرة العائلة, آل سريّع, أنساب', 'group' => 'seo', 'type' => 'text'],
            ['key' => 'seo_og_image', 'value' => null, 'group' => 'seo', 'type' => 'image'],
            ['key' => 'seo_robots_index', 'value' => '1', 'group' => 'seo', 'type' => 'boolean'],

            // Legal & Policies (Compliant with Google Play & Apple Guidelines)
            ['key' => 'page_about_enabled', 'value' => '1', 'group' => 'legal', 'type' => 'boolean'],
            ['key' => 'page_about_title_ar', 'value' => 'من نحن - نبذة عن عائلة السريّع', 'group' => 'legal', 'type' => 'text'],
            ['key' => 'page_about_content_ar', 'value' => 'بوابة وتطبيق عائلة السريّع تهدف لتوثيق تاريخ وشجرة العائلة الكريمة وتعزيز أواصر صلة الرحم والتواصل الاجتماعي والأنشطة الأسرية المشتركة.', 'group' => 'legal', 'type' => 'textarea'],
            
            ['key' => 'page_privacy_enabled', 'value' => '1', 'group' => 'legal', 'type' => 'boolean'],
            ['key' => 'page_privacy_title_ar', 'value' => 'سياسة الخصوصية وحماية البيانات', 'group' => 'legal', 'type' => 'text'],
            ['key' => 'page_privacy_content_ar', 'value' => 'نلتزم في منصة وتطبيق عائلة السريّع بالحفاظ التام على سرية وأمان بيانات أفراد العائلة. تُستخدم البيانات والمعلومات الشخصية المدخلة حصراً في توثيق الأنساب والتواصل العائلي الداخلي ولا تتم مشاركتها أو بيعها لأي طرف خارجي نهائياً.', 'group' => 'legal', 'type' => 'textarea'],

            ['key' => 'page_terms_enabled', 'value' => '1', 'group' => 'legal', 'type' => 'boolean'],
            ['key' => 'page_terms_title_ar', 'value' => 'الشروط والأحكام وسياسة الاستخدام', 'group' => 'legal', 'type' => 'text'],
            ['key' => 'page_terms_content_ar', 'value' => 'يخضع استخدام بوابة وتطبيق عائلة السريّع لضوابط الخصوصية والأعراف الأسرية، ويجب على كل مستخدم الحفاظ على سرية حسابه وبيانات العائلة المتاحة له.', 'group' => 'legal', 'type' => 'textarea'],

            // Security & Maintenance
            ['key' => 'maintenance_mode_site', 'value' => '0', 'group' => 'security', 'type' => 'boolean'],
            ['key' => 'maintenance_site_message', 'value' => 'الموقع يخضع لعمليات صيانة وتحديث مجدولة لتحسين الأداء. سنعود قريباً.', 'group' => 'security', 'type' => 'textarea'],
        ];

        $now = now();
        foreach ($settings as &$item) {
            $item['created_at'] = $now;
            $item['updated_at'] = $now;
        }

        DB::table('system_settings')->insert($settings);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};

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
        Schema::create('app_versions', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 20)->unique(); // 'ios' or 'android'
            $table->string('bundle_id', 120)->nullable(); // e.g. com.alsorayia.app
            $table->string('latest_version', 50)->default('1.0.0'); // Current version in system
            $table->string('min_supported_version', 50)->default('1.0.0'); // Cutoff for force update
            $table->string('store_url', 1000)->nullable();
            $table->string('store_detected_version', 50)->nullable(); // Live detected version from App Store / Google Play
            $table->timestamp('store_synced_at')->nullable();
            $table->string('title_ar', 255)->nullable();
            $table->string('title_en', 255)->nullable();
            $table->text('release_notes_ar')->nullable();
            $table->text('release_notes_en')->nullable();
            $table->boolean('is_under_maintenance')->default(false);
            $table->text('maintenance_message_ar')->nullable();
            $table->text('maintenance_message_en')->nullable();
            $table->timestamps();
        });

        // Seed initial platform records
        DB::table('app_versions')->insert([
            [
                'platform' => 'ios',
                'bundle_id' => 'com.alsorayia.app',
                'latest_version' => '1.2.0',
                'min_supported_version' => '1.0.0',
                'store_url' => 'https://apps.apple.com/app/id6738957876',
                'store_detected_version' => null,
                'store_synced_at' => null,
                'title_ar' => 'تحديث جديد متاح لتطبيق العائلة',
                'title_en' => 'New update available for Family App',
                'release_notes_ar' => 'يتضمن هذا التحديث تحسينات في الأداء وإصلاحات وإضافات جديدة.',
                'release_notes_en' => 'This update includes performance improvements and new features.',
                'is_under_maintenance' => false,
                'maintenance_message_ar' => 'التطبيق يخضع لعمليات صيانة مجدولة لتحسين الخدمات، سنعود قريباً.',
                'maintenance_message_en' => 'The app is undergoing scheduled maintenance, we will be back shortly.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'platform' => 'android',
                'bundle_id' => 'com.alsorayia.app',
                'latest_version' => '1.2.0',
                'min_supported_version' => '1.0.0',
                'store_url' => 'https://play.google.com/store/apps/details?id=com.alsorayia.app',
                'store_detected_version' => null,
                'store_synced_at' => null,
                'title_ar' => 'تحديث جديد متاح لتطبيق العائلة',
                'title_en' => 'New update available for Family App',
                'release_notes_ar' => 'يتضمن هذا التحديث تحسينات في الأداء وإصلاحات وإضافات جديدة.',
                'release_notes_en' => 'This update includes performance improvements and new features.',
                'is_under_maintenance' => false,
                'maintenance_message_ar' => 'التطبيق يخضع لعمليات صيانة مجدولة لتحسين الخدمات، سنعود قريباً.',
                'maintenance_message_en' => 'The app is undergoing scheduled maintenance, we will be back shortly.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_versions');
    }
};

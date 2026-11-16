<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * مصدر الإشارة للشخص في الصورة:
     * - manual: وسم بشري من لوحة التحكم (موثوق)
     * - auto: وسم تلقائي من البحث بالوجه بثقة عالية فقط (>= 95%)
     * - legacy: صُفوف من فترة ما قبل التمييز (مختلطة وتحتاج مراجعة)
     */
    public function up(): void
    {
        Schema::table('image_mentions', function (Blueprint $table) {
            $table->string('source', 20)->nullable()->after('person_id');
            $table->index(['person_id', 'source'], 'image_mentions_person_source_idx');
        });

        // الـ auto-tagging بدأ مع إطلاق البحث بالوجه بتاريخ 2026-09-21،
        // فكل ما قبله وسم بشري مؤكد، وما بعده مختلط (legacy).
        DB::table('image_mentions')
            ->where('created_at', '<', '2026-09-21 00:00:00')
            ->whereNull('source')
            ->update(['source' => 'manual']);

        DB::table('image_mentions')
            ->where('created_at', '>=', '2026-09-21 00:00:00')
            ->whereNull('source')
            ->update(['source' => 'legacy']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('image_mentions', function (Blueprint $table) {
            $table->dropIndex('image_mentions_person_source_idx');
            $table->dropColumn('source');
        });
    }
};

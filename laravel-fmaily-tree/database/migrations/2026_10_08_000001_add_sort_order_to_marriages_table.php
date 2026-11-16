<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * sort_order: ترتيب يدوي للزوجات عند نفس الزوج (1 = الأولى).
     * NULL يعني "بدون ترتيب يدوي" -> نرجع لتاريخ الزواج.
     */
    public function up(): void
    {
        Schema::table('marriages', function (Blueprint $table) {
            $table->integer('sort_order')->nullable()->default(null)->after('is_divorced');
            $table->index(['husband_id', 'sort_order'], 'marriages_husband_sort_idx');
        });

        // Backfill: رتب الموجودين حسب married_at (NULL في الآخر) ثم id
        $husbandIds = DB::table('marriages')->distinct()->pluck('husband_id');
        foreach ($husbandIds as $husbandId) {
            $ids = DB::table('marriages')
                ->where('husband_id', $husbandId)
                ->orderByRaw('CASE WHEN married_at IS NULL THEN 1 ELSE 0 END ASC')
                ->orderBy('married_at', 'asc')
                ->orderBy('id', 'asc')
                ->pluck('id');

            $order = 1;
            foreach ($ids as $id) {
                DB::table('marriages')->where('id', $id)->update(['sort_order' => $order++]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marriages', function (Blueprint $table) {
            $table->dropIndex('marriages_husband_sort_idx');
            $table->dropColumn('sort_order');
        });
    }
};

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
        Schema::create('walking_clubs', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->string('name', 120);
            $table->string('short_name', 60);
            $table->string('sub_label', 60)->default('يومياً');
            $table->unsignedInteger('min_steps')->index();
            $table->unsignedInteger('max_steps')->nullable();
            // تاريخ بدء احتساب الخطوات والاستمرارية في هذا النادي (يتم تجاهل أي أيام سابقة لهذا التاريخ)
            $table->date('calculation_start_date')->nullable()->index();
            $table->string('icon', 30)->default('👟');
            $table->string('badge_color', 20)->default('#10B981');
            $table->string('badge_dark', 20)->default('#059669');
            $table->string('accent_light', 20)->default('#D1FAE5');
            $table->string('accent_dark', 20)->default('#064E3B');
            $table->integer('display_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        // Seed default 3 clubs immediately to guarantee seamless zero-downtime transition
        $now = now();
        DB::table('walking_clubs')->insert([
            [
                'slug'                   => 'club_5k',
                'name'                   => 'نادي الـ 5,000 خطوة يومياً',
                'short_name'             => 'نادي الـ 5,000',
                'sub_label'              => 'يومياً',
                'min_steps'              => 5000,
                'max_steps'              => 9999,
                'calculation_start_date' => null,
                'icon'                   => '🥉',
                'badge_color'            => '#D97706',
                'badge_dark'             => '#F59E0B',
                'accent_light'           => '#FEF3C7',
                'accent_dark'            => '#451A03',
                'display_order'          => 1,
                'is_active'              => true,
                'created_at'             => $now,
                'updated_at'             => $now,
            ],
            [
                'slug'                   => 'club_10k',
                'name'                   => 'نادي الـ 10,000 خطوة يومياً',
                'short_name'             => 'نادي الـ 10,000',
                'sub_label'              => 'يومياً',
                'min_steps'              => 10000,
                'max_steps'              => 19999,
                'calculation_start_date' => null,
                'icon'                   => '🥈',
                'badge_color'            => '#0284C7',
                'badge_dark'             => '#38BDF8',
                'accent_light'           => '#E0F2FE',
                'accent_dark'            => '#082F49',
                'display_order'          => 2,
                'is_active'              => true,
                'created_at'             => $now,
                'updated_at'             => $now,
            ],
            [
                'slug'                   => 'club_20k',
                'name'                   => 'نادي الـ 20,000 خطوة يومياً',
                'short_name'             => 'نادي الـ 20,000',
                'sub_label'              => 'يومياً',
                'min_steps'              => 20000,
                'max_steps'              => null,
                'calculation_start_date' => null,
                'icon'                   => '🥇',
                'badge_color'            => '#7C3AED',
                'badge_dark'             => '#A78BFA',
                'accent_light'           => '#EDE9FE',
                'accent_dark'            => '#2E1065',
                'display_order'          => 3,
                'is_active'              => true,
                'created_at'             => $now,
                'updated_at'             => $now,
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('walking_clubs');
    }
};

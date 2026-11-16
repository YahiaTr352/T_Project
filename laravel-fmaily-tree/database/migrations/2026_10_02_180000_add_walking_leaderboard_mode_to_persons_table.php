<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            if (! Schema::hasColumn('persons', 'walking_leaderboard_mode')) {
                $table->string('walking_leaderboard_mode', 20)
                    ->default('visible')
                    ->after('privacy_settings')
                    ->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            if (Schema::hasColumn('persons', 'walking_leaderboard_mode')) {
                $table->dropIndex(['walking_leaderboard_mode']);
                $table->dropColumn('walking_leaderboard_mode');
            }
        });
    }
};

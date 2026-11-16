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
        Schema::table('quiz_questions', function (Blueprint $table) {
            $table->integer('vote_edit_duration_hours')
                ->nullable()
                ->default(24)
                ->after('vote_max_selections')
                ->comment('Vote edit window in hours: -1 for unlimited, 0 for no edit, >0 for specific hours');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quiz_questions', function (Blueprint $table) {
            $table->dropColumn('vote_edit_duration_hours');
        });
    }
};

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
            $table->unsignedBigInteger('linked_walking_question_id')
                ->nullable()
                ->after('vote_edit_duration_hours')
                ->comment('ID of linked walking challenge question for predictions');

            $table->json('prediction_rank_points')
                ->nullable()
                ->after('linked_walking_question_id')
                ->comment('JSON map of rank to awarded points: {"1": 20, "2": 15, "3": 10}');

            $table->foreign('linked_walking_question_id')
                ->references('id')
                ->on('quiz_questions')
                ->nullOnDelete();

            $table->index('linked_walking_question_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quiz_questions', function (Blueprint $table) {
            $table->dropForeign(['linked_walking_question_id']);
            $table->dropIndex(['linked_walking_question_id']);
            $table->dropColumn(['linked_walking_question_id', 'prediction_rank_points']);
        });
    }
};

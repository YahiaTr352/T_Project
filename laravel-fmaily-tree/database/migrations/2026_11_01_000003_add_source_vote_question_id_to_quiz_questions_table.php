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
            $table->unsignedBigInteger('source_vote_question_id')
                ->nullable()
                ->after('linked_walking_question_id')
                ->comment('Optional source vote question ID if importing nominations from an earlier contest');

            $table->foreign('source_vote_question_id')
                ->references('id')
                ->on('quiz_questions')
                ->nullOnDelete();

            $table->index('source_vote_question_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quiz_questions', function (Blueprint $table) {
            $table->dropForeign(['source_vote_question_id']);
            $table->dropIndex(['source_vote_question_id']);
            $table->dropColumn(['source_vote_question_id']);
        });
    }
};

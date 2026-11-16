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
        Schema::table('quiz_question_choices', function (Blueprint $table) {
            if (!Schema::hasColumn('quiz_question_choices', 'person_id')) {
                $table->unsignedBigInteger('person_id')->nullable()->after('quiz_question_id')->index();
                $table->foreign('person_id')->references('id')->on('persons')->onDelete('set null');
            }
        });

        Schema::table('quiz_questions', function (Blueprint $table) {
            if (!Schema::hasColumn('quiz_questions', 'voter_eligibility')) {
                $table->json('voter_eligibility')->nullable()->after('require_prior_registration');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quiz_question_choices', function (Blueprint $table) {
            if (Schema::hasColumn('quiz_question_choices', 'person_id')) {
                $table->dropForeign(['person_id']);
                $table->dropColumn('person_id');
            }
        });

        Schema::table('quiz_questions', function (Blueprint $table) {
            if (Schema::hasColumn('quiz_questions', 'voter_eligibility')) {
                $table->dropColumn('voter_eligibility');
            }
        });
    }
};

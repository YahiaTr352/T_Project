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
        Schema::table('quiz_answers', function (Blueprint $table) {
            if (! Schema::hasColumn('quiz_answers', 'person_id')) {
                $table->foreignId('person_id')->nullable()->after('quiz_question_id')
                    ->constrained('persons')->nullOnDelete();
                $table->index(['quiz_question_id', 'person_id'], 'qa_q_person_idx');
            }
            if (Schema::hasColumn('quiz_answers', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->change();
            }
        });

        Schema::table('quiz_registrations', function (Blueprint $table) {
            if (! Schema::hasColumn('quiz_registrations', 'person_id')) {
                $table->foreignId('person_id')->nullable()->after('quiz_competition_id')
                    ->constrained('persons')->cascadeOnDelete();
                $table->index(['quiz_competition_id', 'person_id'], 'qr_comp_person_idx');
            }
            if (Schema::hasColumn('quiz_registrations', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quiz_answers', function (Blueprint $table) {
            if (Schema::hasColumn('quiz_answers', 'person_id')) {
                $table->dropForeign(['person_id']);
                $table->dropIndex('qa_q_person_idx');
                $table->dropColumn('person_id');
            }
        });

        Schema::table('quiz_registrations', function (Blueprint $table) {
            if (Schema::hasColumn('quiz_registrations', 'person_id')) {
                $table->dropForeign(['person_id']);
                $table->dropIndex('qr_comp_person_idx');
                $table->dropColumn('person_id');
            }
        });
    }
};

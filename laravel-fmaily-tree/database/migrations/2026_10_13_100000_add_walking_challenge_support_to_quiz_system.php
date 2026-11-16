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
        // 1. Add walking_settings to quiz_questions table
        Schema::table('quiz_questions', function (Blueprint $table) {
            if (! Schema::hasColumn('quiz_questions', 'walking_settings')) {
                $table->json('walking_settings')->nullable()->after('description')
                    ->comment('إعدادات تحدي المشي: الفئة المستهدفة، قواعد النقاط، الجوائز، والخصوصية');
            }
        });

        // 2. Enhance quiz_winners table with person_id, score, steps, gender, and nullable user_id
        Schema::table('quiz_winners', function (Blueprint $table) {
            if (! Schema::hasColumn('quiz_winners', 'person_id')) {
                $table->foreignId('person_id')->nullable()->after('quiz_question_id')
                    ->constrained('persons')->nullOnDelete();
            }
            if (! Schema::hasColumn('quiz_winners', 'score')) {
                $table->integer('score')->nullable()->after('position')->comment('مجموع النقاط');
            }
            if (! Schema::hasColumn('quiz_winners', 'steps')) {
                $table->integer('steps')->nullable()->after('score')->comment('مجموع الخطوات');
            }
            if (! Schema::hasColumn('quiz_winners', 'gender')) {
                $table->string('gender', 10)->nullable()->after('steps')->comment('جنس المسار: male أو female');
            }
        });

        // 3. Create table for tracking daily points and ranks in walking challenges
        if (! Schema::hasTable('quiz_competition_walking_scores')) {
            Schema::create('quiz_competition_walking_scores', function (Blueprint $table) {
                $table->id();
                $table->foreignId('quiz_question_id')->constrained('quiz_questions')->cascadeOnDelete();
                $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete();
                $table->date('date')->index()->comment('تاريخ يوم المنافسة');
                $table->unsignedInteger('steps')->default(0)->comment('خطوات اليوم');
                $table->unsignedSmallInteger('daily_rank')->default(0)->comment('المركز في اليوم');
                $table->unsignedInteger('points_earned')->default(0)->comment('النقاط المكتسبة لهذا اليوم');
                $table->string('gender', 10)->default('male')->comment('جنس المتسابق');
                $table->timestamps();

                $table->unique(['quiz_question_id', 'person_id', 'date'], 'comp_walk_score_unique');
                $table->index(['quiz_question_id', 'date'], 'comp_walk_date_idx');
                $table->index(['person_id', 'date'], 'person_walk_date_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_competition_walking_scores');

        Schema::table('quiz_winners', function (Blueprint $table) {
            if (Schema::hasColumn('quiz_winners', 'person_id')) {
                $table->dropForeign(['person_id']);
                $table->dropColumn('person_id');
            }
            if (Schema::hasColumn('quiz_winners', 'score')) {
                $table->dropColumn('score');
            }
            if (Schema::hasColumn('quiz_winners', 'steps')) {
                $table->dropColumn('steps');
            }
            if (Schema::hasColumn('quiz_winners', 'gender')) {
                $table->dropColumn('gender');
            }
        });

        Schema::table('quiz_questions', function (Blueprint $table) {
            if (Schema::hasColumn('quiz_questions', 'walking_settings')) {
                $table->dropColumn('walking_settings');
            }
        });
    }
};

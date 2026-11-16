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
        Schema::create('quiz_competition_walking_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_question_id')->constrained('quiz_questions')->cascadeOnDelete();
            $table->foreignId('person_id')->nullable()->constrained('persons')->cascadeOnDelete();
            $table->foreignId('walking_group_id')->nullable()->constrained('walking_groups')->nullOnDelete();
            $table->integer('points')->comment('قيمة التعديل: موجبة للمكافأة وسالبة للخصم');
            $table->string('type', 20)->default('bonus')->comment('نوع التعديل: bonus, penalty, correction');
            $table->string('reason', 255)->comment('سبب التعديل وسجل التبرير');
            $table->date('date')->nullable()->comment('تاريخ يوم المنافسة المرتبط إن وجد');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['quiz_question_id', 'person_id'], 'q_walk_adj_person_idx');
            $table->index(['quiz_question_id', 'date'], 'q_walk_adj_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_competition_walking_adjustments');
    }
};

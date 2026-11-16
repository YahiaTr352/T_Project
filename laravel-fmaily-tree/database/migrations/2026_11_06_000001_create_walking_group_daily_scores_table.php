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
        Schema::create('walking_group_daily_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('walking_group_id')
                ->constrained('walking_groups')
                ->cascadeOnDelete();
            $table->foreignId('person_id')
                ->constrained('persons')
                ->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('daily_steps')->default(0);
            $table->unsignedInteger('daily_rank')->default(0);
            $table->unsignedInteger('points_earned')->default(0);
            $table->unsignedInteger('group_members_count')->default(0);
            $table->timestamps();

            $table->unique(['walking_group_id', 'person_id', 'date'], 'w_group_daily_scores_unique');
            $table->index(['walking_group_id', 'date'], 'w_group_daily_scores_group_date_idx');
            $table->index(['person_id', 'date'], 'w_group_daily_scores_person_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('walking_group_daily_scores');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('quiz_answer_choices', 'is_locked')) {
            Schema::table('quiz_answer_choices', function (Blueprint $table) {
                $table->boolean('is_locked')->default(false)->after('quiz_question_choice_id');
            });
        }

        // Backfill: any existing vote answer that currently has fewer selections than the question's vote_max_selections
        // should have its existing selections marked as locked (Phase 1 selections).
        $voteQuestions = DB::table('quiz_questions')
            ->where('answer_type', 'vote')
            ->where('vote_max_selections', '>', 1)
            ->select(['id', 'vote_max_selections'])
            ->get();

        foreach ($voteQuestions as $question) {
            $maxSelections = (int) $question->vote_max_selections;
            $incompleteAnswerIds = DB::table('quiz_answers')
                ->join('quiz_answer_choices', 'quiz_answers.id', '=', 'quiz_answer_choices.quiz_answer_id')
                ->where('quiz_answers.quiz_question_id', $question->id)
                ->groupBy('quiz_answers.id')
                ->havingRaw('COUNT(quiz_answer_choices.id) < ?', [$maxSelections])
                ->pluck('quiz_answers.id')
                ->all();

            if (! empty($incompleteAnswerIds)) {
                DB::table('quiz_answer_choices')
                    ->whereIn('quiz_answer_id', $incompleteAnswerIds)
                    ->update(['is_locked' => true]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('quiz_answer_choices', 'is_locked')) {
            Schema::table('quiz_answer_choices', function (Blueprint $table) {
                $table->dropColumn('is_locked');
            });
        }
    }
};

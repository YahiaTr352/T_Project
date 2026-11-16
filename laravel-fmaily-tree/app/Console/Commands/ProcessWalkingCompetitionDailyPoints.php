<?php

namespace App\Console\Commands;

use App\Models\QuizCompetition;
use App\Models\QuizQuestion;
use App\Services\Walking\WalkingCompetitionScoringService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ProcessWalkingCompetitionDailyPoints extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'competition:walking-process-daily {--date= : تاريخ اليوم بصيغة Y-m-d}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'احتساب وترصيد نقاط المشي اليومية لمسابقات المشي النشطة عند منتصف الليل بتوقيت السعودية';

    public function handle(WalkingCompetitionScoringService $scoringService): int
    {
        $tz = WalkingCompetitionScoringService::KSA_TIMEZONE;
        $targetDate = $this->option('date')
            ? Carbon::parse($this->option('date'), $tz)
            : Carbon::yesterday($tz);

        $dateStr = $targetDate->toDateString();
        $this->info("بدء معالجة نقاط مسابقات المشي لليوم: {$dateStr}");

        // ابحث عن جميع أسئلة المشي في المسابقات النشطة
        $walkingQuestions = QuizQuestion::query()
            ->where('answer_type', 'walking')
            ->whereHas('competition', function ($q) use ($targetDate) {
                $q->where('is_active', true)
                  ->where(function ($sq) use ($targetDate) {
                      $sq->whereNull('start_at')
                         ->orWhereDate('start_at', '<=', $targetDate);
                  })
                  ->where(function ($sq) use ($targetDate) {
                      $sq->whereNull('end_at')
                         ->orWhereDate('end_at', '>=', $targetDate);
                  });
            })
            ->get();

        if ($walkingQuestions->isEmpty()) {
            $this->info("لا توجد مسابقات مشي نشطة تتطلب معالجة لليوم المحدد.");
            return Command::SUCCESS;
        }

        $totalProcessed = 0;
        foreach ($walkingQuestions as $question) {
            if ($question->isGroupWalkingChallenge()) {
                $count = $scoringService->processDailyGroupScores($question, $targetDate);
                $totalProcessed += $count;
                $this->info("تم تحديث وترصيد منافسة المجموعات ({$count} مجموعة) للمسابقة #{$question->quiz_competition_id} - السؤال #{$question->id}");
            } else {
                $count = $scoringService->processDailyScores($question, $targetDate);
                $totalProcessed += $count;
                $this->info("تم احتساب وترصيد {$count} سجل/نقطة للأفراد للمسابقة #{$question->quiz_competition_id} - السؤال #{$question->id}");
            }
        }

        $this->info("اكتملت المعالجة بنجاح. الإجمالي: {$totalProcessed} سجل.");
        return Command::SUCCESS;
    }
}

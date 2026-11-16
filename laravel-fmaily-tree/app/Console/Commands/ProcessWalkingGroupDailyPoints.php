<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Walking\WalkingGroupScoringService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ProcessWalkingGroupDailyPoints extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'walking:groups-process-daily-points
        {--date= : تاريخ محدد بصيغة Y-m-d}
        {--days= : عدد الأيام السابقة للاحتساب بأثر رجعي (مثل --days=5)}
        {--from= : تاريخ البداية للاحتساب بأثر رجعي بصيغة Y-m-d}
        {--to= : تاريخ النهاية للاحتساب بصيغة Y-m-d (الافتراضي الأمس)}
        {--all : احتساب شامل بأثر رجعي من تاريخ إنشاء كل مجموعة حتى الأمس}
        {--group= : حصر الاحتساب في رقم مجموعة محدد (ID)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'احتساب وترصيد نقاط مجموعات وتحديات المشي اليومية للأعضاء عند منتصف الليل بتوقيت السعودية (مع دعم الأثر الرجعي Backfill)';

    public function handle(WalkingGroupScoringService $scoringService): int
    {
        $tz = WalkingGroupScoringService::KSA_TIMEZONE;
        $isBackfill = $this->option('days') !== null || $this->option('from') !== null || (bool) $this->option('all');

        if ($isBackfill) {
            $this->info("بدء احتساب وترصيد نقاط مجموعات المشي بأثر رجعي (Backfill)...");

            $options = [
                'days'     => $this->option('days') ? (int) $this->option('days') : null,
                'from'     => $this->option('from') ? (string) $this->option('from') : null,
                'to'       => $this->option('to') ? (string) $this->option('to') : null,
                'all'      => (bool) $this->option('all'),
                'group_id' => $this->option('group') ? (int) $this->option('group') : null,
            ];

            $result = $scoringService->backfillScores($options);

            if ($result['groups_count'] === 0) {
                $this->warn("لم يتم العثور على مجموعات نشطة مطابقة للاحتساب بأثر رجعي.");
                return Command::SUCCESS;
            }

            $tableRows = [];
            foreach ($result['details'] as $d) {
                $tableRows[] = [
                    $d['group_id'],
                    $d['group_name'],
                    $d['days_count'],
                    $d['from'],
                    $d['to'],
                    $d['records'],
                ];
            }

            $this->table(
                ['ID', 'اسم المجموعة', 'عدد الأيام', 'من تاريخ', 'إلى تاريخ', 'سجلات النقاط'],
                $tableRows
            );

            $this->info("اكتمل الاحتساب بأثر رجعي بنجاح:");
            $this->line("- مجموعات تمت معالجتها: {$result['groups_count']}");
            $this->line("- إجمالي الأيام المعالجة: {$result['total_days_processed']}");
            $this->line("- إجمالي سجلات النقاط المرصدة: {$result['total_records']}");

            return Command::SUCCESS;
        }

        // Single-date processing (Standard Nightly Cron or manual date)
        $targetDate = $this->option('date')
            ? Carbon::parse($this->option('date'), $tz)
            : Carbon::yesterday($tz);

        $dateStr = $targetDate->toDateString();
        $this->info("بدء معالجة نقاط مجموعات المشي لليوم: {$dateStr} (توقيت السعودية)");

        $groupId = $this->option('group') ? (int) $this->option('group') : null;

        if ($groupId) {
            $group = \App\Models\WalkingGroup::find($groupId);
            if (! $group) {
                $this->error("المجموعة رقم {$groupId} غير موجودة.");
                return Command::FAILURE;
            }
            $records = $scoringService->processGroupDailyScore($group, $targetDate);
            $this->info("تم ترصيد {$records} سجل نقاط للمجموعة #{$groupId} ({$group->name}).");
            return Command::SUCCESS;
        }

        $result = $scoringService->processDailyScoresForDate($targetDate);

        $this->info("اكتملت المعالجة بنجاح:");
        $this->line("- مجموعات تمت معالجتها: {$result['groups_processed']}");
        $this->line("- سجلات نقاط تم ترصيدها: {$result['records_recorded']}");

        return Command::SUCCESS;
    }
}

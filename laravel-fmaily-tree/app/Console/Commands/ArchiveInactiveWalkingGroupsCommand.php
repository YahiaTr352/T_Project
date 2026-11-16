<?php

namespace App\Console\Commands;

use App\Models\WalkingGroup;
use App\Models\WalkingRecord;
use App\Services\Walking\WalkingGroupNotificationService;
use App\Services\Walking\WalkingGroupService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ArchiveInactiveWalkingGroupsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'walking:archive-inactive-groups {--days=4 : عدد أيام الخمول المتتالية قبل الأرشفة}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'أرشفة مجموعات وتحديات المشي الخاملة التي لم يسجل أعضاؤها أي خطوات لعدة أيام متتالية تلقائياً.';

    public function __construct(
        protected WalkingGroupService $groupService,
        protected WalkingGroupNotificationService $notificationService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = max(1, (int) ($this->option('days') ?: 4));
        $thresholdDate = Carbon::today()->subDays($days);

        $this->info("بدء فحص تحديات المشي الخاملة (الحد الأدنى للخمول: {$days} أيام - قبل {$thresholdDate->toDateString()})...");

        // Groups that are currently active and were created or started at least $days ago
        $activeGroups = WalkingGroup::where('status', 'active')
            ->where(function ($q) use ($thresholdDate) {
                $q->where('created_at', '<=', $thresholdDate->endOfDay())
                  ->orWhere(function ($sq) use ($thresholdDate) {
                      $sq->whereNotNull('start_date')
                         ->where('start_date', '<=', $thresholdDate->toDateString());
                  });
            })
            ->with(['memberships' => function ($m) {
                $m->where('status', 'accepted');
            }, 'creatorPerson'])
            ->get();

        $archivedCount = 0;

        foreach ($activeGroups as $group) {
            $memberPersonIds = $group->memberships->pluck('person_id')->filter()->unique()->toArray();

            $hasRecentActivity = false;

            if (! empty($memberPersonIds)) {
                // Check if any member logged > 0 steps within the last $days days
                $hasRecentActivity = WalkingRecord::whereIn('person_id', $memberPersonIds)
                    ->where('date', '>=', $thresholdDate->toDateString())
                    ->where('steps', '>', 0)
                    ->exists();

                // If group has a start_date, also check if total steps since start date is 0
                if (! $hasRecentActivity && $group->start_date) {
                    $totalChallengeSteps = (int) WalkingRecord::whereIn('person_id', $memberPersonIds)
                        ->where('date', '>=', $group->start_date->toDateString())
                        ->sum('steps');
                    if ($totalChallengeSteps > 0) {
                        // There was some activity earlier, but none in the last $days days
                        $hasRecentActivity = false;
                    }
                }
            }

            if (! $hasRecentActivity) {
                $this->warn("أرشفة تحدي خامل [ID: {$group->id}] «{$group->name}» (صفر خطوات خلال آخر {$days} أيام).");

                $this->groupService->archiveGroup($group, 'inactivity_auto');

                // Notify creator
                $this->notificationService->notifyGroupArchivedDormant($group);

                Log::info("[WalkingAutoArchive] Archived dormant walking group ID {$group->id} '{$group->name}' due to 0 steps in {$days} days.");

                $archivedCount++;
            }
        }

        $this->info("اكتمل الفحص بنجاح. تم أرشفة {$archivedCount} مجموعة/تحدي خامل.");

        return Command::SUCCESS;
    }
}

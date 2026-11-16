<?php

namespace App\Console\Commands;

use App\Services\Walking\WalkingGroupService;
use Illuminate\Console\Command;

class CheckCompletedWalkingChallengesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'walking:check-completed-challenges';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'فحص التحديات التي انتهت مدتها المحددة، وتحويلها إلى مكتملة، وإرسال تهنئة ختام التحدي لجميع الأعضاء.';

    public function __construct(
        protected WalkingGroupService $groupService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('بدء فحص تحديات المشي المنتهية...');

        $completedCount = $this->groupService->processCompletedChallenges();

        $this->info("تم الانتهاء: تم إغلاق وبث التهنئة لـ {$completedCount} تحدٍّ مكتمل.");

        return Command::SUCCESS;
    }
}

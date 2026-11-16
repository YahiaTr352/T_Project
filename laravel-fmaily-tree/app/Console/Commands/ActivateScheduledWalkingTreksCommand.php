<?php

namespace App\Console\Commands;

use App\Services\Walking\WalkingTrekService;
use Illuminate\Console\Command;

class ActivateScheduledWalkingTreksCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'walking:activate-scheduled-treks';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'فحص وتفعيل مسارات طواف المملكة الافتراضية المجدولة التي حان موعد انطلاقها تلقائياً.';

    public function handle(WalkingTrekService $trekService): int
    {
        $this->info('بدء فحص مسارات المشي المجدولة للتفعيل...');

        $promotedCount = $trekService->promoteReadyScheduledTreks();

        if ($promotedCount > 0) {
            $this->info("تم تفعيل {$promotedCount} مسار طواف مجدول بنجاح وإتاحته على التطبيق.");
        } else {
            $this->line('لا توجد مسارات مجدولة مستحقة للتفعيل في الوقت الحالي.');
        }

        return Command::SUCCESS;
    }
}

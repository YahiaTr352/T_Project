<?php

namespace App\Console\Commands;

use App\Services\Auth\DeviceTrustService;
use Illuminate\Console\Command;

class CheckFaceAuthWhatsappGraceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auth:check-whatsapp-grace';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'فحص الحالات التي انتهت فيها مهلة الـ 24 ساعة دون إضافة رقم واتساب وتصعيد التنبيه لإدارة العائلة.';

    /**
     * Execute the console command.
     */
    public function handle(DeviceTrustService $deviceTrustService): int
    {
        $this->info('بدء فحص مهل الواتساب المنتهية للأجهزة الجديدة...');

        $alertedCount = $deviceTrustService->checkAndAlertGraceExpirations();

        $this->info("تم الانتهاء من الفحص. تم تصعيد {$alertedCount} تنبيه للمديرين والمحررين.");

        return Command::SUCCESS;
    }
}
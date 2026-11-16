<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->job(new \App\Jobs\ScanDataQualityJob)->weeklyOn(0, '03:00');

        // فهرسة يومية للبحث الدلالي (على الشيرد هوست استخدم --sync)
        // العناصر غير المتغيرة تُتخطى تلقائياً (نفس الـ hash) فلا تستهلك حصة API
        $schedule->command('ai:index --sync --only-missing --delay=3 --quota-wait=70')
            ->dailyAt(config('ai.index_schedule_at', '02:30'))
            ->withoutOverlapping(180)
            ->appendOutputTo(storage_path('logs/ai-index.log'))
            ->name('ai-index-daily');

        // أرشفة مجموعات وتحديات المشي الخاملة تلقائياً
        $schedule->command('walking:archive-inactive-groups')
            ->dailyAt('03:30')
            ->withoutOverlapping(60)
            ->appendOutputTo(storage_path('logs/walking-archive.log'))
            ->name('walking-archive-inactive-daily');

        // فحص التحديات المنتهية واكتمالها وبث تهنئة الختام لجميع الأعضاء تلقائياً
        $schedule->command('walking:check-completed-challenges')
            ->dailyAt('00:05')
            ->timezone('Asia/Riyadh')
            ->withoutOverlapping(60)
            ->appendOutputTo(storage_path('logs/walking-completed-challenges.log'))
            ->name('walking-check-completed-challenges-daily');

        // مراجعة تصفير منتصف الليل بتوقيت السعودية: رصد أي سجلات مشبوهة بتاريخ اليوم الجديد
        // (قراءة فقط — لا يعدّل أي بيانات)
        $schedule->command('walking:rollover-verify')
            ->dailyAt('00:05')
            ->timezone('Asia/Riyadh')
            ->withoutOverlapping(60)
            ->appendOutputTo(storage_path('logs/walking-rollover-verify.log'))
            ->name('walking-rollover-verify-daily');

        // Auto reset of the walking day after Saudi midnight + dawn cleanup
        $schedule->command('walking:rollover-reset')
            ->dailyAt('00:01')
            ->timezone('Asia/Riyadh')
            ->withoutOverlapping(60)
            ->appendOutputTo(storage_path('logs/walking-rollover-reset.log'))
            ->name('walking-rollover-reset-daily');

        // احتساب وترصيد نقاط مسابقات المشي لليوم المنقضي بتوقيت السعودية
        $schedule->command('competition:walking-process-daily')
            ->dailyAt('00:02')
            ->timezone('Asia/Riyadh')
            ->withoutOverlapping(60)
            ->appendOutputTo(storage_path('logs/competition-walking-daily.log'))
            ->name('competition-walking-process-daily');

        // احتساب وترصيد نقاط مجموعات المشي اليومية بتوقيت السعودية
        $schedule->command('walking:groups-process-daily-points')
            ->dailyAt('00:03')
            ->timezone('Asia/Riyadh')
            ->withoutOverlapping(60)
            ->appendOutputTo(storage_path('logs/walking-groups-daily-points.log'))
            ->name('walking-groups-process-daily-points');

        // فحص مهلة الـ 24 ساعة للواتساب وتنبيه الإدارة عند الانقضاء
        $schedule->command('auth:check-whatsapp-grace')
            ->hourly()
            ->withoutOverlapping(30)
            ->appendOutputTo(storage_path('logs/auth-whatsapp-grace.log'))
            ->name('auth-check-whatsapp-grace-hourly');

        // فحص وتفعيل مسارات طواف المملكة الافتراضية المجدولة التي حان موعد انطلاقها تلقائياً
        $schedule->command('walking:activate-scheduled-treks')
            ->everyMinute()
            ->timezone('Asia/Riyadh')
            ->withoutOverlapping(10)
            ->appendOutputTo(storage_path('logs/walking-scheduled-treks.log'))
            ->name('walking-activate-scheduled-treks');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}

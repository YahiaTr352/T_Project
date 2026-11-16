<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\NotificationDelivery;
use App\Models\NotificationRecipient;
use App\Models\UltramsgSendLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class NotificationPurgeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:purge
                            {--all : مسح كافة الإشعارات والسجلات بالكامل دون استثناء}
                            {--days= : مسح الإشعارات الأقدم من عدد محدد من الأيام}
                            {--keep-wa-logs : الإبقاء على سجلات الواتساب وعدم مسحها}
                            {--force : تنفيذ العملية دون طلب تأكيد}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'تنظيف ومسح سجلات الإشعارات وصناديق وارد المستلمين بالكامل أو حسب فترة زمنية';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $all = $this->option('all');
        $days = $this->option('days');
        $keepWaLogs = $this->option('keep-wa-logs');
        $force = $this->option('force');

        if (! $all && ! $days) {
            $this->error('يجب تحديد خيار --all لمسح كل شيء أو --days=X لتحديد فترة زمنية.');
            return self::FAILURE;
        }

        $query = Notification::query();

        if ($days) {
            $cutoffDate = now()->subDays((int) $days);
            $query->where('created_at', '<=', $cutoffDate);
            $targetDesc = "الأقدم من {$days} يوم (قبل {$cutoffDate->toDateTimeString()})";
        } else {
            $targetDesc = 'كافة الإشعارات وسجلات التوزيع بالكامل';
        }

        $count = $query->count();

        if ($count === 0) {
            $this->info("لا توجد إشعارات مطابقة للمعايير ({$targetDesc}).");
            return self::SUCCESS;
        }

        if (! $force && ! $this->confirm("هل أنت متأكد من مسح {$count} إشعار ({$targetDesc})؟ سيؤدي ذلك أيضاً لإفراغ صناديق الوارد للأعضاء في التطبيق.", false)) {
            $this->warn('تم إلغاء عملية التنظيف.');
            return self::SUCCESS;
        }

        $this->output->write("جاري تنظيف الإشعارات والملفات المرفقة... ");

        DB::beginTransaction();

        try {
            // 1. مسح وسائط التخزين المرفقة
            $mediaPaths = (clone $query)->whereNotNull('media_path')->pluck('media_path');
            foreach ($mediaPaths as $path) {
                if ($path && Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }

            $notificationIds = (clone $query)->pluck('id');

            // 2. مسح سجلات الواتساب إذا لم يتم استثناؤها
            $waLogsCount = 0;
            if (! $keepWaLogs) {
                $waLogsQuery = UltramsgSendLog::query();
                if ($days) {
                    $waLogsQuery->whereIn('notification_id', $notificationIds)
                        ->orWhere(function ($q) use ($cutoffDate) {
                            $q->whereNull('notification_id')->where('created_at', '<=', $cutoffDate);
                        });
                }
                $waLogsCount = $waLogsQuery->delete();
            }

            // 3. مسح الإشعارات (المستلمون deliveries & recipients يحذفون تلقائياً بفضل الـ Cascade)
            $deletedNotifications = Notification::whereIn('id', $notificationIds)->delete();

            DB::commit();

            Log::channel('single')->info('[NotificationPurgeCommand] Cleaned notifications', [
                'deleted_notifications' => $deletedNotifications,
                'deleted_wa_logs' => $waLogsCount,
                'media_files_removed' => $mediaPaths->count(),
            ]);

            $this->newLine();
            $this->info("✔ تم التنظيف بنجاح:");
            $this->line("  - الإشعارات المحذوفة: {$deletedNotifications}");
            $this->line("  - سجلات الواتساب المحذوفة: {$waLogsCount}");
            $this->line("  - الملفات المرفقة المحذوفة: {$mediaPaths->count()}");
            $this->line("  - تم إفراغ صناديق المستلمين في التطبيق والموقع بالكامل.");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::channel('single')->error('[NotificationPurgeCommand] Failed: ' . $e->getMessage());
            $this->newLine();
            $this->error("حدث خطأ أثناء التنظيف: " . $e->getMessage());

            return self::FAILURE;
        }
    }
}

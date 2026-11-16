<?php

namespace App\Console\Commands;

use App\Models\WalkingDailyReset;
use App\Models\WalkingRecord;
use App\Services\Walking\WalkingRecordService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RolloverResetWalkingCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'walking:rollover-reset';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'تصفير يوم المشي بتوقيت السعودية: سجل reset لليوم الجديد + حذف أي سجلات ملوثة كتبت في أول 5 دقائق بعد منتصف الليل';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tz = WalkingRecordService::KSA_TIMEZONE;
        $now = Carbon::now($tz);
        $todayStr = $now->format('Y-m-d');

        $this->info("تصفير يوم المشي: {$todayStr} (السعودية الآن: {$now->format('H:i:s')})");

        // 1) سجل التصفير اليومي (للتدقيق + ليعتمد عليه الجهاز في إبطال الكاش المحلي)
        $reset = WalkingDailyReset::firstOrCreate(
            ['date' => $todayStr, 'scope' => 'all', 'source' => 'auto'],
            ['reason' => 'تصفير تلقائي بعد منتصف الليل بتوقيت السعودية', 'affected_records' => 0]
        );

        // 2) حذف السجلات الملوثة في نافذة الفجر:
        // أي سجل بتاريخ اليوم أُنشئ بين 00:00 و 06:00 برقم يتجاوز الحد الفيزيائي للوقت الذي سُجل فيه
        $dawnRecords = WalkingRecord::query()
            ->where('date', $todayStr)
            ->where('steps', '>=', WalkingRecordService::DAWN_QUARANTINE_MIN_STEPS)
            ->whereTime('created_at', '<', '06:00:00')
            ->get();

        $deleted = 0;
        foreach ($dawnRecords as $record) {
            $created = Carbon::parse($record->created_at, $tz);
            $mins = max(1, (int) abs($created->diffInMinutes($created->copy()->startOfDay())));
            $maxAllowed = max(800, ($mins * 220) + 800);

            if ((int) $record->steps > $maxAllowed) {
                $this->warn("  حذف سجل ملوث #{$record->id} person={$record->person_id} steps={$record->steps} created_at={$record->created_at} (max was {$maxAllowed})");
                Log::warning('[WalkingRolloverReset] Auto-deleted contaminated dawn record.', $record->toArray());
                $record->delete();
                $deleted++;
            }
        }

        $reset->update(['affected_records' => $deleted]);

        $this->info("سجل التصفير #{$reset->id} — سجلات ملوثة محذوفة: {$deleted}.");

        Log::info('[WalkingRolloverReset] Daily rollover reset completed.', [
            'date'    => $todayStr,
            'reset_id' => $reset->id,
            'deleted' => $deleted,
        ]);

        return Command::SUCCESS;
    }
}
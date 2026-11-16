<?php

namespace App\Console\Commands;

use App\Models\WalkingRecord;
use App\Services\Walking\WalkingRecordService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class VerifyWalkingRolloverCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'walking:rollover-verify';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifies the Saudi-midnight rollover: flags records dated today created in the dawn window with suspiciously high steps, and any future-dated records';

    /**
     * Execute the console command (read-only audit — never modifies data).
     */
    public function handle(): int
    {
        $tz = WalkingRecordService::KSA_TIMEZONE;
        $now = Carbon::now($tz);
        $todayStr = $now->format('Y-m-d');
        $dawnEnd = sprintf('%s %02d:00:00', $todayStr, WalkingRecordService::DAWN_QUARANTINE_END_HOUR);

        $this->info("Verifying walking rollover for {$todayStr} (KSA time: {$now->format('H:i')})...");

        // 1) سجلات بتاريخ اليوم أُنشئت في نافذة الفجر برقم ضخم — مرشحة للتلويث
        $suspicious = WalkingRecord::query()
            ->where('date', $todayStr)
            ->where('steps', '>=', WalkingRecordService::DAWN_QUARANTINE_MIN_STEPS)
            ->where('created_at', '<', $dawnEnd)
            ->orderByDesc('steps')
            ->get(['id', 'person_id', 'date', 'steps', 'source', 'device_info', 'created_at', 'updated_at']);

        if ($suspicious->isNotEmpty()) {
            $this->warn("Found {$suspicious->count()} suspicious dawn record(s) dated today:");
            foreach ($suspicious as $record) {
                $line = "  #{$record->id} person={$record->person_id} steps={$record->steps} source={$record->source} created_at={$record->created_at}";
                $this->line($line);
                Log::warning('[WalkingRolloverVerify] Suspicious dawn record dated today.', $record->toArray());
            }
        } else {
            $this->info('No suspicious dawn records dated today.');
        }

        // 2) سجلات بتاريخ مستقبلي — يجب ألا توجد بعد قفل ثغرة الغد
        $future = WalkingRecord::query()
            ->where('date', '>', $todayStr)
            ->orderBy('date')
            ->get(['id', 'person_id', 'date', 'steps', 'source', 'created_at']);

        if ($future->isNotEmpty()) {
            $this->warn("Found {$future->count()} future-dated record(s):");
            foreach ($future as $record) {
                $line = "  #{$record->id} person={$record->person_id} date={$record->date} steps={$record->steps} source={$record->source}";
                $this->line($line);
                Log::warning('[WalkingRolloverVerify] Future-dated walking record found.', $record->toArray());
            }
        } else {
            $this->info('No future-dated records.');
        }

        // 3) ملخص سجلات اليوم الجديد
        $todayCount = WalkingRecord::where('date', $todayStr)->count();
        $this->info("Records dated today ({$todayStr}): {$todayCount}.");

        Log::info('[WalkingRolloverVerify] Daily rollover verification completed.', [
            'date'       => $todayStr,
            'suspicious' => $suspicious->count(),
            'future'     => $future->count(),
            'today'      => $todayCount,
        ]);

        return Command::SUCCESS;
    }
}

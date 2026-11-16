<?php

namespace App\Services\Walking;

use App\Jobs\SendDailyGoalAchievedJob;
use App\Models\Person;
use App\Models\WalkingClub;
use App\Models\WalkingDailyReset;
use App\Models\WalkingRecord;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Handles step-data ingestion, deduplication, and personal statistics.
 */
class WalkingRecordService
{
    public const KSA_TIMEZONE = 'Asia/Riyadh';

    /**
     * نافذة الفجر بتوقيت السعودية (12:00 صباحاً حتى 06:00 صباحاً).
     * أي مزامنة بتاريخ اليوم داخلها بقيمة ضخمة تُرفض مؤقتاً (422) بدل أن تلتصق
     * في الصدارة عبر حارس max() — والعميل يعيد المحاولة تلقائياً لاحقاً بقيمة حقيقية.
     */
    public const DAWN_QUARANTINE_END_HOUR = 6;
    public const DAWN_QUARANTINE_MIN_STEPS = 5000;
    public const MAX_PLAUSIBLE_DAILY_STEPS = 10000000;
    public const MAX_PLAUSIBLE_STEPS_PER_HOUR = 50000;

    /**
     * هل السقف اليومي للخطوات مفعل؟ (افتراضياً: مفتوح بناءً على رغبة إدارة المشروع)
     */
    public function isDailyCapEnforced(): bool
    {
        return (bool) config('walking.enforce_daily_cap', false);
    }

    /**
     * أقصى عدد خطوات يومي مسموح به في حال تفعيل السقف.
     */
    public function getMaxDailySteps(): int
    {
        return (int) config('walking.max_daily_steps', self::MAX_PLAUSIBLE_DAILY_STEPS);
    }

    /**
     * Upsert a single day's walking record for a person.
     *
     * @param  array{date: string, steps: int, distance_km?: float, duration_minutes?: int, calories?: int, source?: string, device_info?: string, notes?: string, is_authoritative?: bool}  $data
     */
    public function syncForPerson(int $personId, array $data): WalkingRecord
    {
        // whereDate (وليس where) حتى تطابق المقارنة على كل قواعد البيانات
        // مهما كانت صيغة تخزين عمود التاريخ (Y-m-d أو Y-m-d H:i:s)
        $existing = WalkingRecord::where('person_id', $personId)
            ->whereDate('date', $data['date'])
            ->first();

        $incomingSteps = max(0, (int) $data['steps']);
        if ($this->isDailyCapEnforced()) {
            $incomingSteps = min($this->getMaxDailySteps(), $incomingSteps);
        }

        // ── حارس السرعة الفيزيائية الفوري بعد منتصف الليل بتوقيت السعودية (Root-Level Velocity Guard)
        // مفعل دائماً ومستقل عن سقف اليوم لحماية نافذة الفجر (00:00 - 06:00 ص) من تسرب خطوات الأمس
        $ksaNow = Carbon::now(self::KSA_TIMEZONE);
        $todayStr = $ksaNow->format('Y-m-d');

        if ($data['date'] === $todayStr && $ksaNow->hour < self::DAWN_QUARANTINE_END_HOUR) {
            $minutesSinceMidnight = max(1, (int) abs($ksaNow->diffInMinutes($ksaNow->copy()->startOfDay())));
            // معدل المشي/الجري الأقصى في الدقيقة (~220 خطوة) + هامش أمان أولي 800 خطوة لأول دقيقتين
            $maxDawnSteps = max(800, ($minutesSinceMidnight * 220) + 800);

            if ($incomingSteps > $maxDawnSteps) {
                Log::warning('[WalkingRecordService] Dawn Velocity Guard: rejected physically impossible step count for new day.', [
                    'person_id'               => $personId,
                    'date'                    => $data['date'],
                    'incoming_steps'          => $incomingSteps,
                    'max_allowed_at_minute'   => $maxDawnSteps,
                    'minutes_since_midnight'  => $minutesSinceMidnight,
                    'ksa_time'                => $ksaNow->format('H:i:s'),
                    'source'                  => $data['source'] ?? null,
                ]);

                throw ValidationException::withMessages([
                    'steps' => ["عدد الخطوات ({$incomingSteps}) مستحيل فيزيائياً بعد منتصف الليل بـ {$minutesSinceMidnight} دقيقة."],
                ]);
            }
        }

        // Reset-aware & Monotonic Non-Decreasing write:
        // 1. If a reset (manual/auto) is newer than the record, take incomingSteps as-is (administrative reset).
        // 2. If daily cap is enforced and existing DB record exceeds cap, clamp it to the cap.
        // 3. Authoritative dawn downward correction: if today's record was first created in the
        //    dawn window (00:00–06:00 KSA — the suspicious first write that may carry yesterday's
        //    total) and a newer authoritative read arrives with a smaller value for the same day,
        //    accept it instead of max(). This heals carryover automatically without manual resets,
        //    and never affects runners (their numbers only increase, so max() still applies).
        // 4. Otherwise enforce the Monotonic Invariant (never decrease within the same day).
        $reset = WalkingDailyReset::latestFor($data['date'], $personId);

        if ($reset && $existing && $existing->updated_at && $reset->created_at >= $existing->updated_at) {
            $steps = $incomingSteps;
        } elseif ($this->isDailyCapEnforced() && $existing && (int) $existing->steps > $this->getMaxDailySteps()) {
            $steps = min($this->getMaxDailySteps(), $incomingSteps);
        } elseif (
            $existing
            && (bool) ($data['is_authoritative'] ?? false)
            && $incomingSteps < (int) $existing->steps
            && $data['date'] === $todayStr
        ) {
            $createdKsa = Carbon::parse($existing->created_at)->setTimezone(self::KSA_TIMEZONE);
            if ($createdKsa->format('Y-m-d') === $todayStr && $createdKsa->hour < self::DAWN_QUARANTINE_END_HOUR) {
                Log::info('[WalkingRecordService] Authoritative dawn downward correction accepted.', [
                    'person_id'      => $personId,
                    'date'           => $data['date'],
                    'from_steps'     => (int) $existing->steps,
                    'to_steps'       => $incomingSteps,
                    'record_created' => $existing->created_at,
                    'ksa_time'       => $ksaNow->format('H:i:s'),
                    'source'         => $data['source'] ?? null,
                ]);
                $steps = $incomingSteps;
            } else {
                $steps = max($incomingSteps, (int) $existing->steps);
            }
        } else {
            $steps = $existing ? max($incomingSteps, (int) $existing->steps) : $incomingSteps;
        }

        // Optionally link user_id if person has an associated dashboard account
        $linkedUserId = $existing?->user_id;
        if (! $linkedUserId) {
            try {
                $linkedUserId = \App\Models\User::where('person_id', $personId)->value('id');
            } catch (\Throwable $e) {}
        }

        $attributes = [
            'person_id' => $personId,
            'date'      => $data['date'],
        ];

        $deviceInfo = $data['device_info'] ?? ($existing?->device_info ?? null);
        if (empty($deviceInfo)) {
            try {
                $latestDevice = \App\Models\UserDevice::where('person_id', $personId)->orderByDesc('last_active_at')->first();
                if ($latestDevice) {
                    $parts = array_filter([$latestDevice->device_name, $latestDevice->device_model ?: $latestDevice->os_platform]);
                    $deviceInfo = implode(' ', $parts);
                }
            } catch (\Throwable $e) {}
        }

        $rawCalories = $data['calories'] ?? ($existing?->calories ?? null);
        $safeCalories = $rawCalories !== null ? min(65535, max(0, (int) $rawCalories)) : null;

        $values = [
            'steps'            => $steps,
            'distance_km'      => $data['distance_km'] ?? $this->estimateDistanceKm($steps),
            'duration_minutes' => $data['duration_minutes'] ?? ($existing?->duration_minutes ?? 0),
            'calories'         => $safeCalories,
            'source'           => $data['source'] ?? ($existing?->source ?? 'manual'),
            'device_info'      => $deviceInfo,
            'notes'            => $data['notes'] ?? ($existing?->notes ?? null),
        ];

        if ($linkedUserId) {
            $values['user_id'] = $linkedUserId;
        }

        // تحديث/إنشاء صريح بدل updateOrCreate: بحث updateOrCreate الداخلي يستخدم where
        // بالنص الكامل فيفشل في مطابقة عمود التاريخ على بعض قواعد البيانات، بينما
        // البحث أعلاه يستخدم whereDate — هنا نعيد استخدام نفس السجل الموجود يقيناً.
        if ($existing) {
            $existing->update($values);
            $record = $existing;
        } else {
            $record = WalkingRecord::create($attributes + $values);
        }

        // Daily Goal Check (Idempotent: triggers once per calendar day, KSA time)
        $dailyGoal = (int) config('walking.daily_goal_steps', 10000);

        if ($data['date'] === $todayStr && (int) $record->steps >= $dailyGoal && is_null($record->daily_goal_notified_at)) {
            // Atomic conditional update on DB row to prevent parallel sync requests from dispatching twice
            $affected = WalkingRecord::where('id', $record->id)
                ->whereNull('daily_goal_notified_at')
                ->update(['daily_goal_notified_at' => now()]);

            if ($affected > 0) {
                $record->daily_goal_notified_at = now();

                SendDailyGoalAchievedJob::dispatch(
                    $personId,
                    (int) $record->steps,
                    $dailyGoal,
                    $todayStr
                );
            }
        }

        // Check active challenge completions for this person
        try {
            app(WalkingGroupService::class)->checkAndMarkMemberChallengeCompletion($personId);
        } catch (\Throwable $e) {
            Log::warning('[WalkingRecordService] Challenge check error: ' . $e->getMessage());
        }

        return $record->fresh();
    }

    /**
     * Upsert multiple days in a single database transaction.
     *
     * @param  array<int, array{date: string, steps: int, ...}>  $records
     * @return array{synced: int, skipped: int}
     */
    public function batchSyncForPerson(int $personId, array $records): array
    {
        $synced  = 0;
        $skipped = 0;

        DB::transaction(function () use ($personId, $records, &$synced, &$skipped) {
            foreach ($records as $payload) {
                // Skip records with invalid or future dates (KSA calendar day)
                try {
                    $date = Carbon::parse($payload['date']);
                } catch (\Throwable) {
                    $skipped++;
                    continue;
                }

                if ($date->format('Y-m-d') > Carbon::today(self::KSA_TIMEZONE)->format('Y-m-d')) {
                    $skipped++;
                    continue;
                }

                try {
                    $this->syncForPerson($personId, $payload);
                    $synced++;
                } catch (ValidationException $e) {
                    // سجل مرفوض مؤقتاً (مثل حماية الفجر) — نتخطاه دون إفشال باقي الدفعة
                    Log::info('[WalkingRecordService] Batch sync skipped quarantined record.', [
                        'person_id' => $personId,
                        'date'      => $payload['date'] ?? null,
                        'steps'     => $payload['steps'] ?? null,
                    ]);
                    $skipped++;
                }
            }
        });

        return compact('synced', 'skipped');
    }

    /**
     * Reset one person day: delete day records + audit reset row.
     * Device drops its cached value via reset_id.
     */
    public function resetDayForPerson(int $personId, string $date, ?int $byUserId = null, string $source = 'manual', ?string $reason = null): array
    {
        $count = WalkingRecord::forPerson($personId)->forDate($date)->count();
        WalkingRecord::forPerson($personId)->forDate($date)->delete();

        $reset = WalkingDailyReset::create([
            'date'             => $date,
            'person_id'        => $personId,
            'scope'            => 'person',
            'reset_by'         => $byUserId,
            'source'           => $source,
            'reason'           => $reason,
            'affected_records' => $count,
        ]);

        Log::info('[WalkingRecordService] Manual reset for person day.', [
            'person_id' => $personId,
            'date'      => $date,
            'deleted'   => $count,
            'reset_id'  => $reset->id,
            'by'        => $byUserId,
            'source'    => $source,
        ]);

        return ['deleted' => $count, 'reset_id' => $reset->id];
    }

    /**
     * Reset one day for everyone: delete day records + audit reset row.
     * Device drops its cached value via reset_id.
     */
    public function resetDayForAll(string $date, ?int $byUserId = null, string $source = 'manual', ?string $reason = null): array
    {
        $count = WalkingRecord::forDate($date)->count();
        WalkingRecord::forDate($date)->delete();

        $reset = WalkingDailyReset::create([
            'date'             => $date,
            'person_id'        => null,
            'scope'            => 'all',
            'reset_by'         => $byUserId,
            'source'           => $source,
            'reason'           => $reason,
            'affected_records' => $count,
        ]);

        Log::info('[WalkingRecordService] Manual reset for all.', [
            'date'     => $date,
            'deleted'  => $count,
            'reset_id' => $reset->id,
            'by'       => $byUserId,
        ]);

        return ['deleted' => $count, 'reset_id' => $reset->id];
    }

    /**
     * Automatically purge any corrupted/inflated records (> MAX_PLAUSIBLE_DAILY_STEPS)
     * and issue a WalkingDailyReset so client devices immediately wipe their poisoned local cache
     * and re-sync the true hardware step count.
     */
    public function purgeImplausibleRecords(?int $personId = null): int
    {
        if (! $this->isDailyCapEnforced()) {
            return 0;
        }

        $query = WalkingRecord::where('steps', '>', $this->getMaxDailySteps());
        if ($personId !== null) {
            $query->where('person_id', $personId);
        }

        $corrupted = $query->get();
        if ($corrupted->isEmpty()) {
            return 0;
        }

        $purged = 0;
        foreach ($corrupted as $record) {
            $dateStr = Carbon::parse($record->date, self::KSA_TIMEZONE)->format('Y-m-d');
            if ($record->person_id) {
                $this->resetDayForPerson(
                    (int) $record->person_id,
                    $dateStr,
                    null,
                    'auto_anomaly_guard',
                    "تصفير تلقائي لقيمة غير منطقية ({$record->steps} خطوة) لإعادة المزامنة مع تطبيق صحتي"
                );
            } else {
                $record->delete();
            }
            $purged++;
        }

        return $purged;
    }

    /**
     * Build a personal statistics summary for a person.
     *
     * @return array{today_steps: int, weekly_steps: int, monthly_steps: int, all_time_steps: int, current_streak: int, longest_streak: int, best_day_steps: int, avg_daily_steps: float, total_days_active: int}
     */
    public function personalStats(int $personId): array
    {
        $this->purgeImplausibleRecords($personId);

        $today        = Carbon::today('Asia/Riyadh');
        $startOfWeek  = Carbon::now('Asia/Riyadh')->startOfWeek(CarbonInterface::SATURDAY);
        $startOfMonth = Carbon::now('Asia/Riyadh')->startOfMonth();

        $base = WalkingRecord::forPerson($personId);

        $todaySteps   = (clone $base)->forDate($today)->value('steps') ?? 0;
        $weeklySteps  = (clone $base)->forDateRange($startOfWeek, $today)->sum('steps');
        $monthlySteps = (clone $base)->forDateRange($startOfMonth, $today)->sum('steps');
        $allTimeSteps = (clone $base)->sum('steps');
        $bestDaySteps = (clone $base)->max('steps') ?? 0;
        $totalDays    = (clone $base)->count();
        $avgDaily     = $totalDays > 0 ? round($allTimeSteps / $totalDays, 1) : 0;

        [$currentStreak, $longestStreak] = $this->calculateStreaks($personId);
        $lbService = new WalkingLeaderboardService();

        // Calculate dynamic club streaks respecting each club's calculation_start_date
        $activeClubs = WalkingClub::getActiveClubs();
        $clubStreaks = [];
        foreach ($activeClubs as $club) {
            $startDate = $club->calculation_start_date ? Carbon::parse($club->calculation_start_date) : null;
            $cStreak = $lbService->calculateStreaksForPersons([$personId], (int) $club->min_steps, $today, $startDate)[$personId] ?? 0;
            $clubStreaks[$club->slug] = $cStreak;
        }

        $streak5k  = $clubStreaks['club_5k'] ?? ($lbService->calculateStreaksForPersons([$personId], 5000, $today)[$personId] ?? 0);
        $streak10k = $clubStreaks['club_10k'] ?? ($lbService->calculateStreaksForPersons([$personId], 10000, $today)[$personId] ?? 0);
        $streak20k = $clubStreaks['club_20k'] ?? ($lbService->calculateStreaksForPersons([$personId], 20000, $today)[$personId] ?? 0);

        return [
            'date'              => $today->toDateString(),
            'server_time'       => Carbon::now('Asia/Riyadh')->toIso8601String(),
            'reset_id'          => WalkingDailyReset::latestFor($today->toDateString(), $personId)?->id,
            'today_steps'       => (int) $todaySteps,
            'weekly_steps'      => (int) $weeklySteps,
            'monthly_steps'     => (int) $monthlySteps,
            'all_time_steps'    => (int) $allTimeSteps,
            'current_streak'    => $currentStreak,
            'longest_streak'    => $longestStreak,
            'streak_5k'         => $streak5k,
            'streak_10k'        => $streak10k,
            'streak_20k'        => $streak20k,
            'club_streaks'      => $clubStreaks,
            'best_day_steps'    => (int) $bestDaySteps,
            'avg_daily_steps'   => (float) $avgDaily,
            'total_days_active' => $totalDays,
        ];
    }

    /**
     * Compute unbroken active streak and longest history streak of reaching >= 10,000 steps.
     *
     * @return array{0: int, 1: int}  [currentStreak, longestStreak]
     */
    public function calculateStreaks(int $personId): array
    {
        $dates = WalkingRecord::forPerson($personId)
            ->where('steps', '>=', 10000)
            ->orderByDesc('date')
            ->pluck('date')
            ->map(fn ($d) => Carbon::parse($d, 'Asia/Riyadh')->format('Y-m-d'))
            ->unique()
            ->values()
            ->toArray();

        if (empty($dates)) {
            return [0, 0];
        }

        $today     = Carbon::today('Asia/Riyadh')->format('Y-m-d');
        $yesterday = Carbon::yesterday('Asia/Riyadh')->format('Y-m-d');

        $dateSet = array_fill_keys($dates, true);

        // Current active streak calculation (must be unbroken up to yesterday or today)
        $currentStreak = 0;
        $checkDate = null;

        if (isset($dateSet[$today])) {
            $currentStreak = 1;
            $checkDate = Carbon::yesterday('Asia/Riyadh');
        } elseif (isset($dateSet[$yesterday])) {
            // Today is ongoing (<10k so far), but yesterday reached >=10k -> streak is active starting from yesterday
            $currentStreak = 0;
            $checkDate = Carbon::yesterday('Asia/Riyadh');
        } else {
            // Neither today nor yesterday reached 10,000 steps -> streak is broken to 0 immediately!
            return [0, 0];
        }

        if ($checkDate !== null) {
            while (isset($dateSet[$checkDate->format('Y-m-d')])) {
                $currentStreak++;
                $checkDate->subDay();
            }
        }

        // Longest streak across all history (ascending scan)
        $ascending   = array_reverse($dates);
        $maxStreak   = 1;
        $runStreak   = 1;
        for ($i = 1; $i < count($ascending); $i++) {
            $prev = Carbon::parse($ascending[$i - 1]);
            $curr = Carbon::parse($ascending[$i]);
            if ($curr->diffInDays($prev) === 1) {
                $runStreak++;
                $maxStreak = max($maxStreak, $runStreak);
            } else {
                $runStreak = 1;
            }
        }

        return [$currentStreak, max($maxStreak, $currentStreak)];
    }

    /**
     * Rough distance estimate when the app doesn't supply it (average stride = 0.762 m).
     */
    private function estimateDistanceKm(int $steps): float
    {
        return round($steps * 0.000762, 2);
    }
}

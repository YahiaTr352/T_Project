<?php

namespace App\Jobs;

use App\Models\Person;
use App\Services\Walking\WalkingGroupNotificationService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Asynchronously sends daily goal achievement notifications (Push + WhatsApp + In-App).
 * Enforces strict idempotency via ShouldBeUnique and atomic cache locks.
 */
class SendDailyGoalAchievedJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 60;
    public int $uniqueFor = 86400; // 24 hours lock

    public string $dateStr;

    public function __construct(
        public int $personId,
        public int $actualSteps,
        public int $targetGoal = 10000,
        ?string $date = null
    ) {
        $this->dateStr = $date ?? Carbon::today('Asia/Riyadh')->toDateString();
    }

    /**
     * Unique key for queue deduplication per person and per calendar day.
     */
    public function uniqueId(): string
    {
        return "daily_goal_{$this->personId}_{$this->dateStr}";
    }

    public function handle(WalkingGroupNotificationService $notificationService): void
    {
        // Atomic lock to guarantee single execution even across concurrent queue workers
        $lockKey = "lock:daily_goal:{$this->personId}:{$this->dateStr}";
        $lock = Cache::lock($lockKey, 120);

        if (! $lock->get()) {
            Log::channel('single')->warning("[SendDailyGoalAchievedJob] Concurrent duplicate suppressed for Person #{$this->personId} on {$this->dateStr}.");
            return;
        }

        try {
            $person = Person::withoutGlobalScopes()->find($this->personId);
            if (! $person) {
                Log::channel('single')->warning("[SendDailyGoalAchievedJob] Person #{$this->personId} not found.");
                return;
            }

            Log::channel('single')->info("[SendDailyGoalAchievedJob] Dispatching daily goal notification for Person #{$this->personId} ({$this->actualSteps} / {$this->targetGoal} steps on {$this->dateStr}).");

            $notificationService->notifyDailyGoalReached(
                $person,
                $this->actualSteps,
                $this->targetGoal,
                $this->dateStr
            );
        } finally {
            $lock->release();
        }
    }
}

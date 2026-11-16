<?php

namespace App\Jobs;

use App\Models\Person;
use App\Models\WalkingGroup;
use App\Services\Walking\WalkingGroupNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Asynchronously sends challenge target achievement notifications to an individual member.
 * Enforces strict idempotency via ShouldBeUnique and atomic cache locks.
 */
class SendChallengeGoalAchievedJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 60;
    public int $uniqueFor = 604800; // 7 days lock per group and person

    public function __construct(
        public int $groupId,
        public int $personId,
        public string $durationText
    ) {}

    /**
     * Unique key for queue deduplication per challenge and per member.
     */
    public function uniqueId(): string
    {
        return "challenge_goal_{$this->groupId}_{$this->personId}";
    }

    public function handle(WalkingGroupNotificationService $notificationService): void
    {
        // Atomic lock to guarantee single execution even across concurrent queue workers
        $lockKey = "lock:challenge_goal:{$this->groupId}:{$this->personId}";
        $lock = Cache::lock($lockKey, 120);

        if (! $lock->get()) {
            Log::channel('single')->warning("[SendChallengeGoalAchievedJob] Concurrent duplicate suppressed for Person #{$this->personId} in Group #{$this->groupId}.");
            return;
        }

        try {
            $group = WalkingGroup::find($this->groupId);
            if (! $group) {
                Log::channel('single')->warning("[SendChallengeGoalAchievedJob] Group #{$this->groupId} not found.");
                return;
            }

            $person = Person::withoutGlobalScopes()->find($this->personId);
            if (! $person) {
                Log::channel('single')->warning("[SendChallengeGoalAchievedJob] Person #{$this->personId} not found.");
                return;
            }

            Log::channel('single')->info("[SendChallengeGoalAchievedJob] Dispatching challenge goal notification for Person #{$this->personId} in Group #{$this->groupId} ({$this->durationText}).");

            $notificationService->notifyMemberGoalAchieved(
                $group,
                $person,
                $this->durationText
            );
        } finally {
            $lock->release();
        }
    }
}

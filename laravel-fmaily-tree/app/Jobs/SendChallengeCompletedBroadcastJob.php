<?php

namespace App\Jobs;

use App\Models\WalkingGroup;
use App\Services\Walking\WalkingGroupNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Asynchronously broadcasts challenge completion congratulatory notification to all members.
 */
class SendChallengeCompletedBroadcastJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 300;

    public function __construct(
        public int $groupId
    ) {}

    public function handle(WalkingGroupNotificationService $notificationService): void
    {
        $group = WalkingGroup::find($this->groupId);
        if (!$group) {
            Log::channel('single')->warning("[SendChallengeCompletedBroadcastJob] Group #{$this->groupId} not found.");
            return;
        }

        Log::channel('single')->info("[SendChallengeCompletedBroadcastJob] Broadcasting completion notification for Group #{$group->id} ({$group->name}).");

        $notificationService->notifyChallengeCompletedToAllMembers($group);
    }
}

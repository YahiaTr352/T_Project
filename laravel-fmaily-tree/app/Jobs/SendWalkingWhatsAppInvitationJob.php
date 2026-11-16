<?php

namespace App\Jobs;

use App\Models\Person;
use App\Models\WalkingGroup;
use App\Services\Walking\WalkingGroupNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Handles individual staggered WhatsApp dispatch for a member added to a walking group.
 * Respects anti-ban delays without blocking a monolithic queue job worker thread.
 */
class SendWalkingWhatsAppInvitationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 60;

    public function __construct(
        public int $groupId,
        public int $personId,
        public ?string $inviterName = null,
        public ?int $notificationId = null
    ) {}

    public function handle(WalkingGroupNotificationService $notificationService): void
    {
        $group = WalkingGroup::find($this->groupId);
        if (!$group) {
            Log::channel('ultramsg')->warning("[SendWalkingWhatsAppInvitationJob] Walking group #{$this->groupId} not found.");
            return;
        }

        $person = Person::withoutGlobalScopes()->find($this->personId);
        if (!$person) {
            Log::channel('ultramsg')->warning("[SendWalkingWhatsAppInvitationJob] Person #{$this->personId} not found.");
            return;
        }

        $notificationService->sendWhatsAppAdditionMessage(
            $group,
            $person,
            $this->inviterName,
            $this->notificationId
        );
    }
}

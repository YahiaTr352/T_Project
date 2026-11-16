<?php

namespace App\Jobs;

use App\Models\Person;
use App\Models\WalkingGroup;
use App\Services\Walking\WalkingGroupNotificationService;
use App\Support\NotificationSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Handles asynchronous notification dispatching for bulk-added
 * or bulk-invited walking group participants.
 * Fully complies with NotificationSettings (Push/WhatsApp channels, daily limits, anti-ban throttling).
 */
class DispatchBulkGroupInvitationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 600; // 10 minutes max for large audiences

    /**
     * @param int $groupId
     * @param array<int> $personIds
     * @param int|null $inviterPersonId
     * @param string $membershipStatus 'accepted' or 'invited'
     */
    public function __construct(
        public int $groupId,
        public array $personIds,
        public ?int $inviterPersonId = null,
        public string $membershipStatus = 'accepted'
    ) {}

    public function handle(WalkingGroupNotificationService $notificationService): void
    {
        $group = WalkingGroup::find($this->groupId);
        if (!$group) {
            Log::channel('single')->warning("[DispatchBulkGroupInvitationsJob] Group #{$this->groupId} not found. Aborting.");
            return;
        }

        $inviter = $this->inviterPersonId ? Person::find($this->inviterPersonId) : null;
        $uniquePersonIds = array_values(array_unique(array_filter($this->personIds)));
        $total = count($uniquePersonIds);

        Log::channel('single')->info("[DispatchBulkGroupInvitationsJob] Starting bulk notifications for group #{$group->id} ({$group->name}) to {$total} people.");

        $successCount = 0;
        $errorCount = 0;
        $allProcessedPeople = [];

        // 1. Process In-App & Mobile Push in chunks of 50
        $chunks = array_chunk($uniquePersonIds, 50);

        foreach ($chunks as $chunkIds) {
            $people = Person::withoutGlobalScopes()
                ->whereIn('id', $chunkIds)
                ->whereNull('death_date')
                ->get();

            foreach ($people as $person) {
                try {
                    // Send In-App & Mobile Push (defer WhatsApp to staggered anti-ban jobs)
                    $notificationService->notifyMemberAdded($group, $person, $inviter, sendWhatsApp: false);
                    $successCount++;
                    $allProcessedPeople[] = $person;
                } catch (\Throwable $e) {
                    $errorCount++;
                    Log::channel('single')->error("[DispatchBulkGroupInvitationsJob] Failed notifying person #{$person->id}: {$e->getMessage()}");
                }
            }

            usleep(25000); // 25ms micro-pause
        }

        // 2. Schedule Staggered Anti-Ban WhatsApp Invitations if channel is enabled
        if (NotificationSettings::canSendWhatsApp()) {
            $whatsappEligible = [];
            foreach ($allProcessedPeople as $person) {
                if ($notificationService->resolvePersonPhone($person)) {
                    $whatsappEligible[] = $person;
                }
            }

            // Enforce Daily Quota Cap
            $quota = NotificationSettings::dailyQuotaStats();
            if ($quota['has_limit'] && $quota['remaining'] !== null) {
                if ($quota['remaining'] <= 0) {
                    Log::channel('ultramsg')->warning("[DispatchBulkGroupInvitationsJob] Daily WhatsApp quota reached. Skipping all WhatsApp invites.");
                    $whatsappEligible = [];
                } elseif (count($whatsappEligible) > $quota['remaining']) {
                    Log::channel('ultramsg')->warning("[DispatchBulkGroupInvitationsJob] Limiting WhatsApp invites from " . count($whatsappEligible) . " to remaining {$quota['remaining']}.");
                    $whatsappEligible = array_slice($whatsappEligible, 0, $quota['remaining']);
                }
            }

            if (!empty($whatsappEligible)) {
                $delays = NotificationSettings::calculateStaggeredDelays(count($whatsappEligible));
                $inviterName = $inviter?->full_name ?? $group->creatorPerson?->full_name;

                foreach ($whatsappEligible as $index => $person) {
                    $delaySeconds = $delays[$index] ?? 0;
                    SendWalkingWhatsAppInvitationJob::dispatch($group->id, $person->id, $inviterName)
                        ->delay(now()->addSeconds($delaySeconds));
                }

                Log::channel('ultramsg')->info("[DispatchBulkGroupInvitationsJob] Dispatched " . count($whatsappEligible) . " staggered WhatsApp invitation jobs for group #{$group->id}.");
            }
        } else {
            Log::channel('ultramsg')->info("[DispatchBulkGroupInvitationsJob] WhatsApp skipped: channel disabled or daily quota exceeded.");
        }

        Log::channel('single')->info("[DispatchBulkGroupInvitationsJob] Finished for group #{$group->id}. Sent: {$successCount}, Errors: {$errorCount}.");
    }
}

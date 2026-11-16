<?php

namespace App\Services\Invitations;

use App\Exceptions\InvitationException;
use App\Jobs\SendExpoPushNotificationJob;
use App\Jobs\SendWhatsAppInvitationJob;
use App\Models\Notification;
use App\Models\Person;
use App\Models\User;
use App\Models\UserDevice;
use App\Support\NotificationSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InvitationApprovalService
{
    public function __construct(
        protected InvitationMessageService $messages,
        protected InvitationGroupService $groups,
    ) {}

    /**
     * Approve a pending invitation and dispatch its jobs.
     *
     * @return array{notification: Notification, whatsapp_jobs: int, push_jobs: int}
     */
    public function approve(Notification $notification, User $editor): array
    {
        return DB::transaction(function () use ($notification, $editor) {
            $locked = Notification::query()
                ->where('id', $notification->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'pending_approval') {
                throw new InvitationException('هذه الدعوة تمت مراجعتها مسبقاً أو ليست قيد الانتظار.', 'INVALID_STATUS');
            }

            $locked->update([
                'status' => 'approved',
                'approved_by' => $editor->id,
                'approved_at' => now(),
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
            ]);

            $dispatchResults = $this->dispatchJobs($locked);

            $locked->update([
                'status' => 'sending',
                'sent_at' => now(),
            ]);

            return [
                'notification' => $locked->fresh(['recipients', 'person', 'approvedBy']),
                'whatsapp_jobs' => $dispatchResults['whatsapp_jobs'],
                'push_jobs' => $dispatchResults['push_jobs'],
            ];
        });
    }

    /**
     * Reject a pending invitation with a reason.
     */
    public function reject(Notification $notification, User $editor, string $reason): Notification
    {
        return DB::transaction(function () use ($notification, $editor, $reason) {
            $locked = Notification::query()
                ->where('id', $notification->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'pending_approval') {
                throw new InvitationException('هذه الدعوة تمت مراجعتها مسبقاً أو ليست قيد الانتظار.', 'INVALID_STATUS');
            }

            $locked->update([
                'status' => 'rejected',
                'rejected_by' => $editor->id,
                'rejected_at' => now(),
                'rejection_reason' => trim($reason),
            ]);

            // إشعار صاحب الدعوة عبر Push إن وُجد
            $this->notifySenderOfDecision($locked, 'rejected', $reason);

            return $locked->fresh(['person', 'rejectedBy']);
        });
    }

    /**
     * Update title/body and approve.
     *
     * @param  array{title?: string|null, body?: string}  $payload
     * @return array{notification: Notification, whatsapp_jobs: int, push_jobs: int}
     */
    public function updateAndApprove(Notification $notification, User $editor, array $payload): array
    {
        return DB::transaction(function () use ($notification, $editor, $payload) {
            $locked = Notification::query()
                ->where('id', $notification->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'pending_approval') {
                throw new InvitationException('هذه الدعوة تمت مراجعتها مسبقاً أو ليست قيد الانتظار.', 'INVALID_STATUS');
            }

            $updateData = [];
            if (isset($payload['title'])) {
                $updateData['title'] = $payload['title'];
            }
            if (isset($payload['body']) && trim($payload['body']) !== '') {
                $updateData['body'] = $payload['body'];
            }

            $updateData['status'] = 'approved';
            $updateData['approved_by'] = $editor->id;
            $updateData['approved_at'] = now();
            $updateData['rejected_by'] = null;
            $updateData['rejected_at'] = null;
            $updateData['rejection_reason'] = null;

            $locked->update($updateData);

            $dispatchResults = $this->dispatchJobs($locked);

            $locked->update([
                'status' => 'sending',
                'sent_at' => now(),
            ]);

            return [
                'notification' => $locked->fresh(['recipients', 'person', 'approvedBy']),
                'whatsapp_jobs' => $dispatchResults['whatsapp_jobs'],
                'push_jobs' => $dispatchResults['push_jobs'],
            ];
        });
    }

    /**
     * Bulk approve multiple invitations.
     *
     * @param  list<int>  $notificationIds
     * @return array{approved_count: int, failed_count: int}
     */
    public function bulkApprove(array $notificationIds, User $editor): array
    {
        $approvedCount = 0;
        $failedCount = 0;

        foreach ($notificationIds as $id) {
            $notification = Notification::find($id);
            if (! $notification || $notification->status !== 'pending_approval') {
                $failedCount++;
                continue;
            }

            try {
                $this->approve($notification, $editor);
                $approvedCount++;
            } catch (\Throwable $e) {
                Log::channel('single')->error("Bulk approve failed for notification {$id}: " . $e->getMessage());
                $failedCount++;
            }
        }

        return [
            'approved_count' => $approvedCount,
            'failed_count' => $failedCount,
        ];
    }

    /**
     * Dispatch queue jobs for all recipients of the notification.
     *
     * @return array{whatsapp_jobs: int, push_jobs: int}
     */
    public function dispatchJobs(Notification $notification): array
    {
        $sender = $notification->person;
        $channels = is_array($notification->channels) ? $notification->channels : [];
        $targetingFilters = is_array($notification->targeting_filters) ? $notification->targeting_filters : [];
        $templateVars = is_array($targetingFilters['template_vars'] ?? null) ? $targetingFilters['template_vars'] : [];
        $body = (string) $notification->body;
        $body = $this->messages->withSenderSignature($body, $sender);
        $title = (string) ($notification->title ?: 'تنبيه عائلي');
        $mediaType = $notification->media_type;
        $mediaPath = $notification->media_path;

        // جلب المستلمين المسجلين
        $recipients = $notification->recipients()->with('person.contactAccounts')->get();
        $recipientPersons = $recipients->pluck('person')->filter();

        $whatsappJobs = 0;
        $pushJobs = 0;

        if (in_array('whatsapp', $channels, true)) {
            $waTargets = [];
            foreach ($recipients as $recipient) {
                if ($recipient->person) {
                    $person = $recipient->person;
                    $numbers = $this->groups->whatsAppNumbers($person);
                    if ($numbers === []) {
                        continue;
                    }

                    $messageBody = $this->messages->replacePlaceholders($body, $person, $sender, $templateVars);
                    $waTargets[] = [
                        'person_id' => $person->id,
                        'number' => $numbers[0],
                        'body' => $messageBody,
                        'name' => $person->first_name ?: $person->full_name,
                    ];
                } elseif (! empty($recipient->recipient_phone)) {
                    $guestName = $recipient->recipient_name ?: 'ضيفنا العزيز';
                    $messageBody = $this->messages->replacePlaceholders($body, null, $sender, array_merge($templateVars, [
                        '{name}' => $guestName,
                        '{first_name}' => $guestName,
                        '{full_name}' => $guestName,
                    ]));

                    $waTargets[] = [
                        'person_id' => null,
                        'number' => $recipient->recipient_phone,
                        'body' => $messageBody,
                        'name' => $guestName,
                    ];
                }
            }

            $delays = NotificationSettings::calculateStaggeredDelays(count($waTargets));

            foreach ($waTargets as $idx => $target) {
                $delaySeconds = $delays[$idx] ?? 0;

                SendWhatsAppInvitationJob::dispatch(
                    $notification->id,
                    $target['person_id'],
                    $target['number'],
                    $target['body'],
                    $mediaType,
                    $mediaPath,
                    $target['name']
                )->delay(now()->addSeconds($delaySeconds));

                $whatsappJobs++;
            }
        }

        if (in_array('push', $channels, true)) {
            $personIds = $recipientPersons->pluck('id')->all();
            $devices = UserDevice::query()->whereIn('person_id', $personIds)->get();

            foreach ($devices as $device) {
                if (! $device->expo_push_token) {
                    continue;
                }

                $person = $recipientPersons->firstWhere('id', $device->person_id);
                $bodyText = $this->messages->replacePlaceholders($body, $person, $sender, $templateVars);
                $resolvedTitle = $this->messages->replacePlaceholders($title ?: 'تنبيه عائلي', $person, $sender, $templateVars);

                SendExpoPushNotificationJob::dispatch(
                    $notification->id,
                    (int) $device->person_id,
                    $device->expo_push_token,
                    $resolvedTitle,
                    $bodyText,
                    ['notification_id' => $notification->id]
                );
                $pushJobs++;
            }
        }

        // إشعار صاحب الدعوة باعتمادها
        $this->notifySenderOfDecision($notification, 'approved');

        return [
            'whatsapp_jobs' => $whatsappJobs,
            'push_jobs' => $pushJobs,
        ];
    }

    /**
     * Notify the invitation sender about approval or rejection via push + WhatsApp fallback.
     * Sender notices never touch the invitation status (isSenderNotice=true).
     */
    protected function notifySenderOfDecision(Notification $notification, string $decision, ?string $reason = null): void
    {
        if (! $notification->person_id) {
            return;
        }

        $title = $decision === 'approved' ? '✅ تم قبول دعوتك وبدأ الإرسال' : '❌ تعذر اعتماد دعوتك';
        $body = $decision === 'approved'
            ? "تمت الموافقة على دعوتك «" . ($notification->title ?: 'دعوة عائلية') . "» وتم إرسالها لجميع المستهدفين."
            : "تم رفض دعوتك «" . ($notification->title ?: 'دعوة عائلية') . "»." . ($reason ? " السبب: {$reason}" : '');

        $pushSent = false;
        $devices = UserDevice::query()->where('person_id', $notification->person_id)->get();

        foreach ($devices as $device) {
            if ($device->expo_push_token) {
                SendExpoPushNotificationJob::dispatch(
                    $notification->id,
                    (int) $notification->person_id,
                    $device->expo_push_token,
                    $title,
                    $body,
                    [
                        'type' => 'invitation_decision',
                        'notification_id' => $notification->id,
                        'decision' => $decision,
                    ],
                    true,
                );
                $pushSent = true;
            }
        }

        if ($pushSent) {
            return;
        }

        $sender = $notification->relationLoaded('person')
            ? $notification->person
            : Person::query()->with('contactAccounts')->find($notification->person_id);

        if (! $sender) {
            return;
        }

        $numbers = $this->groups->whatsAppNumbers($sender);
        if ($numbers === []) {
            return;
        }

        SendWhatsAppInvitationJob::dispatch(
            $notification->id,
            $sender->id,
            $numbers[0],
            $title . "\n" . $body,
            null,
            null,
            $sender->first_name ?: $sender->full_name,
            true,
        );
    }
}

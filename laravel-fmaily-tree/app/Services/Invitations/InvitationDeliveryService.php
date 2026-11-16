<?php

namespace App\Services\Invitations;

use App\Jobs\SendExpoPushNotificationJob;
use App\Jobs\SendWhatsAppInvitationJob;
use App\Models\Notification;
use App\Models\UserDevice;
use App\Support\NotificationSettings;

class InvitationDeliveryService
{
    public function __construct(
        protected InvitationMessageService $messages,
        protected InvitationGroupService $groups,
    ) {}

    /**
     * Dispatch queue jobs for all recipients of the notification.
     *
     * @return array{whatsapp_jobs: int, push_jobs: int, status: string}
     */
    public function queueRecipients(Notification $notification): array
    {
        if (! config('invitations.delivery_enabled')) {
            return ['whatsapp_jobs' => 0, 'push_jobs' => 0, 'status' => 'delivery_disabled'];
        }

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

        if (in_array('whatsapp', $channels, true) && NotificationSettings::isChannelEnabled('whatsapp')) {
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
                )->delay(now()->addSeconds($delaySeconds))->afterCommit();

                $whatsappJobs++;
            }
        }

        if (in_array('push', $channels, true) && NotificationSettings::canSendPush()) {
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
                )->afterCommit();
                $pushJobs++;
            }
        }

        return [
            'whatsapp_jobs' => $whatsappJobs,
            'push_jobs' => $pushJobs,
            'status' => $whatsappJobs + $pushJobs > 0 ? 'sending' : 'failed',
        ];
    }
}

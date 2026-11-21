<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\NotificationDelivery;
use App\Models\UserDevice;
use App\Services\ExpoNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendExpoPushNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        public int $notificationId,
        public ?int $personId = null,
        public string $expoPushToken,
        public string $title,
        public string $body,
        public array $data = [],
        public bool $isSenderNotice = false,
    ) {}

    public function handle(ExpoNotificationService $expoService): void
    {
        $logContext = [
            'notification_id' => $this->notificationId,
            'person_id' => $this->personId,
            'token' => $this->expoPushToken,
        ];

        Log::channel('single')->info('[ExpoPush Job] Started dispatching push job', $logContext);

        $notification = Notification::find($this->notificationId);
        if (!$notification) {
            Log::channel('single')->error('[ExpoPush Job] Notification not found, aborting', $logContext);
            return;
        }

        if (($notification->person_id !== null || $this->isSenderNotice) && ! config('invitations.delivery_enabled')) {
            return;
        }

        if (!\App\Support\NotificationSettings::canSendPush()) {
            Log::channel('single')->info('[ExpoPush Job] Push channel is disabled in settings, skipping job', $logContext);
            if (! $this->isSenderNotice) {
                $this->updateNotificationStatus($notification);
            }
            return;
        }

        // Find device ID for polymorphic log mapping
        $device = UserDevice::where('expo_push_token', $this->expoPushToken)->first();
        if ($this->isSenderNotice && (! $device || (int) $device->person_id !== $this->personId)) {
            return;
        }

        try {
            $result = $expoService->send(
                $this->expoPushToken,
                $this->title,
                $this->body,
                $this->data
            );

            if ($result['success'] && $result['sent_count'] > 0) {
                Log::channel('single')->info('[ExpoPush Job] Push delivered successfully', $logContext);

                if ($this->isSenderNotice) {
                    return;
                }
                
                if ($device) {
                    NotificationDelivery::create([
                        'notification_id' => $this->notificationId,
                        'deliverable_type' => UserDevice::class,
                        'deliverable_id' => $device->id,
                    ]);
                }
                
                $this->updateNotificationStatus($notification);
            } else {
                $errorMsg = !empty($result['errors']) ? implode(', ', $result['errors']) : 'Unknown dispatch error';
                Log::channel('single')->warning('[ExpoPush Job] Push delivery failed: ' . $errorMsg, $logContext);

                if ($this->isSenderNotice) {
                    return;
                }
                
                // If it fails on first try, throw exception to trigger queue retry
                if ($this->attempts() < $this->tries) {
                    throw new \Exception("Expo Push delivery failed: " . $errorMsg);
                }
                
                $this->updateNotificationStatus($notification);
            }
        } catch (\Throwable $e) {
            Log::channel('single')->error('[ExpoPush Job] Execution failed: ' . $e->getMessage(), array_merge($logContext, [
                'exception' => $e->getMessage()
            ]));

            if ($this->isSenderNotice) {
                return;
            }
            
            if ($this->attempts() < $this->tries) {
                throw $e;
            }
            
            $this->updateNotificationStatus($notification);
        }
    }

    /**
     * Check and update the overall notification status based on recipient delivery logs.
     */
    private function updateNotificationStatus(Notification $notification): void
    {
        $expectedCount = $notification->recipients()->count();
        if ($expectedCount === 0) {
            // General broadcast notifications have no individual recipients
            $notification->update(['status' => 'sent', 'sent_at' => now()]);
            return;
        }

        $deliveredCount = NotificationDelivery::where('notification_id', $notification->id)->count();

        if ($deliveredCount >= $expectedCount) {
            $notification->update(['status' => 'sent', 'sent_at' => now()]);
        } elseif ($deliveredCount > 0) {
            $notification->update(['status' => 'partial']);
        } else {
            $notification->update(['status' => 'failed']);
        }
    }
}

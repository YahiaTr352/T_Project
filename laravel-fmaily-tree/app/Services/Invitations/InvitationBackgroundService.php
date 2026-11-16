<?php

namespace App\Services\Invitations;

use App\Exceptions\InvitationException;
use App\Models\InvitationBackground;
use App\Models\Person;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\ExpoNotificationService;
use App\Services\UltramsgService;
use App\Support\NotificationSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class InvitationBackgroundService
{
    public function __construct(
        protected ExpoNotificationService $expo,
        protected UltramsgService $ultramsg,
        protected InvitationGroupService $groups,
    ) {}

    public function upload(?Person $uploader, UploadedFile $file, ?string $name = null): InvitationBackground
    {
        $path = $file->store('invitation-backgrounds', 'public');

        $background = InvitationBackground::create([
            'name' => $name ?: $file->getClientOriginalName(),
            'image_path' => $path,
            'uploaded_by' => $uploader?->id,
            'status' => InvitationBackground::STATUS_PENDING,
        ]);

        \App\Jobs\NotifyEditorsOfPendingVisualJob::dispatch('background', $background->id);

        return $background;
    }

    public function approve(InvitationBackground $background, User $editor): InvitationBackground
    {
        if ($background->status !== InvitationBackground::STATUS_PENDING) {
            throw new InvitationException('هذه الخلفية تمت مراجعتها مسبقا.', 'INVALID_STATUS');
        }

        $background->update([
            'status' => InvitationBackground::STATUS_APPROVED,
            'reviewed_by' => $editor->id,
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ]);

        $this->notifyUploader($background->fresh(), 'approved');

        return $background->fresh();
    }

    public function reject(InvitationBackground $background, User $editor, string $reason): InvitationBackground
    {
        if ($background->status !== InvitationBackground::STATUS_PENDING) {
            throw new InvitationException('هذه الخلفية تمت مراجعتها مسبقا.', 'INVALID_STATUS');
        }

        $background->update([
            'status' => InvitationBackground::STATUS_REJECTED,
            'reviewed_by' => $editor->id,
            'reviewed_at' => now(),
            'rejection_reason' => trim($reason),
        ]);

        $this->notifyUploader($background->fresh(), 'rejected', trim($reason));

        return $background->fresh();
    }

    public function destroy(InvitationBackground $background): void
    {
        if ($background->image_path && ! filter_var($background->image_path, FILTER_VALIDATE_URL)) {
            try {
                Storage::disk('public')->delete($background->image_path);
            } catch (\Throwable) {
            }
        }
        $background->delete();
    }

    public function usableBackground(?int $id): ?InvitationBackground
    {
        if (! $id) {
            return null;
        }
        $background = InvitationBackground::approved()->find($id);
        if (! $background) {
            throw new InvitationException('الخلفية المحددة غير متاحة.', 'BACKGROUND_NOT_AVAILABLE', 422);
        }

        return $background;
    }

    protected function notifyUploader(InvitationBackground $background, string $decision, ?string $reason = null): void
    {
        $personId = (int) $background->uploaded_by;
        if ($personId <= 0) {
            return;
        }

        $name = $background->name ?: ('خلفية' . ' #' . $background->id);
        if ($decision === 'approved') {
            $title = 'تم اعتماد خلفية الدعوة';
            $body = 'تمت الموافقة على الخلفية ' . $name . ' واصبحت متاحة للاستخدام في الدعوات.';
        } else {
            $title = 'تم رفض خلفية الدعوة';
            $body = 'تم رفض الخلفية ' . $name . '.' . ($reason ? ' السبب: ' . $reason : '');
        }

        try {
            if (NotificationSettings::canSendPush()) {
                $tokens = UserDevice::query()
                    ->where('person_id', $personId)
                    ->whereNotNull('expo_push_token')
                    ->pluck('expo_push_token')
                    ->all();
                foreach ($tokens as $token) {
                    $this->expo->send($token, $title, $body, [
                        'type' => 'background_decision',
                        'background_id' => $background->id,
                        'decision' => $decision,
                    ]);
                }
                if ($tokens !== []) {
                    return;
                }
            }
        } catch (\Throwable $e) {
            Log::channel('single')->error('[Backgrounds] Push notice failed: ' . $e->getMessage());
        }

        try {
            $person = Person::query()->with('contactAccounts')->find($personId);
            if (! $person) {
                return;
            }
            $numbers = $this->groups->whatsAppNumbers($person);
            if ($numbers === []) {
                return;
            }
            $this->ultramsg->sendText($numbers[0], $title . "\n" . $body);
        } catch (\Throwable $e) {
            Log::channel('single')->error('[Backgrounds] WhatsApp notice failed: ' . $e->getMessage());
        }
    }
}
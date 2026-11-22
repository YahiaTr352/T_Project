<?php

namespace App\Services\Invitations;

use App\Exceptions\InvitationException;
use App\Jobs\NotifyEditorsOfPendingVisualJob;
use App\Models\CreativeTemplate;
use App\Models\Person;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\ExpoNotificationService;
use App\Services\UltramsgService;
use App\Support\NotificationSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CreativeTemplateService
{
    public const ARTWORK_RULES = 'required|image|mimes:jpg,jpeg,png,webp,gif|max:16384|dimensions:max_width=4096,max_height=4096';

    public function __construct(
        protected ExpoNotificationService $expo,
        protected UltramsgService $ultramsg,
        protected InvitationGroupService $groups,
    ) {}

    public function upload(?Person $uploader, UploadedFile $file, ?string $name = null, ?string $description = null, ?string $invitationType = null): CreativeTemplate
    {
        $path = $file->store('creative-templates', 'public');

        $template = CreativeTemplate::create([
            'invitation_type' => $invitationType,
            'name' => $name ?: $file->getClientOriginalName(),
            'description' => $description,
            'image_path' => $path,
            'uploaded_by' => $uploader?->id,
            'status' => CreativeTemplate::STATUS_PENDING,
        ]);

        if ($uploader && config('invitations.delivery_enabled')) {
            NotifyEditorsOfPendingVisualJob::dispatch('creative', $template->id);
        }

        return $template;
    }

    public function approve(CreativeTemplate $template, User $editor): CreativeTemplate
    {
        if ($template->status !== CreativeTemplate::STATUS_PENDING) {
            throw new InvitationException('هذا القالب تمت مراجعته مسبقا.', 'INVALID_STATUS');
        }
        if ($template->uploaded_by && ! $template->invitation_type) {
            throw new InvitationException('حدّد نوع الدعوة قبل اعتماد التصميم.', 'CREATIVE_TYPE_REQUIRED', 422);
        }

        $template->update([
            'status' => CreativeTemplate::STATUS_APPROVED,
            'reviewed_by' => $editor->id,
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ]);

        $this->notifyUploader($template->fresh(), 'approved');

        return $template->fresh();
    }

    public function reject(CreativeTemplate $template, User $editor, string $reason): CreativeTemplate
    {
        if ($template->status !== CreativeTemplate::STATUS_PENDING) {
            throw new InvitationException('هذا القالب تمت مراجعته مسبقا.', 'INVALID_STATUS');
        }

        $template->update([
            'status' => CreativeTemplate::STATUS_REJECTED,
            'reviewed_by' => $editor->id,
            'reviewed_at' => now(),
            'rejection_reason' => trim($reason),
        ]);

        $this->notifyUploader($template->fresh(), 'rejected', trim($reason));

        return $template->fresh();
    }

    public function destroy(CreativeTemplate $template): void
    {
        if ($template->image_path && ! filter_var($template->image_path, FILTER_VALIDATE_URL)) {
            try {
                Storage::disk('public')->delete($template->image_path);
            } catch (\Throwable) {
            }
        }
        $template->delete();
    }

    public function usableCreative(?int $id, ?string $invitationType): ?CreativeTemplate
    {
        if (! $id) {
            return null;
        }
        $template = CreativeTemplate::approved()->find($id);
        if (! $template) {
            throw new InvitationException('القالب المحدد غير متاح.', 'CREATIVE_NOT_AVAILABLE', 422);
        }
        if ($template->invitation_type !== null && $template->invitation_type !== $invitationType) {
            throw new InvitationException('اختر قالب صورة يطابق نوع الدعوة.', 'CREATIVE_TYPE_MISMATCH', 422);
        }

        return $template;
    }

    protected function notifyUploader(CreativeTemplate $template, string $decision, ?string $reason = null): void
    {
        $personId = (int) $template->uploaded_by;
        if ($personId <= 0 || ! config('invitations.delivery_enabled')) {
            return;
        }

        $name = $template->name ?: ('تصميم' . ' #' . $template->id);
        if ($decision === 'approved') {
            $title = 'تم اعتماد تصميمك';
            $body = 'تمت الموافقة على التصميم ' . $name . ' واصبح متاحا كقالب للدعوات.';
        } else {
            $title = 'تم رفض تصميمك';
            $body = 'تم رفض التصميم ' . $name . '.' . ($reason ? ' السبب: ' . $reason : '');
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
                        'type' => 'creative_decision',
                        'creative_id' => $template->id,
                        'decision' => $decision,
                    ]);
                }
                if ($tokens !== []) {
                    return;
                }
            }
        } catch (\Throwable $e) {
            Log::channel('single')->error('[Creatives] Push notice failed: ' . $e->getMessage());
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
            Log::channel('single')->error('[Creatives] WhatsApp notice failed: ' . $e->getMessage());
        }
    }
}

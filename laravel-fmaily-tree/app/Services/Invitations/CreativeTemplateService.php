<?php

namespace App\Services\Invitations;

use App\Exceptions\InvitationException;
use App\Jobs\NotifyEditorsOfPendingVisualJob;
use App\Jobs\SendExpoPushNotificationJob;
use App\Models\CreativeTemplate;
use App\Models\Notification;
use App\Models\Person;
use App\Models\User;
use App\Models\UserDevice;
use App\Support\NotificationSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CreativeTemplateService
{
    public const ARTWORK_RULES = 'required|image|mimes:jpg,jpeg,png,webp,gif|max:16384|dimensions:max_width=4096,max_height=4096';

    public function __construct(private InvitationCardRenderer $renderer) {}

    public function previewImageUrl(CreativeTemplate $template): ?string
    {
        try {
            $path = $this->renderer->renderTemplatePreview($template);
        } catch (InvitationException) {
            return null;
        }

        return $path ? url('storage/'.$path) : null;
    }

    public static function designRules(): array
    {
        return [
            'design_mode' => ['sometimes', 'required', Rule::in(array_diff(array_keys(CreativeTemplate::MODES), CreativeTemplate::WEB_MODES))],
            'text_layout' => 'required_if:design_mode,overlay|prohibited_if:design_mode,ready,artwork|nullable|string|max:12000',
        ];
    }

    public function upload(?Person $uploader, UploadedFile $file, ?string $name = null, ?string $description = null, ?string $invitationType = null, bool $publicationConsent = false, ?array $textLayout = null, ?string $designMode = null, ?string $editableImage = null, ?array $editableText = null): CreativeTemplate
    {
        if ($uploader && ! $publicationConsent) {
            throw new InvitationException('يرجى الموافقة على عرض التصميم ضمن القوالب عند اعتماده.', 'PUBLICATION_CONSENT_REQUIRED', 422);
        }

        $path = null;
        try {
            $path = $file->store('creative-templates', 'public');
            if (! $path) {
                throw new InvitationException('تعذّر حفظ صورة التصميم.', 'CREATIVE_IMAGE_STORAGE');
            }
            $template = CreativeTemplate::create([
                'invitation_type' => $invitationType,
                'name' => $name ?: $file->getClientOriginalName(),
                'description' => $description,
                'image_path' => $path,
                'editable_image_path' => $editableImage,
                'editable_text' => $editableText,
                'text_layout' => $textLayout,
                'design_mode' => $designMode ?? ($textLayout === null ? CreativeTemplate::MODE_READY : null),
                'uploaded_by' => $uploader?->id,
                'publication_consented_at' => $uploader ? now() : null,
                'status' => CreativeTemplate::STATUS_PENDING,
            ]);
        } catch (\Throwable $error) {
            Storage::disk('public')->delete(array_values(array_filter([$path, $editableImage])));
            throw $error;
        }

        $this->notifyPending($template);

        return $template;
    }

    public function notifyPending(CreativeTemplate $template): void
    {
        if ($template->uploaded_by && config('invitations.delivery_enabled')) {
            NotifyEditorsOfPendingVisualJob::dispatch('creative', $template->id);
        }
    }

    public function approve(CreativeTemplate $template, User $editor): CreativeTemplate
    {
        return $this->review($template, $editor, CreativeTemplate::STATUS_APPROVED);
    }

    public function reject(CreativeTemplate $template, User $editor, string $reason): CreativeTemplate
    {
        return $this->review($template, $editor, CreativeTemplate::STATUS_REJECTED, trim($reason));
    }

    public function recordPublicationConsent(Person $member, int $templateId): void
    {
        DB::transaction(function () use ($member, $templateId) {
            $template = CreativeTemplate::where('uploaded_by', $member->id)->lockForUpdate()->findOrFail($templateId);
            if ($template->status !== CreativeTemplate::STATUS_PENDING) {
                throw new InvitationException('الموافقة متاحة للتصاميم التي تنتظر المراجعة.', 'INVALID_STATUS', 422);
            }

            if ($template->awaitsPublicationConsent()) {
                $template->update(['publication_consented_at' => now()]);
            }
        });
    }

    private function review(CreativeTemplate $template, User $editor, string $decision, ?string $reason = null): CreativeTemplate
    {
        return DB::transaction(function () use ($template, $editor, $decision, $reason) {
            $template = CreativeTemplate::lockForUpdate()->findOrFail($template->id);
            if ($template->status !== CreativeTemplate::STATUS_PENDING) {
                throw new InvitationException('هذا القالب تمت مراجعته مسبقا.', 'INVALID_STATUS');
            }
            if ($decision === CreativeTemplate::STATUS_APPROVED && $template->designMode() === CreativeTemplate::MODE_ARTWORK
                && (! $template->editable_text || ! $template->editable_image_path || ! $template->canPrepareArtwork())) {
                throw new InvitationException('جهّز حقول الكتابة وراجع معاينتها قبل اعتماد القالب.', 'CREATIVE_ARTWORK_REVIEW_REQUIRED');
            }
            if ($decision === CreativeTemplate::STATUS_APPROVED && $template->designMode() === CreativeTemplate::MODE_SVG
                && (! $template->svgReadyForReview() || ! $template->image_path || ! $template->editable_text || ! $template->invitation_type)) {
                throw new InvitationException('راجع فحص ملف SVG وجرّب استبدال النص قبل اعتماد القالب.', 'CREATIVE_SVG_REVIEW_REQUIRED');
            }
            if ($decision === CreativeTemplate::STATUS_APPROVED && $template->uploaded_by) {
                if (! $template->invitation_type) {
                    throw new InvitationException('حدّد نوع الدعوة قبل اعتماد التصميم.', 'CREATIVE_TYPE_REQUIRED', 422);
                }
                if ($template->awaitsPublicationConsent()) {
                    throw new InvitationException('لا يمكن اعتماد التصميم قبل موافقة العضو على عرضه.', 'PUBLICATION_CONSENT_REQUIRED', 422);
                }
            }
            $template->update([
                'status' => $decision,
                'design_mode' => $template->design_mode ?? ($decision === CreativeTemplate::STATUS_APPROVED
                    && ! $template->text_layout && ! $template->editable_text ? CreativeTemplate::MODE_READY : null),
                'reviewed_by' => $editor->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ]);

            $this->notifyUploader($template, $decision, $reason);

            return $template;
        });
    }

    public function destroy(CreativeTemplate $template): void
    {
        [$imagePaths, $svgPath] = DB::transaction(function () use ($template) {
            $template = CreativeTemplate::lockForUpdate()->findOrFail($template->id);
            $paths = [$template->image_path, $template->editable_image_path];
            $svgPath = $template->svg_path;
            $template->delete();

            return [$paths, $svgPath];
        });

        try {
            Storage::disk('public')->delete(array_values(array_filter($imagePaths, fn ($path) => $path && ! filter_var($path, FILTER_VALIDATE_URL))));
            if ($svgPath) {
                Storage::disk('local')->delete($svgPath);
            }
        } catch (\Throwable) {
        }
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
        if ($personId <= 0) {
            return;
        }

        $name = $template->name ?: ('تصميم' . ' #' . $template->id);
        if ($decision === CreativeTemplate::STATUS_APPROVED) {
            $title = 'تم اعتماد تصميمك';
            $body = 'تمت الموافقة على التصميم ' . $name . ' واصبح متاحا كقالب للدعوات.';
        } else {
            $title = 'تم رفض تصميمك';
            $body = 'تم رفض التصميم ' . $name . '.' . ($reason ? ' السبب: ' . $reason : '');
        }

        $data = ['type' => Notification::CREATIVE_DECISION_TYPE, 'creative_id' => $template->id, 'decision' => $decision];
        $notice = Notification::create([
            'title' => $title,
            'body' => $body,
            'status' => 'sent',
            'sent_at' => now(),
            'targeting_filters' => ['data' => $data],
        ]);
        $notice->recipients()->create(['person_id' => $personId]);

        if (! config('invitations.delivery_enabled') || ! NotificationSettings::canSendPush()) {
            return;
        }

        $tokens = UserDevice::where('person_id', $personId)->whereNotNull('expo_push_token')->distinct()->pluck('expo_push_token');
        foreach ($tokens as $token) {
            SendExpoPushNotificationJob::dispatch($notice->id, $personId, $token, $title, $body,
                $data + ['notification_id' => $notice->id], isSenderNotice: true)->afterCommit();
        }
    }
}

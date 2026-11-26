<?php

namespace App\Services\Invitations;

use App\Exceptions\InvitationException;
use App\Models\CreativeTemplate;
use App\Models\Person;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreativeSvgArtwork
{
    public const UPLOAD_RULES = 'required|file|mimes:svg|max:16384';
    public const PNG_RULES = 'file|mimetypes:image/png|max:16384';

    public function __construct(private CreativeSvgDocument $documents, private CreativeTemplateService $creatives) {}

    public function upload(?Person $owner, UploadedFile $file, string $type, ?string $name, ?string $description = null): CreativeTemplate
    {
        [$svg, $inspection] = $this->documents->inspect($file->get());
        $path = 'creative-svg/'.Str::uuid().'.svg';
        $storage = Storage::disk('local');
        if (! $storage->put($path, $svg)) {
            throw new InvitationException('تعذّر حفظ ملف SVG.', 'CREATIVE_SVG_STORAGE');
        }
        try {
            $template = CreativeTemplate::create([
                'invitation_type' => $type,
                'name' => $name ?: $file->getClientOriginalName(),
                'description' => $description,
                'image_path' => '',
                'svg_path' => $path,
                'svg_inspection' => $inspection,
                'design_mode' => CreativeTemplate::MODE_SVG,
                'uploaded_by' => $owner?->id,
                'publication_consented_at' => $owner ? now() : null,
                'status' => CreativeTemplate::STATUS_PENDING,
            ]);
        } catch (\Throwable $error) {
            $storage->delete($path);
            throw $error;
        }
        $this->creatives->notifyPending($template);

        return $template;
    }

    public function source(CreativeTemplate $template): array
    {
        abort_unless($template->designMode() === CreativeTemplate::MODE_SVG && $template->svg_path, 404);
        $source = Storage::disk('local')->get($template->svg_path);
        abort_if($source === null, 404);

        return ['svg' => $source, 'revision' => $template->artworkRevision()] + $template->svg_inspection;
    }

    public function save(CreativeTemplate $template, User $reviewer, array $selection, UploadedFile $original, UploadedFile $preview, ?string $revision): CreativeTemplate
    {
        $fields = $this->fields($template, $selection);
        $originalBytes = $this->png($template, $original);
        $previewBytes = $this->png($template, $preview);
        if ($originalBytes === $previewBytes) {
            throw new InvitationException('جرّب نصًا بديلًا يظهر في المعاينة قبل اعتماد القالب.', 'CREATIVE_SVG_PREVIEW_REQUIRED');
        }

        $disk = Storage::disk('public');
        $path = 'creative-templates/svg/'.Str::uuid().'.png';
        $previous = null;
        try {
            $saved = DB::transaction(function () use ($template, $reviewer, $revision, $fields, $originalBytes, $disk, $path, &$previous) {
                $template = CreativeTemplate::lockForUpdate()->findOrFail($template->id);
                if (! $template->svgReadyForReview() || ! $template->invitation_type || $template->status === CreativeTemplate::STATUS_REJECTED
                    || $template->artworkRevision() !== $revision) {
                    throw new InvitationException('تغيّر القالب. أعد فتح المراجعة قبل الحفظ.', 'CREATIVE_SVG_REVIEW_CHANGED');
                }
                if (! $disk->put($path, $originalBytes)) {
                    throw new InvitationException('تعذّر حفظ معاينة القالب.', 'CREATIVE_SVG_STORAGE');
                }
                $previous = $template->image_path;
                $template->update(['image_path' => $path, 'editable_text' => $fields]);

                return $template->status === CreativeTemplate::STATUS_PENDING ? $this->creatives->approve($template, $reviewer) : $template;
            });
        } catch (\Throwable $error) {
            $disk->delete($path);
            throw $error;
        }
        if ($previous && str_starts_with($previous, 'creative-templates/svg/')) {
            $disk->delete($previous);
        }

        return $saved;
    }

    public function card(CreativeTemplate $template, array $values, ?UploadedFile $image): string
    {
        $disk = Storage::disk('public');
        $edited = false;
        foreach ($template->editable_text as $key => $field) {
            if (array_key_exists($key, $values) && trim((string) $values[$key]) !== $field['text']) {
                $edited = true;
                break;
            }
        }
        if ($edited && ! $image) {
            throw new InvitationException('افتح محرّر كتابة القالب وطبّق المعاينة لحفظ التعديلات قبل الإرسال.', 'CREATIVE_SVG_PREVIEW_REQUIRED');
        }
        $bytes = $edited ? $this->png($template, $image) : $disk->get($template->image_path);
        if ($bytes === null) {
            throw new InvitationException('تعذّر تحميل صورة القالب.', 'CREATIVE_SVG_STORAGE');
        }
        $path = 'notifications/cards/svg_'.hash('sha256', $bytes).'.png';
        if (! $disk->exists($path) && ! $disk->put($path, $bytes)) {
            throw new InvitationException('تعذّر حفظ صورة الدعوة.', 'CREATIVE_SVG_STORAGE');
        }

        return $path;
    }

    private function fields(CreativeTemplate $template, array $selection): array
    {
        $available = $template->svg_inspection['fields'] ?? [];
        $fields = [];
        foreach ($selection as $key => $field) {
            if (! isset($available[$key])) {
                throw ValidationException::withMessages(['fields' => 'اختر الحقول الموجودة في ملف SVG.']);
            }
            $field = Validator::make($field, [
                'label' => 'required|string|max:60',
                'sample' => 'required|string|max:255',
                'x' => 'required|numeric|between:0,100', 'y' => 'required|numeric|between:0,100',
                'width' => 'required|numeric|between:.000001,100', 'height' => 'required|numeric|between:.000001,100',
            ])->validate();
            $field['label'] = trim($field['label']);
            $field['text'] = $available[$key];
            if ($field['label'] === '' || trim($field['sample']) === '' || trim($field['sample']) === $field['text']
                || $field['x'] + $field['width'] > 100.001 || $field['y'] + $field['height'] > 100.001) {
                throw ValidationException::withMessages(['fields' => 'حدّد اسم الحقل وجرّب نصًا بديلًا داخل مساحة التصميم.']);
            }
            unset($field['sample']);
            $fields[$key] = $field;
        }

        return $fields;
    }

    private function png(CreativeTemplate $template, UploadedFile $file): string
    {
        $bytes = $file->get();
        $info = @getimagesizefromstring($bytes);
        if (! $info || $info['mime'] !== 'image/png'
            || $info[0] !== $template->svg_inspection['width'] || $info[1] !== $template->svg_inspection['height']
            || ! @imagecreatefromstring($bytes)) {
            throw new InvitationException('تعذّر تجهيز PNG بنفس أبعاد التصميم الأصلي.', 'CREATIVE_SVG_DIMENSIONS');
        }

        return $bytes;
    }
}

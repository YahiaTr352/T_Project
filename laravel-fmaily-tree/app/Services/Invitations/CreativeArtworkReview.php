<?php

namespace App\Services\Invitations;

use App\Exceptions\InvitationException;
use App\Models\CreativeTemplate;
use App\Models\Person;
use App\Models\User;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreativeArtworkReview
{
    public function __construct(
        private CreativeArtworkText $artwork,
        private InvitationCardRenderer $renderer,
        private CreativeTemplateService $creatives,
    ) {}

    public function previewUpload(Person|User $actor, UploadedFile $file, string $input): array
    {
        [$fields, $samples] = $this->fields($input, memberUpload: true);
        $bytes = $file->get();
        $cleaned = $this->artwork->prepareUpload($bytes, $fields);
        $draft = new CreativeTemplate([
            'image_path' => 'upload-preview',
            'invitation_type' => 'upload-preview',
            'design_mode' => CreativeTemplate::MODE_WEB_ARTWORK,
            'editable_text' => $fields,
        ]);
        $design = $this->renderer->renderEditableArtwork($draft, inlinePreview: true, cleaned: $cleaned);
        if (! $design) {
            throw new InvitationException('تعذّر إنشاء معاينة التصميم.', 'CREATIVE_RENDER_FAILED');
        }

        $result = ['design' => $design, 'proof' => $this->uploadProof($actor, $bytes, $fields, $samples)];
        if ($samples) {
            $preview = $this->renderer->renderEditableArtwork($draft, $samples, inlinePreview: true, cleaned: $cleaned);
            if (! $preview) {
                throw new InvitationException('تعذّر إنشاء معاينة التصميم.', 'CREATIVE_RENDER_FAILED');
            }
            $result['image'] = $preview;
        }

        return $result;
    }

    public function submitUpload(Person|User $actor, UploadedFile $file, string $type, ?string $name, ?string $input, ?string $proof, ?string $description = null): CreativeTemplate
    {
        $fields = $path = null;
        $disk = Storage::disk('public');
        if ($input !== null) {
            [$fields, $samples] = $this->fields($input, memberUpload: true);
            $bytes = $file->get();
            if (! $proof || ! hash_equals($this->uploadProof($actor, $bytes, $fields, $samples), $proof)) {
                throw new InvitationException('اعرض المعاينة بعد آخر تعديل على الحقول قبل المتابعة.', 'CREATIVE_ARTWORK_PREVIEW_REQUIRED');
            }
            $background = $this->artwork->prepareUpload($bytes, $fields);
            $path = 'creative-templates/prepared/'.Str::uuid().'.png';
            if (! $disk->put($path, $this->encode($background))) {
                throw new InvitationException('تعذّر حفظ خلفية القالب.', 'CREATIVE_RENDER_FAILED');
            }
        }

        $upload = fn (?Person $member) => $this->creatives->upload(
            $member, $file, $name, $description, invitationType: $type, publicationConsent: true,
            designMode: $member || $fields ? CreativeTemplate::MODE_WEB_ARTWORK : CreativeTemplate::MODE_READY,
            editableImage: $path, editableText: $fields,
        );

        if ($actor instanceof Person) {
            return $upload($actor);
        }

        $template = null;
        try {
            return DB::transaction(function () use ($actor, $upload, &$template) {
                $template = $upload(null);

                return $this->creatives->approve($template, $actor);
            });
        } catch (\Throwable $error) {
            if ($template) {
                $disk->delete(array_filter([$template->image_path, $path]));
            }
            throw $error;
        }
    }

    private function uploadProof(Person|User $actor, string $bytes, array $fields, array $samples): string
    {
        $owner = $actor instanceof User ? ['dashboard', $actor->id] : $actor->id;

        return hash_hmac('sha256', serialize([$owner, hash('sha256', $bytes), $fields, $samples]), (string) config('app.key'));
    }

    public function preview(CreativeTemplate $template, string $input, ?string $background, bool $reuseBackground): array
    {
        [$fields, $samples] = $this->fields($input);
        $background = $this->background($template, $background, $reuseBackground);
        $cleaned = $this->artwork->prepareTemplateBackground($template, $fields, $background);
        $draft = clone $template;
        $draft->design_mode = CreativeTemplate::MODE_ARTWORK;
        $draft->editable_text = $fields;
        $image = $this->renderer->renderEditableArtwork($draft, $samples, inlinePreview: true, cleaned: $cleaned);
        if (! $image) {
            throw new InvitationException('تعذّر إنشاء معاينة التصميم.', 'CREATIVE_RENDER_FAILED');
        }

        return ['image' => $image, 'proof' => $this->proof($template, $fields, $samples, $background)];
    }

    public function save(CreativeTemplate $template, User $reviewer, string $input, ?string $background, bool $reuseBackground, string $proof): CreativeTemplate
    {
        [$fields, $samples] = $this->fields($input);
        $disk = Storage::disk('public');
        $path = 'creative-templates/prepared/'.Str::uuid().'.png';
        $previous = null;

        try {
            $result = DB::transaction(function () use ($template, $reviewer, $fields, $samples, $background, $reuseBackground, $proof, $disk, $path, &$previous) {
                $template = CreativeTemplate::lockForUpdate()->findOrFail($template->id);
                $background = $this->background($template, $background, $reuseBackground);
                if (! $template->canPrepareArtwork() || $template->status === CreativeTemplate::STATUS_REJECTED
                    || ! hash_equals($this->proof($template, $fields, $samples, $background), $proof)) {
                    throw new InvitationException('تغيّر القالب أو الحقول. حدّث المعاينة قبل الحفظ.', 'CREATIVE_ARTWORK_PREVIEW_REQUIRED');
                }

                $cleaned = $this->artwork->prepareTemplateBackground($template, $fields, $background);
                if (! $disk->put($path, $this->encode($cleaned))) {
                    throw new InvitationException('تعذّر حفظ خلفية القالب.', 'CREATIVE_RENDER_FAILED');
                }

                $previous = $template->editable_image_path;
                $template->update([
                    'editable_image_path' => $path,
                    'editable_text' => $fields,
                    'design_mode' => CreativeTemplate::MODE_ARTWORK,
                    'text_layout' => null,
                ]);

                return $template->status === CreativeTemplate::STATUS_PENDING
                    ? $this->creatives->approve($template, $reviewer) : $template;
            });
        } catch (\Throwable $error) {
            $disk->delete($path);
            throw $error;
        }

        if ($previous && str_starts_with($previous, 'creative-templates/prepared/')) {
            $disk->delete($previous);
        }

        return $result;
    }

    private function background(CreativeTemplate $template, ?string $background, bool $reuse): ?string
    {
        if ($background !== null || ! $reuse) {
            return $background;
        }

        $stored = $template->editable_image_path ? Storage::disk('public')->get($template->editable_image_path) : null;
        if ($stored === null) {
            throw new InvitationException('الخلفية المنظّفة المحفوظة غير متاحة. أعد تجهيز الخلفية أو ارفع نسخة منها.', 'CREATIVE_BACKGROUND_UNAVAILABLE');
        }

        return $stored;
    }

    private function fields(string $input, bool $memberUpload = false): array
    {
        $extra = $memberUpload ? ',replacePrinted' : '';
        $validated = Validator::make(['fields' => json_decode($input, true)], [
            'fields' => 'required|array|min:1|max:12',
            'fields.*' => 'required|array:id,label,text,sample,x,y,width,height,fontFamily,fontSize,bold,alignment,color,sourceColor'.$extra,
            'fields.*.id' => 'required|integer|between:1,999999|distinct',
            'fields.*.label' => 'required|string|max:60',
            'fields.*.text' => 'required|string|max:255',
            'fields.*.sample' => $memberUpload ? 'nullable|string|max:255' : 'required|string|max:255',
            'fields.*.x' => 'required|numeric|between:0,99',
            'fields.*.y' => 'required|numeric|between:0,99',
            'fields.*.width' => 'required|numeric|between:1,100',
            'fields.*.height' => 'required|numeric|between:1,100',
            'fields.*.fontFamily' => 'required|in:amiri,tajawal',
            'fields.*.fontSize' => 'required|numeric|between:1,40',
            'fields.*.bold' => 'required|boolean',
            'fields.*.alignment' => 'required|in:left,center,right',
            'fields.*.color' => 'required|regex:/^#[a-f0-9]{6}$/i',
            'fields.*.sourceColor' => 'nullable|regex:/^#[a-f0-9]{6}$/i',
            'fields.*.replacePrinted' => 'sometimes|boolean',
        ], ['fields.*' => 'أكمل بيانات كل حقل وحدد موضعه وتنسيقه داخل الصورة.'])->validate();

        $fields = $samples = [];
        foreach ($validated['fields'] as $field) {
            $key = 'creative_text_'.(int) $field['id'];
            $sample = trim($field['sample'] ?? '');
            unset($field['id'], $field['sample']);
            foreach (['x', 'y', 'width', 'height', 'fontSize'] as $coordinate) {
                $field[$coordinate] = round((float) $field[$coordinate], 4);
            }
            $field['label'] = trim($field['label']);
            $field['text'] = trim($field['text']);
            $field['bold'] = (bool) $field['bold'];
            $field['sourceColor'] = ($field['sourceColor'] ?? '') ?: null;
            if ($memberUpload) {
                $field['replacePrinted'] = (bool) ($field['replacePrinted'] ?? false);
            }
            if ($field['label'] === '' || $field['text'] === '') {
                throw ValidationException::withMessages(['fields' => 'اكتب اسم الحقل والنص الذي سيظهر فيه.']);
            }
            if ((! $memberUpload && $sample === '') || $sample === $field['text']) {
                throw ValidationException::withMessages(['fields' => 'اكتب نصًا مختلفًا لتجربة التنسيق.']);
            }
            if ($field['x'] + $field['width'] > 100.00001 || $field['y'] + $field['height'] > 100.00001) {
                throw ValidationException::withMessages(['fields' => 'أبقِ حدود كل حقل داخل الصورة.']);
            }
            foreach ($fields as $other) {
                if ($field['x'] < $other['x'] + $other['width'] && $other['x'] < $field['x'] + $field['width']
                    && $field['y'] < $other['y'] + $other['height'] && $other['y'] < $field['y'] + $field['height']) {
                    throw ValidationException::withMessages(['fields' => 'حدّد كل حقل في مساحة منفصلة عن بقية الحقول.']);
                }
            }
            $fields[$key] = $field;
            if ($sample !== '') {
                $samples[$key] = $sample;
            }
        }

        return [$fields, $samples];
    }

    private function proof(CreativeTemplate $template, array $fields, array $samples, ?string $background): string
    {
        return hash_hmac('sha256', serialize([
            $template->id, $template->image_path, $template->editable_image_path, $template->invitation_type,
            $template->status, $template->design_mode, $fields, $samples, $background === null ? null : hash('sha256', $background),
        ]), (string) config('app.key'));
    }

    private function encode(GdImage $image): string
    {
        ob_start();
        try {
            if (! imagepng($image)) {
                throw new InvitationException('تعذّر تجهيز خلفية القالب.', 'CREATIVE_RENDER_FAILED');
            }

            return ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\InvitationException;
use App\Http\Controllers\Controller;
use App\Models\CreativeTemplate;
use App\Models\Person;
use App\Services\Invitations\CreativeArtworkReview;
use App\Services\Invitations\CreativeTemplateService;
use App\Services\Invitations\CreativeTextLayout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CreativeTemplateApiController extends Controller
{
    public function __construct(
        protected CreativeTemplateService $creatives,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invitation_type' => ['sometimes', Rule::in(array_column(config('invitation_templates.templates', []), 'key'))],
            'design_mode' => ['sometimes', Rule::in(array_diff(array_keys(CreativeTemplate::MODES), CreativeTemplate::WEB_MODES))],
            'editable_only' => 'sometimes|boolean',
            'member_fields' => 'sometimes|boolean',
        ]);
        $list = CreativeTemplate::approved()->availableInApp($validated['member_fields'] ?? false)
            ->when($validated['editable_only'] ?? false, fn ($query) => $query->forEditablePicker())
            ->when(isset($validated['invitation_type']), fn ($query) => $query->where('invitation_type', $validated['invitation_type']))
            ->when(isset($validated['design_mode']), fn ($query) => $query->forDesignMode($validated['design_mode']))
            ->latest()
            ->get()
            ->map(fn (CreativeTemplate $t) => ['preview_image_url' => $this->creatives->previewImageUrl($t)] + $t->toPickerArray())
            ->values();

        return $this->ok('قوالب مساحة الإبداع.', ['templates' => $list]);
    }

    public function mine(Request $request): JsonResponse
    {
        $person = $this->person($request);
        $validated = $request->validate(['member_fields' => 'sometimes|boolean']);

        $list = CreativeTemplate::availableInApp($validated['member_fields'] ?? false)
            ->where('uploaded_by', $person->id)
            ->latest()
            ->get()
            ->map(fn (CreativeTemplate $t) => [
                'description' => $t->description,
                'status' => $t->status,
                'status_label' => $t->status_label,
                'rejection_reason' => $t->rejection_reason,
                'awaits_publication_consent' => $t->awaitsPublicationConsent(),
                'created_at' => $t->created_at,
                'preview_image_url' => $this->creatives->previewImageUrl($t),
            ] + $t->toPickerArray())
            ->values();

        return $this->ok('تصاميمك المرفوعة.', ['templates' => $list]);
    }

    public function previewFields(Request $request, CreativeArtworkReview $review): JsonResponse
    {
        $validated = $request->validate([
            'image' => CreativeTemplateService::ARTWORK_RULES,
            'fields' => 'required|string|json|max:30000',
        ]);

        try {
            $preview = $review->previewUpload($this->person($request), $request->file('image'), $validated['fields']);
        } catch (InvitationException $e) {
            return $this->fail($e);
        }

        return $this->ok('معاينة التصميم.', $preview);
    }

    public function store(Request $request, CreativeArtworkReview $review): JsonResponse
    {
        $validated = $request->validate([
            'image' => CreativeTemplateService::ARTWORK_RULES,
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'invitation_type' => ['required', Rule::in(array_column(config('invitation_templates.templates', []), 'key'))],
            'publication_consent' => ['accepted'],
            'fields' => 'required_with:proof|prohibits:text_layout|nullable|string|json|max:30000',
            'proof' => 'nullable|string|size:64',
        ] + CreativeTemplateService::designRules(), [
            'image.required' => 'يرجى اختيار صورة التصميم.',
            'image.image' => 'الملف المرفوع يجب ان يكون صورة.',
            'image.max' => 'حجم الصورة يجب الا يتجاوز 16 ميجابايت.',
            'image.dimensions' => 'عرض الصورة وارتفاعها لازم يكونوا 4096 بكسل أو أقل.',
            'invitation_type.required' => 'يرجى اختيار نوع الدعوة.',
            'publication_consent.accepted' => 'يرجى الموافقة على عرض التصميم ضمن القوالب عند اعتماده.',
            'text_layout.required_if' => 'احفظ توزيع النص في المحرر.',
        ]);

        try {
            $template = isset($validated['fields']) ? $review->submitUpload(
                $this->person($request), $request->file('image'), $validated['invitation_type'],
                $validated['name'] ?? null, $validated['fields'], $validated['proof'] ?? null,
                description: $validated['description'] ?? null,
            ) : $this->creatives->upload(
                $this->person($request),
                $request->file('image'),
                $validated['name'] ?? null,
                $validated['description'] ?? null,
                $validated['invitation_type'],
                publicationConsent: true,
                textLayout: CreativeTextLayout::validate($validated['text_layout'] ?? null, $validated['invitation_type'], $validated['design_mode'] ?? null),
                designMode: $validated['design_mode'] ?? null,
            );
        } catch (InvitationException $e) {
            return $this->fail($e);
        }

        return $this->ok('تم رفع التصميم وهو قيد المراجعة.', ['template' => [
            'status' => $template->status,
            'status_label' => $template->status_label,
        ] + $template->toPickerArray()], 201);
    }

    public function consent(Request $request, int $id): JsonResponse
    {
        $request->validate(['publication_consent' => 'accepted']);

        try {
            $this->creatives->recordPublicationConsent($this->person($request), $id);
        } catch (InvitationException $e) {
            return $this->fail($e);
        }

        return $this->ok('تم حفظ موافقتك على عرض التصميم.');
    }

    public function destroyMine(): JsonResponse
    {
        return $this->fail(new InvitationException('حذف التصاميم المرفوعة متاح للإدارة فقط.', 'CREATIVE_DELETE_FORBIDDEN', 403));
    }

    private function person(Request $request): Person
    {
        return $request->attributes->get('identity_person');
    }

    private function ok(string $message, ?array $data = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'errors' => null,
        ], $status);
    }

    private function fail(InvitationException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
            'code' => $e->errorCode,
            'data' => null,
            'errors' => null,
        ], $e->httpStatus);
    }
}

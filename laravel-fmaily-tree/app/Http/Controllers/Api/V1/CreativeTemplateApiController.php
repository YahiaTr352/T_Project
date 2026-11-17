<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\InvitationException;
use App\Http\Controllers\Controller;
use App\Models\CreativeTemplate;
use App\Models\Person;
use App\Services\Invitations\CreativeTemplateService;
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
        ]);
        $list = CreativeTemplate::approved()
            ->when(isset($validated['invitation_type']), fn ($query) => $query->where('invitation_type', $validated['invitation_type']))
            ->latest()
            ->get()
            ->map(fn (CreativeTemplate $t) => $t->toPickerArray())
            ->values();

        return $this->ok('قوالب مساحة الإبداع.', ['templates' => $list]);
    }

    public function mine(Request $request): JsonResponse
    {
        $person = $this->person($request);

        $list = CreativeTemplate::query()
            ->where('uploaded_by', $person->id)
            ->latest()
            ->get()
            ->map(fn (CreativeTemplate $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'description' => $t->description,
                'invitation_type' => $t->invitation_type,
                'image_url' => $t->image_url,
                'status' => $t->status,
                'status_label' => $t->status_label,
                'rejection_reason' => $t->rejection_reason,
                'created_at' => $t->created_at,
            ])
            ->values();

        return $this->ok('تصاميمك المرفوعة.', ['templates' => $list]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image' => 'required|image|max:16384',
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'invitation_type' => ['nullable', Rule::in(array_column(config('invitation_templates.templates', []), 'key'))],
        ], [
            'image.required' => 'يرجى اختيار صورة التصميم.',
            'image.image' => 'الملف المرفوع يجب ان يكون صورة.',
            'image.max' => 'حجم الصورة يجب الا يتجاوز 16 ميجابايت.',
        ]);

        try {
            $template = $this->creatives->upload(
                $this->person($request),
                $request->file('image'),
                $validated['name'] ?? null,
                $validated['description'] ?? null,
                $validated['invitation_type'] ?? null
            );
        } catch (InvitationException $e) {
            return $this->fail($e);
        }

        return $this->ok('تم رفع التصميم وهو قيد المراجعة.', ['template' => [
            'id' => $template->id,
            'name' => $template->name,
            'image_url' => $template->image_url,
            'status' => $template->status,
            'status_label' => $template->status_label,
        ]], 201);
    }

    public function destroyMine(Request $request, int $id): JsonResponse
    {
        $template = CreativeTemplate::query()
            ->where('id', $id)
            ->where('uploaded_by', $this->person($request)->id)
            ->first();

        if (! $template) {
            return response()->json([
                'success' => false,
                'message' => 'التصميم غير موجود.',
                'code' => 'CREATIVE_NOT_FOUND',
                'data' => null,
                'errors' => null,
            ], 404);
        }

        $this->creatives->destroy($template);

        return $this->ok('تم حذف التصميم.');
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

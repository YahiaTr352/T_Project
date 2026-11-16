<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\InvitationException;
use App\Http\Controllers\Controller;
use App\Models\InvitationBackground;
use App\Models\Person;
use App\Services\Invitations\InvitationBackgroundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvitationBackgroundApiController extends Controller
{
    public function __construct(
        protected InvitationBackgroundService $backgrounds,
    ) {}

    public function index(): JsonResponse
    {
        $list = InvitationBackground::approved()
            ->latest()
            ->get()
            ->map(fn (InvitationBackground $b) => $b->toPickerArray())
            ->values();

        return $this->ok('مكتبة خلفيات الدعوات.', ['backgrounds' => $list]);
    }

    public function mine(Request $request): JsonResponse
    {
        $person = $this->person($request);

        $list = InvitationBackground::query()
            ->where('uploaded_by', $person->id)
            ->latest()
            ->get()
            ->map(fn (InvitationBackground $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'image_url' => $b->image_url,
                'status' => $b->status,
                'status_label' => $b->status_label,
                'rejection_reason' => $b->rejection_reason,
                'is_template' => (bool) $b->is_template,
                'created_at' => $b->created_at,
            ])
            ->values();

        return $this->ok('خلفياتك المرفوعة.', ['backgrounds' => $list]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image' => 'required|image|max:16384',
            'name' => 'nullable|string|max:255',
        ], [
            'image.required' => 'يرجى اختيار صورة الخلفية.',
            'image.image' => 'الملف المرفوع يجب ان يكون صورة.',
            'image.max' => 'حجم الصورة يجب الا يتجاوز 16 ميجابايت.',
        ]);

        try {
            $background = $this->backgrounds->upload(
                $this->person($request),
                $request->file('image'),
                $validated['name'] ?? null
            );
        } catch (InvitationException $e) {
            return $this->fail($e);
        }

        return $this->ok('تم رفع الخلفية وهي قيد المراجعة والاعتماد.', ['background' => [
            'id' => $background->id,
            'name' => $background->name,
            'image_url' => $background->image_url,
            'status' => $background->status,
            'status_label' => $background->status_label,
        ]], 201);
    }

    public function destroyMine(Request $request, int $id): JsonResponse
    {
        $background = InvitationBackground::query()
            ->where('id', $id)
            ->where('uploaded_by', $this->person($request)->id)
            ->first();

        if (! $background) {
            return response()->json([
                'success' => false,
                'message' => 'الخلفية غير موجودة.',
                'code' => 'BACKGROUND_NOT_FOUND',
                'data' => null,
                'errors' => null,
            ], 404);
        }

        $this->backgrounds->destroy($background);

        return $this->ok('تم حذف الخلفية.');
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
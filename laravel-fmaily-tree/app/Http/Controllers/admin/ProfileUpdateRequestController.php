<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\ProfileUpdateRequest;
use App\Services\ProfileUpdateRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileUpdateRequestController extends Controller
{
    public function __construct(
        protected ProfileUpdateRequestService $updateRequestService
    ) {}

    public function index(Request $request): View
    {
        $status = $request->query('status', 'pending');
        $changeType = $request->query('change_type');
        $search = trim((string) $request->query('search', ''));

        $query = ProfileUpdateRequest::with(['person', 'reviewer'])->latest();

        if (!empty($status) && in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $query->where('status', $status);
        }

        if (!empty($changeType)) {
            $query->where('change_type', $changeType);
        }

        if ($search !== '') {
            $query->whereHas('person', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        $requests = $query->paginate(15)->withQueryString();

        $pendingCount = ProfileUpdateRequest::where('status', 'pending')->count();
        $approvedCount = ProfileUpdateRequest::where('status', 'approved')->count();
        $rejectedCount = ProfileUpdateRequest::where('status', 'rejected')->count();

        return view('dashboard.profile-updates.index', compact(
            'requests',
            'status',
            'changeType',
            'search',
            'pendingCount',
            'approvedCount',
            'rejectedCount'
        ));
    }

    public function show(ProfileUpdateRequest $profileUpdateRequest): View
    {
        $profileUpdateRequest->load(['person', 'reviewer']);

        return view('dashboard.profile-updates.show', [
            'updateRequest' => $profileUpdateRequest,
            'canEditPhoto' => (bool) auth()->user()?->can('people.update'),
        ]);
    }

    /**
     * Save an admin-edited (cropped / rotated / replaced) version of the staged personal photo.
     */
    public function updatePhoto(Request $request, ProfileUpdateRequest $profileUpdateRequest): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'photo' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:4096',
            'image_base64' => 'nullable|string|max:12000000',
        ], [
            'photo.image' => 'الملف المرسل ليس صورة صالحة.',
            'photo.mimes' => 'صيغة الصورة غير مدعومة، يرجى استخدام JPG أو PNG أو WebP.',
            'photo.max' => 'حجم الصورة كبير جداً، الحد الأقصى 4 ميجابايت.',
            'image_base64.max' => 'حجم الصورة المرسلة كبير جداً.',
        ], [
            'photo' => 'الصورة',
            'image_base64' => 'الصورة',
        ]);

        try {
            $updateRequest = $this->updateRequestService->replaceAttachedPhoto(
                request: $profileUpdateRequest,
                photo: $request->file('photo') ?? ($validated['image_base64'] ?? null),
                reviewer: $request->user(),
            );

            return $this->photoActionResponse(
                $request,
                $updateRequest,
                'تم حفظ الصورة المعدّلة، وسيتم تطبيقها على العضو عند اعتماد الطلب.'
            );
        } catch (ValidationException $e) {
            return $this->photoActionError($request, $this->firstValidationMessage($e), 422);
        } catch (\Throwable $e) {
            Log::channel('single')->error('[ProfileUpdateRequestController] updatePhoto error: ' . $e->getMessage(), [
                'request_id' => $profileUpdateRequest->id,
                'user_id' => $request->user()?->id,
            ]);

            return $this->photoActionError($request, 'حدث خطأ أثناء حفظ الصورة المعدّلة، يرجى إعادة المحاولة.', 500);
        }
    }

    /**
     * Discard the admin-edited photo and restore the version originally uploaded by the member.
     */
    public function resetPhoto(Request $request, ProfileUpdateRequest $profileUpdateRequest): JsonResponse|RedirectResponse
    {
        try {
            $updateRequest = $this->updateRequestService->resetAttachedPhoto(
                request: $profileUpdateRequest,
            );

            return $this->photoActionResponse(
                $request,
                $updateRequest,
                'تمت استعادة صورة العضو الأصلية بنجاح.'
            );
        } catch (ValidationException $e) {
            return $this->photoActionError($request, $this->firstValidationMessage($e), 422);
        } catch (\Throwable $e) {
            Log::channel('single')->error('[ProfileUpdateRequestController] resetPhoto error: ' . $e->getMessage(), [
                'request_id' => $profileUpdateRequest->id,
                'user_id' => $request->user()?->id,
            ]);

            return $this->photoActionError($request, 'حدث خطأ أثناء استعادة الصورة الأصلية، يرجى إعادة المحاولة.', 500);
        }
    }

    protected function photoActionResponse(
        Request $request,
        ProfileUpdateRequest $updateRequest,
        string $message
    ): JsonResponse|RedirectResponse {
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $message,
                'photo_url' => $updateRequest->proposed_photo_url,
                'original_photo_url' => $updateRequest->photo_original_url,
                'has_edited_photo' => $updateRequest->has_edited_photo,
            ]);
        }

        return back()->with('success', $message);
    }

    protected function photoActionError(Request $request, string $message, int $status): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => false, 'message' => $message], $status);
        }

        return back()->with('error', $message);
    }

    protected function firstValidationMessage(ValidationException $e): string
    {
        return collect($e->errors())->flatten()->first() ?: 'البيانات المرسلة غير صالحة.';
    }

    public function approve(ProfileUpdateRequest $profileUpdateRequest, Request $request): RedirectResponse
    {
        $request->validate([
            'approved_fields' => 'nullable|array',
            'approved_fields.*' => 'string|max:50',
            'exclusion_reason' => 'nullable|string|max:500',
        ]);

        try {
            $wasRejected = $profileUpdateRequest->status === 'rejected';
            $approvedFields = $request->input('approved_fields');
            $exclusionReason = $request->input('exclusion_reason');

            if ($profileUpdateRequest->change_type === 'profile_info' && $request->has('has_granular_selection') && empty($approvedFields)) {
                return back()->with('error', 'يجب اختيار حقل واحد على الأقل للاعتماد، أو استخدام خيار رفض الطلب.');
            }

            $this->updateRequestService->approve(
                request: $profileUpdateRequest,
                reviewer: $request->user(),
                isReopened: $wasRejected,
                approvedFields: $approvedFields,
                exclusionNote: $exclusionReason
            );

            $message = $wasRejected
                ? 'تمت إعادة فتح واعتماد طلب التعديل بنجاح وتحديث بيانات الشجرة وإشعار العضو.'
                : 'تم اعتماد طلب التعديل وتحديث بيانات الشجرة بنجاح وإشعار العضو.';

            return redirect()->route('dashboard.profile-updates.index')
                ->with('success', $message);
        } catch (\Throwable $e) {
            return back()->with('error', 'حدث خطأ أثناء اعتماد الطلب: ' . $e->getMessage());
        }
    }

    public function reopen(ProfileUpdateRequest $profileUpdateRequest, Request $request): RedirectResponse
    {
        try {
            $this->updateRequestService->reopen($profileUpdateRequest, $request->user());

            return redirect()->route('dashboard.profile-updates.show', $profileUpdateRequest->id)
                ->with('success', 'تمت إعادة فتح الطلب بنجاح وأصبح الآن قيد المراجعة.');
        } catch (\Throwable $e) {
            return back()->with('error', 'حدث خطأ أثناء إعادة فتح الطلب: ' . $e->getMessage());
        }
    }

    public function reopenAndApprove(ProfileUpdateRequest $profileUpdateRequest, Request $request): RedirectResponse
    {
        $request->validate([
            'approved_fields' => 'nullable|array',
            'approved_fields.*' => 'string|max:50',
            'exclusion_reason' => 'nullable|string|max:500',
        ]);

        try {
            $approvedFields = $request->input('approved_fields');
            $exclusionReason = $request->input('exclusion_reason');

            if ($profileUpdateRequest->change_type === 'profile_info' && $request->has('has_granular_selection') && empty($approvedFields)) {
                return back()->with('error', 'يجب اختيار حقل واحد على الأقل للاعتماد، أو استخدام خيار رفض الطلب.');
            }

            $this->updateRequestService->reopenAndApprove(
                request: $profileUpdateRequest,
                reviewer: $request->user(),
                approvedFields: $approvedFields,
                exclusionNote: $exclusionReason
            );

            return redirect()->route('dashboard.profile-updates.index')
                ->with('success', 'تمت إعادة فتح واعتماد طلب التعديل وتحديث بيانات الشجرة بنجاح وإشعار العضو.');
        } catch (\Throwable $e) {
            return back()->with('error', 'حدث خطأ أثناء اعتماد الطلب: ' . $e->getMessage());
        }
    }

    public function reject(ProfileUpdateRequest $profileUpdateRequest, Request $request): RedirectResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $this->updateRequestService->reject(
                request: $profileUpdateRequest,
                reviewer: $request->user(),
                reason: $request->input('reason')
            );

            return redirect()->route('dashboard.profile-updates.index')
                ->with('success', 'تم رفض طلب التعديل وتسجيل السبب وإشعار العضو.');
        } catch (\Throwable $e) {
            return back()->with('error', 'حدث خطأ أثناء رفض الطلب: ' . $e->getMessage());
        }
    }

    public function bulkApprove(Request $request): RedirectResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:profile_update_requests,id',
        ]);

        $ids = $request->input('ids', []);
        $successCount = 0;

        foreach ($ids as $id) {
            $updateReq = ProfileUpdateRequest::find($id);
            if ($updateReq && $updateReq->status === 'pending') {
                try {
                    $this->updateRequestService->approve($updateReq, $request->user());
                    $successCount++;
                } catch (\Throwable) {
                    // Continue with next
                }
            }
        }

        return back()->with('success', "تم اعتماد {$successCount} طلب(ات) بنجاح.");
    }
}

<?php

namespace App\Http\Controllers\admin;

use App\Exceptions\InvitationException;
use App\Http\Controllers\Controller;
use App\Models\InvitationBackground;
use App\Services\Invitations\InvitationBackgroundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvitationBackgroundController extends Controller
{
    public function __construct(
        protected InvitationBackgroundService $backgrounds,
    ) {}

    public function index(Request $request): View
    {
        $status = $request->query('status', 'pending');
        if (! in_array($status, ['pending', 'approved', 'rejected', 'all'], true)) {
            $status = 'pending';
        }

        $query = InvitationBackground::query()->with('uploader')->latest();
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $backgrounds = $query->paginate(24)->withQueryString();
        $counts = [
            'pending' => InvitationBackground::pending()->count(),
            'approved' => InvitationBackground::approved()->count(),
            'rejected' => InvitationBackground::where('status', InvitationBackground::STATUS_REJECTED)->count(),
        ];

        return view('dashboard.invitation-backgrounds.index', compact('backgrounds', 'status', 'counts'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image' => 'required|image|max:16384',
            'name' => 'nullable|string|max:255',
            'is_template' => 'nullable|boolean',
        ]);

        $background = $this->backgrounds->upload(null, $request->file('image'), $validated['name'] ?? null);
        if (! empty($validated['is_template'])) {
            $background->update(['is_template' => true]);
        }
        $this->backgrounds->approve($background, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'تم رفع الخلفية واعتمادها.',
            'data' => ['background' => $background->fresh()->toPickerArray()],
        ], 201);
    }

    public function approve(Request $request, InvitationBackground $background): JsonResponse
    {
        try {
            $result = $this->backgrounds->approve($background, $request->user());
        } catch (InvitationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => $e->errorCode,
            ], $e->httpStatus);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم اعتماد الخلفية واتاحتها للاستخدام.',
            'data' => ['background' => $result->toPickerArray()],
        ]);
    }

    public function reject(Request $request, InvitationBackground $background): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        try {
            $result = $this->backgrounds->reject($background, $request->user(), $validated['reason']);
        } catch (InvitationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => $e->errorCode,
            ], $e->httpStatus);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم رفض الخلفية واشعار الرافع بالسبب.',
            'data' => ['background' => $result->toPickerArray()],
        ]);
    }

    public function destroy(InvitationBackground $background): JsonResponse
    {
        $this->backgrounds->destroy($background);

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الخلفية.',
        ]);
    }
}
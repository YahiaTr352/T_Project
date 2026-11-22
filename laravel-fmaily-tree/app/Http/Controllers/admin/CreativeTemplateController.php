<?php

namespace App\Http\Controllers\admin;

use App\Exceptions\InvitationException;
use App\Http\Controllers\Controller;
use App\Models\CreativeTemplate;
use App\Services\Invitations\CreativeTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CreativeTemplateController extends Controller
{
    public function __construct(
        protected CreativeTemplateService $creatives,
    ) {}

    public function index(Request $request): View
    {
        $status = $request->query('status', 'pending');
        if (! in_array($status, ['pending', 'approved', 'rejected', 'all'], true)) {
            $status = 'pending';
        }

        $query = CreativeTemplate::query()->with('uploader')->latest();
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $creatives = $query->paginate(24)->withQueryString();
        $counts = [
            'pending' => CreativeTemplate::pending()->count(),
            'approved' => CreativeTemplate::approved()->count(),
            'rejected' => CreativeTemplate::where('status', CreativeTemplate::STATUS_REJECTED)->count(),
        ];

        return view('dashboard.creative-templates.index', compact('creatives', 'status', 'counts'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image' => CreativeTemplateService::ARTWORK_RULES,
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'invitation_type' => ['required', Rule::in(array_column(config('invitation_templates.templates', []), 'key'))],
        ]);

        $template = $this->creatives->upload(
            null,
            $request->file('image'),
            $validated['name'] ?? null,
            $validated['description'] ?? null,
            $validated['invitation_type'] ?? null
        );
        $this->creatives->approve($template, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'تم رفع التصميم واعتماده كقالب.',
            'data' => ['template' => $template->fresh()->toPickerArray()],
        ], 201);
    }

    public function update(Request $request, CreativeTemplate $template): JsonResponse
    {
        $validated = $request->validate([
            'invitation_type' => ['present', 'nullable', Rule::in(array_column(config('invitation_templates.templates', []), 'key'))],
        ]);
        $template->update($validated);

        return response()->json(['success' => true, 'message' => 'تم حفظ نوع الدعوة.']);
    }

    public function approve(Request $request, CreativeTemplate $template): JsonResponse
    {
        try {
            $result = $this->creatives->approve($template, $request->user());
        } catch (InvitationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => $e->errorCode,
            ], $e->httpStatus);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم اعتماد التصميم واضافته للقوالب.',
            'data' => ['template' => $result->toPickerArray()],
        ]);
    }

    public function reject(Request $request, CreativeTemplate $template): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        try {
            $result = $this->creatives->reject($template, $request->user(), $validated['reason']);
        } catch (InvitationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => $e->errorCode,
            ], $e->httpStatus);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم رفض التصميم واشعار الرافع بالسبب.',
            'data' => ['template' => $result->toPickerArray()],
        ]);
    }

    public function destroy(CreativeTemplate $template): JsonResponse
    {
        $this->creatives->destroy($template);

        return response()->json([
            'success' => true,
            'message' => 'تم حذف التصميم.',
        ]);
    }
}

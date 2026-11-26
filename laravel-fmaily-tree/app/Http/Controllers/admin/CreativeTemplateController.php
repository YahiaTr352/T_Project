<?php

namespace App\Http\Controllers\admin;

use App\Exceptions\InvitationException;
use App\Http\Controllers\Controller;
use App\Models\CreativeTemplate;
use App\Services\Invitations\CreativeArtworkReview;
use App\Services\Invitations\CreativeSvgArtwork;
use App\Services\Invitations\CreativeTemplateService;
use App\Services\Invitations\CreativeTextLayout;
use App\Services\Invitations\InvitationCardRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        $invitationTypes = array_column(config('invitation_templates.templates', []), 'name', 'key');
        $type = $request->query('invitation_type');
        $type = is_string($type) && ($type === 'unclassified' || isset($invitationTypes[$type])) ? $type : null;
        $source = $request->query('source');
        $source = in_array($source, ['admin', 'members'], true) ? $source : null;
        $modes = CreativeTemplate::MODES;
        $mode = $request->query('design_mode');
        $mode = is_string($mode) && isset($modes[$mode]) ? $mode : null;
        $filters = array_filter(['invitation_type' => $type, 'source' => $source, 'design_mode' => $mode]);

        $library = CreativeTemplate::query();
        if ($mode !== null) {
            $library->forDesignMode($mode);
        }
        if ($type === 'unclassified') {
            $library->whereNull('invitation_type');
        } elseif ($type !== null) {
            $library->where('invitation_type', $type);
        }
        if ($source === 'admin') {
            $library->whereNull('uploaded_by')->whereNull('publication_consented_at');
        } elseif ($source === 'members') {
            $library->where(fn ($query) => $query->whereNotNull('uploaded_by')->orWhereNotNull('publication_consented_at'));
        }

        $creatives = (clone $library)->with('uploader')->latest()->orderByDesc('id')
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->paginate(24)->appends(['status' => $status] + $filters);
        $counts = $library->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status')->all();
        $disk = Storage::disk('public');
        $previewUrls = $creatives->getCollection()->mapWithKeys(function (CreativeTemplate $template) use ($disk) {
            $path = $template->image_path;
            $available = $path && (filter_var($path, FILTER_VALIDATE_URL) || $disk->exists($path));

            return [$template->id => $this->creatives->previewImageUrl($template) ?? ($available ? $template->image_url : null)];
        });

        return view('dashboard.creative-templates.index', compact('creatives', 'status', 'counts', 'type', 'source', 'mode', 'modes', 'filters', 'invitationTypes', 'previewUrls'));
    }

    public function store(Request $request, CreativeSvgArtwork $svg): JsonResponse
    {
        if ($request->input('design_mode') === CreativeTemplate::MODE_SVG) {
            $validated = $request->validate([
                'image' => CreativeSvgArtwork::UPLOAD_RULES,
                'name' => 'nullable|string|max:255',
                'description' => 'nullable|string|max:1000',
                'invitation_type' => ['required', Rule::in(array_column(config('invitation_templates.templates', []), 'key'))],
            ], ['image.mimes' => 'اختر ملف SVG صادرًا من محرّر التصميم.']);
            try {
                $template = $svg->upload(null, $request->file('image'), $validated['invitation_type'], $validated['name'] ?? null, $validated['description'] ?? null);
            } catch (InvitationException $e) {
                return response()->json(['message' => $e->getMessage()], $e->httpStatus);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم رفع ملف SVG. راجع نتيجة الفحص.',
                'data' => ['redirect_url' => route('dashboard.creatives.svg.edit', $template, absolute: false)],
            ], 201);
        }

        $validated = $request->validate([
            'image' => CreativeTemplateService::ARTWORK_RULES,
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'invitation_type' => ['required', Rule::in(array_column(config('invitation_templates.templates', []), 'key'))],
        ] + CreativeTemplateService::designRules(), [
            'text_layout.required_if' => 'احفظ توزيع النص في المحرر.',
        ]);

        $template = $this->creatives->upload(
            null,
            $request->file('image'),
            $validated['name'] ?? null,
            $validated['description'] ?? null,
            $validated['invitation_type'],
            textLayout: CreativeTextLayout::validate($validated['text_layout'] ?? null, $validated['invitation_type'], $validated['design_mode'] ?? null),
            designMode: $validated['design_mode'] ?? null,
        );
        if ($template->designMode() === CreativeTemplate::MODE_ARTWORK) {
            return response()->json([
                'success' => true,
                'message' => 'تم رفع التصميم. حدّد حقول الكتابة وراجعها قبل الاعتماد.',
                'data' => ['redirect_url' => route('dashboard.creatives.artwork.edit', $template, absolute: false)],
            ], 201);
        }

        $template = $this->creatives->approve($template, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'تم رفع التصميم واعتماده كقالب.',
            'data' => [
                'template' => $template->toPickerArray(),
                'redirect_url' => route('dashboard.creatives.index', [
                    'status' => 'approved',
                    'invitation_type' => $template->invitation_type,
                    'source' => 'admin',
                ], absolute: false),
            ],
        ], 201);
    }

    public function update(Request $request, CreativeTemplate $template): JsonResponse
    {
        $validated = $request->validate([
            'invitation_type' => ['present', 'nullable', Rule::in(array_column(config('invitation_templates.templates', []), 'key'))],
        ]);
        DB::transaction(function () use ($template, $validated) {
            $template = CreativeTemplate::lockForUpdate()->findOrFail($template->id);
            if ($template->invitation_type !== $validated['invitation_type']) {
                $template->update($validated + [
                    'text_layout' => CreativeTextLayout::reclassify($template, $validated['invitation_type']),
                ]);
            }
        });

        return response()->json(['success' => true, 'message' => 'تم حفظ نوع الدعوة.']);
    }

    public function editLayout(CreativeTemplate $template): View
    {
        abort_unless($template->designMode() === CreativeTemplate::MODE_OVERLAY && $template->invitation_type && $template->image_url, 404);
        $fields = CreativeTextLayout::editableFields($template->invitation_type);
        $layout = CreativeTextLayout::messageLayout($template->invitation_type, $template->text_layout);

        return view('dashboard.creative-templates.edit-layout', compact('template', 'fields', 'layout'));
    }

    public function editArtwork(CreativeTemplate $template): View
    {
        $this->requireArtwork($template);
        $fields = $template->editable_text ?? [];

        return view('dashboard.creative-templates.review-artwork', compact('template', 'fields'));
    }

    public function editSvg(CreativeTemplate $template): View
    {
        abort_unless($template->designMode() === CreativeTemplate::MODE_SVG, 404);

        return view('dashboard.creative-templates.review-svg', compact('template'));
    }

    public function svgSource(CreativeTemplate $template, CreativeSvgArtwork $svg): JsonResponse
    {
        return response()->json($svg->source($template), headers: ['Cache-Control' => 'private, no-store']);
    }

    public function saveSvg(Request $request, CreativeTemplate $template, CreativeSvgArtwork $svg): JsonResponse
    {
        abort_unless($template->svgReadyForReview() && $template->status !== CreativeTemplate::STATUS_REJECTED, 422, 'راجع التنبيهات الظاهرة في فحص SVG.');
        $validated = $request->validate([
            'fields' => 'required|array|min:1|max:12',
            'fields.*' => 'required|array',
            'original' => 'required|'.CreativeSvgArtwork::PNG_RULES,
            'preview' => 'required|'.CreativeSvgArtwork::PNG_RULES,
            'revision' => 'nullable|string|regex:/^[a-f0-9]{64}$/',
        ]);
        try {
            $svg->save($template, $request->user(), $validated['fields'], $request->file('original'), $request->file('preview'), $validated['revision'] ?? null);
        } catch (InvitationException $error) {
            return response()->json(['message' => $error->getMessage()], $error->httpStatus);
        }

        return response()->json(['redirect_url' => route('dashboard.creatives.index', ['status' => 'approved'], absolute: false)]);
    }

    public function artworkSource(CreativeTemplate $template): StreamedResponse
    {
        $this->requireArtwork($template);
        $disk = Storage::disk('public');
        abort_unless($disk->exists($template->image_path), 404);

        return $disk->response($template->image_path);
    }

    public function previewArtwork(Request $request, CreativeTemplate $template, CreativeArtworkReview $review): JsonResponse
    {
        $this->requireArtwork($template);
        [$fields, $background, $reuse] = $this->artworkInput($request);
        try {
            return response()->json($review->preview($template, $fields, $background, $reuse));
        } catch (InvitationException $error) {
            return response()->json(['message' => $error->getMessage(), 'code' => $error->errorCode], $error->httpStatus);
        }
    }

    public function saveArtwork(Request $request, CreativeTemplate $template, CreativeArtworkReview $review): JsonResponse
    {
        $this->requireArtwork($template);
        [$fields, $background, $reuse] = $this->artworkInput($request);
        $proof = $request->validate(['proof' => 'required|string|regex:/^[a-f0-9]{64}$/'])['proof'];
        try {
            $saved = $review->save($template, $request->user(), $fields, $background, $reuse, $proof);
        } catch (InvitationException $error) {
            return response()->json(['message' => $error->getMessage(), 'code' => $error->errorCode], $error->httpStatus);
        }

        return response()->json([
            'redirect_url' => route('dashboard.creatives.index', ['status' => 'approved'], absolute: false),
            'template' => $saved->toPickerArray(),
        ]);
    }

    private function artworkInput(Request $request): array
    {
        $validated = $request->validate([
            'fields' => 'required|string|json|max:30000',
            'background' => str_replace('required|', 'nullable|', CreativeTemplateService::ARTWORK_RULES),
            'reuse_background' => 'sometimes|boolean',
        ]);

        return [$validated['fields'], $request->file('background')?->get(), $request->boolean('reuse_background')];
    }

    private function requireArtwork(CreativeTemplate $template): void
    {
        abort_unless($template->canPrepareArtwork() && $template->status !== CreativeTemplate::STATUS_REJECTED, 404);
    }

    public function saveLayout(Request $request, CreativeTemplate $template): RedirectResponse|JsonResponse
    {
        DB::transaction(function () use ($template, $request) {
            $template = CreativeTemplate::lockForUpdate()->findOrFail($template->id);
            $layout = $this->submittedLayout($request, $template);
            $template->text_layout = $layout;
            if (isset($layout['message'])) {
                $template->design_mode = CreativeTemplate::MODE_OVERLAY;
            }
            $template->save();
        });

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('layout_saved', 'تم حفظ توزيع النص.');
    }

    public function previewLayout(Request $request, CreativeTemplate $template, InvitationCardRenderer $renderer): JsonResponse
    {
        $template->text_layout = $this->submittedLayout($request, $template);
        if (isset($template->text_layout['message'])) {
            $template->design_mode = CreativeTemplate::MODE_OVERLAY;
        }
        try {
            $path = $renderer->renderTemplatePreview($template, allowOverflow: false);
        } catch (InvitationException $e) {
            return response()->json(['message' => $e->getMessage()], $e->httpStatus);
        }
        if (! $path) {
            return response()->json(['message' => 'تعذّر إنشاء معاينة القالب.'], 422);
        }

        return response()->json(['image_url' => url('storage/'.$path)]);
    }

    private function submittedLayout(Request $request, CreativeTemplate $template): array
    {
        abort_unless($template->designMode() === CreativeTemplate::MODE_OVERLAY && $template->invitation_type, 404);
        $validated = $request->validate([
            'invitation_type' => 'required|string|max:64',
            'text_layout' => 'required|string|max:12000',
        ]);
        if ($template->invitation_type !== $validated['invitation_type']) {
            throw ValidationException::withMessages(['text_layout' => 'تغيّر نوع الدعوة. حدّث الصفحة وجرّب مرة ثانية.']);
        }

        return CreativeTextLayout::validate($validated['text_layout'], $template->invitation_type, $template->design_mode);
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

<?php

namespace App\Http\Controllers;

use App\Exceptions\InvitationException;
use App\Http\Requests\Invitations\InvitationResponseRequest;
use App\Models\Notification;
use App\Models\CreativeTemplate;
use App\Models\InvitationBackground;
use App\Models\NotificationGroup;
use App\Models\Person;
use App\Services\Invitations\CreativeArtworkReview;
use App\Services\Invitations\CreativeTemplateService;
use App\Services\Invitations\CreativeSvgArtwork;
use App\Services\Invitations\InvitationDispatchService;
use App\Services\Invitations\InvitationGroupService;
use App\Services\Invitations\InvitationMessageService;
use App\Services\Invitations\InvitationResponseService;
use App\Services\Invitations\InvitationTreePickerService;
use App\Services\Notifications\MobileNotificationService;
use App\Services\SiteIdentityResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InvitationController extends Controller
{
    private const TYPOGRAPHY = ['font_family' => 'amiri', 'font_weight' => 'bold'];

    public function __construct(
        protected SiteIdentityResolver $identityResolver,
        protected InvitationGroupService $groups,
        protected InvitationDispatchService $dispatcher,
        protected InvitationMessageService $messages,
        protected InvitationTreePickerService $treePicker,
    ) {}

    public function index(Request $request): View
    {
        $person = $this->identityPerson($request);

        if (! $person) {
            $authMethod = session('site_auth_method');

            return view('web-site.invitations.locked', [
                'isPinSession' => $authMethod === 'pin',
            ]);
        }

        $eligibility = app(\App\Services\Invitations\InvitationEligibilityService::class);

        if ($eligibility->isUnderage($person)) {
            return view('web-site.invitations.locked', [
                'isPinSession' => false,
                'customTitle' => 'غير متاح لعمرك',
                'customMessage' => 'ميزة إرسال الدعوات متاحة فقط لمن بلغ 20 سنة فأكثر.',
            ]);
        }

        $missing = $eligibility->missingEssentialFields($person);
        if (count($missing) > 0) {
            return view('web-site.invitations.incomplete', [
                'person' => $person,
                'missing' => $missing,
            ]);
        }

        $groups = $this->groups->ownedByPerson($person->id);
        $recent = Notification::query()
            ->where('person_id', $person->id)
            ->with(['recipients'])
            ->latest()
            ->take(5)
            ->get();

        return view('web-site.invitations.dashboard', [
            'person' => $person,
            'total_groups' => $groups->count(),
            'total_sent' => Notification::query()->where('person_id', $person->id)->count(),
            'groups' => $groups->take(6),
            'recent_notifications' => $recent,
        ]);
    }

    public function send(Request $request): View
    {
        $person = $request->attributes->get('identity_person');
        $groups = $this->groups->ownedByPerson($person->id, excludeOwner: true);
        $templates = $this->messages->templates();

        $backgrounds = InvitationBackground::approved()->latest()->get();
        $creatives = CreativeTemplate::approved()->forEditablePicker()->latest()->get();

        return view('web-site.invitations.send', compact('person', 'groups', 'templates', 'backgrounds', 'creatives'));
    }

    public function creativeSpace(Request $request): View
    {
        $person = $request->attributes->get('identity_person');
        $templates = $this->messages->templates();
        $myCreatives = CreativeTemplate::where('uploaded_by', $person->id)->latest()->orderByDesc('id')->paginate(12);

        return view('web-site.invitations.creative-space', compact('templates', 'myCreatives'));
    }

    public function previewCreativeArtwork(Request $request, int $id, CreativeTemplateService $creatives): RedirectResponse
    {
        $person = $request->attributes->get('identity_person');
        $template = CreativeTemplate::query()->where(function ($query) use ($person) {
            $query->where('uploaded_by', $person->id)
                ->orWhere(fn ($approved) => $approved->approved()->whereNotNull('invitation_type'));
        })->findOrFail($id);

        $preview = $creatives->previewImageUrl($template);
        if ($preview) {
            return redirect()->to($this->previewImageUrl($preview));
        }

        abort_unless($template->image_url, 404);

        return redirect()->to(filter_var($template->image_path, FILTER_VALIDATE_URL)
            ? $template->image_url : asset('storage/'.$template->image_path));
    }

    public function creativeSvgSource(int $id, CreativeSvgArtwork $svg): JsonResponse
    {
        $template = CreativeTemplate::approved()->forEditablePicker()->findOrFail($id);

        return response()->json($svg->source($template), headers: ['Cache-Control' => 'private, no-store']);
    }

    public function previewCreativeFields(Request $request, CreativeArtworkReview $review): JsonResponse
    {
        $validated = $request->validate([
            'image' => CreativeTemplateService::ARTWORK_RULES,
            'fields' => 'required|string|json|max:30000',
        ]);
        try {
            return response()->json($review->previewUpload($request->attributes->get('identity_person'), $request->file('image'), $validated['fields']));
        } catch (InvitationException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'code' => $exception->errorCode], $exception->httpStatus);
        }
    }

    public function submitCreative(Request $request, CreativeSvgArtwork $svg, CreativeArtworkReview $review): RedirectResponse|JsonResponse
    {
        $validated = $request->validateWithBag('creative', [
            'image' => 'required|file|mimes:jpg,jpeg,png,webp,svg|max:16384',
            'name' => 'nullable|string|max:255',
            'invitation_type' => ['required', Rule::in(array_column($this->messages->templates(), 'key'))],
            'publication_consent' => ['accepted'],
            'fields' => 'nullable|string|json|max:30000',
            'proof' => 'nullable|string|regex:/^[a-f0-9]{64}$/',
        ], [
            'image.mimes' => 'اختر تصميمًا بصيغة JPG أو PNG أو WebP أو SVG.',
            'publication_consent.accepted' => 'يرجى الموافقة على عرض التصميم ضمن القوالب عند اعتماده.',
        ]);

        $file = $request->file('image');
        $owner = $request->attributes->get('identity_person');
        try {
            if ($file->getMimeType() === 'image/svg+xml') {
                $design = $svg->upload($owner, $file, $validated['invitation_type'], $validated['name'] ?? null);
            } else {
                $request->validateWithBag('creative', ['image' => CreativeTemplateService::ARTWORK_RULES]);
                $design = $review->submitUpload($owner, $file, $validated['invitation_type'], $validated['name'] ?? null, $validated['fields'] ?? null, $validated['proof'] ?? null);
            }
        } catch (InvitationException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage(), 'code' => $e->errorCode], $e->httpStatus);
            }
            return back()->withErrors(['image' => $e->getMessage()], 'creative')->withInput();
        }

        $message = $design->designMode() === CreativeTemplate::MODE_SVG && ! $design->svgReadyForReview()
            ? 'تم إرسال التصميم إلى الإدارة. راجع ملاحظات فحص SVG ضمن تصاميمي.'
            : 'تم إرسال التصميم إلى الإدارة للمراجعة.';
        if ($request->expectsJson()) {
            $request->session()->flash('creative_success', $message);

            return response()->json(['redirect_url' => route('invitations.creatives.index', absolute: false)], 201);
        }

        return redirect()->route('invitations.creatives.index')->with('creative_success', $message);
    }

    public function consentToCreativePublication(Request $request, int $id, CreativeTemplateService $creatives): RedirectResponse
    {
        $request->validateWithBag('creative', ['publication_consent' => 'accepted']);

        try {
            $creatives->recordPublicationConsent($request->attributes->get('identity_person'), $id);
        } catch (InvitationException $e) {
            return redirect()->route('invitations.creatives.index')->withErrors(['publication_consent' => $e->getMessage()], 'creative');
        }

        return redirect()->route('invitations.creatives.index')->with('creative_success', 'تم حفظ موافقتك على عرض التصميم.');
    }

    public function sendSubmit(Request $request): RedirectResponse
    {
        /** @var Person $person */
        $person = $request->attributes->get('identity_person');

        $validated = $request->validate([
            'channels' => 'required|array|min:1',
            'channels.*' => 'in:whatsapp,push',
            'recipient_type' => 'required|in:persons,group,external',
            'person_ids' => 'required_if:recipient_type,persons|array',
            'person_ids.*' => 'integer|exists:persons,id',
            'group_id' => 'required_if:recipient_type,group|nullable|integer|exists:notification_groups,id',
            'external_name' => 'nullable|string|max:255',
            'external_country_code' => 'nullable|string|max:10',
            'external_phone' => 'required_if:recipient_type,external|nullable|string|max:50',
            'external_recipients' => 'nullable|array',
            'external_recipients.*.name' => 'nullable|string|max:255',
            'external_recipients.*.phone' => 'nullable|string|max:50',
            'external_recipients.*.country_code' => 'nullable|string|max:10',
            'title' => 'nullable|string|max:255',
            'body' => 'nullable|string|max:65535',
            'creative_id' => 'nullable|integer',
            'template_key' => 'nullable|string|max:64',
            'template_vars' => 'nullable|array',
            'template_vars.*' => 'nullable|string|max:255',
            'exclude_deceased' => 'nullable|string',
            'media_type' => 'nullable|string|in:image,video,voice',
            'media' => 'nullable|file|max:16384',
            'svg_card' => 'nullable|'.CreativeSvgArtwork::PNG_RULES,
            'background_id' => 'nullable|integer',
        ] + ['creative_edit_source' => 'nullable|string|regex:/^[0-9a-f]{64}$/'], [], [
            'channels' => 'قنوات الإرسال',
            'recipient_type' => 'نوع المستلمين',
            'person_ids' => 'الأشخاص',
            'group_id' => 'المجموعة',
            'external_country_code' => 'مفتاح الدولة',
            'external_phone' => 'رقم الواتساب للشخص المدعو',
            'body' => 'نص الرسالة',
        ]);

        try {
            $result = $this->dispatcher->send($person, [
                'channels' => $validated['channels'],
                'recipient_type' => $validated['recipient_type'],
                'person_ids' => $validated['person_ids'] ?? [],
                'group_id' => $validated['group_id'] ?? null,
                'external_name' => $validated['external_name'] ?? null,
                'external_country_code' => $validated['external_country_code'] ?? null,
                'external_phone' => $validated['external_phone'] ?? null,
                'external_recipients' => $validated['external_recipients'] ?? [],
                'title' => $validated['title'] ?? null,
                'body' => $validated['body'] ?? '',
                'template_key' => $validated['template_key'] ?? null,
                'template_vars' => $validated['template_vars'] ?? [],
                'exclude_deceased' => $request->has('exclude_deceased'),
                'media_type' => $validated['media_type'] ?? null,
                'media' => $request->file('media'),
                'svg_card' => $request->file('svg_card'),
                'allow_web_template' => true,
                'background_id' => $validated['background_id'] ?? null,
                'creative_id' => $validated['creative_id'] ?? null,
                'creative_edit_source' => $validated['creative_edit_source'] ?? null,
            ] + self::TYPOGRAPHY);
        } catch (InvitationException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('invitations.logs')
            ->with('success', $result['notification']->invitationSubmissionMessage());
    }

    public function logs(Request $request, MobileNotificationService $inboxService): View
    {
        /** @var Person $person */
        $person = $request->attributes->get('identity_person');
        $incoming = $request->query('direction') === 'incoming';
        $inbox = $request->query('direction') === 'notifications';

        if ($inbox) {
            $query = $inboxService->getInboxQueryForPerson($person->id)
                ->whereHas('recipients', fn ($recipients) => $recipients->where('person_id', $person->id))
                ->where(fn ($items) => $items->where('person_id', '!=', $person->id)
                    ->orWhereIn('targeting_filters->data->type', [Notification::INVITATION_RESPONSE_TYPE, Notification::CREATIVE_DECISION_TYPE]))
                ->withInvitationResponseFor($person->id)->with('person');
        } elseif ($incoming) {
            $query = Notification::receivedInvitations($person->id)->withInvitationResponseFor($person->id)->with('person');
        } else {
            $query = Notification::query()->where('person_id', $person->id)->with('recipients.person');
        }
        $notifications = $query->latest()->orderByDesc('id')->paginate(10)->withQueryString();
        foreach ($notifications as $notification) {
            $vars = data_get($notification->targeting_filters, 'template_vars');
            $vars = is_array($vars) ? $vars : [];
            $singleRecipient = ! $incoming && ! $inbox && $notification->recipients->count() === 1
                ? $notification->recipients->first() : null;
            $recipient = $incoming || $inbox ? $person : $singleRecipient?->person;
            if ($recipient === null && $singleRecipient?->recipient_name) {
                $name = $singleRecipient->recipient_name;
                $vars = ['name' => $name, 'first_name' => $name, 'full_name' => $name] + $vars;
            }
            $sender = $notification->person_id === $person->id ? $person : ($notification->person_id ? $notification->person : null);
            $notification->display_title = $this->messages->replacePlaceholders($notification->title ?: 'دعوة عائلية', $recipient, $sender, $vars);
            $notification->display_body = $this->messages->replacePlaceholders((string) $notification->body, $recipient, $sender, $vars);
        }

        return view('web-site.invitations.logs', compact('notifications', 'person', 'incoming', 'inbox'));
    }

    public function respond(InvitationResponseRequest $request, Notification $notification, InvitationResponseService $responses): RedirectResponse
    {
        $person = $request->attributes->get('identity_person');
        $redirect = redirect()->route('invitations.logs', [
            'direction' => $request->query('direction') === 'notifications' ? 'notifications' : 'incoming',
            'page' => max(1, $request->integer('page', 1)),
        ]);

        try {
            $responses->save($notification, $person->id, $request->validated('response'));
        } catch (InvitationException $exception) {
            if ($exception->errorCode === InvitationResponseService::RESPONSE_ALREADY_RECORDED) {
                return $redirect->with('response_error', $exception->getMessage())
                    ->with('responded_invitation_id', $notification->id);
            }
            abort($exception->httpStatus, $exception->getMessage());
        }

        return $redirect->with('success', 'تم حفظ ردك.')->with('responded_invitation_id', $notification->id);
    }

    public function groupsIndex(Request $request): View
    {
        /** @var Person $person */
        $person = $request->attributes->get('identity_person');
        $groups = $this->groups->ownedByPerson($person->id);

        return view('web-site.invitations.groups.index', compact('groups', 'person'));
    }

    public function groupsCreate(Request $request): View
    {
        return view('web-site.invitations.groups.create', [
            'person' => $request->attributes->get('identity_person'),
        ]);
    }

    public function groupsStore(Request $request): RedirectResponse
    {
        /** @var Person $person */
        $person = $request->attributes->get('identity_person');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'person_ids' => 'nullable|array',
            'person_ids.*' => 'integer|exists:persons,id',
        ], [], [
            'name' => 'اسم المجموعة',
            'description' => 'الوصف',
        ]);

        try {
            $group = $this->groups->create(
                $person->id,
                $validated['name'],
                $validated['description'] ?? null,
                $validated['person_ids'] ?? []
            );
        } catch (InvitationException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('invitations.groups.edit', $group)
            ->with('success', 'تم إنشاء المجموعة بنجاح');
    }

    public function groupsEdit(Request $request, NotificationGroup $group): View|RedirectResponse
    {
        /** @var Person $person */
        $person = $request->attributes->get('identity_person');

        try {
            $owned = $this->groups->findOwned($group->id, $person->id);
        } catch (InvitationException $e) {
            return redirect()->route('invitations.groups.index')->with('error', $e->getMessage());
        }

        $owned->load('persons');

        return view('web-site.invitations.groups.edit', [
            'group' => $owned,
            'person' => $person,
        ]);
    }

    public function groupsUpdate(Request $request, NotificationGroup $group): RedirectResponse
    {
        /** @var Person $person */
        $person = $request->attributes->get('identity_person');

        try {
            $owned = $this->groups->findOwned($group->id, $person->id);
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'person_ids' => 'nullable|array',
                'person_ids.*' => 'integer|exists:persons,id',
            ]);
            $this->groups->update($owned, $validated['name'], $validated['description'] ?? null);
            if (array_key_exists('person_ids', $validated)) {
                $this->groups->syncPersons($owned, $validated['person_ids'] ?? []);
            }
        } catch (InvitationException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('invitations.groups.index')->with('success', 'تم تحديث المجموعة بنجاح');
    }

    public function groupsDestroy(Request $request, NotificationGroup $group): RedirectResponse
    {
        /** @var Person $person */
        $person = $request->attributes->get('identity_person');

        try {
            $owned = $this->groups->findOwned($group->id, $person->id);
            $this->groups->delete($owned);
        } catch (InvitationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('invitations.groups.index')->with('success', 'تم حذف المجموعة بنجاح');
    }

    public function groupsAttachPerson(Request $request, NotificationGroup $group): RedirectResponse
    {
        /** @var Person $person */
        $person = $request->attributes->get('identity_person');

        $validated = $request->validate([
            'person_id' => 'required|integer|exists:persons,id',
        ]);

        try {
            $owned = $this->groups->findOwned($group->id, $person->id);
            $this->groups->attachPerson($owned, (int) $validated['person_id']);
        } catch (InvitationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'تمت إضافة الشخص للمجموعة');
    }

    public function groupsDetachPerson(Request $request, NotificationGroup $group, Person $member): RedirectResponse
    {
        /** @var Person $person */
        $person = $request->attributes->get('identity_person');

        try {
            $owned = $this->groups->findOwned($group->id, $person->id);
            $this->groups->detachPerson($owned, $member->id);
        } catch (InvitationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'تمت إزالة الشخص من المجموعة');
    }

    public function treePicker(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'tree' => $this->treePicker->fullTree(),
            'flags' => $this->treePicker->flagsMap(),
        ]);
    }

    public function searchPersons(Request $request): JsonResponse
    {
        $q = trim((string) $request->get('q', ''));
        $excludedPersonId = $request->boolean('exclude_sender')
            ? $request->attributes->get('identity_person')->id
            : null;

        return response()->json([
            'success' => true,
            'persons' => $this->treePicker->search($q, excludedPersonId: $excludedPersonId),
        ]);
    }

    public function previewMessage(Request $request): JsonResponse
    {
        /** @var Person $person */
        $person = $request->attributes->get('identity_person');

        $validated = $request->validate([
            'body' => 'nullable|string',
            'template_key' => 'nullable|string',
            'template_vars' => 'nullable|array',
            'template_vars.*' => 'nullable|string|max:255',
            'person_id' => 'nullable|integer|exists:persons,id',
            'group_id' => 'nullable|integer',
            'external_name' => 'nullable|string|max:255',
            'recipient_type' => 'nullable|string|in:persons,group,external',
            'title' => 'nullable|string|max:255',
            'background_id' => 'nullable|integer',
            'creative_id' => 'nullable|integer',
            'svg_card' => 'nullable|'.CreativeSvgArtwork::PNG_RULES,
        ] + ['creative_edit_source' => 'nullable|string|regex:/^[0-9a-f]{64}$/']) + self::TYPOGRAPHY;

        try {
            $creative = $this->dispatcher->creativeFor($validated, allowWeb: true);
            if ($request->routeIs('invitations.preview-artwork')) {
                return response()->json([
                    'success' => true,
                    'artwork_image' => $this->dispatcher->creativeArtworkLivePreview($creative, $validated),
                ]);
            }
            $sampleId = $validated['person_id'] ?? null;
            if ($sampleId !== null && (int) $sampleId === $person->id) {
                $sampleId = null;
            }
            if (! $sampleId && ($validated['recipient_type'] ?? null) === 'group' && ! empty($validated['group_id'])) {
                $group = $this->groups->findOwned((int) $validated['group_id'], $person->id);
                $sampleId = $group->persons()->whereKeyNot($person->id)->first()?->id;
            }
            $preview = $this->dispatcher->preview($person, $validated, $sampleId ? (int) $sampleId : null, $validated['external_name'] ?? null);
            $card = $creative ? $this->dispatcher->creativeCard($creative, $person, $validated) : null;
            if (! $creative && ! empty($validated['background_id'])) {
                $background = InvitationBackground::approved()->find($validated['background_id']);
                if (! $background) {
                    throw new InvitationException('الخلفية المحددة غير متاحة.', 'BACKGROUND_NOT_AVAILABLE', 422);
                }
                if (trim($preview) !== '') {
                    $card = app(\App\Services\Invitations\InvitationCardRenderer::class)
                        ->renderPreview($background, (string) ($validated['title'] ?? ''), $preview, self::TYPOGRAPHY);
                }
            }
        } catch (InvitationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->httpStatus);
        }

        return response()->json([
            'success' => true,
            'preview' => $preview,
            'card_image_url' => $card ? ($creative ? url('storage/'.$card) : Storage::disk('public')->url($card)) : null,
            'web_card_image_url' => $card ? asset('storage/'.$card) : null,
        ]);
    }

    private function previewImageUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && str_starts_with($path, '/storage/') ? asset(ltrim($path, '/')) : $url;
    }

    private function identityPerson(Request $request): ?Person
    {
        $identity = $this->identityResolver->resolve($request);
        if (! $identity) {
            return null;
        }

        return Person::find($identity['person_id']);
    }
}

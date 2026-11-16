<?php

namespace App\Http\Controllers;

use App\Exceptions\InvitationException;
use App\Models\Notification;
use App\Models\InvitationBackground;
use App\Models\NotificationGroup;
use App\Models\Person;
use App\Services\Invitations\InvitationDispatchService;
use App\Services\Invitations\InvitationGroupService;
use App\Services\Invitations\InvitationMessageService;
use App\Services\Invitations\InvitationTreePickerService;
use App\Services\SiteIdentityResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvitationController extends Controller
{
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
        $groups = $this->groups->ownedByPerson($person->id);
        $templates = $this->messages->templates();

        $backgrounds = InvitationBackground::approved()->latest()->get();

        return view('web-site.invitations.send', compact('person', 'groups', 'templates', 'backgrounds'));
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
            'background_id' => 'صورة الخلفية',
            'template_key' => 'nullable|string|max:64',
            'template_vars' => 'nullable|array',
            'template_vars.*' => 'nullable|string|max:255',
            'exclude_deceased' => 'nullable|string',
            'media_type' => 'nullable|string|in:image,video,voice',
            'media' => 'nullable|file|max:16384',
            'background_id' => 'nullable|integer',
        ], [], [
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
                'background_id' => $validated['background_id'] ?? null,
            ]);
        } catch (InvitationException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('invitations.logs')
            ->with('success', $result['notification']->invitationSubmissionMessage());
    }

    public function logs(Request $request): View
    {
        /** @var Person $person */
        $person = $request->attributes->get('identity_person');
        $incoming = $request->query('direction') === 'incoming';

        $query = $incoming
            ? Notification::receivedInvitations($person->id)->with('person:id,first_name,last_name')
            : Notification::query()->where('person_id', $person->id)->with('recipients.person');
        $notifications = $query->latest()->orderByDesc('id')->paginate(10)->withQueryString();

        return view('web-site.invitations.logs', compact('notifications', 'person', 'incoming'));
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

        return response()->json([
            'success' => true,
            'persons' => $q === '' ? [] : $this->treePicker->search($q),
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
            'person_id' => 'required|integer|exists:persons,id',
        ]);

        try {
            $preview = $this->dispatcher->preview($person, $validated, (int) $validated['person_id']);
        } catch (InvitationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->httpStatus);
        }

        return response()->json([
            'success' => true,
            'preview' => $preview,
        ]);
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

<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\InvitationException;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\InvitationBackground;
use App\Models\CreativeTemplate;
use App\Models\NotificationGroup;
use App\Models\Person;
use App\Services\Invitations\InvitationDispatchService;
use App\Services\Invitations\InvitationGroupService;
use App\Services\Invitations\InvitationMessageService;
use App\Services\Invitations\InvitationTreePickerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvitationApiController extends Controller
{
    public function __construct(
        protected InvitationGroupService $groups,
        protected InvitationDispatchService $dispatcher,
        protected InvitationMessageService $messages,
        protected InvitationTreePickerService $treePicker,
    ) {}

    public function templates(): JsonResponse
    {
        return $this->ok('قوالب الدعوات.', [
            'templates' => $this->messages->templates(),
        ]);
    }

    public function treePicker(): JsonResponse
    {
        return $this->ok('شجرة اختيار المستلمين.', [
            'tree' => $this->treePicker->fullTree(),
            'flags' => $this->treePicker->flagsMap(),
        ]);
    }

    public function searchPersons(Request $request): JsonResponse
    {
        $q = trim((string) $request->get('q', ''));

        return $this->ok('نتائج البحث.', [
            'persons' => $q === '' ? [] : $this->treePicker->search($q),
        ]);
    }

    public function groupsIndex(Request $request): JsonResponse
    {
        $person = $this->person($request);

        return $this->ok('مجموعاتك.', [
            'groups' => $this->groups->ownedByPerson($person->id)->map(fn ($g) => $this->groupPayload($g))->values(),
        ]);
    }

    public function groupsStore(Request $request): JsonResponse
    {
        $person = $this->person($request);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'person_ids' => 'nullable|array',
            'person_ids.*' => 'integer|exists:persons,id',
        ]);

        try {
            $group = $this->groups->create(
                $person->id,
                $validated['name'],
                $validated['description'] ?? null,
                $validated['person_ids'] ?? []
            );
        } catch (InvitationException $e) {
            return $this->fail($e);
        }

        return $this->ok('تم إنشاء المجموعة.', ['group' => $this->groupPayload($group->load('persons'))], 201);
    }

    public function groupsShow(Request $request, NotificationGroup $group): JsonResponse
    {
        try {
            $owned = $this->groups->findOwned($group->id, $this->person($request)->id)->load('persons');
        } catch (InvitationException $e) {
            return $this->fail($e);
        }

        return $this->ok('تفاصيل المجموعة.', ['group' => $this->groupPayload($owned)]);
    }

    public function groupsUpdate(Request $request, NotificationGroup $group): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'person_ids' => 'nullable|array',
            'person_ids.*' => 'integer|exists:persons,id',
        ]);

        try {
            $owned = $this->groups->findOwned($group->id, $this->person($request)->id);
            if (isset($validated['name'])) {
                $this->groups->update($owned, $validated['name'], $validated['description'] ?? $owned->description);
            }
            if (array_key_exists('person_ids', $validated)) {
                $this->groups->syncPersons($owned, $validated['person_ids'] ?? []);
            }
            $owned = $owned->fresh(['persons'])->loadCount('persons');
        } catch (InvitationException $e) {
            return $this->fail($e);
        }

        return $this->ok('تم تحديث المجموعة.', ['group' => $this->groupPayload($owned)]);
    }

    public function groupsDestroy(Request $request, NotificationGroup $group): JsonResponse
    {
        try {
            $owned = $this->groups->findOwned($group->id, $this->person($request)->id);
            $this->groups->delete($owned);
        } catch (InvitationException $e) {
            return $this->fail($e);
        }

        return $this->ok('تم حذف المجموعة.');
    }

    public function attachPerson(Request $request, NotificationGroup $group): JsonResponse
    {
        $validated = $request->validate([
            'person_id' => 'required|integer|exists:persons,id',
        ]);

        try {
            $owned = $this->groups->findOwned($group->id, $this->person($request)->id);
            $this->groups->attachPerson($owned, (int) $validated['person_id']);
        } catch (InvitationException $e) {
            return $this->fail($e);
        }

        return $this->ok('تمت إضافة الشخص.', ['group' => $this->groupPayload($owned->fresh(['persons'])->loadCount('persons'))]);
    }

    public function detachPerson(Request $request, NotificationGroup $group, Person $member): JsonResponse
    {
        try {
            $owned = $this->groups->findOwned($group->id, $this->person($request)->id);
            $this->groups->detachPerson($owned, $member->id);
        } catch (InvitationException $e) {
            return $this->fail($e);
        }

        return $this->ok('تمت إزالة الشخص.');
    }

    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'body' => 'nullable|string',
            'template_key' => 'nullable|string',
            'template_vars' => 'nullable|array',
            'person_id' => 'nullable|integer|exists:persons,id',
            'external_name' => 'nullable|string|max:255',
            'external_country_code' => 'nullable|string|max:10',
            'recipient_type' => 'nullable|string|in:persons,group,external',
            'title' => 'nullable|string|max:255',
            'background_id' => 'nullable|integer',
            'creative_id' => 'nullable|integer',
        ]);

        try {
            $background = $this->previewBackground($validated);
            $creative = $this->previewCreative($validated);
            $preview = $this->dispatcher->preview(
                $this->person($request),
                $validated,
                ! empty($validated['person_id']) ? (int) $validated['person_id'] : null,
                $validated['external_name'] ?? null
            );
        } catch (InvitationException $e) {
            return $this->fail($e);
        }

        return $this->ok('معاينة الرسالة.', ['preview' => $preview, 'background' => $background ? $background->toPickerArray() : null, 'creative' => $creative ? $creative->toPickerArray() : null, 'card_image_url' => $this->previewCard($background, $validated, $preview)]);
    }


    private function previewCreative(array $validated): ?CreativeTemplate
    {
        if (empty($validated['creative_id'])) {
            return null;
        }
        $creative = CreativeTemplate::approved()->find((int) $validated['creative_id']);
        if (! $creative) {
            throw new \App\Exceptions\InvitationException('__CT_NOT_AVAILABLE__', 'CREATIVE_NOT_AVAILABLE', 422);
        }

        return $creative;
    }

    private function previewCard(?InvitationBackground $background, array $validated, string $preview): ?string
    {
        if (! $background) {
            return null;
        }
        try {
            $rendered = app(\App\Services\Invitations\InvitationCardRenderer::class)->renderPreview(
                $background,
                (string) ($validated['title'] ?? ''),
                $preview
            );
            if (! $rendered) {
                return null;
            }

            return \Illuminate\Support\Facades\Storage::disk('public')->url($rendered);
        } catch (\Throwable) {
            return null;
        }
    }

    private function previewBackground(array $validated): ?InvitationBackground
    {
        if (empty($validated['background_id'])) {
            return null;
        }
        $background = InvitationBackground::approved()->find((int) $validated['background_id']);
        if (! $background) {
            throw new \App\Exceptions\InvitationException('الخلفية المحددة غير متاحة.', 'BACKGROUND_NOT_AVAILABLE', 422);
        }

        return $background;
    }


    private function attachBackgrounds($items): void
    {
        $ids = $items->map(fn ($n) => is_array($n->targeting_filters) ? ($n->targeting_filters['background_id'] ?? null) : null)
            ->filter()
            ->unique()
            ->values();
        if ($ids->isEmpty()) {
            $items->each(fn ($n) => $n->setAttribute('background', null));
            return;
        }
        $map = InvitationBackground::query()->whereIn('id', $ids)->get()->mapWithKeys(
            fn (InvitationBackground $b) => [$b->id => $b->toPickerArray()]
        );
        $items->each(function ($n) use ($map) {
            $id = is_array($n->targeting_filters) ? ($n->targeting_filters['background_id'] ?? null) : null;
            $n->setAttribute('background', $id ? ($map[$id] ?? null) : null);
        });
        $cids = $items->map(fn ($n) => is_array($n->targeting_filters) ? ($n->targeting_filters['creative_id'] ?? null) : null)
            ->filter()
            ->unique()
            ->values();
        $cmap = $cids->isEmpty() ? collect() : CreativeTemplate::query()->whereIn('id', $cids)->get()->mapWithKeys(
            fn (CreativeTemplate $t) => [$t->id => $t->toPickerArray()]
        );
        $items->each(function ($n) use ($cmap) {
            $cid = is_array($n->targeting_filters) ? ($n->targeting_filters['creative_id'] ?? null) : null;
            $n->setAttribute('creative', $cid ? ($cmap[$cid] ?? null) : null);
        });
    }

    public function send(Request $request): JsonResponse
    {
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
            'template_key' => 'nullable|string|max:64',
            'template_vars' => 'nullable|array',
            'exclude_deceased' => 'nullable|boolean',
            'media_type' => 'nullable|string|in:image,video,voice',
            'background_id' => 'nullable|integer',
            'creative_id' => 'nullable|integer',
            'media' => 'nullable|file|max:16384',
        ]);

        try {
            $result = $this->dispatcher->send($this->person($request), [
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
                'exclude_deceased' => $validated['exclude_deceased'] ?? true,
                'media_type' => $validated['media_type'] ?? null,
                'background_id' => $validated['background_id'] ?? null,
                'creative_id' => $validated['creative_id'] ?? null,
                'media' => $request->file('media'),
            ]);
        } catch (InvitationException $e) {
            return $this->fail($e);
        }

        return $this->ok('تم تقديم دعوتك بنجاح، وهي قيد المراجعة والاعتماد من قبل إدارة العائلة.', [
            'notification' => [
                'id' => $result['notification']->id,
                'status' => $result['notification']->status,
                'whatsapp_jobs' => $result['whatsapp_jobs'],
                'push_jobs' => $result['push_jobs'],
            ],
        ], 201);
    }

    public function logs(Request $request): JsonResponse
    {
        $notifications = Notification::query()
            ->where('person_id', $this->person($request)->id)
            ->with(['recipients.person:id,first_name'])
            ->latest()
            ->paginate(15);

        $this->attachBackgrounds($notifications->getCollection());

        return $this->ok('سجل الإرسال.', [
            'notifications' => $notifications,
        ]);
    }

    public function logShow(Request $request, Notification $notification): JsonResponse
    {
        if ((int) $notification->person_id !== (int) $this->person($request)->id) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح.',
                'code' => 'GROUP_NOT_OWNED',
                'data' => null,
                'errors' => null,
            ], 403);
        }

        $notification->load(['recipients.person:id,first_name,photo_url']);

        $this->attachBackgrounds(collect([$notification]));

        return $this->ok('تفاصيل الإرسال.', ['notification' => $notification]);
    }

    private function person(Request $request): Person
    {
        return $request->attributes->get('identity_person');
    }

    private function groupPayload(NotificationGroup $group): array
    {
        $group->loadMissing('persons');

        return [
            'id' => $group->id,
            'name' => $group->name,
            'description' => $group->description,
            'persons_count' => $group->persons_count ?? $group->persons->count(),
            'persons' => $group->persons->map(fn (Person $p) => [
                'id' => $p->id,
                'first_name' => $p->first_name,
                'photo_url' => $p->photo_url,
            ])->values(),
        ];
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

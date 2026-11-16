<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Walking\InviteGroupMemberRequest;
use App\Http\Requests\Walking\StoreWalkingGroupRequest;
use App\Http\Requests\Walking\UpdateWalkingGroupRequest;
use App\Http\Resources\Walking\WalkingGroupMemberResource;
use App\Http\Resources\Walking\WalkingGroupResource;
use App\Models\Person;
use App\Models\WalkingGroup;
use App\Models\WalkingGroupMember;
use App\Services\SiteIdentityResolver;
use App\Services\Walking\WalkingGroupService;
use App\Services\Walking\WalkingLeaderboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Walking group (challenge) CRUD + membership management for the mobile app.
 *
 * Privacy rules enforced at every read:
 *  - Private groups â†’ only visible to accepted members.
 *  - Public groups  â†’ visible to all app users.
 */
class WalkingGroupApiController extends Controller
{
    public function __construct(
        protected SiteIdentityResolver   $identityResolver,
        protected WalkingGroupService    $groupService,
        protected WalkingLeaderboardService $leaderboardService
    ) {}

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // GET /api/v1/walking/groups
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * List all groups visible to the authenticated person:
     *  - Public groups (always visible).
     *  - Private groups they belong to.
     */
    public function index(Request $request): JsonResponse
    {
        $personId = $this->resolvePersonId($request);
        $request->attributes->set('viewer_person_id', $personId);

        $statusFilter = (string) $request->query('status', 'active');

        $query = WalkingGroup::withCount('members')
            ->filterStatus($statusFilter)
            ->orderByDesc('created_at');

        if ($personId) {
            $query->visibleToPerson($personId);
            $query->with(['memberships' => function ($m) use ($personId) {
                $m->where('person_id', $personId)->where('status', 'accepted');
            }]);
        } else {
            // Unauthenticated users see only public groups
            $query->public();
        }

        $groups = $query->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'تم استرجاع المجموعات.',
            'data'    => WalkingGroupResource::collection($groups)->response()->getData(true),
            'errors'  => null,
        ]);
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // POST /api/v1/walking/groups
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function store(StoreWalkingGroupRequest $request): JsonResponse
    {
        $personId = $this->resolvePersonOrFail($request);
        if ($personId instanceof JsonResponse) {
            return $personId;
        }

        $inviter = \App\Models\Person::find($personId);
        $validated = $request->validated();
        $group = $this->groupService->create($validated, $personId);

        if (! empty($validated['member_person_ids'])) {
            foreach ($validated['member_person_ids'] as $pid) {
                $this->groupService->addMember($group, (int) $pid, shouldNotify: true, inviter: $inviter);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء المجموعة / التحدي وإضافة الأعضاء بنجاح.',
            'data'    => new WalkingGroupResource($group->fresh(['memberships.person'])),
            'errors'  => null,
        ], 201);
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // GET /api/v1/walking/groups/{group}
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function show(Request $request, WalkingGroup $group): JsonResponse
    {
        $personId = $this->resolvePersonId($request);
        $request->attributes->set('viewer_person_id', $personId);

        // Enforce privacy — private group access is restricted to members
        if (! $group->is_public) {
            if (! $personId || ! $group->hasMember($personId)) {
                return $this->forbidden();
            }
        }

        $group->loadCount('members');
        $group->load(['memberships.person']);

        // Eager-aggregate steps in challenge for all members
        $memberPersonIds = $group->memberships->pluck('person_id')->filter()->unique()->toArray();
        if (! empty($memberPersonIds)) {
            $dateFrom = $group->start_date ?? '2020-01-01';
            $dateTo = $group->end_date ?? now()->toDateString();
            $stepsMap = \App\Models\WalkingRecord::query()
                ->whereIn('person_id', $memberPersonIds)
                ->whereBetween('date', [$dateFrom, $dateTo])
                ->groupBy('person_id')
                ->selectRaw('person_id, SUM(steps) as total_steps')
                ->pluck('total_steps', 'person_id');

            foreach ($group->memberships as $membership) {
                $membership->total_steps_in_challenge = (int) ($stepsMap[$membership->person_id] ?? 0);
            }
        }

        if ($group->relationLoaded('memberships')) {
            $sortedMemberships = $group->memberships->sort(function ($a, $b) {
                $stepsA = (int) ($a->total_steps_in_challenge ?? 0);
                $stepsB = (int) ($b->total_steps_in_challenge ?? 0);
                if ($stepsA !== $stepsB) {
                    return $stepsB <=> $stepsA; // Most steps first
                }

                $joinedA = $a->joined_at ? $a->joined_at->timestamp : 0;
                $joinedB = $b->joined_at ? $b->joined_at->timestamp : 0;
                if ($joinedA !== $joinedB) {
                    return $joinedA <=> $joinedB; // Earliest joined first
                }

                return $a->id <=> $b->id;
            })->values();

            $group->setRelation('memberships', $sortedMemberships);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم استرجاع بيانات المجموعة.',
            'data'    => new WalkingGroupResource($group),
            'errors'  => null,
        ]);
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // PUT /api/v1/walking/groups/{group}
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function update(UpdateWalkingGroupRequest $request, WalkingGroup $group): JsonResponse
    {
        try {
            $personId = $this->resolvePersonOrFail($request);
            if ($personId instanceof JsonResponse) {
                return $personId;
            }

            if (! $group->isAdmin($personId)) {
                return $this->forbidden('فقط منشئ/مدير المجموعة يمكنه التعديل.');
            }

            $request->attributes->set('viewer_person_id', $personId);

            $updated = $this->groupService->update($group, $request->validated());
            $updated->load(['memberships.person']);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث بيانات المجموعة بنجاح.',
                'data'    => new WalkingGroupResource($updated),
                'errors'  => null,
            ]);
        } catch (\Throwable $e) {
            \Log::error('[WalkingGroupApiController] update error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في الخادم أثناء تحديث التحدي: ' . $e->getMessage(),
                'data'    => null,
                'errors'  => $e->getMessage(),
            ], 500);
        }
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // DELETE /api/v1/walking/groups/{group}
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function destroy(Request $request, WalkingGroup $group): JsonResponse
    {
        $personId = $this->resolvePersonOrFail($request);
        if ($personId instanceof JsonResponse) {
            return $personId;
        }

        if (! $group->isAdmin($personId)) {
            return $this->forbidden('فقط منشئ/مدير المجموعة يمكنه الحذف.');
        }

        $group->delete(); // soft delete

        return response()->json([
            'success' => true,
            'message' => 'تم حذف المجموعة بنجاح ونقلها لسلة المحذوفات.',
            'data'    => null,
            'errors'  => null,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // POST /api/v1/walking/groups/{groupId}/restore
    // ─────────────────────────────────────────────────────────────────────────────

    public function restore(Request $request, int $groupId): JsonResponse
    {
        $personId = $this->resolvePersonOrFail($request);
        if ($personId instanceof JsonResponse) {
            return $personId;
        }

        $group = WalkingGroup::onlyTrashed()->findOrFail($groupId);

        if (! $group->isAdmin($personId)) {
            return $this->forbidden('فقط منشئ/مدير المجموعة يمكنه استرجاعها.');
        }

        $restored = $this->groupService->restoreGroup($groupId);

        return response()->json([
            'success' => true,
            'message' => 'تم استرجاع التحدي بنجاح وإعادته للتحديات.',
            'data'    => new WalkingGroupResource($restored->fresh(['memberships'])),
            'errors'  => null,
        ]);
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // POST /api/v1/walking/groups/{group}/join
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Join a PUBLIC group directly (no invite required).
     */
    public function join(Request $request, WalkingGroup $group): JsonResponse
    {
        $personId = $this->resolvePersonOrFail($request);
        if ($personId instanceof JsonResponse) {
            return $personId;
        }

        $member = $this->groupService->joinPublicGroup($group, $personId);

        if (! $member) {
            return response()->json([
                'success' => false,
                'message' => 'هذه المجموعة خاصة. يجب الحصول على دعوة للانضمام.',
                'data'    => null,
                'errors'  => null,
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم الانضمام للمجموعة بنجاح.',
            'data'    => new WalkingGroupMemberResource($member->load('person')),
            'errors'  => null,
        ]);
    }

    /**
     * Join a group using an invite code (POST /api/v1/walking/groups/join-code).
     */
    public function joinByCode(Request $request): JsonResponse
    {
        $personId = $this->resolvePersonOrFail($request);
        if ($personId instanceof JsonResponse) {
            return $personId;
        }

        $code = (string) $request->input('invite_code');
        if (empty(trim($code))) {
            return response()->json([
                'success' => false,
                'message' => 'رمز الدعوة مطلوب.',
                'data'    => null,
                'errors'  => ['invite_code' => ['رمز الدعوة مطلوب.']],
            ], 422);
        }

        $member = $this->groupService->joinByInviteCode($code, $personId);

        if (! $member) {
            return response()->json([
                'success' => false,
                'message' => 'رمز الدعوة غير صحيح أو المجموعة غير مفعلة.',
                'data'    => null,
                'errors'  => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم الانضمام للمجموعة بنجاح عبر رمز الدعوة.',
            'data'    => new WalkingGroupMemberResource($member->load('person')),
            'errors'  => null,
        ]);
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // POST /api/v1/walking/groups/{group}/members
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Add/Invite one or more family-tree persons directly to a group (admin only).
     */
    public function inviteMember(InviteGroupMemberRequest $request, WalkingGroup $group): JsonResponse
    {
        $personId = $this->resolvePersonOrFail($request);
        if ($personId instanceof JsonResponse) {
            return $personId;
        }

        if (! $group->isAdmin($personId)) {
            return $this->forbidden('فقط مدير المجموعة يمكنه إضافة أعضاء.');
        }

        $validated = $request->validated();
        $targetIds = [];
        if (! empty($validated['person_ids'])) {
            $targetIds = array_map('intval', $validated['person_ids']);
        } elseif (! empty($validated['person_id'])) {
            $targetIds = [(int) $validated['person_id']];
        }

        if (empty($targetIds)) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تحديد شخص واحد على الأقل لإضافته.',
                'data'    => null,
                'errors'  => null,
            ], 422);
        }

        $inviter = \App\Models\Person::find($personId);
        $addedCount = 0;
        foreach ($targetIds as $tid) {
            $this->groupService->addMember($group, $tid, shouldNotify: true, inviter: $inviter);
            $addedCount++;
        }

        return response()->json([
            'success' => true,
            'message' => $addedCount > 1 ? "تمت إضافة {$addedCount} أشخاص للمجموعة بنجاح." : 'تمت إضافة العضو للمجموعة بنجاح.',
            'data'    => new WalkingGroupResource($group->fresh(['memberships.person'])),
            'errors'  => null,
        ], 201);
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // POST /api/v1/walking/groups/{group}/leave
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Leave a walking group. If owner leaves, ownership automatically passes to the next member.
     */
    public function leave(Request $request, WalkingGroup $group): JsonResponse
    {
        $personId = $this->resolvePersonOrFail($request);
        if ($personId instanceof JsonResponse) {
            return $personId;
        }

        $result = $this->groupService->leaveGroup($group, $personId);

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'data'    => null,
                'errors'  => null,
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data'    => [
                'group_id'            => $group->id,
                'group_deleted'       => $result['group_deleted'],
                'new_owner_person_id' => $result['new_owner_person_id'],
            ],
            'errors'  => null,
        ]);
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // DELETE /api/v1/walking/groups/{group}/members/{personId}
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Remove a member from a group or leave.
     * Admins can remove anyone; a member can remove themselves (leave).
     */
    public function removeMember(Request $request, WalkingGroup $group, int $targetPersonId): JsonResponse
    {
        $personId = $this->resolvePersonOrFail($request);
        if ($personId instanceof JsonResponse) {
            return $personId;
        }

        // Can remove self (leave) or target if admin
        $isSelf  = $personId === $targetPersonId;
        $isAdmin = $group->isAdmin($personId);

        if (! $isSelf && ! $isAdmin) {
            return $this->forbidden('لا يمكنك إزالة أعضاء آخرين إلا إذا كنت مدير المجموعة.');
        }

        if ($isSelf) {
            $result = $this->groupService->leaveGroup($group, $targetPersonId);
            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data'    => [
                    'group_id'            => $group->id,
                    'group_deleted'       => $result['group_deleted'],
                    'new_owner_person_id' => $result['new_owner_person_id'],
                ],
                'errors'  => null,
            ], $result['success'] ? 200 : 400);
        }

        $remover = \App\Models\Person::find($personId);
        $reason = $request->input('reason');
        $removed = $this->groupService->excludeMember($group, $targetPersonId, $remover, $reason);

        return response()->json([
            'success' => $removed,
            'message' => $removed ? 'تم استبعاد العضو من المجموعة بنجاح وإشعاره.' : 'الشخص غير موجود في هذه المجموعة.',
            'data'    => null,
            'errors'  => null,
        ]);
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // GET /api/v1/walking/groups/{group}/leaderboard
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Group-scoped Top-10 leaderboard (restricted to accepted members only).
     * ?period=today|weekly|monthly|all_time
     */
    public function leaderboard(Request $request, WalkingGroup $group): JsonResponse
    {
        $personId = $this->resolvePersonId($request);

        // Enforce privacy for private groups
        if (! $group->is_public && (! $personId || ! $group->hasMember($personId))) {
            return $this->forbidden();
        }

        $scoringService = app(\App\Services\Walking\WalkingGroupScoringService::class);
        $period = $request->query('period', WalkingLeaderboardService::PERIOD_WEEKLY);

        // If requested metric/period is points, return the points-based leaderboard
        if ($period === 'points' || $request->query('metric') === 'points') {
            $pointsLeaderboard = $scoringService->getGroupPointsLeaderboard($group, $personId);
            return response()->json([
                'success' => true,
                'message' => 'تم استرجاع لوحة شرف نقاط المجموعة.',
                'data'    => [
                    'period'           => 'points',
                    'metric'           => 'points',
                    'group_id'         => $group->id,
                    'group_name'       => $group->name,
                    'standings'        => $pointsLeaderboard['standings'],
                    'total_points_sum' => $pointsLeaderboard['total_points_sum'],
                ],
                'errors'  => null,
            ]);
        }

        $memberIds = $group->memberships()
            ->where('status', 'accepted')
            ->pluck('person_id')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if ($group->created_by_person_id && ! in_array((int) $group->created_by_person_id, $memberIds, true)) {
            $memberIds[] = (int) $group->created_by_person_id;
        }

        $dateRangeOverride = null;
        if ($period === WalkingLeaderboardService::PERIOD_ALLTIME && $group->start_date) {
            $from = \Carbon\Carbon::parse($group->start_date);
            $to = $group->end_date ? \Carbon\Carbon::parse($group->end_date) : now();
            $dateRangeOverride = [$from, $to];
        }

        $genderFilter = $request->query('gender_filter', $request->query('gender'));
        $referenceDate = $this->resolveReferenceDate($request);

        $data = $this->leaderboardService->build(
            period: $period,
            viewerPersonId: $personId,
            restrictToPersonIds: $memberIds,
            dateRangeOverride: $dateRangeOverride,
            groupId: $group->id,
            page: null,
            perPage: 10,
            requestedGenderFilter: $genderFilter,
            referenceDate: $referenceDate
        );

        return response()->json([
            'success' => true,
            'message' => 'تم استرجاع لوحة شرف المجموعة.',
            'data'    => array_merge($data, [
                'group_id'   => $group->id,
                'group_name' => $group->name,
                'points_map' => $scoringService->getGroupTotalPointsMap($group->id),
            ]),
            'errors'  => null,
        ]);
    }

    // â”€â”€ Private helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /** @return int|JsonResponse */
    private function resolvePersonOrFail(Request $request): int|JsonResponse
    {
        $id = $this->resolvePersonId($request);
        if (! $id) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول ببصمة الوجه أو واتساب.',
                'data'    => null,
                'errors'  => null,
            ], 401);
        }
        return $id;
    }

    private function resolvePersonId(Request $request): ?int
    {
        $identity = $this->identityResolver->resolve($request);
        return $identity ? (int) $identity['person_id'] : null;
    }

    private function forbidden(string $message = 'ليس لديك صلاحية الوصول.'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data'    => null,
            'errors'  => null,
        ], 403);
    }

    // ──────────────────────────────────────────────────────────────────────
    // GET /api/v1/walking/groups/{group}/member-settings
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Get member's privacy and notification settings in a walking group.
     */
    public function getMemberSettings(Request $request, WalkingGroup $group): JsonResponse
    {
        try {
            $personId = $this->resolvePersonOrFail($request);
            if ($personId instanceof JsonResponse) {
                return $personId;
            }

            $membership = WalkingGroupMember::where('walking_group_id', $group->id)
                ->where('person_id', $personId)
                ->first();

            if (! $membership) {
                if ($group->created_by_person_id == $personId) {
                    $membership = WalkingGroupMember::firstOrCreate(
                        [
                            'walking_group_id' => $group->id,
                            'person_id'        => $personId,
                        ],
                        [
                            'role'                  => 'admin',
                            'status'                => 'accepted',
                            'joined_at'             => $group->created_at ?? now(),
                            'is_anonymous'          => false,
                            'share_details'         => true,
                            'notifications_enabled' => true,
                        ]
                    );
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'أنت لست عضواً في هذا التحدي.',
                        'data'    => null,
                        'errors'  => null,
                    ], 404);
                }
            }

            $isInherited = is_null($membership->is_anonymous);
            $person = $membership->relationLoaded('person') ? $membership->person : \App\Models\Person::find($personId);
            $effectiveAnonymous = ! $isInherited
                ? (bool) $membership->is_anonymous
                : in_array($person?->walking_leaderboard_mode, ['hidden', 'anonymous'], true);

            return response()->json([
                'success' => true,
                'message' => 'تم استرجاع إعدادات العضو بنجاح.',
                'data'    => [
                    'is_anonymous'          => $effectiveAnonymous,
                    'is_inherited'          => $isInherited,
                    'share_details'         => (bool) ($membership->share_details ?? true),
                    'notifications_enabled' => (bool) ($membership->notifications_enabled ?? true),
                ],
                'errors'  => null,
            ]);
        } catch (\Throwable $e) {
            \Log::error('[WalkingGroup] getMemberSettings error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء استرجاع الإعدادات: ' . $e->getMessage(),
                'data'    => null,
                'errors'  => $e->getMessage(),
            ], 500);
        }
    }

    // ──────────────────────────────────────────────────────────────────────
    // PATCH /api/v1/walking/groups/{group}/member-settings
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Update member's privacy and notification settings in a walking group.
     */
    public function updateMemberSettings(Request $request, WalkingGroup $group): JsonResponse
    {
        try {
            $personId = $this->resolvePersonOrFail($request);
            if ($personId instanceof JsonResponse) {
                return $personId;
            }

            $validated = $request->validate([
                'is_anonymous'          => 'sometimes|nullable|boolean',
                'share_details'         => 'sometimes|boolean',
                'notifications_enabled' => 'sometimes|boolean',
                'apply_to_all'          => 'sometimes|boolean',
            ]);

            $applyToAll = ! empty($validated['apply_to_all']);

            $updated = $this->groupService->updateMemberSettings(
                $group,
                $personId,
                $validated,
                $applyToAll
            );

            if (! $updated) {
                return response()->json([
                    'success' => false,
                    'message' => 'أنت لست عضواً في هذا التحدي أو تعذر تحديث الإعدادات.',
                    'data'    => null,
                    'errors'  => null,
                ], 404);
            }

            $isInherited = is_null($updated->is_anonymous);
            $person = $updated->relationLoaded('person') ? $updated->person : \App\Models\Person::find($personId);
            $effectiveAnonymous = ! $isInherited
                ? (bool) $updated->is_anonymous
                : in_array($person?->walking_leaderboard_mode, ['hidden', 'anonymous'], true);

            return response()->json([
                'success' => true,
                'message' => 'تم حفظ إعدادات الخصوصية بنجاح.',
                'data'    => [
                    'is_anonymous'          => $effectiveAnonymous,
                    'is_inherited'          => $isInherited,
                    'share_details'         => (bool) $updated->share_details,
                    'notifications_enabled' => (bool) $updated->notifications_enabled,
                ],
                'errors'  => null,
            ]);
        } catch (\Throwable $e) {
            \Log::error('[WalkingGroup] updateMemberSettings error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في الخادم أثناء حفظ الإعدادات: ' . $e->getMessage(),
                'data'    => null,
                'errors'  => $e->getMessage(),
            ], 500);
        }
    }

    // ──────────────────────────────────────────────────────────────────────
    // POST /api/v1/walking/groups/{group}/reactivate
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Reactivate an archived or completed walking group.
     */
    public function reactivate(Request $request, WalkingGroup $group): JsonResponse
    {
        $personId = $this->resolvePersonOrFail($request);
        if ($personId instanceof JsonResponse) {
            return $personId;
        }

        if (! $group->isAdmin($personId)) {
            return $this->forbidden('فقط مدير التحدي يمكنه إعادة تنشيطه.');
        }

        $validated = $request->validate([
            'end_date' => 'nullable|date',
        ]);

        $reactivator = Person::find($personId);
        $updatedGroup = $this->groupService->reactivate(
            $group,
            $validated['end_date'] ?? null,
            $reactivator
        );

        $request->attributes->set('viewer_person_id', $personId);

        return response()->json([
            'success' => true,
            'message' => 'تمت إعادة تنشيط التحدي بنجاح.',
            'data'    => new WalkingGroupResource($updatedGroup->fresh(['memberships.person'])),
            'errors'  => null,
        ]);
    }

    /**
     * مرجع "اليوم" الموحد بتوقيت السعودية.
     */
    private function resolveReferenceDate(Request $request): \Carbon\Carbon
    {
        $ksaToday = \Carbon\Carbon::today('Asia/Riyadh');

        $clientDate = $request->query('date');
        if (is_string($clientDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $clientDate)) {
            try {
                $parsed = \Carbon\Carbon::createFromFormat('Y-m-d', $clientDate, 'Asia/Riyadh')->startOfDay();
                if ($parsed->between($ksaToday->copy()->subDay(), $ksaToday->copy())) {
                    return $parsed;
                }
            } catch (\Throwable) {
                // تجاهل تاريخ العميل غير الصالح — نرجع اليوم السعودي
            }
        }

        return $ksaToday;
    }
}

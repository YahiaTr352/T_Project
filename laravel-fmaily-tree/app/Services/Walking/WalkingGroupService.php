<?php

namespace App\Services\Walking;

use App\Jobs\DispatchBulkGroupInvitationsJob;
use App\Jobs\SendChallengeGoalAchievedJob;
use App\Jobs\SendChallengeCompletedBroadcastJob;
use App\Models\Person;
use App\Models\WalkingGroup;
use App\Models\WalkingGroupMember;
use App\Models\WalkingRecord;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates group creation, membership management,
 * notifications, and smart ownership transfer for walking challenges.
 */
class WalkingGroupService
{
    public function __construct(
        protected ?WalkingGroupNotificationService $notificationService = null
    ) {
        $this->notificationService = $notificationService ?? app(WalkingGroupNotificationService::class);
    }

    /**
     * Create a new walking group / challenge.
     *
     * @param  array{name: string, description?: string, target_steps?: int, start_date?: string, end_date?: string, is_public?: bool, cover_image?: string, status?: string}  $data
     */
    public function create(array $data, ?int $creatorPersonId = null, ?int $creatorUserId = null): WalkingGroup
    {
        $group = WalkingGroup::create([
            'name'                 => $data['name'],
            'description'          => $data['description'] ?? null,
            'target_steps'         => $data['target_steps'] ?? null,
            'start_date'           => $data['start_date'] ?? null,
            'end_date'             => $data['end_date'] ?? null,
            'is_public'            => (bool) ($data['is_public'] ?? false),
            'cover_image'          => $data['cover_image'] ?? null,
            'status'               => $data['status'] ?? 'active',
            'created_by_person_id' => $creatorPersonId,
            'created_by_user_id'   => $creatorUserId,
        ]);

        // If creator is a person, they are automatically an admin member (without notify self)
        if ($creatorPersonId) {
            $this->addMember($group, $creatorPersonId, role: 'admin', shouldNotify: false);
        }

        return $group->fresh(['memberships']);
    }

    /**
     * Bulk add or invite persons to a walking group using fast SQL upsert
     * and asynchronous chunked notifications.
     *
     * @param array<int> $personIds
     * @return int Number of people processed
     */
    public function bulkAddMembers(
        WalkingGroup $group,
        array $personIds,
        string $role = 'member',
        string $status = 'accepted',
        bool $shouldNotify = true,
        ?int $inviterPersonId = null
    ): int {
        $cleanIds = array_values(array_unique(array_filter(array_map('intval', $personIds))));
        if (empty($cleanIds)) {
            return 0;
        }

        $now = now();
        $records = [];
        foreach ($cleanIds as $pid) {
            $records[] = [
                'walking_group_id' => $group->id,
                'person_id'        => $pid,
                'role'             => $role,
                'status'           => $status,
                'joined_at'        => $status === 'accepted' ? $now : null,
                'created_at'       => $now,
                'updated_at'       => $now,
            ];
        }

        // Upsert in chunks of 500 records to prevent SQL placeholder limits
        foreach (array_chunk($records, 500) as $chunk) {
            WalkingGroupMember::upsert(
                $chunk,
                ['walking_group_id', 'person_id'],
                ['role', 'status', 'joined_at', 'updated_at']
            );
        }

        if ($shouldNotify) {
            DispatchBulkGroupInvitationsJob::dispatch(
                $group->id,
                $cleanIds,
                $inviterPersonId,
                $status
            );
        }

        return count($cleanIds);
    }

    /**
     * Add a person to a group as an accepted member.
     * If the person already has a pending/invited record, promote it to accepted.
     */
    public function addMember(
        WalkingGroup $group,
        int $personId,
        string $role = 'member',
        bool $shouldNotify = true,
        ?Person $inviter = null
    ): WalkingGroupMember {
        $isGroupPrivate = ! (bool) $group->is_public;
        $member = WalkingGroupMember::updateOrCreate(
            [
                'walking_group_id' => $group->id,
                'person_id'        => $personId,
            ],
            array_filter([
                'role'         => $role,
                'status'       => 'accepted',
                'joined_at'    => now(),
                'is_anonymous' => $isGroupPrivate ? false : null,
            ], fn($v) => ! is_null($v))
        );

        if ($isGroupPrivate && $member->is_anonymous !== false) {
            $member->update(['is_anonymous' => false]);
        }

        if ($shouldNotify) {
            $person = Person::find($personId);
            if ($person) {
                $this->notificationService->notifyMemberAdded($group, $person, $inviter);
            }
        }

        return $member;
    }

    /**
     * Invite a person (status = invited, not yet accepted).
     */
    public function inviteMember(
        WalkingGroup $group,
        int $personId,
        bool $shouldNotify = true,
        ?Person $inviter = null
    ): WalkingGroupMember {
        $member = WalkingGroupMember::firstOrCreate(
            [
                'walking_group_id' => $group->id,
                'person_id'        => $personId,
            ],
            [
                'role'   => 'member',
                'status' => 'invited',
            ]
        );

        if ($shouldNotify) {
            $person = Person::find($personId);
            if ($person) {
                $this->notificationService->notifyMemberAdded($group, $person, $inviter);
            }
        }

        return $member;
    }

    /**
     * Let a person join a PUBLIC group directly (no invite needed).
     * Returns null if the group is private.
     */
    public function joinPublicGroup(WalkingGroup $group, int $personId): ?WalkingGroupMember
    {
        if (! $group->is_public) {
            return null;
        }

        return $this->addMember($group, $personId, shouldNotify: false);
    }

    /**
     * Accept a pending invitation.
     */
    public function acceptInvitation(WalkingGroup $group, int $personId): bool
    {
        return (bool) WalkingGroupMember::where('walking_group_id', $group->id)
            ->where('person_id', $personId)
            ->whereIn('status', ['invited', 'pending'])
            ->update([
                'status'    => 'accepted',
                'joined_at' => now(),
            ]);
    }

    /**
     * Remove a person from a group.
     *
     * @return bool true if removed, false if person was not in the group
     */
    public function removeMember(WalkingGroup $group, int $personId): bool
    {
        return (bool) WalkingGroupMember::where('walking_group_id', $group->id)
            ->where('person_id', $personId)
            ->delete();
    }

    /**
     * Exclude a member from a group (initiated by admin or group leader) and send notifications.
     *
     * @return bool true if removed, false if person was not in the group
     */
    public function excludeMember(
        WalkingGroup $group,
        int $personId,
        ?Person $remover = null,
        ?string $reason = null
    ): bool {
        $person = Person::find($personId);
        $removed = $this->removeMember($group, $personId);

        if ($removed && $person) {
            $this->notificationService->notifyMemberRemoved($group, $person, $remover, $reason);
        }

        return $removed;
    }

    /**
     * Leave group with smart ownership transfer if the leaving member is the owner/creator.
     *
     * @return array{success: bool, group_deleted: bool, new_owner_person_id: ?int, message: string}
     */
    public function leaveGroup(WalkingGroup $group, int $personId): array
    {
        $membership = WalkingGroupMember::where('walking_group_id', $group->id)
            ->where('person_id', $personId)
            ->first();

        if (! $membership) {
            return [
                'success'             => false,
                'group_deleted'       => false,
                'new_owner_person_id' => null,
                'message'             => 'الشخص غير مسجل كعضو في هذه المجموعة.',
            ];
        }

        $isOwner = ((int) $group->created_by_person_id === $personId);

        if ($isOwner) {
            // Find a successor among remaining accepted members
            $successor = WalkingGroupMember::where('walking_group_id', $group->id)
                ->where('person_id', '!=', $personId)
                ->where('status', 'accepted')
                ->orderByRaw("CASE WHEN role = 'admin' THEN 0 ELSE 1 END")
                ->orderBy('joined_at', 'asc')
                ->orderBy('id', 'asc')
                ->first();

            if ($successor) {
                $oldOwner = Person::find($personId);
                $newOwner = Person::find($successor->person_id);

                // Transfer group ownership
                $group->update(['created_by_person_id' => $successor->person_id]);
                $successor->update(['role' => 'admin']);

                // Remove the leaving member
                $this->removeMember($group, $personId);

                // Notify new owner
                if ($newOwner) {
                    $this->notificationService->notifyOwnerChanged($group, $newOwner, $oldOwner);
                }

                return [
                    'success'             => true,
                    'group_deleted'       => false,
                    'new_owner_person_id' => $successor->person_id,
                    'message'             => 'تمت مغادرة المجموعة وتعيين مدير جديد بنجاح.',
                ];
            } else {
                // No remaining members — delete group
                $this->removeMember($group, $personId);
                $group->delete();

                return [
                    'success'             => true,
                    'group_deleted'       => true,
                    'new_owner_person_id' => null,
                    'message'             => 'تمت مغادرة المجموعة وحذفها لعدم وجود أعضاء آخرين.',
                ];
            }
        }

        // Regular member leaving
        $this->removeMember($group, $personId);

        return [
            'success'             => true,
            'group_deleted'       => false,
            'new_owner_person_id' => null,
            'message'             => 'تمت مغادرة المجموعة بنجاح.',
        ];
    }

    /**
     * Join a group using an invite code (works for both public and private groups).
     */
    public function joinByInviteCode(string $code, int $personId): ?WalkingGroupMember
    {
        $group = WalkingGroup::where('invite_code', strtoupper(trim($code)))->first();
        if (! $group || $group->status === 'archived') {
            return null;
        }

        return $this->addMember($group, $personId, shouldNotify: false);
    }

    /**
     * Toggle member role between admin and member.
     */
    public function toggleMemberRole(WalkingGroup $group, int $personId): ?WalkingGroupMember
    {
        $membership = WalkingGroupMember::where('walking_group_id', $group->id)
            ->where('person_id', $personId)
            ->first();

        if (! $membership) {
            return null;
        }

        $membership->role = ($membership->role === 'admin') ? 'member' : 'admin';
        $membership->save();

        return $membership;
    }

    /**
     * Update mutable group fields.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(WalkingGroup $group, array $data): WalkingGroup
    {
        $fields = ['name', 'description', 'target_steps', 'start_date', 'end_date', 'cover_image', 'status'];
        $fillData = [];

        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $fillData[$field] = $data[$field];
            }
        }

        if (array_key_exists('is_public', $data)) {
            $fillData['is_public'] = (bool) $data['is_public'];
        }

        if (! empty($fillData)) {
            $group->fill($fillData)->save();
        }

        return $group->fresh();
    }

    /**
     * Update member's privacy and notification settings in a walking group.
     * Optionally propagates settings to all active memberships of this person.
     */
        public function updateMemberSettings(
        WalkingGroup $group,
        int $personId,
        array $settings,
        bool $applyToAll = false
    ): ?WalkingGroupMember {
        $this->ensurePrivacyColumnsExist();

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
                        'is_anonymous'          => null,
                        'share_details'         => true,
                        'notifications_enabled' => true,
                    ]
                );
            } else {
                return null;
            }
        }

        $isGroupPrivate = ! (bool) $group->is_public;

        $fields = [];
        if (array_key_exists('is_anonymous', $settings)) {
            // Private challenges strictly forbid anonymity: always forced to false
            $fields['is_anonymous'] = $isGroupPrivate ? false : (is_null($settings['is_anonymous']) ? null : (bool) $settings['is_anonymous']);
        }
        if (array_key_exists('share_details', $settings)) {
            $fields['share_details'] = (bool) $settings['share_details'];
        }
        if (array_key_exists('notifications_enabled', $settings)) {
            $fields['notifications_enabled'] = (bool) $settings['notifications_enabled'];
        }

        if (! empty($fields)) {
            $membership->update($fields);

            if ($applyToAll) {
                // Apply general notification and detail sharing across all memberships
                $generalFields = $fields;
                unset($generalFields['is_anonymous']);
                if (! empty($generalFields)) {
                    WalkingGroupMember::where('person_id', $personId)->update($generalFields);
                }

                if (array_key_exists('is_anonymous', $fields)) {
                    if ($fields['is_anonymous'] === false) {
                        WalkingGroupMember::where('person_id', $personId)->update(['is_anonymous' => false]);
                    } else {
                        // Only enable anonymous mode for PUBLIC groups
                        WalkingGroupMember::where('person_id', $personId)
                            ->whereHas('group', fn($q) => $q->where('is_public', true))
                            ->update(['is_anonymous' => true]);

                        // Enforce false for any private groups
                        WalkingGroupMember::where('person_id', $personId)
                            ->whereHas('group', fn($q) => $q->where('is_public', false))
                            ->update(['is_anonymous' => false]);
                    }
                }
            } elseif (isset($fields['is_anonymous']) && $fields['is_anonymous'] === false) {
                // When unhiding in a challenge without apply_to_all,
                // also clean up any soft-deleted group memberships so they never leave lingering anonymous flags
                WalkingGroupMember::where('person_id', $personId)
                    ->whereHas('group', function ($q) {
                        $q->whereNotNull('deleted_at');
                    })
                    ->update(['is_anonymous' => false]);
            }
        }

        return $membership->fresh();
    }

    /**
     * Ensure privacy columns exist in walking_group_members table (fail-safe for production if migration was not run).
     */
    public function ensurePrivacyColumnsExist(): void
    {
        try {
            if (! Schema::hasTable('walking_group_members')) {
                return;
            }

            $missing = [];
            if (! Schema::hasColumn('walking_group_members', 'is_anonymous')) {
                $missing[] = 'is_anonymous';
            }
            if (! Schema::hasColumn('walking_group_members', 'share_details')) {
                $missing[] = 'share_details';
            }
            if (! Schema::hasColumn('walking_group_members', 'notifications_enabled')) {
                $missing[] = 'notifications_enabled';
            }

            if (! empty($missing)) {
                Schema::table('walking_group_members', function (Blueprint $table) use ($missing) {
                    if (in_array('is_anonymous', $missing, true)) {
                        $table->boolean('is_anonymous')->nullable()->default(null)->after('status')->index();
                    }
                    if (in_array('share_details', $missing, true)) {
                        $table->boolean('share_details')->default(true)->after('is_anonymous');
                    }
                    if (in_array('notifications_enabled', $missing, true)) {
                        $table->boolean('notifications_enabled')->default(true)->after('share_details');
                    }
                });
            }
        } catch (\Throwable $e) {
            Log::warning('[WalkingGroup] Auto-schema addition skipped: ' . $e->getMessage());
        }
    }


    /**
     * Reactivate an archived or completed walking group.
     */
    public function reactivate(
        WalkingGroup $group,
        ?string $newEndDate = null,
        ?Person $reactivator = null
    ): WalkingGroup {
        $updateData = [
            'status'          => 'active',
            'archived_at'     => null,
            'archived_reason' => null,
        ];

        $today = now()->toDateString();

        if ($newEndDate) {
            $updateData['end_date'] = $newEndDate;
            if ($group->start_date && $group->start_date->lt(now()->startOfDay())) {
                $updateData['start_date'] = $today;
            }
        } elseif ($group->end_date && $group->end_date->lt(now()->startOfDay())) {
            $updateData['start_date'] = $today;
            $updateData['end_date'] = now()->addDays(14)->toDateString();
        }

        $group->update($updateData);

        // Notify members that challenge is reactivated
        $this->notificationService->notifyGroupReactivated($group, $reactivator);

        return $group->fresh();
    }

    /**
     * Archive a group (manual or automatic).
     */
    public function archiveGroup(WalkingGroup $group, string $reason = 'manual_admin'): WalkingGroup
    {
        $group->update([
            'status'          => 'archived',
            'archived_at'     => now(),
            'archived_reason' => $reason,
        ]);

        return $group->fresh();
    }

    /**
     * Restore a soft-deleted walking group / challenge.
     */
    public function restoreGroup(int $groupId): ?WalkingGroup
    {
        $group = WalkingGroup::onlyTrashed()->find($groupId);
        if ($group) {
            $group->restore();
            return $group->fresh();
        }

        return null;
    }

    /**
     * Permanently force-delete a walking group / challenge.
     */
    public function forceDeleteGroup(int $groupId): bool
    {
        $group = WalkingGroup::withTrashed()->find($groupId);
        if ($group) {
            return (bool) $group->forceDelete();
        }

        return false;
    }

    /**
     * Check if a person has achieved the target steps in any of their active challenges.
     * If achieved and not yet recorded as completed, marks completed_at and dispatches congratulations.
     */
    public function checkAndMarkMemberChallengeCompletion(int $personId): void
    {
        try {
            $activeMemberships = WalkingGroupMember::where('person_id', $personId)
                ->where('status', 'accepted')
                ->whereNull('completed_at')
                ->whereHas('group', function ($q) {
                    $q->where('status', 'active')
                      ->whereNotNull('target_steps')
                      ->where('target_steps', '>', 0);
                })
                ->with('group')
                ->get();

            foreach ($activeMemberships as $membership) {
                $group = $membership->group;
                if (!$group || empty($group->target_steps)) {
                    continue;
                }

                $dateFrom = $group->start_date ? $group->start_date->format('Y-m-d') : '2020-01-01';
                $dateTo   = $group->end_date ? $group->end_date->format('Y-m-d') : now()->toDateString();

                $totalMemberSteps = (int) WalkingRecord::where('person_id', $personId)
                    ->whereBetween('date', [$dateFrom, $dateTo])
                    ->sum('steps');

                if ($totalMemberSteps >= (int) $group->target_steps) {
                    // Atomic conditional update on DB row to prevent parallel sync requests from dispatching twice
                    $affected = WalkingGroupMember::where('id', $membership->id)
                        ->whereNull('completed_at')
                        ->update(['completed_at' => now()]);

                    if ($affected > 0) {
                        $membership->completed_at = now();
                        $durationText = $membership->completion_duration_text ?? 'خلال فترة قياسية';

                        SendChallengeGoalAchievedJob::dispatch(
                            $group->id,
                            $personId,
                            $durationText
                        );
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::channel('single')->error('[WalkingGroupService] checkAndMarkMemberChallengeCompletion error: ' . $e->getMessage(), [
                'person_id' => $personId,
            ]);
        }
    }

    /**
     * Check and complete challenges that reached their end_date,
     * triggering group-wide congratulatory broadcast to all members.
     */
    public function processCompletedChallenges(): int
    {
        try {
            $today = now()->startOfDay();
            $expiredGroups = WalkingGroup::where('status', 'active')
                ->whereNotNull('end_date')
                ->whereDate('end_date', '<', $today)
                ->whereNull('completion_notified_at')
                ->get();

            $count = 0;
            foreach ($expiredGroups as $group) {
                $group->update([
                    'status'                 => 'completed',
                    'completion_notified_at' => now(),
                ]);

                SendChallengeCompletedBroadcastJob::dispatch($group->id);
                $count++;
            }

            return $count;
        } catch (\Throwable $e) {
            Log::channel('single')->error('[WalkingGroupService] processCompletedChallenges error: ' . $e->getMessage());
            return 0;
        }
    }
}

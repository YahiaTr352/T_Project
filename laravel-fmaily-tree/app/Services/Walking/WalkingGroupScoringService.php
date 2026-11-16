<?php

declare(strict_types=1);

namespace App\Services\Walking;

use App\Models\Person;
use App\Models\WalkingGroup;
use App\Models\WalkingGroupDailyScore;
use App\Models\WalkingRecord;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WalkingGroupScoringService
{
    public const KSA_TIMEZONE = 'Asia/Riyadh';

    /**
     * Backfill scores for groups over a date range or from creation date.
     *
     * @param array{
     *     days?: int|null,
     *     from?: string|null,
     *     to?: string|null,
     *     all?: bool,
     *     group_id?: int|null
     * } $options
     * @return array{
     *     groups_count: int,
     *     total_days_processed: int,
     *     total_records: int,
     *     details: array<int, array{group_id: int, group_name: string, days_count: int, from: string, to: string, records: int}>
     * }
     */
    public function backfillScores(array $options = []): array
    {
        $tz = self::KSA_TIMEZONE;
        $yesterday = Carbon::yesterday($tz)->startOfDay();

        $query = WalkingGroup::query()
            ->whereNull('deleted_at')
            ->where('status', '!=', 'archived');

        if (! empty($options['group_id'])) {
            $query->where('id', (int) $options['group_id']);
        }

        $groups = $query->with(['memberships' => function ($m) {
            $m->where('status', 'accepted');
        }])->get();

        $totalDaysProcessed = 0;
        $totalRecords = 0;
        $details = [];

        foreach ($groups as $group) {
            // Determine start date for this group
            if (! empty($options['all'])) {
                // From the earliest of start_date or created_at
                $groupCreated = $group->created_at ? Carbon::parse($group->created_at, $tz)->startOfDay() : $yesterday;
                $groupStart = $group->start_date ? Carbon::parse($group->start_date, $tz)->startOfDay() : $groupCreated;
                $startDate = $groupStart->lt($groupCreated) ? $groupStart : $groupCreated;
            } elseif (! empty($options['from'])) {
                $startDate = Carbon::parse($options['from'], $tz)->startOfDay();
            } elseif (! empty($options['days'])) {
                $days = max(1, (int) $options['days']);
                $startDate = (clone $yesterday)->subDays($days - 1)->startOfDay();
            } else {
                $startDate = clone $yesterday;
            }

            // Determine end date
            if (! empty($options['to'])) {
                $endDate = Carbon::parse($options['to'], $tz)->startOfDay();
            } else {
                $endDate = clone $yesterday;
            }

            // Group cannot be scored after its end_date if specified
            if ($group->end_date) {
                $groupEnd = Carbon::parse($group->end_date, $tz)->startOfDay();
                if ($groupEnd->lt($endDate)) {
                    $endDate = $groupEnd;
                }
            }

            // Ensure start date is not after end date
            if ($startDate->gt($endDate)) {
                continue;
            }

            $groupRecords = 0;
            $groupDays = 0;
            $currentDay = clone $startDate;

            while ($currentDay->lte($endDate)) {
                $count = $this->processGroupDailyScore($group, $currentDay);
                $groupRecords += $count;
                $groupDays++;
                $currentDay->addDay();
            }

            $totalDaysProcessed += $groupDays;
            $totalRecords += $groupRecords;

            $details[] = [
                'group_id'   => $group->id,
                'group_name' => $group->name,
                'days_count' => $groupDays,
                'from'       => $startDate->toDateString(),
                'to'         => $endDate->toDateString(),
                'records'    => $groupRecords,
            ];
        }

        return [
            'groups_count'         => count($details),
            'total_days_processed' => $totalDaysProcessed,
            'total_records'        => $totalRecords,
            'details'              => $details,
        ];
    }

    /**
     * Process daily scores for all active walking groups for the target date.
     *
     * @param Carbon $targetDate
     * @return array{groups_processed: int, records_recorded: int}
     */
    public function processDailyScoresForDate(Carbon $targetDate): array
    {
        $dateStr = $targetDate->toDateString();

        // Active groups whose schedule covers targetDate (or open-ended)
        $groups = WalkingGroup::query()
            ->whereNull('deleted_at')
            ->where('status', '!=', 'archived')
            ->where(function ($q) use ($dateStr) {
                $q->whereNull('start_date')
                  ->orWhereDate('start_date', '<=', $dateStr);
            })
            ->where(function ($q) use ($dateStr) {
                $q->whereNull('end_date')
                  ->orWhereDate('end_date', '>=', $dateStr);
            })
            ->with(['memberships' => function ($m) {
                $m->where('status', 'accepted');
            }])
            ->get();

        $groupsProcessed = 0;
        $recordsRecorded = 0;

        foreach ($groups as $group) {
            $recordedCount = $this->processGroupDailyScore($group, $targetDate);
            $groupsProcessed++;
            $recordsRecorded += $recordedCount;
        }

        return [
            'groups_processed' => $groupsProcessed,
            'records_recorded' => $recordsRecorded,
        ];
    }

    /**
     * Process and record daily scores for a specific group on a specific date.
     */
    public function processGroupDailyScore(WalkingGroup $group, Carbon $targetDate): int
    {
        $dateStr = $targetDate->toDateString();

        // Collect all accepted member IDs + creator
        $memberIds = $group->memberships
            ->where('status', 'accepted')
            ->pluck('person_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($group->created_by_person_id && ! in_array((int) $group->created_by_person_id, $memberIds, true)) {
            $memberIds[] = (int) $group->created_by_person_id;
        }

        $totalMembersCount = count($memberIds);
        if ($totalMembersCount === 0) {
            return 0;
        }

        // Fetch steps walked on this specific day for all group members
        $stepsMap = WalkingRecord::query()
            ->select('person_id', DB::raw('SUM(steps) as total_steps'))
            ->whereIn('person_id', $memberIds)
            ->whereDate('date', $dateStr)
            ->groupBy('person_id')
            ->pluck('total_steps', 'person_id')
            ->map(fn ($val) => (int) $val)
            ->all();

        // Build list of members with their steps
        $memberList = [];
        foreach ($memberIds as $personId) {
            $memberList[] = [
                'person_id' => $personId,
                'steps'     => (int) ($stepsMap[$personId] ?? 0),
            ];
        }

        // Sort members descending by steps, tie-breaker by person_id
        usort($memberList, function ($a, $b) {
            if ($b['steps'] !== $a['steps']) {
                return $b['steps'] <=> $a['steps'];
            }
            return $a['person_id'] <=> $b['person_id'];
        });

        // Calculate Dense Rank and Points
        // Points rule: Rank 1 gets N points, Rank 2 gets N - 1 points...
        // Tied members receive the exact same rank and points.
        // Members with 0 steps get 0 points and rank 0.
        $rank = 0;
        $prevSteps = null;
        $recordsCount = 0;

        DB::transaction(function () use ($group, $memberList, $totalMembersCount, $dateStr, &$recordsCount, &$rank, &$prevSteps) {
            foreach ($memberList as $item) {
                $personId = $item['person_id'];
                $steps = $item['steps'];

                if ($steps > 0) {
                    if ($steps !== $prevSteps) {
                        $rank++;
                        $prevSteps = $steps;
                    }
                    $points = max(1, $totalMembersCount - $rank + 1);
                    $dailyRank = $rank;
                } else {
                    $dailyRank = 0;
                    $points = 0;
                }

                WalkingGroupDailyScore::updateOrCreate(
                    [
                        'walking_group_id' => $group->id,
                        'person_id'        => $personId,
                        'date'             => $dateStr,
                    ],
                    [
                        'daily_steps'         => $steps,
                        'daily_rank'          => $dailyRank,
                        'points_earned'       => $points,
                        'group_members_count' => $totalMembersCount,
                    ]
                );

                $recordsCount++;
            }
        });

        return $recordsCount;
    }

    /**
     * Get cumulative points map for all members in a group.
     *
     * @return array<int, int> [person_id => total_points]
     */
    public function getGroupTotalPointsMap(int $groupId): array
    {
        return WalkingGroupDailyScore::query()
            ->where('walking_group_id', $groupId)
            ->select('person_id', DB::raw('SUM(points_earned) as total_points'))
            ->groupBy('person_id')
            ->pluck('total_points', 'person_id')
            ->map(fn ($pts) => (int) $pts)
            ->all();
    }

    /**
     * Build points-based leaderboard for a walking group.
     *
     * @return array{standings: array<int, array<string, mixed>>, total_points_sum: int}
     */
    public function getGroupPointsLeaderboard(WalkingGroup $group, ?int $viewerPersonId = null): array
    {
        $memberships = $group->memberships()
            ->where('status', 'accepted')
            ->with('person')
            ->get();

        $pointsMap = $this->getGroupTotalPointsMap($group->id);

        $memberRows = [];
        $totalPointsSum = 0;

        $isGroupPrivate = ! (bool) $group->is_public;

        foreach ($memberships as $membership) {
            $person = $membership->person;
            $pid = (int) $membership->person_id;
            $pts = (int) ($pointsMap[$pid] ?? 0);
            $totalPointsSum += $pts;

            $isSelf = $viewerPersonId && ($pid === (int) $viewerPersonId);
            $effectiveAnon = $isGroupPrivate ? false : (bool) $membership->effective_is_anonymous;
            $isRowAnon = (bool) ($effectiveAnon && ! $isSelf && ! $group->isAdmin($viewerPersonId));

            $memberRows[] = [
                'person_id'                => $isRowAnon ? null : $pid,
                'membership_id'            => $membership->id,
                'total_points'             => $pts,
                'total_steps'              => (int) ($membership->total_steps_in_challenge ?? 0),
                'role'                     => $membership->role,
                'is_anonymous'             => (bool) $effectiveAnon,
                'is_self'                  => (bool) $isSelf,
                'completed_at'             => $membership->completed_at?->toDateTimeString(),
                'is_goal_reached'          => (bool) ($membership->completed_at !== null),
                'completion_duration_text' => $membership->completion_duration_text,
                'person'                   => $isRowAnon ? [
                    'id'     => 0,
                    'name'   => 'متسابق متخفي',
                    'avatar' => Person::anonymousAvatarFor($person?->gender),
                    'gender' => $person?->gender ?? 'male',
                ] : [
                    'id'     => $person?->id ?? $pid,
                    'name'   => $person?->full_name ?? 'عضو في التحدي',
                    'avatar' => $person?->avatar,
                    'gender' => $person?->gender ?? 'male',
                ],
            ];
        }

        // Sort descending by total_points, tie-break by total_steps then membership_id
        usort($memberRows, function ($a, $b) {
            if ($b['total_points'] !== $a['total_points']) {
                return $b['total_points'] <=> $a['total_points'];
            }
            if ($b['total_steps'] !== $a['total_steps']) {
                return $b['total_steps'] <=> $a['total_steps'];
            }
            return $a['membership_id'] <=> $b['membership_id'];
        });

        // Dense Ranking assignment
        $rank = 0;
        $prevPoints = null;
        $standings = [];

        foreach ($memberRows as $row) {
            $pts = $row['total_points'];
            if ($pts > 0) {
                if ($pts !== $prevPoints) {
                    $rank++;
                    $prevPoints = $pts;
                }
                $assignedRank = $rank;
            } else {
                $assignedRank = 0;
            }

            $row['rank'] = $assignedRank;
            $standings[] = $row;
        }

        return [
            'standings'        => $standings,
            'total_points_sum' => $totalPointsSum,
        ];
    }
}

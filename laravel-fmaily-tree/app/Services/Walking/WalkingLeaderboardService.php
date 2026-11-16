<?php

namespace App\Services\Walking;

use App\Models\Person;
use App\Models\WalkingClub;
use App\Models\WalkingGroup;
use App\Models\WalkingGroupMember;
use App\Models\WalkingRecord;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds platform-wide and group-scoped leaderboards.
 *
 * All leaderboards are strictly ranked by total_steps.
 * Supports consecutive 10,000-step streaks (streak_10k) per participant.
 * Supports Incognito / Anonymous mode for members who choose privacy.
 */
class WalkingLeaderboardService
{
    public const TOP_N = 10;

    public const PERIOD_TODAY   = 'today';
    public const PERIOD_WEEKLY  = 'weekly';
    public const PERIOD_MONTHLY = 'monthly';
    public const PERIOD_ALLTIME = 'all_time';

    protected WalkingLeaderboardEligibilityService $eligibilityService;

    public function __construct(?WalkingLeaderboardEligibilityService $eligibilityService = null)
    {
        $this->eligibilityService = $eligibilityService ?? app(WalkingLeaderboardEligibilityService::class);
    }

    /**
     * Return leaderboard entries (paginated or top-N) plus the caller's own rank/stats.
     *
     * @param  string  $period  today|weekly|monthly|all_time
     * @param  int|null  $viewerPersonId  the person making the request (for rank injection)
     * @param  array<int>|null  $restrictToPersonIds  supply for group-scoped leaderboards
     * @param  array|null  $dateRangeOverride
     * @param  int|null  $groupId  optional walking group ID to check privacy/anonymous settings
     * @param  int|null  $page  optional page for pagination (default: null, returns all/top)
     * @param  int  $perPage  items per page (default: 10)
     * @param  string|null  $requestedGenderFilter  deprecated/ignored (leaderboards are strictly segregated by viewer gender)
     * @param  \Carbon\Carbon|null  $referenceDate
     * @param  int|null  $minSteps  optional threshold for step clubs (e.g. 5000, 10000, 20000)
     * @param  int|null  $maxSteps  optional upper threshold for exclusive step club tiers (e.g. 9999 for 5k club, 19999 for 10k club)
     * @return array{period: string, min_steps: int|null, max_steps: int|null, date_range: array{from: string, to: string}, gender_visibility: array, top: Collection, viewer_rank: array|null, pagination: array|null}
     */
    public function build(
        string $period,
        ?int $viewerPersonId = null,
        ?array $restrictToPersonIds = null,
        ?array $dateRangeOverride = null,
        ?int $groupId = null,
        ?int $page = null,
        int $perPage = 10,
        ?string $requestedGenderFilter = null,
        ?\Carbon\Carbon $referenceDate = null,
        ?int $minSteps = null,
        ?int $maxSteps = null
    ): array {
        // Auto-resolve exclusive club tiers if minSteps matches standard club values and maxSteps is null
        if ($minSteps === 5000 && $maxSteps === null) {
            $maxSteps = 9999;
        } elseif ($minSteps === 10000 && $maxSteps === null) {
            $maxSteps = 19999;
        }

        app(WalkingRecordService::class)->purgeImplausibleRecords();

        [$from, $to] = $dateRangeOverride ?? $this->resolveDateRange($period, $referenceDate);

        $isGroupScoped = ($restrictToPersonIds !== null || $groupId !== null);

        // 1. Viewer & Gender Resolution
        $viewer = $viewerPersonId ? Person::withoutGlobalScopes()->find($viewerPersonId) : null;
        $viewerGender = $viewer?->gender ?? 'guest';

        // Gender Visibility Enforcement:
        if ($isGroupScoped) {
            // Group challenge: all joined members (males & females) compete together.
            if ($requestedGenderFilter && in_array($requestedGenderFilter, ['male', 'female'], true)) {
                $activeGenderFilter   = $requestedGenderFilter;
                $allowedGenderFilters = ['all', 'male', 'female'];
                $canToggleGender      = true;
            } else {
                $activeGenderFilter   = 'all';
                $allowedGenderFilters = ['all', 'male', 'female'];
                $canToggleGender      = true;
            }
        } else {
            // Platform-wide general leaderboard: strict segregation
            // Males see ONLY males, Females see ONLY females.
            // Mixing genders is strictly prohibited (no 'all' option and no third option).
            if ($viewerGender === 'female') {
                $activeGenderFilter   = 'female';
                $allowedGenderFilters = ['female'];
                $canToggleGender      = false;
            } else {
                // Male or guest viewer: only male participants
                $activeGenderFilter   = 'male';
                $allowedGenderFilters = ['male'];
                $canToggleGender      = false;
            }
        }

        // 2. Family & In-laws Eligibility Enforcement
        // Exclude isolated outsiders who have no family marriages and no family parents/ancestors.
        $eligiblePersonIds = $this->eligibilityService->getEligiblePersonIds();

        // 2.1. Walking Privacy Enforcement: Exclude persons who opted out of the leaderboard ('hidden')
        $hiddenPersonIds = Person::withoutGlobalScopes()
            ->where('walking_leaderboard_mode', 'hidden')
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->toArray();

        if ($isGroupScoped) {
            // In a group challenge, explicit group members participate in the challenge.
            // If a member has global 'hidden' mode, they are not excluded from the group;
            // instead, their privacy is protected by masking them as anonymous in the group leaderboard.
            $allowedPersonIds = array_values(array_unique($restrictToPersonIds ?? []));
        } else {
            if (! empty($hiddenPersonIds)) {
                $eligiblePersonIds = array_values(array_diff($eligiblePersonIds, $hiddenPersonIds));
            }
            $allowedPersonIds = $eligiblePersonIds;
        }

        // 3. Gender Filter on Eligible Person IDs
        if ($activeGenderFilter !== 'all') {
            $allowedPersonIds = DB::table('persons')
                ->whereIn('id', $allowedPersonIds)
                ->where('gender', $activeGenderFilter)
                ->pluck('id')
                ->toArray();
        }

        if (empty($allowedPersonIds)) {
            $recordedRows = collect();
        } else {
            $baseQuery = WalkingRecord::query()
                ->select('person_id', DB::raw('SUM(steps) as total_steps'))
                ->whereNotNull('person_id')
                ->whereBetween('date', [$from, $to])
                ->whereIn('person_id', $allowedPersonIds)
                ->groupBy('person_id');

            // Apply club minimum steps threshold if specified (e.g. 5,000, 10,000, 20,000)
            if ($minSteps !== null && $minSteps > 0) {
                $baseQuery->havingRaw('SUM(steps) >= ?', [$minSteps]);
            }
            if ($maxSteps !== null && $maxSteps > 0) {
                $baseQuery->havingRaw('SUM(steps) <= ?', [$maxSteps]);
            }

            $baseQuery->orderByDesc('total_steps')->orderBy('person_id');

            $recordedRows = $baseQuery->get()->keyBy('person_id');
        }

        // If restricting to specific persons (e.g. Group challenge), ensure all members are represented even with 0 steps
        if ($restrictToPersonIds !== null) {
            $allRows = collect();
            foreach ($allowedPersonIds as $pId) {
                if ($recordedRows->has($pId)) {
                    $allRows->push($recordedRows->get($pId));
                } elseif ($minSteps === null || $minSteps === 0) {
                    $allRows->push((object) [
                        'person_id'   => (int) $pId,
                        'total_steps' => 0,
                    ]);
                }
            }
            $allRows = $allRows->sort(function ($a, $b) {
                $stepsA = (int) $a->total_steps;
                $stepsB = (int) $b->total_steps;
                if ($stepsA !== $stepsB) {
                    return $stepsB <=> $stepsA;
                }
                return ((int) $a->person_id) <=> ((int) $b->person_id);
            })->values();
        } else {
            $allRows = $recordedRows->values();
        }

        // Rank all rows sequentially by total_steps (ties share the same rank)
        $isGroupScoped = ($restrictToPersonIds !== null);
        $ranked = $this->assignRanks($allRows, $isGroupScoped);
        $total = $ranked->count();

        // Determine slice to load (paginated or top N)
        if ($page !== null) {
            $lastPage = max(1, (int) ceil($total / $perPage));
            $currentPage = max(1, min($page, $lastPage));
            $offset = ($currentPage - 1) * $perPage;
            $slice = $ranked->slice($offset, $perPage)->values();
            $pagination = [
                'current_page' => $currentPage,
                'per_page'     => $perPage,
                'total'        => $total,
                'last_page'    => $lastPage,
                'has_more'     => $currentPage < $lastPage,
            ];
        } else {
            $limit = $isGroupScoped ? max(self::TOP_N, count($restrictToPersonIds)) : self::TOP_N;
            $slice = $ranked->take($limit)->values();
            $pagination = [
                'current_page' => 1,
                'per_page'     => $limit,
                'total'        => $total,
                'last_page'    => 1,
                'has_more'     => false,
            ];
        }

        // Preload persons in one single query to eliminate N+1
        $personIdsToLoad = $slice->pluck('person_id')->filter()->unique()->toArray();
        if ($viewerPersonId && ! in_array($viewerPersonId, $personIdsToLoad, true)) {
            $personIdsToLoad[] = $viewerPersonId;
        }

        $personsMap = empty($personIdsToLoad) ? collect() : Person::withoutGlobalScopes()
            ->with(['parent.parent.parent.parent.parent'])
            ->whereIn('id', $personIdsToLoad)
            ->get()
            ->keyBy('id');

        // Check group memberships and global person setting for anonymous mode
        $globalAnonymousPersonIds = Person::withoutGlobalScopes()
            ->where('walking_leaderboard_mode', 'anonymous')
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->toArray();

        $globalHiddenPersonIds = Person::withoutGlobalScopes()
            ->where('walking_leaderboard_mode', 'hidden')
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->toArray();

        $membershipsMap = collect();
        $anonymousPersonIds = [];
        if ($groupId) {
            $targetGroup = WalkingGroup::find($groupId);
            $isPrivateGroup = $targetGroup && ! (bool) $targetGroup->is_public;

            $membershipsMap = WalkingGroupMember::where('walking_group_id', $groupId)
                ->where('status', 'accepted')
                ->with(['group', 'person'])
                ->get()
                ->keyBy('person_id');

            // Private challenges/groups strictly forbid hidden participation
            if (! $isPrivateGroup) {
                foreach ($membershipsMap as $pId => $m) {
                    $isAnon = $m->is_anonymous !== null
                        ? (bool) $m->is_anonymous
                        : (in_array((int) $pId, $globalAnonymousPersonIds, true) || in_array((int) $pId, $globalHiddenPersonIds, true));
                    if ($isAnon) {
                        $anonymousPersonIds[] = (int) $pId;
                    }
                }
            }
        } else {
            // Platform-wide leaderboard: strictly respect the person's own global setting
            $anonymousPersonIds = $globalAnonymousPersonIds;
        }

        // Preload 10,000-step streaks in a single batch query for all displayed persons
        $streaksMap = $this->calculate10kStreaksForPersons($personIdsToLoad);

        // Formatted entries for this page/slice
        $top = $slice->map(fn ($row) => $this->formatEntry($row, $personsMap, $viewerPersonId, $anonymousPersonIds, $membershipsMap, $streaksMap));

        // Viewer's own rank (may or may not be on the current page)
        $viewerRank = null;
        if ($viewerPersonId !== null) {
            $viewerPerson = $personsMap->get($viewerPersonId);
            if (! $viewerPerson && $viewerPersonId) {
                $viewerPerson = Person::withoutGlobalScopes()->find($viewerPersonId);
                if ($viewerPerson) {
                    $personsMap->put($viewerPersonId, $viewerPerson);
                }
            }

            $isViewerHidden = (! $isGroupScoped && $viewerPerson && ($viewerPerson->walking_leaderboard_mode === 'hidden'));
            $viewerRow = $ranked->firstWhere('person_id', $viewerPersonId);

            $viewerSteps = (int) WalkingRecord::where('person_id', $viewerPersonId)
                ->whereBetween('date', [$from, $to])
                ->sum('steps');

            $isQualified = ($minSteps !== null && $minSteps > 0)
                ? ($viewerSteps >= $minSteps && ($maxSteps === null || $viewerSteps <= $maxSteps))
                : true;
            $stepsNeeded = ($minSteps !== null && $minSteps > 0) ? max(0, $minSteps - $viewerSteps) : 0;

            if ($isViewerHidden) {
                $viewerRank = [
                    'rank'                     => null,
                    'person_id'                => $viewerPersonId,
                    'total_steps'              => $viewerSteps,
                    'streak_10k'               => (int) ($streaksMap[$viewerPersonId] ?? 0),
                    'is_anonymous'             => false,
                    'is_hidden'                => true,
                    'is_self'                  => true,
                    'is_qualified'             => $isQualified,
                    'steps_needed'             => $stepsNeeded,
                    'min_steps'                => $minSteps,
                    'max_steps'                => $maxSteps,
                    'completed_at'             => null,
                    'is_goal_reached'          => false,
                    'completion_duration_text' => null,
                    'person'                   => $viewerPerson ? [
                        'id'     => $viewerPerson->id,
                        'name'   => $viewerPerson->full_name,
                        'avatar' => $viewerPerson->avatar,
                        'gender' => $viewerPerson->gender ?? 'male',
                    ] : null,
                    'in_top'                   => false,
                ];
            } else {
                if ($viewerRow) {
                    $viewerRank = $this->formatEntry($viewerRow, $personsMap, $viewerPersonId, $anonymousPersonIds, $membershipsMap, $streaksMap);
                    $viewerRank['is_qualified'] = true;
                    $viewerRank['steps_needed'] = 0;
                    $viewerRank['min_steps']    = $minSteps;
                    $viewerRank['max_steps']    = $maxSteps;
                } else {
                    $viewerRank = [
                        'rank'                     => null,
                        'membership_id'            => $membershipsMap->get($viewerPersonId)?->id,
                        'person_id'                => $viewerPersonId,
                        'total_steps'              => $viewerSteps,
                        'streak_10k'               => (int) ($streaksMap[$viewerPersonId] ?? 0),
                        'is_anonymous'             => in_array($viewerPersonId, $anonymousPersonIds, true),
                        'is_hidden'                => false,
                        'is_self'                  => true,
                        'is_qualified'             => $isQualified,
                        'steps_needed'             => $stepsNeeded,
                        'min_steps'                => $minSteps,
                        'max_steps'                => $maxSteps,
                        'completed_at'             => null,
                        'is_goal_reached'          => false,
                        'completion_duration_text' => null,
                        'person'                   => $viewerPerson ? [
                            'id'     => $viewerPerson->id,
                            'name'   => $viewerPerson->full_name,
                            'avatar' => $viewerPerson->avatar,
                            'gender' => $viewerPerson->gender ?? 'male',
                        ] : null,
                        'in_top'                   => false,
                    ];
                }
            }
        }

        $viewerLeaderboardMode = $viewer ? ($viewer->walking_leaderboard_mode ?? 'visible') : 'visible';

        return [
            'period'            => $period,
            'min_steps'         => $minSteps,
            'max_steps'         => $maxSteps,
            'date_range'        => [
                'from' => $from->toDateString(),
                'to'   => $to->toDateString(),
            ],
            'gender_visibility' => [
                'viewer_gender'          => $viewerGender,
                'active_gender_filter'   => $activeGenderFilter,
                'allowed_gender_filters' => $allowedGenderFilters,
                'can_toggle_gender'      => $canToggleGender,
            ],
            'viewer_privacy'    => [
                'leaderboard_mode' => $viewerLeaderboardMode,
            ],
            'top'               => $top,
            'viewer_rank'       => $viewerRank,
            'pagination'        => $pagination,
        ];
    }

    // =========================================================================
    //  أبطال الاستمرارية (Streaks Leaderboard)
    //  Preserved for future re-activation (محفوظ بالكامل لإعادة التفعيل مستقبلاً)
    // =========================================================================

    /**
     * Return streak-based leaderboard entries (ranked strictly by unbroken 10,000-step streaks).
     *
     * @param  int|null  $viewerPersonId
     * @param  int|null  $page
     * @param  int  $perPage
     * @param  string|null  $requestedGenderFilter
     * @return array
     */
    public function buildStreakLeaderboard(
        ?int $viewerPersonId = null,
        ?int $page = null,
        int $perPage = 10,
        ?string $requestedGenderFilter = null,
        ?Carbon $referenceDate = null,
        int $minSteps = 10000,
        ?int $maxSteps = null
    ): array {
        // Resolve dynamic club configuration if minSteps matches an active club
        $activeClub = null;
        if ($minSteps !== null) {
            $activeClub = WalkingClub::getActiveClubs()->firstWhere('min_steps', $minSteps);
            if ($activeClub && $maxSteps === null) {
                $maxSteps = $activeClub->max_steps;
            }
        }

        // Auto-resolve exclusive club tiers if minSteps matches standard club values and maxSteps is null
        if ($minSteps === 5000 && $maxSteps === null) {
            $maxSteps = 9999;
        } elseif ($minSteps === 10000 && $maxSteps === null) {
            $maxSteps = 19999;
        }

        // 1. Viewer & Gender Resolution
        $viewer = $viewerPersonId ? Person::withoutGlobalScopes()->find($viewerPersonId) : null;
        $viewerGender = $viewer?->gender ?? 'guest';

        // Gender Visibility Enforcement:
        // Strict segregation: Males see ONLY males, Females see ONLY females.
        // Mixing genders is strictly prohibited (no 'all' option and no third option).
        if ($viewerGender === 'female') {
            $activeGenderFilter   = 'female';
            $allowedGenderFilters = ['female'];
            $canToggleGender      = false;
        } else {
            // Male or guest viewer: only male participants
            $activeGenderFilter   = 'male';
            $allowedGenderFilters = ['male'];
            $canToggleGender      = false;
        }

        // 2. Family & In-laws Eligibility
        $eligiblePersonIds = $this->eligibilityService->getEligiblePersonIds();

        // Privacy: Exclude hidden persons
        $hiddenPersonIds = Person::withoutGlobalScopes()
            ->where('walking_leaderboard_mode', 'hidden')
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->toArray();

        if (! empty($hiddenPersonIds)) {
            $eligiblePersonIds = array_values(array_diff($eligiblePersonIds, $hiddenPersonIds));
        }

        $allowedPersonIds = $eligiblePersonIds;

        // Gender filtering
        if ($activeGenderFilter !== 'all') {
            $allowedPersonIds = DB::table('persons')
                ->whereIn('id', $allowedPersonIds)
                ->where('gender', $activeGenderFilter)
                ->pluck('id')
                ->toArray();
        }

        $today = ($referenceDate ? $referenceDate->copy() : Carbon::today('Asia/Riyadh'))->startOfDay();
        $todayStr = $today->format('Y-m-d');
        $yesterday = $today->copy()->subDay();
        $yesterdayStr = $yesterday->format('Y-m-d');

        // Include viewer in persons to inspect steps for
        $personIdsToInspect = array_values(array_unique(array_merge(
            $allowedPersonIds,
            $viewerPersonId ? [$viewerPersonId] : []
        )));

        // Preload recent records (yesterday and today) in one single query
        $recentRows = empty($personIdsToInspect) ? collect() : WalkingRecord::query()
            ->select('person_id', 'date', 'steps')
            ->whereIn('person_id', $personIdsToInspect)
            ->whereDate('date', '>=', $yesterdayStr)
            ->whereDate('date', '<=', $todayStr)
            ->get();

        $todayStepsMap = [];
        $yesterdayStepsMap = [];
        foreach ($recentRows as $r) {
            $d = Carbon::parse($r->date)->format('Y-m-d');
            $pId = (int) $r->person_id;
            if ($d === $todayStr) {
                $todayStepsMap[$pId] = (int) $r->steps;
            } elseif ($d === $yesterdayStr) {
                $yesterdayStepsMap[$pId] = (int) $r->steps;
            }
        }

        // 3. Find candidate person IDs who qualify for this exclusive club tier
        // A participant belongs to their effective active tier:
        // - Effective tier is max(yesterday_tier, today_tier).
        // - A participant preserves their achieved club from yesterday throughout today until midnight (12:00 AM).
        // - If today's steps reach a higher tier, they are promoted immediately to that higher club tier.
        // - Under no circumstances does partial progress during the day demote a participant to a lower tier before midnight.
        // - When midnight passes, yesterday's steps become the new baseline: if they failed the tier, they drop out.
        $candidatePersonIds = [];
        foreach ($allowedPersonIds as $pId) {
            $pId = (int) $pId;
            $todaySteps = $todayStepsMap[$pId] ?? 0;
            $yesterdaySteps = $yesterdayStepsMap[$pId] ?? 0;

            $yesterdayTier = $this->resolveStepClubTier($yesterdaySteps);
            $todayTier     = $this->resolveStepClubTier($todaySteps);
            $effectiveTier = max($yesterdayTier, $todayTier);

            if ($effectiveTier >= $minSteps && ($maxSteps === null || $effectiveTier <= $maxSteps)) {
                $candidatePersonIds[] = $pId;
            }
        }

        // Always include viewer for their personal streak calculation
        $idsToCalculate = array_values(array_unique(array_merge(
            $candidatePersonIds,
            $viewerPersonId ? [$viewerPersonId] : []
        )));

        $calculationStartDate = $activeClub?->calculation_start_date ? Carbon::parse($activeClub->calculation_start_date) : null;
        $streaksMap = $this->calculateStreaksForPersons($idsToCalculate, $minSteps, $referenceDate, $calculationStartDate);

        // 4. Build leaderboard rows: only include active participants with unbroken streak > 0
        $rows = collect();
        foreach ($candidatePersonIds as $pId) {
            $streak = (int) ($streaksMap[$pId] ?? 0);
            if ($streak > 0) {
                $rows->push((object) [
                    'person_id'   => $pId,
                    'streak_days' => $streak,
                    'streak_10k'  => $streak, // backwards compatibility
                    'total_steps' => (int) ($todayStepsMap[$pId] ?? 0),
                ]);
            }
        }

        // Sort by streak_days DESC, then today's steps DESC
        $rows = $rows->sort(function ($a, $b) {
            $streakA = $a->streak_days ?? $a->streak_10k ?? 0;
            $streakB = $b->streak_days ?? $b->streak_10k ?? 0;
            if ($streakA !== $streakB) {
                return $streakB <=> $streakA;
            }
            return $b->total_steps <=> $a->total_steps;
        })->values();

        // Dense ranking
        $ranked = $this->assignStreakRanks($rows);
        $total = $ranked->count();

        // Slice / pagination
        if ($page !== null) {
            $lastPage = max(1, (int) ceil($total / $perPage));
            $currentPage = max(1, min($page, $lastPage));
            $offset = ($currentPage - 1) * $perPage;
            $slice = $ranked->slice($offset, $perPage)->values();
            $pagination = [
                'current_page' => $currentPage,
                'per_page'     => $perPage,
                'total'        => $total,
                'last_page'    => $lastPage,
                'has_more'     => $currentPage < $lastPage,
            ];
        } else {
            $slice = $ranked->take(self::TOP_N)->values();
            $pagination = [
                'current_page' => 1,
                'per_page'     => self::TOP_N,
                'total'        => $total,
                'last_page'    => 1,
                'has_more'     => false,
            ];
        }

        // Preload Person details
        $personIdsToLoad = $slice->pluck('person_id')->filter()->unique()->toArray();
        if ($viewerPersonId && ! in_array($viewerPersonId, $personIdsToLoad, true)) {
            $personIdsToLoad[] = $viewerPersonId;
        }

        $personsMap = empty($personIdsToLoad) ? collect() : Person::withoutGlobalScopes()
            ->with(['parent.parent.parent.parent.parent'])
            ->whereIn('id', $personIdsToLoad)
            ->get()
            ->keyBy('id');

        $globalAnonymousPersonIds = Person::withoutGlobalScopes()
            ->where('walking_leaderboard_mode', 'anonymous')
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->toArray();

        // Format entries
        $top = $slice->map(fn ($row) => $this->formatStreakEntry(
            $row,
            $personsMap,
            $viewerPersonId,
            $globalAnonymousPersonIds,
            $todayStepsMap,
            $minSteps,
            $maxSteps
        ));

        // Viewer rank resolution
        $viewerRank = null;
        if ($viewerPersonId !== null) {
            $viewerPerson = $personsMap->get($viewerPersonId);
            if (! $viewerPerson && $viewerPersonId) {
                $viewerPerson = Person::withoutGlobalScopes()->find($viewerPersonId);
            }

            $isViewerHidden = $viewerPerson && ($viewerPerson->walking_leaderboard_mode === 'hidden');
            $viewerStreak = (int) ($streaksMap[$viewerPersonId] ?? 0);
            $viewerTodaySteps = (int) ($todayStepsMap[$viewerPersonId] ?? 0);
            $viewerYesterdaySteps = (int) ($yesterdayStepsMap[$viewerPersonId] ?? 0);
            $viewerYesterdayTier  = $this->resolveStepClubTier($viewerYesterdaySteps);
            $viewerTodayTier      = $this->resolveStepClubTier($viewerTodaySteps);
            $viewerEffectiveTier  = max($viewerYesterdayTier, $viewerTodayTier);

            $isViewerQualified = ($viewerEffectiveTier >= $minSteps) && ($maxSteps === null || $viewerEffectiveTier <= $maxSteps);
            $isViewerTodayCompleted = ($viewerTodaySteps >= $minSteps) && ($maxSteps === null || $viewerTodaySteps <= $maxSteps);

            $resolvedViewerClub = WalkingClub::resolveForSteps($viewerEffectiveTier);
            $viewerActiveClub = $resolvedViewerClub?->slug ?? match ($viewerEffectiveTier) {
                20000 => 'club_20k',
                10000 => 'club_10k',
                5000  => 'club_5k',
                default => null,
            };

            $viewerRow = $ranked->firstWhere('person_id', $viewerPersonId);

            if ($isViewerHidden) {
                $viewerRank = [
                    'rank'               => null,
                    'person_id'          => $viewerPersonId,
                    'streak_days'        => $viewerStreak,
                    'streak_10k'         => $viewerStreak,
                    'min_steps'          => $minSteps,
                    'max_steps'          => $maxSteps,
                    'total_steps'        => $viewerTodaySteps,
                    'is_today_completed' => $isViewerTodayCompleted,
                    'is_qualified'       => $isViewerQualified,
                    'active_club'        => $viewerActiveClub,
                    'is_anonymous'       => false,
                    'is_hidden'          => true,
                    'is_self'            => true,
                    'person'             => $viewerPerson ? [
                        'id'     => $viewerPerson->id,
                        'name'   => $viewerPerson->full_name,
                        'avatar' => $viewerPerson->avatar,
                        'gender' => $viewerPerson->gender ?? 'male',
                    ] : null,
                    'in_top'             => false,
                ];
            } else {
                if ($viewerRow) {
                    $viewerRank = $this->formatStreakEntry(
                        $viewerRow,
                        $personsMap,
                        $viewerPersonId,
                        $globalAnonymousPersonIds,
                        $todayStepsMap,
                        $minSteps,
                        $maxSteps
                    );
                    $viewerRank['is_qualified'] = true;
                    $viewerRank['active_club']  = $viewerActiveClub;
                } else {
                    $viewerRank = [
                        'rank'               => null,
                        'person_id'          => $viewerPersonId,
                        'streak_days'        => $viewerStreak,
                        'streak_10k'         => $viewerStreak,
                        'min_steps'          => $minSteps,
                        'max_steps'          => $maxSteps,
                        'total_steps'        => $viewerTodaySteps,
                        'is_today_completed' => $isViewerTodayCompleted,
                        'is_qualified'       => $isViewerQualified,
                        'active_club'        => $viewerActiveClub,
                        'is_anonymous'       => in_array($viewerPersonId, $globalAnonymousPersonIds, true),
                        'is_hidden'          => false,
                        'is_self'            => true,
                        'person'             => $viewerPerson ? [
                            'id'     => $viewerPerson->id,
                            'name'   => $viewerPerson->full_name,
                            'avatar' => $viewerPerson->avatar,
                            'gender' => $viewerPerson->gender ?? 'male',
                        ] : null,
                        'in_top'             => false,
                    ];
                }
            }
        }

        $viewerLeaderboardMode = $viewer ? ($viewer->walking_leaderboard_mode ?? 'visible') : 'visible';

        return [
            'type'              => 'streak',
            'period'            => 'all_time',
            'min_steps'         => $minSteps,
            'max_steps'         => $maxSteps,
            'club'              => $activeClub ? $activeClub->toMobileArray() : null,
            'gender_visibility' => [
                'viewer_gender'          => $viewerGender,
                'active_gender_filter'   => $activeGenderFilter,
                'allowed_gender_filters' => $allowedGenderFilters,
                'can_toggle_gender'      => $canToggleGender,
            ],
            'viewer_privacy' => [
                'leaderboard_mode' => $viewerLeaderboardMode,
            ],
            'top'         => $top,
            'viewer_rank' => $viewerRank,
            'pagination'  => $pagination,
        ];
    }

    /**
     * Compute consecutive days of reaching at least $minSteps for the given persons in a single query.
     * Optionally respects $startDate: any dates prior to $startDate are strictly excluded.
     *
     * @param  array<int>  $personIds
     * @param  int  $minSteps
     * @param  Carbon|null  $asOf  reference "today" (defaults to KSA today)
     * @param  Carbon|null  $startDate  cutoff date: dates before this are not counted
     * @return array<int, int> [person_id => streak_days]
     */
    public function calculateStreaksForPersons(
        array $personIds,
        int $minSteps = 10000,
        ?Carbon $asOf = null,
        ?Carbon $startDate = null
    ): array {
        $personIds = array_values(array_filter(array_unique($personIds)));
        if (empty($personIds)) {
            return [];
        }

        $startCarbon = $startDate ? $startDate->copy()->startOfDay() : null;
        $startDateStr = $startCarbon?->format('Y-m-d');

        // Fetch all distinct dates where steps >= minSteps for these persons
        $query = WalkingRecord::query()
            ->select('person_id', 'date')
            ->whereIn('person_id', $personIds)
            ->where('steps', '>=', $minSteps);

        if ($startCarbon) {
            $query->whereDate('date', '>=', $startDateStr);
        }

        $rows = $query->orderByDesc('date')->get();

        $grouped = [];
        foreach ($rows as $row) {
            $pId = (int) $row->person_id;
            $dateStr = Carbon::parse($row->date)->format('Y-m-d');
            $grouped[$pId][$dateStr] = true;
        }

        $today = ($asOf ? $asOf->copy() : Carbon::today('Asia/Riyadh'))->startOfDay();
        $todayStr = $today->format('Y-m-d');
        $yesterday = $today->copy()->subDay();
        $yesterdayStr = $yesterday->format('Y-m-d');

        $result = [];
        foreach ($personIds as $pId) {
            if ($startCarbon && $today->lt($startCarbon)) {
                $result[$pId] = 0;
                continue;
            }

            $dateSet = $grouped[$pId] ?? [];
            if (empty($dateSet)) {
                $result[$pId] = 0;
                continue;
            }

            $streak = 0;
            $checkDate = null;

            // If today is already completed (>= minSteps), streak includes today (1) and counts backward from yesterday
            if (isset($dateSet[$todayStr])) {
                $streak = 1;
                $checkDate = $yesterday->copy();
            } elseif (isset($dateSet[$yesterdayStr])) {
                // Today is not completed yet (<minSteps so far), but yesterday was -> active streak from yesterday is preserved!
                // Only if yesterday is on or after the calculation start date
                if (! $startCarbon || $yesterday->gte($startCarbon)) {
                    $streak = 0;
                    $checkDate = $yesterday->copy();
                } else {
                    $result[$pId] = 0;
                    continue;
                }
            } else {
                // Neither today nor yesterday reached minSteps -> streak is broken to 0
                $result[$pId] = 0;
                continue;
            }

            // Count unbroken consecutive past days
            while ($checkDate !== null && isset($dateSet[$checkDate->format('Y-m-d')])) {
                if ($startCarbon && $checkDate->lt($startCarbon)) {
                    break;
                }
                $streak++;
                $checkDate->subDay();
            }

            $result[$pId] = $streak;
        }

        return $result;
    }

    /**
     * Compatibility wrapper for calculateStreaksForPersons with 10k default.
     *
     * @param  array<int>  $personIds
     * @param  Carbon|null  $asOf
     * @return array<int, int>
     */
    public function calculate10kStreaksForPersons(array $personIds, ?Carbon $asOf = null): array
    {
        return $this->calculateStreaksForPersons($personIds, 10000, $asOf);
    }

    /**
     * Resolve which exclusive step club tier a given step count qualifies for.
     * Queries active dynamic WalkingClubs with fallback to 20k/10k/5k.
     */
    public function resolveStepClubTier(int $steps): int
    {
        $club = WalkingClub::resolveForSteps($steps);
        if ($club) {
            return (int) $club->min_steps;
        }

        if ($steps >= 20000) {
            return 20000;
        }
        if ($steps >= 10000) {
            return 10000;
        }
        if ($steps >= 5000) {
            return 5000;
        }

        return 0;
    }

    // ── Private helpers ────────────────────────────────────────────

    public function resolveDateRange(string $period, ?\Carbon\Carbon $referenceDate = null): array
    {
        $today = ($referenceDate ? $referenceDate->copy() : Carbon::today('Asia/Riyadh'))->startOfDay();

        return match ($period) {
            self::PERIOD_TODAY   => [$today->copy(), $today->copy()],
            self::PERIOD_WEEKLY  => [$today->copy()->subDays(6), $today->copy()],
            self::PERIOD_MONTHLY => [$today->copy()->subDays(29), $today->copy()],
            default              => [Carbon::create(2000, 1, 1), $today->copy()], // all_time
        };
    }

    /** Dense ranking: tied entries share the same rank. Strictly ordered by total_steps without gaps (1, 2, 2, 3...). */
    private function assignRanks(Collection $rows, bool $isGroupScoped = false): Collection
    {
        $rank = 0;
        $prevSteps = null;

        return $rows->values()->map(function ($row, $index) use (&$rank, &$prevSteps, $isGroupScoped) {
            $totalSteps = (int) $row->total_steps;
            if ($totalSteps !== $prevSteps) {
                $rank++;
                $prevSteps = $totalSteps;
            }
            $row->rank   = $rank;
            $row->in_top = $isGroupScoped ? true : ($rank <= self::TOP_N);
            return $row;
        });
    }

    private function formatEntry(
        object $row,
        Collection $personsMap,
        ?int $viewerPersonId = null,
        array $anonymousPersonIds = [],
        ?Collection $membershipsMap = null,
        array $streaksMap = []
    ): array {
        $pId = (int) $row->person_id;
        $isSelf = $viewerPersonId && ($pId === (int) $viewerPersonId);
        $isAnonymous = in_array($pId, $anonymousPersonIds, true);
        $streak10k = (int) ($streaksMap[$pId] ?? 0);

        $membership = $membershipsMap ? $membershipsMap->get($pId) : null;
        $completedAt = $membership?->completed_at?->toDateTimeString();
        $isGoalReached = !is_null($membership?->completed_at);
        $durationText = $membership?->completion_duration_text;

        // If anonymous and not self, mask identity completely in leaderboard
        if ($isAnonymous && ! $isSelf) {
            $maskedGender = $personsMap->get($pId)?->gender ?? 'male';

            return [
                'rank'                     => $row->rank,
                'membership_id'            => $membership?->id,
                'person_id'                => null,
                'total_steps'              => (int) $row->total_steps,
                'streak_10k'               => $streak10k,
                'in_top'                   => $row->in_top ?? false,
                'is_anonymous'             => true,
                'is_self'                  => false,
                'completed_at'             => $completedAt,
                'is_goal_reached'          => $isGoalReached,
                'completion_duration_text' => $durationText,
                'person'                   => [
                    'id'     => 0,
                    'name'   => 'متسابق متخفي',
                    'avatar' => \App\Models\Person::anonymousAvatarFor($maskedGender),
                    'gender' => $maskedGender,
                ],
            ];
        }

        $person = $personsMap->get($pId);

        return [
            'rank'                     => $row->rank,
            'membership_id'            => $membership?->id,
            'person_id'                => $pId,
            'total_steps'              => (int) $row->total_steps,
            'streak_10k'               => $streak10k,
            'in_top'                   => $row->in_top ?? false,
            'is_anonymous'             => $isAnonymous,
            'is_self'                  => $isSelf,
            'completed_at'             => $completedAt,
            'is_goal_reached'          => $isGoalReached,
            'completion_duration_text' => $durationText,
            'person'                   => $person ? [
                'id'         => $person->id,
                'name'       => ($isSelf && $isAnonymous) ? "{$person->full_name} (أنت - متخفي)" : $person->full_name,
                'avatar'     => $person->avatar,
                'gender'     => $person->gender ?? 'male',
            ] : null,
        ];
    }

    /** Dense ranking for streaks: tied streak days share the same rank. Strictly ordered by streak_10k without gaps. */
    private function assignStreakRanks(Collection $rows): Collection
    {
        $rank = 0;
        $prevStreak = null;

        return $rows->values()->map(function ($row, $index) use (&$rank, &$prevStreak) {
            $streak = (int) ($row->streak_days ?? $row->streak_10k ?? 0);
            if ($streak !== $prevStreak) {
                $rank++;
                $prevStreak = $streak;
            }
            $row->rank   = $rank;
            $row->in_top = ($rank <= self::TOP_N);
            return $row;
        });
    }

    private function formatStreakEntry(
        object $row,
        Collection $personsMap,
        ?int $viewerPersonId = null,
        array $anonymousPersonIds = [],
        array $todayStepsMap = [],
        int $minSteps = 10000,
        ?int $maxSteps = null
    ): array {
        $pId = (int) $row->person_id;
        $isSelf = $viewerPersonId && ($pId === (int) $viewerPersonId);
        $isAnonymous = in_array($pId, $anonymousPersonIds, true);
        $streak = (int) ($row->streak_days ?? $row->streak_10k ?? 0);
        $todaySteps = (int) ($todayStepsMap[$pId] ?? $row->total_steps ?? 0);
        $isTodayCompleted = $todaySteps >= $minSteps && ($maxSteps === null || $todaySteps <= $maxSteps);

        if ($isAnonymous && ! $isSelf) {
            $maskedGender = $personsMap->get($pId)?->gender ?? 'male';

            return [
                'rank'               => $row->rank,
                'person_id'          => null,
                'streak_days'        => $streak,
                'streak_10k'         => $streak,
                'min_steps'          => $minSteps,
                'max_steps'          => $maxSteps,
                'total_steps'        => $todaySteps,
                'is_today_completed' => $isTodayCompleted,
                'is_qualified'       => true,
                'in_top'             => $row->in_top ?? false,
                'is_anonymous'       => true,
                'is_self'            => false,
                'person'             => [
                    'id'     => 0,
                    'name'   => 'متسابق متخفي',
                    'avatar' => \App\Models\Person::anonymousAvatarFor($maskedGender),
                    'gender' => $maskedGender,
                ],
            ];
        }

        $person = $personsMap->get($pId);

        return [
            'rank'               => $row->rank,
            'person_id'          => $pId,
            'streak_days'        => $streak,
            'streak_10k'         => $streak,
            'min_steps'          => $minSteps,
            'max_steps'          => $maxSteps,
            'total_steps'        => $todaySteps,
            'is_today_completed' => $isTodayCompleted,
            'is_qualified'       => true,
            'in_top'             => $row->in_top ?? false,
            'is_anonymous'       => $isAnonymous,
            'is_self'            => $isSelf,
            'person'             => $person ? [
                'id'         => $person->id,
                'name'       => ($isSelf && $isAnonymous) ? "{$person->full_name} (أنت - متخفي)" : $person->full_name,
                'avatar'     => $person->avatar,
                'gender'     => $person->gender ?? 'male',
            ] : null,
        ];
    }
}
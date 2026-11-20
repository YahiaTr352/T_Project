<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Walking\AudienceFilterRequest;
use App\Models\Location;
use App\Models\Person;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\WalkingClub;
use App\Models\WalkingDailyReset;
use App\Models\WalkingGroup;
use App\Models\WalkingGroupMember;
use App\Models\WalkingRecord;
use App\Services\AI\Tools\PersonLookupTool;
use App\Services\Walking\PersonAudienceFilterService;
use App\Services\Walking\WalkingGroupService;
use App\Services\Walking\WalkingLeaderboardService;
use App\Services\Walking\WalkingRecordService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WalkingProgramController extends Controller
{
    private const DAILY_GOAL_STEPS = 10000;

    public function __construct(
        protected WalkingGroupService $groupService,
        protected WalkingLeaderboardService $leaderboardService,
        protected WalkingRecordService $recordService,
        protected PersonLookupTool $personLookup,
        protected PersonAudienceFilterService $audienceFilterService
    ) {}

    /**
     * AJAX search endpoint for Select2.
     * Uses PersonLookupTool to match patronymic lineage and return full_name.
     */
    public function searchPersons(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        if ($q === '') {
            $people = Person::query()
                ->withoutGlobalScopes()
                ->with(['parent.parent.parent.parent.parent'])
                ->where('from_outside_the_family', false)
                ->limit(20)
                ->get();
        } else {
            $people = $this->personLookup->search($q, 25);
            $people->each(function (Person $p) {
                $p->loadMissing(['parent.parent.parent.parent.parent']);
            });
        }

        $results = $people->map(function (Person $person) {
            return [
                'id'        => $person->id,
                'text'      => $person->full_name,
                'full_name' => $person->full_name,
                'avatar'    => $person->avatar,
                'gender'    => $person->gender,
            ];
        })->values();

        return response()->json([
            'results' => $results,
        ]);
    }

    /**
     * Display the comprehensive walking dashboard.
     */
    public function index(Request $request): View
    {
        // Auto-heal legacy records that have user_id but missing person_id
        $this->healLegacyUserRecords();
        $this->recordService->purgeImplausibleRecords();

        $today = Carbon::today();
        $startOfWeek = Carbon::now()->startOfWeek(CarbonInterface::SATURDAY);
        $endOfWeek = Carbon::now()->endOfWeek(CarbonInterface::FRIDAY);
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();
        $startOfLastWeek = Carbon::now()->subWeek()->startOfWeek(CarbonInterface::SATURDAY);
        $endOfLastWeek = Carbon::now()->subWeek()->endOfWeek(CarbonInterface::FRIDAY);
        $startOfLastMonth = Carbon::now()->subMonth()->startOfMonth();
        $endOfLastMonth = Carbon::now()->subMonth()->endOfMonth();

        // 1️⃣ Multi-Period Leaderboards (Rankings)
        $leaderboardGender = (string) $request->query('leaderboard_gender', 'all');
        if (! in_array($leaderboardGender, ['all', 'male', 'female'], true)) {
            $leaderboardGender = 'all';
        }

        $dailyRankings = $this->getRankingsForRange($today->copy()->startOfDay(), $today->copy()->endOfDay(), $leaderboardGender);
        $weeklyRankings = $this->getRankingsForRange($startOfWeek, $endOfWeek, $leaderboardGender);
        $monthlyRankings = $this->getRankingsForRange($startOfMonth, $endOfMonth, $leaderboardGender);
        $allTimeRankings = $this->getRankingsForRange(Carbon::create(2000, 1, 1), $today->copy()->endOfDay(), $leaderboardGender);

        // Custom Date / Range Leaderboard Filter
        $leaderboardPeriod = (string) $request->query('leaderboard_period', 'today');
        $leaderboardDate = $request->query('leaderboard_date');
        $leaderboardFrom = $request->query('leaderboard_from');
        $leaderboardTo = $request->query('leaderboard_to');

        $isCustomLeaderboard = false;
        $customRankings = collect();
        $customStats = ['total_steps' => 0, 'participants' => 0, 'average' => 0];
        $customPeriodLabel = '';

        if ($leaderboardPeriod === 'custom' || ! empty($leaderboardDate) || (! empty($leaderboardFrom) && ! empty($leaderboardTo))) {
            $isCustomLeaderboard = true;
            $leaderboardPeriod = 'custom';

            try {
                if (! empty($leaderboardDate)) {
                    $cFrom = Carbon::parse($leaderboardDate)->startOfDay();
                    $cTo = Carbon::parse($leaderboardDate)->endOfDay();
                    $customPeriodLabel = 'يوم ' . $cFrom->format('Y-m-d');
                } elseif (! empty($leaderboardFrom) && ! empty($leaderboardTo)) {
                    $cFrom = Carbon::parse($leaderboardFrom)->startOfDay();
                    $cTo = Carbon::parse($leaderboardTo)->endOfDay();
                    $customPeriodLabel = 'الفترة من ' . $cFrom->format('Y-m-d') . ' إلى ' . $cTo->format('Y-m-d');
                } elseif (! empty($leaderboardFrom)) {
                    $cFrom = Carbon::parse($leaderboardFrom)->startOfDay();
                    $cTo = Carbon::now()->endOfDay();
                    $customPeriodLabel = 'من ' . $cFrom->format('Y-m-d') . ' حتى الآن';
                } else {
                    $cFrom = $today->copy()->startOfDay();
                    $cTo = $today->copy()->endOfDay();
                    $customPeriodLabel = 'يوم ' . $today->format('Y-m-d');
                }

                $customRankings = $this->getRankingsForRange($cFrom, $cTo, $leaderboardGender);
                $customTotalSteps = (int) $customRankings->sum('total_steps');
                $customParticipants = $customRankings->count();
                $customAvg = $customParticipants > 0 ? (int) round($customTotalSteps / $customParticipants) : 0;
                $customStats = [
                    'total_steps'  => $customTotalSteps,
                    'participants' => $customParticipants,
                    'average'      => $customAvg,
                ];
            } catch (\Throwable $e) {
                \Log::warning('Custom leaderboard date parse error: ' . $e->getMessage());
                $isCustomLeaderboard = false;
                $leaderboardPeriod = 'today';
            }
        }

        // 2️⃣ Overall KPIs
        $dailyTotalSteps = (int) $dailyRankings->sum('total_steps');
        $weeklyTotalSteps = (int) $weeklyRankings->sum('total_steps');
        $monthlyTotalSteps = (int) $monthlyRankings->sum('total_steps');
        $allTimeTotalSteps = (int) $allTimeRankings->sum('total_steps');

        $dailyParticipants = $dailyRankings->count();
        $weeklyParticipants = $weeklyRankings->count();
        $monthlyParticipants = $monthlyRankings->count();
        $allTimeParticipants = $allTimeRankings->count();

        $dailyAvg = $dailyParticipants > 0 ? (int) round($dailyTotalSteps / $dailyParticipants) : 0;
        $weeklyAvg = $weeklyParticipants > 0 ? (int) round($weeklyTotalSteps / $weeklyParticipants) : 0;
        $monthlyAvg = $monthlyParticipants > 0 ? (int) round($monthlyTotalSteps / $monthlyParticipants) : 0;

        // Total distance walked across all records
        $totalDistanceKm = (float) WalkingRecord::sum('distance_km');
        $totalCalories = (int) WalkingRecord::sum('calories');

        // 3️⃣ Smart Statistics & Insights
        $smartStats = $this->getSmartStatistics(
            $today,
            $startOfMonth,
            $endOfMonth,
            $startOfLastMonth,
            $endOfLastMonth,
            $startOfWeek,
            $endOfWeek,
            $startOfLastWeek,
            $endOfLastWeek
        );

        // 4️⃣ Walking Groups & Challenges (Categorized with accurate counts)
        $groupStatusFilter = (string) $request->query('group_status', 'active');

        $activeGroupsCount = WalkingGroup::running()->count();
        $completedGroupsCount = WalkingGroup::completed()->count();
        $archivedGroupsCount = WalkingGroup::archived()->count();
        $trashedGroupsCount = WalkingGroup::onlyTrashed()->count();

        $groupsQuery = WalkingGroup::withCount('members')
            ->with(['creatorPerson.parent.parent.parent', 'creatorUser', 'memberships.person.parent.parent.parent'])
            ->orderByDesc('created_at');

        if ($groupStatusFilter === 'trashed') {
            $groupsQuery->onlyTrashed();
        } else {
            $groupsQuery->filterStatus($groupStatusFilter);
        }

        $groups = $groupsQuery->get()
            ->map(function (WalkingGroup $group) {
                // Calculate progress if target steps is specified
                $totalSteps = 0;
                $memberPersonIds = $group->memberships()->where('status', 'accepted')->pluck('person_id')->toArray();
                if (! empty($memberPersonIds)) {
                    $query = WalkingRecord::whereIn('person_id', $memberPersonIds);
                    if ($group->start_date) {
                        $query->where('date', '>=', $group->start_date);
                    }
                    if ($group->end_date) {
                        $query->where('date', '<=', $group->end_date);
                    }
                    $totalSteps = (int) $query->sum('steps');
                }

                $progressPercent = ($group->target_steps && $group->target_steps > 0)
                    ? min(100, round(($totalSteps / $group->target_steps) * 100, 1))
                    : null;

                $group->total_group_steps = $totalSteps;
                $group->progress_percent = $progressPercent;
                return $group;
            });

        // 5️⃣ Filterable Walking Records Ledger (With Quick Presets & Date Filters)
        $recordsQuery = WalkingRecord::with([
            'person.parent.parent.parent.parent.parent',
            'person.devices',
            'user',
        ])
            ->orderByDesc('date')
            ->orderByDesc('id');

        $recordsPreset = (string) $request->query('records_preset', '');
        $singleDate = $request->query('single_date');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        if ($recordsPreset === 'today') {
            $recordsQuery->whereDate('date', $today);
            $dateFrom = $today->format('Y-m-d');
            $dateTo = $today->format('Y-m-d');
        } elseif ($recordsPreset === 'weekly') {
            $recordsQuery->whereBetween('date', [$startOfWeek->format('Y-m-d'), $endOfWeek->format('Y-m-d')]);
            $dateFrom = $startOfWeek->format('Y-m-d');
            $dateTo = $endOfWeek->format('Y-m-d');
        } elseif ($recordsPreset === 'monthly') {
            $recordsQuery->whereBetween('date', [$startOfMonth->format('Y-m-d'), $endOfMonth->format('Y-m-d')]);
            $dateFrom = $startOfMonth->format('Y-m-d');
            $dateTo = $endOfMonth->format('Y-m-d');
        } elseif ($recordsPreset === 'all') {
            $dateFrom = null;
            $dateTo = null;
            $singleDate = null;
        } else {
            if (! empty($singleDate)) {
                $recordsQuery->whereDate('date', $singleDate);
                $dateFrom = $singleDate;
                $dateTo = $singleDate;
                $recordsPreset = 'single';
            } else {
                if ($request->filled('date_from')) {
                    $recordsQuery->whereDate('date', '>=', $request->input('date_from'));
                }
                if ($request->filled('date_to')) {
                    $recordsQuery->whereDate('date', '<=', $request->input('date_to'));
                }
                if ($request->filled('date_from') || $request->filled('date_to')) {
                    $recordsPreset = 'custom';
                } else {
                    $recordsPreset = 'all';
                }
            }
        }

        if ($request->filled('person_id')) {
            $recordsQuery->where('person_id', $request->input('person_id'));
        }
        if ($request->filled('search')) {
            $search = $request->input('search');
            $recordsQuery->where(function ($q) use ($search) {
                $q->whereHas('person', function ($pq) use ($search) {
                    $pq->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                })->orWhereHas('user', function ($uq) use ($search) {
                    $uq->where('name', 'like', "%{$search}%");
                });
            });
        }

        $records = $recordsQuery->paginate(20)->withQueryString()->fragment('tab-records');

        // Selected person for filter if passed
        $selectedPerson = $request->filled('person_id')
            ? Person::withoutGlobalScopes()->with(['parent.parent.parent.parent.parent'])->find($request->input('person_id'))
            : null;

        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        // 6️⃣ Registered Mobile App Devices (Connected Devices & Sync Status)
        $registeredDevices = UserDevice::with(['person.parent.parent.parent.parent.parent'])
            ->orderByDesc('last_active_at')
            ->get()
            ->map(function (UserDevice $device) use ($today) {
                $device->today_steps = 0;
                if ($device->person_id) {
                    $device->today_steps = (int) (WalkingRecord::where('person_id', $device->person_id)
                        ->whereDate('date', $today)
                        ->value('steps') ?? 0);
                }
                return $device;
            });

        $deviceKpis = [
            'total'       => $registeredDevices->count(),
            'ios'         => $registeredDevices->where('os_platform', 'ios')->count(),
            'android'     => $registeredDevices->where('os_platform', 'android')->count(),
            'active_24h'  => $registeredDevices->filter(fn ($d) => $d->last_active_at && $d->last_active_at->diffInHours(now()) <= 24)->count(),
            'with_person' => $registeredDevices->whereNotNull('person_id')->count(),
        ];

        // Active Tab Resolution (Preserve tab context on filter/action/reload)
        $activeTab = 'tab-leaderboard';
        if ($request->filled('active_tab')) {
            $activeTab = $request->input('active_tab');
        } elseif ($request->filled('tab')) {
            $tabMap = [
                'leaderboard' => 'tab-leaderboard',
                'groups'      => 'tab-groups',
                'records'     => 'tab-records',
                'devices'     => 'tab-devices',
                'insights'    => 'tab-insights',
                'clubs'       => 'tab-clubs',
            ];
            $activeTab = $tabMap[$request->input('tab')] ?? 'tab-leaderboard';
        } elseif ($request->has('group_status')) {
            $activeTab = 'tab-groups';
        } elseif ($request->hasAny(['records_preset', 'single_date', 'date_from', 'date_to', 'search', 'page']) || $request->filled('person_id')) {
            $activeTab = 'tab-records';
        } elseif ($request->hasAny(['leaderboard_period', 'leaderboard_date', 'leaderboard_from', 'leaderboard_to', 'leaderboard_gender'])) {
            $activeTab = 'tab-leaderboard';
        }

        $walkingClubs = WalkingClub::query()->orderBy('display_order', 'asc')->orderBy('min_steps', 'asc')->get();

        return view('dashboard.walking.index', [
            'activeTab'           => $activeTab,
            'walkingClubs'        => $walkingClubs,
            'today'               => $today,
            'startOfWeek'         => $startOfWeek,
            'endOfWeek'           => $endOfWeek,
            'startOfMonth'        => $startOfMonth,
            'endOfMonth'          => $endOfMonth,
            'leaderboardGender'   => $leaderboardGender,
            'leaderboardPeriod'   => $leaderboardPeriod,
            'leaderboardDate'     => $leaderboardDate,
            'leaderboardFrom'     => $leaderboardFrom,
            'leaderboardTo'       => $leaderboardTo,
            'isCustomLeaderboard' => $isCustomLeaderboard,
            'customRankings'      => $customRankings,
            'customStats'         => $customStats,
            'customPeriodLabel'   => $customPeriodLabel,
            'dailyRankings'       => $dailyRankings,
            'weeklyRankings'      => $weeklyRankings,
            'monthlyRankings'     => $monthlyRankings,
            'allTimeRankings'     => $allTimeRankings,
            'dailyStats'          => [
                'total_steps'  => $dailyTotalSteps,
                'participants' => $dailyParticipants,
                'average'      => $dailyAvg,
            ],
            'weeklyStats'         => [
                'total_steps'  => $weeklyTotalSteps,
                'participants' => $weeklyParticipants,
                'average'      => $weeklyAvg,
            ],
            'monthlyStats'        => [
                'total_steps'  => $monthlyTotalSteps,
                'participants' => $monthlyParticipants,
                'average'      => $monthlyAvg,
            ],
            'allTimeStats'        => [
                'total_steps'  => $allTimeTotalSteps,
                'participants' => $allTimeParticipants,
                'distance_km'  => round($totalDistanceKm, 1),
                'calories'     => $totalCalories,
            ],
            'smartStats'          => $smartStats,
            'groups'              => $groups,
            'activeGroupsCount'   => $activeGroupsCount,
            'completedGroupsCount'=> $completedGroupsCount,
            'archivedGroupsCount' => $archivedGroupsCount,
            'trashedGroupsCount'  => $trashedGroupsCount,
            'groupStatusFilter'   => $groupStatusFilter,
            'records'             => $records,
            'recordsPreset'       => $recordsPreset,
            'singleDate'          => $singleDate,
            'dateFrom'            => $dateFrom,
            'dateTo'              => $dateTo,
            'selectedPerson'      => $selectedPerson,
            'users'               => $users,
            'registeredDevices'   => $registeredDevices,
            'deviceKpis'          => $deviceKpis,
            'locations'           => Location::orderBy('name')->get(['id', 'name']),
            'familyBranches'      => Person::withoutGlobalScopes()
                ->whereNull('death_date')
                ->where('from_outside_the_family', false)
                ->where(function ($q) {
                    $q->whereNull('parent_id')->orWhere('parent_id', 0);
                })
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name']),
        ]);
    }


    /**
     * Self-heals legacy records where person_id is null but user_id is set.
     * Links them automatically to the appropriate Person from the family tree.
     */
    private function healLegacyUserRecords(): void
    {
        try {
            $unlinkedRecords = WalkingRecord::whereNull('person_id')
                ->whereNotNull('user_id')
                ->get();

            if ($unlinkedRecords->isEmpty()) {
                return;
            }

            $userIds = $unlinkedRecords->pluck('user_id')->unique();
            $users = User::whereIn('id', $userIds)->get()->keyBy('id');

            foreach ($unlinkedRecords as $rec) {
                $user = $users->get($rec->user_id);
                if (! $user) {
                    continue;
                }

                // 1. Direct link via User::person_id
                if (! empty($user->person_id)) {
                    $rec->update(['person_id' => $user->person_id]);
                    continue;
                }

                // 2. Try match Person by phone via PersonContactAccount
                $person = null;
                if (! empty($user->phone)) {
                    $cleanPhone = substr(preg_replace('/[^0-9]/', '', $user->phone), -9);
                    if (strlen($cleanPhone) >= 9) {
                        try {
                            $contact = \App\Models\PersonContactAccount::whereIn('type', ['phone', 'whatsapp'])
                                ->where('value', 'like', "%{$cleanPhone}%")
                                ->first();
                            if ($contact && $contact->person_id) {
                                $person = Person::withoutGlobalScopes()->find($contact->person_id);
                            }
                        } catch (\Throwable $e) {}
                    }
                }

                // 3. Try match Person by name
                if (! $person && ! empty($user->name)) {
                    $trimmedName = trim($user->name);
                    try {
                        $person = Person::withoutGlobalScopes()
                            ->where(function ($q) use ($trimmedName) {
                                $q->where('first_name', 'like', "%{$trimmedName}%")
                                    ->orWhere('last_name', 'like', "%{$trimmedName}%");
                            })->first();
                    } catch (\Throwable $e) {}
                }

                if ($person) {
                    try {
                        $user->update(['person_id' => $person->id]);
                    } catch (\Throwable $e) {}
                    $rec->update(['person_id' => $person->id]);
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('WalkingProgramController healLegacyUserRecords: ' . $e->getMessage());
        }
    }

    /**
     * Compute dense rankings for a given date range.
     * Groups primarily by person_id (if set) or user_id.
     */
    private function getRankingsForRange(Carbon $from, Carbon $to, string $genderFilter = 'all'): Collection
    {
        $eligibilityService = app(\App\Services\Walking\WalkingLeaderboardEligibilityService::class);
        $eligiblePersonIds = $eligibilityService->getEligiblePersonIds();

        // 1. Fetch Person-linked records (restricted to eligible persons)
        $personRows = WalkingRecord::query()
            ->select(
                'person_id',
                DB::raw('SUM(steps) as total_steps'),
                DB::raw('SUM(distance_km) as total_distance'),
                DB::raw('SUM(duration_minutes) as total_duration'),
                DB::raw('SUM(calories) as total_calories'),
                DB::raw('COUNT(id) as days_count')
            )
            ->whereNotNull('person_id')
            ->whereIn('person_id', $eligiblePersonIds)
            ->whereBetween('date', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->groupBy('person_id')
            ->orderByDesc('total_steps')
            ->get();

        $personIds = $personRows->pluck('person_id')->filter()->unique();
        $persons = Person::withoutGlobalScopes()
            ->with(['parent.parent.parent.parent.parent'])
            ->whereIn('id', $personIds)
            ->get()
            ->keyBy('id');

        // 2. Fetch User-only records (legacy or admin entries without person_id)
        $userRows = WalkingRecord::query()
            ->select(
                'user_id',
                DB::raw('SUM(steps) as total_steps'),
                DB::raw('SUM(distance_km) as total_distance'),
                DB::raw('SUM(duration_minutes) as total_duration'),
                DB::raw('SUM(calories) as total_calories'),
                DB::raw('COUNT(id) as days_count')
            )
            ->whereNull('person_id')
            ->whereNotNull('user_id')
            ->whereBetween('date', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->groupBy('user_id')
            ->orderByDesc('total_steps')
            ->get();

        $userIds = $userRows->pluck('user_id')->filter()->unique();
        $users = User::whereIn('id', $userIds)->get()->keyBy('id');

        $combined = collect();

        foreach ($personRows as $row) {
            $person = $persons->get($row->person_id);
            $fullName = $person ? $person->full_name : "شخص #{$row->person_id}";
            $personGender = $person ? ($person->gender ?? 'male') : 'male';

            if ($genderFilter !== 'all' && $personGender !== $genderFilter) {
                continue;
            }

            $combined->push((object) [
                'type'                     => 'person',
                'id'                       => $row->person_id,
                'name'                     => $fullName,
                'full_name'                => $fullName,
                'photo_url'                => $person ? $person->photo_url : null,
                'avatar'                   => $person ? $person->avatar : null,
                'gender'                   => $personGender,
                'walking_leaderboard_mode' => $person ? ($person->walking_leaderboard_mode ?? 'visible') : 'visible',
                'person'                   => $person,
                'user'                     => null,
                'total_steps'              => (int) $row->total_steps,
                'total_distance'           => (float) $row->total_distance,
                'total_duration'           => (int) $row->total_duration,
                'total_calories'           => (int) $row->total_calories,
                'days_count'               => (int) $row->days_count,
            ]);
        }

        foreach ($userRows as $row) {
            $user = $users->get($row->user_id);
            // If the user has a linked person, resolve to that person
            if ($user && $user->person_id) {
                if (! $eligibilityService->isPersonEligible($user->person_id)) {
                    continue;
                }
                $person = Person::withoutGlobalScopes()->with(['parent.parent.parent.parent.parent'])->find($user->person_id);
                if ($person) {
                    $personGender = $person->gender ?? 'male';
                    if ($genderFilter !== 'all' && $personGender !== $genderFilter) {
                        continue;
                    }
                    $combined->push((object) [
                        'type'                     => 'person',
                        'id'                       => $person->id,
                        'name'                     => $person->full_name,
                        'full_name'                => $person->full_name,
                        'photo_url'                => $person->photo_url,
                        'avatar'                   => $person->avatar,
                        'gender'                   => $personGender,
                        'walking_leaderboard_mode' => $person->walking_leaderboard_mode ?? 'visible',
                        'person'                   => $person,
                        'user'                     => $user,
                        'total_steps'              => (int) $row->total_steps,
                        'total_distance'           => (float) $row->total_distance,
                        'total_duration'           => (int) $row->total_duration,
                        'total_calories'           => (int) $row->total_calories,
                        'days_count'               => (int) $row->days_count,
                    ]);
                    continue;
                }
            }

            if ($genderFilter === 'female') {
                continue; // Unlinked user accounts default to male
            }

            $userName = $user ? $user->name : "مستخدم #{$row->user_id}";

            $combined->push((object) [
                'type'           => 'user',
                'id'             => $row->user_id,
                'name'           => $userName,
                'full_name'      => $userName,
                'photo_url'      => null,
                'avatar'         => null,
                'gender'         => 'male',
                'person'         => null,
                'user'           => $user,
                'total_steps'    => (int) $row->total_steps,
                'total_distance' => (float) $row->total_distance,
                'total_duration' => (int) $row->total_duration,
                'total_calories' => (int) $row->total_calories,
                'days_count'     => (int) $row->days_count,
            ]);
        }


        $sorted = $combined->sortByDesc('total_steps')->values();

        // Assign dense ranks
        $rank = 0;
        $prevSteps = null;
        return $sorted->map(function ($entry) use (&$rank, &$prevSteps) {
            if ($entry->total_steps !== $prevSteps) {
                $rank++;
                $prevSteps = $entry->total_steps;
            }
            $entry->rank = $rank;
            return $entry;
        });
    }

    /**
     * Smart analytics calculations.
     */
    private function getSmartStatistics($today, $startOfMonth, $endOfMonth, $startOfLastMonth, $endOfLastMonth, $startOfWeek, $endOfWeek, $startOfLastWeek, $endOfLastWeek): array
    {
        $longestStreak = $this->getLongestStreak();

        $dayNames = [0 => 'الأحد', 1 => 'الإثنين', 2 => 'الثلاثاء', 3 => 'الأربعاء', 4 => 'الخميس', 5 => 'الجمعة', 6 => 'السبت'];
        $dayTotals = [];
        $monthRecords = WalkingRecord::whereBetween('date', [$startOfMonth->format('Y-m-d'), $endOfMonth->format('Y-m-d')])->get();
        foreach ($monthRecords as $rec) {
            $dow = Carbon::parse($rec->date)->dayOfWeek;
            $dayTotals[$dow] = ($dayTotals[$dow] ?? 0) + $rec->steps;
        }
        arsort($dayTotals);
        $mostActiveDayNum = array_key_first($dayTotals);
        $mostActiveDayName = $mostActiveDayNum !== null ? ($dayNames[$mostActiveDayNum] ?? '-') : '-';

        $todayTotal = (int) WalkingRecord::whereDate('date', $today)->sum('steps');
        $goalProgress = self::DAILY_GOAL_STEPS > 0 ? min(100, round(($todayTotal / self::DAILY_GOAL_STEPS) * 100, 1)) : 0;

        $lastWeekTotal = (int) WalkingRecord::whereBetween('date', [$startOfLastWeek->format('Y-m-d'), $endOfLastWeek->format('Y-m-d')])->sum('steps');
        $thisWeekTotal = (int) WalkingRecord::whereBetween('date', [$startOfWeek->format('Y-m-d'), $endOfWeek->format('Y-m-d')])->sum('steps');
        $weeklyChange = $lastWeekTotal > 0 ? round((($thisWeekTotal - $lastWeekTotal) / $lastWeekTotal) * 100, 1) : 0;

        $lastMonthTotal = (int) WalkingRecord::whereBetween('date', [$startOfLastMonth->format('Y-m-d'), $endOfLastMonth->format('Y-m-d')])->sum('steps');
        $thisMonthTotal = (int) WalkingRecord::whereBetween('date', [$startOfMonth->format('Y-m-d'), $endOfMonth->format('Y-m-d')])->sum('steps');
        $monthlyChange = $lastMonthTotal > 0 ? round((($thisMonthTotal - $lastMonthTotal) / $lastMonthTotal) * 100, 1) : 0;

        $improvedWalkers = $this->getMostImprovedWalkers($startOfMonth, $endOfMonth, $startOfLastMonth, $endOfLastMonth);

        return [
            'longest_streak'        => $longestStreak,
            'most_active_day'       => $mostActiveDayName,
            'goal_progress'         => $goalProgress,
            'today_total_steps'     => $todayTotal,
            'weekly_change_percent' => $weeklyChange,
            'monthly_change_percent'=> $monthlyChange,
            'improved_walkers'      => $improvedWalkers,
        ];
    }

    private function getLongestStreak(): int
    {
        $dates = WalkingRecord::select('date')
            ->distinct()
            ->orderBy('date')
            ->pluck('date')
            ->map(fn ($d) => Carbon::parse($d)->format('Y-m-d'))
            ->unique()
            ->values()
            ->toArray();

        if (empty($dates)) {
            return 0;
        }

        $maxStreak = 1;
        $currentStreak = 1;

        for ($i = 1; $i < count($dates); $i++) {
            $prev = Carbon::parse($dates[$i - 1]);
            $curr = Carbon::parse($dates[$i]);
            if ($curr->diffInDays($prev) === 1) {
                $currentStreak++;
            } else {
                $maxStreak = max($maxStreak, $currentStreak);
                $currentStreak = 1;
            }
        }
        return max($maxStreak, $currentStreak);
    }

    private function getMostImprovedWalkers($startOfMonth, $endOfMonth, $startOfLastMonth, $endOfLastMonth): Collection
    {
        $persons = Person::withoutGlobalScopes()
            ->with(['parent.parent.parent.parent.parent'])
            ->whereHas('walkingRecords')
            ->get();

        $improved = [];

        foreach ($persons as $person) {
            $thisMonthAvg = $person->walkingRecords()
                ->whereBetween('date', [$startOfMonth->format('Y-m-d'), $endOfMonth->format('Y-m-d')])
                ->avg('steps') ?? 0;

            $lastMonthAvg = $person->walkingRecords()
                ->whereBetween('date', [$startOfLastMonth->format('Y-m-d'), $endOfLastMonth->format('Y-m-d')])
                ->avg('steps') ?? 0;

            if ($lastMonthAvg > 0 && $thisMonthAvg > $lastMonthAvg) {
                $improvement = round((($thisMonthAvg - $lastMonthAvg) / $lastMonthAvg) * 100, 1);
                $improved[] = (object) [
                    'name'                => $person->full_name,
                    'full_name'           => $person->full_name,
                    'photo_url'           => $person->photo_url,
                    'avatar'              => $person->avatar,
                    'improvement_percent' => $improvement,
                    'this_month_avg'      => (int) round($thisMonthAvg),
                    'last_month_avg'      => (int) round($lastMonthAvg),
                ];
            }
        }

        return collect($improved)->sortByDesc('improvement_percent')->take(5)->values();
    }

    /**
     * Show create walking record page.
     */
    public function create(): View
    {
        $users = User::orderBy('name')->get(['id', 'name', 'email']);
        return view('dashboard.walking.create', compact('users'));
    }

    /**
     * Store walking record.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'person_id'        => 'nullable|exists:persons,id',
            'user_id'          => 'nullable|exists:users,id',
            'date'             => 'required|date|before_or_equal:today',
            'steps'            => 'required|integer|min:0',
            'distance_km'      => 'nullable|numeric|min:0',
            'duration_minutes' => 'nullable|integer|min:0',
            'calories'         => 'nullable|integer|min:0',
            'source'           => 'nullable|string|max:50',
            'notes'            => 'nullable|string|max:1000',
        ], [
            'date.required'  => 'يجب تحديد التاريخ.',
            'steps.required' => 'يجب إدخال عدد الخطوات.',
        ]);

        if (empty($validated['person_id']) && ! empty($validated['user_id'])) {
            $linkedUser = User::find($validated['user_id']);
            if ($linkedUser && $linkedUser->person_id) {
                $validated['person_id'] = $linkedUser->person_id;
            }
        }

        if (empty($validated['person_id']) && empty($validated['user_id'])) {
            return back()->withErrors(['person_id' => 'يجب اختيار شخص من شجرة العائلة أو مستخدم.'])->withInput();
        }

        $steps = (int) $validated['steps'];
        $distanceKm = ! empty($validated['distance_km'])
            ? (float) $validated['distance_km']
            : round($steps * 0.000762, 2);

        $calories = ! empty($validated['calories'])
            ? (int) $validated['calories']
            : (int) round($steps * 0.04);

        if (! empty($validated['person_id'])) {
            WalkingRecord::updateOrCreate(
                [
                    'person_id' => $validated['person_id'],
                    'date'      => $validated['date'],
                ],
                [
                    'user_id'          => $validated['user_id'] ?? null,
                    'steps'            => $steps,
                    'distance_km'      => $distanceKm,
                    'duration_minutes' => $validated['duration_minutes'] ?? 0,
                    'calories'         => $calories,
                    'source'           => $validated['source'] ?? 'dashboard',
                    'notes'            => $validated['notes'] ?? null,
                ]
            );
        } else {
            WalkingRecord::updateOrCreate(
                [
                    'user_id' => $validated['user_id'],
                    'date'    => $validated['date'],
                ],
                [
                    'steps'            => $steps,
                    'distance_km'      => $distanceKm,
                    'duration_minutes' => $validated['duration_minutes'] ?? 0,
                    'calories'         => $calories,
                    'source'           => $validated['source'] ?? 'dashboard',
                    'notes'            => $validated['notes'] ?? null,
                ]
            );
        }

        return redirect()->to(route('dashboard.walking.index', ['active_tab' => 'tab-records']) . '#tab-records')
            ->with('success', 'تم حفظ سجل المشي بنجاح.');
    }

    /**
     * Edit walking record page.
     */
    public function edit(WalkingRecord $walking): View
    {
        $selectedPerson = $walking->person ? $walking->person->loadMissing(['parent.parent.parent.parent.parent']) : null;
        $users = User::orderBy('name')->get(['id', 'name', 'email']);
        return view('dashboard.walking.edit', compact('walking', 'selectedPerson', 'users'));
    }

    /**
     * Update walking record.
     */
    public function update(Request $request, WalkingRecord $walking): RedirectResponse
    {
        $validated = $request->validate([
            'person_id'        => 'nullable|exists:persons,id',
            'user_id'          => 'nullable|exists:users,id',
            'date'             => 'required|date|before_or_equal:today',
            'steps'            => 'required|integer|min:0',
            'distance_km'      => 'nullable|numeric|min:0',
            'duration_minutes' => 'nullable|integer|min:0',
            'calories'         => 'nullable|integer|min:0',
            'notes'            => 'nullable|string|max:1000',
        ]);

        if (empty($validated['person_id']) && ! empty($validated['user_id'])) {
            $linkedUser = User::find($validated['user_id']);
            if ($linkedUser && $linkedUser->person_id) {
                $validated['person_id'] = $linkedUser->person_id;
            }
        }

        $previousSteps = (int) $walking->steps;
        $steps = (int) $validated['steps'];
        $distanceKm = ! empty($validated['distance_km'])
            ? (float) $validated['distance_km']
            : round($steps * 0.000762, 2);

        $calories = ! empty($validated['calories'])
            ? (int) $validated['calories']
            : (int) round($steps * 0.04);

        $targetPersonId = $validated['person_id'] ?? $walking->person_id;
        $targetDate = Carbon::parse($validated['date'])->format('Y-m-d');

        $walking->update([
            'person_id'        => $validated['person_id'] ?? null,
            'user_id'          => $validated['user_id'] ?? null,
            'date'             => $validated['date'],
            'steps'            => $steps,
            'distance_km'      => $distanceKm,
            'duration_minutes' => $validated['duration_minutes'] ?? 0,
            'calories'         => $calories,
            'notes'            => $validated['notes'] ?? null,
        ]);

        // إذا قام الأدمن بتخفيض عدد الخطوات، نسجل إشارة WalkingDailyReset
        // حتى يحترم جوال المستخدم القيمة المخفضة ولا يعيد رفعها من الكاش المحلي القديم.
        if ($steps < $previousSteps && $targetPersonId) {
            \App\Models\WalkingDailyReset::create([
                'date'             => $targetDate,
                'person_id'        => $targetPersonId,
                'scope'            => 'person',
                'reset_by'         => auth()->id(),
                'source'           => 'admin_Correction',
                'reason'           => "تعديل إداري للخطوات من {$previousSteps} إلى {$steps}",
                'affected_records' => 1,
            ]);
        }

        return redirect()->to(route('dashboard.walking.index', ['active_tab' => 'tab-records']) . '#tab-records')
            ->with('success', 'تم تحديث سجل المشي بنجاح.');
    }

    /**
     * Delete walking record (supports AJAX & redirect).
     */
    public function destroy(Request $request, WalkingRecord $walking): JsonResponse|RedirectResponse
    {
        $walking->delete();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'تم حذف سجل المشي بنجاح.',
            ]);
        }

        return redirect()->to(route('dashboard.walking.index', ['active_tab' => 'tab-records']) . '#tab-records')
            ->with('success', 'تم حذف سجل المشي بنجاح.');
    }

    /**
     * Bulk delete walking records via AJAX checkboxes.
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer|exists:walking_records,id',
        ], [
            'ids.required' => 'يجب تحديد سجل واحد على الأقل للحذف.',
            'ids.min'      => 'يجب تحديد سجل واحد على الأقل للحذف.',
        ]);

        $deletedCount = WalkingRecord::whereIn('id', $validated['ids'])->delete();

        return response()->json([
            'success'       => true,
            'deleted_count' => $deletedCount,
            'message'       => "تم حذف {$deletedCount} سجل بنجاح.",
        ]);
    }

    /**
     * Bulk delete walking records by custom date range and optional person.
     */
    public function destroyByRange(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_from' => 'required|date',
            'date_to'   => 'required|date|after_or_equal:date_from',
            'person_id' => 'nullable|exists:persons,id',
        ], [
            'date_from.required'     => 'يجب تحديد تاريخ البداية.',
            'date_to.required'       => 'يجب تحديد تاريخ النهاية.',
            'date_to.after_or_equal' => 'تاريخ النهاية يجب أن يكون مساوياً أو بعد تاريخ البداية.',
        ]);

        $query = WalkingRecord::whereBetween('date', [$validated['date_from'], $validated['date_to']]);

        if (! empty($validated['person_id'])) {
            $query->where('person_id', $validated['person_id']);
        }

        $count = $query->count();
        if ($count === 0) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على أي سجلات مشي في الفترة المحددة.',
            ], 404);
        }

        $query->delete();

        return response()->json([
            'success'       => true,
            'deleted_count' => $count,
            'message'       => "تم حذف {$count} سجل مشي بنجاح في الفترة المحددة.",
        ]);
    }

    /**
     * Reset one person day: delete day records + audit reset row.
     */
    public function resetPersonDay(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'person_id' => 'required|integer|exists:persons,id',
            'date'      => 'nullable|date',
            'reason'    => 'nullable|string|max:255',
        ], [
            'person_id.required' => 'يجب تحديد الشخص.',
            'person_id.exists'   => 'الشخص المحدد غير موجود.',
        ]);

        $date = $validated['date'] ?? Carbon::today('Asia/Riyadh')->toDateString();

        $result = $this->recordService->resetDayForPerson(
            (int) $validated['person_id'],
            $date,
            auth()->id(),
            'manual',
            $validated['reason'] ?? null
        );

        return response()->json([
            'success'  => true,
            'reset_id' => $result['reset_id'],
                        'message'  => "تم تصفير يوم {$date} للشخص بنجاح (حذف {$result['deleted']} سجل).",
        ]);
    }

    /**
     * Reset one day for everyone: delete day records + audit reset row.
     */
    public function resetTodayAll(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date'   => 'nullable|date',
            'reason' => 'nullable|string|max:255',
        ]);

        $date = $validated['date'] ?? Carbon::today('Asia/Riyadh')->toDateString();

        $result = $this->recordService->resetDayForAll(
            $date,
            auth()->id(),
            'manual',
            $validated['reason'] ?? null
        );

        return response()->json([
            'success'  => true,
            'reset_id' => $result['reset_id'],
                        'message'  => "تم تصفير يوم {$date} للجميع بنجاح (حذف {$result['deleted']} سجل).",
        ]);
    }

    /**
     * Get a participant's complete daily steps breakdown for a specific month (1 to 31).
     */
    public function getPersonMonthSteps(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'person_id' => 'nullable|integer|exists:persons,id',
            'user_id'   => 'nullable|integer|exists:users,id',
            'month'     => 'nullable|string|regex:/^\d{4}-\d{2}$/',
        ], [
            'month.regex' => 'صيغة الشهر يجب أن تكون YYYY-MM.',
        ]);

        $personId = ! empty($validated['person_id']) ? (int) $validated['person_id'] : null;
        $userId = ! empty($validated['user_id']) ? (int) $validated['user_id'] : null;

        if (! $personId && ! $userId) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تحديد شخص من شجرة العائلة أو مستخدم.',
            ], 422);
        }

        $personData = null;
        if ($personId) {
            $person = Person::withoutGlobalScopes()
                ->with(['parent.parent.parent.parent.parent'])
                ->find($personId);

            if (! $person) {
                return response()->json(['success' => false, 'message' => 'الشخص غير موجود.'], 404);
            }

            $personData = [
                'id'        => $person->id,
                'type'      => 'person',
                'full_name' => $person->full_name,
                'avatar'    => $person->avatar ?? ($person->photo_url ? asset($person->photo_url) : asset('admin-assets/assets/avatars/default.png')),
            ];
        } else {
            $user = User::find($userId);
            if (! $user) {
                return response()->json(['success' => false, 'message' => 'المستخدم غير موجود.'], 404);
            }

            $personData = [
                'id'        => $user->id,
                'type'      => 'user',
                'full_name' => $user->name,
                'avatar'    => asset('admin-assets/assets/avatars/default.png'),
            ];
        }

        // Resolve target month in KSA timezone
        try {
            $targetMonth = ! empty($validated['month'])
                ? Carbon::createFromFormat('Y-m', $validated['month'], 'Asia/Riyadh')->startOfMonth()
                : Carbon::now('Asia/Riyadh')->startOfMonth();
        } catch (\Throwable $e) {
            $targetMonth = Carbon::now('Asia/Riyadh')->startOfMonth();
        }

        $startOfMonth = $targetMonth->copy()->startOfMonth();
        $endOfMonth = $targetMonth->copy()->endOfMonth();
        $daysInMonth = $targetMonth->daysInMonth;
        $todayStr = Carbon::now('Asia/Riyadh')->format('Y-m-d');

        // Query records for the full month in one efficient indexed query
        $query = WalkingRecord::query()
            ->whereDate('date', '>=', $startOfMonth->format('Y-m-d'))
            ->whereDate('date', '<=', $endOfMonth->format('Y-m-d'));

        if ($personId) {
            $query->where('person_id', $personId);
        } else {
            $query->where('user_id', $userId)->whereNull('person_id');
        }

        $records = $query->get()->keyBy(fn ($r) => Carbon::parse($r->date)->format('Y-m-d'));

        $days = [];
        $monthTotalSteps = 0;
        $monthTotalDistance = 0.0;
        $monthTotalCalories = 0;
        $activeDaysCount = 0;

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dateObj = $targetMonth->copy()->day($day);
            $dateStr = $dateObj->format('Y-m-d');
            $record = $records->get($dateStr);

            $steps = $record ? (int) $record->steps : 0;
            $distance = $record ? (float) $record->distance_km : round($steps * 0.000762, 2);
            $calories = $record ? (int) $record->calories : (int) round($steps * 0.04);

            $monthTotalSteps += $steps;
            $monthTotalDistance += $distance;
            $monthTotalCalories += $calories;
            if ($steps > 0) {
                $activeDaysCount++;
            }

            $days[] = [
                'day'         => $day,
                'date'        => $dateStr,
                'day_name'    => $dateObj->locale('ar')->translatedFormat('l'),
                'short_date'  => $dateObj->locale('ar')->translatedFormat('j M'),
                'steps'       => $steps,
                'formatted'   => number_format($steps),
                'distance_km' => round($distance, 2),
                'calories'    => $calories,
                'has_record'  => (bool) $record,
                'record_id'   => $record?->id,
                'source'      => $record?->source ?? null,
                'is_today'    => ($dateStr === $todayStr),
                'is_future'   => ($dateStr > $todayStr),
                'is_goal'     => ($steps >= self::DAILY_GOAL_STEPS),
            ];
        }

        return response()->json([
            'success'               => true,
            'person'                => $personData,
            'month'                 => $targetMonth->format('Y-m'),
            'month_label'           => $targetMonth->locale('ar')->translatedFormat('F Y'),
            'prev_month'            => $targetMonth->copy()->subMonth()->format('Y-m'),
            'next_month'            => $targetMonth->copy()->addMonth()->format('Y-m'),
            'days_in_month'         => $daysInMonth,
            'month_total_steps'     => $monthTotalSteps,
            'month_total_formatted' => number_format($monthTotalSteps),
            'month_total_km'        => round($monthTotalDistance, 2),
            'month_total_kcal'      => $monthTotalCalories,
            'active_days'           => $activeDaysCount,
            'daily_goal'            => self::DAILY_GOAL_STEPS,
            'days'                  => $days,
        ]);
    }

    /**
     * Update or create a single day's step count for a person via interactive modal.
     */
    public function updatePersonDaySteps(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'person_id' => 'nullable|integer|exists:persons,id',
            'user_id'   => 'nullable|integer|exists:users,id',
            'date'      => 'required|date',
            'steps'     => 'required|integer|min:0',
        ], [
            'date.required'  => 'يجب تحديد التاريخ.',
            'steps.required' => 'يجب إدخال عدد الخطوات.',
            'steps.integer'  => 'عدد الخطوات يجب أن يكون رقماً صحيحاً.',
            'steps.min'      => 'عدد الخطوات لا يمكن أن يكون سالباً.',
        ]);

        $personId = ! empty($validated['person_id']) ? (int) $validated['person_id'] : null;
        $userId = ! empty($validated['user_id']) ? (int) $validated['user_id'] : null;

        if (! $personId && ! $userId) {
            return response()->json([
                'success' => false,
                'message' => 'يجب اختيار شخص أو مستخدم.',
            ], 422);
        }

        $dateStr = Carbon::parse($validated['date'])->format('Y-m-d');
        $steps = (int) $validated['steps'];
        $distanceKm = round($steps * 0.000762, 2);
        $calories = (int) round($steps * 0.04);
        $duration = (int) round($steps / 100);

        // Link user_id if person_id is known
        if ($personId && ! $userId) {
            try {
                $userId = User::where('person_id', $personId)->value('id');
            } catch (\Throwable $e) {}
        }

        // Find existing record
        $recordQuery = WalkingRecord::whereDate('date', $dateStr);
        if ($personId) {
            $recordQuery->where('person_id', $personId);
        } else {
            $recordQuery->where('user_id', $userId)->whereNull('person_id');
        }
        $record = $recordQuery->first();

        $previousSteps = $record ? (int) $record->steps : 0;

        if ($record) {
            $record->update([
                'steps'            => $steps,
                'distance_km'      => $distanceKm,
                'duration_minutes' => $record->duration_minutes ?: $duration,
                'calories'         => $calories,
                'source'           => 'dashboard',
                'notes'            => 'تعديل إداري للخطوات (' . Carbon::now('Asia/Riyadh')->format('Y-m-d H:i') . ')',
            ]);
        } else {
            $record = WalkingRecord::create([
                'person_id'        => $personId,
                'user_id'          => $userId,
                'date'             => $dateStr,
                'steps'            => $steps,
                'distance_km'      => $distanceKm,
                'duration_minutes' => $duration,
                'calories'         => $calories,
                'source'           => 'dashboard',
                'notes'            => 'إضافة إدارية للخطوات (' . Carbon::now('Asia/Riyadh')->format('Y-m-d H:i') . ')',
            ]);
        }

        // إذا خفض الأدمن الخطوات، نسجل إشارة WalkingDailyReset لضمان احترام القيمة المخفضة
        if ($steps < $previousSteps && $personId) {
            try {
                WalkingDailyReset::create([
                    'date'             => $dateStr,
                    'person_id'        => $personId,
                    'scope'            => 'person',
                    'reset_by'         => auth()->id(),
                    'source'           => 'admin_Correction',
                    'reason'           => "تعديل إداري للخطوات من {$previousSteps} إلى {$steps}",
                    'affected_records' => 1,
                ]);
            } catch (\Throwable $e) {
                \Log::warning('WalkingDailyReset failed on step reduction: ' . $e->getMessage());
            }
        }

        // Calculate updated month total for this person
        $targetDateObj = Carbon::parse($dateStr);
        $monthStart = $targetDateObj->copy()->startOfMonth()->format('Y-m-d');
        $monthEnd = $targetDateObj->copy()->endOfMonth()->format('Y-m-d');

        $monthQuery = WalkingRecord::whereDate('date', '>=', $monthStart)->whereDate('date', '<=', $monthEnd);
        if ($personId) {
            $monthQuery->where('person_id', $personId);
        } else {
            $monthQuery->where('user_id', $userId)->whereNull('person_id');
        }
        $monthTotal = (int) $monthQuery->sum('steps');

        return response()->json([
            'success'               => true,
            'message'               => "تم تعديل خطوات يوم {$targetDateObj->locale('ar')->translatedFormat('l j F Y')} إلى " . number_format($steps) . " خطوة بنجاح.",
            'record_id'             => $record->id,
            'person_id'             => $personId,
            'user_id'               => $userId,
            'day'                   => (int) $targetDateObj->format('j'),
            'date'                  => $dateStr,
            'steps'                 => $steps,
            'formatted_steps'       => number_format($steps),
            'distance_km'           => $distanceKm,
            'calories'              => $calories,
            'month_total_steps'     => $monthTotal,
            'month_total_formatted' => number_format($monthTotal),
            'is_goal'               => ($steps >= self::DAILY_GOAL_STEPS),
        ]);
    }

    // ── Groups & Challenges Management ──────────────────────────────────

    /**
     * Preview matching audience based on demographic & engagement filters.
     */
    public function previewAudience(AudienceFilterRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $excludeIds = (array) $request->input('exclude_person_ids', []);

        $result = $this->audienceFilterService->preview($filters, $excludeIds, sampleLimit: 15);

        return response()->json([
            'success'       => true,
            'total_count'   => $result['total_count'],
            'person_ids'    => $result['person_ids'],
            'sample_people' => $result['sample_people'],
            'filters'       => $result['filters'],
        ]);
    }

    /**
     * Store new walking group / challenge with optional bulk audience members.
     */
    public function storeGroup(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'                 => 'required|string|max:150',
            'description'          => 'nullable|string|max:1000',
            'target_steps'         => 'nullable|integer|min:1000|max:100000000',
            'start_date'           => 'nullable|date',
            'end_date'             => 'nullable|date|after_or_equal:start_date',
            'is_public'            => 'nullable|boolean',
            'status'               => 'required|in:active,upcoming,completed,archived',
            'created_by_person_id' => 'nullable|exists:persons,id',
            'member_person_ids'    => 'nullable|array',
            'member_person_ids.*'  => 'exists:persons,id',
            'membership_status'    => 'nullable|in:accepted,invited',
        ]);

        $group = $this->groupService->create(
            data: $validated,
            creatorPersonId: ! empty($validated['created_by_person_id']) ? (int) $validated['created_by_person_id'] : null,
            creatorUserId: auth()->id()
        );

        $membershipStatus = $validated['membership_status'] ?? 'accepted';
        $addedCount = 0;

        if (! empty($validated['member_person_ids'])) {
            $addedCount = $this->groupService->bulkAddMembers(
                $group,
                $validated['member_person_ids'],
                role: 'member',
                status: $membershipStatus,
                shouldNotify: true,
                inviterPersonId: ! empty($validated['created_by_person_id']) ? (int) $validated['created_by_person_id'] : null
            );
        }

        $extraMsg = $addedCount > 0 ? " وتم ضم/دعوة {$addedCount} عضواً وجاري إشعارهم." : "";

        return redirect()->to(route('dashboard.walking.index', ['active_tab' => 'tab-groups', 'group_status' => 'active']) . '#tab-groups')
            ->with('success', "تم إنشاء مجموعة/تحدي \"{$group->name}\" بنجاح برمز دعوة [{$group->invite_code}].{$extraMsg}");
    }

    /**
     * Bulk add or invite persons to an existing walking group.
     */
    public function bulkAddGroupMembers(Request $request, WalkingGroup $group): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'person_ids'   => 'required|array|min:1',
            'person_ids.*' => 'exists:persons,id',
            'role'         => 'nullable|in:member,admin',
            'status'       => 'nullable|in:accepted,invited',
        ]);

        $role = $validated['role'] ?? 'member';
        $status = $validated['status'] ?? 'accepted';

        $count = $this->groupService->bulkAddMembers(
            $group,
            $validated['person_ids'],
            role: $role,
            status: $status,
            shouldNotify: true,
            inviterPersonId: null
        );

        $msg = "تمت إضافة {$count} عضواً إلى المجموعة بنجاح وتوجيه الإشعارات عبر الخلفية.";

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'count'   => $count,
                'message' => $msg,
            ]);
        }

        return back()->with('success', $msg);
    }

    /**
     * Show walking group details JSON / modal.
     */
    public function showGroup(WalkingGroup $group): JsonResponse
    {
        $group->load(['creatorPerson.parent.parent.parent', 'creatorUser', 'memberships.person.parent.parent.parent']);

        $memberPersonIds = $group->memberships()->where('status', 'accepted')->pluck('person_id')->toArray();
        $period = WalkingLeaderboardService::PERIOD_ALLTIME;
        [$defaultFrom, $today] = $this->leaderboardService->resolveDateRange($period);
        $leaderboard = $this->leaderboardService->build(
            period: $period,
            restrictToPersonIds: $memberPersonIds,
            dateRangeOverride: [$group->start_date ?? $defaultFrom, $group->end_date ?? $today],
            groupId: $group->id,
            minSteps: 1,
        );

        return response()->json([
            'success'     => true,
            'group'       => $group,
            'members'     => $group->memberships->map(function ($m) {
                return [
                    'id'        => $m->id,
                    'person_id' => $m->person_id,
                    'name'      => $m->person ? $m->person->full_name : "شخص #{$m->person_id}",
                    'full_name' => $m->person ? $m->person->full_name : "شخص #{$m->person_id}",
                    'photo_url' => $m->person ? $m->person->photo_url : null,
                    'avatar'    => $m->person ? $m->person->avatar : null,
                    'role'      => $m->role,
                    'status'    => $m->status,
                    'joined_at' => $m->joined_at ? $m->joined_at->format('Y-m-d H:i') : null,
                ];
            }),
            'leaderboard' => $leaderboard,
        ]);
    }

    /**
     * Update walking group details.
     */
    public function updateGroup(Request $request, WalkingGroup $group): RedirectResponse
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:150',
            'description'  => 'nullable|string|max:1000',
            'target_steps' => 'nullable|integer|min:1000|max:100000000',
            'start_date'   => 'nullable|date',
            'end_date'     => 'nullable|date|after_or_equal:start_date',
            'is_public'    => 'nullable|boolean',
            'status'       => 'required|in:active,upcoming,completed,archived',
        ]);

        $this->groupService->update($group, $validated);

        return redirect()->to(route('dashboard.walking.index', ['active_tab' => 'tab-groups', 'group_status' => $group->status]) . '#tab-groups')
            ->with('success', 'تم تحديث بيانات المجموعة بنجاح.');
    }

    /**
     * Delete walking group (supports AJAX & redirect).
     */
    public function destroyGroup(Request $request, WalkingGroup $group): JsonResponse|RedirectResponse
    {
        $group->delete();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'تم حذف المجموعة بنجاح.',
            ]);
        }

        return redirect()->to(route('dashboard.walking.index', ['active_tab' => 'tab-groups', 'group_status' => 'active']) . '#tab-groups')
            ->with('success', 'تم حذف المجموعة بنجاح.');
    }

    /**
     * Attach a person to a walking group.
     */
    public function attachGroupMember(Request $request, WalkingGroup $group): RedirectResponse
    {
        $validated = $request->validate([
            'person_id' => 'required|exists:persons,id',
            'role'      => 'nullable|in:admin,member',
        ]);

        $this->groupService->addMember($group, (int) $validated['person_id'], $validated['role'] ?? 'member', shouldNotify: true);

        return back()->with('success', 'تم إضافة العضو للمجموعة بنجاح وإرسال الإشعارات له.');
    }

    /**
     * Remove a person from a walking group (supports AJAX & redirect).
     */
    public function detachGroupMember(Request $request, WalkingGroup $group, Person $person): JsonResponse|RedirectResponse
    {
        $result = $this->groupService->leaveGroup($group, $person->id);
        
        // Notify the person that they were excluded by admin from the walking group
        app(\App\Services\Walking\WalkingGroupNotificationService::class)->notifyMemberRemoved(
            $group,
            $person,
            null,
            'استبعاد بواسطة إدارة البرنامج'
        );

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success'       => true,
                'message'       => 'تم استبعاد العضو من المجموعة بنجاح وإشعاره.',
                'group_deleted' => $result['group_deleted'] ?? false,
            ]);
        }

        if ($result['group_deleted']) {
            return redirect()->to(route('dashboard.walking.index', ['active_tab' => 'tab-groups', 'group_status' => 'active']) . '#tab-groups')
                ->with('success', 'تم استبعاد العضو وإشعاره، وحذف المجموعة لعدم وجود أعضاء آخرين.');
        }

        return back()->with('success', 'تم استبعاد العضو من المجموعة بنجاح وإشعاره.');
    }

    /**
     * Toggle member role between admin and member.
     */
    public function toggleGroupMemberRole(WalkingGroup $group, Person $person): RedirectResponse
    {
        $this->groupService->toggleMemberRole($group, $person->id);
        return back()->with('success', 'تم تغيير صلاحية العضو بنجاح.');
    }

    /**
     * Reactivate an archived walking group.
     */
    public function reactivateGroup(Request $request, WalkingGroup $group): JsonResponse|RedirectResponse
    {
        $newEndDate = $request->input('end_date');
        $this->groupService->reactivate($group, $newEndDate);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'تم إعادة تنشيط المجموعة بنجاح وإشعار الأعضاء.',
            ]);
        }

        return redirect()->to(route('dashboard.walking.index', ['active_tab' => 'tab-groups', 'group_status' => 'active']) . '#tab-groups')
            ->with('success', "تم إعادة تنشيط مجموعة \"{$group->name}\" بنجاح.");
    }

    /**
     * Manually archive a walking group.
     */
    public function archiveGroup(Request $request, WalkingGroup $group): JsonResponse|RedirectResponse
    {
        $reason = $request->input('reason', 'manual_admin');
        $this->groupService->archiveGroup($group, $reason);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'تم أرشفة المجموعة بنجاح.',
            ]);
        }

        return redirect()->to(route('dashboard.walking.index', ['active_tab' => 'tab-groups', 'group_status' => 'archived']) . '#tab-groups')
            ->with('success', "تم أرشفة مجموعة \"{$group->name}\" بنجاح.");
    }

    /**
     * Restore soft-deleted walking group.
     */
    public function restoreGroup(Request $request, int $groupId): JsonResponse|RedirectResponse
    {
        $group = $this->groupService->restoreGroup($groupId);

        if (! $group) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'المجموعة غير موجودة في سلة المحذوفات.'], 404);
            }
            return redirect()->to(route('dashboard.walking.index', ['active_tab' => 'tab-groups', 'group_status' => 'trashed']) . '#tab-groups')->with('error', 'المجموعة غير موجودة.');
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "تم استرجاع مجموعة \"{$group->name}\" بنجاح.",
            ]);
        }

        return redirect()->to(route('dashboard.walking.index', ['active_tab' => 'tab-groups', 'group_status' => 'active']) . '#tab-groups')
            ->with('success', "تم استرجاع مجموعة \"{$group->name}\" بنجاح وإعادتها للتحديات.");
    }

    /**
     * Permanently force-delete a walking group.
     */
    public function forceDeleteGroup(Request $request, int $groupId): JsonResponse|RedirectResponse
    {
        $deleted = $this->groupService->forceDeleteGroup($groupId);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => $deleted,
                'message' => $deleted ? 'تم حذف التحدي نهائياً من قاعدة البيانات.' : 'تعذر الحذف النهائي.',
            ]);
        }

        return redirect()->to(route('dashboard.walking.index', ['active_tab' => 'tab-groups', 'group_status' => 'trashed']) . '#tab-groups')
            ->with('success', 'تم حذف التحدي نهائياً من قاعدة البيانات.');
    }

    /**
     * Bulk soft delete walking groups (move to trash).
     */
    public function bulkDestroyGroups(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer|exists:walking_groups,id',
        ]);

        $count = WalkingGroup::whereIn('id', $validated['ids'])->delete();

        return response()->json([
            'success' => true,
            'count'   => $count,
            'message' => "تم نقل {$count} مجموعة/تحدي إلى سلة المحذوفات بنجاح.",
        ]);
    }

    /**
     * Bulk restore soft-deleted walking groups from trash.
     */
    public function bulkRestoreGroups(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        $count = WalkingGroup::onlyTrashed()->whereIn('id', $validated['ids'])->restore();

        return response()->json([
            'success' => true,
            'count'   => $count,
            'message' => "تم استرجاع {$count} مجموعة/تحدي من سلة المحذوفات بنجاح.",
        ]);
    }

    /**
     * Bulk force delete walking groups permanently from database.
     */
    public function bulkForceDeleteGroups(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        $groups = WalkingGroup::withTrashed()->whereIn('id', $validated['ids'])->get();
        $count = 0;
        foreach ($groups as $group) {
            if ($this->groupService->forceDeleteGroup($group->id)) {
                $count++;
            }
        }

        return response()->json([
            'success' => true,
            'count'   => $count,
            'message' => "تم حذف {$count} مجموعة/تحدي نهائياً من قاعدة البيانات.",
        ]);
    }

    /**
     * Bulk archive walking groups.
     */
    public function bulkArchiveGroups(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer|exists:walking_groups,id',
        ]);

        $groups = WalkingGroup::whereIn('id', $validated['ids'])->get();
        $count = 0;
        foreach ($groups as $group) {
            $this->groupService->archiveGroup($group, 'bulk_manual_admin');
            $count++;
        }

        return response()->json([
            'success' => true,
            'count'   => $count,
            'message' => "تم أرشفة {$count} مجموعة/تحدي بنجاح.",
        ]);
    }

    // =========================================================================
    //  إدارة نوادي المشي والاستمرارية (Walking Clubs Management)
    // =========================================================================

    /**
     * Create a new walking club.
     */
    public function storeClub(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'name'                   => 'required|string|max:120',
            'short_name'             => 'required|string|max:60',
            'sub_label'              => 'nullable|string|max:60',
            'min_steps'              => 'required|integer|min:100|max:100000',
            'max_steps'              => 'nullable|integer|min:100|max:100000|gte:min_steps',
            'calculation_start_date' => 'nullable|date_format:Y-m-d',
            'icon'                   => 'nullable|string|max:30',
            'badge_color'            => 'nullable|string|max:20',
            'badge_dark'             => 'nullable|string|max:20',
            'accent_light'           => 'nullable|string|max:20',
            'accent_dark'            => 'nullable|string|max:20',
            'slug'                   => 'nullable|string|max:60|unique:walking_clubs,slug',
            'is_active'              => 'nullable|boolean',
        ]);

        if (empty($validated['slug'])) {
            $baseSlug = \Illuminate\Support\Str::slug($validated['name'], '_');
            if (empty($baseSlug)) {
                $baseSlug = 'club_' . $validated['min_steps'];
            }
            $slug = $baseSlug;
            $counter = 1;
            while (WalkingClub::where('slug', $slug)->exists()) {
                $slug = "{$baseSlug}_{$counter}";
                $counter++;
            }
            $validated['slug'] = $slug;
        }

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['display_order'] = WalkingClub::max('display_order') + 1;

        $club = WalkingClub::create($validated);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "تم إنشاء نادٍ جديد ({$club->name}) بنجاح.",
                'club'    => $club,
            ]);
        }

        return redirect()->to(route('dashboard.walking.index', ['active_tab' => 'tab-clubs']) . '#tab-clubs')
            ->with('success', "تم إنشاء نادٍ جديد ({$club->name}) بنجاح.");
    }

    /**
     * Update an existing walking club (Name, Steps, Calculation Start Date, Colors, etc.)
     */
    public function updateClub(Request $request, WalkingClub $club): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'name'                   => 'required|string|max:120',
            'short_name'             => 'required|string|max:60',
            'sub_label'              => 'nullable|string|max:60',
            'min_steps'              => 'required|integer|min:100|max:100000',
            'max_steps'              => 'nullable|integer|min:100|max:100000',
            'calculation_start_date' => 'nullable|date_format:Y-m-d',
            'icon'                   => 'nullable|string|max:30',
            'badge_color'            => 'nullable|string|max:20',
            'badge_dark'             => 'nullable|string|max:20',
            'accent_light'           => 'nullable|string|max:20',
            'accent_dark'            => 'nullable|string|max:20',
            'is_active'              => 'nullable|boolean',
        ]);

        if (! empty($validated['max_steps']) && $validated['max_steps'] < $validated['min_steps']) {
            return response()->json([
                'success' => false,
                'message' => 'الحد الأعلى للخطوات يجب أن يكون أكبر من أو يساوي الحد الأدنى.',
            ], 422);
        }

        if ($request->has('is_active')) {
            $validated['is_active'] = $request->boolean('is_active');
        }

        $club->update($validated);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "تم تحديث بيانات نادي ({$club->name}) بنجاح.",
                'club'    => $club,
            ]);
        }

        return redirect()->to(route('dashboard.walking.index', ['active_tab' => 'tab-clubs']) . '#tab-clubs')
            ->with('success', "تم تحديث بيانات نادي ({$club->name}) بنجاح.");
    }

    /**
     * Delete a walking club.
     */
    public function destroyClub(WalkingClub $club): JsonResponse|RedirectResponse
    {
        $totalClubs = WalkingClub::count();
        if ($totalClubs <= 1) {
            if (request()->ajax() || request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكن حذف جميع النوادي، يجب الإبقاء على نادٍ واحد على الأقل.',
                ], 422);
            }

            return redirect()->to(route('dashboard.walking.index', ['active_tab' => 'tab-clubs']) . '#tab-clubs', 303)
                ->with('error', 'لا يمكن حذف جميع النوادي، يجب الإبقاء على نادٍ واحد على الأقل.');
        }

        $name = $club->name;
        $club->delete();

        if (request()->ajax() || request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "تم حذف نادي ({$name}) بنجاح.",
            ]);
        }

        return redirect()->to(route('dashboard.walking.index', ['active_tab' => 'tab-clubs']) . '#tab-clubs', 303)
            ->with('success', "تم حذف نادي ({$name}) بنجاح.");
    }

    /**
     * Toggle active status of a walking club.
     */
    public function toggleClubStatus(WalkingClub $club): JsonResponse
    {
        $club->update(['is_active' => ! $club->is_active]);

        $statusText = $club->is_active ? 'تفعيل' : 'تعطيل';

        return response()->json([
            'success'   => true,
            'is_active' => $club->is_active,
            'message'   => "تم {$statusText} نادي ({$club->name}) بنجاح.",
        ]);
    }

    /**
     * Reorder walking clubs.
     */
    public function reorderClubs(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order'   => 'required|array|min:1',
            'order.*' => 'integer|exists:walking_clubs,id',
        ]);

        foreach ($validated['order'] as $position => $clubId) {
            WalkingClub::where('id', $clubId)->update(['display_order' => $position + 1]);
        }

        WalkingClub::clearCache();

        return response()->json([
            'success' => true,
            'message' => 'تم حفظ ترتيب النوادي بنجاح.',
        ]);
    }
}

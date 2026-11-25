<?php

namespace App\Services\Walking;

use App\Models\Person;
use App\Models\QuizCompetition;
use App\Models\QuizCompetitionWalkingScore;
use App\Models\QuizQuestion;
use App\Models\QuizWinner;
use App\Models\WalkingGroup;
use App\Models\WalkingRecord;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WalkingCompetitionScoringService
{
    public const KSA_TIMEZONE = 'Asia/Riyadh';

    /**
     * عدد المراكز المعروضة على منصة التتويج الهرمية (ثابت لا يتأثر بعدد الفائزين).
     */
    public const PODIUM_TOP_COUNT = 5;

    /**
     * إجمالي الصفوف المعروضة في ترتيب المسابقة (المنصة + جدول المنافسين).
     */
    public const STANDINGS_ROWS_COUNT = 30;

    /**
     * عدد صفوف جدول المنافسين المعروضة تحت منصة التتويج.
     */
    public const TABLE_ROWS_COUNT = self::STANDINGS_ROWS_COUNT - self::PODIUM_TOP_COUNT;

    protected WalkingLeaderboardEligibilityService $eligibilityService;

    public function __construct(?WalkingLeaderboardEligibilityService $eligibilityService = null)
    {
        $this->eligibilityService = $eligibilityService ?? app(WalkingLeaderboardEligibilityService::class);
    }

    /**
     * Get live daily rankings for a specific question on a specific date.
     *
     * @return Collection<int, object>
     */
    public function getLiveDailyStandings(QuizQuestion $question, Carbon $date, ?string $gender = null): Collection
    {
        $settings = $question->getWalkingSettings();
        $dateStr = $date->toDateString();

        // 1. Resolve eligible family member IDs
        $eligiblePersonIds = $this->eligibilityService->getEligiblePersonIds();

        // 2. Filter privacy: Exclude hidden users if requested
        $excludeHidden = $settings['exclude_hidden'] ?? true;
        if ($excludeHidden) {
            $hiddenIds = DB::table('persons')
                ->where('walking_leaderboard_mode', 'hidden')
                ->pluck('id')
                ->all();
            $eligiblePersonIds = array_values(array_diff($eligiblePersonIds, $hiddenIds));
        }

        // 3. Exclude anonymous users if requested
        $excludeAnonymous = !empty($settings['exclude_anonymous']);
        if ($excludeAnonymous) {
            $anonymousIds = DB::table('persons')
                ->where('walking_leaderboard_mode', 'anonymous')
                ->pluck('id')
                ->all();
            $eligiblePersonIds = array_values(array_diff($eligiblePersonIds, $anonymousIds));
        }

        // 4. Gender filter
        $targetGender = $gender ?? ($settings['target_gender'] ?? 'both_segregated');
        if (in_array($targetGender, ['male', 'female'], true)) {
            $eligiblePersonIds = DB::table('persons')
                ->whereIn('id', $eligiblePersonIds)
                ->where('gender', $targetGender)
                ->pluck('id')
                ->all();
        }

        if (empty($eligiblePersonIds)) {
            return collect();
        }

        $minSteps = (int) ($settings['min_daily_steps'] ?? 0);

        // 5. Query daily steps
        $query = WalkingRecord::query()
            ->select('person_id', DB::raw('SUM(steps) as total_steps'))
            ->whereNotNull('person_id')
            ->whereDate('date', $dateStr)
            ->whereIn('person_id', $eligiblePersonIds)
            ->groupBy('person_id')
            ->orderByDesc('total_steps');

        // Note: We do NOT exclude walkers with having >= minSteps in live display
        // so that active participants walking today are immediately visible!
        $results = $query->get();

        // Attach person details
        $persons = Person::withoutGlobalScopes()
            ->whereIn('id', $results->pluck('person_id'))
            ->get()
            ->keyBy('id');

        $dailyPointsMap = $question->getWalkingDailyPoints();

        return $results->map(function ($row, $index) use ($persons, $dailyPointsMap, $targetGender, $minSteps) {
            $rank = $index + 1;
            $person = $persons->get($row->person_id);
            $steps = (int) $row->total_steps;
            $hasMetMinSteps = ($minSteps <= 0 || $steps >= $minSteps);
            $points = $hasMetMinSteps ? ($dailyPointsMap[$rank] ?? 0) : 0;
            $photo = $this->resolvePersonPhotoUrl($person);

            return (object) [
                'person_id' => (int) $row->person_id,
                'name' => $person?->name ?? 'متسابق',
                'photo_url' => $photo,
                'avatar' => $photo,
                'gender' => $person?->gender ?? $targetGender,
                'privacy_mode' => $person?->walking_leaderboard_mode ?? 'visible',
                'steps' => $steps,
                'rank' => $rank,
                'projected_points' => $points,
                'has_met_min_steps' => $hasMetMinSteps,
            ];
        });
    }

    /**
     * Freeze and record daily points at the end of the day (Midnight Rollover).
     */
    public function processDailyScores(QuizQuestion $question, Carbon $date): int
    {
        $settings = $question->getWalkingSettings();
        $targetGender = $settings['target_gender'] ?? 'both_segregated';
        $dailyPointsMap = $question->getWalkingDailyPoints();
        $minSteps = (int) ($settings['min_daily_steps'] ?? 0);
        $dateStr = $date->toDateString();

        $gendersToProcess = match ($targetGender) {
            'male' => ['male'],
            'female' => ['female'],
            'all' => ['all'],
            default => ['male', 'female'],
        };

        $totalRecorded = 0;

        foreach ($gendersToProcess as $genderFilter) {
            $standings = $this->getLiveDailyStandings(
                $question,
                $date,
                $genderFilter === 'all' ? null : $genderFilter
            );

            foreach ($standings as $item) {
                // Award points only if min_daily_steps is reached (or 0)
                $points = ($minSteps > 0 && $item->steps < $minSteps) ? 0 : ($dailyPointsMap[$item->rank] ?? 0);

                QuizCompetitionWalkingScore::updateOrCreate(
                    [
                        'quiz_question_id' => $question->id,
                        'person_id' => $item->person_id,
                        'date' => $dateStr,
                    ],
                    [
                        'steps' => $item->steps,
                        'daily_rank' => $item->rank,
                        'points_earned' => $points,
                        'gender' => $item->gender,
                    ]
                );

                $totalRecorded++;
            }
        }

        return $totalRecorded;
    }

    /**
     * Get cumulative overall standings for the competition period.
     *
     * @return array{standings: Collection<int, object>, viewer_standing: object|null}
     */
    public function getOverallStandings(
        QuizQuestion $question,
        ?string $gender = null,
        ?int $viewerPersonId = null
    ): array {
        $competition = $question->competition;
        if (!$competition) {
            return ['standings' => collect(), 'viewer_standing' => null];
        }

        $settings = $question->getWalkingSettings();
        $scoringMethod = $settings['scoring_method'] ?? 'daily_rank_points';
        $targetGender = $gender ?? ($settings['target_gender'] ?? 'both_segregated');
        $tz = self::KSA_TIMEZONE;
        $today = Carbon::now($tz);

        $excludeHidden = $settings['exclude_hidden'] ?? true;
        $excludeAnonymous = !empty($settings['exclude_anonymous']);

        $disallowedModes = [];
        if ($excludeHidden) {
            $disallowedModes[] = 'hidden';
        }
        if ($excludeAnonymous) {
            $disallowedModes[] = 'anonymous';
        }

        $disallowedPersonIds = [];
        if (!empty($disallowedModes)) {
            $disallowedPersonIds = DB::table('persons')
                ->whereIn('walking_leaderboard_mode', $disallowedModes)
                ->pluck('id')
                ->all();
        }

        // Query manual points adjustments for this walking question and any linked questions
        $relatedQuestionIds = array_filter(array_unique([
            $question->id,
            $question->linked_walking_question_id,
        ]));
        $linkingQuestionIds = DB::table('quiz_questions')
            ->where('linked_walking_question_id', $question->id)
            ->pluck('id')
            ->all();
        $allQuestionIds = array_unique(array_merge($relatedQuestionIds, $linkingQuestionIds));

        $adjustmentsQuery = DB::table('quiz_competition_walking_adjustments')
            ->whereIn('quiz_question_id', $allQuestionIds)
            ->whereNotNull('person_id');

        if (!empty($disallowedPersonIds)) {
            $adjustmentsQuery->whereNotIn('person_id', $disallowedPersonIds);
        }

        if (in_array($targetGender, ['male', 'female'], true)) {
            $adjustmentsQuery->whereExists(function ($sub) use ($targetGender) {
                $sub->select(DB::raw(1))
                    ->from('persons')
                    ->whereColumn('persons.id', 'quiz_competition_walking_adjustments.person_id')
                    ->where(function ($gq) use ($targetGender) {
                        $gq->where('persons.gender', $targetGender)
                            ->orWhereNull('persons.gender')
                            ->orWhere('persons.gender', '');
                    });
            });
        }

        $adjustmentsMap = $adjustmentsQuery
            ->select('person_id', DB::raw('SUM(points) as net_adjustment'))
            ->groupBy('person_id')
            ->pluck('net_adjustment', 'person_id');

        if ($scoringMethod === 'daily_rank_points') {
            // Aggregate recorded daily scores
            $query = QuizCompetitionWalkingScore::query()
                ->select(
                    'person_id',
                    DB::raw('SUM(points_earned) as total_points'),
                    DB::raw('SUM(steps) as total_steps')
                )
                ->where('quiz_question_id', $question->id);

            if (in_array($targetGender, ['male', 'female'], true)) {
                $query->where('gender', $targetGender);
            }

            if (!empty($disallowedPersonIds)) {
                $query->whereNotIn('person_id', $disallowedPersonIds);
            }

            $recordedMap = $query->groupBy('person_id')
                ->get()
                ->keyBy('person_id');

            // Add live today's projected points if within competition range and not yet closed
            $compStart = $competition->start_at ? Carbon::parse($competition->start_at, $tz)->startOfDay() : null;
            $compEnd = $competition->end_at ? Carbon::parse($competition->end_at, $tz)->endOfDay() : null;
            $isTodayRunning = (!$compStart || $today->gte($compStart)) && (!$compEnd || $today->lte($compEnd));

            $mergedRows = collect();

            if ($isTodayRunning) {
                // Check if today is already recorded in table
                $todayRecorded = QuizCompetitionWalkingScore::where('quiz_question_id', $question->id)
                    ->whereDate('date', $today->toDateString())
                    ->exists();

                $todayStandings = !$todayRecorded
                    ? $this->getLiveDailyStandings($question, $today, in_array($targetGender, ['male', 'female'], true) ? $targetGender : null)
                    : collect();

                $todayMap = $todayStandings->keyBy('person_id');

                $allPersonIds = $recordedMap->keys()
                    ->merge($todayMap->keys())
                    ->merge($adjustmentsMap->keys())
                    ->unique();
                if (!empty($disallowedPersonIds)) {
                    $allPersonIds = $allPersonIds->diff($disallowedPersonIds)->values();
                }

                foreach ($allPersonIds as $pid) {
                    $rec = $recordedMap->get($pid);
                    $tod = $todayMap->get($pid);

                    $manualPoints = (int) ($adjustmentsMap->get($pid) ?? 0);
                    $actualPoints = (int) ($rec?->total_points ?? 0);
                    $projectedPoints = (int) ($tod?->projected_points ?? 0);
                    $totPoints = $actualPoints + $manualPoints;
                    $todaySteps = (int) ($tod?->steps ?? 0);
                    $todayRank = (int) ($tod?->rank ?? 0);
                    $recSteps = (int) ($rec?->total_steps ?? 0);
                    $totSteps = $recSteps + $todaySteps;
                    $hasMetMinSteps = $tod ? ($tod->has_met_min_steps ?? true) : true;

                    $mergedRows->push((object) [
                        'person_id' => (int) $pid,
                        'actual_points' => $actualPoints,
                        'manual_points' => $manualPoints,
                        'projected_points' => $projectedPoints,
                        'total_points' => $totPoints,
                        'today_steps' => $todaySteps,
                        'today_rank' => $todayRank,
                        'total_steps' => $totSteps,
                        'has_met_min_steps' => $hasMetMinSteps,
                    ]);
                }
            } else {
                $allPersonIds = $recordedMap->keys()
                    ->merge($adjustmentsMap->keys())
                    ->unique();
                if (!empty($disallowedPersonIds)) {
                    $allPersonIds = $allPersonIds->diff($disallowedPersonIds)->values();
                }

                foreach ($allPersonIds as $pid) {
                    $rec = $recordedMap->get($pid);
                    $manualPoints = (int) ($adjustmentsMap->get($pid) ?? 0);
                    $actualPoints = (int) ($rec?->total_points ?? 0);
                    $recSteps = (int) ($rec?->total_steps ?? 0);
                    $totPoints = $actualPoints + $manualPoints;

                    $mergedRows->push((object) [
                        'person_id' => (int) $pid,
                        'actual_points' => $actualPoints,
                        'manual_points' => $manualPoints,
                        'projected_points' => 0,
                        'total_points' => $totPoints,
                        'today_steps' => 0,
                        'today_rank' => 0,
                        'total_steps' => $recSteps,
                        'has_met_min_steps' => true,
                    ]);
                }
            }

            // Sort:
            // 1. Primary: Highest Total Points (actual + manual + projected)
            // 2. Secondary tie-breaker: actual points earned
            // 3. Tertiary tie-breaker: projected points today
            // 4. Quaternary tie-breaker: today's steps
            // 5. Quinary tie-breaker: cumulative total steps
            $sorted = $mergedRows->sort(function ($a, $b) {
                if ($a->total_points !== $b->total_points) {
                    return $b->total_points <=> $a->total_points;
                }
                if ($a->actual_points !== $b->actual_points) {
                    return $b->actual_points <=> $a->actual_points;
                }
                if ($a->projected_points !== $b->projected_points) {
                    return $b->projected_points <=> $a->projected_points;
                }
                if ($a->today_steps !== $b->today_steps) {
                    return $b->today_steps <=> $a->today_steps;
                }
                return $b->total_steps <=> $a->total_steps;
            })->values();
        } else {
            // Scoring method: cumulative total steps
            $fromDate = $competition->start_at ? Carbon::parse($competition->start_at, $tz)->toDateString() : '2020-01-01';
            $toDate = $competition->end_at ? Carbon::parse($competition->end_at, $tz)->toDateString() : $today->toDateString();

            $eligibleIds = $this->eligibilityService->getEligiblePersonIds();

            if (!empty($disallowedPersonIds)) {
                $eligibleIds = array_values(array_diff($eligibleIds, $disallowedPersonIds));
            }

            if (in_array($targetGender, ['male', 'female'], true)) {
                $eligibleIds = DB::table('persons')->whereIn('id', $eligibleIds)->where('gender', $targetGender)->pluck('id')->all();
            }

            $mergedRows = WalkingRecord::query()
                ->select('person_id', DB::raw('SUM(steps) as total_steps'))
                ->whereNotNull('person_id')
                ->whereBetween('date', [$fromDate, $toDate])
                ->whereIn('person_id', $eligibleIds)
                ->groupBy('person_id')
                ->orderByDesc('total_steps')
                ->get()
                ->map(function ($r) use ($adjustmentsMap) {
                    $pid = (int) $r->person_id;
                    $manual = (int) ($adjustmentsMap->get($pid) ?? 0);
                    return (object) [
                        'person_id' => $pid,
                        'actual_points' => 0,
                        'manual_points' => $manual,
                        'projected_points' => 0,
                        'total_points' => $manual,
                        'today_steps' => 0,
                        'today_rank' => 0,
                        'total_steps' => (int) $r->total_steps,
                        'has_met_min_steps' => true,
                    ];
                });

            // Include any persons who only have manual adjustments but no steps
            $existingPids = $mergedRows->pluck('person_id')->all();
            $missingPids = $adjustmentsMap->keys()->diff($existingPids);
            if (!empty($disallowedPersonIds)) {
                $missingPids = $missingPids->diff($disallowedPersonIds);
            }
            foreach ($missingPids as $mpid) {
                $manual = (int) ($adjustmentsMap->get($mpid) ?? 0);
                $mergedRows->push((object) [
                    'person_id' => (int) $mpid,
                    'actual_points' => 0,
                    'manual_points' => $manual,
                    'projected_points' => 0,
                    'total_points' => $manual,
                    'today_steps' => 0,
                    'today_rank' => 0,
                    'total_steps' => 0,
                    'has_met_min_steps' => true,
                ]);
            }

            $sorted = $mergedRows->sort(function ($a, $b) {
                if ($a->total_points !== $b->total_points) {
                    return $b->total_points <=> $a->total_points;
                }
                return $b->total_steps <=> $a->total_steps;
            })->values();
        }

        // Attach person metadata & apply privacy masking
        $personIds = $sorted->pluck('person_id')->all();
        $persons = Person::withoutGlobalScopes()->whereIn('id', $personIds)->get()->keyBy('id');

        $prizes = $question->prize ?? [];
        $dailyPointsMap = $question->getWalkingDailyPoints();

        $cumulativeStandings = $sorted->map(function ($row, $index) use ($persons, $viewerPersonId, $prizes, $dailyPointsMap) {
            $rank = $index + 1;
            $person = $persons->get($row->person_id);
            $isViewer = ($viewerPersonId && $viewerPersonId === (int) $row->person_id);
            $isAnonymous = ($person?->walking_leaderboard_mode === 'anonymous') && !$isViewer;
            $photo = $isAnonymous ? Person::anonymousAvatarFor($person?->gender) : $this->resolvePersonPhotoUrl($person);

            $prizeTitle = $prizes[$rank - 1] ?? null;

            return (object) [
                'rank' => $rank,
                'person_id' => (int) $row->person_id,
                'name' => $isAnonymous ? 'متسابق متخفي' : ($person?->name ?? 'متسابق'),
                'photo_url' => $photo,
                'avatar' => $photo,
                'gender' => $person?->gender ?? 'male',
                'is_anonymous' => $isAnonymous,
                'is_viewer' => $isViewer,
                'is_current_user' => $isViewer,
                'actual_points' => (int) ($row->total_points ?? 0),
                'manual_points' => (int) ($row->manual_points ?? 0),
                'projected_points' => (int) ($row->projected_points ?? 0),
                'daily_points' => (int) ($row->projected_points ?? 0),
                'total_points' => (int) ($row->total_points ?? 0),
                'actual_score' => (int) ($row->total_points ?? 0),
                'projected_score' => (int) ($row->projected_points ?? 0),
                'total_score' => (int) ($row->total_points ?? 0),
                'points' => (int) ($row->total_points ?? 0),
                'score' => (int) ($row->total_points ?? 0),
                'raw_steps_points' => (int) ($row->actual_points ?? 0),
                'today_steps' => (int) ($row->today_steps ?? 0),
                'today_rank' => (int) ($row->today_rank ?? 0),
                'total_steps' => (int) ($row->total_steps ?? 0),
                'prize' => $prizeTitle,
                'has_met_min_steps' => (bool) ($row->has_met_min_steps ?? true),
            ];
        });

        // Format live today's standings ordered by steps
        $todayStandingsCollection = ($isTodayRunning ?? false) && isset($todayStandings)
            ? $todayStandings
            : $this->getLiveDailyStandings($question, $today, in_array($targetGender, ['male', 'female'], true) ? $targetGender : null);

        $recMap = isset($recordedMap) ? $recordedMap : collect();

        $todayMappedStandings = $todayStandingsCollection->map(function ($row, $index) use ($viewerPersonId, $prizes, $dailyPointsMap, $recMap, $adjustmentsMap) {
            $rank = $index + 1;
            $isViewer = ($viewerPersonId && $viewerPersonId === (int) $row->person_id);
            $isAnonymous = ($row->privacy_mode === 'anonymous') && !$isViewer;
            $photo = $isAnonymous ? Person::anonymousAvatarFor($row->gender) : ($row->avatar ?? $row->photo_url ?? Person::anonymousAvatarFor($row->gender));
            $rec = $recMap->get($row->person_id);
            $pastPoints = (int) ($rec?->total_points ?? 0);
            $manualPoints = (int) ($adjustmentsMap->get($row->person_id) ?? 0);
            $pointsForRank = (int) ($row->projected_points ?? ($dailyPointsMap[$rank] ?? 0));
            $finalTotal = $pastPoints + $manualPoints;

            return (object) [
                'rank' => $rank,
                'person_id' => (int) $row->person_id,
                'name' => $isAnonymous ? 'متسابق متخفي' : $row->name,
                'photo_url' => $photo,
                'avatar' => $photo,
                'gender' => $row->gender,
                'is_anonymous' => $isAnonymous,
                'is_viewer' => $isViewer,
                'is_current_user' => $isViewer,
                'actual_points' => $finalTotal,
                'manual_points' => $manualPoints,
                'projected_points' => $pointsForRank,
                'daily_points' => $pointsForRank,
                'total_points' => $finalTotal,
                'actual_score' => $finalTotal,
                'projected_score' => $pointsForRank,
                'total_score' => $finalTotal,
                'points' => $finalTotal,
                'score' => $finalTotal,
                'raw_steps_points' => $pastPoints,
                'today_steps' => (int) $row->steps,
                'today_rank' => $rank,
                'total_steps' => (int) ($row->steps + ($rec?->total_steps ?? 0)),
                'prize' => $prizes[$rank - 1] ?? null,
                'has_met_min_steps' => $row->has_met_min_steps ?? true,
            ];
        });

        // The primary standings is ALWAYS the official overall leaderboard sorted by points
        $primaryStandings = $cumulativeStandings;

        $viewerStanding = $viewerPersonId ? $primaryStandings->firstWhere('person_id', $viewerPersonId) : null;

        return [
            'standings' => $primaryStandings,
            'today_standings' => $todayMappedStandings,
            'cumulative_standings' => $cumulativeStandings,
            'viewer_standing' => $viewerStanding,
            'winners_count' => max(1, (int) ($question->winners_count ?: 3)),
            'daily_points_map' => $dailyPointsMap,
            'total_participants' => $primaryStandings->count(),
            'total_steps' => (int) $primaryStandings->sum('total_steps'),
            'total_distance_km' => round($primaryStandings->sum('total_steps') * 0.00075, 2),
            'highest_score' => (int) ($primaryStandings->max('total_points') ?? 0),
        ];
    }

    /**
     * Clear caches associated with a walking competition question.
     */
    public function clearCompetitionCaches(QuizQuestion $question): void
    {
        Cache::forget('quiz_winner_json_' . $question->id);
        Cache::forget('quiz-winner-selection-' . $question->id);
        Cache::forget('quiz_question_ended_' . $question->id);
        Cache::forget('quiz_question_data_' . $question->id);
        Cache::forget('api_active_quiz_competition');
        Cache::forget('home_page_data_cache');
        Cache::put('walking_race_ver_' . $question->id, (string) microtime(true), now()->addDays(14));
        if ($question->quiz_competition_id) {
            Cache::forget('quiz_competition_' . $question->quiz_competition_id);
            Cache::forget('quiz_competition_public_' . $question->quiz_competition_id);
        }
        if ($question->linked_walking_question_id) {
            Cache::forget('quiz_winner_json_' . $question->linked_walking_question_id);
            Cache::forget('quiz-winner-selection-' . $question->linked_walking_question_id);
            Cache::forget('quiz_question_ended_' . $question->linked_walking_question_id);
            Cache::forget('quiz_question_data_' . $question->linked_walking_question_id);
        }
    }

    /**
     * Finalize official winners for a walking challenge.
     */
    public function finalizeWinners(QuizQuestion $question): int
    {
        $settings = $question->getWalkingSettings();
        $targetGender = $settings['target_gender'] ?? 'both_segregated';
        $winnersCount = $question->winners_count ?: 3;

        // Delete existing winners for this question
        $question->winners()->delete();

        $gendersToFinalize = match ($targetGender) {
            'male' => ['male'],
            'female' => ['female'],
            default => ['male', 'female'],
        };

        $createdCount = 0;

        foreach ($gendersToFinalize as $gen) {
            $data = $this->getOverallStandings($question, $gen);
            $topList = $data['standings']->take($winnersCount);

            foreach ($topList as $winnerRow) {
                $person = Person::withoutGlobalScopes()->find($winnerRow->person_id);
                $userId = ($person && !empty($person->phone))
                    ? DB::table('users')->where('phone', $person->phone)->value('id')
                    : null;

                QuizWinner::create([
                    'quiz_question_id' => $question->id,
                    'user_id' => $userId,
                    'person_id' => $winnerRow->person_id,
                    'position' => $winnerRow->rank,
                    'score' => $winnerRow->total_points,
                    'steps' => $winnerRow->total_steps,
                    'gender' => $gen,
                ]);

                $createdCount++;
            }
        }

        // Bust caches
        $this->clearCompetitionCaches($question);

        return $createdCount;
    }

    /**
     * Get standing details for a specific viewer person.
     */
    public function getViewerStanding(QuizQuestion $question, int $personId, ?string $gender = null): ?object
    {
        $data = $this->getOverallStandings($question, $gender, $personId);
        return $data['viewer_standing'] ?? null;
    }

    /**
     * Resolve public absolute URL for a person photo or default avatar.
     */
    private function resolvePersonPhotoUrl(?Person $person): ?string
    {
        if (!$person) {
            return Person::anonymousAvatarFor('male');
        }

        return $person->avatar ?: Person::anonymousAvatarFor($person->gender);
    }

    /**
     * Resolve public absolute URL for a group cover image.
     */
    public function resolveGroupCoverUrl(?WalkingGroup $group): string
    {
        if (! $group || empty($group->cover_image)) {
            return asset('assets/img/default-group-cover.png');
        }

        $cover = $group->cover_image;
        if (str_starts_with($cover, 'http://') || str_starts_with($cover, 'https://')) {
            return $cover;
        }

        return asset('storage/' . ltrim($cover, '/'));
    }

    /**
     * Human-readable Arabic label for group scoring mode.
     */
    public function getGroupScoringModeLabel(string $mode): string
    {
        return match ($mode) {
            'total_steps'        => 'إجمالي خطوات المجموعة',
            'average_steps'      => 'متوسط خطوات العضو (الأعلى عدالة)',
            'top_performer'      => 'أفضل متسابق في كل مجموعة (MVP)',
            'daily_group_points' => 'تجميع نقاط المراكز اليومية',
            'member_matchups'    => 'المواجهات المباشرة بين الأعضاء',
            default              => 'إجمالي خطوات المجموعة',
        };
    }

    /**
     * Calculate cumulative overall standings for Group vs Group walking challenges.
     *
     * @return array{
     *     groups_standings: Collection<int, object>,
     *     battle_meta: array,
     *     viewer_group: object|null,
     *     scoring_mode: string,
     *     scoring_mode_label: string
     * }
     */
    public function getOverallGroupStandings(QuizQuestion $question, ?int $viewerPersonId = null): array
    {
        $competition = $question->competition;
        if (! $competition) {
            return [
                'groups_standings'   => collect(),
                'battle_meta'        => [],
                'viewer_group'       => null,
                'scoring_mode'       => 'total_steps',
                'scoring_mode_label' => 'إجمالي خطوات المجموعة',
            ];
        }

        $groupIds = $question->getCompetingGroupIds();
        if (empty($groupIds)) {
            return [
                'groups_standings'   => collect(),
                'battle_meta'        => [],
                'viewer_group'       => null,
                'scoring_mode'       => 'total_steps',
                'scoring_mode_label' => 'إجمالي خطوات المجموعة',
            ];
        }

        $groups = WalkingGroup::whereIn('id', $groupIds)
            ->with(['memberships' => fn ($q) => $q->where('status', 'accepted')])
            ->get()
            ->keyBy('id');

        $scoringMode = $question->getGroupScoringMethod();
        $minMemberSteps = $question->getMinMemberDailySteps();
        $dailyPointsMap = $question->getGroupDailyPoints();
        $settings = $question->getWalkingSettings();
        $targetGender = $settings['target_gender'] ?? 'both_segregated';

        $tz = self::KSA_TIMEZONE;
        $today = Carbon::now($tz)->toDateString();
        $compStart = $competition->start_at ? Carbon::parse($competition->start_at, $tz)->startOfDay() : Carbon::parse('2026-01-01', $tz);
        $compEnd = $competition->end_at ? Carbon::parse($competition->end_at, $tz)->endOfDay() : Carbon::now($tz)->endOfDay();
        $effectiveEnd = Carbon::now($tz)->lt($compEnd) ? Carbon::now($tz) : $compEnd;

        // Collect all accepted member IDs per group
        $groupMembersMap = [];
        $allMemberIds = [];
        foreach ($groups as $gId => $group) {
            $memberIds = $group->memberships->pluck('person_id')->filter()->unique()->values()->all();
            if ($group->created_by_person_id && ! in_array((int) $group->created_by_person_id, $memberIds, true)) {
                $memberIds[] = (int) $group->created_by_person_id;
            }
            $groupMembersMap[$gId] = $memberIds;
            $allMemberIds = array_merge($allMemberIds, $memberIds);
        }
        $allMemberIds = array_values(array_unique($allMemberIds));

        // Gender & Privacy filter
        $excludeHidden = $settings['exclude_hidden'] ?? true;
        $excludeAnonymous = ! empty($settings['exclude_anonymous']);
        $disallowedModes = [];
        if ($excludeHidden) {
            $disallowedModes[] = 'hidden';
        }
        if ($excludeAnonymous) {
            $disallowedModes[] = 'anonymous';
        }

        $personsQuery = Person::withoutGlobalScopes()->whereIn('id', $allMemberIds);
        if (! empty($disallowedModes)) {
            $personsQuery->whereNotIn('walking_leaderboard_mode', $disallowedModes);
        }
        if (in_array($targetGender, ['male', 'female'], true)) {
            $personsQuery->where('gender', $targetGender);
        }
        $validPersons = $personsQuery->get()->keyBy('id');
        $validPersonIds = $validPersons->keys()->all();

        // Filter group members to only eligible persons
        foreach ($groupMembersMap as $gId => $mIds) {
            $groupMembersMap[$gId] = array_values(array_intersect($mIds, $validPersonIds));
        }

        // Fetch walking records within date window
        $recordsQuery = WalkingRecord::query()
            ->select('person_id', 'date', DB::raw('SUM(steps) as daily_steps'))
            ->whereNotNull('person_id')
            ->whereIn('person_id', $validPersonIds)
            ->whereBetween('date', [$compStart->toDateString(), $effectiveEnd->toDateString()])
            ->groupBy('person_id', 'date');

        if ($minMemberSteps > 0) {
            $recordsQuery->havingRaw('SUM(steps) >= ?', [$minMemberSteps]);
        }

        $rawRecords = $recordsQuery->get();

        // Index records by person_id and date
        $personDailySteps = [];
        $personTotalSteps = [];
        $personTodaySteps = [];

        foreach ($rawRecords as $rec) {
            $pid = (int) $rec->person_id;
            $dateStr = $rec->date instanceof Carbon ? $rec->date->toDateString() : (string) $rec->date;
            $steps = (int) $rec->daily_steps;

            $personDailySteps[$pid][$dateStr] = $steps;
            $personTotalSteps[$pid] = ($personTotalSteps[$pid] ?? 0) + $steps;

            if ($dateStr === $today) {
                $personTodaySteps[$pid] = ($personTodaySteps[$pid] ?? 0) + $steps;
            }
        }

        // Calculate stats per group
        $computedGroups = [];
        foreach ($groupIds as $gId) {
            $group = $groups->get($gId);
            if (! $group) {
                continue;
            }

            $mIds = $groupMembersMap[$gId] ?? [];
            $groupTotalSteps = 0;
            $groupTodaySteps = 0;
            $activeMembersCount = 0;
            $membersList = [];

            foreach ($mIds as $pid) {
                $pSteps = $personTotalSteps[$pid] ?? 0;
                $pToday = $personTodaySteps[$pid] ?? 0;
                $person = $validPersons->get($pid);

                if ($pSteps > 0) {
                    $activeMembersCount++;
                }

                $groupTotalSteps += $pSteps;
                $groupTodaySteps += $pToday;

                $membersList[] = (object) [
                    'person_id'  => $pid,
                    'name'       => $person?->name ?? 'متسابق',
                    'photo_url'  => $this->resolvePersonPhotoUrl($person),
                    'steps'      => $pSteps,
                    'today_steps'=> $pToday,
                    'is_viewer'  => ($viewerPersonId && $viewerPersonId === $pid),
                ];
            }

            // Sort members within this group descending by steps
            usort($membersList, fn ($a, $b) => $b->steps <=> $a->steps);

            // Assign intra-group rank
            foreach ($membersList as $idx => $m) {
                $m->intra_rank = $idx + 1;
                $m->contribution_pct = $groupTotalSteps > 0 ? round(($m->steps / $groupTotalSteps) * 100, 1) : 0;
            }

            $topMember = ! empty($membersList) ? $membersList[0] : null;
            $avgSteps = $activeMembersCount > 0 ? (int) round($groupTotalSteps / $activeMembersCount) : 0;

            $computedGroups[$gId] = [
                'group'                => $group,
                'total_steps'          => $groupTotalSteps,
                'today_steps'          => $groupTodaySteps,
                'total_members_count'  => count($mIds),
                'active_members_count' => $activeMembersCount,
                'average_steps'        => $avgSteps,
                'top_member'           => $topMember,
                'members_breakdown'    => array_slice($membersList, 0, 10),
                'all_members'          => $membersList,
                'score'                => 0,
                'daily_points_total'   => 0,
                'matchup_points'       => 0,
            ];
        }

        // Apply scoring mode calculations
        if ($scoringMode === 'total_steps') {
            foreach ($computedGroups as &$cg) {
                $cg['score'] = $cg['total_steps'];
            }
            unset($cg);
        } elseif ($scoringMode === 'average_steps') {
            foreach ($computedGroups as &$cg) {
                $cg['score'] = $cg['average_steps'];
            }
            unset($cg);
        } elseif ($scoringMode === 'top_performer') {
            foreach ($computedGroups as &$cg) {
                $cg['score'] = $cg['top_member'] ? $cg['top_member']->steps : 0;
            }
            unset($cg);
        } elseif ($scoringMode === 'daily_group_points') {
            // Iterate over each date in competition window up to today
            $currDate = $compStart->copy();
            $endDateForDaily = $effectiveEnd->copy()->endOfDay();

            while ($currDate->lte($endDateForDaily)) {
                $dateStr = $currDate->toDateString();
                $dayScores = [];

                foreach ($groupIds as $gId) {
                    if (! isset($computedGroups[$gId])) {
                        continue;
                    }
                    $mIds = $groupMembersMap[$gId] ?? [];
                    $daySteps = 0;
                    foreach ($mIds as $pid) {
                        $daySteps += ($personDailySteps[$pid][$dateStr] ?? 0);
                    }
                    $dayScores[$gId] = $daySteps;
                }

                // Rank groups for this day (only award if day has activity)
                arsort($dayScores);
                $dayRank = 1;
                foreach ($dayScores as $gId => $daySteps) {
                    if ($daySteps > 0 && isset($dailyPointsMap[$dayRank])) {
                        $computedGroups[$gId]['daily_points_total'] += $dailyPointsMap[$dayRank];
                    }
                    $dayRank++;
                }

                $currDate->addDay();
            }

            foreach ($computedGroups as &$cg) {
                $cg['score'] = $cg['daily_points_total'];
            }
            unset($cg);
        } elseif ($scoringMode === 'member_matchups') {
            // Head to head between top pairs of groups
            $gIdsArray = array_values(array_keys($computedGroups));
            if (count($gIdsArray) >= 2) {
                $listA = $computedGroups[$gIdsArray[0]]['all_members'] ?? [];
                $listB = $computedGroups[$gIdsArray[1]]['all_members'] ?? [];
                $maxPairs = max(count($listA), count($listB));

                $winsA = 0;
                $winsB = 0;
                for ($i = 0; $i < $maxPairs; $i++) {
                    $stepsA = isset($listA[$i]) ? $listA[$i]->steps : 0;
                    $stepsB = isset($listB[$i]) ? $listB[$i]->steps : 0;
                    if ($stepsA > $stepsB) {
                        $winsA++;
                    } elseif ($stepsB > $stepsA) {
                        $winsB++;
                    }
                }

                $computedGroups[$gIdsArray[0]]['matchup_points'] = $winsA;
                $computedGroups[$gIdsArray[0]]['score'] = $winsA;

                $computedGroups[$gIdsArray[1]]['matchup_points'] = $winsB;
                $computedGroups[$gIdsArray[1]]['score'] = $winsB;
            }
        }

        // Sort groups by score DESC, then total_steps DESC
        uasort($computedGroups, function ($a, $b) {
            if ($b['score'] !== $a['score']) {
                return $b['score'] <=> $a['score'];
            }
            return $b['total_steps'] <=> $a['total_steps'];
        });

        // Format final standings collection
        $standingsList = collect();
        $rankCounter = 1;
        $viewerGroupInfo = null;

        foreach ($computedGroups as $gId => $cg) {
            $group = $cg['group'];
            $isLeader = ($rankCounter === 1);

            $standingObj = (object) [
                'rank'                 => $rankCounter,
                'group_id'             => (int) $group->id,
                'name'                 => $group->name,
                'description'          => $group->description,
                'cover_image'          => $this->resolveGroupCoverUrl($group),
                'total_steps'          => (int) $cg['total_steps'],
                'today_steps'          => (int) $cg['today_steps'],
                'average_steps'        => (int) $cg['average_steps'],
                'total_members_count'  => (int) $cg['total_members_count'],
                'active_members_count' => (int) $cg['active_members_count'],
                'score'                => (int) $cg['score'],
                'is_leading'           => $isLeader,
                'top_member'           => $cg['top_member'],
                'members_breakdown'    => $cg['members_breakdown'],
                'invite_code'          => $group->invite_code,
            ];

            // Check if viewer belongs to this group
            if ($viewerPersonId && in_array($viewerPersonId, $groupMembersMap[$gId] ?? [], true)) {
                $viewerMember = collect($cg['all_members'])->firstWhere('person_id', $viewerPersonId);
                $viewerGroupInfo = (object) [
                    'group_id'           => (int) $group->id,
                    'group_name'         => $group->name,
                    'group_rank'         => $rankCounter,
                    'user_steps'         => $viewerMember ? (int) $viewerMember->steps : 0,
                    'user_today_steps'   => $viewerMember ? (int) $viewerMember->today_steps : 0,
                    'user_rank_in_group' => $viewerMember ? (int) $viewerMember->intra_rank : 1,
                    'contribution_pct'   => $viewerMember ? (float) $viewerMember->contribution_pct : 0.0,
                ];
            }

            $standingsList->push($standingObj);
            $rankCounter++;
        }

        // Calculate Battle Metadata (Tug of War split)
        $battleMeta = [];
        if ($standingsList->count() === 2) {
            $g1 = $standingsList[0];
            $g2 = $standingsList[1];
            $val1 = max(0, (int) $g1->score);
            $val2 = max(0, (int) $g2->score);
            $sum = $val1 + $val2;

            if ($sum > 0) {
                $pct1 = (int) round(($val1 / $sum) * 100);
                $pct2 = 100 - $pct1;
            } else {
                $pct1 = 50;
                $pct2 = 50;
            }

            $diff = abs($val1 - $val2);
            $leadUnit = ($scoringMode === 'daily_group_points' || $scoringMode === 'member_matchups') ? 'نقطة' : 'خطوة';

            $battleMeta = [
                'is_head_to_head'    => true,
                'group_a_id'         => $g1->group_id,
                'group_a_pct'        => $pct1,
                'group_b_id'         => $g2->group_id,
                'group_b_pct'        => $pct2,
                'leader_group_id'    => $val1 >= $val2 ? $g1->group_id : $g2->group_id,
                'leader_group_name'  => $val1 >= $val2 ? $g1->name : $g2->name,
                'lead_difference'    => $diff,
                'lead_text'          => $diff === 0 ? 'تعادل كامل بين الفريقين!' : "{$g1->name} يتقدم بفارق " . number_format($diff) . " {$leadUnit}",
            ];
        } elseif ($standingsList->count() > 2) {
            $g1 = $standingsList[0];
            $g2 = $standingsList[1];
            $leadDiff = max(0, $g1->score - $g2->score);
            $leadUnit = ($scoringMode === 'daily_group_points' || $scoringMode === 'member_matchups') ? 'نقطة' : 'خطوة';

            $battleMeta = [
                'is_head_to_head'   => false,
                'leader_group_id'   => $g1->group_id,
                'leader_group_name' => $g1->name,
                'lead_difference'   => $leadDiff,
                'lead_text'         => $leadDiff === 0 ? 'تقارب شديد وتنافس على الصدارة!' : "{$g1->name} يتصدر بفارق " . number_format($leadDiff) . " {$leadUnit} عن أقرب منافسيه",
            ];
        }

        return [
            'groups_standings'   => $standingsList,
            'battle_meta'        => $battleMeta,
            'viewer_group'       => $viewerGroupInfo,
            'scoring_mode'       => $scoringMode,
            'scoring_mode_label' => $this->getGroupScoringModeLabel($scoringMode),
        ];
    }

    /**
     * Process daily scores for group competitions during midnight rollover.
     */
    public function processDailyGroupScores(QuizQuestion $question, Carbon $date): int
    {
        // Re-generate group standings to warm caches and ensure all counts are fresh
        $standings = $this->getOverallGroupStandings($question);

        // Bust API and web caches
        Cache::forget('api_active_quiz_competition');
        Cache::forget('home_page_data_cache');

        return $standings['groups_standings']->count();
    }

    /**
     * Strictly determine whether the viewer is allowed to see female walking leaderboards.
     */
    public function canViewFemaleLeaderboard(?Person $viewer): bool
    {
        return $viewer !== null && $viewer->gender === 'female';
    }

    /**
     * Build day-by-day animated Bar Chart Race timeline data for a walking challenge.
     *
     * @return array<string, mixed>
     */
    public function getDailyRaceTimeline(
        QuizCompetition $competition,
        QuizQuestion $question,
        ?Person $viewer = null,
        ?int $maxRanks = null,
        bool $personalizeViewer = true,
    ): array {
        $config = $question->getWalkingRaceConfig();
        if ($maxRanks !== null) {
            $config['max_ranks'] = $maxRanks;
        }
        $targetGender = $question->getWalkingTargetGender();
        $canViewFemale = $this->canViewFemaleLeaderboard($viewer);
        $isGroupScoped = $question->isGroupWalkingChallenge();

        // Enforce female leaderboard privacy for female-only competitions
        if ($targetGender === 'female' && ! $canViewFemale) {
            return [
                'config' => $config,
                'competition_scope' => $isGroupScoped ? 'groups' : 'individuals',
                'target_gender' => $targetGender,
                'can_view_female' => false,
                'access_denied' => true,
                'default_section' => null,
                'total_competition_days' => 0,
                'elapsed_days_count' => 0,
                'sections' => [],
            ];
        }

        $tz = self::KSA_TIMEZONE;
        $nowKsa = Carbon::now($tz)->startOfDay();
        $compStart = ($competition->start_at ?? $competition->created_at ?? $nowKsa)
            ->copy()
            ->setTimezone($tz)
            ->startOfDay();
        $compEnd = $competition->end_at
            ? $competition->end_at->copy()->setTimezone($tz)->startOfDay()
            : null;

        // If competition hasn't started yet
        if ($compStart->gt($nowKsa)) {
            $plannedDays = ($compEnd && $compEnd->gte($compStart))
                ? min(90, $compStart->diffInDays($compEnd) + 1)
                : 1;

            return [
                'config' => $config,
                'competition_scope' => $isGroupScoped ? 'groups' : 'individuals',
                'target_gender' => $targetGender,
                'can_view_female' => $canViewFemale,
                'access_denied' => false,
                'default_section' => $isGroupScoped ? 'groups' : ($targetGender === 'all' ? 'all' : 'male'),
                'total_competition_days' => $plannedDays,
                'elapsed_days_count' => 0,
                'sections' => [],
            ];
        }

        $timelineEnd = ($compEnd && $compEnd->lt($nowKsa)) ? $compEnd->copy() : $nowKsa->copy();
        // Limit displayed frames while still counting activity from the start of the competition.
        $firstFrameDate = $compStart->max($timelineEnd->copy()->subDays(89));

        $totalCompetitionDays = ($compEnd && $compEnd->gte($compStart))
            ? min(90, $compStart->diffInDays($compEnd) + 1)
            : ($firstFrameDate->diffInDays($timelineEnd) + 1);

        $cacheVer = Cache::get('walking_race_ver_' . $question->id, '1');
        $accessKey = $canViewFemale ? 'f' : 'm';
        $configHash = md5(json_encode([
            $config,
            $question->getWalkingSettings(),
            $compStart->toDateString(),
            $timelineEnd->toDateString(),
            $totalCompetitionDays,
        ]));
        $cacheKey = "walking_race_timeline_v2_q{$question->id}_{$accessKey}_{$cacheVer}_{$configHash}";

        $payload = Cache::remember($cacheKey, now()->addMinutes(2), function () use (
            $question,
            $config,
            $targetGender,
            $canViewFemale,
            $isGroupScoped,
            $compStart,
            $firstFrameDate,
            $timelineEnd,
            $totalCompetitionDays
        ) {
            $palette = self::raceBarPalette();
            $sections = [];

            if ($isGroupScoped) {
                $groupDays = $this->buildGroupRaceDays($question, $compStart, $firstFrameDate, $timelineEnd, $config, $palette);
                $sections['groups'] = [
                    'label' => 'ترتيب المجموعات',
                    'days' => $groupDays,
                ];
                $defaultSection = 'groups';
            } else {
                if ($targetGender === 'both_segregated') {
                    $sections['male'] = [
                        'label' => 'ترتيب الرجال',
                        'days' => $this->buildIndividualRaceDays($question, 'male', $compStart, $firstFrameDate, $timelineEnd, $config, $palette),
                    ];
                    if ($canViewFemale) {
                        $sections['female'] = [
                            'label' => 'ترتيب النساء',
                            'days' => $this->buildIndividualRaceDays($question, 'female', $compStart, $firstFrameDate, $timelineEnd, $config, $palette),
                        ];
                    }
                    $defaultSection = $canViewFemale ? 'female' : 'male';
                } elseif ($targetGender === 'male') {
                    $sections['male'] = [
                        'label' => 'ترتيب الرجال',
                        'days' => $this->buildIndividualRaceDays($question, 'male', $compStart, $firstFrameDate, $timelineEnd, $config, $palette),
                    ];
                    $defaultSection = 'male';
                } elseif ($targetGender === 'female') {
                    $sections['female'] = [
                        'label' => 'ترتيب النساء',
                        'days' => $this->buildIndividualRaceDays($question, 'female', $compStart, $firstFrameDate, $timelineEnd, $config, $palette),
                    ];
                    $defaultSection = 'female';
                } else {
                    $sections['all'] = [
                        'label' => 'الترتيب العام',
                        'days' => $this->buildIndividualRaceDays($question, null, $compStart, $firstFrameDate, $timelineEnd, $config, $palette),
                    ];
                    $defaultSection = 'all';
                }
            }

            $elapsedCount = 0;
            if (isset($sections[$defaultSection]['days'])) {
                $elapsedCount = count($sections[$defaultSection]['days']);
            }

            return [
                'config' => $config,
                'competition_scope' => $isGroupScoped ? 'groups' : 'individuals',
                'target_gender' => $targetGender,
                'can_view_female' => $canViewFemale,
                'access_denied' => false,
                'default_section' => $defaultSection,
                'total_competition_days' => max($elapsedCount, $totalCompetitionDays),
                'elapsed_days_count' => $elapsedCount,
                'sections' => $sections,
            ];
        });

        // Personalize viewer visibility if viewer is anonymous in their own race view
        if ($personalizeViewer && $viewer && ! empty($payload['sections'])) {
            $viewerId = (int) $viewer->id;
            foreach ($payload['sections'] as &$sec) {
                foreach ($sec['days'] as &$day) {
                    foreach ($day['top_entries'] as &$entry) {
                        $pid = (int) ($entry['person_id'] ?? 0);
                        $entry['is_viewer'] = ($pid > 0 && $pid === $viewerId);
                        if ($entry['is_viewer'] && ! empty($entry['is_anonymous'])) {
                            $entry['name'] = $viewer->full_name_with_relations ?: ($viewer->name ?: 'أنت');
                            $entry['avatar'] = $this->resolvePersonPhotoUrl($viewer);
                        }
                    }
                }
            }
            unset($sec, $day, $entry);
        }

        return $payload;
    }

    /**
     * Palette of distinct saturated bar colors matching the reference video.
     *
     * @return array<int, string>
     */
    private static function raceBarPalette(): array
    {
        return [
            '#2176c7', // Royal Blue
            '#8c2d19', // Deep Burgundy / Maroon
            '#5c6f84', // Slate Steel Blue
            '#e05a1b', // Vivid Orange
            '#9c6b30', // Warm Ochre Brown
            '#b86b77', // Dusty Mauve Rose
            '#8c4a8c', // Muted Plum Purple
            '#3b8249', // Forest Green
            '#c99700', // Mustard Gold
            '#4f5d95', // Indigo Navy
            '#0f766e', // Deep Teal
            '#b91c1c', // Crimson Red
            '#6d28d9', // Violet
            '#b45309', // Copper Amber
            '#1d4ed8', // Cobalt Blue
            '#047857', // Emerald
            '#be185d', // Berry Pink
            '#374151', // Charcoal Slate
        ];
    }

    /**
     * Award points by step rank, preserving the input order when steps are tied.
     *
     * @param  array<int, int>  $stepsById
     * @param  array<int, int>  $pointsByRank
     * @return array<int, int>
     */
    private function awardRaceDailyPoints(array $stepsById, array $pointsByRank, int $minSteps = 0): array
    {
        arsort($stepsById, SORT_NUMERIC);
        $pointsById = [];
        $rank = 1;

        foreach ($stepsById as $id => $steps) {
            $pointsById[$id] = $steps > 0 && $steps >= $minSteps ? ($pointsByRank[$rank] ?? 0) : 0;
            $rank++;
        }

        return $pointsById;
    }

    /**
     * Build day-by-day frames for individual participants in one gender section.
     *
     * @param  array<string, mixed>  $config
     * @param  array<int, string>  $palette
     * @return array<int, array<string, mixed>>
     */
    private function buildIndividualRaceDays(
        QuizQuestion $question,
        ?string $sectionGender,
        Carbon $startDate,
        Carbon $firstFrameDate,
        Carbon $endDate,
        array $config,
        array $palette
    ): array {
        $settings = $question->getWalkingSettings();
        $metric = $config['metric'] ?? 'steps';
        $showPoints = $metric === 'points';
        $tieBreaker = $showPoints ? 'steps' : 'daily_steps';
        $maxRanks = (int) ($config['max_ranks'] ?? 10);
        $dailyPointsMap = $showPoints ? $question->getWalkingDailyPoints() : [];
        $minDailySteps = (int) ($settings['min_daily_steps'] ?? 0);

        $eligibleIds = $this->eligibilityService->getEligiblePersonIds();

        $excludeHidden = $settings['exclude_hidden'] ?? true;
        if ($excludeHidden) {
            $hiddenIds = DB::table('persons')
                ->where('walking_leaderboard_mode', 'hidden')
                ->pluck('id')
                ->all();
            $eligibleIds = array_values(array_diff($eligibleIds, $hiddenIds));
        }

        $excludeAnonymous = ! empty($settings['exclude_anonymous']);
        if ($excludeAnonymous) {
            $anonIds = DB::table('persons')
                ->where('walking_leaderboard_mode', 'anonymous')
                ->pluck('id')
                ->all();
            $eligibleIds = array_values(array_diff($eligibleIds, $anonIds));
        }

        if (in_array($sectionGender, ['male', 'female'], true)) {
            $eligibleIds = DB::table('persons')
                ->whereIn('id', $eligibleIds)
                ->where('gender', $sectionGender)
                ->pluck('id')
                ->all();
        }

        if (empty($eligibleIds)) {
            return [];
        }

        // Single batch aggregation query across the full competition window (zero N+1)
        $records = WalkingRecord::query()
            ->select('person_id', DB::raw('DATE(date) as step_date'), DB::raw('SUM(steps) as daily_steps'))
            ->whereNotNull('person_id')
            ->whereIn('person_id', $eligibleIds)
            ->whereDate('date', '>=', $startDate->toDateString())
            ->whereDate('date', '<=', $endDate->toDateString())
            ->groupBy('person_id', DB::raw('DATE(date)'))
            ->get();

        // Map steps by [dateStr][personId] => int
        $dailyByDate = [];
        foreach ($records as $rec) {
            $pid = (int) $rec->person_id;
            $dStr = substr((string) $rec->step_date, 0, 10);
            $steps = (int) $rec->daily_steps;
            if ($steps <= 0) {
                continue;
            }
            $dailyByDate[$dStr][$pid] = ($dailyByDate[$dStr][$pid] ?? 0) + $steps;
        }

        $today = Carbon::today(self::KSA_TIMEZONE)->toDateString();
        $recordedSteps = [];
        $recordedPoints = [];
        if ($showPoints) {
            $scores = QuizCompetitionWalkingScore::query()
                ->where('quiz_question_id', $question->id)
                ->whereIn('person_id', $eligibleIds)
                ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
                ->where('date', '<', $today)
                ->get(['person_id', 'date', 'steps', 'points_earned']);

            foreach ($scores as $score) {
                $date = $score->date->toDateString();
                $recordedSteps[$date][$score->person_id] = $score->steps;
                $recordedPoints[$date][$score->person_id] = $score->points_earned;
            }
        }

        if (empty($dailyByDate) && empty($recordedSteps)) {
            return [];
        }

        // Compute ordered day frames in pure integer arrays first to identify appearing contestants
        $runningSteps = [];
        $runningPoints = [];
        $rawDays = [];
        $appearingPersonIds = [];

        $cursor = $startDate->copy();
        $dayNumber = 1;

        while ($cursor->lte($endDate)) {
            $dStr = $cursor->toDateString();
            $todayMap = $recordedSteps[$dStr] ?? $dailyByDate[$dStr] ?? [];
            $previousSteps = $runningSteps;
            $previousPoints = $runningPoints;

            foreach ($todayMap as $pid => $steps) {
                $runningSteps[$pid] = ($runningSteps[$pid] ?? 0) + $steps;
            }

            if ($showPoints) {
                ksort($todayMap, SORT_NUMERIC);
                if ($dStr === $today && $todayMap !== []) {
                    $dailyPoints = $this->getLiveDailyStandings($question, $cursor, $sectionGender)
                        ->pluck('projected_points', 'person_id')->all();
                } else {
                    $dailyPoints = $recordedPoints[$dStr] ?? $this->awardRaceDailyPoints($todayMap, $dailyPointsMap, $minDailySteps);
                }
                foreach ($dailyPoints as $pid => $points) {
                    $runningPoints[$pid] = ($runningPoints[$pid] ?? 0) + $points;
                }
            }

            if ($cursor->lt($firstFrameDate)) {
                $cursor->addDay();
                continue;
            }

            $dayCandidates = [];
            foreach ($runningSteps as $pid => $steps) {
                if ($steps <= 0 && ($runningPoints[$pid] ?? 0) <= 0) {
                    continue;
                }
                $entry = [
                    'person_id' => (int) $pid,
                    'steps' => $steps,
                    'prev_steps' => $previousSteps[$pid] ?? 0,
                    'daily_steps' => $todayMap[$pid] ?? 0,
                ];
                if ($showPoints) {
                    $entry['points'] = $runningPoints[$pid] ?? 0;
                    $entry['prev_points'] = $previousPoints[$pid] ?? 0;
                }
                $dayCandidates[] = $entry;
            }

            usort($dayCandidates, static function ($a, $b) use ($metric, $tieBreaker) {
                if ($b[$metric] !== $a[$metric]) {
                    return $b[$metric] <=> $a[$metric];
                }
                if ($b[$tieBreaker] !== $a[$tieBreaker]) {
                    return $b[$tieBreaker] <=> $a[$tieBreaker];
                }

                return $a['person_id'] <=> $b['person_id'];
            });

            $sliceLimit = $maxRanks > 0 ? $maxRanks : 500;
            $topSlice = array_slice($dayCandidates, 0, $sliceLimit);
            foreach ($topSlice as $item) {
                $appearingPersonIds[$item['person_id']] = true;
            }

            $rawDays[] = [
                'day_index' => $dayNumber - 1,
                'day_number' => $dayNumber,
                'date' => $dStr,
                'date_label' => $this->formatArabicDayMonth($cursor),
                'entries' => $topSlice,
            ];

            $cursor->addDay();
            $dayNumber++;
        }

        if (empty($appearingPersonIds)) {
            return [];
        }

        // Trim leading empty days only if NO one had any steps on day 1+ (keep from first active day onwards if needed,
        // otherwise keep full competition span matching timeline dots)
        $firstActiveIdx = null;
        foreach ($rawDays as $idx => $rd) {
            if (! empty($rd['entries'])) {
                $firstActiveIdx = $idx;
                break;
            }
        }
        if ($firstActiveIdx === null) {
            return [];
        }

        // Load only contestants that actually appear in Top N across the competition (with ancestors for lineage name)
        $persons = Person::withoutGlobalScopes()
            ->with(['parent.parent.parent.parent.parent'])
            ->whereIn('id', array_keys($appearingPersonIds))
            ->get()
            ->keyBy('id');

        // Assign stable distinct colors sequentially by first appearance in the timeline
        $colorMap = [];
        $colorIdx = 0;
        $paletteCount = count($palette);

        $formattedDays = [];
        foreach ($rawDays as $rd) {
            $formattedEntries = [];

            foreach ($rd['entries'] as $rankIdx => $entry) {
                $pid = $entry['person_id'];
                if (! isset($colorMap[$pid])) {
                    $colorMap[$pid] = $palette[$colorIdx % $paletteCount];
                    $colorIdx++;
                }

                $person = $persons->get($pid);
                $isAnon = $person && ($person->walking_leaderboard_mode === 'anonymous');
                $displayName = $isAnon
                    ? 'متسابق متخفي'
                    : ($person?->full_name_with_relations ?: ($person?->name ?? 'متسابق'));
                $avatar = $isAnon
                    ? Person::anonymousAvatarFor($person?->gender)
                    : $this->resolvePersonPhotoUrl($person);

                $formattedEntries[] = $entry + [
                    'entry_id' => 'p_' . $pid,
                    'rank' => $rankIdx + 1,
                    'name' => $displayName,
                    'avatar' => $avatar,
                    'color' => $colorMap[$pid],
                    'is_anonymous' => $isAnon,
                    'is_viewer' => false,
                ];
            }

            $day = [
                'day_index' => $rd['day_index'],
                'day_number' => $rd['day_number'],
                'date' => $rd['date'],
                'date_label' => $rd['date_label'],
                'max_steps' => max([1, ...array_column($formattedEntries, 'steps')]),
                'top_entries' => $formattedEntries,
            ];
            if ($showPoints) {
                $day['max_points'] = max(1, $formattedEntries[0]['points'] ?? 0);
            }
            $formattedDays[] = $day;
        }

        return $formattedDays;
    }

    /**
     * Build day-by-day frames when competition scope is Group vs Group.
     *
     * @param  array<string, mixed>  $config
     * @param  array<int, string>  $palette
     * @return array<int, array<string, mixed>>
     */
    private function buildGroupRaceDays(
        QuizQuestion $question,
        Carbon $startDate,
        Carbon $firstFrameDate,
        Carbon $endDate,
        array $config,
        array $palette
    ): array {
        $groupIds = $question->getCompetingGroupIds();
        if (empty($groupIds)) {
            return [];
        }

        $metric = $config['metric'] ?? 'steps';
        $showPoints = $metric === 'points';
        $maxRanks = (int) ($config['max_ranks'] ?? 10);
        $dailyPointsMap = $showPoints ? $question->getGroupDailyPoints() : [];
        $groupSlots = array_fill_keys($groupIds, 0);
        $minMemberSteps = $question->getMinMemberDailySteps();
        $settings = $question->getWalkingSettings();
        $targetGender = $settings['target_gender'] ?? 'both_segregated';

        $groups = WalkingGroup::whereIn('id', $groupIds)
            ->with(['memberships' => fn ($q) => $q->where('status', 'accepted')])
            ->get()
            ->keyBy('id');

        $groupMembersMap = [];
        $allMemberIds = [];
        foreach ($groups as $gId => $group) {
            $memberIds = $group->memberships->pluck('person_id')->filter()->unique()->values()->all();
            if ($group->created_by_person_id && ! in_array((int) $group->created_by_person_id, $memberIds, true)) {
                $memberIds[] = (int) $group->created_by_person_id;
            }
            $groupMembersMap[$gId] = $memberIds;
            $allMemberIds = array_merge($allMemberIds, $memberIds);
        }
        $allMemberIds = array_values(array_unique($allMemberIds));

        if (empty($allMemberIds)) {
            return [];
        }

        $disallowedModes = [];
        if ($settings['exclude_hidden'] ?? true) {
            $disallowedModes[] = 'hidden';
        }
        if (! empty($settings['exclude_anonymous'])) {
            $disallowedModes[] = 'anonymous';
        }

        $validPersonQuery = DB::table('persons')->whereIn('id', $allMemberIds);
        if (! empty($disallowedModes)) {
            $validPersonQuery->whereNotIn('walking_leaderboard_mode', $disallowedModes);
        }
        if (in_array($targetGender, ['male', 'female'], true)) {
            $validPersonQuery->where('gender', $targetGender);
        }
        $validPersonIds = $validPersonQuery->pluck('id')->all();

        $personToGroups = [];
        foreach ($groupMembersMap as $gId => $mIds) {
            $filtered = array_values(array_intersect($mIds, $validPersonIds));
            foreach ($filtered as $pid) {
                $personToGroups[$pid][] = (int) $gId;
            }
        }

        if (empty($personToGroups)) {
            return [];
        }

        $recordsQuery = WalkingRecord::query()
            ->select('person_id', DB::raw('DATE(date) as step_date'), DB::raw('SUM(steps) as daily_steps'))
            ->whereNotNull('person_id')
            ->whereIn('person_id', array_keys($personToGroups))
            ->whereDate('date', '>=', $startDate->toDateString())
            ->whereDate('date', '<=', $endDate->toDateString())
            ->groupBy('person_id', DB::raw('DATE(date)'));

        if ($minMemberSteps > 0) {
            $recordsQuery->havingRaw('SUM(steps) >= ?', [$minMemberSteps]);
        }

        $dailyGroupSteps = [];
        foreach ($recordsQuery->get() as $rec) {
            $pid = (int) $rec->person_id;
            $dStr = substr((string) $rec->step_date, 0, 10);
            $steps = (int) $rec->daily_steps;
            foreach ($personToGroups[$pid] ?? [] as $gId) {
                $dailyGroupSteps[$dStr][$gId] = ($dailyGroupSteps[$dStr][$gId] ?? 0) + $steps;
            }
        }

        if (empty($dailyGroupSteps)) {
            return [];
        }

        $colorMap = [];
        $colorIdx = 0;
        $paletteCount = count($palette);
        $runningSteps = [];
        $runningPoints = [];
        $formattedDays = [];

        $cursor = $startDate->copy();
        $dayNumber = 1;

        while ($cursor->lte($endDate)) {
            $dStr = $cursor->toDateString();
            $todayMap = $dailyGroupSteps[$dStr] ?? [];
            $previousSteps = $runningSteps;
            $previousPoints = $runningPoints;

            foreach ($todayMap as $gId => $steps) {
                $runningSteps[$gId] = ($runningSteps[$gId] ?? 0) + $steps;
            }

            if ($showPoints) {
                $dailyPoints = $this->awardRaceDailyPoints(array_replace($groupSlots, $todayMap), $dailyPointsMap);
                foreach ($dailyPoints as $gId => $points) {
                    $runningPoints[$gId] = ($runningPoints[$gId] ?? 0) + $points;
                }
            }

            if ($cursor->lt($firstFrameDate)) {
                $cursor->addDay();
                continue;
            }

            $dayCandidates = [];
            foreach ($runningSteps as $gId => $steps) {
                if ($steps <= 0 || ! $groups->has($gId)) {
                    continue;
                }
                $entry = [
                    'group_id' => (int) $gId,
                    'steps' => $steps,
                    'prev_steps' => $previousSteps[$gId] ?? 0,
                    'daily_steps' => $todayMap[$gId] ?? 0,
                ];
                if ($showPoints) {
                    $entry['points'] = $runningPoints[$gId] ?? 0;
                    $entry['prev_points'] = $previousPoints[$gId] ?? 0;
                }
                $dayCandidates[] = $entry;
            }

            usort($dayCandidates, static function ($a, $b) use ($metric) {
                if ($b[$metric] !== $a[$metric]) {
                    return $b[$metric] <=> $a[$metric];
                }
                if ($b['steps'] !== $a['steps']) {
                    return $b['steps'] <=> $a['steps'];
                }

                return $a['group_id'] <=> $b['group_id'];
            });

            $sliceLimit = $maxRanks > 0 ? $maxRanks : 500;
            $topSlice = array_slice($dayCandidates, 0, $sliceLimit);
            $entries = [];

            foreach ($topSlice as $rankIdx => $item) {
                $gId = $item['group_id'];
                if (! isset($colorMap[$gId])) {
                    $colorMap[$gId] = $palette[$colorIdx % $paletteCount];
                    $colorIdx++;
                }
                $group = $groups->get($gId);
                unset($item['group_id']);
                $entries[] = $item + [
                    'entry_id' => 'g_' . $gId,
                    'person_id' => $gId,
                    'rank' => $rankIdx + 1,
                    'name' => $group?->name ?? 'مجموعة',
                    'avatar' => $this->resolveGroupCoverUrl($group),
                    'color' => $colorMap[$gId],
                    'is_anonymous' => false,
                    'is_viewer' => false,
                ];
            }

            $day = [
                'day_index' => $dayNumber - 1,
                'day_number' => $dayNumber,
                'date' => $dStr,
                'date_label' => $this->formatArabicDayMonth($cursor),
                'max_steps' => max([1, ...array_column($entries, 'steps')]),
                'top_entries' => $entries,
            ];
            if ($showPoints) {
                $day['max_points'] = max(1, $entries[0]['points'] ?? 0);
            }
            $formattedDays[] = $day;

            $cursor->addDay();
            $dayNumber++;
        }

        return $formattedDays;
    }

    /**
     * Format a date into Eastern Arabic numerals + Arabic month matching the video (e.g. "٣٠ أكتوبر").
     */
    private function formatArabicDayMonth(Carbon $date): string
    {
        static $months = [
            1 => 'يناير',
            2 => 'فبراير',
            3 => 'مارس',
            4 => 'أبريل',
            5 => 'مايو',
            6 => 'يونيو',
            7 => 'يوليو',
            8 => 'أغسطس',
            9 => 'سبتمبر',
            10 => 'أكتوبر',
            11 => 'نوفمبر',
            12 => 'ديسمبر',
        ];

        $dayArabic = $this->toEasternArabicDigits((string) $date->day);
        $monthName = $months[$date->month] ?? $date->translatedFormat('F');

        return "{$dayArabic} {$monthName}";
    }

    private function toEasternArabicDigits(int|string $value): string
    {
        return strtr((string) $value, [
            '0' => '٠',
            '1' => '١',
            '2' => '٢',
            '3' => '٣',
            '4' => '٤',
            '5' => '٥',
            '6' => '٦',
            '7' => '٧',
            '8' => '٨',
            '9' => '٩',
        ]);
    }
}

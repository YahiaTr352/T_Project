<?php

namespace App\Services\Walking;

use App\Models\Person;
use App\Models\QuizQuestion;
use App\Models\QuizWinner;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class WalkingPredictionScoringService
{
    public const PODIUM_TOP_COUNT = 3;
    public const TABLE_ROWS_COUNT = 25;
    public const STANDINGS_ROWS_COUNT = 30;

    protected WalkingCompetitionScoringService $walkingScoringService;

    public function __construct(?WalkingCompetitionScoringService $walkingScoringService = null)
    {
        $this->walkingScoringService = $walkingScoringService ?? app(WalkingCompetitionScoringService::class);
    }

    /**
     * Get live prediction standings for a vote/prediction question linked to a walking challenge.
     *
     * @return array{
     *   standings: Collection<int, object>,
     *   top_standings: Collection<int, object>,
     *   contenders: Collection<int, object>,
     *   scoring_tiers: Collection<int, object>,
     *   zero_points_members: Collection<int, object>,
     *   walking_top_ranks: array<int, object>,
     *   viewer_standing: object|null,
     *   is_ended: bool,
     *   scoring_method: string,
     *   podium_top_count: int,
     *   table_rows_count: int,
     *   rank_points_map: array<int, int>
     * }
     */
    public function getLivePredictionStandings(QuizQuestion $voteQuestion, ?int $viewerPersonId = null, bool $includeAdminDetails = false): array
    {
        $emptyResult = [
            'standings' => collect(),
            'top_standings' => collect(),
            'contenders' => collect(),
            'scoring_tiers' => collect(),
            'zero_points_members' => collect(),
            'walking_top_ranks' => [],
            'viewer_standing' => null,
            'is_ended' => false,
            'scoring_method' => 'prediction_points',
            'podium_top_count' => self::PODIUM_TOP_COUNT,
            'table_rows_count' => self::TABLE_ROWS_COUNT,
            'rank_points_map' => [],
        ];

        if (!$voteQuestion->isLinkedToWalking()) {
            return $emptyResult;
        }

        $linkedWalking = $voteQuestion->linkedWalkingQuestion;
        if (!$linkedWalking) {
            return $emptyResult;
        }

        $sourceVote = $voteQuestion->source_vote_question_id
            ? ($voteQuestion->sourceVoteQuestion ?? $voteQuestion)
            : $voteQuestion;

        $rankPointsMap = $voteQuestion->getPredictionRankPoints();
        $maxSlots = !empty($rankPointsMap)
            ? max(array_keys($rankPointsMap))
            : max(1, (int) ($sourceVote->vote_max_selections ?: 5));

        $targetGender = $linkedWalking->getWalkingTargetGender();
        $sourceAllowedGender = $sourceVote->resolveEffectiveAllowedGender();

        // 1. جلب الترتيب الفعلي لتحدي المشي حسب المسار الصحيح
        $trackMaps = [];
        $primaryWalkingTopRanks = [];

        if ($targetGender === 'both_segregated') {
            $maleData = $this->walkingScoringService->getOverallStandings($linkedWalking, 'male');
            $femaleData = $this->walkingScoringService->getOverallStandings($linkedWalking, 'female');

            $trackMaps['male'] = $this->buildTrackRankMaps($maleData['standings'] ?? collect());
            $trackMaps['female'] = $this->buildTrackRankMaps($femaleData['standings'] ?? collect());

            $primaryTrack = ($sourceAllowedGender === 'female') ? 'female' : 'male';
            $primaryWalkingTopRanks = $trackMaps[$primaryTrack]['rankToWalkers'] ?? [];
        } else {
            $filterGender = in_array($targetGender, ['male', 'female'], true)
                ? $targetGender
                : (in_array($sourceAllowedGender, ['male', 'female'], true) ? $sourceAllowedGender : null);

            $singleData = $this->walkingScoringService->getOverallStandings($linkedWalking, $filterGender);
            $trackMaps['unified'] = $this->buildTrackRankMaps($singleData['standings'] ?? collect());
            $primaryWalkingTopRanks = $trackMaps['unified']['rankToWalkers'] ?? [];
        }

        // هل انتهى تحدي المشي رسمياً؟
        $isEnded = $linkedWalking->hasEnded() || ($linkedWalking->competition && $linkedWalking->competition->show_draw_only);

        // 2. جلب ترشيحات المصوتين من سؤال التصويت المصدر
        $answers = $sourceVote->answers()
            ->with(['person', 'user', 'selectedChoices'])
            ->get();

        if ($answers->isEmpty()) {
            return array_merge($emptyResult, [
                'is_ended' => $isEnded,
                'rank_points_map' => $rankPointsMap,
                'walking_top_ranks' => array_slice($primaryWalkingTopRanks, 0, $maxSlots, true),
            ]);
        }

        // تحميل خيارات سؤال التصويت مع بيانات الشخص المرتبط بكل خيار
        $choicesById = $sourceVote->choices()
            ->with('person')
            ->get()
            ->keyBy('id');

        // جمع أي معرفات إضافية قد تكون مخزنة مباشرة في الإجابات
        $allRawIds = [];
        foreach ($answers as $ans) {
            $parsed = $this->parsePredictedIds($ans);
            foreach ($parsed as $rawId) {
                $idInt = (int) $rawId;
                if ($idInt > 0 && !$choicesById->has($idInt)) {
                    $allRawIds[] = $idInt;
                }
            }
        }

        $extraPersons = !empty($allRawIds)
            ? Person::withoutGlobalScopes()->whereIn('id', array_unique($allRawIds))->get()->keyBy('id')
            : collect();

        // 3. احتساب النقاط والمطابقة لكل مصوت (الخانة 1 = المركز 1، الخانة 2 = المركز 2...)
        $computedNominators = [];

        foreach ($answers as $ans) {
            $person = $ans->person;
            $user = $ans->user;
            $pid = $person ? (int) $person->id : ($ans->person_id ? (int) $ans->person_id : null);

            $predictedChoiceIds = array_slice($this->parsePredictedIds($ans), 0, $maxSlots);

            $totalPoints = 0;
            $matchesCount = 0;
            $matchesDetails = [];
            $seenPersonIds = [];

            foreach ($predictedChoiceIds as $slotIndex => $rawChoiceId) {
                $rank = $slotIndex + 1;
                if (!isset($rankPointsMap[$rank])) {
                    continue;
                }

                $cIdInt = (int) $rawChoiceId;
                $predictedPersonId = null;
                $predictedName = 'مرشح غير محدد';
                $predictedGender = null;
                $fallbackChoiceGender = in_array($sourceAllowedGender, ['male', 'female'], true)
                    ? $sourceAllowedGender
                    : null;

                if ($choicesById->has($cIdInt)) {
                    $choiceObj = $choicesById->get($cIdInt);
                    $choicePerson = $choiceObj->person;
                    $isCompatiblePerson = $choicePerson && $sourceVote->isPersonGenderCompatibleWithQuestion($choicePerson);
                    $predictedPersonId = ($choicePerson && $isCompatiblePerson) ? (int) $choicePerson->id : null;
                    $predictedName = ($choicePerson && $isCompatiblePerson)
                        ? $choicePerson->name
                        : ($choiceObj->choice_text ?: ('مرشح سابق #' . $cIdInt));
                    $predictedGender = ($choicePerson && $isCompatiblePerson)
                        ? $choicePerson->gender
                        : $fallbackChoiceGender;
                } elseif ($extraPersons->has($cIdInt)) {
                    $candPerson = $extraPersons->get($cIdInt);
                    if ($sourceVote->isPersonGenderCompatibleWithQuestion($candPerson)) {
                        $predictedPersonId = (int) $candPerson->id;
                        $predictedName = $candPerson->name;
                        $predictedGender = $candPerson->gender;
                    } else {
                        $predictedPersonId = null;
                        $predictedName = 'مرشح سابق #' . $cIdInt;
                        $predictedGender = $fallbackChoiceGender;
                    }
                }

                // تحديد المسار في تحدي المشي للمقارنة
                $trackKey = ($targetGender === 'both_segregated')
                    ? (($predictedGender === 'female' || $sourceAllowedGender === 'female') ? 'female' : 'male')
                    : 'unified';

                $trackMap = $trackMaps[$trackKey] ?? ['personIdToRank' => [], 'rankToPersonIds' => []];
                $actualWalkingRank = ($predictedPersonId && isset($trackMap['personIdToRank'][$predictedPersonId]))
                    ? (int) $trackMap['personIdToRank'][$predictedPersonId]
                    : null;

                $isDuplicatePick = ($predictedPersonId && isset($seenPersonIds[$predictedPersonId]));
                if ($predictedPersonId) {
                    $seenPersonIds[$predictedPersonId] = true;
                }

                $actualPersonIdsAtRank = $trackMap['rankToPersonIds'][$rank] ?? [];
                $isMatch = !$isDuplicatePick
                    && $predictedPersonId !== null
                    && in_array($predictedPersonId, $actualPersonIdsAtRank, true);

                $pts = $isMatch ? (int) ($rankPointsMap[$rank] ?? 0) : 0;

                if ($isMatch) {
                    $matchesCount++;
                    $totalPoints += $pts;
                }

                $detailRow = [
                    'rank' => $rank,
                    'predicted_rank' => $rank,
                    'is_match' => $isMatch,
                    'points' => $pts,
                    'points_earned' => $pts,
                    'rank_max_points' => (int) ($rankPointsMap[$rank] ?? 0),
                ];

                if ($includeAdminDetails) {
                    $detailRow['predicted_person_id'] = $predictedPersonId;
                    $detailRow['predicted_name'] = $predictedName;
                    $detailRow['actual_walking_rank'] = $actualWalkingRank;
                }

                $matchesDetails[] = $detailRow;
            }

            $displayName = $person ? $person->name : ($user ? ($user->name ?? $user->phone) : 'مصوت');
            $voterFallbackGender = $person?->gender ?? ($sourceAllowedGender === 'female' ? 'female' : 'male');
            $photo = $this->resolvePersonPhotoUrl($person, $voterFallbackGender);
            $isViewer = ($viewerPersonId && $pid && $viewerPersonId === $pid);

            $computedNominators[] = (object) [
                'answer_id' => $ans->id,
                'person_id' => $pid,
                'user_id' => $ans->user_id,
                'name' => $displayName,
                'voter_name' => $displayName,
                'photo_url' => $photo,
                'avatar' => $photo,
                'gender' => $voterFallbackGender,
                'is_anonymous' => false,
                'is_viewer' => $isViewer,
                'is_current_user' => $isViewer,
                'first_voted_at' => $ans->created_at,
                'matches_count' => $matchesCount,
                'matched_count' => $matchesCount,
                'matches_details' => $matchesDetails,
                'actual_points' => $isEnded ? $totalPoints : 0,
                'projected_points' => $isEnded ? 0 : $totalPoints,
                'total_points' => $totalPoints,
                'actual_score' => $isEnded ? $totalPoints : 0,
                'projected_score' => $isEnded ? 0 : $totalPoints,
                'total_score' => $totalPoints,
            ];
        }

        // 4. ترتيب المتوقعين تنازلياً حسب النقاط ثم عدد المطابقات ثم أسبقية التصويت
        usort($computedNominators, function ($a, $b) {
            if ($b->total_points !== $a->total_points) {
                return $b->total_points <=> $a->total_points;
            }
            if ($b->matches_count !== $a->matches_count) {
                return $b->matches_count <=> $a->matches_count;
            }
            $timeA = $a->first_voted_at ? $a->first_voted_at->timestamp : 0;
            $timeB = $b->first_voted_at ? $b->first_voted_at->timestamp : 0;
            if ($timeA !== $timeB) {
                return $timeA <=> $timeB;
            }
            return $a->answer_id <=> $b->answer_id;
        });

        // 5. تعيين الترتيب المشترك (Dense Ranking) وتكوين مجموعات المراكز (مثل نوادي المشي)
        $scoringTiersMap = [];
        $zeroPointsMembers = [];
        $currentDenseRank = 0;
        $lastDistinctPoints = null;

        foreach ($computedNominators as $row) {
            $pts = (int) $row->total_points;
            if ($pts > 0) {
                if ($lastDistinctPoints === null || $pts !== $lastDistinctPoints) {
                    $currentDenseRank++;
                    $lastDistinctPoints = $pts;
                }
                $row->rank = $currentDenseRank;

                if (!isset($scoringTiersMap[$currentDenseRank])) {
                    $scoringTiersMap[$currentDenseRank] = (object) [
                        'rank' => $currentDenseRank,
                        'points' => $pts,
                        'members_count' => 0,
                        'members' => [],
                    ];
                }
                $scoringTiersMap[$currentDenseRank]->members[] = $row;
                $scoringTiersMap[$currentDenseRank]->members_count++;
            } else {
                $row->rank = 0;
                $zeroPointsMembers[] = $row;
            }
        }

        $allNominatorsCollection = collect($computedNominators);
        $rankedStandings = $allNominatorsCollection->filter(function ($r) {
            $effectivePts = (int) ($r->total_points ?? ($r->projected_points ?? ($r->actual_points ?? 0)));
            return $effectivePts > 0;
        })->values();

        $scoringTiers = collect(array_values($scoringTiersMap));
        $zeroPointsCollection = collect($zeroPointsMembers);

        // المتصدرون للمنصة: المراكز الثلاثة الأولى (بحد أقصى 4 متسابقين في حال التعادل بالمركز الثالث) وممن يملكون نقاطاً فقط (> 0)
        $topStandings = $rankedStandings->filter(fn ($r) => ($r->rank >= 1 && $r->rank <= self::PODIUM_TOP_COUNT))->values();
        if ($topStandings->count() > 4) {
            $topStandings = $topStandings->take(self::PODIUM_TOP_COUNT)->values();
        }
        $topPersonIds = $topStandings->pluck('person_id')->filter()->all();

        // المنافسون على الصدارة: المتسابقون الذين يملكون نقاطاً (فعلية أو متوقعة >= 1) وخارج المنصة الثلاثية
        $contenders = $rankedStandings->filter(fn ($r) => !in_array($r->person_id, $topPersonIds, true))->values();

        // استخراج بطاقة المشاهد الحالي (حتى لو كانت نقاطه 0 لمعرفة موقفه الشخصي دون إدراجه في القائمة العامة)
        $viewerStanding = null;
        if ($viewerPersonId) {
            $viewerStanding = $allNominatorsCollection->firstWhere('person_id', $viewerPersonId);
        }

        $flatWalkingTopRanks = [];
        foreach ($primaryWalkingTopRanks as $rNum => $walkersAtRank) {
            if ((int) $rNum > $maxSlots) {
                continue;
            }
            foreach ($walkersAtRank as $wObj) {
                $flatWalkingTopRanks[] = (array) $wObj;
            }
        }

        return [
            'standings' => $rankedStandings,
            'top_standings' => $topStandings,
            'contenders' => $contenders,
            'scoring_tiers' => $scoringTiers,
            'zero_points_members' => $zeroPointsCollection,
            'walking_top_ranks' => $flatWalkingTopRanks,
            'viewer_standing' => $viewerStanding,
            'is_ended' => $isEnded,
            'is_final' => $isEnded,
            'scoring_method' => 'prediction_points',
            'podium_top_count' => self::PODIUM_TOP_COUNT,
            'table_rows_count' => self::TABLE_ROWS_COUNT,
            'rank_points_map' => $rankPointsMap,
        ];
    }

    /**
     * بناء خريطة المراكز الفعلية لتحدي المشي مع استبعاد من لم يسجل أي خطوات أو نقاط
     * ودعم تساوي المراكز إذا تعادل متسابقان في النقاط والخطوات.
     *
     * @return array{
     *   personIdToRank: array<int, int>,
     *   rankToPersonIds: array<int, array<int, int>>,
     *   rankToWalkers: array<int, array<int, object>>
     * }
     */
    private function buildTrackRankMaps(Collection $trackStandings): array
    {
        $personIdToRank = [];
        $rankToPersonIds = [];
        $rankToWalkers = [];

        foreach ($trackStandings as $wRow) {
            $pid = (int) ($wRow->person_id ?? 0);
            $r = (int) ($wRow->rank ?? 0);
            $totPoints = (int) ($wRow->total_points ?? ($wRow->total_score ?? 0));
            $totSteps = (int) ($wRow->total_steps ?? ($wRow->today_steps ?? 0));

            if ($pid <= 0 || $r <= 0) {
                continue;
            }

            // لا يشغل المركز من لم يمشِ ولم يحقق أي نقاط بعد
            if ($totPoints <= 0 && $totSteps <= 0) {
                continue;
            }

            $personIdToRank[$pid] = $r;
            $rankToPersonIds[$r][] = $pid;
            $rankToWalkers[$r][] = (object) [
                'person_id' => $pid,
                'name' => $wRow->name ?? 'متسابق',
                'photo_url' => $wRow->photo_url ?? null,
                'rank' => $r,
                'total_points' => $totPoints,
                'total_steps' => $totSteps,
            ];
        }

        return [
            'personIdToRank' => $personIdToRank,
            'rankToPersonIds' => $rankToPersonIds,
            'rankToWalkers' => $rankToWalkers,
        ];
    }

    /**
     * استخراج مصفوفة الاختيارات المرتبة من سجل الإجابة
     *
     * @return array<int, int|string>
     */
    private function parsePredictedIds(object $ans): array
    {
        $rawAnswer = $ans->answer;
        $predictedChoiceIds = [];

        if (is_array($rawAnswer)) {
            $predictedChoiceIds = $rawAnswer;
        } elseif (is_string($rawAnswer) && trim($rawAnswer) !== '') {
            $decoded = json_decode($rawAnswer, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $predictedChoiceIds = $decoded;
            } else {
                $predictedChoiceIds = [$rawAnswer];
            }
        }

        if (empty($predictedChoiceIds) && isset($ans->selectedChoices) && $ans->selectedChoices->isNotEmpty()) {
            $predictedChoiceIds = $ans->selectedChoices->pluck('quiz_question_choice_id')->all();
        }

        return array_values(array_filter($predictedChoiceIds, fn ($v) => $v !== null && $v !== ''));
    }

    /**
     * اعتماد وتجميد الفائزين في مسابقة توقعات المشي رسمياً في جدول quiz_winners
     */
    public function finalizePredictionWinners(QuizQuestion $voteQuestion): int
    {
        if (!$voteQuestion->isLinkedToWalking()) {
            return 0;
        }

        $winnersCount = max(1, (int) ($voteQuestion->winners_count ?: 3));

        return DB::transaction(function () use ($voteQuestion, $winnersCount) {
            $voteQuestion->winners()->delete();

            $data = $this->getLivePredictionStandings($voteQuestion);
            // نأخذ كل من حصل على نقاط ضمن المراكز الفائزة الأولى (1..winnersCount)
            $topList = $data['standings']
                ->filter(fn ($row) => $row->total_points > 0 && $row->rank >= 1 && $row->rank <= $winnersCount);

            $createdCount = 0;
            foreach ($topList as $winnerRow) {
                QuizWinner::create([
                    'quiz_question_id' => $voteQuestion->id,
                    'user_id' => $winnerRow->user_id,
                    'person_id' => $winnerRow->person_id,
                    'position' => $winnerRow->rank,
                    'score' => $winnerRow->total_points,
                    'steps' => $winnerRow->matches_count,
                    'gender' => $winnerRow->gender,
                ]);

                $createdCount++;
            }

            Cache::forget('quiz_winner_json_' . $voteQuestion->id);
            Cache::forget('quiz-winner-selection-' . $voteQuestion->id);
            Cache::forget('api_active_quiz_competition');

            return $createdCount;
        });
    }

    /**
     * حل الرابط العام للصورة الشخصية
     */
    private function resolvePersonPhotoUrl(?Person $person, ?string $defaultGender = 'male'): ?string
    {
        if (!$person) {
            return Person::anonymousAvatarFor($defaultGender ?: 'male');
        }

        return $person->avatar ?: Person::anonymousAvatarFor($person->gender);
    }
}

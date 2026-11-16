<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QuizAnswer;
use App\Models\QuizCompetition;
use App\Models\QuizQuestion;
use App\Models\QuizRegistration;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class QuizCompetitionApiController extends Controller
{
    /**
     * Get the active competition or the next upcoming event.
     * Caches the competition layout to support high concurrency.
     */
    public function getActiveCompetition(Request $request): JsonResponse
    {
        $now = now();
        $activeCompetitions = QuizCompetition::active()
            ->where(function ($q) use ($now) {
                $q->whereNull('start_at')->orWhere('start_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('end_at')->orWhere('end_at', '>=', $now);
            })
            ->ordered()
            ->with([
                'questions.choices.person',
                'questions.choices',
                'questions.surveyItems',
                'questions.conversation',
            ])
            ->get();

        // Resolve user identifier and authenticated session (Identity vs 112 Passcode)
        $identityResolver = app(\App\Services\SiteIdentityResolver::class);
        $identity = $identityResolver->resolve($request);
        $authMethod = $identity['auth_method'] ?? null;

        // Check if token is a site-password token (112 guest)
        $token = \App\Services\SiteMobileAccessToken::extractFromRequest($request);
        if ($token && \App\Services\SiteMobileAccessToken::valid($token)) {
            $tokenPayload = \App\Services\SiteMobileAccessToken::getPayload($token);
            if (empty($tokenPayload['person_id'])) {
                $authMethod = 'site_password'; // 112 guest
            }
        }

        $user = $request->user();
        $phone = $request->query('phone') ?? ($user?->phone);
        $person = null;
        if ($identity && !empty($identity['person_id'])) {
            $person = \App\Models\Person::find($identity['person_id']);
        }

        $viewerPersonId = $person?->id;
        $viewerGender = $person?->gender ?? 'male';

        // تصفية المسابقات النشطة بحسب خصوصية وظهور الأسئلة للمشاهد الحالي (Server-side Exclusion)
        $activeCompetitions = $activeCompetitions->filter(function (QuizCompetition $c) use ($person, $authMethod) {
            $visibleQuestions = $c->questions->filter(fn (QuizQuestion $q) => $q->isVisibleTo($person, $authMethod));
            if ($visibleQuestions->isEmpty() && ! $c->show_draw_only) {
                return false;
            }
            $c->setRelation('questions', $visibleQuestions->values());

            return true;
        })->values();

        $answeredQuestionIds = [];
        $userVotesMap = [];

        $allQuestionIds = $activeCompetitions->flatMap->questions->pluck('id')->toArray();
        $allQuestionsMap = $activeCompetitions->flatMap->questions->keyBy('id');

        if (!empty($allQuestionIds) && ($person || $user || $phone)) {
            $userKey = $person ? 'p_'.$person->id : ($user ? 'u_'.$user->id : 'ph_'.$phone);
            $cacheKey = "user_answered_data_" . $userKey;
            $userData = Cache::remember($cacheKey, now()->addMinutes(1), function () use ($phone, $person, $user, $allQuestionIds, $allQuestionsMap) {
                $query = QuizAnswer::with(['selectedChoices.choice'])
                    ->whereIn('quiz_question_id', $allQuestionIds);

                if ($person) {
                    $linkedUserIds = $person->getLinkedUserIds();
                    $query->where(function ($q) use ($person, $linkedUserIds) {
                        $q->where('person_id', $person->id);
                        if (!empty($linkedUserIds)) {
                            $q->orWhereIn('user_id', $linkedUserIds);
                        }
                    });
                } elseif ($user) {
                    $query->where('user_id', $user->id);
                } elseif ($phone) {
                    $query->whereHas('user', function ($uq) use ($phone) {
                        $uq->where('phone', $phone);
                    });
                } else {
                    return ['answered_ids' => [], 'user_votes' => []];
                }

                $userAnswers = $query->oldest()->get();
                $userAnswersGrouped = $userAnswers->groupBy('quiz_question_id');

                $answeredIds = [];
                $votes = [];

                foreach ($userAnswersGrouped as $qId => $answersList) {
                    $firstAnswer = $answersList->first();
                    $answeredIds[] = (int) $qId;

                    if ($firstAnswer->answer_type === 'vote') {
                        $qObj = $allQuestionsMap->get((int) $qId);
                        $requiredSelections = max(1, (int) ($qObj?->vote_max_selections ?? 1));
                        $selectedChoicesColl = $firstAnswer->selectedChoices;
                        $qChoicesById = $qObj ? $qObj->choices->keyBy('id') : collect();

                        $selectedChoiceIds = $selectedChoicesColl
                            ->pluck('quiz_question_choice_id')
                            ->map(fn($id) => (int) $id)
                            ->filter()
                            ->values()
                            ->toArray();

                        if (empty($selectedChoiceIds) && !empty($firstAnswer->answer)) {
                            $decodedRaw = is_string($firstAnswer->answer)
                                ? json_decode($firstAnswer->answer, true)
                                : $firstAnswer->answer;
                            $rawIds = is_array($decodedRaw)
                                ? array_values(array_filter(array_map('intval', $decodedRaw)))
                                : (is_numeric($firstAnswer->answer) ? [(int) $firstAnswer->answer] : []);

                            foreach ($rawIds as $rawId) {
                                if ($qChoicesById->has($rawId)) {
                                    $selectedChoiceIds[] = (int) $rawId;
                                }
                            }
                        }

                        $selectedPersonIds = $selectedChoicesColl
                            ->map(fn($sc) => $sc->choice?->person_id ?? $qChoicesById->get((int) $sc->quiz_question_choice_id)?->person_id)
                            ->filter()
                            ->map(fn($id) => (int) $id)
                            ->unique()
                            ->values()
                            ->toArray();

                        if (empty($selectedPersonIds) && !empty($selectedChoiceIds)) {
                            $selectedPersonIds = collect($selectedChoiceIds)
                                ->map(fn($cid) => $qChoicesById->get((int) $cid)?->person_id)
                                ->filter()
                                ->map(fn($id) => (int) $id)
                                ->unique()
                                ->values()
                                ->toArray();
                        }

                        $needsCompletion = count($selectedChoiceIds) > 0 && count($selectedChoiceIds) < $requiredSelections;

                        if ($needsCompletion) {
                            $lockedChoiceIds = $selectedChoiceIds;
                            $lockedPersonIds = $selectedPersonIds;
                        } else {
                            $lockedChoiceIds = $selectedChoicesColl
                                ->where('is_locked', true)
                                ->pluck('quiz_question_choice_id')
                                ->map(fn($id) => (int) $id)
                                ->filter()
                                ->values()
                                ->toArray();

                            $lockedPersonIds = $selectedChoicesColl
                                ->where('is_locked', true)
                                ->map(fn($sc) => $sc->choice?->person_id ?? $qChoicesById->get((int) $sc->quiz_question_choice_id)?->person_id)
                                ->filter()
                                ->map(fn($id) => (int) $id)
                                ->unique()
                                ->values()
                                ->toArray();
                        }

                        $firstVotedAt = $firstAnswer->created_at;
                        $editBaseTime = (!$needsCompletion && !empty($lockedChoiceIds) && $firstAnswer->updated_at)
                            ? $firstAnswer->updated_at
                            : $firstVotedAt;

                        $voteDurationHours = $qObj ? $qObj->getVoteEditDurationHours() : 24;
                        $isUnlimited = $qObj ? $qObj->isVoteEditUnlimited() : false;
                        $isForbidden = $qObj ? $qObj->isVoteEditForbidden() : false;

                        $editUntilStr = null;
                        if (!$isUnlimited && !$isForbidden) {
                            $editUntilStr = $editBaseTime->copy()->addHours($voteDurationHours)->toIso8601String();
                        }

                        $votes[$qId] = [
                            'first_voted_at' => $firstVotedAt->toIso8601String(),
                            'edit_until' => $editUntilStr,
                            'selected_choices' => $selectedChoiceIds,
                            'selected_person_ids' => $selectedPersonIds,
                            'needs_completion' => $needsCompletion,
                            'locked_choices' => $lockedChoiceIds,
                            'locked_person_ids' => $lockedPersonIds,
                        ];
                    }
                }

                return ['answered_ids' => $answeredIds, 'user_votes' => $votes];
            });

            $answeredQuestionIds = $userData['answered_ids'] ?? [];
            $rawVotes = $userData['user_votes'] ?? [];

            foreach ($rawVotes as $qId => $vData) {
                $qObj = $allQuestionsMap->get((int) $qId);
                $isUnlimited = $qObj ? $qObj->isVoteEditUnlimited() : false;
                $isForbidden = $qObj ? $qObj->isVoteEditForbidden() : false;
                $needsCompletion = (bool) ($vData['needs_completion'] ?? false);

                $canEdit = false;
                $editUntilCarbon = null;
                $secondsRemaining = null;

                if ($needsCompletion) {
                    $canEdit = true;
                } elseif ($isUnlimited) {
                    $canEdit = true;
                } elseif ($isForbidden) {
                    $canEdit = false;
                    $secondsRemaining = 0;
                } elseif (!empty($vData['edit_until'])) {
                    $editUntilCarbon = \Carbon\Carbon::parse($vData['edit_until']);
                    $canEdit = now()->lt($editUntilCarbon);
                    $secondsRemaining = max(0, now()->diffInSeconds($editUntilCarbon, false));
                }

                $userVotesMap[$qId] = [
                    'can_edit' => $canEdit,
                    'needs_completion' => $needsCompletion,
                    'first_voted_at' => $vData['first_voted_at'],
                    'edit_until' => $vData['edit_until'] ?? null,
                    'seconds_remaining' => $secondsRemaining,
                    'selected_choices' => $vData['selected_choices'],
                    'selected_person_ids' => $vData['selected_person_ids'],
                    'locked_choices' => $vData['locked_choices'] ?? [],
                    'locked_person_ids' => $vData['locked_person_ids'] ?? [],
                ];
            }
        }

        if ($activeCompetitions->isEmpty()) {
            $upcoming = QuizCompetition::getNextUpcomingEvent($person);
            if ($upcoming && isset($upcoming['target_at'])) {
                // Format target_at for consistency
                $upcoming['target_at'] = $upcoming['target_at']->toIso8601String();
            }
            return response()->json([
                'status' => 'success',
                'data' => [
                    'active_competition' => null,
                    'active_competitions' => [],
                    'upcoming_event' => $upcoming,
                ]
            ]);
        }

        $scoringService = app(\App\Services\Walking\WalkingCompetitionScoringService::class);

        $formattedCompetitions = $activeCompetitions->map(function (QuizCompetition $activeComp) use ($answeredQuestionIds, $userVotesMap, $scoringService, $viewerPersonId, $viewerGender, $person, $authMethod) {
            $questionsVisibleAt = $activeComp->getQuestionsVisibleAt();
            $isLocked = $questionsVisibleAt && now()->lt($questionsVisibleAt);

            $formattedQuestions = $activeComp->questions->map(function (QuizQuestion $question) use ($answeredQuestionIds, $userVotesMap, $activeComp, $isLocked, $scoringService, $viewerPersonId, $viewerGender, $person, $authMethod) {
            $hasAnswered = in_array($question->id, $answeredQuestionIds);

            // Format choices
            $questionGender = $question->resolveEffectiveAllowedGender();
            $fallbackGender = in_array($questionGender, ['male', 'female'], true) ? $questionGender : null;

            $choices = $question->choices
                ->filter(function ($choice) {
                    return $choice->person_id
                        || trim((string) $choice->choice_text) !== ''
                        || $choice->image
                        || $choice->video;
                })
                ->map(function ($choice) use ($question, $fallbackGender) {
                    $choicePerson = $choice->person;
                    $effectiveGender = $choicePerson?->gender ?? $fallbackGender;
                    $avatarUrl = null;
                    if ($choicePerson && $choicePerson->avatar) {
                        $avatarUrl = $choicePerson->avatar;
                    } elseif ($choice->image) {
                        $avatarUrl = asset('storage/' . $choice->image);
                    } elseif ($question->isVote() || ($choicePerson && $choice->person_id)) {
                        $avatarUrl = \App\Models\Person::anonymousAvatarFor($effectiveGender ?? 'male');
                    }

                    return [
                        'id' => $choice->id,
                        'person_id' => $choicePerson ? $choice->person_id : null,
                        'choice_text' => $choicePerson ? $choicePerson->name : $choice->choice_text,
                        'gender' => $effectiveGender,
                        'avatar_url' => $avatarUrl,
                        'profile_url' => ($choicePerson && $choice->person_id) ? route('people.profile.show', $choice->person_id) : null,
                        'image_url' => $choice->image ? asset('storage/' . $choice->image) : null,
                        'video_url' => $choice->video ? asset('storage/' . $choice->video) : null,
                        'group_name' => $choice->group_name,
                    ];
                })
                ->values();

            // Format vote results and voter eligibility for live display
            $voteResults = null;
            $viewerEligibility = null;
            if ($question->answer_type === 'vote') {
                $voteResults = $question->getLiveVoteResultsData();
                $viewerEligibility = $question->checkVoterEligibility($person, $authMethod);
            }

            // Format survey items
            $surveyItems = $question->surveyItems->map(function ($item) {
                return [
                    'id' => $item->id,
                    'sort_order' => $item->sort_order,
                    'block_type' => $item->block_type,
                    'body_text' => $item->body_text,
                    'media_url' => $item->media_path ? asset('storage/' . $item->media_path) : null,
                    'youtube_url' => $item->youtube_url,
                    'response_kind' => $item->response_kind,
                    'rating_max' => $item->rating_max,
                    'number_min' => $item->number_min,
                    'number_max' => $item->number_max,
                ];
            });

            // Format walking challenge data if applicable
            $walkingData = null;
            if ($question->answer_type === 'walking' && $question->canViewWalkingLeaderboard($person)) {
                if ($question->isGroupWalkingChallenge()) {
                    $groupData = $scoringService->getOverallGroupStandings($question, $viewerPersonId);
                    $walkingData = [
                        'competition_scope'    => 'groups',
                        'settings'             => $question->walking_settings,
                        'walking_race_config'  => $question->getWalkingRaceConfig(),
                        'scoring_method'       => $question->getGroupScoringMethod(),
                        'scoring_mode_label'   => $groupData['scoring_mode_label'],
                        'groups_standings'     => $groupData['groups_standings'],
                        'battle_meta'          => $groupData['battle_meta'],
                        'viewer_group'         => $groupData['viewer_group'],
                        'prizes'               => $question->prize ?? [],
                    ];
                } else {
                    $targetGender = $question->getWalkingTargetGender();
                    $filterGender = in_array($targetGender, ['male', 'female'], true) ? $targetGender : $viewerGender;

                    $standings = $scoringService->getOverallStandings($question, $filterGender, $viewerPersonId);
                    $winnersCount = $standings['winners_count'] ?? max(1, (int) ($question->winners_count ?: 3));
                    $podiumTopCount = \App\Services\Walking\WalkingCompetitionScoringService::PODIUM_TOP_COUNT;

                    // إبقاء المراكز الخمسة الأولى (المنصة) + جميع المتنافسين الذين يملكون نقاطاً (محتسبة أو متوقعة > 0) دون أي اقتطاع عددي
                    $filteredStandings = $standings['standings']->filter(function ($row) use ($podiumTopCount) {
                        $rank = (int) ($row->rank ?? 0);
                        if ($rank <= $podiumTopCount) {
                            return true;
                        }
                        $actual = (int) ($row->total_points ?? ($row->actual_points ?? 0));
                        $projected = (int) ($row->projected_points ?? 0);
                        return ($actual + $projected) > 0;
                    })->values();

                    $tableRowsCount = max(0, $filteredStandings->count() - $podiumTopCount);

                    $walkingData = [
                        'competition_scope'   => 'individuals',
                        'settings'            => $question->walking_settings,
                        'walking_race_config' => $question->getWalkingRaceConfig(),
                        'scoring_method'      => $question->getWalkingScoringMethod(),
                        'winners_count'       => $winnersCount,
                        'podium_top_count'    => $podiumTopCount,
                        'table_rows_count'    => $tableRowsCount,
                        'daily_points_map'    => $standings['daily_points_map'] ?? $question->getWalkingDailyPoints(),
                        'viewer_standing'     => $standings['viewer_standing'],
                        'top_standings'       => $filteredStandings,
                        'today_standings'     => ($standings['today_standings'] ?? collect())->take(10)->values(),
                        'prizes'              => $question->prize ?? [],
                    ];
                }
            }

            // Format walking prediction data if linked to a walking challenge
            $predictionData = null;
            if ($question->isLinkedToWalking()) {
                $predictionScoringService = app(\App\Services\Walking\WalkingPredictionScoringService::class);
                $predictionData = $predictionScoringService->getLivePredictionStandings($question, $viewerPersonId);

                // حجب صدارة وسجلات مشي المتسابقات الحقيقية عن غير النساء لضمان الخصوصية
                if (! $question->canViewWalkingLeaderboard($person) && is_array($predictionData)) {
                    $predictionData['walking_top_ranks'] = [];
                }
            }

            // إخفاء الشات عن المشاهدة للعرض فقط (نساء/زوار في مسابقة الرجال)
            $hideChatForViewer = $question->isViewOnlyFor($person);

            $userVote = $userVotesMap[$question->id] ?? null;
            if ($userVote && ($question->hasEnded() || $activeComp->show_draw_only)) {
                $userVote['can_edit'] = false;
                $userVote['needs_completion'] = false;
                $userVote['seconds_remaining'] = 0;
            }

            return [
                'id' => $question->id,
                'question_text' => $question->question_text,
                'description' => $this->sanitizeDescription($question->description),
                'answer_type' => $question->answer_type,
                'is_multiple_selections' => (bool)$question->is_multiple_selections,
                'vote_max_selections' => $question->vote_max_selections ?? 1,
                'vote_edit_duration_hours' => $question->vote_edit_duration_hours ?? 24,
                'linked_walking_question_id' => $question->linked_walking_question_id,
                'source_vote_question_id' => $question->source_vote_question_id,
                'is_walking_prediction' => $question->isLinkedToWalking(),
                'prediction_data' => $predictionData,
                'prediction_rank_points' => $question->getPredictionRankPoints(),
                'require_prior_registration' => (bool)$question->require_prior_registration,
                'prize' => $question->prize,
                'has_answered' => $hasAnswered,
                'user_vote' => $userVote,
                'choices' => $isLocked ? [] : $choices, // Hide choices if questions are pre-locked
                'survey_items' => $isLocked ? [] : $surveyItems,
                'walking_settings' => $question->walking_settings,
                'walking_data' => $walkingData,
                'voter_eligibility' => $question->voter_eligibility,
                'candidate_rules' => $question->getCandidateRules(),
                'viewer_eligibility' => $viewerEligibility,
                'vote_results' => $voteResults,
                'has_chat' => (bool) $question->has_chat && ! $hideChatForViewer,
                'chat_room' => ($question->has_chat && ! $hideChatForViewer && $question->conversation) ? [
                    'id' => $question->conversation->id,
                    'messages_count' => (int) $question->conversation->messages_count,
                    'last_message_at' => $question->conversation->last_message_at?->toIso8601String(),
                    'last_message_preview' => $question->conversation->last_message_preview,
                ] : null,
            ];
        });

            return [
                'id' => $activeComp->id,
                'title' => $activeComp->title,
                'description' => $this->sanitizeDescription($activeComp->description),
                'start_at' => $activeComp->start_at ? $activeComp->start_at->toIso8601String() : null,
                'end_at' => $activeComp->end_at ? $activeComp->end_at->toIso8601String() : null,
                'show_draw_only' => (bool)$activeComp->show_draw_only,
                'questions_visible_at' => $questionsVisibleAt ? $questionsVisibleAt->toIso8601String() : null,
                'is_locked' => $isLocked,
                'questions' => $formattedQuestions,
            ];
        });

        $primaryComp = $formattedCompetitions->first();

        return response()->json([
            'status' => 'success',
            'data' => [
                'section_title' => $formattedCompetitions->count() > 1 ? 'المسابقات والفعاليات النشطة' : ($primaryComp['title'] ?? 'المسابقات'),
                'active_competition' => $primaryComp,
                'active_competitions' => $formattedCompetitions->values(),
                'upcoming_event' => null,
            ]
        ]);
    }

    /**
     * Submit an answer or a vote.
     * Matches the validation and evaluation logic of QuizCompetitionPublicController.
     */
    public function storeAnswer(Request $request, QuizCompetition $quizCompetition, QuizQuestion $quizQuestion): JsonResponse
    {
        // 1. Signature verification (anti-bot / client verification) (Technical Partner decision)
        $clientSignature = $request->header('X-App-Signature');
        $timestamp = $request->header('X-App-Timestamp');
        
        if ($clientSignature && $timestamp) {
            // Verify timestamp isn't older than 5 minutes to prevent replay attacks
            if (abs(time() - (int)$timestamp) > 300) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'انتهت صلاحية الطلب (زمن غير صالح).'
                ], 403);
            }
            
            $secret = config('quiz.api_secret_key');
            $submittedPhone = (string) $request->input('phone', '');
            $expectedSignature = hash('sha256', $secret . $timestamp . $quizQuestion->id . $submittedPhone);
            
            if (!hash_equals($expectedSignature, $clientSignature)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'توقيع الطلب غير صحيح.'
                ], 403);
            }
        }

        if ($quizCompetition->show_draw_only) {
            return response()->json([
                'status' => 'error',
                'message' => 'عذراً، باب الإجابة مغلق حالياً للمتابعة مع القرعة والسحب.'
            ], 422);
        }

        if ($quizQuestion->quiz_competition_id != $quizCompetition->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'السؤال المحدد لا ينتمي لهذه المسابقة.'
            ], 400);
        }

        if ($quizQuestion->hasNotStarted()) {
            return response()->json([
                'status' => 'error',
                'message' => 'السؤال لم يبدأ بعد.'
            ], 422);
        }

        $questionsVisibleAt = $quizCompetition->getQuestionsVisibleAt();
        if ($questionsVisibleAt && now()->lt($questionsVisibleAt)) {
            return response()->json([
                'status' => 'error',
                'message' => 'السؤال يظهر بعد مرور الوقت المحدد من بدء المسابقة.'
            ], 422);
        }

        if ($quizQuestion->hasEnded()) {
            return response()->json([
                'status' => 'error',
                'message' => 'انتهت فترة الإجابة على هذا السؤال.'
            ], 422);
        }

        $requiresPriorRegistration = (bool)$quizQuestion->require_prior_registration;
        $isVoteType = $quizQuestion->answer_type === 'vote';

        // 1. Resolve user identifier and authenticated session (Identity vs 112 Passcode)
        $identityResolver = app(\App\Services\SiteIdentityResolver::class);
        $identity = $identityResolver->resolve($request);
        $authMethod = $identity['auth_method'] ?? null;

        $token = \App\Services\SiteMobileAccessToken::extractFromRequest($request);
        if ($token && \App\Services\SiteMobileAccessToken::valid($token)) {
            $tokenPayload = \App\Services\SiteMobileAccessToken::getPayload($token);
            if (empty($tokenPayload['person_id'])) {
                $authMethod = 'site_password'; // 112 guest
            }
        }

        $authUser = $request->user();
        $currentPerson = null;
        if ($identity && !empty($identity['person_id'])) {
            $currentPerson = \App\Models\Person::find($identity['person_id']);
        }

        // التحقق من أن السؤال/المسابقة مرئية ومتاحة للمستخدم (منع التجاوز المباشر عبر الـ API)
        if (! $quizQuestion->isVisibleTo($currentPerson, $authMethod)) {
            return response()->json([
                'status' => 'error',
                'code' => 'NOT_ELIGIBLE',
                'message' => 'عذراً، هذه المسابقة غير متاحة لك.',
            ], 403);
        }

        // المشاهدة للعرض فقط (نساء/زوار في مسابقة الرجال): ممنوع المشاركة أو التصويت
        if ($quizQuestion->isViewOnlyFor($currentPerson)) {
            return response()->json([
                'status' => 'error',
                'code' => 'VIEW_ONLY',
                'message' => 'عذراً، هذه المسابقة للعرض فقط ولا تقبل مشاركتك.',
            ], 403);
        }

        // Check voter eligibility for vote questions or if voter eligibility rules are configured
        if ($isVoteType || !empty($quizQuestion->voter_eligibility)) {
            $eligibility = $quizQuestion->checkVoterEligibility($currentPerson, $authMethod);
            if (!$eligibility['eligible']) {
                return response()->json([
                    'status' => 'error',
                    'code' => $eligibility['code'],
                    'message' => $eligibility['message'],
                ], 403);
            }
        }

        // Auto-resolve phone and name if user is verified via Identity/Person
        $resolvedPhone = $request->input('phone');
        if (!$resolvedPhone && $currentPerson) {
            $resolvedPhone = $currentPerson->resolveQuizPhone();

            $request->merge([
                'phone' => $resolvedPhone,
                'name' => $request->input('name') ?: $currentPerson->name,
            ]);
        }

        // Prepare validation rules
        $rules = [
            'name' => $currentPerson ? 'nullable|string|max:255' : 'required|string|max:255',
            'phone' => $currentPerson ? 'nullable|string|max:30' : 'required|string|size:10|regex:/^[0-9]{10}$/',
            'is_from_ancestry' => 'nullable|boolean',
            'mother_name' => 'nullable|string|max:255',
        ];

        // Specific answer validations based on question type
        if ($quizQuestion->is_multiple_selections || $quizQuestion->answer_type === 'ordering' || $quizQuestion->answer_type === 'true_false') {
            $rules['answer'] = 'required|array';
            if ($quizQuestion->answer_type !== 'true_false') {
                $rules['answer.*'] = 'required|exists:quiz_question_choices,id';
            } else {
                $rules['answer.*'] = 'required|in:1,0,true,false';
            }
        } elseif ($isVoteType) {
            $requiredSelections = max(1, (int) ($quizQuestion->vote_max_selections ?? 1));
            $isOpenTree = $quizQuestion->isOpenTreeVoting();
            if ($isOpenTree) {
                if ($request->has('person_ids')) {
                    $rules['person_ids'] = $requiredSelections > 1 ? 'required|array' : 'required';
                } else {
                    $rules['answer'] = $requiredSelections > 1 ? 'required|array' : 'required';
                }
            } else {
                if ($requiredSelections > 1) {
                    $rules['answer'] = 'required|array';
                    $rules['answer.*'] = 'required|exists:quiz_question_choices,id';
                } else {
                    $rules['answer'] = 'required|exists:quiz_question_choices,id';
                }
            }
        } elseif ($quizQuestion->answer_type === 'multiple_choice') {
            $rules['answer'] = 'required|exists:quiz_question_choices,id';
        } elseif ($quizQuestion->answer_type === 'fill_blank') {
            $rules['answer'] = 'required|exists:quiz_question_choices,id';
        } elseif ($quizQuestion->answer_type === 'survey') {
            $quizQuestion->loadMissing('surveyItems');
            if ($quizQuestion->surveyItems->isEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'هذا الاستبيان غير مكتمل الإعداد.'
                ], 422);
            }
            $rules['survey_item'] = 'required|array';
            foreach ($quizQuestion->surveyItems as $item) {
                $key = 'survey_item.' . $item->id;
                if ($item->response_kind === 'rating') {
                    $rules[$key] = 'required|integer|min:1|max:' . max(1, (int)$item->rating_max);
                } elseif ($item->response_kind === 'number') {
                    $min = (int)($item->number_min ?? 0);
                    $max = (int)($item->number_max ?? 100);
                    if ($min > $max) {
                        $min = 0;
                        $max = 100;
                    }
                    $rules[$key] = 'required|integer|min:' . $min . '|max:' . $max;
                } else {
                    $rules[$key] = 'required|string|max:2000';
                }
            }
        } else {
            $rules['answer'] = 'required|string|max:1000';
        }

        $validator = Validator::make($request->all(), $rules, [
            'name.required' => 'الاسم مطلوب',
            'phone.required' => 'رقم الهاتف مطلوب',
            'phone.size' => 'يجب أن يكون رقم الهاتف 10 أرقام بالضبط',
            'phone.regex' => 'يجب أن يكون رقم الهاتف 10 أرقام فقط تبدأ بـ 05',
            'answer.required' => 'الإجابة مطلوبة',
            'answer.size' => 'يجب اختيار عدد (' . ($quizQuestion->vote_max_selections ?? 1) . ') مرشحين إجبارياً لإتمام التصويت.',
            'answer.max' => 'لا يمكنك اختيار أكثر من ' . ($quizQuestion->vote_max_selections ?? 1) . ' خيارات',
            'person_ids.required' => 'يرجى اختيار المرشحين من شجرة العائلة',
            'person_ids.size' => 'يجب اختيار عدد (' . ($quizQuestion->vote_max_selections ?? 1) . ') مرشحين إجبارياً لإتمام التصويت.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first() ?: 'فشل التحقق من البيانات المدخلة.',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        // For open tree voting: convert selected person_ids into dynamic choices
        if ($isVoteType && $quizQuestion->isOpenTreeVoting()) {
            $requiredSelections = max(1, (int) ($quizQuestion->vote_max_selections ?? 1));
            $rawPersonIds = $request->input('person_ids', $request->input('answer'));
            $personIdsList = is_array($rawPersonIds) ? $rawPersonIds : [$rawPersonIds];
            $personIdsList = array_values(array_filter($personIdsList));

            if (empty($personIdsList)) {
                return response()->json([
                    'status' => 'error',
                    'message' => "يرجى اختيار {$requiredSelections} مرشحين من شجرة العائلة للمشاركة في التصويت.",
                ], 422);
            }

            if (count($personIdsList) < $requiredSelections) {
                $remaining = $requiredSelections - count($personIdsList);
                return response()->json([
                    'status' => 'error',
                    'message' => "يجب اختيار {$requiredSelections} مرشحين إجبارياً لإتمام التصويت. فاضل {$remaining} " . ($remaining === 1 ? 'مرشح' : 'مرشحين') . ".",
                ], 422);
            }

            if (count($personIdsList) > $requiredSelections) {
                return response()->json([
                    'status' => 'error',
                    'message' => "لا يمكنك اختيار أكثر من {$requiredSelections} مرشحين في هذا التصويت.",
                ], 422);
            }

            if (count($personIdsList) !== count(array_unique($personIdsList))) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'لا يمكن تكرار نفس المرشح في أكثر من خانة.',
                ], 422);
            }

            $choiceIds = [];
            foreach ($personIdsList as $pId) {
                $person = \App\Models\Person::find((int) $pId);
                if (! $person) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'الشخص المختار كمرشح غير موجود في سجلات العائلة.',
                    ], 422);
                }

                $candCheck = $quizQuestion->checkCandidateEligibility($person);
                if (! $candCheck['eligible']) {
                    return response()->json([
                        'status' => 'error',
                        'message' => $candCheck['message'],
                    ], 422);
                }

                $choice = $quizQuestion->choices()->firstOrCreate(
                    ['person_id' => $person->id],
                    [
                        'choice_text' => $person->name,
                        'is_correct' => false,
                    ]
                );
                $choiceIds[] = $choice->id;
            }

            $validated['answer'] = (count($choiceIds) === 1 && $requiredSelections === 1) ? $choiceIds[0] : $choiceIds;
            $request->merge([
                'answer' => $validated['answer'],
            ]);
        }

        // For predefined voting: validate exact candidate selections before transaction
        if ($isVoteType && !$quizQuestion->isOpenTreeVoting()) {
            $requiredSelections = max(1, (int) ($quizQuestion->vote_max_selections ?? 1));
            $rawSubmitted = is_array($validated['answer']) ? array_filter($validated['answer']) : array_filter([$validated['answer']]);
            $rawSubmitted = array_values(array_map('intval', $rawSubmitted));

            if (empty($rawSubmitted)) {
                return response()->json([
                    'status' => 'error',
                    'message' => "يرجى اختيار {$requiredSelections} مرشحين للتصويت."
                ], 422);
            }

            if (count($rawSubmitted) < $requiredSelections) {
                $remaining = $requiredSelections - count($rawSubmitted);
                return response()->json([
                    'status' => 'error',
                    'message' => "يجب اختيار {$requiredSelections} مرشحين إجبارياً لإتمام التصويت. فاضل {$remaining} " . ($remaining === 1 ? 'مرشح' : 'مرشحين') . "."
                ], 422);
            }

            if (count($rawSubmitted) > $requiredSelections) {
                return response()->json([
                    'status' => 'error',
                    'message' => "لا يمكنك اختيار أكثر من {$requiredSelections} مرشحين في هذا التصويت."
                ], 422);
            }

            if (count($rawSubmitted) !== count(array_unique($rawSubmitted))) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'لا يمكن تكرار نفس المرشح في أكثر من خانة.'
                ], 422);
            }
        }

        // 1. Check phone exists if prior registration is required (bypassed for identity-verified family members)
        if ($requiresPriorRegistration && !$currentPerson) {
            $phoneExists = User::where('phone', $validated['phone'])->exists();
            if (!$phoneExists) {
                $actionMsg = $isVoteType ? 'التصويت' : 'المشاركة';
                return response()->json([
                    'status' => 'error',
                    'message' => "رقم الهاتف غير مسجل. لا يمكنك {$actionMsg} إلا لمن سبق تسجيله برقم هاتفه."
                ], 422);
            }
        }

        try {
            DB::beginTransaction();

            $hasMotherName = !empty($validated['mother_name']);
            $isFromAncestry = (isset($validated['is_from_ancestry']) && $validated['is_from_ancestry']) || $hasMotherName;

            // 2. Cooldown check (2 hours) & Duplicate check
            $existingAnswerQuery = QuizAnswer::where('quiz_question_id', $quizQuestion->id);
            if ($currentPerson) {
                $linkedUids = $currentPerson->getLinkedUserIds();
                $matchingUserIds = !empty($validated['phone'])
                    ? User::where('phone', $validated['phone'])->pluck('id')->toArray()
                    : [];
                $allUserIds = array_unique(array_filter(array_merge($linkedUids, $matchingUserIds)));

                $existingAnswerQuery->where(function ($q) use ($currentPerson, $allUserIds, $validated) {
                    $q->where('person_id', $currentPerson->id);
                    if (!empty($allUserIds)) {
                        $q->orWhereIn('user_id', $allUserIds);
                    }
                    if (!empty($validated['phone'])) {
                        $q->orWhereHas('user', function ($uq) use ($validated) {
                            $uq->where('phone', $validated['phone']);
                        });
                    }
                });
            } else {
                $existingAnswerQuery->where(function ($q) use ($validated) {
                    $q->whereHas('user', function ($uq) use ($validated) {
                        $uq->where('phone', $validated['phone']);
                    });
                    $phonePersons = \App\Models\Person::whereHas('contactAccounts', function ($caq) use ($validated) {
                        $caq->where('account_type', 'phone')->where('account_value', $validated['phone']);
                    })->pluck('id')->toArray();
                    if (!empty($phonePersons)) {
                        $q->orWhereIn('person_id', $phonePersons);
                    }
                });
            }
            $existingAnswer = $existingAnswerQuery->with(['selectedChoices.choice'])->oldest()->first();

            $isVoteUpdate = false;
            $isCompletingPartialVote = false;
            $lockedChoiceIds = [];
            $lockedPersonIdsForValidation = [];

            if ($existingAnswer) {
                if ($isVoteType) {
                    $requiredSelections = max(1, (int) ($quizQuestion->vote_max_selections ?? 1));
                    $existingChoicesColl = $existingAnswer->selectedChoices;
                    $existingCount = $existingChoicesColl->count();
                    $isCompletingPartialVote = $existingCount > 0 && $existingCount < $requiredSelections;

                    if ($isCompletingPartialVote) {
                        $lockedChoiceIds = $existingChoicesColl
                            ->pluck('quiz_question_choice_id')
                            ->map(fn($id) => (int)$id)
                            ->values()
                            ->toArray();
                        $lockedPersonIdsForValidation = $existingChoicesColl
                            ->map(fn($sc) => (int) ($sc->choice?->person_id ?? 0))
                            ->filter()
                            ->values()
                            ->toArray();
                        $isVoteUpdate = true;
                    } else {
                        $lockedChoiceIds = $existingChoicesColl
                            ->where('is_locked', true)
                            ->pluck('quiz_question_choice_id')
                            ->map(fn($id) => (int)$id)
                            ->values()
                            ->toArray();
                        $lockedPersonIdsForValidation = $existingChoicesColl
                            ->where('is_locked', true)
                            ->map(fn($sc) => (int) ($sc->choice?->person_id ?? 0))
                            ->filter()
                            ->values()
                            ->toArray();

                        $editBaseTime = (!empty($lockedChoiceIds) && $existingAnswer->updated_at)
                            ? $existingAnswer->updated_at
                            : $existingAnswer->created_at;
                        $voteEditHours = $quizQuestion->vote_edit_duration_hours ?? 24;

                        if ($voteEditHours === 0) {
                            DB::rollBack();
                            return response()->json([
                                'status' => 'error',
                                'message' => 'عذراً، غير مسموح بتعديل التصويت بعد إرساله.'
                            ], 422);
                        }

                        if ($voteEditHours > 0) {
                            $editDeadline = $editBaseTime->copy()->addHours($voteEditHours);
                            if (now()->gt($editDeadline)) {
                                DB::rollBack();
                                return response()->json([
                                    'status' => 'error',
                                    'message' => "عذراً، انتهت المهلة المحددة لتعديل التصويت ({$voteEditHours} ساعة)."
                                ], 422);
                            }
                        }

                        // Inside deadline or unlimited (-1) -> Allowed to edit!
                        $isVoteUpdate = true;
                    }
                } else {
                    $cooldownHours = 2;
                    $lastAnswerTime = $existingAnswer->created_at;
                    $canAnswerAgain = now()->diffInHours($lastAnswerTime) >= $cooldownHours;

                    if (!$canAnswerAgain) {
                        DB::rollBack();
                        return response()->json([
                            'status' => 'error',
                            'message' => 'لقد أجبت على هذا السؤال مسبقاً.'
                        ], 422);
                    }
                }
            }

            // 3. Register and identify participant (Person vs 112 Guest User)
            $targetPersonId = null;
            $targetUserId = null;

            if ($currentPerson) {
                $targetPersonId = $currentPerson->id;
                QuizRegistration::firstOrCreate([
                    'quiz_competition_id' => $quizCompetition->id,
                    'person_id' => $currentPerson->id,
                ]);
            } else {
                $hasMotherName = !empty($validated['mother_name']);
                $isFromAncestry = (isset($validated['is_from_ancestry']) && $validated['is_from_ancestry']) || $hasMotherName;

                $user = User::where('phone', $validated['phone'])->latest()->first();
                if ($requiresPriorRegistration && !$user) {
                    DB::rollBack();
                    return response()->json([
                        'status' => 'error',
                        'message' => 'رقم الهاتف غير مسجل.'
                    ], 422);
                }

                if (!$user) {
                    $user = User::create([
                        'name' => $validated['name'],
                        'phone' => $validated['phone'],
                        'password' => Hash::make(uniqid()),
                        'status' => 1,
                        'is_from_ancestry' => $isFromAncestry,
                        'mother_name' => $hasMotherName ? $validated['mother_name'] : null,
                    ]);
                }
                $targetUserId = $user->id;

                QuizRegistration::firstOrCreate([
                    'quiz_competition_id' => $quizCompetition->id,
                    'user_id' => $user->id,
                ]);
            }

            $isCorrect = false;
            $answerType = 'custom';
            $answerData = '';
            if ($quizQuestion->answer_type !== 'survey') {
                $answerData = $validated['answer'];
            }

            // 5. Evaluate correctness of the answer based on type
            if ($quizQuestion->answer_type === 'multiple_choice') {
                $answerType = 'choice';

                if ($quizQuestion->is_multiple_selections) {
                    $selectedChoiceIds = $validated['answer'];
                    $correctChoiceIds = $quizQuestion->choices()->where('is_correct', true)->pluck('id')->toArray();

                    $selectedCorrectCount = 0;
                    $hasIncorrectChoice = false;

                    foreach ($selectedChoiceIds as $choiceId) {
                        if (in_array((int)$choiceId, $correctChoiceIds)) {
                            $selectedCorrectCount++;
                        } else {
                            $hasIncorrectChoice = true;
                            break;
                        }
                    }

                    $isCorrect = !$hasIncorrectChoice && ($selectedCorrectCount === count($correctChoiceIds));
                    $answerData = json_encode($selectedChoiceIds);
                } else {
                    $choiceId = (int)$validated['answer'];
                    $correctChoice = $quizQuestion->choices->firstWhere('is_correct', true);
                    $isCorrect = $correctChoice && $correctChoice->id === $choiceId;
                    $answerData = (string)$validated['answer'];
                }
            } elseif ($quizQuestion->answer_type === 'vote') {
                $answerType = 'vote';
                $requiredSelections = max(1, (int) ($quizQuestion->vote_max_selections ?? 1));
                $rawSubmitted = is_array($validated['answer']) ? array_filter($validated['answer']) : array_filter([$validated['answer']]);
                $rawSubmitted = array_values(array_map('intval', $rawSubmitted));

                if (empty($rawSubmitted)) {
                    DB::rollBack();
                    return response()->json([
                        'status' => 'error',
                        'message' => "يرجى اختيار {$requiredSelections} مرشحين للتصويت."
                    ], 422);
                }

                if (count($rawSubmitted) < $requiredSelections) {
                    $remaining = $requiredSelections - count($rawSubmitted);
                    DB::rollBack();
                    return response()->json([
                        'status' => 'error',
                        'message' => "يجب اختيار {$requiredSelections} مرشحين إجبارياً لإتمام التصويت. فاضل {$remaining} " . ($remaining === 1 ? 'مرشح' : 'مرشحين') . "."
                    ], 422);
                }

                if (count($rawSubmitted) > $requiredSelections) {
                    DB::rollBack();
                    return response()->json([
                        'status' => 'error',
                        'message' => "لا يمكنك اختيار أكثر من {$requiredSelections} مرشحين في هذا التصويت."
                    ], 422);
                }

                if (count($rawSubmitted) !== count(array_unique($rawSubmitted))) {
                    DB::rollBack();
                    return response()->json([
                        'status' => 'error',
                        'message' => 'لا يمكن تكرار نفس المرشح في أكثر من خانة.'
                    ], 422);
                }

                if ($quizQuestion->isOpenTreeVoting()) {
                    $selectedChoiceIds = [];
                    $preloadedChoices = $quizQuestion->choices()
                        ->with('person')
                        ->whereIn('id', $rawSubmitted)
                        ->get()
                        ->keyBy('id');

                    foreach ($rawSubmitted as $valId) {
                        $existingChoice = $preloadedChoices->get((int) $valId);
                        if (!$existingChoice || !$existingChoice->person_id || !$existingChoice->person) {
                            DB::rollBack();
                            return response()->json([
                                'status' => 'error',
                                'message' => 'المرشح المختار غير مرتبط بملف شخصي صالح في شجرة العائلة.'
                            ], 422);
                        }

                        $targetPerson = $existingChoice->person;

                        if (!in_array((int) $targetPerson->id, $lockedPersonIdsForValidation, true)) {
                            $eligibility = $quizQuestion->checkCandidateEligibility($targetPerson);
                            if (!$eligibility['eligible']) {
                                DB::rollBack();
                                return response()->json([
                                    'status' => 'error',
                                    'message' => $eligibility['message'] ?? 'المرشح غير مؤهل للتصويت.'
                                ], 422);
                            }
                        }

                        $selectedChoiceIds[] = (int) $existingChoice->id;
                    }
                    $answerData = count($selectedChoiceIds) > 1 ? json_encode($selectedChoiceIds) : (string) $selectedChoiceIds[0];
                } else {
                    $selectedChoiceIds = $rawSubmitted;
                    $answerData = count($selectedChoiceIds) > 1 ? json_encode($selectedChoiceIds) : (string) $selectedChoiceIds[0];
                }

                // Enforce locked prefix / choices integrity for partial vote completion or subsequent edits
                if (!empty($lockedChoiceIds)) {
                    $lockedCount = count($lockedChoiceIds);
                    if ($quizQuestion->isOpenTreeVoting() || $quizQuestion->isLinkedToWalking()) {
                        $submittedPrefix = array_values(array_map('intval', array_slice($selectedChoiceIds, 0, $lockedCount)));
                        if ($submittedPrefix !== $lockedChoiceIds) {
                            DB::rollBack();
                            return response()->json([
                                'status' => 'error',
                                'message' => "لا يمكن تغيير أو حذف المرشحين السابقين المثبتين في الخانات الأولى (1 إلى {$lockedCount}). يرجى إكمال الخانات المتبقية فقط."
                            ], 422);
                        }
                    } else {
                        $missingLocked = array_diff($lockedChoiceIds, array_map('intval', $selectedChoiceIds));
                        if (!empty($missingLocked)) {
                            DB::rollBack();
                            return response()->json([
                                'status' => 'error',
                                'message' => "لا يمكن إلغاء الاختيارات السابقة المثبتة (عدد {$lockedCount}). يرجى اختيار المرشحين الإضافيين فقط."
                            ], 422);
                        }
                    }
                }

                $isCorrect = false; // Votes don't have a correct answer
            } elseif ($quizQuestion->answer_type === 'ordering') {
                $answerType = 'ordering';
                $selectedChoiceIds = $validated['answer'];

                $isCorrect = true;

                if ($quizQuestion->groups_count && $quizQuestion->groups_count > 0) {
                    $groups = $quizQuestion->choices->filter(fn($c) => !empty($c->group_name))->groupBy('group_name');

                    if (count($selectedChoiceIds) !== $quizQuestion->choices->count()) {
                        $isCorrect = false;
                    } else {
                        $correctGroups = [];
                        $groupSizes = [];
                        foreach ($groups as $groupName => $groupChoices) {
                            $correctGroups[] = array_map('intval', $groupChoices->pluck('id')->toArray());
                            $groupSizes[] = $groupChoices->count();
                        }

                        $submittedChunks = [];
                        $currentIndex = 0;
                        foreach ($groupSizes as $size) {
                            $submittedChunks[] = array_map('intval', array_slice($selectedChoiceIds, $currentIndex, $size));
                            $currentIndex += $size;
                        }

                        $matched = [];
                        foreach ($submittedChunks as $chunk) {
                            $found = false;
                            foreach ($correctGroups as $gIdx => $correctIds) {
                                if (isset($matched[$gIdx])) continue;
                                if ($chunk === $correctIds) {
                                    $matched[$gIdx] = true;
                                    $found = true;
                                    break;
                                }
                            }
                            if (!$found) {
                                $isCorrect = false;
                                break;
                            }
                        }

                        if (count($matched) !== count($correctGroups)) {
                            $isCorrect = false;
                        }
                    }
                } else {
                    $correctChoiceIds = $quizQuestion->choices()->orderBy('id')->pluck('id')->toArray();

                    if (count($selectedChoiceIds) !== count($correctChoiceIds)) {
                        $isCorrect = false;
                    } else {
                        foreach ($correctChoiceIds as $index => $correctId) {
                            if ((int)$selectedChoiceIds[$index] !== (int)$correctId) {
                                $isCorrect = false;
                                break;
                            }
                        }
                    }
                }

                $answerData = json_encode($selectedChoiceIds);
            } elseif ($quizQuestion->answer_type === 'true_false') {
                $answerType = 'true_false';
                $selectedAnswers = $validated['answer'];

                $isCorrect = true;
                $correctChoicesCount = $quizQuestion->choices->count();

                if (count($selectedAnswers) !== $correctChoicesCount || $correctChoicesCount === 0) {
                    $isCorrect = false;
                } else {
                    foreach ($quizQuestion->choices as $choice) {
                        if (!isset($selectedAnswers[$choice->id])) {
                            $isCorrect = false;
                            break;
                        }

                        $submittedVal = $selectedAnswers[$choice->id];
                        $submittedBool = ($submittedVal === '1' || $submittedVal === 'true' || $submittedVal === 1 || $submittedVal === true);
                        $actualBool = (bool)$choice->is_correct;

                        if ($submittedBool !== $actualBool) {
                            $isCorrect = false;
                            break;
                        }
                    }
                }

                $answerData = json_encode($selectedAnswers);
            } elseif ($quizQuestion->answer_type === 'fill_blank') {
                $answerType = 'fill_blank';
                $choiceId = (int)$validated['answer'];
                $selectedChoice = $quizQuestion->choices->firstWhere('id', $choiceId);
                $isCorrect = $selectedChoice && (bool)$selectedChoice->is_correct;
                $answerData = (string)$choiceId;
            } elseif ($quizQuestion->answer_type === 'survey') {
                $quizQuestion->loadMissing('surveyItems');
                $answerType = 'survey';
                $isCorrect = true;
                $payload = [];
                foreach ($quizQuestion->surveyItems as $item) {
                    $raw = $validated['survey_item'][$item->id] ?? null;
                    if ($item->response_kind === 'rating') {
                        $payload[$item->id] = ['k' => 'rating', 'v' => (int)$raw];
                    } elseif ($item->response_kind === 'number') {
                        $payload[$item->id] = ['k' => 'number', 'v' => (int)$raw];
                    } else {
                        $payload[$item->id] = ['k' => 'text', 'v' => (string)$raw];
                    }
                }
                $answerData = json_encode($payload, JSON_UNESCAPED_UNICODE);
            } else {
                $answerType = 'custom';
                $isCorrect = true;
                $answerData = (string)$validated['answer'];
            }

            // 6. Create or update the QuizAnswer record
            if ($isVoteUpdate && $existingAnswer) {
                $existingAnswer->update([
                    'answer' => $answerData,
                    'answer_type' => $answerType,
                    'is_correct' => false,
                ]);

                if ($isCompletingPartialVote) {
                    $existingAnswer->touch();
                }

                // Clear previous choices and insert updated choices preserving is_locked
                $existingAnswer->selectedChoices()->delete();
                foreach ($selectedChoiceIds as $choiceId) {
                    $existingAnswer->selectedChoices()->create([
                        'quiz_question_choice_id' => $choiceId,
                        'is_locked' => in_array((int) $choiceId, $lockedChoiceIds, true),
                    ]);
                }

                $existingAnswer->unsetRelation('selectedChoices');
                $existingAnswer->load(['selectedChoices.choice']);
                $quizAnswer = $existingAnswer;
            } else {
                $quizAnswer = QuizAnswer::create([
                    'quiz_question_id' => $quizQuestion->id,
                    'person_id' => $targetPersonId,
                    'user_id' => $targetUserId,
                    'answer' => $answerData,
                    'answer_type' => $answerType,
                    'is_correct' => $isCorrect,
                ]);

                // 7. Store selected choices mapping in quiz_answer_choices
                if (($quizQuestion->is_multiple_selections && $quizQuestion->answer_type === 'multiple_choice') || $quizQuestion->answer_type === 'ordering') {
                    $selectedChoiceIds = $validated['answer'];
                    foreach ($selectedChoiceIds as $choiceId) {
                        $quizAnswer->selectedChoices()->create([
                            'quiz_question_choice_id' => $choiceId,
                        ]);
                    }
                } elseif ($quizQuestion->answer_type === 'true_false') {
                    foreach ($validated['answer'] as $choiceId => $val) {
                        $quizAnswer->selectedChoices()->create([
                            'quiz_question_choice_id' => $choiceId,
                        ]);
                    }
                } elseif ($quizQuestion->answer_type === 'vote') {
                    foreach ($selectedChoiceIds as $choiceId) {
                        $quizAnswer->selectedChoices()->create([
                            'quiz_question_choice_id' => $choiceId,
                            'is_locked' => false,
                        ]);
                    }
                    $quizAnswer->load(['selectedChoices.choice']);
                } elseif ($quizQuestion->answer_type === 'fill_blank') {
                    $quizAnswer->selectedChoices()->create([
                        'quiz_question_choice_id' => (int)$validated['answer'],
                    ]);
                }
            }

            // Invalidate the cache for user answers immediately
            if ($targetPersonId) {
                Cache::forget("user_answered_q_p_{$targetPersonId}");
                Cache::forget("user_answered_data_p_{$targetPersonId}");
            }
            if ($targetUserId) {
                Cache::forget("user_answered_q_u_{$targetUserId}");
                Cache::forget("user_answered_data_u_{$targetUserId}");
            }
            if (!empty($validated['phone'])) {
                Cache::forget("user_answered_q_ph_{$validated['phone']}");
                Cache::forget("user_answered_data_ph_{$validated['phone']}");
                Cache::forget("user_answered_q_{$validated['phone']}");
                Cache::forget("user_answered_questions_{$validated['phone']}");
            }
            // Invalidate vote results cache so the next request gets fresh aggregates
            Cache::forget("vote_results_{$quizQuestion->id}");

            DB::commit();

            $firstVotedAt = $quizAnswer->created_at;
            $responseLockedChoices = $isVoteType
                ? $quizAnswer->selectedChoices->where('is_locked', true)->pluck('quiz_question_choice_id')->map(fn($id) => (int)$id)->values()->toArray()
                : [];
            $responseLockedPersonIds = ($isVoteType && $quizQuestion->isOpenTreeVoting())
                ? $quizAnswer->selectedChoices->where('is_locked', true)->map(fn($sc) => (int) $sc->choice?->person_id)->filter()->values()->toArray()
                : [];
            $editBaseTime = (!empty($responseLockedChoices) && $quizAnswer->updated_at)
                ? $quizAnswer->updated_at
                : $firstVotedAt;
            $voteEditHours = $quizQuestion->vote_edit_duration_hours ?? 24;
            $editUntil = null;
            $canEdit = false;
            $secondsRemaining = null;

            if ($isVoteType && !$quizQuestion->hasEnded() && !$quizCompetition->show_draw_only) {
                if ($voteEditHours === -1) {
                    $canEdit = true;
                    $editUntil = null;
                    $secondsRemaining = null;
                } elseif ($voteEditHours === 0) {
                    $canEdit = false;
                    $editUntil = null;
                    $secondsRemaining = 0;
                } else {
                    $editUntilCarbon = $editBaseTime->copy()->addHours($voteEditHours);
                    $canEdit = now()->lt($editUntilCarbon);
                    $editUntil = $editUntilCarbon->toIso8601String();
                    $secondsRemaining = max(0, now()->diffInSeconds($editUntilCarbon, false));
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => $isCompletingPartialVote ? 'تم استكمال تصويتك بنجاح.' : ($isVoteUpdate ? 'تم تعديل تصويتك بنجاح.' : 'تم تسجيل مشاركتك بنجاح.'),
                'data' => [
                    'is_correct' => $isCorrect,
                    'is_updated' => $isVoteUpdate,
                    'cooldown_ends_at' => now()->addHours(2)->toIso8601String(),
                    'user_vote' => $isVoteType ? [
                        'can_edit' => $canEdit,
                        'needs_completion' => false,
                        'first_voted_at' => $firstVotedAt->toIso8601String(),
                        'edit_until' => $editUntil,
                        'seconds_remaining' => $secondsRemaining,
                        'selected_choices' => $quizAnswer->selectedChoices->pluck('quiz_question_choice_id')->map(fn($id) => (int)$id)->values()->toArray(),
                        'selected_person_ids' => $quizQuestion->isOpenTreeVoting()
                            ? $quizAnswer->selectedChoices->map(fn($sc) => (int) $sc->choice?->person_id)->filter()->values()->toArray()
                            : [],
                        'locked_choices' => $responseLockedChoices,
                        'locked_person_ids' => $responseLockedPersonIds,
                    ] : null,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'حدث خطأ أثناء حفظ الإجابة: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get live poll results.
     * Caches result to protect database from slam.
     */
    public function getVoteResults(QuizCompetition $quizCompetition, QuizQuestion $quizQuestion): JsonResponse
    {
        if ($quizQuestion->answer_type !== 'vote') {
            return response()->json([
                'status' => 'error',
                'message' => 'هذا السؤال ليس تصويتاً.'
            ], 400);
        }

        if ($quizQuestion->quiz_competition_id != $quizCompetition->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'السؤال المحدد لا ينتمي لهذه المسابقة.'
            ], 400);
        }

        // Cache results for 10 seconds to throttle heavy hits
        $cacheKey = "vote_results_{$quizQuestion->id}";
        $data = Cache::remember($cacheKey, now()->addSeconds(5), function () use ($quizQuestion) {
            return $quizQuestion->getLiveVoteResultsData();
        });

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    private function sanitizeDescription(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $clean = trim(strip_tags($html, '<img><video><iframe>'));
        if ($clean === '' || $clean === '&nbsp;' || preg_match('/^(\s*<p>\s*(<br\s*\/?>|&nbsp;|\s*)*<\/p>\s*)+$/i', $html)) {
            return null;
        }

        return $html;
    }
    /**
     * جلب عُقد شجرة العائلة للمرشحين لتطبيق الجوال مع تطبيق ضوابط الجنس وحالة الوفاة والواتساب
     */
    public function candidateTreeNodes(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));
        $parentId = $request->query('parent_id');
        $gender = $request->query('gender'); // 'male' | 'female' | 'all'
        $hasWhatsappOnly = $request->boolean('has_whatsapp_only', false);
        $excludeDeceased = $request->boolean('exclude_deceased', true);

        $questionId = $request->query('question_id');
        $walkingEnabled = $request->boolean('walking_required', false);
        $walkingDays = max(1, (int) $request->query('walking_days', 3));
        $walkingMinSteps = max(0, (int) $request->query('walking_min_steps', 0));
        $walkingType = $request->query('walking_type', 'any_day');

        $competitionId = $request->query('competition_id');
        if (! $questionId && $competitionId) {
            $foundQ = \App\Models\QuizQuestion::where('quiz_competition_id', (int) $competitionId)
                ->where('answer_type', 'vote')
                ->first();
            if ($foundQ) {
                $questionId = $foundQ->id;
            }
        }

        $excludeOutsideFamily = false;
        $excludeMaternalChildren = false;
        $excludeBreastfed = false;
        $excludeOriginalFamily = false;
        $rules = [];

        if ($questionId) {
            $question = \App\Models\QuizQuestion::find((int) $questionId);
            if ($question) {
                $rules = $question->getCandidateRules();
                if (!empty($rules['allowed_gender']) && $rules['allowed_gender'] !== 'all') {
                    $gender = $rules['allowed_gender'];
                } elseif ($question->isLinkedToWalking() && in_array($question->getPredictionTargetGender(), ['male', 'female'], true)) {
                    $gender = $question->getPredictionTargetGender();
                }
                if (! $request->has('has_whatsapp_only') && ! empty($rules['require_whatsapp'])) {
                    $hasWhatsappOnly = true;
                }
                if (! $request->has('exclude_deceased') && isset($rules['exclude_deceased'])) {
                    $excludeDeceased = (bool) $rules['exclude_deceased'];
                }
                if (! $request->has('walking_required') && ! empty($rules['walking_condition']['enabled'])) {
                    $walkingEnabled = true;
                    $walkingDays = (int) ($rules['walking_condition']['days_window'] ?? 3);
                    $walkingMinSteps = (int) ($rules['walking_condition']['min_daily_steps'] ?? 0);
                    $walkingType = $rules['walking_condition']['condition_type'] ?? 'any_day';
                }
                $excludeOutsideFamily = (bool) ($rules['exclude_outside_family'] ?? false);
                $excludeMaternalChildren = (bool) ($rules['exclude_maternal_children'] ?? false);
                $excludeBreastfed = (bool) ($rules['exclude_breastfed'] ?? false);
                $excludeOriginalFamily = (bool) ($rules['exclude_original_family'] ?? false);
            }
        }

        if ($request->has('exclude_outside_family')) {
            $rules['exclude_outside_family'] = $request->boolean('exclude_outside_family');
        }
        if ($request->has('exclude_maternal_children')) {
            $rules['exclude_maternal_children'] = $request->boolean('exclude_maternal_children');
        }
        if ($request->has('exclude_breastfed')) {
            $rules['exclude_breastfed'] = $request->boolean('exclude_breastfed');
        }
        if ($request->has('exclude_original_family')) {
            $rules['exclude_original_family'] = $request->boolean('exclude_original_family');
        }

        $groupService = app(\App\Services\Invitations\InvitationGroupService::class);
        $whatsappIds = array_flip($groupService->personIdsWithWhatsApp());

        $eligibleWalkingMap = [];
        if ($walkingEnabled) {
            $eligibleIds = \App\Models\QuizQuestion::getEligibleWalkingPersonIds($walkingDays, $walkingMinSteps, $walkingType);
            $eligibleWalkingMap = array_flip($eligibleIds);
        }

        $eligibilityService = app(\App\Services\Quiz\CandidateEligibilityService::class);

        if ($search !== '') {
            // في البحث: شمول كافة الفئات المتاحة لتمكين البحث عن أبناء نساء العائلة والرضاعة وسلالة العائلة
            $query = \App\Models\Person::query()
                ->select(['id', 'first_name', 'last_name', 'gender', 'photo_url', 'death_date', 'parent_id', 'mother_id', 'from_outside_the_family'])
                ->with(['parent.parent.parent.parent.parent', 'mother']);

            $query->searchByFullName($search);

            if ($gender && $gender !== 'all') {
                $query->orderByRaw("CASE WHEN gender = ? THEN 0 ELSE 1 END ASC", [$gender]);
            }
            if ($excludeDeceased) {
                $query->orderByRaw("CASE WHEN death_date IS NULL THEN 0 ELSE 1 END ASC");
            }

            $rawPeople = $query->withCount('children')->limit(60)->get();
            $people = app(\App\Services\PersonSearchService::class)->rankCollection($rawPeople, $search);
        } else {
            // في تصفح أعمدة الشجرة الهرمية: تظل لأفراد سلالة العائلة الأصلية
            $query = \App\Models\Person::query()
                ->select(['id', 'first_name', 'last_name', 'gender', 'photo_url', 'death_date', 'parent_id', 'from_outside_the_family'])
                ->with(['parent.parent.parent.parent.parent'])
                ->where('from_outside_the_family', false);

            if ($parentId) {
                $query->where('parent_id', (int) $parentId);
            } else {
                $query->whereNull('parent_id');
            }

            $people = $query->withCount('children')->orderBy('display_order')->orderBy('id')->get();
        }

        $nodes = $people->map(function ($person) use ($whatsappIds, $hasWhatsappOnly, $gender, $excludeDeceased, $walkingEnabled, $walkingDays, $eligibleWalkingMap, $eligibilityService, $rules) {
            $hasWhatsapp = isset($whatsappIds[$person->id]);
            $isDeceased = $person->death_date !== null;

            $genderMatch = (! $gender || $gender === 'all' || $person->gender === $gender);
            $deceasedMatch = (! $excludeDeceased || ! $isDeceased);
            $whatsappMatch = (! $hasWhatsappOnly || $hasWhatsapp);
            $walkingMatch = (! $walkingEnabled || isset($eligibleWalkingMap[$person->id]));

            // فحص مطابقة الفئة العائلية (سلالة العائلة، أبناء نساء العائلة، بالرضاعة، من خارج العائلة)
            $category = $eligibilityService->resolveCategory($person);
            $categoryCheck = $eligibilityService->isCategoryAllowed($category, $rules);
            $categoryMatch = $categoryCheck['allowed'];
            $categoryDetails = $eligibilityService->getCategoryDetails($category);

            $selectable = $genderMatch && $deceasedMatch && $whatsappMatch && $walkingMatch && $categoryMatch;

            $unselectableReason = null;
            if (! $deceasedMatch) {
                $unselectableReason = 'متوفى (' . ($person->gender === 'female' ? 'رحمها الله' : 'رحمه الله') . ')';
            } elseif (! $categoryMatch) {
                $unselectableReason = $categoryCheck['reason'];
            } elseif (! $genderMatch) {
                $unselectableReason = $gender === 'male' ? 'غير متاح (رجال فقط)' : 'غير متاح (نساء فقط)';
            } elseif (! $whatsappMatch) {
                $unselectableReason = 'غير متاح (لا يملك واتساب)';
            } elseif (! $walkingMatch) {
                $dayText = $walkingDays === 1 ? 'اليوم' : ($walkingDays === 2 ? 'اليومين الماضيين' : "آخر {$walkingDays} أيام");
                $unselectableReason = "غير متاح (لم يسجل نشاط مشي خلال {$dayText})";
            }

            return [
                'id' => $person->id,
                'name' => $person->name,
                'first_name' => $person->first_name,
                'gender' => $person->gender,
                'photo_url' => $person->avatar,
                'has_whatsapp' => $hasWhatsapp,
                'children_count' => $person->children_count ?? 0,
                'is_deceased' => $isDeceased,
                'has_walked' => ! $walkingEnabled || isset($eligibleWalkingMap[$person->id]),
                'candidate_category' => $category,
                'candidate_category_label' => $categoryDetails['label'],
                'candidate_category_badge' => $categoryDetails['badge'],
                'selectable' => $selectable,
                'unselectable_reason' => $unselectableReason,
            ];
        });

        if ($search !== '') {
            $nodes = $nodes->sortByDesc('selectable')->values();
        } else {
            $nodes = $nodes->values();
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'nodes' => $nodes,
            ],
        ]);
    }

    /**
     * Get day-by-day Bar Chart Race timeline data for a walking competition question.
     */
    public function walkingRaceTimeline(Request $request, QuizQuestion $question): JsonResponse
    {
        if (! $question->isWalkingChallenge() || ! $question->competition) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'data' => null,
                'message' => 'تحدي المشي غير موجود.',
                'errors' => null,
            ], 404);
        }

        $identityResolver = app(\App\Services\SiteIdentityResolver::class);
        $identity = $identityResolver->resolve($request);
        $authMethod = $identity['auth_method'] ?? null;

        $token = \App\Services\SiteMobileAccessToken::extractFromRequest($request);
        if ($token && \App\Services\SiteMobileAccessToken::valid($token)) {
            $tokenPayload = \App\Services\SiteMobileAccessToken::getPayload($token);
            if (empty($tokenPayload['person_id'])) {
                $authMethod = 'site_password';
            }
        }

        $person = null;
        if ($identity && ! empty($identity['person_id'])) {
            $person = \App\Models\Person::find($identity['person_id']);
        }

        if (! $question->isVisibleTo($person, $authMethod) || ! $question->canViewWalkingLeaderboard($person)) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'data' => null,
                'message' => 'عذراً، هذا الترتيب غير متاح لك.',
                'errors' => null,
            ], 403);
        }

        if (! $question->isWalkingRaceEnabled()) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'data' => null,
                'message' => 'ميزة سباق الترتيب اليومي غير مفعلة حالياً لهذا التحدي.',
                'errors' => null,
            ], 403);
        }

        $timeline = app(\App\Services\Walking\WalkingCompetitionScoringService::class)
            ->getDailyRaceTimeline($question->competition, $question, $person);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'data' => $timeline,
            'message' => 'تم جلب سباق الترتيب اليومي بنجاح.',
            'errors' => null,
        ]);
    }
}

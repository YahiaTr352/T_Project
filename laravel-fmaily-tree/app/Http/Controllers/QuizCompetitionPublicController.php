<?php

namespace App\Http\Controllers;

use App\Models\QuizAnswer;
use App\Models\QuizCompetition;
use App\Models\QuizQuestion;
use App\Models\QuizRegistration;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class QuizCompetitionPublicController extends Controller
{
    public function show(QuizCompetition $quizCompetition): View
    {
        $quizCompetition->load(['questions.choices', 'questions.surveyItems', 'questions.winners.user', 'questions.answers.user']);

        $now = now();
        $comp = $quizCompetition;
        $questions = $comp->questions;
        $upcomingQuestions = collect();
        $endedQuestions = collect();

        if ($comp->start_at && $comp->end_at) {
            if ($now->gte($comp->start_at) && $now->lte($comp->end_at)) {
                $questions = $comp->questions;
            } elseif ($now->lt($comp->start_at)) {
                $questions = collect();
                $upcomingQuestions = $comp->questions;
            } else {
                $questions = collect();
                $endedQuestions = $comp->questions;
            }
        }

        // إضافة معلومات عن إجابات المستخدم لكل سؤال بناءً على session
        $userAnsweredQuestions = [];
        foreach ($questions as $question) {
            $lastAnsweredAt = session('quiz_answered_'.$question->id);
            $userAnsweredQuestions[$question->id] = ! empty($lastAnsweredAt);
        }

        return view('web-site.quiz-competitions.show', compact('quizCompetition', 'questions', 'upcomingQuestions', 'endedQuestions', 'userAnsweredQuestions'));
    }

    public function question(QuizCompetition $quizCompetition, QuizQuestion $quizQuestion): View|RedirectResponse|Response
    {
        // if ($quizQuestion->quiz_competition_id !== $quizCompetition->id) {
        //     abort(404);
        // }

        $quizQuestion->load(['choices', 'surveyItems', 'winners.user', 'winners.person', 'answers.user']);

        $maleStandings = collect();
        $femaleStandings = collect();
        $viewerStanding = null;

        $identityResolver = app(\App\Services\SiteIdentityResolver::class);
        $identity = $identityResolver->resolve(request());
        $authMethod = $identity['auth_method'] ?? null;
        $viewerPersonId = $identityResolver->viewerPersonId(request());
        $viewerPerson = $viewerPersonId ? \App\Models\Person::find($viewerPersonId) : (auth()->check() ? auth()->user()->person : null);
        $viewerGender = $viewerPerson?->gender ?? 'male';

        // حماية الخصوصية: مسابقة النساء محجوبة عن الرجال والزوار (404)، ومسابقة الرجال للعرض فقط لغير الرجال
        if (! $quizQuestion->isVisibleTo($viewerPerson, $authMethod)) {
            abort(404);
        }

        if ($quizQuestion->isWalkingChallenge()) {
            $scoringService = app(\App\Services\Walking\WalkingCompetitionScoringService::class);
            $targetGender = $quizQuestion->getWalkingTargetGender();

            if ($targetGender === 'all' || $targetGender === 'male' || $targetGender === 'both_segregated') {
                $maleData = $scoringService->getOverallStandings($quizQuestion, 'male', $viewerPerson?->id);
                $maleStandings = $maleData['standings'] ?? collect();
            }
            // صدارة النساء للنساء فقط حتى في الوضع المشترك
            if ($targetGender === 'female' || ($targetGender === 'both_segregated' && $viewerPerson?->gender === 'female')) {
                $femaleData = $scoringService->getOverallStandings($quizQuestion, 'female', $viewerPerson?->id);
                $femaleStandings = $femaleData['standings'] ?? collect();
            }
            if ($viewerPerson) {
                $viewerStanding = $scoringService->getViewerStanding($quizQuestion, $viewerPerson->id, $viewerGender);
            }
        }

        $predictionData = null;
        if ($quizQuestion->isLinkedToWalking()) {
            $predService = app(\App\Services\Walking\WalkingPredictionScoringService::class);
            $predictionData = $predService->getLivePredictionStandings($quizQuestion, $viewerPerson?->id);

            // حجب صدارة وسجلات مشي المتسابقات الحقيقية عن غير النساء لضمان الخصوصية
            if (! $quizQuestion->canViewWalkingLeaderboard($viewerPerson) && is_array($predictionData)) {
                $predictionData['walking_top_ranks'] = [];
            }
        }

        $stats = [
            'total' => $quizQuestion->answers->count(),
            'correct' => $quizQuestion->answers->where('is_correct', true)->count(),
            'wrong' => $quizQuestion->answers->where('is_correct', false)->count(),
        ];

        // Fetch candidate names for animation (real users) - Available for ALL statuses
        $candidateNames = $quizQuestion->answers()
            ->where('is_correct', true)
            ->inRandomOrder()
            ->limit(50)
            ->with(['user', 'person'])
            ->get()
            ->map(fn ($a) => $a->person->name ?? $a->user->name ?? 'مجهول')
            ->filter()
            ->values()
            ->all();

        if ($quizQuestion->hasNotStarted()) {
            return view('web-site.quiz-competitions.question', [
                'quizCompetition' => $quizCompetition,
                'quizQuestion' => $quizQuestion,
                'status' => 'not_started',
                'stats' => $stats,
                'maleStandings' => $maleStandings,
                'femaleStandings' => $femaleStandings,
                'viewerStanding' => $viewerStanding,
            ]);
        }

        // السؤال لم يظهر بعد (خلال 60 ثانية من بدء المسابقة)
        $questionsVisibleAt = $quizCompetition->getQuestionsVisibleAt();
        if ($questionsVisibleAt && now()->lt($questionsVisibleAt)) {
            return view('web-site.quiz-competitions.question', [
                'quizCompetition' => $quizCompetition,
                'quizQuestion' => $quizQuestion,
                'status' => 'question_locked',
                'stats' => $stats,
                'questionsVisibleAt' => $questionsVisibleAt,
                'candidateNames' => $candidateNames,
                'maleStandings' => $maleStandings,
                'femaleStandings' => $femaleStandings,
                'viewerStanding' => $viewerStanding,
            ]);
        }

        if ($quizQuestion->hasEnded()) {
            $selectionAt = $quizCompetition->end_at ? $quizCompetition->end_at->copy() : null;

            if ($selectionAt && now()->gte($selectionAt)) {
                // الكاش منفصل حسب الجنس لأن الصدارة المعروضة تختلف (صدارة النساء للنساء فقط)
                $endedCacheSuffix = ($viewerPerson?->gender === 'female') ? 'f' : 'm';
                $cacheKey = 'quiz_question_ended_'.$quizQuestion->id.'_'.$endedCacheSuffix;
                $cachedHtml = Cache::get($cacheKey);
                if ($cachedHtml !== null) {
                    return response($cachedHtml)->header('Content-Type', 'text/html; charset=UTF-8');
                }

                if ($quizQuestion->isWalkingChallenge()) {
                    if ($quizQuestion->winners->count() === 0) {
                        $scoringService = app(\App\Services\Walking\WalkingCompetitionScoringService::class);
                        $scoringService->finalizeWinners($quizQuestion);
                    }
                } elseif ($quizQuestion->isLinkedToWalking()) {
                    if ($quizQuestion->winners->count() === 0) {
                        $predService = app(\App\Services\Walking\WalkingPredictionScoringService::class);
                        $predService->finalizePredictionWinners($quizQuestion);
                    }
                } elseif ($quizQuestion->winners->count() === 0) {
                    $lockKey = 'quiz-winner-selection-'.$quizQuestion->id;
                    Cache::lock($lockKey, 15)->block(10, function () use ($quizQuestion) {
                        $quizQuestion->refresh();
                        $quizQuestion->load(['winners']);
                        if ($quizQuestion->winners->count() === 0 && $quizQuestion->answers()->where('is_correct', true)->exists()) {
                            $quizQuestion->selectRandomWinners();
                        }
                    });
                }

                $quizQuestion->refresh();
                $quizQuestion->load(['choices', 'surveyItems', 'winners.user', 'winners.person', 'answers.user', 'answers.person']);

                $stats = [
                    'total' => $quizQuestion->answers->count(),
                    'correct' => $quizQuestion->answers->where('is_correct', true)->count(),
                    'wrong' => $quizQuestion->answers->where('is_correct', false)->count(),
                ];

                $html = view('web-site.quiz-competitions.question', [
                    'quizCompetition' => $quizCompetition,
                    'quizQuestion' => $quizQuestion,
                    'status' => 'ended',
                    'stats' => $stats,
                    'selectionAt' => $selectionAt,
                    'candidateNames' => $candidateNames,
                    'maleStandings' => $maleStandings,
                    'femaleStandings' => $femaleStandings,
                    'viewerStanding' => $viewerStanding,
                ])->render();

                Cache::put($cacheKey, $html, now()->addMinutes(5));

                return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
            }

            return view('web-site.quiz-competitions.question', [
                'quizCompetition' => $quizCompetition,
                'quizQuestion' => $quizQuestion,
                'status' => 'ended',
                'stats' => $stats,
                'selectionAt' => $selectionAt,
                'candidateNames' => $candidateNames,
                'maleStandings' => $maleStandings,
                'femaleStandings' => $femaleStandings,
                'viewerStanding' => $viewerStanding,
            ]);
        }

        $cooldownHours = 2;
        $lastAnsweredAt = session('quiz_answered_'.$quizQuestion->id);
        $canAnswer = true;

        $linkedUserIds = [];
        if (auth()->check()) {
            $linkedUserIds[] = auth()->id();
        }
        if ($viewerPerson) {
            $linkedUserIds = array_merge($linkedUserIds, $viewerPerson->getLinkedUserIds());
        }

        $existingVoteAnswer = null;
        $needsVoteCompletion = false;
        $lockedVoteChoiceIds = [];
        $lockedVotePersons = [];

        if ($quizQuestion->isVote()) {
            $requiredSelections = max(1, (int) ($quizQuestion->vote_max_selections ?? 1));
            if ($viewerPerson) {
                $existingVoteAnswer = QuizAnswer::with(['selectedChoices.choice.person'])
                    ->where('quiz_question_id', $quizQuestion->id)
                    ->where(function ($q) use ($viewerPerson, $linkedUserIds) {
                        $q->where('person_id', $viewerPerson->id);
                        if (!empty($linkedUserIds)) {
                            $q->orWhereIn('user_id', array_unique($linkedUserIds));
                        }
                    })
                    ->oldest()
                    ->first();
            } elseif (!empty($linkedUserIds)) {
                $existingVoteAnswer = QuizAnswer::with(['selectedChoices.choice.person'])
                    ->where('quiz_question_id', $quizQuestion->id)
                    ->whereIn('user_id', array_unique($linkedUserIds))
                    ->oldest()
                    ->first();
            }

            if ($existingVoteAnswer) {
                $existingCount = $existingVoteAnswer->selectedChoices->count();
                if ($existingCount > 0 && $existingCount < $requiredSelections) {
                    $needsVoteCompletion = true;
                    $canAnswer = true;
                    session()->forget('quiz_answered_'.$quizQuestion->id);
                    $lockedVoteChoiceIds = $existingVoteAnswer->selectedChoices
                        ->pluck('quiz_question_choice_id')
                        ->map(fn($id) => (int) $id)
                        ->values()
                        ->toArray();
                    $lockedVotePersons = $existingVoteAnswer->selectedChoices
                        ->map(function ($sc) {
                            $p = $sc->choice?->person;
                            if (!$p) {
                                return null;
                            }
                            return [
                                'id' => (int) $p->id,
                                'choice_id' => (int) $sc->quiz_question_choice_id,
                                'name' => $p->name,
                                'avatar' => $p->avatar,
                            ];
                        })
                        ->filter()
                        ->values()
                        ->toArray();
                } else {
                    $canAnswer = false;
                }
            } elseif ($lastAnsweredAt) {
                $canAnswer = false;
            }
        } elseif ($lastAnsweredAt) {
            $lastAt = \Carbon\Carbon::parse($lastAnsweredAt);
            $canAnswer = now()->diffInHours($lastAt) >= $cooldownHours;
            if ($canAnswer) {
                session()->forget('quiz_answered_'.$quizQuestion->id);
            }
        }

        $voterEligibility = $quizQuestion->checkVoterEligibility($viewerPerson, $authMethod);
        $liveVoteResults = $quizQuestion->isVote() ? $quizQuestion->getLiveVoteResultsData() : null;

        return view('web-site.quiz-competitions.question', [
            'quizCompetition' => $quizCompetition,
            'quizQuestion' => $quizQuestion,
            'status' => 'active',
            'stats' => $stats,
            'canAnswer' => $canAnswer,
            'needsVoteCompletion' => $needsVoteCompletion,
            'lockedVoteChoiceIds' => $lockedVoteChoiceIds,
            'lockedVotePersons' => $lockedVotePersons,
            'candidateNames' => $candidateNames,
            'maleStandings' => $maleStandings,
            'femaleStandings' => $femaleStandings,
            'viewerStanding' => $viewerStanding,
            'viewerPerson' => $viewerPerson,
            'voterEligibility' => $voterEligibility,
            'liveVoteResults' => $liveVoteResults,
        ]);
    }

    public function storeAnswer(Request $request, QuizCompetition $quizCompetition, QuizQuestion $quizQuestion): RedirectResponse
    {
        if ($quizQuestion->isWalkingChallenge()) {
            return back()->with('error', 'تحدي المشي والخطوات يتم احتسابه تلقائياً من سجلات المشي عبر تطبيق العائلة.');
        }

        if ($quizCompetition->show_draw_only) {
            return back()->with('error', 'عذراً، باب الإجابة مغلق حالياً للمتابعة مع القرعة والسحب.');
        }

        // if ($quizQuestion->quiz_competition_id !== $quizCompetition->id) {
        //     abort(404);
        // }

        if ($quizQuestion->hasNotStarted()) {
            return back()->with('error', 'السؤال لم يبدأ بعد');
        }

        $questionsVisibleAt = $quizCompetition->getQuestionsVisibleAt();
        if ($questionsVisibleAt && now()->lt($questionsVisibleAt)) {
            return back()->with('error', 'السؤال يظهر بعد مرور الوقت المحدد من بدء المسابقة.');
        }

        if ($quizQuestion->hasEnded()) {
            return back()->with('error', 'انتهت فترة الإجابة على هذا السؤال');
        }

        $requiresPriorRegistration = (bool) $quizQuestion->require_prior_registration;
        $isVoteType = $quizQuestion->answer_type === 'vote';

        // 1. Resolve user identifier and authenticated session (Identity vs 112 Passcode)
        $identityResolver = app(\App\Services\SiteIdentityResolver::class);
        $identity = $identityResolver->resolve($request);
        $authMethod = $identity['auth_method'] ?? null;
        $viewerPersonId = $identityResolver->viewerPersonId($request);
        $viewerPerson = $viewerPersonId ? \App\Models\Person::find($viewerPersonId) : null;

        // التحقق من أن السؤال/المسابقة مرئية ومتاحة للمستخدم
        if (! $quizQuestion->isVisibleTo($viewerPerson, $authMethod)) {
            return back()->with('error', 'عذراً، هذه المسابقة غير متاحة لك.');
        }

        // المشاهدة للعرض فقط (نساء/زوار في مسابقة الرجال): ممنوع المشاركة أو التصويت
        if ($quizQuestion->isViewOnlyFor($viewerPerson)) {
            return back()->with('error', 'عذراً، هذه المسابقة للعرض فقط ولا تقبل مشاركتك.');
        }

        // Auto-resolve phone and name if user is verified via Identity/Person
        $resolvedPhone = $request->input('phone');
        if (!$resolvedPhone && $viewerPerson) {
            $resolvedPhone = $viewerPerson->resolveQuizPhone();

            $request->merge([
                'phone' => $resolvedPhone,
                'name' => $request->input('name') ?: $viewerPerson->name,
            ]);
        }

        // Voter eligibility verification
        if ($isVoteType || !empty($quizQuestion->voter_eligibility)) {
            $eligibility = $quizQuestion->checkVoterEligibility($viewerPerson, $authMethod);
            if (!$eligibility['eligible']) {
                return back()->withInput()->with('error', $eligibility['message']);
            }
        }

        $rules = [
            'name' => ($requiresPriorRegistration || $isVoteType || $viewerPerson) ? 'nullable|string|max:255' : 'required|string|max:255',
            'phone' => $viewerPerson ? 'nullable|string|max:20' : 'required|string|size:10|regex:/^[0-9]{10}$/',
            'is_from_ancestry' => 'nullable|in:1',
            'mother_name' => 'nullable|string|max:255',
        ];

        if ($quizQuestion->is_multiple_selections || $quizQuestion->answer_type === 'ordering' || $quizQuestion->answer_type === 'true_false') {
            $rules['answer'] = 'required|array';
            if ($quizQuestion->answer_type !== 'true_false') {
                $rules['answer.*'] = 'required|exists:quiz_question_choices,id';
            } else {
                // For true_false, keys are choice IDs, values are true/false strings or 1/0
                $rules['answer.*'] = 'required|in:1,0,true,false';
            }
        } elseif ($isVoteType) {
            $requiredSelections = max(1, (int) ($quizQuestion->vote_max_selections ?? 1));
            if ($quizQuestion->isOpenTreeVoting()) {
                $rules['answer'] = $requiredSelections > 1 ? 'required|array|size:'.$requiredSelections : 'required';
            } elseif ($requiredSelections > 1) {
                $rules['answer'] = 'required|array|size:'.$requiredSelections;
                $rules['answer.*'] = 'required|exists:quiz_question_choices,id';
            } else {
                $rules['answer'] = 'required|exists:quiz_question_choices,id';
            }
        } elseif ($quizQuestion->answer_type === 'multiple_choice') {
            $rules['answer'] = 'required|exists:quiz_question_choices,id';
        } elseif ($quizQuestion->answer_type === 'fill_blank') {
            $rules['answer'] = 'required|exists:quiz_question_choices,id';
        } elseif ($quizQuestion->answer_type === 'survey') {
            $quizQuestion->loadMissing('surveyItems');
            if ($quizQuestion->surveyItems->isEmpty()) {
                return back()->with('error', 'هذا الاستبيان غير مكتمل الإعداد.');
            }
            $rules['survey_item'] = 'required|array';
            foreach ($quizQuestion->surveyItems as $item) {
                $key = 'survey_item.'.$item->id;
                if ($item->response_kind === 'rating') {
                    $rules[$key] = 'required|integer|min:1|max:'.max(1, (int) $item->rating_max);
                } elseif ($item->response_kind === 'number') {
                    $min = (int) ($item->number_min ?? 0);
                    $max = (int) ($item->number_max ?? 100);
                    if ($min > $max) {
                        $min = 0;
                        $max = 100;
                    }
                    $rules[$key] = 'required|integer|min:'.$min.'|max:'.$max;
                } else {
                    $rules[$key] = 'required|string|max:2000';
                }
            }
        } else {
            $rules['answer'] = 'required|string|max:1000';
        }

        $validated = $request->validate($rules, [
            'name.required' => 'الاسم مطلوب',
            'phone.required' => 'رقم الهاتف مطلوب',
            'phone.size' => 'يجب أن يكون رقم الهاتف 10 أرقام بالضبط',
            'phone.regex' => 'يجب أن يكون رقم الهاتف 10 أرقام فقط',
            'answer.required' => 'الإجابة مطلوبة',
            'answer.size' => 'يجب اختيار عدد ('.($quizQuestion->vote_max_selections ?? 1).') مرشحين إجبارياً لإتمام التصويت.',
            'answer.max' => 'لا يمكنك اختيار أكثر من '.($quizQuestion->vote_max_selections ?? 1).' خيارات',
        ]);

        if (empty($validated['phone']) && $resolvedPhone) {
            $validated['phone'] = $resolvedPhone;
        }
        if (empty($validated['name']) && $viewerPerson) {
            $validated['name'] = $viewerPerson->name;
        }

        // Open tree voting candidates validation
        $openTreePersonIds = [];
        if ($isVoteType && $quizQuestion->isOpenTreeVoting()) {
            $requiredSelections = max(1, (int) ($quizQuestion->vote_max_selections ?? 1));
            $rawAnswer = $request->input('answer');
            $submittedPersons = is_array($rawAnswer) ? array_filter($rawAnswer) : array_filter([$rawAnswer]);
            $submittedPersons = array_values(array_map('intval', $submittedPersons));

            if (empty($submittedPersons)) {
                return back()->withInput()->with('error', "يرجى اختيار {$requiredSelections} مرشحين من شجرة العائلة للمشاركة في التصويت.");
            }
            if (count($submittedPersons) < $requiredSelections) {
                $remaining = $requiredSelections - count($submittedPersons);
                return back()->withInput()->with('error', "يجب اختيار {$requiredSelections} مرشحين إجبارياً لإتمام التصويت. فاضل {$remaining} " . ($remaining === 1 ? 'مرشح' : 'مرشحين') . ".");
            }
            if (count($submittedPersons) > $requiredSelections) {
                return back()->withInput()->with('error', "لا يمكنك اختيار أكثر من {$requiredSelections} مرشحين في هذا التصويت.");
            }
            if (count($submittedPersons) !== count(array_unique($submittedPersons))) {
                return back()->withInput()->with('error', 'لا يمكن تكرار نفس المرشح في أكثر من خانة.');
            }

            foreach ($submittedPersons as $pId) {
                $candPerson = \App\Models\Person::find($pId);
                if (!$candPerson) {
                    return back()->withInput()->with('error', 'أحد المرشحين المختارين غير موجود في شجرة العائلة.');
                }
                $eligibility = $quizQuestion->checkCandidateEligibility($candPerson);
                if (!$eligibility['eligible']) {
                    return back()->withInput()->with('error', $eligibility['message'] ?? 'المرشح (' . $candPerson->name . ') غير مؤهل للتصويت.');
                }
            }
            $openTreePersonIds = $submittedPersons;
        }

        // Participation requirement: verify phone exists (bypassed for identity-verified family members)
        if ($requiresPriorRegistration && !$viewerPerson) {
            $phoneExists = User::where('phone', $validated['phone'])->exists();
            if (! $phoneExists) {
                $actionMsg = $isVoteType ? 'التصويت' : 'المشاركة';
                return back()->withInput()->with('error', 'رقم الهاتف غير مسجل. لا يمكنك '.$actionMsg.' إلا لمن سبق تسجيله برقم هاتفه.');
            }
        }

        try {
            DB::beginTransaction();

            // إذا تم إدخال اسم الأم، يعتبر المستخدم من الأنساب تلقائياً
            $hasMotherName = ! empty($validated['mother_name']);
            $isFromAncestry = isset($validated['is_from_ancestry']) && $validated['is_from_ancestry'] || $hasMotherName;

            // التحقق من وجود إجابة سابقة بنفس رقم الهاتف أو هوية الشخص
            $existingAnswerQuery = QuizAnswer::where('quiz_question_id', $quizQuestion->id);
            if ($viewerPerson) {
                $linkedUids = $viewerPerson->getLinkedUserIds();
                $existingAnswerQuery->where(function ($q) use ($viewerPerson, $validated, $linkedUids) {
                    $q->where('person_id', $viewerPerson->id);
                    if (!empty($linkedUids)) {
                        $q->orWhereIn('user_id', $linkedUids);
                    }
                    if (!empty($validated['phone'])) {
                        $q->orWhereHas('user', function ($uq) use ($validated) {
                            $uq->where('phone', $validated['phone']);
                        });
                    }
                });
            } else {
                $existingAnswerQuery->whereHas('user', function ($q) use ($validated) {
                    $q->where('phone', $validated['phone']);
                });
            }
            $existingAnswer = $existingAnswerQuery->with(['selectedChoices.choice'])->oldest()->first();

            $isVoteUpdate = false;
            $isCompletingPartialVote = false;
            $lockedChoiceIds = [];

            if ($existingAnswer) {
                if ($isVoteType) {
                    $requiredSelections = max(1, (int) ($quizQuestion->vote_max_selections ?? 1));
                    $existingChoicesColl = $existingAnswer->selectedChoices;
                    $existingCount = $existingChoicesColl->count();
                    $isCompletingPartialVote = $existingCount > 0 && $existingCount < $requiredSelections;

                    if ($isCompletingPartialVote) {
                        $lockedChoiceIds = $existingChoicesColl
                            ->pluck('quiz_question_choice_id')
                            ->map(fn($id) => (int) $id)
                            ->values()
                            ->toArray();
                        $isVoteUpdate = true;
                    } else {
                        $lockedChoiceIds = $existingChoicesColl
                            ->where('is_locked', true)
                            ->pluck('quiz_question_choice_id')
                            ->map(fn($id) => (int) $id)
                            ->values()
                            ->toArray();

                        $editBaseTime = (!empty($lockedChoiceIds) && $existingAnswer->updated_at)
                            ? $existingAnswer->updated_at
                            : $existingAnswer->created_at;
                        $voteEditHours = $quizQuestion->vote_edit_duration_hours ?? 24;

                        if ($voteEditHours === 0) {
                            DB::rollBack();
                            return back()->withInput()->with('error', 'عذراً، غير مسموح بتعديل التصويت بعد إرساله.');
                        }

                        if ($voteEditHours > 0) {
                            $editDeadline = $editBaseTime->copy()->addHours($voteEditHours);
                            if (now()->gt($editDeadline)) {
                                DB::rollBack();
                                return back()->withInput()->with('error', "عذراً، انتهت المهلة المحددة لتعديل التصويت ({$voteEditHours} ساعة).");
                            }
                        }

                        $isVoteUpdate = true;
                    }
                } else {
                    $cooldownHours = 2;
                    $lastAnswerTime = $existingAnswer->created_at;
                    $canAnswerAgain = now()->diffInHours($lastAnswerTime) >= $cooldownHours;

                    if (! $canAnswerAgain) {
                        DB::rollBack();

                        return back()->with('error', 'لقد أجبت على هذا السؤال مسبقاً.');
                    }
                }
            }

            // فصل هوية أفراد العائلة (Person) عن حسابات الزوار (Passcode 112 / User)
            $targetUserId = null;
            $targetPersonId = null;

            if ($viewerPerson) {
                $targetPersonId = $viewerPerson->id;
                // إجابات أفراد العائلة الموثقين ترتبط مباشرة بـ person_id ويبقى user_id فارغاً
            } else {
                // مستخدمو الرمز 112 أو الزوار
                if ($requiresPriorRegistration) {
                    $user = User::where('phone', $validated['phone'])->latest()->first();
                    if (!$user) {
                        DB::rollBack();
                        $actionMsg = $isVoteType ? 'التصويت' : 'المشاركة';
                        return back()->withInput()->with('error', 'رقم الهاتف غير مسجل. لا يمكنك '.$actionMsg.' إلا لمن سبق تسجيله برقم هاتفه.');
                    }
                } else {
                    $user = User::where('phone', $validated['phone'])->latest()->first();
                    if (!$user) {
                        $user = User::create([
                            'name' => $validated['name'] ?? ('ضيف_' . $validated['phone']),
                            'phone' => $validated['phone'],
                            'password' => Hash::make(uniqid()),
                            'status' => 1,
                            'is_from_ancestry' => $isFromAncestry,
                            'mother_name' => $hasMotherName ? $validated['mother_name'] : null,
                        ]);
                    }
                }
                $targetUserId = $user->id;
            }

            if ($targetPersonId) {
                QuizRegistration::firstOrCreate([
                    'quiz_competition_id' => $quizCompetition->id,
                    'person_id' => $targetPersonId,
                ]);
            } else {
                QuizRegistration::firstOrCreate([
                    'quiz_competition_id' => $quizCompetition->id,
                    'user_id' => $targetUserId,
                ]);
            }

            $isCorrect = false;
            $answerType = 'custom';
            $answerData = '';
            if ($quizQuestion->answer_type !== 'survey') {
                $answerData = $validated['answer'];
            }

            if ($quizQuestion->answer_type === 'multiple_choice') {
                $answerType = 'choice';

                if ($quizQuestion->is_multiple_selections) {
                    $selectedChoiceIds = $validated['answer']; // Array of ids
                    $correctChoiceIds = $quizQuestion->choices()->where('is_correct', true)->pluck('id')->toArray();

                    $selectedCorrectCount = 0;
                    $hasIncorrectChoice = false;

                    foreach ($selectedChoiceIds as $choiceId) {
                        if (in_array((int) $choiceId, $correctChoiceIds)) {
                            $selectedCorrectCount++;
                        } else {
                            $hasIncorrectChoice = true;
                            break;
                        }
                    }

                    // Must select ALL correct choices and NO incorrect choices
                    $isCorrect = ! $hasIncorrectChoice && ($selectedCorrectCount === count($correctChoiceIds));
                    $answerData = json_encode($selectedChoiceIds);
                } else {
                    $choiceId = (int) $validated['answer'];
                    $correctChoice = $quizQuestion->choices->firstWhere('is_correct', true);
                    $isCorrect = $correctChoice && $correctChoice->id === $choiceId;
                    $answerData = (string) $validated['answer'];
                }
            } elseif ($quizQuestion->answer_type === 'vote') {
                $answerType = 'vote';
                $requiredSelections = max(1, (int) ($quizQuestion->vote_max_selections ?? 1));

                if ($quizQuestion->isOpenTreeVoting()) {
                    $selectedChoiceIds = [];
                    foreach ($openTreePersonIds as $pId) {
                        $candPerson = \App\Models\Person::find($pId);
                        $ch = \App\Models\QuizQuestionChoice::firstOrCreate(
                            [
                                'quiz_question_id' => $quizQuestion->id,
                                'person_id' => (int) $pId,
                            ],
                            [
                                'choice_text' => $candPerson ? $candPerson->name : 'مرشح #' . $pId,
                                'is_correct' => false,
                            ]
                        );
                        $selectedChoiceIds[] = $ch->id;
                    }
                    $answerData = count($selectedChoiceIds) > 1 ? json_encode($selectedChoiceIds) : (string) $selectedChoiceIds[0];
                } else {
                    $rawSubmitted = is_array($validated['answer']) ? array_filter($validated['answer']) : array_filter([$validated['answer']]);
                    $rawSubmitted = array_values(array_map('intval', $rawSubmitted));

                    if (count($rawSubmitted) < $requiredSelections) {
                        $remaining = $requiredSelections - count($rawSubmitted);
                        DB::rollBack();
                        return back()->withInput()->with('error', "يجب اختيار {$requiredSelections} مرشحين إجبارياً لإتمام التصويت. فاضل {$remaining} " . ($remaining === 1 ? 'مرشح' : 'مرشحين') . ".");
                    }
                    if (count($rawSubmitted) > $requiredSelections) {
                        DB::rollBack();
                        return back()->withInput()->with('error', "لا يمكنك اختيار أكثر من {$requiredSelections} مرشحين في هذا التصويت.");
                    }
                    if (count($rawSubmitted) !== count(array_unique($rawSubmitted))) {
                        DB::rollBack();
                        return back()->withInput()->with('error', 'لا يمكن تكرار نفس المرشح في أكثر من خانة.');
                    }

                    if ($requiredSelections > 1) {
                        $selectedChoiceIds = $rawSubmitted;
                        $answerData = json_encode($selectedChoiceIds);
                    } else {
                        $selectedChoiceIds = [(int) $validated['answer']];
                        $answerData = (string) $validated['answer'];
                    }
                }

                if (!empty($lockedChoiceIds)) {
                    $lockedCount = count($lockedChoiceIds);
                    if ($quizQuestion->isOpenTreeVoting() || $quizQuestion->isLinkedToWalking()) {
                        $submittedPrefix = array_values(array_map('intval', array_slice($selectedChoiceIds, 0, $lockedCount)));
                        if ($submittedPrefix !== $lockedChoiceIds) {
                            DB::rollBack();
                            return back()->withInput()->with('error', "لا يمكن تغيير أو حذف المرشحين السابقين المثبتين في الخانات الأولى (1 إلى {$lockedCount}). يرجى إكمال الخانات المتبقية فقط.");
                        }
                    } else {
                        $missingLocked = array_diff($lockedChoiceIds, array_map('intval', $selectedChoiceIds));
                        if (!empty($missingLocked)) {
                            DB::rollBack();
                            return back()->withInput()->with('error', "لا يمكن إلغاء الاختيارات السابقة المثبتة (عدد {$lockedCount}). يرجى اختيار المرشحين الإضافيين فقط.");
                        }
                    }
                }

                $isCorrect = false; // Vote has no correct answer
            } elseif ($quizQuestion->answer_type === 'ordering') {
                $answerType = 'ordering';
                $selectedChoiceIds = $validated['answer']; // Array of ids

                $isCorrect = true;

                if ($quizQuestion->groups_count && $quizQuestion->groups_count > 0) {
                    // Group-based ordering: correct ORDER within each group matters,
                    // but images can be in ANY group (groups are interchangeable)
                    $groups = $quizQuestion->choices->filter(fn ($c) => ! empty($c->group_name))->groupBy('group_name');

                    if (count($selectedChoiceIds) !== $quizQuestion->choices->count()) {
                        $isCorrect = false;
                    } else {
                        // Build the correct groups (each is an array of IDs in order)
                        $correctGroups = [];
                        $groupSizes = [];
                        foreach ($groups as $groupName => $groupChoices) {
                            $correctGroups[] = array_map('intval', $groupChoices->pluck('id')->toArray());
                            $groupSizes[] = $groupChoices->count();
                        }

                        // Chunk submitted IDs by group slot sizes
                        $submittedChunks = [];
                        $currentIndex = 0;
                        foreach ($groupSizes as $size) {
                            $submittedChunks[] = array_map('intval', array_slice($selectedChoiceIds, $currentIndex, $size));
                            $currentIndex += $size;
                        }

                        // Match each submitted chunk to any correct group (order must match exactly)
                        $matched = [];
                        foreach ($submittedChunks as $chunk) {
                            $found = false;
                            foreach ($correctGroups as $gIdx => $correctIds) {
                                if (isset($matched[$gIdx])) {
                                    continue;
                                }
                                if ($chunk === $correctIds) {
                                    $matched[$gIdx] = true;
                                    $found = true;
                                    break;
                                }
                            }
                            if (! $found) {
                                $isCorrect = false;
                                break;
                            }
                        }

                        if (count($matched) !== count($correctGroups)) {
                            $isCorrect = false;
                        }
                    }
                } else {
                    // Standard exact ordering
                    $correctChoiceIds = $quizQuestion->choices()->orderBy('id')->pluck('id')->toArray();

                    if (count($selectedChoiceIds) !== count($correctChoiceIds)) {
                        $isCorrect = false;
                    } else {
                        foreach ($correctChoiceIds as $index => $correctId) {
                            if ((int) $selectedChoiceIds[$index] !== (int) $correctId) {
                                $isCorrect = false;
                                break;
                            }
                        }
                    }
                }

                $answerData = json_encode($selectedChoiceIds);
            } elseif ($quizQuestion->answer_type === 'true_false') {
                $answerType = 'true_false';
                $selectedAnswers = $validated['answer']; // Array map: choice_id => true/false

                $isCorrect = true;
                $correctChoicesCount = $quizQuestion->choices->count();

                if (count($selectedAnswers) !== $correctChoicesCount || $correctChoicesCount === 0) {
                    $isCorrect = false;
                } else {
                    foreach ($quizQuestion->choices as $choice) {
                        if (! isset($selectedAnswers[$choice->id])) {
                            $isCorrect = false;
                            break;
                        }

                        $submittedVal = $selectedAnswers[$choice->id];
                        $submittedBool = ($submittedVal === '1' || $submittedVal === 'true');
                        $actualBool = (bool) $choice->is_correct;

                        if ($submittedBool !== $actualBool) {
                            $isCorrect = false;
                            break;
                        }
                    }
                }

                $answerData = json_encode($selectedAnswers);
            } elseif ($quizQuestion->answer_type === 'fill_blank') {
                $answerType = 'fill_blank';
                $choiceId = (int) $validated['answer'];
                $selectedChoice = $quizQuestion->choices->firstWhere('id', $choiceId);
                $isCorrect = $selectedChoice && (bool) $selectedChoice->is_correct;
                $answerData = (string) $choiceId;
            } elseif ($quizQuestion->answer_type === 'survey') {
                $quizQuestion->loadMissing('surveyItems');
                $answerType = 'survey';
                $isCorrect = true;
                $payload = [];
                foreach ($quizQuestion->surveyItems as $item) {
                    $raw = $validated['survey_item'][$item->id] ?? null;
                    if ($item->response_kind === 'rating') {
                        $payload[$item->id] = ['k' => 'rating', 'v' => (int) $raw];
                    } elseif ($item->response_kind === 'number') {
                        $payload[$item->id] = ['k' => 'number', 'v' => (int) $raw];
                    } else {
                        $payload[$item->id] = ['k' => 'text', 'v' => (string) $raw];
                    }
                }
                $answerData = json_encode($payload, JSON_UNESCAPED_UNICODE);
            } else {
                // Free-text / custom answers: treat any submitted answer as correct (as requested).
                $answerType = 'custom';
                $isCorrect = true;
                $answerData = (string) $validated['answer'];
            }

            // Create or update record
            if ($isVoteUpdate && $existingAnswer) {
                $existingAnswer->update([
                    'answer' => $answerData,
                    'answer_type' => $answerType,
                    'is_correct' => false,
                ]);

                if ($isCompletingPartialVote) {
                    $existingAnswer->touch();
                }

                $existingAnswer->selectedChoices()->delete();
                foreach ($selectedChoiceIds as $choiceId) {
                    $existingAnswer->selectedChoices()->create([
                        'quiz_question_choice_id' => $choiceId,
                        'is_locked' => in_array((int) $choiceId, $lockedChoiceIds, true),
                    ]);
                }

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

                // Store selected choices
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
                } elseif ($quizQuestion->answer_type === 'fill_blank') {
                    $quizAnswer->selectedChoices()->create([
                        'quiz_question_choice_id' => (int) $validated['answer'],
                    ]);
                }
            }

            if ($targetPersonId) {
                \Illuminate\Support\Facades\Cache::forget("user_answered_q_p_{$targetPersonId}");
                \Illuminate\Support\Facades\Cache::forget("user_answered_data_p_{$targetPersonId}");
            }
            if ($targetUserId) {
                \Illuminate\Support\Facades\Cache::forget("user_answered_q_u_{$targetUserId}");
                \Illuminate\Support\Facades\Cache::forget("user_answered_data_u_{$targetUserId}");
            }
            if (!empty($validated['phone'])) {
                \Illuminate\Support\Facades\Cache::forget("user_answered_q_ph_{$validated['phone']}");
                \Illuminate\Support\Facades\Cache::forget("user_answered_data_ph_{$validated['phone']}");
            }
            \Illuminate\Support\Facades\Cache::forget("vote_results_{$quizQuestion->id}");

            session(['quiz_answered_'.$quizQuestion->id => now()->toDateTimeString()]);

            DB::commit();

            // الاستبيان: لا يُنقل المستخدم لصفحة أخرى — يبقى حيث أرسل (رئيسية مثل التصويت، أو نفس صفحة السؤال)
            if ($quizQuestion->answer_type === 'survey') {
                if ($request->input('source') === 'home') {
                    return redirect()
                        ->to(route('home').'#activeQuizSection')
                        ->with('survey_submitted', true)
                        ->with('answered_question_id', $quizQuestion->id);
                }

                return redirect()
                    ->back()
                    ->with('survey_submitted', true)
                    ->with('answered_question_id', $quizQuestion->id);
            }

            if ($isVoteType) {
                if ($request->input('source') === 'home') {
                    // Redirect back to home page with fragment to scroll to quiz block
                    return redirect()
                        ->to(route('home').'#activeQuizSection')
                        ->with('vote_submitted', true)
                        ->with('answered_question_id', $quizQuestion->id);
                }

                return redirect()
                    ->route('quiz-competitions.question', [$quizCompetition, $quizQuestion])
                    ->with('vote_submitted', true);
            }

            return redirect()
                ->route('quiz-competitions.question', [$quizCompetition, $quizQuestion])
                ->with('answer_submitted', true)
                ->with('answer_correct', $isCorrect);
        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();
            // معالجة أخطاء قاعدة البيانات بشكل أفضل
            if ($e->getCode() == 23000) {
                // خطأ في constraint (مثل duplicate entry)
                if (str_contains($e->getMessage(), 'quiz_answers_quiz_question_id_user_id_unique')) {
                    return back()->withInput()->with('error', 'لقد أجبت على هذا السؤال مسبقاً.');
                }

                return back()->withInput()->with('error', 'حدث خطأ في حفظ البيانات. يرجى المحاولة مرة أخرى.');
            }

            return back()->withInput()->with('error', 'حدث خطأ غير متوقع. يرجى المحاولة مرة أخرى.');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withInput()->with('error', 'حدث خطأ: '.$e->getMessage());
        }
    }

    /**
     * JSON endpoint to get vote results (percentage per choice) for vote-type questions.
     * Returns aggregate counts from ALL respondents (no filter by current user).
     */
    public function getVoteResults(QuizCompetition $quizCompetition, QuizQuestion $quizQuestion): \Illuminate\Http\JsonResponse
    {
        if ($quizQuestion->answer_type !== 'vote') {
            return response()->json(['error' => 'Not a vote question'], 400);
        }

        if ($quizQuestion->quiz_competition_id != $quizCompetition->id) {
            abort(404);
        }

        $cacheKey = "vote_results_{$quizQuestion->id}";
        $data = Cache::remember($cacheKey, now()->addSeconds(3), function () use ($quizQuestion) {
            return $quizQuestion->getLiveVoteResultsData();
        });

        $results = array_map(function ($item) {
            $item['image'] = $item['avatar_url'] ?? $item['image_url'];
            return $item;
        }, $data['results'] ?? []);

        return response()->json([
            'success' => true,
            'total' => $data['total_votes'] ?? 0,
            'total_votes' => $data['total_votes'] ?? 0,
            'results' => $results,
            'data' => $data,
        ])->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * JSON endpoint to fetch candidate tree nodes for public voting
     */
    public function candidateTreeNodes(Request $request, QuizCompetition $quizCompetition, QuizQuestion $quizQuestion): \Illuminate\Http\JsonResponse
    {
        if ($quizQuestion->quiz_competition_id != $quizCompetition->id) {
            abort(404);
        }

        $rules = $quizQuestion->getCandidateRules();
        $search = trim((string) $request->query('q', ''));
        $parentId = $request->query('parent_id');
        $allowedGender = $rules['allowed_gender'] ?? 'all';
        if ($allowedGender === 'all' && $quizQuestion->isLinkedToWalking() && in_array($quizQuestion->getPredictionTargetGender(), ['male', 'female'], true)) {
            $allowedGender = $quizQuestion->getPredictionTargetGender();
        }
        $excludeDeceased = (bool) ($rules['exclude_deceased'] ?? true);
        $requireWhatsapp = (bool) ($rules['require_whatsapp'] ?? false);

        $walkingCondition = $rules['walking_condition'] ?? [];
        $walkingEnabled = !empty($walkingCondition['enabled']);
        $walkingDays = max(1, (int) ($walkingCondition['days_window'] ?? 3));
        $walkingMinSteps = max(0, (int) ($walkingCondition['min_daily_steps'] ?? 0));
        $walkingType = $walkingCondition['condition_type'] ?? 'any_day';

        $groupService = app(\App\Services\Invitations\InvitationGroupService::class);
        $whatsappIds = array_flip($groupService->personIdsWithWhatsApp());

        $eligibleWalkingMap = [];
        if ($walkingEnabled) {
            $eligibleIds = \App\Models\QuizQuestion::getEligibleWalkingPersonIds($walkingDays, $walkingMinSteps, $walkingType);
            $eligibleWalkingMap = array_flip($eligibleIds);
        }

        $query = \App\Models\Person::query()
            ->select(['id', 'first_name', 'last_name', 'gender', 'photo_url', 'death_date', 'parent_id', 'from_outside_the_family'])
            ->with(['parent.parent.parent.parent.parent'])
            ->where('from_outside_the_family', false);

        if ($search !== '') {
            $query->searchByFullName($search);

            if ($allowedGender && $allowedGender !== 'all') {
                $query->orderByRaw("CASE WHEN gender = ? THEN 0 ELSE 1 END ASC", [$allowedGender]);
            }
            if ($excludeDeceased) {
                $query->orderByRaw("CASE WHEN death_date IS NULL THEN 0 ELSE 1 END ASC");
            }

            $rawPeople = $query->withCount('children')->limit(50)->get();
            $people = app(\App\Services\PersonSearchService::class)->rankCollection($rawPeople, $search);
        } else {
            if ($parentId) {
                $query->where('parent_id', (int) $parentId);
            } else {
                $query->whereNull('parent_id');
            }
            $people = $query->withCount('children')->orderBy('display_order')->orderBy('id')->get();
        }

        $nodes = $people->map(function ($person) use ($whatsappIds, $allowedGender, $excludeDeceased, $requireWhatsapp, $walkingEnabled, $walkingDays, $eligibleWalkingMap) {
            $hasWhatsapp = isset($whatsappIds[$person->id]);
            $isDeceased = $person->death_date !== null;

            $genderMatch = (! $allowedGender || $allowedGender === 'all' || $person->gender === $allowedGender);
            $deceasedMatch = (! $excludeDeceased || ! $isDeceased);
            $whatsappMatch = (! $requireWhatsapp || $hasWhatsapp);
            $walkingMatch = (! $walkingEnabled || isset($eligibleWalkingMap[$person->id]));

            $selectable = $genderMatch && $deceasedMatch && $whatsappMatch && $walkingMatch;

            $unselectableReason = null;
            if (! $deceasedMatch) {
                $unselectableReason = 'متوفى (' . ($person->gender === 'female' ? 'رحمها الله' : 'رحمه الله') . ')';
            } elseif (! $genderMatch) {
                $unselectableReason = $allowedGender === 'male' ? 'غير متاح (رجال فقط)' : 'غير متاح (نساء فقط)';
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
                'profile_url' => route('people.profile.show', $person->id),
                'has_whatsapp' => $hasWhatsapp,
                'children_count' => $person->children_count ?? 0,
                'is_deceased' => $isDeceased,
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
            'success' => true,
            'nodes' => $nodes,
            'candidate_rules' => $rules,
        ]);
    }

    /**
     * JSON endpoint to get the winner — designed for high concurrency (1000+ users).
     * Uses Cache::remember so only ONE DB query ever runs; all other requests get cached result.
     */
    public function getWinner(QuizCompetition $quizCompetition, QuizQuestion $quizQuestion)
    {
        $selectionAt = $quizCompetition->end_at ? $quizCompetition->end_at->copy() : null;

        // Not time yet
        if (! $selectionAt || now()->lt($selectionAt)) {
            return response()->json(['status' => 'pending', 'message' => 'لم يحن وقت الاختيار بعد']);
        }

        // Try to get from cache first (10 min TTL)
        $cacheKey = 'quiz_winner_json_'.$quizQuestion->id;
        $result = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($quizQuestion) {
            // Atomic lock: only one process selects winners
            if ($quizQuestion->winners()->count() === 0) {
                $lockKey = 'quiz-winner-selection-'.$quizQuestion->id;
                Cache::lock($lockKey, 15)->block(10, function () use ($quizQuestion) {
                    $quizQuestion->refresh();
                    $quizQuestion->load(['winners']);
                    if ($quizQuestion->winners->count() === 0 && $quizQuestion->answers()->where('is_correct', true)->exists()) {
                        $quizQuestion->selectRandomWinners();
                    }
                });
            }

            $quizQuestion->refresh();
            $quizQuestion->load(['winners.user']);

            if ($quizQuestion->winners->count() > 0) {
                $winners = $quizQuestion->winners->map(function ($w) {
                    return [
                        'position' => $w->position,
                        'name' => $w->user->name ?? 'مجهول',
                    ];
                })->values()->all();

                return ['status' => 'done', 'winners' => $winners];
            }

            return ['status' => 'no_winners', 'message' => 'لا يوجد فائزين (لا توجد إجابات صحيحة)'];
        });

        return response()->json($result);
    }

    /**
     * JSON endpoint returning day-by-day Bar Chart Race timeline for a walking challenge.
     */
    public function walkingRaceTimeline(
        Request $request,
        QuizCompetition $quizCompetition,
        QuizQuestion $quizQuestion
    ): \Illuminate\Http\JsonResponse {
        if ((int) $quizQuestion->quiz_competition_id !== (int) $quizCompetition->id || ! $quizQuestion->isWalkingChallenge()) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'تحدي المشي غير موجود في هذه المسابقة.',
                'errors' => null,
            ], 404);
        }

        $identityResolver = app(\App\Services\SiteIdentityResolver::class);
        $identity = $identityResolver->resolve($request);
        $authMethod = $identity['auth_method'] ?? null;
        $viewerPersonId = $identityResolver->viewerPersonId($request);
        $viewerPerson = $viewerPersonId
            ? \App\Models\Person::find($viewerPersonId)
            : (auth()->check() ? auth()->user()?->person : null);

        if (! $quizQuestion->isVisibleTo($viewerPerson, $authMethod) || ! $quizQuestion->canViewWalkingLeaderboard($viewerPerson)) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'عذراً، هذا الترتيب غير متاح لك.',
                'errors' => null,
            ], 403);
        }

        if (! $quizQuestion->isWalkingRaceEnabled()) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'ميزة سباق الترتيب اليومي غير مفعلة حالياً لهذه المسابقة.',
                'errors' => null,
            ], 403);
        }

        $timeline = app(\App\Services\Walking\WalkingCompetitionScoringService::class)
            ->getDailyRaceTimeline($quizCompetition, $quizQuestion, $viewerPerson);

        return response()->json([
            'success' => true,
            'data' => $timeline,
            'message' => 'تم جلب بيانات السباق اليومي بنجاح.',
            'errors' => null,
        ])->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }
}

<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Person;
use App\Models\QuizCompetition;
use App\Models\QuizCompetitionWalkingAdjustment;
use App\Models\QuizQuestion;
use App\Models\QuizSurveyItem;
use App\Models\WalkingGroup;
use App\Services\Walking\WalkingCompetitionScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class QuizQuestionController extends Controller
{
    public function create(QuizCompetition $quizCompetition): View
    {
        $walkingGroups = WalkingGroup::activeOnly()->orderBy('name')->get();
        $walkingQuestions = QuizQuestion::where('answer_type', 'walking')
            ->with('competition')
            ->orderBy('id', 'desc')
            ->get();
        $voteQuestions = QuizQuestion::where('answer_type', 'vote')
            ->with('competition')
            ->orderBy('id', 'desc')
            ->get();

        return view('dashboard.quiz-questions.create', compact('quizCompetition', 'walkingGroups', 'walkingQuestions', 'voteQuestions'));
    }

    public function store(Request $request, QuizCompetition $quizCompetition): RedirectResponse
    {
        $isVoteOpenTree = $request->input('answer_type') === 'vote' && in_array($request->input('candidate_rules.nomination_type', $request->input('vote_mode')), ['open_tree']);
        $isPredictionType = $request->input('answer_type') === 'walking_prediction';
        $choicesRule = ($isVoteOpenTree || $isPredictionType) ? 'nullable|array' : 'required_if:answer_type,multiple_choice,ordering,true_false,vote,fill_blank|array';

        $validated = $request->validate([
            'question_text' => 'nullable|string',
            'description' => 'nullable|string',
            'answer_type' => 'required|in:multiple_choice,custom_text,ordering,true_false,vote,fill_blank,survey,walking,walking_prediction',
            'is_multiple_selections' => 'nullable|boolean',
            'winners_count' => 'required|integer|min:1',
            'groups_count' => 'nullable|integer|min:1',
            'display_order' => 'nullable|integer|min:0',
            'prize' => 'nullable|array',
            'prize.*' => 'nullable|string',
            'choices' => $choicesRule,
            'choices.*.id' => 'nullable|integer',
            'choices.*.person_id' => 'nullable|integer|exists:persons,id',
            'voter_eligibility' => 'nullable|array',
            'candidate_rules' => 'nullable|array',
            'candidate_rules.allowed_gender' => 'nullable|in:all,male,female',
            'candidate_rules.nomination_type' => 'nullable|in:open_tree,predefined_tree,custom_options,predefined',
            'candidate_rules.require_whatsapp' => 'nullable|boolean',
            'candidate_rules.exclude_deceased' => 'nullable|boolean',
            'candidate_rules.candidate_scope' => 'nullable|in:all,family_and_ansab,original_family_only,maternal_only,breastfed_only,custom',
            'candidate_rules.exclude_outside_family' => 'nullable|boolean',
            'candidate_rules.exclude_maternal_children' => 'nullable|boolean',
            'candidate_rules.exclude_breastfed' => 'nullable|boolean',
            'candidate_rules.exclude_original_family' => 'nullable|boolean',
            'choices.*.text' => 'nullable|string',
            'choices.*.group_name' => 'nullable|string|max:255',
            'choices.*.image' => 'nullable|image|max:5120',
            'choices.*.video' => 'nullable|mimes:mp4,mov,ogg,qt|max:20000',
            'choices.*.is_correct' => 'nullable|boolean',
            'correct_choices' => 'nullable|array',
            'vote_max_selections' => 'nullable|integer|min:1',
            'vote_edit_duration_hours' => 'nullable|integer|min:-1|max:8760',
            'linked_walking_question_id' => 'nullable|exists:quiz_questions,id',
            'prediction_rank_points' => 'nullable|array',
            'prediction_rank_points.*' => 'nullable|integer|min:0',
            'source_vote_question_id' => 'nullable|exists:quiz_questions,id',
            'require_prior_registration' => 'nullable|boolean',
            'survey_items' => 'required_if:answer_type,survey|array|min:1',
            'survey_items.*.id' => 'nullable|integer',
            'survey_items.*.block_type' => 'required_if:answer_type,survey|in:image,video,text',
            'survey_items.*.body_text' => 'nullable|string',
            'survey_items.*.response_kind' => 'required_if:answer_type,survey|in:rating,number,text',
            'survey_items.*.rating_max' => 'nullable|integer|min:2|max:100',
            'survey_items.*.number_min' => 'nullable|integer',
            'survey_items.*.number_max' => 'nullable|integer',
            'survey_items.*.youtube_url' => 'nullable|string|max:2000',
            'survey_items.*.media' => 'nullable|file|max:20000',
            'walking_settings' => 'nullable|array',
            'has_chat' => 'nullable|boolean',
        ], [
            'answer_type.required' => 'نوع الإجابة مطلوب',
            'winners_count.required' => 'عدد الفائزين مطلوب',
            'winners_count.min' => 'عدد الفائزين يجب أن يكون على الأقل 1',
            'groups_count.min' => 'عدد المجموعات يجب أن يكون على الأقل 1',
        ]);

        if ($validated['answer_type'] === 'survey') {
            $err = $this->validateSurveyPayload($request, null);
            if ($err) {
                return $err;
            }
        }

        if ($validated['answer_type'] === 'walking' && $request->input('walking_settings.competition_scope') === 'groups') {
            $groupIds = (array) $request->input('walking_settings.competing_group_ids', []);
            if (count(array_filter($groupIds)) < 2) {
                return redirect()->back()->withInput()->withErrors(['walking_settings.competing_group_ids' => 'يجب اختيار مجموعتين على الأقل للمنافسة بين المجموعات']);
            }
        }

        $walkingSettings = $this->buildWalkingSettingsFromRequest($request, $validated);

        $isPrediction = $validated['answer_type'] === 'walking_prediction' || ($validated['answer_type'] === 'vote' && !empty($request->input('linked_walking_question_id')));

        $question = $quizCompetition->questions()->create([
            'question_text' => $validated['question_text'] ?? null,
            'description' => $validated['description'] ?? null,
            'answer_type' => $validated['answer_type'],
            'is_multiple_selections' => ! empty($validated['is_multiple_selections']),
            'winners_count' => $validated['winners_count'],
            'groups_count' => ($validated['answer_type'] === 'ordering') ? $validated['groups_count'] ?? null : null,
            'display_order' => $validated['display_order'] ?? 0,
            'prize' => $validated['prize'] ?? [],
            'vote_max_selections' => ($validated['answer_type'] === 'vote') ? ($validated['vote_max_selections'] ?? 1) : null,
            'vote_edit_duration_hours' => ($validated['answer_type'] === 'vote')
                ? (isset($validated['vote_edit_duration_hours']) ? (int) $validated['vote_edit_duration_hours'] : 24)
                : null,
            'linked_walking_question_id' => $isPrediction ? ($request->input('linked_walking_question_id') ?: null) : null,
            'prediction_rank_points' => $isPrediction ? $this->buildPredictionRankPointsFromRequest($request) : null,
            'source_vote_question_id' => $isPrediction ? ($request->input('source_vote_question_id') ?: null) : null,
            'require_prior_registration' => ($validated['answer_type'] === 'vote')
                ? ($request->has('voter_eligibility.require_identity') ? (bool) $request->input('voter_eligibility.require_identity') : true)
                : ! empty($validated['require_prior_registration']),
            'walking_settings' => $walkingSettings,
            'has_chat' => (bool) $request->input('has_chat', false),
            'voter_eligibility' => ($validated['answer_type'] === 'vote') ? [
                'require_identity' => (bool) $request->input('voter_eligibility.require_identity', false),
                'require_whatsapp' => (bool) $request->input('voter_eligibility.require_whatsapp', false),
                'allowed_gender' => in_array($request->input('voter_eligibility.allowed_gender'), ['male', 'female', 'all']) ? $request->input('voter_eligibility.allowed_gender') : 'all',
                'min_age' => !empty($request->input('voter_eligibility.min_age')) ? (int) $request->input('voter_eligibility.min_age') : null,
                'require_ancestry' => (bool) $request->input('voter_eligibility.require_ancestry', false),
                'custom_message' => $request->input('voter_eligibility.custom_message') ?: null,
            ] : null,
            'candidate_rules' => ($validated['answer_type'] === 'vote') ? $this->buildCandidateRulesFromRequest($request) : null,
        ]);

        if ($validated['answer_type'] === 'walking') {
            app(WalkingCompetitionScoringService::class)->clearCompetitionCaches($question);
            return redirect()->route('dashboard.quiz-competitions.show', $quizCompetition)
                ->with('success', 'تم إضافة تحدي المشي والخطوات بنجاح');
        }

        if ($validated['answer_type'] === 'walking_prediction') {
            return redirect()->route('dashboard.quiz-competitions.show', $quizCompetition)
                ->with('success', 'تم إضافة مسابقة توقعات تحدي المشي بنجاح');
        }

        if ($validated['answer_type'] === 'survey') {
            $this->persistSurveyItemsFromRequest($question, $request);

            return redirect()->route('dashboard.quiz-competitions.show', $quizCompetition)
                ->with('success', 'تم إضافة السؤال بنجاح');
        }

        if (in_array($validated['answer_type'], ['multiple_choice', 'ordering', 'true_false', 'vote', 'fill_blank']) && ! $isVoteOpenTree && ! empty($validated['choices'])) {
            $choices = array_filter($validated['choices'], function ($c) {
                $hasText = trim((string) ($c['text'] ?? '')) !== '';
                $hasPerson = ! empty($c['person_id']);
                $hasMedia = isset($c['image']) || isset($c['video']);

                return $hasText || $hasPerson || $hasMedia;
            });

            $isMultiple = ! empty($validated['is_multiple_selections']);
            $isOrdering = $validated['answer_type'] === 'ordering';
            $isVote = $validated['answer_type'] === 'vote';
            $correctChoicesKeys = $request->input('correct_choices', []);

            $isTrueFalse = $validated['answer_type'] === 'true_false';

            foreach ($choices as $index => $choice) {
                $isCorrect = false;
                if ($isOrdering) {
                    $isCorrect = true;
                } elseif ($isVote) {
                    $isCorrect = false;
                } elseif ($isMultiple) {
                    $isCorrect = in_array((string) $index, $correctChoicesKeys);
                } elseif ($isTrueFalse) {
                    $isCorrect = ! empty($choice['is_correct']) && $choice['is_correct'] != 'false' && $choice['is_correct'] != '0';
                } else {
                    $isCorrect = ! empty($choice['is_correct']) && $choice['is_correct'] != 'false' && $choice['is_correct'] != '0';
                }

                $personId = ! empty($choice['person_id']) ? (int) $choice['person_id'] : null;
                $choiceText = trim((string) ($choice['text'] ?? ''));
                if ($choiceText === '' && $personId) {
                    $choiceText = \App\Models\Person::find($personId)?->name ?? '';
                }

                $question->choices()->create([
                    'person_id' => $personId,
                    'choice_text' => $choiceText,
                    'is_correct' => $isCorrect,
                    'group_name' => $isOrdering ? ($choice['group_name'] ?? null) : null,
                    'image' => isset($choice['image']) ? $choice['image']->store('quiz/choices/images', 'public') : null,
                    'video' => isset($choice['video']) ? $choice['video']->store('quiz/choices/videos', 'public') : null,
                ]);
            }
        }

        return redirect()->route('dashboard.quiz-competitions.show', $quizCompetition)
            ->with('success', 'تم إضافة السؤال بنجاح');
    }

    public function edit(QuizCompetition $quizCompetition, QuizQuestion $quizQuestion): View
    {
        $quizQuestion->load(['choices', 'surveyItems']);
        $walkingGroups = WalkingGroup::activeOnly()->orderBy('name')->get();
        $walkingQuestions = QuizQuestion::where('answer_type', 'walking')
            ->with('competition')
            ->orderBy('id', 'desc')
            ->get();
        $voteQuestions = QuizQuestion::where('answer_type', 'vote')
            ->with('competition')
            ->orderBy('id', 'desc')
            ->get();

        return view('dashboard.quiz-questions.edit', compact('quizCompetition', 'quizQuestion', 'walkingGroups', 'walkingQuestions', 'voteQuestions'));
    }

    public function update(Request $request, QuizCompetition $quizCompetition, QuizQuestion $quizQuestion): RedirectResponse
    {
        $isVoteOpenTree = $request->input('answer_type') === 'vote' && in_array($request->input('candidate_rules.nomination_type', $request->input('vote_mode')), ['open_tree']);
        $isPredictionType = $request->input('answer_type') === 'walking_prediction';
        $choicesRule = ($isVoteOpenTree || $isPredictionType) ? 'nullable|array' : 'required_if:answer_type,multiple_choice,ordering,true_false,vote,fill_blank|array';

        $validated = $request->validate([
            'question_text' => 'nullable|string',
            'description' => 'nullable|string',
            'answer_type' => 'required|in:multiple_choice,custom_text,ordering,true_false,vote,fill_blank,survey,walking,walking_prediction',
            'is_multiple_selections' => 'nullable|boolean',
            'winners_count' => 'required|integer|min:1',
            'groups_count' => 'nullable|integer|min:1',
            'display_order' => 'nullable|integer|min:0',
            'prize' => 'nullable|array',
            'prize.*' => 'nullable|string',
            'choices' => $choicesRule,
            'choices.*.id' => 'nullable|integer',
            'choices.*.person_id' => 'nullable|integer|exists:persons,id',
            'voter_eligibility' => 'nullable|array',
            'candidate_rules' => 'nullable|array',
            'candidate_rules.allowed_gender' => 'nullable|in:all,male,female',
            'candidate_rules.nomination_type' => 'nullable|in:open_tree,predefined_tree,custom_options,predefined',
            'candidate_rules.require_whatsapp' => 'nullable|boolean',
            'candidate_rules.exclude_deceased' => 'nullable|boolean',
            'candidate_rules.candidate_scope' => 'nullable|in:all,family_and_ansab,original_family_only,maternal_only,breastfed_only,custom',
            'candidate_rules.exclude_outside_family' => 'nullable|boolean',
            'candidate_rules.exclude_maternal_children' => 'nullable|boolean',
            'candidate_rules.exclude_breastfed' => 'nullable|boolean',
            'candidate_rules.exclude_original_family' => 'nullable|boolean',
            'choices.*.text' => 'nullable|string',
            'choices.*.group_name' => 'nullable|string|max:255',
            'choices.*.image' => 'nullable|image|max:5120',
            'choices.*.video' => 'nullable|mimes:mp4,mov,ogg,qt|max:20000',
            'choices.*.is_correct' => 'nullable|boolean',
            'correct_choices' => 'nullable|array',
            'vote_max_selections' => 'nullable|integer|min:1',
            'vote_edit_duration_hours' => 'nullable|integer|min:-1|max:8760',
            'linked_walking_question_id' => 'nullable|exists:quiz_questions,id',
            'prediction_rank_points' => 'nullable|array',
            'prediction_rank_points.*' => 'nullable|integer|min:0',
            'source_vote_question_id' => 'nullable|exists:quiz_questions,id',
            'require_prior_registration' => 'nullable|boolean',
            'survey_items' => 'required_if:answer_type,survey|array|min:1',
            'survey_items.*.id' => 'nullable|integer',
            'survey_items.*.block_type' => 'required_if:answer_type,survey|in:image,video,text',
            'survey_items.*.body_text' => 'nullable|string',
            'survey_items.*.response_kind' => 'required_if:answer_type,survey|in:rating,number,text',
            'survey_items.*.rating_max' => 'nullable|integer|min:2|max:100',
            'survey_items.*.number_min' => 'nullable|integer',
            'survey_items.*.number_max' => 'nullable|integer',
            'survey_items.*.youtube_url' => 'nullable|string|max:2000',
            'survey_items.*.media' => 'nullable|file|max:20000',
            'walking_settings' => 'nullable|array',
            'has_chat' => 'nullable|boolean',
        ], [
            'answer_type.required' => 'نوع الإجابة مطلوب',
            'winners_count.required' => 'عدد الفائزين مطلوب',
            'winners_count.min' => 'عدد الفائزين يجب أن يكون على الأقل 1',
            'groups_count.min' => 'عدد المجموعات يجب أن يكون على الأقل 1',
        ]);

        if ($validated['answer_type'] === 'survey') {
            $quizQuestion->load('surveyItems');
            $err = $this->validateSurveyPayload($request, $quizQuestion);
            if ($err) {
                return $err;
            }
        }

        if ($validated['answer_type'] === 'walking' && $request->input('walking_settings.competition_scope') === 'groups') {
            $groupIds = (array) $request->input('walking_settings.competing_group_ids', []);
            if (count(array_filter($groupIds)) < 2) {
                return redirect()->back()->withInput()->withErrors(['walking_settings.competing_group_ids' => 'يجب اختيار مجموعتين على الأقل للمنافسة بين المجموعات']);
            }
        }

        $walkingSettings = $this->buildWalkingSettingsFromRequest($request, $validated) ?? $quizQuestion->walking_settings;

        $isPrediction = $validated['answer_type'] === 'walking_prediction' || ($validated['answer_type'] === 'vote' && !empty($request->input('linked_walking_question_id')));
        $newVoteMax = ($validated['answer_type'] === 'vote') ? (int) ($validated['vote_max_selections'] ?? 1) : null;

        $quizQuestion->update([
            'question_text' => $validated['question_text'] ?? null,
            'description' => $validated['description'] ?? null,
            'answer_type' => $validated['answer_type'],
            'is_multiple_selections' => ! empty($validated['is_multiple_selections']),
            'winners_count' => $validated['winners_count'],
            'groups_count' => ($validated['answer_type'] === 'ordering') ? $validated['groups_count'] ?? null : null,
            'display_order' => $validated['display_order'] ?? 0,
            'prize' => $validated['prize'] ?? [],
            'vote_max_selections' => $newVoteMax,
            'vote_edit_duration_hours' => ($validated['answer_type'] === 'vote')
                ? (isset($validated['vote_edit_duration_hours']) ? (int) $validated['vote_edit_duration_hours'] : 24)
                : null,
            'linked_walking_question_id' => $isPrediction ? ($request->input('linked_walking_question_id') ?: null) : null,
            'prediction_rank_points' => $isPrediction ? $this->buildPredictionRankPointsFromRequest($request) : null,
            'source_vote_question_id' => $isPrediction ? ($request->input('source_vote_question_id') ?: null) : null,
            'require_prior_registration' => ($validated['answer_type'] === 'vote')
                ? ($request->has('voter_eligibility.require_identity') ? (bool) $request->input('voter_eligibility.require_identity') : true)
                : ! empty($validated['require_prior_registration']),
            'walking_settings' => $walkingSettings,
            'has_chat' => (bool) $request->input('has_chat', false),
            'voter_eligibility' => ($validated['answer_type'] === 'vote') ? [
                'require_identity' => (bool) $request->input('voter_eligibility.require_identity', false),
                'require_whatsapp' => (bool) $request->input('voter_eligibility.require_whatsapp', false),
                'allowed_gender' => in_array($request->input('voter_eligibility.allowed_gender'), ['male', 'female', 'all']) ? $request->input('voter_eligibility.allowed_gender') : 'all',
                'min_age' => !empty($request->input('voter_eligibility.min_age')) ? (int) $request->input('voter_eligibility.min_age') : null,
                'require_ancestry' => (bool) $request->input('voter_eligibility.require_ancestry', false),
                'custom_message' => $request->input('voter_eligibility.custom_message') ?: null,
            ] : null,
            'candidate_rules' => ($validated['answer_type'] === 'vote') ? $this->buildCandidateRulesFromRequest($request) : null,
        ]);

        // إذا تم رفع عدد المرشحين المطلوب (مثلاً من 5 إلى 10)، نقفل اختيارات المرحلة الأولى تلقائياً للمصوتين السابقين
        if ($validated['answer_type'] === 'vote' && $newVoteMax && $newVoteMax > 1) {
            $incompleteAnswers = $quizQuestion->answers()
                ->with(['user:id,phone'])
                ->withCount('selectedChoices')
                ->get()
                ->filter(fn ($ans) => $ans->selected_choices_count > 0 && $ans->selected_choices_count < $newVoteMax);

            if ($incompleteAnswers->isNotEmpty()) {
                \App\Models\QuizAnswerChoice::whereIn('quiz_answer_id', $incompleteAnswers->pluck('id')->all())
                    ->update(['is_locked' => true]);

                foreach ($incompleteAnswers as $ans) {
                    if ($ans->person_id) {
                        \Illuminate\Support\Facades\Cache::forget("user_answered_data_p_{$ans->person_id}");
                    }
                    if ($ans->user_id) {
                        \Illuminate\Support\Facades\Cache::forget("user_answered_data_u_{$ans->user_id}");
                    }
                    if ($ans->user?->phone) {
                        \Illuminate\Support\Facades\Cache::forget("user_answered_data_ph_{$ans->user->phone}");
                    }
                }
            }
        }

        if ($validated['answer_type'] === 'walking') {
            $this->purgeChoicesAndMedia($quizQuestion);
            app(WalkingCompetitionScoringService::class)->clearCompetitionCaches($quizQuestion);
            return redirect()->route('dashboard.quiz-competitions.show', $quizCompetition)
                ->with('success', 'تم تحديث تحدي المشي بنجاح');
        }

        if ($validated['answer_type'] === 'walking_prediction') {
            $this->purgeChoicesAndMedia($quizQuestion);
            return redirect()->route('dashboard.quiz-competitions.show', $quizCompetition)
                ->with('success', 'تم تحديث مسابقة توقعات تحدي المشي بنجاح');
        }

        if ($validated['answer_type'] === 'survey') {
            $this->syncSurveyItemsFromRequest($quizQuestion, $request);
            $this->purgeChoicesAndMedia($quizQuestion);

            return redirect()->route('dashboard.quiz-competitions.show', $quizCompetition)
                ->with('success', 'تم تحديث السؤال بنجاح');
        }

        $quizQuestion->load('surveyItems');
        foreach ($quizQuestion->surveyItems as $si) {
            if ($si->media_path) {
                Storage::disk('public')->delete($si->media_path);
            }
            $si->delete();
        }

        $nominationType = $quizQuestion->candidate_rules['nomination_type'] ?? 'predefined';
        $isOpenTreeVoteUpdate = $validated['answer_type'] === 'vote' && $nominationType === 'open_tree';

        if ($isOpenTreeVoteUpdate) {
            // في وضع الاختيار المفتوح من الشجرة، الخيارات يُنشئها المصوتون ديناميكياً ولا يجوز حذفها عند تعديل إعدادات السؤال.
            // نحذف فقط الصفوف الفارغة تماماً التي لا ترتبط بأي شخص أو وسائط أو إجابات.
            $quizQuestion->load('choices.person');
            foreach ($quizQuestion->choices as $existingChoice) {
                $isBlank = empty($existingChoice->person_id)
                    && trim((string) $existingChoice->choice_text) === ''
                    && empty($existingChoice->image)
                    && empty($existingChoice->video);
                if ($isBlank && $existingChoice->answerChoices()->count() === 0) {
                    $existingChoice->delete();
                }
            }
        } elseif (in_array($validated['answer_type'], ['multiple_choice', 'ordering', 'true_false', 'vote', 'fill_blank']) && ! empty($validated['choices'])) {
            $quizQuestion->load('choices.person');
            $oldChoices = $quizQuestion->choices->keyBy('id');
            $oldChoicesByPerson = $quizQuestion->choices->whereNotNull('person_id')->keyBy('person_id');
            $choices = array_filter($validated['choices'], function ($c) {
                $hasText = trim((string) ($c['text'] ?? '')) !== '';
                $hasPerson = ! empty($c['person_id']);
                $hasMedia = isset($c['image']) || isset($c['video']);

                return $hasText || $hasPerson || $hasMedia || ! empty($c['id']);
            });

            $isMultiple = ! empty($validated['is_multiple_selections']);
            $isOrdering = $validated['answer_type'] === 'ordering';
            $isVote = $validated['answer_type'] === 'vote';
            $correctChoicesKeys = $request->input('correct_choices', []);
            $isTrueFalse = $validated['answer_type'] === 'true_false';

            $keptChoiceIds = [];

            foreach ($choices as $index => $choice) {
                $isCorrect = false;
                if ($isOrdering) {
                    $isCorrect = true;
                } elseif ($isVote) {
                    $isCorrect = false;
                } elseif ($isMultiple) {
                    $isCorrect = in_array((string) $index, $correctChoicesKeys);
                } elseif ($isTrueFalse) {
                    $isCorrect = ! empty($choice['is_correct']) && $choice['is_correct'] != 'false' && $choice['is_correct'] != '0';
                } else {
                    $isCorrect = ! empty($choice['is_correct']) && $choice['is_correct'] != 'false' && $choice['is_correct'] != '0';
                }

                $choiceId = isset($choice['id']) ? (int) $choice['id'] : null;
                $submittedPersonId = ! empty($choice['person_id']) ? (int) $choice['person_id'] : null;
                $oldChoice = ($choiceId && $oldChoices->has($choiceId))
                    ? $oldChoices->get($choiceId)
                    : (($submittedPersonId && $oldChoicesByPerson->has($submittedPersonId)) ? $oldChoicesByPerson->get($submittedPersonId) : null);

                // لا تقم بإنشاء أو حفظ صف فارغ تماماً بدون نص وبدون شخص وبدون وسائط
                $incomingText = trim((string) ($choice['text'] ?? ''));
                if ($incomingText === '' && ! $submittedPersonId && ! isset($choice['image']) && ! isset($choice['video']) && ! $oldChoice?->image && ! $oldChoice?->video) {
                    continue;
                }

                // الحفاظ على ربط person_id الأصلي إذا لم يُرسل من الفورم وبقي الاسم مطابقاً
                $resolvedPersonId = $submittedPersonId;
                if (! $resolvedPersonId && $oldChoice && $oldChoice->person_id) {
                    $oldName = trim((string) ($oldChoice->person?->name ?? $oldChoice->choice_text));
                    if ($incomingText === '' || $incomingText === $oldName || $incomingText === trim((string) $oldChoice->choice_text)) {
                        $resolvedPersonId = (int) $oldChoice->person_id;
                    }
                }
                if ($incomingText === '' && $resolvedPersonId) {
                    $incomingText = \App\Models\Person::find($resolvedPersonId)?->name ?? '';
                }

                $imagePath = $oldChoice ? $oldChoice->image : null;
                $videoPath = $oldChoice ? $oldChoice->video : null;

                if (isset($choice['image'])) {
                    if ($imagePath) {
                        Storage::disk('public')->delete($imagePath);
                    }
                    $imagePath = $choice['image']->store('quiz/choices/images', 'public');
                }
                if (isset($choice['video'])) {
                    if ($videoPath) {
                        Storage::disk('public')->delete($videoPath);
                    }
                    $videoPath = $choice['video']->store('quiz/choices/videos', 'public');
                }

                $choiceData = [
                    'person_id' => $resolvedPersonId,
                    'choice_text' => $incomingText,
                    'is_correct' => $isCorrect,
                    'group_name' => $isOrdering ? ($choice['group_name'] ?? null) : null,
                    'image' => $imagePath,
                    'video' => $videoPath,
                ];

                if ($oldChoice) {
                    $oldChoice->update($choiceData);
                    $keptChoiceIds[] = $oldChoice->id;
                } else {
                    $newChoice = $quizQuestion->choices()->create($choiceData);
                    $keptChoiceIds[] = $newChoice->id;
                }
            }

            // Delete only choices removed by user
            $removedChoices = $oldChoices->whereNotIn('id', $keptChoiceIds);
            foreach ($removedChoices as $rem) {
                if ($rem->image) {
                    Storage::disk('public')->delete($rem->image);
                }
                if ($rem->video) {
                    Storage::disk('public')->delete($rem->video);
                }
                $rem->delete();
            }
        } else {
            $this->purgeChoicesAndMedia($quizQuestion);
        }

        \Illuminate\Support\Facades\Cache::forget("vote_results_{$quizQuestion->id}");

        return redirect()->route('dashboard.quiz-competitions.show', $quizCompetition)
            ->with('success', 'تم تحديث السؤال بنجاح');
    }

    private function purgeChoicesAndMedia(QuizQuestion $quizQuestion): void
    {
        foreach ($quizQuestion->choices as $choice) {
            if ($choice->image) {
                Storage::disk('public')->delete($choice->image);
            }
            if ($choice->video) {
                Storage::disk('public')->delete($choice->video);
            }
        }
        $quizQuestion->choices()->delete();
    }

    private function validateSurveyPayload(Request $request, ?QuizQuestion $existingQuestion): ?RedirectResponse
    {
        $rows = $request->input('survey_items', []);
        if (! is_array($rows) || count($rows) < 1) {
            return back()->withInput()->withErrors(['survey_items' => 'أضف عنصراً واحداً على الأقل للاستبيان.']);
        }
        ksort($rows, SORT_NATURAL);
        foreach ($rows as $key => $item) {
            $blockType = $item['block_type'] ?? '';
            $responseKind = $item['response_kind'] ?? '';
            if ($blockType === 'text' && trim((string) ($item['body_text'] ?? '')) === '') {
                return back()->withInput()->withErrors(["survey_items.$key.body_text" => 'أدخل النص للعنصر النصي.']);
            }
            $file = $request->file("survey_items.$key.media");
            if ($blockType === 'image') {
                $existingRow = $this->findSurveyRow($existingQuestion, (int) ($item['id'] ?? 0));
                $keep = $existingRow && $existingRow->block_type === 'image' && $existingRow->media_path && ! $file;
                if (! $file && ! $keep) {
                    return back()->withInput()->withErrors(["survey_items.$key.media" => 'ارفع صورة لهذا العنصر.']);
                }
                if ($file) {
                    $v = \Illuminate\Support\Facades\Validator::make(['f' => $file], ['f' => 'image|max:5120']);
                    if ($v->fails()) {
                        return back()->withInput()->withErrors(["survey_items.$key.media" => 'صورة غير صالحة أو أكبر من 5 ميجا.']);
                    }
                }
            }
            if ($blockType === 'video') {
                $existingRow = $this->findSurveyRow($existingQuestion, (int) ($item['id'] ?? 0));
                $youtubeUrl = $this->normalizeYouTubeUrl($item['youtube_url'] ?? null);
                $keep = $existingRow
                    && $existingRow->block_type === 'video'
                    && ! $file
                    && empty($youtubeUrl)
                    && (! empty($existingRow->media_path) || ! empty($existingRow->youtube_url));

                if (! $file && empty($youtubeUrl) && ! $keep) {
                    return back()->withInput()->withErrors([
                        "survey_items.$key.youtube_url" => 'ارفع فيديو لهذا العنصر أو الصق رابط يوتيوب.',
                    ]);
                }

                if (! empty($youtubeUrl)) {
                    $id = $this->extractYouTubeIdFromUrl($youtubeUrl);
                    if (! $id) {
                        return back()->withInput()->withErrors([
                            "survey_items.$key.youtube_url" => 'رابط يوتيوب غير صالح.',
                        ]);
                    }
                }

                if ($file) {
                    $v = \Illuminate\Support\Facades\Validator::make(['f' => $file], ['f' => 'mimes:mp4,mov,ogg,qt|max:20000']);
                    if ($v->fails()) {
                        return back()->withInput()->withErrors(["survey_items.$key.media" => 'الفيديو يجب أن يكون mp4/mov/ogg/qt وبحد أقصى ~20 ميجا.']);
                    }
                }
            }
            if ($responseKind === 'number') {
                $min = (int) ($item['number_min'] ?? 0);
                $max = (int) ($item['number_max'] ?? 0);
                if ($min > $max) {
                    return back()->withInput()->withErrors(["survey_items.$key.number_max" => 'الحد الأعلى يجب أن يكون أكبر أو يساوي الأدنى.']);
                }
            }
        }

        return null;
    }

    private function findSurveyRow(?QuizQuestion $q, int $id): ?QuizSurveyItem
    {
        if (! $q || $id <= 0) {
            return null;
        }

        return $q->surveyItems->firstWhere('id', $id);
    }

    private function persistSurveyItemsFromRequest(QuizQuestion $question, Request $request): void
    {
        $rows = $request->input('survey_items', []);
        ksort($rows, SORT_NATURAL);
        $sort = 0;
        foreach ($rows as $key => $item) {
            $sort++;
            $mediaPath = $this->storeSurveyMedia($request, $key, $item['block_type']);
            $youtubeUrl = $item['block_type'] === 'video' ? $this->normalizeYouTubeUrl($item['youtube_url'] ?? null) : null;
            if ($item['block_type'] === 'video' && $mediaPath) {
                // If user uploaded a file, prefer it over YouTube.
                $youtubeUrl = null;
            }
            $question->surveyItems()->create($this->surveyItemAttributes($item, $sort, $mediaPath, $youtubeUrl));
        }
    }

    private function syncSurveyItemsFromRequest(QuizQuestion $question, Request $request): void
    {
        $rows = $request->input('survey_items', []);
        ksort($rows, SORT_NATURAL);
        $incomingIds = [];
        foreach ($rows as $item) {
            if (! empty($item['id'])) {
                $incomingIds[] = (int) $item['id'];
            }
        }
        $disk = Storage::disk('public');
        foreach ($question->surveyItems as $old) {
            if (! in_array($old->id, $incomingIds, true)) {
                if ($old->media_path) {
                    $disk->delete($old->media_path);
                }
                $old->delete();
            }
        }
        $sort = 0;
        foreach ($rows as $key => $item) {
            $sort++;
            $incomingYoutubeUrl = $item['block_type'] === 'video'
                ? $this->normalizeYouTubeUrl($item['youtube_url'] ?? null)
                : null;
            $attrs = $this->surveyItemAttributes($item, $sort, null, $incomingYoutubeUrl);
            if (! empty($item['id'])) {
                $row = QuizSurveyItem::where('quiz_question_id', $question->id)->find($item['id']);
                if (! $row) {
                    continue;
                }
                if ($item['block_type'] === 'text') {
                    if ($row->media_path) {
                        $disk->delete($row->media_path);
                    }
                    $attrs['media_path'] = null;
                    $attrs['youtube_url'] = null;
                } else {
                    $file = $request->file("survey_items.$key.media");

                    if ($item['block_type'] === 'image') {
                        $attrs['youtube_url'] = null;
                        if ($file) {
                            if ($row->media_path) {
                                $disk->delete($row->media_path);
                            }
                            $attrs['media_path'] = $file->store('quiz/survey/images', 'public');
                        } else {
                            if ($row->block_type !== $item['block_type'] && $row->media_path) {
                                $disk->delete($row->media_path);
                                $attrs['media_path'] = null;
                            } else {
                                $attrs['media_path'] = $row->media_path;
                            }
                        }
                    } elseif ($item['block_type'] === 'video') {
                        // Prefer uploaded file if present.
                        if ($file) {
                            if ($row->media_path) {
                                $disk->delete($row->media_path);
                            }
                            $attrs['media_path'] = $file->store('quiz/survey/videos', 'public');
                            $attrs['youtube_url'] = null;
                        } elseif (! empty($attrs['youtube_url'])) {
                            // If YouTube URL provided, clear media_path (and delete old file).
                            if ($row->media_path) {
                                $disk->delete($row->media_path);
                            }
                            $attrs['media_path'] = null;
                        } else {
                            // No new input => keep existing content.
                            $attrs['media_path'] = $row->media_path;
                            $attrs['youtube_url'] = $row->youtube_url;
                        }
                    }
                }
                $row->update($attrs);
            } else {
                $attrs['media_path'] = $this->storeSurveyMedia($request, $key, $item['block_type']);
                if ($item['block_type'] === 'video' && ! empty($attrs['media_path'])) {
                    // Prefer uploaded file over YouTube when both exist.
                    $attrs['youtube_url'] = null;
                }
                $question->surveyItems()->create($attrs);
            }
        }
    }

    private function surveyItemAttributes(array $item, int $sort, ?string $mediaPath, ?string $youtubeUrl): array
    {
        $responseKind = $item['response_kind'];
        $ratingMax = max(2, min(100, (int) ($item['rating_max'] ?? 10)));

        return [
            'sort_order' => $sort,
            'block_type' => $item['block_type'],
            'body_text' => $item['body_text'] ?? null,
            'media_path' => $mediaPath,
            'youtube_url' => $youtubeUrl,
            'response_kind' => $responseKind,
            'rating_max' => $responseKind === 'rating' ? $ratingMax : 10,
            'number_min' => $responseKind === 'number' ? (int) ($item['number_min'] ?? 0) : null,
            'number_max' => $responseKind === 'number' ? (int) ($item['number_max'] ?? 100) : null,
        ];
    }

    private function storeSurveyMedia(Request $request, string|int $key, string $blockType): ?string
    {
        if ($blockType === 'text') {
            return null;
        }
        $file = $request->file("survey_items.$key.media");
        if (! $file) {
            return null;
        }

        return $blockType === 'image'
            ? $file->store('quiz/survey/images', 'public')
            : $file->store('quiz/survey/videos', 'public');
    }

    private function normalizeYouTubeUrl(?string $url): ?string
    {
        $url = trim((string) ($url ?? ''));
        if ($url === '') {
            return null;
        }

        // Allow pasting raw 11-char YouTube ID.
        if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $url)) {
            return 'https://www.youtube.com/watch?v='.$url;
        }

        return $url;
    }

    private function extractYouTubeIdFromUrl(string $url): ?string
    {
        $pattern = '/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/|youtube\.com\/shorts\/)([a-zA-Z0-9_-]{11})/';
        if (preg_match($pattern, $url, $m)) {
            return $m[1];
        }

        return null;
    }

    public function destroy(QuizCompetition $quizCompetition, QuizQuestion $quizQuestion): RedirectResponse
    {
        $quizQuestion->delete();

        return redirect()->route('dashboard.quiz-competitions.show', $quizCompetition)
            ->with('success', 'تم حذف السؤال بنجاح');
    }

    public function selectWinners(QuizCompetition $quizCompetition, QuizQuestion $quizQuestion): RedirectResponse
    {
        if ($quizQuestion->isWalkingChallenge()) {
            $count = app(\App\Services\Walking\WalkingCompetitionScoringService::class)->finalizeWinners($quizQuestion);
            return back()->with('success', "تم احتساب وتتويج {$count} فائز(ين) في تحدي المشي بنجاح بناءً على أعلى النقاط والخطوات.");
        }

        if ($quizQuestion->isLinkedToWalking()) {
            $count = app(\App\Services\Walking\WalkingPredictionScoringService::class)->finalizePredictionWinners($quizQuestion);
            return back()->with('success', "تم مطابقة ترشيحات المتسابقين وتتويج {$count} فائز(ين) في مسابقة توقعات المشي بنجاح.");
        }

        if (! $quizQuestion->hasEnded()) {
            return back()->with('error', 'لا يمكن اختيار الفائزين قبل انتهاء فترة الإجابة');
        }

        $count = $quizQuestion->selectRandomWinners();

        return back()->with('success', "تم اختيار {$count} فائز عشوائياً");
    }

    public function removeWinner(\App\Models\QuizWinner $winner): RedirectResponse
    {
        $questionId = $winner->quiz_question_id;
        $winner->delete();

        \Illuminate\Support\Facades\Cache::forget('quiz_winner_json_'.$questionId);

        return back()->with('success', 'تم إزالة الفائز بنجاح');
    }

    public function fillWinners(QuizCompetition $quizCompetition, QuizQuestion $quizQuestion): RedirectResponse
    {
        if (! $quizQuestion->hasEnded()) {
            return back()->with('error', 'لا يمكن اختيار الفائزين قبل انتهاء فترة الإجابة');
        }

        $count = $quizQuestion->fillVacantWinners();

        if ($count > 0) {
            return back()->with('success', "تم اختيار {$count} فائز إضافي عشوائياً");
        }

        return back()->with('info', 'لا يوجد خانات فائزين شاغرة حالياً');
    }

    public function reorderWinners(Request $request, QuizCompetition $quizCompetition, QuizQuestion $quizQuestion): RedirectResponse
    {
        $winnerIds = $request->input('winner_ids', []);

        foreach ($winnerIds as $index => $id) {
            \App\Models\QuizWinner::where('id', $id)
                ->where('quiz_question_id', $quizQuestion->id)
                ->update(['position' => $index + 1]);
        }

        \Illuminate\Support\Facades\Cache::forget('quiz_winner_json_'.$quizQuestion->id);

        return back()->with('success', 'تم إعادة ترتيب الفائزين بنجاح');
    }
    /**
     * جلب عُقد شجرة العائلة بنمط صلة للأدمن لاختيار المرشحين
     */
    public function treePickerNodes(Request $request): \Illuminate\Http\JsonResponse
    {
        $search = trim((string) $request->query('q', ''));
        $parentId = $request->query('parent_id');
        $hasWhatsappOnly = $request->boolean('has_whatsapp_only', false);

        $groupService = app(\App\Services\Invitations\InvitationGroupService::class);
        $whatsappIds = array_flip($groupService->personIdsWithWhatsApp());

        if ($search !== '') {
            $people = \App\Models\Person::query()
                ->select(['id', 'first_name', 'last_name', 'gender', 'photo_url', 'death_date', 'parent_id', 'from_outside_the_family'])
                ->where('from_outside_the_family', false)
                ->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%");
                })
                ->limit(30)
                ->get();
        } else {
            $query = \App\Models\Person::query()
                ->select(['id', 'first_name', 'last_name', 'gender', 'photo_url', 'death_date', 'parent_id', 'from_outside_the_family'])
                ->where('from_outside_the_family', false);

            if ($parentId) {
                $query->where('parent_id', (int) $parentId);
            } else {
                $query->whereNull('parent_id');
            }

            $gender = $request->query('gender');
            if ($gender && in_array($gender, ['male', 'female'], true)) {
                $query->where('gender', $gender);
            }
            if ($request->boolean('exclude_deceased', false)) {
                $query->whereNull('death_date');
            }
            $people = $query->withCount('children')->orderBy('display_order')->orderBy('id')->get();
        }

        $walkingRequired = $request->boolean('walking_required', false);
        $walkingDays = max(1, (int) $request->query('walking_days', 3));
        $walkingMinSteps = max(0, (int) $request->query('walking_min_steps', 0));
        $walkingType = $request->query('walking_type', 'any_day');

        $eligibleWalkingIds = [];
        if ($walkingRequired) {
            $eligibleWalkingIds = array_flip(QuizQuestion::getEligibleWalkingPersonIds($walkingDays, $walkingMinSteps, $walkingType));
        }

        $data = $people->map(function ($person) use ($whatsappIds, $hasWhatsappOnly, $walkingRequired, $eligibleWalkingIds) {
            $hasWhatsapp = isset($whatsappIds[$person->id]);
            if ($hasWhatsappOnly && !$hasWhatsapp) {
                return null;
            }

            $hasWalked = ! $walkingRequired || isset($eligibleWalkingIds[$person->id]);

            return [
                'id' => $person->id,
                'name' => $person->name,
                'first_name' => $person->first_name,
                'gender' => $person->gender,
                'photo_url' => $person->avatar,
                'has_whatsapp' => $hasWhatsapp,
                'children_count' => $person->children_count ?? 0,
                'is_deceased' => $person->death_date !== null,
                'has_walked' => $hasWalked,
            ];
        })->filter()->values();

        return response()->json([
            'success' => true,
            'nodes' => $data,
        ]);
    }

    /**
     * بناء مصفوفة ضوابط وشروط المرشحين من الـ Request
     */
    private function buildCandidateRulesFromRequest(Request $request): array
    {
        $nominationType = in_array($request->input('candidate_rules.nomination_type', $request->input('vote_mode')), ['open_tree', 'predefined_tree', 'custom_options', 'predefined'])
            ? ($request->input('candidate_rules.nomination_type', $request->input('vote_mode')) === 'predefined' ? 'predefined_tree' : $request->input('candidate_rules.nomination_type', $request->input('vote_mode')))
            : 'open_tree';

        $allowedGender = in_array($request->input('candidate_rules.allowed_gender'), ['male', 'female', 'all'])
            ? $request->input('candidate_rules.allowed_gender')
            : 'all';

        $requireWhatsapp = (bool) $request->input('candidate_rules.require_whatsapp', false);
        $excludeDeceased = (bool) $request->input('candidate_rules.exclude_deceased', true);

        $walkingInput = $request->input('candidate_rules.walking_condition', []);
        $walkingEnabled = ! empty($walkingInput['enabled']);
        $daysWindow = max(1, min(365, (int) ($walkingInput['days_window'] ?? 3)));
        $minDailySteps = max(0, (int) ($walkingInput['min_daily_steps'] ?? 0));
        $conditionType = in_array($walkingInput['condition_type'] ?? '', ['any_day', 'all_days'], true)
            ? $walkingInput['condition_type']
            : 'any_day';

        $candidateScope = in_array($request->input('candidate_rules.candidate_scope'), ['all', 'family_and_ansab', 'original_family_only', 'maternal_only', 'breastfed_only', 'custom'], true)
            ? $request->input('candidate_rules.candidate_scope')
            : 'all';
        $excludeOutsideFamily = (bool) $request->input('candidate_rules.exclude_outside_family', false);
        $excludeMaternalChildren = (bool) $request->input('candidate_rules.exclude_maternal_children', false);
        $excludeBreastfed = (bool) $request->input('candidate_rules.exclude_breastfed', false);
        $excludeOriginalFamily = (bool) $request->input('candidate_rules.exclude_original_family', false);

        return [
            'nomination_type' => $nominationType,
            'allowed_gender' => $allowedGender,
            'require_whatsapp' => $requireWhatsapp,
            'exclude_deceased' => $excludeDeceased,
            'candidate_scope' => $candidateScope,
            'exclude_outside_family' => $excludeOutsideFamily,
            'exclude_maternal_children' => $excludeMaternalChildren,
            'exclude_breastfed' => $excludeBreastfed,
            'exclude_original_family' => $excludeOriginalFamily,
            'walking_condition' => [
                'enabled' => $walkingEnabled,
                'days_window' => $daysWindow,
                'min_daily_steps' => $minDailySteps,
                'condition_type' => $conditionType,
            ],
        ];
    }

    /**
     * Build normalized walking_settings array supporting both individual and group vs group competitions.
     */
    protected function buildWalkingSettingsFromRequest(Request $request, array $validated): ?array
    {
        if ($validated['answer_type'] !== 'walking') {
            return null;
        }

        $walkingInput = $request->input('walking_settings', []);
        $scope = in_array($walkingInput['competition_scope'] ?? '', ['individuals', 'groups'], true)
            ? $walkingInput['competition_scope']
            : 'individuals';

        $targetGender = in_array($walkingInput['target_gender'] ?? '', ['male', 'female', 'both_segregated', 'all'], true)
            ? $walkingInput['target_gender']
            : 'both_segregated';

        $excludeHidden = ! empty($walkingInput['exclude_hidden']);
        $excludeAnonymous = ! empty($walkingInput['exclude_anonymous']);
        $raceSettings = $this->extractWalkingRaceSettings($walkingInput);

        if ($scope === 'groups') {
            $competingGroupIds = array_values(array_filter(array_map('intval', (array) ($walkingInput['competing_group_ids'] ?? []))));
            $groupScoringMode = in_array(
                $walkingInput['group_scoring_mode'] ?? '',
                ['total_steps', 'average_steps', 'top_performer', 'daily_group_points', 'member_matchups'],
                true
            ) ? $walkingInput['group_scoring_mode'] : 'total_steps';

            $groupDailyPointsInput = $walkingInput['group_daily_points'] ?? [];
            $groupsCount = max(2, count($competingGroupIds));
            $groupDailyPoints = [];
            for ($g = 1; $g <= $groupsCount; $g++) {
                $groupDailyPoints[$g] = isset($groupDailyPointsInput[$g]) && is_numeric($groupDailyPointsInput[$g])
                    ? (int) $groupDailyPointsInput[$g]
                    : max(1, ($groupsCount - $g + 1) * 5);
            }

            return array_merge([
                'competition_scope'    => 'groups',
                'competing_group_ids'  => $competingGroupIds,
                'group_scoring_mode'   => $groupScoringMode,
                'min_member_steps'     => max(0, (int) ($walkingInput['min_member_steps'] ?? 0)),
                'group_daily_points'   => $groupDailyPoints,
                'target_gender'        => $targetGender,
                'exclude_hidden'       => $excludeHidden,
                'exclude_anonymous'    => $excludeAnonymous,
                'scoring_method'       => 'daily_rank_points',
                'daily_points'         => [],
                'min_daily_steps'      => 0,
            ], $raceSettings);
        }

        // Individuals scope (Existing mode)
        $dailyPointsInput = $walkingInput['daily_points'] ?? [];
        $winnersCount = max(1, (int) ($validated['winners_count'] ?? 1));
        $dailyPoints = [];
        for ($w = 1; $w <= $winnersCount; $w++) {
            $dailyPoints[$w] = isset($dailyPointsInput[$w]) && is_numeric($dailyPointsInput[$w])
                ? (int) $dailyPointsInput[$w]
                : max(1, $winnersCount - $w + 1);
        }

        return array_merge([
            'competition_scope' => 'individuals',
            'target_gender'     => $targetGender,
            'scoring_method'    => in_array($walkingInput['scoring_method'] ?? '', ['daily_rank_points', 'cumulative_steps'], true)
                ? $walkingInput['scoring_method']
                : 'daily_rank_points',
            'exclude_hidden'    => $excludeHidden,
            'exclude_anonymous' => $excludeAnonymous,
            'min_daily_steps'   => ! empty($walkingInput['min_daily_steps']) ? (int) $walkingInput['min_daily_steps'] : 0,
            'daily_points'      => $dailyPoints,
        ], $raceSettings);
    }

    /**
     * Normalize daily Bar Chart Race settings nested inside walking_settings.
     *
     * @param  array<string, mixed>  $walkingInput
     * @return array<string, mixed>
     */
    protected function extractWalkingRaceSettings(array $walkingInput): array
    {
        $calcMode = in_array($walkingInput['race_calc_mode'] ?? '', ['cumulative', 'daily'], true)
            ? $walkingInput['race_calc_mode']
            : 'cumulative';

        $speedMs = isset($walkingInput['race_speed_ms']) && is_numeric($walkingInput['race_speed_ms'])
            ? max(800, min(10000, (int) $walkingInput['race_speed_ms']))
            : 2800;

        $rawMaxRanks = $walkingInput['race_max_ranks'] ?? 10;
        $maxRanks = is_numeric($rawMaxRanks)
            ? ((int) $rawMaxRanks <= 0 ? 0 : max(1, min(500, (int) $rawMaxRanks)))
            : 10;

        return [
            'race_enabled'     => ! empty($walkingInput['race_enabled']) && $walkingInput['race_enabled'] !== '0',
            'race_calc_mode'   => $calcMode,
            'race_speed_ms'    => $speedMs,
            'race_max_ranks'   => $maxRanks,
            'race_title'       => trim((string) ($walkingInput['race_title'] ?? '')) ?: null,
            'race_subtitle'    => trim((string) ($walkingInput['race_subtitle'] ?? '')) ?: null,
            'race_button_text' => trim((string) ($walkingInput['race_button_text'] ?? '')) ?: null,
        ];
    }

    /**
     * بناء وتنظيف خريطة نقاط المراكز لمسابقة التوقعات من الطلب
     *
     * @return array<int, int>|null
     */
    protected function buildPredictionRankPointsFromRequest(Request $request): ?array
    {
        $raw = $request->input('prediction_rank_points');
        if (!is_array($raw)) {
            return null;
        }

        $maxSlots = 10;
        $sourceVoteId = $request->input('source_vote_question_id');
        if (!empty($sourceVoteId)) {
            $srcQ = QuizQuestion::find((int) $sourceVoteId);
            if ($srcQ) {
                $maxSlots = max(1, (int) ($srcQ->vote_max_selections ?: ($srcQ->choices()->count() ?: 5)));
            }
        } elseif ($request->filled('vote_max_selections')) {
            $maxSlots = max(1, (int) $request->input('vote_max_selections'));
        }

        $cleaned = [];
        foreach ($raw as $rank => $pts) {
            $rInt = (int) $rank;
            if ($rInt >= 1 && $rInt <= $maxSlots && $pts !== null && $pts !== '') {
                $cleaned[$rInt] = max(0, (int) $pts);
            }
        }
        ksort($cleaned);

        return !empty($cleaned) ? $cleaned : null;
    }

    /**
     * إضافة أو تعديل نقاط متسابق يدوياً في مسابقة المشي (مكافأة / خصم / تصحيح).
     */
    public function adjustWalkingPoints(QuizCompetition $quizCompetition, QuizQuestion $quizQuestion, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'person_id' => 'required|integer|exists:persons,id',
            'points' => 'required|integer|not_in:0',
            'type' => 'required|string|in:bonus,penalty,correction',
            'reason' => 'required|string|max:255',
            'date' => 'nullable|date',
        ]);

        $rawPoints = (int) $validated['points'];
        $type = $validated['type'];

        // Normalize points sign based on type
        $points = match ($type) {
            'bonus' => abs($rawPoints),
            'penalty' => -abs($rawPoints),
            'correction' => $rawPoints,
        };

        $adjustment = QuizCompetitionWalkingAdjustment::create([
            'quiz_question_id' => $quizQuestion->id,
            'person_id' => (int) $validated['person_id'],
            'points' => $points,
            'type' => $type,
            'reason' => $validated['reason'],
            'date' => $validated['date'] ?? null,
            'created_by' => auth()->id(),
        ]);

        // Invalidate caches
        app(WalkingCompetitionScoringService::class)->clearCompetitionCaches($quizQuestion);

        // Fetch updated standings for participant
        $standings = app(WalkingCompetitionScoringService::class)->getOverallStandings($quizQuestion);
        $participant = $standings['standings']->firstWhere('person_id', (int) $validated['person_id']);

        return response()->json([
            'success' => true,
            'message' => 'تم تعديل نقاط المتسابق بنجاح',
            'data' => [
                'adjustment' => $adjustment,
                'participant' => $participant,
                'total_points' => $participant?->total_points ?? 0,
                'manual_points' => $participant?->manual_points ?? 0,
            ],
            'errors' => null,
        ]);
    }

    /**
     * جلب سجل التعديلات السابقة لمتسابق في مسابقة المشي.
     */
    public function getWalkingAdjustments(QuizCompetition $quizCompetition, QuizQuestion $quizQuestion, Person $person): JsonResponse
    {
        $adjustments = QuizCompetitionWalkingAdjustment::where('quiz_question_id', $quizQuestion->id)
            ->where('person_id', $person->id)
            ->with('creator:id,name')
            ->orderByDesc('id')
            ->get()
            ->map(static function (QuizCompetitionWalkingAdjustment $adj) {
                return [
                    'id' => $adj->id,
                    'points' => $adj->points,
                    'type' => $adj->type,
                    'reason' => $adj->reason,
                    'date' => $adj->date?->format('Y-m-d'),
                    'creator_name' => $adj->creator?->name ?? 'النظام',
                    'created_at' => $adj->created_at?->format('Y-m-d H:i'),
                ];
            });

        $totalManualPoints = (int) $adjustments->sum('points');

        return response()->json([
            'success' => true,
            'data' => [
                'person' => [
                    'id' => $person->id,
                    'name' => $person->name,
                ],
                'adjustments' => $adjustments,
                'total_manual_points' => $totalManualPoints,
            ],
            'message' => 'تم جلب سجل التعديلات بنجاح',
            'errors' => null,
        ]);
    }

    /**
     * حذف تعديل يدوي واستعادة النقاط المحسوبة الأصلية.
     */
    public function destroyWalkingAdjustment(QuizCompetition $quizCompetition, QuizQuestion $quizQuestion, QuizCompetitionWalkingAdjustment $adjustment): JsonResponse
    {
        abort_if((int) $adjustment->quiz_question_id !== (int) $quizQuestion->id, 404, 'سجل التعديل لا يتبع هذا السؤال');

        $personId = (int) $adjustment->person_id;
        $adjustment->delete();

        // Invalidate caches
        app(WalkingCompetitionScoringService::class)->clearCompetitionCaches($quizQuestion);

        // Fetch updated standings for participant
        $standings = app(WalkingCompetitionScoringService::class)->getOverallStandings($quizQuestion);
        $participant = $standings['standings']->firstWhere('person_id', $personId);

        return response()->json([
            'success' => true,
            'message' => 'تم حذف التعديل واستعادة النقاط بنجاح',
            'data' => [
                'participant' => $participant,
                'total_points' => $participant?->total_points ?? 0,
                'manual_points' => $participant?->manual_points ?? 0,
            ],
            'errors' => null,
        ]);
    }

    /**
     * تحديث أو تفعيل/إخفاء إعدادات سباق الترتيب اليومي المتحرك مباشرة من صفحة تفاصيل المسابقة.
     */
    public function updateWalkingRaceSettings(
        Request $request,
        QuizCompetition $quizCompetition,
        QuizQuestion $quizQuestion
    ): RedirectResponse|JsonResponse {
        abort_if(
            (int) $quizQuestion->quiz_competition_id !== (int) $quizCompetition->id || ! $quizQuestion->isWalkingChallenge(),
            404,
            'تحدي المشي غير موجود في هذه المسابقة'
        );

        $existingSettings = $quizQuestion->getWalkingSettings();

        if ($request->boolean('quick_toggle')) {
            $newEnabled = $request->has('race_enabled')
                ? $request->boolean('race_enabled')
                : ! $quizQuestion->isWalkingRaceEnabled();

            $merged = array_merge($existingSettings, [
                'race_enabled' => $newEnabled,
            ]);
        } else {
            $validated = $request->validate([
                'race_enabled' => 'nullable|boolean',
                'race_calc_mode' => 'required|in:cumulative,daily',
                'race_speed_ms' => 'required|integer|min:800|max:10000',
                'race_max_ranks' => 'required|integer|min:0|max:500',
                'race_title' => 'nullable|string|max:255',
                'race_subtitle' => 'nullable|string|max:255',
                'race_button_text' => 'nullable|string|max:120',
            ]);

            $merged = array_merge($existingSettings, $this->extractWalkingRaceSettings([
                'race_enabled' => $request->boolean('race_enabled'),
                'race_calc_mode' => $validated['race_calc_mode'],
                'race_speed_ms' => $validated['race_speed_ms'],
                'race_max_ranks' => $validated['race_max_ranks'],
                'race_title' => $validated['race_title'] ?? null,
                'race_subtitle' => $validated['race_subtitle'] ?? null,
                'race_button_text' => $validated['race_button_text'] ?? null,
            ]));
        }

        $quizQuestion->update([
            'walking_settings' => $merged,
        ]);

        app(WalkingCompetitionScoringService::class)->clearCompetitionCaches($quizQuestion);

        $isEnabled = (bool) ($merged['race_enabled'] ?? false);
        $message = $isEnabled
            ? 'تم تفعيل وحفظ إعدادات سباق الترتيب اليومي المتحرك بنجاح'
            : 'تم إخفاء زر سباق الترتيب اليومي عن المستخدمين';

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'walking_race_config' => $quizQuestion->fresh()->getWalkingRaceConfig(),
                ],
                'message' => $message,
                'errors' => null,
            ]);
        }

        return redirect()
            ->route('dashboard.quiz-competitions.show', $quizCompetition)
            ->with('success', $message);
    }
}

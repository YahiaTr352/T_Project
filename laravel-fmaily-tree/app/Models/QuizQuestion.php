<?php

namespace App\Models;

use App\Contracts\Chatable;
use App\Models\QuizRegistration;
use App\Traits\HasChat;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class QuizQuestion extends BaseModel implements Chatable
{
    use HasFactory, HasChat;

    protected $fillable = [
        'quiz_competition_id',
        'question_text',
        'description',
        'answer_type',
        'is_multiple_selections',
        'winners_count',
        'display_order',
        'prize',
        'groups_count',
        'vote_max_selections',
        'vote_edit_duration_hours',
        'linked_walking_question_id',
        'source_vote_question_id',
        'prediction_rank_points',
        'require_prior_registration',
        'walking_settings',
        'voter_eligibility',
        'candidate_rules',
        'has_chat',
    ];

    protected $casts = [
        'is_multiple_selections' => 'boolean',
        'winners_count' => 'integer',
        'groups_count' => 'integer',
        'display_order' => 'integer',
        'vote_max_selections' => 'integer',
        'vote_edit_duration_hours' => 'integer',
        'linked_walking_question_id' => 'integer',
        'source_vote_question_id' => 'integer',
        'prediction_rank_points' => 'array',
        'require_prior_registration' => 'boolean',
        'walking_settings' => 'array',
        'voter_eligibility' => 'array',
        'candidate_rules' => 'array',
        'has_chat' => 'boolean',
    ];

    protected function prize(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if ($value === null || $value === '') {
                    return [];
                }

                if (is_array($value)) {
                    return $value;
                }

                if (is_string($value)) {
                    $decoded = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        return is_array($decoded) ? $decoded : [];
                    }

                    return [$value];
                }

                return [];
            },
            set: fn ($value) => $value === null ? null : json_encode(is_array($value) ? $value : [$value], JSON_UNESCAPED_UNICODE)
        );
    }

    protected static function booted(): void
    {
        static::saved(function () {
            \Illuminate\Support\Facades\Cache::forget('api_active_quiz_competition');
        });
        static::deleted(function () {
            \Illuminate\Support\Facades\Cache::forget('api_active_quiz_competition');
        });

        static::deleting(function (QuizQuestion $question) {
            $question->loadMissing('surveyItems');
            $disk = Storage::disk('public');
            foreach ($question->surveyItems as $item) {
                if ($item->media_path) {
                    $disk->delete($item->media_path);
                }
            }
        });
    }

    public function competition(): BelongsTo
    {
        return $this->belongsTo(QuizCompetition::class, 'quiz_competition_id');
    }

    public function quizCompetition(): BelongsTo
    {
        return $this->competition();
    }

    public function choices(): HasMany
    {
        return $this->hasMany(QuizQuestionChoice::class)->orderBy('id');
    }

    public function surveyItems(): HasMany
    {
        return $this->hasMany(QuizSurveyItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuizAnswer::class);
    }

    public function winners(): HasMany
    {
        return $this->hasMany(QuizWinner::class)->orderBy('position');
    }

    public function walkingScores(): HasMany
    {
        return $this->hasMany(QuizCompetitionWalkingScore::class)->orderBy('date')->orderBy('daily_rank');
    }

    public function walkingAdjustments(): HasMany
    {
        return $this->hasMany(QuizCompetitionWalkingAdjustment::class)->orderByDesc('id');
    }

    public function linkedWalkingQuestion(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class, 'linked_walking_question_id');
    }

    /**
     * Backward-compatibility alias for linkedWalkingQuestion.
     */
    public function linkedWalkingCompetition(): BelongsTo
    {
        return $this->linkedWalkingQuestion();
    }

    public function sourceVoteQuestion(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class, 'source_vote_question_id');
    }

    public function predictionQuestions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class, 'linked_walking_question_id');
    }

    /**
     * هل مسابقة التصويت هذه مرتبطة بتحدي مشي (مسابقة توقعات ترشيح)
     */
    public function isLinkedToWalking(): bool
    {
        return ($this->isVote() || $this->answer_type === 'walking_prediction') && !empty($this->linked_walking_question_id);
    }

    /**
     * هل نوع السؤال مسابقة توقعات تحدي مشي
     */
    public function isWalkingPrediction(): bool
    {
        return $this->isLinkedToWalking();
    }

    /**
     * الحصول على نقاط المراكز المخصصة لمسابقة توقعات المشي
     *
     * @return array<int, int> [rank => points]
     */
    public function getPredictionRankPoints(): array
    {
        $sourceVote = $this->source_vote_question_id ? $this->sourceVoteQuestion : null;
        $max = $sourceVote
            ? max(1, (int) ($sourceVote->vote_max_selections ?: ($sourceVote->choices()->count() ?: 5)))
            : max(1, (int) ($this->vote_max_selections ?: 5));

        if (!empty($this->prediction_rank_points) && is_array($this->prediction_rank_points)) {
            $normalized = [];
            foreach ($this->prediction_rank_points as $rank => $pts) {
                $rInt = (int) $rank;
                if ($rInt >= 1 && $rInt <= $max) {
                    $normalized[$rInt] = max(0, (int) $pts);
                }
            }
            ksort($normalized);
            if (!empty($normalized)) {
                return $normalized;
            }
        }

        // نقاط افتراضية تنازلية إذا لم تُحدد (مثلاً 20, 15, 10, 5, 2...)
        $defaultCurve = [1 => 20, 2 => 15, 3 => 10, 4 => 5, 5 => 3, 6 => 2, 7 => 1];
        $points = [];
        for ($i = 1; $i <= $max; $i++) {
            $points[$i] = $defaultCurve[$i] ?? max(1, 10 - $i);
        }

        return $points;
    }

    /**
     * الحصول على الفئة المستهدفة جنسياً للتحدي المرتبط (رجال / نساء / الكل)
     */
    public function getPredictionTargetGender(): string
    {
        $linked = $this->linkedWalkingQuestion;
        if (!$linked) {
            return 'all';
        }

        return $linked->getWalkingTargetGender();
    }

    public function isWalkingChallenge(): bool
    {
        return $this->answer_type === 'walking';
    }

    public function getWalkingCompetitionScope(): string
    {
        return $this->walking_settings['competition_scope'] ?? 'individuals';
    }

    public function isGroupWalkingChallenge(): bool
    {
        return $this->isWalkingChallenge() && $this->getWalkingCompetitionScope() === 'groups';
    }

    public function getCompetingGroupIds(): array
    {
        $ids = $this->walking_settings['competing_group_ids'] ?? [];
        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_filter(array_map('intval', $ids)));
    }

    public function getGroupScoringMethod(): string
    {
        return $this->walking_settings['group_scoring_mode'] ?? 'total_steps';
    }

    public function getMinMemberDailySteps(): int
    {
        return max(0, (int) ($this->walking_settings['min_member_steps'] ?? 0));
    }

    public function getGroupDailyPoints(): array
    {
        if (! empty($this->walking_settings['group_daily_points']) && is_array($this->walking_settings['group_daily_points'])) {
            $normalized = [];
            foreach ($this->walking_settings['group_daily_points'] as $rank => $pts) {
                $normalized[(int) $rank] = (int) $pts;
            }
            ksort($normalized);
            return $normalized;
        }

        $count = max(2, count($this->getCompetingGroupIds()));
        $points = [];
        for ($i = 1; $i <= $count; $i++) {
            $points[$i] = max(1, ($count - $i + 1) * 5);
        }

        return $points;
    }

    public function getWalkingSettings(): array
    {
        return $this->walking_settings ?? [];
    }

    public function isWalkingRaceEnabled(): bool
    {
        return (bool) data_get($this->walking_settings, 'race_enabled', false);
    }

    public function getWalkingRaceCalcMode(): string
    {
        $mode = (string) data_get($this->walking_settings, 'race_calc_mode', 'cumulative');

        return in_array($mode, ['cumulative', 'daily'], true) ? $mode : 'cumulative';
    }

    public function getWalkingRaceSpeedMs(): int
    {
        $speed = (int) data_get($this->walking_settings, 'race_speed_ms', 2800);

        return max(800, min(10000, $speed));
    }

    public function getWalkingRaceMaxRanks(): int
    {
        $raw = data_get($this->walking_settings, 'race_max_ranks', 10);
        $ranks = is_numeric($raw) ? (int) $raw : 10;
        if ($ranks <= 0) {
            return 0; // 0 = display all active contestants
        }

        return max(1, min(500, $ranks));
    }

    public function getWalkingRaceTitle(): string
    {
        $customTitle = trim((string) data_get($this->walking_settings, 'race_title', ''));
        if ($customTitle !== '') {
            return $customTitle;
        }

        $compTitle = trim(strip_tags((string) ($this->competition?->title ?? '')));
        if ($compTitle !== '') {
            return $compTitle;
        }

        $qText = trim(strip_tags((string) ($this->question_text ?? '')));

        return $qText !== '' ? $qText : 'سباق الترتيب اليومي';
    }

    public function getWalkingRaceSubtitle(): string
    {
        $subtitle = trim((string) data_get($this->walking_settings, 'race_subtitle', ''));

        return $subtitle !== '' ? $subtitle : 'ماهي مجرد مسابقة... بل بداية عادة';
    }

    public function getWalkingRaceButtonText(): string
    {
        $text = trim((string) data_get($this->walking_settings, 'race_button_text', ''));

        return $text !== '' ? $text : 'سباق الترتيب اليومي';
    }

    /**
     * Get normalized Bar Chart Race configuration for this walking question.
     *
     * @return array<string, mixed>
     */
    public function getWalkingRaceConfig(): array
    {
        return [
            'enabled' => $this->isWalkingRaceEnabled(),
            'calc_mode' => $this->getWalkingRaceCalcMode(),
            'speed_ms' => $this->getWalkingRaceSpeedMs(),
            'max_ranks' => $this->getWalkingRaceMaxRanks(),
            'title' => $this->getWalkingRaceTitle(),
            'subtitle' => $this->getWalkingRaceSubtitle(),
            'button_text' => $this->getWalkingRaceButtonText(),
        ];
    }

    public function getWalkingTargetGender(): string
    {
        return $this->walking_settings['target_gender'] ?? 'both_segregated';
    }

    public function getWalkingScoringMethod(): string
    {
        return $this->walking_settings['scoring_method'] ?? 'daily_rank_points';
    }

    public function getWalkingDailyPoints(): array
    {
        if (! empty($this->walking_settings['daily_points']) && is_array($this->walking_settings['daily_points'])) {
            $normalized = [];
            foreach ($this->walking_settings['daily_points'] as $rank => $pts) {
                $normalized[(int) $rank] = (int) $pts;
            }
            ksort($normalized);
            return $normalized;
        }

        $count = max(1, (int) ($this->winners_count ?: 3));
        $points = [];
        for ($i = 1; $i <= $count; $i++) {
            $points[$i] = max(1, $count - $i + 1);
        }

        return $points;
    }

    public function isActive(): bool
    {
        $comp = $this->competition;
        if (! $comp || ! $comp->start_at || ! $comp->end_at) {
            return false;
        }
        $now = now();

        return $now->gte($comp->start_at) && $now->lte($comp->end_at);
    }

    public function hasEnded(): bool
    {
        $comp = $this->competition;
        if (! $comp || ! $comp->end_at) {
            return false;
        }

        return now()->gt($comp->end_at);
    }

    public function hasNotStarted(): bool
    {
        $comp = $this->competition;
        if (! $comp || ! $comp->start_at) {
            return true;
        }

        return now()->lt($comp->start_at);
    }

    public function getCorrectAnswersCount(): int
    {
        return $this->answers()->where('is_correct', true)->count();
    }

    public function getRequiredCorrectAnswersCount(): int
    {
        if ($this->answer_type !== 'multiple_choice') {
            return 1;
        }

        return $this->choices()->where('is_correct', true)->count();
    }

    public function selectRandomWinners(): int
    {
        $this->winners()->delete();

        return $this->fillVacantWinners();
    }

    public function fillVacantWinners(): int
    {
        $existingWinners = $this->winners()->get();
        $existingWinnerUserIds = $existingWinners->pluck('user_id')->filter()->all();
        $existingWinnerPersonIds = $existingWinners->pluck('person_id')->filter()->all();
        $currentWinnerCount = $existingWinners->count();
        $vacancies = $this->winners_count - $currentWinnerCount;

        if ($vacancies <= 0) {
            return 0;
        }

        $candidateAnswers = $this->answers()
            ->where('is_correct', true)
            ->where(function ($q) use ($existingWinnerUserIds, $existingWinnerPersonIds) {
                if (!empty($existingWinnerPersonIds)) {
                    $q->whereNotIn('person_id', $existingWinnerPersonIds);
                }
                if (!empty($existingWinnerUserIds)) {
                    $q->whereNotIn('user_id', $existingWinnerUserIds);
                }
            })
            ->get()
            ->shuffle()
            ->take($vacancies);

        foreach ($candidateAnswers as $index => $answer) {
            QuizWinner::create([
                'quiz_question_id' => $this->id,
                'person_id' => $answer->person_id,
                'user_id' => $answer->user_id,
                'position' => $currentWinnerCount + $index + 1,
            ]);
        }

        // إزالة التخزين المؤقت لكي تظهر النتيجة الجديدة فوراً للجمهور
        \Illuminate\Support\Facades\Cache::forget('quiz_winner_json_'.$this->id);
        \Illuminate\Support\Facades\Cache::forget('quiz-winner-selection-'.$this->id);

        return $candidateAnswers->count();
    }
    /**
     * التحقق من أهلية المشاهد/المصوت بناءً على ضوابط السؤال
     *
     * @return array{eligible: bool, code: string, message: string}
     */
    public function checkVoterEligibility(?Person $person, ?string $authMethod): array
    {
        $eligibility = $this->voter_eligibility ?? [];
        $requireIdentity = $eligibility['require_identity'] ?? true;

        // 1. فحص الهوية (حظر زوار رمز الموقع 112)
        if ($requireIdentity) {
            $isIdentityAuth = in_array($authMethod, ['face', 'whatsapp'], true);
            if (!$isIdentityAuth || !$person) {
                $msg = !empty($eligibility['custom_message']) ? $eligibility['custom_message'] : 'غير مسموح لك بالمشاركة في هذا التصويت. المشاركة مخصصة لأفراد العائلة المسجلين ببصمة الوجه أو برقم الواتساب.';
                return [
                    'eligible' => false,
                    'code' => 'GUEST_112_NOT_ALLOWED',
                    'message' => $msg,
                    'reason' => $msg,
                ];
            }
        }

        // إذا لم يكن هناك شخص محدد، تحقق هل توجد أي ضوابط تشترط التحقق من الهوية أو بيانات الشخص
        if (!$person) {
            $hasStrictRule = !empty($eligibility['require_whatsapp'])
                || (isset($eligibility['allowed_gender']) && in_array($eligibility['allowed_gender'], ['male', 'female'], true))
                || !empty($eligibility['min_age'])
                || !empty($eligibility['require_ancestry']);

            if ($hasStrictRule) {
                $msg = 'عذراً، يتطلب هذا التصويت توثيق هويتك العائلية عبر الواتساب أو بصمة الوجه للتأكد من مطابقة شروط التصويت.';
                return [
                    'eligible' => false,
                    'code' => 'AUTH_REQUIRED',
                    'message' => $msg,
                    'reason' => $msg,
                ];
            }

            return [
                'eligible' => true,
                'code' => 'ELIGIBLE',
                'message' => 'مؤهل للمشاركة.',
                'reason' => 'مؤهل للمشاركة.',
            ];
        }

        // 2. اشتراط وجود رقم واتساب
        if (!empty($eligibility['require_whatsapp'])) {
            $hasWhatsapp = $person->contactAccounts()->whereIn('type', ['whatsapp', 'phone'])->whereNotNull('value')->where('value', '!=', '')->exists();
            if (!$hasWhatsapp) {
                $msg = 'عذراً، يتطلب هذا التصويت توفر رقم واتساب مسجل في حسابك العائلي.';
                return [
                    'eligible' => false,
                    'code' => 'WHATSAPP_REQUIRED',
                    'message' => $msg,
                    'reason' => $msg,
                ];
            }
        }

        // 3. اشتراط الجنس (رجال / نساء)
        $allowedGender = $eligibility['allowed_gender'] ?? 'all';
        if (in_array($allowedGender, ['male', 'female'], true) && $person->gender !== $allowedGender) {
            $msg = $allowedGender === 'male' ? 'عذراً، هذا التصويت مخصص للرجال فقط.' : 'عذراً، هذا التصويت مخصص للنساء فقط.';
            return [
                'eligible' => false,
                'code' => 'GENDER_RESTRICTED',
                'message' => $msg,
                'reason' => $msg,
            ];
        }

        // 4. اشتراط العمر الأدنى
        if (!empty($eligibility['min_age']) && $person->birth_date) {
            $age = $person->birth_date->age;
            if ($age < (int)$eligibility['min_age']) {
                $msg = 'عذراً، هذا التصويت متاح فقط لمن بلغ ' . $eligibility['min_age'] . ' سنة فأكثر.';
                return [
                    'eligible' => false,
                    'code' => 'AGE_RESTRICTED',
                    'message' => $msg,
                    'reason' => $msg,
                ];
            }
        }

        // 5. اشتراط سلالة العائلة (Ancestry)
        if (!empty($eligibility['require_ancestry']) && $person->from_outside_the_family) {
            $msg = 'عذراً، هذا التصويت مخصص لأفراد سلالة العائلة فقط.';
            return [
                'eligible' => false,
                'code' => 'ANCESTRY_REQUIRED',
                'message' => $msg,
                'reason' => $msg,
            ];
        }

        return [
            'eligible' => true,
            'code' => 'ELIGIBLE',
            'message' => 'مؤهل للمشاركة.',
            'reason' => 'مؤهل للمشاركة.',
        ];
    }

    /**
     * جلب نتائج التصويت الحية مرتبة من الأعلى تصويتاً مع بيانات المرشحين
     */
    /**
     * Resolve the effective target gender ('male', 'female', or 'all') for nominees/choices on this question.
     */
    public function resolveEffectiveAllowedGender(): string
    {
        $rulesGender = $this->getCandidateRules()['allowed_gender'] ?? 'all';
        if (in_array($rulesGender, ['male', 'female'], true)) {
            return $rulesGender;
        }

        if ($this->isWalkingChallenge()) {
            $walkingGender = $this->getWalkingTargetGender();
            if (in_array($walkingGender, ['male', 'female'], true)) {
                return $walkingGender;
            }
        }

        if ($this->isLinkedToWalking()) {
            $linkedGender = $this->getPredictionTargetGender();
            if (in_array($linkedGender, ['male', 'female'], true)) {
                return $linkedGender;
            }
        }

        return 'all';
    }

    /**
     * Check whether a Person matches the question's effective allowed gender.
     */
    public function isPersonGenderCompatibleWithQuestion(?Person $person): bool
    {
        if (!$person) {
            return false;
        }

        $allowedGender = $this->resolveEffectiveAllowedGender();
        if (in_array($allowedGender, ['male', 'female'], true)) {
            return $person->gender === $allowedGender;
        }

        return true;
    }

    public function getLiveVoteResultsData(): array
    {
        $this->loadMissing(['choices.person']);

        $voteCountsByChoiceId = \Illuminate\Support\Facades\DB::table('quiz_answer_choices')
            ->join('quiz_answers', 'quiz_answers.id', '=', 'quiz_answer_choices.quiz_answer_id')
            ->where('quiz_answers.quiz_question_id', $this->id)
            ->selectRaw('quiz_answer_choices.quiz_question_choice_id, COUNT(*) as votes_count')
            ->groupBy('quiz_answer_choices.quiz_question_choice_id')
            ->pluck('votes_count', 'quiz_question_choice_id');

        $totalVotes = (int) $voteCountsByChoiceId->sum();
        $questionGender = $this->resolveEffectiveAllowedGender();
        $fallbackGender = in_array($questionGender, ['male', 'female'], true) ? $questionGender : null;

        $choicesData = [];
        foreach ($this->choices as $choice) {
            $voteCount = (int) ($voteCountsByChoiceId->get($choice->id) ?? 0);
            $trimmedText = trim((string) $choice->choice_text);

            // تجاهل الصفوف الفارغة غير المصوت لها والتي لا تحتوي على بيانات مرشح
            if (!$choice->person_id && $trimmedText === '' && !$choice->image && !$choice->video && $voteCount === 0) {
                continue;
            }

            $person = $choice->person;

            // استبعاد المرشحين من جنس مخالف لإعدادات السؤال عندما تكون أصواتهم صفراً
            if (
                $person
                && in_array($questionGender, ['male', 'female'], true)
                && $person->gender !== $questionGender
                && $voteCount === 0
            ) {
                continue;
            }

            $percent = $totalVotes > 0 ? round(($voteCount / $totalVotes) * 100, 1) : 0;
            $effectiveGender = $person?->gender ?? $fallbackGender;

            $avatarUrl = null;
            if ($person && $person->avatar) {
                $avatarUrl = $person->avatar;
            } elseif ($choice->image) {
                $avatarUrl = asset('storage/' . $choice->image);
            }
            if (!$avatarUrl) {
                $avatarUrl = Person::anonymousAvatarFor($effectiveGender ?? 'male');
            }

            $choicesData[] = [
                'id' => $choice->id,
                'person_id' => $person ? $choice->person_id : null,
                'text' => $person ? $person->name : $choice->choice_text,
                'full_name' => $person ? $person->name : $choice->choice_text,
                'gender' => $effectiveGender,
                'count' => $voteCount,
                'percent' => $percent,
                'avatar_url' => $avatarUrl,
                'profile_url' => ($person && $choice->person_id) ? route('people.profile.show', $choice->person_id) : null,
                'image_url' => $choice->image ? asset('storage/' . $choice->image) : null,
                'video_url' => $choice->video ? asset('storage/' . $choice->video) : null,
            ];
        }

        // ترتيب الخيارات تنازلياً حسب عدد الأصوات
        usort($choicesData, fn($a, $b) => $b['count'] <=> $a['count']);

        // تعيين الترتيب والمراكز
        foreach ($choicesData as $index => &$item) {
            $item['rank'] = $index + 1;
        }

        return [
            'total_votes' => $totalVotes,
            'results' => $choicesData,
        ];
    }
    /**
     * هل نوع السؤال تصويت
     */
    public function isVote(): bool
    {
        return $this->answer_type === 'vote';
    }

    /**
     * هل التصويت يعتمد على ترشيح واختيار حر من شجرة العائلة من قِبل المشاركين
     */
    public function isOpenTreeVoting(): bool
    {
        return $this->isVote() && (($this->candidate_rules['nomination_type'] ?? 'predefined') === 'open_tree');
    }

    /**
     * هل تعديل التصويت متاح دائماً بدون قيد زمني
     */
    public function isVoteEditUnlimited(): bool
    {
        return $this->isVote() && (int) $this->vote_edit_duration_hours === -1;
    }

    /**
     * هل تعديل التصويت ممنوع نهائياً بعد إرساله أول مرة
     */
    public function isVoteEditForbidden(): bool
    {
        return $this->isVote() && (int) $this->vote_edit_duration_hours === 0;
    }

    /**
     * الحصول على مهلة تعديل التصويت بالساعات
     */
    public function getVoteEditDurationHours(): int
    {
        return $this->vote_edit_duration_hours ?? 24;
    }

    /**
     * حساب الموعد النهائي لتعديل التصويت بناءً على تاريخ أول تصويت
     */
    public function calculateVoteEditDeadline(\Carbon\Carbon $firstVotedAt): ?\Carbon\Carbon
    {
        if ($this->isVoteEditUnlimited()) {
            return null;
        }

        if ($this->isVoteEditForbidden()) {
            return $firstVotedAt->copy();
        }

        return $firstVotedAt->copy()->addHours($this->getVoteEditDurationHours());
    }

    /**
     * إرجاع ضوابط وشروط الترشيح للمرشحين
     *
     * @return array{nomination_type: string, allowed_gender: string, require_whatsapp: bool, exclude_deceased: bool}
     */
    public function getCandidateRules(): array
    {
        $nominationType = $this->candidate_rules['nomination_type'] ?? null;
        if (! $nominationType) {
            if ($this->choices()->whereNotNull('person_id')->exists()) {
                $nominationType = 'predefined_tree';
            } elseif ($this->choices()->count() > 0) {
                $nominationType = 'custom_options';
            } else {
                $nominationType = 'open_tree';
            }
        } elseif ($nominationType === 'predefined') {
            $nominationType = $this->choices()->whereNotNull('person_id')->exists() ? 'predefined_tree' : 'custom_options';
        }

        $walkingCondition = $this->candidate_rules['walking_condition'] ?? [];
        $walkingEnabled = !empty($walkingCondition['enabled']);
        $walkingDays = max(1, (int)($walkingCondition['days_window'] ?? 3));
        $walkingMinSteps = max(0, (int)($walkingCondition['min_daily_steps'] ?? 0));
        $walkingMode = in_array($walkingCondition['condition_type'] ?? '', ['any_day', 'all_days'], true)
            ? $walkingCondition['condition_type']
            : 'any_day';

        return [
            'nomination_type' => $nominationType,
            'allowed_gender' => $this->candidate_rules['allowed_gender'] ?? 'all',
            'require_whatsapp' => (bool) ($this->candidate_rules['require_whatsapp'] ?? false),
            'exclude_deceased' => (bool) ($this->candidate_rules['exclude_deceased'] ?? true),
            'candidate_scope' => $this->candidate_rules['candidate_scope'] ?? 'all',
            'exclude_outside_family' => (bool) ($this->candidate_rules['exclude_outside_family'] ?? false),
            'exclude_maternal_children' => (bool) ($this->candidate_rules['exclude_maternal_children'] ?? false),
            'exclude_breastfed' => (bool) ($this->candidate_rules['exclude_breastfed'] ?? false),
            'exclude_original_family' => (bool) ($this->candidate_rules['exclude_original_family'] ?? false),
            'walking_condition' => [
                'enabled' => $walkingEnabled,
                'days_window' => $walkingDays,
                'min_daily_steps' => $walkingMinSteps,
                'condition_type' => $walkingMode,
            ],
        ];
    }

    /**
     * التحقق من أهلية الشخص المرشح ليكون خيار تصويت
     *
     * @return array{eligible: bool, message: ?string}
     */
    public function checkCandidateEligibility(Person $person): array
    {
        if (! $this->isOpenTreeVoting()) {
            return ['eligible' => true, 'message' => null];
        }

        $rules = $this->getCandidateRules();

        if ($rules['exclude_deceased'] && $person->death_date !== null) {
            return [
                'eligible' => false,
                'message' => 'عذراً، هذا التصويت مخصص للأحياء فقط ولا يشمل المتوفين.',
            ];
        }

        if ($rules['allowed_gender'] === 'male' && $person->gender !== 'male') {
            return [
                'eligible' => false,
                'message' => 'غير مسموح بترشيح هذا الشخص؛ هذا التصويت مخصص لرجال العائلة فقط.',
            ];
        }

        if ($rules['allowed_gender'] === 'female' && $person->gender !== 'female') {
            return [
                'eligible' => false,
                'message' => 'غير مسموح بترشيح هذا الشخص؛ هذا التصويت مخصص لنساء العائلة فقط.',
            ];
        }

        // فحص الفئة العائلية (سلالة العائلة، أبناء نساء العائلة، بالرضاعة، من خارج العائلة)
        $eligibilityService = app(\App\Services\Quiz\CandidateEligibilityService::class);
        $category = $eligibilityService->resolveCategory($person);
        $categoryCheck = $eligibilityService->isCategoryAllowed($category, $rules);
        if (!$categoryCheck['allowed']) {
            return [
                'eligible' => false,
                'message' => $categoryCheck['reason'],
            ];
        }

        if ($rules['require_whatsapp']) {
            $hasWhatsapp = $person->contactAccounts()->whereIn('type', ['whatsapp', 'phone'])->whereNotNull('value')->where('value', '!=', '')->exists();
            if (! $hasWhatsapp) {
                return [
                    'eligible' => false,
                    'message' => 'يُشترط في المرشح أن يملك رقم واتساب مسجلاً في دليل العائلة.',
                ];
            }
        }

        // فحص شرط نشاط المشي إذا تم تفعيله
        if (!empty($rules['walking_condition']['enabled'])) {
            $days = (int) ($rules['walking_condition']['days_window'] ?? 3);
            $minSteps = (int) ($rules['walking_condition']['min_daily_steps'] ?? 0);
            $mode = $rules['walking_condition']['condition_type'] ?? 'any_day';

            // معرفات المستخدمين المرتبطين بهذا الشخص عبر أرقام هواتفه للربط بسجلات المشي
            $linkedUserIds = $person->getLinkedUserIds();

            // نافذة البحث بمدى كافٍ لاحتساب الأيام المطلوبة بدون قطع مبكر
            $lookbackDays = ($mode === 'all_days') ? max($days * 3, 30) : max($days + 3, 7);
            $startDate = \Carbon\Carbon::now('Asia/Riyadh')->subDays($lookbackDays)->format('Y-m-d');
            $endDate = \Carbon\Carbon::now('Asia/Riyadh')->addDay()->format('Y-m-d');

            // تجميع خطوات اليوم الواحد للشخص من جدول walking_records
            $dailyRecordsQuery = \App\Models\WalkingRecord::query()
                ->where(function ($q) use ($person, $linkedUserIds) {
                    $q->where('person_id', $person->id);
                    if (!empty($linkedUserIds)) {
                        $q->orWhereIn('user_id', $linkedUserIds);
                    }
                })
                ->whereDate('date', '>=', $startDate)
                ->whereDate('date', '<=', $endDate)
                ->selectRaw('DATE(date) as walk_date, SUM(steps) as total_steps')
                ->groupBy(\Illuminate\Support\Facades\DB::raw('DATE(date)'));

            if ($minSteps > 0) {
                $dailyRecordsQuery->havingRaw('SUM(steps) >= ?', [$minSteps]);
            } else {
                $dailyRecordsQuery->havingRaw('SUM(steps) > 0');
            }

            $qualifyingDays = $dailyRecordsQuery->get();
            $qualifyingDaysCount = $qualifyingDays->count();

            if ($mode === 'all_days') {
                if ($qualifyingDaysCount < $days) {
                    return [
                        'eligible' => false,
                        'message' => "عذراً، يُشترط في المرشح أن يكون قد سجل نشاط مشي يومياً لما لا يقل عن {$days} أيام خلال الفترة الحالية.",
                    ];
                }
            } else {
                if ($qualifyingDaysCount < 1) {
                    return [
                        'eligible' => false,
                        'message' => "عذراً، يُشترط في المرشح أن يكون قد سجل نشاط مشي في برنامج المشي خلال آخر {$days} أيام.",
                    ];
                }
            }
        }

        return ['eligible' => true, 'message' => null];
    }

    /**
     * إرجاع مصفوفة بمعرفات الأشخاص المؤهلين وفق شروط المشي
     *
     * @return array<int, int>
     */
    public static function getEligibleWalkingPersonIds(int $daysWindow = 3, int $minSteps = 0, string $conditionType = 'any_day'): array
    {
        $lookbackDays = ($conditionType === 'all_days') ? max($daysWindow * 3, 30) : max($daysWindow + 3, 7);
        $startDate = \Carbon\Carbon::now('Asia/Riyadh')->subDays($lookbackDays)->format('Y-m-d');
        $endDate = \Carbon\Carbon::now('Asia/Riyadh')->addDay()->format('Y-m-d');

        $records = \App\Models\WalkingRecord::query()
            ->selectRaw('walking_records.person_id as resolved_person_id, DATE(walking_records.date) as w_date, SUM(walking_records.steps) as total_steps')
            ->whereDate('walking_records.date', '>=', $startDate)
            ->whereDate('walking_records.date', '<=', $endDate)
            ->whereNotNull('walking_records.person_id')
            ->groupBy('walking_records.person_id', \Illuminate\Support\Facades\DB::raw('DATE(walking_records.date)'));

        if ($minSteps > 0) {
            $records->havingRaw('SUM(walking_records.steps) >= ?', [$minSteps]);
        } else {
            $records->havingRaw('SUM(walking_records.steps) > 0');
        }

        $dailyAggregates = $records->get();

        $personDaysCount = [];
        foreach ($dailyAggregates as $row) {
            $pid = (int) $row->resolved_person_id;
            $personDaysCount[$pid] = ($personDaysCount[$pid] ?? 0) + 1;
        }

        $eligibleIds = [];
        $requiredDays = ($conditionType === 'all_days') ? $daysWindow : 1;
        foreach ($personDaysCount as $pid => $count) {
            if ($count >= $requiredDays) {
                $eligibleIds[] = $pid;
            }
        }

        return $eligibleIds;
    }

    /**
     * التحقق مما إذا كان السؤال/المسابقة مرئياً للمستخدم أو الزائر في الصفحة الرئيسية
     * مبدأ الفصل غير المتماثل (Asymmetrical Visibility):
     * - مسابقات وتحديات النساء للنساء فقط (محجوبة عن الرجال والزوار غير المسجلين).
     * - مسابقات وتحديات الرجال ظاهرة للجميع (للرجال كاملة، وللنساء والزوار للعرض فقط بدون شات وبدون مشاركة).
     * - المسابقات المشتركة (both_segregated / all) تظهر للجميع.
     */
    public function isVisibleTo(?Person $person, ?string $authMethod = null): bool
    {
        // 1. تحدي المشي الفردي أو الجماعي
        if ($this->isWalkingChallenge()) {
            $targetGender = $this->getWalkingTargetGender();
            if ($targetGender === 'female') {
                return $person !== null && $person->gender === 'female';
            }
            // مسابقة الرجال ظاهرة للجميع (رجال/نساء/زوار) — النساء والزوار عرض فقط
            return true;
        }

        // 2. مسابقة التصويت أو التوقعات
        if ($this->isVote() || $this->answer_type === 'walking_prediction') {
            $voterAllowed = $this->voter_eligibility['allowed_gender'] ?? 'all';

            // إذا كان التصويت مخصصاً للنساء فقط
            if ($voterAllowed === 'female') {
                return $person !== null && $person->gender === 'female';
            }

            // إذا كان التصويت مخصصاً للرجال فقط
            if ($voterAllowed === 'male') {
                return $person !== null && $person->gender === 'male';
            }

            // إذا كانت مسابقة توقعات مرتبطة بتحدي مشي ولم يُحدد جنس المصوتين صراحة
            if ($this->isWalkingPrediction() && empty($this->voter_eligibility['allowed_gender'])) {
                $linkedGender = $this->getPredictionTargetGender();
                if ($linkedGender === 'female') {
                    return $person !== null && $person->gender === 'female';
                }
                // التوقعات المرتبطة بتحدي الرجال ظاهرة للجميع (عرض فقط لغير الرجال)
            }

            return true;
        }

        return true;
    }

    /**
     * هل يشاهد هذا الشخص المسابقة للعرض فقط بدون مشاركة وبدون شات؟
     * (امرأة أو زائر يشاهد مسابقة/توقعات مرتبطة بتحدي مشي للرجال)
     */
    public function isViewOnlyFor(?Person $person): bool
    {
        if ($person !== null && $person->gender === 'male') {
            return false;
        }

        if ($this->isWalkingChallenge()) {
            return $this->getWalkingTargetGender() === 'male';
        }

        if ($this->isWalkingPrediction() && ! $this->isVote()) {
            return $this->getPredictionTargetGender() === 'male';
        }

        if ($this->isWalkingPrediction() && $this->isVote()) {
            $voterAllowed = $this->voter_eligibility['allowed_gender'] ?? 'all';
            // الإعداد الصريح (مخصص للنساء) يُحترم ويتيح المشاركة؛ أما الافتراضي فعرض فقط
            if (in_array($voterAllowed, ['all', ''], true)) {
                return $this->getPredictionTargetGender() === 'male';
            }
        }

        return false;
    }

    /**
     * هل يحق لهذا الشخص الوصول لشات السؤال وقراءته؟
     * شات مسابقة الرجال للرجال فقط (مخفي عن النساء والزوار)، وشات النساء للنساء فقط.
     */
    public function canViewChatFor(?Person $person): bool
    {
        if (! $this->has_chat) {
            return false;
        }

        if ($person === null) {
            return false;
        }

        $target = $this->isWalkingChallenge()
            ? $this->getWalkingTargetGender()
            : ($this->isLinkedToWalking() ? $this->getPredictionTargetGender() : 'all');

        if ($target === 'female') {
            return $person->gender === 'female';
        }

        if ($target === 'male') {
            return $person->gender === 'male';
        }

        return true;
    }

    /**
     * التحقق مما إذا كان بإمكان المشاهد رؤية لوحة صدارة خطوات المشي
     * - مسابقات مشي النساء لا تظهر لوحة صدارتها وخطواتهن إلا للنساء فقط.
     * - مسابقات مشي الرجال ظاهرة للجميع (رجال/نساء/زوار) للعرض فقط.
     */
    public function canViewWalkingLeaderboard(?Person $person): bool
    {
        $target = $this->isWalkingChallenge()
            ? $this->getWalkingTargetGender()
            : ($this->isLinkedToWalking() ? $this->getPredictionTargetGender() : 'all');

        if ($target === 'female') {
            return $person !== null && $person->gender === 'female';
        }

        return true;
    }

    // ── Chatable Implementation ───────────────────────────────────

    /**
     * Determines whether a given person has permission to view the chat and read messages.
     */
    public function canAccessChat(int $personId): bool
    {
        return (bool) $this->has_chat;
    }

    /**
     * Determines whether a given person has permission to send messages to the chat.
     */
    public function canSendMessage(int $personId): bool
    {
        return (bool) $this->has_chat;
    }

    /**
     * Returns a collection of person IDs who should receive push notifications.
     *
     * الشات مكتوم افتراضياً (Muted by default).
     * المستلمون = المشاركون الذين ألغوا كتم المحادثة صراحة (is_muted = false)
     * مع استبعاد المُرسِل، وبشرط أن يكونوا قد أجابوا على السؤال أو سجّلوا في المسابقة.
     *
     * @return Collection<int, int>
     */
    public function getChatNotificationRecipients(int $senderPersonId): Collection
    {
        $conversation = $this->conversation;
        if (! $conversation) {
            return collect();
        }

        // 1. جلب من ألغى كتم المحادثة صراحة
        $unmutedPersonIds = $conversation->participants()
            ->where('is_muted', false)
            ->where('person_id', '!=', $senderPersonId)
            ->pluck('person_id');

        if ($unmutedPersonIds->isEmpty()) {
            return collect();
        }

        // 2. التحقق من أهليتهم (إما أجابوا على السؤال أو مسجلين في المسابقة)
        $competitionId = $this->quiz_competition_id;
        $registeredIds = $competitionId
            ? QuizRegistration::query()
                ->where('quiz_competition_id', $competitionId)
                ->whereIn('person_id', $unmutedPersonIds)
                ->pluck('person_id')
                ->all()
            : [];

        $answererIds = $this->answers()
            ->whereIn('person_id', $unmutedPersonIds)
            ->pluck('person_id')
            ->all();

        $eligibleIds = array_values(array_unique(array_merge($answererIds, $registeredIds)));

        return $unmutedPersonIds->intersect($eligibleIds)->values();
    }

    /**
     * Display title for the chat room.
     */
    public function getChatTitle(): string
    {
        return $this->question_text ?: 'نقاش ' . ($this->competition?->title ?? 'المسابقة');
    }

    /**
     * الحمولة المرسلة مع إشعار push لتوجيه التطبيق إلى نقاش السؤال.
     */
    public function getChatNotificationPayload(): array
    {
        return [
            'type'        => 'quiz_question_chat',
            'action_url'  => "/(tabs)/quiz?questionId={$this->id}&openChat=1",
            'question_id' => (int) $this->id,
        ];
    }
}

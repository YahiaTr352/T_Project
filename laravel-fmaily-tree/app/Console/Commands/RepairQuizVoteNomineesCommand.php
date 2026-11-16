<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Person;
use App\Models\QuizAnswer;
use App\Models\QuizAnswerChoice;
use App\Models\QuizQuestion;
use App\Models\QuizQuestionChoice;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RepairQuizVoteNomineesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'quiz:repair-vote-nominees
        {--dry-run : Inspect and report repairs without modifying the database}
        {--question= : Optional specific quiz_question_id to inspect/repair}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Repairs orphaned, unlinked, blank, or gender-mismatched vote nominees and restores answer choice links';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $questionIdFilter = $this->option('question') ? (int) $this->option('question') : null;

        $this->info($dryRun
            ? 'Running quiz:repair-vote-nominees in DRY-RUN mode (no DB writes)...'
            : 'Running quiz:repair-vote-nominees in APPLY mode...');

        $nameIndexes = $this->buildPersonNameIndexes();

        $questionsQuery = QuizQuestion::with(['competition', 'choices.person', 'linkedWalkingQuestion'])
            ->where('answer_type', 'vote');

        if ($questionIdFilter) {
            $questionsQuery->whereKey($questionIdFilter);
        }

        $questions = $questionsQuery->orderBy('id')->get();
        if ($questions->isEmpty()) {
            $this->warn('No matching vote questions found.');
            return self::SUCCESS;
        }

        $summary = [
            'questions' => 0,
            'relinked_choices' => 0,
            'merged_duplicates' => 0,
            'deleted_blank_choices' => 0,
            'removed_mismatched_choices' => 0,
            'restored_answer_pivots' => 0,
            'cleaned_invalid_pivots' => 0,
            'ambiguous_choices' => 0,
        ];

        $runRepair = function () use ($questions, $nameIndexes, $dryRun, &$summary): void {
            foreach ($questions as $question) {
                $summary['questions']++;
                $this->processQuestion($question, $nameIndexes, $dryRun, $summary);
            }
        };

        if ($dryRun) {
            $runRepair();
        } else {
            DB::transaction($runRepair);
            $this->clearRelatedCaches($questions);
        }

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            collect($summary)->map(fn (int $val, string $key): array => [$key, (string) $val])->values()->all()
        );

        return self::SUCCESS;
    }

    /**
     * Build normalized Arabic name lookup maps across all persons (bypassing global scopes).
     *
     * @return array{full: array<string, array<int, Person>>, name: array<string, array<int, Person>>, rel: array<string, array<int, Person>>}
     */
    private function buildPersonNameIndexes(): array
    {
        $persons = Person::withoutGlobalScopes()->with('parent')->get();
        $indexes = ['full' => [], 'name' => [], 'rel' => []];

        foreach ($persons as $person) {
            $normFull = $this->normalizeArabicText($person->full_name);
            if ($normFull !== '') {
                $indexes['full'][$normFull][$person->id] = $person;
            }

            $normName = $this->normalizeArabicText($person->name);
            if ($normName !== '') {
                $indexes['name'][$normName][$person->id] = $person;
            }

            $normRel = $this->normalizeArabicText($person->full_name_with_relations);
            if ($normRel !== '') {
                $indexes['rel'][$normRel][$person->id] = $person;
            }
        }

        return $indexes;
    }

    /**
     * Repair choices, duplicates, blank rows, and answer pivots for a single vote question.
     *
     * @param  array<string, array<string, array<int, Person>>>  $nameIndexes
     * @param  array<string, int>  $summary
     */
    private function processQuestion(
        QuizQuestion $question,
        array $nameIndexes,
        bool $dryRun,
        array &$summary
    ): void {
        $allowedGender = $question->resolveEffectiveAllowedGender();
        $rules = $question->getCandidateRules();
        $excludeOutsideFamily = (bool) ($rules['exclude_outside_family'] ?? false);

        $this->line(sprintf(
            'Q#%d (%s) | gender=%s | nom=%s | choices=%d',
            $question->id,
            $question->competition?->title ?? 'N/A',
            $allowedGender,
            $rules['nomination_type'] ?? 'custom_options',
            $question->choices->count()
        ));

        $pivotCounts = QuizAnswerChoice::query()
            ->join('quiz_answers', 'quiz_answers.id', '=', 'quiz_answer_choices.quiz_answer_id')
            ->where('quiz_answers.quiz_question_id', $question->id)
            ->selectRaw('quiz_answer_choices.quiz_question_choice_id as cid, COUNT(*) as cnt')
            ->groupBy('quiz_answer_choices.quiz_question_choice_id')
            ->pluck('cnt', 'cid');

        /** @var array<int, QuizQuestionChoice> $canonicalByPersonId */
        $canonicalByPersonId = [];
        foreach ($question->choices as $choice) {
            if ($choice->person_id && $choice->person && $question->isPersonGenderCompatibleWithQuestion($choice->person)) {
                if (!isset($canonicalByPersonId[(int) $choice->person_id])) {
                    $canonicalByPersonId[(int) $choice->person_id] = $choice;
                }
            }
        }

        /** @var array<int, int> $remappedChoiceIds */
        $remappedChoiceIds = [];
        /** @var array<int, bool> $removedChoiceIds */
        $removedChoiceIds = [];

        foreach ($question->choices as $choice) {
            $voteCount = (int) ($pivotCounts->get($choice->id) ?? 0);
            $trimmedText = trim((string) $choice->choice_text);

            // 1. Delete completely blank unvoted rows without media or person link.
            if (
                !$choice->person_id
                && $trimmedText === ''
                && !$choice->image
                && !$choice->video
                && $voteCount === 0
            ) {
                $summary['deleted_blank_choices']++;
                $this->line("  [DELETE-BLANK] Choice#{$choice->id}");
                if (!$dryRun) {
                    $choice->delete();
                }
                $removedChoiceIds[$choice->id] = true;
                continue;
            }

            $currentPerson = $choice->person;
            $hasMissingPerson = $choice->person_id !== null && !$currentPerson;
            $hasGenderMismatch = $currentPerson !== null
                && !$question->isPersonGenderCompatibleWithQuestion($currentPerson);

            // 2. Skip choices already linked to a valid compatible person (unless duplicate).
            if ($currentPerson && !$hasGenderMismatch) {
                $canonical = $canonicalByPersonId[(int) $currentPerson->id] ?? null;
                if ($canonical && $canonical->id !== $choice->id) {
                    $summary['merged_duplicates']++;
                    $remappedChoiceIds[$choice->id] = $canonical->id;
                    $removedChoiceIds[$choice->id] = true;
                    $this->line("  [MERGE-DUP] Choice#{$choice->id} -> Choice#{$canonical->id} (P#{$currentPerson->id})");
                    if (!$dryRun) {
                        $this->reassignChoicePivots($question->id, $choice->id, $canonical->id);
                        $choice->delete();
                    }
                }
                continue;
            }

            // 3. Attempt safe deterministic match from choice_text when unlinked, missing, or mismatched.
            $matchedPerson = $trimmedText !== ''
                ? $this->findUniqueMatchingPerson($trimmedText, $allowedGender, $excludeOutsideFamily, $nameIndexes, $ambiguous)
                : null;

            if ($matchedPerson) {
                $existingCanonical = $canonicalByPersonId[(int) $matchedPerson->id] ?? null;
                if ($existingCanonical && $existingCanonical->id !== $choice->id) {
                    $summary['merged_duplicates']++;
                    $remappedChoiceIds[$choice->id] = $existingCanonical->id;
                    $removedChoiceIds[$choice->id] = true;
                    $this->line("  [MERGE-ORPHAN] Choice#{$choice->id} ('{$trimmedText}') -> Choice#{$existingCanonical->id} (P#{$matchedPerson->id})");
                    if (!$dryRun) {
                        $this->reassignChoicePivots($question->id, $choice->id, $existingCanonical->id);
                        $choice->delete();
                    }
                } else {
                    $summary['relinked_choices']++;
                    $canonicalByPersonId[(int) $matchedPerson->id] = $choice;
                    $this->info("  [RELINK] Choice#{$choice->id} ('{$trimmedText}') => P#{$matchedPerson->id} ({$matchedPerson->full_name}, gender={$matchedPerson->gender})");
                    if (!$dryRun) {
                        $choice->person_id = $matchedPerson->id;
                        $choice->save();
                    }
                }
                continue;
            }

            if ($ambiguous ?? false) {
                $summary['ambiguous_choices']++;
                $this->warn("  [AMBIGUOUS] Choice#{$choice->id} ('{$trimmedText}') matched multiple persons; left untouched.");
            }

            // 4. Remove or unlink corrupted gender-mismatched / missing person links when no female match exists.
            if ($hasGenderMismatch || $hasMissingPerson) {
                $reason = $hasGenderMismatch
                    ? "gender mismatch P#{$currentPerson->id}({$currentPerson->gender}) vs question({$allowedGender})"
                    : "missing P#{$choice->person_id}";

                if ($hasGenderMismatch && $voteCount === 0 && ($rules['nomination_type'] ?? '') === 'open_tree') {
                    $summary['removed_mismatched_choices']++;
                    $removedChoiceIds[$choice->id] = true;
                    $this->warn("  [DELETE-MISMATCH] Choice#{$choice->id} ({$reason}, votes=0)");
                    if (!$dryRun) {
                        $choice->delete();
                    }
                } elseif ($hasMissingPerson || $hasGenderMismatch) {
                    $summary['removed_mismatched_choices']++;
                    $removedChoiceIds[$choice->id] = $hasGenderMismatch;
                    $this->warn("  [UNLINK-INVALID] Choice#{$choice->id} ({$reason}, votes={$voteCount})");
                    if (!$dryRun) {
                        $choice->person_id = null;
                        $choice->save();
                    }
                }
            }
        }

        // Reload valid choices map for answer pivot verification / repair.
        $freshChoices = $question->choices()->with('person')->get()->keyBy('id');
        $this->repairQuestionAnswers($question, $freshChoices, $remappedChoiceIds, $removedChoiceIds, $dryRun, $summary);
    }

    /**
     * Find a unique Person matching Arabic text when multi-word / unambiguous.
     *
     * @param  array<string, array<string, array<int, Person>>>  $nameIndexes
     */
    private function findUniqueMatchingPerson(
        string $rawText,
        string $allowedGender,
        bool $excludeOutsideFamily,
        array $nameIndexes,
        ?bool &$isAmbiguous = false
    ): ?Person {
        $isAmbiguous = false;
        $normText = $this->normalizeArabicText($rawText);
        if ($normText === '') {
            return null;
        }

        $wordCount = count(preg_split('/\s+/u', $normText) ?: []);
        $pools = [$nameIndexes['full'][$normText] ?? []];
        if ($wordCount >= 2) {
            $pools[] = $nameIndexes['name'][$normText] ?? [];
            $pools[] = $nameIndexes['rel'][$normText] ?? [];
        }

        /** @var array<int, Person> $candidates */
        $candidates = [];
        foreach ($pools as $pool) {
            foreach ($pool as $person) {
                if (in_array($allowedGender, ['male', 'female'], true) && $person->gender !== $allowedGender) {
                    continue;
                }
                if ($excludeOutsideFamily && $person->isFromOutsideTheFamily()) {
                    continue;
                }
                $candidates[$person->id] = $person;
            }
        }

        if (count($candidates) === 1) {
            return reset($candidates);
        }

        if (count($candidates) > 1) {
            $isAmbiguous = true;
        }

        return null;
    }

    /**
     * Merge pivot rows from a duplicate choice ID onto the canonical choice ID without creating duplicates.
     */
    private function reassignChoicePivots(int $questionId, int $oldChoiceId, int $newChoiceId): void
    {
        $pivots = QuizAnswerChoice::query()
            ->join('quiz_answers', 'quiz_answers.id', '=', 'quiz_answer_choices.quiz_answer_id')
            ->where('quiz_answers.quiz_question_id', $questionId)
            ->where('quiz_answer_choices.quiz_question_choice_id', $oldChoiceId)
            ->select('quiz_answer_choices.*')
            ->get();

        foreach ($pivots as $pivot) {
            $alreadyExists = QuizAnswerChoice::query()
                ->where('quiz_answer_id', $pivot->quiz_answer_id)
                ->where('quiz_question_choice_id', $newChoiceId)
                ->exists();

            if ($alreadyExists) {
                $pivot->delete();
            } else {
                $pivot->quiz_question_choice_id = $newChoiceId;
                $pivot->save();
            }
        }
    }

    /**
     * Restore missing quiz_answer_choices pivots and prune corrupted/mismatched links.
     *
     * @param  Collection<int, QuizQuestionChoice>  $freshChoices
     * @param  array<int, int>  $remappedChoiceIds
     * @param  array<int, bool>  $removedChoiceIds
     * @param  array<string, int>  $summary
     */
    private function repairQuestionAnswers(
        QuizQuestion $question,
        Collection $freshChoices,
        array $remappedChoiceIds,
        array $removedChoiceIds,
        bool $dryRun,
        array &$summary
    ): void {
        $answers = QuizAnswer::with('selectedChoices')->where('quiz_question_id', $question->id)->get();

        foreach ($answers as $ans) {
            $validPivotChoiceIds = [];
            $pivotsModified = false;

            foreach ($ans->selectedChoices as $sc) {
                $cid = (int) $sc->quiz_question_choice_id;
                $targetCid = $remappedChoiceIds[$cid] ?? $cid;
                $choiceObj = $freshChoices->get($targetCid);

                $isGenderMismatched = $choiceObj
                    && $choiceObj->person
                    && !$question->isPersonGenderCompatibleWithQuestion($choiceObj->person);

                if (!$choiceObj || isset($removedChoiceIds[$targetCid]) || $isGenderMismatched) {
                    $summary['cleaned_invalid_pivots']++;
                    $pivotsModified = true;
                    $this->warn("  [CLEAN-PIVOT] Ans#{$ans->id} removed invalid/mismatched choice#{$cid}");
                    if (!$dryRun) {
                        $sc->delete();
                    }
                    continue;
                }

                $validPivotChoiceIds[$targetCid] = true;
            }

            // Restore any valid choice IDs listed in quiz_answers.answer whose pivot row was lost.
            $rawIds = $this->parseRawAnswerIds($ans->answer);
            $repairedAnswerIds = array_keys($validPivotChoiceIds);

            foreach ($rawIds as $rawId) {
                $mappedId = $remappedChoiceIds[$rawId] ?? $rawId;
                if (isset($validPivotChoiceIds[$mappedId]) || isset($removedChoiceIds[$mappedId])) {
                    continue;
                }

                $choiceObj = $freshChoices->get($mappedId);
                if (!$choiceObj) {
                    continue;
                }

                if ($choiceObj->person && !$question->isPersonGenderCompatibleWithQuestion($choiceObj->person)) {
                    continue;
                }

                $summary['restored_answer_pivots']++;
                $validPivotChoiceIds[$mappedId] = true;
                $repairedAnswerIds[] = $mappedId;
                $pivotsModified = true;
                $this->info("  [RESTORE-PIVOT] Ans#{$ans->id} restored choice#{$mappedId}");

                if (!$dryRun) {
                    QuizAnswerChoice::firstOrCreate([
                        'quiz_answer_id' => $ans->id,
                        'quiz_question_choice_id' => $mappedId,
                    ]);
                }
            }

            $normalizedRawIds = array_values(array_unique(array_map('intval', $rawIds)));
            $uniqueIds = array_values(array_unique(array_map('intval', $repairedAnswerIds)));

            if (($pivotsModified || $normalizedRawIds !== $uniqueIds) && !empty($uniqueIds) && !$dryRun) {
                $ans->answer = count($uniqueIds) > 1
                    ? json_encode($uniqueIds)
                    : (string) $uniqueIds[0];
                $ans->save();
            } elseif ($pivotsModified && empty($uniqueIds) && !$dryRun) {
                $ans->answer = '[]';
                $ans->save();
            }
        }
    }

    /**
     * Parse raw scalar or JSON answer payload into positive integers.
     *
     * @return array<int, int>
     */
    private function parseRawAnswerIds(mixed $rawAnswer): array
    {
        if (is_array($rawAnswer)) {
            return array_values(array_filter(array_map('intval', $rawAnswer), fn (int $v): bool => $v > 0));
        }

        if (is_numeric($rawAnswer)) {
            $id = (int) $rawAnswer;
            return $id > 0 ? [$id] : [];
        }

        if (is_string($rawAnswer) && trim($rawAnswer) !== '') {
            $decoded = json_decode($rawAnswer, true);
            if (is_array($decoded)) {
                return array_values(array_filter(array_map('intval', $decoded), fn (int $v): bool => $v > 0));
            }
        }

        return [];
    }

    /**
     * Normalize Arabic text for tolerant exact-name matching.
     */
    private function normalizeArabicText(?string $text): string
    {
        $text = trim((string) $text);
        if ($text === '') {
            return '';
        }

        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $text) ?? $text;
        $text = str_replace(['أ', 'إ', 'آ'], 'ا', $text);
        $text = str_replace('ى', 'ي', $text);
        $text = str_replace('ة', 'ه', $text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return mb_strtolower($text);
    }

    /**
     * Clear vote result and participant answer caches for repaired questions.
     *
     * @param  Collection<int, QuizQuestion>  $questions
     */
    private function clearRelatedCaches(Collection $questions): void
    {
        Cache::forget('api_active_quiz_competition');

        $questionIds = $questions->pluck('id')->all();
        foreach ($questionIds as $qid) {
            Cache::forget("vote_results_{$qid}");
        }

        $answers = QuizAnswer::with('user')
            ->whereIn('quiz_question_id', $questionIds)
            ->get(['person_id', 'user_id']);

        foreach ($answers as $ans) {
            if ($ans->person_id) {
                Cache::forget('user_answered_data_p_' . $ans->person_id);
            }
            if ($ans->user_id) {
                Cache::forget('user_answered_data_u_' . $ans->user_id);
            }
            if ($ans->user?->phone) {
                Cache::forget('user_answered_data_ph_' . $ans->user->phone);
            }
        }
    }
}

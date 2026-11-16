<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\QuizAnswer;
use App\Models\QuizCompetition;
use App\Models\QuizCompetitionWalkingScore;
use App\Models\QuizQuestion;
use App\Models\QuizQuestionChoice;
use App\Models\QuizSurveyItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QuizCompetitionAdversarialTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->mock(\App\Services\AI\EmbeddingsService::class, function ($mock) {
            $mock->shouldReceive('createForPerson')->andReturn(null);
            $mock->shouldReceive('deleteForPerson')->andReturn(null);
        });
        config(['site.password_protection_enabled' => false]);
        $this->admin = User::factory()->create();
    }

    /**
     * 1. Test eager loading with legacy alias 'linkedWalkingCompetition' on Eloquent queries.
     */
    public function test_eager_loading_legacy_alias_linked_walking_competition(): void
    {
        $competition = QuizCompetition::create(['title' => 'Comp Eager Load']);
        $walkingQ = QuizQuestion::create([
            'quiz_competition_id' => $competition->id,
            'question_text' => 'Walking Q',
            'answer_type' => 'walking',
        ]);
        $predQ = QuizQuestion::create([
            'quiz_competition_id' => $competition->id,
            'question_text' => 'Pred Q',
            'answer_type' => 'walking_prediction',
            'linked_walking_question_id' => $walkingQ->id,
        ]);

        // Eager load with canonical relation
        $loadedCanonical = QuizQuestion::with('linkedWalkingQuestion')->find($predQ->id);
        $this->assertTrue($loadedCanonical->relationLoaded('linkedWalkingQuestion'));
        $this->assertEquals($walkingQ->id, $loadedCanonical->linkedWalkingQuestion->id);

        // Eager load with compatibility alias
        $loadedAlias = QuizQuestion::with('linkedWalkingCompetition')->find($predQ->id);
        $this->assertNotNull($loadedAlias->linkedWalkingCompetition);
        $this->assertEquals($walkingQ->id, $loadedAlias->linkedWalkingCompetition->id);

        // Nested eager load through QuizCompetition
        $compLoaded = QuizCompetition::with(['questions.linkedWalkingQuestion', 'questions.linkedWalkingCompetition'])->find($competition->id);
        $this->assertCount(2, $compLoaded->questions);
        $p = $compLoaded->questions->firstWhere('id', $predQ->id);
        $this->assertEquals($walkingQ->id, $p->linkedWalkingQuestion?->id);
        $this->assertEquals($walkingQ->id, $p->linkedWalkingCompetition?->id);
    }

    /**
     * 2. Test question_id belonging to a DIFFERENT competition.
     * System should NOT leak the other competition's question or crash;
     * it should fall back to the requested competition's questions.
     */
    public function test_question_id_belonging_to_another_competition_falls_back_safely(): void
    {
        $compA = QuizCompetition::create(['title' => 'Competition A']);
        $qA = QuizQuestion::create([
            'quiz_competition_id' => $compA->id,
            'question_text' => 'Question of Comp A',
            'answer_type' => 'multiple_choice',
            'display_order' => 1,
        ]);

        $compB = QuizCompetition::create(['title' => 'Competition B']);
        $qB = QuizQuestion::create([
            'quiz_competition_id' => $compB->id,
            'question_text' => 'Question of Comp B (Secret)',
            'answer_type' => 'multiple_choice',
            'display_order' => 1,
        ]);

        // Request Comp A with qB's ID
        $url = route('dashboard.quiz-competitions.responses', [
            'quizCompetition' => $compA,
            'question_id' => $qB->id,
        ]);

        $response = $this->actingAs($this->admin)->get($url);

        $response->assertStatus(200);
        $response->assertViewIs('dashboard.quiz-competitions.responses');
        // Active question in view should be qA, NOT qB
        $response->assertSee('Question of Comp A');
        $response->assertDontSee('Question of Comp B (Secret)');
    }

    /**
     * 3. Test question_id extreme and malformed inputs:
     * - non-existent ID
     * - negative number
     * - string "invalid"
     * - zero
     * - empty string
     */
    public function test_question_id_extreme_and_malformed_inputs(): void
    {
        $comp = QuizCompetition::create(['title' => 'Comp Boundary Test']);
        $q = QuizQuestion::create([
            'quiz_competition_id' => $comp->id,
            'question_text' => 'Standard Question',
            'answer_type' => 'multiple_choice',
        ]);

        $malformedInputs = [
            '99999999',
            '-50',
            'not_an_id',
            '0',
            '',
            '12.34',
            'null',
            '<script>alert(1)</script>',
        ];

        foreach ($malformedInputs as $input) {
            $url = route('dashboard.quiz-competitions.responses', [
                'quizCompetition' => $comp,
                'question_id' => $input,
            ]);

            $response = $this->actingAs($this->admin)->get($url);
            $response->assertStatus(200);
            $response->assertSee('Standard Question');
        }
    }

    /**
     * 4. Test rendering across ALL distinct question types:
     * - multiple_choice
     * - true_false
     * - ordering
     * - text
     * - vote
     * - survey
     * - walking
     * - walking_prediction
     */
    public function test_all_question_types_render_cleanly_on_responses_page(): void
    {
        $comp = QuizCompetition::create(['title' => 'All Question Types Comp']);

        $walkingQ = QuizQuestion::create([
            'quiz_competition_id' => $comp->id,
            'question_text' => 'Walking Type Question',
            'answer_type' => 'walking',
            'display_order' => 1,
        ]);

        $voteQ = QuizQuestion::create([
            'quiz_competition_id' => $comp->id,
            'question_text' => 'Vote Type Question',
            'answer_type' => 'vote',
            'vote_max_selections' => 2,
            'display_order' => 2,
        ]);
        QuizQuestionChoice::create([
            'quiz_question_id' => $voteQ->id,
            'choice_text' => 'Candidate Alpha',
        ]);
        QuizQuestionChoice::create([
            'quiz_question_id' => $voteQ->id,
            'choice_text' => 'Candidate Beta',
        ]);

        $surveyQ = QuizQuestion::create([
            'quiz_competition_id' => $comp->id,
            'question_text' => 'Survey Type Question',
            'answer_type' => 'survey',
            'display_order' => 3,
        ]);
        QuizSurveyItem::create([
            'quiz_question_id' => $surveyQ->id,
            'block_type' => 'item',
            'response_kind' => 'rating',
            'rating_max' => 5,
            'body_text' => 'Rate our service',
        ]);
        QuizSurveyItem::create([
            'quiz_question_id' => $surveyQ->id,
            'block_type' => 'item',
            'response_kind' => 'number',
            'body_text' => 'Enter steps',
        ]);
        QuizSurveyItem::create([
            'quiz_question_id' => $surveyQ->id,
            'block_type' => 'item',
            'response_kind' => 'text',
            'body_text' => 'Feedback',
        ]);

        $mcQ = QuizQuestion::create([
            'quiz_competition_id' => $comp->id,
            'question_text' => 'Multiple Choice Question',
            'answer_type' => 'multiple_choice',
            'display_order' => 4,
        ]);

        $tfQ = QuizQuestion::create([
            'quiz_competition_id' => $comp->id,
            'question_text' => 'True False Question',
            'answer_type' => 'true_false',
            'display_order' => 5,
        ]);

        $orderQ = QuizQuestion::create([
            'quiz_competition_id' => $comp->id,
            'question_text' => 'Ordering Question',
            'answer_type' => 'ordering',
            'display_order' => 6,
        ]);

        $textQ = QuizQuestion::create([
            'quiz_competition_id' => $comp->id,
            'question_text' => 'Text Question',
            'answer_type' => 'text',
            'display_order' => 7,
        ]);

        $predQ = QuizQuestion::create([
            'quiz_competition_id' => $comp->id,
            'question_text' => 'Prediction Question',
            'answer_type' => 'walking_prediction',
            'linked_walking_question_id' => $walkingQ->id,
            'source_vote_question_id' => $voteQ->id,
            'display_order' => 8,
        ]);

        $allQuestions = [$walkingQ, $voteQ, $surveyQ, $mcQ, $tfQ, $orderQ, $textQ, $predQ];

        foreach ($allQuestions as $question) {
            $url = route('dashboard.quiz-competitions.responses', [
                'quizCompetition' => $comp,
                'question_id' => $question->id,
            ]);

            $response = $this->actingAs($this->admin)->get($url);
            $response->assertStatus(200);
            $response->assertSee($question->question_text);
        }
    }

    /**
     * 5. Test adversarial answer payloads for Vote:
     * - empty string
     * - malformed JSON
     * - empty JSON array
     * - unknown choice IDs not in choices or persons
     * - single integer
     * - JSON object
     */
    public function test_adversarial_vote_answers(): void
    {
        $comp = QuizCompetition::create(['title' => 'Vote Adversarial Comp']);
        $voteQ = QuizQuestion::create([
            'quiz_competition_id' => $comp->id,
            'question_text' => 'Vote Question Adversarial',
            'answer_type' => 'vote',
        ]);
        $c1 = QuizQuestionChoice::create([
            'quiz_question_id' => $voteQ->id,
            'choice_text' => 'Option One',
        ]);

        $answersPayloads = [
            '',
            'null',
            '{invalid json}',
            '[]',
            json_encode([$c1->id]),
            json_encode([999999, 888888]),
            (string) $c1->id,
            '999999',
            json_encode(['key' => 'val']),
        ];

        foreach ($answersPayloads as $index => $payload) {
            $user = User::factory()->create();
            QuizAnswer::create([
                'quiz_question_id' => $voteQ->id,
                'user_id' => $user->id,
                'answer' => $payload,
                'answer_type' => 'vote',
            ]);
        }

        $url = route('dashboard.quiz-competitions.responses', [
            'quizCompetition' => $comp,
            'question_id' => $voteQ->id,
        ]);

        $response = $this->actingAs($this->admin)->get($url);
        $response->assertStatus(200);
        $response->assertSee('Option One');
    }

    /**
     * 6. Test adversarial answer payloads for Survey:
     * - Rating: out of bounds (0, -1, 100), non-numeric
     * - Number: float, negative, non-numeric
     * - Text: empty, long text
     * - Missing survey item keys in json
     */
    public function test_adversarial_survey_answers(): void
    {
        $comp = QuizCompetition::create(['title' => 'Survey Adversarial Comp']);
        $surveyQ = QuizQuestion::create([
            'quiz_competition_id' => $comp->id,
            'question_text' => 'Survey Question Adversarial',
            'answer_type' => 'survey',
        ]);
        $itemRating = QuizSurveyItem::create([
            'quiz_question_id' => $surveyQ->id,
            'block_type' => 'item',
            'response_kind' => 'rating',
            'rating_max' => 5,
            'body_text' => 'Service Rating',
        ]);
        $itemNumber = QuizSurveyItem::create([
            'quiz_question_id' => $surveyQ->id,
            'block_type' => 'item',
            'response_kind' => 'number',
            'body_text' => 'Steps Count',
        ]);
        $itemText = QuizSurveyItem::create([
            'quiz_question_id' => $surveyQ->id,
            'block_type' => 'item',
            'response_kind' => 'text',
            'body_text' => 'Notes',
        ]);

        $surveyPayloads = [
            '',
            'corrupt json string',
            '[]',
            json_encode([
                $itemRating->id => ['v' => '5'],
                $itemNumber->id => ['v' => '12000'],
                $itemText->id => ['v' => 'Great!'],
            ]),
            json_encode([
                $itemRating->id => ['v' => '0'], // below min
                $itemNumber->id => ['v' => 'not_a_number'],
                $itemText->id => ['v' => str_repeat('A', 500)],
            ]),
            json_encode([
                $itemRating->id => ['v' => '10'], // above rating_max
                $itemNumber->id => ['v' => '-500.75'],
                $itemText->id => ['v' => ''],
            ]),
            json_encode([
                999999 => ['v' => 'irrelevant'],
            ]),
        ];

        foreach ($surveyPayloads as $index => $payload) {
            $user = User::factory()->create();
            QuizAnswer::create([
                'quiz_question_id' => $surveyQ->id,
                'user_id' => $user->id,
                'answer' => $payload,
                'answer_type' => 'survey',
            ]);
        }

        $url = route('dashboard.quiz-competitions.responses', [
            'quizCompetition' => $comp,
            'question_id' => $surveyQ->id,
        ]);

        $response = $this->actingAs($this->admin)->get($url);
        $response->assertStatus(200);
        $response->assertSee('Service Rating');
        $response->assertSee('Steps Count');
    }

    /**
     * 7. Test null safety matrix on responses table:
     * - Answer with user_id=null, person_id=null, created_at=null
     * - Answer with person having null mother and null contact accounts
     * - Answer with user having is_from_ancestry=true but mother_name=null
     * - Answer with user having is_from_ancestry=false
     * - Answer with user having null phone
     */
    public function test_null_safety_matrix_on_responses_view(): void
    {
        $comp = QuizCompetition::create(['title' => 'Null Safety Matrix Comp']);
        $question = QuizQuestion::create([
            'quiz_competition_id' => $comp->id,
            'question_text' => 'Null Safety Question',
            'answer_type' => 'multiple_choice',
        ]);

        // Case 1: user=null, person=null, created_at=null
        QuizAnswer::create([
            'quiz_question_id' => $question->id,
            'user_id' => null,
            'person_id' => null,
            'answer' => 'Test Answer 1',
            'answer_type' => 'multiple_choice',
            'is_correct' => true,
            'created_at' => null,
        ]);

        // Case 2: person without mother, without contacts
        $person = Person::factory()->create([
            'first_name' => 'Fahad',
            'last_name' => 'Al-Sabah',
            'gender' => 'male',
            'mother_id' => null,
        ]);
        QuizAnswer::create([
            'quiz_question_id' => $question->id,
            'user_id' => null,
            'person_id' => $person->id,
            'answer' => 'Test Answer 2',
            'answer_type' => 'multiple_choice',
            'is_correct' => false,
        ]);

        // Case 3: user with is_from_ancestry=true, mother_name=null, phone=null
        $user3 = User::factory()->create([
            'name' => 'Sara Ancestry',
            'phone' => null,
            'is_from_ancestry' => true,
            'mother_name' => null,
        ]);
        QuizAnswer::create([
            'quiz_question_id' => $question->id,
            'user_id' => $user3->id,
            'person_id' => null,
            'answer' => 'Test Answer 3',
            'answer_type' => 'multiple_choice',
            'is_correct' => true,
        ]);

        // Case 4: user with is_from_ancestry=false, mother_name=null
        $user4 = User::factory()->create([
            'name' => 'Guest User 4',
            'phone' => '0500000000',
            'is_from_ancestry' => false,
            'mother_name' => null,
        ]);
        QuizAnswer::create([
            'quiz_question_id' => $question->id,
            'user_id' => $user4->id,
            'person_id' => null,
            'answer' => 'Test Answer 4',
            'answer_type' => 'multiple_choice',
            'is_correct' => false,
        ]);

        $url = route('dashboard.quiz-competitions.responses', [
            'quizCompetition' => $comp,
            'question_id' => $question->id,
        ]);

        $response = $this->actingAs($this->admin)->get($url);
        $response->assertStatus(200);
        $response->assertSee('مستخدم غير مسجل');
        $response->assertSee('Fahad');
        $response->assertSee('Sara Ancestry');
        $response->assertSee('Guest User 4');
    }

    /**
     * 8. Test filters and pagination:
     * - q parameter (search)
     * - correct parameter (1 / 0)
     * - from / to date filters
     * - page parameter
     */
    public function test_filters_and_pagination_execution(): void
    {
        $comp = QuizCompetition::create(['title' => 'Filtering Comp']);
        $question = QuizQuestion::create([
            'quiz_competition_id' => $comp->id,
            'question_text' => 'Filter Target Question',
            'answer_type' => 'multiple_choice',
        ]);

        $u1 = User::factory()->create(['name' => 'Target Search Match']);
        QuizAnswer::create([
            'quiz_question_id' => $question->id,
            'user_id' => $u1->id,
            'answer' => 'Correct Ans',
            'answer_type' => 'multiple_choice',
            'is_correct' => true,
            'created_at' => now()->subDays(2),
        ]);

        // Create 35 answers to test pagination (page size is 30)
        for ($i = 0; $i < 35; $i++) {
            $user = User::factory()->create();
            QuizAnswer::create([
                'quiz_question_id' => $question->id,
                'user_id' => $user->id,
                'answer' => "Ans {$i}",
                'answer_type' => 'multiple_choice',
                'is_correct' => ($i % 2 === 0),
                'created_at' => now()->subDay(),
            ]);
        }

        // Test search query filter
        $resQ = $this->actingAs($this->admin)->get(route('dashboard.quiz-competitions.responses', [
            'quizCompetition' => $comp,
            'question_id' => $question->id,
            'q' => 'Target Search',
        ]));
        $resQ->assertStatus(200);
        $resQ->assertSee('Target Search Match');

        // Test correct filter
        $resCorrect = $this->actingAs($this->admin)->get(route('dashboard.quiz-competitions.responses', [
            'quizCompetition' => $comp,
            'question_id' => $question->id,
            'correct' => '1',
        ]));
        $resCorrect->assertStatus(200);

        // Test date range filter
        $resDate = $this->actingAs($this->admin)->get(route('dashboard.quiz-competitions.responses', [
            'quizCompetition' => $comp,
            'question_id' => $question->id,
            'from' => now()->subDays(3)->toDateString(),
            'to' => now()->toDateString(),
        ]));
        $resDate->assertStatus(200);

        // Test pagination page 2
        $resPage2 = $this->actingAs($this->admin)->get(route('dashboard.quiz-competitions.responses', [
            'quizCompetition' => $comp,
            'question_id' => $question->id,
            'page' => 2,
        ]));
        $resPage2->assertStatus(200);
    }

    /**
     * 9. Test Walking Prediction with live WalkingScores and candidate rankings.
     */
    public function test_walking_prediction_with_live_walking_scores(): void
    {
        $comp = QuizCompetition::create([
            'title' => 'Walking Prediction Live Comp',
            'is_active' => true,
        ]);

        $walkingQ = QuizQuestion::create([
            'quiz_competition_id' => $comp->id,
            'question_text' => 'Walking Daily Challenge',
            'answer_type' => 'walking',
        ]);

        $voteQ = QuizQuestion::create([
            'quiz_competition_id' => $comp->id,
            'question_text' => 'Vote for Champions',
            'answer_type' => 'vote',
            'linked_walking_question_id' => $walkingQ->id,
            'vote_max_selections' => 2,
        ]);

        $p1 = Person::factory()->create(['first_name' => 'WalkerAlpha', 'gender' => 'male']);
        $p2 = Person::factory()->create(['first_name' => 'WalkerBeta', 'gender' => 'male']);

        $c1 = QuizQuestionChoice::create([
            'quiz_question_id' => $voteQ->id,
            'choice_text' => 'WalkerAlpha',
            'person_id' => $p1->id,
        ]);
        $c2 = QuizQuestionChoice::create([
            'quiz_question_id' => $voteQ->id,
            'choice_text' => 'WalkerBeta',
            'person_id' => $p2->id,
        ]);

        // Add walking scores for today
        QuizCompetitionWalkingScore::create([
            'quiz_question_id' => $walkingQ->id,
            'person_id' => $p1->id,
            'date' => now()->toDateString(),
            'steps' => 10000,
            'points' => 100,
            'daily_rank' => 1,
        ]);
        QuizCompetitionWalkingScore::create([
            'quiz_question_id' => $walkingQ->id,
            'person_id' => $p2->id,
            'date' => now()->toDateString(),
            'steps' => 8000,
            'points' => 80,
            'daily_rank' => 2,
        ]);

        $predQ = QuizQuestion::create([
            'quiz_competition_id' => $comp->id,
            'question_text' => 'Walking Prediction Final',
            'answer_type' => 'walking_prediction',
            'linked_walking_question_id' => $walkingQ->id,
            'source_vote_question_id' => $voteQ->id,
        ]);

        // Voter submits prediction matching ranking [Alpha, Beta]
        QuizAnswer::create([
            'quiz_question_id' => $voteQ->id,
            'user_id' => $this->admin->id,
            'answer' => json_encode([$c1->id, $c2->id]),
            'answer_type' => 'vote',
        ]);

        $url = route('dashboard.quiz-competitions.responses', [
            'quizCompetition' => $comp,
            'question_id' => $predQ->id,
        ]);

        $response = $this->actingAs($this->admin)->get($url);

        $response->assertStatus(200);
        $response->assertSee('Walking Prediction Final');
        $response->assertSee('جدول النقاط المعتمد لكل مركز متوقع');
    }
}

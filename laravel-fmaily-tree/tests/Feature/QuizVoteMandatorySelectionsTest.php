<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\QuizCompetitionApiController;
use App\Models\Person;
use App\Models\QuizCompetition;
use App\Models\QuizQuestion;
use App\Models\QuizQuestionChoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class QuizVoteMandatorySelectionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Queue::fake();
        $this->mock(\App\Services\AI\EmbeddingsService::class, function ($mock) {
            $mock->shouldReceive('createForPerson')->andReturn(null);
            $mock->shouldReceive('deleteForPerson')->andReturn(null);
        });
        config(['site.password_protection_enabled' => false]);
    }

    /**
     * Test open tree voting enforces exact required candidates count and reports remaining count.
     */
    public function test_open_tree_voting_enforces_exact_required_candidates_count()
    {
        $comp = QuizCompetition::create([
            'title' => 'مسابقة تصويت العائلة',
            'is_active' => true,
            'start_at' => now()->subDay(),
            'end_at' => now()->addDay(),
        ]);

        $question = QuizQuestion::create([
            'quiz_competition_id' => $comp->id,
            'question_text' => 'صوّت لخمسة مرشحين لمجلس العائلة',
            'answer_type' => 'vote',
            'vote_max_selections' => 5,
            'candidate_rules' => [
                'nomination_type' => 'open_tree',
                'allowed_gender' => 'all',
                'exclude_deceased' => true,
            ],
            'voter_eligibility' => [
                'require_identity' => false,
            ],
        ]);

        $user = User::factory()->create([
            'phone' => '0512345678',
        ]);

        // Create 6 persons in tree
        $persons = [];
        for ($i = 1; $i <= 6; $i++) {
            $persons[] = Person::factory()->create([
                'first_name' => 'مرشح' . $i,
                'last_name' => 'السريع',
                'gender' => 'male',
                'death_date' => null,
                'from_outside_the_family' => false,
            ]);
        }

        $controller = app(QuizCompetitionApiController::class);

        // Case 1: Submitting only 2 candidates when 5 are required -> Fails with "فاضل 3 مرشحين"
        $reqIncomplete = Request::create(
            "/api/v1/quiz-competitions/{$comp->id}/questions/{$question->id}/answer",
            'POST',
            [
                'name' => 'المصوت الأول',
                'phone' => '0512345678',
                'person_ids' => [$persons[0]->id, $persons[1]->id],
            ]
        );
        $reqIncomplete->setUserResolver(fn() => $user);

        $respIncomplete = $controller->storeAnswer($reqIncomplete, $comp, $question);
        $this->assertEquals(422, $respIncomplete->getStatusCode());
        $dataIncomplete = json_decode($respIncomplete->getContent(), true);
        $this->assertStringContainsString('فاضل 3 مرشحين', $dataIncomplete['message'] ?? json_encode($dataIncomplete['errors']));

        // Case 2: Submitting 6 candidates when 5 are required -> Fails
        $reqOverLimit = Request::create(
            "/api/v1/quiz-competitions/{$comp->id}/questions/{$question->id}/answer",
            'POST',
            [
                'name' => 'المصوت الأول',
                'phone' => '0512345678',
                'person_ids' => array_map(fn($p) => $p->id, $persons),
            ]
        );
        $reqOverLimit->setUserResolver(fn() => $user);

        $respOverLimit = $controller->storeAnswer($reqOverLimit, $comp, $question);
        $this->assertEquals(422, $respOverLimit->getStatusCode());

        // Case 3: Submitting duplicate candidates -> Fails
        $reqDuplicates = Request::create(
            "/api/v1/quiz-competitions/{$comp->id}/questions/{$question->id}/answer",
            'POST',
            [
                'name' => 'المصوت الأول',
                'phone' => '0512345678',
                'person_ids' => [$persons[0]->id, $persons[0]->id, $persons[1]->id, $persons[2]->id, $persons[3]->id],
            ]
        );
        $reqDuplicates->setUserResolver(fn() => $user);

        $respDuplicates = $controller->storeAnswer($reqDuplicates, $comp, $question);
        $this->assertEquals(422, $respDuplicates->getStatusCode());

        // Case 4: Submitting exactly 5 unique candidates -> Succeeds!
        $reqSuccess = Request::create(
            "/api/v1/quiz-competitions/{$comp->id}/questions/{$question->id}/answer",
            'POST',
            [
                'name' => 'المصوت الأول',
                'phone' => '0512345678',
                'person_ids' => [
                    $persons[0]->id,
                    $persons[1]->id,
                    $persons[2]->id,
                    $persons[3]->id,
                    $persons[4]->id,
                ],
            ]
        );
        $reqSuccess->setUserResolver(fn() => $user);

        $respSuccess = $controller->storeAnswer($reqSuccess, $comp, $question);
        $this->assertEquals(200, $respSuccess->getStatusCode());
        $dataSuccess = json_decode($respSuccess->getContent(), true);
        $this->assertEquals('success', $dataSuccess['status']);
        $this->assertDatabaseHas('quiz_answers', [
            'quiz_question_id' => $question->id,
            'user_id' => $user->id,
        ]);
    }

    /**
     * Test predefined choices voting enforces exact required candidates count and reports remaining count.
     */
    public function test_predefined_choices_voting_enforces_exact_required_candidates_count()
    {
        $comp = QuizCompetition::create([
            'title' => 'مسابقة تصويت الخيارات المحددة',
            'is_active' => true,
            'start_at' => now()->subDay(),
            'end_at' => now()->addDay(),
        ]);

        $question = QuizQuestion::create([
            'quiz_competition_id' => $comp->id,
            'question_text' => 'صوّت لثلاثة مرشحين من القائمة أدناه',
            'answer_type' => 'vote',
            'vote_max_selections' => 3,
            'candidate_rules' => [
                'nomination_type' => 'predefined',
            ],
            'voter_eligibility' => [
                'require_identity' => false,
            ],
        ]);

        // Create 5 choices
        $choices = [];
        for ($i = 1; $i <= 5; $i++) {
            $choices[] = QuizQuestionChoice::create([
                'quiz_question_id' => $question->id,
                'choice_text' => 'الخيار رقم ' . $i,
                'is_correct' => false,
            ]);
        }

        $user = User::factory()->create([
            'phone' => '0598765432',
        ]);

        $controller = app(QuizCompetitionApiController::class);

        // Case 1: Submitting only 1 choice when 3 are required -> Fails with "فاضل 2 مرشحين"
        $reqIncomplete = Request::create(
            "/api/v1/quiz-competitions/{$comp->id}/questions/{$question->id}/answer",
            'POST',
            [
                'name' => 'المصوت الثاني',
                'phone' => '0598765432',
                'answer' => [$choices[0]->id],
            ]
        );
        $reqIncomplete->setUserResolver(fn() => $user);

        $respIncomplete = $controller->storeAnswer($reqIncomplete, $comp, $question);
        $this->assertEquals(422, $respIncomplete->getStatusCode());
        $dataIncomplete = json_decode($respIncomplete->getContent(), true);
        $this->assertStringContainsString('فاضل 2 مرشحين', $dataIncomplete['message'] ?? json_encode($dataIncomplete['errors']));

        // Case 2: Submitting 4 choices when 3 are required -> Fails
        $reqOverLimit = Request::create(
            "/api/v1/quiz-competitions/{$comp->id}/questions/{$question->id}/answer",
            'POST',
            [
                'name' => 'المصوت الثاني',
                'phone' => '0598765432',
                'answer' => [$choices[0]->id, $choices[1]->id, $choices[2]->id, $choices[3]->id],
            ]
        );
        $reqOverLimit->setUserResolver(fn() => $user);

        $respOverLimit = $controller->storeAnswer($reqOverLimit, $comp, $question);
        $this->assertEquals(422, $respOverLimit->getStatusCode());

        // Case 3: Submitting exactly 3 choices -> Succeeds!
        $reqSuccess = Request::create(
            "/api/v1/quiz-competitions/{$comp->id}/questions/{$question->id}/answer",
            'POST',
            [
                'name' => 'المصوت الثاني',
                'phone' => '0598765432',
                'answer' => [$choices[0]->id, $choices[1]->id, $choices[2]->id],
            ]
        );
        $reqSuccess->setUserResolver(fn() => $user);

        $respSuccess = $controller->storeAnswer($reqSuccess, $comp, $question);
        $this->assertEquals(200, $respSuccess->getStatusCode());
        $dataSuccess = json_decode($respSuccess->getContent(), true);
        $this->assertEquals('success', $dataSuccess['status']);
        $this->assertDatabaseHas('quiz_answers', [
            'quiz_question_id' => $question->id,
            'user_id' => $user->id,
        ]);
    }
}

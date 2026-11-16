<?php

namespace Tests\Feature;

use App\Models\QuizAnswer;
use App\Models\QuizCompetition;
use App\Models\QuizQuestion;
use App\Models\QuizQuestionChoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizCompetitionResponsesTest extends TestCase
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
     * Test that loading the quiz responses page with walking_prediction question returns HTTP 200 without throwing 500.
     */
    public function test_quiz_competition_responses_loads_without_500_error_for_walking_prediction(): void
    {
        $admin = User::factory()->create();

        $competition = QuizCompetition::create([
            'title' => 'مسابقة المشي وتوقع الفائزين',
            'is_active' => true,
            'start_at' => now()->subDay(),
            'end_at' => now()->addDays(5),
        ]);

        $walkingQuestion = QuizQuestion::create([
            'quiz_competition_id' => $competition->id,
            'question_text' => 'تحدي المشي اليومي',
            'answer_type' => 'walking',
        ]);

        $predictionQuestion = QuizQuestion::create([
            'quiz_competition_id' => $competition->id,
            'question_text' => 'توقع الفائز بتحدي المشي',
            'answer_type' => 'walking_prediction',
            'linked_walking_question_id' => $walkingQuestion->id,
        ]);

        $url = route('dashboard.quiz-competitions.responses', $competition) . '?question_id=' . $predictionQuestion->id;
        $response = $this->actingAs($admin)->get($url);

        $response->assertStatus(200);
        $response->assertViewIs('dashboard.quiz-competitions.responses');
        $response->assertSee('توقع الفائز بتحدي المشي');
    }

    /**
     * Test that responses view renders properly when competition has no questions.
     */
    public function test_quiz_competition_responses_loads_when_competition_has_no_questions(): void
    {
        $admin = User::factory()->create();

        $competition = QuizCompetition::create([
            'title' => 'مسابقة فارغة بدون أسئلة',
            'is_active' => true,
            'start_at' => now()->subDay(),
            'end_at' => now()->addDays(5),
        ]);

        $url = route('dashboard.quiz-competitions.responses', $competition);
        $response = $this->actingAs($admin)->get($url);

        $response->assertStatus(200);
        $response->assertViewIs('dashboard.quiz-competitions.responses');
    }

    /**
     * Test that responses view safely handles answers where user or person might be null without throwing null errors.
     */
    public function test_quiz_competition_responses_renders_safely_with_null_user_and_null_person(): void
    {
        $admin = User::factory()->create();

        $competition = QuizCompetition::create([
            'title' => 'مسابقة أسئلة عامة',
            'is_active' => true,
            'start_at' => now()->subDay(),
            'end_at' => now()->addDays(5),
        ]);

        $question = QuizQuestion::create([
            'quiz_competition_id' => $competition->id,
            'question_text' => 'ما هي عاصمة المملكة العربية السعودية؟',
            'answer_type' => 'multiple_choice',
        ]);

        $choice = QuizQuestionChoice::create([
            'quiz_question_id' => $question->id,
            'choice_text' => 'الرياض',
            'is_correct' => true,
        ]);

        // Create answer with null person_id and null user_id (e.g. legacy/guest or deleted user)
        QuizAnswer::create([
            'quiz_question_id' => $question->id,
            'user_id' => null,
            'person_id' => null,
            'answer' => 'الرياض',
            'answer_type' => 'multiple_choice',
            'is_correct' => true,
        ]);

        $url = route('dashboard.quiz-competitions.responses', $competition) . '?question_id=' . $question->id;
        $response = $this->actingAs($admin)->get($url);

        $response->assertStatus(200);
        $response->assertViewIs('dashboard.quiz-competitions.responses');
        $response->assertSee('مستخدم غير مسجل');
    }

    /**
     * Test that the backward-compatibility alias linkedWalkingCompetition returns BelongsTo relation.
     */
    public function test_quiz_question_has_backward_compatible_linked_walking_competition_alias(): void
    {
        $competition = QuizCompetition::create([
            'title' => 'مسابقة المشي',
            'is_active' => true,
        ]);

        $walkingQuestion = QuizQuestion::create([
            'quiz_competition_id' => $competition->id,
            'question_text' => 'سؤال المشي الأصلي',
            'answer_type' => 'walking',
        ]);

        $predictionQuestion = QuizQuestion::create([
            'quiz_competition_id' => $competition->id,
            'question_text' => 'سؤال التوقع المرتبط',
            'answer_type' => 'walking_prediction',
            'linked_walking_question_id' => $walkingQuestion->id,
        ]);

        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\BelongsTo::class,
            $predictionQuestion->linkedWalkingCompetition()
        );

        $this->assertEquals(
            $walkingQuestion->id,
            $predictionQuestion->linkedWalkingCompetition->id
        );
    }

    /**
     * Test that responses page filters by requested question_id and displays its details.
     */
    public function test_quiz_competition_responses_filters_by_specific_question_id_correctly(): void
    {
        $admin = User::factory()->create();

        $competition = QuizCompetition::create([
            'title' => 'المسابقة الكبرى السنوية',
            'is_active' => true,
            'start_at' => now()->subDay(),
            'end_at' => now()->addDays(5),
        ]);

        $q1 = QuizQuestion::create([
            'quiz_competition_id' => $competition->id,
            'question_text' => 'السؤال الأول: تاريخ التأسيس',
            'answer_type' => 'multiple_choice',
            'display_order' => 1,
        ]);

        $q2 = QuizQuestion::create([
            'quiz_competition_id' => $competition->id,
            'question_text' => 'السؤال الثاني: عدد الفروع',
            'answer_type' => 'multiple_choice',
            'display_order' => 2,
        ]);

        $url = route('dashboard.quiz-competitions.responses', [
            'quizCompetition' => $competition,
            'question_id' => $q2->id,
        ]);

        $response = $this->actingAs($admin)->get($url);

        $response->assertStatus(200);
        $response->assertViewIs('dashboard.quiz-competitions.responses');
        $response->assertSee('السؤال الثاني: عدد الفروع');
    }
}

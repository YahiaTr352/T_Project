<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\QuizAnswer;
use App\Models\QuizCompetition;
use App\Models\QuizQuestion;
use App\Models\QuizQuestionChoice;
use App\Models\QuizSurveyItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QuizCompetitionChallenger2Test extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected QuizCompetition $competition;

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
        $this->competition = QuizCompetition::create([
            'title' => 'مسابقة التحديات الشاملة',
            'is_active' => true,
            'start_at' => now()->subDays(2),
            'end_at' => now()->addDays(5),
        ]);
    }

    /**
     * Adversarial Test 1: Invalid question_id scenarios
     * - question_id belongs to another competition
     * - question_id is non-existent (e.g. 999999)
     * - question_id is negative or non-numeric
     */
    public function test_invalid_or_foreign_question_id_falls_back_gracefully_to_current_competition(): void
    {
        $q1 = QuizQuestion::create([
            'quiz_competition_id' => $this->competition->id,
            'question_text' => 'سؤال المسابقة الحالية المستهدف',
            'answer_type' => 'multiple_choice',
            'display_order' => 1,
        ]);

        $otherCompetition = QuizCompetition::create([
            'title' => 'مسابقة أخرى منفصلة تماما',
            'is_active' => true,
        ]);

        $foreignQuestion = QuizQuestion::create([
            'quiz_competition_id' => $otherCompetition->id,
            'question_text' => 'سؤال ينتمي لمسابقة ثانية سرية',
            'answer_type' => 'multiple_choice',
            'display_order' => 1,
        ]);

        // 1. Pass foreign question_id belonging to another competition
        $resForeign = $this->actingAs($this->admin)->get(
            route('dashboard.quiz-competitions.responses', $this->competition) . '?question_id=' . $foreignQuestion->id
        );
        $resForeign->assertStatus(200);
        $resForeign->assertSee('سؤال المسابقة الحالية المستهدف');
        $resForeign->assertDontSee('سؤال ينتمي لمسابقة ثانية سرية');

        // 2. Pass non-existent question_id (999999)
        $resNonExistent = $this->actingAs($this->admin)->get(
            route('dashboard.quiz-competitions.responses', $this->competition) . '?question_id=999999'
        );
        $resNonExistent->assertStatus(200);
        $resNonExistent->assertSee('سؤال المسابقة الحالية المستهدف');

        // 3. Pass negative or non-numeric question_id
        $resNegative = $this->actingAs($this->admin)->get(
            route('dashboard.quiz-competitions.responses', $this->competition) . '?question_id=-50'
        );
        $resNegative->assertStatus(200);
        $resNegative->assertSee('سؤال المسابقة الحالية المستهدف');

        $resString = $this->actingAs($this->admin)->get(
            route('dashboard.quiz-competitions.responses', $this->competition) . '?question_id=malicious_payload%27OR%271%27=%271'
        );
        $resString->assertStatus(200);
        $resString->assertSee('سؤال المسابقة الحالية المستهدف');
    }

    /**
     * Adversarial Test 2: Multi-type competition rendering
     * Test a competition containing multiple_choice, vote, survey, walking, and walking_prediction
     */
    public function test_mixed_question_types_all_render_without_error(): void
    {
        // 1. Multiple choice question
        $mcq = QuizQuestion::create([
            'quiz_competition_id' => $this->competition->id,
            'question_text' => 'سؤال اختيار من متعدد فريد',
            'answer_type' => 'multiple_choice',
            'display_order' => 1,
        ]);
        QuizQuestionChoice::create([
            'quiz_question_id' => $mcq->id,
            'choice_text' => 'الخيار الأول',
            'is_correct' => true,
        ]);
        QuizAnswer::create([
            'quiz_question_id' => $mcq->id,
            'user_id' => $this->admin->id,
            'answer' => 'الخيار الأول',
            'answer_type' => 'multiple_choice',
            'is_correct' => true,
        ]);

        // 2. Vote question
        $voteUser = User::factory()->create();
        $voteQ = QuizQuestion::create([
            'quiz_competition_id' => $this->competition->id,
            'question_text' => 'سؤال التصويت الفريد',
            'answer_type' => 'vote',
            'vote_max_selections' => 2,
            'display_order' => 2,
        ]);
        $c1 = QuizQuestionChoice::create(['quiz_question_id' => $voteQ->id, 'choice_text' => 'مرشح 1']);
        $c2 = QuizQuestionChoice::create(['quiz_question_id' => $voteQ->id, 'choice_text' => 'مرشح 2']);
        QuizAnswer::create([
            'quiz_question_id' => $voteQ->id,
            'user_id' => $voteUser->id,
            'answer' => json_encode([$c1->id, $c2->id]),
            'answer_type' => 'vote',
        ]);

        // 3. Survey question
        $surveyUser = User::factory()->create();
        $surveyQ = QuizQuestion::create([
            'quiz_competition_id' => $this->competition->id,
            'question_text' => 'استبيان تقييم الفعالية الفريد',
            'answer_type' => 'survey',
            'display_order' => 3,
        ]);
        $itemRating = QuizSurveyItem::create([
            'quiz_question_id' => $surveyQ->id,
            'block_type' => 'text',
            'body_text' => 'تقييم التنظيم العام',
            'response_kind' => 'rating',
            'rating_max' => 5,
        ]);
        $itemNumber = QuizSurveyItem::create([
            'quiz_question_id' => $surveyQ->id,
            'block_type' => 'text',
            'body_text' => 'عدد أفراد الأسرة المشاركين',
            'response_kind' => 'number',
        ]);
        QuizAnswer::create([
            'quiz_question_id' => $surveyQ->id,
            'user_id' => $surveyUser->id,
            'answer' => json_encode([
                $itemRating->id => ['k' => 'rating', 'v' => 5],
                $itemNumber->id => ['k' => 'number', 'v' => 4],
            ]),
            'answer_type' => 'survey',
        ]);

        // 4. Walking challenge question
        $walkingQ = QuizQuestion::create([
            'quiz_competition_id' => $this->competition->id,
            'question_text' => 'تحدي المشي الأسبوعي الفريد',
            'answer_type' => 'walking',
            'display_order' => 4,
        ]);

        // 5. Walking prediction question
        $predQ = QuizQuestion::create([
            'quiz_competition_id' => $this->competition->id,
            'question_text' => 'توقع الفائز بتحدي المشي الفريد',
            'answer_type' => 'walking_prediction',
            'linked_walking_question_id' => $walkingQ->id,
            'source_vote_question_id' => $voteQ->id,
            'display_order' => 5,
        ]);

        // Verify each question type loads cleanly with HTTP 200
        $questions = [$mcq, $voteQ, $surveyQ, $walkingQ, $predQ];
        foreach ($questions as $q) {
            $resp = $this->actingAs($this->admin)->get(
                route('dashboard.quiz-competitions.responses', $this->competition) . '?question_id=' . $q->id
            );
            $resp->assertStatus(200);
            $resp->assertSee($q->question_text);
        }
    }

    /**
     * Adversarial Test 3: Eager loading and alias backward compatibility
     */
    public function test_linked_walking_relations_eager_loading_and_backward_compatibility(): void
    {
        $walkingQ = QuizQuestion::create([
            'quiz_competition_id' => $this->competition->id,
            'question_text' => 'تحدي المشي الأصلي المستهدف',
            'answer_type' => 'walking',
        ]);

        $predQ = QuizQuestion::create([
            'quiz_competition_id' => $this->competition->id,
            'question_text' => 'سؤال توقع الفائزين المستهدف',
            'answer_type' => 'walking_prediction',
            'linked_walking_question_id' => $walkingQ->id,
        ]);

        // Verify eager loading 'linkedWalkingQuestion' works
        $compWithQuestion = QuizCompetition::with('questions.linkedWalkingQuestion')->find($this->competition->id);
        $loadedQ = $compWithQuestion->questions->firstWhere('id', $predQ->id);
        $this->assertTrue($loadedQ->relationLoaded('linkedWalkingQuestion'));
        $this->assertEquals($walkingQ->id, $loadedQ->linkedWalkingQuestion->id);

        // Verify eager loading 'linkedWalkingCompetition' alias works without throwing RelationNotFoundException
        $compWithCompetitionAlias = QuizCompetition::with('questions.linkedWalkingCompetition')->find($this->competition->id);
        $loadedAliasQ = $compWithCompetitionAlias->questions->firstWhere('id', $predQ->id);
        $this->assertTrue($loadedAliasQ->relationLoaded('linkedWalkingCompetition'));
        $this->assertEquals($walkingQ->id, $loadedAliasQ->linkedWalkingCompetition->id);
    }

    /**
     * Adversarial Test 4: Walking prediction question with null linked_walking_question_id
     */
    public function test_walking_prediction_with_null_linked_question(): void
    {
        $orphanPredQ = QuizQuestion::create([
            'quiz_competition_id' => $this->competition->id,
            'question_text' => 'توقع بدون تحدي مشي مرتبط مطلقاً',
            'answer_type' => 'walking_prediction',
            'linked_walking_question_id' => null,
        ]);

        $resp = $this->actingAs($this->admin)->get(
            route('dashboard.quiz-competitions.responses', $this->competition) . '?question_id=' . $orphanPredQ->id
        );

        $resp->assertStatus(200);
        $resp->assertSee('توقع بدون تحدي مشي مرتبط مطلقاً');
    }

    /**
     * Adversarial Test 5: Search and filter permutations
     */
    public function test_responses_search_and_filter_permutations(): void
    {
        $q = QuizQuestion::create([
            'quiz_competition_id' => $this->competition->id,
            'question_text' => 'سؤال الفلترة الدقيقة',
            'answer_type' => 'multiple_choice',
        ]);

        $user1 = User::factory()->create(['name' => 'محمد الأحمد', 'phone' => '0501112233']);
        $user2 = User::factory()->create(['name' => 'خالد المحمود', 'phone' => '0504445566']);

        QuizAnswer::create([
            'quiz_question_id' => $q->id,
            'user_id' => $user1->id,
            'answer' => 'الرياض',
            'answer_type' => 'multiple_choice',
            'is_correct' => true,
            'created_at' => now()->subDay(),
        ]);

        QuizAnswer::create([
            'quiz_question_id' => $q->id,
            'user_id' => $user2->id,
            'answer' => 'جدة',
            'answer_type' => 'multiple_choice',
            'is_correct' => false,
            'created_at' => now(),
        ]);

        $baseUrl = route('dashboard.quiz-competitions.responses', $this->competition) . '?question_id=' . $q->id;

        // Filter by text search: user name
        $resp = $this->actingAs($this->admin)->get($baseUrl . '&q=محمد');
        $resp->assertStatus(200);
        $resp->assertSee('محمد الأحمد');
        $resp->assertDontSee('خالد المحمود');

        // Filter by is_correct = 1
        $respCorrect = $this->actingAs($this->admin)->get($baseUrl . '&correct=1');
        $respCorrect->assertStatus(200);
        $respCorrect->assertSee('محمد الأحمد');
        $respCorrect->assertDontSee('خالد المحمود');

        // Filter by is_correct = 0
        $respWrong = $this->actingAs($this->admin)->get($baseUrl . '&correct=0');
        $respWrong->assertStatus(200);
        $respWrong->assertSee('خالد المحمود');
        $respWrong->assertDontSee('محمد الأحمد');

        // Filter by date range
        $respDate = $this->actingAs($this->admin)->get(
            $baseUrl . '&from=' . now()->subDays(2)->toDateString() . '&to=' . now()->addDay()->toDateString()
        );
        $respDate->assertStatus(200);
        $respDate->assertSee('محمد الأحمد');
        $respDate->assertSee('خالد المحمود');
    }

    /**
     * Adversarial Test 6: Pagination with > 30 answers
     */
    public function test_pagination_renders_cleanly_with_many_responses(): void
    {
        $q = QuizQuestion::create([
            'quiz_competition_id' => $this->competition->id,
            'question_text' => 'سؤال به عدد ردود كبير جدا',
            'answer_type' => 'multiple_choice',
        ]);

        $users = User::factory()->count(35)->create();

        // Generate 35 answers
        foreach ($users as $i => $u) {
            QuizAnswer::create([
                'quiz_question_id' => $q->id,
                'user_id' => $u->id,
                'answer' => 'إجابة رقم ' . ($i + 1),
                'answer_type' => 'multiple_choice',
                'is_correct' => ($i + 1) % 2 === 0,
            ]);
        }

        // Page 1
        $respPage1 = $this->actingAs($this->admin)->get(
            route('dashboard.quiz-competitions.responses', $this->competition) . '?question_id=' . $q->id . '&page=1'
        );
        $respPage1->assertStatus(200);
        $respPage1->assertSee('المشاركات المصفاة: 35 سجل');

        // Page 2
        $respPage2 = $this->actingAs($this->admin)->get(
            route('dashboard.quiz-competitions.responses', $this->competition) . '?question_id=' . $q->id . '&page=2'
        );
        $respPage2->assertStatus(200);
        $respPage2->assertSee('المشاركات المصفاة: 35 سجل');
    }

    /**
     * Adversarial Test 7: Malformed and corrupted answers
     */
    public function test_malformed_and_corrupted_answer_payloads_do_not_crash(): void
    {
        $voteQ = QuizQuestion::create([
            'quiz_competition_id' => $this->competition->id,
            'question_text' => 'تصويت ببيانات معطوبة غير صالحة',
            'answer_type' => 'vote',
        ]);

        // Corrupted JSON in vote answer
        QuizAnswer::create([
            'quiz_question_id' => $voteQ->id,
            'user_id' => null,
            'person_id' => null,
            'answer' => '{invalid_json_data',
            'answer_type' => 'vote',
            'created_at' => null,
        ]);

        $surveyQ = QuizQuestion::create([
            'quiz_competition_id' => $this->competition->id,
            'question_text' => 'استبيان ببيانات معطوبة غير صالحة',
            'answer_type' => 'survey',
        ]);

        // Corrupted JSON in survey answer
        QuizAnswer::create([
            'quiz_question_id' => $surveyQ->id,
            'user_id' => null,
            'person_id' => null,
            'answer' => 'not-a-json',
            'answer_type' => 'survey',
            'created_at' => null,
        ]);

        $respVote = $this->actingAs($this->admin)->get(
            route('dashboard.quiz-competitions.responses', $this->competition) . '?question_id=' . $voteQ->id
        );
        $respVote->assertStatus(200);

        $respSurvey = $this->actingAs($this->admin)->get(
            route('dashboard.quiz-competitions.responses', $this->competition) . '?question_id=' . $surveyQ->id
        );
        $respSurvey->assertStatus(200);
    }

    /**
     * Adversarial Test 8: Comprehensive Null-Safety Matrix
     */
    public function test_null_safety_matrix_on_responses_view(): void
    {
        $q = QuizQuestion::create([
            'quiz_competition_id' => $this->competition->id,
            'question_text' => 'سؤال فحص مصفوفة الأمان ضد القيم المعدومة',
            'answer_type' => 'multiple_choice',
        ]);

        // Case A: null user, null person, null created_at
        QuizAnswer::create([
            'quiz_question_id' => $q->id,
            'user_id' => null,
            'person_id' => null,
            'answer' => 'إجابة مجهول',
            'answer_type' => 'multiple_choice',
            'created_at' => null,
        ]);

        // Case B: user with null phone and null person
        $uNoPhone = User::factory()->create(['phone' => null]);
        QuizAnswer::create([
            'quiz_question_id' => $q->id,
            'user_id' => $uNoPhone->id,
            'person_id' => null,
            'answer' => 'إجابة مستخدم بدون جوال',
            'answer_type' => 'multiple_choice',
        ]);

        // Case C: person with mother
        $mother = Person::create([
            'first_name' => 'نورة',
            'last_name' => 'الأسرة',
            'gender' => 'F',
        ]);
        $person = Person::create([
            'first_name' => 'سعد',
            'last_name' => 'الأسرة',
            'gender' => 'M',
            'mother_id' => $mother->id,
        ]);
        QuizAnswer::create([
            'quiz_question_id' => $q->id,
            'user_id' => null,
            'person_id' => $person->id,
            'answer' => 'إجابة عضو بشجرة العائلة',
            'answer_type' => 'multiple_choice',
        ]);

        $resp = $this->actingAs($this->admin)->get(
            route('dashboard.quiz-competitions.responses', $this->competition) . '?question_id=' . $q->id
        );

        $resp->assertStatus(200);
        $resp->assertSee('مستخدم غير مسجل');
        $resp->assertSee('نورة');
        $resp->assertSee('سعد');
    }
}

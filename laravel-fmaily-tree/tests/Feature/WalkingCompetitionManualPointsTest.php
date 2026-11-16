<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\QuizCompetition;
use App\Models\QuizCompetitionWalkingAdjustment;
use App\Models\QuizCompetitionWalkingScore;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Services\Walking\WalkingCompetitionScoringService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalkingCompetitionManualPointsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected QuizCompetition $competition;
    protected QuizQuestion $question;
    protected Person $personA;
    protected Person $personB;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Queue::fake();
        config(['site.password_protection_enabled' => false]);

        $this->mock(\App\Services\AI\EmbeddingsService::class, function ($mock) {
            $mock->shouldIgnoreMissing();
        });

        app(\App\Services\Walking\WalkingLeaderboardEligibilityService::class)->clearCache();

        $this->admin = User::factory()->create();

        $now = Carbon::now(WalkingCompetitionScoringService::KSA_TIMEZONE);

        $this->competition = QuizCompetition::create([
            'title' => 'تحدي المشي الكبير',
            'is_active' => true,
            'start_at' => $now->copy()->subDays(3),
            'end_at' => $now->copy()->addDays(4),
        ]);

        $this->question = QuizQuestion::create([
            'quiz_competition_id' => $this->competition->id,
            'question_text' => 'تحدي المشي اليومي',
            'answer_type' => 'walking',
            'winners_count' => 3,
            'walking_settings' => [
                'scoring_method' => 'daily_rank_points',
                'target_gender' => 'all',
                'min_daily_steps' => 1000,
                'daily_points' => [
                    1 => 10,
                    2 => 7,
                    3 => 5,
                ],
            ],
        ]);

        $this->personA = Person::create([
            'first_name' => 'سالم',
            'last_name' => 'الدوسري',
            'gender' => 'male',
        ]);

        $this->personB = Person::create([
            'first_name' => 'ياسر',
            'last_name' => 'الشهراني',
            'gender' => 'male',
        ]);
    }

    /**
     * Test admin can add manual bonus points to a participant.
     */
    public function test_admin_can_add_manual_bonus_points(): void
    {
        $response = $this->actingAs($this->admin)->postJson(
            route('dashboard.quiz-questions.adjust-walking-points', [
                'quizCompetition' => $this->competition,
                'quizQuestion' => $this->question,
            ]),
            [
                'person_id' => $this->personA->id,
                'points' => 20,
                'type' => 'bonus',
                'reason' => 'مكافأة حضور الفعالية الميدانية للمشي',
                'date' => now()->toDateString(),
            ]
        );

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.adjustment.points', 20);
        $response->assertJsonPath('data.adjustment.type', 'bonus');

        $this->assertDatabaseHas('quiz_competition_walking_adjustments', [
            'quiz_question_id' => $this->question->id,
            'person_id' => $this->personA->id,
            'points' => 20,
            'type' => 'bonus',
            'reason' => 'مكافأة حضور الفعالية الميدانية للمشي',
            'created_by' => $this->admin->id,
        ]);
    }

    /**
     * Test admin can add manual penalty points to a participant.
     */
    public function test_admin_can_add_manual_penalty_points(): void
    {
        $response = $this->actingAs($this->admin)->postJson(
            route('dashboard.quiz-questions.adjust-walking-points', [
                'quizCompetition' => $this->competition,
                'quizQuestion' => $this->question,
            ]),
            [
                'person_id' => $this->personB->id,
                'points' => 15,
                'type' => 'penalty',
                'reason' => 'خصم نقاط بسبب مخالفة تتبع الخطوات بالسيارة',
            ]
        );

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.adjustment.points', -15);
        $response->assertJsonPath('data.adjustment.type', 'penalty');

        $this->assertDatabaseHas('quiz_competition_walking_adjustments', [
            'quiz_question_id' => $this->question->id,
            'person_id' => $this->personB->id,
            'points' => -15,
            'type' => 'penalty',
        ]);
    }

    /**
     * Test reason is required and cannot be empty when adding adjustments.
     */
    public function test_reason_is_required_and_cannot_be_empty(): void
    {
        $response = $this->actingAs($this->admin)->postJson(
            route('dashboard.quiz-questions.adjust-walking-points', [
                'quizCompetition' => $this->competition,
                'quizQuestion' => $this->question,
            ]),
            [
                'person_id' => $this->personA->id,
                'points' => 10,
                'type' => 'bonus',
                'reason' => '',
            ]
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['reason']);
    }

    /**
     * Test points cannot be zero.
     */
    public function test_points_cannot_be_zero(): void
    {
        $response = $this->actingAs($this->admin)->postJson(
            route('dashboard.quiz-questions.adjust-walking-points', [
                'quizCompetition' => $this->competition,
                'quizQuestion' => $this->question,
            ]),
            [
                'person_id' => $this->personA->id,
                'points' => 0,
                'type' => 'bonus',
                'reason' => 'تعديل برصيد صفر غير مقبول',
            ]
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['points']);
    }

    /**
     * Test standings reflect total points including manual adjustments and correctly re-order participants.
     */
    public function test_standings_reflect_total_points_and_correctly_reorder(): void
    {
        $yesterday = now()->subDay()->toDateString();

        // Person A has 10 points originally
        QuizCompetitionWalkingScore::create([
            'quiz_question_id' => $this->question->id,
            'person_id' => $this->personA->id,
            'date' => $yesterday,
            'steps' => 8000,
            'daily_rank' => 2,
            'points_earned' => 10,
            'gender' => 'male',
        ]);

        // Person B has 25 points originally (Leader #1)
        QuizCompetitionWalkingScore::create([
            'quiz_question_id' => $this->question->id,
            'person_id' => $this->personB->id,
            'date' => $yesterday,
            'steps' => 15000,
            'daily_rank' => 1,
            'points_earned' => 25,
            'gender' => 'male',
        ]);

        $service = app(WalkingCompetitionScoringService::class);

        // Before adjustment: Person B is #1 (25 pts), Person A is #2 (10 pts)
        $standingsBefore = $service->getOverallStandings($this->question)['standings'];
        $this->assertEquals($this->personB->id, $standingsBefore[0]->person_id);
        $this->assertEquals(25, $standingsBefore[0]->total_points);
        $this->assertEquals($this->personA->id, $standingsBefore[1]->person_id);
        $this->assertEquals(10, $standingsBefore[1]->total_points);

        // Admin adds +30 manual bonus points to Person A
        $this->actingAs($this->admin)->postJson(
            route('dashboard.quiz-questions.adjust-walking-points', [
                'quizCompetition' => $this->competition,
                'quizQuestion' => $this->question,
            ]),
            [
                'person_id' => $this->personA->id,
                'points' => 30,
                'type' => 'bonus',
                'reason' => 'مكافأة تميز في التحدي الميداني',
            ]
        )->assertOk();

        // After adjustment: Person A has 10 + 30 = 40 total points, jumping to #1!
        $standingsAfter = $service->getOverallStandings($this->question)['standings'];
        $this->assertEquals($this->personA->id, $standingsAfter[0]->person_id);
        $this->assertEquals(40, $standingsAfter[0]->total_points);
        $this->assertEquals(30, $standingsAfter[0]->manual_points);
        $this->assertEquals(40, $standingsAfter[0]->actual_points);
        $this->assertEquals(10, $standingsAfter[0]->raw_steps_points);

        // Person B becomes #2 with 25 points
        $this->assertEquals($this->personB->id, $standingsAfter[1]->person_id);
        $this->assertEquals(25, $standingsAfter[1]->total_points);
        $this->assertEquals(0, $standingsAfter[1]->manual_points);
    }

    /**
     * Test admin can fetch adjustment history for a specific person.
     */
    public function test_admin_can_fetch_adjustment_history_for_person(): void
    {
        QuizCompetitionWalkingAdjustment::create([
            'quiz_question_id' => $this->question->id,
            'person_id' => $this->personA->id,
            'points' => 10,
            'type' => 'bonus',
            'reason' => 'المكافأة الأولى',
            'created_by' => $this->admin->id,
        ]);

        QuizCompetitionWalkingAdjustment::create([
            'quiz_question_id' => $this->question->id,
            'person_id' => $this->personA->id,
            'points' => -5,
            'type' => 'penalty',
            'reason' => 'خصم تأخير',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->getJson(
            route('dashboard.quiz-questions.get-walking-adjustments', [
                'quizCompetition' => $this->competition,
                'quizQuestion' => $this->question,
                'person' => $this->personA,
            ])
        );

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonCount(2, 'data.adjustments');
        $response->assertJsonPath('data.total_manual_points', 5);
    }

    /**
     * Test admin can delete an adjustment and points revert.
     */
    public function test_admin_can_delete_adjustment_and_points_revert(): void
    {
        $adj = QuizCompetitionWalkingAdjustment::create([
            'quiz_question_id' => $this->question->id,
            'person_id' => $this->personA->id,
            'points' => 50,
            'type' => 'bonus',
            'reason' => 'تعديل مؤقت سيتم حذفه',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->deleteJson(
            route('dashboard.quiz-questions.destroy-walking-adjustment', [
                'quizCompetition' => $this->competition,
                'quizQuestion' => $this->question,
                'adjustment' => $adj,
            ])
        );

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $this->assertDatabaseMissing('quiz_competition_walking_adjustments', [
            'id' => $adj->id,
        ]);

        $standings = app(WalkingCompetitionScoringService::class)->getOverallStandings($this->question)['standings'];
        $participantA = $standings->firstWhere('person_id', $this->personA->id);
        $this->assertEquals(0, $participantA?->total_points ?? 0);
    }

    /**
     * Test unauthenticated guest cannot adjust points.
     */
    public function test_guest_cannot_adjust_points(): void
    {
        $response = $this->postJson(
            route('dashboard.quiz-questions.adjust-walking-points', [
                'quizCompetition' => $this->competition,
                'quizQuestion' => $this->question,
            ]),
            [
                'person_id' => $this->personA->id,
                'points' => 10,
                'type' => 'bonus',
                'reason' => 'محاولة غير مصرح بها',
            ]
        );

        $response->assertStatus(401);
    }

    /**
     * Test participant with adjustments but no walking steps is included in standings.
     */
    public function test_participant_with_adjustments_but_no_walking_records_is_included_in_standings(): void
    {
        $personC = Person::create([
            'first_name' => 'نواف',
            'last_name' => 'التمياط',
            'gender' => 'male',
        ]);

        QuizCompetitionWalkingAdjustment::create([
            'quiz_question_id' => $this->question->id,
            'person_id' => $personC->id,
            'points' => 15,
            'type' => 'bonus',
            'reason' => 'نقاط مكافأة بدون تسجيل خطوات سابقة',
            'created_by' => $this->admin->id,
        ]);

        $standings = app(WalkingCompetitionScoringService::class)->getOverallStandings($this->question)['standings'];

        $participantC = $standings->firstWhere('person_id', $personC->id);
        $this->assertNotNull($participantC, 'Participant with only manual adjustments must be included in standings');
        $this->assertEquals(15, $participantC->total_points);
        $this->assertEquals(15, $participantC->manual_points);
        $this->assertEquals(15, $participantC->actual_points);
        $this->assertEquals(0, $participantC->raw_steps_points);
    }

    /**
     * Test responses view loads cleanly for walking question and renders leaderboard table.
     */
    public function test_responses_view_renders_walking_leaderboard(): void
    {
        $response = $this->actingAs($this->admin)->get(
            route('dashboard.quiz-competitions.responses', [
                'quizCompetition' => $this->competition,
                'question_id' => $this->question->id,
            ])
        );

        $response->assertStatus(200);
        $response->assertViewIs('dashboard.quiz-competitions.responses');
        $response->assertSee('لوحة متصدري تحدي المشي وإدارة النقاط المعتمدة');
        $response->assertSee('إجمالي المتسابقين');
    }
}

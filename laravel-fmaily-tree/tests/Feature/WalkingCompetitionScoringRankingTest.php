<?php

namespace Tests\Feature;

use App\Http\Resources\HomePageApiResource;
use App\Models\Person;
use App\Models\QuizCompetition;
use App\Models\QuizCompetitionWalkingScore;
use App\Models\QuizQuestion;
use App\Models\WalkingRecord;
use App\Services\Walking\WalkingCompetitionScoringService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalkingCompetitionScoringRankingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Queue::fake();
        config(['site.password_protection_enabled' => false]);

        $this->mock(\App\Services\AI\EmbeddingsService::class, function ($mock) {
            $mock->shouldIgnoreMissing();
        });

        app(\App\Services\Walking\WalkingLeaderboardEligibilityService::class)->clearCache();
    }

    public function test_standings_are_sorted_by_actual_points_earned_at_midnight_rather_than_today_steps(): void
    {
        $tz = WalkingCompetitionScoringService::KSA_TIMEZONE;
        $now = Carbon::now($tz);

        $comp = QuizCompetition::create([
            'title' => 'تحدي المشي الرمضاني',
            'is_active' => true,
            'start_at' => $now->copy()->subDays(2),
            'end_at' => $now->copy()->addDays(5),
        ]);

        $question = QuizQuestion::create([
            'quiz_competition_id' => $comp->id,
            'question_text' => 'تحدي خطوات المشي اليومي',
            'answer_type' => 'walking',
            'winners_count' => 3,
            'walking_settings' => [
                'scoring_method' => 'daily_rank_points',
                'target_gender' => 'all',
                'min_daily_steps' => 1000,
                'daily_points' => [
                    1 => 5,
                    2 => 3,
                    3 => 1,
                ],
            ],
            'prize' => ['المركز الأول: 1000 ريال', 'المركز الثاني: 500 ريال', 'المركز الثالث: 200 ريال'],
        ]);

        $personLeader = Person::create([
            'first_name' => 'فهد',
            'last_name' => 'العتيبي',
            'gender' => 'male',
        ]);

        $personChallenger = Person::create([
            'first_name' => 'عبدالله',
            'last_name' => 'العتيبي',
            'gender' => 'male',
        ]);

        // Yesterday's recorded midnight scores:
        // Leader has 5 actual points from yesterday
        QuizCompetitionWalkingScore::create([
            'quiz_question_id' => $question->id,
            'person_id' => $personLeader->id,
            'date' => $now->copy()->subDay()->toDateString(),
            'steps' => 8000,
            'daily_rank' => 1,
            'points_earned' => 5,
            'gender' => 'male',
        ]);

        // Challenger has 2 actual points from yesterday
        QuizCompetitionWalkingScore::create([
            'quiz_question_id' => $question->id,
            'person_id' => $personChallenger->id,
            'date' => $now->copy()->subDay()->toDateString(),
            'steps' => 5000,
            'daily_rank' => 2,
            'points_earned' => 2,
            'gender' => 'male',
        ]);

        // Today's steps:
        // Challenger walks 12,000 steps today (1st in steps today!)
        WalkingRecord::create([
            'person_id' => $personChallenger->id,
            'date' => $now->toDateString(),
            'steps' => 12000,
        ]);

        // Leader walks only 2,000 steps today (less steps today than challenger)
        WalkingRecord::create([
            'person_id' => $personLeader->id,
            'date' => $now->toDateString(),
            'steps' => 2000,
        ]);

        $service = app(WalkingCompetitionScoringService::class);
        $result = $service->getOverallStandings($question, 'all', $personLeader->id);
        $standings = $result['standings'];

        $this->assertCount(2, $standings);

        $firstStanding = $standings->first();
        $secondStanding = $standings->last();

        // Leader must remain #1 because actual points = 5 vs 2, even though challenger walked more steps today!
        $this->assertEquals($personLeader->id, $firstStanding->person_id, 'Leader with 5 actual points must be ranked #1');
        $this->assertEquals(1, $firstStanding->rank);
        $this->assertEquals(5, $firstStanding->actual_points);
        $this->assertEquals(3, $firstStanding->projected_points, 'Leader is 2nd in steps today, so projected +3');
        $this->assertEquals(5, $firstStanding->total_points, 'Total points reflects confirmed actual points');
        $this->assertEquals(2000, $firstStanding->today_steps);

        $this->assertEquals($personChallenger->id, $secondStanding->person_id, 'Challenger with 2 actual points must be ranked #2');
        $this->assertEquals(2, $secondStanding->rank);
        $this->assertEquals(2, $secondStanding->actual_points);
        $this->assertEquals(5, $secondStanding->projected_points, 'Challenger is 1st in steps today, so projected +5');
        $this->assertEquals(2, $secondStanding->total_points, 'Total points reflects confirmed actual points');
        $this->assertEquals(12000, $secondStanding->today_steps);
    }

    public function test_tie_breaker_uses_projected_points_and_steps_when_actual_points_are_equal(): void
    {
        $tz = WalkingCompetitionScoringService::KSA_TIMEZONE;
        $now = Carbon::now($tz);

        $comp = QuizCompetition::create([
            'title' => 'تحدي المشي',
            'is_active' => true,
            'start_at' => $now->copy()->subDays(2),
            'end_at' => $now->copy()->addDays(5),
        ]);

        $question = QuizQuestion::create([
            'quiz_competition_id' => $comp->id,
            'question_text' => 'تحدي خطوات المشي اليومي',
            'answer_type' => 'walking',
            'winners_count' => 3,
            'walking_settings' => [
                'scoring_method' => 'daily_rank_points',
                'target_gender' => 'all',
                'daily_points' => [1 => 5, 2 => 3, 3 => 1],
            ],
        ]);

        $personA = Person::create(['first_name' => 'أحمد', 'last_name' => 'سالم', 'gender' => 'male']);
        $personB = Person::create(['first_name' => 'خالد', 'last_name' => 'سالم', 'gender' => 'male']);

        // Both have 5 actual points
        QuizCompetitionWalkingScore::create([
            'quiz_question_id' => $question->id,
            'person_id' => $personA->id,
            'date' => $now->copy()->subDay()->toDateString(),
            'steps' => 5000,
            'daily_rank' => 1,
            'points_earned' => 5,
            'gender' => 'male',
        ]);
        QuizCompetitionWalkingScore::create([
            'quiz_question_id' => $question->id,
            'person_id' => $personB->id,
            'date' => $now->copy()->subDay()->toDateString(),
            'steps' => 5000,
            'daily_rank' => 1,
            'points_earned' => 5,
            'gender' => 'male',
        ]);

        // Person A walks 10,000 steps today, Person B walks 4,000 steps today
        WalkingRecord::create(['person_id' => $personA->id, 'date' => $now->toDateString(), 'steps' => 10000]);
        WalkingRecord::create(['person_id' => $personB->id, 'date' => $now->toDateString(), 'steps' => 4000]);

        $service = app(WalkingCompetitionScoringService::class);
        $result = $service->getOverallStandings($question, 'all');
        $standings = $result['standings'];

        $this->assertEquals($personA->id, $standings[0]->person_id);
        $this->assertEquals(1, $standings[0]->rank);
        $this->assertEquals(5, $standings[0]->actual_points);
        $this->assertEquals(5, $standings[0]->projected_points);

        $this->assertEquals($personB->id, $standings[1]->person_id);
        $this->assertEquals(2, $standings[1]->rank);
        $this->assertEquals(5, $standings[1]->actual_points);
        $this->assertEquals(3, $standings[1]->projected_points);
    }

    public function test_day_one_competition_ranks_by_live_projected_points_when_no_midnight_passed_yet(): void
    {
        $tz = WalkingCompetitionScoringService::KSA_TIMEZONE;
        $now = Carbon::now($tz);

        $comp = QuizCompetition::create([
            'title' => 'تحدي اليوم الأول',
            'is_active' => true,
            'start_at' => $now->copy()->startOfDay(),
            'end_at' => $now->copy()->addDays(3),
        ]);

        $question = QuizQuestion::create([
            'quiz_competition_id' => $comp->id,
            'question_text' => 'تحدي خطوات المشي اليومي',
            'answer_type' => 'walking',
            'winners_count' => 3,
            'walking_settings' => [
                'scoring_method' => 'daily_rank_points',
                'target_gender' => 'all',
                'daily_points' => [1 => 5, 2 => 3, 3 => 1],
            ],
        ]);

        $personA = Person::create(['first_name' => 'ياسر', 'last_name' => 'محمد', 'gender' => 'male']);
        $personB = Person::create(['first_name' => 'طارق', 'last_name' => 'محمد', 'gender' => 'male']);

        // Day 1: No QuizCompetitionWalkingScore recorded yet!
        WalkingRecord::create(['person_id' => $personA->id, 'date' => $now->toDateString(), 'steps' => 9000]);
        WalkingRecord::create(['person_id' => $personB->id, 'date' => $now->toDateString(), 'steps' => 6000]);

        $service = app(WalkingCompetitionScoringService::class);
        $result = $service->getOverallStandings($question, 'all');
        $standings = $result['standings'];

        $this->assertEquals(0, $standings[0]->actual_points);
        $this->assertEquals(5, $standings[0]->projected_points);
        $this->assertEquals($personA->id, $standings[0]->person_id);
        $this->assertEquals(1, $standings[0]->rank);

        $this->assertEquals(0, $standings[1]->actual_points);
        $this->assertEquals(3, $standings[1]->projected_points);
        $this->assertEquals($personB->id, $standings[1]->person_id);
        $this->assertEquals(2, $standings[1]->rank);
    }

    public function test_get_viewer_standing_returns_correct_details(): void
    {
        $tz = WalkingCompetitionScoringService::KSA_TIMEZONE;
        $now = Carbon::now($tz);

        $comp = QuizCompetition::create([
            'title' => 'تحدي فحص بطاقة المتسابق',
            'is_active' => true,
            'start_at' => $now->copy()->subDay(),
            'end_at' => $now->copy()->addDay(),
        ]);

        $question = QuizQuestion::create([
            'quiz_competition_id' => $comp->id,
            'question_text' => 'تحدي خطوات المشي اليومي',
            'answer_type' => 'walking',
            'walking_settings' => [
                'scoring_method' => 'daily_rank_points',
                'target_gender' => 'all',
                'daily_points' => [1 => 5, 2 => 3],
            ],
        ]);

        $person = Person::create(['first_name' => 'عمر', 'last_name' => 'علي', 'gender' => 'male']);

        QuizCompetitionWalkingScore::create([
            'quiz_question_id' => $question->id,
            'person_id' => $person->id,
            'date' => $now->copy()->subDay()->toDateString(),
            'steps' => 7000,
            'daily_rank' => 1,
            'points_earned' => 5,
            'gender' => 'male',
        ]);

        WalkingRecord::create(['person_id' => $person->id, 'date' => $now->toDateString(), 'steps' => 3000]);

        $service = app(WalkingCompetitionScoringService::class);
        $viewerStanding = $service->getViewerStanding($question, $person->id, 'all');

        $this->assertNotNull($viewerStanding);
        $this->assertEquals($person->id, $viewerStanding->person_id);
        $this->assertEquals(5, $viewerStanding->actual_points);
        $this->assertEquals(1, $viewerStanding->rank);
        $this->assertEquals(3000, $viewerStanding->today_steps);
        $this->assertEquals(10000, $viewerStanding->total_steps);
    }

    public function test_finalize_winners_awards_winners_by_points(): void
    {
        $tz = WalkingCompetitionScoringService::KSA_TIMEZONE;
        $now = Carbon::now($tz);

        $comp = QuizCompetition::create([
            'title' => 'تحدي تتويج الفائزين',
            'is_active' => true,
            'start_at' => $now->copy()->subDays(3),
            'end_at' => $now->copy()->subMinute(),
        ]);

        $question = QuizQuestion::create([
            'quiz_competition_id' => $comp->id,
            'question_text' => 'تحدي خطوات المشي النهائي',
            'answer_type' => 'walking',
            'winners_count' => 2,
            'walking_settings' => [
                'scoring_method' => 'daily_rank_points',
                'target_gender' => 'all',
                'daily_points' => [1 => 5, 2 => 3],
            ],
            'prize' => ['1000 ريال', '500 ريال'],
        ]);

        $person1 = Person::create(['first_name' => 'فهد', 'last_name' => 'الزعيم', 'gender' => 'male']);
        $person2 = Person::create(['first_name' => 'سالم', 'last_name' => 'المنافس', 'gender' => 'male']);

        // Person 1 has 15 points, Person 2 has 9 points
        QuizCompetitionWalkingScore::create([
            'quiz_question_id' => $question->id,
            'person_id' => $person1->id,
            'date' => $now->copy()->subDays(2)->toDateString(),
            'steps' => 10000,
            'daily_rank' => 1,
            'points_earned' => 15,
            'gender' => 'male',
        ]);

        QuizCompetitionWalkingScore::create([
            'quiz_question_id' => $question->id,
            'person_id' => $person2->id,
            'date' => $now->copy()->subDays(2)->toDateString(),
            'steps' => 8000,
            'daily_rank' => 2,
            'points_earned' => 9,
            'gender' => 'male',
        ]);

        $service = app(WalkingCompetitionScoringService::class);
        $created = $service->finalizeWinners($question);

        $this->assertEquals(2, $created);

        $winners = \App\Models\QuizWinner::where('quiz_question_id', $question->id)->orderBy('position')->get();
        $this->assertCount(2, $winners);
        $this->assertEquals($person1->id, $winners[0]->person_id);
        $this->assertEquals(1, $winners[0]->position);
        $this->assertEquals(15, $winners[0]->score);

        $this->assertEquals($person2->id, $winners[1]->person_id);
        $this->assertEquals(2, $winners[1]->position);
        $this->assertEquals(9, $winners[1]->score);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\QuizCompetition;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Models\WalkingRecord;
use App\Services\AI\EmbeddingsService;
use App\Services\Walking\WalkingCompetitionScoringService;
use App\Services\Walking\WalkingLeaderboardEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WalkingRaceVideoTimelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        config(['site.password_protection_enabled' => false]);
        $this->mock(EmbeddingsService::class, fn ($mock) => $mock->shouldIgnoreMissing());
        app(WalkingLeaderboardEligibilityService::class)->clearCache();
    }

    public function test_export_returns_ten_ranks_without_identifying_anonymous_participants(): void
    {
        $yesterday = now(WalkingCompetitionScoringService::KSA_TIMEZONE)->subDay();
        $competition = QuizCompetition::create([
            'title' => 'الصحة أولًا',
            'start_at' => $yesterday->copy()->startOfDay(),
            'end_at' => $yesterday->copy()->endOfDay(),
            'is_active' => true,
        ]);
        $question = $competition->questions()->create([
            'question_text' => 'تحدي المشي',
            'answer_type' => 'walking',
            'winners_count' => 3,
            'walking_settings' => ['target_gender' => 'all', 'race_max_ranks' => 3, 'race_enabled' => false],
        ]);

        $leader = Person::create([
            'first_name' => 'اسم خاص',
            'gender' => 'male',
            'walking_leaderboard_mode' => 'anonymous',
        ]);
        for ($rank = 1; $rank <= 12; $rank++) {
            $person = $rank === 1 ? $leader : Person::create(['first_name' => 'متسابق '.$rank, 'gender' => 'male']);
            WalkingRecord::create([
                'person_id' => $person->id,
                'date' => $yesterday->toDateString(),
                'steps' => 16000 - ($rank * 1000),
            ]);
        }

        $admin = User::factory()->create();
        $admin->givePermissionTo(Permission::findOrCreate('dashboard.view'));
        $admin->setRelation('person', $leader);
        $response = $this->actingAs($admin)->getJson(route('dashboard.quiz-questions.walking-race-video', [$competition, $question]));
        $response->assertOk()
            ->assertJsonPath('config.max_ranks', 10)
            ->assertJsonCount(10, 'sections.all.days.0.top_entries')
            ->assertJsonPath('sections.all.days.0.top_entries.0.name', 'متسابق متخفي')
            ->assertJsonMissingPath('sections.all.days.0.top_entries.0.person_id');

        $this->assertSame(3, $question->fresh()->getWalkingRaceMaxRanks());
        $this->assertFalse($question->fresh()->isWalkingRaceEnabled());
    }

    public function test_dashboard_permission_allows_exporting_a_womens_race_without_linking_the_account(): void
    {
        $yesterday = now(WalkingCompetitionScoringService::KSA_TIMEZONE)->subDay();
        $competition = QuizCompetition::create([
            'title' => 'تحدي المشي', 'is_active' => true,
            'start_at' => $yesterday->copy()->startOfDay(), 'end_at' => $yesterday->copy()->endOfDay(),
        ]);
        $question = QuizQuestion::create([
            'quiz_competition_id' => $competition->id,
            'question_text' => 'تحدي المشي للنساء',
            'answer_type' => 'walking',
            'winners_count' => 3,
            'walking_settings' => ['target_gender' => 'female', 'race_enabled' => true],
        ]);
        $walker = Person::create(['first_name' => 'اسم خاص', 'gender' => 'female', 'walking_leaderboard_mode' => 'anonymous']);
        WalkingRecord::create(['person_id' => $walker->id, 'date' => $yesterday->toDateString(), 'steps' => 8000]);

        $page = route('dashboard.quiz-competitions.show', $competition);
        $url = route('dashboard.quiz-questions.walking-race-video', [$competition, $question]);
        $user = User::factory()->create();

        $this->getJson($url)->assertUnauthorized();
        $this->actingAs($user)->getJson($url)->assertForbidden();
        $this->get($page)->assertOk()->assertDontSee('data-video-timeline=', false);
        $user->givePermissionTo(Permission::findOrCreate('dashboard.view'));
        $this->get($page)->assertOk()->assertSee('data-video-timeline=', false);
        $this->getJson($url)->assertOk()
            ->assertJsonPath('access_denied', false)
            ->assertJsonPath('default_section', 'female')
            ->assertJsonPath('sections.female.days.0.top_entries.0.name', 'متسابق متخفي')
            ->assertJsonMissingPath('sections.female.days.0.top_entries.0.person_id')
            ->assertJsonMissingPath('sections.male');
        $this->getJson(route('quiz-competitions.walking-race', [$competition, $question]))->assertForbidden();

        $otherCompetition = QuizCompetition::create(['title' => 'مسابقة أخرى', 'is_active' => true]);
        $this->getJson(route('dashboard.quiz-questions.walking-race-video', [$otherCompetition, $question]))->assertNotFound();
    }

    public function test_export_includes_both_sections_for_a_segregated_race_without_exposing_them_to_public_viewers(): void
    {
        $yesterday = now(WalkingCompetitionScoringService::KSA_TIMEZONE)->subDay();
        $competition = QuizCompetition::create([
            'title' => 'سباق بفئتين', 'is_active' => true,
            'start_at' => $yesterday->copy()->startOfDay(), 'end_at' => $yesterday->copy()->endOfDay(),
        ]);
        $question = $competition->questions()->create([
            'question_text' => 'تحدي المشي', 'answer_type' => 'walking', 'winners_count' => 3,
            'walking_settings' => ['target_gender' => 'both_segregated', 'race_enabled' => true],
        ]);
        foreach (['male', 'female'] as $gender) {
            $person = Person::create(['first_name' => 'متسابق', 'gender' => $gender]);
            WalkingRecord::create(['person_id' => $person->id, 'date' => $yesterday->toDateString(), 'steps' => 8000]);
        }
        $admin = User::factory()->create();
        $admin->givePermissionTo(Permission::findOrCreate('site-content.view'));
        $this->actingAs($admin)->getJson(route('dashboard.quiz-questions.walking-race-video', [$competition, $question]))
            ->assertOk()->assertJsonCount(1, 'sections.male.days.0.top_entries')->assertJsonCount(1, 'sections.female.days.0.top_entries');

        $this->getJson(route('quiz-competitions.walking-race', [$competition, $question]))
            ->assertOk()->assertJsonCount(1, 'data.sections.male.days.0.top_entries')->assertJsonMissingPath('data.sections.female');
    }
}

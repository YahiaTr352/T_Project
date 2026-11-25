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

    public function test_export_requires_dashboard_permission_and_respects_womens_leaderboard_visibility(): void
    {
        $competition = QuizCompetition::create(['title' => 'تحدي المشي', 'is_active' => true]);
        $question = QuizQuestion::create([
            'quiz_competition_id' => $competition->id,
            'question_text' => 'تحدي المشي للنساء',
            'answer_type' => 'walking',
            'winners_count' => 3,
            'walking_settings' => ['target_gender' => 'female'],
        ]);
        $url = route('dashboard.quiz-questions.walking-race-video', [$competition, $question]);
        $user = User::factory()->create();

        $this->getJson($url)->assertUnauthorized();
        $this->actingAs($user)->getJson($url)->assertForbidden();
        $user->givePermissionTo(Permission::findOrCreate('dashboard.view'));
        $this->getJson($url)->assertForbidden();

        $otherCompetition = QuizCompetition::create(['title' => 'مسابقة أخرى', 'is_active' => true]);
        $this->getJson(route('dashboard.quiz-questions.walking-race-video', [$otherCompetition, $question]))->assertNotFound();
    }
}

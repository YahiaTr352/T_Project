<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\QuizCompetition;
use App\Models\QuizCompetitionWalkingScore;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Models\WalkingGroup;
use App\Models\WalkingRecord;
use App\Services\SiteMobileAccessToken;
use App\Services\Walking\WalkingCompetitionScoringService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalkingRaceTimelineTest extends TestCase
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

    public function test_cumulative_and_daily_race_timeline_tracks_day_by_day_rank_swaps(): void
    {
        $tz = WalkingCompetitionScoringService::KSA_TIMEZONE;
        $now = Carbon::now($tz);
        $day1 = $now->copy()->subDays(2)->toDateString();
        $day2 = $now->copy()->subDay()->toDateString();

        $competition = QuizCompetition::create([
            'title' => 'مسابقة المشي الكبرى',
            'is_active' => true,
            'start_at' => $now->copy()->subDays(2)->startOfDay(),
            'end_at' => $now->copy()->addDays(4)->endOfDay(),
        ]);

        $question = QuizQuestion::create([
            'quiz_competition_id' => $competition->id,
            'question_text' => 'سباق المشي التراكمي',
            'answer_type' => 'walking',
            'winners_count' => 10,
            'walking_settings' => [
                'competition_scope' => 'individuals',
                'scoring_method' => 'daily_rank_points',
                'daily_points' => [1 => 5, 2 => 3],
                'min_daily_steps' => 4000,
                'target_gender' => 'male',
                'race_enabled' => true,
                'race_calc_mode' => 'cumulative',
                'race_speed_ms' => 1200,
                'race_max_ranks' => 10,
                'race_title' => 'سباق الترتيب اليومي',
                'race_subtitle' => 'تطور الترتيب يومًا بيوم',
                'race_button_text' => 'عرض سباق الترتيب',
            ],
        ]);

        $yousef = Person::create([
            'first_name' => 'يوسف',
            'last_name' => 'السريع',
            'gender' => 'male',
        ]);

        $abdullah = Person::create([
            'first_name' => 'عبدالله',
            'last_name' => 'السريع',
            'gender' => 'male',
        ]);

        // Day 1: Yousef leads with 8000, Abdullah 5000
        WalkingRecord::create(['person_id' => $yousef->id, 'date' => $day1, 'steps' => 8000]);
        WalkingRecord::create(['person_id' => $abdullah->id, 'date' => $day1, 'steps' => 5000]);

        // Day 2: Abdullah adds 7000 (12,000 cumulative), Yousef adds 2000 (10,000 cumulative)
        WalkingRecord::create(['person_id' => $yousef->id, 'date' => $day2, 'steps' => 2000]);
        WalkingRecord::create(['person_id' => $abdullah->id, 'date' => $day2, 'steps' => 7000]);

        $service = app(WalkingCompetitionScoringService::class);
        $timeline = $service->getDailyRaceTimeline($competition, $question, $yousef);

        $this->assertFalse($timeline['access_denied']);
        $this->assertSame('steps', $timeline['config']['metric']);
        $this->assertSame('male', $timeline['default_section']);
        $this->assertArrayHasKey('male', $timeline['sections']);

        $days = $timeline['sections']['male']['days'];
        $this->assertGreaterThanOrEqual(2, count($days));

        // Day 1 ranking: #1 Yousef (8000), #2 Abdullah (5000)
        $firstDayTop = $days[0]['top_entries'];
        $this->assertSame($yousef->id, $firstDayTop[0]['person_id']);
        $this->assertSame(1, $firstDayTop[0]['rank']);
        $this->assertSame(8000, $firstDayTop[0]['steps']);
        $this->assertSame($abdullah->id, $firstDayTop[1]['person_id']);
        $this->assertSame(2, $firstDayTop[1]['rank']);
        $this->assertSame(5000, $firstDayTop[1]['steps']);
        $this->assertNotEmpty($days[0]['date_label']);

        // Day 2 ranking flips: #1 Abdullah (12000 cumulative), #2 Yousef (10000 cumulative)
        $secondDayTop = $days[1]['top_entries'];
        $this->assertSame(12000, $days[1]['max_steps']);
        $this->assertSame($abdullah->id, $secondDayTop[0]['person_id']);
        $this->assertSame(1, $secondDayTop[0]['rank']);
        $this->assertSame(12000, $secondDayTop[0]['steps']);
        $this->assertSame(7000, $secondDayTop[0]['daily_steps']);
        $this->assertSame($yousef->id, $secondDayTop[1]['person_id']);
        $this->assertSame(2, $secondDayTop[1]['rank']);
        $this->assertSame(10000, $secondDayTop[1]['steps']);

        // The first day was finalized with a different points schedule.
        foreach ([[$yousef, 8000, 1, 4], [$abdullah, 5000, 2, 7]] as [$person, $steps, $rank, $points]) {
            QuizCompetitionWalkingScore::create([
                'quiz_question_id' => $question->id,
                'person_id' => $person->id,
                'date' => $day1,
                'steps' => $steps,
                'daily_rank' => $rank,
                'points_earned' => $points,
                'gender' => 'male',
            ]);
        }
        WalkingRecord::create(['person_id' => $yousef->id, 'date' => $now->toDateString(), 'steps' => 6000]);

        $settings = $question->walking_settings;
        $settings['race_calc_mode'] = 'daily';
        $question->walking_settings = $settings;
        $question->save();
        $service->clearCompetitionCaches($question);

        $dailyTimeline = $service->getDailyRaceTimeline($competition, $question, $yousef);
        $this->assertSame('points', $dailyTimeline['config']['metric']);
        $dailyDays = $dailyTimeline['sections']['male']['days'];

        $this->assertSame(7, $dailyDays[0]['max_points']);
        $this->assertSame(8000, $dailyDays[0]['max_steps']);
        $this->assertSame([$abdullah->id, $yousef->id], array_column($dailyDays[0]['top_entries'], 'person_id'));
        $this->assertSame([7, 4], array_column($dailyDays[0]['top_entries'], 'points'));

        $this->assertSame(5, $dailyDays[1]['max_points']);
        $this->assertSame([5, 0], array_column($dailyDays[1]['top_entries'], 'points'));
        $this->assertSame([7, 4], array_column($dailyDays[1]['top_entries'], 'prev_points'));
        $this->assertSame(7000, $dailyDays[1]['top_entries'][0]['steps']);
        $this->assertSame(2000, $dailyDays[1]['top_entries'][1]['steps']);

        $today = $dailyDays[2]['top_entries'];
        $this->assertSame([$yousef->id], array_column($today, 'person_id'));
        $this->assertSame(5, $today[0]['points']);
        $this->assertSame(0, $today[0]['prev_points']);
    }

    public function test_daily_group_race_uses_the_configured_rank_points(): void
    {
        $today = Carbon::today(WalkingCompetitionScoringService::KSA_TIMEZONE);
        $competition = QuizCompetition::create([
            'title' => 'تحدي المجموعات',
            'is_active' => true,
            'start_at' => $today,
            'end_at' => $today->copy()->endOfDay(),
        ]);

        $groupIds = [];
        foreach ([7000, 5000] as $steps) {
            $person = Person::create(['first_name' => 'متسابق', 'last_name' => 'المجموعة', 'gender' => 'male']);
            $group = WalkingGroup::create(['name' => 'مجموعة ' . $steps, 'created_by_person_id' => $person->id]);
            $groupIds[] = $group->id;
            WalkingRecord::create(['person_id' => $person->id, 'date' => $today->toDateString(), 'steps' => $steps]);
        }

        $question = QuizQuestion::create([
            'quiz_competition_id' => $competition->id,
            'question_text' => 'سباق المجموعات',
            'answer_type' => 'walking',
            'walking_settings' => [
                'competition_scope' => 'groups',
                'competing_group_ids' => $groupIds,
                'group_daily_points' => [1 => 2, 2 => 6],
                'target_gender' => 'all',
                'race_enabled' => true,
                'race_calc_mode' => 'daily',
            ],
        ]);

        $this->getJson(route('quiz-competitions.walking-race', [$competition, $question]))
            ->assertOk()
            ->assertJsonPath('data.config.metric', 'points')
            ->assertJsonPath('data.sections.groups.days.0.max_points', 6)
            ->assertJsonPath('data.sections.groups.days.0.top_entries.0.person_id', $groupIds[1])
            ->assertJsonPath('data.sections.groups.days.0.top_entries.0.points', 6)
            ->assertJsonPath('data.sections.groups.days.0.top_entries.0.steps', 5000);
    }

    public function test_privacy_enforced_for_female_race_timeline_on_public_and_api_endpoints(): void
    {
        $tz = WalkingCompetitionScoringService::KSA_TIMEZONE;
        $now = Carbon::now($tz);

        $competition = QuizCompetition::create([
            'title' => 'تحدي المشي النسائي',
            'is_active' => true,
            'start_at' => $now->copy()->subDays(2),
            'end_at' => $now->copy()->addDays(4),
        ]);

        $question = QuizQuestion::create([
            'quiz_competition_id' => $competition->id,
            'question_text' => 'ترتيب المتسابقات',
            'answer_type' => 'walking',
            'walking_settings' => [
                'target_gender' => 'female',
                'race_enabled' => true,
                'race_calc_mode' => 'daily',
            ],
        ]);

        $maleViewer = Person::create([
            'first_name' => 'خالد',
            'last_name' => 'السريع',
            'gender' => 'male',
        ]);

        $femaleParticipant = Person::create([
            'first_name' => 'سارة',
            'last_name' => 'السريع',
            'gender' => 'female',
        ]);

        WalkingRecord::create([
            'person_id' => $femaleParticipant->id,
            'date' => $now->copy()->subDay()->toDateString(),
            'steps' => 6500,
        ]);

        $maleToken = SiteMobileAccessToken::issue(60, [
            'person_id' => $maleViewer->id,
            'auth_method' => 'whatsapp',
        ])['token'];

        $femaleToken = SiteMobileAccessToken::issue(60, [
            'person_id' => $femaleParticipant->id,
            'auth_method' => 'whatsapp',
        ])['token'];

        // Male viewer is blocked from seeing female competition race timeline via Mobile API
        $maleRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $maleToken,
        ])->getJson('/api/v1/quiz-competitions/walking-race/' . $question->id);
        $maleRes->assertStatus(403);

        // Clear session auto-hydrated for male viewer before testing female viewer token
        $this->flushSession();

        // Female viewer gets full timeline via Mobile API
        $femaleRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $femaleToken,
        ])->getJson('/api/v1/quiz-competitions/walking-race/' . $question->id);
        $femaleRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.access_denied', false)
            ->assertJsonPath('data.can_view_female', true)
            ->assertJsonPath('data.default_section', 'female')
            ->assertJsonPath('data.config.metric', 'points')
            ->assertJsonPath('data.sections.female.days.1.top_entries.0.person_id', $femaleParticipant->id);
    }

    public function test_admin_can_toggle_and_configure_walking_race_settings(): void
    {
        $admin = User::factory()->create();
        $tz = WalkingCompetitionScoringService::KSA_TIMEZONE;
        $now = Carbon::now($tz);

        $competition = QuizCompetition::create([
            'title' => 'مسابقة لوحة التحكم',
            'is_active' => true,
            'start_at' => $now->copy()->subDays(1),
            'end_at' => $now->copy()->addDays(5),
        ]);

        $question = QuizQuestion::create([
            'quiz_competition_id' => $competition->id,
            'question_text' => 'تحدي المشي',
            'answer_type' => 'walking',
            'walking_settings' => [
                'target_gender' => 'all',
            ],
        ]);

        // Disabled by default -> public endpoint blocks
        $disabledRes = $this->getJson(route('quiz-competitions.walking-race', [$competition, $question]));
        $disabledRes->assertStatus(403);

        // Admin enables & customizes race settings
        $postRes = $this->actingAs($admin)->post(
            route('dashboard.quiz-questions.walking-race-settings', [$competition, $question]),
            [
                'race_enabled' => '1',
                'race_calc_mode' => 'daily',
                'race_speed_ms' => 950,
                'race_max_ranks' => 5,
                'race_title' => 'سباق الأبطال',
                'race_subtitle' => 'النقاط اليومية',
                'race_button_text' => 'شاهد السباق المتحرك',
            ]
        );
        $postRes->assertRedirect();

        $question->refresh();
        $this->assertTrue($question->isWalkingRaceEnabled());
        $this->assertSame('daily', $question->getWalkingRaceCalcMode());
        $this->assertSame(950, $question->getWalkingRaceSpeedMs());
        $this->assertSame(5, $question->getWalkingRaceMaxRanks());
        $this->assertSame('سباق الأبطال', $question->getWalkingRaceTitle());
        $this->assertSame('النقاط اليومية', $question->getWalkingRaceSubtitle());
        $this->assertSame('شاهد السباق المتحرك', $question->getWalkingRaceButtonText());

        // Public web endpoint now succeeds
        $enabledRes = $this->getJson(route('quiz-competitions.walking-race', [$competition, $question]));
        $enabledRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.config.calc_mode', 'daily')
            ->assertJsonPath('data.config.max_ranks', 5);

        // Quick hide toggle preserves saved config & hides public endpoint
        $hideRes = $this->actingAs($admin)->post(
            route('dashboard.quiz-questions.walking-race-settings', [$competition, $question]),
            [
                'quick_toggle' => '1',
                'race_enabled' => '0',
            ]
        );
        $hideRes->assertRedirect();

        $question->refresh();
        $this->assertFalse($question->isWalkingRaceEnabled());
        $this->assertSame('daily', $question->getWalkingRaceCalcMode());
        $this->assertSame(950, $question->getWalkingRaceSpeedMs());
        $this->getJson(route('quiz-competitions.walking-race', [$competition, $question]))->assertStatus(403);
    }

    public function test_race_max_ranks_zero_returns_all_active_contestants(): void
    {
        $admin = User::factory()->create();
        $tz = WalkingCompetitionScoringService::KSA_TIMEZONE;
        $now = Carbon::now($tz);
        $day = $now->copy()->subDays(2)->toDateString();

        $competition = QuizCompetition::create([
            'title' => 'مسابقة جميع المتسابقين',
            'is_active' => true,
            'start_at' => $now->copy()->subDays(2)->startOfDay(),
            'end_at' => $now->copy()->addDays(4)->endOfDay(),
        ]);

        $question = QuizQuestion::create([
            'quiz_competition_id' => $competition->id,
            'question_text' => 'الترتيب الكامل',
            'answer_type' => 'walking',
            'walking_settings' => [
                'target_gender' => 'male',
                'race_enabled' => true,
                'race_max_ranks' => 1,
            ],
        ]);

        for ($i = 1; $i <= 4; $i++) {
            $p = Person::create([
                'first_name' => 'متسابق' . $i,
                'last_name' => 'العائلة',
                'gender' => 'male',
            ]);
            WalkingRecord::create([
                'person_id' => $p->id,
                'date' => $day,
                'steps' => 1000 * (5 - $i),
            ]);
        }

        // First confirm custom limit 1 returns only 1 entry
        $limitedRes = $this->getJson(route('quiz-competitions.walking-race', [$competition, $question]));
        $limitedRes->assertOk()
            ->assertJsonPath('data.config.max_ranks', 1);
        $this->assertCount(1, $limitedRes->json('data.sections.male.days.0.top_entries'));

        // Save 0 (= All contestants) via admin controller
        $this->actingAs($admin)->post(
            route('dashboard.quiz-questions.walking-race-settings', [$competition, $question]),
            [
                'race_enabled' => '1',
                'race_calc_mode' => 'cumulative',
                'race_speed_ms' => 2800,
                'race_max_ranks' => 0,
            ]
        )->assertRedirect();

        $question->refresh();
        $this->assertSame(0, $question->getWalkingRaceMaxRanks());

        $allRes = $this->getJson(route('quiz-competitions.walking-race', [$competition, $question]));
        $allRes->assertOk()
            ->assertJsonPath('data.config.max_ranks', 0);
        $this->assertCount(4, $allRes->json('data.sections.male.days.0.top_entries'));
    }
}

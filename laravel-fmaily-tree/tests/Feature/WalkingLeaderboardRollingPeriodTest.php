<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\WalkingRecord;
use App\Services\SiteMobileAccessToken;
use App\Services\Walking\WalkingLeaderboardService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WalkingLeaderboardRollingPeriodTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['site.password_protection_enabled' => false]);
    }

    public function test_only_members_with_positive_steps_in_the_selected_period_are_ranked(): void
    {
        Queue::fake();
        Http::preventStrayRequests();

        $this->travelTo(Carbon::parse('2026-11-18 00:43:00', 'Asia/Riyadh'), function (Carbon $now) {
            $profile = ['gender' => 'male', 'from_outside_the_family' => false];
            $walker = Person::factory()->createQuietly($profile);
            $inactive = Person::factory()->createQuietly($profile);
            $today = $now->toDateString();
            $yesterday = $now->copy()->subDay()->toDateString();
            WalkingRecord::create(['person_id' => $walker->id, 'date' => $yesterday, 'steps' => 2000]);
            WalkingRecord::create(['person_id' => $walker->id, 'date' => $today, 'steps' => 0]);
            WalkingRecord::create(['person_id' => $inactive->id, 'date' => $today, 'steps' => 0]);

            $identity = SiteMobileAccessToken::issue(60, ['person_id' => $walker->id, 'auth_method' => 'face']);
            $this->withHeader('X-Site-Access-Token', $identity['token']);
            $leaderboard = '/api/v1/walking/leaderboard?per_page=1&period=';

            $this->getJson($leaderboard.'today')->assertOk()
                ->assertJsonCount(0, 'data.top')
                ->assertJsonPath('data.pagination.total', 0)
                ->assertJsonPath('data.viewer_rank.rank', null)
                ->assertJsonPath('data.viewer_rank.total_steps', 0);

            foreach (['weekly', 'monthly', 'all_time'] as $period) {
                $this->getJson($leaderboard.$period)->assertOk()
                    ->assertJsonPath('data.pagination.total', 1)
                    ->assertJsonPath('data.top.0.person_id', $walker->id)
                    ->assertJsonPath('data.top.0.total_steps', 2000);
            }

            $this->postJson('/api/v1/walking/sync', ['date' => $today, 'steps' => 1])->assertOk();
            $this->getJson($leaderboard.'today')->assertOk()
                ->assertJsonPath('data.pagination.total', 1)
                ->assertJsonPath('data.top.0.rank', 1)
                ->assertJsonPath('data.top.0.person_id', $walker->id)
                ->assertJsonPath('data.top.0.total_steps', 1);

            $this->assertDatabaseHas('walking_records', ['person_id' => $inactive->id, 'steps' => 0]);
        });
    }

    /**
     * التحقق من أن حساب الأسبوع هو 7 أيام متدحرجة تنتهي عند اليوم المرجعي (الخميس -> الجمعة السابقة).
     */
    public function test_weekly_period_uses_rolling_7_day_window(): void
    {
        $service = app(WalkingLeaderboardService::class);

        // الخميس 15 أكتوبر 2026
        $anchorThursday = Carbon::parse('2026-10-15');
        [$from, $to] = $service->resolveDateRange(WalkingLeaderboardService::PERIOD_WEEKLY, $anchorThursday);

        // يجب أن يبدأ من الجمعة 9 أكتوبر وينتهي بالخميس 15 أكتوبر (7 أيام بالضبط)
        $this->assertEquals('2026-10-09', $from->toDateString(), 'Weekly window should start from previous Friday');
        $this->assertEquals('Friday', $from->format('l'));
        $this->assertEquals('2026-10-15', $to->toDateString(), 'Weekly window should end on anchor Thursday');
        $this->assertEquals('Thursday', $to->format('l'));
        $this->assertEquals(7, $from->diffInDays($to) + 1, 'Total days must be exactly 7');
    }

    /**
     * التحقق من أن حساب الأسبوع ينتهي عند الجمعة إذا كان اليوم المرجعي جمعة (الجمعة -> السبت السابق).
     */
    public function test_weekly_period_on_friday_rolls_from_previous_saturday(): void
    {
        $service = app(WalkingLeaderboardService::class);

        // الجمعة 16 أكتوبر 2026
        $anchorFriday = Carbon::parse('2026-10-16');
        [$from, $to] = $service->resolveDateRange(WalkingLeaderboardService::PERIOD_WEEKLY, $anchorFriday);

        $this->assertEquals('2026-10-10', $from->toDateString(), 'Weekly window should start from previous Saturday');
        $this->assertEquals('Saturday', $from->format('l'));
        $this->assertEquals('2026-10-16', $to->toDateString(), 'Weekly window should end on anchor Friday');
        $this->assertEquals('Friday', $to->format('l'));
        $this->assertEquals(7, $from->diffInDays($to) + 1, 'Total days must be exactly 7');
    }

    /**
     * التحقق من أن حساب الشهر هو 30 يوماً متدحرجة تنتهي باليوم المرجعي.
     */
    public function test_monthly_period_uses_rolling_30_day_window(): void
    {
        $service = app(WalkingLeaderboardService::class);

        $anchorDate = Carbon::parse('2026-10-15');
        [$from, $to] = $service->resolveDateRange(WalkingLeaderboardService::PERIOD_MONTHLY, $anchorDate);

        $this->assertEquals('2026-09-16', $from->toDateString());
        $this->assertEquals('2026-10-15', $to->toDateString());
        $this->assertEquals(30, $from->diffInDays($to) + 1, 'Total days must be exactly 30');
    }

    /**
     * التحقق من أن حساب اليوم ينتهي ويبدأ في نفس اليوم المرجعي.
     */
    public function test_today_period_uses_exact_single_day(): void
    {
        $service = app(WalkingLeaderboardService::class);

        $anchorDate = Carbon::parse('2026-10-15');
        [$from, $to] = $service->resolveDateRange(WalkingLeaderboardService::PERIOD_TODAY, $anchorDate);

        $this->assertEquals('2026-10-15', $from->toDateString());
        $this->assertEquals('2026-10-15', $to->toDateString());
        $this->assertEquals(1, $from->diffInDays($to) + 1, 'Total days must be exactly 1');
    }
}

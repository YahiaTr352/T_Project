<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\WalkingRecord;
use App\Services\Walking\WalkingLeaderboardService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalkingLeaderboardRollingPeriodTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['site.password_protection_enabled' => false]);
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

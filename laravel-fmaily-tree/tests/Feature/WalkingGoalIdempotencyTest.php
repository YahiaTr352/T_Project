<?php

namespace Tests\Feature;

use App\Jobs\SendChallengeGoalAchievedJob;
use App\Jobs\SendDailyGoalAchievedJob;
use App\Models\Notification;
use App\Models\Person;
use App\Models\UserDevice;
use App\Models\WalkingGroup;
use App\Models\WalkingGroupMember;
use App\Models\WalkingRecord;
use App\Services\Walking\WalkingGroupNotificationService;
use App\Services\Walking\WalkingGroupService;
use App\Services\Walking\WalkingRecordService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WalkingGoalIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected Person $person;
    protected WalkingRecordService $recordService;
    protected WalkingGroupService $groupService;
    protected WalkingGroupNotificationService $notificationService;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-20 16:00:00');

        config(['walking.daily_goal_steps' => 10000]);
        config(['site.password_protection_enabled' => false]);

        \Illuminate\Support\Facades\Queue::fake([
            \App\Jobs\IndexPersonEmbeddingJob::class,
        ]);

        $this->person = Person::create([
            'first_name' => 'فهد',
            'last_name'  => 'السريع',
            'gender'     => 'male',
            'phone'      => '966500000001',
        ]);

        $this->recordService = app(WalkingRecordService::class);
        $this->groupService = app(WalkingGroupService::class);
        $this->notificationService = app(WalkingGroupNotificationService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_daily_goal_job_is_dispatched_exactly_once_when_steps_reach_target(): void
    {
        Queue::fake([
            SendDailyGoalAchievedJob::class,
            \App\Jobs\IndexPersonEmbeddingJob::class,
        ]);

        $today = Carbon::today('Asia/Riyadh')->toDateString();

        // 1. First sync with 10,050 steps (reaches daily goal)
        $this->recordService->syncForPerson($this->person->id, [
            'date'  => $today,
            'steps' => 10050,
        ]);

        Queue::assertPushed(SendDailyGoalAchievedJob::class, 1);

        // 2. Second sync moments later with 10,200 steps (same day)
        $this->recordService->syncForPerson($this->person->id, [
            'date'  => $today,
            'steps' => 10200,
        ]);

        // Still pushed exactly 1 time in total, NOT 2!
        Queue::assertPushed(SendDailyGoalAchievedJob::class, 1);
    }

    public function test_challenge_completion_job_is_dispatched_exactly_once(): void
    {
        Queue::fake([
            SendChallengeGoalAchievedJob::class,
            \App\Jobs\IndexPersonEmbeddingJob::class,
        ]);

        $group = WalkingGroup::create([
            'name'                 => 'تحدي العشرة آلاف',
            'status'               => 'active',
            'target_steps'         => 10000,
            'start_date'           => now()->subDays(2)->toDateString(),
            'end_date'             => now()->addDays(5)->toDateString(),
            'created_by_person_id' => $this->person->id,
        ]);

        $membership = WalkingGroupMember::create([
            'walking_group_id' => $group->id,
            'person_id'        => $this->person->id,
            'status'           => 'accepted',
            'joined_at'        => now()->subDays(2),
        ]);

        $today = Carbon::today('Asia/Riyadh')->toDateString();

        // Create walking record meeting target
        WalkingRecord::create([
            'person_id' => $this->person->id,
            'date'      => $today,
            'steps'     => 10500,
        ]);

        // First check
        $this->groupService->checkAndMarkMemberChallengeCompletion($this->person->id);
        Queue::assertPushed(SendChallengeGoalAchievedJob::class, 1);

        // Second check should be a no-op
        $this->groupService->checkAndMarkMemberChallengeCompletion($this->person->id);
        Queue::assertPushed(SendChallengeGoalAchievedJob::class, 1);

        $membership->refresh();
        $this->assertNotNull($membership->completed_at);
    }

    public function test_notification_service_suppresses_duplicate_daily_goal_notifications(): void
    {
        $today = Carbon::today('Asia/Riyadh')->toDateString();

        // Send first notification
        $this->notificationService->notifyDailyGoalReached($this->person, 10500, 10000, $today);

        $countAfterFirst = Notification::whereHas('recipients', fn($q) => $q->where('person_id', $this->person->id))
            ->whereJsonContains('targeting_filters->data->type', 'daily_goal_reached')
            ->count();

        $this->assertEquals(1, $countAfterFirst);

        // Send duplicate notification for the same day
        $this->notificationService->notifyDailyGoalReached($this->person, 10500, 10000, $today);

        $countAfterSecond = Notification::whereHas('recipients', fn($q) => $q->where('person_id', $this->person->id))
            ->whereJsonContains('targeting_filters->data->type', 'daily_goal_reached')
            ->count();

        // Must still be 1!
        $this->assertEquals(1, $countAfterSecond);
    }

    public function test_notification_service_suppresses_duplicate_challenge_goal_notifications(): void
    {
        $group = WalkingGroup::create([
            'name'                 => 'تحدي الأبطال',
            'status'               => 'active',
            'target_steps'         => 10000,
            'created_by_person_id' => $this->person->id,
        ]);

        // Send first notification
        $this->notificationService->notifyMemberGoalAchieved($group, $this->person, 'خلال يومين');

        $countAfterFirst = Notification::whereHas('recipients', fn($q) => $q->where('person_id', $this->person->id))
            ->whereJsonContains('targeting_filters->data->type', 'challenge_member_goal_reached')
            ->whereJsonContains('targeting_filters->data->group_id', $group->id)
            ->count();

        $this->assertEquals(1, $countAfterFirst);

        // Send duplicate notification for the same challenge & person
        $this->notificationService->notifyMemberGoalAchieved($group, $this->person, 'خلال يومين');

        $countAfterSecond = Notification::whereHas('recipients', fn($q) => $q->where('person_id', $this->person->id))
            ->whereJsonContains('targeting_filters->data->type', 'challenge_member_goal_reached')
            ->whereJsonContains('targeting_filters->data->group_id', $group->id)
            ->count();

        // Must still be 1!
        $this->assertEquals(1, $countAfterSecond);
    }

    public function test_jobs_define_unique_deduplication_keys(): void
    {
        $dailyJob = new SendDailyGoalAchievedJob($this->person->id, 10000, 10000, '2026-10-20');
        $this->assertEquals("daily_goal_{$this->person->id}_2026-10-20", $dailyJob->uniqueId());
        $this->assertEquals(86400, $dailyJob->uniqueFor);

        $challengeJob = new SendChallengeGoalAchievedJob(42, $this->person->id, 'خلال 3 أيام');
        $this->assertEquals("challenge_goal_42_{$this->person->id}", $challengeJob->uniqueId());
        $this->assertGreaterThan(0, $challengeJob->uniqueFor);
    }
}

<?php

namespace Tests\Unit;

use App\Jobs\SendWhatsAppInvitationJob;
use App\Models\NotificationSetting;
use App\Services\WhatsApp\WhatsAppThrottleService;
use App\Support\NotificationSettings;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WhatsAppAntiBanThrottlingTest extends TestCase
{
    protected function tearDown(): void
    {
        NotificationSettings::forgetCache();
        parent::tearDown();
    }

    public function test_preset_definitions_structure(): void
    {
        $presets = NotificationSettings::presetDefinitions();

        $this->assertArrayHasKey(NotificationSettings::PRESET_SAFE, $presets);
        $this->assertArrayHasKey(NotificationSettings::PRESET_WARMUP, $presets);
        $this->assertArrayHasKey(NotificationSettings::PRESET_CUSTOM, $presets);

        $safe = $presets[NotificationSettings::PRESET_SAFE];
        $this->assertEquals(25, $safe['send_delay_min']);
        $this->assertEquals(45, $safe['send_delay_max']);
        $this->assertEquals(20, $safe['batch_size']);
        $this->assertEquals(180, $safe['batch_pause_seconds']);
        $this->assertEquals(250, $safe['daily_limit']);

        $warmup = $presets[NotificationSettings::PRESET_WARMUP];
        $this->assertEquals(40, $warmup['send_delay_min']);
        $this->assertEquals(70, $warmup['send_delay_max']);
        $this->assertEquals(10, $warmup['batch_size']);
        $this->assertEquals(300, $warmup['batch_pause_seconds']);
        $this->assertEquals(50, $warmup['daily_limit']);
    }

    public function test_safe_preset_limits_are_enforced_by_default(): void
    {
        NotificationSettings::forgetCache();
        $limits = NotificationSettings::limits();

        $this->assertEquals(NotificationSettings::PRESET_SAFE, $limits['preset']);
        $this->assertEquals(25, $limits['send_delay_min']);
        $this->assertEquals(45, $limits['send_delay_max']);
        $this->assertEquals(20, $limits['batch_size']);
        $this->assertEquals(180, $limits['batch_pause_seconds']);
        $this->assertEquals(250, $limits['daily_limit']);
    }

    public function test_calculate_staggered_delays_incorporates_random_jitter_and_batch_pauses(): void
    {
        $service = new WhatsAppThrottleService();

        // 1. Zero count returns empty array
        $this->assertEmpty($service->calculateStaggeredDelays(0));

        // 2. Count = 1 has delay 0
        $single = $service->calculateStaggeredDelays(1);
        $this->assertCount(1, $single);
        $this->assertEquals(0, $single[0]);

        // 3. Test 25 recipients (should trigger 1 batch pause after msg 20 with safe defaults)
        $delays = $service->calculateStaggeredDelays(25);
        $this->assertCount(25, $delays);
        $this->assertEquals(0, $delays[0]);

        // Every subsequent delay must be strictly increasing
        for ($i = 1; $i < 25; $i++) {
            $diff = $delays[$i] - $delays[$i - 1];

            if ($i === 20) {
                // At index 20, cooling pause (180s) + jitter (25..45) must be present
                $this->assertGreaterThanOrEqual(180 + 25, $diff);
                $this->assertLessThanOrEqual(180 + 45, $diff);
            } else {
                // Regular jitter (25..45 seconds)
                $this->assertGreaterThanOrEqual(25, $diff);
                $this->assertLessThanOrEqual(45, $diff);
            }
        }
    }

    public function test_estimate_completion_time_formats_arabic_strings(): void
    {
        $service = new WhatsAppThrottleService();

        $est1 = $service->estimateCompletionTime(1);
        $this->assertEquals('فوري (أقل من دقيقة)', $est1['formatted_arabic']);

        $est50 = $service->estimateCompletionTime(50);
        $this->assertGreaterThan(0, $est50['avg_seconds']);
        $this->assertStringContainsString('دقيقة', $est50['formatted_arabic']);
    }

    public function test_whatsapp_job_can_be_dispatched_with_delay(): void
    {
        Queue::fake();

        SendWhatsAppInvitationJob::dispatch(
            1,
            null,
            '966500000000',
            'مرحباً بك'
        )->delay(now()->addSeconds(45));

        Queue::assertPushed(SendWhatsAppInvitationJob::class, function ($job) {
            return $job->delay !== null;
        });
    }

    public function test_notification_settings_channel_helpers(): void
    {
        NotificationSettings::forgetCache();

        $this->assertTrue(NotificationSettings::isChannelEnabled('whatsapp'));
        $this->assertTrue(NotificationSettings::isChannelEnabled('push'));
        $this->assertTrue(NotificationSettings::canSendPush());
        $this->assertTrue(NotificationSettings::canSendWhatsApp());
        $this->assertFalse(NotificationSettings::isDailyLimitExceeded());
    }

    public function test_walking_whatsapp_job_can_be_dispatched_with_staggered_delay(): void
    {
        Queue::fake();

        \App\Jobs\SendWalkingWhatsAppInvitationJob::dispatch(
            1,
            2,
            'أحمد'
        )->delay(now()->addSeconds(35));

        Queue::assertPushed(\App\Jobs\SendWalkingWhatsAppInvitationJob::class, function ($job) {
            return $job->groupId === 1 && $job->personId === 2 && $job->delay !== null;
        });
    }
}

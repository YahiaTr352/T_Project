<?php

namespace Tests\Unit;

use App\Models\AiSetting;
use App\Models\AiUsageLog;
use App\Services\AI\AiFeatureGate;
use App\Services\AI\AiUsageTracker;
use App\Services\AI\Exceptions\AiBudgetExceededException;
use App\Services\AI\Exceptions\AiFeatureDisabledException;
use App\Support\AiSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiFeatureGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_feature_throws(): void
    {
        AiSetting::setValue('features', [
            'chat' => ['enabled' => false, 'model' => null],
        ]);
        AiSettings::forgetCache();

        $gate = app(AiFeatureGate::class);

        $this->expectException(AiFeatureDisabledException::class);
        $gate->assertEnabled('chat');
    }

    public function test_budget_exceeded_throws_when_soft_stop_enabled(): void
    {
        AiSetting::setValue('budget', [
            'monthly_usd' => 1,
            'soft_stop' => true,
        ]);
        AiSettings::forgetCache();

        AiUsageLog::query()->create([
            'feature' => 'chat',
            'provider' => 'fake',
            'model' => 'fake',
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'cost_usd' => 1.5,
        ]);

        $gate = new AiFeatureGate(app(AiUsageTracker::class));

        $this->expectException(AiBudgetExceededException::class);
        $gate->assertBudget();
    }

    public function test_rate_limit_blocks_after_threshold(): void
    {
        AiSetting::setValue('rate_limit', [
            'per_user_per_minute' => 1,
            'per_ip_per_minute' => 100,
        ]);
        AiSettings::forgetCache();

        $gate = app(AiFeatureGate::class);
        $gate->assertRateLimit(99, null);

        $this->expectException(AiFeatureDisabledException::class);
        $gate->assertRateLimit(99, null);
    }
}

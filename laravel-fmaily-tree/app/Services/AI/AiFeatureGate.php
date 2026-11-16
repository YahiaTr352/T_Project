<?php

namespace App\Services\AI;

use App\Services\AI\Exceptions\AiBudgetExceededException;
use App\Services\AI\Exceptions\AiFeatureDisabledException;
use App\Support\AiSettings;
use Illuminate\Support\Facades\RateLimiter;

class AiFeatureGate
{
    public function __construct(
        private readonly AiUsageTracker $usageTracker,
    ) {
    }

    public function assertEnabled(string $feature): void
    {
        if (! AiSettings::isFeatureEnabled($feature)) {
            throw new AiFeatureDisabledException("ميزة الذكاء الاصطناعي «{$feature}» غير مفعّلة حالياً.");
        }
    }

    public function assertBudget(): void
    {
        $budget = AiSettings::budget();
        $limit = (float) ($budget['monthly_usd'] ?? 0);

        if ($limit <= 0 || ! ($budget['soft_stop'] ?? true)) {
            return;
        }

        if ($this->usageTracker->monthToDateSpendUsd() >= $limit) {
            throw new AiBudgetExceededException('تم تجاوز الميزانية الشهرية للذكاء الاصطناعي. راجع الإعدادات في لوحة التحكم.');
        }
    }

    public function assertRateLimit(?int $userId = null, ?string $ip = null): void
    {
        $limits = AiSettings::rateLimit();
        $userLimit = max(1, (int) ($limits['per_user_per_minute'] ?? 10));
        $ipLimit = max(1, (int) ($limits['per_ip_per_minute'] ?? 20));

        if ($userId) {
            $key = 'ai-user:'.$userId;
            if (RateLimiter::tooManyAttempts($key, $userLimit)) {
                throw new AiFeatureDisabledException('تجاوزت حد الاستخدام اللحظي. حاول بعد دقيقة.');
            }
            RateLimiter::hit($key, 60);
        }

        if ($ip) {
            $key = 'ai-ip:'.$ip;
            if (RateLimiter::tooManyAttempts($key, $ipLimit)) {
                throw new AiFeatureDisabledException('تجاوزت حد الاستخدام من هذا العنوان. حاول بعد دقيقة.');
            }
            RateLimiter::hit($key, 60);
        }
    }

    public function authorize(string $feature, ?int $userId = null, ?string $ip = null): void
    {
        $this->assertEnabled($feature);
        $this->assertBudget();
        $this->assertRateLimit($userId, $ip);
    }
}

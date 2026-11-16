<?php

namespace App\Services\WhatsApp;

use App\Models\UltramsgSendLog;
use App\Support\NotificationSettings;

class WhatsAppThrottleService
{
    /**
     * Calculate staggered dynamic delays for a batch of outgoing WhatsApp messages.
     * Incorporates randomized jitter between [send_delay_min, send_delay_max]
     * and introduces a cooling break (batch_pause_seconds) every batch_size messages.
     *
     * @param int $recipientCount
     * @return array<int, int> Map of recipient index (0-based) to cumulative delay in seconds
     */
    public function calculateStaggeredDelays(int $recipientCount): array
    {
        if ($recipientCount <= 0) {
            return [];
        }

        $limits = NotificationSettings::limits();
        $minDelay = max(1, (int) ($limits['send_delay_min'] ?? 25));
        $maxDelay = max($minDelay, (int) ($limits['send_delay_max'] ?? 45));
        $batchSize = max(0, (int) ($limits['batch_size'] ?? 20));
        $batchPause = max(0, (int) ($limits['batch_pause_seconds'] ?? 180));

        $delays = [];
        $cumulativeSeconds = 0;

        for ($i = 0; $i < $recipientCount; $i++) {
            if ($i === 0) {
                // First message dispatched immediately
                $delays[$i] = 0;
                continue;
            }

            // Random jitter between min and max seconds
            $jitter = random_int($minDelay, $maxDelay);
            $cumulativeSeconds += $jitter;

            // Apply batch cooling period every $batchSize messages
            if ($batchSize > 0 && ($i % $batchSize === 0)) {
                $cumulativeSeconds += $batchPause;
            }

            $delays[$i] = $cumulativeSeconds;
        }

        return $delays;
    }

    /**
     * Get real-time daily quota statistics for WhatsApp sending.
     *
     * @return array{
     *     limit: int,
     *     sent_today: int,
     *     remaining: int|null,
     *     has_limit: bool,
     *     is_exceeded: bool,
     *     percentage: int
     * }
     */
    public function getDailyQuotaStats(): array
    {
        $limits = NotificationSettings::limits();
        $dailyLimit = (int) ($limits['daily_limit'] ?? 0);

        try {
            $sentToday = UltramsgSendLog::query()
                ->whereDate('created_at', today())
                ->count();
        } catch (\Throwable) {
            $sentToday = 0;
        }

        $hasLimit = $dailyLimit > 0;
        $remaining = $hasLimit ? max(0, $dailyLimit - $sentToday) : null;
        $isExceeded = $hasLimit && $sentToday >= $dailyLimit;
        $percentage = $hasLimit ? min(100, (int) round(($sentToday / $dailyLimit) * 100)) : 0;

        return [
            'limit' => $dailyLimit,
            'sent_today' => $sentToday,
            'remaining' => $remaining,
            'has_limit' => $hasLimit,
            'is_exceeded' => $isExceeded,
            'percentage' => $percentage,
        ];
    }

    /**
     * Estimate the time needed to safely deliver a campaign to a given number of recipients.
     *
     * @param int $recipientCount
     * @return array{
     *     min_seconds: int,
     *     max_seconds: int,
     *     avg_seconds: int,
     *     formatted_arabic: string
     * }
     */
    public function estimateCompletionTime(int $recipientCount): array
    {
        if ($recipientCount <= 1) {
            return [
                'min_seconds' => 0,
                'max_seconds' => 0,
                'avg_seconds' => 0,
                'formatted_arabic' => 'فوري (أقل من دقيقة)',
            ];
        }

        $limits = NotificationSettings::limits();
        $minDelay = max(1, (int) ($limits['send_delay_min'] ?? 25));
        $maxDelay = max($minDelay, (int) ($limits['send_delay_max'] ?? 45));
        $batchSize = max(0, (int) ($limits['batch_size'] ?? 20));
        $batchPause = max(0, (int) ($limits['batch_pause_seconds'] ?? 180));

        $messagesWithDelay = $recipientCount - 1;
        $batchPausesCount = ($batchSize > 0) ? intdiv($messagesWithDelay, $batchSize) : 0;
        $totalPauseSeconds = $batchPausesCount * $batchPause;

        $minTotal = ($messagesWithDelay * $minDelay) + $totalPauseSeconds;
        $maxTotal = ($messagesWithDelay * $maxDelay) + $totalPauseSeconds;
        $avgTotal = (int) round(($minTotal + $maxTotal) / 2);

        return [
            'min_seconds' => $minTotal,
            'max_seconds' => $maxTotal,
            'avg_seconds' => $avgTotal,
            'formatted_arabic' => $this->formatDurationArabic($avgTotal),
        ];
    }

    /**
     * Format seconds into a friendly Arabic duration string.
     */
    public function formatDurationArabic(int $totalSeconds): string
    {
        if ($totalSeconds < 60) {
            return 'أقل من دقيقة';
        }

        $minutes = (int) round($totalSeconds / 60);

        if ($minutes < 60) {
            return "حوالي {$minutes} دقيقة";
        }

        $hours = intdiv($minutes, 60);
        $remMinutes = $minutes % 60;

        if ($hours === 1 && $remMinutes === 0) {
            return 'ساعة واحدة تقريباً';
        }

        if ($hours === 1) {
            return "ساعة و {$remMinutes} دقيقة تقريباً";
        }

        if ($hours === 2 && $remMinutes === 0) {
            return 'ساعتين تقريباً';
        }

        if ($hours === 2) {
            return "ساعتين و {$remMinutes} دقيقة تقريباً";
        }

        if ($remMinutes === 0) {
            return "{$hours} ساعات تقريباً";
        }

        return "{$hours} ساعات و {$remMinutes} دقيقة تقريباً";
    }
}

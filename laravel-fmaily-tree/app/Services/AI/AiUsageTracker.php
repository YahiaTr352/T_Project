<?php

namespace App\Services\AI;

use App\Models\AiUsageLog;
use App\Services\AI\DTO\ChatResult;
use App\Services\AI\DTO\EmbeddingResult;
use App\Support\AiSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class AiUsageTracker
{
    public function estimateCost(string $model, int $promptTokens, int $completionTokens = 0): float
    {
        $pricing = config("ai.pricing.{$model}", ['input' => 0.5, 'output' => 1.5]);
        $inputPerMillion = (float) ($pricing['input'] ?? 0);
        $outputPerMillion = (float) ($pricing['output'] ?? 0);

        return ($promptTokens / 1_000_000) * $inputPerMillion
            + ($completionTokens / 1_000_000) * $outputPerMillion;
    }

    public function recordChat(string $feature, ChatResult $result, ?int $userId = null, ?string $sessionKey = null, array $meta = []): AiUsageLog
    {
        $cost = $this->estimateCost($result->model, $result->promptTokens, $result->completionTokens);

        return $this->store([
            'feature' => $feature,
            'provider' => $result->provider,
            'model' => $result->model,
            'prompt_tokens' => $result->promptTokens,
            'completion_tokens' => $result->completionTokens,
            'cost_usd' => $cost,
            'user_id' => $userId,
            'session_key' => $sessionKey,
            'meta' => $meta,
        ]);
    }

    public function recordEmbedding(string $feature, EmbeddingResult $result, ?int $userId = null, ?string $sessionKey = null, array $meta = []): AiUsageLog
    {
        $cost = $this->estimateCost($result->model, $result->promptTokens, 0);

        return $this->store([
            'feature' => $feature,
            'provider' => $result->provider,
            'model' => $result->model,
            'prompt_tokens' => $result->promptTokens,
            'completion_tokens' => 0,
            'cost_usd' => $cost,
            'user_id' => $userId,
            'session_key' => $sessionKey,
            'meta' => $meta,
        ]);
    }


    public function recordTts(string $model, int $characters, ?int $userId = null, ?string $sessionKey = null, array $meta = []): AiUsageLog
    {
        $pricing = config("ai.pricing.{$model}", ['input' => 15.0, 'output' => 0.0]);
        $cost = ($characters / 1_000_000) * (float) ($pricing['input'] ?? 15.0);

        return $this->store([
            'feature' => 'tts',
            'provider' => 'openai',
            'model' => $model,
            'prompt_tokens' => $characters,
            'completion_tokens' => 0,
            'cost_usd' => $cost,
            'user_id' => $userId,
            'session_key' => $sessionKey,
            'meta' => $meta,
        ]);
    }

    public function monthToDateSpendUsd(?Carbon $at = null): float
    {
        if (! Schema::hasTable('ai_usage_logs')) {
            return 0.0;
        }

        $at = $at ?? now();

        return (float) AiUsageLog::query()
            ->whereBetween('created_at', [$at->copy()->startOfMonth(), $at->copy()->endOfMonth()])
            ->sum('cost_usd');
    }

    public function dailySpend(int $days = 30): array
    {
        if (! Schema::hasTable('ai_usage_logs')) {
            return [];
        }

        $from = now()->subDays($days - 1)->startOfDay();

        return AiUsageLog::query()
            ->selectRaw('DATE(created_at) as day, SUM(cost_usd) as cost, SUM(prompt_tokens + completion_tokens) as tokens')
            ->where('created_at', '>=', $from)
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn ($row) => [
                'day' => $row->day,
                'cost' => (float) $row->cost,
                'tokens' => (int) $row->tokens,
            ])
            ->all();
    }

    public function spendByFeature(?Carbon $from = null, ?Carbon $to = null): array
    {
        if (! Schema::hasTable('ai_usage_logs')) {
            return [];
        }

        $from = $from ?? now()->startOfMonth();
        $to = $to ?? now()->endOfMonth();

        return AiUsageLog::query()
            ->selectRaw('feature, SUM(cost_usd) as cost, SUM(prompt_tokens + completion_tokens) as tokens, COUNT(*) as requests')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('feature')
            ->orderByDesc('cost')
            ->get()
            ->map(fn ($row) => [
                'feature' => $row->feature,
                'cost' => (float) $row->cost,
                'tokens' => (int) $row->tokens,
                'requests' => (int) $row->requests,
            ])
            ->all();
    }

    public function budgetRemainingUsd(): float
    {
        $budget = (float) (AiSettings::budget()['monthly_usd'] ?? 0);

        return max(0, $budget - $this->monthToDateSpendUsd());
    }

    private function store(array $data): AiUsageLog
    {
        return AiUsageLog::query()->create($data);
    }
}

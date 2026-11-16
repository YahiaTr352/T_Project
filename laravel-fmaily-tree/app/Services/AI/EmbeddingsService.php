<?php

namespace App\Services\AI;

use App\Models\AiEmbedding;
use App\Services\AI\DTO\EmbeddingResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class EmbeddingsService
{
    public function __construct(
        private readonly AiManager $aiManager,
        private readonly AiUsageTracker $usageTracker,
    ) {
    }

    public function embedText(string $text, string $feature = 'semantic_search', ?string $taskType = null): EmbeddingResult
    {
        return $this->aiManager->withFallback(function ($driver) use ($text, $feature, $taskType) {
            $model = $this->aiManager->modelForFeature($feature, $driver)
                ?? config("ai.models.{$driver->driver()}.embedding");

            $options = ['model' => $model];
            if ($taskType) {
                $options['task_type'] = $taskType;
            }

            $result = $driver->embed($text, $options);
            $this->usageTracker->recordEmbedding($feature, $result);

            return $result;
        }, 'embedding');
    }

    /**
     * @return array{embedding: ?AiEmbedding, wrote: bool, skipped: bool}
     */
    public function upsertResult(Model $model, string $content, string $chunkKey = 'default', string $feature = 'semantic_search'): array
    {
        $content = trim(preg_replace('/\s+/u', ' ', strip_tags($content)) ?? '');
        if ($content === '' || mb_strlen($content) < 10) {
            return ['embedding' => null, 'wrote' => false, 'skipped' => true];
        }

        $hash = hash('sha256', $content);

        $existing = AiEmbedding::query()
            ->where('embeddable_type', $model->getMorphClass())
            ->where('embeddable_id', $model->getKey())
            ->where('chunk_key', $chunkKey)
            ->first();

        if ($existing && $existing->content_hash === $hash) {
            return ['embedding' => $existing, 'wrote' => false, 'skipped' => true];
        }

        $result = $this->embedText($content, $feature, 'RETRIEVAL_DOCUMENT');

        $row = AiEmbedding::query()->updateOrCreate(
            [
                'embeddable_type' => $model->getMorphClass(),
                'embeddable_id' => $model->getKey(),
                'chunk_key' => $chunkKey,
            ],
            [
                'content_hash' => $hash,
                'content' => mb_substr($content, 0, 5000),
                'embedding' => $result->vector,
                'model' => $result->model,
                'dimensions' => $result->dimensions(),
            ]
        );

        return ['embedding' => $row, 'wrote' => true, 'skipped' => false];
    }

    public function upsert(Model $model, string $content, string $chunkKey = 'default', string $feature = 'semantic_search'): ?AiEmbedding
    {
        return $this->upsertResult($model, $content, $chunkKey, $feature)['embedding'];
    }

    public function hasEmbedding(Model $model, string $chunkKey = 'default'): bool
    {
        if (! Schema::hasTable('ai_embeddings')) {
            return false;
        }

        return AiEmbedding::query()
            ->where('embeddable_type', $model->getMorphClass())
            ->where('embeddable_id', $model->getKey())
            ->where('chunk_key', $chunkKey)
            ->exists();
    }

    public function deleteFor(Model $model): void
    {
        if (! Schema::hasTable('ai_embeddings')) {
            return;
        }

        AiEmbedding::query()
            ->where('embeddable_type', $model->getMorphClass())
            ->where('embeddable_id', $model->getKey())
            ->delete();
    }

    /**
     * @return Collection<int, array{embedding: AiEmbedding, score: float}>
     */
    public function search(string $query, int $topK = null, float $minScore = null): Collection
    {
        $topK = $topK ?? (int) config('ai.rag.top_k', 8);
        $minScore = $minScore ?? (float) config('ai.rag.min_score', 0.35);

        if (! Schema::hasTable('ai_embeddings') || AiEmbedding::query()->count() === 0) {
            return collect();
        }

        $queryVector = $this->embedText($query, 'semantic_search', 'RETRIEVAL_QUERY')->vector;

        return AiEmbedding::query()
            ->with('embeddable')
            ->orderBy('id')
            ->get()
            ->map(function (AiEmbedding $embedding) use ($queryVector) {
                return [
                    'embedding' => $embedding,
                    'score' => $this->cosineSimilarity($queryVector, $embedding->embedding ?? []),
                ];
            })
            ->filter(fn (array $row) => $row['score'] >= $minScore)
            ->sortByDesc('score')
            ->take($topK)
            ->values();
    }

    /**
     * @param  array<int, float>  $a
     * @param  array<int, float>  $b
     */
    public function cosineSimilarity(array $a, array $b): float
    {
        $len = min(count($a), count($b));
        if ($len === 0) {
            return 0.0;
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < $len; $i++) {
            $va = (float) $a[$i];
            $vb = (float) $b[$i];
            $dot += $va * $vb;
            $normA += $va * $va;
            $normB += $vb * $vb;
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }
}

<?php

namespace App\Services\AI;

use App\Models\Article;
use App\Models\Person;
use App\Models\Story;
use App\Services\AI\Tools\PersonLookupTool;
use App\Services\AI\Tools\StorySearchTool;

class SemanticSearchService
{
    public function __construct(
        private readonly AiFeatureGate $gate,
        private readonly EmbeddingsService $embeddings,
        private readonly PersonLookupTool $personLookup,
        private readonly StorySearchTool $storySearch,
    ) {
    }

    public function search(string $query, ?int $userId = null, ?string $ip = null, int $limit = 12): array
    {
        $this->gate->authorize('semantic_search', $userId, $ip);

        $query = trim($query);
        $results = [];

        try {
            foreach ($this->embeddings->search($query, $limit) as $hit) {
                $model = $hit['embedding']->embeddable;
                if (! $model) {
                    continue;
                }
                $results[] = $this->formatHit($model, $hit['score']);
            }
        } catch (\Throwable) {
            // fall through to lexical
        }

        if ($results === []) {
            foreach ($this->personLookup->search($query, 6) as $person) {
                $results[] = $this->formatHit($person, 0.5);
            }
            foreach ($this->storySearch->search($query, 6) as $story) {
                $results[] = $this->formatHit($story, 0.45);
            }
        }

        return array_values(array_slice($results, 0, $limit));
    }

    private function formatHit(object $model, float $score): array
    {
        if ($model instanceof Person) {
            return [
                'type' => 'person',
                'id' => $model->id,
                'title' => $model->full_name,
                'snippet' => mb_substr(strip_tags((string) $model->biography), 0, 160),
                'url' => url('/person/'.$model->id),
                'score' => round($score, 4),
            ];
        }

        if ($model instanceof Story) {
            return [
                'type' => 'story',
                'id' => $model->id,
                'title' => $model->title,
                'snippet' => mb_substr(strip_tags((string) $model->content), 0, 160),
                'url' => route('public.stories.show', $model),
                'score' => round($score, 4),
            ];
        }

        if ($model instanceof Article) {
            return [
                'type' => 'article',
                'id' => $model->id,
                'title' => $model->title,
                'snippet' => mb_substr(strip_tags((string) $model->content), 0, 160),
                'url' => url('/gallery/articles/'.$model->id),
                'score' => round($score, 4),
            ];
        }

        return [
            'type' => class_basename($model),
            'id' => $model->id ?? null,
            'title' => (string) ($model->title ?? $model->name ?? ''),
            'snippet' => '',
            'url' => null,
            'score' => round($score, 4),
        ];
    }
}

<?php

namespace App\Jobs;

use App\Models\Article;
use App\Services\AI\Content\ArticleContentBuilder;
use App\Services\AI\EmbeddingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class IndexArticleEmbeddingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 8;

    public array $backoff = [30, 60, 90, 120, 180, 300];

    public function __construct(public Article $article)
    {
    }

    /**
     * @return array{wrote: bool, skipped: bool}
     */
    public function handle(EmbeddingsService $embeddings, ArticleContentBuilder $builder): array
    {
        if (($this->article->status ?? null) === 'draft') {
            return ['wrote' => false, 'skipped' => true];
        }

        $content = $builder->build($this->article);
        $result = $embeddings->upsertResult($this->article, $content, 'article');

        return [
            'wrote' => (bool) $result['wrote'],
            'skipped' => (bool) $result['skipped'],
        ];
    }
}

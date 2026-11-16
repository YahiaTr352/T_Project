<?php

namespace App\Observers;

use App\Jobs\IndexArticleEmbeddingJob;
use App\Models\Article;
use App\Services\AI\EmbeddingsService;

class ArticleObserver
{
    public function saved(Article $article): void
    {
        IndexArticleEmbeddingJob::dispatch($article);
    }

    public function deleted(Article $article): void
    {
        app(EmbeddingsService::class)->deleteFor($article);
    }
}

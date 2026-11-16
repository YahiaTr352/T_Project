<?php

namespace App\Observers;

use App\Jobs\IndexStoryEmbeddingJob;
use App\Models\Story;
use App\Services\AI\EmbeddingsService;

class StoryObserver
{
    public function saved(Story $story): void
    {
        IndexStoryEmbeddingJob::dispatch($story);
    }

    public function deleted(Story $story): void
    {
        app(EmbeddingsService::class)->deleteFor($story);
    }
}

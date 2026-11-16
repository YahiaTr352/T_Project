<?php

namespace App\Jobs;

use App\Models\Story;
use App\Services\AI\Content\StoryContentBuilder;
use App\Services\AI\EmbeddingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class IndexStoryEmbeddingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 8;

    public array $backoff = [30, 60, 90, 120, 180, 300];

    public function __construct(public Story $story)
    {
    }

    /**
     * @return array{wrote: bool, skipped: bool}
     */
    public function handle(EmbeddingsService $embeddings, StoryContentBuilder $builder): array
    {
        $content = $builder->build($this->story);
        $result = $embeddings->upsertResult($this->story, $content, 'story');

        return [
            'wrote' => (bool) $result['wrote'],
            'skipped' => (bool) $result['skipped'],
        ];
    }
}

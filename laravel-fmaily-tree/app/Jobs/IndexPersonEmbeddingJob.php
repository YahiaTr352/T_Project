<?php

namespace App\Jobs;

use App\Models\Person;
use App\Services\AI\Content\PersonContentBuilder;
use App\Services\AI\EmbeddingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class IndexPersonEmbeddingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 8;

    public array $backoff = [30, 60, 90, 120, 180, 300];

    public function __construct(public Person $person)
    {
    }

    /**
     * @return array{wrote: bool, skipped: bool}
     */
    public function handle(EmbeddingsService $embeddings, PersonContentBuilder $builder): array
    {
        $content = $builder->build($this->person);
        $result = $embeddings->upsertResult($this->person, $content, 'profile');

        return [
            'wrote' => (bool) $result['wrote'],
            'skipped' => (bool) $result['skipped'],
        ];
    }
}

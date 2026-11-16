<?php

namespace Tests\Unit;

use App\Services\AI\EmbeddingsService;
use Tests\TestCase;

class EmbeddingsCosineTest extends TestCase
{
    public function test_identical_vectors_score_one(): void
    {
        $service = app(EmbeddingsService::class);
        $v = [0.1, 0.2, 0.3, 0.4];

        $this->assertEqualsWithDelta(1.0, $service->cosineSimilarity($v, $v), 0.0001);
    }

    public function test_orthogonal_vectors_near_zero(): void
    {
        $service = app(EmbeddingsService::class);

        $this->assertEqualsWithDelta(0.0, $service->cosineSimilarity([1, 0], [0, 1]), 0.0001);
    }
}

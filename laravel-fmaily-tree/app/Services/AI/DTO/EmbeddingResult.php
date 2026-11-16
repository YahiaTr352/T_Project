<?php

namespace App\Services\AI\DTO;

class EmbeddingResult
{
    /**
     * @param  array<int, float>  $vector
     */
    public function __construct(
        public readonly array $vector,
        public readonly string $provider,
        public readonly string $model,
        public readonly int $promptTokens = 0,
    ) {
    }

    public function dimensions(): int
    {
        return count($this->vector);
    }
}

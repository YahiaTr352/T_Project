<?php

namespace App\Services\AI\DTO;

class ChatResult
{
    /**
     * @param array<int, array{id: string, type: string, function: array{name: string, arguments: string}}> $toolCalls
     */
    public function __construct(
        public readonly string $content,
        public readonly string $provider,
        public readonly string $model,
        public readonly int $promptTokens = 0,
        public readonly int $completionTokens = 0,
        public readonly array $raw = [],
        public readonly array $toolCalls = [],
    ) {
    }
}

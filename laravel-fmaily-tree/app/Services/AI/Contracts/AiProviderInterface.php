<?php

namespace App\Services\AI\Contracts;

use App\Services\AI\DTO\ChatMessage;
use App\Services\AI\DTO\ChatResult;
use App\Services\AI\DTO\EmbeddingResult;

interface AiProviderInterface
{
    public function driver(): string;

    /**
     * @param  array<int, ChatMessage>  $messages
     * @param  array<string, mixed>  $options
     */
    public function chat(array $messages, array $options = []): ChatResult;

    public function embed(string $text, array $options = []): EmbeddingResult;

    public function transcribe(string $absolutePath, array $options = []): string;

    public function testConnection(): array;
}

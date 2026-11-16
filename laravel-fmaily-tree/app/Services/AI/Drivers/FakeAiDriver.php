<?php

namespace App\Services\AI\Drivers;

use App\Services\AI\Contracts\AiProviderInterface;
use App\Services\AI\DTO\ChatMessage;
use App\Services\AI\DTO\ChatResult;
use App\Services\AI\DTO\EmbeddingResult;

class FakeAiDriver implements AiProviderInterface
{
    public function driver(): string
    {
        return 'fake';
    }

    public function chat(array $messages, array $options = []): ChatResult
    {
        $last = collect($messages)->last(function ($m) {
            if ($m instanceof ChatMessage) {
                return $m->role === 'user';
            }
            if (is_array($m)) {
                return ($m['role'] ?? '') === 'user';
            }
            return false;
        });

        $content = '';
        if ($last instanceof ChatMessage) {
            $content = (string) $last->content;
        } elseif (is_array($last)) {
            $content = (string) ($last['content'] ?? '');
        }

        return new ChatResult(
            content: 'إجابة تجريبية عن: '.mb_substr($content, 0, 80),
            provider: 'fake',
            model: 'fake-model',
            promptTokens: 10,
            completionTokens: 20,
        );
    }

    public function embed(string $text, array $options = []): EmbeddingResult
    {
        // Deterministic tiny vector from text hash
        $seed = crc32($text);
        $vector = [];
        for ($i = 0; $i < 8; $i++) {
            $vector[] = (($seed >> $i) & 1) ? 0.5 : -0.5;
        }

        return new EmbeddingResult($vector, 'fake', 'fake-embed', 5);
    }

    public function transcribe(string $absolutePath, array $options = []): string
    {
        return 'نص تفريغ تجريبي';
    }

    public function testConnection(): array
    {
        return ['ok' => true, 'message' => 'fake ok', 'details' => []];
    }
}

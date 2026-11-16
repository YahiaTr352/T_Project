<?php

namespace App\Services\AI\Drivers;

use App\Services\AI\Contracts\AiProviderInterface;
use App\Services\AI\DTO\ChatMessage;
use App\Services\AI\DTO\ChatResult;
use App\Services\AI\DTO\EmbeddingResult;
use App\Services\AI\Exceptions\AiException;
use App\Services\AI\Support\ProviderHttpError;
use Illuminate\Support\Facades\Http;

class AnthropicDriver implements AiProviderInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly array $models = [],
    ) {
    }

    public function driver(): string
    {
        return 'anthropic';
    }

    public function chat(array $messages, array $options = []): ChatResult
    {
        $model = $options['model'] ?? $this->models['chat'] ?? config('ai.models.anthropic.chat');

        $system = '';
        $chatMessages = [];

        foreach ($messages as $message) {
            /** @var ChatMessage $message */
            if ($message->role === 'system') {
                $system .= ($system === '' ? '' : "\n\n").$message->content;
                continue;
            }

            $chatMessages[] = [
                'role' => $message->role === 'assistant' ? 'assistant' : 'user',
                'content' => $message->content,
            ];
        }

        $payload = [
            'model' => $model,
            'max_tokens' => (int) ($options['max_tokens'] ?? 2048),
            'messages' => $chatMessages,
        ];

        if ($system !== '') {
            $payload['system'] = $system;
        }

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
        ])
            ->timeout(config('ai.timeout', 60))
            ->acceptJson()
            ->post('https://api.anthropic.com/v1/messages', $payload);

        if (! $response->successful()) {
            ProviderHttpError::throw('Anthropic', $response, 'الشات');
        }

        $json = $response->json();
        $text = collect(data_get($json, 'content', []))
            ->where('type', 'text')
            ->pluck('text')
            ->implode("\n");

        return new ChatResult(
            content: $text,
            provider: $this->driver(),
            model: (string) data_get($json, 'model', $model),
            promptTokens: (int) data_get($json, 'usage.input_tokens', 0),
            completionTokens: (int) data_get($json, 'usage.output_tokens', 0),
            raw: is_array($json) ? $json : [],
        );
    }

    public function embed(string $text, array $options = []): EmbeddingResult
    {
        throw new AiException('Anthropic لا يدعم embeddings مباشرة. استخدم OpenAI أو Gemini للفهرسة.');
    }

    public function transcribe(string $absolutePath, array $options = []): string
    {
        throw new AiException('Anthropic لا يدعم التفريغ الصوتي. استخدم OpenAI Whisper أو Gemini.');
    }

    public function testConnection(): array
    {
        try {
            $result = $this->chat([
                new ChatMessage('user', 'قل مرحبا بكلمة واحدة فقط.'),
            ], ['max_tokens' => 16]);

            return [
                'ok' => true,
                'message' => 'اتصال Anthropic ناجح.',
                'details' => ['reply' => mb_substr($result->content, 0, 80)],
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'فشل الاتصال بـ Anthropic: '.$e->getMessage(),
                'details' => [],
            ];
        }
    }
}

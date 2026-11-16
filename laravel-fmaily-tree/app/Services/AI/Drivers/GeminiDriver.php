<?php

namespace App\Services\AI\Drivers;

use App\Services\AI\Contracts\AiProviderInterface;
use App\Services\AI\DTO\ChatMessage;
use App\Services\AI\DTO\ChatResult;
use App\Services\AI\DTO\EmbeddingResult;
use App\Services\AI\Exceptions\AiException;
use App\Services\AI\Support\ProviderHttpError;
use Illuminate\Support\Facades\Http;

class GeminiDriver implements AiProviderInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly array $models = [],
    ) {
    }

    public function driver(): string
    {
        return 'gemini';
    }

    public function chat(array $messages, array $options = []): ChatResult
    {
        $model = $options['model'] ?? $this->models['chat'] ?? config('ai.models.gemini.chat');

        $system = '';
        $contents = [];

        foreach ($messages as $message) {
            /** @var ChatMessage $message */
            if ($message->role === 'system') {
                $system .= ($system === '' ? '' : "\n\n").$message->content;
                continue;
            }

            $contents[] = [
                'role' => $message->role === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $message->content]],
            ];
        }

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => $options['temperature'] ?? 0.4,
                'maxOutputTokens' => (int) ($options['max_tokens'] ?? 2048),
            ],
        ];

        if (!empty($options['tools'])) {
            $payload['tools'] = $options['tools'];
        }

        if ($system !== '') {
            $payload['systemInstruction'] = [
                'parts' => [['text' => $system]],
            ];
        }

        $url = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
            urlencode($model),
            urlencode($this->apiKey)
        );

        $response = Http::timeout(config('ai.timeout', 60))
            ->acceptJson()
            ->post($url, $payload);

        if (! $response->successful()) {
            ProviderHttpError::throw('Gemini', $response, 'الشات');
        }

        $json = $response->json();
        $text = (string) data_get($json, 'candidates.0.content.parts.0.text', '');

        $toolCalls = [];
        $parts = data_get($json, 'candidates.0.content.parts', []);
        if (is_array($parts)) {
            foreach ($parts as $part) {
                if (!empty($part['functionCall'])) {
                    $toolCalls[] = [
                        'id' => 'call_' . \Illuminate\Support\Str::random(10),
                        'type' => 'function',
                        'function' => [
                            'name' => $part['functionCall']['name'] ?? '',
                            'arguments' => json_encode($part['functionCall']['args'] ?? [], JSON_UNESCAPED_UNICODE),
                        ],
                    ];
                }
            }
        }

        return new ChatResult(
            content: $text,
            provider: $this->driver(),
            model: (string) $model,
            promptTokens: (int) data_get($json, 'usageMetadata.promptTokenCount', 0),
            completionTokens: (int) data_get($json, 'usageMetadata.candidatesTokenCount', 0),
            raw: is_array($json) ? $json : [],
            toolCalls: $toolCalls,
        );
    }

    public function embed(string $text, array $options = []): EmbeddingResult
    {
        $model = $options['model'] ?? $this->models['embedding'] ?? config('ai.models.gemini.embedding');
        $model = $this->normalizeEmbeddingModel((string) $model);

        $url = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:embedContent?key=%s',
            urlencode($model),
            urlencode($this->apiKey)
        );

        $payload = [
            'model' => 'models/'.$model,
            'content' => [
                'parts' => [['text' => mb_substr($text, 0, 8000)]],
            ],
            'taskType' => $options['task_type'] ?? 'RETRIEVAL_DOCUMENT',
        ];

        // gemini-embedding-001 supports outputDimensionality; keep a stable size for cosine search
        if (str_starts_with($model, 'gemini-embedding-001')) {
            $payload['outputDimensionality'] = (int) ($options['output_dimensionality'] ?? 768);
        }

        $response = Http::timeout(config('ai.timeout', 60))
            ->acceptJson()
            ->post($url, $payload);

        if (! $response->successful()) {
            ProviderHttpError::throw('Gemini', $response, 'الـ embeddings');
        }

        $json = $response->json();
        $vector = data_get($json, 'embedding.values', []);

        if (! is_array($vector) || $vector === []) {
            throw new AiException('Gemini returned an empty embedding vector.');
        }

        return new EmbeddingResult(
            vector: array_map('floatval', $vector),
            provider: $this->driver(),
            model: (string) $model,
            promptTokens: 0,
        );
    }

    /**
     * Map retired Gemini embedding model IDs to a supported replacement.
     */
    private function normalizeEmbeddingModel(string $model): string
    {
        $model = trim($model);
        $model = preg_replace('#^models/#', '', $model) ?: $model;

        $retired = [
            'text-embedding-004' => 'gemini-embedding-001',
            'embedding-001' => 'gemini-embedding-001',
            'models/embedding-001' => 'gemini-embedding-001',
            'text-embedding-005' => 'gemini-embedding-001',
        ];

        return $retired[$model] ?? ($model !== '' ? $model : 'gemini-embedding-001');
    }

    public function transcribe(string $absolutePath, array $options = []): string
    {
        if (! is_file($absolutePath)) {
            throw new AiException('Audio file not found for transcription.');
        }

        $model = $options['model'] ?? $this->models['transcription'] ?? config('ai.models.gemini.transcription');
        $mime = mime_content_type($absolutePath) ?: 'audio/mpeg';
        $b64 = base64_encode((string) file_get_contents($absolutePath));

        $url = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
            urlencode($model),
            urlencode($this->apiKey)
        );

        $response = Http::timeout(max(120, (int) config('ai.timeout', 60)))
            ->acceptJson()
            ->post($url, [
                'contents' => [[
                    'role' => 'user',
                    'parts' => [
                        ['text' => 'فرّغ هذا الملف الصوتي إلى نص عربي بدقة، وأعد النص فقط دون شرح.'],
                        [
                            'inline_data' => [
                                'mime_type' => $mime,
                                'data' => $b64,
                            ],
                        ],
                    ],
                ]],
            ]);

        if (! $response->successful()) {
            ProviderHttpError::throw('Gemini', $response, 'التفريغ الصوتي');
        }

        return (string) data_get($response->json(), 'candidates.0.content.parts.0.text', '');
    }

    public function testConnection(): array
    {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models?key='.urlencode($this->apiKey);

        $response = Http::timeout(20)->acceptJson()->get($url);

        if (! $response->successful()) {
            return [
                'ok' => false,
                'message' => 'فشل الاتصال بـ Gemini: '.$response->status(),
                'details' => $response->json(),
            ];
        }

        return [
            'ok' => true,
            'message' => 'اتصال Gemini ناجح.',
            'details' => [
                'models_sample' => collect(data_get($response->json(), 'models', []))
                    ->take(5)
                    ->pluck('name')
                    ->values(),
            ],
        ];
    }
}

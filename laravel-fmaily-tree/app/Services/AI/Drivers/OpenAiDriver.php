<?php

namespace App\Services\AI\Drivers;

use App\Services\AI\Contracts\AiProviderInterface;
use App\Services\AI\DTO\ChatMessage;
use App\Services\AI\DTO\ChatResult;
use App\Services\AI\DTO\EmbeddingResult;
use App\Services\AI\Exceptions\AiException;
use App\Services\AI\Support\ProviderHttpError;
use Illuminate\Support\Facades\Http;

class OpenAiDriver implements AiProviderInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly array $models = [],
    ) {
    }

    public function driver(): string
    {
        return 'openai';
    }

    public function chat(array $messages, array $options = []): ChatResult
    {
        $model = $options['model'] ?? $this->models['chat'] ?? config('ai.models.openai.chat');

        $isReasoningModel = (bool) preg_match('/^(o[1-4]|gpt-5)/i', (string) $model);

        $formattedMessages = array_map(
            function ($m) {
                if (is_array($m)) {
                    return $m;
                }
                if ($m instanceof ChatMessage) {
                    return $m->toArray();
                }
                return [
                    'role' => $m->role,
                    'content' => isset($m->content) ? mb_convert_encoding((string) $m->content, 'UTF-8', 'UTF-8') : null,
                ];
            },
            $messages
        );

        $payload = [
            'model' => $model,
            'messages' => $formattedMessages,
        ];

        // Reasoning models (o1, o3, etc.) do not support temperature
        if (! $isReasoningModel) {
            $payload['temperature'] = isset($options['temperature']) ? (float) $options['temperature'] : 0.4;
        }

        // Modern OpenAI API standard: Use max_completion_tokens
        $maxTokens = $options['max_completion_tokens'] ?? $options['max_tokens'] ?? null;
        if (! empty($maxTokens)) {
            $payload['max_completion_tokens'] = (int) $maxTokens;
        }

        // Tools / Function calling support
        if (!empty($options['tools'])) {
            $payload['tools'] = $options['tools'];
            if (isset($options['tool_choice'])) {
                $payload['tool_choice'] = $options['tool_choice'];
            }
        }

        $response = Http::withToken($this->apiKey)
            ->timeout(config('ai.timeout', 60))
            ->acceptJson()
            ->post('https://api.openai.com/v1/chat/completions', $payload);

        // التوافق التلقائي الذكي: إذا كان الموديل قديماً واشتكى من max_completion_tokens يعود إلى max_tokens
        if ($response->status() === 400 && str_contains($response->body(), 'max_completion_tokens') && ! empty($maxTokens)) {
            unset($payload['max_completion_tokens']);
            $payload['max_tokens'] = (int) $maxTokens;
            $response = Http::withToken($this->apiKey)
                ->timeout(config('ai.timeout', 60))
                ->acceptJson()
                ->post('https://api.openai.com/v1/chat/completions', $payload);
        }

        if (! $response->successful()) {
            ProviderHttpError::throw('OpenAI', $response, 'الشات');
        }

        $json = $response->json();

        $rawToolCalls = data_get($json, 'choices.0.message.tool_calls', []);
        $toolCalls = is_array($rawToolCalls) ? $rawToolCalls : [];

        return new ChatResult(
            content: (string) data_get($json, 'choices.0.message.content', ''),
            provider: $this->driver(),
            model: (string) data_get($json, 'model', $model),
            promptTokens: (int) data_get($json, 'usage.prompt_tokens', 0),
            completionTokens: (int) data_get($json, 'usage.completion_tokens', 0),
            raw: is_array($json) ? $json : [],
            toolCalls: $toolCalls,
        );
    }

    /**
     * Real-time streaming from OpenAI using Server-Sent Events.
     *
     * @param array<int, mixed> $messages
     * @param array<string, mixed> $options
     * @param callable(string): void $onDelta
     * @return array{content: string, tool_calls: array}
     */
    public function chatStream(array $messages, array $options, callable $onDelta): array
    {
        $model = $options['model'] ?? $this->models['chat'] ?? config('ai.models.openai.chat');
        $isReasoningModel = (bool) preg_match('/^(o[1-4]|gpt-5)/i', (string) $model);

        $formattedMessages = array_map(
            function ($m) {
                if (is_array($m)) {
                    return $m;
                }
                if ($m instanceof ChatMessage) {
                    return $m->toArray();
                }
                return [
                    'role' => $m->role,
                    'content' => isset($m->content) ? mb_convert_encoding((string) $m->content, 'UTF-8', 'UTF-8') : null,
                ];
            },
            $messages
        );

        $payload = [
            'model' => $model,
            'messages' => $formattedMessages,
            'stream' => true,
        ];

        if (! $isReasoningModel) {
            $payload['temperature'] = isset($options['temperature']) ? (float) $options['temperature'] : 0.4;
        }

        $maxTokens = $options['max_completion_tokens'] ?? $options['max_tokens'] ?? null;
        if (! empty($maxTokens)) {
            $payload['max_completion_tokens'] = (int) $maxTokens;
        }

        if (!empty($options['tools'])) {
            $payload['tools'] = $options['tools'];
            if (isset($options['tool_choice'])) {
                $payload['tool_choice'] = $options['tool_choice'];
            }
        }

        $fullContent = '';
        $toolCallsAcc = [];

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: text/event-stream',
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 90);

        $buffer = '';
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use (&$buffer, &$fullContent, &$toolCallsAcc, $onDelta) {
            $buffer .= $data;
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);

                if ($line === '' || str_starts_with($line, ':')) {
                    continue;
                }

                if (str_starts_with($line, 'data: ')) {
                    $jsonStr = substr($line, 6);
                    if ($jsonStr === '[DONE]') {
                        break;
                    }

                    $parsed = json_decode($jsonStr, true);
                    if (!is_array($parsed)) {
                        continue;
                    }

                    $deltaContent = data_get($parsed, 'choices.0.delta.content');
                    if (is_string($deltaContent) && $deltaContent !== '') {
                        $fullContent .= $deltaContent;
                        $onDelta($deltaContent);
                    }

                    $deltaTools = data_get($parsed, 'choices.0.delta.tool_calls');
                    if (is_array($deltaTools)) {
                        foreach ($deltaTools as $dt) {
                            $idx = $dt['index'] ?? 0;
                            if (!isset($toolCallsAcc[$idx])) {
                                $toolCallsAcc[$idx] = [
                                    'id' => $dt['id'] ?? '',
                                    'type' => 'function',
                                    'function' => [
                                        'name' => $dt['function']['name'] ?? '',
                                        'arguments' => '',
                                    ],
                                ];
                            }
                            if (!empty($dt['id'])) {
                                $toolCallsAcc[$idx]['id'] = $dt['id'];
                            }
                            if (!empty($dt['function']['name'])) {
                                if (empty($toolCallsAcc[$idx]['function']['name'])) {
                                    $toolCallsAcc[$idx]['function']['name'] = $dt['function']['name'];
                                } elseif ($toolCallsAcc[$idx]['function']['name'] !== $dt['function']['name'] && !str_contains($toolCallsAcc[$idx]['function']['name'], $dt['function']['name'])) {
                                    $toolCallsAcc[$idx]['function']['name'] .= $dt['function']['name'];
                                }
                            }
                            if (isset($dt['function']['arguments'])) {
                                $toolCallsAcc[$idx]['function']['arguments'] .= $dt['function']['arguments'];
                            }
                        }
                    }
                }
            }
            return strlen($data);
        });

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($httpCode >= 400 || $curlErr) {
            throw new AiException("فشل البث من OpenAI: HTTP {$httpCode} - {$curlErr}");
        }

        return [
            'content' => $fullContent,
            'tool_calls' => array_values($toolCallsAcc),
        ];
    }

    public function embed(string $text, array $options = []): EmbeddingResult
    {
        $model = $options['model'] ?? $this->models['embedding'] ?? config('ai.models.openai.embedding');

        $response = Http::withToken($this->apiKey)
            ->timeout(config('ai.timeout', 60))
            ->acceptJson()
            ->post('https://api.openai.com/v1/embeddings', [
                'model' => $model,
                'input' => mb_substr($text, 0, 8000),
            ]);

        if (! $response->successful()) {
            ProviderHttpError::throw('OpenAI', $response, 'الـ embeddings');
        }

        $json = $response->json();
        $vector = data_get($json, 'data.0.embedding', []);

        if (! is_array($vector) || $vector === []) {
            throw new AiException('OpenAI returned an empty embedding vector.');
        }

        return new EmbeddingResult(
            vector: array_map('floatval', $vector),
            provider: $this->driver(),
            model: (string) $model,
            promptTokens: (int) data_get($json, 'usage.prompt_tokens', 0),
        );
    }

    public function transcribe(string $absolutePath, array $options = []): string
    {
        $model = $options['model'] ?? $this->models['transcription'] ?? config('ai.models.openai.transcription');

        if (! is_file($absolutePath)) {
            throw new AiException('Audio file not found for transcription.');
        }

        $filename = $options['filename'] ?? basename($absolutePath);
        if (! preg_match('/\.(flac|m4a|mp3|mp4|mpeg|mpga|oga|ogg|wav|webm)$/i', $filename)) {
            $filename .= '.m4a';
        }

        $response = Http::withToken($this->apiKey)
            ->timeout(max(120, (int) config('ai.timeout', 60)))
            ->attach('file', file_get_contents($absolutePath), $filename)
            ->post('https://api.openai.com/v1/audio/transcriptions', [
                'model' => $model,
                'language' => $options['language'] ?? 'ar',
            ]);

        if (! $response->successful()) {
            ProviderHttpError::throw('OpenAI', $response, 'التفريغ الصوتي');
        }

        return (string) data_get($response->json(), 'text', '');
    }


    public function textToSpeech(string $text, array $options = []): string
    {
        $model = $options['model'] ?? $this->models['tts'] ?? config('ai.models.openai.tts', 'tts-1');
        $voice = $options['voice'] ?? config('ai.default_tts_voice', 'onyx');
        $speed = (float) ($options['speed'] ?? 1.0);

        $response = Http::withToken($this->apiKey)
            ->timeout(max(60, (int) config('ai.timeout', 60)))
            ->post('https://api.openai.com/v1/audio/speech', [
                'model' => $model,
                'input' => $text,
                'voice' => $voice,
                'speed' => $speed,
                'response_format' => 'mp3',
            ]);

        if (! $response->successful()) {
            ProviderHttpError::throw('OpenAI', $response, 'توليد الصوت');
        }

        return $response->body();
    }

    public function testConnection(): array
    {
        $response = Http::withToken($this->apiKey)
            ->timeout(20)
            ->acceptJson()
            ->get('https://api.openai.com/v1/models');

        if (! $response->successful()) {
            return [
                'ok' => false,
                'message' => 'فشل الاتصال بـ OpenAI: '.$response->status(),
                'details' => $response->json(),
            ];
        }

        return [
            'ok' => true,
            'message' => 'اتصال OpenAI ناجح.',
            'details' => ['models_sample' => collect(data_get($response->json(), 'data', []))->take(5)->pluck('id')->values()],
        ];
    }
}

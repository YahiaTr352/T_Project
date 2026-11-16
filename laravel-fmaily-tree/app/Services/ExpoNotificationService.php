<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExpoNotificationService
{
    private string $baseUrl = 'https://exp.host/--/api/v2/push/send';

    /**
     * Send a push notification to one or multiple Expo push tokens.
     *
     * @param string|array $to Single token or array of tokens
     * @param string $title Notification title
     * @param string $body Notification body content
     * @param array $data Custom JSON payload payload
     * @param string $sound Sound setting (default 'default')
     * @param int|null $badge Badge count for app icon
     * @return array Response summary of successes and errors
     */
    public function send(
        string|array $to,
        string $title,
        string $body,
        array $data = [],
        string $sound = 'default',
        ?int $badge = null
    ): array {
        $tokens = is_array($to) ? $to : [$to];
        
        // Filter out invalid token patterns to prevent unneeded API roundtrips
        $validTokens = array_values(array_filter($tokens, function ($token) {
            return is_string($token) && (
                str_starts_with($token, 'ExponentPushToken[') ||
                str_starts_with($token, 'ExpoPushToken[')
            );
        }));

        if (empty($validTokens)) {
            Log::channel('single')->warning('[ExpoPush] No valid Expo Push Tokens provided.');
            return [
                'success' => false,
                'sent_count' => 0,
                'errors' => ['No valid tokens found.']
            ];
        }

        Log::channel('single')->info('[ExpoPush] Dispatching push notifications', [
            'total_tokens' => count($tokens),
            'valid_tokens' => count($validTokens),
            'title' => $title,
            'body_length' => mb_strlen($body),
        ]);

        // Expo allows sending up to 100 notifications per HTTP request. Chunk them.
        $chunks = array_chunk($validTokens, 100);
        $totalSent = 0;
        $allErrors = [];
        $tickets = [];

        foreach ($chunks as $chunkIndex => $tokenChunk) {
            $payload = [];
            foreach ($tokenChunk as $token) {
                $item = [
                    'to' => $token,
                    'title' => $title,
                    'body' => $body,
                    'sound' => $sound,
                ];
                if (!empty($data)) {
                    $item['data'] = $data;
                }
                if ($badge !== null) {
                    $item['badge'] = $badge;
                }
                $payload[] = $item;
            }

            try {
                $response = Http::withHeaders([
                    'Accept' => 'application/json',
                    'Accept-Encoding' => 'gzip, deflate',
                    'Content-Type' => 'application/json',
                ])
                ->timeout(15)
                ->post($this->baseUrl, $payload);

                if ($response->successful()) {
                    $result = $response->json();
                    $responseData = $result['data'] ?? [];
                    
                    foreach ($responseData as $index => $ticket) {
                        if (isset($ticket['status']) && $ticket['status'] === 'ok') {
                            $totalSent++;
                            $tickets[] = $ticket['id'] ?? null;
                        } else {
                            $errorMsg = $ticket['message'] ?? 'Unknown error';
                            $allErrors[] = "Token index {$index}: {$errorMsg}";
                            Log::channel('single')->warning("[ExpoPush] Ticket error for token {$tokenChunk[$index]}: {$errorMsg}");
                        }
                    }
                } else {
                    $error = "HTTP Request failed with status code " . $response->status();
                    $allErrors[] = $error;
                    Log::channel('single')->error("[ExpoPush] Chunk {$chunkIndex} failed: {$error}", [
                        'response' => $response->body()
                    ]);
                }
            } catch (\Throwable $e) {
                $allErrors[] = "Exception: " . $e->getMessage();
                Log::channel('single')->error("[ExpoPush] Exception in chunk {$chunkIndex}: " . $e->getMessage(), [
                    'trace' => mb_substr($e->getTraceAsString(), 0, 500)
                ]);
            }
        }

        return [
            'success' => count($allErrors) === 0,
            'sent_count' => $totalSent,
            'tickets' => $tickets,
            'errors' => $allErrors
        ];
    }
}

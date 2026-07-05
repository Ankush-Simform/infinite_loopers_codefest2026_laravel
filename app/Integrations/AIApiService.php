<?php

declare(strict_types=1);

namespace App\Integrations;

use App\Contracts\AiServiceContract;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class AIApiService implements AiServiceContract
{
    /**
     * Call the AI backend to generate a response for a chat message.
     */
    public function generateChatResponse(string $message, array $history = [], ?string $reportUrl = null): string
    {
        $baseUrl = config('services.ai.base_url', 'http://127.0.0.1:5000/api/v1');
        $timeout = (int) config('services.ai.timeout', 30);

        try {
            Log::info('Sending request to AI service', [
                'url' => $baseUrl.'/chat',
                'message_length' => strlen($message),
                'report_url' => $reportUrl,
            ]);

            $response = Http::timeout($timeout)
                ->post($baseUrl.'/chat', [
                    'message' => $message,
                    'history' => $history,
                    'report_url' => $reportUrl,
                ]);

            if ($response->successful()) {
                $responseText = $response->json('response') ?? $response->json('message') ?? '';
                if ($responseText !== '') {
                    Log::info('AI service returned response successfully');

                    return $responseText;
                }
            }

            Log::warning('AI service returned non-successful response or empty text', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to communicate with AI service', [
                'error' => $e->getMessage(),
            ]);
        }

        // Return a professional fallback response if AI backend is down/unavailable
        return "I've received your message, but I'm currently unable to reach my medical analysis engine. Please try again in a few moments.";
    }

    /**
     * Stream an AI chat response. Conversation continuity is maintained by the AI
     * service itself via $sessionId, so no history is sent on each call.
     */
    public function streamChatResponse(
        int $userId,
        int $sessionId,
        string $message,
        array $attachments,
        callable $callback
    ): void {
        $baseUrl = config('services.ai.base_url', 'http://127.0.0.1:5000/api/v1');
        $sharedSecret = config('services.ai.shared_secret', 'super-secret-key-123!');
        $timeout = (int) config('services.ai.timeout', 30);
        $url = $baseUrl.'/chat/report-assistant';

        Log::info('Initiating streaming request to AI service', [
            'url' => $url,
            'user_id' => $userId,
            'session_id' => $sessionId,
            'attachments_count' => count($attachments),
        ]);

        try {
            $guzzle = new Client;
            $response = $guzzle->post($url, [
                'headers' => [
                    'Authorization' => 'Bearer '.$sharedSecret,
                    'Content-Type' => 'application/json',
                    'Accept' => 'text/event-stream',
                ],
                'json' => [
                    'user_id' => $userId,
                    'session_id' => $sessionId,
                    'user_text' => $message,
                    'attachments' => $attachments,
                ],
                'stream' => true,
                'timeout' => $timeout,
            ]);

            $body = $response->getBody();
            $isSse = str_contains(strtolower($response->getHeaderLine('Content-Type')), 'text/event-stream');

            if (! $isSse) {
                // Raw stream fallback — pass bytes straight through to the client.
                while (! $body->eof()) {
                    $chunk = $body->read(128);
                    if ($chunk !== '') {
                        $callback($chunk);
                    }
                }

                return;
            }

            $buffer = '';
            while (! $body->eof()) {
                $buffer .= $body->read(128); // read small blocks for responsive streaming

                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 1);
                    $this->emitSseLine($line, $callback);
                }
            }

            $this->emitSseLine($buffer, $callback);
        } catch (\Throwable $e) {
            Log::error('Error during AI streaming', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $callback("I'm currently unable to reach my medical analysis engine. Please try again in a few moments.");
        }
    }

    /**
     * Extract the text payload from a single SSE line (a "data: ..." frame, or a
     * plain line for backends that don't send an "event:"/"data:" prefix) and hand
     * it to the callback as plain text, so the caller never deals with SSE framing.
     */
    private function emitSseLine(string $line, callable $callback): void
    {
        $line = trim($line);

        if ($line === '') {
            return;
        }

        if (! str_starts_with($line, 'data:')) {
            $callback($line."\n");

            return;
        }

        $dataContent = trim(substr($line, 5));

        if (str_starts_with($dataContent, '{') && str_ends_with($dataContent, '}')) {
            $decoded = json_decode($dataContent, true);
            $callback($decoded['text'] ?? $decoded['content'] ?? $dataContent);

            return;
        }

        $callback($dataContent);
    }
}

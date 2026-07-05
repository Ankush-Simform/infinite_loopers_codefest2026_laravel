<?php

declare(strict_types=1);

namespace App\Integrations\Flask;

use App\Contracts\AiServiceContract;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class FlaskApiService implements AiServiceContract
{
    /**
     * Call the Flask backend to generate a response for a chat message.
     */
    public function generateChatResponse(string $message, array $history = [], ?string $reportUrl = null): string
    {
        $baseUrl = config('services.flask.base_url', 'http://127.0.0.1:5000/api/v1');
        $timeout = (int) config('services.flask.timeout', 30);

        try {
            Log::info('Sending request to Flask AI service', [
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
                    Log::info('Flask AI service returned response successfully');

                    return $responseText;
                }
            }

            Log::warning('Flask AI service returned non-successful response or empty text', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

        } catch (\Throwable $e) {
            Log::error('Failed to communicate with Flask AI service', [
                'error' => $e->getMessage(),
            ]);
        }

        // Return a professional fallback response if AI backend is down/unavailable
        return "I've received your message, but I'm currently unable to reach my medical analysis engine. Please try again in a few moments.";
    }

    /**
     * Stream an AI chat response.
     */
    public function streamChatResponse(
        int $userId,
        int $sessionId,
        string $message,
        array $history,
        array $attachments,
        callable $callback
    ): void {
        $baseUrl = config('services.flask.base_url', 'http://127.0.0.1:5000/api/v1');
        $sharedSecret = config('services.flask.shared_secret', 'super-secret-key-123!');
        $timeout = (int) config('services.flask.timeout', 30);
        $url = $baseUrl.'/chat/report-assistant';

        Log::info('Initiating streaming request to Flask AI service', [
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
                    'history' => $history,
                    'attachments' => $attachments,
                ],
                'stream' => true,
                'timeout' => $timeout,
            ]);

            $body = $response->getBody();
            $contentType = $response->getHeaderLine('Content-Type');
            $isSse = str_contains(strtolower($contentType), 'text/event-stream');

            if ($isSse) {
                $buffer = '';
                while (! $body->eof()) {
                    $chunk = $body->read(128); // read small blocks for responsive streaming
                    if ($chunk === '') {
                        continue;
                    }
                    $buffer .= $chunk;

                    while (($pos = strpos($buffer, "\n")) !== false) {
                        $line = substr($buffer, 0, $pos);
                        $buffer = substr($buffer, $pos + 1);
                        $line = trim($line);

                        if ($line === '') {
                            continue;
                        }

                        if (str_starts_with($line, 'data:')) {
                            $dataContent = trim(substr($line, 5));
                            if (str_starts_with($dataContent, '{') && str_ends_with($dataContent, '}')) {
                                $decoded = json_decode($dataContent, true);
                                $text = $decoded['text'] ?? $decoded['content'] ?? $dataContent;
                            } else {
                                $text = $dataContent;
                            }
                            $callback($text);
                        } else {
                            $callback($line."\n");
                        }
                    }
                }
                if ($buffer !== '') {
                    $trimmed = trim($buffer);
                    if ($trimmed !== '') {
                        if (str_starts_with($trimmed, 'data:')) {
                            $dataContent = trim(substr($trimmed, 5));
                            if (str_starts_with($dataContent, '{') && str_ends_with($dataContent, '}')) {
                                $decoded = json_decode($dataContent, true);
                                $text = $decoded['text'] ?? $decoded['content'] ?? $dataContent;
                            } else {
                                $text = $dataContent;
                            }
                            $callback($text);
                        } else {
                            $callback($buffer);
                        }
                    }
                }
            } else {
                // Raw stream fallback
                while (! $body->eof()) {
                    $chunk = $body->read(128);
                    if ($chunk !== '') {
                        $callback($chunk);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('Error during Flask AI streaming', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $callback("I'm currently unable to reach my medical analysis engine. Please try again in a few moments.");
        }
    }
}

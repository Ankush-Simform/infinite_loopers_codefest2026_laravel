<?php

declare(strict_types=1);

namespace App\Integrations\Flask;

use App\Contracts\AiServiceContract;
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
                'url' => $baseUrl . '/chat',
                'message_length' => strlen($message),
                'report_url' => $reportUrl,
            ]);

            $response = Http::timeout($timeout)
                ->post($baseUrl . '/chat', [
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
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\AiServiceContract;
use Illuminate\Support\Facades\Log;

final class MockAiService implements AiServiceContract
{
    /**
     * Generate a mock AI response.
     */
    public function generateChatResponse(string $message, array $history = [], ?string $reportUrl = null): string
    {
        Log::info('Mock AI Service called to generate response', [
            'message' => $message,
            'has_report' => $reportUrl !== null,
        ]);

        $mock = $this->classifyMockResponse($message);

        return json_encode([
            'status' => 'completed',
            'message' => $mock['text'],
            'metadata' => [
                'confidence' => $mock['confidence'],
                'report_type' => $mock['report_type'],
                'processing_time_ms' => 1240,
                'citations' => [],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Stream a mock AI chat response as plain-text chunks, matching the contract of
     * the real AI service so the controller never has to special-case the mock.
     */
    public function streamChatResponse(
        int $userId,
        int $sessionId,
        string $message,
        array $attachments,
        callable $callback
    ): void {
        Log::info('Mock AI Service streaming response called', [
            'user_id' => $userId,
            'session_id' => $sessionId,
            'attachments_count' => count($attachments),
        ]);

        $responseText = $this->classifyMockResponse($message)['text'];

        foreach (str_split($responseText, 8) as $chunk) {
            $callback($chunk);
            usleep(15000); // 15ms sleep between chunks to simulate streaming
        }
    }

    /**
     * Pick a canned response, report type, and confidence score based on message keywords.
     *
     * @return array{text: string, report_type: string, confidence: float}
     */
    private function classifyMockResponse(string $message): array
    {
        $normalizedMessage = strtolower(trim($message));

        return match (true) {
            str_contains($normalizedMessage, 'emergency') || str_contains($normalizedMessage, 'chest pain') || str_contains($normalizedMessage, 'breathing') => [
                'text' => 'WARNING: If you are experiencing severe symptoms, chest pain, or breathing difficulties, please call emergency services immediately or contact your emergency contact. I am an AI helper and cannot replace professional medical diagnosis.',
                'report_type' => 'General Wellness',
                'confidence' => 99.9,
            ],
            str_contains($normalizedMessage, 'hello') || str_contains($normalizedMessage, 'hi') || str_contains($normalizedMessage, 'hey') => [
                'text' => "Hello! I'm your AMRV medical assistant. I can help explain your medical reports, track health trends, or answer general wellness questions. How can I help you today?",
                'report_type' => 'General Conversation',
                'confidence' => 95.0,
            ],
            str_contains($normalizedMessage, 'thank') || str_contains($normalizedMessage, 'thanks') => [
                'text' => "You're very welcome! If you have any other questions about your health reports or need help understanding wellness advice, feel free to ask.",
                'report_type' => 'General Conversation',
                'confidence' => 97.5,
            ],
            default => [
                'text' => 'Based on the uploaded report, your blood sugar, hemoglobin and cholesterol levels appear to be within the normal range. No critical abnormalities were detected. Please consult your physician for an official medical opinion.',
                'report_type' => 'Blood Test',
                'confidence' => 98.4,
            ],
        };
    }
}

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

        $normalizedMessage = strtolower(trim($message));

        $messageText = "Based on the uploaded report, your blood sugar, hemoglobin and cholesterol levels appear to be within the normal range. No critical abnormalities were detected. Please consult your physician for an official medical opinion.";
        $reportType = "Blood Test";
        $confidence = 98.4;

        if (str_contains($normalizedMessage, 'emergency') || str_contains($normalizedMessage, 'chest pain') || str_contains($normalizedMessage, 'breathing')) {
            $messageText = "WARNING: If you are experiencing severe symptoms, chest pain, or breathing difficulties, please call emergency services immediately or contact your emergency contact. I am an AI helper and cannot replace professional medical diagnosis.";
            $reportType = "General Wellness";
            $confidence = 99.9;
        } elseif (str_contains($normalizedMessage, 'hello') || str_contains($normalizedMessage, 'hi') || str_contains($normalizedMessage, 'hey')) {
            $messageText = "Hello! I'm your AMRV medical assistant. I can help explain your medical reports, track health trends, or answer general wellness questions. How can I help you today?";
            $reportType = "General Conversation";
            $confidence = 95.0;
        } elseif (str_contains($normalizedMessage, 'thank') || str_contains($normalizedMessage, 'thanks')) {
            $messageText = "You're very welcome! If you have any other questions about your health reports or need help understanding wellness advice, feel free to ask.";
            $reportType = "General Conversation";
            $confidence = 97.5;
        }

        return json_encode([
            'status' => 'completed',
            'message' => $messageText,
            'metadata' => [
                'confidence' => $confidence,
                'report_type' => $reportType,
                'processing_time_ms' => 1240,
                'citations' => []
            ]
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
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
        Log::info('Mock AI Service streaming response called', [
            'user_id' => $userId,
            'session_id' => $sessionId,
            'attachments_count' => count($attachments),
        ]);

        $reportUrl = null;
        if (! empty($attachments)) {
            $reportUrl = $attachments[0]['file_path'] ?? null;
        }

        $fullResponse = $this->generateChatResponse($message, $history, $reportUrl);

        // Split response into small chunks to simulate streaming of the JSON string
        $length = strlen($fullResponse);
        $chunkSize = 8;
        for ($i = 0; $i < $length; $i += $chunkSize) {
            $chunk = substr($fullResponse, $i, $chunkSize);
            $callback($chunk);
            usleep(15000); // 15ms sleep between chunks
        }
    }
}

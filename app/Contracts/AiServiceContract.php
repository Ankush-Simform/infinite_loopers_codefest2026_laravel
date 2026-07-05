<?php

declare(strict_types=1);

namespace App\Contracts;

interface AiServiceContract
{
    /**
     * Generate an AI chat response.
     */
    public function generateChatResponse(string $message, array $history = [], ?string $reportUrl = null): string;

    /**
     * Stream an AI chat response. Conversation continuity is maintained by the AI
     * service itself via $sessionId, so no history is passed on each call.
     */
    public function streamChatResponse(
        int $userId,
        int $sessionId,
        string $message,
        array $attachments,
        callable $callback
    ): void;
}

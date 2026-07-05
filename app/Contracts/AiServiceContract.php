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
     * Stream an AI chat response.
     */
    public function streamChatResponse(
        int $userId,
        int $sessionId,
        string $message,
        array $history,
        array $attachments,
        callable $callback
    ): void;
}

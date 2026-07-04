<?php

declare(strict_types=1);

namespace App\Contracts;

interface AiServiceContract
{
    /**
     * Generate an AI chat response.
     *
     * @param string $message
     * @param array $history
     * @param string|null $reportUrl
     * @return string
     */
    public function generateChatResponse(string $message, array $history = [], ?string $reportUrl = null): string;
}

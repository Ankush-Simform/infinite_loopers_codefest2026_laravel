<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ChatMessageRole;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChatService
{
    public function __construct(
        protected AzureBlobService $azureBlobService
    ) {}

    /**
     * Store user message and its attachments in a database transaction.
     */
    public function saveUserMessage(
        ChatSession $session,
        ?string $content,
        array $dbAttachments,
        ?array $metadata = null
    ): ChatMessage {
        return DB::transaction(function () use ($session, $content, $dbAttachments, $metadata) {
            // 1. Save user's message record
            $msg = $session->messages()->create([
                'role' => ChatMessageRole::USER,
                'content' => $content ?? '',
                'metadata' => $metadata,
            ]);

            // 2. Save attachment database entries
            foreach ($dbAttachments as $attachment) {
                $msg->attachments()->create($attachment);
            }

            // 3. Update session's last message timestamp
            $session->update(['last_message_at' => now()]);

            return $msg;
        });
    }

    /**
     * Persist the AI's fully-streamed reply so every conversation has a durable record,
     * independent of whether the client stayed connected for the whole stream.
     */
    public function saveAssistantMessage(ChatSession $session, string $content): ChatMessage
    {
        return DB::transaction(function () use ($session, $content) {
            $msg = $session->messages()->create([
                'role' => ChatMessageRole::ASSISTANT,
                'content' => $content,
            ]);

            $session->update(['last_message_at' => now()]);

            return $msg;
        });
    }

    /**
     * Delete chat session and its files from Azure storage.
     */
    public function deleteSession(ChatSession $session): void
    {
        DB::transaction(function () use ($session): void {
            // Eager-load all messages with their attachments
            $messages = $session->messages()->with('attachments')->get();

            foreach ($messages as $message) {
                foreach ($message->attachments as $attachment) {
                    try {
                        $this->azureBlobService->deleteFile($attachment->stored_name);
                    } catch (\Throwable $e) {
                        Log::warning('Failed to delete attachment from Azure during session destroy', [
                            'blob' => $attachment->stored_name,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
                $message->attachments()->delete();
            }

            $session->messages()->delete();
            $session->delete();
        });
    }
}

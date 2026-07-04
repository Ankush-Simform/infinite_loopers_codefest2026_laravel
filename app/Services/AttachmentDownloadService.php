<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ChatAttachment;
use App\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AttachmentDownloadService
{
    public function __construct(
        protected AzureBlobService $azureBlobService
    ) {}

    /**
     * Generate a secure streamed response for chat attachment.
     *
     * @throws NotFoundHttpException
     */
    public function streamAttachment(User $user, int $attachmentId): StreamedResponse
    {
        $attachment = ChatAttachment::findOrFail($attachmentId);

        // Verify user ownership via ChatSession relation
        $session = $user->chatSessions()
            ->whereHas('messages', function ($query) use ($attachment) {
                $query->where('id', $attachment->chat_message_id);
            })
            ->first();

        if (! $session) {
            throw new NotFoundHttpException('Attachment not found or access denied.');
        }

        return new StreamedResponse(function () use ($attachment) {
            $this->azureBlobService->downloadStream($attachment->stored_name, function ($chunk) {
                echo $chunk;
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            });
        }, 200, [
            'Content-Type' => $attachment->mime_type,
            'Content-Disposition' => 'inline; filename="'.basename($attachment->original_name).'"',
            'Cache-Control' => 'no-cache, must-revalidate',
        ]);
    }
}

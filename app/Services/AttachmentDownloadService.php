<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ChatAttachment;
use App\Models\User;
use Symfony\Component\HttpFoundation\Response;
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
    public function streamAttachment(User $user, int $attachmentId): Response
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

        $sasUrl = $this->azureBlobService->generateSasUrl($attachment->stored_name);

        return redirect($sasUrl);
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AttachmentType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class AttachmentUploadService
{
    /**
     * @var array<string>
     */
    protected array $uploadedBlobs = [];

    public function __construct(
        protected AzureBlobService $azureBlobService
    ) {}

    /**
     * Upload files to Azure and return database-ready attachment arrays.
     *
     * @param  array<UploadedFile>  $files
     * @return array<array<string, mixed>>
     *
     * @throws \Throwable
     */
    public function uploadAttachments(array $files, int $userId, int $sessionId): array
    {
        $dbAttachments = [];
        $this->uploadedBlobs = [];

        try {
            foreach ($files as $file) {
                if (! $file instanceof UploadedFile) {
                    continue;
                }

                // Upload file to Azure
                $uploaded = $this->azureBlobService->uploadFile(
                    $file,
                    "users/{$userId}/chats/{$sessionId}"
                );

                // Track uploaded blob for potential rollback
                $this->uploadedBlobs[] = $uploaded['public_id'];

                $mime = $file->getMimeType() ?: 'application/octet-stream';
                $extension = $file->getClientOriginalExtension() ?: 'bin';
                $type = AttachmentType::fromMime($mime, $extension);

                $dbAttachments[] = [
                    'original_name' => $file->getClientOriginalName(),
                    'stored_name' => $uploaded['public_id'],
                    'file_path' => $uploaded['url'],
                    'mime_type' => $mime,
                    'file_size' => $file->getSize(),
                    'extension' => substr($extension, 0, 20),
                    'type' => $type->value,
                ];
            }
        } catch (\Throwable $e) {
            Log::error('Failed to upload files during message attachment preparation. Rolling back uploaded blobs.', [
                'error' => $e->getMessage(),
            ]);
            $this->rollbackUploadedBlobs();
            throw $e;
        }

        return $dbAttachments;
    }

    /**
     * Clean up uploaded blobs if the database transaction fails.
     */
    public function rollbackUploadedBlobs(): void
    {
        foreach ($this->uploadedBlobs as $blobName) {
            try {
                $this->azureBlobService->deleteFile($blobName);
                Log::info('Rolled back Azure blob storage upload successfully', ['blob' => $blobName]);
            } catch (\Throwable $e) {
                Log::error('Failed to delete uploaded blob during rollback', [
                    'blob' => $blobName,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        $this->uploadedBlobs = [];
    }
}

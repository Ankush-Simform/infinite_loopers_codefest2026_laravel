<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Blob\Models\CreateBlockBlobOptions;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;

class AzureBlobService
{
    protected string $accountName;
    protected string $containerName;
    protected string $accountKey;
    protected BlobRestProxy $client;

    public function __construct()
    {
        $this->accountName = config('services.azure.storage_name') ?? env('AZURE_STORAGE_NAME') ?? '';
        $this->containerName = config('services.azure.storage_container') ?? env('AZURE_STORAGE_CONTAINER') ?? '';
        $this->accountKey = config('services.azure.storage_key') ?? env('AZURE_STORAGE_KEY') ?? '';

        $connectionString = sprintf(
            'DefaultEndpointsProtocol=https;AccountName=%s;AccountKey=%s;EndpointSuffix=core.windows.net',
            $this->accountName,
            $this->accountKey
        );

        $this->client = BlobRestProxy::createBlobService($connectionString);
    }

    /**
     * Blob folder for a user's uploaded medical reports.
     */
    public static function userReportsFolder(int|string $userId): string
    {
        return "user/{$userId}/reports";
    }

    /**
     * Blob folder for a user's profile assets (e.g. avatar).
     */
    public static function userProfileFolder(int|string $userId): string
    {
        return "user/{$userId}/profile";
    }

    /**
     * Build the blob filename (without extension) for a medical report, following the
     * convention: <reference-id>_<sanitized-report-name>_<dd-mm-yyyy--HH:MM:SS>.
     */
    public static function buildReportBlobFilename(int|string $referenceId, string $reportName, ?\DateTimeInterface $dateTime = null): string
    {
        $dateTime ??= now();

        $sanitizedName = preg_replace('/\s+/', '-', trim($reportName));
        $sanitizedName = trim((string) preg_replace('/[^A-Za-z0-9\-_]/', '', $sanitizedName), '-') ?: 'report';

        return $referenceId . '_' . $sanitizedName . '_' . $dateTime->format('d-m-Y--H:i:s');
    }

    /**
     * Upload a file to Azure Blob Storage using the Azure Storage SDK.
     *
     * @param UploadedFile $file
     * @param string $folder
     * @param array<string, mixed> $metadata Extra metadata stored alongside the blob (e.g. user_id, report_id).
     * @param string|null $filename Custom blob filename without extension; defaults to a generated unique name.
     * @return array{url: string, public_id: string, format: string, bytes: int}
     * @throws \Exception
     */
    public function uploadFile(UploadedFile $file, string $folder = 'amrv', array $metadata = [], ?string $filename = null): array
    {
        try {
            $extension = $file->getClientOriginalExtension();
            $filename = ($filename ?: uniqid('report_', true)) . '.' . $extension;
            $blobName = trim($folder, '/') . '/' . $filename;

            $fileContent = file_get_contents($file->getRealPath());
            $contentType = $file->getMimeType() ?: 'application/octet-stream';

            $options = new CreateBlockBlobOptions();
            $options->setContentType($contentType);
            $options->setMetadata($this->normalizeMetadata(array_merge($metadata, [
                'uploaded_at' => now()->toIso8601String(),
                'original_filename' => $file->getClientOriginalName(),
            ])));

            $this->client->createBlockBlob($this->containerName, $blobName, $fileContent, $options);

            $url = "https://{$this->accountName}.blob.core.windows.net/{$this->containerName}/{$blobName}";

            Log::info('Azure Blob Upload Successful', [
                'blob' => $blobName,
                'url' => $url,
            ]);

            return [
                'url' => $url,
                'public_id' => $blobName,
                'format' => $extension,
                'bytes' => strlen($fileContent),
            ];
        } catch (\Throwable $e) {
            Log::error('Azure Blob Service Exception during upload', [
                'error' => $e->getMessage(),
                'file_name' => $file->getClientOriginalName(),
            ]);

            throw new \Exception('Failed to upload file to Azure storage: ' . $e->getMessage());
        }
    }

    /**
     * Delete a file from Azure Blob Storage.
     *
     * @param string $blobName
     * @return bool
     */
    public function deleteFile(string $blobName): bool
    {
        try {
            $this->client->deleteBlob($this->containerName, $blobName);

            Log::info('Azure Blob Delete Successful', [
                'blob' => $blobName,
            ]);

            return true;
        } catch (ServiceException $e) {
            if ($e->getCode() === 404) {
                Log::info('Azure Blob Delete Skipped: Blob not found', ['blob' => $blobName]);
                return true;
            }

            Log::error('Azure Blob Delete Failed', [
                'error' => $e->getMessage(),
                'blob' => $blobName,
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::error('Azure Blob Service Exception during delete', [
                'error' => $e->getMessage(),
                'blob' => $blobName,
            ]);

            return false;
        }
    }

    /**
     * Download / Fetch file content from Azure Blob Storage.
     *
     * @param string $blobName
     * @return array{content: string, mime_type: string}
     * @throws \Exception
     */
    public function getFile(string $blobName): array
    {
        try {
            $blob = $this->client->getBlob($this->containerName, $blobName);

            return [
                'content' => stream_get_contents($blob->getContentStream()),
                'mime_type' => $blob->getProperties()->getContentType() ?: 'application/octet-stream',
            ];
        } catch (\Throwable $e) {
            Log::error('Azure Blob Service Exception during getFile', [
                'error' => $e->getMessage(),
                'blob' => $blobName,
            ]);

            throw new \Exception('Failed to get file from Azure storage: ' . $e->getMessage());
        }
    }

    /**
     * Azure blob metadata keys must be valid C#-style identifiers (letters, digits, underscore,
     * not starting with a digit) and values must be strings.
     *
     * @param array<string, mixed> $metadata
     * @return array<string, string>
     */
    private function normalizeMetadata(array $metadata): array
    {
        $normalized = [];

        foreach ($metadata as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $safeKey = preg_replace('/\W/', '_', (string) $key);
            if ($safeKey === '' || preg_match('/^\d/', $safeKey) === 1) {
                $safeKey = '_' . $safeKey;
            }

            $normalized[$safeKey] = (string) $value;
        }

        return $normalized;
    }
}

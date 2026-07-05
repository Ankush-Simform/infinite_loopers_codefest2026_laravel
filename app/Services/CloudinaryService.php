<?php

declare(strict_types=1);

namespace App\Services;

use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Configuration\Configuration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class CloudinaryService
{
    public function __construct()
    {
        Configuration::instance([
            'cloud' => [
                'cloud_name' => config('services.cloudinary.cloud_name'),
                'api_key' => config('services.cloudinary.api_key'),
                'api_secret' => config('services.cloudinary.api_secret'),
            ],
            'url' => [
                'secure' => true,
            ],
        ]);
    }

    /**
     * Upload an uploaded file to Cloudinary.
     *
     * @return array{url: string, public_id: string, format: string, bytes: int}
     *
     * @throws \Exception
     */
    public function uploadFile(UploadedFile $file, string $folder = 'amrv'): array
    {
        try {
            $uploadApi = new UploadApi;
            $result = $uploadApi->upload($file->getRealPath(), [
                'folder' => $folder,
                'resource_type' => 'auto', // Automatically detects images, PDFs, etc.
            ]);

            Log::info('Cloudinary upload successful', [
                'public_id' => $result['public_id'],
                'secure_url' => $result['secure_url'],
            ]);

            return [
                'url' => $result['secure_url'],
                'public_id' => $result['public_id'],
                'format' => $result['format'] ?? $file->getClientOriginalExtension(),
                'bytes' => (int) ($result['bytes'] ?? $file->getSize()),
            ];
        } catch (\Throwable $e) {
            Log::error('Cloudinary upload failed', [
                'error' => $e->getMessage(),
                'file_name' => $file->getClientOriginalName(),
            ]);

            throw new \Exception('Failed to upload file to cloud storage: '.$e->getMessage());
        }
    }

    /**
     * Delete a file from Cloudinary.
     *
     * @param  string  $resourceType  (image, raw, video)
     */
    public function deleteFile(string $publicId, string $resourceType = 'image'): bool
    {
        try {
            $uploadApi = new UploadApi;
            $result = $uploadApi->destroy($publicId, [
                'resource_type' => $resourceType,
            ]);

            $success = ($result['result'] ?? '') === 'ok';

            Log::info('Cloudinary delete finished', [
                'public_id' => $publicId,
                'status' => $result['result'] ?? 'unknown',
            ]);

            return $success;
        } catch (\Throwable $e) {
            Log::error('Cloudinary delete failed', [
                'error' => $e->getMessage(),
                'public_id' => $publicId,
            ]);

            return false;
        }
    }
}

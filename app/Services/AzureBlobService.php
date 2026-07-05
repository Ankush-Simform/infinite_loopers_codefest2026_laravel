<?php

declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AzureBlobService
{
    protected string $accountName;

    protected string $containerName;

    protected string $accountKey;

    public function __construct()
    {
        $this->accountName = config('services.azure.storage_name') ?? env('AZURE_STORAGE_NAME') ?? '';
        $this->containerName = config('services.azure.storage_container') ?? env('AZURE_STORAGE_CONTAINER') ?? '';
        $this->accountKey = config('services.azure.storage_key') ?? env('AZURE_STORAGE_KEY') ?? '';
    }

    /**
     * Upload a file to Azure Blob Storage.
     *
     * @return array{url: string, public_id: string, format: string, bytes: int}
     *
     * @throws \Exception
     */
    public function uploadFile(UploadedFile $file, string $folder = 'amrv'): array
    {
        try {
            $extension = $file->getClientOriginalExtension();
            $filename = uniqid('report_', true).'.'.$extension;
            $blobName = trim($folder, '/').'/'.$filename;

            $fileContent = file_get_contents($file->getRealPath());
            $contentLength = (string) strlen($fileContent);
            $contentType = $file->getMimeType() ?: 'application/octet-stream';

            // Generate GMT Date for headers
            $gmtDate = gmdate('D, d M Y H:i:s \G\M\T');

            // Construct CanonicalizedHeaders (must be sorted alphabetically by header name)
            $canonicalizedHeaders = "x-ms-blob-type:BlockBlob\n".
                                    'x-ms-date:'.$gmtDate."\n".
                                    'x-ms-version:2021-08-06';

            // Construct CanonicalizedResource
            $canonicalizedResource = '/'.$this->accountName.'/'.$this->containerName.'/'.$blobName;

            // Construct String to Sign
            $stringToSign = "PUT\n".               // VERB
                            "\n".                  // Content-Encoding
                            "\n".                  // Content-Language
                            $contentLength."\n". // Content-Length
                            "\n".                  // Content-MD5
                            $contentType."\n".   // Content-Type
                            "\n".                  // Date
                            "\n".                  // If-Modified-Since
                            "\n".                  // If-Unmodified-Since
                            "\n".                  // If-Match
                            "\n".                  // If-None-Match
                            "\n".                  // Range
                            $canonicalizedHeaders."\n".
                            $canonicalizedResource;

            // Generate HMAC-SHA256 signature
            $decodedKey = base64_decode($this->accountKey);
            $signature = base64_encode(hash_hmac('sha256', $stringToSign, $decodedKey, true));

            $url = "https://{$this->accountName}.blob.core.windows.net/{$this->containerName}/{$blobName}";

            $response = Http::withHeaders([
                'Authorization' => "SharedKey {$this->accountName}:{$signature}",
                'x-ms-date' => $gmtDate,
                'x-ms-version' => '2021-08-06',
                'x-ms-blob-type' => 'BlockBlob',
                'Content-Type' => $contentType,
                'Content-Length' => $contentLength,
            ])->withBody($fileContent, $contentType)->put($url);

            if ($response->successful()) {
                Log::info('Azure Blob Upload Successful', [
                    'blob' => $blobName,
                    'url' => $url,
                ]);

                return [
                    'url' => $url,
                    'public_id' => $blobName,
                    'format' => $extension,
                    'bytes' => (int) $contentLength,
                ];
            }

            Log::error('Azure Blob Upload Failed', [
                'status' => $response->status(),
                'response' => $response->body(),
                'blob' => $blobName,
            ]);

            throw new \Exception('Failed to upload file to Azure storage: '.$response->body());
        } catch (\Throwable $e) {
            Log::error('Azure Blob Service Exception during upload', [
                'error' => $e->getMessage(),
                'file_name' => $file->getClientOriginalName(),
            ]);

            throw new \Exception('Failed to upload file to Azure storage: '.$e->getMessage());
        }
    }

    /**
     * Delete a file from Azure Blob Storage.
     */
    public function deleteFile(string $blobName): bool
    {
        try {
            $gmtDate = gmdate('D, d M Y H:i:s \G\M\T');

            $canonicalizedHeaders = 'x-ms-date:'.$gmtDate."\n".
                                    'x-ms-version:2021-08-06';

            $canonicalizedResource = '/'.$this->accountName.'/'.$this->containerName.'/'.$blobName;

            $stringToSign = "DELETE\n".           // VERB
                            "\n".                  // Content-Encoding
                            "\n".                  // Content-Language
                            "\n".                  // Content-Length
                            "\n".                  // Content-MD5
                            "\n".                  // Content-Type
                            "\n".                  // Date
                            "\n".                  // If-Modified-Since
                            "\n".                  // If-Unmodified-Since
                            "\n".                  // If-Match
                            "\n".                  // If-None-Match
                            "\n".                  // Range
                            $canonicalizedHeaders."\n".
                            $canonicalizedResource;

            $decodedKey = base64_decode($this->accountKey);
            $signature = base64_encode(hash_hmac('sha256', $stringToSign, $decodedKey, true));

            $url = "https://{$this->accountName}.blob.core.windows.net/{$this->containerName}/{$blobName}";

            $response = Http::withHeaders([
                'Authorization' => "SharedKey {$this->accountName}:{$signature}",
                'x-ms-date' => $gmtDate,
                'x-ms-version' => '2021-08-06',
            ])->delete($url);

            if ($response->successful() || $response->status() === 404) {
                Log::info('Azure Blob Delete Successful', [
                    'blob' => $blobName,
                ]);

                return true;
            }

            Log::error('Azure Blob Delete Failed', [
                'status' => $response->status(),
                'response' => $response->body(),
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
     * @return array{content: string, mime_type: string}
     *
     * @throws \Exception
     */
    public function getFile(string $blobName): array
    {
        try {
            $gmtDate = gmdate('D, d M Y H:i:s \G\M\T');

            $canonicalizedHeaders = 'x-ms-date:'.$gmtDate."\n".
                                    'x-ms-version:2021-08-06';

            $canonicalizedResource = '/'.$this->accountName.'/'.$this->containerName.'/'.$blobName;

            $stringToSign = "GET\n".               // VERB
                            "\n".                  // Content-Encoding
                            "\n".                  // Content-Language
                            "\n".                  // Content-Length
                            "\n".                  // Content-MD5
                            "\n".                  // Content-Type
                            "\n".                  // Date
                            "\n".                  // If-Modified-Since
                            "\n".                  // If-Unmodified-Since
                            "\n".                  // If-Match
                            "\n".                  // If-None-Match
                            "\n".                  // Range
                            $canonicalizedHeaders."\n".
                            $canonicalizedResource;

            $decodedKey = base64_decode($this->accountKey);
            $signature = base64_encode(hash_hmac('sha256', $stringToSign, $decodedKey, true));

            $url = "https://{$this->accountName}.blob.core.windows.net/{$this->containerName}/{$blobName}";

            $response = Http::withHeaders([
                'Authorization' => "SharedKey {$this->accountName}:{$signature}",
                'x-ms-date' => $gmtDate,
                'x-ms-version' => '2021-08-06',
            ])->get($url);

            if ($response->successful()) {
                return [
                    'content' => $response->body(),
                    'mime_type' => $response->header('Content-Type') ?: 'application/octet-stream',
                ];
            }

            Log::error('Azure Blob Get File Failed', [
                'status' => $response->status(),
                'response' => $response->body(),
                'blob' => $blobName,
            ]);

            throw new \Exception('Failed to get file from Azure storage: '.$response->body());
        } catch (\Throwable $e) {
            Log::error('Azure Blob Service Exception during getFile', [
                'error' => $e->getMessage(),
                'blob' => $blobName,
            ]);

            throw new \Exception('Failed to get file from Azure storage: '.$e->getMessage());
        }
    }

    /**
     * Download and stream a file chunk-by-chunk from Azure Blob Storage.
     *
     * @throws \Exception
     */
    public function downloadStream(string $blobName, callable $callback): void
    {
        try {
            $gmtDate = gmdate('D, d M Y H:i:s \G\M\T');

            $canonicalizedHeaders = 'x-ms-date:'.$gmtDate."\n".
                                    'x-ms-version:2021-08-06';

            $canonicalizedResource = '/'.$this->accountName.'/'.$this->containerName.'/'.$blobName;

            $stringToSign = "GET\n".               // VERB
                            "\n".                  // Content-Encoding
                            "\n".                  // Content-Language
                            "\n".                  // Content-Length
                            "\n".                  // Content-MD5
                            "\n".                  // Content-Type
                            "\n".                  // Date
                            "\n".                  // If-Modified-Since
                            "\n".                  // If-Unmodified-Since
                            "\n".                  // If-Match
                            "\n".                  // If-None-Match
                            "\n".                  // Range
                            $canonicalizedHeaders."\n".
                            $canonicalizedResource;

            $decodedKey = base64_decode($this->accountKey);
            $signature = base64_encode(hash_hmac('sha256', $stringToSign, $decodedKey, true));

            $url = "https://{$this->accountName}.blob.core.windows.net/{$this->containerName}/{$blobName}";

            $guzzle = new Client;
            $response = $guzzle->get($url, [
                'headers' => [
                    'Authorization' => "SharedKey {$this->accountName}:{$signature}",
                    'x-ms-date' => $gmtDate,
                    'x-ms-version' => '2021-08-06',
                ],
                'stream' => true,
            ]);

            $body = $response->getBody();
            while (! $body->eof()) {
                $chunk = $body->read(8192); // Read in 8KB chunks
                if ($chunk !== '') {
                    $callback($chunk);
                }
            }
        } catch (\Throwable $e) {
            Log::error('Azure Blob Service Exception during downloadStream', [
                'error' => $e->getMessage(),
                'blob' => $blobName,
            ]);
            throw new \Exception('Failed to download stream from Azure storage: '.$e->getMessage());
        }
    }
}

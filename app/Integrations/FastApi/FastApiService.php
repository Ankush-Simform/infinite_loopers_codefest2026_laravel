<?php

declare(strict_types=1);

namespace App\Integrations\FastApi;

use App\Models\MedicalReport;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FastApiService
{
    protected string $baseUrl;
    protected ?string $bearerToken;

    public function __construct()
    {
        $this->baseUrl = config('services.fastapi.base_url', 'http://127.0.0.1:8000/api/v1');
        $this->bearerToken = config('services.fastapi.bearer_token');
    }

    /**
     * Call FastAPI to analyze the report.
     *
     * @param MedicalReport $report
     * @param int|string $userId
     * @throws \Exception
     */
    public function analyzeReport(MedicalReport $report, int|string $userId): void
    {
        $endpoint = rtrim($this->baseUrl, '/') . '/reports/analyze';

        $payload = [
            'id' => $report->id,
            'user_id' => $userId,
            'title' => $report->title,
            'storage_provider' => $report->storage_provider ?? 'azure_blob',
            'container' => $report->container ?? 'medical-reports',
            'blob_name' => $report->blob_name,
            'original_file_name' => $report->original_file_name,
            'mime_type' => $report->mime_type,
            'size' => (int) $report->file_size,
        ];

        Log::info('FastAPI Request', [
            'report_id' => $report->id,
            'endpoint' => $endpoint,
            'payload' => $payload,
        ]);

        $response = Http::withToken((string) $this->bearerToken)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->post($endpoint, $payload);

        Log::info('Outgoing Response', [
            'report_id' => $report->id,
            'status_code' => $response->status(),
        ]);

        if ($response->status() !== 202 && !$response->successful()) {
            Log::error('FastAPI analysis request failed', [
                'status_code' => $response->status(),
                'body' => $response->body(),
                'report_id' => $report->id,
            ]);
            throw new \Exception('FastAPI analysis request failed: ' . $response->body());
        }
    }
}

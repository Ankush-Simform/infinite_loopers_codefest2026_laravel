<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ReportStatus;
use App\Events\AiProcessing;
use App\Events\OcrCompleted;
use App\Events\OcrStarted;
use App\Events\ReportProcessingFailed;
use App\Http\Controllers\Api\V1\Reports\WebhookController;
use App\Models\MedicalReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessMedicalReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly string $reportId,
        public readonly string $azureFileUrl,
        public readonly string $reportProfileId,
        public readonly string $userId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $report = MedicalReport::find($this->reportId);
            if (! $report) {
                Log::error('ProcessMedicalReportJob: Report not found.', ['report_id' => $this->reportId]);

                return;
            }

            // 1. Update report status to processing
            $report->update([
                'status' => ReportStatus::PROCESSING,
            ]);

            // 2. Broadcast OCR Started
            Log::info('Broadcasting OcrStarted event', ['report_id' => $this->reportId]);
            event(new OcrStarted($this->reportId, $this->userId));

            // 3. Broadcast OCR Completed
            Log::info('Broadcasting OcrCompleted event', ['report_id' => $this->reportId]);
            event(new OcrCompleted($this->reportId, $this->userId));

            // 4. Broadcast AI Processing
            Log::info('Broadcasting AiProcessing event', ['report_id' => $this->reportId]);
            event(new AiProcessing($this->reportId, $this->userId));

            // 5. Structure the mock webhook payload from the ML service
            $webhookPayload = [
                'report_id' => $this->reportId,
                'summary' => 'This report presents general vitals and blood values. Most parameters including blood sugar and triglycerides are within normal ranges. Suggesting standard dietary routine.',
                'report_type' => 'blood_test',
                'extracted_text' => 'AMRV MEDICAL ANALYSIS LABS - Blood Panel - Vitals normal.',
                'medical_entities' => [
                    [
                        'entity_type' => 'vital',
                        'entity_name' => 'Systolic Blood Pressure',
                        'value' => '120',
                        'unit' => 'mmHg',
                        'reference_range' => '90-120',
                        'status' => 'normal',
                        'confidence' => 99.00,
                    ],
                    [
                        'entity_type' => 'vital',
                        'entity_name' => 'Diastolic Blood Pressure',
                        'value' => '80',
                        'unit' => 'mmHg',
                        'reference_range' => '60-80',
                        'status' => 'normal',
                        'confidence' => 99.00,
                    ],
                    [
                        'entity_type' => 'lab_value',
                        'entity_name' => 'Hemoglobin',
                        'value' => '14.2',
                        'unit' => 'g/dL',
                        'reference_range' => '13.8-17.2',
                        'status' => 'normal',
                        'confidence' => 98.50,
                    ],
                ],
                'risk_level' => 'Low',
                'recommendations' => [
                    'Continue regular hydration.',
                    'Schedule follow-up check in 6 months.',
                    'Keep moderate daily physical exercise.',
                ],
                'confidence_score' => 97.80,
            ];

            // 6. Send the webhook POST call to Laravel
            $webhookUrl = rtrim(config('app.url') ?: url('/'), '/').'/api/webhooks/report-processing-complete';

            Log::info('Dispatching webhook to Laravel endpoint', ['url' => $webhookUrl]);

            try {
                $response = Http::timeout(30)->post($webhookUrl, $webhookPayload);
                if (! $response->successful()) {
                    throw new \Exception('Webhook server returned status: '.$response->status());
                }
            } catch (\Throwable $e) {
                Log::warning('Asynchronous HTTP Webhook call failed, executing local fallback', [
                    'error' => $e->getMessage(),
                ]);

                // Local fallback: execute the controller logic directly without network call (useful in tests)
                $controller = app(WebhookController::class);
                $controller->handlePayloadDirectly($webhookPayload);
            }
        } catch (\Throwable $e) {
            Log::error('Error processing medical report in queue', [
                'report_id' => $this->reportId,
                'error' => $e->getMessage(),
            ]);

            // Broadcast failure
            event(new ReportProcessingFailed($this->reportId, $this->userId, $e->getMessage()));
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\MedicalReport;
use App\Enums\ReportStatus;
use App\Events\ReportProcessingStarted;
use App\Events\ReportProcessingFailed;
use App\Integrations\FastApi\FastApiService;
use App\Services\ActivityLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessMedicalReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly string $reportId,
        public readonly string $userId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(FastApiService $fastApiService): void
    {
        try {
            $report = MedicalReport::find($this->reportId);
            if (!$report) {
                Log::error('ProcessMedicalReportJob: Report not found.', ['report_id' => $this->reportId]);
                return;
            }

            // 1. Update report status to processing
            $report->update([
                'status' => ReportStatus::PROCESSING,
            ]);

            // 2. Broadcast ReportProcessingStarted
            Log::info('Broadcasting ReportProcessingStarted event', ['report_id' => $this->reportId]);
            event(new ReportProcessingStarted(
                $this->userId,
                $this->reportId,
                ReportStatus::PROCESSING->value,
                'ocr_scanning'
            ));

            // 3. Activity Logging: Queue Started / Processing
            ActivityLogger::log(
                $this->userId,
                $this->reportId,
                null,
                'Queue Started',
                null,
                null
            );

            // 4. Call FastApiService
            ActivityLogger::log(
                $this->userId,
                $this->reportId,
                null,
                'FastAPI Request',
                null,
                null
            );

            \Log::info('USER ID => '. $this->userId);
            $fastApiService->analyzeReport($report, $this->userId);

        } catch (\Throwable $e) {
            Log::error('Error processing medical report in queue', [
                'report_id' => $this->reportId,
                'error' => $e->getMessage(),
            ]);

            $report = MedicalReport::find($this->reportId);
            if ($report) {
                $report->update([
                    'status' => ReportStatus::FAILED,
                ]);
            }

            // Activity Logging: Analysis Failed
            ActivityLogger::log(
                $this->userId,
                $this->reportId,
                null,
                'Analysis Failed',
                null,
                null
            );

            // Broadcast failure
            event(new ReportProcessingFailed(
                $this->userId,
                $this->reportId,
                ReportStatus::FAILED->value,
                'failed',
                $e->getMessage()
            ));

            throw $e;
        }
    }
}

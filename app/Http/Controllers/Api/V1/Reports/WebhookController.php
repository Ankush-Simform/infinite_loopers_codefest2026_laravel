<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Reports;

use App\Http\Controllers\Controller;
use App\Models\MedicalReport;
use App\Models\MedicalKnowledge;
use App\Models\MedicalEntity;
use App\Enums\ReportStatus;
use App\Events\ReportProcessingCompleted;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class WebhookController extends Controller
{
    /**
     * Handle incoming webhook call from the ML Service.
     */
    public function handle(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'report_id' => 'required|exists:medical_reports,id',
                'summary' => 'required|string',
                'report_type' => 'required|string',
                'extracted_text' => 'nullable|string',
                'medical_entities' => 'nullable|array',
                'risk_level' => 'required|string',
                'recommendations' => 'nullable|array',
                'confidence_score' => 'nullable|numeric',
            ]);

            $this->savePayload($data);

            return ApiResponse::success(null, 'Webhook processed successfully.');
        } catch (\Throwable $e) {
            Log::error('Webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Webhook processing failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Local direct invocation (fallback).
     */
    public function handlePayloadDirectly(array $payload): void
    {
        $this->savePayload($payload);
    }

    /**
     * Persist the data and broadcast final event.
     */
    private function savePayload(array $data): void
    {
        DB::transaction(function () use ($data): void {
            $report = MedicalReport::findOrFail($data['report_id']);

            // 1. Update report status and info
            $report->update([
                'status' => ReportStatus::COMPLETED,
                'report_type' => $data['report_type'] ?? $report->report_type,
            ]);

            // 2. Create Knowledge entry (delete existing if any to prevent duplicates)
            $report->knowledge()->delete();
            MedicalKnowledge::create([
                'report_id' => $report->id,
                'summary' => $data['summary'],
                'risk_level' => $data['risk_level'],
                'recommendations' => json_encode($data['recommendations'] ?? []),
                'confidence_score' => $data['confidence_score'] ?? 100.00,
                'processing_time_ms' => 1500,
                'processed_at' => now(),
            ]);

            // 3. Create entities (delete existing)
            $report->entities()->delete();
            if (!empty($data['medical_entities'])) {
                foreach ($data['medical_entities'] as $ent) {
                    MedicalEntity::create([
                        'report_id' => $report->id,
                        'entity_type' => $ent['entity_type'] ?? 'vital',
                        'entity_name' => $ent['entity_name'] ?? '',
                        'value' => $ent['value'] ?? null,
                        'unit' => $ent['unit'] ?? null,
                        'reference_range' => $ent['reference_range'] ?? null,
                        'status' => $ent['status'] ?? null,
                        'confidence' => $ent['confidence'] ?? 100.00,
                    ]);
                }
            }

            // 4. Create timeline event
            $report->timelineEvents()->create([
                'report_profile_id' => $report->report_profile_id,
                'event_type' => 'report_upload',
                'title' => 'Report Processed: ' . $report->title,
                'description' => 'Medical report ' . $report->title . ' was successfully analyzed by AI.',
                'event_date' => $report->report_date ?? now()->toDateString(),
                'importance' => 1,
            ]);

            // 5. Send push and in-app notifications
            try {
                $user = $report->reportProfile->user;
                if ($user) {
                    $notificationService = app(\App\Services\NotificationService::class);
                    $notificationService->send(
                        $user,
                        'report_processed',
                        'Medical Report Processed',
                        "Your medical report '" . $report->title . "' has been successfully analyzed by AI.",
                        [
                            'report_id' => $report->id,
                            'status' => 'completed',
                        ]
                    );
                }
            } catch (\Throwable $ne) {
                Log::warning('Failed to send notification on report complete', [
                    'report_id' => $report->id,
                    'error' => $ne->getMessage()
                ]);
            }

            // 6. Broadcast complete event
            Log::info('Broadcasting ReportProcessingCompleted event', ['report_id' => $report->id]);
            event(new ReportProcessingCompleted($report->load(['knowledge', 'entities'])));
        });
    }
}

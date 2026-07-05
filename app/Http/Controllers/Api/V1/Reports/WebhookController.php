<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Reports;

use App\Http\Controllers\Controller;
use App\Models\MedicalReport;
use App\Enums\ReportStatus;
use App\Events\ReportWaitingConfirmation;
use App\Events\ReportProcessingFailed;
use App\Services\ActivityLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class WebhookController extends Controller
{
    /**
     * Handle incoming webhook call from the ML Service.
     */
    public function handle(Request $request): JsonResponse
    {
        // 1. Authentication Check
        $authHeader = $request->header('Authorization');
        $expectedToken = config('services.ai.webhook_secret');

        if (empty($expectedToken)) {
            Log::error('Webhook secret token not configured in services config.');
            return response()->json([
                'success' => false,
                'error' => 'Webhook secret not configured'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ') || substr($authHeader, 7) !== $expectedToken) {
            Log::warning('Unauthorized webhook attempt rejected', [
                'ip' => $request->ip(),
                'auth_header' => $authHeader ? 'Present' : 'Missing',
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // 2. Validation
        $data = $request->validate([
            'report_id' => 'required|exists:medical_reports,id',
            'summary' => 'nullable|string',
            'report_type' => 'nullable|string',
            'extracted_text' => 'nullable|string',
            'medical_entities' => 'nullable|array',
            'risk_level' => 'nullable|string',
            'recommendations' => 'nullable|array',
            'confidence_score' => 'nullable|numeric',
            'status' => 'nullable|string|in:success,failed',
            'message' => 'nullable|string',
        ]);

        $reportId = $data['report_id'];
        $status = $data['status'] ?? 'success';

        $report = MedicalReport::findOrFail($reportId);
        $userId = $report->user_id;

        // Activity Log: Webhook Received
        ActivityLogger::log(
            $userId,
            $reportId,
            null,
            'Webhook Received',
            $request->ip(),
            $request->userAgent()
        );

        if ($status === 'failed') {
            $message = $data['message'] ?? 'Unable to analyze report.';

            $report->update([
                'status' => ReportStatus::FAILED,
            ]);

            ActivityLogger::log(
                $userId,
                $reportId,
                null,
                'Analysis Failed',
                $request->ip(),
                $request->userAgent()
            );

            event(new ReportProcessingFailed(
                $userId,
                $reportId,
                ReportStatus::FAILED->value,
                'failed',
                $message
            ));

            return ApiResponse::success(null, 'Webhook failure processed.');
        }

        // 3. Staging AI Response
        $stagedData = [
            'created_at' => now()->timestamp,
            'report_id' => $report->id,
            'report' => [
                'title' => $report->title,
                'report_type' => $data['report_type'] ?? $report->report_type ?? 'pdf',
                'doctor_name' => 'Dr. Andrew Miller',
                'hospital_name' => 'Central Health Laboratory',
                'report_date' => now()->toDateString(),
            ],
            'knowledge' => [
                'summary' => $data['summary'] ?? '',
                'risk_level' => $data['risk_level'] ?? 'Low',
                'recommendations' => $data['recommendations'] ?? [],
                'confidence_score' => $data['confidence_score'] ?? 100.00,
            ],
            'entities' => $data['medical_entities'] ?? [],
            'tags' => ['blood-test'],
        ];

        Cache::put('temp_upload_' . $reportId, $stagedData, now()->addDay());

        // 4. Status Update
        $report->update([
            'status' => ReportStatus::WAITING_CONFIRMATION,
        ]);

        // Activity Log: Analysis Completed
        ActivityLogger::log(
            $userId,
            $reportId,
            null,
            'Analysis Completed',
            $request->ip(),
            $request->userAgent()
        );

        // 5. Broadcast ReportWaitingConfirmation
        event(new ReportWaitingConfirmation(
            $userId,
            $reportId,
            ReportStatus::WAITING_CONFIRMATION->value,
            'completed'
        ));

        return ApiResponse::success(null, 'Webhook processed and staged successfully.');
    }

    /**
     * Local direct invocation (fallback).
     */
    public function handlePayloadDirectly(array $payload): void
    {
        $reportId = $payload['report_id'];
        $report = MedicalReport::findOrFail($reportId);
        $userId = $report->user_id;

        $stagedData = [
            'created_at' => now()->timestamp,
            'report_id' => $report->id,
            'report' => [
                'title' => $report->title,
                'report_type' => $payload['report_type'] ?? $report->report_type ?? 'pdf',
                'doctor_name' => 'Dr. Andrew Miller',
                'hospital_name' => 'Central Health Laboratory',
                'report_date' => now()->toDateString(),
            ],
            'knowledge' => [
                'summary' => $payload['summary'] ?? '',
                'risk_level' => $payload['risk_level'] ?? 'Low',
                'recommendations' => $payload['recommendations'] ?? [],
                'confidence_score' => $payload['confidence_score'] ?? 100.00,
            ],
            'entities' => $payload['medical_entities'] ?? [],
            'tags' => ['blood-test'],
        ];

        Cache::put('temp_upload_' . $reportId, $stagedData, now()->addDay());

        $report->update([
            'status' => ReportStatus::WAITING_CONFIRMATION,
        ]);

        event(new ReportWaitingConfirmation(
            $userId,
            $reportId,
            ReportStatus::WAITING_CONFIRMATION->value,
            'completed'
        ));
    }
}

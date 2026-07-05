<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Reports;

use App\Enums\ReportStatus;
use App\Events\ReportProcessingFailed;
use App\Events\ReportWaitingConfirmation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Reports\ReportAnalysisWebhookRequest;
use App\Models\MedicalKnowledge;
use App\Models\MedicalReport;
use App\Services\ActivityLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class WebhookController extends Controller
{
    /**
     * Handle the AI service's report analysis result callback.
     */
    public function handle(ReportAnalysisWebhookRequest $request): JsonResponse
    {
        $expectedToken = config('services.ai.webhook_secret');

        if (empty($expectedToken)) {
            Log::error('Webhook secret token not configured in services config.');

            return ApiResponse::error('Webhook secret not configured', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $authHeader = $request->header('Authorization');

        if (! $authHeader || ! str_starts_with($authHeader, 'Bearer ') || substr($authHeader, 7) !== $expectedToken) {
            Log::warning('Unauthorized webhook attempt rejected', [
                'ip' => $request->ip(),
                'auth_header' => $authHeader ? 'Present' : 'Missing',
            ]);

            return ApiResponse::error('Unauthorized', Response::HTTP_UNAUTHORIZED);
        }

        $payload = $request->validated();

        // The AI service has no knowledge of our internal report id, so the file it
        // was asked to analyze is the only thing we can correlate the callback against.
        $report = MedicalReport::where('original_file_name', $payload['original_filename'])
            ->where('status', ReportStatus::PROCESSING)
            ->latest('created_at')
            ->first();

        if (! $report) {
            Log::warning('Webhook received for a file with no matching processing report.', [
                'original_filename' => $payload['original_filename'],
            ]);

            ActivityLogger::log(
                null,
                null,
                null,
                'Webhook Report Not Matched',
                $request->ip(),
                $request->userAgent(),
                $payload
            );

            return ApiResponse::error('No matching report found for the given file.', Response::HTTP_NOT_FOUND);
        }

        $userId = $report->user_id;

        ActivityLogger::log(
            $userId,
            $report->id,
            null,
            'Webhook Received',
            $request->ip(),
            $request->userAgent(),
            $payload
        );

        if (! $payload['status']) {
            $report->update(['status' => ReportStatus::FAILED]);

            ActivityLogger::log(
                $userId,
                $report->id,
                null,
                'Analysis Failed',
                $request->ip(),
                $request->userAgent(),
                $payload
            );

            event(new ReportProcessingFailed(
                $userId,
                $report->id,
                ReportStatus::FAILED->value,
                'failed',
                $payload['message']
            ));

            return ApiResponse::success(null, 'Webhook failure processed.');
        }

        $data = $payload['data'];

        DB::transaction(function () use ($report, $data): void {
            $report->update([
                'title' => $data['report']['title'] ?? $report->title,
                'report_type' => $data['report']['report_type'] ?? $report->report_type,
                'doctor_name' => $data['report']['doctor_name'] ?? null,
                'hospital_name' => $data['report']['hospital_name'] ?? null,
                'report_date' => $data['report']['report_date'] ?? null,
                'status' => ReportStatus::WAITING_CONFIRMATION,
            ]);

            MedicalKnowledge::updateOrCreate(
                ['report_id' => $report->id],
                [
                    'summary' => $data['knowledge']['summary'] ?? '',
                    'risk_level' => $data['knowledge']['risk_level'] ?? null,
                    'recommendations' => json_encode($data['knowledge']['recommendations'] ?? []),
                    'confidence_score' => $data['knowledge']['confidence_score'] ?? null,
                    'processed_at' => now(),
                ]
            );

            // Replace any previously staged entities/tags (e.g. from a prior webhook
            // retry) with this analysis result, rather than appending duplicates.
            $report->entities()->delete();
            foreach ($data['entities'] ?? [] as $entity) {
                $report->entities()->create([
                    'entity_type' => $entity['entity_type'] ?? 'lab_metric',
                    'entity_name' => $entity['entity_name'],
                    'value' => $entity['value'] ?? null,
                    'unit' => $entity['unit'] ?? null,
                    'reference_range' => $entity['reference_range'] ?? null,
                    'status' => $entity['status'] ?? null,
                ]);
            }

            $report->tags()->delete();
            foreach ($data['tags'] ?? [] as $tag) {
                $report->tags()->create(['tag' => $tag]);
            }
        });

        ActivityLogger::log(
            $userId,
            $report->id,
            null,
            'Analysis Completed',
            $request->ip(),
            $request->userAgent(),
            $data
        );

        event(new ReportWaitingConfirmation(
            $userId,
            $report->id,
            ReportStatus::WAITING_CONFIRMATION->value,
            'completed'
        ));

        return ApiResponse::success(null, 'Webhook processed and report saved successfully.');
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Reports;

use App\Enums\ReportStatus;
use App\Events\ReportSaved;
use App\Events\ReportUploaded;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessMedicalReportJob;
use App\Models\MedicalReport;
use App\Services\ActivityLogger;
use App\Services\NotificationService;
use App\Services\Reports\MedicalReportFileService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

final class ReportUploadController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService,
        protected MedicalReportFileService $reportFileService
    ) {}

    /**
     * Step 1: Upload File and trigger background ML job.
     */
    public function upload(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240', // Max 10MB
            ]);

            $file = $request->file('file');
            $fileHash = hash_file('sha256', $file->getRealPath());

            // Check duplicate in database
            $duplicate = MedicalReport::where('file_hash', $fileHash)->first();

            if ($duplicate) {
                Log::warning('Duplicate file upload attempted during staging', [
                    'file_hash' => $fileHash,
                ]);

                return ApiResponse::error('This file has already been uploaded.', Response::HTTP_CONFLICT);
            }

            $originalFilename = $file->getClientOriginalName();

            // Reserve a unique reference_id and persist a draft row
            $report = $this->reportFileService->createDraft([
                'user_id' => $request->user()->id,
                'report_profile_id' => null, // Stays null initially!
                'title' => $originalFilename, // Automatically set to file name
                'report_type' => $file->getClientOriginalExtension() ?: 'pdf',
                'file_hash' => $fileHash,
                'original_file_name' => $originalFilename,
                'mime_type' => $file->getClientMimeType() ?: $file->getMimeType() ?: 'application/octet-stream',
                'file_size' => $file->getSize(),
            ]);

            // Upload report file to Azure Blob Storage
            $uploaded = $this->reportFileService->uploadReportFile($report, $file, (string) $request->user()->id);
            \Log::info('UPLOADED DATA => ', $uploaded);

            $report->update([
                'file_url' => $uploaded['url'],
                'status' => ReportStatus::UPLOADED,
                'blob_name' => $uploaded['public_id'],
            ]);

            // Activity Log: Upload
            ActivityLogger::log(
                $request->user()->id,
                $report->id,
                null,
                'Upload',
                $request->ip(),
                $request->userAgent()
            );

            // Broadcast ReportUploaded Event
            event(new ReportUploaded(
                $request->user()->id,
                $report->id,
                ReportStatus::UPLOADED->value,
                'upload_completed'
            ));

            // Dispatch ProcessMedicalReportJob
            ProcessMedicalReportJob::dispatch(
                $report->id,
                (string) $request->user()->id
            );

            return response()->json([
                'success' => true,
                'upload_id' => $report->id,
                'status' => 'processing',
            ], Response::HTTP_CREATED);

        } catch (\Throwable $e) {
            Log::error('Staged upload failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('An error occurred during upload: '.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Step 2: Check Processing Status
     */
    public function status(Request $request, string $upload_id): JsonResponse
    {
        try {
            $report = MedicalReport::find($upload_id);

            if (! $report) {
                return ApiResponse::error('Report not found.', Response::HTTP_NOT_FOUND);
            }

            if ($report->status === ReportStatus::FAILED) {
                return response()->json([
                    'status' => 'failed',
                ]);
            }

            if ($report->status === ReportStatus::WAITING_CONFIRMATION || $report->status === ReportStatus::COMPLETED) {
                return response()->json([
                    'status' => 'completed',
                ]);
            }

            return response()->json([
                'status' => 'processing',
                'step' => 'entity_extraction',
                'progress' => 75,
            ]);

        } catch (\Throwable $e) {
            Log::error('Status check failed', [
                'upload_id' => $upload_id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to get status.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Step 3: Get Review Data (Staged AI summary)
     */
    public function review(Request $request, string $upload_id): JsonResponse
    {
        try {
            $report = MedicalReport::where('user_id', $request->user()->id)
                ->with(['knowledge', 'entities', 'tags'])
                ->find($upload_id);

            if (! $report || $report->status !== ReportStatus::WAITING_CONFIRMATION) {
                return ApiResponse::error('Staged AI summary not found or expired.', Response::HTTP_NOT_FOUND);
            }

            // Activity Log: Review Viewed
            ActivityLogger::log(
                $request->user()->id,
                $upload_id,
                null,
                'Review Viewed',
                $request->ip(),
                $request->userAgent()
            );

            return response()->json([
                'upload_id' => $upload_id,
                'report' => [
                    'title' => $report->title,
                    'report_type' => $report->report_type,
                    'doctor_name' => $report->doctor_name,
                    'hospital_name' => $report->hospital_name,
                    'report_date' => $report->report_date?->toDateString(),
                ],
                'knowledge' => $report->knowledge ? [
                    'summary' => $report->knowledge->summary,
                    'risk_level' => $report->knowledge->risk_level?->value,
                    'recommendations' => json_decode($report->knowledge->recommendations ?? '[]', true),
                    'confidence_score' => $report->knowledge->confidence_score,
                ] : null,
                'entities' => $report->entities->map(fn ($entity) => [
                    'entity_type' => $entity->entity_type,
                    'entity_name' => $entity->entity_name,
                    'value' => $entity->value,
                    'unit' => $entity->unit,
                    'reference_range' => $entity->reference_range,
                    'status' => $entity->status?->value,
                ])->values(),
                'tags' => $report->tags->pluck('tag')->values(),
            ]);

        } catch (\Throwable $e) {
            Log::error('Staged review retrieval failed', [
                'upload_id' => $upload_id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to retrieve review details.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Step 4: Save and Commit finalized report details.
     */
    public function save(Request $request, string $upload_id): JsonResponse
    {
        try {
            $report = MedicalReport::where('user_id', $request->user()->id)->find($upload_id);

            if (! $report || $report->status !== ReportStatus::WAITING_CONFIRMATION) {
                return ApiResponse::error('Report not found or not ready for confirmation.', Response::HTTP_NOT_FOUND);
            }

            // Validate edits
            $request->validate([
                'report_profile_id' => [
                    'required',
                    Rule::exists('report_profiles', 'id')->where(function ($query) use ($request): void {
                        $query->where('user_id', $request->user()?->id);
                    }),
                ],
                'report' => 'required|array',
                'report.title' => 'required|string|max:255',
                'report.report_type' => 'required|string|max:50',
                'report.doctor_name' => 'nullable|string|max:255',
                'report.hospital_name' => 'nullable|string|max:255',
                'report.report_date' => 'required|date',
                'entities' => 'sometimes|array',
                'tags' => 'sometimes|array',
            ]);

            DB::transaction(function () use ($request, $report): void {
                // 1. Finalize report with the user-confirmed profile assignment and any edits
                $report->update([
                    'report_profile_id' => $request->report_profile_id,
                    'title' => $request->input('report.title'),
                    'report_type' => $request->input('report.report_type'),
                    'doctor_name' => $request->input('report.doctor_name'),
                    'hospital_name' => $request->input('report.hospital_name'),
                    'report_date' => $request->input('report.report_date'),
                    'status' => ReportStatus::COMPLETED,
                ]);

                // 2. Entities/tags were already persisted by the AI webhook; only replace
                //    them if the user actually edited the staged set.
                if ($request->has('entities')) {
                    $report->entities()->delete();
                    foreach ($request->input('entities') as $ent) {
                        $report->entities()->create([
                            'entity_type' => $ent['entity_type'] ?? 'lab_metric',
                            'entity_name' => $ent['entity_name'] ?? '',
                            'value' => $ent['value'] ?? null,
                            'unit' => $ent['unit'] ?? null,
                            'reference_range' => $ent['reference_range'] ?? null,
                            'status' => $ent['status'] ?? null,
                        ]);
                    }
                }

                if ($request->has('tags')) {
                    $report->tags()->delete();
                    foreach ($request->input('tags') as $tag) {
                        $report->tags()->create(['tag' => $tag]);
                    }
                }

                // 3. Create timeline event
                $report->timelineEvents()->create([
                    'report_profile_id' => $report->report_profile_id,
                    'event_type' => 'report_upload',
                    'title' => 'Report Uploaded: '.$report->title,
                    'description' => 'Medical report '.$report->title.' was successfully saved and reviewed.',
                    'event_date' => $report->report_date ?? now()->toDateString(),
                    'importance' => 1,
                ]);
            });

            // Activity Log: Report Saved
            ActivityLogger::log(
                $request->user()->id,
                $upload_id,
                null,
                'Report Saved',
                $request->ip(),
                $request->userAgent()
            );

            // Broadcast ReportSaved
            event(new ReportSaved(
                $request->user()->id,
                $upload_id,
                ReportStatus::COMPLETED->value,
                'completed'
            ));

            // Send push notification
            try {
                $user = $request->user();
                if ($user) {
                    $this->notificationService->send(
                        $user,
                        'report_processed',
                        'Medical Report Processed',
                        'Your medical report "'.$report->title.'" has been successfully analyzed.',
                        ['report_id' => $upload_id]
                    );
                }
            } catch (\Throwable $ne) {
                Log::error('Failed to trigger notification for report save', [
                    'report_id' => $upload_id,
                    'error' => $ne->getMessage(),
                ]);
            }

            return response()->json([
                'success' => true,
                'report_id' => $upload_id,
            ], Response::HTTP_CREATED);

        } catch (\Throwable $e) {
            Log::error('Finalizing staged report failed', [
                'upload_id' => $upload_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Failed to finalize and save report.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

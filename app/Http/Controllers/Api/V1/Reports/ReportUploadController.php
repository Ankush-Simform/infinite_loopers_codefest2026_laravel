<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Reports;

use App\Enums\MedicalEntityStatus;
use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Models\MedicalEntity;
use App\Models\MedicalKnowledge;
use App\Models\MedicalReport;
use App\Models\ReportTag;
use App\Services\AzureBlobService;
use App\Services\NotificationService;
use App\Services\Reports\MedicalReportFileService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class ReportUploadController extends Controller
{
    public function __construct(
        protected AzureBlobService $azureBlobService,
        protected NotificationService $notificationService,
        protected MedicalReportFileService $reportFileService
    ) {}

    /**
     * Step 1: Upload File
     */
    public function upload(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'report_profile_id' => [
                    'required',
                    \Illuminate\Validation\Rule::exists('report_profiles', 'id')->where(function ($query) use ($request): void {
                        $query->where('user_id', $request->user()?->id);
                    }),
                ],
                'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240', // Max 10MB
            ]);

            $file = $request->file('file');
            $fileHash = hash_file('sha256', $file->getRealPath());

            // Check duplicate in database
            $duplicate = MedicalReport::where('report_profile_id', $request->report_profile_id)
                ->where('file_hash', $fileHash)
                ->first();

            if ($duplicate) {
                Log::warning('Duplicate file upload attempted during staging', [
                    'report_profile_id' => $request->report_profile_id,
                    'file_hash' => $fileHash,
                ]);

                return ApiResponse::error('This file has already been uploaded for this profile.', Response::HTTP_CONFLICT);
            }

            // Reserve a unique reference_id and persist a draft row before touching Azure,
            // so concurrent uploads can never be assigned the same reference_id.
            $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

            $report = $this->reportFileService->createDraft([
                'report_profile_id' => $request->report_profile_id,
                'title' => $originalName !== '' ? $originalName : 'Untitled Report',
                'report_type' => $file->getClientOriginalExtension(),
                'file_hash' => $fileHash,
            ]);

            $uploaded = $this->reportFileService->uploadReportFile($report, $file, $request->user()->id);

            $report->update(['file_url' => $uploaded['url']]);

            $uploadId = (string) Str::uuid();

            // Structure mock AI response results (normally fetched via OCR/AI services)
            $stagedData = [
                'created_at' => now()->timestamp,
                'report_id' => $report->id,
                'report_profile_id' => $request->report_profile_id,
                'report' => [
                    'title' => 'Staged Report - '.now()->format('Y-m-d H:i'),
                    'report_type' => $uploaded['format'],
                    'doctor_name' => 'Dr. Andrew Miller',
                    'hospital_name' => 'Central Health Laboratory',
                    'report_date' => now()->toDateString(),
                ],
                'knowledge' => [
                    'summary' => 'This report presents general vitals and blood values. Most parameters including blood sugar and triglycerides are within normal ranges. Suggesting standard dietary routine.',
                    'risk_level' => 'Low',
                    'recommendations' => [
                        'Continue regular hydration.',
                        'Schedule follow-up check in 6 months.',
                        'Keep moderate daily physical exercise.',
                    ],
                    'confidence_score' => 97.80,
                ],
                'entities' => [
                    [
                        'entity_type' => 'vital',
                        'entity_name' => 'Systolic Blood Pressure',
                        'value' => '120',
                        'unit' => 'mmHg',
                        'reference_range' => '90-120',
                        'status' => 'Normal',
                        'confidence' => 99.00,
                    ],
                    [
                        'entity_type' => 'vital',
                        'entity_name' => 'Diastolic Blood Pressure',
                        'value' => '80',
                        'unit' => 'mmHg',
                        'reference_range' => '60-80',
                        'status' => 'Normal',
                        'confidence' => 99.00,
                    ],
                    [
                        'entity_type' => 'lab_value',
                        'entity_name' => 'Hemoglobin',
                        'value' => '14.2',
                        'unit' => 'g/dL',
                        'reference_range' => '13.8-17.2',
                        'status' => 'Normal',
                        'confidence' => 98.50,
                    ],
                ],
                'tags' => ['blood-test', 'vitals'],
            ];

            // Store in Cache temporary storage for 24 hours
            Cache::put('temp_upload_'.$uploadId, $stagedData, now()->addDay());

            Log::info('Staged medical report uploaded successfully', [
                'upload_id' => $uploadId,
                'report_profile_id' => $request->report_profile_id,
            ]);

            return response()->json([
                'success' => true,
                'upload_id' => $uploadId,
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
            $data = Cache::get('temp_upload_'.$upload_id);

            if ($data === null) {
                return ApiResponse::error('Temporary upload not found or expired.', Response::HTTP_NOT_FOUND);
            }

            // Simulate processing lag (e.g. returns processing for 3 seconds, then completes)
            $elapsedSeconds = now()->timestamp - (int) $data['created_at'];

            if ($elapsedSeconds < 3) {
                return response()->json([
                    'status' => 'processing',
                    'step' => 'entity_extraction',
                    'progress' => 75,
                ]);
            }

            return response()->json([
                'status' => 'completed',
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
     * Step 3: Get Review Data
     */
    public function review(Request $request, string $upload_id): JsonResponse
    {
        try {
            $data = Cache::get('temp_upload_'.$upload_id);

            if ($data === null) {
                return ApiResponse::error('Temporary upload not found or expired.', Response::HTTP_NOT_FOUND);
            }

            // Build proxy file url with token query parameter if authenticated
            $fileUrl = route('api.v1.reports.upload.file', ['upload_id' => $upload_id]);
            $token = null;
            $authorization = $request->header('Authorization');
            if ($authorization && str_starts_with($authorization, 'Bearer ')) {
                $token = substr($authorization, 7);
            } elseif ($request->has('token')) {
                $token = $request->query('token');
            }
            if ($token) {
                $fileUrl .= '?token='.$token;
            }

            return response()->json([
                'upload_id' => $upload_id,
                'file_url' => $fileUrl,
                'report' => $data['report'],
                'knowledge' => $data['knowledge'],
                'entities' => $data['entities'],
                'tags' => $data['tags'],
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
     * Get / Stream Staged Upload File
     */
    public function showFile(Request $request, string $upload_id)
    {
        try {
            $data = Cache::get('temp_upload_'.$upload_id);

            if ($data === null) {
                return ApiResponse::error('Temporary upload not found or expired.', Response::HTTP_NOT_FOUND);
            }

            // Verify that the profile of this upload belongs to the authenticated user
            $profile = $request->user()->profiles()->find($data['profile_id']);
            if (! $profile) {
                return ApiResponse::error('Temporary upload not found or access denied.', Response::HTTP_NOT_FOUND);
            }

            $url = $data['file_url'];

            $parsed = parse_url($url, PHP_URL_PATH);
            if (! $parsed) {
                return ApiResponse::error('Invalid report file path.', Response::HTTP_NOT_FOUND);
            }

            $parts = explode('/', trim($parsed, '/'));
            if (count($parts) <= 1) {
                return ApiResponse::error('Invalid report file path structure.', Response::HTTP_NOT_FOUND);
            }

            array_shift($parts); // Remove container name
            $blobName = implode('/', $parts);

            $sasUrl = $this->azureBlobService->generateSasUrl($blobName);

            return redirect($sasUrl);
        } catch (\Throwable $e) {
            Log::error('Error displaying staged report file', [
                'upload_id' => $upload_id,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Staged report file not found or access denied.', Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Step 4: Save Report
     */
    public function save(Request $request, string $upload_id): JsonResponse
    {
        try {
            $data = Cache::get('temp_upload_'.$upload_id);

            if ($data === null) {
                return ApiResponse::error('Temporary upload not found or expired.', Response::HTTP_NOT_FOUND);
            }

            // Validate requested edits
            $request->validate([
                'report_profile_id' => [
                    'required',
                    \Illuminate\Validation\Rule::exists('report_profiles', 'id')->where(function ($query) use ($request): void {
                        $query->where('user_id', $request->user()?->id);
                    }),
                ],
                'report' => 'required|array',
                'report.title' => 'required|string|max:255',
                'report.report_type' => 'required|string|max:50',
                'report.doctor_name' => 'nullable|string|max:255',
                'report.hospital_name' => 'nullable|string|max:255',
                'report.report_date' => 'required|date',
                'entities' => 'nullable|array',
                'tags' => 'nullable|array',
            ]);

            // Persist elements inside database transaction
            $reportId = DB::transaction(function () use ($request, $data): string {
                // 1. Finalize the draft report row created during staging (already carries
                // the reference_id and file_url assigned at upload time)
                $report = MedicalReport::findOrFail($data['report_id']);

                $report->update([
                    'report_profile_id' => $request->report_profile_id,
                    'report_category_id' => $data['report']['report_category_id'] ?? null,
                    'title' => $request->input('report.title'),
                    'report_type' => $request->input('report.report_type'),
                    'doctor_name' => $request->input('report.doctor_name'),
                    'hospital_name' => $request->input('report.hospital_name'),
                    'report_date' => $request->input('report.report_date'),
                    'status' => ReportStatus::COMPLETED,
                ]);

                // 2. Create Knowledge
                MedicalKnowledge::create([
                    'report_id' => $report->id,
                    'summary' => $data['knowledge']['summary'],
                    'risk_level' => $data['knowledge']['risk_level'],
                    'recommendations' => json_encode($data['knowledge']['recommendations']),
                    'confidence_score' => $data['knowledge']['confidence_score'],
                    'processing_time_ms' => 1500,
                    'processed_at' => now(),
                ]);

                // 3. Create Entities (use edited fields if provided, fallback to staged data)
                $entitiesInput = $request->input('entities') ?? $data['entities'];
                foreach ($entitiesInput as $ent) {
                    $status = null;
                    if (isset($ent['status']) && $ent['status'] !== null) {
                        $normalizedStatus = ucfirst(strtolower((string) $ent['status']));
                        $statusCase = MedicalEntityStatus::tryFrom($normalizedStatus);
                        if ($statusCase) {
                            $status = $statusCase;
                        }
                    }

                    MedicalEntity::create([
                        'report_id' => $report->id,
                        'entity_type' => $ent['entity_type'] ?? 'vital',
                        'entity_name' => $ent['entity_name'] ?? '',
                        'value' => $ent['value'] ?? null,
                        'unit' => $ent['unit'] ?? null,
                        'reference_range' => $ent['reference_range'] ?? null,
                        'status' => $status,
                        'confidence' => $ent['confidence'] ?? 100.00,
                    ]);
                }

                // 4. Create Tags (use edited fields if provided, fallback to staged data)
                $tagsInput = $request->input('tags') ?? $data['tags'];
                foreach ($tagsInput as $tag) {
                    ReportTag::create([
                        'report_id' => $report->id,
                        'tag' => $tag,
                    ]);
                }

                // 5. Create timeline event
                $report->timelineEvents()->create([
                    'report_profile_id' => $report->report_profile_id,
                    'event_type' => 'report_upload',
                    'title' => 'Report Uploaded: '.$report->title,
                    'description' => 'Medical report '.$report->title.' was successfully saved and reviewed.',
                    'event_date' => $report->report_date ?? now()->toDateString(),
                    'importance' => 1,
                ]);

                return $report->id;
            });

            // Clean up cache
            Cache::forget('temp_upload_'.$upload_id);

            // Send in-app and push notification to the user
            try {
                $user = $request->user();
                if ($user) {
                    $report = MedicalReport::find($reportId);
                    $this->notificationService->send(
                        $user,
                        'report_processed',
                        'Medical Report Processed',
                        'Your medical report "'.($report?->title ?? 'New Report').'" has been successfully analyzed.',
                        ['report_id' => $reportId]
                    );
                }
            } catch (\Throwable $ne) {
                Log::error('Failed to trigger notification for report save', [
                    'report_id' => $reportId,
                    'error' => $ne->getMessage(),
                ]);
            }

            Log::info('Staged medical report finalized and saved', [
                'upload_id' => $upload_id,
                'report_id' => $reportId,
            ]);

            return response()->json([
                'success' => true,
                'report_id' => $reportId,
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

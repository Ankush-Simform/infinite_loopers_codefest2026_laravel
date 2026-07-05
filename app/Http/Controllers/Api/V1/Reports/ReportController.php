<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Reports;

use App\Enums\ReportStatus;
use App\Events\ReportUploaded;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Reports\ReportStoreRequest;
use App\Http\Requests\Api\V1\Reports\ReportUpdateRequest;
use App\Http\Resources\Api\V1\Reports\ReportResource;
use App\Jobs\ProcessMedicalReportJob;
use App\Models\MedicalReport;
use App\Services\AzureBlobService;
use App\Services\Reports\MedicalReportFileService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class ReportController extends Controller
{
    public function __construct(
        protected AzureBlobService $azureBlobService,
        protected MedicalReportFileService $reportFileService
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $query = $user->medicalReports()->with('category');

            if ($request->filled('report_profile_id')) {
                $query->where('report_profile_id', $request->query('report_profile_id'));
            }

            if ($request->filled('category_id')) {
                $query->where('report_category_id', $request->query('category_id'));
            }

            if ($request->filled('report_type')) {
                $query->where('report_type', $request->query('report_type'));
            }

            if ($request->filled('status')) {
                $query->where('status', $request->query('status'));
            }

            if ($request->filled('search')) {
                $search = $request->query('search');
                $query->where(function ($q) use ($search): void {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('doctor_name', 'like', "%{$search}%")
                        ->orWhere('hospital_name', 'like', "%{$search}%");
                });
            }

            $reports = $query->latest('report_date')->latest('id')->paginate($request->query('per_page', 15));

            $reports->setCollection(
                $reports->getCollection()->map(fn ($report) => new ReportResource($report))
            );

            Log::info('Medical reports listed successfully with filters', [
                'user_id' => $user->id,
                'count' => $reports->count(),
                'filters' => $request->only(['report_profile_id', 'category_id', 'report_type', 'status', 'search']),
            ]);

            return ApiResponse::paginated($reports, 'Medical reports retrieved.');
        } catch (\Throwable $e) {
            Log::error('Error listing medical reports', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('An error occurred while listing medical reports.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(ReportStoreRequest $request): JsonResponse
    {
        try {
            $file = $request->file('file');
            $fileHash = hash_file('sha256', $file->getRealPath());

            // Check if this file has already been uploaded for this profile to prevent duplicates
            $duplicate = MedicalReport::where('report_profile_id', $request->report_profile_id)
                ->where('file_hash', $fileHash)
                ->first();

            if ($duplicate) {
                Log::warning('Duplicate report upload attempted', [
                    'report_profile_id' => $request->report_profile_id,
                    'file_hash' => $fileHash,
                ]);

                return ApiResponse::error('This file has already been uploaded for this profile.', Response::HTTP_CONFLICT);
            }

            // Reserve a unique reference_id and persist a draft row before touching Azure,
            // so concurrent uploads can never be assigned the same reference_id.
            $report = $this->reportFileService->createDraft([
                'report_profile_id' => $request->report_profile_id,
                'report_category_id' => $request->report_category_id,
                'title' => $request->title,
                'report_type' => $file->getClientOriginalExtension(),
                'doctor_name' => $request->doctor_name,
                'hospital_name' => $request->hospital_name,
                'report_date' => $request->report_date,
                'file_hash' => $fileHash,
            ]);

            $uploadedFile = $this->reportFileService->uploadReportFile($report, $file, $request->user()->id);

            $report = DB::transaction(function () use ($report, $uploadedFile) {
                $report->update([
                    'file_url' => $uploadedFile['url'],
                    'status' => ReportStatus::UPLOADED,
                ]);

                // Create a TimelineEvent for this report upload
                $report->timelineEvents()->create([
                    'report_profile_id' => $report->report_profile_id,
                    'event_type' => 'report_upload',
                    'title' => 'Report Uploaded: '.$report->title,
                    'description' => 'Medical report '.$report->title.' was successfully uploaded.',
                    'event_date' => $report->report_date ?? now()->toDateString(),
                    'importance' => 1,
                ]);

                return $report;
            });

            // 1. Broadcast ReportUploaded Event
            Log::info('Broadcasting ReportUploaded event', ['report_id' => $report->id]);
            event(new ReportUploaded($report));

            // 2. Dispatch background ML report processing queue job
            Log::info('Dispatching ProcessMedicalReportJob', ['report_id' => $report->id]);
            ProcessMedicalReportJob::dispatch(
                $report->id,
                $report->file_url,
                $report->report_profile_id,
                $request->user()->id
            );

            return response()->json([
                'success' => true,
                'report_id' => $report->id,
                'status' => 'uploaded',
            ], Response::HTTP_CREATED);
        } catch (\Throwable $e) {
            Log::error('Error storing medical report', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('An error occurred while uploading medical report.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $report = $request->user()->medicalReports()->with('category')->findOrFail($id);

            Log::info('Medical report retrieved successfully', [
                'report_id' => $report->id,
                'user_id' => $request->user()?->id,
            ]);

            return ApiResponse::success(ReportResource::make($report), 'Medical report retrieved.');
        } catch (\Throwable $e) {
            Log::error('Error retrieving medical report', [
                'report_id' => $id,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Medical report not found or access denied.', Response::HTTP_NOT_FOUND);
        }
    }

    public function update(ReportUpdateRequest $request, string $id): JsonResponse
    {
        try {
            $report = $request->user()->medicalReports()->findOrFail($id);

            DB::transaction(function () use ($request, $report): void {
                $updateData = array_filter(
                    $request->only([
                        'report_profile_id',
                        'report_category_id',
                        'title',
                        'doctor_name',
                        'hospital_name',
                        'report_date',
                    ]),
                    static fn ($value) => $value !== null
                );

                if ($request->hasFile('file')) {
                    $file = $request->file('file');
                    $fileHash = hash_file('sha256', $file->getRealPath());

                    // Delete old file from Azure Storage
                    $this->deleteAzureFile($report->file_url);

                    // Apply pending attribute changes (e.g. title) in-memory first, so the new
                    // blob is named using the report's up-to-date title, not the stale one.
                    $report->fill($updateData);

                    $uploadedFile = $this->reportFileService->uploadReportFile($report, $file, $request->user()->id);

                    $updateData['file_url'] = $uploadedFile['url'];
                    $updateData['file_hash'] = $fileHash;
                    $updateData['report_type'] = $uploadedFile['format'];
                }

                $report->update($updateData);
            });

            Log::info('Medical report updated successfully', [
                'report_id' => $report->id,
                'user_id' => $request->user()?->id,
            ]);

            return ApiResponse::success(ReportResource::make($report->load('category')), 'Medical report updated.');
        } catch (\Throwable $e) {
            Log::error('Error updating medical report', [
                'report_id' => $id,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('An error occurred while updating the report.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $report = $request->user()->medicalReports()->findOrFail($id);

            DB::transaction(function () use ($report): void {
                // Delete file from Azure Storage
                $this->deleteAzureFile($report->file_url);

                // Soft delete from database
                $report->delete();
            });

            Log::info('Medical report deleted successfully', [
                'report_id' => $id,
                'user_id' => $request->user()?->id,
            ]);

            return ApiResponse::success(null, 'Medical report deleted successfully.');
        } catch (\Throwable $e) {
            Log::error('Error deleting medical report', [
                'report_id' => $id,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Medical report not found or access denied.', Response::HTTP_NOT_FOUND);
        }
    }

    public function showFile(Request $request, string $id)
    {
        try {
            $report = $request->user()->medicalReports()->findOrFail($id);
            $url = $report->file_url;

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
            Log::error('Error displaying report file', [
                'report_id' => $id,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Medical report file not found or access denied.', Response::HTTP_NOT_FOUND);
        }
    }

    private function deleteAzureFile(string $url): void
    {
        try {
            $parsed = parse_url($url, PHP_URL_PATH);
            if ($parsed) {
                $parts = explode('/', trim($parsed, '/'));
                if (count($parts) > 1) {
                    array_shift($parts); // Remove container name
                    $blobName = implode('/', $parts);
                    $this->azureBlobService->deleteFile($blobName);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to delete old Azure file', ['url' => $url, 'error' => $e->getMessage()]);
        }
    }
}

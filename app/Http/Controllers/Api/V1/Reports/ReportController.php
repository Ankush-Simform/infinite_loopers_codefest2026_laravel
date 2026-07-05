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
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class ReportController extends Controller
{
    public function __construct(
        protected AzureBlobService $azureBlobService
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $query = $user->medicalReports()->with('category');

            if ($request->filled('profile_id')) {
                $query->where('profile_id', $request->query('profile_id'));
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

            Log::info('Medical reports listed successfully with filters', [
                'user_id' => $user->id,
                'count' => $reports->count(),
                'filters' => $request->only(['profile_id', 'category_id', 'report_type', 'status', 'search']),
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
            $duplicate = MedicalReport::where('profile_id', $request->profile_id)
                ->where('file_hash', $fileHash)
                ->first();

            if ($duplicate) {
                Log::warning('Duplicate report upload attempted', [
                    'profile_id' => $request->profile_id,
                    'file_hash' => $fileHash,
                ]);

                return ApiResponse::error('This file has already been uploaded for this profile.', Response::HTTP_CONFLICT);
            }

            $uploadedFile = $this->azureBlobService->uploadFile($file, 'medical_reports');

            $report = DB::transaction(function () use ($request, $uploadedFile, $fileHash) {
                $report = MedicalReport::create([
                    'profile_id' => $request->profile_id,
                    'report_category_id' => $request->report_category_id,
                    'title' => $request->title,
                    'report_type' => $uploadedFile['format'],
                    'doctor_name' => $request->doctor_name,
                    'hospital_name' => $request->hospital_name,
                    'report_date' => $request->report_date,
                    'file_url' => $uploadedFile['url'],
                    'file_hash' => $fileHash,
                    'status' => ReportStatus::UPLOADED,
                ]);

                // Create a TimelineEvent for this report upload
                $report->timelineEvents()->create([
                    'profile_id' => $report->profile_id,
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
                (int) $report->profile_id,
                (int) $request->user()->id
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

    public function show(Request $request, int $id): JsonResponse
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

    public function update(ReportUpdateRequest $request, int $id): JsonResponse
    {
        try {
            $report = $request->user()->medicalReports()->findOrFail($id);

            DB::transaction(function () use ($request, $report): void {
                $updateData = array_filter(
                    $request->only([
                        'profile_id',
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

                    // Upload new file
                    $uploadedFile = $this->azureBlobService->uploadFile($file, 'medical_reports');

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

    public function destroy(Request $request, int $id): JsonResponse
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

    public function showFile(Request $request, int $id)
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

            $fileData = $this->azureBlobService->getFile($blobName);

            return response($fileData['content'], 200)
                ->header('Content-Type', $fileData['mime_type'])
                ->header('Content-Disposition', 'inline; filename="'.basename($blobName).'"');
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
        $this->azureBlobService->deleteFileByUrl($url);
    }
}

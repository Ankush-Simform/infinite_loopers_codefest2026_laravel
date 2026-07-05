<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\ReportStatus;
use App\Models\MedicalReport;
use App\Services\AzureBlobService;
use Illuminate\Http\UploadedFile;

class MedicalReportFileService
{
    public function __construct(
        protected AzureBlobService $azureBlobService
    ) {}

    /**
     * Persist a draft report row before the file is uploaded to Azure. This reserves
     * a unique reference_id up front, so a duplicate reference id can never be
     * assigned to two reports created at the same time.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createDraft(array $attributes): MedicalReport
    {
        return MedicalReport::create([
            ...$attributes,
            'reference_id' => MedicalReport::nextReferenceId(),
            'status' => ReportStatus::DRAFT,
        ]);
    }

    /**
     * Upload the report's file to Azure Blob Storage, named and tagged using the
     * report's own reference id and title.
     *
     * @return array{url: string, public_id: string, format: string, bytes: int}
     */
    public function uploadReportFile(MedicalReport $report, UploadedFile $file, string $userId): array
    {
        $blobFilename = AzureBlobService::buildReportBlobFilename($report->reference_id, $report->title);

        return $this->azureBlobService->uploadFile(
            $file,
            AzureBlobService::userReportsFolder($userId),
            [
                'user_id' => $userId,
                'report_id' => $report->id,
                'reference_id' => $report->reference_id,
                'report_profile_id' => $report->report_profile_id,
            ],
            $blobFilename
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Reports;

use App\Http\Controllers\Controller;
use App\Models\ReportCategory;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

final class ReportCategoryController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            $categories = ReportCategory::orderBy('name')->get();
            Log::info('Report categories retrieved successfully', ['count' => $categories->count()]);
            return ApiResponse::success($categories, 'Report categories retrieved.');
        } catch (\Throwable $e) {
            Log::error('Error retrieving report categories', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ApiResponse::error('Failed to retrieve categories.', 500);
        }
    }
}

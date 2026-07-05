<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Profile;

use App\Enums\Gender;
use App\Enums\ProfileRelation;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ReportProfileResource;
use App\Models\ReportProfile;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

final class ReportProfileController extends Controller
{
    /**
     * List all report profiles belonging to the user.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $profiles = $user->reportProfiles()->latest()->get();

            Log::info('Report profiles listed successfully', [
                'user_id' => $user->id,
                'count' => $profiles->count(),
            ]);

            return ApiResponse::success(ReportProfileResource::collection($profiles, true), 'Report profiles retrieved.');
        } catch (\Throwable $e) {
            Log::error('Error listing report profiles', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);
            return ApiResponse::error('Failed to retrieve report profiles.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Store a new report profile.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $request->validate([
                'relation' => ['required', 'string', Rule::in(array_column(ProfileRelation::cases(), 'value'))],
                'name' => ['required', 'string', 'max:255'],
                'email' => ['nullable', 'email', 'max:255'],
                'blood_group' => ['nullable', 'string', 'max:5'],
                'date_of_birth' => ['nullable', 'date', 'before:today'],
                'gender' => ['nullable', 'string', Rule::in(array_column(Gender::cases(), 'value'))],
                'height_cm' => ['nullable', 'numeric', 'min:0'],
                'weight_kg' => ['nullable', 'numeric', 'min:0'],
            ]);

            $profile = $user->reportProfiles()->create($request->only([
                'relation',
                'name',
                'email',
                'blood_group',
                'date_of_birth',
                'gender',
                'height_cm',
                'weight_kg',
            ]));

            Log::info('Report profile created successfully', [
                'user_id' => $user->id,
                'report_profile_id' => $profile->id,
            ]);

            return ApiResponse::success(new ReportProfileResource($profile), 'Report profile created.', Response::HTTP_CREATED);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'meta' => [],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $e) {
            Log::error('Error creating report profile', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);
            return ApiResponse::error('Failed to create report profile.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Show a specific report profile.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $profile = $request->user()->reportProfiles()->findOrFail($id);

            Log::info('Report profile retrieved successfully', [
                'user_id' => $request->user()->id,
                'report_profile_id' => $profile->id,
            ]);

            return ApiResponse::success(new ReportProfileResource($profile), 'Report profile retrieved.');
        } catch (\Throwable $e) {
            Log::error('Error retrieving report profile details', [
                'report_profile_id' => $id,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);
            return ApiResponse::error('Report profile not found or access denied.', Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Update a specific report profile.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $profile = $user->reportProfiles()->findOrFail($id);

            $request->validate([
                'relation' => ['sometimes', 'required', 'string', Rule::in(array_column(ProfileRelation::cases(), 'value'))],
                'name' => ['sometimes', 'required', 'string', 'max:255'],
                'email' => ['nullable', 'email', 'max:255'],
                'blood_group' => ['nullable', 'string', 'max:5'],
                'date_of_birth' => ['nullable', 'date', 'before:today'],
                'gender' => ['nullable', 'string', Rule::in(array_column(Gender::cases(), 'value'))],
                'height_cm' => ['nullable', 'numeric', 'min:0'],
                'weight_kg' => ['nullable', 'numeric', 'min:0'],
            ]);

            $profile->update($request->only([
                'relation',
                'name',
                'email',
                'blood_group',
                'date_of_birth',
                'gender',
                'height_cm',
                'weight_kg',
            ]));

            Log::info('Report profile updated successfully', [
                'user_id' => $user->id,
                'report_profile_id' => $profile->id,
            ]);

            return ApiResponse::success(new ReportProfileResource($profile), 'Report profile updated.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'meta' => [],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $e) {
            Log::error('Error updating report profile', [
                'report_profile_id' => $id,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);
            return ApiResponse::error('Failed to update report profile.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete a report profile.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $profile = $user->reportProfiles()->findOrFail($id);

            // Fail if deleting would orphan medical reports
            if ($profile->medicalReports()->exists()) {
                Log::warning('Report profile deletion blocked: has associated reports', [
                    'report_profile_id' => $id,
                    'user_id' => $user->id,
                ]);
                return ApiResponse::error('Cannot delete report profile with associated medical reports.', Response::HTTP_CONFLICT);
            }

            $profile->delete();

            Log::info('Report profile deleted successfully', [
                'report_profile_id' => $id,
                'user_id' => $user->id,
            ]);

            return ApiResponse::success(null, 'Report profile deleted successfully.');
        } catch (\Throwable $e) {
            Log::error('Error deleting report profile', [
                'report_profile_id' => $id,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);
            return ApiResponse::error('Report profile not found or access denied.', Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Get relations and genders enums for report profile selection.
     */
    public function getEnums(Request $request): JsonResponse
    {
        try {
            $relations = array_map(fn (ProfileRelation $case) => [
                'value' => $case->value,
                'label' => ucfirst($case->value),
            ], ProfileRelation::cases());

            $genders = array_map(fn (Gender $case) => [
                'value' => $case->value,
                'label' => $case->value,
            ], Gender::cases());

            return ApiResponse::success([
                'relations' => $relations,
                'genders' => $genders,
            ], 'Enums retrieved successfully.');
        } catch (\Throwable $e) {
            Log::error('Error retrieving enums', [
                'error' => $e->getMessage(),
            ]);
            return ApiResponse::error('Failed to retrieve enums.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

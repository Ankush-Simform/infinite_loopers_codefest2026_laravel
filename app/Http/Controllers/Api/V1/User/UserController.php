<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Enums\Gender;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserProfileResource;
use App\Services\AzureBlobService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

final class UserController extends Controller
{
    public function __construct(
        protected AzureBlobService $azureBlobService
    ) {}

    /**
     * Get the authenticated user's complete profile.
     */
    public function show(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            return ApiResponse::success(UserProfileResource::make($user), 'User profile retrieved.');
        } catch (\Throwable $e) {
            Log::error('Error retrieving user profile', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);
            return ApiResponse::error('Failed to retrieve profile.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update user profile information.
     */
    public function update(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $request->validate([
                'name' => ['sometimes', 'required', 'string', 'max:255'],
                'phone' => ['nullable', 'string', 'max:20'],
                'avatar' => ['nullable', 'image', 'max:10240'],
                'blood_group' => ['nullable', 'string', 'max:5'],
                'date_of_birth' => ['nullable', 'date', 'before:today'],
                'gender' => ['nullable', 'string', Rule::in(array_column(Gender::cases(), 'value'))],
                'height_cm' => ['nullable', 'numeric', 'min:0'],
                'weight_kg' => ['nullable', 'numeric', 'min:0'],
                'emergency_contact_name' => ['nullable', 'string', 'max:255'],
                'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            ]);

            $userData = $request->only([
                'name',
                'phone',
                'blood_group',
                'date_of_birth',
                'gender',
                'height_cm',
                'weight_kg',
                'emergency_contact_name',
                'emergency_contact_phone',
            ]);

            // Handle avatar upload if provided
            if ($request->hasFile('avatar')) {
                if ($user->avatar) {
                    $this->deleteAzureFile($user->avatar);
                }
                $uploaded = $this->azureBlobService->uploadFile(
                    $request->file('avatar'),
                    AzureBlobService::userProfileFolder($user->id),
                    ['user_id' => $user->id, 'purpose' => 'avatar']
                );
                $userData['avatar'] = $uploaded['url'];
            }

            $user->update($userData);

            Log::info('User profile updated successfully', [
                'user_id' => $user->id,
            ]);

            return ApiResponse::success(UserProfileResource::make($user), 'Profile updated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'meta' => [],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $e) {
            Log::error('Error updating user profile', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ApiResponse::error('Failed to update profile.', Response::HTTP_INTERNAL_SERVER_ERROR);
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

    /**
     * Update the authenticated user's password.
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $rules = [
                'password' => ['required', 'string', 'min:8', 'confirmed'],
            ];

            // If user has a password set, old_password is required
            if ($user->password !== null) {
                $rules['old_password'] = ['required', 'string'];
            }

            $request->validate($rules);

            // Verify old password if applicable
            if ($user->password !== null) {
                if (!Hash::check($request->old_password, $user->password)) {
                    Log::warning('Password update failed: Old password incorrect', ['user_id' => $user->id]);
                    return ApiResponse::error('The provided old password is incorrect.', Response::HTTP_UNPROCESSABLE_ENTITY);
                }
            }

            // Update user password
            $user->update([
                'password' => Hash::make($request->password),
            ]);

            Log::info('User password updated successfully', ['user_id' => $user->id]);

            return ApiResponse::success(null, 'Password updated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'meta' => [],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $e) {
            Log::error('Password update failed with exception', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return ApiResponse::error('An error occurred while updating the password.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

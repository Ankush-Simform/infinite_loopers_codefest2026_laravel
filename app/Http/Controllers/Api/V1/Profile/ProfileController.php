<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Profile\ProfileStoreRequest;
use App\Http\Requests\Api\V1\Profile\ProfileUpdateRequest;
use App\Http\Resources\Api\V1\ProfileResource;
use App\Support\ApiResponse;
use App\Services\CloudinaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

final class ProfileController extends Controller
{
    public function __construct(
        protected CloudinaryService $cloudinaryService
    ) {}

    /**
     * List all profiles belonging to the user.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $profiles = $user->profiles()->latest()->get();

            Log::info('User profiles listed successfully', [
                'user_id' => $user->id,
                'count' => $profiles->count(),
            ]);

            return ApiResponse::success(ProfileResource::collection($profiles), 'Profiles retrieved.');
        } catch (\Throwable $e) {
            Log::error('Error listing profiles', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);
            return ApiResponse::error('Failed to retrieve profiles.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get the primary (self) profile.
     */
    public function showSelf(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $profile = $user->profile;

            if ($profile === null) {
                Log::info('Self profile retrieve attempted but none found', ['user_id' => $user->id]);
                return ApiResponse::success(null, 'No profile found.');
            }

            Log::info('Self profile retrieved successfully', ['user_id' => $user->id, 'profile_id' => $profile->id]);
            return ApiResponse::success(ProfileResource::make($profile), 'Profile retrieved.');
        } catch (\Throwable $e) {
            Log::error('Error retrieving self profile', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);
            return ApiResponse::error('An error occurred while retrieving the profile.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Store a new profile.
     */
    public function store(ProfileStoreRequest $request): JsonResponse
    {
        try {
            $user = $request->user();

            $profile = DB::transaction(function () use ($user, $request) {
                // If relation is 'self', update user's emergency contact name and phone
                if ($request->relation === 'self') {
                    $user->update(array_filter(
                        $request->only(['emergency_contact_name', 'emergency_contact_phone']),
                        static fn ($value) => $value !== null
                    ));
                }

                // Create profile record (allows multiple profiles)
                $profile = $user->profiles()->create($this->profileData($request));

                if ($request->hasFile('profile_photo')) {
                    $this->saveProfilePhoto($profile, $request->file('profile_photo'));
                }

                return $profile;
            });

            Log::info('Profile created successfully', [
                'user_id' => $user->id,
                'profile_id' => $profile->id,
            ]);

            return ApiResponse::success(ProfileResource::make($profile->load('user')), 'Profile created.', Response::HTTP_CREATED);
        } catch (\Throwable $e) {
            Log::error('Error creating profile', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ApiResponse::error('An error occurred while creating the profile.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Show a specific profile details.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $profile = $request->user()->profiles()->findOrFail($id);

            Log::info('Profile retrieved successfully', [
                'user_id' => $request->user()->id,
                'profile_id' => $profile->id,
            ]);

            return ApiResponse::success(ProfileResource::make($profile), 'Profile retrieved.');
        } catch (\Throwable $e) {
            Log::error('Error retrieving profile details', [
                'profile_id' => $id,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);
            return ApiResponse::error('Profile not found or access denied.', Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Update a specific profile details.
     */
    public function update(ProfileUpdateRequest $request, int $id): JsonResponse
    {
        try {
            $user = $request->user();

            $profile = DB::transaction(function () use ($user, $request, $id) {
                $profile = $user->profiles()->findOrFail($id);
                $profile->update($this->profileData($request));

                // If relation is 'self', update user's emergency contact details
                if ($profile->relation->value === 'self') {
                    $user->update(array_filter(
                        $request->only(['emergency_contact_name', 'emergency_contact_phone']),
                        static fn ($value) => $value !== null
                    ));
                }

                if ($request->hasFile('profile_photo')) {
                    $this->saveProfilePhoto($profile, $request->file('profile_photo'));
                }

                return $profile;
            });

            Log::info('Profile updated successfully', [
                'user_id' => $user->id,
                'profile_id' => $profile->id,
            ]);

            return ApiResponse::success(ProfileResource::make($profile->load('user')), 'Profile updated.');
        } catch (\Throwable $e) {
            Log::error('Error updating profile details', [
                'profile_id' => $id,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ApiResponse::error('An error occurred while updating the profile.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete a specific profile.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $user = $request->user();
            $profile = $user->profiles()->findOrFail($id);

            DB::transaction(function () use ($profile): void {
                if ($profile->profile_photo_path) {
                    $this->deleteCloudinaryFile($profile->profile_photo_path);
                }
                $profile->delete();
            });

            Log::info('Profile deleted successfully', [
                'profile_id' => $id,
                'user_id' => $user->id,
            ]);

            return ApiResponse::success(null, 'Profile deleted successfully.');
        } catch (\Throwable $e) {
            Log::error('Error deleting profile', [
                'profile_id' => $id,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);
            return ApiResponse::error('Profile not found or access denied.', Response::HTTP_NOT_FOUND);
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

    /**
     * Get relations and genders enums for profile selection.
     */
    public function getEnums(Request $request): JsonResponse
    {
        try {
            $relations = array_map(fn (\App\Enums\ProfileRelation $case) => [
                'value' => $case->value,
                'label' => ucfirst($case->value),
            ], \App\Enums\ProfileRelation::cases());

            $genders = array_map(fn (\App\Enums\Gender $case) => [
                'value' => $case->value,
                'label' => $case->value,
            ], \App\Enums\Gender::cases());

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

    private function profileData(Request $request): array
    {
        return array_filter(
            $request->only([
                'name',
                'email',
                'relation',
                'blood_group',
                'date_of_birth',
                'gender',
                'height_cm',
                'weight_kg',
            ]),
            static fn ($value) => $value !== null
        );
    }

    private function saveProfilePhoto($profile, $file): void
    {
        if ($profile->profile_photo_path) {
            $this->deleteCloudinaryFile($profile->profile_photo_path);
        }

        $uploaded = $this->cloudinaryService->uploadFile($file, 'profiles');
        $profile->profile_photo_path = $uploaded['url'];
        $profile->save();
    }

    private function deleteCloudinaryFile(string $url): void
    {
        try {
            $parsed = parse_url($url, PHP_URL_PATH);
            if ($parsed) {
                $segments = explode('/', trim($parsed, '/'));
                $uploadIndex = array_search('upload', $segments);
                if ($uploadIndex !== false && isset($segments[$uploadIndex + 1])) {
                    $publicIdSegments = array_slice($segments, $uploadIndex + 1);
                    if (preg_match('/^v\d+$/', $publicIdSegments[0])) {
                        array_shift($publicIdSegments);
                    }
                    $last = array_pop($publicIdSegments);
                    $filename = pathinfo($last, PATHINFO_FILENAME);
                    $publicIdSegments[] = $filename;
                    $publicId = implode('/', $publicIdSegments);

                    $this->cloudinaryService->deleteFile($publicId);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to delete old cloudinary file', ['url' => $url, 'error' => $e->getMessage()]);
        }
    }
}

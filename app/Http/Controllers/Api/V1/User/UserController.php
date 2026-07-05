<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Enums\ProfileRelation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\User\UserUpdateRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\AzureBlobService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class UserController extends Controller
{
    public function __construct(
        protected AzureBlobService $azureBlobService
    ) {}

    public function show(Request $request): JsonResponse
    {
        try {
            return ApiResponse::success(
                UserResource::make($request->user()->load('profile')),
                'User profile retrieved.'
            );
        } catch (\Throwable $e) {
            Log::error('Failed to retrieve user profile', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to retrieve user profile.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UserUpdateRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $avatarUrl = null;

            $data = [];
            foreach (['name', 'email', 'phone', 'emergency_contact_name', 'emergency_contact_phone'] as $field) {
                if ($request->has($field)) {
                    $data[$field] = $request->input($field);
                }
            }

            if ($request->hasFile('avatar')) {
                if ($user->avatar) {
                    $this->azureBlobService->deleteFileByUrl($user->avatar);
                }

                $uploaded = $this->azureBlobService->uploadFile($request->file('avatar'), 'avatars');
                $data['avatar'] = $uploaded['url'];
                $avatarUrl = $uploaded['url'];
            }

            DB::transaction(function () use ($user, $data, $request, $avatarUrl): void {
                if ($data !== []) {
                    $user->update($data);
                }

                $profile = $user->profiles()
                    ->where('relation', ProfileRelation::SELF->value)
                    ->first();

                if ($profile === null) {
                    $profile = $user->profiles()->make([
                        'name' => $user->name,
                        'email' => $user->email,
                        'relation' => ProfileRelation::SELF->value,
                    ]);
                }

                $profileData = [];
                foreach (['name', 'email', 'blood_group', 'date_of_birth', 'gender', 'height_cm', 'weight_kg'] as $field) {
                    if ($request->has($field)) {
                        $profileData[$field] = $request->input($field);
                    }
                }

                if ($avatarUrl !== null) {
                    $profileData['profile_photo_path'] = $avatarUrl;
                }

                if (! $profile->exists || $profileData !== []) {
                    $profile->fill($profileData);
                    $profile->save();
                }
            });

            Log::info('User profile updated successfully', ['user_id' => $user->id]);

            return ApiResponse::success(
                UserResource::make($user->fresh()->load('profile')),
                'User profile updated.'
            );
        } catch (\Throwable $e) {
            Log::error('Failed to update user profile', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Failed to update user profile.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

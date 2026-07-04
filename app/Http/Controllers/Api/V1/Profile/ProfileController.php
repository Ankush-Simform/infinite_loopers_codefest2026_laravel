<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Profile\ProfileStoreRequest;
use App\Http\Requests\Api\V1\Profile\ProfileUpdateRequest;
use App\Http\Resources\Api\V1\ProfileResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

final class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $profile = $request->user()->profile;

        if ($profile === null) {
            return ApiResponse::success(null, 'No profile found.');
        }

        return ApiResponse::success(ProfileResource::make($profile), 'Profile retrieved.');
    }

    public function store(ProfileStoreRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->profile) {
            return ApiResponse::error('Profile already exists. Use update to modify.', 409);
        }

        $profile = $user->profile()->create($this->profileData($request));

        if ($request->hasFile('profile_photo')) {
            $this->saveProfilePhoto($profile, $request->file('profile_photo'));
        }

        return ApiResponse::success(ProfileResource::make($profile), 'Profile created.', 201);
    }

    public function update(ProfileUpdateRequest $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->profile ?? $user->profile()->create(['user_id' => $user->id]);

        $profile->update($this->profileData($request));

        if ($request->hasFile('profile_photo')) {
            $this->saveProfilePhoto($profile, $request->file('profile_photo'));
        }

        return ApiResponse::success(ProfileResource::make($profile), 'Profile updated.');
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
                'emergency_contact_name',
                'emergency_contact_phone',
            ]),
            static fn ($value) => $value !== null
        );
    }

    private function saveProfilePhoto($profile, $file): void
    {
        if ($profile->profile_photo_path) {
            Storage::disk('public')->delete($profile->profile_photo_path);
        }

        $profile->profile_photo_path = $file->store('profile_photos', 'public');
        $profile->save();
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Devices;

use App\Http\Controllers\Controller;
use App\Models\UserDevice;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class DeviceController extends Controller
{
    /**
     * Register or update a device FCM token.
     */
    public function register(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'fcm_token' => 'required|string|max:500',
                'device_name' => 'nullable|string|max:255',
                'platform' => 'nullable|string|max:20|in:android,ios,web',
                'app_version' => 'nullable|string|max:50',
            ]);

            $user = $request->user();

            $device = UserDevice::updateOrCreate(
                ['fcm_token' => $request->fcm_token],
                [
                    'user_id' => $user->id,
                    'device_name' => $request->device_name,
                    'platform' => $request->platform,
                    'app_version' => $request->app_version,
                    'last_used_at' => now(),
                    'is_active' => true,
                ]
            );

            Log::info('Device token registered/updated successfully', [
                'user_id' => $user->id,
                'device_id' => $device->id,
            ]);

            return ApiResponse::success(
                data: $device,
                message: 'Device token registered successfully.'
            );
        } catch (\Throwable $e) {
            Log::error('Device token registration failed', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error(
                message: 'Failed to register device token.',
                status: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * De-register a device FCM token.
     */
    public function deregister(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'fcm_token' => 'required|string|max:500',
            ]);

            $device = UserDevice::where('fcm_token', $request->fcm_token)->first();

            if ($device) {
                $device->delete();
                Log::info('Device token de-registered successfully', [
                    'device_id' => $device->id,
                    'user_id' => $device->user_id,
                ]);
            }

            return ApiResponse::success(
                message: 'Device token de-registered successfully.'
            );
        } catch (\Throwable $e) {
            Log::error('Device token de-registration failed', [
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error(
                message: 'Failed to de-register device token.',
                status: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Notifications;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class NotificationController extends Controller
{
    /**
     * List user notifications (paginated).
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $query = $user->notifications();

            if ($request->boolean('unread_only')) {
                $query->whereNull('read_at');
            }

            $notifications = $query->latest('id')->paginate($request->query('per_page', 15));

            Log::info('User notifications listed successfully', [
                'user_id' => $user->id,
                'count' => $notifications->count(),
            ]);

            return ApiResponse::paginated($notifications, 'Notifications retrieved.');
        } catch (\Throwable $e) {
            Log::error('Listing notifications failed', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error(
                message: 'Failed to retrieve notifications.',
                status: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Mark a single notification as read.
     */
    public function read(Request $request, int $id): JsonResponse
    {
        try {
            $user = $request->user();
            $notification = $user->notifications()->findOrFail($id);

            if ($notification->read_at === null) {
                $notification->update(['read_at' => now()]);
            }

            Log::info('Notification marked as read', [
                'user_id' => $user->id,
                'notification_id' => $id,
            ]);

            return ApiResponse::success(
                data: $notification,
                message: 'Notification marked as read.'
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error(
                message: 'Notification not found.',
                status: Response::HTTP_NOT_FOUND
            );
        } catch (\Throwable $e) {
            Log::error('Marking notification as read failed', [
                'user_id' => $request->user()?->id,
                'notification_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error(
                message: 'Failed to mark notification as read.',
                status: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Mark all unread notifications as read.
     */
    public function readAll(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $count = $user->notifications()
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

            Log::info('All notifications marked as read', [
                'user_id' => $user->id,
                'count' => $count,
            ]);

            return ApiResponse::success(
                message: 'All notifications marked as read.'
            );
        } catch (\Throwable $e) {
            Log::error('Marking all notifications as read failed', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error(
                message: 'Failed to mark all notifications as read.',
                status: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}

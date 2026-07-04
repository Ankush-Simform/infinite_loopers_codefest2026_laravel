<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Timeline;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Timeline\TimelineStoreRequest;
use App\Http\Requests\Api\V1\Timeline\TimelineUpdateRequest;
use App\Http\Resources\Api\V1\TimelineResource;
use App\Models\TimelineEvent;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class TimelineController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $query = $user->timelineEvents();

            if ($request->filled('profile_id')) {
                $query->where('profile_id', $request->query('profile_id'));
            }

            if ($request->filled('search')) {
                $search = $request->query('search');
                $query->where(function ($q) use ($search): void {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }

            $events = $query->latest('event_date')
                ->latest('id')
                ->paginate($request->query('per_page', 20));

            Log::info('Timeline events listed successfully', [
                'user_id' => $user->id,
                'count' => $events->count(),
            ]);

            return ApiResponse::paginated($events, 'Timeline events retrieved.');
        } catch (\Throwable $e) {
            Log::error('Error listing timeline events', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('An error occurred while listing timeline events.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(TimelineStoreRequest $request): JsonResponse
    {
        try {
            $event = TimelineEvent::create([
                'profile_id' => $request->profile_id,
                'event_type' => $request->event_type,
                'title' => $request->title,
                'description' => $request->description,
                'event_date' => $request->event_date,
                'importance' => $request->importance,
            ]);

            Log::info('Timeline event created successfully', [
                'event_id' => $event->id,
                'profile_id' => $event->profile_id,
            ]);

            return ApiResponse::success(TimelineResource::make($event), 'Timeline event created successfully.', Response::HTTP_CREATED);
        } catch (\Throwable $e) {
            Log::error('Error creating timeline event', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('An error occurred while creating timeline event.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $event = $request->user()->timelineEvents()->findOrFail($id);

            Log::info('Timeline event retrieved successfully', [
                'event_id' => $event->id,
                'user_id' => $request->user()?->id,
            ]);

            return ApiResponse::success(TimelineResource::make($event), 'Timeline event retrieved.');
        } catch (\Throwable $e) {
            Log::error('Error retrieving timeline event', [
                'event_id' => $id,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Timeline event not found or access denied.', Response::HTTP_NOT_FOUND);
        }
    }

    public function update(TimelineUpdateRequest $request, int $id): JsonResponse
    {
        try {
            $event = $request->user()->timelineEvents()->findOrFail($id);

            $updateData = array_filter(
                $request->only([
                    'profile_id',
                    'event_type',
                    'title',
                    'description',
                    'event_date',
                    'importance',
                ]),
                static fn ($value) => $value !== null
            );

            $event->update($updateData);

            Log::info('Timeline event updated successfully', [
                'event_id' => $event->id,
                'user_id' => $request->user()?->id,
            ]);

            return ApiResponse::success(TimelineResource::make($event), 'Timeline event updated.');
        } catch (\Throwable $e) {
            Log::error('Error updating timeline event', [
                'event_id' => $id,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('An error occurred while updating the timeline event.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $event = $request->user()->timelineEvents()->findOrFail($id);
            $event->delete();

            Log::info('Timeline event deleted successfully', [
                'event_id' => $id,
                'user_id' => $request->user()?->id,
            ]);

            return ApiResponse::success(null, 'Timeline event deleted successfully.');
        } catch (\Throwable $e) {
            Log::error('Error deleting timeline event', [
                'event_id' => $id,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Timeline event not found or access denied.', Response::HTTP_NOT_FOUND);
        }
    }
}

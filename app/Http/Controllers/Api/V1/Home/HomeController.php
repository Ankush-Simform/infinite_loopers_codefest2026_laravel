<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Home;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProfileResource;
use App\Http\Resources\Api\V1\Reports\ReportResource;
use App\Http\Resources\Api\V1\TimelineResource;
use App\Http\Resources\Api\V1\Chat\ChatSessionResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class HomeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            // Get the primary profile (or first one)
            $profile = $user->profile ?? $user->profiles()->first();

            $data = [
                'profile' => $profile ? ProfileResource::make($profile) : null,
                'stats' => [
                    'total_reports' => $profile ? $profile->medicalReports()->count() : 0,
                    'latest_report_date' => $profile ? $profile->medicalReports()->latest('report_date')->value('report_date')?->toDateString() : null,
                    'total_chats' => $user->chatSessions()->count(),
                ],
                'latest_reports' => $profile 
                    ? ReportResource::collection($profile->medicalReports()->with('category')->latest('report_date')->limit(3)->get()) 
                    : [],
                'latest_timeline' => $profile 
                    ? TimelineResource::collection($profile->timelineEvents()->latest('event_date')->latest('id')->limit(3)->get()) 
                    : [],
                'recent_chat' => ($recentChatSession = $user->chatSessions()->latest('last_message_at')->first())
                    ? ChatSessionResource::make($recentChatSession)
                    : null,
            ];

            Log::info('Home dashboard retrieved successfully', [
                'user_id' => $user->id,
                'profile_id' => $profile?->id,
            ]);

            return ApiResponse::success($data, 'Home dashboard retrieved.');
        } catch (\Throwable $e) {
            Log::error('Error retrieving home dashboard', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Failed to retrieve home dashboard.', 500);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Chat;

use App\Enums\ChatMessageRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Chat\ChatSessionStoreRequest;
use App\Http\Requests\Api\V1\Chat\ChatSessionUpdateRequest;
use App\Http\Requests\Api\V1\Chat\ChatMessageStoreRequest;
use App\Http\Resources\Api\V1\Chat\ChatSessionResource;
use App\Http\Resources\Api\V1\Chat\ChatMessageResource;
use App\Models\ChatSession;
use App\Models\MedicalReport;
use App\Contracts\AiServiceContract;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class ChatController extends Controller
{
    public function __construct(
        protected AiServiceContract $aiService
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $sessions = $user->chatSessions()
                ->latest('last_message_at')
                ->paginate($request->query('per_page', 15));

            Log::info('Chat sessions listed successfully', [
                'user_id' => $user->id,
                'count' => $sessions->count(),
            ]);

            return ApiResponse::paginated($sessions, 'Chat sessions retrieved.');
        } catch (\Throwable $e) {
            Log::error('Error listing chat sessions', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ApiResponse::error('An error occurred while listing chat sessions.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(ChatSessionStoreRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $title = $request->title ?? ('Chat Session ' . now()->format('Y-m-d H:i'));

            $session = ChatSession::create([
                'user_id' => $user->id,
                'title' => $title,
                'last_message_at' => now(),
            ]);

            Log::info('Chat session created successfully', [
                'session_id' => $session->id,
                'user_id' => $user->id,
            ]);

            return ApiResponse::success(ChatSessionResource::make($session), 'Chat session created.', Response::HTTP_CREATED);
        } catch (\Throwable $e) {
            Log::error('Error creating chat session', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ApiResponse::error('An error occurred while creating chat session.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(ChatSessionUpdateRequest $request, int $id): JsonResponse
    {
        try {
            $session = $request->user()->chatSessions()->findOrFail($id);

            $session->update([
                'title' => $request->title,
            ]);

            Log::info('Chat session title updated successfully', [
                'session_id' => $session->id,
                'user_id' => $request->user()->id,
                'new_title' => $request->title,
            ]);

            return ApiResponse::success(ChatSessionResource::make($session), 'Chat session title updated successfully.');
        } catch (\Throwable $e) {
            Log::error('Error updating chat session title', [
                'session_id' => $id,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ApiResponse::error('An error occurred while updating the chat session.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function messages(Request $request, int $id): JsonResponse
    {
        try {
            $session = $request->user()->chatSessions()->findOrFail($id);
            $messages = $session->messages()
                ->oldest()
                ->paginate($request->query('per_page', 50));

            Log::info('Chat messages retrieved successfully', [
                'session_id' => $session->id,
                'user_id' => $request->user()?->id,
                'count' => $messages->count(),
            ]);

            return ApiResponse::paginated($messages, 'Chat messages retrieved.');
        } catch (\Throwable $e) {
            Log::error('Error retrieving chat messages', [
                'session_id' => $id,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);
            return ApiResponse::error('Chat session not found or access denied.', Response::HTTP_NOT_FOUND);
        }
    }

    public function sendMessage(ChatMessageStoreRequest $request, int $id): JsonResponse
    {
        try {
            $user = $request->user();
            $session = $user->chatSessions()->findOrFail($id);

            // Fetch recent conversation history for LLM context (last 10 messages)
            $history = $session->messages()
                ->oldest()
                ->limit(10)
                ->get()
                ->map(fn($msg) => [
                    'role' => $msg->role->value,
                    'content' => $msg->content,
                ])
                ->toArray();

            $reportUrl = null;
            if ($request->report_id) {
                $report = MedicalReport::find($request->report_id);
                if ($report) {
                    $reportUrl = $report->file_url;
                }
            }

            // Perform in a transaction to ensure both messages and session updates are consistent
            $messages = DB::transaction(function () use ($request, $session, $reportUrl): array {
                // 1. Save user's message
                $userMessage = $session->messages()->create([
                    'report_id' => $request->report_id,
                    'role' => ChatMessageRole::USER,
                    'content' => $request->content,
                    'metadata' => $request->metadata,
                ]);

                // Update last_message_at
                $session->update(['last_message_at' => now()]);

                // 2. Call AI Service (binds to Mock/Flask in ServiceProvider)
                $assistantReply = $this->aiService->generateChatResponse(
                    $request->content,
                    $history,
                    $reportUrl
                );

                // 3. Save assistant's message
                $assistantMessage = $session->messages()->create([
                    'report_id' => $request->report_id,
                    'role' => ChatMessageRole::ASSISTANT,
                    'content' => $assistantReply,
                ]);

                return [$userMessage, $assistantMessage];
            });

            Log::info('Chat message sent and assistant reply generated successfully', [
                'session_id' => $session->id,
                'user_id' => $user->id,
            ]);

            return ApiResponse::success([
                'user_message' => ChatMessageResource::make($messages[0]),
                'assistant_message' => ChatMessageResource::make($messages[1]),
            ], 'Message sent and reply generated.');

        } catch (\Throwable $e) {
            Log::error('Error sending chat message', [
                'session_id' => $id,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ApiResponse::error('An error occurred while sending the message.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $session = $request->user()->chatSessions()->findOrFail($id);

            DB::transaction(function () use ($session): void {
                // Delete messages first (or cascade delete handles it, but soft deletes need explicit or cascade)
                $session->messages()->delete();
                $session->delete();
            });

            Log::info('Chat session deleted successfully', [
                'session_id' => $id,
                'user_id' => $request->user()?->id,
            ]);

            return ApiResponse::success(null, 'Chat session deleted successfully.');
        } catch (\Throwable $e) {
            Log::error('Error deleting chat session', [
                'session_id' => $id,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);
            return ApiResponse::error('Chat session not found or access denied.', Response::HTTP_NOT_FOUND);
        }
    }
}

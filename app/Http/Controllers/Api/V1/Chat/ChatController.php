<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Chat;

use App\Contracts\AiServiceContract;
use App\Enums\ChatMessageRole;
use App\Events\ChatMessageStreamed;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Chat\ChatMessageStoreRequest;
use App\Http\Requests\Api\V1\Chat\ChatSessionStoreRequest;
use App\Http\Requests\Api\V1\Chat\ChatSessionUpdateRequest;
use App\Http\Resources\Api\V1\Chat\ChatMessageResource;
use App\Http\Resources\Api\V1\Chat\ChatSessionResource;
use App\Models\ChatSession;
use App\Services\AttachmentDownloadService;
use App\Services\AttachmentUploadService;
use App\Services\ChatService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedJsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ChatController extends Controller
{
    public function __construct(
        protected AiServiceContract $aiService,
        protected ChatService $chatService,
        protected AttachmentUploadService $attachmentUploadService,
        protected AttachmentDownloadService $attachmentDownloadService
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $sessions = $user->chatSessions()
                ->latest('last_message_at')
                ->paginate($request->query('per_page', 15));

            $sessions->setCollection(
                $sessions->getCollection()->map(fn ($session) => new ChatSessionResource($session))
            );

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
            $title = $request->title ?? ('Chat Session '.now()->format('Y-m-d H:i'));

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

    public function update(ChatSessionUpdateRequest $request, string $id): JsonResponse
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

    public function messages(Request $request, string $id): JsonResponse
    {
        try {
            $session = $request->user()->chatSessions()->findOrFail($id);
            $messages = $session->messages()
                ->with('attachments')
                ->oldest()
                ->paginate($request->query('per_page', 50));

            $messages->setCollection(
                $messages->getCollection()->map(fn ($msg) => new ChatMessageResource($msg))
            );

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

    public function sendMessage(ChatMessageStoreRequest $request, string $id): StreamedJsonResponse
    {
        try {
            $user = $request->user();
            $session = $user->chatSessions()->findOrFail($id);

            // Upload attachments and prepare DB records
            $dbAttachments = [];
            $attachmentsMetadata = [];

            if ($request->hasFile('attachments')) {
                // Returns DB-ready arrays, or throws on failure (safely rolling back Azure uploads)
                $dbAttachments = $this->attachmentUploadService->uploadAttachments(
                    $request->file('attachments'),
                    (int) $user->id,
                    (int) $session->id
                );

                // Build metadata format expected by the AI service
                foreach ($dbAttachments as $attachment) {
                    $attachmentsMetadata[] = [
                        'storage_provider' => 'azure_blob',
                        'container' => config('services.azure.storage_container', 'amrv-container'),
                        'blob_name' => $attachment['stored_name'],
                        'original_file_name' => $attachment['original_name'],
                        'mime_type' => $attachment['mime_type'],
                        'size' => $attachment['file_size'],
                    ];
                }
            }

            try {
                $validated = $request->validated();
                $userMessage = $this->chatService->saveUserMessage(
                    $session,
                    $validated['content'],
                    $validated['report_id'] ?? null,
                    $dbAttachments,
                    $validated['metadata'] ?? null
                );
            } catch (\Throwable $e) {
                // If DB insert fails, roll back uploaded Azure files to prevent orphan blobs
                $this->attachmentUploadService->rollbackUploadedBlobs();
                throw $e;
            }

            // Fetch recent conversation history context (last 10 messages before this new one)
            $history = $session->messages()
                ->where('id', '<', $userMessage->id)
                ->latest()
                ->limit(10)
                ->get()
                ->reverse()
                ->map(fn ($msg) => [
                    'role' => $msg->role->value,
                    'content' => $msg->content,
                ])
                ->values()
                ->toArray();

            Log::info('User message saved. Starting AI Service live stream.', [
                'session_id' => $session->id,
                'user_id' => $user->id,
                'message_id' => $userMessage->id,
            ]);

            // Stream response
            return response()->stream(function () use ($user, $session, $userMessage, $history, $attachmentsMetadata) {
                $accumulatedResponse = '';

                $this->aiService->streamChatResponse(
                    (int) $user->id,
                    (int) $session->id,
                    $userMessage->content,
                    $history,
                    $attachmentsMetadata,
                    function ($chunk) use (&$accumulatedResponse, $session) {
                        $accumulatedResponse .= $chunk;

                        // Broadcast live token chunk
                        try {
                            broadcast(new ChatMessageStreamed($session->id, $chunk))->toOthers();
                        } catch (\Throwable $e) {
                            Log::warning('Failed to broadcast live chat chunk', ['error' => $e->getMessage()]);
                        }

                        // Echo chunk directly to client
                        echo $chunk;
                        if (ob_get_level() > 0) {
                            ob_flush();
                        }
                        flush();
                    }
                );

                // Save final assistant response in DB
                if (trim($accumulatedResponse) !== '') {
                    try {
                        $contentToSave = $accumulatedResponse;
                        $decoded = json_decode($accumulatedResponse, true);
                        if (is_array($decoded) && isset($decoded['data']['response_text'])) {
                            $contentToSave = $decoded['data']['response_text'];
                        } elseif (is_array($decoded) && isset($decoded['message'])) {
                            $contentToSave = $decoded['message'];
                        }

                        $session->messages()->create([
                            'role' => ChatMessageRole::ASSISTANT,
                            'content' => $contentToSave,
                        ]);
                        $session->update(['last_message_at' => now()]);
                    } catch (\Throwable $e) {
                        Log::error('Failed to cache assistant reply in database', [
                            'session_id' => $session->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }, 200, [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Cache-Control' => 'no-cache, must-revalidate',
                'Connection' => 'keep-alive',
                'X-Accel-Buffering' => 'no',
            ]);
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

    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $session = $request->user()->chatSessions()->findOrFail($id);

            $this->chatService->deleteSession($session);

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

    public function showAttachment(Request $request, int $id): Response
    {
        try {
            return $this->attachmentDownloadService->streamAttachment($request->user(), $id);
        } catch (NotFoundHttpException $e) {
            return ApiResponse::error($e->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (\Throwable $e) {
            Log::error('Error displaying chat attachment file', [
                'attachment_id' => $id,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Attachment file not found or access denied.', Response::HTTP_NOT_FOUND);
        }
    }
}

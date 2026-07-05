<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Chat;

use Illuminate\Http\Resources\Json\JsonResource;

final class ChatMessageResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'sender' => $this->role instanceof \UnitEnum ? $this->role->value : $this->role,
            'message' => $this->content,
            'created_at' => $this->created_at?->toDateTimeString(),
            'attachments' => $this->attachments->map(fn ($attachment) => [
                'id' => $attachment->id,
                'file_name' => $attachment->original_name,
                'file_type' => $attachment->mime_type,
                'file_size' => $attachment->file_size,
                'file_url' => route('api.v1.chats.attachments.show', ['id' => $attachment->id]),
                'created_at' => $attachment->created_at?->toDateTimeString(),
            ])->toArray(),
        ];
    }
}

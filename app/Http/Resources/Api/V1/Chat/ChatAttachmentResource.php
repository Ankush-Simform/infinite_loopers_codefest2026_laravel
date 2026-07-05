<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Chat;

use Illuminate\Http\Resources\Json\JsonResource;

final class ChatAttachmentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'file_name' => $this->original_name,
            'file_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'file_url' => route('api.v1.chats.attachments.show', ['id' => $this->id]),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}

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
            'attachments' => ChatAttachmentResource::collection($this->attachments),
        ];
    }
}

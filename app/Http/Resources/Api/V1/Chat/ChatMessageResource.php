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
            'chat_session_id' => $this->chat_session_id,
            'report_id' => $this->report_id,
            'role' => $this->role,
            'content' => $this->content,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}

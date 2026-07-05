<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Chat;

use Illuminate\Http\Resources\Json\JsonResource;

final class ChatSessionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'title' => $this->title,
            'last_message_at' => $this->last_message_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}

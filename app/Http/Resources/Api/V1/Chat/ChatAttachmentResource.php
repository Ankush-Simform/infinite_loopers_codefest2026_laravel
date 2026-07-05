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
            'file_url' => (function () {
                $url = route('api.v1.chats.attachments.show', ['id' => $this->id]);
                $token = null;
                $authorization = request()->header('Authorization');
                if ($authorization && str_starts_with($authorization, 'Bearer ')) {
                    $token = substr($authorization, 7);
                } elseif (request()->has('token')) {
                    $token = request()->query('token');
                }

                return $token ? $url.'?token='.$token : $url;
            })(),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}

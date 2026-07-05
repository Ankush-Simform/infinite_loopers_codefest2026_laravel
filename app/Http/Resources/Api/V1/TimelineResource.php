<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

final class TimelineResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'profile_id' => $this->profile_id,
            'report_id' => $this->report_id,
            'event_type' => $this->event_type,
            'title' => $this->title,
            'description' => $this->description,
            'event_date' => $this->event_date?->toDateString(),
            'importance' => $this->importance,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}

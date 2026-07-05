<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

final class ProfileResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'relation' => $this->relation,
            'blood_group' => $this->blood_group,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'gender' => $this->gender,
            'height_cm' => $this->height_cm,
            'weight_kg' => $this->weight_kg,
            'emergency_contact_name' => $this->user?->emergency_contact_name,
            'emergency_contact_phone' => $this->user?->emergency_contact_phone,
            'profile_photo_url' => $this->profile_photo_path ? (str_starts_with($this->profile_photo_path, 'http') ? $this->profile_photo_path : Storage::disk('public')->url($this->profile_photo_path)) : null,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}

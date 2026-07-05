<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

final class UserProfileResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar' => $this->avatar,
            'blood_group' => $this->blood_group,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'gender' => $this->gender?->value ?? $this->gender,
            'height_cm' => $this->height_cm !== null ? (float) $this->height_cm : null,
            'weight_kg' => $this->weight_kg !== null ? (float) $this->weight_kg : null,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
        ];
    }
}

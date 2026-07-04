<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Reports;

use Illuminate\Http\Resources\Json\JsonResource;

final class ReportResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'profile_id' => $this->profile_id,
            'category' => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ] : null,
            'title' => $this->title,
            'report_type' => $this->report_type,
            'doctor_name' => $this->doctor_name,
            'hospital_name' => $this->hospital_name,
            'report_date' => $this->report_date?->toDateString(),
            'file_url' => $this->file_url,
            'status' => $this->status->value,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}

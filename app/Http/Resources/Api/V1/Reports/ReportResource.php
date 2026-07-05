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
            'reference_id' => $this->reference_id,
            'report_profile_id' => $this->report_profile_id,
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
            'file_url' => (function () {
                $url = route('api.v1.reports.file', ['id' => $this->id]);
                $token = null;
                $authorization = request()->header('Authorization');
                if ($authorization && str_starts_with($authorization, 'Bearer ')) {
                    $token = substr($authorization, 7);
                } elseif (request()->has('token')) {
                    $token = request()->query('token');
                }

                return $token ? $url.'?token='.$token : $url;
            })(),
            'status' => $this->status->value,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Reports;

use App\Http\Requests\Api\V1\ApiFormRequest;
use Illuminate\Validation\Rule;

final class ReportUpdateRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'profile_id' => [
                'sometimes',
                Rule::exists('profiles', 'id')->where(function ($query): void {
                    $query->where('user_id', $this->user()?->id);
                }),
            ],
            'report_category_id' => [
                'nullable',
                Rule::exists('report_categories', 'id'),
            ],
            'title' => ['sometimes', 'string', 'max:255'],
            'doctor_name' => ['nullable', 'string', 'max:255'],
            'hospital_name' => ['nullable', 'string', 'max:255'],
            'report_date' => ['nullable', 'date'],
            'file' => [
                'sometimes',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:10240', // Max 10MB
            ],
        ];
    }
}

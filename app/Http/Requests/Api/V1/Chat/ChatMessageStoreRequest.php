<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Chat;

use App\Http\Requests\Api\V1\ApiFormRequest;
use Illuminate\Validation\Rule;

class ChatMessageStoreRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->filled('report_id')) {
            $this->merge([
                'report_id' => (int) $this->input('report_id'),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'content' => ['required_without:attachments', 'nullable', 'string', 'max:65535'],
            'report_id' => [
                'nullable',
                Rule::exists('medical_reports', 'id')->where(function ($query): void {
                    $query->whereIn('report_profile_id', $this->user()?->reportProfiles()->pluck('id')->toArray() ?: []);
                }),
            ],
            'metadata' => ['nullable', 'array'],
            'attachments' => ['nullable', 'array', 'max:2'],
            'attachments.*' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }
}

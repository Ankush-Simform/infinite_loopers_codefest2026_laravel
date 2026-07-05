<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Chat;

use App\Http\Requests\Api\V1\ApiFormRequest;
use Illuminate\Validation\Rule;

final class ChatMessageStoreRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:65535'],
            'report_id' => [
                'nullable',
                Rule::exists('medical_reports', 'id')->where(function ($query): void {
                    $query->whereIn('report_profile_id', $this->user()?->reportProfiles()->pluck('id')->toArray() ?: []);
                }),
            ],
            'metadata' => ['nullable', 'array'],
        ];
    }
}

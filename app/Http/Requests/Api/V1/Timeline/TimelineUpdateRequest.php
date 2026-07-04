<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Timeline;

use App\Http\Requests\Api\V1\ApiFormRequest;
use Illuminate\Validation\Rule;

final class TimelineUpdateRequest extends ApiFormRequest
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
            'event_type' => ['sometimes', 'string', 'max:50'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'event_date' => ['sometimes', 'date'],
            'importance' => ['nullable', 'integer', 'min:0', 'max:5'],
        ];
    }
}

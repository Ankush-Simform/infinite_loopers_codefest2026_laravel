<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Timeline;

use App\Http\Requests\Api\V1\ApiFormRequest;
use Illuminate\Validation\Rule;

final class TimelineStoreRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'report_profile_id' => [
                'required',
                Rule::exists('report_profiles', 'id')->where(function ($query): void {
                    $query->where('user_id', $this->user()?->id);
                }),
            ],
            'event_type' => ['required', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'event_date' => ['required', 'date'],
            'importance' => ['nullable', 'integer', 'min:0', 'max:5'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Profile;

use App\Enums\Gender;
use App\Enums\ProfileRelation;
use App\Http\Requests\Api\V1\ApiFormRequest;
use Illuminate\Validation\Rule;

final class ProfileStoreRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'relation' => ['required', Rule::in(array_map(fn (ProfileRelation $case) => $case->value, ProfileRelation::cases()))],
            'blood_group' => ['nullable', 'string', 'max:5'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', Rule::in(array_map(fn (Gender $case) => $case->value, Gender::cases()))],
            'height_cm' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'weight_kg' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'profile_photo' => ['nullable', 'image', 'max:5120'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\User;

use App\Enums\Gender;
use App\Http\Requests\Api\V1\ApiFormRequest;
use Illuminate\Validation\Rule;

final class UserUpdateRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['nullable', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($userId)],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'avatar' => ['nullable', 'image', 'max:10240'],
            'blood_group' => ['nullable', 'string', 'max:5'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', Rule::in(array_map(fn (Gender $case) => $case->value, Gender::cases()))],
            'height_cm' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'weight_kg' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
        ];
    }
}

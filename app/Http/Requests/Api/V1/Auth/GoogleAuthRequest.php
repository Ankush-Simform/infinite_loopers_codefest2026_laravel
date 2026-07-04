<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use App\Http\Requests\Api\V1\ApiFormRequest;

final class GoogleAuthRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'id_token' => ['required', 'string'],
        ];
    }
}

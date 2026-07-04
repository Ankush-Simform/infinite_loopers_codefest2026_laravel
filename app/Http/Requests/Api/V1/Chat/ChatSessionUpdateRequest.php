<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Chat;

use App\Http\Requests\Api\V1\ApiFormRequest;

final class ChatSessionUpdateRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
        ];
    }
}

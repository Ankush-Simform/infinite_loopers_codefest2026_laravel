<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Chat;

use App\Http\Requests\Api\V1\ApiFormRequest;

class ChatMessageStoreRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'content' => ['required_without:attachments', 'nullable', 'string', 'max:65535'],
            'metadata' => ['nullable', 'array'],
            'attachments' => ['nullable', 'array', 'max:2'],
            'attachments.*' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }
}

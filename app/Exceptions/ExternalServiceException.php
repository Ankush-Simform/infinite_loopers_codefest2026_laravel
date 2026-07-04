<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class ExternalServiceException extends ApiException
{
    /**
     * @param  array<string, mixed>  $errors
     */
    public function __construct(
        string $message = 'External service is unavailable',
        array $errors = [],
        int $statusCode = Response::HTTP_BAD_GATEWAY
    ) {
        parent::__construct($message, $statusCode, $errors);
    }
}

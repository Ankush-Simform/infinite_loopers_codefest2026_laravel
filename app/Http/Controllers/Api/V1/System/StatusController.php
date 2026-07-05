<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\System;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\System\StatusResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class StatusController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return ApiResponse::success(
            data: StatusResource::make([
                'service' => config('app.name'),
                'environment' => config('app.env'),
                'version' => 'v1',
                'status' => 'ok',
            ]),
            message: 'AMRV API is running'
        );
    }
}

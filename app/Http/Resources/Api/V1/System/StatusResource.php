<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\System;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StatusResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'service' => $this->resource['service'],
            'environment' => $this->resource['environment'],
            'version' => $this->resource['version'],
            'status' => $this->resource['status'],
        ];
    }
}

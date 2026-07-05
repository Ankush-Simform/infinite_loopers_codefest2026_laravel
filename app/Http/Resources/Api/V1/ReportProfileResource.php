<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

final class ReportProfileResource extends JsonResource
{
    protected bool $isBrief = false;

    public function __construct($resource, bool $isBrief = false)
    {
        parent::__construct($resource);
        $this->isBrief = $isBrief;
    }

    public static function collection($resource, bool $isBrief = false)
    {
        return new class($resource, $isBrief) extends \Illuminate\Http\Resources\Json\AnonymousResourceCollection {
            protected bool $isBrief;

            public function __construct($resource, bool $isBrief = false)
            {
                parent::__construct($resource, ReportProfileResource::class);
                $this->isBrief = $isBrief;
            }

            public function toArray($request): array
            {
                return $this->collection->map(function ($item) use ($request) {
                    return (new ReportProfileResource($item, $this->isBrief))->toArray($request);
                })->all();
            }
        };
    }

    public function toArray($request): array
    {
        if ($this->isBrief) {
            return [
                'id' => $this->id,
                'relation' => $this->relation->value ?? $this->relation,
                'name' => $this->name,
            ];
        }

        return [
            'id' => $this->id,
            'relation' => $this->relation->value ?? $this->relation,
            'name' => $this->name,
            'email' => $this->email,
            'blood_group' => $this->blood_group,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'gender' => $this->gender?->value ?? $this->gender,
            'height_cm' => $this->height_cm !== null ? (float) $this->height_cm : null,
            'weight_kg' => $this->weight_kg !== null ? (float) $this->weight_kg : null,
        ];
    }
}

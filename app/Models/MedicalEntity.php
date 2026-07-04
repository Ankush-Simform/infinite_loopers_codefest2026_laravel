<?php

namespace App\Models;

use App\Enums\MedicalEntityStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MedicalEntity extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'report_id',
        'entity_type',
        'entity_name',
        'value',
        'unit',
        'reference_range',
        'status',
        'confidence',
    ];

    protected function casts(): array
    {
        return [
            'status' => MedicalEntityStatus::class,
            'confidence' => 'decimal:2',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function report(): BelongsTo
    {
        return $this->belongsTo(MedicalReport::class);
    }
}

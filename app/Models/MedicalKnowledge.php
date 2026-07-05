<?php

namespace App\Models;

use App\Enums\ReportRiskLevel;
use App\Models\Traits\GeneratesPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MedicalKnowledge extends Model
{
    use GeneratesPrimaryKey, HasFactory, SoftDeletes;

    protected $table = 'medical_knowledge';

    protected $fillable = [
        'report_id',
        'summary',
        'risk_level',
        'recommendations',
        'confidence_score',
        'processing_time_ms',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'risk_level' => ReportRiskLevel::class,
            'confidence_score' => 'decimal:2',
            'processing_time_ms' => 'integer',
            'processed_at' => 'datetime',
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

<?php

namespace App\Models;

use App\Models\Traits\GeneratesPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReportTag extends Model
{
    use GeneratesPrimaryKey, HasFactory, SoftDeletes;

    protected $fillable = [
        'report_id',
        'tag',
    ];

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

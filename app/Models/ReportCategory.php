<?php

namespace App\Models;

use App\Models\Traits\GeneratesPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReportCategory extends Model
{
    use GeneratesPrimaryKey, HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function medicalReports(): HasMany
    {
        return $this->hasMany(MedicalReport::class);
    }
}

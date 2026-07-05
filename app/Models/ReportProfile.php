<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\ProfileRelation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReportProfile extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'report_profiles';

    protected $fillable = [
        'user_id',
        'relation',
        'name',
        'email',
        'blood_group',
        'date_of_birth',
        'gender',
        'height_cm',
        'weight_kg',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'height_cm' => 'decimal:2',
            'weight_kg' => 'decimal:2',
            'gender' => Gender::class,
            'relation' => ProfileRelation::class,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function medicalReports(): HasMany
    {
        return $this->hasMany(MedicalReport::class, 'report_profile_id');
    }

    public function timelineEvents(): HasMany
    {
        return $this->hasMany(TimelineEvent::class, 'report_profile_id');
    }
}

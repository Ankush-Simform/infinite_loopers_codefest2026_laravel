<?php

namespace App\Models;

use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class MedicalReport extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'report_profile_id',
        'report_category_id',
        'title',
        'report_type',
        'doctor_name',
        'hospital_name',
        'report_date',
        'file_url',
        'file_hash',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'status' => ReportStatus::class,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function reportProfile(): BelongsTo
    {
        return $this->belongsTo(ReportProfile::class, 'report_profile_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ReportCategory::class, 'report_category_id');
    }

    public function knowledge(): HasOne
    {
        return $this->hasOne(MedicalKnowledge::class, 'report_id');
    }

    public function entities(): HasMany
    {
        return $this->hasMany(MedicalEntity::class, 'report_id');
    }

    public function timelineEvents(): HasMany
    {
        return $this->hasMany(TimelineEvent::class, 'report_id');
    }

    public function tags(): HasMany
    {
        return $this->hasMany(ReportTag::class, 'report_id');
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'report_id');
    }
}

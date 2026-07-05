<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReportStatus;
use App\Models\Traits\GeneratesPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class MedicalReport extends Model
{
    use GeneratesPrimaryKey, HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'report_profile_id',
        'report_category_id',
        'reference_id',
        'blob_name',
        'title',
        'report_type',
        'storage_provider',
        'doctor_name',
        'hospital_name',
        'report_date',
        'file_url',
        'file_hash',
        'original_file_name',
        'mime_type',
        'file_size',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'string',
            'reference_id' => 'integer',
            'report_date' => 'date',
            'status' => ReportStatus::class,
        ];
    }

    /**
     * Atomically reserve the next unique reference id via a Postgres sequence,
     * so concurrent report uploads can never collide on the same value.
     */
    public static function nextReferenceId(): int
    {
        return (int) DB::selectOne("SELECT nextval('medical_reports_reference_id_seq') AS value")->value;
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    |
    */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

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

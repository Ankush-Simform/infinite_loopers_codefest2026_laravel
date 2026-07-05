<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TimelineEvent extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'profile_id',
        'report_id',
        'event_type',
        'title',
        'description',
        'event_date',
        'importance',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'importance' => 'integer',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(MedicalReport::class);
    }
}

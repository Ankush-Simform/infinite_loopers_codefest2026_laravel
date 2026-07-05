<?php

namespace App\Models;

use App\Enums\ActivityType;
use App\Models\Traits\GeneratesPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ActivityLog extends Model
{
    use GeneratesPrimaryKey, HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'method',
        'activity_type',
        'subject_type',
        'subject_id',
        'ip_address',
        'user_agent',
        'properties',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'activity_type' => ActivityType::class,
            'properties' => 'array',
            'payload' => 'array',
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

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}

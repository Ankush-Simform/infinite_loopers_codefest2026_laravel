<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmergencyCard extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'profile_id',
        'qr_token',
        'expires_at',
        'last_generated_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_generated_at' => 'datetime',
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
}

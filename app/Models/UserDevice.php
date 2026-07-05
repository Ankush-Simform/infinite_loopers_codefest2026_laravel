<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\GeneratesPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class UserDevice extends Model
{
    use GeneratesPrimaryKey, HasFactory;

    protected $fillable = [
        'user_id',
        'device_name',
        'platform',
        'fcm_token',
        'app_version',
        'last_used_at',
        'is_active',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Get the user that owns the device.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

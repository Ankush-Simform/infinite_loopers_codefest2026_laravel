<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'email_verified_at',
        'password',
        'google_id',
        'avatar',
        'master_key_hash',
        'emergency_contact_name',
        'emergency_contact_phone',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'master_key_hash',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function profiles(): HasMany
    {
        return $this->hasMany(Profile::class);
    }

    public function medicalReports(): HasManyThrough
    {
        return $this->hasManyThrough(
            MedicalReport::class,
            Profile::class,
            'user_id',
            'profile_id'
        );
    }

    public function timelineEvents(): HasManyThrough
    {
        return $this->hasManyThrough(
            TimelineEvent::class,
            Profile::class,
            'user_id',
            'profile_id'
        );
    }

    public function emergencyCard(): HasOneThrough
    {
        return $this->hasOneThrough(
            EmergencyCard::class,
            Profile::class,
            'user_id',
            'profile_id'
        );
    }

    public function chatSessions(): HasMany
    {
        return $this->hasMany(ChatSession::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(UserDevice::class);
    }
}

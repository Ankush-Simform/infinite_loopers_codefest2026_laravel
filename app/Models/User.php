<?php

namespace App\Models;

use App\Enums\Gender;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
        'password',
        'google_id',
        'avatar',
        'master_key_hash',
        'emergency_contact_name',
        'emergency_contact_phone',
        'blood_group',
        'date_of_birth',
        'gender',
        'height_cm',
        'weight_kg',
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
            'date_of_birth' => 'date',
            'height_cm' => 'decimal:2',
            'weight_kg' => 'decimal:2',
            'gender' => Gender::class,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function reportProfiles(): HasMany
    {
        return $this->hasMany(ReportProfile::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(ReportProfile::class)->where('relation', \App\Enums\ProfileRelation::SELF);
    }

    public function medicalReports(): HasManyThrough
    {
        return $this->hasManyThrough(
            MedicalReport::class,
            ReportProfile::class,
            'user_id',
            'report_profile_id'
        );
    }

    public function timelineEvents(): HasManyThrough
    {
        return $this->hasManyThrough(
            TimelineEvent::class,
            ReportProfile::class,
            'user_id',
            'report_profile_id'
        );
    }

    public function emergencyCard(): HasOne
    {
        return $this->hasOne(EmergencyCard::class);
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

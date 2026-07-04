<?php

namespace Database\Seeders;

use App\Models\EmergencyCard;
use App\Models\Profile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EmergencyCardSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Profile::all() as $profile) {

            EmergencyCard::create([
                'profile_id' => $profile->id,
                'qr_token' => Str::uuid(),
                'expires_at' => now()->addYear(),
                'last_generated_at' => now(),
            ]);
        }
    }
}

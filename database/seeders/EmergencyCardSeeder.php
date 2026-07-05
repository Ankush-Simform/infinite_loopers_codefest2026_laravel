<?php

namespace Database\Seeders;

use App\Models\EmergencyCard;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EmergencyCardSeeder extends Seeder
{
    public function run(): void
    {
        foreach (User::all() as $user) {
            EmergencyCard::create([
                'user_id' => $user->id,
                'qr_token' => (string) Str::uuid(),
                'expires_at' => now()->addYear(),
                'last_generated_at' => now(),
            ]);
        }
    }
}

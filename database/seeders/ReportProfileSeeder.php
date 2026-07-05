<?php

namespace Database\Seeders;

use App\Enums\Gender;
use App\Enums\ProfileRelation;
use App\Models\ReportProfile;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReportProfileSeeder extends Seeder
{
    public function run(): void
    {
        foreach (User::all() as $user) {
            // Create SELF report profile
            ReportProfile::create([
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'relation' => ProfileRelation::SELF->value,
                'blood_group' => fake()->randomElement(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']),
                'date_of_birth' => fake()->dateTimeBetween('-70 years', '-18 years')->format('Y-m-d'),
                'gender' => fake()->randomElement(array_column(Gender::cases(), 'value')),
                'height_cm' => fake()->randomFloat(2, 145, 190),
                'weight_kg' => fake()->randomFloat(2, 40, 100),
            ]);

            // Create Mother report profile for some users
            if (fake()->boolean(50)) {
                ReportProfile::create([
                    'user_id' => $user->id,
                    'name' => fake()->name('female'),
                    'email' => fake()->safeEmail(),
                    'relation' => ProfileRelation::MOTHER->value,
                    'blood_group' => fake()->randomElement(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']),
                    'date_of_birth' => fake()->dateTimeBetween('-80 years', '-55 years')->format('Y-m-d'),
                    'gender' => Gender::FEMALE->value,
                    'height_cm' => fake()->randomFloat(2, 145, 175),
                    'weight_kg' => fake()->randomFloat(2, 40, 80),
                ]);
            }
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create default test user for Postman API testing
        User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'phone' => '+1234567890',
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        User::factory(5)->create();

        $this->call([
            ReportCategorySeeder::class,
            ReportProfileSeeder::class,
            MedicalReportSeeder::class,
            MedicalKnowledgeSeeder::class,
            MedicalEntitySeeder::class,
            TimelineEventSeeder::class,
            ReportTagSeeder::class,
            EmergencyCardSeeder::class,
            ChatSeeder::class,
            NotificationSeeder::class,
            ActivityLogSeeder::class,
        ]);
    }
}

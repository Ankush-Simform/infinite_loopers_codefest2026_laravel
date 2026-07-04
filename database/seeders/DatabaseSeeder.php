<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory(5)->create();

        $this->call([
            ReportCategorySeeder::class,
            ProfileSeeder::class,
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

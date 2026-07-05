<?php

namespace Database\Seeders;

use App\Enums\ReportStatus;
use App\Models\MedicalReport;
use App\Models\Profile;
use App\Models\ReportCategory;
use Illuminate\Database\Seeder;

class MedicalReportSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Profile::all() as $profile) {

            for ($i = 1; $i <= 5; $i++) {

                MedicalReport::create([
                    'profile_id' => $profile->id,
                    'report_category_id' => ReportCategory::inRandomOrder()->first()->id,
                    'title' => fake()->sentence(3),
                    'report_type' => fake()->randomElement([
                        'Blood',
                        'MRI',
                        'Prescription',
                    ]),
                    'doctor_name' => fake()->name(),
                    'hospital_name' => fake()->company(),
                    'report_date' => fake()->date(),
                    'file_url' => 'reports/sample.pdf',
                    'file_hash' => fake()->sha256(),
                    'status' => fake()->randomElement(ReportStatus::cases()),
                ]);
            }
        }
    }
}

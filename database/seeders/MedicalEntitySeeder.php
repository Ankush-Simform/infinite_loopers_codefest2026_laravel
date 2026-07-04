<?php

namespace Database\Seeders;

use App\Enums\MedicalEntityStatus;
use App\Models\MedicalEntity;
use App\Models\MedicalReport;
use Illuminate\Database\Seeder;

class MedicalEntitySeeder extends Seeder
{
    public function run(): void
    {
        foreach (MedicalReport::all() as $report) {

            MedicalEntity::create([
                'report_id' => $report->id,
                'entity_type' => 'Lab Test',
                'entity_name' => 'Hemoglobin',
                'value' => fake()->numberBetween(10, 18),
                'unit' => 'g/dL',
                'reference_range' => '12-16',
                'status' => fake()->randomElement(MedicalEntityStatus::cases()),
                'confidence' => fake()->randomFloat(2, 85, 99),
            ]);
        }
    }
}

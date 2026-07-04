<?php

namespace Database\Seeders;

use App\Enums\ReportRiskLevel;
use App\Models\MedicalKnowledge;
use App\Models\MedicalReport;
use Illuminate\Database\Seeder;

class MedicalKnowledgeSeeder extends Seeder
{
    public function run(): void
    {
        foreach (MedicalReport::all() as $report) {

            MedicalKnowledge::create([
                'report_id' => $report->id,
                'summary' => fake()->paragraph(),
                'risk_level' => fake()->randomElement(ReportRiskLevel::cases()),
                'recommendations' => fake()->paragraph(),
                'confidence_score' => fake()->randomFloat(2, 80, 99),
                'processing_time_ms' => fake()->numberBetween(1000, 4000),
                'processed_at' => now(),
            ]);
        }
    }
}

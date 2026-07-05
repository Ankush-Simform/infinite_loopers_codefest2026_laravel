<?php

namespace Database\Seeders;

use App\Models\MedicalReport;
use App\Models\ReportTag;
use Illuminate\Database\Seeder;

class ReportTagSeeder extends Seeder
{
    public function run(): void
    {
        $tags = [
            'Diabetes',
            'Heart',
            'Normal',
            'Critical',
            'Vitamin D',
            'Blood',
        ];

        foreach (MedicalReport::all() as $report) {

            foreach (fake()->randomElements($tags, 3) as $tag) {

                ReportTag::create([
                    'report_id' => $report->id,
                    'tag' => $tag,
                ]);
            }
        }
    }
}

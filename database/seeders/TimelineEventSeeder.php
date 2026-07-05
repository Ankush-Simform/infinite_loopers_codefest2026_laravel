<?php

namespace Database\Seeders;

use App\Models\MedicalReport;
use App\Models\TimelineEvent;
use Illuminate\Database\Seeder;

class TimelineEventSeeder extends Seeder
{
    public function run(): void
    {
        foreach (MedicalReport::all() as $report) {
            TimelineEvent::create([
                'report_profile_id' => $report->report_profile_id,
                'report_id' => $report->id,
                'event_type' => 'Report Uploaded',
                'title' => $report->title,
                'description' => fake()->sentence(),
                'event_date' => $report->report_date,
                'importance' => fake()->numberBetween(1, 5),
            ]);
        }
    }
}

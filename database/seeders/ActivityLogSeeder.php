<?php

namespace Database\Seeders;

use App\Enums\ActivityType;
use App\Models\ActivityLog;
use App\Models\MedicalReport;
use App\Models\User;
use Illuminate\Database\Seeder;

class ActivityLogSeeder extends Seeder
{
    public function run(): void
    {
        $reportId = MedicalReport::query()->value('id');

        foreach (User::all() as $user) {

            ActivityLog::create([
                'user_id' => $user->id,
                'method' => 'POST',
                'activity_type' => fake()->randomElement(ActivityType::cases()),
                'subject_type' => 'MedicalReport',
                'subject_id' => $reportId,
                'ip_address' => fake()->ipv4(),
                'user_agent' => fake()->userAgent(),
                'properties' => [
                    'browser' => 'Chrome',
                ],
                'payload' => [
                    'action' => 'upload',
                ],
            ]);
        }
    }
}

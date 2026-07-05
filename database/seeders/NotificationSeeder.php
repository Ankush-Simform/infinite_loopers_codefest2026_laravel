<?php

namespace Database\Seeders;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        foreach (User::all() as $user) {

            Notification::create([
                'user_id' => $user->id,
                'type' => 'report_uploaded',
                'title' => 'Report Uploaded',
                'message' => 'Your medical report has been processed successfully.',
            ]);
        }
    }
}

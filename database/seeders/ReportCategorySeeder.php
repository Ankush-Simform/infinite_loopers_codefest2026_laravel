<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReportCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Blood Test',
            'X-Ray',
            'MRI',
            'CT Scan',
            'Prescription',
            'ECG',
            '2D Echo',
            'Urine Test',
            'Diabetes',
            'Heart',
        ];

        foreach ($categories as $category) {
            DB::table('report_categories')->insert([
                'name' => $category,
                'slug' => Str::slug($category),
                'description' => $category.' Reports',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}

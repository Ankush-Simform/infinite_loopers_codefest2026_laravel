<?php

namespace Database\Seeders;

use App\Models\ReportCategory;
use Illuminate\Database\Seeder;
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
            ReportCategory::create([
                'name' => $category,
                'slug' => Str::slug($category),
                'description' => $category.' Reports',
            ]);
        }
    }
}

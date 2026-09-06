<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\InterestedProjectName;
use Illuminate\Support\Str;

class InterestedProjectNameSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $projects = [
            'Jashn',
            'Elevate',
            'Oro',
            'Constella',
        ];

        foreach ($projects as $projectName) {
            InterestedProjectName::firstOrCreate(
                ['name' => $projectName],
                [
                    'slug' => Str::slug($projectName),
                    'is_active' => true,
                ]
            );
        }
    }
}

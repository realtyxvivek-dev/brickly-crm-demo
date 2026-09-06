<?php

namespace Database\Seeders;

use App\Models\Project;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        $projects = [
            ['name' => 'Sky Wall', 'short_overview' => 'Sky Wall Project', 'is_active' => true],
            ['name' => 'Eldeco Green', 'short_overview' => 'Eldeco Green Project', 'is_active' => true],
            ['name' => 'Jashn Elevate', 'short_overview' => 'Jashn Elevate Project', 'is_active' => true],
        ];

        foreach ($projects as $project) {
            Project::firstOrCreate(
                ['name' => $project['name']],
                $project
            );
        }
    }
}


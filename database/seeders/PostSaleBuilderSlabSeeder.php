<?php

namespace Database\Seeders;

use App\Models\Builder;
use App\Models\BuilderReleaseScheme;
use App\Models\Project;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PostSaleBuilderSlabSeeder extends Seeder
{
    public function run(): void
    {
        $schemes = [
            ['Shalimar', null, [[20, 33], [35, 66], [50, 100]], true],
            ['ORO', 'Constella', [[35, 50], [50, 100]], true],
            ['ORO', 'Dynasty', [[35, 50], [50, 100]], true],
            ['Anandam', null, [[30, 75], [50, 100]], true],
            ['Akasa', null, [[20, 50], [40, 100]], true],
            ['Skyom', null, [[20, 25], [30, 40], [50, 100]], true],
            ['Oneoak', null, [[10, 10], [20, 40], [40, 85], [50, 100]], true],
            ['Rishita', null, [[15, 33], [30, 66], [45, 100]], true],
            ['Sahu', null, [[10, 20], [20, 40], [30, 60], [40, 80], [50, 100]], true],
            ['Kailasha Awadh', null, [[10, 25], [20, 50], [30, 75], [40, 100]], true],
            ['Jashn Elevate', null, [[20, 25], [30, 50], [50, 75], [60, 100]], true],
            ['OKAS', null, [], false],
        ];

        $projects = Project::with('builder')->get();
        $builders = Builder::all();
        foreach ($schemes as [$builderName, $projectName, $slabs, $complete]) {
            $project = $projects->first(function (Project $candidate) use ($builderName, $projectName) {
                $haystack = $this->normalize($candidate->name.' '.optional($candidate->builder)->name);
                return str_contains($haystack, $this->normalize($projectName ?: $builderName));
            });
            $builder = $project?->builder ?: $builders->first(fn (Builder $candidate) => str_contains($this->normalize($candidate->name), $this->normalize($builderName)));
            $mapped = (bool) ($project || ($builder && !$projectName));

            $scheme = BuilderReleaseScheme::updateOrCreate(
                ['builder_name' => $builderName, 'project_name' => $projectName],
                [
                    'builder_id' => $builder?->id,
                    'project_id' => $project?->id,
                    'name' => trim($builderName.' '.($projectName ?: '')).' Brokerage Release',
                    'is_active' => $complete && $mapped,
                    'setup_status' => !$complete ? 'setup_pending' : ($mapped ? 'ready' : 'needs_mapping'),
                ]
            );
            foreach ($slabs as $index => [$collection, $release]) {
                $scheme->slabs()->updateOrCreate(
                    ['customer_collection_percent' => $collection],
                    ['brokerage_release_percent' => $release, 'sort_order' => $index + 1]
                );
            }
        }
    }

    private function normalize(string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', Str::lower($value)) ?: '';
    }
}

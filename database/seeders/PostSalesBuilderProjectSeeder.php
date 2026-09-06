<?php

namespace Database\Seeders;

use App\Models\Builder;
use App\Models\BuilderReleaseScheme;
use App\Models\Project;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PostSalesBuilderProjectSeeder extends Seeder
{
    private const MAPPINGS = [
        ['AIS', 'Galleria'],
        ['AKASA', 'Akasa Elite'],
        ['AMRAWATI', 'IT City (Phase 2)'],
        ['AMRAWATI', 'Sports City'],
        ['ANANDAM', 'Anandam'],
        ['EKANA', 'Ekana Ontario'],
        ['ELDECO', 'Eldeco Avenue'],
        ['ELDECO', 'Eldeco Imperia'],
        ['ELDECO', 'Eldeco Solano Garden- Plot'],
        ['ELDECO', 'Eldeco Solano Garden- Villa'],
        ['ELDECO', 'Hanging Garden'],
        ['ELDECO', 'Eldeco Latitude'],
        ['ELDECO', 'LIG Shaurya'],
        ['ELDECO', 'Shaurya'],
        ['JASHN', 'Elevate'],
        ['KAILASHA', 'Imperial Residency'],
        ['KAILASHA', 'Kailasha Awadh'],
        ['LJK', 'LJK Vasto'],
        ['LUCKNOW GREENS', 'Lucknow Greens'],
        ['OKAS', 'Sukoon'],
        ['OKAS', 'Residency'],
        ['ONEOAK', 'Eden'],
        ['ORO', 'Constella'],
        ['ORO', 'Oro Atlantis'],
        ['ORO', 'Oro Dynasty'],
        ['ORO', 'Oro Leisure'],
        ['ORO', 'Oro New Jail Road'],
        ['RISHITA', 'Manhattan'],
        ['RISHITA', 'Mulberry'],
        ['SAHU', 'Pearl'],
        ['SHALIMAR', 'Mannat'],
        ['SHALIMAR', 'Marbella'],
        ['SHALIMAR', 'Courtyard'],
        ['SHALIMAR', 'Evara'],
        ['SHALIMAR', 'Twenty One'],
        ['SHALIMAR', 'Valancia Tower-2'],
        ['SKYOM', 'Skyom'],
        ['SMJ', 'SMJ Suraksha Enclave'],
        ['YTT', 'YTT (Phase 2)'],
    ];

    private const BUILDER_ALIASES = [
        'akasa' => 'akasha',
        'eldeco' => 'eldecogroup',
        'jashn' => 'jashnrealty',
        'oro' => 'orogroup',
        'rishita' => 'rishitadevelopers',
    ];

    private const PROJECT_ALIASES = [
        'akasaelite' => 'akashaelite',
    ];

    public function run(): void
    {
        foreach (self::MAPPINGS as [$builderName, $projectName]) {
            $builder = $this->findOrCreateBuilder($builderName);
            $project = $this->findOrCreateProject($builder, $projectName);

            BuilderReleaseScheme::firstOrCreate(
                ['builder_id' => $builder->id, 'project_id' => $project->id],
                [
                    'builder_name' => $builder->name,
                    'project_name' => $project->name,
                    'name' => $builder->name.' '.$project->name.' Brokerage Release',
                    'is_active' => false,
                    'setup_status' => 'setup_pending',
                ]
            );
        }
    }

    private function findOrCreateBuilder(string $builderName): Builder
    {
        $needle = self::BUILDER_ALIASES[$this->normalize($builderName)] ?? $this->normalize($builderName);

        $builder = Builder::withTrashed()->get()->first(
            fn (Builder $candidate) => $this->normalize($candidate->name) === $needle
        );

        if ($builder?->trashed()) {
            $builder->restore();
        }

        return $builder ?: Builder::create(['name' => $builderName, 'status' => 'active']);
    }

    private function findOrCreateProject(Builder $builder, string $projectName): Project
    {
        $needle = self::PROJECT_ALIASES[$this->normalize($projectName)] ?? $this->normalize($projectName);

        $project = Project::withTrashed()
            ->where('builder_id', $builder->id)
            ->get()
            ->first(fn (Project $candidate) => $this->normalize($candidate->name) === $needle);

        if ($project?->trashed()) {
            $project->restore();
        }

        return $project ?: Project::create([
            'builder_id' => $builder->id,
            'name' => $projectName,
            'is_active' => true,
        ]);
    }

    private function normalize(string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', Str::lower($value)) ?: '';
    }
}

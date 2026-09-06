<?php

namespace App\Console\Commands;

use App\Services\VisitImportService;
use App\Services\DuplicateDetectionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ImportVisitsFromSql extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:visits-from-sql {file : Path to SQL file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import visits from SQL file to site_visits table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filePath = $this->argument('file');

        if (!file_exists($filePath)) {
            $this->error("SQL file not found: {$filePath}");
            return Command::FAILURE;
        }

        $this->info("Starting visit import from: {$filePath}");
        $this->newLine();

        try {
            $duplicateService = new DuplicateDetectionService();
            $importService = new VisitImportService($duplicateService);

            $this->info("Parsing SQL file and importing visits...");
            $this->newLine();

            $stats = $importService->importFromSql($filePath);

            // Display summary
            $this->displaySummary($stats);

            // Show user mapping file location if there are unmapped users
            if ($stats['users_not_found'] > 0) {
                $this->newLine();
                $this->warn("Some users were not found and need manual mapping.");
                $this->info("User mapping file: " . storage_path('app/visit_import_user_mapping.json'));
                $this->info("Please review and update the mapping file, then re-run the import.");
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Error during import: " . $e->getMessage());
            Log::error("Visit import error: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }

    /**
     * Display import summary
     */
    protected function displaySummary(array $stats): void
    {
        $this->newLine();
        $this->info('Import Summary:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Visits Found', $stats['total']],
                ['Successfully Imported', $stats['imported']],
                ['Skipped', $stats['skipped']],
                ['Users Found', $stats['users_found']],
                ['Users Not Found', $stats['users_not_found']],
                ['Leads Created', $stats['leads_created']],
                ['Leads Found (Existing)', $stats['leads_found']],
                ['Photos Imported', $stats['photos_imported']],
            ]
        );

        if (!empty($stats['errors'])) {
            $this->newLine();
            $this->warn('Errors encountered:');
            foreach (array_slice($stats['errors'], 0, 10) as $error) {
                $this->line("  - {$error}");
            }
            if (count($stats['errors']) > 10) {
                $this->line("  ... and " . (count($stats['errors']) - 10) . " more errors");
            }
        }
    }
}


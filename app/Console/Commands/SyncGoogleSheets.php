<?php

namespace App\Console\Commands;

use App\Models\GoogleSheetsConfig;
use App\Services\GoogleSheetImportRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncGoogleSheets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'google-sheets:sync {--force : Force sync even if interval not reached} {--timeout=50 : Hard timeout in seconds per sheet}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Google Sheets based on auto-sync settings';

    protected $importRunner;

    public function __construct(GoogleSheetImportRunner $importRunner)
    {
        parent::__construct();
        $this->importRunner = $importRunner;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Google Sheets sync at ' . now()->format('Y-m-d H:i:s'));

        // Find all configs that need syncing
        $configs = GoogleSheetsConfig::where('is_active', true)
            ->where('auto_sync_enabled', true)
            ->get();

        $this->info("Found {$configs->count()} active config(s) with auto-sync enabled");

        $synced = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($configs as $config) {
            // Check if it's time to sync
            // For auto-sync, always sync if interval has passed OR if last_sync_at is null
            // This ensures we don't miss new leads
            $shouldSync = false;
            $forceSync = $this->option('force');
            
            if (!$config->last_sync_at) {
                // Never synced - sync immediately
                $shouldSync = true;
                $this->info("  🔄 First sync for config ID: {$config->id} ({$config->sheet_name})");
                Log::info("Google Sheets first sync", [
                    'config_id' => $config->id,
                    'sheet_name' => $config->sheet_name,
                ]);
            } else {
                // Check if interval has passed
                $nextSyncTime = $config->last_sync_at->copy()->addMinutes($config->sync_interval_minutes);
                if ($forceSync || now()->gte($nextSyncTime)) {
                    $shouldSync = true;
                    if ($forceSync) {
                        $this->info("  🔄 Force syncing config ID: {$config->id} ({$config->sheet_name}) (interval check bypassed)");
                    }
                } else {
                    $skipped++;
                    $this->info("  ⏭ Skipping config ID: {$config->id} ({$config->sheet_name}) - next sync at {$nextSyncTime->format('Y-m-d H:i:s')} (current: " . now()->format('Y-m-d H:i:s') . ", interval: {$config->sync_interval_minutes} min)");
                    Log::info("Google Sheets sync skipped - interval not reached", [
                        'config_id' => $config->id,
                        'sheet_name' => $config->sheet_name,
                        'last_sync_at' => $config->last_sync_at->format('Y-m-d H:i:s'),
                        'sync_interval_minutes' => $config->sync_interval_minutes,
                        'next_sync_at' => $nextSyncTime->format('Y-m-d H:i:s'),
                        'current_time' => now()->format('Y-m-d H:i:s'),
                    ]);
                    continue;
                }
            }
            
            if (!$shouldSync) {
                continue;
            }

            try {
                $this->info("Syncing config ID: {$config->id} ({$config->sheet_name})...");
                
                $timeout = max((int) $this->option('timeout'), 5);
                $result = $this->importRunner->run($config, 'cron', $timeout);

                if ($result['status'] === 'no_changes') {
                    $this->info("  ⏭ No new rows (last processed: {$result['last_processed_row_after']}, duration: {$result['duration_ms']}ms)");
                    $skipped++;
                    continue;
                }

                $this->info("  ✓ Status: {$result['status']} | Imported: {$result['imported']} | Errors: {$result['errors']} | Duration: {$result['duration_ms']}ms");
                if ($result['success']) {
                    $synced++;
                } else {
                    $errors++;
                }

            } catch (\Exception $e) {
                $this->error("  ✗ Error syncing config ID {$config->id}: " . $e->getMessage());
                Log::error("Google Sheets sync error for config {$config->id}: " . $e->getMessage());
                $errors++;
            }
        }

        $this->info("Sync completed. Synced: {$synced}, Skipped: {$skipped}, Errors: {$errors}");
        
        return Command::SUCCESS;
    }
}

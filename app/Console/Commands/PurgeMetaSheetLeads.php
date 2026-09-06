<?php

namespace App\Console\Commands;

use App\Models\GoogleSheetsConfig;
use App\Models\Lead;
use App\Models\LeadAssignment;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class PurgeMetaSheetLeads extends Command
{
    /**
     * The name and signature of the console command.
     *
     * --all: delete all leads linked to this sheet config (created after config was created)
     * default: deletes only "bad" leads (invalid phone length OR name looks like an ID)
     *
     * @var string
     */
    protected $signature = 'meta-sheet:purge-leads
                            {configId : GoogleSheetsConfig ID (meta_facebook)}
                            {--all : Delete ALL leads linked to this sheet config (use with caution)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete previously synced leads for a Meta/Facebook sheet config';

    public function handle(): int
    {
        $configId = (int) $this->argument('configId');
        $deleteAll = (bool) $this->option('all');

        $config = GoogleSheetsConfig::query()
            ->where('id', $configId)
            ->where('sheet_type', 'meta_facebook')
            ->first();

        if (!$config) {
            $this->error("Meta/Facebook sheet config not found (id: {$configId}).");
            return self::FAILURE;
        }

        $leadIds = LeadAssignment::query()
            ->where('sheet_config_id', $config->id)
            ->pluck('lead_id')
            ->unique()
            ->values();

        if ($leadIds->isEmpty()) {
            $this->info('No synced leads found for this config.');
            return self::SUCCESS;
        }

        $this->info("Found {$leadIds->count()} lead(s) linked to sheet config #{$config->id} ({$config->sheet_name}).");

        $deleted = 0;
        $skipped = 0;

        // Process in chunks to avoid memory issues
        $leadIds->chunk(500)->each(function ($chunk) use ($config, $deleteAll, &$deleted, &$skipped) {
            $leads = Lead::query()
                ->whereIn('id', $chunk)
                ->where('source', 'google_sheets')
                ->where('created_by', $config->created_by)
                // avoid deleting pre-existing leads that were only linked due to duplicates
                ->where('created_at', '>=', $config->created_at)
                ->get();

            foreach ($leads as $lead) {
                $digits = preg_replace('/[^0-9]/', '', (string) $lead->phone);
                $len = strlen($digits);

                $looksLikeId = Str::startsWith((string) $lead->name, ['I:', 'i:']);
                $invalidPhone = ($len < 10 || $len > 15);

                if (!$deleteAll && !$looksLikeId && !$invalidPhone) {
                    $skipped++;
                    continue;
                }

                // Soft-delete lead
                $lead->delete();
                $deleted++;

                // Remove assignments for this lead so it disappears everywhere immediately
                LeadAssignment::query()->where('lead_id', $lead->id)->delete();
            }
        });

        $this->info("Deleted leads: {$deleted}");
        if (!$deleteAll) {
            $this->info("Skipped (valid) leads: {$skipped}  (use --all to delete everything linked to this sheet)");
        }

        return self::SUCCESS;
    }
}


<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Prospect;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RemoveDuplicateProspects extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'prospects:remove-duplicates {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove duplicate prospects for the same lead_id, keeping only the latest one';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        
        $this->info('Searching for duplicate prospects by lead_id...');
        
        // Find all lead_ids that have multiple prospects
        $duplicateLeads = DB::table('prospects')
            ->select('lead_id', DB::raw('COUNT(*) as prospect_count'))
            ->whereNotNull('lead_id')
            ->groupBy('lead_id')
            ->having('prospect_count', '>', 1)
            ->get();
        
        if ($duplicateLeads->isEmpty()) {
            $this->info('No duplicate prospects found.');
            return 0;
        }
        
        $this->info("Found {$duplicateLeads->count()} leads with duplicate prospects.");
        
        $totalToDelete = 0;
        $totalKept = 0;
        $deletedIds = [];
        
        foreach ($duplicateLeads as $duplicateLead) {
            $leadId = $duplicateLead->lead_id;
            $count = $duplicateLead->prospect_count;
            
            // Get all prospects for this lead, ordered by priority
            // Priority: 1. pending_verification > verified > rejected
            //           2. Latest created_at
            $prospects = Prospect::where('lead_id', $leadId)
                ->orderByRaw("
                    CASE verification_status
                        WHEN 'pending_verification' THEN 1
                        WHEN 'verified' THEN 2
                        WHEN 'rejected' THEN 3
                        ELSE 4
                    END
                ")
                ->orderBy('created_at', 'desc')
                ->get();
            
            // Keep the first one (highest priority), delete the rest
            $keepProspect = $prospects->first();
            $deleteProspects = $prospects->skip(1);
            
            $this->line("Lead ID: {$leadId} - Found {$count} prospects");
            $this->line("  Keeping: Prospect #{$keepProspect->id} ({$keepProspect->customer_name}, Status: {$keepProspect->verification_status}, Created: {$keepProspect->created_at})");
            
            foreach ($deleteProspects as $deleteProspect) {
                $this->line("  Will delete: Prospect #{$deleteProspect->id} ({$deleteProspect->customer_name}, Status: {$deleteProspect->verification_status}, Created: {$deleteProspect->created_at})");
                
                if (!$isDryRun) {
                    try {
                        // Delete related records first (interested projects, etc.)
                        DB::table('prospect_project')->where('prospect_id', $deleteProspect->id)->delete();
                        
                        // Delete the prospect
                        $deleteProspect->delete();
                        $deletedIds[] = $deleteProspect->id;
                        $totalToDelete++;
                    } catch (\Exception $e) {
                        $this->error("  Error deleting prospect #{$deleteProspect->id}: " . $e->getMessage());
                        Log::error('Error deleting duplicate prospect', [
                            'prospect_id' => $deleteProspect->id,
                            'lead_id' => $leadId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                } else {
                    $totalToDelete++;
                }
                $totalKept++;
            }
            
            $totalKept++;
            $this->line('');
        }
        
        if ($isDryRun) {
            $this->warn("DRY RUN MODE - No prospects were actually deleted.");
            $this->info("Summary: {$duplicateLeads->count()} leads with duplicates found.");
            $this->info("Would keep: {$duplicateLeads->count()} prospects (1 per lead)");
            $this->info("Would delete: {$totalToDelete} duplicate prospects");
        } else {
            $this->info("Summary: {$duplicateLeads->count()} leads with duplicates processed.");
            $this->info("Kept: {$duplicateLeads->count()} prospects (1 per lead)");
            $this->info("Deleted: {$totalToDelete} duplicate prospects");
            
            if (!empty($deletedIds)) {
                $this->line("Deleted prospect IDs: " . implode(', ', $deletedIds));
                
                Log::info('Duplicate prospects removed', [
                    'leads_processed' => $duplicateLeads->count(),
                    'prospects_deleted' => $totalToDelete,
                    'deleted_ids' => $deletedIds,
                ]);
            }
        }
        
        return 0;
    }
}

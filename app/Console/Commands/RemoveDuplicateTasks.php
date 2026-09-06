<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TelecallerTask;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RemoveDuplicateTasks extends Command
{
    protected $signature = 'tasks:remove-duplicates {--dry-run : Show what would be deleted without actually deleting}';
    protected $description = 'Remove duplicate pending tasks for the same lead, keeping only one task per lead';

    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        
        $this->info('Searching for duplicate TelecallerTasks by lead_id...');
        
        // Find all lead_ids that have multiple pending/in_progress tasks
        $duplicateLeads = DB::table('telecaller_tasks')
            ->select('lead_id', DB::raw('COUNT(*) as task_count'))
            ->whereNotNull('lead_id')
            ->whereIn('status', ['pending', 'in_progress'])
            ->groupBy('lead_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();
        
        if ($duplicateLeads->isEmpty()) {
            $this->info('No duplicate pending tasks found.');
            return 0;
        }
        
        $this->info("Found {$duplicateLeads->count()} leads with duplicate pending tasks.");
        
        $totalToDelete = 0;
        $totalKept = 0;
        $deletedIds = [];
        
        foreach ($duplicateLeads as $duplicateLead) {
            $leadId = $duplicateLead->lead_id;
            $count = $duplicateLead->task_count;
            
            // Get all pending/in_progress tasks for this lead, ordered by priority
            // Priority: 1. calling > cnp_retry > follow_up (keep calling tasks, not retry tasks)
            //           2. Oldest created_at (keep the first task created)
            $tasks = TelecallerTask::where('lead_id', $leadId)
                ->whereIn('status', ['pending', 'in_progress'])
                ->orderByRaw("
                    CASE task_type
                        WHEN 'calling' THEN 1
                        WHEN 'follow_up' THEN 2
                        WHEN 'cnp_retry' THEN 3
                        ELSE 4
                    END
                ")
                ->orderBy('created_at', 'asc') // Keep the oldest one
                ->orderBy('id', 'asc') // Fallback to ID for consistency
                ->get();
            
            // Keep the first one (highest priority - calling task, oldest), delete the rest
            $keepTask = $tasks->first();
            $deleteTasks = $tasks->skip(1);
            
            $leadName = $keepTask->lead ? $keepTask->lead->name : 'N/A';
            $this->line("Lead ID: {$leadId} ({$leadName}) - Found {$count} pending tasks");
            $this->line("  Keeping: Task #{$keepTask->id} (Type: {$keepTask->task_type}, Status: {$keepTask->status}, Created: {$keepTask->created_at})");
            
            foreach ($deleteTasks as $deleteTask) {
                $this->line("  Will delete: Task #{$deleteTask->id} (Type: {$deleteTask->task_type}, Status: {$deleteTask->status}, Created: {$deleteTask->created_at})");
                
                if (!$isDryRun) {
                    try {
                        // Delete the task (cascade will handle related records if any)
                        $deleteTask->delete();
                        $deletedIds[] = $deleteTask->id;
                        $totalToDelete++;
                    } catch (\Exception $e) {
                        $this->error("  Error deleting task #{$deleteTask->id}: " . $e->getMessage());
                        Log::error('Error deleting duplicate task', [
                            'task_id' => $deleteTask->id,
                            'lead_id' => $leadId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                } else {
                    $totalToDelete++;
                }
            }
            
            $totalKept++;
            $this->line('');
        }
        
        if ($isDryRun) {
            $this->warn("DRY RUN MODE - No tasks were actually deleted.");
            $this->info("Summary: {$duplicateLeads->count()} leads with duplicates found.");
            $this->info("Would keep: {$duplicateLeads->count()} tasks (1 per lead)");
            $this->info("Would delete: {$totalToDelete} duplicate tasks");
        } else {
            $this->info("Summary: {$duplicateLeads->count()} leads with duplicates processed.");
            $this->info("Kept: {$duplicateLeads->count()} tasks (1 per lead)");
            $this->info("Deleted: {$totalToDelete} duplicate tasks");
            
            if (!empty($deletedIds)) {
                $this->line("Deleted task IDs: " . implode(', ', $deletedIds));
                
                Log::info('Duplicate tasks removed', [
                    'leads_processed' => $duplicateLeads->count(),
                    'tasks_deleted' => $totalToDelete,
                    'deleted_ids' => $deletedIds,
                ]);
            }
        }
        
        return 0;
    }
}

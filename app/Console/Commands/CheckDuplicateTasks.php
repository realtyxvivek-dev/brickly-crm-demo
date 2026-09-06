<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TelecallerTask;
use Illuminate\Support\Facades\DB;

class CheckDuplicateTasks extends Command
{
    protected $signature = 'tasks:check-duplicates';
    protected $description = 'Check for duplicate pending tasks for the same lead';

    public function handle()
    {
        $this->info('Checking for duplicate TelecallerTasks by lead_id...');
        
        $duplicates = DB::table('telecaller_tasks')
            ->select('lead_id', DB::raw('COUNT(*) as task_count'))
            ->whereNotNull('lead_id')
            ->whereIn('status', ['pending', 'in_progress'])
            ->groupBy('lead_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();
        
        if ($duplicates->isEmpty()) {
            $this->info('No duplicate pending tasks found.');
            return 0;
        }
        
        $this->warn("Found {$duplicates->count()} leads with multiple pending tasks:");
        
        foreach ($duplicates as $dup) {
            $tasks = TelecallerTask::where('lead_id', $dup->lead_id)
                ->whereIn('status', ['pending', 'in_progress'])
                ->orderBy('created_at', 'desc')
                ->get();
            
            $this->line("Lead ID: {$dup->lead_id} - {$dup->task_count} pending tasks");
            foreach ($tasks as $task) {
                $leadName = $task->lead ? $task->lead->name : 'N/A';
                $this->line("  Task #{$task->id} - {$leadName} (Status: {$task->status}, Type: {$task->task_type}, Created: {$task->created_at})");
            }
            $this->line('');
        }
        
        return 0;
    }
}

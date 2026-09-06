<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\TelecallerTask;
use App\Models\Role;
use App\Services\TelecallerTaskService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CreateCallingTasksForTodayLeads extends Command
{
    protected $signature = 'leads:create-calling-tasks-today {limit=20}';
    protected $description = 'Create calling tasks for today\'s leads assigned to telecallers';

    public function handle()
    {
        $limit = (int) $this->argument('limit');
        
        DB::beginTransaction();
        
        try {
            // Get today's date
            $today = Carbon::today();
            $todayEnd = Carbon::today()->endOfDay();
            
            // Get leads created today
            $todayLeads = Lead::whereBetween('created_at', [$today, $todayEnd])
                ->with(['activeAssignments.assignedTo.role'])
                ->limit($limit)
                ->get();
            
            if ($todayLeads->isEmpty()) {
                $this->info("No leads found created today.");
                return 0;
            }
            
            $this->info("Found {$todayLeads->count()} leads created today. Processing...");
            
            $taskService = app(TelecallerTaskService::class);
            $tasksCreated = 0;
            $tasksSkipped = 0;
            
            // Get admin user for created_by
            $adminRole = Role::where('slug', Role::ADMIN)->first();
            $admin = $adminRole ? \App\Models\User::where('role_id', $adminRole->id)->where('is_active', true)->first() : null;
            $createdBy = $admin ? $admin->id : 1;
            
            foreach ($todayLeads as $lead) {
                // Get active assignment
                $assignment = $lead->activeAssignments->first();
                
                if (!$assignment) {
                    $this->warn("  ⚠ Lead '{$lead->name}' (ID: {$lead->id}) has no active assignment. Skipping.");
                    $tasksSkipped++;
                    continue;
                }
                
                $assignedUser = $assignment->assignedTo;
                
                if (!$assignedUser) {
                    $this->warn("  ⚠ Lead '{$lead->name}' (ID: {$lead->id}) has assignment but no assigned user. Skipping.");
                    $tasksSkipped++;
                    continue;
                }
                
                // Check if assigned user is a sales executive
                if (!$assignedUser->role || $assignedUser->role->slug !== Role::SALES_EXECUTIVE) {
                    $this->warn("  ⚠ Lead '{$lead->name}' (ID: {$lead->id}) is assigned to '{$assignedUser->name}' who is not a sales executive. Skipping.");
                    $tasksSkipped++;
                    continue;
                }
                
                // Check if calling task already exists
                $existingTask = TelecallerTask::where('lead_id', $lead->id)
                    ->where('assigned_to', $assignedUser->id)
                    ->where('task_type', 'calling')
                    ->where('status', 'pending')
                    ->first();
                
                if ($existingTask) {
                    $this->line("  ✓ Lead '{$lead->name}' already has a pending calling task. Skipping.");
                    $tasksSkipped++;
                    continue;
                }
                
                // Create calling task
                try {
                    $task = $taskService->createCallingTask($lead, $assignedUser, $createdBy);
                    $this->line("  ✓ Created calling task for lead: {$lead->name} (Phone: {$lead->phone}) → {$assignedUser->name}");
                    $tasksCreated++;
                } catch (\Exception $e) {
                    Log::error("Failed to create calling task for lead {$lead->id}: " . $e->getMessage());
                    $this->warn("  ⚠ Failed to create task for lead: {$lead->name}");
                    $tasksSkipped++;
                }
            }
            
            DB::commit();
            
            $this->info("");
            $this->info("✅ Successfully created {$tasksCreated} calling tasks!");
            $this->info("⚠ Skipped {$tasksSkipped} leads (no assignment, not telecaller, or task already exists)");
            $this->info("");
            
            return 0;
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("");
            $this->error("❌ Failed to create calling tasks: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return 1;
        }
    }
}

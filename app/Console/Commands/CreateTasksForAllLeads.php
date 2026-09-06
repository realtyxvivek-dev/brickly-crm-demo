<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\Task;
use App\Models\TelecallerTask;
use App\Models\User;
use App\Models\Role;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateTasksForAllLeads extends Command
{
    protected $signature = 'leads:create-tasks-for-all {--force : Force creation without confirmation}';
    
    protected $description = 'Create calling tasks for all existing assigned leads that don\'t have tasks';

    public function handle()
    {
        $this->info('Finding all leads that need calling tasks...');
        
        // Get all lead assignments (active and inactive) to ensure all leads have tasks
        $assignments = LeadAssignment::with(['lead', 'assignedTo.role'])
            ->get();
        
        $this->info("Found {$assignments->count()} lead assignments.");
        
        $tasksToCreate = [];
        $skipped = 0;
        $processedLeads = []; // Track leads we've already processed
        
        // Process leads from assignments
        foreach ($assignments as $assignment) {
            $lead = $assignment->lead;
            $assignedUser = $assignment->assignedTo;
            
            if (!$lead || !$assignedUser || !$assignedUser->role) {
                $skipped++;
                continue;
            }
            
            $userRole = $assignedUser->role->slug ?? '';
            $leadKey = "{$lead->id}_{$assignedUser->id}";
            
            // Skip if already processed
            if (isset($processedLeads[$leadKey])) {
                continue;
            }
            
            // Check if task already exists
            $hasTask = false;
            
            if (in_array($userRole, [Role::SALES_MANAGER, Role::ASSISTANT_SALES_MANAGER])) {
                // Check Task model - check for any existing task (not just pending)
                $existingTask = Task::where('lead_id', $lead->id)
                    ->where('assigned_to', $assignedUser->id)
                    ->where('type', 'phone_call')
                    ->first();
                
                if ($existingTask) {
                    $hasTask = true;
                } else {
                    $tasksToCreate[] = [
                        'lead_id' => $lead->id,
                        'lead_name' => $lead->name,
                        'user_id' => $assignedUser->id,
                        'user_name' => $assignedUser->name,
                        'role' => $userRole,
                        'model' => 'Task',
                    ];
                }
            } elseif ($userRole === Role::SALES_EXECUTIVE) {
                // Check TelecallerTask model - check for any existing task (not just pending)
                $existingTask = TelecallerTask::where('lead_id', $lead->id)
                    ->where('assigned_to', $assignedUser->id)
                    ->where('task_type', 'calling')
                    ->first();
                
                if ($existingTask) {
                    $hasTask = true;
                } else {
                    $tasksToCreate[] = [
                        'lead_id' => $lead->id,
                        'lead_name' => $lead->name,
                        'user_id' => $assignedUser->id,
                        'user_name' => $assignedUser->name,
                        'role' => $userRole,
                        'model' => 'TelecallerTask',
                    ];
                }
            } else {
                $skipped++;
                continue;
            }
            
            $processedLeads[$leadKey] = true;
        }
        
        // Also check leads from verified prospects of team members (for sales managers)
        $this->info('Checking leads from verified prospects...');
        $salesManagers = User::whereHas('role', function($q) {
            $q->where('slug', Role::SALES_MANAGER);
        })->with('role')->get();
        
        foreach ($salesManagers as $manager) {
            $teamMemberIds = $manager->teamMembers()->pluck('id');
            
            if ($teamMemberIds->isEmpty()) {
                continue;
            }
            
            // Get leads from verified prospects of team members
            $leadsFromProspects = Lead::whereHas('prospects', function ($subQ) use ($teamMemberIds) {
                $subQ->whereIn('telecaller_id', $teamMemberIds)
                     ->whereIn('verification_status', ['verified', 'approved']);
            })->get();
            
            foreach ($leadsFromProspects as $lead) {
                $leadKey = "{$lead->id}_{$manager->id}";
                
                // Skip if already processed
                if (isset($processedLeads[$leadKey])) {
                    continue;
                }
                
                // Check if task already exists for this manager
                $existingTask = Task::where('lead_id', $lead->id)
                    ->where('assigned_to', $manager->id)
                    ->where('type', 'phone_call')
                    ->first();
                
                if (!$existingTask) {
                    $tasksToCreate[] = [
                        'lead_id' => $lead->id,
                        'lead_name' => $lead->name,
                        'user_id' => $manager->id,
                        'user_name' => $manager->name,
                        'role' => Role::SALES_MANAGER,
                        'model' => 'Task',
                    ];
                }
                
                $processedLeads[$leadKey] = true;
            }
        }
        
        // Remove duplicates (same lead + user combination)
        $uniqueTasks = [];
        foreach ($tasksToCreate as $task) {
            $key = "{$task['lead_id']}_{$task['user_id']}";
            if (!isset($uniqueTasks[$key])) {
                $uniqueTasks[$key] = $task;
            }
        }
        $tasksToCreate = array_values($uniqueTasks);
        
        $count = count($tasksToCreate);
        
        if ($count === 0) {
            $this->info('No leads found that need tasks. All assigned leads already have calling tasks.');
            return 0;
        }
        
        $this->info("Found {$count} leads that need calling tasks created.");
        $this->warn('This will create:');
        $this->line("  - {$count} calling tasks");
        $this->line('  - Tasks will be scheduled 15 minutes from now (becomes overdue after 15 min)');
        
        // Show sample of leads
        $this->line('');
        $this->info('Sample leads (first 10):');
        foreach (array_slice($tasksToCreate, 0, 10) as $task) {
            $this->line("  - {$task['lead_name']} → {$task['user_name']} ({$task['role']})");
        }
        if ($count > 10) {
            $this->line("  ... and " . ($count - 10) . " more");
        }
        $this->line('');
        
        if (!$this->option('force')) {
            if (!$this->confirm('Are you sure you want to create calling tasks for these leads?')) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }
        
        $this->info('Starting task creation process...');
        
        $created = 0;
        $failed = 0;
        
        DB::beginTransaction();
        
        try {
            foreach ($tasksToCreate as $taskData) {
                try {
                    if ($taskData['model'] === 'Task') {
                        // Create Task for sales manager/executive
                        $task = Task::create([
                            'lead_id' => $taskData['lead_id'],
                            'assigned_to' => $taskData['user_id'],
                            'type' => 'phone_call',
                            'title' => 'Prospect Verification: ' . $taskData['lead_name'],
                            'description' => "Phone call task for lead: {$taskData['lead_name']}",
                            'status' => 'pending',
                            'scheduled_at' => now()->addMinutes(15), // Set to 15 minutes from now
                            'created_by' => 1, // System user
                        ]);
                        $created++;
                    } else {
                        // Create TelecallerTask for telecaller
                        $task = TelecallerTask::create([
                            'lead_id' => $taskData['lead_id'],
                            'assigned_to' => $taskData['user_id'],
                            'task_type' => 'calling',
                            'status' => 'pending',
                            'scheduled_at' => now()->addMinutes(15), // Set to 15 minutes from now
                            'created_by' => 1, // System user
                        ]);
                        $created++;
                    }
                    
                    if ($created % 50 === 0) {
                        $this->line("  ✓ Created {$created} tasks so far...");
                    }
                } catch (\Exception $e) {
                    $failed++;
                    Log::error("Failed to create task for lead {$taskData['lead_id']}: " . $e->getMessage());
                }
            }
            
            DB::commit();
            
            $this->info("");
            $this->info("✅ Successfully created {$created} calling tasks!");
            if ($failed > 0) {
                $this->warn("⚠ {$failed} tasks failed to create (check logs for details)");
            }
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Error occurred: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            return 1;
        }
        
        return 0;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\Task;
use App\Models\TelecallerTask;
use App\Models\User;
use App\Models\Prospect;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateAllMissingTasks extends Command
{
    protected $signature = 'leads:create-all-missing-tasks {--force : Force creation without confirmation}';
    
    protected $description = 'Create calling tasks for ALL leads that should have tasks (including verified prospects)';

    public function handle()
    {
        $this->info('Finding ALL leads that need calling tasks...');
        
        $tasksToCreate = [];
        $allProcessed = [];
        
        // Get all sales managers
        $salesManagers = User::whereHas('role', function($q) {
            $q->where('slug', \App\Models\Role::SALES_MANAGER);
        })->with('role')->get();
        
        $this->info("Found {$salesManagers->count()} sales managers.");
        
        foreach ($salesManagers as $manager) {
            $this->line("Processing manager: {$manager->name} (ID: {$manager->id})");
            
            $teamMemberIds = $manager->teamMembers()->pluck('id');
            $allUserIds = $teamMemberIds->merge([$manager->id])->toArray();
            
            // Get all leads for this manager using same logic as Lead Section
            $leads = Lead::where(function ($q) use ($manager, $teamMemberIds, $allUserIds) {
                // Leads assigned to manager or any team member (all assignments)
                $q->whereHas('assignments', function ($assignmentQ) use ($allUserIds) {
                    $assignmentQ->whereIn('assigned_to', $allUserIds);
                });
                
                // OR leads from verified prospects of team members
                if ($teamMemberIds->isNotEmpty()) {
                    $q->orWhereHas('prospects', function ($subQ) use ($teamMemberIds) {
                        $subQ->whereIn('telecaller_id', $teamMemberIds)
                             ->whereIn('verification_status', ['verified', 'approved']);
                    });
                }
            })->get();
            
            $this->line("  Found {$leads->count()} leads for this manager.");
            
            foreach ($leads as $lead) {
                $leadKey = "{$lead->id}_{$manager->id}";
                
                if (isset($allProcessed[$leadKey])) {
                    continue;
                }
                
                // Check if task already exists
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
                        'role' => \App\Models\Role::SALES_MANAGER,
                        'model' => 'Task',
                    ];
                }
                
                $allProcessed[$leadKey] = true;
            }
        }
        
        // Also check for telecallers and sales executives (only active assignments)
        $allAssignments = LeadAssignment::with(['lead', 'assignedTo.role'])
            ->where('is_active', true)
            ->get();
        
        foreach ($allAssignments as $assignment) {
            $lead = $assignment->lead;
            $assignedUser = $assignment->assignedTo;
            
            if (!$lead || !$assignedUser || !$assignedUser->role) {
                continue;
            }
            
            $userRole = $assignedUser->role->slug ?? '';
            $leadKey = "{$lead->id}_{$assignedUser->id}";
            
            if (isset($allProcessed[$leadKey])) {
                continue;
            }
            
            if ($userRole === \App\Models\Role::SALES_EXECUTIVE) {
                $existingTask = TelecallerTask::where('lead_id', $lead->id)
                    ->where('assigned_to', $assignedUser->id)
                    ->where('task_type', 'calling')
                    ->first();
                
                if (!$existingTask) {
                    $tasksToCreate[] = [
                        'lead_id' => $lead->id,
                        'lead_name' => $lead->name,
                        'user_id' => $assignedUser->id,
                        'user_name' => $assignedUser->name,
                        'role' => $userRole,
                        'model' => 'TelecallerTask',
                    ];
                }
            } elseif (in_array($userRole, [\App\Models\Role::ASSISTANT_SALES_MANAGER])) {
                $existingTask = Task::where('lead_id', $lead->id)
                    ->where('assigned_to', $assignedUser->id)
                    ->where('type', 'phone_call')
                    ->first();
                
                if (!$existingTask) {
                    $tasksToCreate[] = [
                        'lead_id' => $lead->id,
                        'lead_name' => $lead->name,
                        'user_id' => $assignedUser->id,
                        'user_name' => $assignedUser->name,
                        'role' => $userRole,
                        'model' => 'Task',
                    ];
                }
            }
            
            $allProcessed[$leadKey] = true;
        }
        
        $count = count($tasksToCreate);
        
        if ($count === 0) {
            $this->info('✅ No leads found that need tasks. All leads already have calling tasks.');
            return 0;
        }
        
        $this->info("Found {$count} leads that need calling tasks created.");
        $this->warn('This will create:');
        $this->line("  - {$count} calling tasks");
        $this->line('  - Tasks will be scheduled 15 minutes ago (overdue immediately)');
        
        // Show sample
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
                        $task = Task::create([
                            'lead_id' => $taskData['lead_id'],
                            'assigned_to' => $taskData['user_id'],
                            'type' => 'phone_call',
                            'title' => "Call lead: {$taskData['lead_name']}",
                            'description' => "Phone call task for lead: {$taskData['lead_name']}",
                            'status' => 'pending',
                            'scheduled_at' => now()->subMinutes(15),
                            'created_by' => 1,
                        ]);
                        $created++;
                    } else {
                        $task = TelecallerTask::create([
                            'lead_id' => $taskData['lead_id'],
                            'assigned_to' => $taskData['user_id'],
                            'task_type' => 'calling',
                            'status' => 'pending',
                            'scheduled_at' => now()->subMinutes(15),
                            'created_by' => 1,
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
            return 1;
        }
        
        return 0;
    }
}

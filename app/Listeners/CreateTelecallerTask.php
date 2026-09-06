<?php

namespace App\Listeners;

use App\Events\LeadAssigned;
use App\Events\DashboardUpdate;
use App\Models\CrmAssignment;
use App\Models\TelecallerTask;
use App\Models\User;
use App\Models\ActivityLog;
use App\Services\LeadSingleOpenTaskService;
use App\Services\TelecallerTaskService;
use Illuminate\Support\Facades\Log;

class CreateTelecallerTask
{
    protected $telecallerTaskService;
    protected $leadSingleOpenTaskService;

    public function __construct(TelecallerTaskService $telecallerTaskService, LeadSingleOpenTaskService $leadSingleOpenTaskService)
    {
        $this->telecallerTaskService = $telecallerTaskService;
        $this->leadSingleOpenTaskService = $leadSingleOpenTaskService;
    }

    /**
     * Handle the event - Auto-create calling task when lead is assigned to sales executive,
     * sales manager, senior manager, or assistant sales manager.
     */
    public function handle(LeadAssigned $event): void
    {
        Log::info("CreateTelecallerTask listener triggered", [
            'lead_id' => $event->lead->id,
            'assigned_to' => $event->assignedTo,
            'assigned_by' => $event->assignedBy,
        ]);
        
        try {
            $assignedUser = User::with('role')->find($event->assignedTo);

            if (!$assignedUser) {
                Log::warning("CreateTelecallerTask: Assigned user not found", [
                    'lead_id' => $event->lead->id,
                    'assigned_to' => $event->assignedTo,
                ]);
                return;
            }
            
            if (!$assignedUser->role) {
                Log::warning("CreateTelecallerTask: Assigned user has no role", [
                    'lead_id' => $event->lead->id,
                    'assigned_to' => $event->assignedTo,
                    'user_id' => $assignedUser->id,
                ]);
                return;
            }

            // Check if assigned user has eligible role (telecaller, sales_executive, sales_manager, senior_manager, assistant_sales_manager)
            // EXCLUDE CRM and Admin roles
            $userRole = $assignedUser->role->slug ?? '';
            $eligibleRoles = [\App\Models\Role::TELECALLER, \App\Models\Role::SALES_EXECUTIVE, \App\Models\Role::SALES_MANAGER, \App\Models\Role::SENIOR_MANAGER, \App\Models\Role::ASSISTANT_SALES_MANAGER];
            $excludedRoles = [\App\Models\Role::CRM, \App\Models\Role::ADMIN];
            
            // Explicitly exclude CRM and Admin
            if (in_array($userRole, $excludedRoles)) {
                Log::info("CreateTelecallerTask: User role excluded from task creation (CRM/Admin)", [
                    'lead_id' => $event->lead->id,
                    'assigned_to' => $event->assignedTo,
                    'user_role' => $userRole,
                ]);
                return;
            }
            
            if (!in_array($userRole, $eligibleRoles)) {
                Log::info("CreateTelecallerTask: User role not eligible for task creation", [
                    'lead_id' => $event->lead->id,
                    'assigned_to' => $event->assignedTo,
                    'user_role' => $userRole,
                    'eligible_roles' => $eligibleRoles,
                ]);
                return;
            }

            $lead = $event->lead;

            if ($this->leadSingleOpenTaskService->hasOpenTaskForLead($lead->id)) {
                Log::info("CreateTelecallerTask: skipped because lead already has an open task", [
                    'lead_id' => $lead->id,
                    'assigned_to' => $assignedUser->id,
                ]);
                return;
            }
            
            Log::info("CreateTelecallerTask: Processing task creation", [
                'lead_id' => $lead->id,
                'lead_name' => $lead->name,
                'assigned_to' => $assignedUser->id,
                'assigned_to_name' => $assignedUser->name,
                'user_role' => $userRole,
            ]);

            // Create CrmAssignment if it doesn't exist (only for sales executives)
            if ($assignedUser->isSalesExecutive()) {
                $crmAssignment = CrmAssignment::where('lead_id', $lead->id)
                    ->where('assigned_to', $assignedUser->id)
                    ->where('call_status', 'pending')
                    ->first();

                if (!$crmAssignment) {
                    $crmAssignment = CrmAssignment::create([
                        'lead_id' => $lead->id,
                        'customer_name' => $lead->name,
                        'phone' => $lead->phone,
                        'assigned_to' => $assignedUser->id,
                        'assigned_by' => $event->assignedBy,
                        'assigned_at' => now(),
                        'call_status' => 'pending',
                    ]);
                }
            }

            // For sales managers and sales executives, use Task model
            // For managers and assistant sales managers, use Task model
            if (in_array($userRole, [\App\Models\Role::SALES_MANAGER, \App\Models\Role::SENIOR_MANAGER, \App\Models\Role::ASSISTANT_SALES_MANAGER])) {
                // Check if Task already exists for this lead and user (any status to avoid duplicates)
                $existingTask = \App\Models\Task::where('lead_id', $lead->id)
                    ->where('assigned_to', $assignedUser->id)
                    ->where('type', 'phone_call')
                    ->first();

                if (!$existingTask) {
                    $isFreshTransfer = $lead->isFreshTransfer();
                    // Create Task for sales manager/executive
                    // Schedule task 10 minutes from now - becomes overdue after 10 minutes
                    $task = \App\Models\Task::create([
                        'lead_id' => $lead->id,
                        'assigned_to' => $assignedUser->id,
                        'type' => 'phone_call',
                        'title' => $isFreshTransfer ? "Fresh transfer call: {$lead->name}" : "Call lead: {$lead->name}",
                        'description' => $isFreshTransfer
                            ? "Fresh transfer lead handoff for {$lead->name} ({$lead->phone})"
                            : "Phone call task for lead: {$lead->name} ({$lead->phone})",
                        'status' => 'pending',
                        'scheduled_at' => now()->addMinutes(10), // Set to 10 minutes from now - becomes overdue after 10 min
                        'created_by' => $event->assignedBy,
                        'notes' => $isFreshTransfer ? 'Fresh transfer lead handoff. Contact this lead as a new owner.' : null,
                    ]);
                    
                    Log::info("Auto-created calling task (Task model) for assigned user", [
                        'task_id' => $task->id,
                        'lead_id' => $lead->id,
                        'user_id' => $assignedUser->id,
                        'role' => $userRole,
                        'scheduled_at' => $task->scheduled_at->format('Y-m-d H:i:s'),
                    ]);
                    
                    // Log activity for lead timeline
                    \App\Models\ActivityLog::create([
                        'user_id' => $event->assignedBy,
                        'action' => 'task_created',
                        'model_type' => 'Lead',
                        'model_id' => $lead->id,
                        'description' => "Calling task created for {$lead->name} (Assigned to {$assignedUser->name})",
                        'old_values' => null,
                        'new_values' => [
                            'task_id' => $task->id,
                            'task_model' => 'Task',
                            'task_type' => 'phone_call',
                            'assigned_to' => $assignedUser->id,
                            'assigned_to_name' => $assignedUser->name,
                        ],
                    ]);
                    
                    // Broadcast dashboard update
                    event(new DashboardUpdate($assignedUser->id, 'task_created', [
                        'lead_id' => $lead->id,
                        'lead_name' => $lead->name,
                        'task_id' => $task->id,
                    ]));
                }
            } else {
                // For sales executives, use TelecallerTask
                $existingTask = TelecallerTask::where('lead_id', $lead->id)
                    ->where('assigned_to', $assignedUser->id)
                    ->where('task_type', 'calling')
                    ->where('status', 'pending')
                    ->first();

                if (!$existingTask) {
                    try {
                        // Auto-create calling task for sales executives
                        $task = $this->telecallerTaskService->createCallingTask(
                            $lead,
                            $assignedUser,
                            $event->assignedBy
                        );
                        
                        Log::info("✅ Auto-created calling task (TelecallerTask) for assigned user - SUCCESS", [
                            'task_id' => $task->id,
                            'lead_id' => $lead->id,
                            'lead_name' => $lead->name,
                            'user_id' => $assignedUser->id,
                            'user_name' => $assignedUser->name,
                            'role' => $userRole,
                            'scheduled_at' => $task->scheduled_at ? $task->scheduled_at->format('Y-m-d H:i:s') : 'null',
                        ]);
                    } catch (\Exception $taskError) {
                        Log::error("❌ Failed to create TelecallerTask for sales executive", [
                            'lead_id' => $lead->id,
                            'assigned_to' => $assignedUser->id,
                            'error' => $taskError->getMessage(),
                            'trace' => $taskError->getTraceAsString(),
                        ]);
                        throw $taskError; // Re-throw to be caught by outer catch
                    }
                    
                    // Log activity for lead timeline
                    \App\Models\ActivityLog::create([
                        'user_id' => $event->assignedBy,
                        'action' => 'task_created',
                        'model_type' => 'Lead',
                        'model_id' => $lead->id,
                        'description' => "Calling task created for {$lead->name} (Assigned to {$assignedUser->name})",
                        'old_values' => null,
                        'new_values' => [
                            'task_id' => $task->id,
                            'task_model' => 'TelecallerTask',
                            'task_type' => 'calling',
                            'assigned_to' => $assignedUser->id,
                            'assigned_to_name' => $assignedUser->name,
                        ],
                    ]);
                    
                    // Broadcast dashboard update
                    event(new DashboardUpdate($assignedUser->id, 'task_created', [
                        'lead_id' => $lead->id,
                        'lead_name' => $lead->name,
                        'task_id' => $task->id,
                    ]));
                }
            }
        } catch (\Exception $e) {
            Log::error("❌ CreateTelecallerTask listener failed completely", [
                'lead_id' => $event->lead->id,
                'lead_name' => $event->lead->name ?? 'unknown',
                'assigned_to' => $event->assignedTo,
                'assigned_by' => $event->assignedBy,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            // Don't re-throw - we don't want to break the assignment process
        }
    }
}

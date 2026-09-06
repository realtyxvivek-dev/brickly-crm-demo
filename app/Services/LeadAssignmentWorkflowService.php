<?php

namespace App\Services;

use App\Events\LeadAssigned;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeadAssignmentWorkflowService
{
    public function __construct(
        private readonly LeadOwnerTaskService $leadOwnerTaskService,
        private readonly LeadTaskCleanupService $leadTaskCleanupService
    ) {
    }

    public function assignLead(
        Lead $lead,
        int $assignedTo,
        int $assignedBy,
        ?string $notes = null,
        bool $createCallingTask = true,
        bool $transferExistingTasks = true
    ): array {
        return DB::transaction(function () use (
            $lead,
            $assignedTo,
            $assignedBy,
            $notes,
            $createCallingTask,
            $transferExistingTasks
        ) {
            $oldAssignments = $lead->assignments()
                ->where('is_active', true)
                ->get(['assigned_to']);

            $oldOwnerIds = $oldAssignments
                ->pluck('assigned_to')
                ->filter(fn ($id) => (int) $id !== (int) $assignedTo)
                ->unique()
                ->values();

            $lead->assignments()->where('is_active', true)->update([
                'is_active' => false,
                'unassigned_at' => now(),
            ]);

            $assignment = LeadAssignment::create([
                'lead_id' => $lead->id,
                'assigned_to' => $assignedTo,
                'assigned_by' => $assignedBy,
                'assignment_type' => 'primary',
                'notes' => $notes,
                'assigned_at' => now(),
                'is_active' => true,
            ]);

            $deletedTaskCounts = [
                'telecaller_tasks' => 0,
                'manager_tasks' => 0,
                'crm_assignments' => 0,
            ];

            if ($oldOwnerIds->isNotEmpty()) {
                foreach ($oldOwnerIds as $oldOwnerId) {
                    $cleanup = $this->leadTaskCleanupService->deleteTasksForLeadAndOwner(
                        $lead->id,
                        (int) $oldOwnerId,
                        $assignedBy,
                        'lead_transferred'
                    );

                    $deletedTaskCounts['telecaller_tasks'] += $cleanup['telecaller_tasks'] ?? 0;
                    $deletedTaskCounts['manager_tasks'] += $cleanup['tasks'] ?? 0;
                    $deletedTaskCounts['crm_assignments'] += $cleanup['crm_assignments'] ?? 0;
                }
            }

            $taskResult = [
                'created' => false,
                'task_type' => null,
                'task_id' => null,
                'action_url' => null,
            ];
            $eventDispatched = false;
            $taskError = null;

            if ($createCallingTask) {
                try {
                    event(new LeadAssigned($lead, $assignedTo, $assignedBy));
                    $eventDispatched = true;
                } catch (\Throwable $e) {
                    $taskError = $e->getMessage();

                    Log::warning("LeadAssignmentWorkflowService: LeadAssigned dispatch failed for lead {$lead->id}", [
                        'lead_id' => $lead->id,
                        'assigned_to' => $assignedTo,
                        'error' => $e->getMessage(),
                    ]);
                }

                try {
                    $assignee = User::with('role')->find($assignedTo);
                    if ($assignee) {
                        $taskResult = $this->leadOwnerTaskService->ensureOpenTaskForOwner(
                            $lead,
                            $assignee,
                            $assignedBy,
                            $notes
                        );
                    }
                } catch (\Throwable $e) {
                    $taskError = $e->getMessage();

                    Log::warning("LeadAssignmentWorkflowService: fallback task creation failed for lead {$lead->id}: " . $e->getMessage());
                }
            }

            return [
                'assignment_id' => $assignment->id,
                'old_owner_ids' => $oldOwnerIds->all(),
                'transferred_counts' => $deletedTaskCounts,
                'task_result' => $taskResult,
                'task_error' => $taskError,
                'event_dispatched' => $eventDispatched,
            ];
        });
    }
}

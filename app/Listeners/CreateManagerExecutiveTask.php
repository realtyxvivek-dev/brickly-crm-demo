<?php

namespace App\Listeners;

use App\Events\ProspectSentForVerification;
use App\Models\Prospect;
use App\Models\User;
use App\Services\TaskService;
use Illuminate\Support\Facades\Log;

class CreateManagerExecutiveTask
{
    protected $taskService;

    public function __construct(TaskService $taskService)
    {
        $this->taskService = $taskService;
    }

    /**
     * Handle the event - Auto-create calling task for manager/executive when prospect is assigned
     */
    public function handle(ProspectSentForVerification $event): void
    {
        try {
            $prospect = $event->prospect;

            if (!$prospect->telecaller_id) {
                return;
            }

            // Check if prospect has manager_id (assigned manager)
            $verificationAssigneeId = $prospect->assigned_manager ?? $prospect->manager_id;

            if (!$verificationAssigneeId) {
                Log::warning("No manager assigned for prospect", [
                    'prospect_id' => $prospect->id,
                ]);
                return;
            }

            // Verify the assignee exists and is active
            $assignee = User::find($verificationAssigneeId);
            if (!$assignee || !$assignee->is_active) {
                Log::warning("Assigned manager not found or inactive", [
                    'prospect_id' => $prospect->id,
                    'assignee_id' => $verificationAssigneeId,
                ]);
                return;
            }

            // Check if assignee is Senior Manager or Sales Executive
            if (!$assignee->relationLoaded('role')) {
                $assignee->load('role');
            }

            $roleSlug = $assignee->role->slug ?? '';
            if (!in_array($roleSlug, ['sales_manager', 'sales_executive'])) {
                Log::info("Assignee is not a Senior Manager or Sales Executive, skipping task creation", [
                    'prospect_id' => $prospect->id,
                    'assignee_id' => $verificationAssigneeId,
                    'role' => $roleSlug,
                ]);
                return;
            }

            // Check if task already exists for this prospect and assignee (using Task model)
            $existingTaskQuery = \App\Models\Task::where('assigned_to', $verificationAssigneeId)
                ->where('type', 'phone_call')
                ->where('status', 'pending')
                ->where('title', 'like', '%prospect verification%');
            
            // Only filter by lead_id if prospect has one
            if ($prospect->lead_id) {
                $existingTaskQuery->where('lead_id', $prospect->lead_id);
            } else {
                // If no lead_id, check by customer name in title
                $customerName = $prospect->customer_name ?? '';
                if ($customerName) {
                    $existingTaskQuery->where('title', 'like', "%{$customerName}%");
                }
            }
            
            $existingTask = $existingTaskQuery->first();

            if ($existingTask) {
                Log::info("Manager prospect call task already exists", [
                    'prospect_id' => $prospect->id,
                    'task_id' => $existingTask->id,
                ]);
                return;
            }

            // Create the verification call task scheduled 10 minutes from now
            $createdBy = $prospect->created_by ?? $prospect->telecaller_id ?? 1;
            
            $task = $this->taskService->createManagerProspectCallTask(
                $prospect,
                $verificationAssigneeId,
                $createdBy
            );

            Log::info("Auto-created manager executive prospect call task", [
                'task_id' => $task->id,
                'prospect_id' => $prospect->id,
                'assignee_id' => $verificationAssigneeId,
                'scheduled_at' => $task->scheduled_at,
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to create manager executive task for prospect {$event->prospect->id}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}

<?php

namespace App\Listeners;

use App\Events\ProspectSentForVerification;
use App\Models\Prospect;
use App\Models\User;
use App\Services\TaskService;
use Illuminate\Support\Facades\Log;

class CreateManagerVerificationCallTask
{
    protected $taskService;

    public function __construct(TaskService $taskService)
    {
        $this->taskService = $taskService;
    }

    /**
     * Handle the event - Auto-create calling task for manager/senior manager when prospect is sent for verification
     */
    public function handle(ProspectSentForVerification $event): void
    {
        try {
            $prospect = $event->prospect;

            // Only create task if verification status is pending_verification
            if ($prospect->verification_status !== 'pending_verification') {
                return;
            }

            if (!$prospect->telecaller_id) {
                return;
            }

            // Get the person assigned for verification
            // Priority: assigned_manager > manager_id
            $verificationAssigneeId = $prospect->assigned_manager ?? $prospect->manager_id;

            if (!$verificationAssigneeId) {
                Log::warning("No verification assignee found for prospect", [
                    'prospect_id' => $prospect->id,
                ]);
                return;
            }

            // Verify the assignee exists and is active
            $assignee = User::find($verificationAssigneeId);
            if (!$assignee || !$assignee->is_active) {
                Log::warning("Verification assignee not found or inactive", [
                    'prospect_id' => $prospect->id,
                    'assignee_id' => $verificationAssigneeId,
                ]);
                return;
            }

            // Task requires lead_id, so skip if prospect doesn't have one
            if (!$prospect->lead_id) {
                Log::warning("Prospect has no lead_id, skipping task creation", [
                    'prospect_id' => $prospect->id,
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
                Log::info("Verification call task already exists for prospect", [
                    'prospect_id' => $prospect->id,
                    'task_id' => $existingTask->id,
                ]);
                return;
            }

            // Create the verification call task scheduled 10 minutes from now
            $createdBy = $prospect->created_by ?? $prospect->telecaller_id ?? 1;
            
            $task = $this->taskService->createManagerVerificationCallTask(
                $prospect,
                $verificationAssigneeId,
                $createdBy
            );

            Log::info("Auto-created manager verification call task", [
                'task_id' => $task->id,
                'prospect_id' => $prospect->id,
                'assignee_id' => $verificationAssigneeId,
                'scheduled_at' => $task->scheduled_at,
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to create manager verification call task for prospect {$event->prospect->id}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}

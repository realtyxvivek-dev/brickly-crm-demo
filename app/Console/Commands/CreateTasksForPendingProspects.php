<?php

namespace App\Console\Commands;

use App\Models\Prospect;
use App\Models\TelecallerTask;
use App\Models\User;
use App\Services\TaskService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CreateTasksForPendingProspects extends Command
{
    protected $signature = 'prospects:create-tasks {--force : Force create tasks even if they exist}';
    protected $description = 'Create calling tasks for all pending prospects that don\'t have tasks yet';

    protected $taskService;

    public function __construct(TaskService $taskService)
    {
        parent::__construct();
        $this->taskService = $taskService;
    }

    public function handle()
    {
        $this->info('Creating calling tasks for pending prospects...');

        // Get all prospects with pending_verification status
        $prospects = Prospect::where('verification_status', 'pending_verification')
            ->whereNotNull('lead_id') // Only prospects with lead_id
            ->get();

        if ($prospects->isEmpty()) {
            $this->info('No pending prospects found.');
            return 0;
        }

        $this->info("Found {$prospects->count()} pending prospects.");

        $created = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($prospects as $prospect) {
            try {
                // Get the manager assigned for verification
                $verificationAssigneeId = $prospect->assigned_manager ?? $prospect->manager_id;

                if (!$verificationAssigneeId) {
                    $this->warn("Prospect #{$prospect->id} has no manager assigned. Skipping.");
                    $skipped++;
                    continue;
                }

                // Verify the assignee exists and is active
                $assignee = User::find($verificationAssigneeId);
                if (!$assignee || !$assignee->is_active) {
                    $this->warn("Prospect #{$prospect->id} has invalid or inactive assignee (ID: {$verificationAssigneeId}). Skipping.");
                    $skipped++;
                    continue;
                }

                // Check if task already exists (unless --force)
                if (!$this->option('force')) {
                    $existingTask = TelecallerTask::where('assigned_to', $verificationAssigneeId)
                        ->where('task_type', 'calling')
                        ->where('lead_id', $prospect->lead_id)
                        ->where('status', 'pending')
                        ->first();

                    if ($existingTask) {
                        $this->line("Task already exists for Prospect #{$prospect->id}. Skipping.");
                        $skipped++;
                        continue;
                    }
                }

                // Create the task
                $createdBy = $prospect->created_by ?? $prospect->telecaller_id ?? 1;

                $task = $this->taskService->createManagerVerificationCallTask(
                    $prospect,
                    $verificationAssigneeId,
                    $createdBy
                );

                $this->info("Created task #{$task->id} for Prospect #{$prospect->id} (Manager: {$assignee->name})");
                $created++;

            } catch (\Exception $e) {
                $this->error("Error creating task for Prospect #{$prospect->id}: " . $e->getMessage());
                Log::error("Failed to create task for prospect {$prospect->id}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $errors++;
            }
        }

        $this->newLine();
        $this->info("Summary:");
        $this->info("  - Created: {$created}");
        $this->info("  - Skipped: {$skipped}");
        $this->info("  - Errors: {$errors}");

        return 0;
    }
}

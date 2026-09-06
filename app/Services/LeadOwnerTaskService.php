<?php

namespace App\Services;

use App\Events\DashboardUpdate;
use App\Models\ActivityLog;
use App\Models\CrmAssignment;
use App\Models\Lead;
use App\Models\Task;
use App\Models\TelecallerTask;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class LeadOwnerTaskService
{
    private const OPEN_STATUSES = ['pending', 'in_progress', 'rescheduled'];
    private const MANAGER_TASKS_ROUTE = 'sales-manager.tasks';

    public function __construct(
        private readonly TelecallerTaskService $telecallerTaskService,
        private readonly LeadSingleOpenTaskService $leadSingleOpenTaskService
    ) {
    }

    public function ensureOpenTaskForOwner(Lead $lead, User $assignedUser, int $assignedBy, ?string $notes = null): array
    {
        if (!$assignedUser->role || $assignedUser->isAdmin()) {
            return [
                'created' => false,
                'task_type' => null,
                'task_id' => null,
                'action_url' => null,
            ];
        }

        $existingOpenTask = $this->leadSingleOpenTaskService->findOpenTaskForLead($lead->id);
        if ($existingOpenTask instanceof TelecallerTask) {
            return [
                'created' => false,
                'task_type' => 'telecaller_task',
                'task_id' => $existingOpenTask->id,
                'action_url' => url('/telecaller/tasks?status=pending&task_id=' . $existingOpenTask->id),
            ];
        }

        if ($existingOpenTask instanceof Task) {
            return [
                'created' => false,
                'task_type' => 'task',
                'task_id' => $existingOpenTask->id,
                'action_url' => $this->buildManagerLeadActionUrl($lead, $existingOpenTask),
            ];
        }

        if ($assignedUser->isSalesExecutive()) {
            $this->ensurePendingCrmAssignment($lead, $assignedUser, $assignedBy);

            $task = TelecallerTask::where('lead_id', $lead->id)
                ->where('assigned_to', $assignedUser->id)
                ->where('task_type', 'calling')
                ->whereIn('status', self::OPEN_STATUSES)
                ->latest('id')
                ->first();

            $created = false;
            if (!$task) {
                $task = $this->telecallerTaskService->createCallingTask($lead, $assignedUser, $assignedBy);
                $created = true;
            }

            if ($task && $notes && !$task->notes) {
                $task->update(['notes' => $notes]);
            }

            $this->logTaskCreated($lead, $assignedUser, $assignedBy, $task, 'TelecallerTask', 'calling', $created);

            return [
                'created' => $created,
                'task_type' => 'telecaller_task',
                'task_id' => $task?->id,
                'action_url' => $task
                    ? url('/telecaller/tasks?status=pending&task_id=' . $task->id)
                    : url('/telecaller/tasks?status=pending'),
            ];
        }

        $task = Task::where('lead_id', $lead->id)
            ->where('assigned_to', $assignedUser->id)
            ->where('type', 'phone_call')
            ->whereIn('status', self::OPEN_STATUSES)
            ->latest('id')
            ->first();

        $created = false;
        if (!$task) {
            $isFreshTransfer = $lead->isFreshTransfer();
            $task = Task::create([
                'lead_id' => $lead->id,
                'assigned_to' => $assignedUser->id,
                'type' => 'phone_call',
                'title' => $isFreshTransfer ? "Fresh transfer call: {$lead->name}" : "Call lead: {$lead->name}",
                'description' => $isFreshTransfer
                    ? "Fresh transfer lead handoff for {$lead->name} ({$lead->phone})"
                    : "Phone call task for lead: {$lead->name} ({$lead->phone})",
                'status' => 'pending',
                'scheduled_at' => now()->addMinutes(10),
                'created_by' => $assignedBy,
                'notes' => $notes ?: ($isFreshTransfer ? 'Fresh transfer lead handoff. Contact this lead as a new owner.' : null),
            ]);
            $created = true;
        }

        $this->logTaskCreated($lead, $assignedUser, $assignedBy, $task, 'Task', 'phone_call', $created);

        return [
            'created' => $created,
            'task_type' => 'task',
            'task_id' => $task?->id,
            'action_url' => $this->buildManagerLeadActionUrl($lead, $task),
        ];
    }

    public function resolveActionUrlForOwner(Lead $lead, User $assignedUser): ?string
    {
        if (!$assignedUser->role || $assignedUser->isAdmin()) {
            return null;
        }

        $existingOpenTask = $this->leadSingleOpenTaskService->findOpenTaskForLead($lead->id);
        if ($existingOpenTask instanceof TelecallerTask) {
            return url('/telecaller/tasks?status=pending&task_id=' . $existingOpenTask->id);
        }

        if ($existingOpenTask instanceof Task) {
            return $this->buildManagerLeadActionUrl($lead, $existingOpenTask);
        }

        if ($assignedUser->isSalesExecutive()) {
            $task = TelecallerTask::where('lead_id', $lead->id)
                ->where('assigned_to', $assignedUser->id)
                ->where('task_type', 'calling')
                ->whereIn('status', self::OPEN_STATUSES)
                ->latest('id')
                ->first();

            return $task
                ? url('/telecaller/tasks?status=pending&task_id=' . $task->id)
                : url('/telecaller/tasks?status=pending');
        }

        $task = Task::where('lead_id', $lead->id)
            ->where('assigned_to', $assignedUser->id)
            ->where('type', 'phone_call')
            ->whereIn('status', self::OPEN_STATUSES)
            ->latest('id')
            ->first();

        return $this->buildManagerLeadActionUrl($lead, $task);
    }

    private function buildManagerLeadActionUrl(Lead $lead, ?Task $task = null): string
    {
        $params = [
            'back' => route(self::MANAGER_TASKS_ROUTE),
        ];

        if ($task) {
            $params['open_task'] = $task->id;
            $params['task_category'] = $this->determineManagerTaskCategory($task);
        }

        return route('leads.show', ['lead' => $lead->id] + $params);
    }

    private function determineManagerTaskCategory(Task $task): string
    {
        if ($task->follow_up_id) {
            return 'follow_up';
        }

        if ($task->meeting_id) {
            return 'meeting';
        }

        if ($task->site_visit_id) {
            return 'site_visit';
        }

        if (($task->type ?? null) === 'phone_call') {
            return 'fresh_lead';
        }

        return 'other';
    }

    private function ensurePendingCrmAssignment(Lead $lead, User $assignedUser, int $assignedBy): void
    {
        if (!Schema::hasTable('crm_assignments')) {
            return;
        }

        CrmAssignment::firstOrCreate(
            [
                'lead_id' => $lead->id,
                'assigned_to' => $assignedUser->id,
                'call_status' => 'pending',
            ],
            [
                'customer_name' => $lead->name,
                'phone' => $lead->phone,
                'assigned_by' => $assignedBy,
                'assigned_at' => now(),
            ]
        );
    }

    private function logTaskCreated(
        Lead $lead,
        User $assignedUser,
        int $assignedBy,
        Task|TelecallerTask|null $task,
        string $taskModel,
        string $taskType,
        bool $created
    ): void {
        if (!$task || !$created) {
            return;
        }

        ActivityLog::create([
            'user_id' => $assignedBy,
            'action' => 'task_created',
            'model_type' => 'Lead',
            'model_id' => $lead->id,
            'description' => "Calling task created for {$lead->name} (Assigned to {$assignedUser->name})",
            'old_values' => null,
            'new_values' => [
                'task_id' => $task->id,
                'task_model' => $taskModel,
                'task_type' => $taskType,
                'assigned_to' => $assignedUser->id,
                'assigned_to_name' => $assignedUser->name,
                'role' => $assignedUser->role->slug ?? null,
            ],
        ]);

        event(new DashboardUpdate($assignedUser->id, 'task_created', [
            'lead_id' => $lead->id,
            'lead_name' => $lead->name,
            'task_id' => $task->id,
        ]));
    }
}

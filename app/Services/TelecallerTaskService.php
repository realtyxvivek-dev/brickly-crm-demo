<?php

namespace App\Services;

use App\Models\TelecallerTask;
use App\Models\Meeting;
use App\Models\SiteVisit;
use App\Models\Lead;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TelecallerTaskService
{
    public function __construct(
        private readonly LeadSingleOpenTaskService $leadSingleOpenTaskService
    ) {
    }

    /**
     * Create a calling task for a telecaller when lead is assigned
     */
    public function createCallingTask(Lead $lead, User $telecaller, ?int $createdBy = null): TelecallerTask
    {
        return $this->createScheduledCallingTask($lead, $telecaller, now()->addMinutes(10), $createdBy);
    }

    public function createScheduledCallingTask(Lead $lead, User $telecaller, Carbon $scheduledAt, ?int $createdBy = null, ?string $notes = null): TelecallerTask
    {
        $createdBy = $createdBy ?? auth()->id() ?? 1; // Fallback to user ID 1 if no auth
        $existingOpenTask = $this->leadSingleOpenTaskService->findOpenTaskForLead($lead->id);

        if ($existingOpenTask instanceof TelecallerTask) {
            return $existingOpenTask;
        }

        if ($existingOpenTask !== null) {
            throw new \RuntimeException('Please complete old task first.');
        }

        if (!$notes && $lead->isFreshTransfer()) {
            $notes = 'Fresh transfer lead handoff. Contact this lead as a new owner.';
        }

        $task = TelecallerTask::create([
            'lead_id' => $lead->id,
            'assigned_to' => $telecaller->id,
            'task_type' => 'calling',
            'status' => 'pending',
            'scheduled_at' => $scheduledAt,
            'created_by' => $createdBy,
            'notes' => $notes,
        ]);

        Log::info('Calling task created', [
            'task_id' => $task->id,
            'lead_id' => $lead->id,
            'telecaller_id' => $telecaller->id,
            'scheduled_at' => $task->scheduled_at->format('Y-m-d H:i:s'),
        ]);

        return $task;
    }

    /**
     * Create a CNP retry task
     */
    public function createCnpRetryTask(Lead $lead, User $telecaller, ?Carbon $scheduledAt = null, ?int $createdBy = null): TelecallerTask
    {
        $createdBy = $createdBy ?? auth()->id() ?? 1;
        $existingOpenTask = $this->leadSingleOpenTaskService->findOpenTaskForLead($lead->id);

        if ($existingOpenTask instanceof TelecallerTask) {
            return $existingOpenTask;
        }

        if ($existingOpenTask !== null) {
            throw new \RuntimeException('Please complete old task first.');
        }
        
        // Use provided scheduled time, or default to tomorrow (backward compatibility)
        $scheduledTime = $scheduledAt ?? now()->addDay();
        
        // Format time description for task notes
        if ($scheduledAt) {
            $timeDescription = $scheduledTime->format('d M Y, h:i A');
            $timeNote = "Scheduled for {$timeDescription}";
        } else {
            $timeDescription = 'tomorrow';
            $timeNote = "Scheduled for tomorrow";
        }

        $task = TelecallerTask::create([
            'lead_id' => $lead->id,
            'assigned_to' => $telecaller->id,
            'task_type' => 'cnp_retry',
            'status' => 'pending',
            'scheduled_at' => $scheduledTime,
            'created_by' => $createdBy,
            'notes' => "CNP retry task created on " . now()->format('Y-m-d H:i:s') . " - {$timeNote}",
        ]);

        Log::info('CNP retry task created', [
            'task_id' => $task->id,
            'lead_id' => $lead->id,
            'telecaller_id' => $telecaller->id,
            'scheduled_at' => $scheduledTime->format('Y-m-d H:i:s'),
        ]);

        return $task;
    }

    /**
     * Create a calling task 30 minutes before meeting/site visit scheduled time
     */
    public function createCallTaskBeforeScheduled($item, int $createdBy = null): ?TelecallerTask
    {
        try {
            // Determine if it's a Meeting or SiteVisit
            if ($item instanceof Meeting) {
                $leadId = $item->lead_id;
                $scheduledAt = $item->scheduled_at;
                $itemType = 'Meeting';
            } elseif ($item instanceof SiteVisit) {
                $leadId = $item->lead_id;
                $scheduledAt = $item->scheduled_at;
                $itemType = 'Site Visit';
            } else {
                Log::warning('Invalid item type for creating call task', ['item' => get_class($item)]);
                return null;
            }

            if (!$leadId || !$scheduledAt) {
                Log::warning('Missing lead_id or scheduled_at for call task creation', [
                    'lead_id' => $leadId,
                    'scheduled_at' => $scheduledAt,
                ]);
                return null;
            }

            // Get the lead and find the telecaller assigned to it
            $lead = Lead::with('activeAssignments.assignedTo')->find($leadId);
            if (!$lead) {
                Log::warning('Lead not found for call task creation', ['lead_id' => $leadId]);
                return null;
            }

            // Get telecaller from active assignment
            $telecaller = null;
            if ($lead->activeAssignments && $lead->activeAssignments->count() > 0) {
                $assignment = $lead->activeAssignments->first();
                $assignedUser = $assignment->assignedTo;
                
                // Check if assigned user is a telecaller
                if ($assignedUser && $assignedUser->role && $assignedUser->role->slug === \App\Models\Role::SALES_EXECUTIVE) {
                    $telecaller = $assignedUser;
                }
            }

            if (!$telecaller) {
                Log::warning('No telecaller found for lead', ['lead_id' => $leadId]);
                return null;
            }

            // Calculate task scheduled time (30 minutes before meeting/visit)
            $taskScheduledAt = Carbon::parse($scheduledAt)->subMinutes(30);

            // Don't create task if the time has already passed
            if ($taskScheduledAt->isPast()) {
                Log::info('Skipping call task creation - scheduled time is in the past', [
                    'item_type' => $itemType,
                    'item_id' => $item->id,
                    'scheduled_at' => $scheduledAt,
                    'task_scheduled_at' => $taskScheduledAt,
                ]);
                return null;
            }

            // Check if a similar task already exists
            $existingTaskQuery = TelecallerTask::where('lead_id', $leadId)
                ->where('assigned_to', $telecaller->id)
                ->where('scheduled_at', $taskScheduledAt)
                ->where('status', 'pending');

            if ($item instanceof Meeting) {
                $existingTaskQuery->where('meeting_id', $item->id);
            } elseif (TelecallerTask::supportsColumn('site_visit_id')) {
                $existingTaskQuery->where('site_visit_id', $item->id);
            }

            $existingTask = $existingTaskQuery->first();

            if ($existingTask) {
                Log::info('Call task already exists', [
                    'task_id' => $existingTask->id,
                    'lead_id' => $leadId,
                ]);
                return $existingTask;
            }

            if ($this->leadSingleOpenTaskService->hasOpenTaskForLead($leadId)) {
                Log::info('Skipping call task before scheduled item because lead already has an open task', [
                    'lead_id' => $leadId,
                    'item_type' => $itemType,
                    'item_id' => $item->id,
                ]);
                return null;
            }

            $createdBy = $createdBy ?? auth()->id() ?? 1;

            // Create the task
            $taskPayload = [
                'lead_id' => $leadId,
                'meeting_id' => $item instanceof Meeting ? $item->id : null,
                'assigned_to' => $telecaller->id,
                'task_type' => 'calling',
                'status' => 'pending',
                'scheduled_at' => $taskScheduledAt,
                'notes' => "Reminder call 30 min before scheduled {$itemType}",
                'created_by' => $createdBy,
            ];

            if ($item instanceof SiteVisit && TelecallerTask::supportsColumn('site_visit_id')) {
                $taskPayload['site_visit_id'] = $item->id;
            }

            $task = TelecallerTask::create($taskPayload);

            Log::info('Call task created before scheduled item', [
                'task_id' => $task->id,
                'lead_id' => $leadId,
                'telecaller_id' => $telecaller->id,
                'item_type' => $itemType,
                'item_id' => $item->id,
                'scheduled_at' => $scheduledAt,
                'task_scheduled_at' => $taskScheduledAt,
            ]);

            return $task;
        } catch (\Exception $e) {
            Log::error('Failed to create call task before scheduled item', [
                'error' => $e->getMessage(),
                'item_type' => get_class($item),
                'item_id' => $item->id ?? null,
            ]);
            return null;
        }
    }

    /**
     * Create a follow-up task for a lead when final_status is 'Follow Up'
     */
    public function createFollowUpTask(Lead $lead, int $assignedToUserId, string $followUpDate, string $followUpTime, int $createdBy): TelecallerTask
    {
        $createdBy = $createdBy ?? auth()->id() ?? 1;
        $existingOpenTask = $this->leadSingleOpenTaskService->findOpenTaskForLead($lead->id);

        if ($existingOpenTask instanceof TelecallerTask) {
            return $existingOpenTask;
        }

        if ($existingOpenTask !== null) {
            throw new \RuntimeException('Please complete old task first.');
        }

        // Combine date and time
        $scheduledAt = \Carbon\Carbon::parse($followUpDate . ' ' . $followUpTime);

        // Check if a similar task already exists
        $existingTask = TelecallerTask::where('lead_id', $lead->id)
            ->where('assigned_to', $assignedToUserId)
            ->where('scheduled_at', $scheduledAt)
            ->where('status', 'pending')
            ->first();

        if ($existingTask) {
            Log::info('Follow-up task already exists', [
                'task_id' => $existingTask->id,
                'lead_id' => $lead->id,
            ]);
            return $existingTask;
        }

        $task = TelecallerTask::create([
            'lead_id' => $lead->id,
            'follow_up_id' => null,
            'assigned_to' => $assignedToUserId,
            'task_type' => 'follow_up',
            'status' => 'pending',
            'scheduled_at' => $scheduledAt,
            'notes' => "Follow-up task for lead: {$lead->name}",
            'created_by' => $createdBy,
        ]);

        Log::info('Follow-up task created', [
            'task_id' => $task->id,
            'lead_id' => $lead->id,
            'assigned_to' => $assignedToUserId,
            'scheduled_at' => $scheduledAt,
        ]);

        return $task;
    }

    /**
     * Create a calling task 10 minutes before site visit scheduled time
     */
    public function createSiteVisitCallTask(SiteVisit $siteVisit, ?int $createdBy = null): ?TelecallerTask
    {
        try {
            if (!$siteVisit->scheduled_at) {
                Log::warning('Site visit missing scheduled_at for call task creation', [
                    'site_visit_id' => $siteVisit->id,
                ]);
                return null;
            }

            // Calculate task scheduled time: 10 minutes before site visit
            $taskScheduledAt = Carbon::parse($siteVisit->scheduled_at)->subMinutes(10);

            // If the reminder window has already passed, create an immediate reminder instead of skipping.
            if ($taskScheduledAt->isPast()) {
                Log::info('Adjusting site visit call task time because reminder window is already in the past', [
                    'site_visit_id' => $siteVisit->id,
                    'site_visit_scheduled_at' => $siteVisit->scheduled_at,
                    'original_task_scheduled_at' => $taskScheduledAt,
                ]);
                $taskScheduledAt = now()->addMinute();
            }

            // Check for existing similar task to avoid duplicates
            $existingTaskQuery = TelecallerTask::where('lead_id', $siteVisit->lead_id)
                ->where('assigned_to', $siteVisit->created_by)
                ->where('task_type', 'calling')
                ->where('scheduled_at', $taskScheduledAt)
                ->where('status', 'pending');

            if (TelecallerTask::supportsColumn('site_visit_id')) {
                $existingTaskQuery->where('site_visit_id', $siteVisit->id);
            }

            $existingTask = $existingTaskQuery->first();

            if ($existingTask) {
                Log::info('Site visit call task already exists', [
                    'task_id' => $existingTask->id,
                    'site_visit_id' => $siteVisit->id,
                ]);
                return $existingTask;
            }

            if ($this->leadSingleOpenTaskService->hasOpenTaskForLead($siteVisit->lead_id)) {
                Log::info('Skipping site visit call task because lead already has an open task', [
                    'lead_id' => $siteVisit->lead_id,
                    'site_visit_id' => $siteVisit->id,
                ]);
                return null;
            }

            $createdBy = $createdBy ?? $siteVisit->created_by ?? auth()->id() ?? 1;

            // Create task with status='pending' and scheduled_at 10 minutes before site visit
            // Task will initially appear in "pending" section
            // When scheduled_at time passes, task will automatically appear in "overdue" section
            // (based on TelecallerTask::scopeOverdue - checks scheduled_at < now() and status != 'completed')
            $taskPayload = [
                'lead_id' => $siteVisit->lead_id,
                'assigned_to' => $siteVisit->created_by, // Site visit creator
                'task_type' => 'calling',
                'status' => 'pending', // Initially pending
                'scheduled_at' => $taskScheduledAt, // 10 minutes before site visit
                'notes' => "Reminder call 10 min before site visit scheduled at " . 
                    $siteVisit->scheduled_at->format('M d, Y h:i A'),
                'created_by' => $createdBy,
            ];

            if (TelecallerTask::supportsColumn('site_visit_id')) {
                $taskPayload['site_visit_id'] = $siteVisit->id;
            }

            $task = TelecallerTask::create($taskPayload);

            Log::info('Site visit call task created', [
                'task_id' => $task->id,
                'site_visit_id' => $siteVisit->id,
                'lead_id' => $siteVisit->lead_id,
                'assigned_to' => $siteVisit->created_by,
                'site_visit_scheduled_at' => $siteVisit->scheduled_at,
                'task_scheduled_at' => $taskScheduledAt,
            ]);

            return $task;
        } catch (\Exception $e) {
            Log::error('Failed to create site visit call task', [
                'error' => $e->getMessage(),
                'site_visit_id' => $siteVisit->id ?? null,
            ]);
            return null;
        }
    }
}

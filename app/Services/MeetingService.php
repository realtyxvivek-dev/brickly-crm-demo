<?php

namespace App\Services;

use App\Models\Meeting;
use App\Models\Task;
use App\Models\TelecallerTask;
use App\Models\User;
use App\Models\Lead;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MeetingService
{
    public function __construct(
        private readonly LeadSingleOpenTaskService $leadSingleOpenTaskService
    ) {
    }

    /**
     * Create meeting with optional pre-meeting reminder task
     */
    public function createMeetingWithReminder(array $data, User $creator): Meeting
    {
        DB::beginTransaction();
        
        try {
            // Set creator and initial status
            $data['created_by'] = $creator->id;
            $data['assigned_to'] = $data['assigned_to'] ?? $creator->id;
            $data['status'] = $data['status'] ?? 'scheduled';
            $data['verification_status'] = $data['verification_status'] ?? 'pending';
            
            // Get lead's customer name and phone if not provided
            if (!empty($data['lead_id'])) {
                $lead = Lead::find($data['lead_id']);
                if ($lead) {
                    $data['customer_name'] = $data['customer_name'] ?? $lead->name;
                    $data['phone'] = $data['phone'] ?? $lead->phone;
                } else {
                    throw new \Exception("Lead with ID {$data['lead_id']} not found");
                }
            }
            
            // Validate required fields
            if (empty($data['customer_name'])) {
                throw new \Exception("Customer name is required");
            }
            if (empty($data['phone'])) {
                throw new \Exception("Phone number is required");
            }
            if (empty($data['scheduled_at'])) {
                throw new \Exception("Scheduled date and time is required");
            }
            
            // Validate location for offline meetings (already validated in controller, but double-check)
            if (($data['meeting_mode'] ?? 'offline') === 'offline' && (empty($data['location']) || trim($data['location']) === '')) {
                throw new \Exception("Location is required for offline meetings");
            }
            
            // Set date_of_visit from scheduled_at (required field)
            if (!empty($data['scheduled_at'])) {
                $scheduledAt = Carbon::parse($data['scheduled_at']);
                $data['date_of_visit'] = $data['date_of_visit'] ?? $scheduledAt->format('Y-m-d');
            } else {
                // Fallback to today if scheduled_at is not provided (shouldn't happen due to validation)
                $data['date_of_visit'] = $data['date_of_visit'] ?? now()->format('Y-m-d');
            }

            $existingMeeting = $this->findDuplicateOpenMeeting($data, $scheduledAt ?? null);
            if ($existingMeeting) {
                Log::info('Skipping duplicate meeting creation; returning existing meeting', [
                    'existing_meeting_id' => $existingMeeting->id,
                    'lead_id' => $existingMeeting->lead_id,
                    'assigned_to' => $existingMeeting->assigned_to,
                    'scheduled_at' => optional($existingMeeting->scheduled_at)->toDateTimeString(),
                ]);

                DB::commit();

                return $existingMeeting->fresh(['lead', 'preMeetingCallTask', 'assignedTo']);
            }
            
            // Create meeting
            $meeting = Meeting::create($data);
            app(LeadActiveWorkflowService::class)->moveLeadToWorkflow(
                $meeting->lead_id,
                'meeting',
                $meeting->id
            );

            if (!empty($lead)) {
                $lead->updateStatusIfAllowed('meeting_scheduled');
            }
            
            // Create pre-meeting reminder task if enabled
            if (!empty($data['reminder_enabled']) && !empty($data['scheduled_at'])) {
                $reminderMinutes = $data['reminder_minutes'] ?? 5;
                $scheduledAt = Carbon::parse($data['scheduled_at']);
                $reminderTime = $scheduledAt->copy()->subMinutes($reminderMinutes);

                // If reminder offset time is already past, create an immediate reminder instead of skipping.
                if ($reminderTime->isPast()) {
                    $reminderTime = now()->addMinute();
                }

                $assignedUser = User::with('role')->find($meeting->assigned_to);
                $hasExistingReminderTask = $this->hasOpenReminderTaskForMeeting($meeting);

                if ($hasExistingReminderTask) {
                    Log::info('Skipping pre-meeting reminder task because this meeting already has an open reminder task', [
                        'meeting_id' => $meeting->id,
                        'lead_id' => $meeting->lead_id,
                    ]);
                } elseif ($assignedUser && ($assignedUser->isSalesManager() || $assignedUser->isSalesHead() || $assignedUser->isAssistantSalesManager() || $assignedUser->isSeniorManager())) {
                    // Create Task for manager hierarchy users.
                    $taskPayload = [
                        'lead_id' => $meeting->lead_id,
                        'assigned_to' => $meeting->assigned_to,
                        'type' => 'phone_call',
                        'status' => 'pending',
                        'scheduled_at' => $reminderTime,
                        'title' => "Pre-meeting reminder call - {$meeting->customer_name}",
                        'description' => "Pre-meeting reminder call for meeting scheduled at " . $scheduledAt->format('Y-m-d H:i'),
                        'notes' => "Pre-meeting reminder call for meeting scheduled at " . $scheduledAt->format('Y-m-d H:i') . " | Meeting ID: {$meeting->id}",
                        'created_by' => $creator->id,
                    ];

                    if (Task::supportsColumn('meeting_id')) {
                        $taskPayload['meeting_id'] = $meeting->id;
                    }

                    Task::create($taskPayload);
                } else {
                    // Create TelecallerTask for telecaller-side users.
                    $task = TelecallerTask::create([
                        'lead_id' => $meeting->lead_id,
                        'meeting_id' => $meeting->id,
                        'assigned_to' => $meeting->assigned_to,
                        'task_type' => 'pre_meeting_reminder',
                        'status' => 'pending',
                        'scheduled_at' => $reminderTime,
                        'notes' => "Pre-meeting reminder call for meeting scheduled at " . $scheduledAt->format('Y-m-d H:i'),
                        'created_by' => $creator->id,
                    ]);

                    // Link TelecallerTask to meeting (only TelecallerTask can be referenced by pre_meeting_call_task_id)
                    $meeting->pre_meeting_call_task_id = $task->id;
                    $meeting->save();
                }
            }
            
            DB::commit();
            
            return $meeting->fresh(['lead', 'preMeetingCallTask', 'assignedTo']);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create meeting with reminder', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            throw $e;
        }
    }

    private function findDuplicateOpenMeeting(array $data, ?Carbon $scheduledAt): ?Meeting
    {
        if (empty($data['lead_id']) || !$scheduledAt) {
            return null;
        }

        $query = Meeting::withoutGlobalScopes()
            ->where('lead_id', $data['lead_id'])
            ->where('assigned_to', $data['assigned_to'])
            ->whereBetween('scheduled_at', [
                $scheduledAt->copy()->subSeconds(60),
                $scheduledAt->copy()->addSeconds(60),
            ])
            ->whereNotIn('status', ['cancelled', 'completed', 'rejected'])
            ->whereNull('deleted_at')
            ->lockForUpdate();

        if (!empty($data['meeting_mode'])) {
            $query->where('meeting_mode', $data['meeting_mode']);
        }

        if (!empty($data['meeting_sequence'])) {
            $query->where('meeting_sequence', $data['meeting_sequence']);
        }

        return $query->latest('id')->first();
    }
    
    /**
     * Handle pre-meeting call completion
     */
    public function handlePreCallComplete(Meeting $meeting, string $action, ?string $notes = null, ?int $userId = null): array
    {
        DB::beginTransaction();
        
        try {
            // Mark calling task as completed if exists
            if ($meeting->pre_meeting_call_task_id) {
                $task = TelecallerTask::find($meeting->pre_meeting_call_task_id);
                if ($task && $task->status !== 'completed') {
                    $task->status = 'completed';
                    $task->completed_at = now();
                    $task->outcome = $action;
                    $task->notes = ($task->notes ? $task->notes . "\n" : '') . ($notes ?? "Call completed with action: $action");
                    $task->save();
                }
            }
            
            $result = ['action' => $action, 'meeting_id' => $meeting->id];
            
            // Handle based on action
            switch ($action) {
                case 'confirm':
                    $meeting->confirmMeeting();
                    $result['message'] = 'Meeting confirmed! Customer will join.';
                    $result['status'] = 'confirmed';
                    break;
                    
                case 'cancel':
                    $meeting->cancelMeeting($userId ?? auth()->id(), 'Customer cancelled via pre-meeting call');
                    $result['message'] = 'Meeting has been cancelled.';
                    $result['status'] = 'cancelled';
                    break;
                    
                case 'reschedule':
                    // Return instructions for frontend to handle reschedule flow
                    $result['message'] = 'Please reschedule the meeting.';
                    $result['status'] = 'pending_reschedule';
                    $result['require_reschedule'] = true;
                    break;
                    
                default:
                    throw new \InvalidArgumentException("Invalid action: $action");
            }
            
            DB::commit();
            
            return $result;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to handle pre-call complete', [
                'error' => $e->getMessage(),
                'meeting_id' => $meeting->id,
                'action' => $action,
            ]);
            throw $e;
        }
    }
    
    /**
     * Cancel old meeting and create new one (reschedule)
     */
    public function cancelAndReschedule(Meeting $oldMeeting, array $newData, User $user): Meeting
    {
        DB::beginTransaction();
        
        try {
            // Cancel old meeting
            $oldMeeting->cancelMeeting($user->id, 'Rescheduled');
            
            // Prepare new meeting data based on old meeting
            $newMeetingData = [
                'lead_id' => $oldMeeting->lead_id,
                'customer_name' => $oldMeeting->customer_name,
                'phone' => $oldMeeting->phone,
                'assigned_to' => $oldMeeting->assigned_to,
                'meeting_mode' => $newData['meeting_mode'] ?? $oldMeeting->meeting_mode,
                'meeting_link' => $newData['meeting_link'] ?? $oldMeeting->meeting_link,
                'location' => $newData['location'] ?? $oldMeeting->location,
                'scheduled_at' => $newData['scheduled_at'],
                'reminder_enabled' => $newData['reminder_enabled'] ?? $oldMeeting->reminder_enabled,
                'reminder_minutes' => $newData['reminder_minutes'] ?? $oldMeeting->reminder_minutes,
                'meeting_notes' => $newData['meeting_notes'] ?? "Rescheduled from " . $oldMeeting->scheduled_at->format('Y-m-d H:i'),
                'original_meeting_id' => $oldMeeting->id,
            ];
            
            // Get next meeting sequence for this lead
            if ($oldMeeting->lead_id) {
                $newMeetingData['meeting_sequence'] = $newData['meeting_sequence'] ?? $this->getLeadMeetingSequence($oldMeeting->lead_id);
            }
            
            // Create new meeting with reminder
            $newMeeting = $this->createMeetingWithReminder($newMeetingData, $user);
            
            DB::commit();
            
            return $newMeeting;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to reschedule meeting', [
                'error' => $e->getMessage(),
                'old_meeting_id' => $oldMeeting->id,
            ]);
            throw $e;
        }
    }
    
    /**
     * Get next meeting sequence number for a lead
     */
    public function getLeadMeetingSequence(int $leadId): int
    {
        $maxSequence = Meeting::where('lead_id', $leadId)
            ->where('status', '!=', 'cancelled')
            ->max('meeting_sequence');
            
        return ($maxSequence ?? 0) + 1;
    }

    public function syncPreMeetingReminderAfterReschedule(Meeting $meeting, User $actor, ?Carbon $oldScheduledAt = null): void
    {
        $meeting->refresh();
        $this->cancelOpenPreMeetingReminderTasks($meeting, $actor, 'Cancelled due to meeting reschedule.');

        if (!$meeting->reminder_enabled || !$meeting->scheduled_at) {
            $meeting->forceFill(['pre_meeting_call_task_id' => null])->save();
            return;
        }

        $scheduledAt = Carbon::parse($meeting->scheduled_at);
        $reminderMinutes = (int) ($meeting->reminder_minutes ?? 5);
        $reminderTime = $scheduledAt->copy()->subMinutes(max(0, $reminderMinutes));

        if ($reminderTime->isPast()) {
            $reminderTime = now()->addMinute();
        }

        $assignedUser = User::with('role')->find($meeting->assigned_to);
        if (!$assignedUser) {
            Log::warning('Skipping pre-meeting reminder sync because assigned user was not found', [
                'meeting_id' => $meeting->id,
                'assigned_to' => $meeting->assigned_to,
            ]);
            return;
        }

        if ($assignedUser->isSalesManager() || $assignedUser->isSalesHead() || $assignedUser->isAssistantSalesManager() || $assignedUser->isSeniorManager()) {
            Task::create([
                'lead_id' => $meeting->lead_id,
                'assigned_to' => $meeting->assigned_to,
                'type' => 'phone_call',
                'status' => 'pending',
                'scheduled_at' => $reminderTime,
                'title' => "Pre-meeting reminder call - {$meeting->customer_name}",
                'description' => "Pre-meeting reminder call for meeting scheduled at " . $scheduledAt->format('Y-m-d H:i'),
                'notes' => "Pre-meeting reminder call for meeting scheduled at " . $scheduledAt->format('Y-m-d H:i') . " | Meeting ID: {$meeting->id}",
                'created_by' => $actor->id,
            ]);

            if ($meeting->pre_meeting_call_task_id) {
                $meeting->forceFill(['pre_meeting_call_task_id' => null])->save();
            }

            return;
        }

        $task = TelecallerTask::create([
            'lead_id' => $meeting->lead_id,
            'meeting_id' => $meeting->id,
            'assigned_to' => $meeting->assigned_to,
            'task_type' => 'pre_meeting_reminder',
            'status' => 'pending',
            'scheduled_at' => $reminderTime,
            'notes' => "Pre-meeting reminder call for meeting scheduled at " . $scheduledAt->format('Y-m-d H:i'),
            'created_by' => $actor->id,
        ]);

        $meeting->forceFill(['pre_meeting_call_task_id' => $task->id])->save();
    }

    private function cancelOpenPreMeetingReminderTasks(Meeting $meeting, User $actor, string $reason): void
    {
        $cancelledAt = now();

        Task::withoutGlobalScopes()
            ->where('lead_id', $meeting->lead_id)
            ->whereIn('status', Task::OPEN_STATUSES)
            ->whereNull('completed_at')
            ->where('title', 'like', 'Pre-meeting reminder call - %')
            ->where(function ($query) use ($meeting) {
                $query->where('notes', 'like', "%Meeting ID: {$meeting->id}%");

                if (!Task::supportsColumn('meeting_id')) {
                    return;
                }

                $query->orWhere('meeting_id', $meeting->id);
            })
            ->get()
            ->each(function (Task $task) use ($cancelledAt, $reason) {
                $notes = trim((string) $task->notes);
                $task->forceFill([
                    'status' => 'cancelled',
                    'outcome' => 'rescheduled',
                    'completed_at' => $cancelledAt,
                    'outcome_recorded_at' => $cancelledAt,
                    'notes' => trim($notes . "\n" . $reason),
                ])->save();
            });

        TelecallerTask::withoutGlobalScopes()
            ->where('lead_id', $meeting->lead_id)
            ->where('meeting_id', $meeting->id)
            ->whereIn('status', TelecallerTask::OPEN_STATUSES)
            ->whereNull('completed_at')
            ->get()
            ->each(function (TelecallerTask $task) use ($cancelledAt, $reason) {
                $notes = trim((string) $task->notes);
                $task->forceFill([
                    'status' => 'cancelled',
                    'outcome' => 'rescheduled',
                    'completed_at' => $cancelledAt,
                    'notes' => trim($notes . "\n" . $reason),
                ])->save();
            });
    }

    private function hasOpenReminderTaskForMeeting(Meeting $meeting): bool
    {
        $managerTaskExists = Task::withoutGlobalScopes()
            ->where('lead_id', $meeting->lead_id)
            ->whereIn('status', Task::OPEN_STATUSES)
            ->whereNull('completed_at')
            ->when(
                Task::supportsColumn('meeting_id'),
                fn ($query) => $query->where('meeting_id', $meeting->id),
                fn ($query) => $query->where('title', 'like', 'Pre-meeting reminder call - %')
            )
            ->exists();

        if ($managerTaskExists) {
            return true;
        }

        return TelecallerTask::withoutGlobalScopes()
            ->where('lead_id', $meeting->lead_id)
            ->where('meeting_id', $meeting->id)
            ->whereIn('status', TelecallerTask::OPEN_STATUSES)
            ->whereNull('completed_at')
            ->exists();
    }
}

<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\ActivityLog;
use App\Models\Task;
use App\Models\TelecallerTask;
use App\Models\User;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class LeadActivityService
{
    /**
     * Get complete timeline of all activities for a lead
     */
    public function getTimeline(Lead $lead, ?User $viewer = null): Collection
    {
        $activities = collect();

        // 1. Lead Created
        $displayCreatedAt = $lead->display_created_at;

        if ($displayCreatedAt) {
            $activities->push([
                'type' => 'created',
                'title' => 'Lead Created',
                'description' => "Lead '{$lead->name}' was created",
                'user' => $lead->creator,
                'timestamp' => $displayCreatedAt,
                'icon' => 'fa-plus-circle',
                'color' => '#10b981', // green
                'metadata' => [
                    'source' => $lead->source,
                    'status' => $lead->status,
                    'crm_created_at' => $lead->crm_created_at,
                ],
            ]);
        }

        // 2. Activity Logs
        $activityLogs = ActivityLog::where('model_type', 'Lead')
            ->where('model_id', $lead->id)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        $taskIdsFromActivityLog = collect();
        $telecallerTaskIdsFromActivityLog = collect();

        foreach ($activityLogs as $log) {
            $automationMetadata = $this->detectAutomationMetadata($log);
            $entry = [
                'type' => $this->getActivityType($log->action),
                'title' => $this->getActivityTitle($log),
                'description' => $log->description ?? $this->getActivityDescription($log),
                'user' => $automationMetadata ? null : $log->user,
                'timestamp' => $log->created_at,
                'icon' => $this->getActivityIcon($log->action),
                'color' => $this->getActivityColor($log->action),
                'metadata' => [
                    'old_values' => $log->old_values,
                    'new_values' => $log->new_values,
                    'action' => $log->action,
                    'automation' => $automationMetadata,
                ],
            ];
            $activities->push($entry);

            // Track task_ids already in timeline (from ActivityLog) to avoid duplicate in step 9
            if ($log->action === 'task_created' && $log->new_values && isset($log->new_values['task_id'])) {
                $taskId = $log->new_values['task_id'];
                $model = $log->new_values['task_model'] ?? null;
                if ($model === 'Task') {
                    $taskIdsFromActivityLog->push($taskId);
                } elseif ($model === 'TelecallerTask') {
                    $telecallerTaskIdsFromActivityLog->push($taskId);
                } else {
                    $taskIdsFromActivityLog->push($taskId);
                    $telecallerTaskIdsFromActivityLog->push($taskId);
                }
            }
        }

        // 3. Lead Assignments
        foreach ($lead->assignments()->with(['assignedTo', 'assignedBy'])->orderBy('assigned_at', 'desc')->get() as $assignment) {
            $assigneeName = $assignment->assignedTo->name ?? 'Unknown';
            $activities->push([
                'type' => 'assigned',
                'title' => 'Lead Assigned',
                'description' => $assignment->is_active 
                    ? "Assigned to {$assigneeName}"
                    : "Unassigned from {$assigneeName}",
                'user' => $assignment->assignedBy,
                'timestamp' => $assignment->assigned_at ?? $assignment->created_at,
                'icon' => $assignment->is_active ? 'fa-user-plus' : 'fa-user-minus',
                'color' => $assignment->is_active ? '#3b82f6' : '#ef4444',
                'metadata' => [
                    'assigned_to' => $assigneeName,
                    'is_active' => $assignment->is_active,
                ],
            ]);
        }

        // 4. Call Logs
        foreach ($lead->callLogs()->with('user')->orderBy('created_at', 'desc')->get() as $callLog) {
            $callType = $callLog->call_type === 'incoming' ? 'Inbound' : 'Outbound';
            $duration = $callLog->duration ? $this->formatDuration($callLog->duration) : 'N/A';
            $canPlayRecording = $this->viewerCanAccessCallRecording($viewer, $lead);
            
            $activities->push([
                'type' => 'call',
                'title' => "{$callType} Call",
                'description' => "{$callType} call with {$lead->name}. Duration: {$duration}",
                'user' => $callLog->user,
                'timestamp' => $callLog->created_at,
                'icon' => $callLog->call_type === 'incoming' ? 'fa-phone-alt' : 'fa-phone',
                'color' => $callLog->call_type === 'incoming' ? '#10b981' : '#3b82f6',
                'metadata' => [
                    'direction' => $callLog->direction,
                    'duration' => $callLog->duration,
                    'recording_url' => $callLog->recording_url,
                    'can_play_recording' => $canPlayRecording && filled($callLog->recording_url),
                    'recording_route' => ($canPlayRecording && filled($callLog->recording_url))
                        ? route('calls.recording', $callLog)
                        : null,
                    'recording_download_route' => ($canPlayRecording && filled($callLog->recording_url))
                        ? route('calls.recording', ['callLog' => $callLog->id, 'download' => 1])
                        : null,
                    'status' => $callLog->status,
                ],
            ]);
        }

        // 5. Site Visits
        foreach ($lead->siteVisits()->withoutGlobalScope('visible_in_queue')->with(['creator', 'assignedTo', 'verifiedBy', 'closingVerifiedBy', 'rescheduledBy', 'incentives.user'])->orderBy('created_at', 'desc')->get() as $siteVisit) {
            $siteVisitForm = [
                'title' => 'Site Visit Details',
                'fields' => $this->submittedFormFields($siteVisit, [
                    'customer_name' => 'Customer Name', 'phone' => 'Phone', 'employee' => 'Employee', 'occupation' => 'Occupation',
                    'date_of_visit' => 'Visit Date', 'project' => 'Project', 'visited_projects' => 'Visited Projects',
                    'scheduled_at' => 'Scheduled Date & Time', 'completed_at' => 'Completed At', 'status' => 'Status',
                    'property_type' => 'Property Type', 'budget_range' => 'Budget', 'payment_mode' => 'Payment Mode',
                    'tentative_period' => 'Tentative Period', 'tentative_closing_time' => 'Tentative Closing Time', 'lead_type' => 'Lead Type',
                    'visit_notes' => 'Visit Notes', 'feedback' => 'Customer Feedback', 'rating' => 'Rating',
                    'verification_status' => 'Verification Status',
                ]),
                'files' => $this->submittedFormFiles($siteVisit, [
                    'photos' => 'Visit Photo',
                    'completion_proof_photos' => 'Completion Proof',
                ]),
            ];
            // Site Visit Created event
            $projectsText = $siteVisit->project ? ". Projects: " . $siteVisit->project : '';
            $activities->push([
                'type' => 'site_visit_created',
                'title' => 'Site Visit Created',
                'description' => "Site visit scheduled for {$lead->name}" . 
                    ($siteVisit->scheduled_at ? " on " . $siteVisit->scheduled_at->format('M d, Y h:i A') : '') .
                    $projectsText,
                'user' => $siteVisit->creator,
                'timestamp' => $siteVisit->created_at,
                'icon' => 'fa-calendar-plus',
                'color' => '#3b82f6', // blue
                'metadata' => [
                    'scheduled_at' => $siteVisit->scheduled_at,
                    'project' => $siteVisit->project,
                    'status' => $siteVisit->status,
                    'submitted_form' => $siteVisitForm,
                ],
            ]);

            if ($siteVisit->is_rescheduled && $siteVisit->rescheduled_at) {
                $activities->push([
                    'type' => 'site_visit_rescheduled',
                    'title' => 'Site Visit Rescheduled',
                    'description' => "Site visit rescheduled for {$lead->name}" .
                        ($siteVisit->scheduled_at ? " to " . $siteVisit->scheduled_at->format('M d, Y h:i A') : '') .
                        ($siteVisit->reschedule_reason ? ". Reason: {$siteVisit->reschedule_reason}" : ''),
                    'user' => $siteVisit->rescheduledBy ?? $siteVisit->creator,
                    'timestamp' => $siteVisit->rescheduled_at,
                    'icon' => 'fa-calendar-day',
                    'color' => '#f59e0b',
                    'metadata' => [
                        'scheduled_at' => $siteVisit->scheduled_at,
                        'reschedule_reason' => $siteVisit->reschedule_reason,
                        'reschedule_count' => $siteVisit->reschedule_count,
                        'submitted_form' => $siteVisitForm,
                    ],
                ]);
            }

            // Site Visit Completed event (only if status is completed)
            if ($siteVisit->status === 'completed' && $siteVisit->completed_at) {
                $projectsVisitedText = $siteVisit->project ? ". Projects visited: " . $siteVisit->project : '';
                $activities->push([
                    'type' => 'site_visit_completed',
                    'title' => 'Site Visit Completed',
                    'description' => "Site visit completed for {$lead->name}" . $projectsVisitedText,
                    'user' => $siteVisit->creator,
                    'timestamp' => $siteVisit->completed_at,
                    'icon' => 'fa-check-circle',
                    'color' => '#10b981', // green
                    'metadata' => [
                        'completed_at' => $siteVisit->completed_at,
                        'project' => $siteVisit->project,
                        'rating' => $siteVisit->rating,
                        'submitted_form' => $siteVisitForm,
                    ],
                ]);
            }

            // If verified, add verification activity
            if ($siteVisit->verified_at && $siteVisit->verifiedBy) {
                $activities->push([
                    'type' => 'site_visit_verified',
                    'title' => 'Site Visit Verified',
                    'description' => "Site visit verified by {$siteVisit->verifiedBy->name}",
                    'user' => $siteVisit->verifiedBy,
                    'timestamp' => $siteVisit->verified_at,
                    'icon' => 'fa-check-circle',
                    'color' => '#10b981',
                    'metadata' => [
                        'verification_status' => $siteVisit->verification_status,
                        'submitted_form' => [
                            'title' => 'Site Visit Form',
                            'fields' => $this->submittedFormFields($siteVisit, [
                                'customer_name' => 'Customer Name',
                                'phone' => 'Phone',
                                'employee' => 'Employee',
                                'occupation' => 'Occupation',
                                'date_of_visit' => 'Visit Date',
                                'project' => 'Project',
                                'visited_projects' => 'Visited Projects',
                                'property_type' => 'Property Type',
                                'budget_range' => 'Budget',
                                'payment_mode' => 'Payment Mode',
                                'tentative_period' => 'Tentative Period',
                                'lead_type' => 'Lead Type',
                                'visit_notes' => 'Visit Notes',
                                'feedback' => 'Customer Feedback',
                                'rating' => 'Rating',
                            ]),
                        ],
                    ],
                ]);
            }

            if ($siteVisit->closing_verification_status === 'pending' && $siteVisit->converted_to_closer_at) {
                $activities->push([
                    'type' => 'close_requested',
                    'title' => 'Close Requested',
                    'description' => "Close request submitted for {$lead->name}",
                    'user' => $siteVisit->creator,
                    'timestamp' => $siteVisit->converted_to_closer_at,
                    'icon' => 'fa-file-signature',
                    'color' => '#2563eb',
                    'metadata' => [
                        'closing_verification_status' => $siteVisit->closing_verification_status,
                    ],
                ]);
            }

            if ($siteVisit->closing_verified_at && $siteVisit->closingVerifiedBy) {
                $activities->push([
                    'type' => 'close_verified',
                    'title' => 'Close Verified',
                    'description' => "Close verified by {$siteVisit->closingVerifiedBy->name}",
                    'user' => $siteVisit->closingVerifiedBy,
                    'timestamp' => $siteVisit->closing_verified_at,
                    'icon' => 'fa-stamp',
                    'color' => '#16a34a',
                    'metadata' => [
                        'closing_verification_status' => $siteVisit->closing_verification_status,
                    ],
                ]);
            }

            if (!empty($siteVisit->kyc_documents) && $siteVisit->updated_at) {
                $activities->push([
                    'type' => 'kyc_submitted',
                    'title' => 'KYC Submitted',
                    'description' => "KYC documents submitted for {$lead->name}",
                    'user' => $siteVisit->creator,
                    'timestamp' => $siteVisit->updated_at,
                    'icon' => 'fa-id-card',
                    'color' => '#7c3aed',
                    'metadata' => [
                        'kyc_documents_count' => is_array($siteVisit->kyc_documents) ? count($siteVisit->kyc_documents) : 0,
                    ],
                ]);
            }

            foreach ($siteVisit->incentives as $incentive) {
                $activities->push([
                    'type' => 'incentive_submitted',
                    'title' => 'Incentive Submitted',
                    'description' => "Incentive request of ₹{$incentive->amount} submitted",
                    'user' => $incentive->user,
                    'timestamp' => $incentive->created_at,
                    'icon' => 'fa-money-bill-wave',
                    'color' => '#b45309',
                    'metadata' => [
                        'status' => $incentive->status,
                        'amount' => $incentive->amount,
                        'type' => $incentive->type,
                    ],
                ]);
            }
        }

        // 6. Follow-ups
        foreach ($lead->followUps()->withoutGlobalScope('visible_in_queue')->with('creator')->orderBy('created_at', 'desc')->get() as $followUp) {
            $followUpForm = ['title' => 'Follow-up Details', 'fields' => $this->submittedFormFields($followUp, [
                'type' => 'Follow-up Type', 'status' => 'Status', 'scheduled_at' => 'Scheduled Date & Time', 'completed_at' => 'Completed At', 'outcome' => 'Outcome', 'notes' => 'Remark',
            ])];
            $activities->push([
                'type' => 'followup',
                'title' => 'Follow-up ' . ucfirst($followUp->status),
                'description' => "Follow-up {$followUp->status}" . 
                    ($followUp->scheduled_at ? " scheduled for " . $followUp->scheduled_at->format('M d, Y h:i A') : ''),
                'user' => $followUp->creator,
                'timestamp' => $followUp->created_at,
                'icon' => $this->getFollowUpIcon($followUp->status),
                'color' => $this->getFollowUpColor($followUp->status),
                'metadata' => [
                    'type' => $followUp->type,
                    'status' => $followUp->status,
                    'scheduled_at' => $followUp->scheduled_at,
                    'completed_at' => $followUp->completed_at,
                    'submitted_form' => $followUpForm,
                ],
            ]);
        }

        // 6.5. Next Follow-up (upcoming scheduled follow-up)
        $nextFollowUp = $lead->followUps()
            ->where('status', 'scheduled')
            ->where('scheduled_at', '>', now())
            ->orderBy('scheduled_at', 'asc')
            ->with('creator')
            ->first();

        if ($nextFollowUp) {
            $activities->push([
                'type' => 'next_followup',
                'title' => 'Next Follow-up Scheduled',
                'description' => "Follow-up scheduled for " . $nextFollowUp->scheduled_at->format('M d, Y h:i A'),
                'user' => $nextFollowUp->creator,
                'timestamp' => $nextFollowUp->created_at,
                'icon' => 'fa-calendar-check',
                'color' => '#f59e0b', // amber
                'metadata' => [
                    'scheduled_at' => $nextFollowUp->scheduled_at,
                    'type' => $nextFollowUp->type,
                ],
            ]);
        }

        // 7. Meetings
        foreach ($lead->meetings()->withoutGlobalScope('visible_in_queue')->with(['creator', 'assignedTo', 'verifiedBy', 'rescheduledBy'])->orderBy('created_at', 'desc')->get() as $meeting) {
            $meetingForm = ['title' => 'Meeting Details', 'fields' => $this->submittedFormFields($meeting, [
                'project' => 'Project', 'scheduled_at' => 'Scheduled Date & Time', 'completed_at' => 'Completed At', 'status' => 'Status', 'meeting_mode' => 'Meeting Mode', 'location' => 'Location',
                'customer_name' => 'Customer Name', 'property_type' => 'Property Type', 'budget_range' => 'Budget', 'payment_mode' => 'Payment Mode', 'meeting_notes' => 'Meeting Notes', 'feedback' => 'Customer Feedback', 'rating' => 'Rating',
            ])];
            $activities->push([
                'type' => 'meeting',
                'title' => 'Meeting ' . ucfirst($meeting->status),
                'description' => "Meeting {$meeting->status}" . 
                    ($meeting->scheduled_at ? " scheduled for " . $meeting->scheduled_at->format('M d, Y h:i A') : ''),
                'user' => $meeting->creator,
                'timestamp' => $meeting->created_at,
                'icon' => $this->getMeetingIcon($meeting->status),
                'color' => $this->getMeetingColor($meeting->status),
                'metadata' => [
                    'status' => $meeting->status,
                    'scheduled_at' => $meeting->scheduled_at,
                    'verification_status' => $meeting->verification_status,
                    'customer_name' => $meeting->customer_name,
                    'submitted_form' => $meetingForm,
                ],
            ]);

            if ($meeting->is_rescheduled && $meeting->rescheduled_at) {
                $activities->push([
                    'type' => 'meeting_rescheduled',
                    'title' => 'Meeting Rescheduled',
                    'description' => "Meeting rescheduled for {$lead->name}" .
                        ($meeting->scheduled_at ? " to " . $meeting->scheduled_at->format('M d, Y h:i A') : '') .
                        ($meeting->reschedule_reason ? ". Reason: {$meeting->reschedule_reason}" : ''),
                    'user' => $meeting->rescheduledBy ?? $meeting->creator,
                    'timestamp' => $meeting->rescheduled_at,
                    'icon' => 'fa-calendar-day',
                    'color' => '#f59e0b',
                    'metadata' => [
                        'scheduled_at' => $meeting->scheduled_at,
                        'reschedule_reason' => $meeting->reschedule_reason,
                        'reschedule_count' => $meeting->reschedule_count,
                        'submitted_form' => $meetingForm,
                    ],
                ]);
            }

            // If verified, add verification activity
            if ($meeting->verified_at && $meeting->verifiedBy) {
                $activities->push([
                    'type' => 'meeting_verified',
                    'title' => 'Meeting Verified',
                    'description' => "Meeting verified by {$meeting->verifiedBy->name}",
                    'user' => $meeting->verifiedBy,
                    'timestamp' => $meeting->verified_at,
                    'icon' => 'fa-check-circle',
                    'color' => '#10b981',
                    'metadata' => [
                        'verification_status' => $meeting->verification_status,
                        'submitted_form' => [
                            'title' => 'Meeting Form',
                            'fields' => $this->submittedFormFields($meeting, [
                                'customer_name' => 'Customer Name',
                                'phone' => 'Phone',
                                'employee' => 'Employee',
                                'occupation' => 'Occupation',
                                'scheduled_at' => 'Scheduled Date & Time',
                                'date_of_visit' => 'Meeting Date',
                                'project' => 'Project',
                                'property_type' => 'Property Type',
                                'budget_range' => 'Budget',
                                'payment_mode' => 'Payment Mode',
                                'tentative_period' => 'Tentative Period',
                                'meeting_mode' => 'Meeting Mode',
                                'location' => 'Location',
                                'meeting_notes' => 'Meeting Notes',
                                'feedback' => 'Customer Feedback',
                                'rating' => 'Rating',
                            ]),
                        ],
                    ],
                ]);
            }
        }

        // 8. Prospects
        foreach ($lead->prospects()->with(['createdBy', 'verifiedBy'])->orderBy('created_at', 'desc')->get() as $prospect) {
            $activities->push([
                'type' => 'prospect',
                'title' => 'Prospect Created',
                'description' => "Prospect created for {$lead->name}" . 
                    ($prospect->lead_score ? " with lead score: {$prospect->lead_score}/5" : ''),
                'user' => $prospect->createdBy,
                'timestamp' => $prospect->created_at,
                'icon' => 'fa-user-check',
                'color' => '#8b5cf6',
                'metadata' => [
                    'verification_status' => $prospect->verification_status,
                    'lead_score' => $prospect->lead_score,
                ],
            ]);

            // If verified, add verification activity
            if ($prospect->verified_at && $prospect->verifiedBy) {
                $activities->push([
                    'type' => 'prospect_verified',
                    'title' => 'Prospect Verified',
                    'description' => "Prospect verified by {$prospect->verifiedBy->name}",
                    'user' => $prospect->verifiedBy,
                    'timestamp' => $prospect->verified_at,
                    'icon' => 'fa-check-circle',
                    'color' => '#10b981',
                    'metadata' => [
                        'verification_status' => $prospect->verification_status,
                    ],
                ]);
            }
        }

        // 9. Tasks Created (for this lead) – skip if already in timeline from ActivityLog (step 2)
        $tasks = Task::query()->withoutGlobalScope('visible_in_queue')->withTrashed()->where('lead_id', $lead->id)
            ->with(['assignedTo', 'creator', 'activities.user'])
            ->orderBy('created_at', 'desc')
            ->get();

        foreach ($tasks as $task) {
            $taskCompletionMetadata = [
                'task_id' => $task->id,
                'task_type' => $task->type,
                'assigned_to' => $task->assignedTo ? $task->assignedTo->name : null,
                'status' => $task->status,
                'outcome' => $task->outcome,
                'outcome_remark' => $task->outcome_remark,
                'next_action_at' => $task->next_action_at,
            ];

            $completionActivity = $task->activities->first(function ($activity) {
                return $activity->activity_type === 'status_changed'
                    && $activity->new_value === 'completed';
            });

            if ($completionActivity) {
                $activities->push([
                    'type' => 'task_completed',
                    'title' => 'Task Completed',
                    'description' => $this->describeCompletedTask($task),
                    'user' => $completionActivity->user ?? $task->assignedTo ?? $task->creator,
                    'timestamp' => $completionActivity->created_at,
                    'icon' => 'fa-check-circle',
                    'color' => '#10b981',
                    'metadata' => array_merge($taskCompletionMetadata, [
                        'source' => 'task_activity',
                    ]),
                ]);
            } elseif ($task->status === 'completed' && $task->completed_at) {
                $activities->push([
                    'type' => 'task_completed',
                    'title' => 'Task Completed',
                    'description' => $this->describeCompletedTask($task),
                    'user' => $task->assignedTo ?? $task->creator,
                    'timestamp' => $task->completed_at,
                    'icon' => 'fa-check-circle',
                    'color' => '#10b981',
                    'metadata' => array_merge($taskCompletionMetadata, [
                        'source' => 'task_row',
                    ]),
                ]);
            }

            if ($taskIdsFromActivityLog->contains($task->id)) {
                continue;
            }
            $activities->push([
                'type' => 'task_created',
                'title' => 'Calling Task Created',
                'description' => "Calling task created for {$lead->name}" .
                    ($task->assignedTo ? " (Assigned to {$task->assignedTo->name})" : ''),
                'user' => $task->creator,
                'timestamp' => $task->created_at,
                'icon' => 'fa-phone',
                'color' => '#3b82f6', // blue
                'metadata' => [
                    'task_id' => $task->id,
                    'task_type' => $task->type,
                    'assigned_to' => $task->assignedTo ? $task->assignedTo->name : null,
                    'status' => $task->status,
                ],
            ]);
        }

        $telecallerTasks = TelecallerTask::query()->withoutGlobalScope('visible_in_queue')->withTrashed()->where('lead_id', $lead->id)
            ->with(['assignedTo', 'createdBy'])
            ->orderBy('created_at', 'desc')
            ->get();

        foreach ($telecallerTasks as $task) {
            if ($task->status === 'completed' && $task->completed_at) {
                $activities->push([
                    'type' => 'task_completed',
                    'title' => 'Task Completed',
                    'description' => $this->describeCompletedTelecallerTask($task),
                    'user' => $task->assignedTo ?? $task->createdBy,
                    'timestamp' => $task->completed_at,
                    'icon' => 'fa-check-circle',
                    'color' => '#10b981',
                    'metadata' => [
                        'task_id' => $task->id,
                        'task_type' => $task->task_type,
                        'assigned_to' => $task->assignedTo ? $task->assignedTo->name : null,
                        'status' => $task->status,
                        'source' => 'telecaller_task',
                    ],
                ]);
            }

            if ($telecallerTaskIdsFromActivityLog->contains($task->id)) {
                continue;
            }
            $activities->push([
                'type' => 'task_created',
                'title' => 'Calling Task Created',
                'description' => "Calling task created for {$lead->name}" .
                    ($task->assignedTo ? " (Assigned to {$task->assignedTo->name})" : ''),
                'user' => $task->createdBy ?? $task->assignedTo,
                'timestamp' => $task->created_at,
                'icon' => 'fa-phone',
                'color' => '#3b82f6', // blue
                'metadata' => [
                    'task_id' => $task->id,
                    'task_type' => $task->task_type,
                    'assigned_to' => $task->assignedTo ? $task->assignedTo->name : null,
                    'status' => $task->status,
                ],
            ]);
        }

        // 11. Status Changes (from lead history or activity logs)
        // This is already covered in ActivityLog, but we can add explicit status change tracking
        if ($lead->marked_dead_at) {
            $activities->push([
                'type' => 'status_changed',
                'title' => 'Lead Marked as Dead',
                'description' => "Lead marked as dead. Reason: {$lead->dead_reason}",
                'user' => $lead->markedDeadBy,
                'timestamp' => $lead->marked_dead_at,
                'icon' => 'fa-times-circle',
                'color' => '#ef4444',
                'metadata' => [
                    'status' => 'dead',
                    'reason' => $lead->dead_reason,
                    'stage' => $lead->dead_at_stage,
                ],
            ]);
        }

        // Sort by timestamp (newest first)
        $activities = $activities->sortByDesc('timestamp')->values();

        return $this->filterActivitiesForViewer($activities, $viewer);
    }

    private function filterActivitiesForViewer(Collection $activities, ?User $viewer): Collection
    {
        if ($this->viewerCanSeeSensitiveLeadHistory($viewer)) {
            return $activities->values();
        }

        return $activities
            ->filter(fn (array $activity) => !$this->isSensitiveActivity($activity))
            ->values();
    }

    private function viewerCanSeeSensitiveLeadHistory(?User $viewer): bool
    {
        return $viewer && ($viewer->isAdmin() || $viewer->isCrm());
    }

    private function viewerCanAccessCallRecording(?User $viewer, Lead $lead): bool
    {
        if (!$viewer) {
            return false;
        }

        if ($viewer->isAdmin() || $viewer->isCrm()) {
            return true;
        }

        return $lead->activeAssignments()
            ->where('assigned_to', $viewer->id)
            ->exists();
    }

    private function isSensitiveActivity(array $activity): bool
    {
        $description = strtolower((string) ($activity['description'] ?? ''));
        $metadata = $activity['metadata'] ?? [];
        $action = $metadata['action'] ?? null;

        $statuses = array_filter([
            strtolower((string) ($metadata['status'] ?? '')),
            strtolower((string) ($metadata['old_values']['status'] ?? '')),
            strtolower((string) ($metadata['new_values']['status'] ?? '')),
        ]);

        foreach ($statuses as $status) {
            if (in_array($status, ['junk', 'not_interested'], true)) {
                return true;
            }
        }

        if (in_array($action, ['task_deleted'], true)) {
            return true;
        }

        if ($action === 'updated' && $this->containsSensitiveHistoryText($description)) {
            return true;
        }

        return in_array(($metadata['new_values']['cleanup_reason'] ?? null), ['lead_transferred', 'manual_cleanup'], true);
    }

    private function containsSensitiveHistoryText(string $text): bool
    {
        if ($text === '') {
            return false;
        }

        foreach ([
            'junk',
            'not interested',
            'other leads',
            'active queue',
            'reassigned',
        ] as $needle) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function detectAutomationMetadata(ActivityLog $log): ?array
    {
        if ($log->action !== 'lead_transferred') {
            return null;
        }

        $newValues = is_array($log->new_values) ? $log->new_values : [];
        $oldValues = is_array($log->old_values) ? $log->old_values : [];
        $looksLikeCnpTransfer =
            ($newValues['status'] ?? null) === Lead::STATUS_FRESH_TRANSFER
            && str_contains(strtolower((string) ($newValues['transfer_note'] ?? '')), 'auto-transferred')
            && str_contains(strtolower((string) ($newValues['transfer_note'] ?? '')), 'cnp');

        if (!$looksLikeCnpTransfer) {
            return null;
        }

        return [
            'type' => $newValues['automation_type'] ?? 'asm_fresh_lead_cnp',
            'label' => $newValues['automation_label'] ?? 'ASM Fresh Lead CNP Automation',
            'details' => $newValues['automation_details'] ?? [
                'mode' => 'auto_transfer',
                'trigger' => 'Fresh lead reached configured CNP limit',
                'from_user_id' => $oldValues['assigned_to'] ?? null,
                'to_user_id' => $newValues['assigned_to'] ?? null,
                'note' => $newValues['transfer_note'] ?? null,
            ],
        ];
    }

    private function getActivityType(string $action): string
    {
        return match($action) {
            'created' => 'created',
            'updated' => 'updated',
            'deleted' => 'deleted',
            'assigned' => 'assigned',
            'lead_reenquiry' => 'lead_reenquiry',
            'task_created' => 'task_created',
            'task_deleted' => 'task_deleted',
            'lead_transferred' => 'lead_transferred',
            'fresh_transfer_acknowledged' => 'fresh_transfer_acknowledged',
            default => 'activity',
        };
    }

    private function getActivityTitle($log): string
    {
        if ($log->action === 'lead_reopened') return 'Lead Reopened as New';
        if ($log->action === 'task_created') {
            return 'Calling Task Created';
        }

        if ($log->action === 'task_deleted') {
            return in_array(($log->new_values['cleanup_reason'] ?? null), ['lead_transferred', 'manual_cleanup'], true)
                ? 'Task Removed'
                : 'Task Deleted';
        }

        if ($log->action === 'lead_reenquiry') {
            return 'Lead Re-enquiry';
        }

        if ($log->action === 'lead_transferred') {
            return 'Lead Transferred';
        }

        if ($log->action === 'fresh_transfer_acknowledged') {
            return 'Fresh Transfer Acknowledged';
        }
        
        if ($log->old_values && $log->new_values && isset($log->old_values['status']) && isset($log->new_values['status'])) {
            return 'Status Changed';
        }
        
        return ucfirst(str_replace('_', ' ', $log->action));
    }

    private function getActivityDescription($log): string
    {
        if ($log->old_values && $log->new_values) {
            if ($log->action === 'lead_transferred') {
                $fromUser = $log->old_values['from_user_name'] ?? ('user #' . ($log->old_values['assigned_to'] ?? 'Unknown'));
                $toUser = $log->new_values['to_user_name'] ?? ('user #' . ($log->new_values['assigned_to'] ?? 'Unknown'));
                $previousStatus = $log->old_values['status'] ?? 'unknown';
                $reason = trim((string) ($log->new_values['transfer_reason'] ?? $log->new_values['transfer_note'] ?? ''));

                return "Lead transferred from {$fromUser} to {$toUser}. Previous status: {$previousStatus}"
                    . ($reason !== '' ? ". Reason: {$reason}" : '');
            }

            if ($log->action === 'fresh_transfer_acknowledged') {
                $reason = $log->new_values['reason'] ?? 'first_action';
                $newStatus = $log->new_values['status'] ?? 'connected';

                return "Fresh transfer acknowledged via {$reason}. Lead moved to {$newStatus}";
            }

            if (isset($log->old_values['status']) && isset($log->new_values['status'])) {
                return "Status changed from '{$log->old_values['status']}' to '{$log->new_values['status']}'";
            }
            
            $changes = [];
            foreach ($log->new_values as $key => $value) {
                if (isset($log->old_values[$key]) && $log->old_values[$key] != $value) {
                    $changes[] = "{$key}: {$log->old_values[$key]} → {$value}";
                }
            }
            
            return !empty($changes) ? implode(', ', $changes) : 'Updated';
        }
        
        return ucfirst($log->action);
    }

    private function getActivityIcon(string $action): string
    {
        return match($action) {
            'created' => 'fa-plus-circle',
            'updated' => 'fa-edit',
            'deleted' => 'fa-trash',
            'assigned' => 'fa-user-plus',
            'lead_reenquiry' => 'fa-rotate-right',
            'task_created' => 'fa-phone',
            'task_deleted' => 'fa-trash',
            'lead_transferred' => 'fa-right-left',
            'fresh_transfer_acknowledged' => 'fa-phone-volume',
            default => 'fa-info-circle',
        };
    }

    private function getActivityColor(string $action): string
    {
        return match($action) {
            'created' => '#10b981',
            'updated' => '#3b82f6',
            'deleted' => '#ef4444',
            'assigned' => '#8b5cf6',
            'lead_reenquiry' => '#d97706',
            'task_created' => '#3b82f6',
            'task_deleted' => '#ef4444',
            'lead_transferred' => '#0f766e',
            'fresh_transfer_acknowledged' => '#2563eb',
            default => '#6b7280',
        };
    }

    private function describeCompletedTask(Task $task): string
    {
        $label = $task->title ?: 'Task';
        $assignee = $task->assignedTo?->name;

        return $assignee
            ? "{$label} completed by {$assignee}"
            : "{$label} marked as completed";
    }

    private function describeCompletedTelecallerTask(TelecallerTask $task): string
    {
        $label = $task->task_type
            ? ucfirst(str_replace('_', ' ', $task->task_type)) . ' task'
            : 'Calling task';
        $assignee = $task->assignedTo?->name;

        return $assignee
            ? "{$label} completed by {$assignee}"
            : "{$label} marked as completed";
    }

    private function getSiteVisitIcon(string $status): string
    {
        return match($status) {
            'scheduled' => 'fa-calendar-alt',
            'completed' => 'fa-check-circle',
            'cancelled' => 'fa-times-circle',
            default => 'fa-map-marker-alt',
        };
    }

    private function getSiteVisitColor(string $status): string
    {
        return match($status) {
            'scheduled' => '#3b82f6',
            'completed' => '#10b981',
            'cancelled' => '#ef4444',
            default => '#6b7280',
        };
    }

    private function getFollowUpIcon(string $status): string
    {
        return match($status) {
            'scheduled' => 'fa-calendar-check',
            'completed' => 'fa-check-circle',
            'missed' => 'fa-exclamation-circle',
            'cancelled' => 'fa-times-circle',
            default => 'fa-clock',
        };
    }

    private function getFollowUpColor(string $status): string
    {
        return match($status) {
            'scheduled' => '#3b82f6',
            'completed' => '#10b981',
            'missed' => '#f59e0b',
            'cancelled' => '#ef4444',
            default => '#6b7280',
        };
    }

    private function getMeetingIcon(string $status): string
    {
        return match($status) {
            'scheduled' => 'fa-calendar-alt',
            'completed' => 'fa-check-circle',
            'cancelled' => 'fa-times-circle',
            default => 'fa-handshake',
        };
    }

    private function getMeetingColor(string $status): string
    {
        return match($status) {
            'scheduled' => '#3b82f6',
            'completed' => '#10b981',
            'cancelled' => '#ef4444',
            default => '#6b7280',
        };
    }

    private function submittedFormFields(object $record, array $labels): array
    {
        $fields = [];

        foreach ($labels as $key => $label) {
            $value = $record->{$key} ?? null;
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            if ($value instanceof Carbon) {
                $value = $value->format('d M Y, h:i A');
            } elseif (is_array($value)) {
                $value = implode(', ', array_filter(array_map(function ($item) {
                    return is_scalar($item) ? (string) $item : json_encode($item);
                }, $value)));
            } elseif (is_bool($value)) {
                $value = $value ? 'Yes' : 'No';
            }

            if (filled($value)) {
                $fields[] = ['label' => $label, 'value' => (string) $value];
            }
        }

        return $fields;
    }

    private function submittedFormFiles(object $record, array $labels): array
    {
        $files = [];

        foreach ($labels as $key => $label) {
            foreach ((array) ($record->{$key} ?? []) as $path) {
                if (!is_string($path) || trim($path) === '') {
                    continue;
                }

                $files[] = [
                    'label' => $label,
                    'name' => basename(parse_url($path, PHP_URL_PATH) ?: $path),
                    'url' => filter_var($path, FILTER_VALIDATE_URL) ? $path : asset('storage/' . ltrim($path, '/')),
                ];
            }
        }

        return $files;
    }

    private function formatDuration(int $seconds): string
    {
        $minutes = floor($seconds / 60);
        $secs = $seconds % 60;
        
        if ($minutes > 0) {
            return "{$minutes}m {$secs}s";
        }
        
        return "{$secs}s";
    }
}

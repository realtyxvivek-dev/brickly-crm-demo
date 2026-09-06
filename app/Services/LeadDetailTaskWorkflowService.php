<?php

namespace App\Services;

use App\Events\SiteVisitCreated;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\Meeting;
use App\Models\Prospect;
use App\Models\SiteVisit;
use App\Models\Task;
use App\Models\User;
use App\Services\MetaReviewAutoStageService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class LeadDetailTaskWorkflowService
{
    public function __construct(
        private readonly LeadOutcomeService $leadOutcomeService,
        private readonly MeetingService $meetingService,
        private readonly LeadSingleOpenTaskService $leadSingleOpenTaskService,
        private readonly SiteVisitRescheduleService $siteVisitRescheduleService,
        private readonly SiteVisitTaskSyncService $siteVisitTaskSyncService
    ) {
    }

    public function completeMeeting(Task $task, Meeting $meeting, Lead $lead, User $user, array $payload): array
    {
        return DB::transaction(function () use ($task, $meeting, $lead, $user, $payload) {
            $this->completeMeetingRecord($meeting, $payload);

            return $this->handleCompletionOutcome(
                workflow: 'meeting',
                task: $task,
                lead: $lead,
                user: $user,
                payload: $payload
            );
        });
    }

    public function completeVisit(Task $task, SiteVisit $siteVisit, Lead $lead, User $user, array $payload): array
    {
        return DB::transaction(function () use ($task, $siteVisit, $lead, $user, $payload) {
            $outcome = (string) ($payload['outcome'] ?? '');

            if (!in_array($outcome, ['visited', 'customer_not_available', 'cancelled', 'follow_up_needed'], true)) {
                throw new \InvalidArgumentException('A valid visit outcome is required.');
            }

            if ($outcome === 'visited' && empty($payload['proof_photos'] ?? [])) {
                throw new \InvalidArgumentException('At least one proof photo is required to complete a site visit.');
            }

            if ($outcome === 'visited') {
                $this->completeVisitRecord($siteVisit, $payload);
            }

            return $this->handleCompletionOutcome(
                workflow: 'visit',
                task: $task,
                lead: $lead,
                user: $user,
                payload: $payload,
                siteVisit: $siteVisit
            );
        });
    }

    public function rescheduleMeeting(Task $task, Meeting $meeting, User $user, Carbon $scheduledAt, string $reason): array
    {
        return DB::transaction(function () use ($task, $meeting, $user, $scheduledAt, $reason) {
            $newMeeting = $this->meetingService->cancelAndReschedule($meeting, [
                'scheduled_at' => $scheduledAt->toISOString(),
                'meeting_notes' => $reason,
            ], $user);

            $task->markAsCompleted();
            $task->update([
                'outcome' => 'meeting_rescheduled',
                'outcome_remark' => $reason,
                'outcome_recorded_at' => now(),
                'next_action_at' => $scheduledAt,
            ]);
            $this->resetCnpStage($task, 'Meeting rescheduled.');

            return [
                'message' => 'Meeting rescheduled successfully.',
                'meeting_id' => $newMeeting->id,
            ];
        });
    }

    public function rescheduleVisit(Task $task, SiteVisit $siteVisit, User $user, Carbon $scheduledAt, string $reason): array
    {
        return DB::transaction(function () use ($task, $siteVisit, $user, $scheduledAt, $reason) {
            $newSiteVisit = $this->siteVisitRescheduleService->reschedule($siteVisit, $scheduledAt, $reason, $user);
            $task->refresh();
            $this->resetCnpStage($task, 'Site visit rescheduled.');

            return [
                'message' => 'Site visit rescheduled successfully.',
                'site_visit_id' => $newSiteVisit->id,
            ];
        });
    }

    public function sendMeetingToCloser(Task $task, Meeting $meeting, Lead $lead, User $user, array $payload): array
    {
        return DB::transaction(function () use ($task, $meeting, $lead, $user, $payload) {
            $existingCloserVisit = SiteVisit::query()
                ->where('lead_id', $lead->id)
                ->where('status', 'completed')
                ->where('verification_status', 'verified')
                ->whereIn('closer_status', ['draft', 'pending_crm', 'approved'])
                ->where(function ($query) {
                    $query->whereNull('is_dead')->orWhere('is_dead', false);
                })
                ->latest('updated_at')
                ->first();

            if ($existingCloserVisit) {
                $this->completeMeetingRecordForCloser($meeting, $payload, $user);
                $this->completeTaskForCloser($task, $existingCloserVisit, $payload['remark'] ?? null);

                return [
                    'message' => 'Lead is already in closer pipeline.',
                    'site_visit_id' => $existingCloserVisit->id,
                    'closer_status' => $existingCloserVisit->closer_status,
                    'duplicate' => true,
                ];
            }

            $this->completeMeetingRecordForCloser($meeting, $payload, $user);

            $now = now();
            $remark = trim((string) ($payload['remark'] ?? ''));
            $project = trim((string) ($payload['project'] ?? ''));
            $budgetRange = trim((string) ($payload['budget_range'] ?? ''));
            $proofPhotos = $this->storeProofPhotos($payload['proof_photos'] ?? [], 'site-visits/closer');
            $meetingSummary = 'Sent to closer after meeting';
            if ($remark !== '') {
                $meetingSummary .= ': ' . $remark;
            }

            $siteVisit = SiteVisit::create([
                'lead_id' => $lead->id,
                'created_by' => $user->id,
                'assigned_to' => $meeting->assigned_to ?: ($task->assigned_to ?: $user->id),
                'property_name' => $project ?: null,
                'property_address' => $meeting->location ?? null,
                'scheduled_at' => $now,
                'completed_at' => $now,
                'status' => 'completed',
                'verification_status' => 'verified',
                'verified_by' => $user->id,
                'verified_at' => $now,
                'closer_status' => 'draft',
                'converted_to_closer_at' => $now,
                'visit_notes' => $meetingSummary,
                'customer_name' => $lead->name,
                'phone' => $lead->phone,
                'date_of_visit' => $now->toDateString(),
                'project' => $project ?: null,
                'budget_range' => $budgetRange ?: null,
                'lead_type' => 'Meeting',
                'completion_proof_photos' => $proofPhotos,
                'closer_request_proof_photos' => $proofPhotos,
            ]);

            $this->completeTaskForCloser($task, $siteVisit, $remark);
            app(LeadActiveWorkflowService::class)->moveLeadToWorkflow($lead->id, 'closer', $siteVisit->id);
            $lead->updateStatusIfAllowed('meeting_completed');

            return [
                'message' => 'Lead sent to closer successfully.',
                'site_visit_id' => $siteVisit->id,
                'closer_status' => 'draft',
            ];
        });
    }

    private function completeMeetingRecord(Meeting $meeting, array $payload): void
    {
        $meeting->fill([
            'feedback' => $payload['feedback'] ?? null,
            'rating' => $payload['rating'] ?? null,
            'meeting_notes' => $payload['notes'] ?? null,
            'completion_proof_photos' => $this->storeProofPhotos($payload['proof_photos'] ?? [], 'meetings/proof'),
            'status' => 'completed',
            'completed_at' => now(),
            'verification_status' => 'pending',
        ]);
        $meeting->save();

        if ($meeting->lead) {
            $meeting->lead->updateStatusIfAllowed('meeting_completed');
        }
    }

    private function completeMeetingRecordForCloser(Meeting $meeting, array $payload, User $user): void
    {
        $proofPhotos = $this->storeProofPhotos($payload['proof_photos'] ?? [], 'meetings/proof');
        $existingProofPhotos = (array) ($meeting->completion_proof_photos ?? []);

        $meeting->fill([
            'feedback' => $payload['feedback'] ?? $meeting->feedback,
            'meeting_notes' => $this->appendNote($meeting->meeting_notes, 'Sent to closer after meeting' . (trim((string) ($payload['remark'] ?? '')) !== '' ? ': ' . trim((string) $payload['remark']) : '')),
            'completion_proof_photos' => array_values(array_merge($existingProofPhotos, $proofPhotos)),
            'status' => 'completed',
            'completed_at' => $meeting->completed_at ?: now(),
            'verification_status' => 'verified',
            'verified_by' => $meeting->verified_by ?: $user->id,
            'verified_at' => $meeting->verified_at ?: now(),
        ]);
        $meeting->save();

        if ($meeting->lead) {
            $meeting->lead->updateStatusIfAllowed('meeting_completed');
        }
    }

    private function completeTaskForCloser(Task $task, SiteVisit $siteVisit, ?string $remark = null): void
    {
        $task->markAsCompleted();
        $task->update([
            'outcome' => 'send_to_closer',
            'outcome_remark' => $remark,
            'outcome_recorded_at' => now(),
            'next_action_at' => null,
        ]);
        $this->resetCnpStage($task, 'Lead sent to closer.');

        if (Task::supportsColumn('site_visit_id')) {
            $task->update(['site_visit_id' => $siteVisit->id]);
        }

        $this->leadSingleOpenTaskService->closeOpenTasksForLead($siteVisit->lead_id, [
            'task' => [$task->id],
        ]);
    }

    private function completeVisitRecord(SiteVisit $siteVisit, array $payload): void
    {
        $siteVisit->fill([
            'feedback' => $payload['feedback'] ?? null,
            'rating' => $payload['rating'] ?? null,
            'visit_notes' => $payload['notes'] ?? null,
            'visited_projects' => $payload['visited_projects'] ?? $siteVisit->visited_projects,
            'visited_property_types' => $payload['visited_property_types'] ?? $siteVisit->visited_property_types,
            'tentative_closing_time' => $payload['tentative_closing_time'] ?? $siteVisit->tentative_closing_time,
            'completion_proof_photos' => $this->storeProofPhotos($payload['proof_photos'] ?? [], 'site-visits/proof'),
            'status' => 'completed',
            'completed_at' => now(),
            'verification_status' => 'pending',
        ]);
        $siteVisit->save();

        if ($siteVisit->lead) {
            $leadType = $siteVisit->lead_type ?? null;
            $siteVisit->lead->updateStatusIfAllowed($leadType === 'Revisited' ? 'revisited_completed' : 'visit_done');
        }
    }

    private function handleCompletionOutcome(string $workflow, Task $task, Lead $lead, User $user, array $payload, ?SiteVisit $siteVisit = null): array
    {
        $outcome = (string) ($payload['outcome'] ?? '');

        if ($workflow === 'visit') {
            if ($outcome === 'visited') {
                $task->markAsCompleted();
                $task->update([
                    'outcome' => 'visited',
                    'outcome_remark' => $payload['remark'] ?? null,
                    'outcome_recorded_at' => now(),
                ]);
                $this->resetCnpStage($task, 'Site visit completed.');

                return [
                    'message' => 'Site visit completed successfully.',
                    'next_step' => 'reload',
                ];
            }

            if ($outcome === 'customer_not_available') {
                $remark = trim((string) ($payload['remark'] ?? ''));
                if ($remark === '') {
                    throw new \InvalidArgumentException('Remark is required for customer not available.');
                }

                if ($siteVisit) {
                    $siteVisit->update([
                        'visit_notes' => $this->appendNote($siteVisit->visit_notes, 'Customer not available: ' . $remark),
                    ]);
                }

                $task->update([
                    'outcome' => 'customer_not_available',
                    'outcome_remark' => $remark,
                    'outcome_recorded_at' => now(),
                ]);
                $this->resetCnpStage($task, 'Customer not available captured.');

                return [
                    'message' => 'Customer not available remark saved.',
                    'next_step' => 'reload',
                    'keep_task_open' => true,
                ];
            }

            if ($outcome === 'cancelled') {
                $remark = trim((string) ($payload['remark'] ?? ''));
                if ($remark === '') {
                    throw new \InvalidArgumentException('Reason is required to cancel the site visit.');
                }

                if ($siteVisit) {
                    $siteVisit->forceFill([
                        'status' => 'cancelled',
                        'visit_notes' => $this->appendNote($siteVisit->visit_notes, 'Cancelled: ' . $remark),
                        'queue_hidden_at' => SiteVisit::supportsQueueArchiving() ? now() : $siteVisit->queue_hidden_at,
                        'queue_hidden_reason' => SiteVisit::supportsQueueArchiving() ? 'site_visit_cancelled' : $siteVisit->queue_hidden_reason,
                    ])->save();
                }

                $task->update([
                    'status' => 'cancelled',
                    'completed_at' => now(),
                    'outcome' => 'cancelled',
                    'outcome_remark' => $remark,
                    'outcome_recorded_at' => now(),
                ]);

                if (Task::supportsQueueArchiving()) {
                    $task->archiveForQueue('site_visit_cancelled');
                }
                $this->resetCnpStage($task, 'Site visit cancelled.');

                return [
                    'message' => 'Site visit cancelled successfully.',
                    'next_step' => 'reload',
                ];
            }

            if ($outcome === 'follow_up_needed') {
                $remark = trim((string) ($payload['remark'] ?? ''));
                if ($remark === '') {
                    throw new \InvalidArgumentException('Remark is required to schedule a follow-up.');
                }

                if (empty($payload['scheduled_at'])) {
                    throw new \InvalidArgumentException('Follow-up date and time are required.');
                }

                $nextAt = Carbon::parse($payload['scheduled_at']);
                $followUp = FollowUp::create([
                    'lead_id' => $lead->id,
                    'created_by' => $user->id,
                    'type' => 'call',
                    'notes' => $remark,
                    'scheduled_at' => $nextAt,
                    'status' => 'scheduled',
                ]);
                app(LeadActiveWorkflowService::class)->moveLeadToWorkflow($lead->id, 'follow_up', $followUp->id);

                $task->markAsCompleted();
                $task->update([
                    'outcome' => 'follow_up_needed',
                    'outcome_remark' => $remark,
                    'outcome_recorded_at' => now(),
                    'next_action_at' => $nextAt,
                ]);
                $this->resetCnpStage($task, 'Lead moved to follow-up flow.');

                $followUpTaskPayload = [
                    'lead_id' => $lead->id,
                    'assigned_to' => $user->id,
                    'type' => 'phone_call',
                    'title' => "Follow-up call: {$lead->name}",
                    'description' => "Follow-up call task scheduled for {$nextAt->format('Y-m-d H:i')}.",
                    'status' => 'pending',
                    'scheduled_at' => $nextAt,
                    'created_by' => $user->id,
                    'notes' => $remark,
                ];

                if (Task::supportsColumn('follow_up_id')) {
                    $followUpTaskPayload['follow_up_id'] = $followUp->id;
                }

                Task::create($followUpTaskPayload);

                if ($siteVisit) {
                    $siteVisit->update([
                        'visit_notes' => $this->appendNote($siteVisit->visit_notes, 'Follow-up needed: ' . $remark),
                    ]);
                }

                $lead->update(['next_followup_at' => $nextAt]);

                return [
                    'message' => 'Follow-up task created successfully.',
                    'next_step' => 'reload',
                ];
            }
        }

        if ($outcome === 'interested') {
            $task->markAsCompleted();
            $task->update([
                'outcome' => 'interested',
                'outcome_recorded_at' => now(),
            ]);
            $this->resetCnpStage($task, ucfirst($workflow) . ' moved to interested flow.');

            return [
                'message' => ucfirst($workflow) . ' completed successfully.',
                'next_step' => 'interested_form',
            ];
        }

        if (in_array($outcome, ['junk', 'not_interested'], true)) {
            [$lead, $prospect] = $this->getOrCreateLeadAndProspect($lead, $user);
            $this->leadOutcomeService->markAsOtherLead($task, $lead, $prospect, $user, $outcome, $payload['remark'] ?? null);

            return [
                'message' => $outcome === 'junk'
                    ? ucfirst($workflow) . ' completed and lead marked as junk.'
                    : ucfirst($workflow) . ' completed and lead marked as not interested.',
            ];
        }

        if ($outcome === 'schedule_follow_up') {
            $nextAt = Carbon::parse($payload['scheduled_at']);
            $followUpNote = trim((string) ($payload['remark'] ?? ''));
            if ($followUpNote === '') {
                $followUpNote = "Follow-up scheduled for {$nextAt->format('Y-m-d H:i')}";
            }

            $followUp = FollowUp::create([
                'lead_id' => $lead->id,
                'created_by' => $user->id,
                'type' => 'call',
                'notes' => $followUpNote,
                'scheduled_at' => $nextAt,
                'status' => 'scheduled',
            ]);
            app(LeadActiveWorkflowService::class)->moveLeadToWorkflow($lead->id, 'follow_up', $followUp->id);

            $task->markAsCompleted();
            $task->update([
                'outcome' => 'follow_up',
                'outcome_remark' => $payload['remark'] ?? null,
                'outcome_recorded_at' => now(),
                'next_action_at' => $nextAt,
            ]);
            $this->resetCnpStage($task, 'Lead moved to follow-up flow.');

            $this->leadSingleOpenTaskService->closeOpenTasksForLead($lead->id, [
                'task' => [$task->id],
            ]);

            $followUpTaskPayload = [
                'lead_id' => $lead->id,
                'assigned_to' => $user->id,
                'type' => 'phone_call',
                'title' => "Follow-up call: {$lead->name}",
                'description' => "Follow-up call task scheduled for {$nextAt->format('Y-m-d H:i')}.",
                'status' => 'pending',
                'scheduled_at' => $nextAt,
                'created_by' => $user->id,
                'notes' => $followUpNote,
            ];

            if (Task::supportsColumn('follow_up_id')) {
                $followUpTaskPayload['follow_up_id'] = $followUp->id;
            }

            Task::create($followUpTaskPayload);

            $lead->update(['next_followup_at' => $nextAt]);
            if ($lead->status === Lead::STATUS_FRESH_TRANSFER) {
                $lead->acknowledgeFreshTransfer($user->id, 'follow_up_created', 'connected');
            }

            return [
                'message' => ucfirst($workflow) . ' completed and follow-up scheduled successfully.',
                'next_step' => 'reload',
            ];
        }

        if ($workflow === 'meeting' && $outcome === 'schedule_visit') {
            $scheduledAt = Carbon::parse($payload['scheduled_at']);

            $task->markAsCompleted();
            $task->update([
                'outcome' => 'schedule_visit',
                'outcome_remark' => $payload['remark'] ?? null,
                'outcome_recorded_at' => now(),
                'next_action_at' => $scheduledAt,
            ]);
            $this->resetCnpStage($task, 'Lead moved to site visit flow.');

            $this->leadSingleOpenTaskService->closeOpenTasksForLead($lead->id, [
                'task' => [$task->id],
            ]);

              $siteVisit = SiteVisit::create([
                  'lead_id' => $lead->id,
                  'created_by' => $user->id,
                  'assigned_to' => $user->id,
                  'property_name' => $payload['project'] ?? null,
                'property_address' => $payload['location'] ?? null,
                'scheduled_at' => $scheduledAt,
                'status' => 'scheduled',
                'verification_status' => 'pending',
                'visit_notes' => $payload['remark'] ?? null,
                'customer_name' => $lead->name,
                'phone' => $lead->phone,
                'project' => $payload['project'] ?? null,
                  'lead_type' => 'Meeting',
                  'reminder_enabled' => (bool) ($payload['reminder_enabled'] ?? false),
              ]);
              app(LeadActiveWorkflowService::class)->moveLeadToWorkflow($lead->id, 'site_visit', $siteVisit->id);

              event(new SiteVisitCreated($siteVisit));
              $lead->updateStatusIfAllowed('visit_scheduled');
              $this->siteVisitTaskSyncService->syncReminderTask($siteVisit, $user, [
                  'notes_prefix' => 'Created from completed meeting task #' . $task->id,
              ]);
            app(MetaReviewAutoStageService::class)->applyVisitScheduled($lead);

            return [
                'message' => 'Meeting completed and site visit scheduled successfully.',
                'next_step' => 'reload',
            ];
        }

        if ($workflow === 'visit' && $outcome === 'schedule_meeting') {
            $scheduledAt = Carbon::parse($payload['scheduled_at']);

            $task->markAsCompleted();
            $task->update([
                'outcome' => 'schedule_meeting',
                'outcome_remark' => $payload['remark'] ?? null,
                'outcome_recorded_at' => now(),
                'next_action_at' => $scheduledAt,
            ]);
            $this->resetCnpStage($task, 'Lead moved to meeting flow.');

            $this->leadSingleOpenTaskService->closeOpenTasksForLead($lead->id, [
                'task' => [$task->id],
            ]);

            $meeting = $this->meetingService->createMeetingWithReminder([
                'lead_id' => $lead->id,
                'scheduled_at' => $scheduledAt->toISOString(),
                'meeting_mode' => $payload['meeting_mode'] ?? 'online',
                'meeting_link' => $payload['meeting_link'] ?? null,
                'location' => $payload['location'] ?? null,
                'meeting_notes' => $payload['remark'] ?? null,
                'meeting_sequence' => $this->meetingService->getLeadMeetingSequence($lead->id),
                'reminder_enabled' => (bool) ($payload['reminder_enabled'] ?? false),
                'reminder_minutes' => 5,
            ], $user);

            if (Task::supportsColumn('meeting_id')) {
                $task->update([
                    'meeting_id' => $meeting->id,
                ]);
            }

            return [
                'message' => 'Visit completed and meeting scheduled successfully.',
                'next_step' => 'reload',
            ];
        }

        throw new \InvalidArgumentException('Unsupported workflow outcome.');
    }

    private function getOrCreateLeadAndProspect(Lead $lead, User $user): array
    {
        $prospect = $lead->prospects()->latest()->first();

        if (!$prospect) {
            $prospect = Prospect::create([
                'lead_id' => $lead->id,
                'customer_name' => $lead->name,
                'phone' => $lead->phone,
                'manager_id' => $user->id,
                'assigned_manager' => $user->id,
                'created_by' => $user->id,
                'verification_status' => 'verified',
                'verified_at' => now(),
                'verified_by' => $user->id,
            ]);
        }

        return [$lead, $prospect];
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @return array<int, string>
     */
    private function storeProofPhotos(array $files, string $directory): array
    {
        $paths = [];

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            $filename = $directory . '/' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->storeAs('public', $filename);
            $paths[] = $filename;
        }

        return $paths;
    }

    private function appendNote(?string $existing, string $entry): string
    {
        $existing = trim((string) $existing);

        return $existing !== '' ? $existing . "\n\n" . $entry : $entry;
    }

    private function resetCnpStage(Task $task, string $reason): void
    {
        app(AsmCnpAutomationService::class)->cancelTaskStageAutomation($task, $reason);
    }
}

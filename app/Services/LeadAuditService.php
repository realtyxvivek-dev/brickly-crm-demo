<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\FbLead;
use App\Models\FbWebhookEvent;
use App\Models\ImportedLead;
use App\Models\ImportBatch;
use App\Models\IvrLeadAutomationAudit;
use App\Models\Lead;
use App\Models\McubeOutboundAttempt;
use App\Models\McubeWebhookLog;
use App\Models\MetaReviewSyncLog;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TelecallerTask;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class LeadAuditService
{
    public function __construct(
        private readonly LeadActivityService $leadActivityService
    ) {
    }

    public function searchCandidates(string $search): Collection
    {
        $term = trim($search);
        if ($term === '') {
            return collect();
        }

        $digits = preg_replace('/\D+/', '', $term);
        $candidates = collect();

        if (ctype_digit($term)) {
            $leadById = Lead::with(['activeAssignments.assignedTo'])->find((int) $term);
            if ($leadById) {
                $candidates->push($this->makeLeadCandidate($leadById, 'Lead ID match'));
            }
        }

        if ($digits !== '') {
            $phoneNeedle = strlen($digits) > 10 ? substr($digits, -10) : $digits;

            Lead::with(['activeAssignments.assignedTo'])
                ->where('phone', 'like', '%' . $phoneNeedle . '%')
                ->latest('created_at')
                ->limit(10)
                ->get()
                ->each(fn (Lead $lead) => $candidates->push($this->makeLeadCandidate($lead, 'Phone match')));

            McubeWebhookLog::with(['lead', 'agent'])
                ->where(function ($query) use ($term, $phoneNeedle) {
                    $query->where('callid', 'like', '%' . $term . '%')
                        ->orWhere('callto', 'like', '%' . $phoneNeedle . '%')
                        ->orWhere('emp_phone', 'like', '%' . $phoneNeedle . '%');
                })
                ->latest('created_at')
                ->limit(10)
                ->get()
                ->each(function (McubeWebhookLog $log) use ($candidates) {
                    $leadName = $log->lead?->name ?: 'Unlinked IVR event';
                    $subtitle = $log->lead
                        ? 'IVR log linked to lead #' . $log->lead->id
                        : 'IVR webhook without linked lead';

                    $candidates->push([
                        'type' => 'mcube_webhook_log',
                        'id' => $log->id,
                        'title' => $leadName,
                        'subtitle' => $subtitle,
                        'badge' => 'IVR',
                    ]);
                });
        }

        FbLead::with(['crmLead.activeAssignments.assignedTo', 'form'])
            ->where('leadgen_id', 'like', '%' . $term . '%')
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->each(function (FbLead $fbLead) use ($candidates) {
                $title = $fbLead->crmLead?->name ?: 'Meta leadgen ' . $fbLead->leadgen_id;
                $subtitle = $fbLead->crmLead
                    ? 'Linked Meta lead for lead #' . $fbLead->crmLead->id
                    : 'Meta leadgen row without CRM lead';

                $candidates->push([
                    'type' => 'fb_lead',
                    'id' => $fbLead->id,
                    'title' => $title,
                    'subtitle' => $subtitle,
                    'badge' => 'Meta',
                ]);
            });

        FbWebhookEvent::query()
            ->where('leadgen_id', 'like', '%' . $term . '%')
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->each(function (FbWebhookEvent $event) use ($candidates) {
                $candidates->push([
                    'type' => 'fb_webhook_event',
                    'id' => $event->id,
                    'title' => 'Meta webhook ' . ($event->leadgen_id ?: ('event #' . $event->id)),
                    'subtitle' => 'Webhook status: ' . ($event->status ?: 'received'),
                    'badge' => 'Webhook',
                ]);
            });

        return $candidates
            ->unique(fn (array $candidate) => $candidate['type'] . ':' . $candidate['id'])
            ->values();
    }

    public function buildAudit(string $contextType, int $contextId): array
    {
        $context = $this->resolveContext($contextType, $contextId);
        $lead = $context['lead'];

        $metaLead = $context['fbLead'];
        $metaWebhookEvents = $this->collectMetaWebhookEvents($lead, $metaLead, $context['fbWebhookEvent']);
        $mcubeLogs = $this->collectMcubeLogs($lead, $context['mcubeWebhookLog']);
        $mcubeOutboundAttempts = $this->collectMcubeOutboundAttempts($lead);
        $ivrAudits = $this->collectIvrAudits($lead, $mcubeLogs);
        $importedLeads = $this->collectImportedLeads($lead);
        $metaSyncLogs = $lead
            ? MetaReviewSyncLog::with('user')->where('lead_id', $lead->id)->latest('synced_at')->latest('id')->get()
            : collect();

        $leadAssignments = $lead
            ? $lead->assignments()->with(['assignedTo', 'assignedBy'])->latest('assigned_at')->latest('id')->get()
            : collect();

        $managerTasks = $lead
            ? $lead->managerTasks()->withoutGlobalScopes()->with(['assignedTo', 'creator', 'meeting', 'siteVisit', 'followUp'])->latest('created_at')->get()
            : collect();

        $telecallerTasks = $lead
            ? $lead->tasks()->withoutGlobalScopes()->with(['assignedTo', 'createdBy', 'meeting', 'siteVisit', 'followUp'])->latest('created_at')->get()
            : collect();

        $activityTimeline = $lead
            ? $this->leadActivityService->getTimeline($lead, auth()->user())
            : collect();

        $relatedActivityLogs = $this->collectRelatedActivityLogs($lead, $managerTasks, $telecallerTasks);
        $taskActivities = $this->collectTaskActivities($managerTasks);

        $timeline = $this->buildTimeline(
            $lead,
            $metaLead,
            $metaWebhookEvents,
            $mcubeLogs,
            $ivrAudits,
            $importedLeads,
            $metaSyncLogs,
            $leadAssignments,
            $managerTasks,
            $telecallerTasks,
            $activityTimeline,
            $relatedActivityLogs,
            $taskActivities
        );

        $diagnosis = $this->buildDiagnosis(
            $lead,
            $metaLead,
            $metaWebhookEvents,
            $mcubeLogs,
            $ivrAudits,
            $importedLeads,
            $metaSyncLogs,
            $leadAssignments,
            $managerTasks,
            $telecallerTasks
        );

        return [
            'context' => $context,
            'lead' => $lead,
            'summary' => $this->buildSummary($lead, $metaLead, $context['fbWebhookEvent'], $context['mcubeWebhookLog']),
            'diagnosis' => $diagnosis,
            'timeline' => $timeline,
            'rawPanels' => $this->buildRawPanels($metaLead, $metaWebhookEvents, $mcubeLogs, $mcubeOutboundAttempts, $ivrAudits, $importedLeads, $metaSyncLogs, $relatedActivityLogs, $taskActivities, $managerTasks, $telecallerTasks),
        ];
    }

    private function resolveContext(string $contextType, int $contextId): array
    {
        $lead = null;
        $fbLead = null;
        $fbWebhookEvent = null;
        $mcubeWebhookLog = null;

        if ($contextType === 'lead') {
            $lead = Lead::with(['creator', 'activeAssignments.assignedTo', 'latestFbLead.form', 'latestImportedLead.importBatch'])->findOrFail($contextId);
            $fbLead = $lead->latestFbLead;
        } elseif ($contextType === 'fb_lead') {
            $fbLead = FbLead::with(['crmLead.creator', 'crmLead.activeAssignments.assignedTo', 'form'])->findOrFail($contextId);
            $lead = $fbLead->crmLead;
        } elseif ($contextType === 'fb_webhook_event') {
            $fbWebhookEvent = FbWebhookEvent::findOrFail($contextId);
            if ($fbWebhookEvent->leadgen_id) {
                $fbLead = FbLead::with(['crmLead.creator', 'crmLead.activeAssignments.assignedTo', 'form'])
                    ->where('leadgen_id', $fbWebhookEvent->leadgen_id)
                    ->latest('id')
                    ->first();
                $lead = $fbLead?->crmLead;
            }
        } elseif ($contextType === 'mcube_webhook_log') {
            $mcubeWebhookLog = McubeWebhookLog::with(['lead.creator', 'lead.activeAssignments.assignedTo', 'agent', 'callLog'])->findOrFail($contextId);
            $lead = $mcubeWebhookLog->lead;
        } else {
            abort(404);
        }

        if ($lead) {
            $lead->loadMissing(['creator', 'activeAssignments.assignedTo', 'assignments.assignedBy', 'assignments.assignedTo', 'latestFbLead.form', 'latestImportedLead.importBatch']);
        }

        return compact('lead', 'fbLead', 'fbWebhookEvent', 'mcubeWebhookLog') + [
            'contextType' => $contextType,
            'contextId' => $contextId,
        ];
    }

    private function collectMetaWebhookEvents(?Lead $lead, ?FbLead $metaLead, ?FbWebhookEvent $seedEvent = null): Collection
    {
        $leadgenIds = collect([
            $seedEvent?->leadgen_id,
            $metaLead?->leadgen_id,
        ])->filter()->unique()->values();

        $events = collect();
        if ($leadgenIds->isNotEmpty()) {
            $events = FbWebhookEvent::whereIn('leadgen_id', $leadgenIds->all())->latest('created_at')->latest('id')->get();
        }

        if ($seedEvent && $events->where('id', $seedEvent->id)->isEmpty()) {
            $events->prepend($seedEvent);
        }

        return $events->unique('id')->values();
    }

    private function collectMcubeLogs(?Lead $lead, ?McubeWebhookLog $seedLog = null): Collection
    {
        $query = McubeWebhookLog::with(['lead', 'agent', 'callLog']);

        if ($lead) {
            $query->where(function ($builder) use ($lead) {
                $builder->where('lead_id', $lead->id);
                if ($lead->phone) {
                    $digits = preg_replace('/\D+/', '', (string) $lead->phone);
                    if ($digits !== '') {
                        $builder->orWhere('callto', 'like', '%' . substr($digits, -10) . '%');
                    }
                }
            });
        } elseif ($seedLog) {
            $query->whereKey($seedLog->id);
        } else {
            return collect();
        }

        $logs = $query->latest('created_at')->latest('id')->get();
        if ($seedLog && $logs->where('id', $seedLog->id)->isEmpty()) {
            $logs->prepend($seedLog);
        }

        return $logs->unique('id')->values();
    }

    private function collectMcubeOutboundAttempts(?Lead $lead): Collection
    {
        if (!$lead) {
            return collect();
        }

        return McubeOutboundAttempt::query()
            ->with(['user', 'lead', 'task'])
            ->where('lead_id', $lead->id)
            ->latest('attempted_at')
            ->latest('id')
            ->get();
    }

    private function collectIvrAudits(?Lead $lead, Collection $mcubeLogs): Collection
    {
        $query = IvrLeadAutomationAudit::with(['receiver', 'assignedUser', 'config', 'receiverOverride', 'webhookLog']);

        if ($lead) {
            $query->where('lead_id', $lead->id);
        }

        $webhookIds = $mcubeLogs->pluck('id')->filter()->values();
        if ($webhookIds->isNotEmpty()) {
            $query->orWhereIn('webhook_log_id', $webhookIds->all());
        }

        return $query->latest('processed_at')->latest('id')->get()->unique('id')->values();
    }

    private function collectImportedLeads(?Lead $lead): Collection
    {
        if (!$lead) {
            return collect();
        }

        return ImportedLead::with(['importBatch.user', 'importBatch.assignmentRule', 'assignedTo'])
            ->where('lead_id', $lead->id)
            ->latest('assigned_at')
            ->latest('id')
            ->get();
    }

    private function buildSummary(?Lead $lead, ?FbLead $metaLead, ?FbWebhookEvent $fbWebhookEvent, ?McubeWebhookLog $mcubeWebhookLog): array
    {
        $assignment = $lead?->activeAssignments?->first();

        return [
            'lead_name' => $lead?->name ?: 'Lead not created',
            'phone' => $lead?->phone ?: ($mcubeWebhookLog?->callto ?: 'N/A'),
            'source' => $lead?->source_label ?: ($metaLead ? 'Meta' : ($mcubeWebhookLog ? 'IVR' : 'Unknown')),
            'owner' => $assignment?->assignedTo?->name ?: 'Unassigned',
            'status' => $lead ? ucfirst(str_replace('_', ' ', (string) $lead->status)) : 'Not Created',
            'created_at' => optional($lead?->created_at)->format('d M Y, h:i A') ?: 'N/A',
            'lead_id' => $lead?->id,
            'external_ids' => array_filter([
                'Meta Leadgen ID' => $metaLead?->leadgen_id ?: $fbWebhookEvent?->leadgen_id,
                'MCube Call ID' => $mcubeWebhookLog?->callid,
                'FB Lead Row ID' => $metaLead?->id,
            ], fn ($value) => filled($value)),
        ];
    }

    private function buildDiagnosis(
        ?Lead $lead,
        ?FbLead $metaLead,
        Collection $metaWebhookEvents,
        Collection $mcubeLogs,
        Collection $ivrAudits,
        Collection $importedLeads,
        Collection $metaSyncLogs,
        Collection $leadAssignments,
        Collection $managerTasks,
        Collection $telecallerTasks
    ): array {
        $latestMetaSync = $metaSyncLogs->first();
        $importBatch = $importedLeads->first()?->importBatch;
        $duplicateDetected = $this->detectDuplicate($lead, $metaWebhookEvents, $importBatch);
        $taskCount = $managerTasks->count() + $telecallerTasks->count();
        $automationMatched = $ivrAudits->isNotEmpty()
            || $importedLeads->contains(fn (ImportedLead $importedLead) => filled($importedLead->importBatch?->automation_id));

        $flags = [
            'lead_created' => (bool) $lead,
            'assignment_created' => $leadAssignments->isNotEmpty(),
            'task_created' => $taskCount > 0,
            'automation_matched' => $automationMatched,
            'import_found' => $importedLeads->isNotEmpty(),
            'meta_sync_found' => $metaSyncLogs->isNotEmpty(),
            'duplicate_detected' => $duplicateDetected,
        ];

        $summary = 'No evidence found yet.';
        if (!$lead && $metaWebhookEvents->isNotEmpty()) {
            $summary = 'Meta webhook received but no CRM lead was created from the matched payload.';
        } elseif (!$lead && $mcubeLogs->isNotEmpty()) {
            $summary = 'IVR webhook/log exists but no CRM lead is linked to the matched call event.';
        } elseif ($duplicateDetected) {
            $summary = 'A duplicate or guarded lead condition is likely blocking creation or follow-up processing.';
        } elseif ($importBatch && in_array($importBatch->status, ['failed', 'partial', 'processing'], true)) {
            $summary = 'Lead import was found, but the batch did not complete cleanly and needs review.';
        } elseif ($lead && $leadAssignments->isEmpty()) {
            $summary = 'Lead exists in CRM, but no assignment record was found for this lead.';
        } elseif ($lead && $leadAssignments->isNotEmpty() && $taskCount === 0) {
            $summary = 'Lead and owner are present, but no calling/follow-up task evidence was found.';
        } elseif ($latestMetaSync && in_array(Str::lower((string) $latestMetaSync->status), ['skipped', 'already_synced'], true)) {
            $summary = 'Lead exists and Meta sync logs show the stage sync was skipped or already processed.';
        } elseif ($lead) {
            $summary = 'Lead processing looks complete with CRM creation, ownership, and activity/task evidence.';
        }

        return [
            'flags' => $flags,
            'summary' => $summary,
            'details' => [
                'latest_import_status' => $importBatch?->status,
                'latest_meta_sync_status' => $latestMetaSync?->status,
                'latest_meta_sync_reason' => $latestMetaSync?->reason,
            ],
        ];
    }

    private function buildTimeline(
        ?Lead $lead,
        ?FbLead $metaLead,
        Collection $metaWebhookEvents,
        Collection $mcubeLogs,
        Collection $ivrAudits,
        Collection $importedLeads,
        Collection $metaSyncLogs,
        Collection $leadAssignments,
        Collection $managerTasks,
        Collection $telecallerTasks,
        Collection $activityTimeline,
        Collection $relatedActivityLogs,
        Collection $taskActivities
    ): Collection {
        $timeline = collect();
        $managerTasksById = $managerTasks->keyBy('id');

        foreach ($metaWebhookEvents as $event) {
            $timeline->push([
                'timestamp' => $event->created_at,
                'title' => 'Meta Webhook Received',
                'description' => 'Leadgen ID: ' . ($event->leadgen_id ?: 'N/A'),
                'source' => 'Meta',
                'status' => $event->status ?: 'received',
                'actor' => 'System',
                'entity' => 'fb_webhook_event',
                'reference' => 'Webhook #' . $event->id,
                'error' => $event->error,
            ]);
        }

        if ($metaLead) {
            $timeline->push([
                'timestamp' => $metaLead->created_at,
                'title' => 'Meta Lead Row Stored',
                'description' => 'Meta lead row captured in fb_leads.',
                'source' => 'Meta Lead',
                'status' => $metaLead->crm_lead_id ? 'linked' : 'unlinked',
                'actor' => 'System',
                'entity' => 'fb_lead',
                'reference' => 'Meta Row #' . $metaLead->id,
            ]);
        }

        foreach ($mcubeLogs as $log) {
            $timeline->push([
                'timestamp' => $log->created_at,
                'title' => 'IVR Webhook Logged',
                'description' => trim('Call ID: ' . ($log->callid ?: 'N/A') . ' | Dial status: ' . ($log->dialstatus ?: 'N/A')),
                'source' => 'IVR',
                'status' => $log->status ?: 'received',
                'actor' => $log->agent?->name ?: 'System',
                'entity' => 'mcube_webhook_log',
                'reference' => 'MCube Log #' . $log->id,
                'error' => $log->message,
            ]);
        }

        foreach ($ivrAudits as $audit) {
            $timeline->push([
                'timestamp' => $audit->processed_at ?: $audit->created_at,
                'title' => 'IVR Automation Evaluated',
                'description' => trim('Reason: ' . ($audit->reason ?: 'N/A') . ' | Strategy: ' . ($audit->strategy_used ?: 'N/A')),
                'source' => 'IVR Automation',
                'status' => $audit->fallback_used ? 'fallback' : 'processed',
                'actor' => $audit->receiver?->name ?: 'System',
                'entity' => 'ivr_automation_audit',
                'reference' => 'IVR Audit #' . $audit->id,
            ]);
        }

        foreach ($importedLeads as $importedLead) {
            $batch = $importedLead->importBatch;
            $timeline->push([
                'timestamp' => $importedLead->assigned_at ?: $importedLead->created_at,
                'title' => 'Import Row Linked',
                'description' => 'Batch: ' . ($batch?->file_name ?: 'N/A') . ' | Status: ' . ($batch?->status ?: 'N/A'),
                'source' => 'Import',
                'status' => $batch?->status ?: 'linked',
                'actor' => $batch?->user?->name ?: 'System',
                'entity' => 'imported_lead',
                'reference' => 'Import Row #' . $importedLead->id,
                'error' => is_array($batch?->error_log) ? json_encode($batch->error_log) : $batch?->error_log,
            ]);
        }

        foreach ($metaSyncLogs as $syncLog) {
            $timeline->push([
                'timestamp' => $syncLog->synced_at ?: $syncLog->created_at,
                'title' => 'Meta Stage Sync',
                'description' => trim('Meta stage: ' . ($syncLog->meta_stage ?: 'N/A') . ' | Reason: ' . ($syncLog->reason ?: 'N/A')),
                'source' => 'Meta Sync',
                'status' => $syncLog->status ?: 'logged',
                'actor' => $syncLog->user?->name ?: 'System',
                'entity' => 'meta_review_sync_log',
                'reference' => 'Sync #' . $syncLog->id,
            ]);
        }

        foreach ($leadAssignments as $assignment) {
            $timeline->push([
                'timestamp' => $assignment->assigned_at ?: $assignment->created_at,
                'title' => $assignment->is_active ? 'Lead Assigned' : 'Lead Unassigned',
                'description' => 'Assigned to: ' . ($assignment->assignedTo?->name ?: 'Unknown') . ' | By: ' . ($assignment->assignedBy?->name ?: 'System'),
                'source' => 'Assignment',
                'status' => $assignment->assignment_method ?: 'manual',
                'actor' => $assignment->assignedBy?->name ?: 'System',
                'entity' => 'lead_assignment',
                'reference' => 'Assignment #' . $assignment->id,
            ]);
        }

        foreach ($managerTasks as $task) {
            $timeline->push([
                'timestamp' => $task->created_at,
                'title' => 'Manager Task Created',
                'description' => ($task->title ?: ('Task #' . $task->id)) . ' | Assigned to: ' . ($task->assignedTo?->name ?: 'Unknown') . ' | Status: ' . ucfirst((string) $task->status),
                'source' => 'Task',
                'status' => $task->status,
                'actor' => $task->creator?->name ?: 'System',
                'entity' => 'task',
                'reference' => 'Task #' . $task->id,
            ]);

            if ($task->status === 'cancelled') {
                $reason = $this->deriveTaskCancellationReason($task);

                $timeline->push([
                    'timestamp' => $task->completed_at ?: $task->updated_at,
                    'title' => 'Manager Task Cancelled',
                    'description' => ($task->title ?: ('Task #' . $task->id)) . ' | Reason: ' . ($reason ?: 'Reason not recorded'),
                    'source' => 'Task',
                    'status' => 'cancelled',
                    'actor' => $task->assignedTo?->name ?: $task->creator?->name ?: 'System',
                    'entity' => 'task',
                    'reference' => 'Task #' . $task->id,
                    'reason' => $reason,
                ]);
            } elseif ($task->completed_at) {
                $timeline->push([
                    'timestamp' => $task->completed_at,
                    'title' => 'Manager Task Completed',
                    'description' => ($task->title ?: ('Task #' . $task->id)) . ' | Outcome: ' . ($task->outcome ?: 'N/A'),
                    'source' => 'Task',
                    'status' => 'completed',
                    'actor' => $task->assignedTo?->name ?: $task->creator?->name ?: 'System',
                    'entity' => 'task',
                    'reference' => 'Task #' . $task->id,
                ]);
            }
        }

        foreach ($telecallerTasks as $task) {
            $timeline->push([
                'timestamp' => $task->created_at,
                'title' => 'Telecaller Task Created',
                'description' => 'Type: ' . ($task->task_type ?: 'task') . ' | Assigned to: ' . ($task->assignedTo?->name ?: 'Unknown') . ' | Status: ' . ucfirst((string) $task->status),
                'source' => 'Task',
                'status' => $task->status,
                'actor' => $task->createdBy?->name ?: $task->assignedTo?->name ?: 'System',
                'entity' => 'telecaller_task',
                'reference' => 'Telecaller Task #' . $task->id,
            ]);

            if ($task->status === 'cancelled') {
                $reason = $this->deriveTaskCancellationReason($task);

                $timeline->push([
                    'timestamp' => $task->completed_at ?: $task->updated_at,
                    'title' => 'Telecaller Task Cancelled',
                    'description' => 'Type: ' . ($task->task_type ?: 'task') . ' | Reason: ' . ($reason ?: 'Reason not recorded'),
                    'source' => 'Task',
                    'status' => 'cancelled',
                    'actor' => $task->assignedTo?->name ?: $task->createdBy?->name ?: 'System',
                    'entity' => 'telecaller_task',
                    'reference' => 'Telecaller Task #' . $task->id,
                    'reason' => $reason,
                ]);
            } elseif ($task->completed_at) {
                $timeline->push([
                    'timestamp' => $task->completed_at,
                    'title' => 'Telecaller Task Completed',
                    'description' => 'Type: ' . ($task->task_type ?: 'task') . ' | Outcome: ' . ($task->outcome ?: 'N/A'),
                    'source' => 'Task',
                    'status' => 'completed',
                    'actor' => $task->assignedTo?->name ?: $task->createdBy?->name ?: 'System',
                    'entity' => 'telecaller_task',
                    'reference' => 'Telecaller Task #' . $task->id,
                ]);
            }
        }

        $activityTimeline->each(function (array $activity) use ($timeline) {
            $timeline->push([
                'timestamp' => $activity['timestamp'] ?? now(),
                'title' => $activity['title'] ?? 'Lead Activity',
                'description' => $activity['description'] ?? '',
                'source' => 'Lead Activity',
                'status' => data_get($activity, 'metadata.status'),
                'actor' => data_get($activity, 'user.name') ?: 'System',
                'entity' => $activity['type'] ?? 'lead_activity',
                'reference' => $this->timelineReferenceFromActivity($activity),
                'error' => data_get($activity, 'metadata.error'),
            ]);
        });

        $relatedActivityLogs->each(function (ActivityLog $log) use ($timeline) {
            $timeline->push([
                'timestamp' => $log->created_at,
                'title' => 'Activity Log',
                'description' => $log->description ?: Str::headline((string) $log->action),
                'source' => 'System Activity',
                'status' => $log->action,
                'actor' => $log->user?->name ?: 'System',
                'entity' => class_basename((string) $log->model_type),
                'reference' => class_basename((string) $log->model_type) . ' #' . $log->model_id,
            ]);
        });

        $taskActivities->each(function (TaskActivity $activity) use ($timeline, $managerTasksById) {
            $timeline->push([
                'timestamp' => $activity->created_at,
                'title' => $activity->activity_type === 'status_changed' && $activity->new_value === 'cancelled'
                    ? 'Task Cancel Activity'
                    : 'Task Activity',
                'description' => $this->buildTaskActivityDescription($activity, $managerTasksById),
                'source' => 'Task Activity',
                'status' => $activity->activity_type,
                'actor' => $activity->user?->name ?: 'System',
                'entity' => 'Task',
                'reference' => 'Task #' . $activity->task_id,
                'reason' => $activity->activity_type === 'status_changed' && $activity->new_value === 'cancelled'
                    ? $this->deriveTaskCancellationReason($managerTasksById->get($activity->task_id))
                    : null,
            ]);
        });

        return $timeline
            ->filter(fn (array $item) => $item['timestamp'] !== null)
            ->sortByDesc(fn (array $item) => optional($item['timestamp'])->timestamp ?: 0)
            ->values();
    }

    private function buildRawPanels(
        ?FbLead $metaLead,
        Collection $metaWebhookEvents,
        Collection $mcubeLogs,
        Collection $mcubeOutboundAttempts,
        Collection $ivrAudits,
        Collection $importedLeads,
        Collection $metaSyncLogs,
        Collection $relatedActivityLogs,
        Collection $taskActivities,
        Collection $managerTasks,
        Collection $telecallerTasks
    ): Collection {
        $panels = collect();

        if ($metaLead) {
            $panels->push([
                'title' => 'Meta Lead Row',
                'subtitle' => 'fb_leads fields and raw response',
                'data' => [
                    'leadgen_id' => $metaLead->leadgen_id,
                    'field_data_json' => $metaLead->field_data_json,
                    'raw_response_json' => $metaLead->raw_response_json,
                ],
            ]);
        }

        if ($metaWebhookEvents->isNotEmpty()) {
            $panels->push([
                'title' => 'Meta Webhook Events',
                'subtitle' => 'fb_webhook_events raw payloads',
                'data' => $metaWebhookEvents->map(fn (FbWebhookEvent $event) => [
                    'id' => $event->id,
                    'leadgen_id' => $event->leadgen_id,
                    'status' => $event->status,
                    'error' => $event->error,
                    'created_at' => optional($event->created_at)->toDateTimeString(),
                    'raw_payload' => $event->raw_payload,
                ])->values()->all(),
            ]);
        }

        if ($mcubeLogs->isNotEmpty()) {
            $panels->push([
                'title' => 'IVR / MCube Logs',
                'subtitle' => 'mcube_webhook_logs raw payloads and linkage',
                'data' => $mcubeLogs->map(fn (McubeWebhookLog $log) => [
                    'id' => $log->id,
                    'callid' => $log->callid,
                    'callto' => $log->callto,
                    'dialstatus' => $log->dialstatus,
                    'direction' => $log->direction,
                    'status' => $log->status,
                    'lead_id' => $log->lead_id,
                    'recording_url' => $log->recording_url,
                    'message' => $log->message,
                    'created_at' => optional($log->created_at)->toDateTimeString(),
                    'raw_payload' => $log->raw_payload,
                ])->values()->all(),
            ]);
        }

        if ($mcubeOutboundAttempts->isNotEmpty()) {
            $panels->push([
                'title' => 'MCube Outbound Attempts',
                'subtitle' => 'CRM initiated click-to-call requests and responses',
                'data' => $mcubeOutboundAttempts->map(fn (McubeOutboundAttempt $attempt) => [
                    'id' => $attempt->id,
                    'user' => $attempt->user?->name,
                    'lead_id' => $attempt->lead_id,
                    'task_id' => $attempt->task_id,
                    'agent_number' => $attempt->agent_number,
                    'customer_number' => $attempt->customer_number,
                    'refid' => $attempt->refid,
                    'status' => $attempt->status,
                    'http_status' => $attempt->http_status,
                    'error_message' => $attempt->error_message,
                    'attempted_at' => optional($attempt->attempted_at)->toDateTimeString(),
                    'request_payload' => $attempt->request_payload,
                    'response_payload' => $attempt->response_payload,
                ])->values()->all(),
            ]);
        }

        if ($ivrAudits->isNotEmpty()) {
            $panels->push([
                'title' => 'IVR Automation Audit',
                'subtitle' => 'Routing and automation decision trail',
                'data' => $ivrAudits->map(fn (IvrLeadAutomationAudit $audit) => [
                    'id' => $audit->id,
                    'reason' => $audit->reason,
                    'rule_source' => $audit->rule_source,
                    'target_type' => $audit->target_type,
                    'strategy_used' => $audit->strategy_used,
                    'fallback_used' => $audit->fallback_used,
                    'fallback_mode' => $audit->fallback_mode,
                    'receiver' => $audit->receiver?->name,
                    'assigned_user' => $audit->assignedUser?->name,
                    'processed_at' => optional($audit->processed_at)->toDateTimeString(),
                    'meta' => $audit->meta,
                ])->values()->all(),
            ]);
        }

        if ($importedLeads->isNotEmpty()) {
            $panels->push([
                'title' => 'Import Evidence',
                'subtitle' => 'Imported row data and batch metadata',
                'data' => $importedLeads->map(fn (ImportedLead $importedLead) => [
                    'id' => $importedLead->id,
                    'assigned_to' => $importedLead->assignedTo?->name,
                    'assigned_at' => optional($importedLead->assigned_at)->toDateTimeString(),
                    'import_data' => $importedLead->import_data,
                    'batch' => [
                        'id' => $importedLead->importBatch?->id,
                        'file_name' => $importedLead->importBatch?->file_name,
                        'source_type' => $importedLead->importBatch?->source_type,
                        'import_kind' => $importedLead->importBatch?->import_kind,
                        'status' => $importedLead->importBatch?->status,
                        'automation_id' => $importedLead->importBatch?->automation_id,
                        'error_log' => $importedLead->importBatch?->error_log,
                    ],
                ])->values()->all(),
            ]);
        }

        if ($metaSyncLogs->isNotEmpty()) {
            $panels->push([
                'title' => 'Meta Sync Logs',
                'subtitle' => 'Meta review sync attempts and outcomes',
                'data' => $metaSyncLogs->map(fn (MetaReviewSyncLog $log) => [
                    'id' => $log->id,
                    'status' => $log->status,
                    'reason' => $log->reason,
                    'previous_stage' => $log->previous_stage,
                    'meta_stage' => $log->meta_stage,
                    'event_name' => $log->event_name,
                    'meta_leadgen_id' => $log->meta_leadgen_id,
                    'response_code' => $log->response_code,
                    'response_summary' => $log->response_summary,
                    'note' => $log->note,
                    'synced_at' => optional($log->synced_at)->toDateTimeString(),
                ])->values()->all(),
            ]);
        }

        if ($relatedActivityLogs->isNotEmpty()) {
            $panels->push([
                'title' => 'Lead Activity Logs',
                'subtitle' => 'ActivityLog evidence across lead and related records',
                'data' => $relatedActivityLogs->map(fn (ActivityLog $log) => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'model_type' => $log->model_type,
                    'model_id' => $log->model_id,
                    'user' => $log->user?->name,
                    'description' => $log->description,
                    'old_values' => $log->old_values,
                    'new_values' => $log->new_values,
                    'created_at' => optional($log->created_at)->toDateTimeString(),
                ])->values()->all(),
            ]);
        }

        if ($taskActivities->isNotEmpty()) {
            $panels->push([
                'title' => 'Task Activity Rows',
                'subtitle' => 'Manager task activity history with actor and status transitions',
                'data' => $taskActivities->map(fn (TaskActivity $activity) => [
                    'id' => $activity->id,
                    'task_id' => $activity->task_id,
                    'user' => $activity->user?->name,
                    'activity_type' => $activity->activity_type,
                    'old_value' => $activity->old_value,
                    'new_value' => $activity->new_value,
                    'description' => $activity->description,
                    'created_at' => optional($activity->created_at)->toDateTimeString(),
                ])->values()->all(),
            ]);
        }

        if ($managerTasks->isNotEmpty() || $telecallerTasks->isNotEmpty()) {
            $panels->push([
                'title' => 'Task Evidence',
                'subtitle' => 'All lead-linked manager and telecaller tasks with current state',
                'data' => [
                    'manager_tasks' => $managerTasks->map(fn (Task $task) => [
                        'id' => $task->id,
                        'title' => $task->title,
                        'type' => $task->type,
                        'status' => $task->status,
                        'assigned_to' => $task->assignedTo?->name,
                        'created_by' => $task->creator?->name,
                    'scheduled_at' => optional($task->scheduled_at)->toDateTimeString(),
                    'completed_at' => optional($task->completed_at)->toDateTimeString(),
                    'outcome' => $task->outcome,
                    'outcome_remark' => $task->outcome_remark,
                    'cancellation_reason' => $this->deriveTaskCancellationReason($task),
                    'notes' => $task->notes,
                    'deleted_at' => optional($task->deleted_at)->toDateTimeString(),
                ])->values()->all(),
                    'telecaller_tasks' => $telecallerTasks->map(fn (TelecallerTask $task) => [
                        'id' => $task->id,
                        'task_type' => $task->task_type,
                        'status' => $task->status,
                        'assigned_to' => $task->assignedTo?->name,
                        'created_by' => $task->createdBy?->name,
                        'scheduled_at' => optional($task->scheduled_at)->toDateTimeString(),
                        'completed_at' => optional($task->completed_at)->toDateTimeString(),
                        'outcome' => $task->outcome,
                        'cancellation_reason' => $this->deriveTaskCancellationReason($task),
                        'notes' => $task->notes,
                        'deleted_at' => optional($task->deleted_at)->toDateTimeString(),
                    ])->values()->all(),
                ],
            ]);
        }

        return $panels->values();
    }

    private function collectRelatedActivityLogs(?Lead $lead, Collection $managerTasks, Collection $telecallerTasks): Collection
    {
        if (!$lead) {
            return collect();
        }

        $taskIds = $managerTasks->pluck('id')->filter()->values();
        $telecallerTaskIds = $telecallerTasks->pluck('id')->filter()->values();
        $meetingIds = $lead->meetings()->withoutGlobalScopes()->pluck('id');
        $siteVisitIds = $lead->siteVisits()->withoutGlobalScope('visible_in_queue')->pluck('id');
        $followUpIds = $lead->followUps()->withoutGlobalScope('visible_in_queue')->pluck('id');
        $prospectIds = $lead->prospects()->pluck('id');

        return ActivityLog::query()
            ->with('user')
            ->where(function ($query) use ($lead, $taskIds, $telecallerTaskIds, $meetingIds, $siteVisitIds, $followUpIds, $prospectIds) {
                $query->where(function ($leadQuery) use ($lead) {
                    $leadQuery->whereIn('model_type', ['Lead', Lead::class])->where('model_id', $lead->id);
                });

                if ($taskIds->isNotEmpty()) {
                    $query->orWhere(function ($taskQuery) use ($taskIds) {
                        $taskQuery->whereIn('model_type', ['Task', Task::class])->whereIn('model_id', $taskIds->all());
                    });
                }

                if ($telecallerTaskIds->isNotEmpty()) {
                    $query->orWhere(function ($taskQuery) use ($telecallerTaskIds) {
                        $taskQuery->whereIn('model_type', ['TelecallerTask', TelecallerTask::class])->whereIn('model_id', $telecallerTaskIds->all());
                    });
                }

                if ($meetingIds->isNotEmpty()) {
                    $query->orWhere(function ($meetingQuery) use ($meetingIds) {
                        $meetingQuery->whereIn('model_type', ['Meeting', \App\Models\Meeting::class])->whereIn('model_id', $meetingIds->all());
                    });
                }

                if ($siteVisitIds->isNotEmpty()) {
                    $query->orWhere(function ($visitQuery) use ($siteVisitIds) {
                        $visitQuery->whereIn('model_type', ['SiteVisit', \App\Models\SiteVisit::class])->whereIn('model_id', $siteVisitIds->all());
                    });
                }

                if ($followUpIds->isNotEmpty()) {
                    $query->orWhere(function ($followUpQuery) use ($followUpIds) {
                        $followUpQuery->whereIn('model_type', ['FollowUp', \App\Models\FollowUp::class])->whereIn('model_id', $followUpIds->all());
                    });
                }

                if ($prospectIds->isNotEmpty()) {
                    $query->orWhere(function ($prospectQuery) use ($prospectIds) {
                        $prospectQuery->whereIn('model_type', ['Prospect', \App\Models\Prospect::class])->whereIn('model_id', $prospectIds->all());
                    });
                }
            })
            ->latest('created_at')
            ->get()
            ->unique('id')
            ->values();
    }

    private function collectTaskActivities(Collection $managerTasks): Collection
    {
        $taskIds = $managerTasks->pluck('id')->filter()->values();
        if ($taskIds->isEmpty()) {
            return collect();
        }

        return TaskActivity::query()
            ->with('user')
            ->whereIn('task_id', $taskIds->all())
            ->latest('created_at')
            ->get()
            ->unique('id')
            ->values();
    }

    private function timelineReferenceFromActivity(array $activity): ?string
    {
        $metadata = $activity['metadata'] ?? [];
        $taskId = $metadata['task_id'] ?? null;
        if ($taskId) {
            $taskModel = $metadata['task_model'] ?? ($metadata['source'] ?? 'Task');
            return $taskModel . ' #' . $taskId;
        }

        return $activity['type'] ?? null;
    }

    private function buildTaskActivityDescription(TaskActivity $activity, Collection $tasksById): string
    {
        if ($activity->activity_type !== 'status_changed' || $activity->new_value !== 'cancelled') {
            return $activity->description ?: ('Task ' . Str::headline((string) $activity->activity_type));
        }

        $task = $tasksById->get($activity->task_id);
        $reason = $this->deriveTaskCancellationReason($task);

        return trim(($activity->description ?: 'Task cancelled') . ' | Reason: ' . ($reason ?: 'Reason not recorded'));
    }

    private function deriveTaskCancellationReason(Task|TelecallerTask|null $task): ?string
    {
        if (!$task || $task->status !== 'cancelled') {
            return null;
        }

        $notes = trim((string) ($task->notes ?? ''));
        if ($notes !== '') {
            $noteLines = preg_split('/\r\n|\r|\n/', $notes) ?: [];
            foreach (array_reverse($noteLines) as $line) {
                $line = trim((string) $line);
                if ($line === '') {
                    continue;
                }

                if (str_starts_with($line, 'Cancelled due to ')) {
                    return $line;
                }

                if (str_starts_with($line, 'Cancellation reason:')) {
                    return trim(substr($line, strlen('Cancellation reason:')));
                }
            }
        }

        if ($task instanceof Task && filled($task->outcome_remark)) {
            return trim((string) $task->outcome_remark);
        }

        return null;
    }

    private function detectDuplicate(?Lead $lead, Collection $metaWebhookEvents, ?ImportBatch $importBatch): bool
    {
        if ($lead && $lead->phone) {
            $duplicates = Lead::withTrashed()
                ->where('phone', $lead->phone)
                ->count();
            if ($duplicates > 1) {
                return true;
            }
        }

        $webhookDuplicate = $metaWebhookEvents->contains(function (FbWebhookEvent $event) {
            return Str::contains(Str::lower((string) $event->error), 'duplicate')
                || Str::contains(Str::lower((string) $event->status), 'duplicate');
        });

        if ($webhookDuplicate) {
            return true;
        }

        if ($importBatch && !empty($importBatch->error_log)) {
            return Str::contains(Str::lower(json_encode($importBatch->error_log)), 'duplicate');
        }

        return false;
    }

    private function makeLeadCandidate(Lead $lead, string $subtitle): array
    {
        return [
            'type' => 'lead',
            'id' => $lead->id,
            'title' => $lead->name,
            'subtitle' => $subtitle . ' | ' . ($lead->phone ?: 'No phone'),
            'badge' => $lead->source_label,
        ];
    }
}

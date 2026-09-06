<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Events\LeadAssigned;
use App\Events\LeadStatusUpdated;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\SiteVisit;
use App\Models\User;
use App\Models\Task;
use App\Models\TelecallerTask;
use App\Services\LeadActiveWorkflowService;
use App\Services\LeadDuplicateGuardService;
use App\Services\TelecallerTaskService;
use App\Services\LeadOwnerTaskService;
use App\Services\LeadTaskCleanupService;
use App\Services\LeadTransferService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class LeadController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Lead::with([
            'creator',
            'activeAssignments.assignedTo',
            'latestImportedLead.importBatch',
            'formFieldValues:lead_id,field_key,field_value',
            'followUps' => function ($query) {
                $query->select(['id', 'lead_id', 'scheduled_at', 'status', 'completed_at', 'notes', 'updated_at'])
                    ->whereNull('completed_at')
                    ->orderBy('scheduled_at');
            },
            'meetings' => function ($query) {
                $query->select(['id', 'lead_id', 'scheduled_at', 'status', 'completed_at', 'meeting_notes', 'updated_at'])
                    ->whereNull('completed_at')
                    ->orderBy('scheduled_at');
            },
            'siteVisits' => function ($query) {
                $query->select(['id', 'lead_id', 'scheduled_at', 'status', 'completed_at', 'visit_notes', 'updated_at'])
                    ->whereNull('completed_at')
                    ->orderBy('scheduled_at');
            },
        ]);
        $requestedStatus = trim((string) $request->input('status', ''));
        $groupedStatusFilters = [
            'new' => ['new', Lead::STATUS_FRESH_TRANSFER],
            'prospect' => ['verified_prospect'],
            'meeting' => ['meeting_scheduled', 'meeting_completed'],
            'visit' => ['visit_scheduled', 'visit_done', 'revisited_scheduled', 'revisited_completed'],
            'closer' => ['closed'],
        ];
        $isManagerLeadView = $user->isSalesManager() || $user->isSeniorManager() || $user->isAssistantSalesManager();
        $usesDerivedDisplayStatusFilter = $isManagerLeadView && in_array($requestedStatus, ['fresh', 'new', 'follow_up', 'cnp'], true);

        // Role-based filtering
        if ($user->isSalesHead()) {
            $teamMemberIds = $user->getAllTeamMemberIds();

            if (empty($teamMemberIds)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function ($visibilityQuery) use ($teamMemberIds) {
                    $visibilityQuery->whereAssignedToUsers($teamMemberIds)
                        ->orWhere(function ($fallbackQuery) use ($teamMemberIds) {
                            $fallbackQuery->whereVisibleViaProspectFallback($teamMemberIds);
                        });
                });
            }
        } elseif ($user->isSalesExecutive()) {
            $query->where(function ($visibilityQuery) use ($user) {
                $visibilityQuery->whereAssignedToUsers([$user->id])
                    ->orWhere(function ($fallbackQuery) use ($user) {
                        $fallbackQuery->whereVisibleViaProspectFallback([$user->id]);
                    });
            });
        } elseif ($user->isSalesManager() || $user->isSeniorManager() || $user->isAssistantSalesManager()) {
            $teamMemberIds = $user->teamMembers()->pluck('id');
            $managerAndTeamIds = $teamMemberIds->merge([$user->id])->unique()->values();

            // Show leads: (1) assigned to this manager or their team, OR (2) from team's verified prospects
            $query->where(function ($q) use ($managerAndTeamIds, $teamMemberIds, $user) {
                $q->whereAssignedToUsers($managerAndTeamIds);
                if ($teamMemberIds->isNotEmpty()) {
                    $q->orWhere(function ($fallbackQuery) use ($teamMemberIds, $user) {
                        $fallbackQuery->whereVisibleViaProspectFallback($teamMemberIds, function ($subQ) use ($user) {
                            $subQ->whereIn('verification_status', ['verified', 'approved'])
                                ->where('verified_by', $user->id);
                        });
                    });
                }
            });
        }

        if ($request->has('search')) {
            $search = $request->search;
            $normalizedSearch = strtolower(trim((string) $search));
            $query->where(function ($q) use ($search, $normalizedSearch) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhereHas('followUps', function ($followUpQuery) use ($search, $normalizedSearch) {
                      $followUpQuery->whereNull('completed_at')
                          ->where(function ($innerQuery) use ($search, $normalizedSearch) {
                              $innerQuery->where('notes', 'like', "%{$search}%");

                              if (str_contains($normalizedSearch, 'follow')) {
                                  $innerQuery->orWhereNotNull('id');
                              }
                          });
                  })
                  ->orWhereHas('meetings', function ($meetingQuery) use ($search, $normalizedSearch) {
                      $meetingQuery->whereNull('completed_at')
                          ->where(function ($innerQuery) use ($search, $normalizedSearch) {
                              $innerQuery->where('meeting_notes', 'like', "%{$search}%");

                              if (str_contains($normalizedSearch, 'meeting')) {
                                  $innerQuery->orWhereNotNull('id');
                              }
                          });
                  })
                  ->orWhereHas('siteVisits', function ($visitQuery) use ($search, $normalizedSearch) {
                      $visitQuery->whereNull('completed_at')
                          ->where(function ($innerQuery) use ($search, $normalizedSearch) {
                              $innerQuery->where('visit_notes', 'like', "%{$search}%");

                              if (str_contains($normalizedSearch, 'visit')) {
                                  $innerQuery->orWhereNotNull('id');
                              }
                          });
                  });
            });
        }

        // Filter by assigned user
        if ($request->has('assigned_to')) {
            $assignedToId = $request->assigned_to;
            $query->whereHas('activeAssignments', function ($q) use ($assignedToId) {
                $q->where('assigned_to', $assignedToId);
            });
        }

        if ($request->filled('from_date')) {
            $query->where('created_at', '>=', Carbon::parse($request->from_date)->startOfDay());
        }

        if ($request->filled('to_date')) {
            $query->where('created_at', '<=', Carbon::parse($request->to_date)->endOfDay());
        }

        if ($request->boolean('fresh_today')) {
            $query->whereHas('assignments', function ($q) use ($user) {
                $q->where('assigned_to', $user->id)
                    ->whereDate('created_at', today());
            });
        }

        $statusCounts = $isManagerLeadView
            ? $this->buildManagerLeadStatusCounts((clone $query)->latest()->get())
            : [];

        // Keep summary cards scoped to user/search/date filters, not the selected pipeline tab.
        if ($request->filled('status') && !$usesDerivedDisplayStatusFilter) {
            if ($requestedStatus === 'reenquiry') {
                $query->where('is_reenquiry', true);
            } elseif (array_key_exists($requestedStatus, $groupedStatusFilters)) {
                $query->whereIn('status', $groupedStatusFilters[$requestedStatus]);
            } else {
                $query->where('status', $requestedStatus);
            }
        }

        $perPage = min(100, max(1, (int) $request->get('per_page', 50)));
        if ($usesDerivedDisplayStatusFilter) {
            $orderedLeads = $query->latest()->get();
            $this->applyDisplayStatuses($orderedLeads);
            $this->enrichLeadUiPayload($orderedLeads);

            $filteredLeads = $orderedLeads
                ->filter(function (Lead $lead) use ($requestedStatus) {
                    $displayStatus = (string) ($lead->display_status ?? $lead->status ?? 'new');

                    if (in_array($requestedStatus, ['fresh', 'new'], true)) {
                        return in_array($displayStatus, ['new', Lead::STATUS_FRESH_TRANSFER], true);
                    }

                    if ($requestedStatus === 'new_reenquiry') {
                        return $lead->is_reenquiry || in_array($displayStatus, ['new', Lead::STATUS_FRESH_TRANSFER], true);
                    }

                    return $displayStatus === $requestedStatus;
                })
                ->values();

            $currentPage = max(1, (int) $request->input('page', 1));
            $items = $filteredLeads->slice(($currentPage - 1) * $perPage, $perPage)->values();

            $leads = new LengthAwarePaginator(
                $items,
                $filteredLeads->count(),
                (int) $perPage,
                $currentPage,
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]
            );
        } else {
            $leads = $query->latest()->paginate($perPage);
            $this->applyDisplayStatuses($leads->getCollection());
            $this->enrichLeadUiPayload($leads->getCollection());
        }

        $payload = $leads->toArray();
        $payload['status_counts'] = $statusCounts;

        return response()->json($payload);
    }

    private function applyDisplayStatuses(\Illuminate\Support\Collection $leads): void
    {
        if ($leads->isEmpty()) {
            return;
        }

        $leadIds = $leads->pluck('id')->filter()->values();
        if ($leadIds->isEmpty()) {
            return;
        }

        $latestTasksByLead = Task::query()
            ->with('lead.prospects')
            ->whereIn('lead_id', $leadIds)
            ->where('type', 'phone_call')
            ->orderByDesc('id')
            ->get()
            ->groupBy('lead_id');

        foreach ($leads as $lead) {
            $displayStatus = (string) ($lead->status ?? 'new');

            if ($displayStatus !== 'new') {
                $lead->setAttribute('display_status', $displayStatus);
                continue;
            }

            $tasks = $latestTasksByLead->get($lead->id, collect());
            $latestRelevantTask = $tasks->first(function (Task $task) use ($lead) {
                $taskText = strtolower(trim(
                    ($task->title ?? '') . ' ' .
                    ($task->description ?? '') . ' ' .
                    ($task->notes ?? '')
                ));

                $isCnpRetryTask = str_contains($taskText, 'cnp retry task created')
                    || str_contains($taskText, 'cnp rescheduled')
                    || str_contains($taskText, 'previous call not picked');

                if ($isCnpRetryTask) {
                    return true;
                }

                $taskCategory = $this->determineAsmTaskCategory($task, $lead);

                return ($task->outcome === 'cnp' && $taskCategory === 'fresh_lead')
                    || ($task->outcome === 'follow_up' && $taskCategory === 'fresh_lead');
            });

            if ($latestRelevantTask) {
                $displayStatus = $latestRelevantTask->outcome === 'follow_up' ? 'follow_up' : 'cnp';
            }

            $lead->setAttribute('display_status', $displayStatus);
        }
    }

    private function enrichLeadUiPayload(\Illuminate\Support\Collection $leads): void
    {
        $leadIds = $leads->pluck('id')->filter()->values();
        $taskColumns = collect([
            'id',
            'lead_id',
            'type',
            'title',
            'description',
            'status',
            'notes',
            'scheduled_at',
            'completed_at',
            'updated_at',
        ]);

        foreach (['meeting_id', 'site_visit_id', 'follow_up_id'] as $optionalColumn) {
            if (Task::supportsColumn($optionalColumn)) {
                $taskColumns->push($optionalColumn);
            }
        }

        $tasksByLead = $leadIds->isEmpty()
            ? collect()
            : Task::query()
                ->whereIn('lead_id', $leadIds)
                ->whereIn('status', Task::OPEN_STATUSES)
                ->whereNull('completed_at')
                ->orderBy('scheduled_at')
                ->get($taskColumns->unique()->values()->all())
                ->groupBy('lead_id');

        foreach ($leads as $lead) {
            $lead->setAttribute('import_channel_tag', $lead->import_channel_tag);
            $lead->setAttribute('latest_remark', $this->resolveLatestLeadRemark($lead));

            $nextAction = $this->resolveStructuredNextLeadAction($lead, $tasksByLead->get($lead->id, collect()));
            $lead->setAttribute('next_action_label', $nextAction['label']);
            $lead->setAttribute('next_action_at', $nextAction['at']);
            $lead->setAttribute('next_action_summary', $nextAction['summary']);
            $lead->setAttribute('next_action', $nextAction);
        }
    }

    private function buildManagerLeadStatusCounts(\Illuminate\Support\Collection $leads): array
    {
        $this->applyDisplayStatuses($leads);

        $counts = [
            'all' => 0,
            'fresh' => 0,
            'new' => 0,
            'fresh_transfer' => 0,
            'prospect' => 0,
            'follow_up' => 0,
            'meeting' => 0,
            'visit' => 0,
            'cnp' => 0,
            'closer' => 0,
            'reenquiry' => 0,
        ];

        foreach ($leads as $lead) {
            $displayStatus = (string) ($lead->display_status ?? $lead->status ?? 'new');
            $counts['all']++;

            if ($displayStatus === 'new') {
                $counts['fresh']++;
                $counts['new']++;
            }

            if ($displayStatus === Lead::STATUS_FRESH_TRANSFER) {
                $counts['fresh']++;
                $counts['new']++;
                $counts['fresh_transfer']++;
            }

            if ($displayStatus === 'follow_up') {
                $counts['follow_up']++;
            }

            if ($displayStatus === 'cnp') {
                $counts['cnp']++;
            }

            if ($displayStatus === 'verified_prospect') {
                $counts['prospect']++;
            }

            if (in_array($displayStatus, ['meeting_scheduled', 'meeting_completed'], true)) {
                $counts['meeting']++;
            }

            if (in_array($displayStatus, ['visit_scheduled', 'visit_done', 'revisited_scheduled', 'revisited_completed'], true)) {
                $counts['visit']++;
            }

            if ($displayStatus === 'closed') {
                $counts['closer']++;
            }

            if ($lead->is_reenquiry) {
                $counts['reenquiry']++;
            }
        }

        return $counts;
    }

    private function resolveLatestLeadRemark(Lead $lead): string
    {
        $cleanRemark = function (?string $value): ?string {
            $text = trim((string) $value);
            if ($text === '') {
                return null;
            }

            $text = preg_replace('/^\[\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}(?::\d{2})?)?\]\s*/i', '', $text);
            $text = preg_replace('/^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}(?::\d{2})?)?\s*[-|:]\s*/i', '', $text);
            $text = preg_replace('/^(?:ASM|SM|CRM|Manager)\s+outcome:\s*/i', '', $text);
            $text = trim((string) $text);

            if ($text === '') {
                return null;
            }

            $rawMetaMarkers = [
                'inbox_url:',
                'location_preffered:',
                'location_preferred:',
                'apartment_budet:',
                'apartment_budget:',
                'exact_status_or_remarks',
                'budget:',
                'property_type:',
            ];
            $lower = strtolower($text);
            $markerHits = 0;
            foreach ($rawMetaMarkers as $marker) {
                if (str_contains($lower, $marker)) {
                    $markerHits++;
                }
            }

            if ($markerHits >= 2 || str_contains($lower, 'https://business.facebook.com/')) {
                return null;
            }

            return $text;
        };

        $latestEntityRemark = collect([
            optional($lead->followUps)->filter(fn ($followUp) => is_string($followUp->notes) && trim($followUp->notes) !== '')
                ->sortByDesc(fn ($followUp) => optional($followUp->updated_at)->timestamp ?? 0)
                ->map(fn ($followUp) => ['text' => trim($followUp->notes), 'updated_at' => $followUp->updated_at])
                ->first(),
            optional($lead->meetings)->filter(fn ($meeting) => is_string($meeting->meeting_notes) && trim($meeting->meeting_notes) !== '')
                ->sortByDesc(fn ($meeting) => optional($meeting->updated_at)->timestamp ?? 0)
                ->map(fn ($meeting) => ['text' => trim($meeting->meeting_notes), 'updated_at' => $meeting->updated_at])
                ->first(),
            optional($lead->siteVisits)->filter(fn ($siteVisit) => is_string($siteVisit->visit_notes) && trim($siteVisit->visit_notes) !== '')
                ->sortByDesc(fn ($siteVisit) => optional($siteVisit->updated_at)->timestamp ?? 0)
                ->map(fn ($siteVisit) => ['text' => trim($siteVisit->visit_notes), 'updated_at' => $siteVisit->updated_at])
                ->first(),
        ])->filter()->sortByDesc(fn ($item) => optional($item['updated_at'])->timestamp ?? 0)->first();

        if ($latestEntityRemark && !empty($latestEntityRemark['text'])) {
            $cleaned = $cleanRemark($latestEntityRemark['text']);
            if ($cleaned) {
                return $cleaned;
            }
        }

        $formValues = $lead->relationLoaded('formFieldValues')
            ? $lead->formFieldValues->pluck('field_value', 'field_key')->toArray()
            : [];

        $candidates = [
            $lead->manager_remark ?? null,
            $lead->remark ?? null,
            $lead->notes ?? null,
            $lead->requirements ?? null,
            $formValues['manager_remark'] ?? null,
            $formValues['remark'] ?? null,
        ];

        foreach ($candidates as $value) {
            if (is_string($value)) {
                $cleaned = $cleanRemark($value);
                if ($cleaned) {
                    return $cleaned;
                }
            }
        }

        return 'No remark added';
    }

    private function resolveNextLeadAction(Lead $lead): array
    {
        $candidates = collect();

        foreach (($lead->followUps ?? collect()) as $followUp) {
            if (($followUp->status ?? '') === 'scheduled' && $followUp->scheduled_at) {
                $candidates->push([
                    'label' => 'Follow Up',
                    'at' => $followUp->scheduled_at,
                ]);
            }
        }

        foreach (($lead->meetings ?? collect()) as $meeting) {
            if (($meeting->status ?? '') === 'scheduled' && $meeting->scheduled_at) {
                $candidates->push([
                    'label' => 'Meeting',
                    'at' => $meeting->scheduled_at,
                ]);
            }
        }

        foreach (($lead->siteVisits ?? collect()) as $siteVisit) {
            if (($siteVisit->status ?? '') === 'scheduled' && $siteVisit->scheduled_at) {
                $candidates->push([
                    'label' => 'Visit',
                    'at' => $siteVisit->scheduled_at,
                ]);
            }
        }

        $nextAction = $candidates
            ->filter(fn ($item) => $item['at'] instanceof Carbon)
            ->sortBy(fn ($item) => $item['at']->timestamp)
            ->first();

        if (!$nextAction) {
            return [
                'label' => null,
                'at' => null,
                'summary' => 'No next action',
            ];
        }

        return [
            'label' => $nextAction['label'],
            'at' => $nextAction['at']->toIso8601String(),
            'summary' => $nextAction['label'] . ' • ' . $nextAction['at']->timezone(config('app.timezone'))->format('d M, h:i A'),
        ];
    }

    private function resolveStructuredNextLeadAction(Lead $lead, ?\Illuminate\Support\Collection $tasks = null): array
    {
        $candidates = collect();
        $now = now();

        foreach (($tasks ?? collect()) as $task) {
            if (!$task->scheduled_at || !in_array((string) $task->status, Task::OPEN_STATUSES, true)) {
                continue;
            }

            $type = $this->resolveTaskNextActionType($task);
            $candidates->push([
                'type' => $type,
                'label' => $this->formatNextActionLabel($type),
                'at' => $task->scheduled_at,
                'url' => url('/leads/' . $lead->id . '?open_task=' . $task->id . '&embed_task_flow=1'),
                'priority' => 1,
            ]);
        }

        foreach (($lead->followUps ?? collect()) as $followUp) {
            if (($followUp->status ?? '') === 'scheduled' && $followUp->scheduled_at) {
                $candidates->push([
                    'type' => 'follow_up',
                    'label' => 'Follow-up',
                    'at' => $followUp->scheduled_at,
                    'url' => url('/leads/' . $lead->id),
                    'priority' => 2,
                ]);
            }
        }

        foreach (($lead->meetings ?? collect()) as $meeting) {
            if (($meeting->status ?? '') === 'scheduled' && $meeting->scheduled_at) {
                $candidates->push([
                    'type' => 'meeting',
                    'label' => 'Meeting',
                    'at' => $meeting->scheduled_at,
                    'url' => url('/sales-manager/meetings'),
                    'priority' => 3,
                ]);
            }
        }

        foreach (($lead->siteVisits ?? collect()) as $siteVisit) {
            if (($siteVisit->status ?? '') === 'scheduled' && $siteVisit->scheduled_at) {
                $candidates->push([
                    'type' => 'visit',
                    'label' => 'Visit',
                    'at' => $siteVisit->scheduled_at,
                    'url' => url('/sales-manager/site-visits'),
                    'priority' => 4,
                ]);
            }
        }

        $nextAction = $candidates
            ->filter(fn ($item) => $item['at'] instanceof Carbon)
            ->sortBy(function ($item) use ($now) {
                $at = $item['at'];
                $bucket = $at->lt($now->copy()->subMinutes(Task::OVERDUE_GRACE_MINUTES))
                    ? 0
                    : ($at->isSameDay($now) ? 1 : 2);

                return sprintf('%d-%d-%d', $bucket, $item['priority'] ?? 9, $at->timestamp);
            })
            ->first();

        if (!$nextAction) {
            return [
                'type' => null,
                'label' => null,
                'at' => null,
                'scheduled_at' => null,
                'is_today' => false,
                'is_overdue' => false,
                'url' => null,
                'summary' => 'No next action',
            ];
        }

        $scheduledAt = $nextAction['at']->copy()->timezone(config('app.timezone'));

        return [
            'type' => $nextAction['type'] ?? null,
            'label' => $nextAction['label'],
            'at' => $nextAction['at']->toIso8601String(),
            'scheduled_at' => $nextAction['at']->toIso8601String(),
            'is_today' => $scheduledAt->isSameDay($now),
            'is_overdue' => $nextAction['at']->lt($now->copy()->subMinutes(Task::OVERDUE_GRACE_MINUTES)),
            'url' => $nextAction['url'] ?? null,
            'summary' => $nextAction['label'] . ' - ' . $scheduledAt->format('d M, h:i A'),
        ];
    }

    private function resolveTaskNextActionType(Task $task): string
    {
        $text = strtolower(trim(
            ($task->title ?? '') . ' ' .
            ($task->description ?? '') . ' ' .
            ($task->notes ?? '')
        ));

        if ($task->getAttribute('follow_up_id') || str_contains($text, 'follow')) {
            return 'follow_up';
        }

        if ($task->getAttribute('meeting_id') || str_contains($text, 'meeting')) {
            return 'meeting';
        }

        if ($task->getAttribute('site_visit_id') || str_contains($text, 'visit')) {
            return 'visit';
        }

        if ((string) $task->type === 'phone_call') {
            return 'call';
        }

        return 'task';
    }

    private function formatNextActionLabel(?string $type): string
    {
        return match ($type) {
            'follow_up' => 'Follow-up',
            'meeting' => 'Meeting',
            'visit' => 'Visit',
            'call' => 'Call',
            'task' => 'Task',
            default => 'No Action',
        };
    }

    private function determineAsmTaskCategory(Task $task, Lead $lead): string
    {
        $prospect = $lead->prospects->sortByDesc('created_at')->first();
        $hasPendingProspect = $prospect && in_array($prospect->verification_status ?? '', ['pending', 'pending_verification'], true);

        $taskText = strtolower(trim(
            ($task->title ?? '') . ' ' .
            ($task->description ?? '') . ' ' .
            ($task->notes ?? '')
        ));

        $isFollowUpTask = str_contains($taskText, 'follow-up call')
            || str_contains($taskText, 'follow up call')
            || str_contains($taskText, 'follow-up scheduled');
        $isCnpRetryTask = str_contains($taskText, 'cnp retry task created')
            || str_contains($taskText, 'cnp rescheduled')
            || str_contains($taskText, 'previous call not picked');
        $isCloserTask = str_contains($taskText, 'closer');
        $isSiteVisitTask = $task->getAttribute('site_visit_id') !== null
            || str_contains($taskText, 'site visit')
            || str_contains($taskText, 'site-visit');
        $isMeetingTask = $task->getAttribute('meeting_id') !== null
            || str_contains($taskText, 'meeting id')
            || str_contains($taskText, 'pre-meeting')
            || (str_contains($taskText, 'meeting') && !$isSiteVisitTask);
        $isProspectTask = !$isFollowUpTask && !$isCnpRetryTask && $hasPendingProspect;
        $isFreshLeadTask = !$isFollowUpTask
            && !$isCnpRetryTask
            && !$isCloserTask
            && !$isSiteVisitTask
            && !$isMeetingTask
            && !$isProspectTask;

        if ($isFreshLeadTask) {
            return 'fresh_lead';
        }
        if ($isFollowUpTask) {
            return 'follow_up';
        }
        if ($isCloserTask) {
            return 'closer';
        }
        if ($isSiteVisitTask) {
            return 'site_visit';
        }
        if ($isMeetingTask) {
            return 'meeting';
        }
        if ($isProspectTask) {
            return 'prospect';
        }

        return 'other';
    }

    public function store(Request $request, LeadDuplicateGuardService $leadDuplicateGuardService)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'required|string|max:30',
            'phone_country_iso' => 'nullable|string|size:2',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'pincode' => 'nullable|string|max:10',
            'source' => 'nullable|in:' . implode(',', array_keys(Lead::sourceOptions())),
            'property_type' => 'nullable|in:apartment,villa,plot,commercial,other',
            'budget_min' => 'nullable|numeric|min:0',
            'budget_max' => 'nullable|numeric|min:0|gte:budget_min',
            'requirements' => 'nullable|string',
            'notes' => 'nullable|string',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        if (app(\App\Services\PhonePrivacyService::class)->shouldMask($user)) {
            unset($validated['phone']);
        }

        $parsedPhone = app(\App\Services\DuplicateDetectionService::class)
            ->parsedLeadPhone($validated['phone'] ?? null, $validated['phone_country_iso'] ?? null);
        if (!$parsedPhone && array_key_exists('phone', $validated)) {
            return response()->json(['message' => 'Enter a valid phone number. Use + country code for non-Indian numbers.'], 422);
        }

        return $leadDuplicateGuardService->withPhoneLock($parsedPhone['normalized'] ?? null, function (string $normalizedPhone) use ($validated, $request, $leadDuplicateGuardService, $parsedPhone) {
            if ($normalizedPhone !== '') {
                $validated['phone'] = $parsedPhone['e164'];
                $validated['normalized_phone'] = $normalizedPhone;
                $validated['phone_country_iso'] = $parsedPhone['country_iso'];
                $existingLead = $leadDuplicateGuardService->findExistingLeadByPhone($normalizedPhone);

                if ($existingLead) {
                    return response()->json([
                        'message' => 'Lead already exists for this phone number.',
                        'errors' => [
                            'phone' => ['Lead already exists for this phone number.'],
                        ],
                    ] + $leadDuplicateGuardService->buildDuplicatePayload($existingLead), 422);
                }
            }

        $validated['created_by'] = $request->user()->id;
        $validated['status'] = 'new';
        $validated['source'] = Lead::normalizeSource($validated['source'] ?? null);

        $lead = Lead::create($validated);

        // Assign lead if provided
        if ($request->has('assigned_to')) {
            $this->assignLead($lead, $request->assigned_to, $request->user()->id);
        }

        return response()->json($lead->load(['creator', 'activeAssignments.assignedTo']), 201);
        });
    }

    public function show(Lead $lead)
    {
        $user = request()->user();

        // Check access
        if (!$this->canAccessLead($user, $lead)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $lead->load([
            'creator',
            'assignments.assignedTo',
            'assignments.assignedBy',
            'siteVisits.assignedTo',
            'followUps.creator',
        ]);

        return response()->json($lead);
    }

    public function update(Request $request, Lead $lead)
    {
        $user = $request->user();

        if (!$this->canAccessLead($user, $lead)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'sometimes|string|max:30',
            'phone_country_iso' => 'nullable|string|size:2',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'pincode' => 'nullable|string|max:10',
            'source' => 'nullable|in:' . implode(',', array_keys(Lead::sourceOptions())),
            'status' => 'sometimes|in:new,fresh_transfer,connected,verified_prospect,meeting_scheduled,meeting_completed,visit_scheduled,visit_done,revisited_scheduled,revisited_completed,closed,dead,junk,not_interested,on_hold',
            'property_type' => 'nullable|in:apartment,villa,plot,commercial,other',
            'budget_min' => 'nullable|numeric|min:0',
            'budget_max' => 'nullable|numeric|min:0|gte:budget_min',
            'requirements' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        if (array_key_exists('phone', $validated)) {
            $parsedPhone = app(\App\Services\DuplicateDetectionService::class)
                ->parsedLeadPhone($validated['phone'], $validated['phone_country_iso'] ?? null);
            if (!$parsedPhone) {
                return response()->json(['message' => 'Enter a valid phone number. Use + country code for non-Indian numbers.'], 422);
            }
            $validated['phone'] = $parsedPhone['e164'];
            $validated['normalized_phone'] = $parsedPhone['normalized'];
            $validated['phone_country_iso'] = $parsedPhone['country_iso'];
        }

        $oldStatus = $lead->status;
        
        // Handle smart override logic for manual status changes
        if (isset($validated['status']) && $oldStatus !== $validated['status']) {
            $newStatus = $validated['status'];
            
            // If manager manually sets to 'dead' or 'closed', disable auto-updates
            if (in_array($newStatus, ['dead', 'closed', 'junk', 'not_interested'])) {
                $lead->disableAutoUpdate();
            }
            // If changing from a terminal status to something else, enable auto-updates
            elseif (in_array($oldStatus, ['dead', 'closed', 'junk', 'not_interested'])) {
                $lead->enableAutoUpdate();
            }
        }
        
        if (isset($validated['source'])) {
            $validated['source'] = Lead::normalizeSource($validated['source']);
        }

        $lead->update($validated);

        if (isset($validated['status']) && in_array($validated['status'], ['junk', 'not_interested'], true)) {
            $lead->forceFill([
                'other_lead_marked_by' => $user->id,
                'other_lead_marked_at' => now(),
                'other_lead_reason' => trim((string) ($validated['notes'] ?? $lead->other_lead_reason ?? '')) ?: null,
            ])->save();
        }

        // Fire event if status changed
        if (isset($validated['status']) && $oldStatus !== $validated['status']) {
            event(new LeadStatusUpdated($lead, $oldStatus, $validated['status']));
        }

        return response()->json($lead->load(['creator', 'activeAssignments.assignedTo']));
    }

    public function assign(Request $request, Lead $lead)
    {
        $user = $request->user();

        if (!$user->canAssignLeads()) {
            return response()->json(['message' => 'Forbidden. You cannot assign leads.'], 403);
        }

        $validated = $request->validate([
            'assigned_to' => 'required|exists:users,id',
            'notes' => ['required', 'string', 'max:1000', 'not_regex:/^\s*$/'],
            'create_calling_task' => 'nullable|boolean',
            'transfer_existing_tasks' => 'nullable|boolean',
        ]);

        $result = $this->assignLead(
            $lead,
            $validated['assigned_to'],
            $user->id,
            trim((string) $validated['notes']),
            (bool) ($validated['create_calling_task'] ?? true),
            (bool) ($validated['transfer_existing_tasks'] ?? true)
        );

        return response()->json([
            'message' => 'Lead assigned successfully',
            'data' => $result,
        ]);
    }

    public function completeOldTask(Request $request, Lead $lead)
    {
        $user = $request->user();

        if (!$this->canCompleteOldTask($user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if (!$this->canAccessLead($user, $lead)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'model_type' => 'required|in:task,telecaller_task',
            'task_id' => 'required|integer',
        ]);

        try {
            $task = $this->resolveOldTaskForLead($lead, $validated['model_type'], (int) $validated['task_id']);
        } catch (ModelNotFoundException $exception) {
            return response()->json(['message' => 'Task not found for this lead'], 404);
        }

        if ($task->status === 'completed' || $task->completed_at !== null) {
            return response()->json([
                'success' => true,
                'message' => 'Task already completed',
            ]);
        }

        if ($validated['model_type'] === 'task') {
            $task->markAsCompleted();
        } else {
            $task->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Task completed successfully',
        ]);
    }

    public function deleteOldTask(Request $request, Lead $lead)
    {
        $user = $request->user();

        if (!$user->isAdmin() && !$user->isCrm()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if (!$this->canAccessLead($user, $lead)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $payload = [
            'model_type' => $request->input('model_type', $request->query('model_type')),
            'task_id' => $request->input('task_id', $request->query('task_id')),
        ];

        $validated = validator($payload, [
            'model_type' => 'required|in:task,telecaller_task',
            'task_id' => 'required|integer',
        ])->validate();

        try {
            $task = $this->resolveOldTaskForLead($lead, $validated['model_type'], (int) $validated['task_id']);
        } catch (ModelNotFoundException $exception) {
            return response()->json(['message' => 'Task not found for this lead'], 404);
        }

        DB::transaction(function () use ($task, $validated, $user) {
            $cleanupService = app(LeadTaskCleanupService::class);

            if ($validated['model_type'] === 'task') {
                $cleanupService->logManualTaskDeletion($task, $user->id);
            } else {
                $cleanupService->logManualTelecallerTaskDeletion($task, $user->id);
            }

            $task->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Task removed successfully',
        ]);
    }

    public function transferOldTask(Request $request, Lead $lead)
    {
        $user = $request->user();

        if (!$user->isAdmin() && !$user->isCrm()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if (!$this->canAccessLead($user, $lead)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'model_type' => 'required|in:task,telecaller_task',
            'task_id' => 'required|integer',
            'assigned_to' => 'required|exists:users,id',
            'notes' => 'nullable|string|max:500',
        ]);

        $assignee = User::where('is_active', true)->find((int) $validated['assigned_to']);
        if (!$assignee) {
            return response()->json(['message' => 'Please select an active user.'], 422);
        }

        try {
            $task = $this->resolveOldTaskForLead($lead, $validated['model_type'], (int) $validated['task_id']);
        } catch (ModelNotFoundException $exception) {
            return response()->json(['message' => 'Task not found for this lead'], 404);
        }

        if ($task->completed_at !== null || !in_array((string) $task->status, ['pending', 'in_progress', 'rescheduled'], true)) {
            return response()->json(['message' => 'Only open tasks can be transferred.'], 422);
        }

        $oldAssigneeId = (int) $task->assigned_to;
        $oldAssigneeName = optional($task->assignedTo)->name ?: 'Unassigned';

        if ($oldAssigneeId === (int) $assignee->id) {
            return response()->json([
                'success' => true,
                'message' => 'Task is already assigned to ' . $assignee->name . '.',
            ]);
        }

        DB::transaction(function () use ($task, $assignee, $user, $validated, $oldAssigneeName) {
            $note = trim((string) ($validated['notes'] ?? ''));
            $transferNote = sprintf(
                '[%s] Task transferred from %s to %s by %s.%s',
                now()->format('d M Y h:i A'),
                $oldAssigneeName,
                $assignee->name,
                $user->name,
                $note !== '' ? ' Note: ' . $note : ''
            );

            $existingNotes = trim((string) ($task->notes ?? ''));
            $task->forceFill([
                'assigned_to' => $assignee->id,
                'notes' => $existingNotes !== '' ? $existingNotes . "\n" . $transferNote : $transferNote,
            ])->save();
        });

        return response()->json([
            'success' => true,
            'message' => 'Task transferred to ' . $assignee->name . '.',
        ]);
    }

    /**
     * Bulk assign leads to a user (single or multiple selection).
     */
    public function bulkAssign(Request $request)
    {
        $user = $request->user();

        if (!$user->canAssignLeads()) {
            return response()->json(['message' => 'Forbidden. You cannot assign leads.'], 403);
        }

        $validated = $request->validate([
            'lead_ids' => 'required|array|min:1',
            'lead_ids.*' => 'required|integer|exists:leads,id',
            'assigned_to' => 'required|exists:users,id',
            'notes' => ['required', 'string', 'max:1000', 'not_regex:/^\s*$/'],
            'create_calling_task' => 'nullable|boolean',
            'transfer_existing_tasks' => 'nullable|boolean',
        ]);

        $leadIds = array_values(array_unique($validated['lead_ids']));
        $assignedTo = (int) $validated['assigned_to'];
        $notes = trim((string) $validated['notes']);
        $createCallingTask = (bool) ($validated['create_calling_task'] ?? true);
        $transferExistingTasks = (bool) ($validated['transfer_existing_tasks'] ?? true);

        $transferred = 0;
        $failed = 0;
        $errors = [];

        foreach ($leadIds as $leadId) {
            try {
                $lead = Lead::find($leadId);
                if (!$lead) {
                    $failed++;
                    $errors[] = ['lead_id' => $leadId, 'error' => 'Lead not found'];
                    continue;
                }
                $this->assignLead($lead, $assignedTo, $user->id, $notes, $createCallingTask, $transferExistingTasks);
                $transferred++;
            } catch (\Exception $e) {
                $failed++;
                $errors[] = ['lead_id' => $leadId, 'error' => $e->getMessage()];
            }
        }

        return response()->json([
            'success' => true,
            'transferred' => $transferred,
            'failed' => $failed,
            'message' => $transferred > 0
                ? "{$transferred} lead(s) transferred successfully." . ($failed > 0 ? " {$failed} failed." : '')
                : 'No leads were transferred.',
            'errors' => $errors,
        ]);
    }

    /**
     * Transfer all leads assigned to a given user to another user (one-click).
     */
    public function transferAllFromUser(Request $request)
    {
        $user = $request->user();

        if (!$user->canAssignLeads()) {
            return response()->json(['message' => 'Forbidden. You cannot assign leads.'], 403);
        }

        $validated = $request->validate([
            'from_user_id' => 'required|exists:users,id',
            'assigned_to' => 'required|exists:users,id',
            'notes' => ['required', 'string', 'max:1000', 'not_regex:/^\s*$/'],
            'create_calling_task' => 'nullable|boolean',
            'transfer_existing_tasks' => 'nullable|boolean',
        ]);

        $fromUserId = (int) $validated['from_user_id'];
        $assignedTo = (int) $validated['assigned_to'];
        $notes = trim((string) $validated['notes']);
        $createCallingTask = (bool) ($validated['create_calling_task'] ?? true);
        $transferExistingTasks = (bool) ($validated['transfer_existing_tasks'] ?? true);

        $leadIds = Lead::whereHas('activeAssignments', function ($q) use ($fromUserId) {
            $q->where('assigned_to', $fromUserId);
        })->pluck('id')->take(1000)->all();

        $transferred = 0;
        $failed = 0;
        $errors = [];

        foreach ($leadIds as $leadId) {
            try {
                $lead = Lead::find($leadId);
                if (!$lead) {
                    $failed++;
                    continue;
                }
                $this->assignLead($lead, $assignedTo, $user->id, $notes, $createCallingTask, $transferExistingTasks);
                $transferred++;
            } catch (\Exception $e) {
                $failed++;
                $errors[] = ['lead_id' => $leadId, 'error' => $e->getMessage()];
            }
        }

        $message = $transferred > 0
            ? "{$transferred} lead(s) transferred successfully." . ($failed > 0 ? " {$failed} failed." : '')
            : 'No leads were transferred.';

        if (count($leadIds) >= 1000) {
            $message .= ' Capped at 1000 leads; more may exist for this user.';
        }

        return response()->json([
            'success' => true,
            'transferred' => $transferred,
            'failed' => $failed,
            'message' => $message,
            'errors' => array_slice($errors, 0, 10),
        ]);
    }

    private function assignLead(
        Lead $lead,
        int $assignedTo,
        int $assignedBy,
        ?string $notes = null,
        bool $createCallingTask = true,
        bool $transferExistingTasks = true
    ): array
    {
        return DB::transaction(function () use ($lead, $assignedTo, $assignedBy, $notes, $createCallingTask, $transferExistingTasks) {
            $lead = Lead::query()
                ->whereKey($lead->id)
                ->lockForUpdate()
                ->firstOrFail();

            $activeAssignments = $lead->assignments()
                ->where('is_active', true)
                ->lockForUpdate()
                ->get(['assigned_to']);

            $activeOwnerIds = $activeAssignments
                ->pluck('assigned_to')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            if ($activeOwnerIds->count() === 1 && (int) $activeOwnerIds->first() === (int) $assignedTo) {
                return [
                    'assignment_id' => optional($lead->activeAssignments()->first())->id,
                    'old_owner_ids' => [],
                    'transferred_counts' => [
                        'telecaller_tasks' => 0,
                        'manager_tasks' => 0,
                        'crm_assignments' => 0,
                    ],
                    'created_task' => false,
                    'noop' => true,
                ];
            }

            $oldOwnerIds = $activeAssignments
                ->pluck('assigned_to')
                ->filter(fn ($id) => (int) $id !== (int) $assignedTo)
                ->unique()
                ->values();

            // Deactivate existing assignments
            $lead->assignments()->where('is_active', true)->update([
                'is_active' => false,
                'unassigned_at' => now(),
            ]);

            // Create new assignment
            LeadAssignment::create([
                'lead_id' => $lead->id,
                'assigned_to' => $assignedTo,
                'assigned_by' => $assignedBy,
                'assignment_type' => 'primary',
                'notes' => $notes,
                'assigned_at' => now(),
                'is_active' => true,
            ]);

            if ($oldOwnerIds->isNotEmpty()) {
                $lead->markAsFreshTransfer((int) $oldOwnerIds->first(), $assignedTo, $assignedBy, $notes);
            }

            $transferredTaskCounts = [
                'telecaller_tasks' => 0,
                'manager_tasks' => 0,
                'crm_assignments' => 0,
            ];

            if ($oldOwnerIds->isNotEmpty()) {
                $cleanupService = app(LeadTaskCleanupService::class);
                foreach ($oldOwnerIds as $oldOwnerId) {
                    $cleanup = $cleanupService->deleteTasksForLeadAndOwner(
                        $lead->id,
                        (int) $oldOwnerId,
                        $assignedBy,
                        'lead_transferred'
                    );

                    $transferredTaskCounts['telecaller_tasks'] += $cleanup['telecaller_tasks'] ?? 0;
                    $transferredTaskCounts['manager_tasks'] += $cleanup['tasks'] ?? 0;
                    $transferredTaskCounts['crm_assignments'] += $cleanup['crm_assignments'] ?? 0;
                }
            }

            $createdNewTask = false;
            $shouldEnsureOwnerTask = $createCallingTask || ($transferExistingTasks && $oldOwnerIds->isNotEmpty());

            if ($shouldEnsureOwnerTask) {
                $beforeTelecallerCount = TelecallerTask::where('lead_id', $lead->id)
                    ->where('assigned_to', $assignedTo)
                    ->where('task_type', 'calling')
                    ->whereIn('status', ['pending', 'in_progress', 'rescheduled'])
                    ->count();

                $beforeManagerCount = Task::where('lead_id', $lead->id)
                    ->where('assigned_to', $assignedTo)
                    ->where('type', 'phone_call')
                    ->whereIn('status', ['pending', 'in_progress', 'rescheduled'])
                    ->count();

                if ($createCallingTask) {
                    try {
                        event(new LeadAssigned($lead, $assignedTo, $assignedBy));
                    } catch (\Throwable $eventError) {
                        Log::warning("LeadController: LeadAssigned dispatch failed for lead {$lead->id}", [
                            'lead_id' => $lead->id,
                            'assigned_to' => $assignedTo,
                            'error' => $eventError->getMessage(),
                        ]);
                    }
                }

                $afterTelecallerCount = TelecallerTask::where('lead_id', $lead->id)
                    ->where('assigned_to', $assignedTo)
                    ->where('task_type', 'calling')
                    ->whereIn('status', ['pending', 'in_progress', 'rescheduled'])
                    ->count();

                $afterManagerCount = Task::where('lead_id', $lead->id)
                    ->where('assigned_to', $assignedTo)
                    ->where('type', 'phone_call')
                    ->whereIn('status', ['pending', 'in_progress', 'rescheduled'])
                    ->count();

                $createdNewTask = $afterTelecallerCount > $beforeTelecallerCount || $afterManagerCount > $beforeManagerCount;

                // Fallback: create task if no open task exists after event processing.
                if (!$createdNewTask && $afterTelecallerCount === 0 && $afterManagerCount === 0) {
                    $assignee = User::with('role')->find($assignedTo);
                    if ($assignee) {
                        $taskResult = app(LeadOwnerTaskService::class)->ensureOpenTaskForOwner($lead, $assignee, $assignedBy, $notes);
                        $createdNewTask = (bool) ($taskResult['created'] ?? false);
                    }
                }
            }

            return [
                'lead_id' => $lead->id,
                'old_owner_ids' => $oldOwnerIds->values()->all(),
                'new_owner_id' => $assignedTo,
                'transfer_existing_tasks' => $transferExistingTasks,
                'create_calling_task' => $createCallingTask,
                'transferred_task_counts' => $transferredTaskCounts,
                'created_new_task' => $createdNewTask,
            ];
        });
    }

    /**
     * Get leads pending verification
     */
    public function pendingVerifications(Request $request)
    {
        $user = $request->user();

        // Only Admin or CRM can view pending verifications
        if (!$user->canManageUsers()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $leads = Lead::where('needs_verification', true)
            ->with(['verificationRequestedBy', 'pendingManager', 'activeAssignments.assignedTo'])
            ->latest('verification_requested_at')
            ->paginate(min(100, max(1, (int) $request->get('per_page', 15))));

        return response()->json($leads);
    }

    /**
     * Verify and transfer lead to new manager
     */
    public function verifyLead(Request $request, Lead $lead)
    {
        $user = $request->user();

        // Only Admin or CRM can verify leads
        if (!$user->canManageUsers()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if (!$lead->needs_verification) {
            return response()->json(['message' => 'Lead does not need verification'], 400);
        }

        $validated = $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        $leadTransferService = app(LeadTransferService::class);
        $success = $leadTransferService->verifyAndTransferLead($lead, $user->id, $validated['notes'] ?? null);

        if ($success) {
            return response()->json([
                'message' => 'Lead verified and transferred successfully',
                'lead' => $lead->fresh()->load(['verifiedBy', 'activeAssignments.assignedTo'])
            ]);
        }

        return response()->json(['message' => 'Failed to verify and transfer lead'], 500);
    }

    /**
     * Reject verification and keep lead with current assignment
     */
    public function rejectVerification(Request $request, Lead $lead)
    {
        $user = $request->user();

        // Only Admin or CRM can reject verifications
        if (!$user->canManageUsers()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if (!$lead->needs_verification) {
            return response()->json(['message' => 'Lead does not need verification'], 400);
        }

        $validated = $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        $leadTransferService = app(LeadTransferService::class);
        $success = $leadTransferService->rejectVerification($lead, $user->id, $validated['notes'] ?? null);

        if ($success) {
            return response()->json([
                'message' => 'Verification rejected, lead kept with current assignment',
                'lead' => $lead->fresh()->load(['verifiedBy', 'activeAssignments.assignedTo'])
            ]);
        }

        return response()->json(['message' => 'Failed to reject verification'], 500);
    }

    public function markCloserDraft(Request $request, Lead $lead)
    {
        $user = $request->user();

        if (!$this->canAccessLead($user, $lead)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if (!(
            $user->isAdmin()
            || $user->isCrm()
            || $user->isSalesHead()
            || $user->isSalesManager()
            || $user->isSeniorManager()
            || $user->isAssistantSalesManager()
            || $user->isSalesExecutive()
        )) {
            return response()->json(['message' => 'You are not allowed to move this lead to closer draft.'], 403);
        }

        $visit = DB::transaction(function () use ($lead, $user) {
            $targetAssignedTo = $lead->activeAssignments()
                ->where('is_active', true)
                ->latest('assigned_at')
                ->value('assigned_to') ?: $user->id;

            $existingCloserVisit = SiteVisit::query()
                ->where('lead_id', $lead->id)
                ->whereIn('closer_status', ['draft', 'pending_crm', 'correction_required', 'approved', 'verified'])
                ->where(function ($query) {
                    $query->whereNull('is_dead')->orWhere('is_dead', false);
                })
                ->latest('updated_at')
                ->lockForUpdate()
                ->first();

            if ($existingCloserVisit) {
                $updates = [];
                if ((int) $existingCloserVisit->assigned_to !== (int) $targetAssignedTo) {
                    $updates['assigned_to'] = $targetAssignedTo;
                }
                if ($existingCloserVisit->status !== 'completed') {
                    $updates['status'] = 'completed';
                    $updates['completed_at'] = $existingCloserVisit->completed_at ?: now();
                }
                if ($existingCloserVisit->verification_status !== 'verified') {
                    $updates['verification_status'] = 'verified';
                    $updates['verified_by'] = $existingCloserVisit->verified_by ?: $user->id;
                    $updates['verified_at'] = $existingCloserVisit->verified_at ?: now();
                }
                if ($updates !== []) {
                    $existingCloserVisit->fill($updates)->save();
                }

                app(LeadActiveWorkflowService::class)->moveLeadToWorkflow($lead->id, 'closer', $existingCloserVisit->id);
                return $existingCloserVisit->fresh(['lead', 'creator', 'assignedTo']);
            }

            $siteVisit = SiteVisit::query()
                ->where('lead_id', $lead->id)
                ->where(function ($query) {
                    $query->whereNull('is_dead')->orWhere('is_dead', false);
                })
                ->latest('updated_at')
                ->lockForUpdate()
                ->first();

            $now = now();

            $payload = [
                'created_by' => $siteVisit?->created_by ?: $user->id,
                'assigned_to' => $targetAssignedTo,
                'scheduled_at' => $siteVisit?->scheduled_at ?: $now,
                'completed_at' => $siteVisit?->completed_at ?: $now,
                'status' => 'completed',
                'verification_status' => 'verified',
                'verified_by' => $siteVisit?->verified_by ?: $user->id,
                'verified_at' => $siteVisit?->verified_at ?: $now,
                'closer_status' => 'draft',
                'converted_to_closer_at' => $siteVisit?->converted_to_closer_at ?: $now,
                'customer_name' => $siteVisit?->customer_name ?: $lead->name,
                'phone' => $siteVisit?->phone ?: $lead->phone,
                'date_of_visit' => $siteVisit?->date_of_visit ?: $now->toDateString(),
                'visit_notes' => trim((string) ($siteVisit?->visit_notes ?? '') . "\nMarked as closer draft from lead quick action by {$user->name}."),
                'lead_type' => $siteVisit?->lead_type ?: 'Prospect',
            ];

            if ($siteVisit) {
                $siteVisit->fill($payload);
                $siteVisit->save();
            } else {
                $siteVisit = SiteVisit::create(array_merge($payload, [
                    'lead_id' => $lead->id,
                ]));
            }

            app(LeadActiveWorkflowService::class)->moveLeadToWorkflow($lead->id, 'closer', $siteVisit->id);

            return $siteVisit->fresh(['lead', 'creator', 'assignedTo']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Lead moved to closer draft successfully.',
            'data' => $visit,
        ]);
    }

    private function canAccessLead($user, Lead $lead): bool
    {
        if ($user->isAdmin() || $user->isCrm()) {
            return true;
        }

        if ($user->isSalesHead()) {
            $teamMemberIds = $user->getAllTeamMemberIds();

            return !empty($teamMemberIds) && (
                $lead->isAssignedToAnyUser($teamMemberIds) ||
                $lead->isVisibleViaProspectFallback($teamMemberIds)
            );
        }

        // Check if lead is directly assigned to user
        if ($lead->isAssignedToUser($user->id)) {
            return true;
        }

        // Senior Manager, Manager, Assistant Sales Manager: team's leads
        if ($user->isSalesManager() || $user->isSeniorManager() || $user->isAssistantSalesManager()) {
            $teamMemberIds = $user->teamMembers()->pluck('id');
            if ($teamMemberIds->isNotEmpty() && $lead->isAssignedToAnyUser($teamMemberIds)) {
                return true;
            }
            if ($teamMemberIds->isNotEmpty()) {
                return $lead->isVisibleViaProspectFallback($teamMemberIds, function ($prospectQuery) {
                    $prospectQuery->whereIn('verification_status', ['verified', 'approved']);
                });
            }
        }

        if ($user->isSalesExecutive()) {
            return $lead->isVisibleViaProspectFallback([$user->id]);
        }

        return false;
    }

    private function canCompleteOldTask(User $user): bool
    {
        return $user->isAdmin()
            || $user->isCrm()
            || $user->isSalesHead()
            || $user->isSalesManager()
            || $user->isSeniorManager()
            || $user->isAssistantSalesManager()
            || $user->isSalesExecutive()
            || $user->isTelecaller();
    }

    private function resolveOldTaskForLead(Lead $lead, string $modelType, int $taskId)
    {
        if ($modelType === 'task') {
            return Task::query()
                ->where('id', $taskId)
                ->where('lead_id', $lead->id)
                ->firstOrFail();
        }

        return TelecallerTask::query()
            ->where('id', $taskId)
            ->where('lead_id', $lead->id)
            ->firstOrFail();
    }
}

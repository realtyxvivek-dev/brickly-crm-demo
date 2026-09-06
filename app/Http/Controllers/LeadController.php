<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\AppNotification;
use App\Models\CallLog;
use App\Models\User;
use App\Models\Project;
use App\Models\LeadAssignment;
use App\Models\InsightSheetCellAudit;
use App\Models\InsightSheetCellOverride;
use App\Models\McubeOutboundAttempt;
use App\Models\McubeWebhookLog;
use App\Models\Meeting;
use App\Models\Prospect;
use App\Models\Role;
use App\Models\SiteVisit;
use App\Models\Task;
use App\Models\TelecallerTask;
use App\Events\LeadAssigned;
use App\Services\FormDetectionService;
use App\Services\DynamicFormService;
use App\Services\BulkCallingTaskService;
use App\Services\LeadActivityService;
use App\Services\LeadAssignmentService;
use App\Services\LeadDuplicateGuardService;
use App\Services\LeadOwnerTaskService;
use App\Services\LeadTaskCleanupService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class LeadController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $canViewCnpQuarantine = $this->canViewCnpQuarantine($user);
        $selectedLeadFlag = $canViewCnpQuarantine ? (string) $request->input('lead_flag', '') : '';
        $prospectColumns = $this->selectExistingColumns('prospects', ['id', 'lead_id', 'budget', 'preferred_location', 'size', 'purpose', 'possession', 'remark', 'employee_remark', 'manager_remark', 'notes', 'updated_at', 'created_at']);
        $prospectColumns = array_values(array_unique(array_merge($prospectColumns, $this->selectExistingColumns('prospects', ['verification_status', 'verified_at']))));
        $meetingColumns = $this->selectExistingColumns('meetings', ['id', 'lead_id', 'budget_range', 'property_type', 'location', 'status', 'scheduled_at', 'completed_at', 'verified_at', 'is_dead', 'marked_dead_at', 'updated_at', 'created_at']);
        $siteVisitColumns = $this->selectExistingColumns('site_visits', ['id', 'lead_id', 'status', 'scheduled_at', 'completed_at', 'verification_status', 'verified_at', 'closer_status', 'closer_verified_at', 'converted_to_closer_at', 'is_dead', 'marked_dead_at', 'updated_at', 'created_at']);
        $listRelations = [
            'creator',
            'formFieldValues:lead_id,field_key,field_value',
            'latestImportedLead.importBatch',
            'latestAssignment.assignedTo',
            'latestProspect' => function ($relation) use ($prospectColumns) {
                $relation->select($this->qualifyColumns('prospects', $prospectColumns))
                    ->with('interestedProjects:id,name');
            },
            'latestMeeting' => fn ($relation) => $relation->select($this->qualifyColumns('meetings', $meetingColumns)),
            'latestSiteVisit' => fn ($relation) => $relation->select($this->qualifyColumns('site_visits', $siteVisitColumns)),
            'currentAssignment.assignedTo',
        ];
        $query = Lead::with($listRelations);
        $query->visibleInAllLeadsInventory();

        // Sales Head specific filtering - only show verified prospects, verified site visits, and closed leads
        if ($user->isSalesHead()) {
            // Get team member IDs
            $teamMemberIds = $user->getAllTeamMemberIds();
            
            // Only show leads assigned to team members
            if (!empty($teamMemberIds)) {
                $query->whereHas('activeAssignments', function ($q) use ($teamMemberIds) {
                    $q->whereIn('assigned_to', $teamMemberIds);
                });
            } else {
                // If no team members, show empty
                $query->whereRaw('1 = 0');
            }
            
            // Filter leads that are:
            // 1. Verified prospects (status = 'verified_prospect')
            // 2. Verified site visits (status = 'visit_done' or 'revisited_completed' AND has verified site visit)
            // 3. Closed leads (status = 'closed' or 'dead')
            
            $query->where(function($q) {
                // Verified prospects
                $q->where('status', 'verified_prospect')
                // Verified site visits (visit_done or revisited_completed with verified site visit)
                ->orWhere(function($subQ) {
                    $subQ->whereIn('status', ['visit_done', 'revisited_completed'])
                         ->whereHas('siteVisits', function($visitQ) {
                             $visitQ->where('status', 'completed')
                                    ->whereNotNull('verified_at');
                         });
                })
                // Closed leads
                ->orWhereIn('status', ['closed', 'dead']);
            });
        }

        // Senior Manager: by default only own leads; subordinate leads only when explicitly filtered
        if ($user->isSeniorManager()) {
            $teamMemberIds = $user->teamMembers()->pluck('id');

            $requestedAssignedTo = $request->assigned_to ?? $request->user_id;
            if ($requestedAssignedTo !== null && $requestedAssignedTo !== '') {
                $requestedAssignedTo = (int) $requestedAssignedTo;
                $allowedIds = $teamMemberIds->merge([$user->id])->map(fn ($id) => (int) $id)->unique()->values();

                if (!$allowedIds->contains($requestedAssignedTo)) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->where(function ($q) use ($requestedAssignedTo) {
                        $q->whereAssignedToUsers([$requestedAssignedTo])
                            ->orWhere(function ($fallbackQuery) use ($requestedAssignedTo) {
                                $fallbackQuery->whereVisibleViaProspectFallback([$requestedAssignedTo], function ($prospectQuery) {
                                    $prospectQuery->whereIn('verification_status', ['verified', 'approved']);
                                });
                            });
                    });
                }
            } else {
                $query->where(function ($q) use ($user) {
                    $q->whereAssignedToUsers([$user->id])
                        ->orWhere(function ($fallbackQuery) use ($user) {
                            $fallbackQuery->whereVisibleViaProspectFallback([$user->id], function ($prospectQuery) {
                                $prospectQuery->whereIn('verification_status', ['verified', 'approved']);
                            });
                        });
                });
            }
        }

        // Sales Manager and Assistant Sales Manager: leads assigned to them or their team
        if ($user->isSalesManager() || $user->isAssistantSalesManager()) {
            $teamMemberIds = $user->teamMembers()->pluck('id');
            if ($teamMemberIds->isEmpty()) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function ($q) use ($user, $teamMemberIds) {
                    $managerAndTeamIds = $teamMemberIds->merge([$user->id])->unique()->values();

                    $q->whereAssignedToUsers($managerAndTeamIds)
                        ->orWhere(function ($fallbackQuery) use ($teamMemberIds) {
                            $fallbackQuery->whereVisibleViaProspectFallback($teamMemberIds, function ($prospectQuery) {
                                $prospectQuery->whereIn('verification_status', ['verified', 'approved']);
                            });
                        });
                });
            }
        }

        if ($user->isSalesExecutive()) {
            $query->where(function ($visibilityQuery) use ($user) {
                $visibilityQuery->whereAssignedToUsers([$user->id])
                    ->orWhere(function ($fallbackQuery) use ($user) {
                        $fallbackQuery->whereVisibleViaProspectFallback([$user->id]);
                    });
            });
        }

        $this->applyHiringLeadVisibilityFilter($query, $request, $user);

        // Search functionality
        if ($request->has('search')) {
            $query->searchText((string) $request->search);
        }

        // Filter by source
        if ($request->filled('source')) {
            $this->applySourceFilter($query, (string) $request->source);
        }

        $selectedMetaFormId = $this->resolveEnabledMetaFormId($request->input('meta_form_id'));
        if ($selectedMetaFormId !== null) {
            $this->applyMetaFormFilter($query, $selectedMetaFormId);
        }

        $this->applyMetaOutcomeFilter($query, (string) $request->input('meta_outcome', ''));

        [$startDate, $endDate] = $this->resolveLeadDateRange($request);
        if ($startDate && $endDate) {
            $this->applyLeadIndexDateRangeFilter($query, $request, $startDate, $endDate);
        }

        $this->applyPipelineStageFilter($query, $request);

        // Filter by lead type (Prospect, Visit, Revisit, Meeting, Closer)
        if ($request->has('lead_type_filter') && $request->lead_type_filter) {
            $type = $request->lead_type_filter;
            
            if ($type === 'prospect') {
                // Only verified prospects - leads with status verified_prospect OR leads with verified/approved prospects
                $query->where(function($q) {
                    $q->where('status', 'verified_prospect')
                      ->orWhereHas('prospects', function($subQ) {
                          $subQ->whereIn('verification_status', ['verified', 'approved']);
                      });
                });
            } elseif ($type === 'visit') {
                // Site visits with lead_type = 'New Visit'
                $query->where(function($q) {
                    $q->whereIn('status', ['visit_scheduled', 'visit_done'])
                      ->orWhereHas('siteVisits', function($subQ) {
                          $subQ->where('lead_type', 'New Visit');
                      });
                });
            } elseif ($type === 'revisit') {
                // Revisits with lead_type = 'Revisited'
                $query->where(function($q) {
                    $q->whereIn('status', ['revisited_scheduled', 'revisited_completed'])
                      ->orWhereHas('siteVisits', function($subQ) {
                          $subQ->where('lead_type', 'Revisited');
                      });
                });
            } elseif ($type === 'meeting') {
                // Meetings - leads with meeting_scheduled or meeting_completed status OR leads that have meetings
                $query->where(function($q) {
                    $q->whereIn('status', ['meeting_scheduled', 'meeting_completed'])
                      ->orWhereHas('meetings');
                });
            } elseif ($type === 'closer') {
                // Closer requests - site visits with closer_status pending or not null
                $query->whereHas('siteVisits', function($subQ) {
                    $subQ->where(function($closerQ) {
                        $closerQ->where('closer_status', 'pending')
                                ->orWhereNotNull('closer_status');
                    });
                });
            }
        }

        $this->applyDemandInsightFilters($query, $request);

        $statsQuery = clone $query;

        $selectedStatuses = $this->normalizeLeadStatusFilters($request);
        if ($canViewCnpQuarantine && in_array('cnp_quarantine', $selectedStatuses, true)) {
            $selectedLeadFlag = 'cnp_quarantine';
            $selectedStatuses = array_values(array_diff($selectedStatuses, ['cnp_quarantine']));
        }

        if ($selectedLeadFlag === 'cnp_quarantine') {
            $query->whereNotNull('cnp_quarantined_at')
                ->whereNull('cnp_quarantine_cleared_at');
        }

        // Filter by user (telecaller)
        // Support both 'assigned_to' (leads index filter) and 'user_id'
        $filterUserId = $request->assigned_to ?? $request->user_id;
        if ($filterUserId === 'unassigned') {
            $query->whereDoesntHave('activeAssignments');
        } elseif ($filterUserId) {
            $query->whereHas('activeAssignments', function($q) use ($filterUserId) {
                $q->where('assigned_to', $filterUserId)->where('is_active', true);
            });
        }

        $this->applyLeadStatusFilters($query, $selectedStatuses);

        $taskCreationFilter = trim((string) $request->input('task_creation', ''));
        if (($user->isAdmin() || $user->isCrm()) && in_array($taskCreationFilter, ['created', 'not_created'], true)) {
            $this->applyTaskCreationFilter($query, $taskCreationFilter);
        }

        $view = $request->get('view', 'cards');
        $perPage = 15;
        if ($user->isAdmin() || $user->isCrm()) {
            $requestedPerPage = min(5000, max(50, (int) $request->get('per_page', 500)));
            $perPage = in_array($requestedPerPage, [50, 100, 200, 500, 1000, 5000], true)
                ? $requestedPerPage
                : 500;
        }
        $leads = $query->latest()->paginate($perPage)->withQueryString();
        $this->applyDisplayStatuses($leads->getCollection());
        $this->applyLatestRemarks($leads->getCollection());

        $statuses = ['new', 'fresh_transfer', 'connected', 'verified_prospect', 'meeting_scheduled', 'meeting_completed', 'visit_scheduled', 'visit_done', 'revisited_scheduled', 'revisited_completed', 'closed', 'dead', 'junk', 'not_interested', 'on_hold'];

        // Filter by User dropdown: keep admin/crm/HR visible; only exclude roles that should never own lead filters here.
        $excludeRolesForFilter = [Role::FINANCE_MANAGER];
        $filterUsers = User::where('is_active', true)
            ->whereHas('role', function ($q) use ($excludeRolesForFilter) {
                $q->whereNotIn('slug', $excludeRolesForFilter);
            })
            ->with('role')
            ->orderBy('name');

        if ($user->isSeniorManager()) {
            $allowedFilterUserIds = $user->teamMembers()->pluck('id')->merge([$user->id])->unique()->values();
            $filterUsers->whereIn('id', $allowedFilterUserIds);
        }

        $filterUsers = $filterUsers->get();
        $metaFormOptions = $this->metaFormFilterOptions();

        $ownerTransferUsers = collect();
        $hrHiringUsers = collect();
        if ($user->isAdmin() || $user->isCrm()) {
            $ownerTransferUsers = User::where('is_active', true)
                ->whereHas('role', function ($q) {
                    $q->whereIn('slug', [
                        Role::SALES_MANAGER,
                        Role::SENIOR_MANAGER,
                        Role::ASSISTANT_SALES_MANAGER,
                        Role::SALES_EXECUTIVE,
                    ]);
                })
                ->with('role')
                ->orderBy('name')
                ->get();

            $hrHiringUsers = User::where('is_active', true)
                ->whereHas('role', fn ($q) => $q->whereIn('slug', [Role::HR_MANAGER, Role::JUNIOR_HR]))
                ->with('role')
                ->orderBy('name')
                ->get();
        }

        $leadStats = Cache::remember(
            $this->leadStatsCacheKey($request, $user),
            now()->addSeconds(60),
            fn () => $this->aggregateLeadStats($statsQuery)
        );

        return view('leads.index', compact('leads', 'statuses', 'filterUsers', 'ownerTransferUsers', 'hrHiringUsers', 'view', 'leadStats', 'canViewCnpQuarantine', 'selectedLeadFlag', 'metaFormOptions', 'selectedMetaFormId', 'selectedStatuses'));
    }

    public function exportSelected(Request $request)
    {
        $leadIds = collect($request->input('lead_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($leadIds->isEmpty()) {
            return back()->with('error', 'Export ke liye at least one lead select karo.');
        }

        $allowedColumns = $this->leadExportColumns();
        $defaultColumns = ['name', 'phone', 'status', 'source', 'category', 'type', 'budget', 'location', 'assigned_to', 'last_assigned_to', 'created_at', 'last_remark'];
        $columns = collect($request->input('columns', []))
            ->map(fn ($column) => (string) $column)
            ->filter(fn ($column) => array_key_exists($column, $allowedColumns))
            ->unique()
            ->values()
            ->all();

        if (empty($columns)) {
            $columns = $defaultColumns;
        }

        $columns = collect(['name', 'phone'])
            ->merge($columns)
            ->unique()
            ->values()
            ->all();

        $managerTaskColumns = $this->selectExistingColumns('tasks', ['id', 'lead_id', 'outcome_remark', 'updated_at', 'created_at']);
        $followUpColumns = $this->selectExistingColumns('follow_ups', ['id', 'lead_id', 'notes', 'updated_at', 'created_at']);
        $prospectRemarkColumns = $this->selectExistingColumns('prospects', ['id', 'lead_id', 'remark', 'employee_remark', 'manager_remark', 'notes', 'updated_at', 'created_at']);

        $leads = Lead::with([
            'creator',
            'formFieldValues:lead_id,field_key,field_value',
            'latestImportedLead.importBatch',
            'latestAssignment.assignedTo',
            'managerTasks' => function ($relation) use ($managerTaskColumns) {
                $relation->select($managerTaskColumns)
                    ->orderByDesc('updated_at')
                    ->orderByDesc('id');
            },
            'followUps' => function ($relation) use ($followUpColumns) {
                $relation->select($followUpColumns)
                    ->orderByDesc('updated_at')
                    ->orderByDesc('id');
            },
            'prospects' => function ($relation) use ($prospectRemarkColumns) {
                $relation->select($prospectRemarkColumns)
                    ->orderByDesc('updated_at')
                    ->orderByDesc('id');
            },
            'activeAssignments' => function ($relation) {
                $relation->with('assignedTo')
                    ->orderByDesc('assigned_at')
                    ->orderByDesc('id');
            },
        ])
            ->visibleInAllLeadsInventory()
            ->whereIn('id', $leadIds)
            ->get()
            ->sortBy(fn (Lead $lead) => $leadIds->search($lead->id))
            ->values();

        $this->applyDisplayStatuses($leads);
        $this->applyLatestRemarks($leads);

        $filename = 'leads-export-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($leads, $columns, $allowedColumns) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, array_map(fn ($column) => $allowedColumns[$column], $columns));

            foreach ($leads as $lead) {
                fputcsv($handle, array_map(
                    fn ($column) => $this->resolveLeadExportValue($lead, $column),
                    $columns
                ));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function storeSource(Request $request)
    {
        $user = $request->user();
        if (!$user || (!$user->isAdmin() && !$user->isCrm())) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'type' => ['nullable', 'string', Rule::in(array_keys(LeadSource::TYPE_OPTIONS))],
        ]);

        $name = trim($validated['name']);
        $key = LeadSource::normalizeKey($name);

        if ($key === '') {
            return response()->json([
                'message' => 'Please enter a valid source name.',
                'errors' => ['name' => ['Please enter a valid source name.']],
            ], 422);
        }

        if (LeadSource::query()->where('key', $key)->exists()) {
            return response()->json([
                'message' => 'This lead source already exists.',
                'errors' => ['name' => ['This lead source already exists.']],
            ], 422);
        }

        $nextSortOrder = ((int) LeadSource::query()->max('sort_order')) + 10;

        $source = LeadSource::create([
            'name' => $name,
            'key' => $key,
            'type' => $validated['type'] ?? 'other',
            'is_active' => true,
            'is_system' => false,
            'sort_order' => $nextSortOrder,
            'created_by' => $user->id,
        ]);

        Lead::forgetResolvedSourceOptions();

        return response()->json([
            'success' => true,
            'message' => 'Lead source created successfully.',
            'source' => [
                'id' => $source->id,
                'name' => $source->name,
                'key' => $source->key,
                'type' => $source->type,
            ],
            'options' => Lead::sourceOptions(),
        ]);
    }

    private function leadExportColumns(): array
    {
        return [
            'name' => 'Name',
            'phone' => 'Phone',
            'email' => 'Email',
            'status' => 'Status',
            'source' => 'Source',
            'category' => 'Category',
            'type' => 'Type',
            'budget' => 'Budget',
            'location' => 'Location',
            'assigned_to' => 'Assigned To',
            'last_assigned_to' => 'Last Assigned To',
            'created_at' => 'Created',
            'last_remark' => 'Last Remark',
        ];
    }

    private function resolveLeadExportValue(Lead $lead, string $column): string
    {
        $formValues = $lead->relationLoaded('formFieldValues')
            ? $lead->formFieldValues->pluck('field_value', 'field_key')
            : collect();

        $pick = function (array $keys) use ($lead, $formValues): string {
            foreach ($keys as $key) {
                $value = $lead->{$key} ?? $formValues->get($key);
                if ($value !== null && trim((string) $value) !== '') {
                    return trim((string) $value);
                }
            }

            return '';
        };

        return match ($column) {
            'name' => (string) ($lead->name ?? ''),
            'phone' => (string) ($lead->phone ?? ''),
            'email' => (string) ($lead->email ?? ''),
            'status' => $lead->display_status_label ?? ucfirst(str_replace('_', ' ', (string) ($lead->display_status ?? $lead->status ?? ''))),
            'source' => $lead->source_label,
            'category' => $pick(['category', 'property_category']),
            'type' => $pick(['property_type', 'type', 'apartment_type', 'property_kind']),
            'budget' => $pick(['budget', 'budget_range', 'apartment_budget']),
            'location' => $pick(['preferred_location', 'location', 'city', 'living_city']),
            'assigned_to' => (string) ($lead->activeAssignments->first()?->assignedTo?->name ?? ''),
            'last_assigned_to' => (string) ($lead->latestAssignment?->assignedTo?->name ?? ''),
            'created_at' => optional($lead->display_created_at ?? $lead->created_at)->format('Y-m-d H:i:s') ?? '',
            'last_remark' => (string) ($lead->last_remark ?? $this->resolveLatestRemark($lead) ?? ''),
            default => '',
        };
    }

    private function applyLatestRemarks($leads): void
    {
        $managerTaskColumns = $this->selectExistingColumns('tasks', ['id', 'lead_id', 'outcome_remark', 'updated_at', 'created_at']);
        $followUpColumns = $this->selectExistingColumns('follow_ups', ['id', 'lead_id', 'notes', 'updated_at', 'created_at']);
        $prospectColumns = $this->selectExistingColumns('prospects', ['id', 'lead_id', 'remark', 'employee_remark', 'manager_remark', 'notes', 'updated_at', 'created_at']);

        $relations = [];
        if (in_array('outcome_remark', $managerTaskColumns, true)) {
            $relations['latestManagerTaskRemark'] = fn ($relation) => $relation->select($this->qualifyColumns('tasks', $managerTaskColumns));
        }
        if (in_array('notes', $followUpColumns, true)) {
            $relations['latestFollowUpRemark'] = fn ($relation) => $relation->select($this->qualifyColumns('follow_ups', $followUpColumns));
        }
        if (count(array_intersect(['remark', 'employee_remark', 'manager_remark', 'notes'], $prospectColumns)) === 4) {
            $relations['latestProspectRemark'] = fn ($relation) => $relation->select($this->qualifyColumns('prospects', $prospectColumns));
        }

        $leads->loadMissing($relations);

        $leads->each(function (Lead $lead) {
            $lead->setAttribute('last_remark', $this->resolveLatestRemark($lead));
        });
    }

    private function resolveLatestRemark(Lead $lead): ?string
    {
        $remarkCandidates = collect();

        if ($lead->relationLoaded('managerTasks')) {
            foreach ($lead->managerTasks as $task) {
                $this->pushLatestRemarkCandidate($remarkCandidates, $task, ['outcome_remark']);
            }
        } elseif ($lead->relationLoaded('latestManagerTaskRemark') && $lead->latestManagerTaskRemark) {
            $this->pushLatestRemarkCandidate($remarkCandidates, $lead->latestManagerTaskRemark, ['outcome_remark']);
        }

        if ($lead->relationLoaded('followUps')) {
            foreach ($lead->followUps as $followUp) {
                $this->pushLatestRemarkCandidate($remarkCandidates, $followUp, ['notes']);
            }
        } elseif ($lead->relationLoaded('latestFollowUpRemark') && $lead->latestFollowUpRemark) {
            $this->pushLatestRemarkCandidate($remarkCandidates, $lead->latestFollowUpRemark, ['notes']);
        }

        if ($lead->relationLoaded('prospects')) {
            foreach ($lead->prospects as $prospect) {
                $this->pushLatestRemarkCandidate($remarkCandidates, $prospect, ['manager_remark', 'employee_remark', 'remark', 'notes']);
            }
        } elseif ($lead->relationLoaded('latestProspectRemark') && $lead->latestProspectRemark) {
            $this->pushLatestRemarkCandidate($remarkCandidates, $lead->latestProspectRemark, ['manager_remark', 'employee_remark', 'remark', 'notes']);
        }

        if ($this->isDisplayableRemark($lead->notes ?? null)) {
            $remark = $this->cleanLatestRemarkText((string) $lead->notes);
            if ($this->isDisplayableRemark($remark)) {
                $remarkCandidates->push([
                    'value' => $remark,
                    'at' => $lead->updated_at ?? $lead->created_at,
                ]);
            }
        }

        $latestRemark = $remarkCandidates
            ->sortByDesc(fn ($item) => $item['at'] ? Carbon::parse($item['at'])->timestamp : 0)
            ->first();

        return $latestRemark['value'] ?? null;
    }

    private function pushLatestRemarkCandidate($remarkCandidates, $model, array $attributes): void
    {
        foreach ($attributes as $attribute) {
            $value = trim((string) ($model->{$attribute} ?? ''));
            $value = $this->cleanLatestRemarkText($value);
            if ($this->isDisplayableRemark($value)) {
                $remarkCandidates->push([
                    'value' => $value,
                    'at' => $model->updated_at ?? $model->created_at,
                ]);
                return;
            }
        }
    }

    private function cleanLatestRemarkText(string $value): string
    {
        $lines = collect(preg_split('/\r\n|\r|\n/', trim($value)) ?: [])
            ->map(function ($line) {
                $line = trim((string) $line);
                $line = preg_replace('/^\[\d{4}-\d{2}-\d{2}[^\]]*\]\s*/', '', $line) ?? $line;
                $line = preg_replace('/^ASM\s+outcome:\s*/i', '', $line) ?? $line;

                return trim($line);
            })
            ->filter(fn (string $line) => $this->isDisplayableRemark($line))
            ->values();

        return (string) ($lines->last() ?? '');
    }

    private function isDisplayableRemark($value): bool
    {
        $remark = trim((string) $value);
        if ($remark === '') {
            return false;
        }

        $lower = strtolower($remark);
        $plainDashValues = ['-', '--', '—', 'â€”', 'Ã¢â‚¬â€'];
        if (in_array($remark, $plainDashValues, true) || in_array($lower, ['n/a', 'na', 'null'], true)) {
            return false;
        }

        $systemPatterns = [
            'inbox_url',
            'business.facebook.com',
            'apartment_budget',
            'apartment_type',
            'asm fresh lead cnp automation',
            'mcube non-answered call captured',
        ];

        foreach ($systemPatterns as $pattern) {
            if (str_contains($lower, $pattern)) {
                return false;
            }
        }

        return !preg_match('/^[a-z0-9_]+:\s*/i', $remark);
    }

    private function selectExistingColumns(string $table, array $columns): array
    {
        static $tableColumns = [];

        if (!array_key_exists($table, $tableColumns)) {
            $tableColumns[$table] = array_flip(Schema::getColumnListing($table));
        }

        return collect($columns)
            ->filter(fn (string $column) => isset($tableColumns[$table][$column]))
            ->values()
            ->all();
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        return $this->selectExistingColumns($table, [$column]) !== [];
    }

    private function qualifyColumns(string $table, array $columns): array
    {
        return array_map(fn (string $column) => $table . '.' . $column, $columns);
    }

    private function aggregateLeadStats($query): array
    {
        $stats = (clone $query)
            ->reorder()
            ->selectRaw(<<<'SQL'
                COUNT(*) AS total,
                COALESCE(SUM(CASE WHEN NOT EXISTS (
                    SELECT 1 FROM lead_assignments
                    WHERE lead_assignments.lead_id = leads.id
                      AND lead_assignments.is_active = 1
                ) AND leads.status NOT IN ('junk', 'not_interested', 'closed')
                  AND (leads.is_dead = 0 OR leads.is_dead IS NULL) THEN 1 ELSE 0 END), 0) AS unassigned,
                COALESCE(SUM(CASE WHEN leads.status IN ('meeting_scheduled', 'meeting_completed', 'visit_scheduled', 'visit_done') THEN 1 ELSE 0 END), 0) AS in_pipeline,
                COALESCE(SUM(CASE WHEN leads.status = 'closed' THEN 1 ELSE 0 END), 0) AS closed,
                COALESCE(SUM(CASE WHEN leads.status IN ('junk', 'not_interested') OR leads.is_dead = 1 THEN 1 ELSE 0 END), 0) AS other,
                COALESCE(SUM(CASE WHEN leads.status = 'not_interested' THEN 1 ELSE 0 END), 0) AS not_interested,
                COALESCE(SUM(CASE WHEN leads.status = 'junk' THEN 1 ELSE 0 END), 0) AS junk
                SQL)
            ->first();

        return collect(['total', 'unassigned', 'in_pipeline', 'closed', 'other', 'not_interested', 'junk'])
            ->mapWithKeys(fn (string $key) => [$key => (int) ($stats->{$key} ?? 0)])
            ->all();
    }

    private function leadStatsCacheKey(Request $request, User $user): string
    {
        $filters = collect($request->query())
            ->except(['page', 'per_page', 'view'])
            ->all();
        $this->sortCacheKeyValues($filters);

        return 'leads:index:stats:' . sha1(json_encode([
            'user_id' => $user->id,
            'role_id' => $user->role_id,
            'date' => now()->toDateString(),
            'filters' => $filters,
        ]));
    }

    private function sortCacheKeyValues(array &$values): void
    {
        ksort($values);
        foreach ($values as &$value) {
            if (is_array($value)) {
                $this->sortCacheKeyValues($value);
            }
        }
    }

    private function resolveEnabledMetaFormId(mixed $value): ?int
    {
        if (is_array($value) || !Schema::hasTable('fb_forms')) {
            return null;
        }

        $formId = filter_var((string) $value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$formId) {
            return null;
        }

        $enabledFormId = DB::table('fb_forms')
            ->where('id', $formId)
            ->where('is_enabled', true)
            ->value('id');

        return $enabledFormId ? (int) $enabledFormId : null;
    }

    private function metaFormFilterOptions()
    {
        if (!Schema::hasTable('fb_forms') || !Schema::hasTable('fb_pages')) {
            return collect();
        }

        return DB::table('fb_forms')
            ->leftJoin('fb_pages', 'fb_pages.id', '=', 'fb_forms.fb_page_id')
            ->where('fb_forms.is_enabled', true)
            ->select([
                'fb_forms.id',
                'fb_forms.form_name',
                'fb_forms.form_id',
                'fb_pages.page_name',
            ])
            ->orderBy('fb_pages.page_name')
            ->orderBy('fb_forms.form_name')
            ->get();
    }

    private function applyMetaFormFilter(\Illuminate\Database\Eloquent\Builder $query, int $metaFormId): void
    {
        if (!Schema::hasTable('fb_leads')) {
            return;
        }

        $query->whereExists(function ($subQuery) use ($metaFormId) {
            $subQuery->select(DB::raw(1))
                ->from('fb_leads')
                ->whereColumn('fb_leads.crm_lead_id', 'leads.id')
                ->where('fb_leads.fb_form_id', $metaFormId)
                ->whereNotNull('fb_leads.crm_lead_id');
        });
    }

    private function applyMetaOutcomeFilter(\Illuminate\Database\Eloquent\Builder $query, string $outcome): void
    {
        $outcome = trim(strtolower($outcome));
        if (!in_array($outcome, ['interested', 'not_interested', 'follow_up', 'visit', 'cnp', 'pending', 'closer'], true)) {
            return;
        }

        $qualifiedStatuses = [
            'interested',
            'connected',
            'verified_prospect',
            'meeting_scheduled',
            'meeting_completed',
            'visit_scheduled',
            'visit_done',
            'revisited_scheduled',
            'revisited_completed',
            'closed',
        ];

        if ($outcome === 'interested') {
            $query->whereIn('status', $qualifiedStatuses);
            return;
        }

        if ($outcome === 'not_interested') {
            $query->where('status', 'not_interested');
            return;
        }

        if ($outcome === 'follow_up') {
            $query->where(function ($followUpQuery) {
                $followUpQuery->where('status', 'follow_up')
                    ->orWhereNotNull('next_followup_at');
            });
            return;
        }

        if ($outcome === 'visit') {
            $query->whereHas('siteVisits');
            return;
        }

        if ($outcome === 'closer') {
            $query->whereHas('siteVisits', function ($visitQuery) {
                $visitQuery->where('closer_status', 'verified');
            });
            return;
        }

        if ($outcome === 'cnp') {
            $query->where(function ($cnpQuery) {
                $cnpQuery->where('status', 'on_hold');

                if (Schema::hasColumn('leads', 'cnp_count')) {
                    $cnpQuery->orWhere('cnp_count', '>', 0);
                }

                if (Schema::hasColumn('leads', 'cnp_quarantined_at') && Schema::hasColumn('leads', 'cnp_quarantine_cleared_at')) {
                    $cnpQuery->orWhere(function ($quarantineQuery) {
                        $quarantineQuery->whereNotNull('cnp_quarantined_at')
                            ->whereNull('cnp_quarantine_cleared_at');
                    });
                }

                if (Schema::hasTable('crm_assignments')) {
                    $cnpQuery->orWhereExists(function ($assignmentQuery) {
                        $assignmentQuery->select(DB::raw(1))
                            ->from('crm_assignments')
                            ->whereColumn('crm_assignments.lead_id', 'leads.id')
                            ->where(function ($statusQuery) {
                                $statusQuery->where('crm_assignments.call_status', 'pending');

                                if (Schema::hasColumn('crm_assignments', 'cnp_count')) {
                                    $statusQuery->orWhere('crm_assignments.cnp_count', '>', 0);
                                }
                            });
                    });
                }
            });
            return;
        }

        $query
            ->whereNotIn('status', array_merge($qualifiedStatuses, ['not_interested', 'junk', 'follow_up', 'on_hold', 'closed']))
            ->whereNull('next_followup_at')
            ->whereDoesntHave('siteVisits')
            ->where(function ($pendingQuery) {
                if (Schema::hasColumn('leads', 'cnp_count')) {
                    $pendingQuery->whereNull('cnp_count')->orWhere('cnp_count', 0);
                }
            });
    }

    private function applySourceFilter(\Illuminate\Database\Eloquent\Builder $query, string $source): void
    {
        $sourceInput = trim($source);
        if ($sourceInput === '') {
            return;
        }

        $sourceKey = strtolower($sourceInput);
        $sourceOptions = Lead::sourceOptions();
        $isCanonicalSource = array_key_exists($sourceKey, Lead::SOURCE_OPTIONS)
            || array_key_exists($sourceKey, Lead::LEGACY_SOURCE_MAP);

        if ($isCanonicalSource) {
            $normalizedSource = Lead::normalizeSource($sourceInput);

            if ($normalizedSource === 'meta_awareness') {
                $this->applyMetaAwarenessSourceFilter($query);
                return;
            }

            $query->where('source', $normalizedSource);
            if ($normalizedSource === 'meta') {
                $this->excludeMetaAwarenessSource($query);
            }
            return;
        }

        $selectedLabel = $sourceOptions[$sourceKey] ?? Lead::displaySourceLabel($sourceInput);
        $exactValues = collect([
            $sourceInput,
            $sourceKey,
            $selectedLabel,
            str_replace(['_', '-'], ' ', $sourceInput),
        ])
            ->map(fn ($value) => strtolower(trim((string) $value)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $query->where(function ($sourceQuery) use ($sourceInput, $exactValues) {
            $sourceQuery
                ->where('source', $sourceInput)
                ->orWhere(function ($notesQuery) use ($exactValues) {
                    foreach ($exactValues as $exactValue) {
                        $notesQuery->orWhereRaw('LOWER(notes) LIKE ?', ['%lead source:%' . $exactValue . '%'])
                            ->orWhereRaw('LOWER(notes) LIKE ?', ['%lead source :%' . $exactValue . '%']);
                    }
                })
                ->orWhereHas('formFieldValues', function ($fieldQuery) use ($exactValues) {
                    $fieldQuery->where('field_key', 'source')
                        ->whereIn(DB::raw('LOWER(TRIM(field_value))'), $exactValues);
                });
        });
    }

    private function applyMetaAwarenessSourceFilter(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $awarenessValues = ['meta awareness', 'meta_awareness', 'meta-awareness'];

        $query->where(function ($sourceQuery) use ($awarenessValues) {
            $sourceQuery
                ->where('source', Lead::normalizeSource('meta_awareness'))
                ->orWhereHas('formFieldValues', function ($fieldQuery) use ($awarenessValues) {
                    $fieldQuery->where('field_key', 'source')
                        ->whereIn(DB::raw('LOWER(TRIM(field_value))'), $awarenessValues);
                })
                ->orWhere(function ($notesQuery) use ($awarenessValues) {
                    foreach ($awarenessValues as $value) {
                        $notesQuery->orWhereRaw('LOWER(notes) LIKE ?', ['%lead source:%' . $value . '%'])
                            ->orWhereRaw('LOWER(notes) LIKE ?', ['%lead source :%' . $value . '%']);
                    }
                });
        });
    }

    private function applyHiringLeadVisibilityFilter(\Illuminate\Database\Eloquent\Builder $query, Request $request, User $user): void
    {
        $leadGroup = trim((string) $request->input('lead_group', 'sales'));
        $canSeeHiringGroup = $user->isAdmin() || $user->isCrm();

        if ($canSeeHiringGroup && $leadGroup === 'hr') {
            $query->where('is_hiring_candidate', true);
            return;
        }

        if ($canSeeHiringGroup && $leadGroup === 'all') {
            return;
        }

        $query->where(function ($leadQuery) {
            $leadQuery->where('is_hiring_candidate', false)
                ->orWhereNull('is_hiring_candidate');
        });
    }

    private function applyPipelineStageFilter(\Illuminate\Database\Eloquent\Builder $query, Request $request): void
    {
        $stage = trim((string) $request->input('pipeline_stage', ''));

        if (!in_array($stage, ['new_leads', 'cnp', 'follow_up'], true)) {
            return;
        }

        $this->applyLeadDisplayStatusFilter($query, $stage === 'new_leads' ? 'new' : $stage);
    }

    private function normalizeLeadStatusFilters(Request $request): array
    {
        $rawStatuses = $request->input('status', []);

        if (is_string($rawStatuses)) {
            $rawStatuses = str_contains($rawStatuses, ',')
                ? explode(',', $rawStatuses)
                : [$rawStatuses];
        }

        if (!is_array($rawStatuses)) {
            return [];
        }

        return collect($rawStatuses)
            ->map(fn ($status) => trim((string) $status))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function applyLeadStatusFilters(\Illuminate\Database\Eloquent\Builder $query, array $selectedStatuses): void
    {
        if (empty($selectedStatuses)) {
            return;
        }

        $query->where(function ($statusQuery) use ($selectedStatuses) {
            foreach ($selectedStatuses as $status) {
                $statusQuery->orWhere(function ($singleStatusQuery) use ($status) {
                    if ($status === 'reenquiry') {
                        $singleStatusQuery->where('is_reenquiry', true);
                        return;
                    }

                    if (in_array($status, ['new', 'cnp', 'follow_up'], true)) {
                        $this->applyLeadDisplayStatusFilter($singleStatusQuery, $status);
                        return;
                    }

                    if ($this->applyActivityStatusFilter($singleStatusQuery, $status)) {
                        return;
                    }

                    $singleStatusQuery->where('status', $status);
                });
            }
        });
    }

    private function applyLeadDisplayStatusFilter(\Illuminate\Database\Eloquent\Builder $query, string $stage): void
    {
        if (!in_array($stage, ['new', 'cnp', 'follow_up'], true)) {
            return;
        }

        $query
            ->where(function ($leadQuery) {
                $leadQuery->where('is_hiring_candidate', false)
                    ->orWhereNull('is_hiring_candidate');
            })
            ->whereNotIn('status', ['junk', 'not_interested', 'dead', 'closed'])
            ->where(function ($leadQuery) {
                $leadQuery->where('is_dead', false)->orWhereNull('is_dead');
            })
            ->whereDoesntHave('prospects')
            ->whereDoesntHave('meetings', function ($meetingQuery) {
                $meetingQuery->where('status', 'completed')
                    ->orWhereNotNull('completed_at');
            })
            ->whereDoesntHave('siteVisits', function ($visitQuery) {
                $visitQuery->where('status', 'completed')
                    ->orWhereNotNull('completed_at')
                    ->orWhereIn('closer_status', ['approved', 'verified']);
            });

        if ($stage === 'cnp') {
            $query->where(function ($cnpQuery) {
                $cnpQuery->where('cnp_count', '>', 0)
                    ->orWhereHas('managerTasks', function ($taskQuery) {
                        $taskQuery->where('type', 'phone_call')->where('outcome', 'cnp');
                    });
            });
            return;
        }

        if ($stage === 'follow_up') {
            $query
                ->where(function ($followUpQuery) {
                    $followUpQuery->whereNotNull('next_followup_at')
                        ->orWhereHas('managerTasks', function ($taskQuery) {
                            $taskQuery->where('type', 'phone_call')->where('outcome', 'follow_up');
                        });
                })
                ->where(function ($notCnpQuery) {
                    $notCnpQuery->whereNull('cnp_count')->orWhere('cnp_count', '<=', 0);
                })
                ->whereDoesntHave('managerTasks', function ($taskQuery) {
                    $taskQuery->where('type', 'phone_call')->where('outcome', 'cnp');
                });
            return;
        }

        $query
            ->where(function ($newQuery) {
                $newQuery->whereNull('cnp_count')->orWhere('cnp_count', '<=', 0);
            })
            ->whereNull('next_followup_at')
            ->whereDoesntHave('managerTasks', function ($taskQuery) {
                $taskQuery->where('type', 'phone_call')
                    ->whereIn('outcome', ['cnp', 'follow_up']);
            });
    }

    private function excludeMetaAwarenessSource(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $awarenessValues = ['meta awareness', 'meta_awareness', 'meta-awareness'];

        $query
            ->whereDoesntHave('formFieldValues', function ($fieldQuery) use ($awarenessValues) {
                $fieldQuery->where('field_key', 'source')
                    ->whereIn(DB::raw('LOWER(TRIM(field_value))'), $awarenessValues);
            })
            ->where(function ($notesQuery) use ($awarenessValues) {
                $notesQuery->whereNull('notes')
                    ->orWhere(function ($nonAwarenessNotesQuery) use ($awarenessValues) {
                        foreach ($awarenessValues as $value) {
                            $nonAwarenessNotesQuery->whereRaw('LOWER(notes) NOT LIKE ?', ['%lead source:%' . $value . '%'])
                                ->whereRaw('LOWER(notes) NOT LIKE ?', ['%lead source :%' . $value . '%']);
                        }
                    });
            });
    }

    private function applyTaskCreationFilter(\Illuminate\Database\Eloquent\Builder $query, string $taskCreationFilter): void
    {
        $query->whereHas('activeAssignments')
            ->whereNotIn('status', ['junk', 'not_interested', 'closed'])
            ->where(function (\Illuminate\Database\Eloquent\Builder $builder) {
                $builder->where('is_dead', false)->orWhereNull('is_dead');
            });

        $leadTable = $query->getModel()->getTable();
        $telecallerTasksSupportQueueArchiving = TelecallerTask::supportsQueueArchiving();
        $managerTasksSupportQueueArchiving = Task::supportsQueueArchiving();

        $openTaskExists = function ($subQuery) use ($leadTable, $telecallerTasksSupportQueueArchiving, $managerTasksSupportQueueArchiving) {
            $subQuery->selectRaw('1')
                ->from('lead_assignments as la')
                ->whereColumn('la.lead_id', $leadTable . '.id')
                ->where('la.is_active', true)
                ->where(function ($assignmentQuery) use ($telecallerTasksSupportQueueArchiving, $managerTasksSupportQueueArchiving) {
                    $assignmentQuery
                        ->whereExists(function ($telecallerTaskQuery) use ($telecallerTasksSupportQueueArchiving) {
                            $telecallerTaskQuery->selectRaw('1')
                                ->from('telecaller_tasks')
                                ->whereColumn('telecaller_tasks.lead_id', 'la.lead_id')
                                ->whereColumn('telecaller_tasks.assigned_to', 'la.assigned_to')
                                ->where('telecaller_tasks.task_type', 'calling')
                                ->whereNull('telecaller_tasks.deleted_at')
                                ->whereIn('telecaller_tasks.status', TelecallerTask::OPEN_STATUSES);

                            if ($telecallerTasksSupportQueueArchiving) {
                                $telecallerTaskQuery->whereNull('telecaller_tasks.queue_hidden_at');
                            }
                        })
                        ->orWhereExists(function ($managerTaskQuery) use ($managerTasksSupportQueueArchiving) {
                            $managerTaskQuery->selectRaw('1')
                                ->from('tasks')
                                ->whereColumn('tasks.lead_id', 'la.lead_id')
                                ->whereColumn('tasks.assigned_to', 'la.assigned_to')
                                ->where('tasks.type', 'phone_call')
                                ->whereNull('tasks.deleted_at')
                                ->whereIn('tasks.status', Task::OPEN_STATUSES);

                            if ($managerTasksSupportQueueArchiving) {
                                $managerTaskQuery->whereNull('tasks.queue_hidden_at');
                            }
                        });
                });
        };

        if ($taskCreationFilter === 'created') {
            $query->whereExists($openTaskExists);
            return;
        }

        $query->whereNotExists($openTaskExists);
    }

    private function applyDemandInsightFilters(\Illuminate\Database\Eloquent\Builder $query, Request $request): void
    {
        $propertyBucket = trim((string) $request->input('demand_property', ''));
        $budgetBucket = trim((string) $request->input('demand_budget', ''));

        if ($propertyBucket === '' && $budgetBucket === '') {
            return;
        }

        $demandStatuses = [
            'verified_prospect',
            'meeting_scheduled',
            'meeting_completed',
            'visit_scheduled',
            'visit_done',
            'revisited_scheduled',
            'revisited_completed',
            'closed',
        ];
        $formKeys = ['category', 'type', 'budget', 'interested_projects'];

        $query->where(function ($demandQuery) use ($demandStatuses, $formKeys) {
            $demandQuery->whereIn('status', $demandStatuses)
                ->orWhereHas('formFieldValues', function ($formQuery) use ($formKeys) {
                    $formQuery->whereIn('field_key', $formKeys)
                        ->whereNotNull('field_value')
                        ->where('field_value', '!=', '')
                        ->whereRaw('LOWER(TRIM(field_value)) NOT IN (?, ?, ?, ?)', ['n.a', 'na', 'n/a', 'null']);
                });
        });

        if ($propertyBucket !== '') {
            $this->applyDemandPropertyBucketFilter($query, $propertyBucket);
        }

        if ($budgetBucket !== '') {
            $this->applyDemandBudgetBucketFilter($query, $budgetBucket);
        }
    }

    private function applyDemandPropertyBucketFilter(\Illuminate\Database\Eloquent\Builder $query, string $bucket): void
    {
        $terms = match ($bucket) {
            'Commercial' => ['commercial', 'shop', 'office'],
            'Apartment' => ['apartment', 'flat', 'residential'],
            'Plot' => ['plot'],
            'Villa/Floor' => ['villa', 'floor', 'independent'],
            'Other' => ['other', 'both'],
            default => [],
        };

        if ($bucket === 'Undefined' || empty($terms)) {
            $knownTerms = ['commercial', 'shop', 'office', 'apartment', 'flat', 'residential', 'plot', 'villa', 'floor', 'independent', 'other', 'both'];
            $query->where(function ($undefinedQuery) use ($knownTerms) {
                $undefinedQuery->whereDoesntHave('formFieldValues', function ($formQuery) use ($knownTerms) {
                    $formQuery->whereIn('field_key', ['category', 'type'])
                        ->where(function ($valueQuery) use ($knownTerms) {
                            foreach ($knownTerms as $term) {
                                $valueQuery->orWhereRaw('LOWER(field_value) LIKE ?', ['%' . $term . '%']);
                            }
                        });
                })->where(function ($leadQuery) use ($knownTerms) {
                    $leadQuery->whereNull('property_type')
                        ->orWhere('property_type', '')
                        ->orWhereRaw('LOWER(TRIM(property_type)) IN (?, ?, ?, ?)', ['n.a', 'na', 'n/a', 'null'])
                        ->orWhere(function ($unknownLeadQuery) use ($knownTerms) {
                            foreach ($knownTerms as $term) {
                                $unknownLeadQuery->whereRaw('LOWER(property_type) NOT LIKE ?', ['%' . $term . '%']);
                            }
                        });
                });
            });
            return;
        }

        $query->where(function ($bucketQuery) use ($terms) {
            $bucketQuery->whereHas('formFieldValues', function ($formQuery) use ($terms) {
                $formQuery->whereIn('field_key', ['category', 'type'])
                    ->where(function ($valueQuery) use ($terms) {
                        foreach ($terms as $term) {
                            $valueQuery->orWhereRaw('LOWER(field_value) LIKE ?', ['%' . $term . '%']);
                        }
                    });
            })->orWhere(function ($leadQuery) use ($terms) {
                foreach ($terms as $term) {
                    $leadQuery->orWhereRaw('LOWER(property_type) LIKE ?', ['%' . $term . '%']);
                }
            });
        });
    }

    private function applyDemandBudgetBucketFilter(\Illuminate\Database\Eloquent\Builder $query, string $bucket): void
    {
        $terms = match ($bucket) {
            'Below 50 Lacs' => ['below 50', 'under 50'],
            '50-75 Lacs' => ['50-75', '50 lacs-75', '50 lac-75'],
            '75 Lacs-1 Cr' => ['75 lacs-1 cr', '75 lac-1 cr', '75-1 cr', '75 lacs'],
            '1 Cr-2 Cr' => ['1 cr', '1cr', 'above 1'],
            'Above 2 Cr' => ['above 2', '2 cr', '2cr', '5 cr'],
            default => [],
        };

        if ($bucket === 'Undefined' || empty($terms)) {
            $query->where(function ($undefinedQuery) {
                $undefinedQuery->where(function ($leadQuery) {
                    $leadQuery->whereNull('budget')
                        ->orWhere('budget', '')
                        ->orWhereRaw('LOWER(TRIM(budget)) IN (?, ?, ?, ?)', ['n.a', 'na', 'n/a', 'null']);
                })
                ->whereNull('budget_min')
                ->whereNull('budget_max')
                ->whereDoesntHave('formFieldValues', function ($formQuery) {
                    $formQuery->where('field_key', 'budget')
                        ->whereNotNull('field_value')
                        ->where('field_value', '!=', '')
                        ->whereRaw('LOWER(TRIM(field_value)) NOT IN (?, ?, ?, ?)', ['n.a', 'na', 'n/a', 'null']);
                })
                ->whereDoesntHave('prospects', function ($prospectQuery) {
                    $prospectQuery->whereNotNull('budget')
                        ->where('budget', '!=', '')
                        ->whereRaw('LOWER(TRIM(budget)) NOT IN (?, ?, ?, ?)', ['n.a', 'na', 'n/a', 'null']);
                });
            });
            return;
        }

        $query->where(function ($bucketQuery) use ($terms, $bucket) {
            $bucketQuery->whereHas('formFieldValues', function ($formQuery) use ($terms) {
                $formQuery->where('field_key', 'budget')
                    ->where(function ($valueQuery) use ($terms) {
                        foreach ($terms as $term) {
                            $valueQuery->orWhereRaw('LOWER(field_value) LIKE ?', ['%' . $term . '%']);
                        }
                    });
            })->orWhere(function ($leadQuery) use ($terms) {
                foreach ($terms as $term) {
                    $leadQuery->orWhereRaw('LOWER(budget) LIKE ?', ['%' . $term . '%']);
                }
            })->orWhereHas('prospects', function ($prospectQuery) use ($terms) {
                $prospectQuery->where(function ($valueQuery) use ($terms) {
                    foreach ($terms as $term) {
                        $valueQuery->orWhereRaw('LOWER(budget) LIKE ?', ['%' . $term . '%']);
                    }
                });
            })->orWhere(function ($numericQuery) use ($bucket) {
                match ($bucket) {
                    'Below 50 Lacs' => $numericQuery->where(function ($rangeQuery) {
                        $rangeQuery->where('budget_max', '<', 5000000)
                            ->orWhere(function ($minOnlyQuery) {
                                $minOnlyQuery->whereNull('budget_max')->where('budget_min', '<', 5000000);
                            });
                    }),
                    '50-75 Lacs' => $numericQuery->whereRaw('COALESCE(budget_max, budget_min) >= ? AND COALESCE(budget_min, budget_max) < ?', [5000000, 7500000]),
                    '75 Lacs-1 Cr' => $numericQuery->whereRaw('COALESCE(budget_max, budget_min) >= ? AND COALESCE(budget_min, budget_max) < ?', [7500000, 10000000]),
                    '1 Cr-2 Cr' => $numericQuery->whereRaw('COALESCE(budget_max, budget_min) >= ? AND COALESCE(budget_min, budget_max) < ?', [10000000, 20000000]),
                    'Above 2 Cr' => $numericQuery->whereRaw('COALESCE(budget_max, budget_min) >= ?', [20000000]),
                    default => null,
                };
            });
        });
    }

    private function resolveLeadDateRange(Request $request): array
    {
        $dateRange = $request->get('date_range');
        $today = Carbon::today();

        return match ($dateRange) {
            'today' => [$today->copy()->startOfDay(), $today->copy()->endOfDay()],
            'yesterday' => [$today->copy()->subDay()->startOfDay(), $today->copy()->subDay()->endOfDay()],
            'this_week' => [$today->copy()->startOfWeek(), $today->copy()->endOfWeek()],
            'this_month' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
            'previous_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()],
            'this_year' => [$today->copy()->startOfYear(), $today->copy()->endOfYear()],
            'custom' => $this->resolveCustomLeadDateRange($request),
            default => [null, null],
        };
    }

    private function resolveCustomLeadDateRange(Request $request): array
    {
        if (!$request->filled('start_date') || !$request->filled('end_date')) {
            return [null, null];
        }

        try {
            return [
                Carbon::parse($request->get('start_date'))->startOfDay(),
                Carbon::parse($request->get('end_date'))->endOfDay(),
            ];
        } catch (\Throwable $e) {
            return [null, null];
        }
    }

    private function applyLeadIndexDateRangeFilter($query, Request $request, Carbon $startDate, Carbon $endDate): void
    {
        $selectedStatuses = $this->normalizeLeadStatusFilters($request);
        $activityStatuses = array_values(array_filter(
            $selectedStatuses,
            fn ($status) => in_array($status, ['meeting_scheduled', 'meeting_completed', 'visit_scheduled', 'visit_done', 'revisited_scheduled', 'revisited_completed'], true)
        ));

        if (!empty($activityStatuses)) {
            $query->where(function ($activityQuery) use ($activityStatuses, $startDate, $endDate) {
                foreach ($activityStatuses as $status) {
                    $activityQuery->orWhere(function ($singleStatusQuery) use ($status, $startDate, $endDate) {
                        $this->applyActivityStatusFilter($singleStatusQuery, $status, $startDate, $endDate);
                    });
                }
            });

            return;
        }

        $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    private function applyActivityStatusFilter(\Illuminate\Database\Eloquent\Builder $query, string $status, ?Carbon $startDate = null, ?Carbon $endDate = null): bool
    {
        if (in_array($status, ['visit_scheduled', 'visit_done', 'revisited_scheduled', 'revisited_completed'], true)) {
            $query->whereHas('siteVisits', function ($visitQuery) use ($status, $startDate, $endDate) {
                if (in_array($status, ['visit_done', 'revisited_completed'], true)) {
                    $visitQuery->where('status', 'completed');

                    if ($startDate && $endDate) {
                        $visitQuery->where(function ($dateQuery) use ($startDate, $endDate) {
                            $dateQuery->whereBetween('completed_at', [$startDate, $endDate])
                                ->orWhere(function ($verifiedQuery) use ($startDate, $endDate) {
                                    $verifiedQuery->whereNull('completed_at')
                                        ->whereBetween('verified_at', [$startDate, $endDate]);
                                })
                                ->orWhereBetween('date_of_visit', [$startDate->toDateString(), $endDate->toDateString()]);
                        });
                    }
                } else {
                    $visitQuery->where(function ($statusQuery) {
                        $statusQuery->where('status', 'scheduled')
                            ->orWhere(function ($pendingQuery) {
                                $pendingQuery->whereNull('completed_at')
                                    ->where('status', '!=', 'completed');
                            });
                    });

                    if ($startDate && $endDate) {
                        $visitQuery->where(function ($dateQuery) use ($startDate, $endDate) {
                            $dateQuery->whereBetween('scheduled_at', [$startDate, $endDate])
                                ->orWhereBetween('date_of_visit', [$startDate->toDateString(), $endDate->toDateString()]);
                        });
                    }
                }

                if (in_array($status, ['revisited_scheduled', 'revisited_completed'], true)) {
                    $visitQuery->where(function ($typeQuery) {
                        $typeQuery->where('lead_type', 'Revisited')
                            ->orWhere('visit_sequence', 'revisit');
                    });
                }
            });

            return true;
        }

        if (in_array($status, ['meeting_scheduled', 'meeting_completed'], true)) {
            $query->whereHas('meetings', function ($meetingQuery) use ($status, $startDate, $endDate) {
                if ($status === 'meeting_completed') {
                    $meetingQuery->where('status', 'completed');

                    if ($startDate && $endDate) {
                        $meetingQuery->where(function ($dateQuery) use ($startDate, $endDate) {
                            $dateQuery->whereBetween('completed_at', [$startDate, $endDate])
                                ->orWhere(function ($verifiedQuery) use ($startDate, $endDate) {
                                    $verifiedQuery->whereNull('completed_at')
                                        ->whereBetween('verified_at', [$startDate, $endDate]);
                                })
                                ->orWhereBetween('date_of_visit', [$startDate->toDateString(), $endDate->toDateString()]);
                        });
                    }

                    return;
                }

                $meetingQuery->where(function ($statusQuery) {
                    $statusQuery->where('status', 'scheduled')
                        ->orWhere(function ($pendingQuery) {
                            $pendingQuery->whereNull('completed_at')
                                ->where('status', '!=', 'completed');
                        });
                });

                if ($startDate && $endDate) {
                    $meetingQuery->where(function ($dateQuery) use ($startDate, $endDate) {
                        $dateQuery->whereBetween('scheduled_at', [$startDate, $endDate])
                            ->orWhereBetween('date_of_visit', [$startDate->toDateString(), $endDate->toDateString()]);
                    });
                }
            });

            return true;
        }

        return false;
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

        $leads->loadMissing(['latestMeeting', 'latestSiteVisit', 'latestProspect']);

        $latestTasksByLead = collect();
        if ($this->tableHasColumn('tasks', 'type')) {
            $latestTasksByLead = Task::query()
                ->whereIn('lead_id', $leadIds)
                ->where('type', 'phone_call')
                ->where(function ($taskQuery) {
                    $taskQuery->whereNotNull('outcome')
                        ->orWhereNotNull('meeting_id')
                        ->orWhereNotNull('site_visit_id');

                    foreach (['title', 'description', 'notes'] as $column) {
                        $taskQuery->orWhere($column, 'like', '%cnp retry task created%')
                            ->orWhere($column, 'like', '%cnp rescheduled%')
                            ->orWhere($column, 'like', '%previous call not picked%');
                    }
                })
                ->orderByDesc('id')
                ->get()
                ->groupBy('lead_id');
        }

        $tasks = $latestTasksByLead->flatten(1);
        $directSiteVisitIds = $tasks->pluck('site_visit_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $siteVisitIdsByTask = collect();
        if ($directSiteVisitIds->isNotEmpty() || ($tasks->isNotEmpty() && $this->siteVisitsHaveReminderTaskId())) {
            $siteVisitQuery = SiteVisit::query()->whereIn('id', $directSiteVisitIds);
            if ($this->siteVisitsHaveReminderTaskId()) {
                $siteVisitQuery->orWhereIn('reminder_task_id', $tasks->pluck('id'));
            }

            $siteVisitColumns = $this->siteVisitsHaveReminderTaskId() ? ['id', 'reminder_task_id'] : ['id'];
            $siteVisits = $siteVisitQuery->get($siteVisitColumns);
            $siteVisits->whereNotNull('reminder_task_id')->each(function (SiteVisit $siteVisit) use ($siteVisitIdsByTask) {
                $siteVisitIdsByTask->put((int) $siteVisit->reminder_task_id, (int) $siteVisit->id);
            });
        }

        $tasks->each(function (Task $task) use ($siteVisitIdsByTask) {
            $siteVisitId = (int) ($task->site_visit_id ?? 0);
            if ($siteVisitId === 0) {
                $siteVisitId = (int) $siteVisitIdsByTask->get((int) $task->id, 0);
            }
            $task->setAttribute('_resolved_site_visit_id', $siteVisitId);
        });

        $latestMeetingsByLead = $leads->pluck('latestMeeting')->filter()->groupBy('lead_id');
        $latestSiteVisitsByLead = $leads->pluck('latestSiteVisit')->filter()->groupBy('lead_id');
        $latestProspectsByLead = $leads->pluck('latestProspect')->filter()->groupBy('lead_id');
        $reopenBoundaries = app(\App\Services\LeadReopenService::class)->boundaries($leads->pluck('id')->all());

        foreach ($leads as $lead) {
            $displayStatus = (string) ($lead->status ?? 'new');

            if (!in_array($displayStatus, ['new', 'fresh_transfer'], true)) {
                $lead->setAttribute('display_status', $displayStatus);
                continue;
            }

            $tasks = $latestTasksByLead->get($lead->id, collect());
            $boundary = $reopenBoundaries[$lead->id] ?? [];
            $tasks = $tasks->filter(fn ($task) => $task->id > ($boundary['tasks'] ?? 0));
            $candidates = collect();

            foreach ($tasks as $task) {
                $taskStatus = $this->resolveDisplayStatusFromTask($task, $lead);
                if ($taskStatus) {
                    $candidates->push([
                        'status' => $taskStatus,
                        'at' => $this->displayStatusTimestamp($task, ['outcome_recorded_at', 'completed_at', 'updated_at', 'created_at']),
                    ]);
                }
            }

            foreach ($latestMeetingsByLead->get($lead->id, collect()) as $meeting) {
                if ($meeting->id <= ($boundary['meetings'] ?? 0)) continue;
                $meetingStatus = ((bool) ($meeting->is_dead ?? false))
                    ? 'dead'
                    : (((string) ($meeting->status ?? '') === 'completed' || !empty($meeting->completed_at))
                        ? 'meeting_completed'
                        : 'meeting_scheduled');

                $candidates->push([
                    'status' => $meetingStatus,
                    'at' => $this->displayStatusTimestamp($meeting, ['marked_dead_at', 'completed_at', 'verified_at', 'updated_at', 'created_at', 'scheduled_at']),
                ]);
            }

            foreach ($latestSiteVisitsByLead->get($lead->id, collect()) as $siteVisit) {
                if ($siteVisit->id <= ($boundary['site_visits'] ?? 0)) continue;
                $closerStatus = strtolower((string) ($siteVisit->closer_status ?? ''));
                $visitStatus = ((bool) ($siteVisit->is_dead ?? false))
                    ? 'dead'
                    : (in_array($closerStatus, ['verified', 'approved'], true)
                        ? 'closed'
                        : (((string) ($siteVisit->status ?? '') === 'completed' || !empty($siteVisit->completed_at) || (string) ($siteVisit->verification_status ?? '') === 'verified')
                            ? 'visit_done'
                            : 'visit_scheduled'));

                $candidates->push([
                    'status' => $visitStatus,
                    'at' => $this->displayStatusTimestamp($siteVisit, ['marked_dead_at', 'closer_verified_at', 'converted_to_closer_at', 'completed_at', 'verified_at', 'updated_at', 'created_at', 'scheduled_at']),
                ]);
            }

            foreach ($latestProspectsByLead->get($lead->id, collect()) as $prospect) {
                if ($prospect->id <= ($boundary['prospects'] ?? 0)) continue;
                if (in_array((string) ($prospect->verification_status ?? ''), ['verified', 'approved'], true)) {
                    $candidates->push([
                        'status' => 'verified_prospect',
                        'at' => $this->displayStatusTimestamp($prospect, ['verified_at', 'updated_at', 'created_at']),
                    ]);
                }
            }

            $latestCandidate = $candidates
                ->filter(fn ($candidate) => !empty($candidate['status']))
                ->sortByDesc(fn ($candidate) => optional($candidate['at'])->timestamp ?? 0)
                ->first();

            if ($latestCandidate) {
                $displayStatus = $latestCandidate['status'];
            } elseif (!empty($lead->next_followup_at)) {
                $displayStatus = 'follow_up';
            } elseif ((int) ($lead->cnp_count ?? 0) > 0) {
                $displayStatus = 'cnp';
            }

            $lead->setAttribute('display_status', $displayStatus);
        }
    }

    private function resolveDisplayStatusFromTask(Task $task, Lead $lead): ?string
    {
        $taskText = strtolower(trim(
            ($task->title ?? '') . ' ' .
            ($task->description ?? '') . ' ' .
            ($task->notes ?? '')
        ));

        $isCnpRetryTask = str_contains($taskText, 'cnp retry task created')
            || str_contains($taskText, 'cnp rescheduled')
            || str_contains($taskText, 'previous call not picked');

        if ($isCnpRetryTask) {
            return 'cnp';
        }

        $outcome = strtolower((string) ($task->outcome ?? ''));
        $taskCategory = $this->determineAsmTaskCategory($task, $lead);

        if (in_array($outcome, ['junk', 'not_interested', 'cnp'], true)) {
            return $outcome;
        }

        if (in_array($outcome, ['follow_up', 'followup', 'follow_up_needed', 'schedule_follow_up'], true)) {
            return 'follow_up';
        }

        if ($outcome === 'interested') {
            return 'connected';
        }

        if (in_array($outcome, ['visited', 'visit_done'], true)) {
            return 'visit_done';
        }

        if ($outcome === 'meeting_done') {
            return 'meeting_completed';
        }

        if ($taskCategory === 'meeting' && (int) ($task->meeting_id ?? 0) > 0) {
            return 'meeting_scheduled';
        }

        if ($taskCategory === 'site_visit' && $this->resolveManagerTaskSiteVisitId($task) !== null) {
            return 'visit_scheduled';
        }

        return null;
    }

    private function displayStatusTimestamp(object $model, array $columns): ?Carbon
    {
        foreach ($columns as $column) {
            $value = $model->{$column} ?? null;
            if (empty($value)) {
                continue;
            }

            return $value instanceof Carbon ? $value : Carbon::parse($value);
        }

        return null;
    }

    private function determineAsmTaskCategory(Task $task, Lead $lead): string
    {
        $prospect = $lead->relationLoaded('latestProspect')
            ? $lead->latestProspect
            : ($lead->relationLoaded('prospects') ? $lead->prospects->sortByDesc('created_at')->first() : null);
        $hasPendingProspect = $prospect && in_array($prospect->verification_status ?? '', ['pending', 'pending_verification'], true);
        if ($prospect && $prospect->id <= (app(\App\Services\LeadReopenService::class)->boundary($lead->id)['prospects'] ?? 0)) $hasPendingProspect = false;

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
        $siteVisitId = $this->resolveManagerTaskSiteVisitId($task);
        $isSiteVisitTask = $siteVisitId !== null
            || str_contains($taskText, 'site visit')
            || str_contains($taskText, 'site-visit');
        $isMeetingTask = $task->meeting_id !== null
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

    private function determineTelecallerTaskCategory(TelecallerTask $task): string
    {
        if ($task->meeting_id) {
            return 'meeting';
        }

        if ($task->site_visit_id) {
            return 'site_visit';
        }

        if ($task->follow_up_id) {
            return 'follow_up';
        }

        $taskText = strtolower(trim((string) ($task->notes ?? '') . ' ' . (string) ($task->task_type ?? '')));

        return match (true) {
            str_contains($taskText, 'meeting') => 'meeting',
            str_contains($taskText, 'site visit'), str_contains($taskText, 'site-visit') => 'site_visit',
            str_contains($taskText, 'follow') => 'follow_up',
            default => 'fresh_lead',
        };
    }

    private function resolveManagerTaskSiteVisitId(Task $task): ?int
    {
        if (array_key_exists('_resolved_site_visit_id', $task->getAttributes())) {
            $siteVisitId = (int) $task->getAttribute('_resolved_site_visit_id');

            return $siteVisitId > 0 ? $siteVisitId : null;
        }

        $siteVisit = $this->resolveManagerTaskSiteVisit($task);

        return $siteVisit ? (int) $siteVisit->id : null;
    }

    private function resolveManagerTaskSiteVisit(Task $task): ?SiteVisit
    {
        if (Task::supportsColumn('site_visit_id') && $task->site_visit_id) {
            return SiteVisit::query()->find((int) $task->site_visit_id);
        }

        if (!$this->siteVisitsHaveReminderTaskId()) {
            return null;
        }

        return SiteVisit::query()
            ->where('reminder_task_id', $task->id)
            ->first();
    }

    private function siteVisitsHaveReminderTaskId(): bool
    {
        static $hasColumn;

        return $hasColumn ??= Schema::hasColumn('site_visits', 'reminder_task_id');
    }

    private function telecallerTaskTitle(TelecallerTask $task): string
    {
        return match ($this->determineTelecallerTaskCategory($task)) {
            'meeting' => 'Meeting reminder task',
            'site_visit' => 'Site visit reminder task',
            'follow_up' => 'Follow up task',
            default => 'Calling task',
        };
    }

    private function leadOpenTaskModels(Lead $lead, ?User $user = null)
    {
        $lead->loadMissing('prospects');
        $limitToUser = $user && !$this->canViewTeamLeadOpenTasks($user);

        $managerOpenTasks = Task::query()
            ->withoutGlobalScope('visible_in_queue')
            ->where('lead_id', $lead->id)
            ->when($limitToUser, fn ($query) => $query->where('assigned_to', $user->id))
            ->whereIn('status', Task::OPEN_STATUSES)
            ->whereNull('completed_at')
            ->get()
            ->each(function (Task $task) use ($lead) {
                $siteVisit = $this->resolveManagerTaskSiteVisit($task);
                $task->setAttribute('category', $this->determineAsmTaskCategory($task, $lead));
                $task->setAttribute('model_type', 'task');
                $task->setAttribute('site_visit_id', $siteVisit?->id);
                $task->setAttribute('site_visit_project', $siteVisit?->project ?: $siteVisit?->property_name);
            });

        $telecallerOpenTasks = TelecallerTask::query()
            ->withoutGlobalScope('visible_in_queue')
            ->where('lead_id', $lead->id)
            ->when($limitToUser, fn ($query) => $query->where('assigned_to', $user->id))
            ->whereIn('status', TelecallerTask::OPEN_STATUSES)
            ->whereNull('completed_at')
            ->get()
            ->each(function (TelecallerTask $task) {
                $task->setAttribute('category', $this->determineTelecallerTaskCategory($task));
                $task->setAttribute('model_type', 'telecaller_task');
                $task->setAttribute('title', $this->telecallerTaskTitle($task));
                $task->setAttribute('description', $task->notes);
            });

        return $managerOpenTasks
            ->concat($telecallerOpenTasks)
            ->sortByDesc(fn ($task) => optional($task->scheduled_at ?? $task->updated_at ?? $task->created_at)->timestamp ?? 0)
            ->values();
    }

    private function canViewLeadOpenTasks(User $user): bool
    {
        return $user->isAssistantSalesManager()
            || $user->isSeniorManager()
            || $user->isSalesManager()
            || $user->isSalesExecutive()
            || $user->isTelecaller();
    }

    private function canViewTeamLeadOpenTasks(User $user): bool
    {
        return $user->isAdmin()
            || $user->isCrm()
            || $user->isAssistantSalesManager()
            || $user->isSeniorManager()
            || $user->isSalesManager();
    }

    public function create(
        DynamicFormService $dynamicFormService,
        FormDetectionService $formDetectionService
    )
    {
        $user = auth()->user();
        
        // Disable old form for sales executive and manager - use centralized form instead
        if ($user->isSalesExecutive() || $user->isSalesManager() || $user->isSalesHead()) {
            return redirect()
                ->route('leads.index')
                ->with('info', 'Old lead creation form is disabled. Please use the centralized lead requirement form by editing an existing lead or contact admin for new lead creation.');
        }
        
        // All active users for Assign dropdown (sare user jo system mein hain)
        $users = User::where('is_active', true)
            ->whereHas('role')
            ->with('role')
            ->orderBy('name')
            ->get();

        $projects = Project::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();
        $interestedProjectOptions = $this->buildInterestedProjectOptions(
            $projects,
            $dynamicFormService->getPublishedFormByLocation('lead-detail.requirements')
        );

        // CRM panel: hide Location Details (Address, City, State, Pincode) on create form
        $showLocationDetails = !$user->isCrm();

        $dynamicForm = $dynamicFormService->getPublishedFormByLocation('leads.create');
        $fallbackFields = $formDetectionService->getFieldDefinitions('lead', 'leads.create');

        return view('leads.create', compact(
            'users',
            'projects',
            'interestedProjectOptions',
            'showLocationDetails',
            'dynamicForm',
            'fallbackFields'
        ));
    }

    public function checkDuplicate(Request $request, LeadDuplicateGuardService $leadDuplicateGuardService)
    {
        $validated = $request->validate([
            'phone' => 'required|string|max:30',
            'phone_country_iso' => 'nullable|string|size:2',
        ]);

        $normalizedPhone = $leadDuplicateGuardService->normalizePhone($validated['phone'], $validated['phone_country_iso'] ?? null);

        if ($normalizedPhone === '') {
            return response()->json([
                'success' => false,
                'duplicate' => false,
                'message' => 'Enter a valid phone number to check.',
            ], 422);
        }

        $existingLead = $leadDuplicateGuardService->findExistingLeadByPhone($normalizedPhone);

        if (!$existingLead) {
            return response()->json([
                'success' => true,
                'duplicate' => false,
                'normalized_phone' => $normalizedPhone,
                'message' => 'No duplicate lead found for this phone number.',
            ]);
        }

        return response()->json([
            'success' => true,
            'duplicate' => true,
            'normalized_phone' => $normalizedPhone,
            'message' => 'Lead already exists for this phone number.',
            ...$leadDuplicateGuardService->buildDuplicatePayload($existingLead),
        ]);
    }

    public function dashboardQuickSearch(Request $request)
    {
        $user = $request->user();
        if (!$user || (!$user->isAdmin() && !$user->isCrm())) {
            abort(403, 'Only Admin and CRM can use dashboard lead search.');
        }

        $validated = $request->validate([
            'q' => 'required|string|max:255',
        ]);

        $search = trim($validated['q']);
        if ($search === '') {
            return response()->json([
                'success' => true,
                'results' => [],
            ]);
        }

        $phoneDigits = preg_replace('/\D+/', '', $search);

        $results = Lead::query()
            ->select(['id', 'name', 'phone', 'status'])
            ->where(function ($query) use ($search, $phoneDigits) {
                $query->where('name', 'like', "%{$search}%");

                if ($phoneDigits !== '') {
                    $query->orWhere('phone', 'like', "%{$phoneDigits}%");
                } else {
                    $query->orWhere('phone', 'like', "%{$search}%");
                }
            })
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (Lead $lead) => [
                'id' => $lead->id,
                'name' => $lead->name,
                'phone' => $lead->phone,
                'status' => $lead->status,
                'url' => route('leads.show', $lead),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'results' => $results,
        ]);
    }

    public function store(Request $request, LeadDuplicateGuardService $leadDuplicateGuardService)
    {
        $user = $request->user();

        if ($request->filled('source')) {
            $request->merge([
                'source' => Lead::normalizeSource($request->input('source')),
            ]);
        }

        $sourceRule = 'nullable|in:' . implode(',', array_keys(Lead::sourceOptions()));
        
        // Full form: name and phone required; all other fields optional (CRM and non-CRM)
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:30',
            'phone_country_iso' => 'nullable|string|size:2',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'pincode' => 'nullable|string|max:10',
            'category' => 'nullable|string|max:100',
            'preferred_location' => 'nullable|string|max:255',
            'preferred_size' => 'nullable|string|max:255',
            'preferred_projects' => 'nullable|array',
            'preferred_projects.*' => 'nullable|exists:projects,id',
            'interested_projects' => 'nullable',
            'purpose' => 'nullable|string|max:255',
            'use_end_use' => 'nullable|string|in:End User,2nd Investments',
            'budget' => 'nullable|string|max:255',
            'source' => $sourceRule,
            'type' => 'nullable|string|max:255',
            'property_type' => 'nullable|string|max:255',
            'possession' => 'nullable|string|max:255',
            'possession_status' => 'nullable|string|max:255',
            'lead_status' => 'nullable|string|max:50',
            'lead_quality' => 'nullable|string|max:50',
            'customer_job' => 'nullable|string|max:255',
            'industry_sector' => 'nullable|string|max:255',
            'buying_frequency' => 'nullable|string|max:255',
            'living_city' => 'nullable|string|max:255',
            'city_type' => 'nullable|string|max:255',
            'manager_remark' => 'nullable|string',
            'requirements' => 'nullable|string',
            'notes' => 'nullable|string',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        $parsedPhone = app(\App\Services\DuplicateDetectionService::class)
            ->parsedLeadPhone($validated['phone'], $validated['phone_country_iso'] ?? null);
        if (!$parsedPhone) {
            return back()->withErrors(['phone' => 'Enter a valid phone number. Use + country code for non-Indian numbers.'])->withInput();
        }

        return $leadDuplicateGuardService->withPhoneLock($parsedPhone['normalized'], function (string $normalizedPhone) use ($validated, $request, $user, $leadDuplicateGuardService, $parsedPhone) {
            if ($normalizedPhone !== '') {
                $validated['phone'] = $parsedPhone['e164'];
                $validated['normalized_phone'] = $normalizedPhone;
                $validated['phone_country_iso'] = $parsedPhone['country_iso'];
                $existingLead = $leadDuplicateGuardService->findExistingLeadByPhone($normalizedPhone);
                if ($existingLead) {
                    return back()
                        ->withErrors(['phone' => 'Lead already exists for this phone number.'])
                        ->with('duplicate_lead', $leadDuplicateGuardService->buildDuplicatePayload($existingLead))
                        ->withInput();
                }
            }

        DB::beginTransaction();
        try {
            $validated['created_by'] = $user->id;
            $validated['status'] = 'new';
            $interestedProjectNames = $this->parseInterestedProjects($request->input('interested_projects'));
            $matchedProjectIds = $this->resolveProjectIdsFromNames($interestedProjectNames);
            
            // Handle preferred projects array - convert to JSON string (CRM and non-CRM)
            if (!empty($matchedProjectIds)) {
                $validated['preferred_projects'] = json_encode($matchedProjectIds);
            } elseif (isset($validated['preferred_projects']) && is_array($validated['preferred_projects'])) {
                $validated['preferred_projects'] = json_encode($validated['preferred_projects']);
            }

              $validated['source'] = Lead::normalizeSource($validated['source'] ?? 'other');

            $lead = Lead::create($validated);

            $formFields = [
                'source' => $request->filled('source')
                    ? Lead::displaySourceLabel($request->input('source'))
                    : null,
                'category' => $request->input('category'),
                'preferred_location' => $request->input('preferred_location'),
                'budget' => $request->input('budget'),
                'type' => $request->input('type'),
                'purpose' => $request->input('purpose'),
                'possession' => $request->input('possession'),
                'lead_status' => $request->input('lead_status'),
                'lead_quality' => $request->input('lead_quality'),
                'interested_projects' => !empty($interestedProjectNames)
                    ? array_map(fn ($name) => ['name' => (string) $name], $interestedProjectNames)
                    : null,
                'customer_job' => $request->input('customer_job'),
                'industry_sector' => $request->input('industry_sector'),
                'buying_frequency' => $request->input('buying_frequency'),
                'living_city' => $request->input('living_city'),
                'city_type' => $request->input('city_type'),
                'manager_remark' => $request->input('manager_remark'),
            ];

            foreach ($formFields as $fieldKey => $fieldValue) {
                if ($fieldValue === null || $fieldValue === '') {
                    continue;
                }

                $lead->setFormFieldValue($fieldKey, $fieldValue, $user->id);
            }

            // Assign lead if user selected (CRM and non-CRM); auto-creates calling task for assignee
            if ($request->filled('assigned_to')) {
                try {
                    $this->assignLead($lead, (int) $request->assigned_to, $user->id);
                } catch (\Exception $e) {
                    // Pusher/broadcast errors (e.g. 404 when not configured) must not fail lead creation
                    if (str_contains($e->getMessage(), 'Pusher') || str_contains($e->getMessage(), 'broadcast')) {
                        \Illuminate\Support\Facades\Log::warning('Lead assignment broadcast failed; lead and assignment saved.', ['error' => $e->getMessage()]);
                    } else {
                        throw $e;
                    }
                }
            }

            DB::commit();

            if ($user->isCrm()) {
                $msg = $request->filled('assigned_to')
                    ? "Lead '{$lead->name}' created successfully and assigned. A calling task has been created for the assigned user."
                    : "Lead '{$lead->name}' created successfully. You can now fill detailed requirements using the centralized form.";
                return redirect()
                    ->route('leads.show', $lead->id)
                    ->with('success', $msg);
            }

            return redirect()
                ->route('leads.index')
                ->with('success', "Lead '{$lead->name}' created successfully" . ($request->assigned_to ? ' and assigned.' : '.'));

        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->withErrors(['error' => 'Failed to create lead: ' . $e->getMessage()])
                ->withInput();
        }
        });
    }

    public function preview(Request $request, Lead $lead)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $request->attributes->set('lead_details_preview', true);

        return $this->show($request, $lead);
    }

    public function show(Request $request, Lead $lead)
    {
        $ownerTransferUsers = collect();

        try {
            $user = $request->user();
            
            // Explicitly load user role to ensure it's available in the view
            if ($user && !$user->relationLoaded('role')) {
                $user->load('role');
            }

            if ($user?->isAdManager()) {
                abort_unless($user->canAccessLeadSource($lead->source), 403, 'You do not have permission to view this lead.');

                return redirect()->route('ad-manager.leads.show', $lead);
            }

            // Check access permissions
            if (!$this->canAccessLead($user, $lead)) {
                abort(403, 'You do not have permission to view this lead.');
            }

            $layout = 'layouts.app';
            if ($user) {
                if ($user->isAdmin() || $user->isCrm()) {
                    $layout = 'layouts.app';
                } elseif ($user->isSalesHead() && !$user->isAdmin() && !$user->isCrm()) {
                    $layout = 'sales-head.layout';
                } elseif ($user->isSalesManager()) {
                    $layout = 'sales-manager.layout';
                } elseif ($user->isSeniorManager()) {
                    $layout = 'sales-manager.layout';
                } elseif ($user->isAssistantSalesManager()) {
                    $layout = 'sales-manager.layout';
                } elseif ($user->isTelecaller()) {
                    $layout = 'telecaller.layout';
                } elseif ($user->isFinanceManager()) {
                    $layout = 'finance-manager.layout';
                } elseif ($user->relationLoaded('role') && $user->role) {
                    switch ($user->role->slug) {
                        case \App\Models\Role::SALES_MANAGER:
                            $layout = 'sales-manager.layout';
                            break;
                        case \App\Models\Role::SENIOR_MANAGER:
                            $layout = 'sales-manager.layout';
                            break;
                        case \App\Models\Role::ASSISTANT_SALES_MANAGER:
                            $layout = 'sales-manager.layout';
                            break;
                        case \App\Models\Role::SALES_EXECUTIVE:
                        case \App\Models\Role::TELECALLER:
                            $layout = 'telecaller.layout';
                            break;
                        case \App\Models\Role::FINANCE_MANAGER:
                            $layout = 'finance-manager.layout';
                            break;
                        default:
                            $layout = 'layouts.app';
                    }
                }
            }

            // Load all relationships
            $lead->load([
                 'creator',
                 'assignments.assignedTo',
                 'assignments.assignedBy',
                 'activeAssignments.assignedTo',
                 'latestImportedLead.importBatch',
                 'latestFbLead.form',
                 'formFieldValues',
                 'callLogs' => function($query) use ($user) {
                     $query->where('user_id', $user->id)
                           ->orderBy('start_time', 'asc');
                 },
                'siteVisits.creator',
                'siteVisits.assignedTo',
                'siteVisits.verifiedBy',
                'followUps.creator',
                'meetings.creator',
                'meetings.assignedTo',
                'meetings.verifiedBy',
                'prospects.createdBy',
                'prospects.verifiedBy',
                'prospects.interestedProjects',
                'callLogs.user',
                'tasks.assignedTo',
                'wabaCallEvents.assignedTo',
                'wabaCallEvents.telecallerTask',
                'markedDeadBy',
                'verifiedBy',
            ]);

            $this->backfillLeadPhoneFromLatestMetaLead($lead);

            // Get activity timeline
            $activityService = new LeadActivityService();
            $timeline = $activityService->getTimeline($lead, $user);
            $displayLeadNotes = $this->sanitizeLeadNotesForViewer($lead->notes, $user);
            $internalAuditStage = null;
            $internalAuditRemarks = collect();
            if ($user?->isAdmin()) {
                $rowKey = 'lead:' . $lead->id;
                $internalAuditStage = InsightSheetCellOverride::query()
                    ->where('sheet_key', 'master')->where('row_key', $rowKey)->where('column_key', 'internal_stage')->value('value');
                $internalAuditRemarks = InsightSheetCellAudit::query()
                    ->with('editor:id,name')->where('sheet_key', 'master')->where('row_key', $rowKey)->where('column_key', 'internal_remark')
                    ->whereNotNull('new_value')->where('new_value', '!=', '')->latest('edited_at')->get();
            }
            
            // Calculate response time data
            $responseTimeData = $this->calculateResponseTime($lead, $user);

            if ($this->canTransferLeadOwner($user)) {
                $ownerTransferUsers = User::where('is_active', true)
                    ->whereHas('role', function ($q) {
                        $q->whereIn('slug', [
                            Role::SALES_MANAGER,
                            Role::SENIOR_MANAGER,
                            Role::ASSISTANT_SALES_MANAGER,
                            Role::SALES_EXECUTIVE,
                        ]);
                    })
                    ->with('role')
                    ->orderBy('name')
                    ->get();
            }

            $asmOpenTasks = collect();
            if ($user && $this->canViewLeadOpenTasks($user)) {
                $lead->loadMissing('prospects');
                $asmOpenTasks = $this->leadOpenTaskModels($lead, $user);
            }

            $oldTasks = collect();
            if ($user && (($user->isAdmin() || $user->isCrm()) || $this->canViewLeadOpenTasks($user))) {
                $oldTasks = $this->getLeadOldTasks($lead);
            }

            $canViewLeadCallHistory = (bool) ($user && ($user->isAdmin() || $user->isCrm()));
            $leadCallLogs = collect();
            $leadMcubeOutboundAttempts = collect();
            $leadCallSummary = [
                'total_calls' => 0,
                'answered_calls' => 0,
                'unanswered_calls' => 0,
                'rejected_calls' => 0,
                'total_talk_seconds' => 0,
                'last_call_at' => null,
            ];

            if ($canViewLeadCallHistory) {
                $leadPhoneDigits = preg_replace('/\D+/', '', (string) $lead->phone);
                $leadPhoneLastTen = substr($leadPhoneDigits, -10);

                $leadCallLogs = CallLog::query()
                    ->with(['user:id,name', 'telecaller:id,name'])
                    ->where(function ($query) use ($lead, $leadPhoneLastTen) {
                        $query->where('lead_id', $lead->id);

                        if ($leadPhoneLastTen !== '') {
                            $query->orWhere(function ($phoneQuery) use ($leadPhoneLastTen) {
                                $phoneQuery
                                    ->where('phone_number', 'like', '%' . $leadPhoneLastTen);
                            });
                        }
                    })
                    ->orderByDesc('start_time')
                    ->get()
                    ->unique('id')
                    ->values();

                $leadCallSummary = [
                    'total_calls' => $leadCallLogs->count(),
                    'answered_calls' => $leadCallLogs->where('duration', '>', 0)->count(),
                    'unanswered_calls' => $leadCallLogs->filter(fn (CallLog $callLog) => (int) $callLog->duration <= 0)->count(),
                    'rejected_calls' => $leadCallLogs->where('status', 'rejected')->count(),
                    'total_talk_seconds' => (int) $leadCallLogs->sum('duration'),
                    'last_call_at' => optional($leadCallLogs->first())->start_time,
                ];

                $leadMcubeOutboundAttempts = $this->buildLeadMcubeOutboundAttempts($lead, $leadCallLogs);
            }

            $wabaCallEvents = $canViewLeadCallHistory
                ? $lead->wabaCallEvents()
                    ->with(['assignedTo:id,name', 'telecallerTask:id,status'])
                    ->latest('occurred_at')
                    ->latest('id')
                    ->limit(25)
                    ->get()
                : collect();

            $dynamicFormService = app(DynamicFormService::class);
            $leadDetailRequirementsForm = $dynamicFormService->getPublishedFormByLocation('lead-detail.requirements');
            $leadDetailMeetingForm = $dynamicFormService->getPublishedFormByLocation('lead-detail.meeting');
            $leadDetailSiteVisitForm = $dynamicFormService->getPublishedFormByLocation('lead-detail.site-visit');
            $leadDetailFollowUpForm = $dynamicFormService->getPublishedFormByLocation('lead-detail.follow-up');

            $proposalProjects = Project::query()
                ->with(['publicPage', 'publicUnitTypes.sizeVariants'])
                ->where('is_active', true)
                ->whereHas('publicPage', fn ($query) => $query->where('status', 'published'))
                ->orderBy('name')
                ->get();

            $leadProposals = \App\Models\LeadProposal::query()
                ->with(['projects', 'events'])
                ->where('lead_id', $lead->id)
                ->latest()
                ->take(8)
                ->get()
                ->each(function (\App\Models\LeadProposal $proposal) {
                    $proposal->markExpiredIfNeeded();
                    $proposal->refresh();
                });

            $leadProposalSummaries = $leadProposals->mapWithKeys(fn (\App\Models\LeadProposal $proposal) => [
                $proposal->id => $proposal->analyticsSummary(),
            ]);

            $leadProjectShareLinks = \App\Models\ProjectShareLink::query()
                ->with(['project.publicPage', 'project.publicUnitTypes.sizeVariants', 'events'])
                ->where('lead_id', $lead->id)
                ->latest()
                ->take(8)
                ->get();

            return view('leads.show', compact(
                'lead',
                'displayLeadNotes',
                'internalAuditStage',
                'internalAuditRemarks',
                'timeline',
                'responseTimeData',
                'layout',
                'ownerTransferUsers',
                'asmOpenTasks',
                'oldTasks',
                'canViewLeadCallHistory',
                'leadCallLogs',
                'leadMcubeOutboundAttempts',
                'leadCallSummary',
                'wabaCallEvents',
                'leadDetailRequirementsForm',
                'leadDetailMeetingForm',
                'leadDetailSiteVisitForm',
                'leadDetailFollowUpForm',
                'proposalProjects',
                'leadProposals',
                'leadProposalSummaries',
                'leadProjectShareLinks'
            ));
        } catch (\Exception $e) {
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            Log::error('Error loading lead details: ' . $e->getMessage(), [
                'lead_id' => $lead->id ?? null,
                'user_id' => $request->user()?->id,
                'error' => $e->getTraceAsString(),
            ]);

            $user = $request->user();
            if ($this->canTransferLeadOwner($user)) {
                $ownerTransferUsers = User::where('is_active', true)
                    ->whereHas('role', function ($q) {
                        $q->whereIn('slug', [
                            Role::SALES_MANAGER,
                            Role::SENIOR_MANAGER,
                            Role::ASSISTANT_SALES_MANAGER,
                            Role::SALES_EXECUTIVE,
                        ]);
                    })
                    ->with('role')
                    ->orderBy('name')
                    ->get();
            }

            // Return view with error message instead of throwing
            return view('leads.show', [
                'lead' => $lead,
                'displayLeadNotes' => null,
                'timeline' => collect(),
                'responseTimeData' => null,
                'layout' => $layout ?? 'layouts.app',
                'ownerTransferUsers' => $ownerTransferUsers,
                'asmOpenTasks' => collect(),
                'oldTasks' => collect(),
                'canViewLeadCallHistory' => false,
                'leadCallLogs' => collect(),
                'leadMcubeOutboundAttempts' => collect(),
                'leadCallSummary' => [
                    'total_calls' => 0,
                    'answered_calls' => 0,
                    'unanswered_calls' => 0,
                    'total_talk_seconds' => 0,
                    'last_call_at' => null,
                ],
                'wabaCallEvents' => collect(),
                'leadDetailRequirementsForm' => null,
                'leadDetailMeetingForm' => null,
                'leadDetailSiteVisitForm' => null,
                'leadDetailFollowUpForm' => null,
                'proposalProjects' => collect(),
                'leadProposals' => collect(),
                'leadProposalSummaries' => collect(),
                'leadProjectShareLinks' => collect(),
                'error' => 'An error occurred while loading lead details. Please refresh the page.',
            ]);
        }
    }

    private function buildLeadMcubeOutboundAttempts(Lead $lead, $leadCallLogs)
    {
        $leadPhoneLastTen = $this->lastTenDigits($lead->phone);

        $attempts = McubeOutboundAttempt::query()
            ->with('user:id,name')
            ->where(function ($query) use ($lead, $leadPhoneLastTen) {
                $query->where('lead_id', $lead->id);

                if ($leadPhoneLastTen !== '') {
                    $query->orWhere('customer_number', 'like', '%' . $leadPhoneLastTen);
                }
            })
            ->latest('attempted_at')
            ->latest('id')
            ->limit(25)
            ->get();

        return $attempts->map(function (McubeOutboundAttempt $attempt) use ($lead, $leadCallLogs) {
            $apiCallId = $this->extractMcubeApiCallId($attempt->response_payload);
            $matchedWebhook = $this->findMatchedMcubeWebhook($attempt, $apiCallId);
            $matchedCallLog = $this->findMatchedMcubeCallLog($attempt, $apiCallId, $leadCallLogs);
            $recordingUrl = $matchedCallLog?->recording_url ?: $matchedWebhook?->recording_url;
            $status = $this->resolveMcubeAttemptFinalStatus($attempt, $matchedWebhook, $matchedCallLog, $recordingUrl);
            $response = is_array($attempt->response_payload) ? $attempt->response_payload : [];

            return [
                'id' => $attempt->id,
                'user' => $attempt->user,
                'agent_number' => $attempt->agent_number,
                'customer_number' => $attempt->customer_number,
                'attempted_at' => $attempt->attempted_at ?: $attempt->created_at,
                'status' => $attempt->status,
                'http_status' => $attempt->http_status,
                'api_callid' => $apiCallId,
                'api_message' => $attempt->error_message
                    ?: data_get($response, 'message')
                    ?: data_get($response, 'msg')
                    ?: data_get($response, 'status')
                    ?: ($attempt->status === 'success' ? 'Call initiated successfully.' : 'No API message.'),
                'matched_call_log' => $matchedCallLog,
                'matched_webhook' => $matchedWebhook,
                'recording_url' => $recordingUrl,
                'final_status' => $status['key'],
                'final_status_label' => $status['label'],
                'final_status_class' => $status['class'],
            ];
        });
    }

    private function extractMcubeApiCallId($responsePayload): ?string
    {
        $response = is_array($responsePayload) ? $responsePayload : [];
        $callId = data_get($response, 'callid')
            ?: data_get($response, 'call_id')
            ?: data_get($response, 'data.callid');

        if ($callId) {
            return (string) $callId;
        }

        $raw = is_string($responsePayload) ? $responsePayload : json_encode($responsePayload);
        if (preg_match('/[Cc]allid[^0-9]*([0-9]{10,})/', (string) $raw, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function findMatchedMcubeWebhook(McubeOutboundAttempt $attempt, ?string $apiCallId): ?McubeWebhookLog
    {
        if ($apiCallId) {
            $exactWebhook = McubeWebhookLog::query()->where('callid', $apiCallId)->first();
            if ($exactWebhook) {
                return $exactWebhook;
            }
        }

        $attemptedAt = $attempt->attempted_at ?: $attempt->created_at;
        if (!$attemptedAt) {
            return null;
        }

        $customerLastTen = $this->lastTenDigits($attempt->customer_number);
        $agentLastTen = $this->lastTenDigits($attempt->agent_number);

        return McubeWebhookLog::query()
            ->where(function ($query) use ($attempt, $customerLastTen, $agentLastTen) {
                if ($attempt->lead_id) {
                    $query->where('lead_id', $attempt->lead_id);
                }

                if ($customerLastTen !== '') {
                    $query->orWhere('callto', 'like', '%' . $customerLastTen);
                }

                if ($agentLastTen !== '') {
                    $query->orWhere('emp_phone', 'like', '%' . $agentLastTen);
                }
            })
            ->whereBetween('created_at', [
                $attemptedAt->copy()->subMinutes(10),
                $attemptedAt->copy()->addMinutes(30),
            ])
            ->orderByRaw('CASE WHEN recording_url IS NULL OR recording_url = "" THEN 1 ELSE 0 END')
            ->orderBy('created_at')
            ->first();
    }

    private function findMatchedMcubeCallLog(McubeOutboundAttempt $attempt, ?string $apiCallId, $leadCallLogs): ?CallLog
    {
        if ($apiCallId) {
            $exactCallLog = $leadCallLogs->firstWhere('mcube_call_id', $apiCallId)
                ?: CallLog::query()->where('mcube_call_id', $apiCallId)->first();
            if ($exactCallLog) {
                return $exactCallLog;
            }
        }

        $attemptedAt = $attempt->attempted_at ?: $attempt->created_at;
        if (!$attemptedAt) {
            return null;
        }

        $customerLastTen = $this->lastTenDigits($attempt->customer_number);
        $agentLastTen = $this->lastTenDigits($attempt->agent_number);
        $from = $attemptedAt->copy()->subMinutes(10);
        $to = $attemptedAt->copy()->addMinutes(30);

        $matchedFromLoadedLogs = $leadCallLogs
            ->filter(function (CallLog $callLog) use ($attempt, $customerLastTen, $agentLastTen, $from, $to) {
                $startTime = $callLog->start_time ?: $callLog->created_at;
                if (!$startTime || $startTime->lt($from) || $startTime->gt($to)) {
                    return false;
                }

                $sameLead = (int) $callLog->lead_id === (int) $attempt->lead_id;
                $sameCustomer = $customerLastTen !== '' && str_ends_with($this->digitsOnly($callLog->phone_number), $customerLastTen);
                $sameAgent = $agentLastTen !== '' && str_ends_with($this->digitsOnly($callLog->mcube_agent_phone), $agentLastTen);

                return $sameLead || $sameCustomer || $sameAgent;
            })
            ->sortBy(fn (CallLog $callLog) => blank($callLog->recording_url) ? 1 : 0)
            ->first();

        if ($matchedFromLoadedLogs) {
            return $matchedFromLoadedLogs;
        }

        return CallLog::query()
            ->where(function ($query) use ($attempt, $customerLastTen, $agentLastTen) {
                if ($attempt->lead_id) {
                    $query->where('lead_id', $attempt->lead_id);
                }

                if ($customerLastTen !== '') {
                    $query->orWhere('phone_number', 'like', '%' . $customerLastTen);
                }

                if ($agentLastTen !== '') {
                    $query->orWhere('mcube_agent_phone', 'like', '%' . $agentLastTen);
                }
            })
            ->whereBetween('created_at', [$from, $to])
            ->orderByRaw('CASE WHEN recording_url IS NULL OR recording_url = "" THEN 1 ELSE 0 END')
            ->orderBy('created_at')
            ->first();
    }

    private function resolveMcubeAttemptFinalStatus(McubeOutboundAttempt $attempt, ?McubeWebhookLog $webhook, ?CallLog $callLog, ?string $recordingUrl): array
    {
        if ($attempt->status !== 'success') {
            return [
                'key' => 'api_failed',
                'label' => 'API Failed',
                'class' => 'bg-rose-100 text-rose-700',
            ];
        }

        if (filled($recordingUrl)) {
            return [
                'key' => 'recording_available',
                'label' => 'Recording available',
                'class' => 'bg-emerald-100 text-emerald-700',
            ];
        }

        if ($webhook || $callLog) {
            return [
                'key' => 'webhook_recording_missing',
                'label' => 'Webhook received, recording missing',
                'class' => 'bg-amber-100 text-amber-700',
            ];
        }

        $attemptedAt = $attempt->attempted_at ?: $attempt->created_at;
        if ($attemptedAt && $attemptedAt->greaterThan(now()->subMinutes(30))) {
            return [
                'key' => 'initiated_waiting',
                'label' => 'Initiated, waiting',
                'class' => 'bg-blue-100 text-blue-700',
            ];
        }

        return [
            'key' => 'final_callback_not_received',
            'label' => 'Final callback not received',
            'class' => 'bg-slate-100 text-slate-700',
        ];
    }

    private function lastTenDigits(?string $value): string
    {
        return substr($this->digitsOnly($value), -10);
    }

    private function digitsOnly(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?: '';
    }

    public function edit(Request $request, Lead $lead)
    {
        $user = $request->user();

        // Check access permissions
        if (!$this->canAccessLead($user, $lead)) {
            abort(403, 'You do not have permission to edit this lead.');
        }

        // Load lead with form field values
        $lead->load('formFieldValues');

        return view('leads.edit', compact('lead'));
    }

    public function update(Request $request, Lead $lead)
    {
        $user = $request->user();

        // Check access permissions
        if (!$this->canAccessLead($user, $lead)) {
            abort(403, 'You do not have permission to update this lead.');
        }

        $userRole = $user->role->slug;

        if ($request->filled('source')) {
            $request->merge([
                'source' => Lead::normalizeSource($request->input('source')),
            ]);
        }

        if (($user->isAdmin() || $user->isCrm()) && $request->boolean('source_inline_update')) {
            $validated = $request->validate([
                'source' => 'required|in:' . implode(',', array_keys(Lead::sourceOptions())),
            ]);

            $lead->update([
                'source' => Lead::normalizeSource($validated['source']),
            ]);

            $lead->setFormFieldValue('source', Lead::displaySourceLabel($validated['source']), $request->user()->id);

            return redirect()
                ->route('leads.show', $lead->id)
                ->with('success', 'Lead source updated successfully.');
        }

        // Basic lead fields validation (name and phone - always required)
        $phonePrivacy = app(\App\Services\PhonePrivacyService::class);
        $phoneMaskedForUser = $phonePrivacy->shouldMask($user);
        if ($phoneMaskedForUser) {
            $request->merge(['phone' => (string) $lead->getRawOriginal('phone')]);
        }

        $validationRules = [
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:30',
            'phone_country_iso' => 'nullable|string|size:2',
            'source' => 'nullable|in:' . implode(',', array_keys(Lead::sourceOptions())),
        ];

        // Get visible fields for user's role
        $visibleFields = \App\Models\LeadFormField::active()
            ->visibleToRole($userRole)
            ->get();

        // Validate fields dynamically
        foreach ($visibleFields as $field) {
            $rule = [];
            
            if ($field->is_required) {
                $rule[] = 'required';
            } else {
                $rule[] = 'nullable';
            }
            
            // Add field type validation
            switch ($field->field_type) {
                case 'email':
                    $rule[] = 'email';
                    break;
                case 'number':
                    $rule[] = 'numeric';
                    break;
                case 'date':
                    $rule[] = 'date';
                    break;
                case 'time':
                    $rule[] = 'date_format:H:i';
                    break;
            }
            
            $validationRules[$field->field_key] = $rule;
        }

        // Special validation for conditional fields
        if ($request->has('final_status') && $request->final_status === 'Follow Up') {
            $validationRules['follow_up_date'] = ['required', 'date'];
            $validationRules['follow_up_time'] = ['required', 'date_format:H:i'];
        }

        $validated = $request->validate($validationRules);

        $parsedPhone = null;
        if (!$phoneMaskedForUser) {
            $parsedPhone = app(\App\Services\DuplicateDetectionService::class)
                ->parsedLeadPhone($validated['phone'], $validated['phone_country_iso'] ?? null);
            if (!$parsedPhone) {
                return back()->withErrors(['phone' => 'Enter a valid phone number. Use + country code for non-Indian numbers.'])->withInput();
            }
        }

        DB::beginTransaction();
        try {
            // Update basic lead fields (name and phone)
            $lead->name = $validated['name'];
            if ($phoneMaskedForUser) {
                $lead->phone = (string) $lead->getRawOriginal('phone');
            } else {
                $lead->phone = $parsedPhone['e164'];
                $lead->phone_country_iso = $parsedPhone['country_iso'];
            }
            if (isset($validated['source'])) {
                $lead->source = Lead::normalizeSource($validated['source']);
            }
            
            // Save dynamic form field values
            foreach ($visibleFields as $field) {
                if ($request->has($field->field_key)) {
                    $value = $request->input($field->field_key);
                    if ($field->field_key === 'source' && $value !== null && $value !== '') {
                        $value = Lead::displaySourceLabel($value);
                    }
                    // Only save if value is not empty or if it's a required field
                    if (!empty($value) || $field->is_required) {
                        $lead->setFormFieldValue($field->field_key, $value ?? '', $user->id);
                    }
                }
            }

            // Update tracking flags based on role
            if ($userRole === 'sales_executive') {
                $lead->form_filled_by_telecaller = true;
                $lead->form_filled_by_executive = true;
            } elseif (in_array($userRole, ['sales_manager', 'sales_head'])) {
                $lead->form_filled_by_manager = true;
            }

            $lead->save();

            // Handle follow-up task creation
            if (isset($validated['final_status']) && $validated['final_status'] === 'Follow Up' 
                && isset($validated['follow_up_date']) && isset($validated['follow_up_time'])) {
                
                $followUpDateTime = \Carbon\Carbon::parse($validated['follow_up_date'] . ' ' . $validated['follow_up_time']);
                
                // Create follow-up task
                $taskService = app(\App\Services\TelecallerTaskService::class);
                $taskService->createFollowUpTask(
                    $lead,
                    $user->id,
                    $validated['follow_up_date'],
                    $validated['follow_up_time'],
                    $user->id
                );
            }

            DB::commit();

            $roleMessage = [
                'sales_executive' => 'Lead status updated.' . (isset($validated['final_status']) && $validated['final_status'] === 'Follow Up' ? ' Follow-up task created.' : ''),
                'sales_manager' => 'Lead requirements finalized.',
                'crm' => 'All lead requirements saved successfully.',
                'admin' => 'Lead requirements updated successfully.',
                'sales_head' => 'Lead requirements updated successfully.',
            ];

            return redirect()
                ->route('leads.show', $lead->id)
                ->with('success', $roleMessage[$userRole] ?? 'Lead requirements updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->withErrors(['error' => 'Failed to update lead: ' . $e->getMessage()])
                ->withInput();
        }
    }

    public function destroy(Request $request, Lead $lead)
    {
        $user = $request->user();
        if (!$user->isAdmin() && !$user->isCrm()) {
            abort(403, 'Only Admin and CRM can delete leads.');
        }

        DB::transaction(function () use ($lead) {
            app(LeadTaskCleanupService::class)->deleteAllTasksForLead($lead->id, auth()->id(), 'lead_deleted');
            $lead->assignments()->where('is_active', true)->update([
                'is_active' => false,
                'unassigned_at' => now(),
            ]);
            $lead->delete();
        });

        return redirect()
            ->route('leads.index')
            ->with('success', 'Lead deleted successfully.');
    }

    public function bulkDestroy(Request $request)
    {
        $user = $request->user();
        if (!$user->isAdmin() && !$user->isCrm()) {
            abort(403, 'Only Admin and CRM can delete leads.');
        }

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:leads,id'],
        ]);

        $leadIds = collect($validated['ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        DB::transaction(function () use ($leadIds) {
            $leadIdList = $leadIds->all();
            foreach ($leadIdList as $leadId) {
                app(LeadTaskCleanupService::class)->deleteAllTasksForLead($leadId, auth()->id(), 'lead_deleted');
            }
            LeadAssignment::whereIn('lead_id', $leadIdList)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'unassigned_at' => now(),
                ]);
            Lead::whereIn('id', $leadIdList)->delete();
        });

        return redirect()
            ->route('leads.index')
            ->with('success', "{$leadIds->count()} lead(s) deleted successfully.");
    }

    public function bulkChangeOwner(Request $request, LeadAssignmentService $leadAssignmentService)
    {
        $user = $request->user();
        if (!$this->canTransferLeadOwner($user)) {
            abort(403, 'You do not have permission to reassign leads.');
        }

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:leads,id'],
            'assigned_to' => ['required', 'integer', 'exists:users,id'],
            'notes' => ['required', 'string', 'max:1000', 'not_regex:/^\s*$/'],
        ]);

        $assignedUser = User::with('role')->findOrFail((int) $validated['assigned_to']);
        if (!$assignedUser->is_active || !$this->isAllowedLeadOwnerRole($assignedUser->role?->slug)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Please select a valid active owner.'], 422);
            }

            return back()->with('error', 'Please select a valid active owner.');
        }

        $leadIds = collect($validated['ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $transferReason = trim((string) $validated['notes']);

        if (Lead::query()->whereIn('id', $leadIds->all())->where('is_hiring_candidate', true)->exists()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Hiring leads can only be transferred through Send to HR Hiring.'], 422);
            }

            return back()->with('error', 'Hiring leads can only be transferred through Send to HR Hiring.');
        }

        if (!$user->isAdmin() && !$user->isCrm()) {
            $inaccessibleLead = Lead::whereIn('id', $leadIds)
                ->get()
                ->first(fn (Lead $lead) => !$this->canAccessLead($user, $lead));

            if ($inaccessibleLead) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'You can only change owner for leads you can access.'], 403);
                }

                return back()->with('error', 'You can only change owner for leads you can access.');
            }
        }

        $cleanupService = app(LeadTaskCleanupService::class);
        $oldOwnerMap = Lead::whereIn('id', $leadIds)
            ->with('activeAssignments')
            ->get()
            ->mapWithKeys(function (Lead $lead) use ($assignedUser) {
                $ownerIds = $lead->activeAssignments
                    ->pluck('assigned_to')
                    ->filter(fn ($ownerId) => (int) $ownerId !== (int) $assignedUser->id)
                    ->unique()
                    ->values()
                    ->all();

                return [$lead->id => $ownerIds];
            });

        foreach ($oldOwnerMap as $leadId => $ownerIds) {
            foreach ($ownerIds as $ownerId) {
                $cleanupService->deleteTasksForLeadAndOwner((int) $leadId, (int) $ownerId, (int) $user->id, 'lead_transferred');
            }
        }

        $results = $leadAssignmentService->bulkAssignLeads(
            $leadIds->all(),
            $assignedUser->id,
            (int) $user->id,
            true,
            $transferReason
        );

        Lead::whereIn('id', $leadIds)
            ->with('activeAssignments')
            ->get()
            ->each(function (Lead $lead) use ($assignedUser, $user) {
                $activeAssignment = $lead->activeAssignments->first();
                if ($activeAssignment && (int) $activeAssignment->assigned_to === (int) $assignedUser->id) {
                    app(LeadOwnerTaskService::class)->ensureOpenTaskForOwner($lead, $assignedUser, (int) $user->id);
                }
            });

        if (($results['success'] ?? 0) === 0) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => 0,
                    'failed' => $leadIds->count(),
                    'errors' => $results['errors'] ?? ['No leads could be reassigned.'],
                    'message' => $results['errors'][0] ?? 'No leads could be reassigned.',
                ], 422);
            }

            return back()->with('error', $results['errors'][0] ?? 'No leads could be reassigned.');
        }

        $message = "{$results['success']} lead(s) reassigned to {$assignedUser->name}.";
        if (($results['failed'] ?? 0) > 0) {
            $message .= " {$results['failed']} failed.";
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => (int) ($results['success'] ?? 0),
                'failed' => (int) ($results['failed'] ?? 0),
                'errors' => $results['errors'] ?? [],
                'message' => $message,
                'owner' => [
                    'id' => $assignedUser->id,
                    'name' => $assignedUser->name,
                ],
            ]);
        }

        return back()
            ->with('success', $message)
            ->with('warning', !empty($results['errors']) ? implode(' ', array_slice($results['errors'], 0, 5)) : null);
    }

    public function bulkAssignHiring(Request $request)
    {
        $user = $request->user();
        if (!$user || (!$user->isAdmin() && !$user->isCrm())) {
            abort(403, 'Only Admin and CRM can send leads to HR hiring.');
        }

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:leads,id'],
            'hr_user_id' => ['required', 'integer', 'exists:users,id'],
            'hr_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $hrUser = User::with('role')->findOrFail((int) $validated['hr_user_id']);
        if (!$hrUser->is_active || !in_array($hrUser->role?->slug, [Role::HR_MANAGER, Role::JUNIOR_HR], true)) {
            return back()->with('error', 'Please select a valid active HR user.');
        }

        $leadIds = collect($validated['ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $note = trim((string) ($validated['hr_note'] ?? ''));

        DB::transaction(function () use ($leadIds, $hrUser, $user, $note) {
            $newHiringLeadIds = Lead::query()
                ->whereIn('id', $leadIds->all())
                ->where(function ($query) {
                    $query->where('is_hiring_candidate', false)
                        ->orWhereNull('is_hiring_candidate');
                })
                ->pluck('id');

            LeadAssignment::query()
                ->whereIn('lead_id', $leadIds->all())
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'unassigned_at' => now(),
                ]);

            foreach ($leadIds as $leadId) {
                LeadAssignment::create([
                    'lead_id' => $leadId,
                    'assigned_to' => $hrUser->id,
                    'assigned_by' => $user->id,
                    'assignment_type' => 'primary',
                    'assignment_method' => 'manual',
                    'notes' => $note ?: 'Sent to HR hiring queue',
                    'assigned_at' => now(),
                    'is_active' => true,
                ]);
            }

            $leadUpdates = [
                'is_hiring_candidate' => true,
                'updated_at' => now(),
            ];

            if ($note !== '') {
                $leadUpdates['hr_remark'] = $note;
            }

            Lead::query()
                ->whereIn('id', $leadIds->all())
                ->update($leadUpdates);

            if ($newHiringLeadIds->isNotEmpty()) {
                Lead::query()
                    ->whereIn('id', $newHiringLeadIds->all())
                    ->update([
                        'hiring_status' => 'new',
                        'updated_at' => now(),
                    ]);
            }
        });

        try {
            $actionUrl = $hrUser->role?->slug === Role::JUNIOR_HR
                ? route('junior-hr.hiring.index')
                : route('hr-manager.hiring.index');

            AppNotification::create([
                'user_id' => $hrUser->id,
                'type' => AppNotification::TYPE_NEW_LEAD,
                'title' => 'New Hiring Candidates Assigned',
                'message' => "{$leadIds->count()} hiring candidate(s) assigned to you.",
                'action_type' => AppNotification::ACTION_LEAD,
                'action_url' => $actionUrl,
                'data' => [
                    'type' => 'hr_hiring_assignment',
                    'lead_ids' => $leadIds->all(),
                    'assigned_by' => $user->id,
                    'assigned_by_name' => $user->name,
                ],
            ]);
        } catch (\Throwable $notificationError) {
            Log::warning('Failed to create HR hiring assignment notification', [
                'hr_user_id' => $hrUser->id,
                'lead_ids' => $leadIds->all(),
                'error' => $notificationError->getMessage(),
            ]);
        }

        return back()->with('success', "{$leadIds->count()} lead(s) sent to HR Hiring for {$hrUser->name}.");
    }

    public function bulkCreateCallingTasks(
        Request $request,
        BulkCallingTaskService $bulkCallingTaskService,
        NotificationService $notificationService
    ) {
        $user = $request->user();
        if (!$user->isAdmin() && !$user->isCrm()) {
            abort(403, 'Only Admin and CRM can create bulk calling tasks.');
        }

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:leads,id'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'gap_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $startDate = $validated['start_date'] ?? null;
        $startTime = $validated['start_time'] ?? null;
        $startAt = ($startDate && $startTime)
            ? Carbon::createFromFormat('Y-m-d H:i', $startDate . ' ' . $startTime)
            : now()->addMinutes(10)->startOfMinute();

        if ($startAt === false || $startAt->lt(now()->subMinute())) {
            return back()->with('error', 'Start date and time must be in the future.');
        }

        $gapMinutes = (int) ($validated['gap_minutes'] ?? 0);
        $notes = $validated['notes'] ?? null;
        $selectedIds = collect($validated['ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $leads = Lead::query()
            ->with(['activeAssignments' => function ($relation) {
                $relation->with('assignedTo.role')
                    ->where('is_active', true)
                    ->orderByDesc('assigned_at')
                    ->orderByDesc('id');
            }])
            ->whereIn('id', $selectedIds)
            ->get()
            ->keyBy('id');

        $eligibleLeadIdsByUser = [];
        $skippedReasons = [
            'unassigned' => 0,
            'invalid_status' => 0,
            'invalid_owner' => 0,
            'missing_lead' => 0,
        ];

        foreach ($selectedIds as $leadId) {
            /** @var Lead|null $lead */
            $lead = $leads->get($leadId);
            if (!$lead) {
                $skippedReasons['missing_lead']++;
                continue;
            }

            if (in_array((string) $lead->status, ['junk', 'not_interested', 'closed'], true) || (bool) $lead->is_dead) {
                $skippedReasons['invalid_status']++;
                continue;
            }

            $assignment = $lead->activeAssignments->first();
            $assignedUser = $assignment?->assignedTo;

            if (!$assignment || !$assignedUser) {
                $skippedReasons['unassigned']++;
                continue;
            }

            if (!$bulkCallingTaskService->isEligibleAssignee($assignedUser)) {
                $skippedReasons['invalid_owner']++;
                continue;
            }

            $eligibleLeadIdsByUser[$assignedUser->id]['user'] = $assignedUser;
            $eligibleLeadIdsByUser[$assignedUser->id]['lead_ids'][] = $lead->id;
        }

        $totalCreated = 0;
        $totalSkipped = array_sum($skippedReasons);
        $notificationUsers = 0;

        foreach ($eligibleLeadIdsByUser as $payload) {
            /** @var User $assignedUser */
            $assignedUser = $payload['user'];
            $leadIds = collect($payload['lead_ids'] ?? [])->unique()->values()->all();

            $result = $bulkCallingTaskService->createTasks(
                $assignedUser,
                $startAt->copy(),
                $gapMinutes,
                $notes,
                false,
                false,
                [],
                $leadIds
            );

            $created = (int) ($result['created'] ?? 0);
            $skipped = (int) ($result['skipped'] ?? 0);

            $totalCreated += $created;
            $totalSkipped += $skipped;

            foreach (($result['reason_counts'] ?? []) as $reason => $count) {
                $skippedReasons[$reason] = ($skippedReasons[$reason] ?? 0) + (int) $count;
            }

            if ($created > 0) {
                $notificationService->notifyBulkCallingTasksCreated(
                    $assignedUser,
                    $created,
                    $assignedUser->isSalesExecutive() || $assignedUser->isTelecaller()
                        ? url('/telecaller/tasks?status=pending')
                        : url('/sales-manager/tasks?status=pending'),
                    [
                        'lead_ids' => $leadIds,
                        'created_by' => $user->id,
                    ]
                );
                $notificationUsers++;
            }
        }

        if ($totalCreated === 0) {
            return back()->with('error', 'No calling tasks were created for the selected leads.');
        }

        $warningParts = [];
        foreach ($skippedReasons as $reason => $count) {
            if ((int) $count < 1) {
                continue;
            }

            $warningParts[] = match ($reason) {
                'unassigned' => "{$count} unassigned lead(s) skipped",
                'invalid_status' => "{$count} junk/not interested/closed lead(s) skipped",
                'invalid_owner' => "{$count} lead(s) skipped because current owner cannot receive calling tasks",
                'duplicate_open_task' => "{$count} lead(s) already had an open calling task",
                'lead_not_eligible' => "{$count} lead(s) were not eligible",
                'missing_lead' => "{$count} missing lead(s) skipped",
                default => "{$count} lead(s) skipped ({$reason})",
            };
        }

        $message = "{$totalCreated} calling task(s) created.";
        if ($notificationUsers > 0) {
            $message .= " {$notificationUsers} owner notification(s) sent.";
        }

        return back()
            ->with('success', $message)
            ->with('warning', !empty($warningParts) ? implode(' ', $warningParts) : null);
    }

    private function canTransferLeadOwner(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $user->isAdmin()
            || $user->isCrm()
            || $user->isAssistantSalesManager()
            || $user->isSeniorManager()
            || $user->isSalesManager();
    }

    private function canViewCnpQuarantine(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if (
            (method_exists($user, 'isAdmin') && $user->isAdmin())
            || (method_exists($user, 'isCrm') && $user->isCrm())
        ) {
            return true;
        }

        if (!$user->relationLoaded('role')) {
            $user->load('role');
        }

        return in_array($user->role?->slug, [Role::ADMIN, Role::CRM], true);
    }

    private function isAllowedLeadOwnerRole(?string $roleSlug): bool
    {
        return in_array($roleSlug, [
            Role::SALES_MANAGER,
            Role::SENIOR_MANAGER,
            Role::ASSISTANT_SALES_MANAGER,
            Role::SALES_EXECUTIVE,
        ], true);
    }

    public function shortDetails(Request $request, Lead $lead)
    {
        $user = $request->user();

        // Check access permissions
        if (!$this->canAccessLead($user, $lead)) {
            return response()->json(['error' => 'You do not have permission to view this lead.'], 403);
        }

        // Load necessary relationships including prospects with lead_score and all manager relationships
        $lead->load([
            'activeAssignments.assignedTo.role',
            'creator',
            'formFieldValues',
            'prospects' => function($query) {
                $query->whereNotNull('lead_score')
                      ->orderBy('lead_score', 'desc')
                      ->with(['telecaller.role', 'assignedManager.role', 'manager.role', 'verifiedBy.role', 'interestedProjects']);
            }
        ]);

        // Get the highest lead score from prospects
        $leadScore = $lead->prospects->max('lead_score');
        
        // Get form fields array
        $formFields = $lead->getFormFieldsArray();

        return response()->json([
            'data' => $lead,
            'lead_score' => $leadScore,
            'form_fields' => $formFields
        ]);
    }

    /**
     * Calculate response time for current user
     */
    private function calculateResponseTime(Lead $lead, $user): array
    {
        $assignedAt = null;
        $calledAt = null;
        $responseTime = null;
        
        // Get assignment time for current user
        $assignment = $lead->activeAssignments()
            ->where('assigned_to', $user->id)
            ->first();
        
        if ($assignment) {
            $assignedAt = $assignment->assigned_at;
        }
        
        // Get call time - check CallLog first, then CrmAssignment, then Lead.last_contacted_at
        $callLog = $lead->callLogs()
            ->where('user_id', $user->id)
            ->orderBy('start_time', 'asc')
            ->first();
        
        if ($callLog && $callLog->start_time) {
            $calledAt = $callLog->start_time;
        } else {
            // Check CrmAssignment
            $crmAssignment = \App\Models\CrmAssignment::where('lead_id', $lead->id)
                ->where('assigned_to', $user->id)
                ->whereNotNull('called_at')
                ->orderBy('called_at', 'asc')
                ->first();
            
            if ($crmAssignment && $crmAssignment->called_at) {
                $calledAt = $crmAssignment->called_at;
            } elseif ($lead->last_contacted_at) {
                $calledAt = $lead->last_contacted_at;
            }
        }
        
        // Calculate response time
        if ($assignedAt && $calledAt && $calledAt->gt($assignedAt)) {
            $responseTime = $assignedAt->diffInMinutes($calledAt);
        }
        
        return [
            'assigned_at' => $assignedAt,
            'called_at' => $calledAt,
            'response_time_minutes' => $responseTime,
            'has_responded' => $calledAt !== null,
        ];
    }

    private function getLeadOldTasks(Lead $lead)
    {
        return $this->leadOpenTaskModels($lead)
            ->map(function ($task) {
                $isTelecallerTask = $task instanceof TelecallerTask;
                $label = $isTelecallerTask
                    ? ($task->task_type ? ucfirst(str_replace('_', ' ', $task->task_type)) . ' task' : 'Calling task')
                    : ($task->title ?: 'Task');

                return [
                    'id' => $task->id,
                    'model_type' => $task->model_type ?? ($isTelecallerTask ? 'telecaller_task' : 'task'),
                    'task_label' => $label,
                    'title' => $task->title ?? $label,
                    'description' => $task->description,
                    'status' => $task->status,
                    'category' => $task->category,
                    'scheduled_at' => $task->scheduled_at,
                    'assigned_to_name' => $task->assignedTo?->name,
                    'notes' => $task->notes ?: $task->description,
                    'meeting_id' => $task->meeting_id,
                    'site_visit_id' => $task->site_visit_id,
                    'site_visit_project' => $task->site_visit_project ?? null,
                    'follow_up_id' => $task->follow_up_id,
                    'can_complete' => true,
                    'can_delete' => true,
                ];
            })
            ->sortBy([
                ['scheduled_at', 'asc'],
                ['id', 'desc'],
            ])
            ->values();
    }

    private function canAccessLead($user, Lead $lead): bool
    {
        if (session()->has('impersonating_original_id')) {
            $originalUser = User::with('role')->find(session('impersonating_original_id'));
            if ($originalUser && ($originalUser->isAdmin() || $originalUser->isCrm())) {
                return true;
            }
        }

        // Admin and CRM can see all leads
        if ($user->isAdmin() || $user->isCrm()) {
            return true;
        }

        // Sales Head can see leads from their team
        if ($user->isSalesHead()) {
            $teamMemberIds = $user->getAllTeamMemberIds();
            if (!empty($teamMemberIds)) {
                return $lead->isAssignedToAnyUser($teamMemberIds) ||
                    $lead->isVisibleViaProspectFallback($teamMemberIds);
            }
            return false;
        }

        // Senior Manager, Manager, Assistant Sales Manager: can see leads from their team
        if ($user->isSalesManager() || $user->isSeniorManager() || $user->isAssistantSalesManager()) {
            $teamMemberIds = $user->teamMembers()->pluck('id');

            if ($lead->isAssignedToUser($user->id)) {
                return true;
            }

            if ($teamMemberIds->isNotEmpty() && $lead->isAssignedToAnyUser($teamMemberIds)) {
                return true;
            }

            if ($teamMemberIds->isNotEmpty()) {
                return $lead->isVisibleViaProspectFallback($teamMemberIds, function ($prospectQuery) {
                    $prospectQuery->whereIn('verification_status', ['verified', 'approved']);
                });
            }

            return false;
        }

        // Sales Executive can see only assigned leads or leads from their own prospects
        if ($user->isSalesExecutive()) {
            return $lead->isAssignedToUser($user->id) ||
                $lead->isVisibleViaProspectFallback([$user->id]);
        }

        // Dedicated telecallers can see leads allocated to them or currently present in their task queue.
        if ($user->isDedicatedTelecaller()) {
            return $lead->isAssignedToUser($user->id)
                || $lead->tasks()
                    ->where('assigned_to', $user->id)
                    ->whereIn('status', TelecallerTask::OPEN_STATUSES)
                    ->exists()
                || $lead->isVisibleViaProspectFallback([$user->id]);
        }

        if ($user->isFinanceManager()) {
            return $lead->siteVisits()
                ->where('status', 'completed')
                ->whereHas('incentives', function ($query) {
                    $query->where('type', 'closer')->where('status', 'verified');
                })
                ->exists();
        }

        return false;
    }

    private function sanitizeLeadNotesForViewer(?string $notes, ?User $viewer): ?string
    {
        $notes = trim((string) $notes);

        if ($notes === '') {
            return null;
        }

        if ($viewer && ($viewer->isAdmin() || $viewer->isCrm())) {
            return $notes;
        }

        $filteredLines = collect(preg_split('/\r\n|\r|\n/', $notes) ?: [])
            ->map(fn ($line) => trim((string) $line))
            ->filter(function (string $line) {
                if ($line === '') {
                    return false;
                }

                $normalized = strtolower($line);

                foreach ([
                    'asm outcome:',
                    'junk',
                    'not interested',
                    'other leads',
                    'active queue',
                    'reassigned',
                    'transferred',
                ] as $needle) {
                    if (str_contains($normalized, $needle)) {
                        return false;
                    }
                }

                return true;
            })
            ->values();

        return $filteredLines->isNotEmpty() ? $filteredLines->implode("\n") : null;
    }

    private function parseInterestedProjects($rawValue): array
    {
        if (is_string($rawValue)) {
            $decoded = json_decode($rawValue, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $rawValue = $decoded;
            } else {
                $rawValue = [$rawValue];
            }
        }

        if (!is_array($rawValue)) {
            return [];
        }

        return collect($rawValue)
            ->map(function ($project) {
                if (is_array($project)) {
                    return trim((string) ($project['name'] ?? ''));
                }

                return trim((string) $project);
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function resolveProjectIdsFromNames(array $projectNames): array
    {
        if (empty($projectNames)) {
            return [];
        }

        return Project::query()
            ->whereIn('name', $projectNames)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();
    }

    private function buildInterestedProjectOptions($projects, $leadDetailRequirementsForm): array
    {
        $configuredOptions = collect($leadDetailRequirementsForm?->fields ?? [])
            ->firstWhere('field_key', 'interested_projects')
            ?->options ?? [];

        return collect($configuredOptions)
            ->merge(collect($projects)->pluck('name')->all())
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function backfillLeadPhoneFromLatestMetaLead(Lead $lead): void
    {
        $currentPhone = trim((string) ($lead->phone ?? ''));
        if ($currentPhone !== '' && strtoupper($currentPhone) !== 'N/A') {
            return;
        }

        $fbLead = $lead->latestFbLead;
        if (!$fbLead || empty($fbLead->field_data_json) || !is_array($fbLead->field_data_json)) {
            return;
        }

        $fieldData = $fbLead->field_data_json;
        $phoneCandidates = [
            $fieldData['phone'] ?? null,
            $fieldData['phone_number'] ?? null,
            $fieldData['mobile'] ?? null,
            $fieldData['mobile_number'] ?? null,
            $fieldData['whatsapp_number'] ?? null,
            $fieldData['contact_number'] ?? null,
        ];

        $normalizedPhone = '';
        foreach ($phoneCandidates as $candidate) {
            $digits = preg_replace('/\D+/', '', (string) $candidate);
            if ($digits === '') {
                continue;
            }

            $normalizedPhone = strlen($digits) > 10 ? '+' . $digits : $digits;
            break;
        }

        if ($normalizedPhone === '') {
            return;
        }

        $lead->forceFill(['phone' => $normalizedPhone])->saveQuietly();
        $lead->setAttribute('phone', $normalizedPhone);
    }

    private function assignLead(Lead $lead, int $assignedTo, int $assignedBy): void
    {
        $oldOwnerIds = $lead->assignments()
            ->where('is_active', true)
            ->pluck('assigned_to')
            ->filter(fn ($ownerId) => (int) $ownerId !== (int) $assignedTo)
            ->unique()
            ->values();

        $cleanupService = app(LeadTaskCleanupService::class);
        foreach ($oldOwnerIds as $ownerId) {
            $cleanupService->deleteTasksForLeadAndOwner($lead->id, (int) $ownerId, $assignedBy, 'lead_transferred');
        }

        // Deactivate existing assignments
        $lead->assignments()->update(['is_active' => false, 'unassigned_at' => now()]);

        // Create new assignment
        LeadAssignment::create([
            'lead_id' => $lead->id,
            'assigned_to' => $assignedTo,
            'assigned_by' => $assignedBy,
            'assignment_type' => 'primary',
            'assigned_at' => now(),
            'is_active' => true,
        ]);

        if ($oldOwnerIds->isNotEmpty()) {
            $lead->markAsFreshTransfer((int) $oldOwnerIds->first(), $assignedTo, $assignedBy);
        }

        // Fire event (listener creates calling task)
        event(new LeadAssigned($lead, $assignedTo, $assignedBy));

        // Fallback: ensure calling task exists for assignee (admin-assigned leads must show task to user)
        try {
            $assignee = User::with('role')->find($assignedTo);
            if ($assignee && $assignee->role) {
                app(LeadOwnerTaskService::class)->ensureOpenTaskForOwner($lead, $assignee, $assignedBy);
            }
        } catch (\Exception $e) {
            Log::warning("LeadController assignLead: fallback task creation failed for lead {$lead->id}: " . $e->getMessage());
        }
    }
}

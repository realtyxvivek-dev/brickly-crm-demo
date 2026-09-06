<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Prospect;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\ReportExport;
use App\Models\SiteVisit;
use App\Models\User;
use App\Models\Role;
use App\Models\LeadAssignment;
use App\Models\InterestedProjectName;
use App\Services\LeadDisplayStatusResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ExportController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:admin,crm,sales_manager,sales_head');
    }

    /**
     * Display export page with templates and custom options
     */
    public function index(Request $request)
    {
        return $this->renderWorkspace($request, null);
    }

    public function previewReport(Request $request, string $reportKey)
    {
        return $this->renderWorkspace($request, $reportKey);
    }

    public function downloadReport(Request $request, string $reportKey)
    {
        $definition = $this->reportDefinitions()[$reportKey] ?? null;
        abort_unless($definition, 404);

        $request->validate($this->workspaceValidationRules());

        $format = $request->input('format', 'csv') === 'pdf' ? 'pdf' : 'csv';
        $query = $this->buildWorkspaceReportQuery($reportKey, $request, auth()->user());
        // CSV is the complete operational export; do not silently omit older records after 10,000 rows.
        // Large PDFs remain capped because rendering every row as a document is not practical.
        $records = $format === 'pdf'
            ? $query->limit(10000)->get()
            : $query->get();
        $this->applyLeadDisplayStatusesForExport($records);
        $rows = $this->formatWorkspaceReportRows($reportKey, $records, $definition);

        if ($records->isEmpty()) {
            return back()->with('error', 'No records found matching the selected filters.');
        }

        ReportExport::create([
            'report_key' => $reportKey,
            'report_name' => $definition['label'],
            'filters_json' => $this->workspaceFilterPayload($request, $reportKey),
            'format' => $format,
            'generated_by' => auth()->id(),
            'generated_at' => now(),
            'record_count' => $records->count(),
        ]);

        $filename = str_replace('-', '_', $reportKey) . '_report_' . now()->format('Ymd_His');

        if ($format === 'pdf') {
            return $this->downloadWorkspacePdf($definition, $rows, $filename);
        }

        return $this->downloadWorkspaceCsv($definition, $rows, $filename);
    }

    private function renderWorkspace(Request $request, ?string $reportKey)
    {
        $definitions = $this->reportDefinitions();
        $selectedReportKey = $reportKey && isset($definitions[$reportKey]) ? $reportKey : 'all-leads';
        $preview = null;

        if ($reportKey !== null) {
            $request->validate($this->workspaceValidationRules());
            $definition = $definitions[$selectedReportKey];
            $query = $this->buildWorkspaceReportQuery($selectedReportKey, $request, auth()->user());
            $totalRecords = (clone $query)->count();
            $records = $selectedReportKey === 'site-visits'
                ? $query->get()
                : $query->limit(200)->get();
            $this->applyLeadDisplayStatusesForExport($records);

            $preview = [
                'total_records' => $totalRecords,
                'preview_count' => $records->count(),
                'rows' => $this->formatWorkspaceReportRows($selectedReportKey, $records, $definition),
                'summary' => $this->workspaceReportSummary($selectedReportKey, $request, $totalRecords),
            ];
        }

        return view('export.index', [
            'reportDefinitions' => $definitions,
            'selectedReportKey' => $selectedReportKey,
            'preview' => $preview,
            'users' => $this->exportFilterUsers(),
            'projects' => Project::where('is_active', true)->orderBy('name')->get(),
            'interestedProjectNames' => InterestedProjectName::where('is_active', true)->orderBy('name')->get(),
            'sourceOptions' => Lead::sourceOptions(),
            'recentExports' => ReportExport::with('generatedBy')
                ->latest('generated_at')
                ->limit(10)
                ->get(),
            'filters' => $this->workspaceFilterPayload($request, $selectedReportKey),
        ]);
    }

    private function exportFilterUsers()
    {
        return User::where('is_active', true)
            ->whereHas('role', function ($q) {
                $q->whereIn('slug', [Role::SALES_MANAGER, Role::ASSISTANT_SALES_MANAGER, Role::SALES_EXECUTIVE]);
            })
            ->with('role')
            ->orderBy('name')
            ->get();
    }

    private function reportDefinitions(): array
    {
        return [
            'all-leads' => [
                'label' => 'All Leads',
                'icon' => 'fas fa-users',
                'description' => 'Complete lead report with owner and remarks.',
                'model' => Lead::class,
                'date_column' => 'created_at',
                'filters' => ['date', 'owner', 'status', 'source', 'project', 'search'],
                'columns' => [
                    'serial_no' => 'S. No',
                    'created_at' => 'Created Date',
                    'name' => 'Customer Name',
                    'phone' => 'Customer Number',
                    'source' => 'Source',
                    'status' => 'Status',
                            'owner' => 'Owner',
                            'last_assigned_to' => 'Last Assigned To',
                    'latest_remark' => 'Latest Remark',
                    'next_followup_date' => 'Next Follow-up Date',
                ],
            ],
            'site-visits' => [
                'label' => 'Site Visits',
                'icon' => 'fas fa-map-marker-alt',
                'description' => 'Date wise scheduled and completed visit report.',
                'model' => SiteVisit::class,
                'date_column' => 'scheduled_at',
                'filters' => ['date', 'owner', 'status', 'search'],
                'statuses' => ['scheduled' => 'Scheduled', 'completed' => 'Completed', 'rescheduled' => 'Rescheduled', 'cancelled' => 'Cancelled'],
                'default_statuses' => ['scheduled', 'rescheduled', 'completed'],
                'columns' => [
                    'serial_no' => 'S. No',
                    'scheduled_at' => 'Visit Date',
                    'completed_at' => 'Completed Date',
                    'customer_name' => 'Customer Name',
                    'phone' => 'Phone',
                    'visit_sequence' => 'Visit Sequence',
                    'entry_stage' => 'Lead Type / Entry Stage',
                    'status' => 'Status',
                    'current_lead_status' => 'Current Lead Status',
                    'latest_outcome' => 'Latest Call Outcome',
                    'next_followup_at' => 'Next Follow-up',
                    'owner' => 'Assigned To',
                    'closer_status' => 'Closer Status',
                    'queue_state' => 'Queue State',
                    'location' => 'Location',
                ],
            ],
            'meetings' => [
                'label' => 'Meetings',
                'icon' => 'fas fa-calendar-check',
                'description' => 'Scheduled, completed and rescheduled meeting report.',
                'model' => Meeting::class,
                'date_column' => 'scheduled_at',
                'filters' => ['date', 'owner', 'status', 'search'],
                'statuses' => ['scheduled' => 'Scheduled', 'completed' => 'Completed', 'rescheduled' => 'Rescheduled', 'cancelled' => 'Cancelled'],
                'columns' => [
                    'serial_no' => 'S. No',
                    'scheduled_at' => 'Meeting Date',
                    'customer_name' => 'Customer Name',
                    'phone' => 'Phone',
                    'owner' => 'Assigned To',
                    'status' => 'Status',
                    'created_by' => 'Created By',
                ],
            ],
            'prospects' => [
                'label' => 'Prospects',
                'icon' => 'fas fa-user-check',
                'description' => 'Prospect verification report.',
                'model' => Prospect::class,
                'date_column' => 'created_at',
                'filters' => ['date', 'owner', 'status', 'search'],
                'statuses' => ['pending_verification' => 'Pending Verification', 'verified' => 'Verified', 'approved' => 'Approved', 'rejected' => 'Rejected'],
                'columns' => [
                    'serial_no' => 'S. No',
                    'created_at' => 'Created Date',
                    'customer_name' => 'Customer Name',
                    'phone' => 'Phone',
                    'verification_status' => 'Verification Status',
                    'assigned_manager' => 'Assigned Manager',
                    'created_by' => 'Created By',
                ],
            ],
            'closed-leads' => [
                'label' => 'Closed Leads',
                'icon' => 'fas fa-check-circle',
                'description' => 'Closed lead report by close/update date.',
                'model' => Lead::class,
                'date_column' => 'updated_at',
                'filters' => ['date', 'owner', 'source', 'project', 'search'],
                'fixed_status' => 'closed',
                'columns' => [
                    'serial_no' => 'S. No',
                    'updated_at' => 'Closed Date',
                    'name' => 'Customer Name',
                    'phone' => 'Customer Number',
                    'source' => 'Source',
                    'status' => 'Status',
                    'owner' => 'Owner',
                    'latest_remark' => 'Latest Remark',
                ],
            ],
            'dead-leads' => [
                'label' => 'Dead/Junk Leads',
                'icon' => 'fas fa-times-circle',
                'description' => 'Dead, junk and inactive lead report.',
                'model' => Lead::class,
                'date_column' => 'marked_dead_at',
                'filters' => ['date', 'owner', 'source', 'project', 'search'],
                'dead_report' => true,
                'columns' => [
                    'serial_no' => 'S. No',
                    'marked_dead_at' => 'Marked Date',
                    'name' => 'Customer Name',
                    'phone' => 'Customer Number',
                    'source' => 'Source',
                    'status' => 'Status',
                    'owner' => 'Owner',
                    'dead_reason' => 'Reason',
                ],
            ],
            'source-project' => [
                'label' => 'Source / Project Wise',
                'icon' => 'fas fa-chart-pie',
                'description' => 'Lead report grouped by source or interested project filter.',
                'model' => Lead::class,
                'date_column' => 'created_at',
                'filters' => ['date', 'owner', 'status', 'source', 'project', 'search'],
                'columns' => [
                    'serial_no' => 'S. No',
                    'created_at' => 'Created Date',
                    'name' => 'Customer Name',
                    'phone' => 'Customer Number',
                    'source' => 'Source',
                    'status' => 'Status',
                    'owner' => 'Owner',
                    'interested_projects' => 'Interested Projects',
                ],
            ],
        ];
    }

    private function workspaceValidationRules(): array
    {
        return [
            'date_range' => 'nullable|in:all_time,today,this_week,this_month,previous_month,this_year,custom',
            'start_date' => 'required_if:date_range,custom|nullable|date',
            'end_date' => 'required_if:date_range,custom|nullable|date|after_or_equal:start_date',
            'user_id' => 'nullable|integer',
            'status' => 'nullable|array',
            'status.*' => 'nullable|string|max:80',
            'source' => 'nullable|string|max:80',
            'project_id' => 'nullable|integer',
            'search' => 'nullable|string|max:120',
            'exclude_favorites' => 'nullable|boolean',
            'exclude_site_visits' => 'nullable|boolean',
            'exclude_closed_leads' => 'nullable|boolean',
            'format' => 'nullable|in:csv,pdf',
        ];
    }

    private function workspaceFilterPayload(Request $request, string $reportKey): array
    {
        $definition = $this->reportDefinitions()[$reportKey] ?? [];

        return [
            'date_range' => $request->input('date_range', 'this_month'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'user_id' => $request->input('user_id'),
            'status' => $this->workspaceSelectedStatuses($definition, $request),
            'source' => $request->input('source'),
            'project_id' => $request->input('project_id'),
            'search' => $request->input('search'),
            'exclude_favorites' => $this->supportsFavoriteExclusion($reportKey)
                ? ($this->shouldExcludeFavorites($reportKey, $request) ? '1' : '0')
                : null,
            'exclude_site_visits' => $reportKey === 'all-leads'
                ? ($this->shouldExcludeSiteVisits($reportKey, $request) ? '1' : '0')
                : null,
            'exclude_closed_leads' => $reportKey === 'all-leads'
                ? ($this->shouldExcludeClosedLeads($reportKey, $request) ? '1' : '0')
                : null,
        ];
    }

    private function reportsWithFavoriteExclusion(): array
    {
        return ['all-leads', 'site-visits', 'closed-leads'];
    }

    private function supportsFavoriteExclusion(string $reportKey): bool
    {
        return in_array($reportKey, $this->reportsWithFavoriteExclusion(), true);
    }

    private function shouldExcludeFavorites(string $reportKey, Request $request): bool
    {
        if (!$this->supportsFavoriteExclusion($reportKey)) {
            return false;
        }

        if (!$request->has('exclude_favorites')) {
            return true;
        }

        return $request->boolean('exclude_favorites');
    }

    private function shouldExcludeSiteVisits(string $reportKey, Request $request): bool
    {
        if ($reportKey !== 'all-leads') {
            return false;
        }

        if (!$request->has('exclude_site_visits')) {
            return true;
        }

        return $request->boolean('exclude_site_visits');
    }

    private function shouldExcludeClosedLeads(string $reportKey, Request $request): bool
    {
        if ($reportKey !== 'all-leads') {
            return false;
        }

        if (!$request->has('exclude_closed_leads')) {
            return true;
        }

        return $request->boolean('exclude_closed_leads');
    }

    private function workspaceSelectedStatuses(array $definition, Request $request): array
    {
        $statuses = array_values(array_filter((array) $request->input('status', [])));

        if (empty($statuses) && !empty($definition['default_statuses'])) {
            return $definition['default_statuses'];
        }

        return $statuses;
    }

    private function buildWorkspaceReportQuery(string $reportKey, Request $request, User $user)
    {
        $definition = $this->reportDefinitions()[$reportKey] ?? null;
        abort_unless($definition, 404);

        $query = match ($definition['model']) {
                Lead::class => Lead::with(['creator', 'activeAssignments.assignedTo', 'latestAssignment.assignedTo', 'prospects.interestedProjects', 'followUps', 'tasks']),
            SiteVisit::class => SiteVisit::withoutGlobalScope('visible_in_queue')->with([
                'lead.latestPhoneCallTask' => fn ($task) => $task->withoutGlobalScope('visible_in_queue'),
                'assignedTo',
                'creator',
            ]),
            Meeting::class => Meeting::with(['lead', 'assignedTo', 'creator']),
            Prospect::class => Prospect::with(['lead', 'telecaller', 'assignedManager', 'createdBy']),
            default => abort(404),
        };

        if ($reportKey === 'all-leads' && $definition['model'] === Lead::class) {
            $query->visibleInAllLeadsInventory();
        }

        $this->applyWorkspaceRoleScope($query, $definition['model'], $user);
        $this->applyWorkspaceFixedScope($query, $definition);
        $this->applyWorkspaceFilters($query, $definition, $request);
        $this->applyWorkspaceExcludeFilters($query, $reportKey, $definition, $request);

        return $query->latest($this->workspaceDateColumn($definition, $request));
    }

    private function applyWorkspaceRoleScope($query, string $model, User $user): void
    {
        if ($user->isAdmin() || $user->isCrm()) {
            return;
        }

        $teamMemberIds = [];
        if ($user->isSalesHead()) {
            $teamMemberIds = $user->getAllTeamMemberIds();
        } elseif ($user->isSalesManager()) {
            $teamMemberIds = $user->teamMembers()->pluck('id')->toArray();
        }

        if (empty($teamMemberIds)) {
            $query->whereRaw('1 = 0');
            return;
        }

        if ($model === Lead::class) {
            $query->whereHas('activeAssignments', fn ($assignment) => $assignment->whereIn('assigned_to', $teamMemberIds));
            return;
        }

        if ($model === Prospect::class) {
            $query->whereIn('telecaller_id', $teamMemberIds);
            return;
        }

        $query->whereIn('assigned_to', $teamMemberIds);
    }

    private function applyWorkspaceFixedScope($query, array $definition): void
    {
        if (!empty($definition['fixed_status'])) {
            $query->where('status', $definition['fixed_status']);
        }

        if (!empty($definition['dead_report'])) {
            $query->where(function ($deadQuery) {
                $deadQuery->where('status', 'dead')
                    ->orWhere('status', 'junk')
                    ->orWhere('is_dead', true);
            });
        }
    }

    private function applyWorkspaceFilters($query, array $definition, Request $request): void
    {
        $filters = $definition['filters'] ?? [];

        if (in_array('date', $filters, true) && $request->input('date_range', 'this_month') !== 'all_time') {
            $dateRange = $this->getDateRange($request->input('date_range', 'this_month'), $request);
            if ($dateRange) {
                $query->whereBetween($this->workspaceDateColumn($definition, $request), [$dateRange['start_date'], $dateRange['end_date']]);
            }
        }

        if (in_array('owner', $filters, true) && $request->filled('user_id')) {
            $this->applyWorkspaceOwnerFilter($query, $definition['model'], (int) $request->input('user_id'));
        }

        $statuses = $this->workspaceSelectedStatuses($definition, $request);
        if (in_array('status', $filters, true) && !empty($statuses)) {
            if ($definition['model'] === Prospect::class) {
                $query->whereIn('verification_status', $statuses);
            } else {
                $query->whereIn('status', $statuses);
            }
        }

        if (in_array('source', $filters, true) && $request->filled('source') && $definition['model'] === Lead::class) {
            $query->where('source', Lead::normalizeSource((string) $request->input('source')));
        }

        if (in_array('project', $filters, true) && $request->filled('project_id') && $definition['model'] === Lead::class) {
            $query->whereHas('prospects.interestedProjects', function ($projectQuery) use ($request) {
                $projectQuery->where('interested_project_names.id', (int) $request->input('project_id'));
            });
        }

        if (in_array('search', $filters, true) && $request->filled('search')) {
            $this->applyWorkspaceSearchFilter($query, $definition['model'], trim((string) $request->input('search')));
        }
    }

    private function applyWorkspaceExcludeFilters($query, string $reportKey, array $definition, Request $request): void
    {
        if ($this->shouldExcludeFavorites($reportKey, $request)) {
            if (in_array($reportKey, ['all-leads', 'closed-leads'], true)
                && ($definition['model'] ?? null) === Lead::class) {
                $query->whereDoesntHave('favorites');
            }

            if ($reportKey === 'site-visits'
                && ($definition['model'] ?? null) === SiteVisit::class) {
                $query->whereDoesntHave('lead.favorites');
            }
        }

        if ($reportKey !== 'all-leads' || ($definition['model'] ?? null) !== Lead::class) {
            return;
        }

        if ($this->shouldExcludeSiteVisits($reportKey, $request)) {
            $query->whereDoesntHave('siteVisits', function ($visitQuery) {
                $visitQuery->whereIn('status', ['scheduled', 'rescheduled', 'completed']);
            });
        }

        if ($this->shouldExcludeClosedLeads($reportKey, $request)) {
            $query->where('status', '!=', 'closed');
        }
    }

    private function applyWorkspaceOwnerFilter($query, string $model, int $userId): void
    {
        if ($model === Lead::class) {
            $query->whereHas('activeAssignments', fn ($assignment) => $assignment->where('assigned_to', $userId));
            return;
        }

        if ($model === Prospect::class) {
            $query->where('telecaller_id', $userId);
            return;
        }

        $query->where('assigned_to', $userId);
    }

    private function applyWorkspaceSearchFilter($query, string $model, string $search): void
    {
        if ($search === '') {
            return;
        }

        if ($model === Lead::class) {
            $query->where(function ($leadQuery) use ($search) {
                $leadQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
            return;
        }

        $query->where(function ($recordQuery) use ($search) {
            $recordQuery->where('customer_name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhereHas('lead', function ($leadQuery) use ($search) {
                    $leadQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
        });
    }

    private function workspaceDateColumn(array $definition, Request $request): string
    {
        $statuses = $this->workspaceSelectedStatuses($definition, $request);

        if (($definition['model'] ?? null) === SiteVisit::class && count($statuses) === 1 && $statuses[0] === 'completed') {
            return 'completed_at';
        }

        return $definition['date_column'];
    }

    private function workspaceReportSummary(string $reportKey, Request $request, int $totalRecords): array
    {
        $definition = $this->reportDefinitions()[$reportKey];
        if (isset($definition['statuses'])) {
            $summary = [['label' => 'Total Records', 'value' => $totalRecords]];
            foreach (array_slice($definition['statuses'], 0, 2, true) as $status => $label) {
                $statusRequest = Request::create('', 'GET', array_merge($request->all(), ['status' => [$status]]));
                $summary[] = [
                    'label' => $label,
                    'value' => $this->buildWorkspaceReportQuery($reportKey, $statusRequest, auth()->user())->count(),
                ];
            }
            $summary[] = [
                'label' => 'Preview Rows',
                'value' => $reportKey === 'site-visits' ? $totalRecords : min(200, $totalRecords),
            ];
            if ($this->supportsFavoriteExclusion($reportKey)) {
                $summary[] = [
                    'label' => 'Favourite Leads Excluded',
                    'value' => $this->shouldExcludeFavorites($reportKey, $request) ? 'Yes' : 'No',
                ];
            }
            return $summary;
        }

        $summary = [
            ['label' => 'Total Records', 'value' => $totalRecords],
            ['label' => 'Preview Rows', 'value' => min(200, $totalRecords)],
            ['label' => 'CSV Ready', 'value' => 'Yes'],
            ['label' => 'PDF Ready', 'value' => 'Yes'],
        ];

        if ($this->supportsFavoriteExclusion($reportKey)) {
            $summary[] = [
                'label' => 'Favourite Leads Excluded',
                'value' => $this->shouldExcludeFavorites($reportKey, $request) ? 'Yes' : 'No',
            ];
        }

        if ($reportKey === 'all-leads') {
            $summary[] = [
                'label' => 'Site Visit Leads Excluded',
                'value' => $this->shouldExcludeSiteVisits($reportKey, $request) ? 'Yes' : 'No',
            ];
            $summary[] = [
                'label' => 'Closed Leads Excluded',
                'value' => $this->shouldExcludeClosedLeads($reportKey, $request) ? 'Yes' : 'No',
            ];
        }

        return $summary;
    }

    private function formatWorkspaceReportRows(string $reportKey, $records, array $definition): array
    {
        return $records->values()->map(function ($record, int $index) use ($reportKey, $definition) {
            $row = [];
            foreach ($definition['columns'] as $key => $label) {
                $row[$key] = $this->workspaceColumnValue($reportKey, $record, $key, $index + 1);
            }
            return $row;
        })->all();
    }

    private function workspaceColumnValue(string $reportKey, $record, string $key, int $serial): string
    {
        if ($key === 'serial_no') {
            return (string) $serial;
        }

        if ($record instanceof Lead) {
            return $this->workspaceLeadValue($record, $key);
        }

        if ($record instanceof SiteVisit) {
            return $this->workspaceSiteVisitValue($record, $key);
        }

        if ($record instanceof Meeting) {
            return $this->workspaceMeetingValue($record, $key);
        }

        if ($record instanceof Prospect) {
            return $this->workspaceProspectValue($record, $key);
        }

        return '';
    }

    private function workspaceLeadValue(Lead $lead, string $key): string
    {
        return match ($key) {
            'created_at', 'updated_at', 'marked_dead_at' => $lead->{$key} ? $lead->{$key}->format('Y-m-d H:i') : '',
            'source' => Lead::displaySourceLabel($lead->source),
            'status' => app(LeadDisplayStatusResolver::class)->label($lead),
            'owner' => $lead->activeAssignments->first()?->assignedTo?->name ?? 'Unassigned',
            'last_assigned_to' => $this->getLastAssignedTo($lead),
            'latest_remark' => $this->getLatestLeadRemark($lead),
            'next_followup_date' => $this->getNextFollowUpDate($lead),
            'interested_projects' => $this->getLeadFieldValue($lead, 'interested_projects'),
            default => (string) ($lead->{$key} ?? ''),
        };
    }

    private function workspaceSiteVisitValue(SiteVisit $visit, string $key): string
    {
        return match ($key) {
            'scheduled_at' => $visit->scheduled_at ? $visit->scheduled_at->format('Y-m-d H:i') : '',
            'completed_at' => $visit->completed_at ? $visit->completed_at->format('Y-m-d H:i') : '',
            'customer_name' => (string) ($visit->lead?->name ?? $visit->customer_name ?? ''),
            'phone' => (string) ($visit->lead?->phone ?? $visit->phone ?? ''),
            'visit_sequence' => match ($visit->visit_sequence) {
                'fresh_visit' => 'Fresh Visit',
                '2nd_visit' => '2nd Visit',
                '3rd_visit' => '3rd Visit',
                default => 'Not Specified',
            },
            'entry_stage' => (string) ($visit->lead_type ?: 'Not Specified'),
            'status' => ucfirst(str_replace('_', ' ', (string) $visit->status)),
            'current_lead_status' => $visit->lead ? app(LeadDisplayStatusResolver::class)->label($visit->lead) : 'N/A',
            'latest_outcome' => $this->workspaceLatestCallOutcome($visit->lead),
            'next_followup_at' => $this->workspaceNextFollowUp($visit->lead),
            'owner' => (string) ($visit->assignedTo?->name ?? ''),
            'closer_status' => ucfirst(str_replace('_', ' ', (string) ($visit->closer_status ?? ''))),
            'queue_state' => $this->workspaceSiteVisitQueueState($visit),
            'location' => (string) ($visit->property_address ?? $visit->property_name ?? ''),
            default => (string) ($visit->{$key} ?? ''),
        };
    }

    private function workspaceLatestCallOutcome(?Lead $lead): string
    {
        $outcome = trim((string) ($lead?->latestPhoneCallTask?->outcome ?? ''));

        return $outcome === '' ? 'N/A' : ucwords(str_replace('_', ' ', $outcome));
    }

    private function workspaceNextFollowUp(?Lead $lead): string
    {
        $next = $lead?->next_followup_at ?? $lead?->latestPhoneCallTask?->next_action_at;

        return $next ? Carbon::parse($next)->format('Y-m-d H:i') : 'N/A';
    }

    private function workspaceSiteVisitQueueState(SiteVisit $visit): string
    {
        if (!$visit->queue_hidden_at) {
            return 'Visible';
        }

        return match ($visit->queue_hidden_reason) {
            'superseded_by_follow_up' => 'Hidden after follow-up',
            'superseded_by_site_visit' => 'Hidden after next site visit',
            default => 'Hidden',
        };
    }

    private function workspaceMeetingValue(Meeting $meeting, string $key): string
    {
        return match ($key) {
            'scheduled_at' => $meeting->scheduled_at ? $meeting->scheduled_at->format('Y-m-d H:i') : '',
            'customer_name' => (string) ($meeting->lead?->name ?? $meeting->customer_name ?? ''),
            'phone' => (string) ($meeting->lead?->phone ?? $meeting->phone ?? ''),
            'owner' => (string) ($meeting->assignedTo?->name ?? ''),
            'status' => ucfirst(str_replace('_', ' ', (string) $meeting->status)),
            'created_by' => (string) ($meeting->creator?->name ?? ''),
            default => (string) ($meeting->{$key} ?? ''),
        };
    }

    private function workspaceProspectValue(Prospect $prospect, string $key): string
    {
        return match ($key) {
            'created_at' => $prospect->created_at ? $prospect->created_at->format('Y-m-d H:i') : '',
            'customer_name' => (string) ($prospect->lead?->name ?? $prospect->customer_name ?? ''),
            'phone' => (string) ($prospect->lead?->phone ?? $prospect->phone ?? ''),
            'verification_status' => ucfirst(str_replace('_', ' ', (string) ($prospect->verification_status ?? ''))),
            'assigned_manager' => (string) ($prospect->assignedManager?->name ?? ''),
            'created_by' => (string) ($prospect->createdBy?->name ?? ''),
            default => (string) ($prospect->{$key} ?? ''),
        };
    }

    private function downloadWorkspaceCsv(array $definition, array $rows, string $filename)
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, array_values($definition['columns']));

        foreach ($rows as $row) {
            fputcsv($handle, array_values($row));
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '.csv"');
    }

    private function downloadWorkspacePdf(array $definition, array $rows, string $filename)
    {
        $html = view('export.pdf.report-workspace', [
            'definition' => $definition,
            'rows' => $rows,
        ])->render();

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->download($filename . '.pdf');
        }

        if (class_exists('PDF')) {
            return \PDF::loadHTML($html)->download($filename . '.pdf');
        }

        return response($html)
            ->header('Content-Type', 'text/html')
            ->header('Content-Disposition', 'inline; filename="' . $filename . '.html"');
    }

    /**
     * Export leads with custom filters
     */
    public function exportLeads(Request $request)
    {
        $request->validate([
            'format' => 'required|in:csv,pdf',
            'fields' => 'required|array|min:1',
            'interested_projects' => 'nullable|array',
            'interested_projects.*' => 'exists:interested_project_names,id',
        ]);

        $user = auth()->user();
        $query = $this->buildLeadQuery($user, $request);

        $leads = $query->get();
        $this->applyLeadDisplayStatusesForExport($leads);

        if ($leads->isEmpty()) {
            return back()->with('error', 'No leads found matching the selected filters.');
        }

        // Limit to 10000 records
        if ($leads->count() > 10000) {
            return back()->with('error', 'Export limit exceeded. Please refine your filters. Maximum 10,000 records allowed.');
        }

        if ($request->format === 'csv') {
            return $this->exportLeadsToCsv($leads, $request->fields);
        } else {
            return $this->exportLeadsToPdf($leads, $request->fields);
        }
    }

    public function previewAllLeadExport(Request $request)
    {
        $request->validate([
            'date_range' => 'nullable|in:all_time,today,this_week,this_month,this_year',
            'user_id' => 'nullable|integer',
            'search' => 'nullable|string|max:120',
        ]);

        $fields = ['serial_no', 'created_at', 'name', 'phone', 'source', 'status', 'crm_advisor', 'last_assigned_to', 'latest_remark', 'next_followup_date'];

        $previewRequest = Request::create('', 'GET', array_merge($request->all(), [
            'fields' => $fields,
            'format' => 'csv',
        ]));

        $query = $this->buildLeadQuery(auth()->user(), $previewRequest);
        $totalRecords = (clone $query)->count();
        $leads = $query->latest('created_at')->limit(200)->get();
        $this->applyLeadDisplayStatusesForExport($leads);

        $rows = $leads->values()->map(function ($lead, $index) use ($fields) {
            $row = [];
            foreach ($fields as $field) {
                $row[$field] = $this->getLeadFieldValue($lead, $field, $index + 1);
            }
            return $row;
        });

        return view('export.all-lead-preview', [
            'fields' => $fields,
            'headers' => array_map(fn ($field) => $this->getLeadFieldLabels()[$field] ?? $field, $fields),
            'rows' => $rows,
            'totalRecords' => $totalRecords,
            'previewCount' => $rows->count(),
            'filters' => [
                'date_range' => $request->input('date_range', 'this_month'),
                'user_id' => $request->input('user_id'),
                'search' => $request->input('search'),
            ],
            'users' => User::where('is_active', true)
                ->whereHas('role', function ($q) {
                    $q->whereIn('slug', [Role::SALES_MANAGER, Role::ASSISTANT_SALES_MANAGER, Role::SALES_EXECUTIVE]);
                })
                ->orderBy('name')
                ->get(),
        ]);
    }

    /**
     * Quick export prospects
     */
    public function exportProspects(Request $request)
    {
        $request->validate([
            'format' => 'required|in:csv,pdf',
            'status' => 'nullable|array',
        ]);

        $user = auth()->user();
        $query = Prospect::with(['telecaller', 'lead', 'assignedManager', 'createdBy']);

        // Apply role-based filtering
        if ($user->isSalesManager() || $user->isSalesHead()) {
            $teamMemberIds = $user->isSalesHead() 
                ? $user->getAllTeamMemberIds()
                : $user->teamMembers()->pluck('id')->toArray();
            
            if (!empty($teamMemberIds)) {
                $query->whereIn('telecaller_id', $teamMemberIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        // Apply status filter
        if ($request->has('status') && !empty($request->status)) {
            $query->whereIn('verification_status', $request->status);
        }

        // Apply date range
        if ($request->has('date_range') && $request->date_range !== 'all_time') {
            $dateRange = $this->getDateRange($request->date_range);
            if ($dateRange) {
                $query->whereBetween('created_at', [$dateRange['start_date'], $dateRange['end_date']]);
            }
        }

        $prospects = $query->get();

        if ($prospects->isEmpty()) {
            return back()->with('error', 'No prospects found matching the selected filters.');
        }

        if ($request->format === 'csv') {
            return $this->exportProspectsToCsv($prospects);
        } else {
            return $this->exportProspectsToPdf($prospects);
        }
    }

    /**
     * Quick export meetings
     */
    public function exportMeetings(Request $request)
    {
        $request->validate([
            'format' => 'required|in:csv,pdf',
            'status' => 'nullable|array',
        ]);

        $user = auth()->user();
        $query = Meeting::with(['lead', 'assignedTo', 'creator'])
            ->where('is_converted', false); // Exclude converted meetings

        // Apply role-based filtering
        if ($user->isSalesManager() || $user->isSalesHead()) {
            $teamMemberIds = $user->isSalesHead() 
                ? $user->getAllTeamMemberIds()
                : $user->teamMembers()->pluck('id')->toArray();
            
            if (!empty($teamMemberIds)) {
                $query->whereIn('assigned_to', $teamMemberIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        // Apply status filter
        if ($request->has('status') && !empty($request->status)) {
            $query->whereIn('status', $request->status);
        }

        // Apply date range
        if ($request->has('date_range') && $request->date_range !== 'all_time') {
            $dateRange = $this->getDateRange($request->date_range);
            if ($dateRange) {
                $query->whereBetween('scheduled_at', [$dateRange['start_date'], $dateRange['end_date']]);
            }
        }

        $meetings = $query->get();

        if ($meetings->isEmpty()) {
            return back()->with('error', 'No meetings found matching the selected filters.');
        }

        if ($request->format === 'csv') {
            return $this->exportMeetingsToCsv($meetings);
        } else {
            return $this->exportMeetingsToPdf($meetings);
        }
    }

    /**
     * Quick export site visits
     */
    public function exportSiteVisits(Request $request)
    {
        $request->validate([
            'format' => 'required|in:csv,pdf',
            'visit_type' => 'nullable|array',
            'status' => 'nullable|array',
            'date_range' => 'nullable|string',
            'start_date' => 'required_if:date_range,custom|nullable|date',
            'end_date' => 'required_if:date_range,custom|nullable|date|after_or_equal:start_date',
        ]);

        $user = auth()->user();
        $query = SiteVisit::withoutGlobalScope('visible_in_queue')->with(['lead', 'assignedTo', 'creator']);

        // Apply role-based filtering
        if ($user->isSalesManager() || $user->isSalesHead()) {
            $teamMemberIds = $user->isSalesHead() 
                ? $user->getAllTeamMemberIds()
                : $user->teamMembers()->pluck('id')->toArray();
            
            if (!empty($teamMemberIds)) {
                $query->whereIn('assigned_to', $teamMemberIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        // Apply visit type filter
        if ($request->has('visit_type') && !empty($request->visit_type)) {
            $query->whereIn('lead_type', $request->visit_type);
        }

        // Apply status filter
        if ($request->has('status') && !empty($request->status)) {
            $query->whereIn('status', $request->status);
        }

        // Apply date range
        if ($request->has('date_range') && $request->date_range !== 'all_time') {
            $dateRange = $this->getDateRange($request->date_range, $request);
            if ($dateRange) {
                $siteVisitDateColumn = count(array_values(array_filter((array) $request->input('status', [])))) === 1
                    && array_values(array_filter((array) $request->input('status', [])))[0] === 'completed'
                    ? 'completed_at'
                    : 'scheduled_at';

                $query->whereBetween($siteVisitDateColumn, [$dateRange['start_date'], $dateRange['end_date']]);
            }
        }

        $siteVisits = $query->get();

        if ($siteVisits->isEmpty()) {
            return back()->with('error', 'No site visits found matching the selected filters.');
        }

        if ($request->format === 'csv') {
            return $this->exportSiteVisitsToCsv($siteVisits);
        } else {
            return $this->exportSiteVisitsToPdf($siteVisits);
        }
    }

    /**
     * Quick export closed leads
     */
    public function exportClosedLeads(Request $request)
    {
        $request->validate([
            'format' => 'required|in:csv,pdf',
        ]);

        $user = auth()->user();
        $query = Lead::with(['creator', 'activeAssignments.assignedTo', 'siteVisits'])
            ->where('status', 'closed');

        // Apply role-based filtering
        if ($user->isSalesManager() || $user->isSalesHead()) {
            $teamMemberIds = $user->isSalesHead() 
                ? $user->getAllTeamMemberIds()
                : $user->teamMembers()->pluck('id')->toArray();
            
            if (!empty($teamMemberIds)) {
                $query->whereHas('activeAssignments', function($q) use ($teamMemberIds) {
                    $q->whereIn('assigned_to', $teamMemberIds);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        // Apply date range
        if ($request->has('date_range') && $request->date_range !== 'all_time') {
            $dateRange = $this->getDateRange($request->date_range);
            if ($dateRange) {
                $query->whereBetween('updated_at', [$dateRange['start_date'], $dateRange['end_date']]);
            }
        }

        $leads = $query->get();

        if ($leads->isEmpty()) {
            return back()->with('error', 'No closed leads found.');
        }

        $fields = ['name', 'phone', 'email', 'status', 'budget', 'created_at', 'updated_at', 'assigned_to'];
        
        if ($request->format === 'csv') {
            return $this->exportLeadsToCsv($leads, $fields);
        } else {
            return $this->exportLeadsToPdf($leads, $fields);
        }
    }

    /**
     * Quick export dead leads
     */
    public function exportDeadLeads(Request $request)
    {
        $request->validate([
            'format' => 'required|in:csv,pdf',
        ]);

        $user = auth()->user();
        $query = Lead::with(['creator', 'activeAssignments.assignedTo'])
            ->where(function($q) {
                $q->where('status', 'dead')
                  ->orWhere('is_dead', true);
            });

        // Apply role-based filtering
        if ($user->isSalesManager() || $user->isSalesHead()) {
            $teamMemberIds = $user->isSalesHead() 
                ? $user->getAllTeamMemberIds()
                : $user->teamMembers()->pluck('id')->toArray();
            
            if (!empty($teamMemberIds)) {
                $query->whereHas('activeAssignments', function($q) use ($teamMemberIds) {
                    $q->whereIn('assigned_to', $teamMemberIds);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        // Apply date range
        if ($request->has('date_range') && $request->date_range !== 'all_time') {
            $dateRange = $this->getDateRange($request->date_range);
            if ($dateRange) {
                $query->whereBetween('marked_dead_at', [$dateRange['start_date'], $dateRange['end_date']]);
            }
        }

        $leads = $query->get();

        if ($leads->isEmpty()) {
            return back()->with('error', 'No dead leads found.');
        }

        $fields = ['name', 'phone', 'email', 'status', 'dead_reason', 'dead_at_stage', 'marked_dead_at', 'marked_dead_by'];
        
        if ($request->format === 'csv') {
            return $this->exportLeadsToCsv($leads, $fields);
        } else {
            return $this->exportLeadsToPdf($leads, $fields);
        }
    }

    /**
     * Build lead query with filters
     */
    private function buildLeadQuery($user, Request $request)
    {
        $query = Lead::with([
            'creator',
            'activeAssignments.assignedTo',
            'latestAssignment.assignedTo',
            'prospects.interestedProjects',
            'followUps',
            'tasks',
            'meetings',
            'siteVisits',
        ]);

        // Apply role-based filtering
        if ($user->isAdmin() || $user->isCrm()) {
            // Admin/CRM all-lead export should include every accessible lead,
            // including unassigned, junk, not interested, and other inactive-pipeline states.
        } elseif ($user->isSalesHead()) {
            $teamMemberIds = $user->getAllTeamMemberIds();
            if (!empty($teamMemberIds)) {
                $query->whereHas('activeAssignments', function ($q) use ($teamMemberIds) {
                    $q->whereIn('assigned_to', $teamMemberIds);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($user->isSalesManager()) {
            $teamMemberIds = $user->teamMembers()->pluck('id')->toArray();
            if (!empty($teamMemberIds)) {
                $query->whereHas('activeAssignments', function ($q) use ($teamMemberIds) {
                    $q->whereIn('assigned_to', $teamMemberIds);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        // Apply filters from request
        if ($request->has('status') && !empty($request->status)) {
            $query->whereIn('status', $request->status);
        }

        if ($request->has('user_id') && $request->user_id) {
            $query->whereHas('activeAssignments', function($q) use ($request) {
                $q->where('assigned_to', $request->user_id);
            });
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->has('date_range') && $request->date_range !== 'all_time') {
            $dateRange = $this->getDateRange($request->date_range);
            if ($dateRange) {
                $query->whereBetween('created_at', [$dateRange['start_date'], $dateRange['end_date']]);
            }
        }

        // Lead type filters (multiple selection support)
        if ($request->has('lead_type') && !empty($request->lead_type)) {
            $types = is_array($request->lead_type) ? $request->lead_type : [$request->lead_type];
            
            $query->where(function($q) use ($types) {
                foreach ($types as $type) {
                    $q->orWhere(function($subQ) use ($type) {
                        if ($type === 'prospect') {
                            $subQ->where('status', 'verified_prospect');
                        } elseif ($type === 'visit') {
                            $subQ->whereIn('status', ['visit_scheduled', 'visit_done'])
                                  ->whereHas('siteVisits', function($visitQ) {
                                      $visitQ->where('lead_type', 'New Visit');
                                  });
                        } elseif ($type === 'revisit') {
                            $subQ->whereIn('status', ['revisited_scheduled', 'revisited_completed'])
                                  ->whereHas('siteVisits', function($visitQ) {
                                      $visitQ->where('lead_type', 'Revisited');
                                  });
                        } elseif ($type === 'meeting') {
                            $subQ->where(function($meetingQ) {
                                $meetingQ->whereIn('status', ['meeting_scheduled', 'meeting_completed'])
                                         ->orWhereHas('meetings');
                            });
                        } elseif ($type === 'closer') {
                            $subQ->whereHas('siteVisits', function($closerQ) {
                                $closerQ->where(function($cQ) {
                                    $cQ->where('closer_status', 'pending')
                                       ->orWhereNotNull('closer_status');
                                });
                            });
                        }
                    });
                }
            });
        }

        // Interested projects filter
        if ($request->has('interested_projects') && !empty($request->interested_projects)) {
            $projectIds = is_array($request->interested_projects) ? $request->interested_projects : [$request->interested_projects];
            $query->whereHas('prospects.interestedProjects', function($q) use ($projectIds) {
                $q->whereIn('interested_project_names.id', $projectIds);
            });
        }

        return $query;
    }

    /**
     * Export leads to CSV
     */
    private function exportLeadsToCsv($leads, $fields)
    {
        $headers = [];
        $fieldLabels = $this->getLeadFieldLabels();
        
        foreach ($fields as $field) {
            if (isset($fieldLabels[$field])) {
                $headers[] = $fieldLabels[$field];
            }
        }

        $filename = 'leads_export_' . date('Y-m-d_His') . '.csv';
        
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);

        foreach ($leads as $index => $lead) {
            $row = [];
            foreach ($fields as $field) {
                $row[] = $this->getLeadFieldValue($lead, $field, $index + 1);
            }
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('Content-Transfer-Encoding', 'binary');
    }

    /**
     * Export leads to PDF
     */
    private function exportLeadsToPdf($leads, $fields)
    {
        $fieldLabels = $this->getLeadFieldLabels();
        $headers = [];
        foreach ($fields as $field) {
            if (isset($fieldLabels[$field])) {
                $headers[] = $fieldLabels[$field];
            }
        }

        $html = view('export.pdf.leads', compact('leads', 'headers', 'fields'))->render();
        
        // Check if dompdf is available
        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
            $filename = 'leads_export_' . date('Y-m-d_His') . '.pdf';
            return $pdf->download($filename);
        } elseif (class_exists('PDF')) {
            $pdf = \PDF::loadHTML($html);
            $filename = 'leads_export_' . date('Y-m-d_His') . '.pdf';
            return $pdf->download($filename);
        } else {
            // Fallback: Return HTML for browser print
            return response($html)
                ->header('Content-Type', 'text/html')
                ->header('Content-Disposition', 'inline; filename="leads_export_' . date('Y-m-d_His') . '.html"');
        }
    }

    /**
     * Export prospects to CSV
     */
    private function exportProspectsToCsv($prospects)
    {
        $headers = ['Customer Name', 'Phone', 'Budget', 'Location', 'Purpose', 'Status', 'Created Date', 'Telecaller', 'Manager'];
        
        $filename = 'prospects_export_' . date('Y-m-d_His') . '.csv';
        
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);

        foreach ($prospects as $prospect) {
            fputcsv($handle, [
                $prospect->customer_name,
                $prospect->phone,
                $prospect->budget ?? 'N/A',
                $prospect->preferred_location ?? 'N/A',
                $prospect->purpose ?? 'N/A',
                ucfirst(str_replace('_', ' ', $prospect->verification_status)),
                $prospect->created_at->format('Y-m-d H:i'),
                $prospect->telecaller->name ?? 'N/A',
                $prospect->assignedManager->name ?? 'N/A',
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    /**
     * Export prospects to PDF
     */
    private function exportProspectsToPdf($prospects)
    {
        $html = view('export.pdf.prospects', compact('prospects'))->render();
        
        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
            $filename = 'prospects_export_' . date('Y-m-d_His') . '.pdf';
            return $pdf->download($filename);
        } elseif (class_exists('PDF')) {
            $pdf = \PDF::loadHTML($html);
            $filename = 'prospects_export_' . date('Y-m-d_His') . '.pdf';
            return $pdf->download($filename);
        } else {
            return response($html)
                ->header('Content-Type', 'text/html')
                ->header('Content-Disposition', 'inline; filename="prospects_export_' . date('Y-m-d_His') . '.html"');
        }
    }

    /**
     * Export meetings to CSV
     */
    private function exportMeetingsToCsv($meetings)
    {
        $headers = ['Customer Name', 'Phone', 'Meeting Date', 'Status', 'Employee', 'Team Leader', 'Location', 'Remarks'];
        
        $filename = 'meetings_export_' . date('Y-m-d_His') . '.csv';
        
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);

        foreach ($meetings as $meeting) {
            fputcsv($handle, [
                $meeting->lead->name ?? $meeting->customer_name ?? 'N/A',
                $meeting->lead->phone ?? $meeting->phone ?? 'N/A',
                $meeting->scheduled_at ? $meeting->scheduled_at->format('Y-m-d H:i') : 'N/A',
                ucfirst($meeting->status),
                $meeting->assignedTo->name ?? $meeting->employee ?? 'N/A',
                $meeting->team_leader ?? 'N/A',
                $meeting->property_address ?? 'N/A',
                $meeting->meeting_notes ?? $meeting->feedback ?? 'N/A',
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    /**
     * Export meetings to PDF
     */
    private function exportMeetingsToPdf($meetings)
    {
        $html = view('export.pdf.meetings', compact('meetings'))->render();
        
        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
            $filename = 'meetings_export_' . date('Y-m-d_His') . '.pdf';
            return $pdf->download($filename);
        } elseif (class_exists('PDF')) {
            $pdf = \PDF::loadHTML($html);
            $filename = 'meetings_export_' . date('Y-m-d_His') . '.pdf';
            return $pdf->download($filename);
        } else {
            return response($html)
                ->header('Content-Type', 'text/html')
                ->header('Content-Disposition', 'inline; filename="meetings_export_' . date('Y-m-d_His') . '.html"');
        }
    }

    /**
     * Export site visits to CSV
     */
    private function exportSiteVisitsToCsv($siteVisits)
    {
        $headers = ['Customer Name', 'Phone', 'Visit Date', 'Completed Date', 'Visit Type', 'Status', 'Assigned To', 'Closer Status', 'Queue State', 'Location'];
        
        $filename = 'site_visits_export_' . date('Y-m-d_His') . '.csv';
        
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);

        foreach ($siteVisits as $visit) {
            fputcsv($handle, [
                $visit->lead->name ?? $visit->customer_name ?? 'N/A',
                $visit->lead->phone ?? $visit->phone ?? 'N/A',
                $visit->scheduled_at ? $visit->scheduled_at->format('Y-m-d H:i') : 'N/A',
                $visit->completed_at ? $visit->completed_at->format('Y-m-d H:i') : 'N/A',
                $visit->lead_type ?? 'N/A',
                ucfirst($visit->status),
                $visit->assignedTo->name ?? 'N/A',
                ucfirst($visit->closer_status ?? 'N/A'),
                $this->workspaceSiteVisitQueueState($visit),
                $visit->property_address ?? $visit->property_name ?? 'N/A',
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    /**
     * Export site visits to PDF
     */
    private function exportSiteVisitsToPdf($siteVisits)
    {
        $html = view('export.pdf.site-visits', compact('siteVisits'))->render();
        
        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
            $filename = 'site_visits_export_' . date('Y-m-d_His') . '.pdf';
            return $pdf->download($filename);
        } elseif (class_exists('PDF')) {
            $pdf = \PDF::loadHTML($html);
            $filename = 'site_visits_export_' . date('Y-m-d_His') . '.pdf';
            return $pdf->download($filename);
        } else {
            return response($html)
                ->header('Content-Type', 'text/html')
                ->header('Content-Disposition', 'inline; filename="site_visits_export_' . date('Y-m-d_His') . '.html"');
        }
    }

    /**
     * Get lead field labels
     */
    private function getLeadFieldLabels()
    {
        return [
            'serial_no' => 'S. No',
            'id' => 'ID',
            'name' => 'Customer Name',
            'phone' => 'Customer Number',
            'email' => 'Email',
            'status' => 'Status',
            'budget' => 'Budget',
            'preferred_location' => 'Location',
            'source' => 'Source',
            'assigned_to' => 'Assigned To',
            'crm_advisor' => 'Owner',
            'last_assigned_to' => 'Last Assigned To',
            'created_at' => 'Created Date',
            'updated_at' => 'Updated Date',
            'last_contacted_at' => 'Last Contacted',
            'latest_remark' => 'Latest Remark',
            'next_followup_date' => 'Next Follow-up Date',
            'notes' => 'Notes',
            'employee_remark' => 'Employee Remark',
            'manager_remark' => 'Manager Remark',
            'interested_projects' => 'Interested Projects',
            'dead_reason' => 'Dead Reason',
            'dead_at_stage' => 'Dead At Stage',
            'marked_dead_at' => 'Marked Dead Date',
            'marked_dead_by' => 'Marked Dead By',
        ];
    }

    private function applyLeadDisplayStatusesForExport($records): void
    {
        $leads = collect($records)
            ->map(fn ($record) => $record instanceof Lead ? $record : ($record instanceof SiteVisit ? $record->lead : null))
            ->filter(fn ($record) => $record instanceof Lead)
            ->unique('id')
            ->values();

        app(LeadDisplayStatusResolver::class)->apply($leads);
    }

    /**
     * Get lead field value
     */
    private function getLeadFieldValue($lead, $field, ?int $serialNumber = null)
    {
        switch ($field) {
            case 'serial_no':
                return (string) ($serialNumber ?? '');
            case 'assigned_to':
            case 'crm_advisor':
                return $lead->activeAssignments->first()?->assignedTo->name ?? 'Unassigned';
            case 'last_assigned_to':
                return $this->getLastAssignedTo($lead);
            case 'status':
                return app(LeadDisplayStatusResolver::class)->label($lead);
            case 'source':
                return Lead::displaySourceLabel($lead->source);
            case 'created_at':
            case 'updated_at':
            case 'last_contacted_at':
            case 'marked_dead_at':
                return $lead->$field ? $lead->$field->format('Y-m-d H:i') : 'N/A';
            case 'latest_remark':
                return $this->getLatestLeadRemark($lead);
            case 'next_followup_date':
                return $this->getNextFollowUpDate($lead);
            case 'marked_dead_by':
                $user = User::find($lead->marked_dead_by);
                return $user ? $user->name : 'N/A';
            case 'interested_projects':
                // Get all interested projects from all prospects of this lead
                $allProjects = collect();
                foreach ($lead->prospects as $prospect) {
                    if ($prospect->interestedProjects) {
                        $allProjects = $allProjects->merge($prospect->interestedProjects);
                    }
                }
                $uniqueProjects = $allProjects->unique('id')->pluck('name');
                return $uniqueProjects->isNotEmpty() ? $uniqueProjects->implode(', ') : 'No Projects';
            default:
                return $lead->$field ?? 'N/A';
        }
    }

    private function getLastAssignedTo(Lead $lead): string
    {
        $assignment = $lead->relationLoaded('latestAssignment')
            ? $lead->latestAssignment
            : $lead->latestAssignment()->with('assignedTo')->first();

        return $assignment?->assignedTo?->name ?? 'Never assigned';
    }

    private function getLatestLeadRemark(Lead $lead): string
    {
        $remarkCandidates = collect();

        foreach ($lead->tasks ?? [] as $task) {
            foreach (['outcome_remark', 'notes', 'description'] as $attribute) {
                $value = trim((string) ($task->{$attribute} ?? ''));
                if ($value !== '') {
                    $remarkCandidates->push([
                        'value' => $value,
                        'at' => $task->updated_at ?? $task->created_at,
                    ]);
                    break;
                }
            }
        }

        foreach ($lead->followUps ?? [] as $followUp) {
            $value = trim((string) ($followUp->notes ?? ''));
            if ($value !== '') {
                $remarkCandidates->push([
                    'value' => $value,
                    'at' => $followUp->updated_at ?? $followUp->created_at,
                ]);
            }
        }

        foreach ($lead->prospects ?? [] as $prospect) {
            foreach (['manager_remark', 'employee_remark', 'remark', 'notes'] as $attribute) {
                $value = trim((string) ($prospect->{$attribute} ?? ''));
                if ($value !== '') {
                    $remarkCandidates->push([
                        'value' => $value,
                        'at' => $prospect->updated_at ?? $prospect->created_at,
                    ]);
                    break;
                }
            }
        }

        $leadNotes = trim((string) ($lead->notes ?? ''));
        if ($leadNotes !== '') {
            $remarkCandidates->push([
                'value' => $leadNotes,
                'at' => $lead->updated_at ?? $lead->created_at,
            ]);
        }

        $latest = $remarkCandidates
            ->filter(fn ($item) => !empty($item['value']))
            ->sortByDesc(fn ($item) => $item['at'] ? Carbon::parse($item['at'])->timestamp : 0)
            ->first();

        return $latest['value'] ?? 'N/A';
    }

    private function getNextFollowUpDate(Lead $lead): string
    {
        if ($lead->next_followup_at) {
            return $lead->next_followup_at->format('Y-m-d H:i');
        }

        $scheduledAt = collect($lead->followUps ?? [])
            ->filter(fn ($followUp) => $followUp->scheduled_at)
            ->sortByDesc(fn ($followUp) => $followUp->scheduled_at?->timestamp ?? 0)
            ->first()?->scheduled_at;

        if (!$scheduledAt) {
            $scheduledAt = collect($lead->tasks ?? [])
                ->filter(fn ($task) => $task->scheduled_at && in_array($task->status, ['pending', 'in_progress', 'rescheduled'], true))
                ->sortByDesc(fn ($task) => $task->scheduled_at?->timestamp ?? 0)
                ->first()?->scheduled_at;
        }

        return $scheduledAt ? Carbon::parse($scheduledAt)->format('Y-m-d H:i') : 'N/A';
    }

    /**
     * Get date range from filter
     */
    private function getDateRange($range, ?Request $request = null)
    {
        $today = Carbon::today();
        
        switch ($range) {
            case 'today':
                return [
                    'start_date' => $today->startOfDay(),
                    'end_date' => $today->copy()->endOfDay(),
                ];
            case 'this_week':
                return [
                    'start_date' => $today->copy()->startOfWeek(),
                    'end_date' => $today->copy()->endOfWeek(),
                ];
            case 'this_month':
                return [
                    'start_date' => $today->copy()->startOfMonth(),
                    'end_date' => $today->copy()->endOfMonth(),
                ];
            case 'previous_month':
                return [
                    'start_date' => $today->copy()->subMonthNoOverflow()->startOfMonth(),
                    'end_date' => $today->copy()->subMonthNoOverflow()->endOfMonth(),
                ];
            case 'this_year':
                return [
                    'start_date' => $today->copy()->startOfYear(),
                    'end_date' => $today->copy()->endOfYear(),
                ];
            case 'custom':
                if (!$request || !$request->filled('start_date') || !$request->filled('end_date')) {
                    return null;
                }

                return [
                    'start_date' => Carbon::parse($request->input('start_date'))->startOfDay(),
                    'end_date' => Carbon::parse($request->input('end_date'))->endOfDay(),
                ];
            case 'till_date':
            case 'all_time':
            default:
                return null;
        }
    }

    /**
     * Export leads by interested projects
     */
    public function exportByProject(Request $request)
    {
        $request->validate([
            'format' => 'required|in:csv,xlsx',
            'interested_projects' => 'required|array|min:1',
            'interested_projects.*' => 'exists:interested_project_names,id',
            'date_range' => 'nullable|string',
        ]);

        $user = auth()->user();
        $projectIds = $request->input('interested_projects');

        // Query leads that have prospects with selected interested projects
        $query = Lead::with([
            'creator',
            'activeAssignments.assignedTo',
            'prospects.interestedProjects'
        ])->whereHas('prospects.interestedProjects', function($q) use ($projectIds) {
            $q->whereIn('interested_project_names.id', $projectIds);
        });

        // Apply role-based filtering
        if ($user->isSalesManager() || $user->isSalesHead()) {
            $teamMemberIds = $user->isSalesHead() 
                ? $user->getAllTeamMemberIds()
                : $user->teamMembers()->pluck('id')->toArray();
            
            if (!empty($teamMemberIds)) {
                $query->whereHas('activeAssignments', function($q) use ($teamMemberIds) {
                    $q->whereIn('assigned_to', $teamMemberIds);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        // Apply date range
        if ($request->has('date_range') && $request->date_range !== 'all_time') {
            $dateRange = $this->getDateRange($request->date_range);
            if ($dateRange) {
                $query->whereBetween('created_at', [$dateRange['start_date'], $dateRange['end_date']]);
            }
        }

        $leads = $query->get();

        if ($leads->isEmpty()) {
            return back()->with('error', 'No leads found matching the selected interested projects.');
        }

        // Limit to 10000 records
        if ($leads->count() > 10000) {
            return back()->with('error', 'Export limit exceeded. Please refine your filters. Maximum 10,000 records allowed.');
        }

        if ($request->format === 'csv') {
            return $this->exportByProjectToCsv($leads);
        } else {
            return $this->exportByProjectToExcel($leads);
        }
    }

    /**
     * Export leads by project to CSV
     */
    private function exportByProjectToCsv($leads)
    {
        $headers = [
            'Lead ID',
            'Customer Name',
            'Phone',
            'Email',
            'Status',
            'Budget',
            'Preferred Location',
            'Source',
            'Assigned To',
            'Interested Projects',
            'Created Date',
            'Updated Date',
        ];

        $filename = 'leads_by_project_export_' . date('Y-m-d_His') . '.csv';
        
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);

        foreach ($leads as $lead) {
            // Get all interested projects from all prospects of this lead
            $allProjects = collect();
            foreach ($lead->prospects as $prospect) {
                if ($prospect->interestedProjects) {
                    $allProjects = $allProjects->merge($prospect->interestedProjects);
                }
            }
            $uniqueProjects = $allProjects->unique('id')->pluck('name')->implode(', ');

            $row = [
                $lead->id,
                $lead->name ?? 'N/A',
                $lead->phone ?? 'N/A',
                $lead->email ?? 'N/A',
                ucfirst(str_replace('_', ' ', $lead->status ?? 'N/A')),
                $lead->budget ?? 'N/A',
                $lead->preferred_location ?? 'N/A',
                ucfirst(str_replace('_', ' ', $lead->source ?? 'N/A')),
                $lead->activeAssignments->first()?->assignedTo->name ?? 'Unassigned',
                $uniqueProjects ?: 'No Projects',
                $lead->created_at ? $lead->created_at->format('Y-m-d H:i') : 'N/A',
                $lead->updated_at ? $lead->updated_at->format('Y-m-d H:i') : 'N/A',
            ];
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('Content-Transfer-Encoding', 'binary');
    }

    /**
     * Export leads by project to Excel
     */
    private function exportByProjectToExcel($leads)
    {
        // For Excel export, we'll create a CSV with .xlsx extension
        // In production, you can use Maatwebsite/Excel package for proper Excel export
        // For now, we'll return CSV with .xlsx extension as a workaround
        
        $headers = [
            'Lead ID',
            'Customer Name',
            'Phone',
            'Email',
            'Status',
            'Budget',
            'Preferred Location',
            'Source',
            'Assigned To',
            'Interested Projects',
            'Created Date',
            'Updated Date',
        ];

        $filename = 'leads_by_project_export_' . date('Y-m-d_His') . '.xlsx';
        
        // Create CSV content (Excel can open CSV files)
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);

        foreach ($leads as $lead) {
            // Get all interested projects from all prospects of this lead
            $allProjects = collect();
            foreach ($lead->prospects as $prospect) {
                if ($prospect->interestedProjects) {
                    $allProjects = $allProjects->merge($prospect->interestedProjects);
                }
            }
            $uniqueProjects = $allProjects->unique('id')->pluck('name')->implode(', ');

            $row = [
                $lead->id,
                $lead->name ?? 'N/A',
                $lead->phone ?? 'N/A',
                $lead->email ?? 'N/A',
                ucfirst(str_replace('_', ' ', $lead->status ?? 'N/A')),
                $lead->budget ?? 'N/A',
                $lead->preferred_location ?? 'N/A',
                ucfirst(str_replace('_', ' ', $lead->source ?? 'N/A')),
                $lead->activeAssignments->first()?->assignedTo->name ?? 'Unassigned',
                $uniqueProjects ?: 'No Projects',
                $lead->created_at ? $lead->created_at->format('Y-m-d H:i') : 'N/A',
                $lead->updated_at ? $lead->updated_at->format('Y-m-d H:i') : 'N/A',
            ];
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        // Return as Excel file (CSV format that Excel can open)
        return response($csv)
            ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('Content-Transfer-Encoding', 'binary');
    }
}

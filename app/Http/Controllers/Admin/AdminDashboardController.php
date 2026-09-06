<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\User;
use App\Models\Project;
use App\Models\ActivityLog;
use App\Models\ImportBatch;
use App\Models\SiteVisit;
use App\Models\Meeting;
use App\Models\Role;
use App\Models\LeadAssignment;
use App\Models\CrmAssignment;
use App\Models\TelecallerTask;
use App\Models\Task;
use App\Models\Prospect;
use App\Models\Incentive;
use App\Models\Target;
use App\Models\AttendanceRecord;
use App\Models\AttendanceOutsidePunchRequest;
use App\Models\EmployeeProfile;
use App\Models\ExpenseEntry;
use App\Models\LeadDownloadRequest;
use App\Models\LeaveRequest;
use App\Models\PurchaseOrder;
use App\Models\MetaAdInsightDaily;
use App\Services\TargetService;
use App\Services\CallLogService;
use App\Services\DashboardResponseTimeService;
use App\Services\ExpenseDashboardSummaryService;
use App\Services\LeadQualityReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class AdminDashboardController extends Controller
{
    private const SALES_SCORE_OPTIONAL_COLUMNS = [
        'role',
        'leads',
        'no_response',
        'oldest_assigned',
        'meetings',
        'visits',
        'closers',
        'junk',
        'not_interested',
        'other',
        'ps',
        'pp',
        'vp',
        'avg_response',
        'action',
    ];

    protected $targetService;
    protected $callLogService;
    protected $dashboardResponseTimeService;
    protected $expenseDashboardSummaryService;

    public function __construct(
        TargetService $targetService,
        CallLogService $callLogService,
        DashboardResponseTimeService $dashboardResponseTimeService,
        ExpenseDashboardSummaryService $expenseDashboardSummaryService
    )
    {
        $this->targetService = $targetService;
        $this->callLogService = $callLogService;
        $this->dashboardResponseTimeService = $dashboardResponseTimeService;
        $this->expenseDashboardSummaryService = $expenseDashboardSummaryService;
    }

    public function dashboard()
    {
        $user = auth()->user();
        
        if (!$user->isAdmin()) {
            abort(403, 'Unauthorized access');
        }

        return view('admin.dashboard', [
            'salesScoreColumns' => $this->normalizedSalesScoreColumns($user),
            'leadQualityReport' => [],
        ]);
    }

    public function leadQualityOverview(Request $request, LeadQualityReportService $leadQualityReportService)
    {
        $user = auth()->user();

        if (!$user || !$user->isAdmin()) {
            abort(403, 'Unauthorized access');
        }

        $parameters = array_merge([
            'source' => 'all',
            'period' => 'month',
            'month' => now()->format('Y-m'),
            'sample_limit' => 25,
        ], $request->query());
        ksort($parameters);

        $reportRequest = Request::create($request->url(), 'GET', $parameters);
        $reportRequest->setUserResolver(fn () => $user);
        $cacheKey = 'admin-dashboard:v3:lead-quality:' . $user->id . ':' . sha1(http_build_query($parameters));
        $report = $this->rememberDashboardData(
            $cacheKey,
            300,
            $request->boolean('refresh'),
            fn () => $leadQualityReportService->buildFromRequest($reportRequest)
        );

        return view('marketing.partials.lead-quality-overview', [
            'leadQualityReport' => $report,
            'leadQualityActionUrl' => route('admin.dashboard'),
            'leadQualityResetUrl' => route('admin.dashboard', ['source' => 'all']),
        ]);
    }

    public function updateSalesScoreColumns(Request $request)
    {
        $user = auth()->user();

        if (!$user || !$user->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'columns' => ['required', 'array', 'min:1'],
            'columns.*' => ['required', 'string', Rule::in(self::SALES_SCORE_OPTIONAL_COLUMNS)],
        ]);

        $columns = array_values(array_unique($validated['columns']));
        $preferences = is_array($user->ui_preferences) ? $user->ui_preferences : [];
        $preferences['admin_sales_score_columns'] = $columns;
        $user->ui_preferences = $preferences;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Score table columns updated.',
            'columns' => $this->normalizedSalesScoreColumns($user->fresh()),
        ]);
    }

    private function normalizedSalesScoreColumns(User $user): array
    {
        $preferences = is_array($user->ui_preferences) ? $user->ui_preferences : [];
        $saved = $preferences['admin_sales_score_columns'] ?? null;

        if (!is_array($saved)) {
            return self::SALES_SCORE_OPTIONAL_COLUMNS;
        }

        $columns = array_values(array_intersect(self::SALES_SCORE_OPTIONAL_COLUMNS, $saved));
        if (in_array('leads', $columns, true) && !in_array('other', $columns, true)) {
            $columns[] = 'other';
        }

        return $columns !== [] ? $columns : self::SALES_SCORE_OPTIONAL_COLUMNS;
    }

    public function profile()
    {
        $user = auth()->user();
        
        if (!$user->isAdmin()) {
            abort(403, 'Unauthorized access');
        }

        return view('admin.profile');
    }

    public function resetResponseTime(User $user)
    {
        $admin = auth()->user();

        if (!$admin || !$admin->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $this->dashboardResponseTimeService->resetForUser($user->id, $admin->id);

        $dateRange = $this->getDateRange(request());
        $row = collect($this->dashboardResponseTimeService->getAverageResponseTimeByUser(
            $dateRange['start_date'] ?? null,
            $dateRange['end_date'] ?? null
        ))->firstWhere('user_id', $user->id);

        return response()->json([
            'success' => true,
            'message' => 'Response time reset successfully.',
            'data' => $row,
        ]);
    }

    public function getDashboardData(Request $request)
    {
        try {
            $user = auth()->user();
            
            if (!$user || !$user->isAdmin()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            if ($this->isDashboardReliefMode()) {
                return response()
                    ->json($this->reliefDashboardPayload())
                    ->header('Cache-Control', 'private, max-age=300');
            }

            $mode = strtolower(trim((string) $request->get('mode', 'all')));
            if (!in_array($mode, ['all', 'summary', 'sale', 'marketing', 'finance', 'hr'], true)) {
                $mode = 'all';
            }

            $dateRange = $this->getDateRange($request);
            $cacheParameters = $request->except(['refresh', '_', 'csrf_token']);
            unset($cacheParameters['mode']);
            ksort($cacheParameters);
            $cacheScope = $user->id . ':' . sha1(http_build_query($cacheParameters));
            $refresh = $request->boolean('refresh');
            $requestedModes = $mode === 'all' ? ['sale', 'marketing', 'finance', 'hr'] : [$mode];
            $data = ['mode' => $mode];

            if ($mode === 'summary' || $mode === 'all' || in_array($mode, ['sale', 'marketing'], true)) {
                $data += $this->rememberDashboardData(
                    'admin-dashboard:v3:' . $cacheScope . ':core',
                    60,
                    $refresh,
                    fn () => $this->buildCoreDashboardData($request, $dateRange)
                );
            }

            foreach ($requestedModes as $sectionMode) {
                if ($sectionMode === 'summary') {
                    continue;
                }

                $ttl = match ($sectionMode) {
                    'marketing' => 300,
                    'finance', 'hr' => 120,
                    default => 60,
                };
                $section = $this->rememberDashboardData(
                    'admin-dashboard:v3:' . $cacheScope . ':' . $sectionMode,
                    $ttl,
                    $refresh,
                    fn () => $this->buildDashboardSection($sectionMode, $request, $dateRange)
                );
                $data += $section;
            }

            if ($mode === 'all' || $mode === 'sale') {
                $data['approval_center'] = $this->rememberDashboardData(
                    'admin-dashboard:v3:' . $user->id . ':approval-center',
                    30,
                    $refresh,
                    fn () => $this->getApprovalCenter()
                );
            }
            $data['server_now'] = now()->toIso8601String();

            return response()->json($data);
        } catch (\Exception $e) {
            Log::error('Admin Dashboard Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'error' => 'Failed to load dashboard data',
                'message' => config('app.debug') ? $e->getMessage() : 'An error occurred while loading dashboard data.',
            ], 500);
        }
    }

    private function buildCoreDashboardData(Request $request, array $dateRange): array
    {
        return [
            'system_stats' => $this->getSystemStats($dateRange),
            'user_stats' => $this->getUserStats($dateRange),
            'lead_stats' => $this->getLeadStats($dateRange),
            'project_stats' => $this->getProjectStats($dateRange),
            'recent_leads' => $this->getRecentLeads(10, $dateRange, $request->get('recent_leads_source')),
        ];
    }

    private function buildDashboardSection(string $mode, Request $request, array $dateRange): array
    {
        if ($mode === 'marketing') {
            return [
                'call_statistics' => $this->getCallStatistics(
                    $request->filled('date_range')
                        ? $this->getPresetDateRange($request->get('date_range'))
                        : $dateRange
                ),
                'marketing_summary' => $this->getMarketingSummary($dateRange),
                'ad_spend_summary' => $this->getAdSpendSummary($dateRange),
                'source_performance_analytics' => $this->getSourcePerformanceAnalytics(
                    $this->resolveSectionDateRange($request, 'source_filter', $dateRange)
                ),
            ];
        }

        if ($mode === 'finance') {
            return ['finance_summary' => $this->getFinanceSummary($dateRange, (string) $request->get('filter', 'month'))];
        }

        if ($mode === 'hr') {
            return $this->getHrDashboardSummary($dateRange);
        }

        return $this->buildSalesDashboardData($request, $dateRange);
    }

    private function buildSalesDashboardData(Request $request, array $dateRange): array
    {
        try {
            $targetOverview = $this->targetService->getSystemOverview();
        } catch (\Exception $e) {
            Log::warning('Failed to get target overview: ' . $e->getMessage());
            $targetOverview = [
                'month' => now()->format('Y-m'),
                'total_users' => 0,
                'targets' => ['prospects_extract' => 0, 'prospects_verified' => 0, 'calls' => 0],
                'actuals' => ['prospects_extract' => 0, 'prospects_verified' => 0, 'calls' => 0],
                'percentages' => ['prospects_extract' => 0, 'prospects_verified' => 0, 'calls' => 0],
                'details' => [],
            ];
        }

        $pipelineFilterPreset = trim((string) $request->get('pipeline_filter', 'global'));
        $pipelineDateRange = $pipelineFilterPreset === 'global'
            ? null
            : $this->resolveSectionDateRange($request, 'pipeline_filter', $dateRange);
        $demandDateRange = $this->resolveSectionDateRange($request, 'demand_filter', $dateRange);
        $salesScoreDateRange = $this->resolveSectionDateRange($request, 'sales_score_filter', $dateRange);
        $averageResponseTimeByUser = $this->getAverageResponseTimeByUser($dateRange);
        $leadsPendingResponse = $this->getLeadsPendingResponseByUser($dateRange);
        $salesScoreTable = $this->mergeSalesScoreOperationalMetrics(
            $this->getSalesScoreTable($salesScoreDateRange),
            $averageResponseTimeByUser,
            $leadsPendingResponse,
            $salesScoreDateRange
        );
        $rangeCounts = $this->getDashboardRangeCounts($dateRange);

        return [
            'target_overview' => $targetOverview,
            'activity_summary' => $this->getActivitySummary($dateRange),
            'system_health' => $this->getSystemHealth($dateRange),
            'recent_activities' => $this->getRecentActivities(20, $dateRange),
            'agents_visits_meetings' => $this->getAgentsVisitsVsMeetings($dateRange),
            'property_segments' => $this->getPropertySegments($dateRange),
            'demand_insights' => $this->getDemandInsights($demandDateRange),
            'telecaller_performance' => $this->getTelecallerPerformance($dateRange),
            'leads_pending_response' => $leadsPendingResponse,
            'average_response_time_by_user' => $averageResponseTimeByUser,
            'user_visits_meetings' => $this->getUserVisitsMeetingsData($request->get('visits_meetings_filter', 'this_month')),
            'source_performance_analytics' => [],
            'performance_scores' => $this->getPerformanceScores(
                $pipelineDateRange,
                $pipelineFilterPreset === 'global' ? $rangeCounts : null
            ),
            'weekly_activity_summary' => $this->getWeeklyActivitySummary($pipelineDateRange ?? $dateRange, $pipelineFilterPreset),
            'sales_score_table' => $salesScoreTable,
            'sales_user_activity_table' => $this->getSalesUserActivityTable($dateRange, $leadsPendingResponse),
            'pipeline_funnel' => $this->getPipelineFunnel($pipelineDateRange),
            'team_targets_summary' => $this->getTeamTargetsSummary($dateRange, $rangeCounts),
            'team_targets_breakdown' => $this->getTeamTargetsBreakdown($targetOverview),
            'incentive_summary' => $this->getIncentiveSummary($dateRange),
            'user_pipeline_table' => $this->getUserPipelineTable($salesScoreTable, $averageResponseTimeByUser),
            'dashboard_shortcuts' => $this->getDashboardShortcuts($dateRange, $rangeCounts),
        ];
    }

    private function rememberDashboardData(string $key, int $ttlSeconds, bool $refresh, callable $builder): array
    {
        if (!$refresh && Cache::has($key)) {
            return Cache::get($key);
        }

        try {
            return Cache::lock($key . ':lock', 30)->block(5, function () use ($key, $ttlSeconds, $refresh, $builder) {
                if (!$refresh && Cache::has($key)) {
                    return Cache::get($key);
                }

                $data = $builder();
                Cache::put($key, $data, now()->addSeconds($ttlSeconds));

                return $data;
            });
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException) {
            $data = $builder();
            Cache::put($key, $data, now()->addSeconds($ttlSeconds));

            return $data;
        }
    }

    private function isDashboardReliefMode(): bool
    {
        return file_exists(storage_path('framework/crm-relief-mode'));
    }

    private function reliefDashboardPayload(): array
    {
        return [
            'relief_mode' => true,
            'message' => 'Dashboard is temporarily running in relief mode while server load normalizes.',
            'mode' => 'relief',
            'system_stats' => [],
            'user_stats' => [],
            'lead_stats' => [],
            'project_stats' => [],
            'recent_leads' => [],
            'target_overview' => null,
            'activity_summary' => [],
            'system_health' => [],
            'recent_activities' => [],
            'agents_visits_meetings' => [],
            'property_segments' => [],
            'demand_insights' => [],
            'telecaller_performance' => [],
            'leads_pending_response' => [],
            'average_response_time_by_user' => [],
            'user_visits_meetings' => ['summary' => [], 'users' => []],
            'source_performance_analytics' => [],
            'performance_scores' => [],
            'weekly_activity_summary' => [],
            'sales_score_table' => [],
            'sales_user_activity_table' => [],
            'pipeline_funnel' => [],
            'team_targets_summary' => [],
            'team_targets_breakdown' => [],
            'incentive_summary' => [],
            'user_pipeline_table' => [],
            'dashboard_shortcuts' => [],
            'call_statistics' => [],
            'marketing_summary' => [],
            'ad_spend_summary' => [],
            'finance_summary' => [],
            'approval_center' => ['items' => []],
            'server_now' => now()->toIso8601String(),
        ];
    }

    public function syncMetaSpend(Request $request)
    {
        $dateRange = $this->resolveSectionDateRange(
            $request,
            'source_filter',
            $this->getDateRange($request)
        );

        try {
            $exitCode = Artisan::call('meta-ads:sync-insights', [
                '--from' => $dateRange['start_date']->toDateString(),
                '--to' => $dateRange['end_date']->toDateString(),
            ]);

            $output = trim(Artisan::output());

            if ($exitCode !== 0) {
                return response()->json([
                    'success' => false,
                    'message' => $output ?: 'Meta spend sync failed.',
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => $output ?: 'Meta spend synced successfully.',
                'from' => $dateRange['start_date']->toDateString(),
                'to' => $dateRange['end_date']->toDateString(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Meta spend sync failed from admin dashboard', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => config('app.debug') ? $e->getMessage() : 'Meta spend sync failed.',
            ], 500);
        }
    }

    /**
     * Get date range from request
     */
    private function getDateRange(Request $request): array
    {
        $filter = $request->get('filter', 'month'); // Default to 'month'
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        // If custom dates provided, use them
        if ($startDate && $endDate) {
            return [
                'start_date' => Carbon::parse($startDate)->startOfDay(),
                'end_date' => Carbon::parse($endDate)->endOfDay(),
            ];
        }

        // Calculate based on filter type
        switch ($filter) {
            case 'today':
                return [
                    'start_date' => Carbon::today()->startOfDay(),
                    'end_date' => Carbon::today()->endOfDay(),
                ];
            case 'week':
                return [
                    'start_date' => Carbon::now()->startOfWeek()->startOfDay(),
                    'end_date' => Carbon::now()->endOfWeek()->endOfDay(),
                ];
            case 'month':
                return [
                    'start_date' => Carbon::now()->startOfMonth()->startOfDay(),
                    'end_date' => Carbon::now()->endOfMonth()->endOfDay(),
                ];
            case 'year':
                return [
                    'start_date' => Carbon::now()->startOfYear()->startOfDay(),
                    'end_date' => Carbon::now()->endOfYear()->endOfDay(),
                ];
            default:
                return [
                    'start_date' => Carbon::now()->startOfMonth()->startOfDay(),
                    'end_date' => Carbon::now()->endOfMonth()->endOfDay(),
                ];
        }
    }

    private function resolveSectionDateRange(Request $request, string $requestKey, array $fallback): array
    {
        $preset = trim((string) $request->get($requestKey, ''));

        if ($preset === '' || $preset === 'global') {
            return $fallback;
        }

        if ($preset === 'custom') {
            $prefix = str_replace('_filter', '', $requestKey);
            $startDate = trim((string) $request->get($prefix . '_start_date', ''));
            $endDate = trim((string) $request->get($prefix . '_end_date', ''));

            if ($startDate !== '' && $endDate !== '') {
                return [
                    'start_date' => Carbon::parse($startDate)->startOfDay(),
                    'end_date' => Carbon::parse($endDate)->endOfDay(),
                ];
            }

            return $fallback;
        }

        return $this->getPresetDateRange($preset);
    }

    private function resolveWeeklyActivityDateRange(Request $request): array
    {
        $preset = trim((string) $request->get('weekly_activity_filter', 'this_week'));
        $allowedPresets = ['previous_week', 'this_week', 'next_week', 'this_month', 'custom'];

        if (!in_array($preset, $allowedPresets, true)) {
            $preset = 'this_week';
        }

        if ($preset === 'custom') {
            $startDate = trim((string) $request->get('weekly_activity_start_date', ''));
            $endDate = trim((string) $request->get('weekly_activity_end_date', ''));

            if ($startDate !== '' && $endDate !== '') {
                return [
                    'preset' => 'custom',
                    'date_range' => [
                        'start_date' => Carbon::parse($startDate)->startOfDay(),
                        'end_date' => Carbon::parse($endDate)->endOfDay(),
                    ],
                ];
            }

            $preset = 'this_week';
        }

        return [
            'preset' => $preset,
            'date_range' => $this->getPresetDateRange($preset),
        ];
    }

    private function getSystemStats(?array $dateRange = null)
    {
        $start = $dateRange['start_date'] ?? null;
        $end = $dateRange['end_date'] ?? null;
        $leadStats = DB::table('leads')
            ->whereNull('deleted_at')
            ->selectRaw($dateRange
                ? 'SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as total_leads,
                   SUM(CASE WHEN is_dead = 1 AND marked_dead_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as total_dead'
                : 'COUNT(*) as total_leads, SUM(CASE WHEN is_dead = 1 THEN 1 ELSE 0 END) as total_dead',
                $dateRange ? [$start, $end, $start, $end] : [])
            ->first();
        $visitStats = DB::table('site_visits')
            ->whereNull('deleted_at')
            ->whereNull('queue_hidden_at')
            ->selectRaw($dateRange
                ? "SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as total_visits,
                   SUM(CASE WHEN closer_status IN ('approved', 'verified') AND closer_verified_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as total_closers"
                : "COUNT(*) as total_visits, SUM(CASE WHEN closer_status IN ('approved', 'verified') THEN 1 ELSE 0 END) as total_closers",
                $dateRange ? [$start, $end, $start, $end] : [])
            ->first();
        $meetingStats = DB::table('meetings')
            ->whereNull('deleted_at')
            ->whereNull('queue_hidden_at')
            ->where('is_converted', false)
            ->when($dateRange, fn ($query) => $query->whereBetween('created_at', [$start, $end]))
            ->selectRaw('COUNT(*) as total_meetings')
            ->first();

        return [
            'total_leads' => (int) ($leadStats->total_leads ?? 0),
            'total_visits' => (int) ($visitStats->total_visits ?? 0),
            'total_meetings' => (int) ($meetingStats->total_meetings ?? 0),
            'total_closers' => (int) ($visitStats->total_closers ?? 0),
            'total_dead' => (int) ($leadStats->total_dead ?? 0),
        ];
    }

    private function getFinanceSummary(array $dateRange, string $filter): array
    {
        $startDate = $dateRange['start_date'];
        $endDate = $dateRange['end_date'];
        $snapshot = $this->expenseDashboardSummaryService->getRangeSnapshot($startDate, $endDate);

        $rangeLabel = match ($filter) {
            'today' => 'Today',
            'week' => 'This Week (' . $startDate->format('d M') . ' - ' . $endDate->format('d M Y') . ')',
            'year' => $startDate->format('Y'),
            'custom' => $startDate->isSameDay($endDate)
                ? $startDate->format('d M Y')
                : $startDate->format('d M Y') . ' - ' . $endDate->format('d M Y'),
            default => $startDate->format('F Y'),
        };

        $totalLabel = match ($filter) {
            'today' => 'Today Total',
            'week' => 'Week Total',
            'year' => 'Year Total',
            'custom' => 'Period Total',
            default => 'Month Total',
        };

        return [
            'year' => $snapshot['year'],
            'month' => $snapshot['month'],
            'start_date' => $snapshot['start_date'],
            'end_date' => $snapshot['end_date'],
            'range_label' => $rangeLabel,
            'total_label' => $totalLabel,
            'report_year' => now()->year,
            'report_month' => now()->month,
            'summary' => $snapshot['summary'],
            'status_totals' => $snapshot['status_totals'],
            'pending_count' => $snapshot['pending_count'],
            'approved_amount' => $snapshot['approved_amount'] ?? 0,
            'rejected_amount' => $snapshot['rejected_amount'] ?? 0,
            'pending_amount' => $snapshot['pending_amount'] ?? 0,
            'high_value_pending' => $snapshot['high_value_pending'] ?? ['threshold' => 25000, 'count' => 0, 'amount' => 0],
            'oldest_pending' => $snapshot['oldest_pending'] ?? null,
            'month_comparison' => $snapshot['month_comparison'] ?? null,
            'category_totals' => collect($snapshot['category_totals'] ?? [])->map(function ($row) {
                return [
                    'category_name' => $row->category?->name ?: 'Uncategorized',
                    'entry_count' => (int) ($row->entry_count ?? 0),
                    'total_amount' => (float) ($row->total_amount ?? 0),
                ];
            })->values()->all(),
            'latest_entries' => collect($snapshot['latest_entries'] ?? [])->map(function ($entry) {
                return [
                    'id' => $entry->id,
                    'company_name' => $entry->company?->name,
                    'category_name' => $entry->category?->name,
                    'subcategory_name' => $entry->subcategory?->name,
                    'creator_name' => $entry->creator?->name,
                    'amount' => (float) $entry->amount,
                    'status' => $entry->status,
                    'status_label' => ucfirst(str_replace('_', ' ', (string) $entry->status)),
                    'expense_date' => optional($entry->expense_date)->toDateString(),
                    'view_url' => route('admin.expenses.entries.edit', $entry),
                ];
            })->values()->all(),
        ];
    }

    private function getHrDashboardSummary(array $dateRange): array
    {
        $today = Carbon::today();
        $todayStart = $today->copy()->startOfDay();
        $todayEnd = $today->copy()->endOfDay();
        $rangeStart = $dateRange['start_date'];
        $rangeEnd = $dateRange['end_date'];

        $closedHiringStatuses = ['hired', 'rejected', 'not_interested', 'not_reachable', 'wrong_number', 'duplicate'];

        $todayAttendance = AttendanceRecord::query()
            ->whereDate('attendance_date', $today->toDateString());

        $hrLeadQuery = Lead::query()
            ->where('is_hiring_candidate', true);

        $hrLeadsInRange = (clone $hrLeadQuery)
            ->whereBetween('created_at', [$rangeStart, $rangeEnd]);

        $employeeProfiles = EmployeeProfile::query()
            ->with(['documents'])
            ->get();

        $missingDocumentCount = $employeeProfiles
            ->filter(fn (EmployeeProfile $profile) => count($profile->missingDocumentTypes()) > 0)
            ->count();

        $probationEndingCount = $employeeProfiles
            ->filter(fn (EmployeeProfile $profile) => $profile->probation_end_date
                && $profile->probation_end_date->between($today, $today->copy()->addDays(7)))
            ->count();

        $newJoiningThisMonth = EmployeeProfile::query()
            ->whereBetween('joining_date', [Carbon::now()->startOfMonth()->toDateString(), Carbon::now()->endOfMonth()->toDateString()])
            ->count();

        $attendance = [
            'present' => (clone $todayAttendance)->where('status', AttendanceRecord::STATUS_PRESENT)->count(),
            'absent' => (clone $todayAttendance)->where('status', AttendanceRecord::STATUS_ABSENT)->count(),
            'late' => (clone $todayAttendance)->where('status', AttendanceRecord::STATUS_LATE)->count(),
            'half_day' => (clone $todayAttendance)->where('status', AttendanceRecord::STATUS_HALF_DAY)->count(),
            'on_leave' => (clone $todayAttendance)->where('status', AttendanceRecord::STATUS_LEAVE)->count(),
        ];

        $rawFunnelCounts = (clone $hrLeadsInRange)
            ->select('hiring_status', DB::raw('COUNT(*) as total'))
            ->groupBy('hiring_status')
            ->pluck('total', 'hiring_status');

        $funnelStages = [
            'new' => ['label' => 'New', 'statuses' => ['new']],
            'contacted' => ['label' => 'Contacted', 'statuses' => ['contacted']],
            'interview_scheduled' => ['label' => 'Interview Scheduled', 'statuses' => ['interview_scheduled']],
            'interview_done' => ['label' => 'Interview Done', 'statuses' => ['interview_done']],
            'shortlisted' => ['label' => 'Shortlisted', 'statuses' => ['shortlisted']],
            'offer_sent' => ['label' => 'Offer Sent', 'statuses' => ['offer_sent']],
            'hired' => ['label' => 'Hired', 'statuses' => ['hired']],
            'rejected_not_interested' => [
                'label' => 'Rejected / Not Interested',
                'statuses' => ['rejected', 'not_interested', 'not_reachable', 'wrong_number', 'duplicate'],
            ],
        ];

        $funnel = collect($funnelStages)
            ->map(fn (array $stage, string $status) => [
                'status' => $status,
                'label' => $stage['label'],
                'count' => collect($stage['statuses'])->sum(fn (string $stageStatus) => (int) ($rawFunnelCounts[$stageStatus] ?? 0)),
            ])
            ->values()
            ->all();

        $employeeStatus = [
            'active' => EmployeeProfile::query()->where('employment_status', EmployeeProfile::STATUS_ACTIVE)->count(),
            'on_notice' => EmployeeProfile::query()->where('employment_status', EmployeeProfile::STATUS_ON_NOTICE)->count(),
            'resigned_terminated' => EmployeeProfile::query()
                ->whereIn('employment_status', [EmployeeProfile::STATUS_RESIGNED, EmployeeProfile::STATUS_TERMINATED])
                ->count(),
            'new_joining_this_month' => $newJoiningThisMonth,
            'missing_documents' => $missingDocumentCount,
            'probation_ending' => $probationEndingCount,
        ];

        $pendingLeaveRequests = LeaveRequest::query()->where('status', 'pending')->count();
        $interviewsToday = (clone $hrLeadQuery)
            ->where('hiring_status', 'interview_scheduled')
            ->whereBetween('next_followup_at', [$todayStart, $todayEnd])
            ->count();

        $tasks = [
            [
                'label' => 'Candidates to call today',
                'count' => (clone $hrLeadQuery)
                    ->whereNotIn('hiring_status', array_merge($closedHiringStatuses, ['interview_scheduled']))
                    ->whereBetween('next_followup_at', [$todayStart, $todayEnd])
                    ->count(),
            ],
            [
                'label' => 'Interviews scheduled today',
                'count' => $interviewsToday,
            ],
            [
                'label' => 'Pending follow-ups',
                'count' => (clone $hrLeadQuery)
                    ->whereNotIn('hiring_status', $closedHiringStatuses)
                    ->whereNotNull('next_followup_at')
                    ->where('next_followup_at', '<', now())
                    ->count(),
            ],
            [
                'label' => 'Missing document follow-up',
                'count' => $missingDocumentCount,
            ],
            [
                'label' => 'Joining/probation alerts',
                'count' => $newJoiningThisMonth + $probationEndingCount,
            ],
        ];

        $leaveRequests = [
            'pending' => $pendingLeaveRequests,
            'approved' => LeaveRequest::query()
                ->where('status', 'approved')
                ->whereBetween('updated_at', [$rangeStart, $rangeEnd])
                ->count(),
            'rejected' => LeaveRequest::query()
                ->where('status', 'rejected')
                ->whereBetween('updated_at', [$rangeStart, $rangeEnd])
                ->count(),
            'on_leave_today' => $attendance['on_leave'],
            'pending_items' => LeaveRequest::query()
                ->with(['user:id,name', 'leaveType:id,name'])
                ->where('status', 'pending')
                ->latest('created_at')
                ->limit(5)
                ->get()
                ->map(function (LeaveRequest $leave) {
                    $dateLabel = optional($leave->from_date)->format('d M Y') ?: 'Date not set';
                    if ($leave->to_date && $leave->from_date && !$leave->to_date->isSameDay($leave->from_date)) {
                        $dateLabel .= ' - ' . $leave->to_date->format('d M Y');
                    }

                    return [
                        'id' => $leave->id,
                        'user_name' => $leave->user?->name ?: 'Employee',
                        'leave_type' => $leave->leaveType?->name ?: 'Leave',
                        'date_label' => $dateLabel,
                        'days' => rtrim(rtrim(number_format((float) $leave->days_requested, 2), '0'), '.'),
                        'age' => optional($leave->created_at)->diffForHumans(null, true) . ' ago',
                    ];
                })
                ->values()
                ->all(),
        ];

        $repeatedLateUsers = AttendanceRecord::query()
            ->select('user_id')
            ->whereBetween('attendance_date', [$today->copy()->subDays(6)->toDateString(), $today->toDateString()])
            ->where('status', AttendanceRecord::STATUS_LATE)
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) >= 3')
            ->get()
            ->count();

        $alerts = [
            ['label' => 'Absent today', 'count' => $attendance['absent'], 'tone' => 'danger'],
            [
                'label' => 'Missing punch out',
                'count' => (clone $todayAttendance)
                    ->whereNotNull('first_punch_in_at')
                    ->whereNull('last_punch_out_at')
                    ->count(),
                'tone' => 'warning',
            ],
            ['label' => 'Repeated late employees', 'count' => $repeatedLateUsers, 'tone' => 'warning'],
            [
                'label' => 'Suspicious attendance cases',
                'count' => (clone $todayAttendance)
                    ->where(function ($query) {
                        $query->where('is_suspicious', true)
                            ->orWhere('fraud_review_status', 'pending_review');
                    })
                    ->count(),
                'tone' => 'danger',
            ],
            ['label' => 'Missing documents', 'count' => $missingDocumentCount, 'tone' => 'info'],
            ['label' => 'Probation ending within 7 days', 'count' => $probationEndingCount, 'tone' => 'info'],
        ];

        return [
            'hr_summary' => [
                'present_today' => $attendance['present'],
                'absent_today' => $attendance['absent'],
                'late_today' => $attendance['late'],
                'hr_leads' => (clone $hrLeadsInRange)->count(),
                'interviews_today' => $interviewsToday,
                'pending_leave_requests' => $pendingLeaveRequests,
            ],
            'hr_funnel' => $funnel,
            'hr_attendance' => $attendance,
            'hr_employee_status' => $employeeStatus,
            'hr_tasks' => $tasks,
            'hr_leave_requests' => $leaveRequests,
            'hr_alerts' => $alerts,
        ];
    }

    private function getApprovalCenter(): array
    {
        $items = collect()
            ->merge($this->getExpenseApprovalItems())
            ->merge($this->getOutsidePunchApprovalItems())
            ->merge($this->getLeaveApprovalItems())
            ->merge($this->getPurchaseOrderApprovalItems())
            ->merge($this->getLeadDownloadApprovalItems())
            ->sortByDesc('created_at')
            ->take(12)
            ->values();

        return [
            'total' => $items->count(),
            'counts' => [
                'finance' => $items->where('type', 'finance')->count(),
                'hr' => $items->where('type', 'hr')->count(),
                'leave' => $items->where('type', 'leave')->count(),
                'po' => $items->where('type', 'po')->count(),
                'export' => $items->where('type', 'export')->count(),
            ],
            'items' => $items->all(),
        ];
    }

    private function getExpenseApprovalItems()
    {
        return ExpenseEntry::query()
            ->with(['creator', 'category', 'subcategory', 'paymentMethod', 'assignedApprover'])
            ->where('status', ExpenseEntry::STATUS_DRAFT)
            ->latest('created_at')
            ->limit(5)
            ->get()
            ->map(function (ExpenseEntry $entry) {
                $amount = 'Rs ' . number_format((float) $entry->amount);
                $requester = $entry->creator?->name ?: 'Finance user';
                $category = $entry->category?->name ?: $entry->subcategory?->name ?: 'expense';

                return [
                    'id' => 'expense-' . $entry->id,
                    'type' => 'finance',
                    'icon' => '₹',
                    'title' => 'Expense approval',
                    'badge' => ((float) $entry->amount >= 25000) ? 'HIGH VALUE' : 'FINANCE',
                    'requester' => $requester,
                    'summary' => "{$requester} requested {$amount} for {$category}. Finance review pending.",
                    'details' => array_values(array_filter([
                        ['label' => 'Amount', 'value' => $amount],
                        ['label' => 'Category', 'value' => $entry->category?->name ?: 'N/A'],
                        ['label' => 'Subcategory', 'value' => $entry->subcategory?->name ?: 'N/A'],
                        ['label' => 'Expense Date', 'value' => optional($entry->expense_date)->format('d M Y') ?: 'N/A'],
                        ['label' => 'Paid To', 'value' => $entry->paid_to ?: 'N/A'],
                        ['label' => 'Payment Mode', 'value' => method_exists($entry, 'paymentDisplay') ? $entry->paymentDisplay() : strtoupper((string) $entry->payment_mode)],
                        ['label' => 'Reference No.', 'value' => $entry->reference_no ?: 'N/A'],
                        ['label' => 'Requested By', 'value' => $requester],
                        ['label' => 'Assigned Approver', 'value' => $entry->assignedApprover?->name ?: 'Unassigned'],
                        ['label' => 'Submitted At', 'value' => optional($entry->created_at)->format('d M Y, h:i A') ?: 'N/A'],
                        $entry->remarks ? ['label' => 'Remarks', 'value' => $entry->remarks] : null,
                        $entry->attachment_path ? ['label' => 'Attachment', 'value' => 'Available in full expense page'] : null,
                    ])),
                    'created_at' => optional($entry->created_at)->toIso8601String(),
                    'age' => optional($entry->created_at)->diffForHumans(null, true) . ' ago',
                    'view_url' => route('admin.expenses.entries.edit', $entry),
                    'approve_url' => route('admin.expenses.entries.approve', $entry),
                    'reject_url' => route('admin.expenses.entries.reject', $entry),
                    'reject_field' => 'reject_reason',
                    'reject_prompt' => 'Expense reject reason likhiye',
                ];
            });
    }

    private function getOutsidePunchApprovalItems()
    {
        return AttendanceOutsidePunchRequest::query()
            ->with(['user', 'officeLocation'])
            ->where('status', 'pending')
            ->latest('requested_at')
            ->limit(5)
            ->get()
            ->map(function (AttendanceOutsidePunchRequest $outsidePunchRequest) {
                $requester = $outsidePunchRequest->user?->name ?: 'Employee';
                $distance = $outsidePunchRequest->geo_distance_meters === null
                    ? 'outside office'
                    : ($outsidePunchRequest->geo_distance_meters >= 1000
                        ? number_format($outsidePunchRequest->geo_distance_meters / 1000, 2) . ' km'
                        : number_format($outsidePunchRequest->geo_distance_meters, 0) . ' m');
                $office = $outsidePunchRequest->officeLocation?->name ?: 'office';

                return [
                    'id' => 'outside-punch-' . $outsidePunchRequest->id,
                    'type' => 'hr',
                    'icon' => 'HR',
                    'title' => 'Outside punch request',
                    'badge' => 'NEEDS ACTION',
                    'requester' => $requester,
                    'summary' => "{$requester} punched in {$distance} away from {$office}. Reason and geo proof attached.",
                    'details' => array_values(array_filter([
                        ['label' => 'Employee', 'value' => $requester],
                        ['label' => 'Office', 'value' => $office],
                        ['label' => 'Distance', 'value' => $distance],
                        ['label' => 'Requested At', 'value' => optional($outsidePunchRequest->requested_at ?: $outsidePunchRequest->created_at)->format('d M Y, h:i A') ?: 'N/A'],
                        ['label' => 'Status', 'value' => ucfirst((string) $outsidePunchRequest->status)],
                        !empty($outsidePunchRequest->reason) ? ['label' => 'Reason', 'value' => $outsidePunchRequest->reason] : null,
                    ])),
                    'created_at' => optional($outsidePunchRequest->requested_at ?: $outsidePunchRequest->created_at)->toIso8601String(),
                    'age' => optional($outsidePunchRequest->requested_at ?: $outsidePunchRequest->created_at)->diffForHumans(null, true) . ' ago',
                    'view_url' => route('admin.attendance.outside-punches.index', ['only_pending_requests' => 1]),
                    'approve_url' => route('admin.attendance.outside-punches.approve', $outsidePunchRequest),
                    'reject_url' => route('admin.attendance.outside-punches.reject', $outsidePunchRequest),
                    'reject_field' => 'remarks',
                    'reject_prompt' => 'Outside punch reject remarks likhiye',
                ];
            });
    }

    private function getLeaveApprovalItems()
    {
        return LeaveRequest::query()
            ->with(['user', 'leaveType'])
            ->where('status', 'pending')
            ->latest('created_at')
            ->limit(5)
            ->get()
            ->map(function (LeaveRequest $leave) {
                $requester = $leave->user?->name ?: 'Employee';
                $dateLabel = optional($leave->from_date)->format('d M Y');
                if ($leave->to_date && $leave->from_date && !$leave->to_date->isSameDay($leave->from_date)) {
                    $dateLabel .= ' - ' . $leave->to_date->format('d M Y');
                }
                $days = rtrim(rtrim(number_format((float) $leave->days_requested, 2), '0'), '.');

                return [
                    'id' => 'leave-' . $leave->id,
                    'type' => 'leave',
                    'icon' => 'L',
                    'title' => 'Leave approval',
                    'badge' => strtoupper((string) ($leave->leaveType?->name ?: 'LEAVE')),
                    'requester' => $requester,
                    'summary' => "{$requester} requested {$days} day leave for {$dateLabel}. HR/Admin approval pending.",
                    'details' => array_values(array_filter([
                        ['label' => 'Employee', 'value' => $requester],
                        ['label' => 'Leave Type', 'value' => $leave->leaveType?->name ?: 'Leave'],
                        ['label' => 'From', 'value' => optional($leave->from_date)->format('d M Y') ?: 'N/A'],
                        ['label' => 'To', 'value' => optional($leave->to_date)->format('d M Y') ?: 'N/A'],
                        ['label' => 'Days', 'value' => $days],
                        ['label' => 'Duration', 'value' => ucfirst(str_replace('_', ' ', (string) $leave->duration_mode))],
                        ['label' => 'Requested At', 'value' => optional($leave->created_at)->format('d M Y, h:i A') ?: 'N/A'],
                        $leave->reason ? ['label' => 'Reason', 'value' => $leave->reason] : null,
                    ])),
                    'created_at' => optional($leave->created_at)->toIso8601String(),
                    'age' => optional($leave->created_at)->diffForHumans(null, true) . ' ago',
                    'view_url' => route('admin.attendance.leaves.index'),
                    'approve_url' => route('admin.attendance.leaves.approve', $leave),
                    'reject_url' => route('admin.attendance.leaves.reject', $leave),
                    'reject_field' => 'remarks',
                    'reject_prompt' => 'Leave reject remarks likhiye',
                ];
            });
    }

    private function getPurchaseOrderApprovalItems()
    {
        return PurchaseOrder::query()
            ->with(['creator', 'deleteRequester'])
            ->whereIn('status', [PurchaseOrder::STATUS_SUBMITTED, PurchaseOrder::STATUS_DELETE_REQUESTED])
            ->latest('created_at')
            ->limit(5)
            ->get()
            ->map(function (PurchaseOrder $order) {
                $requester = $order->creator?->name ?: 'PO creator';
                $amount = 'Rs ' . number_format((float) $order->total_amount);
                $purpose = $order->purpose ?: 'Purchase order';
                $isDeleteRequest = $order->status === PurchaseOrder::STATUS_DELETE_REQUESTED;

                return [
                    'id' => 'po-' . $order->id,
                    'type' => 'po',
                    'icon' => 'PO',
                    'title' => $isDeleteRequest ? 'PO delete approval' : 'Purchase order approval',
                    'badge' => strtoupper((string) ($order->vendor_name ?: 'VENDOR')),
                    'requester' => $isDeleteRequest ? ($order->deleteRequester?->name ?: 'Finance Manager') : $requester,
                    'summary' => $isDeleteRequest
                        ? "Delete requested for {$purpose} PO of {$amount}. Admin delete approval pending."
                        : "{$purpose} PO for {$amount} generated by {$requester}. Admin confirmation pending.",
                    'details' => array_values(array_filter([
                        ['label' => 'Request Type', 'value' => $isDeleteRequest ? 'Delete approval' : 'Purchase approval'],
                        ['label' => 'Purpose', 'value' => $purpose],
                        ['label' => 'Amount', 'value' => $amount],
                        ['label' => 'Vendor', 'value' => $order->vendor_name ?: 'N/A'],
                        ['label' => 'Requested By', 'value' => $isDeleteRequest ? ($order->deleteRequester?->name ?: 'Finance Manager') : $requester],
                        ['label' => 'Required By', 'value' => optional($order->required_by_date)->format('d M Y') ?: 'N/A'],
                        ['label' => 'Submitted At', 'value' => optional($isDeleteRequest ? $order->delete_requested_at : $order->created_at)->format('d M Y, h:i A') ?: 'N/A'],
                        $isDeleteRequest && $order->delete_request_reason ? ['label' => 'Delete Reason', 'value' => $order->delete_request_reason] : null,
                    ])),
                    'created_at' => optional($isDeleteRequest ? $order->delete_requested_at : $order->created_at)->toIso8601String(),
                    'age' => optional($isDeleteRequest ? $order->delete_requested_at : $order->created_at)->diffForHumans(null, true) . ' ago',
                    'view_url' => route('admin.purchase-orders.show', $order),
                    'approve_url' => $isDeleteRequest ? route('admin.purchase-orders.approve-delete', $order) : route('admin.purchase-orders.approve', $order),
                    'reject_url' => $isDeleteRequest ? route('admin.purchase-orders.reject-delete', $order) : route('admin.purchase-orders.reject', $order),
                    'reject_field' => $isDeleteRequest ? 'delete_reject_reason' : 'remark',
                    'reject_prompt' => $isDeleteRequest ? 'PO delete reject reason likhiye' : 'PO reject remark likhiye',
                ];
            });
    }

    private function getLeadDownloadApprovalItems()
    {
        return LeadDownloadRequest::query()
            ->with(['requester.role'])
            ->whereIn('status', [LeadDownloadRequest::STATUS_PENDING, LeadDownloadRequest::STATUS_APPROVED])
            ->latest('created_at')
            ->limit(5)
            ->get()
            ->map(function (LeadDownloadRequest $request) {
                $requester = $request->requester?->name ?: 'Sales user';
                $role = $request->requester?->role?->name ?: 'Sales';
                $format = strtoupper((string) ($request->format ?: 'CSV'));
                $scope = ucfirst(str_replace('_', ' ', $request->filters['assigned_scope'] ?? 'my team'));
                $dateRange = ucfirst(str_replace('_', ' ', $request->filters['date_range'] ?? 'all time'));

                return [
                    'id' => 'lead-download-' . $request->id,
                    'type' => 'export',
                    'icon' => 'EX',
                    'title' => 'Lead export request',
                    'badge' => $format,
                    'requester' => $requester,
                    'summary' => "{$requester} ({$role}) requested {$format} lead export. Scope: {$scope}. Date range: {$dateRange}. Admin approval pending.",
                    'details' => array_values(array_filter([
                        ['label' => 'Requested By', 'value' => $requester],
                        ['label' => 'Role', 'value' => $role],
                        ['label' => 'Format', 'value' => $format],
                        ['label' => 'Scope', 'value' => $scope],
                        ['label' => 'Date Range', 'value' => $dateRange],
                        ['label' => 'Requested At', 'value' => optional($request->created_at)->format('d M Y, h:i A') ?: 'N/A'],
                        !empty($request->filters['user_id']) ? ['label' => 'User Filter', 'value' => (string) $request->filters['user_id']] : null,
                    ])),
                    'created_at' => optional($request->created_at)->toIso8601String(),
                    'age' => optional($request->created_at)->diffForHumans(null, true) . ' ago',
                    'view_url' => route('admin.lead-download-requests.index', ['status' => LeadDownloadRequest::STATUS_PENDING]),
                    'approve_url' => route('admin.lead-download-requests.approve', $request),
                    'reject_url' => route('admin.lead-download-requests.reject', $request),
                    'reject_field' => 'rejection_reason',
                    'reject_prompt' => 'Export reject reason likhiye',
                ];
            });
    }

    private function getUserStats(?array $dateRange = null)
    {
        $usersQuery = User::where('is_active', true);
        
        if ($dateRange) {
            $usersQuery->whereBetween('created_at', [$dateRange['start_date'], $dateRange['end_date']]);
        }

        $usersByRole = $usersQuery->with('role')
            ->get()
            ->groupBy(function($user) {
                return $user->role->slug ?? 'no_role';
            })
            ->map(function($group) {
                return $group->count();
            });

        $total = (int) $usersByRole->sum();
        $newQuery = User::where('is_active', true);
        $active24hQuery = User::where('is_active', true);

        if ($dateRange) {
            $active24hQuery->whereBetween('updated_at', [$dateRange['start_date'], $dateRange['end_date']]);
        } else {
            $newQuery->whereMonth('created_at', Carbon::now()->month)
                ->whereYear('created_at', Carbon::now()->year);
            $active24hQuery->where('updated_at', '>=', Carbon::now()->subDay());
        }

        return [
            'total' => $total,
            'by_role' => [
                'admin' => $usersByRole->get('admin', 0),
                'crm' => $usersByRole->get('crm', 0),
                'sales_manager' => $usersByRole->get('sales_manager', 0),
                'sales_executive' => $usersByRole->get('sales_executive', 0),
                'telecaller' => $usersByRole->get('sales_executive', 0), // merged into sales_executive
            ],
            'new_this_month' => $dateRange ? $total : $newQuery->count(),
            'active_24h' => $active24hQuery->count(),
        ];
    }

    private function getLeadStats(?array $dateRange = null)
    {
        $query = Lead::query();
        
        if ($dateRange) {
            $query->whereBetween('created_at', [$dateRange['start_date'], $dateRange['end_date']]);
        }

        $leadsByStatus = (clone $query)->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $total = (int) $leadsByStatus->sum();
        if (!$dateRange) {
            $newTodayQuery = Lead::query();
            $newWeekQuery = Lead::query();
            $newMonthQuery = Lead::query();
            $newTodayQuery->whereDate('created_at', Carbon::today());
            $newWeekQuery->where('created_at', '>=', Carbon::now()->startOfWeek());
            $newMonthQuery->whereMonth('created_at', Carbon::now()->month)
                ->whereYear('created_at', Carbon::now()->year);
        }

        return [
            'total' => $total,
            'by_status' => [
                'new' => $leadsByStatus->get('new', 0),
                'fresh_transfer' => $leadsByStatus->get('fresh_transfer', 0),
                'contacted' => $leadsByStatus->get('contacted', 0),
                'connected' => $leadsByStatus->get('connected', 0),
                'verified_prospect' => $leadsByStatus->get('verified_prospect', 0),
                'meeting_scheduled' => $leadsByStatus->get('meeting_scheduled', 0),
                'meeting_completed' => $leadsByStatus->get('meeting_completed', 0),
                'visit_scheduled' => $leadsByStatus->get('visit_scheduled', 0),
                'visit_done' => $leadsByStatus->get('visit_done', 0),
                'revisited_scheduled' => $leadsByStatus->get('revisited_scheduled', 0),
                'revisited_completed' => $leadsByStatus->get('revisited_completed', 0),
                'closed' => $leadsByStatus->get('closed', 0),
                'dead' => $leadsByStatus->get('dead', 0),
                'on_hold' => $leadsByStatus->get('on_hold', 0),
            ],
            'new_today' => $dateRange ? $total : $newTodayQuery->count(),
            'new_this_week' => $dateRange ? $total : $newWeekQuery->count(),
            'new_this_month' => $dateRange ? $total : $newMonthQuery->count(),
        ];
    }

    private function getProjectStats(?array $dateRange = null)
    {
        $query = Project::query();
        
        if ($dateRange) {
            $query->whereBetween('created_at', [$dateRange['start_date'], $dateRange['end_date']]);
        }

        $summary = $query
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active')
            ->selectRaw('SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive')
            ->first();

        return [
            'total' => (int) ($summary->total ?? 0),
            'active' => (int) ($summary->active ?? 0),
            'inactive' => (int) ($summary->inactive ?? 0),
        ];
    }

    private function getActivitySummary(?array $dateRange = null)
    {
        $activitiesQuery = ActivityLog::with('user');
        
        if ($dateRange) {
            $activitiesQuery->whereBetween('created_at', [$dateRange['start_date'], $dateRange['end_date']]);
        }

        $recentActivities = $activitiesQuery->latest()
            ->limit(50)
            ->get();

        $activitiesByType = $recentActivities->groupBy('action')
            ->map(function($group) {
                return $group->count();
            });

        $mostActiveUsersQuery = ActivityLog::select('user_id', DB::raw('count(*) as count'));
        
        if ($dateRange) {
            $mostActiveUsersQuery->whereBetween('created_at', [$dateRange['start_date'], $dateRange['end_date']]);
        } else {
            $mostActiveUsersQuery->where('created_at', '>=', Carbon::now()->subDays(7));
        }

        $mostActiveUsers = $mostActiveUsersQuery->groupBy('user_id')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->with('user')
            ->get();

        return [
            'recent_count' => $recentActivities->count(),
            'by_type' => $activitiesByType->toArray(),
            'most_active_users' => $mostActiveUsers->map(function($log) {
                return [
                    'user_id' => $log->user_id,
                    'user_name' => ($log->user && $log->user->name) ? $log->user->name : 'Unknown',
                    'count' => $log->count ?? 0,
                ];
            }),
        ];
    }

    private function getSystemHealth(?array $dateRange = null)
    {
        $pendingVerificationsQuery = Lead::where('needs_verification', true);
        $importsQuery = ImportBatch::query();

        if ($dateRange) {
            $pendingVerificationsQuery->whereBetween('verification_requested_at', [$dateRange['start_date'], $dateRange['end_date']]);
            $importsQuery->whereBetween('created_at', [$dateRange['start_date'], $dateRange['end_date']]);
        } else {
            $importsQuery->where(function ($query) {
                $query->whereIn('status', ['pending', 'processing'])
                    ->orWhere(fn ($failed) => $failed->where('status', 'failed')->where('created_at', '>=', Carbon::now()->subDays(7)));
            });
        }

        $imports = $importsQuery
            ->selectRaw("SUM(CASE WHEN status IN ('pending', 'processing') THEN 1 ELSE 0 END) as pending")
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed")
            ->first();

        return [
            'pending_verifications' => $pendingVerificationsQuery->count(),
            'active_automations'    => 0,
            'pending_imports' => (int) ($imports->pending ?? 0),
            'failed_imports' => (int) ($imports->failed ?? 0),
        ];
    }

    private function getRecentLeads($limit = 10, ?array $dateRange = null, ?string $source = null)
    {
        $query = Lead::query()
            ->select(['id', 'name', 'phone', 'source', 'status', 'created_at', 'created_by'])
            ->with([
                'creator:id,name',
                'latestFbLead:fb_leads.id,fb_leads.crm_lead_id',
                'latestImportedLead:imported_leads.id,imported_leads.lead_id',
            ]);
        
        if ($dateRange) {
            $query->whereBetween('created_at', [$dateRange['start_date'], $dateRange['end_date']]);
        }

        $normalizedSource = trim((string) $source);
        if ($normalizedSource !== '') {
            $query->where('source', Lead::normalizeSource($normalizedSource));
        }

        return $query->latest()
            ->limit($limit)
            ->get()
            ->map(function($lead) {
                return [
                    'id' => $lead->id,
                    'name' => $lead->name ?? 'N/A',
                    'phone' => $lead->phone ?? 'N/A',
                    'source' => $lead->source_label,
                    'status' => $lead->status ?? 'N/A',
                    'created_at' => $lead->created_at ? $lead->created_at->format('Y-m-d H:i:s') : 'N/A',
                    'created_by' => (
                        $lead->latestFbLead
                        || $lead->latestImportedLead
                        || in_array(Lead::normalizeSource((string) $lead->source), [
                            'meta', 'ivr', 'sheet', 'whatsapp', 'website', 'google', '99acres', 'housing',
                        ], true)
                    ) ? 'System' : ($lead->creator?->name ?: 'System'),
                ];
            })
            ->filter(function($lead) {
                return $lead !== null;
            })
            ->values();
    }

    private function getRecentActivities($limit = 20, ?array $dateRange = null)
    {
        $query = ActivityLog::with('user');
        
        if ($dateRange) {
            $query->whereBetween('created_at', [$dateRange['start_date'], $dateRange['end_date']]);
        }

        return $query->latest()
            ->limit($limit)
            ->get()
            ->map(function($log) {
                return [
                    'id' => $log->id,
                    'user_name' => ($log->user && $log->user->name) ? $log->user->name : 'System',
                    'action' => $log->action ?? 'N/A',
                    'description' => $log->description ?? 'N/A',
                    'created_at' => $log->created_at ? $log->created_at->format('Y-m-d H:i:s') : 'N/A',
                ];
            })
            ->filter(function($log) {
                return $log !== null;
            })
            ->values();
    }

    /**
     * Get agents visits vs meetings data for Senior Managers and Sales Executives
     */
    private function getAgentsVisitsVsMeetings(?array $dateRange = null): array
    {
        $roleIds = Role::whereIn('slug', ['sales_manager', 'sales_executive'])->pluck('id');
        if ($roleIds->count() < 2) {
            return [];
        }

        // Get all active Senior Managers and Sales Executives
        $agents = User::where('is_active', true)
            ->whereIn('role_id', $roleIds)
            ->with('role')
            ->get();

        $agentIds = $agents->pluck('id');
        $meetingsQuery = Meeting::query()
            ->whereIn('assigned_to', $agentIds)
            ->where('is_converted', false);
        $visitsQuery = SiteVisit::query()->whereIn('assigned_to', $agentIds);
        $closersQuery = SiteVisit::query()
            ->whereIn('assigned_to', $agentIds)
            ->whereIn('closer_status', ['approved', 'verified']);
        if ($dateRange) {
            $meetingsQuery->whereBetween('created_at', [$dateRange['start_date'], $dateRange['end_date']]);
            $visitsQuery->whereBetween('created_at', [$dateRange['start_date'], $dateRange['end_date']]);
            $closersQuery->whereBetween('closer_verified_at', [$dateRange['start_date'], $dateRange['end_date']]);
        }

        $meetings = $meetingsQuery->selectRaw('assigned_to, COUNT(*) as total')->groupBy('assigned_to')->pluck('total', 'assigned_to');
        $visits = $visitsQuery->selectRaw('assigned_to, COUNT(*) as total')->groupBy('assigned_to')->pluck('total', 'assigned_to');
        $closers = $closersQuery->selectRaw('assigned_to, COUNT(*) as total')->groupBy('assigned_to')->pluck('total', 'assigned_to');

        $result = $agents->map(function (User $agent) use ($meetings, $visits, $closers) {
            return [
                'agent_id' => $agent->id,
                'agent_name' => $agent->name,
                'role' => $agent->role ? $agent->role->name : 'Unknown',
                'role_slug' => $agent->role ? $agent->role->slug : 'unknown',
                'meetings' => (int) ($meetings[$agent->id] ?? 0),
                'visits' => (int) ($visits[$agent->id] ?? 0),
                'closers' => (int) ($closers[$agent->id] ?? 0),
            ];
        })->all();

        // Sort by total activity (meetings + visits + closers) descending
        usort($result, function($a, $b) {
            $totalA = $a['meetings'] + $a['visits'] + $a['closers'];
            $totalB = $b['meetings'] + $b['visits'] + $b['closers'];
            return $totalB - $totalA;
        });

        return $result;
    }

    /**
     * Get property segments distribution
     */
    private function getPropertySegments(?array $dateRange = null): array
    {
        $row = Lead::query()
            ->when($dateRange, fn ($query) => $query->whereBetween('created_at', [$dateRange['start_date'], $dateRange['end_date']]))
            ->selectRaw("SUM(CASE WHEN property_type = 'plot' THEN 1 ELSE 0 END) as plot_count")
            ->selectRaw("SUM(CASE WHEN property_type = 'commercial' THEN 1 ELSE 0 END) as commercial_count")
            ->selectRaw("SUM(CASE WHEN property_type IN ('apartment', 'villa') THEN 1 ELSE 0 END) as residential_count")
            ->selectRaw("SUM(CASE WHEN property_type = 'other' OR property_type IS NULL THEN 1 ELSE 0 END) as other_count")
            ->first();

        return [
            'plot' => (int) ($row->plot_count ?? 0),
            'commercial' => (int) ($row->commercial_count ?? 0),
            'residential' => (int) ($row->residential_count ?? 0),
            'other' => (int) ($row->other_count ?? 0),
        ];
    }

    private function getDemandInsights(?array $dateRange = null): array
    {
        $propertyLabels = ['Commercial', 'Apartment', 'Plot', 'Villa/Floor', 'Other', 'Undefined'];
        $budgetLabels = ['Below 50 Lacs', '50-75 Lacs', '75 Lacs-1 Cr', '1 Cr-2 Cr', 'Above 2 Cr', 'Undefined'];
        $propertyCounts = array_fill_keys($propertyLabels, 0);
        $budgetCounts = array_fill_keys($budgetLabels, 0);
        $locationCounts = [];
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
        $formKeys = ['category', 'type', 'budget', 'interested_projects', 'location', 'preferred_location', 'city'];

        $leads = Lead::query()
            ->select(['id', 'status', 'property_type', 'budget', 'budget_min', 'budget_max', 'city', 'preferred_location', 'created_at'])
            ->with([
                'formFieldValues' => function ($query) use ($formKeys) {
                    $query->whereIn('field_key', $formKeys)
                        ->select(['id', 'lead_id', 'field_key', 'field_value']);
                },
                'prospects' => function ($query) {
                    $query->select(['id', 'lead_id', 'budget', 'preferred_location']);
                },
            ])
            ->where(function ($query) use ($demandStatuses, $formKeys) {
                $query->whereIn('status', $demandStatuses)
                    ->orWhereHas('formFieldValues', function ($formQuery) use ($formKeys) {
                        $formQuery->whereIn('field_key', $formKeys)
                            ->whereNotNull('field_value')
                            ->where('field_value', '!=', '')
                            ->whereRaw('LOWER(TRIM(field_value)) NOT IN (?, ?, ?, ?)', ['n.a', 'na', 'n/a', 'null']);
                    });
            })
            ->when($dateRange, function ($query) use ($dateRange) {
                $query->whereBetween('created_at', [$dateRange['start_date'], $dateRange['end_date']]);
            })
            ->get();

        foreach ($leads as $lead) {
            $formValues = $lead->formFieldValues->pluck('field_value', 'field_key');
            $propertyValue = $formValues->get('category')
                ?: $formValues->get('type')
                ?: $lead->property_type;
            $budgetValue = $formValues->get('budget')
                ?: $lead->budget
                ?: optional($lead->prospects->first())->budget;
            $locationValue = $formValues->get('location')
                ?: $formValues->get('preferred_location')
                ?: $formValues->get('city')
                ?: $lead->preferred_location
                ?: $lead->city
                ?: optional($lead->prospects->first())->preferred_location;

            $propertyCounts[$this->normalizeDemandPropertyType($propertyValue)]++;
            $budgetCounts[$this->normalizeDemandBudgetRange($budgetValue, $lead->budget_min, $lead->budget_max)]++;
            $locationLabel = $this->normalizeDemandLocation($locationValue);
            $locationCounts[$locationLabel] = ($locationCounts[$locationLabel] ?? 0) + 1;
        }

        return [
            'total' => $leads->count(),
            'property_types' => $this->formatDemandInsightRows($propertyCounts),
            'budget_ranges' => $this->formatDemandInsightRows($budgetCounts),
            'locations' => $this->formatDemandTopRows($locationCounts),
        ];
    }

    private function formatDemandInsightRows(array $counts): array
    {
        $total = array_sum($counts);

        return collect($counts)
            ->map(function ($count, $label) use ($total) {
                return [
                    'label' => $label,
                    'count' => (int) $count,
                    'percentage' => $total > 0 ? round(($count / $total) * 100, 1) : 0,
                ];
            })
            ->values()
            ->all();
    }

    private function formatDemandTopRows(array $counts): array
    {
        $total = array_sum($counts);

        return collect($counts)
            ->sortDesc()
            ->map(function ($count, $label) use ($total) {
                return [
                    'label' => $label,
                    'count' => (int) $count,
                    'percentage' => $total > 0 ? round(($count / $total) * 100, 1) : 0,
                ];
            })
            ->values()
            ->all();
    }

    private function normalizeDemandPropertyType($value): string
    {
        $text = $this->normalizeDemandText($value);

        if ($text === null) {
            return 'Undefined';
        }

        if (str_contains($text, 'commercial') || str_contains($text, 'shop') || str_contains($text, 'office')) {
            return 'Commercial';
        }

        if (str_contains($text, 'villa') || str_contains($text, 'floor') || str_contains($text, 'independent')) {
            return 'Villa/Floor';
        }

        if (str_contains($text, 'plot')) {
            return 'Plot';
        }

        if (str_contains($text, 'apartment') || str_contains($text, 'flat') || str_contains($text, 'residential')) {
            return 'Apartment';
        }

        if (str_contains($text, 'other') || str_contains($text, 'both')) {
            return 'Other';
        }

        return 'Undefined';
    }

    private function normalizeDemandBudgetRange($value, $budgetMin = null, $budgetMax = null): string
    {
        $text = $this->normalizeDemandText($value);

        if ($text !== null) {
            if (str_contains($text, 'below') && (str_contains($text, '50') || str_contains($text, 'fifty'))) {
                return 'Below 50 Lacs';
            }
            if (str_contains($text, '50') && str_contains($text, '75')) {
                return '50-75 Lacs';
            }
            if (str_contains($text, '75') && (str_contains($text, '1 cr') || str_contains($text, '1cr'))) {
                return '75 Lacs-1 Cr';
            }
            if ((str_contains($text, '1 cr') || str_contains($text, '1cr') || str_contains($text, 'above 1')) && !str_contains($text, 'above 2')) {
                return '1 Cr-2 Cr';
            }
            if (str_contains($text, 'above 2') || str_contains($text, '2 cr') || str_contains($text, '2cr') || str_contains($text, '5 cr')) {
                return 'Above 2 Cr';
            }

            $amountInLacs = $this->parseBudgetAmountToLacs($text);
            if ($amountInLacs !== null) {
                return $this->budgetBucketFromLacs($amountInLacs);
            }
        }

        $numericBudget = $budgetMax ?: $budgetMin;
        if ($numericBudget !== null && $numericBudget !== '') {
            return $this->budgetBucketFromLacs(((float) $numericBudget) / 100000);
        }

        return 'Undefined';
    }

    private function normalizeDemandLocation($value): string
    {
        $text = $this->normalizeDemandText($value);

        if ($text === null) {
            return 'Undefined';
        }

        $text = preg_replace('/\s+/', ' ', str_replace(['_', '-'], ' ', $text));
        return ucwords(trim($text)) ?: 'Undefined';
    }

    private function normalizeDemandText($value): ?string
    {
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value);
        }

        $text = strtolower(trim((string) $value));
        if ($text === '' || in_array($text, ['n.a', 'na', 'n/a', 'null', 'undefined', 'none'], true)) {
            return null;
        }

        return str_replace(['–', '—'], '-', $text);
    }

    private function parseBudgetAmountToLacs(string $text): ?float
    {
        if (!preg_match_all('/\d+(?:\.\d+)?/', $text, $matches) || empty($matches[0])) {
            return null;
        }

        $numbers = $matches[0];
        $amount = (float) end($numbers);
        if ($amount <= 0) {
            return null;
        }

        if (str_contains($text, 'cr')) {
            return $amount * 100;
        }

        if (str_contains($text, 'lac') || str_contains($text, 'lakh') || str_contains($text, 'lacs')) {
            return $amount;
        }

        return $amount >= 100000 ? $amount / 100000 : $amount;
    }

    private function budgetBucketFromLacs(float $amountInLacs): string
    {
        if ($amountInLacs < 50) {
            return 'Below 50 Lacs';
        }
        if ($amountInLacs < 75) {
            return '50-75 Lacs';
        }
        if ($amountInLacs < 100) {
            return '75 Lacs-1 Cr';
        }
        if ($amountInLacs < 200) {
            return '1 Cr-2 Cr';
        }

        return 'Above 2 Cr';
    }

    /**
     * Get telecaller performance metrics - Same logic as CRM API
     */
    private function getTelecallerPerformance(?array $dateRange = null): array
    {
        $salesExecutiveRole = Role::where('slug', Role::SALES_EXECUTIVE)->first();
        
        if (!$salesExecutiveRole) {
            Log::warning('Sales Executive role not found');
            return [];
        }

        $telecallers = User::where('is_active', true)
            ->where('role_id', $salesExecutiveRole->id)
            ->get();

        if ($telecallers->isEmpty()) {
            Log::info('No active telecallers found');
            return [];
        }
        
        Log::info('Found ' . $telecallers->count() . ' telecallers for performance calculation');

        $startDate = $dateRange['start_date'] ?? null;
        $endDate = $dateRange['end_date'] ?? null;
        $userIds = $telecallers->pluck('id');
        $range = fn ($query, string $column) => ($startDate && $endDate)
            ? $query->whereBetween($column, [$startDate, $endDate])
            : $query;

        $allocated = $range(LeadAssignment::query()->whereIn('assigned_to', $userIds)->where('is_active', true), 'assigned_at')
            ->selectRaw('assigned_to, COUNT(*) as aggregate')->groupBy('assigned_to')->pluck('aggregate', 'assigned_to');
        $called = $range(TelecallerTask::query()->whereIn('assigned_to', $userIds)->where('status', 'completed'), 'completed_at')
            ->selectRaw('assigned_to, COUNT(*) as aggregate')->groupBy('assigned_to')->pluck('aggregate', 'assigned_to');
        $prospectStats = $range(Prospect::query()->whereIn('telecaller_id', $userIds), 'verified_at')
            ->selectRaw("telecaller_id, SUM(CASE WHEN verification_status = 'approved' THEN 1 ELSE 0 END) as interested, SUM(CASE WHEN verification_status = 'rejected' THEN 1 ELSE 0 END) as rejected")
            ->groupBy('telecaller_id')->get()->keyBy('telecaller_id');
        $crmStats = $range(CrmAssignment::query()->whereIn('assigned_to', $userIds), 'assigned_at')
            ->selectRaw("assigned_to, SUM(CASE WHEN call_status = 'called_not_interested' THEN 1 ELSE 0 END) as not_interested, SUM(CASE WHEN call_status = 'pending' AND cnp_count > 0 THEN 1 ELSE 0 END) as cnp")
            ->groupBy('assigned_to')->get()->keyBy('assigned_to');
        $junk = $range(LeadAssignment::query()
            ->join('leads', 'leads.id', '=', 'lead_assignments.lead_id')
            ->whereIn('lead_assignments.assigned_to', $userIds)
            ->where('lead_assignments.is_active', true)
            ->where('leads.status', 'junk')
            ->whereNull('leads.deleted_at'), 'lead_assignments.assigned_at')
            ->selectRaw('lead_assignments.assigned_to, COUNT(DISTINCT leads.id) as aggregate')
            ->groupBy('lead_assignments.assigned_to')->pluck('aggregate', 'lead_assignments.assigned_to');
        $remaining = $range(LeadAssignment::query()
            ->whereIn('assigned_to', $userIds)
            ->where('is_active', true)
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')->from('telecaller_tasks')
                    ->whereColumn('telecaller_tasks.lead_id', 'lead_assignments.lead_id')
                    ->whereColumn('telecaller_tasks.assigned_to', 'lead_assignments.assigned_to')
                    ->where('telecaller_tasks.status', 'completed');
            })
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')->from('crm_assignments')
                    ->whereColumn('crm_assignments.lead_id', 'lead_assignments.lead_id')
                    ->whereColumn('crm_assignments.assigned_to', 'lead_assignments.assigned_to')
                    ->where(function ($response) {
                        $response->where('cnp_count', '>', 0)->orWhere('call_status', '!=', 'pending');
                    });
            }), 'assigned_at')
            ->selectRaw('assigned_to, COUNT(*) as aggregate')->groupBy('assigned_to')->pluck('aggregate', 'assigned_to');

        $result = $telecallers->map(function (User $telecaller) use ($allocated, $called, $remaining, $prospectStats, $crmStats, $junk) {
            $prospects = $prospectStats->get($telecaller->id);
            $crm = $crmStats->get($telecaller->id);

            return [
                'telecaller_id' => $telecaller->id,
                'telecaller_name' => $telecaller->name,
                'allocated' => (int) ($allocated[$telecaller->id] ?? 0),
                'called' => (int) ($called[$telecaller->id] ?? 0),
                'remaining' => (int) ($remaining[$telecaller->id] ?? 0),
                'interested' => (int) ($prospects->interested ?? 0),
                'junk' => (int) ($junk[$telecaller->id] ?? 0),
                'not_interested' => (int) ($crm->not_interested ?? 0) + (int) ($prospects->rejected ?? 0),
                'cnp' => (int) ($crm->cnp ?? 0),
                'follow_up' => 0,
            ];
        })->all();

        // Sort by allocated (descending)
        usort($result, function($a, $b) {
            return $b['allocated'] - $a['allocated'];
        });

        return $result;
    }

    /**
     * Get user-wise leads that are allocated but not yet responded (no call outcome).
     * Same "remaining" logic as getTelecallerPerformance. Includes all users except Admin, CRM, Sales Head.
     */
    private function getLeadsPendingResponseByUser(?array $dateRange = null): array
    {
        $users = User::with('role')
            ->where('is_active', true)
            ->whereHas('role', function ($q) {
                $q->whereNotIn('slug', [Role::ADMIN, Role::CRM]);
            })
            ->get()
            ->filter(function ($user) {
                if ($user->role && $user->role->slug === Role::SALES_MANAGER && $user->manager_id === null) {
                    return false;
                }
                return true;
            })
            ->values();

        if ($users->isEmpty()) {
            return [];
        }

        $startDate = $dateRange['start_date'] ?? null;
        $endDate = $dateRange['end_date'] ?? null;
        $workedLeadStatuses = [
            'verified_prospect', 'meeting_scheduled', 'meeting_completed', 'visit_scheduled',
            'visit_done', 'revisited_scheduled', 'revisited_completed', 'closed', 'dead',
            'junk', 'not_interested', 'on_hold',
        ];

        $assignmentsQuery = LeadAssignment::query()
            ->whereIn('assigned_to', $users->pluck('id'))
            ->where('is_active', true)
            ->whereHas('lead', function ($leadQuery) use ($workedLeadStatuses) {
                $leadQuery->whereNotIn('status', $workedLeadStatuses)
                    ->whereNull('next_followup_at')
                    ->where(fn ($deadQuery) => $deadQuery->whereNull('is_dead')->orWhere('is_dead', false));
            })
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')->from('telecaller_tasks')
                    ->whereColumn('telecaller_tasks.lead_id', 'lead_assignments.lead_id')
                    ->whereColumn('telecaller_tasks.assigned_to', 'lead_assignments.assigned_to')
                    ->where('telecaller_tasks.status', 'completed');
            })
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')->from('crm_assignments')
                    ->whereColumn('crm_assignments.lead_id', 'lead_assignments.lead_id')
                    ->whereColumn('crm_assignments.assigned_to', 'lead_assignments.assigned_to')
                    ->where(fn ($response) => $response->where('cnp_count', '>', 0)->orWhere('call_status', '!=', 'pending'));
            })
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')->from('follow_ups')
                    ->whereColumn('follow_ups.lead_id', 'lead_assignments.lead_id')
                    ->whereColumn('follow_ups.created_by', 'lead_assignments.assigned_to')
                    ->whereColumn('follow_ups.created_at', '>=', 'lead_assignments.assigned_at')
                    ->whereNull('follow_ups.deleted_at');
            })
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')->from('tasks')
                    ->whereColumn('tasks.lead_id', 'lead_assignments.lead_id')
                    ->where(function ($taskUserQuery) {
                        $taskUserQuery->whereColumn('tasks.assigned_to', 'lead_assignments.assigned_to')
                            ->orWhereColumn('tasks.created_by', 'lead_assignments.assigned_to');
                    })
                    ->where(function ($responseQuery) {
                        $responseQuery->whereIn('tasks.status', ['in_progress', 'completed'])
                            ->orWhereNotNull('tasks.completed_at')->orWhereNotNull('tasks.outcome')
                            ->orWhereNotNull('tasks.outcome_recorded_at');
                    })
                    ->whereColumn('tasks.created_at', '>=', 'lead_assignments.assigned_at')
                    ->whereNull('tasks.deleted_at');
            })
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')->from('telecaller_tasks')
                    ->whereColumn('telecaller_tasks.lead_id', 'lead_assignments.lead_id')
                    ->where(function ($taskUserQuery) {
                        $taskUserQuery->whereColumn('telecaller_tasks.assigned_to', 'lead_assignments.assigned_to')
                            ->orWhereColumn('telecaller_tasks.created_by', 'lead_assignments.assigned_to');
                    })
                    ->where(function ($responseQuery) {
                        $responseQuery->whereIn('telecaller_tasks.status', ['in_progress', 'completed'])
                            ->orWhereNotNull('telecaller_tasks.completed_at')->orWhereNotNull('telecaller_tasks.outcome');
                    })
                    ->whereColumn('telecaller_tasks.created_at', '>=', 'lead_assignments.assigned_at')
                    ->whereNull('telecaller_tasks.deleted_at');
            })
            ->with('lead:id,name,phone');

        if ($startDate && $endDate) {
            $assignmentsQuery->whereBetween('assigned_at', [$startDate, $endDate]);
        }

        $pendingByUser = $assignmentsQuery->orderBy('assigned_at', 'desc')->get()->groupBy('assigned_to');
        $result = $users->map(function (User $user) use ($pendingByUser) {
            $leads = $pendingByUser->get($user->id, collect())->map(function (LeadAssignment $assignment) {
                return [
                    'lead_id' => $assignment->lead?->id,
                    'name' => $assignment->lead?->name,
                    'phone' => $assignment->lead?->phone,
                    'assigned_at' => $assignment->assigned_at?->toIso8601String(),
                ];
            })->filter(fn (array $lead) => !empty($lead['lead_id']))->values()->all();

            return ['user_id' => $user->id, 'user_name' => $user->name, 'pending_count' => count($leads), 'leads' => $leads];
        })->all();

        // Sort by pending_count descending
        usort($result, function ($a, $b) {
            return $b['pending_count'] - $a['pending_count'];
        });

        return $result;
    }

    /**
     * Get user-wise average lead response time (assign to first response) for the date range.
     * Only includes Sales Executives who have at least one responded lead in the period.
     */
    private function getAverageResponseTimeByUser(?array $dateRange = null): array
    {
        return $this->dashboardResponseTimeService->getAverageResponseTimeByUser(
            $dateRange['start_date'] ?? null,
            $dateRange['end_date'] ?? null
        );
    }

    /**
     * Get user visits and meetings data with date filters
     */
    private function getUserVisitsMeetingsData(string $filter = 'this_month'): array
    {
        $roleIds = Role::whereIn('slug', ['sales_manager', 'sales_executive'])->pluck('id');
        if ($roleIds->count() < 2) {
            return [
                'users' => [],
                'summary' => [
                    'total_users' => 0,
                    'total_visits' => 0,
                    'total_meetings' => 0,
                ],
            ];
        }

        // Get all active Senior Managers and Sales Executives
        $users = User::where('is_active', true)
            ->whereIn('role_id', $roleIds)
            ->with('role')
            ->get();

        // Calculate date range based on filter
        $dateRange = $this->getVisitsMeetingsDateRange($filter);

        $userIds = $users->pluck('id');
        $visitsQuery = SiteVisit::query()->whereIn('assigned_to', $userIds);
        $meetingsQuery = Meeting::query()
            ->whereIn('assigned_to', $userIds)
            ->where('is_converted', false);
        if ($dateRange) {
            $visitsQuery->whereBetween('scheduled_at', [$dateRange['start_date'], $dateRange['end_date']]);
            $meetingsQuery->whereBetween('scheduled_at', [$dateRange['start_date'], $dateRange['end_date']]);
        }

        $visits = $visitsQuery->selectRaw('assigned_to, COUNT(*) as total')->groupBy('assigned_to')->pluck('total', 'assigned_to');
        $meetings = $meetingsQuery->selectRaw('assigned_to, COUNT(*) as total')->groupBy('assigned_to')->pluck('total', 'assigned_to');
        $totalVisits = (int) $visits->sum();
        $totalMeetings = (int) $meetings->sum();

        $result = $users->map(function (User $user) use ($visits, $meetings) {
            $visitsCount = (int) ($visits[$user->id] ?? 0);
            $meetingsCount = (int) ($meetings[$user->id] ?? 0);

            return [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'role' => $user->role ? $user->role->name : 'Unknown',
                'role_slug' => $user->role ? $user->role->slug : 'unknown',
                'visits_count' => $visitsCount,
                'meetings_count' => $meetingsCount,
                'total' => $visitsCount + $meetingsCount,
            ];
        })->all();

        // Sort by total (descending)
        usort($result, function($a, $b) {
            return $b['total'] - $a['total'];
        });

        return [
            'users' => $result,
            'summary' => [
                'total_users' => count($result),
                'total_visits' => $totalVisits,
                'total_meetings' => $totalMeetings,
            ],
        ];
    }

    /**
     * Get date range for visits/meetings filter
     */
    private function getCallStatistics(?array $dateRange = null): array
    {
        try {
            // Determine date range for call statistics
            $dateRangeStr = 'today';
            if ($dateRange) {
                $start = Carbon::parse($dateRange['start_date'] ?? $dateRange['start'] ?? now()->startOfDay());
                $end = Carbon::parse($dateRange['end_date'] ?? $dateRange['end'] ?? now()->endOfDay());
                $now = Carbon::now();
                
                if ($start->isToday() && $end->isToday()) {
                    $dateRangeStr = 'today';
                } elseif ($start->isCurrentWeek() && $end->isCurrentWeek()) {
                    $dateRangeStr = 'this_week';
                } elseif ($start->isCurrentMonth() && $end->isCurrentMonth()) {
                    $dateRangeStr = 'this_month';
                }
            }
            
            $stats = $this->callLogService->getSystemCallStatistics($dateRangeStr);
            
            // Get recent calls (last 10)
            $recentCalls = \App\Models\CallLog::with(['lead:id,name,phone', 'user:id,name', 'telecaller:id,name'])
                ->orderBy('start_time', 'desc')
                ->limit(10)
                ->get()
                ->map(function($call) {
                    return [
                        'id' => $call->id,
                        'phone_number' => $call->phone_number,
                        'lead_name' => $call->lead->name ?? 'N/A',
                        'user_name' => $call->callerUser->name ?? 'N/A',
                        'duration' => $call->formatted_duration,
                        'call_type' => $call->call_type_label,
                        'status' => $call->status_label,
                        'outcome' => $call->call_outcome_label,
                        'start_time' => $call->start_time ? $call->start_time->format('Y-m-d H:i:s') : null,
                    ];
                });
            
            $stats['recent_calls'] = $recentCalls;
            
            return $stats;
        } catch (\Exception $e) {
            Log::error('Failed to get call statistics: ' . $e->getMessage());
            return [
                'total_calls' => 0,
                'completed_calls' => 0,
                'total_duration' => 0,
                'formatted_duration' => '0s',
                'average_duration' => 0,
                'formatted_average_duration' => '0s',
                'connection_rate' => 0,
                'calls_by_role' => [],
                'top_users' => [],
                'outcome_distribution' => [],
                'recent_calls' => [],
            ];
        }
    }

    private function getMarketingSummary(?array $dateRange = null): array
    {
        $leadsQuery = Lead::query();
        $importsQuery = ImportBatch::query();

        if ($dateRange) {
            $leadsQuery->whereBetween('created_at', [$dateRange['start_date'], $dateRange['end_date']]);
            $importsQuery->whereBetween('created_at', [$dateRange['start_date'], $dateRange['end_date']]);
        }

        $sourceDistribution = (clone $leadsQuery)
            ->select('source', DB::raw('count(*) as total'))
            ->groupBy('source')
            ->orderByDesc('total')
            ->get()
            ->map(fn (Lead $lead) => [
                'source' => Lead::displaySourceLabel($lead->source),
                'value' => (int) ($lead->total ?? 0),
            ])
            ->values()
            ->all();

        $leadQuality = [
            'junk' => (clone $leadsQuery)->where('status', 'junk')->count(),
            'not_interested' => (clone $leadsQuery)->where('status', 'not_interested')->count(),
            'fresh_transfer' => (clone $leadsQuery)->where('status', 'fresh_transfer')->count(),
            'connected' => (clone $leadsQuery)->where('status', 'connected')->count(),
            'verified_prospect' => (clone $leadsQuery)->where('status', 'verified_prospect')->count(),
        ];

        $importSummary = [
            'total_batches' => (clone $importsQuery)->count(),
            'completed_batches' => (clone $importsQuery)->where('status', 'completed')->count(),
            'pending_batches' => (clone $importsQuery)->whereIn('status', ['pending', 'processing'])->count(),
            'failed_batches' => (clone $importsQuery)->where('status', 'failed')->count(),
            'imported_leads' => (int) ((clone $importsQuery)->sum('imported_leads') ?? 0),
        ];

        $leadInflow = [];
        $inflowStart = $dateRange['start_date'] ?? now()->copy()->subDays(6)->startOfDay();
        $inflowEnd = $dateRange['end_date'] ?? now()->endOfDay();
        $periodDays = max(1, $inflowStart->copy()->startOfDay()->diffInDays($inflowEnd->copy()->endOfDay()) + 1);
        $bucketCount = min($periodDays, 7);

        for ($i = $bucketCount - 1; $i >= 0; $i--) {
            $day = $inflowEnd->copy()->subDays($i);
            $leadInflow[] = [
                'label' => $day->format('d M'),
                'value' => Lead::query()
                    ->whereDate('created_at', $day->toDateString())
                    ->when($dateRange, function ($query) use ($dateRange) {
                        $query->whereBetween('created_at', [$dateRange['start_date'], $dateRange['end_date']]);
                    })
                    ->count(),
            ];
        }

        return [
            'source_distribution' => $sourceDistribution,
            'lead_quality' => $leadQuality,
            'import_summary' => $importSummary,
            'lead_inflow' => $leadInflow,
        ];
    }

    private function getAdSpendSummary(?array $dateRange = null): array
    {
        $platforms = [
            'meta' => [
                'label' => 'Meta',
                'keywords' => ['meta', 'facebook', 'instagram'],
                'lead_sources' => ['meta', 'meta_awareness', 'facebook_lead_ads', 'instagram'],
            ],
            'google' => [
                'label' => 'Google',
                'keywords' => ['google', 'youtube'],
                'lead_sources' => ['google'],
            ],
            'portal' => [
                'label' => 'Portal',
                'keywords' => ['portal', '99acres', 'housing', 'magicbricks'],
                'lead_sources' => ['99acres', 'housing'],
            ],
            'other' => [
                'label' => 'Other',
                'keywords' => [],
                'lead_sources' => ['other'],
            ],
        ];
        $adKeywords = [
            'marketing',
            'ad',
            'ads',
            'campaign',
            'boost',
            'lead',
            'meta',
            'facebook',
            'instagram',
            'google',
            'youtube',
            'portal',
            '99acres',
            'housing',
            'magicbricks',
        ];

        $entries = ExpenseEntry::query()
            ->with(['category:id,name', 'subcategory:id,name', 'creator:id,name'])
            ->when($dateRange, function ($query) use ($dateRange) {
                $query->whereBetween('expense_date', [
                    Carbon::parse($dateRange['start_date'])->toDateString(),
                    Carbon::parse($dateRange['end_date'])->toDateString(),
                ]);
            })
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->get()
            ->filter(function (ExpenseEntry $entry) use ($adKeywords) {
                $text = strtolower(trim(($entry->category?->name ?? '') . ' ' . ($entry->subcategory?->name ?? '')));

                foreach ($adKeywords as $keyword) {
                    if ($keyword !== '' && str_contains($text, $keyword)) {
                        return true;
                    }
                }

                return false;
            })
            ->map(function (ExpenseEntry $entry) use ($platforms) {
                $text = strtolower(trim(($entry->category?->name ?? '') . ' ' . ($entry->subcategory?->name ?? '')));
                $platformKey = 'other';

                foreach (['meta', 'google', 'portal'] as $key) {
                    foreach ($platforms[$key]['keywords'] as $keyword) {
                        if (str_contains($text, $keyword)) {
                            $platformKey = $key;
                            break 2;
                        }
                    }
                }

                return [
                    'id' => $entry->id,
                    'date' => optional($entry->expense_date)->format('Y-m-d'),
                    'platform' => $platformKey,
                    'platform_label' => $platforms[$platformKey]['label'],
                    'category_name' => $entry->category?->name ?: 'Uncategorized',
                    'subcategory_name' => $entry->subcategory?->name ?: '-',
                    'amount' => (float) $entry->amount,
                    'status' => $entry->status ?: ExpenseEntry::STATUS_DRAFT,
                    'status_label' => ucfirst(str_replace('_', ' ', $entry->status ?: ExpenseEntry::STATUS_DRAFT)),
                    'creator_name' => $entry->creator?->name ?: 'Finance user',
                    'view_url' => route('admin.expenses.entries.edit', $entry),
                ];
            })
            ->values();

        $leadCounts = [];
        foreach ($platforms as $key => $platform) {
            $leadCounts[$key] = Lead::query()
                ->when($dateRange, function ($query) use ($dateRange) {
                    $query->whereBetween('created_at', [$dateRange['start_date'], $dateRange['end_date']]);
                })
                ->whereIn('source', $platform['lead_sources'])
                ->count();
        }

        $platformRows = collect($platforms)->map(function (array $platform, string $key) use ($entries, $leadCounts) {
            $platformEntries = $entries->where('platform', $key);
            $amount = (float) $platformEntries->sum('amount');
            $leads = (int) ($leadCounts[$key] ?? 0);

            return [
                'key' => $key,
                'label' => $platform['label'],
                'amount' => $amount,
                'entry_count' => $platformEntries->count(),
                'lead_count' => $leads,
                'cost_per_lead' => $leads > 0 ? round($amount / $leads, 2) : null,
            ];
        })->values();

        return [
            'total_amount' => (float) $entries->sum('amount'),
            'approved_amount' => (float) $entries->where('status', ExpenseEntry::STATUS_APPROVED)->sum('amount'),
            'pending_amount' => (float) $entries->where('status', ExpenseEntry::STATUS_DRAFT)->sum('amount'),
            'rejected_amount' => (float) $entries->where('status', ExpenseEntry::STATUS_REJECTED)->sum('amount'),
            'platforms' => $platformRows->all(),
            'latest_entries' => $entries->take(8)->values()->all(),
        ];
    }

    private function getSourcePerformanceAnalytics(?array $dateRange = null): array
    {
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
        $hasFacebookTables = Schema::hasTable('fb_leads') && Schema::hasTable('fb_forms')
            && Schema::hasColumn('fb_leads', 'crm_lead_id')
            && Schema::hasColumn('fb_leads', 'fb_form_id')
            && Schema::hasColumn('fb_forms', 'form_name');
        $hasMetaAdInsights = Schema::hasTable('meta_ad_insights_daily')
            && Schema::hasColumn('meta_ad_insights_daily', 'ad_id')
            && Schema::hasColumn('meta_ad_insights_daily', 'spend')
            && Schema::hasColumn('meta_ad_insights_daily', 'date')
            && Schema::hasColumn('fb_leads', 'ad_id');
        $hasProspectScores = Schema::hasTable('prospects')
            && Schema::hasColumn('prospects', 'lead_id')
            && Schema::hasColumn('prospects', 'lead_score');
        $hasProspectStatuses = Schema::hasTable('prospects')
            && Schema::hasColumn('prospects', 'lead_id')
            && Schema::hasColumn('prospects', 'lead_status');
        $hasLeadFormFieldValues = Schema::hasTable('lead_form_field_values')
            && Schema::hasColumn('lead_form_field_values', 'lead_id')
            && Schema::hasColumn('lead_form_field_values', 'field_key')
            && Schema::hasColumn('lead_form_field_values', 'field_value');
        $hasCrmAssignments = Schema::hasTable('crm_assignments')
            && Schema::hasColumn('crm_assignments', 'lead_id')
            && Schema::hasColumn('crm_assignments', 'call_status')
            && Schema::hasColumn('crm_assignments', 'cnp_count');

        if (!$hasFacebookTables) {
            return [
                'rows' => [],
                'totals' => [
                    'sources' => 0,
                    'leads' => 0,
                    'scored_leads' => 0,
                    'score_coverage_rate' => 0,
                    'qualified' => 0,
                    'visits' => 0,
                    'closers' => 0,
                    'hot' => 0,
                    'warm' => 0,
                    'cold' => 0,
                    'average_quality' => null,
                    'quality_label' => 'Needs More Data',
                    'spend' => 0,
                    'cpl' => null,
                ],
                'campaign_rows' => [],
            ];
        }

        $metaSourceKeys = ['meta', 'meta_awareness'];
        $primaryFbLeadSub = DB::table('fb_leads')
            ->selectRaw('MIN(id) as id, crm_lead_id')
            ->whereNotNull('crm_lead_id')
            ->groupBy('crm_lead_id');

        $formSelectSql = "COALESCE(fb_forms.id, 0) as form_id,
            COALESCE(NULLIF(fb_forms.form_name, ''), CONCAT('Meta Form ', fb_forms.form_id), 'Unmapped Meta') as form_name";
        if ($hasProspectScores && $hasLeadFormFieldValues) {
            $scoreValueSql = "COALESCE(prospects.lead_score, CAST(NULLIF(lead_quality_values.field_value, '') AS UNSIGNED))";
        } elseif ($hasProspectScores) {
            $scoreValueSql = "prospects.lead_score";
        } elseif ($hasLeadFormFieldValues) {
            $scoreValueSql = "CAST(NULLIF(lead_quality_values.field_value, '') AS UNSIGNED)";
        } else {
            $scoreValueSql = "NULL";
        }

        if ($hasProspectStatuses && $hasLeadFormFieldValues) {
            $statusValueSql = "LOWER(TRIM(COALESCE(prospects.lead_status, lead_status_values.field_value, '')))";
        } elseif ($hasProspectStatuses) {
            $statusValueSql = "LOWER(TRIM(COALESCE(prospects.lead_status, '')))";
        } elseif ($hasLeadFormFieldValues) {
            $statusValueSql = "LOWER(TRIM(COALESCE(lead_status_values.field_value, '')))";
        } else {
            $statusValueSql = "''";
        }

        $holdConditions = ["leads.status = 'on_hold'"];
        if (Schema::hasColumn('leads', 'cnp_count')) {
            $holdConditions[] = 'COALESCE(leads.cnp_count, 0) > 0';
        }
        if (Schema::hasColumn('leads', 'cnp_quarantined_at') && Schema::hasColumn('leads', 'cnp_quarantine_cleared_at')) {
            $holdConditions[] = '(leads.cnp_quarantined_at IS NOT NULL AND leads.cnp_quarantine_cleared_at IS NULL)';
        }
        if ($hasCrmAssignments) {
            $holdConditions[] = "crm_assignments.call_status = 'pending'";
            $holdConditions[] = 'COALESCE(crm_assignments.cnp_count, 0) > 0';
        }
        $holdConditionSql = '(' . implode(' OR ', $holdConditions) . ')';

        $scoreSelectSql = ($hasProspectScores || $hasLeadFormFieldValues)
            ? "
                COUNT(DISTINCT CASE WHEN $scoreValueSql BETWEEN 1 AND 5 THEN leads.id ELSE NULL END) as scored_leads,
                AVG(CASE WHEN $scoreValueSql BETWEEN 1 AND 5 THEN $scoreValueSql ELSE NULL END) as average_quality,
                COUNT(DISTINCT CASE WHEN $scoreValueSql = 5 THEN leads.id ELSE NULL END) as quality_5_leads,
                COUNT(DISTINCT CASE WHEN $scoreValueSql = 4 THEN leads.id ELSE NULL END) as quality_4_leads,
                COUNT(DISTINCT CASE WHEN $scoreValueSql = 3 THEN leads.id ELSE NULL END) as quality_3_leads,
                COUNT(DISTINCT CASE WHEN $scoreValueSql = 2 THEN leads.id ELSE NULL END) as quality_2_leads,
                COUNT(DISTINCT CASE WHEN $scoreValueSql = 1 THEN leads.id ELSE NULL END) as quality_1_leads,
                COUNT(DISTINCT CASE WHEN $scoreValueSql BETWEEN 4 AND 5 THEN leads.id ELSE NULL END) as hot_leads,
                COUNT(DISTINCT CASE WHEN $scoreValueSql = 3 THEN leads.id ELSE NULL END) as warm_leads,
                COUNT(DISTINCT CASE WHEN $scoreValueSql = 2 THEN leads.id ELSE NULL END) as cold_leads,
                COUNT(DISTINCT CASE WHEN ($scoreValueSql = 1 OR leads.status IN ('junk', 'not_interested') OR $statusValueSql IN ('junk', 'not_interested')) THEN leads.id ELSE NULL END) as bad_leads,
                COUNT(DISTINCT CASE WHEN $holdConditionSql THEN leads.id ELSE NULL END) as hold_leads
            "
            : "
                0 as scored_leads,
                NULL as average_quality,
                0 as quality_5_leads,
                0 as quality_4_leads,
                0 as quality_3_leads,
                0 as quality_2_leads,
                0 as quality_1_leads,
                0 as hot_leads,
                0 as warm_leads,
                0 as cold_leads,
                0 as bad_leads,
                COUNT(DISTINCT CASE WHEN $holdConditionSql THEN leads.id ELSE NULL END) as hold_leads
            ";

        $leadSourceRows = Lead::query()
            ->selectRaw("
                leads.source,
                $formSelectSql,
                COUNT(DISTINCT leads.id) as total_leads,
                COUNT(DISTINCT CASE WHEN leads.status IN ('" . implode("','", $qualifiedStatuses) . "') THEN leads.id ELSE NULL END) as qualified_leads,
                COUNT(DISTINCT CASE WHEN leads.status IN ('" . implode("','", $qualifiedStatuses) . "') THEN leads.id ELSE NULL END) as interested_leads,
                COUNT(DISTINCT CASE WHEN leads.status = 'closed' THEN leads.id ELSE NULL END) as closed_leads,
                COUNT(DISTINCT CASE WHEN leads.status = 'junk' THEN leads.id ELSE NULL END) as junk_leads,
                COUNT(DISTINCT CASE WHEN leads.status = 'not_interested' THEN leads.id ELSE NULL END) as not_interested_leads,
                COUNT(DISTINCT CASE WHEN leads.status = 'follow_up' OR leads.next_followup_at IS NOT NULL THEN leads.id ELSE NULL END) as follow_up_leads,
                $scoreSelectSql
            ")
            ->leftJoinSub($primaryFbLeadSub, 'primary_fb_leads', function ($join) {
                $join->on('primary_fb_leads.crm_lead_id', '=', 'leads.id');
            })
            ->leftJoin('fb_leads', 'fb_leads.id', '=', 'primary_fb_leads.id')
            ->leftJoin('fb_forms', 'fb_forms.id', '=', 'fb_leads.fb_form_id')
            ->when($hasProspectScores || $hasProspectStatuses, function ($query) {
                $query->leftJoin('prospects', 'prospects.lead_id', '=', 'leads.id');
            })
            ->when($hasLeadFormFieldValues, function ($query) {
                $query->leftJoin('lead_form_field_values as lead_quality_values', function ($join) {
                    $join->on('lead_quality_values.lead_id', '=', 'leads.id')
                        ->where('lead_quality_values.field_key', '=', 'lead_quality');
                })->leftJoin('lead_form_field_values as lead_status_values', function ($join) {
                    $join->on('lead_status_values.lead_id', '=', 'leads.id')
                        ->where('lead_status_values.field_key', '=', 'lead_status');
                });
            })
            ->when($hasCrmAssignments, function ($query) {
                $query->leftJoin('crm_assignments', 'crm_assignments.lead_id', '=', 'leads.id');
            })
            ->when($dateRange, function ($query) use ($dateRange) {
                $query->whereBetween('leads.created_at', [$dateRange['start_date'], $dateRange['end_date']]);
            })
            ->whereIn('leads.source', $metaSourceKeys)
            ->groupBy('leads.source', 'fb_forms.id', 'fb_forms.form_name', 'fb_forms.form_id')
            ->orderByDesc('total_leads')
            ->get();

        $groupedMetricQuery = function ($modelQuery, string $dateColumn, ?callable $extra = null) use ($dateRange, $primaryFbLeadSub, $metaSourceKeys) {
            $isSiteVisitMetric = str_contains($dateColumn, 'site_visits');
            $leadIdColumn = $isSiteVisitMetric ? 'site_visits.lead_id' : 'meetings.lead_id';
            $idColumn = $isSiteVisitMetric ? 'site_visits.id' : 'meetings.id';
            $query = $modelQuery
                ->join('leads', 'leads.id', '=', $leadIdColumn)
                ->leftJoinSub($primaryFbLeadSub, 'primary_fb_leads_metric', function ($join) {
                    $join->on('primary_fb_leads_metric.crm_lead_id', '=', 'leads.id');
                })
                ->leftJoin('fb_leads', 'fb_leads.id', '=', 'primary_fb_leads_metric.id')
                ->leftJoin('fb_forms', 'fb_forms.id', '=', 'fb_leads.fb_form_id')
                ->whereIn('leads.source', $metaSourceKeys);

            if ($extra) {
                $extra($query);
            }

            return $query
                ->selectRaw('leads.source as source, COALESCE(fb_forms.id, 0) as form_id, COUNT(DISTINCT ' . $idColumn . ') as total')
                ->when($dateRange, function ($query) use ($dateRange, $dateColumn) {
                    $query->whereBetween($dateColumn, [$dateRange['start_date'], $dateRange['end_date']]);
                })
                ->groupBy('leads.source', 'fb_forms.id')
                ->get()
                ->mapWithKeys(fn ($row) => [$this->sourceFormMetricKey((string) $row->source, (int) $row->form_id) => (int) $row->total]);
        };

        $visitsByGroup = $groupedMetricQuery(SiteVisit::query(), 'site_visits.created_at');

        $closersByGroup = $groupedMetricQuery(
            SiteVisit::query(),
            'site_visits.closer_verified_at',
            fn ($query) => $query->where('site_visits.closer_status', 'verified')
        );

        $meetingsByGroup = $groupedMetricQuery(
            Meeting::query(),
            'meetings.created_at',
            fn ($query) => $query->where('meetings.is_converted', false)
        );

        $spendByForm = $hasMetaAdInsights ? $this->metaSpendByForm($dateRange) : collect();
        $financeMetaSpend = $this->metaFinanceAdSpend($dateRange);
        $campaignRows = $hasMetaAdInsights
            ? $this->metaCampaignPerformanceRows($dateRange, $scoreValueSql, $statusValueSql, $holdConditionSql, $hasProspectScores, $hasProspectStatuses, $hasLeadFormFieldValues, $hasCrmAssignments)
            : collect();

        $rows = $leadSourceRows->map(function ($row) use ($visitsByGroup, $closersByGroup, $meetingsByGroup, $spendByForm) {
            $sourceKey = (string) $row->source;
            $formId = (int) ($row->form_id ?? 0);
            $metricKey = $this->sourceFormMetricKey($sourceKey, $formId);
            $leads = (int) ($row->total_leads ?? 0);
            $spend = round((float) ($spendByForm[$formId] ?? 0), 2);
            $qualified = (int) ($row->qualified_leads ?? 0);
            $scoredLeads = (int) ($row->scored_leads ?? 0);
            $meetings = (int) ($meetingsByGroup[$metricKey] ?? 0);
            $visits = (int) ($visitsByGroup[$metricKey] ?? 0);
            $closers = (int) ($closersByGroup[$metricKey] ?? 0);
            $averageQuality = $scoredLeads > 0 ? round((float) ($row->average_quality ?? 0), 1) : null;
            $badLeads = (int) ($row->bad_leads ?? 0);

            return [
                'source' => Lead::displaySourceLabel($sourceKey),
                'source_key' => $sourceKey,
                'form_id' => $formId,
                'form_name' => $formId > 0 ? (string) $row->form_name : Lead::displaySourceLabel($sourceKey),
                'leads' => $leads,
                'spend' => $spend,
                'spend_source' => 'meta_insights',
                'cpl' => $leads > 0 && $spend > 0 ? round($spend / $leads, 2) : null,
                'scored_leads' => $scoredLeads,
                'score_coverage_rate' => $leads > 0 ? round(($scoredLeads / $leads) * 100, 1) : 0,
                'qualified' => $qualified,
                'interested' => (int) ($row->interested_leads ?? 0),
                'follow_up' => (int) ($row->follow_up_leads ?? 0),
                'meetings' => $meetings,
                'visits' => $visits,
                'closers' => $closers,
                'junk' => (int) ($row->junk_leads ?? 0),
                'not_interested' => (int) ($row->not_interested_leads ?? 0),
                'bad' => $badLeads,
                'hold' => (int) ($row->hold_leads ?? 0),
                'quality_5' => (int) ($row->quality_5_leads ?? 0),
                'quality_4' => (int) ($row->quality_4_leads ?? 0),
                'quality_3' => (int) ($row->quality_3_leads ?? 0),
                'quality_2' => (int) ($row->quality_2_leads ?? 0),
                'quality_1' => (int) ($row->quality_1_leads ?? 0),
                'hot' => (int) ($row->hot_leads ?? 0),
                'warm' => (int) ($row->warm_leads ?? 0),
                'cold' => (int) ($row->cold_leads ?? 0),
                'average_quality' => $averageQuality,
                'quality_label' => $this->leadQualityLabel($averageQuality, $scoredLeads),
                'good_lead_rate' => $scoredLeads > 0 ? round(((int) ($row->hot_leads ?? 0) / $scoredLeads) * 100, 1) : 0,
                'bad_lead_rate' => $leads > 0 ? round(($badLeads / $leads) * 100, 1) : 0,
                'qualified_rate' => $leads > 0 ? round(($qualified / $leads) * 100, 1) : 0,
                'closure_rate' => $leads > 0 ? round(($closers / $leads) * 100, 1) : 0,
            ];
        })->sortByDesc(function ($row) {
            $dataConfidence = $row['scored_leads'] >= 5 ? 100000 : 0;

            return $dataConfidence + ((float) ($row['average_quality'] ?? 0) * 1000) + (int) $row['leads'];
        })->values();

        $totalLeads = (int) $rows->sum('leads');
        $metaInsightsSpend = round((float) $rows->sum('spend'), 2);
        $spendSource = 'meta_insights';

        if ($metaInsightsSpend <= 0 && $financeMetaSpend > 0 && $totalLeads > 0) {
            $spendSource = 'finance_expense_fallback';
            $rows = $rows->map(function (array $row) use ($financeMetaSpend, $totalLeads) {
                $leads = (int) ($row['leads'] ?? 0);
                $spend = $leads > 0 ? round($financeMetaSpend * ($leads / $totalLeads), 2) : 0;
                $row['spend'] = $spend;
                $row['spend_source'] = 'finance_expense_fallback';
                $row['cpl'] = $leads > 0 && $spend > 0 ? round($spend / $leads, 2) : null;

                return $row;
            })->values();
        }

        $totalSpend = round((float) $rows->sum('spend'), 2);
        $totalScoredLeads = (int) $rows->sum('scored_leads');
        $weightedQuality = $totalScoredLeads > 0
            ? round($rows->sum(fn ($row) => ((float) ($row['average_quality'] ?? 0)) * ((int) $row['scored_leads'])) / $totalScoredLeads, 1)
            : null;

        return [
            'rows' => $rows->all(),
            'totals' => [
                'sources' => $rows->count(),
                'leads' => $totalLeads,
                'spend' => $totalSpend,
                'meta_insights_spend' => $metaInsightsSpend,
                'finance_meta_spend' => $financeMetaSpend,
                'spend_source' => $spendSource,
                'spend_source_label' => $spendSource === 'finance_expense_fallback'
                    ? 'Finance Meta Ads expense'
                    : 'Meta API synced spend',
                'cpl' => $totalLeads > 0 && $totalSpend > 0 ? round($totalSpend / $totalLeads, 2) : null,
                'scored_leads' => $totalScoredLeads,
                'score_coverage_rate' => $totalLeads > 0 ? round(($totalScoredLeads / $totalLeads) * 100, 1) : 0,
                'qualified' => (int) $rows->sum('qualified'),
                'interested' => (int) $rows->sum('interested'),
                'follow_up' => (int) $rows->sum('follow_up'),
                'visits' => (int) $rows->sum('visits'),
                'closers' => (int) $rows->sum('closers'),
                'hot' => (int) $rows->sum('hot'),
                'warm' => (int) $rows->sum('warm'),
                'cold' => (int) $rows->sum('cold'),
                'bad' => (int) $rows->sum('bad'),
                'hold' => (int) $rows->sum('hold'),
                'quality_5' => (int) $rows->sum('quality_5'),
                'quality_4' => (int) $rows->sum('quality_4'),
                'quality_3' => (int) $rows->sum('quality_3'),
                'quality_2' => (int) $rows->sum('quality_2'),
                'quality_1' => (int) $rows->sum('quality_1'),
                'average_quality' => $weightedQuality,
                'quality_label' => $this->leadQualityLabel($weightedQuality, $totalScoredLeads),
            ],
            'campaign_rows' => $campaignRows->all(),
        ];
    }

    private function metaSpendByForm(?array $dateRange = null): \Illuminate\Support\Collection
    {
        $pairs = DB::table('fb_leads')
            ->join('leads', 'leads.id', '=', 'fb_leads.crm_lead_id')
            ->join('fb_forms', 'fb_forms.id', '=', 'fb_leads.fb_form_id')
            ->join('meta_ad_insights_daily', 'meta_ad_insights_daily.ad_id', '=', 'fb_leads.ad_id')
            ->selectRaw('DISTINCT fb_forms.id as form_id, meta_ad_insights_daily.date, meta_ad_insights_daily.ad_id, meta_ad_insights_daily.spend')
            ->when($dateRange, function ($query) use ($dateRange) {
                $query->whereBetween('leads.created_at', [$dateRange['start_date'], $dateRange['end_date']])
                    ->whereBetween('meta_ad_insights_daily.date', [
                        $dateRange['start_date']->toDateString(),
                        $dateRange['end_date']->toDateString(),
                    ]);
            });

        return DB::query()
            ->fromSub($pairs, 'form_ad_spend')
            ->selectRaw('form_id, SUM(spend) as spend')
            ->groupBy('form_id')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->form_id => (float) $row->spend]);
    }

    private function metaFinanceAdSpend(?array $dateRange = null): float
    {
        if (!Schema::hasTable('expense_entries')) {
            return 0.0;
        }

        $entries = ExpenseEntry::query()
            ->with(['category:id,name', 'subcategory:id,name'])
            ->when($dateRange, function ($query) use ($dateRange) {
                $query->whereBetween('expense_date', [
                    Carbon::parse($dateRange['start_date'])->toDateString(),
                    Carbon::parse($dateRange['end_date'])->toDateString(),
                ]);
            })
            ->when(Schema::hasColumn('expense_entries', 'deleted_at'), function ($query) {
                $query->whereNull('deleted_at');
            })
            ->get();

        return round((float) $entries->filter(function (ExpenseEntry $entry) {
            $text = strtolower(trim(implode(' ', [
                $entry->category?->name ?? '',
                $entry->subcategory?->name ?? '',
                $entry->paid_to ?? '',
                $entry->remarks ?? '',
            ])));

            return str_contains($text, 'meta')
                || str_contains($text, 'facebook')
                || str_contains($text, 'instagram');
        })->sum('amount'), 2);
    }

    private function metaCampaignPerformanceRows(
        ?array $dateRange,
        string $scoreValueSql,
        string $statusValueSql,
        string $holdConditionSql,
        bool $hasProspectScores,
        bool $hasProspectStatuses,
        bool $hasLeadFormFieldValues,
        bool $hasCrmAssignments
    ): \Illuminate\Support\Collection {
        $scoreSelectSql = ($hasProspectScores || $hasLeadFormFieldValues)
            ? "
                COUNT(DISTINCT CASE WHEN $scoreValueSql BETWEEN 1 AND 5 THEN leads.id ELSE NULL END) as scored_leads,
                AVG(CASE WHEN $scoreValueSql BETWEEN 1 AND 5 THEN $scoreValueSql ELSE NULL END) as average_quality,
                COUNT(DISTINCT CASE WHEN $scoreValueSql = 5 THEN leads.id ELSE NULL END) as quality_5_leads,
                COUNT(DISTINCT CASE WHEN $scoreValueSql = 4 THEN leads.id ELSE NULL END) as quality_4_leads,
                COUNT(DISTINCT CASE WHEN $scoreValueSql = 3 THEN leads.id ELSE NULL END) as quality_3_leads,
                COUNT(DISTINCT CASE WHEN $scoreValueSql = 2 THEN leads.id ELSE NULL END) as quality_2_leads,
                COUNT(DISTINCT CASE WHEN $scoreValueSql = 1 THEN leads.id ELSE NULL END) as quality_1_leads,
                COUNT(DISTINCT CASE WHEN ($scoreValueSql = 1 OR leads.status IN ('junk', 'not_interested') OR $statusValueSql IN ('junk', 'not_interested')) THEN leads.id ELSE NULL END) as bad_leads,
                COUNT(DISTINCT CASE WHEN $holdConditionSql THEN leads.id ELSE NULL END) as hold_leads
            "
            : "
                0 as scored_leads,
                NULL as average_quality,
                0 as quality_5_leads,
                0 as quality_4_leads,
                0 as quality_3_leads,
                0 as quality_2_leads,
                0 as quality_1_leads,
                0 as bad_leads,
                COUNT(DISTINCT CASE WHEN $holdConditionSql THEN leads.id ELSE NULL END) as hold_leads
            ";

        $leadRows = Lead::query()
            ->selectRaw("
                COALESCE(NULLIF(fb_leads.campaign_id, ''), 'unknown') as campaign_id,
                COALESCE(NULLIF(fb_leads.campaign_name, ''), 'Unknown Campaign') as campaign_name,
                COALESCE(NULLIF(fb_leads.adset_id, ''), 'unknown') as adset_id,
                COALESCE(NULLIF(fb_leads.adset_name, ''), 'Unknown Adset') as adset_name,
                COALESCE(NULLIF(fb_leads.ad_id, ''), 'unknown') as ad_id,
                COALESCE(NULLIF(fb_leads.ad_name, ''), 'Unknown Ad') as ad_name,
                COUNT(DISTINCT leads.id) as leads,
                COUNT(DISTINCT CASE WHEN leads.status = 'closed' THEN leads.id ELSE NULL END) as closers,
                $scoreSelectSql
            ")
            ->join('fb_leads', 'fb_leads.crm_lead_id', '=', 'leads.id')
            ->whereNotNull('fb_leads.ad_id')
            ->when($hasProspectScores || $hasProspectStatuses, function ($query) {
                $query->leftJoin('prospects', 'prospects.lead_id', '=', 'leads.id');
            })
            ->when($hasLeadFormFieldValues, function ($query) {
                $query->leftJoin('lead_form_field_values as lead_quality_values', function ($join) {
                    $join->on('lead_quality_values.lead_id', '=', 'leads.id')
                        ->where('lead_quality_values.field_key', '=', 'lead_quality');
                })->leftJoin('lead_form_field_values as lead_status_values', function ($join) {
                    $join->on('lead_status_values.lead_id', '=', 'leads.id')
                        ->where('lead_status_values.field_key', '=', 'lead_status');
                });
            })
            ->when($hasCrmAssignments, function ($query) {
                $query->leftJoin('crm_assignments', 'crm_assignments.lead_id', '=', 'leads.id');
            })
            ->when($dateRange, function ($query) use ($dateRange) {
                $query->whereBetween('leads.created_at', [$dateRange['start_date'], $dateRange['end_date']]);
            })
            ->groupBy('fb_leads.campaign_id', 'fb_leads.campaign_name', 'fb_leads.adset_id', 'fb_leads.adset_name', 'fb_leads.ad_id', 'fb_leads.ad_name')
            ->orderByDesc('leads')
            ->limit(25)
            ->get();

        $spendByAd = MetaAdInsightDaily::query()
            ->selectRaw('ad_id, SUM(spend) as spend')
            ->when($dateRange, function ($query) use ($dateRange) {
                $query->whereBetween('date', [$dateRange['start_date']->toDateString(), $dateRange['end_date']->toDateString()]);
            })
            ->groupBy('ad_id')
            ->get()
            ->mapWithKeys(fn ($row) => [(string) $row->ad_id => (float) $row->spend]);

        return $leadRows->map(function ($row) use ($spendByAd) {
            $leads = (int) ($row->leads ?? 0);
            $spend = round((float) ($spendByAd[(string) $row->ad_id] ?? 0), 2);
            $scoredLeads = (int) ($row->scored_leads ?? 0);
            $averageQuality = $scoredLeads > 0 ? round((float) ($row->average_quality ?? 0), 1) : null;

            return [
                'campaign_id' => (string) $row->campaign_id,
                'campaign_name' => (string) $row->campaign_name,
                'adset_id' => (string) $row->adset_id,
                'adset_name' => (string) $row->adset_name,
                'ad_id' => (string) $row->ad_id,
                'ad_name' => (string) $row->ad_name,
                'leads' => $leads,
                'spend' => $spend,
                'cpl' => $leads > 0 && $spend > 0 ? round($spend / $leads, 2) : null,
                'scored_leads' => $scoredLeads,
                'average_quality' => $averageQuality,
                'quality_label' => $this->leadQualityLabel($averageQuality, $scoredLeads),
                'quality_5' => (int) ($row->quality_5_leads ?? 0),
                'quality_4' => (int) ($row->quality_4_leads ?? 0),
                'quality_3' => (int) ($row->quality_3_leads ?? 0),
                'quality_2' => (int) ($row->quality_2_leads ?? 0),
                'quality_1' => (int) ($row->quality_1_leads ?? 0),
                'bad' => (int) ($row->bad_leads ?? 0),
                'hold' => (int) ($row->hold_leads ?? 0),
                'closers' => (int) ($row->closers ?? 0),
            ];
        })->values();
    }

    private function sourceFormMetricKey(string $source, int $formId): string
    {
        return $source . '|' . $formId;
    }

    private function leadQualityLabel(?float $score, int $scoredLeads = 0): string
    {
        if ($scoredLeads < 5) {
            return 'Needs More Data';
        }
        if ($score === null) {
            return 'Needs More Data';
        }
        if ($score >= 4.5) {
            return 'Excellent';
        }
        if ($score >= 3.5) {
            return 'Good';
        }
        if ($score >= 2.5) {
            return 'Average';
        }
        if ($score >= 1.5) {
            return 'Weak';
        }

        return 'Poor';
    }

    private function getPerformanceScores(?array $dateRange = null, ?array $rangeCounts = null): array
    {
        $leads = $rangeCounts['leads'] ?? $this->countLeadsForRange($dateRange);
        $meetings = $rangeCounts['completed_meetings'] ?? $this->countPipelineCompletedMeetings(collect(), $dateRange);
        $visits = $rangeCounts['completed_visits'] ?? $this->countPipelineCompletedVisits(collect(), $dateRange);
        $closers = $rangeCounts['closers'] ?? $this->countClosersForRange($dateRange);

        return [
            'leads' => $leads,
            'meetings' => $meetings,
            'visits' => $visits,
            'closers' => $closers,
            'ps' => $leads > 0 ? round((($meetings + $visits) / $leads) * 100, 1) : 0,
            'pp' => $leads > 0 ? round(($closers / $leads) * 100, 1) : 0,
            'vp' => $visits > 0 ? round(($closers / $visits) * 100, 1) : 0,
        ];
    }

    private function getWeeklyActivitySummary(array $dateRange, string $preset = 'this_week'): array
    {
        $rangeStart = $dateRange['start_date']->copy()->startOfDay();
        $rangeEnd = $dateRange['end_date']->copy()->endOfDay();

        return [
            'range_label' => $rangeStart->format('d M') . ' - ' . $rangeEnd->format('d M'),
            'start_date' => $rangeStart->toDateString(),
            'end_date' => $rangeEnd->toDateString(),
            'filter_label' => $this->getWeeklyActivityFilterLabel($preset),
            'applied_filter' => $preset,
            'custom_start_date' => $preset === 'custom' ? $rangeStart->toDateString() : null,
            'custom_end_date' => $preset === 'custom' ? $rangeEnd->toDateString() : null,
            'meetings' => $this->buildWeeklyWorkflowSummary(Meeting::query(), $rangeStart, $rangeEnd),
            'visits' => $this->buildWeeklyWorkflowSummary(SiteVisit::query(), $rangeStart, $rangeEnd),
        ];
    }

    private function getWeeklyActivityFilterLabel(string $preset): string
    {
        return match ($preset) {
            'global' => 'Dashboard Range',
            'today' => 'Today',
            'previous_week' => 'Previous Week',
            'week' => 'Week',
            'next_week' => 'Next Week',
            'month' => 'Month',
            'this_month' => 'This Month',
            'year' => 'Year',
            'custom' => 'Custom Range',
            default => 'This Week',
        };
    }

    private function buildWeeklyWorkflowSummary($query, Carbon $weekStart, Carbon $weekEnd): array
    {
        $row = $query
            ->whereBetween('scheduled_at', [$weekStart, $weekEnd])
            ->selectRaw('COUNT(*) as scheduled')
            ->selectRaw("SUM(CASE WHEN status = 'completed' OR completed_at IS NOT NULL THEN 1 ELSE 0 END) as completed")
            ->first();

        $scheduled = (int) ($row->scheduled ?? 0);
        $completed = (int) ($row->completed ?? 0);

        $pending = (int) max($scheduled - $completed, 0);
        $completionRate = $scheduled > 0 ? round(($completed / $scheduled) * 100) : 0;

        return [
            'scheduled' => $scheduled,
            'completed' => $completed,
            'pending' => $pending,
            'completion_rate' => $completionRate,
        ];
    }

    private function getSalesScoreTable(?array $dateRange = null): array
    {
        $users = User::with('role')
            ->where('is_active', true)
            ->whereHas('role', function ($query) {
                $query->whereIn('slug', ['sales_manager', 'sales_executive', 'assistant_sales_manager', 'senior_manager']);
            })
            ->get();

        $userIds = $users->pluck('id');
        $range = fn ($query, string $column) => $dateRange
            ? $query->whereBetween($column, [$dateRange['start_date'], $dateRange['end_date']])
            : $query;
        $leadsMap = $range(LeadAssignment::query()->whereIn('assigned_to', $userIds)->where('is_active', true), 'assigned_at')
            ->selectRaw('assigned_to, COUNT(*) as aggregate')->groupBy('assigned_to')->pluck('aggregate', 'assigned_to');
        $meetingsMap = $range(Meeting::query()->whereIn('assigned_to', $userIds)->where('is_converted', false)->whereNotNull('lead_id'), 'created_at')
            ->selectRaw('assigned_to, COUNT(DISTINCT lead_id) as aggregate')->groupBy('assigned_to')->pluck('aggregate', 'assigned_to');
        $visitsMap = $range(SiteVisit::query()->whereIn('assigned_to', $userIds)->whereNotNull('lead_id'), 'created_at')
            ->selectRaw('assigned_to, COUNT(DISTINCT lead_id) as aggregate')->groupBy('assigned_to')->pluck('aggregate', 'assigned_to');
        $closersMap = $range(SiteVisit::query()->whereIn('assigned_to', $userIds)->whereIn('closer_status', ['approved', 'verified'])->whereNotNull('lead_id'), 'closer_verified_at')
            ->selectRaw('assigned_to, COUNT(DISTINCT lead_id) as aggregate')->groupBy('assigned_to')->pluck('aggregate', 'assigned_to');
        $statusMaps = $range(
            LeadAssignment::query()
                ->join('leads', 'leads.id', '=', 'lead_assignments.lead_id')
                ->whereIn('lead_assignments.assigned_to', $userIds)
                ->where('lead_assignments.is_active', true)
                ->whereIn('leads.status', ['junk', 'not_interested']),
            'lead_assignments.assigned_at'
        )
            ->selectRaw('lead_assignments.assigned_to, leads.status, COUNT(DISTINCT leads.id) as aggregate')
            ->groupBy('lead_assignments.assigned_to', 'leads.status')
            ->get()
            ->groupBy('status')
            ->map(fn ($rows) => $rows->pluck('aggregate', 'assigned_to'));

        $rows = $users->map(function (User $user) use ($leadsMap, $meetingsMap, $visitsMap, $closersMap, $statusMaps) {
            $leads = (int) ($leadsMap[$user->id] ?? 0);
            $meetings = (int) ($meetingsMap[$user->id] ?? 0);
            $visits = (int) ($visitsMap[$user->id] ?? 0);
            $closers = (int) ($closersMap[$user->id] ?? 0);
            $junk = (int) ($statusMaps->get('junk', collect())[$user->id] ?? 0);
            $notInterested = (int) ($statusMaps->get('not_interested', collect())[$user->id] ?? 0);

            return [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'role' => $user->role->name ?? 'Unknown',
                'role_slug' => $user->role->slug ?? 'unknown',
                'leads' => $leads,
                'meet_visit' => $meetings + $visits,
                'meetings' => $meetings,
                'visits' => $visits,
                'closers' => $closers,
                'junk' => $junk,
                'not_interested' => $notInterested,
                'other' => 0,
                'ps' => $leads > 0 ? round((($meetings + $visits) / $leads) * 100, 1) : 0,
                'pp' => $leads > 0 ? round(($closers / $leads) * 100, 1) : 0,
                'vp' => $visits > 0 ? round(($closers / $visits) * 100, 1) : 0,
            ];
        })->sortByDesc(function (array $row) {
            return [$row['ps'], $row['closers'], $row['visits']];
        })->values();

        return $rows->all();
    }

    private function getSalesUserActivityTable(?array $dateRange = null, ?array $pendingResponseRows = null): array
    {
        $salesRoleSlugs = [
            'sales_head',
            'sales_manager',
            'senior_manager',
            'assistant_sales_manager',
            'sales_executive',
        ];

        $users = User::with('role')
            ->where('is_active', true)
            ->whereHas('role', function ($query) use ($salesRoleSlugs) {
                $query->whereIn('slug', $salesRoleSlugs);
            })
            ->orderBy('name')
            ->get();

        $userIds = $users->pluck('id');

        if ($userIds->isEmpty()) {
            return [
                'rows' => [],
                'totals' => [
                    'new_leads' => 0,
                    'fresh_transfer_leads' => 0,
                    'overdue' => 0,
                    'scheduled_visits' => 0,
                    'completed_visits' => 0,
                    'verified_visits' => 0,
                ],
            ];
        }

        $startDate = $dateRange['start_date'] ?? Carbon::now()->startOfMonth();
        $endDate = $dateRange['end_date'] ?? Carbon::now()->endOfMonth();

        $pendingResponseByUser = collect($pendingResponseRows ?? $this->getLeadsPendingResponseByUser($dateRange))
            ->keyBy(fn (array $row) => (int) ($row['user_id'] ?? 0));

        $freshTransferMap = LeadAssignment::query()
            ->join('leads', 'leads.id', '=', 'lead_assignments.lead_id')
            ->whereIn('lead_assignments.assigned_to', $userIds)
            ->where('lead_assignments.is_active', true)
            ->where('leads.status', Lead::STATUS_FRESH_TRANSFER)
            ->whereBetween('lead_assignments.assigned_at', [$startDate, $endDate])
            ->selectRaw('lead_assignments.assigned_to, COUNT(DISTINCT lead_assignments.lead_id) as aggregate')
            ->groupBy('lead_assignments.assigned_to')
            ->pluck('aggregate', 'assigned_to');

        $overdueCutoff = Carbon::now()->subMinutes(Task::OVERDUE_GRACE_MINUTES);
        $overdueMap = Task::query()
            ->join('lead_assignments as active_task_assignments', function ($join) {
                $join->on('active_task_assignments.lead_id', '=', 'tasks.lead_id')
                    ->on('active_task_assignments.assigned_to', '=', 'tasks.assigned_to')
                    ->where('active_task_assignments.is_active', true);
            })
            ->whereIn('tasks.assigned_to', $userIds)
            ->where('tasks.type', 'phone_call')
            ->whereIn('tasks.status', ['pending', 'in_progress'])
            ->where('tasks.scheduled_at', '<', $overdueCutoff)
            ->whereBetween('tasks.scheduled_at', [$startDate, $endDate])
            ->selectRaw('tasks.assigned_to, COUNT(DISTINCT tasks.id) as aggregate')
            ->groupBy('tasks.assigned_to')
            ->pluck('aggregate', 'assigned_to');

        $scheduledVisitsMap = SiteVisit::query()
            ->whereIn('assigned_to', $userIds)
            ->where('status', 'scheduled')
            ->whereBetween('scheduled_at', [$startDate, $endDate])
            ->selectRaw('assigned_to, COUNT(*) as aggregate')
            ->groupBy('assigned_to')
            ->pluck('aggregate', 'assigned_to');

        $completedVisitsMap = $this->siteVisitDateRangeQuery(
            SiteVisit::query()
                ->whereIn('assigned_to', $userIds)
                ->where('status', 'completed'),
            $startDate,
            $endDate
        )
            ->selectRaw('assigned_to, COUNT(*) as aggregate')
            ->groupBy('assigned_to')
            ->pluck('aggregate', 'assigned_to');

        $verifiedVisitsMap = $this->siteVisitDateRangeQuery(
            SiteVisit::query()
                ->whereIn('assigned_to', $userIds)
                ->whereIn('verification_status', ['verified', 'approved']),
            $startDate,
            $endDate
        )
            ->selectRaw('assigned_to, COUNT(*) as aggregate')
            ->groupBy('assigned_to')
            ->pluck('aggregate', 'assigned_to');

        $rows = $users->map(function (User $user) use (
            $pendingResponseByUser,
            $freshTransferMap,
            $overdueMap,
            $scheduledVisitsMap,
            $completedVisitsMap,
            $verifiedVisitsMap
        ) {
            $pendingResponse = $pendingResponseByUser->get((int) $user->id, []);

            return [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'role' => $user->role->name ?? 'Unknown',
                'new_leads' => (int) ($pendingResponse['pending_count'] ?? 0),
                'fresh_transfer_leads' => (int) ($freshTransferMap[$user->id] ?? 0),
                'overdue' => (int) ($overdueMap[$user->id] ?? 0),
                'scheduled_visits' => (int) ($scheduledVisitsMap[$user->id] ?? 0),
                'completed_visits' => (int) ($completedVisitsMap[$user->id] ?? 0),
                'verified_visits' => (int) ($verifiedVisitsMap[$user->id] ?? 0),
            ];
        })->values();

        return [
            'rows' => $rows->all(),
            'totals' => [
                'new_leads' => (int) $rows->sum('new_leads'),
                'fresh_transfer_leads' => (int) $rows->sum('fresh_transfer_leads'),
                'overdue' => (int) $rows->sum('overdue'),
                'scheduled_visits' => (int) $rows->sum('scheduled_visits'),
                'completed_visits' => (int) $rows->sum('completed_visits'),
                'verified_visits' => (int) $rows->sum('verified_visits'),
            ],
        ];
    }

    private function siteVisitDateRangeQuery($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->where(function ($dateQuery) use ($startDate, $endDate) {
            $dateQuery->whereBetween('completed_at', [$startDate, $endDate])
                ->orWhere(function ($fallbackQuery) use ($startDate, $endDate) {
                    $fallbackQuery->whereNull('completed_at')
                        ->whereBetween('scheduled_at', [$startDate, $endDate]);
                });
        });
    }

    private function mergeSalesScoreOperationalMetrics(array $rows, array $averageResponseTimeByUser, array $leadsPendingResponse, ?array $dateRange = null): array
    {
        $responseByUser = collect($averageResponseTimeByUser)->keyBy(fn ($row) => (int) ($row['user_id'] ?? 0));
        $pendingByUser = collect($leadsPendingResponse)->keyBy(fn ($row) => (int) ($row['user_id'] ?? 0));
        $otherBreakdowns = $this->getSalesScoreOtherBreakdowns($rows, $leadsPendingResponse, $dateRange);

        return collect($rows)->map(function (array $row) use ($responseByUser, $pendingByUser, $otherBreakdowns) {
            $userId = (int) ($row['user_id'] ?? 0);
            $pending = $pendingByUser->get($userId, []);
            $pendingLeads = collect($pending['leads'] ?? []);
            $oldestAssignedAt = $pendingLeads
                ->pluck('assigned_at')
                ->filter()
                ->sort()
                ->first();
            $response = $responseByUser->get($userId, []);

            $row['no_response_count'] = (int) ($pending['pending_count'] ?? 0);
            $row['oldest_assigned_at'] = $oldestAssignedAt ?: null;
            $row['avg_response_minutes'] = (float) ($response['avg_response_minutes'] ?? 0);
            $row['other'] = max(0, (int) ($row['leads'] ?? 0)
                - (int) ($row['no_response_count'] ?? 0)
                - (int) ($row['meetings'] ?? 0)
                - (int) ($row['visits'] ?? 0)
                - (int) ($row['junk'] ?? 0)
                - (int) ($row['not_interested'] ?? 0));
            $row['other_breakdown'] = $otherBreakdowns[$userId] ?? [
                'task_activity' => 0,
                'call_response' => 0,
                'follow_up' => 0,
                'progressed' => 0,
                'uncategorized' => $row['other'],
            ];

            return $row;
        })->all();
    }

    private function getSalesScoreOtherBreakdowns(array $rows, array $leadsPendingResponse, ?array $dateRange = null): array
    {
        $userIds = collect($rows)
            ->pluck('user_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($userIds->isEmpty()) {
            return [];
        }

        $pendingLeadIdsByUser = collect($leadsPendingResponse)
            ->mapWithKeys(fn (array $row) => [
                (int) ($row['user_id'] ?? 0) => collect($row['leads'] ?? [])
                    ->pluck('lead_id')
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->all(),
            ]);

        $assignedQuery = LeadAssignment::query()
            ->join('leads', 'leads.id', '=', 'lead_assignments.lead_id')
            ->whereIn('lead_assignments.assigned_to', $userIds->all())
            ->where('lead_assignments.is_active', true)
            ->select([
                'lead_assignments.lead_id',
                'lead_assignments.assigned_to',
                'lead_assignments.assigned_at',
                'leads.status',
                'leads.next_followup_at',
            ]);

        if ($dateRange) {
            $assignedQuery->whereBetween('lead_assignments.assigned_at', [$dateRange['start_date'], $dateRange['end_date']]);
        }

        $assignedRows = $assignedQuery->get();
        $leadIds = $assignedRows->pluck('lead_id')->map(fn ($id) => (int) $id)->unique()->values();

        if ($leadIds->isEmpty()) {
            return [];
        }

        $visitLeadIdsByUser = SiteVisit::query()
            ->whereIn('assigned_to', $userIds->all())
            ->whereIn('lead_id', $leadIds->all())
            ->whereNotNull('lead_id')
            ->get(['assigned_to', 'lead_id'])
            ->groupBy('assigned_to')
            ->map(fn ($items) => $items->pluck('lead_id')->map(fn ($id) => (int) $id)->unique()->all());

        $meetingLeadIdsByUser = Meeting::query()
            ->whereIn('assigned_to', $userIds->all())
            ->whereIn('lead_id', $leadIds->all())
            ->whereNotNull('lead_id')
            ->where('is_converted', false)
            ->get(['assigned_to', 'lead_id'])
            ->groupBy('assigned_to')
            ->map(fn ($items) => $items->pluck('lead_id')->map(fn ($id) => (int) $id)->unique()->all());

        $taskLeadIdsByUser = Task::query()
            ->whereIn('lead_id', $leadIds->all())
            ->where(function ($query) use ($userIds) {
                $query->whereIn('assigned_to', $userIds->all())
                    ->orWhereIn('created_by', $userIds->all());
            })
            ->where(function ($query) {
                $query->whereIn('status', ['in_progress', 'completed'])
                    ->orWhereNotNull('completed_at')
                    ->orWhereNotNull('outcome')
                    ->orWhereNotNull('outcome_recorded_at');
            })
            ->whereNull('deleted_at')
            ->get(['lead_id', 'assigned_to', 'created_by'])
            ->flatMap(function ($task) {
                return collect([$task->assigned_to, $task->created_by])
                    ->filter()
                    ->map(fn ($userId) => ['user_id' => (int) $userId, 'lead_id' => (int) $task->lead_id]);
            })
            ->groupBy('user_id')
            ->map(fn ($items) => $items->pluck('lead_id')->unique()->all());

        $callLeadIdsByUser = collect();
        DB::table('crm_assignments')
            ->whereIn('assigned_to', $userIds->all())
            ->whereIn('lead_id', $leadIds->all())
            ->where(fn ($query) => $query->where('cnp_count', '>', 0)->orWhere('call_status', '!=', 'pending'))
            ->get(['assigned_to', 'lead_id'])
            ->each(function ($row) use (&$callLeadIdsByUser) {
                $callLeadIdsByUser->push(['user_id' => (int) $row->assigned_to, 'lead_id' => (int) $row->lead_id]);
            });
        DB::table('telecaller_tasks')
            ->whereIn('lead_id', $leadIds->all())
            ->where(function ($query) use ($userIds) {
                $query->whereIn('assigned_to', $userIds->all())
                    ->orWhereIn('created_by', $userIds->all());
            })
            ->where(function ($query) {
                $query->whereIn('status', ['in_progress', 'completed'])
                    ->orWhereNotNull('completed_at')
                    ->orWhereNotNull('outcome');
            })
            ->whereNull('deleted_at')
            ->get(['lead_id', 'assigned_to', 'created_by'])
            ->each(function ($row) use (&$callLeadIdsByUser) {
                foreach (array_filter([(int) $row->assigned_to, (int) $row->created_by]) as $userId) {
                    $callLeadIdsByUser->push(['user_id' => $userId, 'lead_id' => (int) $row->lead_id]);
                }
            });
        $callLeadIdsByUser = $callLeadIdsByUser
            ->groupBy('user_id')
            ->map(fn ($items) => $items->pluck('lead_id')->unique()->all());

        $followUpLeadIdsByUser = DB::table('follow_ups')
            ->whereIn('lead_id', $leadIds->all())
            ->whereIn('created_by', $userIds->all())
            ->whereNull('deleted_at')
            ->get(['created_by', 'lead_id'])
            ->groupBy('created_by')
            ->map(fn ($items) => $items->pluck('lead_id')->map(fn ($id) => (int) $id)->unique()->all());

        $result = [];
        foreach ($assignedRows->groupBy('assigned_to') as $userId => $assignments) {
            $pendingIds = $pendingLeadIdsByUser->get((int) $userId, []);
            $meetingIds = $meetingLeadIdsByUser->get((int) $userId, []);
            $visitIds = $visitLeadIdsByUser->get((int) $userId, []);
            $taskIds = $taskLeadIdsByUser->get((int) $userId, []);
            $callIds = $callLeadIdsByUser->get((int) $userId, []);
            $followUpIds = $followUpLeadIdsByUser->get((int) $userId, []);
            $breakdown = [
                'task_activity' => 0,
                'call_response' => 0,
                'follow_up' => 0,
                'progressed' => 0,
                'uncategorized' => 0,
            ];

            foreach ($assignments as $assignment) {
                $leadId = (int) $assignment->lead_id;
                if (
                    in_array($leadId, $pendingIds, true)
                    || in_array($leadId, $meetingIds, true)
                    || in_array($leadId, $visitIds, true)
                    || in_array((string) $assignment->status, ['junk', 'not_interested'], true)
                ) {
                    continue;
                }

                if (in_array($leadId, $taskIds, true)) {
                    $breakdown['task_activity']++;
                } elseif (in_array($leadId, $callIds, true)) {
                    $breakdown['call_response']++;
                } elseif (in_array($leadId, $followUpIds, true) || $assignment->next_followup_at) {
                    $breakdown['follow_up']++;
                } elseif (!in_array((string) $assignment->status, ['new', Lead::STATUS_FRESH_TRANSFER], true)) {
                    $breakdown['progressed']++;
                } else {
                    $breakdown['uncategorized']++;
                }
            }

            $result[(int) $userId] = $breakdown;
        }

        return $result;
    }

    private function getPipelineFunnel(?array $dateRange = null): array
    {
        $prospectFlags = DB::table('prospects')
            ->whereNotNull('lead_id')
            ->selectRaw('lead_id, 1 as has_prospect')
            ->groupBy('lead_id');
        $meetingFlags = DB::table('meetings')
            ->whereNull('deleted_at')
            ->whereNull('queue_hidden_at')
            ->selectRaw("lead_id, MAX(CASE WHEN status = 'completed' OR completed_at IS NOT NULL THEN 1 ELSE 0 END) as meeting_done")
            ->groupBy('lead_id');
        $visitFlags = DB::table('site_visits')
            ->whereNull('deleted_at')
            ->whereNull('queue_hidden_at')
            ->selectRaw("lead_id,
                MAX(CASE WHEN status = 'completed' OR completed_at IS NOT NULL THEN 1 ELSE 0 END) as visit_done,
                MAX(CASE WHEN closer_status IN ('approved', 'verified') THEN 1 ELSE 0 END) as is_closer")
            ->groupBy('lead_id');
        $taskFlags = DB::table('tasks')
            ->whereNull('deleted_at')
            ->whereNull('queue_hidden_at')
            ->where('type', 'phone_call')
            ->selectRaw("lead_id,
                MAX(CASE WHEN outcome = 'cnp' THEN 1 ELSE 0 END) as has_cnp,
                MAX(CASE WHEN outcome = 'follow_up' THEN 1 ELSE 0 END) as has_follow_up")
            ->groupBy('lead_id');

        $pipelineFlags = Lead::query()
            ->visibleInAllLeadsInventory()
            ->where(fn ($query) => $query->where('is_hiring_candidate', false)->orWhereNull('is_hiring_candidate'))
            ->when($dateRange, fn ($query) => $query->whereBetween('leads.created_at', [$dateRange['start_date'], $dateRange['end_date']]))
            ->leftJoinSub($prospectFlags, 'pipeline_prospects', 'pipeline_prospects.lead_id', '=', 'leads.id')
            ->leftJoinSub($meetingFlags, 'pipeline_meetings', 'pipeline_meetings.lead_id', '=', 'leads.id')
            ->leftJoinSub($visitFlags, 'pipeline_visits', 'pipeline_visits.lead_id', '=', 'leads.id')
            ->leftJoinSub($taskFlags, 'pipeline_tasks', 'pipeline_tasks.lead_id', '=', 'leads.id')
            ->selectRaw("leads.status, leads.is_dead,
                CASE
                    WHEN leads.status IN ('junk', 'not_interested', 'dead') OR COALESCE(leads.is_dead, 0) = 1 THEN 'loss'
                    WHEN leads.status = 'closed' OR COALESCE(pipeline_visits.is_closer, 0) = 1 THEN 'closure'
                    WHEN COALESCE(pipeline_visits.visit_done, 0) = 1 THEN 'visit_done'
                    WHEN COALESCE(pipeline_meetings.meeting_done, 0) = 1 THEN 'meeting_done'
                    WHEN COALESCE(pipeline_prospects.has_prospect, 0) = 1 THEN 'prospect'
                    WHEN COALESCE(leads.cnp_count, 0) > 0 OR COALESCE(pipeline_tasks.has_cnp, 0) = 1 THEN 'cnp'
                    WHEN leads.next_followup_at IS NOT NULL OR COALESCE(pipeline_tasks.has_follow_up, 0) = 1 THEN 'follow_up'
                    ELSE 'open'
                END as pipeline_stage");

        $stats = DB::query()
            ->fromSub($pipelineFlags, 'pipeline_rows')
            ->selectRaw("COUNT(*) as total_leads,
                SUM(CASE WHEN pipeline_stage = 'open' THEN 1 ELSE 0 END) as open_leads,
                SUM(CASE WHEN pipeline_stage = 'cnp' THEN 1 ELSE 0 END) as cnp_leads,
                SUM(CASE WHEN pipeline_stage = 'follow_up' THEN 1 ELSE 0 END) as follow_up_leads,
                SUM(CASE WHEN pipeline_stage = 'prospect' THEN 1 ELSE 0 END) as prospect_leads,
                SUM(CASE WHEN pipeline_stage = 'meeting_done' THEN 1 ELSE 0 END) as meeting_done_leads,
                SUM(CASE WHEN status = 'visit_scheduled' THEN 1 ELSE 0 END) as scheduled_visit_leads,
                SUM(CASE WHEN pipeline_stage = 'visit_done' THEN 1 ELSE 0 END) as visit_done_leads,
                SUM(CASE WHEN pipeline_stage = 'closure' THEN 1 ELSE 0 END) as closure_leads,
                SUM(CASE WHEN status = 'junk' THEN 1 ELSE 0 END) as junk_leads,
                SUM(CASE WHEN status = 'not_interested' THEN 1 ELSE 0 END) as not_interested_leads,
                SUM(CASE WHEN status = 'dead' OR COALESCE(is_dead, 0) = 1 THEN 1 ELSE 0 END) as dead_leads")
            ->first();

        $leads = (int) ($stats->total_leads ?? 0);
        $openLeads = (int) ($stats->open_leads ?? 0);
        $cnpLeads = (int) ($stats->cnp_leads ?? 0);
        $followUpLeads = (int) ($stats->follow_up_leads ?? 0);
        $prospects = (int) ($stats->prospect_leads ?? 0);
        $meetings = (int) ($stats->meeting_done_leads ?? 0);
        $scheduledVisits = (int) ($stats->scheduled_visit_leads ?? 0);
        $visits = (int) ($stats->visit_done_leads ?? 0);
        $closers = (int) ($stats->closure_leads ?? 0);
        $hrLeads = 0;
        $junk = (int) ($stats->junk_leads ?? 0);
        $notInterested = (int) ($stats->not_interested_leads ?? 0);
        $dead = (int) ($stats->dead_leads ?? 0);

        return [
            ['label' => 'Total Leads', 'value' => $leads, 'percentage' => 100],
            ['label' => 'HR Leads', 'value' => $hrLeads, 'percentage' => $leads > 0 ? round(($hrLeads / $leads) * 100, 1) : 0],
            ['label' => 'New Leads', 'value' => $openLeads, 'percentage' => $leads > 0 ? round(($openLeads / $leads) * 100, 1) : 0],
            ['label' => 'CNP', 'value' => $cnpLeads, 'percentage' => $leads > 0 ? round(($cnpLeads / $leads) * 100, 1) : 0],
            ['label' => 'Follow Up', 'value' => $followUpLeads, 'percentage' => $leads > 0 ? round(($followUpLeads / $leads) * 100, 1) : 0],
            ['label' => 'Active Prospects', 'value' => $prospects, 'percentage' => $leads > 0 ? round(($prospects / $leads) * 100, 1) : 0],
            ['label' => 'Meeting Done', 'value' => $meetings, 'percentage' => $leads > 0 ? round(($meetings / $leads) * 100, 1) : 0],
            ['label' => 'Site Visit Scheduled', 'value' => $scheduledVisits, 'percentage' => $leads > 0 ? round(($scheduledVisits / $leads) * 100, 1) : 0],
            ['label' => 'Site Visit Done', 'value' => $visits, 'percentage' => $leads > 0 ? round(($visits / $leads) * 100, 1) : 0],
            ['label' => 'Closures', 'value' => $closers, 'percentage' => $leads > 0 ? round(($closers / $leads) * 100, 1) : 0],
            ['label' => 'Junk', 'value' => $junk, 'percentage' => $leads > 0 ? round(($junk / $leads) * 100, 1) : 0],
            ['label' => 'Not Interested', 'value' => $notInterested, 'percentage' => $leads > 0 ? round(($notInterested / $leads) * 100, 1) : 0],
            ['label' => 'Dead', 'value' => $dead, 'percentage' => $leads > 0 ? round(($dead / $leads) * 100, 1) : 0],
        ];
    }

    private function pipelineLeadIdsForRange(?array $dateRange = null)
    {
        return Lead::query()
            ->visibleInAllLeadsInventory()
            ->where(function ($query) {
                $query->where('is_hiring_candidate', false)
                    ->orWhereNull('is_hiring_candidate');
            })
            ->when($dateRange, function ($query) use ($dateRange) {
                $query->whereBetween('created_at', [$dateRange['start_date'], $dateRange['end_date']]);
            })
            ->pluck('id');
    }

    private function pipelineProspectLeadIds($leadIds)
    {
        if ($leadIds->isEmpty()) {
            return collect();
        }

        return Prospect::query()
            ->whereIn('lead_id', $leadIds)
            ->whereNotNull('lead_id')
            ->pluck('lead_id')
            ->filter()
            ->unique()
            ->values();
    }

    private function pipelineHrLeadIds($leadIds)
    {
        if ($leadIds->isEmpty()) {
            return collect();
        }

        return Lead::query()
            ->whereIn('id', $leadIds)
            ->where('is_hiring_candidate', true)
            ->pluck('id')
            ->filter()
            ->unique()
            ->values();
    }

    private function pipelineCnpLeadIds($leadIds)
    {
        if ($leadIds->isEmpty()) {
            return collect();
        }

        $leadCnpIds = Lead::query()
            ->whereIn('id', $leadIds)
            ->where('cnp_count', '>', 0)
            ->pluck('id');

        $taskCnpIds = Task::query()
            ->whereIn('lead_id', $leadIds)
            ->where('type', 'phone_call')
            ->where('outcome', 'cnp')
            ->pluck('lead_id');

        return $leadCnpIds
            ->merge($taskCnpIds)
            ->filter()
            ->unique()
            ->values();
    }

    private function pipelineFollowUpLeadIds($leadIds)
    {
        if ($leadIds->isEmpty()) {
            return collect();
        }

        $leadFollowUpIds = Lead::query()
            ->whereIn('id', $leadIds)
            ->whereNotNull('next_followup_at')
            ->pluck('id');

        $taskFollowUpIds = Task::query()
            ->whereIn('lead_id', $leadIds)
            ->where('type', 'phone_call')
            ->where('outcome', 'follow_up')
            ->pluck('lead_id');

        return $leadFollowUpIds
            ->merge($taskFollowUpIds)
            ->filter()
            ->unique()
            ->values();
    }

    private function countPipelineProspects($leadIds): int
    {
        if ($leadIds->isEmpty()) {
            return 0;
        }

        return $this->pipelineProspectLeadIds($leadIds)->count();
    }

    private function countPipelineMeetings($leadIds, ?array $dateRange = null): int
    {
        if ($leadIds->isEmpty()) {
            return 0;
        }

        return (int) Meeting::query()
            ->where('is_converted', false)
            ->whereIn('lead_id', $leadIds)
            ->when($dateRange, function ($query) use ($dateRange) {
                $query->whereBetween('created_at', [$dateRange['start_date'], $dateRange['end_date']]);
            })
            ->distinct('lead_id')
            ->count('lead_id');
    }

    private function countPipelineCompletedMeetings($leadIds, ?array $dateRange = null): int
    {
        $query = Meeting::query()
            ->where(function ($statusQuery) {
                $statusQuery->where('status', 'completed')
                    ->orWhereNotNull('completed_at');
            });

        if ($leadIds->isNotEmpty()) {
            return $this->pipelineCompletedMeetingLeadIds($leadIds)->count();
        }

        return (int) $query
            ->when($dateRange, function ($dateQuery) use ($dateRange) {
                $dateQuery->whereBetween('scheduled_at', [$dateRange['start_date'], $dateRange['end_date']]);
            })
            ->count();
    }

    private function pipelineCompletedMeetingLeadIds($leadIds)
    {
        if ($leadIds->isEmpty()) {
            return collect();
        }

        return Meeting::query()
            ->where(function ($statusQuery) {
                $statusQuery->where('status', 'completed')
                    ->orWhereNotNull('completed_at');
            })
            ->whereIn('lead_id', $leadIds)
            ->pluck('lead_id')
            ->filter()
            ->unique()
            ->values();
    }

    private function countPipelineVisits($leadIds, ?array $dateRange = null): int
    {
        if ($leadIds->isEmpty()) {
            return 0;
        }

        return (int) SiteVisit::query()
            ->whereIn('lead_id', $leadIds)
            ->when($dateRange, function ($query) use ($dateRange) {
                $query->whereBetween('created_at', [$dateRange['start_date'], $dateRange['end_date']]);
            })
            ->distinct('lead_id')
            ->count('lead_id');
    }

    private function countPipelineCompletedVisits($leadIds, ?array $dateRange = null): int
    {
        $query = SiteVisit::query()
            ->where(function ($statusQuery) {
                $statusQuery->where('status', 'completed')
                    ->orWhereNotNull('completed_at');
            });

        if ($leadIds->isNotEmpty()) {
            return $this->pipelineCompletedVisitLeadIds($leadIds)->count();
        }

        return (int) $query
            ->when($dateRange, function ($dateQuery) use ($dateRange) {
                $dateQuery->whereBetween('scheduled_at', [$dateRange['start_date'], $dateRange['end_date']]);
            })
            ->count();
    }

    private function pipelineCompletedVisitLeadIds($leadIds)
    {
        if ($leadIds->isEmpty()) {
            return collect();
        }

        return SiteVisit::query()
            ->where(function ($statusQuery) {
                $statusQuery->where('status', 'completed')
                    ->orWhereNotNull('completed_at');
            })
            ->whereIn('lead_id', $leadIds)
            ->pluck('lead_id')
            ->filter()
            ->unique()
            ->values();
    }

    private function countPipelineClosers($leadIds, ?array $dateRange = null): int
    {
        if ($leadIds->isEmpty()) {
            return 0;
        }

        return $this->pipelineCloserLeadIds($leadIds)->count();
    }

    private function pipelineCloserLeadIds($leadIds)
    {
        if ($leadIds->isEmpty()) {
            return collect();
        }

        $closedLeadIds = Lead::query()
            ->whereIn('id', $leadIds)
            ->where('status', 'closed')
            ->pluck('id');

        $closerVisitLeadIds = SiteVisit::query()
            ->whereIn('closer_status', ['approved', 'verified'])
            ->whereIn('lead_id', $leadIds)
            ->pluck('lead_id');

        return $closedLeadIds
            ->merge($closerVisitLeadIds)
            ->filter()
            ->unique()
            ->values();
    }

    private function countPipelineLeadStatuses($leadIds, string $status): int
    {
        if ($leadIds->isEmpty()) {
            return 0;
        }

        return (int) Lead::query()
            ->whereIn('id', $leadIds)
            ->where('status', $status)
            ->count();
    }

    private function pipelineLossLeadIds($leadIds)
    {
        if ($leadIds->isEmpty()) {
            return collect();
        }

        return Lead::query()
            ->whereIn('id', $leadIds)
            ->where(function ($query) {
                $query->whereIn('status', ['junk', 'not_interested', 'dead'])
                    ->orWhere('is_dead', true);
            })
            ->pluck('id')
            ->filter()
            ->unique()
            ->values();
    }

    private function countPipelineDeadLeads($leadIds): int
    {
        if ($leadIds->isEmpty()) {
            return 0;
        }

        return (int) Lead::query()
            ->whereIn('id', $leadIds)
            ->where(function ($query) {
                $query->where('status', 'dead')
                    ->orWhere('is_dead', true);
            })
            ->count();
    }

    private function countProspectsForRange(?array $dateRange = null): int
    {
        $query = Prospect::query()->whereNotNull('lead_id');

        if ($dateRange) {
            $query->whereBetween('created_at', [$dateRange['start_date'], $dateRange['end_date']]);
        }

        return $this->countDistinctLeadIds($query, 'lead_id');
    }

    private function countLeadsByStatusForRange(string $status, ?array $dateRange = null, ?int $assignedTo = null): int
    {
        $query = Lead::where('status', $status);

        if ($assignedTo) {
            $query->whereHas('activeAssignments', function ($assignmentQuery) use ($assignedTo, $dateRange) {
                $assignmentQuery->where('assigned_to', $assignedTo)
                    ->where('is_active', true);

                if ($dateRange) {
                    $assignmentQuery->whereBetween('assigned_at', [$dateRange['start_date'], $dateRange['end_date']]);
                }
            });
        }

        if ($dateRange && !$assignedTo) {
            $query->whereBetween('created_at', [$dateRange['start_date'], $dateRange['end_date']]);
        }

        return (int) $query->count();
    }

    private function countOtherLeadsMarkedByUser(string $status, ?array $dateRange, int $userId): int
    {
        $query = Lead::query()
            ->where('status', $status)
            ->where('other_lead_marked_by', $userId);

        if ($dateRange) {
            $query->whereBetween('other_lead_marked_at', [$dateRange['start_date'], $dateRange['end_date']]);
        }

        return (int) $query->count();
    }

    private function getTeamTargetsSummary(?array $dateRange = null, ?array $rangeCounts = null): array
    {
        $month = now()->format('Y-m');
        $targetMonth = Carbon::parse($month . '-01')->startOfMonth();
        $targets = Target::where('target_month', $targetMonth)->get();

        $meetingsTarget = (int) $targets->sum('target_meetings');
        $visitsTarget = (int) $targets->sum('target_visits');
        $closersTarget = (int) $targets->sum('target_closers');

        $meetingsAchieved = $rangeCounts['meetings'] ?? $this->countMeetingsForRange($dateRange);
        $visitsAchieved = $rangeCounts['visits'] ?? $this->countVisitsForRange($dateRange);
        $closersAchieved = $rangeCounts['closers'] ?? $this->countClosersForRange($dateRange);

        return [
            'month' => $month,
            'metrics' => [
                'meetings' => [
                    'target' => $meetingsTarget,
                    'achieved' => $meetingsAchieved,
                    'percentage' => $meetingsTarget > 0 ? round(($meetingsAchieved / $meetingsTarget) * 100, 1) : 0,
                ],
                'visits' => [
                    'target' => $visitsTarget,
                    'achieved' => $visitsAchieved,
                    'percentage' => $visitsTarget > 0 ? round(($visitsAchieved / $visitsTarget) * 100, 1) : 0,
                ],
                'closers' => [
                    'target' => $closersTarget,
                    'achieved' => $closersAchieved,
                    'percentage' => $closersTarget > 0 ? round(($closersAchieved / $closersTarget) * 100, 1) : 0,
                ],
            ],
        ];
    }

    private function getTeamTargetsBreakdown(?array $targetOverview = null): array
    {
        if ($targetOverview !== null && isset($targetOverview['achievement_breakdown'])) {
            return collect($targetOverview['achievement_breakdown'])->values()->all();
        }

        $targetMonth = Carbon::now()->startOfMonth();
        $targetRows = Target::with('user.role')
            ->where('target_month', $targetMonth)
            ->get()
            ->keyBy('user_id');

        $salesRoleIds = Role::whereIn('slug', [
            Role::SALES_MANAGER,
            Role::SENIOR_MANAGER,
            Role::ASSISTANT_SALES_MANAGER,
            Role::SALES_EXECUTIVE,
        ])->pluck('id');

        return User::query()
            ->with('role')
            ->where('is_active', true)
            ->whereIn('role_id', $salesRoleIds)
            ->orderBy('name')
            ->get()
            ->map(function (User $user) use ($targetRows, $targetMonth) {
                $target = $targetRows->get($user->id);

                if (!$target) {
                    $target = new Target([
                        'user_id' => $user->id,
                        'target_month' => $targetMonth,
                        'target_meetings' => 0,
                        'target_visits' => 0,
                        'target_closers' => 0,
                    ]);
                    $target->setRelation('user', $user);
                }

                return [
                    'user_name' => $user->name ?? 'Unknown',
                    'role' => $user->role->name ?? 'Unknown',
                    'meetings' => $target->getAchievementProgress('meetings'),
                    'visits' => $target->getAchievementProgress('visits'),
                    'closers' => $target->getAchievementProgress('closers'),
                ];
            })
            ->values()
            ->all();
    }

    private function getIncentiveSummary(?array $dateRange = null): array
    {
        $query = Incentive::query();
        if ($dateRange) {
            $query->whereBetween('created_at', [$dateRange['start_date'], $dateRange['end_date']]);
        }

        $summary = $query
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified")
            ->selectRaw("SUM(CASE WHEN status IN ('pending_sales_head', 'pending_crm', 'pending_finance_manager', 'pending') THEN 1 ELSE 0 END) as pending")
            ->selectRaw("SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected")
            ->selectRaw('COALESCE(SUM(amount), 0) as total_amount')
            ->first();

        return [
            'total' => (int) ($summary->total ?? 0),
            'verified' => (int) ($summary->verified ?? 0),
            'pending' => (int) ($summary->pending ?? 0),
            'rejected' => (int) ($summary->rejected ?? 0),
            'total_amount' => round((float) ($summary->total_amount ?? 0), 2),
        ];
    }

    private function getUserPipelineTable(array $salesScoreTable, array $averageResponseTimeByUser): array
    {
        $responseLookup = collect($averageResponseTimeByUser)->keyBy('user_id');

        return collect($salesScoreTable)->map(function (array $row) use ($responseLookup) {
            $response = $responseLookup->get($row['user_id']);

            return [
                'user_id' => $row['user_id'],
                'user_name' => $row['user_name'],
                'role' => $row['role'],
                'leads' => $row['leads'],
                'meetings' => $row['meetings'],
                'visits' => $row['visits'],
                'closers' => $row['closers'],
                'avg_response_minutes' => $response['avg_response_minutes'] ?? 0,
            ];
        })->all();
    }

    private function getDashboardShortcuts(?array $dateRange = null, ?array $rangeCounts = null): array
    {
        return [
            ['label' => 'All Leads', 'count' => $rangeCounts['leads'] ?? $this->countLeadsForRange($dateRange), 'icon' => 'fa-users', 'url' => route('leads.index')],
            ['label' => 'Meetings', 'count' => $rangeCounts['meetings'] ?? $this->countMeetingsForRange($dateRange), 'icon' => 'fa-calendar-check', 'url' => route('meetings.index')],
            ['label' => 'Visits', 'count' => $rangeCounts['visits'] ?? $this->countVisitsForRange($dateRange), 'icon' => 'fa-map-marker-alt', 'url' => route('site-visits.index')],
            ['label' => 'Closers', 'count' => $rangeCounts['closers'] ?? $this->countClosersForRange($dateRange), 'icon' => 'fa-handshake', 'url' => route('site-visits.index')],
        ];
    }

    private function getDashboardRangeCounts(?array $dateRange = null): array
    {
        $meetingQuery = Meeting::query()->whereNotNull('lead_id');
        $visitQuery = SiteVisit::query()->whereNotNull('lead_id');

        if ($dateRange) {
            $meetingQuery
                ->selectRaw('COUNT(DISTINCT CASE WHEN is_converted = 0 AND created_at BETWEEN ? AND ? THEN lead_id END) as total', [$dateRange['start_date'], $dateRange['end_date']])
                ->selectRaw("COUNT(DISTINCT CASE WHEN (status = 'completed' OR completed_at IS NOT NULL) AND scheduled_at BETWEEN ? AND ? THEN lead_id END) as completed", [$dateRange['start_date'], $dateRange['end_date']]);
            $visitQuery
                ->selectRaw('COUNT(DISTINCT CASE WHEN created_at BETWEEN ? AND ? THEN lead_id END) as total', [$dateRange['start_date'], $dateRange['end_date']])
                ->selectRaw("COUNT(DISTINCT CASE WHEN (status = 'completed' OR completed_at IS NOT NULL) AND scheduled_at BETWEEN ? AND ? THEN lead_id END) as completed", [$dateRange['start_date'], $dateRange['end_date']])
                ->selectRaw("COUNT(DISTINCT CASE WHEN closer_status IN ('approved', 'verified') AND closer_verified_at BETWEEN ? AND ? THEN lead_id END) as closers", [$dateRange['start_date'], $dateRange['end_date']]);
        } else {
            $meetingQuery
                ->selectRaw('COUNT(DISTINCT CASE WHEN is_converted = 0 THEN lead_id END) as total')
                ->selectRaw("COUNT(DISTINCT CASE WHEN status = 'completed' OR completed_at IS NOT NULL THEN lead_id END) as completed");
            $visitQuery
                ->selectRaw('COUNT(DISTINCT lead_id) as total')
                ->selectRaw("COUNT(DISTINCT CASE WHEN status = 'completed' OR completed_at IS NOT NULL THEN lead_id END) as completed")
                ->selectRaw("COUNT(DISTINCT CASE WHEN closer_status IN ('approved', 'verified') THEN lead_id END) as closers");
        }

        $meetingCounts = $meetingQuery->first();
        $visitCounts = $visitQuery->first();

        return [
            'leads' => $this->countLeadsForRange($dateRange),
            'meetings' => (int) ($meetingCounts->total ?? 0),
            'visits' => (int) ($visitCounts->total ?? 0),
            'closers' => (int) ($visitCounts->closers ?? 0),
            'completed_meetings' => (int) ($meetingCounts->completed ?? 0),
            'completed_visits' => (int) ($visitCounts->completed ?? 0),
        ];
    }

    private function countLeadsForRange(?array $dateRange = null, ?int $assignedTo = null): int
    {
        if ($assignedTo) {
            $query = LeadAssignment::where('assigned_to', $assignedTo)->where('is_active', true);
            if ($dateRange) {
                $query->whereBetween('assigned_at', [$dateRange['start_date'], $dateRange['end_date']]);
            }
            return (int) $query->distinct('lead_id')->count('lead_id');
        }

        $query = Lead::query();
        if ($dateRange) {
            $query->whereBetween('created_at', [$dateRange['start_date'], $dateRange['end_date']]);
        }

        return (int) $query->count();
    }

    private function countMeetingsForRange(?array $dateRange = null, ?int $assignedTo = null): int
    {
        $query = Meeting::query()
            ->where('is_converted', false)
            ->whereNotNull('lead_id');
        if ($assignedTo) {
            $query->where('assigned_to', $assignedTo);
        }
        if ($dateRange) {
            $query->whereBetween('created_at', [$dateRange['start_date'], $dateRange['end_date']]);
        }
        return $this->countDistinctLeadIds($query, 'lead_id');
    }

    private function countVisitsForRange(?array $dateRange = null, ?int $assignedTo = null): int
    {
        $query = SiteVisit::query()->whereNotNull('lead_id');
        if ($assignedTo) {
            $query->where('assigned_to', $assignedTo);
        }
        if ($dateRange) {
            $query->whereBetween('created_at', [$dateRange['start_date'], $dateRange['end_date']]);
        }
        return $this->countDistinctLeadIds($query, 'lead_id');
    }

    private function countClosersForRange(?array $dateRange = null, ?int $assignedTo = null): int
    {
        $query = SiteVisit::query()
            ->whereIn('closer_status', ['approved', 'verified'])
            ->whereNotNull('lead_id');
        if ($assignedTo) {
            $query->where('assigned_to', $assignedTo);
        }
        if ($dateRange) {
            $query->whereBetween('closer_verified_at', [$dateRange['start_date'], $dateRange['end_date']]);
        }
        return $this->countDistinctLeadIds($query, 'lead_id');
    }

    private function countDistinctLeadIds($query, string $column = 'lead_id'): int
    {
        return (int) $query->distinct($column)->count($column);
    }

    private function getPresetDateRange(string $preset): array
    {
        return match ($preset) {
            'today' => [
                'start_date' => Carbon::today()->startOfDay(),
                'end_date' => Carbon::today()->endOfDay(),
            ],
            'previous_week' => [
                'start_date' => Carbon::now()->subWeek()->startOfWeek()->startOfDay(),
                'end_date' => Carbon::now()->subWeek()->endOfWeek()->endOfDay(),
            ],
            'this_week', 'week' => [
                'start_date' => Carbon::now()->startOfWeek()->startOfDay(),
                'end_date' => Carbon::now()->endOfWeek()->endOfDay(),
            ],
            'next_week' => [
                'start_date' => Carbon::now()->addWeek()->startOfWeek()->startOfDay(),
                'end_date' => Carbon::now()->addWeek()->endOfWeek()->endOfDay(),
            ],
            'this_month', 'month' => [
                'start_date' => Carbon::now()->startOfMonth()->startOfDay(),
                'end_date' => Carbon::now()->endOfMonth()->endOfDay(),
            ],
            'this_year', 'year' => [
                'start_date' => Carbon::now()->startOfYear()->startOfDay(),
                'end_date' => Carbon::now()->endOfYear()->endOfDay(),
            ],
            default => [
                'start_date' => Carbon::today()->startOfDay(),
                'end_date' => Carbon::today()->endOfDay(),
            ],
        };
    }

    private function getVisitsMeetingsDateRange(string $filter): ?array
    {
        switch ($filter) {
            case 'today':
                return [
                    'start_date' => Carbon::today()->startOfDay(),
                    'end_date' => Carbon::today()->endOfDay(),
                ];
            case 'tomorrow':
                return [
                    'start_date' => Carbon::tomorrow()->startOfDay(),
                    'end_date' => Carbon::tomorrow()->endOfDay(),
                ];
            case 'this_weekend':
                $now = Carbon::now();
                // Get this week's Saturday and Sunday
                if ($now->dayOfWeek === Carbon::SATURDAY) {
                    // Today is Saturday, use today and tomorrow
                    $saturday = $now->copy()->startOfDay();
                    $sunday = $now->copy()->addDay()->endOfDay();
                } elseif ($now->dayOfWeek === Carbon::SUNDAY) {
                    // Today is Sunday, use yesterday and today
                    $saturday = $now->copy()->subDay()->startOfDay();
                    $sunday = $now->copy()->endOfDay();
                } else {
                    // Get upcoming weekend
                    $saturday = $now->copy()->next(Carbon::SATURDAY)->startOfDay();
                    $sunday = $saturday->copy()->addDay()->endOfDay();
                }
                
                return [
                    'start_date' => $saturday,
                    'end_date' => $sunday,
                ];
            case 'this_month':
                return [
                    'start_date' => Carbon::now()->startOfMonth()->startOfDay(),
                    'end_date' => Carbon::now()->endOfMonth()->endOfDay(),
                ];
            default:
                return [
                    'start_date' => Carbon::now()->startOfMonth()->startOfDay(),
                    'end_date' => Carbon::now()->endOfMonth()->endOfDay(),
                ];
        }
    }
}

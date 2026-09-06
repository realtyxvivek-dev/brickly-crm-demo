<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\BroadcastMessage;
use App\Models\ExecutionTask;
use App\Models\LeaveRequest;
use App\Models\MarketingManagerNote;
use App\Models\Role;
use App\Models\User;
use App\Services\LeadQualityReportService;
use App\Services\SelfTodoService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MarketingController extends Controller
{
    public function __construct(protected SelfTodoService $selfTodoService)
    {
    }

    public function dashboard(Request $request, LeadQualityReportService $leadQualityReportService)
    {
        $user = $request->user();
        $user->loadMissing('role', 'manager.role');

        $isAdmin = method_exists($user, 'isAdmin') && $user->isAdmin();
        abort_unless($isAdmin || $user->isMarketingUser(), 403, 'Unauthorized access.');

        $openStatuses = config('execution-desk.open_statuses', ['open', 'in_progress', 'waiting', 'reopened']);

        $ownTasks = ExecutionTask::query()
            ->with(['creator:id,name', 'assignee:id,name'])
            ->where('assigned_to', $user->id);

        $ownTaskCounts = [
            'open' => (clone $ownTasks)->whereIn('status', $openStatuses)->count(),
            'in_progress' => (clone $ownTasks)->where('status', 'in_progress')->count(),
            'overdue' => (clone $ownTasks)
                ->whereIn('status', $openStatuses)
                ->whereNotNull('due_at')
                ->where('due_at', '<', now())
                ->count(),
        ];
        $recentOwnTasks = (clone $ownTasks)->latest()->limit(6)->get();

        $myTodos = $this->selfTodoService->listForUser($user, 'all');
        $selfTodoCounts = $this->selfTodoService->countsForUser($user);

        $activeBroadcasts = Schema::hasTable('broadcast_messages')
            ? BroadcastMessage::query()
                ->with('sender:id,name')
                ->active()
                ->latest()
                ->limit(12)
                ->get()
                ->filter(fn (BroadcastMessage $message) => $message->isTargetedTo($user))
                ->take(6)
                ->values()
            : collect();

        $managerNotes = $this->resolveManagerNotes($user);
        $recentAttendanceRecords = AttendanceRecord::query()
            ->where('user_id', $user->id)
            ->latest('attendance_date')
            ->latest('updated_at')
            ->limit(10)
            ->get([
                'attendance_date',
                'first_punch_in_at',
                'last_punch_out_at',
                'status',
                'worked_minutes',
                'has_missing_punch_out',
            ]);

        $teamMembers = collect();
        $teamTaskCards = collect();
        $teamAttendanceSummary = null;

        if ($isAdmin || $user->isMarketingManager()) {
            $teamMembersQuery = User::query()
                ->with('role:id,name,slug')
                ->where('is_active', true)
                ->whereHas('role', fn ($query) => $query->whereIn('slug', [Role::MARKETING_EXECUTIVE, Role::MARKETING_MANAGER]))
                ->orderBy('name');

            if (!$isAdmin) {
                $teamMembersQuery->where('manager_id', $user->id);
            }

            $teamMembers = $teamMembersQuery->get(['id', 'name', 'email', 'role_id']);

            $teamAttendanceSummary = $this->buildTeamAttendanceSummary($teamMembers);
            $teamTaskCards = $this->buildTeamTaskCards($teamMembers, $openStatuses);
        }

        $leadQualityRequest = Request::create($request->fullUrl(), 'GET', array_merge([
            'source' => 'all',
            'period' => 'month',
            'month' => now()->format('Y-m'),
            'sample_limit' => 25,
        ], $request->query()));
        $leadQualityRequest->setUserResolver(fn () => $user);

        return view('marketing.dashboard', [
            'isAdmin' => $isAdmin,
            'isManager' => $isAdmin || $user->isMarketingManager(),
            'displayRole' => $isAdmin ? 'Admin' : ($user->isMarketingManager() ? 'Marketing Manager' : 'Marketing Executive'),
            'leadQualityReport' => $leadQualityReportService->buildFromRequest($leadQualityRequest),
            'metaCplSummary' => $this->buildMetaCplSummary((string) $request->get('meta_filter', 'month')),
            'recentOwnTasks' => $recentOwnTasks,
            'ownTaskCounts' => $ownTaskCounts,
            'myTodos' => $myTodos,
            'selfTodoCounts' => $selfTodoCounts,
            'activeBroadcasts' => $activeBroadcasts,
            'managerNotes' => $managerNotes,
            'recentAttendanceRecords' => $recentAttendanceRecords,
            'teamMembers' => $teamMembers,
            'teamAttendanceSummary' => $teamAttendanceSummary,
            'teamTaskCards' => $teamTaskCards,
        ]);
    }

    private function buildMetaCplSummary(string $preset = 'month'): array
    {
        $range = $this->resolveMetaDateRange($preset);
        $empty = [
            'forms' => collect(),
            'campaigns' => collect(),
            'funnel' => collect(),
            'alerts' => collect(),
            'range' => $range + ['preset' => $preset],
            'totals' => [
                'leads' => 0,
                'spend' => 0,
                'cpl' => null,
                'qualified' => 0,
                'bad' => 0,
                'hold' => 0,
                'closers' => 0,
                'cost_per_closer' => null,
                'avg_quality' => null,
                'scored_leads' => 0,
            ],
        ];

        if (!Schema::hasTable('fb_leads') || !Schema::hasTable('fb_forms') || !Schema::hasTable('meta_ad_insights_daily')) {
            return $empty;
        }

        $from = $range['from'];
        $to = $range['to'];
        $hasLeadFormFieldValues = Schema::hasTable('lead_form_field_values')
            && Schema::hasColumn('lead_form_field_values', 'lead_id')
            && Schema::hasColumn('lead_form_field_values', 'field_key')
            && Schema::hasColumn('lead_form_field_values', 'field_value');
        $hasCrmAssignments = Schema::hasTable('crm_assignments')
            && Schema::hasColumn('crm_assignments', 'lead_id')
            && Schema::hasColumn('crm_assignments', 'call_status')
            && Schema::hasColumn('crm_assignments', 'cnp_count');
        $hasAdColumns = Schema::hasColumn('fb_leads', 'ad_id');
        $scoreValueSql = $hasLeadFormFieldValues
            ? "CAST(NULLIF(lead_quality_values.field_value, '') AS UNSIGNED)"
            : "NULL";
        $statusValueSql = $hasLeadFormFieldValues
            ? "LOWER(TRIM(COALESCE(lead_status_values.field_value, '')))"
            : "''";
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
        $qualifiedStatuses = [
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
        $qualitySelectSql = $hasLeadFormFieldValues
            ? "
                COUNT(DISTINCT CASE WHEN $scoreValueSql BETWEEN 1 AND 5 THEN leads.id ELSE NULL END) as scored_leads,
                AVG(CASE WHEN $scoreValueSql BETWEEN 1 AND 5 THEN $scoreValueSql ELSE NULL END) as avg_quality,
                COUNT(DISTINCT CASE WHEN $scoreValueSql = 5 THEN leads.id ELSE NULL END) as quality_5,
                COUNT(DISTINCT CASE WHEN $scoreValueSql = 4 THEN leads.id ELSE NULL END) as quality_4,
                COUNT(DISTINCT CASE WHEN $scoreValueSql = 3 THEN leads.id ELSE NULL END) as quality_3,
                COUNT(DISTINCT CASE WHEN $scoreValueSql = 2 THEN leads.id ELSE NULL END) as quality_2,
                COUNT(DISTINCT CASE WHEN $scoreValueSql = 1 THEN leads.id ELSE NULL END) as quality_1,
                COUNT(DISTINCT CASE WHEN ($scoreValueSql = 1 OR leads.status IN ('junk', 'not_interested') OR $statusValueSql IN ('junk', 'not_interested')) THEN leads.id ELSE NULL END) as bad
            "
            : "
                0 as scored_leads,
                NULL as avg_quality,
                0 as quality_5,
                0 as quality_4,
                0 as quality_3,
                0 as quality_2,
                0 as quality_1,
                COUNT(DISTINCT CASE WHEN leads.status IN ('junk', 'not_interested') THEN leads.id ELSE NULL END) as bad
            ";
        $baseJoins = function ($query) use ($hasLeadFormFieldValues, $hasCrmAssignments) {
            if ($hasLeadFormFieldValues) {
                $query->leftJoin('lead_form_field_values as lead_quality_values', function ($join) {
                    $join->on('lead_quality_values.lead_id', '=', 'leads.id')
                        ->where('lead_quality_values.field_key', '=', 'lead_quality');
                })->leftJoin('lead_form_field_values as lead_status_values', function ($join) {
                    $join->on('lead_status_values.lead_id', '=', 'leads.id')
                        ->where('lead_status_values.field_key', '=', 'lead_status');
                });
            }

            if ($hasCrmAssignments) {
                $query->leftJoin('crm_assignments', 'crm_assignments.lead_id', '=', 'leads.id');
            }
        };
        $dateFilter = function ($query) use ($from, $to) {
            $query->whereBetween('fb_leads.created_at', [$from, $to]);
        };
        $spendByForm = $hasAdColumns ? $this->metaSpendByFormForRange($from, $to) : collect();
        $spendByAd = $hasAdColumns ? $this->metaSpendByAdForRange($from, $to) : collect();

        $formRows = DB::table('fb_leads')
            ->join('fb_forms', 'fb_forms.id', '=', 'fb_leads.fb_form_id')
            ->join('leads', 'leads.id', '=', 'fb_leads.crm_lead_id')
            ->tap($baseJoins)
            ->tap($dateFilter)
            ->selectRaw("
                fb_forms.id as form_id,
                COALESCE(NULLIF(fb_forms.form_name, ''), CONCAT('Meta Form ', fb_forms.form_id)) as form_name,
                COUNT(DISTINCT fb_leads.id) as leads,
                COUNT(DISTINCT CASE WHEN leads.status IN ('" . implode("','", $qualifiedStatuses) . "') THEN leads.id ELSE NULL END) as qualified,
                COUNT(DISTINCT CASE WHEN leads.status = 'closed' THEN leads.id ELSE NULL END) as closers,
                COUNT(DISTINCT CASE WHEN $holdConditionSql THEN leads.id ELSE NULL END) as hold,
                $qualitySelectSql
            ")
            ->groupBy('fb_forms.id', 'fb_forms.form_name', 'fb_forms.form_id')
            ->orderByDesc('leads')
            ->limit(8)
            ->get()
            ->map(function ($row) use ($spendByForm) {
                $leads = (int) $row->leads;
                $spend = round((float) ($spendByForm[(int) $row->form_id] ?? 0), 2);
                $scoredLeads = (int) $row->scored_leads;
                $avgQuality = $scoredLeads > 0 ? round((float) $row->avg_quality, 1) : null;
                $hold = (int) $row->hold;
                $bad = (int) $row->bad;
                $closers = (int) $row->closers;

                return [
                    'name' => (string) $row->form_name,
                    'leads' => $leads,
                    'spend' => $spend,
                    'cpl' => $leads > 0 && $spend > 0 ? round($spend / $leads, 2) : null,
                    'qualified' => (int) $row->qualified,
                    'bad' => $bad,
                    'bad_rate' => $leads > 0 ? round(($bad / $leads) * 100, 1) : 0,
                    'hold' => $hold,
                    'hold_rate' => $leads > 0 ? round(($hold / $leads) * 100, 1) : 0,
                    'closers' => $closers,
                    'cost_per_closer' => $closers > 0 && $spend > 0 ? round($spend / $closers, 2) : null,
                    'scored_leads' => $scoredLeads,
                    'avg_quality' => $avgQuality,
                    'quality_5' => (int) $row->quality_5,
                    'quality_4' => (int) $row->quality_4,
                    'quality_3' => (int) $row->quality_3,
                    'quality_2' => (int) $row->quality_2,
                    'quality_1' => (int) $row->quality_1,
                    'good_quality_rate' => $scoredLeads > 0 ? round((((int) $row->quality_4 + (int) $row->quality_5) / $scoredLeads) * 100, 1) : 0,
                    'verdict' => $this->metaVerdict($avgQuality, $scoredLeads, $hold, $leads, $bad),
                ];
            });

        $campaignRows = $hasAdColumns
            ? DB::table('fb_leads')
                ->join('leads', 'leads.id', '=', 'fb_leads.crm_lead_id')
                ->tap($baseJoins)
                ->tap($dateFilter)
                ->whereNotNull('fb_leads.ad_id')
                ->selectRaw("
                    COALESCE(NULLIF(fb_leads.campaign_name, ''), 'Unknown Campaign') as campaign_name,
                    COALESCE(NULLIF(fb_leads.adset_name, ''), 'Unknown Adset') as adset_name,
                    COALESCE(NULLIF(fb_leads.ad_name, ''), 'Unknown Ad') as ad_name,
                    fb_leads.ad_id as ad_id,
                    COUNT(DISTINCT fb_leads.id) as leads,
                    COUNT(DISTINCT CASE WHEN leads.status = 'closed' THEN leads.id ELSE NULL END) as closers,
                    COUNT(DISTINCT CASE WHEN $holdConditionSql THEN leads.id ELSE NULL END) as hold,
                    $qualitySelectSql
                ")
                ->groupBy('fb_leads.campaign_id', 'fb_leads.campaign_name', 'fb_leads.adset_id', 'fb_leads.adset_name', 'fb_leads.ad_id', 'fb_leads.ad_name')
                ->orderByDesc('leads')
                ->limit(8)
                ->get()
                ->map(function ($row) use ($spendByAd) {
                    $leads = (int) $row->leads;
                    $spend = round((float) ($spendByAd[(string) $row->ad_id] ?? 0), 2);
                    $scoredLeads = (int) $row->scored_leads;
                    $avgQuality = $scoredLeads > 0 ? round((float) $row->avg_quality, 1) : null;
                    $hold = (int) $row->hold;
                    $closers = (int) $row->closers;

                    return [
                        'campaign_name' => (string) $row->campaign_name,
                        'adset_name' => (string) $row->adset_name,
                        'ad_name' => (string) $row->ad_name,
                        'leads' => $leads,
                        'spend' => $spend,
                        'cpl' => $leads > 0 && $spend > 0 ? round($spend / $leads, 2) : null,
                        'avg_quality' => $avgQuality,
                        'hold' => $hold,
                        'hold_rate' => $leads > 0 ? round(($hold / $leads) * 100, 1) : 0,
                        'closers' => $closers,
                        'cost_per_closer' => $closers > 0 && $spend > 0 ? round($spend / $closers, 2) : null,
                    ];
                })
            : collect();

        $totalLeads = (int) $formRows->sum('leads');
        $totalSpend = round((float) $formRows->sum('spend'), 2);
        $totalClosers = (int) $formRows->sum('closers');
        $totalScored = (int) $formRows->sum('scored_leads');
        $weightedQuality = $totalScored > 0
            ? round($formRows->sum(fn ($row) => (float) ($row['avg_quality'] ?? 0) * (int) $row['scored_leads']) / $totalScored, 1)
            : null;
        $funnel = $this->metaFunnelRows($from, $to, $holdConditionSql, $baseJoins);
        $totals = [
            'leads' => $totalLeads,
            'spend' => $totalSpend,
            'cpl' => $totalLeads > 0 && $totalSpend > 0 ? round($totalSpend / $totalLeads, 2) : null,
            'qualified' => (int) $formRows->sum('qualified'),
            'bad' => (int) $formRows->sum('bad'),
            'hold' => (int) $formRows->sum('hold'),
            'closers' => $totalClosers,
            'cost_per_closer' => $totalClosers > 0 && $totalSpend > 0 ? round($totalSpend / $totalClosers, 2) : null,
            'avg_quality' => $weightedQuality,
            'scored_leads' => $totalScored,
        ];

        return [
            'forms' => $formRows,
            'campaigns' => $campaignRows,
            'funnel' => $funnel,
            'alerts' => $this->metaAttentionAlerts($totals, $formRows, $campaignRows),
            'range' => $range + ['preset' => $preset],
            'totals' => $totals,
        ];
    }

    private function resolveMetaDateRange(string $preset): array
    {
        return match ($preset) {
            'today' => ['from' => now()->startOfDay(), 'to' => now()->endOfDay(), 'label' => 'Today'],
            'week' => ['from' => now()->startOfWeek(), 'to' => now()->endOfDay(), 'label' => 'This Week'],
            'year' => ['from' => now()->startOfYear(), 'to' => now()->endOfDay(), 'label' => 'This Year'],
            default => ['from' => now()->startOfMonth(), 'to' => now()->endOfDay(), 'label' => 'This Month'],
        };
    }

    private function metaSpendByFormForRange($from, $to): Collection
    {
        $pairs = DB::table('fb_leads')
            ->join('leads', 'leads.id', '=', 'fb_leads.crm_lead_id')
            ->join('meta_ad_insights_daily', 'meta_ad_insights_daily.ad_id', '=', 'fb_leads.ad_id')
            ->whereBetween('fb_leads.created_at', [$from, $to])
            ->whereBetween('meta_ad_insights_daily.date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('DISTINCT fb_leads.fb_form_id as form_id, meta_ad_insights_daily.date, meta_ad_insights_daily.ad_id, meta_ad_insights_daily.spend');

        return DB::query()
            ->fromSub($pairs, 'form_spend')
            ->selectRaw('form_id, SUM(spend) as spend')
            ->groupBy('form_id')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->form_id => (float) $row->spend]);
    }

    private function metaSpendByAdForRange($from, $to): Collection
    {
        return DB::table('meta_ad_insights_daily')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('ad_id, SUM(spend) as spend')
            ->groupBy('ad_id')
            ->get()
            ->mapWithKeys(fn ($row) => [(string) $row->ad_id => (float) $row->spend]);
    }

    private function metaFunnelRows($from, $to, string $holdConditionSql, callable $baseJoins): Collection
    {
        $row = DB::table('fb_leads')
            ->join('leads', 'leads.id', '=', 'fb_leads.crm_lead_id')
            ->tap($baseJoins)
            ->whereBetween('fb_leads.created_at', [$from, $to])
            ->selectRaw("
                COUNT(DISTINCT leads.id) as received,
                COUNT(DISTINCT CASE WHEN leads.status IN ('contacted', 'connected', 'qualified', 'verified_prospect', 'meeting_scheduled', 'meeting_completed', 'visit_scheduled', 'visit_done', 'closed') THEN leads.id ELSE NULL END) as contacted,
                COUNT(DISTINCT CASE WHEN leads.status IN ('qualified', 'verified_prospect', 'meeting_scheduled', 'meeting_completed', 'visit_scheduled', 'visit_done', 'closed') THEN leads.id ELSE NULL END) as qualified,
                COUNT(DISTINCT CASE WHEN leads.status IN ('visit_scheduled', 'visit_done', 'closed') THEN leads.id ELSE NULL END) as visit_scheduled,
                COUNT(DISTINCT CASE WHEN leads.status IN ('visit_done', 'closed') THEN leads.id ELSE NULL END) as visit_done,
                COUNT(DISTINCT CASE WHEN leads.status = 'closed' THEN leads.id ELSE NULL END) as closed,
                COUNT(DISTINCT CASE WHEN leads.status IN ('junk', 'not_interested') THEN leads.id ELSE NULL END) as bad,
                COUNT(DISTINCT CASE WHEN $holdConditionSql THEN leads.id ELSE NULL END) as hold
            ")
            ->first();

        $received = max((int) ($row->received ?? 0), 1);

        return collect([
            ['label' => 'Received', 'count' => (int) ($row->received ?? 0)],
            ['label' => 'Contacted', 'count' => (int) ($row->contacted ?? 0)],
            ['label' => 'Qualified', 'count' => (int) ($row->qualified ?? 0)],
            ['label' => 'Visit Scheduled', 'count' => (int) ($row->visit_scheduled ?? 0)],
            ['label' => 'Visit Done', 'count' => (int) ($row->visit_done ?? 0)],
            ['label' => 'Closed', 'count' => (int) ($row->closed ?? 0)],
            ['label' => 'Bad', 'count' => (int) ($row->bad ?? 0)],
            ['label' => 'Hold / CNP', 'count' => (int) ($row->hold ?? 0)],
        ])->map(fn ($item) => $item + ['rate' => round(($item['count'] / $received) * 100, 1)]);
    }

    private function metaAttentionAlerts(array $totals, Collection $forms, Collection $campaigns): Collection
    {
        $alerts = collect();

        if (($totals['spend'] ?? 0) > 0 && ($totals['leads'] ?? 0) === 0) {
            $alerts->push(['type' => 'danger', 'title' => 'Spend without leads', 'body' => 'Meta spend synced hai, lekin CRM leads match nahi ho rahe.']);
        }

        foreach ($forms as $row) {
            if (($row['leads'] ?? 0) >= 5 && ($row['scored_leads'] ?? 0) === 0) {
                $alerts->push(['type' => 'warning', 'title' => 'Quality missing', 'body' => $row['name'] . ' form me leads hain but lead quality scoring missing hai.']);
            }
            if (($row['hold_rate'] ?? 0) >= 30) {
                $alerts->push(['type' => 'warning', 'title' => 'High Hold/CNP', 'body' => $row['name'] . ' form ka Hold/CNP ' . $row['hold_rate'] . '%.']);
            }
            if (($row['bad_rate'] ?? 0) >= 30) {
                $alerts->push(['type' => 'danger', 'title' => 'High bad leads', 'body' => $row['name'] . ' form me bad lead rate ' . $row['bad_rate'] . '%.']);
            }
            if (($row['cpl'] ?? 0) > 0 && ($row['avg_quality'] ?? 5) < 2.5) {
                $alerts->push(['type' => 'warning', 'title' => 'Paid low quality', 'body' => $row['name'] . ' spend le raha hai but avg quality low hai.']);
            }
        }

        foreach ($campaigns as $row) {
            if (($row['spend'] ?? 0) >= 1000 && ($row['leads'] ?? 0) <= 2) {
                $alerts->push(['type' => 'danger', 'title' => 'High spend low leads', 'body' => $row['campaign_name'] . ' campaign me spend high hai, leads low hain.']);
            }
        }

        return $alerts->take(6)->values();
    }

    private function metaVerdict(?float $avgQuality, int $scoredLeads, int $hold, int $leads, int $bad): string
    {
        if ($scoredLeads < 5 || $avgQuality === null) {
            return 'Needs More Data';
        }

        $holdRate = $leads > 0 ? ($hold / $leads) * 100 : 0;
        $badRate = $leads > 0 ? ($bad / $leads) * 100 : 0;

        if ($avgQuality >= 4.5 && $holdRate < 15 && $badRate < 20) {
            return 'Excellent';
        }
        if ($avgQuality >= 3.5 && $holdRate < 25 && $badRate < 30) {
            return 'Good';
        }
        if ($avgQuality >= 2.5) {
            return 'Average';
        }

        return 'Poor';
    }

    private function resolveManagerNotes(User $user): Collection
    {
        $managerId = $user->isMarketingManager() ? $user->id : $user->manager_id;

        if (!$managerId) {
            return collect();
        }

        if (!Schema::hasTable('marketing_manager_notes')) {
            return collect();
        }

        return MarketingManagerNote::query()
            ->where('manager_id', $managerId)
            ->active()
            ->orderBy('sort_order')
            ->latest('updated_at')
            ->get();
    }

    private function buildTeamAttendanceSummary(Collection $teamMembers): ?array
    {
        if ($teamMembers->isEmpty()) {
            return null;
        }

        $teamMemberIds = $teamMembers->pluck('id');
        $today = now()->toDateString();

        $todayRecords = AttendanceRecord::query()
            ->whereIn('user_id', $teamMemberIds)
            ->whereDate('attendance_date', $today)
            ->get(['user_id', 'status', 'first_punch_in_at']);

        $leaveIds = LeaveRequest::query()
            ->whereIn('user_id', $teamMemberIds)
            ->where('status', 'approved')
            ->whereDate('from_date', '<=', $today)
            ->whereDate('to_date', '>=', $today)
            ->pluck('user_id')
            ->unique();

        $punchedIds = $todayRecords
            ->filter(fn (AttendanceRecord $record) => $record->first_punch_in_at !== null)
            ->pluck('user_id')
            ->unique();

        return [
            'present' => $todayRecords->whereIn('status', [
                AttendanceRecord::STATUS_PRESENT,
                AttendanceRecord::STATUS_LATE,
                AttendanceRecord::STATUS_HALF_DAY,
            ])->count(),
            'absent' => $todayRecords->where('status', AttendanceRecord::STATUS_ABSENT)->count(),
            'on_leave' => $leaveIds->count() ?: $todayRecords->where('status', AttendanceRecord::STATUS_LEAVE)->count(),
            'late' => $todayRecords->where('status', AttendanceRecord::STATUS_LATE)->count(),
            'not_punched_in' => max($teamMemberIds->count() - $punchedIds->count() - $leaveIds->count(), 0),
            'team_size' => $teamMemberIds->count(),
        ];
    }

    private function buildTeamTaskCards(Collection $teamMembers, array $openStatuses): Collection
    {
        if ($teamMembers->isEmpty()) {
            return collect();
        }

        $groupedTasks = ExecutionTask::query()
            ->with(['creator:id,name', 'assignee:id,name'])
            ->whereIn('assigned_to', $teamMembers->pluck('id'))
            ->whereIn('status', $openStatuses)
            ->latest()
            ->get()
            ->groupBy('assigned_to');

        return $teamMembers->map(function (User $member) use ($groupedTasks) {
            $tasks = $groupedTasks->get($member->id, collect());

            return [
                'member' => $member,
                'open_count' => $tasks->count(),
                'overdue_count' => $tasks->filter(fn (ExecutionTask $task) => $task->due_at && $task->due_at->isPast())->count(),
                'waiting_count' => $tasks->where('status', 'waiting')->count(),
                'recent_tasks' => $tasks->take(3)->values(),
            ];
        });
    }

    public function profile(Request $request)
    {
        $user = $request->user();
        $user->loadMissing('role');

        abort_unless($user->isMarketingUser(), 403, 'Unauthorized access.');

        return view('marketing.profile');
    }
}

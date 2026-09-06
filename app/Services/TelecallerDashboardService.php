<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\TelecallerTask;
use App\Models\TelecallerDailyLimit;
use App\Models\Target;
use App\Models\Prospect;
use App\Models\FollowUp;
use App\Models\SiteVisit;
use App\Models\ActivityLog;
use App\Models\SlaTracking;
use App\Models\User;
use App\Helpers\DashboardHelper;
use App\Services\LeadsPendingResponseService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TelecallerDashboardService
{
    public function __construct(private DashboardKpiModeService $dashboardKpiModeService)
    {
    }

    /**
     * Get complete dashboard data for telecaller
     */
    public function getDashboardData(int $userId, string $dateRange = 'today', $startDate = null, $endDate = null): array
    {
        [$startDate, $endDate] = $this->getDateRange($dateRange, $startDate, $endDate);

        return [
            'today_stats' => $this->getTodayStats($userId, $startDate, $endDate),
            'urgent_tasks' => $this->getUrgentTasks($userId),
            'today_schedule' => $this->getTodaySchedule($userId),
            'lead_breakdown' => $this->getLeadBreakdown($userId),
            'performance_metrics' => $this->getPerformanceMetrics($userId),
            'recent_activity' => $this->getRecentActivity($userId),
            'sla_compliance' => $this->getSlaCompliance($userId),
            'call_quality_metrics' => $this->getCallQualityMetrics($userId),
            'daily_limit' => $this->getDailyLimit($userId),
        ];
    }

    /**
     * Get date range based on filter type
     */
    private function getDateRange(string $dateRange, $startDate = null, $endDate = null): array
    {
        $today = Carbon::today();

        // If custom dates provided, use them
        if ($startDate && $endDate) {
            return [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay(),
            ];
        }

        // Calculate based on filter type
        switch ($dateRange) {
            case 'today':
                return [
                    $today->copy()->startOfDay(),
                    $today->copy()->endOfDay(),
                ];
            case 'this_week':
                return [
                    $today->copy()->startOfWeek(),
                    $today->copy()->endOfWeek(),
                ];
            case 'this_month':
                return [
                    $today->copy()->startOfMonth(),
                    $today->copy()->endOfMonth(),
                ];
            default:
                return [
                    $today->copy()->startOfDay(),
                    $today->copy()->endOfDay(),
                ];
        }
    }

    /**
     * Get today's key performance indicators
     */
    public function getTodayStats(int $userId, $startDate = null, $endDate = null): array
    {
        $startDate = $startDate ?? Carbon::today()->startOfDay();
        $endDate = $endDate ?? Carbon::today()->endOfDay();

        // Ensure Carbon instances
        if (!$startDate instanceof Carbon) {
            $startDate = Carbon::parse($startDate)->startOfDay();
        }
        if (!$endDate instanceof Carbon) {
            $endDate = Carbon::parse($endDate)->endOfDay();
        }

        // Get assigned leads count - filter by assigned_at date range
        $totalAssigned = LeadAssignment::query()
            ->activeWithLiveLead()
            ->where('assigned_to', $userId)
            ->whereBetween('assigned_at', [$startDate, $endDate])
            ->count();

        // Get daily limit
        $dailyLimit = TelecallerDailyLimit::where('user_id', $userId)->first();
        $dailyLimitCount = $dailyLimit ? $dailyLimit->assigned_count_today : 0;
        $dailyLimitMax = $dailyLimit ? $dailyLimit->overall_daily_limit : 0;

        // Calls made in date range (completed tasks)
        $callsMadeToday = TelecallerTask::where('assigned_to', $userId)
            ->where('task_type', 'call')
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->count();

        // Connected calls (outcome = connected or interested)
        $connectedCalls = TelecallerTask::where('assigned_to', $userId)
            ->where('task_type', 'call')
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->whereIn('outcome', ['connected', 'interested', 'qualified'])
            ->count();

        // Lead status updates in date range
        $statusUpdates = ActivityLog::where('user_id', $userId)
            ->where('action', 'like', '%status%')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        // Site visits scheduled in date range
        $siteVisitsScheduled = SiteVisit::where('assigned_to', $userId)
            ->where('status', 'scheduled')
            ->whereBetween('scheduled_at', [$startDate, $endDate])
            ->count();

        // Follow-ups completed in date range
        $followUpsCompleted = FollowUp::where('created_by', $userId)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->count();

        return [
            'total_leads_assigned' => $totalAssigned,
            'daily_limit_used' => $dailyLimitCount,
            'daily_limit_max' => $dailyLimitMax,
            'daily_limit_percentage' => $dailyLimitMax > 0 ? round(($dailyLimitCount / $dailyLimitMax) * 100, 1) : 0,
            'calls_made_today' => $callsMadeToday,
            'connected_calls' => $connectedCalls,
            'connection_rate' => $callsMadeToday > 0 ? round(($connectedCalls / $callsMadeToday) * 100, 1) : 0,
            'lead_status_updates' => $statusUpdates,
            'site_visits_scheduled' => $siteVisitsScheduled,
            'followups_completed' => $followUpsCompleted,
        ];
    }

    /**
     * Get urgent tasks (overdue, SLA risks, etc.)
     */
    public function getUrgentTasks(int $userId): array
    {
        $now = Carbon::now();
        $today = Carbon::today();

        // Overdue follow-ups
        $overdueFollowups = FollowUp::where('created_by', $userId)
            ->where('status', 'scheduled')
            ->where('scheduled_at', '<', $now)
            ->with('lead')
            ->orderBy('scheduled_at', 'asc')
            ->limit(10)
            ->get()
            ->map(function ($followup) {
                return [
                    'id' => $followup->id,
                    'type' => 'followup',
                    'title' => $followup->lead->name ?? 'Unknown',
                    'scheduled_at' => $followup->scheduled_at,
                    'lead_id' => $followup->lead_id,
                ];
            });

        // Today's pending calls
        $pendingCalls = TelecallerTask::where('assigned_to', $userId)
            ->where('status', 'pending')
            ->where('task_type', 'call')
            ->whereDate('scheduled_at', $today)
            ->with('lead')
            ->orderBy('scheduled_at', 'asc')
            ->limit(10)
            ->get()
            ->map(function ($task) {
                return [
                    'id' => $task->id,
                    'type' => 'call',
                    'title' => $task->lead->name ?? 'Unknown',
                    'scheduled_at' => $task->scheduled_at,
                    'lead_id' => $task->lead_id,
                ];
            });

        // SLA breach risk — Smart Import removed
        $slaRisks = [];

        // Today's site visits
        $todaySiteVisits = SiteVisit::where('assigned_to', $userId)
            ->where('status', 'scheduled')
            ->whereDate('scheduled_at', $today)
            ->with('lead')
            ->orderBy('scheduled_at', 'asc')
            ->get()
            ->map(function ($visit) {
                return [
                    'id' => $visit->id,
                    'type' => 'site_visit',
                    'title' => $visit->lead->name ?? 'Unknown',
                    'scheduled_at' => $visit->scheduled_at,
                    'property_name' => $visit->property_name,
                    'lead_id' => $visit->lead_id,
                ];
            });

        return [
            'overdue_followups' => $overdueFollowups->toArray(),
            'overdue_followups_count' => $overdueFollowups->count(),
            'pending_calls_today' => $pendingCalls->toArray(),
            'pending_calls_count' => $pendingCalls->count(),
            'sla_risks' => $slaRisks,
            'sla_risks_count' => count($slaRisks),
            'today_site_visits' => $todaySiteVisits->toArray(),
            'today_site_visits_count' => $todaySiteVisits->count(),
        ];
    }

    /**
     * Get today's schedule timeline
     */
    public function getTodaySchedule(int $userId): array
    {
        $today = Carbon::today();
        $todayEnd = Carbon::today()->endOfDay();

        $schedule = [];

        // Today's tasks
        $tasks = TelecallerTask::where('assigned_to', $userId)
            ->whereBetween('scheduled_at', [$today, $todayEnd])
            ->where('status', '!=', 'cancelled')
            ->with('lead')
            ->orderBy('scheduled_at', 'asc')
            ->get();

        foreach ($tasks as $task) {
            $schedule[] = [
                'id' => $task->id,
                'type' => $task->task_type,
                'title' => $task->lead->name ?? 'Unknown',
                'scheduled_at' => $task->scheduled_at,
                'status' => $task->status,
                'lead_id' => $task->lead_id,
            ];
        }

        // Today's follow-ups
        $followups = FollowUp::where('created_by', $userId)
            ->whereBetween('scheduled_at', [$today, $todayEnd])
            ->where('status', 'scheduled')
            ->with('lead')
            ->orderBy('scheduled_at', 'asc')
            ->get();

        foreach ($followups as $followup) {
            $schedule[] = [
                'id' => $followup->id,
                'type' => 'followup',
                'title' => $followup->lead->name ?? 'Unknown',
                'scheduled_at' => $followup->scheduled_at,
                'status' => $followup->status,
                'lead_id' => $followup->lead_id,
            ];
        }

        // Today's site visits
        $siteVisits = SiteVisit::where('assigned_to', $userId)
            ->whereBetween('scheduled_at', [$today, $todayEnd])
            ->where('status', 'scheduled')
            ->with('lead')
            ->orderBy('scheduled_at', 'asc')
            ->get();

        foreach ($siteVisits as $visit) {
            $schedule[] = [
                'id' => $visit->id,
                'type' => 'site_visit',
                'title' => $visit->lead->name ?? 'Unknown',
                'scheduled_at' => $visit->scheduled_at,
                'status' => $visit->status,
                'property_name' => $visit->property_name,
                'lead_id' => $visit->lead_id,
            ];
        }

        // Sort by scheduled_at
        usort($schedule, function ($a, $b) {
            $aTime = $a['scheduled_at'] instanceof Carbon ? $a['scheduled_at']->timestamp : strtotime($a['scheduled_at']);
            $bTime = $b['scheduled_at'] instanceof Carbon ? $b['scheduled_at']->timestamp : strtotime($b['scheduled_at']);
            return $aTime <=> $bTime;
        });

        return $schedule;
    }

    /**
     * Get lead breakdown by status
     */
    public function getLeadBreakdown(int $userId): array
    {
        $leads = Lead::whereHas('activeAssignments', function ($q) use ($userId) {
            $q->where('assigned_to', $userId);
        })->get();

        $breakdown = [
            'new' => 0,
            'contacted' => 0,
            'qualified' => 0,
            'site_visit_scheduled' => 0,
            'site_visit_completed' => 0,
            'negotiation' => 0,
            'closed_won' => 0,
            'closed_lost' => 0,
            'on_hold' => 0,
            'total' => $leads->count(),
        ];

        foreach ($leads as $lead) {
            if (isset($breakdown[$lead->status])) {
                $breakdown[$lead->status]++;
            }
        }

        // Get hot leads (high CNP count or recently updated)
        $hotLeads = $leads->filter(function ($lead) {
            return ($lead->cnp_count && $lead->cnp_count >= 3) || 
                   ($lead->last_contacted_at && $lead->last_contacted_at->isToday());
        })->count();

        // Follow-up required (has next_followup_at in past or today)
        $followupRequired = $leads->filter(function ($lead) {
            return $lead->next_followup_at && $lead->next_followup_at->lte(Carbon::now());
        })->count();

        $breakdown['hot_leads'] = $hotLeads;
        $breakdown['followup_required'] = $followupRequired;

        return $breakdown;
    }

    /**
     * Get performance metrics (targets vs achievements)
     */
    public function getPerformanceMetrics(int $userId): array
    {
        $currentMonth = Carbon::now()->startOfMonth();
        $currentMonthEnd = Carbon::now()->endOfMonth();

        // Get current month target
        $target = Target::where('user_id', $userId)
            ->whereYear('target_month', $currentMonth->year)
            ->whereMonth('target_month', $currentMonth->month)
            ->first();

        if (!$target) {
            return [
                'has_target' => false,
                'targets' => [],
                'achievements' => [],
                'percentages' => [],
            ];
        }

        // Calculate achievements - Use TelecallerTask with task_type='calling' and status='completed'
        $callsMade = TelecallerTask::where('assigned_to', $userId)
            ->where('task_type', 'calling')
            ->where('status', 'completed')
            ->whereYear('completed_at', $currentMonth->year)
            ->whereMonth('completed_at', $currentMonth->month)
            ->count();

        $siteVisitsCompleted = SiteVisit::where('assigned_to', $userId)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$currentMonth, $currentMonthEnd])
            ->count();

        $closedWon = Lead::whereHas('activeAssignments', function ($q) use ($userId) {
            $q->where('assigned_to', $userId);
        })
            ->where('status', 'closed_won')
            ->whereMonth('updated_at', $currentMonth->month)
            ->whereYear('updated_at', $currentMonth->year)
            ->count();

        $followUpsDone = FollowUp::where('created_by', $userId)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$currentMonth, $currentMonthEnd])
            ->count();

        // Get verified prospects count (only verified prospects count for telecaller)
        $verifiedProspects = Prospect::where('telecaller_id', $userId)
            ->where('verification_status', 'verified')
            ->whereYear('verified_at', $currentMonth->year)
            ->whereMonth('verified_at', $currentMonth->month)
            ->count();
        
        // Get calls count using TelecallerTask with task_type='calling' and status='completed'
        $callsMade = TelecallerTask::where('assigned_to', $userId)
            ->where('task_type', 'calling')
            ->where('status', 'completed')
            ->whereYear('completed_at', $currentMonth->year)
            ->whereMonth('completed_at', $currentMonth->month)
            ->count();
        
        // Get targets
        $targetCalls = $target->target_calls ?? 0;
        $targetProspects = $target->target_prospects_verified ?? 0;
        
        return [
            'has_target' => true,
            'targets' => [
                'calls' => $targetCalls,
                'prospects_verified' => $targetProspects,
                'visits' => $target->target_visits ?? 0,
                'closers' => $target->target_closers ?? 0,
                'followups' => 0, // Not in target model, but tracking
            ],
            'achievements' => [
                'calls' => $callsMade,
                'prospects_verified' => $verifiedProspects,
                'visits' => $siteVisitsCompleted,
                'closers' => $closedWon,
                'followups' => $followUpsDone,
            ],
            'percentages' => [
                'calls' => $targetCalls > 0 ? round(($callsMade / $targetCalls) * 100, 1) : 0,
                'prospects_verified' => $targetProspects > 0 ? round(($verifiedProspects / $targetProspects) * 100, 1) : 0,
                'visits' => $target->target_visits > 0 ? round(($siteVisitsCompleted / $target->target_visits) * 100, 1) : 0,
                'closers' => $target->target_closers > 0 ? round(($closedWon / $target->target_closers) * 100, 1) : 0,
                'followups' => 0, // No target for this
            ],
        ];
    }

    /**
     * Get recent activity log entries
     */
    public function getRecentActivity(int $userId, int $limit = 10): array
    {
        $activities = ActivityLog::where('user_id', $userId)
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'action' => $activity->action,
                    'description' => $activity->description,
                    'model_type' => class_basename($activity->model_type ?? ''),
                    'model_id' => $activity->model_id,
                    'created_at' => $activity->created_at,
                    'created_at_human' => $activity->created_at->diffForHumans(),
                ];
            });

        return $activities->toArray();
    }

    /**
     * Get SLA compliance metrics
     */
    public function getSlaCompliance(int $userId): array
    {
        // Smart Import SLA removed
        return [
            'total' => 0,
            'met' => 0,
            'breached' => 0,
            'pending' => 0,
            'compliance_rate' => 100,
        ];
    }

    /**
     * Get call quality metrics
     */
    public function getCallQualityMetrics(int $userId): array
    {
        $tasks = TelecallerTask::where('assigned_to', $userId)
            ->where('task_type', 'call')
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', Carbon::now()->subDays(30))
            ->get();

        $totalCalls = $tasks->count();
        $connectedCalls = $tasks->whereIn('outcome', ['connected', 'interested', 'qualified'])->count();
        $conversionRate = DashboardHelper::calculateConversionRate(
            $tasks->where('outcome', 'closed_won')->count(),
            $totalCalls
        );

        // Calculate average call duration
        $avgDuration = 0;
        $completedTasks = $tasks->filter(function ($task) {
            return $task->scheduled_at && $task->completed_at;
        });

        if ($completedTasks->count() > 0) {
            $totalMinutes = 0;
            foreach ($completedTasks as $task) {
                $totalMinutes += $task->scheduled_at->diffInMinutes($task->completed_at);
            }
            $avgDuration = round($totalMinutes / $completedTasks->count(), 1);
        }

        $notInterested = $tasks->where('outcome', 'not_interested')->count();
        $callbackRequired = $tasks->where('outcome', 'callback_required')->count();

        return [
            'total_calls' => $totalCalls,
            'connected_calls' => $connectedCalls,
            'connection_rate' => DashboardHelper::calculateConversionRate($connectedCalls, $totalCalls),
            'conversion_rate' => $conversionRate,
            'average_duration_minutes' => $avgDuration,
            'not_interested_count' => $notInterested,
            'callback_required_count' => $callbackRequired,
            'not_interested_percentage' => DashboardHelper::calculateConversionRate($notInterested, $totalCalls),
            'callback_required_percentage' => DashboardHelper::calculateConversionRate($callbackRequired, $totalCalls),
        ];
    }

    /**
     * Get daily limit information
     */
    public function getDailyLimit(int $userId): array
    {
        $limit = TelecallerDailyLimit::where('user_id', $userId)->first();

        if (!$limit) {
            return [
                'overall_daily_limit' => 0,
                'assigned_count_today' => 0,
                'remaining' => 0,
                'percentage_used' => 0,
            ];
        }

        $limit->resetIfNewDay();

        return [
            'overall_daily_limit' => $limit->overall_daily_limit,
            'assigned_count_today' => $limit->assigned_count_today,
            'remaining' => max(0, $limit->overall_daily_limit - $limit->assigned_count_today),
            'percentage_used' => $limit->overall_daily_limit > 0 
                ? round(($limit->assigned_count_today / $limit->overall_daily_limit) * 100, 1) 
                : 0,
        ];
    }

    /**
     * Get dashboard card statistics
     */
    public function getDashboardCardStats(int $userId, string $dateRange = 'today', $startDate = null, $endDate = null, $targetMonth = null, string $targetFilter = 'today'): array
    {
        [$startDate, $endDate] = $this->getDateRange($dateRange, $startDate, $endDate);
        $user = User::with('role')->find($userId);
        $resolvedMode = $this->dashboardKpiModeService->resolveModeForUser($user);

        // Ensure Carbon instances
        if (!$startDate instanceof Carbon) {
            $startDate = Carbon::parse($startDate)->startOfDay();
        }
        if (!$endDate instanceof Carbon) {
            $endDate = Carbon::parse($endDate)->endOfDay();
        }

        // Today Leads: Count of leads assigned in the date range
        $todayLeads = LeadAssignment::query()
            ->activeWithLiveLead()
            ->where('assigned_to', $userId)
            ->whereBetween('assigned_at', [$startDate, $endDate])
            ->count();

        // Remaining Tasks: Count of pending/in_progress tasks
        $remainingTasks = TelecallerTask::where('assigned_to', $userId)
            ->whereIn('status', ['pending', 'in_progress'])
            ->count();

        // Overdue Tasks: Count of overdue tasks (scheduled_at < now - 10 minutes and status != completed)
        $tenMinutesAgo = now()->subMinutes(10);
        $overdueTasks = TelecallerTask::where('assigned_to', $userId)
            ->where('status', '!=', 'completed')
            ->where('scheduled_at', '<', $tenMinutesAgo)
            ->count();

        // Prospects: Count of prospects created in the date range
        $prospects = Prospect::where('created_by', $userId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        // Leads allocated to me on which I haven't responded yet (this_month for card)
        $leadsPendingResponse = app(LeadsPendingResponseService::class)->getCountForUser($userId, 'this_month');

        $thisMonthLostLeads = app(\App\Services\NewLeadSlaAutomationService::class)->countLostLeadsForUserThisMonth($userId);

        // Get target data for telecaller - default to current month
        // If targetMonth parameter is provided, use it; otherwise use current month
        $targetMonthDate = $targetMonth ? Carbon::parse($targetMonth . '-01')->startOfMonth() : Carbon::now()->startOfMonth();
        $target = Target::where('user_id', $userId)
            ->whereYear('target_month', $targetMonthDate->year)
            ->whereMonth('target_month', $targetMonthDate->month)
            ->first();

        $targetsData = [];

        if ($resolvedMode === DashboardKpiModeService::TARGET_BASED && $target) {
            // Calculate targets based on filter period
            $callingData = $this->getCallingTargetForPeriod($target, $userId, $targetFilter, $targetMonthDate);
            $prospectData = $this->getProspectTargetForPeriod($target, $userId, $targetFilter, $targetMonthDate);
            $visitData = $this->getVisitTargetForPeriod($target, $userId, $targetFilter, $targetMonthDate);
        }

        if ($resolvedMode === DashboardKpiModeService::TARGET_BASED) {
            $targetsData['calling'] = $this->formatTargetMetric('Calling Target', $callingData ?? $this->emptyMetricData('daily'), 'Target Based');
            $targetsData['prospect'] = $this->formatTargetMetric('Prospect Target', $prospectData ?? $this->emptyMetricData('daily'), 'Target Based');
            $targetsData['visit'] = $this->formatTargetMetric('Visit Target', $visitData ?? $this->emptyMetricData('weekly'), 'Target Based');
        } else {
            $targetsData['calling'] = $this->formatActivityMetric(
                'Completed Calls',
                $this->getCallingActivityForPeriod($userId, $targetFilter, $targetMonthDate),
                'Completed'
            );
            $targetsData['prospect'] = $this->formatActivityMetric(
                'Verified Prospects',
                $this->getProspectActivityForPeriod($userId, $targetFilter, $targetMonthDate),
                'Verified'
            );
            $targetsData['visit'] = $this->formatActivityMetric(
                'Verified Visits',
                $this->getVerifiedVisitActivityForPeriod($userId, $targetFilter, $targetMonthDate),
                'Verified'
            );
        }

        return [
            'today_leads' => $todayLeads,
            'remaining_tasks' => $remainingTasks,
            'overdue_tasks' => $overdueTasks,
            'prospects' => $prospects,
            'leads_pending_response' => $leadsPendingResponse,
            'this_month_lost_leads' => $thisMonthLostLeads,
            'targets' => $targetsData,
        ];
    }

    private function emptyMetricData(string $period): array
    {
        return [
            'actual' => 0,
            'target' => 0,
            'monthly_target' => 0,
            'percentage' => 0,
            'period' => $period,
        ];
    }

    private function formatTargetMetric(string $label, array $metric, string $subtitle): array
    {
        $target = (int) ($metric['target'] ?? 0);
        $actual = (int) ($metric['actual'] ?? 0);

        return [
            'mode' => DashboardKpiModeService::TARGET_BASED,
            'label' => $label,
            'actual' => $actual,
            'achieved' => $actual,
            'target' => $target,
            'monthly_target' => (int) ($metric['monthly_target'] ?? 0),
            'percentage' => (float) ($metric['percentage'] ?? 0),
            'period' => $metric['period'] ?? 'daily',
            'subtitle' => $target > 0 ? $subtitle : 'Target not set',
            'has_target' => $target > 0,
        ];
    }

    private function formatActivityMetric(string $label, array $metric, string $subtitle): array
    {
        $actual = (int) ($metric['actual'] ?? 0);

        return [
            'mode' => DashboardKpiModeService::ACTIVITY_BASED,
            'label' => $label,
            'actual' => $actual,
            'achieved' => $actual,
            'target' => null,
            'monthly_target' => 0,
            'percentage' => null,
            'period' => $metric['period'] ?? 'daily',
            'subtitle' => $subtitle,
            'has_target' => false,
        ];
    }

    /**
     * Get period dates based on filter
     */
    private function getPeriodDates(string $filterPeriod, Carbon $targetMonthDate): array
    {
        switch($filterPeriod) {
            case 'today':
                return [Carbon::today(), Carbon::today()->endOfDay()];
            case 'this_week':
                return [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()];
            case 'this_month':
                return [$targetMonthDate->copy()->startOfMonth(), $targetMonthDate->copy()->endOfMonth()];
            default:
                return [Carbon::today(), Carbon::today()->endOfDay()];
        }
    }

    /**
     * Get calling target data for a specific period
     */
    private function getCallingTargetForPeriod(Target $target, int $userId, string $filterPeriod, Carbon $targetMonthDate): array
    {
        [$periodStart, $periodEnd] = $this->getPeriodDates($filterPeriod, $targetMonthDate);
        
        // Count completed calling tasks for the period (with CNP logic)
        $actual = 0;
        
        if ($filterPeriod === 'today') {
            $actual = $target->getDailyCallsCompletedCount();
        } else {
            // For week/month, count all completed calling tasks in the period
            $tasks = TelecallerTask::where('assigned_to', $userId)
                ->where('task_type', 'calling')
                ->where('status', 'completed')
                ->whereBetween('completed_at', [$periodStart, $periodEnd])
                ->with('lead')
                ->get();
            
            $count = 0;
            $processedCnpLeads = [];
            
            foreach ($tasks as $task) {
                // For CNP tasks, verify 2 calls on same day
                if ($task->outcome === 'cnp') {
                    $leadId = $task->lead_id;
                    
                    // Skip if we already counted this lead's CNP for the period
                    if (isset($processedCnpLeads[$leadId])) {
                        continue;
                    }
                    
                    // Check if both CNP calls happened in the same period
                    $cnpTasksSamePeriod = TelecallerTask::where('lead_id', $leadId)
                        ->where('outcome', 'cnp')
                        ->where('status', 'completed')
                        ->whereBetween('completed_at', [$periodStart, $periodEnd])
                        ->count();
                    
                    // Only count if 2 or more CNP calls in the period
                    if ($cnpTasksSamePeriod >= 2) {
                        $count++;
                        $processedCnpLeads[$leadId] = true;
                    }
                } else {
                    // Non-CNP tasks count normally
                    $count++;
                }
            }
            
            $actual = $count;
        }
        
        // Calculate target based on period
        $targetValue = 0;
        $periodLabel = 'daily';
        
        switch($filterPeriod) {
            case 'today':
                $targetValue = $target->getDailyCallsTarget();
                $periodLabel = 'daily';
                break;
            case 'this_week':
                $daysInMonth = $targetMonthDate->daysInMonth;
                $monthlyTarget = $target->target_calls ?? 0;
                $targetValue = $monthlyTarget > 0 ? round(($monthlyTarget * 7) / $daysInMonth, 2) : 0;
                $periodLabel = 'weekly';
                break;
            case 'this_month':
                $targetValue = $target->target_calls ?? 0;
                $periodLabel = 'monthly';
                break;
        }
        
        $percentage = $targetValue > 0 ? min(100, round(($actual / $targetValue) * 100, 1)) : 0;
        
        return [
            'actual' => $actual,
            'target' => round($targetValue),
            'monthly_target' => $target->target_calls ?? 0,
            'percentage' => $percentage,
            'period' => $periodLabel
        ];
    }

    private function getCallingActivityForPeriod(int $userId, string $filterPeriod, Carbon $targetMonthDate): array
    {
        [$periodStart, $periodEnd] = $this->getPeriodDates($filterPeriod, $targetMonthDate);

        $actual = TelecallerTask::where('assigned_to', $userId)
            ->whereIn('task_type', ['calling', 'call'])
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$periodStart, $periodEnd])
            ->count();

        return [
            'actual' => $actual,
            'period' => $this->getPeriodLabel($filterPeriod, 'daily'),
        ];
    }

    /**
     * Get prospect target data for a specific period
     */
    private function getProspectTargetForPeriod(Target $target, int $userId, string $filterPeriod, Carbon $targetMonthDate): array
    {
        [$periodStart, $periodEnd] = $this->getPeriodDates($filterPeriod, $targetMonthDate);
        
        // Count verified prospects for the period
        $actual = Prospect::where('telecaller_id', $userId)
            ->where('verification_status', 'verified')
            ->whereBetween('verified_at', [$periodStart, $periodEnd])
            ->count();
        
        // Calculate target based on period
        $targetValue = 0;
        $periodLabel = 'daily';
        
        switch($filterPeriod) {
            case 'today':
                $targetValue = $target->getDailyProspectsTarget();
                $periodLabel = 'daily';
                break;
            case 'this_week':
                $daysInMonth = $targetMonthDate->daysInMonth;
                $monthlyTarget = $target->target_prospects_verified ?? 0;
                $targetValue = $monthlyTarget > 0 ? round(($monthlyTarget * 7) / $daysInMonth, 2) : 0;
                $periodLabel = 'weekly';
                break;
            case 'this_month':
                $targetValue = $target->target_prospects_verified ?? 0;
                $periodLabel = 'monthly';
                break;
        }
        
        $percentage = $targetValue > 0 ? min(100, round(($actual / $targetValue) * 100, 1)) : 0;
        
        return [
            'actual' => $actual,
            'target' => round($targetValue),
            'monthly_target' => $target->target_prospects_verified ?? 0,
            'percentage' => $percentage,
            'period' => $periodLabel
        ];
    }

    private function getProspectActivityForPeriod(int $userId, string $filterPeriod, Carbon $targetMonthDate): array
    {
        [$periodStart, $periodEnd] = $this->getPeriodDates($filterPeriod, $targetMonthDate);

        $actual = Prospect::where('telecaller_id', $userId)
            ->where('verification_status', 'verified')
            ->whereBetween('verified_at', [$periodStart, $periodEnd])
            ->count();

        return [
            'actual' => $actual,
            'period' => $this->getPeriodLabel($filterPeriod, 'daily'),
        ];
    }

    /**
     * Get visit target data for a specific period
     */
    private function getVisitTargetForPeriod(Target $target, int $userId, string $filterPeriod, Carbon $targetMonthDate): array
    {
        [$periodStart, $periodEnd] = $this->getPeriodDates($filterPeriod, $targetMonthDate);
        
        // Count completed visits for the period
        $actual = SiteVisit::where(function($q) use ($userId) {
                $q->where('assigned_to', $userId)
                  ->orWhere('created_by', $userId);
            })
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$periodStart, $periodEnd])
            ->count();
        
        // Calculate target based on period
        $targetValue = 0;
        $periodLabel = 'weekly';
        
        switch($filterPeriod) {
            case 'today':
                // For today, still show weekly target (visits are weekly)
                $targetValue = $target->getWeeklyVisitsTarget();
                $periodLabel = 'weekly';
                break;
            case 'this_week':
                $targetValue = $target->getWeeklyVisitsTarget();
                $periodLabel = 'weekly';
                break;
            case 'this_month':
                $targetValue = $target->target_visits ?? 0;
                $periodLabel = 'monthly';
                break;
        }
        
        $percentage = $targetValue > 0 ? min(100, round(($actual / $targetValue) * 100, 1)) : 0;
        
        return [
            'actual' => $actual,
            'target' => round($targetValue),
            'monthly_target' => $target->target_visits ?? 0,
            'percentage' => $percentage,
            'period' => $periodLabel
        ];
    }

    private function getVerifiedVisitActivityForPeriod(int $userId, string $filterPeriod, Carbon $targetMonthDate): array
    {
        [$periodStart, $periodEnd] = $this->getPeriodDates($filterPeriod, $targetMonthDate);

        $actual = SiteVisit::where(function ($q) use ($userId) {
                $q->where('assigned_to', $userId)
                    ->orWhere('created_by', $userId);
            })
            ->where('verification_status', 'verified')
            ->whereNotNull('verified_at')
            ->whereBetween('verified_at', [$periodStart, $periodEnd])
            ->count();

        return [
            'actual' => $actual,
            'period' => $this->getPeriodLabel($filterPeriod, 'weekly'),
        ];
    }

    private function getPeriodLabel(string $filterPeriod, string $default): string
    {
        return match ($filterPeriod) {
            'today' => 'daily',
            'this_week' => 'weekly',
            'this_month' => 'monthly',
            default => $default,
        };
    }
}

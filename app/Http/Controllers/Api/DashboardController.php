<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Meeting;
use App\Models\SiteVisit;
use App\Models\FollowUp;
use App\Models\User;
use App\Models\Incentive;
use App\Models\Target;
use App\Services\DashboardKpiModeService;
use App\Services\TargetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    protected $targetService;
    protected $dashboardKpiModeService;

    public function __construct(TargetService $targetService, DashboardKpiModeService $dashboardKpiModeService)
    {
        $this->targetService = $targetService;
        $this->dashboardKpiModeService = $dashboardKpiModeService;
    }

    private function resolveDashboardDateRange(Request $request): array
    {
        $today = Carbon::today();
        $dateFilter = $request->get('date_filter', 'today');

        switch ($dateFilter) {
            case 'this_week':
                $startDate = $today->copy()->startOfWeek();
                $endDate = $today->copy()->endOfWeek();
                break;
            case 'this_month':
                $startDate = $today->copy()->startOfMonth();
                $endDate = $today->copy()->endOfMonth();
                break;
            case 'custom':
                $start = $request->get('start_date');
                $end = $request->get('end_date');
                if (!$start || !$end) {
                    $dateFilter = 'today';
                    $startDate = $today->copy()->startOfDay();
                    $endDate = $today->copy()->endOfDay();
                    break;
                }

                $startDate = Carbon::parse($start)->startOfDay();
                $endDate = Carbon::parse($end)->endOfDay();
                if ($startDate->gt($endDate)) {
                    [$startDate, $endDate] = [$endDate->copy()->startOfDay(), $startDate->copy()->endOfDay()];
                }
                break;
            case 'today':
            default:
                $dateFilter = 'today';
                $startDate = $today->copy()->startOfDay();
                $endDate = $today->copy()->endOfDay();
                break;
        }

        return [
            'date_filter' => $dateFilter,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'target_month' => $endDate->copy()->startOfMonth()->format('Y-m'),
        ];
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $range = $this->resolveDashboardDateRange($request);

        $data = [
            'stats' => $this->getStats($user),
            'recent_leads' => $this->getRecentLeads($user),
            'upcoming_followups' => $this->getUpcomingFollowups($user),
            'upcoming_site_visits' => $this->getUpcomingSiteVisits($user),
            'dashboard_filter' => [
                'date_filter' => $range['date_filter'],
                'start_date' => $range['start_date']->toDateString(),
                'end_date' => $range['end_date']->toDateString(),
            ],
        ];

        // Add target progress for telecallers
        if ($user->isTelecaller()) {
            $targetProgress = $this->targetService->getTargetProgress($user->id);
            $data['targets'] = $targetProgress;
            $data['incentives'] = $this->getTelecallerIncentives($user);
            $data['incentive_potential'] = $this->getTelecallerIncentivePotential($user);
        }

        // Add team target progress for managers
        if ($user->isSalesManager() || $user->isSeniorManager() || $user->isAssistantSalesManager()) {
            $teamProgress = $this->getTeamTargetsProgressForRange($user, $range);
            $data['team_targets'] = $teamProgress;
            $data['manager_targets'] = $this->getManagerTargetsVsAchievements($user, $range);
            $data['incentives'] = $this->getManagerIncentives($user, $range);
            $data['incentive_potential'] = $this->getManagerIncentivePotential($user, $range);
        }

        // Add Sales Head specific data
        if ($user->isSalesHead()) {
            $data['sales_head_data'] = $this->getSalesHeadData($user);
        }

        // Add system overview for admin/CRM
        if ($user->isAdmin() || $user->isCrm()) {
            $overview = $this->targetService->getSystemOverview();
            $data['target_overview'] = $overview;
        }

        return response()->json($data);
    }

    /**
     * Get Manager/Sales Executive incentives (closer incentives)
     */
    private function getManagerIncentives(User $user, array $range)
    {
        $incentives = Incentive::where('user_id', $user->id)
            ->where('type', 'closer')
            ->with(['siteVisit.lead', 'salesHeadVerifiedBy', 'crmVerifiedBy'])
            ->orderBy('created_at', 'desc')
            ->get();

        $incentives = $incentives->filter(function ($incentive) use ($range) {
            return $incentive->created_at
                && $incentive->created_at->between($range['start_date'], $range['end_date']);
        })->values();

        $pending = $incentives->where('status', 'pending_sales_head')
            ->concat($incentives->where('status', 'pending_crm'))
            ->concat($incentives->where('status', 'pending_finance_manager'))
            ->values();
        
        $verified = $incentives->where('status', 'verified')->values();
        $rejected = $incentives->where('status', 'rejected')->values();

        $totalEarned = $verified->sum('amount');
        $pendingAmount = $pending->sum('amount');

        return [
            'pending' => $pending->map(function ($inc) {
                return [
                    'id' => $inc->id,
                    'amount' => $inc->amount,
                    'status' => $inc->status,
                    'site_visit' => $inc->siteVisit ? [
                        'id' => $inc->siteVisit->id,
                        'customer_name' => $inc->siteVisit->customer_name ?? ($inc->siteVisit->lead->name ?? 'N/A'),
                    ] : null,
                    'created_at' => $inc->created_at ? $inc->created_at->toIso8601String() : null,
                ];
            }),
            'verified' => $verified->map(function ($inc) {
                return [
                    'id' => $inc->id,
                    'amount' => $inc->amount,
                    'status' => $inc->status,
                    'site_visit' => $inc->siteVisit ? [
                        'id' => $inc->siteVisit->id,
                        'customer_name' => $inc->siteVisit->customer_name ?? ($inc->siteVisit->lead->name ?? 'N/A'),
                    ] : null,
                    'sales_head_verified_at' => $inc->sales_head_verified_at ? $inc->sales_head_verified_at->toIso8601String() : null,
                    'crm_verified_at' => $inc->crm_verified_at ? $inc->crm_verified_at->toIso8601String() : null,
                    'finance_manager_verified_at' => $inc->finance_manager_verified_at ? $inc->finance_manager_verified_at->toIso8601String() : null,
                    'created_at' => $inc->created_at ? $inc->created_at->toIso8601String() : null,
                ];
            }),
            'rejected' => $rejected->map(function ($inc) {
                return [
                    'id' => $inc->id,
                    'amount' => $inc->amount,
                    'status' => $inc->status,
                    'rejection_reason' => $inc->rejection_reason,
                    'site_visit' => $inc->siteVisit ? [
                        'id' => $inc->siteVisit->id,
                        'customer_name' => $inc->siteVisit->customer_name ?? ($inc->siteVisit->lead->name ?? 'N/A'),
                    ] : null,
                    'created_at' => $inc->created_at ? $inc->created_at->toIso8601String() : null,
                ];
            }),
            'total_earned' => $totalEarned,
            'pending_amount' => $pendingAmount,
        ];
    }

    /**
     * Get Telecaller incentives (site visit incentives)
     */
    private function getTelecallerIncentives(User $user)
    {
        $incentives = Incentive::where('user_id', $user->id)
            ->where('type', 'site_visit')
            ->with(['siteVisit.lead', 'salesHeadVerifiedBy', 'crmVerifiedBy'])
            ->orderBy('created_at', 'desc')
            ->get();

        $pending = $incentives->where('status', 'pending_sales_head')
            ->concat($incentives->where('status', 'pending_crm'))
            ->concat($incentives->where('status', 'pending_finance_manager'))
            ->values();
        
        $verified = $incentives->where('status', 'verified')->values();
        $rejected = $incentives->where('status', 'rejected')->values();

        $totalEarned = $verified->sum('amount');
        $pendingAmount = $pending->sum('amount');

        return [
            'pending' => $pending->map(function ($inc) {
                return [
                    'id' => $inc->id,
                    'amount' => $inc->amount,
                    'status' => $inc->status,
                    'site_visit' => $inc->siteVisit ? [
                        'id' => $inc->siteVisit->id,
                        'customer_name' => $inc->siteVisit->customer_name ?? ($inc->siteVisit->lead->name ?? 'N/A'),
                    ] : null,
                    'created_at' => $inc->created_at ? $inc->created_at->toIso8601String() : null,
                ];
            }),
            'verified' => $verified->map(function ($inc) {
                return [
                    'id' => $inc->id,
                    'amount' => $inc->amount,
                    'status' => $inc->status,
                    'site_visit' => $inc->siteVisit ? [
                        'id' => $inc->siteVisit->id,
                        'customer_name' => $inc->siteVisit->customer_name ?? ($inc->siteVisit->lead->name ?? 'N/A'),
                    ] : null,
                    'sales_head_verified_at' => $inc->sales_head_verified_at ? $inc->sales_head_verified_at->toIso8601String() : null,
                    'crm_verified_at' => $inc->crm_verified_at ? $inc->crm_verified_at->toIso8601String() : null,
                    'finance_manager_verified_at' => $inc->finance_manager_verified_at ? $inc->finance_manager_verified_at->toIso8601String() : null,
                    'created_at' => $inc->created_at ? $inc->created_at->toIso8601String() : null,
                ];
            }),
            'rejected' => $rejected->map(function ($inc) {
                return [
                    'id' => $inc->id,
                    'amount' => $inc->amount,
                    'status' => $inc->status,
                    'rejection_reason' => $inc->rejection_reason,
                    'site_visit' => $inc->siteVisit ? [
                        'id' => $inc->siteVisit->id,
                        'customer_name' => $inc->siteVisit->customer_name ?? ($inc->siteVisit->lead->name ?? 'N/A'),
                    ] : null,
                    'created_at' => $inc->created_at ? $inc->created_at->toIso8601String() : null,
                ];
            }),
            'total_earned' => $totalEarned,
            'pending_amount' => $pendingAmount,
        ];
    }

    /**
     * Get Manager/Sales Executive incentive potential (target_closers × incentive_per_closer)
     */
    private function getManagerIncentivePotential(User $user, array $range)
    {
        $currentMonth = Carbon::parse($range['target_month'] . '-01')->startOfMonth();
        
        $target = Target::where('user_id', $user->id)
            ->whereYear('target_month', $currentMonth->year)
            ->whereMonth('target_month', $currentMonth->month)
            ->first();

        if (!$target || !$target->incentive_per_closer || $target->incentive_per_closer <= 0) {
            return [
                'potential' => 0,
                'target_closers' => $target->target_closers ?? 0,
                'incentive_per_closer' => 0,
            ];
        }

        $targetClosers = $target->target_closers ?? 0;
        $incentivePerCloser = $target->incentive_per_closer;
        $potential = $targetClosers * $incentivePerCloser;

        return [
            'potential' => $potential,
            'target_closers' => $targetClosers,
            'incentive_per_closer' => $incentivePerCloser,
        ];
    }

    /**
     * Get Telecaller incentive potential (target_visits × incentive_per_visit)
     */
    private function getTelecallerIncentivePotential(User $user)
    {
        $currentMonth = Carbon::now()->startOfMonth();
        
        $target = Target::where('user_id', $user->id)
            ->whereYear('target_month', $currentMonth->year)
            ->whereMonth('target_month', $currentMonth->month)
            ->first();

        if (!$target || !$target->incentive_per_visit || $target->incentive_per_visit <= 0) {
            return [
                'potential' => 0,
                'target_visits' => $target->target_visits ?? 0,
                'incentive_per_visit' => 0,
            ];
        }

        $targetVisits = $target->target_visits ?? 0;
        $incentivePerVisit = $target->incentive_per_visit;
        $potential = $targetVisits * $incentivePerVisit;

        return [
            'potential' => $potential,
            'target_visits' => $targetVisits,
            'incentive_per_visit' => $incentivePerVisit,
        ];
    }

    /**
     * Get Manager's own target vs achievements
     */
    private function getManagerTargetsVsAchievements(User $user, array $range)
    {
        $resolvedMode = $this->dashboardKpiModeService->resolveModeForUser($user);
        $currentMonth = Carbon::parse($range['target_month'] . '-01')->startOfMonth();
        
        $target = Target::where('user_id', $user->id)
            ->whereYear('target_month', $currentMonth->year)
            ->whereMonth('target_month', $currentMonth->month)
            ->first();

        $meetingsAchieved = $this->countUserMeetingsForRange($user, $range);
        $visitsAchieved = $this->countUserVisitsForRange($user, $range);
        $closersAchieved = $this->countUserClosersForRange($user, $range);

        if ($resolvedMode === DashboardKpiModeService::ACTIVITY_BASED) {
            return [
                'meetings' => $this->formatActivityMetric('Completed Meetings', $meetingsAchieved, 'Completed'),
                'visits' => $this->formatActivityMetric('Verified Visits', $visitsAchieved, 'Verified'),
                'closers' => $this->formatActivityMetric('Verified Closers', $closersAchieved, 'Verified Closers'),
            ];
        }

        if (!$target) {
            return [
                'meetings' => $this->formatTargetMetric('Meetings', 0, $meetingsAchieved),
                'visits' => $this->formatTargetMetric('Verified Visits', 0, $visitsAchieved),
                'closers' => $this->formatTargetMetric('Closers', 0, $closersAchieved),
            ];
        }

        return [
            'meetings' => $this->formatTargetMetric('Meetings', (int) ($target->target_meetings ?? 0), $meetingsAchieved),
            'visits' => $this->formatTargetMetric('Verified Visits', (int) ($target->target_visits ?? 0), $visitsAchieved),
            'closers' => $this->formatTargetMetric('Closers', (int) ($target->target_closers ?? 0), $closersAchieved),
        ];
    }

    private function formatTargetMetric(string $label, int $target, int $achieved): array
    {
        $percentage = $target > 0 ? min(100, round(($achieved / $target) * 100, 2)) : 0;

        return [
            'mode' => DashboardKpiModeService::TARGET_BASED,
            'label' => $label,
            'target' => $target,
            'achieved' => $achieved,
            'percentage' => $percentage,
            'subtitle' => $target > 0 ? 'Target Based' : 'Target not set',
            'has_target' => $target > 0,
        ];
    }

    private function formatActivityMetric(string $label, int $achieved, string $subtitle): array
    {
        return [
            'mode' => DashboardKpiModeService::ACTIVITY_BASED,
            'label' => $label,
            'target' => null,
            'achieved' => $achieved,
            'percentage' => null,
            'subtitle' => $subtitle,
            'has_target' => false,
        ];
    }

    private function countUserMeetingsForRange(User $user, array $range): int
    {
        return Meeting::where(function ($query) use ($user) {
            $query->where('created_by', $user->id)
                ->orWhere('assigned_to', $user->id)
                ->orWhereHas('lead', function ($leadQuery) use ($user) {
                    $leadQuery->whereHas('activeAssignments', function ($assignmentQuery) use ($user) {
                        $assignmentQuery->where('assigned_to', $user->id);
                    });
                });
        })
        ->where('status', 'completed')
        ->where('is_converted', false)
        ->where(function ($query) {
            $query->where(function ($verifiedQuery) {
                $verifiedQuery->where('verification_status', 'verified')
                    ->whereNotNull('verified_at');
            })->orWhere(function ($legacyQuery) {
                $legacyQuery->whereNotNull('completed_at')
                    ->where(function ($statusQuery) {
                        $statusQuery->whereNull('verification_status')
                            ->orWhere('verification_status', '!=', 'rejected');
                    });
            });
        })
        ->where(function ($query) use ($range) {
            $query->whereBetween('verified_at', [$range['start_date'], $range['end_date']])
                ->orWhere(function ($fallbackQuery) use ($range) {
                    $fallbackQuery->whereNull('verified_at')
                        ->whereBetween('completed_at', [$range['start_date'], $range['end_date']]);
                });
        })
        ->when(\Illuminate\Support\Facades\Schema::hasColumn('meetings', 'is_rescheduled'), function ($query) {
            $query->where('is_rescheduled', false);
        })
        ->count();
    }

    private function countUserVisitsForRange(User $user, array $range): int
    {
        return SiteVisit::where(function ($query) use ($user) {
            $query->where('created_by', $user->id)
                ->orWhere('assigned_to', $user->id)
                ->orWhereHas('lead', function ($leadQuery) use ($user) {
                    $leadQuery->whereHas('activeAssignments', function ($assignmentQuery) use ($user) {
                        $assignmentQuery->where('assigned_to', $user->id);
                    });
                });
        })
        ->where('verification_status', 'verified')
        ->whereNotNull('verified_at')
        ->whereBetween('verified_at', [$range['start_date'], $range['end_date']])
        ->when(\Illuminate\Support\Facades\Schema::hasColumn('site_visits', 'is_rescheduled'), function ($query) {
            $query->where('is_rescheduled', false);
        })
        ->count();
    }

    private function countUserClosersForRange(User $user, array $range): int
    {
        return SiteVisit::where(function ($query) use ($user) {
            $query->where('created_by', $user->id)
                ->orWhere('assigned_to', $user->id)
                ->orWhereHas('lead', function ($leadQuery) use ($user) {
                    $leadQuery->whereHas('activeAssignments', function ($assignmentQuery) use ($user) {
                        $assignmentQuery->where('assigned_to', $user->id);
                    });
                });
        })
                    ->whereIn('closer_status', ['approved', 'verified'])
        ->where(function ($dateQuery) use ($range) {
            if (\Illuminate\Support\Facades\Schema::hasColumn('site_visits', 'actual_closer_date')) {
                $dateQuery->whereBetween('actual_closer_date', [
                    \Carbon\Carbon::parse($range['start_date'])->toDateString(),
                    \Carbon\Carbon::parse($range['end_date'])->toDateString(),
                ])->orWhere(function ($legacyQuery) use ($range) {
                    $legacyQuery->whereNull('actual_closer_date')
                        ->whereNotNull('closer_verified_at')
                        ->whereBetween('closer_verified_at', [$range['start_date'], $range['end_date']]);
                });
                return;
            }

            $dateQuery->whereNotNull('closer_verified_at')
                ->whereBetween('closer_verified_at', [$range['start_date'], $range['end_date']]);
        })
        ->when(\Illuminate\Support\Facades\Schema::hasColumn('site_visits', 'is_rescheduled'), function ($query) {
            $query->where('is_rescheduled', false);
        })
        ->count();
    }

    private function getTeamTargetsProgressForRange(User $user, array $range): array
    {
        $resolvedMode = $this->dashboardKpiModeService->resolveModeForUser($user);
        $month = $range['target_month'];
        $targetMonth = Carbon::parse($month . '-01')->startOfMonth();

        $teamMembers = $user->getAllTeamMembers()
            ->filter(function (User $member) {
                return $member->isSalesExecutive();
            })
            ->values();

        $targets = Target::whereIn('user_id', $teamMembers->pluck('id'))
            ->where('target_month', $targetMonth)
            ->get()
            ->keyBy('user_id');

        $teamMembersData = [];
        $teamMeetingsTarget = 0;
        $teamVisitsTarget = 0;
        $teamClosersTarget = 0;
        $teamMeetingsAchieved = 0;
        $teamVisitsAchieved = 0;
        $teamClosersAchieved = 0;

        foreach ($teamMembers as $member) {
            $target = $targets->get($member->id);
            $meetingsTarget = (int) ($target->target_meetings ?? 0);
            $visitsTarget = (int) ($target->target_visits ?? 0);
            $closersTarget = (int) ($target->target_closers ?? 0);
            $meetingsAchieved = $this->countUserMeetingsForRange($member, $range);
            $visitsAchieved = $this->countUserVisitsForRange($member, $range);
            $closersAchieved = $this->countUserClosersForRange($member, $range);

            $teamMeetingsTarget += $meetingsTarget;
            $teamVisitsTarget += $visitsTarget;
            $teamClosersTarget += $closersTarget;
            $teamMeetingsAchieved += $meetingsAchieved;
            $teamVisitsAchieved += $visitsAchieved;
            $teamClosersAchieved += $closersAchieved;

            $teamMembersData[] = [
                'user_id' => $member->id,
                'user_name' => $member->name ?? 'N/A',
                'user_role' => $member->role->slug ?? 'N/A',
                'user_role_name' => $member->role->name ?? 'N/A',
                'targets' => [
                    'meetings' => $resolvedMode === DashboardKpiModeService::ACTIVITY_BASED
                        ? $this->formatActivityMetric('Completed Meetings', $meetingsAchieved, 'Completed')
                        : $this->formatTargetMetric('Meetings', $meetingsTarget, $meetingsAchieved),
                    'visits' => $resolvedMode === DashboardKpiModeService::ACTIVITY_BASED
                        ? $this->formatActivityMetric('Verified Visits', $visitsAchieved, 'Verified')
                        : $this->formatTargetMetric('Verified Visits', $visitsTarget, $visitsAchieved),
                    'closers' => $resolvedMode === DashboardKpiModeService::ACTIVITY_BASED
                        ? $this->formatActivityMetric('Verified Closers', $closersAchieved, 'Verified Closers')
                        : $this->formatTargetMetric('Closers', $closersTarget, $closersAchieved),
                ],
            ];
        }

        return [
            'team_totals' => [
                'meetings' => $resolvedMode === DashboardKpiModeService::ACTIVITY_BASED
                    ? $this->formatActivityMetric('Completed Meetings', $teamMeetingsAchieved, 'Completed')
                    : $this->formatTargetMetric('Meetings', $teamMeetingsTarget, $teamMeetingsAchieved),
                'visits' => $resolvedMode === DashboardKpiModeService::ACTIVITY_BASED
                    ? $this->formatActivityMetric('Verified Visits', $teamVisitsAchieved, 'Verified')
                    : $this->formatTargetMetric('Verified Visits', $teamVisitsTarget, $teamVisitsAchieved),
                'closers' => $resolvedMode === DashboardKpiModeService::ACTIVITY_BASED
                    ? $this->formatActivityMetric('Verified Closers', $teamClosersAchieved, 'Verified Closers')
                    : $this->formatTargetMetric('Closers', $teamClosersTarget, $teamClosersAchieved),
            ],
            'team_members' => $teamMembersData,
        ];
    }

    private function getStats($user)
    {
        $leadQuery = Lead::query();
        $siteVisitQuery = SiteVisit::query();
        $followUpQuery = FollowUp::query();

        // Apply role-based filtering
        if ($user->isSalesExecutive() || $user->isAssistantSalesManager()) {
            $leadIds = Lead::whereHas('activeAssignments', function ($q) use ($user) {
                $q->where('assigned_to', $user->id);
            })->pluck('id');

            $leadQuery->whereIn('id', $leadIds);
            $siteVisitQuery->where('assigned_to', $user->id);
            $followUpQuery->where('created_by', $user->id);
        } elseif ($user->isSalesHead()) {
            // Sales Head sees all team data including nested teams
            $allTeamMemberIds = $user->getAllTeamMemberIds();
            if (!empty($allTeamMemberIds)) {
                $leadIds = Lead::whereHas('activeAssignments', function ($q) use ($allTeamMemberIds) {
                    $q->whereIn('assigned_to', $allTeamMemberIds);
                })->pluck('id');

                $leadQuery->whereIn('id', $leadIds);
                $siteVisitQuery->whereIn('assigned_to', $allTeamMemberIds);
                $followUpQuery->whereIn('created_by', $allTeamMemberIds);
            } else {
                // No team members, return empty results
                $leadQuery->whereRaw('1 = 0');
                $siteVisitQuery->whereRaw('1 = 0');
                $followUpQuery->whereRaw('1 = 0');
            }
        } elseif ($user->isSalesManager()) {
            $teamMemberIds = $user->teamMembers()->pluck('id');
            // Only get leads from verified prospects of team members
            $leadIds = Lead::whereHas('prospects', function ($q) use ($teamMemberIds) {
                $q->whereIn('telecaller_id', $teamMemberIds)
                  ->whereIn('verification_status', ['verified', 'approved']);
            })->pluck('id');

            $leadQuery->whereIn('id', $leadIds);
            $siteVisitQuery->whereIn('assigned_to', $teamMemberIds);
            $followUpQuery->whereIn('created_by', $teamMemberIds);
        }

        return [
            'total_leads' => $leadQuery->count(),
            'new_leads' => (clone $leadQuery)->where('status', 'new')->count(),
            'qualified_leads' => (clone $leadQuery)->where('status', 'qualified')->count(),
            'closed_won' => (clone $leadQuery)->where('status', 'closed_won')->count(),
            'upcoming_site_visits' => $siteVisitQuery->where('status', 'scheduled')
                ->where('scheduled_at', '>=', now())
                ->count(),
            'pending_followups' => $followUpQuery->where('status', 'scheduled')
                ->where('scheduled_at', '>=', now())
                ->count(),
        ];
    }

    private function getRecentLeads($user, $limit = 5)
    {
        $query = Lead::with(['creator', 'activeAssignments.assignedTo']);

        if ($user->isSalesExecutive() || $user->isAssistantSalesManager()) {
            $query->whereHas('activeAssignments', function ($q) use ($user) {
                $q->where('assigned_to', $user->id);
            });
        } elseif ($user->isSalesHead()) {
            $allTeamMemberIds = $user->getAllTeamMemberIds();
            if (!empty($allTeamMemberIds)) {
                $query->whereHas('activeAssignments', function ($q) use ($allTeamMemberIds) {
                    $q->whereIn('assigned_to', $allTeamMemberIds);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($user->isSalesManager()) {
            $teamMemberIds = $user->teamMembers()->pluck('id');
            $query->whereHas('activeAssignments', function ($q) use ($teamMemberIds) {
                $q->whereIn('assigned_to', $teamMemberIds);
            });
        }

        return $query->latest()->limit($limit)->get();
    }

    private function getUpcomingFollowups($user, $limit = 5)
    {
        $query = FollowUp::with(['lead', 'creator'])
            ->where('status', 'scheduled')
            ->where('scheduled_at', '>=', now());

        if ($user->isSalesExecutive() || $user->isAssistantSalesManager()) {
            $query->where('created_by', $user->id);
        } elseif ($user->isSalesHead()) {
            $allTeamMemberIds = $user->getAllTeamMemberIds();
            if (!empty($allTeamMemberIds)) {
                $query->whereIn('created_by', $allTeamMemberIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($user->isSalesManager()) {
            $teamMemberIds = $user->teamMembers()->pluck('id');
            $query->whereIn('created_by', $teamMemberIds);
        }

        return $query->orderBy('scheduled_at')->limit($limit)->get();
    }

    private function getUpcomingSiteVisits($user, $limit = 5)
    {
        $query = SiteVisit::with(['lead', 'assignedTo'])
            ->where('status', 'scheduled')
            ->where('scheduled_at', '>=', now());

        if ($user->isSalesExecutive() || $user->isAssistantSalesManager()) {
            $query->where('assigned_to', $user->id);
        } elseif ($user->isSalesHead()) {
            $allTeamMemberIds = $user->getAllTeamMemberIds();
            if (!empty($allTeamMemberIds)) {
                $query->whereIn('assigned_to', $allTeamMemberIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($user->isSalesManager()) {
            $teamMemberIds = $user->teamMembers()->pluck('id');
            $query->whereIn('assigned_to', $teamMemberIds);
        }

        return $query->orderBy('scheduled_at')->limit($limit)->get();
    }

    /**
     * Get Sales Head specific data
     */
    private function getSalesHeadData($user)
    {
        $allTeamMemberIds = $user->getAllTeamMemberIds();
        
        // Get all Senior Managers
        $salesManagers = User::where('manager_id', $user->id)
            ->whereHas('role', function($q) {
                $q->where('slug', 'sales_manager');
            })
            ->count();

        // Get all Sales Executives
        $salesExecutives = User::whereIn('manager_id', array_merge([$user->id], User::where('manager_id', $user->id)->pluck('id')->toArray()))
            ->whereHas('role', function($q) {
                $q->where('slug', 'sales_executive');
            })
            ->count();

        // Get all Sales Executives (team)
        $telecallers = User::whereIn('manager_id', array_merge([$user->id], $allTeamMemberIds))
            ->whereHas('role', function($q) {
                $q->where('slug', \App\Models\Role::SALES_EXECUTIVE);
            })
            ->count();

        return [
            'total_managers' => $salesManagers,
            'total_executives' => $salesExecutives,
            'total_telecallers' => $telecallers,
            'pending_verifications' => Lead::where('needs_verification', true)->count(),
        ];
    }
}

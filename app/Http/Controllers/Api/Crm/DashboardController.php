<?php

namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Controller;
use App\Models\AsmCnpAutomationAudit;
use App\Models\CrmAssignment;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\Meeting;
use App\Models\Prospect;
use App\Models\SiteVisit;
use App\Models\Task;
use App\Models\TelecallerTask;
use App\Models\User;
use App\Models\TelecallerDailyLimit;
use App\Models\TelecallerProfile;
use App\Models\Role;
use App\Models\UserProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\DashboardResponseTimeService;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardResponseTimeService $dashboardResponseTimeService
    ) {
    }

    /**
     * Get date range based on filter type
     */
    private function getDateRange($dateRange, ?Request $request = null)
    {
        $today = Carbon::today();
        
        switch ($dateRange) {
            case 'today':
                return [$today->copy()->startOfDay(), $today->copy()->endOfDay()];
            case 'yesterday':
                $yesterday = $today->copy()->subDay();
                return [$yesterday->startOfDay(), $yesterday->endOfDay()];
            case 'this_week':
                return [$today->copy()->startOfWeek(), $today->copy()->endOfWeek()];
            case 'this_month':
                return [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()];
            case 'this_year':
                return [$today->copy()->startOfYear(), $today->copy()->endOfYear()];
            case 'custom':
                if ($request && $request->has('start_date') && $request->has('end_date')) {
                    $start = Carbon::parse($request->get('start_date'))->startOfDay();
                    $end = Carbon::parse($request->get('end_date'))->endOfDay();
                    return [$start, $end];
                }
                return [null, null];
            case 'till_date':
            case 'all_time':
            default:
                return [null, null];
        }
    }

    /**
     * Get top 4 stats cards
     */
    public function getStats(Request $request)
    {
        $dateRange = $request->get('date_range', 'all_time');
        [$startDate, $endDate] = $this->getDateRange($dateRange, $request);

        // Total Assigned Leads: Count of all active LeadAssignment records
        $totalAssignedQuery = LeadAssignment::where('is_active', true);
        if ($startDate && $endDate) {
            $totalAssignedQuery->whereBetween('assigned_at', [$startDate, $endDate]);
        }
        $totalAssigned = $totalAssignedQuery->count();

        // Called Leads: Count of all completed TelecallerTask records
        $calledQuery = TelecallerTask::where('status', 'completed');
        if ($startDate && $endDate) {
            $calledQuery->whereBetween('completed_at', [$startDate, $endDate]);
        }
        $called = $calledQuery->count();

        // Interested: Count of verified/approved prospects
        $interestedQuery = Prospect::whereIn('verification_status', ['verified', 'approved']);
        if ($startDate && $endDate) {
            $interestedQuery->whereBetween('verified_at', [$startDate, $endDate]);
        }
        $interested = $interestedQuery->count();

        // Not Interested: Sum of called_not_interested in CrmAssignment + rejected prospects
        $notInterestedCrmQuery = CrmAssignment::where('call_status', 'called_not_interested');
        if ($startDate && $endDate) {
            $notInterestedCrmQuery->whereBetween('assigned_at', [$startDate, $endDate]);
        }
        $notInterestedCrm = $notInterestedCrmQuery->count();

        $notInterestedProspectsQuery = Prospect::where('verification_status', 'rejected');
        if ($startDate && $endDate) {
            $notInterestedProspectsQuery->whereBetween('verified_at', [$startDate, $endDate]);
        }
        $notInterestedProspects = $notInterestedProspectsQuery->count();

        $notInterested = $notInterestedCrm + $notInterestedProspects;

        return response()->json([
            'total_assigned' => $totalAssigned,
            'called' => $called,
            'not_interested' => $notInterested,
            'interested' => $interested,
        ]);
    }

    public function clearDashboardCache()
    {
        try {
            Artisan::call('optimize:clear');

            return response()->json([
                'success' => true,
                'message' => 'Dashboard cache cleared successfully.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to clear CRM dashboard cache: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to clear dashboard cache.',
            ], 500);
        }
    }

    /**
     * Roles for Sales Executive Performance filter (exclude Admin, CRM)
     */
    public function getPerformanceFilterRoles()
    {
        $roles = Role::where('is_active', true)
            ->whereNotIn('slug', [Role::ADMIN, Role::CRM])
            ->get(['id', 'name', 'slug']);
        return response()->json($roles);
    }

    /**
     * Get telecaller performance stats
     */
    public function getTelecallerStats(Request $request)
    {
        try {
            $dateRange = $request->get('date_range', 'this_month');
            [$startDate, $endDate] = $this->getDateRange($dateRange, $request);
            $roleSlug = $request->get('role_slug', 'all');

            // Sab users dikhane chahiye except Admin, CRM aur Sale Head
            $users = User::with('role')
                ->whereHas('role', function ($q) {
                    $q->whereNotIn('slug', [Role::ADMIN, Role::CRM]);
                })
                ->get()
                ->filter(function ($user) {
                    if ($user->role->slug === Role::SALES_MANAGER && $user->manager_id === null) {
                        return false; // Sale Head - exclude
                    }
                    return true;
                });

            // Filter by role if selected
            if ($roleSlug && $roleSlug !== 'all') {
                $users = $users->filter(function ($user) use ($roleSlug) {
                    return $user->role->slug === $roleSlug;
                });
            }

            $users = $users->values();

            if ($users->isEmpty()) {
                return response()->json([]);
            }

            $result = [];

            foreach ($users as $telecaller) {
                try {
                    $userId = $telecaller->id;

                    // assigned = LeadAssignment count
                    $assignedQuery = LeadAssignment::where('assigned_to', $userId)->where('is_active', true);
                    if ($startDate && $endDate) {
                        $assignedQuery->whereBetween('assigned_at', [$startDate, $endDate]);
                    }
                    $assigned = $assignedQuery->count();

                    // follow_up = FollowUp by this user (created_by), date on scheduled_at or created_at
                    $followUpQuery = FollowUp::where('created_by', $userId);
                    if ($startDate && $endDate) {
                        $followUpQuery->where(function ($q) use ($startDate, $endDate) {
                            $q->whereBetween('scheduled_at', [$startDate, $endDate])
                                ->orWhereBetween('created_at', [$startDate, $endDate]);
                        });
                    }
                    $follow_up = $followUpQuery->count();

                    // meetings = Meeting assigned_to this user
                    $meetingsQuery = Meeting::where('assigned_to', $userId);
                    if ($startDate && $endDate) {
                        $meetingsQuery->whereBetween('scheduled_at', [$startDate, $endDate]);
                    }
                    $meetings = $meetingsQuery->count();

                    // visits = SiteVisit assigned_to this user
                    $visitsQuery = SiteVisit::where('assigned_to', $userId);
                    if ($startDate && $endDate) {
                        $visitsQuery->whereBetween('scheduled_at', [$startDate, $endDate]);
                    }
                    $visits = $visitsQuery->count();

                    // closer = SiteVisit assigned_to, closer_status = verified
                    $closerQuery = SiteVisit::where('assigned_to', $userId)->whereIn('closer_status', ['approved', 'verified']);
                    if ($startDate && $endDate) {
                        $closerQuery->where(function ($dateQuery) use ($startDate, $endDate) {
                            if (\Illuminate\Support\Facades\Schema::hasColumn('site_visits', 'actual_closer_date')) {
                                $dateQuery->whereBetween('actual_closer_date', [
                                    \Carbon\Carbon::parse($startDate)->toDateString(),
                                    \Carbon\Carbon::parse($endDate)->toDateString(),
                                ])->orWhere(function ($legacyQuery) use ($startDate, $endDate) {
                                    $legacyQuery->whereNull('actual_closer_date')
                                        ->whereBetween('closer_verified_at', [$startDate, $endDate]);
                                });
                                return;
                            }

                            $dateQuery->whereBetween('closer_verified_at', [$startDate, $endDate]);
                        });
                    }
                    $closer = $closerQuery->count();

                    $junkQuery = Lead::where('status', 'junk')
                        ->where('other_lead_marked_by', $userId);
                    if ($startDate && $endDate) {
                        $junkQuery->whereBetween('other_lead_marked_at', [$startDate, $endDate]);
                    }
                    $junk = $junkQuery->count();

                    $notInterestedLeadQuery = Lead::where('status', 'not_interested')
                        ->where('other_lead_marked_by', $userId);
                    if ($startDate && $endDate) {
                        $notInterestedLeadQuery->whereBetween('other_lead_marked_at', [$startDate, $endDate]);
                    }
                    $not_interested = $notInterestedLeadQuery->count();

                    // pending_tasks = TelecallerTask assigned_to, status pending or rescheduled
                    $pendingTasksQuery = TelecallerTask::where('assigned_to', $userId)
                        ->whereIn('status', ['pending', 'rescheduled']);
                    $pending_tasks = $pendingTasksQuery->count();

                    // overdue_tasks = same but scheduled_at < now
                    $overdueTasksQuery = TelecallerTask::where('assigned_to', $userId)
                        ->whereIn('status', ['pending', 'rescheduled'])
                        ->where('scheduled_at', '<', now());
                    $overdue_tasks = $overdueTasksQuery->count();

                    $result[] = [
                        'telecaller_id' => $userId,
                        'telecaller_name' => $telecaller->name,
                        'username' => $telecaller->name,
                        'assigned' => $assigned,
                        'follow_up' => $follow_up,
                        'meetings' => $meetings,
                        'visits' => $visits,
                        'closer' => $closer,
                        'junk' => $junk,
                        'not_interested' => $not_interested,
                        'pending_tasks' => $pending_tasks,
                        'overdue_tasks' => $overdue_tasks,
                    ];
                } catch (\Exception $e) {
                    Log::error('Error processing telecaller stats for user ' . $telecaller->id . ': ' . $e->getMessage());
                    continue;
                }
            }

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Error in getTelecallerStats: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load telecaller stats: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get user-wise leads allocated but not yet responded (no call outcome).
     * Same "remaining" logic as admin getLeadsPendingResponseByUser.
     */
    public function getLeadsPendingResponse(Request $request)
    {
        try {
            $dateRange = $request->get('date_range', 'this_month');
            [$startDate, $endDate] = $this->getDateRange($dateRange, $request);
            return response()->json([
                'data' => $this->buildPendingResponseRows($startDate, $endDate),
                'server_now' => now()->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getLeadsPendingResponse: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getLeadOperationsSummary(Request $request)
    {
        try {
            $dateRange = $request->get('date_range', 'this_month');
            [$startDate, $endDate] = $this->getDateRange($dateRange, $request);

            $pendingRows = collect($this->buildNewLeadsNotCompletedRows($startDate, $endDate));
            $avgResponseMap = collect($this->dashboardResponseTimeService->getAverageResponseTimeByUser($startDate, $endDate))
                ->keyBy('user_id');

            $data = $pendingRows
                ->map(function (array $row) use ($avgResponseMap) {
                    return [
                        'user_id' => $row['user_id'],
                        'user_name' => $row['user_name'],
                        'pending_new_count' => $row['pending_new_count'] ?? 0,
                        'oldest_assigned_at' => $row['oldest_assigned_at'] ?? null,
                        'avg_response_minutes' => (float) ($avgResponseMap->get($row['user_id'])['avg_response_minutes'] ?? 0),
                        'leads' => $row['leads'] ?? [],
                    ];
                })
                ->sort(function (array $a, array $b) {
                    $countCompare = ($b['pending_new_count'] ?? 0) <=> ($a['pending_new_count'] ?? 0);
                    if ($countCompare !== 0) {
                        return $countCompare;
                    }

                    return strcasecmp((string) ($a['user_name'] ?? ''), (string) ($b['user_name'] ?? ''));
                })
                ->values()
                ->all();

            return response()->json([
                'data' => $data,
                'server_now' => now()->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getLeadOperationsSummary: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getNewLeadsNotCompleted(Request $request)
    {
        try {
            $dateRange = $request->get('date_range', 'this_month');
            [$startDate, $endDate] = $this->getDateRange($dateRange, $request);

            return response()->json([
                'data' => $this->buildNewLeadsNotCompletedRows($startDate, $endDate),
                'server_now' => now()->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getNewLeadsNotCompleted: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getPreviousOverPod(Request $request)
    {
        try {
            return response()->json([
                'data' => $this->buildPreviousOverPodRows(),
                'server_now' => now()->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getPreviousOverPod: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get user-wise average lead response time (assign to first response) for the date range.
     * Same logic as Admin getAverageResponseTimeByUser.
     */
    public function getAverageResponseTime(Request $request)
    {
        try {
            $dateRange = $request->get('date_range', 'this_month');
            [$startDate, $endDate] = $this->getDateRange($dateRange, $request);
            $result = $this->dashboardResponseTimeService->getAverageResponseTimeByUser($startDate, $endDate);

            return response()->json([
                'data' => $result,
                'server_now' => now()->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getAverageResponseTime: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getLeadAllocationOverview()
    {
        $eligibleRoleIds = Role::whereIn('slug', [
            Role::SALES_EXECUTIVE,
            Role::SALES_MANAGER,
            Role::ASSISTANT_SALES_MANAGER,
        ])->pluck('id');

        $eligibleUserIds = User::whereIn('role_id', $eligibleRoleIds)
            ->where('is_active', true)
            ->pluck('id');

        $offProfiles = UserProfile::whereIn('user_id', $eligibleUserIds)
            ->where('is_absent', true)
            ->get();

        return response()->json([
            'lead_off_users' => $offProfiles->filter(fn ($profile) => $profile->isCurrentlyAbsent())->count(),
            'returning_today' => $offProfiles->filter(fn ($profile) => $profile->returnsToday())->count(),
            'scheduled_off' => $offProfiles->filter(fn ($profile) => $profile->hasUpcomingLeadOffWindow())->count(),
            'control_url' => route('lead-assignment.lead-off-users'),
        ]);
    }

    public function getSourceDistribution(Request $request)
    {
        $dateRange = $request->get('date_range', 'all_time');
        [$startDate, $endDate] = $this->getDateRange($dateRange, $request);

        $rows = Lead::query()
            ->select('source', DB::raw('COUNT(*) as total'))
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->groupBy('source')
            ->get();

        $distribution = $rows
            ->reduce(function (array $carry, Lead $lead) {
                $label = Lead::displaySourceLabel($lead->source);
                $carry[$label] = ($carry[$label] ?? 0) + (int) ($lead->total ?? 0);
                return $carry;
            }, []);

        $data = collect($distribution)
            ->map(fn (int $value, string $source) => [
                'source' => $source,
                'value' => $value,
            ])
            ->sortByDesc('value')
            ->values()
            ->all();

        return response()->json($data);
    }

    public function getRecentLeads(Request $request)
    {
        try {
            $dateRange = $request->get('date_range', 'this_month');
            [$startDate, $endDate] = $this->getDateRange($dateRange, $request);

            $leads = Lead::query()
                ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('created_at', [$startDate, $endDate]);
                })
                ->with([
                    'activeAssignments' => function ($query) {
                        $query->with('assignedTo:id,name')
                            ->orderByDesc('assigned_at')
                            ->orderByDesc('id');
                    },
                ])
                ->latest('created_at')
                ->limit(20)
                ->get(['id', 'name', 'status', 'created_at']);

            $leadIds = $leads->pluck('id')->all();

            $telecallerOutcomeMap = TelecallerTask::query()
                ->whereIn('lead_id', $leadIds)
                ->whereNotNull('outcome')
                ->where('outcome', '!=', '')
                ->orderByDesc('completed_at')
                ->orderByDesc('updated_at')
                ->orderByDesc('created_at')
                ->get(['lead_id', 'outcome'])
                ->unique('lead_id')
                ->keyBy('lead_id');

            $managerOutcomeMap = Task::query()
                ->whereIn('lead_id', $leadIds)
                ->whereNotNull('outcome')
                ->where('outcome', '!=', '')
                ->orderByDesc('outcome_recorded_at')
                ->orderByDesc('completed_at')
                ->orderByDesc('updated_at')
                ->orderByDesc('created_at')
                ->get(['lead_id', 'outcome'])
                ->unique('lead_id')
                ->keyBy('lead_id');

            $data = $leads->map(function (Lead $lead) use ($telecallerOutcomeMap, $managerOutcomeMap) {
                $activeAssignment = $lead->activeAssignments->first();
                $ownerName = $activeAssignment?->assignedTo?->name ?? 'Unassigned';
                $statusLabel = $this->formatLeadStatusLabel($lead->status);
                $telecallerOutcome = $telecallerOutcomeMap->get($lead->id)?->outcome;
                $managerOutcome = $managerOutcomeMap->get($lead->id)?->outcome;
                $outcome = $telecallerOutcome ?: ($managerOutcome ?: 'No Outcome');

                return [
                    'lead_id' => $lead->id,
                    'lead_name' => $lead->name,
                    'owner_name' => $ownerName,
                    'status' => $lead->status,
                    'status_label' => $statusLabel,
                    'outcome' => $outcome,
                    'outcome_label' => $this->formatLeadStatusLabel($outcome),
                    'created_at' => $lead->created_at?->toIso8601String(),
                ];
            })->values()->all();

            return response()->json([
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getRecentLeads: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getFreshLeadCnpSummary(Request $request)
    {
        try {
            $dateRange = $request->get('date_range', 'this_month');
            [$startDate, $endDate] = $this->getDateRange($dateRange, $request);
            $roleSlug = $request->get('role_slug', 'all');

            $users = $this->getEligibleDashboardUsers();
            if ($roleSlug && $roleSlug !== 'all') {
                $users = $users->filter(function ($user) use ($roleSlug) {
                    return ($user->role->slug ?? null) === $roleSlug;
                })->values();
            }

            if ($users->isEmpty()) {
                return response()->json(['data' => []]);
            }

            $eligibleUserIds = $users->pluck('id')->all();
            $auditCount = AsmCnpAutomationAudit::query()->count();

            $data = $auditCount > 0
                ? $this->buildFreshLeadCnpSummaryFromAudits($eligibleUserIds, $users, $startDate, $endDate)
                : $this->buildFreshLeadCnpSummaryFromTasks($eligibleUserIds, $users, $startDate, $endDate);

            return response()->json([
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getFreshLeadCnpSummary: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load fresh lead CNP summary.'], 500);
        }
    }

    private function buildFreshLeadCnpSummaryFromAudits(array $eligibleUserIds, $users, $startDate, $endDate): array
    {
        $audits = AsmCnpAutomationAudit::query()
            ->with([
                'lead' => function ($query) {
                    $query->select('id', 'name', 'phone')
                        ->with([
                            'activeAssignments' => function ($assignmentQuery) {
                                $assignmentQuery->with('assignedTo:id,name')
                                    ->orderByDesc('assigned_at')
                                    ->orderByDesc('id');
                            },
                        ]);
                },
                'fromUser:id,name',
            ])
            ->where('action', 'retry_created')
            ->whereIn('from_user_id', $eligibleUserIds)
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('acted_at', [$startDate, $endDate]);
            })
            ->orderByDesc('acted_at')
            ->orderByDesc('id')
            ->get();

        $grouped = [];

        foreach ($audits as $audit) {
            $userId = $audit->from_user_id;
            $lead = $audit->lead;

            if (!$userId || !$lead) {
                continue;
            }

            if (!isset($grouped[$userId])) {
                $grouped[$userId] = [
                    'user_id' => $userId,
                    'user_name' => $audit->fromUser?->name ?? ($users->firstWhere('id', $userId)?->name ?? 'Unknown'),
                    'cnp_total' => 0,
                    'last_cnp_at' => null,
                    'leads' => [],
                ];
            }

            $grouped[$userId]['cnp_total']++;

            $actedAtIso = $audit->acted_at?->toIso8601String();
            if (!$grouped[$userId]['last_cnp_at'] || ($actedAtIso && $actedAtIso > $grouped[$userId]['last_cnp_at'])) {
                $grouped[$userId]['last_cnp_at'] = $actedAtIso;
            }

            $leadKey = (string) $lead->id;
            if (isset($grouped[$userId]['leads'][$leadKey])) {
                continue;
            }

            $currentOwner = $lead->activeAssignments->first()?->assignedTo?->name ?? 'Unassigned';

            $grouped[$userId]['leads'][$leadKey] = [
                'lead_id' => $lead->id,
                'lead_name' => $lead->name,
                'phone' => $lead->phone,
                'cnp_number' => (int) ($audit->cnp_count ?? 0),
                'last_cnp_at' => $actedAtIso,
                'current_owner_name' => $currentOwner,
                'cnp_marked_by_name' => $audit->fromUser?->name ?? ($grouped[$userId]['user_name'] ?? 'Unknown'),
            ];
        }

        return $this->formatFreshLeadCnpSummaryRows($grouped);
    }

    private function buildFreshLeadCnpSummaryFromTasks(array $eligibleUserIds, $users, $startDate, $endDate): array
    {
        $freshLeadStatusesToExclude = [
            'verified_prospect',
            'meeting_scheduled',
            'meeting_completed',
            'visit_scheduled',
            'visit_done',
            'revisited_scheduled',
            'revisited_completed',
            'follow_up',
            'closed',
            'dead',
            'not_interested',
            'on_hold',
            'junk',
        ];

        $tasks = Task::query()
            ->with([
                'lead' => function ($query) use ($freshLeadStatusesToExclude) {
                    $query->select('id', 'name', 'phone', 'status', 'is_dead')
                        ->where('is_dead', false)
                        ->whereNotIn('status', $freshLeadStatusesToExclude)
                        ->whereDoesntHave('prospects')
                        ->whereDoesntHave('meetings')
                        ->whereDoesntHave('siteVisits')
                        ->whereDoesntHave('followUps')
                        ->with([
                            'activeAssignments' => function ($assignmentQuery) {
                                $assignmentQuery->with('assignedTo:id,name')
                                    ->orderByDesc('assigned_at')
                                    ->orderByDesc('id');
                            },
                        ]);
                },
                'assignedTo:id,name',
            ])
            ->where('type', 'phone_call')
            ->where('outcome', 'cnp')
            ->whereIn('assigned_to', $eligibleUserIds)
            ->orderBy('lead_id')
            ->orderByRaw('COALESCE(outcome_recorded_at, completed_at, updated_at, created_at) asc')
            ->orderBy('id')
            ->get();

        $leadSequence = [];
        $grouped = [];

        foreach ($tasks as $task) {
            $lead = $task->lead;
            $userId = $task->assigned_to;

            if (!$lead || !$userId) {
                continue;
            }

            $leadId = (int) $lead->id;
            $leadSequence[$leadId] = ($leadSequence[$leadId] ?? 0) + 1;

            $eventAt = $task->outcome_recorded_at ?? $task->completed_at ?? $task->updated_at ?? $task->created_at;

            if ($startDate && $endDate && (!$eventAt || $eventAt->lt($startDate) || $eventAt->gt($endDate))) {
                continue;
            }

            if (!isset($grouped[$userId])) {
                $grouped[$userId] = [
                    'user_id' => $userId,
                    'user_name' => $task->assignedTo?->name ?? ($users->firstWhere('id', $userId)?->name ?? 'Unknown'),
                    'cnp_total' => 0,
                    'last_cnp_at' => null,
                    'leads' => [],
                ];
            }

            $grouped[$userId]['cnp_total']++;

            $eventAtIso = $eventAt?->toIso8601String();
            if (!$grouped[$userId]['last_cnp_at'] || ($eventAtIso && $eventAtIso > $grouped[$userId]['last_cnp_at'])) {
                $grouped[$userId]['last_cnp_at'] = $eventAtIso;
            }

            $leadKey = (string) $leadId;
            $currentOwner = $lead->activeAssignments->first()?->assignedTo?->name ?? 'Unassigned';

            if (!isset($grouped[$userId]['leads'][$leadKey]) || (($eventAtIso ?? '') > ($grouped[$userId]['leads'][$leadKey]['last_cnp_at'] ?? ''))) {
                $grouped[$userId]['leads'][$leadKey] = [
                    'lead_id' => $leadId,
                    'lead_name' => $lead->name,
                    'phone' => $lead->phone,
                    'cnp_number' => (int) ($leadSequence[$leadId] ?? 0),
                    'last_cnp_at' => $eventAtIso,
                    'current_owner_name' => $currentOwner,
                    'cnp_marked_by_name' => $task->assignedTo?->name ?? ($grouped[$userId]['user_name'] ?? 'Unknown'),
                ];
            }
        }

        return $this->formatFreshLeadCnpSummaryRows($grouped);
    }

    private function formatFreshLeadCnpSummaryRows(array $grouped): array
    {
        return collect($grouped)
            ->map(function (array $row) {
                $row['leads'] = collect($row['leads'])
                    ->sortByDesc(function (array $leadRow) {
                        return $leadRow['last_cnp_at'] ?? '';
                    })
                    ->values()
                    ->all();

                return $row;
            })
            ->sort(function (array $a, array $b) {
                $countCompare = ($b['cnp_total'] ?? 0) <=> ($a['cnp_total'] ?? 0);
                if ($countCompare !== 0) {
                    return $countCompare;
                }

                $dateCompare = strcmp((string) ($b['last_cnp_at'] ?? ''), (string) ($a['last_cnp_at'] ?? ''));
                if ($dateCompare !== 0) {
                    return $dateCompare;
                }

                return strcasecmp((string) ($a['user_name'] ?? ''), (string) ($b['user_name'] ?? ''));
            })
            ->values()
            ->all();
    }

    /**
     * Get daily prospects with filters and pagination
     */
    public function getDailyProspects(Request $request)
    {
        $dateRange = $request->get('date_range', 'all_time');
        [$startDate, $endDate] = $this->getDateRange($dateRange, $request);
        
        $query = Prospect::with(['createdBy', 'assignedManager', 'assignment']);

        // Date filter
        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        // Filter by user
        if ($request->has('user_id') && $request->user_id !== 'all') {
            $query->where('created_by', $request->user_id);
        }

        // Filter by verification status
        if ($request->has('verification_status') && $request->verification_status !== 'all') {
            $query->where('verification_status', $request->verification_status);
        }

        // Get total count before pagination
        $total = $query->count();

        // Pagination
        $perPage = min(100, max(1, (int) $request->get('per_page', 50)));
        $page = max(1, (int) $request->get('page', 1));
        
        $prospects = $query->latest()
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        // Calculate response time and format data
        $formattedProspects = $prospects->map(function($prospect) {
            $responseTime = null;
            if ($prospect->verified_at && $prospect->created_at) {
                $seconds = $prospect->verified_at->diffInSeconds($prospect->created_at);
                $responseTime = $this->formatResponseTime($seconds);
            }

            return [
                'id' => $prospect->id,
                'customer_name' => $prospect->customer_name,
                'phone' => $prospect->phone,
                'budget' => $prospect->budget,
                'preferred_location' => $prospect->preferred_location,
                'size' => $prospect->size,
                'purpose' => $prospect->purpose,
                'possession' => $prospect->possession,
                'notes' => $prospect->notes,
                'employee_remark' => $prospect->employee_remark,
                'manager_remark' => $prospect->manager_remark,
                'verification_status' => $prospect->verification_status,
                'verified_at' => $prospect->verified_at?->format('Y-m-d H:i:s'),
                'created_at' => $prospect->created_at->format('Y-m-d H:i:s'),
                'created_by_name' => $prospect->createdBy->name ?? null,
                'assigned_manager_name' => $prospect->assignedManager->name ?? null,
                'response_time' => $responseTime,
                'response_time_seconds' => $prospect->verified_at && $prospect->created_at 
                    ? $prospect->verified_at->diffInSeconds($prospect->created_at) 
                    : null,
            ];
        });

        // Stats
        $statsQuery = Prospect::query();
        if ($startDate && $endDate) {
            $statsQuery->whereBetween('created_at', [$startDate, $endDate]);
        }
        if ($request->has('user_id') && $request->user_id !== 'all') {
            $statsQuery->where('created_by', $request->user_id);
        }

        $stats = [
            'total' => (clone $statsQuery)->count(),
            'pending_verification' => (clone $statsQuery)->where('verification_status', 'pending_verification')->count(),
            'verified' => (clone $statsQuery)->where('verification_status', 'verified')->count(),
            'rejected' => (clone $statsQuery)->where('verification_status', 'rejected')->count(),
        ];

        // Stats by user
        $statsByUserQuery = Prospect::query()
            ->select('created_by', DB::raw('COUNT(*) as count'))
            ->groupBy('created_by')
            ->with('createdBy');
        
        if ($startDate && $endDate) {
            $statsByUserQuery->whereBetween('created_at', [$startDate, $endDate]);
        }
        
        $statsByUser = $statsByUserQuery->get()->map(function($item) {
            return [
                'user_id' => $item->created_by,
                'username' => $item->createdBy->name ?? 'Unknown',
                'count' => $item->count,
            ];
        });

        // Daily breakdown
        $dailyBreakdownQuery = Prospect::query()
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw('GROUP_CONCAT(DISTINCT CONCAT(createdBy.name, ":", COUNT(*)) SEPARATOR ", ") as users')
            )
            ->groupBy('date', 'created_by')
            ->with('createdBy');
        
        if ($startDate && $endDate) {
            $dailyBreakdownQuery->whereBetween('created_at', [$startDate, $endDate]);
        }
        
        // Simplified daily breakdown - group by date only
        $dailyBreakdown = DB::table('prospects')
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count')
            )
            ->when($startDate && $endDate, function($q) use ($startDate, $endDate) {
                return $q->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get()
            ->map(function($item) {
                // Get users for this date
                $users = Prospect::whereDate('created_at', $item->date)
                    ->select('created_by', DB::raw('COUNT(*) as user_count'))
                    ->groupBy('created_by')
                    ->with('createdBy')
                    ->get()
                    ->map(function($u) {
                        return ($u->createdBy->name ?? 'Unknown') . ':' . $u->user_count;
                    })
                    ->toArray();

                return [
                    'date' => $item->date,
                    'count' => $item->count,
                    'users' => $users,
                ];
            });

        return response()->json([
            'data' => $formattedProspects,
            'stats' => $stats,
            'stats_by_user' => $statsByUser,
            'daily_breakdown' => $dailyBreakdown,
            'pagination' => [
                'current_page' => (int) $page,
                'per_page' => (int) $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
                'from' => (($page - 1) * $perPage) + 1,
                'to' => min($page * $perPage, $total),
            ],
        ]);
    }

    /**
     * Format response time in human readable format
     */
    private function formatResponseTime($seconds)
    {
        if ($seconds < 60) {
            return $seconds . ' seconds';
        } elseif ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            return $minutes . ' minute' . ($minutes > 1 ? 's' : '');
        } elseif ($seconds < 86400) {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            $result = $hours . ' hour' . ($hours > 1 ? 's' : '');
            if ($minutes > 0) {
                $result .= ' ' . $minutes . ' minute' . ($minutes > 1 ? 's' : '');
            }
            return $result;
        } else {
            $days = floor($seconds / 86400);
            $hours = floor(($seconds % 86400) / 3600);
            $result = $days . ' day' . ($days > 1 ? 's' : '');
            if ($hours > 0) {
                $result .= ' ' . $hours . ' hour' . ($hours > 1 ? 's' : '');
            }
            return $result;
        }
    }

    private function getEligibleDashboardUsers()
    {
        return User::with('role')
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
    }

    private function buildPendingResponseRows($startDate = null, $endDate = null): array
    {
        $users = $this->getEligibleDashboardUsers();

        if ($users->isEmpty()) {
            return [];
        }

        $result = [];

        foreach ($users as $user) {
            $userId = $user->id;

            $leadIdsWithCalls = DB::table('telecaller_tasks')
                ->where('assigned_to', $userId)
                ->where('status', 'completed')
                ->distinct()
                ->pluck('lead_id')
                ->merge(
                    DB::table('crm_assignments')
                        ->where('assigned_to', $userId)
                        ->where(function ($q) {
                            $q->where('cnp_count', '>', 0)
                                ->orWhere('call_status', '!=', 'pending');
                        })
                        ->distinct()
                        ->pluck('lead_id')
                )
                ->unique()
                ->values();

            $assignmentsQuery = LeadAssignment::where('assigned_to', $userId)
                ->where('is_active', true)
                ->with('lead:id,name,phone');

            if ($leadIdsWithCalls->isNotEmpty()) {
                $assignmentsQuery->whereNotIn('lead_id', $leadIdsWithCalls);
            }

            if ($startDate && $endDate) {
                $assignmentsQuery->whereBetween('assigned_at', [$startDate, $endDate]);
            }

            $assignments = $assignmentsQuery->orderBy('assigned_at', 'desc')->get();

            $leads = [];
            foreach ($assignments as $assignment) {
                $lead = $assignment->lead;
                if (!$lead) {
                    continue;
                }

                $leads[] = [
                    'lead_id' => $lead->id,
                    'name' => $lead->name,
                    'phone' => $lead->phone,
                    'assigned_at' => $assignment->assigned_at?->toIso8601String(),
                ];
            }

            $result[] = [
                'user_id' => $userId,
                'user_name' => $user->name,
                'pending_count' => count($leads),
                'leads' => $leads,
            ];
        }

        usort($result, function (array $a, array $b) {
            $countCompare = ($b['pending_count'] ?? 0) <=> ($a['pending_count'] ?? 0);
            if ($countCompare !== 0) {
                return $countCompare;
            }

            return strcasecmp((string) ($a['user_name'] ?? ''), (string) ($b['user_name'] ?? ''));
        });

        return $result;
    }

    private function buildNewLeadsNotCompletedRows($startDate = null, $endDate = null): array
    {
        $users = $this->getEligibleDashboardUsers();

        if ($users->isEmpty()) {
            return [];
        }

        $result = [];

        foreach ($users as $user) {
            $assignmentsQuery = LeadAssignment::query()
                ->where('assigned_to', $user->id)
                ->where('is_active', true)
                ->whereHas('lead', function ($leadQuery) {
                    $leadQuery->where('status', 'new')
                        ->where('is_dead', false)
                        ->where(function ($query) {
                            $query->whereNull('cnp_count')
                                ->orWhere('cnp_count', 0);
                        })
                        ->whereNull('next_followup_at')
                        ->whereDoesntHave('prospects')
                        ->whereDoesntHave('meetings')
                        ->whereDoesntHave('siteVisits')
                        ->whereDoesntHave('followUps');
                })
                ->with('lead:id,name,phone,status,cnp_count,next_followup_at,is_dead');

            if ($startDate && $endDate) {
                $assignmentsQuery->whereBetween('assigned_at', [$startDate, $endDate]);
            }

            $assignments = $assignmentsQuery->orderBy('assigned_at', 'desc')->get();
            $leads = [];

            foreach ($assignments as $assignment) {
                $lead = $assignment->lead;
                if (!$lead || $this->isLeadHandledForNewBucket($user, $lead)) {
                    continue;
                }

                $leads[] = [
                    'lead_id' => $lead->id,
                    'name' => $lead->name,
                    'phone' => $lead->phone,
                    'assigned_at' => $assignment->assigned_at?->toIso8601String(),
                ];
            }

            $result[] = [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'pending_new_count' => count($leads),
                'leads' => $leads,
                'oldest_assigned_at' => collect($leads)
                    ->pluck('assigned_at')
                    ->filter()
                    ->sort()
                    ->first(),
            ];
        }

        usort($result, function (array $a, array $b) {
            $countCompare = ($b['pending_new_count'] ?? 0) <=> ($a['pending_new_count'] ?? 0);
            if ($countCompare !== 0) {
                return $countCompare;
            }

            return strcasecmp((string) ($a['user_name'] ?? ''), (string) ($b['user_name'] ?? ''));
        });

        return $result;
    }

    private function buildPreviousOverPodRows(): array
    {
        $users = $this->getEligibleDashboardUsers();

        if ($users->isEmpty()) {
            return [];
        }

        $todayStart = now()->startOfDay();
        $result = [];

        foreach ($users as $user) {
            $items = $this->buildPreviousOverPodItemsForUser($user, $todayStart);

            if (count($items) === 0) {
                continue;
            }

            $result[] = [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'previous_over_pod_count' => count($items),
                'oldest_pending_at' => collect($items)
                    ->pluck('assigned_at')
                    ->filter()
                    ->sort()
                    ->first(),
                'items' => $items,
            ];
        }

        usort($result, function (array $a, array $b) {
            $countCompare = ($b['previous_over_pod_count'] ?? 0) <=> ($a['previous_over_pod_count'] ?? 0);
            if ($countCompare !== 0) {
                return $countCompare;
            }

            $oldestA = (string) ($a['oldest_pending_at'] ?? '');
            $oldestB = (string) ($b['oldest_pending_at'] ?? '');
            $oldestCompare = strcmp($oldestA, $oldestB);
            if ($oldestCompare !== 0) {
                return $oldestCompare;
            }

            return strcasecmp((string) ($a['user_name'] ?? ''), (string) ($b['user_name'] ?? ''));
        });

        return $result;
    }

    private function buildPreviousOverPodItemsForUser(User $user, Carbon $todayStart): array
    {
        $roleSlug = $user->role->slug ?? null;

        if ($roleSlug === Role::SALES_EXECUTIVE) {
            return $this->buildPreviousOverPodItemsFromTelecallerTasks($user, $todayStart);
        }

        if (in_array($roleSlug, [Role::SALES_MANAGER, Role::SENIOR_MANAGER, Role::ASSISTANT_SALES_MANAGER], true)) {
            return $this->buildPreviousOverPodItemsFromManagerTasks($user, $todayStart);
        }

        return [];
    }

    private function buildPreviousOverPodItemsFromTelecallerTasks(User $user, Carbon $todayStart): array
    {
        $tasks = TelecallerTask::query()
            ->where('assigned_to', $user->id)
            ->whereIn('status', TelecallerTask::OPEN_STATUSES)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<', $todayStart)
            ->with('lead:id,name,phone')
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->get();

        return $tasks
            ->filter(fn (TelecallerTask $task) => $task->lead !== null)
            ->map(function (TelecallerTask $task) {
                return [
                    'lead_id' => $task->lead_id,
                    'task_id' => $task->id,
                    'name' => $task->lead->name,
                    'phone' => $task->lead->phone,
                    'assigned_at' => $task->scheduled_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }

    private function buildPreviousOverPodItemsFromManagerTasks(User $user, Carbon $todayStart): array
    {
        $tasks = Task::query()
            ->where('assigned_to', $user->id)
            ->where('type', 'phone_call')
            ->whereIn('status', Task::OPEN_STATUSES)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<', $todayStart)
            ->whereHas('lead', function ($leadQuery) use ($user) {
                $leadQuery->whereHas('activeAssignments', function ($assignmentQuery) use ($user) {
                    $assignmentQuery->where('assigned_to', $user->id)
                        ->where('is_active', true);
                });
            })
            ->with('lead:id,name,phone')
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->get();

        return $tasks
            ->filter(fn (Task $task) => $task->lead !== null)
            ->groupBy(fn (Task $task) => $task->lead_id ?: 'task-' . $task->id)
            ->map(function ($group) {
                /** @var \Illuminate\Support\Collection<int, Task> $group */
                $task = $group
                    ->sortBy(function (Task $item) {
                        $priority = [
                            'pending' => 1,
                            'in_progress' => 2,
                            'rescheduled' => 3,
                        ];

                        return [
                            $priority[$item->status] ?? 99,
                            $item->scheduled_at?->timestamp ?? PHP_INT_MAX,
                            $item->id,
                        ];
                    })
                    ->first();

                if (!$task || !$task->lead) {
                    return null;
                }

                return [
                    'lead_id' => $task->lead_id,
                    'task_id' => $task->id,
                    'name' => $task->lead->name,
                    'phone' => $task->lead->phone,
                    'assigned_at' => $task->scheduled_at?->toIso8601String(),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function isLeadHandledForNewBucket(User $user, Lead $lead): bool
    {
        if ($lead->is_dead || (int) ($lead->cnp_count ?? 0) > 0 || !empty($lead->next_followup_at)) {
            return true;
        }

        return $this->isLeadTaskCompletedForUser($user, (int) $lead->id);
    }

    private function isLeadTaskCompletedForUser(User $user, int $leadId): bool
    {
        $roleSlug = $user->role->slug ?? null;

        if ($roleSlug === Role::SALES_EXECUTIVE) {
            return TelecallerTask::query()
                ->where('assigned_to', $user->id)
                ->where('lead_id', $leadId)
                ->where(function ($query) {
                    $query->where('status', 'completed')
                        ->orWhereNotNull('completed_at')
                        ->orWhere(function ($outcomeQuery) {
                            $outcomeQuery->whereNotNull('outcome')
                                ->where('outcome', '!=', '');
                        });
                })
                ->exists();
        }

        if (in_array($roleSlug, [Role::SALES_MANAGER, Role::SENIOR_MANAGER, Role::ASSISTANT_SALES_MANAGER], true)) {
            return Task::query()
                ->where('assigned_to', $user->id)
                ->where('lead_id', $leadId)
                ->where('type', 'phone_call')
                ->where(function ($query) {
                    $query->where('status', 'completed')
                        ->orWhereNotNull('completed_at')
                        ->orWhereNotNull('outcome_recorded_at')
                        ->orWhere(function ($outcomeQuery) {
                            $outcomeQuery->whereNotNull('outcome')
                                ->where('outcome', '!=', '');
                        })
                        ->orWhere(function ($retryQuery) {
                            $retryQuery->where('title', 'like', '%CNP retry%')
                                ->orWhere('title', 'like', '%CNP rescheduled%')
                                ->orWhere('description', 'like', '%CNP retry%')
                                ->orWhere('description', 'like', '%CNP rescheduled%')
                                ->orWhere('description', 'like', '%previous call not picked%')
                                ->orWhere('notes', 'like', '%CNP retry%')
                                ->orWhere('notes', 'like', '%CNP rescheduled%')
                                ->orWhere('notes', 'like', '%previous call not picked%');
                        });
                })
                ->exists();
        }

        return false;
    }

    private function formatLeadStatusLabel(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return 'No Outcome';
        }

        return ucwords(str_replace('_', ' ', $value));
    }
}

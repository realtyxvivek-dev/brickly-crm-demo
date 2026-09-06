<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Lead;
use App\Models\SiteVisit;
use App\Models\FollowUp;
use App\Models\LeadAssignment;
use App\Models\Target;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SalesHeadController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show Sales Head dashboard
     */
    public function dashboard()
    {
        $user = auth()->user();
        
        // Ensure role is loaded
        if ($user && !$user->relationLoaded('role')) {
            $user->load('role');
        }
        
        if (!$user || !$user->isSalesHead()) {
            abort(403, 'Unauthorized. Only Sales Head can access this page.');
        }

        return view('sales-head.dashboard');
    }

    /**
     * Get dashboard data for Sales Head
     */
    public function getDashboardData(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
            
            // Ensure role is loaded
            if (!$user->relationLoaded('role')) {
                $user->load('role');
            }
            
            if (!$user->isSalesHead()) {
                return response()->json(['error' => 'Unauthorized. Only Sales Head can access this data.'], 403);
            }

            // Get all team member IDs (including nested teams)
            $allTeamMemberIds = $user->getAllTeamMemberIds();
            
            // If no team members, use empty array
            if (empty($allTeamMemberIds)) {
                $allTeamMemberIds = [];
            }
        
        // Get all Senior Managers under this Sales Head
        $salesManagers = User::where('manager_id', $user->id)
            ->whereHas('role', function($q) {
                $q->where('slug', 'sales_manager');
            })
            ->with('role')
            ->get();

        $assistantSalesManagers = User::whereIn('id', $allTeamMemberIds)
            ->whereHas('role', function($q) {
                $q->where('slug', Role::ASSISTANT_SALES_MANAGER);
            })
            ->with(['role', 'manager'])
            ->get();

        // Get all Sales Executives
        $salesExecutives = User::whereIn('manager_id', array_merge([$user->id], $salesManagers->pluck('id')->toArray()))
            ->whereHas('role', function($q) {
                $q->where('slug', 'sales_executive');
            })
            ->with(['role', 'manager'])
            ->get();

        // Get all Sales Executives (team)
        $telecallers = User::whereIn('manager_id', array_merge([$user->id], $allTeamMemberIds))
            ->whereHas('role', function($q) {
                $q->where('slug', \App\Models\Role::SALES_EXECUTIVE);
            })
            ->with(['role', 'manager'])
            ->get();

        // Get all leads assigned to team members
        $leadIds = collect();
        if (!empty($allTeamMemberIds)) {
            $leadIds = Lead::whereHas('activeAssignments', function ($q) use ($allTeamMemberIds) {
                $q->whereIn('assigned_to', $allTeamMemberIds);
            })->pluck('id');
        }

        // Stats
        $leadCount = $leadIds->count();
        $leadIdsArray = $leadIds->toArray();
        $closedWonCount = $leadCount > 0 && !empty($leadIdsArray) ? Lead::whereIn('id', $leadIdsArray)->where('status', 'closed_won')->count() : 0;
        
        $pendingVerificationsQuery = $leadCount > 0 && !empty($leadIdsArray)
            ? Lead::whereIn('id', $leadIdsArray)->where('needs_verification', true)
            : Lead::whereRaw('1 = 0');

        $stats = [
            'total_leads' => $leadCount > 0 && !empty($leadIdsArray) ? Lead::whereIn('id', $leadIdsArray)->count() : 0,
            'new_leads' => $leadCount > 0 && !empty($leadIdsArray) ? Lead::whereIn('id', $leadIdsArray)->where('status', 'new')->count() : 0,
            'qualified_leads' => $leadCount > 0 && !empty($leadIdsArray) ? Lead::whereIn('id', $leadIdsArray)->where('status', 'qualified')->count() : 0,
            'closed_won' => $closedWonCount,
            'conversion_rate' => $leadCount > 0 ? round(($closedWonCount / $leadCount) * 100, 2) : 0,
            'direct_managers' => $salesManagers->count(),
            'active_sales_managers' => $salesManagers->count(),
            'active_asms' => $assistantSalesManagers->count(),
            'active_sales_executives' => $salesExecutives->count(),
            'active_telecallers' => $telecallers->count(),
            'total_team_members' => count($allTeamMemberIds),
            'pending_verifications' => (clone $pendingVerificationsQuery)->count(),
            'today_leads' => $leadCount > 0 && !empty($leadIdsArray) ? Lead::whereIn('id', $leadIdsArray)->whereDate('created_at', today())->count() : 0,
            'today_conversions' => $leadCount > 0 && !empty($leadIdsArray) ? Lead::whereIn('id', $leadIdsArray)
                ->where('status', 'closed_won')
                ->whereDate('updated_at', today())
                ->count() : 0,
            'today_site_visits' => !empty($allTeamMemberIds) ? SiteVisit::whereIn('assigned_to', $allTeamMemberIds)
                ->whereDate('scheduled_at', today())
                ->count() : 0,
            'upcoming_site_visits' => !empty($allTeamMemberIds) ? SiteVisit::whereIn('assigned_to', $allTeamMemberIds)
                ->where('status', 'scheduled')
                ->where('scheduled_at', '>=', now())
                ->count() : 0,
            'pending_followups' => !empty($allTeamMemberIds) ? FollowUp::whereIn('created_by', $allTeamMemberIds)
                ->where('status', 'scheduled')
                ->where('scheduled_at', '>=', now())
                ->count() : 0,
        ];

        $currentMonth = Carbon::now()->startOfMonth();
        $targets = !empty($allTeamMemberIds)
            ? Target::with(['user.role'])
                ->whereIn('user_id', $allTeamMemberIds)
                ->whereDate('target_month', $currentMonth)
                ->get()
            : collect();

        $targetOverview = [
            'users_with_targets' => $targets->count(),
            'meetings_target' => 0,
            'meetings_achieved' => 0,
            'visits_target' => 0,
            'visits_achieved' => 0,
            'closers_target' => 0,
            'closers_achieved' => 0,
        ];

        foreach ($targets as $target) {
            $meetings = $target->getAchievementProgress('meetings');
            $visits = $target->getAchievementProgress('visits');
            $closers = $target->getAchievementProgress('closers');

            $targetOverview['meetings_target'] += (int) ($meetings['target'] ?? 0);
            $targetOverview['meetings_achieved'] += (int) ($meetings['achieved'] ?? 0);
            $targetOverview['visits_target'] += (int) ($visits['target'] ?? 0);
            $targetOverview['visits_achieved'] += (int) ($visits['achieved'] ?? 0);
            $targetOverview['closers_target'] += (int) ($closers['target'] ?? 0);
            $targetOverview['closers_achieved'] += (int) ($closers['achieved'] ?? 0);
        }

        $targetOverview['meetings_percentage'] = $targetOverview['meetings_target'] > 0
            ? round(($targetOverview['meetings_achieved'] / $targetOverview['meetings_target']) * 100, 2)
            : 0;
        $targetOverview['visits_percentage'] = $targetOverview['visits_target'] > 0
            ? round(($targetOverview['visits_achieved'] / $targetOverview['visits_target']) * 100, 2)
            : 0;
        $targetOverview['closers_percentage'] = $targetOverview['closers_target'] > 0
            ? round(($targetOverview['closers_achieved'] / $targetOverview['closers_target']) * 100, 2)
            : 0;

        // Senior Managers Performance
        $managersPerformance = $salesManagers->map(function($manager) {
            $managerTeamIds = $manager->getAllTeamMemberIds();
            $managerLeadIds = !empty($managerTeamIds) ? Lead::whereHas('activeAssignments', function ($q) use ($managerTeamIds) {
                $q->whereIn('assigned_to', $managerTeamIds);
            })->pluck('id') : collect();

            $totalLeads = $managerLeadIds->count();
            $managerLeadIdsArray = $managerLeadIds->toArray();
            $converted = $totalLeads > 0 && !empty($managerLeadIdsArray) ? Lead::whereIn('id', $managerLeadIdsArray)->where('status', 'closed_won')->count() : 0;
            
            return [
                'id' => $manager->id,
                'name' => $manager->name,
                'email' => $manager->email,
                'team_size' => count($managerTeamIds),
                'total_leads' => $totalLeads,
                'leads_converted' => $converted,
                'conversion_rate' => $totalLeads > 0 ? round(($converted / $totalLeads) * 100, 2) : 0,
            ];
        });

        // Sales Executives Performance
        $executivesPerformance = $salesExecutives->map(function($executive) {
            $executiveLeadIds = Lead::whereHas('activeAssignments', function ($q) use ($executive) {
                $q->where('assigned_to', $executive->id);
            })->pluck('id');

            $totalLeads = $executiveLeadIds->count();
            $executiveLeadIdsArray = $executiveLeadIds->toArray();
            $converted = $totalLeads > 0 && !empty($executiveLeadIdsArray) ? Lead::whereIn('id', $executiveLeadIdsArray)->where('status', 'closed_won')->count() : 0;
            $siteVisits = SiteVisit::where('assigned_to', $executive->id)->where('status', 'completed')->count();
            
            return [
                'id' => $executive->id,
                'name' => $executive->name,
                'email' => $executive->email,
                'manager_name' => $executive->manager->name ?? 'N/A',
                'total_leads' => $totalLeads,
                'leads_converted' => $converted,
                'conversion_rate' => $totalLeads > 0 ? round(($converted / $totalLeads) * 100, 2) : 0,
                'site_visits_completed' => $siteVisits,
            ];
        });

        // Telecallers Performance
        $telecallersPerformance = $telecallers->map(function($telecaller) {
            $telecallerLeadIds = Lead::whereHas('activeAssignments', function ($q) use ($telecaller) {
                $q->where('assigned_to', $telecaller->id);
            })->pluck('id');

            $totalLeads = $telecallerLeadIds->count();
            $telecallerLeadIdsArray = $telecallerLeadIds->toArray();
            $qualified = $totalLeads > 0 && !empty($telecallerLeadIdsArray) ? Lead::whereIn('id', $telecallerLeadIdsArray)->where('status', 'qualified')->count() : 0;
            
            return [
                'id' => $telecaller->id,
                'name' => $telecaller->name,
                'email' => $telecaller->email,
                'manager_name' => $telecaller->manager->name ?? 'N/A',
                'total_leads' => $totalLeads,
                'leads_qualified' => $qualified,
                'qualification_rate' => $totalLeads > 0 ? round(($qualified / $totalLeads) * 100, 2) : 0,
            ];
        });

        $asmPerformance = $assistantSalesManagers->map(function($asm) {
            $asmTeamIds = $asm->getAllTeamMemberIds();
            $scopedIds = array_values(array_unique(array_merge([$asm->id], $asmTeamIds)));
            $asmLeadIds = !empty($scopedIds)
                ? Lead::whereHas('activeAssignments', function ($q) use ($scopedIds) {
                    $q->whereIn('assigned_to', $scopedIds);
                })->pluck('id')
                : collect();

            $totalLeads = $asmLeadIds->count();
            $asmLeadIdsArray = $asmLeadIds->toArray();
            $converted = $totalLeads > 0 && !empty($asmLeadIdsArray)
                ? Lead::whereIn('id', $asmLeadIdsArray)->where('status', 'closed_won')->count()
                : 0;

            return [
                'id' => $asm->id,
                'name' => $asm->name,
                'email' => $asm->email,
                'manager_name' => $asm->manager->name ?? 'N/A',
                'team_size' => count($asmTeamIds),
                'total_leads' => $totalLeads,
                'leads_converted' => $converted,
                'conversion_rate' => $totalLeads > 0 ? round(($converted / $totalLeads) * 100, 2) : 0,
            ];
        });

        // Lead Pipeline by Status
        $leadPipeline = [
            'new' => $leadCount > 0 && !empty($leadIdsArray) ? Lead::whereIn('id', $leadIdsArray)->where('status', 'new')->count() : 0,
            'contacted' => $leadCount > 0 && !empty($leadIdsArray) ? Lead::whereIn('id', $leadIdsArray)->where('status', 'contacted')->count() : 0,
            'qualified' => $leadCount > 0 && !empty($leadIdsArray) ? Lead::whereIn('id', $leadIdsArray)->where('status', 'qualified')->count() : 0,
            'site_visit_scheduled' => $leadCount > 0 && !empty($leadIdsArray) ? Lead::whereIn('id', $leadIdsArray)->where('status', 'site_visit_scheduled')->count() : 0,
            'site_visit_completed' => $leadCount > 0 && !empty($leadIdsArray) ? Lead::whereIn('id', $leadIdsArray)->where('status', 'site_visit_completed')->count() : 0,
            'negotiation' => $leadCount > 0 && !empty($leadIdsArray) ? Lead::whereIn('id', $leadIdsArray)->where('status', 'negotiation')->count() : 0,
            'closed_won' => $closedWonCount,
            'closed_lost' => $leadCount > 0 && !empty($leadIdsArray) ? Lead::whereIn('id', $leadIdsArray)->where('status', 'closed_lost')->count() : 0,
            'on_hold' => $leadCount > 0 && !empty($leadIdsArray) ? Lead::whereIn('id', $leadIdsArray)->where('status', 'on_hold')->count() : 0,
        ];

        // Lead Source Distribution
        $leadSourceDistribution = $leadCount > 0 && !empty($leadIdsArray) ? Lead::whereIn('id', $leadIdsArray)
            ->select('source', DB::raw('count(*) as count'))
            ->groupBy('source')
            ->get()
            ->pluck('count', 'source')
            ->toArray() : [];

        // Recent Leads
        $recentLeads = $leadCount > 0 && !empty($leadIdsArray) ? Lead::whereIn('id', $leadIdsArray)
            ->with(['creator', 'activeAssignments.assignedTo'])
            ->latest()
            ->limit(10)
            ->get() : collect();

        // Recent Conversions
        $recentConversions = $leadCount > 0 && !empty($leadIdsArray) ? Lead::whereIn('id', $leadIdsArray)
            ->where('status', 'closed_won')
            ->with(['creator', 'activeAssignments.assignedTo'])
            ->latest('updated_at')
            ->limit(10)
            ->get() : collect();

        // Recent Site Visits
        $recentSiteVisits = !empty($allTeamMemberIds) ? SiteVisit::whereIn('assigned_to', $allTeamMemberIds)
            ->with(['lead', 'assignedTo'])
            ->latest()
            ->limit(10)
            ->get() : collect();

        // Pending Verifications
        $pendingVerifications = (clone $pendingVerificationsQuery)
            ->with(['verificationRequestedBy', 'pendingManager', 'activeAssignments.assignedTo'])
            ->latest('verification_requested_at')
            ->limit(10)
            ->get();

        // Upcoming Follow-ups
        $upcomingFollowups = !empty($allTeamMemberIds) ? FollowUp::whereIn('created_by', $allTeamMemberIds)
            ->where('status', 'scheduled')
            ->where('scheduled_at', '>=', now())
            ->with(['lead', 'creator'])
            ->orderBy('scheduled_at')
            ->limit(10)
            ->get() : collect();

        // Team Hierarchy
        $teamHierarchy = $this->buildTeamHierarchy($user);

        return response()->json([
            'stats' => $stats,
            'managers_performance' => $managersPerformance->values(),
            'asm_performance' => $asmPerformance->values(),
            'executives_performance' => $executivesPerformance->values(),
            'telecallers_performance' => $telecallersPerformance->values(),
            'lead_pipeline' => $leadPipeline,
            'lead_source_distribution' => $leadSourceDistribution,
            'target_overview' => $targetOverview,
            'recent_leads' => $recentLeads->map(function($lead) {
                return [
                    'id' => $lead->id,
                    'name' => $lead->name,
                    'email' => $lead->email,
                    'phone' => $lead->phone,
                    'status' => $lead->status,
                    'source' => $lead->source,
                    'created_at' => $lead->created_at?->toDateTimeString(),
                    'creator' => $lead->creator ? ['id' => $lead->creator->id, 'name' => $lead->creator->name] : null,
                    'assigned_to' => $lead->activeAssignments->first()?->assignedTo ? ['id' => $lead->activeAssignments->first()->assignedTo->id, 'name' => $lead->activeAssignments->first()->assignedTo->name] : null,
                ];
            })->values(),
            'recent_conversions' => $recentConversions->map(function($lead) {
                return [
                    'id' => $lead->id,
                    'name' => $lead->name,
                    'email' => $lead->email,
                    'phone' => $lead->phone,
                    'status' => $lead->status,
                    'updated_at' => $lead->updated_at?->toDateTimeString(),
                    'creator' => $lead->creator ? ['id' => $lead->creator->id, 'name' => $lead->creator->name] : null,
                    'assigned_to' => $lead->activeAssignments->first()?->assignedTo ? ['id' => $lead->activeAssignments->first()->assignedTo->id, 'name' => $lead->activeAssignments->first()->assignedTo->name] : null,
                ];
            })->values(),
            'recent_site_visits' => $recentSiteVisits->map(function($visit) {
                return [
                    'id' => $visit->id,
                    'lead_id' => $visit->lead_id,
                    'lead_name' => $visit->lead->name ?? null,
                    'scheduled_at' => $visit->scheduled_at?->toDateTimeString(),
                    'status' => $visit->status,
                    'assigned_to' => $visit->assignedTo ? ['id' => $visit->assignedTo->id, 'name' => $visit->assignedTo->name] : null,
                ];
            })->values(),
            'pending_verifications' => $pendingVerifications->map(function($lead) {
                return [
                    'id' => $lead->id,
                    'name' => $lead->name,
                    'email' => $lead->email,
                    'phone' => $lead->phone,
                    'verification_requested_at' => $lead->verification_requested_at?->toDateTimeString(),
                    'verification_requested_by' => $lead->verificationRequestedBy ? ['id' => $lead->verificationRequestedBy->id, 'name' => $lead->verificationRequestedBy->name] : null,
                    'pending_manager' => $lead->pendingManager ? ['id' => $lead->pendingManager->id, 'name' => $lead->pendingManager->name] : null,
                    'current_assigned_to' => $lead->activeAssignments->first()?->assignedTo ? ['id' => $lead->activeAssignments->first()->assignedTo->id, 'name' => $lead->activeAssignments->first()->assignedTo->name] : null,
                ];
            })->values(),
            'upcoming_followups' => $upcomingFollowups->map(function($followup) {
                return [
                    'id' => $followup->id,
                    'lead_id' => $followup->lead_id,
                    'lead_name' => $followup->lead->name ?? null,
                    'scheduled_at' => $followup->scheduled_at?->toIso8601String(),
                    'notes' => $followup->notes,
                    'creator' => $followup->creator ? ['id' => $followup->creator->id, 'name' => $followup->creator->name] : null,
                ];
            })->values(),
            'team_hierarchy' => $teamHierarchy,
        ]);
        } catch (\Exception $e) {
            Log::error('Sales Head Dashboard Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()?->id,
            ]);
            
            return response()->json([
                'error' => 'Failed to load dashboard data',
                'message' => config('app.debug') ? $e->getMessage() : 'An error occurred while loading dashboard data.',
            ], 500);
        }
    }

    /**
     * Build team hierarchy structure
     */
    private function buildTeamHierarchy(User $user): array
    {
        // Ensure role is loaded
        if (!$user->relationLoaded('role')) {
            $user->load('role');
        }
        
        $hierarchy = [
            'id' => $user->id,
            'name' => $user->name,
            'role' => $user->role?->name ?? 'N/A',
            'children' => [],
        ];

        $directReports = $user->teamMembers()->with('role')->get();

        foreach ($directReports as $member) {
            // Ensure role is loaded for member
            if (!$member->relationLoaded('role')) {
                $member->load('role');
            }
            
            $memberData = [
                'id' => $member->id,
                'name' => $member->name,
                'role' => $member->role?->name ?? 'N/A',
                'children' => [],
            ];

            // If it's a manager or assistant sales manager, get their team
            if ($member->isSalesManager() || $member->isAssistantSalesManager()) {
                $memberData['children'] = $this->buildTeamHierarchy($member)['children'];
            }

            $hierarchy['children'][] = $memberData;
        }

        return $hierarchy;
    }
}

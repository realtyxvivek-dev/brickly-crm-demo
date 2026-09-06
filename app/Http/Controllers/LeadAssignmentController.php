<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\User;
use App\Models\Role;
use App\Models\BlacklistedNumber;
use App\Services\LeadAssignmentService;
use App\Services\LeadTaskCleanupService;
use App\Services\TelecallerStatusService;
use App\Services\TelecallerLimitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class LeadAssignmentController extends Controller
{
    protected $assignmentService;
    protected $statusService;
    protected $limitService;

    public function __construct(
        LeadAssignmentService $assignmentService,
        TelecallerStatusService $statusService,
        TelecallerLimitService $limitService
    ) {
        $this->assignmentService = $assignmentService;
        $this->statusService = $statusService;
        $this->limitService = $limitService;
    }

    /**
     * Main dashboard
     */
    public function index()
    {
        $stats = $this->getStats();
        $blacklistedNumbers = BlacklistedNumber::with('blacklistedBy')
            ->latest('blacklisted_at')
            ->get();
        return view('lead-assignment.index', compact('stats', 'blacklistedNumbers'));
    }

    /**
     * Get unassigned leads
     */
    public function getUnassignedLeads(Request $request)
    {
        $query = Lead::whereDoesntHave('activeAssignments')
            ->with(['creator', 'assignments.assignedTo']);

        // Filters
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('source')) {
            $query->where('source', $request->source);
        }

        $leads = $query->latest()->paginate(20);

        // Get all eligible users (telecaller, sales_manager, sales_executive)
        $eligibleRoleIds = Role::whereIn('slug', [
            Role::SALES_EXECUTIVE,
            Role::SALES_MANAGER,
            Role::ASSISTANT_SALES_MANAGER
        ])->pluck('id');
        
        $eligibleUsers = User::whereIn('role_id', $eligibleRoleIds)
            ->where('is_active', true)
            ->with('role')
            ->orderBy('name')
            ->get()
            ->groupBy(function($user) {
                return $user->role->name ?? 'Other';
            });

        if ($request->wantsJson()) {
            return response()->json($leads);
        }

        return view('lead-assignment.unassigned', compact('leads', 'eligibleUsers'));
    }

    /**
     * Assign leads (bulk or single)
     */
    public function assignLeads(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lead_ids' => 'required|array',
            'lead_ids.*' => 'exists:leads,id',
            'telecaller_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $assignedUser = User::with('role')->findOrFail($request->telecaller_id);
        
        // Check if user has eligible role (sales_executive, sales_manager, assistant_sales_manager)
        $userRole = $assignedUser->role->slug ?? '';
        $eligibleRoles = [Role::SALES_EXECUTIVE, Role::SALES_MANAGER, Role::ASSISTANT_SALES_MANAGER];
        
        if (!in_array($userRole, $eligibleRoles)) {
            return response()->json([
                'success' => false,
                'message' => 'Selected user must be a Sales Executive, Senior Manager, or Assistant Sales Manager.'
            ], 422);
        }

        // Check if user can receive assignment (only for sales executives)
        if ($assignedUser->isSalesExecutive()) {
            $canReceive = $this->statusService->canReceiveAssignment($assignedUser->id);
            if (!$canReceive['can_receive']) {
                return response()->json([
                    'success' => false,
                    'message' => 'User cannot receive assignment. ' . 
                        ($canReceive['is_absent'] ? 'User is absent.' : '') .
                        ($canReceive['has_reached_threshold'] ? 'Pending threshold reached.' : '')
                ], 422);
            }

            // Check daily limits (only for telecallers)
            $limitCheck = $this->limitService->checkDailyLimits($assignedUser->id);
            if (!$limitCheck['is_allowed']) {
                return response()->json([
                    'success' => false,
                    'message' => 'User has reached daily limit.'
                ], 422);
            }
        }

        $results = $this->assignmentService->bulkAssignLeads(
            $request->lead_ids,
            $assignedUser->id,
            Auth::id(),
            true
        );

        return response()->json([
            'success' => true,
            'message' => "Successfully assigned {$results['success']} leads. {$results['failed']} failed.",
            'results' => $results
        ]);
    }

    /**
     * Delete leads (bulk or single) from Unassigned Leads screen.
     * Soft-deletes leads so they can be recovered from trash if needed.
     */
    public function deleteLeads(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lead_ids' => 'required|array|min:1',
            'lead_ids.*' => 'exists:leads,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $leadIds = collect($request->lead_ids)->map(fn ($id) => (int) $id)->unique()->values();

        $deleted = 0;
        $skipped = 0;

        // Only delete leads that are currently unassigned (safety)
        $leads = Lead::whereIn('id', $leadIds)
            ->whereDoesntHave('activeAssignments')
            ->get();

        foreach ($leads as $lead) {
            try {
                app(LeadTaskCleanupService::class)->deleteAllTasksForLead($lead->id, Auth::id(), 'lead_deleted');
                LeadAssignment::where('lead_id', $lead->id)
                    ->where('is_active', true)
                    ->update([
                        'is_active' => false,
                        'unassigned_at' => now(),
                    ]);
                $lead->delete(); // soft delete (Lead uses SoftDeletes)
                $deleted++;
            } catch (\Exception $e) {
                $skipped++;
                Log::error('Failed to delete lead from unassigned screen', [
                    'lead_id' => $lead->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $requestedCount = $leadIds->count();
        $skipped += max($requestedCount - $leads->count(), 0); // assigned/not-found-in-query

        return response()->json([
            'success' => true,
            'message' => "Deleted {$deleted} lead(s). Skipped {$skipped}.",
            'deleted' => $deleted,
            'skipped' => $skipped,
        ]);
    }

    /**
     * Get telecaller stats
     */
    public function getTelecallerStats(Request $request)
    {
        $telecallerId = $request->input('telecaller_id');
        
        if (!$telecallerId) {
            return response()->json(['error' => 'Telecaller ID required'], 422);
        }

        $telecaller = User::findOrFail($telecallerId);
        
        $canReceive = $this->statusService->canReceiveAssignment($telecaller->id);
        $limitCheck = $this->limitService->checkDailyLimits($telecaller->id);
        $pendingCount = $this->statusService->getPendingLeadsCount($telecaller->id);

        $overallLimit = $this->limitService->getOverallDailyLimit($telecaller->id);

        return response()->json([
            'telecaller' => [
                'id' => $telecaller->id,
                'name' => $telecaller->name,
            ],
            'status' => $canReceive,
            'limits' => $limitCheck,
            'pending_count' => $pendingCount,
            'overall_limit' => [
                'limit' => $overallLimit->overall_daily_limit,
                'assigned_today' => $overallLimit->assigned_count_today,
                'available' => $overallLimit->overall_daily_limit - $overallLimit->assigned_count_today,
            ],
        ]);
    }

    /**
     * Get stats for dashboard
     */
    protected function getStats(): array
    {
        $salesExecutiveRoleId = Role::where('slug', Role::SALES_EXECUTIVE)->value('id');

        $totalTelecallers = User::where('role_id', $salesExecutiveRoleId)
            ->where('is_active', true)
            ->count();

        $activeTelecallers = User::where('role_id', $salesExecutiveRoleId)
            ->where('is_active', true)
            ->with('userProfile')
            ->get()
            ->filter(function ($user) {
                return !($user->userProfile?->isCurrentlyAbsent() ?? false);
            })
            ->count();

        $unassignedLeads = Lead::whereDoesntHave('activeAssignments')->count();

        $totalAssignedToday = \App\Models\LeadAssignment::where('is_active', true)
            ->whereDate('assigned_at', today())
            ->count();

        return [
            'total_telecallers' => $totalTelecallers,
            'active_telecallers' => $activeTelecallers,
            'unassigned_leads' => $unassignedLeads,
            'assigned_today' => $totalAssignedToday,
        ];
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Target;
use App\Models\User;
use App\Models\Role;
use App\Services\TargetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class TargetController extends Controller
{
    protected $targetService;

    protected function getTargetsRouteBase(Request $request): string
    {
        $routeName = $request->route()?->getName() ?? '';
        if (str_starts_with($routeName, 'crm.targets.')) {
            return 'crm.targets';
        }
        if (str_starts_with($routeName, 'admin.targets.')) {
            return 'admin.targets';
        }
        if (str_starts_with($routeName, 'hr-manager.targets.')) {
            return 'hr-manager.targets';
        }
        if ($request->user()?->isHrManager()) {
            return 'hr-manager.targets';
        }
        return $request->user() && $request->user()->isCrm() ? 'crm.targets' : 'admin.targets';
    }

    public function __construct(TargetService $targetService)
    {
        $this->targetService = $targetService;
        
        // Only allow roles responsible for organization-wide target management.
        $this->middleware(function ($request, $next) {
            $user = $request->user();
            if (!$user || (!$user->isAdmin() && !$user->isCrm() && !$user->isSalesHead() && !$user->isHrManager())) {
                abort(403, 'Unauthorized access');
            }
            return $next($request);
        });
    }

    /**
     * Display a listing of targets
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $month = $request->get('month', now()->format('Y-m'));

        $selectedMonth = Carbon::parse($month . '-01')->startOfMonth();
        $previousMonth = $selectedMonth->copy()->subMonthNoOverflow();

        $targetsQuery = $this->buildMonthTargetsQuery($user, $selectedMonth)
            ->with('user.role');

        $monthTargets = $targetsQuery->orderBy('target_month', 'desc')
            ->orderBy('user_id')
            ->get();

        $users = $this->buildEligibleUsersQuery($user)
            ->with('role')
            ->orderBy('name')
            ->get();

        $targetsByUser = $monthTargets->keyBy('user_id');
        $targets = $users->map(function (User $eligibleUser) use ($targetsByUser, $selectedMonth) {
            $target = $targetsByUser->get($eligibleUser->id);
            if ($target) {
                return $target;
            }

            $target = new Target([
                'user_id' => $eligibleUser->id,
                'target_month' => $selectedMonth,
            ]);
            $target->setRelation('user', $eligibleUser);

            return $target;
        })->concat($monthTargets->filter(fn (Target $target) => !$target->user))->values();

        $previousMonthTargetsCount = $this->buildMonthTargetsQuery($user, $previousMonth)->count();

        return view('admin.targets.index', compact(
            'targets',
            'users',
            'month',
            'previousMonth',
            'previousMonthTargetsCount'
        ));
    }

    /**
     * Show the form for creating a new target
     */
    public function create(Request $request)
    {
        $user = $request->user();
        $month = $request->get('month', now()->format('Y-m'));
        $userId = $request->get('user_id');
        $targetMonth = Carbon::parse($month . '-01')->startOfMonth();

        $users = $this->buildEligibleUsersQuery($user)
            ->with('role')
            ->orderBy('name')
            ->get();

        // If user_id is provided, get existing target for that user
        $existingTarget = null;
        if ($userId) {
            $existingTarget = Target::where('user_id', $userId)
                ->where('target_month', $targetMonth)
                ->first();
        }

        $users = $this->attachTargetPreviewMeta($users, $targetMonth);

        return view('admin.targets.create', compact('users', 'month', 'userId', 'existingTarget'));
    }

    /**
     * Store a newly created target
     */
    public function store(Request $request)
    {
        $routeBase = $this->getTargetsRouteBase($request);
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'month' => 'required|date_format:Y-m',
            'target_prospects_extract' => 'nullable|integer|min:0',
            'target_prospects_verified' => 'nullable|integer|min:0',
            'target_calls' => 'nullable|integer|min:0',
            'target_visits' => 'nullable|integer|min:0',
            'target_meetings' => 'nullable|integer|min:0',
            'target_closers' => 'nullable|integer|min:0',
            'manager_target_calculation_logic' => 'nullable|in:juniors_sum,individual_plus_team',
            'manager_junior_scope' => 'nullable|in:executives_only,executives_and_telecallers',
            'incentive_per_closer' => 'nullable|numeric|min:0',
            'incentive_per_visit' => 'nullable|numeric|min:0',
        ]);

        // Verify user role based on who is setting the target
        $currentUser = $request->user();
        $targetUser = User::findOrFail($validated['user_id']);
        
        if ($currentUser->isSalesHead()) {
            // Sales Head can set targets for Sales Executive, Senior Manager, and Assistant Sales Manager
            if (!$this->isEligibleTargetUser($targetUser)) {
                return back()->withErrors(['user_id' => 'Targets can only be set for Senior Managers, Sales Executives, and Assistant Sales Managers.'])->withInput();
            }
        } else {
            // Admin/CRM can set targets for Telecallers, Sales Executives, Senior Managers, and Assistant Sales Managers
            if (!$this->isEligibleTargetUser($targetUser) && !$targetUser->isTelecaller()) {
                return back()->withErrors(['user_id' => 'Targets can only be set for Telecallers, Sales Executives, Senior Managers, and Assistant Sales Managers.'])->withInput();
            }
        }

        try {
            // For Senior Managers and Assistant Sales Managers, set prospect/call targets to 0 (they don't have those)
            $isManagerRole = $this->usesProspectlessTargets($targetUser);
            
            $target = $this->targetService->setTargetsForUser(
                $validated['user_id'],
                $validated['month'],
                [
                    'target_prospects_extract' => $isManagerRole ? 0 : ($validated['target_prospects_extract'] ?? 0),
                    'target_prospects_verified' => $isManagerRole ? 0 : ($validated['target_prospects_verified'] ?? 0),
                    'target_calls' => $isManagerRole ? 0 : ($validated['target_calls'] ?? 0),
                    'target_visits' => $validated['target_visits'] ?? 0,
                    'target_meetings' => $validated['target_meetings'] ?? 0,
                    'target_closers' => $validated['target_closers'] ?? 0,
                    'manager_target_calculation_logic' => $validated['manager_target_calculation_logic'] ?? null,
                    'manager_junior_scope' => $validated['manager_junior_scope'] ?? null,
                    'incentive_per_closer' => $validated['incentive_per_closer'] ?? null,
                    'incentive_per_visit' => $validated['incentive_per_visit'] ?? null,
                ]
            );

            return redirect()
                ->route($routeBase . '.index', ['month' => $validated['month']])
                ->with('success', "Targets set successfully for {$targetUser->name}");

        } catch (\Exception $e) {
            return back()
                ->withErrors(['error' => 'Failed to set targets: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Show the form for editing a target
     */
    public function edit(Request $request, $id)
    {
        $user = $request->user();
        $target = Target::with('user.role')->findOrFail($id);
        if (!$target->user) {
            $routeBase = $this->getTargetsRouteBase($request);
            return redirect()
                ->route($routeBase . '.index', ['month' => $target->target_month->format('Y-m')])
                ->withErrors(['error' => 'This target belongs to a deleted/missing user. Please delete the target record.']);
        }

        $users = $this->buildEligibleUsersQuery($user)
            ->with('role')
            ->orderBy('name')
            ->get();

        $month = $target->target_month->format('Y-m');
        $users = $this->attachTargetPreviewMeta($users, $target->target_month);

        return view('admin.targets.edit', compact('target', 'users', 'month'));
    }

    /**
     * Update the specified target
     */
    public function update(Request $request, $id)
    {
        $target = Target::with('user.role')->findOrFail($id);
        if (!$target->user) {
            $routeBase = $this->getTargetsRouteBase($request);
            return redirect()
                ->route($routeBase . '.index', ['month' => $target->target_month->format('Y-m')])
                ->withErrors(['error' => 'Cannot update this target because the user is missing. Please delete the target record.']);
        }
        $routeBase = $this->getTargetsRouteBase($request);

        $validated = $request->validate([
            'target_prospects_extract' => 'nullable|integer|min:0',
            'target_prospects_verified' => 'nullable|integer|min:0',
            'target_calls' => 'nullable|integer|min:0',
            'target_visits' => 'nullable|integer|min:0',
            'target_meetings' => 'nullable|integer|min:0',
            'target_closers' => 'nullable|integer|min:0',
            'manager_target_calculation_logic' => 'nullable|in:juniors_sum,individual_plus_team',
            'manager_junior_scope' => 'nullable|in:executives_only,executives_and_telecallers',
            'incentive_per_closer' => 'nullable|numeric|min:0',
            'incentive_per_visit' => 'nullable|numeric|min:0',
        ]);

        try {
            // For Senior Managers and Assistant Sales Managers, set prospect/call targets to 0
            $isManagerRole = $this->usesProspectlessTargets($target->user);
            
            $target->update([
                'target_prospects_extract' => $isManagerRole ? 0 : ($validated['target_prospects_extract'] ?? 0),
                'target_prospects_verified' => $isManagerRole ? 0 : ($validated['target_prospects_verified'] ?? 0),
                'target_calls' => $isManagerRole ? 0 : ($validated['target_calls'] ?? 0),
                'target_visits' => $validated['target_visits'] ?? 0,
                'target_meetings' => $validated['target_meetings'] ?? 0,
                'target_closers' => $validated['target_closers'] ?? 0,
                'manager_target_calculation_logic' => $validated['manager_target_calculation_logic'] ?? null,
                'manager_junior_scope' => $validated['manager_junior_scope'] ?? null,
                'incentive_per_closer' => $validated['incentive_per_closer'] ?? null,
                'incentive_per_visit' => $validated['incentive_per_visit'] ?? null,
            ]);

            return redirect()
                ->route($routeBase . '.index', ['month' => $target->target_month->format('Y-m')])
                ->with('success', 'Target updated successfully');

        } catch (\Exception $e) {
            return back()
                ->withErrors(['error' => 'Failed to update target: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Remove the specified target
     */
    public function destroy(Request $request, $id)
    {
        $routeBase = $this->getTargetsRouteBase($request);
        $target = Target::findOrFail($id);
        $month = $target->target_month->format('Y-m');
        
        try {
            $target->delete();

            return redirect()
                ->route($routeBase . '.index', ['month' => $month])
                ->with('success', 'Target deleted successfully');

        } catch (\Exception $e) {
            return back()
                ->withErrors(['error' => 'Failed to delete target: ' . $e->getMessage()]);
        }
    }

    public function copyPrevious(Request $request)
    {
        $routeBase = $this->getTargetsRouteBase($request);
        $validated = $request->validate([
            'month' => 'required|date_format:Y-m',
            'mode' => 'required|in:skip_existing,overwrite_existing',
        ]);

        $user = $request->user();
        $destinationMonth = Carbon::parse($validated['month'] . '-01')->startOfMonth();
        $sourceMonth = $destinationMonth->copy()->subMonthNoOverflow();

        $sourceTargets = $this->buildMonthTargetsQuery($user, $sourceMonth)
            ->with('user.role')
            ->whereHas('user')
            ->get()
            ->filter(function (Target $target) use ($user) {
                return $target->user
                    && ($this->isEligibleTargetUser($target->user)
                        || (!$user->isSalesHead() && $target->user->isTelecaller()));
            })
            ->values();

        if ($sourceTargets->isEmpty()) {
            return redirect()
                ->route($routeBase . '.index', ['month' => $destinationMonth->format('Y-m')])
                ->withErrors([
                    'error' => 'No previous month targets found for ' . $sourceMonth->format('M Y') . '.',
                ]);
        }

        try {
            $summary = $this->targetService->copyTargetsToMonth(
                $sourceMonth->format('Y-m'),
                $destinationMonth->format('Y-m'),
                $sourceTargets->all(),
                $validated['mode']
            );

            $message = sprintf(
                'Copied %d, updated %d, skipped %d targets from %s to %s.',
                $summary['copied'],
                $summary['updated'],
                $summary['skipped'],
                $sourceMonth->format('M Y'),
                $destinationMonth->format('M Y')
            );

            return redirect()
                ->route($routeBase . '.index', ['month' => $destinationMonth->format('Y-m')])
                ->with('success', $message);
        } catch (\Throwable $e) {
            return redirect()
                ->route($routeBase . '.index', ['month' => $destinationMonth->format('Y-m')])
                ->withErrors(['error' => 'Failed to copy previous month targets: ' . $e->getMessage()]);
        }
    }

    /**
     * Bulk set targets for multiple users
     */
    public function bulkSet(Request $request)
    {
        $routeBase = $this->getTargetsRouteBase($request);
        $currentUser = $request->user();
        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'month' => 'required|date_format:Y-m',
            'target_prospects_extract' => 'nullable|integer|min:0',
            'target_prospects_verified' => 'nullable|integer|min:0',
            'target_calls' => 'nullable|integer|min:0',
            'target_visits' => 'nullable|integer|min:0',
            'target_meetings' => 'nullable|integer|min:0',
            'target_closers' => 'nullable|integer|min:0',
        ]);

        // Verify all users are allowed based on role
        $users = User::whereIn('id', $validated['user_ids'])->get();
        foreach ($users as $user) {
            if ($currentUser->isSalesHead()) {
                // Sales Head can set targets for Sales Executive, Senior Manager, and Assistant Sales Manager
                if (!$this->isEligibleTargetUser($user)) {
                    return back()->withErrors(['user_ids' => "Targets can only be set for Senior Managers, Sales Executives, and Assistant Sales Managers. User {$user->name} is not allowed."])->withInput();
                }
            } else {
                // Admin/CRM can set targets for Telecallers, Sales Executives, Senior Managers, and Assistant Sales Managers
                if (!$this->isEligibleTargetUser($user) && !$user->isTelecaller()) {
                    return back()->withErrors(['user_ids' => "Targets can only be set for Telecallers, Sales Executives, Senior Managers, and Assistant Sales Managers. User {$user->name} is not allowed."])->withInput();
                }
            }
        }

        try {
            $this->targetService->bulkSetTargets(
                $validated['user_ids'],
                $validated['month'],
                [
                    'target_prospects_extract' => $validated['target_prospects_extract'] ?? 0,
                    'target_prospects_verified' => $validated['target_prospects_verified'] ?? 0,
                    'target_calls' => $validated['target_calls'] ?? 0,
                    'target_visits' => $validated['target_visits'] ?? 0,
                    'target_meetings' => $validated['target_meetings'] ?? 0,
                    'target_closers' => $validated['target_closers'] ?? 0,
                ]
            );

            return redirect()
                ->route($routeBase . '.index', ['month' => $validated['month']])
                ->with('success', 'Targets set successfully for ' . count($validated['user_ids']) . ' users');

        } catch (\Exception $e) {
            return back()
                ->withErrors(['error' => 'Failed to set targets: ' . $e->getMessage()])
                ->withInput();
        }
    }

    private function buildMonthTargetsQuery(User $user, Carbon $month): Builder
    {
        $query = Target::query()
            ->whereYear('target_month', $month->year)
            ->whereMonth('target_month', $month->month)
            ->whereHas('user', function ($userQuery) use ($user) {
                $this->applyEligibleTargetUserConstraints($userQuery, $user);
            });

        if ($user->isSalesHead()) {
            $teamMemberIds = $user->getAllTeamMemberIds();
            if (empty($teamMemberIds)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('user_id', $teamMemberIds);
            }
        }

        return $query;
    }

    private function buildEligibleUsersQuery(User $user): Builder
    {
        $query = User::query()->where('is_active', true);
        $this->applyEligibleTargetUserConstraints($query, $user);

        if ($user->isSalesHead()) {
            $teamMemberIds = $user->getAllTeamMemberIds();
            if (empty($teamMemberIds)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('id', $teamMemberIds);
            }
        }

        return $query;
    }

    private function applyEligibleTargetUserConstraints(Builder $query, User $currentUser): void
    {
        $allowedRoles = [Role::SALES_MANAGER, Role::SENIOR_MANAGER, Role::ASSISTANT_SALES_MANAGER, Role::SALES_EXECUTIVE];
        if (!$currentUser->isSalesHead()) {
            $allowedRoles[] = Role::TELECALLER;
        }

        $query->whereHas('role', function ($roleQuery) use ($allowedRoles) {
            $roleQuery->whereIn('slug', $allowedRoles);
        });
    }

    private function isEligibleTargetUser(User $user): bool
    {
        return $user->isSalesManager()
            || $user->isSeniorManager()
            || $user->isAssistantSalesManager()
            || $user->isSalesExecutive();
    }

    private function usesProspectlessTargets(User $user): bool
    {
        return $user->isSalesManager()
            || $user->isSeniorManager()
            || $user->isAssistantSalesManager();
    }

    private function attachTargetPreviewMeta($users, Carbon $targetMonth)
    {
        return $users->map(function (User $user) use ($targetMonth) {
            $target = Target::where('user_id', $user->id)
                ->where('target_month', $targetMonth)
                ->with('user.role')
                ->first();

            if ($target) {
                $user->setAttribute('target_preview', [
                    'visits' => $target->getTargetBreakdown('visits'),
                    'meetings' => $target->getTargetBreakdown('meetings'),
                    'closers' => $target->getTargetBreakdown('closers'),
                ]);
            } else {
                $user->setAttribute('target_preview', [
                    'visits' => ['self' => 0, 'team' => 0, 'final' => 0, 'uses_team_sum' => $user->isSalesManager() || $user->isSeniorManager()],
                    'meetings' => ['self' => 0, 'team' => 0, 'final' => 0, 'uses_team_sum' => $user->isSalesManager() || $user->isSeniorManager()],
                    'closers' => ['self' => 0, 'team' => 0, 'final' => 0, 'uses_team_sum' => $user->isSalesManager() || $user->isSeniorManager()],
                ]);
            }

            return $user;
        });
    }
}

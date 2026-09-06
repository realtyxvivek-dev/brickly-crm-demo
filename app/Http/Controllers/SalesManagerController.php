<?php

namespace App\Http\Controllers;

use App\Services\DynamicFormService;
use App\Services\AttendanceAccessService;
use App\Services\KycFormSchemaService;
use App\Services\LeaveService;
use App\Services\ProductiveDayTrackerService;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\LeaveBalance;
use App\Models\Meeting;
use App\Models\SiteVisit;
use App\Models\Task;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use App\Models\SalesManagerProfile;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class SalesManagerController extends Controller
{
    private function shouldUseAsmErpDashboard(User $user): bool
    {
        return $user->isAssistantSalesManager();
    }

    private function canCustomizeDashboard(User $user): bool
    {
        return $user->isAssistantSalesManager() || $user->isSeniorManager();
    }

    private function defaultDashboardVisibility(): array
    {
        return [
            'today_focus_panel' => true,
            'today_focus_fresh_leads' => true,
            'today_focus_overdue' => true,
            'today_focus_meetings' => true,
            'today_focus_site_visits' => true,
            'today_focus_follow_ups' => true,
            'favorites_panel' => true,
            'stat_leads_received' => true,
            'stat_todays_prospects' => true,
            'stat_pending_verifications' => true,
            'stat_overdue_tasks' => true,
            'stat_team_members' => true,
            'stat_pending_tasks' => true,
            'stat_no_response_yet' => true,
            'no_response_section' => true,
            'manager_targets_section' => true,
            'team_targets_section' => true,
            'team_members_cards_section' => true,
            'incentives_section' => true,
            'chatbot_widget' => false,
        ];
    }

    private function isAsmChatbotEnabledForUser(User $user): bool
    {
        if (!$this->canCustomizeDashboard($user)) {
            return true;
        }

        $visibility = $this->getAsmDashboardVisibilityForUser($user);
        return (bool) ($visibility['chatbot_widget'] ?? false);
    }

    private function defaultSectionViewPreferences(): array
    {
        return [
            'leads' => 'list',
            'prospects' => 'card',
            'meetings' => 'card',
            'site_visits' => 'list',
            'tasks' => 'card',
        ];
    }

    private function getAsmDashboardVisibilityForUser(User $user): array
    {
        $defaults = $this->defaultDashboardVisibility();

        if (!$this->canCustomizeDashboard($user)) {
            return $defaults;
        }

        $profile = SalesManagerProfile::firstOrCreate(
            ['user_id' => $user->id],
            ['preferences' => []]
        );

        $saved = is_array($profile->preferences['dashboard_visibility'] ?? null)
            ? $profile->preferences['dashboard_visibility']
            : [];

        return array_merge($defaults, $saved);
    }

    private function getAsmSectionViewPreferencesForUser(User $user): array
    {
        $defaults = $this->defaultSectionViewPreferences();

        if (!$this->canCustomizeDashboard($user)) {
            return $defaults;
        }

        $profile = SalesManagerProfile::firstOrCreate(
            ['user_id' => $user->id],
            ['preferences' => []]
        );

        $saved = is_array($profile->preferences['section_view_preferences'] ?? null)
            ? $profile->preferences['section_view_preferences']
            : [];

        $normalized = [];
        foreach ($defaults as $key => $default) {
            $value = $saved[$key] ?? $default;
            $normalized[$key] = $value === 'list' ? 'list' : 'card';
        }

        return $normalized;
    }

    private function persistAsmDashboardVisibility(User $user, array $submitted): array
    {
        $defaults = $this->defaultDashboardVisibility();
        $allowedKeys = array_keys($defaults);

        $profile = SalesManagerProfile::firstOrCreate(
            ['user_id' => $user->id],
            ['preferences' => []]
        );

        $filtered = [];
        foreach ($allowedKeys as $key) {
            if (array_key_exists($key, $submitted)) {
                $filtered[$key] = filter_var($submitted[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                $filtered[$key] = $filtered[$key] === null ? (bool) $submitted[$key] : $filtered[$key];
            }
        }

        $preferences = is_array($profile->preferences) ? $profile->preferences : [];
        $preferences['dashboard_visibility'] = array_merge(
            $defaults,
            $preferences['dashboard_visibility'] ?? [],
            $filtered
        );

        $profile->preferences = $preferences;
        $profile->save();

        return $this->getAsmDashboardVisibilityForUser($user);
    }

    private function persistAsmSectionViewPreferences(User $user, array $submitted): array
    {
        $defaults = $this->defaultSectionViewPreferences();
        $allowedKeys = array_keys($defaults);

        $profile = SalesManagerProfile::firstOrCreate(
            ['user_id' => $user->id],
            ['preferences' => []]
        );

        $filtered = [];
        foreach ($allowedKeys as $key) {
            if (array_key_exists($key, $submitted)) {
                $filtered[$key] = $submitted[$key] === 'list' ? 'list' : 'card';
            }
        }

        $preferences = is_array($profile->preferences) ? $profile->preferences : [];
        $preferences['section_view_preferences'] = array_merge(
            $defaults,
            $preferences['section_view_preferences'] ?? [],
            $filtered
        );

        $profile->preferences = $preferences;
        $profile->save();

        return $this->getAsmSectionViewPreferencesForUser($user);
    }

    private function resolveDashboardGreeting(?CarbonInterface $moment = null): string
    {
        $hour = (int) ($moment ?? now())->format('G');

        if ($hour >= 6 && $hour < 12) {
            return 'Good morning';
        }

        if ($hour >= 12 && $hour < 17) {
            return 'Good afternoon';
        }

        if ($hour >= 17 && $hour < 21) {
            return 'Good evening';
        }

        return 'Good night';
    }

    private function todayProductivityPayload(User $user): ?array
    {
        $trackerService = app(ProductiveDayTrackerService::class);
        $profile = $trackerService->profileForUser($user);

        if (!$trackerService->isSalesProfile($profile)) {
            return null;
        }

        $today = now();
        $tracker = $trackerService->forUser($user, $today->copy()->startOfDay(), $today->copy()->endOfDay());
        $entry = $trackerService->forCell($tracker, $user->id, $today);

        return [
            'status' => $entry['status'],
            'label' => $entry['label'],
            'assigned' => $entry['assigned'],
            'verified' => $entry['verified'],
            'title' => $entry['title'],
            'date' => $today->toDateString(),
        ];
    }

    private function asmDashboardUserIds(User $user): array
    {
        $ids = collect([$user->id]);

        if ($user->isSalesManager() || $user->isSeniorManager()) {
            $ids = $ids->merge($user->teamMembers()->pluck('id'));
        }

        return $ids->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    private function leadScopeForAsmDashboard(array $userIds)
    {
        return Lead::query()->whereAssignedToUsers($userIds);
    }

    private function buildAsmErpDashboardPayload(User $user): array
    {
        return Cache::remember(
            "asm_erp_dashboard_payload:{$user->id}",
            now()->addSeconds(45),
            fn () => $this->buildFreshAsmErpDashboardPayload($user)
        );
    }

    private function buildFreshAsmErpDashboardPayload(User $user): array
    {
        $userIds = $this->asmDashboardUserIds($user);
        $today = now();
        $startOfDay = $today->copy()->startOfDay();
        $endOfDay = $today->copy()->endOfDay();
        $startOfMonth = $today->copy()->startOfMonth();
        $endOfMonth = $today->copy()->endOfMonth();

        $leadScope = $this->leadScopeForAsmDashboard($userIds);

        $todayLeads = (clone $leadScope)
            ->whereBetween('created_at', [$startOfDay, $endOfDay])
            ->count();

        $openTasks = Task::query()
            ->with('lead:id,name,phone')
            ->whereHas('lead')
            ->whereIn('assigned_to', $userIds)
            ->whereIn('status', Task::OPEN_STATUSES);

        $overdueTasks = (clone $openTasks)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<', $today->copy()->subMinutes(Task::OVERDUE_GRACE_MINUTES))
            ->count();
        $overdueTaskCutoff = $today->copy()->subMinutes(Task::OVERDUE_GRACE_MINUTES);
        $pendingTasks = max(0, (clone $openTasks)->count() - $overdueTasks);

        $todayMeetings = Meeting::query()
            ->whereIn('assigned_to', $userIds)
            ->whereBetween('scheduled_at', [$startOfDay, $endOfDay]);

        $todayVisits = SiteVisit::query()
            ->whereIn('assigned_to', $userIds)
            ->whereBetween('scheduled_at', [$startOfDay, $endOfDay]);

        $followUps = FollowUp::query()
            ->with('lead:id,name,phone,status')
            ->whereHas('lead')
            ->whereIn('created_by', $userIds)
            ->whereIn('status', ['scheduled', 'pending', 'rescheduled'])
            ->whereNull('completed_at');
        $followUpCount = (clone $followUps)->count();

        $todayPriorityTasks = (clone $openTasks)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>=', $overdueTaskCutoff)
            ->where('scheduled_at', '<=', $endOfDay);

        $todayPriorityFollowUps = (clone $followUps)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>=', $overdueTaskCutoff)
            ->where('scheduled_at', '<=', $endOfDay);

        $todayPriorityTaskCount = (clone $todayPriorityTasks)->count();
        $todayPriorityFollowUpCount = (clone $todayPriorityFollowUps)->count();
        $todayPriorityWorkCount = $todayPriorityTaskCount + $todayPriorityFollowUpCount;

        $monthClosures = SiteVisit::query()
            ->whereIn('assigned_to', $userIds)
            ->whereIn('closer_status', ['approved', 'verified'])
            ->where(function ($dateQuery) use ($startOfMonth, $endOfMonth) {
                if (\Illuminate\Support\Facades\Schema::hasColumn('site_visits', 'actual_closer_date')) {
                    $dateQuery->whereBetween('actual_closer_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
                        ->orWhere(function ($legacyQuery) use ($startOfMonth, $endOfMonth) {
                            $legacyQuery->whereNull('actual_closer_date')
                                ->whereBetween('closer_verified_at', [$startOfMonth, $endOfMonth]);
                        });
                    return;
                }

                $dateQuery->whereBetween('closer_verified_at', [$startOfMonth, $endOfMonth]);
            })
            ->count();

        $pipeline = [
            'fresh' => (clone $leadScope)->whereIn('status', ['new', 'fresh', 'fresh_transfer'])->count(),
            'follow_up' => (clone $leadScope)->whereIn('status', ['follow_up', 'followup'])->count(),
            'visit_done' => SiteVisit::query()
                ->whereIn('assigned_to', $userIds)
                ->where('status', 'completed')
                ->where('verification_status', 'verified')
                ->count(),
            'closed' => (clone $leadScope)->where('status', 'closed')->count(),
        ];

        $queueItems = collect();
        $formatTaskFocusItem = function (Task $task): array {
            $taskTitle = $task->title ?: ucfirst(str_replace('_', ' ', (string) $task->type));
            $detail = $task->lead?->phone
                ?: ($task->description ?: ($task->notes ?: ucfirst(str_replace('_', ' ', (string) $task->type))));

            return [
                'id' => $task->id,
                'title' => $task->lead?->name ?: $taskTitle ?: 'Lead task',
                'action_title' => $taskTitle,
                'detail' => $detail,
                'time' => $task->scheduled_at,
                'status' => ucfirst(str_replace('_', ' ', (string) $task->status)),
                'url' => route('sales-manager.tasks', ['task' => $task->id]),
            ];
        };

        (clone $todayPriorityTasks)
            ->orderByRaw('CASE WHEN scheduled_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('scheduled_at')
            ->limit(5)
            ->get()
            ->each(function (Task $task) use ($queueItems) {
                $taskTitle = $task->title ?: ucfirst(str_replace('_', ' ', (string) $task->type));
                $queueItems->push([
                    'type' => 'Task',
                    'title' => $task->lead?->name ?: $taskTitle ?: 'Lead task',
                    'action_title' => $taskTitle,
                    'detail' => $task->lead?->phone ?: ucfirst(str_replace('_', ' ', (string) $task->type)),
                    'time' => $task->scheduled_at,
                    'status' => ucfirst(str_replace('_', ' ', (string) $task->status)),
                    'url' => route('sales-manager.tasks', ['date_filter' => 'today', 'task' => $task->id]),
                ]);
            });

        (clone $todayPriorityFollowUps)
            ->orderBy('scheduled_at')
            ->limit(5)
            ->get()
            ->each(function (FollowUp $followUp) use ($queueItems) {
                $queueItems->push([
                    'type' => 'Follow-up',
                    'title' => $followUp->lead?->name ?: 'Follow-up',
                    'action_title' => 'Follow-up',
                    'detail' => $followUp->lead?->phone ?: ($followUp->notes ?: 'Next customer action'),
                    'time' => $followUp->scheduled_at,
                    'status' => 'Open',
                    'url' => route('sales-manager.leads'),
                ]);
            });

        $queueItems = $queueItems
            ->sortBy(fn ($item) => $item['time'] instanceof Carbon ? $item['time']->timestamp : PHP_INT_MAX)
            ->take(6)
            ->values();

        $pendingTaskItems = (clone $todayPriorityTasks)
            ->orderByRaw('CASE WHEN scheduled_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('scheduled_at')
            ->limit(3)
            ->get()
            ->map($formatTaskFocusItem);

        $overdueTaskItems = (clone $openTasks)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<', $overdueTaskCutoff)
            ->orderBy('scheduled_at')
            ->limit(3)
            ->get()
            ->map($formatTaskFocusItem);

        $scheduleItems = collect();

        (clone $todayMeetings)
            ->with('lead:id,name,phone')
            ->orderBy('scheduled_at')
            ->limit(6)
            ->get()
            ->each(function (Meeting $meeting) use ($scheduleItems) {
                $scheduleItems->push([
                    'type' => 'Meeting',
                    'title' => $meeting->customer_name ?: ($meeting->lead?->name ?: 'Customer meeting'),
                    'detail' => $meeting->location ?: ($meeting->lead?->phone ?: 'Today'),
                    'time' => $meeting->scheduled_at,
                    'url' => route('sales-manager.meetings'),
                ]);
            });

        (clone $todayVisits)
            ->with('lead:id,name,phone')
            ->orderBy('scheduled_at')
            ->limit(6)
            ->get()
            ->each(function (SiteVisit $visit) use ($scheduleItems) {
                $scheduleItems->push([
                    'type' => 'Visit',
                    'title' => $visit->customer_name ?: ($visit->lead?->name ?: 'Site visit'),
                    'detail' => $visit->project ?: ($visit->property_name ?: 'Today'),
                    'time' => $visit->scheduled_at,
                    'url' => route('sales-manager.site-visits'),
                ]);
            });

        $scheduleItems = $scheduleItems
            ->sortBy(fn ($item) => $item['time'] instanceof Carbon ? $item['time']->timestamp : PHP_INT_MAX)
            ->values();

        $alerts = collect([
            [
                'label' => 'Overdue tasks',
                'value' => $overdueTasks,
                'tone' => $overdueTasks > 0 ? 'danger' : 'neutral',
                'url' => route('sales-manager.tasks'),
            ],
            [
                'label' => 'Pending follow-ups',
                'value' => (clone $followUps)->count(),
                'tone' => 'warning',
                'url' => route('sales-manager.leads'),
            ],
            [
                'label' => 'Pending visit verification',
                'value' => SiteVisit::query()
                    ->whereIn('assigned_to', $userIds)
                    ->where('status', 'completed')
                    ->where('verification_status', 'pending')
                    ->count(),
                'tone' => 'warning',
                'url' => route('sales-manager.site-visits'),
            ],
        ]);

        $teamSnapshot = User::query()
            ->where('manager_id', $user->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(5)
            ->get(['id', 'name'])
            ->map(function (User $member) use ($startOfDay, $endOfDay) {
                return [
                    'name' => $member->name,
                    'fresh' => Lead::query()
                        ->whereAssignedToUsers([$member->id])
                        ->whereBetween('created_at', [$startOfDay, $endOfDay])
                        ->count(),
                    'tasks' => Task::query()
                        ->where('assigned_to', $member->id)
                        ->whereIn('status', Task::OPEN_STATUSES)
                        ->count(),
                ];
            });

        return [
            'greeting' => $this->resolveDashboardGreeting(),
            'userIds' => $userIds,
            'kpis' => [
                'today_leads' => $todayLeads,
                'open_tasks' => (clone $openTasks)->count(),
                'today_meetings' => (clone $todayMeetings)->count(),
                'today_visits' => (clone $todayVisits)->count(),
                'month_closures' => $monthClosures,
                'overdue_tasks' => $overdueTasks,
                'pending_tasks' => $pendingTasks,
                'priority_work' => $todayPriorityWorkCount,
            ],
            'pipeline' => $pipeline,
            'queueItems' => $queueItems,
            'taskFocus' => [
                'pending' => [
                    'count' => $todayPriorityTaskCount,
                    'items' => $pendingTaskItems,
                    'url' => route('sales-manager.tasks', ['status' => 'pending']),
                ],
                'overdue' => [
                    'count' => $overdueTasks,
                    'items' => $overdueTaskItems,
                    'url' => route('sales-manager.tasks', ['status' => 'overdue']),
                ],
                'followups' => [
                    'count' => $todayPriorityFollowUpCount,
                    'url' => route('sales-manager.tasks', ['focus' => 'followups']),
                ],
            ],
            'scheduleItems' => $scheduleItems,
            'alerts' => $alerts,
            'teamSnapshot' => $teamSnapshot,
            'generatedAt' => $today,
        ];
    }

    public function __construct()
    {
        $this->middleware('auth');
        
        $this->middleware(function ($request, $next) {
            $user = auth()->user();
            
            if (!$user) {
                return redirect()->route('login');
            }
            
            // Ensure role is loaded
            if (!$user->relationLoaded('role')) {
                $user->load('role');
            }

            view()->share('asmChatbotEnabled', $this->isAsmChatbotEnabledForUser($user));
            
            // Allow Admin, CRM, Sales Head, and Senior Manager to access
            // Only redirect Sales Head if they're trying to access sales-manager dashboard specifically
            if ($user->isSalesHead() && $request->routeIs('sales-manager.dashboard')) {
                return redirect()->route('sales-head.dashboard')->with('info', 'Redirected to Sales Head Dashboard');
            }
            
            return $next($request);
        });
    }

    /**
     * Show sales manager dashboard
     */
    public function dashboard()
    {
        $user = auth()->user();
        
        // Ensure role is loaded
        if (!$user->relationLoaded('role')) {
            $user->load('role');
        }
        
        // Double check - if user is Sales Head, redirect to Sales Head dashboard
        if ($user->isSalesHead()) {
            return redirect()->route('sales-head.dashboard')->with('info', 'Redirected to Sales Head Dashboard');
        }
        
        // Allow Senior Manager, Manager (senior_manager), and Assistant Sales Manager
        if (!$user->isSalesManager() && !$user->isSeniorManager() && !$user->isAssistantSalesManager()) {
            abort(403, 'Unauthorized. Only Senior Managers, Managers, or Assistant Sales Managers can access this page.');
        }
        
        $token = $this->salesManagerApiToken($user);

        if ($this->shouldUseAsmErpDashboard($user)) {
            return view('sales-manager.asm-dashboard-erp', [
                'api_token' => $token,
                'dashboard' => $this->buildAsmErpDashboardPayload($user),
            ]);
        }
        
        return view('sales-manager.dashboard', [
            'api_token' => $token,
            'greeting' => $this->resolveDashboardGreeting(),
            'dashboardVisibility' => $this->getAsmDashboardVisibilityForUser($user),
            'todayProductivity' => $this->todayProductivityPayload($user),
        ]);
    }

    /**
     * Show team page
     */
    public function team()
    {
        $user = auth()->user();

        $token = $this->salesManagerApiToken($user);
        
        return view('sales-manager.team', ['api_token' => $token]);
    }

    private function salesManagerApiToken(User $user): string
    {
        $sessionKey = 'sales_manager_web_token';
        $token = session($sessionKey);

        if (is_string($token) && $token !== '') {
            $storedToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
            if ($storedToken && (int) $storedToken->tokenable_id === (int) $user->id && $storedToken->tokenable_type === User::class) {
                return $token;
            }

            session()->forget($sessionKey);
        }

        $token = $user->createToken('sales-manager-web-token')->plainTextToken;
        session([$sessionKey => $token]);

        return $token;
    }

    /**
     * Show leads page
     */
    public function leads()
    {
        $user = auth()->user();
        $user->load('manager'); // Load manager relationship for team leader auto-fill
        
        $token = $this->salesManagerApiToken($user);
        
        return view('sales-manager.leads', [
            'api_token' => $token,
            'sectionViewPreferences' => $this->getAsmSectionViewPreferencesForUser($user),
        ]);
    }

    /**
     * Show prospects page
     */
    public function prospects(DynamicFormService $dynamicFormService)
    {
        $user = auth()->user();
        
        $token = $this->salesManagerApiToken($user);
        
        // Check for dynamic form for prospect verification
        $dynamicForm = $dynamicFormService->getPublishedFormByLocation('prospects.verify');
        
        // Check if route is unified route (prospects.index) or sales-manager route
        if (request()->routeIs('prospects.index')) {
            return view('prospects.index', ['api_token' => $token, 'dynamicForm' => $dynamicForm]);
        }
        
        return view('sales-manager.prospects', [
            'api_token' => $token,
            'dynamicForm' => $dynamicForm,
            'sectionViewPreferences' => $this->getAsmSectionViewPreferencesForUser($user),
        ]);
    }

    /**
     * Show prospect details page
     */
    public function showProspect($id)
    {
        $user = auth()->user();
        
        $prospect = \App\Models\Prospect::with([
            'telecaller',
            'manager',
            'lead',
            'createdBy',
            'verifiedBy',
            'interestedProjects'
        ])->findOrFail($id);
        
        // If prospect is verified and has a lead, redirect to lead detail page
        if ($prospect->lead_id) {
            return redirect()->route('leads.show', $prospect->lead_id);
        }
        
        $token = $this->salesManagerApiToken($user);
        
        return view('sales-manager.prospect-details', [
            'prospect' => $prospect,
            'api_token' => $token
        ]);
    }

    /**
     * Show reports page
     */
    public function reports()
    {
        return view('sales-manager.reports');
    }

    /**
     * Show profile page
     */
    public function profile()
    {
        return view('sales-manager.sections.profile');
    }

    public function attendance(AttendanceAccessService $attendanceAccessService, LeaveService $leaveService)
    {
        $user = auth()->user();

        if (!$user->isSalesManager() && !$user->isSeniorManager() && !$user->isAssistantSalesManager()) {
            abort(403, 'Unauthorized. Only Senior Managers, Managers, or Assistant Sales Managers can access this page.');
        }

        $attendanceAccessService->ensureEnabledFor($user);
        $leaveService->ensureBalancesForUser($user, now()->year);
        $leaveBalances = LeaveBalance::query()
            ->with('leaveType')
            ->where('user_id', $user->id)
            ->where('year', now()->year)
            ->whereHas('leaveType', fn ($query) => $query->where('is_active', true))
            ->get()
            ->sortBy(fn ($balance) => $balance->leaveType?->code ?: $balance->leaveType?->name ?: '');

        return view('sales-manager.attendance', [
            'todayProductivity' => $this->todayProductivityPayload($user),
            'leaveBalances' => $leaveBalances,
        ]);
    }

    /**
     * Show dashboard settings page for Assistant Sales Manager.
     */
    public function settings()
    {
        $user = auth()->user();

        if (!$this->canCustomizeDashboard($user)) {
            abort(403, 'Only Assistant Sales Managers and Senior Managers can access dashboard settings.');
        }

        return view('sales-manager.settings', [
            'dashboardVisibility' => $this->getAsmDashboardVisibilityForUser($user),
            'sectionViewPreferences' => $this->getAsmSectionViewPreferencesForUser($user),
        ]);
    }

    public function updateDashboardSettings(Request $request)
    {
        $user = auth()->user();

        if (!$this->canCustomizeDashboard($user)) {
            abort(403, 'Only Assistant Sales Managers and Senior Managers can update dashboard settings.');
        }

        $validated = $request->validate([
            'dashboard_visibility' => ['nullable', 'array'],
            'section_view_preferences' => ['nullable', 'array'],
        ]);

        $dashboardVisibility = $this->getAsmDashboardVisibilityForUser($user);
        $sectionViewPreferences = $this->getAsmSectionViewPreferencesForUser($user);

        if (array_key_exists('dashboard_visibility', $validated)) {
            $dashboardVisibility = $this->persistAsmDashboardVisibility(
                $user,
                $validated['dashboard_visibility']
            );
        }

        if (array_key_exists('section_view_preferences', $validated)) {
            $sectionViewPreferences = $this->persistAsmSectionViewPreferences(
                $user,
                $validated['section_view_preferences']
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Dashboard settings updated successfully.',
            'dashboard_visibility' => $dashboardVisibility,
            'section_view_preferences' => $sectionViewPreferences,
        ]);
    }

    /**
     * Show meetings page
     */
    public function meetings()
    {
        $user = auth()->user();
        
        $token = $this->salesManagerApiToken($user);
        
        // Check if route is unified route (meetings.index) or sales-manager route
        if (request()->routeIs('meetings.index')) {
            return view('meetings.index', ['api_token' => $token]);
        }
        
        return view('sales-manager.meetings', [
            'api_token' => $token,
            'sectionViewPreferences' => $this->getAsmSectionViewPreferencesForUser($user),
        ]);
    }

    /**
     * Show create meeting form
     */
    public function createMeeting(DynamicFormService $dynamicFormService)
    {
        $dynamicForm = $dynamicFormService->getPublishedFormByLocation('meetings.create');
        return view('sales-manager.create-meeting', ['dynamicForm' => $dynamicForm]);
    }

    /**
     * Show create site visit form
     */
    public function createSiteVisit(DynamicFormService $dynamicFormService)
    {
        $dynamicForm = $dynamicFormService->getPublishedFormByLocation('site-visits.create');
        return view('sales-manager.create-site-visit', ['dynamicForm' => $dynamicForm]);
    }

    /**
     * Show site visits page
     */
    public function siteVisits(KycFormSchemaService $kycFormSchemaService)
    {
        $user = auth()->user();
        
        $token = $this->salesManagerApiToken($user);
        $filterUsers = collect();

        if ($user->isAdmin() || $user->isCrm()) {
            $filterUsers = User::where('is_active', true)
                ->whereHas('role', function ($query) {
                    $query->whereIn('slug', ['sales_manager', 'senior_manager', 'assistant_sales_manager', 'sales_executive']);
                })
                ->with('role')
                ->orderBy('name')
                ->get();
        } elseif ($user->isSalesManager() || $user->isSeniorManager()) {
            $visibleIds = $user->teamMembers()->pluck('id')
                ->push($user->id)
                ->filter()
                ->unique()
                ->values();

            $filterUsers = User::whereIn('id', $visibleIds)
                ->with('role')
                ->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$user->id])
                ->orderBy('name')
                ->get();
        } elseif ($user->isAssistantSalesManager() || $user->isSalesExecutive()) {
            $filterUsers = collect([$user]);
        }
        
        // Check if route is unified route (site-visits.index) or sales-manager route
        if (request()->routeIs('site-visits.index')) {
            return view('site-visits.index', ['api_token' => $token]);
        }
        
        return view('sales-manager.site-visits', [
            'api_token' => $token,
            'sectionViewPreferences' => $this->getAsmSectionViewPreferencesForUser($user),
            'filterUsers' => $filterUsers,
            'kycFormSchema' => $kycFormSchemaService->getResolvedSchema(),
        ]);
    }

    /**
     * Show closed leads page
     */
    public function closedLeads(KycFormSchemaService $kycFormSchemaService)
    {
        $user = auth()->user();

        $token = $this->salesManagerApiToken($user);

        return view('sales-manager.closed', [
            'api_token' => $token,
            'kycFormSchema' => $kycFormSchemaService->getResolvedSchema(),
        ]);
    }

    /**
     * Show tasks page
     */
    public function tasks()
    {
        $user = auth()->user();
        
        $token = $this->salesManagerApiToken($user);
        
        return view('sales-manager.tasks', [
            'api_token' => $token,
            'sectionViewPreferences' => $this->getAsmSectionViewPreferencesForUser($user),
        ]);
    }

    public function editProspect($id)
    {
        $prospect = \App\Models\Prospect::findOrFail($id);
        return view('prospects.edit', compact('prospect'));
    }

    public function updateProspect(Request $request, $id)
    {
        $prospect = \App\Models\Prospect::findOrFail($id);
        $prospect->update($request->only([
            'customer_name','phone','email','budget',
            'preferred_location','property_type','notes',
            'project','lead_type'
        ]));
        return redirect()->route('prospects')->with('success', 'Prospect updated!');
    }

    public function destroyProspect($id)
    {
        $prospect = \App\Models\Prospect::findOrFail($id);
        $prospect->delete();
        return response()->json(['success' => true, 'message' => 'Prospect deleted!']);
    }
}

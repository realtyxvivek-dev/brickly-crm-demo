<?php

namespace App\Http\Controllers;

use App\Models\ExecutionSavedView;
use App\Models\ExecutionTask;
use App\Models\User;
use App\Services\ExecutionTaskService;
use App\Services\SelfTodoService;
use Illuminate\Http\Request;

class ExecutionDeskController extends Controller
{
    public function __construct(
        protected ExecutionTaskService $executionTaskService,
        protected SelfTodoService $selfTodoService
    )
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $filters = $this->executionTaskService->resolveFiltersFromRequest($request, $user);
        $currentView = $request->string('view')->toString() ?: 'list';
        if (!in_array($currentView, ['list', 'kanban'], true)) {
            $currentView = 'list';
        }
        $currentTab = $filters['tab'] ?? 'my_queue';
        if ($currentTab === 'self_todo' && !$user->canUseSelfTodoPilot()) {
            $currentTab = 'my_queue';
            $filters['tab'] = 'my_queue';
        }
        $tasks = $this->executionTaskService->listForUser($user, $filters);
        $selfTodoFilter = $request->string('todo_filter')->toString() ?: 'today';
        $selfTodoFilter = $this->selfTodoService->normalizeFilter($selfTodoFilter);
        $selfTodoCounts = $this->selfTodoService->countsForUser($user);
        $selfTodos = $this->selfTodoService->listForUser($user, $selfTodoFilter);

        $users = $this->assignableUsers($user);

        $savedViews = ExecutionSavedView::query()
            ->where(function ($query) use ($user): void {
                $query->where('user_id', $user->id)
                    ->orWhere('is_shared', true);
            })
            ->orderByDesc('is_shared')
            ->orderBy('name')
            ->get();

        $statsBase = $this->executionTaskService;
        $myQueueCount = (clone $statsBase->baseQueryForUser($user, 'my_queue'))->count();
        $assignedByMeCount = (clone $statsBase->baseQueryForUser($user, 'assigned_by_me'))->count();
        $teamAllCount = $user->canViewExecutionDeskTeamAll()
            ? (clone $statsBase->baseQueryForUser($user, 'team_all'))->count()
            : 0;

        return view('execution-desk.index', [
            'tasks' => $tasks,
            'filters' => $filters,
            'users' => $users,
            'savedViews' => $savedViews,
            'contexts' => config('execution-desk.contexts'),
            'priorities' => config('execution-desk.priorities'),
            'statuses' => config('execution-desk.statuses'),
            'openStatuses' => config('execution-desk.open_statuses'),
            'myQueueCount' => $myQueueCount,
            'assignedByMeCount' => $assignedByMeCount,
            'teamAllCount' => $teamAllCount,
            'currentView' => $currentView,
            'currentTab' => $currentTab,
            'selfTodos' => $selfTodos,
            'selfTodoFilter' => $selfTodoFilter,
            'selfTodoCounts' => $selfTodoCounts,
            'canUseSelfTodoPilot' => $this->selfTodoService->canUse($user),
        ]);
    }

    public function create(Request $request)
    {
        $user = $request->user();

        return view('execution-desk.create', [
            'users' => $this->assignableUsers($user),
            'contexts' => config('execution-desk.contexts'),
            'priorities' => config('execution-desk.priorities'),
        ]);
    }

    private function assignableUsers(User $user)
    {
        if ($user->isMarketingExecutive()) {
            return User::query()
                ->with('role')
                ->whereKey($user->id)
                ->get();
        }

        return User::query()
            ->with('role')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }
}

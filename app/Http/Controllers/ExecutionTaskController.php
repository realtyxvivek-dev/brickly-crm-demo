<?php

namespace App\Http\Controllers;

use App\Models\ExecutionTask;
use App\Models\User;
use App\Services\ExecutionTaskService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExecutionTaskController extends Controller
{
    public function __construct(protected ExecutionTaskService $executionTaskService)
    {
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'assigned_to' => [
                'required',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'priority' => 'required|in:' . implode(',', config('execution-desk.priorities')),
            'due_at' => 'nullable|date',
            'estimated_time_minutes' => 'nullable|integer|min:0',
            'context_label' => 'required|in:' . implode(',', array_keys(config('execution-desk.contexts'))),
            'is_private' => 'nullable|boolean',
            'checklist' => 'nullable|array',
            'checklist.*' => 'nullable|string|max:255',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240',
            'attachment_links' => 'nullable|array',
            'attachment_links.*.url' => 'nullable|url|max:2048',
            'attachment_links.*.title' => 'nullable|string|max:255',
        ]);

        if ($request->user()->isMarketingExecutive() && (int) $validated['assigned_to'] !== (int) $request->user()->id) {
            return back()
                ->withErrors(['assigned_to' => 'Marketing task can only be assigned to yourself.'])
                ->withInput();
        }

        $task = $this->executionTaskService->createTask($validated, $request->user());

        return redirect()->route('execution-desk.tasks.show', $task)->with('success', 'Execution task created.');
    }

    public function show(Request $request, ExecutionTask $task)
    {
        abort_unless($this->executionTaskService->userCanViewTask($request->user(), $task), 403);

        $task->load([
            'creator.role',
            'assignee.role',
            'waitingOnUser.role',
            'activities.user',
            'attachments.uploadedBy',
            'checklists.completedBy',
        ]);

        return view('execution-desk.show', [
            'task' => $task,
            'users' => User::query()->with('role')->where('is_active', true)->orderBy('name')->get(),
            'statuses' => config('execution-desk.statuses'),
            'allowedNextStatuses' => $this->executionTaskService->nextAllowedStatuses($task->status),
        ]);
    }

    public function updateStatus(Request $request, ExecutionTask $task)
    {
        $user = $request->user();
        abort_unless($this->executionTaskService->userCanViewTask($user, $task), 403);

        $validated = $request->validate([
            'status' => 'required|in:' . implode(',', config('execution-desk.statuses')),
            'waiting_reason' => 'nullable|string',
            'waiting_on_user' => [
                'nullable',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'reason' => 'nullable|string|max:1000',
        ]);

        if (($validated['status'] ?? null) === 'waiting') {
            $request->validate([
                'waiting_reason' => 'required|string',
                'waiting_on_user' => [
                    'required',
                    Rule::exists('users', 'id')->where(fn ($query) => $query->where('is_active', true)),
                ],
            ]);
        }

        if (in_array($validated['status'] ?? null, ['reopened', 'rejected'], true)) {
            $request->validate([
                'reason' => 'required|string|max:1000',
            ]);
        }

        if (($validated['status'] ?? null) === 'closed' && !$this->executionTaskService->userCanCloseTask($user, $task)) {
            abort(403);
        }

        try {
            $this->executionTaskService->updateStatus($task, $user, $validated['status'], $validated);
        } catch (\InvalidArgumentException $exception) {
            return back()->withErrors([
                'status' => 'Invalid status transition for the current task state.',
            ])->withInput();
        }

        return back()->with('success', 'Task status updated.');
    }

    public function reassign(Request $request, ExecutionTask $task)
    {
        $user = $request->user();
        abort_unless($this->executionTaskService->userCanViewTask($user, $task), 403);

        $validated = $request->validate([
            'assigned_to' => [
                'required',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
        ]);

        $this->executionTaskService->reassignTask($task, $user, (int) $validated['assigned_to']);

        return back()->with('success', 'Task reassigned.');
    }

    public function addNote(Request $request, ExecutionTask $task)
    {
        $user = $request->user();
        abort_unless($this->executionTaskService->userCanViewTask($user, $task), 403);

        $validated = $request->validate([
            'note' => 'required|string|max:5000',
        ]);

        $this->executionTaskService->addNote($task, $user, $validated['note']);

        return back()->with('success', 'Note added.');
    }
}

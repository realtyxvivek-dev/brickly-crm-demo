<?php

namespace App\Http\Controllers;

use App\Models\ExecutionTask;
use App\Models\ExecutionTaskChecklist;
use App\Services\ExecutionTaskService;
use Illuminate\Http\Request;

class ExecutionTaskChecklistController extends Controller
{
    public function __construct(protected ExecutionTaskService $executionTaskService)
    {
    }

    public function store(Request $request, ExecutionTask $task)
    {
        abort_unless($this->executionTaskService->userCanManageChecklist($request->user(), $task), 403);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $this->executionTaskService->createChecklistItem($task, $request->user(), $validated['title']);

        return back()->with('success', 'Checklist item added.');
    }

    public function update(Request $request, ExecutionTask $task, ExecutionTaskChecklist $item)
    {
        abort_if($item->task_id !== $task->id, 404);
        abort_unless($this->executionTaskService->userCanManageChecklist($request->user(), $task), 403);

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'is_completed' => 'nullable|boolean',
        ]);

        $this->executionTaskService->updateChecklistItem($item, $request->user(), $validated);

        return back()->with('success', 'Checklist updated.');
    }

    public function destroy(Request $request, ExecutionTask $task, ExecutionTaskChecklist $item)
    {
        abort_if($item->task_id !== $task->id, 404);
        abort_unless($this->executionTaskService->userCanManageChecklist($request->user(), $task), 403);

        $this->executionTaskService->deleteChecklistItem($item, $request->user());

        return back()->with('success', 'Checklist item deleted.');
    }
}

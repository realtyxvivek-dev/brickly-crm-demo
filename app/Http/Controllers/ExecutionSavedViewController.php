<?php

namespace App\Http\Controllers;

use App\Models\ExecutionSavedView;
use App\Services\ExecutionTaskService;
use Illuminate\Http\Request;

class ExecutionSavedViewController extends Controller
{
    public function __construct(protected ExecutionTaskService $executionTaskService)
    {
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'scope_tab' => 'required|string|max:30',
            'filters' => 'required|array',
            'is_shared' => 'nullable|boolean',
        ]);

        $this->executionTaskService->saveView($request->user(), $validated);

        return redirect()->route('execution-desk.index', ['tab' => $validated['scope_tab']])->with('success', 'Saved view created.');
    }

    public function destroy(Request $request, ExecutionSavedView $savedView)
    {
        $user = $request->user();
        abort_unless(
            $savedView->user_id === $user->id || ($user->isAdmin() && $savedView->is_shared),
            403
        );

        $savedView->delete();

        return back()->with('success', 'Saved view deleted.');
    }
}

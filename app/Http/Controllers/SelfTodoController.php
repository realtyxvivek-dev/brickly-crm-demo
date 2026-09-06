<?php

namespace App\Http\Controllers;

use App\Models\SelfTodo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SelfTodoController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $this->abortUnlessPilotUser($request);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'note' => ['nullable', 'string', 'max:1000'],
            'priority' => ['nullable', Rule::in(SelfTodo::PRIORITIES)],
            'due_at' => ['nullable', 'date'],
        ]);

        SelfTodo::query()->create([
            'user_id' => $request->user()->id,
            'title' => $validated['title'],
            'note' => $validated['note'] ?? null,
            'priority' => $validated['priority'] ?? 'medium',
            'due_at' => $validated['due_at'] ?? null,
            'status' => SelfTodo::STATUS_OPEN,
        ]);

        return redirect()
            ->route('execution-desk.index', ['tab' => 'self_todo'])
            ->with('success', 'Todo added.');
    }

    public function update(Request $request, SelfTodo $todo): RedirectResponse
    {
        $this->abortUnlessPilotUser($request);
        $this->abortUnlessOwner($request, $todo);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'note' => ['nullable', 'string', 'max:1000'],
            'priority' => ['required', Rule::in(SelfTodo::PRIORITIES)],
            'due_at' => ['nullable', 'date'],
            'status' => ['required', Rule::in([SelfTodo::STATUS_OPEN, SelfTodo::STATUS_COMPLETED])],
        ]);

        $statusChanged = $todo->status !== $validated['status'];
        $dueChanged = optional($todo->due_at)->toDateTimeString() !== (isset($validated['due_at']) ? (string) $validated['due_at'] : null);

        $todo->fill([
            'title' => $validated['title'],
            'note' => $validated['note'] ?? null,
            'priority' => $validated['priority'],
            'due_at' => $validated['due_at'] ?? null,
            'status' => $validated['status'],
            'completed_at' => $validated['status'] === SelfTodo::STATUS_COMPLETED
                ? ($todo->completed_at ?: now())
                : null,
        ]);

        if ($dueChanged || ($statusChanged && $validated['status'] === SelfTodo::STATUS_OPEN)) {
            $todo->reminder_sent_at = null;
        }

        $todo->save();

        return redirect()
            ->route('execution-desk.index', ['tab' => 'self_todo'])
            ->with('success', 'Todo updated.');
    }

    public function toggleComplete(Request $request, SelfTodo $todo): RedirectResponse
    {
        $this->abortUnlessPilotUser($request);
        $this->abortUnlessOwner($request, $todo);

        if ($todo->isCompleted()) {
            $todo->update([
                'status' => SelfTodo::STATUS_OPEN,
                'completed_at' => null,
                'reminder_sent_at' => null,
            ]);
        } else {
            $todo->update([
                'status' => SelfTodo::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);
        }

        return redirect()
            ->route('execution-desk.index', ['tab' => 'self_todo'])
            ->with('success', 'Todo status updated.');
    }

    public function destroy(Request $request, SelfTodo $todo): RedirectResponse
    {
        $this->abortUnlessPilotUser($request);
        $this->abortUnlessOwner($request, $todo);

        $todo->delete();

        return redirect()
            ->route('execution-desk.index', ['tab' => 'self_todo'])
            ->with('success', 'Todo deleted.');
    }

    private function abortUnlessPilotUser(Request $request): void
    {
        abort_unless($request->user()?->canUseSelfTodoPilot(), 404);
    }

    private function abortUnlessOwner(Request $request, SelfTodo $todo): void
    {
        abort_unless((int) $todo->user_id === (int) $request->user()->id, 404);
    }
}

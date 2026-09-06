<?php

namespace App\Http\Controllers;

use App\Models\SelfTodo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

class MarketingSelfTodoController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $this->abortUnlessMarketingUser($request);

        if (!Schema::hasTable('self_todos')) {
            return $this->redirectToTodos()
                ->withErrors(['title' => 'To-Do table is not ready. Please contact admin.'])
                ->withInput();
        }

        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:180'],
            'note' => ['nullable', 'string', 'max:1000'],
            'priority' => ['nullable', Rule::in(SelfTodo::PRIORITIES)],
            'due_at' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return $this->redirectToTodos()
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        try {
            SelfTodo::query()->create([
                'user_id' => $request->user()->id,
                'title' => $validated['title'],
                'note' => $validated['note'] ?? null,
                'priority' => $validated['priority'] ?? 'medium',
                'due_at' => $validated['due_at'] ?? null,
                'status' => SelfTodo::STATUS_OPEN,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return $this->redirectToTodos()
                ->withErrors(['title' => 'To-Do save nahi hua. Please retry karein ya admin ko batayein.'])
                ->withInput();
        }

        return $this->redirectToTodos()->with('success', 'Todo added.');
    }

    public function update(Request $request, SelfTodo $todo): RedirectResponse
    {
        $this->abortUnlessMarketingUser($request);
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

        return $this->redirectToTodos()->with('success', 'Todo updated.');
    }

    public function toggleComplete(Request $request, SelfTodo $todo): RedirectResponse
    {
        $this->abortUnlessMarketingUser($request);
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

        return $this->redirectToTodos()->with('success', 'Todo status updated.');
    }

    public function destroy(Request $request, SelfTodo $todo): RedirectResponse
    {
        $this->abortUnlessMarketingUser($request);
        $this->abortUnlessOwner($request, $todo);

        $todo->delete();

        return $this->redirectToTodos()->with('success', 'Todo deleted.');
    }

    private function abortUnlessMarketingUser(Request $request): void
    {
        abort_unless($request->user()?->isMarketingUser() && $request->user()?->canUseSelfTodoPilot(), 404);
    }

    private function abortUnlessOwner(Request $request, SelfTodo $todo): void
    {
        abort_unless((int) $todo->user_id === (int) $request->user()->id, 404);
    }

    private function redirectToTodos(): RedirectResponse
    {
        return redirect()->to(route('marketing.dashboard') . '#marketing-todos');
    }
}

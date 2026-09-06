<?php

namespace App\Services;

use App\Models\ExecutionSavedView;
use App\Models\ExecutionTask;
use App\Models\ExecutionTaskAttachment;
use App\Models\ExecutionTaskActivity;
use App\Models\ExecutionTaskChecklist;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ExecutionTaskService
{
    public function allowedStatusTransitions(): array
    {
        return [
            'open' => ['in_progress', 'rejected'],
            'in_progress' => ['waiting', 'completed', 'rejected'],
            'waiting' => ['in_progress', 'rejected'],
            'completed' => ['closed', 'reopened', 'rejected'],
            'reopened' => ['in_progress', 'rejected'],
        ];
    }

    public function nextAllowedStatuses(string $currentStatus): array
    {
        return $this->allowedStatusTransitions()[$currentStatus] ?? [];
    }

    public function listForUser(User $user, array $filters): LengthAwarePaginator
    {
        $tab = $filters['tab'] ?? 'my_queue';
        $query = $this->baseQueryForUser($user, $tab);
        $this->applyFilters($query, $filters);

        return $query
            ->orderByRaw("case when due_at is null then 1 else 0 end")
            ->orderBy('due_at')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();
    }

    public function baseQueryForUser(User $user, string $tab = 'my_queue'): Builder
    {
        $query = ExecutionTask::query()->with([
            'creator.role',
            'assignee.role',
            'waitingOnUser',
        ]);

        if ($tab === 'assigned_by_me') {
            return $query
                ->where('assigned_by', $user->id)
                ->where($this->visibilityClause($user));
        }

        if ($tab === 'team_all' && $user->canViewExecutionDeskTeamAll()) {
            return $query->where($this->visibilityClause($user));
        }

        return $query
            ->where('assigned_to', $user->id)
            ->where($this->visibilityClause($user));
    }

    public function visibilityClause(User $user): \Closure
    {
        return function (Builder $query) use ($user): void {
            $query->where(function (Builder $visibility) use ($user): void {
                $visibility->where('is_private', false);

                if ($user->isAdmin()) {
                    $visibility->orWhere('is_private', true);
                    return;
                }

                $visibility->orWhere(function (Builder $private) use ($user): void {
                    $private->where('is_private', true)
                        ->where(function (Builder $allowed) use ($user): void {
                            $allowed->where('assigned_by', $user->id)
                                ->orWhere('assigned_to', $user->id);
                        });
                });
            });
        };
    }

    public function applyFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (!empty($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        if (!empty($filters['assigned_by'])) {
            $query->where('assigned_by', $filters['assigned_by']);
        }

        if (!empty($filters['context_label'])) {
            $query->where('context_label', $filters['context_label']);
        }

        if (($filters['due_filter'] ?? null) === 'today') {
            $query->whereDate('due_at', now()->toDateString());
        }

        if (($filters['due_filter'] ?? null) === 'overdue') {
            $query->whereNotNull('due_at')
                ->where('due_at', '<', now())
                ->whereIn('status', config('execution-desk.open_statuses', []));
        }

        if (Arr::has($filters, 'is_private') && $filters['is_private'] !== '') {
            $query->where('is_private', (bool) $filters['is_private']);
        }

        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $searchQuery) use ($search): void {
                $searchQuery->where('title', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%')
                    ->orWhere('task_code', 'like', '%' . $search . '%');
            });
        }
    }

    public function createTask(array $validated, User $actor): ExecutionTask
    {
        return DB::transaction(function () use ($validated, $actor): ExecutionTask {
            $task = ExecutionTask::create([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'assigned_by' => $actor->id,
                'assigned_to' => (int) $validated['assigned_to'],
                'priority' => $validated['priority'],
                'status' => 'open',
                'due_at' => $validated['due_at'] ?? null,
                'estimated_time_minutes' => $validated['estimated_time_minutes'] ?? null,
                'context_label' => $validated['context_label'],
                'trigger_type' => 'manual',
                'is_private' => !empty($validated['is_private']),
                'last_status_changed_at' => now(),
            ]);

            $this->syncChecklist($task, $validated['checklist'] ?? [], $actor);
            $this->storeAttachments($task, $actor, $validated['attachments'] ?? [], $validated['attachment_links'] ?? []);
            $this->logActivity($task, $actor, 'created', 'Task created', [
                'status' => 'open',
                'assigned_to' => $task->assigned_to,
            ]);

            app(NotificationService::class)->notifyExecutionTaskAssigned($task);

            return $task->fresh(['creator', 'assignee', 'checklists']);
        });
    }

    public function storeAttachments(ExecutionTask $task, User $actor, array $files = [], array $links = []): void
    {
        foreach ($files as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            $storedPath = $file->storeAs(
                'execution_task_attachments',
                time() . '_' . uniqid() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName()),
                'public'
            );

            $attachment = $task->attachments()->create([
                'attachment_kind' => 'file',
                'file_path' => $storedPath,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'uploaded_by' => $actor->id,
            ]);

            $this->logActivity($task, $actor, 'attachment_added', 'Attachment uploaded', [
                'attachment_id' => $attachment->id,
                'attachment_kind' => 'file',
                'file_name' => $attachment->file_name,
            ]);
        }

        foreach ($links as $link) {
            $url = trim((string) ($link['url'] ?? ''));
            if ($url === '') {
                continue;
            }

            $title = trim((string) ($link['title'] ?? ''));
            $attachment = $task->attachments()->create([
                'attachment_kind' => 'link',
                'file_path' => $url,
                'file_name' => $title !== '' ? $title : $url,
                'file_size' => 0,
                'mime_type' => 'text/uri-list',
                'link_url' => $url,
                'link_title' => $title !== '' ? $title : null,
                'uploaded_by' => $actor->id,
            ]);

            $this->logActivity($task, $actor, 'attachment_added', 'Link added', [
                'attachment_id' => $attachment->id,
                'attachment_kind' => 'link',
                'link_url' => $url,
                'link_title' => $attachment->link_title,
            ]);
        }
    }

    public function addNote(ExecutionTask $task, User $actor, string $note): void
    {
        $this->logActivity($task, $actor, 'note_added', $note);
    }

    public function reassignTask(ExecutionTask $task, User $actor, int $assigneeId): ExecutionTask
    {
        return DB::transaction(function () use ($task, $actor, $assigneeId): ExecutionTask {
            $oldAssigneeId = $task->assigned_to;
            $task->update([
                'assigned_to' => $assigneeId,
            ]);

            $this->logActivity($task, $actor, 'reassigned', 'Task reassigned', [
                'from' => $oldAssigneeId,
                'to' => $assigneeId,
            ]);

            app(NotificationService::class)->notifyExecutionTaskAssigned($task->fresh(['creator', 'assignee']));

            return $task->fresh(['creator', 'assignee']);
        });
    }

    public function updateStatus(ExecutionTask $task, User $actor, string $status, array $payload = []): ExecutionTask
    {
        $currentStatus = $task->status;
        $nextStatuses = $this->nextAllowedStatuses($currentStatus);

        if ($status !== $currentStatus && !in_array($status, $nextStatuses, true)) {
            throw new \InvalidArgumentException('Invalid status transition.');
        }

        return DB::transaction(function () use ($task, $actor, $status, $payload, $currentStatus): ExecutionTask {
            $update = [
                'status' => $status,
                'last_status_changed_at' => now(),
                'waiting_reason' => null,
                'waiting_on_user' => null,
            ];

            $activityMeta = ['from' => $currentStatus, 'to' => $status];
            $message = 'Status changed from ' . $currentStatus . ' to ' . $status;

            if ($status === 'waiting') {
                $update['waiting_reason'] = $payload['waiting_reason'];
                $update['waiting_on_user'] = $payload['waiting_on_user'];
                $activityMeta['waiting_reason'] = $payload['waiting_reason'];
                $activityMeta['waiting_on_user'] = $payload['waiting_on_user'];
                $message = 'Task moved to waiting';
            }

            if ($status === 'completed') {
                $update['completed_at'] = now();
                $message = 'Task marked completed';
            }

            if ($status === 'closed') {
                $update['closed_at'] = now();
                $message = 'Task closed';
            }

            if ($status === 'reopened') {
                $update['completed_at'] = null;
                $update['closed_at'] = null;
                $activityMeta['reason'] = $payload['reason'] ?? null;
                $message = 'Task reopened';
            }

            if ($status === 'rejected') {
                $activityMeta['reason'] = $payload['reason'] ?? null;
                $message = 'Task rejected';
            }

            $task->update($update);
            $this->logActivity($task, $actor, 'status_changed', $message, $activityMeta);

            $notificationService = app(NotificationService::class);
            $freshTask = $task->fresh(['creator', 'assignee']);

            if ($status === 'completed') {
                $notificationService->notifyExecutionTaskCompleted($freshTask);
            } elseif ($status === 'closed') {
                $notificationService->notifyExecutionTaskClosed($freshTask);
            } elseif ($status === 'reopened') {
                $notificationService->notifyExecutionTaskReopened($freshTask, (string) ($payload['reason'] ?? ''));
            } elseif ($status === 'rejected') {
                $notificationService->notifyExecutionTaskRejected($freshTask, (string) ($payload['reason'] ?? ''));
            }

            return $freshTask;
        });
    }

    public function createChecklistItem(ExecutionTask $task, User $actor, string $title): ExecutionTaskChecklist
    {
        $nextSort = (int) $task->checklists()->max('sort_order') + 1;

        $item = $task->checklists()->create([
            'title' => $title,
            'sort_order' => $nextSort,
        ]);

        $this->logActivity($task, $actor, 'checklist_updated', 'Checklist item added', [
            'item_id' => $item->id,
            'title' => $title,
        ]);

        return $item;
    }

    public function updateChecklistItem(ExecutionTaskChecklist $item, User $actor, array $validated): ExecutionTaskChecklist
    {
        $payload = [];
        if (array_key_exists('title', $validated)) {
            $payload['title'] = $validated['title'];
        }

        if (array_key_exists('is_completed', $validated)) {
            $isCompleted = (bool) $validated['is_completed'];
            $payload['is_completed'] = $isCompleted;
            $payload['completed_at'] = $isCompleted ? now() : null;
            $payload['completed_by'] = $isCompleted ? $actor->id : null;
        }

        $item->update($payload);
        $this->logActivity($item->task, $actor, 'checklist_updated', 'Checklist item updated', [
            'item_id' => $item->id,
            'changes' => $payload,
        ]);

        return $item->fresh();
    }

    public function deleteChecklistItem(ExecutionTaskChecklist $item, User $actor): void
    {
        $task = $item->task;
        $title = $item->title;
        $item->delete();

        $this->logActivity($task, $actor, 'checklist_updated', 'Checklist item deleted', [
            'title' => $title,
        ]);
    }

    public function syncChecklist(ExecutionTask $task, array $items, User $actor): void
    {
        $sortOrder = 1;

        foreach ($items as $itemTitle) {
            $title = trim((string) $itemTitle);
            if ($title === '') {
                continue;
            }

            $task->checklists()->create([
                'title' => $title,
                'sort_order' => $sortOrder++,
            ]);
        }

        if ($sortOrder > 1) {
            $this->logActivity($task, $actor, 'checklist_updated', 'Checklist added during task creation');
        }
    }

    public function saveView(User $user, array $validated): ExecutionSavedView
    {
        return ExecutionSavedView::create([
            'user_id' => !empty($validated['is_shared']) ? $user->id : $user->id,
            'name' => $validated['name'],
            'scope_tab' => $validated['scope_tab'] ?? 'my_queue',
            'filters' => $validated['filters'],
            'is_shared' => !empty($validated['is_shared']) && $user->isAdmin(),
        ]);
    }

    public function userCanViewTask(User $user, ExecutionTask $task): bool
    {
        if ($task->is_private) {
            return $user->isAdmin() || $task->assigned_by === $user->id || $task->assigned_to === $user->id;
        }

        if ($task->assigned_by === $user->id || $task->assigned_to === $user->id) {
            return true;
        }

        return $user->canViewExecutionDeskTeamAll();
    }

    public function userCanCloseTask(User $user, ExecutionTask $task): bool
    {
        return $user->isAdmin() || $task->assigned_by === $user->id;
    }

    public function userCanManageChecklist(User $user, ExecutionTask $task): bool
    {
        return $user->isAdmin() || $task->assigned_by === $user->id || $task->assigned_to === $user->id;
    }

    public function resolveFiltersFromRequest(Request $request, User $user): array
    {
        $filters = [
            'tab' => $request->string('tab')->toString() ?: 'my_queue',
            'status' => $request->string('status')->toString() ?: null,
            'priority' => $request->string('priority')->toString() ?: null,
            'assigned_to' => $request->string('assigned_to')->toString() ?: null,
            'assigned_by' => $request->string('assigned_by')->toString() ?: null,
            'context_label' => $request->string('context_label')->toString() ?: null,
            'due_filter' => $request->string('due_filter')->toString() ?: null,
            'is_private' => $request->has('is_private') ? $request->input('is_private') : '',
            'search' => $request->string('search')->toString() ?: null,
        ];

        if ($request->filled('saved_view')) {
            $savedView = ExecutionSavedView::query()
                ->where('id', $request->input('saved_view'))
                ->where(function (Builder $query) use ($user): void {
                    $query->where('user_id', $user->id)
                        ->orWhere(function (Builder $shared) use ($user): void {
                            $shared->where('is_shared', true);
                            if (!$user->isAdmin()) {
                                $shared->whereNotNull('user_id');
                            }
                        });
                })
                ->first();

            if ($savedView) {
                $filters = array_merge($filters, $savedView->filters ?? []);
                $filters['tab'] = $savedView->scope_tab ?: ($filters['tab'] ?? 'my_queue');
            }
        }

        if (($filters['tab'] ?? 'my_queue') === 'team_all' && !$user->canViewExecutionDeskTeamAll()) {
            $filters['tab'] = 'my_queue';
        }

        return $filters;
    }

    public function logActivity(ExecutionTask $task, ?User $actor, string $type, string $message, array $meta = []): void
    {
        ExecutionTaskActivity::create([
            'task_id' => $task->id,
            'user_id' => $actor?->id,
            'type' => $type,
            'message' => $message,
            'meta' => $meta ?: null,
        ]);
    }
}

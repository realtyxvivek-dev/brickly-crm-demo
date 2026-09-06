<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Services\TaskService;
use App\Services\SiteVisitTaskDeletionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    protected $taskService;
    protected $siteVisitTaskDeletionService;

    public function __construct(TaskService $taskService, SiteVisitTaskDeletionService $siteVisitTaskDeletionService)
    {
        $this->taskService = $taskService;
        $this->siteVisitTaskDeletionService = $siteVisitTaskDeletionService;
    }

    /**
     * Update task status (for Kanban drag & drop)
     */
    public function updateStatus(Request $request, Task $task)
    {
        $request->validate([
            'status' => 'required|in:pending,in_progress,completed,cancelled'
        ]);

        try {
            $oldStatus = $task->status;
            $task->update(['status' => $request->status]);

            if ($request->status === 'completed' && !$task->completed_at) {
                $task->update(['completed_at' => now()]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Task status updated successfully',
                'task' => $task->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update task status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reschedule task
     */
    public function reschedule(Request $request, Task $task)
    {
        $request->validate([
            'scheduled_at' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:scheduled_at'
        ]);

        try {
            DB::beginTransaction();
            
            $task->update([
                'scheduled_at' => $request->scheduled_at,
                'due_date' => $request->due_date ?? $request->scheduled_at,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Task rescheduled successfully',
                'task' => $task->fresh()
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to reschedule task: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get tasks for Kanban view
     */
    public function kanban(Request $request)
    {
        $user = $request->user();
        
        $query = Task::with(['lead', 'assignedTo']);

        if ($user) {
            $query->where('assigned_to', $user->id);
        }

        // Apply filters
        if ($request->has('status') && $request->status) {
            $statuses = is_array($request->status) ? $request->status : explode(',', $request->status);
            $query->whereIn('status', $statuses);
        }

        $tasks = $query->get()->groupBy('status');

        return response()->json([
            'pending' => $tasks->get('pending', collect()),
            'in_progress' => $tasks->get('in_progress', collect()),
            'completed' => $tasks->get('completed', collect()),
        ]);
    }

    /**
     * Get tasks for Calendar view
     */
    public function calendar(Request $request)
    {
        $user = $request->user();
        $start = $request->get('start');
        $end = $request->get('end');

        $query = Task::with(['lead']);

        if ($user) {
            $query->where('assigned_to', $user->id);
        }

        if ($start) {
            $query->where('scheduled_at', '>=', $start);
        }

        if ($end) {
            $query->where('scheduled_at', '<=', $end);
        }

        $tasks = $query->get()->map(function($task) {
            return [
                'id' => $task->id,
                'title' => $task->title,
                'start' => $task->scheduled_at ? $task->scheduled_at->toIso8601String() : null,
                'end' => $task->due_date ? $task->due_date->toIso8601String() : null,
                'backgroundColor' => $this->getTaskColor($task->priority ?? 'medium', $task->status),
                'borderColor' => $this->getTaskColor($task->priority ?? 'medium', $task->status),
                'extendedProps' => [
                    'taskId' => $task->id,
                    'leadName' => $task->lead->name,
                    'status' => $task->status,
                    'priority' => $task->priority ?? 'medium'
                ]
            ];
        });

        return response()->json($tasks);
    }

    /**
     * Bulk actions on tasks
     */
    public function bulkAction(Request $request)
    {
        $request->validate([
            'task_ids' => 'required|array',
            'action' => 'required|in:complete,cancel,reschedule,delete,priority_change',
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            DB::beginTransaction();

            $taskIds = $request->task_ids;
            $action = $request->action;

            $tasks = Task::whereIn('id', $taskIds)->get();

            foreach ($tasks as $task) {
                switch ($action) {
                    case 'complete':
                        $task->markAsCompleted();
                        break;
                    case 'cancel':
                        $reasonText = trim((string) $request->input('reason', ''));
                        $reasonLine = 'Cancellation reason: ' . ($reasonText !== '' ? $reasonText : 'Cancelled via bulk action.');
                        $notes = trim(($task->notes ?? '') . PHP_EOL . $reasonLine);

                        $task->update([
                            'status' => 'cancelled',
                            'completed_at' => now(),
                            'notes' => $notes !== '' ? $notes : null,
                        ]);
                        break;
                    case 'reschedule':
                        if ($request->has('scheduled_at')) {
                            $task->update(['scheduled_at' => $request->scheduled_at]);
                        }
                        break;
                    case 'priority_change':
                        if ($request->has('priority')) {
                            $task->update(['priority' => $request->priority]);
                        }
                        break;
                    case 'delete':
                        $this->siteVisitTaskDeletionService->deleteLinkedScheduledVisit($task, $request->user());
                        $task->delete();
                        break;
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Bulk action completed successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to perform bulk action: ' . $e->getMessage()
            ], 500);
        }
    }

    private function getTaskColor($priority, $status)
    {
        if ($status === 'completed') return '#10b981';
        if ($status === 'cancelled') return '#ef4444';
        
        $colors = [
            'urgent' => '#ef4444',
            'high' => '#f97316',
            'medium' => '#eab308',
            'low' => '#6b7280'
        ];
        
        return $colors[$priority] ?? $colors['medium'];
    }
}

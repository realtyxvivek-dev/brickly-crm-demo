<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\Lead;
use App\Services\TaskService;
use App\Services\DynamicFormService;
use App\Services\SiteVisitTaskDeletionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class TaskController extends Controller
{
    protected $taskService;
    protected $dynamicFormService;

    public function __construct(TaskService $taskService, DynamicFormService $dynamicFormService)
    {
        $this->taskService = $taskService;
        $this->dynamicFormService = $dynamicFormService;
    }

    /**
     * Display a listing of user's tasks with advanced filtering
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        $query = Task::with(['lead', 'assignedTo', 'creator']);

        // Base filter: If user is authenticated, show only their tasks by default
        if ($user) {
            $query->where('assigned_to', $user->id);
        }

        // Filter by status
        if ($request->has('status') && $request->status) {
            $statuses = is_array($request->status) ? $request->status : explode(',', $request->status);
            if (in_array('overdue', $statuses)) {
                // Overdue tasks: scheduled_at more than 10 minutes ago and status is pending/in_progress
                $tenMinutesAgo = now()->subMinutes(10);
                $query->where(function($q) use ($statuses, $tenMinutesAgo) {
                    $statuses = array_diff($statuses, ['overdue']);
                    if (!empty($statuses)) {
                        $q->whereIn('status', $statuses)
                          ->orWhere(function($oq) use ($tenMinutesAgo) {
                              $oq->where('scheduled_at', '<', $tenMinutesAgo)
                                 ->whereIn('status', ['pending', 'in_progress']);
                          });
                    } else {
                        $q->where('scheduled_at', '<', $tenMinutesAgo)
                          ->whereIn('status', ['pending', 'in_progress']);
                    }
                });
            } else {
                $query->whereIn('status', $statuses);
            }
        }

        // Filter by type
        if ($request->has('type') && $request->type) {
            $types = is_array($request->type) ? $request->type : explode(',', $request->type);
            $query->whereIn('type', $types);
        }

        // Filter by priority
        if ($request->has('priority') && $request->priority) {
            $priorities = is_array($request->priority) ? $request->priority : explode(',', $request->priority);
            $query->whereIn('priority', $priorities);
        }

        // Filter by date range
        if ($request->has('date_from') && $request->date_from) {
            $query->where('scheduled_at', '>=', $request->date_from);
        }
        if ($request->has('date_to') && $request->date_to) {
            $query->where('scheduled_at', '<=', $request->date_to . ' 23:59:59');
        }

        // Search functionality
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('lead', function($leadQuery) use ($search) {
                      $leadQuery->where('name', 'like', "%{$search}%")
                               ->orWhere('phone', 'like', "%{$search}%");
                  });
            });
        }

        // Quick filters
        if ($request->has('filter') && $request->filter) {
            switch ($request->filter) {
                case 'today':
                    $query->whereDate('scheduled_at', today());
                    break;
                case 'overdue':
                    $query->where('scheduled_at', '<', now())
                          ->whereIn('status', ['pending', 'in_progress']);
                    break;
                case 'urgent':
                    $query->where('priority', 'urgent')
                          ->whereIn('status', ['pending', 'in_progress']);
                    break;
                case 'my_tasks':
                    if ($user) {
                        $query->where('assigned_to', $user->id);
                    }
                    break;
                case 'team_tasks':
                    if ($user && $user->teamMembers()->exists()) {
                        $teamMemberIds = $user->teamMembers()->pluck('id')->toArray();
                        $teamMemberIds[] = $user->id;
                        $query->whereIn('assigned_to', $teamMemberIds);
                    }
                    break;
            }
        }

        // Filter by assigned_to
        if ($request->has('assigned_to') && $request->assigned_to) {
            $query->where('assigned_to', $request->assigned_to);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'scheduled_at');
        $sortOrder = strtolower((string) $request->get('sort_order', 'asc'));
        $sortOrder = in_array($sortOrder, ['asc', 'desc'], true) ? $sortOrder : 'asc';
        
        if (in_array($sortBy, ['scheduled_at', 'due_date', 'priority', 'created_at', 'status'], true)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('scheduled_at', 'asc');
        }
        
        $query->orderBy('created_at', 'desc');

        // Get view type (list, kanban, calendar)
        $view = $request->get('view', 'list');

        // For Kanban and Calendar views, we need all tasks (not paginated)
        if (in_array($view, ['kanban', 'calendar'])) {
            $tasks = $query->get(); // Get all tasks without pagination
        } else {
            // Pagination for list view
            $perPage = min(100, max(1, (int) $request->get('per_page', 20)));
            $tasks = $query->paginate($perPage)->withQueryString();
        }

        // Get users for filter dropdown (for managers/admins)
        $users = [];
        if ($user && ($user->isAdmin() || $user->isCrm() || $user->isSalesManager())) {
            $users = \App\Models\User::where('is_active', true)
                ->orderBy('name')
                ->get();
        }

        // Check for dynamic form for task lead update
        $dynamicForm = $this->dynamicFormService->getPublishedFormByLocation('sales-manager.tasks.update-lead');

        return view('tasks.index', compact('tasks', 'view', 'users', 'dynamicForm'));
    }

    /**
     * Display the specified task
     */
    public function show(Task $task)
    {
        $task->load(['lead', 'assignedTo', 'creator', 'activities.user', 'attachments.uploadedBy']);
        
        return view('tasks.show', compact('task'));
    }

    /**
     * Complete a task and return lead data for popup form
     */
    public function complete(Request $request, Task $task)
    {
        $user = $request->user();
        
        // If user is authenticated, verify task belongs to user
        if ($user && $task->assigned_to !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $this->taskService->completeTask($task);
            $leadData = $this->taskService->getLeadDataForForm($task);

            return response()->json([
                'success' => true,
                'message' => 'Task completed successfully',
                'lead_data' => $leadData,
                'task_id' => $task->id,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete task: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update lead after call completion
     */
    public function updateLeadAfterCall(Request $request, Task $task)
    {
        $user = $request->user();
        
        // If user is authenticated, verify task belongs to user
        if ($user && $task->assigned_to !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($task->status !== 'completed') {
            return response()->json(['error' => 'Task must be completed first'], 400);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'pincode' => 'nullable|string|max:10',
            'preferred_location' => 'nullable|string|max:255',
            'preferred_size' => 'nullable|string|max:255',
            'use_end_use' => 'nullable|string',
            'investment' => 'nullable|numeric|min:0',
            'budget_min' => 'nullable|numeric|min:0',
            'budget_max' => 'nullable|numeric|min:0|gte:budget_min',
            'source' => 'nullable|in:' . implode(',', array_keys(\App\Models\Lead::sourceOptions())),
            'property_type' => 'nullable|in:apartment,villa,plot,commercial,other',
            'requirements' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $lead = $task->lead;
            $lead->update($validator->validated());

            // Update lead status to connected for fresh contact states
            if ($lead->status === Lead::STATUS_FRESH_TRANSFER) {
                $lead->acknowledgeFreshTransfer($request->user()?->id, 'manager_task_lead_update', 'connected');
                $lead->update([
                    'last_contacted_at' => now(),
                ]);
            } elseif ($lead->status === 'new') {
                $lead->update([
                    'status' => 'connected',
                    'last_contacted_at' => now(),
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Lead updated successfully',
                'lead' => $lead->fresh(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update lead: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update task
     */
    public function update(Request $request, Task $task)
    {
        $user = $request->user();
        
        if ($user && $task->assigned_to !== $user->id && !$user->isAdmin() && !$user->isCrm()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|in:pending,in_progress,completed,cancelled',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'scheduled_at' => 'sometimes|nullable|date',
            'due_date' => 'sometimes|nullable|date|after_or_equal:scheduled_at',
            'notes' => 'nullable|string',
        ]);

        try {
            $task->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Task updated successfully',
                'task' => $task->fresh()->load(['lead', 'assignedTo', 'creator'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update task: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete task
     */
    public function destroy(Request $request, Task $task, SiteVisitTaskDeletionService $siteVisitTaskDeletionService)
    {
        $user = $request->user();
        
        if ($user && $task->assigned_to !== $user->id && !$user->isAdmin() && !$user->isCrm()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            DB::beginTransaction();
            $deletedSiteVisit = $siteVisitTaskDeletionService->deleteLinkedScheduledVisit($task, $user);
            $task->delete();
            DB::commit();

            $message = $deletedSiteVisit
                ? 'Task and linked scheduled site visit deleted successfully'
                : 'Task deleted successfully';

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message
                ]);
            }

            return redirect()->route('tasks.index')
                ->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete task: ' . $e->getMessage()
                ], 500);
            }

            return back()->withErrors(['error' => 'Failed to delete task']);
        }
    }

    /**
     * Reschedule task
     */
    public function reschedule(Request $request, Task $task)
    {
        $user = $request->user();
        
        if ($user && $task->assigned_to !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'scheduled_at' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:scheduled_at'
        ]);

        try {
            DB::beginTransaction();

            $task->update([
                'rescheduled_from' => $task->scheduled_at,
                'scheduled_at' => $validated['scheduled_at'],
                'due_date' => $validated['due_date'] ?? $validated['scheduled_at'],
            ]);

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Task rescheduled successfully',
                    'task' => $task->fresh()
                ]);
            }

            return back()->with('success', 'Task rescheduled successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to reschedule task: ' . $e->getMessage()
                ], 500);
            }

            return back()->withErrors(['error' => 'Failed to reschedule task']);
        }
    }

    /**
     * Duplicate task
     */
    public function duplicate(Request $request, Task $task)
    {
        try {
            $newTask = $task->replicate();
            $newTask->status = 'pending';
            $newTask->completed_at = null;
            $newTask->scheduled_at = now()->addHours(1);
            $newTask->save();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Task duplicated successfully',
                    'task' => $newTask->fresh()
                ]);
            }

            return back()->with('success', 'Task duplicated successfully');
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to duplicate task: ' . $e->getMessage()
                ], 500);
            }

            return back()->withErrors(['error' => 'Failed to duplicate task']);
        }
    }

    /**
     * Cancel task
     */
    public function cancel(Request $request, Task $task)
    {
        $user = $request->user();
        
        if ($user && $task->assigned_to !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $validated = $request->validate([
                'reason' => 'nullable|string|max:500',
            ]);

            $reasonText = trim((string) ($validated['reason'] ?? ''));
            $reasonLine = 'Cancellation reason: ' . ($reasonText !== '' ? $reasonText : 'Cancelled manually by user.');
            $notes = trim(($task->notes ?? '') . PHP_EOL . $reasonLine);

            $task->update([
                'status' => 'cancelled',
                'completed_at' => now(),
                'notes' => $notes !== '' ? $notes : null,
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Task cancelled successfully',
                    'task' => $task->fresh()
                ]);
            }

            return back()->with('success', 'Task cancelled successfully');
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to cancel task: ' . $e->getMessage()
                ], 500);
            }

            return back()->withErrors(['error' => 'Failed to cancel task']);
        }
    }

    /**
     * Get task activities
     */
    public function activities(Request $request, Task $task)
    {
        $activities = $task->activities()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'activities' => $activities
            ]);
        }

        return view('tasks.partials.activity-log', compact('activities'));
    }

    /**
     * Upload attachment for task
     */
    public function uploadAttachment(Request $request, Task $task)
    {
        $user = $request->user();
        
        if ($user && $task->assigned_to !== $user->id && !$user->isAdmin() && !$user->isCrm()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'file' => 'required|file|max:10240', // 10MB max
        ]);

        try {
            DB::beginTransaction();

            $file = $request->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('task_attachments', $fileName, 'public');

            $attachment = \App\Models\TaskAttachment::create([
                'task_id' => $task->id,
                'file_path' => $filePath,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'uploaded_by' => $user->id,
            ]);

            // Log activity
            \App\Models\TaskActivity::create([
                'task_id' => $task->id,
                'user_id' => $user->id,
                'activity_type' => 'attachment_uploaded',
                'description' => "File '{$file->getClientOriginalName()}' uploaded",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'File uploaded successfully',
                    'attachment' => $attachment->load('uploadedBy')
                ]);
            }

            return back()->with('success', 'File uploaded successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to upload file: ' . $e->getMessage()
                ], 500);
            }

            return back()->withErrors(['error' => 'Failed to upload file']);
        }
    }

    /**
     * Delete attachment
     */
    public function deleteAttachment(Request $request, Task $task, \App\Models\TaskAttachment $attachment)
    {
        $user = $request->user();
        
        if ($user && $task->assigned_to !== $user->id && !$user->isAdmin() && !$user->isCrm()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($attachment->task_id !== $task->id) {
            return response()->json(['error' => 'Invalid attachment'], 400);
        }

        try {
            DB::beginTransaction();

            // Delete file from storage
            if (Storage::disk('public')->exists($attachment->file_path)) {
                Storage::disk('public')->delete($attachment->file_path);
            }

            // Log activity
            \App\Models\TaskActivity::create([
                'task_id' => $task->id,
                'user_id' => $user->id,
                'activity_type' => 'attachment_deleted',
                'description' => "File '{$attachment->file_name}' deleted",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            $attachment->delete();

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'File deleted successfully'
                ]);
            }

            return back()->with('success', 'File deleted successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete file: ' . $e->getMessage()
                ], 500);
            }

            return back()->withErrors(['error' => 'Failed to delete file']);
        }
    }

    /**
     * Download attachment
     */
    public function downloadAttachment(Task $task, \App\Models\TaskAttachment $attachment)
    {
        if ($attachment->task_id !== $task->id) {
            abort(404);
        }

        if (!Storage::disk('public')->exists($attachment->file_path)) {
            abort(404, 'File not found');
        }

        return Storage::disk('public')->download($attachment->file_path, $attachment->file_name);
    }
}

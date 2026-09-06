<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\User;
use App\Services\BulkCallingTaskService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmBulkCallingTaskController extends Controller
{
    public function __construct(private readonly BulkCallingTaskService $service)
    {
    }

    public function index()
    {
        return view('lead-assignment.calling-tasks', [
            'eligibleUsers' => $this->service->getEligibleUsers(),
        ]);
    }

    public function leads(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'assigned_user_id' => ['required', 'integer', 'exists:users,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $assignedUser = User::with('role')->findOrFail($validated['assigned_user_id']);
        if (!$this->service->isEligibleAssignee($assignedUser)) {
            return response()->json(['message' => 'Selected user cannot receive bulk calling tasks.'], 422);
        }

        $leads = $this->service->previewLeads($assignedUser, [], true, (int) ($validated['per_page'] ?? 50));

        return response()->json($leads);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'assigned_user_id' => ['required', 'integer', 'exists:users,id'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'gap_minutes' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lead_ids' => ['required', 'array', 'min:1'],
            'lead_ids.*' => ['integer', 'exists:leads,id'],
        ]);

        $leadIds = $validated['lead_ids'] ?? [];

        $startAt = Carbon::createFromFormat('Y-m-d H:i', $validated['start_date'] . ' ' . $validated['start_time']);
        if ($startAt === false || $startAt->lt(now()->subMinute())) {
            return response()->json(['message' => 'Start date and time must be in the future.'], 422);
        }

        $assignedUser = User::with('role')->findOrFail($validated['assigned_user_id']);
        if (!$this->service->isEligibleAssignee($assignedUser)) {
            return response()->json(['message' => 'Selected user cannot receive bulk calling tasks.'], 422);
        }

        $result = $this->service->createTasks(
            $assignedUser,
            $startAt,
            (int) $validated['gap_minutes'],
            $validated['notes'] ?? null,
            false,
            false,
            [],
            $leadIds
        );

        return response()->json([
            'message' => "Created {$result['created']} task(s). Skipped {$result['skipped']}.",
            'created' => $result['created'],
            'skipped' => $result['skipped'],
            'reason_counts' => $result['reason_counts'],
            'first_scheduled_at' => $result['first_scheduled_at'],
            'last_scheduled_at' => $result['last_scheduled_at'],
        ]);
    }
}

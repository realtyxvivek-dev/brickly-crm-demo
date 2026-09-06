<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FollowUp;
use App\Models\Lead;
use Carbon\Carbon;
use Illuminate\Http\Request;

class FollowUpController extends Controller
{
    private function normalizeScheduledAt(string $value): Carbon
    {
        $timezone = config('app.timezone');
        $normalizedValue = trim($value);

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $normalizedValue) === 1) {
            $format = strlen($normalizedValue) === 16 ? 'Y-m-d H:i' : 'Y-m-d H:i:s';
            return Carbon::createFromFormat($format, $normalizedValue, $timezone);
        }

        return Carbon::parse($normalizedValue)->setTimezone($timezone);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = FollowUp::with(['lead', 'creator']);

        // Role-based filtering
        if ($user->isSalesExecutive() || $user->isAssistantSalesManager()) {
            $query->where('created_by', $user->id);
        } elseif ($user->isSalesManager()) {
            $teamMemberIds = $user->teamMembers()->pluck('id');
            $query->whereIn('created_by', $teamMemberIds);
        }

        if ($request->has('lead_id')) {
            $query->where('lead_id', $request->lead_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $perPage = min(100, max(1, (int) $request->get('per_page', 15)));
        $followUps = $query->latest('scheduled_at')->paginate($perPage);

        return response()->json($followUps);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'lead_id' => 'required|exists:leads,id',
            'type' => 'required|in:call,email,meeting,site_visit,other',
            'notes' => 'nullable|string',
            'scheduled_at' => 'required|date',
        ]);

        $validated['scheduled_at'] = $this->normalizeScheduledAt((string) $validated['scheduled_at']);
        $validated['created_by'] = $request->user()->id;
        $validated['status'] = 'scheduled';
        $validated['notes'] = trim((string) ($validated['notes'] ?? ''));
        if ($validated['notes'] === '') {
            $validated['notes'] = 'Follow-up scheduled for ' . $validated['scheduled_at']->format('Y-m-d H:i');
        }

        $followUp = FollowUp::create($validated);
        app(\App\Services\LeadActiveWorkflowService::class)->moveLeadToWorkflow(
            $followUp->lead_id,
            'follow_up',
            $followUp->id
        );
        $followUp->load(['lead', 'creator']);

        // Update lead's next follow-up date
        $lead = Lead::find($validated['lead_id']);
        $lead->update(['next_followup_at' => $validated['scheduled_at']]);

        if ($lead->isFreshTransfer()) {
            $lead->acknowledgeFreshTransfer($request->user()->id, 'follow_up_created', 'connected');
        }

        return response()->json($followUp, 201);
    }

    public function update(Request $request, FollowUp $followUp)
    {
        $user = $request->user();

        // Check access
        if ($followUp->created_by !== $user->id && !$user->canViewAllLeads()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'type' => 'sometimes|in:call,email,meeting,site_visit,other',
            'notes' => 'nullable|string',
            'scheduled_at' => 'sometimes|date',
            'completed_at' => 'nullable|date',
            'status' => 'sometimes|in:scheduled,completed,missed,cancelled',
            'outcome' => 'nullable|string',
        ]);

        if (array_key_exists('scheduled_at', $validated)) {
            $validated['scheduled_at'] = $this->normalizeScheduledAt((string) $validated['scheduled_at']);
        }

        $followUp->update($validated);

        return response()->json($followUp->load(['lead', 'creator']));
    }

    public function complete(Request $request, FollowUp $followUp)
    {
        $user = $request->user();

        if ($followUp->created_by !== $user->id && !$user->canViewAllLeads()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $completedAt = now();
        $followUp->forceFill([
            'status' => 'completed',
            'completed_at' => $followUp->completed_at ?: $completedAt,
            'outcome' => $request->input('outcome', 'completed_from_reminder'),
        ])->save();

        \App\Models\Task::query()
            ->where('follow_up_id', $followUp->id)
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereNull('completed_at')
            ->update([
                'status' => 'completed',
                'completed_at' => $completedAt,
                'outcome' => 'completed',
                'outcome_recorded_at' => $completedAt,
                'updated_at' => $completedAt,
            ]);

        \App\Models\TelecallerTask::query()
            ->where('follow_up_id', $followUp->id)
            ->whereIn('status', ['pending', 'in_progress', 'rescheduled'])
            ->whereNull('completed_at')
            ->update([
                'status' => 'completed',
                'completed_at' => $completedAt,
                'outcome' => 'completed',
                'updated_at' => $completedAt,
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Follow-up task completed successfully.',
            'follow_up' => $followUp->fresh(['lead', 'creator']),
        ]);
    }

    public function destroy(FollowUp $followUp)
    {
        $user = request()->user();

        if ($followUp->created_by !== $user->id && !$user->canManageUsers()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $followUp->delete();

        return response()->json(['message' => 'Follow-up deleted successfully']);
    }
}

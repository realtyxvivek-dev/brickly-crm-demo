<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use App\Services\LeadAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OtherLeadsController extends Controller
{
    public function __construct(
        private readonly LeadAssignmentService $leadAssignmentService
    ) {
    }

    public function index(Request $request)
    {
        $query = Lead::query()
            ->where(function ($leadQuery) {
                $leadQuery->whereIn('status', ['junk', 'not_interested'])
                    ->orWhere('is_dead', true);
            })
            ->with([
                'otherLeadMarkedBy:id,name',
                'markedDeadBy:id,name',
                'assignments' => fn ($assignmentQuery) => $assignmentQuery
                    ->with(['assignedTo:id,name', 'assignedBy:id,name'])
                    ->latest('assigned_at'),
            ]);

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($leadQuery) use ($search) {
                $leadQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($type = $request->input('type')) {
            if ($type === 'dead') {
                $query->where('is_dead', true);
            } else {
                $query->where('status', $type);
            }
        }

        if ($assignedTo = (int) $request->input('assigned_to')) {
            $query->whereHas('assignments', function ($assignmentQuery) use ($assignedTo) {
                $assignmentQuery->where('assigned_to', $assignedTo);
            });
        }

        $otherLeads = $query
            ->orderByRaw('COALESCE(other_lead_marked_at, marked_dead_at) DESC')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $eligibleUsers = $this->eligibleUsers();

        return view('admin.other-leads', [
            'otherLeads' => $otherLeads,
            'eligibleUsers' => $eligibleUsers,
            'filterUsers' => $eligibleUsers,
        ]);
    }

    public function reassign(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'lead_ids' => 'required|array|min:1',
            'lead_ids.*' => 'required|integer|exists:leads,id',
            'assigned_to' => 'required|integer|exists:users,id',
        ]);

        $assignedTo = (int) $validated['assigned_to'];
        $adminId = (int) $request->user()->id;
        $success = 0;
        $errors = [];

        $leads = Lead::whereIn('id', $validated['lead_ids'])
            ->where(function ($leadQuery) {
                $leadQuery->whereIn('status', ['junk', 'not_interested'])
                    ->orWhere('is_dead', true);
            })
            ->get()
            ->keyBy('id');

        foreach ($validated['lead_ids'] as $leadId) {
            $lead = $leads->get($leadId);
            if (!$lead) {
                $errors[] = "Lead {$leadId} is not available in Other Leads.";
                continue;
            }

            $result = $this->leadAssignmentService->bulkAssignLeads([$lead->id], $assignedTo, $adminId);
            if (($result['success'] ?? 0) !== 1) {
                $errors = array_merge($errors, $result['errors'] ?? ["Lead {$lead->id} could not be reassigned."]);
                continue;
            }

            $lead->forceFill([
                'status' => Lead::STATUS_FRESH_TRANSFER,
                'is_dead' => false,
                'dead_reason' => null,
                'dead_at_stage' => null,
                'marked_dead_at' => null,
                'marked_dead_by' => null,
                'other_lead_marked_by' => null,
                'other_lead_marked_at' => null,
                'other_lead_reason' => null,
                'next_followup_at' => null,
                'status_auto_update_enabled' => true,
                'notes' => $this->appendAuditNote(
                    $lead->notes,
                    sprintf(
                        '[%s] Admin moved lead from Other Leads to active queue and reassigned to user #%d.',
                        now()->format('Y-m-d H:i:s'),
                        $assignedTo
                    )
                ),
            ])->save();

            $success++;
        }

        if ($success === 0) {
            return back()->with('error', $errors[0] ?? 'No lead could be reassigned.');
        }

        $message = "{$success} other lead(s) reassigned successfully.";
        if (!empty($errors)) {
            $message .= ' Some leads were skipped.';
        }

        return back()
            ->with('success', $message)
            ->with('warning', !empty($errors) ? implode(' ', array_slice($errors, 0, 5)) : null);
    }

    private function eligibleUsers()
    {
        $eligibleRoleIds = Role::whereIn('slug', [
            Role::SALES_EXECUTIVE,
            Role::SALES_MANAGER,
            Role::ASSISTANT_SALES_MANAGER,
        ])->pluck('id');

        return User::whereIn('role_id', $eligibleRoleIds)
            ->where('is_active', true)
            ->with('role')
            ->orderBy('name')
            ->get();
    }

    private function appendAuditNote(?string $existingNotes, string $note): string
    {
        $existingNotes = trim((string) $existingNotes);

        return $existingNotes === '' ? $note : "{$existingNotes}\n{$note}";
    }
}

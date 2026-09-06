<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AsmCnpAutomationAudit;
use App\Models\AsmCnpAutomationConfig;
use App\Models\AsmCnpAutomationPoolUser;
use App\Models\AsmCnpAutomationState;
use App\Models\AsmCnpAutomationUserOverride;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AsmCnpAutomationController extends Controller
{
    public function index()
    {
        $config = AsmCnpAutomationConfig::query()
            ->with(['poolUsers.user.role', 'overrides.fromUser.role', 'overrides.toUser.role'])
            ->firstOrFail();

        $asmUsers = User::query()
            ->with('role')
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->where('slug', Role::ASSISTANT_SALES_MANAGER))
            ->orderBy('name')
            ->get();

        $activeStates = AsmCnpAutomationState::query()
            ->with(['lead:id,name,phone,status', 'originalAssignee:id,name', 'currentAssignee:id,name'])
            ->latest('updated_at')
            ->limit(20)
            ->get();

        $recentAudits = AsmCnpAutomationAudit::query()
            ->with(['lead:id,name,phone', 'fromUser:id,name', 'toUser:id,name'])
            ->latest('acted_at')
            ->limit(25)
            ->get();

        $quarantinedLeads = Lead::query()
            ->with(['asmCnpHistories.user:id,name', 'activeAssignments.assignedTo:id,name'])
            ->whereNotNull('cnp_quarantined_at')
            ->whereNull('cnp_quarantine_cleared_at')
            ->latest('cnp_quarantined_at')
            ->limit(50)
            ->get();

        return view('admin.automation.cnp', compact('config', 'asmUsers', 'activeStates', 'recentAudits', 'quarantinedLeads'));
    }

    public function update(Request $request)
    {
        $config = AsmCnpAutomationConfig::query()->firstOrFail();

        $data = $request->validate([
            'is_enabled' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'create_retry_tasks' => 'nullable|boolean',
            'transfer_rule_mode' => 'required|in:count_only,count_window_restart,count_window_reset',
            'retry_delay_minutes' => 'required|integer|min:5|max:10080',
            'transfer_window_hours' => 'nullable|integer|min:1|max:720',
            'max_cnp_attempts' => 'required|integer|min:1|max:10',
            'fallback_routing' => 'required|in:round_robin',
            'quarantine_enabled' => 'nullable|boolean',
            'quarantine_after_unique_users' => 'required|integer|min:1|max:20',
            'quarantine_action' => 'required|in:unassign',
            'pool_user_ids' => 'nullable|array',
            'pool_user_ids.*' => 'integer|exists:users,id',
            'overrides' => 'nullable|array',
            'overrides.*.from_user_id' => 'nullable|integer|exists:users,id',
            'overrides.*.to_user_id' => 'nullable|integer|exists:users,id|different:overrides.*.from_user_id',
        ]);

        if ($data['transfer_rule_mode'] !== 'count_only' && empty($data['transfer_window_hours'])) {
            return back()
                ->withErrors(['transfer_window_hours' => 'Time window hours is required when window-based CNP transfer is selected.'])
                ->withInput();
        }

        DB::transaction(function () use ($config, $request, $data) {
            $config->update([
                'is_enabled' => true,
                'is_active' => $request->boolean('is_active'),
                'create_retry_tasks' => $request->boolean('create_retry_tasks', true),
                'transfer_rule_mode' => $data['transfer_rule_mode'],
                'retry_delay_minutes' => $data['retry_delay_minutes'],
                'transfer_window_hours' => $data['transfer_rule_mode'] === 'count_only'
                    ? null
                    : $data['transfer_window_hours'],
                'max_cnp_attempts' => $data['max_cnp_attempts'],
                'fallback_routing' => $data['fallback_routing'],
                'quarantine_enabled' => $request->boolean('quarantine_enabled', true),
                'quarantine_after_unique_users' => $data['quarantine_after_unique_users'],
                'quarantine_action' => $data['quarantine_action'],
                'updated_by' => auth()->id(),
            ]);

            AsmCnpAutomationPoolUser::query()->where('config_id', $config->id)->delete();
            foreach (array_values(array_unique($data['pool_user_ids'] ?? [])) as $index => $userId) {
                AsmCnpAutomationPoolUser::create([
                    'config_id' => $config->id,
                    'user_id' => $userId,
                    'is_active' => true,
                    'sort_order' => $index,
                ]);
            }

            AsmCnpAutomationUserOverride::query()->where('config_id', $config->id)->delete();
            foreach ($data['overrides'] ?? [] as $override) {
                if (empty($override['from_user_id']) || empty($override['to_user_id'])) {
                    continue;
                }

                AsmCnpAutomationUserOverride::create([
                    'config_id' => $config->id,
                    'from_user_id' => $override['from_user_id'],
                    'to_user_id' => $override['to_user_id'],
                    'is_active' => true,
                ]);
            }
        });

        return redirect()
            ->route('admin.automation.cnp.index')
            ->with('success', 'ASM CNP automation settings updated successfully.');
    }

    public function toggle(Request $request)
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);
        $enabled = (bool) $data['enabled'];

        AsmCnpAutomationConfig::query()->firstOrFail()->update([
            'is_enabled' => true,
            'is_active' => $enabled,
            'updated_by' => auth()->id(),
        ]);

        return redirect()
            ->route('admin.automation.index')
            ->with('success', 'ASM CNP auto transfer turned ' . ($enabled ? 'on' : 'off') . '. CNP marking and retry remain active.');
    }

    public function reactivate(Request $request, Lead $lead)
    {
        $data = $request->validate([
            'assigned_to' => ['required', 'integer', 'exists:users,id'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        abort_unless($lead->cnp_quarantined_at && !$lead->cnp_quarantine_cleared_at, 422, 'Lead is not in CNP quarantine.');

        $targetUser = User::query()->with('role')->whereKey($data['assigned_to'])->where('is_active', true)->firstOrFail();
        abort_unless($targetUser->isAssistantSalesManager(), 422, 'Select an active Assistant Sales Manager.');

        $config = AsmCnpAutomationConfig::query()->firstOrFail();
        $actorId = $request->user()->id;
        $note = trim((string) ($data['note'] ?? ''));

        DB::transaction(function () use ($lead, $targetUser, $config, $actorId, $note) {
            $lead->activeAssignments()->update([
                'is_active' => false,
                'unassigned_at' => now(),
            ]);

            $assignment = LeadAssignment::create([
                'lead_id' => $lead->id,
                'assigned_to' => $targetUser->id,
                'assigned_by' => $actorId,
                'assignment_type' => 'primary',
                'assignment_method' => 'manual',
                'notes' => trim('CNP quarantine reactivated by admin.' . ($note !== '' ? PHP_EOL . $note : '')),
                'assigned_at' => now(),
                'is_active' => true,
            ]);

            $lead->forceFill([
                'cnp_quarantine_cleared_at' => now(),
                'cnp_quarantine_cleared_by' => $actorId,
            ])->save();

            $lead->markAsFreshTransfer(null, $targetUser->id, $actorId, 'Reactivated from CNP quarantine.' . ($note !== '' ? ' ' . $note : ''));

            $task = Task::query()
                ->where('lead_id', $lead->id)
                ->where('assigned_to', $targetUser->id)
                ->where('type', 'phone_call')
                ->whereIn('status', ['pending', 'in_progress'])
                ->latest('id')
                ->first();

            if (!$task) {
                $task = Task::create([
                    'lead_id' => $lead->id,
                    'assigned_to' => $targetUser->id,
                    'type' => 'phone_call',
                    'title' => 'Reactivated CNP quarantine lead: ' . $lead->name,
                    'description' => 'Lead reactivated from CNP quarantine. Contact this lead as the new owner.',
                    'status' => 'pending',
                    'scheduled_at' => now(),
                    'created_by' => $actorId,
                    'notes' => trim('CNP quarantine reactivation task.' . ($note !== '' ? PHP_EOL . $note : '')),
                ]);
            }

            $state = AsmCnpAutomationState::query()
                ->where('lead_id', $lead->id)
                ->where('status', 'quarantined')
                ->latest('id')
                ->first();

            if ($state) {
                $state->update([
                    'lead_assignment_id' => $assignment->id,
                    'current_assigned_to' => $targetUser->id,
                    'last_retry_task_id' => $task->id,
                    'status' => 'reactivated',
                    'transfer_eligible' => false,
                    'last_processed_at' => now(),
                ]);
            }

            AsmCnpAutomationAudit::create([
                'state_id' => $state?->id,
                'lead_id' => $lead->id,
                'config_id' => $config->id,
                'from_user_id' => null,
                'to_user_id' => $targetUser->id,
                'task_id' => $task->id,
                'cnp_count' => $state?->cnp_count ?? 0,
                'action' => 'reactivated',
                'message' => 'Admin reactivated CNP quarantined lead.',
                'meta' => [
                    'assignment_id' => $assignment->id,
                    'note' => $note,
                ],
                'acted_at' => now(),
            ]);
        });

        return redirect()
            ->route('admin.automation.cnp.index')
            ->with('success', 'Quarantined lead reactivated and assigned successfully.');
    }
}

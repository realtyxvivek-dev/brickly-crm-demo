<?php

namespace App\Services;

use App\Models\{ActivityLog, Lead, LeadAssignment, Task, TelecallerTask, User};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\ValidationException;

class LeadReopenService
{
    public const ROLES = ['telecaller', 'sales_executive', 'sales_manager', 'senior_manager', 'assistant_sales_manager'];
    public const TABLES = ['tasks', 'telecaller_tasks', 'follow_ups', 'meetings', 'site_visits', 'prospects', 'crm_assignments'];

    public function candidates()
    {
        return User::where('is_active', true)->whereHas('role', fn ($q) => $q->whereIn('slug', self::ROLES))->orderBy('name');
    }

    public function version(Lead $lead): string
    {
        $attributes = $lead->getRawOriginal();
        ksort($attributes);
        return hash('sha256', json_encode([$attributes, $lead->assignments()->orderBy('id')->get(['id', 'assigned_to', 'is_active'])->toArray()]));
    }

    public function boundary(int $leadId): array
    {
        return $this->boundaries([$leadId])[$leadId] ?? [];
    }

    public function boundaries(array $leadIds): array
    {
        if (!Schema::hasTable('activity_logs')) return [];
        return ActivityLog::where('model_type', 'Lead')->whereIn('model_id', $leadIds)->where('action', 'lead_reopened')
            ->latest('id')->get(['id', 'model_id', 'new_values'])->unique('model_id')
            ->mapWithKeys(fn ($log) => [$log->model_id => $log->new_values['previous_record_max_ids'] ?? []])->all();
    }

    public function assertCurrentRecord(Model $record): void
    {
        if (!$record->exists || !$record->lead_id || !in_array($record->getTable(), self::TABLES, true)) return;
        $max = $this->boundary((int) $record->lead_id)[$record->getTable()] ?? 0;
        abort_if($max && $record->id <= $max, 409, 'This activity belongs to an earlier lead cycle. Refresh the lead and use its new task.');
    }

    public function reopen(int $leadId, User $actor, array $data): Lead
    {
        abort_unless($actor->isAdmin(), 403);
        return DB::transaction(function () use ($leadId, $actor, $data) {
            $lead = Lead::withTrashed()->lockForUpdate()->findOrFail($leadId);
            abort_if($lead->trashed() || $lead->is_blocked, 422, 'Blocked or deleted leads cannot be reopened here.');
            abort_unless(in_array($lead->status, ['junk', 'not_interested', 'dead'], true), 409, 'Lead stage has changed. Refresh the page.');
            abort_unless(hash_equals($this->version($lead), (string) $data['version']), 409, 'Lead details have changed. Refresh the page.');
            $owner = $this->candidates()->whereKey($data['assigned_to'])->lockForUpdate()->first();
            if (!$owner || !app(UserStatusService::class)->canUserReceiveLeads($owner->id)) {
                throw ValidationException::withMessages(['assigned_to' => 'Select an active sales user who is available to receive leads.']);
            }
            if (in_array($owner->role->slug, ['telecaller', 'sales_executive'], true)
                && !app(TelecallerStatusService::class)->canReceiveAssignment($owner->id)['can_receive']) {
                throw ValidationException::withMessages(['assigned_to' => 'This user is currently unavailable or has reached their lead capacity.']);
            }

            $reset = ['status' => 'new', 'is_dead' => false, 'dead_reason' => null, 'dead_at_stage' => null,
                'marked_dead_at' => null, 'marked_dead_by' => null, 'other_lead_marked_by' => null,
                'other_lead_marked_at' => null, 'other_lead_reason' => null, 'status_auto_update_enabled' => true,
                'next_followup_at' => null, 'cnp_count' => 0, 'pre_transfer_status' => null,
                'transferred_from_user_id' => null, 'transferred_to_user_id' => null, 'transferred_at' => null,
                'transfer_note' => null, 'cnp_quarantined_at' => null, 'cnp_quarantined_by' => null,
                'cnp_quarantine_reason' => null, 'cnp_quarantine_cleared_at' => null, 'cnp_quarantine_cleared_by' => null];
            $reset = array_intersect_key($reset, array_flip(Schema::getColumnListing('leads')));
            $old = $lead->only(array_keys($reset));
            $old['assignments'] = $lead->assignments()->where('is_active', true)->get(['id', 'assigned_to'])->toArray();
            $boundaries = [];
            $cancelled = [];
            foreach (self::TABLES as $table) {
                if (!Schema::hasTable($table)) continue;
                $boundaries[$table] = (int) DB::table($table)->where('lead_id', $lead->id)->max('id');
                if ($table === 'prospects') continue;
                if ($table === 'crm_assignments') {
                    // Legacy intake assignments have no cancelled status; close their pending queue entry only.
                    $ids = DB::table($table)->where('lead_id', $lead->id)->whereNull('deleted_at')->where('call_status', 'pending')->pluck('id')->all();
                    if ($ids) DB::table($table)->whereIn('id', $ids)->update(['call_status' => 'completed', 'updated_at' => now()]);
                    $cancelled[$table] = $ids;
                    continue;
                }
                $query = DB::table($table)->where('lead_id', $lead->id)->whereIn('status', ['pending', 'in_progress', 'rescheduled', 'scheduled', 'confirmed', 'overdue']);
                if (Schema::hasColumn($table, 'deleted_at')) $query->whereNull('deleted_at');
                if (Schema::hasColumn($table, 'completed_at')) $query->whereNull('completed_at');
                $ids = $query->pluck('id')->all();
                if ($ids) DB::table($table)->whereIn('id', $ids)->update(['status' => 'cancelled', 'updated_at' => now()]);
                $cancelled[$table] = $ids;
            }
            foreach (['asm_cnp_automation_states', 'new_lead_sla_automation_states'] as $table) {
                if (!Schema::hasTable($table)) continue;
                DB::table($table)->where('lead_id', $lead->id)->where('status', 'active')->update([
                    'status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => 'lead_reopened', 'updated_at' => now(),
                ]);
            }
            $lead->assignments()->where('is_active', true)->update(['is_active' => false, 'unassigned_at' => now()]);
            // Quiet save prevents external status callbacks before the transaction commits.
            $lead->forceFill($reset)->saveQuietly();
            $assignment = LeadAssignment::create(['lead_id' => $lead->id, 'assigned_to' => $owner->id,
                'assigned_by' => $actor->id, 'assignment_type' => 'primary', 'assignment_method' => 'admin_reopen',
                'notes' => json_encode(['reason' => $data['reason'], 'previous_record_max_ids' => $boundaries]),
                'assigned_at' => now(), 'is_active' => true]);
            $task = $this->createInitialTask($lead, $owner, $actor);
            if ($owner->isSalesExecutive()) {
                \App\Models\CrmAssignment::create(['lead_id' => $lead->id, 'customer_name' => $lead->name,
                    'phone' => $lead->phone, 'assigned_to' => $owner->id, 'assigned_by' => $actor->id,
                    'assigned_at' => now(), 'call_status' => 'pending', 'notes' => 'Admin reopened lead.']);
            }
            ActivityLog::create(['user_id' => $actor->id, 'action' => 'lead_reopened', 'model_type' => 'Lead',
                'model_id' => $lead->id, 'description' => 'Lead reopened as New: '.$data['reason'],
                'old_values' => $old, 'new_values' => ['status' => 'new', 'assigned_to' => $owner->id,
                    'assignment_id' => $assignment->id, 'task_id' => $task->id, 'task_model' => $task->getTable(),
                    'reason' => $data['reason'], 'previous_record_max_ids' => $boundaries,
                    'cancelled_records' => $cancelled, 'cleanup_reason' => 'lead_reopened'], 'ip_address' => request()->ip()]);
            app(NewLeadSlaAutomationService::class)->syncForAssignment($lead, $assignment);
            DB::afterCommit(function () use ($lead, $owner, $actor) {
                try { event(new \App\Events\LeadAssigned($lead->fresh(), $owner->id, $actor->id)); }
                catch (\Throwable $e) { report($e); }
            });
            return $lead;
        });
    }

    protected function createInitialTask(Lead $lead, User $owner, User $actor): Model
    {
        if (in_array($owner->role->slug, ['telecaller', 'sales_executive'], true)) {
            return app(TelecallerTaskService::class)->createCallingTask($lead, $owner, $actor->id);
        }
        return Task::create(['lead_id' => $lead->id, 'assigned_to' => $owner->id, 'created_by' => $actor->id,
            'type' => 'phone_call', 'title' => 'Call lead: '.$lead->name, 'description' => 'Initial call after Admin reopened lead.',
            'status' => 'pending', 'scheduled_at' => now()->addMinutes(10)]);
    }
}

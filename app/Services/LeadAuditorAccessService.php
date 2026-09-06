<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class LeadAuditorAccessService
{
    public const SALES_ROLES = ['sales_manager', 'senior_manager', 'assistant_sales_manager', 'sales_executive', 'telecaller', 'sales_head'];

    public function restricted(): bool
    {
        return auth()->user()?->isLeadQualityAuditor() ?? false;
    }

    public function selectedIds(?User $auditor): array
    {
        if (!$auditor || !Schema::hasTable('lead_auditor_user_access')) return [];
        return DB::table('lead_auditor_user_access')->where('auditor_id', $auditor->id)->pluck('sales_user_id')->map(fn ($id) => (int) $id)->all();
    }

    public function candidates()
    {
        return User::query()->whereHas('role', fn ($q) => $q->whereIn('slug', self::SALES_ROLES));
    }

    public function allowedIds(): array
    {
        return $this->candidates()->where('is_active', true)->whereIn('id', $this->selectedIds(auth()->user()))->pluck('id')->all();
    }

    public function scopeUsers($query, $column = 'users.id')
    {
        if ($this->restricted()) $query->whereIn($column, $this->allowedIds());
        return $query;
    }

    public function visibleLeadIds()
    {
        return Lead::query()->select('leads.id')->whereHas('currentAssignment', fn ($q) => $q->whereIn('assigned_to', $this->allowedIds()));
    }

    public function scopeLeads($query, string $column = 'leads.id')
    {
        if ($this->restricted()) $query->whereIn($column, $this->visibleLeadIds());
        return $query;
    }

    public function authorizeUser(?int $id): void
    {
        if ($id && $this->restricted()) abort_unless(in_array($id, $this->allowedIds()), 403);
    }

    public function authorizeLead(int $id): void
    {
        if ($this->restricted()) abort_unless($this->visibleLeadIds()->where('leads.id', $id)->exists(), 404);
    }

    public function authorizeRow(string $key): void
    {
        if (!$this->restricted()) return;
        abort_unless(preg_match('/^lead:([1-9][0-9]*)$/', $key, $match), 404);
        $this->authorizeLead((int) $match[1]);
    }

    public function validateSelection(Request $request, ?User $user, ?string $role): ?array
    {
        $changingAuditor = $role === 'lead_quality_auditor' || $user?->isLeadQualityAuditor();
        if (!$request->user()->isAdmin()) {
            abort_if($request->has('auditor_user_ids') || $request->has('auditor_access_present') || ($changingAuditor && $request->filled('role_id') && (int) $request->role_id !== (int) $user?->role_id), 403);
            return null;
        }
        if (!$changingAuditor) return null;
        if ($role !== 'lead_quality_auditor') return [];
        if (!$request->has('auditor_access_present') && !$request->has('auditor_user_ids') && $user?->isLeadQualityAuditor()) return null;
        $data = $request->validate(['auditor_user_ids' => ['sometimes', 'array'], 'auditor_user_ids.*' => ['integer', 'distinct']]);
        $ids = array_map('intval', $data['auditor_user_ids'] ?? []);
        $existing = $this->selectedIds($user);
        $valid = $this->candidates()->whereIn('id', $ids)->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $existing))->pluck('id')->all();
        if (array_diff($ids, $valid)) throw ValidationException::withMessages(['auditor_user_ids' => 'Select eligible sales users.']);
        return $ids;
    }

    public function sync(User $user, ?array $ids, Request $request): void
    {
        if ($ids === null) return;
        $old = $this->selectedIds($user);
        sort($old); sort($ids);
        if ($old === $ids) return;
        DB::table('lead_auditor_user_access')->where('auditor_id', $user->id)->delete();
        foreach ($ids as $id) DB::table('lead_auditor_user_access')->insert(['auditor_id' => $user->id, 'sales_user_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
        ActivityLog::create(['user_id' => $request->user()->id, 'action' => 'auditor_access_updated', 'model_type' => User::class, 'model_id' => $user->id, 'description' => 'Lead auditor sales-user access changed', 'old_values' => ['user_ids' => $old], 'new_values' => ['user_ids' => $ids], 'ip_address' => $request->ip()]);
    }
}

<?php

namespace App\Services;

use App\Models\Lead;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class LeadBankAvailabilityService
{
    private const BLOCKED_STATUSES = [
        'dead',
        'junk',
        'duplicate',
        'invalid',
        'wrong_number',
        'dnd',
        'closed_lost',
    ];

    private const PROTECTED_STATUSES = [
        'interested',
        'follow_up',
        'follow-up',
        'qualified',
        'site_visit_scheduled',
        'site_visit_completed',
        'negotiation',
        'closed_won',
        'closed',
        'converted',
    ];

    public function inventoryQuery(): Builder
    {
        return Lead::query()
            ->with([
                'activeAssignments.assignedTo:id,name,role_id',
                'latestAssignment.assignedTo:id,name,role_id',
                'leadTags:id,name,slug,type,color',
                'leadBankCooldown',
                'latestImportedLead.importBatch',
            ])
            ->latest('id');
    }

    public function applyFilters(Builder $query, array $filters): Builder
    {
        $scope = (string) ($filters['scope'] ?? 'all');
        if ($scope === 'imported') {
            $query->whereHas('latestImportedLead');
        } elseif ($scope === 'existing') {
            $query->whereDoesntHave('latestImportedLead');
        } elseif ($scope === 'unassigned') {
            $query->whereDoesntHave('activeAssignments');
        } elseif ($scope === 'untagged') {
            $query->whereDoesntHave('leadTags');
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $searchQuery) use ($search) {
                $searchQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhereHas('leadTags', fn (Builder $tagQuery) => $tagQuery->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('activeAssignments.assignedTo', fn (Builder $userQuery) => $userQuery->where('name', 'like', "%{$search}%"));
            });
        }

        if (!empty($filters['city'])) {
            $query->where('city', $filters['city']);
        }

        if (!empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        if (!empty($filters['tag_id'])) {
            $query->whereHas('leadTags', fn (Builder $tagQuery) => $tagQuery->where('lead_tags.id', (int) $filters['tag_id']));
        }

        $availability = (string) ($filters['availability'] ?? '');
        if ($availability === 'available') {
            $query->whereDoesntHave('activeAssignments')
                ->whereDoesntHave('leadBankCooldown', fn (Builder $cooldownQuery) => $cooldownQuery->where('cooldown_until', '>', now()))
                ->where(function (Builder $leadQuery) {
                    $leadQuery->whereNull('is_blocked')->orWhere('is_blocked', false);
                })
                ->where(function (Builder $leadQuery) {
                    $leadQuery->whereNull('is_dead')->orWhere('is_dead', false);
                })
                ->whereNotIn('status', array_merge(self::BLOCKED_STATUSES, self::PROTECTED_STATUSES));
        } elseif ($availability === 'assigned') {
            $query->whereHas('activeAssignments');
        } elseif ($availability === 'cooling') {
            $query->whereHas('leadBankCooldown', fn (Builder $cooldownQuery) => $cooldownQuery->where('cooldown_until', '>', now()));
        } elseif ($availability === 'blocked') {
            $query->where(function (Builder $blockedQuery) {
                $blockedQuery->where('is_blocked', true)
                    ->orWhere('is_dead', true)
                    ->orWhereIn('status', self::BLOCKED_STATUSES);
            });
        } elseif ($availability === 'protected') {
            $query->whereIn('status', self::PROTECTED_STATUSES);
        }

        return $query;
    }

    public function describe(Lead $lead): array
    {
        $activeAssignment = $lead->activeAssignments->first();
        $cooldown = $lead->leadBankCooldown;
        $status = (string) ($lead->status ?? '');
        $reasons = [];

        if ((bool) $lead->is_blocked) {
            $reasons[] = 'Blocked';
        }

        if ((bool) $lead->is_dead || in_array($status, self::BLOCKED_STATUSES, true)) {
            $reasons[] = 'Blocked status';
        }

        if ($cooldown && $cooldown->cooldown_until && $cooldown->cooldown_until->isFuture()) {
            $reasons[] = 'Cooling until ' . $cooldown->cooldown_until->format('d M Y');
        }

        if ($activeAssignment) {
            $reasons[] = 'Assigned to ' . ($activeAssignment->assignedTo->name ?? 'user #' . $activeAssignment->assigned_to);
        }

        if (in_array($status, self::PROTECTED_STATUSES, true)) {
            $reasons[] = 'Protected by status';
        }

        $available = $reasons === [];

        return [
            'available' => $available,
            'state' => $this->stateFromReasons($available, $activeAssignment !== null, $cooldown?->cooldown_until?->isFuture() ?? false, $status, $reasons),
            'label' => $available ? 'Available' : implode(', ', $reasons),
            'reasons' => $reasons,
            'owner' => $activeAssignment?->assignedTo?->name,
            'cooldown_until' => $cooldown?->cooldown_until,
        ];
    }

    public function summarize(Collection $leads): array
    {
        $summary = [
            'total' => 0,
            'available' => 0,
            'assigned' => 0,
            'cooling' => 0,
            'protected' => 0,
            'blocked' => 0,
        ];

        foreach ($leads as $lead) {
            $description = $this->describe($lead);
            $summary['total']++;
            $summary[$description['state']] = ($summary[$description['state']] ?? 0) + 1;
        }

        return $summary;
    }

    private function stateFromReasons(bool $available, bool $assigned, bool $cooling, string $status, array $reasons): string
    {
        if ($available) {
            return 'available';
        }

        if ($cooling) {
            return 'cooling';
        }

        if (in_array('Blocked', $reasons, true) || in_array('Blocked status', $reasons, true)) {
            return 'blocked';
        }

        if (in_array($status, self::PROTECTED_STATUSES, true)) {
            return 'protected';
        }

        if ($assigned) {
            return 'assigned';
        }

        return 'blocked';
    }
}

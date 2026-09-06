<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadBankAllocation;
use App\Models\LeadBankRequest;
use App\Models\LeadTag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LeadBankAnalyticsService
{
    private const UNAVAILABLE_STATUSES = [
        'dead',
        'junk',
        'duplicate',
        'invalid',
        'wrong_number',
        'dnd',
        'closed_lost',
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

    public function __construct(private readonly LeadBankAvailabilityService $availabilityService)
    {
    }

    public function dashboard(): array
    {
        $hasRequests = Schema::hasTable('lead_bank_requests');
        $hasAllocations = Schema::hasTable('lead_bank_allocations');

        $inventory = [
            'total' => Lead::query()->count(),
            'available' => $this->availableInventoryQuery()->count(),
            'assigned' => Lead::query()->whereHas('activeAssignments')->count(),
            'cooling' => Schema::hasTable('lead_bank_cooldowns')
                ? DB::table('lead_bank_cooldowns')->where('cooldown_until', '>', now())->count()
                : 0,
            'tagged' => Schema::hasTable('lead_tag_assignments')
                ? DB::table('lead_tag_assignments')->distinct('lead_id')->count('lead_id')
                : 0,
        ];

        return [
            'migration_required' => !$hasRequests || !$hasAllocations,
            'inventory' => $inventory,
            'requests' => $hasRequests ? $this->requestMetrics() : $this->emptyRequestMetrics(),
            'allocations' => $hasAllocations ? $this->allocationMetrics() : $this->emptyAllocationMetrics(),
            'manager_performance' => $hasAllocations ? $this->managerPerformance() : collect(),
            'tag_performance' => $hasAllocations && Schema::hasTable('lead_tags') ? $this->tagPerformance() : collect(),
            'source_mix' => $this->availableMix('source'),
            'city_mix' => $this->availableMix('city'),
            'expiry_risk' => $hasAllocations ? $this->expiryRisk() : collect(),
            'recent_requests' => $hasRequests ? $this->recentRequests() : collect(),
        ];
    }

    private function requestMetrics(): array
    {
        $total = LeadBankRequest::query()->count();
        $requested = (int) LeadBankRequest::query()->sum('quantity');
        $allocated = (int) LeadBankRequest::query()->sum('allocatable_count');

        return [
            'total' => $total,
            'pending' => LeadBankRequest::query()->where('status', LeadBankRequest::STATUS_PENDING)->count(),
            'approved' => LeadBankRequest::query()->whereIn('status', [LeadBankRequest::STATUS_APPROVED, LeadBankRequest::STATUS_PARTIALLY_APPROVED, LeadBankRequest::STATUS_FULFILLED])->count(),
            'rejected' => LeadBankRequest::query()->where('status', LeadBankRequest::STATUS_REJECTED)->count(),
            'cancelled' => LeadBankRequest::query()->where('status', LeadBankRequest::STATUS_CANCELLED)->count(),
            'requested_leads' => $requested,
            'allocated_leads' => $allocated,
            'fulfillment_rate' => $requested > 0 ? round(($allocated / $requested) * 100, 1) : 0,
        ];
    }

    private function allocationMetrics(): array
    {
        $total = LeadBankAllocation::query()->count();
        $inactive = LeadBankAllocation::query()->whereIn('status', [
            LeadBankAllocation::STATUS_RECALLED,
            LeadBankAllocation::STATUS_EXPIRED,
        ])->count();

        return [
            'total' => $total,
            'active' => LeadBankAllocation::query()->where('status', LeadBankAllocation::STATUS_ACTIVE)->count(),
            'protected' => LeadBankAllocation::query()->where('status', LeadBankAllocation::STATUS_PROTECTED)->count(),
            'converted' => LeadBankAllocation::query()->where('status', LeadBankAllocation::STATUS_CONVERTED)->count(),
            'recalled' => LeadBankAllocation::query()->where('status', LeadBankAllocation::STATUS_RECALLED)->count(),
            'expired' => LeadBankAllocation::query()->where('status', LeadBankAllocation::STATUS_EXPIRED)->count(),
            'expiring_24h' => LeadBankAllocation::query()
                ->where('status', LeadBankAllocation::STATUS_ACTIVE)
                ->whereBetween('expires_at', [now(), now()->addDay()])
                ->count(),
            'expiring_72h' => LeadBankAllocation::query()
                ->where('status', LeadBankAllocation::STATUS_ACTIVE)
                ->whereBetween('expires_at', [now(), now()->addDays(3)])
                ->count(),
            'recall_rate' => $total > 0 ? round(($inactive / $total) * 100, 1) : 0,
        ];
    }

    private function managerPerformance()
    {
        return LeadBankAllocation::query()
            ->select('assigned_to')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_total")
            ->selectRaw("SUM(CASE WHEN status = 'protected' THEN 1 ELSE 0 END) as protected_total")
            ->selectRaw("SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) as converted_total")
            ->selectRaw("SUM(CASE WHEN status IN ('recalled', 'expired') THEN 1 ELSE 0 END) as recalled_total")
            ->with('assignedTo:id,name')
            ->groupBy('assigned_to')
            ->orderByDesc('total')
            ->limit(10)
            ->get();
    }

    private function tagPerformance()
    {
        return LeadTag::query()
            ->withCount('leads')
            ->orderByDesc('leads_count')
            ->limit(12)
            ->get(['id', 'name', 'type', 'color'])
            ->map(function (LeadTag $tag) {
                $allocationCounts = LeadBankAllocation::query()
                    ->whereHas('lead.leadTags', fn (Builder $query) => $query->where('lead_tags.id', $tag->id))
                    ->selectRaw('COUNT(*) as total')
                    ->selectRaw("SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_total")
                    ->selectRaw("SUM(CASE WHEN status IN ('protected', 'converted') THEN 1 ELSE 0 END) as retained_total")
                    ->first();

                $tag->allocation_total = (int) ($allocationCounts->total ?? 0);
                $tag->active_total = (int) ($allocationCounts->active_total ?? 0);
                $tag->retained_total = (int) ($allocationCounts->retained_total ?? 0);

                return $tag;
            });
    }

    private function availableMix(string $field)
    {
        $query = $this->availableInventoryQuery();
        $column = $field === 'city' ? 'city' : 'source';

        return $query
            ->select($column)
            ->selectRaw('COUNT(*) as total')
            ->groupBy($column)
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->mapWithKeys(fn ($row) => [trim((string) $row->{$column}) !== '' ? $row->{$column} : 'Unknown' => (int) $row->total]);
    }

    private function availableInventoryQuery(): Builder
    {
        $query = Lead::query();

        if (Schema::hasTable('lead_bank_cooldowns')) {
            $this->availabilityService->applyFilters($query, ['availability' => 'available']);

            return $query;
        }

        return $query
            ->whereDoesntHave('activeAssignments')
            ->where(function (Builder $leadQuery) {
                $leadQuery->whereNull('is_blocked')->orWhere('is_blocked', false);
            })
            ->where(function (Builder $leadQuery) {
                $leadQuery->whereNull('is_dead')->orWhere('is_dead', false);
            })
            ->whereNotIn('status', self::UNAVAILABLE_STATUSES);
    }

    private function expiryRisk()
    {
        return LeadBankAllocation::query()
            ->with(['lead:id,name,phone,city,status', 'assignedTo:id,name'])
            ->where('status', LeadBankAllocation::STATUS_ACTIVE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays(3))
            ->orderBy('expires_at')
            ->limit(10)
            ->get();
    }

    private function recentRequests()
    {
        return LeadBankRequest::query()
            ->with('requestedBy:id,name')
            ->latest()
            ->limit(8)
            ->get();
    }

    private function emptyRequestMetrics(): array
    {
        return [
            'total' => 0,
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0,
            'cancelled' => 0,
            'requested_leads' => 0,
            'allocated_leads' => 0,
            'fulfillment_rate' => 0,
        ];
    }

    private function emptyAllocationMetrics(): array
    {
        return [
            'total' => 0,
            'active' => 0,
            'protected' => 0,
            'converted' => 0,
            'recalled' => 0,
            'expired' => 0,
            'expiring_24h' => 0,
            'expiring_72h' => 0,
            'recall_rate' => 0,
        ];
    }
}

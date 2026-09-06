<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadBankAudit;
use App\Models\LeadBankRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class LeadBankRequestService
{
    public function __construct(private readonly LeadBankAvailabilityService $availabilityService)
    {
    }

    public function preview(array $criteria): array
    {
        $quantity = max(1, (int) ($criteria['quantity'] ?? 1));
        $availableQuery = $this->matchingAvailableQuery($criteria);
        $matchedCount = (clone $availableQuery)->count();

        $cityBreakdown = (clone $availableQuery)
            ->select('city')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('city')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->mapWithKeys(fn ($row) => [trim((string) $row->city) !== '' ? $row->city : 'Unknown' => (int) $row->total])
            ->toArray();

        $sourceBreakdown = (clone $availableQuery)
            ->select('source')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('source')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->mapWithKeys(fn ($row) => [trim((string) $row->source) !== '' ? $row->source : 'Unknown' => (int) $row->total])
            ->toArray();

        return [
            'matched_count' => $matchedCount,
            'will_allocate' => min($quantity, $matchedCount),
            'shortfall' => max(0, $quantity - $matchedCount),
            'city_breakdown' => $cityBreakdown,
            'source_breakdown' => $sourceBreakdown,
            'excluded' => $this->excludedSummary($criteria),
        ];
    }

    public function createRequest(User $user, array $data): LeadBankRequest
    {
        $tagIds = collect($data['tag_ids'] ?? [])
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $criteria = [
            'quantity' => (int) $data['quantity'],
            'city' => $data['city'] ?? null,
            'source' => $data['source'] ?? null,
            'tag_ids' => $tagIds,
        ];
        $preview = $this->preview($criteria);

        return DB::transaction(function () use ($user, $data, $criteria, $preview, $tagIds) {
            $leadBankRequest = LeadBankRequest::create([
                'requested_by' => $user->id,
                'quantity' => (int) $data['quantity'],
                'city' => $criteria['city'] ?: null,
                'source' => $criteria['source'] ?: null,
                'tag_ids' => $tagIds,
                'expiry_days' => (int) ($data['expiry_days'] ?? 7),
                'reason' => $data['reason'] ?? null,
                'status' => LeadBankRequest::STATUS_PENDING,
                'matched_count' => $preview['matched_count'],
                'allocatable_count' => $preview['will_allocate'],
                'match_snapshot' => $preview,
            ]);

            LeadBankAudit::create([
                'user_id' => $user->id,
                'action' => 'request_created',
                'subject_type' => LeadBankRequest::class,
                'subject_id' => $leadBankRequest->id,
                'new_values' => [
                    'quantity' => $leadBankRequest->quantity,
                    'filters' => $criteria,
                    'preview' => $preview,
                ],
                'description' => 'Lead bank request created',
            ]);

            return $leadBankRequest;
        });
    }

    public function matchingAvailableQuery(array $criteria): Builder
    {
        $query = Lead::query();
        $this->applyRequestFilters($query, $criteria);
        $this->availabilityService->applyFilters($query, ['availability' => 'available']);

        return $query;
    }

    public function criteriaFromFolder(array $data): array
    {
        $folderType = (string) ($data['folder_type'] ?? 'system');

        if ($folderType === 'tag') {
            return [
                'quantity' => (int) ($data['quantity'] ?? 1),
                'tag_id' => (int) ($data['folder_tag_id'] ?? 0),
                'allocation_mode' => 'folder',
                'folder_type' => 'tag',
                'folder_tag_id' => (int) ($data['folder_tag_id'] ?? 0),
            ];
        }

        $folderKey = (string) ($data['folder_key'] ?? 'all');

        return [
            'quantity' => (int) ($data['quantity'] ?? 1),
            'scope' => $folderKey === 'unassigned' ? 'unassigned' : 'all',
            'allocation_mode' => 'folder',
            'folder_type' => 'system',
            'folder_key' => $folderKey === 'unassigned' ? 'unassigned' : 'all',
        ];
    }

    private function excludedSummary(array $criteria): array
    {
        return [
            'assigned' => $this->filteredLeadQuery($criteria)->whereHas('activeAssignments')->count(),
            'cooling' => $this->filteredLeadQuery($criteria)
                ->whereHas('leadBankCooldown', fn (Builder $query) => $query->where('cooldown_until', '>', now()))
                ->count(),
            'blocked' => $this->filteredLeadQuery($criteria)
                ->where(function (Builder $query) {
                    $query->where('is_blocked', true)
                        ->orWhere('is_dead', true)
                        ->orWhereIn('status', ['dead', 'junk', 'duplicate', 'invalid', 'wrong_number', 'dnd', 'closed_lost']);
                })
                ->count(),
            'protected' => $this->filteredLeadQuery($criteria)
                ->whereIn('status', ['interested', 'follow_up', 'follow-up', 'qualified', 'site_visit_scheduled', 'site_visit_completed', 'negotiation', 'closed_won', 'closed', 'converted'])
                ->count(),
        ];
    }

    private function filteredLeadQuery(array $criteria): Builder
    {
        $query = Lead::query();
        $this->applyRequestFilters($query, $criteria);

        return $query;
    }

    private function applyRequestFilters(Builder $query, array $criteria): void
    {
        $search = trim((string) ($criteria['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $searchQuery) use ($search) {
                $searchQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (($criteria['scope'] ?? null) === 'unassigned') {
            $query->whereDoesntHave('activeAssignments');
        }

        if (!empty($criteria['city'])) {
            $query->where('city', $criteria['city']);
        }

        if (!empty($criteria['source'])) {
            $query->where('source', $criteria['source']);
        }

        if (!empty($criteria['status'])) {
            $query->where('status', $criteria['status']);
        }

        if (!empty($criteria['tag_id'])) {
            $query->whereHas('leadTags', fn (Builder $tagQuery) => $tagQuery->where('lead_tags.id', (int) $criteria['tag_id']));
        }

        $tagIds = collect($criteria['tag_ids'] ?? [])
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($tagIds->isNotEmpty()) {
            $query->whereHas('leadTags', fn (Builder $tagQuery) => $tagQuery->whereIn('lead_tags.id', $tagIds));
        }
    }
}

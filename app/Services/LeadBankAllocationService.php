<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadBankAllocation;
use App\Models\LeadBankAudit;
use App\Models\LeadBankCooldown;
use App\Models\LeadBankRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class LeadBankAllocationService
{
    public const PROTECTED_STATUSES = [
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

    private const CONVERTED_STATUSES = [
        'closed_won',
        'closed',
        'converted',
    ];

    public function __construct(
        private readonly LeadBankRequestService $requestService,
        private readonly LeadAssignmentService $assignmentService
    ) {
    }

    public function approve(LeadBankRequest $leadBankRequest, User $reviewer, ?int $quantity = null): array
    {
        return $this->approveAuto($leadBankRequest, $reviewer, $quantity);
    }

    public function approveAuto(
        LeadBankRequest $leadBankRequest,
        User $reviewer,
        ?int $quantity = null,
        string $priority = 'normal',
        ?string $approvalNote = null
    ): array
    {
        if ($leadBankRequest->status !== LeadBankRequest::STATUS_PENDING) {
            return ['allocated' => 0, 'failed' => 0, 'message' => 'Only pending requests can be approved.'];
        }

        $quantity = max(1, min((int) ($quantity ?: $leadBankRequest->quantity), (int) $leadBankRequest->quantity));
        $criteria = [
            'quantity' => $quantity,
            'city' => $leadBankRequest->city,
            'source' => $leadBankRequest->source,
            'tag_ids' => $leadBankRequest->tag_ids ?? [],
        ];
        $approvalPreview = $this->requestService->preview($criteria);

        $leadIds = $this->requestService
            ->matchingAvailableQuery($criteria)
            ->whereDoesntHave('leadBankAllocations', fn (Builder $query) => $query->where('status', LeadBankAllocation::STATUS_ACTIVE))
            ->limit($quantity)
            ->pluck('id');

        $result = $this->allocateLeadIds(
            $leadBankRequest,
            $reviewer,
            $leadIds->all(),
            $quantity,
            'request_auto',
            [],
            $priority,
            $approvalNote,
            ['mode' => 'request_auto']
        );

        if ($result['allocated'] === 0) {
            return $result;
        }

        $result['message'] = "Auto assigned {$result['allocated']} Lead Bank lead(s).";

        return $result;
    }

    public function approveManual(
        LeadBankRequest $leadBankRequest,
        User $reviewer,
        array $selectedLeadIds,
        string $priority = 'normal',
        ?string $approvalNote = null,
        array $criteriaOverride = [],
        array $approvalSource = []
    ): array
    {
        if ($leadBankRequest->status !== LeadBankRequest::STATUS_PENDING) {
            return ['allocated' => 0, 'failed' => 0, 'message' => 'Only pending requests can be approved.'];
        }

        $selectedLeadIds = collect($selectedLeadIds)
            ->map(fn ($leadId) => (int) $leadId)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($selectedLeadIds === []) {
            return ['allocated' => 0, 'failed' => 0, 'message' => 'Select at least one available lead.'];
        }

        $quantity = min(count($selectedLeadIds), (int) $leadBankRequest->quantity);
        $criteria = $criteriaOverride ?: [
            'quantity' => $quantity,
            'city' => $leadBankRequest->city,
            'source' => $leadBankRequest->source,
            'tag_ids' => $leadBankRequest->tag_ids ?? [],
        ];
        $criteria['quantity'] = $quantity;

        $availableLeadIds = $this->requestService
            ->matchingAvailableQuery($criteria)
            ->whereIn('id', $selectedLeadIds)
            ->whereDoesntHave('leadBankAllocations', fn (Builder $query) => $query->where('status', LeadBankAllocation::STATUS_ACTIVE))
            ->limit($quantity)
            ->pluck('id')
            ->all();

        $result = $this->allocateLeadIds(
            $leadBankRequest,
            $reviewer,
            $availableLeadIds,
            $quantity,
            'manual',
            $selectedLeadIds,
            $priority,
            $approvalNote,
            $approvalSource ?: ['mode' => 'manual_filters']
        );

        if ($result['allocated'] === 0) {
            return $result;
        }

        $skipped = max(0, count($selectedLeadIds) - $result['allocated']);
        $result['message'] = "Manually assigned {$result['allocated']} Lead Bank lead(s)." . ($skipped > 0 ? " Skipped {$skipped} unavailable lead(s)." : '');

        return $result;
    }

    public function approveFromCriteria(
        LeadBankRequest $leadBankRequest,
        User $reviewer,
        array $criteria,
        int $quantity,
        string $assignmentMethod,
        string $priority = 'normal',
        ?string $approvalNote = null,
        array $approvalSource = []
    ): array {
        if ($leadBankRequest->status !== LeadBankRequest::STATUS_PENDING) {
            return ['allocated' => 0, 'failed' => 0, 'message' => 'Only pending requests can be approved.'];
        }

        $quantity = max(1, min($quantity, (int) $leadBankRequest->quantity));
        $criteria['quantity'] = $quantity;

        $leadIds = $this->requestService
            ->matchingAvailableQuery($criteria)
            ->whereDoesntHave('leadBankAllocations', fn (Builder $query) => $query->where('status', LeadBankAllocation::STATUS_ACTIVE))
            ->limit($quantity)
            ->pluck('id');

        $result = $this->allocateLeadIds(
            $leadBankRequest,
            $reviewer,
            $leadIds->all(),
            $quantity,
            $assignmentMethod,
            [],
            $priority,
            $approvalNote,
            $approvalSource
        );

        if ($result['allocated'] > 0 && $assignmentMethod === 'folder') {
            $result['message'] = "Folder wise assigned {$result['allocated']} Lead Bank lead(s).";
        }

        return $result;
    }

    private function allocateLeadIds(
        LeadBankRequest $leadBankRequest,
        User $reviewer,
        array $leadIds,
        int $quantity,
        string $assignmentMethod,
        array $selectedLeadIds = [],
        string $priority = 'normal',
        ?string $approvalNote = null,
        array $approvalSource = []
    ): array {
        $allocated = 0;
        $failed = 0;
        $allocationIds = [];
        $criteria = $approvalSource['criteria'] ?? [
            'quantity' => $quantity,
            'city' => $leadBankRequest->city,
            'source' => $leadBankRequest->source,
            'tag_ids' => $leadBankRequest->tag_ids ?? [],
        ];
        $approvalPreview = $this->requestService->preview($criteria);
        $originalRequestFilters = [
            'quantity' => $leadBankRequest->quantity,
            'city' => $leadBankRequest->city,
            'source' => $leadBankRequest->source,
            'tag_ids' => $leadBankRequest->tag_ids ?? [],
        ];

        foreach ($leadIds as $leadId) {
            $lead = Lead::find($leadId);
            if (!$lead || $lead->activeAssignments()->exists()) {
                $failed++;
                continue;
            }

            $assignment = $this->assignmentService->assignToSpecificUser(
                $lead,
                $leadBankRequest->requested_by,
                $reviewer->id,
                'manual',
                true,
                true
            );

            if (!$assignment) {
                $failed++;
                continue;
            }

            $allocation = LeadBankAllocation::create([
                'lead_bank_request_id' => $leadBankRequest->id,
                'lead_id' => $lead->id,
                'lead_assignment_id' => $assignment->id,
                'assigned_to' => $leadBankRequest->requested_by,
                'assigned_by' => $reviewer->id,
                'status' => LeadBankAllocation::STATUS_ACTIVE,
                'allocated_at' => now(),
                'expires_at' => now()->addDays((int) $leadBankRequest->expiry_days),
                'meta' => [
                    'request_quantity' => $leadBankRequest->quantity,
                    'approved_quantity' => $quantity,
                    'city' => $leadBankRequest->city,
                    'source' => $leadBankRequest->source,
                    'tag_ids' => $leadBankRequest->tag_ids ?? [],
                    'original_request_filters' => $originalRequestFilters,
                    'final_allocation_criteria' => $criteria,
                    'final_allocation_source' => $approvalSource,
                    'assignment_method' => $assignmentMethod,
                    'selected_lead_ids' => $selectedLeadIds,
                    'approval_priority' => $priority,
                    'approval_note' => $approvalNote,
                ],
            ]);

            $allocationIds[] = $allocation->id;
            $allocated++;
        }

        if ($allocated === 0) {
            return [
                'allocated' => 0,
                'failed' => $failed,
                'message' => 'No available leads could be allocated for this request.',
            ];
        }

        DB::transaction(function () use ($leadBankRequest, $reviewer, $allocated, $quantity, $allocationIds, $approvalPreview, $assignmentMethod, $selectedLeadIds, $priority, $approvalNote, $approvalSource, $criteria, $originalRequestFilters) {
            $leadBankRequest->update([
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'status' => $allocated >= $leadBankRequest->quantity
                    ? LeadBankRequest::STATUS_APPROVED
                    : LeadBankRequest::STATUS_PARTIALLY_APPROVED,
                'matched_count' => $approvalPreview['matched_count'],
                'allocatable_count' => $allocated,
                'match_snapshot' => array_merge($approvalPreview, [
                    'approved_quantity' => $quantity,
                    'allocated_now' => $allocated,
                    'allocation_ids' => $allocationIds,
                    'assignment_method' => $assignmentMethod,
                    'selected_lead_ids' => $selectedLeadIds,
                    'allocation_source' => $approvalSource,
                    'final_allocation_criteria' => $criteria,
                    'original_request_filters' => $originalRequestFilters,
                    'approval_priority' => $priority,
                    'approval_note' => $approvalNote,
                ]),
            ]);

            LeadBankAudit::create([
                'user_id' => $reviewer->id,
                'action' => 'request_approved',
                'subject_type' => LeadBankRequest::class,
                'subject_id' => $leadBankRequest->id,
                'new_values' => [
                    'allocated' => $allocated,
                    'approved_quantity' => $quantity,
                    'allocation_ids' => $allocationIds,
                    'assignment_method' => $assignmentMethod,
                    'selected_lead_ids' => $selectedLeadIds,
                    'allocation_source' => $approvalSource,
                    'final_allocation_criteria' => $criteria,
                    'original_request_filters' => $originalRequestFilters,
                    'approval_priority' => $priority,
                    'approval_note' => $approvalNote,
                ],
                'description' => 'Lead bank request approved and allocated',
            ]);
        });

        $this->notifyRequesterAllocationSummary($leadBankRequest, $allocated, $allocationIds);

        return [
            'allocated' => $allocated,
            'failed' => $failed,
            'message' => "Allocated {$allocated} lead(s).",
        ];
    }

    public function reject(LeadBankRequest $leadBankRequest, User $reviewer, ?string $reason = null): void
    {
        DB::transaction(function () use ($leadBankRequest, $reviewer, $reason) {
            $leadBankRequest->update([
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'status' => LeadBankRequest::STATUS_REJECTED,
                'rejection_reason' => $reason,
            ]);

            LeadBankAudit::create([
                'user_id' => $reviewer->id,
                'action' => 'request_rejected',
                'subject_type' => LeadBankRequest::class,
                'subject_id' => $leadBankRequest->id,
                'new_values' => ['reason' => $reason],
                'description' => 'Lead bank request rejected',
            ]);
        });
    }

    public function recallAllocation(LeadBankAllocation $allocation, User $user, string $reason = 'manual_recall', int $cooldownDays = 7): bool
    {
        if ($allocation->status !== LeadBankAllocation::STATUS_ACTIVE) {
            return false;
        }

        $lead = $allocation->lead;
        if ($lead && $this->isProtectedLead($lead)) {
            $allocation->update(['status' => LeadBankAllocation::STATUS_PROTECTED]);
            return false;
        }

        DB::transaction(function () use ($allocation, $user, $reason, $cooldownDays) {
            $assignment = $allocation->assignment;
            if ($assignment && $assignment->is_active && (int) $assignment->assigned_to === (int) $allocation->assigned_to) {
                $assignment->update([
                    'is_active' => false,
                    'unassigned_at' => now(),
                ]);
            }

            LeadBankCooldown::updateOrCreate(
                ['lead_id' => $allocation->lead_id],
                [
                    'cooldown_until' => now()->addDays($cooldownDays),
                    'reason' => $reason,
                    'source_type' => 'lead_bank',
                    'created_by' => $user->id,
                ]
            );

            $allocation->update([
                'status' => $reason === 'expired' ? LeadBankAllocation::STATUS_EXPIRED : LeadBankAllocation::STATUS_RECALLED,
                'recalled_at' => now(),
                'recalled_by' => $user->id,
                'recall_reason' => $reason,
            ]);

            LeadBankAudit::create([
                'lead_id' => $allocation->lead_id,
                'user_id' => $user->id,
                'action' => 'allocation_recalled',
                'subject_type' => LeadBankAllocation::class,
                'subject_id' => $allocation->id,
                'new_values' => [
                    'reason' => $reason,
                    'cooldown_days' => $cooldownDays,
                ],
                'description' => 'Lead bank allocation recalled',
            ]);
        });

        return true;
    }

    public function recallExpired(User $user, int $cooldownDays = 7): array
    {
        $allocations = LeadBankAllocation::query()
            ->with(['lead', 'assignment'])
            ->where('status', LeadBankAllocation::STATUS_ACTIVE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        $recalled = 0;
        $protected = 0;

        foreach ($allocations as $allocation) {
            if ($this->recallAllocation($allocation, $user, 'expired', $cooldownDays)) {
                $recalled++;
            } else {
                $protected++;
            }
        }

        return compact('recalled', 'protected');
    }

    public function syncProtectedAllocations(?User $user = null, int $limit = 500): array
    {
        $allocations = LeadBankAllocation::query()
            ->with('lead:id,status')
            ->where('status', LeadBankAllocation::STATUS_ACTIVE)
            ->whereHas('lead', fn (Builder $query) => $query->whereIn('status', self::PROTECTED_STATUSES))
            ->limit($limit)
            ->get();

        $protected = 0;
        $converted = 0;

        foreach ($allocations as $allocation) {
            $status = (string) ($allocation->lead?->status ?? '');
            $newStatus = in_array($status, self::CONVERTED_STATUSES, true)
                ? LeadBankAllocation::STATUS_CONVERTED
                : LeadBankAllocation::STATUS_PROTECTED;

            DB::transaction(function () use ($allocation, $user, $status, $newStatus) {
                $allocation->update([
                    'status' => $newStatus,
                    'meta' => array_merge($allocation->meta ?? [], [
                        'protected_from_status' => $status,
                        'protected_at' => now()->toDateTimeString(),
                    ]),
                ]);

                LeadBankAudit::create([
                    'lead_id' => $allocation->lead_id,
                    'user_id' => $user?->id,
                    'action' => 'allocation_protected',
                    'subject_type' => LeadBankAllocation::class,
                    'subject_id' => $allocation->id,
                    'new_values' => [
                        'lead_status' => $status,
                        'allocation_status' => $newStatus,
                    ],
                    'description' => 'Lead bank allocation protected by lead status',
                ]);
            });

            if ($newStatus === LeadBankAllocation::STATUS_CONVERTED) {
                $converted++;
            } else {
                $protected++;
            }
        }

        return compact('protected', 'converted');
    }

    private function isProtectedLead(Lead $lead): bool
    {
        return in_array((string) $lead->status, self::PROTECTED_STATUSES, true);
    }

    private function notifyRequesterAllocationSummary(LeadBankRequest $leadBankRequest, int $allocated, array $allocationIds): void
    {
        try {
            $requester = User::find($leadBankRequest->requested_by);
            if (!$requester) {
                return;
            }

            app(NotificationService::class)->notifyLeadBankAllocationSummary(
                $requester,
                $allocated,
                $leadBankRequest->id,
                url('/sales-manager/leads'),
                [
                    'allocation_ids' => $allocationIds,
                    'expiry_days' => $leadBankRequest->expiry_days,
                ]
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}

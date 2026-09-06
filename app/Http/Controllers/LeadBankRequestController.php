<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadBankAudit;
use App\Models\LeadBankAllocation;
use App\Models\LeadBankRequest;
use App\Models\LeadTag;
use App\Services\LeadBankAllocationService;
use App\Services\LeadBankRequestService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeadBankRequestController extends Controller
{
    public function __construct(
        private readonly LeadBankRequestService $requestService,
        private readonly LeadBankAllocationService $allocationService
    ) {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        if ($user?->isDedicatedTelecaller()) {
            return redirect()->route('telecaller.leads');
        }

        $isAdminQueue = $user->canManageLeadBankQueue();

        $requests = LeadBankRequest::query()
            ->with(['requestedBy:id,name,role_id', 'requestedBy.role:id,name,slug', 'reviewedBy:id,name'])
            ->withCount([
                'allocations',
                'allocations as active_allocations_count' => fn ($query) => $query->where('status', LeadBankAllocation::STATUS_ACTIVE),
            ])
            ->where('status', '!=', LeadBankRequest::STATUS_REJECTED)
            ->when(!$isAdminQueue, fn ($query) => $query->where('requested_by', $user->id))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $activeAllocations = $isAdminQueue
            ? LeadBankAllocation::query()
                ->with(['lead:id,name,phone,city,source,status', 'assignedTo:id,name', 'request:id,quantity,requested_by'])
                ->where('status', LeadBankAllocation::STATUS_ACTIVE)
                ->latest('allocated_at')
                ->limit(25)
                ->get()
            : collect();

        return view('lead-bank.requests', [
            'requests' => $requests,
            'activeAllocations' => $activeAllocations,
            'isAdminQueue' => $isAdminQueue,
            'tags' => LeadTag::query()->withCount('leads')->orderBy('type')->orderBy('name')->get(['id', 'name', 'slug', 'type', 'color']),
            'folderTags' => LeadTag::query()->where('is_folder', true)->withCount('leads')->orderBy('name')->get(['id', 'name', 'slug', 'type', 'color', 'is_folder']),
            'cities' => Lead::query()->whereNotNull('city')->where('city', '<>', '')->distinct()->orderBy('city')->pluck('city'),
            'sources' => Lead::query()->whereNotNull('source')->where('source', '<>', '')->distinct()->orderBy('source')->pluck('source'),
            'statuses' => Lead::query()->whereNotNull('status')->where('status', '<>', '')->distinct()->orderBy('status')->pluck('status'),
        ]);
    }

    public function preview(Request $request)
    {
        $data = $request->validate($this->previewRules());

        $criteria = ($data['allocation_mode'] ?? 'request_auto') === 'folder'
            ? $this->requestService->criteriaFromFolder($data)
            : [
                'quantity' => (int) ($data['quantity'] ?? 1),
                'city' => $data['city'] ?? null,
                'source' => $data['source'] ?? null,
                'tag_ids' => $data['tag_ids'] ?? [],
            ];

        return response()->json($this->requestService->preview($criteria));
    }

    public function store(Request $request)
    {
        if ($request->user()?->isDedicatedTelecaller()) {
            $validated = $request->validate([
                'quantity' => ['required', 'integer', 'min:1', 'max:5000'],
            ]);

            $data = [
                'quantity' => (int) $validated['quantity'],
                'city' => null,
                'source' => null,
                'tag_ids' => [],
                'expiry_days' => 7,
                'reason' => 'Telecaller requested leads from lead section.',
            ];
        } else {
            $data = $request->validate($this->rules() + [
                'reason' => ['nullable', 'string', 'max:1000'],
            ]);
        }

        $leadBankRequest = $this->requestService->createRequest($request->user(), $data);

        if ($request->user()?->isDedicatedTelecaller()) {
            return redirect()
                ->route('telecaller.leads')
                ->with('success', "Lead request #{$leadBankRequest->id} submitted. Admin approval ke baad leads allocate hongi.");
        }

        return redirect()
            ->route('lead-bank.requests.index')
            ->with('success', "Lead request #{$leadBankRequest->id} submitted. Matching preview found {$leadBankRequest->matched_count} available lead(s).");
    }

    public function approve(Request $request, LeadBankRequest $leadBankRequest)
    {
        $this->authorizeAdminQueue($request);

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:' . $leadBankRequest->quantity],
            'assignment_method' => ['nullable', 'in:auto'],
            'allocation_mode' => ['nullable', 'in:request_auto,folder'],
            'folder_type' => ['nullable', 'in:system,tag'],
            'folder_key' => ['nullable', 'in:all,unassigned'],
            'folder_tag_id' => ['nullable', 'integer', 'exists:lead_tags,id'],
            'priority' => ['nullable', 'in:normal,urgent'],
            'approval_note' => ['nullable', 'string', 'max:1000'],
        ]);

        if (($data['allocation_mode'] ?? 'request_auto') === 'folder') {
            $criteria = $this->requestService->criteriaFromFolder($data);
            $result = $this->allocationService->approveFromCriteria(
                $leadBankRequest,
                $request->user(),
                $criteria,
                (int) $data['quantity'],
                'folder',
                $data['priority'] ?? 'normal',
                $data['approval_note'] ?? null,
                [
                    'mode' => 'folder',
                    'folder_type' => $criteria['folder_type'] ?? null,
                    'folder_key' => $criteria['folder_key'] ?? null,
                    'folder_tag_id' => $criteria['folder_tag_id'] ?? null,
                    'criteria' => $criteria,
                ]
            );
        } else {
            $result = $this->allocationService->approveAuto(
                $leadBankRequest,
                $request->user(),
                (int) $data['quantity'],
                $data['priority'] ?? 'normal',
                $data['approval_note'] ?? null
            );
        }

        return back()->with('success', $result['message']);
    }

    public function manualCandidates(Request $request, LeadBankRequest $leadBankRequest)
    {
        $this->authorizeAdminQueue($request);
        abort_unless($leadBankRequest->status === LeadBankRequest::STATUS_PENDING, 422);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'source' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'max:80'],
            'tag_id' => ['nullable', 'integer', 'exists:lead_tags,id'],
            'folder_type' => ['nullable', 'in:system,tag'],
            'folder_key' => ['nullable', 'in:all,unassigned'],
            'folder_tag_id' => ['nullable', 'integer', 'exists:lead_tags,id'],
        ]);

        $query = $this->manualCandidateQuery($leadBankRequest, $filters)
            ->with([
                'leadTags:id,name,slug,type,color',
                'latestAssignment.assignedTo:id,name,role_id',
                'latestImportedLead.importBatch',
            ])
            ->limit(100);

        $leads = $query->get()->map(function (Lead $lead) {
            $latestAssignment = $lead->latestAssignment;
            $importedLead = $lead->latestImportedLead;
            $duplicateCount = $this->duplicatePhoneCount($lead);
            $quality = $this->leadQualitySignal($lead, $duplicateCount);

            return [
                'id' => $lead->id,
                'name' => $lead->name ?: 'Lead #' . $lead->id,
                'phone' => $lead->phone,
                'city' => $lead->city ?: 'N/A',
                'source' => strtoupper($lead->source ?: 'N/A'),
                'status' => $lead->status ?: 'new',
                'last_owner' => $latestAssignment?->assignedTo?->name ?: 'Never assigned',
                'tags' => $lead->leadTags->pluck('name')->values(),
                'imported' => $importedLead?->importBatch?->file_name ?: ($importedLead ? 'Imported' : 'Manual / existing'),
                'age_days' => $lead->created_at ? $lead->created_at->diffInDays(now()) : null,
                'duplicate_count' => $duplicateCount,
                'duplicate_risk' => $duplicateCount > 0,
                'quality_label' => $quality['label'],
                'quality_tone' => $quality['tone'],
                'quality_signals' => $quality['signals'],
            ];
        });

        return response()->json([
            'leads' => $leads,
            'total' => $leads->count(),
            'limit' => 100,
        ]);
    }

    public function manualAssign(Request $request, LeadBankRequest $leadBankRequest)
    {
        $this->authorizeAdminQueue($request);

        $data = $request->validate([
            'lead_ids' => ['required', 'array', 'min:1'],
            'lead_ids.*' => ['integer'],
            'priority' => ['nullable', 'in:normal,urgent'],
            'approval_note' => ['nullable', 'string', 'max:1000'],
            'search' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'source' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'max:80'],
            'tag_id' => ['nullable', 'integer', 'exists:lead_tags,id'],
            'folder_type' => ['nullable', 'in:system,tag'],
            'folder_key' => ['nullable', 'in:all,unassigned'],
            'folder_tag_id' => ['nullable', 'integer', 'exists:lead_tags,id'],
        ]);

        $leadIds = collect($data['lead_ids'])
            ->map(fn ($leadId) => (int) $leadId)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (count($leadIds) > (int) $leadBankRequest->quantity) {
            return back()->with('error', 'Selected leads cannot be more than requested quantity.');
        }

        $result = $this->allocationService->approveManual(
            $leadBankRequest,
            $request->user(),
            $leadIds,
            $data['priority'] ?? 'normal',
            $data['approval_note'] ?? null,
            $this->manualCriteria($data),
            [
                'mode' => 'manual_filters',
                'criteria' => $this->manualCriteria($data),
            ]
        );

        return back()->with($result['allocated'] > 0 ? 'success' : 'error', $result['message']);
    }

    public function reject(Request $request, LeadBankRequest $leadBankRequest)
    {
        $this->authorizeAdminQueue($request);

        abort_unless($leadBankRequest->status === LeadBankRequest::STATUS_PENDING, 422);

        $data = $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->allocationService->reject($leadBankRequest, $request->user(), $data['rejection_reason'] ?? null);

        return back()->with('success', "Lead request #{$leadBankRequest->id} rejected.");
    }

    public function recallExpired(Request $request)
    {
        $this->authorizeAdminQueue($request);

        $data = $request->validate([
            'cooldown_days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ]);

        $result = $this->allocationService->recallExpired($request->user(), (int) ($data['cooldown_days'] ?? 7));

        return back()->with('success', "Expired recall completed. Recalled {$result['recalled']} lead(s), protected {$result['protected']} lead(s).");
    }

    public function recallAllocation(Request $request, LeadBankAllocation $allocation)
    {
        $this->authorizeAdminQueue($request);

        $data = $request->validate([
            'cooldown_days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'recall_reason' => ['nullable', 'string', 'max:120'],
        ]);

        $recalled = $this->allocationService->recallAllocation(
            $allocation,
            $request->user(),
            $data['recall_reason'] ?? 'manual_recall',
            (int) ($data['cooldown_days'] ?? 7)
        );

        return back()->with($recalled ? 'success' : 'error', $recalled ? 'Allocation recalled and cooldown applied.' : 'Allocation was not recalled because it is protected or already closed.');
    }

    public function cancel(Request $request, LeadBankRequest $leadBankRequest)
    {
        $user = $request->user();
        abort_unless($leadBankRequest->status === LeadBankRequest::STATUS_PENDING, 422);
        abort_unless($leadBankRequest->requested_by === $user->id || $user->canManageLeadBankQueue(), 403);

        DB::transaction(function () use ($leadBankRequest, $user) {
            $oldValues = $leadBankRequest->only(['status']);
            $leadBankRequest->update(['status' => LeadBankRequest::STATUS_CANCELLED]);

            LeadBankAudit::create([
                'user_id' => $user->id,
                'action' => 'request_cancelled',
                'subject_type' => LeadBankRequest::class,
                'subject_id' => $leadBankRequest->id,
                'old_values' => $oldValues,
                'new_values' => ['status' => LeadBankRequest::STATUS_CANCELLED],
                'description' => 'Lead bank request cancelled',
            ]);
        });

        return back()->with('success', "Lead request #{$leadBankRequest->id} cancelled.");
    }

    private function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1', 'max:5000'],
            'city' => ['nullable', 'string', 'max:120'],
            'source' => ['nullable', 'string', 'max:120'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:lead_tags,id'],
            'expiry_days' => ['required', 'integer', 'min:1', 'max:90'],
        ];
    }

    private function previewRules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1', 'max:5000'],
            'city' => ['nullable', 'string', 'max:120'],
            'source' => ['nullable', 'string', 'max:120'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:lead_tags,id'],
            'allocation_mode' => ['nullable', 'in:request_auto,folder'],
            'folder_type' => ['nullable', 'in:system,tag'],
            'folder_key' => ['nullable', 'in:all,unassigned'],
            'folder_tag_id' => ['nullable', 'integer', 'exists:lead_tags,id'],
        ];
    }

    private function manualCandidateQuery(LeadBankRequest $leadBankRequest, array $filters): Builder
    {
        $criteria = $this->manualCriteria($filters);
        $criteria['quantity'] = $leadBankRequest->quantity;

        $query = $this->requestService
            ->matchingAvailableQuery($criteria)
            ->whereDoesntHave('leadBankAllocations', fn (Builder $allocationQuery) => $allocationQuery->where('status', LeadBankAllocation::STATUS_ACTIVE));

        return $query->latest('id');
    }

    private function manualCriteria(array $filters): array
    {
        $criteria = [
            'quantity' => (int) ($filters['quantity'] ?? 1),
            'search' => $filters['search'] ?? null,
            'city' => $filters['city'] ?? null,
            'source' => $filters['source'] ?? null,
            'status' => $filters['status'] ?? null,
            'tag_id' => $filters['tag_id'] ?? null,
        ];

        if (($filters['folder_type'] ?? null) === 'tag' && !empty($filters['folder_tag_id'])) {
            $criteria['tag_id'] = (int) $filters['folder_tag_id'];
            $criteria['folder_type'] = 'tag';
            $criteria['folder_tag_id'] = (int) $filters['folder_tag_id'];
        } elseif (($filters['folder_key'] ?? null) === 'unassigned') {
            $criteria['scope'] = 'unassigned';
            $criteria['folder_type'] = 'system';
            $criteria['folder_key'] = 'unassigned';
        } elseif (($filters['folder_type'] ?? null) === 'system') {
            $criteria['folder_type'] = 'system';
            $criteria['folder_key'] = 'all';
        }

        return array_filter($criteria, fn ($value) => $value !== null && $value !== '');
    }

    private function authorizeAdminQueue(Request $request): void
    {
        $user = $request->user();
        abort_unless($user && $user->canManageLeadBankQueue(), 403);
    }

    private function duplicatePhoneCount(Lead $lead): int
    {
        $normalizedPhone = $this->normalizePhone($lead->phone);
        if (strlen($normalizedPhone) < 10) {
            return 0;
        }

        $lastTen = substr($normalizedPhone, -10);

        return (int) Lead::query()
            ->where('id', '<>', $lead->id)
            ->whereNotNull('phone')
            ->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), '(', ''), ')', ''), '.', '') LIKE ?", ["%{$lastTen}"])
            ->count();
    }

    private function normalizePhone(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (strlen($digits) > 10 && str_starts_with($digits, '91')) {
            $digits = substr($digits, -10);
        }

        return $digits ?: '';
    }

    private function leadQualitySignal(Lead $lead, int $duplicateCount): array
    {
        $status = strtolower(str_replace(' ', '_', (string) $lead->status));
        $ageDays = $lead->created_at ? $lead->created_at->diffInDays(now()) : null;
        $signals = [];

        if ($duplicateCount > 0) {
            $signals[] = "{$duplicateCount} duplicate";
        }

        if ($ageDays !== null) {
            $signals[] = $ageDays <= 30 ? 'Fresh' : ($ageDays <= 90 ? 'Aging' : 'Old');
        }

        if (!$lead->latestAssignment) {
            $signals[] = 'No prior owner';
        }

        if ($duplicateCount > 0) {
            return ['label' => 'Duplicate risk', 'tone' => 'amber', 'signals' => $signals];
        }

        if (in_array($status, ['junk', 'dead', 'not_interested', 'notintrested', 'not_interested'], true)) {
            return ['label' => 'Weak status', 'tone' => 'rose', 'signals' => $signals];
        }

        if ($ageDays !== null && $ageDays > 90) {
            return ['label' => 'Old lead', 'tone' => 'slate', 'signals' => $signals];
        }

        return ['label' => 'Clean lead', 'tone' => 'emerald', 'signals' => $signals];
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Events\SiteVisitCreated;
use App\Models\SiteVisit;
use App\Models\Lead;
use App\Models\Prospect;
use App\Models\Task;
use App\Services\AsmCnpAutomationService;
use App\Services\LeadTaskCleanupService;
use App\Services\CloserWorkflowService;
use App\Services\SiteVisitRescheduleService;
use App\Services\SiteVisitTaskSyncService;
use App\Services\SiteVisitRevenueService;
use App\Services\TelecallerTaskService;
use App\Services\NotificationService;
use App\Services\MetaReviewAutoStageService;
use App\Services\KycFormSchemaService;
use App\Services\VerificationRoutingService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class SiteVisitController extends Controller
{
    protected $notificationService;
    protected $asmCnpAutomationService;
    protected $siteVisitRescheduleService;
    protected $closerWorkflowService;
    protected $siteVisitTaskSyncService;
    protected $kycFormSchemaService;

    public function __construct(
        NotificationService $notificationService,
        AsmCnpAutomationService $asmCnpAutomationService,
        SiteVisitRescheduleService $siteVisitRescheduleService,
        CloserWorkflowService $closerWorkflowService,
        SiteVisitTaskSyncService $siteVisitTaskSyncService,
        KycFormSchemaService $kycFormSchemaService
    )
    {
        $this->notificationService = $notificationService;
        $this->asmCnpAutomationService = $asmCnpAutomationService;
        $this->siteVisitRescheduleService = $siteVisitRescheduleService;
        $this->closerWorkflowService = $closerWorkflowService;
        $this->siteVisitTaskSyncService = $siteVisitTaskSyncService;
        $this->kycFormSchemaService = $kycFormSchemaService;
    }

    private function getRuntimeBudgetRangeOptions(): array
    {
        static $options = null;

        if ($options !== null) {
            return $options;
        }

        if (DB::getDriverName() === 'sqlite') {
            return $options = [
                'Under 50 Lac',
                '50 Lac â€“ 1 Cr',
                '1 Cr â€“ 2 Cr',
                '2 Cr â€“ 3 Cr',
                'Above 3 Cr',
            ];
        }

        $column = DB::selectOne("SHOW COLUMNS FROM site_visits LIKE 'budget_range'");
        if (!$column || empty($column->Type)) {
            return $options = [];
        }

        preg_match_all("/'([^']*)'/", $column->Type, $matches);
        return $options = $matches[1] ?? [];
    }

    private function simplifyBudgetRangeLabel(string $value): string
    {
        $value = str_replace(['Ã¢â‚¬â€œ', 'â€“', '–', '—', '-'], ' ', $value);
        $value = preg_replace('/[^a-z0-9]+/i', ' ', $value);
        return trim(strtolower((string) $value));
    }

    private function normalizeBudgetRangeForStorage(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim($value);
        if ($normalized === '') {
            return null;
        }

        $aliases = [
            'Below 50 Lacs' => 'Under 50 Lac',
            '50-75 Lacs' => '50 Lac 1 Cr',
            '50 - 75 Lacs' => '50 Lac 1 Cr',
            '75 Lacs-1 Cr' => '50 Lac 1 Cr',
            'Above 1 Cr' => '1 Cr 2 Cr',
            'N.A' => 'Under 50 Lac',
        ];

        $comparisonValue = $aliases[$normalized] ?? $normalized;
        $comparisonValue = $this->simplifyBudgetRangeLabel($comparisonValue);

        foreach ($this->getRuntimeBudgetRangeOptions() as $option) {
            if ($this->simplifyBudgetRangeLabel($option) === $comparisonValue) {
                return $option;
            }
        }

        return $normalized;
    }

    private function normalizeScheduledAtForStorage(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        foreach (['d/m/Y, g:ia', 'd/m/Y, h:ia', 'd/m/Y g:ia', 'd/m/Y h:ia'] as $format) {
            try {
                return Carbon::createFromFormat($format, strtolower($value))->format('Y-m-d H:i:s');
            } catch (\Throwable $e) {
                //
            }
        }

        return $value;
    }

    private function normalizeBudgetRangeValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim($value);
        if ($normalized === '') {
            return null;
        }

        $map = [
            'Below 50 Lacs' => 'Under 50 Lac',
            'Under 50 Lac' => 'Under 50 Lac',
            '50 Lac - 1 Cr' => '50 Lac â€“ 1 Cr',
            '50 Lac – 1 Cr' => '50 Lac â€“ 1 Cr',
            '50 Lac â€“ 1 Cr' => '50 Lac â€“ 1 Cr',
            '75 Lacs-1 Cr' => '50 Lac â€“ 1 Cr',
            '1 Cr - 2 Cr' => '1 Cr â€“ 2 Cr',
            '1 Cr – 2 Cr' => '1 Cr â€“ 2 Cr',
            '1 Cr â€“ 2 Cr' => '1 Cr â€“ 2 Cr',
            '2 Cr - 3 Cr' => '2 Cr â€“ 3 Cr',
            '2 Cr – 3 Cr' => '2 Cr â€“ 3 Cr',
            '2 Cr â€“ 3 Cr' => '2 Cr â€“ 3 Cr',
            'Above 1 Cr' => '1 Cr â€“ 2 Cr',
            'Above 3 Cr' => 'Above 3 Cr',
            'N.A' => 'Under 50 Lac',
        ];

        return $map[$normalized] ?? $normalized;
    }

    private function applySiteVisitVisibility(Builder $query, User $user): Builder
    {
        if ($user->isAdmin() || $user->isCrm()) {
            return $query;
        }

        if ($user->isSalesHead()) {
            $visibleOwnerIds = collect($user->getAllTeamMemberIds())
                ->push($user->id)
                ->filter()
                ->unique()
                ->values();

            return $query->whereIn('assigned_to', $visibleOwnerIds);
        }

        if ($user->isSalesManager() || $user->isSeniorManager()) {
            $visibleOwnerIds = $user->teamMembers()->pluck('id')
                ->push($user->id)
                ->filter()
                ->unique()
                ->values();

            return $query->whereIn('assigned_to', $visibleOwnerIds);
        }

        if ($user->isSalesExecutive() || $user->isAssistantSalesManager()) {
            return $query->where('assigned_to', $user->id);
        }

        return $query->whereRaw('1 = 0');
    }

    private function mapCloserBucket(string $bucket, User $user): array
    {
        $normalized = trim(strtolower($bucket));

        return match ($normalized) {
            'visited', 'visited_clients' => ['label' => 'Visited Clients', 'query' => fn (Builder $q) => $q->whereNull('closer_status')],
            'draft', 'closer_drafts' => ['label' => 'Closer Drafts', 'query' => fn (Builder $q) => $q->where('closer_status', 'draft')],
            'pending', 'pending_crm', 'pending_approval' => ['label' => 'Pending CRM Approval', 'query' => fn (Builder $q) => $q->where('closer_status', 'pending_crm')],
            'correction', 'correction_required' => ['label' => 'Correction Required', 'query' => fn (Builder $q) => $q->whereIn('closer_status', ['correction_required', 'rejected'])],
            'approved', 'approved_closers' => ['label' => 'Approved Closers', 'query' => fn (Builder $q) => $q->where('closer_status', 'approved')],
            'rejected', 'rejected_closers' => ['label' => 'Rejected Closers', 'query' => fn (Builder $q) => $q->where('closer_status', 'rejected')],
            'incentives' => ['label' => 'Incentives', 'query' => fn (Builder $q) => $q->where('closer_status', 'approved')],
            default => ['label' => 'Pending CRM Approval', 'query' => fn (Builder $q) => $q->where('closer_status', $user->isCrm() || $user->isAdmin() ? 'pending_crm' : 'draft')],
        };
    }

    private function formatPipelineVisit(SiteVisit $visit, User $user): array
    {
        $incentive = $visit->incentives->firstWhere('type', 'closer');

        return [
            'id' => $visit->id,
            'customer_name' => $visit->customer_name,
            'phone' => $visit->phone,
            'scheduled_at' => optional($visit->scheduled_at)?->toIso8601String(),
            'completed_at' => optional($visit->completed_at)?->toIso8601String(),
            'updated_at' => optional($visit->updated_at)?->toIso8601String(),
            'status' => $visit->status,
            'verification_status' => $visit->verification_status,
            'closer_status' => $visit->closer_status,
            'closing_verification_status' => $visit->closing_verification_status,
            'property_name' => $visit->property_name,
            'property_address' => $visit->property_address,
            'budget_range' => $visit->budget_range,
            'visit_notes' => $visit->visit_notes,
            'project' => $visit->project,
            'property_type' => $visit->property_type,
            'visited_property_types' => $visit->visited_property_types ?? [],
            'lead_type' => $visit->lead_type,
            'incentive_amount' => $visit->incentive_amount,
            'closer_review_remark' => $visit->closer_review_remark,
            'closer_rejection_reason' => $visit->closer_rejection_reason,
            'closing_rejection_reason' => $visit->closing_rejection_reason,
            'closer_submitted_at' => optional($visit->closer_submitted_at)?->toIso8601String(),
            'actual_closer_date' => optional($visit->actual_closer_date)?->toDateString(),
            'actual_closer_backdate_reason' => $visit->actual_closer_backdate_reason,
            'actual_closer_date_approved_at' => optional($visit->actual_closer_date_approved_at)?->toIso8601String(),
            'actual_closer_date_approved_by' => $visit->actual_closer_date_approved_by,
            'closer_reviewed_at' => optional($visit->closer_reviewed_at)?->toIso8601String(),
            'kyc_submitted_at' => optional($visit->kyc_submitted_at)?->toIso8601String(),
            'kyc_last_corrected_at' => optional($visit->kyc_last_corrected_at)?->toIso8601String(),
            'finance_handover_status' => $visit->finance_handover_status,
            'finance_transferred_at' => optional($visit->finance_transferred_at)?->toIso8601String(),
            'finance_reviewed_at' => optional($visit->finance_reviewed_at)?->toIso8601String(),
            'revenue_value' => $visit->revenue_value,
            'revenue_note' => $visit->revenue_note,
            'revenue_entered_at' => optional($visit->revenue_entered_at)?->toIso8601String(),
            'revenue_updated_at' => optional($visit->revenue_updated_at)?->toIso8601String(),
            'closer_resubmission_count' => (int) ($visit->closer_resubmission_count ?? 0),
            'customer_dob' => optional($visit->customer_dob)?->toDateString(),
            'nominee_name' => $visit->nominee_name,
            'second_customer_name' => $visit->second_customer_name,
            'pan_card' => $visit->pan_card,
            'aadhaar_card_no' => $visit->aadhaar_card_no,
            'kyc_documents' => $visit->kyc_documents ?? [],
            'closer_request_proof_photos' => $visit->closer_request_proof_photos ?? [],
            'primary_applicant_details' => $visit->primary_applicant_details ?? [],
            'joint_applicant_details' => $visit->joint_applicant_details ?? [],
            'unit_details' => $visit->unit_details ?? [],
            'booking_form_version' => $visit->booking_form_version,
            'booking_lifecycle_status' => $visit->booking_lifecycle_status,
            'booking_payment_proofs' => $visit->booking_payment_proofs ?? [],
            'booking_activity_log' => $visit->booking_activity_log ?? [],
            'booking_document_reviews' => $visit->booking_document_reviews ?? [],
            'kyc_dynamic_form_id' => $visit->kyc_dynamic_form_id,
            'kyc_custom_fields' => $visit->kyc_custom_fields ?? [],
            'kyc_section_remarks' => $visit->kyc_section_remarks ?? [],
            'kyc_schema' => $this->kycFormSchemaService->buildReadPayload($visit),
            'kyc_documents_count' => is_array($visit->kyc_documents) ? count($visit->kyc_documents) : 0,
            'proof_photos_count' => is_array($visit->closer_request_proof_photos) ? count($visit->closer_request_proof_photos) : 0,
            'booking_payment_proofs_count' => is_array($visit->booking_payment_proofs) ? count($visit->booking_payment_proofs) : 0,
            'has_complete_kyc' => $visit->hasCompleteKyc(),
            'lead' => $visit->lead ? [
                'id' => $visit->lead->id,
                'name' => $visit->lead->name,
                'phone' => $visit->lead->phone,
                'email' => $visit->lead->email,
                'source' => $visit->lead->source,
                'preferred_location' => $visit->lead->preferred_location,
                'budget' => $visit->lead->budget,
                'requirements' => $visit->lead->requirements,
                'notes' => $visit->lead->notes,
            ] : null,
            'creator' => $visit->creator ? ['id' => $visit->creator->id, 'name' => $visit->creator->name] : null,
            'assignedTo' => $visit->assignedTo ? ['id' => $visit->assignedTo->id, 'name' => $visit->assignedTo->name] : null,
            'closerSubmittedBy' => $visit->closerSubmittedBy ? ['id' => $visit->closerSubmittedBy->id, 'name' => $visit->closerSubmittedBy->name] : null,
            'closerReviewedBy' => $visit->closerReviewedBy ? ['id' => $visit->closerReviewedBy->id, 'name' => $visit->closerReviewedBy->name] : null,
            'financeTransferredBy' => $visit->financeTransferredBy ? ['id' => $visit->financeTransferredBy->id, 'name' => $visit->financeTransferredBy->name] : null,
            'financeReviewedBy' => $visit->financeReviewedBy ? ['id' => $visit->financeReviewedBy->id, 'name' => $visit->financeReviewedBy->name] : null,
            'incentive' => $incentive ? [
                'id' => $incentive->id,
                'status' => $incentive->status,
                'amount' => $incentive->amount,
                'user_id' => $incentive->user_id,
                'rejection_reason' => $incentive->rejection_reason,
            ] : null,
            'can_fill_incentive' => $this->closerWorkflowService->isIncentiveUnlocked($visit, $user),
            'can_transfer_to_finance' => false,
        ];
    }

    public function index(Request $request)
    {
        $user = $request->user();
        
        // Ensure role is loaded
        if (!$user->relationLoaded('role')) {
            $user->load('role');
        }
        
        $query = SiteVisit::with(['lead', 'creator', 'assignedTo']);

        // Role-based filtering
        if ($user->isAdmin() || $user->isCrm()) {
            // Admin and CRM can see all site visits
            // No additional filtering needed
        } elseif ($user->isSalesHead()) {
            $allTeamMemberIds = $user->getAllTeamMemberIds();
            $visibleOwnerIds = collect($allTeamMemberIds)
                ->push($user->id)
                ->filter()
                ->unique()
                ->values();

            $query->where('is_dead', false)
                ->where(function ($visibilityQuery) use ($visibleOwnerIds) {
                    $visibilityQuery->whereIn('assigned_to', $visibleOwnerIds)
                        ->orWhereHas('lead', function ($leadQuery) use ($visibleOwnerIds) {
                            $leadQuery->where('is_dead', false)
                                ->whereHas('activeAssignments', function ($assignmentQuery) use ($visibleOwnerIds) {
                                    $assignmentQuery->whereIn('assigned_to', $visibleOwnerIds);
                                });
                        });
                });
        } elseif ($user->isSalesManager() || $user->isSeniorManager()) {
            $teamMemberIds = $user->teamMembers()->pluck('id');
            $visibleOwnerIds = $teamMemberIds
                ->push($user->id)
                ->filter()
                ->unique()
                ->values();

            $query->where('is_dead', false)
                ->where(function ($visibilityQuery) use ($visibleOwnerIds) {
                    $visibilityQuery->whereIn('assigned_to', $visibleOwnerIds)
                        ->orWhereHas('lead', function ($leadQuery) use ($visibleOwnerIds) {
                            $leadQuery->where('is_dead', false)
                                ->whereHas('activeAssignments', function ($assignmentQuery) use ($visibleOwnerIds) {
                                    $assignmentQuery->whereIn('assigned_to', $visibleOwnerIds);
                                });
                        });
                });
        } elseif ($user->isSalesExecutive() || $user->isAssistantSalesManager()) {
            $query->where('is_dead', false)
                ->where(function ($visibilityQuery) use ($user) {
                    $visibilityQuery->where('assigned_to', $user->id)
                        ->orWhereHas('lead', function ($leadQuery) use ($user) {
                            $leadQuery->where('is_dead', false)
                                ->whereHas('activeAssignments', function ($assignmentQuery) use ($user) {
                                    $assignmentQuery->where('assigned_to', $user->id);
                                });
                        });
                });
        } else {
            // Other roles - return empty
            return response()->json([
                'data' => [],
                'current_page' => 1,
                'per_page' => 15,
                'total' => 0,
                'last_page' => 1
            ]);
        }

        if ($request->has('status') && $request->status && $request->status !== 'all') {
            $query->where('status', $request->status);
        } elseif ($request->input('activity_state') === 'pending') {
            $query->where('status', '!=', 'completed')
                ->whereNull('completed_at');
        } elseif ($request->boolean('active_queue')) {
            $query->where(function ($activeQueueQuery) {
                $activeQueueQuery->where(function ($openQuery) {
                    $openQuery->whereIn('status', ['scheduled', 'in_progress', 'rescheduled'])
                        ->whereNull('completed_at');
                })->orWhere(function ($completedQuery) {
                    $completedQuery->where('status', 'completed')
                        ->whereNotNull('completed_at');
                });
            });
        }

        if ($request->has('verification_status') && $request->verification_status && $request->verification_status !== 'all') {
            $query->where('verification_status', $request->verification_status);
        }

        if ($request->has('closer_status') && $request->closer_status && $request->closer_status !== 'all') {
            $query->where('closer_status', $request->closer_status);
        }

        if ($request->has('closing_verification_status') && $request->closing_verification_status && $request->closing_verification_status !== 'all') {
            $query->where('closing_verification_status', $request->closing_verification_status);
        }

        if ($request->boolean('closed_pipeline')) {
            $query->where(function ($closedQuery) {
                $closedQuery->whereNotNull('closing_verification_status')
                    ->orWhereNotNull('closer_status')
                    ->orWhereHas('lead', function ($leadQuery) {
                        $leadQuery->where('status', 'closed');
                    });
            });
        } else {
            $query->where(function ($visibleQuery) {
                $visibleQuery->whereNull('lead_id')
                    ->orWhereHas('lead', function ($leadQuery) {
                        $leadQuery->where('status', '!=', 'closed');
                    });
            });
        }

        if ($request->has('lead_id')) {
            $query->where('lead_id', $request->lead_id);
        }

        if ($request->filled('assigned_to')) {
            $assignedTo = (int) $request->assigned_to;
            if ($assignedTo > 0) {
                $query->where(function ($assignedQuery) use ($assignedTo) {
                    $assignedQuery->where('assigned_to', $assignedTo)
                        ->orWhereHas('lead.activeAssignments', function ($assignmentQuery) use ($assignedTo) {
                            $assignmentQuery->where('assigned_to', $assignedTo)
                                ->where('is_active', true);
                        });
                });
            }
        }

        // Search filter
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('property_name', 'like', "%{$search}%")
                  ->orWhereHas('lead', function($leadQuery) use ($search) {
                      $leadQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                  });
            });
        }

        // Date filter
        if ($request->has('date_filter') && $request->date_filter) {
            $dateFilter = $request->date_filter;
            $today = now()->startOfDay();
            
            switch ($dateFilter) {
                case 'today':
                    $query->whereDate('scheduled_at', $today);
                    break;
                case 'this_week':
                    $query->whereBetween('scheduled_at', [
                        $today->copy()->startOfWeek(),
                        $today->copy()->endOfWeek()
                    ]);
                    break;
                case 'this_month':
                    $query->whereBetween('scheduled_at', [
                        $today->copy()->startOfMonth(),
                        $today->copy()->endOfMonth()
                    ]);
                    break;
                case 'this_year':
                    $query->whereBetween('scheduled_at', [
                        $today->copy()->startOfYear(),
                        $today->copy()->endOfYear()
                    ]);
                    break;
                case 'custom':
                    if ($request->has('date_from') && $request->has('date_to')) {
                        $query->whereBetween('scheduled_at', [
                            $request->date_from . ' 00:00:00',
                            $request->date_to . ' 23:59:59'
                        ]);
                    }
                    break;
            }
        }

        if ($request->boolean('summary')) {
            $today = now()->toDateString();
            $closerStatuses = ['draft', 'pending_crm', 'correction_required', 'rejected', 'approved', 'verified'];

            return response()->json([
                'summary' => [
                    'today' => (clone $query)->whereDate('scheduled_at', $today)->count(),
                    'scheduled' => (clone $query)->where('status', 'scheduled')->count(),
                    'completed' => (clone $query)->where('status', 'completed')->count(),
                    'closer' => (clone $query)->whereIn('closer_status', $closerStatuses)->count(),
                ],
            ]);
        }

        $perPage = min(100, max(1, (int) $request->get('per_page', 15)));
        $visits = $query->latest('scheduled_at')->paginate($perPage);

        return response()->json($visits);
    }

    public function closerPipeline(Request $request)
    {
        $user = $request->user();

        $bucketConfig = $this->mapCloserBucket((string) $request->input('bucket', ''), $user);
        $baseQuery = $this->closerWorkflowService->buildPipelineQuery($user);

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $baseQuery->where(function (Builder $query) use ($search) {
                $query->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('project', 'like', "%{$search}%")
                    ->orWhereHas('lead', function (Builder $leadQuery) use ($search) {
                        $leadQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        $counts = [
            'visited_clients' => (clone $baseQuery)->whereNull('closer_status')->count(),
            'closer_drafts' => (clone $baseQuery)->where('closer_status', 'draft')->count(),
            'pending_crm' => (clone $baseQuery)->where('closer_status', 'pending_crm')->count(),
            'correction_required' => (clone $baseQuery)->whereIn('closer_status', ['correction_required', 'rejected'])->count(),
            'approved_closers' => (clone $baseQuery)->where('closer_status', 'approved')->count(),
            'rejected_closers' => (clone $baseQuery)->where('closer_status', 'rejected')->count(),
            'incentives' => (clone $baseQuery)->where('closer_status', 'approved')->count(),
        ];

        $query = clone $baseQuery;
        $bucketConfig['query']($query);

        $perPage = min(100, max(1, (int) $request->input('per_page', 15)));
        $items = $query->latest('updated_at')
            ->paginate($perPage)
            ->through(fn (SiteVisit $visit) => $this->formatPipelineVisit($visit, $user));

        return response()->json([
            'success' => true,
            'bucket' => $request->input('bucket', ''),
            'bucket_label' => $bucketConfig['label'],
            'counts' => $counts,
            'data' => $items->items(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'last_page' => $items->lastPage(),
                'from' => $items->firstItem(),
                'to' => $items->lastItem(),
            ],
        ]);
    }

    public function requestClose(Request $request, SiteVisit $siteVisit)
    {
        return $this->moveToCloser($request, $siteVisit);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $request->merge([
            'budget_range' => $this->normalizeBudgetRangeForStorage($request->input('budget_range')),
            'scheduled_at' => $this->normalizeScheduledAtForStorage($request->input('scheduled_at')),
            ...$this->normalizeSiteVisitIds($request),
        ]);

        $validator = Validator::make($request->all(), $this->siteVisitFormRules());

        $validator->after(function ($validator) use ($request, $user) {
            if ($this->canScheduleInPast($user)) {
                return;
            }

            $scheduledAt = $request->input('scheduled_at');
            if ($scheduledAt && Carbon::parse($scheduledAt)->lte(now())) {
                $validator->errors()->add('scheduled_at', 'The scheduled at field must be a future date and time.');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $createLinkedTask = (bool) ($validated['create_linked_task'] ?? false);
        $completeSourceTaskId = isset($validated['complete_source_task_id']) ? (int) $validated['complete_source_task_id'] : null;
        unset($validated['create_linked_task'], $validated['complete_source_task_id']);
        if ($request->filled('budget_range') && !in_array($validated['budget_range'] ?? null, $this->getRuntimeBudgetRangeOptions(), true)) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => ['budget_range' => ['The selected budget range is invalid.']],
            ], 422);
        }

        $validated['created_by'] = $user->id;
        $validated['assigned_to'] = $validated['assigned_to'] ?? $user->id;
        $validated['status'] = 'scheduled';
        $validated['verification_status'] = 'pending';

        // Handle photo uploads
        if ($request->hasFile('photos')) {
            $validated['photos'] = $this->storeUploadedImages($request->file('photos'), 'site-visits');
        }

        $siteVisit = null;
        $linkedTask = null;
        $lead = null;
        $wasDuplicateRequest = false;

        DB::beginTransaction();

        try {
            if (!empty($validated['lead_id'])) {
                $lead = Lead::whereKey($validated['lead_id'])->lockForUpdate()->first();
                $existingVisit = $this->findActiveSiteVisitForLead((int) $validated['lead_id']);

                if ($existingVisit) {
                    if ($this->matchesSameSiteVisitRequest($existingVisit, $validated)) {
                        $siteVisit = $existingVisit;
                        $wasDuplicateRequest = true;
                    } else {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'lead_id' => ['This customer already has an active site visit (scheduled or pending). Complete, reschedule, or mark that visit as dead before creating a new one.'],
                        ]);
                    }
                }
            }

            if (!$siteVisit) {
                $siteVisit = SiteVisit::create($validated);
                app(\App\Services\LeadActiveWorkflowService::class)->moveLeadToWorkflow(
                    $siteVisit->lead_id,
                    'site_visit',
                    $siteVisit->id
                );

                // Update lead status based on lead_type
                if (isset($validated['lead_id'])) {
                    $lead = $lead ?: Lead::find($validated['lead_id']);
                    if ($lead) {
                        $leadType = $validated['lead_type'] ?? null;
                        if ($leadType === 'Revisited') {
                            $lead->updateStatusIfAllowed('revisited_scheduled');
                        } else {
                            // Default to visit_scheduled for 'New Visit' or other types
                            $lead->updateStatusIfAllowed('visit_scheduled');
                        }
                        $this->asmCnpAutomationService->cancelLeadAutomation($lead, 'Lead moved to site visit flow.');
                        app(MetaReviewAutoStageService::class)->applyVisitScheduled($lead);
                    }
                }
            }

            if (!$wasDuplicateRequest && $createLinkedTask && $completeSourceTaskId) {
                $sourceTask = Task::find($completeSourceTaskId);
                if ($sourceTask && (int) $sourceTask->lead_id === (int) ($validated['lead_id'] ?? 0)) {
                    if ((int) $sourceTask->assigned_to !== (int) $user->id && !$user->isAdmin() && !$user->isCrm()) {
                        throw new \RuntimeException('You are not allowed to schedule a site visit for this task.');
                    }

                    $assignedTo = (int) ($sourceTask->assigned_to ?: ($validated['assigned_to'] ?? $user->id));
                    $linkedTask = $this->siteVisitTaskSyncService->syncReminderTask($siteVisit, $user, [
                        'assigned_to' => $assignedTo,
                        'created_by' => $user->id,
                        'priority' => $sourceTask->priority ?? 'medium',
                        'notes_prefix' => 'Created from interested task #' . $sourceTask->id,
                    ]);

                    if ($sourceTask->status !== 'completed') {
                        $sourceTask->markAsCompleted();
                    }

                    $sourceTask->update([
                        'outcome' => 'interested',
                        'outcome_recorded_at' => now(),
                        'next_action_at' => $siteVisit->scheduled_at,
                        'outcome_remark' => 'Site visit scheduled',
                    ]);
                } elseif ($sourceTask) {
                    throw new \RuntimeException('The selected task does not belong to this lead.');
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        // Fire event (wrap in try-catch to handle broadcasting errors)
        if (!$wasDuplicateRequest) {
            try {
                event(new SiteVisitCreated($siteVisit));
            } catch (\Exception $e) {
                // Broadcasting errors (like Pusher) shouldn't stop site visit creation
                // Log but continue - the site visit is successfully created
                \Log::warning("Broadcasting error in SiteVisitController (non-critical): " . $e->getMessage());
            }
        }

        $linkedTask = $this->siteVisitTaskSyncService->syncReminderTask($siteVisit, $user, [
            'assigned_to' => (int) ($validated['assigned_to'] ?? $siteVisit->assigned_to ?? $user->id),
            'created_by' => $user->id,
            'priority' => $linkedTask?->priority ?? 'medium',
            'notes_prefix' => $completeSourceTaskId ? 'Created from interested task #' . $completeSourceTaskId : 'Created from lead detail site visit',
        ]);

        return response()->json([
            'success' => true,
            'message' => $wasDuplicateRequest ? 'Site visit already scheduled' : 'Site visit scheduled successfully',
            'data' => $siteVisit->load(['lead', 'creator', 'assignedTo']),
            'task' => $linkedTask?->fresh(),
        ], $wasDuplicateRequest ? 200 : 201);
    }

    private function findActiveSiteVisitForLead(int $leadId): ?SiteVisit
    {
        return SiteVisit::where('lead_id', $leadId)
            ->whereNotIn('status', ['completed', 'cancelled', 'rejected'])
            ->where(function ($q) {
                $q->whereNull('is_dead')->orWhere('is_dead', false);
            })
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();
    }

    private function matchesSameSiteVisitRequest(SiteVisit $existingVisit, array $validated): bool
    {
        if (empty($validated['scheduled_at']) || empty($existingVisit->scheduled_at)) {
            return false;
        }

        $existingAt = Carbon::parse($existingVisit->scheduled_at);
        $requestedAt = Carbon::parse($validated['scheduled_at']);
        if (abs($existingAt->diffInSeconds($requestedAt, false)) > 60) {
            return false;
        }

        $existingAssignedTo = (int) ($existingVisit->assigned_to ?? 0);
        $requestedAssignedTo = (int) ($validated['assigned_to'] ?? 0);
        if ($existingAssignedTo > 0 && $requestedAssignedTo > 0 && $existingAssignedTo !== $requestedAssignedTo) {
            return false;
        }

        return true;
    }

    public function show(SiteVisit $siteVisit)
    {
        $user = request()->user();

        // Check access
        if (!$this->canAccessSiteVisit($user, $siteVisit)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $siteVisit->load(['lead', 'creator', 'assignedTo']);
        $siteVisit->setAttribute('kyc_schema', $this->kycFormSchemaService->buildReadPayload($siteVisit));
        $siteVisit->setAttribute('kyc_custom_fields', $siteVisit->kyc_custom_fields ?? []);
        $siteVisit->setAttribute('kyc_section_remarks', $siteVisit->kyc_section_remarks ?? []);

        return response()->json($siteVisit);
    }

    public function update(Request $request, SiteVisit $siteVisit)
    {
        $user = $request->user();

        if (!$this->canAccessSiteVisit($user, $siteVisit)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'property_name' => 'sometimes|string|max:255',
            'property_address' => 'nullable|string',
            'scheduled_at' => 'sometimes|date',
            'completed_at' => 'nullable|date',
            'status' => 'sometimes|in:scheduled,in_progress,completed,cancelled,rescheduled',
            'visit_notes' => 'nullable|string',
            'feedback' => 'nullable|string',
            'rating' => 'nullable|integer|min:1|max:5',
        ]);

        $siteVisit->update($validated);

        // Update lead status if visit completed
        if (isset($validated['status']) && $validated['status'] === 'completed') {
            if ($siteVisit->lead) {
                $leadType = $siteVisit->lead_type ?? null;
                if ($leadType === 'Revisited') {
                    $siteVisit->lead->updateStatusIfAllowed('revisited_completed');
                } else {
                    $siteVisit->lead->updateStatusIfAllowed('visit_done');
                }
            }
        }

        return response()->json($siteVisit->load(['lead', 'creator', 'assignedTo']));
    }

    /**
     * Mark site visit as completed
     */
    public function complete(Request $request, SiteVisit $siteVisit)
    {
        try {
            $user = $request->user();

            // Check access
            if (!$this->canAccessSiteVisit($user, $siteVisit)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden'
                ], 403);
            }

            if ($siteVisit->status === 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Site visit already completed',
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'feedback' => 'nullable|string',
                'rating' => 'nullable|integer|min:1|max:5',
                'visit_notes' => 'nullable|string',
                'visited_projects' => 'nullable|string',
                'visited_property_types' => 'nullable|array',
                'visited_property_types.*' => 'string|in:plot,villa,apartment,commercial,other',
                'tentative_closing_time' => 'nullable|in:within_3_days,tomorrow,this_week,this_month,it_will_take_time',
                'proof_photos' => 'required|array|min:1',
                'proof_photos.*' => 'required|image|mimes:jpeg,jpg,png,webp|max:5120', // Max 5MB per image
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422)->header('Content-Type', 'application/json');
            }

            // Handle proof photo uploads
            $proofPhotoPaths = [];
            if ($request->hasFile('proof_photos')) {
                foreach ($request->file('proof_photos') as $photo) {
                    $filename = 'site-visits/proof/' . time() . '_' . uniqid() . '.' . $photo->getClientOriginalExtension();
                    $photo->storeAs('public', $filename);
                    $proofPhotoPaths[] = $filename;
                }
            }

            $data = $validator->validated();
            unset($data['proof_photos']); // Remove from update data
            $data['completion_proof_photos'] = $proofPhotoPaths;
            
            $siteVisit->markAsCompleted();
            $siteVisit->update($data);

            // Update lead status based on lead_type
            if ($siteVisit->lead) {
                $leadType = $siteVisit->lead_type ?? null;
                if ($leadType === 'Revisited') {
                    $siteVisit->lead->updateStatusIfAllowed('revisited_completed');
                } else {
                    // Default to visit_done for 'New Visit' or other types
                    $siteVisit->lead->updateStatusIfAllowed('visit_done');
                }
            }

            // Notify only allowed verifiers: seniors of creator, or CRM when creator has no senior. Do not notify Admin.
            $this->notifySiteVisitPendingVerification($siteVisit);

            return response()->json([
                'success' => true,
                'message' => 'Site visit completed with proof photos. Awaiting verification.',
                'data' => $siteVisit->fresh(['lead', 'creator', 'assignedTo']),
            ])->header('Content-Type', 'application/json');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422)->header('Content-Type', 'application/json');
        } catch (\Exception $e) {
            Log::error('Error completing site visit: ' . $e->getMessage(), [
                'site_visit_id' => $siteVisit->id ?? null,
                'user_id' => $request->user()?->id,
                'error' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while completing the site visit. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500)->header('Content-Type', 'application/json');
        }
    }

    /**
     * Reschedule a site visit
     */
    public function reschedule(Request $request, SiteVisit $siteVisit)
    {
        $user = $request->user();

        // Check access
        if (!$this->canAccessSiteVisit($user, $siteVisit)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Scheduled visits can be rescheduled. Already-rescheduled visits are passed
        // through to the service so repeat clicks return the same new visit.
        if ($siteVisit->status !== 'scheduled' && empty($siteVisit->rescheduled_to_visit_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Can only reschedule site visits with status "scheduled"',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'scheduled_at' => 'required|date',
            'reason' => 'required|string|max:500',
        ]);

        $validator->after(function ($validator) use ($request, $user) {
            if ($this->canScheduleInPast($user)) {
                return;
            }

            $scheduledAt = $request->input('scheduled_at');
            if ($scheduledAt && Carbon::parse($scheduledAt)->lte(now())) {
                $validator->errors()->add('scheduled_at', 'The scheduled at field must be a future date and time.');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $siteVisit = $this->siteVisitRescheduleService->reschedule(
            $siteVisit,
            Carbon::parse($request->scheduled_at),
            (string) $request->reason,
            $user
        );

        return response()->json([
            'success' => true,
            'message' => 'Site visit rescheduled successfully. Verification required.',
            'data' => $siteVisit,
        ]);
    }

    private function canScheduleInPast($user): bool
    {
        return (bool) ($user && ($user->isAdmin() || $user->isCrm()));
    }

    public function resubmit(Request $request, SiteVisit $siteVisit)
    {
        $user = $request->user();

        if (!$this->canAccessSiteVisit($user, $siteVisit)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($siteVisit->status !== 'completed' || $siteVisit->verification_status !== 'rejected') {
            return response()->json([
                'success' => false,
                'message' => 'Only completed rejected site visits can be resubmitted.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'visit_notes' => 'nullable|string',
            'feedback' => 'nullable|string',
            'rating' => 'nullable|integer|min:1|max:5',
            'visited_projects' => 'nullable|string',
            'visited_property_types' => 'nullable|array',
            'visited_property_types.*' => 'string|in:plot,villa,apartment,commercial,other',
            'tentative_closing_time' => 'nullable|in:within_3_days,tomorrow,this_week,this_month,it_will_take_time',
            'proof_photos' => 'nullable|array',
            'proof_photos.*' => 'image|mimes:jpeg,jpg,png,webp|max:5120',
            'existing_completion_proof_photos' => 'nullable|array',
            'existing_completion_proof_photos.*' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $editableFields = collect($validated)->only([
            'visit_notes',
            'feedback',
            'rating',
            'visited_projects',
            'visited_property_types',
            'tentative_closing_time',
        ])->all();

        $currentProofPhotos = collect((array) $siteVisit->completion_proof_photos)
            ->filter(fn ($path) => is_string($path) && $path !== '')
            ->values();
        $retainedProofPhotos = collect((array) $request->input('existing_completion_proof_photos', []))
            ->filter(fn ($path) => is_string($path) && $currentProofPhotos->containsStrict($path))
            ->values();
        $newProofPhotos = $request->hasFile('proof_photos')
            ? collect($this->storeUploadedImages($request->file('proof_photos'), 'site-visits/proof'))
            : collect();
        $finalProofPhotos = $retainedProofPhotos
            ->concat($newProofPhotos)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (count($finalProofPhotos) === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => ['proof_photos' => ['At least one proof photo is required before resubmitting.']],
            ], 422);
        }

        $siteVisit->fill($editableFields);
        $siteVisit->completion_proof_photos = $finalProofPhotos;
        $siteVisit->verification_status = 'pending';
        $siteVisit->rejection_reason = null;
        $siteVisit->verified_by = null;
        $siteVisit->verified_at = null;
        $siteVisit->resubmission_count = (int) $siteVisit->resubmission_count + 1;
        $siteVisit->resubmitted_at = now();
        $siteVisit->save();

        $this->notifySiteVisitPendingVerification($siteVisit);

        return response()->json([
            'success' => true,
            'message' => 'Site visit resubmitted successfully. Awaiting verification.',
            'data' => $siteVisit->fresh(['lead', 'creator', 'assignedTo']),
        ]);
    }

    /**
     * Verify a site visit. Creator's senior verifies; if creator has no senior, CRM verifies. Admin cannot verify.
     */
    public function verify(Request $request, SiteVisit $siteVisit)
    {
        $user = $request->user();

        $siteVisit->load('creator');
        $creator = $siteVisit->creator;
        if (!$creator) {
            return response()->json(['message' => 'Site visit creator not found'], 404);
        }

        $routing = app(VerificationRoutingService::class);
        if (!$routing->canVerify($user, $siteVisit, VerificationRoutingService::WORKFLOW_SITE_VISIT)) {
            return response()->json([
                'message' => 'Forbidden. You are not an eligible verifier for this site visit.',
            ], 403);
        }

        if ($siteVisit->verification_status === 'verified') {
            return response()->json([
                'success' => false,
                'message' => 'Site visit already verified',
            ], 422);
        }

        if ($siteVisit->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Site visit must be completed before verification',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'notes' => 'nullable|string',
            'lead_status' => 'nullable|in:hot,warm,cold,junk',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $notes = $request->input('notes');
        $leadStatus = $request->input('lead_status');
        $siteVisit->verify($user->id, $notes, $leadStatus);
        $routing->auditVerification('site_visit_verified', $user, $siteVisit, VerificationRoutingService::WORKFLOW_SITE_VISIT);

        // Check if this site visit is eligible for Telecaller incentive
        // Load lead with prospects to check telecaller_id
        $siteVisit->load(['lead.prospects']);
        
        if ($siteVisit->lead) {
            $lead = $siteVisit->lead;
            
            // Check if lead has a prospect with telecaller_id
            $prospect = $lead->prospects()
                ->whereNotNull('telecaller_id')
                ->latest('created_at')
                ->first();
            
            if ($prospect && $prospect->telecaller_id) {
                try {
                    $telecaller = User::find($prospect->telecaller_id);
                    
                    if ($telecaller && $telecaller->isTelecaller()) {
                        // Check if incentive already requested for this site visit
                        $existingIncentive = \App\Models\Incentive::where('site_visit_id', $siteVisit->id)
                            ->where('type', 'site_visit')
                            ->where('user_id', $telecaller->id)
                            ->first();
                        
                        if (!$existingIncentive) {
                            // Send notification to Telecaller
                            $actionUrl = url('/telecaller/notifications');
                            $this->notificationService->notifyEligibleSiteVisitForIncentive(
                                $telecaller,
                                $siteVisit,
                                $actionUrl
                            );
                        }
                    }
                } catch (\Exception $e) {
                    // Log error but don't fail verification
                    Log::warning('Error notifying telecaller about eligible site visit: ' . $e->getMessage(), [
                        'site_visit_id' => $siteVisit->id,
                        'prospect_id' => $prospect->id ?? null,
                    ]);
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Site visit verified successfully. This counts as a Site Visit achievement.',
            'data' => $siteVisit->fresh(['lead', 'creator', 'verifiedBy']),
        ]);
    }

    /**
     * Reject a site visit. Admin and CRM can reject any pending visit; seniors keep existing access.
     */
    public function reject(Request $request, SiteVisit $siteVisit)
    {
        $user = $request->user();

        $siteVisit->load('creator');
        $creator = $siteVisit->creator;
        if (!$creator) {
            return response()->json(['message' => 'Site visit creator not found'], 404);
        }

        $routing = app(VerificationRoutingService::class);
        if (!$routing->canVerify($user, $siteVisit, VerificationRoutingService::WORKFLOW_SITE_VISIT)) {
            return response()->json([
                'message' => 'Forbidden. You are not an eligible verifier for this site visit.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $siteVisit->reject($user->id, $request->reason);
        $routing->auditVerification('site_visit_rejected', $user, $siteVisit, VerificationRoutingService::WORKFLOW_SITE_VISIT);

        return response()->json([
            'success' => true,
            'message' => 'Site visit rejected',
            'data' => $siteVisit->fresh(['lead', 'creator', 'verifiedBy']),
        ]);
    }

    /**
     * Convert verified site visit to closer (deprecated - use requestCloser)
     */
    public function convertToCloser(Request $request, SiteVisit $siteVisit)
    {
        // Redirect to requestCloser for backward compatibility
        return $this->requestCloser($request, $siteVisit);
    }

    /**
     * Verify closer (Sales Head only)
     */
    public function verifyCloser(Request $request, SiteVisit $siteVisit)
    {
        return $this->approveCloser($request, $siteVisit, VerificationRoutingService::WORKFLOW_CLOSER);
    }

    /**
     * Reject closer (Sales Head only)
     */
    public function rejectCloser(Request $request, SiteVisit $siteVisit)
    {
        return $this->rejectCloserPipeline($request, $siteVisit, VerificationRoutingService::WORKFLOW_CLOSER);
    }

    /**
     * Verify closing (CRM/Admin)
     */
    public function verifyClosing(Request $request, SiteVisit $siteVisit)
    {
        return $this->approveCloser($request, $siteVisit, VerificationRoutingService::WORKFLOW_CLOSING);
    }

    /**
     * Reject closing (CRM/Admin only)
     */
    public function rejectClosing(Request $request, SiteVisit $siteVisit)
    {
        return $this->rejectCloserPipeline($request, $siteVisit, VerificationRoutingService::WORKFLOW_CLOSING);
    }

    /**
     * Submit KYC after close request verification
     */
    public function submitKyc(Request $request, SiteVisit $siteVisit)
    {
        return $this->submitCloserKyc($request, $siteVisit);
    }

    /**
     * Backward-compatible route for older UI
     */
    public function requestCloser(Request $request, SiteVisit $siteVisit)
    {
        return $this->submitCloserKyc($request, $siteVisit);
    }

    public function moveToCloser(Request $request, SiteVisit $siteVisit)
    {
        try {
            $visit = $this->closerWorkflowService->moveToCloser($siteVisit, $request->user());

            return response()->json([
                'success' => true,
                'message' => 'Visited client moved to closer draft.',
                'data' => $visit->fresh(['lead', 'creator', 'assignedTo']),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function saveCloserKycDraft(Request $request, SiteVisit $siteVisit)
    {
        $validator = Validator::make($request->all(), $this->closerKycValidationRules(false, $request->integer('kyc_form_id')));
        $this->addActualCloserDateValidation($validator, false);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            $visit = $this->closerWorkflowService->saveKycDraft(
                $siteVisit,
                $request->user(),
                $validator->validated(),
                $request->file('kyc_documents', []),
                $request->file('proof_photos', []),
                $request->file('booking_payment_proofs', []),
                $this->extractCustomKycFiles($request),
                $request->has('existing_kyc_documents') ? (array) $request->input('existing_kyc_documents', []) : null,
                $request->has('existing_proof_photos') ? (array) $request->input('existing_proof_photos', []) : null,
                $request->has('existing_booking_payment_proofs') ? (array) $request->input('existing_booking_payment_proofs', []) : null,
                $request->boolean('replace_kyc_documents'),
                $request->boolean('replace_proof_photos'),
                $request->boolean('replace_booking_payment_proofs')
            );

            return response()->json([
                'success' => true,
                'message' => 'KYC draft saved successfully.',
                'data' => $visit->fresh(['lead', 'creator', 'assignedTo']),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function submitCloserKyc(Request $request, SiteVisit $siteVisit)
    {
        $validator = Validator::make($request->all(), $this->closerKycValidationRules(true, $request->integer('kyc_form_id')));
        $this->addActualCloserDateValidation($validator, true);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            $visit = $this->closerWorkflowService->submitKyc(
                $siteVisit,
                $request->user(),
                $validator->validated(),
                $request->file('kyc_documents', []),
                $request->file('proof_photos', []),
                $request->file('booking_payment_proofs', []),
                $this->extractCustomKycFiles($request),
                $request->has('existing_kyc_documents') ? (array) $request->input('existing_kyc_documents', []) : null,
                $request->has('existing_proof_photos') ? (array) $request->input('existing_proof_photos', []) : null,
                $request->has('existing_booking_payment_proofs') ? (array) $request->input('existing_booking_payment_proofs', []) : null,
                $request->boolean('replace_kyc_documents'),
                $request->boolean('replace_proof_photos'),
                $request->boolean('replace_booking_payment_proofs')
            );

            try {
                $this->notificationService->notifyClosingVerificationPending($visit, $request->user()->id);
            } catch (\Exception $e) {
                Log::warning('Error sending closer review notification: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'KYC submitted successfully. Awaiting CRM approval.',
                'data' => $visit->fresh(['lead', 'creator', 'assignedTo']),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function resubmitCloser(Request $request, SiteVisit $siteVisit)
    {
        return $this->submitCloserKyc($request, $siteVisit);
    }

    private function closerKycValidationRules(bool $isSubmit, ?int $formId = null): array
    {
        $rules = $this->kycFormSchemaService->getValidationRules($isSubmit, $formId);
        $rules['actual_closer_date'] = ($isSubmit ? 'required' : 'nullable') . '|date';
        $rules['actual_closer_backdate_reason'] = 'nullable|string|max:1000';

        return $rules;
    }

    private function addActualCloserDateValidation(\Illuminate\Validation\Validator $validator, bool $isSubmit): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator) use ($isSubmit) {
            $data = $validator->getData();
            $rawDate = $data['actual_closer_date'] ?? null;

            if (!$rawDate) {
                if ($isSubmit) {
                    $validator->errors()->add('actual_closer_date', 'Actual closer date is required before submission.');
                }
                return;
            }

            try {
                $actualDate = Carbon::parse($rawDate)->startOfDay();
            } catch (\Throwable $e) {
                return;
            }

            $today = Carbon::today();
            if ($actualDate->gt($today)) {
                $validator->errors()->add('actual_closer_date', 'Actual closer date cannot be in the future.');
            }

            if ($actualDate->lt($today->copy()->subDays(7)) && trim((string) ($data['actual_closer_backdate_reason'] ?? '')) === '') {
                $validator->errors()->add('actual_closer_backdate_reason', 'Backdate reason is required when actual closer date is older than 7 days.');
            }
        });
    }

    public function sendBackCloser(Request $request, SiteVisit $siteVisit)
    {
        $validator = Validator::make($request->all(), [
            'remark' => 'required|string|max:1000',
            'section_remarks' => 'nullable|array',
            'section_remarks.*' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            $visit = $this->closerWorkflowService->sendBackForCorrection(
                $siteVisit,
                $request->user(),
                $request->input('remark'),
                (array) $request->input('section_remarks', [])
            );

            return response()->json([
                'success' => true,
                'message' => 'Closer sent back for correction.',
                'data' => $visit->fresh(['lead', 'creator', 'assignedTo']),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function approveCloser(Request $request, SiteVisit $siteVisit, string $workflowType = VerificationRoutingService::WORKFLOW_CLOSER)
    {
        $validator = Validator::make($request->all(), [
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            $visit = $this->closerWorkflowService->approveCloser($siteVisit, $request->user(), $request->input('notes'), $workflowType);
            app(VerificationRoutingService::class)->auditVerification($workflowType === VerificationRoutingService::WORKFLOW_CLOSING ? 'closing_verified' : 'closer_verified', $request->user(), $siteVisit, $workflowType);

            try {
                $this->notificationService->notifyClosingVerified($visit, $request->user()->id);
            } catch (\Exception $e) {
                Log::warning('Error sending closer approval notification: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Closer approved successfully. Incentive form is now unlocked.',
                'data' => $visit->fresh(['lead', 'creator', 'assignedTo']),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function transferCloserToFinance(Request $request, SiteVisit $siteVisit)
    {
        $user = $request->user();

        if (!$user->isCrm() && !$user->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Only CRM or Admin can transfer closers to Finance.'], 403);
        }

        if ($siteVisit->closer_status !== 'approved') {
            return response()->json(['success' => false, 'message' => 'Only approved closers can be transferred to Finance.'], 422);
        }

        if (!$siteVisit->hasCompleteKyc()) {
            return response()->json(['success' => false, 'message' => 'Complete KYC is required before finance transfer.'], 422);
        }

        if (in_array($siteVisit->finance_handover_status, ['pending_finance_manager', 'finance_approved'], true)) {
            return response()->json(['success' => false, 'message' => 'This closer is already in Finance workflow.'], 422);
        }

        $siteVisit->finance_handover_status = 'pending_finance_manager';
        $siteVisit->finance_transferred_at = now();
        $siteVisit->finance_transferred_by = $user->id;
        $siteVisit->save();

        try {
            $this->notificationService->notifyCloserTransferredToFinance($siteVisit, $user->id);
        } catch (\Exception $e) {
            Log::warning('Error sending finance transfer notification: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Closer transferred to Finance Manager successfully.',
            'data' => $siteVisit->fresh(['lead', 'creator', 'assignedTo']),
        ]);
    }

    public function approveFinanceTransfer(Request $request, SiteVisit $siteVisit, SiteVisitRevenueService $revenueService)
    {
        $user = $request->user();

        if (!$user->isFinanceManager()) {
            return response()->json(['success' => false, 'message' => 'Only Finance Manager can approve finance transfers.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'booking_date' => ['required', 'date', 'before_or_equal:today'],
            'revenue_value' => ['required', 'numeric', 'min:0'],
            'revenue_note' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Booking date and revenue value are required before finance approval.', 'errors' => $validator->errors()], 422);
        }

        if ($siteVisit->finance_handover_status !== 'pending_finance_manager') {
            return response()->json(['success' => false, 'message' => 'This closer is not pending Finance review.'], 422);
        }

        $revenueService->updateRevenue($siteVisit, $user, (float) $request->input('revenue_value'), $request->input('revenue_note'));

        $unitDetails = (array) ($siteVisit->unit_details ?? []);
        $unitDetails['booking_date'] = $request->input('booking_date');
        $siteVisit->unit_details = $unitDetails;
        $siteVisit->finance_handover_status = 'finance_approved';
        $siteVisit->finance_reviewed_at = now();
        $siteVisit->finance_reviewed_by = $user->id;
        $siteVisit->save();

        try {
            $this->notificationService->notifyCloserFinanceApproved($siteVisit, $user->id);
        } catch (\Exception $e) {
            Log::warning('Error sending finance approval notification: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Finance review completed. Incentive form is now unlocked for the assigned manager.',
            'data' => $siteVisit->fresh(['lead', 'creator', 'assignedTo']),
        ]);
    }

    public function financeTransferQueue(Request $request)
    {
        $user = $request->user();

        if (!$user->isFinanceManager()) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $visits = SiteVisit::query()
            ->with([
                'lead:id,name,phone,email,source,preferred_location,budget,requirements,notes',
                'creator:id,name',
                'assignedTo:id,name',
                'financeTransferredBy:id,name',
            ])
            ->where('closer_status', 'approved')
            ->where('finance_handover_status', 'pending_finance_manager')
            ->where('status', 'completed')
            ->latest('finance_transferred_at')
            ->get()
            ->map(fn (SiteVisit $visit) => $this->formatPipelineVisit($visit, $user))
            ->values();

        return response()->json([
            'success' => true,
            'data' => $visits,
        ]);
    }

    public function rejectCloserPipeline(Request $request, SiteVisit $siteVisit, string $workflowType = VerificationRoutingService::WORKFLOW_CLOSER)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:1000',
            'section_remarks' => 'nullable|array',
            'section_remarks.*' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            $visit = $this->closerWorkflowService->rejectCloser(
                $siteVisit,
                $request->user(),
                $request->input('reason'),
                (array) $request->input('section_remarks', []),
                $workflowType
            );
            app(VerificationRoutingService::class)->auditVerification($workflowType === VerificationRoutingService::WORKFLOW_CLOSING ? 'closing_rejected' : 'closer_rejected', $request->user(), $siteVisit, $workflowType);

            try {
                $this->notificationService->notifyClosingRejected($visit, $request->user()->id, $request->input('reason'));
            } catch (\Exception $e) {
                Log::warning('Error sending closer rejection notification: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Closer rejected successfully.',
                'data' => $visit->fresh(['lead', 'creator', 'assignedTo']),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    private function extractCustomKycFiles(Request $request): array
    {
        $files = [];
        $allFiles = $request->allFiles();
        unset($allFiles['kyc_documents'], $allFiles['proof_photos'], $allFiles['booking_payment_proofs']);

        foreach ($allFiles as $key => $value) {
            $files[$key] = $value;
        }

        return $files;
    }

    /**
     * Mark site visit as dead
     */
    public function markDead(Request $request, SiteVisit $siteVisit)
    {
        $user = $request->user();

        // Check access
        if ($user->isSalesManager() && $siteVisit->created_by !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $siteVisit->markAsDead($user->id, $request->reason);

        if ($siteVisit->lead_id) {
            app(LeadTaskCleanupService::class)->deleteAllTasksForLead(
                (int) $siteVisit->lead_id,
                (int) $user->id,
                'lead_marked_dead'
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Site visit marked as dead successfully',
            'data' => $siteVisit->fresh(['lead', 'creator', 'markedDeadBy']),
        ]);
    }

    private function canAccessSiteVisit($user, SiteVisit $siteVisit): bool
    {
        if ($user->canViewAllLeads()) {
            return true;
        }

        if ($user->isSalesManager() || $user->isSeniorManager()) {
            $visibleOwnerIds = $user->teamMembers()
                ->pluck('id')
                ->push($user->id)
                ->filter()
                ->unique()
                ->values()
                ->all();

            return in_array((int) $siteVisit->assigned_to, $visibleOwnerIds, true)
                || in_array((int) $siteVisit->created_by, $visibleOwnerIds, true);
        }

        return $siteVisit->assigned_to === $user->id || $siteVisit->created_by === $user->id;
    }

    private function siteVisitFormRules(bool $scheduledAtRequired = true, bool $strict = false): array
    {
        return [
            'lead_id' => 'bail|nullable|integer|exists:leads,id',
            'prospect_id' => 'bail|nullable|integer|exists:prospects,id',
            'assigned_to' => 'bail|nullable|integer|exists:users,id',
            'property_name' => 'nullable|string|max:255',
            'property_address' => 'nullable|string',
            'scheduled_at' => ($scheduledAtRequired ? 'required' : 'nullable') . '|date',
            'visit_notes' => 'nullable|string',
            'feedback' => 'nullable|string',
            'rating' => 'nullable|integer|min:1|max:5',
            'customer_name' => ($strict ? 'required' : 'nullable') . '|string|max:255',
            'phone' => ($strict ? 'required' : 'nullable') . '|string|max:16',
            'employee' => 'nullable|string|max:255',
            'occupation' => 'nullable|string|max:255',
            'date_of_visit' => 'nullable|date',
            'project' => ($strict ? 'required' : 'nullable') . '|string|max:255',
            'budget_range' => ($strict ? 'required' : 'nullable') . '|string|max:255',
            'team_leader' => ($strict ? 'required' : 'nullable') . '|string|max:255',
            'property_type' => ($strict ? 'required' : 'nullable') . '|in:Plot/Villa,Flat,Commercial,Just Exploring',
            'payment_mode' => ($strict ? 'required' : 'nullable') . '|in:Self Fund,Loan',
            'tentative_period' => ($strict ? 'required' : 'nullable') . '|in:Within 1 Month,Within 3 Months,Within 6 Months,More than 6 Months',
            'lead_type' => ($strict ? 'required' : 'nullable') . '|in:New Visit,Revisited,Meeting,Prospect',
            'visit_sequence' => 'nullable|in:fresh_visit,2nd_visit,3rd_visit',
            'create_linked_task' => 'nullable|boolean',
            'complete_source_task_id' => 'bail|nullable|integer|exists:tasks,id',
            'photos' => 'nullable|array',
            'photos.*' => 'image|mimes:jpeg,jpg,png,webp|max:5120',
            'proof_photos' => 'nullable|array',
            'proof_photos.*' => 'image|mimes:jpeg,jpg,png,webp|max:5120',
        ];
    }

    private function normalizeSiteVisitIds(Request $request): array
    {
        return collect(['lead_id', 'prospect_id', 'assigned_to', 'complete_source_task_id'])
            ->mapWithKeys(fn (string $field) => [$field => $this->normalizeSiteVisitId($request->input($field))])
            ->all();
    }

    private function normalizeSiteVisitId(mixed $value): mixed
    {
        while (is_array($value)) {
            if (array_key_exists('id', $value) && !is_array($value['id'])) {
                return $value['id'];
            }

            if (count($value) !== 1) {
                return $value;
            }

            $value = reset($value);
        }

        return $value;
    }

    private function storeUploadedImages(array $files, string $directory): array
    {
        $paths = [];

        foreach ($files as $file) {
            $filename = $directory . '/' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->storeAs('public', $filename);
            $paths[] = $filename;
        }

        return $paths;
    }

    private function notifySiteVisitPendingVerification(SiteVisit $siteVisit): void
    {
        try {
            $siteVisit->loadMissing('creator', 'lead');
            $toNotify = app(VerificationRoutingService::class)
                ->eligibleVerifiers($siteVisit, VerificationRoutingService::WORKFLOW_SITE_VISIT);

            $actionUrl = url('/hr-manager/verifications');
            $customerName = $siteVisit->customer_name ?? ($siteVisit->lead ? $siteVisit->lead->name : 'Customer');
            foreach ($toNotify->unique('id') as $verificationUser) {
                $this->notificationService->notifyNewVerification(
                    $verificationUser,
                    'site_visit',
                    'New Site Visit Verification',
                    "Site visit for '{$customerName}' requires verification",
                    $actionUrl,
                    [
                        'site_visit_id' => $siteVisit->id,
                        'customer_name' => $customerName,
                    ]
                );
            }
        } catch (\Exception $e) {
            Log::error('Error sending site visit verification notifications: ' . $e->getMessage());
        }
    }
}

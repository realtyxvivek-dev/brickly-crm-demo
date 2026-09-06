<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\AttendanceMonthlyRollup;
use App\Models\Incentive;
use App\Models\OfficeLocation;
use App\Models\PayrollFreeze;
use App\Models\SiteVisit;
use App\Services\AttendanceAccessService;
use App\Services\CloserWorkflowService;
use App\Services\KycFormSchemaService;
use App\Services\NotificationService;
use App\Services\PayrollComputationService;
use App\Services\SiteVisitRevenueService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollController extends Controller
{
    public function __construct(protected AttendanceAccessService $attendanceAccessService)
    {
    }

    public function dashboard(Request $request, PayrollComputationService $payrollService)
    {
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);

        try {
            $overview = $this->buildSummary($year, $month, null);
        } catch (\Throwable $e) {
            report($e);

            $overview = [
                'year' => $year,
                'month' => $month,
                'office_location_id' => null,
                'freeze' => null,
                'totals' => [
                    'employees' => 0,
                    'payable_days' => 0,
                    'late_penalty_days' => 0,
                    'overtime_minutes' => 0,
                    'estimated_salary' => 0,
                    'frozen_rollups' => 0,
                ],
                'rollups' => [],
            ];
        }

        $incentives = $this->buildIncentiveSnapshot($year, $month);

        $bookedCustomers = $this->buildBookedCustomersSnapshot();

        return view('finance-manager.dashboard', compact('overview', 'incentives', 'bookedCustomers', 'year', 'month'));
    }

    public function payroll(Request $request, PayrollComputationService $payrollService)
    {
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);
        $officeLocationId = $request->filled('office_location_id') ? (int) $request->input('office_location_id') : null;
        $search = trim((string) $request->input('search', ''));
        $sort = (string) $request->input('sort', 'salary_desc');

        $payrollService->computeMonth($year, $month, $officeLocationId);
        $offices = OfficeLocation::query()->where('is_active', true)->orderBy('name')->get();
        $summary = $this->buildSummary($year, $month, $officeLocationId, $search, $sort);

        return view('finance-manager.payroll', compact('offices', 'summary', 'year', 'month', 'officeLocationId', 'search', 'sort'));
    }

    public function bookedCustomers(Request $request)
    {
        $year = $request->filled('year') ? (int) $request->input('year') : null;
        $month = $request->filled('month') ? (int) $request->input('month') : null;
        $search = trim((string) $request->input('search', ''));

        $bookedCustomers = $this->buildBookedCustomersSnapshot($year, $month, $search);
        $closerRequests = $this->buildCloserRequestsSnapshot($year, $month, $search);
        $incentiveApprovals = $this->buildIncentiveApprovalSnapshot($year, $month, $search);

        return view('finance-manager.booked-customers', [
            'bookedCustomers' => $bookedCustomers,
            'closerRequests' => $closerRequests,
            'incentiveApprovals' => $incentiveApprovals,
            'kycFormSchema' => app(KycFormSchemaService::class)->getResolvedSchema(),
            'year' => $year,
            'month' => $month,
            'search' => $search,
        ]);
    }

    public function showBookedCustomerKyc(SiteVisit $siteVisit, KycFormSchemaService $kycFormSchemaService)
    {
        $this->assertBookedCustomerAccessible($siteVisit);

        return view('finance-manager.booked-customer-kyc', [
            'siteVisit' => $siteVisit->load([
                'lead:id,name,phone,email,source,preferred_location,budget',
                'assignedTo:id,name',
                'creator:id,name',
                'financeTransferredBy:id,name',
                'revenueEnteredBy:id,name',
                'revenueUpdatedBy:id,name',
                'revenueAudits.changedBy:id,name',
            ]),
            'kycPayload' => $this->buildBookedCustomerKycPayload($siteVisit, $kycFormSchemaService),
        ]);
    }

    public function updateBookedCustomerKyc(
        Request $request,
        SiteVisit $siteVisit,
        KycFormSchemaService $kycFormSchemaService,
        CloserWorkflowService $closerWorkflowService,
        SiteVisitRevenueService $revenueService
    ) {
        $this->assertBookedCustomerAccessible($siteVisit);

        $rules = $kycFormSchemaService->getValidationRules(false, $siteVisit->kyc_dynamic_form_id);
        $rules['booking_date'] = ['required', 'date', 'before_or_equal:today'];
        $rules['revenue_value'] = ['required', 'numeric', 'min:0'];
        $rules['revenue_note'] = ['nullable', 'string', 'max:1000'];
        $rules['override_reason'] = ['required', 'string', 'min:5', 'max:1000'];
        $rules['sensitive_confirmed'] = ['nullable', 'boolean'];
        $validated = $request->validate($rules);
        $beforeSnapshot = $this->buildFinanceKycSnapshot($siteVisit, $kycFormSchemaService);
        $submittedSnapshot = $this->buildSubmittedFinanceKycSnapshot($request, $beforeSnapshot);
        $submittedChanges = $this->buildFinanceKycChangeSet($beforeSnapshot, $submittedSnapshot);
        $hasSensitiveChanges = collect($submittedChanges)->contains(fn (array $change) => $change['sensitive'] ?? false);

        if ($submittedChanges === []) {
            return back()
                ->withErrors(['override_reason' => 'No changes detected. Edit at least one KYC, document, or revenue field before saving.'])
                ->withInput();
        }

        if ($hasSensitiveChanges && !$request->boolean('sensitive_confirmed')) {
            return back()
                ->withErrors(['sensitive_confirmed' => 'Sensitive KYC fields changed. Please confirm before saving.'])
                ->withInput();
        }

        $revenueService->updateRevenue(
            $siteVisit,
            $request->user(),
            (float) $validated['revenue_value'],
            $validated['revenue_note'] ?? null
        );

        $closerWorkflowService->saveFinanceKycDraft(
            $siteVisit,
            $request->user(),
            $validated,
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

        $siteVisit->refresh();
        $afterSnapshot = $this->buildFinanceKycSnapshot($siteVisit, $kycFormSchemaService);
        $confirmedChanges = $this->buildFinanceKycChangeSet($beforeSnapshot, $afterSnapshot);
        $this->appendFinanceKycOverwriteActivity(
            $siteVisit,
            $request,
            $confirmedChanges ?: $submittedChanges,
            (string) $validated['override_reason']
        );

        return redirect()
            ->route('finance-manager.booked-customers.kyc.show', $siteVisit)
            ->with('success', 'KYC details updated successfully.');
    }

    public function updateBookedCustomerRevenue(Request $request, SiteVisit $siteVisit, SiteVisitRevenueService $revenueService)
    {
        $this->assertBookedCustomerAccessible($siteVisit);

        $validated = $request->validate([
            'booking_date' => ['required', 'date', 'before_or_equal:today'],
            'revenue_value' => ['required', 'numeric', 'min:0'],
            'revenue_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $unitDetails = (array) ($siteVisit->unit_details ?? []);
        $unitDetails['booking_date'] = $validated['booking_date'];
        $siteVisit->unit_details = $unitDetails;
        $siteVisit->save();

        $revenueService->updateRevenue(
            $siteVisit,
            $request->user(),
            (float) $validated['revenue_value'],
            $validated['revenue_note'] ?? null
        );

        return back()->with('success', 'Revenue value updated successfully.');
    }

    public function approveBookedCustomerIncentive(Request $request, Incentive $incentive, NotificationService $notificationService)
    {
        abort_unless($incentive->type === 'closer' && $incentive->status === 'pending_finance_manager', 422);

        $data = $request->validate([
            'approved_amount' => ['required', 'numeric', 'min:0'],
            'approval_remark' => ['nullable', 'string', 'max:500'],
        ]);

        $requestedAmount = (float) $incentive->amount;
        $approvedAmount = (float) $data['approved_amount'];

        if (round($requestedAmount, 2) !== round($approvedAmount, 2) && blank($data['approval_remark'] ?? null)) {
            return back()->withErrors(['approval_remark' => 'Amount change karne ke liye approval remark zaroori hai.'])->withInput();
        }

        DB::transaction(function () use ($request, $incentive, $notificationService, $requestedAmount, $approvedAmount, $data) {
            $incentive->update([
                'amount' => $approvedAmount,
                'status' => 'verified',
                'finance_manager_verified_by' => $request->user()->id,
                'finance_manager_verified_at' => now(),
            ]);

            $this->appendIncentiveActivity($incentive->siteVisit, $request, 'incentive_approved', [
                'incentive_id' => $incentive->id,
                'requested_amount' => $requestedAmount,
                'approved_amount' => $approvedAmount,
                'remark' => $data['approval_remark'] ?? null,
            ]);

            if ($incentive->siteVisit && blank($incentive->siteVisit->closer_verified_by)) {
                $incentive->siteVisit->update([
                    'closer_verified_by' => $request->user()->id,
                    'closer_verified_at' => now(),
                ]);
            }

            try {
                $notificationService->notifyIncentiveApproved($incentive->fresh(['user', 'siteVisit.lead']));
            } catch (\Throwable $error) {
                report($error);
            }
        });

        return redirect()->route('finance-manager.booked-customers', array_filter([
            'year' => $request->input('year'),
            'month' => $request->input('month'),
            'search' => $request->input('search'),
            'sheet' => 'incentives',
        ]))->with('success', 'Incentive approved. Post Sales handover is now eligible.');
    }

    public function rejectBookedCustomerIncentive(Request $request, Incentive $incentive, NotificationService $notificationService)
    {
        abort_unless($incentive->type === 'closer' && $incentive->status === 'pending_finance_manager', 422);

        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        DB::transaction(function () use ($request, $incentive, $notificationService, $data) {
            $incentive->update([
                'status' => 'rejected',
                'rejected_by' => $request->user()->id,
                'rejection_reason' => $data['rejection_reason'],
            ]);

            $this->appendIncentiveActivity($incentive->siteVisit, $request, 'incentive_rejected', [
                'incentive_id' => $incentive->id,
                'amount' => (float) $incentive->amount,
                'remark' => $data['rejection_reason'],
            ]);

            try {
                $notificationService->notifyIncentiveRejected($incentive->fresh(['user', 'siteVisit.lead']), $data['rejection_reason']);
            } catch (\Throwable $error) {
                report($error);
            }
        });

        return redirect()->route('finance-manager.booked-customers', array_filter([
            'year' => $request->input('year'),
            'month' => $request->input('month'),
            'search' => $request->input('search'),
            'sheet' => 'incentives',
        ]))->with('success', 'Incentive rejected and sales user has been notified.');
    }

    public function bookedCustomerFile(Request $request)
    {
        $path = $this->resolveBookedCustomerStoragePath((string) $request->query('path', ''));

        abort_if($path === null, 404);

        $absolutePath = Storage::disk('public')->path($path);
        abort_unless(is_file($absolutePath), 404);

        return response()->file($absolutePath);
    }

    public function summary(Request $request, PayrollComputationService $payrollService)
    {
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);
        $officeLocationId = $request->filled('office_location_id') ? (int) $request->input('office_location_id') : null;

        $payrollService->computeMonth($year, $month, $officeLocationId);

        return response()->json([
            'success' => true,
            'data' => $this->buildSummary($year, $month, $officeLocationId),
        ]);
    }

    public function freeze(Request $request, PayrollComputationService $payrollService)
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
            'office_location_id' => 'nullable|exists:office_locations,id',
            'notes' => 'nullable|string|max:2000',
        ]);

        $freeze = $payrollService->freezeMonth(
            (int) $validated['year'],
            (int) $validated['month'],
            $request->user(),
            !empty($validated['office_location_id']) ? (int) $validated['office_location_id'] : null,
            $validated['notes'] ?? null
        );

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => $freeze]);
        }

        return redirect()
            ->route('finance-manager.payroll', [
                'year' => $freeze->year,
                'month' => $freeze->month,
                'office_location_id' => $freeze->office_location_id,
            ])
            ->with('success', 'Payroll attendance frozen successfully.');
    }

    public function release(Request $request, PayrollFreeze $freeze, PayrollComputationService $payrollService)
    {
        $payrollService->releaseFreeze($freeze);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => $freeze->fresh()]);
        }

        return back()->with('success', 'Payroll freeze released.');
    }

    public function export(Request $request, PayrollComputationService $payrollService): StreamedResponse
    {
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);
        $officeLocationId = $request->filled('office_location_id') ? (int) $request->input('office_location_id') : null;

        $payrollService->computeMonth($year, $month, $officeLocationId);
        $summary = $this->buildSummary($year, $month, $officeLocationId);
        $rows = collect($summary['rollups']);
        $filename = sprintf('attendance-payroll-%04d-%02d.csv', $year, $month);

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Employee', 'Office', 'Present', 'Half Day', 'Absent', 'Paid Leave', 'Unpaid Leave', 'Weekoff', 'Holiday', 'Late', 'Late Penalty Days', 'Payable Days', 'Approved Overtime Minutes', 'Estimated Salary', 'Frozen']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['user']['name'] ?? '',
                    $row['office_location']['name'] ?? 'Company',
                    $row['present_days'],
                    $row['half_days'],
                    $row['absent_days'],
                    $row['paid_leave_days'],
                    $row['unpaid_leave_days'],
                    $row['weekoff_days'],
                    $row['holiday_days'],
                    $row['late_count'],
                    $row['late_penalty_days'],
                    $row['payable_days'],
                    $row['overtime_minutes'] ?? 0,
                    $row['estimated_salary'],
                    !empty($row['is_frozen']) ? 'Yes' : 'No',
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function buildSummary(int $year, int $month, ?int $officeLocationId, string $search = '', string $sort = 'salary_desc'): array
    {
        $enabledUserIds = $this->attendanceAccessService->enabledProfilesQuery(now()->setDate($year, $month, 1))->select('user_id');

        $rollups = AttendanceMonthlyRollup::query()
            ->with(['user.role', 'officeLocation'])
            ->where('year', $year)
            ->where('month', $month)
            ->whereIn('user_id', $enabledUserIds)
            ->when($officeLocationId, fn ($query) => $query->where('office_location_id', $officeLocationId))
            ->orderByDesc('estimated_salary')
            ->get();

        $allTotals = [
            'employees' => $rollups->count(),
            'payable_days' => (float) $rollups->sum('payable_days'),
            'late_penalty_days' => (float) $rollups->sum('late_penalty_days'),
            'overtime_minutes' => (int) $rollups->sum('overtime_minutes'),
            'estimated_salary' => (float) $rollups->sum('estimated_salary'),
            'frozen_rollups' => $rollups->where('is_frozen', true)->count(),
        ];

        $searchNeedle = mb_strtolower($search);
        if ($searchNeedle !== '') {
            $rollups = $rollups->filter(function (AttendanceMonthlyRollup $rollup) use ($searchNeedle) {
                $haystack = mb_strtolower(implode(' ', array_filter([
                    $rollup->user?->name,
                    $rollup->user?->role?->name,
                    $rollup->officeLocation?->name,
                ])));

                return str_contains($haystack, $searchNeedle);
            })->values();
        }

        $sortMap = [
            'salary_desc' => ['estimated_salary', true],
            'salary_asc' => ['estimated_salary', false],
            'penalty_desc' => ['late_penalty_days', true],
            'overtime_desc' => ['overtime_minutes', true],
            'payable_desc' => ['payable_days', true],
        ];
        [$sortField, $sortDescending] = $sortMap[$sort] ?? $sortMap['salary_desc'];
        $rollups = $sortDescending
            ? $rollups->sortByDesc($sortField)->values()
            : $rollups->sortBy($sortField)->values();

        $freeze = PayrollFreeze::query()
            ->with(['officeLocation', 'frozenByUser'])
            ->where('year', $year)
            ->where('month', $month)
            ->when($officeLocationId, fn ($query) => $query->where('office_location_id', $officeLocationId))
            ->latest()
            ->first();

        $highlights = [
            'highest_salary' => $rollups->first(),
            'highest_penalty' => $rollups->sortByDesc('late_penalty_days')->first(),
            'highest_overtime' => $rollups->sortByDesc('overtime_minutes')->first(),
            'offices' => $rollups
                ->groupBy(fn (AttendanceMonthlyRollup $rollup) => $rollup->officeLocation?->name ?: 'Company')
                ->map(fn ($group, $office) => [
                    'office' => $office,
                    'employees' => $group->count(),
                    'estimated_salary' => (float) $group->sum('estimated_salary'),
                ])
                ->sortByDesc('estimated_salary')
                ->values()
                ->take(4)
                ->all(),
        ];

        $attention = $rollups
            ->filter(fn (AttendanceMonthlyRollup $rollup) => (float) $rollup->late_penalty_days > 0 || (float) $rollup->absent_days > 0 || (int) $rollup->overtime_minutes > 600)
            ->sortByDesc(fn (AttendanceMonthlyRollup $rollup) => ((float) $rollup->late_penalty_days * 100) + ((float) $rollup->absent_days * 100) + (int) $rollup->overtime_minutes)
            ->values()
            ->take(6)
            ->map(function (AttendanceMonthlyRollup $rollup) {
                return [
                    'user' => $rollup->user?->name ?: 'User',
                    'role' => $rollup->user?->role?->name ?: 'Role',
                    'office' => $rollup->officeLocation?->name ?: 'Company',
                    'late_penalty_days' => (float) $rollup->late_penalty_days,
                    'absent_days' => (float) $rollup->absent_days,
                    'overtime_minutes' => (int) $rollup->overtime_minutes,
                    'estimated_salary' => (float) $rollup->estimated_salary,
                ];
            })
            ->all();

        return [
            'year' => $year,
            'month' => $month,
            'office_location_id' => $officeLocationId,
            'freeze' => $freeze,
            'all_totals' => $allTotals,
            'totals' => [
                'employees' => $rollups->count(),
                'payable_days' => (float) $rollups->sum('payable_days'),
                'late_penalty_days' => (float) $rollups->sum('late_penalty_days'),
                'overtime_minutes' => (int) $rollups->sum('overtime_minutes'),
                'estimated_salary' => (float) $rollups->sum('estimated_salary'),
                'frozen_rollups' => $rollups->where('is_frozen', true)->count(),
            ],
            'filters' => [
                'search' => $search,
                'sort' => $sort,
            ],
            'highlights' => $highlights,
            'attention' => $attention,
            'rollups' => $rollups->toArray(),
        ];
    }

    private function buildIncentiveSnapshot(int $year, int $month): array
    {
        $incentives = Incentive::query()
            ->with(['user:id,name', 'siteVisit.lead:id,name'])
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->latest()
            ->get();

        $pending = $incentives->where('status', 'pending_finance_manager');
        $approved = $incentives->where('status', 'verified');
        $rejected = $incentives->where('status', 'rejected');

        return [
            'pending_count' => $pending->count(),
            'approved_count' => $approved->count(),
            'rejected_count' => $rejected->count(),
            'pending_amount' => (float) $pending->sum('amount'),
            'approved_amount' => (float) $approved->sum('amount'),
            'recent' => $incentives->take(5)->map(function (Incentive $incentive) {
                return [
                    'lead' => $incentive->siteVisit?->lead?->name ?: 'Lead not linked',
                    'user' => $incentive->user?->name ?: 'User',
                    'status' => str_replace('_', ' ', $incentive->status),
                    'amount' => (float) $incentive->amount,
                    'created_at' => optional($incentive->created_at)?->format('d M Y'),
                ];
            })->all(),
        ];
    }

    private function buildBookedCustomersSnapshot(?int $year = null, ?int $month = null, string $search = ''): array
    {
        $query = SiteVisit::query()
            ->with([
                'lead:id,name,phone,source',
                'creator:id,name',
                'assignedTo:id,name',
                'financeTransferredBy:id,name',
                'revenueUpdatedBy:id,name',
                'incentives' => fn ($query) => $query->where('type', 'closer')->latest(),
            ])
            ->where('status', 'completed')
            ->whereHas('incentives', function ($query) {
                $query->where('type', 'closer')->where('status', 'verified');
            });

        if ($year && $month) {
            $start = now()->setDate($year, $month, 1)->startOfMonth();
            $end = $start->copy()->endOfMonth();

            $query->where(function ($dateQuery) use ($start, $end) {
                $dateQuery->whereBetween('finance_reviewed_at', [$start, $end])
                    ->orWhere(function ($fallbackQuery) use ($start, $end) {
                        $fallbackQuery->whereNull('finance_reviewed_at')
                            ->whereBetween('updated_at', [$start, $end]);
                    });
            });
        }

        if ($search !== '') {
            $query->where(function ($searchQuery) use ($search) {
                $searchQuery->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('project', 'like', "%{$search}%")
                    ->orWhere('property_name', 'like', "%{$search}%")
                    ->orWhereHas('lead', function ($leadQuery) use ($search) {
                        $leadQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        $visits = $query->latest('finance_reviewed_at')->latest('updated_at')->get();

        return [
            'count' => $visits->count(),
            'with_complete_kyc' => $visits->filter(fn (SiteVisit $visit) => $visit->hasCompleteKyc())->count(),
            'without_revenue' => $visits->filter(fn (SiteVisit $visit) => $visit->revenue_value === null)->count(),
            'total_incentive' => (float) $visits->sum(function (SiteVisit $visit) {
                return (float) optional($visit->incentives->firstWhere('status', 'verified'))->amount;
            }),
            'recent' => $visits->map(function (SiteVisit $visit) {
                $verifiedIncentive = $visit->incentives->firstWhere('status', 'verified');
                $kycDocuments = collect($visit->kyc_documents ?? [])->filter()->map(function ($path) {
                    return $this->buildBookedCustomerFileUrl($path, ['closings/kyc']);
                })->values()->all();
                $proofPhotos = collect($visit->closer_request_proof_photos ?? [])->filter()->map(function ($path) {
                    return $this->buildBookedCustomerFileUrl($path, ['site-visits/closer-proof']);
                })->values()->all();
                $bookingPaymentProofs = collect($visit->booking_payment_proofs ?? [])->filter()->map(function ($path) {
                    return $this->buildBookedCustomerFileUrl($path, ['closings/payment-proofs']);
                })->values()->all();
                $kycSchema = app(KycFormSchemaService::class)->buildReadPayload($visit);
                $kycSchema['sections'] = collect($kycSchema['sections'] ?? [])->map(function (array $section) use ($kycDocuments, $proofPhotos, $bookingPaymentProofs) {
                    $section['fields'] = collect($section['fields'] ?? [])->map(function (array $field) use ($kycDocuments, $proofPhotos, $bookingPaymentProofs) {
                        if (($field['field_key'] ?? null) === 'kyc_documents') {
                            $field['value'] = $kycDocuments;
                        }

                        if (($field['field_key'] ?? null) === 'proof_photos') {
                            $field['value'] = $proofPhotos;
                        }

                        if (($field['field_key'] ?? null) === 'booking_payment_proofs') {
                            $field['value'] = $bookingPaymentProofs;
                        }

                        return $field;
                    })->values()->all();

                    return $section;
                })->values()->all();

                return [
                    'booking_date' => data_get($visit->unit_details ?? [], 'booking_date'),
                    'id' => $visit->id,
                    'site_visit_id' => $visit->id,
                    'lead_id' => $visit->lead?->id,
                    'customer_name' => $visit->customer_name ?: ($visit->lead?->name ?: 'Customer'),
                    'lead_name' => $visit->lead?->name,
                    'phone' => $visit->phone ?: $visit->lead?->phone,
                    'project' => $visit->project ?: ($visit->property_name ?: 'Project not filled'),
                    'budget_range' => $visit->budget_range ?: ($visit->lead?->budget ?: 'Budget not filled'),
                    'assigned_to' => $visit->assignedTo?->name ?: ($visit->creator?->name ?: 'Unassigned'),
                    'transferred_by' => $visit->financeTransferredBy?->name ?: 'System',
                    'finance_transferred_at' => optional($visit->finance_transferred_at)?->format('d M Y h:i A'),
                    'has_complete_kyc' => $visit->hasCompleteKyc(),
                    'payment_mode' => $visit->payment_mode,
                    'incentive_amount' => (float) ($verifiedIncentive?->amount ?? $visit->incentive_amount ?? 0),
                    'revenue_value' => (float) ($visit->revenue_value ?? 0),
                    'revenue_note' => $visit->revenue_note,
                    'revenue_updated_by' => $visit->revenueUpdatedBy?->name,
                    'revenue_updated_at' => optional($visit->revenue_updated_at)?->format('d M Y h:i A'),
                    'incentive_verified_at' => optional($verifiedIncentive?->finance_manager_verified_at ?? $verifiedIncentive?->updated_at)->format('d M Y h:i A'),
                    'kyc' => [
                        'customer_name' => $visit->customer_name ?: ($visit->lead?->name ?: 'Customer'),
                        'nominee_name' => $visit->nominee_name,
                        'second_customer_name' => $visit->second_customer_name,
                        'customer_dob' => optional($visit->customer_dob)->format('d M Y'),
                        'pan_card' => $visit->pan_card,
                        'aadhaar_card_no' => $visit->aadhaar_card_no,
                        'primary_applicant_details' => $visit->primary_applicant_details ?? [],
                        'joint_applicant_details' => $visit->joint_applicant_details ?? [],
                        'unit_details' => $visit->unit_details ?? [],
                        'kyc_custom_fields' => $visit->kyc_custom_fields ?? [],
                        'kyc_section_remarks' => $visit->kyc_section_remarks ?? [],
                        'schema' => $kycSchema,
                        'kyc_documents' => $kycDocuments,
                        'proof_photos' => $proofPhotos,
                        'booking_payment_proofs' => $bookingPaymentProofs,
                        'booking_document_reviews' => $visit->booking_document_reviews ?? [],
                    ],
                ];
            })->all(),
        ];
    }

    private function buildCloserRequestsSnapshot(?int $year = null, ?int $month = null, string $search = ''): array
    {
        $query = SiteVisit::query()
            ->with([
                'lead:id,name,phone,source',
                'creator:id,name',
                'assignedTo:id,name',
                'approvalAdmin:id,name',
                'closerSubmittedBy:id,name',
                'closerReviewedBy:id,name',
                'financeTransferredBy:id,name',
                'financeReviewedBy:id,name',
            ])
            ->whereNotNull('closer_submitted_at')
            ->where('status', 'completed');

        if ($year && $month) {
            $start = now()->setDate($year, $month, 1)->startOfMonth();
            $end = $start->copy()->endOfMonth();
            $query->whereBetween('closer_submitted_at', [$start, $end]);
        }

        if ($search !== '') {
            $query->where(function ($searchQuery) use ($search) {
                $searchQuery->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('project', 'like', "%{$search}%")
                    ->orWhere('property_name', 'like', "%{$search}%")
                    ->orWhereHas('lead', function ($leadQuery) use ($search) {
                        $leadQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    })
                    ->orWhereHas('assignedTo', fn ($userQuery) => $userQuery->where('name', 'like', "%{$search}%"));
            });
        }

        $visits = $query->latest('closer_submitted_at')->latest('id')->get();

        return [
            'count' => $visits->count(),
            'pending_admin' => $visits->where('closer_status', 'pending_crm')->count(),
            'admin_approved' => $visits->filter(fn (SiteVisit $visit) => $visit->closer_status === 'approved' && blank($visit->finance_handover_status))->count(),
            'finance_pending' => $visits->where('finance_handover_status', 'pending_finance_manager')->count(),
            'finance_reviewed' => $visits->where('finance_handover_status', 'finance_approved')->count(),
            'recent' => $visits->map(function (SiteVisit $visit) {
                $status = $this->formatCloserRequestStatus($visit);

                return [
                    'id' => $visit->id,
                    'site_visit_id' => $visit->id,
                    'lead_id' => $visit->lead?->id,
                    'customer_name' => $visit->customer_name ?: ($visit->lead?->name ?: 'Customer'),
                    'phone' => $visit->phone ?: $visit->lead?->phone,
                    'source' => $visit->lead?->source ?: 'Source not filled',
                    'project' => $visit->project ?: ($visit->property_name ?: 'Project not filled'),
                    'booking_credit_to' => $visit->assignedTo?->name ?: ($visit->creator?->name ?: 'Unassigned'),
                    'submitted_by' => $visit->closerSubmittedBy?->name ?: ($visit->creator?->name ?: 'User'),
                    'approval_admin' => $visit->approvalAdmin?->name ?: 'Any Admin',
                    'reviewed_by' => $visit->closerReviewedBy?->name,
                    'finance_transferred_by' => $visit->financeTransferredBy?->name,
                    'finance_reviewed_by' => $visit->financeReviewedBy?->name,
                    'status_label' => $status['label'],
                    'status_class' => $status['class'],
                    'closer_submitted_at' => optional($visit->closer_submitted_at)->format('d M Y h:i A'),
                    'closer_verified_at' => optional($visit->closer_verified_at)->format('d M Y h:i A'),
                    'finance_transferred_at' => optional($visit->finance_transferred_at)->format('d M Y h:i A'),
                    'finance_reviewed_at' => optional($visit->finance_reviewed_at)->format('d M Y h:i A'),
                    'booking_date' => data_get($visit->unit_details ?? [], 'booking_date'),
                    'is_finance_direct_closer' => (bool) $visit->is_finance_direct_closer,
                ];
            })->all(),
        ];
    }

    private function buildIncentiveApprovalSnapshot(?int $year = null, ?int $month = null, string $search = ''): array
    {
        $query = SiteVisit::query()
            ->with([
                'lead:id,name,phone,source',
                'assignedTo:id,name',
                'creator:id,name',
                'incentives' => fn ($incentiveQuery) => $incentiveQuery->where('type', 'closer')->with('user:id,name')->latest(),
            ])
            ->whereNotNull('closer_submitted_at')
            ->where('status', 'completed')
            ->where('verification_status', 'verified');

        if ($year && $month) {
            $start = now()->setDate($year, $month, 1)->startOfMonth();
            $end = $start->copy()->endOfMonth();
            $query->whereBetween('closer_submitted_at', [$start, $end]);
        }

        if ($search !== '') {
            $query->where(function ($searchQuery) use ($search) {
                $searchQuery->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('project', 'like', "%{$search}%")
                    ->orWhereHas('lead', fn ($leadQuery) => $leadQuery->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"));
            });
        }

        $rows = $query->latest('closer_submitted_at')->latest('id')->get()->map(function (SiteVisit $visit) {
            $incentive = $visit->incentives->first();
            $status = $this->formatIncentiveApprovalStatus($visit, $incentive);

            return [
                'site_visit_id' => $visit->id,
                'lead_id' => $visit->lead?->id,
                'customer_name' => $visit->customer_name ?: ($visit->lead?->name ?: 'Customer'),
                'phone' => $visit->phone ?: $visit->lead?->phone,
                'project' => $visit->project ?: ($visit->property_name ?: 'Project not filled'),
                'requested_by' => $incentive?->user?->name ?: ($visit->assignedTo?->name ?: ($visit->creator?->name ?: 'Unassigned')),
                'requested_amount' => $incentive ? (float) $incentive->amount : null,
                'requested_at' => optional($incentive?->created_at)->format('d M Y h:i A'),
                'status' => $status['status'],
                'status_label' => $status['label'],
                'status_class' => $status['class'],
                'can_review' => $incentive?->status === 'pending_finance_manager',
                'incentive_id' => $incentive?->id,
                'verified_at' => optional($incentive?->finance_manager_verified_at)->format('d M Y h:i A'),
                'rejection_reason' => $incentive?->rejection_reason,
                'kyc_complete' => $visit->hasCompleteKyc(),
            ];
        });

        return [
            'count' => $rows->count(),
            'pending_count' => $rows->where('status', 'requested')->count(),
            'pending_amount' => (float) $rows->where('status', 'requested')->sum('requested_amount'),
            'not_requested_count' => $rows->where('status', 'not_requested')->count(),
            'kyc_pending_count' => $rows->where('status', 'kyc_pending')->count(),
            'approved_count' => $rows->where('status', 'approved')->count(),
            'rejected_count' => $rows->where('status', 'rejected')->count(),
            'rows' => $rows->all(),
        ];
    }

    private function formatIncentiveApprovalStatus(SiteVisit $visit, ?Incentive $incentive): array
    {
        if ($incentive?->status === 'pending_finance_manager') {
            return ['status' => 'requested', 'label' => 'Finance Review', 'class' => 'pending'];
        }

        if ($incentive?->status === 'verified') {
            return ['status' => 'approved', 'label' => 'Approved', 'class' => 'ready'];
        }

        if ($incentive?->status === 'rejected') {
            return ['status' => 'rejected', 'label' => 'Rejected', 'class' => 'missing'];
        }

        if (!$visit->hasCompleteKyc()) {
            return ['status' => 'kyc_pending', 'label' => 'KYC Pending', 'class' => 'missing'];
        }

        if ($visit->closer_status === 'approved') {
            return ['status' => 'not_requested', 'label' => 'Not Requested', 'class' => 'pending'];
        }

        return ['status' => 'closer_pending', 'label' => 'Closer Approval Pending', 'class' => 'pending'];
    }

    private function formatCloserRequestStatus(SiteVisit $visit): array
    {
        if ($visit->closer_status === 'pending_crm') {
            return ['label' => 'Pending Admin Approval', 'class' => 'pending'];
        }

        if ($visit->closer_status === 'correction_required') {
            return ['label' => 'Correction Required', 'class' => 'missing'];
        }

        if ($visit->closer_status === 'rejected') {
            return ['label' => 'Rejected', 'class' => 'missing'];
        }

        if ($visit->finance_handover_status === 'pending_finance_manager') {
            return ['label' => 'Finance Review Pending', 'class' => 'pending'];
        }

        if ($visit->finance_handover_status === 'finance_approved') {
            return ['label' => 'Finance Reviewed', 'class' => 'ready'];
        }

        if ($visit->closer_status === 'approved') {
            return ['label' => 'Admin Approved', 'class' => 'ready'];
        }

        return ['label' => ucfirst(str_replace('_', ' ', (string) $visit->closer_status)), 'class' => 'pending'];
    }

    private function buildBookedCustomerKycPayload(SiteVisit $siteVisit, KycFormSchemaService $kycFormSchemaService): array
    {
        $kycDocuments = collect($siteVisit->kyc_documents ?? [])
            ->filter()
            ->map(fn ($path) => $this->buildBookedCustomerFileUrl($path, ['closings/kyc']))
            ->values()
            ->all();

        $proofPhotos = collect($siteVisit->closer_request_proof_photos ?? [])
            ->filter()
            ->map(fn ($path) => $this->buildBookedCustomerFileUrl($path, ['site-visits/closer-proof']))
            ->values()
            ->all();

        $bookingPaymentProofs = collect($siteVisit->booking_payment_proofs ?? [])
            ->filter()
            ->map(fn ($path) => $this->buildBookedCustomerFileUrl($path, ['closings/payment-proofs']))
            ->values()
            ->all();

        $schema = $kycFormSchemaService->buildReadPayload($siteVisit);
        $schema['sections'] = collect($schema['sections'] ?? [])->map(function (array $section) use ($kycDocuments, $proofPhotos, $bookingPaymentProofs) {
            $section['fields'] = collect($section['fields'] ?? [])->map(function (array $field) use ($kycDocuments, $proofPhotos, $bookingPaymentProofs) {
                if (($field['field_key'] ?? null) === 'kyc_documents') {
                    $field['value'] = $kycDocuments;
                }

                if (($field['field_key'] ?? null) === 'proof_photos') {
                    $field['value'] = $proofPhotos;
                }

                if (($field['field_key'] ?? null) === 'booking_payment_proofs') {
                    $field['value'] = $bookingPaymentProofs;
                }

                return $field;
            })->values()->all();

            return $section;
        })->values()->all();

        return [
            'schema' => $schema,
            'kyc_documents' => $kycDocuments,
            'proof_photos' => $proofPhotos,
            'booking_payment_proofs' => $bookingPaymentProofs,
            'booking_document_reviews' => $siteVisit->booking_document_reviews ?? [],
            'customer_name' => $siteVisit->customer_name ?: ($siteVisit->lead?->name ?: 'Customer'),
            'project' => $siteVisit->project ?: ($siteVisit->property_name ?: 'Project not filled'),
            'status' => $siteVisit->hasCompleteKyc() ? 'KYC complete' : 'KYC pending',
        ];
    }

    private function buildFinanceKycSnapshot(SiteVisit $siteVisit, KycFormSchemaService $kycFormSchemaService): array
    {
        $schema = $kycFormSchemaService->buildReadPayload($siteVisit);
        $fields = [];

        foreach (($schema['sections'] ?? []) as $section) {
            foreach (($section['fields'] ?? []) as $field) {
                $key = (string) ($field['field_key'] ?? '');
                if ($key === '') {
                    continue;
                }

                $fields[$key] = [
                    'label' => (string) ($field['label'] ?? $key),
                    'value' => $this->normalizeFinanceKycValue($field['value'] ?? null),
                    'sensitive' => $this->isSensitiveKycField($key),
                ];
            }
        }

        $fields['kyc_documents'] = [
            'label' => 'KYC Documents',
            'value' => $this->normalizeFinanceKycValue($siteVisit->kyc_documents ?? []),
            'sensitive' => true,
        ];
        $fields['proof_photos'] = [
            'label' => 'Proof Photos',
            'value' => $this->normalizeFinanceKycValue($siteVisit->closer_request_proof_photos ?? []),
            'sensitive' => false,
        ];
        $fields['booking_payment_proofs'] = [
            'label' => 'Booking Payment Proofs',
            'value' => $this->normalizeFinanceKycValue($siteVisit->booking_payment_proofs ?? []),
            'sensitive' => true,
        ];
        $fields['revenue_value'] = [
            'label' => 'Revenue Value',
            'value' => $this->normalizeFinanceKycValue($siteVisit->revenue_value),
            'sensitive' => false,
        ];
        $fields['revenue_note'] = [
            'label' => 'Revenue Note',
            'value' => $this->normalizeFinanceKycValue($siteVisit->revenue_note),
            'sensitive' => false,
        ];

        return $fields;
    }

    private function buildSubmittedFinanceKycSnapshot(Request $request, array $beforeSnapshot): array
    {
        $submitted = [];

        foreach ($beforeSnapshot as $key => $meta) {
            if (in_array($key, ['kyc_documents', 'proof_photos', 'booking_payment_proofs'], true)) {
                $existingKey = match ($key) {
                    'kyc_documents' => 'existing_kyc_documents',
                    'proof_photos' => 'existing_proof_photos',
                    default => 'existing_booking_payment_proofs',
                };
                $replaceKey = match ($key) {
                    'kyc_documents' => 'replace_kyc_documents',
                    'proof_photos' => 'replace_proof_photos',
                    default => 'replace_booking_payment_proofs',
                };
                $retained = $request->has($existingKey)
                    ? (array) $request->input($existingKey, [])
                    : (array) ($meta['value'] ?? []);
                $uploadCount = count((array) $request->file($key, []));
                $submittedValue = $this->normalizeFinanceKycValue($retained);
                if ($request->boolean($replaceKey)) {
                    $submittedValue[] = '__replace_current_files__';
                }
                if ($uploadCount > 0) {
                    $submittedValue[] = '__new_uploads:' . $uploadCount;
                }
            } else {
                $current = $meta['value'] ?? null;
                if (is_bool($current)) {
                    $submittedValue = $request->boolean($key);
                } elseif (is_array($current)) {
                    $submittedValue = (array) $request->input($key, []);
                } else {
                    $submittedValue = $request->input($key, '');
                }
            }

            $submitted[$key] = [
                'label' => (string) ($meta['label'] ?? $key),
                'value' => $this->normalizeFinanceKycValue($submittedValue),
                'sensitive' => (bool) ($meta['sensitive'] ?? false),
            ];
        }

        return $submitted;
    }

    private function buildFinanceKycChangeSet(array $beforeSnapshot, array $afterSnapshot): array
    {
        $changes = [];

        foreach ($beforeSnapshot as $key => $before) {
            $after = $afterSnapshot[$key] ?? null;
            if ($after === null) {
                continue;
            }

            $oldValue = $this->normalizeFinanceKycValue($before['value'] ?? null);
            $newValue = $this->normalizeFinanceKycValue($after['value'] ?? null);

            if ($this->financeKycValuesMatch($oldValue, $newValue)) {
                continue;
            }

            $isSensitive = (bool) (($before['sensitive'] ?? false) || ($after['sensitive'] ?? false));
            $changes[] = [
                'field' => $key,
                'label' => (string) ($before['label'] ?? $after['label'] ?? $key),
                'old' => $this->auditSafeKycValue($oldValue, $isSensitive),
                'new' => $this->auditSafeKycValue($newValue, $isSensitive),
                'sensitive' => $isSensitive,
            ];
        }

        return $changes;
    }

    private function appendFinanceKycOverwriteActivity(SiteVisit $siteVisit, Request $request, array $changes, string $reason): void
    {
        $activity = collect((array) $siteVisit->booking_activity_log)
            ->filter(fn ($entry) => is_array($entry))
            ->values()
            ->all();

        $activity[] = [
            'action' => 'finance_kyc_overwrite',
            'user_id' => $request->user()?->id,
            'user_name' => $request->user()?->name,
            'user_role' => $request->user()?->role,
            'remark' => $reason,
            'changes' => array_slice($changes, 0, 80),
            'changed_count' => count($changes),
            'sensitive_changed' => collect($changes)->contains(fn (array $change) => $change['sensitive'] ?? false),
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'at' => now()->toIso8601String(),
        ];

        $siteVisit->booking_activity_log = array_slice($activity, -100);
        $siteVisit->kyc_last_corrected_at = now();
        $siteVisit->save();
    }

    private function appendIncentiveActivity(?SiteVisit $siteVisit, Request $request, string $action, array $details): void
    {
        if (!$siteVisit) {
            return;
        }

        $activity = collect((array) $siteVisit->booking_activity_log)
            ->filter(fn ($entry) => is_array($entry))
            ->values()
            ->all();

        $activity[] = array_merge([
            'action' => $action,
            'user_id' => $request->user()?->id,
            'user_name' => $request->user()?->name,
            'user_role' => $request->user()?->role,
            'at' => now()->toIso8601String(),
        ], $details);

        $siteVisit->booking_activity_log = array_slice($activity, -100);
        $siteVisit->save();
    }

    private function normalizeFinanceKycValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $key => $item) {
                $normalized[$key] = $this->normalizeFinanceKycValue($item);
            }

            if (Arr::isAssoc($normalized)) {
                ksort($normalized);
                return $normalized;
            }

            sort($normalized);
            return array_values($normalized);
        }

        if (is_bool($value) || is_numeric($value) || $value === null) {
            return $value;
        }

        return trim((string) $value);
    }

    private function financeKycValuesMatch(mixed $oldValue, mixed $newValue): bool
    {
        return json_encode($oldValue, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            === json_encode($newValue, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function isSensitiveKycField(string $key): bool
    {
        $key = strtolower($key);

        return str_contains($key, 'pan')
            || str_contains($key, 'aadhaar')
            || str_contains($key, 'dob')
            || str_contains($key, 'date_of_birth')
            || str_contains($key, 'applicant_name')
            || str_starts_with($key, 'booking_')
            || str_starts_with($key, 'unit_details_')
            || in_array($key, ['kyc_documents', 'booking_payment_proofs'], true);
    }

    private function auditSafeKycValue(mixed $value, bool $sensitive): mixed
    {
        if (!$sensitive) {
            return $value;
        }

        if (is_array($value)) {
            return [
                'type' => 'protected',
                'count' => count($value),
            ];
        }

        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

        return strlen($text) <= 4
            ? str_repeat('*', strlen($text))
            : str_repeat('*', max(strlen($text) - 4, 0)) . substr($text, -4);
    }

    private function buildBookedCustomerFileUrl(?string $path, array $directoryHints = []): ?string
    {
        $resolvedPath = $this->resolveBookedCustomerStoragePath($path, $directoryHints);

        if ($resolvedPath === null) {
            return null;
        }

        if (filter_var($resolvedPath, FILTER_VALIDATE_URL)) {
            return $resolvedPath;
        }

        return route('finance-manager.booked-customers.file', ['path' => $resolvedPath]);
    }

    private function resolveBookedCustomerStoragePath(?string $path, array $directoryHints = []): ?string
    {
        $normalized = trim((string) $path);
        if ($normalized === '' || filter_var($normalized, FILTER_VALIDATE_URL)) {
            return $normalized !== '' ? $normalized : null;
        }

        $normalized = preg_replace('#^/?storage/#i', '', $normalized);
        $normalized = preg_replace('#^/?public/#i', '', $normalized);
        $normalized = ltrim((string) $normalized, '/');

        $candidates = [$normalized];

        if (!str_contains($normalized, '/')) {
            foreach ($directoryHints as $hint) {
                $hint = trim((string) $hint, '/');
                if ($hint !== '') {
                    $candidates[] = $hint . '/' . $normalized;
                }
            }
        }

        foreach (array_unique($candidates) as $candidate) {
            if ($candidate !== '' && Storage::disk('public')->exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function assertBookedCustomerAccessible(SiteVisit $siteVisit): void
    {
        $hasVerifiedCloserIncentive = $siteVisit->incentives()
            ->where('type', 'closer')
            ->where('status', 'verified')
            ->exists();

        abort_unless($siteVisit->status === 'completed' && $hasVerifiedCloserIncentive, 404);
    }

    private function extractCustomKycFiles(Request $request): array
    {
        $allFiles = $request->allFiles();
        unset($allFiles['kyc_documents'], $allFiles['proof_photos'], $allFiles['booking_payment_proofs']);

        $customFiles = [];
        foreach ($allFiles as $key => $value) {
            $customFiles[$key] = $value;
        }

        return $customFiles;
    }
}

<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRegularization;
use App\Models\AttendanceRecord;
use App\Models\LeaveType;
use App\Models\MailDeliveryLog;
use App\Models\PayrollDeductionHead;
use App\Models\PayrollFreeze;
use App\Models\PayrollPayslip;
use App\Models\PayrollPayslipSetting;
use App\Models\PayrollVersion;
use App\Models\User;
use App\Services\AttendanceAccessService;
use App\Services\AttendancePolicyResolver;
use App\Services\AttendanceRecordSyncService;
use App\Services\MailDeliveryLogger;
use App\Services\PayslipPdfService;
use App\Services\PayslipGenerationService;
use App\Services\PayrollAdjustmentService;
use App\Services\PayrollComputationService;
use App\Services\PayrollWorkflowService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PayrollController extends Controller
{
    public function __construct(
        protected AttendanceAccessService $attendanceAccessService,
        protected PayrollWorkflowService $workflow
    ) {
    }

    public function index(Request $request, PayrollComputationService $payrollService, PayslipGenerationService $payslipGenerationService)
    {
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);
        $status = (string) $request->input('status', 'all');

        $payrollService->computeMonth($year, $month);

        $freeze = PayrollFreeze::query()
            ->with(['items.user.role', 'lockedByUser'])
            ->where('year', $year)
            ->where('month', $month)
            ->latest()
            ->first();

        $canEdit = true;
        $lockMessage = null;
        if ($freeze) {
            if (in_array((string) $freeze->status, ['draft', 'employee_review', 'correction_requested', 'admin_rejected', 'frozen'], true)) {
                $freeze = $payrollService->syncFreezeItems($freeze);
                $payslipGenerationService->generateForFreeze($freeze, $request->user(), 'hr_payroll_auto_sync', true);
            }

            $canEdit = $this->workflow->acquireLock($freeze, $request->user());
            $freeze->refresh()->load(['items.user.role', 'lockedByUser']);
            $this->attachPayslipsToItems($freeze);

            if (!$canEdit) {
                $lockMessage = 'This payroll is being edited by ' . ($freeze->lockedByUser?->name ?: 'another user') . '. You can view only.';
            }
        }

        $payslips = collect();
        if ($freeze) {
            $payslips = $freeze->items
                ->map(fn ($item) => $item->payslip?->setRelation('user', $item->user))
                ->filter()
                ->when($status !== 'all', fn ($items) => $items->where('status', $status))
                ->values();
        }

        $users = User::with('role')
            ->where('is_active', true)
            ->whereIn('id', $this->attendanceAccessService->enabledProfilesInPeriodQuery(now()->setDate($year, $month, 1)->startOfMonth(), now()->setDate($year, $month, 1)->endOfMonth())->select('user_id'))
            ->orderBy('name')
            ->get();

        $heads = PayrollDeductionHead::where('is_active', true)->orderBy('name')->get();

        return view('hr-manager.payroll.index', compact('year', 'month', 'status', 'freeze', 'payslips', 'users', 'heads', 'canEdit', 'lockMessage'));
    }

    private function attachPayslipsToItems(PayrollFreeze $freeze): void
    {
        $payslipsByUser = PayrollPayslip::query()
            ->with('versions.changedBy')
            ->where('payroll_freeze_id', $freeze->id)
            ->get()
            ->keyBy('user_id');

        $freeze->items->each(function ($item) use ($payslipsByUser) {
            $item->setRelation('payslip', $payslipsByUser->get($item->user_id));
        });
    }

    public function prepare(Request $request)
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
            'notes' => 'nullable|string|max:2000',
        ]);

        $freeze = PayrollFreeze::query()
            ->where('year', $validated['year'])
            ->where('month', $validated['month'])
            ->latest()
            ->first();

        abort_if($freeze && !$this->workflow->acquireLock($freeze, $request->user()), 423, 'This payroll is being edited by another user. You can view only.');

        $this->workflow->preparePayroll((int) $validated['year'], (int) $validated['month'], $request->user(), null, $validated['notes'] ?? null);

        return back()->with('success', 'Payroll prepared.');
    }

    public function storeAdjustment(Request $request, PayrollAdjustmentService $service)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'payroll_deduction_head_id' => 'nullable|exists:payroll_deduction_heads,id',
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
            'label' => 'required|string|max:255',
            'type' => 'required|string|in:earning,deduction',
            'amount' => 'required|numeric|min:0|max:999999999.99',
            'remarks' => 'nullable|string|max:2000',
        ]);

        $freeze = PayrollFreeze::query()
            ->where('year', $validated['year'])
            ->where('month', $validated['month'])
            ->latest()
            ->first();

        abort_if($freeze && !$this->workflow->acquireLock($freeze, $request->user()), 423, 'This payroll is being edited by another user. You can view only.');

        $service->create($validated, $request->user());

        return back()->with('success', 'Adjustment saved. Prepare payroll again to refresh snapshots.');
    }

    public function releaseToEmployee(Request $request, PayrollFreeze $freeze)
    {
        $this->workflow->releaseToEmployeeReview($freeze, $request->user());

        return back()->with('success', 'Payroll sent to employees for review.');
    }

    private const EDITABLE_PAYSLIP_STATUSES = [
        PayrollPayslip::STATUS_PREVIEW,
        PayrollPayslip::STATUS_EMPLOYEE_REVIEW,
        PayrollPayslip::STATUS_CORRECTION_REQUESTED,
        PayrollPayslip::STATUS_CORRECTION_RESOLVED,
        PayrollPayslip::STATUS_ADMIN_REJECTED,
    ];

    public function previewPayslip(PayrollPayslip $payslip)
    {
        $payslip->load(['user.role', 'user.employeeProfile.department', 'user.employeeProfile.designation', 'payrollFreeze.lockedByUser', 'versions.changedBy']);
        $attendanceDetails = AttendanceRecord::query()
            ->with(['manualOverriddenByUser', 'manualClearedByUser', 'overrideLogs.actor'])
            ->where('user_id', $payslip->user_id)
            ->whereYear('attendance_date', (int) $payslip->year)
            ->whereMonth('attendance_date', (int) $payslip->month)
            ->orderBy('attendance_date')
            ->get();
        $regularizations = AttendanceRegularization::query()
            ->with('approvals.actor')
            ->where('user_id', $payslip->user_id)
            ->whereYear('attendance_date', (int) $payslip->year)
            ->whereMonth('attendance_date', (int) $payslip->month)
            ->latest('id')
            ->get()
            ->groupBy(fn (AttendanceRegularization $regularization) => $regularization->attendance_date?->toDateString());
        $canEditPayrollAttendance = $this->canEditPayslipAttendance($payslip, request()->user());
        $leaveTypes = LeaveType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('hr-manager.payroll.payslip-preview', [
            'payslip' => $payslip,
            'snapshot' => $payslip->snapshot_json ?? [],
            'attendance' => $payslip->snapshot_json['attendance_snapshot'] ?? [],
            'earnings' => collect($payslip->snapshot_json['earnings'] ?? []),
            'deductions' => collect($payslip->snapshot_json['deductions'] ?? []),
            'attendanceDetails' => $attendanceDetails,
            'regularizationsByDate' => $regularizations,
            'canEditPayrollAttendance' => $canEditPayrollAttendance,
            'leaveTypes' => $leaveTypes,
        ]);
    }

    public function overwriteAttendance(
        Request $request,
        PayrollPayslip $payslip,
        AttendancePolicyResolver $policyResolver,
        AttendanceRecordSyncService $recordSyncService,
        PayrollComputationService $payrollService,
        PayslipGenerationService $payslipGenerationService
    ) {
        $this->assertPayslipAttendanceEditable($payslip, $request->user());

        $allowedStatuses = [
            AttendanceRecord::STATUS_PRESENT,
            AttendanceRecord::STATUS_LATE,
            AttendanceRecord::STATUS_HALF_DAY,
            AttendanceRecord::STATUS_ABSENT,
            AttendanceRecord::STATUS_LEAVE,
            AttendanceRecord::STATUS_WEEK_OFF,
            AttendanceRecord::STATUS_HOLIDAY,
        ];

        $validated = $request->validate([
            'attendance_date' => ['required', 'date'],
            'manual_status' => ['required', Rule::in($allowedStatuses)],
            'leave_pay_type' => ['nullable', Rule::in(['paid', 'unpaid'])],
            'leave_type_id' => ['nullable', 'integer', 'exists:leave_types,id'],
            'manual_first_punch_in_at' => ['nullable', 'date_format:H:i'],
            'manual_last_punch_out_at' => ['nullable', 'date_format:H:i'],
            'manual_override_reason' => ['required', 'string', 'max:1000'],
            'refresh_payslip' => ['nullable', 'boolean'],
        ]);

        $date = Carbon::parse($validated['attendance_date'])->startOfDay();
        abort_unless((int) $date->year === (int) $payslip->year && (int) $date->month === (int) $payslip->month, 422, 'Attendance date must be inside payslip month.');

        DB::transaction(function () use ($payslip, $request, $validated, $date, $policyResolver, $recordSyncService, $payrollService, $payslipGenerationService) {
            $payslip->loadMissing('user', 'payrollFreeze');
            $resolved = $policyResolver->resolveForUser($payslip->user, $date);
            $record = $this->resolvePayrollAttendanceRecord($payslip, $date, $resolved['policy']?->id, $resolved['office']?->id);
            $leaveType = null;
            if ($validated['manual_status'] === AttendanceRecord::STATUS_LEAVE && !empty($validated['leave_type_id'])) {
                $leaveType = LeaveType::query()->where('is_active', true)->find((int) $validated['leave_type_id']);
            }
            $payableFraction = $this->payableFractionForPayrollOverride(
                $validated['manual_status'],
                $validated['leave_pay_type'] ?? null,
                $leaveType
            );
            $reason = $this->buildPayrollOverrideReason($validated['manual_override_reason'], $leaveType, $validated['manual_status'], $payableFraction);

            $recordSyncService->applyManualOverride($record, [
                'manual_status' => $validated['manual_status'],
                'manual_first_punch_in_at' => $this->combinePayrollTime($date, $validated['manual_first_punch_in_at'] ?? null),
                'manual_last_punch_out_at' => $this->combinePayrollTime($date, $validated['manual_last_punch_out_at'] ?? null),
                'manual_override_reason' => $reason,
                'payable_day_fraction' => $payableFraction,
            ], $request->user());

            if ($request->boolean('refresh_payslip', true)) {
                $this->refreshPayrollPayslip($payslip, $payrollService, $payslipGenerationService, 'hr_attendance_override');
            }
        });

        return redirect()
            ->route('hr-manager.payroll.payslips.preview', $payslip)
            ->with('success', 'Attendance updated and payslip refreshed.');
    }

    public function clearAttendanceOverride(
        Request $request,
        PayrollPayslip $payslip,
        AttendanceRecordSyncService $recordSyncService,
        PayrollComputationService $payrollService,
        PayslipGenerationService $payslipGenerationService
    ) {
        $this->assertPayslipAttendanceEditable($payslip, $request->user());

        $validated = $request->validate([
            'attendance_record_id' => ['required', 'integer', 'exists:attendance_records,id'],
            'clear_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $record = AttendanceRecord::query()
            ->where('user_id', $payslip->user_id)
            ->whereYear('attendance_date', (int) $payslip->year)
            ->whereMonth('attendance_date', (int) $payslip->month)
            ->findOrFail((int) $validated['attendance_record_id']);

        DB::transaction(function () use ($record, $request, $validated, $recordSyncService, $payslip, $payrollService, $payslipGenerationService) {
            $recordSyncService->clearManualOverride($record, $request->user(), $validated['clear_reason'] ?? 'Payroll preview correction cleared');
            $this->refreshPayrollPayslip($payslip, $payrollService, $payslipGenerationService, 'hr_attendance_override_cleared');
        });

        return redirect()
            ->route('hr-manager.payroll.payslips.preview', $payslip)
            ->with('success', 'Attendance override cleared and payslip refreshed.');
    }

    public function recalculatePayslip(Request $request, PayrollPayslip $payslip, PayrollComputationService $payrollService, PayslipGenerationService $payslipGenerationService)
    {
        $this->assertPayslipAttendanceEditable($payslip, $request->user());
        $this->refreshPayrollPayslip($payslip, $payrollService, $payslipGenerationService, 'hr_manual_recalculate');

        return redirect()
            ->route('hr-manager.payroll.payslips.preview', $payslip)
            ->with('success', 'Payslip recalculated.');
    }

    public function resendPreview(Request $request, PayrollPayslip $payslip, PayslipPdfService $pdfService, MailDeliveryLogger $mailLogger)
    {
        $this->assertPayslipAttendanceEditable($payslip, $request->user());
        $payslip->loadMissing('user');
        abort_unless($payslip->user?->email, 422, 'Employee email missing.');

        $settings = PayrollPayslipSetting::query()->firstOrCreate([], [
            'company_name' => config('app.name', 'Base CRM'),
        ]);
        $pdf = $pdfService->storePayslipPdf($payslip, $settings);
        $payslip->update(['pdf_path' => $pdf['path']]);
        $attendanceDetails = AttendanceRecord::query()
            ->where('user_id', $payslip->user_id)
            ->whereYear('attendance_date', (int) $payslip->year)
            ->whereMonth('attendance_date', (int) $payslip->month)
            ->whereIn('status', [
                AttendanceRecord::STATUS_ABSENT,
                AttendanceRecord::STATUS_HALF_DAY,
                AttendanceRecord::STATUS_LEAVE,
                AttendanceRecord::STATUS_LATE,
                AttendanceRecord::STATUS_WEEK_OFF,
                AttendanceRecord::STATUS_HOLIDAY,
            ])
            ->orderBy('attendance_date')
            ->get();

        $mailLog = $mailLogger->sendView(
            MailDeliveryLog::TYPE_PAYROLL_PREVIEW,
            sprintf('Updated payroll preview for %02d/%d', $payslip->month, $payslip->year),
            $payslip->user,
            'emails.payroll-payslip-preview',
            [
                'payslip' => $payslip->fresh('user'),
                'employee' => $payslip->user,
                'attendance' => $payslip->snapshot_json['attendance_snapshot'] ?? [],
                'earnings' => collect($payslip->snapshot_json['earnings'] ?? []),
                'deductions' => collect($payslip->snapshot_json['deductions'] ?? []),
                'attendanceDetails' => $attendanceDetails,
                'previewUrl' => $payslip->verification_token ? route('payslips.verify', $payslip->verification_token) : route('attendance.payslips'),
            ],
            [
                'payslip_id' => $payslip->id,
                'payroll_month' => sprintf('%02d/%04d', $payslip->month, $payslip->year),
                'net_pay' => $payslip->net_pay,
                'action' => 'hr_resend_updated_preview',
            ],
            $payslip,
            $request->user()?->id
        );

        $this->storePayrollVersionAudit($payslip->fresh(), $request->user(), 'hr_resend_preview', 'HR resent updated payroll preview.');

        return redirect()
            ->route('hr-manager.payroll.payslips.preview', $payslip)
            ->with($mailLog->status === MailDeliveryLog::STATUS_SENT ? 'success' : 'error', $mailLog->status === MailDeliveryLog::STATUS_SENT ? 'Updated payslip mailed to employee.' : 'Mail could not be sent: ' . ($mailLog->error_message ?: $mailLog->statusLabel()));
    }

    public function resolveCorrection(Request $request, PayrollPayslip $payslip)
    {
        $validated = $request->validate([
            'hr_resolution_note' => 'required|string|max:2000',
            'send_to_admin' => 'nullable|boolean',
        ]);

        abort_if(!$this->workflow->acquireLock($payslip->payrollFreeze, $request->user()), 423, 'This payroll is being edited by another user. You can view only.');

        $this->workflow->resolveCorrection($payslip, $request->user(), $validated['hr_resolution_note'], $request->boolean('send_to_admin'));

        return back()->with('success', 'Correction resolved.');
    }

    private function canEditPayslipAttendance(PayrollPayslip $payslip, ?User $actor): bool
    {
        if (!in_array((string) $payslip->status, self::EDITABLE_PAYSLIP_STATUSES, true)) {
            return false;
        }

        $freeze = $payslip->payrollFreeze;
        if (!$freeze) {
            return false;
        }

        return !($freeze->locked_by_user_id && $actor && $freeze->locked_by_user_id !== $actor->id && $freeze->lock_expires_at?->isFuture());
    }

    private function assertPayslipAttendanceEditable(PayrollPayslip $payslip, ?User $actor): void
    {
        $payslip->loadMissing('payrollFreeze');
        abort_unless($this->canEditPayslipAttendance($payslip, $actor), 422, 'This payslip is locked or already approved/paid.');
    }

    private function resolvePayrollAttendanceRecord(PayrollPayslip $payslip, Carbon $date, ?int $policyId, ?int $officeId): AttendanceRecord
    {
        $record = AttendanceRecord::query()
            ->where('user_id', $payslip->user_id)
            ->whereDate('attendance_date', $date->toDateString())
            ->first();

        if (!$record) {
            $record = AttendanceRecord::query()->create([
                'user_id' => $payslip->user_id,
                'attendance_date' => $date->toDateString(),
                'office_location_id' => $officeId,
                'attendance_policy_id' => $policyId,
                'status' => AttendanceRecord::STATUS_ABSENT,
                'status_source' => 'auto',
                'payable_day_fraction' => 0,
                'worked_minutes' => 0,
                'late_minutes' => 0,
                'has_missing_punch_out' => false,
                'finalized_at' => now(),
            ]);
        }

        return $record->loadMissing(['user.role', 'officeLocation']);
    }

    private function refreshPayrollPayslip(PayrollPayslip $payslip, PayrollComputationService $payrollService, PayslipGenerationService $payslipGenerationService, string $changeType): void
    {
        $payslip->loadMissing('payrollFreeze');
        if (!$payslip->payrollFreeze) {
            return;
        }

        $freeze = $payrollService->syncFreezeItems($payslip->payrollFreeze);
        $payslipGenerationService->generateForFreeze($freeze, request()->user(), $changeType, true);
    }

    private function payableFractionForPayrollOverride(string $status, ?string $leavePayType, ?LeaveType $leaveType = null): float
    {
        return match ($status) {
            AttendanceRecord::STATUS_ABSENT => 0.0,
            AttendanceRecord::STATUS_HALF_DAY => 0.5,
            AttendanceRecord::STATUS_LEAVE => $leaveType ? ($leaveType->is_paid ? 1.0 : 0.0) : ($leavePayType === 'unpaid' ? 0.0 : 1.0),
            default => 1.0,
        };
    }

    private function buildPayrollOverrideReason(string $reason, ?LeaveType $leaveType, string $status, float $payableFraction): string
    {
        $reason = trim($reason);
        if ($status !== AttendanceRecord::STATUS_LEAVE || !$leaveType) {
            return $reason;
        }

        $label = trim(($leaveType->code ? $leaveType->code . ' - ' : '') . $leaveType->name);
        $payLabel = $payableFraction > 0 ? 'Paid' : 'Unpaid';

        return "[Leave Type: {$label}; {$payLabel}] {$reason}";
    }

    private function combinePayrollTime(Carbon $date, ?string $time): ?Carbon
    {
        if (!$time) {
            return null;
        }

        return Carbon::parse($date->toDateString() . ' ' . $time);
    }

    private function storePayrollVersionAudit(PayrollPayslip $payslip, ?User $actor, string $changeType, string $remark): void
    {
        $versionNo = ((int) PayrollVersion::query()
            ->where('payroll_payslip_id', $payslip->id)
            ->max('version_no')) + 1;

        $snapshot = [
            'status' => $payslip->status,
            'gross_pay' => (float) $payslip->gross_pay,
            'total_deductions' => (float) $payslip->total_deductions,
            'net_pay' => (float) $payslip->net_pay,
            'snapshot_json' => $payslip->snapshot_json ?? [],
        ];

        PayrollVersion::query()->create([
            'payroll_freeze_id' => $payslip->payroll_freeze_id,
            'payroll_payslip_id' => $payslip->id,
            'version_no' => $versionNo,
            'changed_by' => $actor?->id,
            'change_type' => $changeType,
            'previous_snapshot' => $snapshot,
            'new_snapshot' => $snapshot,
            'remark' => $remark,
            'created_at' => now(),
        ]);
    }

    public function submitToAdmin(Request $request, PayrollFreeze $freeze)
    {
        $validated = $request->validate([
            'payslip_ids' => 'required|array|min:1',
            'payslip_ids.*' => 'integer|exists:payroll_payslips,id',
        ]);

        $count = $this->workflow->submitSelectedToAdmin($freeze, $request->user(), $validated['payslip_ids']);

        return back()->with('success', "{$count} payslip(s) sent to Admin.");
    }
}

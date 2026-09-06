<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\AttendanceRecord;
use App\Models\MailDeliveryLog;
use App\Models\PayrollFreeze;
use App\Models\PayrollPayslip;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayrollWorkflowService
{
    public const FREEZE_DRAFT = 'draft';
    public const FREEZE_EMPLOYEE_REVIEW = 'employee_review';
    public const FREEZE_CORRECTION_REQUESTED = 'correction_requested';
    public const FREEZE_HR_LOCKED = 'hr_locked';
    public const FREEZE_ADMIN_PARTIALLY_APPROVED = 'admin_partially_approved';
    public const FREEZE_ADMIN_APPROVED = 'admin_approved';
    public const FREEZE_ADMIN_REJECTED = 'admin_rejected';
    public const FREEZE_FINANCE_PROCESSING = 'finance_processing';
    public const FREEZE_PAID = 'paid';

    public function __construct(
        protected PayrollComputationService $payrollService,
        protected PayslipGenerationService $payslipGenerationService
    ) {
    }

    public function preparePayroll(int $year, int $month, User $actor, ?int $officeLocationId = null, ?string $notes = null): PayrollFreeze
    {
        return DB::transaction(function () use ($year, $month, $actor, $officeLocationId, $notes) {
            $freeze = $this->payrollService->freezeMonth($year, $month, $actor, $officeLocationId, $notes);
            $this->payslipGenerationService->generateForFreeze($freeze, $actor, 'hr_prepare');

            $freeze->update([
                'status' => self::FREEZE_DRAFT,
                'locked_by_user_id' => null,
                'locked_at' => null,
                'lock_expires_at' => null,
            ]);

            return $freeze->fresh(['items.user']);
        });
    }

    public function releaseToEmployeeReview(PayrollFreeze $freeze, User $actor): void
    {
        $this->assertFreezeEditableByHr($freeze);
        $this->assertLockOwner($freeze, $actor);

        DB::transaction(function () use ($freeze, $actor) {
            $this->itemsWithPayslips($freeze)->each(function ($item) {
                if ($item->payslip && !$this->isFinalPayslip($item->payslip)) {
                    $item->payslip->update([
                        'status' => PayrollPayslip::STATUS_EMPLOYEE_REVIEW,
                    ]);
                }
            });

            $freeze->update([
                'status' => self::FREEZE_EMPLOYEE_REVIEW,
                'hr_finalized_by' => $actor->id,
                'hr_finalized_at' => now(),
                'locked_by_user_id' => null,
                'locked_at' => null,
                'lock_expires_at' => null,
            ]);
        });

        $freeze->items()->with('user')->get()->each(function ($item) use ($freeze) {
            if ($item->user) {
                $this->notify($item->user, 'Payroll ready for review', 'Your payroll preview is ready. Please review it.', $freeze, $item->user);
                $payslip = PayrollPayslip::query()
                    ->where('payroll_freeze_id', $freeze->id)
                    ->where('user_id', $item->user_id)
                    ->first();

                if ($payslip) {
                    $this->mailPayslipPreview($payslip);
                }
            }
        });
    }

    public function requestCorrection(PayrollPayslip $payslip, User $employee, string $note): void
    {
        abort_unless($payslip->user_id === $employee->id, 403);
        abort_unless($payslip->canEmployeeRequestCorrection(), 422, 'Correction request is closed for this payslip.');

        $payslip->update([
            'status' => PayrollPayslip::STATUS_CORRECTION_REQUESTED,
            'employee_correction_note' => $note,
            'employee_correction_requested_at' => now(),
        ]);

        $this->refreshFreezeStatus($payslip->payrollFreeze);

        $this->hrUsers()->each(fn (User $hr) => $this->notify(
            $hr,
            'Payroll correction requested',
            "{$employee->name} requested a payroll correction.",
            $payslip->payrollFreeze,
            $employee
        ));
    }

    public function resolveCorrection(PayrollPayslip $payslip, User $hr, string $note, bool $sendToAdmin = false): void
    {
        abort_unless($payslip->status === PayrollPayslip::STATUS_CORRECTION_REQUESTED, 422, 'Only correction requested payslips can be resolved.');

        $payslip->update([
            'status' => $sendToAdmin ? PayrollPayslip::STATUS_HR_LOCKED : PayrollPayslip::STATUS_CORRECTION_RESOLVED,
            'hr_resolution_note' => $note,
            'hr_resolved_by' => $hr->id,
            'hr_resolved_at' => now(),
        ]);

        $this->refreshFreezeStatus($payslip->payrollFreeze);

        if ($payslip->user) {
            $this->notify($payslip->user, 'Payroll correction resolved', 'HR has resolved your payroll correction.', $payslip->payrollFreeze, $payslip->user);
        }
    }

    public function submitSelectedToAdmin(PayrollFreeze $freeze, User $hr, array $payslipIds): int
    {
        $this->assertFreezeEditableByHr($freeze);
        $this->assertLockOwner($freeze, $hr);

        $allowed = [
            PayrollPayslip::STATUS_PREVIEW,
            PayrollPayslip::STATUS_EMPLOYEE_REVIEW,
            PayrollPayslip::STATUS_CORRECTION_RESOLVED,
            PayrollPayslip::STATUS_ADMIN_REJECTED,
        ];

        $count = PayrollPayslip::query()
            ->where('payroll_freeze_id', $freeze->id)
            ->whereIn('id', $payslipIds)
            ->whereIn('status', $allowed)
            ->update([
                'status' => PayrollPayslip::STATUS_HR_LOCKED,
            ]);

        $freeze->update([
            'status' => self::FREEZE_HR_LOCKED,
            'submitted_to_admin_by' => $hr->id,
            'submitted_to_admin_at' => now(),
            'locked_by_user_id' => null,
            'locked_at' => null,
            'lock_expires_at' => null,
        ]);

        $this->adminUsers()->each(fn (User $admin) => $this->notify($admin, 'Payroll sent for approval', 'HR submitted payroll for approval.', $freeze));

        return $count;
    }

    public function approveSelected(PayrollFreeze $freeze, User $admin, array $payslipIds): int
    {
        $count = PayrollPayslip::query()
            ->where('payroll_freeze_id', $freeze->id)
            ->whereIn('id', $payslipIds)
            ->where('status', PayrollPayslip::STATUS_HR_LOCKED)
            ->update([
                'status' => PayrollPayslip::STATUS_PAYMENT_PENDING,
                'admin_reviewed_by' => $admin->id,
                'admin_reviewed_at' => now(),
                'admin_remark' => null,
            ]);

        $freeze->update([
            'admin_reviewed_by' => $admin->id,
            'admin_reviewed_at' => now(),
        ]);
        $this->refreshFreezeStatus($freeze);

        $this->hrUsers()->each(fn (User $hr) => $this->notify($hr, 'Payroll approved', "{$count} payslip(s) approved.", $freeze));
        $this->financeUsers()->each(fn (User $finance) => $this->notify($finance, 'Payroll ready for payment', "{$count} approved payslip(s) are ready for payment.", $freeze));

        return $count;
    }

    public function rejectSelected(PayrollFreeze $freeze, User $admin, array $payslipIds, string $remark): int
    {
        $count = PayrollPayslip::query()
            ->where('payroll_freeze_id', $freeze->id)
            ->whereIn('id', $payslipIds)
            ->where('status', PayrollPayslip::STATUS_HR_LOCKED)
            ->update([
                'status' => PayrollPayslip::STATUS_ADMIN_REJECTED,
                'admin_reviewed_by' => $admin->id,
                'admin_reviewed_at' => now(),
                'admin_remark' => $remark,
            ]);

        $freeze->update([
            'admin_reviewed_by' => $admin->id,
            'admin_reviewed_at' => now(),
            'admin_remark' => $remark,
        ]);
        $this->refreshFreezeStatus($freeze);

        $this->hrUsers()->each(fn (User $hr) => $this->notify($hr, 'Payroll rejected', "{$count} payslip(s) need correction. Remark: {$remark}", $freeze));

        return $count;
    }

    public function markPaid(PayrollPayslip $payslip, User $finance, array $data, $proof = null): void
    {
        abort_unless(in_array($payslip->status, [PayrollPayslip::STATUS_ADMIN_APPROVED, PayrollPayslip::STATUS_PAYMENT_PENDING], true), 422, 'Only approved payslips can be paid.');

        $proofPath = $proof ? $proof->store('payroll/payment-proofs', 'public') : $payslip->payment_proof_path;

        $payslip->update([
            'status' => PayrollPayslip::STATUS_PAID,
            'payment_mode' => $data['payment_mode'],
            'payment_reference' => $data['payment_reference'],
            'paid_amount' => $data['paid_amount'],
            'payment_proof_path' => $proofPath,
            'paid_at' => $data['paid_at'] ?? now(),
            'finance_paid_by' => $finance->id,
            'finance_remark' => $data['finance_remark'] ?? null,
        ]);

        $this->refreshFreezeStatus($payslip->payrollFreeze);

        if ($payslip->user) {
            $this->notify($payslip->user, 'Payroll paid', 'Your payroll payment has been marked paid.', $payslip->payrollFreeze, $payslip->user);
        }
    }

    public function acquireLock(PayrollFreeze $freeze, User $user): bool
    {
        if ($freeze->locked_by_user_id && $freeze->locked_by_user_id !== $user->id && $freeze->lock_expires_at?->isFuture()) {
            return false;
        }

        $freeze->update([
            'locked_by_user_id' => $user->id,
            'locked_at' => now(),
            'lock_expires_at' => now()->addMinutes(15),
        ]);

        return true;
    }

    public function refreshFreezeStatus(?PayrollFreeze $freeze): void
    {
        if (!$freeze) {
            return;
        }

        $statuses = $this->itemsWithPayslips($freeze)
            ->map(fn ($item) => $item->payslip?->status)
            ->filter()
            ->values();

        if ($statuses->isEmpty()) {
            return;
        }

        $newStatus = match (true) {
            $statuses->every(fn ($status) => $status === PayrollPayslip::STATUS_PAID) => self::FREEZE_PAID,
            $statuses->contains(PayrollPayslip::STATUS_PAID) => self::FREEZE_FINANCE_PROCESSING,
            $statuses->every(fn ($status) => in_array($status, [PayrollPayslip::STATUS_PAYMENT_PENDING, PayrollPayslip::STATUS_ADMIN_APPROVED, PayrollPayslip::STATUS_PAID], true)) => self::FREEZE_ADMIN_APPROVED,
            $statuses->contains(PayrollPayslip::STATUS_PAYMENT_PENDING) || $statuses->contains(PayrollPayslip::STATUS_ADMIN_APPROVED) => self::FREEZE_ADMIN_PARTIALLY_APPROVED,
            $statuses->contains(PayrollPayslip::STATUS_ADMIN_REJECTED) => self::FREEZE_ADMIN_REJECTED,
            $statuses->every(fn ($status) => $status === PayrollPayslip::STATUS_HR_LOCKED) => self::FREEZE_HR_LOCKED,
            $statuses->contains(PayrollPayslip::STATUS_CORRECTION_REQUESTED) => self::FREEZE_CORRECTION_REQUESTED,
            $statuses->contains(PayrollPayslip::STATUS_EMPLOYEE_REVIEW) => self::FREEZE_EMPLOYEE_REVIEW,
            default => self::FREEZE_DRAFT,
        };

        $freeze->update(['status' => $newStatus]);
    }

    public function statusLabel(string $status): string
    {
        return [
            PayrollPayslip::STATUS_PREVIEW => 'Need Review',
            PayrollPayslip::STATUS_EMPLOYEE_REVIEW => 'Need Review',
            PayrollPayslip::STATUS_CORRECTION_REQUESTED => 'Correction Needed',
            PayrollPayslip::STATUS_CORRECTION_RESOLVED => 'Resolved',
            PayrollPayslip::STATUS_HR_LOCKED => 'Ready for Admin',
            PayrollPayslip::STATUS_ADMIN_APPROVED => 'Approved',
            PayrollPayslip::STATUS_PAYMENT_PENDING => 'Payment Pending',
            PayrollPayslip::STATUS_ADMIN_REJECTED => 'Rejected',
            PayrollPayslip::STATUS_PAID => 'Paid',
            PayrollPayslip::STATUS_GENERATED => 'Generated',
        ][$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    private function assertFreezeEditableByHr(PayrollFreeze $freeze): void
    {
        abort_if(in_array($freeze->status, [self::FREEZE_ADMIN_APPROVED, self::FREEZE_FINANCE_PROCESSING, self::FREEZE_PAID], true), 422, 'Admin approved payroll cannot be edited by HR.');
    }

    private function assertLockOwner(PayrollFreeze $freeze, User $user): void
    {
        $freeze->refresh();

        abort_if(
            $freeze->locked_by_user_id
                && $freeze->locked_by_user_id !== $user->id
                && $freeze->lock_expires_at?->isFuture(),
            423,
            'This payroll is being edited by another user. You can view only.'
        );
    }

    private function isFinalPayslip(PayrollPayslip $payslip): bool
    {
        return in_array($payslip->status, [
            PayrollPayslip::STATUS_PAYMENT_PENDING,
            PayrollPayslip::STATUS_ADMIN_APPROVED,
            PayrollPayslip::STATUS_PAID,
            PayrollPayslip::STATUS_GENERATED,
        ], true);
    }

    private function itemsWithPayslips(PayrollFreeze $freeze): Collection
    {
        $items = $freeze->items()->get();
        $payslipsByUser = PayrollPayslip::query()
            ->where('payroll_freeze_id', $freeze->id)
            ->get()
            ->keyBy('user_id');

        return $items->each(function ($item) use ($payslipsByUser) {
            $item->setRelation('payslip', $payslipsByUser->get($item->user_id));
        });
    }

    private function notify(User $user, string $title, string $message, PayrollFreeze $freeze, ?User $employee = null): void
    {
        AppNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'payroll',
            'title' => $title,
            'message' => $message,
            'data' => [
                'payroll_month' => sprintf('%02d/%04d', $freeze->month, $freeze->year),
                'employee_id' => $employee?->id,
                'employee_name' => $employee?->name,
                'status' => $freeze->status,
            ],
            'action_type' => 'payroll',
            'action_url' => $this->actionUrlFor($user),
        ]);
    }

    private function mailPayslipPreview(PayrollPayslip $payslip): void
    {
        $payslip->loadMissing('user');
        $employee = $payslip->user;
        if (!$employee?->email) {
            return;
        }

        $attendance = $payslip->snapshot_json['attendance_snapshot'] ?? [];
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

        $subject = sprintf('Payroll preview for %02d/%d', $payslip->month, $payslip->year);
        $mailLog = app(MailDeliveryLogger::class)->sendView(
            MailDeliveryLog::TYPE_PAYROLL_PREVIEW,
            $subject,
            $employee,
            'emails.payroll-payslip-preview',
            [
                'payslip' => $payslip,
                'employee' => $employee,
                'attendance' => $attendance,
                'earnings' => collect($payslip->snapshot_json['earnings'] ?? []),
                'deductions' => collect($payslip->snapshot_json['deductions'] ?? []),
                'attendanceDetails' => $attendanceDetails,
                'previewUrl' => $payslip->verification_token ? route('payslips.verify', $payslip->verification_token) : route('attendance.payslips'),
            ],
            [
                'payslip_id' => $payslip->id,
                'payroll_month' => sprintf('%02d/%04d', $payslip->month, $payslip->year),
                'net_pay' => $payslip->net_pay,
            ],
            $payslip
        );

        if ($mailLog->status !== MailDeliveryLog::STATUS_SENT) {
            Log::warning('Payroll preview email failed', [
                'payslip_id' => $payslip->id,
                'user_id' => $employee->id,
                'email' => $employee->email,
                'error' => $mailLog->error_message,
            ]);
        }
    }

    private function actionUrlFor(User $user): string
    {
        if ($user->isHrManager()) {
            return route('hr-manager.payroll.index');
        }
        if ($user->isFinanceManager()) {
            return route('finance-manager.payroll');
        }
        if ($user->isAdmin()) {
            return route('admin.payroll-approvals.index');
        }

        return route('attendance.payslips');
    }

    private function hrUsers(): Collection
    {
        return User::query()->whereHas('role', fn ($query) => $query->where('slug', Role::HR_MANAGER))->where('is_active', true)->get();
    }

    private function adminUsers(): Collection
    {
        return User::query()->whereHas('role', fn ($query) => $query->where('slug', Role::ADMIN))->where('is_active', true)->get();
    }

    private function financeUsers(): Collection
    {
        return User::query()->whereHas('role', fn ($query) => $query->where('slug', Role::FINANCE_MANAGER))->where('is_active', true)->get();
    }
}

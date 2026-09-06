<?php

namespace App\Services;

use App\Models\PayrollFreeze;
use App\Models\PayrollPayslip;
use App\Models\PayrollPayslipSetting;
use App\Models\PayrollVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PayslipGenerationService
{
    public function __construct(
        protected PayrollComputationService $payrollComputationService,
        protected PayslipPdfService $pdfService
    ) {
    }

    public function generateForFreeze(PayrollFreeze $freeze, ?User $actor = null, string $changeType = 'generated', bool $onlyMissing = false): int
    {
        $allowedStatuses = $onlyMissing
            ? ['frozen', 'draft', 'employee_review', 'correction_requested', 'admin_rejected']
            : ['frozen'];

        if (!in_array((string) $freeze->status, $allowedStatuses, true)) {
            abort(422, 'Payslips can only be generated for frozen payroll months.');
        }

        $settings = PayrollPayslipSetting::query()->firstOrCreate([], [
            'company_name' => config('app.name', 'Base CRM'),
        ]);

        $count = 0;
        DB::transaction(function () use ($freeze, $settings, $actor, $changeType, $onlyMissing, &$count) {
            foreach ($freeze->items()->with('user')->get() as $item) {
                $snapshot = $this->payrollComputationService->buildPayslipSnapshotFromFreezeItem($item);
                $payslip = PayrollPayslip::firstOrNew([
                    'user_id' => $item->user_id,
                    'year' => $freeze->year,
                    'month' => $freeze->month,
                ]);

                if ($payslip->exists && $this->isFinalPayslip($payslip)) {
                    continue;
                }

                if ($onlyMissing && $payslip->exists && ! $this->salaryChanged($payslip, $snapshot)) {
                    continue;
                }

                $previousSnapshot = $payslip->exists ? $this->buildVersionSnapshot($payslip) : null;
                $salaryChanged = $payslip->exists && $this->salaryChanged($payslip, $snapshot);

                $payslip->fill([
                    'payroll_freeze_id' => $freeze->id,
                    'payslip_number' => $this->buildPayslipNumber($settings, $freeze->year, $freeze->month, $item->user_id),
                    'gross_pay' => $snapshot['gross_pay'],
                    'total_deductions' => $snapshot['total_deductions'],
                    'net_pay' => $snapshot['net_pay'],
                    'generated_at' => now(),
                    'snapshot_json' => $snapshot,
                    'status' => $payslip->exists ? $this->nextEditableStatus($payslip) : $this->initialStatusForFreeze($freeze),
                ]);

                if (!$payslip->verification_token) {
                    $payslip->verification_token = Str::random(48);
                }

                $payslip->save();

                $pdf = $this->pdfService->storePayslipPdf($payslip, $settings);
                $payslip->update(['pdf_path' => $pdf['path']]);

                if ($salaryChanged) {
                    $this->storeVersion($payslip->fresh('payrollFreeze'), $previousSnapshot, $actor, $changeType);
                }

                $count++;
            }
        });

        return $count;
    }

    private function initialStatusForFreeze(PayrollFreeze $freeze): string
    {
        return match ((string) $freeze->status) {
            'employee_review' => PayrollPayslip::STATUS_EMPLOYEE_REVIEW,
            default => PayrollPayslip::STATUS_PREVIEW,
        };
    }

    private function buildPayslipNumber(PayrollPayslipSetting $settings, int $year, int $month, int $userId): string
    {
        return sprintf('%s-%04d%02d-%04d', $settings->payslip_prefix ?: 'PSL', $year, $month, $userId);
    }

    private function nextEditableStatus(PayrollPayslip $payslip): string
    {
        if (in_array($payslip->status, [
            PayrollPayslip::STATUS_ADMIN_APPROVED,
            PayrollPayslip::STATUS_PAYMENT_PENDING,
            PayrollPayslip::STATUS_PAID,
            PayrollPayslip::STATUS_GENERATED,
        ], true)) {
            return $payslip->status;
        }

        return $payslip->status ?: PayrollPayslip::STATUS_PREVIEW;
    }

    private function isFinalPayslip(PayrollPayslip $payslip): bool
    {
        return in_array($payslip->status, [
            PayrollPayslip::STATUS_ADMIN_APPROVED,
            PayrollPayslip::STATUS_PAYMENT_PENDING,
            PayrollPayslip::STATUS_PAID,
            PayrollPayslip::STATUS_GENERATED,
        ], true);
    }

    private function salaryChanged(PayrollPayslip $payslip, array $newSnapshot): bool
    {
        return round((float) $payslip->gross_pay, 2) !== round((float) ($newSnapshot['gross_pay'] ?? 0), 2)
            || round((float) $payslip->total_deductions, 2) !== round((float) ($newSnapshot['total_deductions'] ?? 0), 2)
            || round((float) $payslip->net_pay, 2) !== round((float) ($newSnapshot['net_pay'] ?? 0), 2)
            || json_encode($payslip->snapshot_json ?? []) !== json_encode($newSnapshot);
    }

    private function buildVersionSnapshot(PayrollPayslip $payslip): array
    {
        return [
            'status' => $payslip->status,
            'gross_pay' => (float) $payslip->gross_pay,
            'total_deductions' => (float) $payslip->total_deductions,
            'net_pay' => (float) $payslip->net_pay,
            'snapshot_json' => $payslip->snapshot_json ?? [],
        ];
    }

    private function storeVersion(PayrollPayslip $payslip, ?array $previousSnapshot, ?User $actor, string $changeType): void
    {
        $versionNo = ((int) PayrollVersion::query()
            ->where('payroll_payslip_id', $payslip->id)
            ->max('version_no')) + 1;

        PayrollVersion::query()->create([
            'payroll_freeze_id' => $payslip->payroll_freeze_id,
            'payroll_payslip_id' => $payslip->id,
            'version_no' => $versionNo,
            'changed_by' => $actor?->id,
            'change_type' => $changeType,
            'previous_snapshot' => $previousSnapshot,
            'new_snapshot' => $this->buildVersionSnapshot($payslip),
            'remark' => 'Salary-impacting payroll snapshot changed.',
            'created_at' => now(),
        ]);
    }
}

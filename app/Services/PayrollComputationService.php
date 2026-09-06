<?php

namespace App\Services;

use App\Models\AttendanceMonthlyRollup;
use App\Models\AttendanceOvertime;
use App\Models\AttendancePolicy;
use App\Models\AttendanceRecord;
use App\Models\PayrollFreeze;
use App\Models\PayrollFreezeItem;
use App\Models\PayrollManualAdjustment;
use App\Models\User;
use App\Models\UserAttendanceProfile;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Collection;

class PayrollComputationService
{
    public function __construct(
        protected AttendanceAccessService $attendanceAccessService,
        protected SalaryStructureService $salaryStructureService
    ) {
    }

    public function computeMonth(int $year, int $month, ?int $officeLocationId = null): Collection
    {
        $monthDate = Carbon::create($year, $month, 1);

        $profiles = UserAttendanceProfile::with(['user.role', 'officeLocation', 'attendancePolicy'])
            ->whereIn('id', $this->attendanceAccessService->enabledProfilesInPeriodQuery($monthDate->copy()->startOfMonth(), $monthDate->copy()->endOfMonth())->select('id'))
            ->when($officeLocationId, fn ($query) => $query->where('office_location_id', $officeLocationId))
            ->get();

        return $profiles->map(function (UserAttendanceProfile $profile) use ($year, $month) {
            return $this->computeUserMonth($profile->user, $year, $month, $profile);
        });
    }

    public function computeUserMonth(User $user, int $year, int $month, ?UserAttendanceProfile $profile = null): AttendanceMonthlyRollup
    {
        $profile ??= UserAttendanceProfile::with(['officeLocation', 'attendancePolicy'])->where('user_id', $user->id)->first();
        $policy = $profile?->attendancePolicy;
        $records = AttendanceRecord::query()
            ->where('user_id', $user->id)
            ->whereYear('attendance_date', $year)
            ->whereMonth('attendance_date', $month)
            ->get();

        $presentDays = (float) $records->where('status', AttendanceRecord::STATUS_PRESENT)->count()
            + (float) $records->where('status', AttendanceRecord::STATUS_LATE)->count();
        $halfDays = (float) $records->where('status', AttendanceRecord::STATUS_HALF_DAY)->count();
        $absentDays = (float) $records->where('status', AttendanceRecord::STATUS_ABSENT)->count();
        $paidLeaveDays = (float) $records->where('status', AttendanceRecord::STATUS_LEAVE)
            ->filter(fn ($record) => (float) $record->payable_day_fraction > 0)
            ->count();
        $unpaidLeaveDays = (float) $records->where('status', AttendanceRecord::STATUS_LEAVE)
            ->filter(fn ($record) => (float) $record->payable_day_fraction <= 0)
            ->count();
        $weekoffDays = (float) $records->where('status', AttendanceRecord::STATUS_WEEK_OFF)->count();
        $holidayDays = (float) $records->where('status', AttendanceRecord::STATUS_HOLIDAY)->count();
        $lateCount = (int) $records->where('status', AttendanceRecord::STATUS_LATE)->count();
        $latePenaltyDays = $this->latePenaltyDays($policy, $lateCount);
        $payableDays = max(0, $presentDays + ($halfDays * 0.5) + $paidLeaveDays + $weekoffDays + $holidayDays - $latePenaltyDays);
        $monthDate = Carbon::create($year, $month, 1);
        $salaryProfile = $this->salaryStructureService->resolveProfileForUser($user, $monthDate);
        $monthlySalary = $this->monthlySalaryFromProfile($salaryProfile)
            ?: (float) ($profile?->base_salary ?? 0);
        $estimatedSalary = $monthlySalary > 0 ? round(($monthlySalary / max(1, $monthDate->daysInMonth)) * $payableDays, 2) : null;
        $approvedOvertimeMinutes = Schema::hasTable('attendance_overtimes')
            ? (int) AttendanceOvertime::query()
                ->where('user_id', $user->id)
                ->whereYear('attendance_date', $year)
                ->whereMonth('attendance_date', $month)
                ->where('status', 'approved')
                ->sum('overtime_minutes')
            : 0;

        return AttendanceMonthlyRollup::updateOrCreate(
            [
                'user_id' => $user->id,
                'year' => $year,
                'month' => $month,
            ],
            [
                'office_location_id' => $profile?->office_location_id,
                'present_days' => $presentDays,
                'half_days' => $halfDays,
                'absent_days' => $absentDays,
                'paid_leave_days' => $paidLeaveDays,
                'unpaid_leave_days' => $unpaidLeaveDays,
                'weekoff_days' => $weekoffDays,
                'holiday_days' => $holidayDays,
                'late_count' => $lateCount,
                'late_penalty_days' => $latePenaltyDays,
                'overtime_minutes' => $approvedOvertimeMinutes,
                'payable_days' => $payableDays,
                'estimated_salary' => $estimatedSalary,
            ]
        );
    }

    public function freezeMonth(int $year, int $month, User $actor, ?int $officeLocationId = null, ?string $notes = null): PayrollFreeze
    {
        $rollups = $this->computeMonth($year, $month, $officeLocationId);

        $freeze = PayrollFreeze::updateOrCreate(
            [
                'year' => $year,
                'month' => $month,
                'office_location_id' => $officeLocationId,
            ],
            [
                'freeze_scope' => $officeLocationId ? 'office' : 'company',
                'status' => 'frozen',
                'frozen_by' => $actor->id,
                'frozen_at' => now(),
                'notes' => $notes,
            ]
        );

        foreach ($rollups as $rollup) {
            $rollup->update([
                'is_frozen' => true,
                'frozen_at' => now(),
            ]);

            PayrollFreezeItem::updateOrCreate(
                [
                    'payroll_freeze_id' => $freeze->id,
                    'user_id' => $rollup->user_id,
                ],
                [
                    'attendance_monthly_rollup_id' => $rollup->id,
                    'snapshot_json' => $rollup->toArray(),
                ]
            );
        }

        return $freeze->load('items.user');
    }

    public function syncFreezeItems(PayrollFreeze $freeze): PayrollFreeze
    {
        $rollups = $this->computeMonth((int) $freeze->year, (int) $freeze->month, $freeze->office_location_id);

        foreach ($rollups as $rollup) {
            $rollup->update([
                'is_frozen' => true,
                'frozen_at' => $rollup->frozen_at ?: now(),
            ]);

            PayrollFreezeItem::updateOrCreate(
                [
                    'payroll_freeze_id' => $freeze->id,
                    'user_id' => $rollup->user_id,
                ],
                [
                    'attendance_monthly_rollup_id' => $rollup->id,
                    'snapshot_json' => $rollup->toArray(),
                ]
            );
        }

        return $freeze->fresh(['items.user']);
    }

    public function releaseFreeze(PayrollFreeze $freeze): PayrollFreeze
    {
        $freeze->update([
            'status' => 'released',
        ]);

        AttendanceMonthlyRollup::query()
            ->where('year', $freeze->year)
            ->where('month', $freeze->month)
            ->when($freeze->office_location_id, fn ($query) => $query->where('office_location_id', $freeze->office_location_id))
            ->update([
                'is_frozen' => false,
                'frozen_at' => null,
            ]);

        return $freeze->fresh();
    }

    public function buildPayslipSnapshotFromFreezeItem(PayrollFreezeItem $item): array
    {
        $snapshot = $item->snapshot_json ?? [];
        $date = Carbon::create((int) $snapshot['year'], (int) $snapshot['month'], 1);
        $salaryProfile = $this->salaryStructureService->resolveProfileForUser($item->user, $date);
        $baseSalary = (float) ($salaryProfile?->base_salary ?? $snapshot['estimated_salary'] ?? 0);

        $earnings = collect([
            [
                'code' => 'BASE',
                'label' => 'Base Salary',
                'amount' => round($baseSalary, 2),
                'type' => 'earning',
            ],
        ]);
        $deductions = collect();

        $components = $salaryProfile?->salaryStructure?->components ?? collect();
        foreach ($components as $component) {
            if (!$component->is_active) {
                continue;
            }

            $amount = $component->calc_type === 'percent_of_base'
                ? round($baseSalary * (((float) $component->value) / 100), 2)
                : round((float) $component->value, 2);

            $line = [
                'code' => $component->code,
                'label' => $component->label,
                'amount' => $amount,
                'type' => $component->component_type,
            ];

            if ($component->component_type === 'deduction') {
                $deductions->push($line);
            } else {
                $earnings->push($line);
            }
        }

        $manualAdjustments = PayrollManualAdjustment::query()
            ->where('user_id', $item->user_id)
            ->where('year', $date->year)
            ->where('month', $date->month)
            ->get();

        $manualEarnings = $manualAdjustments->where('type', 'earning')->map(fn ($adjustment) => [
            'code' => 'MANUAL_EARNING',
            'label' => $adjustment->label,
            'amount' => (float) $adjustment->amount,
            'type' => 'earning',
            'remarks' => $adjustment->remarks,
        ]);
        $manualDeductions = $manualAdjustments->where('type', 'deduction')->map(fn ($adjustment) => [
            'code' => 'MANUAL_DEDUCTION',
            'label' => $adjustment->label,
            'amount' => (float) $adjustment->amount,
            'type' => 'deduction',
            'remarks' => $adjustment->remarks,
        ]);

        $earnings = $earnings->merge($manualEarnings);
        $grossSalaryForAttendance = round($earnings->sum('amount'), 2);
        $attendanceDeductions = $this->buildAttendanceDeductions($snapshot, $grossSalaryForAttendance, $date);
        $deductions = $deductions
            ->merge($attendanceDeductions)
            ->merge($manualDeductions);

        $grossPay = round($earnings->sum('amount'), 2);
        $totalDeductions = round($deductions->sum('amount'), 2);

        return [
            'year' => $date->year,
            'month' => $date->month,
            'user_id' => $item->user_id,
            'base_salary' => $baseSalary,
            'attendance_snapshot' => $snapshot,
            'earnings' => $earnings->values()->all(),
            'deductions' => $deductions->values()->all(),
            'gross_pay' => $grossPay,
            'total_deductions' => $totalDeductions,
            'net_pay' => round($grossPay - $totalDeductions, 2),
        ];
    }

    private function buildAttendanceDeductions(array $snapshot, float $baseSalary, Carbon $date): Collection
    {
        $daysInMonth = max(1, $date->daysInMonth);
        $perDay = $baseSalary / $daysInMonth;
        $deductions = collect();

        $absentDays = (float) ($snapshot['absent_days'] ?? 0);
        $halfDays = (float) ($snapshot['half_days'] ?? 0);
        $latePenaltyDays = (float) ($snapshot['late_penalty_days'] ?? 0);
        $unpaidLeaveDays = (float) ($snapshot['unpaid_leave_days'] ?? 0);

        if ($absentDays > 0) {
            $deductions->push([
                'code' => 'ABSENT',
                'label' => 'Absent Deduction',
                'amount' => round($perDay * $absentDays, 2),
                'type' => 'deduction',
            ]);
        }

        if ($halfDays > 0) {
            $deductions->push([
                'code' => 'HALF_DAY',
                'label' => 'Half Day Deduction',
                'amount' => round($perDay * ($halfDays * 0.5), 2),
                'type' => 'deduction',
            ]);
        }

        if ($latePenaltyDays > 0) {
            $deductions->push([
                'code' => 'LATE',
                'label' => 'Late Penalty',
                'amount' => round($perDay * $latePenaltyDays, 2),
                'type' => 'deduction',
            ]);
        }

        if ($unpaidLeaveDays > 0) {
            $deductions->push([
                'code' => 'LWP',
                'label' => 'Leave Without Pay',
                'amount' => round($perDay * $unpaidLeaveDays, 2),
                'type' => 'deduction',
            ]);
        }

        return $deductions;
    }

    private function monthlySalaryFromProfile($salaryProfile): float
    {
        if (!$salaryProfile) {
            return 0.0;
        }

        $baseSalary = (float) $salaryProfile->base_salary;
        $earnings = $salaryProfile->salaryStructure?->components
            ?->where('is_active', true)
            ->where('component_type', 'earning')
            ->sum(function ($component) use ($baseSalary) {
                return $component->calc_type === 'percent_of_base'
                    ? round($baseSalary * (((float) $component->value) / 100), 2)
                    : round((float) $component->value, 2);
            }) ?? 0;

        return round($baseSalary + (float) $earnings, 2);
    }

    private function latePenaltyDays(?AttendancePolicy $policy, int $lateCount): float
    {
        $threshold = max(1, (int) ($policy?->late_penalty_threshold ?? 3));
        $type = $policy?->late_penalty_type ?? 'none';

        if ($type === 'none' || $lateCount < $threshold) {
            return 0.0;
        }

        $blocks = intdiv($lateCount, $threshold);

        return match ($type) {
            'half_day' => $blocks * 0.5,
            'absent' => (float) $blocks,
            default => 0.0,
        };
    }
}

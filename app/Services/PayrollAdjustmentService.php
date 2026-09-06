<?php

namespace App\Services;

use App\Models\PayrollManualAdjustment;
use App\Models\User;
use Carbon\Carbon;

class PayrollAdjustmentService
{
    public function __construct(protected AttendanceLockService $lockService)
    {
    }

    public function create(array $validated, User $actor): PayrollManualAdjustment
    {
        $date = Carbon::create((int) $validated['year'], (int) $validated['month'], 1);
        $this->lockService->ensureNotFrozen($date);

        return PayrollManualAdjustment::create([
            'user_id' => $validated['user_id'],
            'payroll_deduction_head_id' => $validated['payroll_deduction_head_id'] ?? null,
            'year' => $validated['year'],
            'month' => $validated['month'],
            'label' => $validated['label'],
            'type' => $validated['type'],
            'amount' => $validated['amount'],
            'remarks' => $validated['remarks'] ?? null,
            'created_by' => $actor->id,
        ]);
    }
}

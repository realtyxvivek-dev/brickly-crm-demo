<?php

namespace App\Services;

use App\Models\PayrollFreeze;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AttendanceLockService
{
    public function isFrozenForDate(CarbonInterface $date, ?int $officeLocationId = null): bool
    {
        if (!Schema::hasTable('payroll_freezes')) {
            return false;
        }

        return PayrollFreeze::query()
            ->where('year', $date->year)
            ->where('month', $date->month)
            ->where('status', 'frozen')
            ->where(function ($query) use ($officeLocationId) {
                $query->whereNull('office_location_id');
                if ($officeLocationId) {
                    $query->orWhere('office_location_id', $officeLocationId);
                }
            })
            ->exists();
    }

    public function ensureNotFrozen(CarbonInterface $date, ?int $officeLocationId = null): void
    {
        if ($this->isFrozenForDate($date, $officeLocationId)) {
            throw ValidationException::withMessages([
                'attendance' => 'Attendance month is frozen for payroll. Changes are blocked.',
            ]);
        }
    }
}

<?php

namespace App\Services;

use App\Models\AttendanceOvertime;
use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class OvertimeService
{
    public function __construct(protected AttendancePolicyResolver $policyResolver)
    {
    }

    public function syncForRecord(AttendanceRecord $record): ?AttendanceOvertime
    {
        if (!Schema::hasTable('attendance_overtimes') || !$record->attendance_date) {
            return null;
        }

        $record->loadMissing(['user', 'attendancePolicy']);
        $policy = $record->attendancePolicy ?: $this->policyResolver->resolveForUser($record->user, $record->attendance_date)['policy'];

        if (!$policy?->overtime_enabled) {
            AttendanceOvertime::query()
                ->where('user_id', $record->user_id)
                ->whereDate('attendance_date', $record->attendance_date->toDateString())
                ->where('status', 'pending')
                ->delete();

            return null;
        }

        $thresholdMinutes = (int) ($policy->overtime_after_minutes ?? 480);
        $minimumExtraMinutes = (int) ($policy->overtime_min_minutes ?? 30);
        $workedMinutes = (int) $record->worked_minutes;
        $overtimeMinutes = max(0, $workedMinutes - $thresholdMinutes);

        if ($workedMinutes <= 0 || $overtimeMinutes < $minimumExtraMinutes) {
            AttendanceOvertime::query()
                ->where('user_id', $record->user_id)
                ->whereDate('attendance_date', $record->attendance_date->toDateString())
                ->where('status', 'pending')
                ->delete();

            return null;
        }

        return AttendanceOvertime::updateOrCreate(
            [
                'user_id' => $record->user_id,
                'attendance_date' => $record->attendance_date->toDateString(),
            ],
            [
                'attendance_record_id' => $record->id,
                'worked_minutes' => $workedMinutes,
                'overtime_minutes' => $overtimeMinutes,
                'status' => 'pending',
                'source' => 'auto',
            ]
        );
    }

    public function approve(AttendanceOvertime $overtime, User $actor, ?string $remarks = null): AttendanceOvertime
    {
        if (!$actor->isHrManager() && !$actor->isAdmin()) {
            throw ValidationException::withMessages([
                'overtime' => 'User cannot approve overtime.',
            ]);
        }

        $overtime->update([
            'status' => 'approved',
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'remarks' => $remarks ?: $overtime->remarks,
        ]);

        return $overtime->fresh(['user', 'attendanceRecord']);
    }

    public function reject(AttendanceOvertime $overtime, User $actor, ?string $remarks = null): AttendanceOvertime
    {
        if (!$actor->isHrManager() && !$actor->isAdmin()) {
            throw ValidationException::withMessages([
                'overtime' => 'User cannot reject overtime.',
            ]);
        }

        $overtime->update([
            'status' => 'rejected',
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'remarks' => $remarks ?: $overtime->remarks,
        ]);

        return $overtime->fresh(['user', 'attendanceRecord']);
    }
}

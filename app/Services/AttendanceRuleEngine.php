<?php

namespace App\Services;

use App\Models\AttendanceHoliday;
use App\Models\AttendancePolicy;
use App\Models\AttendanceRegularization;
use App\Models\AttendanceRecord;
use App\Models\AttendanceWeekoff;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Schema;

class AttendanceRuleEngine
{
    public function classify(User $user, ?AttendancePolicy $policy, ?CarbonInterface $firstPunchIn, CarbonInterface $date, ?int $officeLocationId = null): array
    {
        $approvedRegularization = null;
        if (Schema::hasTable('attendance_regularizations')) {
            $approvedRegularization = AttendanceRegularization::query()
                ->where('user_id', $user->id)
                ->whereDate('attendance_date', $date->toDateString())
                ->where('status', 'approved')
                ->latest()
                ->first();
        }

        if ($approvedRegularization && $approvedRegularization->requested_status) {
            return $this->result(
                $approvedRegularization->requested_status,
                $approvedRegularization->requested_status === AttendanceRecord::STATUS_HALF_DAY ? 0.5 : ($approvedRegularization->requested_status === AttendanceRecord::STATUS_ABSENT ? 0.0 : 1.0),
                0,
                'regularization'
            );
        }

        if ($this->isApprovedLeave($user, $date)) {
            $leaveRequest = LeaveRequest::query()
                ->with('leaveType')
                ->where('user_id', $user->id)
                ->where('status', 'approved')
                ->whereDate('from_date', '<=', $date->toDateString())
                ->whereDate('to_date', '>=', $date->toDateString())
                ->latest()
                ->first();

            return $this->result(AttendanceRecord::STATUS_LEAVE, $leaveRequest?->leaveType?->is_paid ? 1.0 : 0.0, 0, 'leave');
        }

        if ($this->isHoliday($date, $officeLocationId)) {
            return $this->result(AttendanceRecord::STATUS_HOLIDAY, 1.0, 0, 'holiday');
        }

        if ($this->isWeekoff($user, $date)) {
            return $this->result(AttendanceRecord::STATUS_WEEK_OFF, 1.0, 0, 'weekoff');
        }

        if (!$firstPunchIn) {
            return $this->result(AttendanceRecord::STATUS_ABSENT, 0, 0, 'auto');
        }

        $lateCutoff = Carbon::parse($date->toDateString() . ' ' . ($policy?->late_after_time ?? '09:00:00'))
            ->addMinutes((int) ($policy?->grace_minutes ?? 0));
        $normalWindowEnd = Carbon::parse($date->toDateString() . ' ' . ($policy?->normal_window_end_time ?? '11:30:00'));
        $halfDayStart = Carbon::parse($date->toDateString() . ' ' . ($policy?->half_day_start_time ?? '13:00:00'));
        $halfDayEnd = Carbon::parse($date->toDateString() . ' ' . ($policy?->half_day_end_time ?? '16:00:00'));

        if ($firstPunchIn->lessThanOrEqualTo($lateCutoff)) {
            return $this->result(AttendanceRecord::STATUS_PRESENT, 1.0, 0, 'auto');
        }

        if ($firstPunchIn->lessThanOrEqualTo($normalWindowEnd)) {
            return $this->result(
                AttendanceRecord::STATUS_LATE,
                1.0,
                max(0, $lateCutoff->diffInMinutes($firstPunchIn, false)),
                'auto'
            );
        }

        if ($firstPunchIn->greaterThan($normalWindowEnd) && $firstPunchIn->lessThanOrEqualTo($halfDayEnd)) {
            return $this->result(AttendanceRecord::STATUS_HALF_DAY, 0.5, 0, 'auto');
        }

        return $this->result(AttendanceRecord::STATUS_ABSENT, 0, 0, 'auto');
    }

    private function isHoliday(CarbonInterface $date, ?int $officeLocationId): bool
    {
        return AttendanceHoliday::query()
            ->whereDate('holiday_date', $date->toDateString())
            ->where(function ($query) use ($officeLocationId) {
                $query->whereNull('office_location_id');
                if ($officeLocationId) {
                    $query->orWhere('office_location_id', $officeLocationId);
                }
            })
            ->exists();
    }

    private function isWeekoff(User $user, CarbonInterface $date): bool
    {
        return AttendanceWeekoff::query()
            ->where('user_id', $user->id)
            ->where('day_of_week', (int) $date->dayOfWeek)
            ->where(function ($query) use ($date) {
                $query->whereNull('effective_from')
                    ->orWhereDate('effective_from', '<=', $date->toDateString());
            })
            ->where(function ($query) use ($date) {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $date->toDateString());
            })
            ->exists();
    }

    private function isApprovedLeave(User $user, CarbonInterface $date): bool
    {
        if (!Schema::hasTable('leave_requests')) {
            return false;
        }

        return LeaveRequest::query()
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereDate('from_date', '<=', $date->toDateString())
            ->whereDate('to_date', '>=', $date->toDateString())
            ->exists();
    }

    private function result(string $status, float $fraction, int $lateMinutes, string $source): array
    {
        return [
            'status' => $status,
            'payable_day_fraction' => $fraction,
            'late_minutes' => $lateMinutes,
            'status_source' => $source,
        ];
    }
}

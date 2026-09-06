<?php

namespace App\Http\Controllers;

use App\Models\PayrollPayslip;
use App\Models\AttendanceRecord;

class PayslipVerificationController extends Controller
{
    public function show(string $token)
    {
        $payslip = PayrollPayslip::with(['user.employeeProfile.department', 'user.employeeProfile.designation'])
            ->where('verification_token', $token)
            ->firstOrFail();
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

        return view('attendance.payslip-verify', [
            'payslip' => $payslip,
            'employee' => $payslip->user,
            'profile' => $payslip->user?->employeeProfile,
            'attendance' => $payslip->snapshot_json['attendance_snapshot'] ?? [],
            'earnings' => collect($payslip->snapshot_json['earnings'] ?? []),
            'deductions' => collect($payslip->snapshot_json['deductions'] ?? []),
            'attendanceDetails' => $attendanceDetails,
        ]);
    }
}

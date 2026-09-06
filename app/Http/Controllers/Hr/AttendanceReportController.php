<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSuspicionLog;
use App\Models\AttendanceMonthlyRollup;
use App\Services\AttendanceAccessService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AttendanceReportController extends Controller
{
    public function __construct(protected AttendanceAccessService $attendanceAccessService)
    {
    }

    public function index(Request $request)
    {
        $date = Carbon::parse($request->input('date', now()->toDateString()));
        $monthDate = Carbon::parse($request->input('month', $date->format('Y-m')));
        $enabledUserIds = $this->attendanceAccessService->enabledProfilesQuery($date)->select('user_id');

        $daily = AttendanceRecord::query()
            ->selectRaw('status, COUNT(*) as total')
            ->whereDate('attendance_date', $date->toDateString())
            ->whereIn('user_id', $enabledUserIds)
            ->groupBy('status')
            ->pluck('total', 'status');

        $monthly = AttendanceRecord::query()
            ->selectRaw('status, COUNT(*) as total')
            ->whereYear('attendance_date', $monthDate->year)
            ->whereMonth('attendance_date', $monthDate->month)
            ->whereIn('user_id', $this->attendanceAccessService->enabledProfilesQuery($monthDate)->select('user_id'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $lateTrend = AttendanceRecord::query()
            ->selectRaw('attendance_date, COUNT(*) as late_total')
            ->whereYear('attendance_date', $monthDate->year)
            ->whereMonth('attendance_date', $monthDate->month)
            ->whereIn('user_id', $this->attendanceAccessService->enabledProfilesQuery($monthDate)->select('user_id'))
            ->where('status', AttendanceRecord::STATUS_LATE)
            ->groupBy('attendance_date')
            ->orderBy('attendance_date')
            ->get();

        $suspiciousQueue = Schema::hasTable('attendance_suspicion_logs')
            ? AttendanceSuspicionLog::with(['user.role', 'attendanceRecord'])
                ->whereIn('user_id', $this->attendanceAccessService->enabledProfilesQuery($monthDate)->select('user_id'))
                ->where('status', 'open')
                ->latest()
                ->limit(20)
                ->get()
            : collect();

        $rollupTotals = Schema::hasTable('attendance_monthly_rollups')
            ? AttendanceMonthlyRollup::query()
                ->where('year', $monthDate->year)
                ->where('month', $monthDate->month)
                ->whereIn('user_id', $this->attendanceAccessService->enabledProfilesQuery($monthDate)->select('user_id'))
                ->selectRaw('SUM(payable_days) as payable_days, SUM(estimated_salary) as estimated_salary, SUM(late_penalty_days) as late_penalty_days, SUM(overtime_minutes) as overtime_minutes')
                ->first()
            : null;

        return view('hr-manager.attendance.reports', [
            'date' => $date,
            'monthDate' => $monthDate,
            'daily' => $daily,
            'monthly' => $monthly,
            'lateTrend' => $lateTrend,
            'suspiciousQueue' => $suspiciousQueue,
            'rollupTotals' => $rollupTotals,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\AttendanceOvertime;
use App\Services\AttendanceAccessService;
use App\Services\OvertimeService;
use Illuminate\Http\Request;

class AttendanceOvertimeController extends Controller
{
    public function __construct(
        protected AttendanceAccessService $attendanceAccessService,
        protected OvertimeService $overtimeService
    )
    {
    }

    public function index(Request $request)
    {
        $pendingOvertimes = AttendanceOvertime::with(['user.role', 'attendanceRecord'])
            ->where('status', 'pending')
            ->whereIn('user_id', $this->attendanceAccessService->enabledProfilesQuery(now())->select('user_id'))
            ->latest('attendance_date')
            ->get();

        return view('hr-manager.attendance.overtimes', compact('pendingOvertimes'));
    }

    public function approve(Request $request, AttendanceOvertime $overtime)
    {
        $this->overtimeService->approve($overtime, $request->user(), $request->input('remarks'));

        return back()->with('success', 'Overtime approved.');
    }

    public function reject(Request $request, AttendanceOvertime $overtime)
    {
        $this->overtimeService->reject($overtime, $request->user(), $request->input('remarks'));

        return back()->with('success', 'Overtime rejected.');
    }
}

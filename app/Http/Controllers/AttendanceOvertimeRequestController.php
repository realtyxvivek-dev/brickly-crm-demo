<?php

namespace App\Http\Controllers;

use App\Models\AttendanceOvertime;
use App\Services\AttendanceAccessService;
use Illuminate\Http\Request;

class AttendanceOvertimeRequestController extends Controller
{
    public function __construct(protected AttendanceAccessService $attendanceAccessService)
    {
    }

    private function blocksOvertimeForManagerRole(Request $request): bool
    {
        $user = $request->user();

        return $user
            && ($user->isSalesManager() || $user->isSeniorManager() || $user->isAssistantSalesManager());
    }

    public function index(Request $request)
    {
        $this->attendanceAccessService->ensureEnabledFor($request->user());

        if ($this->blocksOvertimeForManagerRole($request)) {
            return redirect()
                ->route('attendance.regularizations')
                ->with('info', 'Overtime is not available for this role.');
        }

        $overtimes = AttendanceOvertime::with(['attendanceRecord', 'approver'])
            ->where('user_id', $request->user()->id)
            ->latest('attendance_date')
            ->get();

        return view('attendance.overtimes', ['requests' => $overtimes]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceOvertime;
use App\Services\AttendanceAccessService;
use Illuminate\Http\Request;

class AttendanceOvertimeController extends Controller
{
    public function __construct(protected AttendanceAccessService $attendanceAccessService)
    {
    }

    public function index(Request $request)
    {
        $this->attendanceAccessService->ensureEnabledFor($request->user());

        return response()->json([
            'success' => true,
            'data' => AttendanceOvertime::with(['attendanceRecord', 'approver'])
                ->where('user_id', $request->user()->id)
                ->latest('attendance_date')
                ->get(),
        ]);
    }
}

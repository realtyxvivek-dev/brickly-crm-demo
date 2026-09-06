<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRegularization;
use App\Services\AttendanceAccessService;
use App\Services\RegularizationService;
use Illuminate\Http\Request;

class AttendanceRegularizationController extends Controller
{
    public function __construct(
        protected AttendanceAccessService $attendanceAccessService,
        protected RegularizationService $regularizationService
    )
    {
    }

    public function index(Request $request)
    {
        $this->attendanceAccessService->ensureEnabledFor($request->user());

        return response()->json([
            'success' => true,
            'data' => AttendanceRegularization::with('approvals.actor')
                ->where('user_id', $request->user()->id)
                ->latest()
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->attendanceAccessService->ensureEnabledFor($request->user());

        $validated = $request->validate([
            'attendance_date' => 'required|date',
            'request_type' => 'required|string|in:missed_punch_in,missed_punch_out,wrong_status,manual_present,manual_half_day',
            'requested_in_time' => 'nullable|date_format:H:i',
            'requested_out_time' => 'nullable|date_format:H:i',
            'requested_status' => 'nullable|string|in:present,late,half_day,absent',
            'reason' => 'nullable|string|max:2000',
            'proof' => 'nullable|file|max:8192',
        ]);

        $regularization = $this->regularizationService->createRequest($request->user(), $validated, $request->file('proof'));

        return response()->json([
            'success' => true,
            'data' => $regularization,
            'message' => 'Regularization request submitted.',
        ], 201);
    }
}

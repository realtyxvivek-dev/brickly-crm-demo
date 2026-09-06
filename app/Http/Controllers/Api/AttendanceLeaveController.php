<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\AttendanceAccessService;
use App\Services\LeaveService;
use Illuminate\Http\Request;

class AttendanceLeaveController extends Controller
{
    public function __construct(
        protected AttendanceAccessService $attendanceAccessService,
        protected LeaveService $leaveService
    )
    {
    }

    public function types(Request $request)
    {
        $this->attendanceAccessService->ensureEnabledFor($request->user());

        return response()->json([
            'success' => true,
            'data' => LeaveType::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function balances(Request $request)
    {
        $this->attendanceAccessService->ensureEnabledFor($request->user());

        $this->leaveService->ensureBalancesForUser($request->user(), now()->year);

        return response()->json([
            'success' => true,
            'data' => LeaveBalance::with('leaveType')
                ->where('user_id', $request->user()->id)
                ->where('year', now()->year)
                ->whereHas('leaveType', fn ($query) => $query->where('is_active', true))
                ->get(),
        ]);
    }

    public function index(Request $request)
    {
        $this->attendanceAccessService->ensureEnabledFor($request->user());

        return response()->json([
            'success' => true,
            'data' => LeaveRequest::with(['leaveType', 'approvals.actor'])
                ->where('user_id', $request->user()->id)
                ->latest()
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->attendanceAccessService->ensureEnabledFor($request->user());

        $validated = $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'from_date' => 'required|date',
            'to_date' => 'required|date',
            'duration_mode' => 'required|string|in:full_day,half_day_am,half_day_pm',
            'reason' => 'nullable|string|max:2000',
            'attachment' => 'nullable|file|max:8192',
        ]);

        $leaveRequest = $this->leaveService->createRequest($request->user(), $validated, $request->file('attachment'));

        return response()->json([
            'success' => true,
            'data' => $leaveRequest,
            'message' => 'Leave request submitted.',
        ], 201);
    }
}

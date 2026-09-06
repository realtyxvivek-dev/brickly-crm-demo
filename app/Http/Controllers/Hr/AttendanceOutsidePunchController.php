<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\AttendanceOutsidePunchRequest;
use App\Services\AttendanceOutsidePunchService;
use Illuminate\Http\Request;

class AttendanceOutsidePunchController extends Controller
{
    public function __construct(
        protected AttendanceOutsidePunchService $outsidePunchService
    ) {
    }

    public function index()
    {
        $pendingRequests = AttendanceOutsidePunchRequest::with(['user.role', 'officeLocation', 'approvals.actor'])
            ->where('status', 'pending')
            ->latest('requested_at')
            ->get();

        $recentRequests = AttendanceOutsidePunchRequest::with(['user.role', 'officeLocation', 'approver'])
            ->whereIn('status', ['consumed', 'rejected'])
            ->latest('requested_at')
            ->limit(20)
            ->get();

        return view('hr-manager.attendance.outside-punches', compact('pendingRequests', 'recentRequests'));
    }

    public function approve(Request $request, AttendanceOutsidePunchRequest $outsidePunchRequest)
    {
        $this->outsidePunchService->approveRequest($outsidePunchRequest, $request->user(), $request->input('remarks'));

        return back()->with('success', 'Outside punch request approved.');
    }

    public function reject(Request $request, AttendanceOutsidePunchRequest $outsidePunchRequest)
    {
        $this->outsidePunchService->rejectRequest($outsidePunchRequest, $request->user(), $request->input('remarks'));

        return back()->with('success', 'Outside punch request rejected.');
    }
}

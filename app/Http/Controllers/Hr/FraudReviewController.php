<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\AttendanceFaceReview;
use App\Services\AttendanceAccessService;
use App\Services\FaceFraudReviewService;
use Illuminate\Http\Request;

class FraudReviewController extends Controller
{
    public function __construct(protected AttendanceAccessService $attendanceAccessService)
    {
    }

    public function index()
    {
        $reviews = AttendanceFaceReview::with(['user.role', 'attendanceRecord', 'attendancePhoto', 'reviewer'])
            ->whereIn('user_id', $this->attendanceAccessService->enabledProfilesQuery(now())->select('user_id'))
            ->latest()
            ->get();

        return view('hr-manager.attendance.fraud-reviews', compact('reviews'));
    }

    public function approve(Request $request, AttendanceFaceReview $review, FaceFraudReviewService $service)
    {
        $service->review($review, $request->user(), 'accepted', $request->input('remarks'));

        return back()->with('success', 'Face review accepted.');
    }

    public function reject(Request $request, AttendanceFaceReview $review, FaceFraudReviewService $service)
    {
        $service->review($review, $request->user(), 'rejected', $request->input('remarks'));

        return back()->with('success', 'Face review rejected.');
    }
}

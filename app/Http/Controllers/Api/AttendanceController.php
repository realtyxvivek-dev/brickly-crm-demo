<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\OutsidePunchRequiredException;
use App\Http\Controllers\Controller;
use App\Services\AttendanceAccessService;
use App\Services\AttendancePunchService;
use App\Services\AttendanceSummaryService;
use App\Services\ProductiveDayTrackerService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __construct(
        protected AttendanceAccessService $attendanceAccessService,
        protected AttendanceSummaryService $summaryService,
        protected AttendancePunchService $punchService,
        protected ProductiveDayTrackerService $productiveDayTrackerService
    ) {
    }

    public function today(Request $request)
    {
        $this->attendanceAccessService->ensureEnabledFor($request->user());

        return response()->json([
            'success' => true,
            'data' => $this->summaryService->today($request->user()),
        ]);
    }

    public function punchIn(Request $request)
    {
        $this->attendanceAccessService->ensureEnabledFor($request->user());

        $request->validate([
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'photo' => 'nullable|image|max:8192',
            'photo_capture_mode' => 'nullable|string|in:camera',
            'source' => 'nullable|string|max:50',
            'device_fingerprint' => 'nullable|string|max:1000',
        ]);

        try {
            $record = $this->punchService->punchIn($request->user(), $request);
        } catch (OutsidePunchRequiredException $exception) {
            return response()->json([
                'success' => false,
                'code' => 'outside_punch_required',
                'message' => $exception->getMessage(),
                'data' => $exception->context(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Punch-in recorded successfully.',
            'data' => $record,
        ]);
    }

    public function punchOut(Request $request)
    {
        $this->attendanceAccessService->ensureEnabledFor($request->user());

        $request->validate([
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'source' => 'nullable|string|max:50',
            'device_fingerprint' => 'nullable|string|max:1000',
        ]);

        try {
            $record = $this->punchService->punchOut($request->user(), $request);
        } catch (OutsidePunchRequiredException $exception) {
            return response()->json([
                'success' => false,
                'code' => 'outside_punch_required',
                'message' => $exception->getMessage(),
                'data' => $exception->context(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Punch-out recorded successfully.',
            'data' => $record,
        ]);
    }

    public function history(Request $request)
    {
        $this->attendanceAccessService->ensureEnabledFor($request->user());

        $validated = $request->validate([
            'year' => 'nullable|integer|min:2020|max:2100',
            'month' => 'nullable|integer|min:1|max:12',
        ]);

        $historyDate = isset($validated['year'], $validated['month'])
            ? Carbon::create((int) $validated['year'], (int) $validated['month'], 1)
            : now();
        $user = $request->user();
        $profile = $this->productiveDayTrackerService->profileForUser($user);
        $tracker = $this->productiveDayTrackerService->isSalesProfile($profile)
            ? $this->productiveDayTrackerService->forUser($user, $historyDate->copy()->startOfMonth(), $historyDate->copy()->endOfMonth())
            : collect();
        $rows = collect($this->summaryService->history($user, $historyDate))
            ->map(function (array $row) use ($tracker, $user, $profile) {
                if ($this->productiveDayTrackerService->isSalesProfile($profile)) {
                    $row['productivity'] = $this->productiveDayTrackerService->forCell($tracker, $user->id, $row['attendance_date']);
                }

                return $row;
            })
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function monthSummary(Request $request)
    {
        $this->attendanceAccessService->ensureEnabledFor($request->user());

        return response()->json([
            'success' => true,
            'data' => $this->summaryService->monthSummary($request->user(), now()),
        ]);
    }
}

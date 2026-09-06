<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\UserAttendanceProfile;
use App\Services\AttendanceAccessService;
use App\Services\AttendanceFinalizerService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AttendanceRegisterController extends Controller
{
    public function __construct(
        protected AttendanceAccessService $attendanceAccessService,
        protected AttendanceFinalizerService $finalizerService
    )
    {
    }

    public function index(Request $request)
    {
        $anchorDate = Carbon::parse($request->input('date', now()->toDateString()));
        $period = in_array($request->input('period'), ['today', 'week', 'month'], true)
            ? $request->input('period')
            : 'today';

        [$startDate, $endDate] = match ($period) {
            'week' => [$anchorDate->copy()->startOfWeek(), $anchorDate->copy()->endOfWeek()],
            'month' => [$anchorDate->copy()->startOfMonth(), $anchorDate->copy()->endOfMonth()],
            default => [$anchorDate->copy()->startOfDay(), $anchorDate->copy()->startOfDay()],
        };

        $this->finalizerService->finalizeForDate($anchorDate);
        $enabledUserIds = $this->attendanceAccessService->enabledProfilesQuery($anchorDate)->select('user_id');

        $records = AttendanceRecord::with(['user.role', 'officeLocation'])
            ->whereBetween('attendance_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->whereIn('user_id', $enabledUserIds)
            ->orderByDesc('attendance_date')
            ->orderBy('user_id')
            ->get();

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => $records]);
        }

        return view('hr-manager.attendance.index', [
            'records' => $records,
            'date' => $anchorDate,
            'period' => $period,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    public function problems(Request $request)
    {
        $date = Carbon::parse($request->input('date', now()->toDateString()));
        $this->finalizerService->finalizeForDate($date);
        $enabledProfiles = $this->attendanceAccessService->enabledProfilesQuery($date);

        $records = AttendanceRecord::with(['user.role', 'officeLocation'])
            ->whereDate('attendance_date', $date->toDateString())
            ->whereIn('user_id', $enabledProfiles->select('user_id'))
            ->get();

        $mappedUserIds = $enabledProfiles->pluck('user_id')->all();
        $recordUserIds = $records->pluck('user_id')->all();
        $missingPunchIn = UserAttendanceProfile::with('user.role')
            ->whereIn('id', $this->attendanceAccessService->enabledProfilesQuery($date)->select('id'))
            ->where(function ($query) use ($recordUserIds) {
                $query->whereNotIn('user_id', $recordUserIds);
            })
            ->get();

        if ($date->isToday() && now()->gte(Carbon::parse($date->toDateString() . ' 16:00:00'))) {
            $missingPunchIn = $missingPunchIn->concat(
                UserAttendanceProfile::with('user.role')
                    ->whereIn('id', $this->attendanceAccessService->enabledProfilesQuery($date)->select('id'))
                    ->whereIn('user_id', $records->where('status', 'absent')->whereNull('first_punch_in_at')->pluck('user_id'))
                    ->get()
            )->unique('user_id')->values();
        }

        $payload = [
            'date' => $date->toDateString(),
            'missing_punch_in' => $missingPunchIn,
            'half_day' => $records->where('status', 'half_day')->values(),
            'suspicious' => $records->where('is_suspicious', true)->values(),
            'tracked_users_count' => count($mappedUserIds),
        ];

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => $payload]);
        }

        return view('hr-manager.attendance.problems', $payload);
    }
}

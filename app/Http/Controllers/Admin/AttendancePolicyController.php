<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendancePolicy;
use App\Models\AttendanceWeekoff;
use App\Models\OfficeLocation;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAttendanceProfile;
use App\Services\AttendanceFinalizerService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendancePolicyController extends Controller
{
    public function index(Request $request)
    {
        $policies = AttendancePolicy::with('officeLocation')->latest()->get();
        $offices = OfficeLocation::query()->where('is_active', true)->orderBy('name')->get();
        $roles = Role::query()
            ->withCount(['users' => fn ($query) => $query->where('is_active', true)])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $weekoffSummary = $this->weekoffSummary();
        $editingPolicy = null;

        if ($request->filled('edit')) {
            $editingPolicy = AttendancePolicy::find($request->integer('edit'));
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => $policies, 'editing' => $editingPolicy]);
        }

        return view('admin.attendance.policies', compact('policies', 'offices', 'roles', 'weekoffSummary', 'editingPolicy'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateRequest($request);
        $validated['is_default'] = $request->boolean('is_default');
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['geo_fence_required'] = $request->boolean('geo_fence_required');
        $validated['allow_outside_punch_requests'] = $request->boolean('allow_outside_punch_requests', true);
        $validated['outside_punch_permission_default_enabled'] = $request->boolean('outside_punch_permission_default_enabled');
        $validated['photo_required'] = $request->boolean('photo_required');
        $validated['selfie_required'] = $request->boolean('selfie_required');
        $validated['face_review_required'] = $request->boolean('face_review_required');
        $validated['payroll_block_on_pending_face_review'] = $request->boolean('payroll_block_on_pending_face_review');
        $validated['overtime_enabled'] = $request->boolean('overtime_enabled');

        if ($validated['is_default']) {
            AttendancePolicy::query()->update(['is_default' => false]);
        }

        $policy = AttendancePolicy::create($validated);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => $policy], 201);
        }

        return back()->with('success', 'Attendance policy created.');
    }

    public function update(Request $request, AttendancePolicy $policy)
    {
        $validated = $this->validateRequest($request, $policy->id);
        $validated['is_default'] = $request->boolean('is_default');
        $validated['is_active'] = $request->boolean('is_active');
        $validated['geo_fence_required'] = $request->boolean('geo_fence_required');
        $validated['allow_outside_punch_requests'] = $request->boolean('allow_outside_punch_requests', true);
        $validated['outside_punch_permission_default_enabled'] = $request->boolean('outside_punch_permission_default_enabled');
        $validated['photo_required'] = $request->boolean('photo_required');
        $validated['selfie_required'] = $request->boolean('selfie_required');
        $validated['face_review_required'] = $request->boolean('face_review_required');
        $validated['payroll_block_on_pending_face_review'] = $request->boolean('payroll_block_on_pending_face_review');
        $validated['overtime_enabled'] = $request->boolean('overtime_enabled');

        if ($validated['is_default']) {
            AttendancePolicy::query()->where('id', '!=', $policy->id)->update(['is_default' => false]);
        }

        $policy->update($validated);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => $policy->fresh()]);
        }

        return back()->with('success', 'Attendance policy updated.');
    }

    public function destroy(Request $request, AttendancePolicy $policy)
    {
        $isInUse = UserAttendanceProfile::query()
            ->where('attendance_policy_id', $policy->id)
            ->exists();

        if ($isInUse) {
            $message = 'Attendance policy is currently assigned to a user and cannot be deleted.';

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $message], 422);
            }

            return back()->with('error', $message);
        }

        $policy->delete();

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Attendance policy deleted.');
    }

    public function applyWeekoffs(Request $request)
    {
        $validated = $request->validate([
            'role_slugs' => 'required|array|min:1',
            'role_slugs.*' => 'string|exists:roles,slug',
            'day_of_week' => 'required|integer|min:0|max:6',
            'effective_from' => 'nullable|date',
        ]);

        $effectiveFrom = Carbon::parse($validated['effective_from'] ?? now()->startOfMonth()->toDateString())->startOfDay();
        $users = User::query()
            ->with('role')
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->whereIn('slug', $validated['role_slugs']))
            ->get(['id', 'role_id']);

        if ($users->isEmpty()) {
            return back()->with('error', 'Selected roles me koi active user nahi mila.');
        }

        $userIds = $users->pluck('id');

        DB::transaction(function () use ($userIds, $validated, $effectiveFrom) {
            AttendanceWeekoff::query()
                ->whereIn('user_id', $userIds)
                ->whereNull('effective_to')
                ->delete();

            $now = now();
            $rows = $userIds->map(fn ($userId) => [
                'user_id' => $userId,
                'day_of_week' => (int) $validated['day_of_week'],
                'effective_from' => $effectiveFrom->toDateString(),
                'effective_to' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            AttendanceWeekoff::insert($rows);
        });

        $this->refinalizeWeekoffWindow($users, $effectiveFrom);

        $dayName = $this->weekDays()[(int) $validated['day_of_week']];

        return back()->with('success', $users->count() . " users ke liye {$dayName} week off apply ho gaya aur attendance sheet re-sync ho gayi.");
    }

    private function refinalizeWeekoffWindow($users, Carbon $effectiveFrom): void
    {
        $today = now()->startOfDay();
        if ($effectiveFrom->gt($today)) {
            return;
        }

        $finalizer = app(AttendanceFinalizerService::class);
        $cursor = $effectiveFrom->copy();

        while ($cursor->lte($today)) {
            foreach ($users as $user) {
                $finalizer->finalizeUserForDate($user, $cursor);
            }

            $cursor->addDay();
        }
    }

    private function validateRequest(Request $request, ?int $policyId = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'office_location_id' => 'nullable|exists:office_locations,id',
            'reminder_time' => 'required|date_format:H:i',
            'late_after_time' => 'required|date_format:H:i',
            'grace_minutes' => 'required|integer|min:0|max:180',
            'normal_window_end_time' => 'required|date_format:H:i',
            'half_day_start_time' => 'required|date_format:H:i',
            'half_day_end_time' => 'required|date_format:H:i',
            'geo_fence_required' => 'nullable|boolean',
            'allow_outside_punch_requests' => 'nullable|boolean',
            'outside_punch_permission_default_enabled' => 'nullable|boolean',
            'photo_required' => 'nullable|boolean',
            'selfie_required' => 'nullable|boolean',
            'face_review_required' => 'nullable|boolean',
            'payroll_block_on_pending_face_review' => 'nullable|boolean',
            'compress_max_width' => 'required|integer|min:320|max:4000',
            'compress_max_height' => 'required|integer|min:320|max:4000',
            'compress_quality' => 'required|integer|min:30|max:95',
            'suspicious_geo_threshold_meters' => 'required|integer|min:1|max:10000',
            'duplicate_photo_threshold' => 'required|integer|min:1|max:50',
            'face_compare_provider' => 'nullable|string|max:100',
            'liveness_provider' => 'nullable|string|max:100',
            'approval_mode_leave' => 'required|string|in:hr_only,admin_only,either_first,both_required',
            'approval_mode_regularization' => 'required|string|in:hr_only,admin_only,either_first,both_required',
            'regularization_abuse_threshold' => 'required|integer|min:1|max:20',
            'late_penalty_type' => 'required|string|in:none,half_day,absent',
            'late_penalty_threshold' => 'required|integer|min:1|max:31',
            'overtime_enabled' => 'nullable|boolean',
            'overtime_after_minutes' => 'required|integer|min:60|max:1440',
            'overtime_min_minutes' => 'required|integer|min:1|max:600',
            'is_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);
    }

    private function weekoffSummary()
    {
        return AttendanceWeekoff::query()
            ->select('attendance_weekoffs.day_of_week', 'roles.name as role_name', DB::raw('count(*) as users_count'))
            ->join('users', 'users.id', '=', 'attendance_weekoffs.user_id')
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->whereNull('attendance_weekoffs.effective_to')
            ->groupBy('attendance_weekoffs.day_of_week', 'roles.name')
            ->orderBy('roles.name')
            ->get()
            ->map(function ($row) {
                $row->day_name = $this->weekDays()[(int) $row->day_of_week] ?? 'Unknown';

                return $row;
            });
    }

    private function weekDays(): array
    {
        return [
            0 => 'Sunday',
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
        ];
    }
}

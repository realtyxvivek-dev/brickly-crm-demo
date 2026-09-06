<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendancePolicy;
use App\Models\OfficeLocation;
use App\Models\User;
use App\Models\UserAttendanceProfile;
use Illuminate\Http\Request;

class UserAttendanceMappingController extends Controller
{
    public function index(Request $request)
    {
        $mappings = UserAttendanceProfile::with(['user.role', 'officeLocation', 'attendancePolicy'])->latest()->get();
        $users = User::with('role')->where('is_active', true)->orderBy('name')->get();
        $offices = OfficeLocation::where('is_active', true)->orderBy('name')->get();
        $policies = AttendancePolicy::where('is_active', true)->orderBy('name')->get();
        $mappedUserIds = $mappings->pluck('user_id')->filter()->map(fn ($id) => (int) $id)->all();
        $editingMapping = null;

        if ($request->filled('edit')) {
            $editingMapping = UserAttendanceProfile::with(['user.role', 'officeLocation', 'attendancePolicy'])
                ->find($request->integer('edit'));
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => $mappings, 'editing' => $editingMapping]);
        }

        return view('admin.attendance.user-mappings', compact(
            'mappings',
            'users',
            'offices',
            'policies',
            'mappedUserIds',
            'editingMapping'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'office_location_id' => 'nullable|exists:office_locations,id',
            'attendance_policy_id' => 'nullable|exists:attendance_policies,id',
            'employee_code' => 'nullable|string|max:100',
            'salary_mode' => 'nullable|string|max:100',
            'attendance_enabled' => 'nullable|boolean',
            'allow_outside_punch_requests' => 'nullable',
            'attendance_rollout_stage' => 'nullable|string|max:50',
            'base_salary' => 'nullable|numeric|min:0|max:999999999.99',
            'effective_from' => 'nullable|date',
        ]);

        $validated['attendance_enabled'] = $request->boolean('attendance_enabled');
        $validated['allow_outside_punch_requests'] = $this->parseOutsidePunchPreference($request);
        $validated['attendance_rollout_stage'] = $validated['attendance_rollout_stage'] ?: 'pilot';

        $mapping = UserAttendanceProfile::updateOrCreate(
            ['user_id' => $validated['user_id']],
            $validated
        );

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => $mapping->load(['user.role', 'officeLocation', 'attendancePolicy'])]);
        }

        return back()->with('success', 'User attendance mapping saved.');
    }

    public function bulkStore(Request $request)
    {
        $validated = $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
            'office_location_id' => 'nullable|exists:office_locations,id',
            'attendance_policy_id' => 'nullable|exists:attendance_policies,id',
            'attendance_enabled' => 'nullable|boolean',
            'allow_outside_punch_requests' => 'nullable',
            'attendance_rollout_stage' => 'nullable|string|max:50',
            'effective_from' => 'nullable|date',
            'overwrite_existing' => 'nullable|boolean',
        ]);

        $attendanceEnabled = $request->boolean('attendance_enabled');
        $allowOutsidePunchRequests = $this->parseOutsidePunchPreference($request);
        $rolloutStage = $validated['attendance_rollout_stage'] ?: ($attendanceEnabled ? 'pilot' : 'disabled');
        $overwriteExisting = $request->boolean('overwrite_existing', true);

        $selectedUserIds = collect($validated['user_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $existingMappings = UserAttendanceProfile::query()
            ->whereIn('user_id', $selectedUserIds)
            ->get()
            ->keyBy('user_id');

        $createdCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;

        foreach ($selectedUserIds as $userId) {
            $mapping = $existingMappings->get($userId);

            if ($mapping && !$overwriteExisting) {
                $skippedCount++;
                continue;
            }

            $payload = [
                'office_location_id' => $validated['office_location_id'] ?: null,
                'attendance_policy_id' => $validated['attendance_policy_id'] ?: null,
                'attendance_enabled' => $attendanceEnabled,
                'allow_outside_punch_requests' => $allowOutsidePunchRequests,
                'attendance_rollout_stage' => $rolloutStage,
                'effective_from' => $validated['effective_from'] ?: null,
            ];

            if ($mapping) {
                $mapping->update($payload);
                $updatedCount++;
                continue;
            }

            UserAttendanceProfile::create([
                'user_id' => $userId,
                ...$payload,
            ]);
            $createdCount++;
        }

        $parts = [];
        if ($createdCount > 0) {
            $parts[] = "{$createdCount} new mapping saved";
        }
        if ($updatedCount > 0) {
            $parts[] = "{$updatedCount} existing mapping updated";
        }
        if ($skippedCount > 0) {
            $parts[] = "{$skippedCount} existing mapping skipped";
        }

        return back()->with('success', $parts !== [] ? implode('. ', $parts) . '.' : 'No mapping updated.');
    }

    public function update(Request $request, UserAttendanceProfile $mapping)
    {
        $validated = $request->validate([
            'office_location_id' => 'nullable|exists:office_locations,id',
            'attendance_policy_id' => 'nullable|exists:attendance_policies,id',
            'employee_code' => 'nullable|string|max:100',
            'salary_mode' => 'nullable|string|max:100',
            'attendance_enabled' => 'nullable|boolean',
            'allow_outside_punch_requests' => 'nullable',
            'attendance_rollout_stage' => 'nullable|string|max:50',
            'base_salary' => 'nullable|numeric|min:0|max:999999999.99',
            'effective_from' => 'nullable|date',
        ]);

        $validated['attendance_enabled'] = $request->boolean('attendance_enabled');
        $validated['allow_outside_punch_requests'] = $this->parseOutsidePunchPreference($request);
        $validated['attendance_rollout_stage'] = $validated['attendance_rollout_stage'] ?: 'pilot';

        $mapping->update($validated);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => $mapping->fresh(['user.role', 'officeLocation', 'attendancePolicy'])]);
        }

        return back()->with('success', 'User attendance mapping updated.');
    }

    public function destroy(Request $request, UserAttendanceProfile $mapping)
    {
        $mapping->delete();

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'User attendance mapping deleted.');
    }

    private function parseOutsidePunchPreference(Request $request): ?bool
    {
        if (!$request->has('allow_outside_punch_requests')) {
            return null;
        }

        $value = $request->input('allow_outside_punch_requests');
        if ($value === null || $value === '' || $value === 'inherit') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }
}

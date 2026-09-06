<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceOutsidePunchPermission;
use App\Models\AttendancePolicy;
use App\Models\User;
use Illuminate\Http\Request;

class AttendanceOutsidePunchPermissionController extends Controller
{
    public function index(Request $request)
    {
        $permissions = AttendanceOutsidePunchPermission::with(['user.role', 'attendancePolicy', 'creator'])
            ->latest()
            ->get();
        $users = User::with('role')->where('is_active', true)->orderBy('name')->get();
        $policies = AttendancePolicy::where('is_active', true)->orderBy('name')->get();
        $editingPermission = $request->filled('edit')
            ? AttendanceOutsidePunchPermission::with(['user.role', 'attendancePolicy'])->find($request->integer('edit'))
            : null;

        return view('admin.attendance.outside-punch-permissions', compact('permissions', 'users', 'policies', 'editingPermission'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateRequest($request);
        $validated['allow_punch_in'] = $request->boolean('allow_punch_in');
        $validated['allow_punch_out'] = $request->boolean('allow_punch_out');
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['created_by'] = $request->user()?->id;

        AttendanceOutsidePunchPermission::create($validated);

        return back()->with('success', 'Outside punch allowance saved.');
    }

    public function update(Request $request, AttendanceOutsidePunchPermission $permission)
    {
        $validated = $this->validateRequest($request);
        $validated['allow_punch_in'] = $request->boolean('allow_punch_in');
        $validated['allow_punch_out'] = $request->boolean('allow_punch_out');
        $validated['is_active'] = $request->boolean('is_active');

        $permission->update($validated);

        return back()->with('success', 'Outside punch allowance updated.');
    }

    public function destroy(AttendanceOutsidePunchPermission $permission)
    {
        $permission->delete();

        return back()->with('success', 'Outside punch allowance deleted.');
    }

    private function validateRequest(Request $request): array
    {
        return $request->validate([
            'user_id' => 'nullable|exists:users,id|required_without:attendance_policy_id',
            'attendance_policy_id' => 'nullable|exists:attendance_policies,id|required_without:user_id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'allow_punch_in' => 'nullable|boolean',
            'allow_punch_out' => 'nullable|boolean',
            'reason' => 'nullable|string|max:2000',
            'is_active' => 'nullable|boolean',
        ]);
    }
}

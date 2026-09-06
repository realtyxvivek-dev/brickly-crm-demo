<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class LeaveTypeController extends Controller
{
    public function index(Request $request)
    {
        $leaveTypes = LeaveType::latest()->get();
        $editingLeaveType = null;

        if ($request->filled('edit')) {
            $editingLeaveType = LeaveType::find($request->integer('edit'));
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => $leaveTypes,
                'editing' => $editingLeaveType,
            ]);
        }

        return view('admin.attendance.leave-types', compact('leaveTypes', 'editingLeaveType'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:leave_types,code',
            'is_paid' => 'nullable|boolean',
            'allow_half_day' => 'nullable|boolean',
            'annual_quota' => 'required|numeric|min:0|max:365',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['code'] = $validated['code'] ?: Str::upper(Str::slug($validated['name'], '_'));
        $validated['is_paid'] = $request->boolean('is_paid', true);
        $validated['allow_half_day'] = $request->boolean('allow_half_day');
        $validated['is_active'] = $request->boolean('is_active', true);

        $leaveType = LeaveType::create($validated);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => $leaveType], 201);
        }

        return back()->with('success', 'Leave type created.');
    }

    public function update(Request $request, LeaveType $leaveType)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('leave_types', 'code')->ignore($leaveType->id),
            ],
            'is_paid' => 'nullable|boolean',
            'allow_half_day' => 'nullable|boolean',
            'annual_quota' => 'required|numeric|min:0|max:365',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['code'] = $validated['code'] ?: Str::upper(Str::slug($validated['name'], '_'));
        $validated['is_paid'] = $request->boolean('is_paid', true);
        $validated['allow_half_day'] = $request->boolean('allow_half_day');
        $validated['is_active'] = $request->boolean('is_active', true);

        $leaveType->update($validated);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => $leaveType->fresh()]);
        }

        return back()->with('success', 'Leave type updated.');
    }

    public function destroy(Request $request, LeaveType $leaveType)
    {
        if ($leaveType->balances()->exists() || $leaveType->requests()->exists()) {
            $message = 'Leave type is already in use and cannot be deleted.';

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $message], 422);
            }

            return back()->with('error', $message);
        }

        $leaveType->delete();

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Leave type deleted.');
    }
}

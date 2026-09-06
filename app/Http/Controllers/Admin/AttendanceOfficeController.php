<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OfficeLocation;
use App\Models\UserAttendanceProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AttendanceOfficeController extends Controller
{
    public function index(Request $request)
    {
        $offices = OfficeLocation::query()->latest()->get();
        $editingOffice = null;

        if ($request->filled('edit')) {
            $editingOffice = OfficeLocation::find($request->integer('edit'));
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => $offices, 'editing' => $editingOffice]);
        }

        return view('admin.attendance.offices', compact('offices', 'editingOffice'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:office_locations,code',
            'address' => 'nullable|string|max:500',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'radius_meters' => 'required|integer|min:10|max:100000',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['code'] = $validated['code'] ?: Str::upper(Str::slug($validated['name'], '_'));
        $validated['is_active'] = $request->boolean('is_active', true);

        $office = OfficeLocation::create($validated);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => $office], 201);
        }

        return back()->with('success', 'Office location created.');
    }

    public function update(Request $request, OfficeLocation $office)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:office_locations,code,' . $office->id,
            'address' => 'nullable|string|max:500',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'radius_meters' => 'required|integer|min:10|max:100000',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $office->update($validated);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => $office->fresh()]);
        }

        return back()->with('success', 'Office location updated.');
    }

    public function destroy(Request $request, OfficeLocation $office)
    {
        $isInUse = UserAttendanceProfile::query()
            ->where('office_location_id', $office->id)
            ->exists();

        if ($isInUse) {
            $message = 'Office location is currently assigned to a user and cannot be deleted.';

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $message], 422);
            }

            return back()->with('error', $message);
        }

        $office->delete();

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Office location deleted.');
    }
}

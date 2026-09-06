<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\AttendancePolicy;
use Illuminate\Http\Request;

class FraudSettingsController extends Controller
{
    public function index()
    {
        $policies = AttendancePolicy::with('officeLocation')->latest()->get();

        return view('admin.hr.fraud-settings', compact('policies'));
    }

    public function update(Request $request, AttendancePolicy $policy)
    {
        $validated = $request->validate([
            'duplicate_photo_threshold' => 'required|integer|min:1|max:50',
            'face_compare_provider' => 'nullable|string|max:100',
            'liveness_provider' => 'nullable|string|max:100',
        ]);

        $policy->update([
            'selfie_required' => $request->boolean('selfie_required'),
            'face_review_required' => $request->boolean('face_review_required'),
            'payroll_block_on_pending_face_review' => $request->boolean('payroll_block_on_pending_face_review'),
            'duplicate_photo_threshold' => $validated['duplicate_photo_threshold'],
            'face_compare_provider' => $validated['face_compare_provider'] ?? null,
            'liveness_provider' => $validated['liveness_provider'] ?? null,
        ]);

        return back()->with('success', 'Fraud settings updated.');
    }
}

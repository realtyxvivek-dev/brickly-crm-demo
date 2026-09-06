<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSuspicionLog;
use Illuminate\Http\Request;

class AttendanceSuspicionReviewController extends Controller
{
    public function update(Request $request, AttendanceSuspicionLog $log)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:reviewed,ignored,action_taken',
        ]);

        $log->forceFill([
            'status' => $validated['status'],
            'reviewed_by' => optional($request->user())->id,
            'reviewed_at' => now(),
        ])->save();

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => $log->fresh()]);
        }

        return back()->with('success', 'Suspicious case updated.');
    }
}

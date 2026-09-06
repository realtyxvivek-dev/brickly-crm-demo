<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendancePolicy;
use App\Models\User;
use App\Services\AttendancePolicyResolver;
use App\Services\AttendanceRuleEngine;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AttendancePolicySimulatorController extends Controller
{
    public function __construct(
        protected AttendancePolicyResolver $policyResolver,
        protected AttendanceRuleEngine $ruleEngine
    ) {
    }

    public function index(Request $request)
    {
        $users = User::with('role')->where('is_active', true)->orderBy('name')->limit(100)->get();
        $policies = AttendancePolicy::where('is_active', true)->orderBy('name')->get();
        $result = null;

        if ($request->filled(['user_id', 'date'])) {
            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
                'attendance_policy_id' => 'nullable|exists:attendance_policies,id',
                'date' => 'required|date',
                'punch_in_time' => 'nullable|date_format:H:i',
            ]);

            $user = User::findOrFail($validated['user_id']);
            $date = Carbon::parse($validated['date']);
            $resolved = $this->policyResolver->resolveForUser($user, $date);
            $policy = !empty($validated['attendance_policy_id'])
                ? AttendancePolicy::find($validated['attendance_policy_id'])
                : $resolved['policy'];

            $firstPunchIn = !empty($validated['punch_in_time'])
                ? Carbon::parse($date->toDateString() . ' ' . $validated['punch_in_time'])
                : null;

            $classification = $this->ruleEngine->classify($user, $policy, $firstPunchIn, $date, $resolved['office']?->id);
            $result = [
                'user' => $user,
                'policy' => $policy,
                'date' => $date->toDateString(),
                'punch_in_time' => $firstPunchIn?->format('H:i'),
                'classification' => $classification,
            ];
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        }

        return view('admin.attendance.simulator', compact('users', 'policies', 'result'));
    }
}

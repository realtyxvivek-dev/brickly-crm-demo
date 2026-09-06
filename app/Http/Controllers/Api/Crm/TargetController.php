<?php

namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Controller;
use App\Models\Target;
use Illuminate\Http\Request;
use Carbon\Carbon;

class TargetController extends Controller
{
    public function index(Request $request)
    {
        $query = Target::with('user.role');

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('target_month')) {
            $month = Carbon::parse($request->target_month)->startOfMonth();
            $query->where('target_month', $month->format('Y-m-d'));
        }

        $targets = $query->latest('target_month')->get();

        return response()->json($targets);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'target_month' => 'required|date',
            'target_visits' => 'required|integer|min:0',
            'target_meetings' => 'required|integer|min:0',
            'target_closers' => 'required|integer|min:0',
        ]);

        // Ensure target_month is first day of month
        $targetMonth = Carbon::parse($validated['target_month'])->startOfMonth();

        // Check if target already exists for this user and month
        $existing = Target::where('user_id', $validated['user_id'])
            ->where('target_month', $targetMonth->format('Y-m-d'))
            ->first();

        if ($existing) {
            return response()->json(['message' => 'Target already exists for this user and month'], 400);
        }

        $target = Target::create([
            'user_id' => $validated['user_id'],
            'target_month' => $targetMonth->format('Y-m-d'),
            'target_visits' => $validated['target_visits'],
            'target_meetings' => $validated['target_meetings'],
            'target_closers' => $validated['target_closers'],
        ]);

        return response()->json($target->load('user.role'), 201);
    }

    public function update(Request $request, $id)
    {
        $target = Target::findOrFail($id);

        $validated = $request->validate([
            'target_visits' => 'sometimes|integer|min:0',
            'target_meetings' => 'sometimes|integer|min:0',
            'target_closers' => 'sometimes|integer|min:0',
        ]);

        $target->update($validated);

        return response()->json($target->load('user.role'));
    }
}

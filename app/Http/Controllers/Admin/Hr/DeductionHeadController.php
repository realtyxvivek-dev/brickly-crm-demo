<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\PayrollDeductionHead;
use Illuminate\Http\Request;

class DeductionHeadController extends Controller
{
    public function index(Request $request)
    {
        $heads = PayrollDeductionHead::orderBy('name')->get();

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => $heads]);
        }

        return view('admin.hr.deduction-heads', compact('heads'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:100|unique:payroll_deduction_heads,code',
            'type' => 'required|string|in:earning,deduction',
            'is_active' => 'nullable|boolean',
        ]);

        $head = PayrollDeductionHead::create($validated);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => $head], 201);
        }

        return back()->with('success', 'Deduction head saved.');
    }
}

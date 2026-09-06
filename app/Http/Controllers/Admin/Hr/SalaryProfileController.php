<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\SalaryStructure;
use App\Models\User;
use App\Models\UserSalaryProfile;
use Illuminate\Http\Request;

class SalaryProfileController extends Controller
{
    public function index(Request $request)
    {
        $profiles = UserSalaryProfile::with(['user.role', 'salaryStructure.components'])->latest()->get();
        $users = User::with('role')->where('is_active', true)->orderBy('name')->get();
        $structures = SalaryStructure::where('is_active', true)->orderBy('name')->get();

        $profiles->each(function (UserSalaryProfile $profile) {
            $baseSalary = (float) $profile->base_salary;
            $componentsTotal = collect(optional($profile->salaryStructure)->components)
                ->filter(fn ($component) => $component->is_active)
                ->sum(function ($component) use ($baseSalary) {
                    return $component->calc_type === 'percent_of_base'
                        ? round($baseSalary * (((float) $component->value) / 100), 2)
                        : round((float) $component->value, 2);
                });

            $profile->setAttribute('total_salary', round($baseSalary + $componentsTotal, 2));
        });

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => $profiles]);
        }

        return view('admin.hr.salary-profiles', compact('profiles', 'users', 'structures'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'salary_structure_id' => 'nullable|exists:salary_structures,id',
            'base_salary' => 'required|numeric|min:0|max:999999999.99',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
        ]);

        $profile = UserSalaryProfile::updateOrCreate(
            [
                'user_id' => $validated['user_id'],
                'effective_from' => $validated['effective_from'] ?? null,
            ],
            $validated
        );

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => $profile->fresh(['user.role', 'salaryStructure'])]);
        }

        return back()->with('success', 'Salary profile saved.');
    }
}

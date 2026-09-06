<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\SalaryStructure;
use App\Models\User;
use App\Models\UserSalaryProfile;
use App\Services\SalaryStructureService;
use Illuminate\Http\Request;

class SalaryStructureController extends Controller
{
    public function index(Request $request)
    {
        $structures = SalaryStructure::with('components')
            ->where('name', 'not like', '__employee__:%')
            ->latest()
            ->get();
        $users = User::with('role')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $latestProfiles = UserSalaryProfile::with(['user.role', 'salaryStructure.components'])
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get()
            ->unique('user_id')
            ->values();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'structures' => $structures,
                    'users' => $users,
                    'latest_profiles' => $latestProfiles,
                ],
            ]);
        }

        return view('admin.hr.salary-structures', compact('structures', 'users', 'latestProfiles'));
    }

    public function store(Request $request, SalaryStructureService $service)
    {
        $validated = $this->validateRequest($request);
        $service->saveStructure($validated);

        return back()->with('success', 'Salary structure saved.');
    }

    public function update(Request $request, SalaryStructure $salaryStructure, SalaryStructureService $service)
    {
        $validated = $this->validateRequest($request);
        $service->saveStructure($validated, $salaryStructure);

        return back()->with('success', 'Salary structure updated.');
    }

    public function storeEmployeeBreakup(Request $request, SalaryStructureService $service)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'total_salary' => 'nullable|numeric|min:0|max:999999999.99',
            'basic_mode' => 'required|string|in:auto,manual',
            'manual_basic_salary' => 'nullable|numeric|min:0|max:999999999.99',
            'components' => 'nullable|array',
            'components.*.component_type' => 'required|string|in:earning,deduction',
            'components.*.code' => 'required|string|max:100',
            'components.*.label' => 'required|string|max:255',
            'components.*.calc_type' => 'required|string|in:fixed,percent_of_base',
            'components.*.value' => 'required|numeric|min:0|max:999999999.99',
            'components.*.display_order' => 'nullable|integer|min:0|max:999',
            'components.*.is_active' => 'nullable|boolean',
        ]);

        $user = User::query()->findOrFail($validated['user_id']);
        $components = collect($validated['components'] ?? [])
            ->map(function (array $component) {
                $component['value'] = $this->normalizeMoney($component['value'] ?? 0);

                return $component;
            })
            ->values()
            ->all();
        $fixedTotal = collect($components)
            ->filter(fn ($component) => ($component['calc_type'] ?? null) === 'fixed')
            ->sum(fn ($component) => $this->normalizeMoney($component['value'] ?? 0));

        $basicMode = $validated['basic_mode'];
        $totalSalary = $this->normalizeMoney($validated['total_salary'] ?? 0);
        $baseSalary = $basicMode === 'manual'
            ? $this->normalizeMoney($validated['manual_basic_salary'] ?? 0)
            : $this->normalizeMoney($totalSalary - $fixedTotal);

        if ($basicMode === 'manual' && !array_key_exists('manual_basic_salary', $validated)) {
            return back()->withErrors(['manual_basic_salary' => 'Manual basic salary is required.'])->withInput();
        }

        if ($basicMode === 'auto' && $baseSalary < 0) {
            return back()->withErrors(['total_salary' => 'Total salary must be greater than entered fixed components.'])->withInput();
        }

        $effectiveFrom = $validated['effective_from'] ?? now()->toDateString();
        $existingProfile = UserSalaryProfile::query()
            ->with('salaryStructure')
            ->where('user_id', $user->id)
            ->whereDate('effective_from', $effectiveFrom)
            ->first();

        $structureData = [
            'name' => '__employee__:' . $user->id . ':' . $user->name,
            'is_active' => true,
            'components' => $components,
        ];

        $existingStructure = $existingProfile?->salaryStructure
            && str_starts_with($existingProfile->salaryStructure->name, '__employee__:')
            ? $existingProfile->salaryStructure
            : null;

        $structure = $service->saveStructure($structureData, $existingStructure);

        UserSalaryProfile::updateOrCreate(
            [
                'user_id' => $user->id,
                'effective_from' => $effectiveFrom,
            ],
            [
                'salary_structure_id' => $structure->id,
                'base_salary' => $baseSalary,
                'effective_to' => $validated['effective_to'] ?? null,
            ]
        );

        return back()->with('success', 'Employee breakup saved.');
    }

    private function normalizeMoney($value): float
    {
        $amount = round((float) $value, 2);
        $nearestRupee = round($amount);

        if (abs($amount - $nearestRupee) <= 0.05) {
            return (float) $nearestRupee;
        }

        return $amount;
    }

    private function validateRequest(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'is_active' => 'nullable|boolean',
            'components' => 'nullable|array',
            'components.*.component_type' => 'required|string|in:earning,deduction',
            'components.*.code' => 'required|string|max:100',
            'components.*.label' => 'required|string|max:255',
            'components.*.calc_type' => 'required|string|in:fixed,percent_of_base',
            'components.*.value' => 'required|numeric|min:0|max:999999999.99',
            'components.*.display_order' => 'nullable|integer|min:0|max:999',
            'components.*.is_active' => 'nullable|boolean',
        ]);
    }
}

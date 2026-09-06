<?php

namespace App\Services;

use App\Models\EmployeeProfile;
use App\Models\EmployeeSalaryRevision;
use App\Models\User;
use App\Models\UserSalaryProfile;

class EmployeeSalaryRevisionService
{
    public function __construct(protected EmployeeMasterService $employeeMasterService)
    {
    }

    public function store(EmployeeProfile $profile, array $validated, User $actor): EmployeeSalaryRevision
    {
        $user = $profile->user;
        $currentProfile = $user->salaryProfile;
        $previousBase = (float) ($currentProfile?->base_salary ?? 0);
        $previousTotal = $this->resolveTotalSalary($currentProfile);

        $salaryProfile = UserSalaryProfile::updateOrCreate(
            [
                'user_id' => $user->id,
                'effective_from' => $validated['effective_from'],
            ],
            [
                'salary_structure_id' => $validated['salary_structure_id'] ?: null,
                'base_salary' => round((float) $validated['new_base_salary'], 2),
            ]
        );

        $salaryProfile->load('salaryStructure.components');
        $newTotal = $this->resolveTotalSalary($salaryProfile);

        $revision = EmployeeSalaryRevision::query()->create([
            'employee_profile_id' => $profile->id,
            'user_id' => $user->id,
            'user_salary_profile_id' => $salaryProfile->id,
            'salary_structure_id' => $salaryProfile->salary_structure_id,
            'previous_base_salary' => round($previousBase, 2),
            'new_base_salary' => round((float) $validated['new_base_salary'], 2),
            'previous_total_salary' => round($previousTotal, 2),
            'new_total_salary' => round($newTotal, 2),
            'effective_from' => $validated['effective_from'],
            'reason' => $validated['reason'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'changed_by' => $actor->id,
        ]);

        $this->employeeMasterService->recordTimeline(
            $profile,
            $actor,
            'salary_revised',
            'Salary revised',
            'Base salary updated from Rs ' . number_format($previousBase, 2) . ' to Rs ' . number_format((float) $validated['new_base_salary'], 2),
            [
                'previous_total' => round($previousTotal, 2),
                'new_total' => round($newTotal, 2),
                'effective_from' => $validated['effective_from'],
                'reason' => $validated['reason'] ?? null,
            ]
        );

        return $revision->fresh(['salaryStructure', 'changedBy']);
    }

    public function resolveTotalSalary(?UserSalaryProfile $profile): float
    {
        if (!$profile) {
            return 0;
        }

        $profile->loadMissing('salaryStructure.components');

        $base = (float) $profile->base_salary;
        $componentsTotal = collect(optional($profile->salaryStructure)->components)
            ->filter(fn ($component) => $component->is_active)
            ->sum(function ($component) use ($base) {
                return $component->calc_type === 'percent_of_base'
                    ? round($base * (((float) $component->value) / 100), 2)
                    : round((float) $component->value, 2);
            });

        return round($base + $componentsTotal, 2);
    }
}

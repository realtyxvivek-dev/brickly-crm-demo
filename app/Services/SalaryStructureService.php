<?php

namespace App\Services;

use App\Models\SalaryStructure;
use App\Models\User;
use App\Models\UserSalaryProfile;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class SalaryStructureService
{
    public function saveStructure(array $validated, ?SalaryStructure $structure = null): SalaryStructure
    {
        return DB::transaction(function () use ($validated, $structure) {
            $components = $validated['components'] ?? [];
            unset($validated['components']);

            $structure ??= new SalaryStructure();
            $structure->fill($validated);
            $structure->save();

            $structure->components()->delete();
            foreach ($components as $index => $component) {
                $structure->components()->create([
                    'component_type' => $component['component_type'],
                    'code' => $component['code'],
                    'label' => $component['label'],
                    'calc_type' => $component['calc_type'],
                    'value' => $component['value'],
                    'display_order' => $component['display_order'] ?? $index,
                    'is_active' => $component['is_active'] ?? true,
                ]);
            }

            return $structure->fresh('components');
        });
    }

    public function resolveProfileForUser(User $user, CarbonInterface $date): ?UserSalaryProfile
    {
        return UserSalaryProfile::query()
            ->with('salaryStructure.components')
            ->where('user_id', $user->id)
            ->where(function ($query) use ($date) {
                $query->whereNull('effective_from')
                    ->orWhereDate('effective_from', '<=', $date->toDateString());
            })
            ->where(function ($query) use ($date) {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $date->toDateString());
            })
            ->orderByDesc('effective_from')
            ->first();
    }
}

<?php

namespace App\Services;

use App\Models\EmployeeAsset;
use App\Models\EmployeeExitWorkflow;
use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class EmployeeExitWorkflowService
{
    public function __construct(protected EmployeeMasterService $employeeMasterService)
    {
    }

    public function update(EmployeeProfile $profile, array $validated, User $actor): EmployeeExitWorkflow
    {
        $workflow = $profile->exitWorkflow ?: new EmployeeExitWorkflow(['employee_profile_id' => $profile->id]);
        $issuedAssets = $profile->assets()->where('status', EmployeeAsset::STATUS_ISSUED)->count();
        $targetStatus = $validated['status'];

        $assetClearanceCompletedAt = !empty($validated['asset_clearance_completed'])
            ? now()
            : null;

        if ($issuedAssets > 0 && $targetStatus === EmployeeExitWorkflow::STATUS_CLOSED && !$assetClearanceCompletedAt) {
            throw ValidationException::withMessages([
                'asset_clearance_completed' => 'Asset clearance is required before closing an exit case.',
            ]);
        }

        $workflow->fill([
            'status' => $targetStatus,
            'notice_start_date' => $validated['notice_start_date'] ?? null,
            'resignation_date' => $validated['resignation_date'] ?? null,
            'last_working_date' => $validated['last_working_date'] ?? null,
            'exit_reason' => $validated['exit_reason'] ?? null,
            'hr_clearance_completed_at' => !empty($validated['hr_clearance_completed']) ? now() : null,
            'finance_clearance_completed_at' => !empty($validated['finance_clearance_completed']) ? now() : null,
            'asset_clearance_completed_at' => $assetClearanceCompletedAt,
            'login_disabled_at' => !empty($validated['disable_login_now']) ? now() : $workflow->login_disabled_at,
            'closed_at' => $targetStatus === EmployeeExitWorkflow::STATUS_CLOSED ? now() : null,
            'notes' => $validated['notes'] ?? null,
        ]);
        $workflow->save();

        $employmentStatus = match ($targetStatus) {
            EmployeeExitWorkflow::STATUS_ON_NOTICE => EmployeeProfile::STATUS_ON_NOTICE,
            EmployeeExitWorkflow::STATUS_RESIGNED => EmployeeProfile::STATUS_RESIGNED,
            EmployeeExitWorkflow::STATUS_TERMINATED => EmployeeProfile::STATUS_TERMINATED,
            EmployeeExitWorkflow::STATUS_CLOSED => EmployeeProfile::STATUS_RESIGNED,
            default => $profile->employment_status,
        };

        $profile->forceFill([
            'employment_status' => $employmentStatus,
            'employment_status_changed_at' => now(),
        ])->save();

        if (!empty($validated['disable_login_now'])) {
            $profile->user?->forceFill(['is_active' => false])->save();
        }

        $this->employeeMasterService->recordTimeline(
            $profile,
            $actor,
            'exit_status_changed',
            'Exit workflow updated',
            'Exit status set to ' . str_replace('_', ' ', $targetStatus),
            [
                'status' => $targetStatus,
                'issued_assets' => $issuedAssets,
                'last_working_date' => optional($workflow->last_working_date)?->format('Y-m-d'),
            ]
        );

        return $workflow->fresh();
    }
}

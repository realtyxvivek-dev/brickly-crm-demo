<?php

namespace App\Services;

use App\Models\AttendanceApproval;
use App\Models\AttendancePolicy;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class AttendanceApprovalService
{
    public const STEP_HR = 'hr';
    public const STEP_ADMIN = 'admin';

    public function ensureFlow(Model $approvable, string $mode): void
    {
        $steps = $this->stepsForMode($mode);

        foreach ($steps as $step) {
            AttendanceApproval::firstOrCreate([
                'approvable_type' => $approvable::class,
                'approvable_id' => $approvable->getKey(),
                'step_type' => $step,
            ]);
        }
    }

    public function approve(Model $approvable, User $actor, string $mode, ?string $remarks = null): string
    {
        $step = $this->stepForActor($actor);
        if ($actor->isAdmin() && $mode === 'hr_only') {
            $step = self::STEP_HR;
        }
        if ($step === null) {
            throw new InvalidArgumentException('User cannot approve attendance workflow.');
        }

        $allowedSteps = $this->stepsForMode($mode);
        if (!in_array($step, $allowedSteps, true)) {
            throw new InvalidArgumentException('This approver is not configured for the current approval flow.');
        }

        $approval = AttendanceApproval::where([
            'approvable_type' => $approvable::class,
            'approvable_id' => $approvable->getKey(),
            'step_type' => $step,
        ])->first();

        if (!$approval) {
            $approval = AttendanceApproval::create([
                'approvable_type' => $approvable::class,
                'approvable_id' => $approvable->getKey(),
                'step_type' => $step,
            ]);
        }

        $approval->update([
            'actor_user_id' => $actor->id,
            'action' => 'approved',
            'remarks' => $remarks,
            'acted_at' => now(),
        ]);

        return $this->finalDecision($approvable, $mode);
    }

    public function reject(Model $approvable, User $actor, string $mode, ?string $remarks = null): string
    {
        $step = $this->stepForActor($actor);
        if ($actor->isAdmin() && $mode === 'hr_only') {
            $step = self::STEP_HR;
        }
        if ($step === null) {
            throw new InvalidArgumentException('User cannot reject attendance workflow.');
        }

        $allowedSteps = $this->stepsForMode($mode);
        if (!in_array($step, $allowedSteps, true)) {
            throw new InvalidArgumentException('This approver is not configured for the current approval flow.');
        }

        $approval = AttendanceApproval::firstOrCreate([
            'approvable_type' => $approvable::class,
            'approvable_id' => $approvable->getKey(),
            'step_type' => $step,
        ]);

        $approval->update([
            'actor_user_id' => $actor->id,
            'action' => 'rejected',
            'remarks' => $remarks,
            'acted_at' => now(),
        ]);

        return 'rejected';
    }

    public function finalDecision(Model $approvable, string $mode): string
    {
        $approvals = AttendanceApproval::where('approvable_type', $approvable::class)
            ->where('approvable_id', $approvable->getKey())
            ->get()
            ->keyBy('step_type');

        if ($approvals->contains(fn ($approval) => $approval->action === 'rejected')) {
            return 'rejected';
        }

        return match ($mode) {
            'hr_only' => ($approvals[self::STEP_HR]->action ?? null) === 'approved' ? 'approved' : 'pending',
            'admin_only' => ($approvals[self::STEP_ADMIN]->action ?? null) === 'approved' ? 'approved' : 'pending',
            'either_first' => $approvals->contains(fn ($approval) => $approval->action === 'approved') ? 'approved' : 'pending',
            'both_required' => ($approvals[self::STEP_HR]->action ?? null) === 'approved'
                && ($approvals[self::STEP_ADMIN]->action ?? null) === 'approved' ? 'approved' : 'pending',
            default => 'pending',
        };
    }

    public function stepForActor(User $actor): ?string
    {
        if ($actor->isAdmin()) {
            return self::STEP_ADMIN;
        }

        if ($actor->isHrManager()) {
            return self::STEP_HR;
        }

        return null;
    }

    public function modeForPolicy(?AttendancePolicy $policy, string $type): string
    {
        return match ($type) {
            'leave' => $policy?->approval_mode_leave ?? 'hr_only',
            'regularization' => $policy?->approval_mode_regularization ?? 'hr_only',
            default => 'hr_only',
        };
    }

    private function stepsForMode(string $mode): array
    {
        return match ($mode) {
            'admin_only' => [self::STEP_ADMIN],
            'either_first', 'both_required' => [self::STEP_HR, self::STEP_ADMIN],
            default => [self::STEP_HR],
        };
    }
}

<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\AttendanceRegularization;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class RegularizationService
{
    public function __construct(
        protected AttendancePolicyResolver $policyResolver,
        protected AttendanceApprovalService $approvalService,
        protected AttendanceLockService $lockService,
        protected OvertimeService $overtimeService,
        protected AttendanceRecordSyncService $recordSyncService
    ) {
    }

    public function createRequest(User $user, array $data, ?UploadedFile $proof = null): AttendanceRegularization
    {
        $date = Carbon::parse($data['attendance_date']);
        $resolved = $this->policyResolver->resolveForUser($user, $date);
        $this->lockService->ensureNotFrozen($date, $resolved['office']?->id);

        $abuseScore = AttendanceRegularization::query()
            ->where('user_id', $user->id)
            ->whereBetween('attendance_date', [$date->copy()->subDays(30)->toDateString(), $date->toDateString()])
            ->count();

        $proofPath = $proof ? Storage::disk('public')->putFile('attendance/regularization-proofs', $proof) : null;

        $request = AttendanceRegularization::create([
            'user_id' => $user->id,
            'attendance_date' => $date->toDateString(),
            'request_type' => $data['request_type'],
            'requested_in_time' => !empty($data['requested_in_time']) ? Carbon::parse($date->toDateString() . ' ' . $data['requested_in_time']) : null,
            'requested_out_time' => !empty($data['requested_out_time']) ? Carbon::parse($date->toDateString() . ' ' . $data['requested_out_time']) : null,
            'requested_status' => $data['requested_status'] ?? null,
            'reason' => $data['reason'] ?? null,
            'proof_path' => $proofPath,
            'status' => 'pending',
            'abuse_score_snapshot' => $abuseScore,
        ]);

        $policy = $resolved['policy'];
        $mode = $this->approvalService->modeForPolicy($policy, 'regularization');
        if ($abuseScore >= (int) ($policy?->regularization_abuse_threshold ?? 3) && $mode === 'hr_only') {
            $mode = 'both_required';
        }

        $this->approvalService->ensureFlow($request, $mode);

        return $request->load('approvals');
    }

    public function applyApprovedRegularization(AttendanceRegularization $regularization): void
    {
        $resolved = $this->policyResolver->resolveForUser($regularization->user, $regularization->attendance_date);
        $this->lockService->ensureNotFrozen($regularization->attendance_date, $resolved['office']?->id);

        $record = AttendanceRecord::query()
            ->where('user_id', $regularization->user_id)
            ->whereDate('attendance_date', $regularization->attendance_date->toDateString())
            ->first();

        if (!$record) {
            $record = new AttendanceRecord([
                'user_id' => $regularization->user_id,
                'attendance_date' => $regularization->attendance_date->toDateString(),
            ]);
        }

        $status = $regularization->requested_status ?: ($record->status ?: AttendanceRecord::STATUS_PRESENT);
        $payable = match ($status) {
            AttendanceRecord::STATUS_HALF_DAY => 0.5,
            AttendanceRecord::STATUS_ABSENT => 0.0,
            default => 1.0,
        };

        $workedMinutes = 0;
        if ($regularization->requested_in_time && $regularization->requested_out_time && $regularization->requested_out_time->gt($regularization->requested_in_time)) {
            $workedMinutes = $regularization->requested_in_time->diffInMinutes($regularization->requested_out_time);
        }

        $record = $this->recordSyncService->syncComputedRecord(
            $regularization->user,
            $regularization->attendance_date,
            [
                'office_location_id' => $record->office_location_id ?? $resolved['office']?->id,
                'attendance_policy_id' => $record->attendance_policy_id ?? $resolved['policy']?->id,
                'first_punch_in_at' => $regularization->requested_in_time ?: $record->first_punch_in_at,
                'last_punch_out_at' => $regularization->requested_out_time ?: $record->last_punch_out_at,
                'status' => $status,
                'status_source' => 'regularization',
                'late_minutes' => 0,
                'worked_minutes' => $workedMinutes ?: $record->worked_minutes,
                'payable_day_fraction' => $payable,
                'has_missing_punch_out' => $regularization->requested_in_time !== null && $regularization->requested_out_time === null,
                'finalized_at' => now(),
            ]
        );
        $this->overtimeService->syncForRecord($record->fresh(['user', 'attendancePolicy']));
    }
}

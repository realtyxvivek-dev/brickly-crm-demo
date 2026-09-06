<?php

namespace App\Services;

use App\Models\AttendancePolicy;
use App\Models\AttendanceRecord;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class LeaveService
{
    public function __construct(
        protected AttendancePolicyResolver $policyResolver,
        protected AttendanceApprovalService $approvalService,
        protected AttendanceLockService $lockService,
        protected AttendanceFinalizerService $finalizerService
    ) {
    }

    public function createRequest(User $user, array $data, ?UploadedFile $attachment = null): LeaveRequest
    {
        $leaveType = LeaveType::findOrFail($data['leave_type_id']);
        $fromDate = Carbon::parse($data['from_date']);
        $toDate = Carbon::parse($data['to_date']);

        if ($toDate->lt($fromDate)) {
            throw ValidationException::withMessages(['to_date' => 'Leave end date cannot be before start date.']);
        }

        $current = $fromDate->copy();
        while ($current->lte($toDate)) {
            $resolved = $this->policyResolver->resolveForUser($user, $current);
            $this->lockService->ensureNotFrozen($current, $resolved['office']?->id);
            $current->addDay();
        }

        $daysRequested = $this->calculateDaysRequested($fromDate, $toDate, $data['duration_mode'] ?? 'full_day');
        $balance = $this->ensureBalance($user, $leaveType, (int) $fromDate->year);

        if ((float) $balance->remaining < $daysRequested) {
            throw ValidationException::withMessages(['leave_type_id' => 'Insufficient leave balance.']);
        }

        $path = $attachment ? Storage::disk('public')->putFile('attendance/leave-attachments', $attachment) : null;

        $request = LeaveRequest::create([
            'user_id' => $user->id,
            'leave_type_id' => $leaveType->id,
            'from_date' => $fromDate->toDateString(),
            'to_date' => $toDate->toDateString(),
            'duration_mode' => $data['duration_mode'] ?? 'full_day',
            'days_requested' => $daysRequested,
            'reason' => $data['reason'] ?? null,
            'attachment_path' => $path,
            'status' => 'pending',
        ]);

        $mode = $this->approvalService->modeForPolicy($this->policyResolver->resolveForUser($user, $fromDate)['policy'], 'leave');
        $this->approvalService->ensureFlow($request, $mode);

        return $request->load(['leaveType', 'approvals']);
    }

    public function applyApprovedLeave(LeaveRequest $leaveRequest): void
    {
        $leaveRequest->loadMissing(['user', 'leaveType']);
        $current = $leaveRequest->from_date->copy();

        while ($current->lte($leaveRequest->to_date)) {
            $resolved = $this->policyResolver->resolveForUser($leaveRequest->user, $current);
            $this->lockService->ensureNotFrozen($current, $resolved['office']?->id);

            $this->finalizerService->finalizeUserForDate($leaveRequest->user, $current);
            $current->addDay();
        }

        $balance = $this->ensureBalance($leaveRequest->user, $leaveRequest->leaveType, (int) $leaveRequest->from_date->year);
        $used = (float) $balance->used + (float) $leaveRequest->days_requested;
        $remaining = max(0, (float) $balance->opening_balance + (float) $balance->credited - $used);

        $balance->update([
            'used' => $used,
            'remaining' => $remaining,
        ]);
    }

    public function ensureBalance(User $user, LeaveType $leaveType, int $year): LeaveBalance
    {
        return LeaveBalance::firstOrCreate(
            [
                'user_id' => $user->id,
                'leave_type_id' => $leaveType->id,
                'year' => $year,
            ],
            [
                'opening_balance' => $leaveType->annual_quota,
                'credited' => 0,
                'used' => 0,
                'remaining' => $leaveType->annual_quota,
            ]
        );
    }

    public function ensureBalancesForUser(User $user, int $year): void
    {
        LeaveType::query()
            ->where('is_active', true)
            ->get()
            ->each(fn (LeaveType $leaveType) => $this->ensureBalance($user, $leaveType, $year));
    }

    private function calculateDaysRequested(Carbon $fromDate, Carbon $toDate, string $durationMode): float
    {
        if ($durationMode !== 'full_day') {
            return 0.5;
        }

        return (float) $fromDate->diffInDays($toDate) + 1;
    }
}

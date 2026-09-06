<?php

namespace App\Services;

use App\Models\AttendanceEvent;
use App\Models\AttendanceRecord;
use App\Models\User;
use App\Models\UserAttendanceProfile;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class AttendanceFinalizerService
{
    public function __construct(
        protected AttendanceAccessService $attendanceAccessService,
        protected AttendancePolicyResolver $policyResolver,
        protected AttendanceRuleEngine $ruleEngine,
        protected OvertimeService $overtimeService,
        protected AttendanceRecordSyncService $recordSyncService
    ) {
    }

    public function finalizeForDate(CarbonInterface|string $date, ?User $user = null): void
    {
        $date = $date instanceof CarbonInterface ? $date->copy() : Carbon::parse($date);

        if ($user) {
            $this->finalizeUserForDate($user, $date);
            return;
        }

        $userIds = UserAttendanceProfile::query()
            ->whereIn('id', $this->attendanceAccessService->enabledProfilesQuery($date)->select('id'))
            ->pluck('user_id');

        User::whereIn('id', $userIds)->get()->each(function (User $candidate) use ($date) {
            $this->finalizeUserForDate($candidate, $date);
        });
    }

    public function finalizeUserForDate(User $user, CarbonInterface $date): AttendanceRecord
    {
        if (!$this->attendanceAccessService->isEnabledFor($user, $date)) {
            return AttendanceRecord::query()
                ->where('user_id', $user->id)
                ->whereDate('attendance_date', $date->toDateString())
                ->first()
                ?: new AttendanceRecord([
                    'user_id' => $user->id,
                    'attendance_date' => $date->toDateString(),
                ]);
        }

        $resolved = $this->policyResolver->resolveForUser($user, $date);
        $policy = $resolved['policy'];
        $office = $resolved['office'];

        $firstPunchIn = AttendanceEvent::query()
            ->where('user_id', $user->id)
            ->whereDate('event_date', $date->toDateString())
            ->where('event_type', AttendanceEvent::TYPE_PUNCH_IN)
            ->orderBy('event_time')
            ->first();

        $lastPunchOut = AttendanceEvent::query()
            ->where('user_id', $user->id)
            ->whereDate('event_date', $date->toDateString())
            ->where('event_type', AttendanceEvent::TYPE_PUNCH_OUT)
            ->orderByDesc('event_time')
            ->first();

        $classification = $this->ruleEngine->classify(
            $user,
            $policy,
            $firstPunchIn?->event_time,
            $date,
            $office?->id
        );

        $halfDayEnd = Carbon::parse($date->toDateString() . ' ' . ($policy?->half_day_end_time ?? '16:00:00'));
        if (!$firstPunchIn && $date->isToday() && now()->lt($halfDayEnd) && $classification['status'] === AttendanceRecord::STATUS_ABSENT) {
            return AttendanceRecord::query()
                ->where('user_id', $user->id)
                ->whereDate('attendance_date', $date->toDateString())
                ->first()
                ?: new AttendanceRecord([
                    'user_id' => $user->id,
                    'attendance_date' => $date->toDateString(),
                ]);
        }

        $record = $this->recordSyncService->syncComputedRecord(
            $user,
            $date,
            $this->recordSyncService->payloadFromClassification(
                $firstPunchIn?->event_time,
                $lastPunchOut?->event_time,
                $classification,
                [
                    'office_location_id' => $office?->id,
                    'attendance_policy_id' => $policy?->id,
                    'fraud_review_status' => 'clear',
                    'fraud_payroll_blocked' => false,
                    'fraud_review_reason' => null,
                    'finalized_at' => now(),
                ]
            )
        );

        $record = $record->fresh(['user', 'attendancePolicy']);
        $this->overtimeService->syncForRecord($record);

        return $record;
    }
}

<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\AttendanceRecordOverrideLog;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Schema;

class AttendanceRecordSyncService
{
    public function __construct(
        protected AttendancePolicyResolver $policyResolver,
        protected AttendanceRuleEngine $ruleEngine,
        protected AttendanceLockService $lockService
    ) {
    }

    public function syncComputedRecord(User $user, CarbonInterface|string $date, array $attributes): AttendanceRecord
    {
        $date = $date instanceof CarbonInterface ? $date->copy() : Carbon::parse($date);

        $record = AttendanceRecord::query()
            ->where('user_id', $user->id)
            ->whereDate('attendance_date', $date->toDateString())
            ->first();

        if (!$record) {
            $record = new AttendanceRecord([
                'user_id' => $user->id,
                'attendance_date' => $date->toDateString(),
            ]);
        }

        $baseAttributes = [
            'office_location_id' => $attributes['office_location_id'] ?? $record->office_location_id,
            'attendance_policy_id' => $attributes['attendance_policy_id'] ?? $record->attendance_policy_id,
            'outside_punch_status' => $attributes['outside_punch_status'] ?? $record->outside_punch_status,
            'outside_punch_distance_meters' => array_key_exists('outside_punch_distance_meters', $attributes)
                ? $attributes['outside_punch_distance_meters']
                : $record->outside_punch_distance_meters,
            'is_suspicious' => $attributes['is_suspicious'] ?? $record->is_suspicious ?? false,
            'fraud_review_status' => $attributes['fraud_review_status'] ?? $record->fraud_review_status ?? 'clear',
            'fraud_payroll_blocked' => $attributes['fraud_payroll_blocked'] ?? $record->fraud_payroll_blocked ?? false,
            'fraud_review_reason' => array_key_exists('fraud_review_reason', $attributes)
                ? $attributes['fraud_review_reason']
                : $record->fraud_review_reason,
            'suspicion_flags_json' => $attributes['suspicion_flags_json'] ?? $record->suspicion_flags_json,
            'finalized_at' => $attributes['finalized_at'] ?? now(),
        ];

        $autoAttributes = [
            'auto_first_punch_in_at' => $attributes['first_punch_in_at'] ?? null,
            'auto_last_punch_out_at' => $attributes['last_punch_out_at'] ?? null,
            'auto_status' => $attributes['status'] ?? AttendanceRecord::STATUS_ABSENT,
            'auto_status_source' => $attributes['status_source'] ?? 'auto',
            'auto_late_minutes' => (int) ($attributes['late_minutes'] ?? 0),
            'auto_worked_minutes' => (int) ($attributes['worked_minutes'] ?? 0),
            'auto_payable_day_fraction' => (float) ($attributes['payable_day_fraction'] ?? 0),
            'auto_has_missing_punch_out' => (bool) ($attributes['has_missing_punch_out'] ?? false),
        ];

        $effectiveAttributes = [
            'first_punch_in_at' => $attributes['first_punch_in_at'] ?? null,
            'last_punch_out_at' => $attributes['last_punch_out_at'] ?? null,
            'status' => $attributes['status'] ?? AttendanceRecord::STATUS_ABSENT,
            'status_source' => $attributes['status_source'] ?? 'auto',
            'late_minutes' => (int) ($attributes['late_minutes'] ?? 0),
            'worked_minutes' => (int) ($attributes['worked_minutes'] ?? 0),
            'payable_day_fraction' => (float) ($attributes['payable_day_fraction'] ?? 0),
            'has_missing_punch_out' => (bool) ($attributes['has_missing_punch_out'] ?? false),
        ];

        $record->fill($this->onlyExistingColumns(array_merge($baseAttributes, $autoAttributes)));

        if (!$record->hasActiveManualOverride()) {
            $record->fill($this->onlyExistingColumns($effectiveAttributes));
        }

        $record->save();

        return $record->fresh();
    }

    public function applyManualOverride(AttendanceRecord $record, array $attributes, User $actor): AttendanceRecord
    {
        $record->loadMissing('user');

        $attendanceDate = $record->attendance_date instanceof CarbonInterface
            ? $record->attendance_date->copy()
            : Carbon::parse($record->attendance_date);

        $this->lockService->ensureNotFrozen($attendanceDate, $record->office_location_id);
        $this->ensureAutoSnapshot($record);

        $before = $this->snapshot($record);

        $manualFirstPunchInAt = array_key_exists('manual_first_punch_in_at', $attributes)
            ? $attributes['manual_first_punch_in_at']
            : ($record->manual_first_punch_in_at ?? $record->first_punch_in_at);
        $manualLastPunchOutAt = array_key_exists('manual_last_punch_out_at', $attributes)
            ? $attributes['manual_last_punch_out_at']
            : ($record->manual_last_punch_out_at ?? $record->last_punch_out_at);
        $manualStatus = $attributes['manual_status'] ?? $record->manual_status;

        $resolved = $this->policyResolver->resolveForUser($record->user, $attendanceDate);
        $classification = $this->ruleEngine->classify(
            $record->user,
            $resolved['policy'],
            $manualFirstPunchInAt,
            $attendanceDate,
            $record->office_location_id ?? $resolved['office']?->id
        );

        $status = $manualStatus ?: $classification['status'];
        $lateMinutes = $status === AttendanceRecord::STATUS_LATE
            ? (int) ($classification['late_minutes'] ?? 0)
            : 0;
        $workedMinutes = 0;
        if ($manualFirstPunchInAt && $manualLastPunchOutAt && $manualLastPunchOutAt->greaterThan($manualFirstPunchInAt)) {
            $workedMinutes = $manualFirstPunchInAt->diffInMinutes($manualLastPunchOutAt);
        }

        $record->fill($this->onlyExistingColumns([
            'manual_first_punch_in_at' => $manualFirstPunchInAt,
            'manual_last_punch_out_at' => $manualLastPunchOutAt,
            'manual_status' => $status,
            'manual_override_reason' => $attributes['manual_override_reason'] ?? null,
            'manual_overridden_by' => $actor->id,
            'manual_overridden_at' => now(),
            'manual_cleared_by' => null,
            'manual_cleared_at' => null,
            'first_punch_in_at' => $manualFirstPunchInAt,
            'last_punch_out_at' => $manualLastPunchOutAt,
            'status' => $status,
            'status_source' => 'manual_override',
            'late_minutes' => $lateMinutes,
            'worked_minutes' => $workedMinutes,
            'payable_day_fraction' => array_key_exists('payable_day_fraction', $attributes)
                ? (float) $attributes['payable_day_fraction']
                : $this->payableFractionFor($status),
            'has_missing_punch_out' => $manualFirstPunchInAt !== null && $manualLastPunchOutAt === null,
            'finalized_at' => now(),
        ]));
        $record->save();

        $this->logChange($record, $actor, 'applied', (string) ($attributes['manual_override_reason'] ?? ''), $before, $this->snapshot($record));

        return $record->fresh(['overrideLogs.actor', 'user.role', 'officeLocation']);
    }

    public function clearManualOverride(AttendanceRecord $record, User $actor, ?string $reason = null): AttendanceRecord
    {
        $attendanceDate = $record->attendance_date instanceof CarbonInterface
            ? $record->attendance_date->copy()
            : Carbon::parse($record->attendance_date);
        $this->lockService->ensureNotFrozen($attendanceDate, $record->office_location_id);

        $before = $this->snapshot($record);

        $record->fill($this->onlyExistingColumns([
            'first_punch_in_at' => $record->auto_first_punch_in_at,
            'last_punch_out_at' => $record->auto_last_punch_out_at,
            'status' => $record->auto_status ?: AttendanceRecord::STATUS_ABSENT,
            'status_source' => $record->auto_status_source ?: 'auto',
            'late_minutes' => (int) ($record->auto_late_minutes ?? 0),
            'worked_minutes' => (int) ($record->auto_worked_minutes ?? 0),
            'payable_day_fraction' => (float) ($record->auto_payable_day_fraction ?? 0),
            'has_missing_punch_out' => (bool) ($record->auto_has_missing_punch_out ?? false),
            'manual_first_punch_in_at' => null,
            'manual_last_punch_out_at' => null,
            'manual_status' => null,
            'manual_override_reason' => null,
            'manual_cleared_by' => $actor->id,
            'manual_cleared_at' => now(),
            'finalized_at' => now(),
        ]));
        $record->save();

        $this->logChange($record, $actor, 'cleared', $reason, $before, $this->snapshot($record));

        return $record->fresh(['overrideLogs.actor', 'user.role', 'officeLocation']);
    }

    public function payloadFromClassification(
        ?CarbonInterface $firstPunchInAt,
        ?CarbonInterface $lastPunchOutAt,
        array $classification,
        array $extra = []
    ): array {
        $workedMinutes = 0;
        if ($firstPunchInAt && $lastPunchOutAt && $lastPunchOutAt->greaterThan($firstPunchInAt)) {
            $workedMinutes = $firstPunchInAt->diffInMinutes($lastPunchOutAt);
        }

        return array_merge([
            'first_punch_in_at' => $firstPunchInAt,
            'last_punch_out_at' => $lastPunchOutAt,
            'status' => $classification['status'] ?? AttendanceRecord::STATUS_ABSENT,
            'status_source' => $classification['status_source'] ?? 'auto',
            'late_minutes' => (int) ($classification['late_minutes'] ?? 0),
            'worked_minutes' => $workedMinutes,
            'payable_day_fraction' => (float) ($classification['payable_day_fraction'] ?? 0),
            'has_missing_punch_out' => $firstPunchInAt !== null && $lastPunchOutAt === null,
        ], $extra);
    }

    private function ensureAutoSnapshot(AttendanceRecord $record): void
    {
        if (
            $record->auto_status !== null
            || $record->auto_first_punch_in_at !== null
            || $record->auto_last_punch_out_at !== null
        ) {
            return;
        }

        $record->fill($this->onlyExistingColumns([
            'auto_first_punch_in_at' => $record->first_punch_in_at,
            'auto_last_punch_out_at' => $record->last_punch_out_at,
            'auto_status' => $record->status,
            'auto_status_source' => $record->status_source,
            'auto_late_minutes' => $record->late_minutes,
            'auto_worked_minutes' => $record->worked_minutes,
            'auto_payable_day_fraction' => $record->payable_day_fraction,
            'auto_has_missing_punch_out' => $record->has_missing_punch_out,
        ]));
        $record->save();
    }

    private function snapshot(AttendanceRecord $record): array
    {
        return [
            'effective' => [
                'first_punch_in_at' => optional($record->first_punch_in_at)?->toIso8601String(),
                'last_punch_out_at' => optional($record->last_punch_out_at)?->toIso8601String(),
                'status' => $record->status,
                'status_source' => $record->status_source,
                'late_minutes' => $record->late_minutes,
                'worked_minutes' => $record->worked_minutes,
                'payable_day_fraction' => (float) $record->payable_day_fraction,
                'has_missing_punch_out' => (bool) $record->has_missing_punch_out,
            ],
            'manual' => [
                'first_punch_in_at' => optional($record->manual_first_punch_in_at)?->toIso8601String(),
                'last_punch_out_at' => optional($record->manual_last_punch_out_at)?->toIso8601String(),
                'status' => $record->manual_status,
                'reason' => $record->manual_override_reason,
            ],
            'auto' => [
                'first_punch_in_at' => optional($record->auto_first_punch_in_at)?->toIso8601String(),
                'last_punch_out_at' => optional($record->auto_last_punch_out_at)?->toIso8601String(),
                'status' => $record->auto_status,
                'status_source' => $record->auto_status_source,
                'late_minutes' => $record->auto_late_minutes,
                'worked_minutes' => $record->auto_worked_minutes,
                'payable_day_fraction' => (float) ($record->auto_payable_day_fraction ?? 0),
                'has_missing_punch_out' => (bool) ($record->auto_has_missing_punch_out ?? false),
            ],
        ];
    }

    private function logChange(
        AttendanceRecord $record,
        User $actor,
        string $action,
        ?string $reason,
        array $before,
        array $after
    ): void {
        AttendanceRecordOverrideLog::create([
            'attendance_record_id' => $record->id,
            'user_id' => $record->user_id,
            'actor_id' => $actor->id,
            'action' => $action,
            'reason' => $reason,
            'before_json' => $before,
            'after_json' => $after,
        ]);
    }

    private function payableFractionFor(string $status): float
    {
        return match ($status) {
            AttendanceRecord::STATUS_ABSENT => 0.0,
            AttendanceRecord::STATUS_HALF_DAY => 0.5,
            default => 1.0,
        };
    }

    private function onlyExistingColumns(array $attributes): array
    {
        static $columns = null;

        if ($columns === null) {
            $columns = array_flip(Schema::getColumnListing('attendance_records'));
        }

        return array_filter(
            $attributes,
            fn ($value, $key) => isset($columns[$key]),
            ARRAY_FILTER_USE_BOTH
        );
    }
}

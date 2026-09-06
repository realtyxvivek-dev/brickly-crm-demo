<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;

class AttendanceSummaryService
{
    private const MULTI_PUNCH_TEST_EMAILS = [
        'test@gmail.com',
    ];

    public function __construct(
        protected AttendanceFinalizerService $finalizerService,
        protected AttendancePolicyResolver $policyResolver
    ) {
    }

    public function today(User $user): array
    {
        $today = now();
        $resolved = $this->policyResolver->resolveForUser($user, $today);
        $policy = $resolved['policy'];
        $office = $resolved['office'];
        $allowMultiPunchTest = $this->allowMultiPunchTest($user);

        $record = AttendanceRecord::query()
            ->where('user_id', $user->id)
            ->where('attendance_date', $today->toDateString())
            ->first();

        if (!$record && $policy) {
            $record = $this->finalizerService->finalizeUserForDate($user, $today);
        }

        $recordPayload = ($record && $record->exists) ? [
            'status' => $record->status,
            'status_label' => ucwords(str_replace('_', ' ', $record->status)),
            'first_punch_in_at' => optional($record->first_punch_in_at)->toDateTimeString(),
            'last_punch_out_at' => optional($record->last_punch_out_at)->toDateTimeString(),
            'office_name' => $office?->name,
            'has_missing_punch_out' => (bool) $record->has_missing_punch_out,
            'outside_punch_status' => $record->outside_punch_status,
            'outside_punch_distance_meters' => $record->outside_punch_distance_meters,
            'is_suspicious' => (bool) $record->is_suspicious,
            'suspicion_flags' => $record->suspicion_flags_json ?? [],
            'worked_minutes' => (int) $record->worked_minutes,
            'info_line' => $this->infoLine($policy, $user),
        ] : null;

        return [
            'configured' => $policy !== null,
            'policy' => $policy ? [
                'name' => $policy->name,
                'photo_required' => (bool) $policy->photo_required,
                'geo_fence_required' => (bool) $policy->geo_fence_required,
                'normal_window_end_time' => $policy->normal_window_end_time,
                'half_day_start_time' => $policy->half_day_start_time,
                'half_day_end_time' => $policy->half_day_end_time,
            ] : null,
            'office' => $office ? [
                'id' => $office->id,
                'name' => $office->name,
                'radius_meters' => $office->radius_meters,
            ] : null,
            'allow_multi_punch_test' => $allowMultiPunchTest,
            'record' => $recordPayload,
            'month_summary' => $this->monthSummary($user, $today),
        ];
    }

    public function history(User $user, ?Carbon $date = null): array
    {
        $date ??= now();

        return AttendanceRecord::query()
            ->where('user_id', $user->id)
            ->whereYear('attendance_date', $date->year)
            ->whereMonth('attendance_date', $date->month)
            ->orderByDesc('attendance_date')
            ->get()
            ->map(fn (AttendanceRecord $record) => [
                'attendance_date' => $record->attendance_date->toDateString(),
                'status' => $record->status,
                'status_label' => ucwords(str_replace('_', ' ', $record->status)),
                'first_punch_in_at' => optional($record->first_punch_in_at)->toDateTimeString(),
                'last_punch_out_at' => optional($record->last_punch_out_at)->toDateTimeString(),
                'worked_minutes' => (int) $record->worked_minutes,
                'is_suspicious' => (bool) $record->is_suspicious,
                'outside_punch_status' => $record->outside_punch_status,
                'outside_punch_distance_meters' => $record->outside_punch_distance_meters,
            ])
            ->all();
    }

    public function monthSummary(User $user, Carbon $date): array
    {
        $rows = AttendanceRecord::query()
            ->selectRaw('status, COUNT(*) as aggregate_count')
            ->where('user_id', $user->id)
            ->whereYear('attendance_date', $date->year)
            ->whereMonth('attendance_date', $date->month)
            ->groupBy('status')
            ->pluck('aggregate_count', 'status');

        return [
            'present' => (int) ($rows['present'] ?? 0),
            'late' => (int) ($rows['late'] ?? 0),
            'half_day' => (int) ($rows['half_day'] ?? 0),
            'absent' => (int) ($rows['absent'] ?? 0),
        ];
    }

    private function infoLine($policy, ?User $user = null): string
    {
        if (!$policy) {
            return 'Attendance policy not configured.';
        }

        if ($user && $this->allowMultiPunchTest($user)) {
            return 'Test mode active. Multiple punch cycles are allowed.';
        }

        $now = now()->format('H:i:s');

        if ($now <= ($policy->normal_window_end_time ?? '11:30:00')) {
            return 'Normal punch window open.';
        }

        if ($now >= ($policy->half_day_start_time ?? '13:00:00') && $now <= ($policy->half_day_end_time ?? '16:00:00')) {
            return 'Half-day window open.';
        }

        if ($now > ($policy->half_day_end_time ?? '16:00:00')) {
            return 'Attendance closed for today.';
        }

        return 'Waiting for half-day window.';
    }

    private function allowMultiPunchTest(User $user): bool
    {
        return in_array(strtolower((string) $user->email), self::MULTI_PUNCH_TEST_EMAILS, true);
    }
}

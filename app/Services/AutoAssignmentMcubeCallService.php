<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\McubeOutboundAttempt;
use App\Models\McubeSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AutoAssignmentMcubeCallService
{
    public function __construct(private readonly McubeOutboundCallService $mcubeOutboundCallService)
    {
    }

    public function handle(Lead $lead, User $assignedUser, int $assignedBy): ?array
    {
        $settings = McubeSetting::getSettings();
        $skipReason = $this->skipReason($settings, $lead, $assignedUser);

        if ($skipReason !== null) {
            Log::info('MCube assignment auto-call skipped', [
                'lead_id' => $lead->id,
                'assigned_to' => $assignedUser->id,
                'reason' => $skipReason,
            ]);

            return null;
        }

        $assignment = $lead->activeAssignments()
            ->where('assigned_to', $assignedUser->id)
            ->latest('assigned_at')
            ->latest('id')
            ->first();

        $refid = sprintf(
            'auto_assignment:lead:%d:assignment:%s',
            $lead->id,
            $assignment?->id ?: 'none'
        );

        try {
            $result = $this->mcubeOutboundCallService->initiate(
                $assignedUser,
                $lead,
                null,
                null,
                $refid
            );

            Log::info('MCube assignment auto-call processed', [
                'lead_id' => $lead->id,
                'assigned_to' => $assignedUser->id,
                'assigned_by' => $assignedBy,
                'success' => (bool) ($result['success'] ?? false),
                'attempt_id' => $result['attempt_id'] ?? null,
                'message' => $result['message'] ?? null,
            ]);

            return $result;
        } catch (\Throwable $e) {
            Log::error('MCube assignment auto-call failed without blocking assignment', [
                'lead_id' => $lead->id,
                'assigned_to' => $assignedUser->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function skipReason(McubeSetting $settings, Lead $lead, User $assignedUser): ?string
    {
        if (!$settings->auto_call_on_assignment) {
            return 'disabled';
        }

        if (!$settings->outbound_enabled) {
            return 'outbound_disabled';
        }

        if ($this->mcubeOutboundCallService->normalizePhone((string) $lead->phone) === '') {
            return 'missing_lead_phone';
        }

        if ($this->mcubeOutboundCallService->normalizePhone((string) $assignedUser->phone) === '') {
            return 'missing_user_phone';
        }

        if ($this->isQuietHours($settings)) {
            return 'quiet_hours';
        }

        $allowedSources = array_filter((array) $settings->auto_call_allowed_sources);
        if ($allowedSources !== [] && !in_array((string) $lead->source, $allowedSources, true)) {
            return 'source_not_allowed';
        }

        $allowedUserIds = array_map('intval', array_filter((array) $settings->auto_call_allowed_user_ids));
        if ($allowedUserIds !== [] && !in_array((int) $assignedUser->id, $allowedUserIds, true)) {
            return 'user_not_allowed';
        }

        if ($this->hasRecentAutoAttempt($lead, $settings->autoCallCooldownMinutes())) {
            return 'cooldown_or_duplicate';
        }

        return null;
    }

    private function hasRecentAutoAttempt(Lead $lead, int $cooldownMinutes): bool
    {
        $query = McubeOutboundAttempt::query()
            ->where('lead_id', $lead->id)
            ->where('refid', 'like', 'auto_assignment:lead:' . $lead->id . ':%');

        if ($cooldownMinutes > 0) {
            $query->where('attempted_at', '>=', now()->subMinutes($cooldownMinutes));
        } else {
            $query->where('status', 'pending');
        }

        return $query->exists();
    }

    private function isQuietHours(McubeSetting $settings): bool
    {
        if (!$settings->auto_call_quiet_start || !$settings->auto_call_quiet_end) {
            return false;
        }

        $now = now();
        $start = Carbon::createFromFormat('H:i:s', $this->normalizeTime($settings->auto_call_quiet_start), $now->timezone)->setDate($now->year, $now->month, $now->day);
        $end = Carbon::createFromFormat('H:i:s', $this->normalizeTime($settings->auto_call_quiet_end), $now->timezone)->setDate($now->year, $now->month, $now->day);

        if ($start->equalTo($end)) {
            return false;
        }

        if ($start->lessThan($end)) {
            return $now->betweenIncluded($start, $end);
        }

        return $now->greaterThanOrEqualTo($start) || $now->lessThanOrEqualTo($end);
    }

    private function normalizeTime(string $time): string
    {
        return strlen($time) === 5 ? $time . ':00' : $time;
    }
}

<?php

namespace App\Services;

use App\Models\AttendanceEvent;
use App\Models\AttendanceRecord;
use App\Models\AttendancePolicy;
use App\Models\AttendanceSuspicionLog;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Schema;

class SuspicionDetectionService
{
    public function detect(
        User $user,
        ?AttendancePolicy $policy,
        ?float $distance,
        ?bool $insideFence,
        bool $geoProvided,
        CarbonInterface $date,
        ?string $deviceFingerprint = null,
        ?float $latitude = null,
        ?float $longitude = null
    ): array {
        $flags = [];

        if ($policy?->geo_fence_required && !$geoProvided) {
            $flags[] = 'missing_geo';
        }

        if ($insideFence === false) {
            $flags[] = 'outside_radius';
        }

        if ($distance !== null && $policy && $distance > $policy->suspicious_geo_threshold_meters) {
            $flags[] = 'distance_threshold_exceeded';
        }

        if ($distance !== null && $policy && $distance >= max(1, ((int) $policy->suspicious_geo_threshold_meters - 10))) {
            $recentEdgePunches = AttendanceEvent::query()
                ->where('user_id', $user->id)
                ->where('event_type', AttendanceEvent::TYPE_PUNCH_IN)
                ->whereBetween('event_date', [$date->copy()->subDays(14)->toDateString(), $date->toDateString()])
                ->whereNotNull('geo_distance_meters')
                ->where('geo_distance_meters', '>=', max(1, ((int) $policy->suspicious_geo_threshold_meters - 10)))
                ->count();

            if ($recentEdgePunches >= 2) {
                $flags[] = 'repeated_edge_punch';
            }
        }

        if ($deviceFingerprint) {
            $deviceUsage = AttendanceEvent::query()
                ->where('device_fingerprint', $deviceFingerprint)
                ->where('event_type', AttendanceEvent::TYPE_PUNCH_IN)
                ->where('event_date', $date->toDateString())
                ->where('user_id', '!=', $user->id)
                ->exists();

            if ($deviceUsage) {
                $flags[] = 'shared_device_same_day';
            }
        }

        if ($latitude !== null && $longitude !== null) {
            $recentEvent = AttendanceEvent::query()
                ->where('user_id', $user->id)
                ->where('event_type', AttendanceEvent::TYPE_PUNCH_IN)
                ->whereDate('event_date', '>=', $date->copy()->subDays(1)->toDateString())
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->orderByDesc('event_time')
                ->first();

            if ($recentEvent && $recentEvent->event_time && $recentEvent->event_time->diffInHours($date) <= 3) {
                $travelDistance = $this->haversine(
                    (float) $recentEvent->latitude,
                    (float) $recentEvent->longitude,
                    $latitude,
                    $longitude
                );

                if ($travelDistance > 50000) {
                    $flags[] = 'impossible_travel';
                }
            }
        }

        return array_values(array_unique($flags));
    }

    public function recordFlags(
        User $user,
        CarbonInterface $date,
        array $flags,
        ?AttendanceRecord $record = null,
        ?int $eventId = null,
        array $details = []
    ): void {
        if (empty($flags) || !Schema::hasTable('attendance_suspicion_logs')) {
            return;
        }

        foreach ($flags as $flag) {
            AttendanceSuspicionLog::create([
                'user_id' => $user->id,
                'attendance_record_id' => $record?->id,
                'event_id' => $eventId,
                'flag_type' => $flag,
                'severity' => in_array($flag, ['outside_radius', 'impossible_travel', 'duplicate_photo'], true) ? 'high' : 'medium',
                'details_json' => array_merge(['date' => $date->toDateString()], $details),
                'status' => 'open',
            ]);
        }
    }

    public function reviewOpenForDate(CarbonInterface $date): int
    {
        if (!Schema::hasTable('attendance_suspicion_logs')) {
            return 0;
        }

        $count = 0;
        $events = AttendanceEvent::query()
            ->whereDate('event_date', $date->toDateString())
            ->where('event_type', AttendanceEvent::TYPE_PUNCH_IN)
            ->with('user')
            ->get();

        foreach ($events as $event) {
            $resolved = app(AttendancePolicyResolver::class)->resolveForUser($event->user, $date);
            $flags = $this->detect(
                $event->user,
                $resolved['policy'],
                $event->geo_distance_meters,
                $event->inside_geo_fence,
                $event->latitude !== null && $event->longitude !== null,
                $date,
                $event->device_fingerprint,
                $event->latitude,
                $event->longitude
            );

            if (empty($flags)) {
                continue;
            }

            $record = AttendanceRecord::query()
                ->where('user_id', $event->user_id)
                ->whereDate('attendance_date', $date->toDateString())
                ->first();

            $existingFlags = AttendanceSuspicionLog::query()
                ->where('event_id', $event->id)
                ->pluck('flag_type')
                ->all();

            $newFlags = array_values(array_diff($flags, $existingFlags));
            $this->recordFlags($event->user, $date, $newFlags, $record, $event->id, [
                'distance' => $event->geo_distance_meters,
                'inside_geo_fence' => $event->inside_geo_fence,
            ]);

            $count += count($newFlags);
        }

        return $count;
    }

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) * sin($dLng / 2);

        return 2 * $earthRadius * atan2(sqrt($a), sqrt(1 - $a));
    }
}

<?php

namespace App\Services;

use App\Models\AttendanceEvent;
use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AttendancePunchService
{
    private const MULTI_PUNCH_TEST_EMAILS = [
        'test@gmail.com',
    ];

    public function __construct(
        protected AttendancePolicyResolver $policyResolver,
        protected GeoFenceResolver $geoFenceResolver,
        protected AttendancePhotoService $photoService,
        protected AttendanceRuleEngine $ruleEngine,
        protected SuspicionDetectionService $suspicionDetectionService,
        protected FaceFraudReviewService $faceFraudReviewService,
        protected AttendanceFinalizerService $finalizerService,
        protected AttendanceLockService $lockService,
        protected OvertimeService $overtimeService,
        protected AttendanceOutsidePunchService $outsidePunchService
    ) {
    }

    public function punchIn(User $user, Request $request): AttendanceRecord
    {
        $resolved = $this->policyResolver->resolveForUser($user, now());
        $policy = $resolved['policy'];
        $office = $resolved['office'];
        $allowMultiPunchTest = $this->allowMultiPunchTest($user);

        if (!$policy) {
            throw ValidationException::withMessages([
                'attendance' => 'Attendance policy is not configured for this user.',
            ]);
        }

        $this->lockService->ensureNotFrozen(now(), $office?->id);

        $date = now()->toDateString();
        $existing = AttendanceRecord::query()->where('user_id', $user->id)->whereDate('attendance_date', $date)->first();
        if ($existing?->first_punch_in_at && !$allowMultiPunchTest) {
            throw ValidationException::withMessages([
                'attendance' => 'Punch-in already recorded for today.',
            ]);
        }

        $latitude = $request->filled('latitude') ? (float) $request->input('latitude') : null;
        $longitude = $request->filled('longitude') ? (float) $request->input('longitude') : null;
        $geo = $this->geoFenceResolver->resolve($office, $latitude, $longitude);
        $outsideDecision = $this->outsidePunchService->ensurePunchAllowed(
            $user,
            $policy,
            $office,
            'in',
            $geo,
            $latitude,
            $longitude
        );

        if ($request->hasFile('photo') && $request->input('photo_capture_mode') !== 'camera') {
            throw ValidationException::withMessages([
                'photo' => 'Attendance photo must be captured live from the camera.',
            ]);
        }

        $photo = $this->photoService->storeCompressed(
            $request->file('photo'),
            $user,
            $policy,
            [
                'capture_mode' => $request->input('photo_capture_mode'),
                'capture_source' => 'attendance_widget_camera',
                'client_filename' => $request->file('photo')?->getClientOriginalName() ?: ('camera-' . Str::uuid() . '.jpg'),
            ]
        );
        $flags = $this->suspicionDetectionService->detect(
            $user,
            $policy,
            $geo['distance'],
            $geo['inside'],
            $latitude !== null && $longitude !== null,
            now(),
            $request->input('device_fingerprint'),
            $latitude,
            $longitude
        );
        $flags = $this->outsidePunchService->applyOutsideFlags($flags, $outsideDecision['outside_status']);

        $event = AttendanceEvent::create([
            'user_id' => $user->id,
            'event_date' => $date,
            'event_type' => AttendanceEvent::TYPE_PUNCH_IN,
            'event_time' => now(),
            'source' => $request->input('source', 'web'),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'office_location_id' => $geo['office']?->id,
            'geo_distance_meters' => $geo['distance'],
            'inside_geo_fence' => $geo['inside'],
            'photo_id' => $photo?->id,
            'device_fingerprint' => $request->input('device_fingerprint'),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 65535),
            'meta_json' => [
                'flags' => $flags,
                'outside_punch_status' => $outsideDecision['outside_status'],
                'outside_punch_permission_id' => $outsideDecision['permission']?->id,
            ],
        ]);

        $record = $this->finalizerService->finalizeUserForDate($user, Carbon::today());

        $record->forceFill($this->onlyExistingAttendanceColumns([
            'office_location_id' => $geo['office']?->id ?? $record->office_location_id,
            'attendance_policy_id' => $policy->id,
            'outside_punch_status' => $outsideDecision['outside_status'],
            'outside_punch_distance_meters' => $outsideDecision['outside_status'] ? $geo['distance'] : null,
            'is_suspicious' => !empty($flags),
            'suspicion_flags_json' => $flags,
        ]))->save();

        $fraudFlags = $this->faceFraudReviewService->evaluatePunchIn($user, $policy, $record, $event, $photo);
        if (!empty($fraudFlags)) {
            $flags = array_values(array_unique(array_merge($flags, $fraudFlags)));
            $record->update($this->onlyExistingAttendanceColumns([
                'is_suspicious' => true,
                'suspicion_flags_json' => $flags,
            ]));
        }

        $this->suspicionDetectionService->recordFlags($user, now(), $flags, $record, $event->id, [
            'distance' => $geo['distance'],
            'inside_geo_fence' => $geo['inside'],
            'office_location_id' => $geo['office']?->id,
        ]);

        return $record->fresh();
    }

    public function punchOut(User $user, Request $request): AttendanceRecord
    {
        $allowMultiPunchTest = $this->allowMultiPunchTest($user);
        $resolved = $this->policyResolver->resolveForUser($user, now());
        $record = AttendanceRecord::query()
            ->where('user_id', $user->id)
            ->whereDate('attendance_date', now()->toDateString())
            ->first();

        if (!$record || !$record->first_punch_in_at) {
            throw ValidationException::withMessages([
                'attendance' => 'Punch-in required before punch-out.',
            ]);
        }

        $this->lockService->ensureNotFrozen(now(), $record->office_location_id);

        if ($record->last_punch_out_at && !$allowMultiPunchTest) {
            throw ValidationException::withMessages([
                'attendance' => 'Punch-out already recorded for today.',
            ]);
        }

        $latitude = $request->filled('latitude') ? (float) $request->input('latitude') : null;
        $longitude = $request->filled('longitude') ? (float) $request->input('longitude') : null;
        $office = $resolved['office'] ?: $record->officeLocation;
        $geo = $this->geoFenceResolver->resolve($office, $latitude, $longitude);
        $outsideDecision = $this->outsidePunchService->ensurePunchAllowed(
            $user,
            $resolved['policy'],
            $office,
            'out',
            $geo,
            $latitude,
            $longitude
        );

        AttendanceEvent::create([
            'user_id' => $user->id,
            'event_date' => now()->toDateString(),
            'event_type' => AttendanceEvent::TYPE_PUNCH_OUT,
            'event_time' => now(),
            'source' => $request->input('source', 'web'),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'office_location_id' => $office?->id ?: $record->office_location_id,
            'geo_distance_meters' => $geo['distance'],
            'inside_geo_fence' => $geo['inside'],
            'device_fingerprint' => $request->input('device_fingerprint'),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 65535),
            'meta_json' => [
                'outside_punch_status' => $outsideDecision['outside_status'],
                'outside_punch_permission_id' => $outsideDecision['permission']?->id,
            ],
        ]);

        $record = $this->finalizerService->finalizeUserForDate($user, Carbon::today());
        $record->forceFill($this->onlyExistingAttendanceColumns([
            'outside_punch_status' => $outsideDecision['outside_status'] ?: $record->outside_punch_status,
            'outside_punch_distance_meters' => $outsideDecision['outside_status'] ? $geo['distance'] : $record->outside_punch_distance_meters,
            'finalized_at' => now(),
        ]))->save();

        $record = $this->syncPunchOutWithManualOverride($record);

        return $record->fresh();
    }

    private function allowMultiPunchTest(User $user): bool
    {
        return in_array(strtolower((string) $user->email), self::MULTI_PUNCH_TEST_EMAILS, true);
    }

    private function syncPunchOutWithManualOverride(AttendanceRecord $record): AttendanceRecord
    {
        if (!$record->hasActiveManualOverride() || !$record->manual_first_punch_in_at || $record->manual_last_punch_out_at) {
            return $record;
        }

        $punchOutAt = $record->auto_last_punch_out_at ?: $record->last_punch_out_at;
        if (!$punchOutAt || !$punchOutAt->greaterThan($record->manual_first_punch_in_at)) {
            return $record;
        }

        $record->forceFill($this->onlyExistingAttendanceColumns([
            'manual_last_punch_out_at' => $punchOutAt,
            'last_punch_out_at' => $punchOutAt,
            'worked_minutes' => $record->manual_first_punch_in_at->diffInMinutes($punchOutAt),
            'has_missing_punch_out' => false,
            'finalized_at' => now(),
        ]))->save();

        return $record->fresh();
    }

    private function onlyExistingAttendanceColumns(array $attributes): array
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

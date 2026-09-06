<?php

namespace App\Services;

use App\Exceptions\OutsidePunchRequiredException;
use App\Models\AttendanceEvent;
use App\Models\AttendanceOutsidePunchPermission;
use App\Models\AttendanceOutsidePunchRequest;
use App\Models\AttendancePolicy;
use App\Models\AttendanceRecord;
use App\Models\OfficeLocation;
use App\Models\User;
use App\Models\UserAttendanceProfile;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AttendanceOutsidePunchService
{
    public function __construct(
        protected AttendanceApprovalService $approvalService,
        protected AttendanceLockService $lockService,
        protected AttendancePolicyResolver $policyResolver,
        protected AttendanceRuleEngine $ruleEngine,
        protected OvertimeService $overtimeService,
        protected AttendanceFinalizerService $finalizerService,
        protected NotificationService $notificationService
    ) {
    }

    public function ensurePunchAllowed(
        User $user,
        ?AttendancePolicy $policy,
        ?OfficeLocation $office,
        string $punchType,
        array $geo,
        ?float $latitude,
        ?float $longitude
    ): array {
        if (!$policy?->geo_fence_required || !$office || ($geo['inside'] ?? false)) {
            return [
                'allowed' => true,
                'outside_status' => null,
                'permission' => null,
            ];
        }

        if ($latitude === null || $longitude === null || ($geo['distance'] ?? null) === null) {
            throw new OutsidePunchRequiredException(
                'Live location not available. Please enable device/browser location and try again at office.',
                [
                    'can_request' => $this->requestsAllowed($user, $policy),
                    'punch_type' => $punchType,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'geo_distance_meters' => null,
                    'office_location_id' => $office?->id,
                    'office_name' => $office?->name,
                    'radius_meters' => $office?->radius_meters,
                    'location_missing' => true,
                    'location_error_code' => 'missing_coordinates',
                    'location_error_message' => 'Coordinates were not received from the browser.',
                ]
            );
        }

        $permission = $this->findActivePermission($user, $policy, now(), $punchType);
        if ($permission) {
            return [
                'allowed' => true,
                'outside_status' => 'approved_permission',
                'permission' => $permission,
            ];
        }

        throw new OutsidePunchRequiredException(
            'Please punch in at office location.',
            [
                'can_request' => $this->requestsAllowed($user, $policy),
                'punch_type' => $punchType,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'geo_distance_meters' => $geo['distance'] ?? null,
                'office_location_id' => $office?->id,
                'office_name' => $office?->name,
                'radius_meters' => $office?->radius_meters,
                'location_missing' => false,
                'location_error_code' => null,
                'location_error_message' => null,
            ]
        );
    }

    public function requestsAllowed(User $user, ?AttendancePolicy $policy): bool
    {
        $profile = UserAttendanceProfile::query()->where('user_id', $user->id)->first();
        if ($profile && $profile->allow_outside_punch_requests !== null) {
            return (bool) $profile->allow_outside_punch_requests;
        }

        return (bool) ($policy?->allow_outside_punch_requests ?? true);
    }

    public function findActivePermission(User $user, ?AttendancePolicy $policy, Carbon $date, string $punchType): ?AttendanceOutsidePunchPermission
    {
        $dateString = $date->toDateString();

        $userPermission = AttendanceOutsidePunchPermission::query()
            ->where('is_active', true)
            ->where('user_id', $user->id)
            ->whereDate('start_date', '<=', $dateString)
            ->whereDate('end_date', '>=', $dateString)
            ->where($punchType === 'out' ? 'allow_punch_out' : 'allow_punch_in', true)
            ->latest('id')
            ->first();

        if ($userPermission) {
            return $userPermission;
        }

        if (!$policy || !$policy->outside_punch_permission_default_enabled) {
            return null;
        }

        return AttendanceOutsidePunchPermission::query()
            ->where('is_active', true)
            ->whereNull('user_id')
            ->where('attendance_policy_id', $policy->id)
            ->whereDate('start_date', '<=', $dateString)
            ->whereDate('end_date', '>=', $dateString)
            ->where($punchType === 'out' ? 'allow_punch_out' : 'allow_punch_in', true)
            ->latest('id')
            ->first();
    }

    public function createRequest(User $user, array $data): AttendanceOutsidePunchRequest
    {
        $requestedAt = $this->normalizeRequestedAt($data);
        $resolved = $this->policyResolver->resolveForUser($user, $requestedAt);
        $policy = $resolved['policy'];
        $office = $resolved['office'];

        $this->lockService->ensureNotFrozen($requestedAt, $office?->id);

        if (!$this->requestsAllowed($user, $policy)) {
            throw ValidationException::withMessages([
                'attendance' => 'Outside punch requests are disabled for this user.',
            ]);
        }

        $existing = AttendanceOutsidePunchRequest::query()
            ->where('user_id', $user->id)
            ->whereDate('attendance_date', $requestedAt->toDateString())
            ->where('punch_type', $data['punch_type'])
            ->whereIn('status', ['pending', 'approved', 'consumed'])
            ->latest('id')
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'attendance' => 'Outside punch request already exists for this punch.',
            ]);
        }

        $requestModel = AttendanceOutsidePunchRequest::create([
            'user_id' => $user->id,
            'attendance_date' => $requestedAt->toDateString(),
            'punch_type' => $data['punch_type'],
            'requested_at' => $requestedAt,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'office_location_id' => $data['office_location_id'] ?? $office?->id,
            'geo_distance_meters' => $data['geo_distance_meters'] ?? null,
            'reason' => $data['reason'] ?? null,
            'status' => 'pending',
        ]);

        $this->approvalService->ensureFlow($requestModel, 'hr_only');
        $this->notificationService->notifyOutsidePunchRequest($requestModel);

        return $requestModel->load(['user.role', 'officeLocation', 'approvals.actor']);
    }

    public function approveRequest(AttendanceOutsidePunchRequest $outsideRequest, User $actor, ?string $remarks = null): AttendanceOutsidePunchRequest
    {
        $decision = $this->approvalService->approve($outsideRequest, $actor, 'hr_only', $remarks);
        if ($decision !== 'approved') {
            return $outsideRequest->fresh(['user.role', 'officeLocation', 'approvals.actor']);
        }

        $result = $this->applyApprovedRequest($outsideRequest);

        $outsideRequest->update([
            'status' => 'consumed',
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'remarks' => $remarks,
            'attendance_event_id' => $result['event']->id,
            'attendance_record_id' => $result['record']->id,
        ]);

        return $outsideRequest->fresh(['user.role', 'officeLocation', 'approvals.actor', 'approver']);
    }

    public function rejectRequest(AttendanceOutsidePunchRequest $outsideRequest, User $actor, ?string $remarks = null): AttendanceOutsidePunchRequest
    {
        $this->approvalService->reject($outsideRequest, $actor, 'hr_only', $remarks);

        $outsideRequest->update([
            'status' => 'rejected',
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'remarks' => $remarks,
        ]);

        return $outsideRequest->fresh(['user.role', 'officeLocation', 'approvals.actor', 'approver']);
    }

    public function applyApprovedRequest(AttendanceOutsidePunchRequest $outsideRequest): array
    {
        $user = $outsideRequest->user()->firstOrFail();
        $requestedAt = $outsideRequest->requested_at
            ? $this->toAppTimezone($outsideRequest->requested_at)
            : Carbon::parse($outsideRequest->attendance_date, config('app.timezone'));
        $resolved = $this->policyResolver->resolveForUser($user, $requestedAt);
        $policy = $resolved['policy'];
        $office = $resolved['office'];

        $this->lockService->ensureNotFrozen($requestedAt, $office?->id);

        $event = AttendanceEvent::create([
            'user_id' => $user->id,
            'event_date' => $requestedAt->toDateString(),
            'event_type' => $outsideRequest->punch_type === 'out' ? AttendanceEvent::TYPE_PUNCH_OUT : AttendanceEvent::TYPE_PUNCH_IN,
            'event_time' => $requestedAt,
            'source' => 'outside_request_approved',
            'latitude' => $outsideRequest->latitude,
            'longitude' => $outsideRequest->longitude,
            'office_location_id' => $outsideRequest->office_location_id ?: $office?->id,
            'geo_distance_meters' => $outsideRequest->geo_distance_meters,
            'inside_geo_fence' => false,
            'meta_json' => [
                'outside_punch' => 'approved_request',
                'outside_punch_request_id' => $outsideRequest->id,
            ],
        ]);

        $record = AttendanceRecord::query()
            ->where('user_id', $user->id)
            ->whereDate('attendance_date', $requestedAt->toDateString())
            ->first();

        if ($outsideRequest->punch_type === 'in') {
            $record = $this->finalizerService->finalizeUserForDate($user, $requestedAt);
            $record->forceFill($this->onlyExistingAttendanceColumns([
                'outside_punch_status' => 'approved_request',
                'outside_punch_distance_meters' => $outsideRequest->geo_distance_meters,
                'finalized_at' => now(),
            ]))->save();
        } else {
            if (!$record || !$record->first_punch_in_at) {
                throw ValidationException::withMessages([
                    'attendance' => 'Punch-in record is required before approving outside punch-out.',
                ]);
            }

            $record = $this->finalizerService->finalizeUserForDate($user, $requestedAt);
            $record->forceFill($this->onlyExistingAttendanceColumns([
                'outside_punch_status' => 'approved_request',
                'outside_punch_distance_meters' => $outsideRequest->geo_distance_meters,
                'finalized_at' => now(),
            ]))->save();
        }

        $this->overtimeService->syncForRecord($record->fresh(['user', 'attendancePolicy']));

        return [
            'event' => $event,
            'record' => $record->fresh(),
        ];
    }

    public function applyOutsideFlags(array $flags, ?string $outsideStatus): array
    {
        if (!$outsideStatus) {
            return $flags;
        }

        $flags = array_values(array_filter($flags, fn ($flag) => $flag !== 'outside_radius'));
        $flags[] = 'outside_punch_' . $outsideStatus;

        return array_values(array_unique($flags));
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

    private function normalizeRequestedAt(array $data): Carbon
    {
        $appTimezone = config('app.timezone');
        $clientTimezone = $data['client_timezone'] ?? $appTimezone;
        $rawRequestedAt = $data['requested_at'] ?? null;

        if (empty($rawRequestedAt)) {
            return now($appTimezone);
        }

        $rawRequestedAt = trim((string) $rawRequestedAt);

        // Old clients may still send ISO strings in UTC via toISOString().
        if (preg_match('/(Z|[+\-]\d{2}:\d{2})$/', $rawRequestedAt) === 1) {
            return Carbon::parse($rawRequestedAt)->setTimezone($appTimezone);
        }

        return Carbon::parse($rawRequestedAt, $clientTimezone)->setTimezone($appTimezone);
    }

    private function toAppTimezone(CarbonInterface $dateTime): Carbon
    {
        return Carbon::instance($dateTime->copy())->setTimezone(config('app.timezone'));
    }
}

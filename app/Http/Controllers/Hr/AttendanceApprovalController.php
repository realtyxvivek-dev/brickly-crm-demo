<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\AttendanceEvent;
use App\Models\AttendanceOutsidePunchRequest;
use App\Models\AttendanceOutsidePunchPermission;
use App\Models\AttendanceRegularization;
use App\Models\AttendanceRecord;
use App\Models\LeaveRequest;
use App\Models\UserAttendanceProfile;
use App\Services\AttendanceApprovalService;
use App\Services\AttendanceAccessService;
use App\Services\AttendanceLockService;
use App\Services\AttendanceOutsidePunchService;
use App\Services\AttendancePolicyResolver;
use Carbon\Carbon;
use App\Services\LeaveService;
use App\Services\RegularizationService;
use Illuminate\Http\Request;

class AttendanceApprovalController extends Controller
{
    private const DESK_DIRECT_ALLOW_REASON = '[desk-direct-allow]';

    public function __construct(
        protected AttendanceApprovalService $approvalService,
        protected AttendanceAccessService $attendanceAccessService,
        protected AttendancePolicyResolver $policyResolver,
        protected LeaveService $leaveService,
        protected RegularizationService $regularizationService,
        protected AttendanceLockService $lockService,
        protected AttendanceOutsidePunchService $outsidePunchService
    ) {
    }

    public function leaves(Request $request)
    {
        $pendingLeaves = LeaveRequest::with(['user.role', 'leaveType', 'approvals.actor'])
            ->where('status', 'pending')
            ->whereIn('user_id', $this->attendanceAccessService->enabledProfilesQuery(now())->select('user_id'))
            ->latest()
            ->get();

        return view('hr-manager.attendance.leaves', compact('pendingLeaves'));
    }

    public function regularizations(Request $request)
    {
        $enabledUserIds = $this->attendanceAccessService->enabledProfilesQuery(now())->select('user_id');

        $pendingRegularizations = AttendanceRegularization::with(['user.role', 'approvals.actor'])
            ->where('status', 'pending')
            ->whereIn('user_id', $enabledUserIds)
            ->latest()
            ->get();

        $totalPendingRegularizations = AttendanceRegularization::query()
            ->where('status', 'pending')
            ->count();
        $hiddenPendingRegularizations = max(0, $totalPendingRegularizations - $pendingRegularizations->count());

        return view('hr-manager.attendance.regularizations', compact(
            'pendingRegularizations',
            'totalPendingRegularizations',
            'hiddenPendingRegularizations'
        ));
    }

    public function outsidePunches(Request $request)
    {
        $selectedDate = $request->filled('date')
            ? Carbon::parse($request->input('date'))->startOfDay()
            : now()->startOfDay();
        $search = trim((string) $request->input('search', ''));
        $officeId = $request->input('office_id');
        $statusFilter = (string) $request->input('status', 'all');
        $onlyOutside = $request->boolean('only_outside');
        $onlyPendingRequests = $request->boolean('only_pending_requests');
        $windowUserId = $request->integer('window_user');

        $profiles = $this->attendanceAccessService->enabledProfilesQuery($selectedDate)
            ->with(['user.role', 'officeLocation', 'attendancePolicy'])
            ->whereHas('user', function ($query) use ($search) {
                $query->where('is_active', true);
                if ($search !== '') {
                    $query->where('name', 'like', '%' . $search . '%');
                }
            })
            ->when($officeId, fn ($query) => $query->where('office_location_id', $officeId))
            ->get()
            ->sortBy(fn ($profile) => strtolower((string) $profile->user?->name))
            ->values();

        $userIds = $profiles->pluck('user_id')->filter()->values();

        $activePermissions = AttendanceOutsidePunchPermission::with('creator')
            ->whereIn('user_id', $userIds)
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $selectedDate->toDateString())
            ->whereDate('end_date', '>=', $selectedDate->toDateString())
            ->orderByDesc('id')
            ->get()
            ->groupBy('user_id');

        $pendingRequests = AttendanceOutsidePunchRequest::with(['user.role', 'officeLocation', 'approvals.actor'])
            ->where('status', 'pending')
            ->whereIn('user_id', $userIds)
            ->when($officeId, fn ($query) => $query->where('office_location_id', $officeId))
            ->when($search !== '', function ($query) use ($search) {
                $query->whereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', '%' . $search . '%'));
            })
            ->latest('requested_at')
            ->get();

        $recentRequests = AttendanceOutsidePunchRequest::with(['user.role', 'officeLocation', 'approver'])
            ->whereIn('status', ['consumed', 'rejected'])
            ->whereIn('user_id', $userIds)
            ->when($officeId, fn ($query) => $query->where('office_location_id', $officeId))
            ->when($search !== '', function ($query) use ($search) {
                $query->whereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', '%' . $search . '%'));
            })
            ->latest('requested_at')
            ->limit($onlyPendingRequests ? 10 : 20)
            ->get();

        $photoEvents = AttendanceEvent::with(['photo', 'officeLocation'])
            ->whereIn('user_id', $userIds)
            ->whereDate('event_date', $selectedDate->toDateString())
            ->whereIn('event_type', [AttendanceEvent::TYPE_PUNCH_IN, AttendanceEvent::TYPE_PUNCH_OUT])
            ->whereNotNull('photo_id')
            ->orderBy('event_time')
            ->get()
            ->groupBy('user_id');

        $records = AttendanceRecord::with('officeLocation')
            ->whereIn('user_id', $userIds)
            ->whereDate('attendance_date', $selectedDate->toDateString())
            ->get()
            ->keyBy('user_id');

        $userRows = $profiles->map(function (UserAttendanceProfile $profile) use ($selectedDate, $activePermissions, $records, $windowUserId) {
            $permissions = $activePermissions->get($profile->user_id, collect());
            $requestAllowed = $this->outsidePunchService->requestsAllowed($profile->user, $profile->attendancePolicy);
            $deskDirectPermission = $permissions->first(fn ($permission) => str_starts_with((string) $permission->reason, self::DESK_DIRECT_ALLOW_REASON));
            $windowPermission = $permissions->first(fn ($permission) => !str_starts_with((string) $permission->reason, self::DESK_DIRECT_ALLOW_REASON));
            $record = $records->get($profile->user_id);
            $hasDirectAllow = $deskDirectPermission !== null;
            $status = 'Strict';

            if ($hasDirectAllow && $requestAllowed) {
                $status = 'Direct + Request';
            } elseif ($hasDirectAllow) {
                $status = 'Direct Allow';
            } elseif ($windowPermission) {
                $status = 'Window Active';
            } elseif ($requestAllowed) {
                $status = 'Request Allowed';
            }

            return [
                'profile' => $profile,
                'request_allowed' => $requestAllowed,
                'direct_allow' => $hasDirectAllow,
                'current_permission' => $windowPermission,
                'desk_direct_permission' => $deskDirectPermission,
                'record' => $record,
                'status_label' => $status,
                'is_outside_today' => in_array($record?->outside_punch_status, ['approved_permission', 'approved_request'], true)
                    || (($record?->outside_punch_distance_meters ?? 0) > 0),
                'window_open' => $profile->user_id === $windowUserId,
            ];
        })->filter(function (array $row) use ($statusFilter) {
            return match ($statusFilter) {
                'request' => $row['request_allowed'] && !$row['direct_allow'],
                'direct' => $row['direct_allow'],
                'strict' => !$row['request_allowed'] && !$row['direct_allow'],
                default => true,
            };
        })->values();

        $photoRows = $userRows->map(function (array $row) use ($photoEvents, $selectedDate) {
            $profile = $row['profile'];
            $userEventSet = $photoEvents->get($profile->user_id, collect());
            $punchInEvent = $userEventSet->where('event_type', AttendanceEvent::TYPE_PUNCH_IN)->sortByDesc('event_time')->first();
            $punchOutEvent = $userEventSet->where('event_type', AttendanceEvent::TYPE_PUNCH_OUT)->sortByDesc('event_time')->first();
            $record = $row['record'];
            $outsideLabel = match ($record?->outside_punch_status) {
                'approved_permission' => 'Outside Allowed',
                'approved_request' => 'Outside Approved',
                default => (($record?->outside_punch_distance_meters ?? null) ? 'Outside' : 'Inside'),
            };

            return [
                'profile' => $profile,
                'punch_in_event' => $punchInEvent,
                'punch_out_event' => $punchOutEvent,
                'record' => $record,
                'outside_label' => $outsideLabel,
                'distance_label' => $record?->outside_punch_distance_meters === null
                    ? '--'
                    : ($record->outside_punch_distance_meters >= 1000
                        ? number_format($record->outside_punch_distance_meters / 1000, 2) . ' km'
                        : number_format($record->outside_punch_distance_meters, 0) . ' m'),
                'date' => $selectedDate,
            ];
        })->filter(function (array $row) use ($onlyOutside) {
            if (!$onlyOutside) {
                return true;
            }

            return $row['outside_label'] !== 'Inside';
        })->values();

        $summary = [
            'pending' => $pendingRequests->count(),
            'approved_today' => $recentRequests->filter(fn ($item) => $item->status === 'consumed' && optional($item->approved_at)->isSameDay($selectedDate))->count(),
            'rejected' => $recentRequests->where('status', 'rejected')->count(),
            'outside_enabled_users' => $userRows->filter(fn ($row) => $row['request_allowed'] || $row['direct_allow'])->count(),
        ];

        $officeOptions = $profiles->pluck('officeLocation')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();

        return view('hr-manager.attendance.outside-punches', compact(
            'pendingRequests',
            'recentRequests',
            'userRows',
            'photoRows',
            'selectedDate',
            'summary',
            'officeOptions',
            'search',
            'officeId',
            'statusFilter',
            'onlyOutside',
            'onlyPendingRequests',
            'windowUserId'
        ));
    }

    public function toggleOutsidePunchRequest(Request $request, UserAttendanceProfile $mapping)
    {
        $mapping->update([
            'allow_outside_punch_requests' => $request->boolean('enabled'),
        ]);

        return back()->with('success', 'Outside request access updated.');
    }

    public function toggleOutsidePunchDirectAllow(Request $request, UserAttendanceProfile $mapping)
    {
        if ($request->boolean('enabled')) {
            $permission = AttendanceOutsidePunchPermission::query()
                ->where('user_id', $mapping->user_id)
                ->where('reason', 'like', self::DESK_DIRECT_ALLOW_REASON . '%')
                ->latest('id')
                ->first();

            if (!$permission) {
                $permission = new AttendanceOutsidePunchPermission();
                $permission->user_id = $mapping->user_id;
                $permission->created_by = $request->user()?->id;
            }

            $permission->fill([
                'attendance_policy_id' => null,
                'start_date' => now()->toDateString(),
                'end_date' => '2099-12-31',
                'allow_punch_in' => true,
                'allow_punch_out' => true,
                'reason' => self::DESK_DIRECT_ALLOW_REASON . ' Enabled from HR desk',
                'is_active' => true,
            ]);
            $permission->save();
        } else {
            AttendanceOutsidePunchPermission::query()
                ->where('user_id', $mapping->user_id)
                ->where('reason', 'like', self::DESK_DIRECT_ALLOW_REASON . '%')
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'end_date' => now()->toDateString(),
                ]);
        }

        return back()->with('success', 'Direct outside allow updated.');
    }

    public function saveOutsidePunchWindow(Request $request, UserAttendanceProfile $mapping)
    {
        $validated = $request->validate([
            'permission_id' => 'nullable|integer',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'allow_punch_in' => 'nullable|boolean',
            'allow_punch_out' => 'nullable|boolean',
            'reason' => 'nullable|string|max:2000',
        ]);

        $allowPunchIn = $request->boolean('allow_punch_in');
        $allowPunchOut = $request->boolean('allow_punch_out');

        if (!$allowPunchIn && !$allowPunchOut) {
            return back()->withErrors(['window' => 'Select punch in or punch out for the allowance window.']);
        }

        $permission = AttendanceOutsidePunchPermission::query()
            ->where('user_id', $mapping->user_id)
            ->when($request->filled('permission_id'), fn ($query) => $query->where('id', $request->integer('permission_id')))
            ->latest('id')
            ->first();

        if (!$permission) {
            $permission = new AttendanceOutsidePunchPermission();
            $permission->user_id = $mapping->user_id;
            $permission->created_by = $request->user()?->id;
        }

        $permission->fill([
            'attendance_policy_id' => null,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'allow_punch_in' => $allowPunchIn,
            'allow_punch_out' => $allowPunchOut,
            'reason' => $validated['reason'] ?: 'Outside punch window from HR desk',
            'is_active' => true,
        ]);
        $permission->save();

        return back()->with('success', 'Outside punch window saved.');
    }

    public function approveLeave(Request $request, LeaveRequest $leaveRequest)
    {
        try {
            $current = $leaveRequest->from_date->copy();
            while ($current->lte($leaveRequest->to_date)) {
                $resolved = $this->policyResolver->resolveForUser($leaveRequest->user, $current);
                $this->lockService->ensureNotFrozen($current, $resolved['office']?->id);
                $current->addDay();
            }

            $mode = $this->approvalService->modeForPolicy(
                $this->policyResolver->resolveForUser($leaveRequest->user, $leaveRequest->from_date)['policy'],
                'leave'
            );

            $decision = $this->approvalService->approve($leaveRequest, $request->user(), $mode, $request->input('remarks'));
            if ($decision === 'approved') {
                $leaveRequest->update(['status' => 'approved', 'final_approved_at' => now()]);
                $this->leaveService->applyApprovedLeave($leaveRequest->fresh(['user', 'leaveType']));
            }

            return back()->with('success', $decision === 'approved' ? 'Leave request approved.' : 'Approval recorded, waiting for remaining approver.');
        } catch (\InvalidArgumentException $exception) {
            return back()->withErrors([
                'approval' => $exception->getMessage(),
            ]);
        }
    }

    public function rejectLeave(Request $request, LeaveRequest $leaveRequest)
    {
        try {
            $mode = $this->approvalService->modeForPolicy(
                $this->policyResolver->resolveForUser($leaveRequest->user, $leaveRequest->from_date)['policy'],
                'leave'
            );

            $this->approvalService->reject($leaveRequest, $request->user(), $mode, $request->input('remarks'));
            $leaveRequest->update(['status' => 'rejected']);

            return back()->with('success', 'Leave request rejected.');
        } catch (\InvalidArgumentException $exception) {
            return back()->withErrors([
                'approval' => $exception->getMessage(),
            ]);
        }
    }

    public function approveRegularization(Request $request, AttendanceRegularization $regularization)
    {
        try {
            $resolved = $this->policyResolver->resolveForUser($regularization->user, $regularization->attendance_date);
            $this->lockService->ensureNotFrozen($regularization->attendance_date, $resolved['office']?->id);

            $mode = $this->approvalService->modeForPolicy(
                $resolved['policy'],
                'regularization'
            );

            $decision = $this->approvalService->approve($regularization, $request->user(), $mode, $request->input('remarks'));
            if ($decision === 'approved') {
                $regularization->update(['status' => 'approved', 'final_approved_at' => now()]);
                $this->regularizationService->applyApprovedRegularization($regularization->fresh());
            }

            return back()->with('success', $decision === 'approved' ? 'Regularization approved.' : 'Approval recorded, waiting for remaining approver.');
        } catch (\InvalidArgumentException $exception) {
            return back()->withErrors([
                'approval' => $exception->getMessage(),
            ]);
        }
    }

    public function rejectRegularization(Request $request, AttendanceRegularization $regularization)
    {
        try {
            $mode = $this->approvalService->modeForPolicy(
                $this->policyResolver->resolveForUser($regularization->user, $regularization->attendance_date)['policy'],
                'regularization'
            );

            $this->approvalService->reject($regularization, $request->user(), $mode, $request->input('remarks'));
            $regularization->update(['status' => 'rejected']);

            return back()->with('success', 'Regularization rejected.');
        } catch (\InvalidArgumentException $exception) {
            return back()->withErrors([
                'approval' => $exception->getMessage(),
            ]);
        }
    }

    public function approveOutsidePunch(Request $request, AttendanceOutsidePunchRequest $outsidePunchRequest)
    {
        $this->outsidePunchService->approveRequest($outsidePunchRequest, $request->user(), $request->input('remarks'));

        return back()->with('success', 'Outside punch request approved.');
    }

    public function rejectOutsidePunch(Request $request, AttendanceOutsidePunchRequest $outsidePunchRequest)
    {
        $this->outsidePunchService->rejectRequest($outsidePunchRequest, $request->user(), $request->input('remarks'));

        return back()->with('success', 'Outside punch request rejected.');
    }
}

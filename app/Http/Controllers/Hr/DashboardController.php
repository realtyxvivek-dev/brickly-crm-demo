<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\AttendanceEvent;
use App\Models\AttendanceFaceReview;
use App\Models\AttendanceOvertime;
use App\Models\AttendanceRecord;
use App\Models\EmployeeAsset;
use App\Models\EmployeeExitWorkflow;
use App\Models\EmployeeProfile;
use App\Models\Incentive;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\Meeting;
use App\Models\AttendanceOutsidePunchRequest;
use App\Models\AttendanceRegularization;
use App\Models\LeaveRequest;
use App\Models\OfficeLocation;
use App\Models\Role;
use App\Models\SiteVisit;
use App\Models\User;
use App\Models\UserAttendanceProfile;
use App\Services\AttendanceAccessService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(protected AttendanceAccessService $attendanceAccessService)
    {
    }

    public function index(Request $request)
    {
        $date = Carbon::today();
        $officeLocationId = $request->integer('office_location_id') ?: null;
        $weeklyActivityConfig = $this->resolveWeeklyActivityDateRange($request);

        $officeOptions = OfficeLocation::query()
            ->whereIn('id', UserAttendanceProfile::query()->select('office_location_id')->distinct())
            ->orderBy('name')
            ->get(['id', 'name']);

        $trackedProfiles = UserAttendanceProfile::query()
            ->whereIn('id', $this->attendanceAccessService->enabledProfilesQuery($date)->select('id'))
            ->when($officeLocationId, fn ($query) => $query->where('office_location_id', $officeLocationId));

        $trackedUserIds = (clone $trackedProfiles)->select('user_id');

        $todayRecords = AttendanceRecord::query()
            ->whereDate('attendance_date', $date->toDateString())
            ->when($officeLocationId, fn ($query) => $query->where('office_location_id', $officeLocationId));

        $todaySummary = [
            'present' => (clone $todayRecords)->where('status', AttendanceRecord::STATUS_PRESENT)->count(),
            'late' => (clone $todayRecords)->where('status', AttendanceRecord::STATUS_LATE)->count(),
            'half_day' => (clone $todayRecords)->where('status', AttendanceRecord::STATUS_HALF_DAY)->count(),
            'absent' => (clone $todayRecords)->where('status', AttendanceRecord::STATUS_ABSENT)->count(),
            'on_leave' => (clone $todayRecords)->where('status', AttendanceRecord::STATUS_LEAVE)->count(),
        ];

        $pendingWork = [
            'leave_approvals' => LeaveRequest::query()->where('status', 'pending')->when($officeLocationId, fn ($query) => $query->whereIn('user_id', $trackedUserIds))->count(),
            'regularizations' => AttendanceRegularization::query()->where('status', 'pending')->when($officeLocationId, fn ($query) => $query->whereIn('user_id', $trackedUserIds))->count(),
            'outside_punches' => AttendanceOutsidePunchRequest::query()->where('status', 'pending')->when($officeLocationId, fn ($query) => $query->whereIn('user_id', $trackedUserIds))->count(),
            'overtimes' => AttendanceOvertime::query()->where('status', 'pending')->when($officeLocationId, fn ($query) => $query->whereIn('user_id', $trackedUserIds))->count(),
            'fraud_reviews' => AttendanceFaceReview::query()->where('status', 'pending_review')->when($officeLocationId, fn ($query) => $query->whereIn('user_id', $trackedUserIds))->count(),
        ];

        $reviewQueue = array_sum($pendingWork);

        $employeeProfiles = EmployeeProfile::query()
            ->with(['documents', 'assets'])
            ->when($officeLocationId, function ($query) use ($trackedUserIds) {
                $query->whereIn('user_id', $trackedUserIds);
            })
            ->get();

        $employeeSummary = [
            'active' => $employeeProfiles->where('employment_status', EmployeeProfile::STATUS_ACTIVE)->count(),
            'on_notice' => $employeeProfiles->where('employment_status', EmployeeProfile::STATUS_ON_NOTICE)->count(),
            'missing_docs' => $employeeProfiles->filter(fn (EmployeeProfile $profile) => count($profile->missingDocumentTypes()) > 0)->count(),
            'assets_issued' => $employeeProfiles->sum(fn (EmployeeProfile $profile) => $profile->assets->where('status', EmployeeAsset::STATUS_ISSUED)->count()),
            'probation_ending' => $employeeProfiles->filter(function (EmployeeProfile $profile) use ($date) {
                return $profile->probation_end_date && $profile->probation_end_date->between($date, $date->copy()->addDays(7));
            })->count(),
            'pending_asset_return' => $employeeProfiles->filter(fn (EmployeeProfile $profile) => $profile->assets->where('status', EmployeeAsset::STATUS_ISSUED)->isNotEmpty())->count(),
            'exit_open' => EmployeeExitWorkflow::query()
                ->when($officeLocationId, fn ($query) => $query->whereHas('employeeProfile', fn ($profile) => $profile->whereIn('user_id', $trackedUserIds)))
                ->whereNull('closed_at')
                ->count(),
            'incentive_pending' => Incentive::query()
                ->where('status', '!=', 'verified')
                ->when($officeLocationId, fn ($query) => $query->whereIn('user_id', $trackedUserIds))
                ->count(),
        ];

        $outsideOfficePunches = AttendanceEvent::query()
            ->whereDate('event_date', $date->toDateString())
            ->where('event_type', AttendanceEvent::TYPE_PUNCH_IN)
            ->where('inside_geo_fence', false)
            ->when($officeLocationId, fn ($query) => $query->where('office_location_id', $officeLocationId))
            ->count();

        $repeatedLateUsers = AttendanceRecord::query()
            ->select('user_id')
            ->whereBetween('attendance_date', [$date->copy()->subDays(6)->toDateString(), $date->toDateString()])
            ->where('status', AttendanceRecord::STATUS_LATE)
            ->when($officeLocationId, fn ($query) => $query->where('office_location_id', $officeLocationId))
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) >= 3')
            ->get()
            ->count();

        $alerts = [
            'missing_punch_in' => (clone $trackedProfiles)
                ->whereNotIn('user_id', (clone $todayRecords)->select('user_id'))
                ->count(),
            'missing_punch_out' => (clone $todayRecords)
                ->whereNotNull('first_punch_in_at')
                ->whereNull('last_punch_out_at')
                ->count(),
            'suspicious_cases' => (clone $todayRecords)
                ->where(function ($query) {
                    $query->where('is_suspicious', true)
                        ->orWhere('fraud_review_status', 'pending_review');
                })
                ->count(),
            'outside_office_punches' => $outsideOfficePunches,
            'repeated_late_users' => $repeatedLateUsers,
        ];

        $quickLinks = [
            ['label' => 'Hiring Leads', 'route' => route('hr-manager.hiring.index')],
            ['label' => 'Employees', 'route' => route('hr-manager.settings.hr.employees.index')],
            ['label' => 'Document Center', 'route' => route('hr-manager.settings.hr.document-center.index')],
            ['label' => 'Exit Cases', 'route' => route('hr-manager.settings.hr.exit-workflows.index')],
            ['label' => 'Salary Revisions', 'route' => route('hr-manager.settings.hr.salary-revisions.index')],
            ['label' => 'Incentives', 'route' => route('hr-manager.settings.hr.incentives.index')],
            ['label' => 'Monthly Attendance', 'route' => route('hr-manager.attendance.sheet', $this->queryParams($officeLocationId))],
            ['label' => 'Approve Leave', 'route' => route('hr-manager.attendance.leaves')],
            ['label' => 'Regularization', 'route' => route('hr-manager.attendance.regularizations')],
            ['label' => 'Attendance Reports', 'route' => route('hr-manager.attendance.reports', $this->queryParams($officeLocationId))],
        ];

        $reportLinks = [
            ['label' => 'Attendance Report', 'route' => route('hr-manager.attendance.reports', $this->queryParams($officeLocationId))],
            ['label' => 'Employee Master', 'route' => route('hr-manager.settings.hr.employees.index')],
            ['label' => 'Document Center', 'route' => route('hr-manager.settings.hr.document-center.index')],
            ['label' => 'Exit Cases', 'route' => route('hr-manager.settings.hr.exit-workflows.index')],
            ['label' => 'Salary Revisions', 'route' => route('hr-manager.settings.hr.salary-revisions.index')],
            ['label' => 'Incentives', 'route' => route('hr-manager.settings.hr.incentives.index')],
            ['label' => 'Leave Report', 'route' => route('hr-manager.attendance.leaves')],
            ['label' => 'Payroll Report', 'route' => route('hr-manager.attendance.reports.export.payroll', $this->queryParams($officeLocationId))],
        ];

        $registerPreview = AttendanceRecord::query()
            ->with(['user:id,name,role_id', 'user.role:id,name', 'officeLocation:id,name'])
            ->whereDate('attendance_date', $date->toDateString())
            ->when($officeLocationId, fn ($query) => $query->where('office_location_id', $officeLocationId))
            ->whereNotNull('first_punch_in_at')
            ->orderBy('first_punch_in_at')
            ->get();

        $approvalCenter = $this->buildMobileApprovalCenter($officeLocationId, (clone $trackedProfiles)->select('user_id'));
        $activityTracker = $this->buildHrActivityTracker($request);
        $hiringDashboard = $this->buildHiringDashboard($request);
        $siteVisitMonitoring = $this->buildSiteVisitMonitoring($request);
        $meetingMonitoring = $this->buildMeetingMonitoring($request);

        if ($request->string('activity_export')->toString() === 'csv') {
            return $this->downloadActivityTrackerCsv($activityTracker);
        }

        return view('hr-manager.dashboard', [
            'date' => $date,
            'officeOptions' => $officeOptions,
            'selectedOfficeLocationId' => $officeLocationId,
            'selectedOfficeName' => optional($officeOptions->firstWhere('id', $officeLocationId))->name,
            'todaySummary' => $todaySummary,
            'pendingWork' => $pendingWork,
            'reviewQueue' => $reviewQueue,
            'employeeSummary' => $employeeSummary,
            'alerts' => $alerts,
            'quickLinks' => $quickLinks,
            'reportLinks' => $reportLinks,
            'registerPreview' => $registerPreview,
            'approvalCenter' => $approvalCenter,
            'weeklyActivitySummary' => $this->getWeeklyActivitySummary(
                $weeklyActivityConfig['date_range'],
                $weeklyActivityConfig['preset']
            ),
            'weeklyActivityFilter' => $weeklyActivityConfig['preset'],
            'weeklyActivityCustomRange' => [
                'start' => $weeklyActivityConfig['preset'] === 'custom'
                    ? $weeklyActivityConfig['date_range']['start_date']->toDateString()
                    : (string) $request->get('weekly_activity_start_date', ''),
                'end' => $weeklyActivityConfig['preset'] === 'custom'
                    ? $weeklyActivityConfig['date_range']['end_date']->toDateString()
                    : (string) $request->get('weekly_activity_end_date', ''),
            ],
            'activityTracker' => $activityTracker,
            'hiringDashboard' => $hiringDashboard,
            'siteVisitMonitoring' => $siteVisitMonitoring,
            'meetingMonitoring' => $meetingMonitoring,
        ]);
    }

    private function buildMobileApprovalCenter(?int $officeLocationId, $trackedUserIds): array
    {
        $outsideRequests = AttendanceOutsidePunchRequest::query()
            ->with(['user.role', 'officeLocation'])
            ->where('status', 'pending')
            ->when($officeLocationId, fn ($query) => $query->whereIn('user_id', $trackedUserIds))
            ->latest('requested_at')
            ->limit(6)
            ->get()
            ->map(fn (AttendanceOutsidePunchRequest $request) => [
                'type' => 'outside',
                'label' => 'Manual Punch',
                'tone' => 'amber',
                'icon' => 'fa-location-dot',
                'employee' => $request->user?->name ?: 'Employee',
                'role' => $request->user?->role?->name ?: 'User',
                'meta' => ucfirst((string) $request->punch_type) . ' - ' . optional($request->requested_at)->format('d M, h:i A'),
                'detail' => trim(($request->officeLocation?->name ? $request->officeLocation->name . ' - ' : '') . $this->distanceLabel($request->geo_distance_meters) . ' from office'),
                'reason' => $request->reason ?: 'No reason added',
                'approve_route' => route('hr-manager.attendance.outside-punches.approve', $request),
                'reject_route' => route('hr-manager.attendance.outside-punches.reject', $request),
                'view_route' => route('hr-manager.attendance.outside-punches', ['only_pending_requests' => 1]),
            ]);

        $regularizations = AttendanceRegularization::query()
            ->with(['user.role'])
            ->where('status', 'pending')
            ->when($officeLocationId, fn ($query) => $query->whereIn('user_id', $trackedUserIds))
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (AttendanceRegularization $request) => [
                'type' => 'regularization',
                'label' => 'Correction Request',
                'tone' => 'violet',
                'icon' => 'fa-calendar-check',
                'employee' => $request->user?->name ?: 'Employee',
                'role' => $request->user?->role?->name ?: 'User',
                'meta' => 'Attendance for ' . $request->attendance_date?->format('d M Y') . ' - ' . ucwords(str_replace('_', ' ', (string) $request->request_type)),
                'detail' => 'Submitted ' . optional($request->created_at)->format('d M Y, h:i A') . ' | ' . trim(($request->requested_status ?: 'Status update') . ' / ' . (optional($request->requested_in_time)->format('h:i A') ?: '--') . ' / ' . (optional($request->requested_out_time)->format('h:i A') ?: '--')),
                'reason' => $request->reason ?: 'No reason added',
                'approve_route' => route('hr-manager.attendance.regularizations.approve', $request),
                'reject_route' => route('hr-manager.attendance.regularizations.reject', $request),
                'view_route' => route('hr-manager.attendance.regularizations'),
            ]);

        $leaves = LeaveRequest::query()
            ->with(['user.role', 'leaveType'])
            ->where('status', 'pending')
            ->when($officeLocationId, fn ($query) => $query->whereIn('user_id', $trackedUserIds))
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (LeaveRequest $request) => [
                'type' => 'leave',
                'label' => 'Leave',
                'tone' => 'blue',
                'icon' => 'fa-umbrella-beach',
                'employee' => $request->user?->name ?: 'Employee',
                'role' => $request->user?->role?->name ?: 'User',
                'meta' => ($request->leaveType?->name ?: 'Leave') . ' - ' . number_format((float) $request->days_requested, 2) . ' day',
                'detail' => $request->from_date?->format('d M Y') . ' to ' . $request->to_date?->format('d M Y'),
                'reason' => $request->reason ?: 'No reason added',
                'approve_route' => route('hr-manager.attendance.leaves.approve', $request),
                'reject_route' => route('hr-manager.attendance.leaves.reject', $request),
                'view_route' => route('hr-manager.attendance.leaves'),
            ]);

        $items = $outsideRequests
            ->concat($regularizations)
            ->concat($leaves)
            ->sortByDesc(fn (array $item) => $item['type'] === 'outside' ? 3 : ($item['type'] === 'regularization' ? 2 : 1))
            ->values();

        return [
            'counts' => [
                'all' => $items->count(),
                'outside' => $outsideRequests->count(),
                'regularization' => $regularizations->count(),
                'leave' => $leaves->count(),
            ],
            'items' => $items,
        ];
    }

    private function distanceLabel($meters): string
    {
        if ($meters === null || $meters === '') {
            return 'Distance not captured';
        }

        return ((float) $meters) >= 1000
            ? number_format(((float) $meters) / 1000, 2) . ' km'
            : number_format((float) $meters, 0) . ' m';
    }

    private function buildHiringDashboard(Request $request): array
    {
        $userId = (int) $request->user()->id;
        $todayStart = Carbon::today()->startOfDay();
        $todayEnd = Carbon::today()->endOfDay();
        $monthStart = Carbon::now()->startOfMonth()->startOfDay();
        $closedStatuses = ['hired', 'rejected', 'not_interested', 'not_reachable', 'wrong_number', 'duplicate'];

        $baseQuery = Lead::query()
            ->where('is_hiring_candidate', true)
            ->whereHas('activeAssignments', function ($query) use ($userId) {
                $query->where('assigned_to', $userId)->where('is_active', true);
            });

        $assignedTodayIds = LeadAssignment::query()
            ->where('assigned_to', $userId)
            ->where('is_active', true)
            ->whereBetween('assigned_at', [$todayStart, $todayEnd])
            ->pluck('lead_id');

        $summary = [
            'assigned_today' => (clone $baseQuery)->whereIn('id', $assignedTodayIds)->count(),
            'call_today' => (clone $baseQuery)
                ->whereNotIn('hiring_status', array_merge($closedStatuses, ['interview_scheduled']))
                ->whereBetween('next_followup_at', [$todayStart, $todayEnd])
                ->count(),
            'interview_today' => (clone $baseQuery)
                ->where('hiring_status', 'interview_scheduled')
                ->whereBetween('next_followup_at', [$todayStart, $todayEnd])
                ->count(),
            'pending_followup' => (clone $baseQuery)
                ->whereNotIn('hiring_status', $closedStatuses)
                ->whereNotNull('next_followup_at')
                ->where('next_followup_at', '<', now())
                ->count(),
            'hired_this_month' => (clone $baseQuery)
                ->where('hiring_status', 'hired')
                ->where('updated_at', '>=', $monthStart)
                ->count(),
        ];

        $queue = (clone $baseQuery)
            ->with(['activeAssignments' => fn ($query) => $query->where('is_active', true)->latest('assigned_at')])
            ->where(function ($query) use ($assignedTodayIds, $todayStart, $todayEnd) {
                $query->whereIn('id', $assignedTodayIds)
                    ->orWhereBetween('next_followup_at', [$todayStart, $todayEnd]);
            })
            ->latest('updated_at')
            ->limit(8)
            ->get()
            ->map(function (Lead $lead) use ($todayStart, $todayEnd, $assignedTodayIds) {
                $isInterview = $lead->hiring_status === 'interview_scheduled'
                    && $lead->next_followup_at
                    && $lead->next_followup_at->betweenIncluded($todayStart, $todayEnd);

                $isCall = !$isInterview
                    && $lead->next_followup_at
                    && $lead->next_followup_at->betweenIncluded($todayStart, $todayEnd);

                return [
                    'name' => $lead->name,
                    'phone' => $lead->phone,
                    'status' => $lead->hiring_status_label ?? 'New',
                    'action' => $isInterview ? 'Interview' : ($isCall ? 'Call' : 'New Candidate'),
                    'time' => optional($lead->next_followup_at)->format('h:i A') ?: 'Today',
                    'url' => route('hr-manager.hiring.show', $lead),
                ];
            })
            ->values()
            ->all();

        return [
            'summary' => $summary,
            'queue' => $queue,
        ];
    }

    private function buildHrActivityTracker(Request $request): array
    {
        $dateConfig = $this->resolveActivityTrackerDateRange($request);
        $teamFilter = trim((string) $request->get('activity_team', 'all'));

        $salesRoleSlugs = [
            Role::SALES_MANAGER,
            Role::SENIOR_MANAGER,
            Role::ASSISTANT_SALES_MANAGER,
            Role::SALES_EXECUTIVE,
        ];

        $baseUsers = User::query()
            ->with(['role:id,name,slug', 'manager:id,name'])
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->whereIn('slug', $salesRoleSlugs))
            ->when($teamFilter !== 'all', function ($query) use ($teamFilter) {
                if ($teamFilter === 'unassigned') {
                    $query->whereNull('manager_id');
                } elseif (ctype_digit($teamFilter)) {
                    $query->where('manager_id', (int) $teamFilter);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name', 'role_id', 'manager_id']);

        $userIds = $baseUsers->pluck('id')->all();
        $rangeStart = $dateConfig['date_range']['start_date'];
        $rangeEnd = $dateConfig['date_range']['end_date'];

        $meetingScheduled = $this->scheduledWorkflowCounts(Meeting::withQueueHidden(), $userIds, $rangeStart, $rangeEnd, ['scheduled']);
        $meetingVerified = $this->verifiedWorkflowCounts(Meeting::withQueueHidden(), $userIds, $rangeStart, $rangeEnd);
        $visitScheduled = $this->scheduledWorkflowCounts(SiteVisit::withQueueHidden(), $userIds, $rangeStart, $rangeEnd, ['scheduled', 'in_progress', 'rescheduled']);
        $visitVerified = $this->verifiedWorkflowCounts(SiteVisit::withQueueHidden(), $userIds, $rangeStart, $rangeEnd);

        $rows = $baseUsers
            ->map(function (User $user) use ($meetingScheduled, $meetingVerified, $visitScheduled, $visitVerified) {
                return [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'role_name' => optional($user->role)->name ?: 'Sales User',
                    'team_name' => optional($user->manager)->name ?: 'No Manager',
                    'meeting_scheduled' => (int) ($meetingScheduled[$user->id] ?? 0),
                    'meeting_verified' => (int) ($meetingVerified[$user->id] ?? 0),
                    'visit_scheduled' => (int) ($visitScheduled[$user->id] ?? 0),
                    'visit_verified' => (int) ($visitVerified[$user->id] ?? 0),
                ];
            })
            ->filter(fn (array $row) => ($row['meeting_scheduled'] + $row['meeting_verified'] + $row['visit_scheduled'] + $row['visit_verified']) > 0)
            ->values();

        $teamOptions = User::query()
            ->whereIn('id', User::query()
                ->where('is_active', true)
                ->whereHas('role', fn ($query) => $query->whereIn('slug', $salesRoleSlugs))
                ->whereNotNull('manager_id')
                ->select('manager_id'))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user) => ['value' => (string) $user->id, 'label' => $user->name])
            ->values()
            ->all();

        return [
            'filters' => [
                'date_filter' => $dateConfig['preset'],
                'team' => $teamFilter,
                'custom_start' => $dateConfig['custom_start'],
                'custom_end' => $dateConfig['custom_end'],
            ],
            'range_label' => $rangeStart->format('d M Y') . ' - ' . $rangeEnd->format('d M Y'),
            'date_options' => [
                'today' => 'Today',
                'previous_day' => 'Previous Day',
                'this_week' => 'This Week',
                'this_month' => 'This Month',
                'custom' => 'Custom',
            ],
            'team_options' => $teamOptions,
            'summary' => [
                'meeting_scheduled' => (int) $rows->sum('meeting_scheduled'),
                'meeting_verified' => (int) $rows->sum('meeting_verified'),
                'visit_scheduled' => (int) $rows->sum('visit_scheduled'),
                'visit_verified' => (int) $rows->sum('visit_verified'),
            ],
            'rows' => $rows->all(),
        ];
    }

    private function buildSiteVisitMonitoring(Request $request): array
    {
        return $this->buildSalesActivityMonitoring($request, SiteVisit::class, 'site_visit');
    }

    private function buildMeetingMonitoring(Request $request): array
    {
        return $this->buildSalesActivityMonitoring($request, Meeting::class, 'meeting');
    }

    private function buildSalesActivityMonitoring(Request $request, string $modelClass, string $filterPrefix): array
    {
        $now = Carbon::now();
        $dateConfig = $this->resolveSalesActivityMonitoringDateRange($request, $filterPrefix);
        $rangeStart = $dateConfig['date_range']['start_date'];
        $rangeEnd = $dateConfig['date_range']['end_date'];

        $salesRoleSlugs = [
            Role::SALES_MANAGER,
            Role::SENIOR_MANAGER,
            Role::ASSISTANT_SALES_MANAGER,
            Role::SALES_EXECUTIVE,
        ];

        $salesUsers = User::query()
            ->with(['role:id,name,slug', 'manager:id,name'])
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->whereIn('slug', $salesRoleSlugs))
            ->orderBy('name')
            ->get(['id', 'name', 'role_id', 'manager_id']);

        $visits = $modelClass::withQueueHidden()
            ->with(['lead:id,name,phone', 'assignedTo:id,name,role_id,manager_id', 'assignedTo.role:id,name,slug', 'assignedTo.manager:id,name'])
            ->where(function ($query) use ($rangeStart, $rangeEnd) {
                $query->where(function ($completedQuery) use ($rangeStart, $rangeEnd) {
                    $completedQuery->whereNotNull('completed_at')
                        ->whereBetween('completed_at', [$rangeStart, $rangeEnd]);
                })->orWhere(function ($scheduledQuery) use ($rangeStart, $rangeEnd) {
                    $scheduledQuery->whereNull('completed_at')
                        ->whereBetween('scheduled_at', [$rangeStart, $rangeEnd]);
                });
            })
            ->get(['id', 'lead_id', 'assigned_to', 'scheduled_at', 'completed_at', 'status', 'verification_status', 'is_dead']);

        $rowsByUser = [];

        $blankMetrics = fn () => [
            'scheduled' => 0,
            'completed' => 0,
            'cancelled' => 0,
            'pending' => 0,
            'overdue' => 0,
            'verified' => 0,
            'visits' => [],
            'last_completed_at' => null,
        ];

        foreach ($salesUsers as $user) {
            $rowsByUser[$user->id] = array_merge($blankMetrics(), [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'role_name' => optional($user->role)->name ?: 'Sales User',
                'asm_id' => $user->manager_id ?: 0,
                'asm_name' => optional($user->manager)->name ?: 'Unassigned ASM',
            ]);
        }

        foreach ($visits as $visit) {
            $assignedUser = $visit->assignedTo;
            $userId = $assignedUser?->id ?: 0;

            if (!isset($rowsByUser[$userId])) {
                $rowsByUser[$userId] = array_merge($blankMetrics(), [
                    'user_id' => $userId,
                    'user_name' => $assignedUser?->name ?: 'Unassigned User',
                    'role_name' => optional($assignedUser?->role)->name ?: 'Sales User',
                    'asm_id' => $assignedUser?->manager_id ?: 0,
                    'asm_name' => optional($assignedUser?->manager)->name ?: 'Unassigned ASM',
                ]);
            }

            $scheduledAt = $visit->scheduled_at;
            $completedAt = $visit->completed_at;
            $isCompleted = $visit->status === 'completed' || $completedAt !== null;
            $isCancelled = in_array($visit->status, ['cancelled', 'canceled', 'rejected'], true) || (bool) $visit->is_dead;
            $isPending = !$isCompleted && !$isCancelled;
            $visitStatus = $isCompleted ? 'Completed' : ($isCancelled ? 'Cancelled' : ($scheduledAt && $scheduledAt->lt($now) ? 'Overdue' : 'Pending'));
            $scheduledInRange = $scheduledAt && $scheduledAt->betweenIncluded($rangeStart, $rangeEnd);
            $completedInRange = $completedAt && $completedAt->betweenIncluded($rangeStart, $rangeEnd);

            if ($scheduledInRange && !$isCompleted) {
                $rowsByUser[$userId]['scheduled']++;
            }
            $rowsByUser[$userId]['visits'][] = [
                'id' => $visit->id,
                'lead_name' => $visit->lead?->name ?: 'Unknown Lead',
                'phone' => $visit->lead?->phone ?: '-',
                'status' => $visitStatus,
                'scheduled_label' => $scheduledAt ? $scheduledAt->format('d M, h:i A') : '-',
                'completed_label' => $completedAt ? $completedAt->format('d M, h:i A') : '-',
            ];

            if ($isCompleted && $completedInRange) {
                $rowsByUser[$userId]['completed']++;

                if ($visit->verification_status === 'verified') {
                    $rowsByUser[$userId]['verified']++;
                }

                if ($completedAt && (!$rowsByUser[$userId]['last_completed_at'] || $completedAt->gt($rowsByUser[$userId]['last_completed_at']))) {
                    $rowsByUser[$userId]['last_completed_at'] = $completedAt;
                }
            } elseif ($isCancelled && $scheduledInRange) {
                $rowsByUser[$userId]['cancelled']++;
            } elseif ($scheduledInRange) {
                $rowsByUser[$userId]['pending']++;

                if ($isPending && $scheduledAt && $scheduledAt->lt($now)) {
                    $rowsByUser[$userId]['overdue']++;
                }
            }
        }

        $managerRows = collect($rowsByUser)
            ->map(function (array $row) {
                $periodTotal = $row['scheduled'] + $row['completed'] + $row['cancelled'];
                $row['completion_rate'] = $periodTotal > 0
                    ? round(($row['completed'] / $periodTotal) * 100, 1)
                    : 0;
                $row['last_completed_label'] = $row['last_completed_at']
                    ? $row['last_completed_at']->format('d M, h:i A')
                    : '-';

                return $row;
            })
            ->filter(fn (array $row) => ($row['scheduled'] + $row['completed'] + $row['cancelled']) > 0)
            ->sortBy([
                ['scheduled', 'desc'],
                ['user_name', 'asc'],
            ])
            ->values();

        $asmRows = $managerRows
            ->groupBy('asm_id')
            ->map(function ($rows) {
                $first = $rows->first();
                $totals = [
                    'scheduled' => (int) $rows->sum('scheduled'),
                    'completed' => (int) $rows->sum('completed'),
                    'pending' => (int) $rows->sum('pending'),
                    'overdue' => (int) $rows->sum('overdue'),
                    'cancelled' => (int) $rows->sum('cancelled'),
                    'verified' => (int) $rows->sum('verified'),
                ];

                $periodTotal = $totals['scheduled'] + $totals['completed'] + $totals['cancelled'];
                $totals['completion_rate'] = $periodTotal > 0
                    ? round(($totals['completed'] / $periodTotal) * 100, 1)
                    : 0;

                return [
                    'asm_id' => $first['asm_id'],
                    'asm_name' => $first['asm_name'],
                    'totals' => $totals,
                    'sales_managers' => $rows->values()->all(),
                ];
            })
            ->sortByDesc(fn (array $row) => $row['totals']['scheduled'])
            ->values()
            ->all();

        return [
            'range_label' => $rangeStart->format('d M Y') . ' - ' . $rangeEnd->format('d M Y'),
            'filters' => [
                'preset' => $dateConfig['preset'],
                'custom_start' => $dateConfig['custom_start'],
                'custom_end' => $dateConfig['custom_end'],
            ],
            'date_options' => [
                'today' => 'Today',
                'yesterday' => 'Yesterday',
                'this_week' => 'This Week',
                'last_week' => 'Last Week',
                'this_month' => 'This Month',
                'last_month' => 'Last Month',
                'custom' => 'Custom Range',
            ],
            'summary' => [
                'scheduled' => (int) $managerRows->sum('scheduled'),
                'completed' => (int) $managerRows->sum('completed'),
                'pending' => (int) $managerRows->sum('pending'),
                'overdue' => (int) $managerRows->sum('overdue'),
                'verified' => (int) $managerRows->sum('verified'),
                'cancelled' => (int) $managerRows->sum('cancelled'),
            ],
            'manager_rows' => $managerRows->all(),
            'asm_rows' => $asmRows,
        ];
    }

    private function resolveSalesActivityMonitoringDateRange(Request $request, string $filterPrefix): array
    {
        $preset = trim((string) $request->get($filterPrefix . '_period', 'this_month'));
        $allowedPresets = ['today', 'yesterday', 'this_week', 'last_week', 'this_month', 'last_month', 'custom'];

        if (!in_array($preset, $allowedPresets, true)) {
            $preset = 'this_month';
        }

        $customStart = trim((string) $request->get($filterPrefix . '_start_date', ''));
        $customEnd = trim((string) $request->get($filterPrefix . '_end_date', ''));

        if ($preset === 'custom' && $customStart !== '' && $customEnd !== '') {
            return [
                'preset' => 'custom',
                'custom_start' => $customStart,
                'custom_end' => $customEnd,
                'date_range' => [
                    'start_date' => Carbon::parse($customStart)->startOfDay(),
                    'end_date' => Carbon::parse($customEnd)->endOfDay(),
                ],
            ];
        }

        if ($preset === 'custom') {
            $preset = 'this_month';
        }

        return [
            'preset' => $preset,
            'custom_start' => $customStart,
            'custom_end' => $customEnd,
            'date_range' => match ($preset) {
                'today' => [
                    'start_date' => Carbon::now()->startOfDay(),
                    'end_date' => Carbon::now()->endOfDay(),
                ],
                'yesterday' => [
                    'start_date' => Carbon::now()->subDay()->startOfDay(),
                    'end_date' => Carbon::now()->subDay()->endOfDay(),
                ],
                'this_week' => [
                    'start_date' => Carbon::now()->startOfWeek()->startOfDay(),
                    'end_date' => Carbon::now()->endOfWeek()->endOfDay(),
                ],
                'last_week' => [
                    'start_date' => Carbon::now()->subWeek()->startOfWeek()->startOfDay(),
                    'end_date' => Carbon::now()->subWeek()->endOfWeek()->endOfDay(),
                ],
                'last_month' => [
                    'start_date' => Carbon::now()->subMonthNoOverflow()->startOfMonth()->startOfDay(),
                    'end_date' => Carbon::now()->subMonthNoOverflow()->endOfMonth()->endOfDay(),
                ],
                default => [
                    'start_date' => Carbon::now()->startOfMonth()->startOfDay(),
                    'end_date' => Carbon::now()->endOfMonth()->endOfDay(),
                ],
            },
        ];
    }

    private function resolveActivityTrackerDateRange(Request $request): array
    {
        $preset = trim((string) $request->get('activity_date_filter', 'this_month'));
        $allowedPresets = ['today', 'previous_day', 'this_week', 'this_month', 'custom'];

        if (!in_array($preset, $allowedPresets, true)) {
            $preset = 'this_month';
        }

        $customStart = trim((string) $request->get('activity_start_date', ''));
        $customEnd = trim((string) $request->get('activity_end_date', ''));

        if ($preset === 'custom' && $customStart !== '' && $customEnd !== '') {
            return [
                'preset' => 'custom',
                'custom_start' => $customStart,
                'custom_end' => $customEnd,
                'date_range' => [
                    'start_date' => Carbon::parse($customStart)->startOfDay(),
                    'end_date' => Carbon::parse($customEnd)->endOfDay(),
                ],
            ];
        }

        if ($preset === 'custom') {
            $preset = 'this_month';
        }

        return [
            'preset' => $preset,
            'custom_start' => $customStart,
            'custom_end' => $customEnd,
            'date_range' => match ($preset) {
                'today' => [
                    'start_date' => Carbon::now()->startOfDay(),
                    'end_date' => Carbon::now()->endOfDay(),
                ],
                'previous_day' => [
                    'start_date' => Carbon::now()->subDay()->startOfDay(),
                    'end_date' => Carbon::now()->subDay()->endOfDay(),
                ],
                'this_week' => [
                    'start_date' => Carbon::now()->startOfWeek()->startOfDay(),
                    'end_date' => Carbon::now()->endOfWeek()->endOfDay(),
                ],
                default => [
                    'start_date' => Carbon::now()->startOfMonth()->startOfDay(),
                    'end_date' => Carbon::now()->endOfMonth()->endOfDay(),
                ],
            },
        ];
    }

    private function scheduledWorkflowCounts($query, array $userIds, Carbon $rangeStart, Carbon $rangeEnd, array $statuses): array
    {
        if (empty($userIds)) {
            return [];
        }

        return $query
            ->selectRaw('assigned_to, COUNT(*) as total')
            ->whereIn('assigned_to', $userIds)
            ->whereIn('status', $statuses)
            ->whereNull('completed_at')
            ->whereBetween('scheduled_at', [$rangeStart, $rangeEnd])
            ->groupBy('assigned_to')
            ->pluck('total', 'assigned_to')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    private function verifiedWorkflowCounts($query, array $userIds, Carbon $rangeStart, Carbon $rangeEnd): array
    {
        if (empty($userIds)) {
            return [];
        }

        return $query
            ->selectRaw('assigned_to, COUNT(*) as total')
            ->whereIn('assigned_to', $userIds)
            ->where('status', 'completed')
            ->where('verification_status', 'verified')
            ->where(function ($dateQuery) use ($rangeStart, $rangeEnd) {
                $dateQuery->whereBetween('verified_at', [$rangeStart, $rangeEnd])
                    ->orWhere(function ($fallbackQuery) use ($rangeStart, $rangeEnd) {
                        $fallbackQuery->whereNull('verified_at')
                            ->whereBetween('completed_at', [$rangeStart, $rangeEnd]);
                    });
            })
            ->groupBy('assigned_to')
            ->pluck('total', 'assigned_to')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    private function downloadActivityTrackerCsv(array $activityTracker)
    {
        $filename = 'hr-activity-tracker-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($activityTracker) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['User', 'Role', 'Team', 'Meet Sch.', 'Meet Ver.', 'Visit Sch.', 'Visit Ver.']);

            foreach ($activityTracker['rows'] as $row) {
                fputcsv($handle, [
                    $row['user_name'],
                    $row['role_name'],
                    $row['team_name'],
                    $row['meeting_scheduled'],
                    $row['meeting_verified'],
                    $row['visit_scheduled'],
                    $row['visit_verified'],
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function resolveWeeklyActivityDateRange(Request $request): array
    {
        $preset = trim((string) $request->get('weekly_activity_filter', 'this_week'));
        $allowedPresets = ['previous_week', 'this_week', 'next_week', 'this_month', 'custom'];

        if (!in_array($preset, $allowedPresets, true)) {
            $preset = 'this_week';
        }

        if ($preset === 'custom') {
            $startDate = trim((string) $request->get('weekly_activity_start_date', ''));
            $endDate = trim((string) $request->get('weekly_activity_end_date', ''));

            if ($startDate !== '' && $endDate !== '') {
                return [
                    'preset' => 'custom',
                    'date_range' => [
                        'start_date' => Carbon::parse($startDate)->startOfDay(),
                        'end_date' => Carbon::parse($endDate)->endOfDay(),
                    ],
                ];
            }

            $preset = 'this_week';
        }

        return [
            'preset' => $preset,
            'date_range' => $this->getPresetDateRange($preset),
        ];
    }

    private function getWeeklyActivitySummary(array $dateRange, string $preset = 'this_week'): array
    {
        $rangeStart = $dateRange['start_date']->copy()->startOfDay();
        $rangeEnd = $dateRange['end_date']->copy()->endOfDay();

        return [
            'range_label' => $rangeStart->format('d M') . ' - ' . $rangeEnd->format('d M'),
            'filter_label' => $this->getWeeklyActivityFilterLabel($preset),
            'applied_filter' => $preset,
            'meetings' => $this->buildWeeklyWorkflowSummary(Meeting::query(), $rangeStart, $rangeEnd),
            'visits' => $this->buildWeeklyWorkflowSummary(SiteVisit::query(), $rangeStart, $rangeEnd),
        ];
    }

    private function buildWeeklyWorkflowSummary($query, Carbon $rangeStart, Carbon $rangeEnd): array
    {
        $scheduledQuery = (clone $query)->whereBetween('scheduled_at', [$rangeStart, $rangeEnd]);

        $scheduled = (int) $scheduledQuery->count();
        $completed = (int) (clone $scheduledQuery)
            ->where(function ($statusQuery) {
                $statusQuery->where('status', 'completed')
                    ->orWhereNotNull('completed_at');
            })
            ->count();

        $pending = (int) max($scheduled - $completed, 0);
        $completionRate = $scheduled > 0 ? round(($completed / $scheduled) * 100) : 0;

        return [
            'scheduled' => $scheduled,
            'completed' => $completed,
            'pending' => $pending,
            'completion_rate' => $completionRate,
        ];
    }

    private function getWeeklyActivityFilterLabel(string $preset): string
    {
        return match ($preset) {
            'previous_week' => 'Previous Week',
            'next_week' => 'Next Week',
            'this_month' => 'This Month',
            'custom' => 'Custom Range',
            default => 'This Week',
        };
    }

    private function getPresetDateRange(string $preset): array
    {
        return match ($preset) {
            'previous_week' => [
                'start_date' => Carbon::now()->subWeek()->startOfWeek()->startOfDay(),
                'end_date' => Carbon::now()->subWeek()->endOfWeek()->endOfDay(),
            ],
            'this_week', 'week' => [
                'start_date' => Carbon::now()->startOfWeek()->startOfDay(),
                'end_date' => Carbon::now()->endOfWeek()->endOfDay(),
            ],
            'next_week' => [
                'start_date' => Carbon::now()->addWeek()->startOfWeek()->startOfDay(),
                'end_date' => Carbon::now()->addWeek()->endOfWeek()->endOfDay(),
            ],
            'this_month', 'month' => [
                'start_date' => Carbon::now()->startOfMonth()->startOfDay(),
                'end_date' => Carbon::now()->endOfMonth()->endOfDay(),
            ],
            default => [
                'start_date' => Carbon::now()->startOfWeek()->startOfDay(),
                'end_date' => Carbon::now()->endOfWeek()->endOfDay(),
            ],
        };
    }

    private function queryParams(?int $officeLocationId): array
    {
        return array_filter([
            'office_location_id' => $officeLocationId,
        ]);
    }
}

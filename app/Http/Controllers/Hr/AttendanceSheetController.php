<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\EmployeeProfile;
use App\Models\Meeting;
use App\Models\OfficeLocation;
use App\Models\SiteVisit;
use App\Models\User;
use App\Models\UserAttendanceProfile;
use App\Services\AttendanceAccessService;
use App\Services\AttendanceFinalizerService;
use App\Services\AttendanceRecordSyncService;
use App\Services\ProductiveDayTrackerService;
use App\Services\VerificationRoutingService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AttendanceSheetController extends Controller
{
    private const ALLOWED_STATUSES = [
        AttendanceRecord::STATUS_PRESENT,
        AttendanceRecord::STATUS_ABSENT,
        AttendanceRecord::STATUS_HALF_DAY,
        AttendanceRecord::STATUS_LATE,
        AttendanceRecord::STATUS_LEAVE,
        AttendanceRecord::STATUS_WEEK_OFF,
        AttendanceRecord::STATUS_HOLIDAY,
    ];

    public function __construct(
        protected AttendanceAccessService $attendanceAccessService,
        protected AttendanceFinalizerService $finalizerService,
        protected AttendanceRecordSyncService $recordSyncService,
        protected ProductiveDayTrackerService $productiveDayTrackerService
    ) {
    }

    public function index(Request $request): View|JsonResponse
    {
        $month = $this->parseMonth($request->input('month'));
        $this->finalizeVisibleMonth($month);

        $profiles = $this->filteredProfiles($request, $month);
        $hasOverrideLogsTable = Schema::hasTable('attendance_record_override_logs');
        $recordQuery = AttendanceRecord::query()
            ->with(['manualOverriddenByUser', 'manualClearedByUser'])
            ->whereBetween('attendance_date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->whereIn('user_id', $profiles->pluck('user_id'))
            ->when($hasOverrideLogsTable, fn ($query) => $query->with('overrideLogs.actor'));

        $records = $recordQuery->get()->map(function (AttendanceRecord $record) use ($hasOverrideLogsTable) {
            if (!$hasOverrideLogsTable) {
                $record->setRelation('overrideLogs', collect());
            }

            return $record;
        })->groupBy('user_id');

        $profiles = $this->applyPostRecordFilters($profiles, $records, $request);
        $dates = $this->monthDates($month);
        $productiveDayTracker = $this->productiveDayTrackerService->forUsers($profiles, $month->copy()->startOfMonth(), $month->copy()->endOfMonth(), $request->user());
        $productiveDaySummary = $this->productiveDayTrackerService->summary($profiles, $dates, $productiveDayTracker);
        $offices = OfficeLocation::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $employeeOptions = $this->employeeOptions();
        $roles = $profiles->pluck('user.role')
            ->filter()
            ->unique('slug')
            ->sortBy('name')
            ->values();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'month' => $month->format('Y-m'),
                    'dates' => $dates->map(fn (Carbon $date) => [
                        'date' => $date->toDateString(),
                        'label' => $date->format('d M'),
                        'day' => $date->format('D'),
                    ])->values(),
                    'employees' => $profiles->map(function (UserAttendanceProfile $profile) use ($records) {
                        return [
                            'user_id' => $profile->user_id,
                            'name' => $profile->user?->name,
                            'role' => $profile->user?->role?->name,
                            'office' => $profile->officeLocation?->name,
                            'records' => $records->get($profile->user_id, collect())->map(function (AttendanceRecord $record) {
                                return [
                                    'attendance_date' => $record->attendance_date?->toDateString(),
                                    'status' => $record->status,
                                    'status_source' => $record->status_source,
                                    'manual_status' => $record->manual_status,
                                    'manual_override_reason' => $record->manual_override_reason,
                                    'manual_overridden_at' => optional($record->manual_overridden_at)?->toIso8601String(),
                                    'manual_overridden_by' => $record->manualOverriddenByUser?->name,
                                    'first_punch_in_at' => optional($record->first_punch_in_at)?->format('H:i'),
                                    'last_punch_out_at' => optional($record->last_punch_out_at)?->format('H:i'),
                                    'auto_status' => $record->auto_status,
                                    'auto_status_source' => $record->auto_status_source,
                                ];
                            })->values(),
                        ];
                    })->values(),
                ],
            ]);
        }

        return view('hr-manager.attendance.sheet', [
            'month' => $month,
            'dates' => $dates,
            'profiles' => $profiles,
            'recordsByUser' => $records,
            'productiveDayTracker' => $productiveDayTracker,
            'productiveDaySummary' => $productiveDaySummary,
            'offices' => $offices,
            'employeeOptions' => $employeeOptions,
            'roles' => $roles,
            'filters' => [
                'user_id' => (string) $request->input('user_id', ''),
                'search' => (string) $request->input('search', ''),
                'status' => (string) $request->input('status', ''),
                'role' => (string) $request->input('role', ''),
                'office_location_id' => (string) $request->input('office_location_id', ''),
                'manual_only' => (bool) $request->boolean('manual_only'),
            ],
            'statusOptions' => self::ALLOWED_STATUSES,
        ]);
    }

    public function storeOverride(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'scope' => ['required', 'in:cell,row,column'],
            'month' => ['nullable', 'date_format:Y-m'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'attendance_date' => ['nullable', 'date'],
            'manual_status' => ['nullable', 'in:' . implode(',', self::ALLOWED_STATUSES)],
            'manual_first_punch_in_at' => ['nullable', 'date_format:H:i'],
            'manual_last_punch_out_at' => ['nullable', 'date_format:H:i'],
            'manual_override_reason' => ['required', 'string', 'max:1000'],
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:40'],
            'role' => ['nullable', 'string', 'max:100'],
            'office_location_id' => ['nullable', 'integer'],
            'manual_only' => ['nullable', 'boolean'],
        ]);

        if (
            empty($validated['manual_status'])
            && empty($validated['manual_first_punch_in_at'])
            && empty($validated['manual_last_punch_out_at'])
        ) {
            return back()->withErrors(['manual_status' => 'Status ya timing me se kam se kam ek value deni hogi.'])->withInput();
        }

        $targets = $this->resolveTargetRecords($request, $validated);
        foreach ($targets as $record) {
            $date = $record->attendance_date instanceof CarbonInterface
                ? $record->attendance_date->copy()
                : Carbon::parse($record->attendance_date);

            $this->recordSyncService->applyManualOverride($record, [
                'manual_status' => $validated['manual_status'] ?? null,
                'manual_first_punch_in_at' => $this->combineTime($date, $validated['manual_first_punch_in_at'] ?? null),
                'manual_last_punch_out_at' => $this->combineTime($date, $validated['manual_last_punch_out_at'] ?? null),
                'manual_override_reason' => $validated['manual_override_reason'],
            ], $request->user());
        }

        return redirect()->route('hr-manager.attendance.sheet', $this->redirectFilters($validated))
            ->with('success', count($targets) . ' attendance cell(s) updated.');
    }

    public function clearOverride(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'attendance_record_id' => ['required', 'integer', 'exists:attendance_records,id'],
            'month' => ['nullable', 'date_format:Y-m'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:40'],
            'role' => ['nullable', 'string', 'max:100'],
            'office_location_id' => ['nullable', 'integer'],
            'manual_only' => ['nullable', 'boolean'],
            'clear_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $record = AttendanceRecord::query()->with('user.role')->findOrFail($validated['attendance_record_id']);
        $this->recordSyncService->clearManualOverride($record, $request->user(), $validated['clear_reason'] ?? null);

        return redirect()->route('hr-manager.attendance.sheet', $this->redirectFilters($validated))
            ->with('success', 'Manual override cleared.');
    }

    public function verifyProductiveItem(Request $request, string $type, int $id, VerificationRoutingService $routing): RedirectResponse|JsonResponse
    {
        [$item, $workflow] = $this->resolveProductiveVerificationItem($type, $id);

        if (!$routing->canVerify($request->user(), $item, $workflow)) {
            abort(403, 'Verification Routing ke hisab se aap is item ko verify nahi kar sakte.');
        }

        if ($item->verification_status === 'verified') {
            return $this->productiveVerifyResponse($request, false, 'Item already verified.');
        }

        if ((string) $item->status !== 'completed') {
            return $this->productiveVerifyResponse($request, false, 'Item complete hone ke baad verify ho sakta hai.', 422);
        }

        $note = 'Verified from HR Attendance Sheet by ' . $request->user()->name;

        if ($item instanceof SiteVisit) {
            $item->verify($request->user()->id, $note);
            $routing->auditVerification('site_visit_verified', $request->user(), $item, $workflow);
            $message = 'Site Visit verified successfully.';
        } else {
            $item->verify($request->user()->id, $note);
            $routing->auditVerification('meeting_verified', $request->user(), $item, $workflow);
            $message = 'Meeting verified successfully.';
        }

        return $this->productiveVerifyResponse($request, true, $message);
    }

    public function rejectProductiveItem(Request $request, string $type, int $id, VerificationRoutingService $routing): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        [$item, $workflow] = $this->resolveProductiveVerificationItem($type, $id);

        if (!$routing->canVerify($request->user(), $item, $workflow)) {
            abort(403, 'Verification Routing ke hisab se aap is item ko reject nahi kar sakte.');
        }

        if ($item->verification_status === 'verified') {
            return $this->productiveVerifyResponse($request, false, 'Verified item reject nahi ho sakta.', 422);
        }

        if ($item instanceof SiteVisit) {
            $item->reject($request->user()->id, $validated['reason']);
            $routing->auditVerification('site_visit_rejected', $request->user(), $item, $workflow);
            $message = 'Site Visit rejected.';
        } else {
            $item->reject($request->user()->id, $validated['reason']);
            $routing->auditVerification('meeting_rejected', $request->user(), $item, $workflow);
            $message = 'Meeting rejected.';
        }

        return $this->productiveVerifyResponse($request, true, $message);
    }

    private function resolveProductiveVerificationItem(string $type, int $id): array
    {
        if (!in_array($type, ['meeting', 'site_visit'], true)) {
            abort(404);
        }

        $workflow = $type === 'meeting'
            ? VerificationRoutingService::WORKFLOW_MEETING
            : VerificationRoutingService::WORKFLOW_SITE_VISIT;

        $item = $type === 'meeting'
            ? Meeting::query()->withoutGlobalScope('visible_in_queue')->with('creator')->findOrFail($id)
            : SiteVisit::query()->withoutGlobalScope('visible_in_queue')->with('creator')->findOrFail($id);

        return [$item, $workflow];
    }

    private function productiveVerifyResponse(Request $request, bool $success, string $message, int $status = 200): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => $success,
                'message' => $message,
            ], $status);
        }

        return back()->with($success ? 'success' : 'error', $message);
    }

    private function filteredProfiles(Request $request, Carbon $month): Collection
    {
        return User::query()
            ->with(['role', 'employeeProfile', 'attendanceProfile.officeLocation'])
            ->where(function ($query) {
                $query->where('is_active', true)
                    ->orWhereHas('employeeProfile', function ($profileQuery) {
                        $profileQuery->whereIn('employment_status', [
                            EmployeeProfile::STATUS_ACTIVE,
                            EmployeeProfile::STATUS_ON_NOTICE,
                            EmployeeProfile::STATUS_LONG_LEAVE,
                        ]);
                    });
            })
            ->whereHas('role', fn ($roleQuery) => $roleQuery->whereNotNull('slug'))
            ->where(function ($query) use ($request) {
                if ($request->filled('user_id')) {
                    $query->where('id', (int) $request->input('user_id'));
                }

                $search = trim((string) $request->input('search', ''));
                if ($search !== '') {
                    $query->where(function ($inner) use ($search) {
                        $inner->where('name', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%')
                            ->orWhereHas('employeeProfile', fn ($profileQuery) => $profileQuery->where('employee_code', 'like', '%' . $search . '%'))
                            ->orWhereHas('attendanceProfile', fn ($profileQuery) => $profileQuery->where('employee_code', 'like', '%' . $search . '%'));
                    });
                }

                if ($request->filled('role')) {
                    $role = (string) $request->input('role');
                    $query->whereHas('role', fn ($roleQuery) => $roleQuery->where('slug', $role));
                }
            })
            ->when($request->filled('office_location_id'), function ($query) use ($request) {
                $query->whereHas('attendanceProfile', fn ($profileQuery) => $profileQuery->where('office_location_id', (int) $request->input('office_location_id')));
            })
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => $this->attendanceProfileForSheet($user))
            ->values();
    }

    private function employeeOptions(): Collection
    {
        return User::query()
            ->with(['role', 'employeeProfile', 'attendanceProfile.officeLocation'])
            ->where(function ($query) {
                $query->where('is_active', true)
                    ->orWhereHas('employeeProfile', function ($profileQuery) {
                        $profileQuery->whereIn('employment_status', [
                            EmployeeProfile::STATUS_ACTIVE,
                            EmployeeProfile::STATUS_ON_NOTICE,
                            EmployeeProfile::STATUS_LONG_LEAVE,
                        ]);
                    });
            })
            ->whereHas('role', fn ($roleQuery) => $roleQuery->whereNotNull('slug'))
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role?->name,
                'office' => $user->attendanceProfile?->officeLocation?->name,
                'employee_code' => $user->employeeProfile?->employee_code ?: $user->attendanceProfile?->employee_code,
            ])
            ->values();
    }

    private function attendanceProfileForSheet(User $user): UserAttendanceProfile
    {
        $profile = $user->attendanceProfile ?: new UserAttendanceProfile([
            'user_id' => $user->id,
            'employee_code' => $user->employeeProfile?->employee_code,
            'attendance_enabled' => false,
        ]);

        if (!$profile->employee_code && $user->employeeProfile?->employee_code) {
            $profile->employee_code = $user->employeeProfile->employee_code;
        }

        $profile->setRelation('user', $user);
        if ($user->attendanceProfile?->relationLoaded('officeLocation')) {
            $profile->setRelation('officeLocation', $user->attendanceProfile->officeLocation);
        } elseif (!$profile->relationLoaded('officeLocation')) {
            $profile->setRelation('officeLocation', null);
        }

        return $profile;
    }

    private function applyPostRecordFilters(Collection $profiles, Collection $records, Request $request): Collection
    {
        $status = (string) $request->input('status', '');
        $manualOnly = $request->boolean('manual_only');

        if ($status === '' && !$manualOnly) {
            return $profiles;
        }

        return $profiles->filter(function (UserAttendanceProfile $profile) use ($records, $status, $manualOnly) {
            $userRecords = $records->get($profile->user_id, collect());

            if ($manualOnly && !$userRecords->contains(fn (AttendanceRecord $record) => $record->hasActiveManualOverride())) {
                return false;
            }

            if ($status !== '' && !$userRecords->contains(fn (AttendanceRecord $record) => $record->status === $status)) {
                return false;
            }

            return true;
        })->values();
    }

    private function resolveTargetRecords(Request $request, array $validated): Collection
    {
        $scope = $validated['scope'];

        if ($scope === 'cell') {
            return collect([$this->resolveRecord(
                (int) $validated['user_id'],
                Carbon::parse($validated['attendance_date'])
            )]);
        }

        if ($scope === 'row') {
            $month = $this->parseMonth($validated['month'] ?? null);
            return $this->monthDates($month)->map(fn (Carbon $date) => $this->resolveRecord((int) $validated['user_id'], $date));
        }

        $date = Carbon::parse($validated['attendance_date']);
        $profiles = $this->filteredProfiles($request, $date->copy()->startOfMonth());
        $profiles = $this->applyPostRecordFilters(
            $profiles,
            AttendanceRecord::query()
                ->whereDate('attendance_date', $date->toDateString())
                ->whereIn('user_id', $profiles->pluck('user_id'))
                ->get()
                ->groupBy('user_id'),
            $request
        );

        return $profiles->map(fn (UserAttendanceProfile $profile) => $this->resolveRecord($profile->user_id, $date));
    }

    private function resolveRecord(int $userId, Carbon $date): AttendanceRecord
    {
        $record = AttendanceRecord::query()
            ->where('user_id', $userId)
            ->whereDate('attendance_date', $date->toDateString())
            ->first();

        if ($record) {
            return $record->loadMissing(['user.role', 'officeLocation', 'overrideLogs.actor']);
        }

        $profile = UserAttendanceProfile::query()
            ->with(['user.role', 'officeLocation'])
            ->where('user_id', $userId)
            ->first();

        $user = $profile?->user ?: User::query()->with('role')->findOrFail($userId);

        $record = new AttendanceRecord([
            'user_id' => $userId,
            'attendance_date' => $date->toDateString(),
            'office_location_id' => $profile?->office_location_id,
            'attendance_policy_id' => $profile?->attendance_policy_id,
            'status' => AttendanceRecord::STATUS_ABSENT,
            'status_source' => 'auto',
            'payable_day_fraction' => 0,
            'worked_minutes' => 0,
            'late_minutes' => 0,
            'has_missing_punch_out' => false,
        ]);

        $record->setRelation('user', $user);
        $record->setRelation('officeLocation', $profile?->officeLocation);
        $record->setRelation('overrideLogs', collect());

        return $record;
    }

    private function finalizeVisibleMonth(Carbon $month): void
    {
        $today = now()->startOfDay();
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        if ($start->gt($today)) {
            return;
        }

        $cursor = $start->copy();
        $lastFinalizable = $end->lt($today) ? $end : $today;
        while ($cursor->lte($lastFinalizable)) {
            $this->finalizerService->finalizeForDate($cursor);
            $cursor->addDay();
        }
    }

    private function monthDates(Carbon $month): Collection
    {
        $dates = collect();
        $cursor = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        while ($cursor->lte($end)) {
            $dates->push($cursor->copy());
            $cursor->addDay();
        }

        return $dates;
    }

    private function parseMonth(?string $month): Carbon
    {
        if (!$month) {
            return now()->startOfMonth();
        }

        return Carbon::createFromFormat('Y-m', $month)->startOfMonth();
    }

    private function combineTime(Carbon $date, ?string $time): ?Carbon
    {
        if (!$time) {
            return null;
        }

        return Carbon::parse($date->toDateString() . ' ' . $time . ':00');
    }

    private function redirectFilters(array $validated): array
    {
        return array_filter([
            'month' => $validated['month'] ?? null,
            'user_id' => $validated['user_id'] ?? null,
            'search' => $validated['search'] ?? null,
            'status' => $validated['status'] ?? null,
            'role' => $validated['role'] ?? null,
            'office_location_id' => $validated['office_location_id'] ?? null,
            'manual_only' => !empty($validated['manual_only']) ? 1 : null,
        ], fn ($value) => $value !== null && $value !== '');
    }
}

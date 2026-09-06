<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\EmployeeAsset;
use App\Models\EmployeeDepartment;
use App\Models\EmployeeDesignation;
use App\Models\EmployeeDocument;
use App\Models\EmployeeExitWorkflow;
use App\Models\EmployeeProfile;
use App\Models\EmployeeProfileLink;
use App\Models\Incentive;
use App\Models\AttendancePolicy;
use App\Models\OfficeLocation;
use App\Models\SalaryStructure;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAttendanceProfile;
use App\Services\EmployeeExitWorkflowService;
use App\Services\EmployeeMasterService;
use App\Services\EmployeeSalaryRevisionService;
use App\Services\SalaryStructureService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query()
            ->with([
                'role',
                'manager',
                'attendanceProfile.officeLocation',
                'attendanceProfile.attendancePolicy',
                'salaryProfile.salaryStructure.components',
                'employeeProfile.department',
                'employeeProfile.designation',
                'employeeProfile.documents',
                'employeeProfile.latestDetailLink',
                'employeeProfile.assets',
                'employeeProfile.exitWorkflow',
                'incentives',
            ])
            ->whereHas('role', fn ($builder) => $builder->whereNotNull('slug'));

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhereHas('employeeProfile', fn ($profile) => $profile->where('employee_code', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('employment_status')) {
            $status = $request->input('employment_status');
            $query->whereHas('employeeProfile', fn ($profile) => $profile->where('employment_status', $status));
        }

        if ($request->filled('department_id')) {
            $query->whereHas('employeeProfile', fn ($profile) => $profile->where('department_id', $request->integer('department_id')));
        }

        if ($request->filled('designation_id')) {
            $query->whereHas('employeeProfile', fn ($profile) => $profile->where('designation_id', $request->integer('designation_id')));
        }

        if ($request->filled('manager_id')) {
            $query->where('manager_id', $request->integer('manager_id'));
        }

        $employees = $query->orderBy('name')->paginate(18)->withQueryString();
        $departments = EmployeeDepartment::query()->where('is_active', true)->orderBy('display_order')->orderBy('name')->get();
        $designations = EmployeeDesignation::query()->where('is_active', true)->orderBy('display_order')->orderBy('name')->get();
        $managers = User::query()->with('role')->where('is_active', true)->orderBy('name')->get();

        $statsUsers = User::query()
            ->with([
                'attendanceProfile.officeLocation',
                'attendanceProfile.attendancePolicy',
                'salaryProfile',
                'employeeProfile.documents',
                'employeeProfile.assets',
                'employeeProfile.exitWorkflow',
                'incentives',
            ])
            ->get();
        $stats = [
            'total' => $statsUsers->count(),
            'active' => $statsUsers->filter(fn (User $user) => $user->employeeProfile?->employment_status === EmployeeProfile::STATUS_ACTIVE)->count(),
            'on_notice' => $statsUsers->filter(fn (User $user) => $user->employeeProfile?->employment_status === EmployeeProfile::STATUS_ON_NOTICE)->count(),
            'missing_docs' => $statsUsers->filter(fn (User $user) => $user->employeeProfile && count($user->employeeProfile->missingDocumentTypes()) > 0)->count(),
            'pending_setup' => $statsUsers->filter(function (User $user) {
                $profile = $user->employeeProfile;
                $attendanceProfile = $user->attendanceProfile;

                return ! $profile
                    || ! $profile->employee_code
                    || ! $user->phone
                    || ! $attendanceProfile?->officeLocation
                    || ! $attendanceProfile?->attendancePolicy
                    || ! $user->salaryProfile
                    || count($profile->missingDocumentTypes()) > 0;
            })->count(),
            'pending_assets' => $statsUsers->filter(fn (User $user) => $user->employeeProfile && $user->employeeProfile->assets->where('status', EmployeeAsset::STATUS_ISSUED)->isNotEmpty())->count(),
            'exit_open' => $statsUsers->filter(fn (User $user) => $user->employeeProfile?->exitWorkflow && !$user->employeeProfile->exitWorkflow->isClosed())->count(),
            'pending_incentives' => $statsUsers->sum(fn (User $user) => $user->incentives->where('status', '!=', 'verified')->count()),
        ];

        return view('admin.hr.employees.index', compact('employees', 'departments', 'designations', 'managers', 'stats'));
    }

    public function create(Request $request)
    {
        $linkedUser = $request->filled('user_id')
            ? User::query()->with(['role', 'manager', 'employeeProfile'])->findOrFail($request->integer('user_id'))
            : null;

        return view('admin.hr.employees.form', $this->formData(null, $linkedUser));
    }

    public function store(Request $request, EmployeeMasterService $employeeMasterService, EmployeeSalaryRevisionService $salaryRevisionService, SalaryStructureService $salaryStructureService)
    {
        $validated = $this->validateEmployee($request);
        $validated = $this->normalizeRoleDrivenFields($validated);
        $employee = $employeeMasterService->upsertEmployee($validated, $request->user());
        $this->syncAttendanceSetup($employee, $validated);
        $this->syncSalarySetup($employee, $validated, $request->user(), $salaryRevisionService, $salaryStructureService);

        return redirect()
            ->route($this->hrRouteBase() . '.employees.show', $employee)
            ->with('success', 'Employee profile saved.');
    }

    public function show(User $employee)
    {
        $employee->load([
            'role',
            'manager.role',
            'attendanceProfile.officeLocation',
            'attendanceProfile.attendancePolicy',
            'salaryProfile.salaryStructure.components',
            'employeeProfile.department',
            'employeeProfile.designation',
            'employeeProfile.documents.uploader',
            'employeeProfile.latestDetailLink.creator',
            'employeeProfile.assets.logs.performer',
            'employeeProfile.timelineEvents.actor',
            'employeeProfile.exitWorkflow',
            'employeeProfile.salaryRevisions.salaryStructure',
            'employeeProfile.salaryRevisions.changedBy',
            'incentives.siteVisit',
        ]);

        abort_unless($employee->employeeProfile, 404);

        $salaryTotal = $this->resolveTotalSalary($employee);
        $currentSalaryStructureId = $employee->salaryProfile?->salary_structure_id;
        $salaryStructures = SalaryStructure::query()
            ->where('is_active', true)
            ->where(function ($query) use ($currentSalaryStructureId) {
                $query->where('name', 'not like', '__employee__:%')
                    ->when($currentSalaryStructureId, fn ($builder) => $builder->orWhere('id', $currentSalaryStructureId));
            })
            ->with('components')
            ->orderBy('name')
            ->get();
        $documentCenterUrl = route($this->hrRouteBase() . '.document-center.index', ['employee_id' => $employee->id]);
        $incentiveSummary = $this->buildIncentiveSummary($employee);

        return view('admin.hr.employees.show', compact(
            'employee',
            'salaryTotal',
            'salaryStructures',
            'documentCenterUrl',
            'incentiveSummary'
        ));
    }

    public function edit(User $employee)
    {
        $employee->load(['role', 'manager', 'employeeProfile.department', 'employeeProfile.designation']);
        abort_unless($employee->employeeProfile, 404);

        return view('admin.hr.employees.form', $this->formData($employee));
    }

    public function update(Request $request, User $employee, EmployeeMasterService $employeeMasterService, EmployeeSalaryRevisionService $salaryRevisionService, SalaryStructureService $salaryStructureService)
    {
        $employee->load('employeeProfile');
        abort_unless($employee->employeeProfile, 404);

        $validated = $this->validateEmployee($request, $employee);
        $validated = $this->normalizeRoleDrivenFields($validated);
        $updated = $employeeMasterService->upsertEmployee($validated, $request->user(), $employee);
        $this->syncAttendanceSetup($updated, $validated);
        $this->syncSalarySetup($updated, $validated, $request->user(), $salaryRevisionService, $salaryStructureService);

        return redirect()
            ->route($this->hrRouteBase() . '.employees.show', $updated)
            ->with('success', 'Employee profile updated.');
    }

    public function storeDocument(Request $request, User $employee, EmployeeMasterService $employeeMasterService)
    {
        $validated = $request->validate([
            'document_type' => 'required|string|max:100',
            'document_label' => 'required|string|max:255',
            'document_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:2000',
            'expires_at' => 'nullable|date',
            'is_required' => 'nullable|boolean',
            'file' => 'nullable|file|max:10240',
        ]);

        $employee->load('employeeProfile');
        $employeeMasterService->addDocument($employee, $validated, $request->file('file'), $request->user());

        return back()->with('success', 'Employee document added.');
    }

    public function destroyDocument(User $employee, EmployeeDocument $document, EmployeeMasterService $employeeMasterService)
    {
        abort_unless($employee->employeeProfile && $document->employee_profile_id === $employee->employeeProfile->id, 404);
        $employeeMasterService->deleteDocument($document, request()->user());

        return back()->with('success', 'Employee document removed.');
    }

    public function generateDetailLink(Request $request, User $employee)
    {
        $employee->load('employeeProfile');
        abort_unless($employee->employeeProfile, 404);

        $employee->employeeProfile->detailLinks()
            ->whereIn('status', [EmployeeProfileLink::STATUS_ACTIVE, EmployeeProfileLink::STATUS_SUBMITTED])
            ->update(['status' => EmployeeProfileLink::STATUS_REVOKED]);

        $link = $employee->employeeProfile->detailLinks()->create([
            'token' => Str::random(64),
            'status' => EmployeeProfileLink::STATUS_ACTIVE,
            'expires_at' => now()->addDays(7),
            'created_by' => $request->user()->id,
        ]);

        return back()
            ->with('success', 'Employee detail form link ready.')
            ->with('employee_detail_link', $link->publicUrl());
    }

    public function revokeDetailLink(User $employee, EmployeeProfileLink $link)
    {
        $employee->load('employeeProfile');
        abort_unless($employee->employeeProfile && $link->employee_profile_id === $employee->employeeProfile->id, 404);

        $link->update(['status' => EmployeeProfileLink::STATUS_REVOKED]);

        return back()->with('success', 'Employee detail form link revoked.');
    }

    public function storeAsset(Request $request, User $employee, EmployeeMasterService $employeeMasterService)
    {
        $validated = $request->validate([
            'asset_type' => 'required|string|max:100',
            'asset_name' => 'required|string|max:255',
            'serial_number' => 'nullable|string|max:255',
            'vendor' => 'nullable|string|max:255',
            'asset_condition' => 'nullable|string|max:255',
            'status' => ['required', Rule::in([
                EmployeeAsset::STATUS_ISSUED,
                EmployeeAsset::STATUS_RETURNED,
                EmployeeAsset::STATUS_LOST,
                EmployeeAsset::STATUS_DAMAGED,
            ])],
            'issued_at' => 'nullable|date',
            'returned_at' => 'nullable|date|after_or_equal:issued_at',
            'notes' => 'nullable|string|max:2000',
            'attachment' => 'nullable|file|max:10240',
        ]);

        $employee->load('employeeProfile');
        $employeeMasterService->addAsset($employee, $validated, $request->file('attachment'), $request->user());

        return back()->with('success', 'Employee asset saved.');
    }

    public function updateAssetStatus(Request $request, User $employee, EmployeeAsset $asset, EmployeeMasterService $employeeMasterService)
    {
        abort_unless($employee->employeeProfile && $asset->employee_profile_id === $employee->employeeProfile->id, 404);

        $validated = $request->validate([
            'status' => ['required', Rule::in([
                EmployeeAsset::STATUS_ISSUED,
                EmployeeAsset::STATUS_RETURNED,
                EmployeeAsset::STATUS_LOST,
                EmployeeAsset::STATUS_DAMAGED,
            ])],
            'asset_condition' => 'nullable|string|max:255',
            'returned_at' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
        ]);

        $employeeMasterService->updateAssetStatus($asset, $validated, $request->user());

        return back()->with('success', 'Employee asset updated.');
    }

    public function storeSalaryRevision(Request $request, User $employee, EmployeeSalaryRevisionService $salaryRevisionService)
    {
        $employee->load('employeeProfile');
        abort_unless($employee->employeeProfile, 404);

        $validated = $request->validate([
            'salary_structure_id' => 'nullable|exists:salary_structures,id',
            'new_base_salary' => 'required|numeric|min:0',
            'effective_from' => 'required|date',
            'reason' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:2000',
        ]);

        $salaryRevisionService->store($employee->employeeProfile, $validated, $request->user());

        return back()->with('success', 'Salary revision saved.');
    }

    public function updateExitWorkflow(Request $request, User $employee, EmployeeExitWorkflowService $exitWorkflowService)
    {
        $employee->load('employeeProfile.assets', 'employeeProfile.exitWorkflow');
        abort_unless($employee->employeeProfile, 404);

        $validated = $request->validate([
            'status' => ['required', Rule::in([
                EmployeeExitWorkflow::STATUS_ON_NOTICE,
                EmployeeExitWorkflow::STATUS_RESIGNED,
                EmployeeExitWorkflow::STATUS_TERMINATED,
                EmployeeExitWorkflow::STATUS_CLOSED,
            ])],
            'notice_start_date' => 'nullable|date',
            'resignation_date' => 'nullable|date',
            'last_working_date' => 'nullable|date',
            'exit_reason' => 'nullable|string|max:3000',
            'notes' => 'nullable|string|max:3000',
            'hr_clearance_completed' => 'nullable|boolean',
            'finance_clearance_completed' => 'nullable|boolean',
            'asset_clearance_completed' => 'nullable|boolean',
            'disable_login_now' => 'nullable|boolean',
        ]);

        $exitWorkflowService->update($employee->employeeProfile, $validated, $request->user());

        return back()->with('success', 'Exit workflow updated.');
    }

    private function formData(?User $employee = null, ?User $linkedUser = null): array
    {
        $employee?->load(['role', 'manager', 'attendanceProfile.officeLocation', 'attendanceProfile.attendancePolicy', 'salaryProfile.salaryStructure.components', 'employeeProfile.department', 'employeeProfile.designation']);
        $linkedUser?->load(['role', 'manager', 'attendanceProfile.officeLocation', 'attendanceProfile.attendancePolicy', 'salaryProfile.salaryStructure.components', 'employeeProfile.department', 'employeeProfile.designation']);
        $recordUser = $employee ?? $linkedUser;
        $currentSalaryStructureId = $recordUser?->salaryProfile?->salary_structure_id;

        return [
            'employee' => $employee,
            'linkedUser' => $linkedUser,
            'existingUsers' => User::query()->with('role')->orderBy('name')->get(),
            'roles' => Role::query()->where('is_active', true)->orderBy('name')->get(),
            'departments' => EmployeeDepartment::query()->where('is_active', true)->orderBy('display_order')->orderBy('name')->get(),
            'designations' => EmployeeDesignation::query()->where('is_active', true)->orderBy('display_order')->orderBy('name')->get(),
            'managers' => User::query()->with('role')->where('is_active', true)->orderBy('name')->get(),
            'officeLocations' => OfficeLocation::query()->where('is_active', true)->orderBy('name')->get(),
            'attendancePolicies' => AttendancePolicy::query()->with('officeLocation')->where('is_active', true)->orderBy('name')->get(),
            'attendanceDefaults' => $this->attendanceDefaults(),
            'salaryStructures' => SalaryStructure::query()
                ->with('components')
                ->where('is_active', true)
                ->where(function ($query) use ($currentSalaryStructureId) {
                    $query->where('name', 'not like', '__employee__:%')
                        ->when($currentSalaryStructureId, fn ($builder) => $builder->orWhere('id', $currentSalaryStructureId));
                })
                ->orderBy('name')
                ->get(),
        ];
    }

    private function validateEmployee(Request $request, ?User $employee = null): array
    {
        $linkedUserId = $employee?->id ?: $request->input('existing_user_id');

        return $request->validate([
            'existing_user_id' => 'nullable|exists:users,id',
            'login_enabled' => 'nullable|boolean',
            'employee_code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('employee_profiles', 'employee_code')->ignore(optional($employee?->employeeProfile)->id),
            ],
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($linkedUserId),
            ],
            'password' => $employee || $request->filled('existing_user_id')
                ? 'nullable|string|min:8'
                : 'nullable|string|min:8',
            'phone' => 'required|string|max:20',
            'role_id' => 'required|exists:roles,id',
            'manager_id' => 'nullable|exists:users,id',
            'department_id' => 'nullable|exists:employee_departments,id',
            'new_department' => 'nullable|string|max:255',
            'designation_id' => 'nullable|exists:employee_designations,id',
            'new_designation' => 'nullable|string|max:255',
            'joining_date' => 'required|date',
            'employment_status' => ['required', Rule::in([
                EmployeeProfile::STATUS_ACTIVE,
                EmployeeProfile::STATUS_ON_NOTICE,
                EmployeeProfile::STATUS_RESIGNED,
                EmployeeProfile::STATUS_TERMINATED,
                EmployeeProfile::STATUS_LONG_LEAVE,
            ])],
            'probation_end_date' => 'nullable|date|after_or_equal:joining_date',
            'salary_day_of_month' => 'nullable|integer|min:1|max:31',
            'attendance_enabled' => 'nullable|boolean',
            'attendance_office_location_id' => 'nullable|required_if:attendance_enabled,1|exists:office_locations,id',
            'attendance_policy_id' => 'nullable|required_if:attendance_enabled,1|exists:attendance_policies,id',
            'attendance_rollout_stage' => ['nullable', Rule::in(['pilot', 'live', 'disabled'])],
            'attendance_effective_from' => 'nullable|date',
            'attendance_outside_request' => ['nullable', Rule::in(['inherit', '1', '0'])],
            'salary_setup_mode' => ['required', Rule::in(['template', 'custom'])],
            'salary_structure_id' => 'nullable|required_if:salary_setup_mode,template|exists:salary_structures,id',
            'monthly_salary' => 'required|numeric|min:0.01|max:999999999.99',
            'salary_effective_from' => 'required|date',
            'custom_components' => 'nullable|required_if:salary_setup_mode,custom|array',
            'custom_components.*.value' => 'nullable|numeric|min:0|max:999999999.99',
            'account_holder_name' => 'required|string|max:255',
            'bank_account_number' => 'required|string|max:100',
            'ifsc_code' => 'required|string|max:30',
            'pan_number' => 'required|string|max:30',
            'aadhaar_number' => 'required|string|max:30',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:30',
            'current_address' => 'nullable|string|max:3000',
            'permanent_address' => 'nullable|string|max:3000',
            'notes' => 'nullable|string|max:3000',
        ], [
            'department_id.exists' => 'Selected department is invalid.',
            'designation_id.exists' => 'Selected designation is invalid.',
        ]);
    }

    private function normalizeRoleDrivenFields(array $validated): array
    {
        $role = Role::query()->find($validated['role_id']);
        $roleName = Str::lower((string) $role?->name);
        $roleSlug = Str::of((string) $role?->slug)->replace(['_', '-'], ' ')->lower()->toString();
        $roleText = trim($roleName . ' ' . $roleSlug);

        if (! Str::contains($roleText, 'marketing')) {
            return $validated;
        }

        $isMarketingExecutive = Str::contains($roleText, 'marketing executive');
        $isMarketingManager = Str::contains($roleText, 'marketing manager');
        $expectedDesignation = $isMarketingExecutive
            ? 'Marketing Executive'
            : ($isMarketingManager ? 'Marketing Manager' : $role?->name);

        if (! empty($validated['department_id'])) {
            $department = EmployeeDepartment::query()->find($validated['department_id']);
            if ($department && ! Str::contains(Str::lower($department->name), 'marketing')) {
                throw ValidationException::withMessages([
                    'department_id' => 'Marketing role ke liye department Marketing hi hona chahiye.',
                ]);
            }
        } elseif (empty($validated['new_department'])) {
            $validated['new_department'] = 'Marketing';
        }

        if (! empty($validated['designation_id'])) {
            $designation = EmployeeDesignation::query()->find($validated['designation_id']);
            if ($designation && ! Str::contains(Str::lower($designation->name), 'marketing')) {
                throw ValidationException::withMessages([
                    'designation_id' => 'Marketing role ke liye Sales/ASM designation select nahi kar sakte.',
                ]);
            }
        } elseif (empty($validated['new_designation']) && $expectedDesignation) {
            $validated['new_designation'] = $expectedDesignation;
        }

        if (! empty($validated['manager_id'])) {
            $manager = User::query()->with('role')->find($validated['manager_id']);
            $managerRole = Str::lower((string) $manager?->role?->name . ' ' . (string) $manager?->role?->slug);
            $managerRole = str_replace(['_', '-'], ' ', $managerRole);
            $allowed = $isMarketingExecutive
                ? ['marketing manager', 'sales manager', 'admin']
                : ['sales manager', 'admin'];

            if (! collect($allowed)->contains(fn (string $needle) => Str::contains($managerRole, $needle))) {
                throw ValidationException::withMessages([
                    'manager_id' => 'Marketing role ke liye reporting manager Marketing Manager, Sales Manager ya Admin hona chahiye.',
                ]);
            }
        }

        return $validated;
    }

    private function syncAttendanceSetup(User $employee, array $validated): void
    {
        $enabled = (bool) ($validated['attendance_enabled'] ?? false);
        $outsideRequest = $validated['attendance_outside_request'] ?? 'inherit';
        $rolloutStage = $enabled ? ($validated['attendance_rollout_stage'] ?? 'live') : 'disabled';

        UserAttendanceProfile::query()->updateOrCreate(
            ['user_id' => $employee->id],
            [
                'office_location_id' => $enabled ? ($validated['attendance_office_location_id'] ?? null) : null,
                'attendance_policy_id' => $enabled ? ($validated['attendance_policy_id'] ?? null) : null,
                'employee_code' => $validated['employee_code'] ?? $employee->employeeProfile?->employee_code,
                'attendance_enabled' => $enabled,
                'allow_outside_punch_requests' => $outsideRequest === 'inherit' ? null : $outsideRequest === '1',
                'attendance_rollout_stage' => $rolloutStage,
                'effective_from' => $validated['attendance_effective_from']
                    ?? $validated['joining_date']
                    ?? now()->toDateString(),
            ]
        );
    }

    private function attendanceDefaults(): array
    {
        $baseOffice = OfficeLocation::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->where('name', 'like', '%Base CC2%')
                    ->orWhere('name', 'like', '%CC2%')
                    ->orWhere('code', 'like', '%CC2%');
            })
            ->orderBy('name')
            ->first();

        $marketingPolicy = AttendancePolicy::query()
            ->where('is_active', true)
            ->where('name', 'like', '%Marketing%')
            ->orderBy('name')
            ->first();

        $cc2Policy = AttendancePolicy::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->where('name', 'like', '%CC2%')
                    ->orWhere('name', 'like', '%Base%');
            })
            ->orderBy('name')
            ->first();

        $defaultOfficeId = $baseOffice?->id;
        $marketingPolicyId = $marketingPolicy?->id ?: $cc2Policy?->id;
        $salesPolicyId = $cc2Policy?->id ?: $marketingPolicy?->id;

        $on = fn (?int $policyId) => [
            'enabled' => true,
            'office_id' => $defaultOfficeId,
            'policy_id' => $policyId,
            'stage' => 'live',
            'outside' => 'inherit',
        ];
        $off = [
            'enabled' => false,
            'office_id' => null,
            'policy_id' => null,
            'stage' => 'disabled',
            'outside' => 'inherit',
        ];

        return [
            Role::MARKETING_EXECUTIVE => $on($marketingPolicyId),
            Role::MARKETING_MANAGER => $on($marketingPolicyId),
            Role::JUNIOR_HR => $on($marketingPolicyId),
            Role::ASSISTANT_SALES_MANAGER => $on($salesPolicyId),
            Role::SENIOR_MANAGER => $on($salesPolicyId),
            Role::SALES_MANAGER => $on($salesPolicyId),
            Role::ADMIN => $off,
            Role::CRM => $off,
            Role::FINANCE_MANAGER => $off,
            Role::HR_MANAGER => $off,
        ];
    }

    private function syncSalarySetup(User $employee, array $validated, User $actor, EmployeeSalaryRevisionService $salaryRevisionService, SalaryStructureService $salaryStructureService): void
    {
        $employee->load(['employeeProfile', 'salaryProfile.salaryStructure.components']);
        abort_unless($employee->employeeProfile, 404);

        if (($validated['salary_setup_mode'] ?? 'template') === 'custom') {
            $this->syncCustomSalarySetup($employee, $validated, $actor, $salaryRevisionService, $salaryStructureService);
            return;
        }

        $currentSalaryProfile = $employee->salaryProfile;
        $newBaseSalary = round((float) $validated['monthly_salary'], 2);
        $newStructureId = (int) $validated['salary_structure_id'];
        $newEffectiveFrom = Carbon::parse($validated['salary_effective_from'])->toDateString();

        $salaryChanged = ! $currentSalaryProfile
            || round((float) $currentSalaryProfile->base_salary, 2) !== $newBaseSalary
            || (int) $currentSalaryProfile->salary_structure_id !== $newStructureId
            || optional($currentSalaryProfile->effective_from)->toDateString() !== $newEffectiveFrom;

        if (! $salaryChanged) {
            return;
        }

        $salaryRevisionService->store($employee->employeeProfile, [
            'salary_structure_id' => $newStructureId,
            'new_base_salary' => $newBaseSalary,
            'effective_from' => $newEffectiveFrom,
            'reason' => $currentSalaryProfile ? 'Employee form salary update' : 'Initial salary setup',
            'notes' => null,
        ], $actor);
    }

    private function syncCustomSalarySetup(User $employee, array $validated, User $actor, EmployeeSalaryRevisionService $salaryRevisionService, SalaryStructureService $salaryStructureService): void
    {
        $currentSalaryProfile = $employee->salaryProfile;
        $components = $this->buildCustomSalaryComponents($validated['custom_components'] ?? []);
        $fixedTotal = collect($components)->sum(fn (array $component) => round((float) $component['value'], 2));
        $totalSalary = round((float) $validated['monthly_salary'], 2);
        $baseSalary = round($totalSalary - $fixedTotal, 2);

        if ($baseSalary < 0) {
            throw ValidationException::withMessages([
                'monthly_salary' => 'Monthly salary fixed components se zyada honi chahiye.',
            ]);
        }

        $existingStructure = $currentSalaryProfile?->salaryStructure
            && str_starts_with((string) $currentSalaryProfile->salaryStructure->name, '__employee__:')
                ? $currentSalaryProfile->salaryStructure
                : null;

        $componentsChanged = ! $existingStructure || $this->salaryComponentsFingerprint($existingStructure->components) !== $this->salaryComponentsFingerprint(collect($components));

        $structure = $salaryStructureService->saveStructure([
            'name' => '__employee__:' . $employee->id . ':' . $employee->name,
            'is_active' => true,
            'components' => $components,
        ], $existingStructure);

        $effectiveFrom = Carbon::parse($validated['salary_effective_from'])->toDateString();
        $salaryChanged = $componentsChanged
            || ! $currentSalaryProfile
            || round((float) $currentSalaryProfile->base_salary, 2) !== $baseSalary
            || (int) $currentSalaryProfile->salary_structure_id !== (int) $structure->id
            || optional($currentSalaryProfile->effective_from)->toDateString() !== $effectiveFrom;

        if (! $salaryChanged) {
            return;
        }

        $salaryRevisionService->store($employee->employeeProfile, [
            'salary_structure_id' => $structure->id,
            'new_base_salary' => $baseSalary,
            'effective_from' => $effectiveFrom,
            'reason' => $currentSalaryProfile ? 'Employee custom salary breakup update' : 'Initial custom salary setup',
            'notes' => null,
        ], $actor);
    }

    private function buildCustomSalaryComponents(array $input): array
    {
        $heads = [
            'HRA' => 'House Rent Allowance',
            'TRAVEL' => 'Travel Allowance',
            'MEDICAL' => 'Medical Allowance',
            'SPECIAL' => 'Special Allowance',
            'BONUS' => 'Bonus / Incentive',
        ];

        return collect($heads)->map(function (string $label, string $code) use ($input, $heads) {
            return [
                'component_type' => 'earning',
                'code' => $code,
                'label' => $label,
                'calc_type' => 'fixed',
                'value' => round((float) data_get($input, $code . '.value', 0), 2),
                'display_order' => array_search($code, array_keys($heads), true) + 1,
                'is_active' => true,
            ];
        })->values()->all();
    }

    private function salaryComponentsFingerprint($components): string
    {
        return collect($components)
            ->map(fn ($component) => [
                'code' => (string) data_get($component, 'code'),
                'label' => (string) data_get($component, 'label'),
                'calc_type' => (string) data_get($component, 'calc_type'),
                'value' => round((float) data_get($component, 'value'), 2),
                'display_order' => (int) data_get($component, 'display_order'),
            ])
            ->sortBy('display_order')
            ->values()
            ->toJson();
    }

    private function resolveTotalSalary(User $employee): float
    {
        $profile = $employee->salaryProfile;
        if (!$profile) {
            return 0;
        }

        $base = (float) $profile->base_salary;
        $componentsTotal = collect(optional($profile->salaryStructure)->components)
            ->filter(fn ($component) => $component->is_active)
            ->sum(function ($component) use ($base) {
                return $component->calc_type === 'percent_of_base'
                    ? round($base * (((float) $component->value) / 100), 2)
                    : round((float) $component->value, 2);
            });

        return round($base + $componentsTotal, 2);
    }

    private function buildIncentiveSummary(User $employee): array
    {
        $today = now();
        $currentMonthStart = $today->copy()->startOfMonth();
        $previousMonthStart = $today->copy()->subMonthNoOverflow()->startOfMonth();
        $previousMonthEnd = $previousMonthStart->copy()->endOfMonth();

        $incentives = $employee->incentives;

        return [
            'current_month' => (float) $incentives->filter(function (Incentive $incentive) use ($currentMonthStart) {
                return $incentive->created_at && $incentive->created_at->greaterThanOrEqualTo($currentMonthStart);
            })->sum('amount'),
            'previous_month' => (float) $incentives->filter(function (Incentive $incentive) use ($previousMonthStart, $previousMonthEnd) {
                return $incentive->created_at && $incentive->created_at->between($previousMonthStart, $previousMonthEnd);
            })->sum('amount'),
            'pending' => (float) $incentives->where('status', '!=', 'verified')->sum('amount'),
            'total_paid' => (float) $incentives->where('status', 'verified')->sum('amount'),
            'recent' => $incentives->sortByDesc('created_at')->take(8),
        ];
    }

    private function hrRouteBase(): string
    {
        return auth()->check() && auth()->user()->isHrManager()
            ? 'hr-manager.settings.hr'
            : 'admin.hr';
    }
}

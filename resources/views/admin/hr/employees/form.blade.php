@extends('layouts.app')

@section('title', ($employee ? 'Edit Employee' : 'Create Employee'))
@section('page-title', $employee ? 'Edit Employee' : 'Create Employee')

@push('styles')
<style>
    .employee-form-page { --surface:#fff; --line:rgba(22,47,32,.10); --text:#162f20; --muted:#6f7d71; --green:#165a3a; --shadow:0 18px 40px rgba(24,49,38,.08); display:flex; flex-direction:column; gap:16px; }
    .employee-form-card { background:var(--surface); border:1px solid var(--line); border-radius:22px; box-shadow:var(--shadow); }
    .employee-form-header { padding:24px; border-bottom:1px solid var(--line); }
    .employee-form-kicker { font-size:11px; font-weight:900; letter-spacing:.16em; text-transform:uppercase; color:var(--muted); }
    .employee-form-title { margin-top:8px; font-size:32px; line-height:1; letter-spacing:-.06em; color:var(--text); font-weight:900; }
    .employee-form-subtitle { margin-top:10px; max-width:760px; color:var(--muted); font-size:14px; font-weight:500; line-height:1.55; }
    .employee-form-body { padding:24px; display:flex; flex-direction:column; gap:20px; }
    .employee-form-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; }
    .employee-section { border:1px solid var(--line); border-radius:18px; padding:18px; background:#fff; }
    .employee-section-title { font-size:12px; font-weight:900; letter-spacing:.14em; text-transform:uppercase; color:var(--muted); margin-bottom:14px; }
    .employee-section-help { margin:-8px 0 14px; color:var(--muted); font-size:12px; font-weight:600; line-height:1.45; }
    .employee-salary-section { align-self:start; }
    .employee-salary-section .employee-inline { margin-top:2px; }
    .employee-salary-preview { margin-top:14px; border:1px solid var(--line); border-radius:16px; overflow:hidden; background:#fbfcfa; }
    .employee-salary-preview-head { display:flex; justify-content:space-between; gap:12px; padding:12px 14px; border-bottom:1px solid var(--line); background:#f5faf6; font-size:12px; font-weight:800; color:var(--text); }
    .employee-salary-preview-head span { color:var(--muted); font-weight:700; }
    .employee-salary-preview table { width:100%; border-collapse:collapse; font-size:12px; }
    .employee-salary-preview th, .employee-salary-preview td { padding:10px 12px; border-bottom:1px solid var(--line); text-align:left; vertical-align:top; }
    .employee-salary-preview th { font-size:10px; letter-spacing:.1em; text-transform:uppercase; color:var(--muted); background:#fff; }
    .employee-salary-preview tr:last-child td { border-bottom:none; }
    .employee-salary-preview .amount { text-align:right; font-weight:800; color:var(--text); white-space:nowrap; }
    .employee-salary-preview .basic-row td { background:#f8fcf6; font-weight:800; }
    .employee-salary-empty { padding:14px; color:var(--muted); font-size:12px; font-weight:700; }
    .salary-mode-toggle { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:8px; padding:5px; border:1px solid var(--line); border-radius:14px; background:#f6faf7; margin-bottom:14px; }
    .salary-mode-option { position:relative; }
    .salary-mode-option input { position:absolute; opacity:0; pointer-events:none; }
    .salary-mode-label { display:flex; align-items:center; justify-content:center; min-height:38px; border-radius:10px; color:var(--muted); font-size:13px; font-weight:900; cursor:pointer; }
    .salary-mode-option input:checked + .salary-mode-label { background:var(--green); color:#fff; box-shadow:0 10px 20px rgba(22,90,58,.18); }
    .salary-mode-panel[hidden] { display:none; }
    .custom-salary-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; margin-top:12px; }
    .custom-salary-field { display:grid; grid-template-columns:1fr 120px; gap:10px; align-items:center; padding:10px 12px; border:1px solid var(--line); border-radius:14px; background:#fbfcfa; }
    .custom-salary-field strong { font-size:12px; color:var(--text); }
    .custom-salary-field span { display:block; margin-top:2px; font-size:11px; color:var(--muted); font-weight:700; }
    .custom-salary-field input { min-height:36px; padding:8px 10px; border:1px solid var(--line); border-radius:10px; font-size:13px; font-weight:800; text-align:right; color:var(--text); background:#fff; }
    .employee-field { display:flex; flex-direction:column; gap:6px; margin-bottom:14px; }
    .employee-field:last-child { margin-bottom:0; }
    .employee-label { font-size:12px; font-weight:700; color:var(--text); }
    .employee-input, .employee-select, .employee-textarea { width:100%; min-height:44px; padding:10px 14px; border:1.5px solid var(--line); border-radius:14px; background:#fbfcfa; font-size:14px; font-weight:600; color:var(--text); outline:none; }
    .employee-textarea { min-height:110px; resize:vertical; }
    .employee-inline { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; }
    .employee-toggle { display:flex; align-items:center; justify-content:space-between; gap:16px; padding:14px 16px; border:1px solid var(--line); border-radius:16px; background:#f9fbf8; }
    .employee-toggle-copy { font-size:13px; color:var(--muted); font-weight:600; line-height:1.5; }
    .employee-toggle-copy strong { display:block; color:var(--text); font-size:14px; font-weight:800; margin-bottom:2px; }
    .employee-attendance-section { align-self:start; background:linear-gradient(180deg,#fff 0%,#f8fcf9 100%); }
    .attendance-stage-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    .attendance-muted-line { margin-top:8px; color:var(--muted); font-size:12px; font-weight:700; }
    .employee-switch { position:relative; width:48px; height:26px; flex-shrink:0; }
    .employee-switch input { display:none; }
    .employee-slider { position:absolute; inset:0; border-radius:999px; background:#d1d5db; cursor:pointer; transition:.2s ease; }
    .employee-slider::before { content:''; position:absolute; left:3px; top:3px; width:20px; height:20px; border-radius:50%; background:#fff; transition:.2s ease; box-shadow:0 1px 4px rgba(0,0,0,.18); }
    .employee-switch input:checked + .employee-slider { background:var(--green); }
    .employee-switch input:checked + .employee-slider::before { transform:translateX(22px); }
    .employee-actions { display:flex; justify-content:flex-end; gap:10px; flex-wrap:wrap; }
    .employee-btn { min-height:44px; padding:0 18px; border-radius:14px; font-size:13px; font-weight:800; text-decoration:none; display:inline-flex; align-items:center; justify-content:center; gap:8px; cursor:pointer; border:none; }
    .employee-btn-primary { background:var(--green); color:#fff; }
    .employee-btn-secondary { background:#fff; color:var(--text); border:1.5px solid var(--line); }
    @media (max-width:960px) { .employee-form-grid { grid-template-columns:1fr; } }
    @media (max-width:640px) { .employee-inline, .custom-salary-grid, .salary-mode-toggle { grid-template-columns:1fr; } .employee-form-header, .employee-form-body { padding:18px; } .employee-form-title { font-size:28px; } }
</style>
@endpush

@section('content')
@php
    $hrRouteBase = auth()->check() && auth()->user()->isHrManager() ? 'hr-manager.settings.hr' : 'admin.hr';
    $profile = $employee?->employeeProfile;
    $recordUser = $employee ?? $linkedUser ?? null;
    $salaryProfile = $recordUser?->salaryProfile;
    $attendanceProfile = $recordUser?->attendanceProfile;
    $attendanceEnabledValue = (string) old('attendance_enabled', $attendanceProfile?->attendance_enabled ? '1' : '0');
    $attendanceOfficeValue = old('attendance_office_location_id', $attendanceProfile?->office_location_id);
    $attendancePolicyValue = old('attendance_policy_id', $attendanceProfile?->attendance_policy_id);
    $attendanceStageValue = old('attendance_rollout_stage', $attendanceProfile?->attendance_rollout_stage ?? 'live');
    $attendanceEffectiveFrom = old('attendance_effective_from', $attendanceProfile?->effective_from?->format('Y-m-d') ?? $profile?->joining_date?->format('Y-m-d') ?? now()->format('Y-m-d'));
    $outsideRequestValue = old('attendance_outside_request', $attendanceProfile && $attendanceProfile->allow_outside_punch_requests !== null ? ($attendanceProfile->allow_outside_punch_requests ? '1' : '0') : 'inherit');
    $canAutoApplyAttendanceDefaults = ! $attendanceProfile && ! session()->hasOldInput();
    $isCustomSalaryProfile = $salaryProfile?->salaryStructure && str_starts_with((string) $salaryProfile->salaryStructure->name, '__employee__:');
    $salarySetupMode = old('salary_setup_mode', $isCustomSalaryProfile ? 'custom' : 'template');
    $customSalaryHeads = [
        'HRA' => 'House Rent Allowance',
        'TRAVEL' => 'Travel Allowance',
        'MEDICAL' => 'Medical Allowance',
        'SPECIAL' => 'Special Allowance',
        'BONUS' => 'Bonus / Incentive',
    ];
    $existingCustomComponents = $isCustomSalaryProfile
        ? $salaryProfile->salaryStructure->components->keyBy('code')
        : collect();
    $salaryStructureLabel = function ($structure, string $fallback = 'Select salary rule') {
        if (! $structure) {
            return $fallback;
        }

        return str_starts_with((string) $structure->name, '__employee__:')
            ? 'Custom breakup'
            : $structure->name;
    };
    $salaryTemplateOptions = $salaryStructures->reject(fn ($structure) => str_starts_with((string) $structure->name, '__employee__:'))->values();
    $salaryTemplatePayload = $salaryTemplateOptions->map(fn ($structure) => [
        'id' => $structure->id,
        'name' => $salaryStructureLabel($structure),
        'components' => $structure->components->map(fn ($component) => [
            'code' => $component->code,
            'label' => $component->label,
            'calc_type' => $component->calc_type,
            'value' => (float) $component->value,
            'display_order' => (int) $component->display_order,
        ])->values(),
    ])->values();
@endphp

<div class="employee-form-page">
    @include('attendance._flash')
    @include('admin.hr._nav')

    @if($errors->any())
        <div class="employee-form-card" style="padding:18px 22px; color:#9f2f20; border-color:#f3c7bd; background:#fff6f3;">
            <strong>Please fix the following:</strong>
            <ul style="margin:10px 0 0 18px;">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ $employee ? route($hrRouteBase . '.employees.update', $employee) : route($hrRouteBase . '.employees.store') }}">
        @csrf
        @if($employee) @method('PUT') @endif

        <section class="employee-form-card">
            <div class="employee-form-header">
                <div class="employee-form-kicker">Employee Master</div>
                <div class="employee-form-title">{{ $employee ? 'Update employee record' : 'Create employee record' }}</div>
                <div class="employee-form-subtitle">Reuse the current user system, complete HR details once, and keep attendance, salary, bank, KYC, and lifecycle in one linked record.</div>
            </div>

            <div class="employee-form-body">
                <div class="employee-toggle">
                    <div class="employee-toggle-copy"><strong>Enable Login</strong>Keep this on if this employee should use the CRM login. Turn it off for HR-only records linked to an inactive user account.</div>
                    <label class="employee-switch"><input type="checkbox" name="login_enabled" value="1" {{ old('login_enabled', $employee ? $employee->is_active : true) ? 'checked' : '' }}><span class="employee-slider"></span></label>
                </div>

                <div class="employee-form-grid">
                    <div class="employee-section">
                        <div class="employee-section-title">Identity</div>
                        @if(!$employee)
                            <div class="employee-field">
                                <label class="employee-label">Reuse Existing User</label>
                                <select name="existing_user_id" class="employee-select">
                                    <option value="">Create from fresh details</option>
                                    @foreach($existingUsers as $existingUser)
                                        <option value="{{ $existingUser->id }}" @selected((string) old('existing_user_id', $linkedUser?->id) === (string) $existingUser->id)>{{ $existingUser->name }} · {{ $existingUser->email }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        <div class="employee-inline">
                            <div class="employee-field"><label class="employee-label">Employee ID</label><input type="text" name="employee_code" class="employee-input" value="{{ old('employee_code', $profile?->employee_code) }}" placeholder="Auto if left blank"></div>
                            <div class="employee-field"><label class="employee-label">Joining Date</label><input type="date" name="joining_date" class="employee-input" value="{{ old('joining_date', $profile?->joining_date?->format('Y-m-d')) }}" required></div>
                        </div>
                        <div class="employee-field"><label class="employee-label">Full Name</label><input type="text" name="name" class="employee-input" value="{{ old('name', $recordUser?->name) }}" required></div>
                        <div class="employee-inline">
                            <div class="employee-field"><label class="employee-label">Email</label><input type="email" name="email" class="employee-input" value="{{ old('email', $recordUser?->email) }}" required></div>
                            <div class="employee-field"><label class="employee-label">Phone</label><input type="text" name="phone" class="employee-input" value="{{ old('phone', $recordUser?->phone) }}" required></div>
                        </div>
                        <div class="employee-field"><label class="employee-label">Password {{ $employee ? '(leave blank to keep current)' : '(auto generated if blank)' }}</label><input type="text" name="password" class="employee-input" placeholder="{{ $employee ? 'Only fill if you want to change it' : 'Optional initial password' }}"></div>
                    </div>

                    <div class="employee-section">
                        <div class="employee-section-title">Job Details</div>
                        <div class="employee-inline">
                            <div class="employee-field"><label class="employee-label">Role</label><select name="role_id" class="employee-select js-employee-role" required><option value="">Select role</option>@foreach($roles as $role)<option value="{{ $role->id }}" data-name="{{ strtolower($role->name) }}" data-slug="{{ strtolower($role->slug ?? '') }}" @selected((string) old('role_id', $recordUser?->role_id) === (string) $role->id)>{{ $role->name }}</option>@endforeach</select></div>
                            <div class="employee-field"><label class="employee-label">Reporting Manager</label><select name="manager_id" class="employee-select js-employee-manager"><option value="">No reporting manager</option>@foreach($managers as $manager)<option value="{{ $manager->id }}" data-role="{{ strtolower($manager->role->name ?? '') }}" data-role-slug="{{ strtolower($manager->role->slug ?? '') }}" @selected((string) old('manager_id', $recordUser?->manager_id) === (string) $manager->id)>{{ $manager->name }}</option>@endforeach</select></div>
                        </div>
                        <div class="employee-inline">
                            <div class="employee-field"><label class="employee-label">Department</label><select name="department_id" class="employee-select js-employee-department"><option value="">Select department</option>@foreach($departments as $department)<option value="{{ $department->id }}" data-name="{{ strtolower($department->name) }}" @selected((string) old('department_id', $profile?->department_id) === (string) $department->id)>{{ $department->name }}</option>@endforeach</select></div>
                            <div class="employee-field"><label class="employee-label">New Department (optional)</label><input type="text" name="new_department" class="employee-input" value="{{ old('new_department') }}" placeholder="Create and use new department"></div>
                        </div>
                        <div class="employee-inline">
                            <div class="employee-field"><label class="employee-label">Designation</label><select name="designation_id" class="employee-select js-employee-designation"><option value="">Select designation</option>@foreach($designations as $designation)<option value="{{ $designation->id }}" data-name="{{ strtolower($designation->name) }}" @selected((string) old('designation_id', $profile?->designation_id) === (string) $designation->id)>{{ $designation->name }}</option>@endforeach</select></div>
                            <div class="employee-field"><label class="employee-label">New Designation (optional)</label><input type="text" name="new_designation" class="employee-input js-employee-new-designation" value="{{ old('new_designation') }}" placeholder="Create and use new designation"></div>
                        </div>
                        <div class="employee-inline">
                            <div class="employee-field"><label class="employee-label">Employment Status</label><select name="employment_status" class="employee-select" required><option value="active" @selected(old('employment_status', $profile?->employment_status) === 'active')>Active</option><option value="on_notice" @selected(old('employment_status', $profile?->employment_status) === 'on_notice')>On Notice</option><option value="resigned" @selected(old('employment_status', $profile?->employment_status) === 'resigned')>Resigned</option><option value="terminated" @selected(old('employment_status', $profile?->employment_status) === 'terminated')>Terminated</option><option value="long_leave" @selected(old('employment_status', $profile?->employment_status) === 'long_leave')>Long Leave</option></select></div>
                            <div class="employee-field"><label class="employee-label">Probation End</label><input type="date" name="probation_end_date" class="employee-input" value="{{ old('probation_end_date', $profile?->probation_end_date?->format('Y-m-d')) }}"></div>
                        </div>
                        <div class="employee-field"><label class="employee-label">Salary Reminder Day</label><input type="number" name="salary_day_of_month" class="employee-input" min="1" max="31" value="{{ old('salary_day_of_month', $profile?->salary_day_of_month ?? 1) }}"></div>
                    </div>

                    <div class="employee-section">
                        <div class="employee-section-title">Bank & KYC</div>
                        <div class="employee-field"><label class="employee-label">Account Holder Name</label><input type="text" name="account_holder_name" class="employee-input" value="{{ old('account_holder_name', $profile?->account_holder_name) }}" required></div>
                        <div class="employee-field"><label class="employee-label">Bank Account Number</label><input type="text" name="bank_account_number" class="employee-input" value="{{ old('bank_account_number', $profile?->bank_account_number) }}" required></div>
                        <div class="employee-inline">
                            <div class="employee-field"><label class="employee-label">IFSC</label><input type="text" name="ifsc_code" class="employee-input" value="{{ old('ifsc_code', $profile?->ifsc_code) }}" required></div>
                            <div class="employee-field"><label class="employee-label">PAN</label><input type="text" name="pan_number" class="employee-input" value="{{ old('pan_number', $profile?->pan_number) }}" required></div>
                        </div>
                        <div class="employee-field"><label class="employee-label">Aadhaar</label><input type="text" name="aadhaar_number" class="employee-input" value="{{ old('aadhaar_number', $profile?->aadhaar_number) }}" required></div>
                    </div>

                    <div class="employee-section employee-salary-section">
                        <div class="employee-section-title">Salary Setup</div>
                        <div class="employee-section-help">Template choose karo ya custom breakup banao. Employee create hote hi payroll-ready ho jayega.</div>
                        <div class="salary-mode-toggle">
                            <label class="salary-mode-option">
                                <input type="radio" name="salary_setup_mode" value="template" class="js-salary-mode" @checked($salarySetupMode === 'template')>
                                <span class="salary-mode-label">Use Template</span>
                            </label>
                            <label class="salary-mode-option">
                                <input type="radio" name="salary_setup_mode" value="custom" class="js-salary-mode" @checked($salarySetupMode === 'custom')>
                                <span class="salary-mode-label">Custom Breakup</span>
                            </label>
                        </div>
                        <div class="salary-mode-panel js-template-panel">
                            <div class="employee-field">
                                <label class="employee-label">Salary Template</label>
                                <select name="salary_structure_id" class="employee-select js-salary-template">
                                    <option value="">Select salary template</option>
                                    @foreach($salaryTemplateOptions as $structure)
                                        <option value="{{ $structure->id }}" @selected((string) old('salary_structure_id', $salaryProfile?->salary_structure_id) === (string) $structure->id)>{{ $salaryStructureLabel($structure) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="employee-inline">
                            <div class="employee-field">
                                <label class="employee-label">Monthly Salary</label>
                                <input type="number" name="monthly_salary" class="employee-input js-monthly-salary" min="0.01" step="0.01" value="{{ old('monthly_salary', $salaryProfile?->base_salary) }}" required>
                            </div>
                            <div class="employee-field">
                                <label class="employee-label">Apply From</label>
                                <input type="date" name="salary_effective_from" class="employee-input" value="{{ old('salary_effective_from', $salaryProfile?->effective_from?->format('Y-m-d') ?? now()->format('Y-m-d')) }}" required>
                            </div>
                        </div>
                        <div class="salary-mode-panel js-custom-panel">
                            <div class="custom-salary-grid">
                                @foreach($customSalaryHeads as $code => $label)
                                    @php
                                        $oldCustomValue = old('custom_components.' . $code . '.value');
                                        $existingCustomValue = $existingCustomComponents->get($code)?->value;
                                        $defaultCustomValue = $oldCustomValue ?? ($existingCustomValue !== null ? (float) $existingCustomValue : 0);
                                    @endphp
                                    <label class="custom-salary-field">
                                        <span>
                                            <strong>{{ $code }}</strong>
                                            <span>{{ $label }}</span>
                                        </span>
                                        <input type="number" min="0" step="0.01" name="custom_components[{{ $code }}][value]" value="{{ $defaultCustomValue }}" class="js-custom-component" data-code="{{ $code }}" data-label="{{ $label }}">
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div class="employee-salary-preview js-salary-preview">
                            <div class="employee-salary-preview-head">
                                <strong class="js-salary-preview-title">Template Breakup</strong>
                                <span class="js-salary-preview-total">Total: Rs 0.00</span>
                            </div>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Head</th>
                                        <th>Type</th>
                                        <th style="text-align:right;">Amount</th>
                                    </tr>
                                </thead>
                                <tbody class="js-salary-preview-body">
                                    <tr><td colspan="4" class="employee-salary-empty">Salary template select karo to breakup yahan dikhega.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="employee-section employee-attendance-section">
                        <div class="employee-section-title">Attendance Setup</div>
                        <div class="employee-section-help">Employee create/update ke saath attendance mapping bhi ready ho jayegi. Separate assign screen sirf bulk changes ke liye rahega.</div>
                        <input type="hidden" name="attendance_enabled" value="0">
                        <div class="employee-toggle" style="margin-bottom:14px;">
                            <div class="employee-toggle-copy">
                                <strong>Attendance On</strong>
                                User ko punch-in / punch-out access dena hai to on rakho.
                            </div>
                            <label class="employee-switch">
                                <input type="checkbox" name="attendance_enabled" value="1" class="js-attendance-enabled" @checked($attendanceEnabledValue === '1')>
                                <span class="employee-slider"></span>
                            </label>
                        </div>
                        <div class="employee-inline">
                            <div class="employee-field">
                                <label class="employee-label">Office</label>
                                <select name="attendance_office_location_id" class="employee-select js-attendance-office">
                                    <option value="">Select office</option>
                                    @foreach($officeLocations as $office)
                                        <option value="{{ $office->id }}" @selected((string) $attendanceOfficeValue === (string) $office->id)>{{ $office->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="employee-field">
                                <label class="employee-label">Attendance Rule</label>
                                <select name="attendance_policy_id" class="employee-select js-attendance-policy">
                                    <option value="">Select rule</option>
                                    @foreach($attendancePolicies as $policy)
                                        <option value="{{ $policy->id }}" data-office="{{ $policy->office_location_id }}" @selected((string) $attendancePolicyValue === (string) $policy->id)>{{ $policy->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="attendance-stage-row">
                            <div class="employee-field">
                                <label class="employee-label">Mode</label>
                                <select name="attendance_rollout_stage" class="employee-select js-attendance-stage">
                                    <option value="live" @selected($attendanceStageValue === 'live')>Active</option>
                                    <option value="pilot" @selected($attendanceStageValue === 'pilot')>Testing</option>
                                    <option value="disabled" @selected($attendanceStageValue === 'disabled')>Disabled</option>
                                </select>
                            </div>
                            <div class="employee-field">
                                <label class="employee-label">Effective From</label>
                                <input type="date" name="attendance_effective_from" class="employee-input js-attendance-effective" value="{{ $attendanceEffectiveFrom }}">
                            </div>
                        </div>
                        <div class="employee-field">
                            <label class="employee-label">Outside Punch Request</label>
                            <select name="attendance_outside_request" class="employee-select js-attendance-outside">
                                <option value="inherit" @selected($outsideRequestValue === 'inherit')>Policy Default</option>
                                <option value="1" @selected($outsideRequestValue === '1')>Allow</option>
                                <option value="0" @selected($outsideRequestValue === '0')>Block</option>
                            </select>
                            <div class="attendance-muted-line">Recommended: Policy Default. Special user ke liye hi Allow/Block change karo.</div>
                        </div>
                    </div>

                    <div class="employee-section">
                        <div class="employee-section-title">Emergency & Address</div>
                        <div class="employee-inline">
                            <div class="employee-field"><label class="employee-label">Emergency Contact Name</label><input type="text" name="emergency_contact_name" class="employee-input" value="{{ old('emergency_contact_name', $profile?->emergency_contact_name) }}"></div>
                            <div class="employee-field"><label class="employee-label">Emergency Contact Phone</label><input type="text" name="emergency_contact_phone" class="employee-input" value="{{ old('emergency_contact_phone', $profile?->emergency_contact_phone) }}"></div>
                        </div>
                        <div class="employee-field"><label class="employee-label">Current Address</label><textarea name="current_address" class="employee-textarea">{{ old('current_address', $profile?->current_address) }}</textarea></div>
                        <div class="employee-field"><label class="employee-label">Permanent Address</label><textarea name="permanent_address" class="employee-textarea">{{ old('permanent_address', $profile?->permanent_address) }}</textarea></div>
                        <div class="employee-field"><label class="employee-label">HR Notes</label><textarea name="notes" class="employee-textarea">{{ old('notes', $profile?->notes) }}</textarea></div>
                    </div>
                </div>

                <div class="employee-actions">
                    @if($employee)
                        <a href="{{ route($hrRouteBase . '.employees.show', $employee) }}" class="employee-btn employee-btn-secondary">Cancel</a>
                    @else
                        <a href="{{ route($hrRouteBase . '.employees.index') }}" class="employee-btn employee-btn-secondary">Cancel</a>
                    @endif
                    <button type="submit" class="employee-btn employee-btn-primary">{{ $employee ? 'Update Employee' : 'Save Employee' }}</button>
                </div>
            </div>
        </section>
    </form>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const salaryTemplates = @json($salaryTemplatePayload);
        const attendanceDefaults = @json($attendanceDefaults);
        const canAutoApplyAttendanceDefaults = @json($canAutoApplyAttendanceDefaults);
        const roleSelect = document.querySelector('.js-employee-role');
        const managerSelect = document.querySelector('.js-employee-manager');
        const departmentSelect = document.querySelector('.js-employee-department');
        const designationSelect = document.querySelector('.js-employee-designation');
        const newDesignationInput = document.querySelector('.js-employee-new-designation');
        const salaryModeInputs = [...document.querySelectorAll('.js-salary-mode')];
        const templatePanel = document.querySelector('.js-template-panel');
        const customPanel = document.querySelector('.js-custom-panel');
        const salaryTemplateSelect = document.querySelector('.js-salary-template');
        const monthlySalaryInput = document.querySelector('.js-monthly-salary');
        const customComponentInputs = [...document.querySelectorAll('.js-custom-component')];
        const salaryPreviewBody = document.querySelector('.js-salary-preview-body');
        const salaryPreviewTotal = document.querySelector('.js-salary-preview-total');
        const salaryPreviewTitle = document.querySelector('.js-salary-preview-title');
        const attendanceEnabledInput = document.querySelector('.js-attendance-enabled');
        const attendanceOfficeSelect = document.querySelector('.js-attendance-office');
        const attendancePolicySelect = document.querySelector('.js-attendance-policy');
        const attendanceStageSelect = document.querySelector('.js-attendance-stage');
        const attendanceEffectiveInput = document.querySelector('.js-attendance-effective');
        const attendanceOutsideSelect = document.querySelector('.js-attendance-outside');

        if (!departmentSelect || !designationSelect) {
            return;
        }

        const allDesignationOptions = Array.from(designationSelect.options).map((option) => ({
            value: option.value,
            text: option.textContent,
            name: (option.dataset.name || option.textContent || '').toLowerCase(),
            selected: option.selected,
        }));
        const allManagerOptions = managerSelect ? Array.from(managerSelect.options).map((option) => ({
            value: option.value,
            text: option.textContent,
            role: (option.dataset.role || '').toLowerCase(),
            roleSlug: (option.dataset.roleSlug || '').toLowerCase(),
            selected: option.selected,
        })) : [];

        const departmentKeywords = {
            marketing: ['marketing'],
            sales: ['sales', 'manager'],
            finance: ['finance', 'account'],
            hr: ['hr', 'human resource', 'recruiter'],
            crm: ['crm'],
        };

        const getDepartmentGroup = () => {
            const label = departmentSelect.options[departmentSelect.selectedIndex]?.textContent?.toLowerCase() || '';

            if (label.includes('marketing')) return 'marketing';
            if (label.includes('sales')) return 'sales';
            if (label.includes('finance') || label.includes('account')) return 'finance';
            if (label.includes('hr') || label.includes('human')) return 'hr';
            if (label.includes('crm')) return 'crm';

            return '';
        };
        const getSelectedRole = () => {
            const selected = roleSelect?.options[roleSelect.selectedIndex];
            const name = (selected?.dataset.name || selected?.textContent || '').toLowerCase();
            const slug = (selected?.dataset.slug || '').toLowerCase();
            const label = selected?.textContent?.trim() || '';

            return { name, slug, label };
        };
        const roleIncludes = (needle) => {
            const role = getSelectedRole();
            return role.name.includes(needle) || role.slug.includes(needle.replace(/\s+/g, '_')) || role.slug.includes(needle.replace(/\s+/g, '-'));
        };
        const getRoleGroup = () => {
            if (roleIncludes('marketing executive')) return 'marketing_executive';
            if (roleIncludes('marketing manager')) return 'marketing_manager';
            if (roleIncludes('marketing')) return 'marketing';

            return '';
        };
        const syncAttendanceState = () => {
            const enabled = Boolean(attendanceEnabledInput?.checked);

            [attendanceOfficeSelect, attendancePolicySelect, attendanceStageSelect, attendanceEffectiveInput, attendanceOutsideSelect].forEach((field) => {
                if (!field) return;
                field.disabled = !enabled;
            });

            if (attendanceOfficeSelect) attendanceOfficeSelect.required = enabled;
            if (attendancePolicySelect) attendancePolicySelect.required = enabled;
            if (attendanceStageSelect) attendanceStageSelect.required = enabled;
            if (attendanceEffectiveInput) attendanceEffectiveInput.required = enabled;

            if (!enabled && attendanceStageSelect) {
                attendanceStageSelect.value = 'disabled';
            }
        };
        const applyAttendanceDefaultForRole = () => {
            if (!canAutoApplyAttendanceDefaults || !attendanceEnabledInput) {
                syncAttendanceState();
                return;
            }

            const role = getSelectedRole();
            const normalizedRoleSlug = (role.slug || '').replace(/-/g, '_');
            const defaultConfig = attendanceDefaults[normalizedRoleSlug] || attendanceDefaults[role.slug] || null;

            if (!defaultConfig) {
                syncAttendanceState();
                return;
            }

            attendanceEnabledInput.checked = Boolean(defaultConfig.enabled);
            if (attendanceOfficeSelect && defaultConfig.office_id) attendanceOfficeSelect.value = defaultConfig.office_id;
            if (attendancePolicySelect && defaultConfig.policy_id) attendancePolicySelect.value = defaultConfig.policy_id;
            if (attendanceStageSelect) attendanceStageSelect.value = defaultConfig.stage || (defaultConfig.enabled ? 'live' : 'disabled');
            if (attendanceOutsideSelect) attendanceOutsideSelect.value = defaultConfig.outside || 'inherit';
            if (attendanceEffectiveInput && !attendanceEffectiveInput.value) {
                const joiningDateInput = document.querySelector('input[name="joining_date"]');
                attendanceEffectiveInput.value = joiningDateInput?.value || new Date().toISOString().slice(0, 10);
            }
            syncAttendanceState();
        };

        const optionMatchesDepartment = (option, group) => {
            if (!option.value || !group) {
                return true;
            }

            return (departmentKeywords[group] || []).some((keyword) => option.name.includes(keyword));
        };
        const selectOptionContaining = (select, text) => {
            const needle = (text || '').toLowerCase();
            const match = Array.from(select.options).find((option) => {
                const name = (option.dataset.name || option.textContent || '').toLowerCase();
                return option.value && name.includes(needle);
            });

            if (match) {
                select.value = match.value;
                return true;
            }

            return false;
        };
        const selectExactDesignation = (label) => {
            const needle = (label || '').toLowerCase().trim();
            const match = Array.from(designationSelect.options).find((option) => option.value && (option.dataset.name || option.textContent || '').toLowerCase().trim() === needle);

            if (match) {
                designationSelect.value = match.value;
                if (newDesignationInput && !newDesignationInput.dataset.userTouched) {
                    newDesignationInput.value = '';
                }
                return true;
            }

            designationSelect.value = '';
            if (newDesignationInput && !newDesignationInput.dataset.userTouched && needle) {
                newDesignationInput.value = label;
            }
            return false;
        };

        const rebuildDesignations = () => {
            const selectedValue = designationSelect.value;
            const group = getDepartmentGroup();
            const filtered = allDesignationOptions.filter((option) => optionMatchesDepartment(option, group));
            const options = group ? filtered : allDesignationOptions;

            designationSelect.innerHTML = '';
            options.forEach((option) => {
                const node = document.createElement('option');
                node.value = option.value;
                node.textContent = option.text;
                node.dataset.name = option.name;
                designationSelect.appendChild(node);
            });

            if (options.some((option) => option.value === selectedValue)) {
                designationSelect.value = selectedValue;
            } else {
                designationSelect.value = '';
            }
        };
        const managerAllowedForRole = (option, roleGroup) => {
            if (!option.value || !roleGroup) {
                return true;
            }

            const roleText = `${option.role} ${option.roleSlug}`.replace(/[_-]+/g, ' ');

            if (roleGroup === 'marketing_executive') {
                return roleText.includes('marketing manager') || roleText.includes('sales manager') || roleText.includes('admin');
            }

            if (roleGroup === 'marketing_manager' || roleGroup === 'marketing') {
                return roleText.includes('admin') || roleText.includes('sales manager');
            }

            return true;
        };
        const rebuildManagers = () => {
            if (!managerSelect) {
                return;
            }

            const selectedValue = managerSelect.value;
            const roleGroup = getRoleGroup();
            const options = allManagerOptions.filter((option) => managerAllowedForRole(option, roleGroup));

            managerSelect.innerHTML = '';
            options.forEach((option) => {
                const node = document.createElement('option');
                node.value = option.value;
                node.textContent = option.text;
                node.dataset.role = option.role;
                node.dataset.roleSlug = option.roleSlug;
                managerSelect.appendChild(node);
            });

            managerSelect.value = options.some((option) => option.value === selectedValue) ? selectedValue : '';
        };
        const applyRoleDefaults = () => {
            const role = getSelectedRole();
            const roleGroup = getRoleGroup();

            if (roleGroup) {
                selectOptionContaining(departmentSelect, 'marketing');
                rebuildDesignations();
                selectExactDesignation(roleGroup === 'marketing' ? 'Marketing' : role.label);
            } else {
                rebuildDesignations();
            }

            rebuildManagers();
            applyAttendanceDefaultForRole();
        };

        newDesignationInput?.addEventListener('input', () => {
            newDesignationInput.dataset.userTouched = '1';
        });

        roleSelect?.addEventListener('change', applyRoleDefaults);
        attendanceEnabledInput?.addEventListener('change', syncAttendanceState);
        departmentSelect.addEventListener('change', () => {
            rebuildDesignations();
            rebuildManagers();
        });
        applyRoleDefaults();
        syncAttendanceState();

        const formatMoney = (amount) => `Rs ${Number(amount || 0).toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        })}`;
        const currentSalaryMode = () => salaryModeInputs.find((input) => input.checked)?.value || 'template';
        const setSalaryModeState = () => {
            const mode = currentSalaryMode();
            if (templatePanel) templatePanel.hidden = mode !== 'template';
            if (customPanel) customPanel.hidden = mode !== 'custom';
            if (salaryTemplateSelect) salaryTemplateSelect.required = mode === 'template';
            renderSalaryPreview();
        };
        const renderRows = (components, total, title) => {
            const fixedTotal = components.reduce((sum, component) => (
                component.calc_type === 'fixed' ? sum + Number(component.value || 0) : sum
            ), 0);
            const basic = total - fixedTotal;
            const rows = components.map((component) => {
                const type = component.calc_type === 'fixed' ? 'Fixed' : '% of Basic';
                const amount = component.calc_type === 'fixed'
                    ? Number(component.value || 0)
                    : basic * (Number(component.value || 0) / 100);

                return `<tr>
                    <td><strong>${component.code || '-'}</strong></td>
                    <td>${component.label || '-'}</td>
                    <td>${type}</td>
                    <td class="amount">${formatMoney(amount)}</td>
                </tr>`;
            }).join('');

            if (salaryPreviewTitle) salaryPreviewTitle.textContent = title;
            salaryPreviewBody.innerHTML = `${rows}
                <tr class="basic-row">
                    <td><strong>BASIC</strong></td>
                    <td>Basic Salary</td>
                    <td>Auto</td>
                    <td class="amount" style="color:${basic < 0 ? '#c34b32' : 'var(--text)'}">${formatMoney(basic)}</td>
                </tr>`;
        };
        const renderSalaryPreview = () => {
            if (!salaryTemplateSelect || !monthlySalaryInput || !salaryPreviewBody || !salaryPreviewTotal) {
                return;
            }

            const mode = currentSalaryMode();
            const total = Number(monthlySalaryInput.value || 0);

            salaryPreviewTotal.textContent = `Total: ${formatMoney(total)}`;

            if (mode === 'custom') {
                const components = customComponentInputs.map((input, index) => ({
                    code: input.dataset.code || `HEAD${index + 1}`,
                    label: input.dataset.label || input.dataset.code || `Head ${index + 1}`,
                    calc_type: 'fixed',
                    value: Number(input.value || 0),
                }));
                renderRows(components, total, 'Custom Breakup');
                return;
            }

            const selected = salaryTemplates.find((template) => String(template.id) === String(salaryTemplateSelect.value));
            const components = selected?.components || [];

            if (!selected || components.length === 0) {
                if (salaryPreviewTitle) salaryPreviewTitle.textContent = 'Template Breakup';
                salaryPreviewBody.innerHTML = '<tr><td colspan="4" class="employee-salary-empty">Salary template select karo to breakup yahan dikhega.</td></tr>';
                return;
            }

            renderRows(components, total, 'Template Breakup');
        };

        salaryModeInputs.forEach((input) => input.addEventListener('change', setSalaryModeState));
        salaryTemplateSelect?.addEventListener('change', renderSalaryPreview);
        monthlySalaryInput?.addEventListener('input', renderSalaryPreview);
        customComponentInputs.forEach((input) => input.addEventListener('input', renderSalaryPreview));
        setSalaryModeState();
    });
</script>
@endpush

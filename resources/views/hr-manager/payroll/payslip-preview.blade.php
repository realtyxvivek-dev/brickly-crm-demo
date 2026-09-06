@extends('layouts.app')

@section('title', 'Payslip Preview')
@section('page-title', 'Payslip Preview')

@section('content')
@php
    $employee = $payslip->user;
    $profile = $employee?->employeeProfile;
    $period = \Carbon\Carbon::create((int) $payslip->year, (int) $payslip->month, 1);
    $money = fn ($amount) => 'Rs ' . number_format((float) $amount, 2);
    $num = fn ($value) => number_format((float) $value, 1);
    $daysInMonth = $period->daysInMonth;
    $payableDays = (float) ($attendance['payable_days'] ?? 0);
    $perDay = $daysInMonth > 0 ? ((float) $payslip->gross_pay / $daysInMonth) : 0;
    $statusLabel = app(\App\Services\PayrollWorkflowService::class)->statusLabel($payslip->status);
    $dayRows = [
        ['label' => 'Total Month Days', 'value' => $daysInMonth, 'note' => 'Calendar days'],
        ['label' => 'Payable Days', 'value' => $num($payableDays), 'note' => 'Salary is calculated on this'],
        ['label' => 'Present / Late', 'value' => $num($attendance['present_days'] ?? 0), 'note' => 'Full payable days'],
        ['label' => 'Half Day', 'value' => $num($attendance['half_days'] ?? 0), 'note' => 'Half payable'],
        ['label' => 'Absent', 'value' => $num($attendance['absent_days'] ?? 0), 'note' => 'Deducted'],
        ['label' => 'Paid Leave', 'value' => $num($attendance['paid_leave_days'] ?? 0), 'note' => 'Payable leave'],
        ['label' => 'Unpaid Leave', 'value' => $num($attendance['unpaid_leave_days'] ?? 0), 'note' => 'Deducted leave'],
        ['label' => 'Week Off / Holiday', 'value' => $num(($attendance['weekoff_days'] ?? 0) + ($attendance['holiday_days'] ?? 0)), 'note' => 'Payable off days'],
        ['label' => 'Late Count', 'value' => (int) ($attendance['late_count'] ?? 0), 'note' => 'Late marks'],
        ['label' => 'Late Penalty Days', 'value' => $num($attendance['late_penalty_days'] ?? 0), 'note' => 'Deducted if policy applies'],
    ];
    $attendanceStatusMeta = [
        \App\Models\AttendanceRecord::STATUS_ABSENT => ['label' => 'Absent', 'class' => 'danger', 'impact' => 'Full day deducted'],
        \App\Models\AttendanceRecord::STATUS_HALF_DAY => ['label' => 'Half Day', 'class' => 'warning', 'impact' => 'Half day deducted'],
        \App\Models\AttendanceRecord::STATUS_LEAVE => ['label' => 'Leave', 'class' => 'info', 'impact' => 'Leave day'],
        \App\Models\AttendanceRecord::STATUS_LATE => ['label' => 'Late', 'class' => 'late', 'impact' => 'Present but late'],
        \App\Models\AttendanceRecord::STATUS_WEEK_OFF => ['label' => 'Week Off', 'class' => 'neutral', 'impact' => 'Paid off day'],
        \App\Models\AttendanceRecord::STATUS_HOLIDAY => ['label' => 'Holiday', 'class' => 'neutral', 'impact' => 'Paid holiday'],
    ];
    $attendanceDetails = collect($attendanceDetails ?? []);
    $regularizationsByDate = collect($regularizationsByDate ?? []);
    $leaveTypes = collect($leaveTypes ?? []);
    $canEditPayrollAttendance = (bool) ($canEditPayrollAttendance ?? false);
    $salaryImpactStatuses = [
        \App\Models\AttendanceRecord::STATUS_ABSENT,
        \App\Models\AttendanceRecord::STATUS_HALF_DAY,
        \App\Models\AttendanceRecord::STATUS_LEAVE,
        \App\Models\AttendanceRecord::STATUS_LATE,
        \App\Models\AttendanceRecord::STATUS_WEEK_OFF,
        \App\Models\AttendanceRecord::STATUS_HOLIDAY,
    ];
    $visibleAttendanceDetails = $attendanceDetails
        ->filter(function ($record) use ($salaryImpactStatuses, $regularizationsByDate) {
            return in_array($record->status, $salaryImpactStatuses, true)
                || $record->hasActiveManualOverride()
                || $regularizationsByDate->has($record->attendance_date?->toDateString());
        })
        ->values();
    $absentDateList = $attendanceDetails
        ->where('status', \App\Models\AttendanceRecord::STATUS_ABSENT)
        ->pluck('attendance_date')
        ->map(fn ($date) => optional($date)->format('d M Y'))
        ->filter()
        ->values();
    $manualOverrideCount = $attendanceDetails->filter(fn ($record) => $record->hasActiveManualOverride())->count();
    $pendingRegularizationCount = $regularizationsByDate
        ->flatten(1)
        ->filter(fn ($regularization) => $regularization?->status === 'pending')
        ->count();
    $statusCount = fn ($status) => $attendanceDetails->where('status', $status)->count();
@endphp

<style>
    .preview-shell { display:grid; gap:18px; }
    .preview-card { background:#fff; border:1px solid #e5ded4; border-radius:18px; padding:20px; box-shadow:0 10px 22px rgba(15,45,31,.05); }
    .preview-head { display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap; align-items:flex-start; }
    .preview-kicker { margin:0 0 6px; font-size:11px; letter-spacing:.14em; text-transform:uppercase; color:#66756f; font-weight:900; }
    .preview-title { margin:0; font-size:28px; line-height:1.08; font-weight:900; color:#061f17; }
    .preview-sub { margin-top:8px; color:#60716a; font-size:14px; }
    .preview-actions { display:flex; flex-wrap:wrap; gap:10px; }
    .preview-actions form { margin:0; }
    .preview-btn { display:inline-flex; align-items:center; justify-content:center; min-height:40px; padding:0 14px; border-radius:12px; border:1px solid #d8e1db; font-weight:800; font-size:13px; text-decoration:none; color:#0f5137; background:#fff; }
    .preview-btn.primary { background:#0f5b42; color:#fff; border-color:#0f5b42; }
    .preview-btn.dark { background:#101828; color:#fff; border-color:#101828; }
    .preview-btn.warn { background:#fff7ed; color:#9a3412; border-color:#fed7aa; }
    .preview-btn:disabled { opacity:.55; cursor:not-allowed; }
    .preview-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; }
    .preview-days { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:10px; }
    .preview-metric { border:1px solid #e8eee9; border-radius:14px; padding:14px; background:#fbfdfb; min-height:96px; }
    .preview-label { font-size:11px; letter-spacing:.10em; text-transform:uppercase; color:#68776f; font-weight:900; }
    .preview-value { margin-top:8px; font-size:24px; font-weight:900; color:#082819; }
    .preview-note { margin-top:5px; color:#66756f; font-size:12px; }
    .preview-table { width:100%; border-collapse:collapse; }
    .preview-table th, .preview-table td { padding:12px 14px; border-bottom:1px solid #edf1ee; text-align:left; font-size:14px; }
    .preview-table th { background:#f7faf8; color:#68776f; font-size:11px; letter-spacing:.12em; text-transform:uppercase; }
    .amount { text-align:right !important; font-weight:900; white-space:nowrap; }
    .attendance-alert { border:1px solid #fecaca; background:#fff7f7; color:#991b1b; border-radius:14px; padding:12px 14px; font-size:13px; font-weight:800; }
    .attendance-detail-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:10px; }
    .attendance-detail-card { border:1px solid #e8eee9; border-radius:15px; background:#fbfdfb; padding:13px; }
    .attendance-detail-card.override { border-color:#86efac; background:#f0fdf4; }
    .attendance-detail-card.pending-reg { border-color:#fcd34d; }
    .attendance-detail-top { display:flex; align-items:flex-start; justify-content:space-between; gap:10px; }
    .attendance-date { font-size:16px; font-weight:900; color:#071c14; }
    .attendance-day { margin-top:2px; font-size:12px; font-weight:800; color:#64736d; }
    .attendance-pill { display:inline-flex; align-items:center; border-radius:999px; padding:5px 9px; font-size:11px; font-weight:900; white-space:nowrap; }
    .attendance-pill.danger { background:#fee2e2; color:#b91c1c; }
    .attendance-pill.warning { background:#fef3c7; color:#92400e; }
    .attendance-pill.info { background:#e0f2fe; color:#075985; }
    .attendance-pill.late { background:#ede9fe; color:#5b21b6; }
    .attendance-pill.neutral { background:#eef2f7; color:#475569; }
    .attendance-mini { margin-top:11px; display:grid; gap:5px; font-size:12px; color:#52635c; font-weight:700; }
    .attendance-empty { border:1px dashed #dce5df; border-radius:15px; background:#fbfdfb; padding:18px; color:#60716a; font-size:13px; font-weight:800; text-align:center; }
    .attendance-badges { margin-top:8px; display:flex; flex-wrap:wrap; gap:6px; }
    .attendance-badge { display:inline-flex; align-items:center; border-radius:999px; padding:4px 8px; font-size:11px; font-weight:900; }
    .attendance-badge.override { background:#dcfce7; color:#166534; }
    .attendance-badge.reg { background:#fef3c7; color:#92400e; }
    .attendance-form { margin-top:12px; border-top:1px dashed #d8e1db; padding-top:12px; display:grid; gap:9px; }
    .attendance-form summary { cursor:pointer; font-size:12px; font-weight:900; color:#0f5b42; }
    .attendance-form-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:10px; }
    .attendance-form input, .attendance-form select, .attendance-form textarea { width:100%; border:1px solid #d8e1db; border-radius:10px; padding:9px 10px; font-size:12px; font-weight:700; background:#fff; }
    .attendance-form textarea { grid-column:1/-1; min-height:64px; resize:vertical; }
    .attendance-form-actions { display:flex; flex-wrap:wrap; gap:8px; }
    .attendance-mini-btn { border:1px solid #d8e1db; border-radius:10px; padding:8px 10px; background:#fff; color:#0f5137; font-size:12px; font-weight:900; }
    .attendance-mini-btn.primary { background:#0f5b42; color:#fff; border-color:#0f5b42; }
    .attendance-mini-btn.danger { color:#b91c1c; border-color:#fecaca; }
    .summary-strip { display:grid; grid-template-columns:repeat(6,minmax(0,1fr)); gap:10px; margin-top:14px; }
    .summary-pill { border:1px solid #e8eee9; border-radius:14px; padding:12px; background:#fbfdfb; }
    .summary-pill strong { display:block; margin-top:4px; color:#071c14; font-size:18px; }
    .net-strip { background:#0f5b42; color:#fff; border-radius:18px; padding:18px 20px; display:flex; justify-content:space-between; gap:14px; flex-wrap:wrap; align-items:center; }
    .net-strip .preview-label, .net-strip .preview-note { color:#dcefe7; }
    .net-strip .preview-value { color:#fff; font-size:30px; }
    @media (max-width:1100px){ .preview-grid,.summary-strip{grid-template-columns:repeat(2,minmax(0,1fr));} .preview-days{grid-template-columns:repeat(2,minmax(0,1fr));} }
    @media (max-width:560px){ .preview-grid,.preview-days,.summary-strip,.attendance-form-grid{grid-template-columns:1fr;} .preview-title{font-size:24px;} }
</style>

<div class="preview-shell">
    <section class="preview-card">
        <div class="preview-head">
            <div>
                <p class="preview-kicker">Payroll Preview</p>
                <h1 class="preview-title">{{ $employee?->name ?: 'Employee' }} - {{ $period->format('F Y') }}</h1>
                <div class="preview-sub">
                    {{ $profile?->employee_code ?: 'No employee code' }} |
                    {{ $profile?->designation?->name ?: $employee?->role?->name ?: 'No designation' }} |
                    Status: {{ $statusLabel }}
                </div>
            </div>
            <div class="preview-actions">
                <a href="{{ route('hr-manager.payroll.index', ['year' => $payslip->year, 'month' => $payslip->month]) }}" class="preview-btn">Back to Payroll</a>
                <form method="POST" action="{{ route('hr-manager.payroll.payslips.recalculate', $payslip) }}">
                    @csrf
                    <button class="preview-btn warn" @disabled(!$canEditPayrollAttendance)>Recalculate Payslip</button>
                </form>
                <form method="POST" action="{{ route('hr-manager.payroll.payslips.resend-preview', $payslip) }}">
                    @csrf
                    <button class="preview-btn dark" @disabled(!$canEditPayrollAttendance || !$employee?->email)>Update & Mail Again</button>
                </form>
                @if($payslip->verification_token)
                    <a href="{{ route('payslips.verify', $payslip->verification_token) }}" target="_blank" class="preview-btn primary">Employee View</a>
                @endif
            </div>
        </div>
        @if(!$canEditPayrollAttendance)
            <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-800">
                Attendance correction disabled: payslip approved/paid hai ya payroll kisi aur HR ke lock me hai.
            </div>
        @endif
    </section>

    <section class="preview-grid">
        <div class="preview-metric"><div class="preview-label">Gross Salary</div><div class="preview-value">{{ $money($payslip->gross_pay) }}</div><div class="preview-note">Before deductions</div></div>
        <div class="preview-metric"><div class="preview-label">Payable Days</div><div class="preview-value">{{ $num($payableDays) }} / {{ $daysInMonth }}</div><div class="preview-note">Month salary days</div></div>
        <div class="preview-metric"><div class="preview-label">Per Day Rate</div><div class="preview-value">{{ $money($perDay) }}</div><div class="preview-note">Gross / month days</div></div>
        <div class="preview-metric"><div class="preview-label">Total Deduction</div><div class="preview-value">{{ $money($payslip->total_deductions) }}</div><div class="preview-note">Attendance + manual</div></div>
    </section>

    <section class="net-strip">
        <div>
            <div class="preview-label">Net Payable To Employee</div>
            <div class="preview-note">Final amount after all salary deductions.</div>
        </div>
        <div class="preview-value">{{ $money($payslip->net_pay) }}</div>
    </section>

    <section class="preview-card">
        <div class="preview-head" style="margin-bottom:14px;">
            <div>
                <p class="preview-kicker">Attendance Calculation</p>
                <h2 class="preview-title" style="font-size:20px;">Days used for salary</h2>
                <div class="preview-sub">Employee ko yahi breakup dikhega ki salary kitne din ki bani hai.</div>
            </div>
        </div>
        <div class="preview-days">
            @foreach($dayRows as $item)
                <div class="preview-metric">
                    <div class="preview-label">{{ $item['label'] }}</div>
                    <div class="preview-value">{{ $item['value'] }}</div>
                    <div class="preview-note">{{ $item['note'] }}</div>
                </div>
            @endforeach
        </div>
        <div class="summary-strip">
            <div class="summary-pill"><div class="preview-label">HR Overrides</div><strong>{{ $manualOverrideCount }}</strong></div>
            <div class="summary-pill"><div class="preview-label">Pending Reg.</div><strong>{{ $pendingRegularizationCount }}</strong></div>
            <div class="summary-pill"><div class="preview-label">Absent</div><strong>{{ $statusCount(\App\Models\AttendanceRecord::STATUS_ABSENT) }}</strong></div>
            <div class="summary-pill"><div class="preview-label">Leave</div><strong>{{ $statusCount(\App\Models\AttendanceRecord::STATUS_LEAVE) }}</strong></div>
            <div class="summary-pill"><div class="preview-label">Half Day</div><strong>{{ $statusCount(\App\Models\AttendanceRecord::STATUS_HALF_DAY) }}</strong></div>
            <div class="summary-pill"><div class="preview-label">Current Net</div><strong>{{ $money($payslip->net_pay) }}</strong></div>
        </div>
    </section>

    <section class="preview-card">
        <div class="preview-head" style="margin-bottom:14px;">
            <div>
                <p class="preview-kicker">Date-wise Attendance</p>
                <h2 class="preview-title" style="font-size:20px;">Absent / leave / late days</h2>
                <div class="preview-sub">Salary deduction ya attendance impact wale exact dates yahan show honge.</div>
            </div>
        </div>

        @if($absentDateList->isNotEmpty())
            <div class="attendance-alert" style="margin-bottom:12px;">
                Absent dates: {{ $absentDateList->join(', ') }}
            </div>
        @endif

        @if($visibleAttendanceDetails->isNotEmpty())
            <div class="attendance-detail-grid">
                @foreach($visibleAttendanceDetails as $record)
                    @php
                        $meta = $attendanceStatusMeta[$record->status] ?? ['label' => ucwords(str_replace('_', ' ', (string) $record->status)), 'class' => 'neutral', 'impact' => 'Attendance impact'];
                        if ($record->status === \App\Models\AttendanceRecord::STATUS_LEAVE) {
                            $paid = (float) ($record->payable_day_fraction ?? 0) > 0;
                            $meta['label'] = $paid ? 'Paid Leave' : 'Unpaid Leave';
                            $meta['class'] = $paid ? 'info' : 'danger';
                            $meta['impact'] = $paid ? 'Payable leave' : 'Leave deducted';
                        }
                        $recordDateKey = $record->attendance_date?->toDateString();
                        $recordRegularizations = $regularizationsByDate->get($recordDateKey, collect());
                        $pendingRegularization = $recordRegularizations->firstWhere('status', 'pending');
                        $isOverride = $record->hasActiveManualOverride();
                    @endphp
                    <div class="attendance-detail-card {{ $isOverride ? 'override' : '' }} {{ $pendingRegularization ? 'pending-reg' : '' }}">
                        <div class="attendance-detail-top">
                            <div>
                                <div class="attendance-date">{{ $record->attendance_date?->format('d M Y') }}</div>
                                <div class="attendance-day">{{ $record->attendance_date?->format('l') }}</div>
                            </div>
                            <span class="attendance-pill {{ $meta['class'] }}">{{ $meta['label'] }}</span>
                        </div>
                        <div class="attendance-badges">
                            @if($isOverride)
                                <span class="attendance-badge override">HR Override</span>
                            @endif
                            @if($pendingRegularization)
                                <span class="attendance-badge reg">Pending Regularization</span>
                            @endif
                        </div>
                        <div class="attendance-mini">
                            <div><strong>Impact:</strong> {{ $meta['impact'] }}</div>
                            <div><strong>Payable:</strong> {{ number_format((float) ($record->payable_day_fraction ?? 0), 1) }} day</div>
                            <div><strong>Original:</strong> {{ ucwords(str_replace('_', ' ', (string) ($record->auto_status ?: $record->status))) }}</div>
                            @if((int) ($record->late_minutes ?? 0) > 0)
                                <div><strong>Late:</strong> {{ (int) $record->late_minutes }} min</div>
                            @endif
                            @if($record->first_punch_in_at || $record->last_punch_out_at)
                                <div>
                                    <strong>Punch:</strong>
                                    {{ $record->first_punch_in_at?->format('h:i A') ?: '-' }}
                                    -
                                    {{ $record->last_punch_out_at?->format('h:i A') ?: '-' }}
                                </div>
                            @endif
                            @if($record->manual_override_reason)
                                <div><strong>Reason:</strong> {{ $record->manual_override_reason }}</div>
                            @endif
                            @if($record->manualOverriddenByUser)
                                <div><strong>Updated by:</strong> {{ $record->manualOverriddenByUser->name }} {{ $record->manual_overridden_at ? 'on ' . $record->manual_overridden_at->format('d M, h:i A') : '' }}</div>
                            @endif
                            @foreach($recordRegularizations as $regularization)
                                <div class="rounded-lg border border-amber-200 bg-amber-50 p-2 text-amber-900">
                                    <strong>Regularization:</strong> {{ ucwords((string) $regularization->status) }}
                                    @if($regularization->requested_status)
                                        → {{ ucwords(str_replace('_', ' ', $regularization->requested_status)) }}
                                    @endif
                                    <br>
                                    <span>{{ $regularization->reason ?: 'No reason added' }}</span>
                                    @if($regularization->approvals->isNotEmpty())
                                        <br><span>Approval: {{ $regularization->approvals->map(fn ($approval) => ($approval->actor?->name ?: 'User') . ' - ' . $approval->status)->join(', ') }}</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        @if($canEditPayrollAttendance)
                            <details class="attendance-form">
                                <summary>Edit / Mark Leave</summary>
                                @if($pendingRegularization)
                                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-2 text-xs font-bold text-amber-900">
                                        Pending regularization exists; payroll override salary ko immediately update karega.
                                    </div>
                                @endif
                                <form method="POST" action="{{ route('hr-manager.payroll.payslips.attendance-overwrite', $payslip) }}">
                                    @csrf
                                    <input type="hidden" name="attendance_date" value="{{ $recordDateKey }}">
                                    <input type="hidden" name="refresh_payslip" value="1">
                                    <div class="attendance-form-grid">
                                        <select name="manual_status" required data-status-select>
                                            @foreach([
                                                \App\Models\AttendanceRecord::STATUS_PRESENT => 'Present',
                                                \App\Models\AttendanceRecord::STATUS_LATE => 'Late',
                                                \App\Models\AttendanceRecord::STATUS_HALF_DAY => 'Half Day',
                                                \App\Models\AttendanceRecord::STATUS_ABSENT => 'Absent',
                                                \App\Models\AttendanceRecord::STATUS_LEAVE => 'Leave',
                                                \App\Models\AttendanceRecord::STATUS_WEEK_OFF => 'Week Off',
                                                \App\Models\AttendanceRecord::STATUS_HOLIDAY => 'Holiday',
                                            ] as $value => $label)
                                                <option value="{{ $value }}" @selected($record->status === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <select name="leave_type_id" data-leave-type-select>
                                            <option value="">Leave Type (CL/PL/SL)</option>
                                            @foreach($leaveTypes as $leaveType)
                                                <option value="{{ $leaveType->id }}" data-paid="{{ $leaveType->is_paid ? 'paid' : 'unpaid' }}">
                                                    {{ $leaveType->code ? $leaveType->code . ' - ' : '' }}{{ $leaveType->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <select name="leave_pay_type">
                                            <option value="paid" @selected($record->status === \App\Models\AttendanceRecord::STATUS_LEAVE && (float) $record->payable_day_fraction > 0)>Paid Leave</option>
                                            <option value="unpaid" @selected($record->status === \App\Models\AttendanceRecord::STATUS_LEAVE && (float) $record->payable_day_fraction <= 0)>Unpaid Leave</option>
                                        </select>
                                        <input type="time" name="manual_first_punch_in_at" value="{{ $record->first_punch_in_at?->format('H:i') }}" placeholder="In time">
                                        <input type="time" name="manual_last_punch_out_at" value="{{ $record->last_punch_out_at?->format('H:i') }}" placeholder="Out time">
                                        <textarea name="manual_override_reason" placeholder="Reason required" required>{{ old('manual_override_reason', $record->manual_override_reason) }}</textarea>
                                    </div>
                                    <div class="attendance-form-actions mt-2">
                                        <button class="attendance-mini-btn primary">Save & Refresh</button>
                                        <button type="button" class="attendance-mini-btn" onclick="markPayrollLeave(this, 'paid')">Mark Paid Leave</button>
                                        <button type="button" class="attendance-mini-btn" onclick="markPayrollLeave(this, 'unpaid')">Mark Unpaid Leave</button>
                                    </div>
                                </form>
                                @if($isOverride)
                                    <form method="POST" action="{{ route('hr-manager.payroll.payslips.attendance-clear', $payslip) }}">
                                        @csrf
                                        <input type="hidden" name="attendance_record_id" value="{{ $record->id }}">
                                        <input type="hidden" name="clear_reason" value="Cleared from payroll preview">
                                        <button class="attendance-mini-btn danger" onclick="return confirm('Clear HR override for {{ $record->attendance_date?->format('d M Y') }}?')">Clear Override</button>
                                    </form>
                                @endif
                            </details>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <div class="attendance-empty">Is month me absent, leave, half-day, late, week-off ya holiday record nahi mila.</div>
        @endif
    </section>

    <section class="preview-card">
        <div class="preview-head" style="margin-bottom:14px;">
            <div>
                <p class="preview-kicker">Salary Breakup</p>
                <h2 class="preview-title" style="font-size:20px;">Earnings and deductions</h2>
            </div>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div>
                <table class="preview-table">
                    <thead><tr><th>Earning</th><th class="amount">Amount</th></tr></thead>
                    <tbody>
                        @forelse($earnings as $line)
                            <tr><td>{{ $line['label'] ?? '-' }}</td><td class="amount">{{ $money($line['amount'] ?? 0) }}</td></tr>
                        @empty
                            <tr><td>No earnings</td><td class="amount">{{ $money(0) }}</td></tr>
                        @endforelse
                        <tr><td><strong>Total Earnings</strong></td><td class="amount">{{ $money($payslip->gross_pay) }}</td></tr>
                    </tbody>
                </table>
            </div>
            <div>
                <table class="preview-table">
                    <thead><tr><th>Deduction</th><th class="amount">Amount</th></tr></thead>
                    <tbody>
                        @forelse($deductions as $line)
                            <tr><td>{{ $line['label'] ?? '-' }}</td><td class="amount">{{ $money($line['amount'] ?? 0) }}</td></tr>
                        @empty
                            <tr><td>No deductions</td><td class="amount">{{ $money(0) }}</td></tr>
                        @endforelse
                        <tr><td><strong>Total Deductions</strong></td><td class="amount">{{ $money($payslip->total_deductions) }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>
<script>
document.querySelectorAll('[data-leave-type-select]').forEach((select) => {
    select.addEventListener('change', () => {
        const form = select.closest('form');
        const selected = select.options[select.selectedIndex];
        if (!form || !selected || !selected.value) return;
        form.manual_status.value = 'leave';
        if (selected.dataset.paid) {
            form.leave_pay_type.value = selected.dataset.paid;
        }
    });
});

function markPayrollLeave(button, payType) {
    const form = button.closest('form');
    if (!form) return;
    form.manual_status.value = 'leave';
    form.leave_pay_type.value = payType;
    const leaveSelect = form.querySelector('[data-leave-type-select]');
    if (leaveSelect && !leaveSelect.value) {
        const option = Array.from(leaveSelect.options).find(item => item.dataset.paid === payType);
        if (option) leaveSelect.value = option.value;
    }
}
</script>
@endsection

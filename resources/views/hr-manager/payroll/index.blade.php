@extends('layouts.app')

@section('title', 'HR Payroll')
@section('page-title', 'HR Payroll')

@section('content')
@php
    $statusOptions = [
        'all' => 'All',
        'preview' => 'Draft',
        'employee_review' => 'Employee Review',
        'correction_requested' => 'Correction',
        'correction_resolved' => 'Resolved',
        'hr_locked' => 'Ready for Admin',
        'payment_pending' => 'Approved',
        'admin_rejected' => 'Rejected',
        'paid' => 'Paid',
    ];
    $chip = fn ($status) => [
        'correction_requested' => 'bg-red-50 text-red-700 border-red-200',
        'hr_locked' => 'bg-blue-50 text-blue-700 border-blue-200',
        'payment_pending' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'paid' => 'bg-green-50 text-green-700 border-green-200',
        'admin_rejected' => 'bg-orange-50 text-orange-700 border-orange-200',
    ][$status] ?? 'bg-slate-50 text-slate-700 border-slate-200';
    $monthOptions = collect(range(1, 12))->mapWithKeys(fn ($monthNo) => [
        $monthNo => \Carbon\Carbon::create(null, $monthNo, 1)->format('F'),
    ]);
@endphp

<style>
    .payroll-shell { display:grid; gap:16px; }
    .payroll-panel { background:#fff; border:1px solid #e5ded4; border-radius:16px; box-shadow:0 10px 22px rgba(15,45,31,.04); }
    .payroll-toolbar { padding:14px 18px; display:flex; align-items:center; justify-content:space-between; gap:14px; flex-wrap:wrap; }
    .payroll-command-left { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
    .payroll-period-title { font-size:18px; line-height:1.15; font-weight:900; color:#071c14; }
    .payroll-period-sub { margin-top:2px; font-size:12px; color:#64736d; font-weight:700; }
    .payroll-chip-row { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
    .payroll-chip { min-height:36px; display:inline-flex; align-items:center; gap:8px; border:1px solid #dfe8e2; border-radius:999px; padding:0 12px; background:#fbfdfb; font-size:12px; font-weight:900; color:#10241c; }
    .payroll-chip span { color:#64736d; font-weight:800; }
    .payroll-command-actions { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
    .payroll-filter-panel { display:none; border-top:1px solid #e9eee9; padding:14px 18px; background:#fbfdfb; }
    .payroll-filter-panel.is-open { display:block; }
    .payroll-filter { display:flex; align-items:end; gap:10px; flex-wrap:wrap; }
    .payroll-field label { display:block; margin-bottom:6px; font-size:11px; line-height:1; font-weight:900; letter-spacing:.10em; text-transform:uppercase; color:#617164; }
    .payroll-field input, .payroll-field select { height:42px; min-width:136px; border:1px solid #dce5df; border-radius:12px; padding:0 13px; background:#fbfcfb; font-weight:700; color:#0d241b; outline:none; }
    .payroll-field input:focus, .payroll-field select:focus { border-color:#205a44; box-shadow:0 0 0 3px rgba(32,90,68,.10); }
    .payroll-btn { height:42px; display:inline-flex; align-items:center; justify-content:center; border-radius:12px; padding:0 16px; font-size:14px; font-weight:900; border:1px solid transparent; white-space:nowrap; }
    .payroll-btn.primary { background:#205a44; color:#fff; }
    .payroll-btn.dark { background:#101828; color:#fff; }
    .payroll-btn.blue { background:#2563eb; color:#fff; }
    .payroll-btn.soft { background:#fff; color:#205a44; border-color:#d8e1db; }
    .payroll-btn:disabled { opacity:.55; cursor:not-allowed; }
    .payroll-stats { display:none; }
    .payroll-stat { padding:16px 18px; min-height:92px; }
    .payroll-stat-label { font-size:11px; font-weight:900; letter-spacing:.10em; text-transform:uppercase; color:#617164; }
    .payroll-stat-value { margin-top:10px; font-size:22px; line-height:1.15; font-weight:900; color:#071c14; }
    .payroll-stat-note { margin-top:4px; font-size:12px; color:#64736d; font-weight:700; }
    .payroll-section-head { padding:14px 18px; display:flex; align-items:center; justify-content:space-between; gap:14px; flex-wrap:wrap; border-bottom:1px solid #e9eee9; }
    .payroll-section-title { margin:0; font-size:20px; line-height:1.2; font-weight:900; color:#071c14; }
    .payroll-section-copy { margin-top:5px; font-size:13px; color:#64736d; }
    .payroll-adjustment { padding:14px 18px; }
    .payroll-adjustment-grid { display:grid; grid-template-columns:minmax(190px,2fr) minmax(150px,1fr) minmax(130px,1fr) minmax(160px,1fr) minmax(140px,1fr) minmax(110px,.8fr); gap:10px; }
    .payroll-adjustment select, .payroll-adjustment input { height:42px; border:1px solid #dce5df; border-radius:12px; padding:0 13px; background:#fbfcfb; font-weight:700; }
    .payroll-table { width:100%; min-width:1220px; border-collapse:separate; border-spacing:0; font-size:13px; }
    .payroll-table thead th { position:sticky; top:0; z-index:1; background:#f7faf8; color:#5d6d67; font-size:11px; font-weight:900; letter-spacing:.08em; text-transform:uppercase; padding:12px 14px; border-bottom:1px solid #e5e9e6; text-align:left; }
    .payroll-table tbody td { padding:12px 14px; border-bottom:1px solid #eef2ef; vertical-align:middle; }
    .payroll-table tbody tr:hover { background:#fbfdfb; }
    .payroll-money { white-space:nowrap; font-variant-numeric:tabular-nums; }
    .payroll-money.net { font-weight:900; color:#071c14; }
    .payroll-employee { font-weight:900; color:#071c14; }
    .payroll-muted { color:#64736d; font-size:12px; font-weight:700; }
    .payroll-days-main { font-weight:900; color:#071c14; }
    .payroll-days-sub { margin-top:3px; color:#64736d; font-size:12px; font-weight:700; white-space:nowrap; }
    .payroll-action-group { display:flex; gap:7px; flex-wrap:wrap; }
    @media (max-width:1100px) {
        .payroll-stats { grid-template-columns:repeat(2,minmax(0,1fr)); }
        .payroll-adjustment-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
    }
    @media (max-width:680px) {
        .payroll-toolbar, .payroll-section-head { align-items:stretch; }
        .payroll-command-left, .payroll-command-actions, .payroll-chip-row, .payroll-filter, .payroll-field, .payroll-field input, .payroll-field select, .payroll-btn { width:100%; }
        .payroll-stats, .payroll-adjustment-grid { grid-template-columns:1fr; }
    }
</style>

<div class="payroll-shell">
    @include('attendance._flash')

    @if(!($canEdit ?? true) && !empty($lockMessage))
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800">
            {{ $lockMessage }}
        </div>
    @endif

    <div class="payroll-panel" data-payroll-filters>
        <div class="payroll-toolbar">
            <div class="payroll-command-left">
                <div>
                    <div class="payroll-period-title">{{ \Carbon\Carbon::create((int) $year, (int) $month, 1)->format('F Y') }}</div>
                    <div class="payroll-period-sub">Payroll workspace</div>
                </div>
                @if($freeze)
                    <div class="payroll-chip-row">
                        <div class="payroll-chip"><span>Status</span>{{ ucwords(str_replace('_', ' ', $freeze->status)) }}</div>
                        <div class="payroll-chip"><span>Employees</span>{{ $payslips->count() }}</div>
                        <div class="payroll-chip"><span>Net</span>Rs {{ number_format((float) $payslips->sum('net_pay'), 2) }}</div>
                        <div class="payroll-chip"><span>Lock</span>{{ $freeze->lockedByUser?->name ?: 'Open' }}</div>
                    </div>
                @endif
            </div>

            <div class="payroll-command-actions">
                <button type="button" class="payroll-btn soft" data-payroll-filter-toggle>Filters</button>
                <form method="POST" action="{{ route('hr-manager.payroll.prepare') }}" class="flex flex-wrap gap-2">
                    @csrf
                    <input type="hidden" name="year" value="{{ $year }}">
                    <input type="hidden" name="month" value="{{ $month }}">
                    <button class="payroll-btn dark" @disabled(!($canEdit ?? true))>Prepare Payroll</button>
                </form>
            </div>
        </div>

        <div class="payroll-filter-panel">
            <form method="GET" class="payroll-filter">
                <div class="payroll-field">
                    <label>Year</label>
                    <input type="number" name="year" value="{{ $year }}" min="2000" max="2100">
                </div>
                <div class="payroll-field">
                    <label>Month</label>
                    <select name="month">
                        @foreach($monthOptions as $value => $label)
                            <option value="{{ $value }}" @selected((int) $month === (int) $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="payroll-field">
                    <label>Status</label>
                    <select name="status">
                        @foreach($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="payroll-btn primary">Refresh</button>
            </form>
        </div>
    </div>

    @if($freeze)
        <div class="payroll-stats">
            <div class="payroll-panel payroll-stat">
                <div class="payroll-stat-label">Batch Status</div>
                <div class="payroll-stat-value">{{ ucwords(str_replace('_', ' ', $freeze->status)) }}</div>
                <div class="payroll-stat-note">{{ \Carbon\Carbon::create((int) $year, (int) $month, 1)->format('F Y') }}</div>
            </div>
            <div class="payroll-panel payroll-stat">
                <div class="payroll-stat-label">Employees</div>
                <div class="payroll-stat-value">{{ $payslips->count() }}</div>
                <div class="payroll-stat-note">Payslips in this batch</div>
            </div>
            <div class="payroll-panel payroll-stat">
                <div class="payroll-stat-label">Net Total</div>
                <div class="payroll-stat-value">Rs {{ number_format((float) $payslips->sum('net_pay'), 2) }}</div>
                <div class="payroll-stat-note">Payable after deductions</div>
            </div>
            <div class="payroll-panel payroll-stat">
                <div class="payroll-stat-label">Lock</div>
                <div class="payroll-stat-value" style="font-size:18px;">{{ $freeze->lockedByUser?->name ?: 'Open' }}</div>
                <div class="payroll-stat-note">Current editor</div>
            </div>
        </div>

        <div class="payroll-panel">
            <div class="payroll-section-head">
                <div>
                    <h2 class="payroll-section-title">Manual Adjustment</h2>
                    <div class="payroll-section-copy">One-time earning or deduction for this payroll month.</div>
                </div>
            </div>
            <form method="POST" action="{{ route('hr-manager.payroll.adjustments.store') }}" class="payroll-adjustment payroll-adjustment-grid">
                @csrf
                <input type="hidden" name="year" value="{{ $year }}">
                <input type="hidden" name="month" value="{{ $month }}">
                <select name="user_id" required @disabled(!($canEdit ?? true))>
                    <option value="">Employee</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
                <select name="payroll_deduction_head_id" @disabled(!($canEdit ?? true))>
                    <option value="">Head</option>
                    @foreach($heads as $head)
                        <option value="{{ $head->id }}">{{ $head->name }}</option>
                    @endforeach
                </select>
                <select name="type" @disabled(!($canEdit ?? true))>
                    <option value="earning">Earning</option>
                    <option value="deduction">Deduction</option>
                </select>
                <input name="label" placeholder="Label" required @disabled(!($canEdit ?? true))>
                <input name="amount" type="number" step="0.01" placeholder="Amount" required @disabled(!($canEdit ?? true))>
                <button class="payroll-btn primary" @disabled(!($canEdit ?? true))>Save</button>
            </form>
        </div>

        <form id="hr-submit-admin-form" method="POST" action="{{ route('hr-manager.payroll.submit-admin', $freeze) }}" class="hidden">
            @csrf
        </form>
        <form id="hr-release-employee-form" method="POST" action="{{ route('hr-manager.payroll.release-employee', $freeze) }}" class="hidden">
            @csrf
        </form>

        <section class="payroll-panel overflow-hidden">
            <div class="payroll-section-head">
                <div>
                    <h2 class="payroll-section-title">Employee Payslips</h2>
                    <p class="payroll-section-copy">Review salary days, preview employee view, then send for employee/admin review.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button form="hr-release-employee-form" class="payroll-btn blue" @disabled(!($canEdit ?? true))>Send to Employee</button>
                    <button form="hr-submit-admin-form" class="payroll-btn primary" @disabled(!($canEdit ?? true))>Send to Admin</button>
                </div>
            </div>

            <div class="overflow-x-auto max-h-[62vh]">
                <table class="payroll-table">
                    <thead>
                        <tr>
                            <th class="p-3 text-left"><input type="checkbox" onclick="document.querySelectorAll('.payroll-check').forEach(cb => cb.checked = this.checked)" @disabled(!($canEdit ?? true))></th>
                            <th class="p-3 text-left">Employee</th>
                            <th class="p-3 text-left">Status</th>
                            <th class="p-3 text-left">Days</th>
                            <th class="p-3 text-left">Gross</th>
                            <th class="p-3 text-left">Deduction</th>
                            <th class="p-3 text-left">Net</th>
                            <th class="p-3 text-left">Correction</th>
                            <th class="p-3 text-left">Version</th>
                            <th class="p-3 text-left">Preview</th>
                            <th class="p-3 text-left">Resolve</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($payslips as $payslip)
                            @php
                                $attendance = $payslip->snapshot_json['attendance_snapshot'] ?? [];
                                $period = \Carbon\Carbon::create((int) $payslip->year, (int) $payslip->month, 1);
                                $payableDays = (float) ($attendance['payable_days'] ?? 0);
                                $monthDays = $period->daysInMonth;
                            @endphp
                            <tr>
                                <td><input form="hr-submit-admin-form" class="payroll-check" type="checkbox" name="payslip_ids[]" value="{{ $payslip->id }}" @disabled(!($canEdit ?? true))></td>
                                <td>
                                    <div class="payroll-employee">{{ $payslip->user?->name }}</div>
                                    <div class="payroll-muted">{{ $period->format('F Y') }}</div>
                                </td>
                                <td><span class="px-3 py-1 rounded-full border text-xs font-bold {{ $chip($payslip->status) }}">{{ app(\App\Services\PayrollWorkflowService::class)->statusLabel($payslip->status) }}</span></td>
                                <td>
                                    <div class="payroll-days-main">{{ number_format($payableDays, 1) }} / {{ $monthDays }}</div>
                                    <div class="payroll-days-sub">
                                        P {{ number_format((float) ($attendance['present_days'] ?? 0), 1) }},
                                        HD {{ number_format((float) ($attendance['half_days'] ?? 0), 1) }},
                                        A {{ number_format((float) ($attendance['absent_days'] ?? 0), 1) }}
                                    </div>
                                </td>
                                <td class="payroll-money">Rs {{ number_format((float) $payslip->gross_pay, 2) }}</td>
                                <td class="payroll-money">Rs {{ number_format((float) $payslip->total_deductions, 2) }}</td>
                                <td class="payroll-money net">Rs {{ number_format((float) $payslip->net_pay, 2) }}</td>
                                <td class="max-w-xs">{{ $payslip->employee_correction_note ?: '-' }}</td>
                                <td>{{ $payslip->versions->count() ? 'v' . $payslip->versions->max('version_no') : '-' }}</td>
                                <td>
                                    <div class="payroll-action-group">
                                        <a href="{{ route('hr-manager.payroll.payslips.preview', $payslip) }}" target="_blank" class="payroll-btn primary" style="height:34px;padding:0 12px;font-size:12px;">Preview</a>
                                        @if($payslip->verification_token)
                                            <a href="{{ route('payslips.verify', $payslip->verification_token) }}" target="_blank" class="payroll-btn soft" style="height:34px;padding:0 12px;font-size:12px;">Employee</a>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    @if($payslip->status === 'correction_requested')
                                        <form method="POST" action="{{ route('hr-manager.payroll.payslips.resolve', $payslip) }}" class="flex gap-2">
                                            @csrf
                                            <input name="hr_resolution_note" class="border rounded-lg px-2 py-1" placeholder="Resolution" required @disabled(!($canEdit ?? true))>
                                            <button class="px-3 py-1 rounded-lg bg-emerald-600 text-white font-bold disabled:opacity-50" @disabled(!($canEdit ?? true))>Resolve</button>
                                        </form>
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="11" class="p-6 text-center text-slate-500">No payslips. Click Prepare Payroll.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @else
        <div class="bg-white border rounded-2xl p-8 text-center text-slate-500">No payroll prepared yet. Click Prepare Payroll.</div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('click', function (event) {
        const toggle = event.target.closest('[data-payroll-filter-toggle]');
        if (!toggle) {
            return;
        }

        const shell = toggle.closest('[data-payroll-filters]');
        const panel = shell ? shell.querySelector('.payroll-filter-panel') : null;
        if (panel) {
            panel.classList.toggle('is-open');
        }
    });
</script>
@endpush

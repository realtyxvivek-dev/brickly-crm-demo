@extends('layouts.app')

@section('title', 'Attendance Reports')
@section('page-title', 'Attendance Reports')

@push('styles')
<style>
    .hr-report {
        --line: rgba(15, 23, 42, 0.09);
        --text: #0f172a;
        --muted: #64748b;
        --green: #14532d;
        --green-soft: #ecfdf3;
        --blue: #1d4ed8;
        --blue-soft: #eef4ff;
        --amber: #a15c07;
        --amber-soft: #fff7e8;
        --red: #b42318;
        --red-soft: #fff1f0;
        display: flex;
        flex-direction: column;
        gap: 18px;
        color: var(--text);
    }

    .hr-report-card {
        background: #fff;
        border: 1px solid var(--line);
        border-radius: 22px;
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.05);
    }

    .hr-report-hero {
        padding: 22px;
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(340px, 0.9fr);
        gap: 18px;
        align-items: end;
    }

    .hr-report-kicker {
        font-size: 11px;
        font-weight: 900;
        letter-spacing: .12em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .hr-report-title {
        margin-top: 6px;
        font-size: 30px;
        line-height: 1.05;
        font-weight: 900;
        letter-spacing: -0.04em;
    }

    .hr-report-copy {
        margin-top: 8px;
        color: var(--muted);
        font-size: 14px;
        line-height: 1.45;
        max-width: 760px;
    }

    .hr-report-filters {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }

    .hr-report-field label {
        display: block;
        margin-bottom: 7px;
        font-size: 11px;
        font-weight: 900;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .hr-report-input {
        width: 100%;
        min-height: 44px;
        border: 1px solid var(--line);
        border-radius: 14px;
        padding: 0 14px;
        font-weight: 700;
        color: var(--text);
        background: #fff;
    }

    .hr-report-btn {
        min-height: 44px;
        border: 0;
        border-radius: 14px;
        background: #205A44;
        color: #fff;
        font-weight: 900;
        cursor: pointer;
    }

    .hr-report-exports {
        padding: 18px 22px 22px;
        border-top: 1px solid var(--line);
    }

    .hr-report-section-title {
        font-size: 18px;
        font-weight: 900;
        margin-bottom: 12px;
    }

    .hr-export-grid,
    .hr-summary-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
    }

    .hr-export-card,
    .hr-summary-card {
        display: block;
        min-height: 92px;
        padding: 16px;
        border: 1px solid var(--line);
        border-radius: 16px;
        background: #fff;
        text-decoration: none;
        color: var(--text);
    }

    .hr-export-card:hover,
    .hr-summary-card:hover {
        border-color: rgba(20, 83, 45, .22);
        box-shadow: 0 10px 22px rgba(15, 23, 42, .06);
    }

    .hr-export-card strong,
    .hr-summary-card strong {
        display: block;
        font-size: 24px;
        line-height: 1;
        margin-top: 8px;
    }

    .hr-export-card span,
    .hr-summary-card span {
        display: block;
        font-size: 12px;
        color: var(--muted);
        font-weight: 700;
        margin-top: 5px;
    }

    .hr-export-card b,
    .hr-summary-card b {
        font-size: 13px;
        font-weight: 900;
    }

    .hr-export-card.green { background: var(--green-soft); }
    .hr-export-card.blue { background: var(--blue-soft); }
    .hr-export-card.amber { background: var(--amber-soft); }
    .hr-summary-card.green { border-top: 4px solid #22c55e; }
    .hr-summary-card.amber { border-top: 4px solid #f59e0b; }
    .hr-summary-card.red { border-top: 4px solid #ef4444; }
    .hr-summary-card.blue { border-top: 4px solid #3b82f6; }

    .hr-report-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.1fr) minmax(320px, .9fr);
        gap: 18px;
    }

    .hr-report-panel {
        padding: 22px;
    }

    .hr-month-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
    }

    .hr-month-box {
        border: 1px solid var(--line);
        border-radius: 14px;
        padding: 13px;
        background: #f8fafc;
    }

    .hr-month-box span {
        display: block;
        color: var(--muted);
        font-size: 12px;
        font-weight: 800;
    }

    .hr-month-box strong {
        display: block;
        margin-top: 6px;
        font-size: 22px;
        line-height: 1;
    }

    .hr-trend-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .hr-trend-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 12px 14px;
        border-radius: 14px;
        background: #f8fafc;
        border: 1px solid var(--line);
        font-size: 14px;
    }

    .hr-problem-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
    }

    .hr-problem-table th,
    .hr-problem-table td {
        padding: 13px 10px;
        border-bottom: 1px solid var(--line);
        text-align: left;
    }

    .hr-problem-table th {
        color: var(--muted);
        font-size: 11px;
        font-weight: 900;
        letter-spacing: .08em;
        text-transform: uppercase;
        background: #f8fafc;
    }

    .hr-action-row {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .hr-small-btn {
        min-height: 34px;
        border: 0;
        border-radius: 10px;
        padding: 0 12px;
        color: #fff;
        font-size: 12px;
        font-weight: 900;
        cursor: pointer;
    }

    .hr-small-btn.green { background: #205A44; }
    .hr-small-btn.gray { background: #64748b; }
    .hr-small-btn.amber { background: #d97706; }

    .hr-empty {
        padding: 18px;
        color: var(--muted);
        font-weight: 700;
        background: #f8fafc;
        border-radius: 14px;
    }

    @media (max-width: 1100px) {
        .hr-report-hero,
        .hr-report-grid,
        .hr-export-grid,
        .hr-summary-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 700px) {
        .hr-report-filters,
        .hr-month-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@section('content')
@php
    $todayPresent = (int) (($daily['present'] ?? 0) + ($daily['late'] ?? 0));
    $todayHalfDay = (int) ($daily['half_day'] ?? 0);
    $todayAbsent = (int) ($daily['absent'] ?? 0);
    $problemCount = $suspiciousQueue->count();
    $statusLabels = [
        'present' => 'Present',
        'late' => 'Late',
        'half_day' => 'Half Day',
        'leave' => 'Leave',
        'absent' => 'Absent',
        'week_off' => 'Week Off',
        'holiday' => 'Holiday',
    ];
@endphp

<div class="hr-report">
    @include('attendance._flash')
    @include('hr-manager.attendance._nav')

    <div class="hr-report-card">
        <form method="GET" action="{{ route('hr-manager.attendance.reports') }}" class="hr-report-hero">
            <div>
                <div class="hr-report-kicker">Attendance Reports</div>
                <div class="hr-report-title">Reports aur Export</div>
                <div class="hr-report-copy">Month ya date select karo, summary dekho, phir zarurat ke hisab se report download karo.</div>
            </div>
            <div class="hr-report-filters">
                <div class="hr-report-field">
                    <label for="date">Today Summary Date</label>
                    <input id="date" type="date" name="date" value="{{ $date->toDateString() }}" class="hr-report-input">
                </div>
                <div class="hr-report-field">
                    <label for="month">Report Month</label>
                    <input id="month" type="month" name="month" value="{{ $monthDate->format('Y-m') }}" class="hr-report-input">
                </div>
                <button type="submit" class="hr-report-btn" style="grid-column:1 / -1;">Apply</button>
            </div>
        </form>

        <div class="hr-report-exports">
            <div class="hr-report-section-title">Download Report</div>
            <div class="hr-export-grid">
                <a href="{{ route('hr-manager.attendance.reports.export.attendance', ['year' => $monthDate->year, 'month' => $monthDate->month, 'format' => 'xlsx']) }}" class="hr-export-card green">
                    <b>Attendance Register</b>
                    <span>Excel with daily status</span>
                </a>
                <a href="{{ route('hr-manager.attendance.reports.export.attendance', ['year' => $monthDate->year, 'month' => $monthDate->month, 'format' => 'pdf']) }}" class="hr-export-card blue">
                    <b>Attendance PDF</b>
                    <span>Printable summary</span>
                </a>
                <a href="{{ route('hr-manager.attendance.reports.export.payroll', ['year' => $monthDate->year, 'month' => $monthDate->month, 'format' => 'xlsx']) }}" class="hr-export-card green">
                    <b>Salary Report</b>
                    <span>Payroll and payable days</span>
                </a>
                <a href="{{ route('hr-manager.attendance.reports.export.suspicious', ['year' => $monthDate->year, 'month' => $monthDate->month]) }}" class="hr-export-card amber">
                    <b>Problem Report</b>
                    <span>Attendance issues list</span>
                </a>
            </div>
        </div>
    </div>

    <div class="hr-summary-grid">
        <div class="hr-summary-card green"><b>Present Today</b><strong>{{ $todayPresent }}</strong><span>{{ $date->format('d M Y') }}</span></div>
        <div class="hr-summary-card amber"><b>Half Day</b><strong>{{ $todayHalfDay }}</strong><span>Needs review if unexpected</span></div>
        <div class="hr-summary-card red"><b>Absent Today</b><strong>{{ $todayAbsent }}</strong><span>No mark today</span></div>
        <div class="hr-summary-card blue"><b>Problems</b><strong>{{ $problemCount }}</strong><span>Open review items</span></div>
    </div>

    <div class="hr-report-grid">
        <div class="hr-report-card hr-report-panel">
            <div class="hr-report-section-title">Monthly Summary</div>
            <div class="hr-month-grid">
                @foreach($statusLabels as $status => $label)
                    <div class="hr-month-box">
                        <span>{{ $label }}</span>
                        <strong>{{ (int) ($monthly[$status] ?? 0) }}</strong>
                    </div>
                @endforeach
                @if($rollupTotals)
                    <div class="hr-month-box"><span>Payable Days</span><strong>{{ number_format((float) $rollupTotals->payable_days, 2) }}</strong></div>
                    <div class="hr-month-box"><span>Late Penalty</span><strong>{{ number_format((float) $rollupTotals->late_penalty_days, 2) }}</strong></div>
                    <div class="hr-month-box"><span>Salary Total</span><strong>Rs {{ number_format((float) $rollupTotals->estimated_salary, 0) }}</strong></div>
                @endif
            </div>
        </div>

        <div class="hr-report-card hr-report-panel">
            <div class="hr-report-section-title">Late List</div>
            <div class="hr-trend-list">
                @forelse($lateTrend as $row)
                    <div class="hr-trend-row">
                        <span>{{ \Carbon\Carbon::parse($row->attendance_date)->format('d M') }}</span>
                        <strong>{{ $row->late_total }} late</strong>
                    </div>
                @empty
                    <div class="hr-empty">Selected month me late record nahi hai.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="hr-report-card hr-report-panel">
        <div class="hr-report-section-title">Attendance Problems</div>
        <div style="overflow-x:auto;">
            <table class="hr-problem-table">
                <thead>
                    <tr><th>Employee</th><th>Problem</th><th>Date</th><th>Status</th><th>Action</th></tr>
                </thead>
                <tbody>
                    @forelse($suspiciousQueue as $log)
                        <tr>
                            <td>{{ $log->user?->name ?: 'Employee' }}</td>
                            <td>{{ ucwords(str_replace('_', ' ', $log->flag_type)) }}</td>
                            <td>{{ $log->created_at?->format('d M Y h:i A') }}</td>
                            <td>{{ ucfirst($log->status) }}</td>
                            <td>
                                <div class="hr-action-row">
                                    <form method="POST" action="{{ route('hr-manager.attendance.suspicion.update', $log) }}">@csrf<input type="hidden" name="status" value="reviewed"><button class="hr-small-btn green">Reviewed</button></form>
                                    <form method="POST" action="{{ route('hr-manager.attendance.suspicion.update', $log) }}">@csrf<input type="hidden" name="status" value="ignored"><button class="hr-small-btn gray">Ignore</button></form>
                                    <form method="POST" action="{{ route('hr-manager.attendance.suspicion.update', $log) }}">@csrf<input type="hidden" name="status" value="action_taken"><button class="hr-small-btn amber">Action Done</button></form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="hr-empty">Abhi koi attendance problem pending nahi hai.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

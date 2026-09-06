@extends('layouts.app')
@section('title', 'HR Reports')
@section('page-title', '')
@section('page-subtitle', '')
@section('hide-app-header', '1')
@section('content')
@php($hrRouteBase = auth()->check() && auth()->user()->isHrManager() ? 'hr-manager.settings.hr' : 'admin.hr')
<div class="hr-setup-page">
    @include('attendance._flash')
    @include('admin.hr._workspace_hero', [
        'eyebrow' => 'Reports',
        'title' => 'Payroll and attendance exports',
        'subtitle' => 'Review and export monthly attendance, payroll, and suspicious punch reports from one place.',
        'stats' => [
            ['label' => 'Rollups', 'value' => $rollups->count(), 'note' => $monthDate->format('M Y')],
            ['label' => 'Payable Days', 'value' => number_format((float) $rollups->sum('payable_days'), 2), 'note' => 'Current filter'],
            ['label' => 'Payslips', 'value' => $payslipCount, 'note' => 'Generated records'],
        ],
    ])
    @include('admin.hr._nav')
    <div class="hr-setup-card p-6">
        <h2 class="hr-setup-section-title">Report month</h2>
        <form method="GET" class="flex flex-wrap gap-3 items-end">
            <input type="month" name="month" value="{{ $monthDate->format('Y-m') }}" class="px-4 py-2 border border-[#E5DED4] rounded-lg">
            <button type="submit" class="px-4 py-2 bg-[#205A44] text-white rounded-lg">Refresh</button>
        </form>
    </div>
    <div class="hr-setup-card p-6">
        <h2 class="hr-setup-section-title">Export reports</h2>
        <p class="hr-setup-section-copy">Download attendance, payroll, and suspicious activity exports for the selected month.</p>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route($hrRouteBase . '.reports.export.attendance', ['year' => $monthDate->year, 'month' => $monthDate->month, 'format' => 'xlsx']) }}" class="px-4 py-2 bg-[#205A44] text-white rounded-lg">Attendance Excel</a>
            <a href="{{ route($hrRouteBase . '.reports.export.attendance', ['year' => $monthDate->year, 'month' => $monthDate->month, 'format' => 'pdf']) }}" class="px-4 py-2 bg-slate-700 text-white rounded-lg">Attendance PDF</a>
            <a href="{{ route($hrRouteBase . '.reports.export.payroll', ['year' => $monthDate->year, 'month' => $monthDate->month, 'format' => 'xlsx']) }}" class="px-4 py-2 bg-emerald-600 text-white rounded-lg">Payroll Excel</a>
            <a href="{{ route($hrRouteBase . '.reports.export.suspicious', ['year' => $monthDate->year, 'month' => $monthDate->month]) }}" class="px-4 py-2 bg-amber-600 text-white rounded-lg">Suspicious Excel</a>
        </div>
    </div>
</div>
@endsection

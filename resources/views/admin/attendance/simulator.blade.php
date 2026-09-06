@extends('layouts.app')

@section('title', 'Attendance Policy Simulator')
@section('page-title', '')
@section('page-subtitle', '')
@section('hide-app-header', '1')

@section('content')
@php($attendanceRouteBase = auth()->check() && auth()->user()->isHrManager() ? 'hr-manager.settings.attendance' : 'admin.attendance')
<div class="hr-setup-page">
    @include('attendance._flash')
    @include('admin.hr._workspace_hero', [
        'eyebrow' => 'Attendance Setup',
        'title' => 'Policy simulator',
        'subtitle' => 'Select a user and attendance rule to test the expected attendance behavior.',
    ])
    @include('admin.hr._nav')
    @include('admin.attendance._nav')

    <div class="hr-setup-help">
        <h2 class="text-lg font-semibold text-brand-primary mb-3">Quick Help</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm text-[#374151]">
            <div class="bg-white rounded-lg border border-[#E5DED4] p-4">
                <div class="font-semibold text-brand-primary mb-2">What This Does</div>
                <p>This tool shows which attendance status will be generated for the selected user, date, and time.</p>
            </div>
            <div class="bg-white rounded-lg border border-[#E5DED4] p-4">
                <div class="font-semibold text-brand-primary mb-2">How to Use</div>
                <p>1. Select a user.</p>
                <p>2. Policy is optional. Leave it blank to use the mapped policy.</p>
                <p>3. Enter the date and punch-in time.</p>
            </div>
            <div class="bg-white rounded-lg border border-[#E5DED4] p-4">
                <div class="font-semibold text-brand-primary mb-2">Best Use</div>
                <p>Use it to test late rules, half-day rules, and policy behavior before rollout.</p>
            </div>
        </div>
    </div>

    <div class="hr-setup-card p-6">
        <h2 class="text-lg font-semibold text-brand-primary mb-4">Run Simulation</h2>
        <form method="GET" action="{{ route($attendanceRouteBase . '.simulator.index') }}" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-[#374151] mb-1">User</label>
                <select name="user_id" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg" required>
                    <option value="">Select user</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}" @selected(request('user_id') == $user->id)>{{ $user->name }} ({{ $user->role?->name ?? 'Role' }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-[#374151] mb-1">Policy</label>
                <select name="attendance_policy_id" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg">
                    <option value="">Use mapped policy</option>
                    @foreach($policies as $policy)
                        <option value="{{ $policy->id }}" @selected(request('attendance_policy_id') == $policy->id)>{{ $policy->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-[#374151] mb-1">Date</label>
                <input type="date" name="date" value="{{ request('date', now()->toDateString()) }}" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-[#374151] mb-1">Punch-in time</label>
                <input type="time" name="punch_in_time" value="{{ request('punch_in_time') }}" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg">
            </div>
            <div class="md:col-span-4">
                <button type="submit" class="px-5 py-2 bg-[#205A44] text-white rounded-lg">Simulate</button>
            </div>
        </form>
    </div>

    @if($result)
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="hr-setup-card p-6">
                <h2 class="text-lg font-semibold text-brand-primary mb-4">Input</h2>
                <div class="space-y-2 text-sm text-[#374151]">
                    <div><strong>User:</strong> {{ $result['user']->name }}</div>
                    <div><strong>Policy:</strong> {{ $result['policy']?->name ?? 'None' }}</div>
                    <div><strong>Date:</strong> {{ $result['date'] }}</div>
                    <div><strong>Punch In:</strong> {{ $result['punch_in_time'] ?: 'No punch event' }}</div>
                </div>
            </div>

            <div class="hr-setup-card p-6">
                <h2 class="text-lg font-semibold text-brand-primary mb-4">Result</h2>
                <div class="space-y-2 text-sm text-[#374151]">
                    <div><strong>Status:</strong> {{ ucwords(str_replace('_', ' ', $result['classification']['status'])) }}</div>
                    <div><strong>Source:</strong> {{ ucfirst($result['classification']['status_source']) }}</div>
                    <div><strong>Late Minutes:</strong> {{ $result['classification']['late_minutes'] }}</div>
                    <div><strong>Payable Fraction:</strong> {{ number_format($result['classification']['payable_day_fraction'], 2) }}</div>
                </div>
                <div class="mt-4 rounded-lg border border-[#E5DED4] bg-[#F9FAFB] p-4 text-sm text-[#374151]">
                    <div class="font-semibold text-brand-primary mb-2">Meaning</div>
                    @if(($result['classification']['status'] ?? null) === 'present')
                        <p>User is on time and day is counted as full present.</p>
                    @elseif(($result['classification']['status'] ?? null) === 'late')
                        <p>User is marked late, but day is still counted based on current rule.</p>
                    @elseif(($result['classification']['status'] ?? null) === 'half_day')
                        <p>User falls in half-day window. Payroll count becomes half day.</p>
                    @elseif(($result['classification']['status'] ?? null) === 'absent')
                        <p>No valid punch in for this timing rule, so day becomes absent.</p>
                    @else
                        <p>This is the final simulated result for selected inputs.</p>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
@endsection

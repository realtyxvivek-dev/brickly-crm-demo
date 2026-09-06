@extends('layouts.app')

@section('title', 'Attendance Rules')
@section('page-title', '')
@section('page-subtitle', '')
@section('hide-app-header', '1')

@section('content')
@php($attendanceRouteBase = auth()->check() && auth()->user()->isHrManager() ? 'hr-manager.settings.attendance' : 'admin.attendance')
<div class="hr-setup-page">
    @include('attendance._flash')
    @include('admin.hr._workspace_hero', [
        'eyebrow' => 'Attendance Setup',
        'title' => 'Attendance rules',
        'subtitle' => 'Manage office timing, late windows, half-day rules, geo/photo settings, and week-off policy.',
    ])
    @include('admin.hr._nav')
    @include('admin.attendance._nav')

    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6">
            <div class="text-sm text-[#6B7280]">Rules</div>
            <div class="mt-2 text-3xl font-bold text-brand-primary">{{ $policies->count() }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6">
            <div class="text-sm text-[#6B7280]">Default Rule</div>
            <div class="mt-2 text-3xl font-bold text-brand-primary">{{ $policies->firstWhere('is_default', true)?->name ?? 'None' }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6">
            <div class="text-sm text-[#6B7280]">Active Rules</div>
            <div class="mt-2 text-3xl font-bold text-brand-primary">{{ $policies->where('is_active', true)->count() }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6">
            <div class="text-sm text-[#6B7280]">Photo On</div>
            <div class="mt-2 text-3xl font-bold text-brand-primary">{{ $policies->where('photo_required', true)->count() }}</div>
        </div>
    </div>

    <div class="hr-setup-help">
        <h2 class="text-lg font-semibold text-brand-primary mb-3">Quick Help</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm text-[#374151]">
            <div class="bg-white rounded-lg border border-[#E5DED4] p-4">
                <div class="font-semibold text-brand-primary mb-2">Basic Setup</div>
                <p>1. Enter a rule name.</p>
                <p>2. Select the office.</p>
                <p>3. Set reminder time, late time, normal window, and half-day window.</p>
            </div>
            <div class="bg-white rounded-lg border border-[#E5DED4] p-4">
                <div class="font-semibold text-brand-primary mb-2">Recommended for Start</div>
                <p>Reminder: <strong>09:00 AM</strong></p>
                <p>Late after: <strong>09:00 AM</strong></p>
                <p>Normal end: <strong>11:30 AM</strong></p>
                <p>Half day: <strong>01:00 PM - 04:00 PM</strong></p>
            </div>
            <div class="bg-white rounded-lg border border-[#E5DED4] p-4">
                <div class="font-semibold text-brand-primary mb-2">Pilot Suggestion</div>
                <p>Photo required: <strong>On</strong></p>
                <p>Geo required: <strong>On only after office coordinates are filled</strong></p>
                <p>Selfie required: <strong>On</strong></p>
                <p>Face review required: <strong>Off for first pilot</strong></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6">
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-5">
            <div>
                <h2 class="text-lg font-semibold text-brand-primary">Weekly Off Rules</h2>
                <p class="text-sm text-[#6B7280] mt-1">Select roles and apply the week-off day. This mapping is used for punch reminders and attendance status.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route($attendanceRouteBase . '.weekoffs.apply') }}">
                    @csrf
                    @foreach(['senior_manager', 'assistant_sales_manager'] as $slug)
                        <input type="hidden" name="role_slugs[]" value="{{ $slug }}">
                    @endforeach
                    <input type="hidden" name="day_of_week" value="3">
                    <input type="hidden" name="effective_from" value="{{ now()->startOfMonth()->toDateString() }}">
                    <button type="submit" class="px-4 py-2 rounded-lg bg-[#205A44] text-white text-sm font-semibold">ASM + Sr. Manager: Wednesday</button>
                </form>
                <form method="POST" action="{{ route($attendanceRouteBase . '.weekoffs.apply') }}">
                    @csrf
                    @foreach(['marketing_manager', 'marketing_executive', 'hr_manager', 'junior_hr'] as $slug)
                        <input type="hidden" name="role_slugs[]" value="{{ $slug }}">
                    @endforeach
                    <input type="hidden" name="day_of_week" value="0">
                    <input type="hidden" name="effective_from" value="{{ now()->startOfMonth()->toDateString() }}">
                    <button type="submit" class="px-4 py-2 rounded-lg border border-[#E5DED4] text-brand-primary text-sm font-semibold">Marketing + HR: Sunday</button>
                </form>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mt-5">
            <form method="POST" action="{{ route($attendanceRouteBase . '.weekoffs.apply') }}" class="lg:col-span-2 rounded-xl border border-[#E5DED4] p-5">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-brand-primary mb-2">Week off day</label>
                        <select name="day_of_week" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg" required>
                            @foreach([0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'] as $dayValue => $dayLabel)
                                <option value="{{ $dayValue }}">{{ $dayLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-brand-primary mb-2">Effective from</label>
                        <input type="date" name="effective_from" value="{{ now()->startOfMonth()->toDateString() }}" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg">
                    </div>
                </div>
                <div class="mt-4">
                    <div class="text-sm font-semibold text-brand-primary mb-2">Apply to roles</div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
                        @foreach($roles as $role)
                            <label class="flex items-center justify-between gap-3 rounded-lg border border-[#E5DED4] px-3 py-2 text-sm">
                                <span class="flex items-center gap-2">
                                    <input type="checkbox" name="role_slugs[]" value="{{ $role->slug }}">
                                    <span class="text-brand-primary">{{ $role->name }}</span>
                                </span>
                                <span class="text-xs text-[#6B7280]">{{ $role->users_count }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <button type="submit" class="mt-4 px-5 py-2 bg-[#111827] text-white rounded-lg text-sm font-semibold">Apply Weekly Off</button>
            </form>

            <div class="rounded-xl border border-[#E5DED4] p-5">
                <h3 class="text-sm font-semibold text-brand-primary">Current Active Week Offs</h3>
                <div class="mt-3 space-y-2 text-sm">
                    @forelse($weekoffSummary as $summary)
                        <div class="flex items-center justify-between gap-3 rounded-lg bg-[#F7F4EE] px-3 py-2">
                            <span class="text-brand-primary">{{ $summary->role_name }}</span>
                            <span class="text-[#6B7280]">{{ $summary->day_name }} / {{ $summary->users_count }}</span>
                        </div>
                    @empty
                        <div class="rounded-lg bg-[#F7F4EE] px-3 py-3 text-[#6B7280]">No active weekly off mapping yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6">
        <div class="flex items-center justify-between gap-4 mb-4">
            <div>
                <h2 class="text-lg font-semibold text-brand-primary">{{ $editingPolicy ? 'Edit Attendance Rule' : 'Create Attendance Rule' }}</h2>
                <p class="text-sm text-[#6B7280] mt-1">Simple rules are shown first. Advanced controls are available in the accordion below.</p>
            </div>
            @if($editingPolicy)
                <a href="{{ route($attendanceRouteBase . '.policies.index') }}" class="px-4 py-2 border border-[#E5DED4] rounded-lg text-sm text-[#6B7280]">Cancel Edit</a>
            @endif
        </div>
        <form method="POST" action="{{ $editingPolicy ? route($attendanceRouteBase . '.policies.update', $editingPolicy) : route($attendanceRouteBase . '.policies.store') }}" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @csrf
            @if($editingPolicy)
                @method('PUT')
            @endif
            <div class="md:col-span-3 grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="space-y-4 rounded-xl border border-[#E5DED4] p-5">
                    <h3 class="text-base font-semibold text-brand-primary">1. Simple Rules</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-[#374151] mb-1">Rule name</label>
                            <input type="text" name="name" value="{{ old('name', $editingPolicy?->name) }}" placeholder="Example: Main Office Policy" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[#374151] mb-1">Office</label>
                            <select name="office_location_id" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg">
                                <option value="">Default / Global</option>
                                @foreach($offices as $office)
                                    <option value="{{ $office->id }}" @selected((string) old('office_location_id', $editingPolicy?->office_location_id) === (string) $office->id)>{{ $office->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[#374151] mb-1">Reminder time</label>
                            <input type="time" name="reminder_time" value="{{ old('reminder_time', $editingPolicy?->reminder_time ?? '09:00') }}" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[#374151] mb-1">Late after</label>
                            <input type="time" name="late_after_time" value="{{ old('late_after_time', $editingPolicy?->late_after_time ?? '09:00') }}" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[#374151] mb-1">Normal window end</label>
                            <input type="time" name="normal_window_end_time" value="{{ old('normal_window_end_time', $editingPolicy?->normal_window_end_time ?? '11:30') }}" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[#374151] mb-1">Half-day start</label>
                            <input type="time" name="half_day_start_time" value="{{ old('half_day_start_time', $editingPolicy?->half_day_start_time ?? '13:00') }}" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[#374151] mb-1">Half-day end</label>
                            <input type="time" name="half_day_end_time" value="{{ old('half_day_end_time', $editingPolicy?->half_day_end_time ?? '16:00') }}" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg" required>
                        </div>
                    </div>
                </div>

                <div class="space-y-4 rounded-xl border border-[#E5DED4] p-5">
                    <h3 class="text-base font-semibold text-brand-primary">2. Attendance Options</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm text-brand-primary">
                        <label class="flex items-center gap-2"><input type="checkbox" name="geo_fence_required" value="1" {{ old('geo_fence_required', $editingPolicy?->geo_fence_required ?? true) ? 'checked' : '' }}> Geo fence on</label>
                        <label class="flex items-center gap-2"><input type="checkbox" name="photo_required" value="1" {{ old('photo_required', $editingPolicy?->photo_required ?? true) ? 'checked' : '' }}> Photo required</label>
                        <label class="flex items-center gap-2"><input type="checkbox" name="selfie_required" value="1" {{ old('selfie_required', $editingPolicy?->selfie_required ?? true) ? 'checked' : '' }}> Selfie required</label>
                        <label class="flex items-center gap-2"><input type="checkbox" name="allow_outside_punch_requests" value="1" {{ old('allow_outside_punch_requests', $editingPolicy?->allow_outside_punch_requests ?? true) ? 'checked' : '' }}> Allow outside requests</label>
                        <label class="flex items-center gap-2"><input type="checkbox" name="outside_punch_permission_default_enabled" value="1" {{ old('outside_punch_permission_default_enabled', $editingPolicy?->outside_punch_permission_default_enabled) ? 'checked' : '' }}> Allow policy date-range outside punch</label>
                        <label class="flex items-center gap-2"><input type="checkbox" name="is_default" value="1" {{ old('is_default', $editingPolicy?->is_default) ? 'checked' : '' }}> Set as default</label>
                        <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" {{ old('is_active', $editingPolicy?->is_active ?? true) ? 'checked' : '' }}> Active</label>
                    </div>
                    <div class="rounded-lg bg-[#F7F4EE] border border-[#E5DED4] px-4 py-3 text-sm text-[#374151]">
                        Outside rule: if the user is outside the office radius and no active date-range allowance exists, normal punch is blocked and the request flow is used.
                    </div>
                </div>
            </div>

            <div class="md:col-span-3">
                <details class="rounded-xl border border-[#E5DED4] p-5">
                    <summary class="cursor-pointer font-semibold text-brand-primary">Advanced Rule Settings</summary>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                        <div>
                            <label class="block text-sm font-medium text-[#374151] mb-1">Grace minutes</label>
                            <input type="number" name="grace_minutes" value="{{ old('grace_minutes', $editingPolicy?->grace_minutes ?? 0) }}" min="0" max="180" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[#374151] mb-1">Leave approval</label>
                            <select name="approval_mode_leave" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg">
                                <option value="hr_only" @selected(old('approval_mode_leave', $editingPolicy?->approval_mode_leave ?? 'hr_only') === 'hr_only')>HR only</option>
                                <option value="admin_only" @selected(old('approval_mode_leave', $editingPolicy?->approval_mode_leave) === 'admin_only')>Admin only</option>
                                <option value="either_first" @selected(old('approval_mode_leave', $editingPolicy?->approval_mode_leave) === 'either_first')>Either first</option>
                                <option value="both_required" @selected(old('approval_mode_leave', $editingPolicy?->approval_mode_leave) === 'both_required')>Both required</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[#374151] mb-1">Regularization approval</label>
                            <select name="approval_mode_regularization" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg">
                                <option value="hr_only" @selected(old('approval_mode_regularization', $editingPolicy?->approval_mode_regularization ?? 'hr_only') === 'hr_only')>HR only</option>
                                <option value="admin_only" @selected(old('approval_mode_regularization', $editingPolicy?->approval_mode_regularization) === 'admin_only')>Admin only</option>
                                <option value="either_first" @selected(old('approval_mode_regularization', $editingPolicy?->approval_mode_regularization) === 'either_first')>Either first</option>
                                <option value="both_required" @selected(old('approval_mode_regularization', $editingPolicy?->approval_mode_regularization) === 'both_required')>Both required</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[#374151] mb-1">Late penalty</label>
                            <select name="late_penalty_type" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg">
                                <option value="none" @selected(old('late_penalty_type', $editingPolicy?->late_penalty_type ?? 'none') === 'none')>No late penalty</option>
                                <option value="half_day" @selected(old('late_penalty_type', $editingPolicy?->late_penalty_type) === 'half_day')>Late to half day</option>
                                <option value="absent" @selected(old('late_penalty_type', $editingPolicy?->late_penalty_type) === 'absent')>Late to absent</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[#374151] mb-1">Late threshold</label>
                            <input type="number" name="late_penalty_threshold" value="{{ old('late_penalty_threshold', $editingPolicy?->late_penalty_threshold ?? 3) }}" min="1" max="31" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg" required>
                        </div>
                        <div class="md:col-span-2 flex flex-wrap gap-4 pt-7 text-sm text-brand-primary">
                            <label class="flex items-center gap-2"><input type="checkbox" name="face_review_required" value="1" {{ old('face_review_required', $editingPolicy?->face_review_required) ? 'checked' : '' }}> Face review required</label>
                            <label class="flex items-center gap-2"><input type="checkbox" name="payroll_block_on_pending_face_review" value="1" {{ old('payroll_block_on_pending_face_review', $editingPolicy?->payroll_block_on_pending_face_review) ? 'checked' : '' }}> Block payroll on pending review</label>
                            <label class="flex items-center gap-2"><input type="checkbox" name="overtime_enabled" value="1" {{ old('overtime_enabled', $editingPolicy?->overtime_enabled) ? 'checked' : '' }}> Overtime enabled</label>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[#374151] mb-1">Compress width</label>
                            <input type="number" name="compress_max_width" value="{{ old('compress_max_width', $editingPolicy?->compress_max_width ?? 1280) }}" min="320" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[#374151] mb-1">Compress height</label>
                            <input type="number" name="compress_max_height" value="{{ old('compress_max_height', $editingPolicy?->compress_max_height ?? 1280) }}" min="320" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[#374151] mb-1">Compress quality</label>
                            <input type="number" name="compress_quality" value="{{ old('compress_quality', $editingPolicy?->compress_quality ?? 75) }}" min="30" max="95" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[#374151] mb-1">Suspicious geo threshold (meters)</label>
                            <input type="number" name="suspicious_geo_threshold_meters" value="{{ old('suspicious_geo_threshold_meters', $editingPolicy?->suspicious_geo_threshold_meters ?? 100) }}" min="1" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[#374151] mb-1">Duplicate photo threshold</label>
                            <input type="number" name="duplicate_photo_threshold" value="{{ old('duplicate_photo_threshold', $editingPolicy?->duplicate_photo_threshold ?? 2) }}" min="1" max="50" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[#374151] mb-1">Regularization abuse threshold</label>
                            <input type="number" name="regularization_abuse_threshold" value="{{ old('regularization_abuse_threshold', $editingPolicy?->regularization_abuse_threshold ?? 3) }}" min="1" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[#374151] mb-1">Overtime after minutes</label>
                            <input type="number" name="overtime_after_minutes" value="{{ old('overtime_after_minutes', $editingPolicy?->overtime_after_minutes ?? 480) }}" min="60" max="1440" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[#374151] mb-1">Minimum overtime minutes</label>
                            <input type="number" name="overtime_min_minutes" value="{{ old('overtime_min_minutes', $editingPolicy?->overtime_min_minutes ?? 30) }}" min="1" max="600" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[#374151] mb-1">Face provider</label>
                            <input type="text" name="face_compare_provider" value="{{ old('face_compare_provider', $editingPolicy?->face_compare_provider) }}" placeholder="Optional" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[#374151] mb-1">Liveness provider</label>
                            <input type="text" name="liveness_provider" value="{{ old('liveness_provider', $editingPolicy?->liveness_provider) }}" placeholder="Optional" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg">
                        </div>
                    </div>
                </details>
            </div>

            <div class="md:col-span-3">
                <button type="submit" class="px-5 py-2 bg-[#205A44] text-white rounded-lg">{{ $editingPolicy ? 'Update Rule' : 'Save Rule' }}</button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6 overflow-x-auto">
        <h2 class="text-lg font-semibold text-brand-primary mb-4">Existing Rules</h2>
        <table class="min-w-full text-sm">
            <thead><tr class="text-left text-[#6B7280] border-b"><th class="py-2">Rule</th><th>Office</th><th>Reminder</th><th>Window</th><th>Approvals</th><th>Late Rule</th><th>Overtime</th><th>Geo / Photo / Outside</th><th>Fraud</th><th>Status</th><th class="text-right">Action</th></tr></thead>
            <tbody>
                @forelse($policies as $policy)
                    <tr class="border-b last:border-0">
                        <td class="py-3">{{ $policy->name }} @if($policy->is_default)<span class="text-xs text-[#205A44]">(Default)</span>@endif</td>
                        <td>{{ $policy->officeLocation?->name ?? 'Global' }}</td>
                        <td>{{ $policy->reminder_time }}</td>
                        <td>{{ $policy->normal_window_end_time }} / {{ $policy->half_day_start_time }}-{{ $policy->half_day_end_time }}</td>
                        <td>{{ $policy->approval_mode_leave }} / {{ $policy->approval_mode_regularization }}</td>
                        <td>{{ $policy->late_penalty_type }} @if($policy->late_penalty_type !== 'none') ({{ $policy->late_penalty_threshold }}) @endif</td>
                        <td>{{ $policy->overtime_enabled ? 'After ' . $policy->overtime_after_minutes . 'm / +' . $policy->overtime_min_minutes . 'm' : 'Disabled' }}</td>
                        <td>
                            {{ $policy->geo_fence_required ? 'Geo' : 'No Geo' }} /
                            {{ $policy->photo_required ? 'Photo' : 'No Photo' }} /
                            {{ $policy->allow_outside_punch_requests ? 'Request' : 'No Request' }}
                            @if($policy->outside_punch_permission_default_enabled)
                                <div class="text-xs text-[#6B7280] mt-1">Date-range allow on</div>
                            @endif
                        </td>
                        <td>{{ $policy->selfie_required ? 'Selfie' : 'No Selfie' }} / {{ $policy->face_review_required ? 'Review' : 'Auto' }}</td>
                        <td>
                            <span class="px-2 py-1 rounded-full text-xs {{ $policy->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                {{ $policy->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="py-3">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route($attendanceRouteBase . '.policies.index', ['edit' => $policy->id]) }}" class="inline-flex px-3 py-1.5 rounded-lg border border-[#E5DED4] text-[#205A44] hover:bg-[#F7F4EE]">
                                    Edit
                                </a>
                                <form method="POST" action="{{ route($attendanceRouteBase . '.policies.destroy', $policy) }}" onsubmit="return confirm('Delete this policy?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex px-3 py-1.5 rounded-lg border border-[#FECACA] text-[#B91C1C] hover:bg-[#FEF2F2]">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="py-4 text-[#6B7280]">No attendance rules configured yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

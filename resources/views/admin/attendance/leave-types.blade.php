@extends('layouts.app')

@section('title', 'Leave Types')
@section('page-title', '')
@section('page-subtitle', '')
@section('hide-app-header', '1')

@section('content')
@php($attendanceRouteBase = auth()->check() && auth()->user()->isHrManager() ? 'hr-manager.settings.attendance' : 'admin.attendance')
<div class="hr-setup-page">
    @include('attendance._flash')
    @include('admin.hr._workspace_hero', [
        'eyebrow' => 'Attendance Setup',
        'title' => 'Leave types',
        'subtitle' => 'Configure leave buckets such as CL, PL, and SL, along with quota and half-day rules.',
    ])
    @include('admin.hr._nav')
    @include('admin.attendance._nav')

    <div class="hr-setup-help">
        <h2 class="text-lg font-semibold text-brand-primary mb-3">Quick Help</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm text-[#374151]">
            <div class="bg-white rounded-lg border border-[#E5DED4] p-4">
                <div class="font-semibold text-brand-primary mb-2">What to Create</div>
                <p>Common leave types:</p>
                <p><strong>CL</strong> Casual Leave</p>
                <p><strong>SL</strong> Sick Leave</p>
                <p><strong>PL</strong> Paid Leave</p>
            </div>
            <div class="bg-white rounded-lg border border-[#E5DED4] p-4">
                <div class="font-semibold text-brand-primary mb-2">Recommended</div>
                <p>Paid leave: On for PL / CL</p>
                <p>Half day: On only if allowed in company policy</p>
                <p>Set quota on a yearly basis.</p>
            </div>
            <div class="bg-white rounded-lg border border-[#E5DED4] p-4">
                <div class="font-semibold text-brand-primary mb-2">Important</div>
                <p>Do not delete a leave type that has already been used.</p>
                <p>In those cases, mark it inactive instead.</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6">
        <div class="flex items-center justify-between gap-4 mb-4">
            <h2 class="text-lg font-semibold text-brand-primary">
                {{ $editingLeaveType ? 'Edit Leave Type' : 'Create Leave Type' }}
            </h2>
            @if($editingLeaveType)
                <a href="{{ route($attendanceRouteBase . '.leave-types.index') }}" class="px-4 py-2 border border-[#E5DED4] rounded-lg text-sm text-[#6B7280]">Cancel Edit</a>
            @endif
        </div>
        <form method="POST" action="{{ $editingLeaveType ? route($attendanceRouteBase . '.leave-types.update', $editingLeaveType) : route($attendanceRouteBase . '.leave-types.store') }}" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @csrf
            @if($editingLeaveType)
                @method('PUT')
            @endif
            <div>
                <label class="block text-sm font-medium text-[#374151] mb-1">Leave name</label>
                <input type="text" name="name" placeholder="Example: Casual Leave" value="{{ old('name', $editingLeaveType?->name) }}" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-[#374151] mb-1">Code</label>
                <input type="text" name="code" placeholder="Example: CL" value="{{ old('code', $editingLeaveType?->code) }}" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg">
            </div>
            <div>
                <label class="block text-sm font-medium text-[#374151] mb-1">Annual quota</label>
                <input type="number" step="0.5" min="0" name="annual_quota" placeholder="Example: 12" value="{{ old('annual_quota', $editingLeaveType?->annual_quota) }}" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg" required>
            </div>
            <label class="flex items-center gap-2 text-sm text-brand-primary"><input type="checkbox" name="is_paid" value="1" {{ old('is_paid', $editingLeaveType?->is_paid ?? true) ? 'checked' : '' }}> Paid leave</label>
            <label class="flex items-center gap-2 text-sm text-brand-primary"><input type="checkbox" name="allow_half_day" value="1" {{ old('allow_half_day', $editingLeaveType?->allow_half_day) ? 'checked' : '' }}> Half day allowed</label>
            <label class="flex items-center gap-2 text-sm text-brand-primary"><input type="checkbox" name="is_active" value="1" {{ old('is_active', $editingLeaveType?->is_active ?? true) ? 'checked' : '' }}> Active</label>
            <div class="md:col-span-3">
                <button type="submit" class="px-5 py-2 bg-[#205A44] text-white rounded-lg">
                    {{ $editingLeaveType ? 'Update Leave Type' : 'Save Leave Type' }}
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6 overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead><tr class="text-left text-[#6B7280] border-b"><th class="py-2">Name</th><th>Code</th><th>Paid</th><th>Half Day</th><th>Quota</th><th>Status</th><th class="text-right">Action</th></tr></thead>
            <tbody>
                @forelse($leaveTypes as $type)
                    <tr class="border-b last:border-0">
                        <td class="py-3">{{ $type->name }}</td>
                        <td>{{ $type->code }}</td>
                        <td>{{ $type->is_paid ? 'Yes' : 'No' }}</td>
                        <td>{{ $type->allow_half_day ? 'Yes' : 'No' }}</td>
                        <td>{{ number_format((float) $type->annual_quota, 2) }}</td>
                        <td>{{ $type->is_active ? 'Active' : 'Inactive' }}</td>
                        <td class="text-right">
                            <div class="inline-flex items-center gap-2">
                                <a href="{{ route($attendanceRouteBase . '.leave-types.index', ['edit' => $type->id]) }}" class="inline-flex px-3 py-1.5 rounded-lg border border-[#E5DED4] text-[#205A44] hover:bg-[#F7F4EE]">
                                    Edit
                                </a>
                                <form method="POST" action="{{ route($attendanceRouteBase . '.leave-types.destroy', $type) }}" onsubmit="return confirm('Delete this leave type?');">
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
                    <tr><td colspan="7" class="py-4 text-[#6B7280]">No leave types configured yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

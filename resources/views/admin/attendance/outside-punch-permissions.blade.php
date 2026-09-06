@extends('layouts.app')

@section('title', 'Outside Punch Allowance')
@section('page-title', '')
@section('page-subtitle', '')
@section('hide-app-header', '1')

@section('content')
@php($attendanceRouteBase = auth()->check() && auth()->user()->isHrManager() ? 'hr-manager.settings.attendance' : 'admin.attendance')
<div class="hr-setup-page">
    @include('attendance._flash')
    @include('admin.hr._workspace_hero', [
        'eyebrow' => 'Attendance Setup',
        'title' => 'Outside punch allowance',
        'subtitle' => 'Configure outside punch allowance windows for specific users or policies.',
    ])
    @include('admin.hr._nav')
    @include('admin.attendance._nav')

    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6">
            <div class="text-sm text-[#6B7280]">Allowances</div>
            <div class="mt-2 text-3xl font-bold text-brand-primary">{{ $permissions->count() }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6">
            <div class="text-sm text-[#6B7280]">Active</div>
            <div class="mt-2 text-3xl font-bold text-brand-primary">{{ $permissions->where('is_active', true)->count() }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6">
            <div class="text-sm text-[#6B7280]">User Specific</div>
            <div class="mt-2 text-3xl font-bold text-brand-primary">{{ $permissions->whereNotNull('user_id')->count() }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6">
            <div class="text-sm text-[#6B7280]">Policy Default</div>
            <div class="mt-2 text-3xl font-bold text-brand-primary">{{ $permissions->whereNull('user_id')->count() }}</div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6">
        <div class="flex items-center justify-between gap-4 mb-4">
            <div>
                <h2 class="text-lg font-semibold text-brand-primary">{{ $editingPermission ? 'Edit Outside Allowance' : 'Create Outside Allowance' }}</h2>
                <p class="text-sm text-[#6B7280] mt-1">If a user has an active allowance, outside punch will go through directly without any request.</p>
            </div>
            @if($editingPermission)
                <a href="{{ route($attendanceRouteBase . '.outside-punch-permissions.index') }}" class="px-4 py-2 border border-[#E5DED4] rounded-lg text-sm text-[#6B7280]">Cancel Edit</a>
            @endif
        </div>

        <form method="POST" action="{{ $editingPermission ? route($attendanceRouteBase . '.outside-punch-permissions.update', $editingPermission) : route($attendanceRouteBase . '.outside-punch-permissions.store') }}" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            @csrf
            @if($editingPermission)
                @method('PUT')
            @endif
            <div>
                <label class="block text-sm font-medium text-[#374151] mb-1">Employee</label>
                <select name="user_id" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg">
                    <option value="">Policy default allowance</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}" @selected((string) old('user_id', $editingPermission?->user_id) === (string) $user->id)>{{ $user->name }}{{ $user->role?->name ? ' · ' . $user->role->name : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-[#374151] mb-1">Attendance Rule</label>
                <select name="attendance_policy_id" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg">
                    <option value="">No policy default</option>
                    @foreach($policies as $policy)
                        <option value="{{ $policy->id }}" @selected((string) old('attendance_policy_id', $editingPermission?->attendance_policy_id) === (string) $policy->id)>{{ $policy->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="rounded-xl border border-[#E5DED4] bg-[#F7F4EE] px-4 py-3 text-sm text-[#374151]">
                User-specific allowances are checked first. Policy default runs only if no user window matches.
            </div>
            <div>
                <label class="block text-sm font-medium text-[#374151] mb-1">Start Date</label>
                <input type="date" name="start_date" value="{{ old('start_date', optional($editingPermission?->start_date)->toDateString() ?: now()->toDateString()) }}" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-[#374151] mb-1">End Date</label>
                <input type="date" name="end_date" value="{{ old('end_date', optional($editingPermission?->end_date)->toDateString() ?: now()->toDateString()) }}" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-[#374151] mb-1">Reason / Note</label>
                <input type="text" name="reason" value="{{ old('reason', $editingPermission?->reason) }}" placeholder="Example: Field visit approval" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg">
            </div>
            <div class="flex flex-col justify-end gap-2">
                <label class="inline-flex items-center gap-2 text-sm text-brand-primary">
                    <input type="checkbox" name="allow_punch_in" value="1" class="rounded border-[#E5DED4] text-[#205A44]" {{ old('allow_punch_in', $editingPermission?->allow_punch_in ?? true) ? 'checked' : '' }}>
                    Allow Punch In
                </label>
                <label class="inline-flex items-center gap-2 text-sm text-brand-primary">
                    <input type="checkbox" name="allow_punch_out" value="1" class="rounded border-[#E5DED4] text-[#205A44]" {{ old('allow_punch_out', $editingPermission?->allow_punch_out ?? true) ? 'checked' : '' }}>
                    Allow Punch Out
                </label>
                <label class="inline-flex items-center gap-2 text-sm text-brand-primary">
                    <input type="checkbox" name="is_active" value="1" class="rounded border-[#E5DED4] text-[#205A44]" {{ old('is_active', $editingPermission?->is_active ?? true) ? 'checked' : '' }}>
                    Active
                </label>
            </div>
            <div class="md:col-span-2 xl:col-span-3">
                <button type="submit" class="px-5 py-2 bg-[#205A44] text-white rounded-lg">{{ $editingPermission ? 'Update Allowance' : 'Save Allowance' }}</button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6 overflow-x-auto">
        <h2 class="text-lg font-semibold text-brand-primary mb-4">Active Windows</h2>
        <table class="min-w-full text-sm">
            <thead>
                <tr class="text-left text-[#6B7280] border-b">
                    <th class="py-2">Target</th>
                    <th>Rule</th>
                    <th>Window</th>
                    <th>Allowed</th>
                    <th>Status</th>
                    <th>Created By</th>
                    <th class="text-right">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($permissions as $permission)
                    <tr class="border-b last:border-0">
                        <td class="py-3">
                            <div class="font-medium text-[#111827]">{{ $permission->user?->name ?? 'Policy Default' }}</div>
                            <div class="text-xs text-[#6B7280]">{{ $permission->reason ?: 'No note' }}</div>
                        </td>
                        <td>{{ $permission->attendancePolicy?->name ?? 'Any rule' }}</td>
                        <td>{{ optional($permission->start_date)->format('d M Y') }} to {{ optional($permission->end_date)->format('d M Y') }}</td>
                        <td>
                            <span class="text-xs px-2 py-1 rounded-full {{ $permission->allow_punch_in ? 'bg-[#DCFCE7] text-[#166534]' : 'bg-[#F3F4F6] text-[#6B7280]' }}">In</span>
                            <span class="text-xs px-2 py-1 rounded-full {{ $permission->allow_punch_out ? 'bg-[#DBEAFE] text-[#1D4ED8]' : 'bg-[#F3F4F6] text-[#6B7280]' }}">Out</span>
                        </td>
                        <td>
                            <span class="px-2 py-1 rounded-full text-xs {{ $permission->is_active ? 'bg-[#DCFCE7] text-[#166534]' : 'bg-[#FEE2E2] text-[#991B1B]' }}">
                                {{ $permission->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td>{{ $permission->creator?->name ?? 'System' }}</td>
                        <td class="py-3">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route($attendanceRouteBase . '.outside-punch-permissions.index', ['edit' => $permission->id]) }}" class="inline-flex px-3 py-1.5 rounded-lg border border-[#E5DED4] text-[#205A44] hover:bg-[#F7F4EE]">Edit</a>
                                <form method="POST" action="{{ route($attendanceRouteBase . '.outside-punch-permissions.destroy', $permission) }}" onsubmit="return confirm('Delete this outside allowance?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex px-3 py-1.5 rounded-lg border border-[#FECACA] text-[#B91C1C] hover:bg-[#FEF2F2]">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-4 text-[#6B7280]">No outside punch allowances created yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

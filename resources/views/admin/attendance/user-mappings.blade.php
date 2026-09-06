@extends('layouts.app')

@section('title', 'Assign Attendance')
@section('page-title', '')
@section('page-subtitle', '')
@section('hide-app-header', '1')

@section('content')
@php
    $attendanceRouteBase = auth()->check() && auth()->user()->isHrManager() ? 'hr-manager.settings.attendance' : 'admin.attendance';
    $mappedUsersCount = $mappings->count();
    $enabledUsersCount = $mappings->filter(fn ($mapping) => (bool) $mapping->attendance_enabled)->count();
    $unmappedUsersCount = $users->count() - count($mappedUserIds);
@endphp
<div class="hr-setup-page">
    @include('attendance._flash')
    @include('admin.hr._workspace_hero', [
        'eyebrow' => 'Attendance Setup',
        'title' => 'Assign attendance',
        'subtitle' => 'Map users with an office, attendance rule, and attendance visibility.',
    ])
    @include('admin.hr._nav')
    @include('admin.attendance._nav')

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6">
            <div class="text-sm text-[#6B7280]">Mapped Users</div>
            <div class="mt-2 text-3xl font-bold text-brand-primary">{{ $mappedUsersCount }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6">
            <div class="text-sm text-[#6B7280]">Unmapped Users</div>
            <div class="mt-2 text-3xl font-bold text-brand-primary">{{ max($unmappedUsersCount, 0) }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6">
            <div class="text-sm text-[#6B7280]">Attendance Enabled</div>
            <div class="mt-2 text-3xl font-bold text-brand-primary">{{ $enabledUsersCount }}</div>
        </div>
    </div>

    <form method="POST" action="{{ route($attendanceRouteBase . '.user-mappings.bulk-store') }}" id="bulkAttendanceMappingForm" class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6 space-y-6">
        @csrf
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-brand-primary">Assign Users</h2>
            </div>
            <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                <button type="button" id="selectVisibleUsersBtn" class="px-4 py-2 rounded-lg border border-[#205A44] text-[#205A44] text-sm font-medium hover:bg-[#F0FDF4]">Select Visible</button>
                <button type="button" id="selectUnmappedUsersBtn" class="px-4 py-2 rounded-lg border border-[#E5DED4] text-[#374151] text-sm font-medium hover:bg-[#F7F4EE]">Only Unmapped</button>
                <button type="button" id="clearUserSelectionBtn" class="px-4 py-2 rounded-lg border border-[#E5DED4] text-[#374151] text-sm font-medium hover:bg-[#F7F4EE]">Clear</button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-[#374151] mb-1">Search User</label>
                <input type="text" id="userMappingSearchInput" placeholder="Search by name or email" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg">
            </div>
            <div>
                <label class="block text-sm font-medium text-[#374151] mb-1">Role Filter</label>
                <select id="userMappingRoleFilter" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg">
                    <option value="">All Roles</option>
                    @foreach($users->pluck('role.name')->filter()->unique()->sort() as $roleName)
                        <option value="{{ strtolower($roleName) }}">{{ $roleName }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-[#374151] mb-1">Mapping Filter</label>
                <select id="userMappingStatusFilter" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg">
                    <option value="">All Users</option>
                    <option value="unmapped">Only Unmapped</option>
                    <option value="mapped">Only Mapped</option>
                    <option value="enabled">Attendance Enabled</option>
                    <option value="disabled">Attendance Hidden</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4">
            <div>
                <label class="block text-sm font-medium text-[#374151] mb-1">Office</label>
                <select name="office_location_id" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg">
                    <option value="">Select office</option>
                    @foreach($offices as $office)
                        <option value="{{ $office->id }}">{{ $office->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-[#374151] mb-1">Rule</label>
                <select name="attendance_policy_id" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg">
                    <option value="">Select rule</option>
                    @foreach($policies as $policy)
                        <option value="{{ $policy->id }}">{{ $policy->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-[#374151] mb-1">Mode</label>
                <select name="attendance_rollout_stage" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg">
                    <option value="pilot">Testing</option>
                    <option value="live">Active</option>
                    <option value="disabled">Off</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-[#374151] mb-1">Effective From</label>
                <input type="date" name="effective_from" value="{{ now()->toDateString() }}" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg">
            </div>
            <div class="flex flex-col justify-end gap-2">
                <label class="inline-flex items-center gap-2 text-sm text-brand-primary">
                    <input type="checkbox" name="attendance_enabled" value="1" class="rounded border-[#E5DED4] text-[#205A44]" checked>
                    Attendance On
                </label>
                <div>
                    <label class="block text-sm font-medium text-[#374151] mb-1">Outside Request</label>
                    <select name="allow_outside_punch_requests" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg">
                        <option value="inherit">Policy Default</option>
                        <option value="1">Allow Request</option>
                        <option value="0">Block Request</option>
                    </select>
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-brand-primary">
                    <input type="checkbox" name="overwrite_existing" value="1" class="rounded border-[#E5DED4] text-[#205A44]" checked>
                    Update Existing Mapping
                </label>
            </div>
        </div>

        <div class="rounded-xl border border-[#E5DED4] overflow-hidden">
            <div class="px-4 py-3 bg-[#F9FAFB] border-b border-[#E5DED4] flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <div class="text-sm text-[#374151]">
                    <span id="userMappingVisibleCount">{{ $users->count() }}</span> users visible
                    <span class="text-[#9CA3AF]">|</span>
                    <span id="userMappingSelectedCount">0</span> selected
                </div>
            </div>
            <div class="max-h-[420px] overflow-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-white sticky top-0 z-10">
                        <tr class="text-left text-[#6B7280] border-b">
                            <th class="py-3 px-4 w-12">
                                <input type="checkbox" id="selectAllVisibleUsersCheckbox" class="rounded border-[#E5DED4] text-[#205A44]">
                            </th>
                            <th class="py-3 px-4">User</th>
                            <th class="py-3 px-4">Role</th>
                            <th class="py-3 px-4">Current Office</th>
                            <th class="py-3 px-4">Current Rule</th>
                            <th class="py-3 px-4">Status</th>
                        </tr>
                    </thead>
                    <tbody id="userMappingSelectionTable">
                        @foreach($users as $user)
                            @php
                                $mapping = $mappings->firstWhere('user_id', $user->id);
                                $isMapped = $mapping !== null;
                                $isEnabled = $mapping?->attendance_enabled;
                            @endphp
                            <tr
                                class="border-b last:border-0 user-mapping-row"
                                data-user-name="{{ strtolower($user->name) }}"
                                data-user-email="{{ strtolower($user->email) }}"
                                data-role="{{ strtolower($user->role->name ?? '') }}"
                                data-mapped="{{ $isMapped ? 'yes' : 'no' }}"
                                data-enabled="{{ $isEnabled ? 'yes' : 'no' }}"
                            >
                                <td class="py-3 px-4 align-top">
                                    <input type="checkbox" name="user_ids[]" value="{{ $user->id }}" class="rounded border-[#E5DED4] text-[#205A44] user-mapping-checkbox">
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-medium text-[#111827]">{{ $user->name }}</div>
                                    <div class="text-xs text-[#6B7280]">{{ $user->email }}</div>
                                </td>
                                <td class="py-3 px-4">{{ $user->role->name ?? 'Role' }}</td>
                                <td class="py-3 px-4">{{ $mapping?->officeLocation?->name ?? 'Not set' }}</td>
                                <td class="py-3 px-4">{{ $mapping?->attendancePolicy?->name ?? 'Not set' }}</td>
                                <td class="py-3 px-4">
                                    @if($isMapped)
                                        <span class="px-2 py-1 rounded-full text-xs {{ $isEnabled ? 'bg-[#DCFCE7] text-[#166534]' : 'bg-[#FEE2E2] text-[#991B1B]' }}">
                                            {{ $isEnabled ? 'Enabled' : 'Mapped / Hidden' }}
                                        </span>
                                    @else
                                        <span class="px-2 py-1 rounded-full text-xs bg-[#FEF3C7] text-[#92400E]">Unmapped</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <button type="submit" class="px-5 py-2 bg-[#205A44] text-white rounded-lg font-medium">Apply to Selected Users</button>
        </div>
    </form>

    @if($editingMapping)
        <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6">
            <div class="flex items-center justify-between gap-4 mb-4">
                <div>
                    <h2 class="text-lg font-semibold text-brand-primary">Edit Existing Assignment</h2>
                </div>
                <a href="{{ route($attendanceRouteBase . '.user-mappings.index') }}" class="px-4 py-2 border border-[#E5DED4] rounded-lg text-sm text-[#6B7280]">Close Edit</a>
            </div>
            <form method="POST" action="{{ route($attendanceRouteBase . '.user-mappings.update', $editingMapping) }}" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-sm font-medium text-[#374151] mb-1">User</label>
                    <input type="text" value="{{ $editingMapping->user?->name }}" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg bg-[#F9FAFB]" disabled>
                </div>
                <div>
                    <label class="block text-sm font-medium text-[#374151] mb-1">Office</label>
                    <select name="office_location_id" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg">
                        <option value="">Select office</option>
                        @foreach($offices as $office)
                            <option value="{{ $office->id }}" @selected((string) old('office_location_id', $editingMapping->office_location_id) === (string) $office->id)>{{ $office->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-[#374151] mb-1">Attendance rule</label>
                    <select name="attendance_policy_id" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg">
                        <option value="">Select rule</option>
                        @foreach($policies as $policy)
                            <option value="{{ $policy->id }}" @selected((string) old('attendance_policy_id', $editingMapping->attendance_policy_id) === (string) $policy->id)>{{ $policy->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-[#374151] mb-1">Employee code</label>
                    <input type="text" name="employee_code" value="{{ old('employee_code', $editingMapping->employee_code) }}" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium text-[#374151] mb-1">Salary mode</label>
                    <input type="text" name="salary_mode" value="{{ old('salary_mode', $editingMapping->salary_mode) }}" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium text-[#374151] mb-1">Mode</label>
                    <select name="attendance_rollout_stage" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg">
                        <option value="pilot" @selected(old('attendance_rollout_stage', $editingMapping->attendance_rollout_stage ?? 'pilot') === 'pilot')>Testing</option>
                        <option value="live" @selected(old('attendance_rollout_stage', $editingMapping->attendance_rollout_stage) === 'live')>Active</option>
                        <option value="disabled" @selected(old('attendance_rollout_stage', $editingMapping->attendance_rollout_stage) === 'disabled')>Off</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-[#374151] mb-1">Base salary</label>
                    <input type="number" name="base_salary" value="{{ old('base_salary', $editingMapping->base_salary) }}" min="0" step="0.01" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium text-[#374151] mb-1">Effective from</label>
                    <input type="date" name="effective_from" value="{{ old('effective_from', optional($editingMapping->effective_from)->toDateString()) }}" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg">
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-brand-primary">
                    <input type="checkbox" name="attendance_enabled" value="1" class="rounded border-[#E5DED4] text-[#205A44]" {{ old('attendance_enabled', $editingMapping->attendance_enabled) ? 'checked' : '' }}>
                    Attendance on for this user
                </label>
                <div>
                    <label class="block text-sm font-medium text-[#374151] mb-1">Outside request mode</label>
                    <select name="allow_outside_punch_requests" class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg">
                        <option value="inherit" @selected(old('allow_outside_punch_requests', $editingMapping->allow_outside_punch_requests === null ? 'inherit' : ($editingMapping->allow_outside_punch_requests ? '1' : '0')) === 'inherit')>Use policy default</option>
                        <option value="1" @selected(old('allow_outside_punch_requests', $editingMapping->allow_outside_punch_requests === null ? 'inherit' : ($editingMapping->allow_outside_punch_requests ? '1' : '0')) === '1')>Allow request</option>
                        <option value="0" @selected(old('allow_outside_punch_requests', $editingMapping->allow_outside_punch_requests === null ? 'inherit' : ($editingMapping->allow_outside_punch_requests ? '1' : '0')) === '0')>Block request</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <button type="submit" class="px-5 py-2 bg-[#205A44] text-white rounded-lg">Update Mapping</button>
                </div>
            </form>
        </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6 overflow-x-auto">
        <div class="flex items-center justify-between gap-4 mb-4">
            <div>
                <h2 class="text-lg font-semibold text-brand-primary">Existing Assignments</h2>
                <p class="text-sm text-[#6B7280] mt-1">Review, edit, or delete current office and rule assignments from here.</p>
            </div>
        </div>
        <table class="min-w-full text-sm">
            <thead>
                <tr class="text-left text-[#6B7280] border-b">
                    <th class="py-2">User</th>
                    <th>Role</th>
                    <th>Office</th>
                    <th>Rule</th>
                    <th>Mode</th>
                    <th>Outside</th>
                    <th>Effective From</th>
                    <th class="text-right">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($mappings as $mapping)
                    <tr class="border-b last:border-0">
                        <td class="py-3">{{ $mapping->user?->name }}</td>
                        <td>{{ $mapping->user?->role?->name }}</td>
                        <td>{{ $mapping->officeLocation?->name ?? 'Not set' }}</td>
                        <td>{{ $mapping->attendancePolicy?->name ?? 'Not set' }}</td>
                        <td>
                            <span class="px-2 py-1 rounded-full text-xs {{ $mapping->attendance_enabled ? 'bg-[#DCFCE7] text-[#166534]' : 'bg-[#FEE2E2] text-[#991B1B]' }}">
                                {{ $mapping->attendance_enabled ? 'On' : 'Off' }}
                            </span>
                            <div class="text-xs text-[#6B7280] mt-1">
                                {{ match($mapping->attendance_rollout_stage ?: 'pilot') {
                                    'live' => 'Active',
                                    'disabled' => 'Off',
                                    default => 'Testing',
                                } }}
                            </div>
                        </td>
                        <td>
                            @if($mapping->allow_outside_punch_requests === null)
                                <span class="px-2 py-1 rounded-full text-xs bg-[#E0E7FF] text-[#3730A3]">Policy Default</span>
                            @elseif($mapping->allow_outside_punch_requests)
                                <span class="px-2 py-1 rounded-full text-xs bg-[#DCFCE7] text-[#166534]">Request On</span>
                            @else
                                <span class="px-2 py-1 rounded-full text-xs bg-[#FEE2E2] text-[#991B1B]">Request Off</span>
                            @endif
                        </td>
                        <td>{{ optional($mapping->effective_from)->toDateString() ?: '--' }}</td>
                        <td class="py-3">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route($attendanceRouteBase . '.user-mappings.index', ['edit' => $mapping->id]) }}" class="inline-flex px-3 py-1.5 rounded-lg border border-[#E5DED4] text-[#205A44] hover:bg-[#F7F4EE]">
                                    Edit
                                </a>
                                <form method="POST" action="{{ route($attendanceRouteBase . '.user-mappings.destroy', $mapping) }}" onsubmit="return confirm('Delete this user mapping?');">
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
                    <tr><td colspan="8" class="py-4 text-[#6B7280]">No mappings saved yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<script>
    (function () {
        const searchInput = document.getElementById('userMappingSearchInput');
        const roleFilter = document.getElementById('userMappingRoleFilter');
        const statusFilter = document.getElementById('userMappingStatusFilter');
        const rows = Array.from(document.querySelectorAll('.user-mapping-row'));
        const checkboxes = Array.from(document.querySelectorAll('.user-mapping-checkbox'));
        const visibleCountEl = document.getElementById('userMappingVisibleCount');
        const selectedCountEl = document.getElementById('userMappingSelectedCount');
        const selectVisibleBtn = document.getElementById('selectVisibleUsersBtn');
        const selectUnmappedBtn = document.getElementById('selectUnmappedUsersBtn');
        const clearSelectionBtn = document.getElementById('clearUserSelectionBtn');
        const selectAllVisibleCheckbox = document.getElementById('selectAllVisibleUsersCheckbox');
        const bulkForm = document.getElementById('bulkAttendanceMappingForm');

        function isRowVisible(row) {
            return row.style.display !== 'none';
        }

        function updateSelectedCount() {
            const selectedCount = checkboxes.filter((checkbox) => checkbox.checked).length;
            selectedCountEl.textContent = String(selectedCount);
        }

        function updateVisibleCount() {
            const visibleRows = rows.filter(isRowVisible);
            visibleCountEl.textContent = String(visibleRows.length);

            const visibleCheckboxes = visibleRows
                .map((row) => row.querySelector('.user-mapping-checkbox'))
                .filter(Boolean);

            if (visibleCheckboxes.length === 0) {
                selectAllVisibleCheckbox.checked = false;
                selectAllVisibleCheckbox.indeterminate = false;
                return;
            }

            const checkedVisible = visibleCheckboxes.filter((checkbox) => checkbox.checked).length;
            selectAllVisibleCheckbox.checked = checkedVisible === visibleCheckboxes.length;
            selectAllVisibleCheckbox.indeterminate = checkedVisible > 0 && checkedVisible < visibleCheckboxes.length;
        }

        function applyFilters() {
            const search = (searchInput.value || '').trim().toLowerCase();
            const role = roleFilter.value || '';
            const status = statusFilter.value || '';

            rows.forEach((row) => {
                const matchesSearch = search === ''
                    || row.dataset.userName.includes(search)
                    || row.dataset.userEmail.includes(search);
                const matchesRole = role === '' || row.dataset.role === role;
                const matchesStatus = status === ''
                    || (status === 'mapped' && row.dataset.mapped === 'yes')
                    || (status === 'unmapped' && row.dataset.mapped === 'no')
                    || (status === 'enabled' && row.dataset.enabled === 'yes')
                    || (status === 'disabled' && row.dataset.enabled === 'no');

                row.style.display = matchesSearch && matchesRole && matchesStatus ? '' : 'none';
            });

            updateVisibleCount();
            updateSelectedCount();
        }

        searchInput?.addEventListener('input', applyFilters);
        roleFilter?.addEventListener('change', applyFilters);
        statusFilter?.addEventListener('change', applyFilters);

        checkboxes.forEach((checkbox) => {
            checkbox.addEventListener('change', function () {
                updateVisibleCount();
                updateSelectedCount();
            });
        });

        selectVisibleBtn?.addEventListener('click', function () {
            rows.filter(isRowVisible).forEach((row) => {
                const checkbox = row.querySelector('.user-mapping-checkbox');
                if (checkbox) {
                    checkbox.checked = true;
                }
            });
            updateVisibleCount();
            updateSelectedCount();
        });

        selectUnmappedBtn?.addEventListener('click', function () {
            statusFilter.value = 'unmapped';
            applyFilters();
            rows.filter(isRowVisible).forEach((row) => {
                const checkbox = row.querySelector('.user-mapping-checkbox');
                if (checkbox) {
                    checkbox.checked = true;
                }
            });
            updateVisibleCount();
            updateSelectedCount();
        });

        clearSelectionBtn?.addEventListener('click', function () {
            checkboxes.forEach((checkbox) => {
                checkbox.checked = false;
            });
            updateVisibleCount();
            updateSelectedCount();
        });

        selectAllVisibleCheckbox?.addEventListener('change', function () {
            rows.filter(isRowVisible).forEach((row) => {
                const checkbox = row.querySelector('.user-mapping-checkbox');
                if (checkbox) {
                    checkbox.checked = selectAllVisibleCheckbox.checked;
                }
            });
            updateVisibleCount();
            updateSelectedCount();
        });

        bulkForm?.addEventListener('submit', function (event) {
            const selectedCount = checkboxes.filter((checkbox) => checkbox.checked).length;
            if (selectedCount < 1) {
                event.preventDefault();
                alert('Select at least one user.');
            }
        });

        applyFilters();
    })();
</script>
@endsection

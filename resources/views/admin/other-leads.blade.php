@extends('layouts.app')

@section('title', auth()->user()->isCrm() ? 'Other Leads - CRM' : 'Other Leads - Admin')
@section('page-title', 'Other Leads')

@section('content')
@php
    $statusLabels = [
        'junk' => 'Junk',
        'not_interested' => 'Not Interested',
        'dead' => 'Dead',
    ];
@endphp

<div class="w-full space-y-6 pb-6">
    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif
    @if(session('warning'))
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ session('warning') }}</div>
    @endif

    <div class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm lg:p-6">
        <form method="GET" action="{{ route('admin.other-leads.index') }}" class="grid grid-cols-1 gap-3 xl:grid-cols-[minmax(280px,1.4fr)_190px_240px_auto_auto]">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name, phone, email" class="min-w-0 rounded-xl border border-gray-300 px-4 py-3 text-sm text-gray-900">
            <select name="type" class="rounded-xl border border-gray-300 px-4 py-3 text-sm text-gray-900">
                <option value="">All Types</option>
                <option value="junk" {{ request('type') === 'junk' ? 'selected' : '' }}>Junk</option>
                <option value="not_interested" {{ request('type') === 'not_interested' ? 'selected' : '' }}>Not Interested</option>
                <option value="dead" {{ request('type') === 'dead' ? 'selected' : '' }}>Dead</option>
            </select>
            <select name="assigned_to" class="rounded-xl border border-gray-300 px-4 py-3 text-sm text-gray-900">
                <option value="">Last Assigned User</option>
                @foreach($filterUsers as $user)
                    <option value="{{ $user->id }}" {{ (string) request('assigned_to') === (string) $user->id ? 'selected' : '' }}>
                        {{ $user->name }}{{ $user->role?->name ? ' (' . $user->role->name . ')' : '' }}
                    </option>
                @endforeach
            </select>
            <button type="submit" class="rounded-xl bg-gradient-to-r from-[#063A1C] to-[#205A44] px-5 py-3 text-sm font-medium text-white">Filter</button>
            <a href="{{ route('admin.other-leads.index') }}" class="rounded-xl bg-gray-100 px-5 py-3 text-center text-sm font-medium text-gray-700">Clear</a>
        </form>
    </div>

    <form method="POST" action="{{ route('admin.other-leads.reassign') }}" class="space-y-4">
        @csrf
        <div class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm lg:p-6">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">Other Leads Queue</h2>
                    <p class="mt-1 text-sm text-gray-500">{{ auth()->user()->isCrm() ? 'CRM yahan se junk, not interested aur dead leads ko kisi aur user ko wapas active queue me bhej sakta hai.' : 'Admin yahan se junk, not interested aur dead leads ko kisi aur user ko wapas active queue me bhej sakta hai.' }}</p>
                </div>
                <div class="flex w-full flex-col gap-3 sm:flex-row xl:w-auto">
                    <select name="assigned_to" class="min-w-0 rounded-xl border border-gray-300 px-4 py-3 text-sm text-gray-900 xl:min-w-[300px]" required>
                        <option value="">Select user to allocate</option>
                        @foreach($eligibleUsers as $user)
                            <option value="{{ $user->id }}">
                                {{ $user->name }}{{ $user->role?->name ? ' (' . $user->role->name . ')' : '' }}
                            </option>
                        @endforeach
                    </select>
                    <button type="submit" class="rounded-xl bg-gray-900 px-5 py-3 text-sm font-medium text-white">Reassign Selected</button>
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-3xl border border-gray-200 bg-white shadow-sm">
            @if($otherLeads->count())
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    <input type="checkbox" id="selectAllOtherLeads">
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Lead</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Source</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Type</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Reason</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Marked By</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Marked At</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Last Assigned</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach($otherLeads as $lead)
                                @php $latestAssignment = $lead->assignments->first(); @endphp
                                <tr class="align-top">
                                    <td class="px-4 py-4">
                                        <input type="checkbox" name="lead_ids[]" value="{{ $lead->id }}" class="other-lead-checkbox">
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="font-medium text-gray-900">{{ $lead->name }}</div>
                                        <div class="mt-1 text-sm text-gray-500">{{ $lead->phone }}</div>
                                        @if($lead->email)
                                            <div class="text-sm text-gray-500">{{ $lead->email }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                                            {{ $lead->source_label ?: 'Unknown' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4">
                                        @php
                                            $displayType = $lead->is_dead ? 'dead' : $lead->status;
                                            $typeClasses = match ($displayType) {
                                                'junk' => 'bg-orange-100 text-orange-800',
                                                'dead' => 'bg-red-100 text-red-800',
                                                default => 'bg-rose-100 text-rose-800',
                                            };
                                        @endphp
                                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $typeClasses }}">
                                            {{ $statusLabels[$displayType] ?? ucfirst(str_replace('_', ' ', $displayType)) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-700">
                                        {{ $lead->other_lead_reason ?: $lead->dead_reason ?: 'No reason captured' }}
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-700">
                                        {{ $lead->otherLeadMarkedBy?->name ?? $lead->markedDeadBy?->name ?? 'Unknown' }}
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-700">
                                        {{ optional($lead->other_lead_marked_at ?? $lead->marked_dead_at)?->format('d M Y h:i A') ?? '-' }}
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-700">
                                        {{ $latestAssignment?->assignedTo?->name ?? 'Unassigned' }}
                                        @if($latestAssignment?->assigned_at)
                                            <div class="mt-1 text-xs text-gray-400">{{ $latestAssignment->assigned_at->format('d M Y h:i A') }}</div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="flex min-h-[280px] flex-col items-center justify-center px-6 py-14 text-center">
                    <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-50 text-2xl text-emerald-700">
                        <i class="fas fa-inbox"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900">No other leads found</h3>
                    <p class="mt-2 max-w-2xl text-sm text-gray-500">
                        Abhi current filters ke liye koi `junk`, `not interested` ya `dead` lead available nahi hai. Search ya filters clear karke dubara check karo.
                    </p>
                    @if(request()->filled('search') || request()->filled('type') || request()->filled('assigned_to'))
                        <div class="mt-5">
                            <a href="{{ route('admin.other-leads.index') }}" class="rounded-xl bg-gray-900 px-5 py-3 text-sm font-medium text-white">
                                Reset Filters
                            </a>
                        </div>
                    @endif
                </div>
            @endif
        </div>

        <div>
            {{ $otherLeads->links() }}
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    const selectAllOtherLeads = document.getElementById('selectAllOtherLeads');
    const otherLeadCheckboxes = document.querySelectorAll('.other-lead-checkbox');

    if (selectAllOtherLeads) {
        selectAllOtherLeads.addEventListener('change', (event) => {
            otherLeadCheckboxes.forEach((checkbox) => {
                checkbox.checked = event.target.checked;
            });
        });
    }
</script>
@endpush

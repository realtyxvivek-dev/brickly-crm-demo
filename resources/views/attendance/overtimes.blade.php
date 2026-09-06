@php
    $attendanceUsesSalesManagerLayout = auth()->check()
        && (auth()->user()->isSalesManager() || auth()->user()->isSeniorManager() || auth()->user()->isAssistantSalesManager());
@endphp

@extends($attendanceUsesSalesManagerLayout ? 'sales-manager.layout' : 'layouts.app')

@section('title', 'My Overtime')
@section('page-title', 'My Overtime')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    @include('attendance._flash')
    @include('attendance._nav')

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6">
            <div class="text-sm text-[#6B7280]">Total Overtime Entries</div>
            <div class="mt-2 text-3xl font-bold text-brand-primary">{{ $overtimes->count() }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6">
            <div class="text-sm text-[#6B7280]">Approved Minutes</div>
            <div class="mt-2 text-3xl font-bold text-brand-primary">{{ $overtimes->where('status', 'approved')->sum('overtime_minutes') }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6">
            <div class="text-sm text-[#6B7280]">Pending Entries</div>
            <div class="mt-2 text-3xl font-bold text-brand-primary">{{ $overtimes->where('status', 'pending')->count() }}</div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6 overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead><tr class="text-left text-[#6B7280] border-b"><th class="py-2">Date</th><th>Worked</th><th>Overtime</th><th>Status</th><th>Approved By</th><th>Remarks</th></tr></thead>
            <tbody>
                @forelse($overtimes as $overtime)
                    <tr class="border-b last:border-0">
                        <td class="py-3">{{ $overtime->attendance_date->toDateString() }}</td>
                        <td>{{ $overtime->worked_minutes }} min</td>
                        <td>{{ $overtime->overtime_minutes }} min</td>
                        <td>
                            <span class="px-2 py-1 rounded-full text-xs {{ $overtime->status === 'approved' ? 'bg-emerald-100 text-emerald-700' : ($overtime->status === 'rejected' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') }}">
                                {{ ucfirst($overtime->status) }}
                            </span>
                        </td>
                        <td>{{ $overtime->approver?->name ?: '--' }}</td>
                        <td>{{ $overtime->remarks ?: '--' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-4 text-[#6B7280]">No overtime records yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@extends('layouts.app')

@section('title', 'Salary Revisions')
@section('page-title', 'Salary Revisions')

@section('content')
@php
    $hrRouteBase = auth()->check() && auth()->user()->isHrManager() ? 'hr-manager.settings.hr' : 'admin.hr';
@endphp
<div class="space-y-4">
    @include('attendance._flash')
    @include('admin.hr._nav')

    <div class="bg-white rounded-2xl border border-[#E5DED4] shadow-sm">
        <div class="p-6 border-b border-[#EFE7DC]">
            <div class="text-xs font-black tracking-[0.22em] uppercase text-slate-500">Compensation</div>
            <h1 class="text-4xl font-black tracking-[-0.06em] text-slate-900 mt-2">Salary Revision History</h1>
            <p class="text-sm text-slate-500 mt-2">Track old vs new salary, effective date, reason, and actor for every employee salary change.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[1150px]">
                <thead class="bg-slate-50">
                    <tr class="text-left text-[11px] uppercase tracking-[0.16em] text-slate-500">
                        <th class="px-6 py-4">Employee</th>
                        <th class="px-6 py-4">Effective</th>
                        <th class="px-6 py-4">Base Change</th>
                        <th class="px-6 py-4">Total Change</th>
                        <th class="px-6 py-4">Rule</th>
                        <th class="px-6 py-4">Reason</th>
                        <th class="px-6 py-4">Changed By</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($revisions as $revision)
                        <tr class="border-t border-[#EFE7DC]">
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-900">{{ $revision->employeeProfile?->user?->name ?? 'Unknown' }}</div>
                                <div class="text-sm text-slate-500">{{ $revision->employeeProfile?->employee_code ?? '--' }}</div>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-700">{{ $revision->effective_from?->format('d M Y') }}</td>
                            <td class="px-6 py-4 text-sm text-slate-700">Rs {{ number_format((float) $revision->previous_base_salary, 2) }} → <strong>Rs {{ number_format((float) $revision->new_base_salary, 2) }}</strong></td>
                            <td class="px-6 py-4 text-sm text-slate-700">Rs {{ number_format((float) $revision->previous_total_salary, 2) }} → <strong>Rs {{ number_format((float) $revision->new_total_salary, 2) }}</strong></td>
                            <td class="px-6 py-4 text-sm text-slate-700">{{ $revision->salaryStructure?->name ?? 'Custom/Current' }}</td>
                            <td class="px-6 py-4 text-sm text-slate-700">{{ $revision->reason ?: 'No reason added' }}</td>
                            <td class="px-6 py-4 text-sm text-slate-700">{{ $revision->changedBy?->name ?? 'System' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-6 py-12 text-center text-slate-500 font-semibold">No salary revisions logged yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-6">{{ $revisions->links() }}</div>
    </div>
</div>
@endsection

@extends('layouts.app')

@section('title', 'Exit Cases')
@section('page-title', 'Exit Cases')

@section('content')
@php
    $hrRouteBase = auth()->check() && auth()->user()->isHrManager() ? 'hr-manager.settings.hr' : 'admin.hr';
@endphp
<div class="space-y-4">
    @include('attendance._flash')
    @include('admin.hr._nav')

    <div class="bg-white rounded-2xl border border-[#E5DED4] shadow-sm">
        <div class="p-6 border-b border-[#EFE7DC]">
            <div class="text-xs font-black tracking-[0.22em] uppercase text-slate-500">Lifecycle</div>
            <h1 class="text-4xl font-black tracking-[-0.06em] text-slate-900 mt-2">Exit Workflow Cases</h1>
            <p class="text-sm text-slate-500 mt-2">Track notice, resignation, termination, clearance, and close-out in one queue.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[1150px]">
                <thead class="bg-slate-50">
                    <tr class="text-left text-[11px] uppercase tracking-[0.16em] text-slate-500">
                        <th class="px-6 py-4">Employee</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4">Last Working</th>
                        <th class="px-6 py-4">Clearance</th>
                        <th class="px-6 py-4">Assets</th>
                        <th class="px-6 py-4">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($exitCases as $case)
                        @php
                            $profile = $case->employeeProfile;
                            $issuedAssets = $profile?->assets?->where('status', \App\Models\EmployeeAsset::STATUS_ISSUED)->count() ?? 0;
                        @endphp
                        <tr class="border-t border-[#EFE7DC]">
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-900">{{ $profile?->user?->name ?? 'Unknown' }}</div>
                                <div class="text-sm text-slate-500">{{ $profile?->employee_code ?? '--' }}</div>
                            </td>
                            <td class="px-6 py-4 text-sm font-semibold text-slate-800">{{ str_replace('_', ' ', $case->status) }}</td>
                            <td class="px-6 py-4 text-sm text-slate-700">{{ $case->last_working_date?->format('d M Y') ?? 'Not set' }}</td>
                            <td class="px-6 py-4 text-sm text-slate-700">
                                HR: {{ $case->hr_clearance_completed_at ? 'Done' : 'Pending' }}<br>
                                Finance: {{ $case->finance_clearance_completed_at ? 'Done' : 'Pending' }}<br>
                                Assets: {{ $case->asset_clearance_completed_at ? 'Done' : 'Pending' }}
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-700">{{ $issuedAssets }} issued</td>
                            <td class="px-6 py-4">
                                <a href="{{ route($hrRouteBase . '.employees.show', $profile?->user_id) }}#exit" class="inline-flex items-center justify-center min-h-[36px] px-4 rounded-lg bg-[#205A44] text-white text-xs font-bold">Open Case</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-6 py-12 text-center text-slate-500 font-semibold">No exit workflow cases found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-6">{{ $exitCases->links() }}</div>
    </div>
</div>
@endsection

@extends('layouts.app')

@section('title', 'Incentive Overview')
@section('page-title', 'Incentive Overview')

@section('content')
@include('attendance._flash')
@include('admin.hr._nav')

@php
    $money = fn ($amount) => 'Rs ' . number_format((float) $amount, 0);
    $statusMeta = [
        'pending_sales_head' => ['Pending Sales Head', 'bg-amber-50 text-amber-700 border-amber-200'],
        'pending_crm' => ['Pending CRM', 'bg-amber-50 text-amber-700 border-amber-200'],
        'pending_finance_manager' => ['Pending Finance', 'bg-amber-50 text-amber-700 border-amber-200'],
        'verified' => ['Reimbursed', 'bg-emerald-50 text-emerald-700 border-emerald-200'],
        'rejected' => ['Rejected', 'bg-rose-50 text-rose-700 border-rose-200'],
    ];
@endphp

<div class="mt-4 space-y-4">
    <section class="bg-white border border-slate-200 rounded-lg shadow-sm">
        <div class="p-5 border-b border-slate-200 flex flex-col xl:flex-row xl:items-end xl:justify-between gap-4">
            <div>
                <div class="text-xs font-bold tracking-[0.16em] uppercase text-emerald-700">HR Incentives</div>
                <h1 class="text-2xl font-bold text-slate-900 mt-1">Sales Incentive Overview</h1>
                <p class="text-sm text-slate-500 mt-1">Salesperson totals aur customer-wise booking details ek jagah.</p>
            </div>

            <form method="GET" class="grid grid-cols-1 sm:grid-cols-[150px_180px_minmax(220px,1fr)_auto] gap-2 w-full xl:w-auto">
                <label class="sr-only" for="incentiveMonth">Month</label>
                <input id="incentiveMonth" type="month" name="month" value="{{ $month }}" class="h-11 rounded-md border border-slate-300 px-3 text-sm focus:border-emerald-700 focus:ring-2 focus:ring-emerald-100">

                <label class="sr-only" for="incentiveStatus">Status</label>
                <select id="incentiveStatus" name="status" class="h-11 rounded-md border border-slate-300 px-3 text-sm focus:border-emerald-700 focus:ring-2 focus:ring-emerald-100">
                    <option value="">All Statuses</option>
                    <option value="pending" @selected($status === 'pending')>Pending</option>
                    <option value="verified" @selected($status === 'verified')>Reimbursed</option>
                    <option value="rejected" @selected($status === 'rejected')>Rejected</option>
                </select>

                <label class="sr-only" for="incentiveSearch">Search salesperson</label>
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true"></i>
                    <input id="incentiveSearch" type="search" name="search" value="{{ $search }}" placeholder="Search salesperson" class="h-11 w-full rounded-md border border-slate-300 pl-10 pr-3 text-sm focus:border-emerald-700 focus:ring-2 focus:ring-emerald-100">
                </div>

                <button class="h-11 rounded-md bg-[#07583a] px-5 text-sm font-bold text-white hover:bg-[#06462f] focus:outline-none focus:ring-2 focus:ring-emerald-300">
                    <i class="fas fa-filter mr-2" aria-hidden="true"></i>Apply
                </button>
            </form>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-5 border-b border-slate-200">
            @foreach([
                ['Generated', $summary['earned'], 'fa-gift', 'text-slate-900'],
                ['Reimbursed', $summary['approved'], 'fa-circle-check', 'text-emerald-700'],
                ['Pending', $summary['pending'], 'fa-clock', 'text-amber-700'],
                ['Rejected', $summary['rejected'], 'fa-circle-xmark', 'text-rose-700'],
                ['Bookings', $summary['bookings'], 'fa-building-circle-check', 'text-blue-700'],
            ] as [$label, $value, $icon, $color])
                <div class="p-4 border-r border-b lg:border-b-0 border-slate-200 last:border-r-0">
                    <div class="flex items-center gap-2 text-xs font-bold uppercase text-slate-500">
                        <i class="fas {{ $icon }} {{ $color }}" aria-hidden="true"></i>{{ $label }}
                    </div>
                    <div class="mt-2 text-xl font-bold {{ $color }}">{{ $label === 'Bookings' ? number_format($value) : $money($value) }}</div>
                </div>
            @endforeach
        </div>

        <div class="hidden lg:grid grid-cols-[42px_minmax(230px,1.5fr)_150px_repeat(4,minmax(130px,1fr))] gap-3 px-5 py-3 bg-slate-50 text-[11px] font-bold uppercase tracking-wide text-slate-500 border-b border-slate-200">
            <span></span><span>Sales Person</span><span>Bookings</span><span>Generated</span><span>Reimbursed</span><span>Pending</span><span>Rejected</span>
        </div>

        <div class="divide-y divide-slate-200">
            @forelse($rows as $row)
                <details class="group">
                    <summary class="list-none cursor-pointer px-4 sm:px-5 py-4 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-emerald-500">
                        <div class="grid grid-cols-[42px_1fr] lg:grid-cols-[42px_minmax(230px,1.5fr)_150px_repeat(4,minmax(130px,1fr))] gap-3 items-center">
                            <span class="w-9 h-9 rounded-md border border-slate-200 bg-white flex items-center justify-center text-emerald-800 shadow-sm" aria-hidden="true">
                                <i class="fas fa-chevron-right text-xs transition-transform group-open:rotate-90"></i>
                            </span>
                            <div class="min-w-0">
                                <div class="font-bold text-slate-900 truncate">{{ $row['user']->name }}</div>
                                <div class="text-xs text-slate-500 truncate">{{ $row['user']->role?->name ?? 'Sales' }} @if($row['user']->employeeProfile?->employee_code) · {{ $row['user']->employeeProfile->employee_code }} @endif</div>
                            </div>

                            <div class="col-span-2 lg:col-span-1 grid grid-cols-2 sm:grid-cols-5 lg:block gap-2 mt-2 lg:mt-0">
                                <div class="lg:hidden text-xs text-slate-500">Bookings</div>
                                <div class="font-bold text-slate-900">{{ $row['booking_count'] }}</div>
                            </div>
                            @foreach([
                                ['Generated', $row['earned'], 'text-slate-900'],
                                ['Reimbursed', $row['approved'], 'text-emerald-700'],
                                ['Pending', $row['pending'], 'text-amber-700'],
                                ['Rejected', $row['rejected'], 'text-rose-700'],
                            ] as [$label, $amount, $color])
                                <div class="hidden lg:block">
                                    <div class="font-bold {{ $color }}">{{ $money($amount) }}</div>
                                </div>
                            @endforeach
                            <div class="col-span-2 grid grid-cols-2 sm:grid-cols-4 gap-2 lg:hidden mt-1">
                                @foreach([
                                    ['Generated', $row['earned'], 'text-slate-900'],
                                    ['Reimbursed', $row['approved'], 'text-emerald-700'],
                                    ['Pending', $row['pending'], 'text-amber-700'],
                                    ['Rejected', $row['rejected'], 'text-rose-700'],
                                ] as [$label, $amount, $color])
                                    <div class="rounded-md bg-slate-50 border border-slate-200 p-2">
                                        <div class="text-[10px] uppercase text-slate-500">{{ $label }}</div>
                                        <div class="text-sm font-bold {{ $color }}">{{ $money($amount) }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </summary>

                    <div class="px-4 sm:px-5 pb-5 bg-slate-50">
                        <div class="ml-0 lg:ml-[54px] border border-slate-200 rounded-lg bg-white overflow-hidden">
                            @if($row['incentives']->isEmpty())
                                <div class="p-8 text-center text-sm font-semibold text-slate-500">Is month koi incentive record nahi hai.</div>
                            @else
                                <div class="overflow-x-auto">
                                    <table class="w-full min-w-[980px]">
                                        <thead class="bg-slate-50 text-left text-[11px] uppercase tracking-wide text-slate-500">
                                            <tr>
                                                <th class="px-4 py-3">Customer</th>
                                                <th class="px-4 py-3">Project</th>
                                                <th class="px-4 py-3">Booking Date</th>
                                                <th class="px-4 py-3">Type</th>
                                                <th class="px-4 py-3">Amount</th>
                                                <th class="px-4 py-3">Status</th>
                                                <th class="px-4 py-3">Updated</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100">
                                            @foreach($row['incentives'] as $incentive)
                                                @php
                                                    $visit = $incentive->siteVisit;
                                                    $customer = $visit?->customer_name ?: ($visit?->lead?->name ?: 'Customer not filled');
                                                    $phone = $visit?->phone ?: $visit?->lead?->phone;
                                                    $project = $visit?->project ?: ($visit?->property_name ?: 'Project not filled');
                                                    $bookingDate = data_get($visit?->unit_details ?? [], 'booking_date') ?: optional($visit?->actual_closer_date)->format('Y-m-d');
                                                    [$statusLabel, $statusClass] = $statusMeta[$incentive->status] ?? [ucwords(str_replace('_', ' ', $incentive->status)), 'bg-slate-50 text-slate-700 border-slate-200'];
                                                @endphp
                                                <tr class="text-sm">
                                                    <td class="px-4 py-3">
                                                        <div class="font-bold text-slate-900">{{ $customer }}</div>
                                                        <div class="text-xs text-slate-500">{{ $phone ?: 'Phone not filled' }}</div>
                                                    </td>
                                                    <td class="px-4 py-3 font-semibold text-slate-700">{{ $project }}</td>
                                                    <td class="px-4 py-3 text-slate-600">{{ $bookingDate ? \Carbon\Carbon::parse($bookingDate)->format('d M Y') : '--' }}</td>
                                                    <td class="px-4 py-3 text-slate-600">{{ $incentive->type === 'closer' ? 'Booking / Closer' : 'Site Visit' }}</td>
                                                    <td class="px-4 py-3 font-bold text-slate-900">{{ $money($incentive->amount) }}</td>
                                                    <td class="px-4 py-3">
                                                        <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-bold {{ $statusClass }}">{{ $statusLabel }}</span>
                                                        @if($incentive->status === 'rejected' && $incentive->rejection_reason)
                                                            <div class="text-xs text-rose-600 mt-1 max-w-[220px]">{{ $incentive->rejection_reason }}</div>
                                                        @endif
                                                    </td>
                                                    <td class="px-4 py-3 text-xs text-slate-500">
                                                        {{ optional($incentive->finance_manager_verified_at ?? $incentive->updated_at)->format('d M Y, h:i A') }}
                                                        @if($incentive->financeManagerVerifiedBy)
                                                            <div>by {{ $incentive->financeManagerVerifiedBy->name }}</div>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>
                </details>
            @empty
                <div class="px-6 py-12 text-center text-slate-500 font-semibold">No sales person found for selected filters.</div>
            @endforelse
        </div>
    </section>

    <p class="px-1 text-xs text-slate-500"><strong>Reimbursed</strong> means Finance Manager approved in the current CRM workflow.</p>
</div>
@endsection

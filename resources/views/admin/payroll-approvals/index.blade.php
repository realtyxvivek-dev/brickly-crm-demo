@extends('layouts.app')

@section('title', 'Payroll Approval')
@section('page-title', 'Payroll Approval')

@section('content')
@php
    $statusOptions = [
        'all' => 'All',
        'hr_locked' => 'Ready for Admin',
        'payment_pending' => 'Approved',
        'admin_rejected' => 'Rejected',
        'paid' => 'Paid',
    ];
    $chip = fn ($status) => [
        'hr_locked' => 'bg-blue-50 text-blue-700 border-blue-200',
        'payment_pending' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'admin_rejected' => 'bg-red-50 text-red-700 border-red-200',
        'paid' => 'bg-green-50 text-green-700 border-green-200',
    ][$status] ?? 'bg-slate-50 text-slate-700 border-slate-200';
@endphp

<div class="space-y-5">
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200 font-semibold">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="p-4 rounded-xl bg-red-50 text-red-700 border border-red-200 font-semibold">{{ $errors->first() }}</div>
    @endif

    <section class="bg-white border border-[#E5DED4] rounded-2xl p-5 shadow-sm">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-[#0b2e20]">Payroll Approval Queue</h1>
                <p class="text-sm text-slate-500 mt-1">Approve selected payslips. Rejected rows return to HR correction.</p>
            </div>
            <form method="GET" class="flex flex-wrap items-end gap-3">
                <input type="number" name="year" value="{{ $year }}" min="2000" max="2100" class="border rounded-xl px-3 py-2">
                <input type="number" name="month" value="{{ $month }}" min="1" max="12" class="border rounded-xl px-3 py-2">
                <select name="status" class="border rounded-xl px-3 py-2">
                    @foreach($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button class="px-4 py-2 rounded-xl bg-[#205A44] text-white font-bold">Refresh</button>
            </form>
        </div>
    </section>

    @foreach($freezes as $freeze)
        @php
            $freezePayslips = $payslips->where('payroll_freeze_id', $freeze->id)->values();
        @endphp
        <section class="bg-white border border-[#E5DED4] rounded-2xl shadow-sm overflow-hidden">
            <div class="p-5 border-b flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-bold">{{ sprintf('%02d/%04d', $freeze->month, $freeze->year) }} Payroll</h2>
                    <p class="text-sm text-slate-500">Batch: {{ ucwords(str_replace('_', ' ', $freeze->status)) }} · {{ $freezePayslips->count() }} visible payslip(s)</p>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.payroll-approvals.approve', $freeze) }}">
                @csrf
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1050px] text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                            <tr>
                                <th class="p-3 text-left"><input type="checkbox" onclick="this.closest('table').querySelectorAll('.approval-check').forEach(cb => cb.checked = this.checked)"></th>
                                <th class="p-3 text-left">Employee</th>
                                <th class="p-3 text-left">Status</th>
                                <th class="p-3 text-left">Gross</th>
                                <th class="p-3 text-left">Deduction</th>
                                <th class="p-3 text-left">Net</th>
                                <th class="p-3 text-left">Correction / Remark</th>
                                <th class="p-3 text-left">Latest Change</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($freezePayslips as $payslip)
                                <tr class="border-t">
                                    <td class="p-3"><input class="approval-check" type="checkbox" name="payslip_ids[]" value="{{ $payslip->id }}" @disabled($payslip->status !== 'hr_locked')></td>
                                    <td class="p-3 font-bold">{{ $payslip->user?->name }}</td>
                                    <td class="p-3"><span class="px-3 py-1 rounded-full border text-xs font-bold {{ $chip($payslip->status) }}">{{ app(\App\Services\PayrollWorkflowService::class)->statusLabel($payslip->status) }}</span></td>
                                    <td class="p-3">Rs {{ number_format((float) $payslip->gross_pay, 2) }}</td>
                                    <td class="p-3">Rs {{ number_format((float) $payslip->total_deductions, 2) }}</td>
                                    <td class="p-3 font-bold">Rs {{ number_format((float) $payslip->net_pay, 2) }}</td>
                                    <td class="p-3 max-w-xs">{{ $payslip->employee_correction_note ?: ($payslip->admin_remark ?: '-') }}</td>
                                    <td class="p-3">{{ optional($payslip->versions->sortByDesc('version_no')->first())->remark ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="p-6 text-center text-slate-500">No payslips for this filter.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="p-5 border-t grid grid-cols-1 md:grid-cols-[1fr_auto_auto] gap-3">
                    <input name="admin_remark" class="border rounded-xl px-3 py-2" placeholder="Reject remark, required only for reject">
                    <button class="px-4 py-2 rounded-xl bg-emerald-600 text-white font-bold">Approve Selected</button>
                    <button formaction="{{ route('admin.payroll-approvals.reject', $freeze) }}" class="px-4 py-2 rounded-xl bg-red-600 text-white font-bold">Reject Selected</button>
                </div>
            </form>
        </section>
    @endforeach

    @if($freezes->isEmpty())
        <div class="bg-white border rounded-2xl p-8 text-center text-slate-500">No payroll is waiting for Admin review.</div>
    @endif
</div>
@endsection

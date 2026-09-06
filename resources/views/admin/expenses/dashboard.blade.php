@extends('layouts.app')

@section('title', 'Expense Dashboard')
@section('page-title', 'Expense Dashboard')

@section('content')
<div class="w-full space-y-6">
    @include('attendance._flash')
    @include('admin.expenses._nav')
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-5"><div class="text-xs uppercase tracking-[0.2em] text-[#6B7280]">Month Total</div><div class="text-3xl font-bold text-brand-primary mt-2">Rs {{ number_format($summary['total_amount'], 2) }}</div></div>
        <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-5"><div class="text-xs uppercase tracking-[0.2em] text-[#6B7280]">Entries</div><div class="text-3xl font-bold text-brand-primary mt-2">{{ $summary['entry_count'] }}</div></div>
        <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-5"><div class="text-xs uppercase tracking-[0.2em] text-[#6B7280]">Average Ticket</div><div class="text-3xl font-bold text-brand-primary mt-2">Rs {{ number_format($summary['average_amount'], 2) }}</div></div>
        <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-5"><div class="text-xs uppercase tracking-[0.2em] text-[#6B7280]">Pending Queue</div><div class="text-3xl font-bold text-brand-primary mt-2">{{ $pendingCount }}</div><div class="text-sm text-[#6B7280] mt-2">{{ sprintf('%02d', $month) }}/{{ $year }}</div></div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6">
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('admin.expenses.queue') }}" class="px-5 py-2 border border-[#E5DED4] rounded-lg text-brand-primary">Open Approval Queue</a>
            <a href="{{ route('admin.expenses.monthly-report', ['year' => $year, 'month' => $month]) }}" class="px-5 py-2 bg-[#205A44] text-white rounded-lg">Open Monthly Report</a>
            <a href="{{ route('admin.expenses.entries.index') }}" class="px-5 py-2 border border-[#E5DED4] rounded-lg text-brand-primary">Open Ledger</a>
        </div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6">
            <h2 class="text-lg font-semibold text-brand-primary mb-4">Status Summary</h2>
            <div class="space-y-3 text-sm">
                @foreach($statusTotals as $row)
                    <div class="flex items-center justify-between gap-3"><span>{{ $row['label'] }}</span><strong>{{ $row['entry_count'] }} / Rs {{ number_format($row['total_amount'], 2) }}</strong></div>
                @endforeach
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6">
            <h2 class="text-lg font-semibold text-brand-primary mb-4">Recent Entries</h2>
            <div class="space-y-3 text-sm">
                @forelse($latestEntries as $entry)
                    <div class="flex items-center justify-between gap-3"><span>{{ $entry->company?->name }} · {{ $entry->category?->name }}</span><strong>Rs {{ number_format((float) $entry->amount, 2) }}</strong></div>
                @empty
                    <div class="text-[#6B7280]">No recent entries.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection

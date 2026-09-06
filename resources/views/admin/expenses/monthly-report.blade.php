@extends('layouts.app')

@section('title', 'Expense Monthly Report')
@section('page-title', 'Expense Monthly Report')

@section('content')
<div class="w-full space-y-6">
    @include('attendance._flash')
    @include('admin.expenses._nav')

    <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div>
                <h2 class="text-lg font-semibold text-brand-primary">Monthly Report</h2>
                <p class="text-sm text-[#6B7280]">Month-wise summarized view for print and export.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('admin.expenses.summary.print', request()->query()) }}" class="px-5 py-2 border border-[#E5DED4] rounded-lg text-brand-primary">Print Summary</a>
                <a href="{{ route('admin.expenses.summary.export', request()->query()) }}" class="px-5 py-2 bg-[#205A44] text-white rounded-lg">Export Summary</a>
            </div>
        </div>
        <form method="GET" action="{{ route('admin.expenses.monthly-report') }}" class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <input type="number" name="year" min="2000" max="2100" value="{{ $year }}" class="px-4 py-2 border border-[#E5DED4] rounded-lg">
            <input type="number" name="month" min="1" max="12" value="{{ $month }}" class="px-4 py-2 border border-[#E5DED4] rounded-lg">
            <select name="company_id" class="px-4 py-2 border border-[#E5DED4] rounded-lg"><option value="">All companies</option>@foreach($companies as $company)<option value="{{ $company->id }}" @selected((string)$filters['company_id']===(string)$company->id)>{{ $company->name }}</option>@endforeach</select>
            <select name="expense_category_id" class="px-4 py-2 border border-[#E5DED4] rounded-lg"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string)$filters['expense_category_id']===(string)$category->id)>{{ $category->name }}</option>@endforeach</select>
            <select name="status" class="px-4 py-2 border border-[#E5DED4] rounded-lg"><option value="">All status</option>@foreach($statusTotals as $status => $row)<option value="{{ $status }}" @selected((string)$filters['status']===(string)$status)>{{ $row['label'] }}</option>@endforeach</select>
            <button type="submit" class="px-5 py-2 bg-[#205A44] text-white rounded-lg w-fit">Build Report</button>
        </form>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-5"><div class="text-xs uppercase tracking-[0.2em] text-[#6B7280]">Total Amount</div><div class="text-3xl font-bold text-brand-primary mt-2">Rs {{ number_format($summary['total_amount'], 2) }}</div></div>
        <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-5"><div class="text-xs uppercase tracking-[0.2em] text-[#6B7280]">Entries</div><div class="text-3xl font-bold text-brand-primary mt-2">{{ $summary['entry_count'] }}</div></div>
        <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-5"><div class="text-xs uppercase tracking-[0.2em] text-[#6B7280]">Average</div><div class="text-3xl font-bold text-brand-primary mt-2">Rs {{ number_format($summary['average_amount'], 2) }}</div></div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6 overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead><tr class="text-left text-[#6B7280] border-b"><th class="py-2">Company</th><th>Category</th><th>Entries</th><th>Total</th></tr></thead>
            <tbody>
            @forelse($companyCategoryTotals as $row)
                <tr class="border-b last:border-0"><td class="py-3">{{ $row->company?->name }}</td><td>{{ $row->category?->name }}</td><td>{{ (int) $row->entry_count }}</td><td>Rs {{ number_format((float) $row->total_amount, 2) }}</td></tr>
            @empty
                <tr><td colspan="4" class="py-4 text-[#6B7280]">No grouped totals.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

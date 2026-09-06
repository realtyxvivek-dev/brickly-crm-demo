@extends('finance-manager.layout')

@section('title', 'Expense Print Summary')
@section('page_title', 'Expense Print Summary')
@section('page_subtitle', 'Printer-friendly monthly expense summary.')

@push('styles')
    @include('finance-manager.expenses._styles')
@endpush

@section('content')
<div class="expense-print-shell">
    <div class="no-print" style="display:flex;justify-content:flex-end;gap:12px;margin-bottom:20px;">
        <a href="{{ route('finance-manager.expenses.monthly-report', request()->query()) }}" class="expense-btn soft">Back</a>
        <button onclick="window.print()" class="expense-btn primary">Print</button>
    </div>
    <div class="expense-print-head">
        <div>
            <h2 style="margin:0;color:#0b2e20;">Expense Monthly Summary</h2>
            <p style="margin:8px 0 0;color:#6b7280;">Month {{ sprintf('%02d', $month) }}/{{ $year }}</p>
        </div>
        <div style="text-align:right;color:#6b7280;">Generated {{ now()->format('d M Y h:i A') }}</div>
    </div>
    <div class="expense-print-grid">
        <div class="expense-print-card"><div class="expense-print-label">Total Amount</div><div class="expense-print-value">Rs {{ number_format($summary['total_amount'], 2) }}</div></div>
        <div class="expense-print-card"><div class="expense-print-label">Entries</div><div class="expense-print-value">{{ $summary['entry_count'] }}</div></div>
        <div class="expense-print-card"><div class="expense-print-label">Average Ticket</div><div class="expense-print-value">Rs {{ number_format($summary['average_amount'], 2) }}</div></div>
        <div class="expense-print-card"><div class="expense-print-label">Printed Month</div><div class="expense-print-value">{{ sprintf('%02d', $month) }}/{{ $year }}</div></div>
    </div>
    <div class="expense-dual-grid">
        <div class="expense-card" style="box-shadow:none;">
            <div class="expense-header"><div><h2>Company Totals</h2></div></div>
            <div class="expense-mini-list">
                @foreach($companyTotals as $row)
                    <div class="expense-mini-item"><div><strong>{{ $row->company?->name }}</strong></div><div class="expense-mini-value">Rs {{ number_format((float) $row->total_amount, 2) }}</div></div>
                @endforeach
            </div>
        </div>
        <div class="expense-card" style="box-shadow:none;">
            <div class="expense-header"><div><h2>Category Totals</h2></div></div>
            <div class="expense-mini-list">
                @foreach($categoryTotals as $row)
                    <div class="expense-mini-item"><div><strong>{{ $row->category?->name }}</strong></div><div class="expense-mini-value">Rs {{ number_format((float) $row->total_amount, 2) }}</div></div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection

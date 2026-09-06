@extends('finance-manager.layout')

@section('title', 'Expense Dashboard')
@section('page_title', 'Expense Dashboard')
@section('page_subtitle', 'Watch monthly expense exposure, reporting readiness, and latest spend movement from one finance surface.')

@push('styles')
    @include('finance-manager.expenses._styles')
@endpush

@section('content')
<div class="expense-stack">
    @include('finance-manager.expenses._nav')

    <section class="expense-hero">
        <div>
            <h2>Company-wise spend visibility with category depth and audit-ready movement.</h2>
            <p>Track current month expense totals, draft vs approved load, top category pressure, and recent entries without switching away from finance workspace.</p>
            <div class="expense-hero-actions">
                <a href="{{ route('finance-manager.expenses.monthly-report', ['year' => $year, 'month' => $month]) }}" class="expense-hero-action primary"><i class="fas fa-chart-column"></i> Open Monthly Report</a>
                <a href="{{ route('finance-manager.expenses.entries.index') }}" class="expense-hero-action secondary"><i class="fas fa-receipt"></i> Open Expense Ledger</a>
            </div>
        </div>
        <div class="expense-focus-grid">
            <div class="expense-focus-card">
                <div class="expense-focus-label">Month Total</div>
                <div class="expense-focus-value">Rs {{ number_format($summary['total_amount'], 2) }}</div>
                <div class="expense-focus-note">{{ date('F', mktime(0,0,0,$month,1)) }} {{ $year }}</div>
            </div>
            <div class="expense-focus-card">
                <div class="expense-focus-label">Entries</div>
                <div class="expense-focus-value">{{ $summary['entry_count'] }}</div>
                <div class="expense-focus-note">Transactions booked this month</div>
            </div>
            <div class="expense-focus-card">
                <div class="expense-focus-label">Average Ticket</div>
                <div class="expense-focus-value">Rs {{ number_format($summary['average_amount'], 2) }}</div>
                <div class="expense-focus-note">Average expense size</div>
            </div>
            <div class="expense-focus-card">
                <div class="expense-focus-label">Pending Queue</div>
                <div class="expense-focus-value">{{ $pendingCount }}</div>
                <div class="expense-focus-note">Draft expenses waiting for approval</div>
            </div>
        </div>
    </section>

    <section class="expense-kpis">
        @foreach($statusTotals as $status => $row)
            <div class="expense-kpi">
                <div class="expense-kpi-label">{{ $row['label'] }}</div>
                <div class="expense-kpi-value">{{ $row['entry_count'] }}</div>
                <div class="expense-kpi-note">Rs {{ number_format($row['total_amount'], 2) }}</div>
            </div>
        @endforeach
    </section>

    <section class="expense-dual-grid">
        <div class="expense-card">
            <div class="expense-header">
                <div>
                    <h2>Top Subcategories</h2>
                    <p>Which detailed heads are driving the most spend this month.</p>
                </div>
            </div>
            <div class="expense-mini-list">
                @forelse($subcategoryTotals as $row)
                    <div class="expense-mini-item">
                        <div><strong>{{ $row->subcategory?->name }}</strong><span>Expense load</span></div>
                        <div class="expense-mini-value">Rs {{ number_format((float) $row->total_amount, 2) }}</div>
                    </div>
                @empty
                    <div class="expense-mini-item"><div><strong>No subcategory totals</strong><span>No monthly entries yet.</span></div></div>
                @endforelse
            </div>
        </div>
        <div class="expense-card">
            <div class="expense-header">
                <div>
                    <h2>Recent Entries</h2>
                    <p>Latest spend movement for quick finance review.</p>
                </div>
            </div>
            <div class="expense-mini-list">
                @forelse($latestEntries as $entry)
                    <div class="expense-mini-item">
                        <div>
                            <strong>{{ $entry->company?->name }} · {{ $entry->category?->name }}</strong>
                            <span>{{ $entry->paid_to ?: 'No payee' }} · {{ optional($entry->expense_date)->format('d M Y') }}</span>
                        </div>
                        <div class="expense-mini-value">Rs {{ number_format((float) $entry->amount, 2) }}</div>
                    </div>
                @empty
                    <div class="expense-mini-item"><div><strong>No recent entries</strong><span>Monthly activity not found.</span></div></div>
                @endforelse
            </div>
        </div>
    </section>
</div>
@endsection

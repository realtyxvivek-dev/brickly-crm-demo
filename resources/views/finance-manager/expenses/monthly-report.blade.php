@extends('finance-manager.layout')

@section('title', 'Expense Monthly Report')
@section('page_title', 'Expense Monthly Report')
@section('page_subtitle', 'Monthly rolled-up summary by company, category, subcategory, and status.')

@push('styles')
    @include('finance-manager.expenses._styles')
@endpush

@section('content')
<div class="expense-stack">
    @include('finance-manager.expenses._nav')

    <section class="expense-card">
        <div class="expense-header">
            <div>
                <h2>Monthly Report Filters</h2>
                <p>Month, company, category, aur status ke basis par summarized report build karo.</p>
            </div>
            <div style="display:flex;gap:12px;flex-wrap:wrap;">
                <a href="{{ route('finance-manager.expenses.summary.print', request()->query()) }}" class="expense-btn soft"><i class="fas fa-print"></i> Print Summary</a>
                <a href="{{ route('finance-manager.expenses.summary.export', request()->query()) }}" class="expense-btn primary"><i class="fas fa-file-export"></i> Export Summary</a>
            </div>
        </div>
        <form method="GET" action="{{ route('finance-manager.expenses.monthly-report') }}" class="expense-form-grid">
            <div class="expense-field"><label>Year</label><input class="expense-input" type="number" name="year" min="2000" max="2100" value="{{ $year }}"></div>
            <div class="expense-field"><label>Month</label><input class="expense-input" type="number" name="month" min="1" max="12" value="{{ $month }}"></div>
            <div class="expense-field">
                <label>Company</label>
                <select name="company_id" class="expense-input">
                    <option value="">All companies</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}" @selected((string) $filters['company_id'] === (string) $company->id)>{{ $company->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="expense-field">
                <label>Category</label>
                <select name="expense_category_id" class="expense-input">
                    <option value="">All categories</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) $filters['expense_category_id'] === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="expense-field">
                <label>Status</label>
                <select name="status" class="expense-input">
                    <option value="">All status</option>
                    @foreach($statusTotals as $status => $row)
                        <option value="{{ $status }}" @selected((string) $filters['status'] === (string) $status)>{{ $row['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="expense-btn primary"><i class="fas fa-chart-line"></i> Build Report</button>
        </form>
    </section>

    <section class="expense-kpis">
        <div class="expense-kpi">
            <div class="expense-kpi-label">Total Amount</div>
            <div class="expense-kpi-value">Rs {{ number_format($summary['total_amount'], 2) }}</div>
            <div class="expense-kpi-note">{{ $summary['entry_count'] }} entries in current report</div>
        </div>
        <div class="expense-kpi">
            <div class="expense-kpi-label">Average Ticket</div>
            <div class="expense-kpi-value">Rs {{ number_format($summary['average_amount'], 2) }}</div>
            <div class="expense-kpi-note">Average amount in filtered month</div>
        </div>
        <div class="expense-kpi">
            <div class="expense-kpi-label">Top Company</div>
            @php $topCompany = $summary['company_totals']->first(); @endphp
            <div class="expense-kpi-value">{{ $topCompany?->company?->name ?? 'N/A' }}</div>
            <div class="expense-kpi-note">{{ $topCompany ? 'Rs ' . number_format((float) $topCompany->total_amount, 2) : 'No totals yet' }}</div>
        </div>
    </section>

    <section class="expense-card">
        <div class="expense-header">
            <div><h2>Company x Category Summary</h2><p>Monthly grouped totals by company and category.</p></div>
        </div>
        <div class="expense-table-wrap">
            <table class="expense-table">
                <thead><tr><th>Company</th><th>Category</th><th>Entries</th><th>Total Amount</th></tr></thead>
                <tbody>
                @forelse($companyCategoryTotals as $row)
                    <tr>
                        <td>{{ $row->company?->name }}</td>
                        <td>{{ $row->category?->name }}</td>
                        <td>{{ (int) $row->entry_count }}</td>
                        <td>Rs {{ number_format((float) $row->total_amount, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4">No grouped totals found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="expense-card">
        <div class="expense-header">
            <div><h2>Subcategory Summary</h2><p>Detailed head-level rollup for the selected month.</p></div>
        </div>
        <div class="expense-table-wrap">
            <table class="expense-table">
                <thead><tr><th>Category</th><th>Subcategory</th><th>Entries</th><th>Total Amount</th></tr></thead>
                <tbody>
                @forelse($subcategoryTotals as $row)
                    <tr>
                        <td>{{ $row->subcategory?->category?->name }}</td>
                        <td>{{ $row->subcategory?->name }}</td>
                        <td>{{ (int) $row->entry_count }}</td>
                        <td>Rs {{ number_format((float) $row->total_amount, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4">No subcategory totals found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection

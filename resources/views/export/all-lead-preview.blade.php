@extends('layouts.app')

@section('title', 'All Lead Export Preview - ' . brand_name())
@section('page-title', 'All Lead Export Preview')

@push('styles')
<style>
    .report-shell { display:grid; gap:20px; }
    .report-toolbar, .report-table-card { background:#fff; border:1px solid #d9e6de; border-radius:20px; box-shadow:0 16px 36px rgba(6,58,28,.06); }
    .report-toolbar { padding:20px; display:grid; gap:18px; }
    .report-toolbar-top { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; }
    .report-title { font-size:28px; font-weight:800; color:#0f2d22; margin:0; }
    .report-copy { color:#607267; font-size:14px; margin-top:6px; }
    .report-actions { display:flex; gap:10px; flex-wrap:wrap; }
    .report-btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; padding:12px 16px; border-radius:14px; text-decoration:none; border:1px solid #d6e3db; font-weight:700; cursor:pointer; }
    .report-btn.primary { background:linear-gradient(135deg,#0f5132 0%,#1a6b47 100%); color:#fff; border-color:#0f5132; }
    .report-btn.secondary { background:#f7faf8; color:#173128; }
    .report-filters { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:14px; }
    .report-field { display:flex; flex-direction:column; gap:8px; }
    .report-field label { font-size:13px; font-weight:700; color:#173128; }
    .report-field input, .report-field select { width:100%; border:1px solid #d4e0d8; border-radius:14px; padding:12px 14px; font-size:14px; }
    .report-meta { display:flex; gap:12px; flex-wrap:wrap; }
    .report-chip { display:inline-flex; align-items:center; gap:8px; background:#f5fbf7; color:#1e4d37; border:1px solid #dbe8e0; border-radius:999px; padding:8px 12px; font-size:13px; font-weight:700; }
    .report-table-card { overflow:hidden; }
    .report-table-wrap { overflow:auto; max-height:70vh; }
    .report-table { width:100%; border-collapse:separate; border-spacing:0; min-width:1180px; }
    .report-table th { position:sticky; top:0; z-index:2; background:#103728; color:#fff; font-size:12px; text-transform:uppercase; letter-spacing:.06em; padding:14px 12px; text-align:left; white-space:nowrap; }
    .report-table td { padding:12px; border-bottom:1px solid #edf2ef; vertical-align:top; color:#173128; font-size:14px; }
    .report-table tbody tr:nth-child(even) td { background:#fbfdfc; }
    .report-table td:nth-child(8) { min-width:280px; white-space:normal; }
    .report-empty { padding:28px; color:#5f6f67; text-align:center; }
    @media (max-width: 1024px) { .report-filters { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    @media (max-width: 767px) { .report-toolbar-top { flex-direction:column; } .report-filters { grid-template-columns:1fr; } }
</style>
@endpush

@section('content')
<div class="report-shell">
    <section class="report-toolbar">
        <div class="report-toolbar-top">
            <div>
                <h1 class="report-title">All Lead Export</h1>
                <div class="report-copy">Excel-style preview before export. Preview shows up to {{ $previewCount }} rows from {{ $totalRecords }} matching leads.</div>
            </div>
            <div class="report-actions">
                <form method="POST" action="{{ route('export.leads') }}">
                    @csrf
                    <input type="hidden" name="format" value="csv">
                    <input type="hidden" name="fields[]" value="serial_no">
                    <input type="hidden" name="fields[]" value="created_at">
                    <input type="hidden" name="fields[]" value="name">
                    <input type="hidden" name="fields[]" value="phone">
                        <input type="hidden" name="fields[]" value="source">
                        <input type="hidden" name="fields[]" value="status">
                        <input type="hidden" name="fields[]" value="crm_advisor">
                        <input type="hidden" name="fields[]" value="last_assigned_to">
                        <input type="hidden" name="fields[]" value="latest_remark">
                        <input type="hidden" name="fields[]" value="next_followup_date">
                    <input type="hidden" name="date_range" value="{{ $filters['date_range'] }}">
                    <input type="hidden" name="user_id" value="{{ $filters['user_id'] }}">
                    <input type="hidden" name="search" value="{{ $filters['search'] }}">
                    <button type="submit" class="report-btn primary"><i class="fas fa-file-csv"></i> Export CSV</button>
                </form>
                <form method="POST" action="{{ route('export.leads') }}">
                    @csrf
                    <input type="hidden" name="format" value="pdf">
                    <input type="hidden" name="fields[]" value="serial_no">
                    <input type="hidden" name="fields[]" value="created_at">
                    <input type="hidden" name="fields[]" value="name">
                    <input type="hidden" name="fields[]" value="phone">
                        <input type="hidden" name="fields[]" value="source">
                        <input type="hidden" name="fields[]" value="status">
                        <input type="hidden" name="fields[]" value="crm_advisor">
                        <input type="hidden" name="fields[]" value="last_assigned_to">
                        <input type="hidden" name="fields[]" value="latest_remark">
                        <input type="hidden" name="fields[]" value="next_followup_date">
                    <input type="hidden" name="date_range" value="{{ $filters['date_range'] }}">
                    <input type="hidden" name="user_id" value="{{ $filters['user_id'] }}">
                    <input type="hidden" name="search" value="{{ $filters['search'] }}">
                    <button type="submit" class="report-btn secondary"><i class="fas fa-file-pdf"></i> Export PDF</button>
                </form>
                <a href="{{ route('export.index') }}" class="report-btn secondary"><i class="fas fa-arrow-left"></i> Back</a>
            </div>
        </div>

        <form method="GET" action="{{ route('export.all-lead-preview') }}" class="report-filters">
            <div class="report-field">
                <label for="date_range">Date Range</label>
                <select name="date_range" id="date_range">
                    @foreach(['all_time' => 'All Time', 'today' => 'Today', 'this_week' => 'This Week', 'this_month' => 'This Month', 'this_year' => 'This Year'] as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['date_range'] ?? 'this_month') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="report-field">
                <label for="user_id">Owner</label>
                <select name="user_id" id="user_id">
                    <option value="">All Accessible Users</option>
                    @foreach($users as $userItem)
                        <option value="{{ $userItem->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $userItem->id)>{{ $userItem->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="report-field">
                <label for="search">Search</label>
                <input type="text" id="search" name="search" value="{{ $filters['search'] }}" placeholder="Name, phone, or email">
            </div>
            <div class="report-field" style="justify-content:end;">
                <label>&nbsp;</label>
                <button type="submit" class="report-btn primary">Apply Filters</button>
            </div>
        </form>

        <div class="report-meta">
            <span class="report-chip">Columns: {{ count($headers) }}</span>
            <span class="report-chip">Visible Rows: {{ $previewCount }}</span>
            <span class="report-chip">Total Matches: {{ $totalRecords }}</span>
        </div>
    </section>

    <section class="report-table-card">
        <div class="report-table-wrap">
            @if($rows->isEmpty())
                <div class="report-empty">No leads found for the selected filters.</div>
            @else
                <table class="report-table">
                    <thead>
                        <tr>
                            @foreach($headers as $header)
                                <th>{{ $header }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                            <tr>
                                @foreach($fields as $field)
                                    <td>{{ $row[$field] ?? '' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </section>
</div>
@endsection

@extends('layouts.app')

@section('title', 'Reports Workspace - ' . brand_name())
@section('page-title', 'Reports Workspace')

@php
    $selectedDefinition = $reportDefinitions[$selectedReportKey] ?? reset($reportDefinitions);
    $selectedFilters = $selectedDefinition['filters'] ?? [];
    $activeStatuses = $filters['status'] ?? [];
    $favoriteExcludeReports = ['all-leads', 'site-visits', 'closed-leads'];
@endphp

@push('styles')
<style>
    .reports-shell { display:grid; gap:18px; }
    .reports-hero { background:#fff; border:1px solid #dce6e1; border-radius:10px; padding:20px 22px; display:flex; align-items:center; justify-content:space-between; box-shadow:0 8px 22px rgba(9,42,28,.06); }
    .reports-title { font-size:26px; font-weight:900; color:#08251a; margin:0; letter-spacing:0; }
    .reports-subtitle { color:#66756f; font-size:13px; margin-top:5px; }
    .reports-layout { display:grid; grid-template-columns:300px minmax(0, 1fr); gap:18px; align-items:start; }
    .reports-panel { background:#fff; border:1px solid #dce6e1; border-radius:10px; box-shadow:0 8px 22px rgba(9,42,28,.05); overflow:hidden; }
    .reports-panel-head { padding:15px 17px; border-bottom:1px solid #dce6e1; font-size:15px; font-weight:900; color:#08251a; display:flex; align-items:center; justify-content:space-between; }
    .report-list { padding:10px; display:grid; gap:8px; }
    .report-card { display:grid; grid-template-columns:38px 1fr auto; gap:10px; align-items:center; width:100%; padding:12px; border-radius:9px; border:1px solid transparent; background:transparent; color:#08251a; text-align:left; text-decoration:none; }
    .report-card:hover, .report-card.active { background:#eef8f2; border-color:#bdd8ca; }
    .report-icon { width:38px; height:38px; border-radius:8px; display:grid; place-items:center; background:#dff3e8; color:#075833; }
    .report-name { font-size:14px; font-weight:900; display:block; }
    .report-note { font-size:12px; color:#66756f; margin-top:3px; display:block; line-height:1.35; }
    .report-arrow { color:#8fa29a; }
    .report-form { padding:17px; border-bottom:1px solid #dce6e1; }
    .filter-grid { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:12px; align-items:end; }
    .filter-field label { display:block; font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:#5f7269; font-weight:900; margin-bottom:6px; }
    .filter-field input, .filter-field select { width:100%; min-height:42px; border:1px solid #cfdcd6; border-radius:8px; padding:8px 11px; background:#fff; color:#08251a; font-size:14px; }
    .status-box { grid-column:1 / -1; display:flex; flex-wrap:wrap; gap:10px; padding-top:2px; }
    .status-chip { display:inline-flex; gap:7px; align-items:center; min-height:34px; padding:0 11px; border:1px solid #dce6e1; border-radius:999px; font-size:13px; font-weight:800; color:#25483a; background:#f8fbf9; }
    .status-chip input { width:16px; height:16px; accent-color:#075833; }
    .exclude-box { grid-column:1 / -1; display:grid; gap:9px; padding:13px; border:1px solid #dce6e1; border-radius:9px; background:#f8fbf9; }
    .exclude-title { font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:#5f7269; font-weight:900; }
    .exclude-option { display:inline-flex; gap:8px; align-items:center; width:max-content; min-height:34px; color:#25483a; font-size:13px; font-weight:800; }
    .exclude-option input { width:16px; height:16px; accent-color:#075833; }
    .custom-dates { display:none; grid-column:1 / -1; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:12px; }
    .custom-dates.show { display:grid; }
    .form-actions { display:flex; flex-wrap:wrap; gap:10px; justify-content:flex-end; margin-top:14px; }
    .btn { border:0; border-radius:8px; min-height:40px; padding:9px 14px; font-weight:900; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; justify-content:center; gap:7px; font-size:13px; }
    .btn.primary { background:#075833; color:#fff; }
    .btn.soft { background:#f2f7f4; color:#075833; border:1px solid #dce6e1; }
    .btn.warn { background:#fef7e7; color:#9a5c00; border:1px solid #f4dea2; }
    .summary-grid { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:12px; padding:15px 17px; background:#f6f9f7; border-bottom:1px solid #dce6e1; }
    .metric { background:#fff; border:1px solid #dce6e1; border-radius:9px; padding:13px; }
    .metric-value { font-size:24px; font-weight:900; color:#08251a; }
    .metric-label { color:#66756f; font-size:12px; margin-top:4px; }
    .preview-top { padding:15px 17px; display:flex; justify-content:space-between; align-items:center; gap:12px; }
    .preview-title { font-size:16px; font-weight:900; color:#08251a; }
    .preview-subtitle { color:#66756f; font-size:12px; margin-top:3px; }
    .preview-table-wrap { overflow:auto; border-top:1px solid #dce6e1; }
    .preview-table { width:100%; min-width:850px; border-collapse:collapse; font-size:13px; }
    .preview-table th { background:#f4f7f5; color:#53665d; text-transform:uppercase; font-size:11px; text-align:left; padding:12px 14px; border-bottom:1px solid #dce6e1; white-space:nowrap; }
    .preview-table td { padding:13px 14px; border-bottom:1px solid #edf2ef; vertical-align:top; color:#10261d; max-width:260px; }
    .empty-state { padding:34px 18px; text-align:center; color:#66756f; }
    .history-row { display:grid; grid-template-columns:1.2fr 1fr .8fr .8fr; gap:12px; align-items:center; padding:13px 17px; border-top:1px solid #dce6e1; font-size:13px; }
    .history-muted { color:#66756f; font-size:12px; margin-top:3px; }
    @media (max-width:1100px) { .reports-layout { grid-template-columns:1fr; } .filter-grid, .summary-grid { grid-template-columns:repeat(2, minmax(0, 1fr)); } }
    @media (max-width:700px) { .reports-hero { align-items:flex-start; flex-direction:column; } .filter-grid, .summary-grid, .custom-dates, .history-row { grid-template-columns:1fr; } .form-actions { justify-content:stretch; } .btn { width:100%; } }
</style>
@endpush

@section('content')
<div class="reports-shell">
    @if(session('error'))
        <div style="background:#fee;color:#a92525;padding:12px 14px;border-radius:8px;border-left:4px solid #c33;">{{ session('error') }}</div>
    @endif

    @if(session('success'))
        <div style="background:#effaf1;color:#166534;padding:12px 14px;border-radius:8px;border-left:4px solid #16a34a;">{{ session('success') }}</div>
    @endif

    <section class="reports-hero">
        <div>
            <h1 class="reports-title">Reports Workspace</h1>
            <div class="reports-subtitle">Report choose karo, filters lagao, preview dekho, phir CSV/PDF export karo.</div>
        </div>
        <a href="{{ route('export.index') }}" class="btn soft"><i class="fas fa-sync-alt"></i> Reset</a>
    </section>

    <section class="reports-layout">
        <aside class="reports-panel">
            <div class="reports-panel-head">Premade Reports</div>
            <div class="report-list">
                @foreach($reportDefinitions as $key => $definition)
                    <a class="report-card {{ $selectedReportKey === $key ? 'active' : '' }}" href="{{ route('export.reports.preview', ['reportKey' => $key, 'date_range' => $filters['date_range'] ?? 'this_month']) }}">
                        <span class="report-icon"><i class="{{ $definition['icon'] }}"></i></span>
                        <span>
                            <span class="report-name">{{ $definition['label'] }}</span>
                            <span class="report-note">{{ $definition['description'] }}</span>
                        </span>
                        <span class="report-arrow"><i class="fas fa-chevron-right"></i></span>
                    </a>
                @endforeach
            </div>
        </aside>

        <section class="reports-panel">
            <div class="reports-panel-head">
                <span>{{ $selectedDefinition['label'] }}</span>
                <span style="font-size:12px;color:#66756f;">
                    {{ $selectedReportKey === 'site-visits' ? 'Preview: all matching rows' : 'Preview limit: 200 rows' }}
                </span>
            </div>

            <form class="report-form" method="GET" action="{{ route('export.reports.preview', $selectedReportKey) }}" id="reportPreviewForm">
                <div class="filter-grid">
                    @if(in_array('date', $selectedFilters, true))
                        <div class="filter-field">
                            <label>Date Range</label>
                            <select name="date_range" id="reportDateRange">
                                <option value="all_time" {{ ($filters['date_range'] ?? '') === 'all_time' ? 'selected' : '' }}>All Time</option>
                                <option value="today" {{ ($filters['date_range'] ?? '') === 'today' ? 'selected' : '' }}>Today</option>
                                <option value="this_week" {{ ($filters['date_range'] ?? '') === 'this_week' ? 'selected' : '' }}>This Week</option>
                                <option value="this_month" {{ ($filters['date_range'] ?? 'this_month') === 'this_month' ? 'selected' : '' }}>This Month</option>
                                <option value="previous_month" {{ ($filters['date_range'] ?? '') === 'previous_month' ? 'selected' : '' }}>Previous Month</option>
                                <option value="this_year" {{ ($filters['date_range'] ?? '') === 'this_year' ? 'selected' : '' }}>This Year</option>
                                <option value="custom" {{ ($filters['date_range'] ?? '') === 'custom' ? 'selected' : '' }}>Custom Range</option>
                            </select>
                        </div>
                    @endif

                    @if(in_array('owner', $selectedFilters, true))
                        <div class="filter-field">
                            <label>Owner/User</label>
                            <select name="user_id">
                                <option value="">All Users</option>
                                @foreach($users as $userItem)
                                    <option value="{{ $userItem->id }}" {{ (string)($filters['user_id'] ?? '') === (string)$userItem->id ? 'selected' : '' }}>{{ $userItem->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @if(in_array('source', $selectedFilters, true))
                        <div class="filter-field">
                            <label>Source</label>
                            <select name="source">
                                <option value="">All Sources</option>
                                @foreach($sourceOptions as $sourceKey => $sourceLabel)
                                    <option value="{{ $sourceKey }}" {{ ($filters['source'] ?? '') === $sourceKey ? 'selected' : '' }}>{{ $sourceLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @if(in_array('project', $selectedFilters, true))
                        <div class="filter-field">
                            <label>Project</label>
                            <select name="project_id">
                                <option value="">All Projects</option>
                                @foreach($interestedProjectNames as $project)
                                    <option value="{{ $project->id }}" {{ (string)($filters['project_id'] ?? '') === (string)$project->id ? 'selected' : '' }}>{{ $project->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @if(in_array('search', $selectedFilters, true))
                        <div class="filter-field">
                            <label>Search</label>
                            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Name, phone, email">
                        </div>
                    @endif

                    <div class="custom-dates {{ ($filters['date_range'] ?? '') === 'custom' ? 'show' : '' }}" id="customDateFields">
                        <div class="filter-field">
                            <label>Start Date</label>
                            <input type="date" name="start_date" value="{{ $filters['start_date'] ?? '' }}">
                        </div>
                        <div class="filter-field">
                            <label>End Date</label>
                            <input type="date" name="end_date" value="{{ $filters['end_date'] ?? '' }}">
                        </div>
                    </div>

                    @if(!empty($selectedDefinition['statuses']) && in_array('status', $selectedFilters, true))
                        <div class="status-box">
                            @foreach($selectedDefinition['statuses'] as $statusKey => $statusLabel)
                                <label class="status-chip">
                                    <input type="checkbox" name="status[]" value="{{ $statusKey }}" {{ in_array($statusKey, $activeStatuses, true) ? 'checked' : '' }}>
                                    {{ $statusLabel }}
                                </label>
                            @endforeach
                        </div>
                    @endif

                    @if(in_array($selectedReportKey, $favoriteExcludeReports, true))
                        <div class="exclude-box">
                            <div class="exclude-title">Exclude from export</div>
                            <label class="exclude-option">
                                <input type="hidden" name="exclude_favorites" value="0">
                                <input type="checkbox" name="exclude_favorites" value="1" {{ ($filters['exclude_favorites'] ?? '1') === '1' ? 'checked' : '' }}>
                                Favourite Leads
                            </label>
                            @if($selectedReportKey === 'all-leads')
                                <label class="exclude-option">
                                    <input type="hidden" name="exclude_site_visits" value="0">
                                    <input type="checkbox" name="exclude_site_visits" value="1" {{ ($filters['exclude_site_visits'] ?? '1') === '1' ? 'checked' : '' }}>
                                    Site Visit Leads
                                </label>
                                <label class="exclude-option">
                                    <input type="hidden" name="exclude_closed_leads" value="0">
                                    <input type="checkbox" name="exclude_closed_leads" value="1" {{ ($filters['exclude_closed_leads'] ?? '1') === '1' ? 'checked' : '' }}>
                                    Closed Leads
                                </label>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn primary"><i class="fas fa-eye"></i> Preview</button>
                </div>
            </form>

            @if($preview)
                <div class="summary-grid">
                    @foreach($preview['summary'] as $metric)
                        <div class="metric">
                            <div class="metric-value">{{ $metric['value'] }}</div>
                            <div class="metric-label">{{ $metric['label'] }}</div>
                        </div>
                    @endforeach
                </div>

                <div class="preview-top">
                    <div>
                        <div class="preview-title">Preview</div>
                        <div class="preview-subtitle">{{ $preview['preview_count'] }} rows showing out of {{ $preview['total_records'] }} total records.</div>
                    </div>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;">
                        <form method="POST" action="{{ route('export.reports.download', $selectedReportKey) }}">
                            @csrf
                            @foreach($filters as $filterKey => $filterValue)
                                @if(is_array($filterValue))
                                    @foreach($filterValue as $item)
                                        <input type="hidden" name="{{ $filterKey }}[]" value="{{ $item }}">
                                    @endforeach
                                @elseif($filterValue !== null && $filterValue !== '')
                                    <input type="hidden" name="{{ $filterKey }}" value="{{ $filterValue }}">
                                @endif
                            @endforeach
                            <input type="hidden" name="format" value="csv">
                            <button type="submit" class="btn primary"><i class="fas fa-file-csv"></i> Export CSV</button>
                        </form>
                        <form method="POST" action="{{ route('export.reports.download', $selectedReportKey) }}">
                            @csrf
                            @foreach($filters as $filterKey => $filterValue)
                                @if(is_array($filterValue))
                                    @foreach($filterValue as $item)
                                        <input type="hidden" name="{{ $filterKey }}[]" value="{{ $item }}">
                                    @endforeach
                                @elseif($filterValue !== null && $filterValue !== '')
                                    <input type="hidden" name="{{ $filterKey }}" value="{{ $filterValue }}">
                                @endif
                            @endforeach
                            <input type="hidden" name="format" value="pdf">
                            <button type="submit" class="btn soft"><i class="fas fa-file-pdf"></i> Export PDF</button>
                        </form>
                    </div>
                </div>

                <div class="preview-table-wrap">
                    <table class="preview-table">
                        <thead>
                            <tr>
                                @foreach($selectedDefinition['columns'] as $label)
                                    <th>{{ $label }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($preview['rows'] as $row)
                                <tr>
                                    @foreach($row as $value)
                                        <td>{{ $value !== '' ? $value : '-' }}</td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr><td colspan="{{ count($selectedDefinition['columns']) }}">No records found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty-state">
                    <strong>Preview generate nahi hua hai.</strong><br>
                    Filters set karke Preview dabao, phir export buttons available honge.
                </div>
            @endif
        </section>
    </section>

    <section class="reports-panel">
        <div class="reports-panel-head">Export History</div>
        @forelse($recentExports as $export)
            <div class="history-row">
                <div>
                    <strong>{{ $export->report_name }}</strong>
                    <div class="history-muted">{{ $export->report_key }}</div>
                </div>
                <div>{{ strtoupper($export->format) }} · {{ $export->record_count }} records</div>
                <div>{{ $export->generatedBy?->name ?? 'System' }}</div>
                <div>{{ $export->generated_at?->format('d M Y, h:i A') }}</div>
            </div>
        @empty
            <div class="empty-state">Abhi koi report export history nahi hai.</div>
        @endforelse
    </section>
</div>
@endsection

@push('scripts')
<script>
    const dateRange = document.getElementById('reportDateRange');
    const customFields = document.getElementById('customDateFields');

    function syncCustomDateFields() {
        const isCustom = dateRange?.value === 'custom';
        customFields?.classList.toggle('show', Boolean(isCustom));
        customFields?.querySelectorAll('input').forEach((input) => {
            input.required = Boolean(isCustom);
        });
    }

    dateRange?.addEventListener('change', syncCustomDateFields);
    syncCustomDateFields();
</script>
@endpush

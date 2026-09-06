@extends('sales-manager.layout')

@section('title', 'Export Requests - Assistant Sales Manager')
@section('page-title', 'Export Requests')

@push('styles')
<style>
    .export-mobile-only { display:none; }
    .export-workspace { display:grid; gap:18px; }
    .export-hero { display:flex; justify-content:space-between; align-items:center; gap:16px; background:#fff; border:1px solid #CDE5F5; border-radius:18px; padding:22px; box-shadow:0 14px 34px rgba(15,23,42,.06); }
    .export-hero h1 { margin:0; font-size:28px; color:#002B45; font-weight:800; }
    .export-hero p { margin:8px 0 0; color:#5B7182; font-size:14px; }
    .export-reset { border:1px solid #CDE5F5; background:#F7FBFF; color:#006BA6; border-radius:10px; padding:11px 16px; font-weight:800; cursor:pointer; }
    .export-layout { display:grid; grid-template-columns:300px minmax(0,1fr); gap:18px; }
    .export-card { background:#fff; border:1px solid #CDE5F5; border-radius:18px; box-shadow:0 14px 34px rgba(15,23,42,.05); overflow:hidden; }
    .export-card-head { padding:16px 18px; border-bottom:1px solid #D7EAF6; display:flex; justify-content:space-between; gap:12px; align-items:center; background:linear-gradient(180deg,#ffffff,#F7FBFF); }
    .export-card-head h2 { margin:0; font-size:16px; color:#002B45; font-weight:800; }
    .export-card-head span { color:#5B7182; font-size:12px; font-weight:700; }
    .report-list { padding:12px; display:grid; gap:10px; }
    .report-option { width:100%; border:1px solid transparent; background:#fff; border-radius:12px; padding:12px; display:grid; grid-template-columns:38px minmax(0,1fr) 16px; gap:12px; text-align:left; align-items:center; cursor:pointer; }
    .report-option.is-active { background:#EAF6FD; border-color:#B7E0F4; }
    .report-icon { width:38px; height:38px; border-radius:10px; display:inline-flex; align-items:center; justify-content:center; background:#E0F2FE; color:#006BA6; }
    .report-option strong { display:block; color:#002B45; font-size:14px; }
    .report-option small { display:block; margin-top:3px; color:#5B7182; font-size:12px; line-height:1.35; }
    .export-form-grid { padding:18px; display:grid; grid-template-columns:160px repeat(3,minmax(0,1fr)); gap:14px; align-items:start; }
    .export-field { display:flex; flex-direction:column; gap:8px; min-width:0; align-self:start; }
    .export-field.full { grid-column:1 / -1; }
    .export-field.half { grid-column:span 2; }
    .export-field label { color:#48677A; font-size:11px; font-weight:900; text-transform:uppercase; letter-spacing:.09em; display:flex; align-items:center; justify-content:space-between; }
    .export-label-row { display:flex; align-items:center; justify-content:space-between; gap:10px; color:#48677A; font-size:11px; font-weight:900; text-transform:uppercase; letter-spacing:.09em; }
    .export-bulk-actions { display:inline-flex; align-items:center; gap:5px; }
    .export-mini-action { border:1px solid #CDE5F5; background:#fff; color:#006BA6; border-radius:999px; padding:4px 9px; font-size:10px; line-height:1; font-weight:900; cursor:pointer; text-transform:uppercase; letter-spacing:.04em; }
    .export-mini-action:hover { background:#EAF6FD; border-color:#B7E0F4; }
    .export-field input, .export-field select { min-height:42px; border:1px solid #CDE5F5; border-radius:10px; padding:0 12px; background:#fff; color:#002B45; font-size:14px; box-shadow:0 1px 0 rgba(15,23,42,.02); }
    .export-field input:focus, .export-field select:focus { outline:0; border-color:#006BA6; box-shadow:0 0 0 3px rgba(0,107,166,.10); }
    .export-segmented, .export-check-grid { display:flex; flex-wrap:wrap; gap:8px; }
    .export-segmented label, .export-check-grid label { position:relative; display:inline-flex; cursor:pointer; }
    .export-segmented input, .export-check-grid input { position:absolute; opacity:0; pointer-events:none; }
    .export-segmented span, .export-check-grid span { display:inline-flex; align-items:center; gap:7px; border:1px solid #CDE5F5; border-radius:999px; padding:9px 12px; background:#F7FBFF; color:#174966; font-size:13px; font-weight:800; letter-spacing:.01em; transition:background .15s ease,border-color .15s ease,color .15s ease,box-shadow .15s ease; }
    .export-segmented input:checked + span, .export-check-grid input:checked + span { background:#006BA6; color:#fff; border-color:#006BA6; box-shadow:0 7px 16px rgba(0,107,166,.16); }
    .export-scroll-box { height:112px; overflow:auto; border:1px solid #CDE5F5; background:linear-gradient(180deg,#ffffff,#F7FBFF); border-radius:14px; padding:12px; scrollbar-width:thin; scrollbar-color:#7CA8C2 transparent; }
    .export-field.full .export-check-grid { border:1px solid #CDE5F5; border-radius:14px; padding:12px; background:#F7FBFF; }
    .export-actions { padding:14px 18px; border-top:1px solid #D7EAF6; display:flex; justify-content:space-between; align-items:center; gap:12px; background:#F7FBFF; }
    .export-note { color:#5B7182; font-size:13px; line-height:1.5; }
    .export-btn { border:0; border-radius:11px; min-height:42px; padding:0 17px; display:inline-flex; align-items:center; justify-content:center; gap:9px; font-weight:800; text-decoration:none; cursor:pointer; }
    .export-btn.primary { background:#006BA6; color:#fff; box-shadow:0 10px 24px rgba(0,107,166,.18); }
    .export-btn.soft { background:#EAF6FD; color:#006BA6; border:1px solid #CDE5F5; }
    .export-alert { padding:13px 16px; border-radius:12px; font-size:14px; border:1px solid; }
    .export-alert.success { background:#EAF6FD; color:#006BA6; border-color:#bbf7d0; }
    .export-alert.error { background:#fff1f2; color:#be123c; border-color:#fecdd3; }
    .export-modal-backdrop { position:fixed; inset:0; z-index:1000; display:none; align-items:center; justify-content:center; padding:20px; background:rgba(15,23,42,.42); }
    .export-modal-backdrop.show { display:flex; }
    .export-modal { width:min(520px,100%); background:#fff; border:1px solid #CDE5F5; border-radius:18px; box-shadow:0 24px 70px rgba(15,23,42,.24); overflow:hidden; }
    .export-modal-head { padding:18px 20px; border-bottom:1px solid #D7EAF6; display:flex; align-items:flex-start; justify-content:space-between; gap:14px; }
    .export-modal-head h3 { margin:0; color:#002B45; font-size:20px; font-weight:900; }
    .export-modal-head p { margin:5px 0 0; color:#5B7182; font-size:13px; }
    .export-modal-close { border:0; background:#EAF6FD; color:#174966; width:34px; height:34px; border-radius:10px; cursor:pointer; }
    .export-modal-body { padding:20px; }
    .export-count { display:flex; align-items:baseline; gap:10px; color:#006BA6; margin-bottom:16px; }
    .export-count strong { font-size:38px; line-height:1; }
    .export-count span { font-size:14px; font-weight:800; color:#48677A; }
    .export-summary-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; }
    .export-summary-item { border:1px solid #D7EAF6; background:#F7FBFF; border-radius:12px; padding:10px 12px; }
    .export-summary-item small { display:block; color:#5B7182; font-size:10px; font-weight:900; text-transform:uppercase; letter-spacing:.08em; margin-bottom:4px; }
    .export-summary-item strong { color:#002B45; font-size:13px; }
    .export-zero { display:none; margin-top:14px; border:1px solid #fecdd3; background:#fff1f2; color:#be123c; border-radius:12px; padding:12px; font-size:13px; font-weight:700; }
    .export-modal-actions { padding:16px 20px; border-top:1px solid #D7EAF6; display:flex; justify-content:flex-end; gap:10px; }
    .history-list { display:grid; gap:12px; padding:14px; }
    .history-item { border:1px solid #D7EAF6; border-radius:14px; padding:14px; background:#F7FBFF; }
    .history-top { display:flex; justify-content:space-between; gap:12px; align-items:flex-start; }
    .history-title { color:#002B45; font-size:15px; font-weight:800; }
    .history-sub { color:#5B7182; font-size:12px; margin-top:4px; }
    .history-status { display:inline-flex; border-radius:999px; padding:7px 10px; font-size:11px; font-weight:900; text-transform:uppercase; letter-spacing:.06em; }
    .status-pending, .status-approved, .status-processing { background:#fff7ed; color:#b45309; }
    .status-completed { background:#EAF6FD; color:#006BA6; }
    .status-rejected, .status-expired, .status-failed { background:#fff1f2; color:#be123c; }
    .history-meta { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:8px; margin-top:12px; color:#5B7182; font-size:12px; }
    .history-meta strong { display:block; color:#002B45; font-size:11px; text-transform:uppercase; letter-spacing:.06em; margin-bottom:3px; }
    .history-note { margin-top:10px; background:#F7FBFF; border:1px solid #D7EAF6; border-radius:10px; padding:10px 12px; color:#174966; font-size:12px; line-height:1.5; white-space:pre-line; }
    @media (max-width: 767px) {
        .export-workspace { display:none; }
        .export-mobile-only { display:block; background:#fff; border:1px solid #CDE5F5; border-radius:18px; padding:24px; margin:16px 0; box-shadow:0 12px 30px rgba(15,23,42,.06); }
        .export-mobile-only h1 { margin:0 0 8px; color:#002B45; font-size:22px; }
        .export-mobile-only p { margin:0; color:#5B7182; font-size:14px; line-height:1.6; }
    }
</style>
@endpush

@section('content')
<div class="export-mobile-only">
    <h1>Desktop only</h1>
    <p>Exports are available on desktop only. Please open this page on a laptop/desktop to request lead exports.</p>
</div>

<div class="export-workspace">
    @php($selectedScope = old('assigned_scope', $canUseTeamScope ? 'my_team' : 'own'))
    @if(session('success')) <div class="export-alert success">{{ session('success') }}</div> @endif
    @if(session('error')) <div class="export-alert error">{{ session('error') }}</div> @endif
    @if($errors->any()) <div class="export-alert error">{{ $errors->first() }}</div> @endif

    <section class="export-hero">
        <div>
            <h1>Reports Workspace</h1>
            <p>Report choose karo, filters lagao, request bhejo. Admin approval ke baad mail aur portal me download link milega.</p>
        </div>
        <button type="button" class="export-reset" id="exportResetBtn"><i class="fas fa-rotate-right"></i> Reset</button>
    </section>

    <div class="export-layout">
        <aside class="export-card">
            <div class="export-card-head"><h2>Premade Reports</h2></div>
            <div class="report-list">
                <button type="button" class="report-option is-active" data-report="all"><span class="report-icon"><i class="fas fa-users"></i></span><span><strong>All Leads</strong><small>Complete lead report with owner and remarks.</small></span><i class="fas fa-chevron-right"></i></button>
                <button type="button" class="report-option" data-report="visits"><span class="report-icon"><i class="fas fa-map-marker-alt"></i></span><span><strong>Site Visits</strong><small>Visit scheduled and completed lead data.</small></span><i class="fas fa-chevron-right"></i></button>
                <button type="button" class="report-option" data-report="meetings"><span class="report-icon"><i class="fas fa-calendar-check"></i></span><span><strong>Meetings</strong><small>Meeting scheduled and completed leads.</small></span><i class="fas fa-chevron-right"></i></button>
                <button type="button" class="report-option" data-report="prospects"><span class="report-icon"><i class="fas fa-user-check"></i></span><span><strong>Prospects</strong><small>Verified prospect report.</small></span><i class="fas fa-chevron-right"></i></button>
                <button type="button" class="report-option" data-report="closed"><span class="report-icon"><i class="fas fa-circle-check"></i></span><span><strong>Closed Leads</strong><small>Closed lead report.</small></span><i class="fas fa-chevron-right"></i></button>
                <button type="button" class="report-option" data-report="dead"><span class="report-icon"><i class="fas fa-circle-xmark"></i></span><span><strong>Dead/Junk Leads</strong><small>Dead, junk and inactive leads.</small></span><i class="fas fa-chevron-right"></i></button>
                <button type="button" class="report-option" data-report="source_project"><span class="report-icon"><i class="fas fa-chart-pie"></i></span><span><strong>Source / Project Wise</strong><small>Filter by source and interested project.</small></span><i class="fas fa-chevron-right"></i></button>
            </div>
        </aside>

        <section class="export-card">
            <form method="POST" action="{{ route('sales-manager.lead-downloads.store') }}" id="leadDownloadRequestForm">
                @csrf
                <input type="hidden" name="report_type" id="reportTypeInput" value="{{ old('report_type', 'all') }}">
                <div class="export-card-head">
                    <h2 id="selectedReportTitle">All Leads</h2>
                    <span>Approval required | 24h link | 3 downloads max</span>
                </div>

                <div class="export-form-grid">
                    <div class="export-field">
                        <label>Format</label>
                        <div class="export-segmented">
                            <label><input type="radio" name="format" value="csv" {{ old('format', 'csv') === 'csv' ? 'checked' : '' }}><span><i class="fas fa-file-csv"></i> CSV</span></label>
                            <label><input type="radio" name="format" value="pdf" {{ old('format') === 'pdf' ? 'checked' : '' }}><span><i class="fas fa-file-pdf"></i> PDF</span></label>
                        </div>
                    </div>
                    <div class="export-field">
                        <label for="date_range">Date Range</label>
                        <select id="date_range" name="date_range">
                            @foreach($dateRanges as $key => $label)
                                <option value="{{ $key }}" {{ old('date_range', 'this_month') === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="export-field">
                        <label for="assigned_scope">Owner/User</label>
                        <select id="assigned_scope" name="assigned_scope">
                            @if($canUseTeamScope)
                                <option value="my_team" {{ $selectedScope === 'my_team' ? 'selected' : '' }}>Own + Team Leads</option>
                            @endif
                            <option value="own" {{ $selectedScope === 'own' ? 'selected' : '' }}>Only My Leads</option>
                            @if($canUseTeamScope)
                                <option value="specific_user" {{ $selectedScope === 'specific_user' ? 'selected' : '' }}>Specific User</option>
                            @endif
                        </select>
                    </div>
                    <div class="export-field" id="specificUserField" style="{{ $selectedScope === 'specific_user' ? '' : 'display:none;' }}">
                        <label for="user_id">Specific User</label>
                        <select id="user_id" name="user_id">
                            <option value="">Select user</option>
                            @foreach($availableUsers as $availableUser)
                                <option value="{{ $availableUser->id }}" {{ (string) old('user_id') === (string) $availableUser->id ? 'selected' : '' }}>{{ $availableUser->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="export-field">
                        <label for="search">Search</label>
                        <input id="search" type="text" name="search" value="{{ old('search') }}" placeholder="Name, phone, email">
                    </div>
                    <div class="export-field" id="fromDateField" style="{{ old('date_range') === 'custom' ? '' : 'display:none;' }}">
                        <label for="from_date">From Date</label>
                        <input id="from_date" type="date" name="from_date" value="{{ old('from_date') }}">
                    </div>
                    <div class="export-field" id="toDateField" style="{{ old('date_range') === 'custom' ? '' : 'display:none;' }}">
                        <label for="to_date">To Date</label>
                        <input id="to_date" type="date" name="to_date" value="{{ old('to_date') }}">
                    </div>

                    <div class="export-field half">
                        <div class="export-label-row">
                            <span>Source</span>
                            <span class="export-bulk-actions">
                                <button type="button" class="export-mini-action" data-check-action="all" data-check-name="source">All</button>
                                <button type="button" class="export-mini-action" data-check-action="clear" data-check-name="source">Clear</button>
                            </span>
                        </div>
                        <div class="export-scroll-box export-check-grid">
                            @forelse($sources as $source)
                                <label><input type="checkbox" name="source[]" value="{{ $source }}" @checked(collect(old('source', []))->contains($source))><span>{{ ucfirst(str_replace('_', ' ', $source)) }}</span></label>
                            @empty
                                <span class="export-note">No sources available.</span>
                            @endforelse
                        </div>
                    </div>
                    <div class="export-field half">
                        <div class="export-label-row">
                            <span>Project</span>
                            <span class="export-bulk-actions">
                                <button type="button" class="export-mini-action" data-check-action="all" data-check-name="interested_projects">All</button>
                                <button type="button" class="export-mini-action" data-check-action="clear" data-check-name="interested_projects">Clear</button>
                            </span>
                        </div>
                        <div class="export-scroll-box export-check-grid">
                            @foreach($interestedProjects as $project)
                                <label><input type="checkbox" name="interested_projects[]" value="{{ $project->id }}" @checked(collect(old('interested_projects', []))->contains($project->id))><span>{{ $project->name }}</span></label>
                            @endforeach
                        </div>
                    </div>
                    <div class="export-field half">
                        <div class="export-label-row">
                            <span>Status</span>
                            <span class="export-bulk-actions">
                                <button type="button" class="export-mini-action" data-check-action="all" data-check-name="status">All</button>
                                <button type="button" class="export-mini-action" data-check-action="clear" data-check-name="status">Clear</button>
                            </span>
                        </div>
                        <div class="export-scroll-box export-check-grid" id="statusBox">
                            @foreach($statuses as $status)
                                <label><input type="checkbox" name="status[]" value="{{ $status }}" @checked(collect(old('status', []))->contains($status))><span>{{ ucfirst(str_replace('_', ' ', $status)) }}</span></label>
                            @endforeach
                        </div>
                    </div>
                    <div class="export-field half">
                        <div class="export-label-row">
                            <span>Lead Type</span>
                            <span class="export-bulk-actions">
                                <button type="button" class="export-mini-action" data-check-action="all" data-check-name="lead_type">All</button>
                                <button type="button" class="export-mini-action" data-check-action="clear" data-check-name="lead_type">Clear</button>
                            </span>
                        </div>
                        <div class="export-scroll-box export-check-grid" id="leadTypeBox">
                            @foreach($leadTypes as $key => $label)
                                <label><input type="checkbox" name="lead_type[]" value="{{ $key }}" @checked(collect(old('lead_type', []))->contains($key))><span>{{ $label }}</span></label>
                            @endforeach
                        </div>
                    </div>
                    <div class="export-field full">
                        <div class="export-label-row">
                            <span>Fields</span>
                            <span class="export-bulk-actions">
                                <button type="button" class="export-mini-action" data-check-action="all" data-check-name="fields">All</button>
                                <button type="button" class="export-mini-action" data-check-action="clear" data-check-name="fields">Clear</button>
                            </span>
                        </div>
                        <div class="export-check-grid">
                            @foreach($fields as $key => $label)
                                <label><input type="checkbox" name="fields[]" value="{{ $key }}" @checked(collect(old('fields', ['name', 'phone', 'status', 'assigned_to', 'created_at']))->contains($key))><span>{{ $label }}</span></label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="export-actions">
                    <span class="export-note">All Leads means all accessible leads under selected owner and date range. Admin approval ke baad email aur portal me download button milega.</span>
                    <button type="submit" class="export-btn primary" id="requestExportBtn"><i class="fas fa-paper-plane"></i> Preview & Request</button>
                </div>
            </form>
        </section>
    </div>

    <section class="export-card">
        <div class="export-card-head"><h2>Export History</h2><span>Your latest requests</span></div>
        <div class="history-list">
            @forelse($requests as $requestItem)
                <article class="history-item">
                    <div class="history-top">
                        <div>
                            <div class="history-title">{{ strtoupper($requestItem->format) }} lead export</div>
                            <div class="history-sub">Requested {{ $requestItem->created_at->format('d M Y, h:i A') }}</div>
                        </div>
                        <span class="history-status status-{{ $requestItem->status }}">{{ $requestItem->statusLabel() }}</span>
                    </div>
                    <div class="history-meta">
                        <div><strong>Scope</strong>{{ ucfirst(str_replace('_', ' ', $requestItem->filters['assigned_scope'] ?? 'my team')) }}</div>
                        <div><strong>Records</strong>{{ $requestItem->exported_records_count ?? 'Pending' }}</div>
                        <div><strong>Expires</strong>{{ $requestItem->expires_at ? $requestItem->expires_at->format('d M, h:i A') : 'After approval' }}</div>
                        <div><strong>Downloads</strong>{{ (int) $requestItem->download_click_count }} / {{ \App\Models\LeadDownloadRequest::MAX_DOWNLOAD_CLICKS }}</div>
                    </div>
                    @if($requestItem->rejection_reason)<div class="history-note"><strong>Rejection Reason:</strong> {{ $requestItem->rejection_reason }}</div>@endif
                    @if($requestItem->admin_note)<div class="history-note"><strong>Admin Note:</strong> {{ $requestItem->admin_note }}</div>@endif
                    @if($requestItem->isDownloadReady())
                        <div style="margin-top:12px;"><a href="{{ route('sales-manager.lead-downloads.download', $requestItem) }}" class="export-btn primary"><i class="fas fa-download"></i> Download File ({{ $requestItem->remainingDownloads() }} left)</a></div>
                    @elseif($requestItem->hasReachedDownloadLimit())
                        <div class="history-note"><strong>Locked:</strong> Download limit reached. Please request again.</div>
                    @elseif($requestItem->expires_at && $requestItem->expires_at->isPast())
                        <div class="history-note"><strong>Expired:</strong> Please raise a fresh request to generate a new file.</div>
                    @endif
                </article>
            @empty
                <div class="history-item"><div class="history-title">No requests yet</div><div class="history-sub">Submit your first approval-based export request.</div></div>
            @endforelse
        </div>
    </section>
</div>

<div class="export-modal-backdrop" id="exportPreviewModal" aria-hidden="true">
    <div class="export-modal" role="dialog" aria-modal="true" aria-labelledby="exportPreviewTitle">
        <div class="export-modal-head">
            <div>
                <h3 id="exportPreviewTitle">Confirm lead export</h3>
                <p>Review matched leads before sending admin approval request.</p>
            </div>
            <button type="button" class="export-modal-close" id="exportPreviewClose" aria-label="Close"><i class="fas fa-times"></i></button>
        </div>
        <div class="export-modal-body">
            <div class="export-count"><strong id="previewLeadCount">0</strong><span id="previewRecordLabel">leads matched</span></div>
            <div class="export-summary-grid">
                <div class="export-summary-item"><small>Format</small><strong id="previewFormat">CSV</strong></div>
                <div class="export-summary-item"><small>Scope</small><strong id="previewScope">Only My Leads</strong></div>
                <div class="export-summary-item"><small>Date Range</small><strong id="previewDateRange">This Month</strong></div>
                <div class="export-summary-item"><small>Filters</small><strong id="previewFilters">All optional filters</strong></div>
            </div>
            <div class="export-zero" id="previewZeroMessage">No leads found. Please change filters before submitting.</div>
        </div>
        <div class="export-modal-actions">
            <button type="button" class="export-btn soft" id="exportPreviewCancel">Cancel</button>
            <button type="button" class="export-btn primary" id="exportPreviewSubmit"><i class="fas fa-paper-plane"></i> Submit Request</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('leadDownloadRequestForm');
    if (!form) return;
    const scopeSelect = document.getElementById('assigned_scope');
    const specificUserField = document.getElementById('specificUserField');
    const dateRangeSelect = document.getElementById('date_range');
    const fromDateField = document.getElementById('fromDateField');
    const toDateField = document.getElementById('toDateField');
    const reportTypeInput = document.getElementById('reportTypeInput');
    const selectedReportTitle = document.getElementById('selectedReportTitle');
    const reportButtons = Array.from(document.querySelectorAll('.report-option'));
    const modal = document.getElementById('exportPreviewModal');
    const previewCount = document.getElementById('previewLeadCount');
    const previewRecordLabel = document.getElementById('previewRecordLabel');
    const previewFormat = document.getElementById('previewFormat');
    const previewScope = document.getElementById('previewScope');
    const previewDateRange = document.getElementById('previewDateRange');
    const previewFilters = document.getElementById('previewFilters');
    const previewZero = document.getElementById('previewZeroMessage');
    const previewSubmit = document.getElementById('exportPreviewSubmit');
    let confirmedPreview = false;

    function setChecked(name, values) {
        const allowed = new Set(values);
        form.querySelectorAll(`input[name="${name}[]"]`).forEach((input) => {
            input.checked = allowed.has(input.value);
        });
    }

    function setGroupChecked(name, checked) {
        form.querySelectorAll(`input[name="${name}[]"]`).forEach((input) => {
            input.checked = checked;
        });
    }

    function applyReport(report) {
        const map = {
            all: { title: 'All Leads', status: [], lead_type: [] },
            visits: { title: 'Site Visits', status: ['visit_scheduled', 'visit_done', 'revisited_scheduled', 'revisited_completed'], lead_type: ['visit', 'revisit'] },
            meetings: { title: 'Meetings', status: ['meeting_scheduled', 'meeting_completed'], lead_type: ['meeting'] },
            prospects: { title: 'Prospects', status: ['verified_prospect'], lead_type: ['prospect'] },
            closed: { title: 'Closed Leads', status: ['closed'], lead_type: ['closer'] },
            dead: { title: 'Dead/Junk Leads', status: ['dead', 'junk', 'not_interested'], lead_type: [] },
            source_project: { title: 'Source / Project Wise', status: [], lead_type: [] },
        };
        const config = map[report] || map.all;
        reportTypeInput.value = report;
        selectedReportTitle.textContent = config.title;
        setChecked('status', config.status);
        setChecked('lead_type', config.lead_type);
        if (report === 'all') {
            setChecked('source', []);
            setChecked('interested_projects', []);
        }
        reportButtons.forEach((button) => button.classList.toggle('is-active', button.dataset.report === report));
    }

    function syncConditionalFields() {
        specificUserField.style.display = scopeSelect.value === 'specific_user' ? '' : 'none';
        const custom = dateRangeSelect.value === 'custom';
        fromDateField.style.display = custom ? '' : 'none';
        toDateField.style.display = custom ? '' : 'none';
    }

    reportButtons.forEach((button) => button.addEventListener('click', () => applyReport(button.dataset.report)));
    form.querySelectorAll('[data-check-action][data-check-name]').forEach((button) => {
        button.addEventListener('click', () => {
            setGroupChecked(button.dataset.checkName, button.dataset.checkAction === 'all');
        });
    });
    document.getElementById('exportResetBtn')?.addEventListener('click', () => {
        form.reset();
        applyReport('all');
        syncConditionalFields();
    });
    form.addEventListener('change', syncConditionalFields);
    form.addEventListener('submit', async function (event) {
        if (confirmedPreview) return;
        event.preventDefault();

        const submitButton = document.getElementById('requestExportBtn');
        const originalText = submitButton ? submitButton.innerHTML : '';
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking';
        }

        try {
            const response = await fetch('{{ route('sales-manager.lead-downloads.preview') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value,
                    'Accept': 'application/json',
                },
                body: new FormData(form),
            });

            if (!response.ok) {
                throw new Error('Preview failed. Please check required filters.');
            }

            const data = await response.json();
            previewCount.textContent = data.matched_count ?? 0;
            previewRecordLabel.textContent = data.record_label || 'leads matched';
            previewFormat.textContent = data.format || 'CSV';
            previewScope.textContent = data.summary?.scope || 'Only My Leads';
            previewDateRange.textContent = data.summary?.date_range || 'All Time';

            const filterParts = [];
            if ((data.summary?.sources || 0) > 0) filterParts.push(`${data.summary.sources} source`);
            if ((data.summary?.projects || 0) > 0) filterParts.push(`${data.summary.projects} project`);
            if ((data.summary?.statuses || 0) > 0) filterParts.push(`${data.summary.statuses} status`);
            if ((data.summary?.lead_types || 0) > 0) filterParts.push(`${data.summary.lead_types} lead type`);
            if (data.summary?.search) filterParts.push('search applied');
            previewFilters.textContent = filterParts.length ? filterParts.join(', ') : 'All optional filters';

            previewZero.style.display = data.can_submit ? 'none' : 'block';
            previewSubmit.disabled = !data.can_submit;
            modal.classList.add('show');
            modal.setAttribute('aria-hidden', 'false');
        } catch (error) {
            alert(error.message || 'Unable to preview matching leads.');
        } finally {
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.innerHTML = originalText;
            }
        }
    });

    function closePreviewModal() {
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
    }

    document.getElementById('exportPreviewClose')?.addEventListener('click', closePreviewModal);
    document.getElementById('exportPreviewCancel')?.addEventListener('click', closePreviewModal);
    modal?.addEventListener('click', (event) => {
        if (event.target === modal) closePreviewModal();
    });
    previewSubmit?.addEventListener('click', () => {
        confirmedPreview = true;
        form.submit();
    });
    syncConditionalFields();
});
</script>
@endsection

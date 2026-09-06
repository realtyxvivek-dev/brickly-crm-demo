@php
    $user = auth()->user();
    if ($user && !$user->relationLoaded('role')) {
        $user->load('role');
    }
    $leadBackUrl = route('leads.index');
    $showLeadCreatedTime = $user && ($user->isAdmin() || $user->isCrm());
@endphp
@extends('layouts.app')

@section('title', 'All Leads - ' . brand_name())
@section('page-title', 'All Leads')

@section('header-actions')
<div style="display:flex;align-items:center;gap:10px;">
    @if(auth()->user()->isAdmin() || auth()->user()->isCrm())
    <a href="{{ route('leads.create') }}" class="lc-btn-add">
        <i class="fas fa-plus"></i> Add Lead
    </a>
    @endif
</div>
@endsection

@push('styles')
<style>
/* ── Variables ── */
:root {
    --lc-green-dark: #063A1C;
    --lc-green:      #205A44;
    --lc-green-light:#d1fae5;
    --lc-radius:     14px;
    --lc-shadow:     0 2px 8px rgba(0,0,0,.06);
    --lc-shadow-hover: 0 8px 24px rgba(0,0,0,.12);
}

/* ── Layout ── */
.lc-stats-grid {
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:14px;
    margin-bottom:20px;
}
.lc-stat-card {
    background:#fff;
    border-radius:var(--lc-radius);
    padding:16px 20px;
    border:1px solid #e5e7eb;
    display:flex;
    align-items:center;
    gap:14px;
    box-shadow:var(--lc-shadow);
}
.lc-stat-icon {
    width:42px;height:42px;border-radius:10px;
    display:flex;align-items:center;justify-content:center;flex-shrink:0;
}
.lc-stat-val { font-size:24px;font-weight:700;color:#111827;line-height:1; }
.lc-stat-lbl { font-size:11px;color:#6b7280;margin-top:3px;font-weight:500; }
.lc-queue-cards {
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:14px;
    margin:0 0 20px;
}
.lc-queue-card {
    background:#fff;
    border-radius:var(--lc-radius);
    border:1px solid #e5e7eb;
    box-shadow:var(--lc-shadow);
    padding:16px 18px;
    text-decoration:none;
    color:inherit;
    display:flex;
    gap:12px;
    align-items:flex-start;
    transition:.2s;
}
.lc-queue-card:hover {
    transform:translateY(-2px);
    box-shadow:var(--lc-shadow-hover);
    border-color:#cfe7d7;
}
.lc-queue-icon {
    width:42px;height:42px;border-radius:12px;
    display:flex;align-items:center;justify-content:center;flex-shrink:0;
    color:#fff;font-size:16px;
}
.lc-queue-title { font-size:14px;font-weight:700;color:#111827;line-height:1.2; }
.lc-queue-copy { font-size:12px;color:#6b7280;margin-top:4px;line-height:1.4; }
.lc-queue-count { font-size:28px;font-weight:800;line-height:1;color:#111827;margin-left:auto; }

/* ── Toolbar ── */
.lc-toolbar {
    background:#fff;border-radius:var(--lc-radius);
    border:1px solid #e5e7eb;padding:10px 12px;
    margin-bottom:18px;display:flex;gap:8px;align-items:center;
    justify-content:space-between;
    box-shadow:var(--lc-shadow);flex-wrap:nowrap;
    overflow:visible;
    scrollbar-width:thin;
}
.lc-toolbar form#filterForm {
    min-width:0;
    flex:1 1 auto;
    flex-wrap:nowrap !important;
}
.lc-filter-fields {
    display:flex;
    gap:8px;
    width:auto;
    min-width:0;
    flex:1 1 auto;
    align-items:center;
    flex-wrap:nowrap;
}
.lc-filter-actions {
    display:flex;
    gap:7px;
    align-items:center;
    flex-wrap:nowrap;
    flex:0 0 auto;
}
.lc-toolbar-actions {
    display:flex;
    align-items:center;
    justify-content:flex-end;
    gap:8px;
    flex:0 0 auto;
    margin-left:auto;
    flex-wrap:nowrap;
}
.lc-search-wrap { flex:0 1 230px;min-width:180px;position:relative; }
.lc-search-wrap i { position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:13px; }
.lc-search-input {
    width:100%;height:34px;padding:7px 10px 7px 32px;
    border:1.5px solid #e5e7eb;border-radius:9px;
    font-size:12px;color:#111827;outline:none;
    transition:.2s;background:#f9fafb;
}
.lc-search-input:focus { border-color:#205A44;background:#fff;box-shadow:0 0 0 3px rgba(32,90,68,.08); }
.lc-select {
    height:34px;padding:7px 10px;border:1.5px solid #e5e7eb;border-radius:9px;
    font-size:12px;color:#374151;outline:none;background:#f9fafb;
    min-width:0;cursor:pointer;transition:.2s;
    width:112px;
}
.lc-filter-fields > .lc-select { flex:0 1 112px; }
.lc-filter-fields > .lc-date-range-wrap { flex:0 1 128px; }
.lc-select:focus { border-color:#205A44;background:#fff; }
.lc-status-multi {
    position:relative;
    flex:0 1 126px;
    min-width:96px;
}
.lc-status-trigger {
    width:100%;
    height:34px;
    padding:7px 10px;
    border:1.5px solid #e5e7eb;
    border-radius:9px;
    font-size:12px;
    color:#374151;
    outline:none;
    background:#f9fafb;
    cursor:pointer;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:8px;
    transition:.2s;
}
.lc-status-trigger:hover,
.lc-status-multi.show .lc-status-trigger {
    border-color:#205A44;
    background:#fff;
}
.lc-status-trigger span {
    overflow:hidden;
    white-space:nowrap;
    text-overflow:ellipsis;
}
.lc-status-panel {
    display:none;
    position:absolute;
    left:0;
    top:40px;
    width:230px;
    max-height:330px;
    overflow:auto;
    z-index:80;
    background:#fff;
    border:1px solid #dbe5dd;
    border-radius:12px;
    box-shadow:0 18px 40px rgba(15,23,42,.16);
    padding:8px;
}
.lc-status-multi.show .lc-status-panel { display:block; }
.lc-status-option {
    display:flex;
    align-items:center;
    gap:8px;
    padding:8px 9px;
    border-radius:9px;
    color:#111827;
    font-size:12px;
    font-weight:600;
    cursor:pointer;
}
.lc-status-option:hover { background:#f3f7f4; }
.lc-status-option input {
    width:14px;
    height:14px;
    accent-color:#205A44;
}
.lc-btn-filter {
    height:34px;padding:7px 14px;background:linear-gradient(135deg,#063A1C,#205A44);
    color:#fff;border:none;border-radius:9px;font-size:12px;font-weight:600;
    cursor:pointer;display:flex;align-items:center;gap:6px;transition:.2s;white-space:nowrap;
}
.lc-btn-filter:hover { opacity:.9;transform:translateY(-1px); }
.lc-btn-clear {
    height:34px;padding:7px 12px;background:#f3f4f6;color:#374151;border:none;
    border-radius:9px;font-size:12px;font-weight:500;cursor:pointer;
    text-decoration:none;display:flex;align-items:center;gap:5px;transition:.2s;
}
.lc-btn-clear:hover { background:#e5e7eb; }
.lc-date-range-wrap {
    display:flex;
    gap:7px;
    align-items:center;
    flex-wrap:nowrap;
    width:auto;
}
.lc-custom-date-range {
    display:none;
    gap:9px;
    align-items:center;
    flex-wrap:wrap;
}
.lc-custom-date-range.show {
    display:flex;
}
.lc-date-input {
    height:34px;padding:7px 10px;
    border:1.5px solid #e5e7eb;
    border-radius:9px;
    font-size:12px;
    color:#374151;
    outline:none;
    background:#f9fafb;
    min-width:128px;
    transition:.2s;
}
.lc-date-input:focus {
    border-color:#205A44;
    background:#fff;
}
.lc-btn-add {
    height:34px;padding:7px 13px;background:linear-gradient(135deg,#063A1C,#205A44);
    color:#fff;border-radius:9px;font-size:12px;font-weight:600;
    text-decoration:none;display:flex;align-items:center;gap:6px;transition:.2s;
    flex:0 0 auto;
}
.lc-btn-add:hover { opacity:.9;transform:translateY(-1px); }
.lc-page-size-wrap {
    display:flex;align-items:center;gap:6px;flex:0 0 auto;
    padding:0 0 0 4px;white-space:nowrap;
}
.lc-page-size-wrap label {
    font-size:12px;color:#6b7280;font-weight:600;
}
.lc-page-size-select {
    min-width:70px;
    padding:7px 8px;
    width:70px;
}
.lc-results-count {
    font-size:12px;
    color:#9ca3af;
    white-space:nowrap;
    padding-left:2px;
}

/* ── View Toggle ── */
.lc-column-menu {
    position:relative;
    display:flex;
    align-items:center;
}
.lc-column-toggle,
.lc-export-selected-btn {
    height:34px;
    padding:7px 11px;
    border-radius:9px;
    border:1.5px solid #dbe5dd;
    background:#fff;
    color:#063A1C;
    font-size:12px;
    font-weight:700;
    cursor:pointer;
    display:inline-flex;
    align-items:center;
    gap:7px;
    white-space:nowrap;
}
.lc-export-selected-btn {
    background:#063A1C;
    color:#fff;
    border-color:#063A1C;
}
.lc-export-selected-btn:disabled {
    opacity:.45;
    cursor:not-allowed;
}
.lc-column-panel {
    display:none;
    position:absolute;
    right:0;
    top:42px;
    width:240px;
    z-index:30;
    background:#fff;
    border:1px solid #dbe5dd;
    border-radius:12px;
    box-shadow:0 18px 40px rgba(15,23,42,.14);
    padding:12px;
}
.lc-column-panel.show { display:block; }
.lc-column-title {
    font-size:11px;
    font-weight:800;
    color:#6b7280;
    letter-spacing:.08em;
    text-transform:uppercase;
    margin-bottom:8px;
}
.lc-column-option {
    display:flex;
    align-items:center;
    gap:8px;
    padding:7px 6px;
    border-radius:8px;
    font-size:13px;
    color:#1f2937;
    cursor:pointer;
}
.lc-column-option:hover { background:#f6faf7; }
.lc-table-cell-muted {
    color:#6b7280;
    font-size:12px;
}

.lc-view-toggle {
    display:flex;background:#f3f4f6;border-radius:9px;padding:3px;gap:3px;
}
.lc-toggle-btn {
    padding:6px 11px;border:none;border-radius:7px;font-size:13px;
    cursor:pointer;background:transparent;color:#6b7280;transition:all .2s;
}
.lc-toggle-btn.active { background:#fff;color:#063A1C;box-shadow:0 1px 4px rgba(0,0,0,.1); }

/* ── CARD VIEW ── */
.lc-cards-grid {
    display:grid;
    grid-template-columns:repeat(auto-fill,minmax(260px,1fr));
    gap:18px;
}
.lc-card {
    background:#fff;border-radius:16px;border:1px solid #e5e7eb;
    overflow:hidden;box-shadow:var(--lc-shadow);transition:all .25s;
}
.lc-card:hover { box-shadow:var(--lc-shadow-hover);transform:translateY(-3px);border-color:#c8e6c9; }
.lc-card-top {
    padding:16px 16px 12px;
    display:flex;align-items:flex-start;gap:12px;
    border-bottom:1px solid #f3f4f6;
}
.lc-avatar {
    width:44px;height:44px;border-radius:50%;
    display:flex;align-items:center;justify-content:center;
    font-size:18px;font-weight:700;color:#fff;flex-shrink:0;
}
.lc-card-name { font-size:14px;font-weight:700;color:#111827;line-height:1.3;word-break:break-word; }
.lc-card-phone { font-size:12px;color:#6b7280;margin-top:2px; }
.lc-status-badge {
    margin-left:auto;flex-shrink:0;
    padding:3px 9px;border-radius:20px;
    font-size:10px;font-weight:700;
    text-transform:uppercase;letter-spacing:.4px;
    border:1.5px solid;
}
.lc-card-body { padding:12px 16px; }
.lc-info-row {
    display:flex;align-items:center;gap:7px;
    font-size:12px;color:#6b7280;margin-bottom:6px;
}
.lc-info-row i { width:13px;text-align:center;color:#9ca3af;flex-shrink:0; }
.lc-info-row span { color:#374151; }
.lc-source-pill {
    display:inline-flex;align-items:center;gap:4px;
    padding:2px 8px;border-radius:20px;font-size:10px;font-weight:600;
    background:#f0fdf4;color:#065f46;border:1px solid #bbf7d0;
}
.lc-quarantine-pill {
    display:inline-flex;align-items:center;gap:4px;
    padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;
    background:#fff1f2;color:#be123c;border:1px solid #fecdd3;
    text-transform:uppercase;letter-spacing:.25px;
}
.lc-card-footer {
    border-top:1px solid #f3f4f6;padding:10px 12px;
    display:flex;gap:7px;background:#fafafa;
}
.lc-action {
    flex:1;padding:7px 5px;border-radius:8px;font-size:12px;font-weight:600;
    border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;
    gap:4px;text-decoration:none;transition:.2s;
}
.lc-action:hover { opacity:.88;transform:translateY(-1px); }
.lc-action-view  { background:linear-gradient(135deg,#063A1C,#205A44);color:#fff; }
.lc-action-short { background:#f0fdf4;color:#065f46;border:1.5px solid #bbf7d0 !important; }
.lc-action-del   { background:#fff1f2;color:#be123c;border:1.5px solid #fecdd3 !important; }

/* ── LIST VIEW ── */
.lc-list-wrap {
    display:none;
    background:#fff;border-radius:var(--lc-radius);
    border:1px solid #e5e7eb;box-shadow:var(--lc-shadow);
    overflow-x:auto;
    overflow-y:hidden;
    max-width:100%;
    -webkit-overflow-scrolling:touch;
}
.lc-list-wrap { display:none; }
.lc-list-wrap.active { display:block !important; }
.lc-cards-grid { display:none; }
.lc-cards-grid.active { display:grid !important; }
.lc-table { width:max-content;min-width:100%;border-collapse:collapse; }
.lc-table thead tr { background:#f9fafb;border-bottom:2px solid #e5e7eb; }
.lc-table th {
    padding:11px 14px;text-align:left;font-size:11px;
    font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;
    white-space:nowrap;
}
.lc-table th input[type=checkbox] { cursor:pointer; }
.lc-table tbody tr {
    border-bottom:1px solid #f3f4f6;transition:background .15s;
}
.lc-table tbody tr:hover { background:#f9fafb; }
.lc-table tbody tr:last-child { border-bottom:none; }
.lc-table td { padding:11px 14px;font-size:13px;color:#374151;vertical-align:middle; }
.lc-list-avatar {
    width:32px;height:32px;border-radius:50%;
    display:inline-flex;align-items:center;justify-content:center;
    font-size:13px;font-weight:700;color:#fff;flex-shrink:0;
}
.lc-list-name { font-weight:600;color:#111827;font-size:13px; }
.lc-list-phone { font-size:11px;color:#9ca3af;margin-top:1px; }
.lc-list-actions { display:flex;gap:6px;align-items:center; }
.lc-list-btn {
    padding:5px 10px;border-radius:7px;font-size:11px;font-weight:600;
    border:none;cursor:pointer;text-decoration:none;display:inline-flex;
    align-items:center;gap:4px;transition:.2s;white-space:nowrap;
}
.lc-list-btn:hover { opacity:.85;transform:translateY(-1px); }
.lc-list-btn-view  { background:linear-gradient(135deg,#063A1C,#205A44);color:#fff; }
.lc-list-btn-short { background:#f0fdf4;color:#065f46;border:1.5px solid #bbf7d0 !important; }
.lc-list-btn-del   { background:#fff1f2;color:#be123c;border:1.5px solid #fecdd3 !important; }

/* ── Status Colors ── */
.s-new             { background:#eff6ff;color:#1d4ed8;border-color:#93c5fd; }
.s-connected       { background:#f0fdf4;color:#15803d;border-color:#86efac; }
.s-verified_prospect { background:#fefce8;color:#a16207;border-color:#fde047; }
.s-meeting_scheduled,.s-meeting_completed { background:#fdf4ff;color:#7e22ce;border-color:#d8b4fe; }
.s-visit_scheduled,.s-visit_done,.s-revisited_scheduled,.s-revisited_completed { background:#fff7ed;color:#c2410c;border-color:#fdba74; }
.s-closed          { background:#f0fdf4;color:#065f46;border-color:#6ee7b7; }
.s-dead            { background:#fef2f2;color:#991b1b;border-color:#fca5a5; }
.s-junk            { background:#fff7ed;color:#9a3412;border-color:#fdba74; }
.s-not_interested  { background:#ffe4e6;color:#be123c;border-color:#fda4af; }
.s-on_hold         { background:#f8fafc;color:#475569;border-color:#cbd5e1; }
.s-cnp             { background:#fff7ed;color:#c2410c;border-color:#fdba74; }
.s-follow_up       { background:#eff6ff;color:#1d4ed8;border-color:#93c5fd; }

/* ── Empty ── */
.lc-empty {
    grid-column:1/-1;background:#fff;border-radius:16px;
    border:1px solid #e5e7eb;padding:60px 20px;text-align:center;
    box-shadow:var(--lc-shadow);
}

/* ── Pagination ── */
.lc-pagination {
    margin-top:18px;background:#fff;border-radius:12px;
    border:1px solid #e5e7eb;padding:11px 18px;
    box-shadow:0 1px 4px rgba(0,0,0,.04);
}

/* ── Bulk bar ── */
.lc-bulk-bar {
    display:none;background:#063A1C;color:#fff;
    border-radius:10px;padding:10px 18px;margin-bottom:14px;
    align-items:center;gap:12px;font-size:13px;
}
.lc-bulk-bar.show { display:flex; }
.lc-bulk-btn {
    padding:6px 14px;border-radius:7px;font-size:12px;font-weight:600;
    border:none;cursor:pointer;transition:.2s;
}
.lc-modal-backdrop {
    position:fixed;inset:0;background:rgba(17,24,39,.55);
    display:none;align-items:center;justify-content:center;
    z-index:1050;padding:20px;
}
.lc-modal-backdrop.show { display:flex; }
.lc-modal {
    width:min(440px,100%);background:#fff;border-radius:18px;
    border:1px solid #e5e7eb;box-shadow:0 24px 60px rgba(0,0,0,.18);
    padding:22px;
}
.lc-modal-title { margin:0 0 8px;font-size:20px;font-weight:700;color:#111827; }
.lc-modal-copy { margin:0 0 18px;font-size:13px;color:#6b7280;line-height:1.5; }
.lc-modal-actions { display:flex;justify-content:flex-end;gap:10px;margin-top:18px; }
.lc-modal-error { display:none;margin-top:10px;font-size:12px;color:#b91c1c;font-weight:600; }
.lc-modal-error.show { display:block; }
.lc-progress-panel {
    display:none;
    margin-top:16px;
    border:1px solid #dbe7df;
    border-radius:14px;
    background:#f8fbf9;
    padding:14px;
}
.lc-progress-panel.show { display:block; }
.lc-progress-track {
    width:100%;
    height:10px;
    border-radius:999px;
    background:#e5e7eb;
    overflow:hidden;
}
.lc-progress-fill {
    width:0%;
    height:100%;
    border-radius:999px;
    background:linear-gradient(135deg,#047857,#16a34a);
    transition:width .25s ease;
}
.lc-progress-grid {
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:8px;
    margin-top:12px;
}
.lc-progress-stat {
    border-radius:10px;
    background:#fff;
    border:1px solid #e5e7eb;
    padding:9px;
}
.lc-progress-label {
    display:block;
    font-size:10px;
    line-height:1;
    color:#64748b;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.04em;
}
.lc-progress-value {
    display:block;
    margin-top:6px;
    font-size:18px;
    line-height:1;
    color:#0f172a;
    font-weight:800;
}
.lc-progress-status {
    margin-top:10px;
    font-size:12px;
    color:#475569;
    line-height:1.4;
}
.lc-progress-errors {
    display:none;
    margin-top:10px;
    max-height:96px;
    overflow:auto;
    border-radius:10px;
    background:#fff1f2;
    border:1px solid #fecdd3;
    padding:9px 10px;
    color:#b91c1c;
    font-size:12px;
    line-height:1.45;
}
.lc-progress-errors.show { display:block; }
.lc-btn-secondary {
    padding:9px 14px;border-radius:9px;border:1px solid #d1d5db;
    background:#fff;color:#374151;font-size:13px;font-weight:600;cursor:pointer;
}

@media(max-width:768px){
    .lc-stats-grid { grid-template-columns:repeat(2,1fr); }
    .lc-queue-cards { grid-template-columns:repeat(2,1fr); }
    .lc-toolbar {
        flex-direction:column;
        align-items:stretch;
        overflow-x:visible;
    }
    .lc-toolbar form#filterForm {
        flex-wrap:wrap !important;
    }
    .lc-filter-fields {
        grid-template-columns:1fr;
    }
    .lc-toolbar-actions {
        justify-content:flex-start;
        margin-left:0;
    }
    .lc-select,.lc-btn-filter { width:100%; }
    .lc-table th:nth-child(4),
    .lc-table td:nth-child(4),
    .lc-table th:nth-child(5),
    .lc-table td:nth-child(5) { display:none; }
}
@media(max-width:540px){
    .lc-queue-cards { grid-template-columns:1fr; }
}
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.5} }
</style>
@endpush

@section('content')
@php
    $canViewCnpQuarantine = $canViewCnpQuarantine ?? false;
    $selectedLeadFlag = $selectedLeadFlag ?? request('lead_flag', '');
    $statusLabels = [
        'new'                  => 'New',
        'fresh_transfer'       => 'Fresh Transfer',
        'reenquiry'            => 'Re-enquiry',
        'connected'            => 'Connected',
        'verified_prospect'    => 'Prospect',
        'meeting_scheduled'    => 'Mtg Scheduled',
        'meeting_completed'    => 'Mtg Done',
        'visit_scheduled'      => 'Visit Scheduled',
        'visit_done'           => 'Visit Done',
        'revisited_scheduled'  => 'Revisit Sched.',
        'revisited_completed'  => 'Revisit Done',
        'closed'               => 'Closed',
        'dead'                 => 'Dead',
        'junk'                 => 'Junk',
        'not_interested'       => 'Not Interested',
        'on_hold'              => 'On Hold',
        'cnp'                  => 'CNP',
        'follow_up'            => 'Follow Up',
    ];
    if ($canViewCnpQuarantine) {
        $statusLabels['cnp_quarantine'] = 'CNP Quarantine';
    }
    $rawSelectedStatuses = $selectedStatuses ?? request()->input('status', []);
    if (is_string($rawSelectedStatuses)) {
        $rawSelectedStatuses = str_contains($rawSelectedStatuses, ',') ? explode(',', $rawSelectedStatuses) : [$rawSelectedStatuses];
    }
    $selectedStatusValues = collect($rawSelectedStatuses)
        ->map(fn ($status) => trim((string) $status))
        ->filter()
        ->unique()
        ->values()
        ->all();
    if ($selectedLeadFlag === 'cnp_quarantine' && !in_array('cnp_quarantine', $selectedStatusValues, true)) {
        $selectedStatusValues[] = 'cnp_quarantine';
    }
    $selectedStatusLabels = collect($selectedStatusValues)
        ->map(fn ($status) => $statusLabels[$status] ?? null)
        ->filter()
        ->values()
        ->all();
    $sourceLabels = \App\Models\Lead::sourceOptions();
    if (!array_key_exists('meta_awareness', $sourceLabels)) {
        $sourceLabels = collect($sourceLabels)
            ->put('meta_awareness', 'Meta Awareness')
            ->all();
    }
    $avatarColors = ['#063A1C','#205A44','#065f46','#1d4ed8','#7c3aed','#be185d','#c2410c','#b45309'];
    $selectedDateRange = request('date_range', 'all_time');
    $showCustomDateInputs = $selectedDateRange === 'custom';
    $leadTableColumns = [
        'status' => 'Status',
        'source' => 'Source',
        'category' => 'Category',
        'type' => 'Type',
        'budget' => 'Budget',
        'location' => 'Location',
        'assigned_to' => 'Assigned To',
        'last_assigned_to' => 'Last Assigned To',
        'created_at' => 'Created',
        'last_remark' => 'Last Remark',
        'email' => 'Email',
    ];
    $leadDefaultColumns = ['status', 'source', 'category', 'type', 'budget', 'location', 'assigned_to', 'last_assigned_to', 'created_at', 'last_remark'];
    $displayFieldValue = function (array $values, string $fallback = '—') {
        foreach ($values as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return $fallback;
    };
    $leadFieldValue = function ($lead, array $keys, string $fallback = '—') {
        $formValues = $lead->relationLoaded('formFieldValues')
            ? $lead->formFieldValues->pluck('field_value', 'field_key')
            : collect();

        foreach ($keys as $key) {
            $value = $lead->{$key} ?? $formValues->get($key);
            if ($value !== null && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return $fallback;
    };
@endphp

{{-- Flash --}}
@if(session('success'))
<div style="background:#d1fae5;border:1px solid #6ee7b7;color:#065f46;padding:11px 16px;border-radius:10px;margin-bottom:16px;display:flex;align-items:center;gap:9px;font-size:13px;">
    <i class="fas fa-check-circle"></i> {{ session('success') }}
</div>
@endif
@if(session('error'))
<div style="background:#fee2e2;border:1px solid #fca5a5;color:#991b1b;padding:11px 16px;border-radius:10px;margin-bottom:16px;display:flex;align-items:center;gap:9px;font-size:13px;">
    <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
</div>
@endif
@if(session('warning'))
<div style="background:#fef3c7;border:1px solid #fcd34d;color:#92400e;padding:11px 16px;border-radius:10px;margin-bottom:16px;display:flex;align-items:center;gap:9px;font-size:13px;">
    <i class="fas fa-exclamation-triangle"></i> {{ session('warning') }}
</div>
@endif

{{-- Stats --}}
<div class="lc-stats-grid">
    <div class="lc-stat-card">
        <div class="lc-stat-icon" style="background:linear-gradient(135deg,#063A1C,#205A44);">
            <i class="fas fa-users" style="color:#fff;font-size:16px;"></i>
        </div>
        <div>
            <div class="lc-stat-val">{{ $leadStats['total'] ?? $leads->total() }}</div>
            <div class="lc-stat-lbl">Total Leads</div>
        </div>
    </div>
    <div class="lc-stat-card">
        <div class="lc-stat-icon" style="background:linear-gradient(135deg,#1d4ed8,#3b82f6);">
            <i class="fas fa-user-plus" style="color:#fff;font-size:16px;"></i>
        </div>
        <div>
            <div class="lc-stat-val" style="color:#1d4ed8;">{{ $leadStats['unassigned'] ?? 0 }}</div>
            <div class="lc-stat-lbl">Unassigned</div>
        </div>
    </div>
    <div class="lc-stat-card">
        <div class="lc-stat-icon" style="background:linear-gradient(135deg,#7c3aed,#a78bfa);">
            <i class="fas fa-calendar-check" style="color:#fff;font-size:16px;"></i>
        </div>
        <div>
            <div class="lc-stat-val" style="color:#7c3aed;">{{ $leadStats['in_pipeline'] ?? 0 }}</div>
            <div class="lc-stat-lbl">In Pipeline</div>
        </div>
    </div>
    <div class="lc-stat-card">
        <div class="lc-stat-icon" style="background:linear-gradient(135deg,#065f46,#10b981);">
            <i class="fas fa-check-circle" style="color:#fff;font-size:16px;"></i>
        </div>
        <div>
            <div class="lc-stat-val" style="color:#065f46;">{{ $leadStats['closed'] ?? 0 }}</div>
            <div class="lc-stat-lbl">Closed</div>
        </div>
    </div>
</div>

{{-- Bulk Action Bar --}}
<div class="lc-bulk-bar" id="bulkBar">
    <i class="fas fa-check-square"></i>
    <span id="bulkCount">0</span> leads selected
    @if(auth()->user()->isAdmin() || auth()->user()->isCrm())
    <button class="lc-bulk-btn" style="background:#fff;color:#063A1C;" onclick="bulkAssign()">
        <i class="fas fa-user-plus"></i> Assign
    </button>
    <button class="lc-bulk-btn" style="background:#f59e0b;color:#fff;" onclick="bulkAssignHiring()">
        <i class="fas fa-user-tie"></i> Send to HR Hiring
    </button>
    <button class="lc-bulk-btn" style="background:#10b981;color:#fff;" onclick="bulkCreateCallingTask()">
        <i class="fas fa-phone"></i> Create Calling Task
    </button>
    <button class="lc-bulk-btn" style="background:#ef4444;color:#fff;" onclick="bulkDelete()">
        <i class="fas fa-trash"></i> Delete
    </button>
    @endif
    <button class="lc-bulk-btn" style="background:rgba(255,255,255,.2);color:#fff;margin-left:auto;" onclick="clearSelection()">
        <i class="fas fa-times"></i> Clear
    </button>
</div>

{{-- Toolbar --}}
<div class="lc-toolbar">
    <form method="GET" action="{{ route('leads.index') }}" style="display:flex;gap:8px;flex:1 1 auto;align-items:center;flex-wrap:nowrap;" id="filterForm">
        @if(request('pipeline_stage'))<input type="hidden" name="pipeline_stage" value="{{ request('pipeline_stage') }}">@endif
        @if(request('meta_outcome'))<input type="hidden" name="meta_outcome" value="{{ request('meta_outcome') }}">@endif
        <input type="hidden" name="per_page" value="{{ min(5000, max(50, (int) request('per_page', 500))) }}">
        <div class="lc-filter-fields">
        <div class="lc-search-wrap">
            <i class="fas fa-search"></i>
            <input type="text" name="search" class="lc-search-input"
                   value="{{ request('search') }}"
                   placeholder="Search name, phone, email...">
        </div>
        <div class="lc-status-multi" id="leadStatusMultiSelect">
            <button type="button" class="lc-status-trigger" id="leadStatusTrigger" aria-haspopup="true" aria-expanded="false">
                <span id="leadStatusLabel"
                      data-default-label="All Status"
                      data-selected-prefix="selected">
                    @if(count($selectedStatusLabels) === 0)
                        All Status
                    @elseif(count($selectedStatusLabels) === 1)
                        {{ $selectedStatusLabels[0] }}
                    @else
                        {{ count($selectedStatusLabels) }} selected
                    @endif
                </span>
                <i class="fas fa-chevron-down"></i>
            </button>
            <div class="lc-status-panel" id="leadStatusPanel">
                @foreach($statusLabels as $val => $label)
                    <label class="lc-status-option">
                        <input type="checkbox"
                               name="status[]"
                               value="{{ $val }}"
                               data-label="{{ $label }}"
                               {{ in_array($val, $selectedStatusValues, true) ? 'checked' : '' }}>
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </div>
        <select name="source" class="lc-select" id="leadSourceFilter" data-current-source="{{ request('source') }}">
            <option value="">All Sources</option>
            @foreach($sourceLabels as $val => $label)
                <option value="{{ $val }}" {{ request('source') == $val ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
            @if(auth()->user()->isAdmin() || auth()->user()->isCrm())
                <option value="__create_source__">+ Create Lead Source</option>
            @endif
        </select>
        @if(auth()->user()->isAdmin() || auth()->user()->isCrm())
        <select name="meta_form_id" class="lc-select" title="Meta Form">
            <option value="">All Meta Forms</option>
            @foreach($metaFormOptions ?? [] as $metaForm)
                @php
                    $metaFormLabel = trim(($metaForm->form_name ?: ('Meta Form ' . $metaForm->form_id)) . ($metaForm->page_name ? ' - ' . $metaForm->page_name : ''));
                @endphp
                <option value="{{ $metaForm->id }}" {{ (string) ($selectedMetaFormId ?? request('meta_form_id')) === (string) $metaForm->id ? 'selected' : '' }}>
                    {{ $metaFormLabel }}
                </option>
            @endforeach
        </select>
        <select name="lead_group" class="lc-select">
            <option value="sales" {{ request('lead_group', 'sales') === 'sales' ? 'selected' : '' }}>Sales Leads</option>
            <option value="hr" {{ request('lead_group') === 'hr' ? 'selected' : '' }}>HR Leads</option>
            <option value="all" {{ request('lead_group') === 'all' ? 'selected' : '' }}>All Leads</option>
        </select>
        @if($canViewCnpQuarantine)
        <select name="lead_flag" class="lc-select">
            <option value="">All Flags</option>
            <option value="cnp_quarantine" {{ $selectedLeadFlag === 'cnp_quarantine' ? 'selected' : '' }}>CNP Quarantine</option>
        </select>
        @endif
        <div class="lc-date-range-wrap">
            <select name="date_range" id="dateRangeFilter" class="lc-select" onchange="toggleCustomDateRange(this.value)">
                <option value="all_time" {{ $selectedDateRange === 'all_time' ? 'selected' : '' }}>All Time</option>
                <option value="today" {{ $selectedDateRange === 'today' ? 'selected' : '' }}>Today</option>
                <option value="yesterday" {{ $selectedDateRange === 'yesterday' ? 'selected' : '' }}>Yesterday</option>
                <option value="this_week" {{ $selectedDateRange === 'this_week' ? 'selected' : '' }}>This Week</option>
                <option value="this_month" {{ $selectedDateRange === 'this_month' ? 'selected' : '' }}>This Month</option>
                <option value="previous_month" {{ $selectedDateRange === 'previous_month' ? 'selected' : '' }}>Previous Month</option>
                <option value="this_year" {{ $selectedDateRange === 'this_year' ? 'selected' : '' }}>This Year</option>
                <option value="custom" {{ $selectedDateRange === 'custom' ? 'selected' : '' }}>Custom Date</option>
            </select>
            <div id="customDateRangeFields" class="lc-custom-date-range {{ $showCustomDateInputs ? 'show' : '' }}">
                <input type="date" name="start_date" id="startDateFilter" class="lc-date-input" value="{{ request('start_date') }}" max="{{ request('end_date') ?: '' }}">
                <input type="date" name="end_date" id="endDateFilter" class="lc-date-input" value="{{ request('end_date') }}" min="{{ request('start_date') ?: '' }}">
            </div>
        </div>
        <select name="task_creation" class="lc-select">
            <option value="">All Tasks</option>
            <option value="created" {{ request('task_creation') === 'created' ? 'selected' : '' }}>Task Created</option>
            <option value="not_created" {{ request('task_creation') === 'not_created' ? 'selected' : '' }}>Task Not Created</option>
        </select>
        <select name="assigned_to" class="lc-select">
            <option value="">All Agents</option>
            <option value="unassigned" {{ request('assigned_to') === 'unassigned' ? 'selected' : '' }}>Unassigned</option>
            @foreach($filterUsers ?? [] as $tc)
                <option value="{{ $tc->id }}" {{ request('assigned_to') == $tc->id ? 'selected' : '' }}>{{ $tc->name }}</option>
            @endforeach
        </select>
        @endif
        </div>
        <div class="lc-filter-actions">
        <button type="submit" class="lc-btn-filter">
            <i class="fas fa-filter"></i> Filter
        </button>
        @if(request()->hasAny(['search','status','source','meta_form_id','meta_outcome','lead_group','lead_flag','assigned_to','task_creation','date_range','start_date','end_date','pipeline_stage']))
        <a href="{{ route('leads.index') }}" class="lc-btn-clear">
            <i class="fas fa-times"></i> Clear
        </a>
        @endif
        </div>
    </form>
    @if(auth()->user()->isAdmin() || auth()->user()->isCrm())
    <div class="lc-toolbar-actions">
    <a href="{{ route('leads.create') }}" class="lc-btn-add">
        <i class="fas fa-plus"></i> Add Lead
    </a>
    <form method="GET" action="{{ route('leads.index') }}" class="lc-page-size-wrap" id="pageSizeForm">
        @if(request('search'))<input type="hidden" name="search" value="{{ request('search') }}">@endif
        @foreach($selectedStatusValues as $selectedStatusValue)
            <input type="hidden" name="status[]" value="{{ $selectedStatusValue }}">
        @endforeach
        @if(request('source'))<input type="hidden" name="source" value="{{ request('source') }}">@endif
        @if($selectedMetaFormId ?? null)<input type="hidden" name="meta_form_id" value="{{ $selectedMetaFormId }}">@endif
        @if(request('meta_outcome'))<input type="hidden" name="meta_outcome" value="{{ request('meta_outcome') }}">@endif
        @if(request('lead_group'))<input type="hidden" name="lead_group" value="{{ request('lead_group') }}">@endif
        @if(request('lead_flag'))<input type="hidden" name="lead_flag" value="{{ request('lead_flag') }}">@endif
        @if(request('pipeline_stage'))<input type="hidden" name="pipeline_stage" value="{{ request('pipeline_stage') }}">@endif
        @if(request('assigned_to'))<input type="hidden" name="assigned_to" value="{{ request('assigned_to') }}">@endif
        @if(request('task_creation'))<input type="hidden" name="task_creation" value="{{ request('task_creation') }}">@endif
        @if(request('date_range'))<input type="hidden" name="date_range" value="{{ request('date_range') }}">@endif
        @if(request('start_date'))<input type="hidden" name="start_date" value="{{ request('start_date') }}">@endif
        @if(request('end_date'))<input type="hidden" name="end_date" value="{{ request('end_date') }}">@endif
        <label for="perPageSelect">Show</label>
        <select name="per_page" id="perPageSelect" class="lc-select lc-page-size-select" onchange="document.getElementById('pageSizeForm').submit()">
            @foreach([50, 100, 200, 500, 1000, 5000] as $size)
                <option value="{{ $size }}" {{ (int) request('per_page', 500) === $size ? 'selected' : '' }}>{{ $size }}</option>
            @endforeach
        </select>
    </form>
    <div class="lc-column-menu">
        <button type="button" class="lc-column-toggle" id="leadColumnsToggle">
            <i class="fas fa-columns"></i> Columns
        </button>
        <div class="lc-column-panel" id="leadColumnsPanel">
            <div class="lc-column-title">Table fields</div>
            @foreach($leadTableColumns as $columnKey => $columnLabel)
                <label class="lc-column-option">
                    <input type="checkbox"
                           class="lead-column-checkbox"
                           value="{{ $columnKey }}"
                           {{ in_array($columnKey, $leadDefaultColumns, true) ? 'checked' : '' }}>
                    <span>{{ $columnLabel }}</span>
                </label>
            @endforeach
            <div style="font-size:11px;color:#6b7280;line-height:1.35;margin-top:8px;">
                Lead name aur actions fixed rahenge. Email optional hai, default hidden.
            </div>
        </div>
    </div>
    <button type="button" class="lc-export-selected-btn" id="leadExportSelectedBtn" disabled>
        <i class="fas fa-file-export"></i> Export CSV
    </button>
    <div class="lc-results-count">
        {{ $leads->firstItem() ?? 0 }}-{{ $leads->lastItem() ?? 0 }} of {{ $leads->total() }}
    </div>
    </div>
    @endif
</div>

{{-- ═══ CARD VIEW ═══ --}}
@if(auth()->user()->isAdmin() || auth()->user()->isCrm())
<form method="POST" action="{{ route('leads.export-selected') }}" id="leadExportSelectedForm" style="display:none;">
    @csrf
    <div id="leadExportIds"></div>
    <div id="leadExportColumns"></div>
</form>
@endif

<div class="lc-cards-grid" id="cardsView" style="display:none;" aria-hidden="true">
    @forelse($leads as $lead)
    @php
        $initial = strtoupper(substr($lead->name ?? 'L', 0, 1));
        $bgColor = $avatarColors[crc32($lead->name ?? '') % count($avatarColors)];
        $displayStatus = $lead->display_status ?? $lead->status ?? 'new';
        $statusClass = 's-' . $displayStatus;
        $statusLabel = $statusLabels[$displayStatus] ?? ucfirst($displayStatus);
        $sourceLabel = $lead->source_label;
        $importChannelTag = $lead->import_channel_tag;
        $assignedName = $lead->currentAssignment?->assignedTo?->name ?? null;
        $displayCreatedAt = $lead->display_created_at;
    @endphp
    <div class="lc-card" data-id="{{ $lead->id }}">
        {{-- Top --}}
        <div class="lc-card-top">
            <div style="display:flex;align-items:center;gap:2px;margin-top:2px;">
                <input type="checkbox" class="lead-checkbox" value="{{ $lead->id }}"
                       data-hiring="{{ $lead->is_hiring_candidate ? '1' : '0' }}"
                       onchange="handleLeadCheckboxChange(this)" style="cursor:pointer;margin-right:4px;">
            </div>
            <div class="lc-avatar" style="background:{{ $bgColor }};">{{ $initial }}</div>
            <div style="flex:1;min-width:0;">
                <div class="lc-card-name" title="{{ $lead->name }}">{{ Str::limit($lead->name ?? 'Unknown', 24) }}</div>
                <div class="lc-card-phone">
                    <i class="fas fa-phone" style="font-size:10px;margin-right:3px;"></i>
                    {{ $lead->phone ?? '—' }}
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;justify-content:flex-end;">
                @if($lead->is_reenquiry)
                    <span class="lc-source-pill" style="background:#fff7ed;color:#c2410c;border-color:#fdba74;">Re-enquiry</span>
                @endif
                @if($canViewCnpQuarantine && $lead->cnp_quarantined_at && !$lead->cnp_quarantine_cleared_at)
                    <span class="lc-quarantine-pill" title="{{ $lead->cnp_quarantine_reason ?: 'CNP auto-transfer quarantine' }}">
                        <i class="fas fa-shield-alt" style="font-size:9px;"></i> CNP Quarantine
                    </span>
                @endif
                <span class="lc-status-badge {{ $statusClass }}">{{ $statusLabel }}</span>
            </div>
        </div>

        {{-- Body --}}
        <div class="lc-card-body">
            @if($lead->email)
            <div class="lc-info-row">
                <i class="fas fa-envelope"></i>
                <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:190px;" title="{{ $lead->email }}">{{ $lead->email }}</span>
            </div>
            @endif
            @if($lead->city)
            <div class="lc-info-row">
                <i class="fas fa-map-marker-alt"></i>
                <span>{{ $lead->city }}</span>
            </div>
            @endif
            <div class="lc-info-row">
                <i class="fas fa-user-tie"></i>
                <span>{{ $assignedName ?? 'Unassigned' }}</span>
            </div>
            <div class="lc-info-row" style="justify-content:space-between;">
                <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                    <span class="lc-source-pill">
                        <i class="fas fa-tag" style="font-size:9px;"></i> {{ $sourceLabel }}
                    </span>
                </div>
                <span style="font-size:11px;color:#9ca3af;">{{ $displayCreatedAt?->format($showLeadCreatedTime ? 'd M Y, h:i A' : 'd M Y') }}</span>
            </div>
        </div>

        {{-- Footer --}}
        <div class="lc-card-footer">
            <a href="{{ route('leads.show', ['lead' => $lead, 'back' => $leadBackUrl]) }}" class="lc-action lc-action-view">
                <i class="fas fa-eye"></i> View
            </a>
            <a href="{{ route('leads.show', ['lead' => $lead, 'back' => $leadBackUrl]) }}#shortinfo" class="lc-action lc-action-short">
                <i class="fas fa-info-circle"></i> Short...
            </a>
            @if(auth()->user()->isAdmin() || auth()->user()->isCrm())
            <form action="{{ route('leads.destroy', $lead) }}" method="POST"
                  onsubmit="return confirm('Delete {{ addslashes($lead->name ?? '') }}?');" style="flex:1;">
                @csrf @method('DELETE')
                <button type="submit" class="lc-action lc-action-del" style="width:100%;">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </form>
            @endif
        </div>
    </div>
    @empty
    <div class="lc-empty">
        <div style="width:56px;height:56px;background:#f3f4f6;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;">
            <i class="fas fa-users" style="font-size:22px;color:#d1d5db;"></i>
        </div>
        <div style="font-size:15px;font-weight:600;color:#374151;margin-bottom:5px;">No leads found</div>
        <div style="font-size:12px;color:#9ca3af;">Try adjusting your filters or add a new lead.</div>
    </div>
    @endforelse
</div>

{{-- ═══ LIST VIEW ═══ --}}
<div class="lc-list-wrap active" id="listView">
    <table class="lc-table">
        <thead>
            <tr>
                <th style="width:36px;">
                    <input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)" style="cursor:pointer;">
                </th>
                <th>Lead</th>
                <th data-col="status">Status</th>
                <th data-col="source">Source</th>
                <th data-col="category">Category</th>
                <th data-col="type">Type</th>
                <th data-col="budget">Budget</th>
                <th data-col="location">Location</th>
                <th data-col="assigned_to">Assigned To</th>
                <th data-col="last_assigned_to">Last Assigned To</th>
                <th data-col="created_at">Created</th>
                <th data-col="last_remark">Last Remark</th>
                <th data-col="email">Email</th>
                <th style="text-align:right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($leads as $lead)
            @php
                $initial = strtoupper(substr($lead->name ?? 'L', 0, 1));
                $bgColor = $avatarColors[crc32($lead->name ?? '') % count($avatarColors)];
                $displayStatus = $lead->display_status ?? $lead->status ?? 'new';
                $statusClass = 's-' . $displayStatus;
                $statusLabel = $statusLabels[$displayStatus] ?? ucfirst($displayStatus);
                $sourceLabel = $lead->source_label;
                $importChannelTag = $lead->import_channel_tag;
                $assignedName = $lead->currentAssignment?->assignedTo?->name ?? null;
                $lastAssignedName = $lead->latestAssignment?->assignedTo?->name ?? null;
                $displayCreatedAt = $lead->display_created_at;
                $latestProspect = $lead->latestProspect;
                $latestMeeting = $lead->latestMeeting;
                $prospectProjects = $latestProspect && $latestProspect->relationLoaded('interestedProjects')
                    ? $latestProspect->interestedProjects->pluck('name')->filter()->implode(', ')
                    : null;
                $categoryValue = $displayFieldValue([
                    $leadFieldValue($lead, ['category', 'property_category'], ''),
                    $prospectProjects,
                    $latestProspect?->possession,
                ]);
                $typeValue = $displayFieldValue([
                    $leadFieldValue($lead, ['property_type', 'type', 'apartment_type', 'property_kind'], ''),
                    $latestMeeting?->property_type,
                    $latestProspect?->purpose,
                    $latestProspect?->size,
                ]);
                $budgetValue = $displayFieldValue([
                    $leadFieldValue($lead, ['budget', 'budget_range', 'apartment_budget'], ''),
                    $latestProspect?->budget,
                    $latestMeeting?->budget_range,
                ]);
                $locationValue = $displayFieldValue([
                    $leadFieldValue($lead, ['preferred_location', 'location', 'city', 'living_city'], ''),
                    $latestProspect?->preferred_location,
                    $latestMeeting?->location,
                ]);
            @endphp
            <tr>
                <td>
                    <input type="checkbox" class="lead-checkbox" value="{{ $lead->id }}"
                           data-hiring="{{ $lead->is_hiring_candidate ? '1' : '0' }}"
                           onchange="handleLeadCheckboxChange(this)" style="cursor:pointer;">
                </td>
                <td>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div class="lc-list-avatar" style="background:{{ $bgColor }};">{{ $initial }}</div>
                        <div>
                            <div class="lc-list-name">{{ Str::limit($lead->name ?? 'Unknown', 28) }}</div>
                            <div class="lc-list-phone">{{ $lead->phone ?? '—' }}</div>
                        </div>
                    </div>
                </td>
                <td data-col="status">
                    <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                        @if($lead->is_reenquiry)
                            <span class="lc-source-pill" style="background:#fff7ed;color:#c2410c;border-color:#fdba74;">Re-enquiry</span>
                        @endif
                        @if($canViewCnpQuarantine && $lead->cnp_quarantined_at && !$lead->cnp_quarantine_cleared_at)
                            <span class="lc-quarantine-pill" title="{{ $lead->cnp_quarantine_reason ?: 'CNP auto-transfer quarantine' }}">
                                <i class="fas fa-shield-alt" style="font-size:9px;"></i> CNP Quarantine
                            </span>
                        @endif
                        <span class="lc-status-badge {{ $statusClass }}" style="font-size:10px;">{{ $statusLabel }}</span>
                    </div>
                </td>
                <td data-col="source">
                    <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                        <span class="lc-source-pill">{{ $sourceLabel }}</span>
                    </div>
                </td>
                <td data-col="category" class="lc-table-cell-muted">{{ $categoryValue }}</td>
                <td data-col="type" class="lc-table-cell-muted">{{ $typeValue }}</td>
                <td data-col="budget" class="lc-table-cell-muted">{{ $budgetValue }}</td>
                <td data-col="location" class="lc-table-cell-muted">{{ $locationValue }}</td>
                <td data-col="assigned_to" style="font-size:12px;color:#374151;">{{ $assignedName ?? '—' }}</td>
                <td data-col="last_assigned_to" style="font-size:12px;color:#374151;">{{ $lastAssignedName ?? '—' }}</td>
                <td data-col="created_at" style="font-size:12px;color:#9ca3af;white-space:nowrap;">{{ $displayCreatedAt?->format($showLeadCreatedTime ? 'd M Y, h:i A' : 'd M Y') }}</td>
                <td data-col="last_remark" class="lc-table-cell-muted" style="max-width:220px;" title="{{ $lead->last_remark ?: 'No remark' }}">{{ $lead->last_remark ? Str::limit($lead->last_remark, 42) : 'No remark' }}</td>
                <td data-col="email" class="lc-table-cell-muted">{{ $lead->email ?: '—' }}</td>
                <td>
                    <div class="lc-list-actions" style="justify-content:flex-end;">
                        <a href="{{ route('leads.show', ['lead' => $lead, 'back' => $leadBackUrl]) }}" class="lc-list-btn lc-list-btn-view">
                            <i class="fas fa-eye"></i> View
                        </a>
                        <a href="{{ route('leads.show', ['lead' => $lead, 'back' => $leadBackUrl]) }}#shortinfo" class="lc-list-btn lc-list-btn-short">
                            <i class="fas fa-info-circle"></i> Short
                        </a>
                        @if(auth()->user()->isAdmin() || auth()->user()->isCrm())
                        <form action="{{ route('leads.destroy', $lead) }}" method="POST"
                              onsubmit="return confirm('Delete {{ addslashes($lead->name ?? '') }}?');" style="display:inline;">
                            @csrf @method('DELETE')
                            <button type="submit" class="lc-list-btn lc-list-btn-del">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="14" style="text-align:center;padding:50px;color:#9ca3af;">
                    <i class="fas fa-users" style="font-size:28px;margin-bottom:10px;display:block;color:#e5e7eb;"></i>
                    No leads found
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Pagination --}}
@if($leads->hasPages())
<div class="lc-pagination">{{ $leads->appends(request()->query())->links() }}</div>
@endif

@if(auth()->user()->isAdmin() || auth()->user()->isCrm())
<div class="lc-modal-backdrop" id="createLeadSourceModal">
    <div class="lc-modal">
        <h3 class="lc-modal-title">Create Lead Source</h3>
        <p class="lc-modal-copy">New source yahan create hote hi lead forms, filters aur source-based reports me available ho jayega.</p>
        <form method="POST" action="{{ route('leads.sources.store') }}" id="createLeadSourceForm">
            @csrf
            <div style="display:grid;gap:12px;">
                <div>
                    <label for="newLeadSourceName" style="display:block;font-size:12px;font-weight:700;color:#374151;margin-bottom:8px;">Source Name</label>
                    <input type="text" id="newLeadSourceName" name="name" class="lc-search-input" style="width:100%;padding-left:11px;" placeholder="Example: MagicBricks">
                </div>
                <div>
                    <label for="newLeadSourceType" style="display:block;font-size:12px;font-weight:700;color:#374151;margin-bottom:8px;">Source Type</label>
                    <select id="newLeadSourceType" name="type" class="lc-select" style="width:100%;">
                        @foreach(\App\Models\LeadSource::TYPE_OPTIONS as $typeValue => $typeLabel)
                            <option value="{{ $typeValue }}" {{ $typeValue === 'other' ? 'selected' : '' }}>{{ $typeLabel }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="lc-modal-error" id="createLeadSourceError"></div>
            <div class="lc-modal-actions">
                <button type="button" class="lc-btn-secondary" onclick="closeCreateLeadSourceModal()">Cancel</button>
                <button type="submit" class="lc-btn-filter" id="createLeadSourceSubmitBtn">Create Source</button>
            </div>
        </form>
    </div>
</div>

<div class="lc-modal-backdrop" id="bulkAssignModal">
    <div class="lc-modal">
        <h3 class="lc-modal-title">Bulk Assign Leads</h3>
        <p class="lc-modal-copy">Selected leads ko new owner ke naam assign kiya jayega aur old open task hata kar new owner ka pending task create hoga.</p>
        <form method="POST" action="{{ route('leads.bulk-change-owner') }}" id="bulkAssignForm">
            @csrf
            <div>
                <label for="bulkAssignOwner" style="display:block;font-size:12px;font-weight:700;color:#374151;margin-bottom:8px;">Assign To</label>
                <select id="bulkAssignOwner" name="assigned_to" class="lc-select" style="width:100%;">
                    <option value="">Select owner</option>
                    @foreach($ownerTransferUsers as $ownerUser)
                        <option value="{{ $ownerUser->id }}">{{ $ownerUser->name }}{{ $ownerUser->role ? ' (' . $ownerUser->role->name . ')' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div style="margin-top:14px;">
                <label for="bulkAssignReason" style="display:block;font-size:12px;font-weight:700;color:#374151;margin-bottom:8px;">Transfer Reason</label>
                <textarea id="bulkAssignReason" name="notes" rows="3" class="lc-search-input" style="width:100%;height:auto;padding:11px;resize:vertical;" required placeholder="Why are these leads being transferred?"></textarea>
            </div>
            <div id="bulkAssignIds"></div>
            <div class="lc-progress-panel" id="bulkAssignProgress">
                <div class="lc-progress-track">
                    <div class="lc-progress-fill" id="bulkAssignProgressFill"></div>
                </div>
                <div class="lc-progress-grid">
                    <div class="lc-progress-stat">
                        <span class="lc-progress-label">Total</span>
                        <span class="lc-progress-value" id="bulkAssignTotal">0</span>
                    </div>
                    <div class="lc-progress-stat">
                        <span class="lc-progress-label">Done</span>
                        <span class="lc-progress-value" id="bulkAssignDone">0</span>
                    </div>
                    <div class="lc-progress-stat">
                        <span class="lc-progress-label">Failed</span>
                        <span class="lc-progress-value" id="bulkAssignFailed">0</span>
                    </div>
                    <div class="lc-progress-stat">
                        <span class="lc-progress-label">Left</span>
                        <span class="lc-progress-value" id="bulkAssignLeft">0</span>
                    </div>
                </div>
                <div class="lc-progress-status" id="bulkAssignStatus">Ready.</div>
                <div class="lc-progress-errors" id="bulkAssignErrors"></div>
            </div>
            <div class="lc-modal-actions">
                <button type="button" class="lc-btn-secondary" id="bulkAssignCancelBtn" onclick="closeBulkAssignModal()">Cancel</button>
                <button type="submit" class="lc-btn-filter" id="bulkAssignSubmitBtn">Assign Leads</button>
                <button type="button" class="lc-btn-filter" id="bulkAssignOkBtn" style="display:none;" onclick="finishBulkAssign()">OK</button>
            </div>
        </form>
    </div>
</div>

<div class="lc-modal-backdrop" id="bulkHiringModal">
    <div class="lc-modal">
        <h3 class="lc-modal-title">Send Leads to HR Hiring</h3>
        <p class="lc-modal-copy">Selected leads HR hiring queue me jayengi. Sales owner assignment close ho jayega, aur HR apne Hiring Leads screen par status update karega.</p>
        <form method="POST" action="{{ route('leads.bulk-assign-hiring') }}" id="bulkHiringForm">
            @csrf
            <div>
                <label for="bulkHiringOwner" style="display:block;font-size:12px;font-weight:700;color:#374151;margin-bottom:8px;">Assign HR</label>
                <select id="bulkHiringOwner" name="hr_user_id" class="lc-select" style="width:100%;">
                    <option value="">Select HR user</option>
                    @foreach(($hrHiringUsers ?? collect()) as $hrUser)
                        <option value="{{ $hrUser->id }}">{{ $hrUser->name }}{{ $hrUser->role ? ' (' . $hrUser->role->name . ')' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div style="margin-top:12px;">
                <label for="bulkHiringNote" style="display:block;font-size:12px;font-weight:700;color:#374151;margin-bottom:8px;">Note optional</label>
                <textarea id="bulkHiringNote" name="hr_note" rows="3" class="lc-select" style="width:100%;height:auto;resize:vertical;" placeholder="Example: FB hiring candidate, call and screen for opening."></textarea>
            </div>
            <div id="bulkHiringIds"></div>
            <div class="lc-modal-actions">
                <button type="button" class="lc-btn-secondary" onclick="closeBulkHiringModal()">Cancel</button>
                <button type="submit" class="lc-btn-filter">Send to HR</button>
            </div>
        </form>
    </div>
</div>

<div class="lc-modal-backdrop" id="bulkCallingTaskModal">
    <div class="lc-modal">
        <h3 class="lc-modal-title">Create Calling Tasks</h3>
        <p class="lc-modal-copy">Selected assigned leads ke current owner ke liye bulk calling task create hogi. Junk, not interested, closed aur duplicate open-task leads automatically skip hongi.</p>
        <form method="POST" action="{{ route('leads.bulk-calling-tasks') }}" id="bulkCallingTaskForm">
            @csrf
            <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;">
                <div>
                    <label for="bulkTaskStartDate" style="display:block;font-size:12px;font-weight:700;color:#374151;margin-bottom:8px;">Start Date</label>
                    <input type="date" id="bulkTaskStartDate" name="start_date" class="lc-date-input" style="width:100%;" value="{{ now()->format('Y-m-d') }}">
                </div>
                <div>
                    <label for="bulkTaskStartTime" style="display:block;font-size:12px;font-weight:700;color:#374151;margin-bottom:8px;">Start Time</label>
                    <input type="time" id="bulkTaskStartTime" name="start_time" class="lc-date-input" style="width:100%;" value="{{ now()->addMinutes(10)->format('H:i') }}">
                </div>
            </div>
            <div style="margin-top:12px;">
                <label for="bulkTaskGapMinutes" style="display:block;font-size:12px;font-weight:700;color:#374151;margin-bottom:8px;">Gap Minutes</label>
                <input type="number" id="bulkTaskGapMinutes" name="gap_minutes" class="lc-date-input" style="width:100%;" min="0" max="1440" value="0" placeholder="0">
            </div>
            <div style="margin-top:12px;">
                <label for="bulkTaskNotes" style="display:block;font-size:12px;font-weight:700;color:#374151;margin-bottom:8px;">Notes</label>
                <textarea id="bulkTaskNotes" name="notes" class="lc-search-input" rows="3" style="padding-left:11px;resize:vertical;" placeholder="Optional notes for created calling tasks"></textarea>
            </div>
            <div id="bulkCallingTaskIds"></div>
            <div class="lc-modal-actions">
                <button type="button" class="lc-btn-secondary" onclick="closeBulkCallingTaskModal()">Cancel</button>
                <button type="submit" class="lc-btn-filter">Create Tasks</button>
            </div>
        </form>
    </div>
</div>
@endif

@endsection

@push('scripts')
<script>
// ── Bulk Select ──
function updateBulkBar() {
    const selectedIds = getSelectedIds();
    const bar = document.getElementById('bulkBar');
    const cnt = document.getElementById('bulkCount');
    if (selectedIds.length > 0) {
        bar.classList.add('show');
        cnt.textContent = selectedIds.length;
    } else {
        bar.classList.remove('show');
        cnt.textContent = '0';
    }
    updateLeadExportState();
}

const leadColumnStorageKey = 'basecrm.leads.visibleColumns.{{ auth()->id() }}.v4';
const leadFixedColumns = [];

function selectedLeadColumns() {
    const checked = Array.from(document.querySelectorAll('.lead-column-checkbox:checked'))
        .map(input => input.value)
        .filter(Boolean);

    return Array.from(new Set(checked.concat(leadFixedColumns)));
}

function applyLeadColumns() {
    const saved = JSON.parse(localStorage.getItem(leadColumnStorageKey) || 'null');
    const checkboxes = Array.from(document.querySelectorAll('.lead-column-checkbox'));
    if (Array.isArray(saved)) {
        checkboxes.forEach(input => {
            input.checked = saved.includes(input.value);
        });
    }

    const visible = selectedLeadColumns();
    document.querySelectorAll('[data-col]').forEach(cell => {
        cell.style.display = visible.includes(cell.dataset.col) ? '' : 'none';
    });
}

function saveLeadColumns() {
    localStorage.setItem(leadColumnStorageKey, JSON.stringify(
        Array.from(document.querySelectorAll('.lead-column-checkbox:checked')).map(input => input.value)
    ));
    applyLeadColumns();
}

function updateLeadExportState() {
    const btn = document.getElementById('leadExportSelectedBtn');
    if (!btn) return;
    btn.disabled = getSelectedIds().length === 0;
}

function exportSelectedLeads() {
    const ids = getSelectedIds();
    if (!ids.length) {
        alert('Export ke liye at least one lead select karo.');
        return;
    }

    const form = document.getElementById('leadExportSelectedForm');
    const idContainer = document.getElementById('leadExportIds');
    const columnContainer = document.getElementById('leadExportColumns');
    if (!form || !idContainer || !columnContainer) return;

    idContainer.innerHTML = ids.map(id => '<input type="hidden" name="lead_ids[]" value="' + id + '">').join('');
    columnContainer.innerHTML = selectedLeadColumns()
        .map(column => '<input type="hidden" name="columns[]" value="' + column + '">')
        .join('');
    form.submit();
}

function handleLeadCheckboxChange(checkbox) {
    document.querySelectorAll('.lead-checkbox').forEach(c => {
        if (c !== checkbox && c.value === checkbox.value) {
            c.checked = checkbox.checked;
        }
    });
    updateBulkBar();
}

function toggleSelectAll(cb) {
    const activeView = document.querySelector('.lc-list-wrap.active, .lc-cards-grid.active');
    const visibleCheckboxes = activeView
        ? activeView.querySelectorAll('.lead-checkbox')
        : document.querySelectorAll('.lead-checkbox');
    const ids = Array.from(visibleCheckboxes).map(c => c.value);

    document.querySelectorAll('.lead-checkbox').forEach(c => {
        if (ids.includes(c.value)) {
            c.checked = cb.checked;
        }
    });
    updateBulkBar();
}

function clearSelection() {
    document.querySelectorAll('.lead-checkbox').forEach(c => c.checked = false);
    const sa = document.getElementById('selectAll');
    if (sa) sa.checked = false;
    document.getElementById('bulkBar').classList.remove('show');
    updateLeadExportState();
}

function toggleCustomDateRange(value) {
    const customWrap = document.getElementById('customDateRangeFields');
    const startInput = document.getElementById('startDateFilter');
    const endInput = document.getElementById('endDateFilter');
    const isCustom = value === 'custom';

    if (!customWrap) return;

    customWrap.classList.toggle('show', isCustom);

    if (startInput) {
        startInput.disabled = !isCustom;
        if (!isCustom) {
            startInput.value = '';
            startInput.removeAttribute('max');
        }
    }

    if (endInput) {
        endInput.disabled = !isCustom;
        if (!isCustom) {
            endInput.value = '';
            endInput.removeAttribute('min');
        }
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const dateRangeFilter = document.getElementById('dateRangeFilter');
    const startDateFilter = document.getElementById('startDateFilter');
    const endDateFilter = document.getElementById('endDateFilter');
    const bulkAssignForm = document.getElementById('bulkAssignForm');
    const bulkAssignModal = document.getElementById('bulkAssignModal');
    const bulkHiringForm = document.getElementById('bulkHiringForm');
    const bulkHiringModal = document.getElementById('bulkHiringModal');
    const bulkCallingTaskForm = document.getElementById('bulkCallingTaskForm');
    const bulkCallingTaskModal = document.getElementById('bulkCallingTaskModal');
    const leadColumnsToggle = document.getElementById('leadColumnsToggle');
    const leadColumnsPanel = document.getElementById('leadColumnsPanel');
    const leadExportSelectedBtn = document.getElementById('leadExportSelectedBtn');
    const leadSourceFilter = document.getElementById('leadSourceFilter');
    const leadStatusMultiSelect = document.getElementById('leadStatusMultiSelect');
    const leadStatusTrigger = document.getElementById('leadStatusTrigger');
    const leadStatusLabel = document.getElementById('leadStatusLabel');
    const createLeadSourceForm = document.getElementById('createLeadSourceForm');
    const createLeadSourceModal = document.getElementById('createLeadSourceModal');

    applyLeadColumns();
    updateLeadExportState();

    function updateLeadStatusLabel() {
        if (!leadStatusLabel) return;

        const selectedLabels = Array.from(document.querySelectorAll('#leadStatusPanel input[name="status[]"]:checked'))
            .map(input => input.dataset.label || input.value)
            .filter(Boolean);

        if (selectedLabels.length === 0) {
            leadStatusLabel.textContent = leadStatusLabel.dataset.defaultLabel || 'All Status';
        } else if (selectedLabels.length === 1) {
            leadStatusLabel.textContent = selectedLabels[0];
        } else {
            leadStatusLabel.textContent = selectedLabels.length + ' ' + (leadStatusLabel.dataset.selectedPrefix || 'selected');
        }
    }

    leadStatusTrigger?.addEventListener('click', function (event) {
        event.stopPropagation();
        leadStatusMultiSelect?.classList.toggle('show');
        this.setAttribute('aria-expanded', leadStatusMultiSelect?.classList.contains('show') ? 'true' : 'false');
    });

    document.querySelectorAll('#leadStatusPanel input[name="status[]"]').forEach(input => {
        input.addEventListener('change', updateLeadStatusLabel);
    });

    leadColumnsToggle?.addEventListener('click', function (event) {
        event.stopPropagation();
        leadColumnsPanel?.classList.toggle('show');
    });

    document.querySelectorAll('.lead-column-checkbox').forEach(input => {
        input.addEventListener('change', saveLeadColumns);
    });

    document.addEventListener('click', function (event) {
        if (leadStatusMultiSelect && !event.target.closest('#leadStatusMultiSelect')) {
            leadStatusMultiSelect.classList.remove('show');
            leadStatusTrigger?.setAttribute('aria-expanded', 'false');
        }

        if (leadColumnsPanel && !event.target.closest('.lc-column-menu')) {
            leadColumnsPanel.classList.remove('show');
        }
    });

    leadExportSelectedBtn?.addEventListener('click', exportSelectedLeads);

    leadSourceFilter?.addEventListener('focus', function () {
        this.dataset.currentSource = this.value || '';
    });

    leadSourceFilter?.addEventListener('change', function () {
        if (this.value === '__create_source__') {
            this.value = this.dataset.currentSource || '';
            openCreateLeadSourceModal();
        } else {
            this.dataset.currentSource = this.value || '';
        }
    });

    if (createLeadSourceForm) {
        createLeadSourceForm.addEventListener('submit', createLeadSource);
    }

    createLeadSourceModal?.addEventListener('click', function (event) {
        if (event.target === createLeadSourceModal) {
            closeCreateLeadSourceModal();
        }
    });

    if (dateRangeFilter) {
        toggleCustomDateRange(dateRangeFilter.value);
    }

    if (startDateFilter && endDateFilter) {
        startDateFilter.addEventListener('change', function () {
            if (this.value) {
                endDateFilter.min = this.value;
            } else {
                endDateFilter.removeAttribute('min');
            }
        });

        endDateFilter.addEventListener('change', function () {
            if (this.value) {
                startDateFilter.max = this.value;
            } else {
                startDateFilter.removeAttribute('max');
            }
        });
    }

    if (bulkAssignForm) {
        bulkAssignForm.addEventListener('submit', function (event) {
            const ids = document.querySelectorAll('#bulkAssignIds input[name="ids[]"]');
            const owner = document.getElementById('bulkAssignOwner');
            const reason = document.getElementById('bulkAssignReason');
            if (!ids.length || !owner || !owner.value) {
                event.preventDefault();
                alert('Please select an owner for the selected leads.');
                return;
            }
            if (!reason || !reason.value.trim()) {
                event.preventDefault();
                alert('Please enter transfer reason.');
                return;
            }

            event.preventDefault();
            runBulkOwnerTransfer(Array.from(ids).map(input => input.value), owner.value, reason.value.trim());
        });
    }

    bulkAssignModal?.addEventListener('click', function (event) {
        if (event.target === bulkAssignModal && !window.bulkAssignRunning) {
            closeBulkAssignModal();
        }
    });

    if (bulkHiringForm) {
        bulkHiringForm.addEventListener('submit', function (event) {
            const ids = document.querySelectorAll('#bulkHiringIds input[name="ids[]"]');
            const hrUser = document.getElementById('bulkHiringOwner');
            if (!ids.length || !hrUser || !hrUser.value) {
                event.preventDefault();
                alert('Please select an HR user for the selected leads.');
            }
        });
    }

    bulkHiringModal?.addEventListener('click', function (event) {
        if (event.target === bulkHiringModal) {
            closeBulkHiringModal();
        }
    });

    if (bulkCallingTaskForm) {
        bulkCallingTaskForm.addEventListener('submit', function (event) {
            const ids = document.querySelectorAll('#bulkCallingTaskIds input[name="ids[]"]');
            if (!ids.length) {
                event.preventDefault();
                alert('Please select at least one lead.');
            }
        });
    }

    bulkCallingTaskModal?.addEventListener('click', function (event) {
        if (event.target === bulkCallingTaskModal) {
            closeBulkCallingTaskModal();
        }
    });
});

function openCreateLeadSourceModal() {
    const modal = document.getElementById('createLeadSourceModal');
    const input = document.getElementById('newLeadSourceName');
    const error = document.getElementById('createLeadSourceError');
    if (error) {
        error.textContent = '';
        error.classList.remove('show');
    }
    if (modal) modal.classList.add('show');
    setTimeout(() => input?.focus(), 50);
}

function closeCreateLeadSourceModal() {
    const modal = document.getElementById('createLeadSourceModal');
    const form = document.getElementById('createLeadSourceForm');
    const error = document.getElementById('createLeadSourceError');
    if (modal) modal.classList.remove('show');
    if (form) form.reset();
    if (error) {
        error.textContent = '';
        error.classList.remove('show');
    }
}

async function createLeadSource(event) {
    event.preventDefault();

    const form = event.currentTarget;
    const submitBtn = document.getElementById('createLeadSourceSubmitBtn');
    const error = document.getElementById('createLeadSourceError');
    const sourceSelect = document.getElementById('leadSourceFilter');
    const filterForm = document.getElementById('filterForm');
    const formData = new FormData(form);

    if (error) {
        error.textContent = '';
        error.classList.remove('show');
    }

    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Creating...';
    }

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: formData,
        });
        const result = await response.json().catch(() => ({}));

        if (!response.ok || !result.source) {
            const message = result.errors?.name?.[0] || result.message || 'Source create nahi ho paya.';
            if (error) {
                error.textContent = message;
                error.classList.add('show');
            }
            return;
        }

        if (sourceSelect) {
            const createOption = Array.from(sourceSelect.options).find(option => option.value === '__create_source__');
            let option = Array.from(sourceSelect.options).find(item => item.value === result.source.key);
            if (!option) {
                option = new Option(result.source.name, result.source.key);
                sourceSelect.add(option, createOption || null);
            }
            sourceSelect.value = result.source.key;
            sourceSelect.dataset.currentSource = result.source.key;
        }

        closeCreateLeadSourceModal();
        filterForm?.submit();
    } catch (fetchError) {
        if (error) {
            error.textContent = fetchError.message || 'Network error. Please retry.';
            error.classList.add('show');
        }
    } finally {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Create Source';
        }
    }
}

function getSelectedIds() {
    return Array.from(new Set(
        Array.from(document.querySelectorAll('.lead-checkbox:checked'))
            .map(c => c.value)
            .filter(Boolean)
    ));
}

function getSelectedLeadTypes() {
    const selectedById = new Map();
    document.querySelectorAll('.lead-checkbox:checked').forEach(c => {
        if (!selectedById.has(c.value)) {
            selectedById.set(c.value, c);
        }
    });
    const checked = Array.from(selectedById.values());
    const hiringCount = checked.filter(c => c.dataset.hiring === '1').length;
    return {
        total: checked.length,
        hiringCount,
        hasHiring: hiringCount > 0,
        allHiring: checked.length > 0 && hiringCount === checked.length,
        mixed: hiringCount > 0 && hiringCount < checked.length,
    };
}

function bulkAssign() {
    const ids = getSelectedIds();
    if (!ids.length) return;

    const selectedTypes = getSelectedLeadTypes();
    if (selectedTypes.mixed) {
        alert('Please select either only hiring leads or only sales leads.');
        return;
    }

    if (selectedTypes.allHiring) {
        bulkAssignHiring();
        return;
    }

    const modal = document.getElementById('bulkAssignModal');
    const container = document.getElementById('bulkAssignIds');
    const ownerSelect = document.getElementById('bulkAssignOwner');
    const reason = document.getElementById('bulkAssignReason');
    if (!modal || !container || !ownerSelect) return;

    ownerSelect.value = '';
    if (reason) reason.value = '';
    container.innerHTML = ids.map(id => '<input type="hidden" name="ids[]" value="' + id + '">').join('');
    resetBulkAssignProgress(ids.length);
    modal.classList.add('show');
}

function closeBulkAssignModal() {
    if (window.bulkAssignRunning) return;
    const modal = document.getElementById('bulkAssignModal');
    const ownerSelect = document.getElementById('bulkAssignOwner');
    const reason = document.getElementById('bulkAssignReason');
    const container = document.getElementById('bulkAssignIds');
    if (ownerSelect) ownerSelect.value = '';
    if (reason) reason.value = '';
    if (container) container.innerHTML = '';
    if (modal) modal.classList.remove('show');
}

function resetBulkAssignProgress(total) {
    window.bulkAssignRunning = false;
    const progress = document.getElementById('bulkAssignProgress');
    const submitBtn = document.getElementById('bulkAssignSubmitBtn');
    const cancelBtn = document.getElementById('bulkAssignCancelBtn');
    const okBtn = document.getElementById('bulkAssignOkBtn');
    const ownerSelect = document.getElementById('bulkAssignOwner');
    const reason = document.getElementById('bulkAssignReason');

    updateBulkAssignProgress({
        total,
        done: 0,
        failed: 0,
        status: total + ' selected lead(s) ready for transfer.',
        errors: [],
    });

    progress?.classList.remove('show');
    if (submitBtn) {
        submitBtn.style.display = 'inline-flex';
        submitBtn.disabled = false;
        submitBtn.textContent = 'Assign Leads';
    }
    if (cancelBtn) {
        cancelBtn.style.display = 'inline-flex';
        cancelBtn.disabled = false;
    }
    if (okBtn) okBtn.style.display = 'none';
    if (ownerSelect) ownerSelect.disabled = false;
    if (reason) reason.disabled = false;
}

function updateBulkAssignProgress({ total, done, failed, status, errors }) {
    const left = Math.max(total - done - failed, 0);
    const percent = total > 0 ? Math.min(100, Math.round(((done + failed) / total) * 100)) : 0;
    const progress = document.getElementById('bulkAssignProgress');
    const fill = document.getElementById('bulkAssignProgressFill');
    const errorsBox = document.getElementById('bulkAssignErrors');

    progress?.classList.add('show');
    if (fill) fill.style.width = percent + '%';
    const totalEl = document.getElementById('bulkAssignTotal');
    const doneEl = document.getElementById('bulkAssignDone');
    const failedEl = document.getElementById('bulkAssignFailed');
    const leftEl = document.getElementById('bulkAssignLeft');
    const statusEl = document.getElementById('bulkAssignStatus');
    if (totalEl) totalEl.textContent = total;
    if (doneEl) doneEl.textContent = done;
    if (failedEl) failedEl.textContent = failed;
    if (leftEl) leftEl.textContent = left;
    if (statusEl) statusEl.textContent = status;

    if (errorsBox) {
        const list = Array.isArray(errors) ? errors.filter(Boolean).slice(-6) : [];
        errorsBox.innerHTML = list.map(error => '<div>' + escapeBulkAssignText(error) + '</div>').join('');
        errorsBox.classList.toggle('show', list.length > 0);
    }
}

function escapeBulkAssignText(value) {
    const div = document.createElement('div');
    div.textContent = String(value || '');
    return div.innerHTML;
}

async function runBulkOwnerTransfer(ids, ownerId, reason) {
    const form = document.getElementById('bulkAssignForm');
    const ownerSelect = document.getElementById('bulkAssignOwner');
    const reasonInput = document.getElementById('bulkAssignReason');
    const submitBtn = document.getElementById('bulkAssignSubmitBtn');
    const cancelBtn = document.getElementById('bulkAssignCancelBtn');
    const okBtn = document.getElementById('bulkAssignOkBtn');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const chunkSize = 10;
    const total = ids.length;
    let done = 0;
    let failed = 0;
    const errors = [];

    window.bulkAssignRunning = true;
    if (ownerSelect) ownerSelect.disabled = true;
    if (reasonInput) reasonInput.disabled = true;
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Processing...';
    }
    if (cancelBtn) cancelBtn.disabled = true;
    updateBulkAssignProgress({ total, done, failed, status: 'Transfer started...', errors });

    for (let index = 0; index < ids.length; index += chunkSize) {
        const chunk = ids.slice(index, index + chunkSize);
        const body = new FormData();
        body.append('assigned_to', ownerId);
        body.append('notes', reason);
        chunk.forEach(id => body.append('ids[]', id));

        try {
            updateBulkAssignProgress({
                total,
                done,
                failed,
                status: 'Transferring ' + (index + 1) + ' to ' + Math.min(index + chunk.length, total) + ' of ' + total + '...',
                errors,
            });

            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body,
            });
            const result = await response.json().catch(() => ({}));

            if (!response.ok) {
                failed += chunk.length;
                errors.push(result.message || 'A batch failed. Please retry failed leads.');
                continue;
            }

            done += Number(result.success || 0);
            failed += Number(result.failed || 0);
            if (Array.isArray(result.errors) && result.errors.length) {
                errors.push(...result.errors);
            }
        } catch (error) {
            failed += chunk.length;
            errors.push(error.message || 'Network error while transferring leads.');
        }

        updateBulkAssignProgress({ total, done, failed, status: 'Processed ' + Math.min(index + chunk.length, total) + ' of ' + total + '.', errors });
    }

    window.bulkAssignRunning = false;
    if (submitBtn) submitBtn.style.display = 'none';
    if (cancelBtn) cancelBtn.style.display = 'none';
    if (okBtn) okBtn.style.display = 'inline-flex';

    const status = failed > 0
        ? 'Transfer completed with ' + failed + ' failed lead(s).'
        : 'Transfer completed successfully.';
    updateBulkAssignProgress({ total, done, failed, status, errors });
}

function finishBulkAssign() {
    window.location.reload();
}

function bulkAssignHiring() {
    const ids = getSelectedIds();
    if (!ids.length) return;

    const selectedTypes = getSelectedLeadTypes();
    if (selectedTypes.mixed) {
        alert('Please select either only hiring leads or only sales leads.');
        return;
    }

    const modal = document.getElementById('bulkHiringModal');
    const container = document.getElementById('bulkHiringIds');
    const ownerSelect = document.getElementById('bulkHiringOwner');
    const note = document.getElementById('bulkHiringNote');
    if (!modal || !container || !ownerSelect) return;

    ownerSelect.value = '';
    if (note) note.value = '';
    container.innerHTML = ids.map(id => '<input type="hidden" name="ids[]" value="' + id + '">').join('');
    modal.classList.add('show');
}

function closeBulkHiringModal() {
    const modal = document.getElementById('bulkHiringModal');
    const ownerSelect = document.getElementById('bulkHiringOwner');
    const note = document.getElementById('bulkHiringNote');
    const container = document.getElementById('bulkHiringIds');
    if (ownerSelect) ownerSelect.value = '';
    if (note) note.value = '';
    if (container) container.innerHTML = '';
    if (modal) modal.classList.remove('show');
}

function bulkCreateCallingTask() {
    const ids = getSelectedIds();
    if (!ids.length) return;
    const modal = document.getElementById('bulkCallingTaskModal');
    const container = document.getElementById('bulkCallingTaskIds');
    if (!modal || !container) return;

    container.innerHTML = ids.map(id => '<input type="hidden" name="ids[]" value="' + id + '">').join('');
    modal.classList.add('show');
}

function closeBulkCallingTaskModal() {
    const modal = document.getElementById('bulkCallingTaskModal');
    const container = document.getElementById('bulkCallingTaskIds');
    const notes = document.getElementById('bulkTaskNotes');
    if (container) container.innerHTML = '';
    if (notes) notes.value = '';
    if (modal) modal.classList.remove('show');
}

function bulkDelete() {
    const ids = getSelectedIds();
    if (!ids.length) return;
    if (!confirm('Delete ' + ids.length + ' selected leads? This cannot be undone.')) return;
    // Submit bulk delete form
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '{{ route("leads.bulk-delete") }}';
    form.innerHTML = '@csrf @method("DELETE")';
    ids.forEach(id => {
        const inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = id;
        form.appendChild(inp);
    });
    document.body.appendChild(form);
    form.submit();
}
</script>
@endpush

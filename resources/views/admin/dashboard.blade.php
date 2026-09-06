@extends('layouts.app')

@php
    $dashboardSourceOptions = \App\Models\Lead::sourceOptions();
@endphp

@section('title', 'Admin Dashboard - ' . brand_name())
@section('page-title', '')
@section('page-subtitle', '')
@section('header-actions')
@endsection

@push('styles')
<style>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Fraunces:opsz,wght@9..144,600;9..144,700&display=swap');
.admin-dashboard-root { font-family: 'Outfit', 'Inter', sans-serif; color: #16161A; }
.layout-admin .main-header.main-header-admin-dashboard { display: none; }
.admin-dashboard-shell { display: flex; flex-direction: column; gap: 16px; position:relative; }
.admin-hero { position: relative; overflow: hidden; border: 1px solid #e2e1dc; border-radius: 18px; background: radial-gradient(circle at top right, rgba(93, 202, 165, 0.18), transparent 34%), radial-gradient(circle at left bottom, rgba(23, 97, 168, 0.10), transparent 26%), linear-gradient(135deg, #ffffff, #fafaf8 55%, #f0efec); padding: 14px 16px; box-shadow: 0 8px 22px rgba(0,0,0,.04); }
.admin-hero-grid { display: grid; grid-template-columns: minmax(0, 1fr); gap: 10px; align-items: start; }
.admin-hero-head { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; width:100%; }
.admin-hero-title { font-family: 'Fraunces', serif; font-size: 24px; line-height: 1.05; font-weight: 700; color: #16161A; max-width: 680px; }
.admin-hero-actions { display:flex; align-items:center; gap:8px; flex-wrap:wrap; justify-content:flex-end; margin-left:auto; }
.admin-hero-clock { background:#fff; border:1px solid #e2e1dc; border-radius:10px; padding:7px 10px; min-width:140px; text-align:center; box-shadow:0 1px 3px rgba(0,0,0,.06); }
.admin-hero-clock-time { font-family:'Courier New', monospace; font-size:16px; font-weight:700; color:#0b6b4f; }
.admin-hero-clock-date { margin-top:2px; font-size:11px; color:#7a7a73; }
.admin-hero-logout { display:inline-flex; align-items:center; gap:6px; border:none; border-radius:10px; padding:9px 12px; background:#ef4444; color:#fff; font-size:12px; font-weight:700; cursor:pointer; }
.admin-hero-logout:hover { background:#dc2626; }
.admin-hero-mode { display:inline-flex; flex-direction:column; gap:5px; width:fit-content; }
.dashboard-mode-toggle { display: inline-flex; gap: 4px; padding: 5px; border-radius: 999px; border: 1px solid #e2e1dc; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.03); }
.dashboard-mode-btn { border: none; border-radius: 999px; background: transparent; color: #7a7a73; font-size: 11px; font-weight: 700; cursor: pointer; padding: 8px 18px; transition: all .2s ease; min-width: 84px; }
.dashboard-mode-btn.is-active.sale { background: linear-gradient(135deg, #0b6b4f, #084d3a); color: #fff; box-shadow: 0 8px 20px rgba(11,107,79,.18); }
.dashboard-mode-btn.is-active.marketing { background: linear-gradient(135deg, #5946c0, #7a67de); color: #fff; box-shadow: 0 8px 20px rgba(89,70,192,.18); }
.dashboard-mode-btn.is-active.finance { background: linear-gradient(135deg, #14532d, #0f766e); color: #fff; box-shadow: 0 8px 20px rgba(15,118,110,.18); }
.dashboard-mode-btn.is-active.hr { background: linear-gradient(135deg, #0f766e, #2563eb); color: #fff; box-shadow: 0 8px 20px rgba(37,99,235,.18); }
.admin-hero.erp-compact {
    border-radius:16px;
    padding:8px 12px;
    background:linear-gradient(135deg,#fff,#fbfcfb 70%,#eef8f3);
    box-shadow:0 1px 2px rgba(15,23,42,.04);
}
.admin-hero.erp-compact .admin-hero-grid > div {
    display:grid;
    grid-template-columns:minmax(220px, 1fr) auto auto;
    align-items:center;
    gap:10px;
}
.admin-hero.erp-compact .admin-hero-head {
    display:contents;
}
.admin-hero.erp-compact .admin-hero-title {
    font-family:'Outfit','Inter',sans-serif;
    font-size:20px;
    line-height:1;
    white-space:nowrap;
}
.admin-hero.erp-compact .admin-hero-mode {
    width:auto;
    flex-direction:row;
    align-items:center;
    justify-content:center;
    gap:8px;
    padding:3px 4px 3px 10px;
    border:1px solid #e4ebe6;
    border-radius:999px;
    background:rgba(255,255,255,.82);
}
.admin-hero.erp-compact .admin-hero-mode > div {
    display:flex;
    align-items:center;
    gap:9px;
}
.admin-hero.erp-compact .admin-hero-mode .premium-filter-label {
    font-size:9px;
    white-space:nowrap;
}
.admin-hero.erp-compact .dashboard-mode-toggle {
    margin-top:0 !important;
    padding:3px;
    gap:3px;
}
.admin-hero.erp-compact .dashboard-mode-btn {
    min-width:68px;
    padding:6px 10px;
    font-size:10px;
}
.admin-hero.erp-compact .admin-hero-actions {
    flex-wrap:nowrap;
    align-items:center;
    gap:6px;
}
.admin-hero.erp-compact .admin-hero-clock {
    min-width:110px;
    padding:5px 8px;
    border-radius:9px;
    box-shadow:none;
}
.admin-hero.erp-compact .admin-hero-clock-time {
    font-size:14px;
    line-height:1;
}
.admin-hero.erp-compact .admin-hero-clock-date {
    font-size:9px;
}
.admin-hero.erp-compact .admin-hero-logout {
    padding:7px 10px;
    border-radius:9px;
    font-size:11px;
}
.admin-period-filter-panel.erp-compact {
    display:none;
    position:absolute;
    top:58px;
    right:0;
    z-index:60;
    width:min(860px, calc(100vw - 32px));
    padding:12px 14px !important;
    margin-bottom:12px !important;
    border-radius:14px;
    box-shadow:0 18px 44px rgba(15,23,42,.14);
}
.admin-period-filter-panel.erp-compact.is-open { display:block; }
.admin-period-filter-panel.erp-compact .premium-filter-bar {
    gap:10px;
}
.admin-period-filter-panel.erp-compact .date-filter-btn {
    padding:6px 12px;
    font-size:11px;
}
.admin-period-filter-panel.erp-compact .dashboard-customize-btn {
    padding:8px 12px;
    border-radius:9px;
    font-size:11px;
}
.admin-period-filter-panel.erp-compact input[type="date"] {
    height:34px;
    padding:5px 9px !important;
    border-radius:8px !important;
    font-size:12px !important;
}
@media (max-width: 820px) {
    .demand-custom-range { grid-template-columns:1fr 1fr; }
    .demand-custom-range .section-filter-btn.apply { grid-column:1 / -1; }
    .admin-hero.erp-compact .admin-hero-grid > div {
        display:grid;
        grid-template-columns:minmax(0, 1fr) auto;
        gap:8px;
    }
    .admin-hero.erp-compact .admin-hero-title {
        font-size:18px;
        white-space:normal;
    }
    .admin-hero.erp-compact .admin-hero-actions {
        grid-column:2;
        grid-row:1;
    }
    .admin-hero.erp-compact .admin-hero-mode {
        grid-column:1 / -1;
        width:100%;
        justify-content:space-between;
        min-width:0;
    }
    .admin-hero.erp-compact .admin-hero-mode > div {
        min-width:0;
    }
    .admin-hero.erp-compact .dashboard-mode-toggle {
        max-width:100%;
        overflow-x:auto;
        -webkit-overflow-scrolling:touch;
    }
    .admin-period-filter-panel.erp-compact {
        top:96px;
        left:0;
        right:0;
        width:100%;
    }
}
.dashboard-panel { display: none; animation: fadeUp .24s ease; }
.dashboard-panel.is-active { display: block; }
.dashboard-panel.hidden,
.dashboard-skeleton.hidden { display: none !important; }
#sale-dashboard-panel.dashboard-panel.is-active {
    display:grid;
    width:100%;
    grid-template-columns:repeat(3, minmax(0, 1fr)) !important;
    grid-template-areas:
        "stats stats stats"
        "approval-center demand-insights demand-insights"
        "performance pipeline pipeline"
        "sales-user-activity sales-user-activity sales-user-activity"
        "sales-score-table sales-score-table sales-score-table"
        "user-pipeline user-pipeline user-pipeline"
        "sales-performance sales-performance sales-performance"
        "user-visits-meetings user-visits-meetings user-visits-meetings"
        "call-statistics call-statistics call-statistics";
    gap:16px;
    align-items:start;
}
#sale-dashboard-panel > * { width:auto; min-width:0; }
#sale-dashboard-panel > .dashboard-span-full { grid-column:1 / -1; }
#sale-dashboard-panel > .dashboard-column-span-1,
#sale-dashboard-panel > .dashboard-two-up-item { grid-column:span 1; min-width:0; }
.dashboard-column-stack { display:flex; flex-direction:column; gap:16px; align-self:start; }
#dashboard-shortcuts { display:none !important; }
#main-stats { grid-area: stats; grid-template-columns:repeat(4, minmax(0, 1fr)) !important; }
#sale-dashboard-panel > [data-section="approval-center"] { grid-area: approval-center; grid-column:1 / span 1 !important; grid-row:2 !important; }
#sale-dashboard-panel > [data-section="performance-funnel"] { grid-area: performance; }
#sale-dashboard-panel > [data-section="pipeline-funnel"] { grid-area: pipeline; grid-column:2 / span 2 !important; grid-row:3 / span 2 !important; }
#sale-dashboard-panel > [data-section="demand-insights"] { grid-area: demand-insights; grid-column:2 / span 2 !important; grid-row:2 !important; }
#sale-dashboard-panel > [data-section="sales-score-table"] { grid-area: sales-score-table; }
#sale-dashboard-panel > [data-section="sales-user-activity"] { grid-area: sales-user-activity; grid-column:1 / -1 !important; }
#sale-dashboard-panel > [data-section="user-pipeline"] { grid-area: user-pipeline; }
#sale-dashboard-panel > [data-section="sales-performance"] { grid-area: sales-performance; }
#sale-dashboard-panel > [data-section="user-visits-meetings"] { grid-area: user-visits-meetings; }
#sale-dashboard-panel > [data-section="call-statistics"] { grid-area: call-statistics; }
#sale-dashboard-panel > [data-section="team-overview"],
#sale-dashboard-panel > [data-section="recent-activities"],
#sale-dashboard-panel > [data-section="recent-leads"] { display:none !important; }
@media (min-width: 821px) {
    #sale-dashboard-panel > [data-section="demand-insights"] {
        display: block !important;
        margin-bottom:0;
    }
    #sale-dashboard-panel > [data-section="demand-insights"] .demand-insights-grid {
        display:grid !important;
        grid-template-columns:repeat(2, minmax(0, 1fr)) !important;
        gap:14px;
    }
    #sale-dashboard-panel > [data-section="demand-insights"] > .section-title {
        display: none !important;
    }
    #sale-dashboard-panel > [data-section="demand-insights"] .demand-insight-card {
        min-width: 0;
        padding: 14px;
        height: 100%;
    }
    #sale-dashboard-panel > [data-section="demand-insights"] .demand-insight-card:nth-child(1) {
        grid-column:auto !important;
        grid-row:auto !important;
    }
    #sale-dashboard-panel > [data-section="demand-insights"] .demand-insight-card:nth-child(2) {
        grid-column:auto !important;
        grid-row:auto !important;
    }
    #sale-dashboard-panel > [data-section="demand-insights"] .demand-insight-chart {
        height: 150px;
    }
    #sale-dashboard-panel > [data-section="demand-insights"] .demand-insight-head {
        margin-bottom: 8px;
    }
    #sale-dashboard-panel > [data-section="demand-insights"] .demand-insight-total strong {
        font-size: 20px;
    }
    #sale-dashboard-panel > [data-section="approval-center"] {
        min-height: 240px;
    }
    #sale-dashboard-panel > [data-section="approval-center"] .approval-center-list {
        min-height: 132px;
        max-height: 210px;
    }
    #sale-dashboard-panel > [data-section="approval-center"] .approval-empty {
        padding: 36px 14px;
    }
}
@media (min-width: 768px) {
    #sale-dashboard-panel > [data-section="user-pipeline"],
    #sale-dashboard-panel > [data-section="sales-performance"] {
        display: none !important;
    }
}
.premium-filter-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
.premium-filter-label { font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #7a7a73; }
.dashboard-customize-btn { display:inline-flex; align-items:center; gap:8px; border:none; border-radius:12px; padding:9px 14px; background:#111827; color:#fff; font-size:12px; font-weight:700; cursor:pointer; box-shadow:0 8px 18px rgba(17,24,39,.16); }
.dashboard-customize-btn:hover { background:#0f172a; }
.section-filter-custom-range { display:none; align-items:end; gap:8px; grid-column:1 / -1; flex-wrap:wrap; }
.section-filter-custom-range.is-open { display:flex; }
.section-filter-custom-range label { display:flex; flex-direction:column; gap:4px; min-width:130px; }
.section-filter-custom-range label span { font-size:10px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#7a7a73; }
.section-filter-custom-range input { border:1px solid #d8d8d2; border-radius:10px; padding:8px 10px; background:#fff; color:#16161a; font-size:12px; }
.section-filter-custom-range .section-filter-btn.apply { min-width:88px; }
.dashboard-section.is-section-loading { opacity:.58; pointer-events:none; transition:opacity .18s ease; }
.dashboard-section.is-section-loading::after {
    content:'Updating...';
    position:absolute;
    top:14px;
    right:16px;
    padding:6px 10px;
    border-radius:999px;
    background:rgba(17,24,39,.82);
    color:#fff;
    font-size:11px;
    font-weight:700;
    letter-spacing:.04em;
}
.dashboard-section[data-section="pipeline-funnel"] { position:relative; }
.dashboard-section[data-section="pipeline-funnel"] .section-card-header {
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:16px;
    flex-wrap:wrap;
}
.pipeline-filter-toggle,
.pipeline-filter-trigger { display:none; }
.dashboard-section[data-section="pipeline-funnel"] .section-title {
    flex:1 1 220px;
}
.dashboard-section[data-section="pipeline-funnel"] .section-filter-group {
    display:none;
    position:absolute;
    top:46px;
    right:0;
    width:min(100vw - 48px, 420px);
    padding:12px;
    border:1px solid #e7dccb;
    border-radius:16px;
    background:#fffdf9;
    box-shadow:0 18px 40px rgba(15,23,42,.12);
    z-index:5;
}
.dashboard-section[data-section="pipeline-funnel"] .section-filter-group.is-open { display:block; }
.dashboard-section[data-section="pipeline-funnel"] .section-filter-shell { position:relative; display:flex; align-items:center; gap:8px; max-width:100%; min-width:0; overflow:visible; }
.dashboard-section[data-section="pipeline-funnel"] .section-filter-summary { display:inline-flex; align-items:center; padding:6px 10px; border-radius:999px; background:#fff; border:1px solid #eadfce; font-size:11px; font-weight:700; color:#5b6472; white-space:nowrap; max-width:calc(100% - 50px); overflow:hidden; text-overflow:ellipsis; flex:1 1 auto; min-width:0; }
.dashboard-section[data-section="pipeline-funnel"] .section-filter-btn {
    min-width:72px;
}
.pipeline-filter-trigger { display:inline-flex; align-items:center; justify-content:center; width:38px; min-width:38px; height:38px; border-radius:12px; border:1px solid #eadfce; background:#fff; color:#374151; cursor:pointer; box-shadow:0 6px 16px rgba(15,23,42,.06); flex:0 0 38px; }
.pipeline-filter-trigger:hover { border-color:#d9cdb9; background:#fcfaf6; }
.pipeline-filter-panel-head { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:10px; }
.pipeline-filter-panel-title { font-size:12px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; color:#6b7280; }
.pipeline-filter-close { border:none; background:transparent; color:#6b7280; cursor:pointer; font-size:13px; }
.pipeline-filter-options { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.pipeline-custom-range {
    display:flex;
    align-items:center;
    gap:8px;
    flex-wrap:wrap;
    margin-top:10px;
    padding-top:10px;
    border-top:1px solid #ebe7de;
}
.premium-grid-2, .premium-grid-3, .premium-grid-4 { display: grid; gap: 14px; }
.premium-grid-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.premium-grid-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.premium-grid-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
.marketing-stat { border: 1px solid #e2e1dc; border-radius: 16px; background: #fff; padding: 18px; box-shadow: 0 1px 3px rgba(0,0,0,.03); }
.marketing-stat .value { font-size: 30px; font-weight: 800; line-height: 1; color: #16161A; }
.marketing-stat .label { margin-top: 6px; font-size: 11px; color: #7a7a73; text-transform: uppercase; letter-spacing: .08em; font-weight: 700; }
.marketing-stat .sub { margin-top: 8px; font-size: 12px; color: #5e5e5a; }
.marketing-list { display: flex; flex-direction: column; gap: 10px; }
.marketing-list-item { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 12px 14px; border: 1px solid #edece7; border-radius: 12px; background: #fafaf8; }
.marketing-list-item strong { font-size: 13px; color: #16161A; }
.marketing-list-item span { font-size: 12px; color: #5e5e5a; }
.meta-decision-grid { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:12px; margin-bottom:14px; }
.meta-decision-card { border:1px solid #e4ece7; border-radius:14px; background:#f8fbf9; padding:14px; min-width:0; }
.meta-decision-card .value { font-size:22px; line-height:1; font-weight:900; color:#0b5b39; white-space:nowrap; }
.meta-decision-card .label { margin-top:7px; font-size:10px; color:#64746d; font-weight:900; text-transform:uppercase; letter-spacing:.08em; }
.meta-decision-card .note { margin-top:5px; font-size:11px; color:#7a7a73; }
.meta-funnel-grid { display:grid; grid-template-columns:minmax(240px, 300px) minmax(0, 1fr); gap:14px; margin:14px 0; align-items:stretch; }
.meta-funnel-card { border:1px solid #cfded5; border-radius:0; background:#fff; padding:14px; min-width:0; }
.meta-funnel-card strong { display:block; font-size:24px; line-height:1; font-weight:900; color:#052e23; }
.meta-funnel-card span { display:block; margin-top:6px; font-size:10px; color:#496257; font-weight:900; text-transform:uppercase; letter-spacing:.08em; }
.meta-funnel-card small { display:block; margin-top:8px; color:#64748b; font-size:11px; line-height:1.35; }
.meta-funnel-mix { border:1px solid #cfded5; border-radius:0; background:#fff; overflow:hidden; min-width:0; }
.meta-funnel-mix-head { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:10px 12px; background:#eaf4ee; border-bottom:1px solid #abc7b5; }
.meta-funnel-mix-head strong { font-size:11px; color:#183c2a; text-transform:uppercase; letter-spacing:.08em; font-weight:900; }
.meta-funnel-mix-head span { font-size:11px; color:#64748b; font-weight:800; }
.meta-funnel-stack { display:flex; height:16px; overflow:hidden; background:#edf4ef; border-bottom:1px solid #d6e2da; }
.meta-funnel-stack i { display:block; min-width:2px; height:100%; }
.meta-funnel-stack i.empty { min-width:0; width:100%; background:#edf4ef; }
.meta-funnel-stack i.qualified { background:#059669; }
.meta-funnel-stack i.closed { background:#0f6b35; }
.meta-funnel-stack i.hold { background:#f59e0b; }
.meta-funnel-stack i.bad { background:#ef4444; }
.meta-funnel-stack i.pending { background:#3b82f6; }
.meta-funnel-table { width:100%; border-collapse:collapse; font-size:12px; }
.meta-funnel-table th, .meta-funnel-table td { padding:9px 12px; border-bottom:1px solid #e1e7ef; text-align:left; }
.meta-funnel-table th { background:#f7faf8; color:#183c2a; font-size:10px; text-transform:uppercase; letter-spacing:.06em; font-weight:900; }
.meta-funnel-table td { color:#0f172a; }
.meta-funnel-table tr:last-child td { border-bottom:none; }
.meta-funnel-count { display:inline-flex; min-width:36px; justify-content:center; padding:3px 8px; border:1px solid #cfded5; background:#f7faf8; font-weight:900; color:#052e23; }
.meta-funnel-count.qualified { color:#006b3a; background:#e7f7ee; border-color:#a7dec0; }
.meta-funnel-count.closed { color:#065f46; background:#dff8ed; border-color:#99dfbf; }
.meta-funnel-count.hold { color:#92400e; background:#fff5d7; border-color:#f4c96a; }
.meta-funnel-count.bad { color:#b91c1c; background:#fee2e2; border-color:#fca5a5; }
.meta-funnel-count.pending { color:#1d4ed8; background:#dbeafe; border-color:#93c5fd; }
.meta-funnel-dot { display:inline-block; width:8px; height:8px; margin-right:7px; background:#64748b; }
.meta-funnel-dot.qualified { background:#059669; }
.meta-funnel-dot.closed { background:#0f6b35; }
.meta-funnel-dot.hold { background:#f59e0b; }
.meta-funnel-dot.bad { background:#ef4444; }
.meta-funnel-dot.pending { background:#3b82f6; }
.meta-alert-list { display:grid; gap:8px; margin-top:14px; }
.meta-alert-row { border:1px solid #e5e7eb; border-radius:12px; padding:10px 12px; background:#f8fbf9; }
.meta-alert-row.warning { background:#fffbeb; border-color:#fde68a; }
.meta-alert-row.danger { background:#fff7f7; border-color:#fecaca; }
.meta-alert-row strong { display:block; font-size:13px; color:#123127; }
.meta-alert-row span { display:block; margin-top:3px; font-size:12px; color:#64748b; }
.meta-detail-grid { display:grid; grid-template-columns:1fr; gap:14px; margin-top:16px; }
.meta-detail-head { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:8px; }
.meta-detail-head strong { font-size:13px; color:#052e23; text-transform:uppercase; letter-spacing:.03em; }
.meta-detail-head span { font-size:11px; color:#64748b; font-weight:700; }
.source-performance-grid { display:grid; grid-template-columns: minmax(260px, 320px) minmax(0, 1fr); gap:14px; }
.source-performance-panel { border:1px solid #edece7; border-radius:14px; background:linear-gradient(180deg,#fff,#fbfbf9); padding:14px; }
.source-performance-highlights { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:10px; margin-top:12px; }
.source-performance-kpi { border:1px solid #eceaf8; border-radius:12px; background:#f7faf8; padding:12px; }
.source-performance-kpi .value { font-size:22px; line-height:1; font-weight:800; color:#0b6b4f; }
.source-performance-kpi .label { margin-top:6px; font-size:10px; letter-spacing:.08em; text-transform:uppercase; color:#7a7a73; font-weight:700; }
.source-performance-table-wrap { overflow-x:auto; border:1px solid #cfded5; border-radius:0; background:#fff; }
.source-performance-table { width:100%; border-collapse:separate; border-spacing:0; min-width:1180px; font-size:12px; line-height:1.35; }
.source-performance-table th, .source-performance-table td { padding:9px 10px; border-right:1px solid #e1e7ef; border-bottom:1px solid #e1e7ef; vertical-align:middle; }
.source-performance-table th { background:#eaf4ee; font-size:10px; text-transform:uppercase; letter-spacing:.06em; color:#183c2a; font-weight:900; white-space:nowrap; text-align:center; border-bottom-color:#abc7b5; }
.source-performance-table td { font-size:12px; color:#0f172a; text-align:center; background:#fff; }
.source-performance-table th:first-child,
.source-performance-table td:first-child { text-align:left; }
.source-performance-table tr:last-child td { border-bottom:none; }
.source-performance-table tr:hover td { background:#f7fbf8; }
.source-performance-table .source-performance-sticky { position:sticky; left:0; z-index:1; background:#fff; box-shadow:5px 0 0 rgba(207,216,227,.45); }
.source-performance-table thead .source-performance-sticky { background:#eaf4ee; z-index:3; }
.source-performance-table .source-performance-group-raw { background:#fff3df; color:#7c3d00; }
.source-performance-table .source-performance-group-pipe { background:#e6f3eb; color:#14532d; }
.source-performance-source { display:block; font-weight:850; color:#0f172a; letter-spacing:0; }
.source-performance-sub { margin-top:4px; font-size:11px; color:#718096; }
.source-performance-rate { display:inline-flex; min-width:58px; justify-content:center; padding:4px 9px; border-radius:7px; background:#eef6f2; color:#0b5b39; font-size:11px; font-weight:850; }
.source-performance-rate.warning { background:#fff5eb; color:#b45309; }
.source-performance-money { font-weight:850; color:#0f172a; white-space:nowrap; }
.source-performance-money.muted { color:#9aa6b2; font-weight:800; }
.source-performance-muted { margin-top:4px; font-size:10px; color:#8b98a5; font-weight:700; }
.source-metric-stack { display:grid; gap:4px; justify-items:center; }
.source-metric-primary { font-size:14px; font-weight:900; color:#0f172a; white-space:nowrap; }
.source-metric-note { font-size:10px; line-height:1.25; color:#718096; font-weight:750; }
.source-quality-cell { display:flex; flex-direction:column; align-items:center; gap:4px; }
.source-quality-score { font-size:15px; font-weight:900; color:#111827; white-space:nowrap; }
.source-quality-score.muted { color:#94a3b8; font-size:12px; }
.source-quality-verdict { display:inline-flex; min-width:94px; justify-content:center; padding:5px 9px; border-radius:3px; background:#f1f5f9; color:#475569; font-size:10px; font-weight:900; white-space:nowrap; border:1px solid transparent; }
.source-quality-verdict.excellent { background:#e9f7ef; color:#166534; }
.source-quality-verdict.good { background:#eef8f3; color:#047857; }
.source-quality-verdict.average { background:#fff8e8; color:#854d0e; }
.source-quality-verdict.weak,
.source-quality-verdict.poor { background:#fff0f0; color:#b91c1c; }
.source-quality-verdict.needs-more-data { background:#f1f5f9; color:#64748b; }
.source-outcome-count { display:inline-flex; align-items:center; justify-content:center; min-width:34px; height:23px; border-radius:3px; padding:0 8px; font-size:11px; font-weight:900; border:1px solid transparent; text-decoration:none; }
.source-outcome-count.good { background:#e8f5e9; color:#0f6b35; border-color:#bfe3ca; }
.source-outcome-count.bad { background:#fde7e9; color:#b4232e; border-color:#f5c2c7; }
.source-outcome-count.warn { background:#fff4ce; color:#8a5600; border-color:#f4d781; }
.source-outcome-count.info { background:#e7f0ff; color:#1959b3; border-color:#c1d8ff; }
.source-outcome-count.neutral { background:#edf2f7; color:#111827; border-color:#d9e1ea; }
.source-outcome-count:hover { filter:brightness(.98); transform:translateY(-1px); }
.source-score-mix { display:grid; grid-template-columns:repeat(6, minmax(42px, 1fr)); gap:5px; min-width:310px; }
.source-score-mix span { display:flex; align-items:center; justify-content:space-between; gap:5px; padding:5px 7px; border:1px solid #e4ebe6; border-radius:7px; background:#f8faf9; color:#334155; font-size:10px; font-weight:850; }
.source-score-mix b { font-size:10px; color:#697771; font-weight:900; }
.source-score-mix .q5 { border-color:#cfe8d8; background:#f3fbf6; }
.source-score-mix .q4 { border-color:#d8ecdf; background:#f6fbf8; }
.source-score-mix .q3 { border-color:#f3e7bd; background:#fffaf0; }
.source-score-mix .q2 { border-color:#d7e8f3; background:#f5fbff; }
.source-score-mix .q1 { border-color:#f1d2d2; background:#fff7f7; }
.source-score-mix .hold { border-color:#ddd8ee; background:#f7f5ff; }
.source-quality-summary { display:grid; gap:3px; justify-items:center; min-width:130px; }
.source-outcome-grid { display:grid; grid-template-columns:repeat(7, minmax(54px, 1fr)); gap:6px; min-width:430px; }
.source-outcome-grid span,
.source-outcome-grid a { border:1px solid #e4ebe6; border-radius:7px; background:#f8faf9; padding:6px 7px; text-align:center; text-decoration:none; display:block; color:inherit; transition:all .16s ease; }
.source-outcome-grid a:hover { border-color:#9ccbb8; background:#f1faf6; box-shadow:0 6px 14px rgba(15, 91, 66, .1); transform:translateY(-1px); }
.source-outcome-grid b { display:block; font-size:13px; color:#0f172a; }
.source-outcome-grid small { display:block; margin-top:2px; font-size:9px; color:#64748b; font-weight:900; text-transform:uppercase; }
.meta-sync-btn { border:1px solid #0b6b4f; background:#0b6b4f; color:#fff; }
.meta-sync-btn:disabled { opacity:.65; cursor:not-allowed; transform:none; }
.meta-sync-status { font-size:11px; color:#64748b; font-weight:800; min-width:120px; text-align:right; }
.meta-sync-status.success { color:#047857; }
.meta-sync-status.error { color:#b91c1c; }
.demand-insights-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:12px; }
.demand-insight-card { position:relative; border:1px solid #dce7df; border-radius:8px; background:#fff; padding:12px; min-width:0; box-shadow:none; }
.demand-insight-head { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-bottom:10px; padding-bottom:10px; border-bottom:1px solid #dce7df; }
.demand-insight-title { font-size:13px; line-height:1.2; font-weight:850; color:#123c2c; }
.demand-insight-copy { margin-top:4px; font-size:11px; line-height:1.35; color:#6b7a70; }
.demand-chart-toggle { display:inline-flex; gap:2px; padding:2px; border:1px solid #dce7df; border-radius:7px; background:#f3f7f4; margin-top:8px; }
.demand-chart-toggle button { border:none; border-radius:5px; padding:5px 9px; background:transparent; color:#64756c; font-size:10px; font-weight:850; cursor:pointer; }
.demand-chart-toggle button.active { background:#176442; color:#fff; box-shadow:none; }
.demand-insight-total { text-align:center; min-width:54px; padding:7px 8px; border:1px solid #dce7df; border-radius:7px; background:#f5faf6; }
.demand-insight-total strong { display:block; font-size:22px; line-height:1; color:#0b6b4f; }
.demand-insight-total span { display:block; margin-top:4px; font-size:9px; letter-spacing:.08em; text-transform:uppercase; color:#7a7a73; font-weight:800; }
.demand-insight-actions { display:flex; align-items:center; gap:7px; }
.compact-card-filter-trigger { display:inline-flex; align-items:center; justify-content:center; width:34px; height:34px; flex:0 0 34px; border:1px solid #dce7df; border-radius:7px; background:#fff; color:#345246; cursor:pointer; box-shadow:none; }
.compact-card-filter-trigger:hover { border-color:#0b6b4f; color:#0b6b4f; background:#f7fbf9; }
.demand-filter-panel { display:none; position:absolute; top:66px; right:12px; z-index:20; width:min(390px, calc(100vw - 48px)); padding:12px; border:1px solid #e2e8e4; border-radius:14px; background:#fff; box-shadow:0 18px 40px rgba(15,23,42,.14); }
.demand-filter-panel.is-open { display:block; }
.demand-filter-panel-head { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:10px; padding-bottom:9px; border-bottom:1px solid #edf1ee; }
.demand-filter-panel-title { font-size:11px; font-weight:900; letter-spacing:.08em; text-transform:uppercase; color:#59645f; }
.demand-filter-panel-close { border:0; background:transparent; color:#64748b; cursor:pointer; }
.demand-filter-options { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:7px; }
.demand-filter-options .section-filter-btn { width:100%; min-width:0; }
.demand-custom-range { display:none; grid-template-columns:1fr 1fr auto; gap:7px; align-items:end; margin-top:10px; padding-top:10px; border-top:1px solid #edf1ee; }
.demand-custom-range.is-open { display:grid; }
.demand-custom-range label { display:grid; gap:4px; }
.demand-custom-range label span { font-size:9px; font-weight:850; text-transform:uppercase; color:#64748b; }
.demand-custom-range input { min-width:0; width:100%; border:1px solid #dfe7e2; border-radius:9px; padding:7px 8px; font-size:11px; color:#334155; }
.demand-insight-chart { height:164px; }
.demand-insight-list {
    display:grid;
    gap:0;
    margin-top:10px;
    border:1px solid #dce7df;
    border-radius:7px;
    overflow:hidden;
    background:#fff;
}
.demand-insight-table-head {
    display:grid;
    grid-template-columns:minmax(0, 1fr) 58px 58px;
    gap:8px;
    padding:7px 9px;
    background:#f3f7f4;
    border-bottom:1px solid #dce7df;
    color:#547061;
    font-size:9px;
    font-weight:900;
    letter-spacing:.06em;
    text-transform:uppercase;
}
.demand-insight-table-head span:nth-child(n+2) { text-align:right; }
.demand-insight-row {
    display:grid;
    grid-template-columns:minmax(0, 1fr) 58px 58px;
    gap:8px;
    align-items:center;
    min-height:34px;
    font-size:11.5px;
    color:#2d493d;
    padding:7px 9px;
    border-bottom:1px solid #e8eee9;
}
.demand-insight-row:last-child { border-bottom:none; }
.demand-insight-row.is-clickable { cursor:pointer; transition:.16s ease; }
.demand-insight-row.is-clickable:hover { background:#f3f8f4; color:#123c2c; }
.demand-insight-row .label { font-weight:750; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.demand-insight-row .count { font-weight:850; color:#123c2c; text-align:right; font-variant-numeric:tabular-nums; }
.demand-insight-row .percent { min-width:0; text-align:right; color:#6b7a70; font-weight:800; font-variant-numeric:tabular-nums; }
.approval-center-card { position:relative; overflow:visible; padding:0; border-radius:16px; box-shadow:0 1px 2px rgba(15,23,42,.035); }
.approval-center-head { display:flex; flex-direction:column; align-items:stretch; gap:12px; padding:16px 16px 12px; border-bottom:1px solid #e8ece6; }
.approval-center-title-row { display:flex; align-items:center; justify-content:space-between; gap:12px; }
.approval-center-eyebrow { font-size:11px; font-weight:800; letter-spacing:.14em; text-transform:uppercase; color:#9aa19b; }
.approval-center-title { margin-top:3px; font-size:21px; line-height:1.05; font-weight:850; color:#183028; white-space:nowrap; }
.approval-center-filters { display:none; position:absolute; top:62px; right:14px; z-index:20; grid-template-columns:repeat(3, minmax(0, 1fr)); align-items:center; gap:7px; width:min(330px, calc(100% - 28px)); max-width:none; padding:10px; border:1px solid #dfe7e2; border-radius:12px; background:#fff; box-shadow:0 16px 34px rgba(15,23,42,.13); }
.approval-center-filters.is-open { display:grid; }
.approval-filter-btn { width:100%; border:1px solid #dfe7e2; background:#f8fbf9; color:#43504a; border-radius:9px; padding:7px 8px; font-size:10.5px; font-weight:850; cursor:pointer; transition:all .18s ease; white-space:nowrap; }
.approval-filter-btn:hover { border-color:#0b6b4f; color:#0b6b4f; background:#fff; }
.approval-filter-btn.active { border-color:#0b6b4f; background:#0b6b4f; color:#fff; box-shadow:0 10px 18px rgba(11,107,79,.14); }
.approval-center-list { display:flex; flex-direction:column; gap:10px; padding:12px; max-height:420px; overflow:auto; }
.approval-center-row { display:grid; grid-template-columns:44px minmax(0, 1fr); gap:12px; align-items:start; padding:14px; border:1px solid #edf1ec; border-radius:16px; background:linear-gradient(180deg,#fff,#fbfcfa); box-shadow:0 6px 14px rgba(15,23,42,.035); }
.approval-center-row:last-child { border-bottom:1px solid #edf1ec; }
.approval-icon { width:44px; height:44px; border-radius:15px; display:flex; align-items:center; justify-content:center; font-size:15px; font-weight:900; color:#fff; background:#0b6b4f; grid-row:1 / span 2; margin-top:2px; }
.approval-icon.finance { background:#0b6b4f; }
.approval-icon.hr { background:#2563eb; }
.approval-icon.leave { background:#b77900; }
.approval-icon.po { background:#6d28d9; }
.approval-icon.export { background:#0f766e; }
.approval-row-title { display:flex; align-items:center; gap:8px; flex-wrap:wrap; font-size:16px; line-height:1.2; font-weight:800; color:#183028; }
.approval-badge { display:inline-flex; align-items:center; border-radius:999px; padding:4px 8px; background:#fff4dc; color:#94610b; font-size:10px; font-weight:900; letter-spacing:.04em; }
.approval-summary { margin-top:6px; color:#68756f; font-size:12.5px; line-height:1.45; font-weight:650; word-break:normal; }
.approval-age { grid-column:2; white-space:nowrap; color:#7d8781; font-size:12px; font-weight:800; align-self:center; }
.approval-actions { grid-column:1 / -1; display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:8px; align-items:center; }
.approval-action-btn { border:1px solid #dfe7e2; border-radius:999px; background:#fff; color:#0a3d2f; padding:10px 8px; font-size:12px; line-height:1; font-weight:900; cursor:pointer; text-decoration:none; transition:all .18s ease; text-align:center; }
.approval-action-btn:hover { transform:translateY(-1px); box-shadow:0 8px 16px rgba(15,23,42,.08); }
.approval-action-btn.approve { background:#0b6b4f; border-color:#0b6b4f; color:#fff; }
.approval-action-btn.reject { background:#fff5f5; border-color:#fecaca; color:#b91c1c; }
.approval-empty { padding:28px 22px; text-align:center; color:#7d8781; font-size:13px; font-weight:700; }
.approval-modal-backdrop { position:fixed; inset:0; z-index:9999; display:none; align-items:center; justify-content:center; padding:18px; background:rgba(15,23,42,.46); backdrop-filter:blur(7px); }
.approval-modal-backdrop.active { display:flex; }
.approval-modal-card { width:min(520px, 100%); border:1px solid rgba(226,232,240,.9); border-radius:24px; background:linear-gradient(180deg,#ffffff,#fbfcfa); box-shadow:0 28px 80px rgba(15,23,42,.28); overflow:hidden; animation:approvalModalIn .18s ease-out; }
.approval-view-card { width:min(760px, 100%); max-height:min(86vh, 780px); display:flex; flex-direction:column; }
.approval-modal-head { display:flex; align-items:flex-start; gap:14px; padding:20px 22px 16px; border-bottom:1px solid #edf1ec; }
.approval-modal-icon { width:46px; height:46px; border-radius:16px; display:flex; align-items:center; justify-content:center; background:#fff5f5; color:#b91c1c; font-weight:900; }
.approval-modal-icon.approval-icon { grid-row:auto; margin-top:0; color:#fff; }
.approval-modal-title { font-size:20px; line-height:1.2; font-weight:900; color:#17251f; }
.approval-modal-copy { margin-top:6px; font-size:13px; line-height:1.45; color:#66736d; font-weight:650; }
.approval-modal-body { padding:18px 22px 20px; }
.approval-view-card .approval-modal-body { overflow:auto; }
.approval-modal-label { display:block; margin-bottom:8px; font-size:12px; font-weight:900; letter-spacing:.08em; text-transform:uppercase; color:#68756f; }
.approval-modal-textarea { width:100%; min-height:120px; resize:vertical; border:1px solid #dbe5df; border-radius:16px; padding:13px 14px; color:#17251f; font-size:14px; line-height:1.45; outline:none; background:#fff; }
.approval-modal-textarea:focus { border-color:#0b6b4f; box-shadow:0 0 0 4px rgba(11,107,79,.12); }
.approval-modal-error { display:none; margin-top:8px; color:#b91c1c; font-size:12px; font-weight:800; }
.approval-modal-error.active { display:block; }
.approval-modal-actions { display:flex; justify-content:flex-end; gap:10px; padding:0 22px 22px; }
.approval-modal-btn { border:1px solid #dfe7e2; border-radius:999px; padding:11px 18px; background:#fff; color:#1f3029; font-size:13px; font-weight:900; cursor:pointer; }
.approval-modal-btn.primary { border-color:#b91c1c; background:#dc2626; color:#fff; box-shadow:0 12px 24px rgba(220,38,38,.18); }
.approval-modal-btn.approve { border-color:#0b6b4f; background:#0b6b4f; color:#fff; box-shadow:0 12px 24px rgba(11,107,79,.18); }
.approval-modal-btn:disabled { opacity:.58; cursor:not-allowed; }
.approval-detail-grid { display:grid; gap:10px; }
.approval-detail-grid.expanded { grid-template-columns:repeat(2, minmax(0, 1fr)); }
.approval-detail-box { border:1px solid #edf1ec; border-radius:16px; background:#f8fbf9; padding:13px 14px; }
.approval-detail-box.full { grid-column:1 / -1; }
.approval-detail-label { font-size:10px; font-weight:900; letter-spacing:.12em; text-transform:uppercase; color:#8a958f; }
.approval-detail-value { margin-top:5px; color:#1b2d25; font-size:14px; line-height:1.45; font-weight:750; }
@media (max-width:640px) { .approval-detail-grid.expanded { grid-template-columns:1fr; } }
@keyframes approvalModalIn { from { opacity:0; transform:translateY(10px) scale(.98); } to { opacity:1; transform:translateY(0) scale(1); } }
.marketing-score-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.marketing-score-box { border: 1px solid #eceaf8; border-radius: 14px; background: linear-gradient(135deg, #ffffff, #f8f7ff); padding: 16px; }
.marketing-score-box .score { font-size: 28px; line-height: 1; font-weight: 800; color: #5946c0; }
.marketing-score-box .name { margin-top: 6px; font-size: 11px; letter-spacing: .08em; text-transform: uppercase; color: #7a7a73; font-weight: 700; }
.marketing-score-box .note { margin-top: 8px; font-size: 12px; color: #5e5e5a; }
.finance-summary-grid { display:grid; grid-template-columns:repeat(6, minmax(0, 1fr)); gap:12px; margin-bottom:16px; }
.finance-kpi-card { border:1px solid #e7e5df; border-radius:16px; background:linear-gradient(180deg,#fff,#fafaf8); padding:16px; box-shadow:0 8px 20px rgba(15,23,42,.035); min-width:0; }
.finance-kpi-label { font-size:11px; font-weight:700; letter-spacing:.18em; text-transform:uppercase; color:#64748b; }
.finance-kpi-value { margin-top:10px; font-size:24px; line-height:1.05; font-weight:800; color:#0f172a; overflow-wrap:anywhere; }
.finance-kpi-note { margin-top:9px; font-size:12px; line-height:1.35; color:#64748b; }
.finance-action-strip { display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:16px; }
.finance-action-link { display:inline-flex; align-items:center; justify-content:center; min-height:42px; padding:10px 18px; border-radius:12px; border:1px solid #e5ded4; background:#fff; color:#0b6b4f; font-size:14px; font-weight:700; text-decoration:none; transition:all .18s ease; }
.finance-action-link:hover { transform:translateY(-1px); box-shadow:0 10px 20px rgba(15,23,42,.06); }
.finance-action-link.primary { background:#0b6b4f; border-color:#0b6b4f; color:#fff; }
.finance-detail-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:16px; }
.finance-wide { grid-column:1 / -1; }
.finance-pending-grid { display:grid; grid-template-columns:minmax(220px, .9fr) minmax(0, 1.1fr); gap:14px; align-items:stretch; }
.finance-pending-hero { border:1px solid #fde68a; border-radius:16px; background:linear-gradient(180deg,#fffbeb,#fff); padding:16px; }
.finance-pending-value { font-size:34px; line-height:1; font-weight:900; color:#92400e; }
.finance-pending-label { margin-top:8px; font-size:11px; font-weight:900; letter-spacing:.1em; text-transform:uppercase; color:#a16207; }
.finance-pending-note { margin-top:10px; color:#6b7280; font-size:13px; line-height:1.4; }
.finance-pending-list { display:grid; gap:10px; }
.finance-pending-item { display:flex; justify-content:space-between; align-items:center; gap:12px; border:1px solid #ece8df; border-radius:14px; background:#fff; padding:12px 14px; }
.finance-pending-item span { font-size:12px; font-weight:800; color:#64748b; }
.finance-pending-item strong { font-size:14px; font-weight:900; color:#0f172a; text-align:right; }
.finance-status-list,
.finance-recent-list { display:flex; flex-direction:column; gap:12px; }
.finance-status-row,
.finance-recent-row { display:flex; align-items:center; justify-content:space-between; gap:12px; }
.finance-status-row { font-size:14px; color:#334155; }
.finance-status-row strong,
.finance-recent-row strong { font-size:15px; color:#0f172a; font-weight:800; }
.finance-status-row span,
.finance-recent-row span { color:#0f172a; font-weight:600; }
.finance-recent-row { padding-bottom:12px; border-bottom:1px solid #ece8df; }
.finance-recent-row:last-child { padding-bottom:0; border-bottom:none; }
.finance-recent-meta { display:flex; flex-direction:column; gap:4px; min-width:0; }
.finance-recent-title { font-size:14px; font-weight:700; color:#0f172a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.finance-recent-subtitle { font-size:12px; color:#64748b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.finance-empty-state { font-size:13px; color:#94a3b8; }
.finance-category-list { display:grid; gap:11px; }
.finance-category-row { display:grid; gap:7px; }
.finance-category-head { display:flex; justify-content:space-between; align-items:center; gap:12px; font-size:13px; }
.finance-category-head strong { color:#0f172a; font-weight:850; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.finance-category-head span { color:#475569; font-weight:800; white-space:nowrap; }
.finance-category-track { height:10px; border-radius:999px; overflow:hidden; background:#edf2ef; }
.finance-category-fill { height:100%; border-radius:999px; background:linear-gradient(135deg,#0b6b4f,#14b8a6); min-width:4px; }
.finance-comparison-card { border:1px solid #e2e8f0; border-radius:16px; background:#fff; padding:16px; }
.finance-comparison-value { font-size:30px; line-height:1; font-weight:900; color:#0f172a; }
.finance-comparison-value.up { color:#b45309; }
.finance-comparison-value.down { color:#0b6b4f; }
.finance-comparison-note { margin-top:10px; color:#64748b; font-size:13px; line-height:1.45; }
.finance-table-wrap { overflow-x:auto; border:1px solid #ece8df; border-radius:16px; background:#fff; }
.finance-table { width:100%; border-collapse:collapse; min-width:780px; }
.finance-table th, .finance-table td { padding:11px 12px; border-bottom:1px solid #edf0eb; font-size:13px; text-align:left; }
.finance-table th { background:#f8faf8; color:#64748b; font-size:11px; text-transform:uppercase; letter-spacing:.08em; font-weight:900; }
.finance-table tr:last-child td { border-bottom:none; }
.finance-table-amount { text-align:right !important; font-weight:900; color:#0f172a; white-space:nowrap; }
.finance-status-pill { display:inline-flex; align-items:center; justify-content:center; border-radius:999px; padding:5px 9px; font-size:11px; font-weight:900; text-transform:uppercase; letter-spacing:.04em; }
.finance-status-pill.draft { background:#fff7ed; color:#b45309; }
.finance-status-pill.approved { background:#ecfdf5; color:#047857; }
.finance-status-pill.rejected { background:#fef2f2; color:#b91c1c; }
.finance-table-action { color:#0b6b4f; text-decoration:none; font-weight:900; white-space:nowrap; }
.ad-spend-grid { display:grid; grid-template-columns:repeat(6, minmax(0, 1fr)); gap:12px; margin-bottom:16px; }
.ad-spend-card { border:1px solid #dfe7e2; border-radius:16px; background:linear-gradient(180deg,#fff,#fbfcfa); padding:15px; min-width:0; }
.ad-spend-value { font-size:20px; line-height:1.1; font-weight:900; color:#0f766e; overflow-wrap:anywhere; }
.ad-spend-label { margin-top:7px; font-size:10px; letter-spacing:.08em; text-transform:uppercase; color:#64748b; font-weight:900; }
.ad-spend-layout { display:grid; grid-template-columns:minmax(260px, .78fr) minmax(0, 1.22fr); gap:16px; align-items:start; }
.ad-spend-platform-list { display:grid; gap:10px; }
.ad-spend-platform-row { display:grid; grid-template-columns:minmax(80px, 1fr) minmax(88px, auto) minmax(72px, auto) minmax(92px, auto); gap:10px; align-items:center; border:1px solid #edf1ec; border-radius:14px; background:#fff; padding:12px 14px; font-size:13px; }
.ad-spend-platform-row strong { color:#0f172a; font-weight:900; }
.ad-spend-platform-row span { color:#475569; font-weight:800; text-align:right; white-space:nowrap; }
.ad-spend-empty { padding:16px; border:1px dashed #dfe7e2; border-radius:14px; color:#64748b; font-size:13px; background:#fbfcfa; }
.hr-summary-grid { display:grid; grid-template-columns:repeat(6, minmax(0, 1fr)); gap:12px; margin-bottom:22px; }
.hr-kpi-card { border:1px solid #dfe7e2; border-radius:16px; background:linear-gradient(180deg,#fff,#fbfcfa); padding:16px; box-shadow:0 8px 18px rgba(15,23,42,.035); }
.hr-kpi-value { font-size:28px; line-height:1; font-weight:800; color:#0f766e; }
.hr-kpi-label { margin-top:7px; font-size:11px; letter-spacing:.08em; text-transform:uppercase; color:#64748b; font-weight:800; }
.hr-dashboard-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:16px; }
.hr-dashboard-grid .section-card { margin-bottom:0; }
.hr-funnel-list, .hr-list { display:flex; flex-direction:column; gap:10px; }
.hr-funnel-row { display:grid; grid-template-columns:minmax(125px, .7fr) minmax(0, 1fr) 56px; gap:10px; align-items:center; }
.hr-funnel-label { font-size:12px; font-weight:800; color:#334155; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.hr-funnel-track { height:12px; border-radius:999px; background:#e8f1ee; overflow:hidden; }
.hr-funnel-fill { height:100%; min-width:4px; border-radius:999px; background:linear-gradient(135deg,#0f766e,#2563eb); }
.hr-funnel-count { text-align:right; font-size:12px; font-weight:800; color:#0f172a; }
.hr-mini-grid { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:10px; }
.hr-mini-stat { border:1px solid #e8ece6; border-radius:14px; background:#f8fbf9; padding:13px; }
.hr-mini-stat strong { display:block; font-size:24px; line-height:1; color:#0f766e; }
.hr-mini-stat span { display:block; margin-top:7px; font-size:11px; letter-spacing:.06em; text-transform:uppercase; color:#64748b; font-weight:800; }
.hr-list-row { display:flex; align-items:center; justify-content:space-between; gap:12px; border:1px solid #edf1ec; border-radius:14px; background:#fff; padding:12px 14px; }
.hr-list-row strong { font-size:13px; color:#1f2937; }
.hr-list-row span { display:inline-flex; align-items:center; justify-content:center; min-width:34px; height:28px; border-radius:999px; background:#e8f5f2; color:#0f766e; font-size:13px; font-weight:900; }
.hr-alert-row span.danger { background:#fef2f2; color:#b91c1c; }
.hr-alert-row span.warning { background:#fff7ed; color:#b45309; }
.hr-alert-row span.info { background:#eff6ff; color:#1d4ed8; }
.hr-leave-request-list { grid-column:1 / -1; margin-top:4px; display:grid; gap:10px; }
.hr-leave-request-row { display:grid; grid-template-columns:minmax(0, 1fr) auto; gap:12px; align-items:center; border:1px solid #edf1ec; border-radius:14px; background:#fff; padding:12px 14px; }
.hr-leave-request-name { font-size:13px; font-weight:900; color:#0f172a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.hr-leave-request-meta { margin-top:4px; font-size:12px; color:#64748b; line-height:1.35; }
.hr-leave-request-days { display:inline-flex; align-items:center; justify-content:center; min-width:48px; height:30px; border-radius:999px; background:#e8f5f2; color:#0f766e; font-size:12px; font-weight:900; white-space:nowrap; }
.shortcut-grid { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:12px; }
.shortcut-card { display:flex; align-items:center; gap:10px; border:1px solid #e7e5df; border-radius:16px; background:#fff; padding:14px; text-decoration:none; color:#16161A; box-shadow:0 1px 3px rgba(0,0,0,.03); transition:transform .18s ease, box-shadow .18s ease; min-height: 82px; }
.shortcut-card:hover { transform:translateY(-2px); box-shadow:0 10px 24px rgba(0,0,0,.06); }
.shortcut-icon { width:38px; height:38px; border-radius:12px; display:flex; align-items:center; justify-content:center; background:linear-gradient(135deg,#eef6f2,#dff3ea); color:#0b6b4f; }
.shortcut-label { font-size:10px; text-transform:uppercase; letter-spacing:.08em; color:#7a7a73; font-weight:700; }
.shortcut-value { margin-top:3px; font-size:20px; font-weight:800; color:#16161A; line-height:1; }
.score-card-grid { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:10px; }
.score-card { text-align:center; padding:16px 14px; border:1px solid #ece8df; border-radius:14px; background:#fff; }
.score-card .value { font-size:22px; line-height:1; font-weight:800; color:#273142; }
.score-card .label { margin-top:8px; font-size:10px; text-transform:uppercase; letter-spacing:.1em; color:#7a7a73; font-weight:700; }
.score-card .note { margin-top:10px; font-size:12px; color:#676d68; line-height:1.45; }
.weekly-activity-wrap { margin-top:14px; width:100%; max-width:100%; }
.weekly-activity-shell { container-type:inline-size; padding:14px; border:1px solid #dce7df; border-radius:8px; background:#fff; box-shadow:none; }
.weekly-activity-head { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:14px; }
.weekly-activity-title { font-size:16px; line-height:1.2; font-weight:800; color:#13251d; }
.weekly-activity-copy { display:none; }
.weekly-activity-head-meta { display:flex; flex-direction:column; align-items:flex-end; gap:10px; min-width:0; width:min(100%, 430px); max-width:100%; position:relative; overflow:visible; }
.weekly-activity-chip { display:inline-flex; align-items:center; padding:8px 12px; border-radius:10px; background:#f7faf8; border:1px solid #dfe8e2; font-size:11px; font-weight:700; color:#3f5b4e; white-space:nowrap; max-width:calc(100% - 50px); overflow:hidden; text-overflow:ellipsis; flex:1 1 auto; min-width:0; }
.weekly-activity-filter-stack { display:flex; flex-direction:column; align-items:flex-end; gap:10px; width:100%; }
.weekly-activity-filter-row { display:flex; align-items:center; justify-content:flex-end; flex-wrap:nowrap; gap:8px; width:100%; min-width:0; max-width:100%; overflow:visible; }
.weekly-activity-filter-group { display:flex; align-items:center; justify-content:flex-end; flex-wrap:wrap; gap:8px; }
.weekly-activity-filter-btn { border:1px solid #eadfce; background:rgba(255,255,255,.78); color:#6b7280; border-radius:999px; padding:7px 11px; font-size:11px; font-weight:700; line-height:1; cursor:pointer; transition:all .18s ease; }
.weekly-activity-filter-btn:hover { background:#fff; border-color:#d9cdb9; color:#374151; }
.weekly-activity-filter-btn.active { background:#16161A; border-color:#16161A; color:#fff; box-shadow:0 10px 18px rgba(22,22,26,.12); }
.weekly-activity-custom-range { display:flex; align-items:center; justify-content:flex-end; gap:8px; flex-wrap:wrap; width:100%; }
.weekly-activity-date-input { border:1px solid #e7dccb; border-radius:12px; background:#fff; color:#374151; padding:9px 12px; min-width:132px; font-size:12px; font-weight:600; }
.weekly-activity-apply-btn { border:none; border-radius:12px; background:#0b6b4f; color:#fff; padding:9px 14px; font-size:12px; font-weight:700; cursor:pointer; box-shadow:0 10px 18px rgba(11,107,79,.16); }
.weekly-activity-apply-btn:hover { background:#09533e; }
.weekly-activity-filter-trigger { display:inline-flex; align-items:center; justify-content:center; width:38px; min-width:38px; height:38px; border-radius:10px; border:1px solid #dfe8e2; background:#fff; color:#1d4f3b; cursor:pointer; box-shadow:0 4px 12px rgba(15,23,42,.05); flex:0 0 38px; }
.weekly-activity-filter-trigger:hover { border-color:#b9cfc3; background:#f7faf8; }
.weekly-activity-filter-panel { display:none; position:absolute; top:46px; right:0; width:min(100vw - 48px, 360px); padding:12px; border:1px solid #e7dccb; border-radius:16px; background:#fffdf9; box-shadow:0 18px 40px rgba(15,23,42,.12); z-index:5; }
.weekly-activity-filter-panel.is-open { display:block; }
.weekly-activity-filter-panel-head { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:10px; }
.weekly-activity-filter-panel-title { font-size:12px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; color:#6b7280; }
.weekly-activity-filter-close { border:none; background:transparent; color:#6b7280; cursor:pointer; font-size:13px; }
.weekly-activity-filter-panel .weekly-activity-filter-group { justify-content:flex-start; width:100%; }
.weekly-activity-filter-panel .weekly-activity-custom-range { justify-content:flex-start; margin-top:10px; }
.weekly-activity-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:12px; }
.weekly-activity-card { min-width:0; border:1px solid #dce7df; border-radius:8px; background:#fbfdfb; padding:12px; }
.weekly-activity-top { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:14px; }
.weekly-activity-name { display:flex; align-items:center; gap:9px; min-width:0; font-size:15px; font-weight:800; color:#13251d; }
.weekly-activity-icon { width:32px; height:32px; border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:13px; }
.weekly-activity-icon.meetings { background:#e8f4ed; color:#176442; }
.weekly-activity-icon.visits { background:#edf7ef; color:#3c825c; }
.weekly-activity-badge { flex-shrink:0; padding:5px 8px; border-radius:8px; border:1px solid #dfe8e2; background:#fff; font-size:10px; font-weight:800; color:#496258; }
.weekly-activity-stats { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); margin-bottom:14px; border:1px solid #e5e9e6; border-radius:11px; background:#fff; overflow:hidden; }
.weekly-activity-stat { min-width:0; padding:10px 4px; text-align:center; border-right:1px solid #e5e9e6; color:inherit; text-decoration:none; cursor:pointer; transition:background-color .16s ease, box-shadow .16s ease; }
.weekly-activity-stat:last-child { border-right:0; }
.weekly-activity-stat:hover { background:#f3f8f5; }
.weekly-activity-stat:focus-visible { position:relative; z-index:1; outline:2px solid #0b6b4f; outline-offset:-2px; }
.weekly-activity-stat strong { display:block; font-size:20px; line-height:1; margin-bottom:6px; color:#13251d; }
.weekly-activity-stat span { display:block; width:100%; min-height:12px; font-size:9px; line-height:1; font-weight:700; letter-spacing:0; text-transform:uppercase; color:#6d7b74; white-space:nowrap; text-align:center; }
.weekly-activity-stat.completed strong { color:#176442; }
.weekly-activity-stat.pending strong { color:#8a5a18; }
.weekly-activity-progress { display:grid; grid-template-columns:1fr auto; align-items:center; gap:10px; }
.weekly-activity-track { height:6px; border-radius:999px; overflow:hidden; background:#e9efeb; }
.weekly-activity-fill { height:100%; border-radius:999px; }
.weekly-activity-fill.meetings { background:#176442; }
.weekly-activity-fill.visits { background:#3c825c; }
.weekly-activity-percent { font-size:10px; font-weight:700; color:#5d6f66; white-space:nowrap; }
@container (max-width: 460px) {
    .weekly-activity-head { align-items:flex-start; gap:9px; }
    .weekly-activity-head-meta,
    .weekly-activity-filter-stack { width:auto; align-items:flex-end; }
    .weekly-activity-chip { max-width:180px; padding:6px 8px; font-size:10px; }
    .weekly-activity-grid { grid-template-columns:1fr; gap:10px; }
    .weekly-activity-card { padding:11px; }
    .weekly-activity-stat { padding:9px 4px; }
    .weekly-activity-stat span { white-space:normal; font-size:8px; line-height:1.15; min-height:18px; }
    .weekly-activity-progress { grid-template-columns:1fr; gap:6px; }
    .weekly-activity-percent { white-space:normal; }
}
.performance-target-incentive-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:12px; align-items:stretch; }
.performance-target-incentive-grid > .section-card { margin-bottom:0; min-width:0; height:100%; }
.dashboard-column-stack[data-section="performance-funnel"] > .section-card,
#sale-dashboard-panel > [data-section="pipeline-funnel"] { margin-bottom:0; }
#incentive-summary { grid-template-columns:repeat(2, minmax(0, 1fr)); }
.performance-target-incentive-grid .score-card { min-height:92px; padding:14px 10px; }
.team-targets-card { align-self:stretch; display:flex; flex-direction:column; }
.team-targets-card .progress-list { flex:1; }
.team-targets-card .progress-list { gap:14px; }
.team-targets-card .date-filter-btn { margin-top:14px !important; }
.progress-list { display:flex; flex-direction:column; gap:18px; }
.progress-item-head { margin-bottom:8px; }
.progress-track { height:6px; background:#ecebe5; }
.progress-fill { box-shadow:none; }
.funnel-kpi-card,
.funnel-visual,
.funnel-stage-panel,
.funnel-loss-card { border-radius:16px; box-shadow:none; }
.funnel-kpi-card { border-color:#ece8df; background:#fff; }
.funnel-stage-panel { border-color:#ece8df; background:#fff; overflow:hidden; }
.funnel-loss-card { border-color:#ece8df; background:#fff; overflow:hidden; }
.funnel-visual { border-color:#ece8df; background:linear-gradient(180deg,#fcfcfb,#f5f7f5); }
.funnel-stage-link:hover { background:#f7faf8; transform:none; box-shadow:none; }
.dashboard-skeleton { display:flex; flex-direction:column; gap:16px; margin:0 0 16px; }
.dashboard-skeleton-card { border:1px solid #e7e5df; border-radius:18px; background:linear-gradient(180deg,#ffffff,#fafaf8); box-shadow:0 8px 24px rgba(15,23,42,.04); overflow:hidden; }
.dashboard-skeleton-status { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:14px 16px; border:1px solid #e7e5df; border-radius:16px; background:linear-gradient(180deg,#fff,#fbfbf9); box-shadow:0 8px 22px rgba(15,23,42,.04); }
.dashboard-skeleton-status-copy { display:flex; flex-direction:column; gap:8px; min-width:0; }
.dashboard-skeleton-badge { display:inline-flex; align-items:center; gap:8px; font-size:11px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#7a7a73; }
.dashboard-skeleton-dot { width:10px; height:10px; border-radius:999px; background:linear-gradient(135deg,#0b6b4f,#6fd0ae); box-shadow:0 0 0 6px rgba(11,107,79,.08); }
.dashboard-skeleton-row { display:grid; gap:14px; }
.dashboard-skeleton-row.stats { grid-template-columns:repeat(4, minmax(0, 1fr)); }
.dashboard-skeleton-row.main { grid-template-columns:minmax(0, 1.1fr) minmax(0, .9fr); }
.dashboard-skeleton-row.bottom { grid-template-columns:minmax(0, .85fr) minmax(0, 1.15fr); }
.dashboard-skeleton-stat { padding:16px; }
.dashboard-skeleton-panel { padding:16px; min-height:220px; }
.dashboard-skeleton-table { padding:16px; }
.dashboard-skeleton-chart { height:180px; border-radius:18px; background:linear-gradient(180deg,#f8faf8,#eef4ef); border:1px solid #edf1ec; padding:18px; display:flex; align-items:flex-end; gap:12px; }
.dashboard-skeleton-bar { flex:1 1 0; border-radius:14px 14px 8px 8px; background:linear-gradient(180deg,rgba(11,107,79,.14),rgba(11,107,79,.28)); min-width:20px; }
.dashboard-skeleton-table-grid { display:grid; gap:12px; margin-top:16px; }
.dashboard-skeleton-table-row { display:grid; grid-template-columns:minmax(140px, 1.3fr) repeat(4, minmax(60px, .6fr)); gap:12px; align-items:center; }
.dashboard-skeleton-line { position:relative; overflow:hidden; border-radius:999px; background:#edf1ec; }
.dashboard-skeleton-line::after { content:''; position:absolute; inset:0; transform:translateX(-100%); background:linear-gradient(90deg,transparent,rgba(255,255,255,.72),transparent); animation:dashboardSkeletonShimmer 1.5s ease-in-out infinite; }
.dashboard-skeleton-line.title { width:min(260px, 82%); height:18px; }
.dashboard-skeleton-line.subtitle { width:min(200px, 52%); height:11px; }
.dashboard-skeleton-line.pill { width:84px; height:30px; border-radius:12px; }
.dashboard-skeleton-line.kpi { width:70px; height:28px; }
.dashboard-skeleton-line.label { width:110px; height:12px; margin-top:10px; }
.dashboard-skeleton-line.section-title { width:150px; height:16px; margin-bottom:16px; }
.dashboard-skeleton-line.metric-title { width:120px; height:14px; margin-bottom:12px; }
.dashboard-skeleton-line.metric-copy { width:100%; height:12px; }
.dashboard-skeleton-line.metric-copy.short { width:72%; }
.dashboard-skeleton-line.table-cell.short { width:58px; height:12px; }
.dashboard-skeleton-line.table-cell.medium { width:86px; height:12px; }
.dashboard-skeleton-line.table-cell.long { width:100%; height:12px; }
@keyframes dashboardSkeletonShimmer { 100% { transform:translateX(100%); } }
.funnel-stack {
    display:flex;
    flex-direction:column;
    gap:16px;
}
.funnel-kpi-strip {
    display:grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap:10px;
}
.funnel-kpi-card {
    border:1px solid #e8ede8;
    border-radius:14px;
    background:linear-gradient(180deg,#ffffff,#f8faf8);
    padding:14px 16px;
}
.funnel-kpi-card .label {
    font-size:10px;
    font-weight:700;
    letter-spacing:.08em;
    text-transform:uppercase;
    color:#7a7a73;
}
.funnel-kpi-card .value {
    margin-top:8px;
    font-size:24px;
    line-height:1;
    font-weight:800;
    color:#16161A;
}
.funnel-kpi-card .note {
    margin-top:6px;
    font-size:12px;
    color:#5e5e5a;
}
.funnel-main-grid {
    display:grid;
    grid-template-columns: minmax(250px, 300px) minmax(0, 1fr) minmax(240px, 280px);
    gap:14px;
    align-items:start;
}
.funnel-left-stack {
    display:flex;
    flex-direction:column;
    gap:10px;
}
.funnel-visual {
    position:relative;
    min-height:360px;
    border:1px solid #edf1ec;
    border-radius:18px;
    background:linear-gradient(180deg,#ffffff,#f8fbf9);
    padding:20px 16px;
    display:flex;
    align-items:center;
    justify-content:center;
}
.funnel-visual-shell {
    width:100%;
    max-width:235px;
    display:flex;
    flex-direction:column;
    align-items:center;
    gap:5px;
}
.funnel-cap {
    width:90%;
    height:16px;
    border:4px solid #18a89a;
    border-radius:999px;
    background:#fff;
    box-shadow: inset 0 1px 0 rgba(255,255,255,.9);
}
.funnel-segment {
    position:relative;
    height:32px;
    display:flex;
    align-items:center;
    justify-content:center;
    color:#fff;
    font-size:13px;
    font-weight:800;
    letter-spacing:.01em;
    clip-path: polygon(6% 0, 94% 0, 86% 100%, 14% 100%);
    box-shadow: 0 10px 22px rgba(15, 23, 42, .08);
}
.funnel-segment span {
    text-shadow: 0 1px 1px rgba(0,0,0,.18);
}
.funnel-stage-panel {
    border:1px solid #edf1ec;
    border-radius:16px;
    background:#fff;
    padding:12px;
}
.funnel-panel-heading {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    margin:0 2px 10px;
}
.funnel-panel-title {
    font-size:11px;
    font-weight:800;
    letter-spacing:.08em;
    text-transform:uppercase;
    color:#667085;
}
.funnel-panel-hint {
    font-size:11px;
    font-weight:700;
    color:#8a918b;
}
.funnel-stage-list {
    display:flex;
    flex-direction:column;
    gap:0;
    border:1px solid #e3e9e5;
    border-radius:12px;
    overflow-x:auto;
    overflow-y:hidden;
    background:#fff;
    -webkit-overflow-scrolling:touch;
}
.funnel-stage-table-head {
    display:grid;
    grid-template-columns:minmax(180px, 1.05fr) 86px minmax(160px, 1.4fr) 86px;
    gap:12px;
    align-items:center;
    padding:9px 12px;
    background:#f6f8f7;
    border-bottom:1px solid #e3e9e5;
    color:#59645f;
    font-size:9px;
    font-weight:900;
    letter-spacing:.06em;
    text-transform:uppercase;
}
.funnel-stage-table-head span:nth-child(2),
.funnel-stage-table-head span:nth-child(4) {
    text-align:right;
}
.funnel-stage-link {
    display:grid;
    grid-template-columns:minmax(180px, 1.05fr) 86px minmax(160px, 1.4fr) 86px;
    align-items:center;
    gap:12px;
    text-decoration:none;
    color:inherit;
    border-bottom:1px solid #e8eee9;
    border-radius:0;
    min-height:48px;
    padding:8px 12px;
    transition: background .18s ease;
}
.funnel-stage-link:last-child {
    border-bottom:none;
}
.funnel-stage-link:hover {
    background:#f1f8f4;
}
.funnel-line-icon {
    width:28px;
    height:28px;
    border-radius:9px;
    display:flex;
    align-items:center;
    justify-content:center;
    color:#fff;
    font-size:12px;
    box-shadow:none;
}
.funnel-stage-copy {
    min-width:0;
    display:flex;
    align-items:center;
    gap:9px;
}
.funnel-line-label {
    font-size:12px;
    font-weight:850;
    color:#16161A;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
}
.funnel-stage-sub {
    font-size:11px;
    color:#7a7a73;
    text-align:right;
    font-weight:850;
    font-variant-numeric:tabular-nums;
}
.funnel-line-track {
    position:relative;
    height:7px;
    background:#e7ece8;
    border-radius:999px;
    overflow:hidden;
}
.funnel-line-fill {
    position:absolute;
    inset:0 auto 0 0;
    min-width: 14px;
    border-radius:999px;
}
.funnel-line-meta {
    min-width:86px;
    text-align:right;
}
.funnel-line-value {
    font-size:14px;
    line-height:1;
    font-weight:900;
    color:#16161A;
    font-variant-numeric:tabular-nums;
}
.funnel-line-percent {
    margin-top:4px;
    font-size:11px;
    font-weight:800;
    color:#7a7a73;
    font-variant-numeric:tabular-nums;
}
.funnel-loss-card {
    border:1px solid #f0e5e2;
    border-radius:16px;
    background:#fff;
    padding:10px;
}
.funnel-loss-header {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    margin-bottom:10px;
}
.funnel-loss-title {
    font-size:11px;
    font-weight:700;
    letter-spacing:.08em;
    text-transform:uppercase;
    color:#8b5c55;
}
.funnel-loss-total {
    font-size:12px;
    font-weight:700;
    color:#7f1d1d;
}
.funnel-loss-grid {
    display:grid;
    grid-template-columns: 1fr;
    gap:0;
    border:1px solid #f0e5e2;
    border-radius:12px;
    overflow:hidden;
}
.funnel-loss-table-head {
    display:grid;
    grid-template-columns:minmax(0, 1fr) 72px;
    gap:10px;
    padding:8px 10px;
    background:#fff7f6;
    border-bottom:1px solid #f0e5e2;
    color:#8b5c55;
    font-size:9px;
    font-weight:900;
    letter-spacing:.06em;
    text-transform:uppercase;
}
.funnel-loss-table-head span:last-child {
    text-align:right;
}
.funnel-loss-item {
    display:grid;
    grid-template-columns:minmax(0, 1fr) 72px;
    align-items:center;
    gap:10px;
    border:0;
    border-bottom:1px solid #f3e4e1;
    border-radius:0;
    background:#fff;
    padding:8px 10px;
}
.funnel-loss-item:last-child { border-bottom:none; }
.funnel-loss-item strong {
    display:block;
    font-size:12px;
    color:#16161A;
}
.funnel-loss-item span {
    display:block;
    margin-top:3px;
    font-size:10px;
    text-transform:uppercase;
    letter-spacing:.06em;
    color:#8f8b86;
}
.funnel-loss-value {
    font-size:14px;
    font-weight:900;
    color:#b42318;
    text-align:right;
    font-variant-numeric:tabular-nums;
}
/* Optional funnel preview. The established dashboard renderer remains below for rollback. */
.funnel-minimal-kpis {
    display:grid;
    grid-template-columns:repeat(3, minmax(0, 1fr));
    border:1px solid #dce7df;
    border-radius:8px;
    overflow:hidden;
    background:#fff;
}
.funnel-minimal-kpi {
    min-height:78px;
    padding:14px 16px;
    border-right:1px solid #dce7df;
}
.funnel-minimal-kpi:last-child { border-right:0; }
.funnel-minimal-kpi span,
.funnel-minimal-heading,
.funnel-minimal-table-head {
    display:block;
    color:#6b7a70;
    font-size:10px;
    font-weight:800;
    letter-spacing:.07em;
    text-transform:uppercase;
}
.funnel-minimal-kpi strong {
    display:block;
    margin-top:7px;
    color:#123c2c;
    font-size:25px;
    line-height:1;
    font-variant-numeric:tabular-nums;
}
.funnel-minimal-kpi small {
    display:block;
    margin-top:6px;
    color:#728078;
    font-size:11px;
}
.funnel-minimal-grid {
    display:grid;
    grid-template-columns:minmax(255px, 310px) minmax(0, 1fr) minmax(210px, 255px);
    gap:14px;
    align-items:start;
}
.funnel-minimal-card {
    border:1px solid #dce7df;
    border-radius:8px;
    background:#fff;
    overflow:hidden;
}
.funnel-minimal-heading {
    padding:13px 14px;
    border-bottom:1px solid #dce7df;
    color:#174f3b;
}
.funnel-minimal-visual { padding:16px 18px 18px; }
.funnel-minimal-step {
    display:flex;
    justify-content:center;
    align-items:center;
    height:34px;
    margin:0 auto 6px;
    color:#fff;
    font-size:13px;
    font-weight:800;
    text-decoration:none;
    clip-path:polygon(6% 0, 94% 0, 87% 100%, 13% 100%);
    transition:filter .16s ease;
}
.funnel-minimal-step:hover { color:#fff; filter:brightness(.92); }
.funnel-minimal-table-head,
.funnel-minimal-row {
    display:grid;
    grid-template-columns:minmax(120px, 1fr) 70px minmax(96px, 1.15fr) 74px;
    gap:10px;
    align-items:center;
    padding:10px 12px;
}
.funnel-minimal-table-head {
    background:#f3f7f4;
    border-bottom:1px solid #dce7df;
}
.funnel-minimal-table-head span:nth-child(n+2),
.funnel-minimal-row > span:nth-child(n+2) { text-align:right; }
.funnel-minimal-row {
    min-height:48px;
    color:#20362c;
    text-decoration:none;
    border-bottom:1px solid #e7eee9;
    font-size:12px;
    font-weight:700;
}
.funnel-minimal-row:last-child { border-bottom:0; }
.funnel-minimal-row:hover { background:#f5faf6; color:#123c2c; }
.funnel-minimal-progress {
    height:6px;
    overflow:hidden;
    border-radius:999px;
    background:#e0e9e3;
}
.funnel-minimal-progress i { display:block; height:100%; border-radius:inherit; background:#287553; }
.funnel-minimal-count { font-variant-numeric:tabular-nums; color:#123c2c; }
.funnel-minimal-losses { padding:0; }
.funnel-minimal-loss {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    padding:11px 13px;
    color:#6f302c;
    text-decoration:none;
    border-bottom:1px solid #f1dfdc;
    font-size:12px;
    font-weight:700;
}
.funnel-minimal-loss:last-child { border-bottom:0; }
.funnel-minimal-loss:hover { background:#fff7f6; color:#a1221b; }
.funnel-minimal-loss span { color:#b42318; font-size:14px; font-weight:900; font-variant-numeric:tabular-nums; }
@media (max-width: 1100px) {
    .funnel-minimal-grid { grid-template-columns:minmax(245px, .8fr) minmax(0, 1.2fr); }
    .funnel-minimal-grid .funnel-demand-panel { grid-column:1 / -1; display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); }
}
@media (max-width: 700px) {
    .funnel-minimal-kpis,
    .funnel-minimal-grid { grid-template-columns:1fr; }
    .funnel-minimal-kpi { border-right:0; border-bottom:1px solid #dce7df; }
    .funnel-minimal-kpi:last-child { border-bottom:0; }
    .funnel-minimal-grid .funnel-demand-panel { grid-column:auto; grid-template-columns:1fr; }
    .funnel-minimal-table-head,
    .funnel-minimal-row { grid-template-columns:minmax(100px, 1fr) 52px minmax(72px, 1fr) 58px; gap:7px; padding:9px; }
}
.funnel-demand-panel {
    display:flex;
    flex-direction:column;
    gap:12px;
    min-width:0;
}
.funnel-demand-card {
    border:1px solid #e3e9e5;
    border-radius:16px;
    background:#fff;
    padding:12px;
}
.funnel-demand-head {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    margin-bottom:10px;
}
.funnel-demand-title {
    display:flex;
    align-items:center;
    gap:8px;
    min-width:0;
    font-size:11px;
    font-weight:900;
    letter-spacing:.08em;
    text-transform:uppercase;
    color:#667085;
}
.funnel-demand-title i {
    width:24px;
    height:24px;
    border-radius:8px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    background:#eaf4ff;
    color:#006BA6;
    font-size:11px;
}
.funnel-demand-total {
    flex:0 0 auto;
    font-size:11px;
    font-weight:900;
    color:#006BA6;
    font-variant-numeric:tabular-nums;
}
.funnel-demand-list {
    display:flex;
    flex-direction:column;
    gap:8px;
}
.funnel-demand-row {
    display:grid;
    grid-template-columns:minmax(0, 1fr) auto;
    gap:10px;
    align-items:center;
    color:inherit;
    text-decoration:none;
}
.funnel-demand-row:hover .funnel-demand-label {
    color:#006BA6;
}
.funnel-demand-copy {
    min-width:0;
}
.funnel-demand-label {
    display:block;
    min-width:0;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
    font-size:12px;
    font-weight:850;
    color:#16161A;
}
.funnel-demand-track {
    margin-top:5px;
    height:6px;
    border-radius:999px;
    background:#edf2f7;
    overflow:hidden;
}
.funnel-demand-fill {
    height:100%;
    min-width:6px;
    border-radius:999px;
    background:#006BA6;
}
.funnel-demand-value {
    text-align:right;
    font-size:12px;
    font-weight:900;
    color:#0f172a;
    font-variant-numeric:tabular-nums;
}
.funnel-demand-value span {
    display:block;
    margin-top:2px;
    font-size:10px;
    font-weight:800;
    color:#64748b;
}
.funnel-demand-empty {
    padding:10px;
    border-radius:12px;
    background:#f8fafc;
    color:#94a3b8;
    font-size:12px;
    font-weight:700;
    text-align:center;
}
.progress-list { display:flex; flex-direction:column; gap:12px; }
.progress-item-head { display:flex; justify-content:space-between; gap:10px; margin-bottom:5px; font-size:12px; }
.progress-item-head strong { color:#16161A; }
.progress-item-head span { color:#5e5e5a; font-weight:600; }
.progress-track { height:7px; border-radius:999px; background:#f1f0ec; overflow:hidden; }
.progress-fill { height:100%; border-radius:999px; }
.metric-table td small { color:#7a7a73; font-size:11px; }
.table-avatar { width:26px; height:26px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:10px; font-weight:700; color:#fff; background:linear-gradient(135deg,#1761a8,#3b82f6); margin-right:8px; }
.metric-modal { position:fixed; inset:0; background:rgba(0,0,0,.48); display:none; align-items:center; justify-content:center; z-index:1200; padding:20px; }
.metric-modal.is-open { display:flex; }
.metric-modal-card { width:min(900px, 100%); max-height:86vh; overflow:auto; background:#fff; border-radius:20px; box-shadow:0 20px 60px rgba(0,0,0,.2); padding:22px 24px; }
.metric-modal-header { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:18px; }
.metric-modal-close { border:none; background:#f3f4f6; color:#374151; width:36px; height:36px; border-radius:10px; cursor:pointer; }
@keyframes fadeUp { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
/* ── Stat Cards ─────────────────────────────── */
.stat-card {
    background: #fff;
    border-radius: 16px;
    border: 1px solid #ebe8e1;
    padding: 14px 16px;
    box-shadow: 0 1px 2px rgba(15,23,42,.03);
    transition: border-color .2s ease, box-shadow .2s ease;
    display: flex;
    align-items: center;
    gap: 12px;
}
.stat-card:hover { border-color:#ddd8ce; box-shadow: 0 8px 20px rgba(15,23,42,.05); }
.stat-icon {
    width: 40px; height: 40px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    background:#f4f3ef !important;
}
.stat-icon i { font-size: 16px; color: #20242c; }
.stat-value { font-size: 30px; font-weight: 700; color: #141922; line-height: 1; letter-spacing:-.03em; }
.stat-label { font-size: 10px; color: #7d827d; font-weight: 700; margin-bottom: 6px; text-transform: uppercase; letter-spacing: .08em; }
#main-stats .stat-card:last-child { display:none; }
#main-stats .stat-card > div:last-child { display:flex; flex-direction:column-reverse; gap:2px; }

/* ── Section Cards ──────────────────────────── */
.section-card {
    background: #fff;
    border-radius: 18px;
    border: 1px solid #ebe8e1;
    padding: 18px;
    margin-bottom: 16px;
    box-shadow: 0 1px 2px rgba(15,23,42,.03);
}
.section-title {
    font-size: 14px;
    font-weight: 700;
    color: #1c2430;
    margin-bottom: 14px;
    padding-bottom: 12px;
    border-bottom: 1px solid #f1efe9;
    display: flex;
    align-items: center;
    gap: 9px;
}
.section-title-icon {
    width: 30px; height: 30px; border-radius: 10px;
    display: inline-flex; align-items: center; justify-content: center;
    background: #f3f4ef;
    flex-shrink: 0;
}
.section-title-icon i { color: #20242c; font-size: 12px; }

/* ── Mini stat box ──────────────────────────── */
.mini-stat {
    background: #f9fafb; border-radius: 10px; padding: 12px 14px;
    border: 1px solid #f3f4f6;
}
.mini-stat-val { font-size: 20px; font-weight: 700; color: #063A1C; line-height: 1; }
.mini-stat-lbl { font-size: 10px; color: #6b7280; margin-top: 4px; text-transform: uppercase; letter-spacing: .05em; }

/* ── Date filter pills ──────────────────────── */
.date-filter-btn {
    padding: 6px 14px; border: 1.5px solid #e5e7eb; background: #fff;
    border-radius: 20px; font-size: 12px; font-weight: 600; color: #374151;
    cursor: pointer; transition: all .2s; white-space: nowrap;
}
.date-filter-btn:hover { border-color: #205A44; color: #205A44; }
.date-filter-btn.active { background:#20242c; color:#fff; border-color:#20242c; box-shadow:none; }
.section-filter-btn {
    padding: 5px 10px;
    border: 1px solid #dfe6df;
    background: #fff;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    color: #5f6b62;
    cursor: pointer;
    transition: all .18s ease;
}
.section-filter-btn:hover { border-color:#0b6b4f; color:#0b6b4f; }
.section-filter-btn.active { background:#20242c; color:#fff; border-color:#20242c; }
.section-card-header { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:14px; padding-bottom:12px; border-bottom:1px solid #f1efe9; }
.section-card-header .section-title { margin-bottom:0; padding-bottom:0; border-bottom:none; }
.section-filter-group { display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
.dashboard-customize-modal { position:fixed; inset:0; background:rgba(15,23,42,.42); display:none; align-items:center; justify-content:center; z-index:1300; padding:18px; }
.dashboard-customize-modal.is-open { display:flex; }
.dashboard-customize-card { width:min(560px,100%); background:#fff; border-radius:22px; box-shadow:0 24px 60px rgba(15,23,42,.18); overflow:hidden; }
.dashboard-customize-head { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:20px 22px; border-bottom:1px solid #edf1ec; }
.dashboard-customize-title { font-size:18px; font-weight:800; color:#111827; }
.dashboard-customize-sub { margin-top:4px; font-size:12px; color:#6b7280; }
.dashboard-customize-close { border:none; background:#f3f4f6; color:#374151; width:38px; height:38px; border-radius:12px; cursor:pointer; }
.dashboard-customize-body { padding:18px 22px 22px; display:grid; gap:10px; max-height:70vh; overflow:auto; }
.dashboard-customize-foot { display:flex; align-items:center; justify-content:flex-end; gap:10px; padding:16px 22px 22px; border-top:1px solid #edf1ec; }
.dashboard-customize-secondary { border:1px solid #d1d5db; background:#fff; color:#374151; border-radius:12px; padding:10px 14px; font-size:12px; font-weight:700; cursor:pointer; }
.dashboard-customize-primary { border:none; background:#0b6b4f; color:#fff; border-radius:12px; padding:10px 16px; font-size:12px; font-weight:700; cursor:pointer; box-shadow:0 8px 18px rgba(11,107,79,.16); }
.dashboard-customize-primary:hover { background:#09543f; }
.dashboard-customize-item { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:14px 16px; border:1px solid #edf1ec; border-radius:14px; background:#fafcfb; }
.dashboard-customize-item strong { display:block; font-size:13px; color:#16161A; }
.dashboard-customize-item span { display:block; margin-top:4px; font-size:12px; color:#6b7280; }
.dashboard-customize-item.is-dragging { opacity:.5; border-style:dashed; }
.dashboard-customize-main { display:flex; align-items:center; gap:12px; min-width:0; }
.dashboard-drag-handle { width:34px; height:34px; border:1px solid #d9e2da; border-radius:12px; background:#fff; color:#6b7280; display:flex; align-items:center; justify-content:center; cursor:grab; flex-shrink:0; }
.dashboard-drag-handle:active { cursor:grabbing; }
.dashboard-switch { position:relative; width:48px; height:28px; display:inline-block; }
.dashboard-switch input { opacity:0; width:0; height:0; }
.dashboard-switch-slider { position:absolute; inset:0; background:#d1d5db; border-radius:999px; transition:.2s ease; }
.dashboard-switch-slider::before { content:''; position:absolute; width:22px; height:22px; left:3px; top:3px; background:#fff; border-radius:50%; transition:.2s ease; box-shadow:0 2px 6px rgba(0,0,0,.15); }
.dashboard-switch input:checked + .dashboard-switch-slider { background:#0b6b4f; }
.dashboard-switch input:checked + .dashboard-switch-slider::before { transform:translateX(20px); }

/* ── VM filter pills ────────────────────────── */
.visits-meetings-filter-btn {
    padding: 6px 14px; border: 1.5px solid #e5e7eb; background: #fff;
    border-radius: 20px; font-size: 12px; font-weight: 600; color: #374151;
    cursor: pointer; transition: all .2s;
}
.visits-meetings-filter-btn:hover { border-color: #205A44; color: #205A44; }
.visits-meetings-filter-btn.active { background: linear-gradient(135deg,#063A1C,#205A44); color: #fff; border-color: transparent; }

/* ── Tables ─────────────────────────────────── */
table { width: 100%; border-collapse: collapse; }
th, td { padding: 9px 12px; text-align: left; border-bottom: 1px solid #f3f4f6; }
th { background: #f9fafb; font-weight: 700; color: #374151; font-size: 11px; text-transform: uppercase; letter-spacing: .4px; }
td { color: #374151; font-size: 13px; }
tr:last-child td { border-bottom: none; }
tr:hover td { background: #fafafa; }

/* ── Badges ─────────────────────────────────── */
.badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.badge-new { background: #dbeafe; color: #1e40af; }
.badge-connected { background: #bfdbfe; color: #1e3a8a; }
.badge-verified-prospect { background: #e9d5ff; color: #6b21a8; }
.badge-meeting-scheduled { background: #ddd6fe; color: #5b21b6; }
.badge-meeting-completed { background: #cffafe; color: #155e75; }
.badge-visit-scheduled { background: #ede9fe; color: #5b21b6; }
.badge-visit-done { background: #fce7f3; color: #9f1239; }
.badge-revisited-scheduled { background: #fce7f3; color: #9f1239; }
.badge-revisited-completed { background: #fecdd3; color: #881337; }
.badge-closed { background: #d1fae5; color: #065f46; }
.badge-dead { background: #fee2e2; color: #991b1b; }
.badge-on-hold { background: #f3f4f6; color: #374151; }
.badge-contacted { background: #fef3c7; color: #92400e; }
.badge-default { background: #f3f4f6; color: #6b7280; }
.badge-qualified { background: #e9d5ff; color: #6b21a8; }

/* ── Chart container ────────────────────────── */
.chart-container { position: relative; height: 220px; }

/* ── Call stats filter ──────────────────────── */
.call-stats-filter-btn {
    padding: 5px 12px; border: 1.5px solid #e5e7eb; background: #fff;
    border-radius: 6px; font-size: 12px; font-weight: 600; color: #374151; cursor: pointer; transition: all .2s;
}
.call-stats-filter-btn.active { background: linear-gradient(135deg,#063A1C,#205A44); color: #fff; border-color: transparent; }

/* ── quick-action-btn kept for JS compat ──── */
.quick-action-btn { display: none; }
.admin-table-shell { overflow-x:auto; border:1px solid #edf1ec; border-radius:14px; background:linear-gradient(180deg,#fff,#fcfcfa); }
.admin-side-panel { border:1px solid #edf1ec; border-radius:14px; background:linear-gradient(180deg,#fff,#fbfcfb); padding:12px; }
.admin-side-panel-title { font-size:11px; font-weight:700; color:#5f6b62; text-transform:uppercase; letter-spacing:.08em; margin-bottom:10px; }
.compact-metric-table { width:100%; border-collapse:collapse; font-size:12.5px; }
.compact-metric-table thead th { background:#f4f7f4; color:#5f6b62; border-bottom:1px solid #e4ebe5; padding:8px 10px; }
.compact-metric-table tbody td { padding:8px 10px; }
.compact-metric-table tbody tr:last-child td { border-bottom:none; }
.compact-reset-btn { border:none; border-radius:8px; padding:5px 9px; background:#eef2ff; color:#1d4ed8; font-size:11px; font-weight:700; cursor:pointer; }
.compact-link-btn { color:#0b6b4f; font-size:11px; font-weight:700; text-decoration:none; }
.compact-muted { color:#8b948d; font-size:12px; }
.mobile-dashboard-filter-toggle { display:inline-flex; align-items:center; justify-content:center; gap:7px; width:36px; height:32px; border:1px solid #e2e1dc; border-radius:999px; background:#fff; color:#16161A; padding:0; font-size:12px; font-weight:800; cursor:pointer; white-space:nowrap; box-shadow:0 1px 3px rgba(15,23,42,.04); }
.mobile-dashboard-filter-toggle:hover { border-color:#cfd8d1; color:#0b6b4f; }
.mobile-dashboard-filter-toggle span { display:none; }
.visits-meetings-filter-shell { display:flex; gap:8px; margin-bottom:12px; flex-wrap:wrap; }
.visits-meetings-filter-panel { display:flex; gap:8px; flex-wrap:wrap; }
.sales-score-mobile-grid { display:none; }
.sales-score-head-actions { display:flex; align-items:center; justify-content:flex-end; gap:10px; flex-wrap:wrap; }
.sales-score-column-picker { position:relative; }
.sales-score-columns-btn {
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:7px;
    height:36px;
    padding:0 13px;
    border:1px solid #dfe7e1;
    border-radius:9px;
    background:#fff;
    color:#24352d;
    font-size:12px;
    font-weight:800;
    cursor:pointer;
}
.sales-score-columns-btn:hover,
.sales-score-columns-btn[aria-expanded="true"] { border-color:#0b6b4f; color:#0b6b4f; background:#f4fbf7; }
.sales-score-column-popover {
    position:absolute;
    top:calc(100% + 8px);
    right:0;
    z-index:30;
    width:320px;
    border:1px solid #dfe7e1;
    border-radius:12px;
    background:#fff;
    box-shadow:0 18px 45px rgba(15,23,42,.16);
    overflow:hidden;
}
.sales-score-column-popover[hidden] { display:none; }
.sales-score-column-popover-head { padding:13px 14px 10px; border-bottom:1px solid #edf1ee; }
.sales-score-column-popover-head strong { display:block; color:#14271e; font-size:13px; }
.sales-score-column-popover-head span { display:block; margin-top:3px; color:#738078; font-size:11px; }
.sales-score-column-options { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:6px 10px; max-height:260px; overflow:auto; padding:12px 14px; }
.sales-score-column-option { display:flex; align-items:center; gap:7px; min-width:0; color:#34443c; font-size:12px; font-weight:650; cursor:pointer; }
.sales-score-column-option input { width:15px; height:15px; accent-color:#0b6b4f; }
.sales-score-column-option.locked { color:#8a948f; cursor:not-allowed; }
.sales-score-column-quick-actions { display:flex; gap:6px; padding:0 14px 11px; }
.sales-score-column-link { border:0; background:transparent; color:#0b6b4f; padding:2px 0; font-size:11px; font-weight:800; cursor:pointer; }
.sales-score-column-link + .sales-score-column-link { padding-left:7px; border-left:1px solid #dfe7e1; }
.sales-score-column-error { min-height:16px; margin:0; padding:0 14px 7px; color:#b42318; font-size:11px; font-weight:700; }
.sales-score-column-actions { display:flex; justify-content:flex-end; gap:8px; padding:10px 14px; border-top:1px solid #edf1ee; background:#fafcfb; }
.sales-score-column-action { height:34px; border:1px solid #dbe4de; border-radius:8px; padding:0 13px; background:#fff; color:#34443c; font-size:12px; font-weight:800; cursor:pointer; }
.sales-score-column-action.primary { border-color:#0b6b4f; background:#0b6b4f; color:#fff; }
.sales-score-column-action:disabled { opacity:.6; cursor:wait; }
.sales-score-excel-wrap {
    width:100%;
    overflow:auto;
    border:1px solid #dfe7e1;
    border-radius:12px;
    background:#fff;
    -webkit-overflow-scrolling:touch;
}
.sales-score-excel-table {
    min-width:1320px;
    border-collapse:separate;
    border-spacing:0;
    font-size:12px;
}
.sales-score-excel-table th,
.sales-score-excel-table td {
    border-right:1px solid #e7ece8;
    border-bottom:1px solid #e7ece8;
    padding:8px 10px;
    line-height:1.2;
    white-space:nowrap;
    vertical-align:middle;
}
.sales-score-excel-table th:last-child,
.sales-score-excel-table td:last-child { border-right:none; }
.sales-score-excel-table thead th {
    position:sticky;
    top:0;
    z-index:2;
    background:#f6f8f7;
    color:#374151;
    font-size:10px;
    letter-spacing:.04em;
}
.sales-score-excel-table tbody tr:hover td { background:#fbfcfb; }
.sales-score-excel-table .sales-score-user-cell {
    position:sticky;
    left:0;
    z-index:1;
    min-width:220px;
    background:#fff;
    font-weight:700;
    color:#162033;
}
.sales-score-excel-table thead .sales-score-user-cell {
    z-index:3;
    background:#f6f8f7;
}
.sales-score-num,
.sales-score-percent,
.sales-score-action { text-align:center; }
.sales-score-muted { color:#8b948d; }
.no-response-pill {
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:28px;
    height:24px;
    padding:0 8px;
    border-radius:999px;
    font-size:12px;
    font-weight:800;
}
.no-response-pill.none { background:#f1f5f3; color:#6b7280; }
.no-response-pill.warn { background:#fff4d6; color:#9a5b00; }
.no-response-pill.danger { background:#ffe4e1; color:#b42318; }
.sales-score-reset-btn {
    padding:4px 8px;
    border-radius:7px;
    white-space:nowrap;
}
.sales-score-breakdown {
    margin-top:12px;
    border:1px solid #dfe7e1;
    border-radius:12px;
    background:#fbfcfb;
    padding:12px;
}
.sales-score-breakdown-head {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    margin-bottom:10px;
}
.sales-score-breakdown-head strong { font-size:13px; color:#162033; }
.sales-score-breakdown-head span { font-size:11px; color:#6b7280; }
.sales-score-breakdown-grid {
    display:grid;
    grid-template-columns:repeat(auto-fit, minmax(250px, 1fr));
    gap:10px;
}
.sales-score-breakdown-card {
    border:1px solid #e7ece8;
    border-radius:10px;
    background:#fff;
    padding:10px;
}
.sales-score-breakdown-title {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:8px;
    margin-bottom:9px;
}
.sales-score-breakdown-title strong { font-size:12px; color:#162033; }
.sales-score-breakdown-title span { font-size:11px; font-weight:800; color:#006BA6; }
.sales-score-breakdown-chips { display:flex; flex-wrap:wrap; gap:6px; }
.sales-score-breakdown-chip {
    display:inline-flex;
    align-items:center;
    gap:5px;
    border-radius:999px;
    background:#f1f5f3;
    padding:5px 8px;
    font-size:11px;
    font-weight:750;
    color:#374151;
}
.sales-score-breakdown-chip b { color:#111827; }
.sales-score-breakdown-chip.no-response { background:#fff4d6; color:#8a4b00; }
.sales-score-breakdown-chip.visits { background:#eaf6fc; color:#006BA6; }
.sales-score-breakdown-chip.other { background:#eef2ff; color:#4338ca; }
.sales-score-breakdown-formula {
    margin-top:8px;
    font-size:11px;
    color:#6b7280;
}
.legacy-leads-allocated-section { display:none !important; }
.sales-user-activity-wrap {
    width:100%;
    max-height:430px;
    overflow:auto;
    border:1px solid #dbe3ea;
    border-radius:12px;
    background:#fff;
}
.sales-user-activity-table {
    width:100%;
    min-width:820px;
    border-collapse:separate;
    border-spacing:0;
    font-size:12px;
}
.sales-user-activity-table th,
.sales-user-activity-table td {
    padding:8px 10px;
    border-right:1px solid #e6edf3;
    border-bottom:1px solid #e6edf3;
    white-space:nowrap;
}
.sales-user-activity-table th {
    position:sticky;
    top:0;
    z-index:1;
    background:#f3f6f9;
    color:#334155;
    font-size:10px;
    font-weight:800;
    letter-spacing:.05em;
    text-transform:uppercase;
}
.sales-user-activity-table .activity-user-cell {
    position:sticky;
    left:0;
    z-index:2;
    background:#fff;
    min-width:210px;
    max-width:230px;
    box-shadow:1px 0 0 #e6edf3;
}
.sales-user-activity-table th.activity-user-cell {
    background:#f3f6f9;
    z-index:3;
}
.sales-user-activity-table .activity-user-name {
    display:flex;
    align-items:center;
    gap:8px;
    color:#0f172a;
    font-weight:800;
}
.sales-user-activity-table .activity-role {
    display:block;
    margin-top:2px;
    color:#64748b;
    font-size:10px;
    font-weight:600;
}
.sales-user-activity-table .activity-num {
    text-align:right;
    font-variant-numeric:tabular-nums;
    font-weight:800;
    color:#0f172a;
}
.sales-user-activity-table .activity-total-row td {
    position:sticky;
    bottom:0;
    background:#ecfdf5;
    border-bottom:0;
    color:#064e3b;
    font-weight:900;
}
.sales-user-activity-table .activity-total-row .activity-user-cell {
    background:#ecfdf5;
}

@media (max-width: 820px) {
    .sales-score-column-picker { display:none !important; }
    /* Keep Sale dashboard sections in the normal one-column flow on phones. */
    #sale-dashboard-panel > [data-section="approval-center"],
    #sale-dashboard-panel > [data-section="demand-insights"] {
        display:block !important;
        grid-column:1 / -1 !important;
        grid-row:auto !important;
        width:100% !important;
        max-width:100% !important;
        min-width:0 !important;
    }
    #sale-dashboard-panel > [data-section="approval-center"] { grid-area:approval-center !important; }
    #sale-dashboard-panel > [data-section="demand-insights"] { grid-area:demand-insights !important; }
    #sale-dashboard-panel > [data-section="approval-center"] .approval-center-card,
    #sale-dashboard-panel > [data-section="demand-insights"] .demand-insights-grid,
    #sale-dashboard-panel > [data-section="demand-insights"] .demand-insight-card {
        width:100% !important;
        max-width:100% !important;
        min-width:0 !important;
    }
    .source-performance-grid {
        grid-template-columns: 1fr;
    }
    .meta-decision-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .meta-funnel-grid {
        grid-template-columns: 1fr;
    }
    .demand-insights-grid {
        grid-template-columns: 1fr;
    }
    .approval-center-head {
        align-items:flex-start;
        flex-direction:column;
        padding:16px;
    }
    .approval-center-filters {
        justify-content:flex-start;
        width:100%;
        max-width:none;
        flex-wrap:nowrap;
        overflow-x:auto;
        -webkit-overflow-scrolling:touch;
        padding-bottom:2px;
    }
    .approval-filter-btn {
        flex:0 0 auto;
    }
    .demand-insight-head {
        flex-direction:column;
        align-items:flex-start;
    }
    .demand-insight-total {
        text-align:left;
    }
    .approval-center-row {
        grid-template-columns:48px minmax(0, 1fr);
        gap:12px;
        padding:15px 16px;
    }
    .approval-icon {
        width:44px;
        height:44px;
        border-radius:14px;
        font-size:15px;
    }
    .approval-row-title {
        font-size:15px;
    }
    .approval-summary {
        font-size:12px;
    }
    .approval-age {
        grid-column:2;
        font-size:12px;
    }
    .approval-actions {
        grid-column:1 / -1;
        justify-content:flex-start;
    }
    .approval-action-btn {
        padding:9px 12px;
        font-size:12px;
    }
    #sale-dashboard-panel > .dashboard-two-up-item {
        width:100%;
        min-width:0;
        flex-basis:100%;
    }
    .funnel-kpi-strip,
    .funnel-loss-grid {
        grid-template-columns: 1fr;
    }
    .funnel-main-grid {
        grid-template-columns: 1fr;
    }
    .funnel-stage-link {
        grid-template-columns: 40px minmax(0, 1fr);
    }
    .funnel-line-track {
        grid-column: 1 / -1;
    }
    .funnel-line-meta {
        min-width: 0;
        text-align: left;
        grid-column: 2;
    }
    .admin-dashboard-root {
        width: 100%;
        max-width: 100%;
        overflow-x: hidden;
    }
    .admin-dashboard-shell {
        gap: 12px;
    }
    .admin-hero {
        padding: 10px;
        border-radius: 14px;
    }
    .admin-hero-grid {
        grid-template-columns: 1fr;
        gap: 10px;
    }
    .admin-hero-title {
        max-width: none;
        font-family:'Outfit','Inter',sans-serif;
        font-size: 20px;
        line-height: 1.1;
        letter-spacing:0;
        word-break: normal;
        overflow-wrap: break-word;
    }
    .admin-hero-head {
        display:grid;
        grid-template-columns:minmax(0, 1fr) auto;
        flex-direction: row;
        align-items: center;
        gap:8px;
    }
    .admin-hero-actions {
        justify-content: flex-end;
        gap:6px;
        flex-wrap:nowrap;
    }
    .admin-hero-clock {
        min-width:78px;
        padding:6px 8px;
        border-radius:10px;
    }
    .admin-hero-clock-time {
        font-size:12px;
        line-height:1;
    }
    .admin-hero-clock-date {
        font-size:9px;
        white-space:nowrap;
    }
    .admin-hero-logout {
        padding:8px 9px;
        border-radius:10px;
        font-size:0;
        gap:0;
    }
    .admin-hero-logout i {
        display:none;
    }
    .admin-hero-logout::after {
        content:'Logout';
        font-size:11px;
        line-height:1;
        font-weight:800;
    }
    .admin-hero-mode {
        display:grid;
        grid-template-columns:minmax(0, 1fr) auto;
        align-items:center;
        gap:8px;
        width:100%;
    }
    .admin-hero-mode > div {
        display:grid;
        grid-template-columns:minmax(0, 1fr) auto;
        align-items:center;
        gap:8px;
        width:100%;
    }
    .admin-hero-mode .premium-filter-label {
        display:none;
    }
    .mobile-dashboard-filter-toggle {
        display:inline-flex;
    }
    .admin-hero-mode .mobile-dashboard-filter-toggle {
        width:38px;
        height:34px;
        padding:0;
        border-radius:999px;
    }
    .admin-hero-mode .mobile-dashboard-filter-toggle span {
        display:none;
    }
    .admin-period-filter-panel {
        display:none;
    }
    .admin-period-filter-panel.is-open {
        display:block;
    }
    .admin-period-filter-panel .premium-filter-bar {
        gap:10px;
    }
    .admin-period-filter-panel .premium-filter-bar > div:first-child {
        display:grid !important;
        grid-template-columns:repeat(4, minmax(0, 1fr));
        gap:6px !important;
        align-items:center;
    }
    .admin-period-filter-panel .premium-filter-label {
        grid-column:1 / -1;
        font-size:9px;
    }
    .admin-period-filter-panel .date-filter-btn {
        padding:8px 4px;
        font-size:11px;
        text-align:center;
    }
    .admin-period-filter-panel .dashboard-customize-btn {
        padding:9px 10px;
        border-radius:10px;
        font-size:11px;
    }
    .admin-period-filter-panel input[type="date"] {
        min-width:0;
        width:100%;
    }
    .admin-hero-meta,
    .premium-grid-2,
    .premium-grid-3,
    .premium-grid-4,
    .ad-spend-grid,
    .ad-spend-layout,
    .shortcut-grid,
    .marketing-score-grid,
    .grid-responsive-2,
    .grid-responsive-leads {
        grid-template-columns: 1fr !important;
    }
    #main-stats {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    }
    .premium-filter-bar {
        flex-direction: column;
        align-items: stretch;
    }
    .section-card-header {
        flex-direction: column;
        align-items: stretch;
    }
    .dashboard-mode-toggle {
        width: 100%;
        justify-content: stretch;
        margin-top:0 !important;
        display:grid;
        grid-template-columns:repeat(3, minmax(0, 1fr));
        gap:4px;
        padding:4px;
    }
    .dashboard-mode-btn {
        text-align: center;
        min-width:0;
        padding:7px 4px;
        font-size:11px;
    }
    .hero-meta-card,
    .marketing-stat,
    .section-card,
    .shortcut-card,
    .score-card {
        min-width: 0;
    }
    #performance-scores.score-card-grid,
    .section-card #incentive-summary.score-card-grid {
        display:grid !important;
        gap:7px !important;
    }
    #performance-scores.score-card-grid {
        grid-template-columns:repeat(3, minmax(0, 1fr)) !important;
    }
    .section-card #incentive-summary.score-card-grid {
        grid-template-columns:repeat(4, minmax(0, 1fr)) !important;
    }
    #performance-scores .score-card,
    #incentive-summary .score-card {
        padding:10px 5px;
        border-radius:12px;
    }
    #performance-scores .score-card .value,
    #incentive-summary .score-card .value {
        font-size:17px;
        line-height:1;
    }
    #performance-scores .score-card .label,
    #incentive-summary .score-card .label {
        margin-top:6px;
        font-size:8px;
        letter-spacing:.06em;
        line-height:1.1;
    }
    #performance-scores .score-card .note,
    #incentive-summary .score-card .note {
        margin-top:5px;
        font-size:8px;
        line-height:1.2;
    }
    #pipeline-funnel.funnel-stack {
        display:grid;
        grid-template-columns:repeat(4, minmax(0, 1fr));
        gap:6px;
    }
    #pipeline-funnel .funnel-kpi-strip,
    #pipeline-funnel .funnel-main-grid,
    #pipeline-funnel .funnel-loss-grid {
        display:contents;
    }
    #pipeline-funnel .funnel-kpi-card,
    #pipeline-funnel .funnel-stage-link,
    #pipeline-funnel .funnel-loss-item,
    #pipeline-funnel .funnel-row {
        min-width:0;
        border:1px solid #ece8df;
        border-radius:12px;
        background:#fff;
        padding:9px 5px;
        text-align:center;
        box-shadow:none;
    }
    #pipeline-funnel .funnel-stage-link {
        display:flex !important;
        flex-direction:column-reverse;
        align-items:center;
        justify-content:center;
        gap:6px;
    }
    #pipeline-funnel .funnel-kpi-card,
    #pipeline-funnel .funnel-stage-link,
    #pipeline-funnel .funnel-loss-item,
    #pipeline-funnel .funnel-row {
        grid-column:span 1 !important;
    }
    #pipeline-funnel .funnel-visual,
    #pipeline-funnel .funnel-loss-card {
        display:none;
    }
    #pipeline-funnel .funnel-main-grid > div[style],
    #pipeline-funnel .funnel-stage-panel,
    #pipeline-funnel .funnel-stage-list {
        display:contents !important;
    }
    #pipeline-funnel .funnel-kpi-card .label,
    #pipeline-funnel .funnel-line-label,
    #pipeline-funnel .funnel-label,
    #pipeline-funnel .funnel-loss-label {
        width:auto;
        margin:6px 0 0;
        font-size:7px;
        line-height:1.1;
        letter-spacing:.06em;
        text-transform:uppercase;
        color:#7a7a73;
        font-weight:800;
    }
    #pipeline-funnel .funnel-kpi-card .value,
    #pipeline-funnel .funnel-line-value,
    #pipeline-funnel .funnel-bar,
    #pipeline-funnel .funnel-loss-count {
        margin:0;
        width:auto !important;
        min-width:0;
        padding:0;
        background:transparent !important;
        color:#16161A;
        font-size:16px;
        line-height:1;
        font-weight:900;
    }
    #pipeline-funnel .funnel-stage-icon,
    #pipeline-funnel .funnel-line-icon,
    #pipeline-funnel .funnel-line-track,
    #pipeline-funnel .funnel-stage-sub,
    #pipeline-funnel .funnel-line-percent,
    #pipeline-funnel .funnel-meta,
    #pipeline-funnel .funnel-bar-wrap {
        display:none;
    }
    [data-section="sales-score-table"] .section-filter-group {
        display:none;
    }
    [data-section="sales-score-table"] .section-title {
        font-size:14px;
        line-height:1.2;
        gap:7px;
    }
    [data-section="sales-score-table"] .section-title-icon {
        width:28px;
        height:28px;
        border-radius:9px;
        font-size:12px;
        flex:0 0 28px;
    }
    #sales-score-table {
        overflow:visible;
    }
    #sales-score-table table { display:none; }
    #sales-score-table table {
        min-width:520px;
        font-size:11px;
    }
    #sales-score-table table th,
    #sales-score-table table td {
        padding:8px 9px;
        font-size:11px;
        line-height:1.25;
    }
    #sales-score-table table th {
        font-size:9px;
        letter-spacing:.05em;
    }
    #sales-score-table .table-avatar {
        width:24px;
        height:24px;
        min-width:24px;
        font-size:10px;
        margin-right:6px;
        vertical-align:middle;
    }
    #sales-score-table table th:nth-child(6),
    #sales-score-table table td:nth-child(6),
    #sales-score-table table th:nth-child(7),
    #sales-score-table table td:nth-child(7) {
        display:none;
    }
    #sales-score-table table th:nth-child(2),
    #sales-score-table table td:nth-child(2) {
        display:none;
    }
    .sales-user-activity-wrap {
        width:100%;
        overflow-x:auto;
        border:1px solid #e5e7eb;
        border-radius:16px;
        background:#fff;
    }
    .sales-user-activity-table {
        width:100%;
        min-width:820px;
        border-collapse:separate;
        border-spacing:0;
        font-size:13px;
    }
    .sales-user-activity-table th,
    .sales-user-activity-table td {
        padding:12px 14px;
        border-bottom:1px solid #edf2f7;
        white-space:nowrap;
    }
    .sales-user-activity-table th {
        position:sticky;
        top:0;
        z-index:1;
        background:#f8fafc;
        color:#475569;
        font-size:11px;
        font-weight:800;
        letter-spacing:.05em;
        text-transform:uppercase;
    }
    .sales-user-activity-table .activity-user-cell {
        position:sticky;
        left:0;
        z-index:2;
        background:#fff;
        min-width:230px;
        box-shadow:1px 0 0 #edf2f7;
    }
    .sales-user-activity-table th.activity-user-cell {
        background:#f8fafc;
        z-index:3;
    }
    .sales-user-activity-table .activity-user-name {
        display:flex;
        align-items:center;
        gap:10px;
        color:#0f172a;
        font-weight:800;
    }
    .sales-user-activity-table .activity-role {
        display:block;
        margin-top:2px;
        color:#64748b;
        font-size:11px;
        font-weight:600;
    }
    .sales-user-activity-table .activity-num {
        text-align:right;
        font-variant-numeric:tabular-nums;
        font-weight:800;
        color:#0f172a;
    }
    .sales-user-activity-table .activity-total-row td {
        background:#ecfdf5;
        border-bottom:0;
        color:#064e3b;
        font-weight:900;
    }
    .sales-user-activity-table .activity-total-row .activity-user-cell {
        background:#ecfdf5;
    }
    .visits-meetings-filter-shell {
        justify-content:flex-end;
        margin-bottom:10px;
    }
    .visits-meetings-filter-toggle {
        margin-left:auto;
    }
    .visits-meetings-filter-panel {
        display:none;
        width:100%;
        grid-template-columns:repeat(2, minmax(0, 1fr));
        gap:6px;
        padding:8px;
        border:1px dashed #d8d8d2;
        border-radius:12px;
        background:#fbfbf8;
    }
    .visits-meetings-filter-panel.is-open {
        display:grid;
    }
    .visits-meetings-filter-panel .visits-meetings-filter-btn {
        padding:8px 4px;
        font-size:11px;
        text-align:center;
    }
    .weekly-activity-shell { padding:12px; border-radius:13px; }
    .weekly-activity-head {
        display:grid !important;
        grid-template-columns:minmax(0, 1fr);
        gap:10px;
        margin-bottom:10px;
    }
    .weekly-activity-title {
        font-size:14px;
        line-height:1.15;
    }
    .weekly-activity-head-meta,
    .weekly-activity-filter-stack {
        min-width:0;
        width:100%;
        align-items:stretch !important;
    }
    .weekly-activity-filter-row {
        display:flex !important;
        justify-content:space-between;
        gap:8px;
        align-items:center !important;
    }
    .weekly-activity-filter-group {
        display:grid !important;
        grid-template-columns:repeat(3, minmax(0, 1fr));
        gap:6px;
        width:100%;
    }
    .weekly-activity-filter-btn {
        padding:7px 4px;
        font-size:10px;
        text-align:center;
    }
    .weekly-activity-chip {
        justify-content:center;
        width:100%;
        font-size:10px;
        padding:7px 8px;
    }
    .weekly-activity-grid {
        grid-template-columns:1fr !important;
        gap:10px;
    }
    .weekly-activity-card { padding:12px; border-radius:12px; }
    .weekly-activity-top {
        margin-bottom:9px;
        gap:8px;
    }
    .weekly-activity-name {
        font-size:13px;
        gap:8px;
    }
    .weekly-activity-icon {
        width:28px;
        height:28px;
        border-radius:9px;
        font-size:12px;
    }
    .weekly-activity-badge { font-size:9px; padding:5px 7px; }
    .weekly-activity-stats {
        grid-template-columns:repeat(3, minmax(0, 1fr)) !important;
        gap:0;
    }
    .weekly-activity-stat { padding:9px 1px; overflow:hidden; }
    .weekly-activity-stat strong {
        font-size:18px;
        margin-bottom:5px;
    }
    .weekly-activity-stat span { min-height:10px; font-size:7px; line-height:1; letter-spacing:0; }
    .weekly-activity-progress {
        margin-top:8px;
    }
    .weekly-activity-filter-toggle {
        width:auto;
        margin-left:auto;
        padding:7px 10px;
        font-size:11px;
    }
    .weekly-activity-filter-panel {
        width:100%;
        left:0;
        right:auto;
        top:44px;
        margin-top:0;
    }
    [data-section="pipeline-funnel"] .section-card-header {
        display:grid;
        grid-template-columns:minmax(0, 1fr) auto;
        align-items:center;
    }
    .pipeline-filter-toggle { display:none !important; }
    [data-section="pipeline-funnel"] .section-filter-group {
          width:min(100vw - 48px, 420px);
          top:46px;
          right:0;
          left:auto;
          padding:12px;
    }
    [data-section="pipeline-funnel"] .section-filter-group.is-open {
          display:block;
      }
    [data-section="pipeline-funnel"] .section-filter-btn {
            padding:8px 4px;
            font-size:10px;
            text-align:center;
        }
      .pipeline-filter-options {
          display:grid;
          grid-template-columns:repeat(3, minmax(0, 1fr));
          gap:6px;
      }
      .section-filter-custom-range,
      .section-filter-custom-range.is-open {
          display:grid;
          grid-template-columns:1fr 1fr;
          gap:6px;
      }
      .section-filter-custom-range .section-filter-btn.apply {
          grid-column:1 / -1;
      }
    [data-section="user-pipeline"],
    [data-section="user-visits-meetings"],
    [data-section="call-statistics"],
    [data-section="team-overview"],
    [data-section="recent-activities"] {
        display:none !important;
    }
    .sales-score-mobile-grid {
        display:grid;
        gap:8px;
    }
    .sales-score-mobile-card {
        border:1px solid #ece8df;
        border-radius:13px;
        background:#fff;
        padding:10px;
    }
    .sales-score-mobile-head {
        display:flex;
        align-items:center;
        gap:8px;
        margin-bottom:9px;
    }
    .sales-score-mobile-avatar {
        width:28px;
        height:28px;
        border-radius:999px;
        background:#2b73d2;
        color:#fff;
        display:grid;
        place-items:center;
        font-size:11px;
        font-weight:800;
        flex:0 0 28px;
    }
    .sales-score-mobile-name {
        min-width:0;
        font-size:12px;
        font-weight:800;
        color:#111827;
        white-space:nowrap;
        overflow:hidden;
        text-overflow:ellipsis;
    }
    .sales-score-mobile-metrics {
        display:grid;
        grid-template-columns:repeat(4, minmax(0, 1fr));
        gap:6px;
    }
    .sales-score-mobile-metric {
        border:1px solid #eef0ec;
        border-radius:10px;
        background:#fafbf9;
        padding:7px 4px;
        text-align:center;
    }
    .sales-score-mobile-metric strong {
        display:block;
        font-size:13px;
        line-height:1;
        font-weight:900;
    }
    .sales-score-mobile-metric span {
        display:block;
        margin-top:5px;
        font-size:7px;
        line-height:1.1;
        letter-spacing:.05em;
        text-transform:uppercase;
        color:#6f766f;
        font-weight:800;
    }
    .sales-score-mobile-metric .no-response-pill {
        display:inline-flex;
        margin:0;
        color:inherit;
        font-size:11px;
        letter-spacing:0;
        text-transform:none;
    }
    .shortcut-card {
        min-height: 72px;
        padding: 12px;
    }
    .funnel-row,
    .funnel-row-link {
        align-items: flex-start;
        gap: 8px;
    }
    .funnel-label {
        width: 68px;
        font-size: 11px;
    }
    .funnel-bar-wrap {
        min-width: 0;
    }
    .funnel-bar {
        min-width: 40px;
        padding: 0 10px;
        font-size: 11px;
    }
    .funnel-meta {
        width: 42px;
        font-size: 11px;
    }
    .chart-container {
        height: 200px;
    }
    .metric-modal {
        padding: 12px;
    }
    .metric-modal-card {
        padding: 16px;
        border-radius: 16px;
    }
}
</style>
@endpush

@section('content')
<div id="dashboard-content" class="admin-dashboard-root">
    <div class="admin-dashboard-shell">

    {{-- ── Loading State ──────────────────────────────────── --}}
    <section class="admin-hero erp-compact">
        <div class="admin-hero-grid">
            <div>
                <div class="admin-hero-head">
                    <div class="admin-hero-title">Admin Dashboard</div>
                    <div class="admin-hero-actions">
                        <div class="admin-hero-clock">
                            <div id="adminClockTime" class="admin-hero-clock-time">--:--:--</div>
                            <div id="adminClockDate" class="admin-hero-clock-date">-- -- ----</div>
                        </div>
                        <form action="{{ route('logout') }}" method="POST" style="display:inline;">
                            @csrf
                            <button type="submit" class="admin-hero-logout">
                                <i class="fas fa-sign-out-alt"></i>
                                Logout
                            </button>
                        </form>
                    </div>
                </div>
                <div class="admin-hero-mode">
                    <div>
                        <div class="premium-filter-label">Dashboard Mode</div>
                        <div class="dashboard-mode-toggle" style="margin-top:6px;">
                            <button type="button" class="dashboard-mode-btn sale is-active" id="dashboard-mode-sale" onclick="switchDashboardMode('sale')">Sale</button>
                            <button type="button" class="dashboard-mode-btn marketing" id="dashboard-mode-marketing" onclick="switchDashboardMode('marketing')">Marketing</button>
                            <button type="button" class="dashboard-mode-btn finance" id="dashboard-mode-finance" onclick="switchDashboardMode('finance')">Finance</button>
                            <button type="button" class="dashboard-mode-btn hr" id="dashboard-mode-hr" onclick="switchDashboardMode('hr')">HR</button>
                        </div>
                    </div>
                    <button type="button" class="mobile-dashboard-filter-toggle" onclick="toggleMobilePanel('admin-period-filter-panel', this)" aria-controls="admin-period-filter-panel" aria-expanded="false" aria-label="Open dashboard filters" title="Filters">
                        <i class="fas fa-filter"></i>
                        <span>Filters</span>
                    </button>
                </div>
            </div>
        </div>
    </section>

    {{-- ── Date Filter ────────────────────────────────────── --}}
    <div class="section-card admin-period-filter-panel erp-compact" id="admin-period-filter-panel" style="margin-bottom:16px;">
        <div class="premium-filter-bar">
            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                <span class="premium-filter-label" style="white-space:nowrap;">Period</span>
                <button onclick="applyDateFilter('today'); closeMobilePanel('admin-period-filter-panel')"   id="filter-today"  class="date-filter-btn">Today</button>
                <button onclick="applyDateFilter('week'); closeMobilePanel('admin-period-filter-panel')"    id="filter-week"   class="date-filter-btn">This Week</button>
                <button onclick="applyDateFilter('month'); closeMobilePanel('admin-period-filter-panel')"   id="filter-month"  class="date-filter-btn active">This Month</button>
                <button onclick="applyDateFilter('year'); closeMobilePanel('admin-period-filter-panel')"    id="filter-year"   class="date-filter-btn">This Year</button>
            </div>
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-left:auto;">
                <button type="button" class="dashboard-customize-btn" onclick="openDashboardCustomize()">
                    <i class="fas fa-sliders-h"></i>
                    Customize Dashboard
                </button>
                <span style="font-size:12px;color:#6b7280;font-weight:500;">Custom:</span>
                <input type="date" id="custom-start-date" onchange="applyCustomDateFilter()"
                    style="padding:6px 10px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:12.5px;color:#374151;outline:none;background:#f9fafb;">
                <span style="font-size:12px;color:#9ca3af;">→</span>
                <input type="date" id="custom-end-date" onchange="applyCustomDateFilter()"
                    style="padding:6px 10px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:12.5px;color:#374151;outline:none;background:#f9fafb;">
            </div>
        </div>
    </div>

    <div id="loading" class="dashboard-skeleton" aria-hidden="true">
        <div class="dashboard-skeleton-status">
            <div class="dashboard-skeleton-status-copy">
                <span class="dashboard-skeleton-badge"><span class="dashboard-skeleton-dot"></span>Loading live dashboard</span>
                <div class="dashboard-skeleton-line title"></div>
                <div class="dashboard-skeleton-line subtitle"></div>
            </div>
            <div class="dashboard-skeleton-line pill"></div>
        </div>

        <div class="dashboard-skeleton-row stats">
            @for ($i = 0; $i < 4; $i++)
                <div class="dashboard-skeleton-card dashboard-skeleton-stat">
                    <div class="dashboard-skeleton-line kpi"></div>
                    <div class="dashboard-skeleton-line label"></div>
                </div>
            @endfor
        </div>

        <div class="dashboard-skeleton-row main">
            <div class="dashboard-skeleton-card dashboard-skeleton-panel">
                <div class="dashboard-skeleton-line section-title"></div>
                <div class="dashboard-skeleton-chart">
                    <div class="dashboard-skeleton-bar" style="height:48%;"></div>
                    <div class="dashboard-skeleton-bar" style="height:76%;"></div>
                    <div class="dashboard-skeleton-bar" style="height:58%;"></div>
                    <div class="dashboard-skeleton-bar" style="height:86%;"></div>
                    <div class="dashboard-skeleton-bar" style="height:64%;"></div>
                </div>
            </div>
            <div class="dashboard-skeleton-card dashboard-skeleton-panel">
                <div class="dashboard-skeleton-line section-title"></div>
                <div class="dashboard-skeleton-table-grid">
                    @for ($i = 0; $i < 4; $i++)
                        <div>
                            <div class="dashboard-skeleton-line metric-title"></div>
                            <div class="dashboard-skeleton-line metric-copy"></div>
                            <div class="dashboard-skeleton-line metric-copy short" style="margin-top:8px;"></div>
                        </div>
                    @endfor
                </div>
            </div>
        </div>

        <div class="dashboard-skeleton-row bottom">
            <div class="dashboard-skeleton-card dashboard-skeleton-table">
                <div class="dashboard-skeleton-line section-title"></div>
                <div class="dashboard-skeleton-table-grid">
                    @for ($i = 0; $i < 5; $i++)
                        <div class="dashboard-skeleton-table-row">
                            <div class="dashboard-skeleton-line table-cell long"></div>
                            <div class="dashboard-skeleton-line table-cell short"></div>
                            <div class="dashboard-skeleton-line table-cell short"></div>
                            <div class="dashboard-skeleton-line table-cell medium"></div>
                            <div class="dashboard-skeleton-line table-cell short"></div>
                        </div>
                    @endfor
                </div>
            </div>
            <div class="dashboard-skeleton-card dashboard-skeleton-panel">
                <div class="dashboard-skeleton-line section-title"></div>
                <div class="dashboard-skeleton-chart" style="height:220px;align-items:center;">
                    <div class="dashboard-skeleton-line metric-copy" style="height:100%;border-radius:20px;"></div>
                </div>
            </div>
        </div>
    </div>

    <div id="sale-dashboard-panel" class="dashboard-panel is-active">
    <div id="dashboard-shortcuts" class="shortcut-grid dashboard-span-full" style="margin-bottom:0;">
        <a class="shortcut-card" href="#">
            <span class="shortcut-icon"><i class="fas fa-layer-group"></i></span>
            <span><div class="shortcut-label">Dashboard</div><div class="shortcut-value">0</div></span>
        </a>
    </div>

    {{-- ── KPI Stats Row ───────────────────────────────────── --}}
    <div id="main-stats" style="display:none;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:0;" class="grid dashboard-span-full">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div><div class="stat-value" id="total-leads">0</div><div class="stat-label">All Leads</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
            <div><div class="stat-value" id="total-meetings">0</div><div class="stat-label">Meetings</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-eye"></i></div>
            <div><div class="stat-value" id="total-visits">0</div><div class="stat-label">Visits</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-flag"></i></div>
            <div><div class="stat-value" id="total-closers">0</div><div class="stat-label">Closers</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
            <div><div class="stat-value" id="total-dead">0</div><div class="stat-label">Dead</div></div>
        </div>
    </div>

    <div id="approval-center-section" class="section-card dashboard-section dashboard-orderable approval-center-card" data-section="approval-center">
        <div class="approval-center-head">
            <div class="approval-center-title-row">
                <div>
                    <div class="approval-center-eyebrow">Unified Queue</div>
                    <div class="approval-center-title">Pending approvals</div>
                </div>
                <button type="button" class="compact-card-filter-trigger" onclick="toggleMobilePanel('approval-center-filters', this)" aria-controls="approval-center-filters" aria-expanded="false" aria-label="Filter approvals" title="Filter approvals">
                    <i class="fas fa-filter"></i>
                </button>
            </div>
            <div class="approval-center-filters" id="approval-center-filters">
                <button type="button" class="approval-filter-btn active" data-approval-filter="all" onclick="filterApprovalCenter('all', this); closeMobilePanel('approval-center-filters')">All</button>
                <button type="button" class="approval-filter-btn" data-approval-filter="finance" onclick="filterApprovalCenter('finance', this); closeMobilePanel('approval-center-filters')">Finance</button>
                <button type="button" class="approval-filter-btn" data-approval-filter="hr" onclick="filterApprovalCenter('hr', this); closeMobilePanel('approval-center-filters')">HR</button>
                <button type="button" class="approval-filter-btn" data-approval-filter="leave" onclick="filterApprovalCenter('leave', this); closeMobilePanel('approval-center-filters')">Leave</button>
                <button type="button" class="approval-filter-btn" data-approval-filter="po" onclick="filterApprovalCenter('po', this); closeMobilePanel('approval-center-filters')">PO</button>
                <button type="button" class="approval-filter-btn" data-approval-filter="export" onclick="filterApprovalCenter('export', this); closeMobilePanel('approval-center-filters')">Export</button>
            </div>
        </div>
        <div id="approval-center-list" class="approval-center-list">
            <div class="approval-empty">Loading approvals...</div>
        </div>
    </div>

    {{-- ── Lead Statistics + Property Segments ──────────────── --}}
    <div class="dashboard-column-stack dashboard-orderable dashboard-column-span-1" data-section="performance-funnel">
        <div class="section-card dashboard-section" data-section="performance-overview" style="margin-bottom:0;">
            <div class="section-title">
                <span class="section-title-icon"><i class="fas fa-gauge-high"></i></span>
                Performance Scores
            </div>
            <div id="performance-scores" class="score-card-grid">
                <div class="score-card"><div class="value">0%</div><div class="label">PS Score</div><div class="note">Loading...</div></div>
            </div>
            <div class="weekly-activity-wrap">
                <div id="weekly-activity-summary" class="weekly-activity-shell">
                    <div class="weekly-activity-head">
                        <div>
                            <div class="weekly-activity-title">Activity Summary</div>
                            <div class="weekly-activity-copy"></div>
                        </div>
                        <span class="weekly-activity-chip">This Week</span>
                    </div>
                    <div class="weekly-activity-grid">
                        <div class="weekly-activity-card">
                            <div class="weekly-activity-top">
                                <div class="weekly-activity-name">
                                    <span class="weekly-activity-icon meetings"><i class="fas fa-calendar-check"></i></span>
                                    <span>Meetings</span>
                                </div>
                                <span class="weekly-activity-badge">Loading</span>
                            </div>
                        </div>
                        <div class="weekly-activity-card">
                            <div class="weekly-activity-top">
                                <div class="weekly-activity-name">
                                    <span class="weekly-activity-icon visits"><i class="fas fa-map-marker-alt"></i></span>
                                    <span>Visits</span>
                                </div>
                                <span class="weekly-activity-badge">Loading</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="performance-target-incentive-grid">
            <div class="section-card team-targets-card" style="margin-bottom:0;">
                <div class="section-title">
                    <span class="section-title-icon"><i class="fas fa-bullseye"></i></span>
                    Team Targets
                </div>
                <div id="team-targets-summary" class="progress-list">
                    <div class="progress-item-head"><strong>Meetings</strong><span>0 / 0</span></div>
                    <div class="progress-track"><div class="progress-fill" style="width:0%;background:#1761a8;"></div></div>
                </div>
                <button type="button" onclick="openTargetsModal()" class="date-filter-btn" style="margin-top:16px;">View Per User Breakdown</button>
            </div>
            <div class="section-card dashboard-orderable" id="incentives-summary-section" data-section="targets-incentives" style="margin-bottom:0;">
                <div class="section-title">
                    <span class="section-title-icon"><i class="fas fa-indian-rupee-sign"></i></span>
                    Incentives
                </div>
                <div id="incentive-summary" class="score-card-grid">
                    <div class="score-card"><div class="value">0</div><div class="label">Total</div><div class="note">Loading...</div></div>
                </div>
            </div>
        </div>
    </div>

    <div class="section-card dashboard-section dashboard-orderable dashboard-column-span-1" data-section="pipeline-funnel">
        <div class="section-card-header">
            <div class="section-title">
                <span class="section-title-icon"><i class="fas fa-filter"></i></span>
                Pipeline Funnel
            </div>
            <div class="section-filter-shell">
                <span class="section-filter-summary" id="pipeline-filter-summary">Month</span>
                <button type="button" class="pipeline-filter-trigger" onclick="toggleMobilePanel('pipeline-filter-panel', this)" aria-controls="pipeline-filter-panel" aria-expanded="false" title="Open filters">
                    <i class="fas fa-sliders-h"></i>
                </button>
                <div class="section-filter-group" id="pipeline-filter-panel" data-filter-group="pipeline_filter">
                    <div class="pipeline-filter-panel-head">
                        <span class="pipeline-filter-panel-title">Filter Funnel</span>
                        <button type="button" class="pipeline-filter-close" onclick="closeMobilePanel('pipeline-filter-panel', this)" aria-label="Close filters">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="pipeline-filter-options">
                        <button type="button" class="section-filter-btn" data-section-filter="pipeline_filter" data-value="global" onclick="applySectionFilter('pipeline_filter','global', this)">Global</button>
                        <button type="button" class="section-filter-btn" data-section-filter="pipeline_filter" data-value="today" onclick="applySectionFilter('pipeline_filter','today', this)">Today</button>
                        <button type="button" class="section-filter-btn" data-section-filter="pipeline_filter" data-value="week" onclick="applySectionFilter('pipeline_filter','week', this)">Week</button>
                        <button type="button" class="section-filter-btn" data-section-filter="pipeline_filter" data-value="month" onclick="applySectionFilter('pipeline_filter','month', this)">Month</button>
                        <button type="button" class="section-filter-btn" data-section-filter="pipeline_filter" data-value="year" onclick="applySectionFilter('pipeline_filter','year', this)">Year</button>
                        <button type="button" class="section-filter-btn" data-section-filter="pipeline_filter" data-value="custom" onclick="applySectionFilter('pipeline_filter','custom', this)">Custom</button>
                    </div>
                    <div class="section-filter-custom-range pipeline-custom-range" id="pipeline-custom-range">
                        <label>
                            <span>From</span>
                            <input type="date" id="pipeline-custom-start" onchange="handlePipelineDateInput(this, 'start')">
                        </label>
                        <label>
                            <span>To</span>
                            <input type="date" id="pipeline-custom-end" onchange="handlePipelineDateInput(this, 'end')">
                        </label>
                        <button type="button" class="section-filter-btn apply" onclick="applyPipelineCustomRange()">Apply</button>
                    </div>
                </div>
            </div>
        </div>
        <div id="pipeline-funnel" class="funnel-stack">
            <div class="funnel-row"><div class="funnel-label">Leads</div><div class="funnel-bar-wrap"><div class="funnel-bar" style="width:40px;background:#0b6b4f;">0</div></div><div class="funnel-meta">0%</div></div>
        </div>
    </div>

    <div class="section-card dashboard-section dashboard-orderable" data-section="demand-insights">
        <div class="section-title">
            <span class="section-title-icon"><i class="fas fa-chart-simple"></i></span>
            Demand Insights
        </div>
        <div class="demand-insights-grid">
            <div class="demand-insight-card">
                <div class="demand-insight-head">
                    <div>
                        <div class="demand-insight-title">Property Type Mix</div>
                        <div class="demand-insight-copy">Interested aur verified demand category-wise.</div>
                        <div class="demand-chart-toggle" data-demand-toggle="property">
                            <button type="button" data-demand-chart-type="doughnut" onclick="setDemandChartType('property', 'doughnut', this)">Donut</button>
                            <button type="button" data-demand-chart-type="bar" onclick="setDemandChartType('property', 'bar', this)">Bar</button>
                        </div>
                    </div>
                    <div class="demand-insight-actions">
                        <div class="demand-insight-total">
                            <strong id="demand-property-total">0</strong>
                            <span>Total</span>
                        </div>
                        <button type="button" class="compact-card-filter-trigger" onclick="toggleMobilePanel('demand-property-filter-panel', this)" aria-controls="demand-property-filter-panel" aria-expanded="false" aria-label="Filter property mix" title="Filter property mix"><i class="fas fa-filter"></i></button>
                    </div>
                </div>
                <div id="demand-property-filter-panel" class="demand-filter-panel">
                    <div class="demand-filter-panel-head"><span class="demand-filter-panel-title">Filter Demand Mix</span><button type="button" class="demand-filter-panel-close" onclick="closeMobilePanel('demand-property-filter-panel')" aria-label="Close"><i class="fas fa-times"></i></button></div>
                    <div class="demand-filter-options">
                        <button type="button" class="section-filter-btn" data-section-filter="demand_filter" data-value="global" onclick="applyDemandFilter('global', this)">Global</button>
                        <button type="button" class="section-filter-btn" data-section-filter="demand_filter" data-value="today" onclick="applyDemandFilter('today', this)">Today</button>
                        <button type="button" class="section-filter-btn" data-section-filter="demand_filter" data-value="week" onclick="applyDemandFilter('week', this)">Week</button>
                        <button type="button" class="section-filter-btn" data-section-filter="demand_filter" data-value="month" onclick="applyDemandFilter('month', this)">Month</button>
                        <button type="button" class="section-filter-btn" data-section-filter="demand_filter" data-value="year" onclick="applyDemandFilter('year', this)">Year</button>
                        <button type="button" class="section-filter-btn" data-section-filter="demand_filter" data-value="custom" onclick="applyDemandFilter('custom', this)">Custom</button>
                    </div>
                    <div class="demand-custom-range">
                        <label><span>From</span><input type="date" data-demand-date="start" onchange="handleDemandDateInput(this, 'start')"></label>
                        <label><span>To</span><input type="date" data-demand-date="end" onchange="handleDemandDateInput(this, 'end')"></label>
                        <button type="button" class="section-filter-btn apply" onclick="applyDemandCustomRange()">Apply</button>
                    </div>
                </div>
                <div class="demand-insight-chart"><canvas id="adminDemandPropertyChart"></canvas></div>
                <div id="demand-property-list" class="demand-insight-list">
                    <div class="demand-insight-row"><span class="label">No data</span><span class="count">0</span><span class="percent">0%</span></div>
                </div>
            </div>
            <div class="demand-insight-card">
                <div class="demand-insight-head">
                    <div>
                        <div class="demand-insight-title">Budget Mix</div>
                        <div class="demand-insight-copy">Demand kis budget range me aa rahi hai.</div>
                        <div class="demand-chart-toggle" data-demand-toggle="budget">
                            <button type="button" data-demand-chart-type="doughnut" onclick="setDemandChartType('budget', 'doughnut', this)">Donut</button>
                            <button type="button" data-demand-chart-type="bar" onclick="setDemandChartType('budget', 'bar', this)">Bar</button>
                        </div>
                    </div>
                    <div class="demand-insight-actions">
                        <div class="demand-insight-total">
                            <strong id="demand-budget-total">0</strong>
                            <span>Total</span>
                        </div>
                        <button type="button" class="compact-card-filter-trigger" onclick="toggleMobilePanel('demand-budget-filter-panel', this)" aria-controls="demand-budget-filter-panel" aria-expanded="false" aria-label="Filter budget mix" title="Filter budget mix"><i class="fas fa-filter"></i></button>
                    </div>
                </div>
                <div id="demand-budget-filter-panel" class="demand-filter-panel">
                    <div class="demand-filter-panel-head"><span class="demand-filter-panel-title">Filter Demand Mix</span><button type="button" class="demand-filter-panel-close" onclick="closeMobilePanel('demand-budget-filter-panel')" aria-label="Close"><i class="fas fa-times"></i></button></div>
                    <div class="demand-filter-options">
                        <button type="button" class="section-filter-btn" data-section-filter="demand_filter" data-value="global" onclick="applyDemandFilter('global', this)">Global</button>
                        <button type="button" class="section-filter-btn" data-section-filter="demand_filter" data-value="today" onclick="applyDemandFilter('today', this)">Today</button>
                        <button type="button" class="section-filter-btn" data-section-filter="demand_filter" data-value="week" onclick="applyDemandFilter('week', this)">Week</button>
                        <button type="button" class="section-filter-btn" data-section-filter="demand_filter" data-value="month" onclick="applyDemandFilter('month', this)">Month</button>
                        <button type="button" class="section-filter-btn" data-section-filter="demand_filter" data-value="year" onclick="applyDemandFilter('year', this)">Year</button>
                        <button type="button" class="section-filter-btn" data-section-filter="demand_filter" data-value="custom" onclick="applyDemandFilter('custom', this)">Custom</button>
                    </div>
                    <div class="demand-custom-range">
                        <label><span>From</span><input type="date" data-demand-date="start" onchange="handleDemandDateInput(this, 'start')"></label>
                        <label><span>To</span><input type="date" data-demand-date="end" onchange="handleDemandDateInput(this, 'end')"></label>
                        <button type="button" class="section-filter-btn apply" onclick="applyDemandCustomRange()">Apply</button>
                    </div>
                </div>
                <div class="demand-insight-chart"><canvas id="adminDemandBudgetChart"></canvas></div>
                <div id="demand-budget-list" class="demand-insight-list">
                    <div class="demand-insight-row"><span class="label">No data</span><span class="count">0</span><span class="percent">0%</span></div>
                </div>
            </div>
        </div>
    </div>

    <div class="section-card dashboard-section dashboard-orderable dashboard-span-full" data-section="sales-score-table">
        <div class="section-card-header">
            <div class="section-title">
                <span class="section-title-icon"><i class="fas fa-ranking-star"></i></span>
                Sales Team - PS / PP / VP Scores
            </div>
            <div class="sales-score-head-actions">
                <div class="sales-score-column-picker">
                    <button type="button" id="sales-score-columns-btn" class="sales-score-columns-btn" aria-expanded="false" aria-controls="sales-score-column-popover" onclick="toggleSalesScoreColumnPopover(event)">
                        <i class="fas fa-table-columns" aria-hidden="true"></i>
                        Columns
                    </button>
                    <div id="sales-score-column-popover" class="sales-score-column-popover" role="dialog" aria-label="Choose score table columns" hidden>
                        <div class="sales-score-column-popover-head">
                            <strong>Choose columns</strong>
                            <span>Select what you want to see in this table.</span>
                        </div>
                        <div class="sales-score-column-options">
                            <label class="sales-score-column-option locked"><input type="checkbox" checked disabled> User</label>
                            @foreach([
                                'role' => 'Role',
                                'leads' => 'Assigned Leads',
                                'no_response' => 'No Response',
                                'oldest_assigned' => 'Oldest Assigned',
                                'meetings' => 'Meetings',
                                'visits' => 'Visits',
                                'closers' => 'Closers',
                                'junk' => 'Junk',
                                'not_interested' => 'Not Interested',
                                'other' => 'Other',
                                'ps' => 'PS %',
                                'pp' => 'PP %',
                                'vp' => 'VP %',
                                'avg_response' => 'Avg Response',
                                'action' => 'Action',
                            ] as $columnKey => $columnLabel)
                                <label class="sales-score-column-option">
                                    <input type="checkbox" data-sales-score-column="{{ $columnKey }}">
                                    {{ $columnLabel }}
                                </label>
                            @endforeach
                        </div>
                        <div class="sales-score-column-quick-actions">
                            <button type="button" class="sales-score-column-link" onclick="setSalesScoreColumnDraft('all')">Select All</button>
                            <button type="button" class="sales-score-column-link" onclick="setSalesScoreColumnDraft('clear')">Clear Optional</button>
                            <button type="button" class="sales-score-column-link" onclick="setSalesScoreColumnDraft('default')">Reset Default</button>
                        </div>
                        <p id="sales-score-column-error" class="sales-score-column-error" aria-live="polite"></p>
                        <div class="sales-score-column-actions">
                            <button type="button" class="sales-score-column-action" onclick="closeSalesScoreColumnPopover()">Cancel</button>
                            <button type="button" id="sales-score-column-apply" class="sales-score-column-action primary" onclick="applySalesScoreColumns()">Apply</button>
                        </div>
                    </div>
                </div>
                <div class="section-filter-group" data-filter-group="sales_score_filter">
                    <button type="button" class="section-filter-btn" data-section-filter="sales_score_filter" data-value="global" onclick="applySectionFilter('sales_score_filter','global', this)">Global</button>
                    <button type="button" class="section-filter-btn" data-section-filter="sales_score_filter" data-value="today" onclick="applySectionFilter('sales_score_filter','today', this)">Today</button>
                    <button type="button" class="section-filter-btn" data-section-filter="sales_score_filter" data-value="week" onclick="applySectionFilter('sales_score_filter','week', this)">Week</button>
                    <button type="button" class="section-filter-btn" data-section-filter="sales_score_filter" data-value="month" onclick="applySectionFilter('sales_score_filter','month', this)">Month</button>
                    <button type="button" class="section-filter-btn" data-section-filter="sales_score_filter" data-value="year" onclick="applySectionFilter('sales_score_filter','year', this)">Year</button>
                </div>
            </div>
        </div>
        <div id="sales-score-table" style="overflow-x:auto;">
            <p style="color:#9ca3af;font-size:13px;">Loading...</p>
        </div>
    </div>

    <div class="section-card dashboard-section dashboard-orderable dashboard-span-full" data-section="sales-user-activity">
        <div class="section-card-header">
            <div>
                <div class="section-title">
                    <span class="section-title-icon"><i class="fas fa-table-list"></i></span>
                    Sales User Activity
                </div>
                <p style="margin:4px 0 0;color:#64748b;font-size:13px;">Fresh leads, overdue tasks, and visit movement by user.</p>
            </div>
        </div>
        <div id="sales-user-activity-table" class="sales-user-activity-wrap">
            <p style="color:#9ca3af;font-size:13px;padding:14px;">Loading...</p>
        </div>
    </div>

    <div class="section-card dashboard-section dashboard-orderable dashboard-span-full" data-section="user-pipeline">
        <div class="section-title">
            <span class="section-title-icon"><i class="fas fa-users-viewfinder"></i></span>
            User View - Meetings, Visits & Closures
        </div>
        <div id="user-pipeline-table" style="overflow-x:auto;">
            <p style="color:#9ca3af;font-size:13px;">Loading...</p>
        </div>
    </div>

    <div class="section-card dashboard-section dashboard-orderable dashboard-span-full" data-section="sales-performance">
        <div class="section-title">
            <span class="section-title-icon"><i class="fas fa-trophy"></i></span>
            Sales Executive Performance
        </div>
        <div id="telecaller-performance-cards" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px;">
            <p style="color:#9ca3af;font-size:13px;">Loading...</p>
        </div>
    </div>

    {{-- ── Leads Allocated + Avg Response ──────────────────── --}}
    <div class="legacy-leads-allocated-section" aria-hidden="true">
        <div class="section-title">
            <span class="section-title-icon"><i class="fas fa-tasks"></i></span>
            Leads Allocated
        </div>
        <div style="display:grid;grid-template-columns:3fr 1fr;gap:16px;align-items:start;" class="grid-responsive-leads">
            <div class="admin-table-shell">
                <table>
                    <thead>
                        <tr>
                            <th style="width:36px;"></th>
                            <th>Sales Executive</th>
                            <th style="text-align:center;">No Response</th>
                            <th>Oldest Assigned</th>
                        </tr>
                    </thead>
                    <tbody id="leads-pending-response-tbody">
                        <tr><td colspan="4" style="text-align:center;color:#9ca3af;padding:20px;">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="admin-side-panel">
                <div class="admin-side-panel-title">Avg Response Time</div>
                <div id="average-response-time-panel">
                    <p class="compact-muted">Loading...</p>
                </div>
            </div>
        </div>
    </div>

    {{-- ── User Stats + Recent Activities (2 col) ──────────── --}}
    <div class="section-card dashboard-section dashboard-orderable dashboard-two-up-item" data-section="team-overview" style="margin-bottom:0;">
            <div class="section-title">
                <span class="section-title-icon"><i class="fas fa-user-cog"></i></span>
                Team Overview
            </div>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:14px;">
                <div class="mini-stat" style="text-align:center;"><div class="mini-stat-val" id="users-admin">0</div><div class="mini-stat-lbl">Admin</div></div>
                <div class="mini-stat" style="text-align:center;"><div class="mini-stat-val" id="users-crm">0</div><div class="mini-stat-lbl">CRM</div></div>
                <div class="mini-stat" style="text-align:center;"><div class="mini-stat-val" id="users-sales-manager">0</div><div class="mini-stat-lbl">Sr. Manager</div></div>
                <div class="mini-stat" style="text-align:center;"><div class="mini-stat-val" id="users-sales-executive">0</div><div class="mini-stat-lbl">Sales Exec</div></div>
                <div class="mini-stat" style="text-align:center;"><div class="mini-stat-val" id="users-telecaller">0</div><div class="mini-stat-lbl">Telecaller</div></div>
                <div class="mini-stat" style="text-align:center;"><div class="mini-stat-val" id="users-total">0</div><div class="mini-stat-lbl">Total Active</div></div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:12px;">
                    <div style="font-size:20px;font-weight:700;color:#065f46;" id="users-new-month">0</div>
                    <div style="font-size:11px;color:#6b7280;margin-top:3px;">New This Month</div>
                </div>
                <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:12px;">
                    <div style="font-size:20px;font-weight:700;color:#1e40af;" id="users-active-24h">0</div>
                    <div style="font-size:11px;color:#6b7280;margin-top:3px;">Active (24h)</div>
                </div>
            </div>
    </div>
    <div class="section-card dashboard-section dashboard-orderable dashboard-two-up-item" data-section="recent-activities" style="margin-bottom:0;">
            <div class="section-title">
                <span class="section-title-icon"><i class="fas fa-history"></i></span>
                Recent Activities
            </div>
            <div id="recent-activities" style="max-height:280px;overflow-y:auto;">
                <p style="color:#9ca3af;font-size:13px;">Loading...</p>
            </div>
    </div>

    {{-- ── Recent Leads ──────────────────────────────────────── --}}
    <div class="section-card dashboard-section dashboard-orderable dashboard-span-full" data-section="recent-leads">
        <div class="section-title" style="justify-content:space-between;">
            <div style="display:flex;align-items:center;gap:9px;">
                <span class="section-title-icon"><i class="fas fa-user-plus"></i></span>
                Recent Leads
            </div>
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                <select id="recent-leads-source-filter" onchange="applyRecentLeadsSourceFilter(this.value)" style="padding:7px 11px;border:1px solid #d1d5db;border-radius:10px;font-size:12px;background:#fff;color:#374151;min-width:140px;">
                    <option value="">All Sources</option>
                    @foreach($dashboardSourceOptions as $sourceValue => $sourceLabel)
                        <option value="{{ $sourceValue }}">{{ $sourceLabel }}</option>
                    @endforeach
                </select>
                <a href="{{ route('leads.index') }}" style="font-size:12px;font-weight:600;color:#205A44;text-decoration:none;">View All →</a>
            </div>
        </div>
        <div id="recent-leads" style="overflow-x:auto;">
            <p style="color:#9ca3af;font-size:13px;">Loading...</p>
        </div>
    </div>

    </div>

    <div id="marketing-dashboard-panel" class="dashboard-panel">
        <div class="premium-grid-4" style="margin-bottom:22px;display:none;">
            <div class="marketing-stat">
                <div class="value" id="marketing-total-batches">0</div>
                <div class="label">Import Batches</div>
                <div class="sub" id="marketing-completed-batches">0 completed batches</div>
            </div>
            <div class="marketing-stat">
                <div class="value" id="marketing-imported-leads">0</div>
                <div class="label">Imported Leads</div>
                <div class="sub" id="marketing-pending-batches">0 pending or processing</div>
            </div>
            <div class="marketing-stat">
                <div class="value" id="marketing-junk-leads">0</div>
                <div class="label">Junk Leads</div>
                <div class="sub">Current reporting window quality drop-offs</div>
            </div>
            <div class="marketing-stat">
                <div class="value" id="marketing-not-interested">0</div>
                <div class="label">Not Interested</div>
                <div class="sub">Use this to inspect source quality and recycle flow</div>
            </div>
        </div>

        <div id="admin-lead-quality-overview">
            @include('marketing.partials.lead-quality-overview', [
                'leadQualityActionUrl' => route('admin.dashboard'),
                'leadQualityResetUrl' => route('admin.dashboard', ['source' => 'all']),
            ])
        </div>

        <div class="section-card" style="margin-bottom:22px;display:none;">
            <div class="section-card-header">
                <div class="section-title">
                    <span class="section-title-icon"><i class="fas fa-chart-line"></i></span>
                    Meta Decision Analytics
                </div>
                <div class="section-filter-group" data-filter-group="source_filter">
                    <button type="button" class="section-filter-btn meta-sync-btn meta-sync-trigger" id="meta-spend-sync-btn" onclick="syncMetaSpend(this)">
                        <i class="fas fa-sync-alt"></i> Sync Spend
                    </button>
                    <button type="button" class="section-filter-btn" data-section-filter="source_filter" data-value="global" onclick="applySectionFilter('source_filter','global', this)">Global</button>
                    <button type="button" class="section-filter-btn" data-section-filter="source_filter" data-value="today" onclick="applySectionFilter('source_filter','today', this)">Today</button>
                    <button type="button" class="section-filter-btn" data-section-filter="source_filter" data-value="week" onclick="applySectionFilter('source_filter','week', this)">Week</button>
                    <button type="button" class="section-filter-btn" data-section-filter="source_filter" data-value="month" onclick="applySectionFilter('source_filter','month', this)">Month</button>
                    <button type="button" class="section-filter-btn" data-section-filter="source_filter" data-value="year" onclick="applySectionFilter('source_filter','year', this)">Year</button>
                    <span class="meta-sync-status" id="meta-spend-sync-status"></span>
                </div>
            </div>
            <div class="meta-decision-grid" id="admin-meta-decision-kpis">
                <div class="meta-decision-card"><div class="value">0</div><div class="label">Meta Leads</div><div class="note">Lead Ads intake</div></div>
                <div class="meta-decision-card"><div class="value">Rs 0.00</div><div class="label">Spend</div><div class="note">Synced Meta spend</div></div>
                <div class="meta-decision-card"><div class="value">-</div><div class="label">CPL</div><div class="note">Spend / leads</div></div>
                <div class="meta-decision-card"><div class="value">0</div><div class="label">Closers</div><div class="note">Closed from Meta leads</div></div>
            </div>
            <div class="meta-funnel-grid" id="admin-meta-funnel">
                <div class="meta-funnel-card"><strong>0</strong><span>Received</span><div class="meta-funnel-bar"><i style="width:0%"></i></div></div>
            </div>
            <div class="meta-alert-list" id="admin-meta-alerts" style="display:none;">
                <div class="meta-alert-row"><strong>No Meta alerts yet</strong><span>Analytics will appear after dashboard data loads.</span></div>
            </div>
            <div class="meta-detail-grid">
                <div style="display:none;">
                    <div class="meta-detail-head">
                        <strong>Form Performance</strong>
                        <span>Quality, CPL, hold and closers</span>
                    </div>
                    <div class="source-performance-table-wrap">
                        <table class="source-performance-table">
                            <thead>
                                <tr>
                                    <th rowspan="2" class="source-performance-sticky">Meta Form</th>
                                    <th rowspan="2">Leads</th>
                                    <th rowspan="2">Cost</th>
                                    <th rowspan="2">Quality</th>
                                    <th colspan="3" class="source-performance-group-pipe">Qualified</th>
                                    <th colspan="3" class="source-performance-group-raw">Raw Outcome</th>
                                    <th rowspan="2">Closer</th>
                                    <th rowspan="2">Verdict</th>
                                </tr>
                                <tr>
                                    <th>Interested</th>
                                    <th>Follow-up</th>
                                    <th>Visit</th>
                                    <th>Not</th>
                                    <th>CNP</th>
                                    <th>Pending</th>
                                </tr>
                            </thead>
                            <tbody id="admin-meta-top-form-performance-body">
                                <tr><td colspan="12" style="text-align:center;color:#9ca3af;padding:20px;">Meta form quality will appear here.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div style="display:none;">
                    <div class="meta-detail-head">
                        <strong>Campaign Performance</strong>
                        <span style="display:inline-flex;align-items:center;gap:8px;flex-wrap:wrap;justify-content:flex-end;">
                            <button type="button" class="section-filter-btn meta-sync-btn meta-sync-trigger" onclick="syncMetaSpend(this)">
                                <i class="fas fa-sync-alt"></i> Sync CPL
                            </button>
                            <span>Campaign / adset / ad drilldown</span>
                        </span>
                    </div>
                    <div class="source-performance-table-wrap">
                        <table class="source-performance-table">
                            <thead>
                                <tr>
                                    <th>Campaign / Adset / Ad</th>
                                    <th style="text-align:center;">Leads</th>
                                    <th style="text-align:center;">Spend</th>
                                    <th style="text-align:center;">CPL</th>
                                    <th style="text-align:center;">Avg Quality</th>
                                    <th style="text-align:center;">Hold</th>
                                    <th style="text-align:center;">Closers</th>
                                </tr>
                            </thead>
                            <tbody id="admin-meta-top-campaign-performance-body">
                                <tr><td colspan="7" style="text-align:center;color:#9ca3af;padding:20px;">Meta campaign CPL will appear after ad attribution and spend sync.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="section-card" style="margin-bottom:22px;">
            <div class="section-title">
                <span class="section-title-icon"><i class="fas fa-bullhorn"></i></span>
                Ad Spend Overview
            </div>
            <div class="ad-spend-grid">
                <div class="ad-spend-card">
                    <div class="ad-spend-value" id="ad-spend-total">Rs 0.00</div>
                    <div class="ad-spend-label">Total Ad Spend</div>
                </div>
                <div class="ad-spend-card">
                    <div class="ad-spend-value" id="ad-spend-meta">Rs 0.00</div>
                    <div class="ad-spend-label">Meta Ads Spend</div>
                </div>
                <div class="ad-spend-card">
                    <div class="ad-spend-value" id="ad-spend-google">Rs 0.00</div>
                    <div class="ad-spend-label">Google Ads Spend</div>
                </div>
                <div class="ad-spend-card">
                    <div class="ad-spend-value" id="ad-spend-portal">Rs 0.00</div>
                    <div class="ad-spend-label">Portal Ads Spend</div>
                </div>
                <div class="ad-spend-card">
                    <div class="ad-spend-value" id="ad-spend-other">Rs 0.00</div>
                    <div class="ad-spend-label">Other Ads Spend</div>
                </div>
                <div class="ad-spend-card">
                    <div class="ad-spend-value" id="ad-spend-pending">Rs 0.00</div>
                    <div class="ad-spend-label">Pending Approval</div>
                </div>
            </div>
            <div id="ad-spend-empty" class="ad-spend-empty" style="display:none;">
                No ad spend found for this period. Add Marketing/Ads expense from Finance to see spend here.
            </div>
            <div class="ad-spend-layout" id="ad-spend-content">
                <div>
                    <div class="finance-empty-state" style="margin-bottom:10px;font-weight:800;color:#475569;">Platform, spend, leads and CPL</div>
                    <div class="ad-spend-platform-list" id="ad-spend-platform-list"></div>
                </div>
                <div>
                    <div class="finance-empty-state" style="margin-bottom:10px;font-weight:800;color:#475569;">Latest ad expenses</div>
                    <div class="finance-table-wrap" id="ad-spend-latest-table"></div>
                </div>
            </div>
        </div>

        <div class="premium-grid-2" style="margin-bottom:22px;display:none;">
            <div class="section-card" style="margin-bottom:0;">
                <div class="section-title">
                    <span class="section-title-icon"><i class="fas fa-bullseye"></i></span>
                    Leads by Source
                </div>
                <div class="chart-container"><canvas id="marketingSourceChart"></canvas></div>
                <div id="marketing-source-list" class="marketing-list" style="margin-top:14px;">
                    <div class="marketing-list-item"><strong>No data</strong><span>Source distribution will appear here.</span></div>
                </div>
            </div>
            <div class="section-card" style="margin-bottom:0;">
                <div class="section-title">
                    <span class="section-title-icon"><i class="fas fa-wave-square"></i></span>
                    Lead Inflow
                </div>
                <div class="chart-container"><canvas id="marketingInflowChart"></canvas></div>
                <div id="marketing-import-summary" class="marketing-score-grid" style="margin-top:14px;">
                    <div class="marketing-score-box">
                        <div class="score">0</div>
                        <div class="name">Completed Imports</div>
                        <div class="note">Import summary will render here.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="section-card" style="margin-bottom:22px;">
            <div class="section-card-header">
                <div class="section-title">
                    <span class="section-title-icon"><i class="fas fa-bullseye"></i></span>
                    Meta Form Quality Leaderboard
                </div>
                <div class="section-filter-group" data-filter-group="source_filter">
                    <button type="button" class="section-filter-btn" data-section-filter="source_filter" data-value="global" onclick="applySectionFilter('source_filter','global', this)">Global</button>
                    <button type="button" class="section-filter-btn" data-section-filter="source_filter" data-value="today" onclick="applySectionFilter('source_filter','today', this)">Today</button>
                    <button type="button" class="section-filter-btn" data-section-filter="source_filter" data-value="week" onclick="applySectionFilter('source_filter','week', this)">Week</button>
                    <button type="button" class="section-filter-btn" data-section-filter="source_filter" data-value="month" onclick="applySectionFilter('source_filter','month', this)">Month</button>
                    <button type="button" class="section-filter-btn" data-section-filter="source_filter" data-value="year" onclick="applySectionFilter('source_filter','year', this)">Year</button>
                </div>
            </div>
            <div class="source-performance-grid">
                <div class="source-performance-panel">
                    <div class="chart-container" style="height:250px;"><canvas id="adminLeadSourceChart"></canvas></div>
                    <div class="source-performance-highlights" id="admin-source-highlights">
                        <div class="source-performance-kpi"><div class="value">0</div><div class="label">Meta Forms</div></div>
                        <div class="source-performance-kpi"><div class="value">0</div><div class="label">Total Leads</div></div>
                        <div class="source-performance-kpi"><div class="value">0</div><div class="label">Avg Quality</div></div>
                        <div class="source-performance-kpi"><div class="value">0</div><div class="label">Closers</div></div>
                    </div>
                </div>
                <div class="source-performance-table-wrap">
                    <table class="source-performance-table">
                        <thead>
                            <tr>
                                <th rowspan="2" class="source-performance-sticky">Meta Form</th>
                                <th rowspan="2">Leads</th>
                                <th rowspan="2">Cost</th>
                                <th rowspan="2">Quality</th>
                                <th colspan="3" class="source-performance-group-pipe">Qualified</th>
                                <th colspan="3" class="source-performance-group-raw">Raw Outcome</th>
                                <th rowspan="2">Closer</th>
                                <th rowspan="2">Verdict</th>
                            </tr>
                            <tr>
                                <th>Interested</th>
                                <th>Follow-up</th>
                                <th>Visit</th>
                                <th>Not</th>
                                <th>CNP</th>
                                <th>Pending</th>
                            </tr>
                        </thead>
                        <tbody id="admin-source-performance-body">
                            <tr><td colspan="12" style="text-align:center;color:#9ca3af;padding:20px;">Meta form quality will appear here.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="meta-detail-head" style="margin-top:14px;">
                <strong>Campaign CPL Performance</strong>
                <button type="button" class="section-filter-btn meta-sync-btn meta-sync-trigger" onclick="syncMetaSpend(this)">
                    <i class="fas fa-sync-alt"></i> Sync CPL
                </button>
            </div>
            <div class="source-performance-table-wrap">
                <table class="source-performance-table">
                    <thead>
                        <tr>
                            <th>Campaign / Adset / Ad</th>
                            <th style="text-align:center;">Leads</th>
                            <th style="text-align:center;">Spend</th>
                            <th style="text-align:center;">CPL</th>
                            <th style="text-align:center;">Avg Quality</th>
                            <th style="text-align:center;">Hold</th>
                            <th style="text-align:center;">Closers</th>
                        </tr>
                    </thead>
                    <tbody id="admin-meta-campaign-performance-body">
                        <tr><td colspan="7" style="text-align:center;color:#9ca3af;padding:20px;">Meta campaign CPL will appear here.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="premium-grid-3" style="margin-bottom:22px;display:none;">
            <div class="section-card" style="margin-bottom:0;">
                <div class="section-title">
                    <span class="section-title-icon"><i class="fas fa-filter"></i></span>
                    Quality Snapshot
                </div>
                <div class="marketing-score-grid" id="marketing-quality-grid">
                    <div class="marketing-score-box">
                        <div class="score">0</div>
                        <div class="name">Connected</div>
                        <div class="note">Lead quality split will appear here.</div>
                    </div>
                </div>
            </div>
            <div class="section-card" style="margin-bottom:0;">
                <div class="section-title">
                    <span class="section-title-icon"><i class="fas fa-phone-volume"></i></span>
                    Call Outcomes
                </div>
                <div id="marketing-call-outcomes" class="marketing-list">
                    <div class="marketing-list-item"><strong>No outcomes</strong><span>Call outcome mix will appear here.</span></div>
                </div>
            </div>
            <div class="section-card" style="margin-bottom:0;">
                <div class="section-title">
                    <span class="section-title-icon"><i class="fas fa-user-clock"></i></span>
                    Fresh Leads
                </div>
                <div id="marketing-recent-leads" class="marketing-list">
                    <div class="marketing-list-item"><strong>No recent leads</strong><span>Recent additions will appear here.</span></div>
                </div>
            </div>
        </div>
    </div>

    <div id="dashboard-customize-modal" class="dashboard-customize-modal" onclick="closeDashboardCustomize(event)">
        <div class="dashboard-customize-card" onclick="event.stopPropagation()">
            <div class="dashboard-customize-head">
                <div>
                    <div class="dashboard-customize-title">Customize Dashboard</div>
                    <div class="dashboard-customize-sub">Choose which sections stay visible. Global filter remains on top, while selected sections can use local overrides.</div>
                </div>
                <button type="button" class="dashboard-customize-close" onclick="closeDashboardCustomize()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="dashboard-customize-body" class="dashboard-customize-body"></div>
            <div class="dashboard-customize-foot">
                <button type="button" class="dashboard-customize-secondary" onclick="closeDashboardCustomize()">Cancel</button>
                <button type="button" class="dashboard-customize-primary" onclick="saveDashboardCustomize()">Save Layout</button>
            </div>
        </div>
    </div>

    <div id="finance-dashboard-panel" class="dashboard-panel">
        <div class="finance-summary-grid">
            <div class="finance-kpi-card">
                <div class="finance-kpi-label" id="finance-total-label">Month Total</div>
                <div class="finance-kpi-value" id="finance-month-total">Rs 0.00</div>
                <div class="finance-kpi-note" id="finance-month-note">Spend overview</div>
            </div>
            <div class="finance-kpi-card">
                <div class="finance-kpi-label">Pending Approval</div>
                <div class="finance-kpi-value" id="finance-pending-queue">0</div>
                <div class="finance-kpi-note" id="finance-pending-note">Awaiting review</div>
            </div>
            <div class="finance-kpi-card">
                <div class="finance-kpi-label">Approved Amount</div>
                <div class="finance-kpi-value" id="finance-approved-amount">Rs 0.00</div>
                <div class="finance-kpi-note" id="finance-approved-note">Approved in range</div>
            </div>
            <div class="finance-kpi-card">
                <div class="finance-kpi-label">Rejected Amount</div>
                <div class="finance-kpi-value" id="finance-rejected-amount">Rs 0.00</div>
                <div class="finance-kpi-note" id="finance-rejected-note">Rejected in range</div>
            </div>
            <div class="finance-kpi-card">
                <div class="finance-kpi-label">Average Ticket</div>
                <div class="finance-kpi-value" id="finance-average-ticket">Rs 0.00</div>
                <div class="finance-kpi-note" id="finance-entry-note">0 entries in range</div>
            </div>
            <div class="finance-kpi-card">
                <div class="finance-kpi-label">This Month vs Last</div>
                <div class="finance-kpi-value" id="finance-month-comparison">0%</div>
                <div class="finance-kpi-note" id="finance-month-comparison-note">No previous month baseline</div>
            </div>
        </div>

        <div class="section-card finance-action-strip">
            <a href="{{ route('admin.expenses.queue') }}" class="finance-action-link">Open Approval Queue</a>
            <a href="{{ route('admin.expenses.monthly-report', ['year' => now()->year, 'month' => now()->month]) }}" class="finance-action-link primary" id="finance-monthly-report-link">Open Monthly Report</a>
            <a href="{{ route('admin.expenses.entries.index') }}" class="finance-action-link">Open Ledger</a>
        </div>

        <div class="finance-detail-grid">
            <div class="section-card finance-wide" style="margin-bottom:0;">
                <div class="section-title">
                    <span class="section-title-icon"><i class="fas fa-clipboard-check"></i></span>
                    Pending Approval Queue
                </div>
                <div class="finance-pending-grid">
                    <div class="finance-pending-hero">
                        <div class="finance-pending-value" id="finance-pending-count-large">0</div>
                        <div class="finance-pending-label">Pending Requests</div>
                        <div class="finance-pending-note" id="finance-pending-amount-note">Rs 0.00 pending approval</div>
                    </div>
                    <div class="finance-pending-list">
                        <div class="finance-pending-item">
                            <span>High Value Pending</span>
                            <strong id="finance-high-value-pending">0 / Rs 0.00</strong>
                        </div>
                        <div class="finance-pending-item">
                            <span>Oldest Pending</span>
                            <strong id="finance-oldest-pending">None</strong>
                        </div>
                        <div class="finance-pending-item">
                            <span>Review Action</span>
                            <strong><a href="{{ route('admin.expenses.queue') }}" class="finance-table-action">Open Queue</a></strong>
                        </div>
                    </div>
                </div>
            </div>

            <div class="section-card" style="margin-bottom:0;">
                <div class="section-title">
                    <span class="section-title-icon"><i class="fas fa-chart-pie"></i></span>
                    Status Summary
                </div>
                <div id="finance-status-summary" class="finance-status-list">
                    <div class="finance-empty-state">No status totals available.</div>
                </div>
            </div>

            <div class="section-card" style="margin-bottom:0;">
                <div class="section-title">
                    <span class="section-title-icon"><i class="fas fa-layer-group"></i></span>
                    Category Wise Spend
                </div>
                <div id="finance-category-spend" class="finance-category-list">
                    <div class="finance-empty-state">No category spend available.</div>
                </div>
            </div>

            <div class="section-card" style="margin-bottom:0;">
                <div class="section-title">
                    <span class="section-title-icon"><i class="fas fa-scale-balanced"></i></span>
                    Month Comparison
                </div>
                <div id="finance-comparison-card" class="finance-comparison-card">
                    <div class="finance-comparison-value" id="finance-comparison-value">Rs 0.00</div>
                    <div class="finance-comparison-note" id="finance-comparison-note">No previous month baseline available.</div>
                </div>
            </div>

            <div class="section-card finance-wide" style="margin-bottom:0;">
                <div class="section-title">
                    <span class="section-title-icon"><i class="fas fa-receipt"></i></span>
                    Recent Entries
                </div>
                <div id="finance-recent-entries" class="finance-table-wrap">
                    <div class="finance-empty-state" style="padding:16px;">No recent finance entries in this period.</div>
                </div>
            </div>
        </div>
    </div>

    <div id="hr-dashboard-panel" class="dashboard-panel">
        <div class="hr-summary-grid">
            <div class="hr-kpi-card">
                <div class="hr-kpi-value" id="hr-present-today">0</div>
                <div class="hr-kpi-label">Present Today</div>
            </div>
            <div class="hr-kpi-card">
                <div class="hr-kpi-value" id="hr-absent-today">0</div>
                <div class="hr-kpi-label">Absent Today</div>
            </div>
            <div class="hr-kpi-card">
                <div class="hr-kpi-value" id="hr-late-today">0</div>
                <div class="hr-kpi-label">Late Today</div>
            </div>
            <div class="hr-kpi-card">
                <div class="hr-kpi-value" id="hr-leads">0</div>
                <div class="hr-kpi-label">HR Leads</div>
            </div>
            <div class="hr-kpi-card">
                <div class="hr-kpi-value" id="hr-interviews-today">0</div>
                <div class="hr-kpi-label">Interviews Today</div>
            </div>
            <div class="hr-kpi-card">
                <div class="hr-kpi-value" id="hr-pending-leaves">0</div>
                <div class="hr-kpi-label">Pending Leaves</div>
            </div>
        </div>

        <div class="hr-dashboard-grid">
            <div class="section-card">
                <div class="section-title">
                    <span class="section-title-icon"><i class="fas fa-filter"></i></span>
                    HR Lead Funnel
                </div>
                <div id="hr-funnel" class="hr-funnel-list">
                    <div class="finance-empty-state">HR funnel will appear here.</div>
                </div>
            </div>

            <div class="section-card">
                <div class="section-title">
                    <span class="section-title-icon"><i class="fas fa-calendar-check"></i></span>
                    Attendance Summary
                </div>
                <div id="hr-attendance-summary" class="hr-mini-grid">
                    <div class="finance-empty-state">Attendance summary will appear here.</div>
                </div>
            </div>

            <div class="section-card">
                <div class="section-title">
                    <span class="section-title-icon"><i class="fas fa-id-badge"></i></span>
                    Employee Status
                </div>
                <div id="hr-employee-status" class="hr-mini-grid">
                    <div class="finance-empty-state">Employee status will appear here.</div>
                </div>
            </div>

            <div class="section-card">
                <div class="section-title">
                    <span class="section-title-icon"><i class="fas fa-list-check"></i></span>
                    Today HR Tasks
                </div>
                <div id="hr-tasks" class="hr-list">
                    <div class="finance-empty-state">No HR tasks available.</div>
                </div>
            </div>

            <div class="section-card">
                <div class="section-title">
                    <span class="section-title-icon"><i class="fas fa-umbrella-beach"></i></span>
                    Leave Requests
                </div>
                <div id="hr-leave-requests" class="hr-mini-grid">
                    <div class="finance-empty-state">Leave request summary will appear here.</div>
                </div>
            </div>

            <div class="section-card">
                <div class="section-title">
                    <span class="section-title-icon"><i class="fas fa-triangle-exclamation"></i></span>
                    Important Alerts
                </div>
                <div id="hr-alerts" class="hr-list">
                    <div class="finance-empty-state">No important alerts available.</div>
                </div>
            </div>
        </div>
    </div>

    <div id="targets-breakdown-modal" class="metric-modal" onclick="closeTargetsModal(event)">
        <div class="metric-modal-card" onclick="event.stopPropagation()">
            <div class="metric-modal-header">
                <div>
                    <div class="premium-filter-label">Team Targets</div>
                    <div style="font-size:24px;font-family:'Fraunces',serif;font-weight:700;color:#16161A;">Per User Breakdown</div>
                </div>
                <button type="button" class="metric-modal-close" onclick="closeTargetsModal()"><i class="fas fa-times"></i></button>
            </div>
            <div id="targets-breakdown-table" style="overflow-x:auto;">
                <p style="color:#9ca3af;font-size:13px;">Loading...</p>
            </div>
        </div>
    </div>

    {{-- hidden IDs kept for JS compat --}}
    <div style="display:none;">
        <span id="pending-verifications"></span>
        <span id="active-automations"></span>
        <span id="pending-imports"></span>
        <span id="failed-imports"></span>
    </div>
    <div id="health-stats" style="display:none;"></div>
    <div id="target-overview-section" style="display:none;"><div id="target-overview-content"></div></div>
</div>
</div>

<style>
@media(max-width:820px){
    html,
    body,
    #mainContent,
    #mainContent .container,
    #dashboard-content,
    .admin-dashboard-root,
    .admin-dashboard-shell {
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        overflow-x: hidden !important;
    }
    .admin-dashboard-root *,
    .admin-dashboard-shell * {
        min-width: 0;
        box-sizing: border-box;
    }
    .admin-hero-grid,
    .premium-grid-2,
    .premium-grid-3,
    .premium-grid-4,
    .ad-spend-grid,
    .ad-spend-layout,
    .finance-summary-grid,
    .finance-detail-grid,
    .hr-summary-grid,
    .hr-dashboard-grid,
    .hr-mini-grid,
    .marketing-score-grid,
    .finance-pending-grid,
    .shortcut-grid,
    .score-card-grid { grid-template-columns: 1fr !important; }
    .admin-hero {
        padding: 14px 12px;
        border-radius: 16px;
    }
    .admin-hero-title {
        font-family:'Outfit','Inter',sans-serif;
        font-size: 20px;
        line-height:1.1;
        max-width: 100%;
        overflow-wrap: normal;
    }
    .admin-hero-copy,
    .admin-hero-meta,
    .premium-filter-bar,
    .admin-table-shell,
    .section-card,
    .marketing-stat,
    .admin-side-panel,
    .shortcut-card {
        width: 100%;
        max-width: 100%;
    }
    .admin-hero-meta { grid-template-columns: 1fr; }
    .premium-filter-bar { flex-direction: column; align-items: flex-start; }
    .admin-hero-head { display:grid; grid-template-columns:minmax(0, 1fr) auto; align-items:center; }
    .admin-hero-actions { width: auto; justify-content: flex-end; margin-left: 0; }
    #sale-dashboard-panel.dashboard-panel.is-active {
        display:grid !important;
        grid-template-columns:1fr !important;
        grid-template-areas:
            "stats"
            "approval-center"
            "demand-insights"
            "performance"
            "targets"
            "pipeline"
            "sales-score-table"
            "sales-user-activity"
            "user-pipeline"
            "sales-performance"
            "user-visits-meetings"
            "call-statistics" !important;
    }
    #marketing-dashboard-panel.dashboard-panel.is-active,
    #finance-dashboard-panel.dashboard-panel.is-active,
    #hr-dashboard-panel.dashboard-panel.is-active {
        display:block !important;
        width:100% !important;
        max-width:100% !important;
        min-width:0 !important;
    }
    #sale-dashboard-panel > .dashboard-span-full,
    #sale-dashboard-panel > .dashboard-column-span-1,
    #sale-dashboard-panel > .dashboard-two-up-item { grid-column:1 / -1 !important; }
    #sale-dashboard-panel > [data-section="approval-center"],
    #sale-dashboard-panel > [data-section="demand-insights"] {
        display:block !important;
        grid-column:1 / -1 !important;
        grid-row:auto !important;
        width:100% !important;
        max-width:100% !important;
        min-width:0 !important;
    }
    #sale-dashboard-panel > [data-section="approval-center"] { grid-area:approval-center !important; }
    #sale-dashboard-panel > [data-section="demand-insights"] { grid-area:demand-insights !important; }
    #sale-dashboard-panel > [data-section="approval-center"] .approval-center-card,
    #sale-dashboard-panel > [data-section="demand-insights"] .demand-insights-grid,
    #sale-dashboard-panel > [data-section="demand-insights"] .demand-insight-card {
        width:100% !important;
        max-width:100% !important;
        min-width:0 !important;
    }
    #sale-dashboard-panel > [data-section="demand-insights"] .demand-insights-grid {
        display:grid !important;
        grid-template-columns:1fr !important;
    }
    #sale-dashboard-panel > [data-section="approval-center"] .approval-center-filters {
        flex-wrap:nowrap;
        overflow-x:auto;
        -webkit-overflow-scrolling:touch;
        max-width:none;
        padding-bottom:2px;
    }
    #sale-dashboard-panel > [data-section="approval-center"] .approval-filter-btn {
        flex:0 0 auto;
    }
    .performance-target-incentive-grid { grid-template-columns: 1fr; }
    .weekly-activity-wrap { max-width:100%; }
    .weekly-activity-custom-range {
        align-items:flex-start;
        justify-content:flex-start;
    }
    .weekly-activity-date-input { width:100%; }
    .dashboard-skeleton-status { flex-direction: column; align-items: flex-start; }
    .dashboard-skeleton-row.stats,
    .dashboard-skeleton-row.main,
    .dashboard-skeleton-row.bottom { grid-template-columns: 1fr; }
    .dashboard-skeleton-table-row { grid-template-columns: minmax(110px, 1.2fr) repeat(2, minmax(48px, .5fr)); }
    .dashboard-skeleton-table-row .dashboard-skeleton-line:nth-child(n+4) { display:none; }
    .grid-responsive-2{grid-template-columns:1fr !important;}
    .grid-responsive-leads{grid-template-columns:1fr !important;}
    #main-stats{grid-template-columns:repeat(2,1fr) !important;}
    #sale-dashboard-panel > [data-section="pipeline-funnel"] {
        overflow:hidden;
        padding:14px !important;
    }
    #sale-dashboard-panel > [data-section="pipeline-funnel"] .section-card-header {
        align-items:flex-start;
        gap:10px;
    }
    .pipeline-filter-toggle,
    .pipeline-filter-trigger { display:inline-flex !important; }
    #sale-dashboard-panel > [data-section="pipeline-funnel"] .section-title {
        min-width:0;
        font-size:14px;
        line-height:1.2;
    }
    #sale-dashboard-panel > [data-section="pipeline-funnel"] .section-title-icon {
        width:30px;
        height:30px;
        border-radius:10px;
    }
    #sale-dashboard-panel > [data-section="pipeline-funnel"] .dashboard-filter-panel {
        width:100%;
        overflow-x:auto;
        -webkit-overflow-scrolling:touch;
        padding-bottom:2px;
    }
    #sale-dashboard-panel > [data-section="pipeline-funnel"] .period-filter,
    #sale-dashboard-panel > [data-section="pipeline-funnel"] .filter-btn {
        flex:0 0 auto;
        white-space:nowrap;
    }
    .funnel-stack:not(#pipeline-funnel) { grid-template-columns: 1fr !important; gap: 16px; }
    #pipeline-funnel.funnel-stack {
        display:flex !important;
        flex-direction:column !important;
        gap:12px;
    }
    #pipeline-funnel .funnel-kpi-strip {
        display:grid !important;
        grid-template-columns:repeat(3, minmax(0, 1fr));
        gap:8px;
    }
    #pipeline-funnel .funnel-main-grid {
        display:grid !important;
        grid-template-columns:1fr !important;
        gap:12px;
    }
    #pipeline-funnel .funnel-main-grid > div[style] {
        display:flex !important;
        flex-direction:column !important;
        gap:12px !important;
    }
    #pipeline-funnel .funnel-kpi-card {
        display:block !important;
        grid-column:auto !important;
        padding:11px 10px;
        border-radius:14px;
        min-width:0;
        text-align:left;
    }
    #pipeline-funnel .funnel-kpi-card .label {
        font-size:9px;
        letter-spacing:.06em;
        line-height:1.15;
        margin:0;
        width:auto;
    }
    #pipeline-funnel .funnel-kpi-card .value {
        font-size:20px;
        margin-top:7px;
        width:auto !important;
    }
    #pipeline-funnel .funnel-kpi-card .note {
        display:block;
        font-size:10px;
        line-height:1.25;
    }
    #pipeline-funnel .funnel-visual {
        display:none !important;
        min-height:auto;
        padding:14px 10px;
        order:2;
    }
    .funnel-visual-shell {
        max-width:220px;
        gap:6px;
    }
    #pipeline-funnel .funnel-stage-panel,
    #pipeline-funnel .funnel-loss-card {
        display:block !important;
        padding:10px;
        border-radius:16px;
    }
    #pipeline-funnel .funnel-panel-heading {
        margin:0 0 8px;
        align-items:flex-start;
    }
    #pipeline-funnel .funnel-panel-title,
    #pipeline-funnel .funnel-panel-hint {
        font-size:10px;
        line-height:1.25;
    }
    #pipeline-funnel .funnel-stage-list {
        display:flex !important;
        flex-direction:column !important;
        gap:8px;
    }
    #pipeline-funnel .funnel-stage-link {
        display:grid !important;
        flex-direction:unset !important;
        grid-template-columns:minmax(132px, 1fr) 54px minmax(96px, .8fr) 58px;
        gap:8px;
        padding:8px 9px;
        border-radius:0;
        border:0;
        border-bottom:1px solid #e8eee9;
        text-align:left;
    }
    #pipeline-funnel .funnel-stage-table-head {
        grid-template-columns:minmax(132px, 1fr) 54px minmax(96px, .8fr) 58px;
        gap:8px;
        padding:8px 9px;
    }
    #pipeline-funnel .funnel-line-icon {
        display:flex !important;
        width:26px;
        height:26px;
        border-radius:9px;
        font-size:11px;
    }
    #pipeline-funnel .funnel-line-label {
        font-size:13px;
        margin:0;
        width:auto;
        text-transform:none;
        letter-spacing:0;
    }
    #pipeline-funnel .funnel-stage-sub,
    #pipeline-funnel .funnel-line-percent {
        display:block !important;
        font-size:10px;
        letter-spacing:.04em;
    }
    #pipeline-funnel .funnel-line-track {
        display:block !important;
        grid-column:auto;
        margin-left:0;
        width:100%;
        height:7px;
    }
    #pipeline-funnel .funnel-line-meta {
        display:block !important;
        min-width:48px;
        text-align:right;
        grid-column:auto;
        margin-left:0;
    }
    #pipeline-funnel .funnel-line-value {
        font-size:13px;
        width:auto !important;
    }
    .funnel-loss-header {
        margin-bottom:8px;
    }
    #pipeline-funnel .funnel-loss-grid {
        display:grid !important;
        grid-template-columns:1fr;
        gap:0;
    }
    #pipeline-funnel .funnel-loss-item {
        display:grid !important;
        grid-template-columns:minmax(0, 1fr) 58px;
        grid-column:auto !important;
        text-align:left;
        padding:8px 9px;
        border-radius:0;
    }
    #pipeline-funnel .funnel-loss-table-head {
        grid-template-columns:minmax(0, 1fr) 58px;
    }
    #pipeline-funnel .funnel-loss-value {
        font-size:13px;
    }
    .metric-modal-card { padding: 18px 16px; }
}
@media(max-width:480px){
    #main-stats{grid-template-columns:1fr !important;}
    .admin-hero {
        padding: 12px 10px;
    }
    .dashboard-skeleton-stat,
    .dashboard-skeleton-panel,
    .dashboard-skeleton-table { padding: 14px; }
    .dashboard-skeleton-line.title { width: 100%; }
    .dashboard-skeleton-line.subtitle { width: 82%; }
    .dashboard-skeleton-chart { gap: 8px; padding: 14px; }
    .dashboard-mode-btn { min-width: 0; padding: 7px 4px; font-size:10.5px; }
    .finance-action-strip { flex-direction:column; align-items:stretch; }
    .finance-action-link { width:100%; }
    .funnel-cap { height: 16px; }
    .funnel-segment { height: 40px; font-size: 12px; }
    #pipeline-funnel .funnel-kpi-strip {
        grid-template-columns:1fr;
    }
    #pipeline-funnel .funnel-kpi-card {
        display:grid;
        grid-template-columns:minmax(0, 1fr) auto;
        align-items:center;
        column-gap:10px;
    }
    #pipeline-funnel .funnel-kpi-card .value {
        grid-row:1 / span 2;
        grid-column:2;
        margin-top:0;
        font-size:22px;
    }
    #pipeline-funnel .funnel-kpi-card .note {
        grid-column:1;
    }
    #pipeline-funnel .funnel-stage-link {
        grid-template-columns:minmax(132px, 1fr) 54px minmax(96px, .8fr) 58px;
        gap:8px;
        padding:8px 9px;
    }
    #pipeline-funnel .funnel-line-icon {
        width:26px;
        height:26px;
    }
    #pipeline-funnel .funnel-line-track {
        grid-column:auto;
    }
}
</style>
<div id="approval-reject-modal" class="approval-modal-backdrop" aria-hidden="true">
    <div class="approval-modal-card" role="dialog" aria-modal="true" aria-labelledby="approval-reject-title">
        <div class="approval-modal-head">
            <div class="approval-modal-icon"><i class="fas fa-ban"></i></div>
            <div>
                <div id="approval-reject-title" class="approval-modal-title">Reject approval</div>
                <div id="approval-reject-copy" class="approval-modal-copy">Please add a clear reason before rejecting this request.</div>
            </div>
        </div>
        <div class="approval-modal-body">
            <label for="approval-reject-remarks" class="approval-modal-label">Reject reason</label>
            <textarea id="approval-reject-remarks" class="approval-modal-textarea" placeholder="Example: Leave balance insufficient / documents missing / request not valid"></textarea>
            <div id="approval-reject-error" class="approval-modal-error">Reject reason required hai.</div>
        </div>
        <div class="approval-modal-actions">
            <button type="button" class="approval-modal-btn" onclick="closeApprovalRejectModal()">Cancel</button>
            <button type="button" id="approval-reject-submit" class="approval-modal-btn primary" onclick="confirmApprovalReject()">Reject request</button>
        </div>
    </div>
</div>
<div id="approval-view-modal" class="approval-modal-backdrop" aria-hidden="true">
    <div class="approval-modal-card approval-view-card" role="dialog" aria-modal="true" aria-labelledby="approval-view-title">
        <div class="approval-modal-head">
            <div id="approval-view-icon" class="approval-modal-icon"><i class="fas fa-clipboard-check"></i></div>
            <div>
                <div id="approval-view-title" class="approval-modal-title">Approval detail</div>
                <div id="approval-view-copy" class="approval-modal-copy">Request details</div>
            </div>
        </div>
        <div class="approval-modal-body">
            <div id="approval-view-detail-grid" class="approval-detail-grid expanded">
                <div class="approval-detail-box full">
                    <div class="approval-detail-label">Summary</div>
                    <div id="approval-view-summary" class="approval-detail-value">--</div>
                </div>
                <div class="approval-detail-box full">
                    <div class="approval-detail-label">Meta</div>
                    <div id="approval-view-meta" class="approval-detail-value">--</div>
                </div>
            </div>
        </div>
        <div class="approval-modal-actions">
            <button type="button" class="approval-modal-btn" onclick="closeApprovalViewModal()">Close</button>
            <button type="button" id="approval-view-approve" class="approval-modal-btn approve" onclick="approveFromApprovalView()">Approve</button>
            <button type="button" id="approval-view-reject" class="approval-modal-btn primary" onclick="rejectFromApprovalView()">Reject</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@if(config('broadcasting.default') === 'pusher')
<script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
@endif
<script>
    const API_BASE_URL = '/admin/dashboard/data';
    const LEAD_QUALITY_URL = @json(route('admin.dashboard.lead-quality'));
    const LEADS_INDEX_URL = @json(route('leads.index'));
    const META_SPEND_SYNC_URL = @json(route('admin.dashboard.meta-spend-sync'));
    let leadStatusChart = null;
    let agentsVisitsMeetingsChart = null;
    let propertySegmentsChart = null;
    let adminLeadSourceChart = null;
    let adminDemandPropertyChart = null;
    let adminDemandBudgetChart = null;
    let latestDemandInsights = {};
    let demandChartTypes = JSON.parse(localStorage.getItem('adminDemandChartTypes') || '{"property":"doughnut","budget":"doughnut"}');
    let marketingSourceChart = null;
    let marketingInflowChart = null;
    let currentFilter = localStorage.getItem('dashboardFilter') || 'month';
    let customStartDate = localStorage.getItem('dashboardStartDate') || '';
    let customEndDate = localStorage.getItem('dashboardEndDate') || '';
    let currentRecentLeadsSource = localStorage.getItem('adminDashboardRecentLeadsSource') || '';
    let currentDashboardMode = ['sale', 'marketing', 'finance', 'hr'].includes(localStorage.getItem('adminDashboardMode'))
        ? localStorage.getItem('adminDashboardMode')
        : 'sale';
    const dashboardModeCache = new Map();
    let leadQualityLoaded = false;
    let dashboardMergedData = {};
    const DASHBOARD_BROWSER_CACHE_TTL = 60000;
    let sectionFilters = JSON.parse(localStorage.getItem('adminDashboardSectionFilters') || '{"pipeline_filter":"global","demand_filter":"global","source_filter":"month","sales_score_filter":"global"}');
    if (!sectionFilters.source_filter || sectionFilters.source_filter === 'global') {
        sectionFilters.source_filter = 'month';
        localStorage.setItem('adminDashboardSectionFilters', JSON.stringify(sectionFilters));
    }
    let pipelineCustomRange = JSON.parse(localStorage.getItem('adminDashboardPipelineCustomRange') || '{"start":"","end":""}');
    let demandCustomRange = JSON.parse(localStorage.getItem('adminDashboardDemandCustomRange') || '{"start":"","end":""}');
    let weeklyActivityCustomRange = JSON.parse(localStorage.getItem('adminDashboardWeeklyActivityCustomRange') || '{"start":"","end":""}');
    let latestWeeklyActivitySummary = {};
    let averageResponseTimeByUser = {};
    let latestApprovalCenter = { items: [] };
    let currentApprovalFilter = 'all';
    let pendingApprovalRejectItem = null;
    let pendingApprovalViewItem = null;
    const SALES_SCORE_OPTIONAL_COLUMNS = ['role', 'leads', 'no_response', 'oldest_assigned', 'meetings', 'visits', 'closers', 'junk', 'not_interested', 'other', 'ps', 'pp', 'vp', 'avg_response', 'action'];
    const SALES_SCORE_DEFAULT_COLUMNS = [...SALES_SCORE_OPTIONAL_COLUMNS];
    const initialSalesScoreColumns = @json($salesScoreColumns ?? []);
    let salesScoreColumns = Array.isArray(initialSalesScoreColumns) && initialSalesScoreColumns.length > 0
        ? initialSalesScoreColumns.filter((column) => SALES_SCORE_OPTIONAL_COLUMNS.includes(column))
        : [...SALES_SCORE_DEFAULT_COLUMNS];
    let draftSalesScoreColumns = [...salesScoreColumns];
    let latestSalesScoreRows = [];
    let sectionVisibility = JSON.parse(localStorage.getItem('adminDashboardSectionVisibility') || '{}');
    let sectionOrder = JSON.parse(localStorage.getItem('adminDashboardSectionOrder') || '[]');
    let draftSectionVisibility = {};
    let draftSectionOrder = [];
    let draggedSectionKey = null;
    const sectionFilterRenderers = {
        pipeline_filter: {
            sectionKey: 'pipeline-funnel',
            apply(data) {
                renderPipelineFunnel(data.pipeline_funnel || []);
            },
        },
        demand_filter: {
            sectionKey: 'demand-insights',
            apply(data) {
                renderDemandInsights(data.demand_insights || {});
            },
        },
        sales_score_filter: {
            sectionKey: 'sales-score-table',
            apply(data) {
                renderSalesScoreTable(data.sales_score_table || []);
            },
        },
        source_filter: {
            sectionKey: 'demand-insights',
            apply(data) {
                renderAdminLeadSources(data.marketing_summary || {}, data.source_performance_analytics || {});
                renderAdminMetaDecisionAnalytics(data.source_performance_analytics || {});
            },
        },
    };
    const customizableSections = [
        { key: 'approval-center', label: 'Approval Center', note: 'Unified finance, HR, leave and PO approvals' },
        { key: 'performance-funnel', label: 'Performance Scores', note: 'Primary PS / PP / VP scorecards' },
        { key: 'targets-incentives', label: 'Incentives', note: 'Incentive summary section' },
        { key: 'pipeline-funnel', label: 'Pipeline Funnel', note: 'Lead flow snapshot with stage conversion' },
        { key: 'demand-insights', label: 'Demand Insights', note: 'Property type and budget demand mix' },
        { key: 'sales-score-table', label: 'Sales Team Scores', note: 'Detailed PS / PP / VP leaderboard' },
        { key: 'sales-user-activity', label: 'Sales User Activity', note: 'Fresh leads, overdue tasks and visits by user' },
        { key: 'user-pipeline', label: 'User Pipeline', note: 'Meetings, visits and closers by user' },
        { key: 'sales-performance', label: 'Sales Performance', note: 'Executive performance cards' },
    ];

    // Initialize filter UI on page load
    document.addEventListener('DOMContentLoaded', function() {
        if (sectionFilters.weekly_activity_filter) {
            delete sectionFilters.weekly_activity_filter;
            localStorage.setItem('adminDashboardSectionFilters', JSON.stringify(sectionFilters));
        }
        pipelineCustomRange = {
            start: pipelineCustomRange?.start || '',
            end: pipelineCustomRange?.end || '',
        };
        demandCustomRange = {
            start: demandCustomRange?.start || '',
            end: demandCustomRange?.end || '',
        };
        weeklyActivityCustomRange = {
            start: weeklyActivityCustomRange?.start || '',
            end: weeklyActivityCustomRange?.end || '',
        };

        const recentLeadsSourceFilter = document.getElementById('recent-leads-source-filter');
        if (recentLeadsSourceFilter) {
            recentLeadsSourceFilter.value = currentRecentLeadsSource;
        }

        const approvalRejectModal = document.getElementById('approval-reject-modal');
        if (approvalRejectModal) {
            approvalRejectModal.addEventListener('click', (event) => {
                if (event.target === approvalRejectModal) {
                    closeApprovalRejectModal();
                }
            });
        }
        const approvalViewModal = document.getElementById('approval-view-modal');
        if (approvalViewModal) {
            approvalViewModal.addEventListener('click', (event) => {
                if (event.target === approvalViewModal) {
                    closeApprovalViewModal();
                }
            });
        }
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeApprovalRejectModal();
                closeApprovalViewModal();
            }
        });

        // Set active filter button
        document.querySelectorAll('.date-filter-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        const activeBtn = document.getElementById('filter-' + currentFilter);
        if (activeBtn) {
            activeBtn.classList.add('active');
        }

        // Set custom dates if available
        if (customStartDate && customEndDate) {
            document.getElementById('custom-start-date').value = customStartDate;
            document.getElementById('custom-end-date').value = customEndDate;
        }

        initializeSectionOrder();
        hydrateSectionControlState();
        syncDemandCustomRangeUI();
        applySectionOrder();
        applySectionVisibility();
        switchDashboardMode(currentDashboardMode, false);
        updateHeroDateRangeLabel();
    });

    function initializeSectionOrder() {
        const defaultOrder = getDefaultSectionOrder();
        if (!Array.isArray(sectionOrder) || sectionOrder.length === 0) {
            sectionOrder = defaultOrder;
            localStorage.setItem('adminDashboardSectionOrder', JSON.stringify(sectionOrder));
            return;
        }

        const existingKeys = new Set(sectionOrder);
        defaultOrder.forEach((key) => {
            if (!existingKeys.has(key)) {
                sectionOrder.push(key);
            }
        });
        sectionOrder = sectionOrder.filter((key) => defaultOrder.includes(key));
        localStorage.setItem('adminDashboardSectionOrder', JSON.stringify(sectionOrder));
    }

    function getDefaultSectionOrder() {
        return Array.from(document.querySelectorAll('#sale-dashboard-panel > .dashboard-orderable[data-section]'))
            .map((section) => section.getAttribute('data-section'))
            .filter(Boolean);
    }

    function getSectionFilterDefault(filterKey) {
        return 'global';
    }

    function persistWeeklyActivityCustomRange() {
        localStorage.setItem('adminDashboardWeeklyActivityCustomRange', JSON.stringify(weeklyActivityCustomRange));
    }

    function persistPipelineCustomRange() {
        localStorage.setItem('adminDashboardPipelineCustomRange', JSON.stringify(pipelineCustomRange));
    }

    function persistDemandCustomRange() {
        localStorage.setItem('adminDashboardDemandCustomRange', JSON.stringify(demandCustomRange));
    }

    function setSectionLoadingState(sectionKey, isLoading) {
        if (!sectionKey) {
            return;
        }
        const section = document.querySelector(`.dashboard-orderable[data-section="${sectionKey}"]`);
        if (!section) {
            return;
        }
        section.classList.toggle('is-section-loading', !!isLoading);
    }

    async function loadSectionData(filterKey, options = {}) {
        const rendererConfig = sectionFilterRenderers[filterKey];
        if (!rendererConfig) {
            return loadDashboardData(options);
        }

        const preserveScroll = options.preserveScroll !== false;
        const sectionKey = rendererConfig.sectionKey;
        const scrollX = preserveScroll ? window.scrollX : 0;
        const scrollY = preserveScroll ? window.scrollY : 0;

        try {
            setSectionLoadingState(sectionKey, true);
            const params = new URLSearchParams();
            params.append('filter', currentFilter);
            params.append('mode', 'sale');
            if (customStartDate && customEndDate) {
                params.append('start_date', customStartDate);
                params.append('end_date', customEndDate);
            }
            Object.entries(sectionFilters).forEach(([key, value]) => {
                if (value && value !== 'global') {
                    params.append(key, value);
                }
            });
            if ((sectionFilters.pipeline_filter || 'global') === 'custom') {
                if (pipelineCustomRange.start && pipelineCustomRange.end) {
                    params.append('pipeline_start_date', pipelineCustomRange.start);
                    params.append('pipeline_end_date', pipelineCustomRange.end);
                }
            }
            if ((sectionFilters.demand_filter || 'global') === 'custom') {
                if (demandCustomRange.start && demandCustomRange.end) {
                    params.append('demand_start_date', demandCustomRange.start);
                    params.append('demand_end_date', demandCustomRange.end);
                }
            }
            if (currentRecentLeadsSource) {
                params.append('recent_leads_source', currentRecentLeadsSource);
            }

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const response = await fetch(API_BASE_URL + '?' + params.toString(), {
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(errorText || 'Section refresh failed');
            }

            const data = await response.json();
            rendererConfig.apply(data || {});
            if (preserveScroll) {
                requestAnimationFrame(() => window.scrollTo(scrollX, scrollY));
            }
        } catch (error) {
            console.error('Error loading section:', filterKey, error);
        } finally {
            setSectionLoadingState(sectionKey, false);
        }
    }

    function hydrateSectionControlState() {
        document.querySelectorAll('[data-section-filter]').forEach((button) => {
            const filterKey = button.getAttribute('data-section-filter');
            const value = button.getAttribute('data-value');
            button.classList.toggle('active', (sectionFilters[filterKey] || getSectionFilterDefault(filterKey)) === value);
        });
        syncPipelineCustomRangeUI();
        syncDemandCustomRangeUI();
    }

    function applySectionFilter(filterKey, value, button) {
        sectionFilters[filterKey] = value;
        localStorage.setItem('adminDashboardSectionFilters', JSON.stringify(sectionFilters));

        document.querySelectorAll(`[data-section-filter="${filterKey}"]`).forEach((btn) => {
            btn.classList.toggle('active', btn.getAttribute('data-value') === value);
        });

        if (button) {
            button.blur();
        }
        if (filterKey === 'pipeline_filter') {
            document.getElementById('pipeline-filter-panel')?.classList.remove('is-open');
        }

        if (filterKey === 'pipeline_filter') {
            syncPipelineCustomRangeUI();
            if (value === 'custom') {
                renderWeeklyActivitySummary(latestWeeklyActivitySummary || {});
                return;
            }
            loadDashboardData({ preserveScroll: true, silent: true });
            return;
        }

        loadSectionData(filterKey);
    }

    function syncPipelineCustomRangeUI() {
        const isCustom = (sectionFilters.pipeline_filter || 'global') === 'custom';
        const panel = document.getElementById('pipeline-custom-range');
        const startInput = document.getElementById('pipeline-custom-start');
        const endInput = document.getElementById('pipeline-custom-end');
        const summary = document.getElementById('pipeline-filter-summary');
        const labels = {
            global: 'Global',
            today: 'Today',
            week: 'Week',
            month: 'Month',
            year: 'Year',
            custom: 'Custom',
        };
        if (panel) {
            panel.classList.toggle('is-open', isCustom);
        }
        if (startInput) {
            startInput.value = pipelineCustomRange.start || '';
        }
        if (endInput) {
            endInput.value = pipelineCustomRange.end || '';
        }
        if (summary) {
            summary.textContent = isCustom && pipelineCustomRange.start && pipelineCustomRange.end
                ? `${pipelineCustomRange.start} - ${pipelineCustomRange.end}`
                : (labels[sectionFilters.pipeline_filter || 'global'] || 'Global');
        }
    }

    function handlePipelineDateInput(input, boundary) {
        if (!input || !boundary) {
            return;
        }
        pipelineCustomRange[boundary] = input.value || '';
        persistPipelineCustomRange();
        sectionFilters.pipeline_filter = 'custom';
        localStorage.setItem('adminDashboardSectionFilters', JSON.stringify(sectionFilters));
        syncPipelineCustomRangeUI();
    }

    function applyPipelineCustomRange() {
        if (!pipelineCustomRange.start || !pipelineCustomRange.end) {
            alert('Custom date ke liye From aur To dono select karo.');
            return;
        }
        if (pipelineCustomRange.start > pipelineCustomRange.end) {
            alert('From date To date se badi nahi ho sakti.');
            return;
        }
        sectionFilters.pipeline_filter = 'custom';
        localStorage.setItem('adminDashboardSectionFilters', JSON.stringify(sectionFilters));
        persistPipelineCustomRange();
        syncPipelineCustomRangeUI();
        loadDashboardData({ preserveScroll: true, silent: true });
    }

    function syncDemandCustomRangeUI() {
        const isCustom = (sectionFilters.demand_filter || 'global') === 'custom';
        document.querySelectorAll('.demand-custom-range').forEach((panel) => panel.classList.toggle('is-open', isCustom));
        document.querySelectorAll('[data-demand-date="start"]').forEach((input) => { input.value = demandCustomRange.start || ''; });
        document.querySelectorAll('[data-demand-date="end"]').forEach((input) => { input.value = demandCustomRange.end || ''; });
    }

    function closeDemandFilterPanels() {
        closeMobilePanel('demand-property-filter-panel');
        closeMobilePanel('demand-budget-filter-panel');
    }

    function applyDemandFilter(value, button) {
        sectionFilters.demand_filter = value;
        localStorage.setItem('adminDashboardSectionFilters', JSON.stringify(sectionFilters));
        document.querySelectorAll('[data-section-filter="demand_filter"]').forEach((item) => {
            item.classList.toggle('active', item.getAttribute('data-value') === value);
        });
        button?.blur();
        syncDemandCustomRangeUI();
        if (value === 'custom') {
            return;
        }
        closeDemandFilterPanels();
        loadSectionData('demand_filter');
    }

    function handleDemandDateInput(input, boundary) {
        demandCustomRange[boundary] = input?.value || '';
        sectionFilters.demand_filter = 'custom';
        localStorage.setItem('adminDashboardSectionFilters', JSON.stringify(sectionFilters));
        persistDemandCustomRange();
        hydrateSectionControlState();
        syncDemandCustomRangeUI();
    }

    function applyDemandCustomRange() {
        if (!demandCustomRange.start || !demandCustomRange.end) {
            alert('Custom date ke liye From aur To dono select karo.');
            return;
        }
        if (demandCustomRange.start > demandCustomRange.end) {
            alert('From date To date se badi nahi ho sakti.');
            return;
        }
        sectionFilters.demand_filter = 'custom';
        localStorage.setItem('adminDashboardSectionFilters', JSON.stringify(sectionFilters));
        persistDemandCustomRange();
        closeDemandFilterPanels();
        loadSectionData('demand_filter');
    }

    function handleWeeklyActivityDateInput(input, boundary) {
        if (!input || !boundary) {
            return;
        }

        weeklyActivityCustomRange[boundary] = input.value || '';
        persistWeeklyActivityCustomRange();
    }

    function applyWeeklyActivityCustomFilter() {
        const start = weeklyActivityCustomRange.start || '';
        const end = weeklyActivityCustomRange.end || '';

        if (!start || !end) {
            alert('Please select both start and end dates.');
            return;
        }

        if (start > end) {
            alert('Start date cannot be after end date.');
            return;
        }

        pipelineCustomRange = { start, end };
        sectionFilters.pipeline_filter = 'custom';
        localStorage.setItem('adminDashboardSectionFilters', JSON.stringify(sectionFilters));
        persistPipelineCustomRange();
        syncPipelineCustomRangeUI();
        loadDashboardData({ preserveScroll: true, silent: true });
    }

    function renderDashboardCustomizeOptions() {
        const container = document.getElementById('dashboard-customize-body');
        if (!container) {
            return;
        }

        const orderedSections = draftSectionOrder.length
            ? draftSectionOrder.map((key) => customizableSections.find((section) => section.key === key)).filter(Boolean)
            : customizableSections;

        container.innerHTML = orderedSections.map((section) => {
            const isVisible = draftSectionVisibility[section.key] !== false;
            return `
                <div class="dashboard-customize-item" draggable="true" data-order-key="${section.key}" ondragstart="startDashboardDrag(event)" ondragover="dragOverDashboardItem(event)" ondrop="dropDashboardItem(event)" ondragend="endDashboardDrag()">
                    <div class="dashboard-customize-main">
                        <button type="button" class="dashboard-drag-handle" title="Drag to reorder">
                            <i class="fas fa-grip-vertical"></i>
                        </button>
                        <div>
                            <strong>${section.label}</strong>
                            <span>${section.note}</span>
                        </div>
                    </div>
                    <label class="dashboard-switch">
                        <input type="checkbox" ${isVisible ? 'checked' : ''} onchange="toggleDashboardSection('${section.key}', this.checked)">
                        <span class="dashboard-switch-slider"></span>
                    </label>
                </div>
            `;
        }).join('');
    }

    function toggleDashboardSection(sectionKey, isVisible) {
        draftSectionVisibility[sectionKey] = isVisible;
    }

    function applySectionOrder() {
        const panel = document.getElementById('sale-dashboard-panel');
        if (!panel) {
            return;
        }

        const orderLookup = new Map(sectionOrder.map((key, index) => [key, index]));
        const sections = Array.from(panel.querySelectorAll(':scope > .dashboard-orderable[data-section]'));
        sections
            .sort((a, b) => {
                const aOrder = orderLookup.has(a.dataset.section) ? orderLookup.get(a.dataset.section) : Number.MAX_SAFE_INTEGER;
                const bOrder = orderLookup.has(b.dataset.section) ? orderLookup.get(b.dataset.section) : Number.MAX_SAFE_INTEGER;
                return aOrder - bOrder;
            })
            .forEach((section) => panel.appendChild(section));
    }

    function applySectionVisibility() {
        document.querySelectorAll('.dashboard-orderable[data-section]').forEach((section) => {
            const key = section.getAttribute('data-section');
            section.style.display = sectionVisibility[key] === false ? 'none' : '';
        });
    }

    function startDashboardDrag(event) {
        draggedSectionKey = event.currentTarget.getAttribute('data-order-key');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', draggedSectionKey);
        event.currentTarget.classList.add('is-dragging');
    }

    function dragOverDashboardItem(event) {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
    }

    function dropDashboardItem(event) {
        event.preventDefault();
        const targetKey = event.currentTarget.getAttribute('data-order-key');
        const sourceKey = draggedSectionKey || event.dataTransfer.getData('text/plain');

        if (!sourceKey || !targetKey || sourceKey === targetKey) {
            return;
        }

        const nextOrder = [...draftSectionOrder];
        const sourceIndex = nextOrder.indexOf(sourceKey);
        const targetIndex = nextOrder.indexOf(targetKey);
        if (sourceIndex === -1 || targetIndex === -1) {
            return;
        }

        nextOrder.splice(sourceIndex, 1);
        nextOrder.splice(targetIndex, 0, sourceKey);
        draftSectionOrder = nextOrder;
        renderDashboardCustomizeOptions();
    }

    function endDashboardDrag() {
        draggedSectionKey = null;
        document.querySelectorAll('.dashboard-customize-item.is-dragging').forEach((item) => item.classList.remove('is-dragging'));
    }

    function openDashboardCustomize() {
        const modal = document.getElementById('dashboard-customize-modal');
        if (modal) {
            draftSectionVisibility = { ...sectionVisibility };
            draftSectionOrder = [...sectionOrder];
            renderDashboardCustomizeOptions();
            modal.classList.add('is-open');
        }
    }

    function saveDashboardCustomize() {
        sectionVisibility = { ...draftSectionVisibility };
        sectionOrder = [...draftSectionOrder];
        localStorage.setItem('adminDashboardSectionVisibility', JSON.stringify(sectionVisibility));
        localStorage.setItem('adminDashboardSectionOrder', JSON.stringify(sectionOrder));
        applySectionOrder();
        applySectionVisibility();
        closeDashboardCustomize();
    }

    function closeDashboardCustomize(event) {
        if (event && event.target && event.target.id !== 'dashboard-customize-modal') {
            return;
        }
        const modal = document.getElementById('dashboard-customize-modal');
        if (modal) {
            modal.classList.remove('is-open');
        }
    }

    function updateHeroDateRangeLabel() {
        const label = document.getElementById('hero-date-range-label');
        if (!label) {
            return;
        }

        if (currentFilter === 'custom' && customStartDate && customEndDate) {
            label.textContent = `${customStartDate} to ${customEndDate}`;
            return;
        }

        const labels = {
            today: 'Today',
            week: 'This week',
            month: 'This month',
            year: 'This year',
            custom: 'Custom range',
        };
        label.textContent = labels[currentFilter] || 'This month';
    }

    function switchDashboardMode(mode, shouldLoad = true) {
        currentDashboardMode = ['sale', 'marketing', 'finance', 'hr'].includes(mode) ? mode : 'sale';
        localStorage.setItem('adminDashboardMode', currentDashboardMode);

        document.querySelectorAll('.dashboard-mode-btn').forEach((button) => {
            button.classList.remove('is-active');
        });

        const saleBtn = document.getElementById('dashboard-mode-sale');
        const marketingBtn = document.getElementById('dashboard-mode-marketing');
        const financeBtn = document.getElementById('dashboard-mode-finance');
        const hrBtn = document.getElementById('dashboard-mode-hr');
        const salePanel = document.getElementById('sale-dashboard-panel');
        const marketingPanel = document.getElementById('marketing-dashboard-panel');
        const financePanel = document.getElementById('finance-dashboard-panel');
        const hrPanel = document.getElementById('hr-dashboard-panel');

        if (saleBtn) saleBtn.classList.toggle('is-active', currentDashboardMode === 'sale');
        if (marketingBtn) marketingBtn.classList.toggle('is-active', currentDashboardMode === 'marketing');
        if (financeBtn) financeBtn.classList.toggle('is-active', currentDashboardMode === 'finance');
        if (hrBtn) hrBtn.classList.toggle('is-active', currentDashboardMode === 'hr');
        if (salePanel) salePanel.classList.toggle('is-active', currentDashboardMode === 'sale');
        if (marketingPanel) marketingPanel.classList.toggle('is-active', currentDashboardMode === 'marketing');
        if (financePanel) financePanel.classList.toggle('is-active', currentDashboardMode === 'finance');
        if (hrPanel) hrPanel.classList.toggle('is-active', currentDashboardMode === 'hr');

        window.requestAnimationFrame(() => {
            if (leadStatusChart) leadStatusChart.resize();
            if (agentsVisitsMeetingsChart) agentsVisitsMeetingsChart.resize();
            if (propertySegmentsChart) propertySegmentsChart.resize();
            if (adminLeadSourceChart) adminLeadSourceChart.resize();
            if (adminDemandPropertyChart) adminDemandPropertyChart.resize();
            if (adminDemandBudgetChart) adminDemandBudgetChart.resize();
            if (marketingSourceChart) marketingSourceChart.resize();
            if (marketingInflowChart) marketingInflowChart.resize();
        });

        if (shouldLoad) {
            loadDashboardData({ preserveScroll: true });
        }

        if (currentDashboardMode === 'marketing') {
            loadLeadQualityOverview();
        }
    }

    async function loadLeadQualityOverview(forceRefresh = false) {
        if (leadQualityLoaded && !forceRefresh) {
            return;
        }

        const container = document.getElementById('admin-lead-quality-overview');
        if (!container) {
            return;
        }

        try {
            const url = new URL(LEAD_QUALITY_URL, window.location.origin);
            const currentParams = new URLSearchParams(window.location.search);
            ['source', 'period', 'month', 'from', 'to', 'user_id', 'bucket', 'meta_view', 'fb_form_id', 'campaign_id'].forEach((key) => {
                currentParams.getAll(key).forEach((value) => url.searchParams.append(key, value));
            });
            if (forceRefresh) {
                url.searchParams.set('refresh', '1');
            }

            const response = await fetch(url.toString(), {
                headers: { 'Accept': 'text/html' },
                credentials: 'same-origin',
            });
            if (!response.ok) {
                throw new Error('Lead quality report failed to load');
            }

            container.innerHTML = await response.text();
            leadQualityLoaded = true;
        } catch (error) {
            console.error('Lead quality report error:', error);
        }
    }

    function setDashboardLoadingState(isLoading) {
        const loadingEl = document.getElementById('loading');
        const salePanel = document.getElementById('sale-dashboard-panel');
        const marketingPanel = document.getElementById('marketing-dashboard-panel');
        const financePanel = document.getElementById('finance-dashboard-panel');
        const hrPanel = document.getElementById('hr-dashboard-panel');
        const activePanels = {
            sale: salePanel,
            marketing: marketingPanel,
            finance: financePanel,
            hr: hrPanel,
        };
        const activePanel = activePanels[currentDashboardMode] || salePanel;

        if (loadingEl) {
            loadingEl.classList.toggle('hidden', !isLoading);
        }

        if (activePanel) {
            activePanel.classList.toggle('hidden', isLoading);
        }
    }

    function applyDateFilter(filter) {
        currentFilter = filter;
        customStartDate = '';
        customEndDate = '';
        
        // Update active button
        document.querySelectorAll('.date-filter-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        document.getElementById('filter-' + filter).classList.add('active');

        // Clear custom date inputs
        document.getElementById('custom-start-date').value = '';
        document.getElementById('custom-end-date').value = '';

        // Save to localStorage
        localStorage.setItem('dashboardFilter', filter);
        localStorage.removeItem('dashboardStartDate');
        localStorage.removeItem('dashboardEndDate');
        updateHeroDateRangeLabel();

        // Reload dashboard data
        loadDashboardData();
    }

    function applyCustomDateFilter() {
        const startDate = document.getElementById('custom-start-date').value;
        const endDate = document.getElementById('custom-end-date').value;

        if (!startDate || !endDate) {
            return;
        }

        customStartDate = startDate;
        customEndDate = endDate;
        currentFilter = 'custom';

        // Update active button
        document.querySelectorAll('.date-filter-btn').forEach(btn => {
            btn.classList.remove('active');
        });

        // Save to localStorage
        localStorage.setItem('dashboardFilter', 'custom');
        localStorage.setItem('dashboardStartDate', startDate);
        localStorage.setItem('dashboardEndDate', endDate);
        updateHeroDateRangeLabel();

        // Reload dashboard data
        loadDashboardData();
    }

    function applyRecentLeadsSourceFilter(value) {
        currentRecentLeadsSource = value || '';
        localStorage.setItem('adminDashboardRecentLeadsSource', currentRecentLeadsSource);
        loadDashboardData();
    }

    async function loadDashboardData(options = {}) {
        const preserveScroll = options.preserveScroll === true;
        const silent = options.silent === true;
        const forceRefresh = options.forceRefresh === true;
        const scrollX = preserveScroll ? window.scrollX : 0;
        const scrollY = preserveScroll ? window.scrollY : 0;

        try {
            if (!silent) {
                // Show loading state
                setDashboardLoadingState(true);
                document.getElementById('main-stats').classList.add('hidden');
                document.getElementById('health-stats').classList.add('hidden');
            }

            // Build query parameters
            const params = new URLSearchParams();
            params.append('filter', currentFilter);
            params.append('mode', currentDashboardMode);
            if (customStartDate && customEndDate) {
                params.append('start_date', customStartDate);
                params.append('end_date', customEndDate);
            }
            Object.entries(sectionFilters).forEach(([key, value]) => {
                if (value && value !== 'global') {
                    params.append(key, value);
                }
            });
            if ((sectionFilters.pipeline_filter || 'global') === 'custom') {
                if (pipelineCustomRange.start && pipelineCustomRange.end) {
                    params.append('pipeline_start_date', pipelineCustomRange.start);
                    params.append('pipeline_end_date', pipelineCustomRange.end);
                }
            }
            if ((sectionFilters.demand_filter || 'global') === 'custom') {
                if (demandCustomRange.start && demandCustomRange.end) {
                    params.append('demand_start_date', demandCustomRange.start);
                    params.append('demand_end_date', demandCustomRange.end);
                }
            }
            if (currentRecentLeadsSource) {
                params.append('recent_leads_source', currentRecentLeadsSource);
            }
            if (forceRefresh) {
                params.append('refresh', '1');
            }

            const cacheKeyParams = new URLSearchParams(params);
            cacheKeyParams.delete('refresh');
            const browserCacheKey = cacheKeyParams.toString();
            const cachedEntry = dashboardModeCache.get(browserCacheKey);
            if (!forceRefresh && cachedEntry && (Date.now() - cachedEntry.loadedAt) < DASHBOARD_BROWSER_CACHE_TTL) {
                dashboardMergedData = { ...dashboardMergedData, ...cachedEntry.data };
                renderDashboard(dashboardMergedData);
                if (preserveScroll) {
                    requestAnimationFrame(() => window.scrollTo(scrollX, scrollY));
                }
                return;
            }

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const response = await fetch(API_BASE_URL + '?' + params.toString(), {
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                const errorText = await response.text();
                let errorData;
                try {
                    errorData = JSON.parse(errorText);
                } catch (e) {
                    errorData = { message: errorText || 'Failed to load dashboard data' };
                }
                throw new Error(errorData.message || errorData.error || 'Failed to load dashboard data');
            }

            const data = await response.json();
            
            // Debug: Log data to console
            console.log('Dashboard data received:', data);
            
            // Validate data structure
            if (!data || typeof data !== 'object') {
                throw new Error('Invalid data received from server');
            }
            
            dashboardModeCache.set(browserCacheKey, { data, loadedAt: Date.now() });
            dashboardMergedData = { ...dashboardMergedData, ...data };
            renderDashboard(dashboardMergedData);
            if (preserveScroll) {
                requestAnimationFrame(() => window.scrollTo(scrollX, scrollY));
            }
        } catch (error) {
            console.error('Error loading dashboard:', error);
            const loadingEl = document.getElementById('loading');
            if (loadingEl && !silent) {
                loadingEl.innerHTML = `<p class="text-red-600">Error: ${error.message}. Please refresh the page.</p>`;
                loadingEl.classList.remove('hidden');
            }
        }
    }

    function renderDashboard(data) {
        // Hide loading, show content
        const mainStatsEl = document.getElementById('main-stats');
        const healthStatsEl = document.getElementById('health-stats');
        
        setDashboardLoadingState(false);
        if (mainStatsEl) mainStatsEl.classList.remove('hidden');
        if (healthStatsEl) healthStatsEl.classList.remove('hidden');
        updateHeroDateRangeLabel();
        hydrateSectionControlState();
        applySectionVisibility();
        renderHeroMetrics(data);
        renderDashboardShortcuts(data.dashboard_shortcuts || []);
        renderApprovalCenter(data.approval_center || {});
        renderFinanceDashboard(data.finance_summary || {});
        renderHrDashboard(data);
        renderPerformanceScores(data.performance_scores || {});
        renderWeeklyActivitySummary(data.weekly_activity_summary || {});
        renderPipelineFunnel(data.pipeline_funnel || []);
        renderDemandInsights(data.demand_insights || {});
        renderTeamTargetsSummary(data.team_targets_summary || {});
        renderTargetsBreakdown(data.team_targets_breakdown || []);
        renderIncentiveSummary(data.incentive_summary || {});
        averageResponseTimeByUser = {};
        if (Array.isArray(data.average_response_time_by_user)) {
            data.average_response_time_by_user.forEach((row) => {
                const userId = Number(row.user_id || 0);
                if (userId > 0) {
                    averageResponseTimeByUser[userId] = row.avg_response_minutes;
                }
                if (row.user_name) {
                    averageResponseTimeByUser[String(row.user_name).trim().toLowerCase()] = row.avg_response_minutes;
                }
            });
        }
        renderSalesScoreTable(data.sales_score_table || []);
        renderSalesUserActivityTable(data.sales_user_activity_table || {});
        renderUserPipelineTable(data.user_pipeline_table || []);

        // System Stats - with null checks
        const systemStats = data.system_stats || {};
        
        const leadsEl = document.getElementById('total-leads');
        if (leadsEl) leadsEl.textContent = systemStats.total_leads || 0;
        
        const visitsEl = document.getElementById('total-visits');
        if (visitsEl) visitsEl.textContent = systemStats.total_visits || 0;
        
        const meetingsEl = document.getElementById('total-meetings');
        if (meetingsEl) meetingsEl.textContent = systemStats.total_meetings || 0;
        
        const closersEl = document.getElementById('total-closers');
        if (closersEl) closersEl.textContent = systemStats.total_closers || 0;
        
        const deadEl = document.getElementById('total-dead');
        if (deadEl) deadEl.textContent = systemStats.total_dead || 0;

        // System Health - with null checks
        const systemHealth = data.system_health || {};
        const pendingVerEl = document.getElementById('pending-verifications');
        if (pendingVerEl) pendingVerEl.textContent = systemHealth.pending_verifications || 0;
        
        const activeAutoEl = document.getElementById('active-automations');
        if (activeAutoEl) activeAutoEl.textContent = systemHealth.active_automations || 0;
        
        const pendingImpEl = document.getElementById('pending-imports');
        if (pendingImpEl) pendingImpEl.textContent = systemHealth.pending_imports || 0;
        
        const failedImpEl = document.getElementById('failed-imports');
        if (failedImpEl) failedImpEl.textContent = systemHealth.failed_imports || 0;

        // User Stats - with null checks
        const userStats = data.user_stats || {};
        const byRole = userStats.by_role || {};
        const adminEl = document.getElementById('users-admin');
        if (adminEl) adminEl.textContent = byRole.admin || 0;
        
        const crmEl = document.getElementById('users-crm');
        if (crmEl) crmEl.textContent = byRole.crm || 0;
        
        const smEl = document.getElementById('users-sales-manager');
        if (smEl) smEl.textContent = byRole.sales_manager || 0;
        
        const seEl = document.getElementById('users-sales-executive');
        if (seEl) seEl.textContent = byRole.sales_executive || 0;
        
        const telEl = document.getElementById('users-telecaller');
        if (telEl) telEl.textContent = byRole.telecaller || 0;
        
        const newMonthEl = document.getElementById('users-new-month');
        if (newMonthEl) newMonthEl.textContent = userStats.new_this_month || 0;
        
        const active24hEl = document.getElementById('users-active-24h');
        if (active24hEl) active24hEl.textContent = userStats.active_24h || 0;
        
        const totalEl = document.getElementById('users-total');
        if (totalEl) totalEl.textContent = userStats.total || 0;

        // Lead Stats - with null checks
        const leadStats = data.lead_stats || {};
        const todayEl = document.getElementById('leads-today');
        if (todayEl) todayEl.textContent = leadStats.new_today || 0;
        
        const weekEl = document.getElementById('leads-week');
        if (weekEl) weekEl.textContent = leadStats.new_this_week || 0;
        
        const monthEl = document.getElementById('leads-month');
        if (monthEl) monthEl.textContent = leadStats.new_this_month || 0;

        // Lead Status Chart
        if (leadStats.by_status) {
            renderLeadStatusChart(leadStats.by_status);
        }

        // Target Overview
        if (data.target_overview) {
            renderTargetOverview(data.target_overview);
        }

        // Recent Leads
        if (data.recent_leads && Array.isArray(data.recent_leads)) {
            renderRecentLeads(data.recent_leads);
        } else {
            renderRecentLeads([]);
        }

        // Recent Activities
        if (data.recent_activities && Array.isArray(data.recent_activities)) {
            renderRecentActivities(data.recent_activities);
        } else {
            renderRecentActivities([]);
        }

        // Agents Visits vs Meetings
        if (data.agents_visits_meetings && Array.isArray(data.agents_visits_meetings)) {
            renderAgentsVisitsVsMeetings(data.agents_visits_meetings);
        } else {
            renderAgentsVisitsVsMeetings([]);
        }

        // Property Segments
        if (data.property_segments) {
            renderPropertySegments(data.property_segments);
        } else {
            renderPropertySegments({});
        }

        // Telecaller Performance
        console.log('Telecaller Performance Data:', data.telecaller_performance);
        if (data.telecaller_performance && Array.isArray(data.telecaller_performance)) {
            renderTelecallerPerformance(data.telecaller_performance);
        } else {
            console.warn('No telecaller performance data or invalid format');
            renderTelecallerPerformance([]);
        }

        // Leads Pending Response
        if (data.leads_pending_response && Array.isArray(data.leads_pending_response)) {
            renderLeadsPendingResponse(data.leads_pending_response, data.server_now);
        } else {
            renderLeadsPendingResponse([], data.server_now);
        }

        // Average Response Time
        if (data.average_response_time_by_user && Array.isArray(data.average_response_time_by_user)) {
            renderAverageResponseTime(data.average_response_time_by_user);
        } else {
            renderAverageResponseTime([]);
        }

        renderMarketingSummary(
            data.marketing_summary || {},
            data.call_statistics || {},
            Array.isArray(data.recent_leads) ? data.recent_leads : []
        );
        renderAdSpendSummary(data.ad_spend_summary || {});
        renderAdminLeadSources(data.marketing_summary || {}, data.source_performance_analytics || {});
        renderAdminMetaDecisionAnalytics(data.source_performance_analytics || {});

    }

    function renderHeroMetrics(data) {
        const systemStats = data.system_stats || {};
        const marketingSummary = data.marketing_summary || {};
        const importSummary = marketingSummary.import_summary || {};
        const callStatistics = data.call_statistics || {};

        const totalLeads = document.getElementById('hero-total-leads');
        const connectionRate = document.getElementById('hero-connection-rate');
        const importedLeads = document.getElementById('hero-imported-leads');

        if (totalLeads) totalLeads.textContent = systemStats.total_leads || 0;
        if (connectionRate) connectionRate.textContent = `${Math.round(callStatistics.connection_rate || 0)}%`;
        if (importedLeads) importedLeads.textContent = importSummary.imported_leads || 0;
    }

    function renderDashboardShortcuts(shortcuts) {
        const container = document.getElementById('dashboard-shortcuts');
        if (!container) {
            return;
        }

        if (!Array.isArray(shortcuts) || shortcuts.length === 0) {
            container.innerHTML = '';
            return;
        }

        container.innerHTML = shortcuts.map((item) => `
            <a class="shortcut-card" href="${item.url || '#'}">
                <span class="shortcut-icon"><i class="fas ${item.icon || 'fa-layer-group'}"></i></span>
                <span>
                    <div class="shortcut-label">${item.label || 'Option'}</div>
                    <div class="shortcut-value">${item.count || 0}</div>
                </span>
            </a>
        `).join('');
    }

    function renderPerformanceScores(scores) {
        const container = document.getElementById('performance-scores');
        if (!container) {
            return;
        }

        const items = [
            ['PS Score', `${Number(scores.ps || 0).toFixed(1)}%`, '(Visits + Meetings) / Leads'],
            ['PP Score', `${Number(scores.pp || 0).toFixed(1)}%`, 'Closures / Leads'],
            ['VP Score', `${Number(scores.vp || 0).toFixed(1)}%`, 'Closures / Visits'],
        ];

        container.innerHTML = items.map(([label, value, note]) => `
            <div class="score-card">
                <div class="value">${value}</div>
                <div class="label">${label}</div>
                <div class="note">${note}</div>
            </div>
        `).join('');
    }

    function renderWeeklyActivitySummary(summary) {
        const container = document.getElementById('weekly-activity-summary');
        if (!container) {
            return;
        }

        latestWeeklyActivitySummary = summary || {};
        const appliedFilter = sectionFilters.pipeline_filter || 'global';
        const rangeLabel = summary.range_label || 'Selected Range';
        const filterLabel = summary.filter_label || 'Funnel Filter';
        const customStart = summary.custom_start_date || weeklyActivityCustomRange.start || '';
        const customEnd = summary.custom_end_date || weeklyActivityCustomRange.end || '';
        const rangeStart = summary.start_date || customStart || '';
        const rangeEnd = summary.end_date || customEnd || '';
        weeklyActivityCustomRange = { start: customStart, end: customEnd };
        persistWeeklyActivityCustomRange();
        const items = [
            ['Meetings', 'meetings', 'fa-calendar-check', 'meetings'],
            ['Visits', 'visits', 'fa-map-marker-alt', 'visits'],
        ];
        const filterButtons = [
            ['global', 'Global'],
            ['today', 'Today'],
            ['week', 'Week'],
            ['month', 'Month'],
            ['year', 'Year'],
            ['custom', 'Custom'],
        ].map(([value, label]) => `
            <button
                type="button"
                class="weekly-activity-filter-btn ${appliedFilter === value ? 'active' : ''}"
                data-section-filter="pipeline_filter"
                data-value="${value}"
                onclick="applySectionFilter('pipeline_filter', '${value}', this)"
            >${label}</button>
        `).join('');

        const cards = items.map(([label, key, icon, colorKey]) => {
            const item = summary[key] || {};
            const scheduled = Number(item.scheduled || 0);
            const completed = Number(item.completed || 0);
            const pending = Number(item.pending || 0);
            const completionRate = Math.max(0, Math.min(100, Number(item.completion_rate || 0)));
            const listPath = key === 'meetings' ? '/meetings' : '/site-visits';
            const rangeQuery = rangeStart && rangeEnd
                ? `date_filter=custom&date_from=${encodeURIComponent(rangeStart)}&date_to=${encodeURIComponent(rangeEnd)}`
                : '';
            const buildActivityUrl = (state) => {
                const params = [rangeQuery];
                if (state === 'completed') params.push('status=completed');
                if (state === 'pending') params.push('activity_state=pending');
                return `${listPath}?${params.filter(Boolean).join('&')}`;
            };

            return `
                <div class="weekly-activity-card">
                    <div class="weekly-activity-top">
                        <div class="weekly-activity-name">
                            <span class="weekly-activity-icon ${colorKey}"><i class="fas ${icon}"></i></span>
                            <span>${label}</span>
                        </div>
                        <span class="weekly-activity-badge">${completionRate}% complete</span>
                    </div>
                    <div class="weekly-activity-stats">
                        <a class="weekly-activity-stat" href="${buildActivityUrl('scheduled')}" aria-label="View ${label} scheduled in this range">
                            <strong>${scheduled}</strong>
                            <span>Scheduled</span>
                        </a>
                        <a class="weekly-activity-stat completed" href="${buildActivityUrl('completed')}" aria-label="View completed ${label.toLowerCase()} in this range">
                            <strong>${completed}</strong>
                            <span>Completed</span>
                        </a>
                        <a class="weekly-activity-stat pending" href="${buildActivityUrl('pending')}" aria-label="View pending ${label.toLowerCase()} in this range">
                            <strong>${pending}</strong>
                            <span>Pending</span>
                        </a>
                    </div>
                    <div class="weekly-activity-progress">
                        <div class="weekly-activity-track">
                            <div class="weekly-activity-fill ${colorKey}" style="width:${completionRate}%;"></div>
                        </div>
                        <span class="weekly-activity-percent">${completed} of ${scheduled} done</span>
                    </div>
                </div>
            `;
        }).join('');

        container.innerHTML = `
            <div class="weekly-activity-head">
                <div>
                    <div class="weekly-activity-title">Activity Summary</div>
                    <div class="weekly-activity-copy"></div>
                </div>
                <div class="weekly-activity-head-meta">
                    <div class="weekly-activity-filter-stack">
                        <div class="weekly-activity-filter-row">
                            <span class="weekly-activity-chip">${filterLabel} · ${rangeLabel}</span>
                            <button type="button" class="weekly-activity-filter-trigger" onclick="toggleMobilePanel('weekly-activity-filter-panel', this)" aria-controls="weekly-activity-filter-panel" aria-expanded="false" aria-label="Filter activity summary" title="Filter activity summary">
                                <i class="fas fa-sliders-h"></i>
                            </button>
                        </div>
                        <div class="weekly-activity-filter-panel" id="weekly-activity-filter-panel">
                            <div class="weekly-activity-filter-panel-head">
                            <span class="weekly-activity-filter-panel-title">Filter Activity + Funnel</span>
                                <button type="button" class="weekly-activity-filter-close" onclick="closeMobilePanel('weekly-activity-filter-panel', this)" aria-label="Close filters">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div class="weekly-activity-filter-group">${filterButtons}</div>
                            ${appliedFilter === 'custom' ? `
                            <div class="weekly-activity-custom-range">
                                <input
                                    type="date"
                                    class="weekly-activity-date-input"
                                    value="${pipelineCustomRange.start || customStart}"
                                    onchange="handlePipelineDateInput(this, 'start')"
                                >
                                <input
                                    type="date"
                                    class="weekly-activity-date-input"
                                    value="${pipelineCustomRange.end || customEnd}"
                                    onchange="handlePipelineDateInput(this, 'end')"
                                >
                                <button type="button" class="weekly-activity-apply-btn" onclick="applyPipelineCustomRange()">Apply Range</button>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
            </div>
            <div class="weekly-activity-grid">${cards}</div>
        `;
    }

    function renderPipelineFunnel(items) {
        const useClassic = new URLSearchParams(window.location.search).get('funnel_design') === 'classic';
        if (useClassic) {
            renderPipelineFunnelClassic(items);
            return;
        }

        renderPipelineFunnelMinimal(items);
    }

    function renderPipelineFunnelMinimal(items) {
        const container = document.getElementById('pipeline-funnel');
        if (!container) {
            return;
        }

        const positiveStages = ['total leads', 'hr leads', 'new leads', 'cnp', 'follow up', 'active prospects', 'meeting done', 'site visit done', 'closures'];
        const lossStages = ['junk', 'not interested', 'dead'];
        const palette = ['#064e3b', '#12633f', '#28754b', '#45885b', '#649b6c', '#83ad7c', '#a1be8e', '#b9cc9c', '#cfdaa9'];
        const safeItems = Array.isArray(items) ? items : [];
        if (safeItems.length === 0) {
            container.innerHTML = '<p style="color:#6b7a70;font-size:13px;">No funnel data available.</p>';
            return;
        }

        const normalized = safeItems.map((item) => ({ ...item, key: String(item.label || '').trim().toLowerCase() }));
        const positive = positiveStages.map((key) => normalized.find((item) => item.key === key)).filter(Boolean);
        const losses = lossStages.map((key) => normalized.find((item) => item.key === key)).filter(Boolean);
        const totalLeads = Number(positive.find((item) => item.key === 'total leads')?.value || 0);
        const totalVisits = Number(positive.find((item) => item.key === 'site visit done')?.value || 0);
        const totalClosures = Number(positive.find((item) => item.key === 'closures')?.value || 0);
        const visitRate = totalLeads > 0 ? ((totalVisits / totalLeads) * 100).toFixed(1) : '0.0';
        const closureRate = totalLeads > 0 ? ((totalClosures / totalLeads) * 100).toFixed(1) : '0.0';

        const funnelSteps = positive.map((item, index) => {
            const width = Math.max(38, 94 - (index * 7));
            return `<a href="${buildPipelineStageUrl(item.key)}" class="funnel-minimal-step" style="width:${width}%;background:${palette[index] || '#287553'};">${Number(item.value || 0).toLocaleString()}</a>`;
        }).join('');

        const stageRows = positive.map((item) => {
            const percentage = Math.max(0, Math.min(100, Number(item.percentage || 0)));
            return `
                <a href="${buildPipelineStageUrl(item.key)}" class="funnel-minimal-row">
                    <span>${item.label || 'Stage'}</span>
                    <span>${percentage.toFixed(1)}%</span>
                    <span class="funnel-minimal-progress"><i style="width:${Math.max(3, percentage)}%;"></i></span>
                    <span class="funnel-minimal-count">${Number(item.value || 0).toLocaleString()}</span>
                </a>
            `;
        }).join('');

        const lossRows = losses.length ? losses.map((item) => `
            <a href="${buildPipelineStageUrl(item.key)}" class="funnel-minimal-loss">
                <strong>${item.label || 'Loss'}</strong>
                <span>${Number(item.value || 0).toLocaleString()}</span>
            </a>
        `).join('') : '<div class="funnel-minimal-loss"><strong>No loss bucket data</strong><span>0</span></div>';

        container.innerHTML = `
            <div class="funnel-minimal-kpis">
                <div class="funnel-minimal-kpi"><span>Total Leads</span><strong>${totalLeads.toLocaleString()}</strong><small>Selected period</small></div>
                <div class="funnel-minimal-kpi"><span>Lead To Visit</span><strong>${visitRate}%</strong><small>${totalVisits.toLocaleString()} site visits</small></div>
                <div class="funnel-minimal-kpi"><span>Lead To Closure</span><strong>${closureRate}%</strong><small>${totalClosures.toLocaleString()} closures</small></div>
            </div>
            <div class="funnel-minimal-grid">
                <div>
                    <div class="funnel-minimal-card">
                        <div class="funnel-minimal-heading">Conversion Funnel</div>
                        <div class="funnel-minimal-visual">${funnelSteps}</div>
                    </div>
                    <div class="funnel-minimal-card" style="margin-top:14px;">
                        <div class="funnel-minimal-heading">Loss Buckets</div>
                        <div class="funnel-minimal-losses">${lossRows}</div>
                    </div>
                </div>
                <div class="funnel-minimal-card">
                    <div class="funnel-minimal-heading">Current Stage Distribution</div>
                    <div class="funnel-minimal-table-head"><span>Stage</span><span>Share</span><span>Progress</span><span>Count</span></div>
                    ${stageRows}
                </div>
                <div class="funnel-demand-panel" id="pipeline-demand-panel">
                    <div class="funnel-demand-card"><div class="funnel-demand-head"><div class="funnel-demand-title"><i class="fas fa-building"></i><span>Property Type</span></div><div class="funnel-demand-total" id="pipeline-property-total">0</div></div><div class="funnel-demand-list" id="pipeline-property-list"><div class="funnel-demand-empty">Loading demand...</div></div></div>
                    <div class="funnel-demand-card"><div class="funnel-demand-head"><div class="funnel-demand-title"><i class="fas fa-indian-rupee-sign"></i><span>Budget</span></div><div class="funnel-demand-total" id="pipeline-budget-total">0</div></div><div class="funnel-demand-list" id="pipeline-budget-list"><div class="funnel-demand-empty">Loading demand...</div></div></div>
                    <div class="funnel-demand-card"><div class="funnel-demand-head"><div class="funnel-demand-title"><i class="fas fa-location-dot"></i><span>Top Locations</span></div><div class="funnel-demand-total" id="pipeline-location-total">0</div></div><div class="funnel-demand-list" id="pipeline-location-list"><div class="funnel-demand-empty">Loading demand...</div></div></div>
                </div>
            </div>
        `;
        renderPipelineDemandMiniCards(latestDemandInsights || {});
    }

    function renderPipelineFunnelClassic(items) {
        const container = document.getElementById('pipeline-funnel');
        if (!container) {
            return;
        }

        const positiveStages = ['total leads', 'hr leads', 'new leads', 'cnp', 'follow up', 'active prospects', 'meeting done', 'site visit done', 'closures'];
        const lossStages = ['junk', 'not interested', 'dead'];
          const stageMeta = {
              'total leads': { color: '#2aa889', icon: 'fa-users', width: '90%' },
              'hr leads': { color: '#14b8a6', icon: 'fa-user-tie', width: '82%' },
              'new leads': { color: '#d79d2a', icon: 'fa-inbox', width: '75%' },
              cnp: { color: '#f97316', icon: 'fa-phone-slash', width: '70%' },
              'follow up': { color: '#2563eb', icon: 'fa-phone-volume', width: '65%' },
              'active prospects': { color: '#3097c8', icon: 'fa-user-plus', width: '60%' },
              'meeting done': { color: '#3979be', icon: 'fa-calendar-check', width: '51%' },
              'site visit done': { color: '#5566c8', icon: 'fa-eye', width: '44%' },
              closures: { color: '#695bb7', icon: 'fa-flag-checkered', width: '36%' },
              junk: { color: '#7c8698', icon: 'fa-trash-alt' },
              'not interested': { color: '#8b93a3', icon: 'fa-ban' },
              dead: { color: '#b42318', icon: 'fa-skull-crossbones' },
          };
        const safeItems = Array.isArray(items) ? items : [];
        if (safeItems.length === 0) {
            container.innerHTML = '<p style="color:#9ca3af;font-size:13px;">No funnel data available.</p>';
            return;
        }

        const normalized = safeItems.map((item) => {
            const key = String(item.label || '').trim().toLowerCase();
            return { ...item, key };
        });
        const positive = positiveStages.map((key) => normalized.find((item) => item.key === key)).filter(Boolean);
        const losses = lossStages.map((key) => normalized.find((item) => item.key === key)).filter(Boolean);

        const totalLeads = Number(positive.find((item) => item.key === 'total leads')?.value || 0);
        const totalVisits = Number(positive.find((item) => item.key === 'site visit done')?.value || 0);
        const totalClosures = Number(positive.find((item) => item.key === 'closures')?.value || 0);
        const visitRate = totalLeads > 0 ? ((totalVisits / totalLeads) * 100).toFixed(1) : '0.0';
        const closureRate = totalLeads > 0 ? ((totalClosures / totalLeads) * 100).toFixed(1) : '0.0';

        const visualMarkup = positive.map((item) => {
            const meta = stageMeta[item.key] || { color: '#12a999', width: '60%' };
            return `
                <div class="funnel-segment" style="width:${meta.width};background:${meta.color};">
                    <span>${Number(item.value || 0).toLocaleString()}</span>
                </div>
            `;
        }).join('');

        const stageMarkup = positive.map((item) => {
            const width = Math.max(8, Math.min(100, Number(item.percentage || 0)));
            const meta = stageMeta[item.key] || { color: '#12a999', icon: 'fa-circle' };
            const target = buildPipelineStageUrl(item.key);
            return `
                <a href="${target}" class="funnel-stage-link">
                    <div class="funnel-stage-copy">
                        <div class="funnel-line-icon" style="background:${meta.color};">
                            <i class="fas ${meta.icon}"></i>
                        </div>
                        <div class="funnel-line-label">${item.label || 'Stage'}</div>
                    </div>
                    <div class="funnel-stage-sub">${Number(item.percentage || 0).toFixed(1)}%</div>
                    <div class="funnel-line-track">
                        <div class="funnel-line-fill" style="width:${width}%;background:${meta.color};"></div>
                    </div>
                    <div class="funnel-line-meta">
                        <div class="funnel-line-value">${Number(item.value || 0).toLocaleString()}</div>
                    </div>
                </a>
            `;
        }).join('');

        const lossesMarkup = losses.length
            ? losses.map((item) => {
                const meta = stageMeta[item.key] || { color: '#b42318', icon: 'fa-circle' };
                const target = buildPipelineStageUrl(item.key);
                return `
                    <a href="${target}" class="funnel-loss-item" style="text-decoration:none;">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div class="funnel-line-icon" style="width:36px;height:36px;border-radius:12px;background:${meta.color};">
                                <i class="fas ${meta.icon}"></i>
                            </div>
                            <div>
                                <strong>${item.label || 'Loss'}</strong>
                                <span>${Number(item.percentage || 0).toFixed(1)}% of leads</span>
                            </div>
                        </div>
                        <div class="funnel-loss-value">${Number(item.value || 0).toLocaleString()}</div>
                    </a>
                `;
            }).join('')
            : '<div class="funnel-loss-item"><div><strong>No loss bucket data</strong><span>Negative outcomes will appear here</span></div><div class="funnel-loss-value">0</div></div>';

        container.innerHTML = `
            <div class="funnel-kpi-strip">
                <div class="funnel-kpi-card">
                    <div class="label">Total Leads</div>
                    <div class="value">${totalLeads.toLocaleString()}</div>
                    <div class="note">Top-of-funnel volume in current window</div>
                </div>
                <div class="funnel-kpi-card">
                    <div class="label">Lead To Visit</div>
                    <div class="value">${visitRate}%</div>
                    <div class="note">${totalVisits.toLocaleString()} visits from all leads</div>
                </div>
                <div class="funnel-kpi-card">
                    <div class="label">Lead To Closure</div>
                    <div class="value">${closureRate}%</div>
                    <div class="note">${totalClosures.toLocaleString()} closures from all leads</div>
                </div>
            </div>
            <div class="funnel-main-grid">
                <div class="funnel-left-stack">
                    <div class="funnel-visual">
                        <div class="funnel-visual-shell">
                            <div class="funnel-cap"></div>
                            ${visualMarkup}
                        </div>
                    </div>
                    <div class="funnel-loss-card">
                        <div class="funnel-loss-header">
                            <div class="funnel-loss-title">Loss Buckets</div>
                            <div class="funnel-loss-total">${losses.reduce((sum, item) => sum + Number(item.value || 0), 0).toLocaleString()} total</div>
                        </div>
                        <div class="funnel-loss-grid">
                            <div class="funnel-loss-table-head"><span>Bucket</span><span>Count</span></div>
                            ${lossesMarkup}
                        </div>
                    </div>
                </div>
                <div style="display:flex;flex-direction:column;gap:12px;">
                    <div class="funnel-stage-panel">
                        <div class="funnel-panel-heading">
                            <div class="funnel-panel-title">Current Stage Distribution</div>
                            <div class="funnel-panel-hint">Click a row to open filtered leads</div>
                        </div>
                        <div class="funnel-stage-list">
                            <div class="funnel-stage-table-head"><span>Stage</span><span>Share</span><span>Progress</span><span>Count</span></div>
                            ${stageMarkup}
                        </div>
                    </div>
                </div>
                <div class="funnel-demand-panel" id="pipeline-demand-panel">
                    <div class="funnel-demand-card">
                        <div class="funnel-demand-head">
                            <div class="funnel-demand-title"><i class="fas fa-building"></i><span>Property Type</span></div>
                            <div class="funnel-demand-total" id="pipeline-property-total">0</div>
                        </div>
                        <div class="funnel-demand-list" id="pipeline-property-list">
                            <div class="funnel-demand-empty">Loading demand...</div>
                        </div>
                    </div>
                    <div class="funnel-demand-card">
                        <div class="funnel-demand-head">
                            <div class="funnel-demand-title"><i class="fas fa-indian-rupee-sign"></i><span>Budget</span></div>
                            <div class="funnel-demand-total" id="pipeline-budget-total">0</div>
                        </div>
                        <div class="funnel-demand-list" id="pipeline-budget-list">
                            <div class="funnel-demand-empty">Loading demand...</div>
                        </div>
                    </div>
                    <div class="funnel-demand-card">
                        <div class="funnel-demand-head">
                            <div class="funnel-demand-title"><i class="fas fa-location-dot"></i><span>Top Locations</span></div>
                            <div class="funnel-demand-total" id="pipeline-location-total">0</div>
                        </div>
                        <div class="funnel-demand-list" id="pipeline-location-list">
                            <div class="funnel-demand-empty">Loading demand...</div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        renderPipelineDemandMiniCards(latestDemandInsights || {});
    }

    function renderApprovalCenter(approvalCenter) {
        latestApprovalCenter = approvalCenter || { items: [] };
        const listEl = document.getElementById('approval-center-list');
        const filtersEl = document.getElementById('approval-center-filters');
        if (!listEl) return;

        const items = Array.isArray(latestApprovalCenter.items) ? latestApprovalCenter.items : [];
        const filteredItems = currentApprovalFilter === 'all'
            ? items
            : items.filter((item) => item.type === currentApprovalFilter);

        if (filtersEl) {
            filtersEl.querySelectorAll('[data-approval-filter]').forEach((button) => {
                const type = button.getAttribute('data-approval-filter');
                const count = type === 'all'
                    ? items.length
                    : items.filter((item) => item.type === type).length;
                const label = type === 'all' ? 'All' : button.textContent.replace(/\s*\(\d+\)$/, '');
                button.textContent = `${label} (${count})`;
                button.classList.toggle('active', type === currentApprovalFilter);
            });
        }

        if (!filteredItems.length) {
            listEl.innerHTML = '<div class="approval-empty">No pending approvals in this filter.</div>';
            return;
        }

        listEl.innerHTML = filteredItems.map((item) => {
            const summary = escapeHtml(item.summary || '').replace(/\. /g, '.<br>');
            return `
            <div class="approval-center-row" data-approval-type="${escapeHtml(item.type || '')}">
                <div class="approval-icon ${escapeHtml(item.type || '')}">${escapeHtml(item.icon || '')}</div>
                <div>
                    <div class="approval-row-title">
                        <span>${escapeHtml(item.title || 'Approval')}</span>
                        ${item.badge ? `<span class="approval-badge">${escapeHtml(item.badge)}</span>` : ''}
                    </div>
                    <div class="approval-summary">${summary}</div>
                </div>
                <div class="approval-age">${escapeHtml(item.age || '')}</div>
                <div class="approval-actions">
                    <button type="button" class="approval-action-btn" onclick="openApprovalItem('${escapeJsString(item.id || '')}')">View</button>
                    <button type="button" class="approval-action-btn approve" onclick="submitApprovalAction('${escapeJsString(item.id || '')}', 'approve')">Approve</button>
                    <button type="button" class="approval-action-btn reject" onclick="submitApprovalAction('${escapeJsString(item.id || '')}', 'reject')">Reject</button>
                </div>
            </div>
        `;
        }).join('');
    }

    function filterApprovalCenter(type, button) {
        currentApprovalFilter = type || 'all';
        if (button) {
            document.querySelectorAll('[data-approval-filter]').forEach((filterButton) => filterButton.classList.remove('active'));
            button.classList.add('active');
        }
        renderApprovalCenter(latestApprovalCenter || {});
    }

    function openApprovalItem(itemId) {
        const items = Array.isArray(latestApprovalCenter.items) ? latestApprovalCenter.items : [];
        const item = items.find((row) => row.id === itemId);
        if (!item) return;

        pendingApprovalViewItem = item;
        const modal = document.getElementById('approval-view-modal');
        const titleEl = document.getElementById('approval-view-title');
        const copyEl = document.getElementById('approval-view-copy');
        const summaryEl = document.getElementById('approval-view-summary');
        const metaEl = document.getElementById('approval-view-meta');
        const iconEl = document.getElementById('approval-view-icon');
        const detailGridEl = document.getElementById('approval-view-detail-grid');

        if (titleEl) titleEl.textContent = item.title || 'Approval detail';
        if (copyEl) copyEl.textContent = `${item.badge || item.type || 'Pending'} approval`;
        if (summaryEl) summaryEl.textContent = item.summary || '--';
        if (metaEl) metaEl.textContent = `${item.requester || 'Requester'} - ${item.age || 'Pending'} - ${String(item.type || '').toUpperCase()}`;
        if (iconEl) {
            iconEl.textContent = item.icon || 'A';
            iconEl.className = `approval-modal-icon approval-icon ${item.type || ''}`;
        }
        if (detailGridEl) {
            detailGridEl.querySelectorAll('[data-approval-detail-extra="1"]').forEach((node) => node.remove());
            const details = Array.isArray(item.details)
                ? item.details.filter((row) => row && row.label)
                : [];
            const detailHtml = details.length
                ? details.map((row) => {
                    const value = row.value == null || row.value === '' ? '--' : String(row.value);
                    const fullClass = value.length > 70 ? ' full' : '';
                    return `
                        <div class="approval-detail-box${fullClass}" data-approval-detail-extra="1">
                            <div class="approval-detail-label">${escapeHtml(row.label)}</div>
                            <div class="approval-detail-value">${escapeHtml(value)}</div>
                        </div>
                    `;
                }).join('')
                : `
                    <div class="approval-detail-box full" data-approval-detail-extra="1">
                        <div class="approval-detail-label">More Details</div>
                        <div class="approval-detail-value">Extra details available nahi hain. Open page se full record dekh sakte hain.</div>
                    </div>
                `;
            detailGridEl.insertAdjacentHTML('beforeend', detailHtml);
        }
        if (modal) {
            modal.classList.add('active');
            modal.setAttribute('aria-hidden', 'false');
        }
    }

    function closeApprovalViewModal() {
        pendingApprovalViewItem = null;
        const modal = document.getElementById('approval-view-modal');
        if (modal) {
            modal.classList.remove('active');
            modal.setAttribute('aria-hidden', 'true');
        }
    }

    function approveFromApprovalView() {
        const item = pendingApprovalViewItem;
        if (!item) return;
        closeApprovalViewModal();
        submitApprovalAction(item.id, 'approve');
    }

    function rejectFromApprovalView() {
        const item = pendingApprovalViewItem;
        if (!item) return;
        closeApprovalViewModal();
        submitApprovalAction(item.id, 'reject');
    }

    async function submitApprovalAction(itemId, action) {
        const items = Array.isArray(latestApprovalCenter.items) ? latestApprovalCenter.items : [];
        const item = items.find((row) => row.id === itemId);
        if (!item) return;

        const isReject = action === 'reject';
        const actionUrl = isReject ? item.reject_url : item.approve_url;
        if (!actionUrl) return;

        const body = new FormData();
        if (isReject) {
            openApprovalRejectModal(item);
            return;
        } else if (!window.confirm('Approve this request?')) {
            return;
        }

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const response = await fetch(actionUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body,
                credentials: 'same-origin',
            });

            if (!response.ok) {
                let errorText = 'Approval action failed.';
                try {
                    const errorData = await response.json();
                    errorText = errorData.message || Object.values(errorData.errors || {}).flat().join('\n') || errorText;
                } catch (error) {
                    errorText = await response.text() || errorText;
                }
                throw new Error(errorText);
            }

            await loadDashboardData({ silent: true, preserveScroll: true, forceRefresh: true });
        } catch (error) {
            console.error('Approval action error:', error);
            alert(error.message || 'Approval action failed.');
        }
    }

    function openApprovalRejectModal(item) {
        pendingApprovalRejectItem = item;
        const modal = document.getElementById('approval-reject-modal');
        const titleEl = document.getElementById('approval-reject-title');
        const copyEl = document.getElementById('approval-reject-copy');
        const remarksEl = document.getElementById('approval-reject-remarks');
        const errorEl = document.getElementById('approval-reject-error');

        if (titleEl) titleEl.textContent = `Reject ${item.title || 'approval'}`;
        if (copyEl) copyEl.textContent = item.summary || 'Please add a clear reason before rejecting this request.';
        if (remarksEl) {
            remarksEl.value = '';
            setTimeout(() => remarksEl.focus(), 60);
        }
        if (errorEl) errorEl.classList.remove('active');
        if (modal) {
            modal.classList.add('active');
            modal.setAttribute('aria-hidden', 'false');
        }
    }

    function closeApprovalRejectModal() {
        pendingApprovalRejectItem = null;
        const modal = document.getElementById('approval-reject-modal');
        const errorEl = document.getElementById('approval-reject-error');
        if (errorEl) errorEl.classList.remove('active');
        if (modal) {
            modal.classList.remove('active');
            modal.setAttribute('aria-hidden', 'true');
        }
    }

    async function confirmApprovalReject() {
        const item = pendingApprovalRejectItem;
        if (!item || !item.reject_url) return;

        const remarksEl = document.getElementById('approval-reject-remarks');
        const errorEl = document.getElementById('approval-reject-error');
        const submitBtn = document.getElementById('approval-reject-submit');
        const reason = String(remarksEl?.value || '').trim();

        if (!reason) {
            if (errorEl) errorEl.classList.add('active');
            return;
        }

        const body = new FormData();
        body.append(item.reject_field || 'remarks', reason);

        try {
            if (submitBtn) submitBtn.disabled = true;
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const response = await fetch(item.reject_url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body,
                credentials: 'same-origin',
            });

            if (!response.ok) {
                let errorText = 'Approval reject failed.';
                try {
                    const errorData = await response.json();
                    errorText = errorData.message || Object.values(errorData.errors || {}).flat().join('\n') || errorText;
                } catch (error) {
                    errorText = await response.text() || errorText;
                }
                throw new Error(errorText);
            }

            closeApprovalRejectModal();
            await loadDashboardData({ silent: true, preserveScroll: true, forceRefresh: true });
        } catch (error) {
            console.error('Approval reject error:', error);
            alert(error.message || 'Approval reject failed.');
        } finally {
            if (submitBtn) submitBtn.disabled = false;
        }
    }

    function renderDemandInsights(insights) {
        latestDemandInsights = insights || {};
        hydrateDemandChartToggles();
        const propertyRows = Array.isArray(insights.property_types) ? insights.property_types : [];
        const budgetRows = Array.isArray(insights.budget_ranges) ? insights.budget_ranges : [];
        renderPipelineDemandMiniCards(latestDemandInsights);
        renderDemandInsightCard({
            chartId: 'adminDemandPropertyChart',
            listId: 'demand-property-list',
            totalId: 'demand-property-total',
            rows: propertyRows,
            chartRef: 'property',
            type: demandChartTypes.property || 'doughnut',
            colors: ['#0b553d', '#247653', '#4f956c', '#7bb286', '#a9c8a6', '#d8e5d6'],
        });
        renderDemandInsightCard({
            chartId: 'adminDemandBudgetChart',
            listId: 'demand-budget-list',
            totalId: 'demand-budget-total',
            rows: budgetRows,
            chartRef: 'budget',
            type: demandChartTypes.budget || 'doughnut',
            colors: ['#0b553d', '#247653', '#4f956c', '#7bb286', '#a9c8a6', '#d8e5d6'],
        });
    }

    function renderPipelineDemandMiniCards(insights) {
        const propertyRows = Array.isArray(insights?.property_types) ? insights.property_types : [];
        const budgetRows = Array.isArray(insights?.budget_ranges) ? insights.budget_ranges : [];
        const locationRows = Array.isArray(insights?.locations) ? insights.locations : [];
        renderPipelineDemandMiniList('pipeline-property-list', 'pipeline-property-total', propertyRows, 'property');
        renderPipelineDemandMiniList('pipeline-budget-list', 'pipeline-budget-total', budgetRows, 'budget');
        renderPipelineDemandMiniList('pipeline-location-list', 'pipeline-location-total', locationRows, 'location');
    }

    function renderPipelineDemandMiniList(listId, totalId, rows, chartRef) {
        const listEl = document.getElementById(listId);
        const totalEl = document.getElementById(totalId);
        if (!listEl && !totalEl) return;

        const safeRows = Array.isArray(rows) ? rows : [];
        const total = safeRows.reduce((sum, row) => sum + Number(row.count || 0), 0);
        if (totalEl) {
            totalEl.textContent = `${total.toLocaleString('en-IN')} total`;
        }
        if (!listEl) return;

        const visibleRows = safeRows
            .filter((row) => Number(row.count || 0) > 0)
            .slice(0, 4);

        if (!visibleRows.length) {
            listEl.innerHTML = '<div class="funnel-demand-empty">No demand data</div>';
            return;
        }

        listEl.innerHTML = visibleRows.map((row) => {
            const label = row.label || 'Undefined';
            const percent = Math.max(0, Math.min(100, Number(row.percentage || 0)));
            const target = (() => {
                const url = new URL('/leads', window.location.origin);
                if (chartRef === 'property') {
                    url.searchParams.set('demand_property', label);
                } else if (chartRef === 'budget') {
                    url.searchParams.set('demand_budget', label);
                } else {
                    url.searchParams.set('search', label);
                }
                return url.toString();
            })();

            return `
                <a class="funnel-demand-row" href="${target}">
                    <span class="funnel-demand-copy">
                        <span class="funnel-demand-label">${escapeHtml(label)}</span>
                        <span class="funnel-demand-track"><span class="funnel-demand-fill" style="width:${Math.max(6, percent)}%;"></span></span>
                    </span>
                    <span class="funnel-demand-value">${Number(row.count || 0).toLocaleString('en-IN')}<span>${percent.toFixed(1)}%</span></span>
                </a>
            `;
        }).join('');
    }

    function setDemandChartType(chartRef, type) {
        demandChartTypes[chartRef] = type === 'bar' ? 'bar' : 'doughnut';
        localStorage.setItem('adminDemandChartTypes', JSON.stringify(demandChartTypes));
        renderDemandInsights(latestDemandInsights || {});
    }

    function hydrateDemandChartToggles() {
        document.querySelectorAll('[data-demand-toggle]').forEach((group) => {
            const chartRef = group.getAttribute('data-demand-toggle');
            const activeType = demandChartTypes[chartRef] || 'doughnut';
            group.querySelectorAll('[data-demand-chart-type]').forEach((button) => {
                button.classList.toggle('active', button.getAttribute('data-demand-chart-type') === activeType);
            });
        });
    }

    function renderDemandInsightCard({ chartId, listId, totalId, rows, chartRef, type, colors }) {
        const safeRows = Array.isArray(rows) ? rows : [];
        const total = safeRows.reduce((sum, row) => sum + Number(row.count || 0), 0);
        const totalEl = document.getElementById(totalId);
        const listEl = document.getElementById(listId);
        const ctx = document.getElementById(chartId);

        if (totalEl) {
            totalEl.textContent = total.toLocaleString('en-IN');
        }

        if (listEl) {
            const visibleRows = safeRows.filter((row) => Number(row.count || 0) > 0);
            const bodyRows = (visibleRows.length ? visibleRows : safeRows).map((row) => `
                <div class="demand-insight-row is-clickable" onclick="openDemandInsightLeads('${chartRef}', '${escapeJsString(row.label || 'Undefined')}')">
                    <span class="label">${escapeHtml(row.label || 'Undefined')}</span>
                    <span class="count">${Number(row.count || 0).toLocaleString('en-IN')}</span>
                    <span class="percent">${Number(row.percentage || 0).toFixed(1)}%</span>
                </div>
            `).join('') || '<div class="demand-insight-row"><span class="label">No data</span><span class="count">0</span><span class="percent">0%</span></div>';
            listEl.innerHTML = `
                <div class="demand-insight-table-head"><span>Segment</span><span>Count</span><span>%</span></div>
                ${bodyRows}
            `;
        }

        if (!ctx) {
            return;
        }

        if (chartRef === 'property' && adminDemandPropertyChart) {
            adminDemandPropertyChart.destroy();
        }
        if (chartRef === 'budget' && adminDemandBudgetChart) {
            adminDemandBudgetChart.destroy();
        }

        const isBar = type === 'bar';
        const chart = new Chart(ctx, {
            type: isBar ? 'bar' : 'doughnut',
            data: {
                labels: safeRows.map((row) => row.label || 'Undefined'),
                datasets: [{
                    data: safeRows.map((row) => Number(row.count || 0)),
                    backgroundColor: colors,
                    borderRadius: isBar ? 10 : 4,
                    barThickness: isBar ? 18 : undefined,
                    borderWidth: isBar ? 0 : 2,
                    borderColor: '#ffffff',
                }],
            },
            options: {
                indexAxis: isBar ? 'y' : undefined,
                responsive: true,
                maintainAspectRatio: false,
                cutout: isBar ? undefined : '62%',
                onClick: (event, elements) => {
                    if (!elements.length) {
                        return;
                    }
                    const row = safeRows[elements[0].index];
                    if (row) {
                        openDemandInsightLeads(chartRef, row.label || 'Undefined');
                    }
                },
                plugins: {
                    legend: { display: !isBar, position: 'bottom', labels: { boxWidth: 10, usePointStyle: true } },
                    tooltip: {
                        callbacks: {
                            label: (context) => `${Number(context.raw || 0).toLocaleString('en-IN')} leads`,
                        },
                    },
                },
                scales: isBar ? {
                    x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#eef2ee' } },
                    y: { grid: { display: false }, ticks: { color: '#475569', font: { size: 11, weight: 700 } } },
                } : {},
            },
        });

        if (chartRef === 'property') {
            adminDemandPropertyChart = chart;
        } else {
            adminDemandBudgetChart = chart;
        }
    }

    function openDemandInsightLeads(chartRef, label) {
        const url = new URL('{{ route('leads.index') }}', window.location.origin);
        url.searchParams.set(chartRef === 'property' ? 'demand_property' : 'demand_budget', label || 'Undefined');
        appendSectionDateRangeToUrl(url, 'demand_filter');
        window.location.href = url.toString();
    }

    function mapDashboardPresetToLeadDateRange(preset) {
        const map = {
            today: 'today',
            week: 'this_week',
            month: 'this_month',
            year: 'this_year',
            previous_week: 'this_week',
            next_week: 'this_week',
        };

        return map[preset] || '';
    }

    function appendSectionDateRangeToUrl(url, filterKey) {
        const currentSectionFilter = sectionFilters[filterKey] || 'global';

        if (filterKey === 'pipeline_filter' && currentSectionFilter === 'custom' && pipelineCustomRange.start && pipelineCustomRange.end) {
            url.searchParams.set('date_range', 'custom');
            url.searchParams.set('start_date', pipelineCustomRange.start);
            url.searchParams.set('end_date', pipelineCustomRange.end);
            return;
        }

        if (filterKey === 'demand_filter' && currentSectionFilter === 'custom' && demandCustomRange.start && demandCustomRange.end) {
            url.searchParams.set('date_range', 'custom');
            url.searchParams.set('start_date', demandCustomRange.start);
            url.searchParams.set('end_date', demandCustomRange.end);
            return;
        }

        if (filterKey === 'weekly_activity_filter' && currentSectionFilter === 'custom' && weeklyActivityCustomRange.start && weeklyActivityCustomRange.end) {
            url.searchParams.set('date_range', 'custom');
            url.searchParams.set('start_date', weeklyActivityCustomRange.start);
            url.searchParams.set('end_date', weeklyActivityCustomRange.end);
            return;
        }

        if (currentSectionFilter && currentSectionFilter !== 'global') {
            const mappedRange = mapDashboardPresetToLeadDateRange(currentSectionFilter);
            if (mappedRange) {
                url.searchParams.set('date_range', mappedRange);
                return;
            }
        }

        appendDashboardDateRangeToUrl(url);
    }

    function appendDashboardDateRangeToUrl(url) {
        if (currentFilter === 'custom' && customStartDate && customEndDate) {
            url.searchParams.set('date_range', 'custom');
            url.searchParams.set('start_date', customStartDate);
            url.searchParams.set('end_date', customEndDate);
            return;
        }

        const mappedRange = mapDashboardPresetToLeadDateRange(currentFilter);
        if (mappedRange) {
            url.searchParams.set('date_range', mappedRange);
        }
    }

    function buildMetaOutcomeLeadUrl(row, outcome) {
        const url = new URL(LEADS_INDEX_URL, window.location.origin);
        const formId = Number(row?.form_id || 0);

        if (formId > 0) {
            url.searchParams.set('meta_form_id', String(formId));
        }

        url.searchParams.set('meta_outcome', outcome);
        url.searchParams.set('lead_group', 'sales');
        appendSectionDateRangeToUrl(url, 'source_filter');

        return url.toString();
    }

    async function syncMetaSpend(triggerButton = null) {
        const button = triggerButton || document.getElementById('meta-spend-sync-btn');
        const allButtons = Array.from(document.querySelectorAll('.meta-sync-trigger'));
        const status = document.getElementById('meta-spend-sync-status');
        const originalHtml = button ? button.innerHTML : '';
        const params = new URLSearchParams();

        params.append('filter', currentFilter);
        const sourceFilter = sectionFilters.source_filter || 'global';
        if (sourceFilter && sourceFilter !== 'global') {
            params.append('source_filter', sourceFilter);
        }
        if (customStartDate && customEndDate) {
            params.append('start_date', customStartDate);
            params.append('end_date', customEndDate);
        }

        allButtons.forEach((syncButton) => {
            syncButton.disabled = true;
        });
        if (button) {
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Syncing';
        }
        if (status) {
            status.className = 'meta-sync-status';
            status.textContent = 'Please wait...';
        }

        try {
            const response = await fetch(META_SPEND_SYNC_URL, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'Accept': 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                },
                credentials: 'same-origin',
                body: params.toString(),
            });
            const data = await response.json().catch(() => ({}));

            if (!response.ok || data.success === false) {
                throw new Error(data.message || 'Spend sync failed');
            }

            if (status) {
                status.className = 'meta-sync-status success';
                status.textContent = data.message || 'Spend synced';
            }

            dashboardModeCache.clear();
            await loadSectionData('source_filter', { preserveScroll: true });
        } catch (error) {
            console.error('Meta spend sync failed:', error);
            if (status) {
                status.className = 'meta-sync-status error';
                status.textContent = error.message || 'Sync failed';
            } else {
                alert(error.message || 'Sync failed');
            }
        } finally {
            allButtons.forEach((syncButton) => {
                syncButton.disabled = false;
            });
            if (button && originalHtml) {
                button.innerHTML = originalHtml;
            }
        }
    }

    function buildPipelineStageUrl(stageKey) {
        const baseRoutes = {
            'total leads': '{{ route('leads.index') }}',
            'hr leads': '{{ route('leads.index', ['lead_group' => 'hr']) }}',
            'new leads': '{{ route('leads.index', ['pipeline_stage' => 'new_leads']) }}',
            cnp: '{{ route('leads.index', ['pipeline_stage' => 'cnp']) }}',
            'follow up': '{{ route('leads.index', ['pipeline_stage' => 'follow_up']) }}',
            'active prospects': '{{ route('prospects.index') }}',
            'meeting done': '{{ route('leads.index', ['lead_type_filter' => 'meeting']) }}',
            'site visit done': '{{ route('leads.index', ['lead_type_filter' => 'visit']) }}',
            closures: '{{ route('leads.index', ['lead_type_filter' => 'closer']) }}',
            junk: '{{ route('admin.other-leads.index', ['type' => 'junk']) }}',
            'not interested': '{{ route('admin.other-leads.index', ['type' => 'not_interested']) }}',
            dead: '{{ route('admin.other-leads.index', ['type' => 'dead']) }}',
        };

        const url = new URL(baseRoutes[stageKey] || baseRoutes['total leads'], window.location.origin);
        appendSectionDateRangeToUrl(url, 'pipeline_filter');
        return url.toString();
    }

    function escapeJsString(value) {
        return String(value ?? '')
            .replace(/\\/g, '\\\\')
            .replace(/'/g, "\\'")
            .replace(/\n/g, '\\n')
            .replace(/\r/g, '\\r');
    }

    function renderTeamTargetsSummary(summary) {
        const container = document.getElementById('team-targets-summary');
        if (!container) {
            return;
        }

        const metrics = summary.metrics || {};
        const config = [
            ['Meetings', metrics.meetings || {}, '#1761a8'],
            ['Visits', metrics.visits || {}, '#5946c0'],
            ['Closers', metrics.closers || {}, '#9a6510'],
        ];

        container.innerHTML = config.map(([label, item, color]) => `
            <div>
                <div class="progress-item-head">
                    <strong>${label}</strong>
                    <span>${item.achieved || 0} / ${item.target || 0}</span>
                </div>
                <div class="progress-track">
                    <div class="progress-fill" style="width:${Math.min(100, Number(item.percentage || 0))}%;background:${color};"></div>
                </div>
            </div>
        `).join('');
    }

    function renderTargetsBreakdown(rows) {
        const container = document.getElementById('targets-breakdown-table');
        if (!container) {
            return;
        }

        if (!Array.isArray(rows) || rows.length === 0) {
            container.innerHTML = '<p style="color:#9ca3af;font-size:13px;">No target breakdown found.</p>';
            return;
        }

        container.innerHTML = `
            <table class="metric-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Meetings</th>
                        <th>Visits</th>
                        <th>Closers</th>
                    </tr>
                </thead>
                <tbody>
                    ${rows.map((row) => `
                        <tr>
                            <td><span class="table-avatar">${(row.user_name || 'U').charAt(0).toUpperCase()}</span>${row.user_name || 'Unknown'}</td>
                            <td>${row.role || 'Unknown'}</td>
                            <td>${row.meetings?.achieved || 0} / ${row.meetings?.target || 0}<br><small>${Number(row.meetings?.percentage || 0).toFixed(1)}%</small></td>
                            <td>${row.visits?.achieved || 0} / ${row.visits?.target || 0}<br><small>${Number(row.visits?.percentage || 0).toFixed(1)}%</small></td>
                            <td>${row.closers?.achieved || 0} / ${row.closers?.target || 0}<br><small>${Number(row.closers?.percentage || 0).toFixed(1)}%</small></td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    }

    function renderIncentiveSummary(summary) {
        const container = document.getElementById('incentive-summary');
        if (!container) {
            return;
        }

        const amount = Number(summary.total_amount || 0).toLocaleString('en-IN', { maximumFractionDigits: 0 });
        const items = [
            ['Total', summary.total || 0, `Rs ${amount} total value`],
            ['Verified', summary.verified || 0, 'Fully approved incentives'],
            ['Pending', summary.pending || 0, 'Awaiting approval'],
            ['Rejected', summary.rejected || 0, 'Rejected requests'],
        ];

        container.innerHTML = items.map(([label, value, note]) => `
            <div class="score-card">
                <div class="value">${value}</div>
                <div class="label">${label}</div>
                <div class="note">${note}</div>
            </div>
        `).join('');
    }

    function syncSalesScoreColumnCheckboxes() {
        document.querySelectorAll('[data-sales-score-column]').forEach((checkbox) => {
            checkbox.checked = draftSalesScoreColumns.includes(checkbox.dataset.salesScoreColumn);
        });
    }

    function toggleSalesScoreColumnPopover(event) {
        event?.stopPropagation();
        const popover = document.getElementById('sales-score-column-popover');
        const button = document.getElementById('sales-score-columns-btn');
        if (!popover || !button) return;

        if (popover.hidden) {
            draftSalesScoreColumns = [...salesScoreColumns];
            syncSalesScoreColumnCheckboxes();
            document.getElementById('sales-score-column-error').textContent = '';
            popover.hidden = false;
            button.setAttribute('aria-expanded', 'true');
            return;
        }

        closeSalesScoreColumnPopover();
    }

    function closeSalesScoreColumnPopover() {
        const popover = document.getElementById('sales-score-column-popover');
        const button = document.getElementById('sales-score-columns-btn');
        if (!popover || !button) return;

        draftSalesScoreColumns = [...salesScoreColumns];
        popover.hidden = true;
        button.setAttribute('aria-expanded', 'false');
    }

    function collectSalesScoreColumnDraft() {
        draftSalesScoreColumns = Array.from(document.querySelectorAll('[data-sales-score-column]:checked'))
            .map((checkbox) => checkbox.dataset.salesScoreColumn)
            .filter((column) => SALES_SCORE_OPTIONAL_COLUMNS.includes(column));
    }

    function setSalesScoreColumnDraft(mode) {
        draftSalesScoreColumns = mode === 'clear' ? [] : [...SALES_SCORE_DEFAULT_COLUMNS];
        syncSalesScoreColumnCheckboxes();
        document.getElementById('sales-score-column-error').textContent = mode === 'clear'
            ? 'Select at least one optional column before applying.'
            : '';
    }

    async function applySalesScoreColumns() {
        collectSalesScoreColumnDraft();
        const errorElement = document.getElementById('sales-score-column-error');
        const applyButton = document.getElementById('sales-score-column-apply');

        if (draftSalesScoreColumns.length === 0) {
            errorElement.textContent = 'Select at least one optional column.';
            return;
        }

        applyButton.disabled = true;
        errorElement.textContent = '';

        try {
            const response = await fetch(@json(route('admin.dashboard.score-columns.update')), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({ columns: draftSalesScoreColumns }),
            });
            const result = await response.json().catch(() => ({}));

            if (!response.ok || !result.success || !Array.isArray(result.columns)) {
                throw new Error(result.message || 'Unable to save column selection.');
            }

            salesScoreColumns = result.columns.filter((column) => SALES_SCORE_OPTIONAL_COLUMNS.includes(column));
            draftSalesScoreColumns = [...salesScoreColumns];
            renderSalesScoreTable(latestSalesScoreRows);
            closeSalesScoreColumnPopover();
        } catch (error) {
            errorElement.textContent = error.message || 'Unable to save column selection.';
        } finally {
            applyButton.disabled = false;
        }
    }

    document.addEventListener('click', (event) => {
        const picker = event.target.closest('.sales-score-column-picker');
        if (!picker) closeSalesScoreColumnPopover();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeSalesScoreColumnPopover();
    });

    function renderSalesScoreTable(rows) {
        const container = document.getElementById('sales-score-table');
        if (!container) {
            return;
        }

        latestSalesScoreRows = Array.isArray(rows) ? rows : [];

        if (latestSalesScoreRows.length === 0) {
            container.innerHTML = '<p style="color:#9ca3af;font-size:13px;">No score data available.</p>';
            return;
        }

        rows = latestSalesScoreRows;

        const hasColumn = (column) => salesScoreColumns.includes(column);
        const tableBaseWidth = hasColumn('role') ? 330 : 230;
        const tableMinWidth = Math.max(520, tableBaseWidth + (salesScoreColumns.length * 82));

        const avgMinutesForRow = (row) => {
            if (row.avg_response_minutes !== undefined && row.avg_response_minutes !== null) {
                return Number(row.avg_response_minutes || 0);
            }
            const byId = averageResponseTimeByUser[Number(row.user_id || 0)];
            if (byId !== undefined) return Number(byId || 0);
            const byName = averageResponseTimeByUser[String(row.user_name || '').trim().toLowerCase()];
            return byName !== undefined ? Number(byName || 0) : 0;
        };
        const avgForRow = (row) => formatAvgResponseTime(avgMinutesForRow(row));
        const noResponseClass = (count) => {
            const value = Number(count || 0);
            if (value >= 6) return 'danger';
            if (value >= 1) return 'warn';
            return 'none';
        };
        const noResponsePill = (row) => {
            const count = Number(row.no_response_count || 0);
            return `<span class="no-response-pill ${noResponseClass(count)}">${count}</span>`;
        };
        const oldestAssignedForRow = (row) => {
            if (!row.oldest_assigned_at) {
                return '<span class="sales-score-muted">--</span>';
            }
            return `<span title="${formatAssignedAtFull(row.oldest_assigned_at)}">${formatAssignedAt(row.oldest_assigned_at, dashboardMergedData.server_now || null)}</span>`;
        };
        const resetButtonForRow = (row) => {
            const userId = Number(row.user_id || 0);
            if (!userId || avgMinutesForRow(row) <= 0) {
                return '<span class="sales-score-muted">--</span>';
            }
            return `<button type="button" onclick="resetAverageResponseTime(${userId})" class="compact-reset-btn sales-score-reset-btn">Reset</button>`;
        };
        const breakdownForRow = (row) => {
            const assigned = Number(row.leads || 0);
            const noResponse = Number(row.no_response_count || 0);
            const meetings = Number(row.meetings || 0);
            const visits = Number(row.visits || 0);
            const junk = Number(row.junk || 0);
            const notInterested = Number(row.not_interested || 0);
            const otherBreakdown = row.other_breakdown || {};
            const taskActivity = Number(otherBreakdown.task_activity || 0);
            const callResponse = Number(otherBreakdown.call_response || 0);
            const followUp = Number(otherBreakdown.follow_up || 0);
            const progressed = Number(otherBreakdown.progressed || 0);
            const uncategorized = Number(otherBreakdown.uncategorized || 0);
            const bucketTotal = noResponse + meetings + visits + junk + notInterested + taskActivity + callResponse + followUp + progressed + uncategorized;
            const detailChips = [
                ['Task Activity', taskActivity, 'other'],
                ['Call Response', callResponse, 'other'],
                ['Follow Up', followUp, 'other'],
                ['Progressed', progressed, 'other'],
                ['Uncategorized', uncategorized, 'other'],
            ].filter(([, value]) => value > 0);

            return `
                <div class="sales-score-breakdown-card">
                    <div class="sales-score-breakdown-title">
                        <strong>${row.user_name || 'Unknown'}</strong>
                        <span>${assigned} assigned</span>
                    </div>
                    <div class="sales-score-breakdown-chips">
                        <span class="sales-score-breakdown-chip no-response">No Response <b>${noResponse}</b></span>
                        <span class="sales-score-breakdown-chip">Meetings <b>${meetings}</b></span>
                        <span class="sales-score-breakdown-chip visits">Visits <b>${visits}</b></span>
                        <span class="sales-score-breakdown-chip">Junk <b>${junk}</b></span>
                        <span class="sales-score-breakdown-chip">Not Interested <b>${notInterested}</b></span>
                        ${detailChips.length ? detailChips.map(([label, value, className]) => `<span class="sales-score-breakdown-chip ${className}">${label} <b>${value}</b></span>`).join('') : ''}
                    </div>
                    <div class="sales-score-breakdown-formula">${assigned} = ${noResponse} No Response + ${meetings} Meetings + ${visits} Visits + ${junk} Junk + ${notInterested} Not Interested + ${taskActivity + callResponse + followUp + progressed + uncategorized} activity/details${bucketTotal !== assigned ? ` (${bucketTotal} bucket total)` : ''}</div>
                </div>
            `;
        };

        container.innerHTML = `
            <div class="sales-score-mobile-grid">
                ${rows.map((row) => `
                    <div class="sales-score-mobile-card">
                        <div class="sales-score-mobile-head">
                            <span class="sales-score-mobile-avatar">${(row.user_name || 'U').charAt(0).toUpperCase()}</span>
                            <span class="sales-score-mobile-name">${row.user_name || 'Unknown'}</span>
                        </div>
                        <div class="sales-score-mobile-metrics">
                            <div class="sales-score-mobile-metric"><strong>${row.leads || 0}</strong><span>Assigned</span></div>
                            <div class="sales-score-mobile-metric"><strong>${noResponsePill(row)}</strong><span>No Response</span></div>
                            <div class="sales-score-mobile-metric"><strong>${row.meetings || 0}</strong><span>Meetings</span></div>
                            <div class="sales-score-mobile-metric"><strong>${row.visits || 0}</strong><span>Visits</span></div>
                            <div class="sales-score-mobile-metric"><strong>${row.closers || 0}</strong><span>Closers</span></div>
                            <div class="sales-score-mobile-metric"><strong>${row.other || 0}</strong><span>Other</span></div>
                            <div class="sales-score-mobile-metric"><strong style="color:#0b6b4f;">${Number(row.ps || 0).toFixed(1)}%</strong><span>PS</span></div>
                            <div class="sales-score-mobile-metric"><strong style="color:#1761a8;">${Number(row.pp || 0).toFixed(1)}%</strong><span>PP</span></div>
                            <div class="sales-score-mobile-metric"><strong style="color:#5946c0;">${Number(row.vp || 0).toFixed(1)}%</strong><span>VP</span></div>
                            <div class="sales-score-mobile-metric"><strong>${avgForRow(row)}</strong><span>Avg Response</span></div>
                        </div>
                        <div style="margin-top:8px;display:flex;align-items:center;justify-content:space-between;gap:8px;font-size:11px;color:#6b7280;">
                            <span>Oldest: ${oldestAssignedForRow(row)}</span>
                            ${resetButtonForRow(row)}
                        </div>
                    </div>
                `).join('')}
            </div>
            <div class="sales-score-excel-wrap">
                <table class="metric-table sales-score-excel-table" style="min-width:${tableMinWidth}px;">
                    <thead>
                        <tr>
                            <th class="sales-score-user-cell">User</th>
                            ${hasColumn('role') ? '<th>Role</th>' : ''}
                            ${hasColumn('leads') ? '<th class="sales-score-num">Assigned Leads</th>' : ''}
                            ${hasColumn('no_response') ? '<th class="sales-score-num">No Response</th>' : ''}
                            ${hasColumn('oldest_assigned') ? '<th>Oldest Assigned</th>' : ''}
                            ${hasColumn('meetings') ? '<th class="sales-score-num">Meetings</th>' : ''}
                            ${hasColumn('visits') ? '<th class="sales-score-num">Visits</th>' : ''}
                            ${hasColumn('closers') ? '<th class="sales-score-num">Closers</th>' : ''}
                            ${hasColumn('junk') ? '<th class="sales-score-num">Junk</th>' : ''}
                            ${hasColumn('not_interested') ? '<th class="sales-score-num">Not Interested</th>' : ''}
                            ${hasColumn('other') ? '<th class="sales-score-num">Other</th>' : ''}
                            ${hasColumn('ps') ? '<th class="sales-score-percent">PS %</th>' : ''}
                            ${hasColumn('pp') ? '<th class="sales-score-percent">PP %</th>' : ''}
                            ${hasColumn('vp') ? '<th class="sales-score-percent">VP %</th>' : ''}
                            ${hasColumn('avg_response') ? '<th class="sales-score-num">Avg Response</th>' : ''}
                            ${hasColumn('action') ? '<th class="sales-score-action">Action</th>' : ''}
                        </tr>
                    </thead>
                    <tbody>
                        ${rows.map((row) => `
                            <tr>
                                <td class="sales-score-user-cell"><span class="table-avatar">${(row.user_name || 'U').charAt(0).toUpperCase()}</span>${row.user_name || 'Unknown'}</td>
                                ${hasColumn('role') ? `<td>${row.role || 'Unknown'}</td>` : ''}
                                ${hasColumn('leads') ? `<td class="sales-score-num">${row.leads || 0}</td>` : ''}
                                ${hasColumn('no_response') ? `<td class="sales-score-num">${noResponsePill(row)}</td>` : ''}
                                ${hasColumn('oldest_assigned') ? `<td>${oldestAssignedForRow(row)}</td>` : ''}
                                ${hasColumn('meetings') ? `<td class="sales-score-num">${row.meetings || 0}</td>` : ''}
                                ${hasColumn('visits') ? `<td class="sales-score-num">${row.visits || 0}</td>` : ''}
                                ${hasColumn('closers') ? `<td class="sales-score-num">${row.closers || 0}</td>` : ''}
                                ${hasColumn('junk') ? `<td class="sales-score-num">${row.junk || 0}</td>` : ''}
                                ${hasColumn('not_interested') ? `<td class="sales-score-num">${row.not_interested || 0}</td>` : ''}
                                ${hasColumn('other') ? `<td class="sales-score-num">${row.other || 0}</td>` : ''}
                                ${hasColumn('ps') ? `<td class="sales-score-percent"><strong style="color:#0b6b4f;">${Number(row.ps || 0).toFixed(1)}%</strong></td>` : ''}
                                ${hasColumn('pp') ? `<td class="sales-score-percent"><strong style="color:#1761a8;">${Number(row.pp || 0).toFixed(1)}%</strong></td>` : ''}
                                ${hasColumn('vp') ? `<td class="sales-score-percent"><strong style="color:#5946c0;">${Number(row.vp || 0).toFixed(1)}%</strong></td>` : ''}
                                ${hasColumn('avg_response') ? `<td class="sales-score-num">${avgForRow(row)}</td>` : ''}
                                ${hasColumn('action') ? `<td class="sales-score-action">${resetButtonForRow(row)}</td>` : ''}
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    function renderSalesUserActivityTable(tableData) {
        const container = document.getElementById('sales-user-activity-table');
        if (!container) {
            return;
        }

        const rows = Array.isArray(tableData?.rows) ? tableData.rows : [];
        const totals = tableData?.totals || {};

        if (rows.length === 0) {
            container.innerHTML = '<p style="color:#9ca3af;font-size:13px;padding:14px;">No sales activity found for this date range.</p>';
            return;
        }

        const metric = (row, key) => Number(row?.[key] || 0).toLocaleString('en-IN');
        const userInitial = (name) => String(name || 'U').trim().charAt(0).toUpperCase() || 'U';

        container.innerHTML = `
            <table class="sales-user-activity-table">
                <thead>
                    <tr>
                        <th class="activity-user-cell">User Name</th>
                        <th class="activity-num">New Assigned</th>
                        <th class="activity-num">Fresh Transfer</th>
                        <th class="activity-num">Overdue</th>
                        <th class="activity-num">Scheduled Visits</th>
                        <th class="activity-num">Completed Visits</th>
                        <th class="activity-num">Verified Visits</th>
                    </tr>
                </thead>
                <tbody>
                    ${rows.map((row) => `
                        <tr>
                            <td class="activity-user-cell">
                                <span class="activity-user-name">
                                    <span class="table-avatar">${escapeHtml(userInitial(row.user_name))}</span>
                                    <span>
                                        ${escapeHtml(row.user_name || 'Unknown')}
                                        <span class="activity-role">${escapeHtml(row.role || 'Sales')}</span>
                                    </span>
                                </span>
                            </td>
                            <td class="activity-num">${metric(row, 'new_leads')}</td>
                            <td class="activity-num">${metric(row, 'fresh_transfer_leads')}</td>
                            <td class="activity-num">${metric(row, 'overdue')}</td>
                            <td class="activity-num">${metric(row, 'scheduled_visits')}</td>
                            <td class="activity-num">${metric(row, 'completed_visits')}</td>
                            <td class="activity-num">${metric(row, 'verified_visits')}</td>
                        </tr>
                    `).join('')}
                    <tr class="activity-total-row">
                        <td class="activity-user-cell">Total</td>
                        <td class="activity-num">${metric(totals, 'new_leads')}</td>
                        <td class="activity-num">${metric(totals, 'fresh_transfer_leads')}</td>
                        <td class="activity-num">${metric(totals, 'overdue')}</td>
                        <td class="activity-num">${metric(totals, 'scheduled_visits')}</td>
                        <td class="activity-num">${metric(totals, 'completed_visits')}</td>
                        <td class="activity-num">${metric(totals, 'verified_visits')}</td>
                    </tr>
                </tbody>
            </table>
        `;
    }

    function renderUserPipelineTable(rows) {
        const container = document.getElementById('user-pipeline-table');
        if (!container) {
            return;
        }

        if (!Array.isArray(rows) || rows.length === 0) {
            container.innerHTML = '<p style="color:#9ca3af;font-size:13px;">No user pipeline data available.</p>';
            return;
        }

        container.innerHTML = `
            <table class="metric-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Leads</th>
                        <th>Meetings</th>
                        <th>Visits</th>
                        <th>Closers</th>
                        <th>Avg Response</th>
                    </tr>
                </thead>
                <tbody>
                    ${rows.map((row) => `
                        <tr>
                            <td><span class="table-avatar">${(row.user_name || 'U').charAt(0).toUpperCase()}</span>${row.user_name || 'Unknown'}</td>
                            <td>${row.role || 'Unknown'}</td>
                            <td>${row.leads || 0}</td>
                            <td>${row.meetings || 0}</td>
                            <td>${row.visits || 0}</td>
                            <td>${row.closers || 0}</td>
                            <td>${Number(row.avg_response_minutes || 0).toFixed(1)} min</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    }

    function openTargetsModal() {
        const modal = document.getElementById('targets-breakdown-modal');
        if (modal) {
            modal.classList.add('is-open');
        }
    }

    function closeTargetsModal(event) {
        if (event && event.target && event.target !== event.currentTarget) {
            return;
        }
        const modal = document.getElementById('targets-breakdown-modal');
        if (modal) {
            modal.classList.remove('is-open');
        }
    }

    function renderLeadStatusChart(statusData) {
        const ctx = document.getElementById('leadStatusChart');
        if (leadStatusChart) {
            leadStatusChart.destroy();
        }

        if (!ctx) {
            leadStatusChart = null;
            return;
        }

        const labels = Object.keys(statusData);
        const values = Object.values(statusData);

        leadStatusChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels.map(label => label.replace(/_/g, ' ').toUpperCase()),
                datasets: [{
                    label: 'Leads by Status',
                    data: values,
                    backgroundColor: 'var(--primary-color)',
                    borderColor: 'var(--secondary-color)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    function renderTargetOverview(overview) {
        const section = document.getElementById('target-overview-section');
        const content = document.getElementById('target-overview-content');
        
        if (!overview || !overview.targets) {
            section.classList.add('hidden');
            return;
        }

        section.classList.remove('hidden');
        const ov = overview;
        content.innerHTML = `
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div class="p-4 bg-[#F7F6F3] rounded-lg">
                    <h4 class="font-semibold text-brand-primary mb-2">Total Prospects Extract</h4>
                    <div class="text-2xl font-bold text-gray-700">${ov.actuals?.prospects_extract || 0} / ${ov.targets?.prospects_extract || 0}</div>
                    <div class="text-sm text-gray-600">${Math.round(ov.percentages?.prospects_extract || 0)}% Complete</div>
                </div>
                <div class="p-4 bg-[#F7F6F3] rounded-lg">
                    <h4 class="font-semibold text-brand-primary mb-2">Total Prospects Verified</h4>
                    <div class="text-2xl font-bold text-gray-700">${ov.actuals?.prospects_verified || 0} / ${ov.targets?.prospects_verified || 0}</div>
                    <div class="text-sm text-gray-600">${Math.round(ov.percentages?.prospects_verified || 0)}% Complete</div>
                </div>
                <div class="p-4 bg-[#F7F6F3] rounded-lg">
                    <h4 class="font-semibold text-brand-primary mb-2">Total Calls</h4>
                    <div class="text-2xl font-bold text-gray-700">${ov.actuals?.calls || 0} / ${ov.targets?.calls || 0}</div>
                    <div class="text-sm text-gray-600">${Math.round(ov.percentages?.calls || 0)}% Complete</div>
                </div>
            </div>
            <p class="text-sm text-gray-600">Total Users: ${ov.total_users || 0} | Month: ${ov.month || 'N/A'}</p>
        `;
    }

    // Status mapping function for display
    function getStatusDisplay(status) {
        const statusMap = {
            // New statuses
            'new': { label: 'New', class: 'badge-new' },
            'connected': { label: 'Connected', class: 'badge-connected' },
            'verified_prospect': { label: 'Verified Prospect', class: 'badge-verified-prospect' },
            'meeting_scheduled': { label: 'Meeting Scheduled', class: 'badge-meeting-scheduled' },
            'meeting_completed': { label: 'Meeting Completed', class: 'badge-meeting-completed' },
            'visit_scheduled': { label: 'Visit Scheduled', class: 'badge-visit-scheduled' },
            'visit_done': { label: 'Visit Done', class: 'badge-visit-done' },
            'revisited_scheduled': { label: 'Revisit Scheduled', class: 'badge-revisited-scheduled' },
            'revisited_completed': { label: 'Revisit Completed', class: 'badge-revisited-completed' },
            'closed': { label: 'Closed', class: 'badge-closed' },
            'dead': { label: 'Dead', class: 'badge-dead' },
            'on_hold': { label: 'On Hold', class: 'badge-on-hold' },
            // Old statuses (for backward compatibility during migration)
            'contacted': { label: 'Contacted', class: 'badge-contacted' },
            'qualified': { label: 'Verified Prospect', class: 'badge-verified-prospect' },
            'site_visit_scheduled': { label: 'Visit Scheduled', class: 'badge-visit-scheduled' },
            'site_visit_completed': { label: 'Visit Done', class: 'badge-visit-done' },
            'closed_won': { label: 'Closed', class: 'badge-closed' },
            'closed_lost': { label: 'Dead', class: 'badge-dead' },
            'negotiation': { label: 'Negotiation', class: 'badge-negotiation' },
        };
        
        const statusInfo = statusMap[status] || { label: status || 'N/A', class: 'badge-default' };
        return statusInfo;
    }

    function renderRecentLeads(leads) {
        const container = document.getElementById('recent-leads');
        
        if (!leads || !Array.isArray(leads) || leads.length === 0) {
            container.innerHTML = '<p class="text-gray-600">No recent leads found</p>';
            return;
        }

        const tableHtml = `
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Source</th>
                        <th>Status</th>
                        <th>Created By</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>
                    ${leads.filter(lead => lead !== null && lead !== undefined).map(lead => {
                        const statusInfo = getStatusDisplay(lead?.status);
                        return `
                        <tr>
                            <td>${lead?.name || 'N/A'}</td>
                            <td>${lead?.phone || 'N/A'}</td>
                            <td>${lead?.source || 'N/A'}</td>
                            <td><span class="badge ${statusInfo.class}">${statusInfo.label}</span></td>
                            <td>${lead?.created_by || 'System'}</td>
                            <td>${lead?.created_at ? new Date(lead.created_at).toLocaleDateString() : 'N/A'}</td>
                        </tr>
                    `;
                    }).join('')}
                </tbody>
            </table>
        `;
        container.innerHTML = tableHtml;
    }

    function renderHrDashboard(data) {
        const summary = data.hr_summary || {};
        const setText = (id, value) => {
            const el = document.getElementById(id);
            if (el) el.textContent = value ?? 0;
        };

        setText('hr-present-today', summary.present_today || 0);
        setText('hr-absent-today', summary.absent_today || 0);
        setText('hr-late-today', summary.late_today || 0);
        setText('hr-leads', summary.hr_leads || 0);
        setText('hr-interviews-today', summary.interviews_today || 0);
        setText('hr-pending-leaves', summary.pending_leave_requests || 0);

        renderHrFunnel(data.hr_funnel || []);
        renderHrAttendance(data.hr_attendance || {});
        renderHrEmployeeStatus(data.hr_employee_status || {});
        renderHrTasks(data.hr_tasks || []);
        renderHrLeaveRequests(data.hr_leave_requests || {});
        renderHrAlerts(data.hr_alerts || []);
    }

    function renderHrFunnel(funnel) {
        const container = document.getElementById('hr-funnel');
        if (!container) return;

        if (!Array.isArray(funnel) || funnel.length === 0) {
            container.innerHTML = '<div class="finance-empty-state">No HR lead funnel data available.</div>';
            return;
        }

        const maxCount = Math.max(...funnel.map((row) => Number(row.count || 0)), 1);
        container.innerHTML = funnel.map((row) => {
            const count = Number(row.count || 0);
            const width = Math.max(4, Math.round((count / maxCount) * 100));
            return `
                <div class="hr-funnel-row">
                    <div class="hr-funnel-label">${escapeHtml(row.label || 'Unknown')}</div>
                    <div class="hr-funnel-track"><div class="hr-funnel-fill" style="width:${width}%;"></div></div>
                    <div class="hr-funnel-count">${count}</div>
                </div>
            `;
        }).join('');
    }

    function renderHrAttendance(attendance) {
        renderHrMiniGrid('hr-attendance-summary', [
            ['Present', attendance.present || 0],
            ['Absent', attendance.absent || 0],
            ['Late', attendance.late || 0],
            ['Half Day', attendance.half_day || 0],
            ['On Leave', attendance.on_leave || 0],
        ]);
    }

    function renderHrEmployeeStatus(status) {
        renderHrMiniGrid('hr-employee-status', [
            ['Active Employees', status.active || 0],
            ['On Notice', status.on_notice || 0],
            ['Resigned / Terminated', status.resigned_terminated || 0],
            ['New Joining This Month', status.new_joining_this_month || 0],
            ['Missing Documents', status.missing_documents || 0],
            ['Probation Ending Soon', status.probation_ending || 0],
        ]);
    }

    function renderHrLeaveRequests(leaves) {
        const container = document.getElementById('hr-leave-requests');
        if (!container) return;

        const rows = [
            ['Pending Leaves', leaves.pending || 0],
            ['Approved In Period', leaves.approved || 0],
            ['Rejected In Period', leaves.rejected || 0],
            ['On Leave Today', leaves.on_leave_today || 0],
        ];
        const pendingItems = Array.isArray(leaves.pending_items) ? leaves.pending_items : [];
        const statsHtml = rows.map(([label, count]) => `
            <div class="hr-mini-stat">
                <strong>${Number(count || 0)}</strong>
                <span>${escapeHtml(label)}</span>
            </div>
        `).join('');
        const requestsHtml = pendingItems.length
            ? `
                <div class="hr-leave-request-list">
                    ${pendingItems.map((item) => `
                        <div class="hr-leave-request-row">
                            <div>
                                <div class="hr-leave-request-name">${escapeHtml(item.user_name || 'Employee')}</div>
                                <div class="hr-leave-request-meta">${escapeHtml(item.leave_type || 'Leave')} · ${escapeHtml(item.date_label || '')} · ${escapeHtml(item.age || '')}</div>
                            </div>
                            <div class="hr-leave-request-days">${escapeHtml(item.days || '0')} day</div>
                        </div>
                    `).join('')}
                </div>
            `
            : '<div class="finance-empty-state" style="grid-column:1 / -1;">No pending leave requests.</div>';

        container.innerHTML = statsHtml + requestsHtml;
    }

    function renderHrMiniGrid(containerId, rows) {
        const container = document.getElementById(containerId);
        if (!container) return;

        container.innerHTML = rows.map(([label, count]) => `
            <div class="hr-mini-stat">
                <strong>${Number(count || 0)}</strong>
                <span>${escapeHtml(label)}</span>
            </div>
        `).join('');
    }

    function renderHrTasks(tasks) {
        const container = document.getElementById('hr-tasks');
        if (!container) return;

        container.innerHTML = Array.isArray(tasks) && tasks.length
            ? tasks.map((task) => `
                <div class="hr-list-row">
                    <strong>${escapeHtml(task.label || 'HR task')}</strong>
                    <span>${Number(task.count || 0)}</span>
                </div>
            `).join('')
            : '<div class="finance-empty-state">No HR tasks available.</div>';
    }

    function renderHrAlerts(alerts) {
        const container = document.getElementById('hr-alerts');
        if (!container) return;

        container.innerHTML = Array.isArray(alerts) && alerts.length
            ? alerts.map((alert) => `
                <div class="hr-list-row hr-alert-row">
                    <strong>${escapeHtml(alert.label || 'Alert')}</strong>
                    <span class="${escapeHtml(alert.tone || 'info')}">${Number(alert.count || 0)}</span>
                </div>
            `).join('')
            : '<div class="finance-empty-state">No important alerts available.</div>';
    }

    function renderAdminMetaDecisionAnalytics(analytics) {
        const totals = analytics?.totals || {};
        const rows = Array.isArray(analytics?.rows) ? analytics.rows : [];
        const campaigns = Array.isArray(analytics?.campaign_rows) ? analytics.campaign_rows : [];
        const kpis = document.getElementById('admin-meta-decision-kpis');
        const funnel = document.getElementById('admin-meta-funnel');
        const alerts = document.getElementById('admin-meta-alerts');
        const leads = Number(totals.leads || 0);
        const spend = Number(totals.spend || 0);
        const cpl = totals.cpl === null || totals.cpl === undefined ? null : Number(totals.cpl || 0);
        const closers = Number(totals.closers || 0);
        const costPerCloser = closers > 0 && spend > 0 ? spend / closers : null;
        const qualified = Number(totals.qualified || 0);
        const bad = Number(totals.bad || 0);
        const hold = Number(totals.hold || 0);
        const avgQuality = totals.average_quality === null || totals.average_quality === undefined ? null : Number(totals.average_quality || 0);
        const scoredLeads = Number(totals.scored_leads || 0);
        const spendSourceLabel = totals.spend_source_label || 'Synced Meta spend';
        const financeMetaSpend = Number(totals.finance_meta_spend || 0);
        const spendNote = financeMetaSpend > 0
            ? `${spendSourceLabel} · Finance ${formatFinanceCurrency(financeMetaSpend)}`
            : spendSourceLabel;

        if (kpis) {
            kpis.innerHTML = `
                <div class="meta-decision-card"><div class="value">${leads.toLocaleString('en-IN')}</div><div class="label">Meta Leads</div><div class="note">Lead Ads intake</div></div>
                <div class="meta-decision-card"><div class="value">${formatFinanceCurrency(spend)}</div><div class="label">Spend</div><div class="note">${escapeHtml(spendNote)}</div></div>
                <div class="meta-decision-card"><div class="value">${cpl === null ? '-' : formatFinanceCurrency(cpl)}</div><div class="label">CPL</div><div class="note">Spend / leads</div></div>
                <div class="meta-decision-card"><div class="value">${qualified.toLocaleString('en-IN')}</div><div class="label">Qualified</div><div class="note">Strong pipeline leads</div></div>
                <div class="meta-decision-card"><div class="value">${bad.toLocaleString('en-IN')}</div><div class="label">Bad / Junk</div><div class="note">${leads > 0 ? ((bad / leads) * 100).toFixed(1) : '0.0'}% of leads</div></div>
                <div class="meta-decision-card"><div class="value">${hold.toLocaleString('en-IN')}</div><div class="label">Hold / CNP</div><div class="note">${leads > 0 ? ((hold / leads) * 100).toFixed(1) : '0.0'}% hold</div></div>
                <div class="meta-decision-card"><div class="value">${closers.toLocaleString('en-IN')}</div><div class="label">Closers</div><div class="note">Closed Meta leads</div></div>
                <div class="meta-decision-card"><div class="value">${costPerCloser === null ? '-' : formatFinanceCurrency(costPerCloser)}</div><div class="label">Cost / Closer</div><div class="note">${avgQuality === null ? 'Quality pending' : `Avg ${avgQuality.toFixed(1)}/5`}</div></div>
            `;
        }

        if (funnel) {
            const funnelRows = [
                ['Received', leads],
                ['Scored', scoredLeads],
                ['Qualified', qualified],
                ['Hold / CNP', hold],
                ['Bad', bad],
                ['Closed', closers],
            ];
            const base = Math.max(leads, 1);
            funnel.innerHTML = funnelRows.map(([label, count]) => {
                const rate = Math.min(100, Math.max(0, (Number(count || 0) / base) * 100));
                return `
                    <div class="meta-funnel-card">
                        <strong>${Number(count || 0).toLocaleString('en-IN')}</strong>
                        <span>${escapeHtml(label)} · ${rate.toFixed(1)}%</span>
                        <div class="meta-funnel-bar"><i style="width:${rate}%"></i></div>
                    </div>
                `;
            }).join('');
        }

        if (funnel) {
            const totalLeads = Math.max(0, leads);
            const closedCount = Math.min(Math.max(0, closers), totalLeads);
            let remainingLeads = Math.max(0, totalLeads - closedCount);
            const qualifiedOpen = Math.min(Math.max(0, qualified - closedCount), remainingLeads);
            remainingLeads = Math.max(0, remainingLeads - qualifiedOpen);
            const holdCount = Math.min(Math.max(0, hold), remainingLeads);
            remainingLeads = Math.max(0, remainingLeads - holdCount);
            const badCount = Math.min(Math.max(0, bad), remainingLeads);
            remainingLeads = Math.max(0, remainingLeads - badCount);
            const pendingCount = remainingLeads;
            const scoreCoverage = totalLeads > 0 ? (scoredLeads / totalLeads) * 100 : 0;
            const mixRows = [
                { key: 'qualified', label: 'Qualified Open', count: qualifiedOpen },
                { key: 'closed', label: 'Closed', count: closedCount },
                { key: 'hold', label: 'Hold / CNP', count: holdCount },
                { key: 'bad', label: 'Bad / Junk', count: badCount },
                { key: 'pending', label: 'Pending / Other', count: pendingCount },
            ];
            const pct = (count) => totalLeads > 0 ? (Number(count || 0) / totalLeads) * 100 : 0;
            const segmentHtml = totalLeads > 0
                ? mixRows.map((row) => `<i class="${row.key}" style="width:${pct(row.count).toFixed(4)}%"></i>`).join('')
                : '<i class="empty"></i>';

            funnel.innerHTML = `
                <div class="meta-funnel-card">
                    <strong>${totalLeads.toLocaleString('en-IN')}</strong>
                    <span>Total Meta Leads</span>
                    <small>Outcome mix below is mutually exclusive, so all percentages add up to 100%. Scored is coverage only: ${Number(scoredLeads || 0).toLocaleString('en-IN')} leads (${scoreCoverage.toFixed(1)}%).</small>
                </div>
                <div class="meta-funnel-mix">
                    <div class="meta-funnel-mix-head">
                        <strong>Lead Outcome Mix</strong>
                        <span>Total share: ${totalLeads > 0 ? '100.0' : '0.0'}%</span>
                    </div>
                    <div class="meta-funnel-stack">${segmentHtml}</div>
                    <table class="meta-funnel-table">
                        <thead>
                            <tr>
                                <th>Bucket</th>
                                <th>Leads</th>
                                <th>Share</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${mixRows.map((row) => `
                                <tr>
                                    <td><span class="meta-funnel-dot ${row.key}"></span>${escapeHtml(row.label)}</td>
                                    <td><span class="meta-funnel-count ${row.key}">${Number(row.count || 0).toLocaleString('en-IN')}</span></td>
                                    <td>${pct(row.count).toFixed(1)}%</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        }

        if (alerts) {
            const alertRows = [];
            if (spend > 0 && leads === 0) {
                alertRows.push(['danger', 'Spend without CRM leads', 'Meta spend synced hai, lekin CRM leads match nahi ho rahe.']);
            }
            rows.forEach((row) => {
                const rowLeads = Number(row.leads || 0);
                const rowSpend = Number(row.spend || 0);
                const rowHold = Number(row.hold || 0);
                const rowBadRate = Number(row.bad_lead_rate || 0);
                const rowAvg = row.average_quality === null || row.average_quality === undefined ? null : Number(row.average_quality || 0);
                const rowScored = Number(row.scored_leads || 0);
                const name = row.form_name || row.source || 'Meta form';
                if (rowLeads >= 5 && rowScored === 0) {
                    alertRows.push(['warning', 'Quality missing', `${name} me leads hain but lead quality scoring missing hai.`]);
                }
                if (rowLeads > 0 && (rowHold / rowLeads) * 100 >= 30) {
                    alertRows.push(['warning', 'High Hold/CNP', `${name} ka Hold/CNP ${((rowHold / rowLeads) * 100).toFixed(1)}% hai.`]);
                }
                if (rowBadRate >= 30) {
                    alertRows.push(['danger', 'High bad leads', `${name} me bad lead rate ${rowBadRate.toFixed(1)}% hai.`]);
                }
                if (rowSpend > 0 && rowAvg !== null && rowAvg < 2.5) {
                    alertRows.push(['warning', 'Paid low quality', `${name} spend le raha hai but avg quality ${rowAvg.toFixed(1)}/5 hai.`]);
                }
            });
            campaigns.forEach((row) => {
                const campaignSpend = Number(row.spend || 0);
                const campaignLeads = Number(row.leads || 0);
                if (campaignSpend >= 1000 && campaignLeads <= 2) {
                    alertRows.push(['danger', 'High spend low leads', `${row.campaign_name || 'Campaign'} me spend high hai, leads low hain.`]);
                }
            });

            alerts.innerHTML = alertRows.length
                ? alertRows.slice(0, 6).map(([tone, title, body]) => `
                    <div class="meta-alert-row ${tone}">
                        <strong>${escapeHtml(title)}</strong>
                        <span>${escapeHtml(body)}</span>
                    </div>
                `).join('')
                : '<div class="meta-alert-row"><strong>No urgent Meta issues</strong><span>Current range me high CPL, Hold/CNP ya missing quality alert nahi mila.</span></div>';
        }
    }

    function renderMarketingSummary(summary, callStatistics, recentLeads) {
        const leadQuality = summary.lead_quality || {};
        const importSummary = summary.import_summary || {};
        const sourceDistribution = Array.isArray(summary.source_distribution) ? summary.source_distribution : [];
        const leadInflow = Array.isArray(summary.lead_inflow) ? summary.lead_inflow : [];
        const outcomeDistribution = callStatistics.outcome_distribution || {};

        const setText = (id, value) => {
            const el = document.getElementById(id);
            if (el) el.textContent = value;
        };

        setText('marketing-total-batches', importSummary.total_batches || 0);
        setText('marketing-completed-batches', `${importSummary.completed_batches || 0} completed batches`);
        setText('marketing-imported-leads', importSummary.imported_leads || 0);
        setText('marketing-pending-batches', `${(importSummary.pending_batches || 0)} pending or processing`);
        setText('marketing-junk-leads', leadQuality.junk || 0);
        setText('marketing-not-interested', leadQuality.not_interested || 0);

        renderMarketingSourceChart(sourceDistribution);
        renderMarketingInflowChart(leadInflow);

        const sourceList = document.getElementById('marketing-source-list');
        if (sourceList) {
            sourceList.innerHTML = sourceDistribution.length
                ? sourceDistribution.map((item) => `
                    <div class="marketing-list-item">
                        <strong>${item.source || 'Unknown'}</strong>
                        <span>${item.value || 0} leads</span>
                    </div>
                `).join('')
                : '<div class="marketing-list-item"><strong>No data</strong><span>Source distribution will appear here.</span></div>';
        }

        const importBox = document.getElementById('marketing-import-summary');
        if (importBox) {
            importBox.innerHTML = `
                <div class="marketing-score-box">
                    <div class="score">${importSummary.completed_batches || 0}</div>
                    <div class="name">Completed Imports</div>
                    <div class="note">${importSummary.failed_batches || 0} failed batches in this window.</div>
                </div>
                <div class="marketing-score-box">
                    <div class="score">${importSummary.pending_batches || 0}</div>
                    <div class="name">Pending Imports</div>
                    <div class="note">${importSummary.imported_leads || 0} leads imported from all completed runs.</div>
                </div>
            `;
        }

        const qualityGrid = document.getElementById('marketing-quality-grid');
        if (qualityGrid) {
            const items = [
                ['Connected', leadQuality.connected || 0, 'Leads that moved past first contact.'],
                ['Verified Prospect', leadQuality.verified_prospect || 0, 'Leads validated as stronger pipeline candidates.'],
                ['Junk', leadQuality.junk || 0, 'Leads filtered out as invalid or unusable.'],
                ['Not Interested', leadQuality.not_interested || 0, 'Leads parked for later recycle or reassignment.'],
            ];
            qualityGrid.innerHTML = items.map(([name, score, note]) => `
                <div class="marketing-score-box">
                    <div class="score">${score}</div>
                    <div class="name">${name}</div>
                    <div class="note">${note}</div>
                </div>
            `).join('');
        }

        const outcomes = document.getElementById('marketing-call-outcomes');
        if (outcomes) {
            const entries = Object.entries(outcomeDistribution);
            outcomes.innerHTML = entries.length
                ? entries.slice(0, 6).map(([name, value]) => `
                    <div class="marketing-list-item">
                        <strong>${String(name).replace(/_/g, ' ')}</strong>
                        <span>${value || 0} calls</span>
                    </div>
                `).join('')
                : '<div class="marketing-list-item"><strong>No outcomes</strong><span>Call outcome mix will appear here.</span></div>';
        }

        const recentLeadsList = document.getElementById('marketing-recent-leads');
        if (recentLeadsList) {
            recentLeadsList.innerHTML = recentLeads.length
                ? recentLeads.slice(0, 6).map((lead) => `
                    <div class="marketing-list-item">
                        <strong>${lead?.name || 'N/A'}</strong>
                        <span>${lead?.phone || 'N/A'} · ${lead?.created_by || 'System'}</span>
                    </div>
                `).join('')
                : '<div class="marketing-list-item"><strong>No recent leads</strong><span>Recent additions will appear here.</span></div>';
        }
    }

    function renderAdSpendSummary(summary) {
        const platforms = Array.isArray(summary.platforms) ? summary.platforms : [];
        const latestEntries = Array.isArray(summary.latest_entries) ? summary.latest_entries : [];
        const platformAmount = (key) => {
            const row = platforms.find((item) => item.key === key);
            return row ? Number(row.amount || 0) : 0;
        };
        const setText = (id, value) => {
            const el = document.getElementById(id);
            if (el) {
                el.textContent = value;
            }
        };

        setText('ad-spend-total', formatFinanceCurrency(summary.total_amount || 0));
        setText('ad-spend-meta', formatFinanceCurrency(platformAmount('meta')));
        setText('ad-spend-google', formatFinanceCurrency(platformAmount('google')));
        setText('ad-spend-portal', formatFinanceCurrency(platformAmount('portal')));
        setText('ad-spend-other', formatFinanceCurrency(platformAmount('other')));
        setText('ad-spend-pending', formatFinanceCurrency(summary.pending_amount || 0));

        const hasSpend = Number(summary.total_amount || 0) > 0 || latestEntries.length > 0;
        const emptyEl = document.getElementById('ad-spend-empty');
        const contentEl = document.getElementById('ad-spend-content');
        if (emptyEl) {
            emptyEl.style.display = hasSpend ? 'none' : 'block';
        }
        if (contentEl) {
            contentEl.style.display = hasSpend ? 'grid' : 'none';
        }

        const platformContainer = document.getElementById('ad-spend-platform-list');
        if (platformContainer) {
            platformContainer.innerHTML = platforms.length
                ? platforms.map((row) => {
                    const leads = Number(row.lead_count || 0);
                    const cpl = leads > 0 && row.cost_per_lead !== null ? formatFinanceCurrency(row.cost_per_lead) : '-';
                    return `
                        <div class="ad-spend-platform-row">
                            <strong>${escapeHtml(row.label || 'Other')}</strong>
                            <span>${formatFinanceCurrency(row.amount || 0)}</span>
                            <span>${leads.toLocaleString('en-IN')} leads</span>
                            <span>${cpl}</span>
                        </div>
                    `;
                }).join('')
                : '<div class="finance-empty-state">No platform spend available.</div>';
        }

        const latestContainer = document.getElementById('ad-spend-latest-table');
        if (latestContainer) {
            latestContainer.innerHTML = latestEntries.length
                ? `
                    <table class="finance-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Platform</th>
                                <th>Category / Subcategory</th>
                                <th class="finance-table-amount">Amount</th>
                                <th>Status</th>
                                <th>Added By</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${latestEntries.slice(0, 8).map((entry) => {
                                const statusClass = ['draft', 'approved', 'rejected'].includes(entry.status) ? entry.status : 'draft';
                                const category = `${entry.category_name || 'Uncategorized'} / ${entry.subcategory_name || '-'}`;
                                return `
                                    <tr>
                                        <td>${escapeHtml(entry.date || '-')}</td>
                                        <td>${escapeHtml(entry.platform_label || 'Other')}</td>
                                        <td>${escapeHtml(category)}</td>
                                        <td class="finance-table-amount">${formatFinanceCurrency(entry.amount || 0)}</td>
                                        <td><span class="finance-status-pill ${statusClass}">${escapeHtml(entry.status_label || entry.status || 'Draft')}</span></td>
                                        <td>${escapeHtml(entry.creator_name || 'Finance user')}</td>
                                    </tr>
                                `;
                            }).join('')}
                        </tbody>
                    </table>
                `
                : '<div class="finance-empty-state" style="padding:16px;">No ad spend found for this period. Add Marketing/Ads expense from Finance to see spend here.</div>';
        }
    }

    function formatFinanceCurrency(value) {
        return `Rs ${Number(value || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    }

    function formatFinanceMonthLabel(year, month) {
        const monthNumber = Number(month || 0);
        const yearNumber = Number(year || 0);
        if (!monthNumber || !yearNumber) {
            return 'Current Month';
        }

        const date = new Date(yearNumber, monthNumber - 1, 1);
        return date.toLocaleDateString('en-IN', { month: 'long', year: 'numeric' });
    }

    function renderFinanceDashboard(financeSummary) {
        const summary = financeSummary.summary || {};
        const statusTotals = financeSummary.status_totals || {};
        const latestEntries = Array.isArray(financeSummary.latest_entries) ? financeSummary.latest_entries : [];
        const categoryTotals = Array.isArray(financeSummary.category_totals) ? financeSummary.category_totals : [];
        const highValuePending = financeSummary.high_value_pending || {};
        const oldestPending = financeSummary.oldest_pending || null;
        const monthComparison = financeSummary.month_comparison || {};
        const year = financeSummary.year || new Date().getFullYear();
        const month = financeSummary.month || (new Date().getMonth() + 1);
        const rangeLabel = financeSummary.range_label || formatFinanceMonthLabel(year, month);
        const reportYear = financeSummary.report_year || new Date().getFullYear();
        const reportMonth = financeSummary.report_month || (new Date().getMonth() + 1);
        const entryCount = Number(summary.entry_count || 0);
        const pendingCount = Number(financeSummary.pending_count || summary.pending_count || 0);
        const pendingAmount = Number(financeSummary.pending_amount || summary.pending_amount || 0);

        const setText = (id, value) => {
            const el = document.getElementById(id);
            if (el) {
                el.textContent = value;
            }
        };

        setText('finance-total-label', financeSummary.total_label || 'Month Total');
        setText('finance-month-total', formatFinanceCurrency(summary.total_amount || 0));
        setText('finance-pending-queue', pendingCount.toLocaleString('en-IN'));
        setText('finance-approved-amount', formatFinanceCurrency(financeSummary.approved_amount || summary.approved_amount || 0));
        setText('finance-rejected-amount', formatFinanceCurrency(financeSummary.rejected_amount || summary.rejected_amount || 0));
        setText('finance-average-ticket', formatFinanceCurrency(summary.average_amount || 0));
        setText('finance-month-note', `${rangeLabel} spend overview`);
        setText('finance-entry-note', `${entryCount.toLocaleString('en-IN')} entries in ${rangeLabel}`);
        setText('finance-pending-note', `${formatFinanceCurrency(pendingAmount)} awaiting approval`);
        setText('finance-approved-note', `${rangeLabel}`);
        setText('finance-rejected-note', `${rangeLabel}`);
        setText('finance-pending-count-large', pendingCount.toLocaleString('en-IN'));
        setText('finance-pending-amount-note', `${formatFinanceCurrency(pendingAmount)} pending approval`);
        setText(
            'finance-high-value-pending',
            `${Number(highValuePending.count || 0).toLocaleString('en-IN')} / ${formatFinanceCurrency(highValuePending.amount || 0)}`
        );
        setText(
            'finance-oldest-pending',
            oldestPending
                ? `${oldestPending.expense_date || 'No date'} - ${formatFinanceCurrency(oldestPending.amount || 0)}`
                : 'None'
        );

        const reportLink = document.getElementById('finance-monthly-report-link');
        if (reportLink) {
            reportLink.href = `{{ route('admin.expenses.monthly-report') }}?year=${reportYear}&month=${reportMonth}`;
        }

        renderFinanceMonthComparison(monthComparison);

        const statusContainer = document.getElementById('finance-status-summary');
        if (statusContainer) {
            const rows = Object.values(statusTotals);
            statusContainer.innerHTML = rows.length
                ? rows.map((row) => `
                    <div class="finance-status-row">
                        <span>${escapeHtml(row.label || 'Unknown')}</span>
                        <strong>${Number(row.entry_count || 0).toLocaleString('en-IN')} / ${formatFinanceCurrency(row.total_amount || 0)}</strong>
                    </div>
                `).join('')
                : '<div class="finance-empty-state">No status totals available.</div>';
        }

        renderFinanceCategorySpend(categoryTotals);

        const recentContainer = document.getElementById('finance-recent-entries');
        if (recentContainer) {
            recentContainer.innerHTML = latestEntries.length
                ? `
                    <table class="finance-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Created By</th>
                                <th>Category</th>
                                <th>Subcategory</th>
                                <th class="finance-table-amount">Amount</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${latestEntries.slice(0, 8).map((entry) => {
                                const statusClass = ['draft', 'approved', 'rejected'].includes(entry.status) ? entry.status : 'draft';
                                return `
                                    <tr>
                                        <td>${escapeHtml(entry.expense_date || '-')}</td>
                                        <td>${escapeHtml(entry.creator_name || 'System')}</td>
                                        <td>${escapeHtml(entry.category_name || 'Uncategorized')}</td>
                                        <td>${escapeHtml(entry.subcategory_name || '-')}</td>
                                        <td class="finance-table-amount">${formatFinanceCurrency(entry.amount || 0)}</td>
                                        <td><span class="finance-status-pill ${statusClass}">${escapeHtml(entry.status_label || entry.status || 'Draft')}</span></td>
                                        <td><a class="finance-table-action" href="${escapeHtml(entry.view_url || '#')}">Open</a></td>
                                    </tr>
                                `;
                            }).join('')}
                        </tbody>
                    </table>
                `
                : '<div class="finance-empty-state" style="padding:16px;">No recent finance entries in this period.</div>';
        }
    }

    function renderFinanceCategorySpend(categoryTotals) {
        const container = document.getElementById('finance-category-spend');
        if (!container) return;

        const total = categoryTotals.reduce((sum, row) => sum + Number(row.total_amount || 0), 0);
        if (!categoryTotals.length || total <= 0) {
            container.innerHTML = '<div class="finance-empty-state">No category spend available.</div>';
            return;
        }

        container.innerHTML = categoryTotals.slice(0, 6).map((row) => {
            const amount = Number(row.total_amount || 0);
            const percent = total > 0 ? Math.round((amount / total) * 100) : 0;
            return `
                <div class="finance-category-row">
                    <div class="finance-category-head">
                        <strong>${escapeHtml(row.category_name || 'Uncategorized')}</strong>
                        <span>${formatFinanceCurrency(amount)} · ${percent}%</span>
                    </div>
                    <div class="finance-category-track"><div class="finance-category-fill" style="width:${Math.max(percent, 4)}%;"></div></div>
                </div>
            `;
        }).join('');
    }

    function renderFinanceMonthComparison(comparison) {
        const topValue = document.getElementById('finance-month-comparison');
        const topNote = document.getElementById('finance-month-comparison-note');
        const cardValue = document.getElementById('finance-comparison-value');
        const cardNote = document.getElementById('finance-comparison-note');
        const direction = comparison.direction || 'flat';
        const percent = comparison.percentage_change;
        const currentLabel = comparison.current_month_label || 'This month';
        const previousLabel = comparison.previous_month_label || 'Last month';
        const difference = Number(comparison.difference_amount || 0);

        const percentLabel = percent === null || percent === undefined
            ? 'No baseline'
            : `${difference > 0 ? '+' : ''}${Number(percent).toFixed(1)}%`;
        const note = percent === null || percent === undefined
            ? `${previousLabel} has no spend baseline. ${currentLabel}: ${formatFinanceCurrency(comparison.current_amount || 0)}.`
            : `${currentLabel} is ${formatFinanceCurrency(Math.abs(difference))} ${direction === 'up' ? 'higher' : direction === 'down' ? 'lower' : 'flat'} than ${previousLabel}.`;

        if (topValue) topValue.textContent = percentLabel;
        if (topNote) topNote.textContent = note;
        if (cardValue) {
            cardValue.textContent = `${difference > 0 ? '+' : difference < 0 ? '-' : ''}${formatFinanceCurrency(Math.abs(difference))}`;
            cardValue.classList.remove('up', 'down');
            if (direction === 'up') cardValue.classList.add('up');
            if (direction === 'down') cardValue.classList.add('down');
        }
        if (cardNote) cardNote.textContent = note;
    }

    function renderAdminLeadSources(summary, analytics) {
        const analyticsRows = Array.isArray(analytics.rows) ? analytics.rows : [];
        const totals = analytics.totals || {};
        const sourceDistribution = analyticsRows.length
            ? analyticsRows.map((row) => ({ source: row.form_name || row.source, value: row.leads }))
            : (Array.isArray(summary.source_distribution) ? summary.source_distribution : []);
        const totalLeads = sourceDistribution.reduce((sum, item) => sum + Number(item.value || 0), 0);
        const highlights = document.getElementById('admin-source-highlights');
        const performanceBody = document.getElementById('admin-source-performance-body');
        const campaignBody = document.getElementById('admin-meta-campaign-performance-body');
        const topPerformanceBody = document.getElementById('admin-meta-top-form-performance-body');
        const topCampaignBody = document.getElementById('admin-meta-top-campaign-performance-body');
        const ctx = document.getElementById('adminLeadSourceChart');
        const qualityValue = totals.average_quality === null || totals.average_quality === undefined
            ? '-'
            : `${Number(totals.average_quality || 0).toFixed(1)}/5`;
        const totalCpl = totals.cpl === null || totals.cpl === undefined
            ? '-'
            : formatFinanceCurrency(totals.cpl);

        if (highlights) {
            highlights.innerHTML = `
                <div class="source-performance-kpi"><div class="value">${Number(totals.sources || sourceDistribution.length || 0).toLocaleString()}</div><div class="label">Meta Forms</div></div>
                <div class="source-performance-kpi"><div class="value">${Number(totals.leads || totalLeads || 0).toLocaleString()}</div><div class="label">Total Leads</div></div>
                <div class="source-performance-kpi"><div class="value">${qualityValue}</div><div class="label">${escapeHtml(totals.quality_label || 'Avg Quality')}</div></div>
                <div class="source-performance-kpi"><div class="value">${Number(totals.closers || 0).toLocaleString()}</div><div class="label">Closers</div></div>
                <div class="source-performance-kpi"><div class="value">${formatFinanceCurrency(totals.spend || 0)}</div><div class="label">Meta Spend</div></div>
                <div class="source-performance-kpi"><div class="value">${totalCpl}</div><div class="label">CPL</div></div>
            `;
        }

        if (performanceBody) {
            performanceBody.innerHTML = analyticsRows.length
                ? analyticsRows.map((row) => {
                    const qualityLabel = row.quality_label || 'Needs More Data';
                    const qualityClass = String(qualityLabel).toLowerCase().replace(/\s+/g, '-');
                    const averageQuality = row.average_quality === null || row.average_quality === undefined
                        ? '<span class="source-quality-score muted">No score</span>'
                        : `<span class="source-quality-score">${Number(row.average_quality || 0).toFixed(1)}/5</span>`;
                    const spendSourceNote = row.spend_source === 'finance_expense_fallback' ? 'Finance expense share' : 'Meta API spend';
                    const spend = Number(row.spend || 0) > 0
                        ? `<span class="source-performance-money">${formatFinanceCurrency(row.spend)}</span><div class="source-performance-muted">${spendSourceNote}</div>`
                        : '<span class="source-performance-money muted">-</span><div class="source-performance-muted">No spend linked</div>';
                    const cpl = row.cpl === null || row.cpl === undefined
                        ? '<span class="source-performance-money muted">-</span>'
                        : `<span class="source-performance-money">${formatFinanceCurrency(row.cpl)}</span>`;
                    const costSummary = `
                        <div class="source-metric-stack">
                            <div>${spend}</div>
                            <div class="source-metric-note">CPL ${cpl}</div>
                        </div>
                    `;
                    const qualitySummary = `
                        <div class="source-quality-summary">
                            <div class="source-quality-cell">
                                ${averageQuality}
                                <span class="source-performance-muted">${Number(row.scored_leads || 0).toLocaleString()} scored of ${Number(row.leads || 0).toLocaleString()} leads</span>
                            </div>
                        </div>
                    `;
                    const outcomeInterested = Number(row.interested || row.qualified || 0);
                    const outcomeNot = Number(row.not_interested || 0);
                    const outcomeFollowUp = Number(row.follow_up || 0);
                    const outcomeVisits = Number(row.visits || 0);
                    const outcomeCnp = Number(row.hold || 0);
                    const outcomeClosers = Number(row.closers || 0);
                    const outcomePending = Math.max(0, Number(row.leads || 0) - outcomeInterested - outcomeNot - outcomeFollowUp - outcomeVisits - outcomeCnp - outcomeClosers);
                    const outcomeLink = (outcome) => buildMetaOutcomeLeadUrl(row, outcome);
                    const outcomeBadge = (outcome, count, className, label) => `
                        <a class="source-outcome-count ${className}" href="${outcomeLink(outcome)}" title="Open ${escapeHtml(label)} leads in All Leads">${Number(count || 0).toLocaleString()}</a>
                    `;
                    return `
                        <tr>
                            <td class="source-performance-sticky">
                                <span class="source-performance-source">${escapeHtml(row.form_name || row.source || 'Unknown')}</span>
                                <div class="source-performance-sub">Meta Lead Ads</div>
                            </td>
                            <td>${Number(row.leads || 0).toLocaleString()}</td>
                            <td>${costSummary}</td>
                            <td>${qualitySummary}</td>
                            <td>${outcomeBadge('interested', outcomeInterested, 'good', 'Interested')}</td>
                            <td>${outcomeBadge('follow_up', outcomeFollowUp, 'info', 'Follow-up')}</td>
                            <td>${outcomeBadge('visit', outcomeVisits, 'good', 'Visit')}</td>
                            <td>${outcomeBadge('not_interested', outcomeNot, 'bad', 'Not Interested')}</td>
                            <td>${outcomeBadge('cnp', outcomeCnp, 'warn', 'CNP')}</td>
                            <td>${outcomeBadge('pending', outcomePending, 'neutral', 'Pending')}</td>
                            <td>${outcomeBadge('closer', outcomeClosers, 'good', 'Closer')}</td>
                            <td><span class="source-quality-verdict ${qualityClass}">${escapeHtml(qualityLabel)}</span></td>
                        </tr>
                    `;
                }).join('')
                : '<tr><td colspan="12" style="text-align:center;color:#9ca3af;padding:20px;">Meta form quality will appear here.</td></tr>';
        }
        if (topPerformanceBody && performanceBody) {
            topPerformanceBody.innerHTML = performanceBody.innerHTML;
        }

        if (campaignBody) {
            const campaignRows = Array.isArray(analytics.campaign_rows) ? analytics.campaign_rows : [];
            campaignBody.innerHTML = campaignRows.length
                ? campaignRows.map((row) => {
                    const cpl = row.cpl === null || row.cpl === undefined
                        ? '<span class="source-performance-money muted">-</span>'
                        : `<span class="source-performance-money">${formatFinanceCurrency(row.cpl)}</span>`;
                    const avgQuality = row.average_quality === null || row.average_quality === undefined
                        ? '<span class="source-quality-score muted">No score</span>'
                        : `<span class="source-quality-score">${Number(row.average_quality || 0).toFixed(1)}/5</span>`;

                    return `
                        <tr>
                            <td>
                                <span class="source-performance-source">${escapeHtml(row.campaign_name || 'Unknown Campaign')}</span>
                                <div class="source-performance-sub">${escapeHtml(row.adset_name || 'Unknown Adset')} / ${escapeHtml(row.ad_name || 'Unknown Ad')}</div>
                            </td>
                            <td style="text-align:center;">${Number(row.leads || 0).toLocaleString()}</td>
                            <td style="text-align:center;"><span class="source-performance-money">${formatFinanceCurrency(row.spend || 0)}</span></td>
                            <td style="text-align:center;">${cpl}</td>
                            <td style="text-align:center;">${avgQuality}</td>
                            <td style="text-align:center;"><span class="source-score-mix" style="min-width:80px;grid-template-columns:1fr;"><span class="hold"><b>Hold</b>${Number(row.hold || 0).toLocaleString()}</span></span></td>
                            <td style="text-align:center;">${Number(row.closers || 0).toLocaleString()}</td>
                        </tr>
                    `;
                }).join('')
                : '<tr><td colspan="7" style="text-align:center;color:#9ca3af;padding:20px;">Meta campaign CPL will appear after ad attribution and spend sync.</td></tr>';
        }
        if (topCampaignBody && campaignBody) {
            topCampaignBody.innerHTML = campaignBody.innerHTML;
        }

        if (!ctx) {
            return;
        }

        if (adminLeadSourceChart) {
            adminLeadSourceChart.destroy();
        }

        adminLeadSourceChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: sourceDistribution.map((item) => item.source || 'Unknown'),
                datasets: [{
                    data: sourceDistribution.map((item) => item.value || 0),
                    backgroundColor: ['#0b6b4f', '#3f7cff', '#f59e0b', '#7a67de', '#ef4444', '#14b8a6', '#f97316', '#64748b'],
                    borderWidth: 0,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }

    function renderMarketingSourceChart(items) {
        const ctx = document.getElementById('marketingSourceChart');
        if (!ctx) {
            return;
        }

        if (marketingSourceChart) {
            marketingSourceChart.destroy();
        }

        marketingSourceChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: items.map((item) => item.source || 'Unknown'),
                datasets: [{
                    data: items.map((item) => item.value || 0),
                    backgroundColor: ['#0b6b4f', '#3f7cff', '#f59e0b', '#7a67de', '#ef4444', '#14b8a6'],
                    borderWidth: 0,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }

    function renderMarketingInflowChart(items) {
        const ctx = document.getElementById('marketingInflowChart');
        if (!ctx) {
            return;
        }

        if (marketingInflowChart) {
            marketingInflowChart.destroy();
        }

        marketingInflowChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: items.map((item) => item.label || ''),
                datasets: [{
                    label: 'Lead Inflow',
                    data: items.map((item) => item.value || 0),
                    borderColor: '#5946c0',
                    backgroundColor: 'rgba(89, 70, 192, 0.12)',
                    tension: 0.35,
                    fill: true,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    function renderRecentActivities(activities) {
        const container = document.getElementById('recent-activities');
        
        if (!activities || !Array.isArray(activities) || activities.length === 0) {
            container.innerHTML = '<p class="text-gray-600">No recent activities found</p>';
            return;
        }

        const tableHtml = `
            <table>
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Action</th>
                        <th>Description</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    ${activities.filter(activity => activity !== null && activity !== undefined).map(activity => `
                        <tr>
                            <td>${activity?.user_name || 'System'}</td>
                            <td><span class="badge">${activity?.action || 'N/A'}</span></td>
                            <td>${activity?.description || 'N/A'}</td>
                            <td>${activity?.created_at ? new Date(activity.created_at).toLocaleString() : 'N/A'}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
        container.innerHTML = tableHtml;
    }

    function renderAgentsVisitsVsMeetings(agentsData) {
        const chartContainer = document.getElementById('agentsVisitsMeetingsChart');
        const tableContainer = document.getElementById('agents-visits-meetings-table');
        
        if (!agentsData || !Array.isArray(agentsData) || agentsData.length === 0) {
            if (tableContainer) {
                tableContainer.innerHTML = '<p class="text-[#B3B5B4]">No agent data available</p>';
            }
            return;
        }

        // Destroy existing chart if it exists
        if (agentsVisitsMeetingsChart) {
            agentsVisitsMeetingsChart.destroy();
        }

        // Prepare data for chart
        const labels = agentsData.map(agent => agent.agent_name || 'Unknown');
        const meetingsData = agentsData.map(agent => agent.meetings || 0);
        const visitsData = agentsData.map(agent => agent.visits || 0);
        const closersData = agentsData.map(agent => agent.closers || 0);

        // Create chart
        if (chartContainer) {
            agentsVisitsMeetingsChart = new Chart(chartContainer, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Meetings',
                            data: meetingsData,
                            backgroundColor: '#3B82F6',
                            borderColor: '#2563EB',
                            borderWidth: 1
                        },
                        {
                            label: 'Visits',
                            data: visitsData,
                            backgroundColor: '#10B981',
                            borderColor: '#059669',
                            borderWidth: 1
                        },
                        {
                            label: 'Closers',
                            data: closersData,
                            backgroundColor: '#F59E0B',
                            borderColor: '#D97706',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        }
                    }
                },
                plugins: [{
                    id: 'showValues',
                    afterDatasetsDraw: (chart) => {
                        const ctx = chart.ctx;
                        ctx.save();
                        ctx.font = 'bold 12px Arial';
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'bottom';
                        ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--text-color').trim() || '#063A1C';
                        
                        chart.data.datasets.forEach((dataset, i) => {
                            const meta = chart.getDatasetMeta(i);
                            meta.data.forEach((bar, index) => {
                                const value = dataset.data[index];
                                if (value > 0) {
                                    ctx.fillText(value, bar.x, bar.y - 5);
                                }
                            });
                        });
                        ctx.restore();
                    }
                }]
            });
        }

        // Render table
        if (tableContainer) {
            const tableHtml = `
                <table>
                    <thead>
                        <tr>
                            <th>Agent Name</th>
                            <th>Role</th>
                            <th>Meetings</th>
                            <th>Visits</th>
                            <th>Closers</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${agentsData.map(agent => {
                            const total = (agent.meetings || 0) + (agent.visits || 0) + (agent.closers || 0);
                            return `
                                <tr>
                                    <td>${agent.agent_name || 'Unknown'}</td>
                                    <td><span class="badge">${agent.role || 'N/A'}</span></td>
                                    <td>${agent.meetings || 0}</td>
                                    <td>${agent.visits || 0}</td>
                                    <td>${agent.closers || 0}</td>
                                    <td class="font-semibold">${total}</td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            `;
            tableContainer.innerHTML = tableHtml;
        }
    }

    function renderPropertySegments(segmentsData) {
        const chartContainer = document.getElementById('propertySegmentsChart');
        const legendContainer = document.getElementById('property-segments-legend');
        
        if (!segmentsData || Object.keys(segmentsData).length === 0) {
            if (legendContainer) {
                legendContainer.innerHTML = '<p class="text-[#B3B5B4]">No property segment data available</p>';
            }
            return;
        }

        // Destroy existing chart if it exists
        if (propertySegmentsChart) {
            propertySegmentsChart.destroy();
        }

        // Prepare data
        const labels = ['Plot', 'Commercial', 'Residential', 'Other'];
        const data = [
            segmentsData.plot || 0,
            segmentsData.commercial || 0,
            segmentsData.residential || 0,
            segmentsData.other || 0
        ];
        const colors = ['#F59E0B', '#3B82F6', '#10B981', '#6B7280'];
        const total = data.reduce((sum, val) => sum + val, 0);

        // Create donut chart
        if (chartContainer) {
            propertySegmentsChart = new Chart(chartContainer, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: colors,
                        borderColor: '#ffffff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }

        // Render legend
        if (legendContainer) {
            const legendHtml = labels.map((label, index) => {
                const value = data[index];
                const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                return `
                    <div class="flex items-center gap-2">
                        <div class="w-4 h-4 rounded" style="background-color: ${colors[index]}"></div>
                        <span class="text-sm text-brand-primary font-medium">${label}:</span>
                        <span class="text-sm font-bold text-brand-primary">${value}</span>
                        <span class="text-xs text-[#B3B5B4]">(${percentage}%)</span>
                    </div>
                `;
            }).join('');
            legendContainer.innerHTML = legendHtml;
        }
    }

    function renderTelecallerPerformance(telecallersData) {
        const container = document.getElementById('telecaller-performance-cards');
        
        if (!telecallersData || !Array.isArray(telecallersData) || telecallersData.length === 0) {
            if (container) {
                container.innerHTML = '<p class="text-[#B3B5B4]">No sales executive performance data available</p>';
            }
            return;
        }

        const cardsHtml = telecallersData.map(telecaller => {
            // Use same card style as CRM dashboard
            const allocated = (telecaller.allocated !== undefined && telecaller.allocated !== null) ? telecaller.allocated : 0;
            const called = (telecaller.called !== undefined && telecaller.called !== null) ? telecaller.called : 0;
            const remaining = (telecaller.remaining !== undefined && telecaller.remaining !== null) ? telecaller.remaining : 0;
            const interested = (telecaller.interested !== undefined && telecaller.interested !== null) ? telecaller.interested : 0;
            const junk = (telecaller.junk !== undefined && telecaller.junk !== null) ? telecaller.junk : 0;
            const notInterested = (telecaller.not_interested !== undefined && telecaller.not_interested !== null) ? telecaller.not_interested : 0;
            const cnp = (telecaller.cnp !== undefined && telecaller.cnp !== null) ? telecaller.cnp : 0;
            
            return `
                <div class="rounded-lg p-6 text-white shadow-md hover:shadow-lg transition-shadow" style="background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));">
                    <h3 class="text-xl font-bold mb-4 text-center text-white">${telecaller.telecaller_name || 'Unknown'}</h3>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <span class="text-sm opacity-90 block">Allocated</span>
                            <span class="text-lg font-bold block">${allocated}</span>
                        </div>
                        <div>
                            <span class="text-sm opacity-90 block">Called</span>
                            <span class="text-lg font-bold block">${called}</span>
                        </div>
                        <div>
                            <span class="text-sm opacity-90 block">Remaining</span>
                            <span class="text-lg font-bold block">${remaining}</span>
                        </div>
                        <div>
                            <span class="text-sm opacity-90 block">Interested</span>
                            <span class="text-lg font-bold block" style="color: #90ee90;">${interested}</span>
                        </div>
                        <div>
                            <span class="text-sm opacity-90 block">Junk</span>
                            <span class="text-lg font-bold block" style="color: #ffd27f;">${junk}</span>
                        </div>
                        <div>
                            <span class="text-sm opacity-90 block">Not Interested</span>
                            <span class="text-lg font-bold block" style="color: #ffb3b3;">${notInterested}</span>
                        </div>
                        <div>
                            <span class="text-sm opacity-90 block">CNP</span>
                            <span class="text-lg font-bold block text-white">${cnp}</span>
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        if (container) {
            container.innerHTML = cardsHtml;
        }
    }

    function formatAssignedAt(isoString, nowIso) {
        if (!isoString) return '—';
        const d = new Date(isoString);
        if (isNaN(d.getTime())) return isoString;
        const now = nowIso ? new Date(nowIso) : new Date();
        const diffMs = now - d;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);
        if (diffMins < 60) return diffMins <= 1 ? '1m ago' : diffMins + 'm ago';
        if (diffHours < 24) return diffHours === 1 ? '1h ago' : diffHours + 'h ago';
        if (diffDays < 7) return diffDays === 1 ? '1d ago' : diffDays + 'd ago';
        return d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    }

    function formatAssignedAtFull(isoString) {
        if (!isoString) return '—';
        const d = new Date(isoString);
        if (isNaN(d.getTime())) return isoString;
        return d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    }

    function maskPhone(phone) {
        if (!phone || typeof phone !== 'string') return '—';
        const digits = phone.replace(/\D/g, '');
        if (digits.length < 4) return '****';
        return digits.slice(0, 2) + '****' + digits.slice(-4);
    }

    function formatAvgResponseTime(avgResponseMinutes) {
        if (avgResponseMinutes == null || avgResponseMinutes === 0 || isNaN(avgResponseMinutes)) return '0 min';
        const m = Math.round(Number(avgResponseMinutes));
        if (m < 60) return m + ' min';
        const h = Math.floor(m / 60);
        const min = m % 60;
        return min > 0 ? (h + 'h ' + min + 'm') : (h + 'h');
    }

    function renderAverageResponseTime(list) {
        const panel = document.getElementById('average-response-time-panel');
        if (!panel) return;
        let html = '<table class="compact-metric-table"><thead><tr><th>User Name</th><th style="text-align: right;">Avg Time</th><th style="width: 82px;"></th></tr></thead><tbody>';
        if (!list || list.length === 0) {
            html += '<tr><td colspan="3" class="compact-muted" style="padding: 12px; text-align: center;">No users in this role.</td></tr>';
        } else {
            list.forEach(function(row) {
                const name = escapeHtml(row.user_name || '');
                const timeStr = formatAvgResponseTime(row.avg_response_minutes);
                const userId = Number(row.user_id || 0);
                const resetButton = userId > 0
                    ? '<button type="button" onclick="resetAverageResponseTime(' + userId + ')" class="compact-reset-btn">Reset</button>'
                    : '';
                html += '<tr><td>' + name + '</td><td style="text-align: right; font-weight: 700; color: var(--text-color);">' + timeStr + '</td><td style="text-align: right;">' + resetButton + '</td></tr>';
            });
        }
        html += '</tbody></table>';
        panel.innerHTML = html;
    }

    async function resetAverageResponseTime(userId) {
        if (!userId) return;

        const confirmed = window.confirm('Reset this user response time? Old average data will be ignored from now.');
        if (!confirmed) return;

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const response = await fetch('/admin/dashboard/response-time/' + encodeURIComponent(userId) + '/reset', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error('Failed to reset response time');
            }

            await loadDashboardData({ forceRefresh: true });
        } catch (error) {
            console.error('Error resetting response time:', error);
            alert('Failed to reset response time. Please try again.');
        }
    }

    function renderLeadsPendingResponse(data, serverNow) {
        const tbody = document.getElementById('leads-pending-response-tbody');
        if (!tbody) return;

        if (!data || data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="compact-muted" style="padding: 16px; text-align: center;">No leads pending response.</td></tr>';
            return;
        }

        const leadShowBase = "{{ url('/leads') }}";
        let html = '';
        data.forEach((row, index) => {
            const leads = row.leads || [];
            const oldestAssignedAt = leads.length > 0
                ? leads.reduce((min, l) => (!l.assigned_at ? min : (!min || l.assigned_at < min ? l.assigned_at : min)), null)
                : null;
            const oldestAssign = oldestAssignedAt ? formatAssignedAt(oldestAssignedAt, serverNow) : '—';
            const rowId = 'pending-row-' + row.user_id;
            const detailId = 'pending-detail-' + row.user_id;
            html += `
                <tr class="leads-pending-user-row" data-user-id="${row.user_id}" style="background: white; border-bottom: 1px solid #E5DED4; cursor: pointer;" onclick="toggleLeadsPendingDetail('${detailId}', '${rowId}')">
                    <td style="padding: 10px;"><i class="fas fa-chevron-right leads-pending-chevron" id="chevron-${rowId}" style="color: #B3B5B4;"></i></td>
                    <td style="padding: 10px; font-weight: 600;">${escapeHtml(row.user_name || '')}</td>
                    <td style="padding: 10px; text-align: center; font-weight: 700;">${row.pending_count || 0}</td>
                    <td style="padding: 10px; color: #666;">${oldestAssign}</td>
                </tr>
                <tr id="${detailId}" class="leads-pending-detail-row" style="display: none;">
                    <td colspan="4" style="padding: 0; border-bottom: 1px solid #E5DED4; background: #FAFAF9;">
                        <div style="padding: 10px 10px 10px 38px;">
                            <table class="compact-metric-table">
                                <thead>
                                    <tr>
                                        <th>Lead Name</th>
                                        <th>Phone</th>
                                        <th>Assigned At</th>
                                        <th style="width: 64px;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${(row.leads || []).length === 0 ? '<tr><td colspan="4" class="compact-muted" style="padding: 12px; text-align: center;">No pending leads.</td></tr>' : (row.leads || []).map(lead => `
                                        <tr>
                                            <td>${escapeHtml(lead.name || '—')}</td>
                                            <td>${maskPhone(lead.phone)}</td>
                                            <td>${formatAssignedAtFull(lead.assigned_at)}</td>
                                            <td><a href="${leadShowBase}/${lead.lead_id}" class="compact-link-btn">View</a></td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    </td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
    }

    function toggleLeadsPendingDetail(detailId, rowId) {
        const detailRow = document.getElementById(detailId);
        const chevron = document.getElementById('chevron-' + rowId);
        if (!detailRow || !chevron) return;
        const isHidden = detailRow.style.display === 'none';
        detailRow.style.display = isHidden ? 'table-row' : 'none';
        chevron.className = isHidden ? 'fas fa-chevron-down leads-pending-chevron' : 'fas fa-chevron-right leads-pending-chevron';
        if (chevron.style) chevron.style.color = '#B3B5B4';
    }

    function escapeHtml(text) {
        if (text == null) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function toggleMobilePanel(panelId, trigger) {
        const panel = document.getElementById(panelId);
        if (!panel) return;
        const isOpen = panel.classList.toggle('is-open');
        if (trigger) {
            trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        }
    }

    function closeMobilePanel(panelId, trigger) {
        const panel = document.getElementById(panelId);
        if (!panel) return;
        panel.classList.remove('is-open');
        const actionButton = trigger || document.querySelector(`[aria-controls="${panelId}"]`);
        actionButton?.setAttribute('aria-expanded', 'false');
    }

    function updateVisitsMeetingsFilterLabel(filter) {
        const label = document.getElementById('visits-meetings-current-filter');
        if (!label) return;
        const labels = {
            today: 'Today',
            tomorrow: 'Tomorrow',
            this_weekend: 'Weekend',
            this_month: 'This Month',
        };
        label.textContent = labels[filter] || 'Filter';
    }

    // User Visits & Meetings Functions
    let currentVisitsMeetingsFilter = localStorage.getItem('visitsMeetingsFilter') || 'this_month';
    let visitsMeetingsSortColumn = 'total';
    let visitsMeetingsSortDirection = 'desc';

    // Initialize filter button on page load
    document.addEventListener('DOMContentLoaded', function() {
        const activeBtn = document.querySelector(`.visits-meetings-filter-btn[data-filter="${currentVisitsMeetingsFilter}"]`);
        if (activeBtn) {
            document.querySelectorAll('.visits-meetings-filter-btn').forEach(btn => btn.classList.remove('active'));
            activeBtn.classList.add('active');
        }
        updateVisitsMeetingsFilterLabel(currentVisitsMeetingsFilter);
    });

    async function loadUserVisitsMeetings(filter) {
        try {
            currentVisitsMeetingsFilter = filter;
            localStorage.setItem('visitsMeetingsFilter', filter);

            // Update active button
            document.querySelectorAll('.visits-meetings-filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            const activeBtn = document.querySelector(`.visits-meetings-filter-btn[data-filter="${filter}"]`);
            if (activeBtn) {
                activeBtn.classList.add('active');
            }
            updateVisitsMeetingsFilterLabel(filter);

            // Show loading state
            const tableBody = document.getElementById('visits-meetings-table-body');
            if (tableBody) {
                tableBody.innerHTML = '<tr><td colspan="5" style="padding: 20px; text-align: center; color: #B3B5B4;">Loading...</td></tr>';
            }

            // Build query parameters
            const params = new URLSearchParams();
            params.append('visits_meetings_filter', filter);

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const response = await fetch(API_BASE_URL + '?' + params.toString(), {
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error('Failed to load visits/meetings data');
            }

            const data = await response.json();
            
            if (data.user_visits_meetings) {
                renderVisitsMeetingsSummary(data.user_visits_meetings.summary);
                renderVisitsMeetingsTable(data.user_visits_meetings.users);
            } else {
                renderVisitsMeetingsSummary({ total_users: 0, total_visits: 0, total_meetings: 0 });
                renderVisitsMeetingsTable([]);
            }
        } catch (error) {
            console.error('Error loading visits/meetings data:', error);
            const tableBody = document.getElementById('visits-meetings-table-body');
            if (tableBody) {
                tableBody.innerHTML = '<tr><td colspan="5" style="padding: 20px; text-align: center; color: #ef4444;">Error loading data. Please try again.</td></tr>';
            }
        }
    }

    function filterVisitsMeetings(filter, buttonElement) {
        if (buttonElement) {
            document.querySelectorAll('.visits-meetings-filter-btn').forEach(btn => btn.classList.remove('active'));
            buttonElement.classList.add('active');
        }
        updateVisitsMeetingsFilterLabel(filter);
        const panel = document.getElementById('visits-meetings-filter-panel');
        const trigger = document.querySelector('[aria-controls="visits-meetings-filter-panel"]');
        if (panel) panel.classList.remove('is-open');
        if (trigger) trigger.setAttribute('aria-expanded', 'false');
        loadUserVisitsMeetings(filter);
    }

    function renderVisitsMeetingsSummary(summary) {
        const totalUsersEl = document.getElementById('visits-meetings-total-users');
        const totalVisitsEl = document.getElementById('visits-meetings-total-visits');
        const totalMeetingsEl = document.getElementById('visits-meetings-total-meetings');

        if (totalUsersEl) totalUsersEl.textContent = summary.total_users || 0;
        if (totalVisitsEl) totalVisitsEl.textContent = summary.total_visits || 0;
        if (totalMeetingsEl) totalMeetingsEl.textContent = summary.total_meetings || 0;
    }

    function renderVisitsMeetingsTable(users) {
        const tableBody = document.getElementById('visits-meetings-table-body');
        
        if (!tableBody) return;

        if (!users || users.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="5" style="padding: 20px; text-align: center; color: #B3B5B4;">No data available</td></tr>';
            return;
        }

        // Sort data
        const sortedUsers = [...users].sort((a, b) => {
            let aVal, bVal;
            switch(visitsMeetingsSortColumn) {
                case 'user_name':
                    aVal = a.user_name || '';
                    bVal = b.user_name || '';
                    return visitsMeetingsSortDirection === 'asc' ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
                case 'role':
                    aVal = a.role || '';
                    bVal = b.role || '';
                    return visitsMeetingsSortDirection === 'asc' ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
                case 'visits_count':
                    aVal = a.visits_count || 0;
                    bVal = b.visits_count || 0;
                    return visitsMeetingsSortDirection === 'asc' ? aVal - bVal : bVal - aVal;
                case 'meetings_count':
                    aVal = a.meetings_count || 0;
                    bVal = b.meetings_count || 0;
                    return visitsMeetingsSortDirection === 'asc' ? aVal - bVal : bVal - aVal;
                case 'total':
                default:
                    aVal = a.total || 0;
                    bVal = b.total || 0;
                    return visitsMeetingsSortDirection === 'asc' ? aVal - bVal : bVal - aVal;
            }
        });

        const rowsHtml = sortedUsers.map(user => `
            <tr style="border-bottom: 1px solid #E5DED4;" onmouseover="this.style.background='#F7F6F3'" onmouseout="this.style.background='transparent'">
                <td style="padding: 12px; color: var(--text-color);">${user.user_name || 'N/A'}</td>
                <td style="padding: 12px; color: var(--text-color);">${user.role || 'N/A'}</td>
                <td style="padding: 12px; text-align: center; color: var(--text-color); font-weight: 600;">${user.visits_count || 0}</td>
                <td style="padding: 12px; text-align: center; color: var(--text-color); font-weight: 600;">${user.meetings_count || 0}</td>
                <td style="padding: 12px; text-align: center; color: var(--text-color); font-weight: 700;">${user.total || 0}</td>
            </tr>
        `).join('');

        tableBody.innerHTML = rowsHtml;
    }

    function sortTable(column) {
        if (visitsMeetingsSortColumn === column) {
            visitsMeetingsSortDirection = visitsMeetingsSortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            visitsMeetingsSortColumn = column;
            visitsMeetingsSortDirection = 'desc';
        }

        // Reload table with new sort
        loadUserVisitsMeetings(currentVisitsMeetingsFilter);
    }

    // Initial load
    loadDashboardData();

    // Auto-refresh every 5 minutes without moving the user from the current section.
    setInterval(() => loadDashboardData({ preserveScroll: true, silent: true }), 300000);

    // Export dropdown toggle
    document.addEventListener('DOMContentLoaded', function() {
        const exportBtn = document.querySelector('.dropdown button');
        const dropdownMenu = document.querySelector('.dropdown-menu');
        
        if (exportBtn && dropdownMenu) {
            exportBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                dropdownMenu.style.display = dropdownMenu.style.display === 'none' ? 'block' : 'none';
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!exportBtn.contains(e.target) && !dropdownMenu.contains(e.target)) {
                    dropdownMenu.style.display = 'none';
                }
            });
        }
    });
    
    // Call Statistics Functions
    let callsByRoleChart = null;
    let outcomeDistributionChart = null;
    let currentCallStatsFilter = 'today';
    
    async function loadCallStatistics(filter = 'today', buttonElement = null) {
        currentCallStatsFilter = filter;
        localStorage.setItem('callStatsFilter', filter);
        
        // Update button states
        if (buttonElement) {
            document.querySelectorAll('.call-stats-filter-btn').forEach(btn => {
                btn.classList.remove('active');
                btn.style.background = 'white';
                btn.style.color = 'var(--text-color)';
            });
            buttonElement.classList.add('active');
            buttonElement.style.background = 'var(--primary-color)';
            buttonElement.style.color = 'white';
        }
        
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const params = new URLSearchParams();
            params.append('date_range', filter);
            
            const response = await fetch(API_BASE_URL + '?' + params.toString(), {
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
            });
            
            if (!response.ok) {
                throw new Error('Failed to load call statistics');
            }
            
            const data = await response.json();
            
            if (data.call_statistics) {
                updateCallStatistics(data.call_statistics);
            }
        } catch (error) {
            console.error('Error loading call statistics:', error);
        }
    }
    
    function updateCallStatistics(stats) {
        // Update summary cards
        const totalEl = document.getElementById('call-stats-total');
        if (totalEl) totalEl.textContent = stats.total_calls || 0;
        
        const durationEl = document.getElementById('call-stats-duration');
        if (durationEl) durationEl.textContent = stats.formatted_duration || '0s';
        
        const avgDurationEl = document.getElementById('call-stats-avg-duration');
        if (avgDurationEl) avgDurationEl.textContent = stats.formatted_average_duration || '0s';
        
        const connectionRateEl = document.getElementById('call-stats-connection-rate');
        if (connectionRateEl) connectionRateEl.textContent = (stats.connection_rate || 0).toFixed(1) + '%';
        
        // Update Calls by Role Chart
        if (stats.calls_by_role && stats.calls_by_role.length > 0) {
            updateCallsByRoleChart(stats.calls_by_role);
        }
        
        // Update Outcome Distribution Chart
        if (stats.outcome_distribution && stats.outcome_distribution.length > 0) {
            updateOutcomeDistributionChart(stats.outcome_distribution);
        }
        
        // Update Top Users Table
        if (stats.top_users && stats.top_users.length > 0) {
            const tbody = document.getElementById('top-users-table-body');
            if (tbody) {
                tbody.innerHTML = stats.top_users.map(user => `
                    <tr style="border-bottom: 1px solid #E5DED4;">
                        <td style="padding: 12px; color: var(--text-color);">${user.user_name}</td>
                        <td style="padding: 12px; text-align: center; color: var(--text-color);">${user.total_calls}</td>
                        <td style="padding: 12px; text-align: center; color: var(--text-color);">${user.formatted_duration}</td>
                        <td style="padding: 12px; text-align: center; color: var(--text-color);">${user.formatted_average_duration}</td>
                    </tr>
                `).join('');
            }
            const topUsersSection = document.getElementById('top-users-section');
            if (topUsersSection) topUsersSection.style.display = 'block';
        }
        
        // Update Recent Calls
        if (stats.recent_calls && stats.recent_calls.length > 0) {
            const list = document.getElementById('recent-calls-list');
            if (list) {
                list.innerHTML = stats.recent_calls.map(call => `
                    <div style="padding: 12px; border-bottom: 1px solid #E5DED4; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div style="font-weight: 600; color: var(--text-color);">${call.lead_name}</div>
                            <div style="font-size: 12px; color: #B3B5B4;">${call.phone_number} • ${call.duration} • ${new Date(call.start_time).toLocaleString()}</div>
                        </div>
                        <a href="/calls/${call.id}" style="color: var(--link-color); text-decoration: none;">
                            <i class="fas fa-eye"></i>
                        </a>
                    </div>
                `).join('');
            }
            const recentCallsSection = document.getElementById('recent-calls-section');
            if (recentCallsSection) recentCallsSection.style.display = 'block';
        }
    }
    
    function updateCallsByRoleChart(data) {
        const ctx = document.getElementById('callsByRoleChart');
        if (!ctx) return;
        
        if (callsByRoleChart) {
            callsByRoleChart.destroy();
        }
        
        const labels = data.map(item => item.role_name);
        const callCounts = data.map(item => item.call_count);
        
        callsByRoleChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Calls',
                    data: callCounts,
                    backgroundColor: [
                        'rgba(32, 90, 68, 0.6)',
                        'rgba(6, 58, 28, 0.6)',
                        'rgba(21, 128, 61, 0.6)',
                        'rgba(179, 181, 180, 0.6)',
                    ],
                    borderColor: [
                        'rgba(32, 90, 68, 1)',
                        'rgba(6, 58, 28, 1)',
                        'rgba(21, 128, 61, 1)',
                        'rgba(179, 181, 180, 1)',
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }
    
    function updateOutcomeDistributionChart(data) {
        const ctx = document.getElementById('outcomeDistributionChart');
        if (!ctx) return;
        
        if (outcomeDistributionChart) {
            outcomeDistributionChart.destroy();
        }
        
        const labels = data.map(item => item.label);
        const counts = data.map(item => item.count);
        
        outcomeDistributionChart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    data: counts,
                    backgroundColor: [
                        'rgba(32, 90, 68, 0.6)',
                        'rgba(239, 68, 68, 0.6)',
                        'rgba(59, 130, 246, 0.6)',
                        'rgba(234, 179, 8, 0.6)',
                        'rgba(168, 85, 247, 0.6)',
                        'rgba(107, 114, 128, 0.6)',
                    ],
                    borderColor: [
                        'rgba(32, 90, 68, 1)',
                        'rgba(239, 68, 68, 1)',
                        'rgba(59, 130, 246, 1)',
                        'rgba(234, 179, 8, 1)',
                        'rgba(168, 85, 247, 1)',
                        'rgba(107, 114, 128, 1)',
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
    
    // Real-time call log updates via Pusher
    @if(config('broadcasting.default') === 'pusher')
    document.addEventListener('DOMContentLoaded', function() {
        const pusher = new Pusher('{{ config("broadcasting.connections.pusher.key") }}', {
            cluster: '{{ config("broadcasting.connections.pusher.options.cluster") }}',
            encrypted: true
        });
        
        const callLogsChannel = pusher.subscribe('call-logs');
        callLogsChannel.bind('call-log.created', function(data) {
            // Reload call statistics
            if (typeof loadCallStatistics === 'function') {
                loadCallStatistics(currentCallStatsFilter);
            }
        });
        
        // Subscribe to admin channel
        const adminChannel = pusher.subscribe('private-admin');
        adminChannel.bind('call-log.created', function(data) {
            if (typeof loadCallStatistics === 'function') {
                loadCallStatistics(currentCallStatsFilter);
            }
        });
    });
    @endif
</script>
@endpush

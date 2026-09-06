@extends(auth()->user()?->isFinanceManager() && !auth()->user()?->isAdmin() ? 'finance-manager.layout' : 'layouts.app')

@section('title', 'Lead Quality Report - Data Intelligent')
@section('page-title', 'Lead Quality Report')

@section('content')
@php
    $pct = function ($value, $total = null) use ($summary) {
        $total = $total ?? $summary['total'];

        return $total > 0 ? round(($value / $total) * 100, 1) : 0;
    };
    $bucketClass = [
        'interested' => 'good',
        'not_interested' => 'bad',
        'junk' => 'bad',
        'cnp' => 'warn',
        'call_later' => 'info',
        'pending' => 'muted',
    ];
    $isAllSourceReport = ($filters['source'] ?? '') === 'all';
    $sourceTotals = $isAllSourceReport ? [
        'total' => $sourceBreakdown->sum('total'),
        'interested' => $sourceBreakdown->sum('interested'),
        'cnp' => $sourceBreakdown->sum('cnp'),
        'call_later' => $sourceBreakdown->sum('call_later'),
        'junk' => $sourceBreakdown->sum('junk'),
        'not_interested' => $sourceBreakdown->sum('not_interested'),
        'interested_follow_up' => $sourceBreakdown->sum('interested_follow_up'),
        'site_visit' => $sourceBreakdown->sum('site_visit'),
        'site_visit_followup' => $sourceBreakdown->sum('site_visit_followup'),
        'meeting' => $sourceBreakdown->sum('meeting'),
        'closed' => $sourceBreakdown->sum('closed'),
    ] : [];
    $sourceTotals['interested_rate'] = ($sourceTotals['total'] ?? 0) > 0 ? round(($sourceTotals['interested'] / $sourceTotals['total']) * 100, 1) : 0;
@endphp

<style>
    .ql-page{background:#eef2f6;min-height:100vh;margin:-1.5rem;padding:18px;color:#111827;font-family:Arial,"Helvetica Neue",sans-serif}
    .ql-shell{width:100%;max-width:none;margin:0;background:#fff;border:1px solid #cfd8e3;box-shadow:0 12px 26px rgba(15,23,42,.08)}
    .ql-hero{background:#107c41;color:#fff;padding:14px 18px;border-bottom:1px solid #0b5f30}
    .ql-hero h1{font-size:24px;font-weight:800;margin:3px 0 0;letter-spacing:0}
    .ql-hero p{margin:5px 0 0;color:#e6f4ea;font-size:13px;line-height:1.45;max-width:1100px}
    .ql-actions{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
    .ql-btn,.ql-input{height:34px;border-radius:3px;border:1px solid #b8c7d9;background:#fff;padding:0 10px;font-size:12px;color:#1f2937}
    .ql-input{min-width:150px;box-shadow:inset 0 1px 0 rgba(15,23,42,.03)}
    .ql-help{display:block;margin-top:4px;font-size:11px;font-weight:600;text-transform:none;letter-spacing:0;color:#64748b}
    .ql-meta-controls{width:100%;order:30;margin-top:2px}
    .ql-check-group{width:100%;border:1px solid #b7cabb;background:#fff;border-radius:4px;padding:10px}
    .ql-check-head{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:8px}
    .ql-check-title{font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:.08em;color:#345a46}
    .ql-check-actions{display:flex;gap:6px}
    .ql-mini-btn{border:1px solid #b8c7d9;background:#f8fafc;border-radius:3px;padding:4px 7px;font-size:11px;font-weight:800;color:#1f2937;cursor:pointer}
    .ql-mini-btn:hover{background:#eef6f1}
    .ql-check-search{width:100%;height:32px;border:1px solid #d0dbe6;border-radius:3px;padding:0 8px;margin-bottom:8px;font-size:12px}
    .ql-check-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:6px;max-height:176px;overflow:auto;padding-right:2px}
    .ql-check-option{display:flex!important;grid-template-columns:none!important;align-items:center;gap:8px;padding:6px 7px;border:1px solid #e2e8f0;border-radius:4px;background:#f8fafc;font-size:12px!important;font-weight:700!important;text-transform:none!important;letter-spacing:0!important;color:#0f172a!important}
    .ql-check-option input{width:14px;height:14px;accent-color:#107c41;flex:0 0 auto}
    .ql-check-option span{white-space:normal;line-height:1.25}
    .ql-check-empty{padding:10px;border:1px dashed #cbd5e1;border-radius:4px;color:#64748b;font-size:12px}
    .ql-btn{display:inline-flex;align-items:center;gap:7px;font-weight:700;cursor:pointer;text-decoration:none}
    .ql-btn:hover{background:#f3f6f9}
    .ql-btn.primary{background:#107c41;border-color:#107c41;color:#fff}
    .ql-btn.primary:hover{background:#0b6f39}
    .ql-btn.light{background:#e6f4ea;border-color:#9fd3b2;color:#0f5132}
    .ql-panel{background:#fff;border:0;border-radius:0;box-shadow:none}
    .ql-filter{padding:12px 18px;display:flex;flex-wrap:wrap;gap:10px;align-items:end;background:#f7f9fb;border-bottom:1px solid #cfd8e3}
    .ql-filter label{display:grid;gap:4px;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#53657a}
    .ql-grid{display:grid;gap:0;margin-top:0;border-bottom:1px solid #cfd8e3}
    .ql-grid.kpi{grid-template-columns:repeat(6,minmax(0,1fr))}
    .ql-card{padding:13px 16px;border-right:1px solid #dbe3ee;background:#fff}
    .ql-card:last-child{border-right:0}
    .ql-kpi-label{font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#53657a}
    .ql-kpi-value{font-size:25px;font-weight:800;margin-top:6px;line-height:1.05}
    .ql-kpi-sub{font-size:12px;color:#5f6f84;margin-top:5px}
    .ql-score{display:flex;align-items:center;gap:18px}
    .ql-ring{width:98px;height:98px;border-radius:999px;background:conic-gradient(#107c41 calc(var(--score)*1%),#d9e1ea 0);display:grid;place-items:center;flex:0 0 auto}
    .ql-ring span{width:70px;height:70px;border-radius:999px;background:#fff;display:grid;place-items:center;font-size:21px;font-weight:800;border:1px solid #dbe3ee}
    .ql-section{padding:16px 18px;border-bottom:1px solid #cfd8e3;overflow-x:auto}
    .ql-section-head{display:flex;justify-content:space-between;gap:14px;align-items:flex-start;margin-bottom:10px}
    .ql-section h2{margin:0;font-size:16px;font-weight:800;color:#111827}
    .ql-section p{margin:3px 0 0;color:#5f6f84;font-size:12px;line-height:1.45}
    .ql-two{display:grid;grid-template-columns:1fr 1fr;gap:0;border-bottom:1px solid #cfd8e3}
    .ql-two>.ql-section{border-bottom:0}
    .ql-two>.ql-section:first-child{border-right:1px solid #cfd8e3}
    .ql-table{width:100%;border-collapse:collapse;font-size:12px;line-height:1.35;background:#fff}
    .ql-table-scroll{width:100%;overflow-x:auto;border:1px solid #d7e0ea}
    .ql-section>.ql-table{min-width:820px;border:1px solid #d7e0ea}
    .ql-table.funnel{min-width:1320px;border-collapse:separate;border-spacing:0;border:0}
    .ql-table.funnel th,.ql-table.funnel td{text-align:center;white-space:nowrap}
    .ql-table.funnel th:first-child,.ql-table.funnel td:first-child{text-align:left}
    .ql-table.funnel tbody tr:hover,.ql-table tbody tr:hover{background:#f8fbfd}
    .ql-funnel-source{position:sticky;left:0;background:#fff;z-index:1;box-shadow:5px 0 0 rgba(207,216,227,.45)}
    .ql-table.funnel thead .ql-funnel-source{background:#edf4ff;z-index:3}
    .ql-funnel-group th{font-size:10px;color:#203247;background:#e8f3ec;border-bottom:1px solid #bfd6c7;text-align:center}
    .ql-funnel-group .raw{background:#fff3df;color:#7c3d00}
    .ql-funnel-group .pipe{background:#e8f3ec;color:#0f5132}
    .ql-count{display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:23px;border-radius:3px;padding:0 8px;font-weight:800;font-size:11px;border:1px solid transparent}
    .ql-count.neutral{background:#edf2f7;color:#111827;border-color:#d9e1ea}.ql-count.good{background:#e8f5e9;color:#0f6b35;border-color:#bfe3ca}.ql-count.warn{background:#fff4ce;color:#8a5600;border-color:#f4d781}.ql-count.info{background:#e7f0ff;color:#1959b3;border-color:#c1d8ff}.ql-count.bad{background:#fde7e9;color:#b4232e;border-color:#f5c2c7}.ql-count.muted{background:#f7f9fb;color:#5f6f84;border-color:#e2e8f0}
    .ql-funnel-total td{background:#edf4ff;font-weight:800;border-top:2px solid #8eb4e3}
    .ql-funnel-legend{display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:flex-end}
    .ql-funnel-legend span{display:inline-flex;align-items:center;gap:6px;color:#53657a;font-size:11px;font-weight:700}
    .ql-dot{width:8px;height:8px;border-radius:2px;display:inline-block}.ql-dot.raw{background:#f4b183}.ql-dot.pipe{background:#70ad47}.ql-dot.bad{background:#d9534f}
    .ql-table th{position:sticky;top:0;z-index:2;background:#edf4ff;color:#203247;text-align:left;font-size:10px;text-transform:uppercase;letter-spacing:.06em;padding:9px 10px;border-right:1px solid #d7e0ea;border-bottom:1px solid #b7c9dd;white-space:nowrap}
    .ql-table td{padding:9px 10px;border-right:1px solid #e1e7ef;border-bottom:1px solid #e1e7ef;vertical-align:top}
    .ql-table th:last-child,.ql-table td:last-child{border-right:0}
    .ql-table tr:last-child td{border-bottom:0}
    .ql-badge{display:inline-flex;border-radius:3px;padding:4px 8px;font-size:11px;font-weight:800;border:1px solid transparent;white-space:nowrap}
    .ql-badge.good{background:#e8f5e9;color:#0f6b35;border-color:#bfe3ca}
    .ql-badge.bad{background:#fde7e9;color:#b4232e;border-color:#f5c2c7}
    .ql-badge.warn{background:#fff4ce;color:#8a5600;border-color:#f4d781}
    .ql-badge.info{background:#e7f0ff;color:#1959b3;border-color:#c1d8ff}
    .ql-badge.muted{background:#f7f9fb;color:#5f6f84;border-color:#dbe3ee}
    .ql-bar{height:12px;border-radius:0;background:#edf2f7;overflow:hidden;min-width:95px;border:1px solid #d7e0ea}
    .ql-bar span{display:block;height:100%;border-radius:0;background:#107c41;width:var(--w)}
    .ql-bar.bad span{background:#d9534f}.ql-bar.warn span{background:#f4b183}.ql-bar.info span{background:#4472c4}
    .ql-note{border:1px solid #bfe3ca;border-left:5px solid #107c41;background:#f3fbf5;border-radius:0;padding:12px;color:#164b2f;font-size:12px;line-height:1.55}
    .ql-list{margin:0;padding-left:18px;color:#334155;font-size:12px;line-height:1.75}
    .ql-trend-layout{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:14px;align-items:stretch}
    .ql-trend-card{border:1px solid #d7e0ea;background:#fff;min-width:0}
    .ql-trend-card-head{display:flex;justify-content:space-between;gap:10px;align-items:center;padding:9px 10px;background:#edf4ff;border-bottom:1px solid #b7c9dd}
    .ql-trend-card-head h3{margin:0;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:#203247}
    .ql-trend-card-head span{font-size:11px;font-weight:700;color:#53657a}
    .ql-trend-chart{display:grid;gap:9px;padding:10px}
    .ql-trend-row{display:grid;grid-template-columns:74px minmax(0,1fr) 44px;gap:8px;align-items:center}
    .ql-trend-date{font-size:11px;font-weight:700;color:#334155;white-space:nowrap}
    .ql-trend-track{height:18px;background:#f2f6fa;border:1px solid #d7e0ea;display:flex;overflow:hidden}
    .ql-trend-segment{height:100%;min-width:1px}
    .ql-trend-segment.not-interested{background:#d9534f}
    .ql-trend-segment.junk{background:#f4b183}
    .ql-trend-segment.cnp{background:#ffd966}
    .ql-trend-total{font-size:11px;font-weight:800;text-align:right;color:#111827}
    .ql-trend-legend{display:flex;flex-wrap:wrap;gap:10px;padding:0 10px 10px;color:#53657a;font-size:11px;font-weight:700}
    .ql-trend-legend span{display:inline-flex;align-items:center;gap:5px}
    .ql-trend-legend i{width:9px;height:9px;display:inline-block;border-radius:2px}
    .ql-trend-table-wrap{overflow-x:auto}
    .ql-page{background:linear-gradient(180deg,#eaf3ef 0,#f6f8fb 210px);color:#111827}
    .ql-shell{background:#fff;border-color:#c9d8cf;box-shadow:0 14px 30px rgba(6,58,28,.12)}
    .ql-hero{background:linear-gradient(135deg,#052e1d 0,#0b5f30 58%,#128447 100%);border-bottom-color:#0f7a42}
    .ql-hero p{color:#e6f7ec}
    .ql-filter{background:#f1f8f4;border-bottom-color:#c9d8cf}
    .ql-filter label{color:#345a46}
    .ql-input{background:#fff;border-color:#b7cabb;color:#0f172a}
    .ql-btn.primary{background:#0b6b34;border-color:#0b6b34;color:#fff}
    .ql-btn.primary:hover{background:#095a2c}
    .ql-btn.light{background:#eef8f2;border-color:#cce7d5;color:#064326}
    .ql-grid,.ql-section,.ql-two{border-color:#c9d8cf}
    .ql-card{background:linear-gradient(180deg,#ffffff 0,#f7fbf8 100%);border-right-color:#d8e4dc}
    .ql-kpi-label{color:#3f6651}
    .ql-kpi-sub,.ql-section p{color:#64748b}
    .ql-section h2{color:#0f172a}
    .ql-table,.ql-trend-card{background:#fff;color:#0f172a}
    .ql-table-scroll,.ql-section>.ql-table,.ql-trend-card{border-color:#cfded5}
    .ql-table th{background:#eaf4ee;color:#183c2a;border-right-color:#cfded5;border-bottom-color:#abc7b5}
    .ql-table td{background:#fff;border-right-color:#e1e7ef;border-bottom-color:#e1e7ef}
    .ql-table tbody tr:hover td,.ql-table.funnel tbody tr:hover{background:#f7fbf8}
    .ql-funnel-source{background:#fff;box-shadow:5px 0 0 rgba(207,216,227,.45)}
    .ql-table.funnel thead .ql-funnel-source{background:#eaf4ee}
    .ql-funnel-group th,.ql-funnel-group .pipe{background:#e6f3eb;color:#14532d;border-bottom-color:#c0d8c8}
    .ql-funnel-group .raw{background:#fff3df;color:#7c3d00}
    .ql-funnel-total td{background:#eaf4ee;color:#0f172a;border-top-color:#0f7a42}
    .ql-note{background:#f0fbf4;border-color:#bfe3ca;border-left-color:#0f7a42;color:#14532d}
    .ql-ring{background:conic-gradient(#0f7a42 calc(var(--score)*1%),#d9e1ea 0)}
    .ql-ring span{background:#fff;border-color:#dbe3ee;color:#0f172a}
    .ql-trend-card-head{background:#eaf4ee;border-bottom-color:#abc7b5}
    .ql-trend-card-head h3{color:#183c2a}
    .ql-trend-card-head span,.ql-trend-date,.ql-trend-legend{color:#53657a}
    .ql-trend-track{background:#f2f6fa;border-color:#d7e0ea}
    .ql-trend-total{color:#111827}
    .ql-print-only{display:none}
    @media(max-width:1100px){.ql-grid.kpi{grid-template-columns:repeat(2,minmax(0,1fr))}.ql-two,.ql-trend-layout{grid-template-columns:1fr}.ql-two>.ql-section:first-child{border-right:0;border-bottom:1px solid #cfd8e3}}
    @media(max-width:640px){.ql-page{margin:-1rem;padding:1rem}.ql-grid.kpi{grid-template-columns:1fr}.ql-card{border-right:0;border-bottom:1px solid #dbe3ee}.ql-card:last-child{border-bottom:0}.ql-hero h1{font-size:22px}.ql-actions,.ql-filter{display:grid}.ql-btn,.ql-input{width:100%;min-width:0}.ql-section-head{display:grid}.ql-score{align-items:flex-start}}
    @media print{
        @page{size:A4 landscape;margin:6mm}
        *{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important}
        html,body{width:100%!important;height:auto!important;overflow:visible!important;background:#fff!important}
        .sidebar,.topbar,.header,#sidebar,#sidebarToggle,#sidebarOverlay,#mobileFooterNav,.admin-mobile-nav-scroll-hint,.ql-filter,.ql-no-print{display:none!important}
        #mainContent{margin:0!important;padding:0!important;width:100%!important;max-width:none!important}
        .ql-page{margin:0!important;padding:0!important;background:#fff!important;min-height:auto!important;width:100%!important}
        .ql-shell{width:100%!important;max-width:none!important;margin:0!important;border:1px solid #9eb6ce!important}
        .ql-panel,.ql-hero{box-shadow:none!important;break-inside:avoid}
        .ql-hero{padding:9px 10px!important;border-radius:0!important;background:#107c41!important}
        .ql-hero h1{font-size:17px!important}
        .ql-hero p{font-size:9px!important;line-height:1.25!important;white-space:nowrap!important;overflow:hidden!important;text-overflow:ellipsis!important}
        .ql-print-only{display:block!important}
        .ql-grid.kpi{grid-template-columns:repeat(6,minmax(0,1fr))!important;gap:0!important}
        .ql-two{grid-template-columns:1fr 1fr!important;gap:0!important}
        .ql-card,.ql-section{padding:7px 8px!important}
        .ql-kpi-label{font-size:7px!important;letter-spacing:.04em!important}
        .ql-kpi-value{font-size:16px!important}
        .ql-kpi-sub,.ql-section p,.ql-note,.ql-list{font-size:8px!important}
        .ql-section h2{font-size:12px!important}
        .ql-section-head{margin-bottom:6px!important}
        .ql-table-scroll{overflow:visible!important;border:1px solid #b7c9dd!important}
        .ql-section>.ql-table,.ql-table.funnel{min-width:0!important;width:100%!important;table-layout:fixed!important}
        .ql-table{font-size:7.5px!important;line-height:1.18!important}
        .ql-table th,.ql-table td{position:static!important;padding:3px 4px!important;white-space:normal!important;word-break:break-word!important}
        .ql-table.funnel th,.ql-table.funnel td{white-space:normal!important}
        .ql-funnel-source{position:static!important;box-shadow:none!important}
        .ql-count{min-width:0!important;height:auto!important;padding:2px 4px!important;font-size:7px!important}
        .ql-badge{padding:2px 4px!important;font-size:7px!important}
        .ql-ring{width:50px!important;height:50px!important}
        .ql-ring span{width:36px!important;height:36px!important;font-size:11px!important}
        .ql-bar{height:8px!important;min-width:45px!important}
        .ql-trend-layout{grid-template-columns:1fr 1fr!important;gap:6px!important}
        .ql-trend-card-head{padding:4px 5px!important}
        .ql-trend-card-head h3{font-size:8px!important}
        .ql-trend-card-head span,.ql-trend-date,.ql-trend-total,.ql-trend-legend{font-size:7px!important}
        .ql-trend-chart{gap:4px!important;padding:5px!important}
        .ql-trend-row{grid-template-columns:48px minmax(0,1fr) 24px!important;gap:4px!important}
        .ql-trend-track{height:10px!important}
        .ql-trend-legend{gap:5px!important;padding:0 5px 5px!important}
        a[href]:after{content:""}
    }
</style>

<div class="ql-page">
    <div class="ql-shell">
        <section class="ql-hero">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <div class="text-xs font-black uppercase tracking-[0.18em] text-emerald-100">Data Intelligent</div>
                    <h1>{{ $sourceLabel }} Lead Quality Report</h1>
                    <p>
                        CRM me user outcomes ke basis par live lead quality analysis:
                        Interested, Not Interested, Junk/Invalid, CNP, call later aur pending leads ka breakdown.
                    </p>
                    <p class="ql-print-only">Generated on {{ $generatedAt->format('d M Y, h:i A') }}</p>
                </div>
                <div class="ql-actions ql-no-print">
                    <button type="button" onclick="window.print()" class="ql-btn light"><i class="fas fa-print"></i> Print / Save PDF</button>
                    <a href="{{ route('data-intelligence.index') }}" class="ql-btn light"><i class="fas fa-brain"></i> Data Intelligent</a>
                </div>
            </div>
        </section>

        <form method="GET" action="{{ route('data-intelligence.lead-quality') }}" class="ql-panel ql-filter ql-no-print">
            @if(empty($sourceOptions))
                <div class="ql-note">No lead source access assigned. Ask an admin to assign allowed sources for this Ad Manager.</div>
            @else
            <label>Source
                <select name="source" class="ql-input" data-source-select>
                    @foreach($sourceOptions as $sourceKey => $sourceName)
                        <option value="{{ $sourceKey }}" @selected($filters['source'] === $sourceKey)>{{ $sourceName }}</option>
                    @endforeach
                </select>
            </label>
            @endif
            @php
                $selectedFormIds = collect($filters['fb_form_ids'] ?? [])->map(fn ($id) => (string) $id)->all();
                $selectedCampaignIds = collect($filters['campaign_ids'] ?? [])->map(fn ($id) => (string) $id)->all();
            @endphp
            <label data-meta-view-wrap style="{{ $isMetaSource ? '' : 'display:none' }}">Meta View
                <select name="meta_view" class="ql-input" data-meta-view-select>
                    @foreach($metaViewOptions as $metaViewKey => $metaViewLabel)
                        <option value="{{ $metaViewKey }}" @selected($filters['meta_view'] === $metaViewKey)>{{ $metaViewLabel }}</option>
                    @endforeach
                </select>
            </label>
            <div class="ql-meta-controls" data-meta-controls style="{{ $isMetaSource ? '' : 'display:none' }}">
                <div class="ql-check-group" data-meta-panel="form" style="{{ $isMetaSource && $filters['meta_view'] === 'form' ? '' : 'display:none' }}">
                    <div class="ql-check-head">
                        <div>
                            <div class="ql-check-title">Meta Forms</div>
                            <span class="ql-help" data-selected-count>All Forms selected</span>
                        </div>
                        <div class="ql-check-actions">
                            <button type="button" class="ql-mini-btn" data-check-all>Select All</button>
                            <button type="button" class="ql-mini-btn" data-check-clear>Clear</button>
                        </div>
                    </div>
                    <input type="search" class="ql-check-search" placeholder="Search form..." data-check-search>
                    <div class="ql-check-list">
                        @forelse($metaOptions['forms'] as $form)
                            <label class="ql-check-option">
                                <input type="checkbox" name="fb_form_ids[]" value="{{ $form['id'] }}" @checked(in_array((string) $form['id'], $selectedFormIds, true))>
                                <span>{{ $form['name'] }}</span>
                            </label>
                        @empty
                            <div class="ql-check-empty">No Meta forms found for selected period.</div>
                        @endforelse
                    </div>
                </div>
                <div class="ql-check-group" data-meta-panel="campaign" style="{{ $isMetaSource && $filters['meta_view'] === 'campaign' ? '' : 'display:none' }}">
                    <div class="ql-check-head">
                        <div>
                            <div class="ql-check-title">Campaigns</div>
                            <span class="ql-help" data-selected-count>All Campaigns selected</span>
                        </div>
                        <div class="ql-check-actions">
                            <button type="button" class="ql-mini-btn" data-check-all>Select All</button>
                            <button type="button" class="ql-mini-btn" data-check-clear>Clear</button>
                        </div>
                    </div>
                    <input type="search" class="ql-check-search" placeholder="Search campaign..." data-check-search>
                    <div class="ql-check-list">
                        @forelse($metaOptions['campaigns'] as $campaign)
                            <label class="ql-check-option">
                                <input type="checkbox" name="campaign_ids[]" value="{{ $campaign['id'] }}" @checked(in_array((string) $campaign['id'], $selectedCampaignIds, true))>
                                <span>{{ $campaign['name'] }}</span>
                            </label>
                        @empty
                            <div class="ql-check-empty">No Meta campaigns found for selected period.</div>
                        @endforelse
                    </div>
                </div>
            </div>
            <label>Quick Period
                <select name="period" class="ql-input">
                    @foreach($periodOptions as $periodKey => $periodLabel)
                        <option value="{{ $periodKey }}" @selected($filters['period'] === $periodKey)>{{ $periodLabel }}</option>
                    @endforeach
                </select>
            </label>
            <label>Month
                <input type="month" name="month" value="{{ $filters['month'] }}" class="ql-input">
            </label>
            <label>From
                <input type="date" name="from" value="{{ request('from') ? $filters['from']->toDateString() : '' }}" class="ql-input">
            </label>
            <label>To
                <input type="date" name="to" value="{{ request('to') ? $filters['to']->toDateString() : '' }}" class="ql-input">
            </label>
            <label>User
                <select name="user_id" class="ql-input">
                    <option value="">All Users</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}" @selected((int) $filters['user_id'] === (int) $user->id)>{{ $user->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>Outcome Bucket
                <select name="bucket" class="ql-input">
                    <option value="">All Buckets</option>
                    @foreach($bucketOptions as $bucketKey => $bucketLabel)
                        <option value="{{ $bucketKey }}" @selected($filters['bucket'] === $bucketKey)>{{ $bucketLabel }}</option>
                    @endforeach
                </select>
            </label>
            <label>Evidence Rows
                <input type="number" name="sample_limit" min="5" max="100" value="{{ $filters['sample_limit'] }}" class="ql-input">
            </label>
            <button class="ql-btn primary" type="submit"><i class="fas fa-filter"></i> Generate Report</button>
            <a href="{{ route('data-intelligence.lead-quality', array_filter(['source' => array_key_first($sourceOptions) ?: null])) }}" class="ql-btn"><i class="fas fa-rotate-left"></i> Reset</a>
            <div class="ml-auto text-sm text-slate-500">Period: <b>{{ $filters['from']->format('d M Y') }}</b> to <b>{{ $filters['to']->format('d M Y') }}</b></div>
        </form>

        <div class="ql-grid kpi">
            <div class="ql-panel ql-card">
                <div class="ql-kpi-label">Total Leads</div>
                <div class="ql-kpi-value">{{ number_format($summary['total']) }}</div>
                <div class="ql-kpi-sub">{{ $isAllSourceReport ? 'All source leads' : $sourceLabel . ' source leads' }}</div>
            </div>
            <div class="ql-panel ql-card">
                <div class="ql-kpi-label">Interested</div>
                <div class="ql-kpi-value text-emerald-700">{{ number_format($summary['interested']) }}</div>
                <div class="ql-kpi-sub">{{ $pct($summary['interested']) }}% of total</div>
            </div>
            <div class="ql-panel ql-card">
                <div class="ql-kpi-label">Not Interested</div>
                <div class="ql-kpi-value text-red-700">{{ number_format($summary['not_interested']) }}</div>
                <div class="ql-kpi-sub">{{ $pct($summary['not_interested']) }}% of total</div>
            </div>
            <div class="ql-panel ql-card">
                <div class="ql-kpi-label">Junk / Invalid</div>
                <div class="ql-kpi-value text-red-700">{{ number_format($summary['junk']) }}</div>
                <div class="ql-kpi-sub">{{ $pct($summary['junk']) }}% of total</div>
            </div>
            <div class="ql-panel ql-card">
                <div class="ql-kpi-label">CNP</div>
                <div class="ql-kpi-value text-amber-700">{{ number_format($summary['cnp']) }}</div>
                <div class="ql-kpi-sub">{{ $pct($summary['cnp']) }}% not reachable</div>
            </div>
            <div class="ql-panel ql-card">
                <div class="ql-kpi-label">Quality Score</div>
                <div class="ql-kpi-value text-emerald-800">{{ $summary['quality_score'] }}%</div>
                <div class="ql-kpi-sub">Interested / qualified only</div>
            </div>
        </div>

        <section class="ql-panel ql-section">
            <div class="ql-two">
                <div class="ql-score">
                    <div class="ql-ring" style="--score:{{ $summary['quality_score'] }}"><span>{{ $summary['quality_score'] }}%</span></div>
                    <div>
                        <h2>Lead Quality Summary</h2>
                        <p>Total {{ number_format($summary['total']) }} leads me se {{ number_format($summary['good_leads']) }} usable leads hain, aur {{ number_format($summary['poor_leads']) }} leads vendor review/replacement bucket me aate hain.</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <span class="ql-badge good">Usable {{ $summary['quality_score'] }}%</span>
                            <span class="ql-badge bad">Poor {{ $summary['poor_quality_rate'] }}%</span>
                            <span class="ql-badge info">Actioned {{ $summary['actioned_rate'] }}%</span>
                        </div>
                    </div>
                </div>
                <div class="ql-note">
                    <b>Vendor note:</b> This report is generated from CRM call outcomes submitted by users. {{ $isAllSourceReport ? 'Leads' : $sourceLabel . ' leads' }} marked Not Interested, Junk/Invalid and repeated CNP should be treated as low-quality enquiries and reviewed for replacement or credit adjustment.
                </div>
            </div>
        </section>

        @if($isAllSourceReport)
            <section class="ql-panel ql-section">
                <div class="ql-section-head">
                    <div><h2>Source Wise Quality</h2><p>Interested % sirf Interested / Qualified leads par based hai; CNP, call later, junk aur not interested exclude hain.</p></div>
                    <div class="ql-funnel-legend ql-no-print">
                        <span><i class="ql-dot raw"></i> Raw outcome</span>
                        <span><i class="ql-dot pipe"></i> Interested pipeline</span>
                        <span><i class="ql-dot bad"></i> Low quality</span>
                    </div>
                </div>
                <div class="ql-table-scroll">
                <table class="ql-table funnel">
                    <thead>
                        <tr class="ql-funnel-group">
                            <th class="ql-funnel-source" rowspan="2">Source</th>
                            <th rowspan="2">Total</th>
                            <th colspan="2" class="pipe">Qualified</th>
                            <th colspan="4" class="raw">Raw Outcome</th>
                            <th colspan="5" class="pipe">Interested Pipeline</th>
                        </tr>
                        <tr>
                            <th>Interested</th>
                            <th>Rate</th>
                            <th>CNP</th>
                            <th>Call Later</th>
                            <th>Junk</th>
                            <th>Not Interested</th>
                            <th>Interested Follow-up</th>
                            <th>Site Visit</th>
                            <th>Visit Follow-up</th>
                            <th>Meeting</th>
                            <th>Closed</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($sourceBreakdown as $row)
                        <tr>
                            <td class="ql-funnel-source"><b>{{ $row['source'] }}</b></td>
                            <td><span class="ql-count neutral">{{ number_format($row['total']) }}</span></td>
                            <td><span class="ql-count good">{{ number_format($row['interested']) }}</span></td>
                            <td><span class="ql-badge {{ $row['interested_rate'] >= 40 ? 'good' : 'warn' }}">{{ $row['interested_rate'] }}%</span></td>
                            <td><span class="ql-count warn">{{ number_format($row['cnp']) }}</span></td>
                            <td><span class="ql-count info">{{ number_format($row['call_later']) }}</span></td>
                            <td><span class="ql-count bad">{{ number_format($row['junk']) }}</span></td>
                            <td><span class="ql-count bad">{{ number_format($row['not_interested']) }}</span></td>
                            <td><span class="ql-count info">{{ number_format($row['interested_follow_up']) }}</span></td>
                            <td><span class="ql-count good">{{ number_format($row['site_visit']) }}</span></td>
                            <td><span class="ql-count info">{{ number_format($row['site_visit_followup']) }}</span></td>
                            <td><span class="ql-count good">{{ number_format($row['meeting']) }}</span></td>
                            <td><span class="ql-count good">{{ number_format($row['closed']) }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="13" class="text-center text-slate-500">No source-wise data found.</td></tr>
                    @endforelse
                    </tbody>
                    @if($sourceBreakdown->isNotEmpty())
                        <tfoot>
                            <tr class="ql-funnel-total">
                                <td class="ql-funnel-source">Total</td>
                                <td>{{ number_format($sourceTotals['total']) }}</td>
                                <td class="text-emerald-700">{{ number_format($sourceTotals['interested']) }}</td>
                                <td><span class="ql-badge {{ $sourceTotals['interested_rate'] >= 40 ? 'good' : 'warn' }}">{{ $sourceTotals['interested_rate'] }}%</span></td>
                                <td class="text-amber-700">{{ number_format($sourceTotals['cnp']) }}</td>
                                <td class="text-blue-700">{{ number_format($sourceTotals['call_later']) }}</td>
                                <td class="text-red-700">{{ number_format($sourceTotals['junk']) }}</td>
                                <td class="text-red-700">{{ number_format($sourceTotals['not_interested']) }}</td>
                                <td class="text-blue-700">{{ number_format($sourceTotals['interested_follow_up']) }}</td>
                                <td class="text-emerald-700">{{ number_format($sourceTotals['site_visit']) }}</td>
                                <td class="text-blue-700">{{ number_format($sourceTotals['site_visit_followup']) }}</td>
                                <td class="text-emerald-700">{{ number_format($sourceTotals['meeting']) }}</td>
                                <td class="text-emerald-700">{{ number_format($sourceTotals['closed']) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
                </div>
            </section>
        @endif

        <div class="ql-two">
            <section class="ql-panel ql-section">
                <div class="ql-section-head">
                    <div><h2>Outcome Breakdown</h2><p>User submitted outcomes from telecalling/calling tasks.</p></div>
                </div>
                <table class="ql-table">
                    <thead><tr><th>Outcome</th><th>Count</th><th>%</th><th>Share</th></tr></thead>
                    <tbody>
                    @forelse($outcomes as $label => $count)
                        <tr>
                            <td>{{ $label }}</td>
                            <td><b>{{ number_format($count) }}</b></td>
                            <td>{{ $pct($count) }}%</td>
                            <td><div class="ql-bar"><span style="--w:{{ $pct($count) }}%"></span></div></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-slate-500">No outcome data found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </section>

            <section class="ql-panel ql-section">
                <div class="ql-section-head">
                    <div><h2>Lead List Status Breakdown</h2><p>Lead list jaisa operational status: CNP aur Call Later / Follow-up New se alag count hote hain.</p></div>
                </div>
                <table class="ql-table">
                    <thead><tr><th>Status</th><th>Count</th><th>%</th><th>Share</th></tr></thead>
                    <tbody>
                    @forelse($statusBreakdown as $label => $count)
                        <tr>
                            <td>{{ $label }}</td>
                            <td><b>{{ number_format($count) }}</b></td>
                            <td>{{ $pct($count) }}%</td>
                            <td><div class="ql-bar info"><span style="--w:{{ $pct($count) }}%"></span></div></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-slate-500">No status data found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </section>
        </div>

        <section class="ql-panel ql-section">
            <div class="ql-section-head">
                <div><h2>Daily Quality Trend</h2><p>Day-wise received leads and low quality buckets.</p></div>
            </div>
            @php
                $maxTrendTotal = max(1, (int) $dailyTrend->max('total'));
            @endphp
            <div class="ql-trend-layout">
                <div class="ql-trend-card">
                    <div class="ql-trend-card-head">
                        <h3>Daily Numbers</h3>
                        <span>{{ $dailyTrend->count() }} days</span>
                    </div>
                    <div class="ql-trend-table-wrap">
                        <table class="ql-table">
                            <thead><tr><th>Date</th><th>Total</th><th>Interested</th><th>Not Interested</th><th>Junk</th><th>CNP</th><th>Call Later</th><th>Pending</th></tr></thead>
                            <tbody>
                            @foreach($dailyTrend as $day)
                                <tr>
                                    <td>{{ $day['date']->format('d M Y') }}</td>
                                    <td><b>{{ $day['total'] }}</b></td>
                                    <td class="text-emerald-700">{{ $day['interested'] }}</td>
                                    <td class="text-red-700">{{ $day['not_interested'] }}</td>
                                    <td class="text-red-700">{{ $day['junk'] }}</td>
                                    <td class="text-amber-700">{{ $day['cnp'] }}</td>
                                    <td class="text-blue-700">{{ $day['call_later'] ?? $day['follow_up'] ?? 0 }}</td>
                                    <td class="text-slate-500">{{ $day['pending'] }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="ql-trend-card">
                    <div class="ql-trend-card-head">
                        <h3>Low Quality Trend</h3>
                        <span>Not Interested + Junk + CNP</span>
                    </div>
                    <div class="ql-trend-chart">
                        @foreach($dailyTrend as $day)
                            @php
                                $dayTotal = max(1, (int) $day['total']);
                                $trackWidth = max(2, round(((int) $day['total'] / $maxTrendTotal) * 100, 1));
                                $notInterestedWidth = round(((int) $day['not_interested'] / $dayTotal) * 100, 1);
                                $junkWidth = round(((int) $day['junk'] / $dayTotal) * 100, 1);
                                $cnpWidth = round(((int) $day['cnp'] / $dayTotal) * 100, 1);
                                $lowQualityTotal = (int) $day['not_interested'] + (int) $day['junk'] + (int) $day['cnp'];
                            @endphp
                            <div class="ql-trend-row">
                                <div class="ql-trend-date">{{ $day['date']->format('d M') }}</div>
                                <div class="ql-trend-track" title="{{ $day['date']->format('d M Y') }}: {{ $lowQualityTotal }} low quality / {{ $day['total'] }} total" style="width:{{ $trackWidth }}%">
                                    <span class="ql-trend-segment not-interested" style="width:{{ $notInterestedWidth }}%"></span>
                                    <span class="ql-trend-segment junk" style="width:{{ $junkWidth }}%"></span>
                                    <span class="ql-trend-segment cnp" style="width:{{ $cnpWidth }}%"></span>
                                </div>
                                <div class="ql-trend-total">{{ $lowQualityTotal }}</div>
                            </div>
                        @endforeach
                    </div>
                    <div class="ql-trend-legend">
                        <span><i style="background:#d9534f"></i>Not Interested</span>
                        <span><i style="background:#f4b183"></i>Junk</span>
                        <span><i style="background:#ffd966"></i>CNP</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="ql-panel ql-section">
            <div class="ql-section-head">
                <div><h2>User Outcome Performance</h2><p>Assigned user wise lead quality summary.</p></div>
            </div>
            <table class="ql-table">
                <thead><tr><th>User</th><th>Total</th><th>Interested</th><th>Not Interested</th><th>Junk</th><th>CNP</th><th>Call Later</th><th>Quality Score</th></tr></thead>
                <tbody>
                @forelse($ownerBreakdown as $owner)
                    <tr>
                        <td><b>{{ $owner['owner'] }}</b></td>
                        <td>{{ $owner['total'] }}</td>
                        <td class="text-emerald-700">{{ $owner['interested'] }}</td>
                        <td class="text-red-700">{{ $owner['not_interested'] }}</td>
                        <td class="text-red-700">{{ $owner['junk'] }}</td>
                        <td class="text-amber-700">{{ $owner['cnp'] }}</td>
                        <td class="text-blue-700">{{ $owner['call_later'] }}</td>
                        <td><span class="ql-badge {{ $owner['quality_score'] >= 50 ? 'good' : 'warn' }}">{{ $owner['quality_score'] }}%</span></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-slate-500">No assigned-user data found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>

        @if($isMetaSource && in_array($filters['meta_view'], ['form', 'campaign'], true))
            <section class="ql-panel ql-section">
                <div class="ql-section-head">
                    <div>
                        <h2>{{ $filters['meta_view'] === 'form' ? 'Meta Form Quality Breakdown' : 'Meta Campaign Quality Breakdown' }}</h2>
                        <p>{{ $filters['meta_view'] === 'form' ? 'Form wise Meta lead quality based on CRM user outcomes.' : 'Campaign wise Meta lead quality based on CRM user outcomes.' }}</p>
                    </div>
                </div>
                <table class="ql-table">
                    <thead>
                        <tr>
                            <th>{{ $filters['meta_view'] === 'form' ? 'Form Name' : 'Campaign Name' }}</th>
                            <th>Total</th>
                            <th>Interested</th>
                            <th>Not Interested</th>
                            <th>Junk</th>
                            <th>CNP</th>
                            <th>Call Later</th>
                            <th>Pending</th>
                            <th>Quality Score</th>
                            <th class="ql-no-print">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($metaBreakdown as $row)
                        <tr>
                            <td><b>{{ $row['label'] }}</b></td>
                            <td>{{ number_format($row['total']) }}</td>
                            <td class="text-emerald-700">{{ number_format($row['interested']) }}</td>
                            <td class="text-red-700">{{ number_format($row['not_interested']) }}</td>
                            <td class="text-red-700">{{ number_format($row['junk']) }}</td>
                            <td class="text-amber-700">{{ number_format($row['cnp']) }}</td>
                            <td class="text-blue-700">{{ number_format($row['call_later'] ?? $row['follow_up'] ?? 0) }}</td>
                            <td class="text-slate-500">{{ number_format($row['pending']) }}</td>
                            <td><span class="ql-badge {{ $row['quality_score'] >= 50 ? 'good' : 'warn' }}">{{ $row['quality_score'] }}%</span></td>
                            <td class="ql-no-print">
                                @if($row['query_value'])
                                    @php
                                        $metaFilterQuery = request()->except(['fb_form_id', 'fb_form_ids', 'campaign_id', 'campaign_ids']);
                                        $metaFilterQuery[$row['query_key'] === 'fb_form_id' ? 'fb_form_ids' : 'campaign_ids'] = [$row['query_value']];
                                        $metaFilterQuery['meta_view'] = $filters['meta_view'];
                                    @endphp
                                    <a class="ql-btn" href="{{ route('data-intelligence.lead-quality', $metaFilterQuery) }}">Filter</a>
                                @else
                                    <span class="text-xs text-slate-500">Unmapped</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="text-center text-slate-500">No Meta breakdown data found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </section>
        @endif

        <section class="ql-panel ql-section">
            <div class="ql-section-head">
                <div><h2>Evidence Rows</h2><p>Filtered rows for vendor review and internal quality checks.</p></div>
                <span class="ql-badge muted">{{ $evidenceRows->count() }} rows</span>
            </div>
            <table class="ql-table">
                <thead>
                    <tr>
                        <th>Lead</th><th>Phone</th><th>Created</th><th>User</th><th>Status</th><th>Bucket</th><th>Outcome</th>@if($isMetaSource)<th>Meta Context</th>@endif<th>Remark / Evidence</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($evidenceRows as $row)
                    @php
                        $lead = $row['lead'];
                    @endphp
                    <tr>
                        <td><b>{{ $lead->name }}</b><div class="text-xs text-slate-500">ID {{ $lead->id }}</div></td>
                        <td>{{ $lead->phone }}</td>
                        <td>{{ $lead->created_at->format('d M Y, h:i A') }}</td>
                        <td>{{ $row['owner'] }}</td>
                        <td>{{ $row['status_label'] }}</td>
                        <td><span class="ql-badge {{ $bucketClass[$row['bucket']] ?? 'muted' }}">{{ $row['bucket_label'] }}</span></td>
                        <td>{{ $row['outcome_label'] }}</td>
                        @if($isMetaSource)
                            <td>
                                <b>{{ $row['meta_form_name'] }}</b>
                                <div class="text-xs text-slate-500">{{ $row['meta_campaign_name'] }}</div>
                                @if($row['meta_adset_name'] || $row['meta_ad_name'])
                                    <div class="text-xs text-slate-500">{{ $row['meta_adset_name'] }}{{ $row['meta_adset_name'] && $row['meta_ad_name'] ? ' / ' : '' }}{{ $row['meta_ad_name'] }}</div>
                                @endif
                            </td>
                        @endif
                        <td>{{ $row['remarks'] ?: 'No remark captured' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $isMetaSource ? 9 : 8 }}" class="text-center text-slate-500">No leads found for selected filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>

        @foreach([
            'interested' => 'Interested / Qualified Evidence',
            'not_interested' => 'Not Interested Evidence',
            'junk' => 'Junk / Invalid Evidence',
            'cnp' => 'CNP / Not Reachable Evidence',
        ] as $bucket => $title)
            <section class="ql-panel ql-section">
                <div class="ql-section-head">
                    <div><h2>{{ $title }}</h2><p>Sample rows for vendor review. Increase evidence rows filter if more examples are needed.</p></div>
                    <span class="ql-badge {{ $bucketClass[$bucket] ?? 'muted' }}">{{ $samples[$bucket]->count() }} rows</span>
                </div>
                <table class="ql-table">
                    <thead>
                        <tr>
                            <th>Lead</th><th>Phone</th><th>Created</th><th>User</th><th>Status</th><th>Outcome</th><th>Remark / Evidence</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($samples[$bucket] as $row)
                        @php
                            $lead = $row['lead'];
                        @endphp
                        <tr>
                            <td><b>{{ $lead->name }}</b><div class="text-xs text-slate-500">ID {{ $lead->id }}</div></td>
                            <td>{{ $lead->phone }}</td>
                            <td>{{ $lead->created_at->format('d M Y, h:i A') }}</td>
                            <td>{{ $row['owner'] }}</td>
                            <td>{{ $row['status_label'] }}</td>
                            <td><span class="ql-badge {{ $bucketClass[$row['bucket']] ?? 'muted' }}">{{ $row['outcome_label'] }}</span></td>
                            <td>{{ $row['remarks'] ?: 'No remark captured' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-slate-500">No rows in this bucket.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </section>
        @endforeach

        <section class="ql-panel ql-section">
            <div class="ql-section-head">
                <div><h2>Recommendation for {{ $sourceLabel }}</h2><p>Points that can be shared with vendor for corrective action.</p></div>
            </div>
            <ul class="ql-list">
                @foreach($recommendations as $recommendation)
                    <li>{{ $recommendation }}</li>
                @endforeach
            </ul>
        </section>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const metaSources = new Set(['meta', 'meta_awareness']);

    document.querySelectorAll('form.ql-filter').forEach((form) => {
        const sourceSelect = form.querySelector('[data-source-select]');
        const metaControls = form.querySelector('[data-meta-controls]');
        const metaViewWrap = form.querySelector('[data-meta-view-wrap]');
        const metaViewSelect = form.querySelector('[data-meta-view-select]');
        const panels = form.querySelectorAll('[data-meta-panel]');

        const updateCounts = () => {
            form.querySelectorAll('.ql-check-group').forEach((group) => {
                const checked = group.querySelectorAll('input[type="checkbox"]:checked').length;
                const total = group.querySelectorAll('input[type="checkbox"]').length;
                const count = group.querySelector('[data-selected-count]');
                const title = group.querySelector('.ql-check-title')?.textContent?.trim() || 'Options';

                if (!count) {
                    return;
                }

                count.textContent = checked === 0
                    ? `All ${title.toLowerCase()} selected`
                    : `${checked} of ${total} selected`;
            });
        };

        const syncMetaControls = () => {
            const isMeta = metaSources.has(sourceSelect?.value || '');
            const view = metaViewSelect?.value || 'all';

            if (metaControls) {
                metaControls.style.display = isMeta ? 'block' : 'none';
            }

            if (metaViewWrap) {
                metaViewWrap.style.display = isMeta ? 'grid' : 'none';
            }

            panels.forEach((panel) => {
                panel.style.display = isMeta && panel.dataset.metaPanel === view ? 'block' : 'none';
            });

            updateCounts();
        };

        sourceSelect?.addEventListener('change', syncMetaControls);
        metaViewSelect?.addEventListener('change', syncMetaControls);

        form.querySelectorAll('[data-check-all]').forEach((button) => {
            button.addEventListener('click', () => {
                button.closest('.ql-check-group')?.querySelectorAll('input[type="checkbox"]').forEach((input) => {
                    input.checked = true;
                });
                updateCounts();
            });
        });

        form.querySelectorAll('[data-check-clear]').forEach((button) => {
            button.addEventListener('click', () => {
                button.closest('.ql-check-group')?.querySelectorAll('input[type="checkbox"]').forEach((input) => {
                    input.checked = false;
                });
                updateCounts();
            });
        });

        form.querySelectorAll('[data-check-search]').forEach((input) => {
            input.addEventListener('input', () => {
                const term = input.value.trim().toLowerCase();
                input.closest('.ql-check-group')?.querySelectorAll('.ql-check-option').forEach((option) => {
                    option.style.display = option.textContent.toLowerCase().includes(term) ? 'flex' : 'none';
                });
            });
        });

        form.addEventListener('change', (event) => {
            if (event.target.matches('.ql-check-option input')) {
                updateCounts();
            }
        });

        syncMetaControls();
    });
});
</script>
@endsection

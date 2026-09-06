@extends(auth()->check() && (auth()->user()->isAssistantSalesManager() || auth()->user()->isSeniorManager() || auth()->user()->isSalesManager()) ? 'sales-manager.layout' : 'layouts.app')

@section('title', 'Employees')
@section('page-title', '')
@section('page-subtitle', '')
@section('hide-app-header', '1')

@push('styles')
<style>
    .em {
        --green: var(--primary-color, #1B5E20);
        --green-mid: var(--secondary-color, #2E7D32);
        --green-deep: var(--accent-color, #145226);
        --green-light: rgba(6, 58, 28, 0.08);
        --green-xlight: rgba(6, 58, 28, 0.04);
        --green-ink: var(--text-color, #0E3B17);
        --red: #C62828;
        --red-light: #FFEBEE;
        --amber: #E65100;
        --amber-light: #FFF3E0;
        --violet: #6A1B9A;
        --violet-light: #F3E5F5;
        --blue: #1565C0;
        --blue-light: #E3F2FD;
        --slate: #546E7A;
        --slate-light: #ECEFF1;
        --text: var(--text-color, #112418);
        --sub: var(--link-color, #5F6F66);
        --border: #DCE5DF;
        --bg: #EEF3EF;
        --white: #FFFFFF;

        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
        display: flex;
        flex-direction: column;
        gap: 16px;
        padding: 8px 0 36px;
        width: 100%;
        max-width: none;
        margin: 0;
        color: var(--text);
    }

    /* ── TOPBAR ── */
    .em-topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 18px 24px;
        min-height: 78px;
        background:
            radial-gradient(circle at top right, rgba(93, 202, 165, 0.14), transparent 32%),
            linear-gradient(135deg, #0F261D 0%, #163224 100%);
        border: 1px solid rgba(255,255,255,0.08);
        border-radius: 20px;
        box-shadow: 0 20px 40px rgba(12, 28, 20, 0.16);
        gap: 16px;
        flex-wrap: wrap;
        flex-shrink: 0;
    }
    .em-topbar { display: none !important; }

    .em-topbar-left { display: flex; align-items: center; gap: 20px; }
    .em-logo {
        display: flex; align-items: center; gap: 10px; text-decoration: none; color: #fff;
    }
    .em-logo-mark {
        width: 36px; height: 36px; background: linear-gradient(135deg, #3DAA63, #1E6E2E);
        border-radius: 12px; display: flex; align-items: center; justify-content: center;
        color: #fff; font-size: 14px; font-weight: 900;
        box-shadow: 0 10px 18px rgba(16, 58, 24, 0.32);
    }
    .em-logo-text { font-size: 17px; font-weight: 800; letter-spacing: -.03em; color: #fff; }
    .em-topbar-sep { width: 1px; height: 22px; background: rgba(255,255,255,.14); }
    .em-topbar-title { font-size: 13px; font-weight: 700; color: rgba(255,255,255,.72); letter-spacing: .04em; text-transform: uppercase; }

    .em-topbar-right { display: flex; align-items: center; gap: 14px; }
    .em-clock {
        font-size: 12px; font-weight: 700; color: rgba(255,255,255,.68); font-variant-numeric: tabular-nums;
        padding: 10px 14px; border-radius: 12px; background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.08);
    }
    .em-clock span { color: #fff; font-weight: 800; }
    .em-user { display: flex; align-items: center; gap: 8px; text-decoration: none; }
    .em-user-avatar {
        width: 34px; height: 34px; border-radius: 50%; background: rgba(255,255,255,.12);
        color: #fff; display: flex; align-items: center; justify-content: center;
        font-size: 11px; font-weight: 800; border: 1px solid rgba(255,255,255,.08);
    }
    .em-user-name { font-size: 12px; font-weight: 700; color: #fff; line-height: 1.2; }
    .em-user-role { font-size: 10px; color: rgba(255,255,255,.6); font-weight: 600; }
    .em-logout {
        display: inline-flex; align-items: center; gap: 5px; padding: 9px 14px;
        border-radius: 10px; border: 1px solid rgba(255,255,255,.08); background: rgba(255,255,255,.08); color: #fff;
        font-size: 12px; font-weight: 700; cursor: pointer; text-decoration: none; transition: all .15s;
    }
    .em-logout:hover { background: #C62828; color: #fff; border-color: #C62828; }
    .em-logout svg { width: 12px; height: 12px; }

    /* ── PAGE WRAPPER ── */
    .em-page { padding: 0 8px; }

    /* ── HERO CARD ── */
    .em-hero {
        background:
            radial-gradient(circle at top right, rgba(46, 125, 50, 0.10), transparent 30%),
            linear-gradient(180deg, #FFFFFF 0%, #FBFDFB 100%);
        border: 1px solid var(--border);
        border-radius: 22px;
        box-shadow: 0 12px 28px rgba(17, 36, 24, 0.06);
        overflow: hidden;
    }
    .em-hero-inner {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        gap: 24px;
        padding: 22px 30px 18px;
        flex-wrap: wrap;
    }
    .em-eyebrow {
        font-size: 10.5px; font-weight: 800; letter-spacing: .16em;
        text-transform: uppercase; color: var(--green-mid); margin-bottom: 10px;
    }
    .em-title {
        font-size: 32px; font-weight: 900; letter-spacing: 0;
        color: var(--green-ink); line-height: 1;
        max-width: 760px;
        font-family: 'Poppins', 'Inter', sans-serif;
    }
    .em-desc {
        font-size: 14px; color: var(--sub); font-weight: 600;
        margin-top: 12px; max-width: 760px; line-height: 1.7;
    }

    /* ── BUTTONS ── */
    .em-btn {
        display: inline-flex; align-items: center; gap: 7px;
        padding: 9px 18px; border-radius: 10px; border: none;
        font-size: 13px; font-weight: 700; cursor: pointer;
        text-decoration: none; transition: all .18s ease;
        font-family: 'Inter', sans-serif; white-space: nowrap;
    }
    .em-btn svg { width: 14px; height: 14px; }
    .em-btn-primary {
        background: linear-gradient(135deg, #185C36, #0F4D2C);
        color: #fff; box-shadow: 0 4px 12px rgba(27,94,32,0.22);
    }
    .em-btn-primary:hover {
        background: var(--green-deep);
        box-shadow: 0 8px 20px rgba(27,94,32,0.3);
        transform: translateY(-1px);
    }
    .em-btn-secondary {
        background: #fff; color: var(--green-ink);
        border: 1.5px solid #CAD7CF;
    }
    .em-btn-secondary:hover {
        border-color: var(--green); color: var(--green);
        background: var(--green-xlight);
    }
    .em-btn-sm {
        padding: 6px 14px; font-size: 12px; font-weight: 700;
        border-radius: 8px; border: 1px solid var(--border);
        background: var(--white); color: var(--text);
        text-decoration: none; display: inline-flex; align-items: center; gap: 6px;
        transition: all .15s; font-family: 'Inter', sans-serif;
    }
    .em-btn-sm:hover {
        border-color: var(--green); color: var(--green);
        background: var(--green-xlight);
    }
    .em-btn-sm svg { width: 12px; height: 12px; }
    .em-btn-sm-primary {
        background: linear-gradient(135deg, #185C36, #0F4D2C);
        border-color: var(--green); color: #fff;
        box-shadow: 0 3px 10px rgba(27,94,32,.18);
    }
    .em-btn-sm-primary:hover {
        background: var(--green-deep);
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(27,94,32,.25);
    }

    /* ── STATS ROW ── */
    .em-stats {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        padding: 0 30px 22px;
    }
    .em-stat {
        background: linear-gradient(180deg, #FFFFFF 0%, #F8FBF9 100%);
        border: 1px solid #E0E9E2;
        border-radius: 16px;
        padding: 14px 16px;
        position: relative;
        overflow: hidden;
        transition: all .2s;
        box-shadow: 0 8px 18px rgba(17, 36, 24, 0.04);
    }
    .em-stat:hover {
        box-shadow: 0 6px 20px rgba(0,0,0,0.07);
        transform: translateY(-2px);
        border-color: #c8c8c8;
    }
    .em-stat-label {
        font-size: 10px; font-weight: 800; letter-spacing: .12em;
        text-transform: uppercase; color: #6D7E74; margin-bottom: 10px;
    }
    .em-stat-value {
        font-size: 28px; font-weight: 900; letter-spacing: 0;
        color: var(--green-ink); line-height: 1;
    }
    .em-stat-note {
        font-size: 11px; color: #829187; font-weight: 600;
        margin-top: 6px; line-height: 1.4;
    }

    /* ── FILTER CARD ── */
    .em-filter-card {
        background: var(--white);
        border: 1px solid var(--border);
        border-radius: 18px;
        box-shadow: 0 12px 28px rgba(17, 36, 24, 0.06);
        overflow: hidden;
    }
    .em-filter-head {
        display: flex; align-items: center; justify-content: space-between;
        gap: 16px; padding: 16px 20px;
        border-bottom: 1px solid var(--border); background: linear-gradient(180deg, #FCFDFC 0%, #F5F8F6 100%);
        flex-wrap: wrap;
    }
    .em-filter-title { font-size: 18px; font-weight: 850; color: var(--green-ink); letter-spacing: 0; font-family: 'Inter', sans-serif; }
    .em-filter-sub { font-size: 13px; color: var(--sub); font-weight: 600; margin-top: 6px; line-height: 1.6; max-width: 860px; }
    .em-filter-meta {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 7px 12px; border-radius: 999px;
        border: 1px solid var(--border); background: var(--white);
        font-size: 12px; font-weight: 800; color: var(--green-ink);
    }
    .em-filter-body { padding: 16px 20px 18px; }
    .em-filter-row {
        display: grid;
        grid-template-columns: minmax(240px, 1.35fr) repeat(4, minmax(136px, 1fr)) auto auto;
        gap: 12px;
        align-items: end;
    }
    .em-field { display: flex; flex-direction: column; gap: 7px; }
    .em-field-label {
        font-size: 10px; font-weight: 800; letter-spacing: .12em;
        text-transform: uppercase; color: #6D7E74;
    }
    .em-input, .em-select {
        width: 100%; min-height: 42px; padding: 0 13px;
        border: 1px solid #D8E1DB; border-radius: 10px;
        font-size: 13px; font-weight: 700; color: var(--green-ink);
        background: #F9FBFA; outline: none;
        font-family: 'Inter', sans-serif; transition: border-color .15s;
    }
    .em-input::placeholder { color: #9AA69F; font-weight: 600; }
    .em-input:focus, .em-select:focus {
        border-color: var(--green);
        box-shadow: 0 0 0 4px rgba(27,94,32,.08);
        background: #fff;
    }
    .em-select {
        cursor: pointer; appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%236B7280' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
        background-repeat: no-repeat; background-position: right 12px center;
        padding-right: 34px;
    }

    /* ── RESULT GRID ── */
    .em-result-card {
        background: linear-gradient(180deg, #FFFFFF 0%, #FCFDFC 100%);
        border: 1px solid var(--border);
        border-radius: 18px;
        box-shadow: 0 12px 28px rgba(17, 36, 24, 0.06);
        overflow: hidden;
    }
    .em-result-head {
        display: flex; align-items: center; justify-content: space-between;
        padding: 14px 20px; border-bottom: 1px solid var(--border);
        background: linear-gradient(180deg, #FCFDFC 0%, #F5F8F6 100%); gap: 12px; flex-wrap: wrap;
    }
    .em-result-title {
        font-size: 12px; font-weight: 800; letter-spacing: .12em;
        text-transform: uppercase; color: #5B6C62;
    }
    .em-result-count {
        display: inline-flex; align-items: center; justify-content: center;
        min-width: 34px; height: 34px; padding: 0 12px;
        border-radius: 999px; background: #EFF7F0; color: var(--green-ink);
        font-size: 12px; font-weight: 800;
    }

    /* ── TABLE ── */
    .em-table-wrap { overflow-x: auto; }
    .em-table {
        width: 100%; min-width: 1080px; border-collapse: collapse;
    }
    .em-table th {
        padding: 12px 14px; font-size: 10px; font-weight: 800;
        letter-spacing: .12em; text-transform: uppercase; color: #718177;
        text-align: left; border-bottom: 1px solid var(--border);
        background: #FAFCFB; white-space: nowrap;
        position: sticky; top: 0; z-index: 1;
    }
    .em-table th:first-child { background: #F6FAF7; }
    .em-table td {
        padding: 16px 14px; border-bottom: 1px solid #EEF2EF;
        vertical-align: top; font-size: 13px;
    }
    .em-table tbody tr { transition: background .12s; }
    .em-table tbody tr:hover { background: #F8FBF8; }
    .em-table tbody tr:last-child td { border-bottom: none; }
    .em-table td:first-child { background: var(--white); }
    .em-table tbody tr:nth-child(odd) td { background: #FFFFFF; }
    .em-table tbody tr:nth-child(even) td { background: #FBFDFC; }
    .em-table tbody tr:hover td { background: #F4F8F5; }

    /* ── BADGES / PILLS ── */
    .em-badge {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 6px 11px; border-radius: 999px;
        font-size: 11px; font-weight: 800; letter-spacing: .02em;
        white-space: nowrap;
    }
    .em-badge.green { background: var(--green-light); color: var(--green); }
    .em-badge.amber { background: var(--amber-light); color: var(--amber); }
    .em-badge.red { background: var(--red-light); color: var(--red); }
    .em-badge.violet { background: var(--violet-light); color: var(--violet); }
    .em-badge.blue { background: var(--blue-light); color: var(--blue); }
    .em-badge.slate { background: var(--slate-light); color: var(--slate); }

    /* ── EMPLOYEE PROFILE CELL ── */
    .em-profile-cell { display: flex; flex-direction: column; gap: 6px; }
    .em-name-row { display: flex; align-items: center; gap: 10px; }
    .em-avatar {
        width: 44px; height: 44px; border-radius: 14px;
        background: linear-gradient(135deg, rgba(6,58,28,.12), rgba(6,58,28,.05));
        color: var(--green-ink); display: flex; align-items: center; justify-content: center;
        font-size: 13px; font-weight: 900; flex-shrink: 0;
        border: 1px solid rgba(6,58,28,.08);
    }
    .em-name { font-size: 15px; font-weight: 850; color: var(--green-ink); letter-spacing: 0; }
    .em-id-tag {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 4px 9px; border-radius: 6px;
        background: #F8FBF8; border: 1px solid var(--border);
        font-size: 10.5px; font-weight: 800; color: #5C6C63;
    }
    .em-contact-cell { display: flex; flex-direction: column; gap: 4px; }
    .em-contact-line { font-size: 12px; font-weight: 600; color: var(--sub); }
    .em-stack-cell { display: flex; flex-direction: column; gap: 6px; }
    .em-stack-row { display: flex; flex-direction: column; gap: 1px; }
    .em-key-tag { font-size: 9.5px; font-weight: 800; color: #8A988F; text-transform: uppercase; letter-spacing: .09em; }
    .em-val { font-size: 12.5px; font-weight: 700; color: var(--green-ink); line-height: 1.5; }
    .em-val.muted { color: var(--sub); font-weight: 600; }
    .em-metric-stack { display:flex; flex-direction:column; gap:6px; }
    .em-actions-col { display:flex; flex-direction:column; gap:8px; min-width:110px; }
    .em-btn-sm-soft {
        background: #f8fafc;
        color: var(--green-ink);
        border-color: #d9e5de;
    }
    .em-btn-sm-soft:hover {
        background: var(--green-xlight);
        color: var(--green);
    }
    .em-action-form { margin:0; }
    .em-action-form button { width:100%; justify-content:center; }
    .em-inline-badges { display:flex; flex-wrap:wrap; gap:6px; }
    .em-setup-list { display:flex; flex-wrap:wrap; gap:6px; max-width: 280px; }
    .em-row-muted { font-size: 11px; color: var(--sub); font-weight: 650; line-height: 1.45; }
    .em-section-title {
        font-size: 11px;
        font-weight: 800;
        letter-spacing: .14em;
        text-transform: uppercase;
        color: #718177;
        margin-bottom: 10px;
    }

    /* ── PAGINATION ── */
    .em-pagination {
        display: flex; align-items: center; justify-content: space-between;
        padding: 14px 22px; border-top: 1px solid var(--border);
    }
    .em-pg-info { font-size: 12px; color: #7A897F; font-weight: 700; }
    .em-pg-pages { display: flex; gap: 4px; }
    .em-pg-btn {
        display: inline-flex; align-items: center; justify-content: center;
        min-width: 32px; height: 32px; padding: 0 8px;
        border-radius: 8px; border: 1px solid var(--border);
        background: var(--white); color: #688176;
        font-size: 12px; font-weight: 700; cursor: pointer;
        text-decoration: none; transition: all .12s; font-family: 'Inter', sans-serif;
    }
    .em-pg-btn:hover { border-color: var(--green); color: var(--green); background: var(--green-xlight); }
    .em-pg-btn.active { background: var(--green); border-color: var(--green); color: #fff; }
    .em-pg-btn svg { width: 13px; height: 13px; }

    /* ── EMPTY ── */
    .em-empty {
        display: flex; flex-direction: column; align-items: center;
        justify-content: center; padding: 56px 24px; text-align: center;
    }
    .em-empty-title { font-size: 16px; font-weight: 800; color: var(--green-ink); margin: 12px 0 4px; }
    .em-empty-desc { font-size: 13px; color: var(--sub); font-weight: 600; }

    /* ── RESPONSIVE ── */
    @media (max-width: 1100px) {
        .em-stats { grid-template-columns: repeat(4, 1fr); }
        .em-filter-row { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .em-field:first-child { grid-column: 1 / -1; }
    }
    @media (max-width: 768px) {
        .em-page { padding: 0; }
        .em-topbar { padding: 16px; border-radius: 16px; }
        .em-user-info { display: none; }
        .em-stats { grid-template-columns: repeat(2, 1fr); }
        .em-filter-row { grid-template-columns: 1fr; }
        .em-hero-inner { padding: 18px; }
        .em-title { font-size: 30px; }
    }
</style>
@endpush

@section('content')

@php
    $hrRouteBase = auth()->check() && auth()->user()->isHrManager() ? 'hr-manager.settings.hr' : 'admin.hr';
    $salaryStructureLabel = function ($structure, string $fallback = 'Rule missing') {
        if (! $structure) {
            return $fallback;
        }

        return str_starts_with((string) $structure->name, '__employee__:')
            ? 'Custom breakup'
            : $structure->name;
    };
@endphp

{{-- TOPBAR --}}
<div class="em-topbar">
    <div class="em-topbar-left">
        <a href="#" class="em-logo">
            <div class="em-logo-mark">M</div>
            <span class="em-logo-text">Bihtech CRM</span>
        </a>
        <div class="em-topbar-sep"></div>
        <span class="em-topbar-title">Employee Master</span>
    </div>
    <div class="em-topbar-right">
        <div class="em-clock">Mon, 27 Apr &nbsp;<span>14:06</span></div>
        <div class="em-topbar-sep"></div>
        <a href="#" class="em-user">
            <div class="em-user-avatar">{{ collect(explode(' ', auth()->user()->name ?? 'U'))->map(fn ($n) => strtoupper($n[0] ?? ''))->take(2)->implode('') }}</div>
            <div class="em-user-info">
                <div class="em-user-name">{{ auth()->user()->name ?? 'User' }}</div>
                <div class="em-user-role">{{ auth()->user()->role->name ?? 'Employee' }}</div>
            </div>
        </a>
        <a href="#" class="em-logout">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Logout
        </a>
    </div>
</div>

{{-- PAGE --}}
<div class="em-page">

    {{-- HERO --}}
    <section class="em-hero">
        <div class="em-hero-inner">
            <div>
                <div class="em-eyebrow">Employee Master</div>
                <h1 class="em-title">Employees</h1>
            </div>
            <a href="{{ route($hrRouteBase . '.employees.create') }}" class="em-btn em-btn-primary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add Employee
            </a>
        </div>

        {{-- STATS ROW --}}
        <div class="em-stats">
            <div class="em-stat">
                <div class="em-stat-label">Total Employees</div>
                <div class="em-stat-value">{{ $stats['total'] }}</div>
                <div class="em-stat-note">Linked user records</div>
            </div>
            <div class="em-stat">
                <div class="em-stat-label">Active</div>
                <div class="em-stat-value">{{ $stats['active'] }}</div>
                <div class="em-stat-note">Working employees</div>
            </div>
            <div class="em-stat">
                <div class="em-stat-label">Pending Setup</div>
                <div class="em-stat-value" style="color: var(--red);">{{ $stats['pending_setup'] ?? $stats['missing_docs'] }}</div>
                <div class="em-stat-note">Need HR action</div>
            </div>
            <div class="em-stat">
                <div class="em-stat-label">Pending Incentives</div>
                <div class="em-stat-value">{{ $stats['pending_incentives'] }}</div>
                <div class="em-stat-note">Need verification</div>
            </div>
        </div>
    </section>

    @include('admin.hr._nav')

    {{-- FILTER CARD --}}
    <section class="em-filter-card">
        <div class="em-filter-head">
            <div>
                <div class="em-filter-title">Find Employee</div>
            </div>
            <div class="em-filter-meta">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                {{ $employees->total() }} results
            </div>
        </div>
        <div class="em-filter-body">
            <form method="GET" class="em-filter-row">
                <div class="em-field">
                    <label class="em-field-label">Search Employee</label>
                    <input type="text" name="search" value="{{ request('search') }}" class="em-input" placeholder="Name, email, phone, employee ID">
                </div>
                <div class="em-field">
                    <label class="em-field-label">Status</label>
                    <select name="employment_status" class="em-select">
                        <option value="">All statuses</option>
                        <option value="active" @selected(request('employment_status') === 'active')>Active</option>
                        <option value="on_notice" @selected(request('employment_status') === 'on_notice')>On Notice</option>
                        <option value="resigned" @selected(request('employment_status') === 'resigned')>Resigned</option>
                        <option value="terminated" @selected(request('employment_status') === 'terminated')>Terminated</option>
                        <option value="long_leave" @selected(request('employment_status') === 'long_leave')>Long Leave</option>
                    </select>
                </div>
                <div class="em-field">
                    <label class="em-field-label">Department</label>
                    <select name="department_id" class="em-select">
                        <option value="">All departments</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}" @selected((string) request('department_id') === (string) $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="em-field">
                    <label class="em-field-label">Designation</label>
                    <select name="designation_id" class="em-select">
                        <option value="">All designations</option>
                        @foreach($designations as $designation)
                            <option value="{{ $designation->id }}" @selected((string) request('designation_id') === (string) $designation->id)>{{ $designation->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="em-field">
                    <label class="em-field-label">Manager</label>
                    <select name="manager_id" class="em-select">
                        <option value="">All managers</option>
                        @foreach($managers as $manager)
                            <option value="{{ $manager->id }}" @selected((string) request('manager_id') === (string) $manager->id)>{{ $manager->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="em-btn em-btn-primary">Search</button>
                <a href="{{ route($hrRouteBase . '.employees.index') }}" class="em-btn em-btn-secondary">Reset</a>
            </form>
        </div>
    </section>

    {{-- RESULT GRID --}}
    <section class="em-result-card">
        <div class="em-result-head">
            <div class="em-result-title">Employees</div>
            <div class="em-result-count">{{ $employees->total() }}</div>
        </div>

        <div class="em-table-wrap">
            <table class="em-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Role & Team</th>
                        <th>Status</th>
                        <th>Work Setup</th>
                        <th>Salary</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($employees as $employee)
                        @php
                            $profile = $employee->employeeProfile;
                            $attendanceProfile = $employee->attendanceProfile;
                            $salaryProfile = $employee->salaryProfile;
                            $missingDocs = $profile ? count($profile->missingDocumentTypes()) : count(\App\Models\EmployeeProfile::REQUIRED_DOCUMENT_TYPES);
                            $assetCount = $profile?->assets?->where('status', \App\Models\EmployeeAsset::STATUS_ISSUED)->count() ?? 0;
                            $statusClass = match($profile?->employment_status) {
                                'active' => 'green',
                                'on_notice' => 'amber',
                                'resigned', 'terminated' => 'red',
                                'long_leave' => 'violet',
                                default => 'slate',
                            };
                            $initials = collect(explode(' ', $employee->name))->map(fn ($n) => strtoupper($n[0] ?? ''))->take(2)->implode('');
                            $setupIssues = [];
                            if (! $profile) {
                                $setupIssues[] = 'Profile missing';
                            }
                            if (! $profile?->employee_code) {
                                $setupIssues[] = 'Employee code missing';
                            }
                            if (! $employee->phone) {
                                $setupIssues[] = 'Phone missing';
                            }
                            if (! $attendanceProfile?->officeLocation) {
                                $setupIssues[] = 'Office missing';
                            }
                            if (! $attendanceProfile?->attendancePolicy) {
                                $setupIssues[] = 'Attendance rule missing';
                            }
                            if (! $salaryProfile) {
                                $setupIssues[] = 'Salary missing';
                            }
                            if ($missingDocs > 0) {
                                $setupIssues[] = $missingDocs . ' document missing';
                            }
                            $detailLink = $profile?->latestDetailLink;
                            $detailLinkUsable = $detailLink?->isUsable() ?? false;
                            $detailLinkExpired = $detailLink?->expires_at && $detailLink->expires_at->isPast();
                            $detailLinkLabel = $detailLink
                                ? ($detailLinkUsable ? ($detailLink->submitted_at ? 'Submitted link' : 'Active link') : ($detailLinkExpired ? 'Expired' : ucfirst($detailLink->status)))
                                : 'No link';
                        @endphp
                        <tr>
                            <td>
                                <div class="em-profile-cell">
                                    <div class="em-name-row">
                                        <div class="em-avatar">{{ $initials }}</div>
                                        <div class="em-name">{{ $employee->name }}</div>
                                    </div>
                                    <div class="em-id-tag">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:10px;height:10px;"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                                        {{ $profile?->employee_code ?? 'No employee ID' }}
                                    </div>
                                    <div class="em-contact-cell">
                                        <span class="em-contact-line">{{ $employee->email }}</span>
                                        <span class="em-contact-line">{{ $employee->phone ?: 'Phone missing' }}</span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="em-stack-cell">
                                    <div class="em-stack-row">
                                        <span class="em-key-tag">Role</span>
                                        <span class="em-val">{{ $employee->role->name ?? 'Not set' }}</span>
                                    </div>
                                    <div class="em-stack-row">
                                        <span class="em-key-tag">Department</span>
                                        <span class="em-val muted">{{ $profile?->department?->name ?? 'Missing' }}</span>
                                    </div>
                                    <div class="em-stack-row">
                                        <span class="em-key-tag">Designation</span>
                                        <span class="em-val muted">{{ $profile?->designation?->name ?? 'Missing' }}</span>
                                    </div>
                                    <div class="em-stack-row">
                                        <span class="em-key-tag">Manager</span>
                                        <span class="em-val muted">{{ $employee->manager?->name ?? 'Not assigned' }}</span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="em-stack-cell">
                                    <div class="em-inline-badges" style="margin-bottom:8px;">
                                        <span class="em-badge {{ $statusClass }}">{{ str_replace('_', ' ', ucfirst($profile?->employment_status ?? 'pending')) }}</span>
                                        <span class="em-badge {{ $employee->is_active ? 'green' : 'slate' }}">{{ $employee->is_active ? 'Login Active' : 'Login Off' }}</span>
                                        <span class="em-badge {{ $detailLinkUsable ? ($detailLink?->submitted_at ? 'green' : 'amber') : 'slate' }}">{{ $detailLinkLabel }}</span>
                                    </div>
                                    <div class="em-setup-list">
                                        @forelse($setupIssues as $issue)
                                            <span class="em-badge {{ str_contains($issue, 'missing') || str_contains($issue, 'Missing') ? 'amber' : 'red' }}">{{ $issue }}</span>
                                        @empty
                                            <span class="em-badge green">Setup complete</span>
                                        @endforelse
                                    </div>
                                    <div class="em-stack-row">
                                        <span class="em-key-tag">Joined</span>
                                        <span class="em-val muted">{{ $profile?->joining_date?->format('d M Y') ?? 'Missing' }}</span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="em-stack-cell">
                                    <div class="em-stack-row">
                                        <span class="em-key-tag">Office</span>
                                        <span class="em-val">{{ $attendanceProfile?->officeLocation?->name ?? 'Office missing' }}</span>
                                    </div>
                                    <div class="em-stack-row">
                                        <span class="em-key-tag">Policy</span>
                                        <span class="em-val muted">{{ $attendanceProfile?->attendancePolicy?->name ?? 'Rule missing' }}</span>
                                    </div>
                                    <div class="em-stack-row">
                                        <span class="em-key-tag">Code</span>
                                        <span class="em-val muted">{{ $attendanceProfile?->employee_code ?? ($profile?->employee_code ?? '--') }}</span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="em-stack-cell">
                                    <div class="em-stack-row">
                                        <span class="em-key-tag">Salary</span>
                                        <span class="em-val">{{ $salaryProfile ? 'Rs ' . number_format((float) $salaryProfile->base_salary, 0) : 'Salary missing' }}</span>
                                    </div>
                                    <div class="em-stack-row">
                                        <span class="em-key-tag">Rule</span>
                                        <span class="em-val muted">{{ $salaryStructureLabel($salaryProfile?->salaryStructure) }}</span>
                                    </div>
                                    <div class="em-stack-row">
                                        <span class="em-key-tag">Effective</span>
                                        <span class="em-val muted">{{ $salaryProfile?->effective_from?->format('d M Y') ?? 'Missing' }}</span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="em-actions-col">
                                    @if($profile)
                                        <a href="{{ route($hrRouteBase . '.employees.show', $employee) }}" class="em-btn-sm em-btn-sm-primary">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                            Open
                                        </a>
                                        @if($detailLinkUsable)
                                            <button type="button" class="em-btn-sm em-btn-sm-soft" data-copy-url="{{ $detailLink->publicUrl() }}">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                                Copy Link
                                            </button>
                                        @else
                                            <form method="POST" action="{{ route($hrRouteBase . '.employees.detail-link.generate', $employee) }}" class="em-action-form">
                                                @csrf
                                                <button type="submit" class="em-btn-sm em-btn-sm-soft">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                                                    Share Form
                                                </button>
                                            </form>
                                        @endif
                                    @else
                                        <a href="{{ route($hrRouteBase . '.employees.create', ['user_id' => $employee->id]) }}" class="em-btn-sm em-btn-sm-primary">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                            Complete Setup
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="em-empty">
                                    <div class="em-empty-title">No employees matched these filters</div>
                                    <div class="em-empty-desc">Try adjusting the search criteria or clear filters to see all employees.</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($employees->hasPages())
            <div class="em-pagination">
                <div class="em-pg-info">Showing {{ $employees->firstItem() }}-{{ $employees->lastItem() }} of {{ $employees->total() }} employees</div>
                <div class="em-pg-pages">
                    @if($employees->onFirstPage())
                        <span class="em-pg-btn" style="opacity:.4; cursor:not-allowed;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                        </span>
                    @else
                        <a href="{{ $employees->previousPageUrl() }}" class="em-pg-btn">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                        </a>
                    @endif

                    @foreach($employees->getUrlRange(max(1, $employees->currentPage() - 2), min($employees->lastPage(), $employees->currentPage() + 2)) as $page => $url)
                        <a href="{{ $url }}" class="em-pg-btn {{ $page == $employees->currentPage() ? 'active' : '' }}">{{ $page }}</a>
                    @endforeach

                    @if($employees->hasMorePages())
                        <a href="{{ $employees->nextPageUrl() }}" class="em-pg-btn">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                        </a>
                    @else
                        <span class="em-pg-btn" style="opacity:.4; cursor:not-allowed;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                        </span>
                    @endif
                </div>
            </div>
        @endif
    </section>

</div>

@push('scripts')
<script>
    document.addEventListener('click', function (event) {
        const button = event.target.closest('[data-copy-url]');
        if (!button) return;

        navigator.clipboard.writeText(button.dataset.copyUrl).then(function () {
            const oldText = button.textContent.trim();
            button.textContent = 'Copied';
            window.setTimeout(function () { button.textContent = oldText; }, 1400);
        });
    });
</script>
@endpush
@endsection

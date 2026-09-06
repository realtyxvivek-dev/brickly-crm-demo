@extends(auth()->check() && (auth()->user()->isAssistantSalesManager() || auth()->user()->isSeniorManager() || auth()->user()->isSalesManager()) ? 'sales-manager.layout' : 'layouts.app')

@section('title', 'Execution Desk')
@section('hide-app-header', '1')
@section('page-title', '')
@section('page-subtitle', '')

@push('styles')
<style>
    .ed {
        --green: #1B5E20;
        --green-light: #F1F8F2;
        --green-mid: #4CAF50;
        --red: #C62828;
        --red-light: #FDECEA;
        --amber: #E65100;
        --amber-light: #FFF3E0;
        --blue: #1565C0;
        --blue-light: #E3F2FD;
        --purple: #6A1B9A;
        --purple-light: #F3E5F5;
        --orange: #E65100;
        --orange-light: #FFF3E0;
        --text: #1A1A1A;
        --sub: #6B7280;
        --border: #E5E7EB;
        --bg: #F4F5F7;
        --white: #FFFFFF;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        -webkit-font-smoothing: antialiased;
        display: flex;
        flex-direction: column;
        gap: 20px;
        padding: 20px 0 28px;
        width: 100%;
        max-width: none;
        margin: 0;
        color: var(--text);
    }

    /* ── TOPBAR ── */
    .ed-topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
    }
    .ed-topbar-left {}
    .ed-eyebrow {
        font-size: 10.5px; font-weight: 800; letter-spacing: .14em;
        text-transform: uppercase; color: var(--green); margin-bottom: 5px;
    }
    .ed-title {
        font-size: 26px; font-weight: 800; letter-spacing: -.04em;
        color: var(--text); line-height: 1.1;
    }
    .ed-sub { font-size: 13px; color: var(--sub); font-weight: 500; margin-top: 5px; }
    .ed-topbar-right {
        display: flex; align-items: center; gap: 8px;
    }

    /* ── TOGGLES ── */
    .ed-toggle {
        display: flex; background: var(--white); border: 1px solid var(--border);
        border-radius: 10px; padding: 4px; gap: 2px;
    }
    .ed-toggle-btn {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 7px 14px; border-radius: 8px; border: none;
        font-size: 12.5px; font-weight: 700; cursor: pointer;
        background: transparent; color: var(--sub); transition: all .15s;
        font-family: 'Inter', sans-serif; text-decoration: none;
    }
    .ed-toggle-btn svg { width: 14px; height: 14px; }
    .ed-toggle-btn:hover { background: var(--green-light); color: var(--green); }
    .ed-toggle-btn.active { background: var(--green); color: #fff; }

    /* ── BUTTONS ── */
    .ed-btn {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 8px 16px; border-radius: 10px; border: none;
        font-size: 13px; font-weight: 700; cursor: pointer; text-decoration: none;
        transition: all .15s; white-space: nowrap; font-family: 'Inter', sans-serif;
    }
    .ed-btn svg { width: 14px; height: 14px; }
    .ed-btn-ghost { background: var(--white); color: var(--text); border: 1px solid var(--border); }
    .ed-btn-ghost:hover { border-color: #aaa; background: #fafafa; }
    .ed-btn-primary { background: var(--green); color: #fff; box-shadow: 0 2px 8px rgba(27,94,32,0.25); }
    .ed-btn-primary:hover { background: #1a4d1f; box-shadow: 0 4px 14px rgba(27,94,32,0.32); transform: translateY(-1px); }
    .ed-mobile-action-bell { display: none; }
    .ed-mobile-task-modal { display: none; }

    /* ── KPI STRIP ── */
    .ed-kpi-strip { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
    .ed-kpi {
        background: var(--white); border: 1px solid var(--border); border-radius: 14px;
        padding: 16px 18px; display: flex; flex-direction: column; gap: 4px; transition: all .2s;
    }
    .ed-kpi:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.06); transform: translateY(-1px); }
    .ed-kpi-top { display: flex; align-items: center; justify-content: space-between; }
    .ed-kpi-icon { font-size: 16px; line-height: 1; }
    .ed-kpi-trend { font-size: 10.5px; font-weight: 700; padding: 2px 7px; border-radius: 6px; }
    .ed-kpi-trend.up { background: var(--green-light); color: var(--green); }
    .ed-kpi-num { font-size: 32px; font-weight: 800; letter-spacing: -.06em; color: var(--text); line-height: 1; }
    .ed-kpi-label { font-size: 11.5px; font-weight: 700; color: var(--sub); text-transform: uppercase; letter-spacing: .06em; }
    .ed-kpi-note { font-size: 11px; color: #9ca3af; font-weight: 500; }
    .ed-kpi.active-tab { border-color: var(--green); background: var(--green-light); }

    /* ── FILTER PANEL ── */
    .ed-filter {
        background: var(--white); border: 1px solid var(--border);
        border-radius: 14px; padding: 16px 18px;
    }
    .ed-mobile-filter-toggle { display: none; }
    .ed-filter-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .ed-filter-search-wrap { position: relative; flex: 1; min-width: 220px; }
    .ed-filter-select-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .ed-filter-search-wrap svg {
        position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
        width: 14px; height: 14px; color: #9ca3af;
    }
    .ed-filter-input {
        width: 100%; padding: 9px 12px 9px 38px; border: 1px solid var(--border);
        border-radius: 8px; font-size: 13px; font-weight: 500; color: var(--text);
        background: var(--white); outline: none; font-family: 'Inter', sans-serif;
        transition: border-color .15s;
    }
    .ed-filter-input:focus { border-color: var(--green); box-shadow: 0 0 0 3px rgba(27,94,32,.08); }
    .ed-filter-input::placeholder { color: #b0b0b0; }
    .ed-filter-select {
        padding: 8px 32px 8px 12px; border: 1px solid var(--border); border-radius: 8px;
        font-size: 12.5px; font-weight: 600; color: var(--text); background: var(--white);
        outline: none; cursor: pointer; font-family: 'Inter', sans-serif; appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%236B7280' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
        background-repeat: no-repeat; background-position: right 10px center;
        transition: border-color .15s; min-width: 140px;
    }
    .ed-filter-select:focus { border-color: var(--green); box-shadow: 0 0 0 3px rgba(27,94,32,.08); }
    .ed-filter-actions { display: flex; gap: 6px; margin-left: auto; }
    .ed-filter-btn {
        padding: 8px 14px; border-radius: 8px; border: none;
        font-size: 12.5px; font-weight: 700; cursor: pointer;
        font-family: 'Inter', sans-serif; transition: all .15s;
    }
    .ed-filter-btn-apply { background: var(--green); color: #fff; }
    .ed-filter-btn-apply:hover { background: #1a4d1f; }
    .ed-filter-btn-reset { background: transparent; color: var(--sub); }
    .ed-filter-btn-reset:hover { color: var(--text); }
    .ed-filter-divider { width: 1px; height: 24px; background: var(--border); }
    .ed-save-row {
        display: flex; align-items: center; gap: 10px;
        margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--border); flex-wrap: wrap;
    }
    .ed-save-label { font-size: 11.5px; font-weight: 700; color: var(--sub); white-space: nowrap; }
    .ed-save-input {
        flex: 1; min-width: 180px; padding: 7px 12px; border: 1px solid var(--border);
        border-radius: 8px; font-size: 12.5px; font-weight: 600; color: var(--text);
        background: var(--white); outline: none; font-family: 'Inter', sans-serif;
        transition: border-color .15s;
    }
    .ed-save-input:focus { border-color: var(--green); box-shadow: 0 0 0 3px rgba(27,94,32,.08); }
    .ed-save-input::placeholder { color: #b0b0b0; }
    .ed-save-check { display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; color: var(--sub); cursor: pointer; white-space: nowrap; }
    .ed-save-check input[type="checkbox"] { width: 14px; height: 14px; border-radius: 4px; cursor: pointer; accent-color: var(--green); }

    /* ── RESULT AREA ── */
    .ed-result { background: var(--white); border: 1px solid var(--border); border-radius: 14px; overflow: hidden; }
    .ed-result-bar {
        display: flex; align-items: center; justify-content: space-between;
        padding: 12px 18px; border-bottom: 1px solid var(--border); background: #fafafa; gap: 12px; flex-wrap: wrap;
    }
    .ed-result-count { font-size: 12.5px; font-weight: 600; color: var(--sub); }
    .ed-result-count strong { color: var(--green); font-weight: 800; }
    .ed-result-tabs { display: flex; gap: 4px; }
    .ed-result-tab {
        padding: 6px 12px; border-radius: 8px; border: none; background: transparent;
        color: var(--sub); font-size: 12px; font-weight: 700; cursor: pointer;
        transition: all .12s; font-family: 'Inter', sans-serif; text-decoration: none;
    }
    .ed-result-tab:hover { background: var(--green-light); color: var(--green); }
    .ed-result-tab.active { background: var(--green); color: #fff; }
    .ed-result-tab .count {
        display: inline-flex; align-items: center; justify-content: center;
        min-width: 20px; height: 20px; padding: 0 5px; border-radius: 6px;
        font-size: 10px; font-weight: 800; margin-left: 5px;
    }
    .ed-result-tab.active .count { background: rgba(255,255,255,0.2); color: #fff; }
    .ed-result-tab:not(.active) .count { background: var(--green-light); color: var(--green); }

    /* ── TASK LIST ── */
    .ed-task-list { padding: 16px 18px; display: flex; flex-direction: column; gap: 10px; }
    .ed-self-todo { padding: 18px; display: grid; gap: 14px; background: linear-gradient(135deg, #fffdfa 0%, #f4f8f2 100%); }
    .ed-self-quick { display: grid; grid-template-columns: minmax(0, 1.4fr) 150px 130px auto; gap: 10px; align-items: center; }
    .ed-self-input, .ed-self-select {
        width: 100%; border: 1px solid var(--border); border-radius: 12px; background: #fff;
        padding: 10px 12px; font-size: 13px; font-weight: 650; color: var(--text);
        font-family: 'Inter', sans-serif; outline: none;
    }
    .ed-self-input:focus, .ed-self-select:focus { border-color: var(--green); box-shadow: 0 0 0 3px rgba(27,94,32,.08); }
    .ed-self-chips { display: flex; gap: 8px; overflow-x: auto; scrollbar-width: none; }
    .ed-self-chips::-webkit-scrollbar { display: none; }
    .ed-self-chip {
        display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; border: 1px solid var(--border);
        border-radius: 999px; padding: 8px 12px; background: #fff; color: var(--sub);
        text-decoration: none; font-size: 12px; font-weight: 800;
    }
    .ed-self-chip.active { background: var(--green); color: #fff; border-color: var(--green); box-shadow: 0 8px 20px rgba(27,94,32,.16); }
    .ed-self-list { display: grid; gap: 10px; }
    .ed-self-card {
        display: grid; grid-template-columns: auto 1fr auto; gap: 12px; align-items: start;
        border: 1px solid var(--border); border-radius: 18px; padding: 14px; background: #fff;
        box-shadow: 0 10px 24px rgba(13, 43, 28, .06);
    }
    .ed-self-card.done { opacity: .7; background: #f8faf7; }
    .ed-self-card.done .ed-self-title { text-decoration: line-through; color: #748078; }
    .ed-self-check {
        width: 30px; height: 30px; border-radius: 11px; border: 2px solid currentColor;
        display: inline-flex; align-items: center; justify-content: center; background: transparent;
        font-weight: 900; cursor: pointer;
    }
    .ed-self-priority-bar { width: 7px; height: 44px; border-radius: 99px; }
    .ed-self-priority-bar.high { background: var(--red); }
    .ed-self-priority-bar.medium { background: var(--amber); }
    .ed-self-priority-bar.low { background: var(--blue); }
    .ed-self-left { display: flex; gap: 10px; align-items: flex-start; }
    .ed-self-title { margin: 0 0 7px; font-size: 14px; line-height: 1.35; font-weight: 850; color: var(--text); }
    .ed-self-note { margin: 0 0 8px; color: var(--sub); font-size: 12.5px; line-height: 1.45; font-weight: 550; }
    .ed-self-meta { display: flex; flex-wrap: wrap; gap: 7px; }
    .ed-self-pill { display: inline-flex; align-items: center; gap: 5px; padding: 5px 9px; border-radius: 999px; background: var(--green-light); color: var(--green); font-size: 10.5px; font-weight: 800; }
    .ed-self-pill.high { background: var(--red-light); color: var(--red); }
    .ed-self-pill.medium { background: var(--amber-light); color: var(--amber); }
    .ed-self-pill.low { background: var(--blue-light); color: var(--blue); }
    .ed-self-actions { display: flex; align-items: center; gap: 6px; }
    .ed-self-details summary { list-style: none; cursor: pointer; }
    .ed-self-details summary::-webkit-details-marker { display: none; }
    .ed-self-edit {
        grid-column: 1 / -1; margin-top: 10px; padding-top: 12px; border-top: 1px solid #edf2ee;
        display: grid; grid-template-columns: minmax(0, 1fr) 140px 130px 130px auto; gap: 8px; align-items: center;
    }
    .ed-self-edit .ed-self-note-input { grid-column: span 2; }
    .ed-self-delete { background: #fff5f5; color: var(--red); border: 1px solid #fecaca; }

    .ed-task-card {
        border: 1px solid var(--border); border-radius: 12px;
        padding: 14px 16px; background: var(--white);
        transition: all .18s ease; cursor: pointer;
    }
    .ed-task-card:hover {
        border-color: rgba(27,94,32,0.25);
        box-shadow: 0 4px 16px rgba(0,0,0,0.06);
        transform: translateY(-1px);
    }

    .ed-task-top {
        display: flex; align-items: center; gap: 8px; margin-bottom: 8px; flex-wrap: wrap;
    }
    .ed-task-id {
        font-size: 11px; font-weight: 700; color: #c0c0c0;
        font-family: 'SF Mono', 'Consolas', monospace; letter-spacing: .03em;
    }
    .ed-task-title {
        font-size: 14px; font-weight: 700; color: var(--text); flex: 1;
    }
    .ed-task-title a { color: inherit; text-decoration: none; }
    .ed-task-title a:hover { color: var(--green); }

    .ed-badge {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 3px 9px; border-radius: 6px; font-size: 10.5px; font-weight: 800; letter-spacing: .05em;
    }
    .ed-badge .dot { width: 5px; height: 5px; border-radius: 50%; }
    .ed-badge-hr { background: var(--purple-light); color: var(--purple); }
    .ed-badge-hr .dot { background: var(--purple); }
    .ed-badge-finance { background: var(--amber-light); color: var(--amber); }
    .ed-badge-finance .dot { background: var(--amber); }
    .ed-badge-crm { background: var(--blue-light); color: var(--blue); }
    .ed-badge-crm .dot { background: var(--blue); }
    .ed-badge-admin { background: var(--green-light); color: var(--green); }
    .ed-badge-admin .dot { background: var(--green); }
    .ed-badge-sales { background: var(--orange-light); color: var(--orange); }
    .ed-badge-sales .dot { background: var(--orange); }
    .ed-badge-marketing { background: #fce7f3; color: #9d174d; }
    .ed-badge-marketing .dot { background: #9d174d; }
    .ed-badge-ops { background: #f0fdf4; color: #166534; }
    .ed-badge-ops .dot { background: #166534; }

    .ed-badge-ph { background: var(--red-light); color: var(--red); }
    .ed-badge-pm { background: var(--amber-light); color: var(--amber); }
    .ed-badge-pl { background: var(--blue-light); color: var(--blue); }
    .ed-badge-pu { background: var(--purple-light); color: var(--purple); }

    .ed-badge-sopen { background: var(--amber-light); color: var(--amber); }
    .ed-badge-sopen .dot { background: var(--amber); }
    .ed-badge-sprog { background: var(--blue-light); color: var(--blue); }
    .ed-badge-sprog .dot { background: var(--blue); }
    .ed-badge-swait { background: var(--purple-light); color: var(--purple); }
    .ed-badge-swait .dot { background: var(--purple); }
    .ed-badge-sdone { background: var(--green-light); color: var(--green); }
    .ed-badge-sdone .dot { background: var(--green); }
    .ed-badge-sclosed { background: #f3f4f6; color: #6b7280; }
    .ed-badge-sclosed .dot { background: #6b7280; }
    .ed-badge-sreopen { background: #fff7ed; color: #c2410c; }
    .ed-badge-sreopen .dot { background: #c2410c; }
    .ed-badge-sreject { background: var(--red-light); color: var(--red); }
    .ed-badge-sreject .dot { background: var(--red); }

    .ed-task-desc {
        font-size: 12.5px; color: var(--sub); font-weight: 500;
        margin-bottom: 10px; line-height: 1.5;
    }
    .ed-task-context { margin-bottom: 10px; }

    .ed-task-footer {
        display: flex; align-items: center; justify-content: space-between;
        gap: 10px; padding-top: 10px; border-top: 1px solid #f3f4f6; flex-wrap: wrap;
    }
    .ed-task-people { display: flex; align-items: center; gap: 14px; }
    .ed-task-person { display: flex; align-items: center; gap: 6px; }
    .ed-task-person-label { font-size: 10px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: .07em; }
    .ed-task-avatar {
        width: 24px; height: 24px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 9px; font-weight: 800; color: #fff; flex-shrink: 0;
    }
    .ed-task-person-name { font-size: 12px; font-weight: 700; color: var(--text); }

    .ed-task-right { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .ed-task-due { font-size: 11.5px; font-weight: 600; color: var(--sub); }
    .ed-task-due.overdue { color: var(--red); }
    .ed-task-flag {
        display: inline-flex; align-items: center; gap: 3px;
        font-size: 10px; font-weight: 700; color: var(--red);
    }
    .ed-task-flag svg { width: 11px; height: 11px; }

    .ed-task-actions { display: flex; gap: 6px; align-items: center; }
    .ed-task-form { display: flex; align-items: center; gap: 6px; }
    .ed-task-select {
        padding: 6px 28px 6px 10px; border: 1px solid var(--border); border-radius: 8px;
        font-size: 12px; font-weight: 600; color: var(--text); background: var(--white);
        outline: none; cursor: pointer; font-family: 'Inter', sans-serif; appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='9' height='9' viewBox='0 0 24 24' fill='none' stroke='%236B7280' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
        background-repeat: no-repeat; background-position: right 8px center; min-width: 120px;
    }
    .ed-task-select:focus { border-color: var(--green); }
    .ed-task-sm-btn {
        padding: 6px 12px; border-radius: 8px; border: none;
        font-size: 12px; font-weight: 700; cursor: pointer;
        font-family: 'Inter', sans-serif; transition: all .15s; white-space: nowrap;
    }
    .ed-task-sm-btn-update { background: var(--green); color: #fff; }
    .ed-task-sm-btn-update:hover { background: #1a4d1f; }
    .ed-task-sm-btn-outline { background: transparent; border: 1px solid var(--border); color: var(--sub); }
    .ed-task-sm-btn-outline:hover { border-color: var(--green); color: var(--green); }

    /* ── KANBAN ── */
    .ed-kanban-board {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 14px;
        padding: 18px;
        overflow-x: auto;
    }
    .ed-kanban-col {
        background: #f8f9fa;
        border-radius: 14px;
        min-width: 220px;
        display: flex;
        flex-direction: column;
    }
    .ed-kanban-head {
        display: flex; align-items: center; justify-content: space-between;
        padding: 12px 14px 10px; border-bottom: 1px solid var(--border);
    }
    .ed-kanban-head-title { display: flex; align-items: center; gap: 7px; }
    .ed-kanban-count {
        display: inline-flex; align-items: center; justify-content: center;
        min-width: 22px; height: 22px; padding: 0 6px; border-radius: 6px;
        font-size: 11px; font-weight: 800; color: #fff;
    }
    .ed-kanban-count.open { background: var(--amber); }
    .ed-kanban-count.progress { background: var(--blue); }
    .ed-kanban-count.waiting { background: var(--purple); }
    .ed-kanban-count.done { background: var(--green-mid); }
    .ed-kanban-count.closed { background: #9ca3af; }
    .ed-kanban-count.reopen { background: #c2410c; }
    .ed-kanban-count.rejected { background: var(--red); }

    .ed-kanban-name {
        font-size: 12px; font-weight: 800; text-transform: uppercase;
        letter-spacing: .07em; color: var(--text);
    }
    .ed-kanban-body {
        padding: 10px; display: flex; flex-direction: column;
        gap: 10px; min-height: 200px; max-height: 520px; overflow-y: auto;
    }
    .ed-kanban-body::-webkit-scrollbar { width: 4px; }
    .ed-kanban-body::-webkit-scrollbar-track { background: transparent; }
    .ed-kanban-body::-webkit-scrollbar-thumb { background: #d0d0d0; border-radius: 4px; }

    /* ── PAGINATION ── */
    .ed-pagination {
        display: flex; align-items: center; justify-content: space-between;
        padding: 12px 18px; border-top: 1px solid var(--border);
    }
    .ed-pagination-text { font-size: 11.5px; color: #9ca3af; font-weight: 600; }
    .ed-pagination-pages { display: flex; gap: 4px; }
    .ed-page-btn {
        display: inline-flex; align-items: center; justify-content: center;
        min-width: 34px; height: 34px; padding: 0 8px; border-radius: 8px;
        border: 1px solid var(--border); background: var(--white);
        color: var(--sub); font-size: 12px; font-weight: 700; cursor: pointer;
        text-decoration: none; transition: all .12s; font-family: 'Inter', sans-serif;
    }
    .ed-page-btn:hover { border-color: var(--green); color: var(--green); background: var(--green-light); }
    .ed-page-btn.active { background: var(--green); border-color: var(--green); color: #fff; }
    .ed-page-btn svg { width: 14px; height: 14px; }

    /* ── EMPTY STATE ── */
    .ed-empty {
        display: flex; flex-direction: column; align-items: center;
        justify-content: center; padding: 56px 24px; text-align: center;
    }
    .ed-empty-icon {
        width: 60px; height: 60px; border-radius: 20px;
        background: var(--green-light); display: flex; align-items: center; justify-content: center; margin-bottom: 14px;
    }
    .ed-empty-icon svg { width: 28px; height: 28px; color: var(--green-mid); }
    .ed-empty-title { font-size: 16px; font-weight: 800; color: var(--text); margin-bottom: 5px; }
    .ed-empty-desc { font-size: 12.5px; color: var(--sub); font-weight: 500; max-width: 280px; margin-bottom: 18px; line-height: 1.5; }

    /* ── FLASH ── */
    .ed-flash {
        padding: 12px 16px; border-radius: 12px; font-size: 13.5px; font-weight: 600;
        margin-bottom: 4px;
    }
    .ed-flash-success { background: var(--green-light); color: var(--green); border: 1px solid #bbf7d0; }
    .ed-flash-error { background: var(--red-light); color: var(--red); border: 1px solid #fecaca; }

    /* ── RESPONSIVE ── */
    @media (max-width: 900px) {
        .ed { padding: 16px; }
        .ed-kpi-strip { grid-template-columns: repeat(2, 1fr); }
        .ed-filter { padding: 10px; }
        .ed-mobile-filter-toggle {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            border: 1px solid var(--border);
            background: #fff;
            color: var(--text);
            border-radius: 12px;
            padding: 10px 12px;
            font-size: 13px;
            font-weight: 800;
            font-family: 'Inter', sans-serif;
        }
        .ed-mobile-filter-toggle svg { width: 16px; height: 16px; color: var(--green); }
        .ed-filter-panel { display: none; margin-top: 10px; }
        .ed-filter.is-open .ed-filter-panel { display: block; }
        .ed-filter-row { flex-direction: column; align-items: stretch; gap: 10px; }
        .ed-filter-search-wrap { width: 100%; min-width: 0; }
        .ed-filter-select-row {
            width: 100%;
            flex-wrap: nowrap;
            overflow-x: auto;
            padding-bottom: 4px;
            scrollbar-width: none;
        }
        .ed-filter-select-row::-webkit-scrollbar { display: none; }
        .ed-filter-select { flex: 0 0 auto; min-width: 132px; }
        .ed-filter-actions { width: 100%; margin-left: 0; justify-content: flex-end; }
        .ed-filter-divider { display: none; }
        .ed-save-row {
            flex-direction: row;
            align-items: center;
            gap: 8px;
            padding-top: 10px;
            margin-top: 10px;
        }
        .ed-save-input { min-width: 0; flex: 1; }
        .ed-save-row .ed-btn { padding: 8px 12px !important; }
        .ed-result-bar { flex-direction: column; align-items: flex-start; }
        .ed-self-quick { grid-template-columns: 1fr; }
        .ed-self-edit { grid-template-columns: 1fr; }
        .ed-self-edit .ed-self-note-input { grid-column: auto; }
        .ed-task-footer { flex-direction: column; align-items: flex-start; }
        .ed-task-right { width: 100%; }
        .ed-kanban-board { padding: 12px; }
    }
    @media (max-width: 600px) {
        .ed-kpi-strip {
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 8px;
        }
        .ed-kpi {
            min-width: 0;
            border-radius: 14px;
            padding: 8px 6px;
            gap: 2px;
        }
        .ed-kpi-top { min-height: 16px; }
        .ed-kpi-icon { font-size: 12px; }
        .ed-kpi-trend { display: none; }
        .ed-kpi-num {
            font-size: 20px;
            letter-spacing: -.05em;
        }
        .ed-kpi-label {
            font-size: 7.5px;
            letter-spacing: .035em;
            line-height: 1.15;
        }
        .ed-kpi-note { display: none; }
        .ed-result-bar {
            padding: 10px 12px;
            gap: 8px;
        }
        .ed-result-count {
            font-size: 11px;
            line-height: 1.4;
        }
        .ed-result-tabs {
            width: 100%;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 6px;
        }
        .ed-result-tab {
            justify-content: center;
            text-align: center;
            padding: 8px 5px;
            font-size: 10.5px;
            line-height: 1.15;
        }
        .ed-result-tab .count {
            min-width: 17px;
            height: 17px;
            font-size: 9px;
            margin-left: 3px;
        }
        .ed-self-todo {
            padding: 10px;
            gap: 9px;
        }
        .ed-self-quick {
            grid-template-columns: minmax(0, 1fr) 40px 64px 44px;
            gap: 5px;
        }
        .ed-self-input,
        .ed-self-select {
            min-width: 0;
            height: 38px;
            border-radius: 11px;
            padding: 7px 8px;
            font-size: 10.5px;
        }
        .ed-self-input::placeholder { font-size: 10.5px; }
        .ed-self-quick input[name="due_at"] {
            color: transparent;
            padding: 0;
            text-align: center;
        }
        .ed-self-quick input[name="due_at"]::-webkit-calendar-picker-indicator {
            margin: 0 auto;
            cursor: pointer;
            opacity: .8;
        }
        .ed-self-quick select[name="priority"] {
            padding-right: 17px;
            font-size: 10px;
        }
        .ed-self-quick .ed-btn {
            width: 44px;
            height: 38px;
            padding: 0 !important;
            border-radius: 11px;
            font-size: 0;
        }
        .ed-self-quick .ed-btn::before {
            content: '+';
            font-size: 20px;
            line-height: 1;
            font-weight: 900;
        }
        .ed-self-chips {
            gap: 6px;
            padding-bottom: 2px;
        }
        .ed-self-chip {
            padding: 6px 8px;
            font-size: 10px;
        }
        .ed-self-list { gap: 8px; }
        .ed-self-card {
            grid-template-columns: auto minmax(0, 1fr);
            gap: 9px;
            padding: 11px;
            border-radius: 16px;
        }
        .ed-self-left { gap: 7px; }
        .ed-self-priority-bar {
            width: 5px;
            height: 38px;
        }
        .ed-self-check {
            width: 28px;
            height: 28px;
            border-radius: 10px;
        }
        .ed-self-title {
            font-size: 13px;
            margin-bottom: 6px;
        }
        .ed-self-note {
            font-size: 11px;
            margin-bottom: 6px;
        }
        .ed-self-meta {
            gap: 5px;
        }
        .ed-self-pill {
            padding: 4px 7px;
            font-size: 9.5px;
        }
        .ed-self-actions {
            grid-column: 1 / -1;
            justify-content: flex-end;
        }
        .ed-self-actions .ed-task-sm-btn {
            padding: 6px 10px;
            font-size: 11px;
        }
        .ed-topbar { display: block; }
        .ed-topbar-left { display: none; }
        .ed-topbar-right {
            width: 100%;
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto auto;
            align-items: center;
            gap: 8px;
        }
        .ed-toggle { min-width: 0; width: 100%; }
        .ed-toggle-btn {
            flex: 1;
            justify-content: center;
            padding: 8px 7px;
            font-size: 11.5px;
            gap: 4px;
        }
        .ed-btn { padding: 9px 10px; border-radius: 9px; }
        .ed-btn .ed-mobile-hide-label { display: none; }
        .ed-mobile-action-bell {
            display: inline-flex;
            width: 40px;
            height: 40px;
            padding: 0;
            justify-content: center;
            border-radius: 12px;
            box-shadow: none;
        }
        .ed-mobile-action-bell .global-notification-center { position: static; }
        .ed-mobile-action-bell .global-notification-bell {
            width: 40px;
            height: 40px;
            box-shadow: none;
            border-radius: 12px;
        }
        .ed-mobile-action-bell .global-notification-panel {
            position: fixed;
            top: 72px;
            left: 12px;
            right: 12px;
            width: auto;
            max-height: calc(100vh - 120px);
        }
        .ed-mobile-task-modal.is-open {
            position: fixed;
            inset: 0;
            z-index: 3600;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 18px;
            background: rgba(6, 24, 17, .58);
            backdrop-filter: blur(8px);
        }
        .ed-mobile-task-sheet {
            width: 100%;
            max-width: 420px;
            max-height: 86vh;
            overflow-y: auto;
            background: #fff;
            border-radius: 24px;
            padding: 18px;
            box-shadow: 0 24px 70px rgba(6, 24, 17, .26);
        }
        .ed-mobile-task-errors {
            border: 1px solid #fecaca;
            border-radius: 14px;
            background: #fef2f2;
            color: #991b1b;
            padding: 10px 12px;
            font-size: 12px;
            font-weight: 700;
            line-height: 1.45;
        }
        .ed-mobile-task-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 14px;
        }
        .ed-mobile-task-head h3 {
            margin: 0;
            color: var(--text);
            font-size: 18px;
            font-weight: 800;
        }
        .ed-mobile-task-close {
            width: 38px;
            height: 38px;
            border: 0;
            border-radius: 12px;
            background: #f3f4f6;
            color: var(--text);
            font-size: 20px;
            line-height: 1;
        }
        .ed-mobile-task-form {
            display: grid;
            gap: 11px;
        }
        .ed-mobile-task-field label {
            display: block;
            margin-bottom: 5px;
            color: var(--sub);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .ed-mobile-task-field input,
        .ed-mobile-task-field select,
        .ed-mobile-task-field textarea {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 11px 12px;
            color: var(--text);
            font-size: 14px;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            background: #fff;
        }
        .ed-mobile-task-help {
            display: block;
            margin-top: 5px;
            color: var(--muted);
            font-size: 11px;
            font-weight: 600;
            line-height: 1.35;
        }
        .ed-mobile-task-field textarea {
            min-height: 76px;
            resize: vertical;
        }
        .ed-mobile-task-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .ed-mobile-task-submit {
            width: 100%;
            justify-content: center;
            margin-top: 4px;
            padding: 12px 14px;
        }
    }

    .hidden { display: none !important; }
</style>
@endpush

@php
    $currentTab = $filters['tab'] ?? 'my_queue';
    $currentView = 'list';
    $creatorName = auth()->user()->name;
    $creatorInitials = collect(explode(' ', auth()->user()->name))->map(fn ($n) => strtoupper($n[0]))->take(2)->implode('');
    $creatorColor = '#1B5E20';
    $userId = auth()->id();

    $contextBadgeClass = [
        'hr' => 'ed-badge-hr',
        'finance' => 'ed-badge-finance',
        'crm' => 'ed-badge-crm',
        'admin' => 'ed-badge-admin',
        'sales' => 'ed-badge-sales',
        'marketing' => 'ed-badge-marketing',
        'operations' => 'ed-badge-ops',
    ];

    $contextLabels = [
        'hr' => 'HR', 'finance' => 'Finance', 'crm' => 'CRM',
        'admin' => 'Admin', 'sales' => 'Sales', 'marketing' => 'Marketing', 'operations' => 'Operations',
    ];

    $priorityBadgeClass = [
        'high' => 'ed-badge-ph',
        'medium' => 'ed-badge-pm',
        'low' => 'ed-badge-pl',
        'urgent' => 'ed-badge-pu',
    ];

    $priorityLabels = [
        'high' => 'High', 'medium' => 'Medium', 'low' => 'Low', 'urgent' => 'Urgent',
    ];

    // Map status to kanban column
    $kanbanMap = [
        'open' => 'open',
        'in_progress' => 'progress',
        'waiting' => 'waiting',
        'completed' => 'done',
        'closed' => 'closed',
        'reopened' => 'reopen',
        'rejected' => 'rejected',
    ];

    $kanbanCols = [
        'open' => ['key' => 'open', 'label' => 'Open', 'count_class' => 'open'],
        'in_progress' => ['key' => 'progress', 'label' => 'In Progress', 'count_class' => 'progress'],
        'waiting' => ['key' => 'waiting', 'label' => 'Waiting', 'count_class' => 'waiting'],
        'completed' => ['key' => 'done', 'label' => 'Completed', 'count_class' => 'done'],
        'closed' => ['key' => 'closed', 'label' => 'Closed', 'count_class' => 'closed'],
    ];

    $statusBadgeClass = [
        'open' => 'ed-badge-sopen',
        'in_progress' => 'ed-badge-sprog',
        'waiting' => 'ed-badge-swait',
        'completed' => 'ed-badge-sdone',
        'closed' => 'ed-badge-sclosed',
        'reopened' => 'ed-badge-sreopen',
        'rejected' => 'ed-badge-sreject',
    ];

    $statusDotColor = [
        'open' => '#E65100',
        'in_progress' => '#1565C0',
        'waiting' => '#6A1B9A',
        'completed' => '#1B5E20',
        'closed' => '#9ca3af',
        'reopened' => '#c2410c',
        'rejected' => '#C62828',
    ];

    // Kanban: group tasks by status
    $kanbanTasks = [];
    foreach ($kanbanCols as $backendStatus => $col) {
        $kanbanTasks[$col['key']] = $tasks->filter(fn ($t) => $t->status === $backendStatus)->values();
    }
@endphp

@section('content')
@php
    $currentView = $currentView ?? 'list';
    $currentTab = $currentTab ?? ($filters['tab'] ?? 'my_queue');
    $isMarketingUser = auth()->user()?->isMarketingUser();
    $isMarketingExecutive = auth()->user()?->isMarketingExecutive();
    $allowedTransitions = [
        'open' => ['in_progress', 'rejected'],
        'in_progress' => ['waiting', 'completed', 'rejected'],
        'waiting' => ['in_progress', 'rejected'],
        'completed' => ['closed', 'reopened', 'rejected'],
        'reopened' => ['in_progress', 'rejected'],
    ];
    $kanbanCols = $kanbanCols ?? [
        'open' => ['key' => 'open', 'label' => 'Open', 'count_class' => 'open'],
        'in_progress' => ['key' => 'progress', 'label' => 'In Progress', 'count_class' => 'progress'],
        'waiting' => ['key' => 'waiting', 'label' => 'Waiting', 'count_class' => 'waiting'],
        'completed' => ['key' => 'done', 'label' => 'Completed', 'count_class' => 'done'],
        'closed' => ['key' => 'closed', 'label' => 'Closed', 'count_class' => 'closed'],
    ];
@endphp
<div class="ed">

    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="ed-flash ed-flash-success">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
        </div>
    @endif
    @if($errors->any())
        <div class="ed-flash ed-flash-error">
            <i class="fas fa-exclamation-circle"></i> {{ $errors->first() }}
        </div>
    @endif

    {{-- TOPBAR --}}
    <div class="ed-topbar">
        <div class="ed-topbar-left">
            @unless($isMarketingUser)
                <div class="ed-eyebrow">Internal Operations</div>
            @endunless
            <h1 class="ed-title">Execution Desk</h1>
        </div>
        <div class="ed-topbar-right">
            {{-- Role Switcher --}}
            <div class="ed-toggle">
                <a href="{{ route('execution-desk.index', array_merge(request()->query(), array_filter(['tab' => 'my_queue', 'view' => $currentView]))) }}"
                   class="ed-toggle-btn {{ $currentTab === 'my_queue' ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    My Tasks
                </a>
                <a href="{{ route('execution-desk.index', array_merge(request()->query(), array_filter(['tab' => 'team_all', 'view' => $currentView]))) }}"
                   class="ed-toggle-btn {{ $currentTab === 'team_all' ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    All Tasks
                </a>
                @if($canUseSelfTodoPilot)
                <a href="{{ route('execution-desk.index', ['tab' => 'self_todo']) }}"
                   class="ed-toggle-btn {{ $currentTab === 'self_todo' ? 'active' : '' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                    My Todo
                </a>
                @endif
            </div>

            <a href="{{ route('execution-desk.create') }}" class="ed-btn ed-btn-primary" data-mobile-task-create>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <span class="ed-mobile-hide-label">Create Task</span>
            </a>
            <div class="ed-mobile-action-bell">
                @include('components.global-notification-center')
            </div>
        </div>
    </div>

    {{-- KPI STRIP --}}
    <div class="ed-kpi-strip">
        <a href="{{ route('execution-desk.index', array_merge(request()->query(), array_filter(['tab' => 'my_queue', 'view' => $currentView]))) }}"
           class="ed-kpi {{ $currentTab === 'my_queue' ? 'active-tab' : '' }}">
            <div class="ed-kpi-top">
                <span class="ed-kpi-icon">📋</span>
            </div>
            <div class="ed-kpi-num">{{ $myQueueCount }}</div>
            <div class="ed-kpi-label">My Queue</div>
            @unless($isMarketingUser)
                <div class="ed-kpi-note">Assigned to you</div>
            @endunless
        </a>
        <a href="{{ route('execution-desk.index', array_merge(request()->query(), array_filter(['tab' => 'assigned_by_me', 'view' => $currentView]))) }}"
           class="ed-kpi {{ $currentTab === 'assigned_by_me' ? 'active-tab' : '' }}">
            <div class="ed-kpi-top">
                <span class="ed-kpi-icon">👥</span>
            </div>
            <div class="ed-kpi-num">{{ $assignedByMeCount }}</div>
            <div class="ed-kpi-label">Assigned By Me</div>
            @unless($isMarketingUser)
                <div class="ed-kpi-note">Your team's tasks</div>
            @endunless
        </a>
        @if(auth()->user()->canViewExecutionDeskTeamAll())
        <a href="{{ route('execution-desk.index', array_merge(request()->query(), array_filter(['tab' => 'team_all', 'view' => $currentView]))) }}"
           class="ed-kpi {{ $currentTab === 'team_all' ? 'active-tab' : '' }}">
            <div class="ed-kpi-top">
                <span class="ed-kpi-icon">📊</span>
            </div>
            <div class="ed-kpi-num">{{ $teamAllCount }}</div>
            <div class="ed-kpi-label">Team / All</div>
            @unless($isMarketingUser)
                <div class="ed-kpi-note">All active tasks</div>
            @endunless
        </a>
        @endif
        @if($canUseSelfTodoPilot)
        <a href="{{ route('execution-desk.index', ['tab' => 'self_todo']) }}"
           class="ed-kpi {{ $currentTab === 'self_todo' ? 'active-tab' : '' }}">
            <div class="ed-kpi-top">
                <span class="ed-kpi-icon">✅</span>
                @if(($selfTodoCounts['overdue'] ?? 0) > 0)
                    <span class="ed-kpi-trend up" style="background:var(--red-light);color:var(--red);">{{ $selfTodoCounts['overdue'] }} due</span>
                @endif
            </div>
            <div class="ed-kpi-num">{{ $selfTodoCounts['open'] ?? 0 }}</div>
            <div class="ed-kpi-label">My Todo</div>
            @unless($isMarketingUser)
                <div class="ed-kpi-note">Private checklist</div>
            @endunless
        </a>
        @endif
        <a href="{{ route('execution-desk.index', array_merge(request()->query(), ['tab' => $currentTab, 'view' => $currentView, 'due_filter' => 'overdue'])) }}"
           class="ed-kpi">
            <div class="ed-kpi-top">
                <span class="ed-kpi-icon">⚠</span>
            </div>
            <div class="ed-kpi-num" style="color: var(--red);">{{ $tasks->filter(fn ($t) => $t->due_at && $t->due_at->isPast() && !in_array($t->status, ['completed', 'closed', 'rejected']))->count() }}</div>
            <div class="ed-kpi-label">Overdue</div>
            @unless($isMarketingUser)
                <div class="ed-kpi-note">Needs attention</div>
            @endunless
        </a>
    </div>

    {{-- FILTER PANEL --}}
    @if($currentTab !== 'self_todo')
    <div class="ed-filter" id="executionDeskFilter">
        <button type="button" class="ed-mobile-filter-toggle" data-ed-filter-toggle aria-expanded="false">
            <span style="display:inline-flex;align-items:center;gap:8px;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M3 5h18"/><path d="M6 12h12"/><path d="M10 19h4"/></svg>
                Search & Filters
            </span>
            <span>Open</span>
        </button>
        <div class="ed-filter-panel">
            <form method="GET" action="{{ route('execution-desk.index') }}" class="ed-filter-row">
                <input type="hidden" name="tab" value="{{ $currentTab }}">
                <input type="hidden" name="view" value="{{ $currentView }}">
                <div class="ed-filter-search-wrap">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="ed-filter-input" placeholder="Search title, description, code...">
                </div>
                <div class="ed-filter-select-row">
                    <select name="status" class="ed-filter-select">
                        <option value="">All Status</option>
                        @foreach($statuses as $status)
                            <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucwords(str_replace('_', ' ', $status)) }}</option>
                        @endforeach
                    </select>
                    <select name="priority" class="ed-filter-select">
                        <option value="">All Priority</option>
                        @foreach($priorities as $priority)
                            <option value="{{ $priority }}" @selected(($filters['priority'] ?? '') === $priority)>{{ ucfirst($priority) }}</option>
                        @endforeach
                    </select>
                    <select name="context_label" class="ed-filter-select">
                        <option value="">All Context</option>
                        @foreach($contexts as $key => $label)
                            <option value="{{ $key }}" @selected(($filters['context_label'] ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <select name="due_filter" class="ed-filter-select">
                        <option value="">Any Due Date</option>
                        <option value="today" @selected(($filters['due_filter'] ?? '') === 'today')>Due Today</option>
                        <option value="overdue" @selected(($filters['due_filter'] ?? '') === 'overdue')>Overdue</option>
                    </select>
                </div>
                <div class="ed-filter-divider"></div>
                <div class="ed-filter-actions">
                    <a href="{{ route('execution-desk.index', array_filter(['tab' => $currentTab, 'view' => $currentView])) }}"
                       class="ed-filter-btn ed-filter-btn-reset">Reset</a>
                    <button type="submit" class="ed-filter-btn ed-filter-btn-apply">Apply</button>
                </div>
            </form>

            {{-- Save View --}}
            <form method="POST" action="{{ route('execution-desk.saved-views.store') }}" class="ed-save-row">
                @csrf
                <input type="text" name="name" class="ed-save-input" placeholder="Save current filter as view...">
                <input type="hidden" name="scope_tab" value="{{ $currentTab }}">
                <input type="hidden" name="filters[status]" value="{{ $filters['status'] ?? '' }}">
                <input type="hidden" name="filters[priority]" value="{{ $filters['priority'] ?? '' }}">
                <input type="hidden" name="filters[assigned_to]" value="{{ $filters['assigned_to'] ?? '' }}">
                <input type="hidden" name="filters[assigned_by]" value="{{ $filters['assigned_by'] ?? '' }}">
                <input type="hidden" name="filters[context_label]" value="{{ $filters['context_label'] ?? '' }}">
                <input type="hidden" name="filters[due_filter]" value="{{ $filters['due_filter'] ?? '' }}">
                <input type="hidden" name="filters[search]" value="{{ $filters['search'] ?? '' }}">
                @if(auth()->user()->isAdmin())
                    <label class="ed-save-check">
                        <input type="checkbox" name="is_shared" value="1"> Shared Admin View
                    </label>
                @endif
                <button type="submit" class="ed-btn ed-btn-primary" style="padding:7px 14px; font-size:12px; box-shadow:none;">Save View</button>
            </form>
        </div>

        {{-- Saved View Chips --}}
        @if($savedViews->isNotEmpty())
            <div style="display:flex; flex-wrap:wrap; gap:8px; margin-top:12px; padding-top:12px; border-top:1px solid var(--border);">
                @foreach($savedViews as $sv)
                    <a href="{{ route('execution-desk.index', ['saved_view' => $sv->id, 'tab' => $currentTab, 'view' => $currentView]) }}"
                       style="display:inline-flex; align-items:center; gap:6px; padding:5px 12px; border-radius:999px; border:1px solid var(--border); background:#f8f9fa; font-size:12px; font-weight:700; color:var(--text); text-decoration:none; transition:all .15s;"
                       onmouseover="this.style.borderColor='var(--green)';this.style.color='var(--green)'"
                       onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--text)'">
                        {{ $sv->name }}
                        @if($sv->is_shared)
                            <span style="font-size:10px; color:var(--blue);">Shared</span>
                        @endif
                    </a>
                @endforeach
            </div>
        @endif
    </div>
    @endif

    {{-- RESULT AREA --}}
    <div class="ed-result">
        <div class="ed-result-bar">
            <div class="ed-result-count">
                @if($currentTab === 'self_todo' && $canUseSelfTodoPilot)
                    <strong>{{ $selfTodos->count() }}</strong> private todos
                    &nbsp;·&nbsp;
                    <span style="color:#9ca3af">
                        {{ $selfTodoCounts['open'] ?? 0 }} open
                        · {{ $selfTodoCounts['today'] ?? 0 }} today
                        · {{ $selfTodoCounts['overdue'] ?? 0 }} due
                    </span>
                @else
                <strong>{{ $tasks->total() }}</strong> tasks found
                &nbsp;·&nbsp;
                <span style="color:#9ca3af">
                    {{ $tasks->where('status', 'open')->count() }} open
                    · {{ $tasks->where('status', 'in_progress')->count() }} in progress
                    · {{ $tasks->where('status', 'waiting')->count() }} waiting
                    · {{ $tasks->where('status', 'completed')->count() }} completed
                </span>
                @endif
            </div>
            <div class="ed-result-tabs">
                <a href="{{ route('execution-desk.index', array_merge(request()->query(), array_filter(['tab' => 'my_queue', 'view' => $currentView]))) }}"
                   class="ed-result-tab {{ $currentTab === 'my_queue' ? 'active' : '' }}">
                    My Queue <span class="count">{{ $myQueueCount }}</span>
                </a>
                <a href="{{ route('execution-desk.index', array_merge(request()->query(), array_filter(['tab' => 'assigned_by_me', 'view' => $currentView]))) }}"
                   class="ed-result-tab {{ $currentTab === 'assigned_by_me' ? 'active' : '' }}">
                    Assigned By Me <span class="count">{{ $assignedByMeCount }}</span>
                </a>
                @if(auth()->user()->canViewExecutionDeskTeamAll())
                <a href="{{ route('execution-desk.index', array_merge(request()->query(), array_filter(['tab' => 'team_all', 'view' => $currentView]))) }}"
                   class="ed-result-tab {{ $currentTab === 'team_all' ? 'active' : '' }}">
                    Team / All <span class="count">{{ $teamAllCount }}</span>
                </a>
                @endif
                @if($canUseSelfTodoPilot)
                <a href="{{ route('execution-desk.index', ['tab' => 'self_todo']) }}"
                   class="ed-result-tab {{ $currentTab === 'self_todo' ? 'active' : '' }}">
                    My Todo <span class="count">{{ $selfTodoCounts['open'] ?? 0 }}</span>
                </a>
                @endif
            </div>
        </div>

        @if($currentTab === 'self_todo' && $canUseSelfTodoPilot)
        <div class="ed-self-todo">
            <form method="POST" action="{{ route('execution-desk.self-todos.store') }}" class="ed-self-quick">
                @csrf
                <input type="text" name="title" class="ed-self-input" placeholder="Add quick private todo..." required maxlength="180">
                <input type="datetime-local" name="due_at" class="ed-self-input">
                <select name="priority" class="ed-self-select">
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                    <option value="low">Low</option>
                </select>
                <button type="submit" class="ed-btn ed-btn-primary" style="justify-content:center;">Add</button>
            </form>

            <div class="ed-self-chips">
                <a href="{{ route('execution-desk.index', ['tab' => 'self_todo', 'todo_filter' => 'today']) }}" class="ed-self-chip {{ $selfTodoFilter === 'today' ? 'active' : '' }}">Today</a>
                <a href="{{ route('execution-desk.index', ['tab' => 'self_todo', 'todo_filter' => 'pending']) }}" class="ed-self-chip {{ $selfTodoFilter === 'pending' ? 'active' : '' }}">Pending</a>
                <a href="{{ route('execution-desk.index', ['tab' => 'self_todo', 'todo_filter' => 'done']) }}" class="ed-self-chip {{ $selfTodoFilter === 'done' ? 'active' : '' }}">Done</a>
                <a href="{{ route('execution-desk.index', ['tab' => 'self_todo', 'todo_filter' => 'high']) }}" class="ed-self-chip {{ $selfTodoFilter === 'high' ? 'active' : '' }}">High Priority</a>
            </div>

            <div class="ed-self-list">
                @forelse($selfTodos as $todo)
                    @php
                        $isDone = $todo->status === \App\Models\SelfTodo::STATUS_COMPLETED;
                        $isDue = !$isDone && $todo->due_at && $todo->due_at->isPast();
                        $priority = $todo->priority ?: 'medium';
                    @endphp
                    <article class="ed-self-card {{ $isDone ? 'done' : '' }}">
                        <div class="ed-self-left">
                            <span class="ed-self-priority-bar {{ $priority }}"></span>
                            <form method="POST" action="{{ route('execution-desk.self-todos.complete', $todo) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="ed-self-check {{ $priority }}" aria-label="Toggle todo complete">{{ $isDone ? '✓' : '' }}</button>
                            </form>
                        </div>
                        <div>
                            <p class="ed-self-title">{{ $todo->title }}</p>
                            @if(filled($todo->note))
                                <p class="ed-self-note">{{ $todo->note }}</p>
                            @endif
                            <div class="ed-self-meta">
                                <span class="ed-self-pill {{ $priority }}">{{ ucfirst($priority) }}</span>
                                @if($todo->due_at)
                                    <span class="ed-self-pill {{ $isDue ? 'high' : '' }}">Due {{ $todo->due_at->format('d M, h:i A') }}</span>
                                @else
                                    <span class="ed-self-pill">No due time</span>
                                @endif
                                <span class="ed-self-pill">{{ $isDone ? 'Completed' : 'Private' }}</span>
                            </div>
                        </div>
                        <div class="ed-self-actions">
                            <details class="ed-self-details">
                                <summary class="ed-task-sm-btn ed-task-sm-btn-outline">Edit</summary>
                                <form method="POST" action="{{ route('execution-desk.self-todos.update', $todo) }}" class="ed-self-edit">
                                    @csrf
                                    @method('PUT')
                                    <input type="text" name="title" value="{{ $todo->title }}" class="ed-self-input" required maxlength="180">
                                    <select name="priority" class="ed-self-select">
                                        @foreach(['high', 'medium', 'low'] as $option)
                                            <option value="{{ $option }}" @selected($priority === $option)>{{ ucfirst($option) }}</option>
                                        @endforeach
                                    </select>
                                    <select name="status" class="ed-self-select">
                                        <option value="open" @selected(!$isDone)>Open</option>
                                        <option value="completed" @selected($isDone)>Completed</option>
                                    </select>
                                    <input type="datetime-local" name="due_at" value="{{ optional($todo->due_at)->format('Y-m-d\\TH:i') }}" class="ed-self-input">
                                    <textarea name="note" class="ed-self-input ed-self-note-input" rows="1" placeholder="Note">{{ $todo->note }}</textarea>
                                    <button type="submit" class="ed-task-sm-btn ed-task-sm-btn-update">Save</button>
                                </form>
                            </details>
                            <form method="POST" action="{{ route('execution-desk.self-todos.destroy', $todo) }}" onsubmit="return confirm('Delete this private todo?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="ed-task-sm-btn ed-self-delete">Delete</button>
                            </form>
                        </div>
                    </article>
                @empty
                    <div class="ed-empty">
                        <div class="ed-empty-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M9 11l3 3L22 4"/>
                                <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                            </svg>
                        </div>
                        <div class="ed-empty-title">No private todos found</div>
                        <div class="ed-empty-desc">Add a quick todo above. It stays private and does not affect team task reports.</div>
                    </div>
                @endforelse
            </div>
        </div>
        @else

        {{-- LIST VIEW --}}
        <div id="ed-view-list" class="{{ $currentView !== 'list' ? 'hidden' : '' }}">
            <div class="ed-task-list">
                @forelse($tasks as $task)
                    @php
                        $ctx = $task->context_label ?? 'admin';
                        $pri = $task->priority ?? 'medium';
                        $sts = $task->status ?? 'open';
                        $isOverdue = $task->due_at && $task->due_at->isPast() && !in_array($sts, ['completed', 'closed', 'rejected']);
                        $assigneeInitials = $task->assignee
                            ? collect(explode(' ', $task->assignee->name))->map(fn ($n) => strtoupper($n[0] ?? ''))->take(2)->implode('')
                            : '?';
                        $assigneeColor = $task->assignee
                            ? '#' . substr(md5($task->assignee->id), 0, 6)
                            : '#9ca3af';
                        $creatorInit = $task->creator
                            ? collect(explode(' ', $task->creator->name))->map(fn ($n) => strtoupper($n[0] ?? ''))->take(2)->implode('')
                            : '?';
                        $creatorCol = $task->creator
                            ? '#' . substr(md5($task->creator->id), 0, 6)
                            : '#9ca3af';
                    @endphp
                    <div class="ed-task-card">
                        <div class="ed-task-top">
                            <span class="ed-task-id">{{ $task->task_code ?? $task->id }}</span>
                            <span class="ed-task-title">
                                <a href="{{ route('execution-desk.tasks.show', $task) }}">{{ $task->title }}</a>
                            </span>
                            <span class="ed-badge {{ $priorityBadgeClass[$pri] ?? 'ed-badge-pl' }}">{{ $priorityLabels[$pri] ?? ucfirst($pri) }}</span>
                            <span class="ed-badge {{ $contextBadgeClass[$ctx] ?? 'ed-badge-admin' }}">{{ $contextLabels[$ctx] ?? ucfirst($ctx) }}</span>
                        </div>
                        <div class="ed-task-desc">{{ Str::limit($task->description ?? 'No description added.', 120) }}</div>
                        <div class="ed-task-footer">
                            <div class="ed-task-people">
                                <div class="ed-task-person">
                                    <span class="ed-task-person-label">By</span>
                                    <div class="ed-task-avatar" style="background:{{ $creatorCol }}">{{ $creatorInit }}</div>
                                    <span class="ed-task-person-name">{{ $task->creator->name ?? 'Unknown' }}</span>
                                </div>
                                <div class="ed-task-person">
                                    <span class="ed-task-person-label">To</span>
                                    <div class="ed-task-avatar" style="background:{{ $assigneeColor }}">{{ $assigneeInitials }}</div>
                                    <span class="ed-task-person-name">{{ $task->assignee->name ?? 'Unassigned' }}</span>
                                </div>
                                @if($task->due_at)
                                    <div class="ed-task-person" style="margin-left:4px;">
                                        <span class="ed-task-person-label">Due</span>
                                        <span class="ed-task-due {{ $isOverdue ? 'overdue' : '' }}">{{ $task->due_at->format('d M Y') }}</span>
                                        @if($isOverdue)
                                            <span class="ed-task-flag">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                                Overdue
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                            <div class="ed-task-right">
                                <span class="ed-badge {{ $statusBadgeClass[$sts] ?? 'ed-badge-sopen' }}">
                                    <span class="dot" style="background:{{ $statusDotColor[$sts] ?? '#E65100' }}"></span>
                                    {{ ucwords(str_replace('_', ' ', $sts)) }}
                                </span>
                                <div class="ed-task-actions">
                                    <form method="POST" action="{{ route('execution-desk.tasks.status', $task) }}" class="ed-task-form">
                                        @csrf
                                        @method('PUT')
                                        <select name="status" class="ed-task-select">
                                            <option value="{{ $task->status }}" selected>{{ ucwords(str_replace('_', ' ', $task->status)) }}</option>
                                            @foreach($allowedTransitions[$task->status] ?? [] as $s)
                                                <option value="{{ $s }}">{{ ucwords(str_replace('_', ' ', $s)) }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="ed-task-sm-btn ed-task-sm-btn-update">Update</button>
                                    </form>
                                    <form method="GET" action="{{ route('execution-desk.tasks.show', $task) }}" class="ed-task-form">
                                        <button type="submit" class="ed-task-sm-btn ed-task-sm-btn-outline">View</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="ed-empty">
                        <div class="ed-empty-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M9 11l3 3L22 4"/>
                                <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                            </svg>
                        </div>
                        <div class="ed-empty-title">No execution tasks found</div>
                        <div class="ed-empty-desc">Create your first task or adjust the filters to see results from your team queue.</div>
                        <a href="{{ route('execution-desk.create') }}" class="ed-btn ed-btn-primary">Create First Task</a>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- KANBAN VIEW --}}
        <div id="ed-view-kanban" class="{{ $currentView !== 'kanban' ? 'hidden' : '' }}">
            <div class="ed-kanban-board">
                @foreach($kanbanCols as $backendStatus => $col)
                    @php
                        $colTasks = $tasks->filter(fn ($t) => $t->status === $backendStatus);
                    @endphp
                    <div class="ed-kanban-col">
                        <div class="ed-kanban-head">
                            <div class="ed-kanban-head-title">
                                <span class="ed-kanban-count {{ $col['count_class'] }}">{{ $colTasks->count() }}</span>
                                <span class="ed-kanban-name">{{ $col['label'] }}</span>
                            </div>
                        </div>
                        <div class="ed-kanban-body">
                            @forelse($colTasks as $task)
                                @php
                                    $ctx = $task->context_label ?? 'admin';
                                    $pri = $task->priority ?? 'medium';
                                    $isOverdue = $task->due_at && $task->due_at->isPast() && !in_array($task->status, ['completed', 'closed', 'rejected']);
                                    $assigneeInit = $task->assignee
                                        ? collect(explode(' ', $task->assignee->name))->map(fn ($n) => strtoupper($n[0] ?? ''))->take(2)->implode('')
                                        : '?';
                                    $assigneeCol = $task->assignee
                                        ? '#' . substr(md5($task->assignee->id), 0, 6)
                                        : '#9ca3af';
                                @endphp
                                <div class="ed-task-card">
                                    <div class="ed-task-top">
                                        <span class="ed-task-id">{{ $task->task_code ?? $task->id }}</span>
                                        <span class="ed-badge {{ $priorityBadgeClass[$pri] ?? 'ed-badge-pl' }}" style="padding:2px 7px; font-size:9.5px;">{{ $priorityLabels[$pri] ?? ucfirst($pri) }}</span>
                                    </div>
                                    <div class="ed-task-title" style="margin-bottom:6px;">
                                        <a href="{{ route('execution-desk.tasks.show', $task) }}">{{ $task->title }}</a>
                                    </div>
                                    <div class="ed-task-context">
                                        <span class="ed-badge {{ $contextBadgeClass[$ctx] ?? 'ed-badge-admin' }}" style="font-size:10px; padding:2px 8px;">
                                            {{ $contextLabels[$ctx] ?? ucfirst($ctx) }}
                                        </span>
                                    </div>
                                    <div class="ed-task-footer">
                                        <div class="ed-task-person">
                                            <div class="ed-task-avatar" style="background:{{ $assigneeCol }}">{{ $assigneeInit }}</div>
                                            <span class="ed-task-person-name">{{ $task->assignee->name ?? 'Unassigned' }}</span>
                                        </div>
                                        <div class="ed-task-right" style="gap:6px;">
                                            @if($task->due_at)
                                                <span class="ed-task-due {{ $isOverdue ? 'overdue' : '' }}" style="font-size:10.5px;">{{ $task->due_at->format('d M') }}</span>
                                            @endif
                                            @if($isOverdue)
                                                <span class="ed-task-flag">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:11px;height:11px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div style="display:flex; align-items:center; justify-content:center; padding:32px 12px; color:#9ca3af; font-size:12px; font-weight:600; text-align:center;">
                                    No tasks
                                </div>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- PAGINATION --}}
        @if($tasks->hasPages())
            <div class="ed-pagination">
                <div class="ed-pagination-text">
                    Showing {{ $tasks->firstItem() }}–{{ $tasks->lastItem() }} of {{ $tasks->total() }} tasks
                </div>
                <div class="ed-pagination-pages">
                    @if($tasks->onFirstPage())
                        <span class="ed-page-btn" style="opacity:.4; cursor:not-allowed;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                        </span>
                    @else
                        <a href="{{ $tasks->previousPageUrl() . '&view=' . $currentView . '&tab=' . $currentTab }}" class="ed-page-btn">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                        </a>
                    @endif

                    @foreach($tasks->getUrlRange(max(1, $tasks->currentPage() - 2), min($tasks->lastPage(), $tasks->currentPage() + 2)) as $page => $url)
                        <a href="{{ $url . '&view=' . $currentView . '&tab=' . $currentTab }}"
                           class="ed-page-btn {{ $page == $tasks->currentPage() ? 'active' : '' }}">
                            {{ $page }}
                        </a>
                    @endforeach

                    @if($tasks->hasMorePages())
                        <a href="{{ $tasks->nextPageUrl() . '&view=' . $currentView . '&tab=' . $currentTab }}" class="ed-page-btn">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                        </a>
                    @else
                        <span class="ed-page-btn" style="opacity:.4; cursor:not-allowed;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                        </span>
                    @endif
                </div>
            </div>
        @endif
        @endif
    </div>

    <div class="ed-mobile-task-modal" id="mobileTaskCreateModal" aria-hidden="true" data-has-errors="{{ $errors->any() ? '1' : '0' }}">
        <div class="ed-mobile-task-sheet" role="dialog" aria-modal="true" aria-labelledby="mobileTaskCreateTitle">
            <div class="ed-mobile-task-head">
                <h3 id="mobileTaskCreateTitle">Assign Task</h3>
                <button type="button" class="ed-mobile-task-close" data-mobile-task-close aria-label="Close">&times;</button>
            </div>
            @if($errors->any())
                <div class="ed-mobile-task-errors">
                    {{ $errors->first() }}
                </div>
            @endif
            <form method="POST" action="{{ route('execution-desk.tasks.store') }}" enctype="multipart/form-data" class="ed-mobile-task-form">
                @csrf
                <div class="ed-mobile-task-field">
                    <label>Title</label>
                    <input type="text" name="title" value="{{ old('title') }}" required placeholder="Task title">
                </div>
                <div class="ed-mobile-task-field">
                    <label>Assign To</label>
                    @if($isMarketingExecutive && $users->count() === 1)
                        <input type="hidden" name="assigned_to" value="{{ $users->first()->id }}">
                        <input type="text" value="{{ $users->first()->name }}" disabled>
                    @else
                        <select name="assigned_to" required>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" @selected((string) old('assigned_to') === (string) $user->id)>{{ $user->name }} ({{ $user->getDisplayRoleName() }})</option>
                            @endforeach
                        </select>
                    @endif
                </div>
                <div class="ed-mobile-task-grid">
                    <div class="ed-mobile-task-field">
                        <label>Priority</label>
                        <select name="priority" required>
                            @foreach($priorities as $priority)
                                <option value="{{ $priority }}" @selected(old('priority', 'medium') === $priority)>{{ ucfirst($priority) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ed-mobile-task-field">
                        <label>Context</label>
                        <select name="context_label" required>
                            @foreach($contexts as $key => $label)
                                <option value="{{ $key }}" @selected(old('context_label', $isMarketingUser ? 'marketing' : '') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="ed-mobile-task-field">
                    <label>Due Date & Time (Optional)</label>
                    <input type="datetime-local" name="due_at" value="{{ old('due_at') }}" placeholder="Select date and time">
                    <span class="ed-mobile-task-help">Date/time select karo ya blank chhod do.</span>
                </div>
                <div class="ed-mobile-task-field">
                    <label>Description</label>
                    <textarea name="description" placeholder="Task details">{{ old('description') }}</textarea>
                </div>
                <button type="submit" class="ed-btn ed-btn-primary ed-mobile-task-submit">Create Task</button>
            </form>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const filterBox = document.getElementById('executionDeskFilter');
        const toggle = document.querySelector('[data-ed-filter-toggle]');
        if (!filterBox || !toggle) {
            return;
        }

        toggle.addEventListener('click', function () {
            const isOpen = filterBox.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            const stateText = toggle.querySelector('span:last-child');
            if (stateText) {
                stateText.textContent = isOpen ? 'Close' : 'Open';
            }
        });

        const createLink = document.querySelector('[data-mobile-task-create]');
        const taskModal = document.getElementById('mobileTaskCreateModal');
        const closeButtons = document.querySelectorAll('[data-mobile-task-close]');
        const isPhone = () => window.matchMedia('(max-width: 600px)').matches;
        const openTaskModal = () => {
            taskModal?.classList.add('is-open');
            taskModal?.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        };
        const closeTaskModal = () => {
            taskModal?.classList.remove('is-open');
            taskModal?.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        };

        if (createLink && taskModal) {
            createLink.addEventListener('click', function (event) {
                if (!isPhone()) {
                    return;
                }
                event.preventDefault();
                openTaskModal();
            });
        }

        if (taskModal?.dataset.hasErrors === '1' && isPhone()) {
            openTaskModal();
        }

        closeButtons.forEach((button) => {
            button.addEventListener('click', closeTaskModal);
        });

        taskModal?.addEventListener('click', function (event) {
            if (event.target === taskModal) {
                closeTaskModal();
            }
        });
    });
</script>
@endpush

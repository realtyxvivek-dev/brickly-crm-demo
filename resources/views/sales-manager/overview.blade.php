@extends($leadBoardLayout ?? 'sales-manager.layout')

@section('title', 'Lead Board')
@section('page-title', 'Lead Board')

@section('content')
<style>
    html,
    body {
        max-width: 100%;
    }
    body.kanban-overview-active {
        overflow-x: hidden !important;
        background: #F3F8FC;
    }
    body.kanban-overview-active .header {
        display: none !important;
    }
    body.kanban-overview-active .container,
    body.kanban-overview-active .main-content,
    body.kanban-overview-active .content,
    body.kanban-overview-active .page-content,
    body.kanban-overview-active .dashboard-content,
    body.kanban-overview-active .sales-manager-content,
    body.kanban-overview-active main {
        max-width: 100vw;
        min-width: 0;
        overflow-x: hidden;
        overflow-x: clip;
        box-sizing: border-box;
    }
    .kanban-shell {
        display: flex;
        flex-direction: column;
        padding: 18px clamp(10px, 1.4vw, 22px) 8px;
        width: 100%;
        max-width: 100%;
        min-height: calc(100vh - 32px);
        min-width: 0;
        overflow-x: hidden;
        box-sizing: border-box;
        background:
            radial-gradient(circle at 6% 4%, rgba(0, 107, 166, .14), transparent 26%),
            radial-gradient(circle at 88% 8%, rgba(47, 128, 237, .13), transparent 28%),
            radial-gradient(circle at 50% 100%, rgba(245, 158, 11, .08), transparent 24%),
            linear-gradient(135deg, #F4FAFF 0%, #EAF6FD 48%, #F7FBFF 100%);
    }
    .kanban-filter-card,
    .kanban-summary-card,
    .kanban-column {
        background: #fff;
        border: 1px solid rgba(0, 107, 166, .14);
        border-radius: 22px;
        box-shadow: 0 18px 45px rgba(0, 43, 69, .08);
    }
    .kanban-hero {
        position: relative;
        overflow: hidden;
        padding: 28px;
        margin-bottom: 18px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        background: linear-gradient(135deg, #ffffff 0%, #EAF6FD 50%, #eaf3ff 100%);
    }
    .kanban-hero::after {
        content: "";
        position: absolute;
        right: -90px;
        top: -95px;
        width: 260px;
        height: 260px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(0, 107, 166, .16), rgba(0, 107, 166, 0) 68%);
        pointer-events: none;
    }
    .kanban-hero > * {
        position: relative;
        z-index: 1;
    }
    .kanban-kicker {
        color: #5B7182;
        font-weight: 800;
        letter-spacing: .14em;
        font-size: 12px;
        text-transform: uppercase;
        margin-bottom: 8px;
    }
    .kanban-title {
        color: #002B45;
        font-size: clamp(28px, 4vw, 44px);
        font-weight: 900;
        line-height: 1.05;
        margin: 0;
    }
    .kanban-subtitle {
        color: #5B7182;
        margin: 10px 0 0;
        font-size: 15px;
    }
    .kanban-filter-card {
        padding: 18px;
        margin-bottom: 18px;
    }
    .kanban-quick-row {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 14px;
        overflow-x: auto;
        overscroll-behavior-x: contain;
        padding: 2px 0 8px;
        scrollbar-color: #006BA6 #EAF6FD;
        scrollbar-width: thin;
    }
    .kanban-quick-row::-webkit-scrollbar {
        height: 8px;
    }
    .kanban-quick-row::-webkit-scrollbar-track {
        border-radius: 999px;
        background: #EAF6FD;
    }
    .kanban-quick-row::-webkit-scrollbar-thumb {
        border-radius: 999px;
        background: #006BA6;
    }
    .kanban-quick-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        min-height: 42px;
        border: 1px solid rgba(0, 107, 166, .18);
        border-radius: 999px;
        padding: 0 16px;
        background: #fff;
        color: #002B45;
        font-size: 13px;
        font-weight: 900;
        white-space: nowrap;
        cursor: pointer;
        box-shadow: 0 12px 28px rgba(0, 43, 69, .07);
        transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease, background .18s ease, color .18s ease;
    }
    .kanban-quick-btn i {
        color: #006BA6;
        font-size: 13px;
    }
    .kanban-quick-btn:hover,
    .kanban-quick-btn.active {
        transform: translateY(-1px);
        border-color: #006BA6;
        background: #006BA6;
        color: #fff;
        box-shadow: 0 16px 34px rgba(0, 107, 166, .16);
    }
    .kanban-quick-btn:hover i,
    .kanban-quick-btn.active i {
        color: #fff;
    }
    .kanban-filter-card.is-collapsed {
        display: none;
    }
    .kanban-filter-grid {
        display: grid;
        grid-template-columns: minmax(180px, 1.4fr) repeat(6, minmax(130px, 1fr)) auto;
        gap: 12px;
        align-items: end;
    }
    .kanban-view-bar {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 8px;
        margin-top: 14px;
        padding-top: 14px;
        border-top: 1px solid #D7EAF6;
    }
    .kanban-view-btn {
        height: 38px;
        border: 1px solid #CDE5F5;
        border-radius: 999px;
        padding: 0 14px;
        background: #fff;
        color: #174966;
        font-size: 12px;
        font-weight: 900;
        cursor: pointer;
        transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease, background .18s ease;
    }
    .kanban-view-btn:hover {
        transform: translateY(-1px);
        border-color: #0077B6;
        box-shadow: 0 10px 24px rgba(0, 107, 166, .12);
    }
    .kanban-view-btn.active {
        border-color: #006BA6;
        background: #006BA6;
        color: #fff;
    }
    .kanban-field label {
        display: block;
        margin-bottom: 6px;
        color: #5B7182;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: .08em;
        text-transform: uppercase;
    }
    .kanban-field input,
    .kanban-field select {
        width: 100%;
        height: 44px;
        border: 1px solid #CDE5F5;
        border-radius: 14px;
        padding: 0 13px;
        color: #102A3A;
        background: #fff;
        font-weight: 700;
    }
    .kanban-actions {
        display: flex;
        gap: 8px;
    }
    .kanban-btn {
        height: 44px;
        border: 0;
        border-radius: 14px;
        padding: 0 16px;
        font-weight: 900;
        cursor: pointer;
        white-space: nowrap;
        transition: transform .18s ease, box-shadow .18s ease, background .18s ease;
    }
    .kanban-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 12px 24px rgba(15, 35, 30, .12);
    }
    .kanban-btn.primary {
        background: linear-gradient(135deg, #006BA6 0%, #0077B6 100%);
        color: #fff;
        box-shadow: 0 12px 24px rgba(0, 107, 166, .16);
    }
    .kanban-btn.light {
        background: #eef5f1;
        color: #19382d;
    }
    .kanban-summary {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 10px;
        margin-bottom: 12px;
    }
    .kanban-summary-card {
        position: relative;
        overflow: hidden;
        min-height: 86px;
        padding: 12px 14px;
        transition: transform .18s ease, box-shadow .18s ease;
    }
    .kanban-summary-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 20px 46px rgba(15, 35, 30, .12);
    }
    .kanban-summary-card::after {
        content: "";
        position: absolute;
        right: -28px;
        bottom: -34px;
        width: 88px;
        height: 88px;
        border-radius: 50%;
        background: rgba(5, 97, 63, .08);
    }
    .kanban-summary-card:nth-child(2)::after {
        background: rgba(47, 128, 237, .10);
    }
    .kanban-summary-card:nth-child(3)::after {
        background: rgba(245, 158, 11, .10);
    }
    .kanban-summary-card:nth-child(4)::after {
        background: rgba(220, 38, 38, .08);
    }
    .kanban-summary-card:nth-child(5)::after {
        background: rgba(5, 97, 63, .10);
    }
    .kanban-summary-card strong {
        display: block;
        color: #03291d;
        font-size: 24px;
        line-height: 1;
        margin-bottom: 6px;
    }
    .kanban-summary-card span {
        color: #5B7182;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: .08em;
        text-transform: uppercase;
    }
    .kanban-filter-summary {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        align-items: center;
        gap: 6px 10px;
    }
    .kanban-filter-summary strong,
    .kanban-filter-summary span {
        grid-column: 1;
    }
    .kanban-filter-toggle {
        position: relative;
        z-index: 1;
        grid-column: 2;
        grid-row: 1 / span 3;
        min-width: 76px;
        height: 34px;
        border: 0;
        border-radius: 12px;
        background: #006BA6;
        color: #fff;
        font-size: 11px;
        font-weight: 900;
        cursor: pointer;
        box-shadow: 0 12px 24px rgba(5, 97, 63, .16);
        transition: transform .18s ease, box-shadow .18s ease, background .18s ease;
    }
    .kanban-filter-toggle:hover {
        transform: translateY(-1px);
        background: #0077B6;
        box-shadow: 0 16px 30px rgba(5, 97, 63, .22);
    }
    .kanban-inline-user-filter {
        position: relative;
        z-index: 1;
        grid-column: 1 / -1;
        max-width: calc(100% - 84px);
    }
    .kanban-inline-user-filter select {
        width: 100%;
        height: 30px;
        border: 1px solid #CDE5F5;
        border-radius: 10px;
        padding: 0 10px;
        background: #fff;
        color: #082c20;
        font-size: 11px;
        font-weight: 900;
        outline: none;
    }
    .kanban-inline-user-filter select:focus {
        border-color: #006BA6;
        box-shadow: 0 0 0 3px rgba(5, 97, 63, .10);
    }
    .kanban-board-wrap {
        flex: 1;
        max-width: 100%;
        width: 100%;
        overflow-x: auto;
        overflow-y: hidden;
        overscroll-behavior-x: contain;
        padding-bottom: 8px;
        min-height: max(560px, calc(100vh - 185px));
        scrollbar-color: #006BA6 #EAF6FD;
        scrollbar-width: thin;
    }
    .kanban-board-wrap::-webkit-scrollbar {
        height: 14px;
    }
    .kanban-board-wrap::-webkit-scrollbar-track {
        border-radius: 999px;
        background: #EAF6FD;
    }
    .kanban-board-wrap::-webkit-scrollbar-thumb {
        border: 3px solid #EAF6FD;
        border-radius: 999px;
        background: #006BA6;
    }
    .kanban-board-wrap::-webkit-scrollbar-thumb:hover {
        background: #0077B6;
    }
    .kanban-board {
        display: grid;
        grid-auto-flow: column;
        grid-auto-columns: minmax(260px, 300px);
        gap: 14px;
        width: max-content;
        min-width: 100%;
        min-height: max(560px, calc(100vh - 185px));
        align-items: stretch;
    }
    .kanban-board.list-mode {
        grid-auto-columns: minmax(340px, 390px);
    }
    .kanban-column {
        position: relative;
        padding: 14px;
        background: #fbfdfb;
        border-top: 4px solid #19a974;
        height: max(560px, calc(100vh - 185px));
        max-height: none;
        overflow-y: auto;
        transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
    }
    .kanban-column:nth-child(2n) {
        border-top-color: #2f80ed;
    }
    .kanban-column:nth-child(3n) {
        border-top-color: #7c3aed;
    }
    .kanban-column:nth-child(4n) {
        border-top-color: #f59e0b;
    }
    .kanban-column:hover {
        transform: translateY(-2px);
        box-shadow: 0 20px 50px rgba(15, 35, 30, .12);
    }
    .kanban-column-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        padding: 6px 4px 12px;
        border-bottom: 1px solid #D7EAF6;
        margin-bottom: 12px;
    }
    .kanban-column-head h3 {
        margin: 0;
        font-size: 15px;
        color: #082c20;
        font-weight: 900;
    }
    .kanban-head-tools {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        min-width: 0;
    }
    .kanban-cnp-filter {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        padding: 3px;
        border: 1px solid #CDE5F5;
        border-radius: 999px;
        background: #f8fcfa;
    }
    .kanban-cnp-filter button {
        height: 22px;
        border: 0;
        border-radius: 999px;
        padding: 0 7px;
        background: transparent;
        color: #49645a;
        font-size: 10px;
        font-weight: 900;
        cursor: pointer;
        white-space: nowrap;
    }
    .kanban-cnp-filter button.active {
        background: #006BA6;
        color: #fff;
    }
    .kanban-count {
        min-width: 28px;
        padding: 5px 8px;
        border-radius: 999px;
        background: #EAF6FD;
        color: #006BA6;
        text-align: center;
        font-size: 12px;
        font-weight: 900;
    }
    .kanban-card {
        position: relative;
        overflow: hidden;
        background: #fff;
        border: 1px solid #dfeae4;
        border-radius: 18px;
        padding: 14px;
        margin-bottom: 12px;
        box-shadow: 0 8px 22px rgba(8, 44, 32, .05);
        transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
    }
    .kanban-card::before {
        content: "";
        position: absolute;
        left: 0;
        top: 14px;
        bottom: 14px;
        width: 4px;
        border-radius: 0 999px 999px 0;
        background: linear-gradient(180deg, #10b981, #2f80ed);
    }
    .kanban-card:hover {
        transform: translateY(-2px);
        border-color: rgba(5, 97, 63, .28);
        box-shadow: 0 16px 34px rgba(8, 44, 32, .12);
    }
    .kanban-card h4 {
        margin: 0 0 8px;
        color: #061f18;
        font-size: 16px;
        font-weight: 900;
    }
    .kanban-meta {
        display: grid;
        gap: 6px;
        color: #66766f;
        font-size: 12px;
        font-weight: 700;
        margin-bottom: 12px;
    }
    .kanban-pill-row {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-bottom: 12px;
    }
    .kanban-pill {
        border-radius: 999px;
        padding: 5px 8px;
        background: #f1f6f3;
        color: #315244;
        font-size: 11px;
        font-weight: 800;
    }
    .kanban-card-actions {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
    }
    .kanban-card-actions a {
        text-decoration: none;
        border-radius: 12px;
        padding: 9px 8px;
        text-align: center;
        background: #eef5f1;
        color: #063525;
        font-size: 12px;
        font-weight: 900;
        transition: transform .16s ease, background .16s ease, box-shadow .16s ease;
    }
    .kanban-card-actions a:hover {
        transform: translateY(-1px);
        background: #e0f2ea;
        box-shadow: 0 8px 18px rgba(0, 43, 69, .08);
    }
    .kanban-card-actions a.primary {
        background: linear-gradient(135deg, #006BA6 0%, #0077B6 100%);
        color: #fff;
    }
    .kanban-empty {
        padding: 18px 8px;
        color: #8a9a93;
        text-align: center;
        font-weight: 800;
        border: 1px dashed #CDE5F5;
        border-radius: 16px;
        background: #fff;
    }
    .kanban-list-card {
        display: grid;
        grid-template-columns: minmax(0, 1.2fr) minmax(105px, .9fr) auto;
        align-items: center;
        gap: 10px;
        padding: 10px 10px 10px 12px;
        margin-bottom: 8px;
        background: #fff;
        border: 1px solid #dfeae4;
        border-radius: 14px;
        box-shadow: 0 6px 16px rgba(8, 44, 32, .05);
    }
    .kanban-list-card:hover {
        border-color: rgba(5, 97, 63, .28);
        box-shadow: 0 10px 22px rgba(8, 44, 32, .09);
    }
    .kanban-list-card.is-hot {
        border-left: 4px solid #dc2626;
        background: #fff7f7;
    }
    .kanban-list-card.is-warm {
        border-left: 4px solid #d97706;
        background: #fffbeb;
    }
    .kanban-list-card.is-cold {
        border-left: 4px solid #2563eb;
        background: #eff6ff;
    }
    .kanban-list-name {
        min-width: 0;
    }
    .kanban-list-name strong,
    .kanban-list-phone strong {
        display: block;
        overflow: hidden;
        color: #061f18;
        font-size: 13px;
        font-weight: 900;
        line-height: 1.25;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .kanban-temperature {
        display: inline-flex;
        align-items: center;
        margin-left: 6px;
        padding: 2px 7px;
        border-radius: 999px;
        font-size: 9px;
        font-weight: 900;
        line-height: 1.2;
        vertical-align: 1px;
        text-transform: uppercase;
    }
    .kanban-temperature.hot {
        background: #fee2e2;
        color: #991b1b;
    }
    .kanban-temperature.warm {
        background: #fef3c7;
        color: #92400e;
    }
    .kanban-temperature.cold {
        background: #dbeafe;
        color: #1e3a8a;
    }
    .kanban-list-label {
        display: block;
        margin-bottom: 2px;
        color: #5B7182;
        font-size: 10px;
        font-weight: 900;
        letter-spacing: .08em;
        text-transform: uppercase;
    }
    .kanban-list-phone {
        min-width: 0;
    }
    .kanban-action-menu {
        position: relative;
        display: inline-block;
    }
    .kanban-action-menu summary {
        list-style: none;
        border-radius: 12px;
        padding: 9px 12px;
        background: #006BA6;
        color: #fff;
        font-size: 12px;
        font-weight: 900;
        cursor: pointer;
        transition: transform .16s ease, box-shadow .16s ease;
    }
    .kanban-action-menu summary:hover {
        transform: translateY(-1px);
        box-shadow: 0 10px 22px rgba(0, 107, 166, .16);
    }
    .kanban-action-menu summary::-webkit-details-marker {
        display: none;
    }
    .kanban-action-list {
        position: absolute;
        right: 0;
        top: calc(100% + 8px);
        z-index: 20;
        display: grid;
        min-width: 170px;
        padding: 8px;
        border: 1px solid #dbe8e1;
        border-radius: 14px;
        background: #fff;
        box-shadow: 0 18px 38px rgba(13, 35, 28, .16);
    }
    .kanban-action-list a {
        padding: 9px 10px;
        border-radius: 10px;
        color: #063525;
        font-size: 12px;
        font-weight: 900;
        text-decoration: none;
    }
    .kanban-action-list a:hover {
        background: #EAF6FD;
    }
    @media (max-width: 1100px) {
        .kanban-filter-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .kanban-actions {
            grid-column: 1 / -1;
        }
        .kanban-summary {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }
    @media (max-width: 720px) {
        .kanban-shell {
            padding: 12px 8px 16px;
        }
        .kanban-hero {
            display: block;
            padding: 20px;
        }
        .kanban-filter-grid,
        .kanban-summary {
            grid-template-columns: 1fr;
        }
        .kanban-board {
            grid-auto-columns: minmax(245px, 84vw);
            min-width: max-content;
        }
        .kanban-board-wrap {
            margin: 0 -2px;
            padding-left: 2px;
        }
        .kanban-view-bar {
            justify-content: stretch;
        }
        .kanban-view-btn {
            flex: 1;
        }
        .kanban-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
        }
        .kanban-board.list-mode {
            grid-auto-columns: minmax(286px, 88vw);
        }
        .kanban-list-card {
            grid-template-columns: minmax(0, 1fr) auto;
        }
        .kanban-list-phone {
            grid-column: 1 / -1;
            grid-row: 2;
        }
        .kanban-list-card .kanban-action-menu {
            grid-column: 2;
            grid-row: 1;
        }
        .kanban-btn {
            width: 100%;
        }
    }
</style>

<div class="kanban-shell">
    <div class="kanban-quick-row" aria-label="Lead Board quick navigation">
        <button type="button" class="kanban-quick-btn active" data-board-jump="all"><i class="fas fa-layer-group"></i>All Leads</button>
        <button type="button" class="kanban-quick-btn" data-board-jump="fresh"><i class="fas fa-seedling"></i>Fresh</button>
        <button type="button" class="kanban-quick-btn" data-board-jump="cnp"><i class="fas fa-phone-slash"></i>CNP</button>
        <button type="button" class="kanban-quick-btn" data-board-jump="follow_up"><i class="fas fa-phone-volume"></i>Follow-ups</button>
        <button type="button" class="kanban-quick-btn" data-board-jump="meeting_scheduled"><i class="fas fa-handshake"></i>Meetings</button>
        <button type="button" class="kanban-quick-btn" data-board-jump="visit_scheduled"><i class="fas fa-map-marker-alt"></i>Visits</button>
        <button type="button" class="kanban-quick-btn" data-board-jump="closer"><i class="fas fa-circle-check"></i>Closers</button>
        <button type="button" class="kanban-quick-btn" data-filter-toggle><i class="fas fa-sliders"></i>Filter</button>
    </div>

    <section id="kanbanFilterPanel" class="kanban-filter-card is-collapsed" aria-hidden="true">
        <form id="kanbanFilterForm" class="kanban-filter-grid">
            <input type="hidden" name="view_mode" id="kanbanViewMode" value="{{ $filters['view_mode'] ?? 'list' }}">
            <div class="kanban-field">
                <label>Search</label>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Name, phone, email">
            </div>
            <div class="kanban-field">
                <label>Date</label>
                <select name="date_filter" id="kanbanDateFilter">
                    <option value="all" @selected(($filters['date_filter'] ?? 'all') === 'all')>All</option>
                    <option value="today" @selected(($filters['date_filter'] ?? '') === 'today')>Today</option>
                    <option value="week" @selected(($filters['date_filter'] ?? '') === 'week')>This Week</option>
                    <option value="month" @selected(($filters['date_filter'] ?? '') === 'month')>This Month</option>
                    <option value="year" @selected(($filters['date_filter'] ?? '') === 'year')>This Year</option>
                    <option value="custom" @selected(($filters['date_filter'] ?? '') === 'custom')>Custom</option>
                </select>
            </div>
            <div class="kanban-field">
                <label>From</label>
                <input type="date" name="start_date" value="{{ $filters['start_date'] ?? '' }}">
            </div>
            <div class="kanban-field">
                <label>To</label>
                <input type="date" name="end_date" value="{{ $filters['end_date'] ?? '' }}">
            </div>
            <div class="kanban-field">
                <label>Assigned</label>
                <select name="assigned_to">
                    @if(($filterOptions['scope']['show_all_option'] ?? true))
                        <option value="">{{ $filterOptions['scope']['label'] ?? 'All Team' }}</option>
                    @endif
                    @foreach($filterOptions['users'] ?? [] as $teamUser)
                        <option value="{{ $teamUser->id }}" @selected((string)($filters['assigned_to'] ?? '') === (string)$teamUser->id)>{{ $teamUser->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="kanban-field">
                <label>Source</label>
                <select name="source">
                    <option value="">All Sources</option>
                    @foreach($filterOptions['sources'] ?? [] as $source)
                        <option value="{{ $source }}" @selected(($filters['source'] ?? '') === $source)>{{ $source }}</option>
                    @endforeach
                </select>
            </div>
            <div class="kanban-field">
                <label>Status</label>
                <select name="status">
                    <option value="">All Status</option>
                    @foreach($filterOptions['statuses'] ?? [] as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucwords(str_replace('_', ' ', $status)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="kanban-actions">
                <button type="submit" class="kanban-btn primary">Apply</button>
                <button type="button" id="kanbanResetBtn" class="kanban-btn light">Reset</button>
            </div>
        </form>
        <div class="kanban-view-bar" aria-label="Lead Board view switcher">
            <button type="button" class="kanban-view-btn {{ ($filters['view_mode'] ?? 'list') === 'card' ? 'active' : '' }}" data-view-mode="card">Card View</button>
            <button type="button" class="kanban-view-btn {{ ($filters['view_mode'] ?? 'list') === 'list' ? 'active' : '' }}" data-view-mode="list">List View</button>
        </div>
    </section>

    <div id="kanbanBoardMount">
        @include('sales-manager.overview._board')
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.body.classList.add('kanban-overview-active');

    const form = document.getElementById('kanbanFilterForm');
    const filterPanel = document.getElementById('kanbanFilterPanel');
    const mount = document.getElementById('kanbanBoardMount');
    const resetBtn = document.getElementById('kanbanResetBtn');
    const viewInput = document.getElementById('kanbanViewMode');
    const viewButtons = document.querySelectorAll('.kanban-view-btn[data-view-mode]');
    const quickButtons = document.querySelectorAll('.kanban-quick-btn[data-board-jump]');
    const endpoint = @json(route($leadBoardDataRoute ?? 'sales-manager.overview.data'));
    let debounceTimer = null;

    const toggleFilterPanel = (forceOpen = null) => {
        if (!filterPanel) {
            return;
        }

        const shouldOpen = forceOpen === null
            ? filterPanel.classList.contains('is-collapsed')
            : forceOpen;

        filterPanel.classList.toggle('is-collapsed', !shouldOpen);
        filterPanel.setAttribute('aria-hidden', shouldOpen ? 'false' : 'true');
    };

    const loadBoard = () => {
        const params = new URLSearchParams(new FormData(form));
        mount.style.opacity = '0.55';

        fetch(`${endpoint}?${params.toString()}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Lead Board refresh failed');
                }
                return response.json();
            })
            .then(payload => {
                mount.innerHTML = payload.html || '';
            })
            .catch(() => {
                mount.insertAdjacentHTML('afterbegin', '<div class="kanban-empty" style="margin-bottom:12px;">Lead Board refresh failed. Page reload karke try karo.</div>');
            })
            .finally(() => {
                mount.style.opacity = '1';
            });
    };

    const jumpToBoardStage = (stage) => {
        const boardWrap = mount.querySelector('.kanban-board-wrap');

        if (!boardWrap) {
            return;
        }

        if (stage === 'all') {
            boardWrap.scrollTo({ left: 0, behavior: 'smooth' });
            return;
        }

        const target = boardWrap.querySelector(`[data-kanban-stage="${stage}"]`);
        if (!target) {
            return;
        }

        boardWrap.scrollTo({
            left: Math.max(target.offsetLeft - 8, 0),
            behavior: 'smooth',
        });
    };

    form.addEventListener('submit', event => {
        event.preventDefault();
        loadBoard();
        toggleFilterPanel(false);
    });

    form.querySelectorAll('select,input[type="date"]').forEach(input => {
        input.addEventListener('change', loadBoard);
    });

    form.querySelector('input[name="search"]').addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(loadBoard, 350);
    });

    resetBtn.addEventListener('click', () => {
        form.reset();
        viewInput.value = 'list';
        viewButtons.forEach(button => button.classList.toggle('active', button.dataset.viewMode === 'list'));
        loadBoard();
        toggleFilterPanel(false);
    });

    viewButtons.forEach(button => {
        button.addEventListener('click', () => {
            viewInput.value = button.dataset.viewMode;
            viewButtons.forEach(item => item.classList.toggle('active', item === button));
            loadBoard();
        });
    });

    document.addEventListener('click', event => {
        if (event.target.closest('[data-filter-toggle]')) {
            toggleFilterPanel();
            return;
        }

        const jumpButton = event.target.closest('[data-board-jump]');
        if (jumpButton) {
            quickButtons.forEach(button => button.classList.toggle('active', button === jumpButton));
            jumpToBoardStage(jumpButton.dataset.boardJump || 'all');
            return;
        }

        const cnpButton = event.target.closest('[data-cnp-filter]');
        if (cnpButton) {
            const cnpColumn = cnpButton.closest('[data-kanban-stage="cnp"]');
            const filter = cnpButton.dataset.cnpFilter || 'all';

            if (cnpColumn) {
                cnpColumn.querySelectorAll('[data-cnp-filter]').forEach(button => {
                    button.classList.toggle('active', button === cnpButton);
                });

                cnpColumn.querySelectorAll('[data-cnp-type]').forEach(card => {
                    const type = card.dataset.cnpType || 'fresh';
                    card.style.display = filter === 'all' || type === filter ? '' : 'none';
                });
            }

            return;
        }

        document.querySelectorAll('.kanban-action-menu[open]').forEach(menu => {
            if (!menu.contains(event.target)) {
                menu.removeAttribute('open');
            }
        });
    });

    document.addEventListener('change', event => {
        const quickAssigned = event.target.closest('[data-quick-assigned-filter]');
        if (!quickAssigned) {
            return;
        }

        const assignedSelect = form.querySelector('select[name="assigned_to"]');
        if (assignedSelect) {
            assignedSelect.value = quickAssigned.value;
        }

        loadBoard();
    });
});

window.addEventListener('pagehide', () => {
    document.body.classList.remove('kanban-overview-active');
});
</script>
@endsection

@extends('layouts.app')

@section('title', 'CRM Workspace - ' . brand_name())
@section('page-title', 'CRM Workspace')

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<style>
    .layout-crm .main-header {
        display: none;
    }
    .crm-dashboard-shell {
        display: flex;
        flex-direction: column;
        gap: 24px;
    }
    .crm-grid-2 {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 24px;
        align-items: start;
    }
    .crm-grid-2 > .crm-surface {
        margin-top: 0 !important;
        align-self: start;
    }
    .crm-grid-4 {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 20px;
    }
    .crm-table-shell .table,
    .crm-table-shell .table * {
        color: inherit;
    }
    .crm-table-shell .table {
        margin-bottom: 0;
        min-width: 640px;
    }
    .crm-source-shell .table {
        min-width: 0;
    }
    .crm-compact-table .table {
        min-width: 0;
        table-layout: fixed;
    }
    .crm-compact-table .table thead th,
    .crm-compact-table .table td {
        padding: 11px 14px;
        font-size: 13px;
    }
    .crm-compact-table .table thead th {
        font-size: 11px;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }
    .crm-click-row {
        cursor: pointer;
    }
    .crm-click-row:hover td {
        background: #f8fbf9;
    }
    .crm-click-cell {
        display: block;
        width: 100%;
        color: inherit;
        text-decoration: none;
    }
    .crm-panel-fill {
        min-height: 100%;
        display: flex;
        flex-direction: column;
    }
    .crm-perf-panel .crm-surface-header,
    .crm-ops-panel .crm-surface-header {
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        padding-bottom: 14px;
    }
    .crm-pod-panel .crm-surface-header {
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        padding-bottom: 14px;
    }
    .crm-perf-panel .crm-section-copy,
    .crm-ops-panel .crm-section-copy {
        margin-bottom: 0;
    }
    .crm-panel-fill .crm-table-shell {
        flex: 1 1 auto;
    }
    .crm-scroll-table {
        overflow: hidden;
    }
    .crm-scroll-table .table {
        width: 100%;
        table-layout: fixed;
        border-collapse: separate;
        border-spacing: 0;
    }
    .crm-scroll-table .table thead,
    .crm-scroll-table .table tbody tr {
        display: table;
        width: 100%;
        table-layout: fixed;
    }
    .crm-scroll-table .table tbody {
        display: block;
        max-height: 332px;
        overflow-y: auto;
        overflow-x: hidden;
    }
    .crm-scroll-table .table tbody::-webkit-scrollbar {
        width: 8px;
    }
    .crm-scroll-table .table tbody::-webkit-scrollbar-thumb {
        background: rgba(6, 58, 28, 0.18);
        border-radius: 999px;
    }
    .crm-scroll-table .table tbody tr td {
        background: #fff;
    }
    .crm-ops-panel .crm-scroll-table .table thead th:nth-child(1),
    .crm-ops-panel .crm-scroll-table .table tbody td:nth-child(1) {
        width: 44px;
    }
    .crm-ops-panel .crm-scroll-table .table thead th:nth-child(2),
    .crm-ops-panel .crm-scroll-table .table tbody td:nth-child(2) {
        width: 46%;
        text-align: left;
    }
    .crm-ops-panel .crm-scroll-table .table thead th:nth-child(3),
    .crm-ops-panel .crm-scroll-table .table tbody td:nth-child(3) {
        width: 28%;
    }
    .crm-ops-panel .crm-scroll-table .table thead th:nth-child(4),
    .crm-ops-panel .crm-scroll-table .table tbody td:nth-child(4) {
        width: 26%;
    }
    .crm-recent-panel .crm-scroll-table .table thead th:nth-child(1),
    .crm-recent-panel .crm-scroll-table .table tbody td:nth-child(1) {
        width: 38%;
        text-align: left;
    }
    .crm-recent-panel .crm-scroll-table .table thead th:nth-child(2),
    .crm-recent-panel .crm-scroll-table .table tbody td:nth-child(2) {
        width: 24%;
        text-align: left;
    }
    .crm-recent-panel .crm-scroll-table .table thead th:nth-child(3),
    .crm-recent-panel .crm-scroll-table .table tbody td:nth-child(3) {
        width: 18%;
    }
    .crm-recent-panel .crm-scroll-table .table thead th:nth-child(4),
    .crm-recent-panel .crm-scroll-table .table tbody td:nth-child(4) {
        width: 20%;
        text-align: left;
    }
    .crm-ops-panel .crm-click-cell {
        text-align: left;
    }
    .crm-pod-toolbar {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }
    .crm-pod-toolbar .form-control {
        min-height: 42px;
        border-radius: 14px;
        border: 1px solid #d7e0d9;
        min-width: 220px;
    }
    .crm-pod-summary {
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #667085;
        white-space: nowrap;
    }
    .crm-pod-panel .crm-scroll-table .table thead th:nth-child(1),
    .crm-pod-panel .crm-scroll-table .table tbody td:nth-child(1) {
        width: 44px;
    }
    .crm-pod-panel .crm-scroll-table .table thead th:nth-child(2),
    .crm-pod-panel .crm-scroll-table .table tbody td:nth-child(2) {
        width: 44%;
        text-align: left;
    }
    .crm-pod-panel .crm-scroll-table .table thead th:nth-child(3),
    .crm-pod-panel .crm-scroll-table .table tbody td:nth-child(3) {
        width: 22%;
    }
    .crm-pod-panel .crm-scroll-table .table thead th:nth-child(4),
    .crm-pod-panel .crm-scroll-table .table tbody td:nth-child(4) {
        width: 34%;
        text-align: left;
    }
    .crm-pod-count {
        font-weight: 700;
        color: #9a3412;
    }
    .crm-recent-click-row {
        cursor: pointer;
    }
    .crm-recent-click-row:hover td {
        background: #f8fbf9;
    }
    .crm-detail-panel {
        padding: 14px 18px 14px 54px;
        background: #f8fbf9;
        width: 100%;
        box-sizing: border-box;
    }
    .crm-detail-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.7fr) 168px 72px;
        gap: 12px;
        align-items: center;
    }
    .crm-cnp-detail-head,
    .crm-cnp-detail-item {
        grid-template-columns: minmax(0, 1.65fr) 72px 168px minmax(0, 1fr) 56px;
    }
    .crm-detail-head {
        color: #667085;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        padding-bottom: 8px;
        border-bottom: 1px solid #e8efea;
        margin-bottom: 8px;
    }
    .crm-detail-item {
        padding: 8px 0;
        border-bottom: 1px solid #edf2ee;
        font-size: 13px;
    }
    .crm-detail-item:last-child {
        border-bottom: 0;
    }
    .crm-detail-link {
        color: #0d6efd;
        font-weight: 600;
        text-decoration: none;
    }
    .crm-detail-link:hover {
        text-decoration: underline;
    }
    .leads-pending-detail-row {
        display: none;
        width: 100%;
    }
    .leads-pending-detail-row > td {
        display: block;
        width: 100% !important;
        box-sizing: border-box;
    }
    .crm-detail-lead-name {
        font-weight: 600;
        color: #142850;
        line-height: 1.25;
    }
    .crm-detail-lead-phone {
        color: #667085;
        font-size: 12px;
        margin-top: 2px;
        line-height: 1.2;
    }
    .crm-perf-table-wrap {
        overflow: hidden;
    }
    .crm-perf-table {
        min-width: 100%;
        margin-bottom: 0;
    }
    .crm-perf-table th,
    .crm-perf-table td {
        white-space: nowrap;
        padding: 12px 10px;
        font-size: 12px;
        vertical-align: middle;
    }
    .crm-perf-table thead th {
        background: #f3f7f4;
        color: #667085;
        border-color: #ebf1ed;
        font-size: 11px;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }
    .crm-perf-table td:first-child,
    .crm-perf-table th:first-child {
        min-width: 180px;
    }
    .crm-table-shell .table thead th {
        background: #f3f7f4;
        color: #667085;
        border-color: #ebf1ed;
        white-space: nowrap;
    }
    .crm-table-shell .table td {
        border-color: #ebf1ed;
        vertical-align: middle;
    }
    .crm-select-row {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
        justify-content: flex-end;
    }
    .crm-select-row .form-select,
    .crm-select-row .form-control {
        min-height: 42px;
        border-radius: 14px;
        border: 1px solid #d7e0d9;
        box-shadow: inset 0 1px 0 rgba(255,255,255,0.65);
    }
    .crm-filter-inline {
        display: flex;
        flex-wrap: nowrap;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
        min-width: 0;
    }
    .crm-filter-inline .form-select,
    .crm-filter-inline .form-control {
        flex: 0 0 auto;
    }
    .crm-clear-cache-btn {
        flex: 0 0 auto;
        white-space: nowrap;
    }
    .crm-filter-inline .crm-inline-date-range {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: nowrap;
    }
    .crm-minimal-title {
        margin: 0;
    }
    .crm-muted-panel {
        border-radius: 22px;
        border: 1px solid rgba(6, 58, 28, 0.09);
        background: linear-gradient(180deg, #ffffff, #f8fbf9);
        padding: 20px;
        min-height: 100%;
    }
    .crm-telecaller-grid .telecaller-card {
        margin-bottom: 0;
        border-radius: 24px;
        padding: 20px;
        min-height: 100%;
        background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
        box-shadow: 0 18px 30px rgba(6, 58, 28, 0.18);
    }
    .crm-telecaller-grid .telecaller-card table {
        width: 100%;
    }
    .crm-telecaller-grid .text-muted {
        color: rgba(255,255,255,0.72) !important;
    }
    .crm-danger-zone {
        border: 1px solid rgba(220, 38, 38, 0.18);
        background: linear-gradient(135deg, #fff7f7, #fffdfd);
    }
    .crm-hero {
        border-radius: 24px;
        border: 1px solid rgba(15, 23, 42, 0.08);
        background: linear-gradient(135deg, #eefaf5, #ffffff 55%, #f6fbef);
        padding: 24px 32px;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
    }
    .crm-hero-grid {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
    }
    .crm-hero-actions {
        display: flex;
        align-items: center;
        gap: 14px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }
    .crm-hero-tools {
        display: flex;
        align-items: center;
        gap: 14px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }
    .crm-hero-search {
        display: flex;
        flex-direction: column;
        align-items: stretch;
        gap: 8px;
        min-width: min(420px, 100%);
        position: relative;
    }
    .crm-hero-search-row {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: nowrap;
    }
    .crm-hero-search-input {
        flex: 1 1 auto;
        min-width: 220px;
        height: 46px;
        border-radius: 14px;
        border: 1px solid rgba(6, 58, 28, 0.14);
        background: rgba(255,255,255,0.92);
        padding: 0 16px;
        color: #142850;
        box-shadow: 0 8px 20px rgba(15, 23, 42, 0.04);
    }
    .crm-hero-search-input:focus {
        outline: none;
        border-color: #205A44;
        box-shadow: 0 0 0 4px rgba(32, 90, 68, 0.10);
    }
    .crm-hero-search-btn {
        height: 46px;
        border-radius: 14px;
        padding: 0 18px;
        white-space: nowrap;
        font-weight: 700;
    }
    .crm-hero-search-results {
        display: none;
        position: absolute;
        top: calc(100% + 6px);
        left: 0;
        right: 0;
        z-index: 25;
        border-radius: 16px;
        border: 1px solid rgba(6, 58, 28, 0.12);
        background: #ffffff;
        box-shadow: 0 20px 40px rgba(15, 23, 42, 0.14);
        overflow: hidden;
    }
    .crm-hero-search-results.is-open {
        display: block;
    }
    .crm-hero-search-results-inner {
        max-height: 320px;
        overflow-y: auto;
    }
    .crm-hero-search-empty,
    .crm-hero-search-state {
        padding: 16px 18px;
        color: #667085;
        font-size: 14px;
    }
    .crm-hero-search-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        padding: 14px 16px;
        border-bottom: 1px solid #edf2ee;
    }
    .crm-hero-search-item:last-child {
        border-bottom: 0;
    }
    .crm-hero-search-item-main {
        min-width: 0;
        flex: 1 1 auto;
    }
    .crm-hero-search-item-name {
        font-size: 15px;
        font-weight: 700;
        color: #142850;
        line-height: 1.25;
    }
    .crm-hero-search-item-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 3px;
        color: #667085;
        font-size: 13px;
    }
    .crm-hero-search-item-status {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 2px 8px;
        background: #eef7f1;
        color: #205A44;
        font-weight: 600;
        text-transform: capitalize;
    }
    .crm-hero-search-view {
        flex: 0 0 auto;
        white-space: nowrap;
        font-weight: 700;
    }
    .crm-hero-clock {
        background: rgba(255,255,255,0.88);
        border: 1px solid rgba(6, 58, 28, 0.1);
        border-radius: 14px;
        padding: 10px 14px;
        min-width: 160px;
        text-align: center;
        box-shadow: 0 8px 20px rgba(15, 23, 42, 0.06);
        font-family: 'Courier New', monospace;
        font-weight: 600;
    }
    .crm-hero-clock-time {
        font-size: 16px;
        color: #205A44;
    }
    .crm-hero-clock-date {
        font-size: 11px;
        color: #7a8796;
        margin-top: 2px;
    }
    .crm-hero-logout.btn {
        padding: 10px 18px;
        border-radius: 12px;
    }
    .crm-hero-title {
        margin: 0;
        font-size: clamp(1.8rem, 3vw, 2.5rem);
        line-height: 1.15;
        font-weight: 700;
        letter-spacing: -0.03em;
        color: #142850;
    }
    .crm-hero-title strong {
        color: #138a63;
        font-weight: 800;
    }
    .crm-source-value {
        font-weight: 700;
        color: #142850;
    }
    @media (max-width: 960px) {
        .crm-grid-2,
        .crm-grid-4 {
            grid-template-columns: 1fr;
        }
        .crm-table-shell .table {
            min-width: 560px;
        }
        .crm-hero {
            padding: 18px 20px;
        }
        .crm-hero-title {
            font-size: clamp(1.4rem, 5vw, 1.9rem);
        }
        .crm-perf-panel .crm-surface-header,
        .crm-ops-panel .crm-surface-header,
        .crm-pod-panel .crm-surface-header {
            align-items: flex-start;
        }
        .crm-filter-inline {
            width: 100%;
            flex-wrap: wrap;
            justify-content: flex-start;
        }
        .crm-pod-toolbar {
            width: 100%;
            justify-content: flex-start;
        }
        .crm-pod-toolbar .form-control {
            min-width: min(100%, 220px);
        }
        .crm-filter-inline .crm-inline-date-range {
            flex-wrap: wrap;
            width: 100%;
        }
        .crm-scroll-table .table tbody {
            max-height: none;
        }
        .crm-hero-grid {
            align-items: flex-start;
            flex-direction: column;
        }
        .crm-hero-actions {
            width: 100%;
            justify-content: flex-start;
        }
        .crm-hero-tools {
            width: 100%;
            justify-content: flex-start;
        }
        .crm-hero-search {
            width: 100%;
            min-width: 0;
        }
        .crm-hero-search-row {
            width: 100%;
        }
        .crm-hero-search-input {
            min-width: 0;
        }
        .crm-hero-search-item {
            flex-direction: column;
            align-items: flex-start;
        }
        .crm-hero-search-view {
            width: 100%;
        }
        .crm-detail-grid {
            grid-template-columns: minmax(0, 1fr);
            gap: 6px;
        }
        .crm-detail-head {
            display: none;
        }
        .crm-detail-panel {
            padding-left: 18px;
        }
    }
</style>
@endpush

@section('content')
<div class="page-shell crm-dashboard-shell">
    @if(auth()->user()?->hasAttendanceRolloutEnabled())
        @include('attendance._widget')
    @endif
    @php
        $greetingHour = (int) now()->format('G');
        if ($greetingHour >= 6 && $greetingHour < 12) {
            $dashboardGreeting = 'Good morning';
        } elseif ($greetingHour >= 12 && $greetingHour < 17) {
            $dashboardGreeting = 'Good afternoon';
        } elseif ($greetingHour >= 17 && $greetingHour < 21) {
            $dashboardGreeting = 'Good evening';
        } else {
            $dashboardGreeting = 'Good night';
        }
    @endphp
    <section class="crm-hero">
        <div class="crm-hero-grid">
            <div>
                <h2 class="crm-hero-title">{{ $dashboardGreeting }}, <strong>{{ auth()->user()->name ?? 'User' }}</strong></h2>
            </div>
            <div class="crm-hero-actions">
                <div class="crm-hero-tools">
                    <form class="crm-hero-search" id="crmDashboardLeadSearch" autocomplete="off">
                        <div class="crm-hero-search-row">
                            <input
                                type="text"
                                name="search"
                                id="crmDashboardLeadSearchInput"
                                class="crm-hero-search-input"
                                placeholder="Search lead by name or phone"
                            >
                            <button type="submit" class="btn btn-outline-success crm-hero-search-btn">
                                <i class="fas fa-search" style="margin-right:6px;"></i>
                                Search
                            </button>
                        </div>
                        <div class="crm-hero-search-results" id="crmDashboardLeadSearchResults">
                            <div class="crm-hero-search-results-inner" id="crmDashboardLeadSearchResultsInner"></div>
                        </div>
                    </form>
                    <div class="crm-hero-clock">
                        <div id="crmClockTime" class="crm-hero-clock-time">--:--:--</div>
                        <div id="crmClockDate" class="crm-hero-clock-date">-- -- ----</div>
                    </div>
                    <form action="{{ route('logout') }}" method="POST" class="header-logout-form" style="display:inline;">
                        @csrf
                        <button type="submit" class="btn btn-danger crm-hero-logout">
                            <i class="fas fa-sign-out-alt" style="margin-right: 5px;"></i>
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <div id="notification-alert" class="alert alert-success alert-dismissible fade d-none" role="alert">
        <span id="notification-message"></span>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>

    <section class="crm-grid-2">
        <div class="crm-surface crm-panel-fill crm-ops-panel">
            <div class="crm-surface-header">
                <div>
                    <h3 class="crm-section-title crm-minimal-title">Leads Allocated And Average Response</h3>
                </div>
                <div class="crm-select-row crm-filter-inline">
                    <select id="leads-allocated-date-range" class="form-select form-select-sm" style="width: 154px;" title="Date range">
                        <option value="today">Today</option>
                        <option value="yesterday">Yesterday</option>
                        <option value="this_week">This Week</option>
                        <option value="this_month" selected>This Month</option>
                        <option value="this_year">This Year</option>
                        <option value="all_time">All Time</option>
                        <option value="custom">Custom</option>
                    </select>
                    <span id="leads-allocated-custom-date-wrap" class="d-none crm-inline-date-range">
                        <input type="date" id="leads-allocated-date-start" class="form-control form-control-sm" style="width: 132px;" title="From">
                        <input type="date" id="leads-allocated-date-end" class="form-control form-control-sm" style="width: 132px;" title="To">
                    </span>
                    <button type="button" class="btn btn-outline-secondary btn-sm crm-clear-cache-btn" data-dashboard-clear-cache>
                        Clear Cache
                    </button>
                </div>
            </div>
            <div class="crm-table-shell crm-compact-table crm-scroll-table">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width: 44px;"></th>
                                <th class="text-start">User Name</th>
                                <th class="text-center">New Leads Not Completed</th>
                                <th class="text-end">Avg Response Time</th>
                            </tr>
                        </thead>
                        <tbody id="lead-operations-summary-tbody">
                            <tr>
                                <td colspan="4" class="text-center text-muted py-5">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="crm-surface crm-panel-fill crm-perf-panel">
            <div class="crm-surface-header">
                <div>
                    <h3 class="crm-section-title crm-minimal-title">Sales Executive Performance</h3>
                </div>
                <div class="crm-select-row crm-filter-inline">
                    <select id="perf-role-filter" class="form-select form-select-sm" style="width: 144px;" title="User type">
                        <option value="all">All</option>
                    </select>
                    <select id="perf-date-range" class="form-select form-select-sm" style="width: 154px;" title="Date range">
                        <option value="today">Today</option>
                        <option value="yesterday">Yesterday</option>
                        <option value="this_week">This Week</option>
                        <option value="this_month" selected>This Month</option>
                        <option value="this_year">This Year</option>
                        <option value="all_time">All Time</option>
                        <option value="custom">Custom</option>
                    </select>
                    <span id="perf-custom-date-wrap" class="d-none crm-inline-date-range">
                        <input type="date" id="perf-date-start" class="form-control form-control-sm" style="width: 132px;" title="From">
                        <input type="date" id="perf-date-end" class="form-control form-control-sm" style="width: 132px;" title="To">
                    </span>
                    <button type="button" class="btn btn-outline-secondary btn-sm crm-clear-cache-btn" data-dashboard-clear-cache>
                        Clear Cache
                    </button>
                </div>
            </div>
            <div id="telecaller-stats-container" class="crm-table-shell crm-compact-table crm-scroll-table">
                <div class="crm-empty">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Loading sales executive performance...</p>
                </div>
            </div>
        </div>
    </section>

    <section>
        <div class="crm-surface crm-panel-fill crm-pod-panel">
            <div class="crm-surface-header">
                <div>
                    <h3 class="crm-section-title crm-minimal-title">Previous Over POD</h3>
                    <p class="crm-section-copy mb-0">Purane unresolved POD items by user. Aaj se pehle ke pending items.</p>
                </div>
                <div class="crm-pod-toolbar">
                    <input type="search" id="previous-over-pod-search" class="form-control form-control-sm" placeholder="Search user">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="previous-over-pod-refresh">Refresh</button>
                    <span class="crm-pod-summary" id="previous-over-pod-total-users">Total Users: 0</span>
                </div>
            </div>
            <div class="crm-table-shell crm-compact-table crm-scroll-table">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width: 44px;"></th>
                                <th class="text-start">User Name</th>
                                <th class="text-center">Previous Over POD</th>
                                <th class="text-start">Oldest Pending Since</th>
                            </tr>
                        </thead>
                        <tbody id="previous-over-pod-tbody">
                            <tr>
                                <td colspan="4" class="text-center text-muted py-5">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <section class="crm-grid-2">
        <div class="crm-surface crm-panel-fill crm-recent-panel">
            <div class="crm-surface-header">
                <div>
                    <h3 class="crm-section-title crm-minimal-title">Recent Leads</h3>
                </div>
            </div>
            <div class="crm-table-shell crm-compact-table crm-scroll-table">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="text-start">Lead Name</th>
                                <th class="text-start">Owner</th>
                                <th class="text-center">Status</th>
                                <th class="text-start">Outcome</th>
                            </tr>
                        </thead>
                        <tbody id="recent-leads-tbody">
                            <tr>
                                <td colspan="4" class="text-center text-muted py-5">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="crm-surface crm-panel-fill crm-source-panel">
            <div class="crm-surface-header">
                <div>
                    <h3 class="crm-section-title crm-minimal-title">Source-wise Lead Inflow</h3>
                </div>
                <div class="crm-select-row crm-filter-inline">
                    <select id="source-date-range" class="form-select form-select-sm" style="width: 154px;" title="Date range">
                        <option value="today">Today</option>
                        <option value="yesterday">Yesterday</option>
                        <option value="this_week">This Week</option>
                        <option value="this_month" selected>This Month</option>
                        <option value="this_year">This Year</option>
                        <option value="all_time">All Time</option>
                        <option value="custom">Custom</option>
                    </select>
                    <span id="source-custom-date-wrap" class="d-none crm-inline-date-range">
                        <input type="date" id="source-date-start" class="form-control form-control-sm" style="width: 132px;" title="From">
                        <input type="date" id="source-date-end" class="form-control form-control-sm" style="width: 132px;" title="To">
                    </span>
                    <button type="button" class="btn btn-outline-secondary btn-sm crm-clear-cache-btn" data-dashboard-clear-cache>
                        Clear Cache
                    </button>
                </div>
            </div>
            <div class="crm-table-shell crm-source-shell crm-compact-table">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Source</th>
                                <th class="text-end">Lead Count</th>
                            </tr>
                        </thead>
                        <tbody id="crm-source-distribution">
                            @if(!empty($initialSourceDistribution ?? []))
                                @foreach(($initialSourceDistribution ?? []) as $item)
                                    <tr>
                                        <td>{{ $item['source'] ?? 'Other' }}</td>
                                        <td class="text-end crm-source-value">{{ $item['value'] ?? 0 }}</td>
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="2" class="text-center text-muted py-4">No leads found for the selected date range.</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <section class="crm-grid-2">
        <div class="crm-surface crm-panel-fill crm-cnp-panel">
            <div class="crm-surface-header">
                <div>
                    <h3 class="crm-section-title crm-minimal-title">Fresh Lead CNP Track</h3>
                </div>
            </div>
            <div class="crm-table-shell crm-compact-table crm-scroll-table">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width: 10%;"></th>
                                <th class="text-start" style="width: 48%;">User</th>
                                <th class="text-center" style="width: 18%;">Total CNP</th>
                                <th class="text-start" style="width: 24%;">Last CNP</th>
                            </tr>
                        </thead>
                        <tbody id="fresh-lead-cnp-tbody">
                            <tr>
                                <td colspan="4" class="text-center text-muted py-5">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="crm-grid-placeholder d-none d-lg-block" aria-hidden="true"></div>
    </section>

</div>

@include('crm.modals.user-management')
@include('crm.modals.transfer-leads')
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/crm-dashboard.js') }}?v={{ @filemtime(public_path('js/crm-dashboard.js')) }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('crmDashboardLeadSearch');
    const input = document.getElementById('crmDashboardLeadSearchInput');
    const results = document.getElementById('crmDashboardLeadSearchResults');
    const resultsInner = document.getElementById('crmDashboardLeadSearchResultsInner');
    const endpoint = @json(route('dashboard.lead-search'));
    let activeController = null;

    if (!form || !input || !results || !resultsInner) {
        return;
    }

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const closeResults = () => {
        results.classList.remove('is-open');
    };

    const openResults = () => {
        results.classList.add('is-open');
    };

    const renderState = (markup) => {
        resultsInner.innerHTML = markup;
        openResults();
    };

    const runSearch = async () => {
        const query = input.value.trim();
        if (query.length < 2) {
            closeResults();
            return;
        }

        if (activeController) {
            activeController.abort();
        }

        activeController = new AbortController();
        renderState('<div class="crm-hero-search-state">Searching leads...</div>');

        try {
            const response = await fetch(`${endpoint}?q=${encodeURIComponent(query)}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                signal: activeController.signal,
            });

            if (!response.ok) {
                throw new Error(`Search failed with status ${response.status}`);
            }

            const data = await response.json();
            const leads = Array.isArray(data.results) ? data.results : [];

            if (!leads.length) {
                renderState('<div class="crm-hero-search-empty">No lead found for this search.</div>');
                return;
            }

            resultsInner.innerHTML = leads.map((lead) => `
                <div class="crm-hero-search-item">
                    <div class="crm-hero-search-item-main">
                        <div class="crm-hero-search-item-name">${escapeHtml(lead.name || 'Unnamed Lead')}</div>
                        <div class="crm-hero-search-item-meta">
                            <span>${escapeHtml(lead.phone || '-')}</span>
                            <span class="crm-hero-search-item-status">${escapeHtml((lead.status || 'new').replace(/_/g, ' '))}</span>
                        </div>
                    </div>
                    <a href="${escapeHtml(lead.url)}" class="btn btn-outline-success btn-sm crm-hero-search-view">
                        View More
                    </a>
                </div>
            `).join('');
            openResults();
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }
            renderState('<div class="crm-hero-search-empty">Unable to search right now. Please try again.</div>');
        }
    };

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        runSearch();
    });

    input.addEventListener('input', function () {
        if (input.value.trim().length >= 10) {
            runSearch();
            return;
        }
        if (input.value.trim().length < 2) {
            closeResults();
        }
    });

    document.addEventListener('click', function (event) {
        if (!form.contains(event.target)) {
            closeResults();
        }
    });
});
</script>
@endpush

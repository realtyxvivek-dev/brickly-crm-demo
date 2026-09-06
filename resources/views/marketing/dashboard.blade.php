@extends('layouts.app')

@section('title', 'Marketing Dashboard')
@section('page-title', 'Marketing Dashboard')
@section('hide-app-header', '1')

@php
    $isManager = $isManager ?? false;
    $displayRole = $displayRole ?? ($isManager ? 'Marketing Manager' : 'Marketing Executive');
    $recentOwnTasks = $recentOwnTasks ?? collect();
    $ownTaskCounts = $ownTaskCounts ?? ['open' => 0, 'in_progress' => 0, 'overdue' => 0];
    $myTodos = $myTodos ?? collect();
    $selfTodoCounts = $selfTodoCounts ?? ['total' => $myTodos->count(), 'open' => 0, 'completed' => 0];
    $activeBroadcasts = $activeBroadcasts ?? collect();
    $managerNotes = $managerNotes ?? collect();
    $recentAttendanceRecords = $recentAttendanceRecords ?? collect();
    $teamMembers = $teamMembers ?? collect();
    $teamTaskCards = $teamTaskCards ?? collect();
    $teamAttendanceSummary = $teamAttendanceSummary ?? null;
    $metaCplSummary = $metaCplSummary ?? [
        'forms' => collect(),
        'campaigns' => collect(),
        'funnel' => collect(),
        'alerts' => collect(),
        'range' => ['preset' => request('meta_filter', 'month'), 'label' => 'This Month'],
        'totals' => ['leads' => 0, 'spend' => 0, 'cpl' => null, 'qualified' => 0, 'bad' => 0, 'hold' => 0, 'closers' => 0, 'cost_per_closer' => null, 'avg_quality' => null],
    ];
    $taskTitle = $isManager ? 'My Tasks' : 'Assigned Tasks';
    $taskEmpty = $isManager ? 'No manager tasks right now.' : 'No assigned tasks right now.';
    $todoOpenCount = $selfTodoCounts['open'] ?? $myTodos->where('status', \App\Models\SelfTodo::STATUS_OPEN)->count();
    $todoDoneCount = $selfTodoCounts['completed'] ?? $myTodos->where('status', \App\Models\SelfTodo::STATUS_COMPLETED)->count();
    $announcementCount = $activeBroadcasts->count();
    $managerNoteCount = $managerNotes->count();
    $taskStatusClass = function ($status) {
        return match ($status) {
            'in_progress' => 'mkt-badge mkt-badge-blue',
            'waiting' => 'mkt-badge mkt-badge-amber',
            'reopened' => 'mkt-badge mkt-badge-red',
            default => 'mkt-badge',
        };
    };
    $priorityClass = function ($priority) {
        return match ($priority) {
            'high', 'urgent' => 'mkt-badge mkt-badge-red',
            'medium' => 'mkt-badge mkt-badge-amber',
            'low' => 'mkt-badge mkt-badge-blue',
            default => 'mkt-badge',
        };
    };
    $attendanceStatusLabel = fn ($status) => ucwords(str_replace('_', ' ', (string) $status));
    $money = fn ($value) => 'Rs ' . number_format((float) ($value ?? 0), 2);
    $metaFilter = $metaCplSummary['range']['preset'] ?? request('meta_filter', 'month');
    $metaFilters = ['today' => 'Today', 'week' => 'Week', 'month' => 'Month', 'year' => 'Year'];
@endphp

@push('styles')
<style>
    .mkt-shell {
        max-width: 1240px;
        margin: 0 auto;
        display: grid;
        gap: 18px;
        color: #123127;
        font-family: "Nunito Sans", "Segoe UI", system-ui, sans-serif;
    }
    .mkt-header {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 16px;
    }
    .mkt-header-copy {
        min-width: 0;
    }
    .mkt-kicker {
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .12em;
        color: #6a7b74;
        margin-bottom: 8px;
    }
    .mkt-title {
        margin: 0;
        font-size: 34px;
        font-weight: 800;
        line-height: 1.05;
        letter-spacing: -.04em;
    }
    .mkt-subtitle {
        margin: 8px 0 0;
        color: #64746d;
        font-size: 14px;
    }
    .mkt-header-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }
    .mkt-role-chip {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border: 1px solid #dce7e0;
        background: #fff;
        border-radius: 999px;
        padding: 10px 14px;
        font-size: 12px;
        font-weight: 800;
        color: #0b5b39;
        white-space: nowrap;
    }
    .mkt-row {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
        align-items: start;
    }
    .mkt-row-manager {
        display: grid;
        grid-template-columns: minmax(0, .85fr) minmax(0, 1.15fr);
        gap: 18px;
        align-items: start;
    }
    .mkt-card {
        background: #fff;
        border: 1px solid #dfe8e3;
        border-radius: 22px;
        box-shadow: 0 12px 30px rgba(16, 37, 28, .05);
        padding: 18px;
        min-width: 0;
        scroll-margin-top: 92px;
    }
    .mkt-attendance-shell {
        scroll-margin-top: 92px;
    }
    .mkt-attendance-history {
        margin-top: 14px;
    }
    .mkt-cpl-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        margin-bottom: 14px;
    }
    .mkt-cpl-stat {
        border: 1px solid #e4ece7;
        border-radius: 16px;
        background: #f8fbf9;
        padding: 14px;
    }
    .mkt-cpl-stat strong {
        display: block;
        font-size: 24px;
        line-height: 1;
        color: #0b5b39;
        font-weight: 900;
    }
    .mkt-cpl-stat span {
        display: block;
        margin-top: 7px;
        font-size: 11px;
        color: #6a7b74;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: .08em;
    }
    .mkt-cpl-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }
    .mkt-cpl-table th,
    .mkt-cpl-table td {
        padding: 10px 8px;
        border-bottom: 1px solid #e7efea;
        text-align: left;
    }
    .mkt-cpl-table th {
        font-size: 11px;
        color: #65736c;
        text-transform: uppercase;
        letter-spacing: .08em;
        font-weight: 900;
    }
    .mkt-cpl-table .num {
        text-align: right;
        white-space: nowrap;
        font-weight: 800;
    }
    .mkt-filter-group {
        display: inline-flex;
        gap: 6px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }
    .mkt-filter-link {
        border: 1px solid #dce7e0;
        border-radius: 999px;
        padding: 8px 12px;
        font-size: 11px;
        font-weight: 900;
        color: #466158;
        background: #fff;
    }
    .mkt-filter-link.active {
        background: #123127;
        color: #fff;
        border-color: #123127;
    }
    .mkt-funnel-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
        margin: 14px 0;
    }
    .mkt-funnel-card {
        border: 1px solid #e4ece7;
        border-radius: 14px;
        padding: 12px;
        background: #fff;
    }
    .mkt-funnel-card strong {
        display: block;
        font-size: 20px;
        color: #123127;
        font-weight: 900;
    }
    .mkt-funnel-card span {
        display: block;
        margin-top: 5px;
        font-size: 11px;
        color: #65736c;
        font-weight: 900;
    }
    .mkt-funnel-bar {
        height: 6px;
        border-radius: 999px;
        background: #e8f0eb;
        overflow: hidden;
        margin-top: 9px;
    }
    .mkt-funnel-bar i {
        display: block;
        height: 100%;
        border-radius: inherit;
        background: #0b5b39;
    }
    .mkt-alert-list {
        display: grid;
        gap: 8px;
        margin: 14px 0;
    }
    .mkt-alert {
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        padding: 11px 12px;
        background: #f8fbf9;
    }
    .mkt-alert strong {
        display: block;
        font-size: 13px;
        color: #123127;
    }
    .mkt-alert span {
        display: block;
        margin-top: 3px;
        font-size: 12px;
        color: #64746d;
    }
    .mkt-alert.danger {
        background: #fff7f7;
        border-color: #fecaca;
    }
    .mkt-alert.warning {
        background: #fffbeb;
        border-color: #fde68a;
    }
    .mkt-attendance-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 12px;
        font-size: 13px;
    }
    .mkt-attendance-table th,
    .mkt-attendance-table td {
        padding: 10px 8px;
        border-bottom: 1px solid #e7efea;
        text-align: left;
        vertical-align: middle;
    }
    .mkt-attendance-table th {
        color: #66756e;
        font-size: 11px;
        font-weight: 900;
        letter-spacing: .08em;
        text-transform: uppercase;
        background: #f8fbf9;
    }
    .mkt-attendance-table td {
        color: #123127;
        font-weight: 700;
    }
    .mkt-attendance-status {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 5px 9px;
        background: #ecfdf5;
        color: #047857;
        font-size: 11px;
        font-weight: 900;
        white-space: nowrap;
    }
    .mkt-attendance-status.absent,
    .mkt-attendance-status.missing {
        background: #fef2f2;
        color: #b91c1c;
    }
    .mkt-attendance-status.late,
    .mkt-attendance-status.half_day {
        background: #fffbeb;
        color: #b45309;
    }
    .mkt-card-header {
        display: flex;
        align-items: start;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 14px;
    }
    .mkt-card-title {
        margin: 0;
        font-size: 22px;
        line-height: 1.1;
        font-weight: 800;
        letter-spacing: -.03em;
    }
    .mkt-card-copy {
        color: #66756e;
        font-size: 13px;
        margin-top: 5px;
    }
    .mkt-mini-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
        margin-bottom: 16px;
    }
    .mkt-todo-stats {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
    .mkt-mini-stat {
        border: 1px solid #e7efea;
        background: #f8fbf9;
        border-radius: 18px;
        padding: 12px;
        min-width: 0;
    }
    .mkt-mini-stat strong {
        display: block;
        font-size: 24px;
        line-height: 1;
        font-weight: 800;
    }
    .mkt-mini-stat span {
        display: block;
        margin-top: 6px;
        font-size: 11px;
        color: #6c7a74;
        text-transform: uppercase;
        letter-spacing: .08em;
        font-weight: 800;
    }
    .mkt-list,
    .mkt-note-list,
    .mkt-announcement-list {
        display: grid;
        gap: 12px;
    }
    .mkt-item {
        border: 1px solid #e5ede8;
        background: #fbfdfc;
        border-radius: 18px;
        padding: 14px;
    }
    .mkt-item-top {
        display: flex;
        align-items: start;
        justify-content: space-between;
        gap: 12px;
    }
    .mkt-item-title {
        font-size: 16px;
        font-weight: 800;
        line-height: 1.2;
        margin: 0;
    }
    .mkt-item-meta {
        margin-top: 6px;
        color: #66756e;
        font-size: 12px;
        line-height: 1.45;
    }
    .mkt-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 12px;
    }
    .mkt-btn {
        appearance: none;
        border: none;
        border-radius: 14px;
        padding: 10px 14px;
        font-size: 12px;
        font-weight: 800;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        cursor: pointer;
    }
    .mkt-btn-primary {
        background: #0b5b39;
        color: #fff;
    }
    .mkt-btn-round {
        width: 42px;
        height: 42px;
        border-radius: 16px;
        padding: 0;
        font-size: 16px;
    }
    .mkt-btn-secondary {
        background: #eff5f1;
        color: #123127;
        border: 1px solid #dce7e0;
    }
    .mkt-btn-danger {
        background: #fff1f1;
        color: #ad2525;
        border: 1px solid #f6d0d0;
    }
    .mkt-badge {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        background: #eef3f0;
        color: #395146;
        padding: 7px 10px;
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .08em;
        white-space: nowrap;
    }
    .mkt-badge-green { background: #dcfce7; color: #166534; }
    .mkt-badge-amber { background: #fff4dc; color: #8d5a0a; }
    .mkt-badge-blue { background: #e8f1ff; color: #1d4ed8; }
    .mkt-badge-red { background: #fee2e2; color: #991b1b; }
    .mkt-empty {
        border: 1px dashed #dce7e0;
        border-radius: 18px;
        padding: 18px;
        color: #6a7b74;
        text-align: center;
        font-size: 13px;
        background: #fbfdfc;
    }
    .mkt-modal-backdrop {
        position: fixed;
        inset: 0;
        z-index: 9998;
        background: rgba(6, 28, 19, .48);
        display: none;
        align-items: center;
        justify-content: center;
        padding: 18px;
    }
    .mkt-modal-backdrop.is-open {
        display: flex;
    }
    .mkt-modal {
        width: min(520px, 100%);
        background: #fff;
        border: 1px solid #dfe8e3;
        border-radius: 24px;
        box-shadow: 0 28px 80px rgba(6, 28, 19, .22);
        padding: 18px;
    }
    .mkt-modal-header {
        display: flex;
        align-items: start;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 14px;
    }
    .mkt-modal-title {
        margin: 0;
        font-size: 22px;
        font-weight: 800;
        letter-spacing: -.03em;
    }
    .mkt-modal-close {
        width: 38px;
        height: 38px;
        border-radius: 14px;
        border: 1px solid #dfe8e3;
        background: #f8fbf9;
        color: #123127;
        cursor: pointer;
        font-weight: 900;
    }
    .mkt-form-error {
        margin-bottom: 12px;
        padding: 10px 12px;
        border: 1px solid #fecaca;
        border-radius: 14px;
        background: #fff1f2;
        color: #991b1b;
        font-size: 13px;
        font-weight: 700;
    }
    .mkt-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
        margin-bottom: 14px;
    }
    .mkt-field,
    .mkt-textarea {
        width: 100%;
        border: 1px solid #dbe7df;
        border-radius: 14px;
        background: #fff;
        color: #123127;
        padding: 12px 14px;
        font-size: 14px;
    }
    .mkt-textarea {
        min-height: 96px;
        resize: vertical;
    }
    .mkt-note-form {
        border-top: 1px solid #edf2ef;
        margin-top: 14px;
        padding-top: 14px;
    }
    .mkt-foot-link {
        color: #0b5b39;
        font-size: 12px;
        font-weight: 800;
        text-decoration: none;
    }
    .mql-panel {
        background: #fff;
        border: 1px solid #c9d8cf;
        box-shadow: 0 10px 24px rgba(6, 58, 28, .08);
        overflow: hidden;
        color: #0f172a;
        font-family: Arial, "Helvetica Neue", sans-serif;
    }
    .mql-filter {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: end;
        padding: 12px 18px;
        background: #f1f8f4;
        border-bottom: 1px solid #c9d8cf;
    }
    .mql-filter label {
        display: grid;
        gap: 4px;
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: #345a46;
    }
    .mql-input,
    .mql-btn {
        height: 34px;
        border-radius: 3px;
        border: 1px solid #b7cabb;
        background: #fff;
        padding: 0 10px;
        font-size: 12px;
        color: #0f172a;
    }
    .mql-input {
        min-width: 150px;
    }
    .mql-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        font-weight: 800;
        text-decoration: none;
        cursor: pointer;
    }
    .mql-btn.primary {
        background: #0b6b34;
        border-color: #0b6b34;
        color: #fff;
    }
    .mql-period {
        margin-left: auto;
        color: #486175;
        font-size: 12px;
        padding-bottom: 8px;
    }
    .mql-kpis {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        border-bottom: 1px solid #c9d8cf;
    }
    .mql-kpi {
        padding: 13px 16px;
        border-right: 1px solid #d8e4dc;
        background: linear-gradient(180deg, #fff 0, #f7fbf8 100%);
    }
    .mql-kpi:last-child {
        border-right: 0;
    }
    .mql-kpi span {
        display: block;
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: #3f6651;
    }
    .mql-kpi strong {
        display: block;
        margin-top: 6px;
        font-size: 25px;
        line-height: 1.05;
        font-weight: 900;
        color: #111827;
    }
    .mql-kpi strong.good {
        color: #047857;
    }
    .mql-kpi strong.bad {
        color: #b91c1c;
    }
    .mql-kpi strong.warn {
        color: #b45309;
    }
    .mql-kpi small {
        display: block;
        margin-top: 5px;
        color: #64748b;
        font-size: 12px;
    }
    .mql-summary {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0;
        padding: 16px 18px;
        border-bottom: 1px solid #c9d8cf;
    }
    .mql-score {
        display: flex;
        align-items: center;
        gap: 18px;
        min-width: 0;
    }
    .mql-ring {
        width: 98px;
        height: 98px;
        border-radius: 999px;
        background: conic-gradient(#0f7a42 calc(var(--score) * 1%), #d9e1ea 0);
        display: grid;
        place-items: center;
        flex: 0 0 auto;
    }
    .mql-ring span {
        width: 70px;
        height: 70px;
        border-radius: 999px;
        background: #fff;
        display: grid;
        place-items: center;
        font-size: 21px;
        font-weight: 900;
        border: 1px solid #dbe3ee;
    }
    .mql-summary h2,
    .mql-section-head h2 {
        margin: 0;
        font-size: 16px;
        font-weight: 900;
    }
    .mql-summary p,
    .mql-section-head p {
        margin: 4px 0 0;
        color: #64748b;
        font-size: 12px;
        line-height: 1.45;
    }
    .mql-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 10px;
    }
    .mql-badge,
    .mql-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 3px;
        padding: 4px 8px;
        font-size: 11px;
        font-weight: 900;
        border: 1px solid transparent;
        white-space: nowrap;
    }
    .mql-count {
        min-width: 34px;
        height: 23px;
        padding: 0 8px;
    }
    .mql-badge.good,
    .mql-count.good { background: #e8f5e9; color: #0f6b35; border-color: #bfe3ca; }
    .mql-badge.bad,
    .mql-count.bad { background: #fde7e9; color: #b4232e; border-color: #f5c2c7; }
    .mql-badge.warn,
    .mql-count.warn { background: #fff4ce; color: #8a5600; border-color: #f4d781; }
    .mql-badge.info,
    .mql-count.info { background: #e7f0ff; color: #1959b3; border-color: #c1d8ff; }
    .mql-count.neutral { background: #edf2f7; color: #111827; border-color: #d9e1ea; }
    .mql-note {
        border: 1px solid #bfe3ca;
        border-left: 5px solid #0f7a42;
        background: #f0fbf4;
        padding: 12px;
        color: #14532d;
        font-size: 12px;
        line-height: 1.55;
    }
    .mql-section-head {
        display: flex;
        justify-content: space-between;
        gap: 14px;
        align-items: flex-start;
        padding: 16px 18px 10px;
    }
    .mql-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
        justify-content: flex-end;
    }
    .mql-legend span {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        color: #53657a;
        font-size: 11px;
        font-weight: 800;
    }
    .mql-legend i {
        width: 8px;
        height: 8px;
        border-radius: 2px;
        display: inline-block;
    }
    .mql-legend i.raw { background: #f4b183; }
    .mql-legend i.pipe { background: #70ad47; }
    .mql-legend i.bad { background: #d9534f; }
    .mql-table-wrap {
        overflow-x: auto;
        padding: 0 18px 16px;
    }
    .mql-table {
        width: 100%;
        min-width: 1320px;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 12px;
        line-height: 1.35;
        border: 1px solid #cfded5;
    }
    .mql-table th {
        background: #eaf4ee;
        color: #183c2a;
        text-align: center;
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: .06em;
        padding: 9px 10px;
        border-right: 1px solid #cfded5;
        border-bottom: 1px solid #abc7b5;
        white-space: nowrap;
    }
    .mql-table td {
        padding: 9px 10px;
        border-right: 1px solid #e1e7ef;
        border-bottom: 1px solid #e1e7ef;
        text-align: center;
        background: #fff;
        vertical-align: middle;
    }
    .mql-table th:first-child,
    .mql-table td:first-child {
        text-align: left;
    }
    .mql-table .mql-source {
        position: sticky;
        left: 0;
        z-index: 1;
        background: #fff;
        box-shadow: 5px 0 0 rgba(207, 216, 227, .45);
    }
    .mql-table thead .mql-source {
        background: #eaf4ee;
        z-index: 3;
    }
    .mql-group .raw {
        background: #fff3df;
        color: #7c3d00;
    }
    .mql-group .pipe {
        background: #e6f3eb;
        color: #14532d;
    }
    .mql-table tfoot td {
        background: #eaf4ee;
        font-weight: 900;
        border-top: 2px solid #0f7a42;
    }
    .mql-empty {
        padding: 22px !important;
        color: #64748b;
        text-align: center !important;
    }
    .mkt-todo-row {
        display: flex;
        align-items: start;
        gap: 12px;
    }
    .mkt-todo-check {
        margin-top: 4px;
        width: 18px;
        height: 18px;
        accent-color: #0b5b39;
    }
    .mkt-todo-body {
        flex: 1 1 auto;
        min-width: 0;
    }
    .mkt-todo-complete .mkt-item-title {
        text-decoration: line-through;
        color: #8a9791;
    }
    .mkt-team-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }
    .mkt-team-card {
        border: 1px solid #e5ede8;
        background: #fbfdfc;
        border-radius: 18px;
        padding: 14px;
        min-width: 0;
    }
    .mkt-team-name {
        margin: 0;
        font-size: 15px;
        font-weight: 800;
    }
    .mkt-team-role {
        margin-top: 4px;
        color: #66756e;
        font-size: 12px;
    }
    .mkt-team-stats {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 8px;
        margin-top: 12px;
    }
    .mkt-team-stats .mkt-mini-stat strong {
        font-size: 18px;
    }
    details.mkt-details summary {
        list-style: none;
        cursor: pointer;
    }
    details.mkt-details summary::-webkit-details-marker {
        display: none;
    }
    @media (max-width: 960px) {
        .mkt-row,
        .mkt-row-manager,
        .mkt-form-grid,
        .mkt-team-grid {
            grid-template-columns: 1fr;
        }
        .mkt-mini-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .mkt-cpl-grid,
        .mkt-funnel-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .mql-kpis {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .mql-summary {
            grid-template-columns: 1fr;
            gap: 14px;
        }
        .mql-section-head {
            display: grid;
        }
    }
    @media (max-width: 640px) {
        #mainContent > .container {
            padding-inline: 14px !important;
            padding-bottom: 96px !important;
        }
        .mkt-shell {
            gap: 14px;
        }
        .mkt-header {
            flex-direction: column;
            align-items: start;
        }
        .mkt-title {
            font-size: 28px;
        }
        .mkt-card {
            padding: 16px;
            border-radius: 20px;
        }
        .mkt-mini-grid {
            grid-template-columns: 1fr 1fr;
        }
        .mkt-cpl-grid,
        .mkt-funnel-grid {
            grid-template-columns: 1fr;
        }
        .mkt-todo-stats {
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 7px;
        }
        .mkt-todo-stats .mkt-mini-stat {
            border-radius: 14px;
            padding: 10px 8px;
        }
        .mkt-todo-stats .mkt-mini-stat strong {
            font-size: 21px;
        }
        .mkt-todo-stats .mkt-mini-stat span {
            font-size: 9px;
            letter-spacing: .06em;
        }
        .mkt-modal-backdrop {
            align-items: center;
            justify-content: center;
            padding: 18px;
        }
        .mkt-modal {
            border-radius: 24px;
            max-height: 86vh;
            overflow-y: auto;
            padding: 16px;
        }
        .mkt-modal .mkt-form-grid {
            grid-template-columns: 1fr;
        }
        .mkt-attendance-mobile-simple .attendance-widget {
            margin: 0 !important;
            padding: 16px !important;
            border-radius: 22px !important;
        }
        .mkt-attendance-mobile-simple .attendance-widget-top {
            margin-bottom: 12px !important;
        }
        .mkt-attendance-mobile-simple .attendance-widget-copy,
        .mkt-attendance-mobile-simple .attendance-widget-status,
        .mkt-attendance-mobile-simple .attendance-widget-stats,
        .mkt-attendance-mobile-simple .attendance-widget-inline {
            display: none !important;
        }
        .mkt-attendance-mobile-simple .attendance-widget-grid {
            display: grid !important;
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            gap: 10px !important;
            margin-bottom: 12px !important;
        }
        .mkt-attendance-mobile-simple .attendance-widget-card {
            min-height: 76px !important;
            padding: 12px !important;
        }
        .mkt-attendance-mobile-simple .attendance-widget-value {
            font-size: 17px !important;
            line-height: 1.2 !important;
        }
        .mkt-attendance-mobile-simple .attendance-widget-actions {
            display: grid !important;
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            gap: 10px !important;
            margin: 0 !important;
            align-items: stretch !important;
        }
        .mkt-attendance-mobile-simple .attendance-widget-btn {
            width: 100% !important;
            min-height: 44px !important;
            padding: 11px 12px !important;
        }
        .mkt-attendance-mobile-simple .attendance-widget-error,
        .mkt-attendance-mobile-simple .attendance-widget-request {
            margin-top: 12px !important;
        }
        .mkt-mobile-hidden {
            display: none !important;
        }
        .mkt-mobile-task-panel {
            display: none !important;
        }
        body:not(.marketing-show-todos) .mkt-todo-mobile-panel {
            display: none !important;
        }
        body.marketing-show-todos .mkt-attendance-shell {
            display: none !important;
        }
        body.marketing-show-todos .mkt-mobile-task-panel {
            display: none !important;
        }
        .mkt-header-actions {
            display: none;
        }
        .mql-filter {
            display: grid;
        }
        .mql-input,
        .mql-btn {
            width: 100%;
            min-width: 0;
        }
        .mql-period {
            margin-left: 0;
        }
        .mql-kpis {
            grid-template-columns: 1fr;
        }
        .mql-kpi {
            border-right: 0;
            border-bottom: 1px solid #d8e4dc;
        }
        .mql-score {
            align-items: flex-start;
        }
    }
</style>
@endpush

@section('content')
<div class="mkt-shell">
    <header class="mkt-header">
        <div class="mkt-header-copy">
            <div class="mkt-kicker">Marketing Workspace</div>
            <h1 class="mkt-title">Marketing Execution Desk</h1>
        </div>
        <div class="mkt-header-actions">
            <a href="{{ route('marketing.profile') }}" class="mkt-btn mkt-btn-secondary">Profile</a>
            <a href="{{ route('execution-desk.index') }}" class="mkt-btn mkt-btn-secondary">Execution Desk</a>
            <div class="mkt-role-chip">
                <i class="fas fa-briefcase"></i>
                <span>{{ $displayRole }}</span>
            </div>
        </div>
    </header>

    <section id="marketing-dashboard" class="mkt-attendance-shell mkt-attendance-mobile-simple">
        @include('attendance._widget')
    </section>

    @if($isAdmin ?? false)
        @include('marketing.partials.lead-quality-overview')
    @endif

    <section id="marketing-meta-cpl" class="mkt-card">
        <div class="mkt-card-header">
            <div>
                <h2 class="mkt-card-title">Meta CPL Performance</h2>
                <div class="mkt-card-copy">{{ $metaCplSummary['range']['label'] ?? 'This Month' }} spend, quality, hold and closing performance from Meta Lead Ads.</div>
            </div>
            <div class="mkt-filter-group">
                @foreach($metaFilters as $key => $label)
                    <a href="{{ route('marketing.dashboard', ['meta_filter' => $key]) }}#marketing-meta-cpl" class="mkt-filter-link {{ $metaFilter === $key ? 'active' : '' }}">{{ $label }}</a>
                @endforeach
                <a href="{{ route('integrations.facebook-lead-ads.settings') }}" class="mkt-filter-link">Settings</a>
            </div>
        </div>

        <div class="mkt-cpl-grid">
            <div class="mkt-cpl-stat">
                <strong>{{ number_format((int) ($metaCplSummary['totals']['leads'] ?? 0)) }}</strong>
                <span>Meta Leads</span>
            </div>
            <div class="mkt-cpl-stat">
                <strong>{{ $money($metaCplSummary['totals']['spend'] ?? 0) }}</strong>
                <span>Spend</span>
            </div>
            <div class="mkt-cpl-stat">
                <strong>{{ ($metaCplSummary['totals']['cpl'] ?? null) !== null ? $money($metaCplSummary['totals']['cpl']) : '-' }}</strong>
                <span>CPL</span>
            </div>
            <div class="mkt-cpl-stat">
                <strong>{{ number_format((int) ($metaCplSummary['totals']['qualified'] ?? 0)) }}</strong>
                <span>Qualified</span>
            </div>
            <div class="mkt-cpl-stat">
                <strong>{{ number_format((int) ($metaCplSummary['totals']['bad'] ?? 0)) }}</strong>
                <span>Bad / Junk</span>
            </div>
            <div class="mkt-cpl-stat">
                <strong>{{ number_format((int) ($metaCplSummary['totals']['hold'] ?? 0)) }}</strong>
                <span>Hold / CNP</span>
            </div>
            <div class="mkt-cpl-stat">
                <strong>{{ number_format((int) ($metaCplSummary['totals']['closers'] ?? 0)) }}</strong>
                <span>Closers</span>
            </div>
            <div class="mkt-cpl-stat">
                <strong>{{ ($metaCplSummary['totals']['cost_per_closer'] ?? null) !== null ? $money($metaCplSummary['totals']['cost_per_closer']) : '-' }}</strong>
                <span>Cost / Closer</span>
            </div>
        </div>

        @if(($metaCplSummary['funnel'] ?? collect())->isNotEmpty())
            <div class="mkt-funnel-grid">
                @foreach($metaCplSummary['funnel'] as $step)
                    <div class="mkt-funnel-card">
                        <strong>{{ number_format((int) $step['count']) }}</strong>
                        <span>{{ $step['label'] }} · {{ number_format((float) $step['rate'], 1) }}%</span>
                        <div class="mkt-funnel-bar"><i style="width:{{ min(100, max(0, (float) $step['rate'])) }}%"></i></div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="mkt-alert-list">
            @forelse(($metaCplSummary['alerts'] ?? collect()) as $alert)
                <div class="mkt-alert {{ $alert['type'] ?? 'warning' }}">
                    <strong>{{ $alert['title'] }}</strong>
                    <span>{{ $alert['body'] }}</span>
                </div>
            @empty
                <div class="mkt-alert">
                    <strong>No urgent Meta issues</strong>
                    <span>Current range me high CPL, high Hold/CNP ya missing quality alert nahi mila.</span>
                </div>
            @endforelse
        </div>

        <div class="overflow-x-auto">
            <table class="mkt-cpl-table">
                <thead>
                    <tr>
                        <th>Meta Form</th>
                        <th class="num">Leads</th>
                        <th class="num">Spend</th>
                        <th class="num">CPL</th>
                        <th class="num">Avg Quality</th>
                        <th class="num">5/4/3/2/1</th>
                        <th class="num">Hold</th>
                        <th class="num">Bad %</th>
                        <th class="num">Closers</th>
                        <th class="num">Cost / Closer</th>
                        <th>Verdict</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse(($metaCplSummary['forms'] ?? collect()) as $row)
                        <tr>
                            <td>{{ $row['name'] }}</td>
                            <td class="num">{{ number_format($row['leads']) }}</td>
                            <td class="num">{{ $money($row['spend']) }}</td>
                            <td class="num">{{ $row['cpl'] !== null ? $money($row['cpl']) : '-' }}</td>
                            <td class="num">{{ $row['avg_quality'] !== null ? number_format($row['avg_quality'], 1).'/5' : '-' }}</td>
                            <td class="num">{{ $row['quality_5'] }}/{{ $row['quality_4'] }}/{{ $row['quality_3'] }}/{{ $row['quality_2'] }}/{{ $row['quality_1'] }}</td>
                            <td class="num">{{ number_format($row['hold']) }} ({{ number_format($row['hold_rate'], 1) }}%)</td>
                            <td class="num">{{ number_format($row['bad_rate'], 1) }}%</td>
                            <td class="num">{{ number_format($row['closers']) }}</td>
                            <td class="num">{{ $row['cost_per_closer'] !== null ? $money($row['cost_per_closer']) : '-' }}</td>
                            <td>{{ $row['verdict'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="mkt-empty">No Meta CPL data yet. Save Marketing API token, backfill attribution, then sync insights.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(($metaCplSummary['campaigns'] ?? collect())->isNotEmpty())
            <div class="overflow-x-auto" style="margin-top:14px;">
                <table class="mkt-cpl-table">
                    <thead>
                        <tr>
                            <th>Campaign / Adset / Ad</th>
                            <th class="num">Leads</th>
                            <th class="num">Spend</th>
                            <th class="num">CPL</th>
                            <th class="num">Avg Quality</th>
                            <th class="num">Hold</th>
                            <th class="num">Closers</th>
                            <th class="num">Cost / Closer</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($metaCplSummary['campaigns'] as $row)
                            <tr>
                                <td>
                                    <strong>{{ $row['campaign_name'] }}</strong>
                                    <div class="mkt-item-meta">{{ $row['adset_name'] }} / {{ $row['ad_name'] }}</div>
                                </td>
                                <td class="num">{{ number_format($row['leads']) }}</td>
                                <td class="num">{{ $money($row['spend']) }}</td>
                                <td class="num">{{ $row['cpl'] !== null ? $money($row['cpl']) : '-' }}</td>
                                <td class="num">{{ $row['avg_quality'] !== null ? number_format($row['avg_quality'], 1).'/5' : '-' }}</td>
                                <td class="num">{{ number_format($row['hold']) }} ({{ number_format($row['hold_rate'], 1) }}%)</td>
                                <td class="num">{{ number_format($row['closers']) }}</td>
                                <td class="num">{{ $row['cost_per_closer'] !== null ? $money($row['cost_per_closer']) : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section id="marketing-attendance-history" class="mkt-card mkt-attendance-history">
        <div class="mkt-card-header">
            <div>
                <h2 class="mkt-card-title">My Attendance History</h2>
                <div class="mkt-card-copy">Latest punch records from your attendance register.</div>
            </div>
            <a href="{{ route('attendance.regularizations') }}" class="mkt-foot-link">Regularize</a>
        </div>

        @if($recentAttendanceRecords->isEmpty())
            <div class="mkt-empty">No attendance records found yet.</div>
        @else
            <div class="overflow-x-auto">
                <table class="mkt-attendance-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Punch In</th>
                            <th>Punch Out</th>
                            <th>Status</th>
                            <th>Hours</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentAttendanceRecords as $record)
                            @php
                                $missingOut = $record->has_missing_punch_out || ($record->first_punch_in_at && ! $record->last_punch_out_at);
                                $statusClass = $missingOut ? 'missing' : str_replace('-', '_', str_replace(' ', '_', strtolower((string) $record->status)));
                                $workedMinutes = (int) ($record->worked_minutes ?? 0);
                            @endphp
                            <tr>
                                <td>{{ $record->attendance_date?->format('d M Y') ?? '--' }}</td>
                                <td>{{ $record->first_punch_in_at?->format('h:i A') ?? '--' }}</td>
                                <td>{{ $record->last_punch_out_at?->format('h:i A') ?? '--' }}</td>
                                <td>
                                    <span class="mkt-attendance-status {{ $statusClass }}">
                                        {{ $missingOut ? 'Missing Out' : $attendanceStatusLabel($record->status) }}
                                    </span>
                                </td>
                                <td>{{ intdiv($workedMinutes, 60) }}h {{ $workedMinutes % 60 }}m</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <div class="mkt-row">
        <section id="marketing-tasks" class="mkt-card mkt-mobile-task-panel">
            <div class="mkt-card-header">
                <div>
                    <h2 class="mkt-card-title">{{ $taskTitle }}</h2>
                </div>
                <a href="{{ route('execution-desk.index') }}" class="mkt-foot-link">View all</a>
            </div>

            <div class="mkt-mini-grid">
                <div class="mkt-mini-stat">
                    <strong>{{ $ownTaskCounts['open'] ?? 0 }}</strong>
                    <span>Open</span>
                </div>
                <div class="mkt-mini-stat">
                    <strong>{{ $ownTaskCounts['in_progress'] ?? 0 }}</strong>
                    <span>In Progress</span>
                </div>
                <div class="mkt-mini-stat">
                    <strong>{{ $ownTaskCounts['overdue'] ?? 0 }}</strong>
                    <span>Overdue</span>
                </div>
            </div>

            @if($recentOwnTasks->isEmpty())
                <div class="mkt-empty">{{ $taskEmpty }}</div>
            @else
                <div class="mkt-list">
                    @foreach($recentOwnTasks as $task)
                        <article class="mkt-item">
                            <div class="mkt-item-top">
                                <div>
                                    <h3 class="mkt-item-title">{{ $task->title }}</h3>
                                    <div class="mkt-item-meta">
                                        @if($task->due_at)
                                            Due {{ $task->due_at->format('d M, h:i A') }}
                                        @else
                                            No due date
                                        @endif
                                        @if($task->creator)
                                            - By {{ $task->creator->name }}
                                        @endif
                                    </div>
                                </div>
                                <span class="{{ $taskStatusClass($task->status) }}">{{ str_replace('_', ' ', $task->status) }}</span>
                            </div>
                            <div class="mkt-actions">
                                <a href="{{ route('execution-desk.tasks.show', $task) }}" class="mkt-btn mkt-btn-primary">Open Task</a>
                                <a href="{{ route('execution-desk.index') }}" class="mkt-btn mkt-btn-secondary">All Tasks</a>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <section id="marketing-todos" class="mkt-card mkt-todo-mobile-panel">
            <div class="mkt-card-header">
                <div>
                    <h2 class="mkt-card-title">My To-Do</h2>
                </div>
                <button type="button" class="mkt-btn mkt-btn-primary mkt-btn-round" onclick="openMarketingTodoModal()" aria-label="Add To-Do">
                    <i class="fas fa-plus"></i>
                </button>
            </div>

            <div class="mkt-mini-grid mkt-todo-stats">
                <div class="mkt-mini-stat">
                    <strong>{{ $todoOpenCount }}</strong>
                    <span>Open</span>
                </div>
                <div class="mkt-mini-stat">
                    <strong>{{ $todoDoneCount }}</strong>
                    <span>Done</span>
                </div>
                <div class="mkt-mini-stat">
                    <strong>{{ $selfTodoCounts['total'] ?? $myTodos->count() }}</strong>
                    <span>Total</span>
                </div>
            </div>

            <div class="mkt-note-form">
                @if($myTodos->isEmpty())
                    <div class="mkt-empty">No todos added yet.</div>
                @else
                    <div class="mkt-list">
                        @foreach($myTodos as $todo)
                            <article class="mkt-item {{ $todo->isCompleted() ? 'mkt-todo-complete' : '' }}">
                                <div class="mkt-todo-row">
                                    <form method="POST" action="{{ route('marketing.self-todos.complete', $todo) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input class="mkt-todo-check" type="checkbox" onchange="this.form.submit()" {{ $todo->isCompleted() ? 'checked' : '' }}>
                                    </form>
                                    <div class="mkt-todo-body">
                                        <div class="mkt-item-top">
                                            <div>
                                                <h3 class="mkt-item-title">{{ $todo->title }}</h3>
                                                <div class="mkt-item-meta">
                                                    {{ $todo->note ?: 'No note added.' }}
                                                    @if($todo->due_at)
                                                        - Due {{ $todo->due_at->format('d M, h:i A') }}
                                                    @endif
                                                </div>
                                            </div>
                                            <span class="{{ $priorityClass($todo->priority) }}">{{ $todo->priority }}</span>
                                        </div>
                                        <details class="mkt-details">
                                            <summary class="mkt-foot-link">Edit</summary>
                                            <form method="POST" action="{{ route('marketing.self-todos.update', $todo) }}" class="mkt-note-form">
                                                @csrf
                                                @method('PUT')
                                                <div class="mkt-form-grid">
                                                    <input class="mkt-field" type="text" name="title" value="{{ $todo->title }}" required>
                                                    <input class="mkt-field" type="datetime-local" name="due_at" value="{{ $todo->due_at ? $todo->due_at->format('Y-m-d\TH:i') : '' }}">
                                                    <select class="mkt-field" name="priority">
                                                        @foreach(\App\Models\SelfTodo::PRIORITIES as $priority)
                                                            <option value="{{ $priority }}" {{ $todo->priority === $priority ? 'selected' : '' }}>{{ ucfirst($priority) }} priority</option>
                                                        @endforeach
                                                    </select>
                                                    <select class="mkt-field" name="status">
                                                        <option value="open" {{ $todo->status === 'open' ? 'selected' : '' }}>Open</option>
                                                        <option value="completed" {{ $todo->status === 'completed' ? 'selected' : '' }}>Completed</option>
                                                    </select>
                                                </div>
                                                <textarea class="mkt-textarea" name="note">{{ $todo->note }}</textarea>
                                                <div class="mkt-actions">
                                                    <button type="submit" class="mkt-btn mkt-btn-primary">Save</button>
                                                </div>
                                            </form>
                                            <div class="mkt-actions">
                                                <form method="POST" action="{{ route('marketing.self-todos.destroy', $todo) }}" onsubmit="return confirm('Delete this todo?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="mkt-btn mkt-btn-danger">Delete</button>
                                                </form>
                                            </div>
                                        </details>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    </div>

    @if($isManager)
        <div class="mkt-row-manager mkt-mobile-hidden">
            <section class="mkt-card">
                <div class="mkt-card-header">
                    <div>
                        <h2 class="mkt-card-title">Team Attendance</h2>
                    </div>
                </div>

                @if(!$teamAttendanceSummary)
                    <div class="mkt-empty">No marketing team members assigned.</div>
                @else
                    <div class="mkt-mini-grid">
                        <div class="mkt-mini-stat"><strong>{{ $teamAttendanceSummary['present'] }}</strong><span>Present</span></div>
                        <div class="mkt-mini-stat"><strong>{{ $teamAttendanceSummary['absent'] }}</strong><span>Absent</span></div>
                        <div class="mkt-mini-stat"><strong>{{ $teamAttendanceSummary['on_leave'] }}</strong><span>On Leave</span></div>
                        <div class="mkt-mini-stat"><strong>{{ $teamAttendanceSummary['late'] }}</strong><span>Late</span></div>
                        <div class="mkt-mini-stat"><strong>{{ $teamAttendanceSummary['not_punched_in'] }}</strong><span>No Punch</span></div>
                        <div class="mkt-mini-stat"><strong>{{ $teamAttendanceSummary['team_size'] }}</strong><span>Team Size</span></div>
                    </div>
                @endif
            </section>

            <section class="mkt-card">
                <div class="mkt-card-header">
                    <div>
                        <h2 class="mkt-card-title">Team Tasks</h2>
                    </div>
                </div>

                @if($teamTaskCards->isEmpty())
                    <div class="mkt-empty">No marketing team tasks found.</div>
                @else
                    <div class="mkt-team-grid">
                        @foreach($teamTaskCards as $card)
                            <article class="mkt-team-card">
                                <h3 class="mkt-team-name">{{ $card['member']->name }}</h3>
                                <div class="mkt-team-role">{{ $card['member']->role->name ?? 'Team member' }}</div>
                                <div class="mkt-team-stats">
                                    <div class="mkt-mini-stat"><strong>{{ $card['open_count'] }}</strong><span>Open</span></div>
                                    <div class="mkt-mini-stat"><strong>{{ $card['overdue_count'] }}</strong><span>Overdue</span></div>
                                    <div class="mkt-mini-stat"><strong>{{ $card['waiting_count'] }}</strong><span>Waiting</span></div>
                                </div>
                                @if($card['recent_tasks']->isNotEmpty())
                                    <div class="mkt-note-form">
                                        @foreach($card['recent_tasks'] as $task)
                                            <div class="mkt-item" style="padding:10px; margin-bottom:8px;">
                                                <div class="mkt-item-title" style="font-size:13px;">{{ $task->title }}</div>
                                                <div class="mkt-item-meta">{{ $task->due_at ? 'Due '.$task->due_at->format('d M, h:i A') : 'No due date' }}</div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    @endif

    <div class="mkt-row mkt-mobile-hidden">
        <section id="marketing-announcements" class="mkt-card">
            <div class="mkt-card-header">
                <div>
                    <h2 class="mkt-card-title">Announcements</h2>
                </div>
                <span class="mkt-badge">{{ $announcementCount }} active</span>
            </div>

            @if($activeBroadcasts->isEmpty())
                <div class="mkt-empty">No announcements right now.</div>
            @else
                <div class="mkt-announcement-list">
                    @foreach($activeBroadcasts as $message)
                        <article class="mkt-item">
                            <div class="mkt-item-top">
                                <div>
                                    <h3 class="mkt-item-title">{{ $message->title }}</h3>
                                    <div class="mkt-item-meta">
                                        {{ $message->message }}
                                        @if($message->sender)
                                            - By {{ $message->sender->name }}
                                        @endif
                                    </div>
                                </div>
                                <span class="{{ $message->priority === 'urgent' ? 'mkt-badge mkt-badge-red' : ($message->priority === 'important' ? 'mkt-badge mkt-badge-amber' : 'mkt-badge mkt-badge-blue') }}">{{ $message->priority ?? 'normal' }}</span>
                            </div>
                            @if($message->action_url)
                                <div class="mkt-actions">
                                    <a href="{{ $message->action_url }}" class="mkt-btn mkt-btn-secondary">{{ $message->action_label ?: 'Open' }}</a>
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <section id="marketing-notes" class="mkt-card">
            <div class="mkt-card-header">
                <div>
                    <h2 class="mkt-card-title">Manager Notes</h2>
                </div>
                <span class="mkt-badge">{{ $managerNoteCount }} notes</span>
            </div>

            @if($isManager)
                <form method="POST" action="{{ route('marketing.manager-notes.store') }}">
                    @csrf
                    <div class="mkt-form-grid">
                        <input class="mkt-field" type="text" name="title" placeholder="Note title" required>
                        <input class="mkt-field" type="number" name="sort_order" min="0" placeholder="Sort order (optional)">
                    </div>
                    <textarea class="mkt-textarea" name="note" placeholder="Write a short team note" required></textarea>
                    <div class="mkt-actions">
                        <button type="submit" class="mkt-btn mkt-btn-primary">Add Note</button>
                    </div>
                </form>
                <div class="mkt-note-form">
            @endif

            @if($managerNotes->isEmpty())
                <div class="mkt-empty">No manager notes available.</div>
            @else
                <div class="mkt-note-list">
                    @foreach($managerNotes as $note)
                        <article class="mkt-item">
                            <div class="mkt-item-top">
                                <div>
                                    <h3 class="mkt-item-title">{{ $note->title }}</h3>
                                    <div class="mkt-item-meta">{{ $note->note }}</div>
                                </div>
                                @if($isManager)
                                    <span class="mkt-badge mkt-badge-green">Live</span>
                                @endif
                            </div>
                            @if($isManager)
                                <details class="mkt-details">
                                    <summary class="mkt-foot-link">Edit note</summary>
                                    <form method="POST" action="{{ route('marketing.manager-notes.update', $note) }}" class="mkt-note-form">
                                        @csrf
                                        @method('PUT')
                                        <div class="mkt-form-grid">
                                            <input class="mkt-field" type="text" name="title" value="{{ $note->title }}" required>
                                            <input class="mkt-field" type="number" name="sort_order" min="0" value="{{ $note->sort_order }}">
                                        </div>
                                        <textarea class="mkt-textarea" name="note" required>{{ $note->note }}</textarea>
                                        <div class="mkt-actions">
                                            <button type="submit" class="mkt-btn mkt-btn-primary">Save</button>
                                        </div>
                                    </form>
                                    <div class="mkt-actions">
                                        <form method="POST" action="{{ route('marketing.manager-notes.archive', $note) }}" onsubmit="return confirm('Archive this note?')">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="mkt-btn mkt-btn-danger">Archive</button>
                                        </form>
                                    </div>
                                </details>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif

            @if($isManager)
                </div>
            @endif
        </section>
    </div>
</div>

<div id="marketingTodoModal" class="mkt-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="marketingTodoModalTitle" data-has-errors="{{ $errors->any() ? '1' : '0' }}">
    <div class="mkt-modal">
        <div class="mkt-modal-header">
            <div>
                <h2 id="marketingTodoModalTitle" class="mkt-modal-title">Add To-Do</h2>
            </div>
            <button type="button" class="mkt-modal-close" onclick="closeMarketingTodoModal()" aria-label="Close">&times;</button>
        </div>
        @if($errors->any())
            <div class="mkt-form-error">{{ $errors->first() }}</div>
        @endif
        <form id="marketingTodoCreateForm" method="POST" action="{{ route('marketing.self-todos.store') }}">
            @csrf
            <div class="mkt-form-grid">
                <input class="mkt-field" type="text" name="title" value="{{ old('title') }}" placeholder="To-Do title" required>
                <input class="mkt-field" type="datetime-local" name="due_at" value="{{ old('due_at') }}" aria-label="Due date and time">
                <select class="mkt-field" name="priority">
                    <option value="medium" @selected(old('priority', 'medium') === 'medium')>Medium priority</option>
                    <option value="high" @selected(old('priority', 'medium') === 'high')>High priority</option>
                    <option value="low" @selected(old('priority', 'medium') === 'low')>Low priority</option>
                </select>
            </div>
            <textarea class="mkt-textarea" name="note" placeholder="Optional note">{{ old('note') }}</textarea>
            <div class="mkt-actions">
                <button type="button" class="mkt-btn mkt-btn-secondary" onclick="closeMarketingTodoModal()">Cancel</button>
                <button type="submit" form="marketingTodoCreateForm" class="mkt-btn mkt-btn-primary">Save To-Do</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        function syncMarketingMobilePanel() {
            const showingTodos = window.location.hash === '#marketing-todos';
            document.body.classList.toggle('marketing-show-todos', showingTodos);

            document.querySelectorAll('#mobileFooterNav .footer-nav-link[href*="marketing/dashboard"]').forEach((link) => {
                const isTodoLink = link.getAttribute('href')?.includes('#marketing-todos');
                link.classList.toggle('active', showingTodos ? isTodoLink : !isTodoLink);
            });
        }

        syncMarketingMobilePanel();
        window.addEventListener('hashchange', syncMarketingMobilePanel);
    })();

    function openMarketingTodoModal() {
        const modal = document.getElementById('marketingTodoModal');
        if (!modal) return;
        modal.classList.add('is-open');
        const titleInput = modal.querySelector('input[name="title"]');
        if (titleInput) setTimeout(() => titleInput.focus(), 80);
    }

    function closeMarketingTodoModal() {
        const modal = document.getElementById('marketingTodoModal');
        if (modal) modal.classList.remove('is-open');
    }

    document.addEventListener('click', function (event) {
        const modal = document.getElementById('marketingTodoModal');
        if (modal && event.target === modal) {
            closeMarketingTodoModal();
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('marketingTodoModal');
        if (modal?.dataset.hasErrors === '1') {
            if (window.location.hash !== '#marketing-todos') {
                window.location.hash = 'marketing-todos';
            }
            openMarketingTodoModal();
        }
    });
</script>
@endpush

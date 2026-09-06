@extends('layouts.app')

@section('title', 'Attendance Sheet')
@section('page-title', 'Attendance Sheet')

@push('styles')
<style>
    .hr-sheet {
        --line: rgba(15, 23, 42, 0.08);
        --line-strong: rgba(15, 23, 42, 0.14);
        --text: #13261a;
        --muted: #64748b;
        --green: #14532d;
        --green-soft: #e9f9ef;
        --red: #b42318;
        --red-soft: #fff0f0;
        --amber: #a15c07;
        --amber-soft: #fff6da;
        --slate: #475467;
        --slate-soft: #f3f5f7;
        --blue: #175cd3;
        --blue-soft: #eff4ff;
        display: flex;
        flex-direction: column;
        gap: 18px;
        color: var(--text);
    }

    .hr-sheet * { box-sizing: border-box; }

    .hr-sheet-card {
        background: #fff;
        border: 1px solid var(--line);
        border-radius: 26px;
        box-shadow: 0 18px 44px rgba(15, 23, 42, 0.06);
    }

    .hr-sheet-toolbar {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        gap: 14px;
        padding: 22px;
    }

    .hr-sheet-field {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .hr-sheet-field label {
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .hr-sheet-input,
    .hr-sheet-select,
    .hr-sheet-textarea {
        width: 100%;
        min-height: 44px;
        border-radius: 16px;
        border: 1px solid var(--line-strong);
        background: #fff;
        padding: 12px 14px;
        color: var(--text);
        font-size: 14px;
    }

    .hr-sheet-textarea {
        min-height: 94px;
        resize: vertical;
    }

    .hr-sheet-toolbar-actions {
        grid-column: 1 / -1;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
    }

    .hr-sheet-summary {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .hr-sheet-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        min-height: 40px;
        border-radius: 999px;
        padding: 10px 14px;
        background: #f8fafc;
        border: 1px solid var(--line);
        font-size: 13px;
        font-weight: 700;
        color: var(--slate);
    }

    .hr-sheet-pill strong {
        color: var(--text);
    }

    .hr-sheet-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .hr-sheet-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 44px;
        border-radius: 999px;
        border: 1px solid var(--line-strong);
        padding: 11px 16px;
        background: #fff;
        color: var(--text);
        font-size: 13px;
        font-weight: 800;
        text-decoration: none;
        cursor: pointer;
    }

    .hr-sheet-btn.primary {
        background: linear-gradient(135deg, #165a3a, #20724a);
        border-color: #165a3a;
        color: #fff;
        box-shadow: 0 16px 32px rgba(22, 90, 58, 0.18);
    }

    .hr-sheet-btn.soft {
        background: #f8fafc;
    }

    .hr-sheet-table-controls {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 14px 18px;
        border-top: 1px solid var(--line);
        background: linear-gradient(180deg, #fff, #fbfdfc);
    }

    .hr-sheet-scroll-hint {
        font-size: 12px;
        font-weight: 700;
        color: var(--muted);
    }

    .hr-sheet-lock-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .hr-sheet-lock-btn {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        min-height: 34px;
        border-radius: 999px;
        border: 1px solid var(--line-strong);
        background: #fff;
        padding: 8px 12px;
        color: var(--slate);
        font-size: 12px;
        font-weight: 900;
        cursor: pointer;
        box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
    }

    .hr-sheet-lock-btn[aria-pressed="true"] {
        border-color: rgba(20, 83, 45, 0.2);
        background: var(--green-soft);
        color: var(--green);
    }

    .hr-sheet-lock-dot {
        width: 8px;
        height: 8px;
        border-radius: 999px;
        background: #cbd5e1;
    }

    .hr-sheet-lock-btn[aria-pressed="true"] .hr-sheet-lock-dot {
        background: #16a34a;
    }

    .hr-sheet-table-wrap {
        overflow: auto;
        border-radius: 0 0 26px 26px;
        max-height: calc(100vh - 230px);
        scrollbar-gutter: stable both-edges;
    }

    .hr-sheet-table {
        width: 100%;
        min-width: 1320px;
        border-collapse: separate;
        border-spacing: 0;
    }

    .hr-sheet-table th,
    .hr-sheet-table td {
        border-bottom: 1px solid rgba(15, 23, 42, 0.06);
        border-right: 1px solid rgba(15, 23, 42, 0.05);
        background: #fff;
        vertical-align: top;
    }

    .hr-sheet-table th {
        position: sticky;
        top: 0;
        z-index: 8;
        background: #f8fafc;
        padding: 14px 10px;
        text-align: center;
    }

    .hr-sheet-employee-col {
        min-width: 280px;
        max-width: 280px;
        background: #fff;
    }

    .hr-sheet-table-wrap.is-left-locked .hr-sheet-employee-col {
        position: sticky;
        left: 0;
        z-index: 7;
        box-shadow: 12px 0 18px -18px rgba(15, 23, 42, 0.35);
    }

    .hr-sheet-table th.hr-sheet-employee-col {
        text-align: left;
        padding: 18px;
        background: #f8fafc;
    }

    .hr-sheet-table-wrap.is-left-locked .hr-sheet-table th.hr-sheet-employee-col {
        z-index: 10;
    }

    .hr-sheet-table-wrap.is-right-locked .hr-sheet-table th:last-child,
    .hr-sheet-table-wrap.is-right-locked .hr-sheet-table td:last-child {
        position: sticky;
        right: 0;
        z-index: 6;
        box-shadow: -12px 0 18px -18px rgba(15, 23, 42, 0.35);
    }

    .hr-sheet-table-wrap.is-right-locked .hr-sheet-table th:last-child {
        z-index: 9;
        background: #f8fafc;
    }

    .hr-sheet-table-wrap.is-right-locked .hr-sheet-table td:last-child {
        background: #fff;
    }

    .hr-sheet-employee {
        padding: 16px;
    }

    .hr-sheet-employee-name {
        font-size: 15px;
        font-weight: 800;
        color: var(--text);
    }

    .hr-sheet-employee-meta {
        margin-top: 6px;
        font-size: 12px;
        line-height: 1.45;
        color: var(--muted);
    }

    .hr-sheet-quick-actions {
        display: flex;
        gap: 8px;
        margin-top: 12px;
        flex-wrap: wrap;
    }

    .hr-sheet-mini-btn {
        min-height: 34px;
        padding: 7px 10px;
        border-radius: 999px;
        border: 1px solid var(--line-strong);
        background: #fff;
        font-size: 11px;
        font-weight: 800;
        color: var(--slate);
        cursor: pointer;
    }

    .hr-sheet-date-head {
        min-width: 108px;
    }

    .hr-sheet-date-head strong {
        display: block;
        font-size: 14px;
        color: var(--text);
    }

    .hr-sheet-date-head span {
        display: block;
        margin-top: 3px;
        font-size: 11px;
        color: var(--muted);
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }

    .hr-sheet-cell {
        padding: 10px;
    }

    .hr-sheet-cell-btn {
        width: 100%;
        min-height: 92px;
        border-radius: 18px;
        border: 1px solid var(--line);
        background: #fff;
        padding: 10px;
        text-align: left;
        cursor: pointer;
        transition: transform 0.16s ease, border-color 0.16s ease, box-shadow 0.16s ease;
    }

    .hr-sheet-cell-btn:hover {
        transform: translateY(-1px);
        border-color: rgba(20, 83, 45, 0.2);
        box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08);
    }

    .hr-sheet-cell-code {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 42px;
        min-height: 30px;
        border-radius: 999px;
        padding: 0 10px;
        font-size: 12px;
        font-weight: 900;
        letter-spacing: 0.06em;
    }

    .hr-sheet-status-present,
    .hr-sheet-status-late,
    .hr-sheet-status-leave,
    .hr-sheet-status-week_off,
    .hr-sheet-status-holiday,
    .hr-sheet-status-half_day,
    .hr-sheet-status-absent { color: var(--text); }
    .hr-sheet-status-present .hr-sheet-cell-code { background: var(--green-soft); color: var(--green); }
    .hr-sheet-status-late .hr-sheet-cell-code { background: var(--amber-soft); color: var(--amber); }
    .hr-sheet-status-half_day .hr-sheet-cell-code { background: #f1e9ff; color: #6d49cb; }
    .hr-sheet-status-absent .hr-sheet-cell-code { background: var(--red-soft); color: var(--red); }
    .hr-sheet-status-leave .hr-sheet-cell-code { background: #ecfdf3; color: #027a48; }
    .hr-sheet-status-week_off .hr-sheet-cell-code { background: var(--slate-soft); color: var(--slate); }
    .hr-sheet-status-holiday .hr-sheet-cell-code { background: var(--blue-soft); color: var(--blue); }

    .hr-sheet-cell-source {
        margin-top: 8px;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--muted);
    }

    .hr-sheet-cell-time {
        margin-top: 8px;
        font-size: 12px;
        color: var(--slate);
    }

    .hr-sheet-pd-sticker {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 22px;
        margin-top: 8px;
        border-radius: 999px;
        padding: 4px 8px;
        font-size: 10px;
        font-weight: 900;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        border: 1px solid transparent;
    }

    button.hr-sheet-pd-sticker {
        cursor: pointer;
        border: 1px solid transparent;
    }

    button.hr-sheet-pd-sticker:hover {
        transform: translateY(-1px);
        box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08);
    }

    .hr-sheet-pd-sticker.pd {
        background: var(--green-soft);
        border-color: rgba(20, 83, 45, 0.16);
        color: var(--green);
    }

    .hr-sheet-pd-sticker.pending {
        background: var(--amber-soft);
        border-color: rgba(161, 92, 7, 0.18);
        color: var(--amber);
    }

    .hr-sheet-pd-sticker.npd {
        background: var(--slate-soft);
        border-color: rgba(71, 84, 103, 0.16);
        color: var(--slate);
    }

    .hr-sheet-cell-note {
        margin-top: 8px;
        font-size: 11px;
        line-height: 1.4;
        color: var(--muted);
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .hr-sheet-empty {
        padding: 30px;
        text-align: center;
        color: var(--muted);
        font-size: 14px;
        font-weight: 700;
    }

    .hr-sheet-modal-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.55);
        display: none;
        align-items: center;
        justify-content: center;
        padding: 18px;
        z-index: 9999;
    }

    .hr-sheet-modal-backdrop.open {
        display: flex;
    }

    .hr-sheet-modal {
        width: min(100%, 760px);
        max-height: calc(100vh - 36px);
        overflow: auto;
        border-radius: 28px;
        background: #fff;
        border: 1px solid var(--line);
        box-shadow: 0 28px 70px rgba(15, 23, 42, 0.24);
    }

    .hr-sheet-modal-head,
    .hr-sheet-modal-body,
    .hr-sheet-modal-foot {
        padding: 24px;
    }

    .hr-sheet-modal-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        border-bottom: 1px solid var(--line);
    }

    .hr-sheet-modal-title {
        font-size: 25px;
        font-weight: 900;
        letter-spacing: -0.04em;
        color: var(--text);
    }

    .hr-sheet-modal-subtitle {
        margin-top: 8px;
        font-size: 14px;
        line-height: 1.5;
        color: var(--muted);
    }

    .hr-sheet-modal-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }

    .hr-sheet-modal-foot {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        border-top: 1px solid var(--line);
    }

    .hr-sheet-history {
        margin-top: 18px;
        border: 1px solid var(--line);
        border-radius: 18px;
        background: #f8fafc;
        padding: 14px;
    }

    .hr-sheet-productive-summary {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 18px;
    }

    .hr-sheet-productive-list {
        display: grid;
        gap: 12px;
    }

    .hr-sheet-productive-item {
        border: 1px solid var(--line);
        border-radius: 18px;
        background: linear-gradient(180deg, #ffffff, #fbfdfc);
        padding: 18px;
        box-shadow: 0 14px 30px rgba(15, 23, 42, 0.05);
    }

    .hr-sheet-productive-item-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
    }

    .hr-sheet-productive-item-title {
        font-size: 15px;
        font-weight: 900;
        color: var(--text);
    }

    .hr-sheet-productive-item-meta {
        margin-top: 10px;
        color: var(--muted);
        font-size: 13px;
        line-height: 1.65;
    }

    .hr-sheet-productive-proof-section {
        margin-top: 14px;
        padding-top: 14px;
        border-top: 1px dashed rgba(15, 23, 42, 0.12);
    }

    .hr-sheet-productive-proof-title {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 10px;
        color: var(--text);
        font-size: 12px;
        font-weight: 900;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .hr-sheet-proof-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(112px, 1fr));
        gap: 10px;
    }

    .hr-sheet-proof-link {
        display: block;
        overflow: hidden;
        border: 1px solid var(--line);
        border-radius: 16px;
        background: #fff;
        color: var(--text);
        text-decoration: none;
        box-shadow: 0 8px 18px rgba(15, 23, 42, 0.05);
    }

    .hr-sheet-proof-link img {
        display: block;
        width: 100%;
        height: 92px;
        object-fit: cover;
        background: #f8fafc;
    }

    .hr-sheet-proof-link span {
        display: block;
        padding: 8px 9px;
        color: var(--slate);
        font-size: 11px;
        font-weight: 800;
    }

    .hr-sheet-proof-empty {
        border: 1px dashed rgba(15, 23, 42, 0.14);
        border-radius: 16px;
        background: linear-gradient(180deg, #ffffff, #f8fafc);
        padding: 14px;
        color: var(--muted);
        font-size: 12px;
        font-weight: 700;
    }

    .hr-sheet-productive-status {
        display: inline-flex;
        align-items: center;
        min-height: 26px;
        border-radius: 999px;
        padding: 5px 10px;
        font-size: 10px;
        font-weight: 900;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        background: var(--amber-soft);
        color: var(--amber);
    }

    .hr-sheet-productive-status.verified,
    .hr-sheet-productive-status.approved,
    .hr-sheet-productive-status.completed,
    .hr-sheet-productive-status.done {
        background: var(--green-soft);
        color: var(--green);
    }

    .hr-sheet-productive-item-actions {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-top: 16px;
        padding-top: 14px;
        border-top: 1px solid rgba(226, 232, 240, 0.75);
        flex-wrap: wrap;
    }

    .hr-sheet-productive-primary-actions,
    .hr-sheet-productive-secondary-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .hr-sheet-productive-action-form {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .hr-sheet-productive-reason {
        min-height: 38px;
        width: min(260px, 100%);
        border: 1px solid var(--line-strong);
        border-radius: 999px;
        padding: 9px 12px;
        font-size: 12px;
        color: var(--text);
    }

    .hr-sheet-btn.danger {
        border-color: rgba(220, 38, 38, 0.22);
        background: #fff5f5;
        color: #b42318;
    }

    .hr-sheet-btn.danger:hover {
        background: #fee2e2;
    }

    .hr-sheet-productive-note {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        background: #f8fafc;
        padding: 9px 12px;
        color: var(--slate);
        font-size: 12px;
        font-weight: 800;
        line-height: 1.35;
    }

    .hr-sheet-productive-footer-note {
        max-width: 520px;
        color: var(--muted);
        font-size: 12px;
        font-weight: 700;
        line-height: 1.5;
    }

    .hr-sheet-history-item + .hr-sheet-history-item {
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px dashed rgba(15, 23, 42, 0.12);
    }

    .hr-sheet-alert {
        padding: 14px 18px;
        border-radius: 18px;
        border: 1px solid rgba(22, 90, 58, 0.16);
        background: #f1fcf5;
        color: #165a3a;
        font-size: 14px;
        font-weight: 700;
    }

    .hr-sheet-errors {
        padding: 14px 18px;
        border-radius: 18px;
        border: 1px solid rgba(180, 35, 24, 0.16);
        background: #fff3f2;
        color: #912018;
        font-size: 14px;
        font-weight: 700;
    }

    @media (max-width: 900px) {
        .hr-sheet-toolbar {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 768px) {
        .hr-sheet {
            padding: 8px;
        }

        .hr-sheet-toolbar {
            grid-template-columns: 1fr;
            padding: 16px;
        }

        .hr-sheet-toolbar-actions {
            align-items: stretch;
        }

        .hr-sheet-actions,
        .hr-sheet-summary {
            width: 100%;
        }

        .hr-sheet-actions .hr-sheet-btn,
        .hr-sheet-actions a.hr-sheet-btn {
            flex: 1 1 100%;
        }

        .hr-sheet-table-controls {
            align-items: stretch;
            flex-direction: column;
        }

        .hr-sheet-lock-actions {
            width: 100%;
        }

        .hr-sheet-lock-btn {
            flex: 1 1 auto;
            justify-content: center;
        }

        .hr-sheet-employee-col,
        .hr-sheet-table th.hr-sheet-employee-col {
            min-width: 220px;
            max-width: 220px;
        }

        .hr-sheet-modal-grid {
            grid-template-columns: 1fr;
        }

        .hr-sheet-modal-head,
        .hr-sheet-modal-body,
        .hr-sheet-modal-foot {
            padding: 16px;
        }

        .hr-sheet-modal-foot {
            flex-direction: column;
        }

        .hr-sheet-modal-foot .hr-sheet-actions {
            width: 100%;
        }
    }
</style>
@endpush

@php
    $statusMeta = [
        'present' => ['code' => 'P', 'label' => 'Present'],
        'late' => ['code' => 'LT', 'label' => 'Late'],
        'half_day' => ['code' => 'HD', 'label' => 'Half Day'],
        'absent' => ['code' => 'A', 'label' => 'Absent'],
        'leave' => ['code' => 'L', 'label' => 'Leave'],
        'week_off' => ['code' => 'WO', 'label' => 'Week Off'],
        'holiday' => ['code' => 'H', 'label' => 'Holiday'],
    ];

    $totalEmployees = $profiles->count();
    $manualCount = $recordsByUser->flatten(1)->filter(fn ($record) => $record instanceof \App\Models\AttendanceRecord && $record->hasActiveManualOverride())->count();
    $monthRecordCount = $recordsByUser->flatten(1)->count();
    $canManageAttendanceSheet = auth()->check() && auth()->user()->isHrManager();
@endphp

@section('content')
<div class="hr-sheet">
    @include('hr-manager.attendance._nav')

    @if(session('success'))
        <div class="hr-sheet-alert">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="hr-sheet-errors">{{ session('error') }}</div>
    @endif

    @if($errors->any())
        <div class="hr-sheet-errors">{{ $errors->first() }}</div>
    @endif

    <div class="hr-sheet-card">
        <form method="GET" action="{{ route('hr-manager.attendance.sheet') }}" class="hr-sheet-toolbar">
            <div class="hr-sheet-field">
                <label for="month">Month</label>
                <input class="hr-sheet-input" id="month" type="month" name="month" value="{{ $month->format('Y-m') }}">
            </div>
            <div class="hr-sheet-field">
                <label for="search">Search Employee</label>
                <input class="hr-sheet-input" id="search" type="text" name="search" value="{{ $filters['search'] }}" placeholder="Name ya email">
            </div>
            <div class="hr-sheet-field">
                <label for="user_id">User Filter</label>
                <select class="hr-sheet-select" id="user_id" name="user_id">
                    <option value="">All users</option>
                    @foreach($employeeOptions as $employeeOption)
                        <option value="{{ $employeeOption['id'] }}" @selected((string) $filters['user_id'] === (string) $employeeOption['id'])>
                            {{ $employeeOption['name'] }}
                            @if(!empty($employeeOption['employee_code']))
                                ({{ $employeeOption['employee_code'] }})
                            @endif
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="hr-sheet-field">
                <label for="status">Status Filter</label>
                <select class="hr-sheet-select" id="status" name="status">
                    <option value="">All status</option>
                    @foreach($statusMeta as $value => $meta)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $meta['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="hr-sheet-field">
                <label for="role">Team / Role</label>
                <select class="hr-sheet-select" id="role" name="role">
                    <option value="">All roles</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->slug }}" @selected($filters['role'] === $role->slug)>{{ $role->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="hr-sheet-field">
                <label for="office_location_id">Office</label>
                <select class="hr-sheet-select" id="office_location_id" name="office_location_id">
                    <option value="">All offices</option>
                    @foreach($offices as $office)
                        <option value="{{ $office->id }}" @selected((string) $filters['office_location_id'] === (string) $office->id)>{{ $office->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="hr-sheet-field">
                <label for="manual_only">Manual Only</label>
                <select class="hr-sheet-select" id="manual_only" name="manual_only">
                    <option value="0" @selected(!$filters['manual_only'])>Show all</option>
                    <option value="1" @selected($filters['manual_only'])>Manual rows only</option>
                </select>
            </div>

            <div class="hr-sheet-toolbar-actions">
                <div class="hr-sheet-summary">
                    <div class="hr-sheet-pill">Employees <strong>{{ $totalEmployees }}</strong></div>
                    <div class="hr-sheet-pill">Manual Cells <strong>{{ $manualCount }}</strong></div>
                    <div class="hr-sheet-pill">Month Records <strong>{{ $monthRecordCount }}</strong></div>
                    <div class="hr-sheet-pill">PD Days <strong>{{ $productiveDaySummary['pd'] ?? 0 }}</strong></div>
                    <div class="hr-sheet-pill">Pending Days <strong>{{ $productiveDaySummary['pending'] ?? 0 }}</strong></div>
                    <div class="hr-sheet-pill">NPD Days <strong>{{ $productiveDaySummary['npd'] ?? 0 }}</strong></div>
                </div>

                <div class="hr-sheet-actions">
                    <button class="hr-sheet-btn primary" type="submit">Apply Filters</button>
                    <a class="hr-sheet-btn soft" href="{{ route('hr-manager.attendance.sheet', ['month' => now()->format('Y-m')]) }}">Reset</a>
                    @if($canManageAttendanceSheet)
                        <a class="hr-sheet-btn" href="{{ route('hr-manager.attendance.reports.export.attendance', array_filter([
                            'year' => $month->year,
                            'month' => $month->month,
                            'format' => 'xlsx',
                            'user_id' => $filters['user_id'],
                            'search' => $filters['search'],
                            'status' => $filters['status'],
                            'role' => $filters['role'],
                            'office_location_id' => $filters['office_location_id'],
                            'manual_only' => $filters['manual_only'] ? 1 : null,
                        ], fn ($value) => $value !== null && $value !== '')) }}">Export Register</a>
                    @endif
                </div>
            </div>
        </form>

        <div class="hr-sheet-table-controls">
            <div class="hr-sheet-scroll-hint">
                Scroll horizontally. You can lock the employee column or the last date column for easier comparison.
            </div>
            <div class="hr-sheet-lock-actions" role="group" aria-label="Attendance sheet column locks">
                <button type="button" class="hr-sheet-lock-btn" data-sheet-lock-toggle="left" aria-pressed="true">
                    <span class="hr-sheet-lock-dot"></span>
                    Lock Left
                </button>
                <button type="button" class="hr-sheet-lock-btn" data-sheet-lock-toggle="right" aria-pressed="false">
                    <span class="hr-sheet-lock-dot"></span>
                    Lock Right
                </button>
            </div>
        </div>

        <div class="hr-sheet-table-wrap is-left-locked" id="attendanceSheetWrap">
            <table class="hr-sheet-table">
                <thead>
                    <tr>
                        <th class="hr-sheet-employee-col">
                            <div style="font-size:11px; font-weight:900; letter-spacing:0.08em; text-transform:uppercase; color:#64748b;">Attendance Sheet</div>
                            <div style="margin-top:8px; font-size:24px; font-weight:900; letter-spacing:-0.04em;">{{ $month->format('F Y') }}</div>
                            <div style="margin-top:8px; font-size:13px; color:#64748b;">Auto + manual override matrix</div>
                        </th>
                        @foreach($dates as $date)
                            <th>
                                <div class="hr-sheet-date-head">
                                    <strong>{{ $date->format('d') }}</strong>
                                    <span>{{ $date->format('D') }}</span>
                                </div>
                                @if($canManageAttendanceSheet)
                                    <button
                                        type="button"
                                        class="hr-sheet-mini-btn"
                                        data-modal-trigger="true"
                                        data-scope="column"
                                        data-date="{{ $date->toDateString() }}"
                                        data-date-label="{{ $date->format('d M Y') }}"
                                        data-title="Bulk date override"
                                        data-subtitle="Filtered employees ke liye {{ $date->format('d M Y') }} status set karo."
                                    >
                                        Mark date
                                    </button>
                                @endif
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($profiles as $profile)
                        @php
                            $userRecords = $recordsByUser->get($profile->user_id, collect())->keyBy(fn ($record) => optional($record->attendance_date)->toDateString());
                        @endphp
                        <tr>
                            <td class="hr-sheet-employee-col">
                                <div class="hr-sheet-employee">
                                    <div class="hr-sheet-employee-name">{{ $profile->user?->name }}</div>
                                    <div class="hr-sheet-employee-meta">
                                        {{ $profile->user?->role?->name ?? 'Employee' }}<br>
                                        {{ $profile->officeLocation?->name ?? 'No office mapped' }}<br>
                                        {{ $profile->employee_code ?: 'No employee code' }}
                                    </div>

                                    @if($canManageAttendanceSheet)
                                        <div class="hr-sheet-quick-actions">
                                            <button
                                                type="button"
                                                class="hr-sheet-mini-btn"
                                                data-modal-trigger="true"
                                                data-scope="row"
                                                data-user-id="{{ $profile->user_id }}"
                                                data-user-name="{{ $profile->user?->name }}"
                                                data-title="Bulk employee override"
                                                data-subtitle="{{ $profile->user?->name }} ke current month cells ko ek saath mark karo."
                                            >
                                                Mark month
                                            </button>
                                        </div>
                                    @endif
                                </div>
                            </td>
                            @foreach($dates as $date)
                                @php
                                    /** @var \App\Models\AttendanceRecord|null $record */
                                    $record = $userRecords->get($date->toDateString());
                                    $status = $record?->status ?? 'absent';
                                    $meta = $statusMeta[$status] ?? ['code' => 'A', 'label' => ucfirst(str_replace('_', ' ', $status))];
                                    $sourceLabel = $record?->hasActiveManualOverride() ? 'Manual' : ($record?->status_source ? ucfirst(str_replace('_', ' ', $record->status_source)) : 'Auto');
                                    $latestLog = $record?->overrideLogs?->first();
                                    $historyLines = $record?->overrideLogs?->take(3)->map(function ($log) {
                                        $actor = $log->actor?->name ?: 'HR';
                                        return ucfirst($log->action) . ' by ' . $actor . ' on ' . optional($log->created_at)->format('d M, h:i A') . ($log->reason ? ' - ' . $log->reason : '');
                                    })->values()->all() ?? [];
                                    $isSalesProfile = in_array($profile->user?->role?->slug, \App\Services\ProductiveDayTrackerService::SALES_ROLE_SLUGS, true);
                                    $productiveEntry = null;
                                    if ($isSalesProfile) {
                                        $productiveEntry = $productiveDayTracker->get($profile->user_id . '|' . $date->toDateString()) ?? [
                                            'status' => 'npd',
                                            'label' => 'NPD',
                                            'assigned' => 0,
                                            'verified' => 0,
                                            'title' => 'No assigned visit',
                                        ];
                                    }
                                    $cellTitle = $latestLog ? implode(' | ', $historyLines) : 'Auto attendance cell';
                                    if ($productiveEntry) {
                                        $cellTitle .= ' | Productive: ' . $productiveEntry['label'] . ' (' . $productiveEntry['title'] . ')';
                                    }
                                    $productiveItems = $productiveEntry['items'] ?? [];
                                    $productiveItemsJson = e(json_encode($productiveItems, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT));
                                @endphp
                                <td>
                                    <div class="hr-sheet-cell">
                                        @if($canManageAttendanceSheet)
                                        <button
                                            type="button"
                                            class="hr-sheet-cell-btn hr-sheet-status-{{ $status }}"
                                            data-modal-trigger="true"
                                            data-scope="cell"
                                            data-user-id="{{ $profile->user_id }}"
                                            data-user-name="{{ $profile->user?->name }}"
                                            data-record-id="{{ $record?->id }}"
                                            data-date="{{ $date->toDateString() }}"
                                            data-date-label="{{ $date->format('d M Y') }}"
                                            data-title="Attendance override"
                                            data-subtitle="{{ $profile->user?->name }} - {{ $date->format('d M Y') }}"
                                            data-current-status="{{ $record?->manual_status ?: $record?->status }}"
                                            data-current-in="{{ optional($record?->manual_first_punch_in_at ?: $record?->first_punch_in_at)->format('H:i') }}"
                                            data-current-out="{{ optional($record?->manual_last_punch_out_at ?: $record?->last_punch_out_at)->format('H:i') }}"
                                            data-current-reason="{{ $record?->manual_override_reason }}"
                                            data-has-manual="{{ $record?->hasActiveManualOverride() ? '1' : '0' }}"
                                            data-history="{{ e(implode(' || ', $historyLines)) }}"
                                            title="{{ $cellTitle }}"
                                        >
                                            <span class="hr-sheet-cell-code">{{ $meta['code'] }}</span>
                                            <div class="hr-sheet-cell-source">{{ $sourceLabel }}</div>
                                            <div class="hr-sheet-cell-time">
                                                {{ optional($record?->first_punch_in_at)->format('h:i A') ?: '--' }}
                                                -
                                                {{ optional($record?->last_punch_out_at)->format('h:i A') ?: '--' }}
                                            </div>
                                            @if($productiveEntry)
                                                <span
                                                    class="hr-sheet-pd-sticker {{ $productiveEntry['status'] }}"
                                                    title="{{ $productiveEntry['title'] }}"
                                                    @if(!empty($productiveItems))
                                                        role="button"
                                                        tabindex="0"
                                                        data-productive-trigger="true"
                                                        data-employee-name="{{ $profile->user?->name }}"
                                                        data-date-label="{{ $date->format('d M Y') }}"
                                                        data-assigned="{{ $productiveEntry['assigned'] ?? 0 }}"
                                                        data-verified="{{ $productiveEntry['verified'] ?? 0 }}"
                                                        data-status="{{ $productiveEntry['status'] }}"
                                                        data-items="{!! $productiveItemsJson !!}"
                                                    @endif
                                                >
                                                    {{ $productiveEntry['label'] }}
                                                </span>
                                            @endif
                                            @if($record?->manual_override_reason)
                                                <div class="hr-sheet-cell-note">{{ $record->manual_override_reason }}</div>
                                            @elseif($latestLog)
                                                <div class="hr-sheet-cell-note">{{ $historyLines[0] ?? '' }}</div>
                                            @endif
                                        </button>
                                        @else
                                        <div class="hr-sheet-cell-btn hr-sheet-status-{{ $status }}" title="{{ $cellTitle }}">
                                            <span class="hr-sheet-cell-code">{{ $meta['code'] }}</span>
                                            <div class="hr-sheet-cell-source">{{ $sourceLabel }}</div>
                                            <div class="hr-sheet-cell-time">
                                                {{ optional($record?->first_punch_in_at)->format('h:i A') ?: '--' }}
                                                -
                                                {{ optional($record?->last_punch_out_at)->format('h:i A') ?: '--' }}
                                            </div>
                                            @if($productiveEntry)
                                                <span
                                                    class="hr-sheet-pd-sticker {{ $productiveEntry['status'] }}"
                                                    title="{{ $productiveEntry['title'] }}"
                                                    @if(!empty($productiveItems))
                                                        role="button"
                                                        tabindex="0"
                                                        data-productive-trigger="true"
                                                        data-employee-name="{{ $profile->user?->name }}"
                                                        data-date-label="{{ $date->format('d M Y') }}"
                                                        data-assigned="{{ $productiveEntry['assigned'] ?? 0 }}"
                                                        data-verified="{{ $productiveEntry['verified'] ?? 0 }}"
                                                        data-status="{{ $productiveEntry['status'] }}"
                                                        data-items="{!! $productiveItemsJson !!}"
                                                    @endif
                                                >
                                                    {{ $productiveEntry['label'] }}
                                                </span>
                                            @endif
                                            @if($record?->manual_override_reason)
                                                <div class="hr-sheet-cell-note">{{ $record->manual_override_reason }}</div>
                                            @elseif($latestLog)
                                                <div class="hr-sheet-cell-note">{{ $historyLines[0] ?? '' }}</div>
                                            @endif
                                        </div>
                                        @endif
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td class="hr-sheet-empty" colspan="{{ $dates->count() + 1 }}">No employees matched these filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="hr-sheet-modal-backdrop" id="productiveDayModal">
    <div class="hr-sheet-modal">
        <div class="hr-sheet-modal-head">
            <div>
                <div class="hr-sheet-modal-title" id="productiveModalTitle">Productive day details</div>
                <div class="hr-sheet-modal-subtitle" id="productiveModalSubtitle">Meeting / Site Visit details</div>
            </div>
            <button type="button" class="hr-sheet-btn soft" data-productive-close="true">Close</button>
        </div>

        <div class="hr-sheet-modal-body">
            <div class="hr-sheet-productive-summary">
                <div class="hr-sheet-pill">Assigned <strong id="productiveAssignedCount">0</strong></div>
                <div class="hr-sheet-pill">Verified <strong id="productiveVerifiedCount">0</strong></div>
                <div class="hr-sheet-pill">Status <strong id="productiveStatusLabel">-</strong></div>
            </div>
            <div class="hr-sheet-productive-list" id="productiveItemList"></div>
        </div>

        <div class="hr-sheet-modal-foot">
            <div class="hr-sheet-productive-footer-note">
                Click a photo thumbnail to open it. Verify and reject actions are available only to users allowed by Verification Routing.
            </div>
            <button type="button" class="hr-sheet-btn soft" data-productive-close="true">Close</button>
        </div>
    </div>
</div>

@if($canManageAttendanceSheet)
<div class="hr-sheet-modal-backdrop" id="attendanceOverrideModal">
    <div class="hr-sheet-modal">
        <div class="hr-sheet-modal-head">
            <div>
                <div class="hr-sheet-modal-title" id="attendanceModalTitle">Attendance override</div>
                <div class="hr-sheet-modal-subtitle" id="attendanceModalSubtitle">Cell details</div>
            </div>
            <button type="button" class="hr-sheet-btn soft" data-modal-close="true">Close</button>
        </div>

        <div class="hr-sheet-modal-body">
            <form id="attendanceOverrideForm" method="POST" action="{{ route('hr-manager.attendance.sheet.override') }}">
                @csrf
                <input type="hidden" name="scope" id="modalScope" value="cell">
                <input type="hidden" name="user_id" id="modalUserId">
                <input type="hidden" name="attendance_date" id="modalAttendanceDate">
                <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
                <input type="hidden" name="search" value="{{ $filters['search'] }}">
                <input type="hidden" name="status" value="{{ $filters['status'] }}">
                <input type="hidden" name="role" value="{{ $filters['role'] }}">
                <input type="hidden" name="office_location_id" value="{{ $filters['office_location_id'] }}">
                <input type="hidden" name="manual_only" value="{{ $filters['manual_only'] ? 1 : 0 }}">

                <div class="hr-sheet-modal-grid">
                    <div class="hr-sheet-field">
                        <label for="modalManualStatus">Status</label>
                        <select class="hr-sheet-select" id="modalManualStatus" name="manual_status">
                            <option value="">Auto calculate from timing</option>
                            @foreach($statusMeta as $value => $meta)
                                <option value="{{ $value }}">{{ $meta['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="hr-sheet-field">
                        <label>Scope</label>
                        <input class="hr-sheet-input" id="modalScopeLabel" type="text" value="Single cell" readonly>
                    </div>
                    <div class="hr-sheet-field">
                        <label for="modalPunchIn">Punch In</label>
                        <input class="hr-sheet-input" id="modalPunchIn" type="time" name="manual_first_punch_in_at">
                    </div>
                    <div class="hr-sheet-field">
                        <label for="modalPunchOut">Punch Out</label>
                        <input class="hr-sheet-input" id="modalPunchOut" type="time" name="manual_last_punch_out_at">
                    </div>
                </div>

                <div class="hr-sheet-field" style="margin-top:14px;">
                    <label for="modalReason">Reason</label>
                    <textarea class="hr-sheet-textarea" id="modalReason" name="manual_override_reason" placeholder="Enter override reason, approval note, or audit remark." required></textarea>
                </div>

                <div class="hr-sheet-history" id="attendanceHistoryBox" style="display:none;">
                    <div style="font-size:12px; font-weight:900; letter-spacing:0.08em; text-transform:uppercase; color:#64748b;">Recent audit</div>
                    <div id="attendanceHistoryBody" style="margin-top:12px;"></div>
                </div>
            </form>
        </div>

        <div class="hr-sheet-modal-foot">
            <form id="attendanceClearForm" method="POST" action="{{ route('hr-manager.attendance.sheet.clear') }}" style="display:none;">
                @csrf
                <input type="hidden" name="attendance_record_id" id="modalClearRecordId">
                <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
                <input type="hidden" name="user_id" value="{{ $filters['user_id'] }}">
                <input type="hidden" name="search" value="{{ $filters['search'] }}">
                <input type="hidden" name="status" value="{{ $filters['status'] }}">
                <input type="hidden" name="role" value="{{ $filters['role'] }}">
                <input type="hidden" name="office_location_id" value="{{ $filters['office_location_id'] }}">
                <input type="hidden" name="manual_only" value="{{ $filters['manual_only'] ? 1 : 0 }}">
                <input type="hidden" name="clear_reason" id="modalClearReason">
                <button type="submit" class="hr-sheet-btn">Clear Override</button>
            </form>

            <div class="hr-sheet-actions" style="margin-left:auto;">
                <button type="button" class="hr-sheet-btn soft" data-modal-close="true">Cancel</button>
                <button type="submit" form="attendanceOverrideForm" class="hr-sheet-btn primary">Save Override</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const sheetWrap = document.getElementById('attendanceSheetWrap');
        const lockButtons = document.querySelectorAll('[data-sheet-lock-toggle]');
        const storageKey = 'hrAttendanceSheetColumnLocks';

        function applySheetLocks(locks) {
            if (!sheetWrap) {
                return;
            }

            const normalized = {
                left: locks.left !== false,
                right: locks.right === true,
            };

            sheetWrap.classList.toggle('is-left-locked', normalized.left);
            sheetWrap.classList.toggle('is-right-locked', normalized.right);

            lockButtons.forEach((button) => {
                const side = button.dataset.sheetLockToggle;
                button.setAttribute('aria-pressed', normalized[side] ? 'true' : 'false');
            });

            try {
                window.localStorage.setItem(storageKey, JSON.stringify(normalized));
            } catch (error) {
                // Local storage blocked hone par bhi UI functional rahega.
            }
        }

        function readSheetLocks() {
            try {
                return JSON.parse(window.localStorage.getItem(storageKey) || '{}') || {};
            } catch (error) {
                return {};
            }
        }

        applySheetLocks(readSheetLocks());

        lockButtons.forEach((button) => {
            button.addEventListener('click', function () {
                const side = this.dataset.sheetLockToggle;
                const locks = readSheetLocks();
                const current = this.getAttribute('aria-pressed') === 'true';
                locks[side] = !current;
                applySheetLocks(locks);
            });
        });

        const productiveModal = document.getElementById('productiveDayModal');
        const productiveTitle = document.getElementById('productiveModalTitle');
        const productiveSubtitle = document.getElementById('productiveModalSubtitle');
        const productiveAssigned = document.getElementById('productiveAssignedCount');
        const productiveVerified = document.getElementById('productiveVerifiedCount');
        const productiveStatus = document.getElementById('productiveStatusLabel');
        const productiveList = document.getElementById('productiveItemList');
        const productiveVerifyBase = @json(url('/hr-manager/attendance/productive-items'));
        const csrfToken = @json(csrf_token());

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function statusLabel(status) {
            return String(status || 'pending').replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
        }

        function productiveActions(item) {
            const base = `${productiveVerifyBase}/${encodeURIComponent(item.type)}/${encodeURIComponent(item.id)}`;
            const viewButton = item.view_url
                ? `<a class="hr-sheet-btn soft" href="${escapeHtml(item.view_url)}" target="_blank" rel="noopener">View</a>`
                : '<span class="hr-sheet-productive-note">View link unavailable</span>';
            const verifyButton = item.can_verify
                ? `
                    <form method="POST" action="${base}/verify">
                        <input type="hidden" name="_token" value="${escapeHtml(csrfToken)}">
                        <button type="submit" class="hr-sheet-btn primary">Verify</button>
                    </form>
                `
                : `<span class="hr-sheet-productive-note">${escapeHtml(item.cannot_verify_reason || 'You are not eligible to verify this item based on Verification Routing.')}</span>`;
            const rejectButton = item.can_reject
                ? `
                    <form method="POST" action="${base}/reject" class="hr-sheet-productive-action-form" onsubmit="return this.reason.value.trim().length > 0;">
                        <input type="hidden" name="_token" value="${escapeHtml(csrfToken)}">
                        <input class="hr-sheet-productive-reason" name="reason" type="text" maxlength="500" placeholder="Reject reason required" required>
                        <button type="submit" class="hr-sheet-btn danger">Reject</button>
                    </form>
                `
                : '';

            return `
                <div class="hr-sheet-productive-primary-actions">${viewButton}${verifyButton}</div>
                <div class="hr-sheet-productive-secondary-actions">${rejectButton}</div>
            `;
        }

        function proofGrid(title, photos) {
            const list = Array.isArray(photos) ? photos.filter(Boolean) : [];

            return `
                <div class="hr-sheet-productive-proof-section">
                    <div class="hr-sheet-productive-proof-title">
                        <span>${escapeHtml(title)}</span>
                        <span>${list.length} photo${list.length === 1 ? '' : 's'}</span>
                    </div>
                    ${list.length
                        ? `<div class="hr-sheet-proof-grid">
                            ${list.map((url, index) => `
                                <a href="${escapeHtml(url)}" target="_blank" rel="noopener" class="hr-sheet-proof-link">
                                    <img src="${escapeHtml(url)}" alt="${escapeHtml(title)} ${index + 1}" loading="lazy">
                                    <span>Open photo ${index + 1}</span>
                                </a>
                            `).join('')}
                        </div>`
                        : '<div class="hr-sheet-proof-empty">No photos uploaded yet.</div>'
                    }
                </div>
            `;
        }

        function productiveItemHtml(item) {
            const time = item.completed_at || item.scheduled_at || item.date || '-';
            const status = item.verification_status || 'pending';
            const proofPhotos = item.proof_photos || [];
            const regularPhotos = item.photos || [];
            const closerProofPhotos = item.closer_proof_photos || [];

            return `
                <div class="hr-sheet-productive-item">
                    <div class="hr-sheet-productive-item-head">
                        <div>
                            <div class="hr-sheet-productive-item-title">${escapeHtml(item.type_label)} #${escapeHtml(item.id)} · ${escapeHtml(item.customer_name || 'Unknown lead')}</div>
                            <div class="hr-sheet-productive-item-meta">
                                Phone: ${escapeHtml(item.phone || '-')}<br>
                                Project/Property: ${escapeHtml(item.project || '-')}<br>
                                Assigned: ${escapeHtml(item.assigned_to_name || '-')}<br>
                                Time: ${escapeHtml(time)}
                            </div>
                        </div>
                        <span class="hr-sheet-productive-status ${escapeHtml(status)}">${escapeHtml(statusLabel(status))}</span>
                    </div>
                    ${proofGrid('Completion proof', proofPhotos)}
                    ${regularPhotos.length ? proofGrid('Uploaded photos', regularPhotos) : ''}
                    ${closerProofPhotos.length ? proofGrid('Closer proof', closerProofPhotos) : ''}
                    <div class="hr-sheet-productive-item-actions">
                        ${productiveActions(item)}
                    </div>
                </div>
            `;
        }

        function openProductiveModal(trigger) {
            if (!productiveModal) {
                return;
            }

            let items = [];
            try {
                items = JSON.parse(trigger.dataset.items || '[]');
            } catch (error) {
                items = [];
            }

            productiveTitle.textContent = `${trigger.dataset.employeeName || 'Employee'} · ${trigger.dataset.dateLabel || ''}`;
            productiveSubtitle.textContent = 'Meeting and site visit productive-day details';
            productiveAssigned.textContent = trigger.dataset.assigned || String(items.length);
            productiveVerified.textContent = trigger.dataset.verified || String(items.filter((item) => item.is_verified).length);
            productiveStatus.textContent = (trigger.dataset.status || 'pending').toUpperCase();
            productiveList.innerHTML = items.length
                ? items.map(productiveItemHtml).join('')
                : '<div class="hr-sheet-empty">No assigned meeting/site visit found.</div>';

            productiveModal.classList.add('open');
        }

        document.querySelectorAll('[data-productive-trigger="true"]').forEach((trigger) => {
            trigger.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                openProductiveModal(this);
            });
            trigger.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    event.stopPropagation();
                    openProductiveModal(this);
                }
            });
        });

        document.querySelectorAll('[data-productive-close="true"]').forEach((button) => {
            button.addEventListener('click', function () {
                productiveModal?.classList.remove('open');
            });
        });

        productiveModal?.addEventListener('click', function (event) {
            if (event.target === productiveModal) {
                productiveModal.classList.remove('open');
            }
        });

        const modal = document.getElementById('attendanceOverrideModal');
        const title = document.getElementById('attendanceModalTitle');
        const subtitle = document.getElementById('attendanceModalSubtitle');
        const scopeInput = document.getElementById('modalScope');
        const scopeLabel = document.getElementById('modalScopeLabel');
        const userIdInput = document.getElementById('modalUserId');
        const dateInput = document.getElementById('modalAttendanceDate');
        const statusInput = document.getElementById('modalManualStatus');
        const punchInInput = document.getElementById('modalPunchIn');
        const punchOutInput = document.getElementById('modalPunchOut');
        const reasonInput = document.getElementById('modalReason');
        const clearForm = document.getElementById('attendanceClearForm');
        const clearRecordId = document.getElementById('modalClearRecordId');
        const clearReason = document.getElementById('modalClearReason');
        const historyBox = document.getElementById('attendanceHistoryBox');
        const historyBody = document.getElementById('attendanceHistoryBody');

        function openModal(trigger) {
            const scope = trigger.dataset.scope || 'cell';
            scopeInput.value = scope;
            userIdInput.value = trigger.dataset.userId || '';
            dateInput.value = trigger.dataset.date || '';
            title.textContent = trigger.dataset.title || 'Attendance override';
            subtitle.textContent = trigger.dataset.subtitle || '';
            statusInput.value = trigger.dataset.currentStatus || '';
            punchInInput.value = trigger.dataset.currentIn || '';
            punchOutInput.value = trigger.dataset.currentOut || '';
            reasonInput.value = trigger.dataset.currentReason || '';

            scopeLabel.value = scope === 'row'
                ? 'Full month for employee'
                : (scope === 'column' ? 'Selected date for filtered employees' : 'Single attendance cell');

            const hasManual = trigger.dataset.hasManual === '1';
            const recordId = trigger.dataset.recordId || '';
            clearForm.style.display = hasManual && recordId ? 'block' : 'none';
            clearRecordId.value = recordId;
            clearReason.value = reasonInput.value || '';

            const history = (trigger.dataset.history || '').split(' || ').filter(Boolean);
            if (history.length) {
                historyBody.innerHTML = history.map((line) => `<div class="hr-sheet-history-item">${line}</div>`).join('');
                historyBox.style.display = 'block';
            } else {
                historyBody.innerHTML = '';
                historyBox.style.display = 'none';
            }

            modal.classList.add('open');
        }

        document.querySelectorAll('[data-modal-trigger="true"]').forEach((trigger) => {
            trigger.addEventListener('click', function () {
                openModal(this);
            });
        });

        document.querySelectorAll('[data-modal-close="true"]').forEach((button) => {
            button.addEventListener('click', function () {
                modal.classList.remove('open');
            });
        });

        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                modal.classList.remove('open');
            }
        });

        reasonInput.addEventListener('input', function () {
            clearReason.value = this.value;
        });
    })();
</script>
@endpush
@endif

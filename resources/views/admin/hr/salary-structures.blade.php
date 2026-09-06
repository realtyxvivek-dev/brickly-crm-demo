@extends('layouts.app')

@section('title', 'Salary Structures')
@section('page-title', '')
@section('page-subtitle', '')
@section('hide-app-header', '1')

@push('styles')
<style>
    .salary-workspace {
        --bg: #f7f5ef;
        --surface: rgba(255, 255, 255, 0.96);
        --line: rgba(22, 47, 32, 0.10);
        --text: #162f20;
        --muted: #728173;
        --green: #165a3a;
        --green-soft: #e8f7ee;
        --green-gradient: linear-gradient(135deg, #165a3a, #20724a);
        --amber: #a66a00;
        --amber-soft: #fff3d6;
        --red: #c34b32;
        --red-soft: #ffede8;
        --violet: #7754d8;
        --violet-soft: #f2edff;
        --slate: #536374;
        --slate-soft: #eef3f7;
        --shadow: 0 20px 50px rgba(24, 49, 38, 0.08);
        --radius: 22px;
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .salary-workspace * { box-sizing: border-box; }

    .salary-card {
        background: var(--surface);
        border: 1px solid var(--line);
        border-radius: var(--radius);
        box-shadow: var(--shadow);
    }

    /* Header */
    .salary-header {
        display: none;
        align-items: flex-end;
        justify-content: space-between;
        gap: 18px;
        flex-wrap: wrap;
        padding: 22px 24px;
    }

    .salary-kicker {
        font-size: 11px;
        font-weight: 900;
        letter-spacing: 0.15em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .salary-title {
        margin-top: 6px;
        font-size: 30px;
        line-height: 1;
        letter-spacing: -0.05em;
        font-weight: 900;
        color: var(--text);
    }

    .salary-subtitle {
        margin-top: 8px;
        font-size: 13px;
        color: var(--muted);
        font-weight: 500;
        max-width: 640px;
        line-height: 1.5;
    }

    /* Nav tabs */
    .salary-tabs {
        display: flex;
        gap: 8px;
        padding: 0 24px 20px;
        flex-wrap: wrap;
    }

    .salary-tab {
        padding: 10px 20px;
        border-radius: 999px;
        border: 1.5px solid var(--line);
        background: #fff;
        color: var(--muted);
        font-family: inherit;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.18s ease;
    }

    .salary-tab:hover { border-color: var(--green); color: var(--green); }

    .salary-tab.active {
        background: var(--green);
        color: #fff;
        border-color: var(--green);
        box-shadow: 0 8px 22px rgba(22, 90, 58, 0.18);
    }

    .salary-panel { display: none; }
    .salary-panel.active { display: block; }

    .salary-form { padding: 0 24px 24px; }

    /* Form grid */
    .form-row {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        align-items: flex-end;
    }

    .field { display: flex; flex-direction: column; gap: 6px; flex: 1 1 180px; min-width: 160px; }

    .field-label {
        font-size: 10px;
        font-weight: 800;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .field-input, .field-select, .date-input {
        padding: 10px 14px;
        border: 1.5px solid var(--line);
        border-radius: 12px;
        font-family: inherit;
        font-size: 14px;
        font-weight: 600;
        color: var(--text);
        background: #fafcf8;
        outline: none;
        width: 100%;
        transition: border-color 0.18s;
    }

    .field-input:focus, .field-select:focus, .date-input:focus { border-color: var(--green); }
    .field-input::placeholder { color: var(--muted); font-weight: 500; }
    .field-select { cursor: pointer; }

    /* Total Summary Card */
    .total-card {
        margin-top: 14px;
        padding: 20px 24px;
        background: var(--green-gradient);
        border-radius: 22px;
        box-shadow: 0 24px 60px rgba(22, 90, 58, 0.22);
        color: #fff;
    }

    .total-label {
        font-size: 10px;
        font-weight: 800;
        letter-spacing: 0.15em;
        text-transform: uppercase;
        opacity: 0.78;
        margin-bottom: 8px;
    }

    .total-row {
        display: flex;
        align-items: flex-end;
        gap: 24px;
        flex-wrap: wrap;
    }

    .total-big {
        font-size: 38px;
        font-weight: 900;
        letter-spacing: -0.06em;
        line-height: 1;
    }

    .total-sub {
        display: flex;
        flex-direction: column;
        gap: 4px;
        font-size: 13px;
        opacity: 0.88;
    }

    .total-sub span { font-weight: 700; }

    .progress-wrap { margin-top: 14px; }

    .progress-label {
        display: flex;
        justify-content: space-between;
        font-size: 11px;
        opacity: 0.82;
        font-weight: 600;
        margin-bottom: 6px;
    }

    .progress-bar {
        height: 7px;
        background: rgba(255, 255, 255, 0.22);
        border-radius: 999px;
        overflow: hidden;
    }

    .progress-fill {
        height: 100%;
        background: rgba(255, 255, 255, 0.92);
        border-radius: 999px;
        transition: width 0.4s ease;
    }

    /* Copy Section */
    .copy-toggle {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px 16px;
        background: var(--slate-soft);
        border: 1px solid var(--line);
        border-radius: 14px;
        font-size: 12px;
        font-weight: 700;
        color: var(--slate);
        cursor: pointer;
        width: 100%;
        justify-content: center;
        transition: all 0.18s;
        font-family: inherit;
        margin-top: 14px;
    }

    .copy-toggle:hover { background: var(--violet-soft); color: var(--violet); border-color: var(--violet); }

    .copy-panel {
        display: none;
        gap: 10px;
        align-items: flex-end;
        padding: 14px 18px;
        border: 1px solid var(--line);
        border-radius: 16px;
        flex-wrap: wrap;
        background: var(--surface);
        margin-top: 10px;
    }

    .copy-panel.open { display: flex; }

    .copy-panel .field { min-width: 160px; }

    .copy-btn {
        padding: 10px 20px;
        border-radius: 999px;
        border: none;
        background: #3f73ca;
        color: #fff;
        font-family: inherit;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.18s;
        white-space: nowrap;
    }

    .copy-btn:hover { background: #2d5fa8; transform: translateY(-1px); }

    /* Section head */
    .section-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-top: 18px;
        margin-bottom: 10px;
    }

    .section-title {
        font-size: 15px;
        font-weight: 800;
        color: var(--text);
        letter-spacing: -0.03em;
    }

    /* Components Table */
    .salary-components {
        overflow-x: auto;
        border: 1px solid var(--line);
        border-radius: 20px;
    }

    .salary-components table {
        width: 100%;
        min-width: 860px;
        border-collapse: collapse;
    }

    .salary-components th, .salary-components td {
        padding: 14px 16px;
        border-bottom: 1px solid var(--line);
        text-align: left;
        vertical-align: middle;
    }

    .salary-components th {
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.12em;
        color: var(--muted);
        background: #fbfcfa;
    }

    .salary-components td { font-size: 14px; color: var(--text); }
    .salary-components tr:last-child td { border-bottom: none; }
    .salary-components tbody tr:hover td { background: #fcfffd; }

    .row-input {
        padding: 8px 12px;
        border: 1.5px solid var(--line);
        border-radius: 10px;
        font-family: inherit;
        font-size: 13px;
        font-weight: 600;
        color: var(--text);
        background: #fafcf8;
        outline: none;
        width: 100%;
        min-width: 80px;
        transition: border-color 0.18s;
    }

    .row-input:focus { border-color: var(--green); }

    .type-select {
        padding: 8px 12px;
        border: 1.5px solid var(--line);
        border-radius: 10px;
        font-family: inherit;
        font-size: 13px;
        font-weight: 600;
        color: var(--text);
        background: #fafcf8;
        outline: none;
        cursor: pointer;
        min-width: 120px;
    }

    .code-tag {
        display: inline-flex;
        align-items: center;
        padding: 5px 10px;
        border-radius: 999px;
        background: var(--green-soft);
        color: var(--green);
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.06em;
    }

    .helper-tag {
        display: inline-flex;
        align-items: center;
        padding: 5px 10px;
        border-radius: 999px;
        background: var(--slate-soft);
        color: var(--slate);
        font-size: 11px;
        font-weight: 700;
        white-space: nowrap;
    }

    .row-order {
        width: 60px;
        padding: 8px 10px;
        border: 1.5px solid var(--line);
        border-radius: 10px;
        font-family: inherit;
        font-size: 13px;
        font-weight: 600;
        color: var(--text);
        background: #fafcf8;
        outline: none;
        text-align: center;
    }

    .del-btn {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        border: 1.5px solid rgba(195, 75, 50, 0.20);
        background: var(--red-soft);
        color: var(--red);
        font-size: 16px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.18s;
        flex-shrink: 0;
    }

    .del-btn:hover { background: var(--red); color: #fff; border-color: var(--red); }

    /* Basic auto row */
    .basic-row td {
        background: #fcfef8;
    }

    .basic-code {
        display: inline-flex;
        align-items: center;
        padding: 5px 10px;
        border-radius: 999px;
        background: #e7f7ef;
        color: #165a3a;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.06em;
    }

    .basic-val {
        font-size: 16px;
        font-weight: 900;
        letter-spacing: -0.04em;
        color: var(--green);
    }

    .basic-note {
        display: block;
        font-size: 10px;
        color: var(--muted);
        font-weight: 600;
        margin-top: 2px;
    }

    /* Save bar */
    .save-bar {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 12px;
        padding: 16px 24px;
        border-top: 1px solid var(--line);
        margin-top: 4px;
    }

    .save-hint {
        font-size: 12px;
        color: var(--muted);
        font-weight: 500;
    }

    .save-btn {
        padding: 12px 28px;
        border-radius: 999px;
        border: none;
        background: var(--green);
        color: #fff;
        font-family: inherit;
        font-size: 14px;
        font-weight: 800;
        cursor: pointer;
        box-shadow: 0 8px 24px rgba(22, 90, 58, 0.22);
        transition: all 0.18s;
    }

    .save-btn:hover { transform: translateY(-2px); box-shadow: 0 12px 32px rgba(22, 90, 58, 0.28); }

    /* Template form extras */
    .template-meta {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
        margin-top: 14px;
    }

    .meta-card {
        padding: 14px 16px;
        border: 1px solid var(--line);
        border-radius: 16px;
        background: #fafcf8;
    }

    .meta-card-label {
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.10em;
        color: var(--muted);
        margin-bottom: 6px;
    }

    .meta-card-val {
        font-size: 18px;
        font-weight: 800;
        letter-spacing: -0.04em;
        color: var(--text);
    }

    /* Summary list table */
    .salary-list-card { padding: 20px 24px; }

    .salary-badge {
        display: inline-flex;
        align-items: center;
        padding: 5px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.06em;
    }

    .salary-badge.active { background: var(--green-soft); color: var(--green); }
    .salary-badge.inactive { background: var(--amber-soft); color: var(--amber); }

    .salary-empty {
        padding: 28px;
        text-align: center;
        font-size: 14px;
        font-weight: 600;
        color: var(--muted);
    }

    .salary-list-table {
        width: 100%;
        border-collapse: collapse;
    }

    .salary-list-table th {
        text-align: left;
        padding: 12px 16px;
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.12em;
        color: var(--muted);
        background: #fbfcfa;
        border-bottom: 1px solid var(--line);
    }

    .salary-list-table td {
        padding: 14px 16px;
        font-size: 14px;
        color: var(--text);
        border-bottom: 1px solid rgba(22, 47, 32, 0.06);
    }

    .salary-list-table tr:last-child td { border-bottom: none; }
    .salary-list-table tbody tr:hover td { background: #fcfffd; }

    .is-hidden { display: none !important; }

    @media (max-width: 768px) {
        .salary-header, .salary-form, .salary-list-card { padding-left: 16px; padding-right: 16px; }
        .salary-title { font-size: 24px; }
        .form-row { flex-direction: column; }
        .field { min-width: 100%; }
        .template-meta { grid-template-columns: 1fr; }
        .total-row { flex-direction: column; gap: 12px; }
        .copy-panel { flex-direction: column; }
        .copy-panel .field { min-width: 100%; }
        .salary-tabs { overflow-x: auto; flex-wrap: nowrap; padding-bottom: 4px; }
        .salary-tabs::-webkit-scrollbar { display: none; }
    }
</style>
@endpush

@section('content')
@php
    $routeBase = auth()->check() && auth()->user()->isHrManager() ? 'hr-manager.settings.hr' : 'admin.hr';
    $templateStoreRoute = route($routeBase . '.salary-structures.store');
    $employeeBreakupRoute = route($routeBase . '.salary-structures.employee-breakup.store');
    $defaultRows = [
        ['code' => 'HRA', 'label' => 'House Rent Allowance', 'calc_type' => 'fixed', 'value' => 5000, 'display_order' => 1],
        ['code' => 'TRAVEL', 'label' => 'Travel Allowance', 'calc_type' => 'fixed', 'value' => 3000, 'display_order' => 2],
        ['code' => 'MEDICAL', 'label' => 'Medical Allowance', 'calc_type' => 'fixed', 'value' => 2000, 'display_order' => 3],
        ['code' => 'SPECIAL', 'label' => 'Special Allowance', 'calc_type' => 'fixed', 'value' => 0, 'display_order' => 4],
        ['code' => 'BONUS', 'label' => 'Bonus / Incentive', 'calc_type' => 'fixed', 'value' => 0, 'display_order' => 5],
    ];
    $templateSourcePayload = $structures->map(fn ($s) => [
        'id' => $s->id, 'name' => $s->name,
        'components' => $s->components->map(fn ($c) => [
            'code' => $c->code, 'label' => $c->label,
            'calc_type' => $c->calc_type, 'value' => (float) $c->value, 'display_order' => (int) $c->display_order,
        ])->values(),
    ])->values();
    $employeeSourcePayload = $latestProfiles->map(fn ($p) => [
        'user_id' => $p->user_id, 'user_name' => optional($p->user)->name,
        'base_salary' => (float) $p->base_salary,
        'effective_from' => optional($p->effective_from)?->toDateString(),
        'components' => collect(optional($p->salaryStructure)->components)->map(fn ($c) => [
            'code' => $c->code, 'label' => $c->label,
            'calc_type' => $c->calc_type, 'value' => (float) $c->value, 'display_order' => (int) $c->display_order,
        ])->values(),
    ])->values();
@endphp

<div class="hr-setup-page salary-workspace">
    @include('attendance._flash')
    @include('admin.hr._workspace_hero', [
        'eyebrow' => 'HR / Payroll',
        'title' => 'Salary Structure',
        'subtitle' => 'Select an employee, enter total salary. Fixed components deduct, Basic auto-calculates. Copy from template or employee if needed.',
        'stats' => [
            ['label' => 'Active Users', 'value' => $users->count(), 'note' => 'Employees available'],
            ['label' => 'Templates', 'value' => $structures->count(), 'note' => 'Salary rule templates'],
            ['label' => 'Employee Breakups', 'value' => $latestProfiles->count(), 'note' => 'Saved salary profiles'],
        ],
    ])
    @include('admin.hr._nav')

    <div class="salary-card">
        <div style="padding: 18px 24px 0;">
            <h2 class="hr-setup-section-title">Salary setup workspace</h2>
            <p class="hr-setup-section-copy">Choose between template rules and employee-wise salary breakup.</p>
        </div>
        <div class="salary-header">
            <div>
                <div class="salary-kicker">HR · Payroll</div>
                <div class="salary-title">Salary Structure</div>
                <div class="salary-subtitle">Select an employee, enter total salary. Fixed components deduct, Basic auto-calculates. Copy from template or employee if needed.</div>
            </div>
        </div>

        <div class="salary-tabs">
            <button type="button" class="salary-tab" data-target="templatePanel">Template Rules</button>
            <button type="button" class="salary-tab active" data-target="employeePanel">Employee Breakup</button>
        </div>

        {{-- TEMPLATE PANEL --}}
        <div id="templatePanel" class="salary-panel">
            <form method="POST" action="{{ $templateStoreRoute }}" class="salary-form" id="templateRulesForm">
                @csrf
                <div class="form-row">
                    <div class="field" style="flex:2 1 300px;">
                        <div class="field-label">Structure Name</div>
                        <input type="text" name="name" class="field-input" placeholder="Senior Manager Template" required>
                    </div>
                    <div class="field" style="flex:0 0 auto;">
                        <div class="field-label">Status</div>
                        <label style="display:flex;align-items:center;gap:8px;padding:10px 14px;border:1.5px solid var(--line);border-radius:12px;background:#fafcf8;cursor:pointer;font-size:14px;font-weight:700;color:var(--text);">
                            <input type="checkbox" name="is_active" value="1" checked> Active
                        </label>
                    </div>
                </div>

                <div class="template-meta">
                    <div class="meta-card">
                        <div class="meta-card-label">Example Total Salary</div>
                        <input type="number" step="0.01" id="templateTotalSalary" class="row-input" style="margin-top:6px;font-size:16px;" placeholder="50000">
                    </div>
                    <div class="meta-card">
                        <div class="meta-card-label">Fixed Components</div>
                        <div class="meta-card-val" id="templateEnteredAmount">Rs 0.00</div>
                    </div>
                    <div class="meta-card">
                        <div class="meta-card-label">Estimated Basic</div>
                        <div class="meta-card-val" id="templateEstimatedBasic">Rs 0.00</div>
                    </div>
                </div>

                <div class="section-head">
                    <div class="section-title">Components</div>
                </div>

                <div class="salary-components">
                    <table>
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Head Name</th>
                                <th>Type</th>
                                <th>Value (₹)</th>
                                <th>Order</th>
                                <th>Note</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($defaultRows as $index => $row)
                                <tr>
                                    <input type="hidden" name="components[{{ $index }}][component_type]" value="earning">
                                    <td><input type="text" name="components[{{ $index }}][code]" value="{{ $row['code'] }}" class="row-input js-template-code" style="min-width:80px;" required></td>
                                    <td><input type="text" name="components[{{ $index }}][label]" value="{{ $row['label'] }}" class="row-input js-template-label" required></td>
                                    <td>
                                        <select name="components[{{ $index }}][calc_type]" class="type-select js-template-calc-type">
                                            <option value="fixed" @selected($row['calc_type'] === 'fixed')>Fixed Amount</option>
                                            <option value="percent_of_base" @selected($row['calc_type'] === 'percent_of_base')>% of Basic</option>
                                        </select>
                                    </td>
                                    <td><input type="number" step="0.01" name="components[{{ $index }}][value]" value="{{ $row['value'] }}" class="row-input js-template-value" style="min-width:100px;" required></td>
                                    <td><input type="number" name="components[{{ $index }}][display_order]" value="{{ $row['display_order'] }}" class="row-order" required></td>
                                    <td><span class="helper-tag js-template-meaning">{{ $row['calc_type'] === 'fixed' ? 'Same every month' : '% of basic' }}</span></td>
                                </tr>
                            @endforeach
                            <tr class="basic-row">
                                <td><span class="basic-code">BASIC</span></td>
                                <td><strong>Basic Salary</strong></td>
                                <td><span class="helper-tag">Auto Calculated</span></td>
                                <td><span class="basic-val" id="templateBasicCell">Rs 0.00</span></td>
                                <td>0</td>
                                <td><span class="basic-note">Total − Fixed components</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="save-bar">
                    <span class="save-hint">Basic auto-calculates from Total − Fixed components</span>
                    <button type="submit" class="save-btn">Save Template</button>
                </div>
            </form>
        </div>

        {{-- EMPLOYEE PANEL --}}
        <div id="employeePanel" class="salary-panel active">
            <form method="POST" action="{{ $employeeBreakupRoute }}" class="salary-form" id="employeeBreakupForm">
                @csrf
                <input type="hidden" name="basic_mode" value="auto">

                {{-- Main form row --}}
                <div class="form-row">
                    <div class="field">
                        <div class="field-label">Employee</div>
                        <select name="user_id" id="employeeUserId" class="field-select" required>
                            <option value="">Select employee</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->role->name ?? 'Role' }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <div class="field-label">Salary Template</div>
                        <select id="salaryTemplateSelect" class="field-select">
                            <option value="">No Template / Manual</option>
                            @foreach($structures as $structure)
                                <option value="{{ $structure->id }}">{{ $structure->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <div class="field-label">Total Salary (₹)</div>
                        <input type="number" step="0.01" name="total_salary" id="employeeTotalSalary" class="field-input" placeholder="50000">
                    </div>
                    <div class="field">
                        <div class="field-label">Effective From</div>
                        <input type="date" name="effective_from" id="employeeEffectiveFrom" class="date-input" value="{{ now()->toDateString() }}">
                    </div>
                </div>

                {{-- Total Summary Card --}}
                <div class="total-card">
                    <div class="total-label">Total Salary Allocation</div>
                    <div class="total-row">
                        <div class="total-big" id="totalDisplay">₹0</div>
                        <div class="total-sub">
                            <span>Fixed: <span id="fixedDisplay">₹0</span></span>
                            <span>Basic: <span id="basicDisplay">₹0</span></span>
                        </div>
                    </div>
                    <div class="progress-wrap">
                        <div class="progress-label">
                            <span>Allocation</span>
                            <span id="progressPct">0%</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" id="progressFill" style="width:0%;"></div>
                        </div>
                    </div>
                </div>

                {{-- Copy Section (collapsed) --}}
                <button type="button" class="copy-toggle" id="copyToggleBtn">
                    📋 Copy from existing template or employee
                </button>
                <div class="copy-panel" id="copyPanel">
                    <div class="field">
                        <div class="field-label">Source Type</div>
                        <select id="copySourceType" class="field-select">
                            <option value="">Select type</option>
                            <option value="template">Template</option>
                            <option value="employee">Employee</option>
                        </select>
                    </div>
                    <div class="field">
                        <div class="field-label">Source Record</div>
                        <select id="copySourceId" class="field-select">
                            <option value="">Select record</option>
                        </select>
                    </div>
                    <button type="button" class="copy-btn" id="applyCopyBtn">Apply Copy</button>
                </div>

                <div class="section-head">
                    <div class="section-title">Salary Components</div>
                </div>

                <div class="salary-components">
                    <table>
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Head Name</th>
                                <th>Type</th>
                                <th>Value (₹)</th>
                                <th>Order</th>
                                <th>Note</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($defaultRows as $index => $row)
                                <tr>
                                    <input type="hidden" name="components[{{ $index }}][component_type]" value="earning">
                                    <td><input type="text" name="components[{{ $index }}][code]" value="{{ $row['code'] }}" class="row-input js-employee-code" style="min-width:80px;" required></td>
                                    <td><input type="text" name="components[{{ $index }}][label]" value="{{ $row['label'] }}" class="row-input js-employee-label" required></td>
                                    <td>
                                        <select name="components[{{ $index }}][calc_type]" class="type-select js-employee-calc-type">
                                            <option value="fixed" @selected($row['calc_type'] === 'fixed')>Fixed Amount</option>
                                            <option value="percent_of_base" @selected($row['calc_type'] === 'percent_of_base')>% of Basic</option>
                                        </select>
                                    </td>
                                    <td><input type="number" step="0.01" name="components[{{ $index }}][value]" value="{{ $row['value'] }}" class="row-input js-employee-value" style="min-width:100px;" required></td>
                                    <td><input type="number" name="components[{{ $index }}][display_order]" value="{{ $row['display_order'] }}" class="row-order js-employee-order" required></td>
                                    <td><span class="helper-tag js-employee-meaning">{{ $row['calc_type'] === 'fixed' ? 'Same every month' : '% of basic' }}</span></td>
                                </tr>
                            @endforeach
                            <tr class="basic-row">
                                <td><span class="basic-code">BASIC</span></td>
                                <td><strong>Basic Salary</strong></td>
                                <td><span class="helper-tag" id="employeeBasicModeText">Auto Calculated</span></td>
                                <td><span class="basic-val" id="employeeBasicCell">Rs 0.00</span></td>
                                <td>0</td>
                                <td><span class="basic-note" id="employeeBasicMeaning">Total − Fixed components</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="save-bar">
                    <span class="save-hint">Basic auto-calculates from Total − Fixed components</span>
                    <button type="submit" class="save-btn">Save Employee Breakup</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Template List --}}
    <div class="salary-card salary-list-card">
        <table class="salary-list-table">
            <thead>
                <tr>
                    <th>Template</th>
                    <th>Components</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            @forelse($structures as $structure)
                <tr>
                    <td><strong>{{ $structure->name }}</strong></td>
                    <td style="color:var(--muted);">{{ $structure->components->pluck('label')->implode(', ') ?: '--' }}</td>
                    <td>
                        <span class="salary-badge {{ $structure->is_active ? 'active' : 'inactive' }}">
                            {{ $structure->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" class="salary-empty">No salary templates yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    /* @php echo json_encode($templateSourcePayload); @endphp */
    const templateSources = {!! json_encode($templateSourcePayload) !!};
    /* @php echo json_encode($employeeSourcePayload); @endphp */
    const employeeSources = {!! json_encode($employeeSourcePayload) !!};
    const manualDefaultRows = {!! json_encode($defaultRows) !!};

    const fmt = (v) => `Rs ${Number(v || 0).toFixed(2)}`;
    const fmtNum = (v) => `₹${Number(v || 0).toLocaleString('en-IN', {minimumFractionDigits: 0, maximumFractionDigits: 0})}`;

    // Tab switching
    document.querySelectorAll('.salary-tab').forEach((tab) => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.salary-tab').forEach(n => n.classList.remove('active'));
            document.querySelectorAll('.salary-panel').forEach(n => n.classList.remove('active'));
            tab.classList.add('active');
            const t = document.getElementById(tab.dataset.target);
            if (t) t.classList.add('active');
        });
    });

    // Copy toggle
    document.getElementById('copyToggleBtn').addEventListener('click', () => {
        document.getElementById('copyPanel').classList.toggle('open');
    });

    // Helper text updater
    const bindMeaning = (selects, meanings) => {
        selects.forEach((sel, i) => {
            sel.addEventListener('change', () => {
                if (meanings[i]) meanings[i].textContent = sel.value === 'fixed' ? 'Same every month' : '% of basic';
            });
        });
    };

    // ── TEMPLATE ──
    const tTotal = document.getElementById('templateTotalSalary');
    const tValues = [...document.querySelectorAll('.js-template-value')];
    const tTypes = [...document.querySelectorAll('.js-template-calc-type')];
    bindMeaning(tTypes, [...document.querySelectorAll('.js-template-meaning')]);

    const updateTemplate = () => {
        const total = Number(tTotal.value || 0);
        const fixed = tValues.reduce((s, inp, i) => {
            return tTypes[i].value === 'fixed' ? s + Number(inp.value || 0) : s;
        }, 0);
        const basic = total - fixed;
        document.getElementById('templateEnteredAmount').textContent = fmt(fixed);
        document.getElementById('templateEstimatedBasic').textContent = fmt(basic);
        const cell = document.getElementById('templateBasicCell');
        cell.textContent = fmt(basic);
        cell.style.color = basic < 0 ? '#c34b32' : '#165a3a';
    };

    [tTotal, ...tValues, ...tTypes].forEach(n => n.addEventListener('input', updateTemplate));
    updateTemplate();

    // ── EMPLOYEE ──
    const eTotal = document.getElementById('employeeTotalSalary');
    const eValues = [...document.querySelectorAll('.js-employee-value')];
    const eTypes = [...document.querySelectorAll('.js-employee-calc-type')];
    const eCodes = [...document.querySelectorAll('.js-employee-code')];
    const eLabels = [...document.querySelectorAll('.js-employee-label')];
    const eOrders = [...document.querySelectorAll('.js-employee-order')];
    const templateSelect = document.getElementById('salaryTemplateSelect');
    bindMeaning(eTypes, [...document.querySelectorAll('.js-employee-meaning')]);

    const updateEmployee = () => {
        const total = Number(eTotal.value || 0);
        const fixed = eValues.reduce((s, inp, i) => {
            return eTypes[i].value === 'fixed' ? s + Number(inp.value || 0) : s;
        }, 0);
        const basic = Math.max(0, total - fixed);
        const pct = total > 0 ? Math.min(100, Math.round((fixed / total) * 100)) : 0;

        document.getElementById('fixedDisplay').textContent = fmtNum(fixed);
        document.getElementById('basicDisplay').textContent = fmtNum(basic);
        document.getElementById('totalDisplay').textContent = fmtNum(total);
        document.getElementById('progressPct').textContent = pct + '%';

        const fill = document.getElementById('progressFill');
        fill.style.width = pct + '%';
        if (pct >= 100) fill.classList.add('full');
        else fill.classList.remove('full');

        const cell = document.getElementById('employeeBasicCell');
        cell.textContent = fmt(basic);
        cell.style.color = basic <= 0 ? '#c34b32' : '#165a3a';
    };

    [eTotal, ...eValues, ...eTypes].forEach(n => n.addEventListener('input', updateEmployee));
    updateEmployee();

    // ── COPY LOGIC ──
    const copyType = document.getElementById('copySourceType');
    const copyId = document.getElementById('copySourceId');
    const applyCopy = document.getElementById('applyCopyBtn');

    const refreshCopy = () => {
        copyId.innerHTML = '<option value="">Select record</option>';
        const items = copyType.value === 'template' ? templateSources
                  : copyType.value === 'employee' ? employeeSources : [];
        items.forEach(item => {
            const o = document.createElement('option');
            o.value = copyType.value === 'template' ? item.id : item.user_id;
            o.textContent = copyType.value === 'template' ? item.name : item.user_name;
            copyId.appendChild(o);
        });
    };

    const applyRows = (comps) => {
        eCodes.forEach((inp, i) => {
            const r = comps[i] || manualDefaultRows[i] || {};
            inp.value = r.code || '';
            eLabels[i].value = r.label || '';
            eTypes[i].value = r.calc_type || 'fixed';
            eValues[i].value = r.value ?? 0;
            if (eOrders[i]) eOrders[i].value = r.display_order ?? (i + 1);
        });
        eTypes.forEach(s => s.dispatchEvent(new Event('change')));
    };

    const sumFixed = (comps) => (comps || []).reduce((s, c) => c.calc_type === 'fixed' ? s + Number(c.value || 0) : s, 0);

    templateSelect.addEventListener('change', () => {
        if (!templateSelect.value) {
            applyRows(manualDefaultRows);
            updateEmployee();
            return;
        }

        const selected = templateSources.find(x => String(x.id) === String(templateSelect.value));
        if (!selected) return;
        applyRows(selected.components || []);
        updateEmployee();
    });

    copyType.addEventListener('change', refreshCopy);

    applyCopy.addEventListener('click', () => {
        const type = copyType.value, id = copyId.value;
        if (!type || !id) return;

        if (type === 'template') {
            const t = templateSources.find(x => String(x.id) === String(id));
            if (!t) return;
            applyRows(t.components || []);
            templateSelect.value = t.id || '';
            eTotal.value = sumFixed(t.components) || '';
        } else {
            const p = employeeSources.find(x => String(x.user_id) === String(id));
            if (!p) return;
            applyRows(p.components || []);
            templateSelect.value = '';
            document.getElementById('employeeUserId').value = p.user_id || '';
            eTotal.value = (Number(p.base_salary || 0) + sumFixed(p.components || [])) || '';
            if (p.effective_from) document.getElementById('employeeEffectiveFrom').value = p.effective_from;
        }

        updateEmployee();
    });
})();
</script>
@endpush

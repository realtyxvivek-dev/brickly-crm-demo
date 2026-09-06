@extends('layouts.app')

@section('title', 'Employee Record')
@section('page-title', 'Employee Record')

@push('styles')
<style>
    .employee-record-page {
        --surface: #ffffff;
        --surface-soft: #f8fbf8;
        --line: rgba(22, 47, 32, 0.10);
        --line-strong: rgba(22, 47, 32, 0.16);
        --text: #153223;
        --muted: #6e7d72;
        --green: #165a3a;
        --green-soft: #edf8f1;
        --amber: #b26d0d;
        --amber-soft: #fff6e6;
        --red: #c25137;
        --red-soft: #ffefea;
        --violet: #6a56d2;
        --violet-soft: #f3efff;
        --slate: #4f6473;
        --slate-soft: #f1f5f8;
        --shadow: 0 18px 42px rgba(21, 50, 35, 0.08);
        display: flex;
        flex-direction: column;
        gap: 14px;
        color: var(--text);
    }

    .employee-record-shell {
        background: var(--surface);
        border: 1px solid var(--line);
        border-radius: 20px;
        box-shadow: var(--shadow);
        overflow: hidden;
    }

    .employee-record-hero {
        padding: 24px 28px;
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 16px;
        background:
            radial-gradient(circle at top right, rgba(22, 90, 58, 0.08), transparent 34%),
            linear-gradient(180deg, #fcfdfc 0%, #ffffff 100%);
    }

    .employee-record-kicker {
        font-size: 11px;
        font-weight: 900;
        letter-spacing: 0.18em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .employee-record-title {
        margin-top: 8px;
        font-size: 34px;
        line-height: 1.05;
        letter-spacing: 0;
        font-weight: 900;
        color: var(--text);
    }

    .employee-record-subtitle {
        margin-top: 10px;
        font-size: 15px;
        line-height: 1.55;
        color: var(--muted);
        font-weight: 600;
        max-width: 920px;
    }

    .employee-record-inline-meta {
        margin-top: 12px;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        font-size: 13px;
        color: var(--muted);
        font-weight: 700;
    }

    .employee-record-inline-meta span::after {
        content: "";
        margin-left: 10px;
    }

    .employee-record-inline-meta span:last-child::after {
        content: none;
        margin: 0;
    }

    .employee-record-pills {
        margin-top: 14px;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }

    .employee-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 30px;
        padding: 0 12px;
        border-radius: 999px;
        border: 1px solid transparent;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: 0.01em;
        text-transform: capitalize;
    }

    .employee-pill.green { background: var(--green-soft); color: var(--green); }
    .employee-pill.amber { background: var(--amber-soft); color: var(--amber); }
    .employee-pill.red { background: var(--red-soft); color: var(--red); }
    .employee-pill.violet { background: var(--violet-soft); color: var(--violet); }
    .employee-pill.slate { background: var(--slate-soft); color: var(--slate); }

    .employee-record-side {
        display: flex;
        flex-direction: column;
        gap: 14px;
    }

    .employee-record-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        flex-wrap: wrap;
    }

    .employee-btn {
        min-height: 42px;
        padding: 0 16px;
        border-radius: 12px;
        font-size: 13px;
        font-weight: 800;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        cursor: pointer;
        border: none;
    }

    .employee-btn-primary {
        background: var(--green);
        color: #fff;
    }

    .employee-btn-secondary {
        background: #fff;
        color: var(--text);
        border: 1px solid var(--line-strong);
    }

    .employee-record-highlight {
        border: 1px solid var(--line);
        border-radius: 20px;
        background: rgba(255, 255, 255, 0.92);
        padding: 18px;
        display: flex;
        flex-direction: column;
        gap: 14px;
    }

    .employee-record-setup-alert {
        margin: 0 28px 18px;
        padding: 14px 16px;
        border-radius: 16px;
        border: 1px solid var(--line);
        background: #fffdf7;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        flex-wrap: wrap;
    }

    .employee-record-setup-title {
        font-size: 13px;
        font-weight: 900;
        color: var(--text);
    }

    .employee-record-setup-list {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .employee-highlight-label {
        font-size: 10px;
        font-weight: 900;
        letter-spacing: 0.16em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .employee-highlight-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }

    .employee-highlight-block {
        border-radius: 16px;
        padding: 14px;
        background: var(--surface-soft);
        border: 1px solid var(--line);
    }

    .employee-highlight-value {
        font-size: 26px;
        font-weight: 900;
        letter-spacing: -0.04em;
        color: var(--text);
        line-height: 1;
    }

    .employee-highlight-note {
        margin-top: 8px;
        font-size: 12px;
        color: var(--muted);
        font-weight: 700;
        line-height: 1.5;
    }

    .employee-summary-strip {
        padding: 0 28px 18px;
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        border-top: 1px solid var(--line);
    }

    .employee-summary-card {
        margin-top: 18px;
        border: 1px solid var(--line);
        border-radius: 16px;
        background: #fff;
        padding: 16px;
    }

    .employee-summary-label {
        font-size: 10px;
        font-weight: 900;
        letter-spacing: 0.16em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .employee-summary-value {
        margin-top: 10px;
        font-size: 24px;
        font-weight: 900;
        letter-spacing: -0.05em;
        line-height: 1;
        color: var(--text);
    }

    .employee-summary-note {
        margin-top: 8px;
        font-size: 12px;
        line-height: 1.5;
        color: var(--muted);
        font-weight: 700;
    }

    .employee-record-tabs {
        padding: 0 28px 18px;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }

    .employee-tab {
        padding: 9px 14px;
        border-radius: 999px;
        border: 1px solid var(--line);
        background: #fff;
        color: var(--muted);
        text-decoration: none;
        font-size: 13px;
        font-weight: 800;
    }

    .employee-tab:hover,
    .employee-tab.active {
        background: var(--green);
        border-color: var(--green);
        color: #fff;
    }

    .employee-section {
        padding: 24px 28px;
        border-top: 1px solid var(--line);
    }

    .employee-section-head {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 18px;
    }

    .employee-section-title {
        font-size: 24px;
        letter-spacing: 0;
        font-weight: 900;
        letter-spacing: -0.05em;
        color: var(--text);
        line-height: 1;
    }

    .employee-section-copy {
        margin-top: 6px;
        color: var(--muted);
        font-size: 13px;
        line-height: 1.6;
        font-weight: 700;
    }

    .employee-panel-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
    }

    .employee-panel {
        border: 1px solid var(--line);
        border-radius: 18px;
        background: #fff;
        overflow: hidden;
    }

    .employee-panel-head {
        padding: 16px 18px;
        border-bottom: 1px solid var(--line);
        background: linear-gradient(180deg, #fcfdfc 0%, #f8fbf8 100%);
    }

    .employee-panel-title {
        font-size: 14px;
        font-weight: 900;
        letter-spacing: 0.02em;
        color: var(--text);
    }

    .employee-panel-subtitle {
        margin-top: 5px;
        font-size: 12px;
        color: var(--muted);
        line-height: 1.5;
        font-weight: 700;
    }

    .employee-share-panel {
        padding: 16px 18px 18px;
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 14px;
        align-items: end;
    }

    .employee-share-url {
        width: 100%;
        min-height: 42px;
        border: 1px solid var(--line);
        border-radius: 12px;
        padding: 0 12px;
        color: var(--text);
        background: #f8fafc;
        font-size: 13px;
        font-weight: 700;
    }

    .employee-share-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .employee-data-list {
        padding: 8px 18px 14px;
        display: flex;
        flex-direction: column;
    }

    .employee-data-row {
        display: grid;
        grid-template-columns: 180px minmax(0, 1fr);
        gap: 18px;
        padding: 14px 0;
        border-bottom: 1px solid rgba(22, 47, 32, 0.08);
        align-items: start;
    }

    .employee-data-row:last-child {
        border-bottom: none;
        padding-bottom: 4px;
    }

    .employee-data-label {
        font-size: 11px;
        font-weight: 900;
        letter-spacing: 0.16em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .employee-data-value {
        font-size: 15px;
        font-weight: 800;
        line-height: 1.55;
        color: var(--text);
        word-break: break-word;
    }

    .employee-data-value.muted {
        color: var(--muted);
        font-weight: 700;
    }

    .employee-split-workspace {
        display: grid;
        grid-template-columns: minmax(0, 1.35fr) minmax(340px, 0.85fr);
        gap: 16px;
        align-items: start;
    }

    .employee-table-wrap {
        overflow-x: auto;
    }

    .employee-table {
        width: 100%;
        min-width: 760px;
        border-collapse: collapse;
    }

    .employee-table th,
    .employee-table td {
        padding: 14px 16px;
        border-bottom: 1px solid rgba(22, 47, 32, 0.08);
        text-align: left;
        vertical-align: top;
    }

    .employee-table th {
        background: #fbfcfa;
        font-size: 10px;
        font-weight: 900;
        letter-spacing: 0.16em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .employee-table tr:last-child td {
        border-bottom: none;
    }

    .employee-empty {
        padding: 18px;
        border: 1px dashed var(--line-strong);
        border-radius: 16px;
        background: #fcfdfc;
        color: var(--muted);
        font-size: 13px;
        line-height: 1.6;
        font-weight: 700;
    }

    .employee-form-card {
        border: 1px solid var(--line);
        border-radius: 22px;
        background: #fff;
        overflow: hidden;
    }

    .employee-form-head {
        padding: 16px 18px;
        border-bottom: 1px solid var(--line);
        background: linear-gradient(180deg, #fcfdfc 0%, #f8fbf8 100%);
    }

    .employee-form-title {
        font-size: 14px;
        font-weight: 900;
        color: var(--text);
    }

    .employee-form-copy {
        margin-top: 5px;
        font-size: 12px;
        line-height: 1.5;
        color: var(--muted);
        font-weight: 700;
    }

    .employee-form-body {
        padding: 18px;
        display: flex;
        flex-direction: column;
        gap: 14px;
    }

    .employee-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }

    .employee-field {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .employee-field.full {
        grid-column: 1 / -1;
    }

    .employee-label {
        font-size: 10px;
        font-weight: 900;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .employee-input,
    .employee-select,
    .employee-textarea {
        width: 100%;
        min-height: 44px;
        padding: 10px 12px;
        border: 1.5px solid var(--line);
        border-radius: 14px;
        background: #fbfcfa;
        color: var(--text);
        font-size: 13px;
        font-weight: 700;
        outline: none;
    }

    .employee-textarea {
        min-height: 96px;
        resize: vertical;
    }

    .employee-form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        flex-wrap: wrap;
    }

    .employee-status-stack {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .employee-status-card {
        border: 1px solid var(--line);
        border-radius: 18px;
        padding: 16px;
        background: #fff;
    }

    .employee-status-top {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: center;
        flex-wrap: wrap;
    }

    .employee-status-name {
        font-size: 15px;
        font-weight: 800;
        color: var(--text);
    }

    .employee-status-meta {
        margin-top: 10px;
        font-size: 13px;
        line-height: 1.6;
        color: var(--muted);
        font-weight: 700;
    }

    .employee-timeline {
        display: grid;
        gap: 12px;
    }

    .employee-timeline-item {
        border: 1px solid var(--line);
        border-radius: 20px;
        background: #fff;
        padding: 16px 18px;
    }

    .employee-timeline-top {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        align-items: flex-start;
    }

    .employee-timeline-title {
        font-size: 15px;
        font-weight: 800;
        color: var(--text);
    }

    .employee-timeline-date {
        font-size: 12px;
        color: var(--muted);
        font-weight: 800;
    }

    .employee-timeline-copy {
        margin-top: 8px;
        font-size: 13px;
        line-height: 1.6;
        color: var(--muted);
        font-weight: 700;
    }

    @media (max-width: 1260px) {
        .employee-summary-strip {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .employee-panel-grid,
        .employee-split-workspace {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 880px) {
        .employee-record-hero {
            grid-template-columns: 1fr;
        }

        .employee-data-row {
            grid-template-columns: 1fr;
            gap: 8px;
        }
    }

    @media (max-width: 640px) {
        .employee-record-hero,
        .employee-summary-strip,
        .employee-record-setup-alert,
        .employee-record-tabs,
        .employee-section {
            padding-left: 18px;
            padding-right: 18px;
        }

        .employee-record-setup-alert {
            margin-left: 18px;
            margin-right: 18px;
        }

        .employee-record-hero {
            padding-top: 20px;
            padding-bottom: 20px;
        }

        .employee-summary-strip {
            padding-top: 0;
            padding-bottom: 18px;
            grid-template-columns: 1fr;
        }

        .employee-record-tabs {
            padding-bottom: 18px;
        }

        .employee-record-title {
            font-size: 32px;
        }

        .employee-form-grid,
        .employee-highlight-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@section('content')
@php
    $hrRouteBase = auth()->check() && auth()->user()->isHrManager() ? 'hr-manager.settings.hr' : 'admin.hr';
    $profile = $employee->employeeProfile;
    $attendanceProfile = $employee->attendanceProfile;
    $salaryProfile = $employee->salaryProfile;
    $exitWorkflow = $profile->exitWorkflow;
    $missingDocs = $profile->missingDocumentTypes();
    $openAssets = $profile->assets->where('status', \App\Models\EmployeeAsset::STATUS_ISSUED)->count();
    $salaryRevisions = $profile->salaryRevisions;
    $recentIncentives = $incentiveSummary['recent'];
    $statusClass = match($profile->employment_status) {
        'active' => 'green',
        'on_notice' => 'amber',
        'resigned', 'terminated' => 'red',
        'long_leave' => 'violet',
        default => 'slate',
    };
    $setupIssues = [];
    if (! $profile->employee_code) {
        $setupIssues[] = 'Employee ID missing';
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
    if (count($missingDocs) > 0) {
        $setupIssues[] = count($missingDocs) . ' documents missing';
    }
    $salaryStructureLabel = function ($structure, string $fallback = 'Not linked') {
        if (! $structure) {
            return $fallback;
        }

        return str_starts_with((string) $structure->name, '__employee__:')
            ? 'Custom breakup'
            : $structure->name;
    };
    $detailLink = $profile->latestDetailLink;
    $detailLinkUsable = $detailLink?->isUsable() ?? false;
    $detailLinkExpired = $detailLink?->expires_at && $detailLink->expires_at->isPast();
    $detailLinkStatusLabel = $detailLink
        ? ($detailLinkUsable ? ($detailLink->submitted_at ? 'Submitted' : 'Active') : ($detailLinkExpired ? 'Expired' : ucfirst($detailLink->status)))
        : 'Not generated';
@endphp

<div class="employee-record-page">
    @include('attendance._flash')
    @include('admin.hr._nav')

    <section class="employee-record-shell">
        <div class="employee-record-hero">
            <div>
                <div class="employee-record-kicker">Employee Master</div>
                <div class="employee-record-title">{{ $employee->name }}</div>
                <div class="employee-record-subtitle">
                    Employee details, attendance setup, salary, documents, and HR history in one place.
                </div>
                <div class="employee-record-inline-meta">
                    <span>Employee ID {{ $profile->employee_code }}</span>
                    <span>{{ $employee->role->name ?? 'Role not set' }}</span>
                    <span>{{ $profile->department?->name ?? 'Department not set' }}</span>
                    <span>{{ $profile->designation?->name ?? 'Designation not set' }}</span>
                    <span>{{ $employee->phone ?: 'Phone missing' }}</span>
                </div>
                <div class="employee-record-pills">
                    <span class="employee-pill {{ $statusClass }}">{{ str_replace('_', ' ', $profile->employment_status) }}</span>
                    <span class="employee-pill {{ $employee->is_active ? 'green' : 'red' }}">{{ $employee->is_active ? 'Login enabled' : 'Login disabled' }}</span>
                    <span class="employee-pill {{ count($missingDocs) ? 'red' : 'green' }}">
                        {{ count($missingDocs) ? count($missingDocs) . ' documents missing' : 'Documents complete' }}
                    </span>
                </div>
            </div>

            <div class="employee-record-side">
                <div class="employee-record-actions">
                    <a href="{{ route($hrRouteBase . '.employees.edit', $employee) }}" class="employee-btn employee-btn-primary">Edit Employee</a>
                    <a href="{{ route('users.show', $employee) }}" class="employee-btn employee-btn-secondary">Open User</a>
                </div>
            </div>
        </div>

        <div class="employee-record-setup-alert">
            <div>
                <div class="employee-record-setup-title">{{ count($setupIssues) ? 'Setup needs attention' : 'Setup complete' }}</div>
                <div class="employee-highlight-note">
                    {{ $employee->manager?->name ? 'Reports to ' . $employee->manager->name : 'Reporting manager not assigned yet' }}
                </div>
            </div>
            <div class="employee-record-setup-list">
                @forelse($setupIssues as $issue)
                    <span class="employee-pill amber">{{ $issue }}</span>
                @empty
                    <span class="employee-pill green">Everything is ready</span>
                @endforelse
            </div>
        </div>

        <div class="employee-summary-strip">
            <div class="employee-summary-card">
                <div class="employee-summary-label">Attendance</div>
                <div class="employee-summary-value">{{ $attendanceProfile?->officeLocation?->name ? 'On' : 'Off' }}</div>
                <div class="employee-summary-note">{{ $attendanceProfile?->officeLocation?->name ?? 'Office not linked' }}</div>
            </div>
            <div class="employee-summary-card">
                <div class="employee-summary-label">Salary</div>
                <div class="employee-summary-value">{{ $salaryProfile ? 'Rs ' . number_format($salaryTotal, 0) : '--' }}</div>
                <div class="employee-summary-note">{{ $salaryProfile ? 'Base Rs ' . number_format((float) $salaryProfile->base_salary, 0) : 'Salary not linked' }}</div>
            </div>
            <div class="employee-summary-card">
                <div class="employee-summary-label">Documents</div>
                <div class="employee-summary-value">{{ count($missingDocs) ? count($missingDocs) : 'OK' }}</div>
                <div class="employee-summary-note">{{ count($missingDocs) ? 'Documents missing' : 'Documents complete' }}</div>
            </div>
            <div class="employee-summary-card">
                <div class="employee-summary-label">Assets Issued</div>
                <div class="employee-summary-value">{{ $openAssets }}</div>
                <div class="employee-summary-note">Open inventory assignments</div>
            </div>
        </div>

        <div class="employee-panel" style="margin-bottom:16px;">
            <div class="employee-panel-head">
                <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start; flex-wrap:wrap;">
                    <div>
                        <div class="employee-panel-title">Employee Self Detail Form</div>
                        <div class="employee-panel-subtitle">HR shareable link se employee apni KYC, bank, address aur documents details fill kar sakta hai.</div>
                    </div>
                    <span class="employee-pill {{ $detailLinkUsable ? ($detailLink?->submitted_at ? 'green' : 'amber') : 'slate' }}">
                        {{ $detailLinkStatusLabel }}
                    </span>
                </div>
            </div>

            <div class="employee-share-panel">
                <div>
                    <div class="employee-data-label">Shareable Link</div>
                    <input class="employee-share-url" type="text" readonly value="{{ $detailLinkUsable ? $detailLink->publicUrl() : 'Generate a fresh link to share with employee.' }}">
                    <div class="employee-panel-subtitle" style="margin-top:6px;">
                        @if($detailLinkUsable)
                            Expires {{ $detailLink->expires_at?->format('d M Y, h:i A') }}{{ $detailLink->last_opened_at ? ' - Last opened ' . $detailLink->last_opened_at->format('d M, h:i A') : '' }}
                        @else
                            Default expiry 7 days. Old links are revoked when a fresh link is generated.
                        @endif
                    </div>
                </div>
                <div class="employee-share-actions">
                    @if($detailLinkUsable)
                        <button type="button" class="employee-btn employee-btn-secondary" data-copy-url="{{ $detailLink->publicUrl() }}">Copy Link</button>
                        <form method="POST" action="{{ route($hrRouteBase . '.employees.detail-link.revoke', [$employee, $detailLink]) }}">
                            @csrf
                            <button type="submit" class="employee-btn employee-btn-secondary">Revoke</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route($hrRouteBase . '.employees.detail-link.generate', $employee) }}">
                        @csrf
                        <button type="submit" class="employee-btn employee-btn-primary">{{ $detailLinkUsable ? 'Regenerate' : 'Generate Link' }}</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="employee-record-tabs">
            <a href="#profile" class="employee-tab active">Profile</a>
            <a href="#job" class="employee-tab">Job</a>
            <a href="#attendance" class="employee-tab">Attendance</a>
            <a href="#salary" class="employee-tab">Salary</a>
            <a href="#documents" class="employee-tab">Documents</a>
            <a href="#assets" class="employee-tab">Assets</a>
            <a href="#exit" class="employee-tab">Exit</a>
            <a href="#timeline" class="employee-tab">History</a>
        </div>

        <div id="profile" class="employee-section">
            <div class="employee-section-head">
                <div>
                    <div class="employee-section-title">Profile</div>
                    <div class="employee-section-copy">Core identity, contact details, employee code, and address information in a cleaner HR record format.</div>
                </div>
            </div>

            <div class="employee-panel-grid">
                <div class="employee-panel">
                    <div class="employee-panel-head">
                        <div class="employee-panel-title">Identity</div>
                        <div class="employee-panel-subtitle">Primary employee and account details.</div>
                    </div>
                    <div class="employee-data-list">
                        <div class="employee-data-row"><div class="employee-data-label">Full Name</div><div class="employee-data-value">{{ $employee->name }}</div></div>
                        <div class="employee-data-row"><div class="employee-data-label">Employee ID</div><div class="employee-data-value">{{ $profile->employee_code }}</div></div>
                        <div class="employee-data-row"><div class="employee-data-label">Date of Birth</div><div class="employee-data-value {{ $profile->date_of_birth ? '' : 'muted' }}">{{ $profile->date_of_birth?->format('d M Y') ?? 'Not provided' }}</div></div>
                        <div class="employee-data-row"><div class="employee-data-label">Email</div><div class="employee-data-value">{{ $employee->email }}</div></div>
                        <div class="employee-data-row"><div class="employee-data-label">Phone</div><div class="employee-data-value">{{ $employee->phone ?: 'Not provided' }}</div></div>
                    </div>
                </div>

                <div class="employee-panel">
                    <div class="employee-panel-head">
                        <div class="employee-panel-title">Contact & Address</div>
                        <div class="employee-panel-subtitle">Emergency contact and residential details.</div>
                    </div>
                    <div class="employee-data-list">
                        <div class="employee-data-row"><div class="employee-data-label">Emergency Contact</div><div class="employee-data-value {{ $profile->emergency_contact_name ? '' : 'muted' }}">{{ $profile->emergency_contact_name ?: 'Not provided' }}</div></div>
                        <div class="employee-data-row"><div class="employee-data-label">Emergency Phone</div><div class="employee-data-value {{ $profile->emergency_contact_phone ? '' : 'muted' }}">{{ $profile->emergency_contact_phone ?: 'Not provided' }}</div></div>
                        <div class="employee-data-row"><div class="employee-data-label">Current Address</div><div class="employee-data-value {{ $profile->current_address ? '' : 'muted' }}">{{ $profile->current_address ?: 'Not provided' }}</div></div>
                        <div class="employee-data-row"><div class="employee-data-label">Permanent Address</div><div class="employee-data-value {{ $profile->permanent_address ? '' : 'muted' }}">{{ $profile->permanent_address ?: 'Not provided' }}</div></div>
                    </div>
                </div>
            </div>
        </div>

        <div id="job" class="employee-section">
            <div class="employee-section-head">
                <div>
                    <div class="employee-section-title">Job</div>
                    <div class="employee-section-copy">Lifecycle, reporting line, department, designation, and probation details separated from auth role.</div>
                </div>
            </div>

            <div class="employee-panel-grid">
                <div class="employee-panel">
                    <div class="employee-panel-head">
                        <div class="employee-panel-title">Organization</div>
                        <div class="employee-panel-subtitle">Role, department, designation, and reporting setup.</div>
                    </div>
                    <div class="employee-data-list">
                        <div class="employee-data-row"><div class="employee-data-label">Role</div><div class="employee-data-value">{{ $employee->role->name ?? 'Not set' }}</div></div>
                        <div class="employee-data-row"><div class="employee-data-label">Department</div><div class="employee-data-value">{{ $profile->department?->name ?? 'Not set' }}</div></div>
                        <div class="employee-data-row"><div class="employee-data-label">Designation</div><div class="employee-data-value">{{ $profile->designation?->name ?? 'Not set' }}</div></div>
                        <div class="employee-data-row"><div class="employee-data-label">Reporting Manager</div><div class="employee-data-value">{{ $employee->manager?->name ?? 'Not assigned' }}</div></div>
                    </div>
                </div>

                <div class="employee-panel">
                    <div class="employee-panel-head">
                        <div class="employee-panel-title">Lifecycle</div>
                        <div class="employee-panel-subtitle">Joining, status, probation, and salary reminder day.</div>
                    </div>
                    <div class="employee-data-list">
                        <div class="employee-data-row"><div class="employee-data-label">Joining Date</div><div class="employee-data-value">{{ $profile->joining_date?->format('d M Y') ?? 'Not set' }}</div></div>
                        <div class="employee-data-row"><div class="employee-data-label">Employment Status</div><div class="employee-data-value">{{ str_replace('_', ' ', $profile->employment_status) }}</div></div>
                        <div class="employee-data-row"><div class="employee-data-label">Probation End</div><div class="employee-data-value {{ $profile->probation_end_date ? '' : 'muted' }}">{{ $profile->probation_end_date?->format('d M Y') ?? 'Not set' }}</div></div>
                        <div class="employee-data-row"><div class="employee-data-label">Salary Reminder Day</div><div class="employee-data-value">{{ $profile->salary_day_of_month ?: 'Not set' }}</div></div>
                    </div>
                </div>
            </div>
        </div>

        <div id="bank" class="employee-section">
            <div class="employee-section-head">
                <div>
                    <div class="employee-section-title">Bank & KYC</div>
                    <div class="employee-section-copy">Payroll payout details and compliance records in a more formal HR panel layout.</div>
                </div>
            </div>

            <div class="employee-panel-grid">
                <div class="employee-panel">
                    <div class="employee-panel-head">
                        <div class="employee-panel-title">Banking</div>
                        <div class="employee-panel-subtitle">Salary transfer information.</div>
                    </div>
                    <div class="employee-data-list">
                        <div class="employee-data-row"><div class="employee-data-label">Account Holder</div><div class="employee-data-value">{{ $profile->account_holder_name }}</div></div>
                        <div class="employee-data-row"><div class="employee-data-label">Bank Account</div><div class="employee-data-value">{{ $profile->bank_account_number }}</div></div>
                        <div class="employee-data-row"><div class="employee-data-label">IFSC</div><div class="employee-data-value">{{ $profile->ifsc_code }}</div></div>
                    </div>
                </div>

                <div class="employee-panel">
                    <div class="employee-panel-head">
                        <div class="employee-panel-title">Compliance</div>
                        <div class="employee-panel-subtitle">KYC identifiers and internal HR note.</div>
                    </div>
                    <div class="employee-data-list">
                        <div class="employee-data-row"><div class="employee-data-label">PAN</div><div class="employee-data-value">{{ $profile->pan_number }}</div></div>
                        <div class="employee-data-row"><div class="employee-data-label">Aadhaar</div><div class="employee-data-value">{{ $profile->aadhaar_number }}</div></div>
                        <div class="employee-data-row"><div class="employee-data-label">HR Notes</div><div class="employee-data-value {{ $profile->notes ? '' : 'muted' }}">{{ $profile->notes ?: 'No private HR notes yet.' }}</div></div>
                    </div>
                </div>
            </div>
        </div>

        <div id="attendance" class="employee-section">
            <div class="employee-section-head">
                <div>
                    <div class="employee-section-title">Attendance</div>
                    <div class="employee-section-copy">Current office and policy mapping from the existing attendance engine.</div>
                </div>
            </div>

            <div class="employee-panel-grid">
                <div class="employee-panel">
                    <div class="employee-panel-head">
                        <div class="employee-panel-title">Mapping</div>
                        <div class="employee-panel-subtitle">Attendance enablement and employee code sync.</div>
                    </div>
                    <div class="employee-data-list">
                        <div class="employee-data-row"><div class="employee-data-label">Attendance Enabled</div><div class="employee-data-value">{{ $attendanceProfile?->attendance_enabled ? 'Yes' : 'No / not mapped' }}</div></div>
                        <div class="employee-data-row"><div class="employee-data-label">Attendance Code</div><div class="employee-data-value">{{ $attendanceProfile?->employee_code ?? $profile->employee_code }}</div></div>
                        <div class="employee-data-row"><div class="employee-data-label">Effective From</div><div class="employee-data-value {{ $attendanceProfile?->effective_from ? '' : 'muted' }}">{{ $attendanceProfile?->effective_from?->format('d M Y') ?? 'Not set' }}</div></div>
                    </div>
                </div>

                <div class="employee-panel">
                    <div class="employee-panel-head">
                        <div class="employee-panel-title">Assignment</div>
                        <div class="employee-panel-subtitle">Office and attendance rule currently linked.</div>
                    </div>
                    <div class="employee-data-list">
                        <div class="employee-data-row"><div class="employee-data-label">Office</div><div class="employee-data-value">{{ $attendanceProfile?->officeLocation?->name ?? 'Not linked' }}</div></div>
                        <div class="employee-data-row"><div class="employee-data-label">Policy</div><div class="employee-data-value">{{ $attendanceProfile?->attendancePolicy?->name ?? 'Not linked' }}</div></div>
                        <div class="employee-data-row"><div class="employee-data-label">Base Salary Mode</div><div class="employee-data-value {{ $attendanceProfile?->salary_mode ? '' : 'muted' }}">{{ $attendanceProfile?->salary_mode ?? 'Not set' }}</div></div>
                    </div>
                </div>
            </div>
        </div>

        <div id="salary" class="employee-section">
            <div class="employee-section-head">
                <div>
                    <div class="employee-section-title">Salary</div>
                    <div class="employee-section-copy">Current salary profile, revision history, and direct salary change logging without losing old data.</div>
                </div>
            </div>

            <div class="employee-split-workspace">
                <div class="employee-panel">
                    <div class="employee-panel-head">
                        <div class="employee-panel-title">Current Salary Profile</div>
                        <div class="employee-panel-subtitle">Base and effective information.</div>
                    </div>
                    <div class="employee-data-list">
                        <div class="employee-data-row"><div class="employee-data-label">Structure</div><div class="employee-data-value">{{ $salaryStructureLabel($salaryProfile?->salaryStructure) }}</div></div>
                        <div class="employee-data-row"><div class="employee-data-label">Base Salary</div><div class="employee-data-value">{{ $salaryProfile ? 'Rs ' . number_format((float) $salaryProfile->base_salary, 2) : 'Not set' }}</div></div>
                        <div class="employee-data-row"><div class="employee-data-label">Effective From</div><div class="employee-data-value {{ $salaryProfile?->effective_from ? '' : 'muted' }}">{{ $salaryProfile?->effective_from?->format('d M Y') ?? 'Not set' }}</div></div>
                    </div>
                </div>

                <div class="employee-panel">
                    <div class="employee-panel-head">
                        <div class="employee-panel-title">Payroll View</div>
                        <div class="employee-panel-subtitle">Total estimate and current setup state.</div>
                    </div>
                    <div class="employee-data-list">
                        <div class="employee-data-row"><div class="employee-data-label">Total Salary</div><div class="employee-data-value">{{ $salaryProfile ? 'Rs ' . number_format($salaryTotal, 2) : 'Not set' }}</div></div>
                        <div class="employee-data-row"><div class="employee-data-label">Profile Status</div><div class="employee-data-value">{{ $salaryProfile ? 'Linked and active' : 'No active salary profile' }}</div></div>
                        <div class="employee-data-row"><div class="employee-data-label">Payslip Readiness</div><div class="employee-data-value">{{ $salaryProfile ? 'Ready for payroll cycle' : 'Salary must be linked first' }}</div></div>
                    </div>
                </div>

                <div class="employee-form-card">
                    <div class="employee-form-head">
                        <div class="employee-form-title">Add Salary Revision</div>
                        <div class="employee-form-copy">Every salary change is logged with old and new values, effective date, and reason.</div>
                    </div>
                    <form method="POST" action="{{ route($hrRouteBase . '.employees.salary-revisions.store', $employee) }}" class="employee-form-body">
                        @csrf
                        <div class="employee-form-grid">
                            <div class="employee-field">
                                <label class="employee-label">Salary Rule</label>
                                <select name="salary_structure_id" class="employee-select">
                                    <option value="">Keep current / custom</option>
                                    @foreach($salaryStructures as $structure)
                                        <option value="{{ $structure->id }}" @selected($salaryProfile?->salary_structure_id === $structure->id)>{{ $salaryStructureLabel($structure, 'Current salary rule') }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="employee-field"><label class="employee-label">New Base Salary</label><input type="number" step="0.01" min="0" name="new_base_salary" class="employee-input" value="{{ $salaryProfile?->base_salary }}"></div>
                            <div class="employee-field"><label class="employee-label">Effective From</label><input type="date" name="effective_from" class="employee-input" value="{{ now()->format('Y-m-d') }}"></div>
                            <div class="employee-field"><label class="employee-label">Reason</label><input type="text" name="reason" class="employee-input" placeholder="Annual revision / promotion"></div>
                            <div class="employee-field full"><label class="employee-label">Notes</label><textarea name="notes" class="employee-textarea" rows="3" placeholder="Optional notes for HR and payroll"></textarea></div>
                        </div>
                        <div class="employee-form-actions">
                            <button type="submit" class="employee-btn employee-btn-primary">Save Revision</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="employee-panel" style="margin-top: 16px;">
                <div class="employee-panel-head">
                    <div class="employee-panel-title">Revision History</div>
                    <div class="employee-panel-subtitle">Old vs new salary snapshots with actor and effective date.</div>
                </div>
                <div class="employee-table-wrap">
                    <table class="employee-table">
                        <thead>
                            <tr>
                                <th>Effective</th>
                                <th>Base Change</th>
                                <th>Total Change</th>
                                <th>Rule</th>
                                <th>Reason</th>
                                <th>Changed By</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($salaryRevisions as $revision)
                                <tr>
                                    <td>{{ $revision->effective_from?->format('d M Y') }}</td>
                                    <td>Rs {{ number_format((float) $revision->previous_base_salary, 2) }} → <strong>Rs {{ number_format((float) $revision->new_base_salary, 2) }}</strong></td>
                                    <td>Rs {{ number_format((float) $revision->previous_total_salary, 2) }} → <strong>Rs {{ number_format((float) $revision->new_total_salary, 2) }}</strong></td>
                                    <td>{{ $salaryStructureLabel($revision->salaryStructure, 'Current/Custom') }}</td>
                                    <td>{{ $revision->reason ?: 'No reason added' }}</td>
                                    <td>{{ $revision->changedBy?->name ?? 'System' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6"><div class="employee-empty">No salary revisions logged yet.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="incentives" class="employee-section">
            <div class="employee-section-head">
                <div>
                    <div class="employee-section-title">Incentives</div>
                    <div class="employee-section-copy">HR-facing incentive visibility using the existing CRM incentive data, without creating a new engine.</div>
                </div>
            </div>

            <div class="employee-summary-strip" style="padding-left: 0; padding-right: 0; border-top: 0;">
                <div class="employee-summary-card" style="margin-top: 0;">
                    <div class="employee-summary-label">Current Month</div>
                    <div class="employee-summary-value">Rs {{ number_format($incentiveSummary['current_month'], 0) }}</div>
                    <div class="employee-summary-note">New incentive activity this month</div>
                </div>
                <div class="employee-summary-card" style="margin-top: 0;">
                    <div class="employee-summary-label">Previous Month</div>
                    <div class="employee-summary-value">Rs {{ number_format($incentiveSummary['previous_month'], 0) }}</div>
                    <div class="employee-summary-note">Last closed month snapshot</div>
                </div>
                <div class="employee-summary-card" style="margin-top: 0;">
                    <div class="employee-summary-label">Pending</div>
                    <div class="employee-summary-value">Rs {{ number_format($incentiveSummary['pending'], 0) }}</div>
                    <div class="employee-summary-note">Waiting for final verification</div>
                </div>
                <div class="employee-summary-card" style="margin-top: 0;">
                    <div class="employee-summary-label">Total Paid</div>
                    <div class="employee-summary-value">Rs {{ number_format($incentiveSummary['total_paid'], 0) }}</div>
                    <div class="employee-summary-note">Verified incentive paid/approved</div>
                </div>
            </div>

            <div class="employee-panel">
                <div class="employee-panel-head">
                    <div class="employee-panel-title">Recent Incentives</div>
                    <div class="employee-panel-subtitle">Latest incentive records from site visits and CRM approvals.</div>
                </div>
                <div class="employee-table-wrap">
                    <table class="employee-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Site Visit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentIncentives as $incentive)
                                <tr>
                                    <td>{{ $incentive->created_at?->format('d M Y') }}</td>
                                    <td>{{ $incentive->type ?: 'Incentive' }}</td>
                                    <td>Rs {{ number_format((float) $incentive->amount, 2) }}</td>
                                    <td><span class="employee-pill {{ $incentive->status === 'verified' ? 'green' : 'amber' }}">{{ str_replace('_', ' ', $incentive->status) }}</span></td>
                                    <td>{{ $incentive->siteVisit?->customer_name ?? ('Visit #' . ($incentive->site_visit_id ?? '--')) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5"><div class="employee-empty">No incentive records found for this employee.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="assets" class="employee-section">
            <div class="employee-section-head">
                <div>
                    <div class="employee-section-title">Assets</div>
                    <div class="employee-section-copy">Issued inventory plus a compact action area to add or update asset status without leaving the employee record.</div>
                </div>
            </div>

            <div class="employee-split-workspace">
                <div class="employee-panel">
                    <div class="employee-panel-head">
                        <div class="employee-panel-title">Current Asset Register</div>
                        <div class="employee-panel-subtitle">Allocation, serials, condition, and return state.</div>
                    </div>
                    <div class="employee-table-wrap">
                        <table class="employee-table">
                            <thead>
                                <tr>
                                    <th>Asset</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Serial</th>
                                    <th>Condition</th>
                                    <th>Dates</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($profile->assets as $asset)
                                    @php
                                        $assetClass = match($asset->status) {
                                            'issued' => 'amber',
                                            'returned' => 'green',
                                            'lost', 'damaged' => 'red',
                                            default => 'slate',
                                        };
                                    @endphp
                                    <tr>
                                        <td>{{ $asset->asset_name }}</td>
                                        <td>{{ $asset->asset_type }}</td>
                                        <td><span class="employee-pill {{ $assetClass }}">{{ str_replace('_', ' ', $asset->status) }}</span></td>
                                        <td>{{ $asset->serial_number ?: '--' }}</td>
                                        <td>{{ $asset->asset_condition ?: '--' }}</td>
                                        <td>
                                            Issued: {{ $asset->issued_at?->format('d M Y') ?? '--' }}<br>
                                            Returned: {{ $asset->returned_at?->format('d M Y') ?? '--' }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6">
                                            <div class="employee-empty">No assets allocated yet.</div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="employee-status-stack">
                    <div class="employee-form-card">
                        <div class="employee-form-head">
                            <div class="employee-form-title">Add Asset</div>
                            <div class="employee-form-copy">Create a new asset allocation directly from this employee record.</div>
                        </div>
                        <form method="POST" action="{{ route($hrRouteBase . '.employees.assets.store', $employee) }}" enctype="multipart/form-data" class="employee-form-body">
                            @csrf
                            <div class="employee-form-grid">
                                <div class="employee-field"><label class="employee-label">Asset Type</label><input type="text" name="asset_type" class="employee-input" placeholder="Laptop"></div>
                                <div class="employee-field"><label class="employee-label">Asset Name</label><input type="text" name="asset_name" class="employee-input" placeholder="Dell Latitude 5440"></div>
                                <div class="employee-field"><label class="employee-label">Serial</label><input type="text" name="serial_number" class="employee-input" placeholder="SN12345"></div>
                                <div class="employee-field"><label class="employee-label">Vendor</label><input type="text" name="vendor" class="employee-input" placeholder="Dell"></div>
                                <div class="employee-field"><label class="employee-label">Condition</label><input type="text" name="asset_condition" class="employee-input" placeholder="Good"></div>
                                <div class="employee-field">
                                    <label class="employee-label">Status</label>
                                    <select name="status" class="employee-select">
                                        <option value="issued">Issued</option>
                                        <option value="returned">Returned</option>
                                        <option value="lost">Lost</option>
                                        <option value="damaged">Damaged</option>
                                    </select>
                                </div>
                                <div class="employee-field"><label class="employee-label">Issued At</label><input type="date" name="issued_at" class="employee-input"></div>
                                <div class="employee-field"><label class="employee-label">Returned At</label><input type="date" name="returned_at" class="employee-input"></div>
                                <div class="employee-field full"><label class="employee-label">Attachment</label><input type="file" name="attachment" class="employee-input"></div>
                                <div class="employee-field full"><label class="employee-label">Notes</label><input type="text" name="notes" class="employee-input" placeholder="Optional asset note"></div>
                            </div>
                            <div class="employee-form-actions">
                                <button type="submit" class="employee-btn employee-btn-primary">Add Asset</button>
                            </div>
                        </form>
                    </div>

                    @foreach($profile->assets as $asset)
                        <div class="employee-status-card">
                            <div class="employee-status-top">
                                <div class="employee-status-name">{{ $asset->asset_name }}</div>
                                <span class="employee-pill {{ $asset->status === 'issued' ? 'amber' : ($asset->status === 'returned' ? 'green' : 'red') }}">{{ str_replace('_', ' ', $asset->status) }}</span>
                            </div>
                            <div class="employee-status-meta">Serial: {{ $asset->serial_number ?: '--' }} | Condition: {{ $asset->asset_condition ?: '--' }}</div>
                            <form method="POST" action="{{ route($hrRouteBase . '.employees.assets.status', [$employee, $asset]) }}" style="margin-top: 14px;">
                                @csrf
                                <div class="employee-form-grid">
                                    <div class="employee-field">
                                        <label class="employee-label">Status</label>
                                        <select name="status" class="employee-select">
                                            <option value="issued" @selected($asset->status === 'issued')>Issued</option>
                                            <option value="returned" @selected($asset->status === 'returned')>Returned</option>
                                            <option value="lost" @selected($asset->status === 'lost')>Lost</option>
                                            <option value="damaged" @selected($asset->status === 'damaged')>Damaged</option>
                                        </select>
                                    </div>
                                    <div class="employee-field">
                                        <label class="employee-label">Condition</label>
                                        <input type="text" name="asset_condition" class="employee-input" value="{{ $asset->asset_condition }}">
                                    </div>
                                    <div class="employee-field">
                                        <label class="employee-label">Returned At</label>
                                        <input type="date" name="returned_at" class="employee-input" value="{{ $asset->returned_at?->format('Y-m-d') }}">
                                    </div>
                                    <div class="employee-field full">
                                        <label class="employee-label">Notes</label>
                                        <input type="text" name="notes" class="employee-input" value="{{ $asset->notes }}">
                                    </div>
                                </div>
                                <div class="employee-form-actions" style="margin-top: 14px;">
                                    <button type="submit" class="employee-btn employee-btn-secondary">Update Asset</button>
                                </div>
                            </form>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div id="documents" class="employee-section">
            <div class="employee-section-head">
                <div>
                    <div class="employee-section-title">Documents</div>
                    <div class="employee-section-copy">Track required KYC and upload employee documents from the same enterprise workspace.</div>
                </div>
                <a href="{{ $documentCenterUrl }}" class="employee-btn employee-btn-secondary">Open Document Center</a>
            </div>

            <div class="employee-split-workspace">
                <div class="employee-panel">
                    <div class="employee-panel-head">
                        <div class="employee-panel-title">Document Register</div>
                        <div class="employee-panel-subtitle">Required document health and uploaded file list.</div>
                    </div>
                    <div style="padding: 18px;">
                        @if(count($missingDocs))
                            <div class="employee-empty" style="border-style: solid; border-color: #f3c7bd; background: #fff6f3; color: #9f2f20; margin-bottom: 14px;">
                                Missing required documents: {{ implode(', ', $missingDocs) }}
                            </div>
                        @endif
                        <div class="employee-table-wrap">
                            <table class="employee-table">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Label</th>
                                        <th>Number</th>
                                        <th>Expiry</th>
                                        <th>Uploaded By</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($profile->documents as $document)
                                        <tr>
                                            <td>{{ $document->document_type }}</td>
                                            <td>{{ $document->document_label }}</td>
                                            <td>{{ $document->document_number ?: '--' }}</td>
                                            <td>{{ $document->expires_at?->format('d M Y') ?? '--' }}</td>
                                            <td>{{ $document->uploader?->name ?? 'System' }}</td>
                                            <td>
                                                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                                    @if($document->file_path)
                                                        <a href="{{ route($hrRouteBase . '.document-center.download', $document) }}" class="employee-btn employee-btn-secondary" style="min-height: 34px;">Download</a>
                                                    @endif
                                                <form method="POST" action="{{ route($hrRouteBase . '.employees.documents.destroy', [$employee, $document]) }}" onsubmit="return confirm('Remove this document?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="employee-btn employee-btn-secondary" style="min-height: 34px;">Remove</button>
                                                </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6">
                                                <div class="employee-empty">No documents uploaded yet.</div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="employee-form-card">
                    <div class="employee-form-head">
                        <div class="employee-form-title">Add Document</div>
                        <div class="employee-form-copy">Upload KYC or employee paperwork without leaving this page.</div>
                    </div>
                    <form method="POST" action="{{ route($hrRouteBase . '.employees.documents.store', $employee) }}" enctype="multipart/form-data" class="employee-form-body">
                        @csrf
                        <div class="employee-form-grid">
                            <div class="employee-field">
                                <label class="employee-label">Document Type</label>
                                <select name="document_type" class="employee-select">
                                    <option value="pan_card">PAN</option>
                                    <option value="aadhaar_card">Aadhaar</option>
                                    <option value="id_proof">ID Proof</option>
                                    <option value="address_proof">Address Proof</option>
                                    <option value="appointment_letter">Appointment Letter</option>
                                    <option value="custom">Custom</option>
                                </select>
                            </div>
                            <div class="employee-field"><label class="employee-label">Document Label</label><input type="text" name="document_label" class="employee-input" placeholder="PAN Card"></div>
                            <div class="employee-field"><label class="employee-label">Document Number</label><input type="text" name="document_number" class="employee-input" placeholder="Optional"></div>
                            <div class="employee-field"><label class="employee-label">Expiry</label><input type="date" name="expires_at" class="employee-input"></div>
                            <div class="employee-field">
                                <label class="employee-label">Required</label>
                                <select name="is_required" class="employee-select">
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                </select>
                            </div>
                            <div class="employee-field full"><label class="employee-label">File</label><input type="file" name="file" class="employee-input"></div>
                            <div class="employee-field full"><label class="employee-label">Notes</label><input type="text" name="notes" class="employee-input" placeholder="Optional note"></div>
                        </div>
                        <div class="employee-form-actions">
                            <button type="submit" class="employee-btn employee-btn-primary">Add Document</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="exit" class="employee-section">
            <div class="employee-section-head">
                <div>
                    <div class="employee-section-title">Exit Workflow</div>
                    <div class="employee-section-copy">Notice, resignation, termination, clearances, asset return, and final close from one controlled HR action panel.</div>
                </div>
            </div>

            <div class="employee-split-workspace">
                <div class="employee-panel">
                    <div class="employee-panel-head">
                        <div class="employee-panel-title">Current Exit Case</div>
                        <div class="employee-panel-subtitle">Lifecycle status is separate from login state and must be closed intentionally.</div>
                    </div>
                    <div class="employee-data-list">
                        <div class="employee-data-row"><div class="employee-data-label">Current Status</div><div class="employee-data-value">{{ $exitWorkflow ? str_replace('_', ' ', $exitWorkflow->status) : 'No exit case open' }}</div></div>
                        <div class="employee-data-row"><div class="employee-data-label">Notice Start</div><div class="employee-data-value {{ $exitWorkflow?->notice_start_date ? '' : 'muted' }}">{{ $exitWorkflow?->notice_start_date?->format('d M Y') ?? 'Not set' }}</div></div>
                        <div class="employee-data-row"><div class="employee-data-label">Resignation Date</div><div class="employee-data-value {{ $exitWorkflow?->resignation_date ? '' : 'muted' }}">{{ $exitWorkflow?->resignation_date?->format('d M Y') ?? 'Not set' }}</div></div>
                        <div class="employee-data-row"><div class="employee-data-label">Last Working Date</div><div class="employee-data-value {{ $exitWorkflow?->last_working_date ? '' : 'muted' }}">{{ $exitWorkflow?->last_working_date?->format('d M Y') ?? 'Not set' }}</div></div>
                        <div class="employee-data-row"><div class="employee-data-label">Asset Return</div><div class="employee-data-value">{{ $openAssets > 0 ? $openAssets . ' issued asset(s) still open' : 'All assets clear' }}</div></div>
                        <div class="employee-data-row"><div class="employee-data-label">Login State</div><div class="employee-data-value">{{ $employee->is_active ? 'Enabled' : 'Disabled' }}</div></div>
                    </div>
                </div>

                <div class="employee-form-card">
                    <div class="employee-form-head">
                        <div class="employee-form-title">Update Exit Workflow</div>
                        <div class="employee-form-copy">Use this when an employee goes on notice, resigns, is terminated, or the exit gets formally closed.</div>
                    </div>
                    <form method="POST" action="{{ route($hrRouteBase . '.employees.exit-workflow.update', $employee) }}" class="employee-form-body">
                        @csrf
                        <div class="employee-form-grid">
                            <div class="employee-field">
                                <label class="employee-label">Exit Status</label>
                                <select name="status" class="employee-select">
                                    <option value="on_notice" @selected(($exitWorkflow?->status ?? $profile->employment_status) === 'on_notice')>On Notice</option>
                                    <option value="resigned" @selected(($exitWorkflow?->status ?? $profile->employment_status) === 'resigned')>Resigned</option>
                                    <option value="terminated" @selected(($exitWorkflow?->status ?? $profile->employment_status) === 'terminated')>Terminated</option>
                                    <option value="closed" @selected(($exitWorkflow?->status ?? '') === 'closed')>Exited / Closed</option>
                                </select>
                            </div>
                            <div class="employee-field"><label class="employee-label">Notice Start</label><input type="date" name="notice_start_date" class="employee-input" value="{{ $exitWorkflow?->notice_start_date?->format('Y-m-d') }}"></div>
                            <div class="employee-field"><label class="employee-label">Resignation Date</label><input type="date" name="resignation_date" class="employee-input" value="{{ $exitWorkflow?->resignation_date?->format('Y-m-d') }}"></div>
                            <div class="employee-field"><label class="employee-label">Last Working Date</label><input type="date" name="last_working_date" class="employee-input" value="{{ $exitWorkflow?->last_working_date?->format('Y-m-d') }}"></div>
                            <div class="employee-field full"><label class="employee-label">Exit Reason</label><textarea name="exit_reason" class="employee-textarea" rows="3" placeholder="Reason for resignation / termination / separation">{{ $exitWorkflow?->exit_reason }}</textarea></div>
                            <div class="employee-field full"><label class="employee-label">Notes</label><textarea name="notes" class="employee-textarea" rows="3" placeholder="Internal exit notes">{{ $exitWorkflow?->notes }}</textarea></div>
                            <div class="employee-field"><label class="employee-label">HR Clearance</label><select name="hr_clearance_completed" class="employee-select"><option value="0">Pending</option><option value="1" @selected((bool) $exitWorkflow?->hr_clearance_completed_at)>Completed</option></select></div>
                            <div class="employee-field"><label class="employee-label">Finance Clearance</label><select name="finance_clearance_completed" class="employee-select"><option value="0">Pending</option><option value="1" @selected((bool) $exitWorkflow?->finance_clearance_completed_at)>Completed</option></select></div>
                            <div class="employee-field"><label class="employee-label">Asset Clearance</label><select name="asset_clearance_completed" class="employee-select"><option value="0">Pending</option><option value="1" @selected((bool) $exitWorkflow?->asset_clearance_completed_at)>Completed</option></select></div>
                            <div class="employee-field"><label class="employee-label">Disable Login</label><select name="disable_login_now" class="employee-select"><option value="0">Keep enabled</option><option value="1">Disable now</option></select></div>
                        </div>
                        <div class="employee-form-actions">
                            <button type="submit" class="employee-btn employee-btn-primary">Save Exit Workflow</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="timeline" class="employee-section">
            <div class="employee-section-head">
                <div>
                    <div class="employee-section-title">Timeline</div>
                    <div class="employee-section-copy">Employee lifecycle, salary, document, and asset history in a simple operational timeline.</div>
                </div>
            </div>

            <div class="employee-timeline">
                @forelse($profile->timelineEvents as $event)
                    <div class="employee-timeline-item">
                        <div class="employee-timeline-top">
                            <div class="employee-timeline-title">{{ $event->title }}</div>
                            <div class="employee-timeline-date">{{ $event->event_date?->format('d M Y h:i A') ?? $event->created_at->format('d M Y h:i A') }}</div>
                        </div>
                        <div class="employee-timeline-copy">
                            {{ $event->summary ?: 'No extra notes.' }}
                            @if($event->actor)
                                <br>By {{ $event->actor->name }}
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="employee-empty">Timeline entries will appear here after employee activity starts.</div>
                @endforelse
            </div>
        </div>
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

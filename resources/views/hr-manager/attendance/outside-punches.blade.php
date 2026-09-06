@extends('layouts.app')

@section('title', 'Manual Punch')
@section('page-title', 'Manual Punch')

@push('styles')
<style>
    .manual-punch-page {
        display: flex;
        flex-direction: column;
        gap: 16px;
        color: #10231a;
    }

    .manual-card {
        background: #fff;
        border: 1px solid #e4ebe5;
        border-radius: 18px;
        box-shadow: 0 12px 32px rgba(16, 35, 26, .06);
    }

    .manual-hero {
        padding: 22px 26px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
    }

    .manual-eyebrow {
        margin-bottom: 6px;
        color: #65766d;
        font-size: 11px;
        font-weight: 900;
        letter-spacing: .16em;
        text-transform: uppercase;
    }

    .manual-title {
        margin: 0;
        color: #031b10;
        font-size: 28px;
        line-height: 1.1;
        font-weight: 900;
    }

    .manual-subtitle {
        margin-top: 7px;
        color: #52677a;
        font-size: 13px;
    }

    .manual-stats {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .manual-stat {
        min-width: 122px;
        padding: 13px 16px;
        border: 1px solid #e2e8f0;
        border-radius: 15px;
        background: #f8fafc;
        text-align: center;
    }

    .manual-stat.pending {
        background: #fff7ed;
        border-color: #fed7aa;
    }

    .manual-stat.enabled {
        background: #ecfdf5;
        border-color: #bbf7d0;
    }

    .manual-stat strong {
        display: block;
        color: #10231a;
        font-size: 24px;
        line-height: 1;
        font-weight: 900;
    }

    .manual-stat span {
        display: block;
        margin-top: 6px;
        color: #65766d;
        font-size: 10px;
        font-weight: 900;
        letter-spacing: .1em;
        text-transform: uppercase;
    }

    .manual-filter {
        padding: 18px;
    }

    .manual-filter form {
        display: grid;
        grid-template-columns: minmax(240px, 1.6fr) repeat(3, minmax(150px, .75fr)) auto;
        gap: 12px;
        align-items: end;
    }

    .manual-label {
        display: block;
        margin-bottom: 7px;
        color: #65766d;
        font-size: 10px;
        font-weight: 900;
        letter-spacing: .14em;
        text-transform: uppercase;
    }

    .manual-input,
    .manual-select {
        width: 100%;
        min-height: 44px;
        border: 1px solid #d6ded8;
        border-radius: 12px;
        background: #fff;
        padding: 10px 12px;
        color: #0f172a;
        font-size: 14px;
    }

    .manual-filter-actions {
        display: flex;
        gap: 8px;
        align-items: center;
        justify-content: flex-end;
    }

    .manual-btn {
        border: 0;
        border-radius: 11px;
        padding: 10px 14px;
        font: inherit;
        font-size: 13px;
        font-weight: 900;
        cursor: pointer;
        text-decoration: none;
        white-space: nowrap;
    }

    .manual-btn.primary {
        background: #06633b;
        color: #fff;
    }

    .manual-btn.secondary {
        background: #fff;
        border: 1px solid #d6ded8;
        color: #10231a;
    }

    .manual-btn.danger {
        background: #e11d48;
        color: #fff;
    }

    .manual-section-head {
        padding: 17px 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        border-bottom: 1px solid #edf2ef;
    }

    .manual-section-title {
        margin: 0;
        color: #031b10;
        font-size: 21px;
        font-weight: 900;
    }

    .manual-section-meta {
        color: #64748b;
        font-size: 12px;
        font-weight: 800;
    }

    .manual-request-list,
    .manual-user-list,
    .manual-photo-list,
    .manual-decision-list {
        display: flex;
        flex-direction: column;
    }

    .manual-request-row,
    .manual-user-row,
    .manual-decision-row {
        padding: 15px 18px;
        display: grid;
        align-items: center;
        gap: 14px;
        border-bottom: 1px solid #edf2ef;
    }

    .manual-request-row:last-child,
    .manual-user-row:last-child,
    .manual-decision-row:last-child {
        border-bottom: 0;
    }

    .manual-request-row {
        grid-template-columns: minmax(190px, .8fr) minmax(260px, 1.5fr) minmax(250px, 1fr);
    }

    .manual-user-row {
        grid-template-columns: minmax(210px, .9fr) minmax(190px, .7fr) minmax(260px, 1fr) auto;
    }

    .manual-user-main {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 0;
    }

    .manual-avatar {
        width: 42px;
        height: 42px;
        border-radius: 14px;
        display: grid;
        place-items: center;
        flex: 0 0 auto;
        background: #e9f7ef;
        color: #075332;
        font-weight: 900;
    }

    .manual-name {
        color: #061c12;
        font-weight: 900;
    }

    .manual-muted {
        margin-top: 2px;
        color: #607386;
        font-size: 12px;
    }

    .manual-pill {
        display: inline-flex;
        align-items: center;
        padding: 6px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 900;
        white-space: nowrap;
    }

    .manual-pill.green {
        background: #ecfdf5;
        border: 1px solid #bbf7d0;
        color: #047857;
    }

    .manual-pill.blue {
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        color: #1d4ed8;
    }

    .manual-pill.gray {
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        color: #475569;
    }

    .manual-pill.amber {
        background: #fff7ed;
        border: 1px solid #fed7aa;
        color: #b45309;
    }

    .manual-access-line {
        display: flex;
        flex-wrap: wrap;
        gap: 7px;
    }

    .manual-row-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        flex-wrap: wrap;
        gap: 8px;
    }

    .manual-toggle {
        border: 1px solid #d6ded8;
        border-radius: 999px;
        background: #fff;
        padding: 8px 11px;
        color: #10231a;
        font: inherit;
        font-size: 12px;
        font-weight: 900;
        cursor: pointer;
        white-space: nowrap;
    }

    .manual-toggle.is-on {
        border-color: #bbf7d0;
        background: #dcfce7;
        color: #166534;
    }

    .manual-toggle.direct-on {
        border-color: #bfdbfe;
        background: #dbeafe;
        color: #1d4ed8;
    }

    .manual-window-form {
        padding: 16px 18px;
        border-bottom: 1px solid #edf2ef;
        background: #fbfdfc;
    }

    .manual-window-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(160px, .6fr)) minmax(180px, .7fr) minmax(260px, 1fr) auto;
        gap: 12px;
        align-items: end;
    }

    .manual-checkboxes {
        display: flex;
        flex-direction: column;
        gap: 8px;
        color: #334155;
        font-size: 13px;
    }

    .manual-empty {
        padding: 34px 18px;
        text-align: center;
        color: #64748b;
    }

    .manual-grid-two {
        display: grid;
        grid-template-columns: minmax(0, 1.05fr) minmax(320px, .75fr);
        gap: 16px;
    }

    .manual-photo-list {
        padding: 16px;
        gap: 12px;
    }

    .manual-photo-card {
        padding: 13px;
        border: 1px solid #edf2ef;
        border-radius: 14px;
        background: #fcfffd;
    }

    .manual-photo-images {
        margin-top: 12px;
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }

    .manual-photo-open img,
    .manual-photo-placeholder {
        width: 100%;
        height: 96px;
        border: 1px solid #e4ebe5;
        border-radius: 12px;
        object-fit: cover;
        background: #f8fafc;
    }

    .manual-photo-placeholder {
        display: grid;
        place-items: center;
        color: #94a3b8;
        font-size: 12px;
    }

    .manual-decision-row {
        grid-template-columns: minmax(0, 1fr) auto;
        align-items: start;
    }

    .manual-decision-main {
        min-width: 0;
    }

    .manual-decision-facts {
        margin-top: 8px;
        display: flex;
        flex-wrap: wrap;
        gap: 7px;
    }

    .manual-decision-status {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 5px;
        min-width: 96px;
    }

    .manual-decision-approver {
        max-width: 120px;
        color: #607386;
        font-size: 12px;
        text-align: right;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    @media (max-width: 1200px) {
        .manual-filter form,
        .manual-request-row,
        .manual-user-row,
        .manual-window-grid,
        .manual-grid-two,
        .manual-decision-row {
            grid-template-columns: 1fr;
        }

        .manual-row-actions,
        .manual-filter-actions {
            justify-content: flex-start;
        }

        .manual-decision-status {
            align-items: flex-start;
        }

        .manual-decision-approver {
            max-width: 100%;
            text-align: left;
        }
    }

    @media (max-width: 720px) {
        .manual-hero,
        .manual-section-head {
            flex-direction: column;
            align-items: flex-start;
        }

        .manual-stats {
            justify-content: flex-start;
            width: 100%;
        }

        .manual-stat {
            flex: 1 1 140px;
        }
    }
</style>
@endpush

@section('content')
@php
    $isHrManager = auth()->check() && auth()->user()->isHrManager();
    $isJuniorHr = auth()->check() && auth()->user()->isJuniorHr();
    $usesHrAttendanceRoutes = $isHrManager || $isJuniorHr;
    $canManageOutsidePunchAccess = ! $isJuniorHr;
    $reviewRouteBase = $usesHrAttendanceRoutes ? 'hr-manager.attendance' : 'admin.attendance';
    $outsideIndexRoute = $usesHrAttendanceRoutes
        ? route('hr-manager.attendance.outside-punches', request()->query())
        : route('admin.attendance.outside-punches.index', request()->query());
    $toggleRequestRoute = fn ($mapping) => route(($usesHrAttendanceRoutes ? 'hr-manager.attendance.outside-punches.toggle-request' : 'admin.attendance.outside-punches.toggle-request'), $mapping);
    $toggleDirectRoute = fn ($mapping) => route(($usesHrAttendanceRoutes ? 'hr-manager.attendance.outside-punches.toggle-direct-allow' : 'admin.attendance.outside-punches.toggle-direct-allow'), $mapping);
    $windowRoute = fn ($mapping) => route(($usesHrAttendanceRoutes ? 'hr-manager.attendance.outside-punches.window' : 'admin.attendance.outside-punches.window'), $mapping);
    $approveRoute = fn ($requestItem) => route($reviewRouteBase . '.outside-punches.approve', $requestItem);
    $rejectRoute = fn ($requestItem) => route($reviewRouteBase . '.outside-punches.reject', $requestItem);
    $mapsUrl = fn ($latitude, $longitude) => $latitude !== null && $longitude !== null
        ? 'https://www.google.com/maps?q=' . $latitude . ',' . $longitude
        : null;
    $distanceLabel = fn ($meters) => $meters === null
        ? '--'
        : ($meters >= 1000
            ? number_format($meters / 1000, 2) . ' km'
            : number_format($meters, 0) . ' m');
    $photoUrl = fn ($event) => $event?->photo?->file_path ? asset('storage/' . $event->photo->file_path) : null;
@endphp

<div class="manual-punch-page">
    @include('attendance._flash')

    <section class="manual-card manual-hero">
        <div>
            <div class="manual-eyebrow">HR Attendance</div>
            <h1 class="manual-title">Manual Punch</h1>
            <div class="manual-subtitle">Allow outside punch by request, direct access, or date window.</div>
        </div>
        <div class="manual-stats">
            <div class="manual-stat pending">
                <strong>{{ $summary['pending'] }}</strong>
                <span>Pending</span>
            </div>
            <div class="manual-stat enabled">
                <strong>{{ $summary['outside_enabled_users'] }}</strong>
                <span>Allowed Users</span>
            </div>
            <div class="manual-stat">
                <strong>{{ $summary['approved_today'] }}</strong>
                <span>Approved Today</span>
            </div>
            <div class="manual-stat">
                <strong>{{ $summary['rejected'] }}</strong>
                <span>Rejected</span>
            </div>
        </div>
    </section>

    <section class="manual-card manual-filter">
        <form method="GET" action="{{ $outsideIndexRoute }}">
            <div>
                <label class="manual-label">Search</label>
                <input type="text" name="search" value="{{ $search }}" placeholder="Employee name" class="manual-input">
            </div>
            <div>
                <label class="manual-label">Office</label>
                <select name="office_id" class="manual-select">
                    <option value="">All Offices</option>
                    @foreach($officeOptions as $office)
                        <option value="{{ $office->id }}" @selected((string) $officeId === (string) $office->id)>{{ $office->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="manual-label">Access</label>
                <select name="status" class="manual-select">
                    <option value="all" @selected($statusFilter === 'all')>All users</option>
                    <option value="request" @selected($statusFilter === 'request')>Request allowed</option>
                    <option value="direct" @selected($statusFilter === 'direct')>Direct allowed</option>
                    <option value="strict" @selected($statusFilter === 'strict')>Strict only</option>
                </select>
            </div>
            <div>
                <label class="manual-label">Date</label>
                <input type="date" name="date" value="{{ $selectedDate->toDateString() }}" class="manual-input">
            </div>
            <div class="manual-filter-actions">
                <a href="{{ $usesHrAttendanceRoutes ? route('hr-manager.attendance.outside-punches') : route('admin.attendance.outside-punches.index') }}" class="manual-btn secondary">Reset</a>
                <button type="submit" class="manual-btn primary">Filter</button>
            </div>
        </form>
    </section>

    @if($canManageOutsidePunchAccess)
    <section class="manual-card">
        <div class="manual-section-head">
            <div>
                <h2 class="manual-section-title">Pending Requests</h2>
                <div class="manual-section-meta">Employee requested outside punch approval.</div>
            </div>
            <span class="manual-pill amber">{{ $pendingRequests->count() }} pending</span>
        </div>
        <div class="manual-request-list">
            @forelse($pendingRequests as $requestItem)
                <article class="manual-request-row">
                    <div class="manual-user-main">
                        <div class="manual-avatar">{{ strtoupper(substr((string) $requestItem->user?->name, 0, 1)) ?: 'U' }}</div>
                        <div>
                            <div class="manual-name">{{ $requestItem->user?->name ?: 'Unknown User' }}</div>
                            <div class="manual-muted">{{ $requestItem->user?->role?->name ?? 'Employee' }} · {{ ucfirst($requestItem->punch_type) }} · {{ optional($requestItem->requested_at)->format('d M Y h:i A') }}</div>
                        </div>
                    </div>
                    <div>
                        <div class="manual-access-line">
                            <span class="manual-pill blue">{{ $requestItem->officeLocation?->name ?? 'No office' }}</span>
                            <span class="manual-pill amber">{{ $distanceLabel($requestItem->geo_distance_meters) }} away</span>
                        </div>
                        <div class="manual-muted" style="margin-top:8px;">{{ $requestItem->reason ?: 'No reason added.' }}</div>
                        @if($mapsUrl($requestItem->latitude, $requestItem->longitude))
                            <a href="{{ $mapsUrl($requestItem->latitude, $requestItem->longitude) }}" target="_blank" rel="noopener" class="manual-muted" style="display:inline-block;text-decoration:underline;margin-top:6px;color:#06633b;">Open map</a>
                        @endif
                    </div>
                    <div class="manual-row-actions">
                        <form method="POST" action="{{ $approveRoute($requestItem) }}">
                            @csrf
                            <button type="submit" class="manual-btn primary">Approve</button>
                        </form>
                        <form method="POST" action="{{ $rejectRoute($requestItem) }}">
                            @csrf
                            <button type="submit" class="manual-btn danger">Reject</button>
                        </form>
                    </div>
                </article>
            @empty
                <div class="manual-empty">No pending outside punch requests.</div>
            @endforelse
        </div>
    </section>

    <section class="manual-card">
        <div class="manual-section-head">
            <div>
                <h2 class="manual-section-title">User Access</h2>
                <div class="manual-section-meta">Turn request access on, allow direct punch, or set a temporary window.</div>
            </div>
            <span class="manual-pill green">{{ $userRows->count() }} users</span>
        </div>
        <div class="manual-user-list">
            @forelse($userRows as $row)
                @php
                    $profile = $row['profile'];
                    $permission = $row['current_permission'];
                    $windowUrl = request()->fullUrlWithQuery(['window_user' => $profile->user_id]) . '#outside-user-' . $profile->user_id;
                    $initial = strtoupper(substr((string) $profile->user?->name, 0, 1)) ?: 'U';
                @endphp
                <article id="outside-user-{{ $profile->user_id }}" class="manual-user-row">
                    <div class="manual-user-main">
                        <div class="manual-avatar">{{ $initial }}</div>
                        <div>
                            <div class="manual-name">{{ $profile->user?->name }}</div>
                            <div class="manual-muted">{{ $profile->user?->role?->name ?? 'Employee' }} · Code {{ $profile->employee_code ?: '--' }}</div>
                        </div>
                    </div>
                    <div>
                        <div class="manual-name" style="font-size:14px;">{{ $profile->officeLocation?->name ?? 'No office' }}</div>
                        <div class="manual-muted">{{ $profile->attendancePolicy?->name ?? 'No policy' }}</div>
                    </div>
                    <div class="manual-access-line">
                        <span class="manual-pill {{ $row['request_allowed'] ? 'green' : 'gray' }}">Request {{ $row['request_allowed'] ? 'On' : 'Off' }}</span>
                        <span class="manual-pill {{ $row['direct_allow'] ? 'blue' : 'gray' }}">Direct {{ $row['direct_allow'] ? 'On' : 'Off' }}</span>
                        <span class="manual-pill {{ $permission ? 'amber' : 'gray' }}">{{ $row['status_label'] }}</span>
                        @if($permission)
                            <span class="manual-muted">{{ $permission->start_date?->format('d M') }} to {{ $permission->end_date?->format('d M') }}</span>
                        @endif
                    </div>
                    <div class="manual-row-actions">
                        <form method="POST" action="{{ $toggleRequestRoute($profile) }}">
                            @csrf
                            <input type="hidden" name="enabled" value="{{ $row['request_allowed'] ? 0 : 1 }}">
                            <button type="submit" class="manual-toggle {{ $row['request_allowed'] ? 'is-on' : '' }}">
                                Request {{ $row['request_allowed'] ? 'Off' : 'On' }}
                            </button>
                        </form>
                        <form method="POST" action="{{ $toggleDirectRoute($profile) }}">
                            @csrf
                            <input type="hidden" name="enabled" value="{{ $row['direct_allow'] ? 0 : 1 }}">
                            <button type="submit" class="manual-toggle {{ $row['direct_allow'] ? 'direct-on' : '' }}">
                                Direct {{ $row['direct_allow'] ? 'Off' : 'On' }}
                            </button>
                        </form>
                        <a href="{{ $windowUrl }}" class="manual-btn secondary">Window</a>
                    </div>
                </article>

                @if($row['window_open'])
                    <div class="manual-window-form">
                        <form method="POST" action="{{ $windowRoute($profile) }}" class="manual-window-grid">
                            @csrf
                            <input type="hidden" name="permission_id" value="{{ $permission?->id }}">
                            <div>
                                <label class="manual-label">Start</label>
                                <input type="date" name="start_date" value="{{ optional($permission?->start_date)->toDateString() ?? now()->toDateString() }}" class="manual-input" required>
                            </div>
                            <div>
                                <label class="manual-label">End</label>
                                <input type="date" name="end_date" value="{{ optional($permission?->end_date)->toDateString() ?? now()->toDateString() }}" class="manual-input" required>
                            </div>
                            <div class="manual-checkboxes">
                                <label><input type="checkbox" name="allow_punch_in" value="1" @checked($permission?->allow_punch_in ?? true)> Punch in</label>
                                <label><input type="checkbox" name="allow_punch_out" value="1" @checked($permission?->allow_punch_out ?? true)> Punch out</label>
                            </div>
                            <div>
                                <label class="manual-label">Reason</label>
                                <input type="text" name="reason" value="{{ $permission?->reason }}" placeholder="Site work / travel / temporary exception" class="manual-input">
                            </div>
                            <div class="manual-filter-actions">
                                <a href="{{ $usesHrAttendanceRoutes ? route('hr-manager.attendance.outside-punches', request()->except('window_user')) : route('admin.attendance.outside-punches.index', request()->except('window_user')) }}" class="manual-btn secondary">Close</a>
                                <button type="submit" class="manual-btn primary">Save</button>
                            </div>
                        </form>
                    </div>
                @endif
            @empty
                <div class="manual-empty">No attendance-enabled users match the current filters.</div>
            @endforelse
        </div>
    </section>
    @endif

    <div class="manual-grid-two">
        <section class="manual-card">
            <div class="manual-section-head">
                <div>
                    <h2 class="manual-section-title">Punch Photos</h2>
                    <div class="manual-section-meta">{{ $selectedDate->format('d M Y') }}</div>
                </div>
                <span class="manual-pill gray">{{ $photoRows->count() }} users</span>
            </div>
            <div class="manual-photo-list">
                @forelse($photoRows as $photoRow)
                    @php
                        $profile = $photoRow['profile'];
                        $inUrl = $photoUrl($photoRow['punch_in_event']);
                        $outUrl = $photoUrl($photoRow['punch_out_event']);
                    @endphp
                    <div class="manual-photo-card">
                        <div class="manual-user-main" style="justify-content:space-between;">
                            <div>
                                <div class="manual-name">{{ $profile->user?->name }}</div>
                                <div class="manual-muted">{{ $photoRow['outside_label'] }} · {{ $photoRow['distance_label'] }}</div>
                            </div>
                        </div>
                        <div class="manual-photo-images">
                            <div>
                                <div class="manual-label">Punch In</div>
                                @if($inUrl)
                                    <button type="button" class="manual-photo-open outside-photo-open" data-photo-src="{{ $inUrl }}" data-photo-title="{{ $profile->user?->name }} · Punch In">
                                        <img src="{{ $inUrl }}" alt="Punch in photo">
                                    </button>
                                @else
                                    <div class="manual-photo-placeholder">No photo</div>
                                @endif
                            </div>
                            <div>
                                <div class="manual-label">Punch Out</div>
                                @if($outUrl)
                                    <button type="button" class="manual-photo-open outside-photo-open" data-photo-src="{{ $outUrl }}" data-photo-title="{{ $profile->user?->name }} · Punch Out">
                                        <img src="{{ $outUrl }}" alt="Punch out photo">
                                    </button>
                                @else
                                    <div class="manual-photo-placeholder">No photo</div>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="manual-empty">No punch photos for selected date.</div>
                @endforelse
            </div>
        </section>

        <section class="manual-card">
            <div class="manual-section-head">
                <div>
                    <h2 class="manual-section-title">Recent Decisions</h2>
                    <div class="manual-section-meta">Latest approved/rejected requests.</div>
                </div>
            </div>
            <div class="manual-decision-list">
                @forelse($recentRequests as $requestItem)
                    <article class="manual-decision-row">
                        <div class="manual-decision-main">
                            <div class="manual-name">{{ $requestItem->user?->name }}</div>
                            <div class="manual-muted">{{ optional($requestItem->requested_at)->format('d M Y h:i A') }}</div>
                            <div class="manual-decision-facts">
                                <span class="manual-pill gray">{{ ucfirst($requestItem->punch_type) }}</span>
                                <span class="manual-pill gray">{{ $requestItem->officeLocation?->name ?? 'No office' }}</span>
                                <span class="manual-pill amber">{{ $distanceLabel($requestItem->geo_distance_meters) }}</span>
                            </div>
                        </div>
                        <div class="manual-decision-status">
                            <span class="manual-pill {{ $requestItem->status === 'consumed' ? 'green' : 'amber' }}">
                                {{ $requestItem->status === 'consumed' ? 'Approved' : ucfirst($requestItem->status) }}
                            </span>
                            <div class="manual-decision-approver" title="{{ $requestItem->approver?->name ?? '--' }}">{{ $requestItem->approver?->name ?? '--' }}</div>
                        </div>
                    </article>
                @empty
                    <div class="manual-empty">No recent outside punch decisions.</div>
                @endforelse
            </div>
        </section>
    </div>
</div>

<div id="outsidePhotoModal" class="fixed inset-0 bg-black/70 z-[2000] hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-3xl w-full overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-[#EEE8DF]">
            <div class="font-semibold text-brand-primary" id="outsidePhotoModalTitle">Punch Photo</div>
            <button type="button" id="outsidePhotoModalClose" class="text-2xl leading-none text-[#6B7280]">&times;</button>
        </div>
        <div class="p-4">
            <img id="outsidePhotoModalImage" src="" alt="Punch photo" class="w-full max-h-[75vh] object-contain rounded-xl bg-[#F8FAFC]">
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('outsidePhotoModal');
    const modalImage = document.getElementById('outsidePhotoModalImage');
    const modalTitle = document.getElementById('outsidePhotoModalTitle');
    const modalClose = document.getElementById('outsidePhotoModalClose');

    document.querySelectorAll('.outside-photo-open').forEach((button) => {
        button.addEventListener('click', function () {
            modalImage.src = button.dataset.photoSrc || '';
            modalTitle.textContent = button.dataset.photoTitle || 'Punch Photo';
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        });
    });

    const closeModal = () => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        modalImage.src = '';
    };

    modalClose?.addEventListener('click', closeModal);
    modal?.addEventListener('click', function (event) {
        if (event.target === modal) {
            closeModal();
        }
    });
});
</script>
@endsection

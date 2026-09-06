@php
    $attendanceRouteBase = auth()->check() && auth()->user()->isHrManager()
        ? 'hr-manager.settings.attendance'
        : 'admin.attendance';
    $attendanceReviewRouteBase = auth()->check() && auth()->user()->isHrManager()
        ? 'hr-manager.attendance'
        : 'admin.attendance';
    $outsidePunchRouteName = auth()->check() && auth()->user()->isHrManager()
        ? 'hr-manager.attendance.outside-punches'
        : 'admin.attendance.outside-punches.index';
    $isAttendanceRoute = fn (string $pattern) => request()->routeIs($pattern);
@endphp
<div class="attendance-setup-nav">
    <div class="attendance-setup-nav-head">
        <span class="attendance-setup-nav-badge">Attendance Setup</span>
        <span class="attendance-setup-nav-copy">Simple flow: create offices, set rules, then assign users. The remaining tabs are for secondary setup and review.</span>
    </div>
    <div class="attendance-setup-nav-grid">
        <div class="attendance-setup-nav-group">
            <div class="attendance-setup-nav-label">Setup</div>
            <div class="attendance-setup-nav-links">
                <a href="{{ route($attendanceRouteBase . '.offices.index') }}" class="attendance-setup-nav-link {{ $isAttendanceRoute($attendanceRouteBase . '.offices.*') ? 'is-active' : '' }}">Offices</a>
                <a href="{{ route($attendanceRouteBase . '.policies.index') }}" class="attendance-setup-nav-link {{ $isAttendanceRoute($attendanceRouteBase . '.policies.*') ? 'is-active' : '' }}">Rules</a>
                <a href="{{ route($attendanceRouteBase . '.user-mappings.index') }}" class="attendance-setup-nav-link {{ $isAttendanceRoute($attendanceRouteBase . '.user-mappings.*') ? 'is-active' : '' }}">Assign Users</a>
            </div>
        </div>
        <div class="attendance-setup-nav-group">
            <div class="attendance-setup-nav-label">Requests</div>
            <div class="attendance-setup-nav-links">
                <a href="{{ route($attendanceRouteBase . '.leave-types.index') }}" class="attendance-setup-nav-link {{ $isAttendanceRoute($attendanceRouteBase . '.leave-types.*') ? 'is-active' : '' }}">Leave Types</a>
                <a href="{{ route($attendanceRouteBase . '.overtimes.index') }}" class="attendance-setup-nav-link {{ $isAttendanceRoute($attendanceRouteBase . '.overtimes.*') ? 'is-active' : '' }}">Overtimes</a>
            </div>
        </div>
        <div class="attendance-setup-nav-group">
            <div class="attendance-setup-nav-label">Review</div>
            <div class="attendance-setup-nav-links">
                <a href="{{ route($attendanceRouteBase . '.simulator.index') }}" class="attendance-setup-nav-link {{ $isAttendanceRoute($attendanceRouteBase . '.simulator.*') ? 'is-active' : '' }}">Simulator</a>
                <a href="{{ route($outsidePunchRouteName) }}" class="attendance-setup-nav-link {{ $isAttendanceRoute($attendanceReviewRouteBase . '.outside-punches*') ? 'is-active' : '' }}">Outside Punch</a>
            </div>
        </div>
    </div>
</div>

@once
<style>
    .attendance-setup-nav {
        display: flex;
        flex-direction: column;
        gap: 12px;
        padding: 14px;
        border: 1px solid #dce5df;
        border-radius: 18px;
        background: #fff;
        box-shadow: 0 10px 24px rgba(17, 36, 24, .045);
    }
    .attendance-setup-nav-head {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    .attendance-setup-nav-badge {
        display: inline-flex;
        align-items: center;
        min-height: 34px;
        padding: 8px 14px;
        border-radius: 999px;
        background: #0f5c3f;
        color: #fff;
        font-size: 12px;
        font-weight: 900;
    }
    .attendance-setup-nav-copy {
        color: #62736a;
        font-size: 13px;
        font-weight: 650;
    }
    .attendance-setup-nav-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
        padding-top: 12px;
        border-top: 1px solid #dce5df;
    }
    .attendance-setup-nav-group {
        min-width: 0;
    }
    .attendance-setup-nav-label {
        margin-bottom: 8px;
        color: #62736a;
        font-size: 10.5px;
        font-weight: 900;
        letter-spacing: .16em;
        text-transform: uppercase;
    }
    .attendance-setup-nav-links {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    .attendance-setup-nav-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 36px;
        padding: 8px 13px;
        border: 1px solid #dce5df;
        border-radius: 10px;
        background: #fff;
        color: #0b3f2e;
        font-size: 12.5px;
        font-weight: 850;
        text-decoration: none;
        white-space: nowrap;
    }
    .attendance-setup-nav-link.is-active {
        background: #0f5c3f;
        border-color: #0f5c3f;
        color: #fff;
        box-shadow: 0 10px 20px rgba(15, 92, 63, .16);
    }
    @media (max-width: 900px) {
        .attendance-setup-nav-grid {
            display: flex;
            gap: 14px;
            overflow-x: auto;
            padding-bottom: 4px;
        }
        .attendance-setup-nav-group {
            min-width: 220px;
        }
    }
</style>
@endonce

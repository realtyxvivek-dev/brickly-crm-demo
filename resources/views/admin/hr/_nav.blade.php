@php
    $hideHrSetupNav = auth()->check() && auth()->user()->isHrManager();
    $attendanceRouteBase = auth()->check() && auth()->user()->isHrManager()
        ? 'hr-manager.settings.attendance'
        : 'admin.attendance';
    $hrRouteBase = auth()->check() && auth()->user()->isHrManager()
        ? 'hr-manager.settings.hr'
        : 'admin.hr';
@endphp
@if(! $hideHrSetupNav)
@once
<style>
    .hr-setup-nav {
        background: rgba(255,255,255,.94);
        border: 1px solid #dce5df;
        border-radius: 18px;
        box-shadow: 0 10px 24px rgba(17, 36, 24, 0.045);
        padding: 12px;
        overflow-x: auto;
    }
    .hr-setup-nav-track {
        display: flex;
        gap: 10px;
        min-width: max-content;
    }
    .hr-setup-nav-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 38px;
        padding: 9px 16px;
        border: 1px solid #dce5df;
        border-radius: 11px;
        color: #0b3f2e;
        background: #fff;
        font-size: 13px;
        font-weight: 800;
        text-decoration: none;
        white-space: nowrap;
        transition: all .15s ease;
    }
    .hr-setup-nav-link:hover {
        color: #0f5c3f;
        border-color: rgba(15, 92, 63, .35);
        background: #fbfdfb;
    }
    .hr-setup-nav-link.is-active {
        color: #fff;
        background: #0f5c3f;
        border-color: #0f5c3f;
        box-shadow: 0 10px 22px rgba(15, 92, 63, .18);
    }
</style>
@endonce
<div class="hr-setup-nav">
    <div class="hr-setup-nav-track">
        <a href="{{ route($attendanceRouteBase . '.offices.index') }}" class="hr-setup-nav-link {{ request()->routeIs($attendanceRouteBase . '.*') ? 'is-active' : '' }}">Attendance Setup</a>
        <a href="{{ route($hrRouteBase . '.employees.index') }}" class="hr-setup-nav-link {{ request()->routeIs($hrRouteBase . '.employees.*') ? 'is-active' : '' }}">Employees</a>
        <a href="{{ route($hrRouteBase . '.salary-structures.index') }}" class="hr-setup-nav-link {{ request()->routeIs($hrRouteBase . '.salary-structures.*') ? 'is-active' : '' }}">Salary Rules</a>
        <a href="{{ route($hrRouteBase . '.salary-profiles.index') }}" class="hr-setup-nav-link {{ request()->routeIs($hrRouteBase . '.salary-profiles.*') ? 'is-active' : '' }}">Employee Salary</a>
        <a href="{{ route($hrRouteBase . '.deduction-heads.index') }}" class="hr-setup-nav-link {{ request()->routeIs($hrRouteBase . '.deduction-heads.*') ? 'is-active' : '' }}">Salary Heads</a>
        <a href="{{ route($hrRouteBase . '.payslip-settings.index') }}" class="hr-setup-nav-link {{ request()->routeIs($hrRouteBase . '.payslip-settings.*') ? 'is-active' : '' }}">Payslip Setup</a>
        <a href="{{ route($hrRouteBase . '.fraud-settings.index') }}" class="hr-setup-nav-link {{ request()->routeIs($hrRouteBase . '.fraud-settings.*') ? 'is-active' : '' }}">Photo Check</a>
    </div>
</div>
@endif

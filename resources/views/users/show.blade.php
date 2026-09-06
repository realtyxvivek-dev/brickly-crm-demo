@extends('layouts.app')

@section('title', 'User Profile - ' . brand_name())
@section('page-title', 'User Profile')

@push('styles')
<style>
    .user-profile-page {
        max-width: 1280px;
        margin: 0;
        color: #0f172a;
    }

    .user-profile-alert {
        border-radius: 18px;
        padding: 14px 16px;
        margin-bottom: 16px;
        font-size: 14px;
        font-weight: 700;
    }

    .user-profile-hero,
    .user-profile-card {
        border: 1px solid rgba(15, 23, 42, .07);
        background: rgba(255, 255, 255, .96);
        box-shadow: 0 18px 45px rgba(15, 23, 42, .06);
    }

    .user-profile-hero {
        position: relative;
        overflow: hidden;
        border-radius: 28px;
        padding: 26px;
        margin-bottom: 22px;
        background:
            radial-gradient(circle at 8% 0%, rgba(93, 202, 165, .18), transparent 32%),
            linear-gradient(135deg, #ffffff 0%, #f8fbf9 58%, #eef8f3 100%);
    }

    .user-profile-hero::after {
        content: '';
        position: absolute;
        right: -80px;
        top: -100px;
        width: 240px;
        height: 240px;
        border-radius: 999px;
        background: rgba(32, 90, 68, .08);
        pointer-events: none;
    }

    .user-profile-hero-main {
        position: relative;
        display: flex;
        align-items: flex-start;
        gap: 22px;
        z-index: 1;
    }

    .user-profile-avatar {
        width: 88px;
        height: 88px;
        border-radius: 28px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #e8ecff, #dbeafe);
        color: #4f46e5;
        font-size: 34px;
        font-weight: 900;
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .9), 0 14px 28px rgba(79, 70, 229, .12);
        flex: 0 0 auto;
    }

    .user-profile-name-row {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .user-profile-name {
        margin: 0;
        font-size: clamp(26px, 3vw, 36px);
        line-height: 1.05;
        letter-spacing: -0.04em;
        font-weight: 900;
        color: #0f172a;
    }

    .user-profile-status {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        border-radius: 999px;
        padding: 8px 12px;
        font-size: 12px;
        font-weight: 900;
    }

    .user-profile-status::before {
        content: '';
        width: 7px;
        height: 7px;
        border-radius: 999px;
        background: currentColor;
    }

    .user-profile-contact {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 14px;
    }

    .user-profile-contact-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        min-height: 36px;
        border-radius: 999px;
        border: 1px solid #e2e8f0;
        background: rgba(255,255,255,.82);
        padding: 8px 12px;
        color: #475569;
        font-size: 14px;
        font-weight: 700;
    }

    .user-profile-actions {
        position: relative;
        z-index: 1;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 24px;
        padding-top: 18px;
        border-top: 1px solid rgba(15, 23, 42, .07);
    }

    .user-profile-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-height: 40px;
        border-radius: 14px;
        padding: 10px 14px;
        font-size: 13px;
        font-weight: 900;
        text-decoration: none;
        border: 1px solid transparent;
        transition: transform .18s ease, box-shadow .18s ease, background .18s ease;
    }

    .user-profile-action:hover {
        transform: translateY(-1px);
        box-shadow: 0 10px 20px rgba(15, 23, 42, .08);
    }

    .user-profile-action.employee { background: #ecfdf5; color: #065f46; border-color: #bbf7d0; }
    .user-profile-action.edit { background: #f8fafc; color: #334155; border-color: #e2e8f0; }
    .user-profile-action.credentials { background: #fff7ed; color: #9a3412; border-color: #fed7aa; }
    .user-profile-action.reset { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
    .user-profile-action.login { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
    .user-profile-action.password { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
    .user-profile-action.remove { min-height: 34px; padding: 8px 11px; background: #fff1f2; color: #be123c; border-color: #fecdd3; }

    .user-profile-help {
        flex-basis: 100%;
        color: #64748b;
        font-size: 13px;
        line-height: 1.6;
    }

    .user-profile-modal-backdrop {
        position: fixed;
        inset: 0;
        z-index: 9998;
        display: none;
        align-items: center;
        justify-content: center;
        background: rgba(15, 23, 42, .48);
        padding: 18px;
    }

    .user-profile-modal-backdrop.is-open {
        display: flex;
    }

    .user-profile-modal {
        width: min(100%, 460px);
        border-radius: 24px;
        border: 1px solid rgba(15, 23, 42, .08);
        background: #fff;
        box-shadow: 0 28px 80px rgba(15, 23, 42, .28);
        overflow: hidden;
    }

    .user-profile-modal-head,
    .user-profile-modal-body {
        padding: 20px 22px;
    }

    .user-profile-modal-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 14px;
        border-bottom: 1px solid #eef2f0;
    }

    .user-profile-modal-title {
        margin: 0;
        color: #0f172a;
        font-size: 19px;
        font-weight: 900;
    }

    .user-profile-modal-copy {
        margin-top: 5px;
        color: #64748b;
        font-size: 13px;
        line-height: 1.5;
    }

    .user-profile-modal-close {
        border: 0;
        border-radius: 12px;
        background: #f8fafc;
        color: #64748b;
        padding: 8px 10px;
    }

    .user-profile-field {
        display: grid;
        gap: 7px;
        margin-bottom: 14px;
    }

    .user-profile-field label {
        color: #334155;
        font-size: 12px;
        font-weight: 900;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .user-profile-field input {
        width: 100%;
        min-height: 44px;
        border-radius: 14px;
        border: 1px solid #cbd5e1;
        padding: 10px 12px;
        color: #0f172a;
        font-size: 14px;
    }

    .user-profile-warning {
        border-radius: 16px;
        border: 1px solid #fed7aa;
        background: #fff7ed;
        color: #9a3412;
        padding: 12px 14px;
        font-size: 13px;
        line-height: 1.45;
        font-weight: 700;
    }

    .user-profile-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 18px;
        align-items: start;
    }

    .user-profile-card {
        border-radius: 24px;
        padding: 22px;
        min-height: 190px;
    }

    .user-profile-card h4 {
        margin: 0 0 18px;
        color: #0f172a;
        font-size: 18px;
        font-weight: 900;
        letter-spacing: -0.02em;
    }

    .user-profile-card dl {
        display: grid;
        gap: 14px;
    }

    .user-profile-card dt {
        color: #64748b;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .user-profile-card dd {
        margin-top: 4px;
        color: #0f172a;
        font-size: 14px;
        font-weight: 700;
    }

    .user-profile-sessions {
        margin-top: 22px;
        border-radius: 24px;
        overflow: hidden;
    }

    .user-profile-empty {
        border-radius: 18px;
        border: 1px dashed #cbd5e1;
        background: #f8fafc;
        padding: 18px;
        color: #64748b;
        font-size: 14px;
    }

    @media (max-width: 1100px) {
        .user-profile-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    @media (max-width: 720px) {
        .user-profile-hero { padding: 20px; border-radius: 22px; }
        .user-profile-hero-main { flex-direction: column; }
        .user-profile-grid { grid-template-columns: 1fr; }
    }
</style>
@endpush

@section('header-actions')
    @if(auth()->user()->isAdmin())
    <a href="{{ $user->employeeProfile ? route('admin.hr.employees.show', $user) : route('admin.hr.employees.create', ['user_id' => $user->id]) }}" class="px-4 py-2 bg-white border border-[#205A44] text-[#205A44] rounded-lg hover:bg-[#f0fdf4] transition-colors duration-200 text-sm font-medium">
        {{ $user->employeeProfile ? 'Open Employee Record' : 'Create Employee Profile' }}
    </a>
    @endif
    @if(!empty($canManageSessions) && auth()->user()->isAdmin())
    <a href="{{ route('admin.sessions.index') }}" class="px-4 py-2 bg-amber-100 text-amber-900 rounded-lg hover:bg-amber-200 transition-colors duration-200 text-sm font-medium">
        Global Sessions
    </a>
    @endif
    <a href="{{ route('users.edit', $user) }}" class="px-4 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg hover:from-[#205A44] hover:to-[#15803d] transition-colors duration-200 text-sm font-medium">
        Edit User
    </a>
    @if(auth()->user()->canManageUsers())
    <form action="{{ route('users.send-credentials-email', $user) }}" method="POST" class="inline ml-2" onsubmit="return confirm('Send email with a new temporary password to {{ $user->email }}?');">
        @csrf
        <button type="submit" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg transition-colors duration-200 text-sm font-medium">
            Send credentials email
        </button>
    </form>
    <form action="{{ route('users.send-password-reset-email', $user) }}" method="POST" class="inline ml-2" onsubmit="return confirm('Send password reset OTP email to {{ $user->email }}?');">
        @csrf
        <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors duration-200 text-sm font-medium">
            Send password reset email
        </button>
    </form>
    @endif
@endsection

@section('content')
    @php
        $canAddTeamMembers = auth()->user()->canManageUsers()
            && in_array($user->role?->slug, [
                \App\Models\Role::ADMIN,
                \App\Models\Role::CRM,
                \App\Models\Role::MARKETING_MANAGER,
                \App\Models\Role::SALES_MANAGER,
                \App\Models\Role::SENIOR_MANAGER,
                \App\Models\Role::ASSISTANT_SALES_MANAGER,
            ], true);
    @endphp
    <div class="user-profile-page">
        @if(session('success'))
            <div class="user-profile-alert bg-green-50 text-green-800 border border-green-200">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="user-profile-alert bg-red-50 text-red-800 border border-red-200">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="user-profile-alert bg-red-50 text-red-800 border border-red-200">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <!-- User Profile Card -->
        <div class="user-profile-hero">
            <div class="user-profile-hero-main">
                <!-- Avatar -->
                <div class="user-profile-avatar">
                    {{ strtoupper(substr($user->name, 0, 1)) }}
                </div>

                <!-- User Info -->
                <div class="flex-1">
                    <div class="user-profile-name-row">
                        <h3 class="user-profile-name">{{ $user->name }}</h3>
                        @if($user->is_active)
                            <span class="user-profile-status bg-green-100 text-green-800">Active</span>
                        @else
                            <span class="user-profile-status bg-red-100 text-red-800">Inactive</span>
                        @endif
                    </div>
                    <div class="user-profile-contact">
                        <div class="user-profile-contact-pill">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            <span>{{ $user->email }}</span>
                        </div>
                        @if($user->phone)
                            <div class="user-profile-contact-pill">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                </svg>
                                <span>{{ $user->phone }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            @if(auth()->user()->canManageUsers())
            <div class="user-profile-actions">
                @if(auth()->user()->isAdmin())
                <a href="{{ $user->employeeProfile ? route('admin.hr.employees.show', $user) : route('admin.hr.employees.create', ['user_id' => $user->id]) }}" class="user-profile-action employee">
                    <i class="fas fa-id-card"></i>
                    {{ $user->employeeProfile ? 'Open employee record' : 'Create employee record' }}
                </a>
                @endif
                <a href="{{ route('users.edit', $user) }}" class="user-profile-action edit">
                    <i class="fas fa-pen"></i>
                    Edit user details
                </a>
                @if(auth()->user()->isAdmin() && auth()->id() !== $user->id)
                    <a href="{{ route('impersonate.start', $user) }}"
                       class="user-profile-action login"
                       onclick="return confirm('Direct login as {{ addslashes($user->name) }}? Aap admin account se temporarily is user ke account me switch ho jayenge.');">
                        <i class="fas fa-right-to-bracket"></i>
                        Direct login
                    </a>
                @endif
                <form action="{{ route('users.send-credentials-email', $user) }}" method="POST" class="inline-flex" onsubmit="return confirm('Send email with a new temporary password to {{ $user->email }}?');">
                    @csrf
                    <button type="submit" class="user-profile-action credentials">
                        <i class="fas fa-key"></i>
                        Send credentials email
                    </button>
                </form>
                <form action="{{ route('users.send-password-reset-email', $user) }}" method="POST" class="inline-flex" onsubmit="return confirm('Send password reset OTP email to {{ $user->email }}?');">
                    @csrf
                    <button type="submit" class="user-profile-action reset">
                        <i class="fas fa-envelope"></i>
                        Send password reset email
                    </button>
                </form>
                @if(auth()->user()->isAdmin())
                    <button type="button" class="user-profile-action password" data-open-password-modal>
                        <i class="fas fa-lock"></i>
                        Update password
                    </button>
                @endif
                <span class="user-profile-help">Admin can direct login for support, resend credentials, send a reset email, or directly update the password. Direct password update logs out active sessions for security.</span>
            </div>
            @endif
        </div>

        <!-- Details Grid -->
        <div class="user-profile-grid">
            <!-- Role Information -->
            <div class="user-profile-card">
                <h4>Role Information</h4>
                <dl>
                    <div>
                        <dt>Role</dt>
                        <dd>
                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                {{ $user->getDisplayRoleName() }}
                            </span>
                        </dd>
                    </div>
                    @if($user->manager)
                        <div>
                            <dt>Manager</dt>
                            <dd>{{ $user->manager->name }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            <!-- Account Information -->
            <div class="user-profile-card">
                <h4>Account Information</h4>
                <dl>
                    <div>
                        <dt>Created At</dt>
                        <dd>{{ $user->created_at->format('M d, Y h:i A') }}</dd>
                    </div>
                    <div>
                        <dt>Last Updated</dt>
                        <dd>{{ $user->updated_at->format('M d, Y h:i A') }}</dd>
                    </div>
                </dl>
            </div>

            @if($user->canUseAdvisorPublicProfile())
            <div class="user-profile-card">
                <h4>Public Profile</h4>
                <dl>
                    <div>
                        <dt>Completion</dt>
                        <dd>{{ $user->advisorPublicProfile->completion_percentage ?? 0 }}%</dd>
                    </div>
                    <div>
                        <dt>Visibility Request</dt>
                        <dd>{{ ($user->advisorPublicProfile && $user->advisorPublicProfile->is_public) ? 'Requested' : 'Draft' }}</dd>
                    </div>
                    <div>
                        <dt>Approval</dt>
                        <dd>{{ ($user->advisorPublicProfile && $user->advisorPublicProfile->is_approved) ? 'Approved' : 'Pending' }}</dd>
                    </div>
                    @if($user->advisorPublicProfile)
                    <div>
                        <dt>Slug</dt>
                        <dd>{{ $user->advisorPublicProfile->public_slug }}</dd>
                    </div>
                    @endif
                </dl>
            </div>
            @endif
        </div>

        <!-- Team Members (if manager) -->
        @if($user->teamMembers->count() > 0 || $canAddTeamMembers)
            <div class="user-profile-card mt-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-4">
                    <h4 class="mb-0">Team Members</h4>
                    @if($canAddTeamMembers)
                        <button type="button" class="user-profile-action employee" data-open-team-member-modal>
                            <i class="fas fa-user-plus"></i>
                            Add team member
                        </button>
                    @endif
                </div>
                @if($user->teamMembers->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                @if($canAddTeamMembers)
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Action</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($user->teamMembers as $member)
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">{{ $member->name }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{{ $member->email }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                            {{ $member->getDisplayRoleName() }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        @if($member->is_active)
                                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                Active
                                            </span>
                                        @else
                                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                Inactive
                                            </span>
                                        @endif
                                    </td>
                                    @if($canAddTeamMembers)
                                        <td class="px-4 py-3 whitespace-nowrap text-right">
                                            <form action="{{ route('users.team-members.detach', [$user, $member]) }}" method="POST" class="inline-flex" onsubmit="return confirm('{{ addslashes($member->name) }} ko {{ addslashes($user->name) }} ki team se remove karna hai?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="user-profile-action remove">
                                                    <i class="fas fa-user-minus"></i>
                                                    Remove
                                                </button>
                                            </form>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                    <div class="user-profile-empty">
                        No team members assigned yet.
                    </div>
                @endif
            </div>
        @endif

        @if(!empty($canManageSessions))
            <div id="active-sessions" class="user-profile-card user-profile-sessions">
                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between mb-4">
                    <div>
                        <h4>Active Sessions</h4>
                        <p class="text-sm text-gray-500 mt-1">
                            {{ $userSessions->count() }} active session(s) based on configured session lifetime.
                        </p>
                    </div>
                    @if(auth()->id() !== $user->id || $userSessions->count() > 1)
                        <form action="{{ route('admin.sessions.users.revoke', $user) }}" method="POST" onsubmit="return confirm('Logout all active sessions for {{ addslashes($user->name) }}?');">
                            @csrf
                            <button type="submit" class="px-4 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-medium">
                                Logout all sessions
                            </button>
                        </form>
                    @endif
                </div>

                @if($userSessions->isEmpty())
                    <div class="user-profile-empty">
                        No active database-backed sessions found for this user.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Device</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Activity</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($userSessions as $session)
                                    <tr>
                                        <td class="px-4 py-3 text-sm text-gray-900">
                                            <div class="font-medium">{{ $session->device_label }}</div>
                                            @if($session->is_impersonating)
                                                <div class="text-xs text-amber-700 mt-1">Impersonation flow active in this session</div>
                                            @endif
                                            <div class="text-xs text-gray-500 mt-1 break-all">{{ $session->user_agent ?: 'User agent unavailable' }}</div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">{{ $session->ip_address }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">{{ $session->last_activity_at->format('d M Y h:i A') }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm">
                                            @if($session->is_current)
                                                <span class="inline-flex px-2 py-1 rounded-full bg-emerald-100 text-emerald-800 text-xs font-semibold">Current admin session</span>
                                            @else
                                                <span class="inline-flex px-2 py-1 rounded-full bg-slate-100 text-slate-700 text-xs font-semibold">Active</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endif

        <!-- Back Button -->
        <div class="mt-6">
            <a href="{{ route('users.index') }}" 
               class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors duration-200 font-medium">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Back to Users List
            </a>
        </div>
    </div>

    @if(auth()->user()->isAdmin())
        <div id="adminPasswordModal" class="user-profile-modal-backdrop" aria-hidden="true">
            <div class="user-profile-modal" role="dialog" aria-modal="true" aria-labelledby="adminPasswordModalTitle">
                <div class="user-profile-modal-head">
                    <div>
                        <h3 id="adminPasswordModalTitle" class="user-profile-modal-title">Update password</h3>
                        <p class="user-profile-modal-copy">Set a new password for {{ $user->name }}. Active sessions will be logged out after update.</p>
                    </div>
                    <button type="button" class="user-profile-modal-close" data-close-password-modal aria-label="Close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form action="{{ route('users.update-password', $user) }}" method="POST" class="user-profile-modal-body" onsubmit="return confirm('Update password and logout active sessions for {{ addslashes($user->email) }}?');">
                    @csrf
                    <div class="user-profile-field">
                        <label for="adminNewPassword">New password</label>
                        <input id="adminNewPassword" type="password" name="password" minlength="8" autocomplete="new-password" required>
                    </div>
                    <div class="user-profile-field">
                        <label for="adminNewPasswordConfirm">Confirm password</label>
                        <input id="adminNewPasswordConfirm" type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required>
                    </div>
                    <div class="user-profile-warning">
                        <i class="fas fa-triangle-exclamation mr-1"></i>
                        Password update ke baad is user ki active sessions logout ho jayengi. User ko new password se login karna hoga.
                    </div>
                    <div class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <button type="button" class="user-profile-action edit" data-close-password-modal>Cancel</button>
                        <button type="submit" class="user-profile-action password">
                            <i class="fas fa-lock"></i>
                            Update password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if($canAddTeamMembers)
        <div id="teamMemberModal" class="user-profile-modal-backdrop" aria-hidden="true">
            <div class="user-profile-modal" role="dialog" aria-modal="true" aria-labelledby="teamMemberModalTitle">
                <div class="user-profile-modal-head">
                    <div>
                        <h3 id="teamMemberModalTitle" class="user-profile-modal-title">Add team member</h3>
                        <p class="user-profile-modal-copy">Old users me se select karke {{ $user->name }} ki team me add karein.</p>
                    </div>
                    <button type="button" class="user-profile-modal-close" data-close-team-member-modal aria-label="Close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <form action="{{ route('users.team-members.attach', $user) }}" method="POST" class="user-profile-modal-body">
                    @csrf
                    @if($assignableTeamMembers->isNotEmpty())
                        <div class="user-profile-field">
                            <label for="teamMemberSelect">Select old user</label>
                            <select id="teamMemberSelect" name="member_id" class="w-full min-h-[44px] rounded-[14px] border border-slate-300 px-3 py-2 text-sm text-slate-900" required>
                                <option value="">Select user...</option>
                                @foreach($assignableTeamMembers as $candidate)
                                    <option value="{{ $candidate->id }}">
                                        {{ $candidate->name }} - {{ $candidate->getDisplayRoleName() }}
                                        @if($candidate->manager)
                                            (Current: {{ $candidate->manager->name }})
                                        @else
                                            (No manager)
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="user-profile-warning">
                            Selected user ka current manager replace hokar {{ $user->name }} ho jayega.
                        </div>
                    @else
                        <div class="user-profile-empty">
                            Is manager ke liye koi eligible old user available nahi hai.
                        </div>
                    @endif

                    <div class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <button type="button" class="user-profile-action edit" data-close-team-member-modal>Cancel</button>
                        @if($assignableTeamMembers->isNotEmpty())
                            <button type="submit" class="user-profile-action employee">
                                <i class="fas fa-user-plus"></i>
                                Add to team
                            </button>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('adminPasswordModal');
        const openButtons = document.querySelectorAll('[data-open-password-modal]');
        const closeButtons = document.querySelectorAll('[data-close-password-modal]');
        const firstInput = document.getElementById('adminNewPassword');

        function openPasswordModal() {
            if (!modal) return;
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            setTimeout(() => firstInput?.focus(), 50);
        }

        function closePasswordModal() {
            if (!modal) return;
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
        }

        openButtons.forEach((button) => button.addEventListener('click', openPasswordModal));
        closeButtons.forEach((button) => button.addEventListener('click', closePasswordModal));
        modal?.addEventListener('click', function (event) {
            if (event.target === modal) closePasswordModal();
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') closePasswordModal();
        });

        const teamMemberModal = document.getElementById('teamMemberModal');
        const teamMemberSelect = document.getElementById('teamMemberSelect');
        const openTeamMemberButtons = document.querySelectorAll('[data-open-team-member-modal]');
        const closeTeamMemberButtons = document.querySelectorAll('[data-close-team-member-modal]');

        function openTeamMemberModal() {
            if (!teamMemberModal) return;
            teamMemberModal.classList.add('is-open');
            teamMemberModal.setAttribute('aria-hidden', 'false');
            setTimeout(() => teamMemberSelect?.focus(), 50);
        }

        function closeTeamMemberModal() {
            if (!teamMemberModal) return;
            teamMemberModal.classList.remove('is-open');
            teamMemberModal.setAttribute('aria-hidden', 'true');
        }

        openTeamMemberButtons.forEach((button) => button.addEventListener('click', openTeamMemberModal));
        closeTeamMemberButtons.forEach((button) => button.addEventListener('click', closeTeamMemberModal));
        teamMemberModal?.addEventListener('click', function (event) {
            if (event.target === teamMemberModal) closeTeamMemberModal();
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') closeTeamMemberModal();
        });
    });
</script>
@endpush

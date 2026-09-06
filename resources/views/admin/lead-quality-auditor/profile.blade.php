@extends('layouts.app')

@section('title', 'Profile')
@section('page-title', 'Profile')

@push('styles')
<style>
    .auditor-profile {
        max-width: 980px;
        margin: 0 auto;
        color: #062f20;
    }
    .profile-sheet {
        border: 1px solid #b7d2c3;
        background: #fff;
        box-shadow: 0 8px 24px rgba(6, 58, 28, .06);
    }
    .profile-sheet__title {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 20px 24px;
        border-bottom: 1px solid #b7d2c3;
        background: #edf7f0;
    }
    .profile-avatar {
        width: 58px;
        height: 58px;
        flex: 0 0 58px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: #0b5a3c;
        color: #fff;
        font-size: 22px;
        font-weight: 800;
        overflow: hidden;
    }
    .profile-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .profile-sheet h2 { margin: 0; font-size: 22px; font-weight: 800; }
    .profile-sheet__role { margin-top: 4px; color: #5d7469; font-size: 13px; }
    .profile-grid {
        display: grid;
        grid-template-columns: 180px minmax(0, 1fr);
    }
    .profile-label, .profile-value {
        min-height: 58px;
        padding: 17px 20px;
        border-bottom: 1px solid #d7e4dc;
    }
    .profile-label {
        border-right: 1px solid #d7e4dc;
        background: #f5faf7;
        color: #476257;
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
    }
    .profile-value {
        min-width: 0;
        color: #102b21;
        font-size: 15px;
        font-weight: 650;
        overflow-wrap: anywhere;
    }
    .profile-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        padding: 20px 24px;
    }
    .profile-action {
        min-height: 42px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 9px;
        padding: 10px 16px;
        border: 1px solid #8db8a0;
        border-radius: 6px;
        background: #fff;
        color: #07583a;
        font-size: 14px;
        font-weight: 750;
        text-decoration: none;
    }
    .profile-action:hover { background: #edf7f0; }
    .profile-action--primary { background: #0b5a3c; color: #fff; border-color: #0b5a3c; }
    .profile-action--primary:hover { background: #08472f; }
    @media (max-width: 640px) {
        .profile-sheet__title { padding: 16px; }
        .profile-grid { grid-template-columns: 1fr; }
        .profile-label { min-height: auto; padding: 12px 16px 4px; border-right: 0; border-bottom: 0; }
        .profile-value { padding: 5px 16px 13px; }
        .profile-actions { padding: 16px; }
        .profile-action { flex: 1 1 100%; }
    }
</style>
@endpush

@section('content')
<div class="auditor-profile">
    <section class="profile-sheet" aria-labelledby="auditorProfileTitle">
        <header class="profile-sheet__title">
            <div class="profile-avatar" aria-hidden="true">
                @if($user->profile_picture_url)
                    <img src="{{ $user->profile_picture_url }}" alt="">
                @else
                    {{ strtoupper(substr($user->name ?: 'U', 0, 1)) }}
                @endif
            </div>
            <div>
                <h2 id="auditorProfileTitle">{{ $user->name }}</h2>
                <div class="profile-sheet__role">{{ $user->getDisplayRoleName() ?: 'Lead Quality Auditor' }}</div>
            </div>
        </header>

        <div class="profile-grid">
            <div class="profile-label">Name</div>
            <div class="profile-value">{{ $user->name }}</div>
            <div class="profile-label">Email</div>
            <div class="profile-value">{{ $user->email ?: 'Not set' }}</div>
            <div class="profile-label">Phone</div>
            <div class="profile-value">{{ $user->phone ?: 'Not set' }}</div>
            <div class="profile-label">Role</div>
            <div class="profile-value">{{ $user->getDisplayRoleName() ?: 'Lead Quality Auditor' }}</div>
            <div class="profile-label">Account Since</div>
            <div class="profile-value">{{ $user->created_at?->format('d M Y') ?: '-' }}</div>
        </div>

        <footer class="profile-actions">
            <a href="{{ route('account.password.edit') }}" class="profile-action profile-action--primary">
                <i class="fas fa-key"></i>
                Change Password
            </a>
            <a href="{{ route('attendance.leaves') }}" class="profile-action">
                <i class="fas fa-calendar-check"></i>
                My Attendance
            </a>
            <a href="{{ route('logout.get') }}" class="profile-action">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </footer>
    </section>
</div>
@endsection

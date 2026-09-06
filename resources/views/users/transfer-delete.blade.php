@extends('layouts.app')

@section('title', 'Transfer Leads Before Delete - ' . brand_name())
@section('page-title', 'Transfer & Delete User')

@push('styles')
<style>
.td-shell { max-width:980px; margin:0 auto; display:grid; gap:22px; }
.td-card {
    background:#fff; border:1px solid #e5e7eb; border-radius:18px; padding:24px 26px;
    box-shadow:0 10px 30px rgba(15, 23, 42, 0.06);
}
.td-hero {
    display:flex; justify-content:space-between; gap:24px; align-items:flex-start; flex-wrap:wrap;
}
.td-eyebrow {
    display:inline-flex; align-items:center; gap:8px; border-radius:999px; padding:6px 12px;
    background:#ecfdf5; color:#065f46; font-size:12px; font-weight:700; letter-spacing:.04em; text-transform:uppercase;
}
.td-title { font-size:32px; line-height:1.1; color:#111827; font-weight:800; margin:16px 0 8px; }
.td-subtitle { max-width:640px; color:#6b7280; font-size:14px; line-height:1.7; }
.td-user-chip {
    min-width:240px; background:linear-gradient(135deg,#063A1C,#205A44); color:#fff;
    border-radius:18px; padding:18px 20px;
}
.td-user-chip strong { display:block; font-size:22px; margin-bottom:4px; }
.td-grid { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:16px; }
.td-stat {
    background:#f8fafc; border:1px solid #e5e7eb; border-radius:16px; padding:18px;
}
.td-stat-label { font-size:12px; text-transform:uppercase; letter-spacing:.06em; color:#6b7280; font-weight:700; }
.td-stat-value { font-size:32px; font-weight:800; color:#111827; margin-top:8px; }
.td-form-grid { display:grid; grid-template-columns:2fr 1fr; gap:16px; align-items:end; }
.td-label { display:block; font-size:13px; font-weight:700; color:#374151; margin-bottom:8px; }
.td-select {
    width:100%; border:1.5px solid #d1d5db; border-radius:12px; padding:13px 14px;
    background:#fff; color:#111827; font-size:14px; outline:none;
}
.td-select:focus { border-color:#205A44; box-shadow:0 0 0 3px rgba(32,90,68,.12); }
.td-actions { display:flex; gap:12px; flex-wrap:wrap; }
.td-btn {
    display:inline-flex; align-items:center; justify-content:center; gap:8px; border-radius:12px;
    padding:13px 18px; font-size:14px; font-weight:700; text-decoration:none; border:none; cursor:pointer;
}
.td-btn-primary { background:linear-gradient(135deg,#063A1C,#205A44); color:#fff; }
.td-btn-secondary { background:#f3f4f6; color:#374151; }
.td-note {
    margin-top:14px; padding:14px 16px; border-radius:14px; border:1px solid #fde68a;
    background:#fffbeb; color:#92400e; font-size:13px; line-height:1.6;
}
@media(max-width: 860px) {
    .td-grid, .td-form-grid { grid-template-columns:1fr; }
}
</style>
@endpush

@section('content')
<div class="td-shell">
    @if(session('error'))
    <div class="td-card" style="background:#fef2f2;border-color:#fecaca;color:#991b1b;">
        {{ session('error') }}
    </div>
    @endif

    <div class="td-card">
        <div class="td-hero">
            <div>
                <span class="td-eyebrow"><i class="fas fa-right-left"></i> Pre-delete transfer</span>
                <h1 class="td-title">Transfer active leads before deleting {{ $userToDelete->name }}</h1>
                <p class="td-subtitle">
                    Is user ke active leads aur open lead-related tasks ko pehle kisi aur eligible user ko transfer karna zaroori hai.
                    History preserve rahegi, lekin delete tabhi hoga jab transfer successfully complete ho.
                </p>
            </div>
            <div class="td-user-chip">
                <strong>{{ $userToDelete->name }}</strong>
                <div>{{ $userToDelete->getDisplayRoleName() }}</div>
                <div style="opacity:.85; margin-top:8px;">{{ $userToDelete->email }}</div>
                <div style="opacity:.85;">{{ $userToDelete->phone ?: 'No phone' }}</div>
            </div>
        </div>
    </div>

    <div class="td-grid">
        <div class="td-stat">
            <div class="td-stat-label">Active Leads</div>
            <div class="td-stat-value">{{ $transferPreview['active_lead_count'] }}</div>
        </div>
        <div class="td-stat">
            <div class="td-stat-label">Open Manager Tasks</div>
            <div class="td-stat-value">{{ $transferPreview['open_task_count'] }}</div>
        </div>
        <div class="td-stat">
            <div class="td-stat-label">Open Telecaller Tasks</div>
            <div class="td-stat-value">{{ $transferPreview['open_telecaller_task_count'] }}</div>
        </div>
    </div>

    <div class="td-card">
        <form method="POST" action="{{ route('users.transfer-delete.store', $userToDelete) }}">
            @csrf
            <div class="td-form-grid">
                <div>
                    <label for="replacement_user_id" class="td-label">Transfer all active leads to</label>
                    <select id="replacement_user_id" name="replacement_user_id" class="td-select" required>
                        <option value="">Select replacement user</option>
                        @foreach($replacementUsers as $replacementUser)
                            <option value="{{ $replacementUser->id }}" {{ old('replacement_user_id') == $replacementUser->id ? 'selected' : '' }}>
                                {{ $replacementUser->name }} • {{ $replacementUser->getDisplayRoleName() }}
                            </option>
                        @endforeach
                    </select>
                    @error('replacement_user_id')
                    <div style="margin-top:8px;color:#b91c1c;font-size:12px;">{{ $message }}</div>
                    @enderror
                    @if($replacementUsers->isEmpty())
                    <div style="margin-top:8px;color:#b91c1c;font-size:12px;">
                        No eligible active replacement users are available right now.
                    </div>
                    @endif
                </div>
                <div class="td-actions">
                    <button
                        type="submit"
                        class="td-btn td-btn-primary"
                        {{ $replacementUsers->isEmpty() ? 'disabled' : '' }}
                        onclick="return confirm('Transfer all active leads from {{ addslashes($userToDelete->name) }} and then delete this user?');">
                        <i class="fas fa-user-slash"></i> Transfer & Delete
                    </button>
                    <a href="{{ route('users.index') }}" class="td-btn td-btn-secondary">
                        <i class="fas fa-arrow-left"></i> Cancel
                    </a>
                </div>
            </div>
        </form>

        <div class="td-note">
            Replacement user ko current active leads ka ownership mil jayega. Existing active assignment records inactive ho jayenge,
            naya active assignment create hoga, aur open lead-related tasks bhi reassigned user par move ho jayenge.
        </div>
    </div>
</div>
@endsection

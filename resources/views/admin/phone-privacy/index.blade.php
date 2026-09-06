@extends('layouts.app')

@section('title', 'Phone Privacy & Dialer')
@section('page-title', 'Phone Privacy & Dialer')

@push('styles')
<style>
    .pp-shell { display:flex; flex-direction:column; gap:16px; }
    .pp-panel { background:#fff; border:1px solid #e2e8f0; border-radius:8px; box-shadow:0 4px 18px rgba(15,23,42,.05); }
    .pp-head { padding:18px 20px; display:flex; align-items:flex-start; justify-content:space-between; gap:16px; }
    .pp-title { margin:0; font-size:20px; font-weight:800; color:#0f172a; }
    .pp-copy { margin-top:5px; color:#64748b; font-size:13px; }
    .pp-status { display:inline-flex; align-items:center; gap:7px; padding:7px 10px; border-radius:999px; background:#ecfdf5; color:#047857; font-size:11px; font-weight:800; white-space:nowrap; }
    .pp-toolbar { padding:14px 20px; border-top:1px solid #eef2f7; display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
    .pp-input,.pp-select { min-height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:8px 11px; background:#fff; color:#0f172a; font:inherit; font-size:13px; }
    .pp-input { flex:1 1 240px; }
    .pp-btn { min-height:40px; border:0; border-radius:8px; padding:9px 14px; font:inherit; font-size:12px; font-weight:800; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; gap:7px; }
    .pp-btn.primary { background:#075b38; color:#fff; }
    .pp-btn.secondary { background:#f1f5f9; color:#334155; border:1px solid #cbd5e1; text-decoration:none; }
    .pp-table-wrap { overflow-x:auto; }
    .pp-table { width:100%; border-collapse:collapse; min-width:1050px; }
    .pp-table th { padding:11px 14px; background:#073f2b; color:#fff; text-align:left; font-size:10px; text-transform:uppercase; }
    .pp-table td { padding:13px 14px; border-bottom:1px solid #e5e7eb; vertical-align:middle; font-size:13px; }
    .pp-user { font-weight:800; color:#0f172a; }
    .pp-sub { margin-top:3px; color:#64748b; font-size:11px; }
    .pp-row-form { display:contents; }
    .pp-switch { display:inline-flex; align-items:center; gap:8px; font-weight:700; }
    .pp-switch input { width:18px; height:18px; accent-color:#075b38; }
    .pp-badge { display:inline-flex; padding:5px 8px; border-radius:999px; font-size:10px; font-weight:800; }
    .pp-badge.on { background:#dcfce7; color:#166534; }
    .pp-badge.off { background:#f1f5f9; color:#475569; }
    .pp-bulk { position:sticky; bottom:10px; padding:14px; display:flex; align-items:center; gap:10px; flex-wrap:wrap; border-top:1px solid #e2e8f0; background:rgba(255,255,255,.96); }
    .pp-bulk strong { margin-right:auto; }
    .pp-audits { padding:0 20px 18px; display:grid; gap:8px; }
    .pp-audit { display:grid; grid-template-columns:160px 1fr auto; gap:12px; padding:10px 0; border-bottom:1px solid #eef2f7; font-size:12px; }
    .pp-empty { padding:28px; text-align:center; color:#64748b; }
    @media(max-width:700px){ .pp-head{flex-direction:column}.pp-toolbar>*{width:100%}.pp-bulk>*{width:100%}.pp-audit{grid-template-columns:1fr}.pp-status{white-space:normal} }
</style>
@endpush

@section('content')
<div class="pp-shell">
    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-800">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">{{ $errors->first() }}</div>
    @endif

    <section class="pp-panel">
        <div class="pp-head">
            <div>
                <h1 class="pp-title">Customer Number Access</h1>
                <div class="pp-copy">Selected users ko masked number dikhega. Cloud Only aur WhatsApp API Only strict protection modes hain.</div>
            </div>
            <span class="pp-status"><i class="fas fa-shield-alt"></i> Admin controlled</span>
        </div>
        <form class="pp-toolbar" method="GET">
            <input class="pp-input" name="search" value="{{ $search }}" placeholder="Search name, email or employee phone">
            <select class="pp-select" name="role">
                <option value="">All roles</option>
                @foreach($roles as $roleOption)
                    <option value="{{ $roleOption->slug }}" @selected($role === $roleOption->slug)>{{ $roleOption->name }}</option>
                @endforeach
            </select>
            <button class="pp-btn primary" type="submit"><i class="fas fa-search"></i> Filter</button>
            <a class="pp-btn secondary" href="{{ route('admin.phone-privacy.index') }}">Clear</a>
        </form>
    </section>

    <section class="pp-panel">
        <form method="POST" action="{{ route('admin.phone-privacy.bulk.update') }}" id="privacyBulkForm">
            @csrf
            @method('PUT')
            <div class="pp-table-wrap">
                <table class="pp-table">
                    <thead><tr><th><input type="checkbox" id="selectAllPrivacyUsers" aria-label="Select all users"></th><th>User</th><th>Masking</th><th>Calling mode</th><th>WhatsApp mode</th><th>Last updated</th><th>Action</th></tr></thead>
                    <tbody>
                    @forelse($users as $user)
                        @php($setting = $user->phonePrivacySetting)
                        <tr>
                            <td><input type="checkbox" name="user_ids[]" value="{{ $user->id }}" class="privacy-user-check" form="privacyBulkForm" aria-label="Select {{ $user->name }}"></td>
                            <td><div class="pp-user">{{ $user->name }}</div><div class="pp-sub">{{ $user->role?->name }} | {{ $user->email }}</div></td>
                            <td><span class="pp-badge {{ $setting?->mask_enabled ? 'on' : 'off' }}">{{ $setting?->mask_enabled ? '98XXXX3210' : 'Full number' }}</span></td>
                            <td>{{ ($setting?->call_mode ?? 'cloud_preferred') === 'cloud_only' ? 'Cloud Only' : 'Cloud Preferred' }}</td>
                            <td>{{ ($setting?->whatsapp_mode ?? 'direct_allowed') === 'api_only' ? 'API Only' : 'Direct Allowed' }}</td>
                            <td><div>{{ $setting?->updated_at?->format('d M Y, h:i A') ?: 'Default policy' }}</div><div class="pp-sub">{{ $setting?->updatedBy?->name }}</div></td>
                            <td><button type="button" class="pp-btn primary" onclick="document.getElementById('policyDialog{{ $user->id }}').showModal()"><i class="fas fa-sliders-h"></i> Manage</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="pp-empty">No eligible users found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pp-bulk">
                <strong><span id="selectedPrivacyCount">0</span> selected</strong>
                <select class="pp-select" name="mask_enabled"><option value="1">Mask ON</option><option value="0">Mask OFF</option></select>
                <select class="pp-select" name="call_mode"><option value="cloud_preferred">Cloud Preferred</option><option value="cloud_only">Cloud Only</option></select>
                <select class="pp-select" name="whatsapp_mode"><option value="direct_allowed">Direct Allowed</option><option value="api_only">WhatsApp API Only</option></select>
                <button class="pp-btn primary" type="submit">Apply to selected</button>
            </div>
        </form>
        <div class="px-4 py-3">{{ $users->links() }}</div>
    </section>

    @foreach($users as $user)
        @php($setting = $user->phonePrivacySetting)
        <dialog id="policyDialog{{ $user->id }}" class="w-[min(520px,calc(100%-24px))] rounded-xl border-0 p-0 shadow-2xl backdrop:bg-slate-900/50">
            <form method="POST" action="{{ route('admin.phone-privacy.users.update', $user) }}" class="p-5">
                @csrf
                @method('PUT')
                <div class="flex items-start justify-between gap-4 border-b border-slate-200 pb-4">
                    <div><div class="text-lg font-extrabold text-slate-900">{{ $user->name }}</div><div class="text-xs text-slate-500">{{ $user->role?->name }} | {{ $user->email }}</div></div>
                    <button type="button" class="h-9 w-9 rounded-lg border border-slate-200 bg-white" onclick="this.closest('dialog').close()" aria-label="Close"><i class="fas fa-times"></i></button>
                </div>
                <div class="mt-5 grid gap-4">
                    <label class="pp-switch"><input type="hidden" name="mask_enabled" value="0"><input type="checkbox" name="mask_enabled" value="1" @checked($setting?->mask_enabled)> Mask customer numbers</label>
                    <label><span class="mb-1 block text-xs font-bold text-slate-600">Calling mode</span><select class="pp-select w-full" name="call_mode"><option value="cloud_preferred" @selected(($setting?->call_mode ?? 'cloud_preferred') === 'cloud_preferred')>Cloud Preferred - fallback allowed</option><option value="cloud_only" @selected($setting?->call_mode === 'cloud_only')>Cloud Only - no number reveal</option></select></label>
                    <label><span class="mb-1 block text-xs font-bold text-slate-600">WhatsApp mode</span><select class="pp-select w-full" name="whatsapp_mode"><option value="direct_allowed" @selected(($setting?->whatsapp_mode ?? 'direct_allowed') === 'direct_allowed')>Direct Allowed - number may reveal</option><option value="api_only" @selected($setting?->whatsapp_mode === 'api_only')>WhatsApp API Only - direct blocked</option></select></label>
                </div>
                <div class="mt-5 flex justify-end gap-2"><button type="button" class="pp-btn secondary" onclick="this.closest('dialog').close()">Cancel</button><button type="submit" class="pp-btn primary"><i class="fas fa-save"></i> Save policy</button></div>
            </form>
        </dialog>
    @endforeach

    <section class="pp-panel">
        <div class="pp-head"><div><h2 class="pp-title">Recent Privacy Audit</h2><div class="pp-copy">Policy changes and controlled number reveals.</div></div></div>
        <div class="pp-audits">
            @forelse($recentAudits as $audit)
                <div class="pp-audit"><span>{{ $audit->created_at?->format('d M Y, h:i A') }}</span><span><strong>{{ $audit->actor?->name ?: 'System' }}</strong> - {{ str_replace('_', ' ', $audit->action) }} @if($audit->targetUser) for {{ $audit->targetUser->name }} @endif @if($audit->lead) on lead #{{ $audit->lead->id }} @endif</span><span>{{ $audit->ip_address }}</span></div>
            @empty
                <div class="pp-empty">No privacy audit events yet.</div>
            @endforelse
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const all = document.getElementById('selectAllPrivacyUsers');
    const checks = [...document.querySelectorAll('.privacy-user-check')];
    const count = document.getElementById('selectedPrivacyCount');
    const sync = () => { count.textContent = checks.filter((item) => item.checked).length; };
    all?.addEventListener('change', () => { checks.forEach((item) => item.checked = all.checked); sync(); });
    checks.forEach((item) => item.addEventListener('change', sync));
    document.getElementById('privacyBulkForm')?.addEventListener('submit', (event) => {
        if (!checks.some((item) => item.checked)) { event.preventDefault(); alert('Select at least one user.'); }
    });
})();
</script>
@endpush

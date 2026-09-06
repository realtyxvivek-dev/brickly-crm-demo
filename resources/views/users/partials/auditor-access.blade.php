@if(auth()->user()->isAdmin())
@php
    $auditorAccess = app(\App\Services\LeadAuditorAccessService::class);
    $selectedAuditorUsers = array_map('intval', old('auditor_user_ids', old('auditor_access_present') ? [] : $auditorAccess->selectedIds($user)));
    $auditorCandidates = $auditorAccess->candidates()->with('role')->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $selectedAuditorUsers))->orderBy('name')->get();
@endphp
<style>
#auditorAccessPanel[hidden]{display:none!important}
#auditorAccessPanel button{border:1px solid #cbd9d0;border-radius:4px;background:#fff;color:#205a44;padding:6px 10px;font:inherit;cursor:pointer}
#auditorAccessPanel input[type=checkbox]{flex:0 0 16px;width:16px;height:16px;accent-color:#205a44}
#auditorAccessPanel [data-auditor-user]>span{min-width:0;overflow-wrap:anywhere;font-size:13px}
#auditorAccessPanel small{display:block;color:#64746b;font-size:11px}
#auditorSelectionCount{margin-left:auto;align-self:center;font-size:12px;color:#64746b}
#auditorUserEmpty{margin:0;padding:14px;color:#64746b;font-size:13px;text-align:center}
</style>
<fieldset id="auditorAccessPanel" class="uf-field" style="border:0;padding:0;min-width:0" @if($currentRoleSlug !== 'lead_quality_auditor') hidden disabled @endif>
    <legend class="uf-label">Allowed Sales Users</legend>
    <input type="hidden" name="auditor_access_present" value="1">
    <input type="search" id="auditorUserSearch" name="auditor_user_search" class="uf-input" placeholder="Search name, role or user ID" aria-label="Search allowed sales users" autocomplete="off" autocapitalize="none" spellcheck="false" readonly data-1p-ignore data-lpignore="true">
    <div style="display:flex;gap:8px;margin:8px 0">
        <button type="button" id="auditorSelectAll">Select All</button>
        <button type="button" id="auditorClearAll">Clear</button>
        <output id="auditorSelectionCount" aria-live="polite"></output>
    </div>
    <div style="max-height:240px;overflow:auto;border:1px solid #dce7df;border-radius:4px">
        @foreach($auditorCandidates as $candidate)
        <label data-auditor-user style="display:flex;align-items:center;gap:10px;padding:10px;border-bottom:1px solid #edf1ef">
            <input type="checkbox" name="auditor_user_ids[]" value="{{ $candidate->id }}" @checked(in_array($candidate->id, $selectedAuditorUsers))>
            <span>{{ $candidate->name }} <small>#{{ $candidate->id }} · {{ $candidate->role?->name }}{{ !$candidate->is_active ? ' · Inactive' : '' }}</small></span>
        </label>
        @endforeach
        <p id="auditorUserEmpty" hidden>No matching sales users found.</p>
    </div>
    @error('auditor_user_ids')<p role="alert" style="color:#b42318">{{ $message }}</p>@enderror
    @error('auditor_user_ids.*')<p role="alert" style="color:#b42318">{{ $message }}</p>@enderror
</fieldset>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const panel = document.getElementById('auditorAccessPanel');
    const rows = [...panel.querySelectorAll('[data-auditor-user]')];
    const search = document.getElementById('auditorUserSearch');
    const empty = document.getElementById('auditorUserEmpty');
    const refresh = () => document.getElementById('auditorSelectionCount').textContent = panel.querySelectorAll('input[type=checkbox]:checked').length + ' selected';
    const filter = () => {
        const term = search.value.trim().toLowerCase();
        let visible = 0;
        rows.forEach(row => {
            const matches = row.textContent.toLowerCase().includes(term);
            row.style.display = matches ? 'flex' : 'none';
            if (matches) visible++;
        });
        empty.hidden = visible !== 0;
    };
    const enableSearch = () => {
        search.readOnly = false;
        if (!search.dataset.enabled) {
            search.value = '';
            search.dataset.enabled = 'true';
            filter();
        }
    };
    search.value = '';
    search.addEventListener('focus', enableSearch);
    search.addEventListener('pointerdown', enableSearch);
    search.addEventListener('input', filter);
    filter();
    document.getElementById('auditorSelectAll').onclick = () => { rows.filter(row => row.style.display !== 'none').forEach(row => row.querySelector('input').checked = true); refresh(); };
    document.getElementById('auditorClearAll').onclick = () => { rows.forEach(row => row.querySelector('input').checked = false); refresh(); };
    panel.addEventListener('change', refresh);
    refresh();
});
</script>
@endpush
@endif

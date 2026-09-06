@extends('layouts.app')

@section('title', 'ASM CNP Automation')

@section('content')
<style>
    .cnp-grid { display:grid; gap:1.5rem; grid-template-columns: 1.2fr .8fr; }
    .cnp-card { background:#fff; border:1px solid #e5e7eb; border-radius:16px; box-shadow:0 10px 30px rgba(6,58,28,.06); }
    .cnp-card-header { padding:1.25rem 1.5rem; border-bottom:1px solid #eef2f7; display:flex; justify-content:space-between; align-items:center; }
    .cnp-card-body { padding:1.5rem; }
    .cnp-form-grid { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:1rem; }
    .cnp-field label { display:block; font-size:.875rem; font-weight:600; color:#0f172a; margin-bottom:.45rem; }
    .cnp-field input, .cnp-field select { width:100%; border:1px solid #cbd5e1; border-radius:12px; padding:.8rem .9rem; }
    .cnp-pill { display:inline-flex; align-items:center; gap:.4rem; border-radius:999px; padding:.35rem .7rem; font-size:.75rem; font-weight:700; }
    .cnp-pill.green { background:#dcfce7; color:#166534; }
    .cnp-pill.slate { background:#e2e8f0; color:#334155; }
    .cnp-user-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.75rem; }
    .cnp-check { border:1px solid #dbe4ee; border-radius:12px; padding:.8rem .9rem; display:flex; gap:.65rem; align-items:flex-start; }
    .cnp-table { width:100%; border-collapse:collapse; }
    .cnp-table th, .cnp-table td { text-align:left; padding:.75rem; border-bottom:1px solid #eef2f7; font-size:.875rem; vertical-align:top; }
    .cnp-transfer-note { display:block; margin-top:.3rem; font-size:.75rem; color:#475569; }
    .cnp-transfer-arrow { color:#16a34a; font-weight:700; }
    .cnp-actions { display:flex; justify-content:flex-end; gap:.75rem; margin-top:1.25rem; }
    .cnp-btn { border:none; border-radius:12px; padding:.85rem 1.1rem; font-weight:700; cursor:pointer; }
    .cnp-btn.primary { background:linear-gradient(135deg,#063A1C,#205A44); color:#fff; }
    .cnp-btn.secondary { background:#f8fafc; color:#334155; border:1px solid #dbe4ee; }
    .cnp-meta-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:1rem; }
    .cnp-stat { background:#f8fafc; border:1px solid #e2e8f0; border-radius:14px; padding:1rem; }
    .cnp-override-row { display:grid; grid-template-columns:1fr 1fr; gap:.75rem; margin-bottom:.75rem; }
    .cnp-section { border-top:1px solid #eef2f7; margin-top:1.5rem; padding-top:1.5rem; }
    .cnp-switches { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:1rem; margin-bottom:1rem; }
    .cnp-switch { border:1px solid #dbe4ee; border-radius:14px; padding:1rem; display:flex; gap:.75rem; align-items:flex-start; background:#fcfdfd; }
    .cnp-switch input { margin-top:.2rem; }
    .cnp-switch strong { display:block; color:#0f172a; }
    .cnp-switch span { display:block; font-size:.8rem; color:#64748b; line-height:1.45; }
    .cnp-quarantine-row { border:1px solid #e2e8f0; border-radius:14px; padding:1rem; margin-bottom:.85rem; background:#fff; }
    .cnp-history-chip { display:inline-flex; align-items:center; gap:.3rem; border-radius:999px; background:#f1f5f9; color:#334155; padding:.25rem .55rem; font-size:.72rem; font-weight:700; margin:.15rem .2rem .15rem 0; }
    .cnp-doc-card { background:linear-gradient(135deg,#f8fffb,#ffffff); border:1px solid #dbeee4; border-radius:18px; box-shadow:0 12px 34px rgba(6,58,28,.06); margin-bottom:1.5rem; overflow:hidden; }
    .cnp-doc-header { padding:1.15rem 1.35rem; display:flex; align-items:center; justify-content:space-between; gap:1rem; border-bottom:1px solid #e7f3ed; }
    .cnp-doc-body { padding:1.25rem 1.35rem; display:grid; grid-template-columns:1.05fr .95fr; gap:1rem; }
    .cnp-doc-flow { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.75rem; }
    .cnp-doc-step { border:1px solid #dbeee4; background:#fff; border-radius:14px; padding:.85rem; min-height:112px; }
    .cnp-doc-step-number { width:28px; height:28px; border-radius:999px; display:inline-flex; align-items:center; justify-content:center; background:#063A1C; color:#fff; font-weight:800; font-size:.8rem; margin-bottom:.55rem; }
    .cnp-doc-step strong { display:block; color:#0f172a; font-size:.88rem; margin-bottom:.25rem; }
    .cnp-doc-step span { display:block; color:#64748b; font-size:.78rem; line-height:1.45; }
    .cnp-doc-list { display:grid; gap:.6rem; }
    .cnp-doc-item { border:1px solid #e2e8f0; border-radius:12px; background:#fff; padding:.75rem .85rem; }
    .cnp-doc-item strong { color:#0f172a; display:block; font-size:.83rem; }
    .cnp-doc-item span { color:#64748b; display:block; font-size:.76rem; line-height:1.45; margin-top:.2rem; }
    .cnp-doc-example { margin-top:1rem; border:1px dashed #86efac; background:#f0fdf4; color:#14532d; border-radius:14px; padding:.85rem 1rem; font-size:.82rem; line-height:1.55; }
    @media (max-width: 960px) {
        .cnp-grid, .cnp-form-grid, .cnp-meta-grid, .cnp-user-grid, .cnp-override-row, .cnp-switches, .cnp-doc-body, .cnp-doc-flow { grid-template-columns:1fr; }
    }
</style>

@if(session('success'))
    <div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-green-800">{{ session('success') }}</div>
@endif

<div class="mb-6 flex items-start justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">ASM Fresh Lead CNP Automation</h1>
        <p class="mt-2 text-sm text-slate-500">Admin yahin decide karega: sirf total CNP par transfer karna hai, ya fixed time window ke andar CNP count dekhna hai.</p>
    </div>
    <span class="cnp-pill {{ $config->is_active ? 'green' : 'slate' }}">
        Retry Active · Auto Transfer {{ $config->is_active ? 'On' : 'Off' }}
    </span>
</div>

<div class="cnp-doc-card">
    <div class="cnp-doc-header">
        <div>
            <h2 class="text-lg font-semibold text-slate-900">How this automation works</h2>
            <p class="mt-1 text-sm text-slate-500">New admin ke liye simple guide: CNP, transfer, retry, pool aur quarantine ka meaning.</p>
        </div>
        <span class="cnp-pill green"><i class="fas fa-book-open"></i> Documentation</span>
    </div>
    <div class="cnp-doc-body">
        <div>
            <div class="cnp-doc-flow">
                <div class="cnp-doc-step">
                    <span class="cnp-doc-step-number">1</span>
                    <strong>Fresh lead ASM ko assign hoti hai</strong>
                    <span>Fresh/new lead jis ASM ko milti hai, uske phone-call task par CNP count track hota hai.</span>
                </div>
                <div class="cnp-doc-step">
                    <span class="cnp-doc-step-number">2</span>
                    <strong>ASM CNP mark karta hai</strong>
                    <span>CNP ka matlab customer ne phone pickup nahi kiya / contact nahi hua. Count same lead + same ASM par badhta hai.</span>
                </div>
                <div class="cnp-doc-step">
                    <span class="cnp-doc-step-number">3</span>
                    <strong>Max CNP hit par transfer</strong>
                    <span>Agar max CNP 4 hai, to 4th CNP ke baad lead next eligible ASM ko transfer hoti hai.</span>
                </div>
                <div class="cnp-doc-step">
                    <span class="cnp-doc-step-number">4</span>
                    <strong>Too many users fail = quarantine</strong>
                    <span>Agar configured unique users max CNP hit kar dete hain, lead unassigned quarantine me chali jati hai.</span>
                </div>
            </div>

            <div class="cnp-doc-example">
                <strong>Example:</strong>
                Max CNP = 4 and Quarantine after unique users = 4. User A ne 4 CNP kiya to lead User B ko jayegi. B, C ke baad agar User D bhi 4 CNP complete kar deta hai, lead next ASM ko nahi jayegi; quarantine/unassigned ho jayegi. Admin baad me yahin se Reactivate & Assign kar sakta hai.
            </div>
        </div>

        <div class="cnp-doc-list">
            <div class="cnp-doc-item">
                <strong>CNP retry flow</strong>
                <span>CNP marking, counting aur same-owner retry task hamesha active rahenge.</span>
            </div>
            <div class="cnp-doc-item">
                <strong>Allow transfers</strong>
                <span>ON hoga to max CNP ke baad lead next eligible ASM ko move hogi. OFF hoga to transfer stop rahega.</span>
            </div>
            <div class="cnp-doc-item">
                <strong>Create retry tasks below max</strong>
                <span>Max CNP se pehle system retry calling task create karega, taki ASM ko follow-up reminder milta rahe.</span>
            </div>
            <div class="cnp-doc-item">
                <strong>Transfer rule</strong>
                <span>Only CNP count = total CNP count dekhega. Time-window options = fixed hours ke andar CNP count check karega.</span>
            </div>
            <div class="cnp-doc-item">
                <strong>Eligible ASM Pool / Direct Mapping</strong>
                <span>Direct mapping ho to specific ASM ko transfer. Mapping na ho to selected ASM pool me round-robin se next user choose hota hai.</span>
            </div>
            <div class="cnp-doc-item">
                <strong>Quarantine</strong>
                <span>Repeated CNP across multiple ASMs ke baad lead active workflow se hata di jati hai. Lead delete nahi hoti; admin manually reactivate/assign kar sakta hai.</span>
            </div>
        </div>
    </div>
</div>

<div class="cnp-grid">
    <div class="cnp-card">
        <div class="cnp-card-header">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Automation Rules</h2>
                <p class="text-sm text-slate-500">Count-based transfer, retry behavior, pool aur direct mapping.</p>
            </div>
        </div>
        <div class="cnp-card-body">
            <form method="POST" action="{{ route('admin.automation.cnp.update') }}">
                @csrf
                <div class="cnp-switches">
                    <div class="cnp-switch">
                        <span>
                            <strong>CNP retry flow is always active</strong>
                            <span>CNP count aur selected-time retry task same owner ke liye continue rahenge.</span>
                        </span>
                    </div>
                    <label class="cnp-switch">
                        <input type="checkbox" name="is_active" value="1" {{ $config->is_active ? 'checked' : '' }}>
                        <span>
                            <strong>Allow transfers</strong>
                            <span>Enabled hone par max CNP hit karte hi lead next eligible ASM ko move hogi.</span>
                        </span>
                    </label>
                    <label class="cnp-switch">
                        <input type="checkbox" name="create_retry_tasks" value="1" {{ $config->create_retry_tasks ? 'checked' : '' }}>
                        <span>
                            <strong>Create retry tasks below max</strong>
                            <span>Max CNP limit hit hone se pehle auto retry calling task generate kare.</span>
                        </span>
                    </label>
                    <label class="cnp-switch">
                        <input type="checkbox" name="quarantine_enabled" value="1" {{ ($config->quarantine_enabled ?? true) ? 'checked' : '' }}>
                        <span>
                            <strong>Enable quarantine stop</strong>
                            <span>Configured unique users max CNP hit karne ke baad lead unassigned quarantine me jayegi.</span>
                        </span>
                    </label>
                    <div class="cnp-switch">
                        <span>
                            <strong>Admin decides transfer rule</strong>
                            <span>Example: 4 CNP anytime, ya 4 CNP within 24 hours. Window expire hone par restart ya reset rule bhi yahin control hoga.</span>
                        </span>
                    </div>
                </div>

                <div class="cnp-section">
                    <h3 class="text-sm font-semibold text-slate-900 mb-3">Rule Settings</h3>
                    <div class="cnp-form-grid">
                    <div class="cnp-field">
                        <label for="transfer_rule_mode">Transfer rule</label>
                        <select id="transfer_rule_mode" name="transfer_rule_mode">
                            <option value="count_only" {{ old('transfer_rule_mode', $config->transfer_rule_mode ?? 'count_only') === 'count_only' ? 'selected' : '' }}>
                                Only CNP count
                            </option>
                            <option value="count_window_restart" {{ old('transfer_rule_mode', $config->transfer_rule_mode) === 'count_window_restart' ? 'selected' : '' }}>
                                CNP within time window (restart on expiry)
                            </option>
                            <option value="count_window_reset" {{ old('transfer_rule_mode', $config->transfer_rule_mode) === 'count_window_reset' ? 'selected' : '' }}>
                                CNP within time window (reset to zero on expiry)
                            </option>
                        </select>
                    </div>
                    <div class="cnp-field">
                        <label for="retry_delay_minutes">Retry delay (minutes)</label>
                        <input id="retry_delay_minutes" type="number" name="retry_delay_minutes" min="5" value="{{ old('retry_delay_minutes', $config->retry_delay_minutes) }}">
                    </div>
                    <div class="cnp-field">
                        <label for="max_cnp_attempts">Max fresh-lead CNP attempts</label>
                        <input id="max_cnp_attempts" type="number" name="max_cnp_attempts" min="1" max="10" value="{{ old('max_cnp_attempts', $config->max_cnp_attempts) }}">
                    </div>
                    <div class="cnp-field">
                        <label for="quarantine_after_unique_users">Quarantine after unique users</label>
                        <input id="quarantine_after_unique_users" type="number" name="quarantine_after_unique_users" min="1" max="20" value="{{ old('quarantine_after_unique_users', $config->quarantine_after_unique_users ?? 4) }}">
                    </div>
                    <div class="cnp-field">
                        <label for="quarantine_action">Quarantine action</label>
                        <select id="quarantine_action" name="quarantine_action">
                            <option value="unassign" {{ old('quarantine_action', $config->quarantine_action ?? 'unassign') === 'unassign' ? 'selected' : '' }}>Unassign lead</option>
                        </select>
                    </div>
                    <div class="cnp-field" id="transfer-window-field">
                        <label for="transfer_window_hours">Time window (hours)</label>
                        <input id="transfer_window_hours" type="number" name="transfer_window_hours" min="1" max="720" value="{{ old('transfer_window_hours', $config->transfer_window_hours) }}">
                    </div>
                    <div class="cnp-field">
                        <label for="fallback_routing">Fallback routing</label>
                        <select id="fallback_routing" name="fallback_routing">
                            <option value="round_robin" {{ old('fallback_routing', $config->fallback_routing) === 'round_robin' ? 'selected' : '' }}>Round Robin</option>
                        </select>
                    </div>
                </div>
                </div>

                <div class="cnp-section">
                    <h3 class="text-sm font-semibold text-slate-900 mb-3">Eligible ASM Pool</h3>
                    <p class="text-xs text-slate-500 mb-3">Agar direct mapping na mile to lead in active ASM pool se next round-robin user ko jayegi.</p>
                    <div class="cnp-user-grid">
                        @foreach($asmUsers as $user)
                            <label class="cnp-check">
                                <input type="checkbox" name="pool_user_ids[]" value="{{ $user->id }}" {{ $config->poolUsers->contains('user_id', $user->id) ? 'checked' : '' }}>
                                <span>
                                    <span class="block font-semibold text-slate-900">{{ $user->name }}</span>
                                    <span class="block text-xs text-slate-500">{{ $user->email }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="cnp-section">
                    <h3 class="text-sm font-semibold text-slate-900 mb-3">Direct Transfer Mapping</h3>
                    <p class="text-xs text-slate-500 mb-3">Yahan define karo ki kisi specific ASM ki fresh lead max CNP ke baad kis ASM ko jaani chahiye.</p>
                    @php $existingOverrides = $config->overrides->values(); @endphp
                    @for($i = 0; $i < max(4, $existingOverrides->count()); $i++)
                        <div class="cnp-override-row">
                            <div class="cnp-field">
                                <label>From ASM</label>
                                <select name="overrides[{{ $i }}][from_user_id]">
                                    <option value="">Select user</option>
                                    @foreach($asmUsers as $user)
                                        <option value="{{ $user->id }}" {{ (string) old("overrides.$i.from_user_id", $existingOverrides[$i]->from_user_id ?? '') === (string) $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="cnp-field">
                                <label>Transfer to</label>
                                <select name="overrides[{{ $i }}][to_user_id]">
                                    <option value="">Select user</option>
                                    @foreach($asmUsers as $user)
                                        <option value="{{ $user->id }}" {{ (string) old("overrides.$i.to_user_id", $existingOverrides[$i]->to_user_id ?? '') === (string) $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    @endfor
                </div>

                <div class="cnp-actions">
                    <a href="{{ route('admin.automation.index') }}" class="cnp-btn secondary">Back</a>
                    <button type="submit" class="cnp-btn primary">Save CNP Automation</button>
                </div>
            </form>
        </div>
    </div>

    <div class="space-y-6">
        <div class="cnp-card">
            <div class="cnp-card-header">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Live Summary</h2>
                    <p class="text-sm text-slate-500">Current operational snapshot.</p>
                </div>
            </div>
            <div class="cnp-card-body">
                <div class="cnp-meta-grid">
                    <div class="cnp-stat">
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Retry Delay</div>
                        <div class="mt-2 text-2xl font-bold text-slate-900">{{ $config->retry_delay_minutes }}m</div>
                    </div>
                    <div class="cnp-stat">
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Transfer Mode</div>
                        <div class="mt-2 text-2xl font-bold text-slate-900">
                            @if(($config->transfer_rule_mode ?? 'count_only') === 'count_only')
                                Count
                            @elseif($config->transfer_rule_mode === 'count_window_restart')
                                Window Restart
                            @else
                                Window Reset
                            @endif
                        </div>
                    </div>
                    <div class="cnp-stat">
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Max CNP</div>
                        <div class="mt-2 text-2xl font-bold text-slate-900">{{ $config->max_cnp_attempts }}</div>
                    </div>
                    <div class="cnp-stat">
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Window</div>
                        <div class="mt-2 text-2xl font-bold text-slate-900">{{ $config->transfer_window_hours ? $config->transfer_window_hours . 'h' : 'Anytime' }}</div>
                    </div>
                    <div class="cnp-stat">
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pool Size</div>
                        <div class="mt-2 text-2xl font-bold text-slate-900">{{ $config->poolUsers->count() }}</div>
                    </div>
                    <div class="cnp-stat">
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Fallback</div>
                        <div class="mt-2 text-2xl font-bold text-slate-900">{{ \Illuminate\Support\Str::of($config->fallback_routing)->replace('_', ' ')->title() }}</div>
                    </div>
                    <div class="cnp-stat">
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Quarantine Limit</div>
                        <div class="mt-2 text-2xl font-bold text-slate-900">{{ $config->quarantine_after_unique_users ?? 4 }} users</div>
                    </div>
                    <div class="cnp-stat">
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Quarantined Leads</div>
                        <div class="mt-2 text-2xl font-bold text-rose-700">{{ $quarantinedLeads->count() }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="cnp-card">
            <div class="cnp-card-header">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Quarantined Leads</h2>
                    <p class="text-sm text-slate-500">Admin yahan se lead ko dobara assign/reactivate kar sakta hai.</p>
                </div>
            </div>
            <div class="cnp-card-body" style="max-height:420px; overflow:auto;">
                @forelse($quarantinedLeads as $lead)
                    <div class="cnp-quarantine-row">
                        <div class="flex flex-col gap-3">
                            <div>
                                <div class="font-semibold text-slate-900">{{ $lead->name }}</div>
                                <div class="text-xs text-slate-500">
                                    {{ $lead->phone ?: 'No phone' }} · Quarantined {{ optional($lead->cnp_quarantined_at)->format('d M Y, h:i A') }}
                                </div>
                                <div class="mt-2">
                                    @foreach($lead->asmCnpHistories->sortByDesc('max_hit_at') as $history)
                                        <span class="cnp-history-chip">
                                            {{ $history->user->name ?? 'User' }} · {{ $history->completed_cnp_count }} CNP
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                            <form method="POST" action="{{ route('admin.automation.cnp.quarantined.reactivate', $lead) }}" class="grid gap-2">
                                @csrf
                                <select name="assigned_to" class="rounded-xl border border-slate-300 px-3 py-2 text-sm" required>
                                    <option value="">Assign to ASM</option>
                                    @foreach($asmUsers as $user)
                                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                                    @endforeach
                                </select>
                                <input type="text" name="note" class="rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="Optional note">
                                <button type="submit" class="cnp-btn primary">Reactivate & Assign</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl bg-slate-50 p-6 text-center text-sm font-semibold text-slate-500">No quarantined leads.</div>
                @endforelse
            </div>
        </div>

        <div class="cnp-card">
            <div class="cnp-card-header">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Recent States</h2>
                    <p class="text-sm text-slate-500">Pending, cancelled, transferred lifecycles.</p>
                </div>
            </div>
            <div class="cnp-card-body" style="max-height:360px; overflow:auto;">
                <table class="cnp-table">
                    <thead>
                        <tr><th>Lead</th><th>Status</th><th>CNP</th><th>Transfer</th></tr>
                    </thead>
                    <tbody>
                        @forelse($activeStates as $state)
                            <tr>
                                <td>
                                    <div class="font-semibold text-slate-900">{{ $state->lead->name ?? 'Lead' }}</div>
                                    <div class="text-xs text-slate-500">
                                        {{ $state->status === 'transferred'
                                            ? ($state->originalAssignee->name ?? '-') . ' to ' . ($state->currentAssignee->name ?? '-')
                                            : ($state->currentAssignee->name ?? '-') }}
                                    </div>
                                </td>
                                <td><span class="cnp-pill {{ $state->status === 'active' ? 'green' : 'slate' }}">{{ ucwords(str_replace('_', ' ', $state->status)) }}</span></td>
                                <td>{{ $state->cnp_count }}</td>
                                <td>
                                    @if($state->status === 'transferred')
                                        <span class="text-xs font-semibold text-slate-900">
                                            {{ $state->originalAssignee->name ?? '-' }}
                                            <span class="cnp-transfer-arrow">-></span>
                                            {{ $state->currentAssignee->name ?? '-' }}
                                        </span>
                                        <span class="cnp-transfer-note">
                                            {{ optional($state->transferred_at)->format('d M Y, h:i A') ?: 'Transferred' }}
                                        </span>
                                    @elseif($state->status === 'quarantined')
                                        <span class="text-xs font-semibold text-rose-700">Quarantined</span>
                                        <span class="cnp-transfer-note">
                                            {{ optional($state->quarantined_at)->format('d M Y, h:i A') ?: $state->cancel_reason }}
                                        </span>
                                    @elseif($state->status === 'reactivated')
                                        <span class="text-xs font-semibold text-emerald-700">Reactivated</span>
                                        <span class="cnp-transfer-note">{{ $state->currentAssignee->name ?? '-' }}</span>
                                    @elseif($state->status === 'cancelled')
                                        <span class="text-xs text-slate-500">{{ $state->cancel_reason ?: 'Flow stopped' }}</span>
                                    @else
                                        <span class="text-xs text-slate-400">Not transferred</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-slate-500">No automation states yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="cnp-card">
            <div class="cnp-card-header">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Admin Audit</h2>
                    <p class="text-sm text-slate-500">Previous-owner history sirf yahin visible hai.</p>
                </div>
            </div>
            <div class="cnp-card-body" style="max-height:360px; overflow:auto;">
                <table class="cnp-table">
                    <thead>
                        <tr><th>Action</th><th>Lead</th><th>Users</th></tr>
                    </thead>
                    <tbody>
                        @forelse($recentAudits as $audit)
                            <tr>
                                <td>
                                    <div class="font-semibold text-slate-900">{{ ucwords(str_replace('_', ' ', $audit->action)) }}</div>
                                    <div class="text-xs text-slate-500">{{ optional($audit->acted_at)->format('d M Y, h:i A') }}</div>
                                </td>
                                <td>{{ $audit->lead->name ?? 'Lead' }}</td>
                                <td class="text-xs text-slate-600">
                                    From: {{ $audit->fromUser->name ?? '-' }}<br>
                                    To: {{ $audit->toUser->name ?? '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-slate-500">No audit entries yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        const mode = document.getElementById('transfer_rule_mode');
        const windowField = document.getElementById('transfer-window-field');
        const windowInput = document.getElementById('transfer_window_hours');

        if (!mode || !windowField || !windowInput) {
            return;
        }

        const sync = () => {
            const usesWindow = mode.value !== 'count_only';
            windowField.style.display = usesWindow ? 'block' : 'none';
            windowInput.disabled = !usesWindow;
        };

        mode.addEventListener('change', sync);
        sync();
    })();
</script>
@endsection

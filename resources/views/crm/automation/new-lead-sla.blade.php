@extends('layouts.app')

@section('title', 'New Lead SLA Automation - ' . brand_name())
@section('page-title', 'New Lead Response SLA')
@section('page-subtitle', 'Per-source no-response automation for sales users.')

@section('content')
<style>
    .sla-page{display:flex;flex-direction:column;gap:24px}
    .sla-hero,.sla-panel,.sla-metric{background:#fff;border:1px solid #dbe3ea;border-radius:22px;box-shadow:0 12px 28px rgba(15,23,42,.06)}
    .sla-hero{padding:28px;background:linear-gradient(135deg,#0f3d2e,#166534 60%,#1f7a45);color:#fff}
    .sla-hero-grid{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(280px,.9fr);gap:20px;align-items:start}
    .sla-kicker,.sla-pill{display:inline-flex;align-items:center;gap:8px;border-radius:999px;padding:7px 12px;font-size:12px;font-weight:800;letter-spacing:.04em;text-transform:uppercase}
    .sla-kicker{background:rgba(255,255,255,.14);color:#fff}
    .sla-pill{background:#ecfdf3;color:#166534}
    .sla-title{margin:14px 0 8px;font-size:clamp(28px,3vw,42px);line-height:1.08;font-weight:800}
    .sla-copy{margin:0;color:rgba(255,255,255,.88);max-width:62ch;line-height:1.7;font-size:16px}
    .sla-points,.sla-metrics,.sla-grid-3,.sla-grid-2,.sla-form-grid,.sla-chooser{display:grid;gap:16px}
    .sla-points{grid-template-columns:repeat(3,minmax(0,1fr));margin-top:18px}
    .sla-point{background:rgba(255,255,255,.09);border:1px solid rgba(255,255,255,.14);border-radius:16px;padding:14px}
    .sla-point b{display:block;font-size:11px;letter-spacing:.05em;text-transform:uppercase;color:rgba(255,255,255,.7);margin-bottom:5px}
    .sla-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}
    .sla-metric{padding:18px}
    .sla-metric .label{font-size:12px;font-weight:800;text-transform:uppercase;color:#64748b;margin-bottom:8px}
    .sla-metric .value{font-size:34px;line-height:1;font-weight:800;color:#063a1c;margin-bottom:8px}
    .sla-metric p{margin:0;color:#64748b;line-height:1.55}
    .sla-grid-3{grid-template-columns:repeat(3,minmax(0,1fr))}
    .sla-grid-2{grid-template-columns:repeat(2,minmax(0,1fr))}
    .sla-panel{padding:22px}
    .sla-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:16px}
    .sla-head h3{margin:10px 0 4px;font-size:24px;line-height:1.15;color:#0f172a}
    .sla-head p,.sla-muted{margin:0;color:#64748b;line-height:1.6;font-size:14px}
    .sla-form-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
    .sla-field{display:flex;flex-direction:column;gap:8px}
    .sla-hidden{display:none}
    .sla-field label{font-size:14px;font-weight:700;color:#334155}
    .sla-input,.sla-select{width:100%;min-height:48px;padding:12px 14px;border:1px solid #cbd5e1;border-radius:14px;background:#fff;color:#0f172a}
    .sla-input:focus,.sla-select:focus{outline:none;border-color:#15803d;box-shadow:0 0 0 4px rgba(21,128,61,.12)}
    .sla-chooser{grid-template-columns:repeat(2,minmax(0,1fr));margin-top:16px}
    .sla-list{max-height:260px;overflow:auto;background:#f8fafc;border:1px solid #dbe3ea;border-radius:18px;padding:12px;display:flex;flex-direction:column;gap:10px}
    .sla-item{display:flex;gap:10px;align-items:flex-start;background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:11px 12px}
    .sla-item input{margin-top:2px;accent-color:#166534}
    .sla-item strong{display:block;color:#0f172a}
    .sla-item span{display:block;color:#64748b;font-size:13px}
    .sla-toggles,.sla-actions,.sla-row-actions{display:flex;flex-wrap:wrap;gap:10px}
    .sla-toggles{margin-top:16px}
    .sla-toggle{display:inline-flex;align-items:center;gap:8px;padding:10px 12px;border:1px solid #dbe3ea;border-radius:999px;background:#f8fafc;font-size:14px;font-weight:700;color:#334155}
    .sla-toggle input{accent-color:#166534}
    .sla-actions{margin-top:18px}
    .sla-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:46px;padding:11px 16px;border-radius:14px;border:none;text-decoration:none;font-weight:800;cursor:pointer}
    .sla-btn-primary{background:linear-gradient(135deg,#166534,#15803d);color:#fff}
    .sla-btn-light{background:#eef2f7;border:1px solid #dbe3ea;color:#1e293b}
    .sla-btn-danger{background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff}
    .sla-table-wrap{overflow:auto;border:1px solid #e2e8f0;border-radius:18px}
    .sla-table{width:100%;min-width:860px;border-collapse:collapse}
    .sla-table th,.sla-table td{padding:15px 14px;border-bottom:1px solid #e2e8f0;text-align:left;vertical-align:top}
    .sla-table th{background:#f8fafc;color:#475569;font-size:12px;text-transform:uppercase;letter-spacing:.05em}
    .sla-badge{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;font-size:13px;font-weight:800;background:#ecfdf3;color:#166534}
    .sla-badge.off{background:#f1f5f9;color:#64748b}
    .sla-empty{text-align:center;padding:28px 16px;color:#64748b}
    .sla-edit{display:none;padding-top:14px}
    .sla-edit.open{display:block}
    .sla-edit-box{background:#f8fafc;border:1px solid #dbe3ea;border-radius:18px;padding:16px}
    .sla-note{padding:14px 16px;border-radius:16px;font-weight:700;border:1px solid #bbf7d0;background:#f0fdf4;color:#166534}
    .sla-note.warn{border-color:#fed7aa;background:#fff7ed;color:#9a3412}
    @media (max-width:1024px){.sla-hero-grid,.sla-grid-3,.sla-grid-2,.sla-form-grid,.sla-chooser,.sla-points,.sla-metrics{grid-template-columns:1fr}}
    @media (max-width:768px){.sla-hero,.sla-panel,.sla-metric{border-radius:18px}.sla-hero{padding:20px}.sla-actions,.sla-row-actions,.sla-toggles{flex-direction:column}.sla-btn,.sla-toggle{width:100%}}
</style>

<div class="page-shell">
    <div class="sla-page">
        @if(session('success'))
            <div class="sla-note"><i class="fas fa-circle-check mr-2"></i>{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="sla-note warn">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <section class="sla-hero">
            <div class="sla-hero-grid">
                <div>
                    <span class="sla-kicker"><i class="fas fa-stopwatch"></i>SLA Automation</span>
                    <h2 class="sla-title">Auto reassign new leads before silence turns into lead leakage.</h2>
                    <p class="sla-copy">Any outcome counts as response. SLA clock runs in business hours only, weekends stay off, and final misses escalate to Admin/CRM with a full audit trail.</p>
                    <div class="sla-points">
                        <div class="sla-point"><b>Response Rule</b>Any lead outcome stops the timer.</div>
                        <div class="sla-point"><b>Transfer Logic</b>Lead moves to the next eligible sales user.</div>
                        <div class="sla-point"><b>Escalation</b>Final miss alerts Admin/CRM instantly.</div>
                    </div>
                </div>
                <div class="sla-metrics">
                    <div class="sla-metric"><div class="label">Active Leads</div><div class="value">{{ $summary['active_leads'] ?? 0 }}</div><p>Currently tracked inside the SLA window.</p></div>
                    <div class="sla-metric"><div class="label">Escalated</div><div class="value">{{ $summary['escalated_leads'] ?? 0 }}</div><p>Leads waiting for manual intervention.</p></div>
                </div>
            </div>
        </section>

        <section class="sla-grid-3">
            <article class="sla-metric"><span class="sla-pill"><i class="fas fa-triangle-exclamation"></i>At Risk</span><div class="value" style="margin-top:14px">{{ $summary['at_risk_leads'] ?? 0 }}</div><p>Deadlines due within the next hour.</p></article>
            <article class="sla-metric"><span class="sla-pill"><i class="fas fa-user-clock"></i>Missed By User</span><div class="value" style="margin-top:14px">{{ optional(($summary['user_misses'] ?? collect())->first())->total ?? 0 }}</div><p>Highest miss count by a single sales user.</p></article>
            <article class="sla-metric"><span class="sla-pill"><i class="fas fa-layer-group"></i>Missed By Source</span><div class="value" style="margin-top:14px">{{ optional(($summary['source_misses'] ?? collect())->first())->total ?? 0 }}</div><p>Highest miss count among configured sources.</p></article>
        </section>

        <section class="sla-panel">
            <div class="sla-head">
                <div>
                    <span class="sla-pill"><i class="fas fa-plus"></i>Create Config</span>
                    <h3>New Source Automation</h3>
                    <p>Set one source, one SLA window, one sales pool, and one escalation list.</p>
                    <p class="sla-muted" style="margin-top:6px">Meta source form-wise chalega. Har form ke liye alag SLA config banegi.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('crm.automation.sla.store') }}">
                @csrf
                <div class="sla-form-grid">
                    <div class="sla-field"><label>Lead Source</label><select name="source" class="sla-select js-sla-source" required><option value="">Select source</option>@foreach($sources as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                    <div class="sla-field sla-hidden js-sla-meta-form-wrap">
                        <label>Meta Form</label>
                        <select name="fb_form_id" class="sla-select js-sla-meta-form">
                            <option value="">Select Meta form</option>
                            @foreach($metaForms as $form)
                                <option value="{{ $form->id }}">{{ $form->form_name ?: $form->form_id }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sla-field"><label>Config Name</label><input type="text" name="name" class="sla-input" placeholder="Plot Form SLA"></div>
                    <div class="sla-field"><label>SLA Minutes</label><input type="number" name="sla_minutes" class="sla-input" min="5" value="240" required></div>
                    <div class="sla-field"><label>Max Transfer Attempts</label><input type="number" name="max_transfer_attempts" class="sla-input" min="1" value="3" required></div>
                    <div class="sla-field"><label>Business Start</label><input type="time" name="business_start_time" class="sla-input" value="10:00" required></div>
                    <div class="sla-field"><label>Business End</label><input type="time" name="business_end_time" class="sla-input" value="19:00" required></div>
                </div>

                <div class="sla-chooser">
                    <div class="sla-field">
                        <label>Sales User Pool</label>
                        <div class="sla-list">
                            @foreach($salesUsers as $user)
                                <label class="sla-item"><input type="checkbox" name="pool_user_ids[]" value="{{ $user->id }}"><span><strong>{{ $user->name }}</strong><span>{{ $user->getDisplayRoleName() }}</span></span></label>
                            @endforeach
                        </div>
                    </div>
                    <div class="sla-field">
                        <label>Escalation Recipients</label>
                        <div class="sla-list">
                            @foreach($recipients as $user)
                                <label class="sla-item"><input type="checkbox" name="recipient_user_ids[]" value="{{ $user->id }}"><span><strong>{{ $user->name }}</strong><span>{{ $user->getDisplayRoleName() }}</span></span></label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="sla-toggles">
                    <label class="sla-toggle"><input type="checkbox" name="is_active" value="1" checked> Active</label>
                    <label class="sla-toggle"><input type="checkbox" name="weekends_off" value="1" checked> Weekends Off</label>
                    <label class="sla-toggle"><input type="checkbox" name="email_enabled" value="1" checked> Email</label>
                    <label class="sla-toggle"><input type="checkbox" name="in_app_enabled" value="1" checked> In-App</label>
                    <label class="sla-toggle"><input type="checkbox" name="dashboard_alert_enabled" value="1" checked> Dashboard Alert</label>
                    <label class="sla-toggle"><input type="checkbox" name="skip_inactive_users" value="1" checked> Skip Inactive</label>
                    <label class="sla-toggle"><input type="checkbox" name="skip_absent_users" value="1" checked> Skip Absent</label>
                </div>

                <div class="sla-actions">
                    <button type="submit" class="sla-btn sla-btn-primary"><i class="fas fa-check"></i>Create SLA Config</button>
                    <a href="{{ route('crm.automation.index') }}" class="sla-btn sla-btn-light"><i class="fas fa-arrow-left"></i>Back To Automation</a>
                </div>
            </form>
        </section>

        <section class="sla-panel">
            <div class="sla-head">
                <div>
                    <span class="sla-pill"><i class="fas fa-sliders"></i>Configs</span>
                    <h3>Current Source Rules</h3>
                    <p>Review active source configs, user pools, escalation owners, and edit settings inline.</p>
                </div>
            </div>

            <div class="sla-table-wrap">
                <table class="sla-table">
                    <thead><tr><th>Source / Form</th><th>SLA Window</th><th>User Pool</th><th>Escalation</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        @forelse($configs as $config)
                            <tr>
                                <td>
                                    <strong>{{ \App\Models\Lead::displaySourceLabel($config->source) }}</strong>
                                    @if($config->source === 'meta')
                                        <div class="sla-muted">Form: {{ $config->fbForm?->form_name ?: $config->fbForm?->form_id ?: 'Unlinked form' }}</div>
                                    @endif
                                    <div class="sla-muted">{{ $config->name ?: 'No custom name' }}</div>
                                </td>
                                <td><strong>{{ $config->sla_minutes }} min</strong><div class="sla-muted">{{ $config->business_start_time }} - {{ $config->business_end_time }}</div></td>
                                <td class="sla-muted">{{ $config->poolUsers->pluck('user.name')->implode(', ') ?: 'No users selected' }}</td>
                                <td class="sla-muted">{{ $config->recipients->pluck('user.name')->implode(', ') ?: 'No recipients selected' }}</td>
                                <td><span class="sla-badge {{ $config->is_active ? '' : 'off' }}"><i class="fas {{ $config->is_active ? 'fa-circle' : 'fa-minus-circle' }}"></i>{{ $config->is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td>
                                    <div class="sla-row-actions">
                                        <button type="button" class="sla-btn sla-btn-light" style="min-height:auto;padding:9px 12px" onclick="document.getElementById('sla-edit-{{ $config->id }}').classList.toggle('open')"><i class="fas fa-pen"></i>Edit</button>
                                        <form method="POST" action="{{ route('crm.automation.sla.destroy', $config) }}" onsubmit="return confirm('Delete this SLA config?')">@csrf @method('DELETE')<button type="submit" class="sla-btn sla-btn-danger" style="min-height:auto;padding:9px 12px"><i class="fas fa-trash"></i>Delete</button></form>
                                    </div>
                                </td>
                            </tr>
                            <tr><td colspan="6" style="padding-top:0">
                                <div id="sla-edit-{{ $config->id }}" class="sla-edit">
                                    <div class="sla-edit-box">
                                        <form method="POST" action="{{ route('crm.automation.sla.update', $config) }}">
                                            @csrf
                                            @method('PUT')
                                            <div class="sla-form-grid">
                                                <div class="sla-field"><label>Lead Source</label><select name="source" class="sla-select js-sla-source" required>@foreach($sources as $value => $label)<option value="{{ $value }}" @selected($config->source === $value)>{{ $label }}</option>@endforeach</select></div>
                                                <div class="sla-field {{ $config->source === 'meta' ? '' : 'sla-hidden' }} js-sla-meta-form-wrap">
                                                    <label>Meta Form</label>
                                                    <select name="fb_form_id" class="sla-select js-sla-meta-form">
                                                        <option value="">Select Meta form</option>
                                                        @foreach($metaForms as $form)
                                                            <option value="{{ $form->id }}" @selected((int) $config->fb_form_id === (int) $form->id)>{{ $form->form_name ?: $form->form_id }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="sla-field"><label>Config Name</label><input type="text" name="name" class="sla-input" value="{{ $config->name }}"></div>
                                                <div class="sla-field"><label>SLA Minutes</label><input type="number" name="sla_minutes" class="sla-input" min="5" value="{{ $config->sla_minutes }}" required></div>
                                                <div class="sla-field"><label>Max Transfer Attempts</label><input type="number" name="max_transfer_attempts" class="sla-input" min="1" value="{{ $config->max_transfer_attempts }}" required></div>
                                                <div class="sla-field"><label>Business Start</label><input type="time" name="business_start_time" class="sla-input" value="{{ \Illuminate\Support\Str::substr($config->business_start_time, 0, 5) }}" required></div>
                                                <div class="sla-field"><label>Business End</label><input type="time" name="business_end_time" class="sla-input" value="{{ \Illuminate\Support\Str::substr($config->business_end_time, 0, 5) }}" required></div>
                                            </div>

                                            <div class="sla-chooser">
                                                <div class="sla-field"><label>Sales User Pool</label><div class="sla-list">@foreach($salesUsers as $user)<label class="sla-item"><input type="checkbox" name="pool_user_ids[]" value="{{ $user->id }}" @checked($config->poolUsers->pluck('user_id')->contains($user->id))><span><strong>{{ $user->name }}</strong><span>{{ $user->getDisplayRoleName() }}</span></span></label>@endforeach</div></div>
                                                <div class="sla-field"><label>Escalation Recipients</label><div class="sla-list">@foreach($recipients as $user)<label class="sla-item"><input type="checkbox" name="recipient_user_ids[]" value="{{ $user->id }}" @checked($config->recipients->pluck('user_id')->contains($user->id))><span><strong>{{ $user->name }}</strong><span>{{ $user->getDisplayRoleName() }}</span></span></label>@endforeach</div></div>
                                            </div>

                                            <div class="sla-toggles">
                                                <label class="sla-toggle"><input type="checkbox" name="is_active" value="1" @checked($config->is_active)> Active</label>
                                                <label class="sla-toggle"><input type="checkbox" name="weekends_off" value="1" @checked($config->weekends_off)> Weekends Off</label>
                                                <label class="sla-toggle"><input type="checkbox" name="email_enabled" value="1" @checked($config->email_enabled)> Email</label>
                                                <label class="sla-toggle"><input type="checkbox" name="in_app_enabled" value="1" @checked($config->in_app_enabled)> In-App</label>
                                                <label class="sla-toggle"><input type="checkbox" name="dashboard_alert_enabled" value="1" @checked($config->dashboard_alert_enabled)> Dashboard Alert</label>
                                                <label class="sla-toggle"><input type="checkbox" name="skip_inactive_users" value="1" @checked($config->skip_inactive_users)> Skip Inactive</label>
                                                <label class="sla-toggle"><input type="checkbox" name="skip_absent_users" value="1" @checked($config->skip_absent_users)> Skip Absent</label>
                                            </div>

                                            <div class="sla-actions">
                                                <button type="submit" class="sla-btn sla-btn-primary"><i class="fas fa-save"></i>Save Changes</button>
                                                <button type="button" class="sla-btn sla-btn-light" onclick="document.getElementById('sla-edit-{{ $config->id }}').classList.remove('open')"><i class="fas fa-times"></i>Cancel</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </td></tr>
                        @empty
                            <tr><td colspan="6"><div class="sla-empty">No SLA automation config yet.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="sla-grid-2">
            <article class="sla-panel">
                <div class="sla-head"><div><span class="sla-pill"><i class="fas fa-bell"></i>Escalations</span><h3>Recent Escalated Leads</h3><p>Latest leads that exhausted the configured response cycle.</p></div></div>
                <div class="sla-table-wrap"><table class="sla-table" style="min-width:100%"><thead><tr><th>Lead</th><th>Source</th><th>Owner</th><th>Escalated</th></tr></thead><tbody>@forelse($summary['recent_escalations'] ?? [] as $state)<tr><td><strong>{{ $state->lead?->name ?? 'N/A' }}</strong><div class="sla-muted">{{ $state->lead?->phone ?? 'N/A' }}</div></td><td>{{ \App\Models\Lead::displaySourceLabel($state->lead?->source) }}</td><td>{{ $state->currentAssignee?->name ?? 'N/A' }}</td><td>{{ optional($state->escalated_at)->format('d M Y h:i A') }}</td></tr>@empty<tr><td colspan="4"><div class="sla-empty">No escalations yet.</div></td></tr>@endforelse</tbody></table></div>
            </article>

            <article class="sla-panel">
                <div class="sla-head"><div><span class="sla-pill"><i class="fas fa-chart-column"></i>Misses</span><h3>User SLA Misses</h3><p>Highest miss counts by sales users under active automation.</p></div></div>
                <div class="sla-table-wrap"><table class="sla-table" style="min-width:100%"><thead><tr><th>User</th><th>Miss Count</th></tr></thead><tbody>@forelse($summary['user_misses'] ?? [] as $row)<tr><td>{{ $row->fromUser?->name ?? 'Unknown' }}</td><td>{{ $row->total }}</td></tr>@empty<tr><td colspan="2"><div class="sla-empty">No SLA misses yet.</div></td></tr>@endforelse</tbody></table></div>
            </article>
        </section>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form').forEach(function (form) {
            var source = form.querySelector('.js-sla-source');
            var metaWrap = form.querySelector('.js-sla-meta-form-wrap');
            var metaSelect = form.querySelector('.js-sla-meta-form');
            if (!source || !metaWrap || !metaSelect) {
                return;
            }

            var syncMetaFormState = function () {
                var isMeta = source.value === 'meta';
                metaWrap.classList.toggle('sla-hidden', !isMeta);
                metaSelect.required = isMeta;
                if (!isMeta) {
                    metaSelect.value = '';
                }
            };

            source.addEventListener('change', syncMetaFormState);
            syncMetaFormState();
        });
    });
</script>
@endsection

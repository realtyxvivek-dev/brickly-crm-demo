@extends('layouts.app')

@section('title', 'IVR Receiver Assignment')
@section('page-title', 'IVR Receiver Assignment')
@section('page-subtitle', 'Assign IVR leads to the call receiver, with optional override rules.')

@php
    $poolUsers = old('pool_users', $config->poolUsers->map(fn ($row) => [
        'user_id' => $row->user_id,
        'allocation_percentage' => $row->allocation_percentage,
    ])->values()->all());
    if (empty($poolUsers)) {
        $poolUsers = [['user_id' => '', 'allocation_percentage' => '']];
    }

    $receiverOverrides = old('receiver_overrides', $config->receiverOverrides->map(fn ($row) => [
        'user_id' => $row->user_id,
        'is_enabled' => $row->is_enabled,
        'can_assign_to_self' => $row->can_assign_to_self,
        'target_type' => $row->target_type,
        'distribution_method' => $row->distribution_method,
        'team_manager_user_id' => $row->team_manager_user_id,
        'fixed_user_id' => $row->fixed_user_id,
        'fallback_mode' => $row->fallback_mode,
        'fallback_user_id' => $row->fallback_user_id,
        'notes' => $row->notes,
    ])->values()->all());
    if (empty($receiverOverrides)) {
        $receiverOverrides = [[
            'user_id' => '',
            'is_enabled' => true,
            'can_assign_to_self' => true,
            'target_type' => 'self',
            'distribution_method' => 'receiver',
            'team_manager_user_id' => '',
            'fixed_user_id' => '',
            'fallback_mode' => '',
            'fallback_user_id' => '',
            'notes' => '',
        ]];
    }
@endphp

@section('content')
<style>
    .ivr-page{display:flex;flex-direction:column;gap:24px}
    .ivr-card,.ivr-hero{background:#fff;border:1px solid #dbe3ea;border-radius:20px;box-shadow:0 10px 28px rgba(15,23,42,.06)}
    .ivr-hero{padding:24px;background:#006BA6;color:#fff}
    .ivr-hero-grid,.ivr-grid-2,.ivr-grid-3,.ivr-form-grid,.ivr-repeat-grid{display:grid;gap:16px}
    .ivr-hero-grid{grid-template-columns:minmax(0,1.35fr) minmax(280px,.9fr)}
    .ivr-grid-2{grid-template-columns:repeat(2,minmax(0,1fr))}
    .ivr-grid-3{grid-template-columns:repeat(3,minmax(0,1fr))}
    .ivr-form-grid,.ivr-repeat-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
    .ivr-card{padding:20px}
    .ivr-pill{display:inline-flex;align-items:center;gap:8px;border-radius:999px;padding:6px 12px;font-size:12px;font-weight:800;text-transform:uppercase;background:#e0f2fe;color:#075985}
    .ivr-title{margin:12px 0 8px;font-size:34px;line-height:1.1;font-weight:800}
    .ivr-copy,.ivr-muted{margin:0;color:#64748b;line-height:1.6}
    .ivr-hero .ivr-copy{color:rgba(255,255,255,.88)}
    .ivr-stat{padding:16px;border:1px solid #dbe3ea;border-radius:16px;background:#fff;color:#0f172a}
    .ivr-stat .label{font-size:12px;font-weight:800;text-transform:uppercase;color:#64748b}
    .ivr-stat .value{margin-top:10px;font-size:30px;font-weight:800;color:#006BA6}
    .ivr-field{display:flex;flex-direction:column;gap:8px}
    .ivr-field label{font-size:14px;font-weight:700;color:#334155}
    .ivr-input,.ivr-select,.ivr-textarea{width:100%;min-height:46px;padding:11px 14px;border:1px solid #cbd5e1;border-radius:14px;background:#fff}
    .ivr-textarea{min-height:92px;resize:vertical}
    .ivr-input:focus,.ivr-select:focus,.ivr-textarea:focus{outline:none;border-color:#0077b6;box-shadow:0 0 0 4px rgba(0,119,182,.14)}
    .ivr-toggle-row,.ivr-actions,.ivr-head{display:flex;flex-wrap:wrap;gap:10px}
    .ivr-head{justify-content:space-between;align-items:flex-start;margin-bottom:16px}
    .ivr-toggle{display:inline-flex;align-items:center;gap:8px;padding:10px 12px;border:1px solid #dbe3ea;border-radius:999px;background:#f8fafc;font-size:14px;font-weight:700;color:#334155}
    .ivr-toggle input{accent-color:#006BA6}
    .ivr-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:44px;padding:10px 16px;border-radius:14px;border:none;text-decoration:none;font-weight:800;cursor:pointer}
    .ivr-btn-primary{background:#006BA6;color:#fff}
    .ivr-btn-primary:hover{background:#005985}
    .ivr-btn-light{background:#eef2f7;border:1px solid #dbe3ea;color:#1e293b}
    .ivr-note{padding:14px 16px;border-radius:16px;font-weight:700;border:1px solid #bae6fd;background:#f0f9ff;color:#075985}
    .ivr-note.warn{border-color:#fed7aa;background:#fff7ed;color:#9a3412}
    .ivr-repeat{display:flex;flex-direction:column;gap:12px}
    .ivr-repeat-row{padding:14px;border:1px solid #dbe3ea;border-radius:16px;background:#f8fafc}
    .ivr-row-tools{display:flex;justify-content:flex-end;margin-top:10px}
    .ivr-table{width:100%;border-collapse:collapse}
    .ivr-table th,.ivr-table td{padding:12px;border-bottom:1px solid #e2e8f0;text-align:left;vertical-align:top}
    .ivr-table th{font-size:12px;text-transform:uppercase;color:#64748b;background:#f8fafc}
    @media (max-width:1024px){.ivr-hero-grid,.ivr-grid-2,.ivr-grid-3,.ivr-form-grid,.ivr-repeat-grid{grid-template-columns:1fr}}
</style>

<div class="page-shell">
    <div class="ivr-page">
        @if(session('success'))
            <div class="ivr-note">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="ivr-note warn">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <section class="ivr-hero">
            <div class="ivr-hero-grid">
                <div>
                    <span class="ivr-pill"><i class="fas fa-phone-volume"></i>IVR Automation</span>
                    <h2 class="ivr-title">Assign each IVR lead to the user who receives the call.</h2>
                    <p class="ivr-copy">Default behavior sends the lead to the call receiver. Use overrides only when a receiver should route leads to a team, shared pool, fixed user, or fallback queue.</p>
                </div>
                <div class="ivr-grid-2">
                    <div class="ivr-stat"><div class="label">Status</div><div class="value">{{ $config->is_enabled ? 'On' : 'Off' }}</div></div>
                    <div class="ivr-stat"><div class="label">Pool Users</div><div class="value">{{ $summary['pool_count'] }}</div></div>
                    <div class="ivr-stat"><div class="label">Overrides</div><div class="value">{{ $summary['override_count'] }}</div></div>
                    <div class="ivr-stat"><div class="label">Recent Audits</div><div class="value">{{ $summary['recent_count'] }}</div></div>
                </div>
            </div>
        </section>

        <form method="POST" action="{{ route('crm.automation.ivr.update') }}">
            @csrf
            @method('PUT')

            <section class="ivr-card">
                <div class="ivr-head">
                    <div>
                        <span class="ivr-pill">Default Rule</span>
                        <h3>Global IVR Rule</h3>
                        <p class="ivr-muted">This rule applies when no per-receiver override matches.</p>
                    </div>
                </div>

                <div class="ivr-toggle-row">
                    <label class="ivr-toggle"><input type="checkbox" name="is_enabled" value="1" @checked(old('is_enabled', $config->is_enabled))> Automation Enabled</label>
                    <label class="ivr-toggle"><input type="checkbox" name="receiver_assignment_allowed" value="1" @checked(old('receiver_assignment_allowed', $config->receiver_assignment_allowed))> Assign To Call Receiver</label>
                </div>

                <div class="ivr-form-grid" style="margin-top:16px">
                    <div class="ivr-field">
                        <label>Default Mode</label>
                        <select name="default_mode" class="ivr-select" required>
                            @foreach(\App\Models\IvrLeadAutomationConfig::modeOptions() as $value => $label)
                                <option value="{{ $value }}" @selected(old('default_mode', $config->default_mode) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ivr-field">
                        <label>Distribution Method</label>
                        <select name="distribution_method" class="ivr-select" required>
                            @foreach(\App\Models\IvrLeadAutomationConfig::methodOptions() as $value => $label)
                                <option value="{{ $value }}" @selected(old('distribution_method', $config->distribution_method) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ivr-field">
                        <label>Fixed User</label>
                        <select name="fixed_user_id" class="ivr-select">
                            <option value="">Select user</option>
                            @foreach($assignableUsers as $user)
                                <option value="{{ $user->id }}" @selected((int) old('fixed_user_id', $config->fixed_user_id) === (int) $user->id)>{{ $user->name }} ({{ $user->getDisplayRoleName() }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ivr-field">
                        <label>Receiver Team Manager</label>
                        <select name="default_team_manager_user_id" class="ivr-select">
                            <option value="">Use receiver direct team</option>
                            @foreach($receiverUsers as $user)
                                <option value="{{ $user->id }}" @selected((int) old('default_team_manager_user_id', $config->default_team_manager_user_id) === (int) $user->id)>{{ $user->name }} ({{ $user->getDisplayRoleName() }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ivr-field">
                        <label>Fallback Mode</label>
                        <select name="fallback_mode" class="ivr-select" required>
                            @foreach(\App\Models\IvrLeadAutomationConfig::fallbackOptions() as $value => $label)
                                <option value="{{ $value }}" @selected(old('fallback_mode', $config->fallback_mode) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ivr-field">
                        <label>Fallback User</label>
                        <select name="fallback_user_id" class="ivr-select">
                            <option value="">Select backup user</option>
                            @foreach($assignableUsers as $user)
                                <option value="{{ $user->id }}" @selected((int) old('fallback_user_id', $config->fallback_user_id) === (int) $user->id)>{{ $user->name }} ({{ $user->getDisplayRoleName() }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="ivr-field" style="margin-top:16px">
                    <label>Notes</label>
                    <textarea name="notes" class="ivr-textarea">{{ old('notes', $config->notes) }}</textarea>
                </div>
            </section>

            <section class="ivr-card">
                <div class="ivr-head">
                    <div>
                        <span class="ivr-pill">Pool / Team Mapping</span>
                        <h3>Shared Pool</h3>
                        <p class="ivr-muted">Percentage and round-robin routing use this shared pool.</p>
                    </div>
                    <button type="button" class="ivr-btn ivr-btn-light" data-add-row="pool-users">Add Pool User</button>
                </div>
                <div class="ivr-repeat" id="pool-users">
                    @foreach($poolUsers as $index => $row)
                        <div class="ivr-repeat-row">
                            <div class="ivr-repeat-grid">
                                <div class="ivr-field">
                                    <label>User</label>
                                    <select name="pool_users[{{ $index }}][user_id]" class="ivr-select">
                                        <option value="">Select user</option>
                                        @foreach($assignableUsers as $user)
                                            <option value="{{ $user->id }}" @selected((int) ($row['user_id'] ?? 0) === (int) $user->id)>{{ $user->name }} ({{ $user->getDisplayRoleName() }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="ivr-field">
                                    <label>Allocation %</label>
                                    <input type="number" step="0.01" min="0" max="100" name="pool_users[{{ $index }}][allocation_percentage]" class="ivr-input" value="{{ $row['allocation_percentage'] }}">
                                </div>
                            </div>
                            <div class="ivr-row-tools"><button type="button" class="ivr-btn ivr-btn-light" data-remove-row>Remove</button></div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="ivr-card">
                <div class="ivr-head">
                    <div>
                        <span class="ivr-pill">Receiver Overrides</span>
                        <h3>Per-Receiver Rules</h3>
                        <p class="ivr-muted">Create exceptions for a receiver when their IVR leads should follow a custom route.</p>
                    </div>
                    <button type="button" class="ivr-btn ivr-btn-light" data-add-row="receiver-overrides">Add Override</button>
                </div>
                <div class="ivr-repeat" id="receiver-overrides">
                    @foreach($receiverOverrides as $index => $row)
                        <div class="ivr-repeat-row">
                            <div class="ivr-toggle-row">
                                <label class="ivr-toggle"><input type="checkbox" name="receiver_overrides[{{ $index }}][is_enabled]" value="1" @checked($row['is_enabled'] ?? false)> Enabled</label>
                                <label class="ivr-toggle"><input type="checkbox" name="receiver_overrides[{{ $index }}][can_assign_to_self]" value="1" @checked($row['can_assign_to_self'] ?? false)> Can Assign To Receiver</label>
                            </div>
                            <div class="ivr-grid-3" style="margin-top:12px">
                                <div class="ivr-field">
                                    <label>Receiver</label>
                                    <select name="receiver_overrides[{{ $index }}][user_id]" class="ivr-select">
                                        <option value="">Select receiver</option>
                                        @foreach($receiverUsers as $user)
                                            <option value="{{ $user->id }}" @selected((int) ($row['user_id'] ?? 0) === (int) $user->id)>{{ $user->name }} ({{ $user->getDisplayRoleName() }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="ivr-field">
                                    <label>Target Type</label>
                                    <select name="receiver_overrides[{{ $index }}][target_type]" class="ivr-select">
                                        @foreach(\App\Models\IvrLeadAutomationReceiverOverride::targetOptions() as $value => $label)
                                            <option value="{{ $value }}" @selected(($row['target_type'] ?? '') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="ivr-field">
                                    <label>Distribution Method</label>
                                    <select name="receiver_overrides[{{ $index }}][distribution_method]" class="ivr-select">
                                        @foreach(\App\Models\IvrLeadAutomationConfig::methodOptions() as $value => $label)
                                            <option value="{{ $value }}" @selected(($row['distribution_method'] ?? '') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="ivr-field">
                                    <label>Team Manager</label>
                                    <select name="receiver_overrides[{{ $index }}][team_manager_user_id]" class="ivr-select">
                                        <option value="">Use receiver direct team</option>
                                        @foreach($receiverUsers as $user)
                                            <option value="{{ $user->id }}" @selected((int) ($row['team_manager_user_id'] ?? 0) === (int) $user->id)>{{ $user->name }} ({{ $user->getDisplayRoleName() }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="ivr-field">
                                    <label>Fixed User</label>
                                    <select name="receiver_overrides[{{ $index }}][fixed_user_id]" class="ivr-select">
                                        <option value="">Select user</option>
                                        @foreach($assignableUsers as $user)
                                            <option value="{{ $user->id }}" @selected((int) ($row['fixed_user_id'] ?? 0) === (int) $user->id)>{{ $user->name }} ({{ $user->getDisplayRoleName() }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="ivr-field">
                                    <label>Fallback Mode</label>
                                    <select name="receiver_overrides[{{ $index }}][fallback_mode]" class="ivr-select">
                                        <option value="">Use global fallback</option>
                                        @foreach(\App\Models\IvrLeadAutomationConfig::fallbackOptions() as $value => $label)
                                            <option value="{{ $value }}" @selected(($row['fallback_mode'] ?? '') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="ivr-field">
                                    <label>Fallback User</label>
                                    <select name="receiver_overrides[{{ $index }}][fallback_user_id]" class="ivr-select">
                                        <option value="">Select backup user</option>
                                        @foreach($assignableUsers as $user)
                                            <option value="{{ $user->id }}" @selected((int) ($row['fallback_user_id'] ?? 0) === (int) $user->id)>{{ $user->name }} ({{ $user->getDisplayRoleName() }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="ivr-field">
                                    <label>Notes</label>
                                    <input type="text" name="receiver_overrides[{{ $index }}][notes]" class="ivr-input" value="{{ $row['notes'] ?? '' }}">
                                </div>
                            </div>
                            <div class="ivr-row-tools"><button type="button" class="ivr-btn ivr-btn-light" data-remove-row>Remove</button></div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="ivr-card">
                <div class="ivr-head">
                    <div>
                        <span class="ivr-pill">Recent Assignment History</span>
                        <h3>Audit Trail</h3>
                        <p class="ivr-muted">Shows why an IVR lead moved from the call receiver to the final assignee.</p>
                    </div>
                </div>
                <div style="overflow:auto">
                    <table class="ivr-table">
                        <thead><tr><th>Lead</th><th>Receiver</th><th>Assigned</th><th>Rule</th><th>Fallback</th><th>Processed</th></tr></thead>
                        <tbody>
                        @forelse($summary['recent_audits'] as $audit)
                            <tr>
                                <td><strong>{{ $audit->lead?->name ?? 'N/A' }}</strong><div class="ivr-muted">{{ $audit->lead?->phone ?? 'N/A' }}</div></td>
                                <td>{{ $audit->receiver?->name ?? 'N/A' }}</td>
                                <td>{{ $audit->assignedUser?->name ?? 'Unassigned' }}</td>
                                <td>{{ \Illuminate\Support\Str::of($audit->rule_source)->replace('_', ' ')->title() }}<div class="ivr-muted">{{ \Illuminate\Support\Str::of($audit->strategy_used)->replace('_', ' ')->title() }}</div></td>
                                <td>{{ $audit->fallback_used ? 'Yes' : 'No' }}<div class="ivr-muted">{{ $audit->fallback_mode ?: '-' }}</div></td>
                                <td>{{ optional($audit->processed_at)->format('d M Y h:i A') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="ivr-muted" style="padding:20px;text-align:center">No IVR audit yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="ivr-actions">
                <button type="submit" class="ivr-btn ivr-btn-primary">Save IVR Automation</button>
                <a href="{{ route('crm.automation.index') }}" class="ivr-btn ivr-btn-light">Back To Automation</a>
            </div>
        </form>
    </div>
</div>

<template id="pool-users-template">
    <div class="ivr-repeat-row">
        <div class="ivr-repeat-grid">
            <div class="ivr-field">
                <label>User</label>
                <select class="ivr-select" data-name="user_id">
                    <option value="">Select user</option>
                    @foreach($assignableUsers as $user)
                        <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->getDisplayRoleName() }})</option>
                    @endforeach
                </select>
            </div>
            <div class="ivr-field">
                <label>Allocation %</label>
                <input type="number" step="0.01" min="0" max="100" class="ivr-input" data-name="allocation_percentage">
            </div>
        </div>
        <div class="ivr-row-tools"><button type="button" class="ivr-btn ivr-btn-light" data-remove-row>Remove</button></div>
    </div>
</template>

<template id="receiver-overrides-template">
    <div class="ivr-repeat-row">
        <div class="ivr-toggle-row">
            <label class="ivr-toggle"><input type="checkbox" value="1" data-name="is_enabled" checked> Enabled</label>
            <label class="ivr-toggle"><input type="checkbox" value="1" data-name="can_assign_to_self" checked> Can Assign To Receiver</label>
        </div>
        <div class="ivr-grid-3" style="margin-top:12px">
            <div class="ivr-field"><label>Receiver</label><select class="ivr-select" data-name="user_id"><option value="">Select receiver</option>@foreach($receiverUsers as $user)<option value="{{ $user->id }}">{{ $user->name }} ({{ $user->getDisplayRoleName() }})</option>@endforeach</select></div>
            <div class="ivr-field"><label>Target Type</label><select class="ivr-select" data-name="target_type">@foreach(\App\Models\IvrLeadAutomationReceiverOverride::targetOptions() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
            <div class="ivr-field"><label>Distribution Method</label><select class="ivr-select" data-name="distribution_method">@foreach(\App\Models\IvrLeadAutomationConfig::methodOptions() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
            <div class="ivr-field"><label>Team Manager</label><select class="ivr-select" data-name="team_manager_user_id"><option value="">Use receiver direct team</option>@foreach($receiverUsers as $user)<option value="{{ $user->id }}">{{ $user->name }} ({{ $user->getDisplayRoleName() }})</option>@endforeach</select></div>
            <div class="ivr-field"><label>Fixed User</label><select class="ivr-select" data-name="fixed_user_id"><option value="">Select user</option>@foreach($assignableUsers as $user)<option value="{{ $user->id }}">{{ $user->name }} ({{ $user->getDisplayRoleName() }})</option>@endforeach</select></div>
            <div class="ivr-field"><label>Fallback Mode</label><select class="ivr-select" data-name="fallback_mode"><option value="">Use global fallback</option>@foreach(\App\Models\IvrLeadAutomationConfig::fallbackOptions() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
            <div class="ivr-field"><label>Fallback User</label><select class="ivr-select" data-name="fallback_user_id"><option value="">Select backup user</option>@foreach($assignableUsers as $user)<option value="{{ $user->id }}">{{ $user->name }} ({{ $user->getDisplayRoleName() }})</option>@endforeach</select></div>
            <div class="ivr-field"><label>Notes</label><input type="text" class="ivr-input" data-name="notes"></div>
        </div>
        <div class="ivr-row-tools"><button type="button" class="ivr-btn ivr-btn-light" data-remove-row>Remove</button></div>
    </div>
</template>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-add-row]').forEach(function (button) {
        button.addEventListener('click', function () {
            var targetId = this.getAttribute('data-add-row');
            var container = document.getElementById(targetId);
            var template = document.getElementById(targetId + '-template');
            var fragment = template.content.cloneNode(true);
            var index = container.children.length;
            fragment.querySelectorAll('[data-name]').forEach(function (field) {
                field.name = targetId.replace('-', '_') + '[' + index + '][' + field.getAttribute('data-name') + ']';
            });
            container.appendChild(fragment);
        });
    });

    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-remove-row]');
        if (!button) return;
        var row = button.closest('.ivr-repeat-row');
        if (row && row.parentElement.children.length > 1) row.remove();
    });
});
</script>
@endsection

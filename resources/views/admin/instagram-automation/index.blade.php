@extends('layouts.app')

@section('title', 'Instagram Automation')
@section('page-title', 'Instagram Automation')

@push('styles')
<style>
    .ig-shell{display:grid;gap:22px}.ig-tabs{display:flex;flex-wrap:wrap;gap:10px}.ig-tab{padding:10px 16px;border-radius:999px;border:1px solid #d1d5db;background:#fff;color:#374151;font-size:14px;font-weight:600;text-decoration:none}.ig-tab.active{background:#c13584;color:#fff;border-color:#c13584}.ig-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px}.ig-card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:20px;box-shadow:0 10px 30px rgba(15,23,42,.05)}.ig-header{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}.ig-kpi{font-size:28px;font-weight:700;color:#111827}.ig-muted{color:#6b7280;font-size:13px}.ig-btn{display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:12px;font-size:13px;font-weight:600;border:1px solid #d1d5db;background:#fff;color:#111827;text-decoration:none}.ig-btn.primary{background:#c13584;color:#fff;border-color:#c13584}.ig-btn.danger{background:#fff1f2;color:#9f1239;border-color:#fecdd3}.ig-btn.disabled{opacity:.55;cursor:not-allowed;background:#f9fafb}.ig-actions{display:flex;gap:8px;flex-wrap:wrap}.ig-badge{display:inline-flex;align-items:center;padding:6px 10px;border-radius:999px;font-size:12px;font-weight:700;background:#e5e7eb;color:#374151}.ig-badge.active,.ig-badge.connected,.ig-badge.success{background:#dcfce7;color:#166534}.ig-badge.error,.ig-badge.failed,.ig-badge.token_expired{background:#fee2e2;color:#991b1b}.ig-badge.pending,.ig-badge.draft{background:#fef3c7;color:#92400e}.ig-badge.disconnected{background:#e5e7eb;color:#374151}.ig-table{width:100%;border-collapse:collapse}.ig-table th,.ig-table td{padding:12px 10px;border-bottom:1px solid #e5e7eb;text-align:left;font-size:14px;vertical-align:top}.ig-table th{font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:#6b7280}.ig-empty{padding:30px;border:1px dashed #cbd5e1;border-radius:16px;text-align:center;color:#64748b;background:#fff}.ig-settings{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:14px}.ig-setting{border:1px solid #e5e7eb;border-radius:14px;padding:16px;background:#fff}.ig-alert{padding:14px 16px;border-radius:14px;border:1px solid;font-size:14px}.ig-alert.success{background:#ecfdf5;border-color:#a7f3d0;color:#166534}.ig-alert.error{background:#fef2f2;border-color:#fecaca;color:#991b1b}.ig-form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px}.ig-field{display:grid;gap:6px}.ig-field label{font-size:12px;font-weight:700;text-transform:uppercase;color:#64748b}.ig-input{width:100%;border:1px solid #d1d5db;border-radius:10px;padding:10px 12px;font-size:14px;background:#fff}.ig-list{display:grid;gap:14px}
    .ig-badge.ok{background:#dcfce7;color:#166534}.ig-badge.warning{background:#fef3c7;color:#92400e}
</style>
@endpush

@section('content')
<div class="ig-shell">
    @if(session('success'))
        <div class="ig-alert success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="ig-alert error">{{ session('error') }}</div>
    @endif

    @if($errors->any())
        <div class="ig-alert error">{{ $errors->first() }}</div>
    @endif

    <div class="ig-header">
        <div>
            <h2 class="text-2xl font-semibold text-slate-900">Instagram Automation</h2>
            <p class="ig-muted">Connect Instagram professional accounts and prepare comment-to-DM automation.</p>
        </div>
        @if($isConfigured)
            <a class="ig-btn primary" href="{{ route($routeBase . '.connect') }}">
                <i class="fab fa-instagram"></i>
                Connect Instagram
            </a>
        @else
            <a class="ig-btn disabled" href="{{ route($routeBase . '.index', ['tab' => 'settings']) }}">
                <i class="fab fa-instagram"></i>
                Configure Instagram App
            </a>
        @endif
    </div>

    <div class="ig-tabs">
        @foreach($tabs as $tabKey => $tabLabel)
            <a href="{{ route($routeBase . '.index', ['tab' => $tabKey]) }}" class="ig-tab {{ $tab === $tabKey ? 'active' : '' }}">{{ $tabLabel }}</a>
        @endforeach
    </div>

    @if($tab === 'accounts')
        <div class="ig-grid">
            <div class="ig-card"><div class="ig-kpi">{{ $accounts->total() }}</div><div class="ig-muted">Connected accounts</div></div>
            <div class="ig-card"><div class="ig-kpi">{{ $rules->total() }}</div><div class="ig-muted">Automation rules</div></div>
            <div class="ig-card"><div class="ig-kpi">{{ $conversations->total() }}</div><div class="ig-muted">Conversations</div></div>
        </div>
        <div class="ig-card overflow-x-auto">
            <div class="ig-header mb-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Instagram Accounts</h3>
                    <p class="ig-muted">Use Instagram Login to connect Business or Creator accounts.</p>
                </div>
            </div>
            <table class="ig-table">
                <thead><tr><th>Account</th><th>IG User ID</th><th>Type</th><th>Status</th><th>Token Expiry</th><th>Rules</th><th>Conversations</th><th>Connected By</th><th>Actions</th></tr></thead>
                <tbody>
                @forelse($accounts as $account)
                    <tr>
                        <td>{{ $account->ig_username ? '@' . $account->ig_username : $account->ig_user_id }}</td>
                        <td>{{ $account->ig_user_id }}</td>
                        <td>{{ $account->account_type ?: 'Unknown' }}</td>
                        <td><span class="ig-badge {{ $account->status }}">{{ ucfirst($account->status) }}</span></td>
                        <td>{{ $account->token_expiry?->format('d M Y h:i A') ?: 'N/A' }}</td>
                        <td>{{ $account->automation_rules_count }}</td>
                        <td>{{ $account->conversations_count }}</td>
                        <td>{{ $account->connectedBy?->name ?? 'System' }}</td>
                        <td>
                            <div class="ig-actions">
                                <form method="POST" action="{{ route($routeBase . '.accounts.refresh-token', $account) }}">
                                    @csrf
                                    <button class="ig-btn" type="submit" {{ !$account->access_token ? 'disabled' : '' }}>Refresh</button>
                                </form>
                                <form method="POST" action="{{ route($routeBase . '.accounts.disconnect', $account) }}" onsubmit="return confirm('Disconnect this Instagram account? Rules and conversations will be kept.');">
                                    @csrf
                                    <button class="ig-btn danger" type="submit">Disconnect</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9"><div class="ig-empty">No Instagram accounts connected yet.</div></td></tr>
                @endforelse
                </tbody>
            </table>
            <div class="mt-4">{{ $accounts->withQueryString()->links() }}</div>
        </div>
    @elseif($tab === 'automations')
        <div class="ig-card">
            <h3 class="text-lg font-semibold text-slate-900 mb-4">Create Automation Rule</h3>
            <form method="POST" action="{{ route($routeBase . '.rules.store') }}" class="ig-form-grid">
                @csrf
                <div class="ig-field"><label>Instagram Account</label><select class="ig-input" name="instagram_account_id" required>@foreach($accountOptions as $accountOption)<option value="{{ $accountOption->id }}">{{ $accountOption->ig_username ? '@' . $accountOption->ig_username : $accountOption->ig_user_id }} ({{ $accountOption->status }})</option>@endforeach</select></div>
                <div class="ig-field"><label>Rule Name</label><input class="ig-input" name="name" required></div>
                <div class="ig-field"><label>Post/Reel Media ID</label><input class="ig-input" name="media_id" placeholder="Optional"></div>
                <div class="ig-field"><label>Keywords</label><input class="ig-input" name="keywords" required placeholder="price, details, info"></div>
                <div class="ig-field"><label>Public Reply</label><textarea class="ig-input" name="public_reply_message" required rows="2">Details DM me bhej diye, please check.</textarea></div>
                <div class="ig-field"><label>DM Flow</label><select class="ig-input" name="dm_flow_id"><option value="">Public reply only</option>@foreach($flowOptions as $flowOption)<option value="{{ $flowOption->id }}">{{ $flowOption->name }}</option>@endforeach</select></div>
                <div class="ig-field"><label>Priority</label><input class="ig-input" name="priority" type="number" value="100" min="1" max="999"></div>
                <div class="ig-field"><label>Status</label><select class="ig-input" name="status"><option value="draft">Draft</option><option value="active">Active</option><option value="paused">Paused</option></select></div>
                <div class="ig-field"><label>Active</label><label style="font-size:14px;text-transform:none;font-weight:600;color:#111827;"><input type="checkbox" name="is_active" value="1"> Enable rule</label></div>
                <div class="ig-field" style="align-self:end;"><button class="ig-btn primary" type="submit">Create Rule</button></div>
            </form>
        </div>
        <div class="ig-list">
            @forelse($rules as $rule)
                <div class="ig-card">
                    <form method="POST" action="{{ route($routeBase . '.rules.update', $rule) }}">
                        @csrf
                        @method('PUT')
                        <div class="ig-header mb-4">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-900">{{ $rule->name }}</h3>
                                <p class="ig-muted">Priority {{ $rule->priority }} | {{ $rule->instagramAccount?->ig_username ? '@' . $rule->instagramAccount->ig_username : 'Unknown account' }}</p>
                            </div>
                            <span class="ig-badge {{ $rule->is_active ? 'active' : $rule->status }}">{{ $rule->is_active ? 'Active' : ucfirst($rule->status) }}</span>
                        </div>
                        <div class="ig-form-grid">
                            <div class="ig-field"><label>Instagram Account</label><select class="ig-input" name="instagram_account_id" required>@foreach($accountOptions as $accountOption)<option value="{{ $accountOption->id }}" @selected($rule->instagram_account_id === $accountOption->id)>{{ $accountOption->ig_username ? '@' . $accountOption->ig_username : $accountOption->ig_user_id }} ({{ $accountOption->status }})</option>@endforeach</select></div>
                            <div class="ig-field"><label>Rule Name</label><input class="ig-input" name="name" value="{{ $rule->name }}" required></div>
                            <div class="ig-field"><label>Post/Reel Media ID</label><input class="ig-input" name="media_id" value="{{ $rule->media_id }}" placeholder="Optional"></div>
                            <div class="ig-field"><label>Keywords</label><input class="ig-input" name="keywords" value="{{ collect($rule->keywords ?? [])->implode(', ') }}" required></div>
                            <div class="ig-field"><label>Public Reply</label><textarea class="ig-input" name="public_reply_message" required rows="2">{{ $rule->public_reply_message }}</textarea></div>
                            <div class="ig-field"><label>DM Flow</label><select class="ig-input" name="dm_flow_id"><option value="">Public reply only</option>@foreach($flowOptions as $flowOption)<option value="{{ $flowOption->id }}" @selected($rule->dm_flow_id === $flowOption->id)>{{ $flowOption->name }}</option>@endforeach</select></div>
                            <div class="ig-field"><label>Priority</label><input class="ig-input" name="priority" type="number" value="{{ $rule->priority }}" min="1" max="999"></div>
                            <div class="ig-field"><label>Status</label><select class="ig-input" name="status"><option value="draft" @selected($rule->status === 'draft')>Draft</option><option value="active" @selected($rule->status === 'active')>Active</option><option value="paused" @selected($rule->status === 'paused')>Paused</option></select></div>
                            <div class="ig-field"><label>Active</label><label style="font-size:14px;text-transform:none;font-weight:600;color:#111827;"><input type="checkbox" name="is_active" value="1" @checked($rule->is_active)> Enable rule</label></div>
                            <div class="ig-field" style="align-self:end;"><button class="ig-btn" type="submit">Update Rule</button></div>
                        </div>
                    </form>
                    <form class="mt-3" method="POST" action="{{ route($routeBase . '.rules.toggle', $rule) }}">@csrf<button class="ig-btn" type="submit">{{ $rule->is_active ? 'Pause Rule' : 'Activate Rule' }}</button></form>
                </div>
            @empty
                <div class="ig-empty">No automation rules created yet.</div>
            @endforelse
            <div>{{ $rules->withQueryString()->links() }}</div>
        </div>
    @elseif($tab === 'dm-flows')
        <div class="ig-card">
            <h3 class="text-lg font-semibold text-slate-900 mb-4">Create DM Flow</h3>
            <form method="POST" action="{{ route($routeBase . '.flows.store') }}">
                @csrf
                <div class="ig-form-grid">
                    <div class="ig-field"><label>Flow Name</label><input class="ig-input" name="name" required></div>
                    <div class="ig-field"><label>Description</label><input class="ig-input" name="description"></div>
                    <div class="ig-field"><label>Status</label><select class="ig-input" name="status"><option value="draft">Draft</option><option value="active">Active</option><option value="paused">Paused</option></select></div>
                    <div class="ig-field"><label>Active</label><label style="font-size:14px;text-transform:none;font-weight:600;color:#111827;"><input type="checkbox" name="is_active" value="1"> Enable flow</label></div>
                </div>
                <div class="ig-form-grid mt-4">
                    <div class="ig-field"><label>Step 1 Message</label><textarea class="ig-input" name="steps[0][message_text]" rows="2" required>Hi, aapko details chahiye thi. Aap kis city se hain?</textarea></div>
                    <div class="ig-field"><label>Step 1 Save As</label><input class="ig-input" name="steps[0][save_reply_as]" value="city"></div>
                    <div class="ig-field"><label>Step 2 Message</label><textarea class="ig-input" name="steps[1][message_text]" rows="2">Please apna mobile number share kar dein.</textarea></div>
                    <div class="ig-field"><label>Step 2 Save As</label><input class="ig-input" name="steps[1][save_reply_as]" value="phone"></div>
                </div>
                <button class="ig-btn primary mt-4" type="submit">Create Flow</button>
            </form>
        </div>
        <div class="ig-list">
            @forelse($flows as $flow)
                <div class="ig-card">
                    <form method="POST" action="{{ route($routeBase . '.flows.update', $flow) }}">
                        @csrf
                        @method('PUT')
                        <div class="ig-header mb-4">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-900">{{ $flow->name }}</h3>
                                <p class="ig-muted">Steps: {{ $flow->steps_count }} | Rules: {{ $flow->automation_rules_count }}</p>
                            </div>
                            <span class="ig-badge {{ $flow->is_active ? 'active' : $flow->status }}">{{ $flow->is_active ? 'Active' : ucfirst($flow->status) }}</span>
                        </div>
                        <div class="ig-form-grid">
                            <div class="ig-field"><label>Flow Name</label><input class="ig-input" name="name" value="{{ $flow->name }}" required></div>
                            <div class="ig-field"><label>Description</label><input class="ig-input" name="description" value="{{ $flow->description }}"></div>
                            <div class="ig-field"><label>Status</label><select class="ig-input" name="status"><option value="draft" @selected($flow->status === 'draft')>Draft</option><option value="active" @selected($flow->status === 'active')>Active</option><option value="paused" @selected($flow->status === 'paused')>Paused</option></select></div>
                            <div class="ig-field"><label>Active</label><label style="font-size:14px;text-transform:none;font-weight:600;color:#111827;"><input type="checkbox" name="is_active" value="1" @checked($flow->is_active)> Enable flow</label></div>
                        </div>
                        <div class="ig-form-grid mt-4">
                            @for($i = 0; $i < 3; $i++)
                                @php($step = $flow->steps->get($i))
                                <div class="ig-field"><label>Step {{ $i + 1 }} Message</label><textarea class="ig-input" name="steps[{{ $i }}][message_text]" rows="2">{{ $step?->message_text }}</textarea></div>
                                <div class="ig-field"><label>Step {{ $i + 1 }} Save As</label><input class="ig-input" name="steps[{{ $i }}][save_reply_as]" value="{{ $step?->save_reply_as }}"></div>
                            @endfor
                        </div>
                        <button class="ig-btn mt-4" type="submit">Update Flow</button>
                    </form>
                    <form class="mt-3" method="POST" action="{{ route($routeBase . '.flows.toggle', $flow) }}">@csrf<button class="ig-btn" type="submit">{{ $flow->is_active ? 'Pause Flow' : 'Activate Flow' }}</button></form>
                </div>
            @empty
                <div class="ig-empty">No DM flows created yet.</div>
            @endforelse
            <div>{{ $flows->withQueryString()->links() }}</div>
        </div>
    @elseif($tab === 'conversations')
        <div class="ig-card overflow-x-auto">
            <h3 class="text-lg font-semibold text-slate-900 mb-4">Conversations</h3>
            <table class="ig-table">
                <thead><tr><th>User</th><th>Account</th><th>Collected</th><th>Lead</th><th>Duplicate</th><th>Step</th><th>Status</th><th>Human Takeover</th><th>Actions</th></tr></thead>
                <tbody>
                @forelse($conversations as $conversation)
                    @php($fields = $conversation->collected_fields ?? [])
                    <tr>
                        <td>{{ $conversation->instagram_username ? '@' . $conversation->instagram_username : $conversation->instagram_user_id }}</td>
                        <td>{{ $conversation->instagramAccount?->ig_username ? '@' . $conversation->instagramAccount->ig_username : 'Unknown' }}</td>
                        <td>
                            <div>City: <strong>{{ $fields['city'] ?? 'N/A' }}</strong></div>
                            <div>Phone: <strong>{{ $fields['phone'] ?? 'N/A' }}</strong></div>
                        </td>
                        <td>
                            @if($conversation->lead)
                                <a href="{{ route('leads.show', $conversation->lead) }}">{{ $conversation->lead->name }}</a>
                            @else
                                No lead yet
                            @endif
                            <div class="ig-muted">{{ $conversation->automationRule?->name ?? 'N/A' }}</div>
                        </td>
                        <td>
                            @if($conversation->duplicateLead)
                                <a href="{{ route('leads.show', $conversation->duplicateLead) }}">#{{ $conversation->duplicateLead->id }}</a>
                            @else
                                N/A
                            @endif
                        </td>
                        <td>
                            {{ $conversation->state?->completed ? 'Completed' : ('Step ' . ($conversation->state?->current_step ?? 'N/A')) }}
                            <div class="ig-muted">{{ $conversation->state?->current_field ?: 'No field waiting' }}</div>
                        </td>
                        <td><span class="ig-badge {{ $conversation->status }}">{{ ucfirst($conversation->status) }}</span></td>
                        <td>{{ $conversation->is_human_taken_over ? 'Paused' : 'Automation allowed' }}</td>
                        <td>
                            @if($conversation->is_human_taken_over)
                                <form method="POST" action="{{ route($routeBase . '.conversations.resume', $conversation) }}">
                                    @csrf
                                    <button class="ig-btn" type="submit">Resume</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route($routeBase . '.conversations.takeover', $conversation) }}">
                                    @csrf
                                    <button class="ig-btn" type="submit">Take Over</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9"><div class="ig-empty">No Instagram conversations yet.</div></td></tr>
                @endforelse
                </tbody>
            </table>
            <div class="mt-4">{{ $conversations->withQueryString()->links() }}</div>
        </div>
    @elseif($tab === 'logs')
        <div class="ig-card overflow-x-auto">
            <div class="ig-header mb-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Webhook Logs</h3>
                    <p class="ig-muted">Read-only event ingestion status from Meta webhooks.</p>
                </div>
            </div>
            <form method="GET" action="{{ route($routeBase . '.index') }}" class="ig-actions mb-4">
                <input type="hidden" name="tab" value="logs">
                <select name="status" class="border border-slate-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">All statuses</option>
                    @foreach(['received', 'queued', 'processed', 'ignored', 'failed'] as $statusOption)
                        <option value="{{ $statusOption }}" @selected(($logFilters['status'] ?? '') === $statusOption)>{{ ucfirst($statusOption) }}</option>
                    @endforeach
                </select>
                <select name="event_type" class="border border-slate-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">All event types</option>
                    @foreach(['comments', 'messages', 'message_reactions', 'unknown'] as $eventTypeOption)
                        <option value="{{ $eventTypeOption }}" @selected(($logFilters['event_type'] ?? '') === $eventTypeOption)>{{ ucfirst(str_replace('_', ' ', $eventTypeOption)) }}</option>
                    @endforeach
                </select>
                <select name="account_id" class="border border-slate-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">All accounts</option>
                    @foreach($accounts as $accountOption)
                        <option value="{{ $accountOption->id }}" @selected((string) ($logFilters['account_id'] ?? '') === (string) $accountOption->id)>{{ $accountOption->ig_username ? '@' . $accountOption->ig_username : $accountOption->ig_user_id }}</option>
                    @endforeach
                </select>
                <button class="ig-btn" type="submit">Filter</button>
                <a class="ig-btn" href="{{ route($routeBase . '.index', ['tab' => 'logs']) }}">Clear</a>
            </form>
            <table class="ig-table">
                <thead><tr><th>Received</th><th>Processed</th><th>Account</th><th>Object</th><th>Field</th><th>Event</th><th>Status</th><th>Error</th></tr></thead>
                <tbody>
                @forelse($webhookLogs as $log)
                    <tr>
                        <td>{{ $log->received_at?->format('d M Y h:i A') ?: $log->created_at?->format('d M Y h:i A') }}</td>
                        <td>{{ $log->processed_at?->format('d M Y h:i A') ?: 'Pending' }}</td>
                        <td>{{ $log->instagramAccount?->ig_username ? '@' . $log->instagramAccount->ig_username : 'Unknown' }}</td>
                        <td>{{ $log->object_type ?: 'N/A' }}</td>
                        <td>{{ $log->field ?: 'N/A' }}</td>
                        <td>{{ $log->event_type ?: 'Unknown' }}</td>
                        <td><span class="ig-badge {{ $log->status }}">{{ ucfirst($log->status) }}</span></td>
                        <td class="ig-muted">{{ $log->error_message ?: 'OK' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8"><div class="ig-empty">No webhook logs yet.</div></td></tr>
                @endforelse
                </tbody>
            </table>
            <div class="mt-4">{{ $webhookLogs->withQueryString()->links() }}</div>
        </div>
        <div class="ig-card overflow-x-auto">
            <h3 class="text-lg font-semibold text-slate-900 mb-4">Recent API Logs</h3>
            <table class="ig-table">
                <thead><tr><th>When</th><th>Endpoint</th><th>Method</th><th>Status Code</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($apiLogs as $log)
                    <tr>
                        <td>{{ $log->created_at?->format('d M Y h:i A') }}</td>
                        <td>{{ $log->endpoint ?: 'N/A' }}</td>
                        <td>{{ $log->method ?: 'N/A' }}</td>
                        <td>{{ $log->status_code ?: 'N/A' }}</td>
                        <td><span class="ig-badge {{ $log->status }}">{{ ucfirst($log->status) }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5"><div class="ig-empty">No API logs yet.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    @elseif($tab === 'settings')
        <div class="ig-card">
            <div class="ig-header mb-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Production Readiness</h3>
                    <p class="ig-muted">Application-side launch checks. Meta dashboard setup is still handled in Meta.</p>
                </div>
                <span class="ig-badge {{ $readiness['ready'] ? ($readiness['has_warnings'] ? 'warning' : 'ok') : 'failed' }}">
                    {{ $readiness['ready'] ? ($readiness['has_warnings'] ? 'Ready with warnings' : 'Ready') : 'Not ready' }}
                </span>
            </div>
            <div class="ig-settings mb-6">
                @foreach($readiness['summary'] as $metric => $value)
                    <div class="ig-setting">
                        <div class="ig-muted">{{ str_replace('_', ' ', ucfirst($metric)) }}</div>
                        <div class="text-lg font-semibold">{{ $value }}</div>
                    </div>
                @endforeach
            </div>
            <div class="overflow-x-auto mb-6">
                <table class="ig-table">
                    <thead><tr><th>Check</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                    @foreach($readiness['checks'] as $check)
                        <tr>
                            <td>{{ $check['name'] }}</td>
                            <td><span class="ig-badge {{ $check['status'] }}">{{ strtoupper($check['status']) }}</span></td>
                            <td class="ig-muted">{{ $check['message'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <h3 class="text-lg font-semibold text-slate-900 mb-4">Instagram App Config</h3>
            <div class="ig-settings mb-6">
                <div class="ig-setting"><div class="ig-muted">App ID</div><div class="text-lg font-semibold">{{ $configStatus['client_id_configured'] ? 'Configured' : 'Missing' }}</div></div>
                <div class="ig-setting"><div class="ig-muted">App Secret</div><div class="text-lg font-semibold">{{ $configStatus['client_secret_configured'] ? 'Configured' : 'Missing' }}</div></div>
                <div class="ig-setting"><div class="ig-muted">Webhook Verify Token</div><div class="text-lg font-semibold">{{ $configStatus['webhook_verify_token_configured'] ? 'Configured' : 'Missing' }}</div></div>
                <div class="ig-setting"><div class="ig-muted">Webhook Signature</div><div class="text-lg font-semibold">{{ $configStatus['webhook_signature_enabled'] ? 'Enabled' : 'Disabled' }}</div></div>
                <div class="ig-setting"><div class="ig-muted">Graph Version</div><div class="text-lg font-semibold">{{ $configStatus['graph_version'] }}</div></div>
                <div class="ig-setting"><div class="ig-muted">Redirect URI</div><div class="text-sm font-semibold break-all">{{ $configStatus['redirect_uri'] }}</div></div>
                <div class="ig-setting"><div class="ig-muted">Webhook URL</div><div class="text-sm font-semibold break-all">{{ $configStatus['webhook_url'] }}</div></div>
            </div>
            <div class="ig-card mb-6" style="box-shadow:none;">
                <div class="ig-muted mb-2">Requested Scopes</div>
                <div>{{ implode(', ', $configStatus['scopes']) }}</div>
            </div>

            <h3 class="text-lg font-semibold text-slate-900 mb-4">DM Flow Defaults</h3>
            <form method="POST" action="{{ route($routeBase . '.settings.update') }}" class="ig-form-grid">
                @csrf
                <div class="ig-field">
                    <label>Duplicate Check</label>
                    <label style="font-size:14px;text-transform:none;font-weight:600;color:#111827;">
                        <input type="checkbox" name="duplicate_check_enabled" value="1" @checked($settings['duplicate_check_enabled'])>
                        Enable duplicate protection
                    </label>
                </div>
                <div class="ig-field"><label>Duplicate Days Limit</label><input class="ig-input" type="number" min="1" max="365" name="duplicate_days_limit" value="{{ $settings['duplicate_days_limit'] }}" required></div>
                <div class="ig-field"><label>Max Phone Retries</label><input class="ig-input" type="number" min="1" max="10" name="max_phone_retries" value="{{ $settings['max_phone_retries'] }}" required></div>
                <div class="ig-field"><label>Invalid Phone Retry Message</label><textarea class="ig-input" name="invalid_phone_retry_message" rows="2" required>{{ $settings['invalid_phone_retry_message'] }}</textarea></div>
                <div class="ig-field"><label>Default Final Message</label><textarea class="ig-input" name="default_final_message" rows="2" required>{{ $settings['default_final_message'] }}</textarea></div>
                <div class="ig-field" style="align-self:end;"><button class="ig-btn primary" type="submit">Save Settings</button></div>
            </form>
            <div class="ig-settings mt-6">
                <div class="ig-setting"><div class="ig-muted">Default Message Type</div><div class="text-lg font-semibold">{{ ucfirst($settings['default_message_type']) }}</div></div>
                <div class="ig-setting"><div class="ig-muted">Default Rule Priority</div><div class="text-lg font-semibold">{{ $settings['default_rule_priority'] }}</div></div>
            </div>
        </div>
    @endif
</div>
@endsection

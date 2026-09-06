@extends('layouts.app')

@section('title', 'WhatsApp Control Center - ' . brand_name())
@section('page-title', 'WhatsApp Control Center')
@section('page-subtitle', 'Chats, templates, API numbers and delivery logs in one place')

@push('styles')
<style>
    .wcc-shell { width: 100%; max-width: none; margin: 0; color: #0f172a; }
    .wcc-hero { position: relative; overflow: hidden; background: linear-gradient(135deg, #f0fdfa 0%, #ffffff 46%, #eff6ff 100%); border: 1px solid #dbeafe; border-radius: 22px; padding: 24px; box-shadow: 0 18px 44px rgba(15, 23, 42, .07); }
    .wcc-hero:before { content: ""; position: absolute; inset: 0; background: linear-gradient(90deg, rgba(16,185,129,.12), transparent 34%, rgba(37,99,235,.10)); pointer-events: none; }
    .wcc-hero > * { position: relative; z-index: 1; }
    .wcc-logo { height: 58px; width: 58px; border-radius: 18px; background: #059669; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 30px; box-shadow: 0 12px 24px rgba(5,150,105,.24); }
    .wcc-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 18px; box-shadow: 0 12px 30px rgba(15, 23, 42, .055); }
    .wcc-tabs { display: flex; gap: 9px; overflow-x: auto; padding-bottom: 2px; scrollbar-width: none; }
    .wcc-tabs::-webkit-scrollbar { display: none; }
    .wcc-tab { display: inline-flex; align-items: center; gap: 8px; min-height: 42px; padding: 0 14px; border: 1px solid #dbe3ea; border-radius: 13px; background: #fff; color: #334155; font-size: 13px; font-weight: 850; white-space: nowrap; box-shadow: 0 1px 2px rgba(15,23,42,.035); transition: transform .16s ease, border-color .16s ease, box-shadow .16s ease; }
    .wcc-tab:hover { transform: translateY(-1px); border-color: #94a3b8; box-shadow: 0 7px 18px rgba(15,23,42,.07); }
    .wcc-tab.active { border-color: #10b981; background: #ecfdf5; color: #047857; box-shadow: 0 8px 20px rgba(16,185,129,.14); }
    .wcc-stat { position: relative; min-height: 118px; padding: 18px; border-radius: 18px; border: 1px solid #e2e8f0; background: #fff; box-shadow: 0 10px 24px rgba(15,23,42,.045); overflow: hidden; }
    .wcc-stat:after { content: ""; position: absolute; right: -28px; bottom: -28px; width: 94px; height: 94px; border-radius: 999px; background: #f1f5f9; }
    .wcc-stat-row { position: relative; z-index: 1; display: flex; justify-content: space-between; gap: 14px; align-items: flex-start; }
    .wcc-stat-icon { width: 42px; height: 42px; border-radius: 14px; display: flex; align-items: center; justify-content: center; color: #fff; flex: 0 0 auto; box-shadow: 0 10px 20px rgba(15,23,42,.12); }
    .wcc-stat-icon.green { background: #059669; }
    .wcc-stat-icon.blue { background: #2563eb; }
    .wcc-stat-icon.cyan { background: #0891b2; }
    .wcc-stat-icon.red { background: #dc2626; }
    .wcc-label { font-size: 11px; text-transform: uppercase; letter-spacing: .08em; color: #64748b; font-weight: 800; }
    .wcc-value { margin-top: 5px; font-size: 24px; font-weight: 900; color: #0f172a; }
    .wcc-muted { color: #64748b; font-size: 13px; }
    .wcc-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 42px; border-radius: 12px; padding: 0 15px; font-size: 13px; font-weight: 850; border: 1px solid transparent; transition: transform .16s ease, box-shadow .16s ease; }
    .wcc-btn:hover { transform: translateY(-1px); box-shadow: 0 10px 22px rgba(15,23,42,.10); }
    .wcc-btn.primary { background: #059669; color: #fff; }
    .wcc-btn.blue { background: #2563eb; color: #fff; }
    .wcc-btn.light { background: #f8fafc; color: #334155; border-color: #dbe3ea; }
    .wcc-btn.purple { background: #7c3aed; color: #fff; }
    .wcc-table { width: 100%; border-collapse: collapse; }
    .wcc-table th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .07em; color: #64748b; padding: 10px 12px; border-bottom: 1px solid #e5e7eb; }
    .wcc-table td { padding: 12px; border-bottom: 1px solid #f1f5f9; font-size: 13px; color: #0f172a; vertical-align: top; }
    .wcc-pill { display: inline-flex; align-items: center; border-radius: 999px; padding: 3px 9px; font-size: 11px; font-weight: 900; }
    .wcc-pill.green { background: #dcfce7; color: #166534; }
    .wcc-pill.yellow { background: #fef3c7; color: #92400e; }
    .wcc-pill.red { background: #fee2e2; color: #991b1b; }
    .wcc-pill.gray { background: #f1f5f9; color: #475569; }
    .wcc-form-input { width: 100%; border: 1px solid #d1d5db; border-radius: 11px; padding: 10px 12px; font-size: 14px; outline: none; }
    .wcc-form-input:focus { border-color: #059669; box-shadow: 0 0 0 3px rgba(5, 150, 105, .12); }
    .wcc-inline-form { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
    .wcc-small-input { border: 1px solid #d1d5db; border-radius: 9px; padding: 7px 9px; font-size: 12px; min-width: 150px; }
    .wcc-status-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
    .wcc-info-tile { border: 1px solid #eef2f7; border-radius: 15px; background: #f8fafc; padding: 15px 16px; min-height: 86px; }
    .wcc-error-tile { background: #fff7ed; border-color: #fed7aa; }
    .wcc-error-tile .wcc-label { color: #c2410c; }
    .wcc-flow-list { counter-reset: flow; display: grid; gap: 12px; margin-top: 16px; }
    .wcc-flow-item { counter-increment: flow; display: flex; gap: 12px; align-items: flex-start; padding: 12px; border: 1px solid #eef2f7; border-radius: 14px; background: #f8fafc; }
    .wcc-flow-item:before { content: counter(flow); width: 26px; height: 26px; border-radius: 999px; background: #0f766e; color: #fff; font-size: 12px; font-weight: 900; display: flex; align-items: center; justify-content: center; flex: 0 0 auto; }
    .wcc-section-head { display: flex; justify-content: space-between; gap: 16px; align-items: flex-start; margin-bottom: 16px; }
    .wcc-filter-bar { display: flex; align-items: end; justify-content: space-between; gap: 14px; flex-wrap: wrap; padding: 14px; border: 1px solid #e2e8f0; border-radius: 16px; background: #f8fafc; margin-bottom: 16px; }
    .wcc-account-select { min-width: 280px; max-width: 380px; }
    .wcc-quick-send { display: flex; align-items: end; gap: 10px; flex-wrap: wrap; }
    .wcc-quick-send .wcc-form-input { min-width: 230px; background: #fff; }
    .wcc-action-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .wcc-modal-backdrop { position: fixed; inset: 0; z-index: 80; display: flex; align-items: center; justify-content: center; padding: 20px; background: rgba(15, 23, 42, .58); }
    .wcc-modal-backdrop.hidden { display: none; }
    .wcc-modal-panel { width: min(720px, 100%); max-height: min(760px, calc(100vh - 40px)); overflow: auto; border-radius: 18px; background: #fff; box-shadow: 0 24px 70px rgba(15, 23, 42, .22); }
    .wcc-modal-head { display: flex; justify-content: space-between; gap: 16px; align-items: flex-start; padding: 18px 20px; border-bottom: 1px solid #e5e7eb; }
    .wcc-icon-btn { width: 36px; height: 36px; border-radius: 10px; border: 1px solid #dbe3ea; background: #f8fafc; color: #334155; display: inline-flex; align-items: center; justify-content: center; }
    .wcc-preview-phone { border: 1px solid #dbe3ea; border-radius: 18px; background: #eef7f2; padding: 16px; }
    .wcc-preview-bubble { max-width: 92%; border-radius: 14px 14px 14px 4px; background: #fff; border: 1px solid #d9eee3; padding: 13px 14px; color: #0f172a; white-space: pre-wrap; line-height: 1.55; font-size: 14px; box-shadow: 0 5px 14px rgba(15, 23, 42, .06); }
    .wcc-preview-buttons { display: grid; gap: 8px; margin-top: 10px; max-width: 92%; }
    .wcc-preview-button { border: 1px solid #bfdbfe; border-radius: 11px; background: #eff6ff; color: #1d4ed8; padding: 9px 11px; font-size: 13px; font-weight: 850; text-align: center; }
    @media (max-width: 900px) {
        .wcc-hero { padding: 20px; }
        .wcc-status-grid { grid-template-columns: 1fr; }
        .wcc-section-head { flex-direction: column; }
    }
</style>
@endpush

@section('content')
@php
    $tabs = [
        'overview' => ['label' => 'Overview', 'icon' => 'fas fa-gauge-high'],
        'chats' => ['label' => 'Chats', 'icon' => 'fas fa-comments'],
        'templates' => ['label' => 'Templates', 'icon' => 'fas fa-file-lines'],
        'send-test' => ['label' => 'Send Test', 'icon' => 'fas fa-paper-plane'],
        'campaigns' => ['label' => 'Campaigns', 'icon' => 'fas fa-bullhorn'],
        'quick-replies' => ['label' => 'Quick Replies', 'icon' => 'fas fa-reply'],
        'automation' => ['label' => 'Automation', 'icon' => 'fas fa-bolt'],
        'routing' => ['label' => 'Routing', 'icon' => 'fas fa-route'],
        'analytics' => ['label' => 'Analytics', 'icon' => 'fas fa-chart-line'],
        'health' => ['label' => 'Health', 'icon' => 'fas fa-heart-pulse'],
        'numbers' => ['label' => 'API Numbers', 'icon' => 'fas fa-sim-card'],
        'logs' => ['label' => 'Logs', 'icon' => 'fas fa-list-check'],
    ];

    $statusClass = function (?string $status) {
        return match (strtoupper((string) $status)) {
            'APPROVED', 'SENT', 'DELIVERED', 'READ' => 'green',
            'PENDING', 'IN_REVIEW', 'QUEUED', 'SENDING' => 'yellow',
            'REJECTED', 'FAILED' => 'red',
            default => 'gray',
        };
    };

    $selectedTemplateAccount = $accounts->firstWhere('id', (int) $selectedTemplateAccountId);
    $templatePreviewData = $templates->mapWithKeys(function ($template) {
        return [
            $template->id => [
                'id' => $template->id,
                'name' => $template->name,
                'status' => $template->status,
                'language' => $template->language,
                'category' => $template->category,
                'account' => $template->metaWabaAccount?->display_phone_number ?: $template->metaWabaAccount?->name,
                'content' => $template->content,
                'components' => $template->components ?: [],
                'rejection_reason' => $template->rejection_reason,
            ],
        ];
    });
    $friendlyWccIssue = function (?string $message): ?string {
        if (!$message) {
            return null;
        }

        $lower = strtolower($message);
        if (str_contains($lower, 'access token')
            || str_contains($lower, 'session has been invalidated')
            || str_contains($lower, 'password')
            || str_contains($lower, 'security reasons')) {
            return 'Meta access token invalid ho gaya hai. API Settings me new token save/reconnect karo, Verify click karo, phir Sync Templates chalao.';
        }

        return $message;
    };
@endphp

<div class="wcc-shell space-y-6">
    <div id="wcc-message" class="hidden rounded-xl border px-4 py-3 text-sm font-semibold"></div>

    <section class="wcc-hero">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-5">
            <div class="flex items-start gap-4">
                <div class="wcc-logo">
                    <i class="fab fa-whatsapp"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-black text-slate-950">WhatsApp Control Center</h2>
                    <p class="wcc-muted mt-1">One place for WhatsApp Cloud API status, templates, chats, test sending and logs.</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <span class="wcc-pill {{ $defaultAccount?->is_verified ? 'green' : 'yellow' }}">{{ $defaultAccount?->is_verified ? 'Verified' : 'Verification pending' }}</span>
                        <span class="wcc-pill {{ $defaultSender === 'meta_waba' ? 'green' : 'gray' }}">Default: {{ $defaultSender === 'meta_waba' ? 'Meta WABA' : 'Third party' }}</span>
                        <span class="wcc-pill gray">{{ $defaultAccount?->display_phone_number ?: $defaultAccount?->name ?: 'No API number selected' }}</span>
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('chat.index') }}" class="wcc-btn primary"><i class="fab fa-whatsapp"></i> Open Chats</a>
                <a href="{{ route('integrations.meta-waba.index') }}" class="wcc-btn blue"><i class="fas fa-sliders"></i> API Settings</a>
                <a href="{{ route('integrations.meta-waba.index', ['new_account' => 1]) }}" class="wcc-btn light"><i class="fas fa-plus"></i> Add Number</a>
            </div>
        </div>
    </section>

    <nav class="wcc-tabs">
        @foreach($tabs as $key => $item)
            <a href="{{ route('whatsapp-control-center.index', ['tab' => $key]) }}" class="wcc-tab {{ $tab === $key ? 'active' : '' }}">
                <i class="{{ $item['icon'] }}"></i>{{ $item['label'] }}
            </a>
        @endforeach
    </nav>

    @if($tab === 'overview')
        <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
            <div class="wcc-stat">
                <div class="wcc-stat-row">
                    <div><div class="wcc-label">API Numbers</div><div class="wcc-value">{{ $stats['accounts'] }}</div><div class="wcc-muted">{{ $stats['verified_accounts'] }} verified</div></div>
                    <div class="wcc-stat-icon green"><i class="fas fa-sim-card"></i></div>
                </div>
            </div>
            <div class="wcc-stat">
                <div class="wcc-stat-row">
                    <div><div class="wcc-label">Approved Templates</div><div class="wcc-value">{{ $stats['templates_approved'] }}</div><div class="wcc-muted">{{ $stats['templates_total'] }} total templates</div></div>
                    <div class="wcc-stat-icon blue"><i class="fas fa-file-circle-check"></i></div>
                </div>
            </div>
            <div class="wcc-stat">
                <div class="wcc-stat-row">
                    <div><div class="wcc-label">Open Chats</div><div class="wcc-value">{{ $stats['conversations'] }}</div><div class="wcc-muted">{{ $stats['unread'] }} unread, {{ $stats['unassigned_conversations'] }} unassigned</div></div>
                    <div class="wcc-stat-icon cyan"><i class="fas fa-comments"></i></div>
                </div>
            </div>
            <div class="wcc-stat">
                <div class="wcc-stat-row">
                    <div><div class="wcc-label">Failed Messages</div><div class="wcc-value">{{ $stats['messages_failed'] }}</div><div class="wcc-muted">{{ $stats['messages_sent'] }} sent records</div></div>
                    <div class="wcc-stat-icon red"><i class="fas fa-triangle-exclamation"></i></div>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-1 xl:grid-cols-3 gap-5">
            <div class="wcc-card p-6 xl:col-span-2">
                <div class="wcc-section-head">
                    <div>
                        <div class="flex items-center gap-3">
                            <span class="wcc-stat-icon green" style="width:38px;height:38px;border-radius:13px;"><i class="fab fa-whatsapp"></i></span>
                            <div>
                                <h3 class="text-lg font-black text-slate-950">Default API Number</h3>
                                <p class="wcc-muted">Current sender will stay same until you change default.</p>
                            </div>
                        </div>
                    </div>
                    <a href="{{ route('integrations.meta-waba.index') }}" class="wcc-btn light"><i class="fas fa-gear"></i> Manage</a>
                </div>
                <div class="wcc-status-grid">
                    <div class="wcc-info-tile"><div class="wcc-label">Number</div><div class="font-black text-slate-950 mt-2 text-lg">{{ $defaultAccount?->display_phone_number ?: '-' }}</div></div>
                    <div class="wcc-info-tile"><div class="wcc-label">Phone Number ID</div><div class="font-black text-slate-950 mt-2 break-all">{{ $defaultAccount?->phone_number_id ?: '-' }}</div></div>
                    <div class="wcc-info-tile"><div class="wcc-label">WABA ID</div><div class="font-black text-slate-950 mt-2 break-all">{{ $defaultAccount?->waba_id ?: '-' }}</div></div>
                    <div class="wcc-info-tile {{ $defaultAccount?->last_error ? 'wcc-error-tile' : '' }}">
                        <div class="wcc-label">Last Error</div>
                        <div class="font-semibold text-slate-900 mt-2 leading-relaxed">{{ $defaultAccount?->last_error ?: 'No recent error' }}</div>
                    </div>
                </div>
            </div>
            <div class="wcc-card p-6">
                <div class="flex items-start gap-3">
                    <span class="wcc-stat-icon blue" style="width:38px;height:38px;border-radius:13px;"><i class="fas fa-list-check"></i></span>
                    <div>
                        <h3 class="text-lg font-black text-slate-950">Reviewer Quick Flow</h3>
                        <p class="wcc-muted mt-1">Meta review ke liye minimum working checks.</p>
                    </div>
                </div>
                <ol class="wcc-flow-list text-sm text-slate-700">
                    <li class="wcc-flow-item">Connect or verify API number.</li>
                    <li class="wcc-flow-item">Sync approved templates.</li>
                    <li class="wcc-flow-item">Send one approved template test.</li>
                    <li class="wcc-flow-item">Reply on WhatsApp and check Chats/Logs.</li>
                </ol>
                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="{{ route('legal.privacy') }}" class="wcc-btn light">Privacy</a>
                    <a href="{{ route('legal.terms') }}" class="wcc-btn light">Terms</a>
                    <a href="{{ route('legal.data-deletion') }}" class="wcc-btn light">Data deletion</a>
                </div>
            </div>
        </section>
    @elseif($tab === 'chats')
        <section class="wcc-card p-5">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-4">
                <div><h3 class="text-lg font-black text-slate-950">Team WhatsApp Inbox</h3><p class="wcc-muted">Assign, resolve and track open WhatsApp conversations.</p></div>
                <a href="{{ route('chat.index') }}" class="wcc-btn primary"><i class="fas fa-comments"></i> Open Full Inbox</a>
            </div>
            <div class="mb-4 flex flex-wrap gap-2">
                @foreach(['all' => 'All', 'mine' => 'Mine', 'unassigned' => 'Unassigned', 'unread' => 'Unread'] as $filterKey => $filterLabel)
                    <a href="{{ route('whatsapp-control-center.index', ['tab' => 'chats', 'chat_filter' => $filterKey]) }}" class="wcc-tab {{ $chatFilter === $filterKey ? 'active' : '' }}">{{ $filterLabel }}</a>
                @endforeach
            </div>
            <div class="overflow-x-auto">
                <table class="wcc-table">
                    <thead><tr><th>Contact</th><th>Lead / Owner</th><th>Assign</th><th>Status</th><th>Unread</th><th>Opt-out</th><th>Note</th></tr></thead>
                    <tbody>
                        @forelse($recentConversations as $conversation)
                            @php $leadOwner = $conversation->lead?->activeAssignments?->first()?->assignedTo; @endphp
                            <tr>
                                <td>
                                    <div class="font-bold">{{ $conversation->contact_name ?: 'Unknown' }}</div>
                                    <div class="wcc-muted">{{ $conversation->phone_number }}</div>
                                    <a href="{{ route('chat.index') }}" class="text-green-700 font-bold text-xs">Open chat</a>
                                </td>
                                <td>
                                    <div class="font-bold">{{ $conversation->lead?->name ?: '-' }}</div>
                                    <div class="wcc-muted">Owner: {{ $leadOwner?->name ?: 'Not assigned' }}</div>
                                    <div class="wcc-muted">Updated: {{ $conversation->updated_at?->format('d M, h:i A') }}</div>
                                </td>
                                <td>
                                    <select class="wcc-small-input" onchange="assignConversation({{ $conversation->id }}, this.value)">
                                        <option value="">Unassigned</option>
                                        @foreach($assignableUsers as $user)
                                            <option value="{{ $user->id }}" @selected((int) $conversation->assigned_to === (int) $user->id)>{{ $user->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <select class="wcc-small-input" onchange="updateConversationStatus({{ $conversation->id }}, this.value)">
                                        @foreach(['open' => 'Open', 'pending_reply' => 'Pending reply', 'resolved' => 'Resolved'] as $statusKey => $statusLabel)
                                            <option value="{{ $statusKey }}" @selected(($conversation->status ?: 'open') === $statusKey)>{{ $statusLabel }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><span class="wcc-pill {{ ($conversation->unread_count ?? 0) > 0 ? 'yellow' : 'gray' }}">{{ $conversation->unread_count ?? 0 }}</span></td>
                                <td>
                                    @if($conversation->lead)
                                        <button type="button" class="wcc-btn light" onclick="toggleLeadOptOut({{ $conversation->lead->id }}, {{ $conversation->lead->whatsapp_opted_out_at ? 'false' : 'true' }})">
                                            {{ $conversation->lead->whatsapp_opted_out_at ? 'Opt in' : 'Opt out' }}
                                        </button>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    <form class="wcc-inline-form" onsubmit="saveConversationNote(event, {{ $conversation->id }})">
                                        <input class="wcc-small-input" name="note" placeholder="Add note" required>
                                        <button class="wcc-btn light" type="submit">Save</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-slate-500">No WhatsApp conversations yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @elseif($tab === 'templates')
        <section class="wcc-card p-5">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-4">
                <div>
                    <h3 class="text-lg font-black text-slate-950">Templates</h3>
                    <p class="wcc-muted">{{ $templateTabStats['approved'] }} approved, {{ $templateTabStats['pending'] }} pending, {{ $templateTabStats['rejected'] }} rejected for selected number.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('template-management.create', ['meta_waba_account_id' => $selectedTemplateAccountId]) }}" class="wcc-btn blue"><i class="fas fa-plus"></i> New Template</a>
                    <button type="button" class="wcc-btn light" onclick="syncTemplates(this)"><i class="fas fa-rotate"></i> Refresh Status</button>
                    <button type="button" class="wcc-btn primary" onclick="syncTemplates(this)"><i class="fas fa-rotate"></i> Sync Templates</button>
                </div>
            </div>
            <div class="wcc-filter-bar">
                <form method="GET" action="{{ route('whatsapp-control-center.index') }}" class="wcc-account-select">
                    <input type="hidden" name="tab" value="templates">
                    <input type="hidden" name="status_filter" value="{{ $templateStatusFilter }}">
                    <label>
                        <span class="wcc-label">Show templates for API number</span>
                        <select name="account_id" class="wcc-form-input mt-1" onchange="this.form.submit()">
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}" @selected((int) $selectedTemplateAccountId === (int) $account->id)>
                                    {{ $account->display_phone_number ?: $account->name ?: ('Account #' . $account->id) }}{{ $account->is_default ? ' (Default)' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                </form>
                <div class="wcc-quick-send">
                    <label>
                        <span class="wcc-label">Single test number</span>
                        <input id="quickTemplatePhone" class="wcc-form-input mt-1" inputmode="tel" placeholder="919369205635">
                    </label>
                    <div class="flex flex-wrap gap-2">
                        @foreach([
                            'all' => ['label' => $templateTabStats['total'] . ' Total', 'class' => 'gray'],
                            'approved' => ['label' => $templateTabStats['approved'] . ' Approved', 'class' => 'green'],
                            'pending' => ['label' => $templateTabStats['pending'] . ' Pending', 'class' => 'yellow'],
                            'rejected' => ['label' => $templateTabStats['rejected'] . ' Rejected', 'class' => 'red'],
                        ] as $filterKey => $filter)
                            <a href="{{ route('whatsapp-control-center.index', ['tab' => 'templates', 'account_id' => $selectedTemplateAccountId, 'status_filter' => $filterKey === 'all' ? null : $filterKey]) }}"
                               class="wcc-pill {{ $filter['class'] }} {{ $templateStatusFilter === $filterKey ? 'ring-2 ring-offset-2 ring-slate-300' : '' }}">
                                {{ $filter['label'] }}
                            </a>
                        @endforeach
                    </div>
                </div>
                @if($selectedTemplateAccount?->last_error)
                    <div class="w-full rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">
                        Selected API number issue: {{ $friendlyWccIssue($selectedTemplateAccount->last_error) }}
                    </div>
                @endif
                <div id="quickTemplateResult" class="hidden w-full rounded-xl border px-4 py-3 text-sm font-semibold"></div>
            </div>
            <div class="overflow-x-auto">
                <table class="wcc-table">
                    <thead><tr><th>Name</th><th>Status</th><th>Language</th><th>Account</th><th>Content</th><th>Actions</th></tr></thead>
                    <tbody>
                        @forelse($templates as $template)
                            <tr>
                                <td class="font-bold">{{ $template->name }}</td>
                                <td><span class="wcc-pill {{ $statusClass($template->status) }}">{{ $template->status }}</span></td>
                                <td>{{ $template->language }}</td>
                                <td>{{ $template->metaWabaAccount?->display_phone_number ?: $template->metaWabaAccount?->name ?: '-' }}</td>
                                <td>{{ \Illuminate\Support\Str::limit($template->content, 120) }}</td>
                                <td>
                                    <div class="wcc-action-row">
                                        <button type="button"
                                                class="wcc-btn light"
                                                onclick="openTemplatePreview({{ $template->id }})">
                                            <i class="fas fa-eye"></i> Preview
                                        </button>
                                        <button type="button"
                                                class="wcc-btn light"
                                                onclick="sendTemplateQuick({{ $template->id }}, this)"
                                                @disabled($template->status !== 'APPROVED')>
                                            <i class="fas fa-paper-plane"></i> Send
                                        </button>
                                        <button type="button"
                                                class="wcc-btn light text-red-700"
                                                onclick="deleteTemplate({{ $template->id }}, @js($template->name), this)">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-slate-500">No templates synced yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div id="templatePreviewModal" class="wcc-modal-backdrop hidden" onclick="closeTemplatePreview(event)">
                <div class="wcc-modal-panel" role="dialog" aria-modal="true" aria-labelledby="templatePreviewTitle" onclick="event.stopPropagation()">
                    <div class="wcc-modal-head">
                        <div>
                            <h3 id="templatePreviewTitle" class="text-lg font-black text-slate-950">Template Preview</h3>
                            <div id="templatePreviewMeta" class="wcc-muted mt-1"></div>
                        </div>
                        <button type="button" class="wcc-icon-btn" onclick="closeTemplatePreview()" aria-label="Close template preview">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="p-5 space-y-4">
                        <div class="flex flex-wrap gap-2" id="templatePreviewBadges"></div>
                        <div class="wcc-preview-phone">
                            <div id="templatePreviewBody" class="wcc-preview-bubble"></div>
                            <div id="templatePreviewButtons" class="wcc-preview-buttons"></div>
                        </div>
                        <div id="templatePreviewRejection" class="hidden rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800"></div>
                    </div>
                </div>
            </div>
        </section>
    @elseif($tab === 'send-test')
        <section class="grid grid-cols-1 xl:grid-cols-3 gap-5">
            <form class="wcc-card p-5 xl:col-span-2" onsubmit="sendTestTemplate(event)">
                @csrf
                @if($selectedTemplateAccountId)
                    <input type="hidden" name="meta_waba_account_id" value="{{ $selectedTemplateAccountId }}">
                @endif
                <h3 class="text-lg font-black text-slate-950">Send Approved Template Test</h3>
                <p class="wcc-muted mt-1">Only approved templates are available here.</p>
                <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <label>
                        <span class="wcc-label">Recipient phone</span>
                        <div class="mt-1 grid grid-cols-[150px_minmax(0,1fr)] gap-2">
                            <select name="country_code" class="wcc-form-input">
                                <option value="91" selected>+91 India</option>
                                <option value="971">+971 UAE</option>
                                <option value="1">+1 US/Canada</option>
                                <option value="44">+44 UK</option>
                                <option value="61">+61 Australia</option>
                                <option value="65">+65 Singapore</option>
                            </select>
                            <input name="recipient_phone" class="wcc-form-input" placeholder="9369205635" inputmode="numeric" autocomplete="tel-national" required>
                        </div>
                    </label>
                    <label><span class="wcc-label">Template</span><select name="template_id" class="wcc-form-input mt-1" required>
                        <option value="">Select approved template</option>
                        @foreach($approvedTemplates as $template)
                            <option value="{{ $template->id }}">{{ $template->name }} ({{ $template->language }})</option>
                        @endforeach
                    </select></label>
                </div>
                <label class="block mt-4"><span class="wcc-label">Variables, comma separated</span><input name="parameters" class="wcc-form-input mt-1" placeholder="Name, Project, Amount"></label>
                <button type="submit" class="wcc-btn primary mt-5"><i class="fas fa-paper-plane"></i> Send Test</button>
            </form>
            <div class="wcc-card p-5">
                <h3 class="text-lg font-black text-slate-950">Before Sending</h3>
                <p class="wcc-muted mt-2">Template must be approved. If message fails, check Logs tab for token, permission, PIN or Meta account error.</p>
            </div>
            <div class="wcc-card p-5 xl:col-span-3">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-2 mb-3">
                    <div>
                        <h3 class="text-lg font-black text-slate-950">Recent Test / Template Results</h3>
                        <p class="wcc-muted">Yahan latest template sends ka success/failed status dikhega.</p>
                    </div>
                    <a href="{{ route('whatsapp-control-center.index', ['tab' => 'send-test']) }}" class="wcc-btn light"><i class="fas fa-rotate"></i> Refresh</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="wcc-table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Phone</th>
                                <th>Template / Message</th>
                                <th>API Number</th>
                                <th>Status</th>
                                <th>Message ID / Error</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentTestMessages as $message)
                                @php
                                    $messageStatus = strtolower((string) ($message->status ?: $message->provider_status ?: 'sent'));
                                    $statusTone = $messageStatus === 'failed' ? 'red' : (in_array($messageStatus, ['delivered', 'read', 'sent'], true) ? 'green' : 'yellow');
                                    $messageId = $message->external_message_id ?: $message->message_id;
                                @endphp
                                <tr>
                                    <td>{{ optional($message->created_at)->format('d M, h:i A') }}</td>
                                    <td class="font-bold">{{ $message->conversation?->phone_number ?: '-' }}</td>
                                    <td>{{ \Illuminate\Support\Str::limit($message->message ?: ('Template #' . $message->template_id), 70) }}</td>
                                    <td>{{ $message->metaWabaAccount?->display_phone_number ?: $message->metaWabaAccount?->name ?: 'Default' }}</td>
                                    <td><span class="wcc-pill {{ $statusTone }}">{{ strtoupper($messageStatus) }}</span></td>
                                    <td class="text-xs {{ $messageStatus === 'failed' ? 'text-red-700' : 'text-slate-500' }}">
                                        {{ $messageStatus === 'failed' ? ($message->error_message ?: 'Failed') : ($messageId ?: 'Accepted') }}
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-slate-500">No test/template results yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    @elseif($tab === 'campaigns')
        <section class="grid grid-cols-1 xl:grid-cols-3 gap-5">
            <div class="wcc-card p-5 xl:col-span-2">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-4">
                    <div><h3 class="text-lg font-black text-slate-950">Bulk Campaigns</h3><p class="wcc-muted">Create/schedule campaigns from API Settings; monitor progress here.</p></div>
                    <a href="{{ route('integrations.meta-waba.index', ['tab' => 'campaigns']) }}#campaigns" class="wcc-btn primary"><i class="fas fa-plus"></i> Create Campaign</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="wcc-table">
                        <thead><tr><th>Name</th><th>Template</th><th>Status</th><th>Progress</th><th>Account</th><th>Action</th></tr></thead>
                        <tbody>
                            @forelse($recentCampaigns as $campaign)
                                <tr>
                                    <td class="font-bold">{{ $campaign->name }}</td>
                                    <td>{{ $campaign->template?->name ?: '-' }}</td>
                                    <td><span class="wcc-pill {{ $statusClass($campaign->status) }}">{{ $campaign->status }}</span></td>
                                    <td>{{ $campaign->sent_count }}/{{ $campaign->total_recipients }} sent, {{ $campaign->failed_count }} failed</td>
                                    <td>{{ $campaign->metaWabaAccount?->display_phone_number ?: $campaign->metaWabaAccount?->name ?: 'Default' }}</td>
                                    <td><a href="{{ route('whatsapp-control-center.index', ['tab' => 'campaigns', 'campaign_id' => $campaign->id]) }}" class="wcc-btn light">Recipients</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-slate-500">No WABA campaigns yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="wcc-card p-5">
                <h3 class="text-lg font-black text-slate-950">{{ $selectedCampaign ? 'Recipients' : 'Campaign Rules' }}</h3>
                @if($selectedCampaign)
                    <p class="wcc-muted mt-1">{{ $selectedCampaign->name }}</p>
                    <div class="mt-4 max-h-[520px] overflow-auto">
                        <table class="wcc-table">
                            <thead><tr><th>Phone</th><th>Status</th><th>Retry</th></tr></thead>
                            <tbody>
                                @forelse($campaignRecipients as $recipient)
                                    <tr>
                                        <td>{{ $recipient->phone }}<br><span class="wcc-muted">{{ $recipient->lead?->name ?: $recipient->name }}</span></td>
                                        <td><span class="wcc-pill {{ $statusClass($recipient->status) }}">{{ $recipient->status }}</span><br><span class="wcc-muted">{{ $recipient->error_message }}</span></td>
                                        <td>
                                            @if($recipient->status === \App\Models\WabaCampaignRecipient::STATUS_FAILED)
                                                <button type="button" class="wcc-btn light" onclick="retryRecipient({{ $recipient->id }})">Retry</button>
                                            @else
                                                <span class="wcc-muted">{{ $recipient->retry_count ?? 0 }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-slate-500">No recipients found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="wcc-muted mt-2">Opted-out leads are skipped. Failed recipients can be retried from campaign recipients or Logs tab.</p>
                @endif
            </div>
        </section>
    @elseif($tab === 'quick-replies')
        <section class="grid grid-cols-1 xl:grid-cols-3 gap-5">
            <form class="wcc-card p-5" onsubmit="saveQuickReply(event)">
                @csrf
                <h3 class="text-lg font-black text-slate-950">Create Quick Reply</h3>
                <label class="block mt-4"><span class="wcc-label">Title</span><input name="title" class="wcc-form-input mt-1" required></label>
                <label class="block mt-4"><span class="wcc-label">Message</span><textarea name="message" class="wcc-form-input mt-1" rows="5" required></textarea></label>
                <label class="mt-4 flex items-center gap-2 text-sm font-bold text-slate-700"><input type="checkbox" name="is_active" value="1" checked> Active</label>
                <button type="submit" class="wcc-btn primary mt-4">Save Quick Reply</button>
            </form>
            <div class="wcc-card p-5 xl:col-span-2">
                <h3 class="text-lg font-black text-slate-950 mb-4">Reusable Replies</h3>
                <div class="overflow-x-auto">
                    <table class="wcc-table">
                        <thead><tr><th>Title</th><th>Message</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody>
                            @forelse($quickReplies as $reply)
                                <tr>
                                    <td class="font-bold">{{ $reply->title }}</td>
                                    <td>{{ \Illuminate\Support\Str::limit($reply->message, 140) }}</td>
                                    <td><span class="wcc-pill {{ $reply->is_active ? 'green' : 'gray' }}">{{ $reply->is_active ? 'Active' : 'Inactive' }}</span></td>
                                    <td><button type="button" class="wcc-btn light" onclick="deleteQuickReply({{ $reply->id }})">Delete</button></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-slate-500">No quick replies yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    @elseif($tab === 'automation')
        <section class="grid grid-cols-1 xl:grid-cols-4 gap-4">
            <div class="wcc-stat"><div class="wcc-label">Active Journeys</div><div class="wcc-value">{{ $automationOverview['active_automations'] ?? 0 }}</div><div class="wcc-muted">Enabled automation flows</div></div>
            <div class="wcc-stat"><div class="wcc-label">Sent Today</div><div class="wcc-value">{{ $automationOverview['sent_today'] ?? 0 }}</div><div class="wcc-muted">Automation sends</div></div>
            <div class="wcc-stat"><div class="wcc-label">Failed</div><div class="wcc-value">{{ $automationOverview['failed'] ?? 0 }}</div><div class="wcc-muted">Needs review</div></div>
            <div class="wcc-stat"><div class="wcc-label">Blocked</div><div class="wcc-value">{{ $automationOverview['blocked'] ?? 0 }}</div><div class="wcc-muted">Compliance/duplicate blocks</div></div>
        </section>
        <section class="grid grid-cols-1 xl:grid-cols-2 gap-5">
            <div class="wcc-card p-5">
                <div class="flex items-center justify-between gap-3 mb-4">
                    <div><h3 class="text-lg font-black text-slate-950">Automation Builder</h3><p class="wcc-muted">Create journeys, rules, triggers and template mappings.</p></div>
                    <a href="{{ route(auth()->user()->isCrm() ? 'crm.whatsapp-automation.index' : 'admin.whatsapp-automation.index') }}" class="wcc-btn primary">Open Builder</a>
                </div>
                <table class="wcc-table">
                    <thead><tr><th>Journey</th><th>Status</th><th>Rules</th><th>Logs</th></tr></thead>
                    <tbody>
                        @forelse($automationJourneys as $journey)
                            <tr>
                                <td><strong>{{ $journey->name }}</strong><br><span class="wcc-muted">{{ $journey->description }}</span></td>
                                <td><span class="wcc-pill {{ $journey->is_active ? 'green' : 'gray' }}">{{ $journey->status }}</span></td>
                                <td>{{ $journey->rules_count }}</td>
                                <td>{{ $journey->logs_count }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-slate-500">No automation journeys yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="wcc-card p-5">
                <h3 class="text-lg font-black text-slate-950 mb-4">Active Rules</h3>
                <table class="wcc-table">
                    <thead><tr><th>Rule</th><th>Trigger</th><th>Sender</th><th>Limits</th></tr></thead>
                    <tbody>
                        @forelse($automationRules as $rule)
                            <tr>
                                <td><strong>{{ $rule->name }}</strong><br><span class="wcc-muted">{{ $rule->journey?->name }}</span></td>
                                <td>{{ str_replace('_', ' ', $rule->trigger) }}</td>
                                <td>{{ $rule->metaWabaAccount?->display_phone_number ?: 'Routing/default' }}</td>
                                <td>{{ $rule->daily_send_cap ?? 3 }}/day, {{ $rule->cooldown_minutes ?? 0 }}m cooldown</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-slate-500">No automation rules yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @elseif($tab === 'routing')
        <section class="grid grid-cols-1 xl:grid-cols-3 gap-5">
            <form class="wcc-card p-5" onsubmit="saveRoutingRule(event)">
                @csrf
                <h3 class="text-lg font-black text-slate-950">Create Routing Rule</h3>
                <p class="wcc-muted mt-1">Rule match hoga to selected API number use hoga; warna default number safe rahega.</p>
                <label class="block mt-4"><span class="wcc-label">Rule name</span><input name="name" class="wcc-form-input mt-1" required></label>
                <label class="block mt-4"><span class="wcc-label">Priority</span><input name="priority" type="number" min="1" max="999" value="100" class="wcc-form-input mt-1" required></label>
                <label class="block mt-4"><span class="wcc-label">Send from API number</span><select name="meta_waba_account_id" class="wcc-form-input mt-1" required>
                    <option value="">Select number</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->id }}">{{ $account->display_phone_number ?: $account->name }}</option>
                    @endforeach
                </select></label>
                <label class="block mt-4"><span class="wcc-label">Sources, comma separated</span><input name="conditions[sources_text]" class="wcc-form-input mt-1" placeholder="Meta, Website"></label>
                <label class="block mt-4"><span class="wcc-label">Cities, comma separated</span><input name="conditions[cities_text]" class="wcc-form-input mt-1" placeholder="Lucknow, Kanpur"></label>
                <label class="block mt-4"><span class="wcc-label">Projects, comma separated</span><input name="conditions[projects_text]" class="wcc-form-input mt-1" placeholder="Base Infra"></label>
                <label class="mt-4 flex items-center gap-2 text-sm font-bold text-slate-700"><input type="checkbox" name="is_active" value="1" checked> Active</label>
                <button type="submit" class="wcc-btn primary mt-4" @disabled(!$canManageWhatsApp)>Save Rule</button>
                @unless($canManageWhatsApp)<p class="wcc-muted mt-2">Only admin or whatsapp.manage role can change routing.</p>@endunless
            </form>
            <div class="wcc-card p-5 xl:col-span-2">
                <h3 class="text-lg font-black text-slate-950 mb-4">Routing Rules</h3>
                <table class="wcc-table">
                    <thead><tr><th>Rule</th><th>Conditions</th><th>Sender</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                        @forelse($routingRules as $rule)
                            <tr>
                                <td><strong>{{ $rule->name }}</strong><br><span class="wcc-muted">Priority {{ $rule->priority }}</span></td>
                                <td>
                                    @foreach(($rule->conditions ?? []) as $key => $values)
                                        <div><strong>{{ str_replace('_', ' ', $key) }}:</strong> {{ implode(', ', (array) $values) }}</div>
                                    @endforeach
                                    @if(empty($rule->conditions))<span class="wcc-muted">Matches all leads</span>@endif
                                </td>
                                <td>{{ $rule->metaWabaAccount?->display_phone_number ?: $rule->metaWabaAccount?->name }}</td>
                                <td><span class="wcc-pill {{ $rule->is_active ? 'green' : 'gray' }}">{{ $rule->is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td><button type="button" class="wcc-btn light" onclick="deleteRoutingRule({{ $rule->id }})" @disabled(!$canManageWhatsApp)>Delete</button></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-slate-500">No routing rules. Sending will use selected campaign number or default number.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @elseif($tab === 'analytics')
        <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4">
            @foreach(['sent' => 'Sent', 'delivered' => 'Delivered', 'read' => 'Read', 'failed' => 'Failed', 'replied' => 'Replies'] as $key => $label)
                <div class="wcc-stat"><div class="wcc-label">{{ $label }}</div><div class="wcc-value">{{ $analytics['totals'][$key] ?? 0 }}</div><div class="wcc-muted">Selected period</div></div>
            @endforeach
        </section>
        <section class="wcc-card p-5">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-5">
                <input type="hidden" name="tab" value="analytics">
                <label><span class="wcc-label">From</span><input type="date" name="date_from" value="{{ request('date_from') }}" class="wcc-form-input mt-1"></label>
                <label><span class="wcc-label">To</span><input type="date" name="date_to" value="{{ request('date_to') }}" class="wcc-form-input mt-1"></label>
                <label><span class="wcc-label">API Number</span><select name="account_id" class="wcc-form-input mt-1"><option value="">All numbers</option>@foreach($accounts as $account)<option value="{{ $account->id }}" @selected((string) request('account_id') === (string) $account->id)>{{ $account->display_phone_number ?: $account->name }}</option>@endforeach</select></label>
                <div class="flex items-end"><button class="wcc-btn primary w-full" type="submit">Filter</button></div>
            </form>
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
                <div><h3 class="text-lg font-black text-slate-950 mb-3">Campaign Status</h3>@foreach(($analytics['campaigns'] ?? collect()) as $status => $total)<div class="flex justify-between border-b py-2"><span>{{ $status }}</span><strong>{{ $total }}</strong></div>@endforeach</div>
                <div><h3 class="text-lg font-black text-slate-950 mb-3">Automation Status</h3>@foreach(($analytics['automation'] ?? collect()) as $status => $total)<div class="flex justify-between border-b py-2"><span>{{ $status }}</span><strong>{{ $total }}</strong></div>@endforeach</div>
                <div><h3 class="text-lg font-black text-slate-950 mb-3">Recent Campaigns</h3>@forelse(($analytics['campaign_list'] ?? collect()) as $campaign)<div class="border-b py-2"><strong>{{ $campaign->name }}</strong><br><span class="wcc-muted">{{ $campaign->status }} | {{ $campaign->metaWabaAccount?->display_phone_number ?: 'Default' }}</span></div>@empty<p class="wcc-muted">No campaigns.</p>@endforelse</div>
            </div>
        </section>
    @elseif($tab === 'health')
        <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
            <div class="wcc-stat"><div class="wcc-label">Default Account</div><div class="wcc-value">{{ $health['default_account_verified'] ? 'OK' : 'Check' }}</div><div class="wcc-muted">{{ $defaultAccount?->display_phone_number ?: 'No default number' }}</div></div>
            <div class="wcc-stat"><div class="wcc-label">Queue Pending</div><div class="wcc-value">{{ $health['queue_pending'] }}</div><div class="wcc-muted">Automation jobs</div></div>
            <div class="wcc-stat"><div class="wcc-label">Open Sessions</div><div class="wcc-value">{{ $health['open_sessions'] }}</div><div class="wcc-muted">24-hour inbound windows</div></div>
            <div class="wcc-stat"><div class="wcc-label">Opted Out</div><div class="wcc-value">{{ $health['opted_out_leads'] }}</div><div class="wcc-muted">Blocked from sends</div></div>
        </section>
        <section class="grid grid-cols-1 xl:grid-cols-2 gap-5">
            <div class="wcc-card p-5">
                <h3 class="text-lg font-black text-slate-950 mb-4">Readable Errors</h3>
                <div class="space-y-3 text-sm text-slate-700">
                    <div><strong>Token expired:</strong> API Settings me token rotate karke Save + Verify karein.</div>
                    <div><strong>Permission #200:</strong> System user/app ko WhatsApp account full access aur whatsapp_business_messaging permission chahiye.</div>
                    <div><strong>Template issue:</strong> Templates tab me Sync karein, sirf approved template send karein.</div>
                    <div><strong>Customer opted out:</strong> Lead ko manually opt-in tabhi karein jab customer consent de.</div>
                    <div><strong>Outside 24-hour window:</strong> Normal text ke bajay approved template use karein.</div>
                </div>
            </div>
            <div class="wcc-card p-5">
                <h3 class="text-lg font-black text-slate-950 mb-4">Recent Automation Failures</h3>
                <table class="wcc-table">
                    <thead><tr><th>Lead</th><th>Trigger</th><th>Reason</th></tr></thead>
                    <tbody>
                        @forelse($automationLogs->where('status', \App\Models\WhatsAppAutomationLog::STATUS_FAILED)->take(8) as $log)
                            <tr><td>{{ $log->lead?->name ?: '-' }}</td><td>{{ $log->trigger }}</td><td>{{ $log->failure_reason ?: '-' }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-slate-500">No recent automation failures.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @elseif($tab === 'numbers')
        <section class="wcc-card p-5">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-4">
                <div><h3 class="text-lg font-black text-slate-950">Connected API Numbers</h3><p class="wcc-muted">Add new number separately; current default remains untouched.</p></div>
                <a href="{{ route('integrations.meta-waba.index', ['new_account' => 1]) }}" class="wcc-btn blue"><i class="fas fa-plus"></i> Add API Number</a>
            </div>
            <div class="overflow-x-auto">
                <table class="wcc-table">
                    <thead><tr><th>Account</th><th>Phone Number ID</th><th>WABA ID</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                        @forelse($accounts as $account)
                            <tr>
                                <td><strong>{{ $account->display_phone_number ?: $account->name }}</strong>@if($account->is_default)<span class="wcc-pill green ml-2">Default</span>@endif</td>
                                <td class="break-all">{{ $account->phone_number_id ?: '-' }}</td>
                                <td class="break-all">{{ $account->waba_id ?: '-' }}</td>
                                <td><span class="wcc-pill {{ $account->is_verified ? 'green' : 'yellow' }}">{{ $account->is_verified ? 'Verified' : ($account->connection_status ?: 'Pending') }}</span></td>
                                <td><a href="{{ route('integrations.meta-waba.index', ['account_id' => $account->id]) }}" class="wcc-btn light">Manage</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-slate-500">No API number connected yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @else
        <section class="grid grid-cols-1 xl:grid-cols-2 gap-5">
            <div class="wcc-card p-5">
                <h3 class="text-lg font-black text-slate-950 mb-4">Recent Message Logs</h3>
                <div class="overflow-x-auto">
                    <table class="wcc-table">
                        <thead><tr><th>Phone</th><th>Direction</th><th>Status</th><th>Error</th><th>Time</th></tr></thead>
                        <tbody>
                            @forelse($recentMessages as $message)
                                <tr>
                                    <td>{{ $message->conversation?->phone_number ?: '-' }}</td>
                                    <td>{{ $message->direction }}</td>
                                    <td><span class="wcc-pill {{ $statusClass($message->status) }}">{{ $message->status }}</span></td>
                                    <td>{{ $message->error_message ?: '-' }}</td>
                                    <td>{{ $message->created_at?->format('d M, h:i A') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-slate-500">No Meta WABA message logs yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="wcc-card p-5">
                <div class="flex items-center justify-between gap-3 mb-4">
                    <h3 class="text-lg font-black text-slate-950">Failed Recipients</h3>
                    <a href="{{ route('whatsapp-control-center.index', ['tab' => 'campaigns']) }}" class="wcc-btn light">Open Campaigns</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="wcc-table">
                        <thead><tr><th>Campaign</th><th>Phone</th><th>Error</th><th>Action</th></tr></thead>
                        <tbody>
                            @forelse($failedRecipients as $recipient)
                                <tr>
                                    <td>{{ $recipient->campaign?->name ?: '-' }}</td>
                                    <td>{{ $recipient->phone }}</td>
                                    <td>{{ $recipient->error_message ?: 'Meta delivery failed.' }}</td>
                                    <td><button type="button" class="wcc-btn light" onclick="retryRecipient({{ $recipient->id }})">Retry</button></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-slate-500">No failed campaign recipients.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    @endif
</div>

@push('scripts')
<script>
window.wccTemplatePreviewData = @json($templatePreviewData);

function wccEscapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    })[char]);
}

function wccTemplateStatusClass(status) {
    const normalized = String(status || '').toUpperCase();
    if (['APPROVED', 'SENT', 'DELIVERED', 'READ'].includes(normalized)) return 'green';
    if (['PENDING', 'IN_REVIEW', 'QUEUED', 'SENDING'].includes(normalized)) return 'yellow';
    if (['REJECTED', 'FAILED'].includes(normalized)) return 'red';
    return 'gray';
}

function wccTemplateComponents(template) {
    const components = template?.components;
    if (Array.isArray(components)) return components;
    if (typeof components === 'string') {
        try {
            const parsed = JSON.parse(components);
            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            return [];
        }
    }
    return [];
}

function wccTemplateText(template) {
    const components = wccTemplateComponents(template);
    const header = components.find((component) => String(component.type || '').toUpperCase() === 'HEADER')?.text || '';
    const body = template?.content || components.find((component) => String(component.type || '').toUpperCase() === 'BODY')?.text || '';
    const footer = components.find((component) => String(component.type || '').toUpperCase() === 'FOOTER')?.text || '';

    return [header, body, footer].filter((line) => String(line || '').trim()).join('\n\n');
}

function wccTemplateButtons(template) {
    return wccTemplateComponents(template)
        .filter((component) => String(component.type || '').toUpperCase() === 'BUTTONS')
        .flatMap((component) => Array.isArray(component.buttons) ? component.buttons : [])
        .map((button) => {
            const type = String(button.type || '').replace(/_/g, ' ');
            const text = button.text || button.title || type || 'Button';
            const suffix = button.phone_number ? ` (${button.phone_number})` : '';
            return `${text}${suffix}`;
        });
}

function openTemplatePreview(templateId) {
    const template = window.wccTemplatePreviewData?.[templateId];
    if (!template) {
        showWccMessage(false, 'Template preview data missing.');
        return;
    }

    document.getElementById('templatePreviewTitle').textContent = template.name || 'Template Preview';
    document.getElementById('templatePreviewMeta').textContent = [template.account, template.language, template.category].filter(Boolean).join(' · ');
    document.getElementById('templatePreviewBadges').innerHTML = [
        `<span class="wcc-pill ${wccTemplateStatusClass(template.status)}">${wccEscapeHtml(template.status || 'UNKNOWN')}</span>`,
        template.language ? `<span class="wcc-pill gray">${wccEscapeHtml(template.language)}</span>` : '',
        template.category ? `<span class="wcc-pill gray">${wccEscapeHtml(template.category)}</span>` : '',
    ].filter(Boolean).join('');
    document.getElementById('templatePreviewBody').textContent = wccTemplateText(template) || 'No content available.';

    const buttons = wccTemplateButtons(template);
    document.getElementById('templatePreviewButtons').innerHTML = buttons.length
        ? buttons.map((button) => `<div class="wcc-preview-button">${wccEscapeHtml(button)}</div>`).join('')
        : '';

    const rejection = document.getElementById('templatePreviewRejection');
    if (template.rejection_reason) {
        rejection.textContent = template.rejection_reason;
        rejection.classList.remove('hidden');
    } else {
        rejection.textContent = '';
        rejection.classList.add('hidden');
    }

    document.getElementById('templatePreviewModal').classList.remove('hidden');
}

function closeTemplatePreview(event) {
    if (event && event.target?.id !== 'templatePreviewModal') return;
    document.getElementById('templatePreviewModal')?.classList.add('hidden');
}

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') closeTemplatePreview();
});

function normalizeWccMessage(success, message) {
    const text = (message || '').toString();
    const lower = text.toLowerCase();
    if (!success && (lower.includes('access token') || lower.includes('session has been invalidated'))) {
        return 'Selected API number ka Meta access token invalid/expired hai. API Settings me reconnect/verify karke phir send karo.';
    }

    return text || (success ? 'Done' : 'Something went wrong');
}

function showWccMessage(success, message, targetId = 'wcc-message') {
    const box = document.getElementById(targetId) || document.getElementById('wcc-message');
    if (!box) return;
    box.className = 'rounded-xl border px-4 py-3 text-sm font-semibold ' + (success ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800');
    box.textContent = normalizeWccMessage(success, message);
    box.classList.remove('hidden');
    box.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
}

async function syncTemplates(button = null) {
    const originalHtml = button?.innerHTML;
    try {
        if (button) {
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing...';
        }
        const selectedAccountId = @json($selectedTemplateAccountId);
        const syncUrl = new URL(@json(route('integrations.meta-waba.templates.sync')), window.location.origin);
        if (selectedAccountId) {
            syncUrl.searchParams.set('account_id', selectedAccountId);
        }
        const response = await fetch(syncUrl.toString(), {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': @json(csrf_token()), 'Accept': 'application/json'},
        });
        const data = await response.json();
        showWccMessage(!!data.success, data.message || data.error);
        if (data.success) setTimeout(() => window.location.reload(), 900);
    } catch (error) {
        showWccMessage(false, 'Template sync failed. Open API Settings and check token/permissions.');
        if (button) {
            button.disabled = false;
            button.innerHTML = originalHtml;
        }
    }
}

async function sendTestTemplate(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const formData = new FormData(form);
    const rawParams = (formData.get('parameters') || '').toString();
    formData.delete('parameters');
    rawParams.split(',').map(item => item.trim()).filter(Boolean).forEach(item => formData.append('parameters[]', item));

    try {
        const response = await fetch(@json(route('integrations.meta-waba.test-template')), {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': @json(csrf_token()), 'Accept': 'application/json'},
            body: formData,
        });
        const data = await response.json();
        showWccMessage(!!data.success, data.message || data.error || (data.success ? 'Meta accepted template send.' : 'Template send failed.'));
        setTimeout(() => window.location.reload(), 1200);
    } catch (error) {
        showWccMessage(false, 'Test send failed. Check approved template, recipient phone and API number status.');
    }
}

async function sendTemplateQuick(templateId, button) {
    const phoneInput = document.getElementById('quickTemplatePhone');
    const phone = (phoneInput?.value || '').trim();
    if (!phone) {
        showWccMessage(false, 'Single test number enter karo.', 'quickTemplateResult');
        phoneInput?.focus();
        return;
    }

    const formData = new FormData();
    formData.append('recipient_phone', phone);
    formData.append('template_id', templateId);
    const selectedAccountId = @json($selectedTemplateAccountId);
    if (selectedAccountId) {
        formData.append('meta_waba_account_id', selectedAccountId);
    }

    const original = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending';

    try {
        const response = await fetch(@json(route('integrations.meta-waba.test-template')), {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': @json(csrf_token()), 'Accept': 'application/json'},
            body: formData,
        });
        const data = await response.json();
        showWccMessage(!!data.success, data.message || data.error || (data.success ? 'Template sent.' : 'Template send failed.'), 'quickTemplateResult');
    } catch (error) {
        showWccMessage(false, 'Template send failed. Check selected API number token/permissions.', 'quickTemplateResult');
    } finally {
        button.disabled = false;
        button.innerHTML = original;
    }
}

async function deleteTemplate(templateId, templateName, button) {
    if (!confirm(`Delete template "${templateName}"?`)) return;

    const original = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting';

    try {
        const deleteUrl = @json(route('integrations.meta-waba.templates.delete', ['template' => '__TEMPLATE_ID__'])).replace('__TEMPLATE_ID__', templateId);
        const response = await fetch(deleteUrl, {
            method: 'DELETE',
            headers: {'X-CSRF-TOKEN': @json(csrf_token()), 'Accept': 'application/json'},
        });
        const data = await response.json();
        showWccMessage(!!data.success, data.message || data.error || 'Template delete failed.', 'quickTemplateResult');
        if (data.success) setTimeout(() => window.location.reload(), 700);
    } catch (error) {
        showWccMessage(false, 'Template delete failed. Check API token/permissions.', 'quickTemplateResult');
    } finally {
        button.disabled = false;
        button.innerHTML = original;
    }
}

async function postForm(url, formData, method = 'POST') {
    const response = await fetch(url, {
        method,
        headers: {'X-CSRF-TOKEN': @json(csrf_token()), 'Accept': 'application/json'},
        body: formData,
    });
    const data = await response.json();
    showWccMessage(!!data.success, data.message);
    if (!response.ok || !data.success) throw new Error(data.message || 'Request failed');
    return data;
}

async function assignConversation(id, assignedTo) {
    const formData = new FormData();
    if (assignedTo) formData.append('assigned_to', assignedTo);
    await postForm(`/whatsapp-control-center/conversations/${id}/assign`, formData).catch(() => {});
}

async function updateConversationStatus(id, status) {
    const formData = new FormData();
    formData.append('status', status);
    await postForm(`/whatsapp-control-center/conversations/${id}/status`, formData).then(() => {
        if (status === 'resolved') setTimeout(() => window.location.reload(), 700);
    }).catch(() => {});
}

async function saveConversationNote(event, id) {
    event.preventDefault();
    await postForm(`/whatsapp-control-center/conversations/${id}/notes`, new FormData(event.currentTarget)).then(() => {
        event.currentTarget.reset();
    }).catch(() => {});
}

async function saveQuickReply(event) {
    event.preventDefault();
    await postForm(@json(route('whatsapp-control-center.quick-replies.store')), new FormData(event.currentTarget)).then(() => {
        setTimeout(() => window.location.reload(), 700);
    }).catch(() => {});
}

async function deleteQuickReply(id) {
    if (!confirm('Delete this quick reply?')) return;
    const formData = new FormData();
    await postForm(`/whatsapp-control-center/quick-replies/${id}`, formData, 'DELETE').then(() => {
        setTimeout(() => window.location.reload(), 700);
    }).catch(() => {});
}

function appendCsv(formData, sourceName, targetName) {
    const raw = (formData.get(sourceName) || '').toString();
    formData.delete(sourceName);
    raw.split(',').map(item => item.trim()).filter(Boolean).forEach(item => formData.append(targetName, item));
}

async function saveRoutingRule(event) {
    event.preventDefault();
    const formData = new FormData(event.currentTarget);
    appendCsv(formData, 'conditions[sources_text]', 'conditions[sources][]');
    appendCsv(formData, 'conditions[cities_text]', 'conditions[cities][]');
    appendCsv(formData, 'conditions[projects_text]', 'conditions[projects][]');

    await postForm(@json(route('whatsapp-control-center.routing-rules.store')), formData).then(() => {
        setTimeout(() => window.location.reload(), 700);
    }).catch(() => {});
}

async function deleteRoutingRule(id) {
    if (!confirm('Delete this routing rule?')) return;
    await postForm(`/whatsapp-control-center/routing-rules/${id}`, new FormData(), 'DELETE').then(() => {
        setTimeout(() => window.location.reload(), 700);
    }).catch(() => {});
}

async function retryRecipient(id) {
    const formData = new FormData();
    await postForm(`/whatsapp-control-center/campaign-recipients/${id}/retry`, formData).then(() => {
        setTimeout(() => window.location.reload(), 900);
    }).catch(() => {});
}

async function toggleLeadOptOut(leadId, optedOut) {
    const reason = optedOut ? prompt('Opt-out reason?', 'Manual opt-out from WhatsApp Control Center') : '';
    if (optedOut && reason === null) return;
    const formData = new FormData();
    formData.append('opted_out', optedOut ? '1' : '0');
    if (reason) formData.append('reason', reason);
    await postForm(`/whatsapp-control-center/leads/${leadId}/whatsapp-opt-out`, formData).then(() => {
        setTimeout(() => window.location.reload(), 700);
    }).catch(() => {});
}
</script>
@endpush
@endsection

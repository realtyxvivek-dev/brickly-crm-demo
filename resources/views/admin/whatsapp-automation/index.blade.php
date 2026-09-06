@extends('layouts.app')

@section('title', 'WhatsApp Automation')
@section('page-title', 'WhatsApp Automation')

@push('styles')
<style>
    .wa-shell{display:grid;gap:24px}.wa-tabs{display:flex;flex-wrap:wrap;gap:10px}.wa-tab{padding:10px 16px;border-radius:999px;border:1px solid #d1d5db;background:#fff;color:#374151;font-size:14px;font-weight:600}.wa-tab.active{background:#0f766e;color:#fff;border-color:#0f766e}.wa-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px}.wa-card{background:#fff;border:1px solid #e5e7eb;border-radius:18px;padding:20px;box-shadow:0 10px 30px rgba(15,23,42,.05)}.wa-kpi{font-size:28px;font-weight:700;color:#111827}.wa-muted{color:#6b7280;font-size:13px}.wa-header{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}.wa-list-actions,.wa-actions{display:flex;gap:8px;flex-wrap:wrap}.wa-btn{display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:12px;font-size:13px;font-weight:600;border:1px solid #d1d5db;background:#fff;color:#111827;text-decoration:none}.wa-btn.primary{background:#0f766e;color:#fff;border-color:#0f766e}.wa-btn.soft{background:#ecfeff;border-color:#a5f3fc;color:#155e75}.wa-badge{display:inline-flex;align-items:center;padding:6px 10px;border-radius:999px;font-size:12px;font-weight:700}.wa-badge.active{background:#dcfce7;color:#166534}.wa-badge.paused{background:#fef3c7;color:#92400e}.wa-badge.draft{background:#e5e7eb;color:#374151}.wa-table{width:100%;border-collapse:collapse}.wa-table th,.wa-table td{padding:12px 10px;border-bottom:1px solid #e5e7eb;text-align:left;font-size:14px;vertical-align:top}.wa-table th{font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:#6b7280}.wa-empty{padding:30px;border:1px dashed #cbd5e1;border-radius:16px;text-align:center;color:#64748b;background:#fff}
</style>
@endpush

@section('content')
<div class="wa-shell">
    @if(session('success'))
        <div class="wa-card" style="background:#ecfdf5;border-color:#a7f3d0;color:#166534;">{{ session('success') }}</div>
    @endif

    <div class="wa-tabs">
        @foreach(['overview' => 'Overview', 'journeys' => 'Journeys', 'templates' => 'Templates', 'rules' => 'Rules', 'logs' => 'Logs', 'analytics' => 'Analytics'] as $tabKey => $tabLabel)
            <a href="{{ route($routeBase . '.index', ['tab' => $tabKey]) }}" class="wa-tab {{ $tab === $tabKey ? 'active' : '' }}">{{ $tabLabel }}</a>
        @endforeach
    </div>

    @if($tab === 'overview')
        <div class="wa-grid">
            <div class="wa-card"><div class="wa-kpi">{{ $overview['active_automations'] }}</div><div class="wa-muted">Active automations</div></div>
            <div class="wa-card"><div class="wa-kpi">{{ $overview['sent_today'] }}</div><div class="wa-muted">Sent today</div></div>
            <div class="wa-card"><div class="wa-kpi">{{ $overview['delivered'] }}</div><div class="wa-muted">Delivered</div></div>
            <div class="wa-card"><div class="wa-kpi">{{ $overview['read'] }}</div><div class="wa-muted">Read</div></div>
            <div class="wa-card"><div class="wa-kpi">{{ $overview['replied'] }}</div><div class="wa-muted">Replied</div></div>
            <div class="wa-card"><div class="wa-kpi">{{ $overview['failed'] }}</div><div class="wa-muted">Failed sends</div></div>
            <div class="wa-card"><div class="wa-kpi">{{ $overview['blocked'] }}</div><div class="wa-muted">Opt-outs / blocked</div></div>
        </div>
        <div class="wa-card">
            <div class="wa-header">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Top performing journeys</h3>
                    <p class="wa-muted">Preset-first summary for easy daily handling.</p>
                </div>
            </div>
            <div class="mt-4">
                @forelse($overview['top_journeys'] as $row)
                    <div class="flex items-center justify-between py-3 border-b border-slate-100">
                        <div class="font-medium text-slate-900">{{ $row->journey?->name ?? 'Unknown Journey' }}</div>
                        <div class="wa-muted">{{ $row->total }} send(s)</div>
                    </div>
                @empty
                    <div class="wa-empty">No journey activity yet.</div>
                @endforelse
            </div>
        </div>
    @elseif($tab === 'journeys')
        <div class="wa-header">
            <div><h3 class="text-lg font-semibold text-slate-900">Journey presets</h3><p class="wa-muted">Admin lands here first. Toggle, clone, and edit without technical complexity.</p></div>
            <a href="{{ route($routeBase . '.journeys.create') }}" class="wa-btn primary">Create journey</a>
        </div>
        <div class="wa-grid">
            @foreach($journeys as $journey)
                <div class="wa-card">
                    <div class="wa-header">
                        <div><h4 class="text-base font-semibold text-slate-900">{{ $journey->name }}</h4><p class="wa-muted">{{ $journey->description ?: 'Template-driven CRM journey.' }}</p></div>
                        <span class="wa-badge {{ $journey->is_active ? 'active' : ($journey->status === 'paused' ? 'paused' : 'draft') }}">{{ $journey->is_active ? 'Active' : ucfirst($journey->status) }}</span>
                    </div>
                    <div class="mt-4 wa-muted">Rules: {{ $journey->rules_count }} | Logs: {{ $journey->logs_count }}</div>
                    <div class="mt-2 wa-muted">Template: {{ $journey->template?->name ?? 'Not selected' }}</div>
                    <div class="wa-list-actions mt-4">
                        <a class="wa-btn" href="{{ route($routeBase . '.journeys.edit', $journey) }}">Edit</a>
                        <form method="POST" action="{{ route($routeBase . '.journeys.toggle', $journey) }}">@csrf<button class="wa-btn" type="submit">{{ $journey->is_active ? 'Pause' : 'Activate' }}</button></form>
                        <form method="POST" action="{{ route($routeBase . '.journeys.clone', $journey) }}">@csrf<button class="wa-btn" type="submit">Clone</button></form>
                        <a class="wa-btn soft" href="{{ route($routeBase . '.rules.create', ['journey_id' => $journey->id]) }}">Add rule</a>
                    </div>
                </div>
            @endforeach
        </div>
    @elseif($tab === 'templates')
        <div class="wa-card overflow-x-auto">
            <h3 class="text-lg font-semibold text-slate-900 mb-4">Templates ready for automation</h3>
            <table class="wa-table">
                <thead><tr><th>Template</th><th>Category</th><th>Language</th><th>Slots</th><th>Preview text</th></tr></thead>
                <tbody>
                @forelse($templates as $template)
                    <tr>
                        <td>{{ $template['name'] }}</td>
                        <td>{{ $template['category'] ?: 'General' }}</td>
                        <td>{{ $template['language'] ?: 'en_US' }}</td>
                        <td>{{ $template['placeholder_count'] }}</td>
                        <td class="wa-muted">{{ \Illuminate\Support\Str::limit($template['content'], 120) ?: 'No body synced yet.' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">No WhatsApp templates found. Sync them on the WhatsApp Integration page first.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    @elseif($tab === 'rules')
        <div class="wa-header">
            <div><h3 class="text-lg font-semibold text-slate-900">Rules</h3><p class="wa-muted">Source-wise, project-wise, or fallback rules for each trigger.</p></div>
            <a href="{{ route($routeBase . '.rules.create') }}" class="wa-btn primary">Create rule</a>
        </div>
        <div class="wa-card overflow-x-auto">
            <table class="wa-table">
                <thead><tr><th>Rule</th><th>Journey</th><th>Trigger</th><th>Template</th><th>Priority</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                @forelse($rules as $rule)
                    <tr>
                        <td>{{ $rule->name }}</td>
                        <td>{{ $rule->journey?->name }}</td>
                        <td>{{ $availableTriggers[$rule->trigger] ?? $rule->trigger }}</td>
                        <td>{{ $rule->template?->name ?? $rule->journey?->template?->name ?? 'Not selected' }}</td>
                        <td>{{ $rule->priority }}</td>
                        <td><span class="wa-badge {{ $rule->is_active ? 'active' : ($rule->status === 'paused' ? 'paused' : 'draft') }}">{{ $rule->is_active ? 'Active' : ucfirst($rule->status) }}</span></td>
                        <td><div class="wa-list-actions"><a class="wa-btn" href="{{ route($routeBase . '.rules.edit', $rule) }}">Edit</a><form method="POST" action="{{ route($routeBase . '.rules.toggle', $rule) }}">@csrf<button class="wa-btn" type="submit">{{ $rule->is_active ? 'Pause' : 'Activate' }}</button></form></div></td>
                    </tr>
                @empty
                    <tr><td colspan="7">No rules created yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    @elseif($tab === 'logs')
        <div class="wa-card overflow-x-auto">
            <h3 class="text-lg font-semibold text-slate-900 mb-4">Execution logs</h3>
            <table class="wa-table">
                <thead><tr><th>When</th><th>Journey</th><th>Lead</th><th>Trigger</th><th>Template</th><th>Status</th><th>Reason</th></tr></thead>
                <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td>{{ $log->created_at?->format('d M Y h:i A') }}</td>
                        <td>{{ $log->journey?->name ?? 'Unknown' }}</td>
                        <td>{{ $log->lead?->name ?? 'Lead removed' }}</td>
                        <td>{{ $availableTriggers[$log->trigger] ?? $log->trigger }}</td>
                        <td>{{ $log->template_name ?: 'N/A' }}</td>
                        <td>{{ ucfirst($log->status) }}</td>
                        <td class="wa-muted">{{ $log->failure_reason ?: 'OK' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7">No logs yet.</td></tr>
                @endforelse
                </tbody>
            </table>
            <div class="mt-4">{{ $logs->links() }}</div>
        </div>
    @elseif($tab === 'analytics')
        <div class="wa-grid">
            <div class="wa-card"><h3 class="text-lg font-semibold text-slate-900 mb-4">Journey performance</h3>@forelse($analytics['journey_performance'] as $row)<div class="flex items-center justify-between py-2 border-b border-slate-100"><span>{{ $row->journey?->name ?? 'Unknown' }}</span><span class="wa-muted">{{ $row->total }}</span></div>@empty<div class="wa-empty">No analytics yet.</div>@endforelse</div>
            <div class="wa-card"><h3 class="text-lg font-semibold text-slate-900 mb-4">Template performance</h3>@forelse($analytics['template_performance'] as $row)<div class="flex items-center justify-between py-2 border-b border-slate-100"><span>{{ $row->template_name }}</span><span class="wa-muted">{{ $row->total }}</span></div>@empty<div class="wa-empty">No analytics yet.</div>@endforelse</div>
            <div class="wa-card"><h3 class="text-lg font-semibold text-slate-900 mb-4">Source performance</h3>@forelse($analytics['source_performance'] as $row)<div class="flex items-center justify-between py-2 border-b border-slate-100"><span>{{ $row->source ?: 'Unknown' }}</span><span class="wa-muted">{{ $row->total }}</span></div>@empty<div class="wa-empty">No analytics yet.</div>@endforelse</div>
            <div class="wa-card"><h3 class="text-lg font-semibold text-slate-900 mb-4">Trigger failures</h3>@forelse($analytics['trigger_failures'] as $row)<div class="flex items-center justify-between py-2 border-b border-slate-100"><span>{{ $availableTriggers[$row->trigger] ?? $row->trigger }}</span><span class="wa-muted">{{ $row->total }}</span></div>@empty<div class="wa-empty">No failures yet.</div>@endforelse</div>
        </div>
    @endif
</div>
@endsection

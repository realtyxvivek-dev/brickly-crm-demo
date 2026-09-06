@extends('layouts.app')

@section('title', 'BulkSMSPlans IVR - ' . brand_name())
@section('page-title', 'BulkSMSPlans IVR')
@section('page-subtitle', 'Inbound IVR webhook for calls, recordings, DTMF and missed-call tasks')

@section('header-actions')
    <a href="{{ route('integrations.index') }}"
       class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 text-sm font-medium">
        <i class="fas fa-arrow-left mr-2"></i> Back to Integrations
    </a>
@endsection

@section('content')
@php
    $sourceAutomationRule = \App\Models\SourceAutomationRule::query()
        ->where('source_type', 'ivr')
        ->where(function ($query) {
            $query->whereNull('source_id')->orWhere('source_id', '');
        })
        ->where('is_active', true)
        ->first();
    $sourceAutomationUrl = route('admin.automation.create', [
        'source_type' => 'ivr',
        'source_label' => 'IVR Leads',
        'return_to' => request()->fullUrl(),
    ]);
@endphp
<div class="mb-6 rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="text-sm font-black text-gray-900">Source Automation</div>
            <div class="mt-1 text-sm text-gray-500">IVR leads ke liye common assignment rule.</div>
            @unless($sourceAutomationRule)
                <div class="mt-2 inline-flex rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">Automation setup pending</div>
            @endunless
        </div>
        <a href="{{ $sourceAutomationRule ? route('admin.automation.edit', $sourceAutomationRule) : $sourceAutomationUrl }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-100">
            <i class="fas fa-bolt"></i> Setup Automation
        </a>
    </div>
</div>
<div class="max-w-5xl mx-auto space-y-5">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-bold text-gray-900 flex items-center gap-2">
                <span class="w-9 h-9 rounded-lg flex items-center justify-center" style="background:rgba(0,122,255,.12);color:var(--primary-color);">
                    <i class="fas fa-phone-volume"></i>
                </span>
                IVR Settings
            </h2>
            <label class="flex items-center gap-3 cursor-pointer">
                <span class="text-sm font-medium text-gray-700">Integration</span>
                <input type="checkbox" id="ivr-enabled" class="h-4 w-4" {{ $settings->is_enabled ? 'checked' : '' }}>
                <span class="text-sm font-semibold {{ $settings->is_enabled ? 'text-green-600' : 'text-gray-500' }}">
                    {{ $settings->is_enabled ? 'Enabled' : 'Disabled' }}
                </span>
            </label>
        </div>

        <div class="p-6 space-y-5">
            <div id="settings-alert" class="hidden p-3 rounded-lg text-sm"></div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Webhook URL</label>
                <div class="flex gap-2">
                    <input id="webhook-url" type="text" value="{{ $webhookUrl }}" readonly class="flex-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm font-mono">
                    <button type="button" onclick="copyField('webhook-url', this)" class="px-3 py-2 bg-gray-100 border border-gray-200 rounded-lg text-sm font-semibold">
                        <i class="fas fa-copy mr-1"></i> Copy
                    </button>
                </div>
                <p class="text-xs text-gray-500 mt-1">Give this URL to BulkSMSPlans support after enabling the integration.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Webhook Token</label>
                    <div class="flex gap-2">
                        <input id="token-input" type="text" value="{{ $settings->token }}" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="Generate or enter token">
                        <button type="button" onclick="generateToken()" class="px-3 py-2 border border-blue-200 text-blue-700 rounded-lg text-sm">Generate</button>
                        <button type="button" onclick="copyField('token-input', this)" class="px-3 py-2 bg-gray-100 border border-gray-200 rounded-lg text-sm font-semibold">
                            <i class="fas fa-copy mr-1"></i> Copy
                        </button>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Provider must send this as <code>X-IVR-Token</code>. Query-string tokens are rejected.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fallback User</label>
                    <select id="fallback-user" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="">Auto-pick first active admin</option>
                        @foreach($fallbackUsers as $user)
                            <option value="{{ $user->id }}" @selected((int) $settings->fallback_user_id === (int) $user->id)>
                                {{ $user->name }}{{ $user->role ? ' (' . $user->role->name . ')' : '' }}{{ $user->phone ? ' - ' . $user->phone : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <label class="flex items-center gap-2 rounded-lg border border-gray-200 p-3 text-sm">
                    <input id="auto-create-lead" type="checkbox" class="h-4 w-4" {{ $settings->auto_create_lead ? 'checked' : '' }}>
                    Auto-create lead
                </label>
                <label class="flex items-center gap-2 rounded-lg border border-gray-200 p-3 text-sm">
                    <input id="missed-task" type="checkbox" class="h-4 w-4" {{ $settings->create_missed_call_task ? 'checked' : '' }}>
                    Missed-call task
                </label>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Default Source</label>
                    <input id="default-source" type="text" value="{{ $settings->default_source ?: 'ivr' }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
            </div>

            <div class="rounded-xl border border-orange-200 bg-orange-50 p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="font-semibold text-orange-900 flex items-center gap-2">
                            <i class="fas fa-triangle-exclamation"></i>
                            Vendor Test Mode
                        </h3>
                        <p class="mt-1 text-sm text-orange-800">
                            Use only when BulkSMSPlans cannot send a token during testing. It accepts webhook calls without token until the selected expiry time.
                        </p>
                        @if($settings->acceptsTokenlessTest())
                            <p class="mt-2 text-xs font-semibold text-orange-900">
                                Active until {{ $settings->tokenless_testing_expires_at->format('d M Y, h:i A') }}.
                            </p>
                        @endif
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 min-w-[280px]">
                        <label class="flex items-center gap-2 rounded-lg border border-orange-200 bg-white/70 px-3 py-2 text-sm font-medium text-orange-900">
                            <input id="allow-tokenless-testing" type="checkbox" class="h-4 w-4" {{ $settings->acceptsTokenlessTest() ? 'checked' : '' }}>
                            Allow no-token test
                        </label>
                        <select id="tokenless-testing-minutes" class="rounded-lg border border-orange-200 bg-white px-3 py-2 text-sm text-orange-900">
                            <option value="30">30 minutes</option>
                            <option value="60" selected>1 hour</option>
                            <option value="180">3 hours</option>
                            <option value="360">6 hours</option>
                            <option value="1440">24 hours</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-blue-100 bg-blue-50 p-4">
                <div class="grid grid-cols-1 md:grid-cols-[1fr_2fr] gap-4 items-start">
                    <div>
                        <h3 class="font-semibold text-blue-900 flex items-center gap-2">
                            <i class="fas fa-shield-alt"></i>
                            Allowed Vendor IPs
                        </h3>
                        <p class="mt-1 text-sm text-blue-700">
                            Requests from these IPs can be accepted even if the provider cannot send a token.
                        </p>
                    </div>
                    <div>
                        <textarea id="allowed-webhook-ips" rows="3" class="w-full px-3 py-2 border border-blue-200 rounded-lg text-sm font-mono" placeholder="37.58.58.215">{{ implode("\n", $settings->allowedWebhookIps()) }}</textarea>
                        <p class="mt-1 text-xs text-blue-700">Add one IP per line. Current BulkSMSPlans test IP: <code>37.58.58.215</code>.</p>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-3">
                <button type="button" onclick="saveSettings()" class="px-5 py-2.5 rounded-lg text-white text-sm font-semibold" style="background:linear-gradient(135deg,var(--primary-color),var(--secondary-color));">
                    <i class="fas fa-save mr-2"></i> Save Settings
                </button>
                <button type="button" onclick="runTest()" class="px-5 py-2.5 rounded-lg bg-orange-500 text-white text-sm font-semibold">
                    <i class="fas fa-flask mr-2"></i> Send Test Payload
                </button>
                <button type="button" onclick="copyHeaderLine(this)" class="px-5 py-2.5 rounded-lg bg-slate-900 text-white text-sm font-semibold">
                    <i class="fas fa-key mr-2"></i> Copy Header
                </button>
                <button type="button" onclick="copyVendorConfig(this)" class="px-5 py-2.5 rounded-lg border border-blue-200 bg-blue-50 text-blue-700 text-sm font-semibold">
                    <i class="fas fa-file-lines mr-2"></i> Copy Vendor Config
                </button>
            </div>
        </div>
    </div>

    <div class="bg-blue-50 border border-blue-100 rounded-xl p-5">
        <h3 class="font-semibold text-blue-800 mb-3"><i class="fas fa-circle-info mr-2"></i>Provider instructions</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm text-blue-700">
            <p><strong>Header:</strong> ask BulkSMSPlans to send <code>X-IVR-Token</code>.</p>
            <p><strong>Phone mapping:</strong> customer number maps to lead phone; agent phone maps to CRM user phone.</p>
            <p><strong>Payload:</strong> flexible fields are accepted until final provider payload is confirmed.</p>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800"><i class="fas fa-list-alt text-blue-500 mr-2"></i>Recent Webhook Hits</h3>
            <span class="text-xs text-gray-400">last 10</span>
        </div>
        @if($recentLogs->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-xs text-gray-500 uppercase">
                            <th class="px-5 py-3">Time</th>
                            <th class="px-3 py-3">Customer</th>
                            <th class="px-3 py-3">Agent</th>
                            <th class="px-3 py-3">Status</th>
                            <th class="px-3 py-3">DTMF</th>
                            <th class="px-3 py-3">Lead</th>
                            <th class="px-3 py-3">Result</th>
                            <th class="px-3 py-3">Message</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($recentLogs as $log)
                            <tr class="{{ $log->status === 'failed' ? 'bg-red-50' : '' }}">
                                <td class="px-5 py-3 text-xs text-gray-500 whitespace-nowrap">{{ $log->created_at->format('d M, H:i:s') }}</td>
                                <td class="px-3 py-3 font-mono text-xs">{{ $log->customer_phone ?: '-' }}</td>
                                <td class="px-3 py-3 text-xs">{{ $log->agent?->name ?: ($log->agent_phone ?: 'Not resolved') }}</td>
                                <td class="px-3 py-3 text-xs">{{ $log->call_status ?: '-' }}</td>
                                <td class="px-3 py-3 text-xs">{{ $log->dtmf_option ?: '-' }}</td>
                                <td class="px-3 py-3 text-xs">
                                    @if($log->lead)
                                        <a href="{{ route('leads.show', $log->lead_id) }}" class="text-blue-600 hover:underline">#{{ $log->lead_id }} {{ $log->lead->name }}</a>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-xs font-semibold {{ $log->status === 'success' ? 'text-green-700' : ($log->status === 'skipped' ? 'text-yellow-700' : 'text-red-700') }}">{{ ucfirst($log->status) }}</td>
                                <td class="px-3 py-3 text-xs text-gray-600 max-w-xs truncate" title="{{ $log->message }}">{{ $log->message ?: '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="p-10 text-center text-gray-400">No BulkSMSPlans IVR webhook hits yet.</div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
const CSRF = '{{ csrf_token() }}';

function showAlert(id, ok, message) {
    const el = document.getElementById(id);
    el.className = 'p-3 rounded-lg text-sm ' + (ok ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700');
    el.textContent = message;
    el.classList.remove('hidden');
}

async function copyText(text, button) {
    await navigator.clipboard.writeText(text || '');
    if (!button) return;
    const original = button.innerHTML;
    button.innerHTML = '<i class="fas fa-check mr-1"></i> Copied';
    button.classList.add('bg-green-50', 'text-green-700', 'border-green-200');
    setTimeout(() => {
        button.innerHTML = original;
        button.classList.remove('bg-green-50', 'text-green-700', 'border-green-200');
    }, 1400);
}

function copyField(id, button) {
    copyText(document.getElementById(id).value, button);
}

function copyHeaderLine(button) {
    const token = document.getElementById('token-input').value.trim();
    copyText('X-IVR-Token: ' + token, button);
}

function copyVendorConfig(button) {
    const url = document.getElementById('webhook-url').value.trim();
    const token = document.getElementById('token-input').value.trim();
    const tokenless = document.getElementById('allow-tokenless-testing').checked;
    const ips = document.getElementById('allowed-webhook-ips').value.trim();
    const text = [
        'Webhook URL: ' + url,
        'Method: POST',
        'Content-Type: application/json',
        ips ? 'Allowed source IP(s): ' + ips.replace(/\s+/g, ', ') : 'Allowed source IP(s): not configured',
        'Header: X-IVR-Token: ' + token,
        'Alternative Header: Authorization: Bearer ' + token,
        tokenless ? 'Temporary test mode: token is not required until the configured expiry.' : 'Note: Please do not send token in query string.'
    ].join('\n');
    copyText(text, button);
}

async function generateToken() {
    const response = await fetch('{{ route('integrations.bulksmsplans-ivr.generate-token') }}');
    const json = await response.json();
    if (json.token) document.getElementById('token-input').value = json.token;
}

async function saveSettings() {
    const body = {
        token: document.getElementById('token-input').value,
        is_enabled: document.getElementById('ivr-enabled').checked ? 1 : 0,
        default_source: document.getElementById('default-source').value || 'ivr',
        auto_create_lead: document.getElementById('auto-create-lead').checked ? 1 : 0,
        create_missed_call_task: document.getElementById('missed-task').checked ? 1 : 0,
        fallback_user_id: document.getElementById('fallback-user').value,
        allow_tokenless_testing: document.getElementById('allow-tokenless-testing').checked ? 1 : 0,
        tokenless_testing_minutes: document.getElementById('tokenless-testing-minutes').value,
        allowed_webhook_ips: document.getElementById('allowed-webhook-ips').value,
    };
    const response = await fetch('{{ route('integrations.bulksmsplans-ivr.settings.update') }}', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF},
        body: JSON.stringify(body),
    });
    const json = await response.json();
    showAlert('settings-alert', response.ok && json.success, json.message || 'Unable to save settings.');
}

async function runTest() {
    const response = await fetch('{{ route('integrations.bulksmsplans-ivr.test') }}', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF},
        body: JSON.stringify({call_status: 'ANSWERED', dtmf_option: '1'}),
    });
    const json = await response.json();
    showAlert('settings-alert', response.ok && json.success, json.message || 'Test failed.');
    if (response.ok && json.success) {
        setTimeout(() => window.location.reload(), 900);
    }
}
</script>
@endpush

@extends('layouts.app')

@section('title', 'MCube Integration - ' . brand_name())
@section('page-title', 'MCube Integration')
@section('page-subtitle', 'Call tracking via MCube webhook')

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
            <div class="mt-1 text-sm text-gray-500">IVR/MCube leads ke liye common assignment rule.</div>
            @unless($sourceAutomationRule)
                <div class="mt-2 inline-flex rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">Automation setup pending</div>
            @endunless
        </div>
        <a href="{{ $sourceAutomationRule ? route('admin.automation.edit', $sourceAutomationRule) : $sourceAutomationUrl }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-100">
            <i class="fas fa-bolt"></i> Setup Automation
        </a>
    </div>
</div>
<div class="w-full max-w-7xl mx-auto space-y-5">

    {{-- ── SETTINGS CARD ── --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="font-bold text-gray-900 flex items-center gap-2">
                <div class="w-8 h-8 bg-orange-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-phone-alt text-orange-600 text-sm"></i>
                </div>
                MCube Settings
            </h2>
            {{-- Enable/Disable Toggle --}}
            <label class="flex items-center gap-3 cursor-pointer">
                <span class="text-sm font-medium text-gray-700">Integration</span>
                <div class="relative">
                    <input type="checkbox" id="toggle-enabled" class="sr-only"
                        {{ $settings->is_enabled ? 'checked' : '' }}>
                    <div id="toggle-track"
                        class="w-11 h-6 rounded-full transition-colors duration-200 {{ $settings->is_enabled ? 'bg-green-500' : 'bg-gray-300' }}"></div>
                    <div id="toggle-thumb"
                        class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform duration-200 {{ $settings->is_enabled ? 'translate-x-5' : '' }}"></div>
                </div>
                <span id="toggle-label" class="text-sm font-semibold {{ $settings->is_enabled ? 'text-green-600' : 'text-gray-500' }}">
                    {{ $settings->is_enabled ? 'Enabled' : 'Disabled' }}
                </span>
            </label>
        </div>

        <div class="p-6 space-y-5">
            <div id="settings-alert" class="hidden p-3 rounded-lg text-sm"></div>

            {{-- Webhook URL --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Webhook URL <span class="text-xs text-gray-400">(paste this in MCube dashboard)</span>
                </label>
                <div class="flex items-center gap-2">
                    <input type="text" value="{{ $webhookUrl }}" readonly
                        class="flex-1 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm font-mono text-gray-700">
                    <button onclick="copyWebhook()"
                        class="px-3 py-2 bg-gray-100 border border-gray-200 rounded-lg text-sm text-gray-600 hover:bg-gray-200 whitespace-nowrap">
                        <i class="fas fa-copy mr-1"></i> Copy
                    </button>
                </div>
            </div>

            {{-- Token --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Webhook Token <span class="text-xs text-gray-400">(sent as X-MCube-Token header)</span>
                </label>
                <div class="flex items-center gap-2">
                    <input type="text" id="token-input" value="{{ $settings->token }}"
                        placeholder="Enter or generate a secure token..."
                        class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-orange-400 focus:border-transparent">
                    <button onclick="generateToken()"
                        class="px-3 py-2 bg-orange-50 border border-orange-200 text-orange-700 rounded-lg text-sm hover:bg-orange-100 whitespace-nowrap">
                        <i class="fas fa-sync-alt mr-1"></i> Generate
                    </button>
                </div>
                <p class="text-xs text-gray-400 mt-1">MCube must send this token in the <code class="bg-gray-100 px-1 rounded">X-MCube-Token</code> request header.</p>
            </div>

            {{-- Save Button --}}
            <div class="flex gap-3 pt-2">
                <button onclick="saveSettings()" id="btn-save"
                    class="px-5 py-2.5 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg text-sm font-medium hover:opacity-90">
                    <i class="fas fa-save mr-2"></i> Save Settings
                </button>
                <button onclick="openTestModal()"
                    class="px-5 py-2.5 bg-orange-500 text-white rounded-lg text-sm font-medium hover:bg-orange-600">
                    <i class="fas fa-flask mr-2"></i> Test Webhook
                </button>
            </div>
        </div>
    </div>

    {{-- ── HOW IT WORKS ── --}}
    <div class="bg-blue-50 border border-blue-100 rounded-xl p-5">
        <h3 class="font-semibold text-blue-800 mb-3 flex items-center gap-2">
            <i class="fas fa-info-circle"></i> How it works
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm text-blue-700">
            <div class="flex items-start gap-2">
                <span class="w-6 h-6 bg-blue-200 text-blue-800 rounded-full flex items-center justify-center font-bold text-xs flex-shrink-0 mt-0.5">1</span>
                <p>MCube sends a POST to the webhook URL when a call is <strong>answered (ANSWER)</strong>.</p>
            </div>
            <div class="flex items-start gap-2">
                <span class="w-6 h-6 bg-blue-200 text-blue-800 rounded-full flex items-center justify-center font-bold text-xs flex-shrink-0 mt-0.5">2</span>
                <p>CRM finds the agent by <code class="bg-blue-100 px-1 rounded">emp_phone</code> and customer by <code class="bg-blue-100 px-1 rounded">callto</code>.</p>
            </div>
            <div class="flex items-start gap-2">
                <span class="w-6 h-6 bg-blue-200 text-blue-800 rounded-full flex items-center justify-center font-bold text-xs flex-shrink-0 mt-0.5">3</span>
                <p>Lead is <strong>created or updated</strong>, agent is assigned, and call log is saved with recording URL.</p>
            </div>
        </div>
    </div>

    {{-- ── RECENT WEBHOOK LOGS ── --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800 flex items-center gap-2">
                <i class="fas fa-list-alt text-orange-500"></i> Recent Webhook Hits
                <span class="text-xs text-gray-400 font-normal">(last 10)</span>
            </h3>
            @if($recentLogs->isNotEmpty())
            @php
                $successCount  = $recentLogs->where('status','success')->count();
                $skippedCount  = $recentLogs->where('status','skipped')->count();
                $failedCount   = $recentLogs->where('status','failed')->count();
            @endphp
            <div class="flex gap-2 text-xs">
                @if($successCount) <span class="bg-green-50 text-green-600 px-2 py-1 rounded-full">✓ {{ $successCount }}</span> @endif
                @if($skippedCount) <span class="bg-yellow-50 text-yellow-600 px-2 py-1 rounded-full">⊘ {{ $skippedCount }}</span> @endif
                @if($failedCount)  <span class="bg-red-50 text-red-600 px-2 py-1 rounded-full">✗ {{ $failedCount }}</span> @endif
            </div>
            @endif
        </div>

        @if($recentLogs->isNotEmpty())
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 text-left text-xs text-gray-500 font-medium uppercase tracking-wide">
                        <th class="px-5 py-3">Time</th>
                        <th class="px-3 py-3">Agent Phone</th>
                        <th class="px-3 py-3">Customer</th>
                        <th class="px-3 py-3">Dial Status</th>
                        <th class="px-3 py-3">Agent</th>
                        <th class="px-3 py-3">Lead</th>
                        <th class="px-3 py-3">Result</th>
                        <th class="px-3 py-3">Message</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($recentLogs as $log)
                    <tr class="hover:bg-gray-50 {{ $log->status === 'failed' ? 'bg-red-50' : '' }}">
                        <td class="px-5 py-3 text-xs text-gray-500 whitespace-nowrap">
                            {{ $log->created_at->format('d M, H:i:s') }}
                        </td>
                        <td class="px-3 py-3 font-mono text-xs">{{ $log->emp_phone ?? '—' }}</td>
                        <td class="px-3 py-3 font-mono text-xs">{{ $log->callto ?? '—' }}</td>
                        <td class="px-3 py-3">
                            <span class="text-xs px-2 py-0.5 rounded-full
                                {{ $log->dialstatus === 'ANSWER' ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                {{ $log->dialstatus ?? '—' }}
                            </span>
                        </td>
                        <td class="px-3 py-3 text-xs">
                            @if($log->agent)
                                <span class="font-medium text-gray-800">{{ $log->agent->name }}</span>
                            @else
                                <span class="text-gray-400">Not found</span>
                            @endif
                        </td>
                        <td class="px-3 py-3 text-xs">
                            @if($log->lead)
                                <a href="{{ route('leads.show', $log->lead_id) }}" class="text-blue-600 hover:underline">
                                    #{{ $log->lead_id }} {{ $log->lead->name }}
                                </a>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-3">
                            @if($log->status === 'success')
                                <span class="inline-flex items-center gap-1 text-xs bg-green-50 text-green-700 px-2 py-0.5 rounded-full">
                                    <i class="fas fa-check-circle"></i> Success
                                </span>
                            @elseif($log->status === 'skipped')
                                <span class="inline-flex items-center gap-1 text-xs bg-yellow-50 text-yellow-700 px-2 py-0.5 rounded-full">
                                    <i class="fas fa-minus-circle"></i> Skipped
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 text-xs bg-red-50 text-red-700 px-2 py-0.5 rounded-full">
                                    <i class="fas fa-times-circle"></i> Failed
                                </span>
                            @endif
                        </td>
                        <td class="px-3 py-3 text-xs text-gray-600 max-w-xs truncate" title="{{ $log->message }}">
                            {{ $log->message ?? '—' }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="p-10 text-center">
            <i class="fas fa-phone-slash text-gray-200 text-4xl mb-3"></i>
            <p class="text-gray-400 text-sm">No webhook hits yet.</p>
        </div>
        @endif
    </div>


</div>

{{-- ── TEST WEBHOOK MODAL ── --}}
<div id="testModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40 px-4"
    style="display:none!important">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="font-bold text-gray-900 flex items-center gap-2">
                <i class="fas fa-flask text-orange-500"></i> Test Webhook
            </h3>
            <button onclick="closeTestModal()" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 text-gray-500">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="px-6 py-5 space-y-4">
            <p class="text-sm text-gray-500">Send a dummy ANSWER payload to test the integration.</p>

            <div id="test-alert" class="hidden p-3 rounded-lg text-sm"></div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Agent Phone (emp_phone)</label>
                <input type="text" id="test-emp-phone" placeholder="e.g. 9876543210"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-orange-400">
                <p class="text-xs text-gray-400 mt-0.5">Must match a user's phone in CRM</p>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Customer Phone (callto)</label>
                <input type="text" id="test-callto" placeholder="e.g. 9123456789"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-orange-400">
            </div>

            <button onclick="runTest()" id="btn-run-test"
                class="w-full py-2.5 bg-orange-500 text-white rounded-lg text-sm font-medium hover:bg-orange-600 flex items-center justify-center gap-2">
                <i class="fas fa-play"></i> Run Test
            </button>
        </div>
    </div>
</div>


@push('scripts')
<script>
const CSRF = '{{ csrf_token() }}';

// ── Toggle ────────────────────────────────────

document.getElementById('toggle-enabled').addEventListener('change', function () {
    const enabled = this.checked;
    document.getElementById('toggle-track').className =
        'w-11 h-6 rounded-full transition-colors duration-200 ' + (enabled ? 'bg-green-500' : 'bg-gray-300');
    document.getElementById('toggle-thumb').className =
        'absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform duration-200 ' + (enabled ? 'translate-x-5' : '');
    document.getElementById('toggle-label').textContent = enabled ? 'Enabled' : 'Disabled';
    document.getElementById('toggle-label').className =
        'text-sm font-semibold ' + (enabled ? 'text-green-600' : 'text-gray-500');
});


// ── Copy webhook URL ─────────────────────────
function copyWebhook() {
    navigator.clipboard.writeText('{{ $webhookUrl }}');
    alert('Webhook URL copied!');
}

// ── Generate token ───────────────────────────
function generateToken() {
    fetch('{{ route("integrations.mcube.generate-token") }}', {
        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
    })
    .then(r => r.json())
    .then(d => { if (d.token) document.getElementById('token-input').value = d.token; });
}

// ── Save settings ────────────────────────────
function saveSettings() {
    const btn = document.getElementById('btn-save');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Saving...';

    fetch('{{ route("integrations.mcube.settings.update") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({
            token:      document.getElementById('token-input').value.trim(),
            is_enabled: document.getElementById('toggle-enabled').checked ? 1 : 0,
        })
    })
    .then(r => r.json())
    .then(d => {
        showAlert('settings-alert', d.message, d.success ? 'green' : 'red');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save mr-2"></i> Save Settings';
    })
    .catch(() => {
        showAlert('settings-alert', 'Save failed.', 'red');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save mr-2"></i> Save Settings';
    });
}

// ── Test modal ───────────────────────────────
function openTestModal() {
    document.getElementById('testModal').style.cssText = 'display:flex!important';
    document.getElementById('test-alert').className = 'hidden p-3 rounded-lg text-sm';
}
function closeTestModal() {
    document.getElementById('testModal').style.cssText = 'display:none!important';
}
document.getElementById('testModal').addEventListener('click', function(e) {
    if (e.target === this) closeTestModal();
});


function runTest() {
    const btn = document.getElementById('btn-run-test');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...';

    fetch('{{ route("integrations.mcube.test") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({
            emp_phone: document.getElementById('test-emp-phone').value.trim(),
            callto:    document.getElementById('test-callto').value.trim(),
        })
    })
    .then(r => r.json())
    .then(d => {
        showAlert('test-alert', '[' + d.status.toUpperCase() + '] ' + d.message, d.success ? 'green' : 'red');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-play"></i> Run Test';
        if (d.success) setTimeout(() => location.reload(), 2000);
    })
    .catch(() => {
        showAlert('test-alert', 'Request failed.', 'red');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-play"></i> Run Test';
    });
}


function showAlert(id, msg, color) {
    const map = {
        green: 'p-3 rounded-lg text-sm bg-green-50 border border-green-200 text-green-800',
        red:   'p-3 rounded-lg text-sm bg-red-50 border border-red-200 text-red-800',
    };
    const el = document.getElementById(id);
    el.className = map[color] || map.red;
    el.textContent = msg;
}
</script>
@endpush
@endsection

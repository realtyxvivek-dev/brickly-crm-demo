@extends('layouts.app')

@section('title', 'MCube Outbound - ' . brand_name())
@section('page-title', 'MCube Outbound')
@section('page-subtitle', 'Click-to-call and fresh lead auto-call settings')

@section('header-actions')
    @php
        $callingCenterUser = auth()->user();
        $canViewCallingCenter = $callingCenterUser && method_exists($callingCenterUser, 'canUseCallingCenter')
            ? $callingCenterUser->canUseCallingCenter('calling_center.view')
            : ($callingCenterUser?->isAdmin() ?? false);
        $canCreateCallingCampaign = $callingCenterUser && method_exists($callingCenterUser, 'canUseCallingCenter')
            ? $callingCenterUser->canUseCallingCenter('calling_center.create_campaign')
            : ($callingCenterUser?->isAdmin() ?? false);
    @endphp
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('integrations.mcube.index') }}"
           class="px-4 py-2 bg-orange-50 text-orange-700 border border-orange-200 rounded-lg hover:bg-orange-100 text-sm font-medium">
            <i class="fas fa-phone-alt mr-2"></i> MCube Webhook
        </a>
        @if($canViewCallingCenter)
        <a href="{{ $canCreateCallingCampaign ? route('calling-center.index') : route('calling-center.queue') }}"
           class="px-4 py-2 bg-emerald-50 text-emerald-800 border border-emerald-200 rounded-lg hover:bg-emerald-100 text-sm font-medium">
            <i class="fas fa-headset mr-2"></i> Open Calling Center
        </a>
        @endif
        <a href="{{ route('integrations.index') }}"
           class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 text-sm font-medium">
            <i class="fas fa-arrow-left mr-2"></i> Back to Integrations
        </a>
    </div>
@endsection

@section('content')
<div class="w-full max-w-7xl mx-auto space-y-5">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="font-bold text-gray-900 flex items-center gap-2">
                <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-phone-volume text-blue-600 text-sm"></i>
                </div>
                MCube Outbound Settings
            </h2>
            <button onclick="openOutboundTestModal()"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                <i class="fas fa-phone-volume mr-2"></i> Test Outbound
            </button>
        </div>

        <div class="p-6 space-y-5">
            <div id="settings-alert" class="hidden p-3 rounded-lg text-sm"></div>

            <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm leading-6 text-amber-900">
                <b>DND/NDNC warning:</b> Keep fresh lead auto-call disabled until MCube confirms outbound DND/NDNC clearance for this account.
            </div>

            @if($canViewCallingCenter)
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm leading-6 text-emerald-900">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <b>Bulk Lead Bank calling:</b> Use Calling Center to select a Lead Bank folder, assign an agent, and run one-by-one MCube campaign calls.
                    </div>
                    <a href="{{ $canCreateCallingCampaign ? route('calling-center.index') : route('calling-center.queue') }}"
                       class="inline-flex items-center justify-center rounded-lg bg-[#205A44] px-4 py-2 text-sm font-semibold text-white hover:opacity-90">
                        <i class="fas fa-headset mr-2"></i> Open Calling Center
                    </a>
                </div>
            </div>
            @endif

            <div class="border border-blue-100 rounded-xl overflow-hidden">
                <div class="flex flex-col gap-3 bg-blue-50/70 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-gray-900">Outbound Click-to-Call</h3>
                        <p class="text-xs text-gray-500">Use MCube outbound API before falling back to phone dialer.</p>
                    </div>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <span class="text-sm font-medium text-gray-700">Outbound</span>
                        <div class="relative">
                            <input type="checkbox" id="outbound-enabled" class="sr-only js-mcube-switch"
                                data-track="outbound-enabled-track"
                                data-thumb="outbound-enabled-thumb"
                                data-label="outbound-enabled-label"
                                data-on="Enabled"
                                data-off="Disabled"
                                data-on-class="text-green-600"
                                data-off-class="text-gray-500"
                                {{ $settings->outbound_enabled ? 'checked' : '' }}>
                            <div id="outbound-enabled-track"
                                class="w-11 h-6 rounded-full transition-colors duration-200 {{ $settings->outbound_enabled ? 'bg-green-500' : 'bg-gray-300' }}"></div>
                            <div id="outbound-enabled-thumb"
                                class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform duration-200 {{ $settings->outbound_enabled ? 'translate-x-5' : '' }}"></div>
                        </div>
                        <span id="outbound-enabled-label" class="text-sm font-semibold {{ $settings->outbound_enabled ? 'text-green-600' : 'text-gray-500' }}">
                            {{ $settings->outbound_enabled ? 'Enabled' : 'Disabled' }}
                        </span>
                    </label>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 p-4">
                    <div class="xl:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Outbound API URL</label>
                        <input type="url" id="outbound-api-url"
                            value="{{ $settings->outbound_api_url ?: \App\Models\McubeSetting::DEFAULT_OUTBOUND_API_URL }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-400">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Default refurl</label>
                        <input type="text" id="default-refurl" value="{{ $settings->default_refurl ?: '1' }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-400">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Auth Mode</label>
                        <select id="outbound-auth-mode"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-400">
                            <option value="json_http_authorization" {{ $settings->outboundAuthMode() === 'json_http_authorization' ? 'selected' : '' }}>JSON HTTP_AUTHORIZATION</option>
                            <option value="authorization_header" {{ $settings->outboundAuthMode() === 'authorization_header' ? 'selected' : '' }}>Authorization Header</option>
                        </select>
                    </div>
                    <div class="md:col-span-2 xl:col-span-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Outbound Token</label>
                        <input type="password" id="outbound-token" value=""
                            placeholder="{{ $settings->outbound_token ? 'Leave blank to keep existing token' : 'MCube outbound token' }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-400">
                        @if(($outboundTokenStatus['saved'] ?? false))
                            <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                                <span class="rounded-full bg-green-50 px-2 py-1 font-semibold text-green-700">
                                    <i class="fas fa-shield-alt mr-1"></i> Token saved
                                </span>
                                <span class="text-gray-500">Ending {{ $outboundTokenStatus['last'] ?? '******' }}</span>
                                @if(!empty($outboundTokenStatus['expires_at']))
                                    <span class="text-gray-500">Expires {{ $outboundTokenStatus['expires_at'] }}</span>
                                @endif
                                <span class="text-gray-400">Blank rehne par existing token same rahega.</span>
                            </div>
                        @else
                            <p class="mt-1 text-xs text-red-500">No outbound token saved. Paste token and click Save Outbound Settings.</p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="border border-gray-100 rounded-xl overflow-hidden">
                <div class="flex flex-col gap-3 bg-gray-50/70 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-gray-900">Fresh Lead Auto-Call</h3>
                        <p class="text-xs text-gray-500">Optional outbound call after a lead is assigned. Existing inbound/IVR flow is not changed.</p>
                    </div>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <span class="text-sm font-medium text-gray-700">Auto-call</span>
                        <div class="relative">
                            <input type="checkbox" id="auto-call-on-assignment" class="sr-only js-mcube-switch"
                                data-track="auto-call-on-assignment-track"
                                data-thumb="auto-call-on-assignment-thumb"
                                data-label="auto-call-on-assignment-label"
                                data-on="Enabled"
                                data-off="Disabled"
                                data-on-class="text-green-600"
                                data-off-class="text-gray-500"
                                {{ $settings->auto_call_on_assignment ? 'checked' : '' }}>
                            <div id="auto-call-on-assignment-track"
                                class="w-11 h-6 rounded-full transition-colors duration-200 {{ $settings->auto_call_on_assignment ? 'bg-green-500' : 'bg-gray-300' }}"></div>
                            <div id="auto-call-on-assignment-thumb"
                                class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform duration-200 {{ $settings->auto_call_on_assignment ? 'translate-x-5' : '' }}"></div>
                        </div>
                        <span id="auto-call-on-assignment-label" class="text-sm font-semibold {{ $settings->auto_call_on_assignment ? 'text-green-600' : 'text-gray-500' }}">
                            {{ $settings->auto_call_on_assignment ? 'Enabled' : 'Disabled' }}
                        </span>
                    </label>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 p-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Cooldown Minutes</label>
                        <input type="number" min="0" max="1440" id="auto-call-cooldown-minutes"
                            value="{{ $settings->auto_call_cooldown_minutes ?? 10 }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-400">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Quiet Hours</label>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="time" id="auto-call-quiet-start" value="{{ $settings->auto_call_quiet_start ? substr((string) $settings->auto_call_quiet_start, 0, 5) : '' }}"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-400">
                            <input type="time" id="auto-call-quiet-end" value="{{ $settings->auto_call_quiet_end ? substr((string) $settings->auto_call_quiet_end, 0, 5) : '' }}"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-400">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Allowed Sources</label>
                        <input type="text" id="auto-call-allowed-sources"
                            value="{{ implode(',', array_filter((array) $settings->auto_call_allowed_sources)) }}"
                            placeholder="Blank = all, e.g. meta,google_sheets,website"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-400">
                        <p class="mt-1 text-xs text-gray-400">Allowed: {{ implode(', ', array_keys(\App\Models\Lead::SOURCE_OPTIONS)) }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Allowed User IDs</label>
                        <input type="text" id="auto-call-allowed-user-ids"
                            value="{{ implode(',', array_filter((array) $settings->auto_call_allowed_user_ids)) }}"
                            placeholder="Blank = all, e.g. 4,7,12"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-400">
                        <p class="mt-1 text-xs text-gray-400">Only active user IDs are accepted.</p>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <button onclick="saveOutboundSettings()" id="btn-save"
                    class="px-5 py-2.5 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg text-sm font-medium hover:opacity-90">
                    <i class="fas fa-save mr-2"></i> Save Outbound Settings
                </button>
                <button onclick="openOutboundTestModal()"
                    class="px-5 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
                    <i class="fas fa-phone-volume mr-2"></i> Test Outbound
                </button>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800 flex items-center gap-2">
                <i class="fas fa-phone-volume text-blue-500"></i> Recent Outbound Attempts
                <span class="text-xs text-gray-400 font-normal">(last 10)</span>
            </h3>
        </div>
        <div class="px-5 py-3 border-b border-gray-100 bg-slate-50 text-xs text-slate-500">
            Shows the exact MCube request, HTTP result, response message, refid, and fallback reason for every outbound call.
        </div>
        @if(($recentOutboundAttempts ?? collect())->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-xs text-gray-500 font-medium uppercase tracking-wide">
                            <th class="px-5 py-3">Time</th>
                            <th class="px-3 py-3">User</th>
                            <th class="px-3 py-3">Lead</th>
                            <th class="px-3 py-3">Agent</th>
                            <th class="px-3 py-3">Customer</th>
                            <th class="px-3 py-3">Status</th>
                            <th class="px-3 py-3">API Result</th>
                            <th class="px-3 py-3">Refid</th>
                            <th class="px-3 py-3">Details</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($recentOutboundAttempts as $attempt)
                            @php
                                $response = is_array($attempt->response_payload) ? $attempt->response_payload : [];
                                $requestPayload = is_array($attempt->request_payload) ? $attempt->request_payload : [];
                                $attemptMessage = $attempt->error_message
                                    ?: data_get($response, 'message')
                                    ?: data_get($response, 'msg')
                                    ?: data_get($response, 'status')
                                    ?: data_get($response, 'raw')
                                    ?: ($attempt->status === 'success' ? 'MCube accepted the outbound call request.' : '');
                                $apiStatus = data_get($response, 'status')
                                    ?: data_get($response, 'callstatus')
                                    ?: data_get($response, 'dialstatus')
                                    ?: data_get($response, 'success');
                                $apiCallId = data_get($response, 'callid')
                                    ?: data_get($response, 'call_id')
                                    ?: data_get($response, 'refid')
                                    ?: data_get($response, 'data.callid');
                                $detailId = 'mcube-attempt-details-' . $attempt->id;
                                $statusClass = $attempt->status === 'success'
                                    ? 'bg-green-50 text-green-700'
                                    : ($attempt->status === 'pending' ? 'bg-yellow-50 text-yellow-700' : 'bg-red-50 text-red-700');
                            @endphp
                            <tr class="hover:bg-gray-50 {{ $attempt->status === 'failed' ? 'bg-red-50' : '' }}">
                                <td class="px-5 py-3 text-xs text-gray-500 whitespace-nowrap">{{ optional($attempt->attempted_at ?: $attempt->created_at)->format('d M, H:i:s') }}</td>
                                <td class="px-3 py-3 text-xs">{{ $attempt->user->name ?? '-' }}</td>
                                <td class="px-3 py-3 text-xs">
                                    @if($attempt->lead)
                                        <a href="{{ route('leads.show', $attempt->lead_id) }}" class="text-blue-600 hover:underline">#{{ $attempt->lead_id }} {{ $attempt->lead->name }}</a>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 font-mono text-xs">{{ $attempt->agent_number ?: '-' }}</td>
                                <td class="px-3 py-3 font-mono text-xs">{{ $attempt->customer_number ?: '-' }}</td>
                                <td class="px-3 py-3">
                                    <span class="text-xs px-2 py-0.5 rounded-full {{ $statusClass }}">
                                        {{ ucfirst($attempt->status) }}
                                    </span>
                                    @if($attempt->http_status)
                                        <div class="mt-1 text-[11px] text-gray-400">HTTP {{ $attempt->http_status }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-xs text-gray-600 max-w-sm">
                                    <div class="line-clamp-2" title="{{ $attemptMessage }}">{{ $attemptMessage ?: '-' }}</div>
                                    @if($apiStatus !== null || $apiCallId)
                                        <div class="mt-1 flex flex-wrap gap-1 text-[11px]">
                                            @if($apiStatus !== null)
                                                <span class="rounded bg-slate-100 px-1.5 py-0.5 text-slate-600">api: {{ is_bool($apiStatus) ? ($apiStatus ? 'true' : 'false') : $apiStatus }}</span>
                                            @endif
                                            @if($apiCallId)
                                                <span class="rounded bg-blue-50 px-1.5 py-0.5 text-blue-700">callid: {{ $apiCallId }}</span>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="px-3 py-3 font-mono text-[11px] text-gray-500 max-w-[220px] truncate" title="{{ $attempt->refid ?: '-' }}">
                                    {{ $attempt->refid ?: '-' }}
                                </td>
                                <td class="px-3 py-3">
                                    <button type="button"
                                        class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                        onclick="toggleOutboundAttemptDetails('{{ $detailId }}', this)">
                                        View
                                    </button>
                                </td>
                            </tr>
                            <tr id="{{ $detailId }}" class="hidden bg-slate-50">
                                <td colspan="9" class="px-5 py-4">
                                    <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                                        <div class="rounded-lg border border-slate-200 bg-white p-3">
                                            <div class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">Request sent to MCube</div>
                                            <dl class="grid grid-cols-2 gap-2 text-xs">
                                                <dt class="text-slate-400">Agent</dt><dd class="font-mono text-slate-700">{{ $attempt->agent_number ?: '-' }}</dd>
                                                <dt class="text-slate-400">Customer</dt><dd class="font-mono text-slate-700">{{ $attempt->customer_number ?: '-' }}</dd>
                                                <dt class="text-slate-400">Refurl</dt><dd class="font-mono text-slate-700">{{ $attempt->refurl ?: '-' }}</dd>
                                                <dt class="text-slate-400">Refid</dt><dd class="font-mono text-slate-700 break-all">{{ $attempt->refid ?: '-' }}</dd>
                                            </dl>
                                            <pre class="mt-3 max-h-52 overflow-auto rounded bg-slate-900 p-3 text-[11px] leading-5 text-slate-100">{{ json_encode($requestPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}' }}</pre>
                                        </div>
                                        <div class="rounded-lg border border-slate-200 bg-white p-3">
                                            <div class="mb-2 flex items-center justify-between gap-2">
                                                <div class="text-xs font-bold uppercase tracking-wide text-slate-500">MCube API response</div>
                                                <span class="rounded-full px-2 py-0.5 text-[11px] {{ $statusClass }}">{{ ucfirst($attempt->status) }}</span>
                                            </div>
                                            <dl class="grid grid-cols-2 gap-2 text-xs">
                                                <dt class="text-slate-400">HTTP status</dt><dd class="font-mono text-slate-700">{{ $attempt->http_status ?: '-' }}</dd>
                                                <dt class="text-slate-400">Stored message</dt><dd class="text-slate-700">{{ $attemptMessage ?: '-' }}</dd>
                                            </dl>
                                            <pre class="mt-3 max-h-52 overflow-auto rounded bg-slate-900 p-3 text-[11px] leading-5 text-slate-100">{{ json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}' }}</pre>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="p-10 text-center">
                <i class="fas fa-phone-volume text-gray-200 text-4xl mb-3"></i>
                <p class="text-gray-400 text-sm">No outbound attempts yet.</p>
            </div>
        @endif
    </div>
</div>

<div id="outboundTestModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40 px-4"
    style="display:none!important">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="font-bold text-gray-900 flex items-center gap-2">
                <i class="fas fa-phone-volume text-blue-500"></i> Test Outbound Call
            </h3>
            <button onclick="closeOutboundTestModal()" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 text-gray-500">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="px-6 py-5 space-y-4">
            <p class="text-sm text-gray-500">Enter a CRM lead ID. Optional phone overrides the lead phone only for this test.</p>
            <div id="outbound-test-alert" class="hidden p-3 rounded-lg text-sm"></div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Lead ID</label>
                <input type="number" id="outbound-test-lead-id"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-400">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Override Customer Phone (optional)</label>
                <input type="text" id="outbound-test-phone"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-400">
            </div>
            <button onclick="runOutboundTest()" id="btn-run-outbound-test"
                class="w-full py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 flex items-center justify-center gap-2">
                <i class="fas fa-play"></i> Run Outbound Test
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
const CSRF = '{{ csrf_token() }}';

function syncSwitch(input) {
    const enabled = input.checked;
    const track = document.getElementById(input.dataset.track);
    const thumb = document.getElementById(input.dataset.thumb);
    const label = document.getElementById(input.dataset.label);

    if (track) {
        track.className = 'w-11 h-6 rounded-full transition-colors duration-200 ' + (enabled ? 'bg-green-500' : 'bg-gray-300');
    }

    if (thumb) {
        thumb.className = 'absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform duration-200 ' + (enabled ? 'translate-x-5' : '');
    }

    if (label) {
        label.textContent = enabled ? (input.dataset.on || 'Enabled') : (input.dataset.off || 'Disabled');
        label.className = 'text-sm font-semibold ' + (enabled ? (input.dataset.onClass || 'text-green-600') : (input.dataset.offClass || 'text-gray-500'));
    }
}

document.querySelectorAll('.js-mcube-switch').forEach((input) => {
    input.addEventListener('change', function () {
        syncSwitch(this);
    });
});

function saveOutboundSettings() {
    const btn = document.getElementById('btn-save');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Saving...';

    fetch('{{ route("integrations.mcube.settings.update") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({
            outbound_enabled: document.getElementById('outbound-enabled').checked ? 1 : 0,
            outbound_api_url: document.getElementById('outbound-api-url').value.trim(),
            outbound_token: document.getElementById('outbound-token').value.trim(),
            outbound_auth_mode: document.getElementById('outbound-auth-mode').value,
            default_refurl: document.getElementById('default-refurl').value.trim() || '1',
            auto_call_on_assignment: document.getElementById('auto-call-on-assignment').checked ? 1 : 0,
            auto_call_cooldown_minutes: document.getElementById('auto-call-cooldown-minutes').value || 10,
            auto_call_quiet_start: document.getElementById('auto-call-quiet-start').value || null,
            auto_call_quiet_end: document.getElementById('auto-call-quiet-end').value || null,
            auto_call_allowed_sources: document.getElementById('auto-call-allowed-sources').value.trim(),
            auto_call_allowed_user_ids: document.getElementById('auto-call-allowed-user-ids').value.trim(),
        })
    })
    .then(async r => {
        const d = await r.json();
        if (!r.ok && !d.message) d.message = 'Save failed.';
        return d;
    })
    .then(d => {
        showAlert('settings-alert', d.message, d.success ? 'green' : 'red');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save mr-2"></i> Save Outbound Settings';
    })
    .catch(() => {
        showAlert('settings-alert', 'Save failed.', 'red');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save mr-2"></i> Save Outbound Settings';
    });
}

function openOutboundTestModal() {
    document.getElementById('outboundTestModal').style.cssText = 'display:flex!important';
    document.getElementById('outbound-test-alert').className = 'hidden p-3 rounded-lg text-sm';
}
function closeOutboundTestModal() {
    document.getElementById('outboundTestModal').style.cssText = 'display:none!important';
}
document.getElementById('outboundTestModal').addEventListener('click', function(e) {
    if (e.target === this) closeOutboundTestModal();
});

function toggleOutboundAttemptDetails(rowId, button) {
    const row = document.getElementById(rowId);
    if (!row) return;

    const isHidden = row.classList.toggle('hidden');
    button.textContent = isHidden ? 'View' : 'Hide';
}

function runOutboundTest() {
    const btn = document.getElementById('btn-run-outbound-test');
    const leadId = document.getElementById('outbound-test-lead-id').value.trim();
    const phone = document.getElementById('outbound-test-phone').value.trim();

    if (!leadId) {
        showAlert('outbound-test-alert', 'Lead ID is required.', 'red');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Calling...';

    fetch('{{ route("integrations.mcube.test-outbound") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({
            lead_id: leadId,
            phone: phone || null,
        })
    })
    .then(async r => {
        const d = await r.json();
        if (!r.ok && !d.message) d.message = 'Outbound test failed.';
        return d;
    })
    .then(d => {
        showAlert('outbound-test-alert', '[' + (d.success ? 'SUCCESS' : 'FAILED') + '] ' + (d.message || 'No message'), d.success ? 'green' : 'red');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-play"></i> Run Outbound Test';
        setTimeout(() => location.reload(), d.success ? 1800 : 3500);
    })
    .catch(() => {
        showAlert('outbound-test-alert', 'Request failed.', 'red');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-play"></i> Run Outbound Test';
    });
}

function showAlert(id, msg, color) {
    const map = {
        green: 'p-3 rounded-lg text-sm bg-green-50 border border-green-200 text-green-800',
        red:   'p-3 rounded-lg text-sm bg-red-50 border border-red-200 text-red-800'
    };
    const el = document.getElementById(id);
    el.className = map[color] || map.green;
    el.textContent = msg;
}
</script>
@endpush
@endsection

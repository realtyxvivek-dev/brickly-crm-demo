@extends('layouts.app')

@section('title', ($isEdit ? 'Edit' : 'Create') . ' Website Integration - ' . brand_name())
@section('page-title', $isEdit ? 'Edit Website Integration' : 'Create Website Integration')

@section('header-actions')
    <a href="{{ route('integrations.website.index') }}" class="px-4 py-2 bg-white border border-gray-200 text-gray-700 rounded-lg text-sm font-medium">
        <i class="fas fa-arrow-left mr-2"></i>
        Back
    </a>
@endsection

@section('content')
@php
    $currentMappings = old('mappings', $mappings);
    $samplePayload = old('sample_payload_json', json_encode($integration->sample_payload_json ?: $templates['website_default_json']['sample_payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
@endphp

<div class="max-w-7xl mx-auto space-y-6">
    @if (session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <div class="font-semibold mb-2">Please fix these issues:</div>
            <ul class="list-disc ml-5 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ $isEdit ? route('integrations.website.update', $integration) : route('integrations.website.store') }}" class="space-y-6" id="websiteIntegrationForm">
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Basic Settings</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Integration Name</label>
                            <input type="text" name="name" value="{{ old('name', $integration->name) }}" class="w-full rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Slug</label>
                            <input type="text" name="slug" value="{{ old('slug', $integration->slug) }}" class="w-full rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500" required>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea name="description" rows="2" class="w-full rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">{{ old('description', $integration->description) }}</textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Fallback Type</label>
                            <select name="fallback_type" id="fallbackType" class="w-full rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
                                @foreach($fallbackOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('fallback_type', $integration->fallback_type) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div id="fallbackUserWrapper">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Fallback User</label>
                            <select name="fallback_user_id" class="w-full rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
                                <option value="">Select user</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" @selected((string) old('fallback_user_id', $integration->fallback_user_id) === (string) $user->id)>{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Rate Limit / Minute</label>
                            <input type="number" min="1" max="5000" name="rate_limit" value="{{ old('rate_limit', $integration->rate_limit ?: 60) }}" class="w-full rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500" required>
                        </div>
                        <div class="flex items-center gap-3 mt-6">
                            <input type="checkbox" name="is_active" id="isActive" value="1" @checked(old('is_active', $integration->is_active ?? true)) class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                            <label for="isActive" class="text-sm font-medium text-gray-700">Integration Active</label>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                        <h2 class="text-lg font-semibold text-gray-900">Field Mapping</h2>
                        <div class="flex items-center gap-2">
                            <select id="templateSelector" class="rounded-lg border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                                @foreach($templates as $key => $template)
                                    <option value="{{ $key }}" @selected($key === 'website_default_json')>{{ $template['label'] }}</option>
                                @endforeach
                            </select>
                            <button type="button" onclick="applySelectedTemplate()" class="px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg border border-gray-200">Apply Template</button>
                            <button type="button" onclick="addMappingRow()" class="px-3 py-2 text-sm bg-emerald-50 text-emerald-700 rounded-lg border border-emerald-200">Add Row</button>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200" id="mappingTable">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Incoming Key</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">CRM Target</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Required</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Default Value</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ignore</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase"></th>
                                </tr>
                            </thead>
                            <tbody id="mappingTableBody" class="divide-y divide-gray-100"></tbody>
                        </table>
                    </div>
                    <p class="text-xs text-gray-500 mt-3">Required CRM fields: <span class="font-semibold">name</span>, <span class="font-semibold">phone</span>. Nested JSON keys are supported with dot notation like <span class="font-semibold">source.$oid</span>.</p>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                        <h2 class="text-lg font-semibold text-gray-900">Live Mapping Preview</h2>
                        <div class="flex items-center gap-2">
                            <button type="button" onclick="useSamplePayload()" class="px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg border border-gray-200">Use Sample Payload</button>
                            <button type="button" onclick="runPreview()" class="px-3 py-2 text-sm bg-emerald-600 text-white rounded-lg">Refresh Preview</button>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Sample Incoming Payload</label>
                            <textarea name="sample_payload_json" id="samplePayloadJson" rows="18" class="w-full rounded-lg border-gray-300 font-mono text-sm focus:border-emerald-500 focus:ring-emerald-500">{{ $samplePayload }}</textarea>
                        </div>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Parsed CRM Payload</label>
                                <pre id="previewMappedPayload" class="rounded-lg bg-slate-900 text-slate-100 text-xs p-4 min-h-[180px] overflow-auto">Run preview to inspect mapped output.</pre>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <div class="rounded-lg border border-gray-200 p-3">
                                    <div class="text-xs font-semibold text-gray-500 uppercase mb-2">Missing Required</div>
                                    <div id="previewMissing" class="text-sm text-red-600">None</div>
                                </div>
                                <div class="rounded-lg border border-gray-200 p-3">
                                    <div class="text-xs font-semibold text-gray-500 uppercase mb-2">Defaults Applied</div>
                                    <div id="previewDefaults" class="text-sm text-gray-700">None</div>
                                </div>
                                <div class="rounded-lg border border-gray-200 p-3">
                                    <div class="text-xs font-semibold text-gray-500 uppercase mb-2">Ignored Fields</div>
                                    <div id="previewIgnored" class="text-sm text-gray-700">None</div>
                                </div>
                            </div>
                            <div class="rounded-lg border border-gray-200 p-3">
                                <div class="text-xs font-semibold text-gray-500 uppercase mb-2">Validation</div>
                                <div id="previewValidation" class="text-sm text-gray-700">Run preview to validate mapping.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Endpoint & API Key</h2>
                    @if($isEdit)
                        <div class="space-y-4">
                            <div>
                                <div class="text-xs font-semibold uppercase text-gray-500 mb-1">Endpoint</div>
                                <div class="text-sm text-gray-700 break-all">{{ url('/api/webhooks/website-leads/' . $integration->slug) }}</div>
                            </div>
                            <div>
                                <div class="text-xs font-semibold uppercase text-gray-500 mb-1">API Key</div>
                                <div class="flex items-center gap-2">
                                    <input type="text" id="apiKeyField" value="{{ $integration->api_key }}" readonly class="w-full rounded-lg border-gray-300 bg-gray-50 text-sm">
                                    <button type="button" onclick="regenerateApiKey()" class="px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg border border-gray-200">Regenerate</button>
                                </div>
                            </div>
                        </div>
                    @else
                        <p class="text-sm text-gray-500">Save the integration first to generate endpoint and API key.</p>
                    @endif
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold text-gray-900">Mini Postman</h2>
                        @if($isEdit)
                            <button type="button" onclick="sendTestRequest()" class="px-3 py-2 text-sm bg-emerald-600 text-white rounded-lg">Send Test Request</button>
                        @endif
                    </div>
                    @if($isEdit)
                        <div class="space-y-3 text-sm">
                            <div class="rounded-lg border border-gray-200 p-3">
                                <div class="text-xs font-semibold uppercase text-gray-500 mb-2">Request Meta</div>
                                <div id="testMeta" class="text-gray-700">No test executed yet.</div>
                            </div>
                            <div class="rounded-lg border border-gray-200 p-3">
                                <div class="text-xs font-semibold uppercase text-gray-500 mb-2">Validation Result</div>
                                <pre id="testValidation" class="text-xs text-gray-700 whitespace-pre-wrap">No test executed yet.</pre>
                            </div>
                            <div class="rounded-lg border border-gray-200 p-3">
                                <div class="text-xs font-semibold uppercase text-gray-500 mb-2">Assignment Result</div>
                                <pre id="testAssignment" class="text-xs text-gray-700 whitespace-pre-wrap">No test executed yet.</pre>
                            </div>
                            <div class="rounded-lg border border-gray-200 p-3">
                                <div class="text-xs font-semibold uppercase text-gray-500 mb-2">API Response Preview</div>
                                <pre id="testResponse" class="text-xs text-gray-700 whitespace-pre-wrap">No test executed yet.</pre>
                            </div>
                        </div>
                    @else
                        <p class="text-sm text-gray-500">Save the integration first to use test mode.</p>
                    @endif
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <button type="submit" class="w-full px-4 py-3 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg font-medium">
                        {{ $isEdit ? 'Update Integration' : 'Create Integration' }}
                    </button>
                </div>
            </div>
        </div>
    </form>

    @if($isEdit)
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Recent Request Logs</h2>
                <div class="space-y-3 max-h-[480px] overflow-auto pr-2">
                    @forelse($logs as $log)
                        <div class="rounded-lg border border-gray-200 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <div class="text-sm font-semibold text-gray-900">{{ $log->request_id }}</div>
                                <span class="px-2 py-1 rounded-full bg-gray-100 text-gray-700 text-xs font-semibold">{{ $log->status }}</span>
                            </div>
                            <div class="text-xs text-gray-500 mt-1">{{ optional($log->created_at)->format('d M Y, h:i A') }} · {{ $log->response_time_ms }} ms</div>
                            <div class="text-sm text-gray-700 mt-2">
                                Lead: {{ $log->lead_id ?: 'N/A' }} · Duplicate: {{ $log->duplicate ? 'Yes' : 'No' }} · Test: {{ $log->is_test ? 'Yes' : 'No' }}
                            </div>
                            @if($log->error_message)
                                <div class="text-sm text-red-600 mt-2">{{ $log->error_message }}</div>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">No request logs yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Unassigned CRM Queue</h2>
                <div class="space-y-3 max-h-[480px] overflow-auto pr-2">
                    @forelse($queueLeads as $lead)
                        <div class="rounded-lg border border-gray-200 p-4">
                            <div class="font-semibold text-gray-900">{{ $lead->name }}</div>
                            <div class="text-sm text-gray-600 mt-1">{{ $lead->phone ?: 'No phone' }}</div>
                            <div class="text-xs text-gray-500 mt-2">Lead #{{ $lead->id }} · {{ optional($lead->created_at)->format('d M Y, h:i A') }}</div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">No leads are waiting in the unassigned website queue.</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
const websiteTemplates = @json($templates);
const websiteTargetFields = @json($targetFields);
const initialMappings = @json($currentMappings);

function targetOptions(selectedValue) {
    const options = ['<option value="">Select target</option>'];
    Object.entries(websiteTargetFields).forEach(([value, config]) => {
        const selected = value === selectedValue ? 'selected' : '';
        options.push(`<option value="${value}" ${selected}>${config.label}</option>`);
    });
    return options.join('');
}

function mappingRowTemplate(mapping = {}, index = 0) {
    return `
        <tr data-index="${index}">
            <td class="px-3 py-3">
                <input type="text" name="mappings[${index}][incoming_field]" value="${escapeHtml(mapping.incoming_field || '')}" class="w-full rounded-lg border-gray-300 text-sm">
            </td>
            <td class="px-3 py-3">
                <select name="mappings[${index}][crm_field]" class="w-full rounded-lg border-gray-300 text-sm">
                    ${targetOptions(mapping.crm_field || '')}
                </select>
            </td>
            <td class="px-3 py-3 text-center">
                <input type="checkbox" name="mappings[${index}][is_required]" value="1" ${mapping.is_required ? 'checked' : ''} class="rounded border-gray-300 text-emerald-600">
            </td>
            <td class="px-3 py-3">
                <input type="text" name="mappings[${index}][default_value]" value="${escapeHtml(mapping.default_value || '')}" class="w-full rounded-lg border-gray-300 text-sm">
            </td>
            <td class="px-3 py-3 text-center">
                <input type="checkbox" name="mappings[${index}][is_ignored]" value="1" ${mapping.is_ignored ? 'checked' : ''} class="rounded border-gray-300 text-emerald-600">
            </td>
            <td class="px-3 py-3 text-right">
                <button type="button" onclick="removeMappingRow(this)" class="text-red-600 text-sm">Remove</button>
            </td>
        </tr>
    `;
}

function renderMappings(mappings) {
    const body = document.getElementById('mappingTableBody');
    body.innerHTML = '';
    (mappings || []).forEach((mapping, index) => {
        body.insertAdjacentHTML('beforeend', mappingRowTemplate(mapping, index));
    });
    if (!mappings || mappings.length === 0) {
        addMappingRow();
    }
}

function addMappingRow() {
    const body = document.getElementById('mappingTableBody');
    const index = body.querySelectorAll('tr').length;
    body.insertAdjacentHTML('beforeend', mappingRowTemplate({}, index));
}

function removeMappingRow(button) {
    button.closest('tr').remove();
    reindexRows();
}

function reindexRows() {
    document.querySelectorAll('#mappingTableBody tr').forEach((row, index) => {
        row.dataset.index = index;
        row.querySelectorAll('input, select').forEach((field) => {
            field.name = field.name.replace(/mappings\[\d+\]/, `mappings[${index}]`);
        });
    });
}

function gatherMappings() {
    const mappings = [];
    document.querySelectorAll('#mappingTableBody tr').forEach((row) => {
        const getValue = (selector) => row.querySelector(selector);
        mappings.push({
            incoming_field: getValue('input[name*="[incoming_field]"]').value,
            crm_field: getValue('select[name*="[crm_field]"]').value,
            is_required: getValue('input[name*="[is_required]"]').checked,
            default_value: getValue('input[name*="[default_value]"]').value,
            is_ignored: getValue('input[name*="[is_ignored]"]').checked,
        });
    });
    return mappings;
}

function applySelectedTemplate() {
    const templateKey = document.getElementById('templateSelector').value;
    const template = websiteTemplates[templateKey];
    if (!template) return;
    renderMappings(template.mappings || []);
    document.getElementById('samplePayloadJson').value = JSON.stringify(template.sample_payload || {}, null, 2);
    runPreview();
}

function useSamplePayload() {
    const templateKey = document.getElementById('templateSelector').value;
    const template = websiteTemplates[templateKey] || websiteTemplates.website_default_json;
    document.getElementById('samplePayloadJson').value = JSON.stringify(template.sample_payload || {}, null, 2);
    runPreview();
}

async function runPreview() {
    const payload = {
        sample_payload_json: document.getElementById('samplePayloadJson').value,
        mappings: gatherMappings(),
        _token: '{{ csrf_token() }}',
    };

    const response = await fetch('{{ route('integrations.website.preview') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
        body: JSON.stringify(payload),
    });

    const data = await response.json();
    const preview = data.preview || {};
    document.getElementById('previewMappedPayload').textContent = JSON.stringify({
        lead: preview.mapped_payload || {},
        meta: preview.meta_payload || {},
        unmapped_incoming: preview.unmapped_incoming || {},
    }, null, 2);
    document.getElementById('previewMissing').textContent = (preview.missing_required || []).join(', ') || 'None';
    document.getElementById('previewDefaults').textContent = Object.keys(preview.defaults_applied || {}).length
        ? JSON.stringify(preview.defaults_applied)
        : 'None';
    document.getElementById('previewIgnored').textContent = Object.keys(preview.ignored_fields || {}).length
        ? Object.keys(preview.ignored_fields).join(', ')
        : 'None';

    const validationMessages = [];
    if (data.errors && Object.keys(data.errors).length) {
        Object.values(data.errors).forEach(message => validationMessages.push(message));
    }
    if (preview.duplicate_warnings && preview.duplicate_warnings.length) {
        preview.duplicate_warnings.forEach(message => validationMessages.push(message));
    }
    document.getElementById('previewValidation').textContent = validationMessages.length
        ? validationMessages.join(' | ')
        : 'Preview valid.';
}

async function sendTestRequest() {
    const payload = {
        sample_payload_json: document.getElementById('samplePayloadJson').value,
        mappings: gatherMappings(),
        _token: '{{ csrf_token() }}',
    };

    const response = await fetch('{{ $isEdit ? route('integrations.website.test', $integration) : '#' }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
        body: JSON.stringify(payload),
    });

    const data = await response.json();
    document.getElementById('testMeta').textContent = `request_id: ${data.request_id || 'n/a'} | ${data.response_time_ms || 0} ms | duplicate: ${data.duplicate ? 'yes' : 'no'} | is_test: ${data.is_test ? 'yes' : 'no'}`;
    document.getElementById('testValidation').textContent = JSON.stringify(data.validation_result || {}, null, 2);
    document.getElementById('testAssignment').textContent = JSON.stringify({
        assignment_result: data.assignment_result || {},
        fallback_result: data.fallback_result || {},
    }, null, 2);
    document.getElementById('testResponse').textContent = JSON.stringify({
        status: data.status,
        message: data.message,
        lead_id: data.lead_id,
        duplicate: data.duplicate,
        request_id: data.request_id,
    }, null, 2);
}

async function regenerateApiKey() {
    const response = await fetch('{{ $isEdit ? route('integrations.website.regenerate-key', $integration) : '#' }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
        body: JSON.stringify({ _token: '{{ csrf_token() }}' }),
    });
    const data = await response.json();
    if (data.success) {
        document.getElementById('apiKeyField').value = data.api_key;
    }
}

function toggleFallbackUser() {
    const fallbackType = document.getElementById('fallbackType').value;
    document.getElementById('fallbackUserWrapper').style.display = fallbackType === 'default_user' ? 'block' : 'none';
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

document.addEventListener('DOMContentLoaded', () => {
    renderMappings(initialMappings);
    toggleFallbackUser();
    document.getElementById('fallbackType').addEventListener('change', toggleFallbackUser);
    runPreview();
});
</script>
@endpush

@extends('layouts.app')

@section('title', 'Form Integration Setup - ' . brand_name())
@section('page-title', 'Form Integration Setup')

@section('content')
<div class="max-w-4xl mx-auto">
    <!-- Progress Steps -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            @for($i = 1; $i <= 6; $i++)
                <div class="flex items-center flex-1">
                    <div class="flex flex-col items-center flex-1">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center font-semibold
                            {{ $step >= $i ? 'bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white' : 'bg-gray-200 text-gray-600' }}">
                            {{ $i }}
                        </div>
                        <div class="mt-2 text-xs text-center text-gray-600">
                            @if($i == 1) Sheet Type
                            @elseif($i == 2) Sheet Config
                            @elseif($i == 3) Field Mapping
                            @elseif($i == 4) Status Columns
                            @elseif($i == 5) Apps Script
                            @else Test & Complete
                            @endif
                        </div>
                    </div>
                    @if($i < 6)
                        <div class="flex-1 h-1 mx-2 {{ $step > $i ? 'bg-gradient-to-r from-[#063A1C] to-[#205A44]' : 'bg-gray-200' }}"></div>
                    @endif
                </div>
            @endfor
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        @if($step == 1)
            <!-- Step 1: Select Sheet Type -->
            <h2 class="text-xl font-semibold text-gray-900 mb-6">Select Sheet Type</h2>
            <form action="{{ route('integrations.form-integration.store-step1') }}" method="POST">
                @csrf
                <div class="space-y-4">
                    <label class="flex items-start p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-[#063A1C] transition-colors">
                        <input type="radio" name="sheet_type" value="meta_facebook" class="mt-1 mr-4" required>
                        <div class="flex-1">
                            <div class="flex items-center">
                                <i class="fab fa-facebook text-blue-600 text-2xl mr-3"></i>
                                <h3 class="font-semibold text-gray-900">Meta/Facebook Sheet</h3>
                            </div>
                            <p class="text-sm text-gray-600 mt-1">Pre-configured field mappings for Meta/Facebook lead forms</p>
                        </div>
                    </label>
                    
                    <label class="flex items-start p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-[#063A1C] transition-colors">
                        <input type="radio" name="sheet_type" value="google_forms" class="mt-1 mr-4" required>
                        <div class="flex-1">
                            <div class="flex items-center">
                                <i class="fab fa-google text-green-600 text-2xl mr-3"></i>
                                <h3 class="font-semibold text-gray-900">Google Forms Sheet</h3>
                            </div>
                            <p class="text-sm text-gray-600 mt-1">Pre-configured field mappings for Google Forms</p>
                        </div>
                    </label>
                    
                    <label class="flex items-start p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-[#063A1C] transition-colors">
                        <input type="radio" name="sheet_type" value="custom" class="mt-1 mr-4" required>
                        <div class="flex-1">
                            <div class="flex items-center">
                                <i class="fas fa-cog text-gray-600 text-2xl mr-3"></i>
                                <h3 class="font-semibold text-gray-900">Custom Sheet</h3>
                            </div>
                            <p class="text-sm text-gray-600 mt-1">Manual field mapping for any custom form</p>
                        </div>
                    </label>
                </div>
                
                <div class="mt-6 flex justify-end">
                    <button type="submit" class="px-6 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg hover:from-[#205A44] hover:to-[#15803d] transition-colors duration-200">
                        Next <i class="fas fa-arrow-right ml-2"></i>
                    </button>
                </div>
            </form>
            
        @elseif($step == 2 && isset($config))
            <!-- Step 2: Google Sheet Configuration -->
            <h2 class="text-xl font-semibold text-gray-900 mb-6">Google Sheet Configuration</h2>
            <form action="{{ route('integrations.form-integration.store-step2', $config->id) }}" method="POST">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Google Sheet URL or ID *</label>
                        <input type="text" name="sheet_id" value="{{ old('sheet_id', $config->sheet_id) }}" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#063A1C] focus:border-[#063A1C]"
                            placeholder="https://docs.google.com/spreadsheets/d/... or Sheet ID">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Sheet Name (Tab Name) *</label>
                        <input type="text" name="sheet_name" value="{{ old('sheet_name', $config->sheet_name) }}" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#063A1C] focus:border-[#063A1C]"
                            placeholder="Sheet1">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">API Key (Optional)</label>
                            <input type="text" name="api_key" value="{{ old('api_key', $config->api_key) }}"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#063A1C] focus:border-[#063A1C]"
                                placeholder="For public sheets">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Service Account JSON Path (Optional)</label>
                            <input type="text" name="service_account_json_path" value="{{ old('service_account_json_path', $config->service_account_json_path) }}"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#063A1C] focus:border-[#063A1C]"
                                placeholder="google-credentials/service-account.json">
                        </div>
                    </div>
                    
                    <div>
                        <button type="button" onclick="autoDetectColumns()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                            <i class="fas fa-search mr-2"></i> Auto-Detect Columns
                        </button>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-between">
                    <a href="{{ route('integrations.form-integration.index') }}" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                        Cancel
                    </a>
                    <button type="submit" class="px-6 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg hover:from-[#205A44] hover:to-[#15803d]">
                        Next <i class="fas fa-arrow-right ml-2"></i>
                    </button>
                </div>
            </form>
            
        @elseif($step == 3 && isset($config))
            <!-- Step 3: Field Mapping -->
            <h2 class="text-xl font-semibold text-gray-900 mb-6">Field Mapping</h2>
            <p class="text-sm text-gray-600 mb-4">Map your sheet columns to CRM fields</p>
            
            <div id="mapping-container">
                <p class="text-gray-500 text-center py-8">Click "Auto-Detect Columns" in Step 2 first, then come back here.</p>
            </div>
            
            <div class="mt-6 flex justify-between">
                <a href="{{ route('integrations.form-integration.step2', $config->id) }}" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                    <i class="fas fa-arrow-left mr-2"></i> Back
                </a>
                <button onclick="saveMappings()" class="px-6 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg hover:from-[#205A44] hover:to-[#15803d]">
                    Save & Continue <i class="fas fa-arrow-right ml-2"></i>
                </button>
            </div>
            
        @elseif($step == 4 && isset($config))
            <!-- Step 4: CRM Status Columns -->
            <h2 class="text-xl font-semibold text-gray-900 mb-6">CRM Status Columns</h2>
            <p class="text-sm text-gray-600 mb-4">Map columns where CRM will update status</p>
            
            <form action="{{ route('integrations.form-integration.store-step4', $config->id) }}" method="POST">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">CRM Sent Status Column</label>
                        <input type="text" name="crm_status_columns[crm_sent_status]" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg"
                            placeholder="Column letter (e.g., AA)">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">CRM Lead ID Column</label>
                        <input type="text" name="crm_status_columns[crm_lead_id]" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg"
                            placeholder="Column letter (e.g., AB)">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">CRM Assigned User Column</label>
                        <input type="text" name="crm_status_columns[crm_assigned_user]" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg"
                            placeholder="Column letter (e.g., AC)">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">CRM Call Date Column</label>
                        <input type="text" name="crm_status_columns[crm_call_date]" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg"
                            placeholder="Column letter (e.g., AD)">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">CRM Call Status Column</label>
                        <input type="text" name="crm_status_columns[crm_call_status]" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg"
                            placeholder="Column letter (e.g., AE)">
                    </div>
                </div>
                
                <div class="mt-6 flex justify-between">
                    <a href="{{ route('integrations.form-integration.step3', $config->id) }}" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                        <i class="fas fa-arrow-left mr-2"></i> Back
                    </a>
                    <button type="submit" class="px-6 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg hover:from-[#205A44] hover:to-[#15803d]">
                        Save & Continue <i class="fas fa-arrow-right ml-2"></i>
                    </button>
                </div>
            </form>
            
        @elseif($step == 5 && isset($config))
            <!-- Step 5: Google Apps Script -->
            <h2 class="text-xl font-semibold text-gray-900 mb-6">Google Apps Script Setup</h2>
            
            <div class="grid gap-4 mb-6 md:grid-cols-2">
                <div class="rounded-lg border border-gray-200 bg-white p-4">
                    <div class="text-sm font-medium text-gray-700 mb-2">Secure Webhook URL</div>
                    <div class="text-xs text-gray-600 break-all bg-gray-50 border rounded p-3">{{ $webhookUrl }}</div>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-4">
                    <div class="text-sm font-medium text-gray-700 mb-2">Inbound API Key</div>
                    <div class="text-xs text-gray-600 break-all bg-gray-50 border rounded p-3">{{ $inboundApiKey }}</div>
                </div>
            </div>

            <div class="mb-4 flex flex-wrap gap-3">
                <button onclick="generateScript()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    <i class="fas fa-code mr-2"></i> Generate Script
                </button>
                <form action="{{ route('integrations.form-integration.rotate-secret', $config->id) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-amber-500 text-white rounded-lg hover:bg-amber-600">
                        <i class="fas fa-key mr-2"></i> Rotate API Key
                    </button>
                </form>
            </div>
            
            <div id="script-container" class="bg-gray-50 p-4 rounded-lg">
                <p class="text-gray-500">Click "Generate Script" to create the secure Google Apps Script code.</p>
            </div>
            
            <div class="mt-6 flex justify-between">
                <a href="{{ route('integrations.form-integration.step4', $config->id) }}" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                    <i class="fas fa-arrow-left mr-2"></i> Back
                </a>
                <form action="{{ route('integrations.form-integration.store-step5', $config->id) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="px-6 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg hover:from-[#205A44] hover:to-[#15803d]">
                        Complete Setup <i class="fas fa-check ml-2"></i>
                    </button>
                </form>
            </div>
            
        @elseif($step == 6 && isset($config))
            <!-- Step 6: Test & Complete -->
            <h2 class="text-xl font-semibold text-gray-900 mb-6">Test Integration</h2>
            
            <div class="grid gap-4 mb-6 md:grid-cols-2">
                <div class="rounded-lg border border-gray-200 bg-white p-4">
                    <div class="text-sm font-medium text-gray-700 mb-2">Secure Webhook URL</div>
                    <div class="text-xs text-gray-600 break-all bg-gray-50 border rounded p-3">{{ $webhookUrl }}</div>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-4">
                    <div class="text-sm font-medium text-gray-700 mb-2">Inbound API Key</div>
                    <div class="text-xs text-gray-600 break-all bg-gray-50 border rounded p-3">{{ $inboundApiKey }}</div>
                </div>
            </div>

            <div class="mb-6 flex flex-wrap gap-3">
                <button onclick="testIntegration()" class="px-6 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg hover:from-[#205A44] hover:to-[#15803d]">
                    <i class="fas fa-vial mr-2"></i> 1-Click Test
                </button>
                <form action="{{ route('integrations.form-integration.rotate-secret', $config->id) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-amber-500 text-white rounded-lg hover:bg-amber-600">
                        <i class="fas fa-key mr-2"></i> Rotate API Key
                    </button>
                </form>
            </div>
            
            <div id="test-result" class="hidden p-4 rounded-lg mb-6"></div>
            
            <div class="mt-6 flex justify-between">
                <a href="{{ route('integrations.form-integration.step5', $config->id) }}" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                    <i class="fas fa-arrow-left mr-2"></i> Back
                </a>
                <a href="{{ route('integrations.form-integration.index') }}" class="px-6 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg hover:from-[#205A44] hover:to-[#15803d]">
                    Go to Integrations <i class="fas fa-check ml-2"></i>
                </a>
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
function autoDetectColumns() {
    // Get form values
    const sheetId = document.querySelector('input[name="sheet_id"]').value;
    const sheetName = document.querySelector('input[name="sheet_name"]').value;
    const apiKey = document.querySelector('input[name="api_key"]').value;
    const serviceAccountPath = document.querySelector('input[name="service_account_json_path"]').value;
    
    // Validate required fields
    if (!sheetId || !sheetName) {
        alert('Please enter Sheet ID and Sheet Name first');
        return;
    }
    
    // Show loading state
    const button = event.target;
    const originalText = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Detecting...';
    
    // Call API
    fetch('{{ route("integrations.form-integration.auto-detect-columns") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            sheet_id: sheetId,
            sheet_name: sheetName,
            api_key: apiKey || null,
            service_account_json_path: serviceAccountPath || null,
        }),
    })
    .then(response => response.json())
    .then(data => {
        button.disabled = false;
        button.innerHTML = originalText;
        
        if (data.success) {
            // Store columns in a hidden field or sessionStorage for Step 3
            sessionStorage.setItem('detected_columns', JSON.stringify(data.columns));
            alert('Successfully detected ' + data.columns.length + ' columns! You can proceed to Step 3.');
        } else {
            alert('Error: ' + (data.message || 'Failed to detect columns'));
        }
    })
    .catch(error => {
        button.disabled = false;
        button.innerHTML = originalText;
        console.error('Error:', error);
        alert('Error: ' + error.message);
    });
}

function saveMappings() {
    // Implementation for saving mappings
    alert('Save mappings - will be implemented');
}

function generateScript() {
    fetch('{{ route('integrations.form-integration.generate-script', $config->id ?? 0) }}')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('script-container').innerHTML = 
                    '<pre class="text-sm overflow-x-auto">' + 
                    escapeHtml(data.script) + 
                    '</pre>' +
                    '<button onclick="copyScript()" class="mt-4 px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">Copy Script</button>';
            } else {
                document.getElementById('script-container').innerHTML =
                    '<div class="text-red-700 bg-red-50 border border-red-200 rounded p-3">' +
                    escapeHtml(data.message || 'Failed to generate script.') +
                    '</div>';
            }
        });
}

function copyScript() {
    const script = document.querySelector('#script-container pre')?.innerText || '';
    if (!script) {
        alert('No script available to copy.');
        return;
    }

    navigator.clipboard.writeText(script)
        .then(() => alert('Script copied to clipboard.'))
        .catch(() => alert('Copy failed. Please copy manually.'));
}

function testIntegration() {
    fetch('{{ route('integrations.form-integration.test', $config->id ?? 0) }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
        },
    })
    .then(response => response.json())
    .then(data => {
        const resultDiv = document.getElementById('test-result');
        resultDiv.classList.remove('hidden');
        
        if (data.success) {
            resultDiv.className = 'p-4 rounded-lg mb-6 bg-green-100 text-green-800';
            resultDiv.innerHTML = '<strong>Success!</strong> ' + data.message + '<br>Lead ID: ' + (data.lead_id || 'N/A');
        } else {
            resultDiv.className = 'p-4 rounded-lg mb-6 bg-red-100 text-red-800';
            resultDiv.innerHTML = '<strong>Error!</strong> ' + data.message;
        }
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>
@endpush
@endsection

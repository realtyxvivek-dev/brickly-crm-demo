@extends('layouts.app')

@section('title', 'Meta Sheet Configuration Setup - ' . brand_name())
@section('page-title', 'Meta Sheet Configuration Setup')

@section('content')
@include('integrations.meta-sheet-guide-modal')
<div class="max-w-4xl mx-auto">
    <!-- Progress Steps -->
    <div class="mb-8">
        @php
            $stepRoutes = [
                1 => null,
                2 => isset($config) ? route('integrations.meta-sheet.step2', $config->id) : null,
                3 => isset($config) ? route('integrations.meta-sheet.step3', $config->id) : null,
                4 => isset($config) ? route('integrations.meta-sheet.step4', $config->id) : null,
                5 => isset($config) ? route('integrations.meta-sheet.step5', $config->id) : null,
                6 => isset($config) ? route('integrations.meta-sheet.step6', $config->id) : null,
            ];
        @endphp
        <div class="flex items-center justify-between">
            @for($i = 1; $i <= 6; $i++)
                <div class="flex items-center flex-1">
                    @if($stepRoutes[$i])
                        <a href="{{ $stepRoutes[$i] }}" class="flex flex-col items-center flex-1 group" title="Go to Step {{ $i }}">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center font-semibold transition-colors
                                {{ $step >= $i ? 'bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white' : 'bg-gray-200 text-gray-600 group-hover:bg-gray-300' }}">
                                {{ $i }}
                            </div>
                            <div class="mt-2 text-xs text-center text-gray-600 group-hover:text-gray-800">
                                @if($i == 1) Info
                                @elseif($i == 2) Sheet Config
                                @elseif($i == 3) Field Mapping
                                @elseif($i == 4) Status Columns
                                @elseif($i == 5) Apps Script
                                @else Test & Complete
                                @endif
                            </div>
                        </a>
                    @else
                        <div class="flex flex-col items-center flex-1">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center font-semibold
                                {{ $step >= $i ? 'bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white' : 'bg-gray-200 text-gray-600' }}">
                                {{ $i }}
                            </div>
                            <div class="mt-2 text-xs text-center text-gray-600">
                                @if($i == 1) Info
                                @elseif($i == 2) Sheet Config
                                @elseif($i == 3) Field Mapping
                                @elseif($i == 4) Status Columns
                                @elseif($i == 5) Apps Script
                                @else Test & Complete
                                @endif
                            </div>
                        </div>
                    @endif
                    @if($i < 6)
                        <div class="flex-1 h-1 mx-2 {{ $step > $i ? 'bg-gradient-to-r from-[#063A1C] to-[#205A44]' : 'bg-gray-200' }}"></div>
                    @endif
                </div>
            @endfor
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        @if($step == 1)
            <!-- Step 1: Info Card (Auto-set to Meta/Facebook) -->
            <div class="text-center">
                <div class="mb-6">
                    <i class="fab fa-facebook text-blue-600 text-6xl mb-4"></i>
                    <h2 class="text-2xl font-semibold text-gray-900 mb-2">Meta/Facebook Sheet Configuration</h2>
                    <p class="text-gray-600">This wizard will configure your Meta/Facebook lead form integration via Google Sheets.</p>
                </div>
                
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 text-left">
                    <h3 class="font-semibold text-blue-900 mb-2">What this will do:</h3>
                    <ul class="list-disc list-inside text-sm text-blue-800 space-y-1">
                        <li>Connect your Meta/Facebook lead form Google Sheet to CRM</li>
                        <li>Auto-map Meta/Facebook form fields to CRM fields</li>
                        <li>Automatically sync new leads from sheet to CRM</li>
                        <li>Update sheet with CRM status (sent, assigned, call info)</li>
                    </ul>
                </div>

                <p class="mb-4">
                    <button type="button" onclick="document.getElementById('metaSheetGuideModal').classList.remove('hidden')" class="text-[#063A1C] hover:text-[#205A44] font-medium underline inline-flex items-center gap-1">
                        <i class="fas fa-book-open"></i>
                        See full connection guide (Meta + CRM steps)
                    </button>
                </p>
                
                <form action="{{ route('integrations.meta-sheet.store-step1') }}" method="POST">
                    @csrf
                    <button type="submit" class="px-6 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg hover:from-[#205A44] hover:to-[#15803d] transition-colors duration-200">
                        Get Started <i class="fas fa-arrow-right ml-2"></i>
                    </button>
                </form>
            </div>
            
        @elseif($step == 2 && isset($config))
            <!-- Step 2: Google Sheet Configuration -->
            <h2 class="text-xl font-semibold text-gray-900 mb-6">Google Sheet Configuration</h2>
            @php
                $hasSelectedColumns = is_array($selectedColumns ?? null) && count($selectedColumns ?? []) > 0;
            @endphp
            <form action="{{ route('integrations.meta-sheet.store-step2', $config->id) }}" method="POST" onsubmit="return validateStep2Form(event)">
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
                            <input type="text" name="service_account_json_path" value="{{ old('service_account_json_path', $config->service_account_json_path ?? 'google-credentials/service-account.json') }}"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#063A1C] focus:border-[#063A1C]"
                                placeholder="google-credentials/service-account.json">
                        </div>
                    </div>
                    
                    <div>
                        <button type="button" onclick="autoDetectColumns()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                            <i class="fas fa-search mr-2"></i> Auto-Detect Columns
                        </button>
                    </div>
                    
                    <!-- Column Selection Section (hidden initially) -->
                    <div id="column-selection-section" class="hidden mt-6">
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                            <h3 class="text-lg font-semibold text-blue-900 mb-2">
                                <i class="fas fa-check-square mr-2"></i>Select Columns to Include
                            </h3>
                            <p class="text-sm text-blue-800">
                                Select only the columns you want to map to CRM. Unselected columns (like id, form_name) will be ignored.
                            </p>
                        </div>
                        
                        <div class="mb-4 flex gap-2">
                            <button type="button" onclick="selectAllColumns()" class="px-3 py-1 text-sm bg-gray-200 text-gray-700 rounded hover:bg-gray-300">
                                <i class="fas fa-check-double mr-1"></i> Select All
                            </button>
                            <button type="button" onclick="deselectAllColumns()" class="px-3 py-1 text-sm bg-gray-200 text-gray-700 rounded hover:bg-gray-300">
                                <i class="fas fa-times mr-1"></i> Deselect All
                            </button>
                        </div>
                        
                        <div class="border border-gray-200 rounded-lg overflow-hidden">
                            <div class="overflow-x-auto max-h-96">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50 sticky top-0">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-12">
                                                <input type="checkbox" id="select-all-checkbox" onchange="toggleAllColumns(this.checked)" class="w-4 h-4 text-[#063A1C] border-gray-300 rounded focus:ring-[#063A1C]">
                                            </th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Column</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Header</th>
                                        </tr>
                                    </thead>
                                    <tbody id="column-selection-table-body" class="bg-white divide-y divide-gray-200">
                                        <!-- Columns will be inserted here by JavaScript -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                            <p class="text-sm text-yellow-800">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                <strong>Required:</strong> At least one column for "Name" and one for "Phone" must be selected.
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Hidden input to store selected columns -->
                <input type="hidden" name="selected_columns" id="selected_columns_input" value="{{ old('selected_columns', $config->selected_columns_json ? json_encode($config->selected_columns_json) : '') }}">
                
                <div class="mt-6 flex justify-between">
                    <div class="flex gap-2">
                        <a href="{{ route('integrations.meta-sheet.index') }}" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                            Cancel
                        </a>
                        <button type="button" onclick="saveDraft(2)" class="px-6 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600">
                            <i class="fas fa-save mr-2"></i> Save as Draft
                        </button>
                    </div>
                    <button type="submit" id="step2-next-btn" class="px-6 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg hover:from-[#205A44] hover:to-[#15803d] disabled:opacity-50 disabled:cursor-not-allowed" @if(!$hasSelectedColumns) disabled @endif>
                        Next <i class="fas fa-arrow-right ml-2"></i>
                    </button>
                </div>
            </form>
            
        @elseif($step == 3 && isset($config))
            <!-- Step 3: Field Mapping -->
            <h2 class="text-xl font-semibold text-gray-900 mb-6">Field Mapping</h2>
            <p class="text-sm text-gray-600 mb-4">Meta/Facebook fields are pre-mapped. Verify and adjust if needed.</p>
            
            <div id="mapping-container">
                <div id="mapping-loading" class="text-center py-8">
                    <i class="fas fa-spinner fa-spin text-gray-400 text-2xl mb-2"></i>
                    <p class="text-gray-500">Loading columns...</p>
                </div>
                <div id="mapping-empty" class="hidden text-center py-8">
                    <p class="text-gray-500 mb-4">Click "Auto-Detect Columns" in Step 2 first, then come back here.</p>
                    <a href="{{ route('integrations.meta-sheet.step2', $config->id) }}" class="inline-block px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                        <i class="fas fa-arrow-left mr-2"></i> Go to Step 2
                    </a>
                </div>
                <div id="mapping-content" class="hidden">
                    <!-- Error Messages Container -->
                    <div id="step3-errors" class="mb-4 hidden">
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-circle text-red-600 mr-2"></i>
                                <div>
                                    <h4 class="font-semibold text-red-800">Validation Errors</h4>
                                    <ul id="step3-error-list" class="mt-2 text-sm text-red-700 list-disc list-inside"></ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    @if($errors->any())
                        <div id="step3-errors" class="mb-4">
                            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                                <div class="flex items-center">
                                    <i class="fas fa-exclamation-circle text-red-600 mr-2"></i>
                                    <div>
                                        <h4 class="font-semibold text-red-800">Validation Errors</h4>
                                        <ul class="mt-2 text-sm text-red-700 list-disc list-inside">
                                            @foreach($errors->all() as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                    
                    <div class="bg-gray-50 rounded-lg p-4 mb-4">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm text-gray-600">
                                <i class="fas fa-info-circle mr-2"></i>
                                Map each sheet column to a CRM field. Required fields (Name, Phone) are pre-mapped. Unmapped columns will be ignored.
                            </p>
                            <button type="button" 
                                    onclick="autoMapAllFields()" 
                                    class="px-4 py-2 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-700 hover:to-blue-800 transition-colors text-sm font-medium">
                                <i class="fas fa-magic mr-2"></i> Auto Map All Fields
                            </button>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 border border-gray-200 rounded-lg">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sheet Column</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Column Header</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Map to CRM Field</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Field Label</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Required</th>
                                </tr>
                            </thead>
                            <tbody id="mapping-table-body" class="bg-white divide-y divide-gray-200">
                                <!-- Mappings will be inserted here by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="mt-6 flex justify-between">
                <div class="flex gap-2">
                    <a href="{{ route('integrations.meta-sheet.step2', $config->id) }}" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                        <i class="fas fa-arrow-left mr-2"></i> Back
                    </a>
                    <button type="button" onclick="saveDraft(3)" class="px-6 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600">
                        <i class="fas fa-save mr-2"></i> Save as Draft
                    </button>
                </div>
                <button onclick="saveMappings()" id="save-mappings-btn" class="px-6 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg hover:from-[#205A44] hover:to-[#15803d] disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                    Save & Continue <i class="fas fa-arrow-right ml-2"></i>
                </button>
            </div>
            
        @elseif($step == 4 && isset($config))
            <!-- Step 4: CRM Status Columns -->
            <h2 class="text-xl font-semibold text-gray-900 mb-6">CRM Status Columns</h2>
            <p class="text-sm text-gray-600 mb-4">Map columns where CRM will update status</p>
            
            <form action="{{ route('integrations.meta-sheet.store-step4', $config->id) }}" method="POST">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">CRM Sent Status Column</label>
                        <input type="text" name="crm_status_columns[crm_sent_status]" 
                            value="{{ old('crm_status_columns.crm_sent_status', $config->crm_status_columns_json['crm_sent_status'] ?? '') }}"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg"
                            placeholder="Column letter (e.g., AA)">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">CRM Lead ID Column</label>
                        <input type="text" name="crm_status_columns[crm_lead_id]" 
                            value="{{ old('crm_status_columns.crm_lead_id', $config->crm_status_columns_json['crm_lead_id'] ?? '') }}"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg"
                            placeholder="Column letter (e.g., AB)">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">CRM Assigned User Column</label>
                        <input type="text" name="crm_status_columns[crm_assigned_user]" 
                            value="{{ old('crm_status_columns.crm_assigned_user', $config->crm_status_columns_json['crm_assigned_user'] ?? '') }}"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg"
                            placeholder="Column letter (e.g., AC)">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">CRM Call Date Column</label>
                        <input type="text" name="crm_status_columns[crm_call_date]" 
                            value="{{ old('crm_status_columns.crm_call_date', $config->crm_status_columns_json['crm_call_date'] ?? '') }}"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg"
                            placeholder="Column letter (e.g., AD)">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">CRM Call Status Column</label>
                        <input type="text" name="crm_status_columns[crm_call_status]" 
                            value="{{ old('crm_status_columns.crm_call_status', $config->crm_status_columns_json['crm_call_status'] ?? '') }}"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg"
                            placeholder="Column letter (e.g., AE)">
                    </div>
                </div>
                
                <div class="mt-6 flex justify-between">
                    <div class="flex gap-2">
                        <a href="{{ route('integrations.meta-sheet.step3', $config->id) }}" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                            <i class="fas fa-arrow-left mr-2"></i> Back
                        </a>
                        <button type="button" onclick="saveDraft(4)" class="px-6 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600">
                            <i class="fas fa-save mr-2"></i> Save as Draft
                        </button>
                    </div>
                    <button type="submit" class="px-6 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg hover:from-[#205A44] hover:to-[#15803d]">
                        Save & Continue <i class="fas fa-arrow-right ml-2"></i>
                    </button>
                </div>
            </form>
            
        @elseif($step == 5 && isset($config))
            <!-- Step 5: Google Apps Script (Modal Button) -->
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

            <div class="mb-4">
                <button onclick="showScriptModal({{ $config->id }})" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium">
                    <i class="fas fa-code mr-2"></i> View Script
                </button>
            </div>
            
            <div class="mt-6 flex justify-between">
                <div class="flex gap-2">
                    <a href="{{ route('integrations.meta-sheet.step4', $config->id) }}" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                        <i class="fas fa-arrow-left mr-2"></i> Back
                    </a>
                    <button type="button" onclick="saveDraft(5)" class="px-6 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600">
                        <i class="fas fa-save mr-2"></i> Save as Draft
                    </button>
                    <form action="{{ route('integrations.meta-sheet.rotate-secret', $config->id) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="px-6 py-2 bg-amber-500 text-white rounded-lg hover:bg-amber-600">
                            <i class="fas fa-key mr-2"></i> Rotate API Key
                        </button>
                    </form>
                </div>
                <form action="{{ route('integrations.meta-sheet.store-step5', $config->id) }}" method="POST" class="inline">
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
                <form action="{{ route('integrations.meta-sheet.rotate-secret', $config->id) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-amber-500 text-white rounded-lg hover:bg-amber-600">
                        <i class="fas fa-key mr-2"></i> Rotate API Key
                    </button>
                </form>
            </div>
            
            <div id="test-result" class="hidden p-4 rounded-lg mb-6"></div>
            
            <div class="mt-6 flex justify-between">
                <a href="{{ route('integrations.meta-sheet.step5', $config->id) }}" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                    <i class="fas fa-arrow-left mr-2"></i> Back
                </a>
                <a href="{{ route('integrations.meta-sheet.index') }}" class="px-6 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg hover:from-[#205A44] hover:to-[#15803d]">
                    Go to Meta Sheets <i class="fas fa-check ml-2"></i>
                </a>
            </div>
        @endif
    </div>
</div>

<!-- Script Modal -->
@include('integrations.meta-sheet-script-modal')

<!-- Create Custom Field Modal -->
<div id="custom-field-modal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-labelledby="custom-field-modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <!-- Background overlay -->
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" onclick="closeCustomFieldModal()"></div>

        <!-- Center the modal -->
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <!-- Modal panel -->
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form id="custom-field-form" onsubmit="createCustomField(event)">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                            <i class="fas fa-plus-circle text-blue-600 text-xl"></i>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left flex-1">
                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="custom-field-modal-title">
                                Create Custom Field
                            </h3>
                            <div class="mt-4 space-y-4">
                                <div>
                                    <label for="field_label" class="block text-sm font-medium text-gray-700 mb-1">Field Label *</label>
                                    <input type="text" 
                                           id="field_label" 
                                           name="field_label" 
                                           required
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#063A1C] focus:border-[#063A1C]"
                                           placeholder="e.g., Company Name">
                                    <p class="mt-1 text-xs text-gray-500">Display name for this field</p>
                                </div>
                                
                                <div>
                                    <label for="field_type" class="block text-sm font-medium text-gray-700 mb-1">Field Type *</label>
                                    <select id="field_type" 
                                            name="field_type" 
                                            required
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#063A1C] focus:border-[#063A1C]">
                                        <option value="text">Text</option>
                                        <option value="textarea">Textarea</option>
                                        <option value="number">Number</option>
                                        <option value="email">Email</option>
                                        <option value="tel">Phone</option>
                                        <option value="select">Dropdown</option>
                                        <option value="date">Date</option>
                                        <option value="time">Time</option>
                                        <option value="datetime">Date and Time</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label for="field_key" class="block text-sm font-medium text-gray-700 mb-1">Field Key</label>
                                    <input type="text" 
                                           id="field_key" 
                                           name="field_key" 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#063A1C] focus:border-[#063A1C]"
                                           placeholder="Auto-generated from label">
                                    <p class="mt-1 text-xs text-gray-500">Unique identifier (auto-generated, editable)</p>
                                </div>
                                
                                <div>
                                    <label for="help_text" class="block text-sm font-medium text-gray-700 mb-1">Help Text (Optional)</label>
                                    <textarea id="help_text" 
                                              name="help_text" 
                                              rows="2"
                                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#063A1C] focus:border-[#063A1C]"
                                              placeholder="Additional information about this field"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" 
                            id="create-field-btn"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-base font-medium text-white hover:from-[#205A44] hover:to-[#15803d] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#063A1C] sm:ml-3 sm:w-auto sm:text-sm">
                        <i class="fas fa-plus mr-2"></i> Create Field
                    </button>
                    <button type="button" 
                            onclick="closeCustomFieldModal()" 
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#063A1C] sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Success/Error Modal -->
<div id="detection-modal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <!-- Background overlay -->
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" onclick="closeDetectionModal()"></div>

        <!-- Center the modal -->
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <!-- Modal panel -->
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div id="modal-icon" class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full sm:mx-0 sm:h-10 sm:w-10">
                        <!-- Icon will be inserted here -->
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left flex-1">
                        <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                            <!-- Title will be inserted here -->
                        </h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500" id="modal-message">
                                <!-- Message will be inserted here -->
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button type="button" id="modal-primary-btn" onclick="handleModalPrimaryAction()" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-base font-medium text-white hover:from-[#205A44] hover:to-[#15803d] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#063A1C] sm:ml-3 sm:w-auto sm:text-sm">
                    <!-- Button text will be inserted here -->
                </button>
                <button type="button" onclick="closeDetectionModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#063A1C] sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
// Meta template and standard fields (available for all steps)
const metaTemplate = @json($template ?? []);
const standardFields = @json($standardFields ?? []);
const customFields = @json($customFields ?? []);

// Modal state
let modalAction = null;
let modalActionUrl = null;

// Show success modal
function showSuccessModal(title, message) {
    const modal = document.getElementById('detection-modal');
    const icon = document.getElementById('modal-icon');
    const titleEl = document.getElementById('modal-title');
    const messageEl = document.getElementById('modal-message');
    const primaryBtn = document.getElementById('modal-primary-btn');
    
    // Set success styling
    icon.className = 'mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-green-100 sm:mx-0 sm:h-10 sm:w-10';
    icon.innerHTML = '<i class="fas fa-check-circle text-green-600 text-2xl"></i>';
    
    titleEl.textContent = title;
    messageEl.textContent = message;
    
    // Set button for success - show "Next Step" if on step 2
    @if($step == 2 && isset($config))
    primaryBtn.textContent = 'Next Step →';
    primaryBtn.className = 'w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-base font-medium text-white hover:from-[#205A44] hover:to-[#15803d] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#063A1C] sm:ml-3 sm:w-auto sm:text-sm';
    modalAction = 'next';
    modalActionUrl = '{{ route("integrations.meta-sheet.step3", $config->id) }}';
    @else
    primaryBtn.textContent = 'OK';
    primaryBtn.className = 'w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-base font-medium text-white hover:from-[#205A44] hover:to-[#15803d] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#063A1C] sm:ml-3 sm:w-auto sm:text-sm';
    modalAction = 'close';
    @endif
    
    modal.classList.remove('hidden');
}

// Show error modal
function showErrorModal(title, message) {
    const modal = document.getElementById('detection-modal');
    const icon = document.getElementById('modal-icon');
    const titleEl = document.getElementById('modal-title');
    const messageEl = document.getElementById('modal-message');
    const primaryBtn = document.getElementById('modal-primary-btn');
    
    // Set error styling
    icon.className = 'mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10';
    icon.innerHTML = '<i class="fas fa-exclamation-circle text-red-600 text-2xl"></i>';
    
    titleEl.textContent = title;
    messageEl.innerHTML = message.replace(/\n/g, '<br>'); // Preserve line breaks
    
    primaryBtn.textContent = 'OK';
    primaryBtn.className = 'w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm';
    modalAction = 'close';
    
    modal.classList.remove('hidden');
}

// Close modal
function closeDetectionModal() {
    const modal = document.getElementById('detection-modal');
    modal.classList.add('hidden');
    modalAction = null;
    modalActionUrl = null;
}

// Handle primary button action
function handleModalPrimaryAction() {
    if (modalAction === 'next' && modalActionUrl) {
        window.location.href = modalActionUrl;
    } else {
        closeDetectionModal();
    }
}

// Custom Field Modal State
let currentFieldSelectIndex = null;

// Show create custom field modal
function showCreateCustomFieldModal(selectIndex, columnHeader = '') {
    currentFieldSelectIndex = selectIndex;
    const modal = document.getElementById('custom-field-modal');
    const fieldLabelInput = document.getElementById('field_label');
    const fieldKeyInput = document.getElementById('field_key');
    
    // Pre-fill field label with column header if available
    if (columnHeader) {
        fieldLabelInput.value = columnHeader.trim();
        // Auto-generate field key
        generateFieldKeyFromLabel();
    } else {
        fieldLabelInput.value = '';
        fieldKeyInput.value = '';
    }
    
    // Reset form
    document.getElementById('field_type').value = 'text';
    document.getElementById('help_text').value = '';
    
    modal.classList.remove('hidden');
    fieldLabelInput.focus();
}

// Close custom field modal
function closeCustomFieldModal() {
    const modal = document.getElementById('custom-field-modal');
    modal.classList.add('hidden');
    currentFieldSelectIndex = null;
    document.getElementById('custom-field-form').reset();
}

// Generate field key from label (no arg = read from modal input and set key field; with arg = return key string)
function generateFieldKeyFromLabel(labelOrEmpty) {
    const labelInput = document.getElementById('field_label');
    const keyInput = document.getElementById('field_key');
    const str = (typeof labelOrEmpty === 'string') ? labelOrEmpty : (labelInput && labelInput.value ? labelInput.value : '');
    const label = (str || '').trim();
    if (!label) {
        if (keyInput && (labelOrEmpty === undefined || labelOrEmpty === null)) keyInput.value = '';
        return '';
    }
    let key = label.toLowerCase()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/_+/g, '_')
        .replace(/^_+|_+$/g, '');
    if (key && !/^[a-z]/.test(key)) key = 'field_' + key;
    if (keyInput && (labelOrEmpty === undefined || labelOrEmpty === null)) keyInput.value = key;
    return key;
}

// Auto-generate field key when label changes
document.addEventListener('DOMContentLoaded', function() {
    const fieldLabelInput = document.getElementById('field_label');
    if (fieldLabelInput) {
        fieldLabelInput.addEventListener('input', function() {
            const keyInput = document.getElementById('field_key');
            // Only auto-generate if key field is empty or matches previous auto-generated value
            if (!keyInput.value || keyInput.dataset.autoGenerated === 'true') {
                generateFieldKeyFromLabel();
                if (keyInput.value) {
                    keyInput.dataset.autoGenerated = 'true';
                }
            }
        });
        
        // Mark as auto-generated when user manually edits key
        const keyInput = document.getElementById('field_key');
        if (keyInput) {
            keyInput.addEventListener('input', function() {
                this.dataset.autoGenerated = 'false';
            });
        }
    }
});

// Create custom field via AJAX
function createCustomField(event) {
    event.preventDefault();
    
    const form = event.target;
    const submitBtn = document.getElementById('create-field-btn');
    const originalBtnText = submitBtn.innerHTML;
    
    // Disable button and show loading
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Creating...';
    
    const formData = {
        field_label: document.getElementById('field_label').value,
        field_type: document.getElementById('field_type').value,
        field_key: document.getElementById('field_key').value || null,
        help_text: document.getElementById('help_text').value || null,
    };
    
    fetch('{{ route("integrations.meta-sheet.create-custom-field") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        body: JSON.stringify(formData),
    })
    .then(response => {
        const ct = response.headers.get('content-type');
        if (!ct || !ct.includes('application/json')) {
            throw new Error('Server returned an invalid response. Please refresh the page and try again.');
        }
        return response.json();
    })
    .then(data => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnText;
        
        if (data.success) {
            // Add field to customFields object
            customFields[data.field.key] = {
                required: data.field.required,
                label: data.field.label,
                type: data.field.type
            };
            
            // Refresh the dropdown and select the new field
            refreshFieldDropdown(currentFieldSelectIndex, data.field.key, data.field.label);
            
            // Close modal
            closeCustomFieldModal();
            
            // Show success message
            showSuccessModal('Custom Field Created', `Field "${data.field.label}" has been created and selected.`);
        } else {
            showErrorModal('Failed to Create Field', data.message || 'An error occurred while creating the field.');
        }
    })
    .catch(error => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnText;
        console.error('Error:', error);
        showErrorModal('Error', 'Failed to create custom field. Please try again.');
    });
}

function autoDetectColumns() {
    const sheetId = document.querySelector('input[name="sheet_id"]').value;
    const sheetName = document.querySelector('input[name="sheet_name"]').value;
    const apiKey = document.querySelector('input[name="api_key"]').value;
    const serviceAccountPath = document.querySelector('input[name="service_account_json_path"]').value;
    
    if (!sheetId || !sheetName) {
        showErrorModal('Missing Information', 'Please enter Sheet ID and Sheet Name first.');
        return;
    }
    
    const button = event.target;
    const originalText = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Detecting...';
    
    fetch('{{ route("integrations.meta-sheet.auto-detect-columns") }}', {
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
    .then(response => {
        // Check if response is ok
        if (!response.ok) {
            return response.json().then(data => {
                throw new Error(data.message || `HTTP ${response.status}: ${response.statusText}`);
            });
        }
        return response.json();
    })
    .then(data => {
        button.disabled = false;
        button.innerHTML = originalText;
        
        if (data.success) {
            sessionStorage.setItem('detected_columns', JSON.stringify(data.columns));
            // Show column selection UI
            displayColumnSelection(data.columns);
            // Show success message
            showSuccessModal('Successfully detected ' + data.columns.length + ' columns!', 'Please select which columns you want to include in the mapping.');
        } else {
            showErrorModal('Failed to detect columns', data.message || 'An error occurred while detecting columns.');
        }
    })
    .catch(error => {
        button.disabled = false;
        button.innerHTML = originalText;
        console.error('Error:', error);
        
        // Show detailed error message
        let errorMessage = 'Failed to fetch columns from Google Sheet.\n\n';
        if (error.message) {
            errorMessage += error.message;
        } else {
            errorMessage += 'Please check:\n';
            errorMessage += '1. Sheet ID and Sheet Name are correct\n';
            errorMessage += '2. Sheet is shared as "Anyone with link" (Viewer access), OR\n';
            errorMessage += '3. Valid API key is provided, OR\n';
            errorMessage += '4. Valid service account JSON file path is provided and the service account has access to the sheet';
        }
        
        showErrorModal('Failed to fetch columns', errorMessage);
    });
}

// Column Selection Functions for Step 2
const savedSelectedColumns = @json($selectedColumns ?? []);

function displayColumnSelection(columns) {
    const tableBody = document.getElementById('column-selection-table-body');
    const section = document.getElementById('column-selection-section');
    
    if (!tableBody || !section) return;
    
    tableBody.innerHTML = '';
    
    // Pre-select columns that match common patterns (name, phone, email)
    const preSelectPatterns = ['name', 'phone', 'mobile', 'email', 'full_name', 'contact'];
    
    columns.forEach((column, index) => {
        const headerText = (column.header || '').trim().toLowerCase();
        const shouldPreSelectByPattern = preSelectPatterns.some(pattern => headerText.includes(pattern));
        
        // Check if column was previously selected (from saved config)
        const wasPreviouslySelected = savedSelectedColumns.includes(column.position);
        
        // Pre-select if it matches pattern OR was previously selected
        const shouldPreSelect = shouldPreSelectByPattern || wasPreviouslySelected;
        
        const row = document.createElement('tr');
        row.className = shouldPreSelect ? 'bg-green-50' : '';
        row.innerHTML = `
            <td class="px-4 py-3 whitespace-nowrap">
                <input type="checkbox" 
                       class="column-checkbox" 
                       data-column="${column.position}"
                       ${shouldPreSelect ? 'checked' : ''}
                       onchange="updateSelectedColumns()">
            </td>
            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                ${column.position}
            </td>
            <td class="px-4 py-3 text-sm text-gray-500">
                ${column.header || '<span class="text-gray-400 italic">(Empty)</span>'}
            </td>
        `;
        tableBody.appendChild(row);
    });
    
    // Show section
    section.classList.remove('hidden');
    
    // Update selected columns
    updateSelectedColumns();
}

function updateSelectedColumns() {
    const checkboxes = document.querySelectorAll('.column-checkbox:checked');
    const selectedColumns = Array.from(checkboxes).map(cb => cb.dataset.column);
    const input = document.getElementById('selected_columns_input');
    const nextBtn = document.getElementById('step2-next-btn');
    
    if (input) {
        input.value = JSON.stringify(selectedColumns);
    }
    
    // Enable/disable Next button based on selection
    if (nextBtn) {
        // Check if at least name and phone-like columns are selected
        const hasName = Array.from(checkboxes).some(cb => {
            const row = cb.closest('tr');
            const header = row.querySelector('td:last-child').textContent.toLowerCase();
            return header.includes('name');
        });
        const hasPhone = Array.from(checkboxes).some(cb => {
            const row = cb.closest('tr');
            const header = row.querySelector('td:last-child').textContent.toLowerCase();
            return header.includes('phone') || header.includes('mobile');
        });
        
        nextBtn.disabled = !(selectedColumns.length > 0 && hasName && hasPhone);
    }
}

function selectAllColumns() {
    document.querySelectorAll('.column-checkbox').forEach(cb => {
        cb.checked = true;
    });
    updateSelectedColumns();
}

function deselectAllColumns() {
    document.querySelectorAll('.column-checkbox').forEach(cb => {
        cb.checked = false;
    });
    updateSelectedColumns();
}

function toggleAllColumns(checked) {
    document.querySelectorAll('.column-checkbox').forEach(cb => {
        cb.checked = checked;
    });
    updateSelectedColumns();
}

// Validate Step 2 form before submission
function validateStep2Form(event) {
    const selectedColumnsInput = document.getElementById('selected_columns_input');
    const selectedColumns = selectedColumnsInput ? JSON.parse(selectedColumnsInput.value || '[]') : [];
    
    if (selectedColumns.length === 0) {
        event.preventDefault();
        showErrorModal('No Columns Selected', 'Please select at least one column to include in the mapping. Required: Name and Phone columns must be selected.');
        return false;
    }
    
    // Check if name and phone columns are selected
    const checkboxes = document.querySelectorAll('.column-checkbox:checked');

    // Edit flow support: if columns already saved previously and user did not re-run auto-detect,
    // allow moving to next step based on saved selection.
    if (checkboxes.length === 0 && selectedColumns.length > 0) {
        return true;
    }

    let hasName = false;
    let hasPhone = false;
    
    checkboxes.forEach(cb => {
        const row = cb.closest('tr');
        const header = row.querySelector('td:last-child').textContent.toLowerCase();
        if (header.includes('name')) hasName = true;
        if (header.includes('phone') || header.includes('mobile')) hasPhone = true;
    });
    
    if (!hasName || !hasPhone) {
        event.preventDefault();
        showErrorModal('Required Columns Missing', 'Please select at least one column for "Name" and one column for "Phone/Mobile". These are required fields.');
        return false;
    }
    
    return true;
}

// Initialize Step 2 Next button when editing existing configuration
document.addEventListener('DOMContentLoaded', function() {
    const selectedColumnsInput = document.getElementById('selected_columns_input');
    const nextBtn = document.getElementById('step2-next-btn');
    if (!selectedColumnsInput || !nextBtn) return;

    try {
        const selectedColumns = JSON.parse(selectedColumnsInput.value || '[]');
        if (Array.isArray(selectedColumns) && selectedColumns.length > 0) {
            nextBtn.disabled = false;
        }
    } catch (error) {
        // Keep button state unchanged if JSON parsing fails.
    }
});

// Load and display columns when step 3 loads
@if($step == 3 && isset($config))
const selectedColumnsFromConfig = @json($selectedColumns ?? []);

function loadDetectedColumns() {
    const detectedColumnsJson = sessionStorage.getItem('detected_columns');
    
    if (!detectedColumnsJson) {
        document.getElementById('mapping-loading').classList.add('hidden');
        document.getElementById('mapping-empty').classList.remove('hidden');
        return;
    }
    
    try {
        const allColumns = JSON.parse(detectedColumnsJson);
        
        // Filter columns by selected_columns_json from config
        const selectedColumns = selectedColumnsFromConfig.length > 0 
            ? selectedColumnsFromConfig 
            : allColumns.map(c => c.position); // Fallback to all if no selection saved
        
        const filteredColumns = allColumns.filter(column => 
            selectedColumns.includes(column.position)
        );
        
        if (filteredColumns.length === 0) {
            document.getElementById('mapping-loading').classList.add('hidden');
            document.getElementById('mapping-empty').classList.remove('hidden');
            document.getElementById('mapping-empty').innerHTML = `
                <p class="text-gray-500 mb-4">No columns selected. Please go back to Step 2 and select columns.</p>
                <a href="{{ route('integrations.meta-sheet.step2', $config->id) }}" class="inline-block px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                    <i class="fas fa-arrow-left mr-2"></i> Go to Step 2
                </a>
            `;
            return;
        }
        
        displayMappings(filteredColumns);
    } catch (e) {
        console.error('Error parsing columns:', e);
        document.getElementById('mapping-loading').classList.add('hidden');
        document.getElementById('mapping-empty').classList.remove('hidden');
    }
}

function displayMappings(columns) {
    const tableBody = document.getElementById('mapping-table-body');
    tableBody.innerHTML = '';
    
    // Build dropdown options with standard and custom fields
    let fieldOptions = '<option value="">-- Select CRM Field --</option>';
    
    // Add standard fields
    const standardFieldKeys = Object.keys(standardFields).filter(key => !customFields.hasOwnProperty(key));
    if (standardFieldKeys.length > 0) {
        fieldOptions += '<optgroup label="Standard Fields">';
        standardFieldKeys.forEach(key => {
            fieldOptions += `<option value="${key}">${standardFields[key].label}</option>`;
        });
        fieldOptions += '</optgroup>';
    }
    
    // Add custom fields
    if (Object.keys(customFields).length > 0) {
        fieldOptions += '<optgroup label="Custom Fields">';
        Object.keys(customFields).forEach(key => {
            fieldOptions += `<option value="${key}">${customFields[key].label}</option>`;
        });
        fieldOptions += '</optgroup>';
    }
    
    columns.forEach((column, index) => {
        const headerText = (column.header || '').trim();
        const columnLetter = column.position;
        
        // Auto-map using template
        let mappedField = '';
        let fieldLabel = headerText || `Column ${columnLetter}`;
        let isRequired = false;
        
        // Try to find in template
        const normalizedHeader = headerText.toLowerCase().replace(/[^a-z0-9]/g, '_');
        if (metaTemplate[normalizedHeader]) {
            mappedField = metaTemplate[normalizedHeader];
        } else {
            // Try fuzzy matching
            for (const [templateKey, templateValue] of Object.entries(metaTemplate)) {
                if (normalizedHeader.includes(templateKey.replace(/[^a-z0-9]/g, '_')) || 
                    templateKey.replace(/[^a-z0-9]/g, '_').includes(normalizedHeader)) {
                    mappedField = templateValue;
                    break;
                }
            }
        }
        
        // Special handling for name and phone (auto-map only; required is user choice)
        if (headerText.toLowerCase().includes('name') && !mappedField) {
            mappedField = 'name';
        } else if ((headerText.toLowerCase().includes('phone') || headerText.toLowerCase().includes('mobile')) && !mappedField) {
            mappedField = 'phone';
        }
        
        const row = document.createElement('tr');
        row.className = '';
        row.innerHTML = `
            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                ${columnLetter}
            </td>
            <td class="px-4 py-3 text-sm text-gray-500">
                ${headerText || '<span class="text-gray-400 italic">(Empty)</span>'}
            </td>
            <td class="px-4 py-3 whitespace-nowrap">
                <div class="flex items-center gap-2">
                    <select name="mappings[${index}][lead_field_key]" 
                            id="field-select-${index}"
                            class="crm-field-select flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#063A1C] focus:border-[#063A1C]">
                        ${fieldOptions.replace(`value="${mappedField}"`, `value="${mappedField}" selected`)}
                    </select>
                    <button type="button" 
                            onclick="showCreateCustomFieldModal(${index}, '${headerText.replace(/'/g, "\\'")}')" 
                            class="px-3 py-2 text-sm text-[#063A1C] border border-[#063A1C] rounded-lg hover:bg-[#063A1C] hover:text-white transition-colors" 
                            title="Create Custom Field">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
                <input type="hidden" name="mappings[${index}][sheet_column]" value="${columnLetter}">
            </td>
            <td class="px-4 py-3 whitespace-nowrap">
                <input type="text" 
                       name="mappings[${index}][field_label]" 
                       value="${fieldLabel}"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#063A1C] focus:border-[#063A1C]">
            </td>
            <td class="px-4 py-3 whitespace-nowrap text-center">
                <input type="checkbox" 
                       name="mappings[${index}][is_required]" 
                       value="1"
                       class="w-4 h-4 text-[#063A1C] border-gray-300 rounded focus:ring-[#063A1C]">
            </td>
        `;
        
        tableBody.appendChild(row);
    });
    
    // Show content
    document.getElementById('mapping-loading').classList.add('hidden');
    document.getElementById('mapping-empty').classList.add('hidden');
    document.getElementById('mapping-content').classList.remove('hidden');
    document.getElementById('save-mappings-btn').disabled = false;
}

// Helper function to capitalize first letter of each word
function capitalizeWords(str) {
    return str.replace(/\b\w/g, char => char.toUpperCase());
}

// Helper function to create custom field via AJAX
async function createCustomFieldForMapping(fieldLabel, fieldKey) {
    try {
        const response = await fetch('{{ route("integrations.meta-sheet.create-custom-field") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                field_label: fieldLabel,
                field_type: 'text', // Default to text for auto-created fields
                field_key: fieldKey,
                help_text: `Auto-generated from sheet column: ${fieldLabel}`
            })
        });
        
        const ct = response.headers.get('content-type');
        if (!ct || !ct.includes('application/json')) {
            throw new Error('Server returned an invalid response. Please refresh the page and try again.');
        }
        const data = await response.json();
        
        if (data.success && data.field) {
            // Add to customFields object
            customFields[data.field.key] = {
                required: data.field.required,
                label: data.field.label,
                type: data.field.type
            };
            
            return data.field;
        } else {
            throw new Error(data.message || 'Failed to create custom field');
        }
    } catch (error) {
        console.error('Error creating custom field:', error);
        throw error;
    }
}

// Helper function to refresh all dropdowns with new custom field
function refreshAllDropdownsWithNewField(newFieldKey, newFieldLabel) {
    const allSelects = document.querySelectorAll('#mapping-table-body select[name*="[lead_field_key]"]');
    
    allSelects.forEach(selectElement => {
        // Check if option already exists
        const existingOption = selectElement.querySelector(`option[value="${newFieldKey}"]`);
        if (existingOption) {
            return; // Already exists, skip
        }
        
        // Find the custom fields optgroup or create it
        let customFieldsGroup = selectElement.querySelector('optgroup[label="Custom Fields"]');
        
        if (!customFieldsGroup) {
            // Create custom fields optgroup
            customFieldsGroup = document.createElement('optgroup');
            customFieldsGroup.label = 'Custom Fields';
            
            // Insert before the closing select tag or after standard fields group
            const standardFieldsGroup = selectElement.querySelector('optgroup[label="Standard Fields"]');
            if (standardFieldsGroup && standardFieldsGroup.nextSibling) {
                selectElement.insertBefore(customFieldsGroup, standardFieldsGroup.nextSibling);
            } else {
                selectElement.appendChild(customFieldsGroup);
            }
        }
        
        // Add new option
        const newOption = document.createElement('option');
        newOption.value = newFieldKey;
        newOption.textContent = newFieldLabel;
        customFieldsGroup.appendChild(newOption);
    });
}

// Auto-map all fields function (async to support field creation)
async function autoMapAllFields() {
    const rows = document.querySelectorAll('#mapping-table-body tr');
    let mappedCount = 0;
    let createdCount = 0;
    let skippedCount = 0;
    
    // Show loading modal
    const loadingModal = showLoadingModal('Auto-mapping fields...');
    
    // Process rows sequentially to avoid race conditions
    for (let index = 0; index < rows.length; index++) {
        const row = rows[index];
        const headerCell = row.querySelector('td:nth-child(2)');
        const selectElement = row.querySelector('select[name*="[lead_field_key]"]');
        const fieldLabelInput = row.querySelector('input[name*="[field_label]"]');
        const requiredCheckbox = row.querySelector('input[name*="[is_required]"]');
        
        if (!headerCell || !selectElement) {
            skippedCount++;
            continue;
        }
        
        const headerText = (headerCell.textContent.trim() || '').replace(/(Empty)/g, '').trim();
        if (!headerText) {
            skippedCount++;
            continue;
        }
        
        // Use same auto-mapping logic as displayMappings
        let mappedField = '';
        let fieldLabel = headerText;
        let isRequired = false;
        
        // Try to find in template
        const normalizedHeader = headerText.toLowerCase().replace(/[^a-z0-9]/g, '_');
        if (metaTemplate[normalizedHeader]) {
            mappedField = metaTemplate[normalizedHeader];
        } else {
            // Try fuzzy matching
            for (const [templateKey, templateValue] of Object.entries(metaTemplate)) {
                const templateKeyNormalized = templateKey.replace(/[^a-z0-9]/g, '_');
                if (normalizedHeader.includes(templateKeyNormalized) || 
                    templateKeyNormalized.includes(normalizedHeader)) {
                    mappedField = templateValue;
                    break;
                }
            }
        }
        
        // Special handling for name, phone, email, etc. (required is user choice)
        if (!mappedField) {
            if (headerText.toLowerCase().includes('name')) {
                mappedField = 'name';
            } else if (headerText.toLowerCase().includes('phone') || headerText.toLowerCase().includes('mobile')) {
                mappedField = 'phone';
            } else if (headerText.toLowerCase().includes('email')) {
                mappedField = 'email';
            } else if (headerText.toLowerCase().includes('city') || headerText.toLowerCase().includes('lucknow')) {
                mappedField = 'city';
            } else if (headerText.toLowerCase().includes('budget') || headerText.toLowerCase().includes('price')) {
                mappedField = 'budget';
            } else if (headerText.toLowerCase().includes('requirement') || headerText.toLowerCase().includes('looking')) {
                mappedField = 'requirements';
            } else if (headerText.toLowerCase().includes('property') || headerText.toLowerCase().includes('type')) {
                mappedField = 'property_type';
            } else if (headerText.toLowerCase().includes('location') || headerText.toLowerCase().includes('preferred')) {
                mappedField = 'preferred_location';
            } else if (headerText.toLowerCase().includes('use') || headerText.toLowerCase().includes('purpose')) {
                mappedField = 'use_end_use';
            } else if (headerText.toLowerCase().includes('when') || headerText.toLowerCase().includes('buy')) {
                mappedField = 'possession_status';
            } else if (headerText.toLowerCase().includes('state')) {
                mappedField = 'state';
            } else if (headerText.toLowerCase().includes('source')) {
                mappedField = 'source';
            } else if (headerText.toLowerCase().includes('note') || headerText.toLowerCase().includes('comment')) {
                mappedField = 'notes';
            }
        }
        
        // If still not mapped, try to find in existing custom fields
        if (!mappedField && Object.keys(customFields).length > 0) {
            for (const [customKey, customField] of Object.entries(customFields)) {
                const customLabel = customField.label || '';
                if (headerText.toLowerCase() === customLabel.toLowerCase() || 
                    headerText.toLowerCase().includes(customLabel.toLowerCase()) ||
                    customLabel.toLowerCase().includes(headerText.toLowerCase())) {
                    mappedField = customKey;
                    break;
                }
            }
        }
        
        // If still not mapped, create a new custom field
        if (!mappedField) {
            try {
                const fieldLabel = capitalizeWords(headerText.replace(/_/g, ' '));
                const fieldKey = generateFieldKeyFromLabel(headerText);
                
                const newField = await createCustomFieldForMapping(fieldLabel, fieldKey);
                
                if (newField) {
                    mappedField = newField.key;
                    fieldLabel = newField.label;
                    createdCount++;
                    
                    // Refresh all dropdowns with the new field
                    refreshAllDropdownsWithNewField(newField.key, newField.label);
                }
            } catch (error) {
                console.error(`Failed to create custom field for "${headerText}":`, error);
                skippedCount++;
                continue;
            }
        }
        
        // Set the mapping if found or created
        if (mappedField) {
            selectElement.value = mappedField;
            
            // Update field label
            if (fieldLabelInput) {
                fieldLabelInput.value = headerText;
            }
            
            // Required is always user choice - do not auto-check or disable
            if (requiredCheckbox) {
                requiredCheckbox.checked = false;
                requiredCheckbox.disabled = false;
            }
            
            mappedCount++;
        } else {
            skippedCount++;
        }
    }
    
    closeLoadingModal(loadingModal);
    
    // Show success message
    let message = `Auto-mapped ${mappedCount} field(s).`;
    if (createdCount > 0) {
        message += ` Created ${createdCount} new custom field(s).`;
    }
    if (skippedCount > 0) {
        message += ` ${skippedCount} field(s) could not be auto-mapped or created - you can map them manually or leave them unmapped.`;
    }
    showSuccessModal('Auto-Mapping Complete', message);
}

// Save as Draft function
function saveDraft(step) {
    const configId = {{ $config->id ?? 0 }};
    if (!configId) {
        showErrorModal('Error', 'Configuration ID not found.');
        return;
    }
    
    let formData = {
        step: step,
        _token: '{{ csrf_token() }}'
    };
    
    // Collect form data based on step
    if (step == 2) {
        formData.sheet_id = document.querySelector('input[name="sheet_id"]').value;
        formData.sheet_name = document.querySelector('input[name="sheet_name"]').value;
        formData.api_key = document.querySelector('input[name="api_key"]').value || null;
        formData.service_account_json_path = document.querySelector('input[name="service_account_json_path"]').value || null;
        formData.selected_columns = document.getElementById('selected_columns_input')?.value || null;
    } else if (step == 3) {
        // Collect mappings
        const mappings = [];
        const rows = document.querySelectorAll('#mapping-table-body tr');
        rows.forEach((row) => {
            const sheetColumn = row.querySelector('input[name*="[sheet_column]"]')?.value;
            const leadFieldKey = row.querySelector('select[name*="[lead_field_key]"]')?.value;
            const fieldLabel = row.querySelector('input[name*="[field_label]"]')?.value;
            const isRequired = row.querySelector('input[name*="[is_required]"]')?.checked || false;
            
            if (sheetColumn && leadFieldKey) {
                mappings.push({
                    sheet_column: sheetColumn,
                    lead_field_key: leadFieldKey,
                    field_label: fieldLabel || leadFieldKey,
                    is_required: isRequired
                });
            }
        });
        formData.mappings = mappings;
    } else if (step == 4) {
        formData.crm_status_columns = {
            crm_sent_status: document.querySelector('input[name="crm_status_columns[crm_sent_status]"]')?.value || '',
            crm_lead_id: document.querySelector('input[name="crm_status_columns[crm_lead_id]"]')?.value || '',
            crm_assigned_user: document.querySelector('input[name="crm_status_columns[crm_assigned_user]"]')?.value || '',
            crm_call_date: document.querySelector('input[name="crm_status_columns[crm_call_date]"]')?.value || '',
            crm_call_status: document.querySelector('input[name="crm_status_columns[crm_call_status]"]')?.value || '',
        };
    }
    
    // Show loading
    const loadingModal = showLoadingModal('Saving draft...');
    
    fetch(`{{ route('integrations.meta-sheet.save-draft', $config->id ?? 0) }}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(formData),
    })
    .then(response => response.json())
    .then(data => {
        closeLoadingModal(loadingModal);
        if (data.success) {
            showSuccessModal('Draft Saved', 'Your progress has been saved. You can continue later from where you left off.');
        } else {
            showErrorModal('Failed to Save Draft', data.message || 'An error occurred while saving the draft.');
        }
    })
    .catch(error => {
        closeLoadingModal(loadingModal);
        console.error('Error:', error);
        showErrorModal('Error', 'Failed to save draft. Please try again.');
    });
}

function showLoadingModal(message) {
    const modal = document.createElement('div');
    modal.id = 'loading-modal';
    modal.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50';
    modal.innerHTML = `
        <div class="bg-white rounded-lg p-6 flex items-center gap-4">
            <i class="fas fa-spinner fa-spin text-2xl text-[#063A1C]"></i>
            <span class="text-lg">${message}</span>
        </div>
    `;
    document.body.appendChild(modal);
    return modal;
}

function closeLoadingModal(modal) {
    if (modal && modal.parentNode) {
        modal.parentNode.removeChild(modal);
    }
}

// Refresh field dropdown after custom field creation
function refreshFieldDropdown(selectIndex, newFieldKey, newFieldLabel) {
    const selectElement = document.getElementById(`field-select-${selectIndex}`);
    if (!selectElement) {
        console.error('Select element not found for index:', selectIndex);
        return;
    }
    
    // Check if option already exists
    const existingOption = selectElement.querySelector(`option[value="${newFieldKey}"]`);
    if (existingOption) {
        // Just select it
        selectElement.value = newFieldKey;
        return;
    }
    
    // Find the custom fields optgroup or create it
    let customFieldsGroup = selectElement.querySelector('optgroup[label="Custom Fields"]');
    
    if (!customFieldsGroup) {
        // Create custom fields optgroup
        customFieldsGroup = document.createElement('optgroup');
        customFieldsGroup.label = 'Custom Fields';
        
        // Insert before the closing select tag or after standard fields group
        const standardFieldsGroup = selectElement.querySelector('optgroup[label="Standard Fields"]');
        if (standardFieldsGroup && standardFieldsGroup.nextSibling) {
            selectElement.insertBefore(customFieldsGroup, standardFieldsGroup.nextSibling);
        } else {
            selectElement.appendChild(customFieldsGroup);
        }
    }
    
    // Create and add new option
    const newOption = document.createElement('option');
    newOption.value = newFieldKey;
    newOption.textContent = newFieldLabel;
    customFieldsGroup.appendChild(newOption);
    
    // Select the new option
    selectElement.value = newFieldKey;
    
    // Trigger change event
    selectElement.dispatchEvent(new Event('change', { bubbles: true }));
}

async function saveMappings() {
    // Collect mappings
    const mappings = [];
    const rows = document.querySelectorAll('#mapping-table-body tr');
    
    rows.forEach((row, index) => {
        const sheetColumn = row.querySelector('input[name*="[sheet_column]"]').value;
        const leadFieldKey = row.querySelector('select[name*="[lead_field_key]"]').value;
        const fieldLabel = row.querySelector('input[name*="[field_label]"]').value;
        const isRequired = row.querySelector('input[name*="[is_required]"]').checked;
        
        if (leadFieldKey) { // Only include if mapped to a CRM field
            mappings.push({
                sheet_column: sheetColumn,
                lead_field_key: leadFieldKey,
                field_label: fieldLabel,
                is_required: isRequired
            });
        }
    });
    
    // At least one mapping required
    if (mappings.length === 0) {
        showErrorModal('No Mappings', 'Please map at least one column to a CRM field.');
        return;
    }
    
    // Show info if some columns are unmapped
    const totalRows = rows.length;
    const mappedRows = mappings.length;
    if (mappedRows < totalRows) {
        const unmappedCount = totalRows - mappedRows;
        if (!confirm(`${unmappedCount} column(s) are not mapped and will be ignored during import. Continue?`)) {
            return;
        }
    }
    
    // Show loading
    const loadingModal = showLoadingModal('Saving mappings...');
    
    // Hide previous errors
    const errorContainer = document.getElementById('step3-errors');
    const errorList = document.getElementById('step3-error-list');
    if (errorContainer) {
        errorContainer.classList.add('hidden');
    }
    if (errorList) {
        errorList.innerHTML = '';
    }
    
    // Disable save button
    const saveBtn = document.getElementById('save-mappings-btn');
    if (saveBtn) {
        saveBtn.disabled = true;
    }
    
    try {
        const response = await fetch('{{ route("integrations.meta-sheet.store-step3", $config->id) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                mappings: mappings
            })
        });
        
        const data = await response.json();
        
        closeLoadingModal(loadingModal);
        
        if (response.ok && data.success) {
            // Success - redirect to step 4
            if (data.redirect) {
                window.location.href = data.redirect;
            } else {
                window.location.href = '{{ route("integrations.meta-sheet.step4", $config->id) }}';
            }
        } else {
            // Validation errors or other errors
            let errorMessage = data.message || 'Failed to save mappings.';
            
            if (data.errors) {
                // Display validation errors
                if (errorContainer && errorList) {
                    errorList.innerHTML = '';
                    Object.keys(data.errors).forEach(key => {
                        const errors = Array.isArray(data.errors[key]) ? data.errors[key] : [data.errors[key]];
                        errors.forEach(error => {
                            const li = document.createElement('li');
                            li.textContent = error;
                            errorList.appendChild(li);
                        });
                    });
                    errorContainer.classList.remove('hidden');
                } else {
                    // Fallback to modal if container not found
                    showErrorModal('Validation Error', errorMessage);
                }
            } else {
                showErrorModal('Error', errorMessage);
            }
            
            // Re-enable save button
            if (saveBtn) {
                saveBtn.disabled = false;
            }
        }
    } catch (error) {
        closeLoadingModal(loadingModal);
        console.error('Error saving mappings:', error);
        showErrorModal('Network Error', 'Failed to save mappings. Please check your connection and try again.');
        
        // Re-enable save button
        if (saveBtn) {
            saveBtn.disabled = false;
        }
    }
}

// Initialize when page loads (only for step 3)
document.addEventListener('DOMContentLoaded', function() {
    loadDetectedColumns();
});
@endif

function showScriptModal(configId) {
    // Fetch script
    fetch(`/integrations/meta-sheet/generate-script/${configId}`, {
        method: 'GET',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
        },
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('script-content').value = data.script;
            document.getElementById('script-modal').classList.remove('hidden');
        } else {
            alert('Failed to generate script: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error: ' + error.message);
    });
}

function closeScriptModal() {
    document.getElementById('script-modal').classList.add('hidden');
}

function copyScriptToClipboard() {
    const scriptContent = document.getElementById('script-content');
    scriptContent.select();
    scriptContent.setSelectionRange(0, 99999); // For mobile devices
    
    try {
        document.execCommand('copy');
        
        // Show success message
        const copyButton = document.getElementById('copy-script-btn');
        const originalText = copyButton.innerHTML;
        copyButton.innerHTML = '<i class="fas fa-check mr-2"></i> Copied!';
        copyButton.classList.add('bg-green-600');
        copyButton.classList.remove('bg-blue-600');
        
        setTimeout(() => {
            copyButton.innerHTML = originalText;
            copyButton.classList.remove('bg-green-600');
            copyButton.classList.add('bg-blue-600');
        }, 2000);
    } catch (err) {
        alert('Failed to copy. Please select and copy manually.');
    }
}

function testIntegration() {
    const configId = {{ $config->id ?? 0 }};
    const resultDiv = document.getElementById('test-result');
    const testButton = event.target;
    const originalText = testButton.innerHTML;
    
    // Show loading state
    resultDiv.classList.remove('hidden');
    resultDiv.className = 'p-4 rounded-lg mb-6 bg-blue-100 text-blue-800';
    resultDiv.innerHTML = '<strong>Testing...</strong> Please wait...';
    testButton.disabled = true;
    testButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Testing...';
    
    fetch(`/integrations/meta-sheet/test/${configId}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
    })
    .then(response => {
        if (!response.ok) {
            return response.json().then(err => {
                throw new Error(err.message || `HTTP ${response.status}: ${response.statusText}`);
            });
        }
        return response.json();
    })
    .then(data => {
        resultDiv.classList.remove('hidden');
        
        if (data.success) {
            resultDiv.className = 'p-4 rounded-lg mb-6 bg-green-100 text-green-800';
            resultDiv.innerHTML = '<strong>Success!</strong> ' + data.message + '<br>Lead ID: ' + (data.lead_id || 'N/A');
        } else {
            resultDiv.className = 'p-4 rounded-lg mb-6 bg-red-100 text-red-800';
            resultDiv.innerHTML = '<strong>Error!</strong> ' + (data.message || 'Test failed');
        }
        testButton.disabled = false;
        testButton.innerHTML = originalText;
    })
    .catch(error => {
        console.error('Error:', error);
        resultDiv.classList.remove('hidden');
        resultDiv.className = 'p-4 rounded-lg mb-6 bg-red-100 text-red-800';
        
        let errorMessage = 'Failed to connect to server. ';
        if (error.message) {
            errorMessage += error.message;
        } else if (error instanceof TypeError && error.message.includes('fetch')) {
            errorMessage += 'Please check your internet connection and make sure the server is running.';
        } else {
            errorMessage += 'Please try again or check the server logs.';
        }
        
        resultDiv.innerHTML = '<strong>Error!</strong> ' + errorMessage;
        testButton.disabled = false;
        testButton.innerHTML = originalText;
    });
}
</script>
@endpush
@endsection

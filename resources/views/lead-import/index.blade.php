@extends('layouts.app')

@section('title', 'Lead Import - ' . brand_name())
@section('page-title', 'Lead Import')

@section('header-actions')
    <a href="{{ route('lead-import.simple') }}" class="px-4 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg hover:from-[#205A44] hover:to-[#15803d] transition-colors duration-200 text-sm font-medium mr-2">
        Simple Import
    </a>
    <a href="{{ route('lead-import.csv') }}" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors duration-200 text-sm font-medium mr-2">
        Import CSV
    </a>
    <button onclick="openGoogleSheetsModal()" class="px-4 py-2 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-lg hover:from-green-700 hover:to-green-800 transition-all duration-200 text-sm font-medium shadow-md">
        + Add Google Sheet
    </button>
@endsection

@section('content')
    @if(session('success'))
        <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <!-- Stats Grid (phone: 50% x 2, two rows) -->
    <div class="grid grid-cols-2 md:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-8">
        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
            <h3 class="text-sm font-medium text-gray-500 mb-2">Total Imports</h3>
            <div class="text-3xl font-bold text-gray-800">{{ $stats['total_imports'] }}</div>
        </div>
        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
            <h3 class="text-sm font-medium text-gray-500 mb-2">Leads Imported</h3>
            <div class="text-3xl font-bold text-gray-800">{{ number_format($stats['total_leads_imported']) }}</div>
        </div>
        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
            <h3 class="text-sm font-medium text-gray-500 mb-2">Pending</h3>
            <div class="text-3xl font-bold text-gray-800">{{ $stats['pending_imports'] }}</div>
        </div>
        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
            <h3 class="text-sm font-medium text-gray-500 mb-2">Failed</h3>
            <div class="text-3xl font-bold text-gray-800">{{ $stats['failed_imports'] }}</div>
        </div>
    </div>

    <div class="bg-gradient-to-r from-emerald-50 to-teal-50 border border-emerald-200 rounded-xl p-6 mb-8">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold text-gray-800">Simple Import</h2>
                <p class="text-sm text-gray-600 mt-1">Excel ya CSV upload karo, preview dekho, aur directly bulk leads import karo.</p>
            </div>
            <a href="{{ route('lead-import.simple') }}" class="inline-flex items-center justify-center px-4 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg hover:from-[#205A44] hover:to-[#15803d] transition-colors duration-200 font-medium">
                Open Simple Import
            </a>
        </div>
    </div>

    <!-- Google Sheets Configurations -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-8">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold text-gray-800">Google Sheets Configurations</h2>
        </div>
        
        @if($configs->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sheet Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Sync</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Auto-Sync</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Interval</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Automation</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($configs as $config)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ $config->sheet_name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $config->last_sync_at ? $config->last_sync_at->diffForHumans() : 'Never' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($config->auto_sync_enabled)
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            Enabled
                                        </span>
                                    @else
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                            Disabled
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $config->sync_interval_minutes }} min
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $config->automation->name ?? 'Manual' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button onclick="syncSheet({{ $config->id }})" class="text-brand-secondary hover:text-brand-primary mr-3">Sync</button>
                                    <button onclick="editConfig({{ $config->id }})" class="text-brand-secondary hover:text-brand-primary mr-3">Edit</button>
                                    <button onclick="deleteConfig({{ $config->id }})" class="text-red-600 hover:text-red-900">Delete</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-12">
                <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <h3 class="text-lg font-medium text-gray-700 mb-2">No Google Sheets configured</h3>
                <p class="text-gray-500 mb-4">Add your first Google Sheet to start importing leads automatically</p>
                <button onclick="openGoogleSheetsModal()" class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg hover:from-[#205A44] hover:to-[#15803d] transition-colors duration-200 font-medium">
                    + Add Google Sheet
                </button>
            </div>
        @endif
    </div>

    <!-- Recent Imports -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold text-gray-800">Recent Imports</h2>
            <a href="{{ route('lead-import.history') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors duration-200 text-sm font-medium">
                View All
            </a>
        </div>
        
        @if($recentImports->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Source</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Leads</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Imported</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Failed</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($recentImports as $import)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $import->created_at->format('M d, Y H:i') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                        {{ strtoupper($import->import_kind === 'old_crm' ? 'OLD CRM' : $import->source_type) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $import->total_leads }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $import->imported_leads }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $import->failed_leads }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($import->status === 'completed')
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            Completed
                                        </span>
                                    @elseif($import->status === 'failed')
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                            Failed
                                        </span>
                                    @else
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                            {{ ucfirst($import->status) }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-12">
                <p class="text-gray-500">No imports yet</p>
            </div>
        @endif
    </div>

    <!-- Recent Imported Leads -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mt-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold text-gray-800">Recent Imported Leads</h2>
        </div>
        
        @if(isset($recentImportedLeads) && $recentImportedLeads->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Source</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assigned To</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Imported At</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($recentImportedLeads as $importedLead)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $importedLead->lead?->name ?? 'N/A' }}
                                    @if($importedLead->lead?->trashed())
                                        <span class="ml-1 text-xs text-gray-400">(deleted)</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <a href="tel:{{ $importedLead->lead?->phone ?? '' }}" class="text-blue-600 hover:underline">
                                        {{ $importedLead->lead?->phone ?? 'N/A' }}
                                    </a>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                        {{ strtoupper($importedLead->importBatch->source_type ?? 'N/A') }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    @php
                                        // Check actual assignment from LeadAssignment table (most accurate)
                                        $lead = $importedLead->lead;
                                        $actualAssignment = $lead ? $lead->activeAssignments()->first() : null;
                                        $assignedUser = $actualAssignment ? $actualAssignment->assignedTo : ($importedLead->assignedTo ?? null);
                                    @endphp
                                    {{ $assignedUser ? $assignedUser->name : 'Unassigned' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $importedLead->created_at->format('M d, Y H:i') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    @if($importedLead->lead && !$importedLead->lead->trashed())
                                        <a href="{{ route('leads.show', $importedLead->lead->id) }}" class="text-indigo-600 hover:text-indigo-900 font-medium">
                                            View
                                        </a>
                                    @elseif($importedLead->lead?->trashed())
                                        <span class="text-gray-400" title="Lead was deleted">Deleted</span>
                                    @else
                                        <span class="text-gray-400">N/A</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-12">
                <p class="text-gray-500">No imported leads yet</p>
            </div>
        @endif
    </div>

    <!-- Google Sheets Config Modal -->
    @include('lead-import.google-sheets-config-modal')

    @push('scripts')
    <script>
        function openGoogleSheetsModal() {
            // Set default service account path if field is empty
            const serviceAccountPathField = document.getElementById('service_account_json_path');
            if (!serviceAccountPathField.value) {
                serviceAccountPathField.value = 'google-credentials/google-service-account.json';
            }
            document.getElementById('google_sheets_modal').classList.remove('hidden');
        }

        function closeGoogleSheetsModal() {
            document.getElementById('google_sheets_modal').classList.add('hidden');
            document.getElementById('google_sheets_form').reset();
            // Set default service account path after reset
            const serviceAccountPathField = document.getElementById('service_account_json_path');
            if (serviceAccountPathField) {
                serviceAccountPathField.value = 'google-credentials/google-service-account.json';
            }
        }

        function editConfig(id) {
            // Load config and open modal
            axios.get(`{{ route('lead-import.google-sheets.config') }}?config_id=${id}`)
                .then(response => {
                    const config = response.data.config;
                    // Populate form
                    document.getElementById('config_id').value = config.id;
                    document.getElementById('sheet_id').value = config.sheet_id;
                    document.getElementById('sheet_name').value = config.sheet_name;
                    document.getElementById('api_key').value = config.api_key || '';
                    document.getElementById('service_account_json_path').value = config.service_account_json_path || 'google-credentials/google-service-account.json';
                    document.getElementById('range').value = config.range;
                    document.getElementById('name_column').value = config.name_column;
                    document.getElementById('phone_column').value = config.phone_column;
                    document.getElementById('auto_sync_enabled').checked = config.auto_sync_enabled;
                    if (config.auto_sync_enabled) {
                        document.querySelector('input[name="auto_sync_enabled"][type="hidden"]').value = '1';
                    }
                    document.getElementById('sync_interval_minutes').value = config.sync_interval_minutes || 2;
                    
                    // Load custom column mappings
                    if (config.column_mappings && config.column_mappings.length > 0) {
                        customColumnRows = [];
                        document.getElementById('custom-columns-list').innerHTML = '';
                        config.column_mappings.forEach(mapping => {
                            addCustomColumnRow(mapping);
                        });
                    }
                    
                    openGoogleSheetsModal();
                })
                .catch(error => {
                    alert('Error loading configuration');
                });
        }

        function syncSheet(id) {
            if (!confirm('Start sync now?')) return;
            
            axios.post('{{ route('lead-import.google-sheets.sync') }}', { config_id: id })
                .then(response => {
                    alert(response.data.message);
                    location.reload();
                })
                .catch(error => {
                    alert('Sync failed: ' + (error.response?.data?.message || error.message));
                });
        }

        function deleteConfig(id) {
            if (!confirm('Are you sure you want to delete this configuration?')) return;
            
            axios.delete(`{{ route('lead-import.google-sheets.config.delete', '') }}/${id}`)
                .then(response => {
                    alert('Configuration deleted successfully');
                    location.reload();
                })
                .catch(error => {
                    alert('Delete failed');
                });
        }
    </script>
    @endpush
@endsection

@extends('layouts.app')

@section('title', 'Sheet Assignments - ' . brand_name())
@section('page-title', 'Sheet Assignments')
@section('page-subtitle', 'Configure lead assignment for Google Sheets')

@section('header-actions')
    <a href="{{ route('lead-assignment.index') }}" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors duration-200 text-sm font-medium">
        ← Back
    </a>
@endsection

@section('content')
    @if(isset($error))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
            {{ $error }}
        </div>
    @endif
    
    @if($sheets->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
            <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            <h3 class="text-lg font-semibold text-gray-900 mb-2">No Google Sheets Configured</h3>
            <p class="text-gray-600 mb-6">You need to configure Google Sheets first before you can set up sheet assignments.</p>
            <a href="{{ route('lead-import.index') }}" class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg hover:from-[#205A44] hover:to-[#15803d] transition-colors duration-200">
                Go to Lead Import
            </a>
        </div>
    @else
    <div class="space-y-6">
        @foreach($sheets as $sheet)
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">{{ $sheet->sheet_name }}</h3>
                        <p class="text-sm text-gray-500">Sheet ID: {{ $sheet->sheet_id }}</p>
                    </div>
                    <button onclick="editSheetConfig({{ $sheet->id }})" class="px-4 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg hover:from-[#205A44] hover:to-[#15803d] text-sm">
                        Configure
                    </button>
                </div>

                @if($sheet->linkedTelecaller)
                    <div class="mb-2">
                        <span class="text-sm text-gray-600">Linked Sales Executive:</span>
                        <span class="font-medium text-gray-900">{{ $sheet->linkedTelecaller->name }}</span>
                    </div>
                @endif

                @if($sheet->sheetAssignmentConfig)
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-gray-600">Assignment Method:</span>
                            <span class="font-medium ml-2 text-gray-900">
                                @if($sheet->sheetAssignmentConfig->assignment_method)
                                    {{ ucfirst(str_replace('_', ' ', $sheet->sheetAssignmentConfig->assignment_method)) }}
                                @else
                                    <span class="text-gray-400">Not Set</span>
                                @endif
                            </span>
                        </div>
                        <div>
                            <span class="text-gray-600">Auto-Assign:</span>
                            <span class="font-medium ml-2 {{ $sheet->sheetAssignmentConfig->auto_assign_enabled ? 'text-green-600' : 'text-red-600' }}">
                                {{ $sheet->sheetAssignmentConfig->auto_assign_enabled ? 'Enabled' : 'Disabled' }}
                            </span>
                        </div>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
    @endif

    <!-- Config Modal -->
    <div id="config-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-lg p-6 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Configure Sheet Assignment</h3>
            <form id="config-form">
                <input type="hidden" id="config-sheet-id">
                
                <div class="mb-4 hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Link to User (Optional)</label>
                    <select id="config-telecaller" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        <option value="">None</option>
                        @if(isset($eligibleUsers))
                            @foreach($eligibleUsers as $roleName => $users)
                                <optgroup label="{{ $roleName }}">
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        @endif
                    </select>
                </div>

                <div class="mb-4 hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Per-Sheet Daily Limit</label>
                    <input type="number" id="config-per-sheet-limit" min="0" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="Leave empty for no limit">
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Assignment Method</label>
                    <select id="config-method" class="w-full px-4 py-2 border border-gray-300 rounded-lg" onchange="togglePercentageConfig()">
                        <option value="manual">Manual</option>
                        <option value="round_robin">Round Robin</option>
                        <option value="first_available">First Available</option>
                        <option value="percentage">Percentage-Based</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="flex items-center">
                        <input type="checkbox" id="config-auto-assign" class="mr-2">
                        <span class="text-sm font-medium text-gray-700">Enable Auto-Assignment</span>
                    </label>
                </div>

                <div id="percentage-config" class="hidden mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Percentage Distribution</label>
                    <div id="percentage-list" class="space-y-2">
                        <!-- Will be populated dynamically -->
                    </div>
                    <button type="button" onclick="addPercentageRow()" class="mt-2 text-sm text-indigo-600 hover:text-indigo-800">+ Add User</button>
                </div>

                <div id="round-robin-config" class="hidden mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Select Users for Round Robin</label>
                    <div id="round-robin-list" class="space-y-2">
                        <!-- Will be populated dynamically -->
                    </div>
                    <button type="button" onclick="addRoundRobinRow()" class="mt-2 text-sm text-indigo-600 hover:text-indigo-800">+ Add User</button>
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeConfigModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg hover:from-[#205A44] hover:to-[#15803d]">Save</button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        const eligibleUsers = @json($eligibleUsers ?? []);
        let percentageRows = [];
        let roundRobinRows = [];

        function editSheetConfig(sheetId) {
            document.getElementById('config-sheet-id').value = sheetId;
            
            // Load existing config
            axios.get(`{{ route('lead-assignment.sheet-assignments') }}?sheet_id=${sheetId}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
                .then(response => {
                    const config = response.data.config;
                    if (config) {
                        // Populate form with existing config
                        document.getElementById('config-telecaller').value = config.linked_telecaller_id || '';
                        document.getElementById('config-per-sheet-limit').value = config.per_sheet_daily_limit || '';
                        document.getElementById('config-method').value = config.assignment_method || 'manual';
                        document.getElementById('config-auto-assign').checked = config.auto_assign_enabled || false;
                        
                        // Load percentage configs if method is percentage
                        if (config.assignment_method === 'percentage' && config.percentage_configs && config.percentage_configs.length > 0) {
                            percentageRows = [];
                            document.getElementById('percentage-list').innerHTML = '';
                            config.percentage_configs.forEach(pc => {
                                addPercentageRow();
                                const lastRow = document.querySelector('#percentage-list > div:last-child');
                                if (lastRow) {
                                    lastRow.querySelector('.telecaller-select').value = pc.user_id || '';
                                    lastRow.querySelector('.percentage-input').value = pc.percentage || '';
                                    lastRow.querySelector('.limit-input').value = pc.daily_limit || '';
                                }
                            });
                        } else if (config.assignment_method === 'percentage') {
                            // If percentage method but no configs, add one empty row
                            percentageRows = [];
                            document.getElementById('percentage-list').innerHTML = '';
                            addPercentageRow();
                        }
                        
                        // Load round robin configs if method is round_robin
                        if (config.assignment_method === 'round_robin' && config.round_robin_configs) {
                            roundRobinRows = [];
                            document.getElementById('round-robin-list').innerHTML = '';
                            config.round_robin_configs.forEach(rc => {
                                addRoundRobinRow();
                                const lastRow = document.querySelector('#round-robin-list > div:last-child');
                                if (lastRow) {
                                    lastRow.querySelector('.round-robin-select').value = rc.user_id;
                                    lastRow.querySelector('.round-robin-limit-input').value = rc.daily_limit || '';
                                }
                            });
                        }
                        
                        togglePercentageConfig();
                    } else {
                        // Reset form for new config (no existing config found)
                        document.getElementById('config-form').reset();
                        document.getElementById('config-sheet-id').value = sheetId;
                        document.getElementById('config-method').value = 'manual';
                        document.getElementById('config-auto-assign').checked = false;
                        percentageRows = [];
                        roundRobinRows = [];
                        document.getElementById('percentage-list').innerHTML = '';
                        document.getElementById('round-robin-list').innerHTML = '';
                        document.getElementById('percentage-config').classList.add('hidden');
                        document.getElementById('round-robin-config').classList.add('hidden');
                    }
                })
                .catch(error => {
                    console.error('Error loading config:', error);
                    // Reset form on error
                    document.getElementById('config-form').reset();
                    document.getElementById('config-sheet-id').value = sheetId;
                    document.getElementById('config-method').value = 'manual';
                    document.getElementById('config-auto-assign').checked = false;
                    percentageRows = [];
                    document.getElementById('percentage-list').innerHTML = '';
                    document.getElementById('percentage-config').classList.add('hidden');
                });
            
            // Show modal
            document.getElementById('config-modal').classList.remove('hidden');
        }

        function closeConfigModal() {
            document.getElementById('config-modal').classList.add('hidden');
            percentageRows = [];
            roundRobinRows = [];
            document.getElementById('percentage-list').innerHTML = '';
            document.getElementById('round-robin-list').innerHTML = '';
        }

        function togglePercentageConfig() {
            const method = document.getElementById('config-method').value;
            const percentageDiv = document.getElementById('percentage-config');
            const roundRobinDiv = document.getElementById('round-robin-config');
            
            if (method === 'percentage') {
                percentageDiv.classList.remove('hidden');
                roundRobinDiv.classList.add('hidden');
                if (percentageRows.length === 0) {
                    addPercentageRow();
                }
            } else if (method === 'round_robin') {
                percentageDiv.classList.add('hidden');
                roundRobinDiv.classList.remove('hidden');
                if (document.getElementById('round-robin-list').children.length === 0) {
                    addRoundRobinRow();
                }
            } else {
                percentageDiv.classList.add('hidden');
                roundRobinDiv.classList.add('hidden');
            }
        }

        function addPercentageRow() {
            const row = document.createElement('div');
            row.className = 'flex gap-2 items-end';
            
            // Build options grouped by role
            let optionsHtml = '<option value="">Select User</option>';
            Object.keys(eligibleUsers).forEach(roleName => {
                optionsHtml += `<optgroup label="${roleName}">`;
                eligibleUsers[roleName].forEach(user => {
                    optionsHtml += `<option value="${user.id}">${user.name}</option>`;
                });
                optionsHtml += '</optgroup>';
            });
            
            row.innerHTML = `
                <select class="flex-1 px-4 py-2 border border-gray-300 rounded-lg telecaller-select">
                    ${optionsHtml}
                </select>
                <input type="number" step="0.01" min="0" max="100" placeholder="%" class="w-24 px-4 py-2 border border-gray-300 rounded-lg percentage-input">
                <input type="number" min="0" placeholder="Daily Limit" class="w-32 px-4 py-2 border border-gray-300 rounded-lg limit-input">
                <button type="button" onclick="this.parentElement.remove()" class="px-3 py-2 bg-red-100 text-red-600 rounded-lg hover:bg-red-200">Remove</button>
            `;
            document.getElementById('percentage-list').appendChild(row);
            percentageRows.push(row);
        }

        function addRoundRobinRow() {
            const row = document.createElement('div');
            row.className = 'flex gap-2 items-end';
            
            // Build options grouped by role
            let optionsHtml = '<option value="">Select User</option>';
            Object.keys(eligibleUsers).forEach(roleName => {
                optionsHtml += `<optgroup label="${roleName}">`;
                eligibleUsers[roleName].forEach(user => {
                    optionsHtml += `<option value="${user.id}">${user.name}</option>`;
                });
                optionsHtml += '</optgroup>';
            });
            
            row.innerHTML = `
                <select class="flex-1 px-4 py-2 border border-gray-300 rounded-lg round-robin-select">
                    ${optionsHtml}
                </select>
                <input type="number" min="0" placeholder="Daily Limit" class="w-32 px-4 py-2 border border-gray-300 rounded-lg round-robin-limit-input">
                <button type="button" onclick="this.parentElement.remove()" class="px-3 py-2 bg-red-100 text-red-600 rounded-lg hover:bg-red-200">Remove</button>
            `;
            document.getElementById('round-robin-list').appendChild(row);
            roundRobinRows.push(row);
        }

        document.getElementById('config-form').addEventListener('submit', function(e) {
            e.preventDefault();
            const sheetId = document.getElementById('config-sheet-id').value;
            const method = document.getElementById('config-method').value;
            const autoAssign = document.getElementById('config-auto-assign').checked;
            const telecallerId = document.getElementById('config-telecaller').value;
            const perSheetLimit = document.getElementById('config-per-sheet-limit').value;

            const data = {
                sheet_id: sheetId,
                assignment_method: method,
                auto_assign_enabled: autoAssign,
                telecaller_id: telecallerId,
                per_sheet_daily_limit: perSheetLimit || null,
            };

            if (method === 'percentage') {
                const percentageConfig = [];
                document.querySelectorAll('#percentage-list > div').forEach(row => {
                    const telecallerId = row.querySelector('.telecaller-select').value;
                    const percentage = row.querySelector('.percentage-input').value;
                    const limit = row.querySelector('.limit-input').value;
                    if (telecallerId && percentage && !isNaN(parseFloat(percentage))) {
                        percentageConfig.push({
                            user_id: parseInt(telecallerId),
                            percentage: parseFloat(percentage),
                            daily_limit: limit ? parseInt(limit) : null
                        });
                    }
                });
                
                if (percentageConfig.length === 0) {
                    alert('Please add at least one user with percentage for percentage-based assignment.');
                    return;
                }
                
                data.percentage_config = percentageConfig;
            } else if (method === 'round_robin') {
                const roundRobinConfig = [];
                document.querySelectorAll('#round-robin-list > div').forEach(row => {
                    const userId = row.querySelector('.round-robin-select').value;
                    const limit = row.querySelector('.round-robin-limit-input').value;
                    if (userId) {
                        roundRobinConfig.push({
                            user_id: userId,
                            daily_limit: limit || null
                        });
                    }
                });
                data.round_robin_config = roundRobinConfig;
            }

            // First update sheet assignment
            if (telecallerId || perSheetLimit) {
                axios.post('{{ route("lead-assignment.sheet-assignments.assign") }}', {
                    sheet_id: sheetId,
                    telecaller_id: telecallerId || null,
                    per_sheet_daily_limit: perSheetLimit || null
                });
            }

            // Then update config
            axios.post('{{ route("lead-assignment.sheet-assignments.config") }}', data)
                .then(response => {
                    if (response.data.success) {
                        alert(response.data.message || 'Configuration saved successfully!');
                        closeConfigModal();
                        // Reload after a short delay to ensure data is saved
                        setTimeout(() => {
                            window.location.reload();
                        }, 500);
                    } else {
                        alert('Error: ' + (response.data.message || 'Failed to update config'));
                    }
                })
                .catch(error => {
                    console.error('Config update error:', error);
                    const errorMsg = error.response?.data?.message 
                        || error.response?.data?.errors 
                        || 'Failed to update config. Please try again.';
                    alert('Error: ' + (typeof errorMsg === 'string' ? errorMsg : JSON.stringify(errorMsg)));
                });
        });
    </script>
    @endpush
@endsection


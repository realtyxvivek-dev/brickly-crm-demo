@extends('layouts.app')

@section('title', 'Old CRM Import - ' . brand_name())
@section('page-title', 'Old CRM Import')

@section('content')
    <div class="max-w-7xl mx-auto space-y-6">
        @if($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-700">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
            <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">Advanced Old CRM Import</h2>
                    <p class="text-sm text-gray-500 mt-1">CSV upload, field mapping, stage mapping, custom field creation, validation aur profile reuse ek hi flow me.</p>
                </div>
                <div class="flex gap-3">
                    <a href="{{ route('lead-import.index') }}" class="px-4 py-2 rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200 text-sm font-medium">Back</a>
                    <a href="{{ route('lead-import.csv') }}" class="px-4 py-2 rounded-lg bg-emerald-50 text-emerald-800 hover:bg-emerald-100 text-sm font-medium">Simple CSV Import</a>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-4 gap-6">
            <div class="xl:col-span-3 space-y-6">
                <section class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Step 1. Upload CSV</h3>
                            <p class="text-sm text-gray-500">File upload karo aur headers detect karao.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-[1fr_auto] gap-4 items-end">
                        <div>
                            <label for="csvFile" class="block text-sm font-medium text-gray-700 mb-2">Old CRM CSV File</label>
                            <input id="csvFile" type="file" accept=".csv,.txt" class="block w-full rounded-lg border border-gray-300 px-4 py-3 text-sm text-gray-900">
                            <p class="mt-2 text-xs text-gray-500">Header row required hai. File size max 20 MB.</p>
                        </div>
                        <button type="button" id="analyzeBtn" class="px-5 py-3 rounded-lg bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white text-sm font-medium hover:from-[#205A44] hover:to-[#15803d]">
                            Analyze File
                        </button>
                    </div>

                    <div id="uploadSummary" class="hidden mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700"></div>
                </section>

                <section id="mappingSection" class="hidden bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Step 2. Map Fields</h3>
                            <p class="text-sm text-gray-500">Har CSV column ko CRM lead field, metadata field, ya custom field se map karo.</p>
                        </div>
                        <div class="flex flex-col sm:flex-row gap-3">
                            <select id="savedProfileSelect" class="hidden rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900"></select>
                            <button type="button" id="applyProfileBtn" class="hidden px-4 py-2 rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 text-sm font-medium">
                                Apply Profile
                            </button>
                        </div>
                    </div>

                    <div class="overflow-x-auto border border-gray-200 rounded-xl">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">CSV Column</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Sample Value</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Target Field</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">New Field Config</th>
                                </tr>
                            </thead>
                            <tbody id="mappingTableBody" class="divide-y divide-gray-200 bg-white"></tbody>
                        </table>
                    </div>

                    <div id="stageMappingSection" class="hidden mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4">
                        <div class="mb-3">
                            <h4 class="text-sm font-semibold text-amber-900">Stage To 5-Step Pipeline Mapping</h4>
                            <p class="text-xs text-amber-700 mt-1">Old stage values ko `Lead`, `Follow Up`, `Meeting`, `Site Visit`, ya `Closer` me map karo.</p>
                        </div>
                        <div id="stageMappingRows" class="grid grid-cols-1 md:grid-cols-2 gap-3"></div>
                    </div>

                    <div id="ownerMappingSection" class="hidden mt-6 rounded-xl border border-sky-200 bg-sky-50 p-4">
                        <div class="mb-3">
                            <h4 class="text-sm font-semibold text-sky-900">Owner To CRM User Mapping</h4>
                            <p class="text-xs text-sky-700 mt-1">Old CRM owner values ko current CRM users se map karo. Unmapped owners import ko block karenge.</p>
                        </div>
                        <div id="ownerMappingRows" class="grid grid-cols-1 md:grid-cols-2 gap-3"></div>
                    </div>
                </section>

                <section id="validationSection" class="hidden bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Step 3. Validate</h3>
                            <p class="text-sm text-gray-500">Import se pehle validation summary aur mapped preview dekh lo.</p>
                        </div>
                        <div class="flex gap-3">
                            <button type="button" id="validateDemoBtn" class="px-4 py-2 rounded-lg border border-[#205A44] text-[#063A1C] hover:bg-emerald-50 text-sm font-medium">Validate Demo Row</button>
                            <button type="button" id="validateBtn" class="px-4 py-2 rounded-lg bg-gray-900 text-white hover:bg-gray-800 text-sm font-medium">Validate All Rows</button>
                        </div>
                    </div>

                    <div id="validationSummary" class="hidden space-y-4">
                        <div id="validationStats" class="grid grid-cols-2 lg:grid-cols-5 gap-3"></div>
                        <div id="validationWarnings" class="hidden rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"></div>
                        <div id="validationErrors" class="hidden rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"></div>
                        <div>
                            <h4 class="text-sm font-semibold text-gray-800 mb-3">Mapped Preview</h4>
                            <div class="overflow-x-auto border border-gray-200 rounded-xl">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Row</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Name</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Phone</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Source</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">CRM Status</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Imported Meta</th>
                                        </tr>
                                    </thead>
                                    <tbody id="validationPreviewBody" class="divide-y divide-gray-200 bg-white"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </section>

                <section id="importSection" class="hidden bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Step 4. Import</h3>
                            <p class="text-sm text-gray-500">Validation pass ho jaye to profile save karke import run karo.</p>
                        </div>
                        <div class="flex gap-3">
                            <button type="button" id="demoImportBtn" class="px-4 py-2 rounded-lg border border-[#205A44] text-[#063A1C] hover:bg-emerald-50 text-sm font-medium">Import 1 Demo Lead</button>
                            <button type="button" id="finalImportBtn" class="px-4 py-2 rounded-lg bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white hover:from-[#205A44] hover:to-[#15803d] text-sm font-medium">Import All Rows</button>
                        </div>
                    </div>

                    <div class="mt-5 grid grid-cols-1 md:grid-cols-[auto_1fr] gap-4 items-center">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" id="saveProfileToggle" class="rounded border-gray-300 text-emerald-700 focus:ring-emerald-600">
                            <span>Save this mapping as profile</span>
                        </label>
                        <input type="text" id="profileNameInput" placeholder="Profile name, e.g. Old CRM March Export" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-900">
                    </div>
                </section>
            </div>

            <aside class="space-y-6">
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                    <h3 class="text-sm font-semibold text-gray-900 mb-3">Import Rules</h3>
                    <ul class="space-y-2 text-sm text-gray-600">
                        <li>`name` aur `phone` mapping mandatory hai.</li>
                        <li>`owner` ko current CRM user se map karke lead assign hogi.</li>
                        <li>`created on`, original `source`, aur old `stage` metadata me preserve rahenge.</li>
                        <li>5 stage buckets: `Lead`, `Follow Up`, `Meeting`, `Site Visit`, `Closer`.</li>
                        <li>Extra columns ko existing ya naye custom fields me map kar sakte ho.</li>
                        <li>Duplicate phone wale rows skip ho jayenge.</li>
                    </ul>
                </div>

                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                    <h3 class="text-sm font-semibold text-gray-900 mb-3">Saved Profiles</h3>
                    <div id="profileList" class="space-y-2 text-sm text-gray-600">
                        <p>No saved profiles yet.</p>
                    </div>
                </div>
            </aside>
        </div>
    </div>

    <form id="importSubmitForm" method="POST" action="{{ route('lead-import.old-crm.import') }}" class="hidden">
        @csrf
        <input type="hidden" name="file_token" id="formFileToken">
        <input type="hidden" name="mapping_config" id="formMappingConfig">
        <input type="hidden" name="stage_mapping" id="formStageMapping">
        <input type="hidden" name="lead_status_mapping" id="formLeadStatusMapping">
        <input type="hidden" name="owner_mapping" id="formOwnerMapping">
        <input type="hidden" name="create_custom_fields" id="formCreateCustomFields">
        <input type="hidden" name="import_mode" id="formImportMode">
        <input type="hidden" name="save_profile" id="formSaveProfile">
        <input type="hidden" name="profile_name" id="formProfileName">
        <input type="hidden" name="profile_id" id="formProfileId">
    </form>

    @push('scripts')
    <script>
        const wizardContext = @json($wizardContext);
        const state = {
            fileToken: null,
            headers: [],
            previewRows: [],
            distinctValuesByColumn: {},
            matchedProfiles: [],
            mappings: {},
            createCustomFields: {},
            stageMapping: {},
            leadStatusMapping: {},
            ownerMapping: {},
            selectedProfileId: '',
            lastValidation: null,
        };

        const targetOptions = [
            { label: 'Skip Column', value: '' },
            ...wizardContext.lead_fields.map(item => ({ label: `Lead: ${item.label}`, value: item.value })),
            ...wizardContext.meta_fields.map(item => ({ label: `Meta: ${item.label}`, value: item.value })),
            ...wizardContext.custom_fields.map(item => ({ label: `Custom: ${item.label}`, value: item.value })),
            { label: 'Create New Custom Field', value: '__create_custom__' },
        ];

        const csvFile = document.getElementById('csvFile');
        const analyzeBtn = document.getElementById('analyzeBtn');
        const uploadSummary = document.getElementById('uploadSummary');
        const mappingSection = document.getElementById('mappingSection');
        const mappingTableBody = document.getElementById('mappingTableBody');
        const savedProfileSelect = document.getElementById('savedProfileSelect');
        const applyProfileBtn = document.getElementById('applyProfileBtn');
        const stageMappingSection = document.getElementById('stageMappingSection');
        const stageMappingRows = document.getElementById('stageMappingRows');
        const ownerMappingSection = document.getElementById('ownerMappingSection');
        const ownerMappingRows = document.getElementById('ownerMappingRows');
        const validationSummary = document.getElementById('validationSummary');
        const validationStats = document.getElementById('validationStats');
        const validationWarnings = document.getElementById('validationWarnings');
        const validationErrors = document.getElementById('validationErrors');
        const validationPreviewBody = document.getElementById('validationPreviewBody');
        const importSection = document.getElementById('importSection');
        const profileList = document.getElementById('profileList');
        const saveProfileToggle = document.getElementById('saveProfileToggle');
        const profileNameInput = document.getElementById('profileNameInput');

        renderSavedProfilesList();

        analyzeBtn.addEventListener('click', async () => {
            if (!csvFile.files.length) {
                alert('Please select a CSV file first.');
                return;
            }

            const formData = new FormData();
            formData.append('csv_file', csvFile.files[0]);
            formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);

            analyzeBtn.disabled = true;
            analyzeBtn.textContent = 'Analyzing...';

            try {
                const response = await fetch('{{ route('lead-import.old-crm.analyze') }}', {
                    method: 'POST',
                    body: formData,
                });
                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Analyze failed.');
                }

                const payload = data.data;
                state.fileToken = payload.file_token;
                state.headers = payload.headers;
                state.previewRows = payload.preview_rows;
                state.distinctValuesByColumn = payload.distinct_values_by_column || {};
                state.matchedProfiles = payload.matched_profiles || [];
                state.mappings = {};
                state.createCustomFields = {};
                state.stageMapping = {};
                state.leadStatusMapping = {};
                state.ownerMapping = {};
                state.lastValidation = null;
                state.selectedProfileId = '';

                uploadSummary.innerHTML = `
                    <div class="flex flex-col gap-1">
                        <strong class="text-slate-900">${payload.total_rows} rows detected</strong>
                        <span>Headers found: ${payload.headers.map(h => escapeHtml(h.label)).join(', ')}</span>
                        <span>${payload.matched_profiles.length} saved profile matches found for this header structure.</span>
                    </div>
                `;
                uploadSummary.classList.remove('hidden');

                renderMappingTable();
                renderProfilePicker();
                mappingSection.classList.remove('hidden');
                validationSummary.classList.add('hidden');
                importSection.classList.add('hidden');
            } catch (error) {
                alert(error.message);
            } finally {
                analyzeBtn.disabled = false;
                analyzeBtn.textContent = 'Analyze File';
            }
        });

        applyProfileBtn.addEventListener('click', () => {
            const profileId = savedProfileSelect.value;
            const profile = [...wizardContext.profiles, ...state.matchedProfiles].find(item => String(item.id) === String(profileId));
            if (!profile) {
                return;
            }

            state.mappings = profile.mapping_config || {};
            state.stageMapping = profile.stage_mapping || {};
            state.leadStatusMapping = profile.lead_status_mapping || {};
            state.ownerMapping = {};
            state.selectedProfileId = String(profile.id);
            renderMappingTable();
            renderStageMapping();
            renderOwnerMapping();
        });

        document.getElementById('validateBtn').addEventListener('click', () => runValidation('all'));
        document.getElementById('validateDemoBtn').addEventListener('click', () => runValidation('demo'));
        document.getElementById('demoImportBtn').addEventListener('click', () => submitImport('demo'));
        document.getElementById('finalImportBtn').addEventListener('click', () => submitImport('all'));

        async function runValidation(importMode) {
            if (!state.fileToken) {
                alert('Please analyze a CSV file first.');
                return;
            }

            try {
                const response = await fetch('{{ route('lead-import.old-crm.validate') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        file_token: state.fileToken,
                        mapping_config: state.mappings,
                        stage_mapping: state.stageMapping,
                        lead_status_mapping: state.leadStatusMapping,
                        owner_mapping: state.ownerMapping,
                        create_custom_fields: state.createCustomFields,
                        import_mode: importMode,
                    }),
                });
                const data = await response.json();
                if (!data.success) {
                    throw new Error(data.message || 'Validation failed.');
                }

                state.lastValidation = data.data;
                renderValidation(data.data);
                importSection.classList.remove('hidden');
            } catch (error) {
                alert(error.message);
            }
        }

        function submitImport(importMode) {
            if (!state.lastValidation) {
                alert('Please run validation before import.');
                return;
            }

            document.getElementById('formFileToken').value = state.fileToken;
            document.getElementById('formMappingConfig').value = JSON.stringify(state.mappings);
            document.getElementById('formStageMapping').value = JSON.stringify(state.stageMapping);
            document.getElementById('formLeadStatusMapping').value = JSON.stringify(state.leadStatusMapping);
            document.getElementById('formOwnerMapping').value = JSON.stringify(state.ownerMapping);
            document.getElementById('formCreateCustomFields').value = JSON.stringify(state.createCustomFields);
            document.getElementById('formImportMode').value = importMode;
            document.getElementById('formSaveProfile').value = saveProfileToggle.checked ? '1' : '0';
            document.getElementById('formProfileName').value = profileNameInput.value;
            document.getElementById('formProfileId').value = state.selectedProfileId || '';
            document.getElementById('importSubmitForm').submit();
        }

        function renderMappingTable() {
            mappingTableBody.innerHTML = state.headers.map((header) => {
                const sampleRow = state.previewRows.find(row => String(row.values?.[header.index] || '').trim() !== '');
                const sampleValue = sampleRow?.values?.[header.index] || '';
                const selectedTarget = state.mappings[header.index] || '';
                const createConfig = selectedTarget.startsWith('create_custom:') ? (state.createCustomFields[selectedTarget.split(':')[1]] || {}) : null;
                const selectOptions = targetOptions.map(option => `
                    <option value="${escapeHtml(option.value)}" ${option.value === selectedTarget || (option.value === '__create_custom__' && selectedTarget.startsWith('create_custom:')) ? 'selected' : ''}>
                        ${escapeHtml(option.label)}
                    </option>
                `).join('');

                return `
                    <tr>
                        <td class="px-4 py-3 align-top">
                            <div class="font-medium text-gray-900">${escapeHtml(header.label)}</div>
                            <div class="text-xs text-gray-500 mt-1">Column #${header.index + 1}</div>
                        </td>
                        <td class="px-4 py-3 align-top text-sm text-gray-600 max-w-xs break-words">${escapeHtml(sampleValue || '-')}</td>
                        <td class="px-4 py-3 align-top">
                            <select data-column-index="${header.index}" class="mapping-select w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900">
                                ${selectOptions}
                            </select>
                        </td>
                        <td class="px-4 py-3 align-top">
                            ${renderCreateFieldConfig(header.index, createConfig)}
                        </td>
                    </tr>
                `;
            }).join('');

            document.querySelectorAll('.mapping-select').forEach(select => {
                select.addEventListener('change', handleMappingChange);
            });

            document.querySelectorAll('[data-create-field]').forEach(input => {
                input.addEventListener('input', syncCreateFieldConfig);
                input.addEventListener('change', syncCreateFieldConfig);
            });

            renderStageMapping();
            renderOwnerMapping();
        }

        function renderCreateFieldConfig(columnIndex, config) {
            if (!config) {
                return `<span class="text-xs text-gray-400">Not needed</span>`;
            }

            const tempKey = `column_${columnIndex}`;
            return `
                <div class="grid grid-cols-1 gap-2 min-w-[220px]">
                    <input data-create-field="${tempKey}" data-key="field_label" value="${escapeHtml(config.field_label || '')}" type="text" placeholder="Field label" class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900">
                    <div class="grid grid-cols-[1fr_auto] gap-2">
                        <select data-create-field="${tempKey}" data-key="field_type" class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900">
                            ${wizardContext.field_types.map(type => `<option value="${type}" ${type === (config.field_type || 'text') ? 'selected' : ''}>${type}</option>`).join('')}
                        </select>
                        <label class="inline-flex items-center gap-2 text-xs text-gray-600 rounded-lg border border-gray-300 px-3 py-2">
                            <input data-create-field="${tempKey}" data-key="is_required" type="checkbox" ${config.is_required ? 'checked' : ''}>
                            <span>Required</span>
                        </label>
                    </div>
                </div>
            `;
        }

        function handleMappingChange(event) {
            const columnIndex = event.target.dataset.columnIndex;
            const value = event.target.value;

            if (value === '__create_custom__') {
                const tempKey = `column_${columnIndex}`;
                state.mappings[columnIndex] = `create_custom:${tempKey}`;
                state.createCustomFields[tempKey] = state.createCustomFields[tempKey] || {
                    field_label: state.headers.find(item => String(item.index) === String(columnIndex))?.label || '',
                    field_type: 'text',
                    is_required: false,
                };
            } else if (value) {
                state.mappings[columnIndex] = value;
                delete state.createCustomFields[`column_${columnIndex}`];
            } else {
                delete state.mappings[columnIndex];
                delete state.createCustomFields[`column_${columnIndex}`];
            }

            renderMappingTable();
        }

        function syncCreateFieldConfig(event) {
            const tempKey = event.target.dataset.createField;
            const key = event.target.dataset.key;
            state.createCustomFields[tempKey] = state.createCustomFields[tempKey] || {
                field_label: '',
                field_type: 'text',
                is_required: false,
            };

            state.createCustomFields[tempKey][key] = event.target.type === 'checkbox'
                ? event.target.checked
                : event.target.value;
        }

        function renderProfilePicker() {
            const profiles = [...state.matchedProfiles, ...wizardContext.profiles.filter(profile => !state.matchedProfiles.find(match => match.id === profile.id))];
            if (!profiles.length) {
                savedProfileSelect.classList.add('hidden');
                applyProfileBtn.classList.add('hidden');
                return;
            }

            savedProfileSelect.innerHTML = `
                <option value="">Select saved profile</option>
                ${profiles.map(profile => `<option value="${profile.id}">${escapeHtml(profile.name)}</option>`).join('')}
            `;
            savedProfileSelect.classList.remove('hidden');
            applyProfileBtn.classList.remove('hidden');
        }

        function renderStageMapping() {
            const usesStatusMapping = Object.values(state.mappings).includes('lead:status');
            if (!usesStatusMapping) {
                stageMappingSection.classList.add('hidden');
                stageMappingRows.innerHTML = '';
                return;
            }

            const statusColumnIndex = Object.entries(state.mappings).find(([, target]) => target === 'lead:status')?.[0];
            const values = state.distinctValuesByColumn[statusColumnIndex] || [];

            stageMappingRows.innerHTML = values.map(value => `
                <label class="rounded-lg border border-amber-200 bg-white px-3 py-3">
                    <span class="block text-sm font-medium text-gray-800 mb-2">${escapeHtml(value)}</span>
                    <select data-stage-value="${escapeHtml(value)}" class="stage-map-select w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900">
                        <option value="">Select pipeline stage</option>
                        ${wizardContext.stage_bucket_options.map(option => `
                            <option value="${option.value}" ${state.stageMapping[value] === option.value ? 'selected' : ''}>${escapeHtml(option.label)}</option>
                        `).join('')}
                    </select>
                    <select data-lead-status-value="${escapeHtml(value)}" class="lead-status-map-select mt-3 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 ${state.stageMapping[value] === 'lead' ? '' : 'hidden'}">
                        <option value="">If Lead, choose status</option>
                        ${wizardContext.lead_bucket_status_options.map(option => `
                            <option value="${option.value}" ${state.leadStatusMapping[value] === option.value ? 'selected' : ''}>${escapeHtml(option.label)}</option>
                        `).join('')}
                    </select>
                </label>
            `).join('');

            document.querySelectorAll('.stage-map-select').forEach(select => {
                select.addEventListener('change', (event) => {
                    const value = event.target.dataset.stageValue;
                    if (event.target.value) {
                        state.stageMapping[value] = event.target.value;
                        if (event.target.value !== 'lead') {
                            delete state.leadStatusMapping[value];
                        } else if (!state.leadStatusMapping[value]) {
                            state.leadStatusMapping[value] = 'new';
                        }
                    } else {
                        delete state.stageMapping[value];
                        delete state.leadStatusMapping[value];
                    }

                    renderStageMapping();
                });
            });

            document.querySelectorAll('.lead-status-map-select').forEach(select => {
                select.addEventListener('change', (event) => {
                    const value = event.target.dataset.leadStatusValue;
                    if (event.target.value) {
                        state.leadStatusMapping[value] = event.target.value;
                    } else {
                        delete state.leadStatusMapping[value];
                    }
                });
            });

            stageMappingSection.classList.remove('hidden');
        }

        function renderOwnerMapping() {
            const usesOwnerMapping = Object.values(state.mappings).includes('lead:owner');
            if (!usesOwnerMapping) {
                ownerMappingSection.classList.add('hidden');
                ownerMappingRows.innerHTML = '';
                return;
            }

            const ownerColumnIndex = Object.entries(state.mappings).find(([, target]) => target === 'lead:owner')?.[0];
            const values = state.distinctValuesByColumn[ownerColumnIndex] || [];

            ownerMappingRows.innerHTML = values.map(value => `
                <label class="rounded-lg border border-sky-200 bg-white px-3 py-3">
                    <span class="block text-sm font-medium text-gray-800 mb-2">${escapeHtml(value)}</span>
                    <select data-owner-value="${escapeHtml(value)}" class="owner-map-select w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900">
                        <option value="">Select CRM user</option>
                        ${wizardContext.assignable_users.map(user => `
                            <option value="${user.id}" ${String(state.ownerMapping[value] || '') === String(user.id) ? 'selected' : ''}>
                                ${escapeHtml(user.name)}${user.role ? ` (${escapeHtml(user.role)})` : ''}
                            </option>
                        `).join('')}
                    </select>
                </label>
            `).join('');

            document.querySelectorAll('.owner-map-select').forEach(select => {
                select.addEventListener('change', (event) => {
                    const value = event.target.dataset.ownerValue;
                    if (event.target.value) {
                        state.ownerMapping[value] = event.target.value;
                    } else {
                        delete state.ownerMapping[value];
                    }
                });
            });

            ownerMappingSection.classList.remove('hidden');
        }

        function renderValidation(result) {
            validationSummary.classList.remove('hidden');

            const stats = [
                ['Total Rows', result.total_rows],
                ['Processed', result.processed_rows],
                ['Importable', result.imported],
                ['Failed', result.failed],
                ['Duplicate Skips', result.skipped_duplicates],
            ];

            validationStats.innerHTML = stats.map(([label, value]) => `
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">${escapeHtml(label)}</div>
                    <div class="mt-2 text-2xl font-bold text-slate-900">${value}</div>
                </div>
            `).join('');

            renderMessageBlock(validationWarnings, result.warnings, 'Warnings');
            renderMessageBlock(validationErrors, result.errors, 'Errors');

            validationPreviewBody.innerHTML = (result.preview || []).map(row => `
                <tr>
                    <td class="px-4 py-3 text-sm text-gray-700">${row.row_number}</td>
                    <td class="px-4 py-3 text-sm text-gray-900">${escapeHtml(row.name || '-')}</td>
                    <td class="px-4 py-3 text-sm text-gray-900">${escapeHtml(row.phone || '-')}</td>
                    <td class="px-4 py-3 text-sm text-gray-700">${escapeHtml(row.source || '-')}</td>
                    <td class="px-4 py-3 text-sm text-gray-700">${escapeHtml(row.status || 'new')}</td>
                    <td class="px-4 py-3 text-xs text-gray-600">${escapeHtml(formatMeta(row.meta || {}, row.assigned_to || null))}</td>
                </tr>
            `).join('');
        }

        function renderMessageBlock(element, rows, title) {
            if (!rows || !rows.length) {
                element.classList.add('hidden');
                element.innerHTML = '';
                return;
            }

            element.innerHTML = `
                <strong class="block mb-2">${title}</strong>
                <ul class="list-disc list-inside space-y-1">
                    ${rows.slice(0, 20).map(item => `<li>${escapeHtml(item)}</li>`).join('')}
                </ul>
            `;
            element.classList.remove('hidden');
        }

        function renderSavedProfilesList() {
            if (!wizardContext.profiles.length) {
                profileList.innerHTML = '<p>No saved profiles yet.</p>';
                return;
            }

            profileList.innerHTML = wizardContext.profiles.map(profile => `
                <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                    <div class="font-medium text-slate-800">${escapeHtml(profile.name)}</div>
                    <div class="text-xs text-slate-500 mt-1">${escapeHtml((profile.headers || []).map(item => item.label).join(', '))}</div>
                </div>
            `).join('');
        }

        function formatMeta(meta, assignedTo) {
            const entries = Object.entries(meta || {})
                .map(([key, value]) => `${key}: ${value}`)
            if (assignedTo) {
                entries.unshift(`assigned_to: ${assignedTo}`);
            }
            return entries.join(' | ');
        }

        function escapeHtml(value) {
            const div = document.createElement('div');
            div.textContent = value ?? '';
            return div.innerHTML;
        }
    </script>
    @endpush
@endsection

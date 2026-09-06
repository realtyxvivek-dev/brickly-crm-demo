@extends('layouts.app')

@section('title', 'Simple Import - ' . brand_name())
@section('page-title', 'Simple Import')

@section('content')
    <div class="max-w-6xl mx-auto space-y-6">
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
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">Simple Import</h2>
                    <p class="text-sm text-gray-500 mt-1">Excel ya CSV file upload karo, auto-detect preview dekho, lead stage aur owner map karo, phir bulk import run karo.</p>
                </div>
                <div class="flex gap-3">
                    <a href="{{ route('lead-import.index') }}" class="px-4 py-2 rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200 text-sm font-medium">Back</a>
                    <a href="{{ route('lead-import.simple.sample-download') }}" class="px-4 py-2 rounded-lg bg-emerald-50 text-emerald-800 hover:bg-emerald-100 text-sm font-medium">Download Sample</a>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">1. Upload File</h3>
            <div class="mb-4 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
                <strong class="font-semibold">Date format:</strong> `14-Nov-2025 10:30 AM`, `2025-11-14 10:30:00`, `14/11/2025 10:30 AM`, ya sirf `14-Nov-2025` bhi de sakte ho.
                Time optional hai. Agar time nahi hoga to system date ke saath default time use kar lega. `Created On` column import hone par lead ki real created date wahi save hogi, aaj ki date nahi.
            </div>
            <div id="simpleImportUploadArea" class="border-2 border-dashed border-emerald-300 rounded-xl p-8 text-center cursor-pointer hover:bg-emerald-50 transition-colors duration-200">
                <input type="file" id="simpleImportFile" accept=".xlsx,.xls,.csv,.txt" class="hidden">
                <svg class="w-12 h-12 mx-auto text-emerald-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                </svg>
                <p class="text-emerald-700 font-medium mb-2">Click to upload or drag and drop</p>
                <p class="text-gray-500 text-sm">Supported: .xlsx, .xls, .csv</p>
                <p id="simpleImportFileName" class="mt-4 text-gray-700 font-medium hidden"></p>
            </div>

            <div class="mt-5 flex flex-wrap items-center gap-3">
                <button type="button" id="simpleImportPreviewBtn" class="px-4 py-2 rounded-lg bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white hover:from-[#205A44] hover:to-[#15803d] text-sm font-medium">
                    Preview File
                </button>
                <span class="text-xs text-gray-500">Suggested columns: Created On, FirstName, Phone Number, Lead Source, Owner, Lead Stage</span>
            </div>
        </div>

        <div id="simpleImportPreviewSection" class="hidden bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-6">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">2. Preview & Mapping</h3>
                <p class="text-sm text-gray-500 mt-1">Detected columns check karo. `Owner` aur `Lead Stage` file ke values se automatically resolve honge.</p>
            </div>

            <div id="simpleImportSummary" class="grid grid-cols-2 lg:grid-cols-4 gap-3"></div>
            <div id="simpleImportWarnings" class="hidden rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"></div>

            <div class="rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                    <h4 class="text-sm font-semibold text-gray-800">Column Mapping</h4>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-white">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">File Column</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Sample</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Map To</th>
                            </tr>
                        </thead>
                        <tbody id="simpleImportMappingTableBody" class="divide-y divide-gray-200 bg-white"></tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                    <h4 class="text-sm font-semibold text-gray-800">Preview Rows</h4>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-white">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Row</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Name</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Phone</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Stage</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Owner</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Source</th>
                            </tr>
                        </thead>
                        <tbody id="simpleImportPreviewBody" class="divide-y divide-gray-200 bg-white"></tbody>
                    </table>
                </div>
            </div>

            <div id="simpleImportProgressCard" class="hidden rounded-2xl border border-emerald-200 bg-emerald-50/70 p-5">
                <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div>
                        <h4 class="text-base font-semibold text-emerald-950">Import Progress</h4>
                        <p id="simpleImportProgressMessage" class="mt-1 text-sm text-emerald-800">Import preparing...</p>
                    </div>
                    <div class="text-left md:text-right">
                        <div id="simpleImportProgressPercent" class="text-3xl font-bold text-emerald-900">0%</div>
                        <div id="simpleImportProgressRows" class="text-sm text-emerald-700">0 / 0 rows processed</div>
                    </div>
                </div>
                <div class="mt-4 h-3 overflow-hidden rounded-full bg-emerald-100">
                    <div id="simpleImportProgressBar" class="h-full w-0 rounded-full bg-gradient-to-r from-[#063A1C] to-[#16a34a] transition-all duration-300"></div>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <div class="rounded-xl bg-white px-4 py-3 shadow-sm">
                        <div id="simpleImportProcessedCount" class="text-2xl font-bold text-gray-900">0</div>
                        <div class="mt-1 text-xs font-semibold uppercase tracking-wide text-gray-500">Processed</div>
                    </div>
                    <div class="rounded-xl bg-white px-4 py-3 shadow-sm">
                        <div id="simpleImportImportedCount" class="text-2xl font-bold text-emerald-700">0</div>
                        <div class="mt-1 text-xs font-semibold uppercase tracking-wide text-gray-500">Imported</div>
                    </div>
                    <div class="rounded-xl bg-white px-4 py-3 shadow-sm">
                        <div id="simpleImportSkippedCount" class="text-2xl font-bold text-amber-600">0</div>
                        <div class="mt-1 text-xs font-semibold uppercase tracking-wide text-gray-500">Skipped</div>
                    </div>
                    <div class="rounded-xl bg-white px-4 py-3 shadow-sm">
                        <div id="simpleImportFailedCount" class="text-2xl font-bold text-rose-600">0</div>
                        <div class="mt-1 text-xs font-semibold uppercase tracking-wide text-gray-500">Failed</div>
                    </div>
                </div>
                <div id="simpleImportCompleteBox" class="hidden mt-4 rounded-xl border border-emerald-200 bg-white px-4 py-3 text-sm text-emerald-900"></div>
            </div>

            <div class="flex justify-end gap-3">
                <form id="simpleImportSubmitForm" method="POST" action="javascript:void(0)">
                    @csrf
                    <input type="hidden" name="file_token" id="simpleImportFileToken">
                    <input type="hidden" name="original_file_name" id="simpleImportOriginalFileName">
                    <input type="hidden" name="column_mapping" id="simpleImportColumnMapping">
                    <input type="hidden" name="duplicate_policy" value="skip">
                    <label class="mb-3 flex items-start gap-3 rounded-xl border border-emerald-100 bg-emerald-50/60 px-4 py-3 text-sm text-emerald-900">
                        <input
                            type="checkbox"
                            id="simpleImportCreateCallingTask"
                            class="mt-1 h-4 w-4 rounded border-emerald-300 text-emerald-700 focus:ring-emerald-500"
                            @checked($simpleImportContext['default_create_calling_task'] ?? false)
                        >
                        <span>
                            <span class="block font-semibold">Create calling task for assigned leads</span>
                            <span class="block text-xs text-emerald-800 mt-1">Agar import ke time owner resolve hoga, tabhi calling task create hogi.</span>
                        </span>
                    </label>
                    <button type="button" id="simpleImportSubmitBtn" class="px-5 py-2.5 rounded-lg bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white hover:from-[#205A44] hover:to-[#15803d] text-sm font-medium">
                        Import Leads
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div id="simpleImportReviewModal" class="fixed inset-0 z-[1100] hidden items-center justify-center bg-slate-950/60 px-4 py-6">
        <div class="flex max-h-[90vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="flex flex-col gap-3 border-b border-gray-200 px-5 py-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <h3 class="text-lg font-black text-gray-950">Import Review Required</h3>
                    <p id="simpleImportReviewSummary" class="mt-1 text-sm text-gray-600">Resolve import issues before continuing.</p>
                </div>
                <button type="button" id="simpleImportReviewClose" class="rounded-lg bg-gray-100 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-200">Close</button>
            </div>

            <div class="border-b border-gray-200 px-5 py-3">
                <div class="flex flex-wrap gap-2">
                    <button type="button" data-review-tab="owners" class="simple-import-review-tab rounded-lg px-3 py-2 text-sm font-bold">Owners</button>
                    <button type="button" data-review-tab="stages" class="simple-import-review-tab rounded-lg px-3 py-2 text-sm font-bold">Stages</button>
                    <button type="button" data-review-tab="rows" class="simple-import-review-tab rounded-lg px-3 py-2 text-sm font-bold">Rows</button>
                </div>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto p-5">
                <div id="simpleImportOwnersPanel" class="simple-import-review-panel space-y-4">
                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h4 class="text-base font-bold text-gray-900">Unknown Owners</h4>
                            <p class="text-sm text-gray-500">File owner value ko CRM user se map karo, ya selected value ko unassigned import karo.</p>
                        </div>
                        <button type="button" id="simpleImportAutoMatchOwners" class="rounded-lg bg-emerald-50 px-4 py-2 text-sm font-bold text-emerald-800 hover:bg-emerald-100">Auto Match</button>
                    </div>
                    <div id="simpleImportOwnerMappingRows" class="grid grid-cols-1 gap-3 md:grid-cols-2"></div>
                </div>

                <div id="simpleImportStagesPanel" class="simple-import-review-panel hidden space-y-4">
                    <div>
                        <h4 class="text-base font-bold text-gray-900">Unknown Stages</h4>
                        <p class="text-sm text-gray-500">Unsupported stage value ko supported import bucket se map karo.</p>
                    </div>
                    <div id="simpleImportStageMappingRows" class="grid grid-cols-1 gap-3 md:grid-cols-2"></div>
                </div>

                <div id="simpleImportRowsPanel" class="simple-import-review-panel hidden space-y-4">
                    <div>
                        <h4 class="text-base font-bold text-gray-900">Row Editor</h4>
                        <p class="text-sm text-gray-500">Preview rows me exception values edit karo. Bulk owner/stage mapping primary flow hai.</p>
                    </div>
                    <div class="overflow-x-auto rounded-xl border border-gray-200">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-3 text-left text-xs font-bold uppercase text-gray-500">Row</th>
                                    <th class="px-3 py-3 text-left text-xs font-bold uppercase text-gray-500">Name</th>
                                    <th class="px-3 py-3 text-left text-xs font-bold uppercase text-gray-500">Phone</th>
                                    <th class="px-3 py-3 text-left text-xs font-bold uppercase text-gray-500">Owner</th>
                                    <th class="px-3 py-3 text-left text-xs font-bold uppercase text-gray-500">Stage</th>
                                    <th class="px-3 py-3 text-left text-xs font-bold uppercase text-gray-500">Source</th>
                                </tr>
                            </thead>
                            <tbody id="simpleImportReviewRowsBody" class="divide-y divide-gray-200 bg-white"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-3 border-t border-gray-200 bg-gray-50 px-5 py-4 md:flex-row md:items-center md:justify-between">
                <div id="simpleImportReviewStatus" class="text-sm font-semibold text-amber-700"></div>
                <div class="flex flex-wrap justify-end gap-2">
                    <button type="button" id="simpleImportValidateAgain" class="rounded-lg bg-white px-4 py-2 text-sm font-bold text-gray-700 ring-1 ring-gray-200 hover:bg-gray-100">Validate Again</button>
                    <button type="button" id="simpleImportSaveReview" class="rounded-lg bg-emerald-50 px-4 py-2 text-sm font-bold text-emerald-800 hover:bg-emerald-100">Save Mapping</button>
                    <button type="button" id="simpleImportModalImportBtn" class="rounded-lg bg-gradient-to-r from-[#063A1C] to-[#205A44] px-5 py-2 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-50">Import Leads</button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        const simpleImportContext = @json($simpleImportContext);
        const simpleImportState = {
            fileToken: '',
            originalFileName: '',
            headers: [],
            preview: [],
            distinctValuesByColumn: {},
            detectedColumns: {},
            columnMapping: {},
            unknownStageValues: [],
            unknownOwnerValues: [],
            ownerMapping: {},
            stageMapping: {},
            rowOverrides: {},
            activeReviewTab: 'owners',
        };

        const uploadArea = document.getElementById('simpleImportUploadArea');
        const fileInput = document.getElementById('simpleImportFile');
        const fileName = document.getElementById('simpleImportFileName');
        const previewBtn = document.getElementById('simpleImportPreviewBtn');
        const previewSection = document.getElementById('simpleImportPreviewSection');
        const summaryBox = document.getElementById('simpleImportSummary');
        const warningsBox = document.getElementById('simpleImportWarnings');
        const mappingTableBody = document.getElementById('simpleImportMappingTableBody');
        const previewBody = document.getElementById('simpleImportPreviewBody');
        const submitForm = document.getElementById('simpleImportSubmitForm');
        const submitBtn = document.getElementById('simpleImportSubmitBtn');
        const createCallingTaskCheckbox = document.getElementById('simpleImportCreateCallingTask');
        const progressCard = document.getElementById('simpleImportProgressCard');
        const progressBar = document.getElementById('simpleImportProgressBar');
        const progressPercent = document.getElementById('simpleImportProgressPercent');
        const progressRows = document.getElementById('simpleImportProgressRows');
        const progressMessage = document.getElementById('simpleImportProgressMessage');
        const processedCount = document.getElementById('simpleImportProcessedCount');
        const importedCount = document.getElementById('simpleImportImportedCount');
        const skippedCount = document.getElementById('simpleImportSkippedCount');
        const failedCount = document.getElementById('simpleImportFailedCount');
        const completeBox = document.getElementById('simpleImportCompleteBox');
        const reviewModal = document.getElementById('simpleImportReviewModal');
        const reviewSummary = document.getElementById('simpleImportReviewSummary');
        const reviewClose = document.getElementById('simpleImportReviewClose');
        const reviewStatus = document.getElementById('simpleImportReviewStatus');
        const ownerMappingRows = document.getElementById('simpleImportOwnerMappingRows');
        const stageMappingRows = document.getElementById('simpleImportStageMappingRows');
        const reviewRowsBody = document.getElementById('simpleImportReviewRowsBody');
        const autoMatchOwnersBtn = document.getElementById('simpleImportAutoMatchOwners');
        const saveReviewBtn = document.getElementById('simpleImportSaveReview');
        const validateAgainBtn = document.getElementById('simpleImportValidateAgain');
        const modalImportBtn = document.getElementById('simpleImportModalImportBtn');

        function escapeHtml(value) {
            const div = document.createElement('div');
            div.textContent = value ?? '';
            return div.innerHTML;
        }

        function setSelectedFile(file) {
            if (!file) return;
            fileName.textContent = file.name;
            fileName.classList.remove('hidden');
        }

        function getFieldOptionsMarkup(selectedValue) {
            return (simpleImportContext.field_options || []).map((option) => `
                <option value="${escapeHtml(option.value)}" ${selectedValue === option.value ? 'selected' : ''}>${escapeHtml(option.label)}</option>
            `).join('');
        }

        function getSelectedColumnIndex(targetField) {
            const entry = Object.entries(simpleImportState.columnMapping).find(([, value]) => value === targetField);
            return entry ? entry[0] : null;
        }

        function renderSummary(data) {
            const cards = [
                ['Total Rows', data.total_rows || 0],
                ['Duplicates In File', (data.duplicate_phones_in_file || []).length],
                ['Duplicates In CRM', (data.duplicate_phones_in_crm || []).length],
                ['Missing Phone', (data.missing_phone_rows || []).length],
            ];

            summaryBox.innerHTML = cards.map(([label, value]) => `
                <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                    <div class="text-2xl font-bold text-gray-900">${value}</div>
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 mt-1">${escapeHtml(label)}</div>
                </div>
            `).join('');
        }

        function renderWarnings(data) {
            const warnings = [];
            if ((data.missing_name_rows || []).length) {
                warnings.push(`${data.missing_name_rows.length} row(s) me name missing hai.`);
            }
            if ((data.missing_phone_rows || []).length) {
                warnings.push(`${data.missing_phone_rows.length} row(s) me phone missing hai.`);
            }
            if ((data.duplicate_phones_in_file || []).length) {
                warnings.push(`${data.duplicate_phones_in_file.length} duplicate phone value file ke andar mile.`);
            }
            if ((data.duplicate_phones_in_crm || []).length) {
                warnings.push(`${data.duplicate_phones_in_crm.length} phone number already CRM me exist karte hain aur default me skip honge.`);
            }
            if ((data.unknown_stage_values || []).length) {
                warnings.push(`In lead stage values ko system auto samajh nahi paaya: ${data.unknown_stage_values.join(', ')}.`);
            }
            if ((data.unknown_owner_values || []).length) {
                warnings.push(`In owner values ka CRM user match nahi mila: ${data.unknown_owner_values.join(', ')}.`);
            }

            if (!warnings.length) {
                warningsBox.classList.add('hidden');
                warningsBox.innerHTML = '';
                return;
            }

            warningsBox.innerHTML = `
                <strong class="block mb-1">Import Warnings</strong>
                <ul class="list-disc list-inside space-y-1">
                    ${warnings.map((item) => `<li>${escapeHtml(item)}</li>`).join('')}
                </ul>
            `;
            warningsBox.classList.remove('hidden');
        }

        function renderMappingTable() {
            mappingTableBody.innerHTML = (simpleImportState.headers || []).map((header) => {
                const selectedValue = simpleImportState.columnMapping[String(header.index)] || 'skip';
                return `
                    <tr>
                        <td class="px-4 py-3 text-sm font-medium text-gray-900">${escapeHtml(header.label)}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">${escapeHtml(header.sample || '—')}</td>
                        <td class="px-4 py-3">
                            <select data-column-index="${header.index}" class="simple-import-mapping-select w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900">
                                ${getFieldOptionsMarkup(selectedValue)}
                            </select>
                        </td>
                    </tr>
                `;
            }).join('');

            document.querySelectorAll('.simple-import-mapping-select').forEach((select) => {
                select.addEventListener('change', function () {
                    simpleImportState.columnMapping[this.dataset.columnIndex] = this.value;
                    ensureUniqueFieldSelections(this.dataset.columnIndex, this.value);
                    renderMappingTable();
                });
            });
        }

        function ensureUniqueFieldSelections(currentIndex, currentValue) {
            if (!currentValue || currentValue === 'skip') return;

            Object.keys(simpleImportState.columnMapping).forEach((columnIndex) => {
                if (String(columnIndex) !== String(currentIndex) && simpleImportState.columnMapping[columnIndex] === currentValue) {
                    simpleImportState.columnMapping[columnIndex] = 'skip';
                }
            });
        }

        function renderPreviewRows() {
            previewBody.innerHTML = (simpleImportState.preview || []).map((row) => `
                <tr>
                    <td class="px-4 py-3 text-sm text-gray-600">${row.row_number}</td>
                    <td class="px-4 py-3 text-sm text-gray-900">${escapeHtml(row.name || '')}</td>
                    <td class="px-4 py-3 text-sm text-gray-900">${escapeHtml(row.phone || '')}</td>
                    <td class="px-4 py-3 text-sm text-gray-900">${escapeHtml(row.lead_stage || '')}</td>
                    <td class="px-4 py-3 text-sm text-gray-900">${escapeHtml(row.owner || '')}</td>
                    <td class="px-4 py-3 text-sm text-gray-900">${escapeHtml(row.source || '')}</td>
                </tr>
            `).join('');
        }

        function normalizeImportLookup(value) {
            return String(value || '').trim().toLowerCase();
        }

        function currentRowValue(row, field) {
            return simpleImportState.rowOverrides?.[row.row_number]?.[field] ?? row[field] ?? '';
        }

        function getUnresolvedOwnerValues() {
            return (simpleImportState.unknownOwnerValues || []).filter((value) => {
                const key = normalizeImportLookup(value);
                return !Object.prototype.hasOwnProperty.call(simpleImportState.ownerMapping, key);
            });
        }

        function getUnresolvedStageValues() {
            return (simpleImportState.unknownStageValues || []).filter((value) => {
                const key = normalizeImportLookup(value);
                return !Object.prototype.hasOwnProperty.call(simpleImportState.stageMapping, key);
            });
        }

        function getReviewIssues() {
            const issues = [];
            if (!simpleImportState.fileToken) {
                issues.push('Please preview the file first.');
            }

            if (getSelectedColumnIndex('name') === null) {
                issues.push('Lead name column mapping is required.');
            }

            if (getSelectedColumnIndex('phone') === null) {
                issues.push('Phone number column mapping is required.');
            }

            const unresolvedStages = getUnresolvedStageValues();
            if (unresolvedStages.length) {
                issues.push(`Unknown lead stage values present hain: ${unresolvedStages.join(', ')}.`);
            }

            const unresolvedOwners = getUnresolvedOwnerValues();
            if (unresolvedOwners.length) {
                issues.push(`Unknown owner values present hain: ${unresolvedOwners.join(', ')}.`);
            }

            return issues;
        }

        function validateBeforeSubmit() {
            return getReviewIssues()[0] || '';
        }

        function userOptionsMarkup(selectedValue) {
            const options = ['<option value="">Leave Unassigned</option>'];
            (simpleImportContext.users || []).forEach((user) => {
                options.push(`<option value="${user.id}" ${String(selectedValue || '') === String(user.id) ? 'selected' : ''}>${escapeHtml(user.name)}${user.role ? ' (' + escapeHtml(user.role) + ')' : ''}</option>`);
            });
            return options.join('');
        }

        function stageOptionsMarkup(selectedValue) {
            return ['<option value="">Select stage</option>'].concat((simpleImportContext.stage_bucket_options || []).map((option) => (
                `<option value="${escapeHtml(option.value)}" ${String(selectedValue || '') === String(option.value) ? 'selected' : ''}>${escapeHtml(option.label)}</option>`
            ))).join('');
        }

        function setReviewTab(tab) {
            simpleImportState.activeReviewTab = tab;
            document.querySelectorAll('.simple-import-review-tab').forEach((button) => {
                const active = button.dataset.reviewTab === tab;
                button.classList.toggle('bg-emerald-700', active);
                button.classList.toggle('text-white', active);
                button.classList.toggle('bg-gray-100', !active);
                button.classList.toggle('text-gray-700', !active);
            });
            document.querySelectorAll('.simple-import-review-panel').forEach((panel) => panel.classList.add('hidden'));
            document.getElementById(`simpleImport${tab.charAt(0).toUpperCase() + tab.slice(1)}Panel`)?.classList.remove('hidden');
        }

        function updateReviewStatus() {
            const unresolvedOwners = getUnresolvedOwnerValues();
            const unresolvedStages = getUnresolvedStageValues();
            const issues = getReviewIssues();
            if (reviewSummary) {
                reviewSummary.textContent = `${unresolvedOwners.length} owner issue(s), ${unresolvedStages.length} stage issue(s), ${Object.keys(simpleImportState.rowOverrides || {}).length} edited row(s).`;
            }
            if (reviewStatus) {
                reviewStatus.textContent = issues.length
                    ? `${issues.length} blocker(s) pending. Save mapping or mark owner as unassigned.`
                    : 'All blockers resolved. Import can continue.';
                reviewStatus.classList.toggle('text-emerald-700', issues.length === 0);
                reviewStatus.classList.toggle('text-amber-700', issues.length > 0);
            }
            if (modalImportBtn) {
                modalImportBtn.disabled = issues.length > 0;
            }
        }

        function collectReviewFormMappings() {
            document.querySelectorAll('.simple-import-owner-map').forEach((select) => {
                simpleImportState.ownerMapping[normalizeImportLookup(select.dataset.ownerValue)] = select.value || '';
            });

            document.querySelectorAll('.simple-import-stage-map').forEach((select) => {
                const key = normalizeImportLookup(select.dataset.stageValue);
                if (select.value) {
                    simpleImportState.stageMapping[key] = select.value;
                } else {
                    delete simpleImportState.stageMapping[key];
                }
            });

            document.querySelectorAll('.simple-import-row-override').forEach((input) => {
                const rowNumber = input.dataset.rowNumber;
                const field = input.dataset.rowField;
                simpleImportState.rowOverrides[rowNumber] = simpleImportState.rowOverrides[rowNumber] || {};
                simpleImportState.rowOverrides[rowNumber][field] = input.value;
            });
        }

        function renderOwnerMappingRows() {
            const values = simpleImportState.unknownOwnerValues || [];
            ownerMappingRows.innerHTML = values.length ? values.map((value) => {
                const key = normalizeImportLookup(value);
                return `
                    <label class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                        <span class="mb-2 block text-xs font-bold uppercase tracking-wide text-gray-500">Owner in file</span>
                        <span class="mb-3 block text-sm font-black text-gray-950">${escapeHtml(value)}</span>
                        <select data-owner-value="${escapeHtml(value)}" class="simple-import-owner-map w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900">
                            ${userOptionsMarkup(simpleImportState.ownerMapping[key])}
                        </select>
                    </label>
                `;
            }).join('') : '<div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">No unknown owner values.</div>';

            document.querySelectorAll('.simple-import-owner-map').forEach((select) => {
                select.addEventListener('change', function () {
                    simpleImportState.ownerMapping[normalizeImportLookup(this.dataset.ownerValue)] = this.value || '';
                    updateReviewStatus();
                });
            });
        }

        function renderStageMappingRows() {
            const values = simpleImportState.unknownStageValues || [];
            stageMappingRows.innerHTML = values.length ? values.map((value) => {
                const key = normalizeImportLookup(value);
                return `
                    <label class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                        <span class="mb-2 block text-xs font-bold uppercase tracking-wide text-gray-500">Stage in file</span>
                        <span class="mb-3 block text-sm font-black text-gray-950">${escapeHtml(value)}</span>
                        <select data-stage-value="${escapeHtml(value)}" class="simple-import-stage-map w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900">
                            ${stageOptionsMarkup(simpleImportState.stageMapping[key])}
                        </select>
                    </label>
                `;
            }).join('') : '<div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">No unknown stage values.</div>';

            document.querySelectorAll('.simple-import-stage-map').forEach((select) => {
                select.addEventListener('change', function () {
                    const key = normalizeImportLookup(this.dataset.stageValue);
                    if (this.value) {
                        simpleImportState.stageMapping[key] = this.value;
                    } else {
                        delete simpleImportState.stageMapping[key];
                    }
                    updateReviewStatus();
                });
            });
        }

        function renderReviewRows() {
            reviewRowsBody.innerHTML = (simpleImportState.preview || []).map((row) => {
                const rowNumber = row.row_number;
                return `
                    <tr>
                        <td class="px-3 py-3 text-sm font-semibold text-gray-600">${rowNumber}</td>
                        ${['name', 'phone', 'owner', 'lead_stage', 'source'].map((field) => `
                            <td class="px-3 py-3">
                                <input data-row-number="${rowNumber}" data-row-field="${field}" value="${escapeHtml(currentRowValue(row, field))}" class="simple-import-row-override w-40 rounded-lg border border-gray-300 px-2 py-1.5 text-sm text-gray-900">
                            </td>
                        `).join('')}
                    </tr>
                `;
            }).join('');

            document.querySelectorAll('.simple-import-row-override').forEach((input) => {
                input.addEventListener('input', function () {
                    const rowNumber = this.dataset.rowNumber;
                    const field = this.dataset.rowField;
                    simpleImportState.rowOverrides[rowNumber] = simpleImportState.rowOverrides[rowNumber] || {};
                    simpleImportState.rowOverrides[rowNumber][field] = this.value;
                    updateReviewStatus();
                });
            });
        }

        function openReviewModal(preferredTab = 'owners') {
            renderOwnerMappingRows();
            renderStageMappingRows();
            renderReviewRows();
            setReviewTab(preferredTab);
            updateReviewStatus();
            reviewModal?.classList.remove('hidden');
            reviewModal?.classList.add('flex');
        }

        function closeReviewModal() {
            reviewModal?.classList.add('hidden');
            reviewModal?.classList.remove('flex');
        }

        function autoMatchOwners() {
            const users = simpleImportContext.users || [];
            (simpleImportState.unknownOwnerValues || []).forEach((value) => {
                const normalized = normalizeImportLookup(value);
                const compact = normalized.replace(/[^a-z0-9]/g, '');
                const match = users.find((user) => {
                    const userName = normalizeImportLookup(user.name);
                    const userCompact = userName.replace(/[^a-z0-9]/g, '');
                    return userName === normalized || userCompact === compact || userName.includes(normalized) || normalized.includes(userName);
                });
                if (match) {
                    simpleImportState.ownerMapping[normalized] = String(match.id);
                }
            });
            renderOwnerMappingRows();
            updateReviewStatus();
        }

        function renderProgress(data) {
            const progress = data.progress || {};
            const percentage = Number(progress.percentage || 0);
            const processed = Number(progress.processed_rows || 0);
            const total = Number(progress.total_rows || 0);
            const imported = Number(progress.imported || 0);
            const skipped = Number(progress.skipped_duplicates || 0);
            const failed = Number(progress.failed || 0);

            progressCard.classList.remove('hidden');
            progressBar.style.width = `${Math.max(0, Math.min(100, percentage))}%`;
            progressPercent.textContent = `${percentage}%`;
            progressRows.textContent = `${processed} / ${total} rows processed`;
            progressMessage.textContent = data.message || 'Import in progress';
            processedCount.textContent = processed;
            importedCount.textContent = imported;
            skippedCount.textContent = skipped;
            failedCount.textContent = failed;

            if (data.completed) {
                completeBox.innerHTML = `
                    <strong class="font-semibold">Import complete.</strong>
                    ${imported} leads imported, ${skipped} duplicate skipped, ${failed} failed.
                `;
                completeBox.classList.remove('hidden');
            } else {
                completeBox.classList.add('hidden');
                completeBox.innerHTML = '';
            }
        }

        async function startAndProcessImport() {
            const startFormData = new FormData();
            startFormData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
            startFormData.append('file_token', simpleImportState.fileToken);
            startFormData.append('original_file_name', simpleImportState.originalFileName);
            startFormData.append('column_mapping', JSON.stringify(simpleImportState.columnMapping));
            startFormData.append('owner_mapping', JSON.stringify(simpleImportState.ownerMapping || {}));
            startFormData.append('stage_mapping', JSON.stringify(simpleImportState.stageMapping || {}));
            startFormData.append('row_overrides', JSON.stringify(simpleImportState.rowOverrides || {}));
            startFormData.append('duplicate_policy', 'skip');
            startFormData.append('create_calling_task', createCallingTaskCheckbox?.checked ? '1' : '0');

            const startResponse = await fetch('{{ route('lead-import.simple.start') }}', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: startFormData,
            });
            const startRaw = await startResponse.text();
            let startPayload = {};

            try {
                startPayload = JSON.parse(startRaw);
            } catch (error) {
                throw new Error('Import start response JSON nahi tha. Page ko refresh karke dubara try karo.');
            }

            if (!startResponse.ok || !startPayload.success) {
                throw new Error(startPayload.message || 'Unable to start import.');
            }

            let current = startPayload.data || {};
            renderProgress(current);

            const batchId = current.batch?.id;
            if (!batchId) {
                throw new Error('Import batch id missing.');
            }

            while (!current.completed) {
                const processFormData = new FormData();
                processFormData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
                processFormData.append('batch_id', String(batchId));

                const processResponse = await fetch('{{ route('lead-import.simple.process') }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: processFormData,
                });
                const processRaw = await processResponse.text();
                let processPayload = {};

                try {
                    processPayload = JSON.parse(processRaw);
                } catch (error) {
                    throw new Error('Import process response JSON nahi tha. Chunk timeout ya server error aaya hai.');
                }

                if (!processResponse.ok || !processPayload.success) {
                    throw new Error(processPayload.message || 'Import processing failed.');
                }

                current = processPayload.data || {};
                renderProgress(current);
            }
        }

        async function previewImportFile() {
            if (!fileInput.files.length) {
                alert('Please select a file first.');
                return;
            }

            const formData = new FormData();
            formData.append('import_file', fileInput.files[0]);
            formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);

            previewBtn.disabled = true;
            previewBtn.textContent = 'Previewing...';

            try {
                const response = await fetch('{{ route('lead-import.simple.preview') }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: formData,
                });
                const raw = await response.text();
                let payload = {};

                try {
                    payload = JSON.parse(raw);
                } catch (error) {
                    throw new Error('Preview response JSON nahi tha. Session expire ya server validation issue ho sakta hai. Page refresh karke dubara try karo.');
                }

                if (!response.ok || !payload.success) {
                    throw new Error(payload.message || 'Unable to preview file.');
                }

                const data = payload.data || {};
                simpleImportState.fileToken = data.file_token || '';
                simpleImportState.originalFileName = data.original_file_name || '';
                simpleImportState.headers = data.headers || [];
                simpleImportState.preview = data.preview || [];
                simpleImportState.distinctValuesByColumn = data.distinct_values_by_column || {};
                simpleImportState.detectedColumns = data.detected_columns || {};
                simpleImportState.unknownStageValues = data.unknown_stage_values || [];
                simpleImportState.unknownOwnerValues = data.unknown_owner_values || [];
                simpleImportState.ownerMapping = {};
                simpleImportState.stageMapping = {};
                simpleImportState.rowOverrides = {};
                simpleImportState.columnMapping = {};

                Object.entries(simpleImportState.detectedColumns).forEach(([field, columnIndex]) => {
                    simpleImportState.columnMapping[String(columnIndex)] = field;
                });

                renderSummary(data);
                renderWarnings(data);
                renderMappingTable();
                renderPreviewRows();
                previewSection.classList.remove('hidden');
            } catch (error) {
                alert(error.message || 'Preview failed.');
            } finally {
                previewBtn.disabled = false;
                previewBtn.textContent = 'Preview File';
            }
        }

        uploadArea.addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', () => setSelectedFile(fileInput.files[0]));

        uploadArea.addEventListener('dragover', (event) => {
            event.preventDefault();
            uploadArea.classList.add('bg-emerald-50');
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('bg-emerald-50');
        });

        uploadArea.addEventListener('drop', (event) => {
            event.preventDefault();
            uploadArea.classList.remove('bg-emerald-50');
            if (event.dataTransfer.files.length > 0) {
                fileInput.files = event.dataTransfer.files;
                setSelectedFile(event.dataTransfer.files[0]);
            }
        });

        previewBtn.addEventListener('click', previewImportFile);

        document.querySelectorAll('.simple-import-review-tab').forEach((button) => {
            button.addEventListener('click', () => setReviewTab(button.dataset.reviewTab));
        });

        reviewClose?.addEventListener('click', closeReviewModal);
        autoMatchOwnersBtn?.addEventListener('click', autoMatchOwners);
        saveReviewBtn?.addEventListener('click', () => {
            collectReviewFormMappings();
            renderOwnerMappingRows();
            renderStageMappingRows();
            renderReviewRows();
            updateReviewStatus();
        });
        validateAgainBtn?.addEventListener('click', () => {
            collectReviewFormMappings();
            updateReviewStatus();
            const unresolvedOwners = getUnresolvedOwnerValues();
            const unresolvedStages = getUnresolvedStageValues();
            if (unresolvedOwners.length) {
                setReviewTab('owners');
            } else if (unresolvedStages.length) {
                setReviewTab('stages');
            }
        });
        modalImportBtn?.addEventListener('click', () => {
            collectReviewFormMappings();
            if (getReviewIssues().length) {
                updateReviewStatus();
                return;
            }
            closeReviewModal();
            handleImportSubmit(true);
        });

        async function handleImportSubmit(skipReview = false) {
            const issues = getReviewIssues();
            if (issues.length && !skipReview) {
                openReviewModal(getUnresolvedOwnerValues().length ? 'owners' : (getUnresolvedStageValues().length ? 'stages' : 'rows'));
                return;
            }

            if (issues.length) {
                openReviewModal(getUnresolvedOwnerValues().length ? 'owners' : (getUnresolvedStageValues().length ? 'stages' : 'rows'));
                return;
            }

            document.getElementById('simpleImportFileToken').value = simpleImportState.fileToken;
            document.getElementById('simpleImportOriginalFileName').value = simpleImportState.originalFileName;
            document.getElementById('simpleImportColumnMapping').value = JSON.stringify(simpleImportState.columnMapping);

            submitBtn.disabled = true;
            submitBtn.textContent = 'Importing...';
            previewBtn.disabled = true;

            startAndProcessImport()
                .catch((error) => {
                    alert(error.message || 'Import failed.');
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Import Leads';
                    previewBtn.disabled = false;
                });
        }

        submitBtn.addEventListener('click', handleImportSubmit);

        submitForm.addEventListener('submit', (event) => {
            event.preventDefault();
            handleImportSubmit();
        });
    </script>
    @endpush
@endsection

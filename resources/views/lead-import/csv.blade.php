@extends('layouts.app')

@section('title', 'Import CSV - ' . brand_name())
@section('page-title', 'Import CSV')

@section('content')
    <div class="max-w-4xl mx-auto">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            @if($errors->any())
                <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form id="importForm" method="POST" action="{{ route('lead-import.csv.import') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="import_mode" id="importModeInput" value="all">

                <!-- File Upload -->
                <div class="mb-8">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-200">1. Upload CSV File</h3>
                    
                    <div class="border-2 border-dashed border-indigo-300 rounded-xl p-8 text-center cursor-pointer hover:bg-indigo-50 transition-colors duration-200" 
                         id="fileUploadArea">
                        <input type="file" name="csv_file" id="csvFile" accept=".csv,.txt" required class="hidden">
                        <svg class="w-12 h-12 mx-auto text-indigo-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                        </svg>
                        <p class="text-indigo-600 font-medium mb-2">Click to upload or drag and drop</p>
                        <p class="text-gray-500 text-sm">CSV file with name/full name and phone/mobile columns</p>
                        <p id="fileName" class="mt-4 text-gray-700 font-medium hidden"></p>
                    </div>
                </div>

                <!-- Preview -->
                <div class="mb-8">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-200">2. Preview (Optional)</h3>
                    <button type="button" id="previewBtn" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors duration-200 mb-4">
                        Preview CSV
                    </button>
                    <div id="previewSection" class="hidden bg-gray-50 rounded-lg p-4">
                        <h4 class="font-semibold text-gray-800 mb-3">Preview (First 10 rows)</h4>
                        <div id="detectedColumnsSummary" class="hidden mb-4 p-4 bg-white rounded-lg border border-gray-200"></div>
                        <div id="stageFilterSection" class="hidden mb-4 p-4 bg-white rounded-lg border border-gray-200">
                            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
                                <div>
                                    <h5 class="font-semibold text-gray-800">Lead Stage Filter</h5>
                                    <p class="text-xs text-gray-500">Import se pehle choose karo kaunse stage ki leads leni hain.</p>
                                </div>
                                <div class="flex gap-2">
                                    <label class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-200 bg-gray-50 text-sm text-gray-700">
                                        <input type="radio" name="stage_filter_mode" value="include" checked>
                                        <span>Include selected stages</span>
                                    </label>
                                    <label class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-200 bg-gray-50 text-sm text-gray-700">
                                        <input type="radio" name="stage_filter_mode" value="exclude">
                                        <span>Exclude selected stages</span>
                                    </label>
                                </div>
                            </div>
                            <div id="stageSummaryBadges" class="flex flex-wrap gap-2 mb-4"></div>
                            <div id="stageCheckboxes" class="grid grid-cols-1 sm:grid-cols-2 gap-2"></div>
                        </div>
                        <div id="previewWarnings" class="hidden mb-4 p-4 rounded-lg border border-amber-200 bg-amber-50 text-amber-800 text-sm"></div>
                        <div id="previewContent"></div>
                    </div>
                </div>

                <!-- Import Notes -->
                <div class="mb-8">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-200">3. Import Notes</h3>
                    <div class="rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                        Imported lead me old CRM ka source, remarks, lead stage, score, owner, created on aur alternate phone sab `notes` me preserve honge.
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex justify-end gap-3">
                    <a href="{{ route('lead-import.index') }}" 
                       class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors duration-200">
                        Cancel
                    </a>
                    <button type="button" id="demoImportBtn"
                            class="px-4 py-2 bg-white text-[#063A1C] border border-[#205A44] rounded-lg hover:bg-emerald-50 transition-colors duration-200">
                        Import 1 Demo Lead
                    </button>
                    <button type="submit" id="fullImportBtn"
                            class="px-4 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg hover:from-[#205A44] hover:to-[#15803d] transition-colors duration-200">
                        Import All Filtered Leads
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        const fileUploadArea = document.getElementById('fileUploadArea');
        const csvFile = document.getElementById('csvFile');
        const fileName = document.getElementById('fileName');
        const previewBtn = document.getElementById('previewBtn');
        const previewSection = document.getElementById('previewSection');
        const previewContent = document.getElementById('previewContent');
        const detectedColumnsSummary = document.getElementById('detectedColumnsSummary');
        const stageFilterSection = document.getElementById('stageFilterSection');
        const stageSummaryBadges = document.getElementById('stageSummaryBadges');
        const stageCheckboxes = document.getElementById('stageCheckboxes');
        const previewWarnings = document.getElementById('previewWarnings');
        const importModeInput = document.getElementById('importModeInput');
        const demoImportBtn = document.getElementById('demoImportBtn');
        const importForm = document.getElementById('importForm');

        function escapeHtml(value) {
            const div = document.createElement('div');
            div.textContent = value ?? '';
            return div.innerHTML;
        }

        function renderDetectedColumns(columns) {
            const entries = Object.entries(columns || {}).filter(([, value]) => value);
            if (!entries.length) {
                detectedColumnsSummary.classList.add('hidden');
                detectedColumnsSummary.innerHTML = '';
                return;
            }

            detectedColumnsSummary.innerHTML = `
                <h5 class="font-semibold text-gray-800 mb-2">Detected Column Mapping</h5>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm text-gray-700">
                    ${entries.map(([key, value]) => `
                        <div class="rounded-lg bg-gray-50 border border-gray-200 px-3 py-2">
                            <strong class="text-gray-900">${escapeHtml(key.replace(/_/g, ' '))}</strong>
                            <div>${escapeHtml(value)}</div>
                        </div>
                    `).join('')}
                </div>
            `;
            detectedColumnsSummary.classList.remove('hidden');
        }

        function renderStageSummary(stageSummary, hasStageColumn) {
            if (!hasStageColumn || !stageSummary || Object.keys(stageSummary).length === 0) {
                stageFilterSection.classList.add('hidden');
                stageSummaryBadges.innerHTML = '';
                stageCheckboxes.innerHTML = '';
                return;
            }

            const stageEntries = Object.entries(stageSummary).sort((a, b) => b[1] - a[1]);
            stageSummaryBadges.innerHTML = stageEntries.map(([stage, count]) => `
                <span class="inline-flex items-center gap-2 rounded-full bg-slate-100 text-slate-700 px-3 py-1 text-xs font-semibold border border-slate-200">
                    <span>${escapeHtml(stage)}</span>
                    <span>${count}</span>
                </span>
            `).join('');

            stageCheckboxes.innerHTML = stageEntries.map(([stage, count], index) => {
                const stageValue = stage === '(Blank Stage)' ? '__blank__' : stage;
                return `
                <label class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                    <span class="inline-flex items-center gap-2 min-w-0">
                        <input type="checkbox" name="selected_stages[]" value="${escapeHtml(stageValue)}" ${index < 3 ? 'checked' : ''}>
                        <span class="truncate">${escapeHtml(stage)}</span>
                    </span>
                    <span class="text-xs font-semibold text-gray-500">${count}</span>
                </label>
            `}).join('');

            stageFilterSection.classList.remove('hidden');
        }

        function renderWarnings(data) {
            const warnings = [];
            if ((data.duplicate_phones_in_file || []).length > 0) {
                warnings.push(`${data.duplicate_phones_in_file.length} duplicate phone values file ke andar mile.`);
            }
            if (data.has_stage_column) {
                warnings.push('Stage filter sirf selected stages par import ko limit karega.');
            }

            if (!warnings.length) {
                previewWarnings.classList.add('hidden');
                previewWarnings.innerHTML = '';
                return;
            }

            previewWarnings.innerHTML = `
                <strong class="block mb-1">Warnings</strong>
                <ul class="list-disc list-inside space-y-1">
                    ${warnings.map((warning) => `<li>${escapeHtml(warning)}</li>`).join('')}
                </ul>
            `;
            previewWarnings.classList.remove('hidden');
        }

        fileUploadArea.addEventListener('click', () => csvFile.click());
        
        csvFile.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                fileName.textContent = e.target.files[0].name;
                fileName.classList.remove('hidden');
            }
        });

        fileUploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            fileUploadArea.classList.add('bg-indigo-100');
        });

        fileUploadArea.addEventListener('dragleave', () => {
            fileUploadArea.classList.remove('bg-indigo-100');
        });

        fileUploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            fileUploadArea.classList.remove('bg-indigo-100');
            if (e.dataTransfer.files.length > 0) {
                csvFile.files = e.dataTransfer.files;
                fileName.textContent = e.dataTransfer.files[0].name;
                fileName.classList.remove('hidden');
            }
        });

        previewBtn.addEventListener('click', async () => {
            if (!csvFile.files.length) {
                alert('Please select a CSV file first');
                return;
            }

            const formData = new FormData();
            formData.append('csv_file', csvFile.files[0]);
            formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);

            try {
                const response = await fetch('{{ route("lead-import.csv.preview") }}', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    renderDetectedColumns(data.detected_columns || {});
                    renderStageSummary(data.stage_summary || {}, !!data.has_stage_column);
                    renderWarnings(data);
                    previewContent.innerHTML = `
                        <p class="mb-3"><strong>Total rows found: ${data.total}</strong></p>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Phone</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Stage</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    ${data.preview.map(row => `
                                        <tr>
                                            <td class="px-4 py-2 text-sm text-gray-900">${row.name || ''}</td>
                                            <td class="px-4 py-2 text-sm text-gray-900">${row.phone || ''}</td>
                                            <td class="px-4 py-2 text-sm text-gray-900">${row.email || ''}</td>
                                            <td class="px-4 py-2 text-sm text-gray-900">${row.lead_stage || '(Blank)'}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    `;
                    previewSection.classList.remove('hidden');
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                alert('Error previewing file: ' + error.message);
            }
        });

        demoImportBtn.addEventListener('click', function () {
            if (!csvFile.files.length) {
                alert('Please select and preview a CSV file first');
                return;
            }

            importModeInput.value = 'demo';
            importForm.requestSubmit();
        });

        importForm.addEventListener('submit', function (event) {
            const submitterId = event.submitter?.id;
            if (submitterId !== 'demoImportBtn') {
                importModeInput.value = 'all';
            }
        });
    </script>
    @endpush
@endsection

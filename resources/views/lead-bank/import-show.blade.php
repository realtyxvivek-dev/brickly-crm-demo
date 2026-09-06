@extends('layouts.app')

@section('title', 'Lead Bank Import Preview - ' . brand_name())
@section('page-title', 'Lead Bank Import Preview')
@section('page-subtitle', $session->original_file_name)

@section('header-actions')
    <a href="{{ route('lead-bank.import.index') }}" class="px-4 py-2 rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200 text-sm font-medium">Import Sessions</a>
@endsection

@section('content')
    <div class="space-y-6">
        @if(session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($session->folder_name)
            @php
                $previewFolderColor = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $session->folder_color) ? $session->folder_color : '#205A44';
            @endphp
            <div class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white px-5 py-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl text-white shadow-sm" style="background-color: {{ $previewFolderColor }}"><i class="fas fa-folder"></i></span>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Smart folder</p>
                        <p class="mt-0.5 text-base font-semibold text-gray-900">{{ $session->folder_name }}</p>
                    </div>
                </div>
                <span class="w-fit rounded-full px-3 py-1 text-xs font-semibold {{ $session->folder_tag_id ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-50 text-blue-700' }}">
                    {{ $session->folder_tag_id ? 'Folder created' : 'Will be created on confirmation' }}
                </span>
            </div>
        @endif

        @if($isImportProcessing)
            @php
                $totalImportRows = max(1, (int) $session->included_rows);
                $importedRows = min((int) $session->imported_rows + (int) $session->failed_rows, (int) $session->included_rows);
                $initialImportPercent = round(($importedRows / $totalImportRows) * 100, 1);
            @endphp
            <div class="rounded-2xl border border-emerald-100 bg-white p-6 shadow-sm" data-lead-bank-import-processing="1">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">Import processing</p>
                        <h2 class="mt-2 text-2xl font-bold text-gray-900">Lead Bank import chal raha hai</h2>
                        <p class="mt-2 text-sm text-gray-500">Rows small batches me CRM me create/update ho rahi hain. Page ko open rakho.</p>
                    </div>
                    <span id="leadBankImportPercent" class="rounded-full bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-800">{{ $initialImportPercent }}%</span>
                </div>

                <div class="mt-6">
                    <div class="h-3 overflow-hidden rounded-full bg-gray-100">
                        <div id="leadBankImportBar" class="h-full rounded-full bg-[#205A44] transition-all" style="width: {{ $initialImportPercent }}%"></div>
                    </div>
                    <div class="mt-3 flex flex-col gap-1 text-sm text-gray-600 sm:flex-row sm:items-center sm:justify-between">
                        <span id="leadBankImportStatus">{{ number_format($importedRows) }} / {{ number_format($session->included_rows) }} rows processed</span>
                        <span id="leadBankImportCounts" class="text-xs text-gray-500">Imported {{ number_format($session->imported_rows) }}, failed {{ number_format($session->failed_rows) }}, skipped {{ number_format($session->skipped_rows) }}</span>
                    </div>
                    @if($session->processing_error)
                        <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $session->processing_error }}</div>
                    @else
                        <div id="leadBankImportError" class="mt-4 hidden rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"></div>
                    @endif
                </div>
            </div>
        @elseif(!$isPreviewReady)
            @php
                $totalRows = max(1, (int) $session->total_rows);
                $processedRows = min((int) $session->processed_rows, (int) $session->total_rows);
                $initialPercent = round(($processedRows / $totalRows) * 100, 1);
            @endphp
            <div class="rounded-2xl border border-emerald-100 bg-white p-6 shadow-sm" data-lead-bank-preview-processing="1">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">Preview processing</p>
                        <h2 class="mt-2 text-2xl font-bold text-gray-900">Lead preview generate ho raha hai</h2>
                        <p class="mt-2 text-sm text-gray-500">File upload ho chuki hai. Rows small batches me validate ho rahi hain, isliye 504 timeout nahi aayega.</p>
                    </div>
                    <span id="leadBankPreviewPercent" class="rounded-full bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-800">{{ $initialPercent }}%</span>
                </div>

                <div class="mt-6">
                    <div class="h-3 overflow-hidden rounded-full bg-gray-100">
                        <div id="leadBankPreviewBar" class="h-full rounded-full bg-[#205A44] transition-all" style="width: {{ $initialPercent }}%"></div>
                    </div>
                    <div class="mt-3 flex flex-col gap-1 text-sm text-gray-600 sm:flex-row sm:items-center sm:justify-between">
                        <span id="leadBankPreviewStatus">{{ number_format($processedRows) }} / {{ number_format($session->total_rows) }} rows processed</span>
                        <span class="text-xs text-gray-500">{{ $session->original_file_name }}</span>
                    </div>
                    @if($session->processing_error)
                        <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $session->processing_error }}</div>
                    @else
                        <div id="leadBankPreviewError" class="mt-4 hidden rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"></div>
                    @endif
                </div>
            </div>
        @else

        <div class="grid grid-cols-2 lg:grid-cols-7 gap-4">
            @foreach([
                ['Total Rows', $summary['total'], 'text-gray-900'],
                ['Included', $summary['included'], 'text-emerald-700'],
                ['Duplicates', $summary['duplicate'], 'text-amber-600'],
                ['Invalid', $summary['invalid'], 'text-rose-700'],
                ['Blocked', $summary['blocked'], 'text-rose-700'],
                ['Malformed', $summary['malformed'], 'text-rose-700'],
                ['Skipped', $summary['skipped'], 'text-gray-700'],
            ] as [$label, $value, $class])
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                    <p class="text-sm font-medium text-gray-500">{{ $label }}</p>
                    <p class="mt-2 text-3xl font-bold {{ $class }}">{{ number_format($value) }}</p>
                </div>
            @endforeach
        </div>

        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
            <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">Column Mapping</h2>
                    <p class="text-sm text-gray-500 mt-1">Map file columns to Lead Bank fields. Refreshing mapping rebuilds preview validation from the saved draft file.</p>
                </div>
                <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $session->status === 'imported' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">{{ ucfirst($session->status) }}</span>
            </div>

            <form method="POST" action="{{ route('lead-bank.import.mapping', $session) }}" class="mt-5">
                @csrf
                <div class="grid grid-cols-1 gap-3 md:grid-cols-3 xl:grid-cols-5">
                    @foreach($session->headers ?? [] as $header)
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-3">
                            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">{{ $header['label'] }}</label>
                            <select name="column_mapping[{{ $header['index'] }}]" class="w-full rounded-lg border-gray-300 text-sm focus:border-[#205A44] focus:ring-[#205A44]" @disabled($session->status === 'imported')>
                                @foreach($fieldOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(($session->column_mapping[(string)$header['index']] ?? 'skip') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                </div>
                @if($session->status !== 'imported')
                    <div class="mt-4 flex justify-end">
                        <button type="submit" class="px-4 py-2 rounded-lg bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white hover:from-[#205A44] hover:to-[#15803d] text-sm font-medium">Refresh Preview</button>
                    </div>
                @endif
            </form>
        </div>

        @if($session->status !== 'imported')
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                <h2 class="text-xl font-semibold text-gray-900">Bulk Preview Tag</h2>
                <p class="text-sm text-gray-500 mt-1">Select preview rows below, then apply this tag to those draft rows.</p>
                <form method="POST" action="{{ route('lead-bank.import.bulk-tag', $session) }}" id="leadBankPreviewBulkTagForm" class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-4">
                    @csrf
                    <input type="text" name="bulk_tag" placeholder="Example: Premium Data" class="rounded-lg border-gray-300 text-sm focus:border-[#205A44] focus:ring-[#205A44]" required>
                    <button type="button" id="previewSelectValid" class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">Select Valid</button>
                    <button type="button" id="previewSelectAll" class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">Select All</button>
                    <button type="submit" class="rounded-lg bg-gradient-to-r from-[#063A1C] to-[#205A44] px-4 py-2 text-sm font-medium text-white hover:from-[#205A44] hover:to-[#15803d]">Apply Tag</button>
                </form>
            </div>
        @endif

        <form method="POST" action="{{ route('lead-bank.import.rows', $session) }}" id="leadBankPreviewRowsForm" class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
            @csrf
            <div class="px-6 py-4 border-b border-gray-100 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">Preview Rows</h2>
                    <p class="text-sm text-gray-500">Edit tags, include/skip rows, and choose duplicate update behavior before confirmation.</p>
                </div>
                @if($session->status !== 'imported')
                    <button type="submit" class="px-4 py-2 rounded-lg bg-gray-900 text-white hover:bg-gray-800 text-sm font-medium">Save Preview Decisions</button>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Select</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Row</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Lead</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">City / Source</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Tags</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Validation</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Action</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Include</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @foreach($session->rows as $row)
                            @php
                                $mapped = $row->mapped_data ?? [];
                                $badge = match($row->validation_status) {
                                    'valid' => 'bg-emerald-100 text-emerald-800',
                                    'duplicate' => 'bg-amber-100 text-amber-800',
                                    default => 'bg-rose-100 text-rose-800',
                                };
                            @endphp
                            <tr class="hover:bg-gray-50 {{ !$row->include ? 'opacity-60' : '' }}">
                                <td class="px-4 py-3">
                                    <input type="checkbox" name="row_ids[]" form="leadBankPreviewBulkTagForm" value="{{ $row->id }}" data-status="{{ $row->validation_status }}" class="preview-row-check rounded border-gray-300 text-[#205A44] focus:ring-[#205A44]" @disabled($session->status === 'imported')>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ $row->row_number }}</td>
                                <td class="px-4 py-3">
                                    <div class="text-sm font-semibold text-gray-900">{{ $mapped['name'] ?? 'N/A' }}</div>
                                    <div class="text-xs text-gray-500">{{ $row->normalized_phone ?: ($mapped['phone'] ?? 'No phone') }}</div>
                                    @if($row->existingLead)
                                        <a href="{{ route('leads.show', $row->existingLead) }}" class="text-xs font-medium text-brand-secondary hover:text-brand-primary">Existing lead #{{ $row->existingLead->id }}</a>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-sm text-gray-900">{{ $mapped['city'] ?? 'N/A' }}</div>
                                    <div class="text-xs text-gray-500">{{ strtoupper($mapped['source'] ?? $session->source_type) }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <input type="text" name="rows[{{ $row->id }}][tags]" value="{{ implode(', ', $row->tags ?? []) }}" class="min-w-[220px] rounded-lg border-gray-300 text-xs focus:border-[#205A44] focus:ring-[#205A44]" @disabled($session->status === 'imported')>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $badge }}">{{ ucfirst(str_replace('_', ' ', $row->validation_status)) }}</span>
                                    <div class="mt-1 max-w-xs text-xs text-gray-500">{{ implode(' ', $row->errors ?? []) }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <select name="rows[{{ $row->id }}][import_action]" class="rounded-lg border-gray-300 text-xs focus:border-[#205A44] focus:ring-[#205A44]" @disabled($session->status === 'imported')>
                                        <option value="create" @selected($row->import_action === 'create') @disabled($row->validation_status === 'duplicate')>Create lead</option>
                                        <option value="update_existing" @selected($row->import_action === 'update_existing') @disabled(!$row->existing_lead_id)>Update existing tags</option>
                                        <option value="skip" @selected($row->import_action === 'skip')>Skip</option>
                                    </select>
                                </td>
                                <td class="px-4 py-3">
                                    <input type="checkbox" name="rows[{{ $row->id }}][include]" value="1" class="rounded border-gray-300 text-[#205A44] focus:ring-[#205A44]" @checked($row->include) @disabled($session->status === 'imported')>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </form>

        @if($session->status !== 'imported')
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900">Confirm Import</h2>
                        <p class="text-sm text-gray-500 mt-1">Only included rows are processed. Duplicate rows can update existing lead tags, but will not create a second lead.</p>
                    </div>
                    <form method="POST" action="{{ route('lead-bank.import.confirm', $session) }}" id="leadBankConfirmImportForm">
                        @csrf
                        <button type="button" id="openLeadBankConfirmModal" class="px-5 py-2.5 rounded-lg bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white hover:from-[#205A44] hover:to-[#15803d] text-sm font-medium">Confirm Import</button>
                    </form>
                </div>
            </div>

            <div id="leadBankConfirmModal" class="fixed inset-0 z-[9999] hidden items-center justify-center bg-slate-950/55 px-4 py-6 backdrop-blur-sm">
                <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl ring-1 ring-black/5">
                    <div class="border-b border-gray-100 px-6 py-5">
                        <div class="flex items-start gap-4">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-[#205A44]">
                                <i class="fas fa-check-circle"></i>
                            </span>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Confirm Lead Bank import?</h3>
                                <p class="mt-1 text-sm leading-6 text-gray-500">Included clean rows will be imported now. Skipped or failed rows will not be added.</p>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-col-reverse gap-3 px-6 py-4 sm:flex-row sm:justify-end">
                        <button type="button" id="closeLeadBankConfirmModal" class="inline-flex min-h-[42px] items-center justify-center rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancel</button>
                        <button type="button" id="submitLeadBankConfirmImport" class="inline-flex min-h-[42px] items-center justify-center rounded-xl bg-gradient-to-r from-[#063A1C] to-[#205A44] px-5 py-2 text-sm font-semibold text-white shadow-sm hover:from-[#205A44] hover:to-[#15803d]">
                            Yes, confirm import
                        </button>
                    </div>
                </div>
            </div>
        @endif
        @endif
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const importProcessingPanel = document.querySelector('[data-lead-bank-import-processing]');
        if (importProcessingPanel) {
            const processUrl = @json(route('lead-bank.import.process-confirm', $session));
            const csrfToken = @json(csrf_token());
            const progressBar = document.getElementById('leadBankImportBar');
            const progressPercent = document.getElementById('leadBankImportPercent');
            const progressStatus = document.getElementById('leadBankImportStatus');
            const progressCounts = document.getElementById('leadBankImportCounts');
            const progressError = document.getElementById('leadBankImportError');

            const updateProgress = (payload) => {
                const percent = Number(payload.percent || 0);
                if (progressBar) progressBar.style.width = `${Math.min(100, percent)}%`;
                if (progressPercent) progressPercent.textContent = `${percent}%`;
                if (progressStatus) progressStatus.textContent = `${Number(payload.processed || 0).toLocaleString()} / ${Number(payload.total || 0).toLocaleString()} rows processed`;
                if (progressCounts) progressCounts.textContent = `Imported ${Number((payload.processed || 0) - (payload.failed || 0)).toLocaleString()}, failed ${Number(payload.failed || 0).toLocaleString()}, skipped ${Number(payload.skipped || 0).toLocaleString()}`;
            };

            const processNextChunk = async () => {
                try {
                    const response = await fetch(processUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                        },
                    });
                    const payload = await response.json();

                    if (!response.ok || !payload.success) {
                        throw new Error(payload.message || 'Import processing failed.');
                    }

                    updateProgress(payload);
                    if (payload.done) {
                        window.location.href = payload.redirect_url || @json(route('lead-bank.index'));
                        return;
                    }

                    window.setTimeout(processNextChunk, 250);
                } catch (error) {
                    if (progressError) {
                        progressError.textContent = error.message || 'Import processing failed.';
                        progressError.classList.remove('hidden');
                    }
                }
            };

            processNextChunk();
            return;
        }

        const processingPanel = document.querySelector('[data-lead-bank-preview-processing]');
        if (processingPanel) {
            const processUrl = @json(route('lead-bank.import.process-preview', $session));
            const csrfToken = @json(csrf_token());
            const progressBar = document.getElementById('leadBankPreviewBar');
            const progressPercent = document.getElementById('leadBankPreviewPercent');
            const progressStatus = document.getElementById('leadBankPreviewStatus');
            const progressError = document.getElementById('leadBankPreviewError');

            const updateProgress = (payload) => {
                const percent = Number(payload.percent || 0);
                if (progressBar) progressBar.style.width = `${Math.min(100, percent)}%`;
                if (progressPercent) progressPercent.textContent = `${percent}%`;
                if (progressStatus) progressStatus.textContent = `${Number(payload.processed || 0).toLocaleString()} / ${Number(payload.total || 0).toLocaleString()} rows processed`;
            };

            const processNextChunk = async () => {
                try {
                    const response = await fetch(processUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                        },
                    });
                    const payload = await response.json();

                    if (!response.ok || !payload.success) {
                        throw new Error(payload.message || 'Preview processing failed.');
                    }

                    updateProgress(payload);
                    if (payload.done) {
                        window.location.href = payload.redirect_url || window.location.href;
                        return;
                    }

                    window.setTimeout(processNextChunk, 250);
                } catch (error) {
                    if (progressError) {
                        progressError.textContent = error.message || 'Preview processing failed.';
                        progressError.classList.remove('hidden');
                    }
                }
            };

            processNextChunk();
            return;
        }

        const selectValid = document.getElementById('previewSelectValid');
        const selectAll = document.getElementById('previewSelectAll');
        const bulkForm = document.getElementById('leadBankPreviewBulkTagForm');
        const checks = () => Array.from(document.querySelectorAll('.preview-row-check'));

        if (selectValid) {
            selectValid.addEventListener('click', function () {
                checks().forEach((check) => {
                    check.checked = check.dataset.status === 'valid';
                });
            });
        }

        if (selectAll) {
            selectAll.addEventListener('click', function () {
                const shouldSelect = checks().some((check) => !check.checked);
                checks().forEach((check) => {
                    check.checked = shouldSelect;
                });
            });
        }

        if (bulkForm) {
            bulkForm.addEventListener('submit', function (event) {
                if (!checks().some((check) => check.checked)) {
                    event.preventDefault();
                    alert('Select at least one preview row.');
                }
            });
        }

        const confirmForm = document.getElementById('leadBankConfirmImportForm');
        const confirmModal = document.getElementById('leadBankConfirmModal');
        const openConfirmModal = document.getElementById('openLeadBankConfirmModal');
        const closeConfirmModal = document.getElementById('closeLeadBankConfirmModal');
        const submitConfirmImport = document.getElementById('submitLeadBankConfirmImport');

        const showConfirmModal = () => {
            if (!confirmModal) return;
            confirmModal.classList.remove('hidden');
            confirmModal.classList.add('flex');
            document.body.classList.add('overflow-hidden');
        };

        const hideConfirmModal = () => {
            if (!confirmModal) return;
            confirmModal.classList.add('hidden');
            confirmModal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
        };

        if (openConfirmModal) {
            openConfirmModal.addEventListener('click', showConfirmModal);
        }

        if (closeConfirmModal) {
            closeConfirmModal.addEventListener('click', hideConfirmModal);
        }

        if (confirmModal) {
            confirmModal.addEventListener('click', function (event) {
                if (event.target === confirmModal) {
                    hideConfirmModal();
                }
            });
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                hideConfirmModal();
            }
        });

        if (submitConfirmImport && confirmForm) {
            submitConfirmImport.addEventListener('click', function () {
                submitConfirmImport.disabled = true;
                submitConfirmImport.classList.add('opacity-70', 'cursor-not-allowed');
                submitConfirmImport.textContent = 'Importing...';
                confirmForm.submit();
            });
        }
    });
</script>
@endpush

@extends('layouts.app')

@section('title', 'Lead Bank Import - ' . brand_name())
@section('page-title', 'Lead Bank Import')
@section('page-subtitle', 'Upload CSV/XLSX files, save draft sessions, and resume preview decisions')

@section('header-actions')
    <a href="{{ route('lead-bank.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50">
        <i class="fas fa-arrow-left text-xs"></i>
        Back to Lead Bank
    </a>
@endsection

@push('styles')
<style>
    .lead-bank-import-shell { --lb-green: #063A1C; --lb-green-soft: #205A44; --lb-border: #e3e8e5; --lb-muted: #667085; }
    .lead-bank-surface { border: 1px solid var(--lb-border); box-shadow: 0 12px 34px rgba(15, 45, 34, .055); }
    .lead-bank-input { min-height: 48px; border: 1px solid #d7dce3; background: #fff; color: #111827; transition: border-color .18s ease, box-shadow .18s ease; }
    .lead-bank-input:focus { border-color: #205A44; box-shadow: 0 0 0 3px rgba(32, 90, 68, .12); outline: none; }
    .lead-bank-file-zone { min-height: 132px; border: 1.5px dashed #b9c9c1; background: linear-gradient(145deg, #fbfdfc 0%, #f4f9f6 100%); transition: border-color .18s ease, background .18s ease, box-shadow .18s ease, transform .18s ease; }
    .lead-bank-file-zone:hover { border-color: #205A44; background: #f1f8f4; box-shadow: 0 10px 24px rgba(32, 90, 68, .08); transform: translateY(-1px); }
    .lead-bank-file-zone.has-file { border-style: solid; border-color: #16a34a; background: #f0fdf4; }
    .lead-bank-section-title { letter-spacing: -0.01em; }
    .lead-bank-progress { position: relative; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; }
    .lead-bank-progress::before { content: ''; position: absolute; top: 20px; left: 16.5%; right: 16.5%; height: 2px; background: #e2e8e5; }
    .lead-bank-step { position: relative; z-index: 1; display: flex; align-items: center; gap: 12px; min-width: 0; }
    .lead-bank-step-number { display: inline-flex; width: 40px; height: 40px; flex: 0 0 40px; align-items: center; justify-content: center; border: 5px solid #fff; border-radius: 999px; background: #e8f4ed; color: #14633f; font-size: 13px; font-weight: 800; box-shadow: 0 0 0 1px #d8e6de; }
    .lead-bank-step:first-child .lead-bank-step-number { background: #0b6b38; color: #fff; box-shadow: 0 0 0 1px #0b6b38; }
    .lead-bank-config-panel { border: 1px solid #e4e9e6; background: #fafcfb; }
    .lead-bank-folder-builder { border: 1px solid #dbe7e0; background: #fff; }
    .lead-bank-folder-builder[hidden] { display: none; }
    .lead-bank-color-preset { width: 24px; height: 24px; border: 3px solid #fff; border-radius: 999px; box-shadow: 0 0 0 1px #d1d5db; transition: transform .15s ease, box-shadow .15s ease; }
    .lead-bank-color-preset:hover, .lead-bank-color-preset.is-active { transform: scale(1.08); box-shadow: 0 0 0 2px #205A44; }
    .lead-bank-primary-btn { min-height: 50px; box-shadow: 0 8px 18px rgba(6, 58, 28, .16); transition: transform .18s ease, box-shadow .18s ease, background .18s ease; }
    .lead-bank-primary-btn:hover { transform: translateY(-1px); box-shadow: 0 11px 24px rgba(6, 58, 28, .2); }
    .lead-bank-primary-btn.is-loading { opacity: .72; cursor: wait; transform: none; }
    .lead-bank-upload-progress { border: 1px solid #bbf7d0; background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 100%); box-shadow: 0 14px 30px rgba(15, 45, 34, .08); }
    .lead-bank-upload-progress[hidden] { display: none; }
    .lead-bank-upload-track { height: 12px; overflow: hidden; border-radius: 999px; background: #dbeafe; box-shadow: inset 0 1px 2px rgba(15, 23, 42, .08); }
    .lead-bank-upload-bar { width: 0%; height: 100%; border-radius: inherit; background: linear-gradient(90deg, #0b6b38, #16a34a, #22c55e); transition: width .2s ease; }
    .lead-bank-upload-bar.is-indeterminate { position: relative; width: 38%; animation: leadBankUploadIndeterminate 1.1s ease-in-out infinite; }
    .lead-bank-upload-meta { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
    @keyframes leadBankUploadIndeterminate {
        0% { transform: translateX(-120%); }
        100% { transform: translateX(280%); }
    }
    .lead-bank-empty-icon { box-shadow: 0 0 0 10px #f5f8f6; }
    @media (max-width: 767px) {
        .lead-bank-progress { grid-template-columns: 1fr; gap: 14px; }
        .lead-bank-progress::before { top: 20px; bottom: 20px; left: 19px; right: auto; width: 2px; height: auto; }
        .lead-bank-file-zone { min-height: 112px; }
    }
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const fileInput = document.getElementById('leadBankImportFile');
        const fileName = document.getElementById('leadBankImportFileName');
        const fileZone = document.getElementById('leadBankFileZone');
        const emptyUploadButton = document.getElementById('leadBankEmptyUpload');
        const createFolder = document.getElementById('leadBankCreateFolder');
        const folderBuilder = document.getElementById('leadBankFolderBuilder');
        const folderName = document.getElementById('leadBankFolderName');
        const folderColor = document.getElementById('leadBankFolderColor');
        const importForm = document.getElementById('leadBankImportForm');
        const submitButton = document.getElementById('leadBankImportSubmit');
        const progressPanel = document.getElementById('leadBankUploadProgress');
        const progressBar = document.getElementById('leadBankUploadProgressBar');
        const progressPercent = document.getElementById('leadBankUploadPercent');
        const progressStatus = document.getElementById('leadBankUploadStatus');
        const progressFile = document.getElementById('leadBankUploadFileMeta');

        if (fileInput && fileName) {
            fileInput.addEventListener('change', function () {
                const hasFile = fileInput.files && fileInput.files.length;
                fileName.textContent = hasFile ? fileInput.files[0].name : 'Choose CSV, XLSX, or XLS file';
                if (fileZone) fileZone.classList.toggle('has-file', Boolean(hasFile));
            });
        }

        if (emptyUploadButton && fileInput) {
            emptyUploadButton.addEventListener('click', function () {
                fileInput.click();
            });
        }

        function syncFolderBuilder() {
            if (!createFolder || !folderBuilder) return;
            folderBuilder.hidden = !createFolder.checked;
            if (folderName) folderName.required = createFolder.checked;
            if (folderColor) folderColor.required = createFolder.checked;
        }

        if (createFolder) {
            createFolder.addEventListener('change', syncFolderBuilder);
            syncFolderBuilder();
        }

        document.querySelectorAll('[data-folder-color]').forEach(function (button) {
            button.addEventListener('click', function () {
                if (!folderColor) return;
                folderColor.value = button.dataset.folderColor;
                document.querySelectorAll('[data-folder-color]').forEach(function (preset) {
                    preset.classList.toggle('is-active', preset === button);
                });
            });
        });

        function formatBytes(bytes) {
            if (!bytes) return '0 KB';
            const units = ['B', 'KB', 'MB', 'GB'];
            const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
            const value = bytes / Math.pow(1024, index);
            return `${value.toFixed(value >= 10 || index === 0 ? 0 : 1)} ${units[index]}`;
        }

        function setProgress(percent, statusText) {
            const safePercent = Math.max(0, Math.min(100, Math.round(percent)));
            if (progressBar) {
                progressBar.classList.remove('is-indeterminate');
                progressBar.style.width = `${safePercent}%`;
            }
            if (progressPercent) progressPercent.textContent = `${safePercent}%`;
            if (progressStatus && statusText) progressStatus.textContent = statusText;
        }

        function setFormLocked(isLocked) {
            if (!importForm) return;
            importForm.querySelectorAll('input, select, button').forEach(function (control) {
                control.disabled = isLocked;
            });
            if (submitButton) {
                submitButton.classList.toggle('is-loading', isLocked);
                submitButton.innerHTML = isLocked
                    ? '<i class="fas fa-spinner fa-spin"></i> Uploading leads...'
                    : '<i class="fas fa-eye"></i> Upload & Preview Leads';
            }
        }

        if (importForm) {
            importForm.addEventListener('submit', function (event) {
                if (!importForm.checkValidity()) {
                    return;
                }

                event.preventDefault();

                if (!fileInput || !fileInput.files || !fileInput.files.length) {
                    fileInput?.reportValidity();
                    return;
                }

                const selectedFile = fileInput.files[0];
                const formData = new FormData(importForm);
                const xhr = new XMLHttpRequest();

                if (progressPanel) progressPanel.hidden = false;
                if (progressFile) progressFile.textContent = `${selectedFile.name} • ${formatBytes(selectedFile.size)}`;
                setProgress(2, 'Upload start ho raha hai...');
                setFormLocked(true);

                xhr.upload.addEventListener('progress', function (event) {
                    if (event.lengthComputable) {
                        const percent = (event.loaded / event.total) * 100;
                        setProgress(percent, percent >= 100 ? 'File uploaded. Preview generate ho raha hai...' : 'File upload ho rahi hai...');
                    } else if (progressBar) {
                        progressBar.classList.add('is-indeterminate');
                        if (progressStatus) progressStatus.textContent = 'Upload progress calculate ho raha hai...';
                    }
                });

                xhr.addEventListener('load', function () {
                    setProgress(100, 'Upload complete. Preview page open ho raha hai...');
                    const finalUrl = xhr.responseURL || importForm.action;
                    if (xhr.status >= 200 && xhr.status < 400) {
                        window.location.href = finalUrl;
                        return;
                    }

                    document.open();
                    document.write(xhr.responseText);
                    document.close();
                });

                xhr.addEventListener('error', function () {
                    setFormLocked(false);
                    if (progressStatus) progressStatus.textContent = 'Upload failed. Network connection check karke dobara try karo.';
                });

                xhr.addEventListener('abort', function () {
                    setFormLocked(false);
                    if (progressStatus) progressStatus.textContent = 'Upload cancel ho gaya.';
                });

                xhr.open('POST', importForm.action, true);
                xhr.send(formData);
            });
        }
    });
</script>
@endpush

@section('content')
    <div class="lead-bank-import-shell w-full space-y-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-start gap-3">
                <a href="{{ route('lead-bank.index') }}" class="mt-1 inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-600 shadow-sm transition hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-800" aria-label="Back to Lead Bank">
                    <i class="fas fa-arrow-left text-sm"></i>
                </a>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-[#063A1C]">Lead Bank Import</h1>
                    <p class="mt-1 text-sm text-gray-500">Upload, validate, tag and confirm leads before they enter the central inventory.</p>
                </div>
            </div>
            <a href="{{ route('lead-bank.import.sample-download') }}" class="inline-flex min-h-[42px] items-center justify-center gap-2 rounded-xl border border-emerald-200 bg-white px-4 py-2 text-sm font-semibold text-emerald-800 shadow-sm transition hover:border-emerald-300 hover:bg-emerald-50">
                <i class="fas fa-file-excel"></i>
                Download Sample Excel
            </a>
        </div>

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

        <div class="lead-bank-surface overflow-hidden rounded-2xl bg-white">
            <div class="flex flex-col gap-3 border-b border-gray-100 bg-gradient-to-r from-white to-emerald-50/40 px-6 py-5 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="lead-bank-section-title text-lg font-semibold text-gray-900">Create a new import</h2>
                    <p class="mt-1 max-w-3xl text-sm text-gray-500">Your file is saved as a draft first, so nothing enters the CRM without review.</p>
                </div>
                <span class="inline-flex w-fit items-center gap-2 rounded-full border border-emerald-100 bg-white px-3 py-1.5 text-xs font-semibold text-emerald-700 shadow-sm">
                    <i class="fas fa-shield-halved"></i>
                    Safe preview enabled
                </span>
            </div>

            <div class="px-6 py-5">
                <div class="lead-bank-progress">
                    <div class="lead-bank-step">
                        <span class="lead-bank-step-number">01</span>
                        <div class="min-w-0 bg-white pr-3">
                            <p class="text-sm font-semibold text-gray-900">Upload file</p>
                            <p class="mt-0.5 text-xs text-gray-500">CSV or Excel</p>
                        </div>
                    </div>
                    <div class="lead-bank-step">
                        <span class="lead-bank-step-number">02</span>
                        <div class="min-w-0 bg-white pr-3">
                            <p class="text-sm font-semibold text-gray-900">Review mapping</p>
                            <p class="mt-0.5 text-xs text-gray-500">Check rows and tags</p>
                        </div>
                    </div>
                    <div class="lead-bank-step">
                        <span class="lead-bank-step-number">03</span>
                        <div class="min-w-0 bg-white pr-3">
                            <p class="text-sm font-semibold text-gray-900">Confirm import</p>
                            <p class="mt-0.5 text-xs text-gray-500">Add clean leads only</p>
                        </div>
                    </div>
                </div>

                <form id="leadBankImportForm" method="POST" action="{{ route('lead-bank.import.upload') }}" enctype="multipart/form-data" class="mt-6 grid grid-cols-1 gap-5 xl:grid-cols-12">
                    @csrf
                    <div class="xl:col-span-7">
                        <div class="mb-2 flex items-center justify-between gap-3">
                            <label class="text-xs font-semibold uppercase tracking-wide text-gray-600">Lead file <span class="text-rose-500">*</span></label>
                            <span class="text-xs text-gray-400">Maximum 20 MB</span>
                        </div>
                        <label id="leadBankFileZone" for="leadBankImportFile" class="lead-bank-file-zone flex cursor-pointer items-center justify-center gap-4 rounded-2xl px-5 py-4 text-center sm:text-left">
                            <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-white text-xl text-[#14633f] shadow-sm ring-1 ring-emerald-100">
                                <i class="fas fa-cloud-arrow-up"></i>
                            </span>
                            <span class="min-w-0">
                                <span id="leadBankImportFileName" class="block truncate text-base font-semibold text-gray-900">Choose CSV, XLSX, or XLS file</span>
                                <span class="mt-1 block text-sm text-gray-500">Click to browse. Supported: CSV, TXT, XLSX and XLS.</span>
                            </span>
                        </label>
                        <input id="leadBankImportFile" type="file" name="file" accept=".csv,.txt,.xlsx,.xls" class="sr-only" required>
                    </div>

                    <div class="lead-bank-config-panel rounded-2xl p-4 xl:col-span-5">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-600">Lead source</label>
                                <select name="source_type" class="lead-bank-input w-full rounded-xl px-3 text-sm">
                                    <option value="csv">CSV Vendor</option>
                                    <option value="sheet">Excel / Sheet</option>
                                    <option value="meta">Meta</option>
                                    <option value="website">Website</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-600">Default tags <span class="font-normal normal-case text-gray-400">optional</span></label>
                                <input type="text" name="default_tags" placeholder="Delhi May, Vendor A" class="lead-bank-input w-full rounded-xl px-3 text-sm">
                            </div>
                        </div>
                        <label class="mt-4 flex cursor-pointer items-start gap-3 rounded-xl border border-emerald-100 bg-emerald-50/60 px-3 py-3">
                            <input id="leadBankCreateFolder" type="checkbox" name="create_folder" value="1" class="mt-0.5 rounded border-emerald-300 text-emerald-700 focus:ring-emerald-600" @checked(old('create_folder'))>
                            <span>
                                <span class="block text-sm font-semibold text-gray-900">Create a smart folder</span>
                                <span class="mt-0.5 block text-xs text-gray-500">All successfully imported leads will appear inside this colored folder.</span>
                            </span>
                        </label>
                        <div id="leadBankFolderBuilder" class="lead-bank-folder-builder mt-3 rounded-xl p-3" @if(!old('create_folder')) hidden @endif>
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                                <div>
                                    <label for="leadBankFolderName" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-600">Folder name</label>
                                    <input id="leadBankFolderName" type="text" name="folder_name" value="{{ old('folder_name') }}" maxlength="80" placeholder="Example: Noida Leads" class="lead-bank-input w-full rounded-xl px-3 text-sm">
                                </div>
                                <div>
                                    <label for="leadBankFolderColor" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-600">Color</label>
                                    <input id="leadBankFolderColor" type="color" name="folder_color" value="{{ old('folder_color', '#205A44') }}" class="h-12 w-16 cursor-pointer rounded-xl border border-gray-300 bg-white p-1">
                                </div>
                            </div>
                            <div class="mt-3 flex flex-wrap items-center gap-2" aria-label="Folder color presets">
                                @foreach(['#205A44', '#2563EB', '#7C3AED', '#D97706', '#DC2626', '#DB2777'] as $folderColor)
                                    <button type="button" data-folder-color="{{ $folderColor }}" class="lead-bank-color-preset" style="background-color: {{ $folderColor }}" aria-label="Use folder color {{ $folderColor }}"></button>
                                @endforeach
                            </div>
                        </div>
                        <button id="leadBankImportSubmit" type="submit" class="lead-bank-primary-btn mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-[#063A1C] to-[#167044] px-5 py-3 text-sm font-semibold text-white">
                            <i class="fas fa-eye"></i>
                            Upload & Preview Leads
                        </button>
                        <p class="mt-2 text-center text-xs text-gray-500"><i class="fas fa-lock mr-1 text-emerald-700"></i>No leads are imported until you confirm the preview.</p>
                    </div>
                </form>
                <div id="leadBankUploadProgress" class="lead-bank-upload-progress mt-5 rounded-2xl p-4" hidden>
                    <div class="lead-bank-upload-meta">
                        <div>
                            <p id="leadBankUploadStatus" class="text-sm font-semibold text-gray-900">Upload start ho raha hai...</p>
                            <p id="leadBankUploadFileMeta" class="mt-1 text-xs text-gray-500">File ready</p>
                        </div>
                        <div id="leadBankUploadPercent" class="rounded-full bg-white px-3 py-1 text-sm font-extrabold text-emerald-800 shadow-sm">0%</div>
                    </div>
                    <div class="lead-bank-upload-track mt-3">
                        <div id="leadBankUploadProgressBar" class="lead-bank-upload-bar"></div>
                    </div>
                    <p class="mt-3 text-xs text-gray-500">
                        Browser upload percentage real-time dikh raha hai. 100% ke baad server file validate karke preview page banata hai.
                    </p>
                </div>
            </div>
        </div>

        <div class="lead-bank-surface overflow-hidden rounded-2xl bg-white">
            <div class="flex flex-col gap-2 border-b border-gray-100 px-6 py-5 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-600"><i class="fas fa-clock-rotate-left"></i></span>
                    <div>
                        <h2 class="lead-bank-section-title text-lg font-semibold text-gray-900">Draft import sessions</h2>
                        <p class="mt-0.5 text-sm text-gray-500">Resume drafts, review issues and confirm when ready.</p>
                    </div>
                </div>
                <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">{{ number_format($sessions->count()) }} draft(s)</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">File</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Rows</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Included</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Issues</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Created</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse($sessions as $session)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm font-semibold text-gray-900">
                                    <div>{{ $session->original_file_name }}</div>
                                    @if($session->folder_name)
                                        @php
                                            $sessionFolderColor = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $session->folder_color) ? $session->folder_color : '#205A44';
                                        @endphp
                                        <span class="mt-1 inline-flex items-center gap-1.5 text-xs font-medium" style="color: {{ $sessionFolderColor }}"><i class="fas fa-folder"></i>{{ $session->folder_name }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $session->status === 'imported' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">{{ ucfirst($session->status) }}</span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ number_format($session->total_rows) }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ number_format($session->included_rows) }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ number_format($session->duplicate_rows + $session->invalid_rows) }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ $session->created_at->format('d M Y H:i') }}</td>
                                <td class="px-6 py-4 text-sm">
                                    <a href="{{ route('lead-bank.import.show', $session) }}" class="font-medium text-brand-secondary hover:text-brand-primary">Open</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-16 text-center">
                                    <div class="lead-bank-empty-icon mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 text-xl text-emerald-700">
                                        <i class="fas fa-file-circle-plus"></i>
                                    </div>
                                    <p class="mt-5 text-base font-semibold text-gray-900">No draft imports yet</p>
                                    <p class="mx-auto mt-1 max-w-md text-sm text-gray-500">Choose a lead file above to create a safe preview. You can review every row before importing.</p>
                                    <button id="leadBankEmptyUpload" type="button" class="mt-4 inline-flex items-center gap-2 rounded-xl border border-emerald-200 bg-white px-4 py-2 text-sm font-semibold text-emerald-800 transition hover:bg-emerald-50">
                                        <i class="fas fa-plus"></i>
                                        Choose a file
                                    </button>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

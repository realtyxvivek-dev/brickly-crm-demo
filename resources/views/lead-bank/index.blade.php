@extends('layouts.app')

@section('title', 'Lead Bank - ' . brand_name())
@section('page-title', 'Lead Bank')
@section('page-subtitle', 'Central lead inventory with dynamic availability and tagging foundation')

@push('styles')
<style>
    .lead-bank-shell { --lb-green: #063A1C; --lb-soft: #205A44; --lb-border: #e3e8e5; }
    .lead-bank-card { border: 1px solid var(--lb-border); box-shadow: 0 10px 26px rgba(15, 45, 34, .05); }
    .lead-bank-stat { min-height: 92px; transition: transform .18s ease, box-shadow .18s ease; }
    .lead-bank-stat:hover { transform: translateY(-1px); box-shadow: 0 12px 26px rgba(15, 45, 34, .075); }
    .lead-bank-stat-icon { width: 38px; height: 38px; border-radius: 11px; display: inline-flex; align-items: center; justify-content: center; }
    .lead-bank-field { min-height: 44px; border: 1px solid #d7dce3; background: #fff; }
    .lead-bank-field:focus { border-color: #205A44; box-shadow: 0 0 0 3px rgba(32, 90, 68, .12); outline: none; }
    .lead-bank-tools > summary { list-style: none; }
    .lead-bank-tools > summary::-webkit-details-marker { display: none; }
    .lead-bank-tools-chevron { transition: transform .18s ease; }
    .lead-bank-tools[open] .lead-bank-tools-chevron { transform: rotate(180deg); }
    .lead-bank-action { transition: transform .18s ease, box-shadow .18s ease, background .18s ease; }
    .lead-bank-action:hover { transform: translateY(-1px); }
    .lead-folder-card { position: relative; min-height: 154px; border-radius: 22px; border: 1px solid #e3e8e5; background: linear-gradient(145deg, #ffffff 0%, #f8fbf9 100%); box-shadow: 0 12px 28px rgba(15, 45, 34, .06); transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease; }
    .lead-folder-card::before { content: ''; position: absolute; left: 22px; top: -1px; width: 112px; height: 18px; border-radius: 0 0 14px 14px; background: var(--folder-color, #205A44); opacity: .95; }
    .lead-folder-card:hover { transform: translateY(-3px); border-color: #b9d8c6; box-shadow: 0 18px 36px rgba(15, 45, 34, .10); }
    .lead-folder-card .folder-open-icon { opacity: 0; transform: translateX(-4px); transition: opacity .18s ease, transform .18s ease; }
    .lead-folder-card:hover .folder-open-icon { opacity: 1; transform: translateX(0); }
    .lead-bank-coming-soon-action { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 36px; border-radius: 999px; border: 1px solid #bbf7d0; background: #f0fdf4; color: #166534; padding: 8px 12px; font-size: 12px; font-weight: 800; cursor: pointer; opacity: .95; transition: background .18s ease, transform .18s ease; }
    .lead-bank-coming-soon-action:hover { background: #dcfce7; transform: translateY(-1px); }
    .lead-bank-coming-soon-action small { color: #64748b; font-size: 10px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
    .lead-bank-folder-whatsapp { width: 100%; margin-top: 16px; }
    .lead-bank-folder-actions { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 10px; align-items: center; margin-top: 16px; }
    .lead-bank-folder-actions .lead-bank-folder-whatsapp { margin-top: 0; }
    .lead-bank-folder-delete { display: inline-flex; align-items: center; justify-content: center; width: 38px; height: 38px; border-radius: 999px; border: 1px solid #fecdd3; background: #fff1f2; color: #be123c; font-size: 13px; transition: transform .18s ease, background .18s ease, border-color .18s ease; }
    .lead-bank-folder-delete:hover { transform: translateY(-1px); background: #ffe4e6; border-color: #fda4af; }
    .lead-bank-delete-modal { position: fixed; inset: 0; z-index: 80; display: none; align-items: center; justify-content: center; padding: 18px; background: rgba(15, 23, 42, .52); backdrop-filter: blur(4px); }
    .lead-bank-delete-modal.is-open { display: flex; }
    .lead-bank-delete-dialog { width: min(440px, 100%); border-radius: 24px; background: #fff; box-shadow: 0 28px 80px rgba(15, 23, 42, .28); overflow: hidden; transform: translateY(8px) scale(.98); opacity: 0; transition: transform .18s ease, opacity .18s ease; }
    .lead-bank-delete-modal.is-open .lead-bank-delete-dialog { transform: translateY(0) scale(1); opacity: 1; }
    .lead-bank-delete-icon { width: 48px; height: 48px; border-radius: 16px; display: inline-flex; align-items: center; justify-content: center; background: #fff1f2; color: #be123c; }
    .lead-bank-delete-cancel { border: 1px solid #e2e8f0; background: #fff; color: #334155; }
    .lead-bank-delete-cancel:hover { background: #f8fafc; }
    .lead-bank-delete-confirm { background: #be123c; color: #fff; box-shadow: 0 10px 22px rgba(190, 18, 60, .22); }
    .lead-bank-delete-confirm:hover { background: #9f1239; }
    .lead-bank-inventory-layout { display: grid; grid-template-columns: 240px minmax(0, 1fr); }
    .lead-bank-folder-link { transition: background .16s ease, color .16s ease, transform .16s ease; }
    .lead-bank-folder-link:hover { transform: translateX(2px); }
    .lead-bank-folder-link.is-active { background: #f0f7f3; color: #063A1C; box-shadow: inset 0 0 0 1px #d9e9df; }
    @media (max-width: 1023px) {
        .lead-bank-inventory-layout { grid-template-columns: 1fr; }
        .lead-bank-folder-rail { border-right: 0 !important; border-bottom: 1px solid #e5e7eb; }
        .lead-bank-folder-list { display: flex; gap: 8px; overflow-x: auto; padding-bottom: 4px; }
        .lead-bank-folder-list .lead-bank-folder-link { min-width: 170px; }
        .lead-bank-folder-link:hover { transform: translateY(-1px); }
    }
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const checkAll = document.getElementById('leadBankCheckAll');
        const selectVisible = document.getElementById('leadBankSelectVisible');
        const form = document.getElementById('leadBankBulkTagForm');

        function rowChecks() {
            return Array.from(document.querySelectorAll('.lead-bank-row-check'));
        }

        if (checkAll) {
            checkAll.addEventListener('change', function () {
                rowChecks().forEach((checkbox) => {
                    checkbox.checked = checkAll.checked;
                });
            });
        }

        if (selectVisible) {
            selectVisible.addEventListener('click', function () {
                const checks = rowChecks();
                const shouldSelect = checks.some((checkbox) => !checkbox.checked);
                checks.forEach((checkbox) => {
                    checkbox.checked = shouldSelect;
                });
                if (checkAll) checkAll.checked = shouldSelect;
            });
        }

        if (form) {
            form.addEventListener('submit', function (event) {
                if (!rowChecks().some((checkbox) => checkbox.checked)) {
                    event.preventDefault();
                    alert('Select at least one lead before applying a tag.');
                }
            });
        }

        document.querySelectorAll('[data-lead-bank-coming-soon]').forEach((button) => {
            button.addEventListener('click', function () {
                alert('Coming soon');
            });
        });

        const deleteModal = document.querySelector('[data-folder-delete-modal]');
        const deleteName = document.querySelector('[data-folder-delete-name]');
        const deleteCancel = document.querySelector('[data-folder-delete-cancel]');
        const deleteConfirm = document.querySelector('[data-folder-delete-confirm]');
        let pendingDeleteForm = null;

        function closeDeleteModal() {
            if (!deleteModal) return;
            deleteModal.classList.remove('is-open');
            deleteModal.setAttribute('aria-hidden', 'true');
            pendingDeleteForm = null;
        }

        document.querySelectorAll('[data-folder-delete-form]').forEach((deleteForm) => {
            deleteForm.addEventListener('submit', function (event) {
                if (deleteForm.dataset.confirmed === '1') {
                    return;
                }

                event.preventDefault();
                pendingDeleteForm = deleteForm;
                if (deleteName) {
                    deleteName.textContent = deleteForm.dataset.folderName || 'Selected folder';
                }
                if (deleteModal) {
                    deleteModal.classList.add('is-open');
                    deleteModal.setAttribute('aria-hidden', 'false');
                }
            });
        });

        if (deleteCancel) {
            deleteCancel.addEventListener('click', closeDeleteModal);
        }

        if (deleteModal) {
            deleteModal.addEventListener('click', function (event) {
                if (event.target === deleteModal) {
                    closeDeleteModal();
                }
            });
        }

        if (deleteConfirm) {
            deleteConfirm.addEventListener('click', function () {
                if (!pendingDeleteForm) return;
                pendingDeleteForm.dataset.confirmed = '1';
                pendingDeleteForm.submit();
            });
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && deleteModal && deleteModal.classList.contains('is-open')) {
                closeDeleteModal();
            }
        });
    });
</script>
@endpush

@section('content')
    <div class="lead-bank-shell space-y-5">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-[#063A1C] sm:text-3xl">Lead Bank</h1>
                <p class="mt-1 text-sm text-gray-500">Search, tag and manage your central lead inventory.</p>
            </div>

            <div class="grid w-full grid-cols-1 gap-2 sm:flex sm:w-auto sm:flex-wrap sm:items-center sm:justify-end">
                <a href="{{ route('lead-bank.analytics') }}" class="lead-bank-action inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">
                    <i class="fas fa-chart-line text-xs"></i>
                    Analytics
                </a>
                <a href="{{ route('lead-bank.requests.index') }}" class="lead-bank-action inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">
                    <i class="fas fa-clipboard-list text-xs"></i>
                    Lead Requests
                </a>
                <a href="{{ route('lead-bank.import.index') }}" class="lead-bank-action inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-[#063A1C] to-[#205A44] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:from-[#205A44] hover:to-[#15803d]">
                    <i class="fas fa-file-import text-xs"></i>
                    Lead Bank Import
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                {{ session('error') }}
            </div>
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

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-3 xl:grid-cols-6">
            <div class="lead-bank-card lead-bank-stat rounded-2xl bg-white p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Total Leads</p>
                        <p class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($stats['total']) }}</p>
                    </div>
                    <span class="lead-bank-stat-icon bg-slate-100 text-slate-600"><i class="fas fa-database"></i></span>
                </div>
            </div>
            <div class="lead-bank-card lead-bank-stat rounded-2xl bg-white p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Available</p>
                        <p class="mt-1 text-2xl font-bold text-emerald-700">{{ number_format($stats['available']) }}</p>
                    </div>
                    <span class="lead-bank-stat-icon bg-emerald-50 text-emerald-700"><i class="fas fa-check"></i></span>
                </div>
            </div>
            <div class="lead-bank-card lead-bank-stat rounded-2xl bg-white p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Assigned</p>
                        <p class="mt-1 text-2xl font-bold text-indigo-700">{{ number_format($stats['assigned']) }}</p>
                    </div>
                    <span class="lead-bank-stat-icon bg-indigo-50 text-indigo-700"><i class="fas fa-user-check"></i></span>
                </div>
            </div>
            <div class="lead-bank-card lead-bank-stat rounded-2xl bg-white p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Cooling</p>
                        <p class="mt-1 text-2xl font-bold text-amber-600">{{ number_format($stats['cooling']) }}</p>
                    </div>
                    <span class="lead-bank-stat-icon bg-amber-50 text-amber-700"><i class="fas fa-clock"></i></span>
                </div>
            </div>
            <div class="lead-bank-card lead-bank-stat rounded-2xl bg-white p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Tagged</p>
                        <p class="mt-1 text-2xl font-bold text-blue-700">{{ number_format($stats['tagged']) }}</p>
                    </div>
                    <span class="lead-bank-stat-icon bg-blue-50 text-blue-700"><i class="fas fa-tags"></i></span>
                </div>
            </div>
            <div class="lead-bank-card lead-bank-stat rounded-2xl bg-white p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Blocked</p>
                        <p class="mt-1 text-2xl font-bold text-rose-700">{{ number_format($stats['blocked']) }}</p>
                    </div>
                    <span class="lead-bank-stat-icon bg-rose-50 text-rose-700"><i class="fas fa-ban"></i></span>
                </div>
            </div>
        </div>        <div class="lead-bank-card overflow-hidden rounded-2xl bg-white">
            <div class="border-b border-gray-100 px-6 py-5">
                <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Lead folders</h2>
                        <p class="text-sm text-gray-500">Open a folder to view, search, filter, or bulk tag its leads.</p>
                    </div>
                    <span class="w-fit rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ number_format($stats['total']) }} lead(s)</span>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 p-6 sm:grid-cols-2 xl:grid-cols-3">
                @foreach($systemFolders as $folder)
                    <div class="lead-folder-card group p-5 pt-7" style="--folder-color: {{ $folder['color'] }}">
                        <a href="{{ route('lead-bank.folder', ['scope' => $folder['key']]) }}" class="block">
                            <div class="flex items-start justify-between gap-4">
                                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl text-white shadow-sm" style="background-color: {{ $folder['color'] }}">
                                    <i class="fas {{ $folder['icon'] }}"></i>
                                </span>
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ number_format($folder['count']) }}</span>
                            </div>
                            <div class="mt-5">
                                <h3 class="text-base font-semibold text-gray-900 group-hover:text-[#063A1C]">{{ $folder['label'] }}</h3>
                                <p class="mt-1 flex items-center gap-2 text-sm text-gray-500">Open leads <i class="folder-open-icon fas fa-arrow-right text-xs text-emerald-700"></i></p>
                            </div>
                        </a>
                        <button type="button" class="lead-bank-coming-soon-action lead-bank-folder-whatsapp" data-lead-bank-coming-soon>
                            <i class="fab fa-whatsapp text-sm"></i>
                            <span>Send Bulk WhatsApp</span>
                        </button>
                    </div>
                @endforeach

                @foreach($folderTags as $folderTag)
                    @php
                        $customFolderColor = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $folderTag->color) ? $folderTag->color : '#205A44';
                    @endphp
                    <div class="lead-folder-card group p-5 pt-7" style="--folder-color: {{ $customFolderColor }}">
                        <a href="{{ route('lead-bank.folder.tag', ['tag' => $folderTag->id]) }}" class="block">
                            <div class="flex items-start justify-between gap-4">
                                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl text-white shadow-sm" style="background-color: {{ $customFolderColor }}">
                                    <i class="fas fa-folder"></i>
                                </span>
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ number_format($folderTag->leads_count) }}</span>
                            </div>
                            <div class="mt-5">
                                <h3 class="text-base font-semibold text-gray-900 group-hover:text-[#063A1C]">{{ $folderTag->name }}</h3>
                                <p class="mt-1 flex items-center gap-2 text-sm text-gray-500">Smart folder <i class="folder-open-icon fas fa-arrow-right text-xs text-emerald-700"></i></p>
                            </div>
                        </a>
                        <div class="lead-bank-folder-actions">
                            <button type="button" class="lead-bank-coming-soon-action lead-bank-folder-whatsapp" data-lead-bank-coming-soon>
                                <i class="fab fa-whatsapp text-sm"></i>
                                <span>Send Bulk WhatsApp</span>
                            </button>
                            <form method="POST" action="{{ route('lead-bank.tags.destroy', $folderTag) }}" data-folder-delete-form data-folder-name="{{ $folderTag->name }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="lead-bank-folder-delete" title="Delete folder" aria-label="Delete folder {{ $folderTag->name }}">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="lead-bank-delete-modal" data-folder-delete-modal aria-hidden="true">
            <div class="lead-bank-delete-dialog" role="dialog" aria-modal="true" aria-labelledby="leadBankDeleteTitle">
                <div class="p-6">
                    <div class="flex items-start gap-4">
                        <span class="lead-bank-delete-icon">
                            <i class="fas fa-trash"></i>
                        </span>
                        <div class="min-w-0">
                            <h3 id="leadBankDeleteTitle" class="text-lg font-bold text-gray-900">Delete folder?</h3>
                            <p class="mt-2 text-sm leading-6 text-gray-600">
                                <span class="font-semibold text-gray-900" data-folder-delete-name>Selected folder</span> will be hidden from Lead folders. Leads and tag assignments will not be deleted.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="flex flex-col-reverse gap-3 border-t border-gray-100 bg-slate-50 px-6 py-4 sm:flex-row sm:justify-end">
                    <button type="button" class="lead-bank-delete-cancel rounded-xl px-4 py-2 text-sm font-bold" data-folder-delete-cancel>Cancel</button>
                    <button type="button" class="lead-bank-delete-confirm rounded-xl px-4 py-2 text-sm font-bold" data-folder-delete-confirm>Delete folder</button>
                </div>
            </div>
        </div>
    </div>
@endsection



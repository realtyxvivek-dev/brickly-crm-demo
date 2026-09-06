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
    .lead-bank-inventory-layout { display: grid; grid-template-columns: 240px minmax(0, 1fr); }
    .lead-bank-folder-link { transition: background .16s ease, color .16s ease, transform .16s ease; }
    .lead-bank-folder-link:hover { transform: translateX(2px); }
    .lead-bank-folder-link.is-active { background: #f0f7f3; color: #063A1C; box-shadow: inset 0 0 0 1px #d9e9df; }
    .lead-bank-folder-delete-action { display: inline-flex; align-items: center; justify-content: center; gap: 8px; border-radius: 12px; border: 1px solid #fecdd3; background: #fff1f2; color: #be123c; padding: 10px 14px; font-size: 13px; font-weight: 800; transition: transform .18s ease, background .18s ease, border-color .18s ease; }
    .lead-bank-folder-delete-action:hover { transform: translateY(-1px); background: #ffe4e6; border-color: #fda4af; }
    .lead-bank-delete-modal { position: fixed; inset: 0; z-index: 80; display: none; align-items: center; justify-content: center; padding: 18px; background: rgba(15, 23, 42, .52); backdrop-filter: blur(4px); }
    .lead-bank-delete-modal.is-open { display: flex; }
    .lead-bank-delete-dialog { width: min(440px, 100%); border-radius: 24px; background: #fff; box-shadow: 0 28px 80px rgba(15, 23, 42, .28); overflow: hidden; transform: translateY(8px) scale(.98); opacity: 0; transition: transform .18s ease, opacity .18s ease; }
    .lead-bank-delete-modal.is-open .lead-bank-delete-dialog { transform: translateY(0) scale(1); opacity: 1; }
    .lead-bank-delete-icon { width: 48px; height: 48px; border-radius: 16px; display: inline-flex; align-items: center; justify-content: center; background: #fff1f2; color: #be123c; }
    .lead-bank-delete-cancel { border: 1px solid #e2e8f0; background: #fff; color: #334155; }
    .lead-bank-delete-cancel:hover { background: #f8fafc; }
    .lead-bank-delete-confirm { background: #be123c; color: #fff; box-shadow: 0 10px 22px rgba(190, 18, 60, .22); }
    .lead-bank-delete-confirm:hover { background: #9f1239; }
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
            <div class="flex items-center gap-3">
                <a href="{{ route('lead-bank.index') }}" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-600 shadow-sm hover:bg-emerald-50 hover:text-emerald-800" aria-label="Back to folders"><i class="fas fa-arrow-left"></i></a>
                <div><h1 class="text-2xl font-bold text-[#063A1C] sm:text-3xl">{{ $activeFolder['label'] }}</h1><p class="mt-1 text-sm text-gray-500">Folder leads and counts are scoped to this folder.</p></div>
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

        <div class="lead-bank-card overflow-hidden rounded-3xl bg-white">
            <div class="relative bg-gradient-to-br from-slate-50 via-white to-emerald-50/50 px-6 py-5">
                <div class="absolute left-6 top-0 h-3 w-28 rounded-b-2xl" style="background-color: {{ $activeFolder['color'] }}"></div>
                <div class="flex flex-col gap-4 pt-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-4">
                        <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl text-2xl text-white shadow-sm" style="background-color: {{ $activeFolder['color'] }}">
                            <i class="fas {{ $activeFolder['icon'] }}"></i>
                        </span>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $activeFolder['description'] }}</p>
                            <h2 class="mt-1 text-xl font-bold text-gray-900">{{ $activeFolder['label'] }}</h2>
                            <p class="mt-1 text-sm text-gray-500">Only leads from this folder are shown below.</p>
                        </div>
                    </div>
                    <div class="flex flex-col items-stretch gap-2 sm:items-end">
                        <div class="rounded-2xl bg-white px-5 py-3 text-right shadow-sm ring-1 ring-gray-100">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Folder leads</p>
                            <p class="text-2xl font-bold text-gray-900">{{ number_format($stats['total']) }}</p>
                        </div>
                        @if($activeFolder['is_custom'] ?? false)
                            <form method="POST" action="{{ route('lead-bank.tags.destroy', $activeFolder['tag_id']) }}" data-folder-delete-form data-folder-name="{{ $activeFolder['label'] }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="lead-bank-folder-delete-action">
                                    <i class="fas fa-trash"></i>
                                    Delete Folder
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>

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
        </div>`n        <div class="lead-bank-card rounded-2xl bg-white p-5">
            <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700"><i class="fas fa-magnifying-glass"></i></span>
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Find leads</h2>
                        <p class="text-sm text-gray-500">Search or narrow the inventory using filters.</p>
                    </div>
                </div>
                @if(collect($filters)->except('scope')->filter(fn ($value) => $value !== null && $value !== '')->isNotEmpty() || ($filters['scope'] ?? 'all') !== 'all')
                    <a href="{{ url()->current() }}" class="text-sm font-semibold text-emerald-800 hover:text-emerald-900">Clear all filters</a>
                @endif
            </div>
            <form method="GET" action="{{ url()->current() }}" class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-12 xl:items-end">
                <input type="hidden" name="scope" value="{{ $filters['scope'] ?? 'all' }}">
                <div class="xl:col-span-4">
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Search</label>
                    <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Name, phone, tag, owner" class="lead-bank-field w-full rounded-lg px-3 text-sm">
                </div>
                <div class="xl:col-span-2">
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Availability</label>
                    <select name="availability" class="w-full rounded-lg border-gray-300 text-sm focus:border-[#205A44] focus:ring-[#205A44]">
                        <option value="">All</option>
                        <option value="available" @selected(($filters['availability'] ?? '') === 'available')>Available</option>
                        <option value="assigned" @selected(($filters['availability'] ?? '') === 'assigned')>Assigned</option>
                        <option value="cooling" @selected(($filters['availability'] ?? '') === 'cooling')>Cooling</option>
                        <option value="protected" @selected(($filters['availability'] ?? '') === 'protected')>Protected</option>
                        <option value="blocked" @selected(($filters['availability'] ?? '') === 'blocked')>Blocked</option>
                    </select>
                </div>
                <div class="xl:col-span-2">
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">City</label>
                    <select name="city" class="w-full rounded-lg border-gray-300 text-sm focus:border-[#205A44] focus:ring-[#205A44]">
                        <option value="">All cities</option>
                        @foreach($cities as $city)
                            <option value="{{ $city }}" @selected(($filters['city'] ?? '') === $city)>{{ $city }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="xl:col-span-2">
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Source</label>
                    <select name="source" class="w-full rounded-lg border-gray-300 text-sm focus:border-[#205A44] focus:ring-[#205A44]">
                        <option value="">All sources</option>
                        @foreach($sources as $source)
                            <option value="{{ $source }}" @selected(($filters['source'] ?? '') === $source)>{{ strtoupper($source) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex gap-2 md:col-span-2 xl:col-span-12 xl:justify-end">
                    <a href="{{ url()->current() }}" class="inline-flex min-h-[42px] items-center justify-center rounded-xl bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-200">Reset</a>
                    <button type="submit" class="inline-flex min-h-[42px] items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-[#063A1C] to-[#205A44] px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:from-[#205A44] hover:to-[#15803d]"><i class="fas fa-filter text-xs"></i>Apply filters</button>
                </div>
            </form>
        </div>

        <div class="lead-bank-card overflow-hidden rounded-2xl bg-white">
            <div class="px-6 py-4 border-b border-gray-100 flex flex-col gap-1 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">{{ $activeFolder['label'] }} leads</h2>
                    <p class="text-sm text-gray-500">Only leads in this folder are listed here.</p>
                </div>
                <span class="w-fit rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ number_format($leads->total()) }} lead(s)</span>
            </div>
<form method="POST" action="{{ route('lead-bank.bulk-tags') }}" id="leadBankBulkTagForm">
                @csrf
                <div class="border-b border-gray-100 bg-slate-50/70 px-6 py-4">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-5 md:items-end">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Bulk Tag</label>
                            <select name="tag_id" class="w-full rounded-lg border-gray-300 text-sm focus:border-[#205A44] focus:ring-[#205A44]" required>
                                <option value="">Select tag</option>
                                @foreach($tags as $tag)
                                    <option value="{{ $tag->id }}">{{ $tag->name }} · {{ ucfirst($tag->type) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Action</label>
                            <select name="action" class="w-full rounded-lg border-gray-300 text-sm focus:border-[#205A44] focus:ring-[#205A44]">
                                <option value="apply">Apply tag</option>
                                <option value="remove">Remove tag</option>
                            </select>
                        </div>
                        <div class="flex gap-2 md:col-span-2">
                            <button type="button" id="leadBankSelectVisible" class="flex-1 rounded-lg bg-white px-4 py-2 text-sm font-medium text-gray-700 ring-1 ring-gray-200 hover:bg-gray-100">Select Visible</button>
                            <button type="submit" class="flex-1 rounded-lg bg-gradient-to-r from-[#063A1C] to-[#205A44] px-4 py-2 text-sm font-medium text-white hover:from-[#205A44] hover:to-[#15803d]">Apply to Selected</button>
                        </div>
                    </div>
                </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <input type="checkbox" id="leadBankCheckAll" class="rounded border-gray-300 text-[#205A44] focus:ring-[#205A44]">
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lead</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Source</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tags</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Availability</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Owner</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Imported</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($leads as $lead)
                            @php
                                $state = $lead->lead_bank_state;
                                $badgeClass = match($state['state']) {
                                    'available' => 'bg-emerald-100 text-emerald-800',
                                    'assigned' => 'bg-indigo-100 text-indigo-800',
                                    'cooling' => 'bg-amber-100 text-amber-800',
                                    'protected' => 'bg-blue-100 text-blue-800',
                                    default => 'bg-rose-100 text-rose-800',
                                };
                                $status = trim((string) ($lead->status ?? ''));
                                $statusLabel = $status !== '' ? ucwords(str_replace(['_', '-'], ' ', $status)) : 'No status';
                                $statusClass = match(true) {
                                    in_array($status, ['interested', 'qualified', 'site_visit_scheduled', 'site_visit_completed', 'closed_won', 'closed', 'converted'], true) => 'bg-emerald-100 text-emerald-800',
                                    in_array($status, ['connected', 'follow_up', 'follow-up', 'negotiation'], true) => 'bg-blue-100 text-blue-800',
                                    in_array($status, ['not_interested', 'not interested', 'not-reachable', 'not_reachable'], true) => 'bg-amber-100 text-amber-800',
                                    in_array($status, ['dead', 'junk', 'duplicate', 'invalid', 'wrong_number', 'dnd', 'closed_lost'], true) => 'bg-rose-100 text-rose-800',
                                    default => 'bg-slate-100 text-slate-700',
                                };
                                $latestAssignment = $lead->latestAssignment;
                                $activeAssignment = $lead->activeAssignments->first();
                                $ownerName = $activeAssignment?->assignedTo?->name
                                    ?? $latestAssignment?->assignedTo?->name
                                    ?? null;
                                $ownerContext = $activeAssignment
                                    ? 'Current owner'
                                    : ($latestAssignment ? 'Previous owner' : 'Never assigned');
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <input type="checkbox" name="lead_ids[]" value="{{ $lead->id }}" class="lead-bank-row-check rounded border-gray-300 text-[#205A44] focus:ring-[#205A44]">
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-semibold text-gray-900">{{ $lead->name }}</div>
                                    <div class="text-sm text-gray-500">{{ $lead->phone }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $statusClass }}">{{ $statusLabel }}</span>
                                    <div class="mt-1 text-xs text-gray-500">Current status</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ strtoupper($lead->source ?: 'N/A') }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex max-w-xs flex-wrap gap-1">
                                        @forelse($lead->leadTags as $tag)
                                            <span class="rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700">{{ $tag->name }}</span>
                                        @empty
                                            <span class="text-sm text-gray-400">No tags</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $badgeClass }}">{{ ucfirst($state['state']) }}</span>
                                    <div class="mt-1 max-w-xs text-xs text-gray-500">{{ $state['label'] }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ $ownerName ?: 'Never assigned' }}</div>
                                    <div class="text-xs text-gray-500">{{ $ownerContext }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                    @if($lead->latestImportedLead?->importBatch)
                                        {{ $lead->latestImportedLead->importBatch->created_at->format('d M Y') }}
                                    @else
                                        Manual / existing
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <a href="{{ route('leads.show', $lead->id) }}" class="font-medium text-brand-secondary hover:text-brand-primary">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-6 py-12 text-center text-gray-500">No leads found for the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-100 px-6 py-4">
                {{ $leads->links() }}
            </div>
            </form>
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

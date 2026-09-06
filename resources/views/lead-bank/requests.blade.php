@extends(auth()->check() && (auth()->user()->isAssistantSalesManager() || auth()->user()->isSeniorManager() || auth()->user()->isSalesManager()) ? 'sales-manager.layout' : 'layouts.app')

@section('title', 'Lead Requests - ' . brand_name())
@section('page-title', $isAdminQueue ? 'Lead Request Queue' : 'Request Lead Bank Leads')
@section('page-subtitle', $isAdminQueue ? 'Review manager demand with simulated inventory matching' : 'Create temporary lead requests with live match preview')

@section('header-actions')
    @if(auth()->user()->isAdmin() || auth()->user()->isCrm())
        <a href="{{ route('lead-bank.index') }}" class="px-4 py-2 rounded-lg bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 transition-colors duration-200 text-sm font-medium">
            Lead Bank
        </a>
    @endif
@endsection

@push('styles')
<style>
    .lead-request-shell {
        --lr-green: #063A1C;
        --lr-green-soft: #205A44;
        --lr-border: rgba(15, 23, 42, .08);
        --lr-muted: #667085;
    }

    .lead-request-card {
        border: 1px solid var(--lr-border);
        box-shadow: 0 16px 40px rgba(15, 45, 34, .07);
    }

    .lead-request-input {
        width: 100%;
        min-height: 44px;
        border: 1px solid #d8ded9;
        border-radius: 12px;
        background: #fff;
        color: #071b13;
        padding: 10px 12px;
        font-size: 14px;
        transition: border-color .18s ease, box-shadow .18s ease, background .18s ease;
    }

    .lead-request-input:focus {
        border-color: var(--lr-green-soft);
        box-shadow: 0 0 0 3px rgba(32, 90, 68, .12);
        outline: none;
    }

    .lead-request-label {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 7px;
        color: #344054;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .lead-request-tag-grid {
        max-height: 150px;
        overflow: auto;
        border: 1px solid #d8ded9;
        border-radius: 14px;
        background: #fbfcfb;
        padding: 10px;
    }

    .lead-request-tag-option {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        border: 1px solid #e6ebe8;
        border-radius: 999px;
        background: #fff;
        padding: 8px 10px;
        font-size: 13px;
        color: #1f2937;
        cursor: pointer;
    }

    .lead-request-tag-option input {
        width: 15px;
        height: 15px;
        accent-color: var(--lr-green);
        flex: 0 0 auto;
    }

    .lead-request-action {
        min-height: 42px;
        border-radius: 12px;
        padding: 10px 16px;
        font-size: 14px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .lead-request-empty {
        min-height: 154px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        color: #98a2b3;
    }

    .lead-approval-dialog {
        max-height: 92vh;
        width: min(100%, 460px);
        overflow: hidden;
        border-radius: 18px;
        background: #fff;
        box-shadow: 0 24px 70px rgba(15, 23, 42, .24);
        transition: width .18s ease;
    }

    .lead-approval-dialog.is-manual {
        width: min(100%, 1152px);
    }

    .lead-approval-dialog.is-folder {
        width: min(100%, 760px);
    }

    .lead-approval-body {
        max-height: calc(92vh - 88px);
        overflow-y: auto;
    }

    .lead-approval-dialog.is-manual .lead-approval-body {
        display: grid;
        grid-template-columns: minmax(320px, 380px) minmax(0, 1fr);
    }

    .lead-approval-side {
        padding: 22px 24px 24px;
    }

    .lead-approval-dialog.is-manual .lead-approval-side {
        border-right: 1px solid #eef2f0;
    }

    .lead-approval-summary {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 10px;
    }

    .lead-approval-chip {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        background: #f3f7f5;
        color: #475467;
        font-size: 12px;
        font-weight: 700;
        padding: 6px 10px;
    }

    .lead-approval-mode {
        width: 100%;
        border: 1px solid #e5e7eb;
        background: #fff;
        color: #475467;
        justify-content: flex-start;
    }

    .lead-approval-mode.is-active {
        border-color: #a7f3d0;
        background: #ecfdf3;
        color: #065f46;
        box-shadow: inset 0 0 0 1px #bbf7d0;
    }

    .lead-folder-preview {
        border: 1px solid #d9f99d;
        background: linear-gradient(135deg, #f7fee7 0%, #ffffff 100%);
    }

    @media (max-width: 767px) {
        .lead-request-action {
            width: 100%;
        }

        .lead-approval-dialog.is-manual .lead-approval-body {
            display: block;
        }

        .lead-approval-dialog.is-manual .lead-approval-side {
            border-right: 0;
            border-bottom: 1px solid #eef2f0;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const previewButton = document.getElementById('leadRequestPreviewButton');
        const form = document.getElementById('leadRequestForm');
        const previewPanel = document.getElementById('leadRequestPreviewPanel');
        const previewBody = document.getElementById('leadRequestPreviewBody');

        function formPayload() {
            const data = new FormData(form);
            return new URLSearchParams(data);
        }

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function rowsFromObject(items) {
            const entries = Object.entries(items || {});
            if (!entries.length) {
                return '<div class="text-xs text-gray-500">No breakdown available.</div>';
            }

            return entries.map(([label, total]) => `
                <div class="flex items-center justify-between text-sm py-1">
                    <span class="text-gray-600">${escapeHtml(label)}</span>
                    <span class="font-semibold text-gray-900">${escapeHtml(total)}</span>
                </div>
            `).join('');
        }

        if (previewButton && form && previewPanel && previewBody) {
            previewButton.addEventListener('click', async function () {
                previewButton.disabled = true;
                previewButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking...';
                previewPanel.classList.remove('hidden');
                previewBody.innerHTML = '<div class="text-sm text-gray-500">Calculating available inventory...</div>';

                try {
                    const response = await fetch('{{ route('lead-bank.requests.preview') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json',
                        },
                        body: formPayload(),
                    });

                    const data = await response.json();
                    if (!response.ok) {
                        throw new Error(data.message || 'Preview failed.');
                    }

                    previewBody.innerHTML = `
                        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                            <div class="rounded-lg border border-gray-100 p-4">
                                <div class="text-xs text-gray-500 uppercase tracking-wide">Matching Leads</div>
                                <div class="mt-1 text-2xl font-bold text-gray-900">${data.matched_count}</div>
                            </div>
                            <div class="rounded-lg border border-emerald-100 bg-emerald-50 p-4">
                                <div class="text-xs text-emerald-700 uppercase tracking-wide">Will Allocate</div>
                                <div class="mt-1 text-2xl font-bold text-emerald-900">${data.will_allocate}</div>
                            </div>
                            <div class="rounded-lg border border-amber-100 bg-amber-50 p-4">
                                <div class="text-xs text-amber-700 uppercase tracking-wide">Shortfall</div>
                                <div class="mt-1 text-2xl font-bold text-amber-900">${data.shortfall}</div>
                            </div>
                            <div class="rounded-lg border border-gray-100 p-4">
                                <div class="text-xs text-gray-500 uppercase tracking-wide">Excluded</div>
                                <div class="mt-1 text-sm font-semibold text-gray-900">A:${data.excluded.assigned} C:${data.excluded.cooling} B:${data.excluded.blocked} P:${data.excluded.protected}</div>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-4">
                            <div class="rounded-lg border border-gray-100 p-4">
                                <div class="text-sm font-semibold text-gray-900 mb-2">City Mix</div>
                                ${rowsFromObject(data.city_breakdown)}
                            </div>
                            <div class="rounded-lg border border-gray-100 p-4">
                                <div class="text-sm font-semibold text-gray-900 mb-2">Source Mix</div>
                                ${rowsFromObject(data.source_breakdown)}
                            </div>
                        </div>
                    `;
                } catch (error) {
                    previewBody.innerHTML = `<div class="text-sm text-rose-700">${error.message}</div>`;
                } finally {
                    previewButton.disabled = false;
                    previewButton.innerHTML = '<i class="fas fa-magnifying-glass-chart"></i> Preview Match';
                }
            });
        }

        const approvalModal = document.getElementById('leadApprovalModal');
        const approvalDialog = document.getElementById('leadApprovalDialog');
        const approvalSummary = document.getElementById('leadApprovalSummary');
        const approvalQuantity = document.getElementById('leadApprovalQuantity');
        const approvalAutoForm = document.getElementById('leadApprovalAutoForm');
        const approvalFolderForm = document.getElementById('leadApprovalFolderForm');
        const folderPanel = approvalFolderForm;
        const folderQuantity = document.getElementById('leadFolderApprovalQuantity');
        const folderSelector = document.getElementById('leadFolderSelector');
        const folderPriority = document.getElementById('leadFolderApprovalPriority');
        const folderNote = document.getElementById('leadFolderApprovalNote');
        const folderType = document.getElementById('leadFolderType');
        const folderKey = document.getElementById('leadFolderKey');
        const folderTagId = document.getElementById('leadFolderTagId');
        const folderPreviewButton = document.getElementById('leadFolderPreviewButton');
        const folderPreviewBox = document.getElementById('leadFolderPreviewBox');
        const folderPreviewBody = document.getElementById('leadFolderPreviewBody');
        const approvalManualForm = document.getElementById('leadApprovalManualForm');
        const manualPanel = document.getElementById('leadApprovalManualPanel');
        const manualToggle = document.getElementById('leadApprovalManualToggle');
        const manualRows = document.getElementById('manualCandidateRows');
        const manualStatus = document.getElementById('manualCandidateStatus');
        const manualSelectedCount = document.getElementById('manualSelectedCount');
        const manualApply = document.getElementById('manualFilterApply');
        const manualSubmit = document.getElementById('manualAssignSubmit');
        const manualSelectTop = document.getElementById('manualSelectTop');
        const manualSelectTopCount = document.getElementById('manualSelectTopCount');
        const approvalPriority = document.getElementById('leadApprovalPriority');
        const approvalNote = document.getElementById('leadApprovalNote');
        const manualPriority = document.getElementById('manualApprovalPriority');
        const manualNote = document.getElementById('manualApprovalNote');
        const modeButtons = document.querySelectorAll('[data-approval-mode]');
        let activeApprovalRequest = null;

        function openApprovalModal(button) {
            activeApprovalRequest = {
                id: button.dataset.requestId,
                requester: button.dataset.requester || 'Requester',
                quantity: Number(button.dataset.quantity || 0),
                defaultQuantity: Number(button.dataset.defaultQuantity || 1),
                matched: Number(button.dataset.matched || 0),
                allocatable: Number(button.dataset.allocatable || 0),
                expiry: Number(button.dataset.expiry || 0),
                autoUrl: button.dataset.autoUrl,
                manualUrl: button.dataset.manualUrl,
                candidatesUrl: button.dataset.candidatesUrl,
            };

            approvalSummary.innerHTML = `
                <span class="lead-approval-chip">Request #${escapeHtml(activeApprovalRequest.id)}</span>
                <span class="lead-approval-chip">${escapeHtml(activeApprovalRequest.requester)}</span>
                <span class="lead-approval-chip">${activeApprovalRequest.quantity} requested</span>
                <span class="lead-approval-chip">${activeApprovalRequest.allocatable} allocatable</span>
                <span class="lead-approval-chip">${activeApprovalRequest.expiry} day access</span>
            `;
            approvalQuantity.value = activeApprovalRequest.defaultQuantity || 1;
            approvalQuantity.max = activeApprovalRequest.quantity || 1;
            if (folderQuantity) {
                folderQuantity.value = activeApprovalRequest.defaultQuantity || 1;
                folderQuantity.max = activeApprovalRequest.quantity || 1;
            }
            if (manualSelectTopCount) {
                manualSelectTopCount.value = activeApprovalRequest.defaultQuantity || 1;
                manualSelectTopCount.max = activeApprovalRequest.quantity || 1;
            }
            if (approvalPriority) approvalPriority.value = 'normal';
            if (approvalNote) approvalNote.value = '';
            if (folderPriority) folderPriority.value = 'normal';
            if (folderNote) folderNote.value = '';
            approvalAutoForm.action = activeApprovalRequest.autoUrl;
            if (approvalFolderForm) approvalFolderForm.action = activeApprovalRequest.autoUrl;
            approvalManualForm.action = activeApprovalRequest.manualUrl;
            approvalDialog?.classList.remove('is-manual', 'is-folder');
            setApprovalMode('auto');
            manualPanel.classList.add('hidden');
            folderPanel?.classList.add('hidden');
            if (folderPreviewBox) folderPreviewBox.classList.add('hidden');
            manualRows.innerHTML = '<tr><td colspan="9" class="px-4 py-8 text-center text-sm text-gray-500">No leads loaded.</td></tr>';
            manualStatus.textContent = 'Load matching leads to select manually.';
            updateSelectedCount();

            approvalModal.classList.remove('hidden');
            approvalModal.classList.add('flex');
        }

        function closeApprovalModal() {
            approvalModal?.classList.add('hidden');
            approvalModal?.classList.remove('flex');
            activeApprovalRequest = null;
        }

        function manualFilters() {
            const params = new URLSearchParams();
            [
                ['search', document.getElementById('manualFilterSearch')?.value || ''],
                ['city', document.getElementById('manualFilterCity')?.value || ''],
                ['source', document.getElementById('manualFilterSource')?.value || ''],
                ['status', document.getElementById('manualFilterStatus')?.value || ''],
                ['tag_id', document.getElementById('manualFilterTag')?.value || ''],
            ].forEach(([key, value]) => {
                if (value) params.set(key, value);
            });
            return params;
        }

        function setApprovalMode(mode) {
            modeButtons.forEach((button) => {
                button.classList.toggle('is-active', button.dataset.approvalMode === mode);
            });
            approvalDialog?.classList.toggle('is-manual', mode === 'manual');
            approvalDialog?.classList.toggle('is-folder', mode === 'folder');
            approvalAutoForm?.classList.toggle('hidden', mode !== 'auto');
            folderPanel?.classList.toggle('hidden', mode !== 'folder');
            manualPanel?.classList.toggle('hidden', mode !== 'manual');
            if (mode === 'manual') {
                loadManualCandidates();
            }
            if (mode === 'folder') {
                loadFolderPreview();
            }
        }

        function selectedFolderParts() {
            const [type, value] = String(folderSelector?.value || 'system:all').split(':');
            return {
                folder_type: type === 'tag' ? 'tag' : 'system',
                folder_key: type === 'tag' ? '' : (value === 'unassigned' ? 'unassigned' : 'all'),
                folder_tag_id: type === 'tag' ? value : '',
            };
        }

        function syncFolderInputs() {
            const parts = selectedFolderParts();
            if (folderType) folderType.value = parts.folder_type;
            if (folderKey) folderKey.value = parts.folder_key;
            if (folderTagId) folderTagId.value = parts.folder_tag_id;
            return parts;
        }

        async function loadFolderPreview() {
            if (!activeApprovalRequest || !folderPreviewBody) return;
            const parts = syncFolderInputs();
            const params = new URLSearchParams();
            params.set('quantity', folderQuantity?.value || activeApprovalRequest.defaultQuantity || 1);
            params.set('allocation_mode', 'folder');
            params.set('folder_type', parts.folder_type);
            if (parts.folder_key) params.set('folder_key', parts.folder_key);
            if (parts.folder_tag_id) params.set('folder_tag_id', parts.folder_tag_id);

            folderPreviewBox?.classList.remove('hidden');
            folderPreviewBody.innerHTML = '<div class="text-sm text-gray-500">Folder inventory checking...</div>';
            if (folderPreviewButton) {
                folderPreviewButton.disabled = true;
                folderPreviewButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking';
            }

            try {
                const response = await fetch('{{ route('lead-bank.requests.preview') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: params,
                });
                const data = await response.json();
                if (!response.ok) throw new Error(data.message || 'Folder preview failed.');

                folderPreviewBody.innerHTML = `
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <div class="text-xs uppercase tracking-wide text-gray-500">Matched</div>
                            <div class="text-xl font-bold text-gray-900">${data.matched_count}</div>
                        </div>
                        <div>
                            <div class="text-xs uppercase tracking-wide text-emerald-700">Will allocate</div>
                            <div class="text-xl font-bold text-emerald-800">${data.will_allocate}</div>
                        </div>
                        <div>
                            <div class="text-xs uppercase tracking-wide text-amber-700">Shortfall</div>
                            <div class="text-xl font-bold text-amber-800">${data.shortfall}</div>
                        </div>
                    </div>
                    <div class="mt-3 text-xs text-gray-600">Excluded: assigned ${data.excluded.assigned}, cooling ${data.excluded.cooling}, blocked ${data.excluded.blocked}, protected ${data.excluded.protected}</div>
                `;
            } catch (error) {
                folderPreviewBody.innerHTML = `<div class="text-sm text-rose-700">${escapeHtml(error.message)}</div>`;
            } finally {
                if (folderPreviewButton) {
                    folderPreviewButton.disabled = false;
                    folderPreviewButton.innerHTML = '<i class="fas fa-rotate"></i> Refresh Preview';
                }
            }
        }

        async function loadManualCandidates() {
            if (!activeApprovalRequest) return;

            manualRows.innerHTML = '<tr><td colspan="9" class="px-4 py-8 text-center text-sm text-gray-500">Loading available leads...</td></tr>';
            manualStatus.textContent = 'Checking availability...';

            try {
                const response = await fetch(`${activeApprovalRequest.candidatesUrl}?${manualFilters().toString()}`, {
                    headers: { 'Accept': 'application/json' },
                });
                const data = await response.json();
                if (!response.ok) {
                    throw new Error(data.message || 'Could not load matching leads.');
                }

                if (!data.leads.length) {
                    manualRows.innerHTML = '<tr><td colspan="9" class="px-4 py-8 text-center text-sm text-gray-500">No available leads match these filters.</td></tr>';
                } else {
                    manualRows.innerHTML = data.leads.map((lead) => `
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <input type="checkbox" name="lead_ids[]" value="${lead.id}" data-duplicate-risk="${lead.duplicate_risk ? '1' : '0'}" data-lead-name="${escapeHtml(lead.name)}" class="manual-lead-check rounded border-gray-300 text-[#205A44] focus:ring-[#205A44]">
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-semibold text-gray-900">${escapeHtml(lead.name)}</div>
                                <div class="text-xs text-gray-500">${escapeHtml(lead.phone || '-')}</div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">${escapeHtml(lead.source)}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">${escapeHtml(lead.city)}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">${escapeHtml(String(lead.status || 'new').replaceAll('_', ' '))}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">${escapeHtml(lead.last_owner)}</td>
                            <td class="px-4 py-3 text-xs">
                                <div class="${qualityClass(lead.quality_tone)}">${escapeHtml(lead.quality_label || 'Clean lead')}</div>
                                <div class="mt-1 text-gray-500">${(lead.quality_signals || []).map(escapeHtml).join(' / ') || 'No risk'}</div>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500">${lead.tags.length ? lead.tags.map(escapeHtml).join(', ') : 'No tags'}</td>
                            <td class="px-4 py-3 text-xs text-gray-500">${escapeHtml(lead.imported)}</td>
                        </tr>
                    `).join('');
                }

                manualStatus.textContent = `${data.total} lead(s) loaded${data.total >= data.limit ? ' (first 100 shown)' : ''}.`;
                updateSelectedCount();
            } catch (error) {
                manualRows.innerHTML = `<tr><td colspan="9" class="px-4 py-8 text-center text-sm text-rose-600">${escapeHtml(error.message)}</td></tr>`;
                manualStatus.textContent = 'Load failed.';
            }
        }

        function qualityClass(tone) {
            const classes = {
                emerald: 'inline-flex rounded-full bg-emerald-50 px-2 py-1 font-semibold text-emerald-700',
                amber: 'inline-flex rounded-full bg-amber-50 px-2 py-1 font-semibold text-amber-700',
                rose: 'inline-flex rounded-full bg-rose-50 px-2 py-1 font-semibold text-rose-700',
                slate: 'inline-flex rounded-full bg-slate-100 px-2 py-1 font-semibold text-slate-700',
            };

            return classes[tone] || classes.emerald;
        }

        function updateSelectedCount() {
            const selected = document.querySelectorAll('.manual-lead-check:checked').length;
            const requested = activeApprovalRequest?.quantity || 0;
            if (manualSelectedCount) {
                manualSelectedCount.textContent = `Selected ${selected} / Requested ${requested}`;
            }
            if (manualSubmit) {
                manualSubmit.disabled = selected === 0;
                manualSubmit.classList.toggle('opacity-50', selected === 0);
            }
        }

        function syncApprovalMeta() {
            if (manualPriority && approvalPriority) manualPriority.value = approvalPriority.value || 'normal';
            if (manualNote && approvalNote) manualNote.value = approvalNote.value || '';
        }

        function selectedManualChecks() {
            return Array.from(document.querySelectorAll('.manual-lead-check:checked'));
        }

        document.querySelectorAll('.lead-request-open-approval').forEach((button) => {
            button.addEventListener('click', () => openApprovalModal(button));
        });

        document.getElementById('leadApprovalModalClose')?.addEventListener('click', closeApprovalModal);
        approvalModal?.addEventListener('click', function (event) {
            if (event.target === approvalModal) closeApprovalModal();
        });

        modeButtons.forEach((button) => {
            button.addEventListener('click', () => setApprovalMode(button.dataset.approvalMode));
        });
        folderSelector?.addEventListener('change', loadFolderPreview);
        folderQuantity?.addEventListener('change', loadFolderPreview);
        folderPreviewButton?.addEventListener('click', loadFolderPreview);

        manualApply?.addEventListener('click', loadManualCandidates);
        manualSelectTop?.addEventListener('click', function () {
            const limit = Math.min(
                Number(manualSelectTopCount?.value || activeApprovalRequest?.defaultQuantity || 1),
                activeApprovalRequest?.quantity || 0
            );
            document.querySelectorAll('.manual-lead-check').forEach((check) => {
                check.checked = false;
            });
            Array.from(document.querySelectorAll('.manual-lead-check')).slice(0, limit).forEach((check) => {
                check.checked = true;
            });
            updateSelectedCount();
        });
        manualRows?.addEventListener('change', function (event) {
            if (!event.target.classList.contains('manual-lead-check')) return;
            if (document.querySelectorAll('.manual-lead-check:checked').length > (activeApprovalRequest?.quantity || 0)) {
                event.target.checked = false;
                alert('Selected leads cannot be more than requested quantity.');
            }
            updateSelectedCount();
        });

        approvalAutoForm?.addEventListener('submit', function (event) {
            syncApprovalMeta();
            const quantity = Number(approvalQuantity?.value || 0);
            const priority = approvalPriority?.value === 'urgent' ? 'Urgent' : 'Normal';
            const message = [
                `Auto assign ${quantity} Lead Bank lead(s)?`,
                `Requester: ${activeApprovalRequest?.requester || '-'}`,
                `Access: ${activeApprovalRequest?.expiry || 0} day(s)`,
                `Priority: ${priority}`,
            ].join('\n');

            if (!confirm(message)) {
                event.preventDefault();
            }
        });

        approvalFolderForm?.addEventListener('submit', function (event) {
            syncFolderInputs();
            const quantity = Number(folderQuantity?.value || 0);
            const folderLabel = folderSelector?.selectedOptions?.[0]?.textContent?.trim() || 'Selected folder';
            const priority = folderPriority?.value === 'urgent' ? 'Urgent' : 'Normal';
            const message = [
                `Folder wise assign ${quantity} Lead Bank lead(s)?`,
                `Folder: ${folderLabel}`,
                `Requester: ${activeApprovalRequest?.requester || '-'}`,
                `Access: ${activeApprovalRequest?.expiry || 0} day(s)`,
                `Priority: ${priority}`,
            ].join('\n');

            if (!confirm(message)) {
                event.preventDefault();
            }
        });

        approvalManualForm?.addEventListener('submit', function (event) {
            syncApprovalMeta();
            const selectedChecks = selectedManualChecks();
            if (selectedChecks.length === 0) {
                event.preventDefault();
                alert('Select at least one lead to assign.');
                return;
            }

            const duplicateRiskCount = selectedChecks.filter((check) => check.dataset.duplicateRisk === '1').length;
            const priority = approvalPriority?.value === 'urgent' ? 'Urgent' : 'Normal';
            const message = [
                `Assign ${selectedChecks.length} selected lead(s)?`,
                `Requester: ${activeApprovalRequest?.requester || '-'}`,
                `Requested: ${activeApprovalRequest?.quantity || 0}`,
                `Duplicate risk selected: ${duplicateRiskCount}`,
                `Priority: ${priority}`,
            ].join('\n');

            if (!confirm(message)) {
                event.preventDefault();
            }
        });
    });
</script>
@endpush

@section('content')
    @php
        $usesManagerShell = auth()->check() && (auth()->user()->isAssistantSalesManager() || auth()->user()->isSeniorManager() || auth()->user()->isSalesManager());
        $simpleRequester = auth()->check() && (auth()->user()->isAssistantSalesManager() || auth()->user()->isSeniorManager()) && !$isAdminQueue;
    @endphp

    <div class="lead-request-shell space-y-6">
        @if($usesManagerShell)
            <div class="lead-request-card rounded-2xl bg-white/90 p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[.16em] text-[#205A44]">Lead Bank</p>
                        <h1 class="mt-1 text-2xl font-bold text-[#063A1C]">Lead Requests</h1>
                    </div>
                </div>
            </div>
        @endif

        @if(session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('success') }}
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

        <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 items-start">
            <div class="xl:col-span-4 lead-request-card bg-white rounded-2xl p-6">
                <div class="flex items-start justify-between gap-4 mb-5">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">Create Lead Request</h3>
                    </div>
                    <div class="w-11 h-11 rounded-xl bg-emerald-50 flex items-center justify-center text-[#205A44]">
                        <i class="fas fa-layer-group"></i>
                    </div>
                </div>

                <form method="POST" action="{{ route('lead-bank.requests.store') }}" id="leadRequestForm" class="space-y-5">
                    @csrf
                    <div>
                        <label class="lead-request-label"><i class="fas fa-list-ol"></i> Quantity</label>
                        <input type="number" name="quantity" min="1" max="5000" value="{{ old('quantity', 100) }}" class="lead-request-input" required>
                    </div>

                    @unless($simpleRequester)
                        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-1 gap-4">
                            <div>
                                <label class="lead-request-label"><i class="fas fa-location-dot"></i> City</label>
                                <select name="city" class="lead-request-input">
                                    <option value="">Any city</option>
                                    @foreach($cities as $city)
                                        <option value="{{ $city }}" @selected(old('city') === $city)>{{ $city }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="lead-request-label"><i class="fas fa-bullhorn"></i> Source</label>
                                <select name="source" class="lead-request-input">
                                    <option value="">Any source</option>
                                    @foreach($sources as $source)
                                        <option value="{{ $source }}" @selected(old('source') === $source)>{{ $source }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="lead-request-label"><i class="fas fa-tags"></i> Tags</label>
                            @if($tags->isNotEmpty())
                                <div class="lead-request-tag-grid">
                                    <div class="grid grid-cols-1 gap-2">
                                        @foreach($tags as $tag)
                                            <label class="lead-request-tag-option">
                                                <span class="truncate">{{ $tag->name }}</span>
                                                <span class="inline-flex items-center gap-2">
                                                    <span class="text-xs text-gray-400">{{ number_format($tag->leads_count) }}</span>
                                                    <input type="checkbox" name="tag_ids[]" value="{{ $tag->id }}" @checked(in_array($tag->id, old('tag_ids', [])))>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                <div class="rounded-xl border border-dashed border-gray-200 bg-gray-50 px-4 py-5 text-sm text-gray-500">
                                    No tags created yet.
                                </div>
                            @endif
                        </div>
                    @endunless

                    <div>
                        <label class="lead-request-label"><i class="fas fa-clock"></i> Temporary Access Days</label>
                        <input type="number" name="expiry_days" min="1" max="90" value="{{ old('expiry_days', 7) }}" class="lead-request-input" required>
                    </div>

                    @unless($simpleRequester)
                        <div>
                            <label class="lead-request-label"><i class="fas fa-note-sticky"></i> Reason</label>
                            <textarea name="reason" rows="3" class="lead-request-input min-h-[92px] resize-y" placeholder="Campaign, locality, team need, or follow-up context">{{ old('reason') }}</textarea>
                        </div>
                    @endunless

                    <div class="flex flex-wrap items-center gap-3 pt-2">
                        @unless($simpleRequester)
                            <button type="button" id="leadRequestPreviewButton" class="lead-request-action border border-gray-200 bg-white text-gray-700 hover:bg-gray-50">
                                <i class="fas fa-magnifying-glass-chart"></i>
                                Preview Match
                            </button>
                        @endunless
                        <button type="submit" class="lead-request-action bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white hover:from-[#205A44] hover:to-[#15803d]">
                            <i class="fas fa-paper-plane"></i>
                            Submit Request
                        </button>
                    </div>
                </form>
            </div>

            <div class="xl:col-span-8 space-y-6">
                <div id="leadRequestPreviewPanel" class="hidden lead-request-card bg-white rounded-2xl p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-900">Simulated Match Preview</h3>
                        <span class="text-xs px-2 py-1 rounded-full bg-blue-50 text-blue-700">No allocation yet</span>
                    </div>
                    <div id="leadRequestPreviewBody"></div>
                </div>

                <div class="lead-request-card bg-white rounded-2xl overflow-hidden">
                    <div class="px-6 py-5 border-b border-gray-100 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900">{{ $isAdminQueue ? 'Approval Queue' : 'My Requests' }}</h3>
                        </div>
                        @if($isAdminQueue)
                            <form method="POST" action="{{ route('lead-bank.requests.recall-expired') }}" class="flex flex-wrap items-end gap-2" onsubmit="return confirm('Recall all expired, non-protected Lead Bank allocations?');">
                                @csrf
                                <label class="block">
                                    <span class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-gray-500">Cooldown</span>
                                    <input type="number" name="cooldown_days" min="1" max="90" value="7" class="lead-request-input w-24 text-sm" title="Cooldown days">
                                </label>
                                <button type="submit" class="lead-request-action bg-gray-900 text-white hover:bg-gray-800">Recall Expired</button>
                            </form>
                        @endif
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Request</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Filters</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Preview</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($requests as $leadRequest)
                                    @php
                                        $badgeClass = match($leadRequest->status) {
                                            'pending' => 'bg-amber-50 text-amber-700',
                                            'approved', 'fulfilled' => 'bg-emerald-50 text-emerald-700',
                                            'partially_approved' => 'bg-blue-50 text-blue-700',
                                            'rejected', 'cancelled' => 'bg-rose-50 text-rose-700',
                                            default => 'bg-gray-50 text-gray-700',
                                        };
                                        $snapshot = $leadRequest->match_snapshot ?: [];
                                    @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4">
                                            <div class="font-semibold text-gray-900">#{{ $leadRequest->id }} · {{ number_format($leadRequest->quantity) }} leads</div>
                                            <div class="text-sm text-gray-500">
                                                {{ $leadRequest->requestedBy->name ?? 'User #' . $leadRequest->requested_by }}
                                                · {{ $leadRequest->created_at->format('d M Y, h:i A') }}
                                            </div>
                                            <div class="text-xs text-gray-500 mt-1">{{ $leadRequest->expiry_days }} day temp access</div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-700">{{ $leadRequest->city ?: 'Any city' }} · {{ $leadRequest->source ?: 'Any source' }}</div>
                                            <div class="text-xs text-gray-500 mt-1">
                                                Tags: {{ collect($leadRequest->tag_ids ?? [])->isEmpty() ? 'Any tag' : collect($leadRequest->tag_ids)->count() . ' selected' }}
                                            </div>
                                            @if($leadRequest->reason)
                                                <div class="text-xs text-gray-500 mt-1 max-w-xs truncate">{{ $leadRequest->reason }}</div>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-900">Matched: <span class="font-semibold">{{ number_format($leadRequest->matched_count) }}</span></div>
                                            <div class="text-sm text-gray-900">Will allocate: <span class="font-semibold">{{ number_format($leadRequest->allocatable_count) }}</span></div>
                                            @if(($snapshot['shortfall'] ?? 0) > 0)
                                                <div class="text-xs text-amber-700 mt-1">Shortfall: {{ number_format($snapshot['shortfall']) }}</div>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex px-2 py-1 rounded-full text-xs font-medium {{ $badgeClass }}">
                                                {{ ucwords(str_replace('_', ' ', $leadRequest->status)) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            @if($isAdminQueue && $leadRequest->status === 'pending')
                                                <div class="flex flex-col items-end gap-2 min-w-[190px]">
                                                    <button
                                                        type="button"
                                                        class="lead-request-open-approval rounded-lg bg-emerald-700 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-800"
                                                        data-request-id="{{ $leadRequest->id }}"
                                                        data-requester="{{ $leadRequest->requestedBy->name ?? 'User #' . $leadRequest->requested_by }}"
                                                        data-quantity="{{ $leadRequest->quantity }}"
                                                        data-default-quantity="{{ max(1, min($leadRequest->quantity, $leadRequest->allocatable_count ?: $leadRequest->quantity)) }}"
                                                        data-matched="{{ $leadRequest->matched_count }}"
                                                        data-allocatable="{{ $leadRequest->allocatable_count }}"
                                                        data-expiry="{{ $leadRequest->expiry_days }}"
                                                        data-auto-url="{{ route('lead-bank.requests.approve', $leadRequest) }}"
                                                        data-manual-url="{{ route('lead-bank.requests.manual-assign', $leadRequest) }}"
                                                        data-candidates-url="{{ route('lead-bank.requests.manual-candidates', $leadRequest) }}"
                                                    >
                                                        Approve
                                                    </button>
                                                    <form method="POST" action="{{ route('lead-bank.requests.reject', $leadRequest) }}" class="flex items-center gap-2" onsubmit="return confirm('Reject this lead request?');">
                                                        @csrf
                                                        <input type="text" name="rejection_reason" placeholder="Reason" class="w-28 rounded-lg border-gray-300 text-sm focus:border-[#205A44] focus:ring-[#205A44]">
                                                        <button type="submit" class="text-sm font-medium text-rose-600 hover:text-rose-700">Reject</button>
                                                    </form>
                                                </div>
                                            @elseif($leadRequest->status === 'pending' && ($leadRequest->requested_by === auth()->id() || auth()->user()->isAdmin() || auth()->user()->isCrm()))
                                                <form method="POST" action="{{ route('lead-bank.requests.cancel', $leadRequest) }}" onsubmit="return confirm('Cancel this lead request?');">
                                                    @csrf
                                                    <button type="submit" class="text-sm font-medium text-rose-600 hover:text-rose-700">Cancel</button>
                                                </form>
                                            @elseif($leadRequest->active_allocations_count > 0)
                                                <span class="text-xs font-medium text-emerald-700">{{ $leadRequest->active_allocations_count }} active</span>
                                            @else
                                                <span class="text-xs text-gray-400">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-6 py-14 text-center">
                                            <div class="lead-request-empty">
                                                <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-50 text-slate-400">
                                                    <i class="fas fa-clipboard-list text-xl"></i>
                                                </div>
                                                <div class="text-sm font-semibold text-gray-700">No lead requests yet</div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($requests->hasPages())
                        <div class="px-6 py-4 border-t border-gray-100">
                            {{ $requests->links() }}
                        </div>
                    @endif
                </div>

                @if($isAdminQueue)
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100">
                            <h3 class="text-lg font-semibold text-gray-900">Active Temporary Allocations</h3>
                            <p class="text-sm text-gray-500 mt-1">Manual recall keeps interested/protected leads with the manager and cools recalled leads for 7 days by default.</p>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Lead</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Owner</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Window</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @forelse($activeAllocations as $allocation)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4">
                                                <div class="font-semibold text-gray-900">{{ $allocation->lead->name ?? 'Lead #' . $allocation->lead_id }}</div>
                                                <div class="text-sm text-gray-500">{{ $allocation->lead->phone ?? '-' }} · {{ $allocation->lead->city ?: 'No city' }} · {{ $allocation->lead->source ?: 'No source' }}</div>
                                                <div class="text-xs text-gray-500 mt-1">Status: {{ $allocation->lead->status ?? '-' }} · Request #{{ $allocation->lead_bank_request_id }}</div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm font-medium text-gray-900">{{ $allocation->assignedTo->name ?? 'User #' . $allocation->assigned_to }}</div>
                                                <div class="text-xs text-gray-500">Allocated {{ optional($allocation->allocated_at)->format('d M Y, h:i A') }}</div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm text-gray-900">Expires {{ optional($allocation->expires_at)->format('d M Y') ?: '-' }}</div>
                                                @if($allocation->expires_at && $allocation->expires_at->isPast())
                                                    <div class="text-xs text-amber-700 mt-1">Expired, waiting recall</div>
                                                @else
                                                    <div class="text-xs text-gray-500 mt-1">{{ $allocation->expires_at ? $allocation->expires_at->diffForHumans() : 'No expiry' }}</div>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 text-right">
                                                <form method="POST" action="{{ route('lead-bank.allocations.recall', $allocation) }}" class="flex justify-end items-center gap-2" onsubmit="return confirm('Recall this allocation and apply cooldown?');">
                                                    @csrf
                                                    <input type="hidden" name="recall_reason" value="manual_recall">
                                                    <input type="number" name="cooldown_days" min="1" max="90" value="7" class="w-20 rounded-lg border-gray-300 text-sm focus:border-[#205A44] focus:ring-[#205A44]">
                                                    <button type="submit" class="text-sm font-medium text-rose-600 hover:text-rose-700">Recall</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="px-6 py-10 text-center text-sm text-gray-500">No active temporary allocations.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if($isAdminQueue)
        <div id="leadApprovalModal" class="fixed inset-0 z-[9998] hidden items-center justify-center bg-black/40 p-4">
            <div id="leadApprovalDialog" class="lead-approval-dialog">
                <div class="flex items-start justify-between gap-4 border-b border-gray-100 px-6 py-5">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900">Approve Lead Request</h3>
                        <div id="leadApprovalSummary" class="lead-approval-summary text-sm text-gray-500"></div>
                    </div>
                    <button type="button" id="leadApprovalModalClose" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div id="leadApprovalBody" class="lead-approval-body">
                    <div class="lead-approval-side">
                        <div class="mb-5 grid grid-cols-1 gap-2">
                            <button type="button" data-approval-mode="auto" class="lead-approval-mode lead-request-action is-active">
                                <i class="fas fa-bolt"></i>
                                Auto from request
                            </button>
                            <button type="button" data-approval-mode="folder" class="lead-approval-mode lead-request-action">
                                <i class="fas fa-folder-open"></i>
                                Folder wise
                            </button>
                            <button type="button" data-approval-mode="manual" class="lead-approval-mode lead-request-action" id="leadApprovalManualToggle">
                                <i class="fas fa-list-check"></i>
                                Manual select
                            </button>
                        </div>

                        <form id="leadApprovalAutoForm" method="POST" action="">
                            @csrf
                            <input type="hidden" name="assignment_method" value="auto">
                            <input type="hidden" name="allocation_mode" value="request_auto">
                            <label class="lead-request-label">Approve Quantity</label>
                            <input id="leadApprovalQuantity" type="number" name="quantity" min="1" class="lead-request-input mb-4" required>
                            <label class="lead-request-label">Priority</label>
                            <select id="leadApprovalPriority" name="priority" class="lead-request-input mb-4">
                                <option value="normal">Normal</option>
                                <option value="urgent">Urgent</option>
                            </select>
                            <label class="lead-request-label">Approval Note</label>
                            <textarea id="leadApprovalNote" name="approval_note" rows="3" class="lead-request-input mb-4" placeholder="Internal note for this approval"></textarea>
                            <button type="submit" class="lead-request-action w-full bg-emerald-700 text-white hover:bg-emerald-800">
                                <i class="fas fa-bolt"></i>
                                Auto Assign
                            </button>
                        </form>

                        <form id="leadApprovalFolderForm" method="POST" action="" class="hidden">
                            @csrf
                            <input type="hidden" name="assignment_method" value="auto">
                            <input type="hidden" name="allocation_mode" value="folder">
                            <input type="hidden" id="leadFolderType" name="folder_type" value="system">
                            <input type="hidden" id="leadFolderKey" name="folder_key" value="all">
                            <input type="hidden" id="leadFolderTagId" name="folder_tag_id" value="">
                            <label class="lead-request-label">Approve Quantity</label>
                            <input id="leadFolderApprovalQuantity" type="number" name="quantity" min="1" class="lead-request-input mb-4" required>
                            <label class="lead-request-label">Lead Folder</label>
                            <select id="leadFolderSelector" class="lead-request-input mb-4">
                                <option value="system:all">All Leads</option>
                                <option value="system:unassigned">Unassigned Leads</option>
                                @foreach($folderTags as $folderTag)
                                    <option value="tag:{{ $folderTag->id }}">{{ $folderTag->name }} ({{ number_format($folderTag->leads_count) }})</option>
                                @endforeach
                            </select>
                            <label class="lead-request-label">Priority</label>
                            <select id="leadFolderApprovalPriority" name="priority" class="lead-request-input mb-4">
                                <option value="normal">Normal</option>
                                <option value="urgent">Urgent</option>
                            </select>
                            <label class="lead-request-label">Approval Note</label>
                            <textarea id="leadFolderApprovalNote" name="approval_note" rows="3" class="lead-request-input mb-4" placeholder="Internal note for this approval"></textarea>
                            <button type="button" id="leadFolderPreviewButton" class="lead-request-action mb-3 w-full border border-gray-200 bg-white text-gray-700 hover:bg-gray-50">
                                <i class="fas fa-rotate"></i>
                                Refresh Preview
                            </button>
                            <div id="leadFolderPreviewBox" class="lead-folder-preview mb-4 hidden rounded-xl p-4">
                                <div id="leadFolderPreviewBody"></div>
                            </div>
                            <button type="submit" class="lead-request-action w-full bg-emerald-700 text-white hover:bg-emerald-800">
                                <i class="fas fa-folder-check"></i>
                                Assign From Folder
                            </button>
                        </form>
                    </div>

                    <div id="leadApprovalManualPanel" class="hidden p-6 lg:col-span-2">
                        <form id="leadApprovalManualForm" method="POST" action="">
                            @csrf
                            <input type="hidden" id="manualApprovalPriority" name="priority" value="normal">
                            <input type="hidden" id="manualApprovalNote" name="approval_note" value="">
                            <div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-5">
                                <input type="text" id="manualFilterSearch" name="search" class="lead-request-input md:col-span-2" placeholder="Search name or phone">
                                <select id="manualFilterCity" name="city" class="lead-request-input">
                                    <option value="">All cities</option>
                                    @foreach($cities as $city)
                                        <option value="{{ $city }}">{{ $city }}</option>
                                    @endforeach
                                </select>
                                <select id="manualFilterSource" name="source" class="lead-request-input">
                                    <option value="">All sources</option>
                                    @foreach($sources as $source)
                                        <option value="{{ $source }}">{{ strtoupper($source) }}</option>
                                    @endforeach
                                </select>
                                <select id="manualFilterStatus" name="status" class="lead-request-input">
                                    <option value="">All statuses</option>
                                    @foreach($statuses as $status)
                                        <option value="{{ $status }}">{{ ucwords(str_replace('_', ' ', $status)) }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-[1fr_auto_auto_auto]">
                                <select id="manualFilterTag" name="tag_id" class="lead-request-input">
                                    <option value="">All tags</option>
                                    @foreach($tags as $tag)
                                        <option value="{{ $tag->id }}">{{ $tag->name }}</option>
                                    @endforeach
                                </select>
                                <button type="button" id="manualFilterApply" class="lead-request-action border border-gray-200 bg-white text-gray-700 hover:bg-gray-50">Apply Filters</button>
                                <div class="flex items-center gap-2">
                                    <input id="manualSelectTopCount" type="number" min="1" class="lead-request-input w-24" value="1">
                                    <button type="button" id="manualSelectTop" class="lead-request-action border border-gray-200 bg-white text-gray-700 hover:bg-gray-50">Select Top N</button>
                                </div>
                                <button type="submit" id="manualAssignSubmit" class="lead-request-action bg-gray-900 text-white hover:bg-gray-800">Assign Selected</button>
                            </div>

                            <div class="mb-3 flex items-center justify-between text-sm">
                                <span id="manualSelectedCount" class="font-semibold text-gray-700">Selected 0 / Requested 0</span>
                                <span id="manualCandidateStatus" class="text-gray-500">Load matching leads to select manually.</span>
                            </div>

                            <div class="max-h-[420px] overflow-auto rounded-xl border border-gray-100">
                                <table class="min-w-full divide-y divide-gray-100">
                                    <thead class="sticky top-0 bg-gray-50">
                                        <tr>
                                            <th class="w-10 px-4 py-3"></th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Lead</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Source</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">City</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Status</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Last Owner</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Quality</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Tags</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Imported</th>
                                        </tr>
                                    </thead>
                                    <tbody id="manualCandidateRows" class="divide-y divide-gray-100 bg-white">
                                        <tr>
                                            <td colspan="9" class="px-4 py-8 text-center text-sm text-gray-500">No leads loaded.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

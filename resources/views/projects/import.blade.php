@extends('layouts.app')

@section('title', 'Import Project - ' . brand_name())
@section('page-title', 'Import Project Excel')

@section('header-actions')
    <div class="flex items-center gap-3">
        <a href="{{ route('projects.import.template') }}" class="px-4 py-2 bg-white border border-[#205A44]/20 text-[#205A44] rounded-lg hover:bg-[#F3F7F4] transition-colors duration-200 text-sm font-medium">
            Download Sample Excel
        </a>
        <a href="{{ route('projects.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors duration-200 text-sm font-medium">
            Back to Projects
        </a>
    </div>
@endsection

@push('styles')
<style>
    .import-shell {
        display: grid;
        grid-template-columns: minmax(320px, 0.9fr) minmax(0, 1.3fr);
        gap: 24px;
    }
    .import-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 20px;
        padding: 24px;
        box-shadow: 0 10px 30px rgba(6, 58, 28, 0.05);
    }
    .import-drop {
        border: 1.5px dashed #b7c7bd;
        border-radius: 18px;
        padding: 28px;
        background: linear-gradient(180deg, #fbfdfb 0%, #f5faf6 100%);
    }
    .preview-card {
        border: 1px solid #e5e7eb;
        border-radius: 16px;
        padding: 16px;
        background: #fafaf9;
    }
    .stat-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border-radius: 999px;
        padding: 8px 12px;
        background: #f3f7f4;
        color: #205A44;
        font-size: 12px;
        font-weight: 700;
    }
    .mini-table {
        width: 100%;
        border-collapse: collapse;
    }
    .mini-table th,
    .mini-table td {
        border-bottom: 1px solid #e5e7eb;
        padding: 10px 0;
        text-align: left;
        font-size: 13px;
        vertical-align: top;
    }
    .mini-table th {
        color: #6b7280;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .mini-table tr:last-child td {
        border-bottom: none;
    }
    .notice-list {
        margin: 0;
        padding-left: 18px;
        color: #4b5563;
        font-size: 13px;
    }
    .notice-list li + li {
        margin-top: 6px;
    }
    .error-box {
        background: #fff5f5;
        border: 1px solid #fecaca;
        color: #991b1b;
        border-radius: 14px;
        padding: 14px 16px;
    }
    .warning-box {
        background: #fffbea;
        border: 1px solid #fde68a;
        color: #92400e;
        border-radius: 14px;
        padding: 14px 16px;
    }
    @media (max-width: 1024px) {
        .import-shell {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@section('content')
<div class="import-shell">
    <div class="import-card">
        <div class="mb-5">
            <h2 class="text-xl font-semibold text-gray-900">Excel-first project creation</h2>
            <p class="text-sm text-gray-500 mt-2">
                Ek file me ek project import hoga. Structure Excel se aayega, aur images/media baad me wizard se add hongi.
            </p>
        </div>

        <div class="import-drop">
            <form id="project-import-form">
                @csrf
                <label for="import_file" class="block text-sm font-semibold text-gray-900 mb-2">Project Excel file</label>
                <input
                    id="import_file"
                    name="import_file"
                    type="file"
                    accept=".xlsx,.xls"
                    class="block w-full border border-gray-300 rounded-xl px-4 py-3 bg-white text-sm"
                >
                <p class="text-xs text-gray-500 mt-3">
                    Supported: `.xlsx`, `.xls` | One file = one project | Images/PDFs later wizard se upload hongi
                </p>
            </form>
        </div>

        <div class="mt-5 flex flex-wrap gap-3">
            <button id="preview-btn" type="button" class="px-5 py-3 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-xl text-sm font-semibold">
                Preview Import
            </button>
            <button id="create-btn" type="button" disabled class="px-5 py-3 bg-[#d1d5db] text-white rounded-xl text-sm font-semibold">
                Create Draft Project
            </button>
        </div>

        <div class="mt-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-2">What this import creates</h3>
            <ul class="notice-list">
                <li>Project core record</li>
                <li>Draft public page</li>
                <li>Unit types and variants</li>
                <li>Price calculation from rates</li>
                <li>Landmarks and external media links</li>
            </ul>
        </div>
    </div>

    <div class="import-card">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h3 class="text-xl font-semibold text-gray-900">Import Preview</h3>
                <p class="text-sm text-gray-500 mt-2">Validation yahin dikhegi. Errors clear hone ke baad hi draft create hoga.</p>
            </div>
        </div>

        <div id="preview-empty" class="preview-card text-sm text-gray-500">
            Sample Excel download karo, fill karo, phir yahan preview dikhegi.
        </div>

        <div id="preview-state" class="hidden space-y-4">
            <div class="flex flex-wrap gap-2" id="preview-stats"></div>

            <div class="preview-card">
                <div class="text-sm font-semibold text-gray-900 mb-3">Project Summary</div>
                <table class="mini-table">
                    <tbody id="project-summary-body"></tbody>
                </table>
            </div>

            <div class="preview-card">
                <div class="text-sm font-semibold text-gray-900 mb-3">Variants</div>
                <div class="overflow-x-auto">
                    <table class="mini-table">
                        <thead>
                            <tr>
                                <th>Unit Type</th>
                                <th>Size</th>
                                <th>Built-up</th>
                                <th>Final Price</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="variant-summary-body"></tbody>
                    </table>
                </div>
            </div>

            <div id="warnings-box" class="warning-box hidden"></div>
            <div id="errors-box" class="error-box hidden"></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const importFileInput = document.getElementById('import_file');
const previewBtn = document.getElementById('preview-btn');
const createBtn = document.getElementById('create-btn');
const previewEmpty = document.getElementById('preview-empty');
const previewState = document.getElementById('preview-state');
const statsWrap = document.getElementById('preview-stats');
const projectSummaryBody = document.getElementById('project-summary-body');
const variantSummaryBody = document.getElementById('variant-summary-body');
const warningsBox = document.getElementById('warnings-box');
const errorsBox = document.getElementById('errors-box');
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

let previewToken = null;

previewBtn.addEventListener('click', async function () {
    if (!importFileInput.files.length) {
        alert('Please select an Excel file first.');
        return;
    }

    const formData = new FormData();
    formData.append('import_file', importFileInput.files[0]);

    previewBtn.disabled = true;
    previewBtn.textContent = 'Generating Preview...';
    createBtn.disabled = true;
    createBtn.className = 'px-5 py-3 bg-[#d1d5db] text-white rounded-xl text-sm font-semibold';

    try {
        const response = await fetch('{{ route('projects.import.preview') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: formData,
        });

        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Preview failed.');
        }

        previewToken = data.token;
        renderPreview(data.preview);
    } catch (error) {
        previewToken = null;
        alert(error.message);
    } finally {
        previewBtn.disabled = false;
        previewBtn.textContent = 'Preview Import';
    }
});

createBtn.addEventListener('click', async function () {
    if (!previewToken) {
        return;
    }

    createBtn.disabled = true;
    createBtn.textContent = 'Creating Draft...';

    try {
        const response = await fetch('{{ route('projects.import.create') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ preview_token: previewToken }),
        });

        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Create draft failed.');
        }

        window.location.href = data.wizard_url;
    } catch (error) {
        alert(error.message);
        createBtn.disabled = false;
        createBtn.textContent = 'Create Draft Project';
    }
});

function renderPreview(preview) {
    previewEmpty.classList.add('hidden');
    previewState.classList.remove('hidden');

    statsWrap.innerHTML = '';
    [
        ['Unit Types', preview.stats.unit_types],
        ['Variants', preview.stats.variants],
        ['Landmarks', preview.stats.landmarks],
        ['Media Links', preview.stats.assets],
    ].forEach(([label, value]) => {
        const pill = document.createElement('div');
        pill.className = 'stat-pill';
        pill.textContent = `${label}: ${value}`;
        statsWrap.appendChild(pill);
    });

    projectSummaryBody.innerHTML = '';
    [
        ['Builder', preview.project.builder_name],
        ['Builder Action', preview.builder_action === 'existing_builder' ? 'Use existing builder' : 'Auto-create builder'],
        ['Project', preview.project.project_name],
        ['Location', [preview.project.city, preview.project.area].filter(Boolean).join(', ')],
        ['Public Title', preview.project.public_title || preview.project.project_name],
        ['Base Rate / Sq.ft.', preview.project.base_rate_per_sqft || 'Not set'],
        ['Draft Status', 'Draft on import'],
    ].forEach(([label, value]) => {
        const row = document.createElement('tr');
        row.innerHTML = `<th>${label}</th><td>${value || '-'}</td>`;
        projectSummaryBody.appendChild(row);
    });

    variantSummaryBody.innerHTML = '';
    preview.variants.forEach((variant) => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${variant.unit_type}</td>
            <td>${variant.size_label}</td>
            <td>${variant.builtup_area_sqft ?? '-'}</td>
            <td>${variant.is_price_on_request ? 'On request' : (variant.final_price ?? '-')}</td>
            <td>${variant.status}</td>
        `;
        variantSummaryBody.appendChild(row);
    });

    warningsBox.classList.toggle('hidden', !preview.warnings.length);
    warningsBox.innerHTML = preview.warnings.length
        ? `<strong class="block mb-2">Warnings</strong><ul class="notice-list">${preview.warnings.map(item => `<li>${item}</li>`).join('')}</ul>`
        : '';

    errorsBox.classList.toggle('hidden', !preview.errors.length);
    errorsBox.innerHTML = preview.errors.length
        ? `<strong class="block mb-2">Errors</strong><ul class="notice-list">${preview.errors.map(item => `<li>${item}</li>`).join('')}</ul>`
        : '';

    createBtn.disabled = preview.errors.length > 0;
    createBtn.className = preview.errors.length > 0
        ? 'px-5 py-3 bg-[#d1d5db] text-white rounded-xl text-sm font-semibold'
        : 'px-5 py-3 bg-[#205A44] text-white rounded-xl text-sm font-semibold';
}
</script>
@endpush

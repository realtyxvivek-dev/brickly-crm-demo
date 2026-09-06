@extends('layouts.app')

@section('title', 'Import Leads - ' . brand_name())
@section('page-title', 'Import Leads')
@section('page-subtitle', 'Upload CSV files, preview incoming rows, and assign them with existing CRM rules.')

@push('styles')
<style>
    .crm-upload-dropzone {
        border: 1.5px dashed rgba(15, 122, 84, 0.38);
        border-radius: 24px;
        background: linear-gradient(180deg, #ffffff, #f7fbf8);
        padding: 40px 24px;
        text-align: center;
        cursor: pointer;
        transition: border-color 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
    }
    .crm-upload-dropzone:hover,
    .crm-upload-dropzone.dragover {
        transform: translateY(-1px);
        border-color: rgba(15, 122, 84, 0.6);
        box-shadow: 0 16px 28px rgba(15, 23, 42, 0.06);
    }
    .crm-preview-table {
        width: 100%;
        border-collapse: collapse;
    }
    .crm-preview-table th,
    .crm-preview-table td {
        padding: 12px 14px;
        border-bottom: 1px solid #ebf1ed;
    }
    .crm-preview-table th {
        background: #f3f7f4;
        color: #667085;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        font-size: 12px;
    }
</style>
@endpush

@section('content')
<div class="page-shell">
    <section class="crm-hero">
        <div class="crm-hero-grid">
            <div>
                <span class="crm-kicker">
                    <i class="fas fa-file-import"></i>
                    CSV Import
                </span>
                <h2 class="crm-hero-title">Bring leads into CRM with the <strong>same import service, cleaner flow</strong>.</h2>
                <p class="crm-hero-copy">Preview, rule selection, and import behavior exactly same rahega. Sirf shell aur form presentation upgrade hui hai.</p>
            </div>
            <div class="crm-note">
                <strong>Supported:</strong> CSV/TXT files with name and phone columns. Google Sheets abhi bhi “coming soon” state me hi rahega.
            </div>
        </div>
    </section>

    @if($errors->any())
        <div class="crm-note crm-note-warning">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form id="importForm" method="POST" action="{{ route('crm.automation.import.csv') }}" enctype="multipart/form-data" class="page-shell">
        @csrf

        <section class="crm-surface">
            <div class="crm-surface-header">
                <div>
                    <div class="crm-pill">Step 1</div>
                    <h3 class="crm-section-title">Select Source</h3>
                </div>
            </div>

            <div class="crm-form-grid">
                <div class="crm-field">
                    <label for="sourceType">Source Type</label>
                    <select name="source_type" id="sourceType" required class="crm-form-control w-100">
                        <option value="csv">CSV File Upload</option>
                        <option value="sheets" disabled>Google Sheets (Coming Soon)</option>
                    </select>
                </div>
                <div class="crm-field">
                    <label>CSV File</label>
                    <div class="crm-upload-dropzone" id="fileUploadArea">
                        <input type="file" name="csv_file" id="csvFile" accept=".csv,.txt" required style="display:none;">
                        <div class="crm-pill mx-auto" style="width:max-content;">Upload</div>
                        <h4 class="mt-3 mb-2 fw-bold">Click to upload or drag and drop</h4>
                        <p class="text-muted mb-0">CSV file with name and phone columns</p>
                        <p id="fileName" class="mt-3 fw-semibold text-dark" style="display:none;"></p>
                    </div>
                </div>
            </div>
        </section>

        <section class="crm-surface">
            <div class="crm-surface-header">
                <div>
                    <div class="crm-pill">Step 2</div>
                    <h3 class="crm-section-title">Preview</h3>
                    <p class="crm-section-copy">Optional preview before import. Same preview endpoint use hota rahega.</p>
                </div>
                <button type="button" id="previewBtn" class="btn btn-brand-secondary">Preview CSV</button>
            </div>
            <div id="previewSection" class="d-none">
                <div id="previewContent" class="crm-table-shell"></div>
            </div>
        </section>

        <section class="crm-surface">
            <div class="crm-surface-header">
                <div>
                    <div class="crm-pill">Step 3</div>
                    <h3 class="crm-section-title">Assignment Rule</h3>
                </div>
            </div>
            <div class="crm-field">
                <label for="assignmentRule">Select Assignment Rule</label>
                <select name="assignment_rule_id" id="assignmentRule" required class="crm-form-control w-100">
                    <option value="">Select rule</option>
                    @foreach($rules as $rule)
                        <option value="{{ $rule->id }}">
                            {{ $rule->name }}
                            @if($rule->type === 'specific_user')
                                ({{ $rule->specificUser->name ?? 'N/A' }})
                            @else
                                ({{ $rule->ruleUsers->count() }} users)
                            @endif
                        </option>
                    @endforeach
                </select>
                <div class="mt-2">
                    <a href="{{ route('crm.automation.rules') }}" class="text-decoration-none">Create or edit assignment rules</a>
                </div>
            </div>
            <div class="crm-inline-stack mt-4">
                <button type="submit" class="btn btn-brand-primary" id="submitBtn">Import Leads</button>
                <a href="{{ route('crm.automation.index') }}" class="btn btn-light">Cancel</a>
            </div>
        </section>
    </form>
</div>
@endsection

@push('scripts')
<script>
    const fileUploadArea = document.getElementById('fileUploadArea');
    const csvFile = document.getElementById('csvFile');
    const fileName = document.getElementById('fileName');
    const previewBtn = document.getElementById('previewBtn');
    const previewSection = document.getElementById('previewSection');
    const previewContent = document.getElementById('previewContent');

    fileUploadArea.addEventListener('click', () => csvFile.click());

    csvFile.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
            fileName.textContent = e.target.files[0].name;
            fileName.style.display = 'block';
        }
    });

    fileUploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        fileUploadArea.classList.add('dragover');
    });

    fileUploadArea.addEventListener('dragleave', () => {
        fileUploadArea.classList.remove('dragover');
    });

    fileUploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        fileUploadArea.classList.remove('dragover');
        if (e.dataTransfer.files.length > 0) {
            csvFile.files = e.dataTransfer.files;
            fileName.textContent = e.dataTransfer.files[0].name;
            fileName.style.display = 'block';
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
            const response = await fetch('{{ route("crm.automation.import.csv.preview") }}', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                previewContent.innerHTML = `
                    <div class="table-responsive">
                        <table class="crm-preview-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Phone</th>
                                    <th>Email</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${data.preview.map(row => `
                                    <tr>
                                        <td>${row.name || ''}</td>
                                        <td>${row.phone || ''}</td>
                                        <td>${row.email || ''}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                    <div class="px-3 py-3 text-muted small">Total rows found: ${data.total}</div>
                `;
                previewSection.classList.remove('d-none');
            } else {
                alert('Error: ' + data.message);
            }
        } catch (error) {
            alert('Error previewing file: ' + error.message);
        }
    });
</script>
@endpush

@extends('finance-manager.layout')

@section('title', 'Booked Customer KYC')
@section('page_title', 'Booked Customer KYC')
@section('page_subtitle', 'Finance can review and update closer KYC details in a full-page workspace.')

@push('styles')
<style>
    .fkyc-shell { display: grid; gap: 18px; }
    .fkyc-card {
        background: #fff;
        border: 1px solid #ddd7ca;
        border-radius: 18px;
        padding: 0;
        overflow: hidden;
        box-shadow: 0 14px 34px rgba(10, 25, 16, 0.08);
    }
    .fkyc-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        flex-wrap: wrap;
        padding: 22px 24px 18px;
        border-bottom: 1px solid #e7e0d4;
        background: linear-gradient(180deg, #ffffff 0%, #fbfcfa 100%);
    }
    .fkyc-head h2 {
        margin: 0;
        font-size: 28px;
        line-height: 1.05;
        font-weight: 800;
        color: #0b2e20;
    }
    .fkyc-head p {
        margin: 7px 0 0;
        color: #66756f;
        font-size: 13px;
    }
    .fkyc-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 12px;
        color: #6d7b75;
        font-size: 12px;
    }
    .fkyc-meta span {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: 1px solid #dbe7df;
        border-radius: 999px;
        padding: 6px 10px;
        background: #fff;
        color: #345247;
        font-weight: 700;
    }
    .fkyc-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    .fkyc-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        border: none;
        border-radius: 10px;
        padding: 11px 16px;
        font-size: 13px;
        font-weight: 800;
        text-decoration: none;
        cursor: pointer;
        min-height: 42px;
        transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease;
    }
    .fkyc-btn.primary {
        background: #0f5b42;
        color: #fff;
        box-shadow: 0 10px 22px rgba(15, 91, 66, 0.18);
    }
    .fkyc-btn.secondary {
        background: #fff;
        color: #0f5b42;
        border: 1px solid #cfe0d7;
    }
    .fkyc-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 8px 18px rgba(10, 25, 16, 0.08);
    }
    .fkyc-status {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 12px;
        border-radius: 999px;
        background: #eef6f1;
        color: #0f5b42;
        font-size: 12px;
        font-weight: 800;
        border: 1px solid #cfe0d7;
    }
    .fkyc-correction {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 8px 12px;
        border-radius: 999px;
        background: #fff7ed;
        color: #9a3412;
        font-size: 12px;
        font-weight: 800;
        border: 1px solid #fed7aa;
    }
    .fkyc-edit-note {
        display: none;
        border: 1px solid #bfdbfe;
        background: #eff6ff;
        color: #1e3a8a;
        border-radius: 12px;
        padding: 12px 14px;
        font-size: 13px;
        font-weight: 700;
    }
    .fkyc-form.is-editing .fkyc-edit-note { display: block; }
    .fkyc-form:not(.is-editing) .fkyc-input,
    .fkyc-form:not(.is-editing) .fkyc-textarea,
    .fkyc-form:not(.is-editing) .fkyc-select {
        background: #f7f8f5;
        color: #34483f;
        box-shadow: none;
    }
    .fkyc-form:not(.is-editing) .fkyc-file-row,
    .fkyc-form:not(.is-editing) .fkyc-save-only {
        display: none;
    }
    .fkyc-section-nav {
        display: flex;
        gap: 8px;
        overflow-x: auto;
        padding: 14px 24px;
        background: #f8faf8;
        border-bottom: 1px solid #e7e0d4;
    }
    .fkyc-section-nav a {
        flex: 0 0 auto;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 9px 12px;
        border-radius: 999px;
        border: 1px solid #d7e2db;
        background: #fff;
        color: #244c3e;
        font-size: 12px;
        font-weight: 800;
        text-decoration: none;
    }
    .fkyc-section-nav span {
        display: inline-grid;
        place-items: center;
        width: 20px;
        height: 20px;
        border-radius: 999px;
        background: #e8f3ed;
        color: #0f5b42;
        font-size: 11px;
    }
    .fkyc-alert {
        border-radius: 12px;
        padding: 14px 16px;
        font-size: 14px;
        border: 1px solid;
    }
    .fkyc-alert.success {
        background: #ecfdf5;
        border-color: #a7f3d0;
        color: #166534;
    }
    .fkyc-alert.error {
        background: #fff7ed;
        border-color: #fed7aa;
        color: #9a3412;
    }
    .fkyc-form {
        display: grid;
        gap: 18px;
        padding: 20px 24px 0;
    }
    .fkyc-section {
        border: 1px solid #e7e0d4;
        border-radius: 14px;
        padding: 18px;
        background: #fff;
        display: grid;
        gap: 16px;
        scroll-margin-top: 18px;
    }
    .fkyc-section h3 {
        margin: 0;
        padding-bottom: 12px;
        border-bottom: 1px solid #edf1ee;
        font-size: 17px;
        color: #0b2e20;
        font-weight: 800;
    }
    .fkyc-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
    }
    .fkyc-field {
        display: grid;
        gap: 8px;
        align-content: start;
    }
    .fkyc-field.full { grid-column: 1 / -1; }
    .fkyc-field label {
        font-size: 11px;
        color: #6d7b75;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }
    .fkyc-input,
    .fkyc-textarea,
    .fkyc-select {
        width: 100%;
        border-radius: 10px;
        border: 1px solid #d7e2db;
        background: #fff;
        padding: 12px 14px;
        font-size: 14px;
        color: #0b2e20;
        min-height: 44px;
        transition: border-color .15s ease, box-shadow .15s ease;
    }
    .fkyc-input:focus,
    .fkyc-textarea:focus,
    .fkyc-select:focus {
        outline: none;
        border-color: #0f5b42;
        box-shadow: 0 0 0 3px rgba(15, 91, 66, 0.1);
    }
    .fkyc-textarea {
        min-height: 110px;
        resize: vertical;
    }
    .fkyc-check-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }
    .fkyc-group-card {
        grid-column: 1 / -1;
        border: 1px solid #e1e8e2;
        border-radius: 12px;
        padding: 16px;
        background: #f8fbf9;
        display: grid;
        gap: 12px;
    }
    .fkyc-group-card h4 {
        margin: 0;
        font-size: 14px;
        font-weight: 800;
        color: #0b2e20;
        letter-spacing: 0.02em;
        text-transform: uppercase;
    }
    .fkyc-check-item {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 9px 12px;
        border-radius: 999px;
        border: 1px solid #d7e2db;
        background: #fff;
        font-size: 13px;
        font-weight: 700;
        color: #0f5b42;
        cursor: pointer;
    }
    .fkyc-check-item input {
        accent-color: #0f5b42;
    }
    .fkyc-check-item:has(input:checked) {
        border-color: #8fc5aa;
        background: #eef8f2;
        color: #064e3b;
    }
    .fkyc-doc-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 14px;
    }
    .fkyc-doc-card {
        display: grid;
        overflow: hidden;
        border-radius: 12px;
        border: 1px solid #ddd7ca;
        background: #fff;
    }
    .fkyc-doc-preview {
        height: 210px;
        background: #f7f5ee;
        border-bottom: 1px solid #ece6da;
        overflow: hidden;
    }
    .fkyc-doc-preview img,
    .fkyc-doc-preview iframe {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border: 0;
        display: block;
    }
    .fkyc-doc-fallback {
        height: 100%;
        display: grid;
        place-items: center;
        text-align: center;
        padding: 18px;
        color: #0f5b42;
        gap: 8px;
    }
    .fkyc-doc-fallback i {
        font-size: 36px;
        color: #166534;
    }
    .fkyc-doc-meta {
        display: grid;
        gap: 10px;
        padding: 12px;
    }
    .fkyc-doc-name {
        font-size: 13px;
        font-weight: 700;
        color: #0b2e20;
        word-break: break-word;
        line-height: 1.4;
    }
    .fkyc-doc-status {
        justify-self: start;
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 5px 9px;
        background: #fff7ed;
        border: 1px solid #fed7aa;
        color: #9a3412;
        font-size: 11px;
        font-weight: 900;
        text-transform: uppercase;
    }
    .fkyc-doc-status.verified { background: #ecfdf5; border-color: #a7f3d0; color: #166534; }
    .fkyc-doc-status.rejected { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
    .fkyc-file-row {
        display: grid;
        gap: 10px;
        padding-top: 4px;
    }
    .fkyc-file-help {
        font-size: 12px;
        color: #6d7b75;
    }
    .fkyc-foot {
        position: sticky;
        bottom: 0;
        z-index: 4;
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        flex-wrap: wrap;
        margin: 2px -24px 0;
        padding: 16px 24px;
        border-top: 1px solid #e7e0d4;
        background: rgba(255, 255, 255, 0.94);
        backdrop-filter: blur(8px);
    }
    .fkyc-override-panel {
        flex: 1 1 420px;
        display: grid;
        gap: 8px;
        text-align: left;
    }
    .fkyc-override-panel label {
        font-size: 11px;
        color: #6d7b75;
        font-weight: 900;
        letter-spacing: .08em;
        text-transform: uppercase;
    }
    .fkyc-override-panel textarea {
        min-height: 74px;
        resize: vertical;
    }
    .fkyc-sensitive-confirm {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        color: #7c2d12;
        font-size: 12px;
        font-weight: 800;
    }
    .fkyc-history {
        border: 1px solid #e7e0d4;
        border-radius: 14px;
        padding: 18px;
        background: #fff;
        display: grid;
        gap: 14px;
    }
    .fkyc-history h3 {
        margin: 0;
        color: #0b2e20;
        font-size: 17px;
        font-weight: 900;
    }
    .fkyc-history-list {
        display: grid;
        gap: 10px;
    }
    .fkyc-history-item {
        border: 1px solid #e7e0d4;
        border-radius: 12px;
        background: #fbfcfa;
        padding: 12px;
        display: grid;
        gap: 8px;
    }
    .fkyc-history-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        flex-wrap: wrap;
        color: #0b2e20;
        font-weight: 900;
    }
    .fkyc-history-meta {
        color: #66756f;
        font-size: 12px;
        font-weight: 700;
    }
    .fkyc-history-changes {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }
    .fkyc-history-chip {
        border-radius: 999px;
        background: #eef6f1;
        border: 1px solid #cfe0d7;
        color: #0f5b42;
        padding: 5px 8px;
        font-size: 11px;
        font-weight: 900;
    }
    .fkyc-modal {
        position: fixed;
        inset: 0;
        z-index: 1000;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 20px;
        background: rgba(8, 20, 14, .48);
    }
    .fkyc-modal.is-open { display: flex; }
    .fkyc-modal-card {
        width: min(520px, 100%);
        background: #fff;
        border-radius: 18px;
        border: 1px solid #dbe7df;
        box-shadow: 0 28px 80px rgba(10, 25, 16, .22);
        padding: 22px;
        display: grid;
        gap: 14px;
    }
    .fkyc-modal-card h3 {
        margin: 0;
        color: #0b2e20;
        font-size: 22px;
        font-weight: 900;
    }
    .fkyc-modal-summary {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }
    .fkyc-modal-stat {
        border: 1px solid #e7e0d4;
        border-radius: 12px;
        padding: 12px;
        background: #fbfcfa;
    }
    .fkyc-modal-stat strong {
        display: block;
        color: #0b2e20;
        font-size: 24px;
    }
    .fkyc-modal-stat span {
        color: #66756f;
        font-size: 12px;
        font-weight: 800;
    }
    .fkyc-modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        flex-wrap: wrap;
    }
    @media (max-width: 900px) {
        .fkyc-grid { grid-template-columns: 1fr; }
        .fkyc-card { border-radius: 14px; }
        .fkyc-head, .fkyc-form, .fkyc-section-nav { padding-left: 16px; padding-right: 16px; }
        .fkyc-foot { margin-left: -16px; margin-right: -16px; padding-left: 16px; padding-right: 16px; }
    }
</style>
@endpush

@section('content')
@php
    $schema = $kycPayload['schema'] ?? ['sections' => []];
    $sections = $schema['sections'] ?? [];
    $existingKycDocuments = $kycPayload['kyc_documents'] ?? [];
    $existingProofPhotos = $kycPayload['proof_photos'] ?? [];
    $existingBookingPaymentProofs = $kycPayload['booking_payment_proofs'] ?? [];
    $documentReviews = $kycPayload['booking_document_reviews'] ?? [];
    $financeKycHistory = collect((array) $siteVisit->booking_activity_log)
        ->filter(fn ($entry) => is_array($entry) && in_array(($entry['action'] ?? ''), ['finance_kyc_overwrite', 'finance_kyc_updated'], true))
        ->reverse()
        ->take(8)
        ->values();
    $latestFinanceOverwrite = $financeKycHistory->firstWhere('action', 'finance_kyc_overwrite');
    $startInEditMode = $errors->any();

    $extractOriginalPath = function (?string $url): string {
        $url = (string) $url;
        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
            if (!empty($query['path'])) {
                return urldecode((string) $query['path']);
            }
        }

        return urldecode($parts['path'] ?? $url);
    };

    $fileName = function (?string $url) use ($extractOriginalPath): string {
        $path = $extractOriginalPath($url);
        return basename($path ?: 'file');
    };

    $fileExt = function (?string $url) use ($extractOriginalPath): string {
        return strtolower(pathinfo($extractOriginalPath($url), PATHINFO_EXTENSION));
    };

    $isImage = function (?string $url) use ($fileExt): bool {
        return in_array($fileExt($url), ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'svg'], true);
    };

    $isPdf = function (?string $url) use ($fileExt): bool {
        return $fileExt($url) === 'pdf';
    };

    $fieldMap = function (array $section): array {
        return collect($section['fields'] ?? [])->mapWithKeys(function (array $field) {
            return [($field['field_key'] ?? '') => $field];
        })->all();
    };

    $selectedBookingType = trim((string) data_get($siteVisit->unit_details ?? [], 'booking_type', ''));
    $shouldRenderField = function (?array $field) use ($selectedBookingType): bool {
        if (!$field) {
            return false;
        }

        $condition = $field['visibility_condition'] ?? null;
        if (!is_array($condition) || $condition === []) {
            return true;
        }

        foreach ($condition as $key => $expected) {
            $currentValue = $key === 'booking_type' ? $selectedBookingType : null;
            $expectedValues = is_array($expected) ? $expected : [$expected];
            if (!in_array($currentValue, $expectedValues, true)) {
                return false;
            }
        }

        return true;
    };

    $fieldOldValue = function (array $field) {
        $key = $field['field_key'] ?? '';
        return old($key, $field['value'] ?? null);
    };

    $isSensitiveField = function (string $fieldKey): bool {
        $key = strtolower($fieldKey);
        return str_contains($key, 'pan')
            || str_contains($key, 'aadhaar')
            || str_contains($key, 'dob')
            || str_contains($key, 'date_of_birth')
            || str_contains($key, 'applicant_name')
            || str_starts_with($key, 'booking_')
            || str_starts_with($key, 'unit_details_')
            || in_array($key, ['kyc_documents', 'booking_payment_proofs'], true);
    };

    $controlAttrs = function (string $fieldKey, string $label, $value = null) use ($isSensitiveField): string {
        $encodedValue = is_array($value)
            ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : (string) $value;

        return ' data-kyc-control="1" data-kyc-field="' . e($fieldKey) . '" data-kyc-label="' . e($label) . '" data-original="' . e($encodedValue) . '" data-sensitive="' . ($isSensitiveField($fieldKey) ? '1' : '0') . '"';
    };

    $renderInput = function (?array $field, string $type = 'text', bool $full = false) use ($fieldOldValue, $controlAttrs) {
        if (!$field) {
            return '';
        }
        $fieldKey = $field['field_key'] ?? '';
        $label = $field['label'] ?? $fieldKey;
        $fieldValue = $fieldOldValue($field);
        $class = $full ? 'fkyc-field full' : 'fkyc-field';
        $dateBounds = $type === 'date' && str_ends_with($fieldKey, 'date_of_birth')
            ? ' min="1900-01-01" max="' . now()->toDateString() . '"'
            : '';
        return '
            <div class="' . $class . '">
                <label for="' . e($fieldKey) . '">' . e($label) . '</label>
                <input id="' . e($fieldKey) . '" type="' . e($type) . '" name="' . e($fieldKey) . '" class="fkyc-input" value="' . e(is_array($fieldValue) ? '' : (string) $fieldValue) . '" placeholder="' . e($field['placeholder'] ?? '') . '"' . $dateBounds . $controlAttrs($fieldKey, $label, is_array($fieldValue) ? '' : (string) $fieldValue) . '>
            </div>
        ';
    };

    $renderTextarea = function (?array $field) use ($fieldOldValue, $controlAttrs) {
        if (!$field) {
            return '';
        }
        $fieldKey = $field['field_key'] ?? '';
        $label = $field['label'] ?? $fieldKey;
        $fieldValue = $fieldOldValue($field);
        return '
            <div class="fkyc-field full">
                <label for="' . e($fieldKey) . '">' . e($label) . '</label>
                <textarea id="' . e($fieldKey) . '" name="' . e($fieldKey) . '" class="fkyc-textarea" placeholder="' . e($field['placeholder'] ?? '') . '"' . $controlAttrs($fieldKey, $label, is_array($fieldValue) ? '' : (string) $fieldValue) . '>' . e(is_array($fieldValue) ? '' : (string) $fieldValue) . '</textarea>
            </div>
        ';
    };

    $renderCheckGroup = function (string $title, array $fields) use ($fieldOldValue, $controlAttrs) {
        $items = collect($fields)->filter()->all();
        if (!$items) {
            return '';
        }

        $html = '';
        foreach ($items as $field) {
            $fieldKey = $field['field_key'] ?? '';
            $label = $field['label'] ?? $fieldKey;
            $checked = !empty($fieldOldValue($field)) ? 'checked' : '';
            $html .= '
                <label class="fkyc-check-item">
                    <input type="checkbox" name="' . e($fieldKey) . '" value="1" ' . $checked . $controlAttrs($fieldKey, $label, !empty($fieldOldValue($field)) ? '1' : '0') . '>
                    ' . e($label) . '
                </label>
            ';
        }

        return '
            <div class="fkyc-group-card">
                <h4>' . e($title) . '</h4>
                <div class="fkyc-check-grid">' . $html . '</div>
            </div>
        ';
    };

    $documentStatusBadge = function (string $groupKey, ?string $file) use ($documentReviews, $extractOriginalPath): string {
        $review = $documentReviews[$groupKey][$extractOriginalPath($file)] ?? null;
        $status = strtolower((string) ($review['status'] ?? 'pending'));
        $label = $status === 'verified' ? 'Verified' : ($status === 'rejected' ? 'Rejected' : 'Pending');

        return '<span class="fkyc-doc-status ' . e($status) . '">' . e($label) . '</span>';
    };
@endphp

<div class="fkyc-shell">
    <div class="fkyc-card">
        <div class="fkyc-head">
            <div>
                <h2>{{ $kycPayload['customer_name'] ?? 'Booked Customer KYC' }}</h2>
                <p>{{ $kycPayload['project'] ?? 'Project not filled' }} booking KYC</p>
                <div class="fkyc-meta">
                    <span><i class="fas fa-building"></i>{{ $kycPayload['project'] ?? 'Project not filled' }}</span>
                    <span><i class="fas fa-user"></i>{{ $siteVisit->lead?->name ?: 'Customer' }}</span>
                    <span><i class="fas fa-user-check"></i>{{ $siteVisit->assignedTo?->name ?: ($siteVisit->creator?->name ?: 'Unassigned') }}</span>
                </div>
            </div>
            <div class="fkyc-actions">
                <span class="fkyc-status">{{ $kycPayload['status'] ?? 'KYC details' }}</span>
                @if($siteVisit->kyc_last_corrected_at)
                    <span class="fkyc-correction">
                        <i class="fas fa-pen-to-square"></i>
                        Corrected {{ $siteVisit->kyc_last_corrected_at->format('d M Y h:i A') }}
                    </span>
                @endif
                <button type="button" class="fkyc-btn primary" id="fkycEditButton">
                    <i class="fas fa-pen"></i> Edit KYC
                </button>
                <a href="{{ route('finance-manager.booked-customers') }}" class="fkyc-btn secondary">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <nav class="fkyc-section-nav" aria-label="KYC sections">
            @foreach($sections as $index => $section)
                <a href="#fkyc-section-{{ $index + 1 }}">
                    <span>{{ $index + 1 }}</span>
                    {{ $section['label'] ?? 'Section' }}
                </a>
            @endforeach
        </nav>

        @if(session('success'))
            <div class="fkyc-alert success">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="fkyc-alert error">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('finance-manager.booked-customers.kyc.update', $siteVisit) }}" enctype="multipart/form-data" class="fkyc-form {{ $startInEditMode ? 'is-editing' : '' }}" id="financeKycForm" data-editing="{{ $startInEditMode ? '1' : '0' }}">
            @csrf
            <input type="hidden" name="kyc_form_id" value="{{ $schema['form_id'] ?? $siteVisit->kyc_dynamic_form_id }}">
            <input type="hidden" name="override_reason" id="overrideReasonInput" value="{{ old('override_reason') }}">
            <input type="hidden" name="sensitive_confirmed" id="sensitiveConfirmedInput" value="{{ old('sensitive_confirmed') ? '1' : '0' }}">

            <div class="fkyc-edit-note">
                <i class="fas fa-circle-info"></i>
                Edit mode active. Save ke liye reason mandatory hai; PAN, Aadhaar, applicant, booking, unit, or document change par sensitive confirmation required hai.
            </div>

            @foreach($sections as $section)
                <section class="fkyc-section" id="fkyc-section-{{ $loop->iteration }}">
                    <h3>{{ $section['label'] ?? 'Section' }}</h3>
                    <div class="fkyc-grid">
                        @php
                            $sectionLabel = $section['label'] ?? '';
                            $sectionFieldMap = $fieldMap($section);
                        @endphp

                        @if(in_array($sectionLabel, ['1. Sole / First Applicant', '2. Second / Joint Applicant / Nominee'], true))
                            @php
                                $prefix = $sectionLabel === '1. Sole / First Applicant' ? 'primary_applicant_' : 'joint_applicant_';
                            @endphp

                            {!! $renderInput($sectionFieldMap[$prefix . 'name'] ?? null) !!}
                            {!! $renderInput($sectionFieldMap[$prefix . 'relation_name'] ?? null) !!}
                            {!! $renderInput($sectionFieldMap[$prefix . 'date_of_birth'] ?? null, 'date') !!}
                            {!! $renderInput($sectionFieldMap[$prefix . 'nationality'] ?? null) !!}

                            {!! $renderCheckGroup('Occupation', [
                                $sectionFieldMap[$prefix . 'occupation_service'] ?? null,
                                $sectionFieldMap[$prefix . 'occupation_professional'] ?? null,
                                $sectionFieldMap[$prefix . 'occupation_housewife'] ?? null,
                                $sectionFieldMap[$prefix . 'occupation_business'] ?? null,
                            ]) !!}
                            {!! $renderInput($sectionFieldMap[$prefix . 'occupation_any_other'] ?? null, 'text', true) !!}

                            {!! $renderCheckGroup('Residential Status', [
                                $sectionFieldMap[$prefix . 'resident_indian'] ?? null,
                            ]) !!}
                            {!! $renderInput($sectionFieldMap[$prefix . 'resident_other'] ?? null, 'text', true) !!}

                            {!! $renderCheckGroup('Marital Status', [
                                $sectionFieldMap[$prefix . 'marital_status_married'] ?? null,
                                $sectionFieldMap[$prefix . 'marital_status_unmarried'] ?? null,
                            ]) !!}

                            {!! $renderInput($sectionFieldMap[$prefix . 'pan_no'] ?? null) !!}
                            @if($prefix === 'primary_applicant_')
                                {!! $renderInput($sectionFieldMap[$prefix . 'aadhaar_no'] ?? null) !!}
                            @endif
                            {!! $renderTextarea($sectionFieldMap[$prefix . 'address'] ?? null) !!}
                            {!! $renderTextarea($sectionFieldMap[$prefix . 'communication_address'] ?? null) !!}
                            {!! $renderInput($sectionFieldMap[$prefix . 'city'] ?? null) !!}
                            {!! $renderInput($sectionFieldMap[$prefix . 'state'] ?? null) !!}
                            {!! $renderInput($sectionFieldMap[$prefix . 'pin'] ?? null) !!}
                            {!! $renderInput($sectionFieldMap[$prefix . 'email'] ?? null, 'email') !!}
                            {!! $renderInput($sectionFieldMap[$prefix . 'mobile_no'] ?? null) !!}
                            {!! $renderInput($sectionFieldMap[$prefix . 'tel_no'] ?? null) !!}

                            @if($prefix === 'joint_applicant_')
                                <div class="fkyc-group-card">
                                    <h4>Company / Firm Details</h4>
                                    <div class="fkyc-grid">
                                        {!! $renderInput($sectionFieldMap['company_name'] ?? null) !!}
                                        {!! $renderInput($sectionFieldMap['company_date_of_incorporation'] ?? null, 'date') !!}
                                        {!! $renderTextarea($sectionFieldMap['company_registered_address'] ?? null) !!}
                                        {!! $renderInput($sectionFieldMap['company_incorporation_no'] ?? null) !!}
                                        {!! $renderInput($sectionFieldMap['company_pan_no'] ?? null) !!}
                                        {!! $renderInput($sectionFieldMap['company_gst_no'] ?? null) !!}
                                        {!! $renderTextarea($sectionFieldMap['company_business_nature'] ?? null) !!}
                                        {!! $renderInput($sectionFieldMap['company_signatory_name'] ?? null) !!}
                                        {!! $renderInput($sectionFieldMap['company_signatory_relation'] ?? null) !!}
                                        {!! $renderInput($sectionFieldMap['company_signatory_designation'] ?? null) !!}
                                        {!! $renderInput($sectionFieldMap['company_email'] ?? null, 'email') !!}
                                        {!! $renderInput($sectionFieldMap['company_contact_1'] ?? null) !!}
                                        {!! $renderInput($sectionFieldMap['company_contact_2'] ?? null) !!}
                                    </div>
                                </div>
                            @endif
                        @else
                        @foreach(($section['fields'] ?? []) as $field)
                            @php
                                if (!$shouldRenderField($field)) {
                                    continue;
                                }
                                $fieldKey = $field['field_key'] ?? '';
                                $fieldType = $field['field_type'] ?? 'text';
                                $fieldValue = old($fieldKey, $field['value'] ?? null);
                            @endphp

                            @if($fieldType === 'file' && $fieldKey === 'kyc_documents')
                                <div class="fkyc-field full">
                                    <label>{{ $field['label'] ?? 'KYC Documents' }}</label>
                                    <div class="fkyc-doc-grid">
                                        @forelse($existingKycDocuments as $file)
                                            <div class="fkyc-doc-card">
                                                <div class="fkyc-doc-preview">
                                                    @if($isImage($file))
                                                        <img src="{{ $file }}" alt="{{ $fileName($file) }}">
                                                    @elseif($isPdf($file))
                                                        <iframe src="{{ $file }}#toolbar=0&navpanes=0&scrollbar=0"></iframe>
                                                    @else
                                                        <div class="fkyc-doc-fallback">
                                                            <i class="fas fa-file-lines"></i>
                                                            <div>{{ $fileName($file) }}</div>
                                                        </div>
                                                    @endif
                                                </div>
                                                <div class="fkyc-doc-meta">
                                                    <div class="fkyc-doc-name">{{ $fileName($file) }}</div>
                                                    {!! $documentStatusBadge('kyc_documents', $file) !!}
                                                    <a href="{{ $file }}" target="_blank" rel="noopener" class="fkyc-btn secondary">Open File</a>
                                                    <input type="hidden" name="existing_kyc_documents[]" value="{{ $extractOriginalPath($file) }}">
                                                </div>
                                            </div>
                                        @empty
                                            <div class="fkyc-file-help">No existing KYC documents.</div>
                                        @endforelse
                                    </div>
                                    <div class="fkyc-file-row">
                                        <input type="file" name="kyc_documents[]" multiple class="fkyc-input" accept=".jpg,.jpeg,.png,.pdf,.webp" data-kyc-control="1" data-kyc-field="kyc_documents" data-kyc-label="KYC Documents" data-original="" data-sensitive="1">
                                        <label class="fkyc-check-item">
                                            <input type="checkbox" name="replace_kyc_documents" value="1" data-kyc-control="1" data-kyc-field="kyc_documents" data-kyc-label="KYC Documents" data-original="0" data-sensitive="1">
                                            Replace current KYC documents
                                        </label>
                                        <div class="fkyc-file-help">New uploads append honge. Agar replace tick karoge to current KYC docs replace ho jayenge.</div>
                                    </div>
                                </div>
                            @elseif($fieldType === 'file' && $fieldKey === 'proof_photos')
                                <div class="fkyc-field full">
                                    <label>{{ $field['label'] ?? 'Proof Photos' }}</label>
                                    <div class="fkyc-doc-grid">
                                        @forelse($existingProofPhotos as $file)
                                            <div class="fkyc-doc-card">
                                                <div class="fkyc-doc-preview">
                                                    @if($isImage($file))
                                                        <img src="{{ $file }}" alt="{{ $fileName($file) }}">
                                                    @elseif($isPdf($file))
                                                        <iframe src="{{ $file }}#toolbar=0&navpanes=0&scrollbar=0"></iframe>
                                                    @else
                                                        <div class="fkyc-doc-fallback">
                                                            <i class="fas fa-image"></i>
                                                            <div>{{ $fileName($file) }}</div>
                                                        </div>
                                                    @endif
                                                </div>
                                                <div class="fkyc-doc-meta">
                                                    <div class="fkyc-doc-name">{{ $fileName($file) }}</div>
                                                    {!! $documentStatusBadge('proof_photos', $file) !!}
                                                    <a href="{{ $file }}" target="_blank" rel="noopener" class="fkyc-btn secondary">Open File</a>
                                                    <input type="hidden" name="existing_proof_photos[]" value="{{ $extractOriginalPath($file) }}">
                                                </div>
                                            </div>
                                        @empty
                                            <div class="fkyc-file-help">No existing proof photos.</div>
                                        @endforelse
                                    </div>
                                    <div class="fkyc-file-row">
                                        <input type="file" name="proof_photos[]" multiple class="fkyc-input" accept=".jpg,.jpeg,.png,.webp" data-kyc-control="1" data-kyc-field="proof_photos" data-kyc-label="Proof Photos" data-original="" data-sensitive="0">
                                        <label class="fkyc-check-item">
                                            <input type="checkbox" name="replace_proof_photos" value="1" data-kyc-control="1" data-kyc-field="proof_photos" data-kyc-label="Proof Photos" data-original="0" data-sensitive="0">
                                            Replace current proof photos
                                        </label>
                                        <div class="fkyc-file-help">Yahan se finance proof photos add ya replace kar sakta hai.</div>
                                    </div>
                                </div>
                            @elseif($fieldType === 'file' && $fieldKey === 'booking_payment_proofs')
                                <div class="fkyc-field full">
                                    <label>{{ $field['label'] ?? 'Booking Receipt / Payment Proofs' }}</label>
                                    <div class="fkyc-doc-grid">
                                        @forelse($existingBookingPaymentProofs as $file)
                                            <div class="fkyc-doc-card">
                                                <div class="fkyc-doc-preview">
                                                    @if($isImage($file))
                                                        <img src="{{ $file }}" alt="{{ $fileName($file) }}">
                                                    @elseif($isPdf($file))
                                                        <iframe src="{{ $file }}#toolbar=0&navpanes=0&scrollbar=0"></iframe>
                                                    @else
                                                        <div class="fkyc-doc-fallback">
                                                            <i class="fas fa-receipt"></i>
                                                            <div>{{ $fileName($file) }}</div>
                                                        </div>
                                                    @endif
                                                </div>
                                                <div class="fkyc-doc-meta">
                                                    <div class="fkyc-doc-name">{{ $fileName($file) }}</div>
                                                    {!! $documentStatusBadge('booking_payment_proofs', $file) !!}
                                                    <a href="{{ $file }}" target="_blank" rel="noopener" class="fkyc-btn secondary">Open File</a>
                                                    <input type="hidden" name="existing_booking_payment_proofs[]" value="{{ $extractOriginalPath($file) }}">
                                                </div>
                                            </div>
                                        @empty
                                            <div class="fkyc-file-help">No existing booking payment proofs.</div>
                                        @endforelse
                                    </div>
                                    <div class="fkyc-file-row">
                                        <input type="file" name="booking_payment_proofs[]" multiple class="fkyc-input" accept=".jpg,.jpeg,.png,.pdf,.webp" data-kyc-control="1" data-kyc-field="booking_payment_proofs" data-kyc-label="Booking Payment Proofs" data-original="" data-sensitive="1">
                                        <label class="fkyc-check-item">
                                            <input type="checkbox" name="replace_booking_payment_proofs" value="1" data-kyc-control="1" data-kyc-field="booking_payment_proofs" data-kyc-label="Booking Payment Proofs" data-original="0" data-sensitive="1">
                                            Replace current booking payment proofs
                                        </label>
                                        <div class="fkyc-file-help">Booking receipt, UTR screenshot ya payment proof yahan add/replace kar sakte ho.</div>
                                    </div>
                                </div>
                            @elseif($fieldType === 'textarea')
                                <div class="fkyc-field full">
                                    <label for="{{ $fieldKey }}">{{ $field['label'] ?? $fieldKey }}</label>
                                    <textarea id="{{ $fieldKey }}" name="{{ $fieldKey }}" class="fkyc-textarea" placeholder="{{ $field['placeholder'] ?? '' }}" {!! $controlAttrs($fieldKey, $field['label'] ?? $fieldKey, is_array($fieldValue) ? '' : $fieldValue) !!}>{{ is_array($fieldValue) ? '' : $fieldValue }}</textarea>
                                </div>
                            @elseif($fieldType === 'date')
                                <div class="fkyc-field">
                                    <label for="{{ $fieldKey }}">{{ $field['label'] ?? $fieldKey }} @if($fieldKey === 'booking_date')<span style="color:#dc2626;">*</span>@endif</label>
                                    <input id="{{ $fieldKey }}" type="date" name="{{ $fieldKey }}" class="fkyc-input" value="{{ is_array($fieldValue) ? '' : $fieldValue }}" @if($fieldKey === 'booking_date') required max="{{ now()->toDateString() }}" @endif {!! $controlAttrs($fieldKey, $field['label'] ?? $fieldKey, is_array($fieldValue) ? '' : $fieldValue) !!}>
                                </div>
                            @elseif($fieldType === 'checkbox' && !empty($field['options']))
                                @php $selectedValues = is_array($fieldValue) ? $fieldValue : []; @endphp
                                <div class="fkyc-field full">
                                    <label>{{ $field['label'] ?? $fieldKey }}</label>
                                    <div class="fkyc-check-grid">
                                        @foreach(($field['options'] ?? []) as $option)
                                            <label class="fkyc-check-item">
                                                <input type="checkbox" name="{{ $fieldKey }}[]" value="{{ $option }}" @checked(in_array($option, $selectedValues, true)) {!! $controlAttrs($fieldKey, $field['label'] ?? $fieldKey, $selectedValues) !!}>
                                                {{ $option }}
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @elseif($fieldType === 'checkbox')
                                <div class="fkyc-field">
                                    <label>{{ $field['label'] ?? $fieldKey }}</label>
                                    <label class="fkyc-check-item">
                                        <input type="checkbox" name="{{ $fieldKey }}" value="1" @checked(!empty($fieldValue)) {!! $controlAttrs($fieldKey, $field['label'] ?? $fieldKey, !empty($fieldValue) ? '1' : '0') !!}>
                                        {{ $field['label'] ?? $fieldKey }}
                                    </label>
                                </div>
                            @else
                                <div class="fkyc-field {{ in_array($fieldKey, ['primary_applicant_address', 'joint_applicant_address'], true) ? 'full' : '' }}">
                                    <label for="{{ $fieldKey }}">{{ $field['label'] ?? $fieldKey }}</label>
                                    <input id="{{ $fieldKey }}" type="{{ $fieldType === 'email' ? 'email' : 'text' }}" name="{{ $fieldKey }}" class="fkyc-input" value="{{ is_array($fieldValue) ? '' : $fieldValue }}" placeholder="{{ $field['placeholder'] ?? '' }}" {!! $controlAttrs($fieldKey, $field['label'] ?? $fieldKey, is_array($fieldValue) ? '' : $fieldValue) !!}>
                                </div>
                            @endif
                        @endforeach
                        @endif
                    </div>
                </section>
            @endforeach

            <section class="fkyc-section" id="fkyc-revenue-section">
                <h3>Revenue Contribution</h3>
                <div class="fkyc-grid">
                    <div class="fkyc-field">
                        <label for="revenue_value">Revenue Value</label>
                        <input
                            id="revenue_value"
                            type="number"
                            min="0"
                            step="0.01"
                            name="revenue_value"
                            class="fkyc-input"
                            value="{{ old('revenue_value', $siteVisit->revenue_value) }}"
                            required
                            data-kyc-control="1" data-kyc-field="revenue_value" data-kyc-label="Revenue Value" data-original="{{ old('revenue_value', $siteVisit->revenue_value) }}" data-sensitive="0"
                        >
                    </div>
                    <div class="fkyc-field">
                        <label>Last Updated</label>
                        <div class="fkyc-input" style="display:flex;align-items:center;background:#f7f5ee;">
                            {{ $siteVisit->revenue_updated_at ? $siteVisit->revenue_updated_at->format('d M Y h:i A') : 'Not entered yet' }}
                            @if($siteVisit->revenueUpdatedBy)
                                by {{ $siteVisit->revenueUpdatedBy->name }}
                            @endif
                        </div>
                    </div>
                    <div class="fkyc-field full">
                        <label for="revenue_note">Revenue Note</label>
                        <textarea id="revenue_note" name="revenue_note" class="fkyc-textarea" placeholder="Optional finance/admin note" data-kyc-control="1" data-kyc-field="revenue_note" data-kyc-label="Revenue Note" data-original="{{ old('revenue_note', $siteVisit->revenue_note) }}" data-sensitive="0">{{ old('revenue_note', $siteVisit->revenue_note) }}</textarea>
                    </div>
                </div>
            </section>

            <div class="fkyc-foot">
                <div class="fkyc-override-panel fkyc-save-only">
                    <label for="overrideReasonDraft">Change Reason</label>
                    <textarea id="overrideReasonDraft" class="fkyc-textarea" placeholder="Example: Corrected PAN spelling after finance document check.">{{ old('override_reason') }}</textarea>
                    <label class="fkyc-sensitive-confirm">
                        <input type="checkbox" id="sensitiveConfirmDraft" @checked(old('sensitive_confirmed'))>
                        Sensitive KYC/booking/document changes verified by Finance.
                    </label>
                </div>
                <a href="{{ route('finance-manager.booked-customers') }}" class="fkyc-btn secondary">Back To List</a>
                <button type="button" class="fkyc-btn secondary fkyc-save-only" id="fkycCancelEdit">
                    Cancel
                </button>
                <button type="button" class="fkyc-btn primary fkyc-save-only" id="fkycReviewChanges">
                    <i class="fas fa-floppy-disk"></i> Save Changes
                </button>
            </div>
        </form>

        <section class="fkyc-history" id="fkyc-history">
            <h3>Change History</h3>
            <div class="fkyc-history-list">
                @forelse($financeKycHistory as $entry)
                    @php
                        $changes = collect($entry['changes'] ?? [])->take(8);
                        $changedCount = (int) ($entry['changed_count'] ?? $changes->count());
                    @endphp
                    <div class="fkyc-history-item">
                        <div class="fkyc-history-top">
                            <span>{{ ($entry['action'] ?? '') === 'finance_kyc_overwrite' ? 'Finance overwrite' : 'Finance KYC update' }}</span>
                            <span class="fkyc-history-meta">{{ !empty($entry['at']) ? \Carbon\Carbon::parse($entry['at'])->format('d M Y h:i A') : 'Time not captured' }}</span>
                        </div>
                        <div class="fkyc-history-meta">
                            {{ $entry['user_name'] ?? 'Finance user' }}
                            @if(!empty($entry['remark']))
                                - {{ $entry['remark'] }}
                            @endif
                        </div>
                        @if($changes->isNotEmpty())
                            <div class="fkyc-history-changes">
                                @foreach($changes as $change)
                                    <span class="fkyc-history-chip">{{ $change['label'] ?? $change['field'] ?? 'Field' }}</span>
                                @endforeach
                                @if($changedCount > $changes->count())
                                    <span class="fkyc-history-chip">+{{ $changedCount - $changes->count() }} more</span>
                                @endif
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="fkyc-file-help">No finance KYC overwrite history yet.</div>
                @endforelse
            </div>
        </section>
    </div>
</div>

<div class="fkyc-modal" id="fkycConfirmModal" aria-hidden="true">
    <div class="fkyc-modal-card" role="dialog" aria-modal="true" aria-labelledby="fkycConfirmTitle">
        <h3 id="fkycConfirmTitle">Confirm KYC overwrite</h3>
        <p class="fkyc-file-help">Review changes before saving. This action will be captured in booking activity history.</p>
        <div class="fkyc-modal-summary">
            <div class="fkyc-modal-stat">
                <strong id="fkycChangeCount">0</strong>
                <span>Changed fields</span>
            </div>
            <div class="fkyc-modal-stat">
                <strong id="fkycSensitiveCount">0</strong>
                <span>Sensitive changes</span>
            </div>
        </div>
        <div class="fkyc-alert error" id="fkycModalWarning" style="display:none;"></div>
        <div class="fkyc-modal-actions">
            <button type="button" class="fkyc-btn secondary" id="fkycCloseConfirm">Back to edit</button>
            <button type="button" class="fkyc-btn primary" id="fkycSubmitConfirm">
                <i class="fas fa-check"></i> Confirm & Save
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('financeKycForm');
    const editButton = document.getElementById('fkycEditButton');
    const cancelButton = document.getElementById('fkycCancelEdit');
    const reviewButton = document.getElementById('fkycReviewChanges');
    const modal = document.getElementById('fkycConfirmModal');
    const closeConfirm = document.getElementById('fkycCloseConfirm');
    const submitConfirm = document.getElementById('fkycSubmitConfirm');
    const changeCountEl = document.getElementById('fkycChangeCount');
    const sensitiveCountEl = document.getElementById('fkycSensitiveCount');
    const modalWarning = document.getElementById('fkycModalWarning');
    const reasonDraft = document.getElementById('overrideReasonDraft');
    const reasonInput = document.getElementById('overrideReasonInput');
    const sensitiveDraft = document.getElementById('sensitiveConfirmDraft');
    const sensitiveInput = document.getElementById('sensitiveConfirmedInput');

    if (!form) return;

    const controls = () => Array.from(form.querySelectorAll('[data-kyc-control="1"]'));
    const normalize = (value) => String(value ?? '').trim();
    const parseOriginal = (raw) => {
        try {
            const parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed.map(normalize).sort().join('|') : normalize(parsed);
        } catch (e) {
            return normalize(raw);
        }
    };

    const setEditing = (enabled) => {
        form.dataset.editing = enabled ? '1' : '0';
        form.classList.toggle('is-editing', enabled);
        controls().forEach((control) => {
            control.disabled = !enabled;
            if ('readOnly' in control) {
                control.readOnly = !enabled && !['checkbox', 'radio', 'file'].includes(control.type);
            }
        });
        if (editButton) editButton.style.display = enabled ? 'none' : 'inline-flex';
    };

    const currentGroupedValues = () => {
        const groups = {};
        controls().forEach((control) => {
            const field = control.dataset.kycField;
            if (!field) return;
            groups[field] ??= {
                label: control.dataset.kycLabel || field,
                sensitive: control.dataset.sensitive === '1',
                original: parseOriginal(control.dataset.original || ''),
                values: [],
                fileChanged: false,
            };

            if (control.type === 'file') {
                if (control.files && control.files.length > 0) {
                    groups[field].fileChanged = true;
                    groups[field].values.push(`files:${control.files.length}`);
                }
                return;
            }

            if (control.type === 'checkbox') {
                if (control.checked) {
                    groups[field].values.push(control.value || '1');
                }
                return;
            }

            groups[field].values.push(control.value);
        });

        return Object.values(groups).map((group) => ({
            ...group,
            current: group.values.map(normalize).sort().join('|'),
        }));
    };

    const changedGroups = () => currentGroupedValues().filter((group) => {
        if (group.fileChanged) return true;
        return group.current !== group.original;
    });

    const openModal = () => {
        const changes = changedGroups();
        const sensitiveChanges = changes.filter((group) => group.sensitive);
        const reason = normalize(reasonDraft?.value);
        const sensitiveConfirmed = Boolean(sensitiveDraft?.checked);

        modalWarning.style.display = 'none';
        modalWarning.textContent = '';

        if (changes.length === 0) {
            alert('No changes detected. Edit at least one field before saving.');
            return;
        }

        if (reason.length < 5) {
            alert('Change reason is required before saving.');
            reasonDraft?.focus();
            return;
        }

        if (sensitiveChanges.length > 0 && !sensitiveConfirmed) {
            alert('Sensitive KYC, booking, or document changes need Finance confirmation.');
            sensitiveDraft?.focus();
            return;
        }

        changeCountEl.textContent = changes.length;
        sensitiveCountEl.textContent = sensitiveChanges.length;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
    };

    editButton?.addEventListener('click', () => setEditing(true));
    cancelButton?.addEventListener('click', () => window.location.reload());
    reviewButton?.addEventListener('click', openModal);
    closeConfirm?.addEventListener('click', () => {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
    });
    submitConfirm?.addEventListener('click', () => {
        reasonInput.value = reasonDraft.value;
        sensitiveInput.value = sensitiveDraft.checked ? '1' : '0';
        controls().forEach((control) => control.disabled = false);
        form.submit();
    });

    setEditing(form.dataset.editing === '1');
});
</script>
@endsection

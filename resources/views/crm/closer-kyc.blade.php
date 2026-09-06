@extends('layouts.app')

@section('title', 'Closer KYC Review')
@section('page-title', 'Closer KYC Review')
@section('page-subtitle', 'Full-page KYC workspace for CRM verification review.')

@push('styles')
<style>
    .ckyc-shell { display: grid; gap: 18px; }
    .ckyc-card {
        background: #fff;
        border: 1px solid #ddd7ca;
        border-radius: 22px;
        overflow: hidden;
        box-shadow: 0 16px 40px rgba(10, 25, 16, 0.08);
    }
    .ckyc-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        flex-wrap: wrap;
        padding: 24px 26px;
        border-bottom: 1px solid #e7e0d4;
        background: linear-gradient(180deg, #ffffff 0%, #fbfcfa 100%);
    }
    .ckyc-kicker {
        color: #718179;
        font-size: 12px;
        font-weight: 900;
        letter-spacing: .18em;
        text-transform: uppercase;
    }
    .ckyc-head h2 {
        margin: 8px 0 0;
        font-size: 34px;
        line-height: 1.05;
        color: #0b2e20;
        font-weight: 900;
        letter-spacing: -.04em;
    }
    .ckyc-head p { margin: 8px 0 0; color: #66756f; }
    .ckyc-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 14px;
    }
    .ckyc-chip {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        border: 1px solid #dbe7df;
        border-radius: 999px;
        padding: 7px 11px;
        background: #fff;
        color: #244c3e;
        font-size: 12px;
        font-weight: 800;
    }
    .ckyc-actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
    .ckyc-status {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 10px 13px;
        border-radius: 999px;
        background: #eef8f2;
        color: #0f5b42;
        font-size: 12px;
        font-weight: 900;
        border: 1px solid #cfe0d7;
    }
    .ckyc-correction {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 10px 13px;
        border-radius: 999px;
        background: #fff7ed;
        color: #9a3412;
        font-size: 12px;
        font-weight: 900;
        border: 1px solid #fed7aa;
    }
    .ckyc-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        border-radius: 12px;
        padding: 11px 16px;
        font-size: 13px;
        font-weight: 900;
        text-decoration: none;
        border: 1px solid #cfe0d7;
        color: #0f5b42;
        background: #fff;
    }
    .ckyc-nav {
        display: flex;
        gap: 8px;
        overflow-x: auto;
        padding: 15px 26px;
        border-bottom: 1px solid #e7e0d4;
        background: #f8faf8;
    }
    .ckyc-nav a {
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
        font-weight: 900;
        text-decoration: none;
    }
    .ckyc-nav span {
        display: inline-grid;
        place-items: center;
        width: 20px;
        height: 20px;
        border-radius: 999px;
        background: #e8f3ed;
        color: #0f5b42;
        font-size: 11px;
    }
    .ckyc-body {
        display: grid;
        gap: 18px;
        padding: 22px 26px 28px;
    }
    .ckyc-section {
        border: 1px solid #e7e0d4;
        border-radius: 16px;
        padding: 20px;
        background: #fff;
        scroll-margin-top: 16px;
    }
    .ckyc-section h3 {
        margin: 0 0 16px;
        padding-bottom: 12px;
        border-bottom: 1px solid #edf1ee;
        color: #0b2e20;
        font-size: 18px;
        font-weight: 900;
    }
    .ckyc-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }
    .ckyc-field {
        display: grid;
        gap: 7px;
        align-content: start;
    }
    .ckyc-field.full { grid-column: 1 / -1; }
    .ckyc-label {
        font-size: 11px;
        color: #6d7b75;
        font-weight: 900;
        letter-spacing: .08em;
        text-transform: uppercase;
    }
    .ckyc-value {
        min-height: 44px;
        display: flex;
        align-items: center;
        border: 1px solid #d7e2db;
        border-radius: 11px;
        background: #fbfdfb;
        padding: 11px 13px;
        color: #0b2e20;
        font-weight: 700;
        word-break: break-word;
        white-space: pre-wrap;
    }
    .ckyc-group-card {
        grid-column: 1 / -1;
        border: 1px solid #e1e8e2;
        border-radius: 14px;
        padding: 16px;
        background: #f8fbf9;
        display: grid;
        gap: 12px;
    }
    .ckyc-group-card h4 {
        margin: 0;
        font-size: 14px;
        color: #0b2e20;
        font-weight: 900;
        text-transform: uppercase;
    }
    .ckyc-check-grid { display: flex; flex-wrap: wrap; gap: 10px; }
    .ckyc-check {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border: 1px solid #d7e2db;
        border-radius: 999px;
        padding: 9px 12px;
        background: #fff;
        color: #0f5b42;
        font-size: 13px;
        font-weight: 800;
    }
    .ckyc-check.is-checked {
        border-color: #8fc5aa;
        background: #eef8f2;
        color: #064e3b;
    }
    .ckyc-doc-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(270px, 1fr));
        gap: 14px;
    }
    .ckyc-doc-card {
        display: grid;
        overflow: hidden;
        border-radius: 13px;
        border: 1px solid #ddd7ca;
        background: #fff;
    }
    .ckyc-doc-preview {
        height: 230px;
        background: #f7f5ee;
        border-bottom: 1px solid #ece6da;
        overflow: hidden;
    }
    .ckyc-doc-preview img,
    .ckyc-doc-preview iframe {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border: 0;
        display: block;
    }
    .ckyc-doc-fallback {
        height: 100%;
        display: grid;
        place-items: center;
        text-align: center;
        padding: 18px;
        color: #0f5b42;
        gap: 8px;
    }
    .ckyc-doc-fallback i { font-size: 36px; color: #166534; }
    .ckyc-doc-meta { display: grid; gap: 10px; padding: 12px; }
    .ckyc-doc-name { color: #0b2e20; font-weight: 800; word-break: break-word; }
    .ckyc-doc-status {
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
    .ckyc-doc-status.verified { background: #ecfdf5; border-color: #a7f3d0; color: #166534; }
    .ckyc-doc-status.rejected { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
    .ckyc-history {
        border: 1px solid #e7e0d4;
        border-radius: 16px;
        padding: 18px;
        background: #fff;
        display: grid;
        gap: 12px;
    }
    .ckyc-history h3 {
        margin: 0;
        color: #0b2e20;
        font-size: 18px;
        font-weight: 900;
    }
    .ckyc-history-row {
        border: 1px solid #e7e0d4;
        border-radius: 12px;
        background: #fbfcfa;
        padding: 12px;
        display: grid;
        gap: 7px;
    }
    .ckyc-history-top {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        flex-wrap: wrap;
        color: #0b2e20;
        font-weight: 900;
    }
    .ckyc-history-meta {
        color: #66756f;
        font-size: 12px;
        font-weight: 700;
    }
    .ckyc-history-chip {
        display: inline-flex;
        border-radius: 999px;
        padding: 5px 8px;
        margin: 0 4px 4px 0;
        background: #eef8f2;
        border: 1px solid #cfe0d7;
        color: #0f5b42;
        font-size: 11px;
        font-weight: 900;
    }
    @media (max-width: 900px) {
        .ckyc-grid { grid-template-columns: 1fr; }
        .ckyc-head, .ckyc-nav, .ckyc-body { padding-left: 16px; padding-right: 16px; }
        .ckyc-head h2 { font-size: 28px; }
    }
</style>
@endpush

@section('content')
@php
    $schema = $kycPayload['schema'] ?? ['sections' => []];
    $sections = $schema['sections'] ?? [];
    $documentReviews = $kycPayload['booking_document_reviews'] ?? [];
    $financeKycHistory = collect((array) $siteVisit->booking_activity_log)
        ->filter(fn ($entry) => is_array($entry) && in_array(($entry['action'] ?? ''), ['finance_kyc_overwrite', 'finance_kyc_updated'], true))
        ->reverse()
        ->take(5)
        ->values();
    $latestFinanceOverwrite = $financeKycHistory->firstWhere('action', 'finance_kyc_overwrite');

    $extractOriginalPath = function (?string $url): string {
        $url = (string) $url;
        if ($url === '') return '';
        $parts = parse_url($url);
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
            if (!empty($query['path'])) return urldecode((string) $query['path']);
        }
        return urldecode($parts['path'] ?? $url);
    };

    $fileName = fn (?string $url): string => basename($extractOriginalPath($url) ?: 'file');
    $fileExt = fn (?string $url): string => strtolower(pathinfo($extractOriginalPath($url), PATHINFO_EXTENSION));
    $isImage = fn (?string $url): bool => in_array($fileExt($url), ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'svg'], true);
    $isPdf = fn (?string $url): bool => $fileExt($url) === 'pdf';

    $fieldMap = function (array $section): array {
        return collect($section['fields'] ?? [])->mapWithKeys(fn (array $field) => [($field['field_key'] ?? '') => $field])->all();
    };

    $selectedBookingType = trim((string) data_get($siteVisit->unit_details ?? [], 'booking_type', ''));
    $shouldRenderField = function (?array $field) use ($selectedBookingType): bool {
        if (!$field) return false;
        $condition = $field['visibility_condition'] ?? null;
        if (!is_array($condition) || $condition === []) return true;

        foreach ($condition as $key => $expected) {
            $currentValue = $key === 'booking_type' ? $selectedBookingType : null;
            $expectedValues = is_array($expected) ? $expected : [$expected];
            if (!in_array($currentValue, $expectedValues, true)) {
                return false;
            }
        }

        return true;
    };

    $renderValue = function (?array $field, bool $full = false) {
        if (!$field) return '';
        $value = $field['value'] ?? null;
        if (is_array($value)) {
            $value = implode(', ', array_filter($value));
        }
        $class = $full ? 'ckyc-field full' : 'ckyc-field';
        return '
            <div class="' . $class . '">
                <div class="ckyc-label">' . e($field['label'] ?? $field['field_key'] ?? '') . '</div>
                <div class="ckyc-value">' . e(($value !== null && $value !== '') ? (string) $value : 'N/A') . '</div>
            </div>
        ';
    };

    $renderCheckGroup = function (string $title, array $fields) {
        $items = collect($fields)->filter()->all();
        if (!$items) return '';
        $html = '';
        foreach ($items as $field) {
            $checked = !empty($field['value']);
            $html .= '
                <span class="ckyc-check ' . ($checked ? 'is-checked' : '') . '">
                    <i class="fas ' . ($checked ? 'fa-check' : 'fa-minus') . '"></i>
                    ' . e($field['label'] ?? $field['field_key'] ?? '') . '
                </span>
            ';
        }
        return '
            <div class="ckyc-group-card">
                <h4>' . e($title) . '</h4>
                <div class="ckyc-check-grid">' . $html . '</div>
            </div>
        ';
    };

    $renderDocs = function (array $files, string $emptyText, string $groupKey = '') use ($fileName, $isImage, $isPdf, $extractOriginalPath, $documentReviews) {
        if (!$files) {
            return '<div class="ckyc-value">' . e($emptyText) . '</div>';
        }

        $html = '<div class="ckyc-doc-grid">';
        foreach ($files as $index => $file) {
            $review = $groupKey !== '' ? (($documentReviews[$groupKey][$extractOriginalPath($file)] ?? null)) : null;
            $status = strtolower((string) ($review['status'] ?? 'pending'));
            $statusLabel = $status === 'verified' ? 'Verified' : ($status === 'rejected' ? 'Rejected' : 'Pending');
            $preview = '<div class="ckyc-doc-fallback"><i class="fas fa-file-lines"></i><div>' . e($fileName($file)) . '</div></div>';
            if ($isImage($file)) {
                $preview = '<img src="' . e($file) . '" alt="' . e($fileName($file)) . '">';
            } elseif ($isPdf($file)) {
                $preview = '<iframe src="' . e($file) . '#toolbar=0&navpanes=0&scrollbar=0"></iframe>';
            }

            $html .= '
                <div class="ckyc-doc-card">
                    <div class="ckyc-doc-preview">' . $preview . '</div>
                    <div class="ckyc-doc-meta">
                        <div class="ckyc-doc-name">' . e($fileName($file)) . '</div>
                        <span class="ckyc-doc-status ' . e($status) . '">' . e($statusLabel) . '</span>
                        <a href="' . e($file) . '" target="_blank" rel="noopener" class="ckyc-btn">
                            <i class="fas fa-up-right-from-square"></i> Open File ' . ($index + 1) . '
                        </a>
                    </div>
                </div>
            ';
        }
        return $html . '</div>';
    };
@endphp

<div class="ckyc-shell">
    <div class="ckyc-card">
        <div class="ckyc-head">
            <div>
                <div class="ckyc-kicker">CRM Verification</div>
                <h2>{{ $kycPayload['customer_name'] ?? 'Closer KYC Review' }}</h2>
                <p>{{ $kycPayload['project'] ?? 'Project not filled' }} booking KYC review</p>
                <div class="ckyc-meta">
                    <span class="ckyc-chip"><i class="fas fa-building"></i>{{ $kycPayload['project'] ?? 'Project not filled' }}</span>
                    <span class="ckyc-chip"><i class="fas fa-phone"></i>{{ $kycPayload['phone'] ?? 'Phone not filled' }}</span>
                    <span class="ckyc-chip"><i class="fas fa-user-check"></i>{{ $siteVisit->assignedTo?->name ?: ($siteVisit->creator?->name ?: 'Unassigned') }}</span>
                </div>
            </div>
            <div class="ckyc-actions">
                <span class="ckyc-status"><i class="fas fa-id-card"></i>{{ $kycPayload['status'] ?? 'KYC details' }}</span>
                @if($siteVisit->kyc_last_corrected_at)
                    <span class="ckyc-correction">
                        <i class="fas fa-pen-to-square"></i>
                        Last corrected {{ $siteVisit->kyc_last_corrected_at->format('d M Y h:i A') }}
                    </span>
                @endif
                <a href="{{ route('crm.verifications') }}" class="ckyc-btn"><i class="fas fa-arrow-left"></i> Back</a>
            </div>
        </div>

        <nav class="ckyc-nav" aria-label="KYC sections">
            @foreach($sections as $index => $section)
                <a href="#ckyc-section-{{ $index + 1 }}">
                    <span>{{ $index + 1 }}</span>
                    {{ $section['label'] ?? 'Section' }}
                </a>
            @endforeach
        </nav>

        <div class="ckyc-body">
            @if($financeKycHistory->isNotEmpty())
                <section class="ckyc-history" id="ckyc-finance-history">
                    <h3>Finance Change History</h3>
                    @foreach($financeKycHistory as $entry)
                        @php
                            $changes = collect($entry['changes'] ?? [])->take(8);
                            $changedCount = (int) ($entry['changed_count'] ?? $changes->count());
                        @endphp
                        <div class="ckyc-history-row">
                            <div class="ckyc-history-top">
                                <span>{{ ($entry['action'] ?? '') === 'finance_kyc_overwrite' ? 'Finance overwrite' : 'Finance KYC update' }}</span>
                                <span class="ckyc-history-meta">{{ !empty($entry['at']) ? \Carbon\Carbon::parse($entry['at'])->format('d M Y h:i A') : 'Time not captured' }}</span>
                            </div>
                            <div class="ckyc-history-meta">
                                {{ $entry['user_name'] ?? 'Finance user' }}
                                @if(!empty($entry['remark']))
                                    - {{ $entry['remark'] }}
                                @endif
                            </div>
                            @if($changes->isNotEmpty())
                                <div>
                                    @foreach($changes as $change)
                                        <span class="ckyc-history-chip">{{ $change['label'] ?? $change['field'] ?? 'Field' }}</span>
                                    @endforeach
                                    @if($changedCount > $changes->count())
                                        <span class="ckyc-history-chip">+{{ $changedCount - $changes->count() }} more</span>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </section>
            @endif

            @foreach($sections as $section)
                <section class="ckyc-section" id="ckyc-section-{{ $loop->iteration }}">
                    <h3>{{ $section['label'] ?? 'Section' }}</h3>
                    <div class="ckyc-grid">
                        @php
                            $sectionLabel = $section['label'] ?? '';
                            $sectionFieldMap = $fieldMap($section);
                        @endphp

                        @if(in_array($sectionLabel, ['1. Sole / First Applicant', '2. Second / Joint Applicant / Nominee'], true))
                            @php $prefix = $sectionLabel === '1. Sole / First Applicant' ? 'primary_applicant_' : 'joint_applicant_'; @endphp

                            {!! $renderValue($sectionFieldMap[$prefix . 'name'] ?? null) !!}
                            {!! $renderValue($sectionFieldMap[$prefix . 'relation_name'] ?? null) !!}
                            {!! $renderValue($sectionFieldMap[$prefix . 'date_of_birth'] ?? null) !!}
                            {!! $renderValue($sectionFieldMap[$prefix . 'nationality'] ?? null) !!}
                            {!! $renderCheckGroup('Occupation', [
                                $sectionFieldMap[$prefix . 'occupation_service'] ?? null,
                                $sectionFieldMap[$prefix . 'occupation_professional'] ?? null,
                                $sectionFieldMap[$prefix . 'occupation_housewife'] ?? null,
                                $sectionFieldMap[$prefix . 'occupation_business'] ?? null,
                            ]) !!}
                            {!! $renderValue($sectionFieldMap[$prefix . 'occupation_any_other'] ?? null, true) !!}
                            {!! $renderCheckGroup('Residential Status', [
                                $sectionFieldMap[$prefix . 'resident_indian'] ?? null,
                            ]) !!}
                            {!! $renderValue($sectionFieldMap[$prefix . 'resident_other'] ?? null, true) !!}
                            {!! $renderCheckGroup('Marital Status', [
                                $sectionFieldMap[$prefix . 'marital_status_married'] ?? null,
                                $sectionFieldMap[$prefix . 'marital_status_unmarried'] ?? null,
                            ]) !!}
                            {!! $renderValue($sectionFieldMap[$prefix . 'pan_no'] ?? null) !!}
                            @if($prefix === 'primary_applicant_')
                                {!! $renderValue($sectionFieldMap[$prefix . 'aadhaar_no'] ?? null) !!}
                            @endif
                            {!! $renderValue($sectionFieldMap[$prefix . 'address'] ?? null, true) !!}
                            {!! $renderValue($sectionFieldMap[$prefix . 'communication_address'] ?? null, true) !!}
                            {!! $renderValue($sectionFieldMap[$prefix . 'city'] ?? null) !!}
                            {!! $renderValue($sectionFieldMap[$prefix . 'state'] ?? null) !!}
                            {!! $renderValue($sectionFieldMap[$prefix . 'pin'] ?? null) !!}
                            {!! $renderValue($sectionFieldMap[$prefix . 'email'] ?? null) !!}
                            {!! $renderValue($sectionFieldMap[$prefix . 'mobile_no'] ?? null) !!}
                            {!! $renderValue($sectionFieldMap[$prefix . 'tel_no'] ?? null) !!}

                            @if($prefix === 'joint_applicant_')
                                <div class="ckyc-group-card">
                                    <h4>Company / Firm Details</h4>
                                    <div class="ckyc-grid">
                                        {!! $renderValue($sectionFieldMap['company_name'] ?? null) !!}
                                        {!! $renderValue($sectionFieldMap['company_date_of_incorporation'] ?? null) !!}
                                        {!! $renderValue($sectionFieldMap['company_registered_address'] ?? null, true) !!}
                                        {!! $renderValue($sectionFieldMap['company_incorporation_no'] ?? null) !!}
                                        {!! $renderValue($sectionFieldMap['company_pan_no'] ?? null) !!}
                                        {!! $renderValue($sectionFieldMap['company_gst_no'] ?? null) !!}
                                        {!! $renderValue($sectionFieldMap['company_business_nature'] ?? null, true) !!}
                                        {!! $renderValue($sectionFieldMap['company_signatory_name'] ?? null) !!}
                                        {!! $renderValue($sectionFieldMap['company_signatory_relation'] ?? null) !!}
                                        {!! $renderValue($sectionFieldMap['company_signatory_designation'] ?? null) !!}
                                        {!! $renderValue($sectionFieldMap['company_email'] ?? null) !!}
                                        {!! $renderValue($sectionFieldMap['company_contact_1'] ?? null) !!}
                                        {!! $renderValue($sectionFieldMap['company_contact_2'] ?? null) !!}
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
                                    $value = $field['value'] ?? null;
                                @endphp

                                @if($fieldType === 'file' && $fieldKey === 'kyc_documents')
                                    <div class="ckyc-field full">
                                        <div class="ckyc-label">{{ $field['label'] ?? 'KYC Documents' }}</div>
                                        {!! $renderDocs(array_filter((array) $value), 'No KYC documents uploaded.', 'kyc_documents') !!}
                                    </div>
                                @elseif($fieldType === 'file' && $fieldKey === 'proof_photos')
                                    <div class="ckyc-field full">
                                        <div class="ckyc-label">{{ $field['label'] ?? 'Proof Photos' }}</div>
                                        {!! $renderDocs(array_filter((array) $value), 'No proof photos uploaded.', 'proof_photos') !!}
                                    </div>
                                @elseif($fieldType === 'file' && $fieldKey === 'booking_payment_proofs')
                                    <div class="ckyc-field full">
                                        <div class="ckyc-label">{{ $field['label'] ?? 'Booking Payment Proofs' }}</div>
                                        {!! $renderDocs(array_filter((array) $value), 'No booking payment proofs uploaded.', 'booking_payment_proofs') !!}
                                    </div>
                                @elseif($fieldType === 'checkbox' && !empty($field['options']))
                                    <div class="ckyc-group-card">
                                        <h4>{{ $field['label'] ?? $fieldKey }}</h4>
                                        <div class="ckyc-check-grid">
                                            @foreach(($field['options'] ?? []) as $option)
                                                @php $checked = in_array($option, (array) $value, true); @endphp
                                                <span class="ckyc-check {{ $checked ? 'is-checked' : '' }}">
                                                    <i class="fas {{ $checked ? 'fa-check' : 'fa-minus' }}"></i>{{ $option }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>
                                @elseif($fieldType === 'checkbox')
                                    {!! $renderCheckGroup($field['label'] ?? $fieldKey, [$field]) !!}
                                @else
                                    {!! $renderValue($field, in_array($fieldType, ['textarea'], true)) !!}
                                @endif
                            @endforeach
                        @endif
                    </div>
                </section>
            @endforeach
        </div>
    </div>
</div>
@endsection

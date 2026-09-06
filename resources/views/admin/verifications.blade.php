@extends('crm.verifications')

@section('title', 'Admin Verifications - ' . brand_name())
@section('page-title', '')
@section('page-subtitle', '')

@push('styles')
<style>
    .layout-admin .main-header {
        display: none;
    }

    .layout-admin .content-wrapper,
    .layout-admin .main-content {
        background: #f7f6f2;
    }

    .layout-admin .crm-verification-shell {
        gap: 12px;
        margin-top: 0;
        max-width: none;
        padding-top: 0;
    }

    .layout-admin .crm-hero {
        display: none !important;
    }

    .layout-admin .crm-surface {
        background: #fff;
        border: 1px solid #dfe6e1;
        border-radius: 12px;
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.05);
        padding: 16px 18px 18px;
    }

    .layout-admin .crm-tabbar {
        align-items: center;
        border-bottom: 1px solid #e7ebe8;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 14px;
        padding-bottom: 12px;
    }

    .layout-admin .tab {
        align-items: center;
        background: #fff;
        border: 1px solid #dce5df;
        border-radius: 8px;
        box-shadow: none;
        color: #334155;
        display: inline-flex;
        gap: 7px;
        min-height: 38px;
        margin-bottom: 0;
        padding: 8px 12px;
        font-size: 13px;
        font-weight: 700;
        line-height: 1;
    }

    .layout-admin .tab i {
        color: #64748b;
        font-size: 13px;
        margin-right: 0 !important;
    }

    .layout-admin .tab.active {
        background: #0b7557;
        border-color: #0b7557;
        box-shadow: 0 6px 14px rgba(11, 117, 87, 0.16);
        color: #fff;
    }

    .layout-admin .tab.active i {
        color: #fff;
    }

    .layout-admin .crm-badge-soft {
        align-items: center;
        background: rgba(255, 255, 255, 0.22);
        border-radius: 999px;
        color: inherit;
        display: inline-flex;
        font-size: 11px;
        height: 22px;
        justify-content: center;
        min-width: 24px;
        padding: 0 7px;
    }

    .layout-admin .tab:not(.active) .crm-badge-soft {
        background: #edf4ff;
        color: #2563eb;
    }

    .layout-admin .prospects-grid {
        background: #fff;
        border: 1px solid #dfe6e1;
        border-radius: 10px;
        display: flex;
        flex-direction: column;
        gap: 0;
        overflow-x: auto;
        padding-top: 0;
    }

    .layout-admin .admin-excel-header {
        background: #f4f7f5;
        border-bottom: 1px solid #dfe6e1;
        color: #475569;
        display: grid;
        font-size: 11px;
        font-weight: 800;
        grid-template-columns: 1.1fr 0.85fr 0.9fr 1.05fr 1.45fr 0.95fr;
        letter-spacing: 0.04em;
        min-width: 980px;
        text-transform: uppercase;
    }

    .layout-admin .admin-excel-header span {
        border-right: 1px solid #dfe6e1;
        padding: 10px 12px;
    }

    .layout-admin .admin-excel-header span:last-child {
        border-right: 0;
        text-align: center;
    }

    .layout-admin .verification-card {
        background: #fff;
        border: 0;
        border-bottom: 1px solid #e7ebe8;
        border-left: 3px solid #d97706;
        border-radius: 0;
        box-shadow: none;
        display: grid;
        grid-template-columns: 1.1fr 0.85fr 0.9fr 1.05fr 1.45fr 0.95fr;
        min-width: 980px;
        padding: 0;
    }

    .layout-admin .verification-card:last-child {
        border-bottom: 0;
    }

    .layout-admin .verification-card:hover {
        transform: none;
        background: #fbfdfc;
        box-shadow: none;
    }

    .layout-admin .verification-card.verified {
        border-left-color: #059669;
    }

    .layout-admin .verification-card.rejected {
        border-left-color: #dc2626;
    }

    .layout-admin .verification-header {
        margin-bottom: 10px;
    }

    .layout-admin .verification-info h3 {
        color: #0f172a;
        font-size: 15px;
        line-height: 1.25;
        margin-bottom: 0 !important;
    }

    .layout-admin .verification-card .verification-info {
        display: contents !important;
    }

    .layout-admin .verification-card .verification-info > h3,
    .layout-admin .verification-card .verification-info > .card-detail-row {
        align-items: center;
        border-bottom: 0 !important;
        border-right: 1px solid #eef2ef;
        display: flex;
        min-height: 58px;
        min-width: 0;
        overflow: hidden;
        padding: 8px 12px !important;
    }

    .layout-admin .verification-card .verification-info > h3 {
        color: #063a1c !important;
        font-size: 14px !important;
        font-weight: 800 !important;
        grid-column: 1;
        grid-row: 1;
        margin: 0 !important;
    }

    .layout-admin .verification-card .verification-info > .card-detail-row:nth-of-type(1) {
        grid-column: 2;
        grid-row: 1;
    }

    .layout-admin .verification-card .verification-info > .card-detail-row:nth-of-type(2) {
        grid-column: 3;
        grid-row: 1;
    }

    .layout-admin .verification-card .verification-info > .card-detail-row:nth-of-type(3) {
        grid-column: 4;
        grid-row: 1;
    }

    .layout-admin .verification-card .verification-info > .card-detail-row:nth-of-type(n+4) {
        grid-column: 5;
        grid-row: 1;
    }

    .layout-admin .verification-card .verification-info > .card-detail-row:nth-of-type(n+5) {
        display: none;
    }

    .layout-admin .verification-card .verification-info > .card-detail-row:nth-of-type(4):has(+ .card-detail-row) {
        align-items: flex-start;
        flex-direction: column;
        gap: 4px;
    }

    .layout-admin .verification-info p,
    .layout-admin .card-detail-row {
        color: #475569;
        font-size: 12.5px;
        line-height: 1.4;
        margin-bottom: 0 !important;
        margin-top: 0 !important;
    }

    .layout-admin .card-detail-row i {
        color: #64748b !important;
        flex: 0 0 18px;
        font-size: 12px;
        width: 18px !important;
    }

    .layout-admin .card-detail-row span {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .layout-admin .card-detail-row strong {
        flex: 0 0 auto;
        font-size: 12px;
    }

    .layout-admin .verification-card > div:last-child {
        align-content: center;
        border-left: 1px solid #eef2ef;
        border-top: 0 !important;
        display: grid !important;
        gap: 6px !important;
        grid-column: 6;
        grid-row: 1;
        margin-top: 0 !important;
        padding: 8px 10px !important;
    }

    .layout-admin .verification-card > div:last-child > div {
        display: grid !important;
        gap: 6px !important;
        grid-template-columns: 1fr 1fr;
    }

    .layout-admin .btn-view-details {
        background: #0b5f49;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 700;
        min-height: 32px;
        padding: 7px 10px;
    }

    .layout-admin .btn-view-details:hover {
        background: #084d3a;
    }

    .layout-admin .btn-success {
        background: #0b7557;
        border-radius: 6px;
        font-size: 12px !important;
        min-height: 32px;
    }

    .layout-admin .btn-danger {
        background: #dc2626;
        border-radius: 6px;
        font-size: 12px !important;
        min-height: 32px;
    }

    .layout-admin .tab-content {
        min-height: 250px;
    }

    .layout-admin .tab-content > div {
        min-height: inherit;
    }

    .layout-admin .tab-content > div > .empty-state {
        min-height: 250px;
    }

    .layout-admin .empty-state {
        align-items: center;
        background: #fbfcfb;
        border: 1px dashed #d4ddd8;
        border-radius: 10px;
        color: #64748b;
        display: flex;
        flex-direction: column;
        justify-content: center;
        padding: 34px 20px;
    }

    .layout-admin .empty-state i {
        background: #e8ecef;
        border-radius: 999px;
        color: #94a3b8;
        font-size: 28px;
        height: 58px;
        line-height: 58px;
        margin-bottom: 12px;
        width: 58px;
    }

    .layout-admin .empty-state h3 {
        color: #111827;
        font-size: 17px;
        font-weight: 800;
        margin-bottom: 6px;
    }

    .layout-admin .empty-state p {
        color: #94a3b8;
        font-size: 14px;
        margin: 0;
    }

    @media (max-width: 767px) {
        .layout-admin .crm-surface {
            border-radius: 10px;
            padding: 12px;
        }

        .layout-admin .crm-tabbar {
            flex-wrap: nowrap;
            overflow-x: auto;
            padding-bottom: 10px;
        }

        .layout-admin .tab {
            flex: 0 0 auto;
            min-width: max-content;
            padding: 9px 11px;
        }

        .layout-admin .admin-excel-header,
        .layout-admin .verification-card {
            min-width: 900px;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const headers = ['Lead', 'Phone', 'Date', 'Created By', 'Property / Notes', 'Action'];

        function addExcelHeaders() {
            document.querySelectorAll('.layout-admin .prospects-grid').forEach(function (grid) {
                if (grid.querySelector(':scope > .admin-excel-header')) {
                    return;
                }

                const header = document.createElement('div');
                header.className = 'admin-excel-header';
                header.innerHTML = headers.map(function (label) {
                    return '<span>' + label + '</span>';
                }).join('');
                grid.prepend(header);
            });
        }

        addExcelHeaders();

        const observer = new MutationObserver(addExcelHeaders);
        observer.observe(document.body, { childList: true, subtree: true });
    });
</script>
@endpush

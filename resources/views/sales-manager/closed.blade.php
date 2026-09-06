@extends('sales-manager.layout')

@section('title', 'Closed Leads - Senior Manager')
@section('page-title', 'Closed')

@push('styles')
<style>
    /* ====== Closed / Closer Pipeline — professional CRM design ====== */
    .closed-page-shell { display: grid; gap: 14px; }

    /* Hero — compact banner with inline stats */
    .closed-hero {
        background: linear-gradient(135deg, #0b4d2b 0%, #17613e 55%, #1f7a50 100%);
        border-radius: 16px;
        padding: 18px 20px;
        color: #fff;
        box-shadow: 0 10px 24px rgba(6, 58, 28, 0.14);
        border: 1px solid rgba(255, 255, 255, 0.08);
    }
    .closed-hero-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 14px;
        flex-wrap: wrap;
    }
    .closed-hero-title {
        font-size: 1.35rem;
        font-weight: 800;
        letter-spacing: -0.02em;
        margin-bottom: 4px;
        line-height: 1.2;
    }
    .closed-hero-copy {
        max-width: 640px;
        color: rgba(232, 245, 236, 0.88);
        font-size: 0.82rem;
        line-height: 1.5;
    }
    .closed-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 11px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.14);
        color: #f5fff7;
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }
    .closed-stats {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
        margin-top: 14px;
    }
    .closed-stat {
        background: rgba(255, 255, 255, 0.09);
        border: 1px solid rgba(255, 255, 255, 0.14);
        border-radius: 12px;
        padding: 10px 12px;
    }
    .closed-stat-label {
        color: rgba(231, 245, 235, 0.85);
        font-size: 0.62rem;
        font-weight: 700;
        margin-bottom: 4px;
        text-transform: uppercase;
        letter-spacing: 0.1em;
    }
    .closed-stat-value {
        font-size: 1.35rem;
        font-weight: 800;
        letter-spacing: -0.02em;
        line-height: 1.1;
    }

    /* Pipeline tabs — clean chip row */
    .closed-pipeline-tabs {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        padding: 10px;
        background: #ffffff;
        border: 1px solid #e5ece8;
        border-radius: 12px;
        box-shadow: 0 1px 2px rgba(7, 46, 32, 0.04);
    }
    .closed-pipeline-tab {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        min-height: 36px;
        padding: 0 13px;
        border-radius: 8px;
        border: 1px solid #dbe4df;
        background: #f6faf7;
        color: #2a4a3b;
        cursor: pointer;
        font-size: 0.8rem;
        font-weight: 700;
        transition: all 0.18s ease;
    }
    .closed-pipeline-tab:hover { background: #eaf3ed; }
    .closed-pipeline-tab.active {
        background: linear-gradient(135deg, #0f5132 0%, #14532d 100%);
        color: #fff;
        border-color: transparent;
        box-shadow: 0 4px 10px rgba(11, 77, 43, 0.18);
    }
    .closed-pipeline-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 20px;
        height: 20px;
        padding: 0 5px;
        border-radius: 999px;
        background: rgba(15, 81, 50, 0.1);
        color: #0f5132;
        font-size: 0.68rem;
        font-weight: 800;
    }
    .closed-pipeline-tab.active .closed-pipeline-count {
        background: rgba(255, 255, 255, 0.2);
        color: #ffffff;
    }

    /* Filter bar */
    .closed-filter-bar {
        display: grid;
        grid-template-columns: minmax(0, 1.4fr) repeat(2, minmax(170px, 0.7fr));
        gap: 10px;
        padding: 12px 14px;
        background: #ffffff;
        border-radius: 12px;
        border: 1px solid #e5ece8;
        box-shadow: 0 1px 2px rgba(7, 46, 32, 0.04);
    }
    .closed-field {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }
    .closed-field label {
        font-size: 0.62rem;
        font-weight: 800;
        color: #55706a;
        text-transform: uppercase;
        letter-spacing: 0.1em;
    }
    .closed-input,
    .closed-select {
        width: 100%;
        min-height: 40px;
        border-radius: 9px;
        border: 1px solid #dce4df;
        background: #fff;
        padding: 0 12px;
        font-size: 0.86rem;
        font-family: inherit;
        color: #163124;
        transition: border-color 0.18s ease, box-shadow 0.18s ease;
    }
    .closed-input:focus,
    .closed-select:focus {
        outline: none;
        border-color: #17613e;
        box-shadow: 0 0 0 3px rgba(23, 97, 62, 0.12);
    }

    /* Results head */
    .closed-results-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 2px;
    }
    .closed-results-title {
        font-size: 1.05rem;
        font-weight: 800;
        color: #0b2e24;
        letter-spacing: -0.01em;
    }
    .closed-results-copy {
        font-size: 0.78rem;
        color: #6a7f75;
        margin-top: 1px;
    }

    /* Loading & Empty */
    #closedLoadingState,
    #closedEmptyState {
        padding: 28px 20px;
        text-align: center;
        border-radius: 12px;
        background: #ffffff;
        border: 1px dashed #d8e1db;
        color: #6a7f75;
        font-size: 0.88rem;
        font-weight: 600;
    }

    /* Grid */
    #closedLeadsGrid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
        align-items: stretch;
    }

    /* Card */
    .closed-card {
        display: flex;
        flex-direction: column;
        gap: 10px;
        padding: 12px 13px 11px;
        border-radius: 14px;
        background: #ffffff;
        border: 1px solid #e5ece8;
        box-shadow: 0 1px 2px rgba(7, 46, 32, 0.04);
        min-height: 100%;
        transition: box-shadow .18s ease, border-color .18s ease;
        position: relative;
    }
    .closed-card:hover {
        box-shadow: 0 10px 24px rgba(7, 46, 32, 0.09);
        border-color: #c9dbd1;
    }

    .closed-card-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
    }
    .closed-card-identity { min-width: 0; flex: 1; display: flex; align-items: center; gap: 10px; }
    .closed-card-avatar {
        flex: none;
        width: 38px; height: 38px;
        border-radius: 10px;
        display: grid; place-items: center;
        background: linear-gradient(135deg, #d9ece1, #ecf5ef);
        color: #0f5132;
        font-weight: 800;
        font-size: 0.82rem;
        border: 1px solid #d3e6da;
    }
    .closed-card-identity-text { min-width: 0; }
    .closed-card-name {
        font-size: 0.98rem;
        font-weight: 700;
        color: #0b2e24;
        line-height: 1.2;
        letter-spacing: -0.01em;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-bottom: 0;
    }
    .closed-card-sub {
        color: #5a6e66;
        font-size: 0.76rem;
        font-weight: 600;
        margin-top: 1px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* Stage badge */
    .closed-stage {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 8px;
        border-radius: 6px;
        font-size: 10.5px;
        font-weight: 800;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        white-space: nowrap;
        flex: none;
    }
    .closed-stage i { font-size: 9.5px; }
    .closed-stage.requested { background: #dbeafe; color: #1d4ed8; }
    .closed-stage.verified  { background: #dcfce7; color: #166534; }
    .closed-stage.pending   { background: #fef3c7; color: #92400e; }
    .closed-stage.rejected  { background: #fee2e2; color: #991b1b; }
    .closed-stage.submitted { background: #ede9fe; color: #5b21b6; }

    /* Meta grid */
    .closed-meta {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 6px 10px;
        padding: 9px 10px;
        margin: 0;
        background: #f6faf7;
        border: 1px solid #e5ece8;
        border-radius: 10px;
    }
    .closed-meta-item {
        padding: 0;
        border: none;
        background: transparent;
        min-width: 0;
    }
    .closed-meta-label {
        display: block;
        font-size: 0.6rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: #8a9790;
        margin-bottom: 2px;
    }
    .closed-meta-value {
        color: #1e372b;
        font-size: 0.8rem;
        font-weight: 600;
        line-height: 1.3;
        word-break: break-word;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    /* Remark / requirements block */
    .closed-requirements {
        padding: 9px 11px;
        border-radius: 10px;
        background: #fbfcfb;
        border: 1px solid #eaefe9;
        border-left: 3px solid #0f5132;
        color: #3b4f46;
        font-size: 0.8rem;
        line-height: 1.45;
        min-height: 0;
    }

    /* Actions */
    .closed-actions {
        margin-top: auto;
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        padding-top: 2px;
    }
    .closed-btn {
        flex: 1 1 calc(50% - 3px);
        min-width: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        min-height: 36px;
        padding: 0 10px;
        border-radius: 9px;
        border: 1px solid transparent;
        text-decoration: none;
        font-size: 0.78rem;
        font-weight: 700;
        white-space: nowrap;
        transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease, filter 0.15s ease;
        cursor: pointer;
    }
    .closed-btn i { font-size: 0.78rem; }
    .closed-btn-primary {
        background: linear-gradient(135deg, #0f5132 0%, #14532d 100%);
        color: #fff;
        box-shadow: 0 4px 10px rgba(15, 81, 50, 0.22);
    }
    .closed-btn-primary:hover { filter: brightness(1.08); color: #fff; }
    .closed-btn-secondary {
        background: #fff;
        color: #0b2e24;
        border-color: #dbe4df;
    }
    .closed-btn-secondary:hover { background: #f6f9f7; }
    .closed-btn-warning {
        background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
        color: #fff;
        box-shadow: 0 4px 10px rgba(217, 119, 6, 0.22);
    }
    .closed-btn-warning:hover { filter: brightness(1.08); color: #fff; }

    /* Pagination */
    .closed-pagination {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 4px;
        padding: 10px 12px;
        background: #ffffff;
        border: 1px solid #e5ece8;
        border-radius: 12px;
    }
    .closed-pagination-controls {
        display: flex;
        gap: 5px;
        flex-wrap: wrap;
    }
    .closed-page-btn {
        min-width: 34px;
        min-height: 34px;
        padding: 0 11px;
        border-radius: 8px;
        border: 1px solid #dbe4df;
        background: #fff;
        color: #1a3528;
        font-weight: 700;
        font-size: 0.82rem;
        cursor: pointer;
        transition: all 0.15s;
    }
    .closed-page-btn:hover:not(:disabled) { background: #f6f9f7; }
    .closed-page-btn.active {
        background: linear-gradient(135deg, #0f5132 0%, #14532d 100%);
        color: #fff;
        border-color: transparent;
    }
    .closed-page-btn:disabled {
        opacity: 0.45;
        cursor: not-allowed;
    }

    /* Form fields inside modals */
    .closed-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }
    .closed-form-field {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .closed-form-field.full { grid-column: 1 / -1; }
    .closed-form-field label {
        font-size: 0.72rem;
        font-weight: 800;
        color: #284538;
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }
    .closed-kyc-section {
        grid-column: 1 / -1;
        border: 1px solid #e2eae5;
        border-radius: 14px;
        background: #fcfefd;
        padding: 14px;
        display: grid;
        gap: 12px;
    }
    .closed-kyc-section-title {
        font-size: 0.9rem;
        font-weight: 800;
        color: #163124;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .closed-kyc-inline-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }
    .closed-kyc-inline-grid.form-structured {
        grid-template-columns: 1fr;
        gap: 18px;
    }
    .closed-kyc-compact-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
    }
    .closed-kyc-field-row {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }
    .closed-kyc-field-row.cols-3 {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
    .closed-kyc-field-row.cols-4 {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
    .closed-kyc-group-block {
        display: grid;
        grid-template-columns: 180px minmax(0, 1fr);
        align-items: center;
        gap: 10px;
        padding: 14px;
        border: 1px solid #e1e8e3;
        border-radius: 14px;
        background: linear-gradient(180deg, #fbfdfc 0%, #f6faf7 100%);
    }
    .closed-kyc-group-title {
        font-size: 0.8rem;
        font-weight: 800;
        color: #284538;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }
    .closed-check-grid.inline-chips {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px 10px;
    }
    .closed-check-grid.inline-chips .closed-form-field {
        display: block;
        width: auto;
        min-width: 0;
    }
    .closed-check-grid.inline-chips .closed-check-item {
        display: inline-flex;
        align-items: center;
        width: auto;
        min-height: 36px;
        border-radius: 999px;
        padding: 8px 12px;
        background: #fff;
        border: 1px solid #d8e2dc;
        white-space: nowrap;
        margin: 0 !important;
    }
    .closed-check-grid.inline-chips .closed-check-item input {
        flex: 0 0 auto;
        accent-color: #17613e;
    }
    .closed-check-grid.inline-chips + .closed-form-field {
        grid-column: 1 / -1;
    }
    .closed-form-field.is-inline-compact label {
        margin-bottom: 0;
    }
    .closed-kyc-check-group {
        display: grid;
        gap: 8px;
    }
    .closed-kyc-check-options {
        display: flex;
        flex-wrap: wrap;
        gap: 10px 14px;
    }
    .closed-kyc-check-option {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        font-size: 0.84rem;
        color: #244437;
        font-weight: 600;
    }
    .closed-kyc-check-option input[type="checkbox"] {
        width: 16px;
        height: 16px;
        accent-color: #17613e;
    }
    .closed-kyc-line-input {
        border: none;
        border-bottom: 1px solid #c8d5cd;
        border-radius: 0;
        padding: 8px 0 6px;
        min-height: auto;
        background: transparent;
    }
    .closed-kyc-line-input:focus {
        box-shadow: none;
        border-color: #17613e;
    }
    .closed-textarea,
    .closed-file,
    .closed-number {
        width: 100%;
        border-radius: 9px;
        border: 1px solid #dce4df;
        background: #fff;
        padding: 10px 12px;
        font-size: 0.88rem;
        font-family: inherit;
        transition: border-color 0.18s ease, box-shadow 0.18s ease;
    }
    .closed-textarea:focus,
    .closed-file:focus,
    .closed-number:focus {
        outline: none;
        border-color: #17613e;
        box-shadow: 0 0 0 3px rgba(23, 97, 62, 0.12);
    }
    .closed-textarea { min-height: 110px; resize: vertical; }

    /* Modal */
    .closed-modal-shell {
        position: fixed;
        inset: 0;
        z-index: 1200;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 20px;
        background: rgba(7, 23, 15, 0.48);
        backdrop-filter: blur(6px);
    }
    .closed-modal-shell.show { display: flex; }
    .closed-modal-content {
        width: min(780px, 92vw);
        max-height: min(88vh, 920px);
        display: grid;
        grid-template-rows: auto minmax(0, 1fr);
        overflow: hidden;
        background: #fff;
        border-radius: 16px;
        border: 1px solid #e5ece8;
        box-shadow: 0 24px 50px rgba(10, 20, 12, 0.22);
    }
    #closedKycModal .closed-modal-content {
        width: min(1460px, 96vw);
        max-height: 96vh;
        border-radius: 22px;
        grid-template-rows: auto minmax(0, 1fr) auto;
    }
    #closedKycModal { z-index: 2200; }
    .closed-modal-head {
        padding: 16px 20px;
        background: #ffffff;
        border-bottom: 1px solid #eef2ef;
        display: flex;
        justify-content: space-between;
        align-items: start;
        gap: 16px;
        position: sticky;
        top: 0;
        z-index: 1;
    }
    .closed-modal-head h3 {
        font-size: 1.1rem !important;
        font-weight: 800 !important;
        color: #0b2e24 !important;
        letter-spacing: -0.01em;
        line-height: 1.2;
    }
    .closed-modal-head p { font-size: 0.8rem !important; color: #60786b !important; margin-top: 3px !important; }
    .closed-modal-head .btn.btn-danger {
        width: 32px; height: 32px;
        border-radius: 8px;
        padding: 0;
        display: inline-flex; align-items: center; justify-content: center;
        min-height: auto;
        font-size: 0.78rem;
    }
    .closed-modal-body {
        padding: 16px 20px 18px;
        display: grid;
        gap: 14px;
        overflow-y: auto;
    }
    #closedKycModal .closed-modal-head {
        padding: 20px 24px;
    }
    #closedKycModal .closed-modal-body {
        padding: 18px 24px 24px;
    }
    .closed-modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
        position: sticky;
        bottom: 0;
        padding-top: 10px;
        background: linear-gradient(180deg, rgba(255,255,255,0) 0%, #fff 24%);
    }
    #closedKycModal .closed-modal-actions {
        position: static;
        padding: 12px 24px;
        border-top: 1px solid #e5ece8;
        background: #fff;
        z-index: 2;
    }
    #closedKycModal .closed-modal-actions .closed-btn[hidden] { display: none !important; }
    .closed-kyc-stepper {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
    }
    .closed-kyc-stepper[hidden] { display: none; }
    .closed-kyc-step-pill {
        border: 1px solid #dbe7df;
        background: #f7fbf8;
        border-radius: 14px;
        padding: 12px 14px;
        display: grid;
        gap: 2px;
        text-align: left;
        cursor: pointer;
        transition: border-color 0.18s ease, background 0.18s ease, box-shadow 0.18s ease;
    }
    .closed-kyc-step-pill.is-active {
        border-color: #17613e;
        background: #eef8f1;
        box-shadow: 0 0 0 3px rgba(23, 97, 62, 0.10);
    }
    .closed-kyc-step-pill-index {
        font-size: 0.7rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #6a8174;
    }
    .closed-kyc-step-pill-label {
        font-size: 0.86rem;
        font-weight: 800;
        color: #163124;
        line-height: 1.3;
    }
    .closed-kyc-step-panel[hidden] { display: none; }
    .closed-kyc-step-panel {
        grid-column: 1 / -1;
        width: 100%;
    }
    .closed-kyc-step-caption {
        font-size: 0.8rem;
        color: #60786b;
        margin: -4px 0 2px;
    }
    .closed-combo-input {
        display: grid;
        grid-template-columns: 150px minmax(0, 1fr);
        gap: 10px;
    }
    .closed-title-select {
        min-width: 0;
    }
    .closed-modal-note {
        padding: 10px 12px;
        border-radius: 10px;
        background: #f6fbf7;
        border: 1px dashed #d8e6dc;
        color: #4f6a5d;
        font-size: 0.8rem;
        line-height: 1.5;
    }
    .closed-modal-note.is-danger {
        background: #fff5f5;
        border-color: #fecaca;
        color: #9f1239;
    }
    .closed-existing-files {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }
    .closed-existing-group {
        display: grid;
        gap: 8px;
    }
    .closed-existing-group-title {
        font-size: 0.72rem;
        font-weight: 800;
        color: #284538;
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }
    .closed-existing-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }
    .closed-existing-card {
        position: relative;
        border: 1px solid #dce4df;
        border-radius: 12px;
        background: #fff;
        overflow: hidden;
        min-height: 96px;
    }
    .closed-existing-card img {
        width: 100%;
        height: 180px;
        object-fit: contain;
        object-position: center;
        display: block;
        background: #eef3f0;
    }
    @media (max-width: 720px) {
        .closed-kyc-inline-grid,
        .closed-kyc-compact-grid {
            grid-template-columns: 1fr;
        }
        .closed-kyc-field-row,
        .closed-kyc-field-row.cols-3,
        .closed-kyc-field-row.cols-4 {
            grid-template-columns: 1fr;
        }
        .closed-kyc-stepper {
            grid-template-columns: 1fr;
        }
        .closed-combo-input {
            grid-template-columns: 1fr;
        }
        .closed-existing-files {
            grid-template-columns: 1fr;
        }
        .closed-existing-grid {
            grid-template-columns: 1fr;
        }
        .closed-existing-card img {
            height: 140px;
        }
    }
    .closed-existing-card-body {
        padding: 10px 12px;
        display: grid;
        gap: 4px;
    }
    .closed-existing-card-name {
        font-size: 0.78rem;
        color: #1f352c;
        word-break: break-word;
    }
    .closed-existing-card-link {
        font-size: 0.72rem;
        font-weight: 700;
        color: #17613e;
        text-decoration: none;
    }
    .closed-existing-card-link:hover {
        text-decoration: underline;
    }
    .closed-existing-remove {
        position: absolute;
        top: 8px;
        right: 8px;
        border: none;
        border-radius: 999px;
        width: 28px;
        height: 28px;
        background: rgba(127, 29, 29, 0.92);
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 6px 18px rgba(127, 29, 29, 0.18);
    }
    .closed-existing-remove:hover {
        background: #991b1b;
    }
    .closed-existing-empty {
        margin: 0;
        padding: 12px;
        border-radius: 10px;
        border: 1px dashed #dce4df;
        background: #f8fbf9;
        color: #6b7f74;
        font-size: 0.78rem;
    }
    .closed-file-hint {
        margin-top: 4px;
        font-size: 0.74rem;
        color: #60786b;
    }

    @media (max-width: 1280px) {
        #closedLeadsGrid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 768px) {
        .closed-hero { padding: 14px; border-radius: 14px; }
        .closed-hero-title { font-size: 1.2rem; }
        .closed-stats,
        #closedLeadsGrid,
        .closed-meta,
        .closed-filter-bar { grid-template-columns: 1fr; }
        .closed-actions { flex-direction: column; gap: 6px; }
        .closed-btn { flex: 1 1 auto; width: 100%; }
        .closed-pagination,
        .closed-modal-actions { flex-direction: column; align-items: stretch; }
        .closed-form-grid { grid-template-columns: 1fr; }
        .closed-pagination-controls { width: 100%; justify-content: flex-start; }
        .closed-modal-shell { padding: 10px; align-items: flex-end; }
        .closed-modal-content { width: 100%; max-height: 92vh; border-radius: 16px 16px 0 0; }
        #closedKycModal .closed-modal-content { width: 100%; max-height: 94vh; border-radius: 16px 16px 0 0; }
        #closedKycModal .closed-modal-head,
        #closedKycModal .closed-modal-body { padding-left: 16px; padding-right: 16px; }
        #closedKycModal .closed-modal-actions {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            padding: 10px 16px calc(10px + env(safe-area-inset-bottom, 0px));
        }
        .closed-pipeline-tab { font-size: 0.75rem; padding: 0 11px; min-height: 34px; }
    }

    /* OMAXE blue polish overrides for the closer workspace */
    .closed-page-shell {
        gap: 16px;
        padding-bottom: 12px;
    }
    .closed-hero {
        background: linear-gradient(135deg, #002b45 0%, #006ba6 58%, #0a88c8 100%);
        border: 1px solid rgba(183, 224, 244, 0.32);
        border-radius: 22px;
        padding: 22px;
        box-shadow: 0 18px 42px rgba(0, 43, 69, 0.16);
    }
    .closed-hero-title {
        color: #ffffff;
        letter-spacing: 0;
    }
    .closed-hero-copy {
        color: rgba(234, 246, 253, 0.92);
        max-width: 760px;
        line-height: 1.45;
    }
    .closed-pill {
        background: rgba(255, 255, 255, 0.16);
        border: 1px solid rgba(255, 255, 255, 0.24);
        color: #ffffff;
        box-shadow: none;
    }
    .closed-stats {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
    }
    .closed-stat {
        background: rgba(255, 255, 255, 0.13);
        border-color: rgba(255, 255, 255, 0.22);
        border-radius: 16px;
        padding: 14px 16px;
    }
    .closed-stat-label {
        color: rgba(234, 246, 253, 0.84);
    }
    .closed-stat-value {
        color: #ffffff;
    }
    .closed-pipeline-tabs {
        background: #ffffff;
        border-color: #d7eaf6;
        border-radius: 18px;
        box-shadow: 0 10px 30px rgba(0, 43, 69, 0.06);
        padding: 10px;
    }
    .closed-pipeline-tab {
        background: #f7fbff;
        border-color: #d7eaf6;
        border-radius: 14px;
        color: #23495f;
    }
    .closed-pipeline-tab:hover {
        background: #eaf6fd;
        border-color: #b7e0f4;
    }
    .closed-pipeline-tab.active {
        background: linear-gradient(135deg, #0073b1 0%, #005f91 100%);
        box-shadow: 0 10px 22px rgba(0, 115, 177, 0.18);
    }
    .closed-pipeline-count {
        background: #eaf6fd;
        color: #0073b1;
    }
    .closed-pipeline-tab.active .closed-pipeline-count {
        background: rgba(255, 255, 255, 0.22);
        color: #ffffff;
    }
    .closed-filter-bar {
        border-color: #d7eaf6;
        border-radius: 20px;
        box-shadow: 0 12px 32px rgba(0, 43, 69, 0.06);
        padding: 18px;
    }
    .closed-field label {
        color: #40677b;
    }
    .closed-input,
    .closed-select {
        background: #ffffff;
        border-color: #d7eaf6;
        border-radius: 14px;
        color: #102a3a;
        min-height: 48px;
    }
    .closed-input:focus,
    .closed-select:focus {
        border-color: #0073b1;
        box-shadow: 0 0 0 4px rgba(0, 115, 177, 0.12);
    }
    .closed-results-title,
    .closed-card-name,
    .closed-meta-value,
    .closed-kyc-section-title,
    .closed-kyc-step-pill-label {
        color: #102a3a;
    }
    .closed-results-copy,
    #closedLoadingState,
    #closedEmptyState {
        color: #5b7182;
    }
    #closedLoadingState,
    #closedEmptyState {
        background: #f7fbff;
        border-color: #b7e0f4;
        border-radius: 18px;
    }
    .closed-card {
        border-color: #d7eaf6;
        border-radius: 18px;
        box-shadow: 0 12px 32px rgba(0, 43, 69, 0.06);
    }
    .closed-card:hover {
        border-color: #b7e0f4;
        box-shadow: 0 18px 40px rgba(0, 43, 69, 0.10);
    }
    .closed-card-avatar {
        background: linear-gradient(135deg, #eaf6fd, #d7eaf6);
        border-color: #b7e0f4;
        color: #0073b1;
    }
    .closed-requirements {
        background: #f7fbff;
        border-left-color: #0073b1;
    }
    .closed-btn-primary,
    .closed-page-btn.active {
        background: linear-gradient(135deg, #0073b1 0%, #005f91 100%);
        box-shadow: 0 10px 22px rgba(0, 115, 177, 0.18);
    }
    .closed-btn-secondary,
    .closed-page-btn {
        border-color: #d7eaf6;
        color: #23495f;
    }
    @media (max-width: 768px) {
        .closed-page-shell {
            gap: 14px;
        }
        .closed-hero {
            border-radius: 22px;
            padding: 18px;
        }
        .closed-hero-top {
            display: grid;
            gap: 12px;
        }
        .closed-hero-title {
            font-size: 1.45rem;
        }
        .closed-hero-copy {
            font-size: 0.88rem;
        }
        .closed-pill {
            width: max-content;
        }
        .closed-stats {
            grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
            gap: 8px;
        }
        .closed-stat {
            border-radius: 14px;
            padding: 11px 10px;
        }
        .closed-stat-label {
            font-size: 0.54rem;
            letter-spacing: 0.08em;
        }
        .closed-stat-value {
            font-size: 1.05rem;
        }
        .closed-pipeline-tabs {
            flex-wrap: nowrap;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            border-radius: 18px;
            padding: 8px;
        }
        .closed-pipeline-tabs::-webkit-scrollbar {
            display: none;
        }
        .closed-pipeline-tab {
            flex: 0 0 auto;
            min-height: 40px;
            border-radius: 13px;
            padding: 0 13px;
            font-size: 0.76rem;
        }
        .closed-filter-bar,
        #closedLeadsGrid {
            grid-template-columns: 1fr !important;
        }
        .closed-filter-bar {
            border-radius: 20px;
            padding: 16px;
        }
    }
</style>
@endpush

@section('content')
@include('sales-manager.partials.pipeline-tabs')
<div class="closed-page-shell">
    <section class="closed-hero">
        <div class="closed-hero-top">
            <div>
                <div class="closed-hero-title">Closer Pipeline</div>
                <p class="closed-hero-copy">Move verified site visits through closer draft, KYC submission, CRM correction, approval, and incentive tracking.</p>
            </div>
            <div class="closed-pill">
                <i class="fas fa-circle-check"></i>
                Closure Journey
            </div>
        </div>
        <div class="closed-stats">
            <div class="closed-stat">
                <div class="closed-stat-label">Current Bucket</div>
                <div class="closed-stat-value" id="closedTotalCount">0</div>
            </div>
            <div class="closed-stat">
                <div class="closed-stat-label">Visible Cards</div>
                <div class="closed-stat-value" id="closedPageCount">0</div>
            </div>
            <div class="closed-stat">
                <div class="closed-stat-label">Last Refresh</div>
                <div class="closed-stat-value" id="closedLastRefresh">-</div>
            </div>
        </div>
    </section>

    <section class="closed-pipeline-tabs" id="closedPipelineTabs">
        <button type="button" class="closed-pipeline-tab active" data-bucket="visited_clients">Visited Clients <span class="closed-pipeline-count" id="bucketCountVisited">0</span></button>
        <button type="button" class="closed-pipeline-tab" data-bucket="closer_drafts">Closer Drafts <span class="closed-pipeline-count" id="bucketCountDrafts">0</span></button>
        <button type="button" class="closed-pipeline-tab" data-bucket="pending_crm">CRM Approval <span class="closed-pipeline-count" id="bucketCountPending">0</span></button>
        <button type="button" class="closed-pipeline-tab" data-bucket="correction_required">Correction Required <span class="closed-pipeline-count" id="bucketCountCorrection">0</span></button>
        <button type="button" class="closed-pipeline-tab" data-bucket="approved_closers">Approved Closers <span class="closed-pipeline-count" id="bucketCountApproved">0</span></button>
        <button type="button" class="closed-pipeline-tab" data-bucket="incentives">Incentives <span class="closed-pipeline-count" id="bucketCountIncentive">0</span></button>
    </section>

    <section class="closed-filter-bar asm-filter-shell">
        <div class="closed-field">
            <label for="closedSearchInput">Search Pipeline</label>
            <input id="closedSearchInput" class="closed-input asm-filter-input" type="text" placeholder="Name, phone, email, location..." />
        </div>
        <div class="closed-field">
            <label for="closedSourceFilter">Source</label>
            <select id="closedSourceFilter" class="closed-select asm-filter-select">
                <option value="">All Sources</option>
                <option value="website">Website</option>
                <option value="referral">Referral</option>
                <option value="walk_in">Walk In</option>
                <option value="call">Call</option>
                <option value="social_media">Social Media</option>
                <option value="google_sheets">Google Sheets</option>
                <option value="csv">CSV</option>
                <option value="pabbly">Pabbly</option>
                <option value="facebook_lead_ads">Facebook Lead Ads</option>
                <option value="mcube">Mcube</option>
                <option value="other">Other</option>
            </select>
        </div>
        <div class="closed-field">
            <label for="closedSortFilter">Sort</label>
            <select id="closedSortFilter" class="closed-select asm-filter-select">
                <option value="latest">Latest Updated</option>
                <option value="oldest">Oldest Updated</option>
                <option value="name_asc">Name A-Z</option>
                <option value="name_desc">Name Z-A</option>
            </select>
        </div>
    </section>

    <section class="closed-results-head">
        <div>
            <div class="closed-results-title" id="closedBucketTitle">Visited Clients</div>
            <div class="closed-results-copy" id="closedBucketCopy">Verified site visit ko closer draft me move karke KYC journey start karo.</div>
        </div>
    </section>

    <div id="closedLoadingState">Closer pipeline load ho rahi hai...</div>
    <div id="closedEmptyState" hidden>Abhi is bucket me koi client nahi mila.</div>
    <div id="closedLeadsGrid" hidden></div>
    <div id="closedPagination" class="closed-pagination" hidden></div>
</div>

<div id="closedKycModal" class="closed-modal-shell" hidden aria-hidden="true" onclick="if (event.target === this) closeClosedKycModal()">
    <div class="closed-modal-content">
        <div class="closed-modal-head">
            <div>
                <h3 id="closedKycModalTitle" style="font-size:1.45rem;font-weight:800;color:#133025;">Fill KYC Form</h3>
                <p id="closedKycModalSubtitle" style="margin-top:6px;color:#60786b;">Close verification ke baad full KYC yahin se submit karo.</p>
            </div>
            <button type="button" class="btn btn-danger" onclick="closeClosedKycModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="closed-modal-body">
            <div id="closedKycModalNote" class="closed-modal-note">Customer, nominee, identity details, KYC documents, aur proof photos required hain. Submit hone ke baad incentive form unlock hoga.</div>
            <div id="closedKycExistingFiles" class="closed-modal-note" hidden></div>
            <div id="closedKycStepper" class="closed-kyc-stepper" hidden></div>
            <div id="closedKycStepCaption" class="closed-kyc-step-caption" hidden></div>
            <div id="closedKycExistingFilesPanel" class="closed-existing-files">
                <div class="closed-existing-group">
                    <div class="closed-existing-group-title">Existing KYC Documents</div>
                    <div id="closedExistingKycDocs" class="closed-existing-grid"></div>
                </div>
                <div class="closed-existing-group">
                    <div class="closed-existing-group-title">Existing Proof Photos</div>
                    <div id="closedExistingProofPhotos" class="closed-existing-grid"></div>
                </div>
                <div class="closed-existing-group">
                    <div class="closed-existing-group-title">Existing Booking Payment Proofs</div>
                    <div id="closedExistingBookingPaymentProofs" class="closed-existing-grid"></div>
                </div>
            </div>
            <form id="closedKycForm" class="closed-form-grid">
                <div class="closed-kyc-section">
                    <div class="closed-kyc-section-title">1. Sole / First Applicant</div>
                    <div class="closed-kyc-inline-grid">
                        <div class="closed-form-field">
                            <label for="closedPrimaryApplicantName">Mr./Mrs./Ms.</label>
                            <input id="closedPrimaryApplicantName" class="closed-input closed-kyc-line-input" type="text">
                        </div>
                        <div class="closed-form-field">
                            <label for="closedPrimaryApplicantRelationName">S/W/D of</label>
                            <input id="closedPrimaryApplicantRelationName" class="closed-input closed-kyc-line-input" type="text">
                        </div>
                        <div class="closed-form-field">
                            <label for="closedPrimaryApplicantDob">Date of Birth</label>
                            <input id="closedPrimaryApplicantDob" class="closed-input closed-kyc-line-input" type="date">
                        </div>
                        <div class="closed-form-field">
                            <label for="closedPrimaryApplicantNationality">Nationality</label>
                            <input id="closedPrimaryApplicantNationality" class="closed-input closed-kyc-line-input" type="text">
                        </div>
                    </div>
                    <div class="closed-kyc-check-group">
                        <label>Occupation</label>
                        <div class="closed-kyc-check-options">
                            <label class="closed-kyc-check-option"><input id="closedPrimaryOccupationService" type="checkbox"> Service</label>
                            <label class="closed-kyc-check-option"><input id="closedPrimaryOccupationProfessional" type="checkbox"> Professional</label>
                            <label class="closed-kyc-check-option"><input id="closedPrimaryOccupationHousewife" type="checkbox"> Housewife</label>
                            <label class="closed-kyc-check-option"><input id="closedPrimaryOccupationBusiness" type="checkbox"> Business</label>
                            <label class="closed-kyc-check-option"><input id="closedPrimaryOccupationAnyOtherToggle" type="checkbox"> Any Other</label>
                        </div>
                        <input id="closedPrimaryOccupationAnyOther" class="closed-input closed-kyc-line-input" type="text" placeholder="Any other occupation">
                    </div>
                    <div class="closed-kyc-check-group">
                        <label>Residential Status</label>
                        <div class="closed-kyc-check-options">
                            <label class="closed-kyc-check-option"><input id="closedPrimaryResidentIndian" type="checkbox"> Resident Indian</label>
                            <label class="closed-kyc-check-option"><input id="closedPrimaryResidentNonResident" type="checkbox"> Non-Resident</label>
                            <label class="closed-kyc-check-option"><input id="closedPrimaryResidentForeignNational" type="checkbox"> Foreign National of Indian Origin</label>
                        </div>
                        <input id="closedPrimaryResidentOther" class="closed-input closed-kyc-line-input" type="text" placeholder="Others (please specify)">
                    </div>
                    <div class="closed-kyc-check-group">
                        <label>Marital Status</label>
                        <div class="closed-kyc-check-options">
                            <label class="closed-kyc-check-option"><input id="closedPrimaryMaritalMarried" data-exclusive-group="closedPrimaryMarital" type="checkbox"> Married</label>
                            <label class="closed-kyc-check-option"><input id="closedPrimaryMaritalUnmarried" data-exclusive-group="closedPrimaryMarital" type="checkbox"> Unmarried</label>
                        </div>
                    </div>
                    <div class="closed-kyc-inline-grid">
                        <div class="closed-form-field">
                            <label for="closedPrimaryPanNo">Permanent Account Number (PAN No.)</label>
                            <input id="closedPrimaryPanNo" class="closed-input closed-kyc-line-input" type="text">
                        </div>
                        <div class="closed-form-field">
                            <label for="closedPrimaryAadhaarNo">Aadhaar No</label>
                            <input id="closedPrimaryAadhaarNo" class="closed-input closed-kyc-line-input" type="text">
                        </div>
                    </div>
                    <div class="closed-form-field full">
                        <label for="closedPrimaryAddress">Address</label>
                        <textarea id="closedPrimaryAddress" class="closed-textarea" rows="3"></textarea>
                    </div>
                    <div class="closed-kyc-compact-grid">
                        <div class="closed-form-field">
                            <label for="closedPrimaryCity">City</label>
                            <input id="closedPrimaryCity" class="closed-input closed-kyc-line-input" type="text">
                        </div>
                        <div class="closed-form-field">
                            <label for="closedPrimaryState">State</label>
                            <input id="closedPrimaryState" class="closed-input closed-kyc-line-input" type="text">
                        </div>
                        <div class="closed-form-field">
                            <label for="closedPrimaryCountry">Country</label>
                            <input id="closedPrimaryCountry" class="closed-input closed-kyc-line-input" type="text">
                        </div>
                        <div class="closed-form-field">
                            <label for="closedPrimaryPin">PIN</label>
                            <input id="closedPrimaryPin" class="closed-input closed-kyc-line-input" type="text">
                        </div>
                        <div class="closed-form-field">
                            <label for="closedPrimaryEmail">Email</label>
                            <input id="closedPrimaryEmail" class="closed-input closed-kyc-line-input" type="email">
                        </div>
                        <div class="closed-form-field">
                            <label for="closedPrimaryTel">Contact No 2</label>
                            <input id="closedPrimaryTel" class="closed-input closed-kyc-line-input" type="text">
                        </div>
                        <div class="closed-form-field">
                            <label for="closedPrimaryMobile">Contact No 1</label>
                            <input id="closedPrimaryMobile" class="closed-input closed-kyc-line-input" type="text">
                        </div>
                        <div class="closed-form-field">
                            <label for="closedPrimaryFax">Fax No.</label>
                            <input id="closedPrimaryFax" class="closed-input closed-kyc-line-input" type="text">
                        </div>
                    </div>
                </div>
                <div class="closed-kyc-section">
                    <div class="closed-kyc-section-title">2. Second / Joint Applicant / Nominee</div>
                    <div class="closed-kyc-inline-grid">
                        <div class="closed-form-field">
                            <label for="closedJointApplicantName">Mr./Mrs./Ms.</label>
                            <input id="closedJointApplicantName" class="closed-input closed-kyc-line-input" type="text">
                        </div>
                        <div class="closed-form-field">
                            <label for="closedJointApplicantRelationName">S/W/D of</label>
                            <input id="closedJointApplicantRelationName" class="closed-input closed-kyc-line-input" type="text">
                        </div>
                        <div class="closed-form-field">
                            <label for="closedJointApplicantDob">Date of Birth</label>
                            <input id="closedJointApplicantDob" class="closed-input closed-kyc-line-input" type="date">
                        </div>
                        <div class="closed-form-field">
                            <label for="closedJointApplicantNationality">Nationality</label>
                            <input id="closedJointApplicantNationality" class="closed-input closed-kyc-line-input" type="text">
                        </div>
                    </div>
                    <div class="closed-kyc-check-group">
                        <label>Occupation</label>
                        <div class="closed-kyc-check-options">
                            <label class="closed-kyc-check-option"><input id="closedJointOccupationService" type="checkbox"> Service</label>
                            <label class="closed-kyc-check-option"><input id="closedJointOccupationProfessional" type="checkbox"> Professional</label>
                            <label class="closed-kyc-check-option"><input id="closedJointOccupationHousewife" type="checkbox"> Housewife</label>
                            <label class="closed-kyc-check-option"><input id="closedJointOccupationBusiness" type="checkbox"> Business</label>
                            <label class="closed-kyc-check-option"><input id="closedJointOccupationAnyOtherToggle" type="checkbox"> Any Other</label>
                        </div>
                        <input id="closedJointOccupationAnyOther" class="closed-input closed-kyc-line-input" type="text" placeholder="Any other occupation">
                    </div>
                    <div class="closed-kyc-check-group">
                        <label>Residential Status</label>
                        <div class="closed-kyc-check-options">
                            <label class="closed-kyc-check-option"><input id="closedJointResidentIndian" type="checkbox"> Resident Indian</label>
                            <label class="closed-kyc-check-option"><input id="closedJointResidentNonResident" type="checkbox"> Non-Resident</label>
                            <label class="closed-kyc-check-option"><input id="closedJointResidentForeignNational" type="checkbox"> Foreign National of Indian Origin</label>
                        </div>
                        <input id="closedJointResidentOther" class="closed-input closed-kyc-line-input" type="text" placeholder="Others (please specify)">
                    </div>
                    <div class="closed-kyc-check-group">
                        <label>Marital Status</label>
                        <div class="closed-kyc-check-options">
                            <label class="closed-kyc-check-option"><input id="closedJointMaritalMarried" data-exclusive-group="closedJointMarital" type="checkbox"> Married</label>
                            <label class="closed-kyc-check-option"><input id="closedJointMaritalUnmarried" data-exclusive-group="closedJointMarital" type="checkbox"> Unmarried</label>
                        </div>
                    </div>
                    <div class="closed-form-field">
                        <label for="closedJointPanNo">Permanent Account Number (PAN No.)</label>
                        <input id="closedJointPanNo" class="closed-input closed-kyc-line-input" type="text">
                    </div>
                    <div class="closed-form-field full">
                        <label for="closedJointAddress">Address</label>
                        <textarea id="closedJointAddress" class="closed-textarea" rows="3"></textarea>
                    </div>
                    <div class="closed-kyc-compact-grid">
                        <div class="closed-form-field">
                            <label for="closedJointCity">City</label>
                            <input id="closedJointCity" class="closed-input closed-kyc-line-input" type="text">
                        </div>
                        <div class="closed-form-field">
                            <label for="closedJointState">State</label>
                            <input id="closedJointState" class="closed-input closed-kyc-line-input" type="text">
                        </div>
                        <div class="closed-form-field">
                            <label for="closedJointCountry">Country</label>
                            <input id="closedJointCountry" class="closed-input closed-kyc-line-input" type="text">
                        </div>
                        <div class="closed-form-field">
                            <label for="closedJointPin">PIN</label>
                            <input id="closedJointPin" class="closed-input closed-kyc-line-input" type="text">
                        </div>
                        <div class="closed-form-field">
                            <label for="closedJointEmail">Email</label>
                            <input id="closedJointEmail" class="closed-input closed-kyc-line-input" type="email">
                        </div>
                        <div class="closed-form-field">
                            <label for="closedJointTel">Contact No 2</label>
                            <input id="closedJointTel" class="closed-input closed-kyc-line-input" type="text">
                        </div>
                        <div class="closed-form-field">
                            <label for="closedJointMobile">Contact No 1</label>
                            <input id="closedJointMobile" class="closed-input closed-kyc-line-input" type="text">
                        </div>
                        <div class="closed-form-field">
                            <label for="closedJointFax">Fax No.</label>
                            <input id="closedJointFax" class="closed-input closed-kyc-line-input" type="text">
                        </div>
                    </div>
                </div>
                <div class="closed-kyc-section">
                    <div class="closed-kyc-section-title">Details Of The Unit</div>
                    <div class="closed-kyc-compact-grid">
                        <div class="closed-form-field">
                            <label for="closedUnitNo">Unit No.</label>
                            <input id="closedUnitNo" class="closed-input closed-kyc-line-input" type="text">
                        </div>
                        <div class="closed-form-field">
                            <label for="closedUnitBlockCluster">Block / Cluster</label>
                            <input id="closedUnitBlockCluster" class="closed-input closed-kyc-line-input" type="text">
                        </div>
                        <div class="closed-form-field">
                            <label for="closedUnitFloor">Floor</label>
                            <input id="closedUnitFloor" class="closed-input closed-kyc-line-input" type="text">
                        </div>
                        <div class="closed-form-field">
                            <label for="closedUnitCarpetAreaSqMt">Carpet Area (sq. mt.)</label>
                            <input id="closedUnitCarpetAreaSqMt" class="closed-input closed-kyc-line-input" type="text">
                        </div>
                        <div class="closed-form-field">
                            <label for="closedUnitCarpetAreaSqFt">Carpet Area (sq. ft.)</label>
                            <input id="closedUnitCarpetAreaSqFt" class="closed-input closed-kyc-line-input" type="text">
                        </div>
                        <div class="closed-form-field">
                            <label for="closedUnitSuperAreaSqMt">Super Area (sq. mt.)</label>
                            <input id="closedUnitSuperAreaSqMt" class="closed-input closed-kyc-line-input" type="text">
                        </div>
                        <div class="closed-form-field">
                            <label for="closedUnitSuperAreaSqFt">Super Area (sq. ft.)</label>
                            <input id="closedUnitSuperAreaSqFt" class="closed-input closed-kyc-line-input" type="text">
                        </div>
                        <div class="closed-form-field">
                            <label for="closedUnitBasicSalePrice">Basic Sale Price (Rs.)</label>
                            <input id="closedUnitBasicSalePrice" class="closed-input closed-kyc-line-input" type="text">
                        </div>
                        <div class="closed-form-field">
                            <label for="closedUnitPlcAmount">PLC Amount (Rs.)</label>
                            <input id="closedUnitPlcAmount" class="closed-input closed-kyc-line-input" type="text">
                        </div>
                        <div class="closed-form-field">
                            <label for="closedUnitClubMembershipCharges">Club Membership Charges</label>
                            <input id="closedUnitClubMembershipCharges" class="closed-input closed-kyc-line-input" type="text">
                        </div>
                    </div>
                    <div class="closed-kyc-check-group">
                        <label>Car Parking Opted</label>
                        <div class="closed-kyc-check-options">
                            <label class="closed-kyc-check-option"><input id="closedUnitCarParkingCovered" type="checkbox"> Covered</label>
                            <label class="closed-kyc-check-option"><input id="closedUnitCarParkingOpen" type="checkbox"> Open</label>
                        </div>
                    </div>
                    <div class="closed-kyc-check-group">
                        <label>Payment Plan Opted</label>
                        <div class="closed-kyc-check-options">
                            <label class="closed-kyc-check-option"><input id="closedUnitPaymentPlanConstructionLinked" type="checkbox"> Construction Linked</label>
                            <label class="closed-kyc-check-option"><input id="closedUnitPaymentPlanDownPayment" type="checkbox"> Down Payment</label>
                            <label class="closed-kyc-check-option"><input id="closedUnitPaymentPlanOther" type="checkbox"> Other</label>
                        </div>
                        <input id="closedUnitPaymentPlanOtherText" class="closed-input closed-kyc-line-input" type="text" placeholder="Other payment plan">
                    </div>
                </div>
                <div class="closed-form-field full">
                    <label for="closedKycDocs">KYC Documents</label>
                    <input id="closedKycDocs" class="closed-file" type="file" accept=".jpg,.jpeg,.png,.pdf" multiple>
                    <div class="closed-file-hint">Purane docs retain ya remove kar sakte ho. Naye docs yahin add honge.</div>
                    <div id="closedSelectedKycDocs" class="closed-existing-grid"></div>
                </div>
                <div class="closed-form-field full">
                    <label for="closedProofPhotos">Proof Photos</label>
                    <input id="closedProofPhotos" class="closed-file" type="file" accept=".jpg,.jpeg,.png,.webp" multiple>
                    <div class="closed-file-hint">Proof photo remove karke new upload kar sakte ho. Kam se kam ek proof final submit me hona chahiye.</div>
                    <div id="closedSelectedProofPhotos" class="closed-existing-grid"></div>
                </div>
            </form>
        </div>
        <div class="closed-modal-actions">
                <button type="button" id="closedKycBackButton" class="closed-btn closed-btn-secondary" onclick="changeClosedKycStep(-1)">Back</button>
                <button type="button" class="closed-btn closed-btn-secondary" onclick="closeClosedKycModal()">Cancel</button>
                <button type="button" id="closedKycSaveDraftButton" class="closed-btn closed-btn-secondary" onclick="submitClosedKyc('draft')">Save Draft</button>
                <button type="button" id="closedKycNextButton" class="closed-btn closed-btn-primary" onclick="changeClosedKycStep(1)">Next</button>
                <button type="button" id="closedKycSubmitButton" class="closed-btn closed-btn-primary" onclick="submitClosedKyc('submit')">Submit KYC</button>
        </div>
    </div>
</div>

<div id="closedIncentiveModal" class="closed-modal-shell" hidden aria-hidden="true" onclick="if (event.target === this) closeClosedIncentiveModal()">
    <div class="closed-modal-content">
        <div class="closed-modal-head">
            <div>
                <h3 style="font-size:1.45rem;font-weight:800;color:#133025;">Submit Incentive Request</h3>
                <p style="margin-top:6px;color:#60786b;">KYC submit hone ke baad closer incentive yahin request hoga.</p>
            </div>
            <button type="button" class="btn btn-danger" onclick="closeClosedIncentiveModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="closed-modal-body">
            <div class="closed-modal-note">Incentive request CRM/Admin verification queue me jayega.</div>
            <div class="closed-form-grid">
                <div class="closed-form-field">
                    <label for="closedIncentiveAmount">Incentive Amount</label>
                    <input id="closedIncentiveAmount" class="closed-number" type="number" min="0" step="0.01" required>
                </div>
            </div>
            <div class="closed-modal-actions">
                <button type="button" class="closed-btn closed-btn-secondary" onclick="closeClosedIncentiveModal()">Cancel</button>
                <button type="button" class="closed-btn closed-btn-primary" onclick="submitClosedIncentive()">Submit Incentive</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const CLOSED_API_BASE_URL = '/api/sales-manager';
    const CLOSED_DEFAULT_KYC_SCHEMA = @json($kycFormSchema ?? []);
    const CLOSED_STORAGE_PROXY_URL = @json(route('storage.proxy'));
    const CLOSED_BUCKETS = {
        visited_clients: {
            title: 'Visited Clients',
            copy: 'Verified site visit ko closer draft me move karke KYC journey start karo.',
            countId: 'bucketCountVisited',
        },
        closer_drafts: {
            title: 'Closer Drafts',
            copy: 'Draft KYC save karo ya final submit karke CRM review me bhejo.',
            countId: 'bucketCountDrafts',
        },
        pending_crm: {
            title: 'CRM Approval',
            copy: 'Ye closers CRM review me hain. Approval ya correction remark ka wait karo.',
            countId: 'bucketCountPending',
        },
        correction_required: {
            title: 'Correction Required',
            copy: 'CRM remark ke hisaab se KYC update karke same closer ko resubmit karo.',
            countId: 'bucketCountCorrection',
        },
        approved_closers: {
            title: 'Approved Closers',
            copy: 'CRM-approved closers yahan read-only milenge. Incentive eligible cases yahin se submit karo.',
            countId: 'bucketCountApproved',
        },
        incentives: {
            title: 'Incentives',
            copy: 'Approved closers ka incentive state track karo aur pending requests ko manage karo.',
            countId: 'bucketCountIncentive',
        },
    };
    let closedLeadPage = 1;
    let closedActiveBucket = 'visited_clients';
    let closedRawVisits = [];
    let closedVisitData = [];
    let closedVisitMeta = null;
    let activeClosedVisitId = null;
    const closedKycState = {
        existingKycDocuments: [],
        existingProofPhotos: [],
        existingBookingPaymentProofs: [],
        schema: null,
        currentStep: 0,
        readonly: false,
    };
    const CLOSED_KYC_TITLES = ['Mr.', 'Mrs.', 'Ms.', 'M/s.', 'Dr.'];
    const CLOSED_KYC_FIELD_IDS = {
        primaryApplicant: {
            name: 'closedPrimaryApplicantName',
            relation_name: 'closedPrimaryApplicantRelationName',
            date_of_birth: 'closedPrimaryApplicantDob',
            nationality: 'closedPrimaryApplicantNationality',
            occupation_service: 'closedPrimaryOccupationService',
            occupation_professional: 'closedPrimaryOccupationProfessional',
            occupation_housewife: 'closedPrimaryOccupationHousewife',
            occupation_business: 'closedPrimaryOccupationBusiness',
            occupation_any_other: 'closedPrimaryOccupationAnyOther',
            occupation_any_other_toggle: 'closedPrimaryOccupationAnyOtherToggle',
            resident_indian: 'closedPrimaryResidentIndian',
            resident_non_resident: 'closedPrimaryResidentNonResident',
            resident_foreign_national: 'closedPrimaryResidentForeignNational',
            resident_other: 'closedPrimaryResidentOther',
            marital_status_married: 'closedPrimaryMaritalMarried',
            marital_status_unmarried: 'closedPrimaryMaritalUnmarried',
            pan_no: 'closedPrimaryPanNo',
            aadhaar_no: 'closedPrimaryAadhaarNo',
            address: 'closedPrimaryAddress',
            city: 'closedPrimaryCity',
            state: 'closedPrimaryState',
            country: 'closedPrimaryCountry',
            pin: 'closedPrimaryPin',
            email: 'closedPrimaryEmail',
            tel_no: 'closedPrimaryTel',
            mobile_no: 'closedPrimaryMobile',
            fax_no: 'closedPrimaryFax',
        },
        jointApplicant: {
            name: 'closedJointApplicantName',
            relation_name: 'closedJointApplicantRelationName',
            date_of_birth: 'closedJointApplicantDob',
            nationality: 'closedJointApplicantNationality',
            occupation_service: 'closedJointOccupationService',
            occupation_professional: 'closedJointOccupationProfessional',
            occupation_housewife: 'closedJointOccupationHousewife',
            occupation_business: 'closedJointOccupationBusiness',
            occupation_any_other: 'closedJointOccupationAnyOther',
            occupation_any_other_toggle: 'closedJointOccupationAnyOtherToggle',
            resident_indian: 'closedJointResidentIndian',
            resident_non_resident: 'closedJointResidentNonResident',
            resident_foreign_national: 'closedJointResidentForeignNational',
            resident_other: 'closedJointResidentOther',
            marital_status_married: 'closedJointMaritalMarried',
            marital_status_unmarried: 'closedJointMaritalUnmarried',
            pan_no: 'closedJointPanNo',
            address: 'closedJointAddress',
            city: 'closedJointCity',
            state: 'closedJointState',
            country: 'closedJointCountry',
            pin: 'closedJointPin',
            email: 'closedJointEmail',
            tel_no: 'closedJointTel',
            mobile_no: 'closedJointMobile',
            fax_no: 'closedJointFax',
        },
        unitDetails: {
            unit_no: 'closedUnitNo',
            block_cluster: 'closedUnitBlockCluster',
            floor: 'closedUnitFloor',
            carpet_area_sq_mt: 'closedUnitCarpetAreaSqMt',
            carpet_area_sq_ft: 'closedUnitCarpetAreaSqFt',
            super_area_sq_mt: 'closedUnitSuperAreaSqMt',
            super_area_sq_ft: 'closedUnitSuperAreaSqFt',
            basic_sale_price: 'closedUnitBasicSalePrice',
            plc_amount: 'closedUnitPlcAmount',
            car_parking_covered: 'closedUnitCarParkingCovered',
            car_parking_open: 'closedUnitCarParkingOpen',
            club_membership_charges: 'closedUnitClubMembershipCharges',
            payment_plan_construction_linked: 'closedUnitPaymentPlanConstructionLinked',
            payment_plan_down_payment: 'closedUnitPaymentPlanDownPayment',
            payment_plan_other: 'closedUnitPaymentPlanOther',
            payment_plan_other_text: 'closedUnitPaymentPlanOtherText',
        },
    };

    function getClosedAuthHeaders() {
        const token = document.querySelector('meta[name="api-token"]')?.getAttribute('content') || @json($api_token ?? '');
        return {
            'Accept': 'application/json',
            'Authorization': `Bearer ${token}`,
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        };
    }

    function debounce(fn, delay = 300) {
        let timeoutId;
        return (...args) => {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => fn(...args), delay);
        };
    }

    function escapeClosedHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function getClosedStorageUrl(path, directoryHint = '') {
        const normalized = String(path || '').trim();
        if (!normalized) return '';
        if (/^https?:\/\//i.test(normalized)) {
            return normalized;
        }

        const url = new URL(CLOSED_STORAGE_PROXY_URL, window.location.origin);
        url.searchParams.set('path', normalized);

        const safeDirectory = String(directoryHint || '').trim().replace(/^\/+|\/+$/g, '');
        if (safeDirectory) {
            url.searchParams.set('hint', safeDirectory);
        }

        return url.toString();
    }

    function setClosedFieldValue(fieldId, value) {
        const element = document.getElementById(fieldId);
        if (!element) return;
        if (element.type === 'checkbox') {
            element.checked = Boolean(value);
            return;
        }
        element.value = value ?? '';
    }

    function getClosedFieldValue(fieldId) {
        const element = document.getElementById(fieldId);
        if (!element) return '';
        if (element.type === 'checkbox') {
            return element.checked ? '1' : '0';
        }
        return element.value.trim();
    }

    function fillClosedKycSections(visit) {
        const primary = visit.primary_applicant_details || {};
        const joint = visit.joint_applicant_details || {};
        const unit = visit.unit_details || {};

        Object.entries(CLOSED_KYC_FIELD_IDS.primaryApplicant).forEach(([key, fieldId]) => {
            const fallback = key === 'name' ? (visit.customer_name || visit.lead?.name || '') :
                key === 'date_of_birth' ? (visit.customer_dob || '') :
                key === 'pan_no' ? (visit.pan_card || '') :
                key === 'aadhaar_no' ? (visit.aadhaar_card_no || '') : '';
            setClosedFieldValue(fieldId, primary[key] ?? fallback);
        });

        if (!primary.occupation_any_other && primary.occupation_any_other_toggle === undefined) {
            setClosedFieldValue('closedPrimaryOccupationAnyOtherToggle', Boolean(primary.occupation_any_other));
        }

        Object.entries(CLOSED_KYC_FIELD_IDS.jointApplicant).forEach(([key, fieldId]) => {
            const fallback = key === 'name' ? (visit.nominee_name || visit.second_customer_name || '') : '';
            setClosedFieldValue(fieldId, joint[key] ?? fallback);
        });

        if (!joint.occupation_any_other && joint.occupation_any_other_toggle === undefined) {
            setClosedFieldValue('closedJointOccupationAnyOtherToggle', Boolean(joint.occupation_any_other));
        }

        Object.entries(CLOSED_KYC_FIELD_IDS.unitDetails).forEach(([key, fieldId]) => {
            setClosedFieldValue(fieldId, unit[key] ?? '');
        });
    }

    function appendClosedStructuredKycFields(formData) {
        formData.append('customer_name', getClosedFieldValue('closedPrimaryApplicantName'));
        formData.append('nominee_name', getClosedFieldValue('closedJointApplicantName'));
        formData.append('second_customer_name', getClosedFieldValue('closedJointApplicantName'));
        formData.append('customer_dob', getClosedFieldValue('closedPrimaryApplicantDob'));
        formData.append('pan_card', getClosedFieldValue('closedPrimaryPanNo').toUpperCase());
        formData.append('aadhaar_card_no', getClosedFieldValue('closedPrimaryAadhaarNo'));

        formData.append('primary_applicant_name', getClosedFieldValue('closedPrimaryApplicantName'));
        formData.append('primary_applicant_relation_name', getClosedFieldValue('closedPrimaryApplicantRelationName'));
        formData.append('primary_applicant_date_of_birth', getClosedFieldValue('closedPrimaryApplicantDob'));
        formData.append('primary_applicant_nationality', getClosedFieldValue('closedPrimaryApplicantNationality'));
        formData.append('primary_applicant_occupation_service', getClosedFieldValue('closedPrimaryOccupationService'));
        formData.append('primary_applicant_occupation_professional', getClosedFieldValue('closedPrimaryOccupationProfessional'));
        formData.append('primary_applicant_occupation_housewife', getClosedFieldValue('closedPrimaryOccupationHousewife'));
        formData.append('primary_applicant_occupation_business', getClosedFieldValue('closedPrimaryOccupationBusiness'));
        formData.append('primary_applicant_occupation_any_other', getClosedFieldValue('closedPrimaryOccupationAnyOther'));
        formData.append('primary_applicant_resident_indian', getClosedFieldValue('closedPrimaryResidentIndian'));
        formData.append('primary_applicant_resident_non_resident', getClosedFieldValue('closedPrimaryResidentNonResident'));
        formData.append('primary_applicant_resident_foreign_national', getClosedFieldValue('closedPrimaryResidentForeignNational'));
        formData.append('primary_applicant_resident_other', getClosedFieldValue('closedPrimaryResidentOther'));
        formData.append('primary_applicant_marital_status_married', getClosedFieldValue('closedPrimaryMaritalMarried'));
        formData.append('primary_applicant_marital_status_unmarried', getClosedFieldValue('closedPrimaryMaritalUnmarried'));
        formData.append('primary_applicant_pan_no', getClosedFieldValue('closedPrimaryPanNo').toUpperCase());
        formData.append('primary_applicant_aadhaar_no', getClosedFieldValue('closedPrimaryAadhaarNo'));
        formData.append('primary_applicant_address', getClosedFieldValue('closedPrimaryAddress'));
        formData.append('primary_applicant_city', getClosedFieldValue('closedPrimaryCity'));
        formData.append('primary_applicant_state', getClosedFieldValue('closedPrimaryState'));
        formData.append('primary_applicant_country', getClosedFieldValue('closedPrimaryCountry'));
        formData.append('primary_applicant_pin', getClosedFieldValue('closedPrimaryPin'));
        formData.append('primary_applicant_email', getClosedFieldValue('closedPrimaryEmail'));
        formData.append('primary_applicant_tel_no', getClosedFieldValue('closedPrimaryTel'));
        formData.append('primary_applicant_mobile_no', getClosedFieldValue('closedPrimaryMobile'));
        formData.append('primary_applicant_fax_no', getClosedFieldValue('closedPrimaryFax'));

        formData.append('joint_applicant_name', getClosedFieldValue('closedJointApplicantName'));
        formData.append('joint_applicant_relation_name', getClosedFieldValue('closedJointApplicantRelationName'));
        formData.append('joint_applicant_date_of_birth', getClosedFieldValue('closedJointApplicantDob'));
        formData.append('joint_applicant_nationality', getClosedFieldValue('closedJointApplicantNationality'));
        formData.append('joint_applicant_occupation_service', getClosedFieldValue('closedJointOccupationService'));
        formData.append('joint_applicant_occupation_professional', getClosedFieldValue('closedJointOccupationProfessional'));
        formData.append('joint_applicant_occupation_housewife', getClosedFieldValue('closedJointOccupationHousewife'));
        formData.append('joint_applicant_occupation_business', getClosedFieldValue('closedJointOccupationBusiness'));
        formData.append('joint_applicant_occupation_any_other', getClosedFieldValue('closedJointOccupationAnyOther'));
        formData.append('joint_applicant_resident_indian', getClosedFieldValue('closedJointResidentIndian'));
        formData.append('joint_applicant_resident_non_resident', getClosedFieldValue('closedJointResidentNonResident'));
        formData.append('joint_applicant_resident_foreign_national', getClosedFieldValue('closedJointResidentForeignNational'));
        formData.append('joint_applicant_resident_other', getClosedFieldValue('closedJointResidentOther'));
        formData.append('joint_applicant_marital_status_married', getClosedFieldValue('closedJointMaritalMarried'));
        formData.append('joint_applicant_marital_status_unmarried', getClosedFieldValue('closedJointMaritalUnmarried'));
        formData.append('joint_applicant_pan_no', getClosedFieldValue('closedJointPanNo').toUpperCase());
        formData.append('joint_applicant_address', getClosedFieldValue('closedJointAddress'));
        formData.append('joint_applicant_city', getClosedFieldValue('closedJointCity'));
        formData.append('joint_applicant_state', getClosedFieldValue('closedJointState'));
        formData.append('joint_applicant_country', getClosedFieldValue('closedJointCountry'));
        formData.append('joint_applicant_pin', getClosedFieldValue('closedJointPin'));
        formData.append('joint_applicant_email', getClosedFieldValue('closedJointEmail'));
        formData.append('joint_applicant_tel_no', getClosedFieldValue('closedJointTel'));
        formData.append('joint_applicant_mobile_no', getClosedFieldValue('closedJointMobile'));
        formData.append('joint_applicant_fax_no', getClosedFieldValue('closedJointFax'));

        formData.append('unit_details_unit_no', getClosedFieldValue('closedUnitNo'));
        formData.append('unit_details_block_cluster', getClosedFieldValue('closedUnitBlockCluster'));
        formData.append('unit_details_floor', getClosedFieldValue('closedUnitFloor'));
        formData.append('unit_details_carpet_area_sq_mt', getClosedFieldValue('closedUnitCarpetAreaSqMt'));
        formData.append('unit_details_carpet_area_sq_ft', getClosedFieldValue('closedUnitCarpetAreaSqFt'));
        formData.append('unit_details_super_area_sq_mt', getClosedFieldValue('closedUnitSuperAreaSqMt'));
        formData.append('unit_details_super_area_sq_ft', getClosedFieldValue('closedUnitSuperAreaSqFt'));
        formData.append('unit_details_basic_sale_price', getClosedFieldValue('closedUnitBasicSalePrice'));
        formData.append('unit_details_plc_amount', getClosedFieldValue('closedUnitPlcAmount'));
        formData.append('unit_details_car_parking_covered', getClosedFieldValue('closedUnitCarParkingCovered'));
        formData.append('unit_details_car_parking_open', getClosedFieldValue('closedUnitCarParkingOpen'));
        formData.append('unit_details_club_membership_charges', getClosedFieldValue('closedUnitClubMembershipCharges'));
        formData.append('unit_details_payment_plan_construction_linked', getClosedFieldValue('closedUnitPaymentPlanConstructionLinked'));
        formData.append('unit_details_payment_plan_down_payment', getClosedFieldValue('closedUnitPaymentPlanDownPayment'));
        formData.append('unit_details_payment_plan_other', getClosedFieldValue('closedUnitPaymentPlanOther'));
        formData.append('unit_details_payment_plan_other_text', getClosedFieldValue('closedUnitPaymentPlanOtherText'));
    }

    function getClosedFileName(path) {
        const normalized = String(path || '').trim();
        if (!normalized) return 'File';
        const parts = normalized.split('/');
        return parts[parts.length - 1] || normalized;
    }

    function isClosedImageFile(path) {
        return /\.(png|jpe?g|webp|gif)$/i.test(String(path || ''));
    }

    function createClosedExistingCard({ path, removable = false, removeHandler = '', image = false, label = '', directoryHint = '' }) {
        const safePath = escapeClosedHtml(path);
        const safeLabel = escapeClosedHtml(label || getClosedFileName(path));
        const fileUrl = getClosedStorageUrl(path, directoryHint);
        const safeUrl = escapeClosedHtml(fileUrl);

        return `
            <div class="closed-existing-card">
                ${removable ? `<button type="button" class="closed-existing-remove" onclick="${removeHandler}" aria-label="Remove file"><i class="fas fa-times"></i></button>` : ''}
                ${image ? `<img src="${safeUrl}" alt="${safeLabel}">` : `
                    <div class="closed-existing-card-body">
                        <div class="closed-existing-card-name">${safeLabel}</div>
                        <a class="closed-existing-card-link" href="${safeUrl}" target="_blank" rel="noopener">Open file</a>
                    </div>
                `}
                ${image ? `
                    <div class="closed-existing-card-body">
                        <div class="closed-existing-card-name">${safeLabel}</div>
                        <a class="closed-existing-card-link" href="${safeUrl}" target="_blank" rel="noopener">Open image</a>
                    </div>
                ` : ''}
            </div>
        `;
    }

    function renderClosedExistingFiles() {
        const kycContainer = document.getElementById('closedExistingKycDocs');
        const proofContainer = document.getElementById('closedExistingProofPhotos');
        const paymentProofContainer = document.getElementById('closedExistingBookingPaymentProofs');
        const panel = document.getElementById('closedKycExistingFilesPanel');

        if (kycContainer) {
            kycContainer.innerHTML = closedKycState.existingKycDocuments.length
                ? closedKycState.existingKycDocuments.map((path, index) => createClosedExistingCard({
                    path,
                    removable: !closedKycState.readonly,
                    removeHandler: `removeClosedExistingKycDocument(${index})`,
                    image: isClosedImageFile(path),
                    directoryHint: 'closings/kyc',
                })).join('')
                : '<p class="closed-existing-empty">No retained KYC documents.</p>';
        }

        if (proofContainer) {
            proofContainer.innerHTML = closedKycState.existingProofPhotos.length
                ? closedKycState.existingProofPhotos.map((path, index) => createClosedExistingCard({
                    path,
                    removable: !closedKycState.readonly,
                    removeHandler: `removeClosedExistingProofPhoto(${index})`,
                    image: true,
                    directoryHint: 'site-visits/closer-proof',
                })).join('')
                : '<p class="closed-existing-empty">No retained proof photos.</p>';
        }

        if (paymentProofContainer) {
            paymentProofContainer.innerHTML = closedKycState.existingBookingPaymentProofs.length
                ? closedKycState.existingBookingPaymentProofs.map((path, index) => createClosedExistingCard({
                    path,
                    removable: !closedKycState.readonly,
                    removeHandler: `removeClosedExistingBookingPaymentProof(${index})`,
                    image: isClosedImageFile(path),
                    directoryHint: 'closings/payment-proofs',
                })).join('')
                : '<p class="closed-existing-empty">No retained booking payment proofs.</p>';
        }

        if (panel) {
            panel.hidden = !closedKycState.existingKycDocuments.length
                && !closedKycState.existingProofPhotos.length
                && !closedKycState.existingBookingPaymentProofs.length;
        }
    }

    function removeClosedExistingKycDocument(index) {
        closedKycState.existingKycDocuments.splice(index, 1);
        renderClosedExistingFiles();
    }

    function removeClosedExistingProofPhoto(index) {
        closedKycState.existingProofPhotos.splice(index, 1);
        renderClosedExistingFiles();
    }

    function removeClosedExistingBookingPaymentProof(index) {
        closedKycState.existingBookingPaymentProofs.splice(index, 1);
        renderClosedExistingFiles();
    }

    function renderClosedSelectedFiles(inputId, containerId, imageOnly = false) {
        const input = document.getElementById(inputId);
        const container = document.getElementById(containerId);
        if (!input || !container) return;

        const files = Array.from(input.files || []);
        if (!files.length) {
            container.innerHTML = '';
            return;
        }

        container.innerHTML = files.map((file) => {
            const objectUrl = URL.createObjectURL(file);
            const safeName = escapeClosedHtml(file.name || 'File');
            const safeUrl = escapeClosedHtml(objectUrl);

            return `
                <div class="closed-existing-card">
                    ${imageOnly ? `<img src="${safeUrl}" alt="${safeName}">` : ''}
                    <div class="closed-existing-card-body">
                        <div class="closed-existing-card-name">${safeName}</div>
                        ${imageOnly ? `<a class="closed-existing-card-link" href="${safeUrl}" target="_blank" rel="noopener">Preview</a>` : `<span class="closed-existing-card-link">Ready to upload</span>`}
                    </div>
                </div>
            `;
        }).join('');
    }

    function closedNotify(message, type = 'success') {
        if (typeof showNotification === 'function') {
            showNotification(message, type, 3000);
            return;
        }

        window.alert(message);
    }

    function getClosedValidationMessage(result, fallbackMessage) {
        if (result?.errors && typeof result.errors === 'object') {
            const messages = Object.values(result.errors)
                .flat()
                .filter(Boolean);

            if (messages.length) {
                return messages[0];
            }
        }

        return result?.message || fallbackMessage;
    }

    function toggleClosedModalState(isOpen) {
        document.body.style.overflow = isOpen ? 'hidden' : '';
    }

    function formatClosedDate(value, withTime = false) {
        if (!value) return 'N/A';
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return 'N/A';
        return date.toLocaleString('en-IN', withTime ? {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        } : {
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        });
    }

    function updateClosedStats(meta, items) {
        document.getElementById('closedTotalCount').textContent = meta?.total ?? 0;
        document.getElementById('closedPageCount').textContent = Array.isArray(items) ? items.length : 0;
        document.getElementById('closedLastRefresh').textContent = new Date().toLocaleTimeString('en-IN', {
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function updateClosedBucketHeader() {
        const config = CLOSED_BUCKETS[closedActiveBucket] || CLOSED_BUCKETS.visited_clients;
        document.getElementById('closedBucketTitle').textContent = config.title;
        document.getElementById('closedBucketCopy').textContent = config.copy;
    }

    function setClosedActiveBucket(bucket) {
        closedActiveBucket = CLOSED_BUCKETS[bucket] ? bucket : 'visited_clients';
        document.querySelectorAll('.closed-pipeline-tab').forEach((tab) => {
            tab.classList.toggle('active', tab.dataset.bucket === closedActiveBucket);
        });
        updateClosedBucketHeader();
    }

    function updateClosedCounts(counts = {}) {
        document.getElementById('bucketCountVisited').textContent = counts.visited_clients ?? 0;
        document.getElementById('bucketCountDrafts').textContent = counts.closer_drafts ?? 0;
        document.getElementById('bucketCountPending').textContent = counts.pending_crm ?? 0;
        document.getElementById('bucketCountCorrection').textContent = counts.correction_required ?? 0;
        document.getElementById('bucketCountApproved').textContent = counts.approved_closers ?? 0;
        document.getElementById('bucketCountIncentive').textContent = counts.incentives ?? 0;
    }

    function getClosedVisitById(siteVisitId) {
        return closedRawVisits.find((visit) => Number(visit.id) === Number(siteVisitId)) || null;
    }

    function applyClosedClientFilters(visits) {
        const source = document.getElementById('closedSourceFilter').value;
        const sort = document.getElementById('closedSortFilter').value;
        let filtered = Array.isArray(visits) ? [...visits] : [];

        if (source) {
            filtered = filtered.filter((visit) => (visit.lead?.source || '') === source);
        }

        filtered.sort((a, b) => {
            switch (sort) {
                case 'oldest':
                    return new Date(a.updated_at || a.created_at || 0) - new Date(b.updated_at || b.created_at || 0);
                case 'name_asc':
                    return (a.lead?.name || a.customer_name || '').localeCompare(b.lead?.name || b.customer_name || '');
                case 'name_desc':
                    return (b.lead?.name || b.customer_name || '').localeCompare(a.lead?.name || a.customer_name || '');
                case 'latest':
                default:
                    return new Date(b.updated_at || b.created_at || 0) - new Date(a.updated_at || a.created_at || 0);
            }
        });

        return filtered;
    }

    function getClosedStage(visit) {
        if (visit.incentive?.status === 'verified') return { key: 'verified', label: 'Incentive Verified' };
        if (visit.incentive?.status === 'pending_finance_manager') return { key: 'submitted', label: 'Incentive Submitted' };
        if (visit.closer_status === 'approved') return { key: 'verified', label: visit.can_fill_incentive ? 'Incentive Pending' : 'Closer Approved' };
        if (visit.closer_status === 'pending_crm') return { key: 'pending', label: 'CRM Approval Pending' };
        if (visit.closer_status === 'correction_required') return { key: 'rejected', label: 'Correction Required' };
        if (visit.closer_status === 'rejected') return { key: 'rejected', label: 'Closer Rejected' };
        if (visit.closer_status === 'draft') return { key: 'requested', label: 'Closer Draft' };
        return { key: 'requested', label: 'Visited Client' };
    }

    function getClosedActionButtons(visit) {
        const lead = visit.lead || {};
        const buttons = [];

        if (closedActiveBucket === 'visited_clients') {
            buttons.push(`<button type="button" class="closed-btn closed-btn-primary" onclick="moveVisitToCloser(${visit.id})"><i class="fas fa-arrow-right"></i>Move To Closer</button>`);
        }

        if (['draft', 'pending_crm', 'correction_required', 'rejected'].includes(visit.closer_status)) {
            const closerActionLabel = visit.closer_status === 'draft'
                ? 'Fill KYC'
                : (visit.closer_status === 'pending_crm' ? 'Edit KYC' : 'Resubmit Closer Form');
            buttons.push(`<button type="button" class="closed-btn closed-btn-primary" onclick="openClosedKycModal(${visit.id})"><i class="fas fa-id-card"></i>${closerActionLabel}</button>`);
        }

        if (['approved', 'verified'].includes(visit.closer_status)) {
            buttons.push(`<button type="button" class="closed-btn closed-btn-secondary" onclick="openClosedKycModal(${visit.id}, true)"><i class="fas fa-eye"></i>View KYC</button>`);
        }

        if (visit.can_fill_incentive) {
            buttons.push(`<button type="button" class="closed-btn closed-btn-warning" onclick="openClosedIncentiveModal(${visit.id})"><i class="fas fa-wallet"></i>Fill Incentive Form</button>`);
        } else if (closedActiveBucket === 'incentives') {
            const incentiveStatus = visit.incentive?.status || '';
            const incentiveLabel = incentiveStatus === 'verified'
                ? 'View Incentive'
                : (incentiveStatus === 'pending_finance_manager'
                    ? 'Edit Incentive'
                    : (incentiveStatus === 'rejected' ? 'Resubmit Incentive' : 'Submit Incentive'));
            buttons.push(`<button type="button" class="closed-btn closed-btn-warning" onclick="openClosedIncentiveModal(${visit.id})"><i class="fas fa-wallet"></i>${incentiveLabel}</button>`);
        }

        if (lead.id) {
            buttons.push(`<a href="/leads/${lead.id}" class="closed-btn closed-btn-primary"><i class="fas fa-eye"></i>View Detail</a>`);
            buttons.push(`<a href="/leads/${lead.id}#timeline" class="closed-btn closed-btn-secondary"><i class="fas fa-clock-rotate-left"></i>Activity</a>`);
        }

        return buttons.join('');
    }

    function createClosedLeadCard(visit) {
        const lead = visit.lead || {};
        const stage = getClosedStage(visit);
        const reviewNote = visit.closer_review_remark || visit.closer_rejection_reason || visit.closing_rejection_reason || '';
        const customerName = lead.name || visit.customer_name || 'N/A';
        const phone = lead.phone || visit.phone || '';
        const email = lead.email || '';
        const subText = [phone || 'No phone', email].filter(Boolean).join(' • ');
        const initials = String(customerName).trim().split(/\s+/)
            .map((p) => p.charAt(0)).filter(Boolean).slice(0, 2).join('').toUpperCase() || '?';
        const wrapper = document.createElement('article');
        wrapper.className = 'closed-card';

        wrapper.innerHTML = `
            <div class="closed-card-top">
                <div class="closed-card-identity">
                    <div class="closed-card-avatar">${escapeClosedHtml(initials)}</div>
                    <div class="closed-card-identity-text">
                        <div class="closed-card-name">${escapeClosedHtml(customerName)}</div>
                        <div class="closed-card-sub">${escapeClosedHtml(subText)}</div>
                    </div>
                </div>
                <div class="closed-stage ${stage.key}">
                    <i class="fas fa-circle-check"></i>
                    ${escapeClosedHtml(stage.label)}
                </div>
            </div>
            <div class="closed-meta">
                <div class="closed-meta-item">
                    <span class="closed-meta-label">Location</span>
                    <span class="closed-meta-value">${escapeClosedHtml(lead.preferred_location || visit.property_address || 'N/A')}</span>
                </div>
                <div class="closed-meta-item">
                    <span class="closed-meta-label">Budget</span>
                    <span class="closed-meta-value">${escapeClosedHtml(lead.budget || visit.budget_range || 'N/A')}</span>
                </div>
                <div class="closed-meta-item">
                    <span class="closed-meta-label">Project</span>
                    <span class="closed-meta-value">${escapeClosedHtml(visit.project || visit.property_name || 'N/A')}</span>
                </div>
                <div class="closed-meta-item">
                    <span class="closed-meta-label">Updated</span>
                    <span class="closed-meta-value">${escapeClosedHtml(formatClosedDate(visit.updated_at || visit.completed_at, true))}</span>
                </div>
                <div class="closed-meta-item">
                    <span class="closed-meta-label">Assigned To</span>
                    <span class="closed-meta-value">${escapeClosedHtml(visit.assignedTo?.name || visit.creator?.name || 'N/A')}</span>
                </div>
                <div class="closed-meta-item">
                    <span class="closed-meta-label">KYC Files</span>
                    <span class="closed-meta-value">${visit.kyc_documents_count || 0} docs • ${visit.proof_photos_count || 0} proofs</span>
                </div>
            </div>
            ${visit.incentive?.status === 'rejected' ? `
                <div class="closed-inline-alert closed-inline-alert--danger">
                    <strong>Incentive rejected:</strong> ${escapeClosedHtml(visit.incentive?.rejection_reason || 'No remark shared.')}<br>
                    <span>Correct the amount and resubmit.</span>
                </div>
            ` : ''}
            ${['correction_required', 'rejected'].includes(visit.closer_status) ? `
                <div class="closed-inline-alert closed-inline-alert--danger">
                    <strong>${visit.closer_status === 'rejected' ? 'Closer rejected:' : 'CRM remark:'}</strong> ${escapeClosedHtml(reviewNote || 'No remark shared.')}<br>
                    <span>Purana form prefilled milega. Proof photo remove karke new upload ke saath resubmit kar sakte ho.</span>
                </div>
            ` : ''}
            <div class="closed-actions">
                ${getClosedActionButtons(visit)}
            </div>
        `;

        return wrapper;
    }

    function renderClosedLeads(visits) {
        const grid = document.getElementById('closedLeadsGrid');
        const empty = document.getElementById('closedEmptyState');
        grid.innerHTML = '';

        if (!visits.length) {
            grid.hidden = true;
            empty.hidden = false;
            return;
        }

        visits.forEach((visit) => grid.appendChild(createClosedLeadCard(visit)));
        empty.hidden = true;
        grid.hidden = false;
    }

    function renderClosedPagination(meta) {
        const pagination = document.getElementById('closedPagination');
        if (!meta || (meta.last_page || 1) <= 1) {
            pagination.hidden = true;
            pagination.innerHTML = '';
            return;
        }

        let controls = '';
        const currentPage = meta.current_page || 1;
        const lastPage = meta.last_page || 1;
        controls += `<button class="closed-page-btn" ${currentPage <= 1 ? 'disabled' : ''} onclick="loadClosedLeads(${currentPage - 1})">Prev</button>`;
        for (let i = 1; i <= lastPage; i++) {
            if (i === 1 || i === lastPage || (i >= currentPage - 2 && i <= currentPage + 2)) {
                controls += `<button class="closed-page-btn ${i === currentPage ? 'active' : ''}" onclick="loadClosedLeads(${i})">${i}</button>`;
            } else if (i === currentPage - 3 || i === currentPage + 3) {
                controls += `<button class="closed-page-btn" disabled>...</button>`;
            }
        }
        controls += `<button class="closed-page-btn" ${currentPage >= lastPage ? 'disabled' : ''} onclick="loadClosedLeads(${currentPage + 1})">Next</button>`;

        pagination.innerHTML = `<div class="closed-pagination-controls">${controls}</div><div class="closed-results-copy">Showing ${meta.from || 0} to ${meta.to || 0} of ${meta.total || 0} closed pipeline records</div>`;
        pagination.hidden = false;
    }

    // Legacy KYC modal branch retained temporarily for safe staged cleanup. Active branch is the schema-driven implementation below.
    function __legacy_openClosedKycModal_v1(siteVisitId) {
        const visit = getClosedVisitById(siteVisitId);
        if (!visit) {
            closedNotify('Site visit data not found. Please refresh and try again.', 'error');
            return;
        }

        activeClosedVisitId = siteVisitId;
        document.getElementById('closedCustomerName').value = visit.customer_name || visit.lead?.name || '';
        document.getElementById('closedNomineeName').value = visit.nominee_name || '';
        document.getElementById('closedSecondCustomerName').value = visit.second_customer_name || '';
        document.getElementById('closedCustomerDob').value = visit.customer_dob || '';
        document.getElementById('closedPanCard').value = visit.pan_card || '';
        document.getElementById('closedAadhaarCard').value = visit.aadhaar_card_no || '';
        document.getElementById('closedKycDocs').value = '';
        document.getElementById('closedProofPhotos').value = '';

        const noteElement = document.getElementById('closedKycModalNote');
        const existingFilesElement = document.getElementById('closedKycExistingFiles');
        const submitButton = document.getElementById('closedKycSubmitButton');
        const existingBits = [];

        if ((visit.kyc_documents_count || 0) > 0) {
            existingBits.push(`${visit.kyc_documents_count} existing KYC docs`);
        }
        if ((visit.proof_photos_count || 0) > 0) {
            existingBits.push(`${visit.proof_photos_count} existing proof photos`);
        }

        existingFilesElement.hidden = existingBits.length === 0;
        existingFilesElement.textContent = existingBits.length
            ? `${existingBits.join(' • ')}. Naye uploads add karne par purane files preserve rahenge.`
            : '';

        if (visit.closer_status === 'correction_required') {
            noteElement.textContent = visit.closer_review_remark
                ? `CRM correction remark: ${visit.closer_review_remark}`
                : 'CRM ne correction request bheji hai. Same KYC update karke resubmit karo.';
            submitButton.textContent = 'Resubmit To CRM';
        } else {
            noteElement.textContent = 'Customer, nominee, identity details, KYC documents, aur proof photos required hain. Submit hone ke baad incentive form unlock hoga.';
            submitButton.textContent = 'Submit KYC';
        }

        const modal = document.getElementById('closedKycModal');
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        modal.classList.add('show');
        toggleClosedModalState(true);
    }

    function __legacy_closeClosedKycModal_v1() {
        const modal = document.getElementById('closedKycModal');
        modal.classList.remove('show');
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        activeClosedVisitId = null;
        toggleClosedModalState(false);
    }

    async function __legacy_submitClosedKyc_v1(mode = 'submit') {
        if (!activeClosedVisitId) return;
        const visit = getClosedVisitById(activeClosedVisitId);
        if (!visit) {
            closedNotify('Site visit data not found. Please refresh and try again.', 'error');
            return;
        }

        const customerName = document.getElementById('closedCustomerName').value.trim();
        const nomineeName = document.getElementById('closedNomineeName').value.trim();
        const secondCustomerName = document.getElementById('closedSecondCustomerName').value.trim();
        const customerDob = document.getElementById('closedCustomerDob').value;
        const panCard = document.getElementById('closedPanCard').value.trim().toUpperCase();
        const aadhaarNumber = document.getElementById('closedAadhaarCard').value.trim();

        const formData = new FormData();
        formData.append('customer_name', customerName);
        formData.append('nominee_name', nomineeName);
        formData.append('second_customer_name', secondCustomerName);
        formData.append('customer_dob', customerDob);
        formData.append('pan_card', panCard);
        formData.append('aadhaar_card_no', aadhaarNumber);

        const kycDocs = document.getElementById('closedKycDocs').files;
        const proofPhotos = document.getElementById('closedProofPhotos').files;
        const hasExistingKycDocs = (visit.kyc_documents_count || 0) > 0;
        const hasExistingProofPhotos = (visit.proof_photos_count || 0) > 0;
        if (mode === 'submit' && !customerName) {
            closedNotify('Customer name is required before submission.', 'error');
            return;
        }
        if (mode === 'submit' && !nomineeName) {
            closedNotify('Nominee name is required before submission.', 'error');
            return;
        }
        if (mode === 'submit' && !customerDob) {
            closedNotify('Customer DOB is required before submission.', 'error');
            return;
        }
        if (mode === 'submit' && !panCard) {
            closedNotify('PAN card is required before submission.', 'error');
            return;
        }
        if (mode === 'submit' && !aadhaarNumber) {
            closedNotify('Aadhaar number is required before submission.', 'error');
            return;
        }
        if (mode === 'submit' && !hasExistingKycDocs && !kycDocs.length) {
            closedNotify('At least one KYC document is required before submission.', 'error');
            return;
        }
        if (mode === 'submit' && !hasExistingProofPhotos && !proofPhotos.length) {
            closedNotify('At least one proof photo is required before submission.', 'error');
            return;
        }
        for (let i = 0; i < kycDocs.length; i++) formData.append('kyc_documents[]', kycDocs[i]);
        for (let i = 0; i < proofPhotos.length; i++) formData.append('proof_photos[]', proofPhotos[i]);

        let endpoint = `/site-visits/${activeClosedVisitId}/kyc/draft`;
        let defaultMessage = 'KYC draft saved successfully.';
        if (mode === 'submit') {
            endpoint = visit.closer_status === 'correction_required'
                ? `/site-visits/${activeClosedVisitId}/closer/resubmit`
                : `/site-visits/${activeClosedVisitId}/kyc/submit`;
            defaultMessage = visit.closer_status === 'correction_required'
                ? 'KYC resubmitted successfully.'
                : 'KYC submitted successfully.';
        }

        try {
            const response = await fetch(`${CLOSED_API_BASE_URL}${endpoint}`, {
                method: 'POST',
                headers: getClosedAuthHeaders(),
                body: formData,
            });
            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(getClosedValidationMessage(result, `Failed to ${mode === 'submit' ? 'submit' : 'save'} KYC`));
            }
            closeClosedKycModal();
            closedNotify(result.message || defaultMessage, 'success');
            if (mode === 'draft') {
                setClosedActiveBucket('closer_drafts');
            } else if (visit.closer_status === 'correction_required') {
                setClosedActiveBucket('pending_crm');
            }
            loadClosedLeads(closedLeadPage);
        } catch (error) {
            closedNotify(error.message || `Failed to ${mode === 'submit' ? 'submit' : 'save'} KYC`, 'error');
        }
    }

    function __legacy_openClosedKycModal_v2(siteVisitId) {
        const visit = getClosedVisitById(siteVisitId);
        if (!visit) {
            closedNotify('Site visit data not found. Please refresh and try again.', 'error');
            return;
        }

        activeClosedVisitId = siteVisitId;
        document.getElementById('closedKycDocs').value = '';
        document.getElementById('closedProofPhotos').value = '';
        document.getElementById('closedSelectedKycDocs').innerHTML = '';
        document.getElementById('closedSelectedProofPhotos').innerHTML = '';
        fillClosedKycSections(visit);

        closedKycState.existingKycDocuments = Array.isArray(visit.kyc_documents) ? [...visit.kyc_documents] : [];
        closedKycState.existingProofPhotos = Array.isArray(visit.closer_request_proof_photos) ? [...visit.closer_request_proof_photos] : [];
        renderClosedExistingFiles();

        const noteElement = document.getElementById('closedKycModalNote');
        const existingFilesElement = document.getElementById('closedKycExistingFiles');
        const submitButton = document.getElementById('closedKycSubmitButton');
        const titleElement = document.getElementById('closedKycModalTitle');
        const subtitleElement = document.getElementById('closedKycModalSubtitle');
        const existingBits = [];

        if (closedKycState.existingKycDocuments.length > 0) {
            existingBits.push(`${closedKycState.existingKycDocuments.length} existing KYC docs`);
        }
        if (closedKycState.existingProofPhotos.length > 0) {
            existingBits.push(`${closedKycState.existingProofPhotos.length} existing proof photos`);
        }

        existingFilesElement.hidden = existingBits.length === 0;
        existingFilesElement.textContent = existingBits.length
            ? `${existingBits.join(' • ')}. Retain, remove, ya new upload mix karke resubmit kar sakte ho.`
            : '';

        const isResubmission = ['correction_required', 'rejected'].includes(visit.closer_status);
        noteElement.classList.toggle('is-danger', isResubmission);

        if (isResubmission) {
            titleElement.textContent = 'Resubmit Closer Form';
            subtitleElement.textContent = 'Previous submission prefilled hai. Required changes karke dubara CRM review me bhejo.';
            noteElement.textContent = visit.closer_review_remark
                ? `CRM correction remark: ${visit.closer_review_remark}`
                : (visit.closer_rejection_reason || visit.closing_rejection_reason
                    ? `Closer reject remark: ${visit.closer_rejection_reason || visit.closing_rejection_reason}`
                    : 'CRM ne resubmission request bheji hai. Same KYC update karke resubmit karo.');
            submitButton.textContent = 'Resubmit To CRM';
        } else {
            titleElement.textContent = 'Fill KYC Form';
            subtitleElement.textContent = 'Close verification ke baad full KYC yahin se submit karo.';
            noteElement.textContent = 'Customer, nominee, identity details, KYC documents, aur proof photos required hain. Submit hone ke baad incentive form unlock hoga.';
            submitButton.textContent = 'Submit KYC';
        }

        const modal = document.getElementById('closedKycModal');
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        modal.classList.add('show');
        toggleClosedModalState(true);
    }

    function __legacy_closeClosedKycModal_v2() {
        const modal = document.getElementById('closedKycModal');
        modal.classList.remove('show');
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        activeClosedVisitId = null;
        closedKycState.existingKycDocuments = [];
        closedKycState.existingProofPhotos = [];
        renderClosedExistingFiles();
        toggleClosedModalState(false);
    }

    async function __legacy_submitClosedKyc_v2(mode = 'submit') {
        if (!activeClosedVisitId) return;
        const visit = getClosedVisitById(activeClosedVisitId);
        if (!visit) {
            closedNotify('Site visit data not found. Please refresh and try again.', 'error');
            return;
        }

        const customerName = getClosedFieldValue('closedPrimaryApplicantName');
        const nomineeName = getClosedFieldValue('closedJointApplicantName');
        const customerDob = getClosedFieldValue('closedPrimaryApplicantDob');
        const panCard = getClosedFieldValue('closedPrimaryPanNo').toUpperCase();
        const aadhaarNumber = getClosedFieldValue('closedPrimaryAadhaarNo');
        const kycDocs = document.getElementById('closedKycDocs').files;
        const proofPhotos = document.getElementById('closedProofPhotos').files;
        const hasExistingKycDocs = closedKycState.existingKycDocuments.length > 0;
        const hasExistingProofPhotos = closedKycState.existingProofPhotos.length > 0;

        if (mode === 'submit' && !customerName) {
            closedNotify('Customer name is required before submission.', 'error');
            return;
        }
        if (mode === 'submit' && !nomineeName) {
            closedNotify('Nominee name is required before submission.', 'error');
            return;
        }
        if (mode === 'submit' && !customerDob) {
            closedNotify('Customer DOB is required before submission.', 'error');
            return;
        }
        if (mode === 'submit' && !panCard) {
            closedNotify('PAN card is required before submission.', 'error');
            return;
        }
        if (mode === 'submit' && !aadhaarNumber) {
            closedNotify('Aadhaar number is required before submission.', 'error');
            return;
        }
        if (mode === 'submit' && !hasExistingKycDocs && !kycDocs.length) {
            closedNotify('At least one KYC document is required before submission.', 'error');
            return;
        }
        if (mode === 'submit' && !hasExistingProofPhotos && !proofPhotos.length) {
            closedNotify('At least one proof photo is required before submission.', 'error');
            return;
        }

        const formData = new FormData();
        appendClosedStructuredKycFields(formData);
        closedKycState.existingKycDocuments.forEach((path) => formData.append('existing_kyc_documents[]', path));
        closedKycState.existingProofPhotos.forEach((path) => formData.append('existing_proof_photos[]', path));
        for (let i = 0; i < kycDocs.length; i++) formData.append('kyc_documents[]', kycDocs[i]);
        for (let i = 0; i < proofPhotos.length; i++) formData.append('proof_photos[]', proofPhotos[i]);

        let endpoint = `/site-visits/${activeClosedVisitId}/kyc/draft`;
        let defaultMessage = 'KYC draft saved successfully.';
        if (mode === 'submit') {
            const isResubmission = ['correction_required', 'rejected'].includes(visit.closer_status);
            endpoint = isResubmission
                ? `/site-visits/${activeClosedVisitId}/closer/resubmit`
                : `/site-visits/${activeClosedVisitId}/kyc/submit`;
            defaultMessage = isResubmission
                ? 'KYC resubmitted successfully.'
                : 'KYC submitted successfully.';
        }

        try {
            const response = await fetch(`${CLOSED_API_BASE_URL}${endpoint}`, {
                method: 'POST',
                headers: getClosedAuthHeaders(),
                body: formData,
            });
            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(getClosedValidationMessage(result, `Failed to ${mode === 'submit' ? 'submit' : 'save'} KYC`));
            }

            closeClosedKycModal();
            closedNotify(result.message || defaultMessage, 'success');
            if (mode === 'draft') {
                setClosedActiveBucket('closer_drafts');
            } else if (['correction_required', 'rejected'].includes(visit.closer_status)) {
                setClosedActiveBucket('pending_crm');
            }
            loadClosedLeads(closedLeadPage);
        } catch (error) {
            closedNotify(error.message || `Failed to ${mode === 'submit' ? 'submit' : 'save'} KYC`, 'error');
        }
    }

    function openClosedIncentiveModal(siteVisitId) {
        activeClosedVisitId = siteVisitId;
        const visit = getClosedVisitById(siteVisitId);
        document.getElementById('closedIncentiveAmount').value = visit?.incentive?.amount || visit?.incentive_amount || '';
        const title = document.querySelector('#closedIncentiveModal h3');
        const note = document.querySelector('#closedIncentiveModal .closed-modal-note');
        const submitButton = document.querySelector('#closedIncentiveModal .closed-btn-primary');
        if (visit?.incentive?.status === 'rejected') {
            if (title) title.textContent = 'Resubmit Incentive Request';
            if (note) note.textContent = `Finance remark: ${visit.incentive.rejection_reason || 'Correct the incentive amount and resubmit.'}`;
            if (submitButton) submitButton.textContent = 'Resubmit Incentive';
        } else {
            if (title) title.textContent = 'Submit Incentive Request';
            if (note) note.textContent = 'Incentive request CRM/Admin verification queue me jayega.';
            if (submitButton) submitButton.textContent = 'Submit Incentive';
        }
        const modal = document.getElementById('closedIncentiveModal');
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        modal.classList.add('show');
        toggleClosedModalState(true);
    }

    function closeClosedIncentiveModal() {
        const modal = document.getElementById('closedIncentiveModal');
        modal.classList.remove('show');
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        activeClosedVisitId = null;
        toggleClosedModalState(false);
    }

    async function submitClosedIncentive() {
        if (!activeClosedVisitId) return;
        const amount = parseFloat(document.getElementById('closedIncentiveAmount').value || '0');
        if (!amount || amount <= 0) {
            closedNotify('Please enter a valid incentive amount.', 'error');
            return;
        }
        try {
            const response = await fetch(`${CLOSED_API_BASE_URL}/site-visits/${activeClosedVisitId}/request-incentive`, {
                method: 'POST',
                headers: {
                    ...getClosedAuthHeaders(),
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ type: 'closer', amount }),
            });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'Failed to submit incentive request');
            closeClosedIncentiveModal();
            closedNotify(result.message || 'Incentive request submitted successfully.', 'success');
            loadClosedLeads(closedLeadPage);
        } catch (error) {
            closedNotify(error.message || 'Failed to submit incentive request', 'error');
        }
    }

    async function moveVisitToCloser(siteVisitId) {
        try {
            const response = await fetch(`${CLOSED_API_BASE_URL}/site-visits/${siteVisitId}/move-to-closer`, {
                method: 'POST',
                headers: {
                    ...getClosedAuthHeaders(),
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({}),
            });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'Failed to move client into closer pipeline');

            closedNotify(result.message || 'Visited client moved to closer draft.', 'success');
            setClosedActiveBucket('closer_drafts');
            loadClosedLeads(1);
        } catch (error) {
            closedNotify(error.message || 'Failed to move client into closer pipeline', 'error');
        }
    }

    async function loadClosedLeads(page = 1) {
        closedLeadPage = page;
        const loading = document.getElementById('closedLoadingState');
        const grid = document.getElementById('closedLeadsGrid');
        const pagination = document.getElementById('closedPagination');
        const search = document.getElementById('closedSearchInput').value.trim();

        loading.hidden = false;
        grid.hidden = true;
        pagination.hidden = true;

        try {
            const params = new URLSearchParams({
                page: String(page),
                per_page: '15',
                bucket: closedActiveBucket,
            });
            if (search) params.append('search', search);

            const response = await fetch(`${CLOSED_API_BASE_URL}/site-visits/closer-pipeline?${params.toString()}`, {
                headers: getClosedAuthHeaders(),
                credentials: 'same-origin'
            });
            if (!response.ok) throw new Error('Failed to load closer pipeline');

            const payload = await response.json();
            closedVisitMeta = payload.meta || null;
            closedRawVisits = Array.isArray(payload.data) ? payload.data : [];
            closedVisitData = applyClosedClientFilters(closedRawVisits);
            updateClosedCounts(payload.counts || {});
            updateClosedStats(payload.meta, closedVisitData);
            renderClosedLeads(closedVisitData);
            renderClosedPagination(payload.meta || {});
        } catch (error) {
            console.error('Closed leads load error:', error);
            document.getElementById('closedEmptyState').hidden = false;
            document.getElementById('closedEmptyState').textContent = 'Closer pipeline load nahi ho payi. Please refresh and try again.';
        } finally {
            loading.hidden = true;
        }
    }

    function closedKycInputId(fieldKey) {
        return `closed-field-${fieldKey.replace(/[^a-z0-9_-]/gi, '-')}`;
    }

    function getClosedSchemaSectionRemarks(schema) {
        return (schema && typeof schema === 'object' && schema.section_remarks) ? schema.section_remarks : {};
    }

    function getClosedFieldCurrentValue(field) {
        return field && Object.prototype.hasOwnProperty.call(field, 'value') ? field.value : (field.default_value ?? '');
    }

    function isClosedTitleField(fieldKey) {
        return ['primary_applicant_name', 'joint_applicant_name'].includes(fieldKey);
    }

    function isClosedApplicantSection(sectionLabel) {
        return ['1. Sole / First Applicant', '2. Second / Joint Applicant / Nominee'].includes(String(sectionLabel || ''));
    }

    function getClosedApplicantPrefix(sectionLabel) {
        return String(sectionLabel || '').startsWith('1.') ? 'primary_applicant_' : 'joint_applicant_';
    }

    function splitClosedTitleValue(value) {
        const raw = String(value || '').trim();
        if (!raw) {
            return { title: '', name: '' };
        }

        const matchedTitle = CLOSED_KYC_TITLES.find((title) => raw === title || raw.startsWith(`${title} `));
        if (!matchedTitle) {
            return { title: '', name: raw };
        }

        return {
            title: matchedTitle,
            name: raw === matchedTitle ? '' : raw.slice(matchedTitle.length).trim(),
        };
    }

    function getClosedTitleCompositeValue(inputId) {
        const title = document.getElementById(`${inputId}__title`)?.value || '';
        const name = (document.getElementById(`${inputId}__name`)?.value || '').trim();
        if (!name) {
            return '';
        }

        return `${title ? `${title} ` : ''}${name}`.trim();
    }

    function syncClosedTitleValue(fieldKey) {
        const inputId = closedKycInputId(fieldKey);
        const hidden = document.getElementById(inputId);
        if (hidden) {
            hidden.value = getClosedTitleCompositeValue(inputId);
        }
    }

    function getClosedKycSteps(schema) {
        const sections = Array.isArray(schema?.sections) ? schema.sections : [];
        if (sections.length === 0) {
            return [];
        }

        const normalize = (value) => String(value || '').trim().toLowerCase();
        const byLabel = new Map(sections.map((section) => [normalize(section.label), section]));
        const first = byLabel.get(normalize('1. Sole / First Applicant')) || sections[0] || null;
        const setup = byLabel.get(normalize('Booking Setup')) || null;
        const joint = byLabel.get(normalize('2. Second / Joint Applicant / Nominee')) || null;
        const unit = byLabel.get(normalize('Booking & Unit Details')) || byLabel.get(normalize('Details Of The Unit')) || null;
        const docs = byLabel.get(normalize('Documents')) || null;

        const ordered = [
            first ? { label: '1. Sole / First Applicant', sections: [first], isDocuments: false } : null,
            setup ? { label: 'Booking Type & Applicant Setup', sections: [setup], isDocuments: false } : null,
            joint ? { label: '2. Second Applicant', sections: [joint], isDocuments: false, requiresJointApplicant: true } : null,
            unit ? { label: 'Booking & Unit Details', sections: [unit], isDocuments: false } : null,
            docs ? { label: 'Documents & Proof Upload', sections: [docs], isDocuments: true } : null,
        ].filter(Boolean);

        const used = new Set(ordered.flatMap((step) => step.sections));
        const leftovers = sections.filter((section) => !used.has(section));
        leftovers.forEach((section) => {
            ordered.push({ label: section.label || 'Additional Section', sections: [section], isDocuments: false });
        });

        return ordered;
    }

    function isClosedJointApplicantAvailable() {
        const input = document.getElementById(closedKycInputId('booking_joint_applicant_available'));
        if (input) {
            return input.checked;
        }

        const setupField = getClosedKycFieldSchema('booking_joint_applicant_available');
        return Boolean(setupField?.value);
    }

    function isClosedKycStepAvailable(step) {
        if (step?.requiresJointApplicant) {
            return isClosedJointApplicantAvailable();
        }

        return true;
    }

    function getClosedNextAvailableStepIndex(fromIndex, direction) {
        const steps = getClosedKycSteps(closedKycState.schema);
        if (!steps.length) {
            return 0;
        }

        const stepDirection = direction >= 0 ? 1 : -1;
        let index = Math.min(Math.max(fromIndex, 0), steps.length - 1);
        while (steps[index] && !isClosedKycStepAvailable(steps[index])) {
            index += stepDirection;
        }

        return Math.min(Math.max(index, 0), steps.length - 1);
    }

    function syncClosedKycWizardUi() {
        const steps = getClosedKycSteps(closedKycState.schema);
        if (!steps.length) {
            return;
        }

        closedKycState.currentStep = getClosedNextAvailableStepIndex(closedKycState.currentStep || 0, 1);
        const currentIndex = closedKycState.currentStep;

        document.querySelectorAll('[data-closed-kyc-step]').forEach((element) => {
            const stepIndex = Number(element.dataset.closedKycStep || '0');
            element.hidden = stepIndex !== currentIndex;
        });

        document.querySelectorAll('[data-closed-kyc-step-pill]').forEach((element) => {
            const stepIndex = Number(element.dataset.closedKycStepPill || '0');
            element.hidden = !isClosedKycStepAvailable(steps[stepIndex]);
            element.classList.toggle('is-active', stepIndex === currentIndex);
        });

        const caption = document.getElementById('closedKycStepCaption');
        if (caption) {
            caption.hidden = false;
            const availableSteps = steps.filter((step) => isClosedKycStepAvailable(step));
            const visibleIndex = availableSteps.findIndex((step) => step === steps[currentIndex]);
            caption.textContent = `Step ${visibleIndex + 1} of ${availableSteps.length}`;
        }

        const existingPanel = document.getElementById('closedKycExistingFilesPanel');
        const existingNote = document.getElementById('closedKycExistingFiles');
        const isDocumentsStep = !!steps[currentIndex]?.isDocuments;
        if (existingPanel) {
            const hasFiles = closedKycState.existingKycDocuments.length > 0 || closedKycState.existingProofPhotos.length > 0 || closedKycState.existingBookingPaymentProofs.length > 0;
            existingPanel.hidden = !isDocumentsStep || !hasFiles;
        }
        if (existingNote) {
            const hasExistingSummary = existingNote.textContent && existingNote.textContent.trim() !== '';
            existingNote.hidden = !isDocumentsStep || !hasExistingSummary;
        }

        const backButton = document.getElementById('closedKycBackButton');
        const nextButton = document.getElementById('closedKycNextButton');
        const saveButton = document.getElementById('closedKycSaveDraftButton');
        const submitButton = document.getElementById('closedKycSubmitButton');

        if (backButton) {
            backButton.hidden = currentIndex === 0;
        }
        if (nextButton) {
            nextButton.hidden = currentIndex >= steps.length - 1;
        }
        if (submitButton) {
            submitButton.hidden = closedKycState.readonly || currentIndex < steps.length - 1;
        }
        if (saveButton) {
            saveButton.hidden = closedKycState.readonly;
        }
    }

    function applyClosedKycReadonlyState() {
        const form = document.getElementById('closedKycForm');
        if (!form) return;

        form.querySelectorAll('input, select, textarea, button').forEach((element) => {
            element.disabled = !!closedKycState.readonly;
        });
    }

    function setClosedKycStep(stepIndex) {
        const steps = getClosedKycSteps(closedKycState.schema);
        if (!steps[stepIndex] || !isClosedKycStepAvailable(steps[stepIndex])) {
            return;
        }

        closedKycState.currentStep = stepIndex;
        syncClosedKycWizardUi();
    }

    function changeClosedKycStep(delta) {
        const steps = getClosedKycSteps(closedKycState.schema);
        if (!steps.length) {
            return;
        }

        const direction = delta >= 0 ? 1 : -1;
        let nextIndex = Math.min(Math.max((closedKycState.currentStep || 0) + direction, 0), steps.length - 1);
        while (steps[nextIndex] && !isClosedKycStepAvailable(steps[nextIndex])) {
            nextIndex += direction;
        }

        setClosedKycStep(Math.min(Math.max(nextIndex, 0), steps.length - 1));
    }

    function getClosedBookingTypeConfig() {
        const configuredOptions = getClosedKycFieldSchema('booking_unit_type')?.options || [];
        const commercialOptions = new Set(['Retail Space', 'Office Space', 'Studio', 'Suites']);
        return {
            Plot: {
                unitTypeOptions: [],
                requiredKeys: ['unit_details_unit_no', 'unit_details_super_area_sq_ft', 'unit_details_basic_sale_price'],
            },
            Villa: {
                unitTypeOptions: [],
                requiredKeys: ['unit_details_unit_no', 'unit_details_super_area_sq_ft', 'unit_details_build_up_area_sq_ft', 'unit_details_carpet_area_sq_ft', 'unit_details_basic_sale_price'],
            },
            Apartment: {
                unitTypeOptions: configuredOptions.filter((option) => !commercialOptions.has(option)),
                requiredKeys: ['unit_details_unit_no', 'booking_unit_type', 'unit_details_block_cluster', 'unit_details_floor', 'unit_details_super_area_sq_ft', 'unit_details_build_up_area_sq_ft', 'unit_details_carpet_area_sq_ft', 'unit_details_basic_sale_price'],
            },
            Commercial: {
                unitTypeOptions: configuredOptions.filter((option) => commercialOptions.has(option)),
                requiredKeys: ['unit_details_unit_no', 'booking_unit_type', 'unit_details_block_cluster', 'unit_details_floor', 'unit_details_super_area_sq_ft', 'unit_details_carpet_area_sq_ft', 'unit_details_basic_sale_price'],
            },
        };
    }

    function getClosedKycFieldSchema(fieldKey) {
        for (const section of closedKycState.schema?.sections || []) {
            const field = (section.fields || []).find((item) => item.field_key === fieldKey);
            if (field) {
                return field;
            }
        }

        return null;
    }

    function getClosedBookingTypeValue() {
        return String(document.getElementById(closedKycInputId('booking_type'))?.value || '').trim();
    }

    function isClosedFieldVisibleByCondition(field) {
        const condition = field?.visibility_condition;
        if (!condition || typeof condition !== 'object') {
            return true;
        }

        return Object.entries(condition).every(([dependencyKey, expected]) => {
            const dependencyInput = document.getElementById(closedKycInputId(dependencyKey));
            const currentValue = String(dependencyInput?.value || '').trim();
            const expectedValues = Array.isArray(expected) ? expected.map((item) => String(item).trim()) : [String(expected).trim()];
            return expectedValues.includes(currentValue);
        });
    }

    function syncClosedBookingUnitTypeOptions() {
        const unitTypeField = getClosedKycFieldSchema('booking_unit_type');
        const unitTypeInput = document.getElementById(closedKycInputId('booking_unit_type'));
        if (!unitTypeField || !unitTypeInput) {
            return;
        }

        const bookingType = getClosedBookingTypeValue();
        const config = getClosedBookingTypeConfig()[bookingType] || { unitTypeOptions: [] };
        const options = config.unitTypeOptions || [];
        const currentValue = String(unitTypeInput.value || '').trim();

        unitTypeInput.innerHTML = [`<option value="">Select ${escapeClosedHtml(unitTypeField.label || 'Unit Type')}</option>`]
            .concat(options.map((option) => `<option value="${escapeClosedHtml(option)}" ${currentValue === option ? 'selected' : ''}>${escapeClosedHtml(option)}</option>`))
            .join('');

        if (currentValue && !options.includes(currentValue)) {
            unitTypeInput.value = '';
        }
    }

    function syncClosedBookingTypeFields() {
        (closedKycState.schema?.sections || []).forEach((section) => {
            (section.fields || []).forEach((field) => {
                const wrapper = document.querySelector(`[data-closed-field-key="${field.field_key}"]`);
                if (!wrapper) {
                    return;
                }

                const visible = isClosedFieldVisibleByCondition(field);
                wrapper.hidden = !visible;
                wrapper.querySelectorAll('input, select, textarea').forEach((input) => {
                    input.disabled = !!closedKycState.readonly || !visible;
                });
            });
        });

        syncClosedBookingUnitTypeOptions();
    }

    function renderClosedKycField(field) {
        const fieldKey = field.field_key;
        const inputId = closedKycInputId(fieldKey);
        const value = getClosedFieldCurrentValue(field);
        const requiredMark = field.required ? ' *' : '';
        const helpText = field.help_text ? `<div class="closed-file-hint">${escapeClosedHtml(field.help_text)}</div>` : '';
        const wrapperAttrs = `data-closed-field-key="${escapeClosedHtml(fieldKey)}"`;

        if (fieldKey === 'kyc_documents') {
            return `
                <div class="closed-form-field full" ${wrapperAttrs}>
                    <label for="closedKycDocs">${escapeClosedHtml(field.label)}${requiredMark}</label>
                    <input id="closedKycDocs" class="closed-file" type="file" accept=".jpg,.jpeg,.png,.pdf" multiple>
                    ${helpText || '<div class="closed-file-hint">Purane docs retain ya remove kar sakte ho. Naye docs yahin add honge.</div>'}
                    <div id="closedSelectedKycDocs" class="closed-existing-grid"></div>
                </div>
            `;
        }

        if (fieldKey === 'proof_photos') {
            return `
                <div class="closed-form-field full" ${wrapperAttrs}>
                    <label for="closedProofPhotos">${escapeClosedHtml(field.label)}${requiredMark}</label>
                    <input id="closedProofPhotos" class="closed-file" type="file" accept=".jpg,.jpeg,.png,.webp" multiple>
                    ${helpText || '<div class="closed-file-hint">Proof photo remove karke new upload kar sakte ho. Kam se kam ek proof final submit me hona chahiye.</div>'}
                    <div id="closedSelectedProofPhotos" class="closed-existing-grid"></div>
                </div>
                <div class="closed-form-field full" data-closed-field-key="other_photos_upload">
                    <label for="closedOtherPhotos">Other Photos / Attachments</label>
                    <input id="closedOtherPhotos" class="closed-file" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" multiple>
                    <div class="closed-file-hint">Extra customer, site, payment, ya supporting photos/PDF yahan upload kar sakte ho.</div>
                    <div id="closedSelectedOtherPhotos" class="closed-existing-grid"></div>
                </div>
            `;
        }

        if (fieldKey === 'booking_payment_proofs') {
            return `
                <div class="closed-form-field full" ${wrapperAttrs}>
                    <label for="closedBookingPaymentProofs">${escapeClosedHtml(field.label)}${requiredMark}</label>
                    <input id="closedBookingPaymentProofs" class="closed-file" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" multiple>
                    ${helpText || '<div class="closed-file-hint">Booking receipt, payment screenshot ya PDF add kar sakte ho.</div>'}
                    <div id="closedSelectedBookingPaymentProofs" class="closed-existing-grid"></div>
                </div>
            `;
        }

        if (isClosedTitleField(fieldKey)) {
            const splitValue = splitClosedTitleValue(value);
            const titleOptions = [`<option value="">Title</option>`]
                .concat(CLOSED_KYC_TITLES.map((option) => `<option value="${escapeClosedHtml(option)}" ${splitValue.title === option ? 'selected' : ''}>${escapeClosedHtml(option)}</option>`))
                .join('');

            return `
                <div class="closed-form-field" ${wrapperAttrs}>
                    <label for="${inputId}__name">${escapeClosedHtml(field.label)}${requiredMark}</label>
                    <div class="closed-combo-input">
                        <select id="${inputId}__title" class="closed-select closed-title-select" onchange="syncClosedTitleValue('${escapeClosedHtml(fieldKey)}')">${titleOptions}</select>
                        <input id="${inputId}__name" class="closed-input" type="text" value="${escapeClosedHtml(splitValue.name || '')}" placeholder="Enter full name" oninput="syncClosedTitleValue('${escapeClosedHtml(fieldKey)}')">
                    </div>
                    <input id="${inputId}" type="hidden" value="${escapeClosedHtml(value || '')}">
                    ${helpText}
                </div>
            `;
        }

        if (field.field_type === 'textarea') {
            return `
                <div class="closed-form-field full" ${wrapperAttrs}>
                    <label for="${inputId}">${escapeClosedHtml(field.label)}${requiredMark}</label>
                    <textarea id="${inputId}" class="closed-input" rows="3" placeholder="${escapeClosedHtml(field.placeholder || '')}">${escapeClosedHtml(value || '')}</textarea>
                    ${helpText}
                </div>
            `;
        }

        if (field.field_type === 'select') {
            const options = Array.isArray(field.options) ? field.options : [];
            const optionHtml = [`<option value="">Select ${escapeClosedHtml(field.label)}</option>`]
                .concat(options.map((option) => `<option value="${escapeClosedHtml(option)}" ${String(value || '') === String(option) ? 'selected' : ''}>${escapeClosedHtml(option)}</option>`))
                .join('');

            return `
                <div class="closed-form-field" ${wrapperAttrs}>
                    <label for="${inputId}">${escapeClosedHtml(field.label)}${requiredMark}</label>
                    <select id="${inputId}" class="closed-select">${optionHtml}</select>
                    ${helpText}
                </div>
            `;
        }

        if (field.field_type === 'radio') {
            const options = Array.isArray(field.options) ? field.options : [];
            return `
                <div class="closed-form-field full" ${wrapperAttrs}>
                    <label>${escapeClosedHtml(field.label)}${requiredMark}</label>
                    <div class="closed-check-grid">
                        ${options.map((option) => `
                            <label class="closed-check-item">
                                <input type="radio" name="${escapeClosedHtml(fieldKey)}" value="${escapeClosedHtml(option)}" ${String(value || '') === String(option) ? 'checked' : ''}>
                                <span>${escapeClosedHtml(option)}</span>
                            </label>
                        `).join('')}
                    </div>
                    ${helpText}
                </div>
            `;
        }

        if (field.field_type === 'checkbox' && Array.isArray(field.options) && field.options.length > 0) {
            const selectedValues = Array.isArray(value) ? value.map(String) : [];
            return `
                <div class="closed-form-field full" ${wrapperAttrs}>
                    <label>${escapeClosedHtml(field.label)}${requiredMark}</label>
                    <div class="closed-check-grid">
                        ${field.options.map((option) => `
                            <label class="closed-check-item">
                                <input type="checkbox" data-array-checkbox="true" data-field-key="${escapeClosedHtml(fieldKey)}" value="${escapeClosedHtml(option)}" ${selectedValues.includes(String(option)) ? 'checked' : ''}>
                                <span>${escapeClosedHtml(option)}</span>
                            </label>
                        `).join('')}
                    </div>
                    ${helpText}
                </div>
            `;
        }

        if (field.field_type === 'checkbox') {
            const exclusiveGroup = closedExclusiveGroupForField(fieldKey);
            const exclusiveAttr = exclusiveGroup ? ` data-exclusive-group="${escapeClosedHtml(exclusiveGroup)}"` : '';
            return `
                <div class="closed-form-field" ${wrapperAttrs}>
                    <label class="closed-check-item" style="margin-top:28px;">
                        <input id="${inputId}" type="checkbox"${exclusiveAttr} ${value ? 'checked' : ''}>
                        <span>${escapeClosedHtml(field.label)}${requiredMark}</span>
                    </label>
                    ${helpText}
                </div>
            `;
        }

        if (field.field_type === 'file') {
            return `
                <div class="closed-form-field full" ${wrapperAttrs}>
                    <label for="${inputId}">${escapeClosedHtml(field.label)}${requiredMark}</label>
                    <input id="${inputId}" class="closed-file" type="file">
                    ${helpText}
                </div>
            `;
        }

        const type = ['date', 'email', 'number'].includes(field.field_type) ? field.field_type : 'text';
        const dateBounds = fieldKey.endsWith('date_of_birth') ? ` min="1900-01-01" max="{{ now()->toDateString() }}"` : '';
        return `
            <div class="closed-form-field" ${wrapperAttrs}>
                <label for="${inputId}">${escapeClosedHtml(field.label)}${requiredMark}</label>
                <input id="${inputId}" class="closed-input" type="${type}" value="${escapeClosedHtml(value || '')}" placeholder="${escapeClosedHtml(field.placeholder || '')}"${dateBounds}>
                ${helpText}
            </div>
        `;
    }

    function closedExclusiveGroupForField(fieldKey) {
        if (/^primary_applicant_occupation_/.test(fieldKey)) return 'primaryApplicantOccupation';
        if (/^primary_applicant_resident_/.test(fieldKey)) return 'primaryApplicantResident';
        if (/^primary_applicant_marital_status_/.test(fieldKey)) return 'primaryApplicantMarital';
        if (/^joint_applicant_occupation_/.test(fieldKey)) return 'jointApplicantOccupation';
        if (/^joint_applicant_resident_/.test(fieldKey)) return 'jointApplicantResident';
        if (/^joint_applicant_marital_status_/.test(fieldKey)) return 'jointApplicantMarital';
        return '';
    }

    function renderClosedStructuredApplicantSection(section, remarks) {
        const prefix = getClosedApplicantPrefix(section.label);
        const byKey = new Map((section.fields || []).map((field) => [field.field_key, field]));
        const render = (key) => byKey.has(key) ? renderClosedKycField(byKey.get(key)) : '';
        const renderGroup = (title, keys, extra = '') => {
            const html = keys.map((key) => render(key)).filter(Boolean).join('');
            if (!html && !extra) {
                return '';
            }
            return `
                <div class="closed-kyc-group-block">
                    <div class="closed-kyc-group-title">${escapeClosedHtml(title)}</div>
                    <div class="closed-check-grid inline-chips">${html}</div>
                    ${extra}
                </div>
            `;
        };

        return `
            <div class="closed-kyc-section full">
                <div class="closed-kyc-section-title">${escapeClosedHtml(section.label || 'Section')}</div>
                ${remarks[section.label] ? `<div class="closed-modal-note is-danger" style="margin-bottom:14px;">${escapeClosedHtml(remarks[section.label])}</div>` : ''}
                <div class="closed-kyc-inline-grid form-structured">
                    <div class="closed-kyc-field-row">
                        ${render(`${prefix}name`)}
                        ${render(`${prefix}relation_name`)}
                    </div>
                    <div class="closed-kyc-field-row">
                        ${render(`${prefix}date_of_birth`)}
                        ${render(`${prefix}nationality`)}
                    </div>
                    ${renderGroup('Occupation', [
                        `${prefix}occupation_service`,
                        `${prefix}occupation_professional`,
                        `${prefix}occupation_housewife`,
                        `${prefix}occupation_business`,
                    ])}
                    ${renderGroup('Residential Status', [
                        `${prefix}resident_indian`,
                        `${prefix}resident_non_resident`,
                        `${prefix}resident_foreign_national`,
                    ])}
                    ${renderGroup('Marital Status', [
                        `${prefix}marital_status_married`,
                        `${prefix}marital_status_unmarried`,
                    ])}
                    <div class="closed-kyc-field-row">
                        ${render(`${prefix}pan_no`)}
                        ${prefix === 'primary_applicant_' ? render(`${prefix}aadhaar_no`) : '<div></div>'}
                    </div>
                    ${prefix !== 'primary_applicant_' ? render(`${prefix}aadhaar_no`) : ''}
                    ${render(`${prefix}address`)}
                    ${render(`${prefix}communication_address`)}
                    <div class="closed-kyc-field-row cols-4">
                        ${render(`${prefix}city`)}
                        ${render(`${prefix}state`)}
                        ${render(`${prefix}pin`)}
                        ${render(`${prefix}email`)}
                    </div>
                    <div class="closed-kyc-field-row">
                        ${render(`${prefix}mobile_no`)}
                        ${render(`${prefix}tel_no`)}
                    </div>
                </div>
            </div>
        `;
    }

    function renderClosedKycForm(visit) {
        const schema = visit?.kyc_schema || CLOSED_DEFAULT_KYC_SCHEMA || { sections: [] };
        closedKycState.schema = schema;
        const remarks = getClosedSchemaSectionRemarks(schema);
        const steps = getClosedKycSteps(schema);
        const form = document.getElementById('closedKycForm');
        const stepper = document.getElementById('closedKycStepper');
        if (!form) {
            return;
        }

        if (stepper) {
            stepper.hidden = steps.length === 0;
            stepper.innerHTML = steps.map((step, index) => `
                <button type="button" class="closed-kyc-step-pill ${index === closedKycState.currentStep ? 'is-active' : ''}" data-closed-kyc-step-pill="${index}" onclick="setClosedKycStep(${index})">
                    <span class="closed-kyc-step-pill-index">Step ${index + 1}</span>
                    <span class="closed-kyc-step-pill-label">${escapeClosedHtml(step.label || `Step ${index + 1}`)}</span>
                </button>
            `).join('');
        }

        form.innerHTML = steps.map((step, stepIndex) => `
            <div class="closed-kyc-step-panel full" data-closed-kyc-step="${stepIndex}" ${stepIndex === closedKycState.currentStep ? '' : 'hidden'}>
                ${step.sections.map((section) => (
                    isClosedApplicantSection(section.label)
                        ? renderClosedStructuredApplicantSection(section, remarks)
                        : `
                            <div class="closed-kyc-section full">
                                <div class="closed-kyc-section-title">${escapeClosedHtml(section.label || 'Section')}</div>
                                ${remarks[section.label] ? `<div class="closed-modal-note is-danger" style="margin-bottom:14px;">${escapeClosedHtml(remarks[section.label])}</div>` : ''}
                                <div class="closed-kyc-inline-grid">
                                    ${(section.fields || []).map((field) => renderClosedKycField(field)).join('')}
                                </div>
                            </div>
                        `
                )).join('')}
            </div>
        `).join('');

        document.getElementById('closedKycDocs')?.addEventListener('change', () => {
            renderClosedSelectedFiles('closedKycDocs', 'closedSelectedKycDocs', false);
        });
        document.getElementById('closedProofPhotos')?.addEventListener('change', () => {
            renderClosedSelectedFiles('closedProofPhotos', 'closedSelectedProofPhotos', true);
        });
        document.getElementById('closedOtherPhotos')?.addEventListener('change', () => {
            renderClosedSelectedFiles('closedOtherPhotos', 'closedSelectedOtherPhotos', false);
        });
        document.getElementById('closedBookingPaymentProofs')?.addEventListener('change', () => {
            renderClosedSelectedFiles('closedBookingPaymentProofs', 'closedSelectedBookingPaymentProofs', false);
        });
        attachClosedBookingCalculation();
        document.getElementById(closedKycInputId('booking_type'))?.addEventListener('change', syncClosedBookingTypeFields);
        document.getElementById(closedKycInputId('booking_joint_applicant_available'))?.addEventListener('change', () => {
            syncClosedKycWizardUi();
        });
        syncClosedBookingTypeFields();

        syncClosedKycWizardUi();
    }

    function attachClosedBookingCalculation() {
        const amountKeys = [
            'unit_details_basic_sale_price',
            'unit_details_plc_amount',
            'unit_details_other_charges',
            'unit_details_discount',
        ];
        const finalInput = document.getElementById(closedKycInputId('unit_details_final_total'));
        const overrideInput = document.getElementById(closedKycInputId('unit_details_override_total'));
        if (!finalInput) {
            return;
        }

        const parseAmount = (value) => Number(String(value || '').replace(/[^0-9.-]/g, '')) || 0;
        const updateFinalTotal = () => {
            if (overrideInput?.checked) {
                return;
            }

            const bsp = parseAmount(document.getElementById(closedKycInputId('unit_details_basic_sale_price'))?.value);
            const plc = parseAmount(document.getElementById(closedKycInputId('unit_details_plc_amount'))?.value);
            const other = parseAmount(document.getElementById(closedKycInputId('unit_details_other_charges'))?.value);
            const discount = parseAmount(document.getElementById(closedKycInputId('unit_details_discount'))?.value);
            const total = Math.max(0, bsp + plc + other - discount);
            finalInput.value = total ? total.toFixed(2) : '';
        };

        amountKeys.forEach((key) => {
            document.getElementById(closedKycInputId(key))?.addEventListener('input', updateFinalTotal);
        });
        overrideInput?.addEventListener('change', updateFinalTotal);
        updateFinalTotal();
    }

    function closedFieldInputValue(field) {
        const inputId = closedKycInputId(field.field_key);

        if (field.field_key === 'kyc_documents' || field.field_key === 'proof_photos' || field.field_key === 'booking_payment_proofs') {
            return null;
        }

        if (field.field_key.startsWith('joint_applicant_') && !isClosedJointApplicantAvailable()) {
            return field.field_type === 'checkbox' ? '' : '';
        }

        if (field.field_type === 'checkbox' && Array.isArray(field.options) && field.options.length > 0) {
            return Array.from(document.querySelectorAll(`input[data-array-checkbox="true"][data-field-key="${field.field_key}"]:checked`)).map((checkbox) => checkbox.value);
        }

        if (field.field_type === 'checkbox') {
            return document.getElementById(inputId)?.checked ? '1' : '';
        }

        if (field.field_type === 'radio') {
            return document.querySelector(`input[name="${field.field_key}"]:checked`)?.value || '';
        }

        if (field.field_type === 'file') {
            return document.getElementById(inputId)?.files || null;
        }

        if (isClosedTitleField(field.field_key)) {
            const composite = getClosedTitleCompositeValue(inputId);
            const hidden = document.getElementById(inputId);
            if (hidden) {
                hidden.value = composite;
            }
            return composite || hidden?.value || '';
        }

        return document.getElementById(inputId)?.value || '';
    }

    function validateClosedKycSchema(mode) {
        if (mode !== 'submit' || !closedKycState.schema) {
            return true;
        }

        const requireAnyChecked = (fieldKeys, label) => {
            const checked = fieldKeys.some((key) => document.getElementById(closedKycInputId(key))?.checked);
            if (!checked) {
                closedNotify(`${label} is required before submission.`, 'error');
                return false;
            }

            return true;
        };

        if (!requireAnyChecked([
            'primary_applicant_occupation_service',
            'primary_applicant_occupation_professional',
            'primary_applicant_occupation_housewife',
            'primary_applicant_occupation_business',
        ], 'Occupation')) {
            return false;
        }

        if (!requireAnyChecked([
            'primary_applicant_resident_indian',
            'primary_applicant_resident_non_resident',
            'primary_applicant_resident_foreign_national',
        ], 'Residential Status')) {
            return false;
        }

        for (const section of closedKycState.schema.sections || []) {
            for (const field of section.fields || []) {
                if (!isClosedFieldVisibleByCondition(field)) {
                    continue;
                }

                if (!field.required) {
                    continue;
                }

                if (isClosedTitleField(field.field_key)) {
                    const inputId = closedKycInputId(field.field_key);
                    const nameValue = (document.getElementById(`${inputId}__name`)?.value || '').trim();
                    const titleValue = document.getElementById(`${inputId}__title`)?.value || '';
                    const combined = nameValue ? `${titleValue ? `${titleValue} ` : ''}${nameValue}`.trim() : '';
                    const hidden = document.getElementById(inputId);
                    if (hidden) {
                        hidden.value = combined;
                    }
                    if (!combined) {
                        closedNotify(`${field.label} is required before submission.`, 'error');
                        return false;
                    }
                    continue;
                }

                if (field.field_key === 'kyc_documents') {
                    const files = document.getElementById('closedKycDocs')?.files || [];
                    if (!closedKycState.existingKycDocuments.length && !files.length) {
                        closedNotify(`${field.label} is required before submission.`, 'error');
                        return false;
                    }
                    continue;
                }

                if (field.field_key === 'proof_photos') {
                    const files = document.getElementById('closedProofPhotos')?.files || [];
                    if (!closedKycState.existingProofPhotos.length && !files.length) {
                        closedNotify(`${field.label} is required before submission.`, 'error');
                        return false;
                    }
                    continue;
                }

                if (field.field_key === 'booking_payment_proofs') {
                    const files = document.getElementById('closedBookingPaymentProofs')?.files || [];
                    if (field.required && !closedKycState.existingBookingPaymentProofs.length && !files.length) {
                        closedNotify(`${field.label} is required before submission.`, 'error');
                        return false;
                    }
                    continue;
                }

                const value = closedFieldInputValue(field);
                if (Array.isArray(value) && value.length === 0) {
                    closedNotify(`${field.label} is required before submission.`, 'error');
                    return false;
                }

                if (value instanceof FileList && value.length === 0) {
                    closedNotify(`${field.label} is required before submission.`, 'error');
                    return false;
                }

                if (!Array.isArray(value) && !(value instanceof FileList) && String(value || '').trim() === '') {
                    closedNotify(`${field.label} is required before submission.`, 'error');
                    return false;
                }
            }
        }

        const v2Value = (key) => String(document.getElementById(closedKycInputId(key))?.value || '').trim();
        const requiredV2 = [
            ['booking_project_name', 'Project Name'],
            ['booking_date', 'Booking Date'],
            ['booking_deal_type', 'Deal Type'],
            ['booking_type', 'Booking Type'],
        ];

        for (const [key, label] of requiredV2) {
            if (!v2Value(key)) {
                closedNotify(`${label} is required before submission.`, 'error');
                return false;
            }
        }

        const bookingType = v2Value('booking_type');
        const bookingConfig = getClosedBookingTypeConfig()[bookingType];
        for (const key of bookingConfig?.requiredKeys || []) {
            const field = getClosedKycFieldSchema(key);
            const label = field?.label || key;
            if (!v2Value(key)) {
                closedNotify(`${label} is required before submission.`, 'error');
                return false;
            }
        }

        if (v2Value('unit_details_discount') && !v2Value('unit_details_discount_remark')) {
            closedNotify('Discount Remark is required when discount is filled.', 'error');
            return false;
        }

        if (document.getElementById(closedKycInputId('unit_details_override_total'))?.checked && !v2Value('unit_details_override_reason')) {
            closedNotify('Override Reason is required when final total is manually overridden.', 'error');
            return false;
        }

        return true;
    }

    function openClosedKycModal(siteVisitId, readonly = false) {
        const visit = getClosedVisitById(siteVisitId);
        if (!visit) {
            closedNotify('Site visit data not found. Please refresh and try again.', 'error');
            return;
        }

        activeClosedVisitId = siteVisitId;
        closedKycState.currentStep = 0;
        closedKycState.readonly = !!readonly || ['approved', 'verified'].includes(visit.closer_status || '');
        closedKycState.existingKycDocuments = Array.isArray(visit.kyc_documents) ? [...visit.kyc_documents] : [];
        closedKycState.existingProofPhotos = Array.isArray(visit.closer_request_proof_photos) ? [...visit.closer_request_proof_photos] : [];
        closedKycState.existingBookingPaymentProofs = Array.isArray(visit.booking_payment_proofs) ? [...visit.booking_payment_proofs] : [];
        renderClosedExistingFiles();
        renderClosedKycForm(visit);

        const noteElement = document.getElementById('closedKycModalNote');
        const existingFilesElement = document.getElementById('closedKycExistingFiles');
        const submitButton = document.getElementById('closedKycSubmitButton');
        const titleElement = document.getElementById('closedKycModalTitle');
        const subtitleElement = document.getElementById('closedKycModalSubtitle');
        const existingBits = [];

        if (closedKycState.existingKycDocuments.length > 0) existingBits.push(`${closedKycState.existingKycDocuments.length} existing KYC docs`);
        if (closedKycState.existingProofPhotos.length > 0) existingBits.push(`${closedKycState.existingProofPhotos.length} existing proof photos`);
        if (closedKycState.existingBookingPaymentProofs.length > 0) existingBits.push(`${closedKycState.existingBookingPaymentProofs.length} booking payment proofs`);

        existingFilesElement.hidden = existingBits.length === 0;
        existingFilesElement.textContent = existingBits.length
            ? `${existingBits.join(' • ')}. Retain, remove, ya new upload mix karke resubmit kar sakte ho.`
            : '';

        const isPendingEdit = visit.closer_status === 'pending_crm';
        const isResubmission = ['correction_required', 'rejected'].includes(visit.closer_status);
        noteElement.classList.toggle('is-danger', isResubmission);

        if (closedKycState.readonly) {
            noteElement.classList.remove('is-danger');
            titleElement.textContent = 'View KYC Form';
            subtitleElement.textContent = 'CRM verified KYC locked hai. ASM/Sr. Manager ab isko edit nahi kar sakte.';
            noteElement.textContent = 'Ye KYC read-only mode me open hai.';
            submitButton.textContent = 'Submit KYC';
        } else if (isResubmission) {
            titleElement.textContent = 'Resubmit Closer Form';
            subtitleElement.textContent = 'Previous submission prefilled hai. Required changes karke dubara CRM review me bhejo.';
            noteElement.textContent = visit.closer_review_remark
                ? `CRM correction remark: ${visit.closer_review_remark}`
                : (visit.closer_rejection_reason || visit.closing_rejection_reason
                    ? `Closer reject remark: ${visit.closer_rejection_reason || visit.closing_rejection_reason}`
                    : 'CRM ne resubmission request bheji hai. Same KYC update karke resubmit karo.');
            submitButton.textContent = 'Resubmit To CRM';
        } else if (isPendingEdit) {
            titleElement.textContent = 'Edit KYC Form';
            subtitleElement.textContent = 'KYC CRM verification pending hai. Changes save/submit kar sakte ho jab tak CRM verify nahi karta.';
            noteElement.textContent = 'Update karne ke baad Submit KYC dabao. Verified hone ke baad form locked ho jayega.';
            submitButton.textContent = 'Update KYC';
        } else {
            titleElement.textContent = 'Fill KYC Form';
            subtitleElement.textContent = 'Close verification ke baad full KYC yahin se submit karo.';
            noteElement.textContent = 'Customer, nominee, identity details, KYC documents, aur proof photos required hain. Submit hone ke baad incentive form unlock hoga.';
            submitButton.textContent = 'Submit KYC';
        }

        applyClosedKycReadonlyState();
        syncClosedKycWizardUi();
        const modal = document.getElementById('closedKycModal');
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        modal.classList.add('show');
        toggleClosedModalState(true);
    }

    function closeClosedKycModal() {
        const modal = document.getElementById('closedKycModal');
        modal.classList.remove('show');
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        activeClosedVisitId = null;
        closedKycState.existingKycDocuments = [];
        closedKycState.existingProofPhotos = [];
        closedKycState.existingBookingPaymentProofs = [];
        closedKycState.schema = null;
        closedKycState.currentStep = 0;
        closedKycState.readonly = false;
        renderClosedExistingFiles();
        toggleClosedModalState(false);
    }

    async function submitClosedKyc(mode = 'draft') {
        if (closedKycState.readonly) return;
        const visit = getClosedVisitById(activeClosedVisitId);
        if (!visit || !closedKycState.schema) {
            closedNotify('Site visit data not found. Please refresh and try again.', 'error');
            return;
        }

        (closedKycState.schema.sections || []).forEach((section) => {
            (section.fields || []).forEach((field) => {
                if (isClosedTitleField(field.field_key)) {
                    syncClosedTitleValue(field.field_key);
                }
            });
        });

        if (!validateClosedKycSchema(mode)) {
            return;
        }

        const formData = new FormData();
        if (closedKycState.schema.form_id) {
            formData.append('kyc_form_id', closedKycState.schema.form_id);
        }

        (closedKycState.schema.sections || []).forEach((section) => {
            (section.fields || []).forEach((field) => {
                if (!isClosedFieldVisibleByCondition(field)) {
                    return;
                }

                const value = closedFieldInputValue(field);

                if (field.field_key === 'kyc_documents' || field.field_key === 'proof_photos' || field.field_key === 'booking_payment_proofs') {
                    return;
                }

                if (value instanceof FileList) {
                    if (value.length > 0) {
                        formData.append(field.field_key, value[0]);
                    }
                    return;
                }

                if (Array.isArray(value)) {
                    value.forEach((item) => formData.append(`${field.field_key}[]`, item));
                    return;
                }

                if (field.field_type === 'checkbox') {
                    formData.append(field.field_key, value ? '1' : '0');
                    return;
                }

                formData.append(field.field_key, value ?? '');
            });
        });

        closedKycState.existingKycDocuments.forEach((path) => formData.append('existing_kyc_documents[]', path));
        closedKycState.existingProofPhotos.forEach((path) => formData.append('existing_proof_photos[]', path));
        closedKycState.existingBookingPaymentProofs.forEach((path) => formData.append('existing_booking_payment_proofs[]', path));

        const kycDocs = document.getElementById('closedKycDocs')?.files || [];
        const proofPhotos = document.getElementById('closedProofPhotos')?.files || [];
        const otherPhotos = document.getElementById('closedOtherPhotos')?.files || [];
        const paymentProofs = document.getElementById('closedBookingPaymentProofs')?.files || [];
        for (let i = 0; i < kycDocs.length; i += 1) formData.append('kyc_documents[]', kycDocs[i]);
        for (let i = 0; i < proofPhotos.length; i += 1) formData.append('proof_photos[]', proofPhotos[i]);
        for (let i = 0; i < otherPhotos.length; i += 1) formData.append('proof_photos[]', otherPhotos[i]);
        for (let i = 0; i < paymentProofs.length; i += 1) formData.append('booking_payment_proofs[]', paymentProofs[i]);

        let endpoint = `/site-visits/${activeClosedVisitId}/kyc/draft`;
        let defaultMessage = 'KYC draft saved successfully.';
        if (mode === 'submit') {
            const isResubmission = ['correction_required', 'rejected'].includes(visit.closer_status);
            endpoint = isResubmission
                ? `/site-visits/${activeClosedVisitId}/closer/resubmit`
                : `/site-visits/${activeClosedVisitId}/kyc/submit`;
            defaultMessage = isResubmission ? 'KYC resubmitted successfully.' : 'KYC submitted successfully.';
        }

        try {
            const response = await fetch(`${CLOSED_API_BASE_URL}${endpoint}`, {
                method: 'POST',
                headers: getClosedAuthHeaders(),
                body: formData,
            });
            const result = await response.json();
            if (!response.ok || !result.success) {
                const errorMessage = result.errors ? Object.values(result.errors).flat().join(' ') : (result.message || 'Request failed');
                throw new Error(errorMessage);
            }

            closeClosedKycModal();
            closedNotify(result.message || defaultMessage, 'success');
            if (mode === 'submit') {
                setClosedActiveBucket('pending_crm');
            } else if (['correction_required', 'rejected'].includes(visit.closer_status)) {
                setClosedActiveBucket('pending_crm');
            }
            loadClosedLeads(closedLeadPage);
        } catch (error) {
            closedNotify(error.message || `Failed to ${mode === 'submit' ? 'submit' : 'save'} KYC`, 'error');
        }
    }

    const reloadClosedLeads = debounce(() => loadClosedLeads(1), 280);

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.closed-pipeline-tab').forEach((tab) => {
            tab.addEventListener('click', () => {
                if (tab.dataset.bucket === closedActiveBucket) {
                    return;
                }

                setClosedActiveBucket(tab.dataset.bucket || 'visited_clients');
                loadClosedLeads(1);
            });
        });
        document.getElementById('closedSearchInput').addEventListener('input', reloadClosedLeads);
        document.getElementById('closedSourceFilter').addEventListener('change', () => {
            closedVisitData = applyClosedClientFilters(closedRawVisits);
            renderClosedLeads(closedVisitData);
            updateClosedStats(closedVisitMeta, closedVisitData);
        });
        document.getElementById('closedSortFilter').addEventListener('change', () => {
            closedVisitData = applyClosedClientFilters(closedRawVisits);
            renderClosedLeads(closedVisitData);
            updateClosedStats(closedVisitMeta, closedVisitData);
        });
        document.addEventListener('change', (event) => {
            const checkbox = event.target;
            if (!(checkbox instanceof HTMLInputElement) || checkbox.type !== 'checkbox' || !checkbox.dataset.exclusiveGroup || !checkbox.checked) {
                return;
            }

            const group = checkbox.dataset.exclusiveGroup;
            document.querySelectorAll(`input[type="checkbox"][data-exclusive-group="${group}"]`).forEach((peer) => {
                if (peer !== checkbox) {
                    peer.checked = false;
                }
            });
        });
        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') {
                return;
            }

            if (document.getElementById('closedKycModal').classList.contains('show')) {
                closeClosedKycModal();
            }

            if (document.getElementById('closedIncentiveModal').classList.contains('show')) {
                closeClosedIncentiveModal();
            }
        });
        setClosedActiveBucket(closedActiveBucket);
        loadClosedLeads(1);
    });
</script>
@endpush

@extends('finance-manager.layout')

@section('title', 'Booked Customers')
@section('page_title', 'Booked Customers')
@section('page_subtitle', 'Booked customers whose closer incentive is verified, with quick access to lead details for finance review.')

@push('styles')
<style>
    .bc-grid { display: grid; gap: 22px; }
    .bc-card {
        background: #fff;
        border: 1px solid #ddd7ca;
        border-radius: 24px;
        padding: 24px;
        box-shadow: 0 10px 24px rgba(10, 25, 16, 0.05);
    }
    .bc-head {
        display: flex;
        justify-content: space-between;
        gap: 16px;
        align-items: flex-start;
        flex-wrap: wrap;
        margin-bottom: 18px;
    }
    .bc-head h2, .bc-head h3 {
        margin: 0;
        font-size: 28px;
        line-height: 1.05;
        font-weight: 800;
        letter-spacing: -0.04em;
        color: #0b2e20;
    }
    .bc-head p {
        margin: 8px 0 0;
        color: #66756f;
        font-size: 14px;
    }
    .bc-pill {
        padding: 8px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 800;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #fff7ed;
        color: #9a3412;
    }
    .bc-stats {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 16px;
    }
    .bc-filters {
        display: grid;
        grid-template-columns: 140px 140px minmax(220px, 1fr) auto auto;
        gap: 12px;
        align-items: end;
        margin-top: 18px;
    }
    .bc-field label {
        display: block;
        margin-bottom: 6px;
        color: #65746d;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
    }
    .bc-input {
        width: 100%;
        border: 1px solid #d8d1c5;
        border-radius: 12px;
        padding: 10px 12px;
        font-size: 14px;
        color: #153528;
        background: #fff;
    }
    .bc-stat {
        background: #f7f5ee;
        border: 1px solid #e7e0d4;
        border-radius: 20px;
        padding: 18px;
    }
    .bc-stat strong {
        display: block;
        font-size: 30px;
        line-height: 1;
        color: #0b2e20;
    }
    .bc-stat span {
        display: block;
        margin-top: 8px;
        color: #65746d;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }
    .bc-list { display: grid; gap: 14px; }
    .bc-item {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 16px;
        padding: 18px;
        border-radius: 20px;
        background: #fff;
        border: 1px solid #ebe5da;
    }
    .bc-item strong {
        display: block;
        font-size: 16px;
        color: #0b2e20;
    }
    .bc-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 10px 14px;
        margin-top: 8px;
        color: #6d7b75;
        font-size: 12px;
    }
    .bc-side {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 8px;
        text-align: right;
    }
    .bc-tag {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 12px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 800;
    }
    .bc-tag.ready { background: #ecfdf5; color: #166534; }
    .bc-tag.missing { background: #fff7ed; color: #9a3412; }
    .bc-tag.pending { background: #eff6ff; color: #1d4ed8; }
    .bc-request-summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        margin-bottom: 16px;
    }
    .bc-request-summary span {
        display: block;
        color: #66756f;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }
    .bc-request-summary strong {
        display: block;
        margin-top: 6px;
        color: #0b2e20;
        font-size: 22px;
        line-height: 1;
    }
    .bc-request-note {
        color: #6d7b75;
        font-size: 12px;
        line-height: 1.45;
    }
    .bc-amount { font-size: 13px; font-weight: 800; color: #0f5b42; }
    .bc-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }
    .bc-revenue-form {
        display: grid;
        grid-template-columns: 120px 130px 86px;
        gap: 8px;
        width: 352px;
    }
    .bc-revenue-form .bc-input {
        height: 38px;
        padding: 8px 10px;
        font-size: 12px;
    }
    .bc-btn {
        border: none;
        border-radius: 12px;
        padding: 10px 14px;
        font-size: 12px;
        font-weight: 800;
        cursor: pointer;
        transition: 0.2s ease;
    }
    .bc-btn.approve {
        background: linear-gradient(135deg, #166534, #16a34a);
        color: #fff;
    }
    .bc-btn.secondary {
        background: #eef6f1;
        color: #0f5b42;
        border: 1px solid #cfe3d8;
    }
    .bc-btn:disabled {
        opacity: 0.7;
        cursor: wait;
    }
    .bc-empty {
        padding: 44px 20px;
        text-align: center;
        color: #6b7771;
        border: 1px dashed #ddd7ca;
        border-radius: 22px;
        background: #fcfbf8;
    }
    .bc-modal-shell {
        position: fixed;
        inset: 0;
        background: rgba(4, 18, 12, 0.58);
        backdrop-filter: blur(4px);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        z-index: 1050;
    }
    .bc-modal-shell[hidden] { display: none; }
    .bc-modal {
        width: min(980px, 100%);
        max-height: 88vh;
        overflow: auto;
        background: #fff;
        border-radius: 24px;
        border: 1px solid #ddd7ca;
        box-shadow: 0 22px 60px rgba(10, 25, 16, 0.18);
    }
    .bc-review-modal {
        width: min(900px, 100%);
        overflow: hidden;
        box-shadow: 0 28px 80px rgba(4, 24, 15, 0.28);
    }
    .bc-modal-head,
    .bc-modal-foot {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        padding: 20px 24px;
        border-bottom: 1px solid #ece6da;
    }
    .bc-review-modal .bc-modal-head {
        padding: 18px 28px;
        background: linear-gradient(135deg, #f8fcfa 0%, #fff 64%);
    }
    .bc-modal-foot {
        border-bottom: none;
        border-top: 1px solid #ece6da;
        justify-content: flex-end;
    }
    .bc-review-modal .bc-modal-foot {
        padding: 16px 28px;
        background: #fafbf9;
    }
    .bc-modal-title {
        margin: 0;
        font-size: 22px;
        font-weight: 800;
        color: #0b2e20;
    }
    .bc-modal-subtitle {
        margin: 6px 0 0;
        font-size: 13px;
        color: #6c7a73;
    }
    .bc-modal-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        margin-bottom: 7px;
        color: #16724f;
        font-size: 10px;
        font-weight: 800;
        letter-spacing: .1em;
        text-transform: uppercase;
    }
    .bc-modal-close {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        border: 1px solid #ddd7ca;
        background: #fff;
        color: #0b2e20;
        cursor: pointer;
        transition: background-color .18s ease, border-color .18s ease, color .18s ease;
    }
    .bc-modal-close:hover { background: #eef6f1; border-color: #b9d3c7; color: #0f6849; }
    .bc-modal-close:focus-visible,
    .bc-btn:focus-visible,
    .bc-input:focus-visible {
        outline: 3px solid rgba(22, 163, 74, .2);
        outline-offset: 2px;
        border-color: #168052;
    }
    .bc-modal-body {
        padding: 24px;
        display: grid;
        gap: 18px;
    }
    .bc-review-modal .bc-modal-body {
        max-height: calc(88vh - 154px);
        overflow-y: auto;
        padding: 24px 28px 28px;
        gap: 16px;
    }
    .bc-review-summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        overflow: hidden;
        border: 1px solid #dbe7e0;
        border-radius: 14px;
        background: #f7faf8;
    }
    .bc-review-summary-item {
        min-width: 0;
        padding: 13px 15px;
        border-right: 1px solid #dbe7e0;
    }
    .bc-review-summary-item:last-child { border-right: 0; }
    .bc-review-summary-item span {
        display: block;
        margin-bottom: 4px;
        color: #6b7972;
        font-size: 9px;
        font-weight: 800;
        letter-spacing: .08em;
        text-transform: uppercase;
    }
    .bc-review-summary-item strong {
        display: block;
        overflow: hidden;
        color: #153528;
        font-size: 13px;
        line-height: 1.4;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .bc-kyc-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }
    .bc-kyc-card {
        background: #f8f6ef;
        border: 1px solid #e7e0d4;
        border-radius: 18px;
        padding: 16px;
    }
    .bc-kyc-card span {
        display: block;
        font-size: 11px;
        color: #6d7b75;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        margin-bottom: 8px;
    }
    .bc-kyc-card strong {
        display: block;
        font-size: 16px;
        color: #0b2e20;
        word-break: break-word;
    }
    .bc-doc-section {
        border: 1px solid #e7e0d4;
        border-radius: 20px;
        padding: 18px;
        background: #fcfbf8;
    }
    .bc-doc-section h4 {
        margin: 0 0 12px;
        font-size: 16px;
        color: #0b2e20;
    }
    .bc-doc-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 12px;
    }
    .bc-doc-card {
        display: grid;
        overflow: hidden;
        border-radius: 16px;
        border: 1px solid #ddd7ca;
        background: #fff;
        min-height: 250px;
    }
    .bc-doc-preview {
        position: relative;
        height: 170px;
        background: #f7f5ee;
        border-bottom: 1px solid #ece6da;
        overflow: hidden;
    }
    .bc-doc-preview img,
    .bc-doc-preview iframe {
        width: 100%;
        height: 100%;
        border: 0;
        display: block;
        object-fit: cover;
        background: #fff;
    }
    .bc-doc-fallback {
        height: 100%;
        display: grid;
        place-items: center;
        gap: 8px;
        color: #0f5b42;
        text-align: center;
        padding: 18px;
    }
    .bc-doc-fallback i {
        font-size: 34px;
        color: #166534;
    }
    .bc-doc-meta {
        display: grid;
        gap: 10px;
        padding: 12px;
    }
    .bc-doc-name {
        font-size: 13px;
        font-weight: 700;
        color: #0b2e20;
        word-break: break-word;
        line-height: 1.45;
    }
    .bc-doc-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 12px;
        border-radius: 12px;
        border: 1px solid #ddd7ca;
        background: #fff;
        color: #0f5b42;
        font-weight: 700;
        text-decoration: none;
        word-break: break-word;
        width: fit-content;
    }
    .bc-doc-empty {
        color: #6b7771;
        font-size: 13px;
    }
    .bc-structured-section {
        border: 1px solid #e7e0d4;
        border-radius: 20px;
        padding: 18px;
        background: #fcfbf8;
        display: grid;
        gap: 14px;
    }
    .bc-review-section {
        position: relative;
        gap: 15px;
        padding: 18px;
        border-radius: 16px;
        background: #fff;
    }
    .bc-review-section.approve { border-color: #cfe3d8; box-shadow: inset 3px 0 0 #1c9a63; }
    .bc-review-section.reject { border-color: #ead9d6; box-shadow: inset 3px 0 0 #d05a4a; }
    .bc-review-section-head {
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }
    .bc-review-section-icon {
        width: 36px;
        height: 36px;
        flex: 0 0 36px;
        display: grid;
        place-items: center;
        border-radius: 10px;
        background: #eaf7ef;
        color: #16724f;
    }
    .bc-review-section.reject .bc-review-section-icon { background: #fff1f0; color: #b42318; }
    .bc-review-section-copy p {
        margin: 4px 0 0;
        color: #6c7a73;
        font-size: 12px;
        line-height: 1.45;
    }
    .bc-structured-title {
        margin: 0;
        font-size: 16px;
        font-weight: 800;
        color: #0b2e20;
        letter-spacing: -0.02em;
    }
    .bc-structured-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }
    .bc-review-approve-grid {
        display: grid;
        grid-template-columns: minmax(170px, .8fr) minmax(250px, 1.3fr) auto;
        align-items: end;
        gap: 14px;
    }
    .bc-review-approve-action {
        display: flex;
        align-items: end;
        min-height: 44px;
    }
    .bc-review-approve-action .bc-btn { white-space: nowrap; }
    .bc-review-reject-control {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        min-height: 48px;
        padding: 0 2px;
    }
    .bc-review-reject-copy {
        color: #6c7a73;
        font-size: 12px;
        line-height: 1.4;
    }
    .bc-review-reject-copy strong { display: block; color: #3b4f44; font-size: 13px; }
    .bc-structured-field {
        display: grid;
        gap: 6px;
    }
    .bc-structured-field.full {
        grid-column: 1 / -1;
    }
    .bc-structured-field span {
        font-size: 11px;
        color: #6d7b75;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }
    .bc-field-help {
        color: #718078;
        font-size: 11px;
        line-height: 1.4;
    }
    .bc-review-modal .bc-input {
        min-height: 44px;
        border-color: #cfd9d3;
        border-radius: 10px;
        transition: border-color .18s ease, box-shadow .18s ease;
    }
    .bc-review-modal textarea.bc-input { min-height: 88px; resize: vertical; }
    .bc-review-modal .bc-input:hover { border-color: #aebfb6; }
    .bc-btn.danger {
        color: #b42318;
        background: #fff3f2;
        border: 1px solid #f1c7c2;
    }
    .bc-btn.danger:hover { color: #8f1c13; background: #ffe8e5; border-color: #eaa69d; }
    .bc-review-modal .bc-actions .bc-btn {
        min-height: 44px;
        padding-inline: 18px;
    }
    .bc-btn.approve:hover { box-shadow: 0 7px 18px rgba(22, 101, 52, .2); filter: brightness(.98); }
    .bc-structured-field strong,
    .bc-structured-field p {
        margin: 0;
        font-size: 15px;
        color: #0b2e20;
        word-break: break-word;
        line-height: 1.5;
    }
    .bc-mail-lock { padding: 11px 12px; border: 1px solid #f1d49b; border-radius: 6px; background: #fff9eb; color: #805b11; font-size: 12px; line-height: 1.45; }
    .bc-review-modal .bc-mail-lock {
        display: flex;
        align-items: flex-start;
        gap: 9px;
        padding: 12px 14px;
        border-radius: 12px;
    }
    .bc-review-modal .bc-mail-lock i { margin-top: 2px; }
    .bc-check-row {
        display: flex;
        flex-wrap: wrap;
        gap: 8px 10px;
    }
    .bc-check-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 10px;
        border-radius: 999px;
        background: #eef6f1;
        color: #0f5b42;
        font-size: 12px;
        font-weight: 700;
        border: 1px solid #cfe3d8;
    }
    .bc-check-pill.empty {
        background: #f7f5ee;
        color: #6d7b75;
        border-color: #e7e0d4;
    }
    .bc-workbook { display: grid; gap: 10px; }
    .bc-workbook .bc-card { border-radius: 8px; padding: 14px; box-shadow: 0 2px 8px rgba(10, 25, 16, .05); }
    .bc-workbook-head { display: flex; align-items: center; gap: 18px; min-width: 0; }
    .bc-workbook-title { min-width: 230px; }
    .bc-workbook-title h2 { margin: 0; color: #0b2e20; font-size: 20px; font-weight: 800; }
    .bc-workbook-title p { margin: 4px 0 0; color: #68766f; font-size: 12px; }
    .bc-stat-strip { display: grid; grid-template-columns: repeat(4, minmax(120px, 1fr)); flex: 1; border: 1px solid #d9e1dc; border-radius: 6px; overflow: hidden; }
    .bc-stat-cell { padding: 9px 12px; background: #f8faf9; border-right: 1px solid #d9e1dc; }
    .bc-stat-cell:last-child { border-right: 0; }
    .bc-stat-cell span { display: block; color: #65746d; font-size: 9px; font-weight: 800; text-transform: uppercase; }
    .bc-stat-cell strong { display: block; margin-top: 3px; color: #0b2e20; font-size: 18px; line-height: 1.1; white-space: nowrap; }
    .bc-toolbar { display: grid; grid-template-columns: 130px 130px minmax(240px, 1fr) auto auto; gap: 8px; align-items: end; }
    .bc-toolbar .bc-input { min-height: 36px; border-radius: 5px; padding: 7px 9px; }
    .bc-toolbar .bc-btn { min-height: 36px; border-radius: 5px; padding: 8px 12px; }
    .bc-sheet-tabs { display: flex; align-items: end; gap: 2px; padding: 0 8px; border-bottom: 2px solid #0b5a40; }
    .bc-sheet-tab { border: 1px solid #d2ddd7; border-bottom: 0; border-radius: 5px 5px 0 0; background: #edf3ef; color: #365448; padding: 9px 15px; font-size: 12px; font-weight: 800; cursor: pointer; }
    .bc-sheet-tab.is-active { background: #0b5a40; color: #fff; border-color: #0b5a40; }
    .bc-sheet-tab .count { margin-left: 6px; opacity: .8; }
    .bc-sheet-panel[hidden] { display: none; }
    .bc-sheet { border: 1px solid #d6dfda; border-radius: 6px; background: #fff; overflow: hidden; }
    .bc-sheet-bar { min-height: 42px; padding: 8px 12px; display: flex; align-items: center; justify-content: space-between; gap: 12px; border-bottom: 1px solid #d6dfda; background: #f8faf9; }
    .bc-sheet-bar strong { color: #14392b; font-size: 14px; }
    .bc-sheet-bar span { color: #68776f; font-size: 11px; }
    .bc-table-wrap { overflow: auto; max-height: calc(100vh - 325px); scrollbar-gutter: stable; }
    .bc-table { width: 100%; min-width: 1180px; border-collapse: separate; border-spacing: 0; font-size: 12px; }
    .bc-table th { position: sticky; top: 0; z-index: 3; background: #083f30; color: #fff; padding: 9px 10px; border-right: 1px solid #2d6556; text-align: left; font-size: 10px; text-transform: uppercase; white-space: nowrap; }
    .bc-table td { padding: 7px 10px; border-right: 1px solid #dde5e1; border-bottom: 1px solid #dde5e1; color: #253f35; vertical-align: middle; background: #fff; }
    .bc-table tbody tr:nth-child(even) td { background: #f8faf9; }
    .bc-table tbody tr:hover td { background: #edf7f2; }
    .bc-table .bc-freeze { position: sticky; left: 0; z-index: 2; min-width: 190px; box-shadow: 2px 0 0 #d8e2dd; }
    .bc-table th.bc-freeze { z-index: 5; background: #083f30; }
    .bc-table td.bc-freeze { font-weight: 800; color: #0b2e20; }
    .bc-table .bc-action-col { position: sticky; right: 0; z-index: 2; min-width: 170px; box-shadow: -2px 0 0 #d8e2dd; }
    .bc-table th.bc-action-col { z-index: 5; background: #083f30; }
    .bc-cell-sub { display: block; color: #78867f; font-size: 10px; font-weight: 500; margin-top: 2px; white-space: nowrap; }
    .bc-table .bc-tag { padding: 5px 8px; border-radius: 5px; white-space: nowrap; }
    .bc-table .bc-btn { border-radius: 5px; padding: 7px 9px; white-space: nowrap; }
    .bc-table .bc-input { min-width: 105px; height: 32px; border-radius: 4px; padding: 5px 7px; font-size: 11px; }
    .bc-inline-edit { display: grid; grid-template-columns: 110px 125px auto; gap: 5px; min-width: 315px; }
    .bc-inline-actions { display: flex; gap: 5px; align-items: center; }
    .bc-request-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); border-bottom: 1px solid #d6dfda; }
    .bc-request-kpi { padding: 8px 12px; border-right: 1px solid #d6dfda; background: #fbfcfb; }
    .bc-request-kpi:last-child { border-right: 0; }
    .bc-request-kpi span { display: block; color: #6b7972; font-size: 9px; font-weight: 800; text-transform: uppercase; }
    .bc-request-kpi strong { display: block; margin-top: 2px; color: #0b2e20; font-size: 16px; }
    @media (max-width: 1100px) {
        .bc-stats { grid-template-columns: 1fr; }
        .bc-filters { grid-template-columns: 1fr; }
        .bc-item { grid-template-columns: 1fr; }
        .bc-kyc-grid { grid-template-columns: 1fr; }
        .bc-structured-grid { grid-template-columns: 1fr; }
        .bc-request-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .bc-workbook-head { align-items: stretch; flex-direction: column; }
        .bc-stat-strip { width: 100%; }
        .bc-toolbar { grid-template-columns: 1fr 1fr; }
        .bc-toolbar .bc-field:nth-child(3) { grid-column: 1 / -1; }
    }
    @media (max-width: 700px) {
        .bc-card { padding: 18px; border-radius: 20px; }
        .bc-head h2, .bc-head h3 { font-size: 24px; }
        .bc-modal-head, .bc-modal-foot, .bc-modal-body { padding: 16px; }
        .bc-revenue-form { width: 100%; grid-template-columns: 1fr auto; }
        .bc-request-summary { grid-template-columns: 1fr; }
        .bc-workbook .bc-card { padding: 12px; }
        .bc-stat-strip { grid-template-columns: repeat(2, 1fr); }
        .bc-stat-cell:nth-child(2) { border-right: 0; }
        .bc-stat-cell:nth-child(-n+2) { border-bottom: 1px solid #d9e1dc; }
        .bc-toolbar { grid-template-columns: 1fr 1fr; }
        .bc-toolbar .bc-btn { width: 100%; }
        .bc-sheet-tabs { overflow-x: auto; }
        .bc-sheet-tab { white-space: nowrap; }
        .bc-sheet-bar { align-items: flex-start; flex-direction: column; }
        .bc-table-wrap { max-height: none; overflow: visible; }
        .bc-table { min-width: 0; display: block; }
        .bc-table thead { display: none; }
        .bc-table tbody { display: grid; gap: 10px; padding: 10px; }
        .bc-table tr { display: grid; border: 1px solid #d6dfda; border-radius: 6px; overflow: hidden; }
        .bc-table td, .bc-table td.bc-freeze, .bc-table td.bc-action-col { position: static; display: grid; grid-template-columns: 115px minmax(0, 1fr); gap: 8px; min-width: 0; padding: 8px 10px; box-shadow: none; background: #fff !important; }
        .bc-table td::before { content: attr(data-label); color: #708078; font-size: 9px; font-weight: 800; text-transform: uppercase; }
        .bc-table td.bc-freeze { background: #eef7f2 !important; font-size: 14px; }
        .bc-inline-edit { min-width: 0; grid-template-columns: 1fr; }
        .bc-inline-actions { flex-wrap: wrap; }
        .bc-request-kpis { grid-template-columns: repeat(2, 1fr); }
        .bc-request-kpi:nth-child(2) { border-right: 0; }
        .bc-modal-shell { align-items: flex-end; padding: 0; }
        .bc-review-modal { max-height: 94dvh; border-radius: 20px 20px 0 0; }
        .bc-review-modal .bc-modal-head,
        .bc-review-modal .bc-modal-foot,
        .bc-review-modal .bc-modal-body { padding-left: 18px; padding-right: 18px; }
        .bc-review-modal .bc-modal-title { font-size: 21px; }
        .bc-review-modal .bc-modal-body { max-height: calc(94dvh - 146px); }
        .bc-review-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .bc-review-summary-item:nth-child(2) { border-right: 0; }
        .bc-review-summary-item:nth-child(-n+2) { border-bottom: 1px solid #dbe7e0; }
        .bc-review-section { padding: 17px; }
        .bc-review-approve-grid { grid-template-columns: 1fr; }
        .bc-review-approve-action { width: 100%; }
        .bc-review-approve-action .bc-btn { width: 100%; }
        .bc-review-reject-control { align-items: flex-start; flex-direction: column; }
        .bc-review-reject-control .bc-btn { width: 100%; }
        .bc-review-modal .bc-actions .bc-btn { width: 100%; }
    }
    @media (prefers-reduced-motion: reduce) {
        .bc-modal-close, .bc-btn, .bc-input { transition: none; }
    }
</style>
@endpush

@section('content')
<div class="bc-workbook">
    <section class="bc-card">
        <div class="bc-workbook-head">
            <div class="bc-workbook-title">
                <h2>Booked Customer Workbook</h2>
                <p>Verified bookings, closer handovers and finance values in one sheet.</p>
            </div>
            <div class="bc-stat-strip">
                <div class="bc-stat-cell"><span>Verified Bookings</span><strong>{{ $bookedCustomers['count'] ?? 0 }}</strong></div>
                <div class="bc-stat-cell"><span>KYC Complete</span><strong>{{ $bookedCustomers['with_complete_kyc'] ?? 0 }}</strong></div>
                <div class="bc-stat-cell"><span>Verified Incentive</span><strong>Rs {{ number_format($bookedCustomers['total_incentive'] ?? 0, 0) }}</strong></div>
                <div class="bc-stat-cell"><span>Revenue Pending</span><strong>{{ $bookedCustomers['without_revenue'] ?? 0 }}</strong></div>
            </div>
        </div>
    </section>

    <section class="bc-card">
        <form method="GET" action="{{ route('finance-manager.booked-customers') }}" class="bc-toolbar">
            <div class="bc-field"><label for="month">Month</label><select id="month" name="month" class="bc-input"><option value="">All Months</option>@foreach(range(1, 12) as $m)<option value="{{ $m }}" @selected((int) ($month ?? 0) === $m)>{{ \Carbon\Carbon::create(null, $m, 1)->format('F') }}</option>@endforeach</select></div>
            <div class="bc-field"><label for="year">Year</label><select id="year" name="year" class="bc-input"><option value="">All Years</option>@foreach(range(now()->year, now()->year - 3) as $y)<option value="{{ $y }}" @selected((int) ($year ?? 0) === $y)>{{ $y }}</option>@endforeach</select></div>
            <div class="bc-field"><label for="search">Search</label><input id="search" name="search" class="bc-input" value="{{ $search ?? '' }}" placeholder="Customer, phone, project or owner"></div>
            <button type="submit" class="bc-btn approve"><i class="fas fa-filter"></i> Apply</button>
            <a href="{{ route('finance-manager.booked-customers') }}" class="bc-btn secondary" style="text-decoration:none;"><i class="fas fa-rotate-left"></i> Reset</a>
        </form>
    </section>

    <div class="bc-sheet-tabs" role="tablist" aria-label="Booked customer workbook sheets">
        <button type="button" class="bc-sheet-tab is-active" role="tab" aria-selected="true" data-sheet-tab="booked">Booked Customers <span class="count">{{ $bookedCustomers['count'] ?? 0 }}</span></button>
        <button type="button" class="bc-sheet-tab" role="tab" aria-selected="false" data-sheet-tab="closers">Closer Requests <span class="count">{{ $closerRequests['count'] ?? 0 }}</span></button>
        <button type="button" class="bc-sheet-tab" role="tab" aria-selected="false" data-sheet-tab="incentives">Incentive Approval <span class="count">{{ $incentiveApprovals['pending_count'] ?? 0 }}</span></button>
    </div>

    <section class="bc-sheet bc-sheet-panel" data-sheet-panel="booked">
        <div class="bc-sheet-bar"><div><strong>Verified Bookings</strong><span> · Revenue and booking date can be edited directly.</span></div><span>{{ $bookedCustomers['count'] ?? 0 }} row(s)</span></div>
        <div class="bc-table-wrap">
            <table class="bc-table">
                <thead><tr><th class="bc-freeze">Customer</th><th>Project</th><th>Phone</th><th>Owner</th><th>Booking</th><th>KYC</th><th>Payment</th><th>Incentive</th><th>Revenue / Date</th><th class="bc-action-col">Actions</th></tr></thead>
                <tbody>
                @forelse($bookedCustomers['recent'] ?? [] as $customer)
                    <tr>
                        <td class="bc-freeze" data-label="Customer">{{ $customer['customer_name'] }}<span class="bc-cell-sub">#{{ $customer['site_visit_id'] }}</span></td>
                        <td data-label="Project">{{ $customer['project'] }}<span class="bc-cell-sub">{{ $customer['budget_range'] }}</span></td>
                        <td data-label="Phone">{{ $customer['phone'] ?: 'Not available' }}</td>
                        <td data-label="Owner">{{ $customer['assigned_to'] }}<span class="bc-cell-sub">By {{ $customer['transferred_by'] }}</span></td>
                        <td data-label="Booking">{{ !empty($customer['booking_date']) ? \Illuminate\Support\Carbon::parse($customer['booking_date'])->format('d M Y') : 'Pending' }}<span class="bc-cell-sub">{{ $customer['incentive_verified_at'] ?: 'Verified' }}</span></td>
                        <td data-label="KYC"><span class="bc-tag {{ $customer['has_complete_kyc'] ? 'ready' : 'missing' }}"><i class="fas {{ $customer['has_complete_kyc'] ? 'fa-circle-check' : 'fa-triangle-exclamation' }}"></i>{{ $customer['has_complete_kyc'] ? 'Complete' : 'Pending' }}</span></td>
                        <td data-label="Payment">{{ $customer['payment_mode'] ?: 'Not filled' }}</td>
                        <td data-label="Incentive">Rs {{ number_format($customer['incentive_amount'] ?? 0, 0) }}</td>
                        <td data-label="Revenue / Date">
                            <form method="POST" action="{{ route('finance-manager.booked-customers.revenue.update', $customer['site_visit_id']) }}" class="bc-inline-edit">@csrf
                                <input class="bc-input" aria-label="Revenue for {{ $customer['customer_name'] }}" type="number" min="0" step="0.01" name="revenue_value" value="{{ $customer['revenue_value'] ?: '' }}" placeholder="Revenue" required>
                                <input class="bc-input" aria-label="Booking date for {{ $customer['customer_name'] }}" type="date" name="booking_date" value="{{ $customer['booking_date'] ?? '' }}" max="{{ now()->toDateString() }}" required>
                                <button type="submit" class="bc-btn approve" title="Save revenue and booking date"><i class="fas fa-floppy-disk"></i> Save</button>
                            </form>
                        </td>
                        <td class="bc-action-col" data-label="Actions"><div class="bc-inline-actions"><a href="{{ route('finance-manager.booked-customers.kyc.show', $customer['site_visit_id']) }}" target="_blank" rel="noopener" class="bc-btn secondary" style="text-decoration:none;"><i class="fas fa-id-card"></i> KYC</a>@if(!empty($customer['lead_id']))<a href="{{ route('leads.show', $customer['lead_id']) }}" class="bc-btn approve" style="text-decoration:none;"><i class="fas fa-arrow-up-right-from-square"></i> Lead</a>@endif</div></td>
                    </tr>
                @empty
                    <tr><td colspan="10"><div class="bc-empty">No verified booked customers found.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="bc-sheet bc-sheet-panel" data-sheet-panel="closers" hidden>
        <div class="bc-sheet-bar"><div><strong>Closer Requests</strong><span> · Read-only finance handover tracker.</span></div><span>{{ $closerRequests['count'] ?? 0 }} row(s)</span></div>
        <div class="bc-request-kpis">
            <div class="bc-request-kpi"><span>Admin Pending</span><strong>{{ $closerRequests['pending_admin'] ?? 0 }}</strong></div>
            <div class="bc-request-kpi"><span>Admin Approved</span><strong>{{ $closerRequests['admin_approved'] ?? 0 }}</strong></div>
            <div class="bc-request-kpi"><span>Finance Pending</span><strong>{{ $closerRequests['finance_pending'] ?? 0 }}</strong></div>
            <div class="bc-request-kpi"><span>Finance Reviewed</span><strong>{{ $closerRequests['finance_reviewed'] ?? 0 }}</strong></div>
        </div>
        <div class="bc-table-wrap">
            <table class="bc-table">
                <thead><tr><th class="bc-freeze">Customer</th><th>Project</th><th>Source</th><th>Booking Credit</th><th>Submitted By</th><th>Submitted</th><th>Approval Admin</th><th>Status</th><th>Reviewed By</th><th class="bc-action-col">Action</th></tr></thead>
                <tbody>
                @forelse($closerRequests['recent'] ?? [] as $closerRequest)
                    <tr>
                        <td class="bc-freeze" data-label="Customer">{{ $closerRequest['customer_name'] }}<span class="bc-cell-sub">{{ $closerRequest['phone'] ?: 'Phone not available' }}</span></td>
                        <td data-label="Project">{{ $closerRequest['project'] }}@if(!empty($closerRequest['is_finance_direct_closer']))<span class="bc-cell-sub">Finance Direct</span>@endif</td>
                        <td data-label="Source">{{ ucfirst((string) $closerRequest['source']) }}</td>
                        <td data-label="Booking Credit">{{ $closerRequest['booking_credit_to'] }}</td>
                        <td data-label="Submitted By">{{ $closerRequest['submitted_by'] }}</td>
                        <td data-label="Submitted">{{ $closerRequest['closer_submitted_at'] ?: 'Not available' }}@if(!empty($closerRequest['closer_verified_at']))<span class="bc-cell-sub">Approved {{ $closerRequest['closer_verified_at'] }}</span>@endif</td>
                        <td data-label="Approval Admin">{{ $closerRequest['approval_admin'] }}</td>
                        <td data-label="Status"><span class="bc-tag {{ $closerRequest['status_class'] }}"><i class="fas fa-circle-info"></i>{{ $closerRequest['status_label'] }}</span></td>
                        <td data-label="Reviewed By">{{ $closerRequest['finance_reviewed_by'] ?: ($closerRequest['reviewed_by'] ?: 'Pending') }}@if(!empty($closerRequest['finance_reviewed_at']))<span class="bc-cell-sub">{{ $closerRequest['finance_reviewed_at'] }}</span>@endif</td>
                        <td class="bc-action-col" data-label="Action">@if(!empty($closerRequest['lead_id']))<a href="{{ route('leads.show', $closerRequest['lead_id']) }}" class="bc-btn secondary" style="text-decoration:none;"><i class="fas fa-arrow-up-right-from-square"></i> View Lead</a>@else<span class="bc-cell-sub">Lead unavailable</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="10"><div class="bc-empty">No closer requests found for the selected filters.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="bc-sheet bc-sheet-panel" data-sheet-panel="incentives" hidden>
        <div class="bc-sheet-bar"><div><strong>Incentive Approval</strong><span> · Finance action queue and sales request visibility.</span></div><span>{{ $incentiveApprovals['pending_count'] ?? 0 }} awaiting action</span></div>
        <div class="bc-request-kpis">
            <div class="bc-request-kpi"><span>Finance Review</span><strong>{{ $incentiveApprovals['pending_count'] ?? 0 }}</strong></div>
            <div class="bc-request-kpi"><span>Requested Amount</span><strong>Rs {{ number_format($incentiveApprovals['pending_amount'] ?? 0, 0) }}</strong></div>
            <div class="bc-request-kpi"><span>Not Requested</span><strong>{{ $incentiveApprovals['not_requested_count'] ?? 0 }}</strong></div>
            <div class="bc-request-kpi"><span>KYC Pending</span><strong>{{ $incentiveApprovals['kyc_pending_count'] ?? 0 }}</strong></div>
        </div>
        <div class="bc-table-wrap">
            <table class="bc-table">
                <thead><tr><th class="bc-freeze">Customer</th><th>Project</th><th>Sales Person</th><th>KYC</th><th>Request Date</th><th>Requested Amount</th><th>Incentive Status</th><th>Approval / Rejection</th><th class="bc-action-col">Next Action</th></tr></thead>
                <tbody>
                @forelse($incentiveApprovals['rows'] ?? [] as $approval)
                    <tr>
                        <td class="bc-freeze" data-label="Customer">{{ $approval['customer_name'] }}<span class="bc-cell-sub">{{ $approval['phone'] ?: 'Phone not available' }}</span></td>
                        <td data-label="Project">{{ $approval['project'] }}</td>
                        <td data-label="Sales Person">{{ $approval['requested_by'] }}</td>
                        <td data-label="KYC"><span class="bc-tag {{ $approval['kyc_complete'] ? 'ready' : 'missing' }}"><i class="fas {{ $approval['kyc_complete'] ? 'fa-circle-check' : 'fa-triangle-exclamation' }}"></i>{{ $approval['kyc_complete'] ? 'Complete' : 'Pending' }}</span></td>
                        <td data-label="Request Date">{{ $approval['requested_at'] ?: 'Not requested' }}</td>
                        <td data-label="Requested Amount">{{ $approval['requested_amount'] !== null ? 'Rs '.number_format($approval['requested_amount'], 2) : '-' }}</td>
                        <td data-label="Incentive Status"><span class="bc-tag {{ $approval['status_class'] }}"><i class="fas fa-circle-info"></i>{{ $approval['status_label'] }}</span></td>
                        <td data-label="Approval / Rejection">@if(!empty($approval['verified_at'])){{ $approval['verified_at'] }}@elseif(!empty($approval['rejection_reason']))<span title="{{ $approval['rejection_reason'] }}">{{ \Illuminate\Support\Str::limit($approval['rejection_reason'], 42) }}</span>@else<span class="bc-cell-sub">Awaiting sales request</span>@endif</td>
                        <td class="bc-action-col" data-label="Next Action"><div class="bc-inline-actions">@if($approval['can_review'])<button type="button" class="bc-btn approve" data-incentive-review data-incentive-id="{{ $approval['incentive_id'] }}" data-customer="{{ $approval['customer_name'] }}" data-project="{{ $approval['project'] }}" data-sales-person="{{ $approval['requested_by'] }}" data-amount="{{ $approval['requested_amount'] }}"><i class="fas fa-clipboard-check"></i> Review</button>@elseif(!empty($approval['lead_id']))<a href="{{ route('leads.show', $approval['lead_id']) }}" class="bc-btn secondary" style="text-decoration:none;"><i class="fas fa-arrow-up-right-from-square"></i> View Lead</a>@else<span class="bc-cell-sub">No action</span>@endif</div></td>
                    </tr>
                @empty
                    <tr><td colspan="9"><div class="bc-empty">No closer incentive rows found for the selected filters.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>

<div id="incentiveApprovalModal" class="bc-modal-shell" hidden aria-hidden="true" onclick="if (event.target === this) closeIncentiveApproval()">
    <div class="bc-modal bc-review-modal" role="dialog" aria-modal="true" aria-labelledby="incentiveApprovalTitle" aria-describedby="incentiveApprovalSubtitle">
        <div class="bc-modal-head">
            <div>
                <span class="bc-modal-eyebrow"><i class="fas fa-clipboard-check" aria-hidden="true"></i> Finance review</span>
                <h3 id="incentiveApprovalTitle" class="bc-modal-title">Review Incentive</h3>
                <p id="incentiveApprovalSubtitle" class="bc-modal-subtitle">Verify the request, then approve or reject it with a clear audit note.</p>
            </div>
            <button type="button" class="bc-modal-close" onclick="closeIncentiveApproval()" aria-label="Close incentive review"><i class="fas fa-times" aria-hidden="true"></i></button>
        </div>
        <div class="bc-modal-body">
            <div class="bc-review-summary" aria-label="Incentive request summary">
                <div class="bc-review-summary-item"><span>Customer</span><strong id="incentiveReviewCustomer">—</strong></div>
                <div class="bc-review-summary-item"><span>Project</span><strong id="incentiveReviewProject">—</strong></div>
                <div class="bc-review-summary-item"><span>Requested by</span><strong id="incentiveReviewSalesPerson">—</strong></div>
                <div class="bc-review-summary-item"><span>Requested amount</span><strong id="incentiveReviewAmount">₹0</strong></div>
            </div>
            <div class="bc-mail-lock"><i class="fas fa-circle-info" aria-hidden="true"></i><span>Amount change karne par approval remark mandatory hai. Approval aur rejection dono booking timeline mein record honge.</span></div>
            <form id="incentiveApproveForm" method="POST" class="bc-structured-section bc-review-section approve">@csrf
                <input type="hidden" name="year" value="{{ $year }}"><input type="hidden" name="month" value="{{ $month }}"><input type="hidden" name="search" value="{{ $search }}">
                <div class="bc-review-section-head">
                    <span class="bc-review-section-icon"><i class="fas fa-circle-check" aria-hidden="true"></i></span>
                    <div class="bc-review-section-copy"><h4 class="bc-structured-title">Approve Incentive</h4><p>Confirm the final payable amount before approval.</p></div>
                </div>
                <div class="bc-review-approve-grid">
                    <label class="bc-structured-field" for="incentiveApprovedAmount"><span>Approved amount</span><input id="incentiveApprovedAmount" class="bc-input" name="approved_amount" type="number" min="0" step="0.01" inputmode="decimal" required></label>
                    <label class="bc-structured-field" for="incentiveApprovalRemark"><span>Approval remark</span><input id="incentiveApprovalRemark" class="bc-input" name="approval_remark" maxlength="500" aria-describedby="incentiveApprovalRemarkHelp" placeholder="Add a note if the amount changes"><small id="incentiveApprovalRemarkHelp" class="bc-field-help">Required only when approved amount differs from the request.</small></label>
                    <div class="bc-review-approve-action"><button type="submit" class="bc-btn approve"><i class="fas fa-circle-check" aria-hidden="true"></i> Approve Incentive</button></div>
                </div>
            </form>
            <div class="bc-review-reject-control">
                <div class="bc-review-reject-copy"><strong>Request needs correction?</strong>Reject only when the sales person needs to update this request.</div>
                <button id="incentiveRejectToggle" type="button" class="bc-btn danger" aria-expanded="false" aria-controls="incentiveRejectForm"><i class="fas fa-ban" aria-hidden="true"></i> Reject Request</button>
            </div>
            <form id="incentiveRejectForm" method="POST" class="bc-structured-section bc-review-section reject" hidden>@csrf
                <input type="hidden" name="year" value="{{ $year }}"><input type="hidden" name="month" value="{{ $month }}"><input type="hidden" name="search" value="{{ $search }}">
                <div class="bc-review-section-head">
                    <span class="bc-review-section-icon"><i class="fas fa-ban" aria-hidden="true"></i></span>
                    <div class="bc-review-section-copy"><h4 class="bc-structured-title">Reject Incentive</h4><p>Give the sales person a clear reason so they can correct the request.</p></div>
                </div>
                <label class="bc-structured-field full" for="incentiveRejectionReason"><span>Rejection reason</span><textarea id="incentiveRejectionReason" class="bc-input" name="rejection_reason" minlength="5" maxlength="500" rows="3" aria-describedby="incentiveRejectionReasonHelp" required placeholder="Explain what needs to be corrected"></textarea><small id="incentiveRejectionReasonHelp" class="bc-field-help">This reason will be visible to the sales person and saved in the audit timeline.</small></label>
                <div class="bc-actions"><button type="submit" class="bc-btn danger"><i class="fas fa-ban" aria-hidden="true"></i> Confirm Rejection</button></div>
            </form>
        </div>
        <div class="bc-modal-foot"><button type="button" class="bc-btn secondary" onclick="closeIncentiveApproval()">Cancel</button></div>
    </div>
</div>

<div id="bookedCustomerKycModal" class="bc-modal-shell" hidden aria-hidden="true" onclick="if (event.target === this) closeBookedCustomerKyc()">
    <div class="bc-modal">
        <div class="bc-modal-head">
            <div>
                <h3 id="bookedCustomerKycTitle" class="bc-modal-title">KYC Details</h3>
                <p id="bookedCustomerKycSubtitle" class="bc-modal-subtitle">Finance can inspect submitted KYC before review.</p>
            </div>
            <button type="button" class="bc-modal-close" onclick="closeBookedCustomerKyc()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="bookedCustomerKycBody" class="bc-modal-body"></div>
        <div class="bc-modal-foot">
            <button type="button" class="bc-btn secondary" onclick="closeBookedCustomerKyc()">Close</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@php
    $bookedCustomerKycMap = collect($bookedCustomers['recent'] ?? [])->mapWithKeys(function ($customer) {
        return [
            $customer['id'] => [
                'customer_name' => $customer['customer_name'] ?? 'Customer',
                'project' => $customer['project'] ?? 'Project not filled',
                'status' => !empty($customer['has_complete_kyc']) ? 'KYC complete' : 'KYC pending',
                'nominee_name' => $customer['kyc']['nominee_name'] ?? null,
                'second_customer_name' => $customer['kyc']['second_customer_name'] ?? null,
                'customer_dob' => $customer['kyc']['customer_dob'] ?? null,
                'pan_card' => $customer['kyc']['pan_card'] ?? null,
                'aadhaar_card_no' => $customer['kyc']['aadhaar_card_no'] ?? null,
                'primary_applicant_details' => $customer['kyc']['primary_applicant_details'] ?? [],
                'joint_applicant_details' => $customer['kyc']['joint_applicant_details'] ?? [],
                'unit_details' => $customer['kyc']['unit_details'] ?? [],
                'schema' => $customer['kyc']['schema'] ?? [],
                'kyc_documents' => $customer['kyc']['kyc_documents'] ?? [],
                'proof_photos' => $customer['kyc']['proof_photos'] ?? [],
            ],
        ];
    })->all();
@endphp
<script>
    const bookedCustomerSheetTabs = document.querySelectorAll('[data-sheet-tab]');
    const bookedCustomerSheetPanels = document.querySelectorAll('[data-sheet-panel]');

    function activateBookedCustomerSheet(sheet) {
        const selected = ['booked', 'closers', 'incentives'].includes(sheet) ? sheet : 'booked';
        bookedCustomerSheetTabs.forEach((tab) => {
            const active = tab.dataset.sheetTab === selected;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        bookedCustomerSheetPanels.forEach((panel) => {
            panel.hidden = panel.dataset.sheetPanel !== selected;
        });
        sessionStorage.setItem('financeBookedCustomerSheet', selected);
    }

    bookedCustomerSheetTabs.forEach((tab) => tab.addEventListener('click', () => activateBookedCustomerSheet(tab.dataset.sheetTab)));
    activateBookedCustomerSheet(@json(request('sheet')) || sessionStorage.getItem('financeBookedCustomerSheet'));

    const incentiveApprovalModal = document.getElementById('incentiveApprovalModal');
    const incentiveApproveForm = document.getElementById('incentiveApproveForm');
    const incentiveRejectForm = document.getElementById('incentiveRejectForm');
    const incentiveReviewCustomer = document.getElementById('incentiveReviewCustomer');
    const incentiveReviewProject = document.getElementById('incentiveReviewProject');
    const incentiveReviewSalesPerson = document.getElementById('incentiveReviewSalesPerson');
    const incentiveReviewAmount = document.getElementById('incentiveReviewAmount');
    const incentiveApprovedAmount = document.getElementById('incentiveApprovedAmount');
    const incentiveApprovalRemark = document.getElementById('incentiveApprovalRemark');
    const incentiveRejectionReason = document.getElementById('incentiveRejectionReason');
    const incentiveRejectToggle = document.getElementById('incentiveRejectToggle');
    const incentiveApproveUrl = @json(route('finance-manager.booked-customers.incentives.approve', ['incentive' => '__INCENTIVE__']));
    const incentiveRejectUrl = @json(route('finance-manager.booked-customers.incentives.reject', ['incentive' => '__INCENTIVE__']));
    let incentiveReviewTrigger = null;

    function closeIncentiveApproval() {
        incentiveApprovalModal.hidden = true;
        incentiveApprovalModal.setAttribute('aria-hidden', 'true');
        setIncentiveRejectOpen(false);
        incentiveReviewTrigger?.focus();
    }

    function setIncentiveRejectOpen(isOpen) {
        incentiveRejectForm.hidden = !isOpen;
        incentiveRejectToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        incentiveRejectToggle.innerHTML = isOpen
            ? '<i class="fas fa-chevron-up" aria-hidden="true"></i> Hide Rejection Form'
            : '<i class="fas fa-ban" aria-hidden="true"></i> Reject Request';
        if (isOpen) incentiveRejectionReason.focus();
    }

    incentiveRejectToggle.addEventListener('click', () => setIncentiveRejectOpen(incentiveRejectForm.hidden));

    document.querySelectorAll('[data-incentive-review]').forEach((button) => button.addEventListener('click', () => {
        const { incentiveId, customer, project, salesPerson, amount } = button.dataset;
        incentiveReviewTrigger = button;
        incentiveApproveForm.action = incentiveApproveUrl.replace('__INCENTIVE__', incentiveId);
        incentiveRejectForm.action = incentiveRejectUrl.replace('__INCENTIVE__', incentiveId);
        incentiveReviewCustomer.textContent = customer;
        incentiveReviewProject.textContent = project;
        incentiveReviewSalesPerson.textContent = salesPerson;
        incentiveReviewAmount.textContent = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 2 }).format(Number(amount) || 0);
        incentiveApprovedAmount.value = amount || 0;
        incentiveApprovalRemark.value = '';
        incentiveRejectionReason.value = '';
        setIncentiveRejectOpen(false);
        incentiveApprovalModal.hidden = false;
        incentiveApprovalModal.setAttribute('aria-hidden', 'false');
        incentiveApprovedAmount.focus();
    }));

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !incentiveApprovalModal.hidden) closeIncentiveApproval();
    });

    const bookedCustomerKycMap = @json($bookedCustomerKycMap);

    function escapeBookedCustomerHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, function (char) {
            return ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;',
            })[char];
        });
    }

    function renderBookedCustomerFiles(files, emptyLabel, icon) {
        if (!Array.isArray(files) || files.length === 0) {
            return `<div class="bc-doc-empty">${escapeBookedCustomerHtml(emptyLabel)}</div>`;
        }

        function getBookedCustomerOriginalPath(file) {
            try {
                const raw = String(file || '');
                const parsed = new URL(raw, window.location.origin);
                const queryPath = parsed.searchParams.get('path');
                if (queryPath) {
                    return decodeURIComponent(queryPath);
                }

                return decodeURIComponent(parsed.pathname || raw);
            } catch (error) {
                return String(file || '');
            }
        }

        function getBookedCustomerFileName(file) {
            try {
                const originalPath = getBookedCustomerOriginalPath(file);
                return originalPath.substring(originalPath.lastIndexOf('/') + 1) || 'Document';
            } catch (error) {
                return 'Document';
            }
        }

        function getBookedCustomerExtension(file) {
            const originalPath = getBookedCustomerOriginalPath(file).toLowerCase();
            const match = originalPath.match(/\.([a-z0-9]+)$/i);
            return match ? match[1] : '';
        }

        function isBookedCustomerImage(file) {
            return ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp', 'svg'].includes(getBookedCustomerExtension(file));
        }

        function isBookedCustomerPdf(file) {
            return getBookedCustomerExtension(file) === 'pdf';
        }

        function renderBookedCustomerPreview(fileUrl, fileName, fallbackIcon) {
            if (isBookedCustomerImage(fileUrl)) {
                return `<img src="${fileUrl}" alt="${fileName}" loading="lazy">`;
            }

            if (isBookedCustomerPdf(fileUrl)) {
                return `<iframe src="${fileUrl}#toolbar=0&navpanes=0&scrollbar=0" title="${fileName}"></iframe>`;
            }

            return `
                <div class="bc-doc-fallback">
                    <i class="fas ${fallbackIcon}"></i>
                    <div>${fileName}</div>
                </div>
            `;
        }

        return `
            <div class="bc-doc-grid">
                ${files.map(function (file, index) {
                    const fileUrl = escapeBookedCustomerHtml(file);
                    const fileName = escapeBookedCustomerHtml(getBookedCustomerFileName(file));
                    return `
                        <div class="bc-doc-card">
                            <div class="bc-doc-preview">
                                ${renderBookedCustomerPreview(fileUrl, fileName, icon)}
                            </div>
                            <div class="bc-doc-meta">
                                <div class="bc-doc-name">${fileName}</div>
                                <a class="bc-doc-link" href="${fileUrl}" target="_blank" rel="noopener">
                                    <i class="fas ${icon}"></i>
                                    <span>Open File ${index + 1}</span>
                                </a>
                            </div>
                        </div>
                    `;
                }).join('')}
            </div>
        `;
    }

    function renderBookedCustomerCheckItems(items, emptyLabel = 'Not specified') {
        if (!Array.isArray(items) || items.length === 0) {
            return `<span class="bc-check-pill empty">${escapeBookedCustomerHtml(emptyLabel)}</span>`;
        }

        return items.map(function (item) {
            return `<span class="bc-check-pill"><i class="fas fa-check"></i>${escapeBookedCustomerHtml(item)}</span>`;
        }).join('');
    }

    function renderBookedCustomerApplicantSection(title, applicant, isPrimary = false, fallback = {}) {
        const occupation = [];
        if (applicant.occupation_service) occupation.push('Service');
        if (applicant.occupation_professional) occupation.push('Professional');
        if (applicant.occupation_housewife) occupation.push('Housewife');
        if (applicant.occupation_business) occupation.push('Business');
        if (applicant.occupation_any_other) occupation.push(`Other: ${applicant.occupation_any_other}`);

        const residential = [];
        if (applicant.resident_indian) residential.push('Resident Indian');
        if (applicant.resident_non_resident) residential.push('Non-Resident');
        if (applicant.resident_foreign_national) residential.push('Foreign National of Indian Origin');
        if (applicant.resident_other) residential.push(`Other: ${applicant.resident_other}`);

        const marital = [];
        if (applicant.marital_status_married) marital.push('Married');
        if (applicant.marital_status_unmarried) marital.push('Unmarried');

        return `
            <section class="bc-structured-section">
                <h4 class="bc-structured-title">${escapeBookedCustomerHtml(title)}</h4>
                <div class="bc-structured-grid">
                    <div class="bc-structured-field">
                        <span>Applicant Name</span>
                        <strong>${escapeBookedCustomerHtml(applicant.name || fallback.name || 'N/A')}</strong>
                    </div>
                    <div class="bc-structured-field">
                        <span>S/W/D Of</span>
                        <strong>${escapeBookedCustomerHtml(applicant.relation_name || 'N/A')}</strong>
                    </div>
                    <div class="bc-structured-field">
                        <span>Date Of Birth</span>
                        <strong>${escapeBookedCustomerHtml(applicant.date_of_birth || fallback.date_of_birth || 'N/A')}</strong>
                    </div>
                    <div class="bc-structured-field">
                        <span>Nationality</span>
                        <strong>${escapeBookedCustomerHtml(applicant.nationality || 'N/A')}</strong>
                    </div>
                    <div class="bc-structured-field full">
                        <span>Occupation</span>
                        <div class="bc-check-row">${renderBookedCustomerCheckItems(occupation)}</div>
                    </div>
                    <div class="bc-structured-field full">
                        <span>Residential Status</span>
                        <div class="bc-check-row">${renderBookedCustomerCheckItems(residential)}</div>
                    </div>
                    <div class="bc-structured-field full">
                        <span>Marital Status</span>
                        <div class="bc-check-row">${renderBookedCustomerCheckItems(marital)}</div>
                    </div>
                    <div class="bc-structured-field">
                        <span>PAN No.</span>
                        <strong>${escapeBookedCustomerHtml(applicant.pan_no || fallback.pan_no || 'N/A')}</strong>
                    </div>
                    ${isPrimary ? `
                    <div class="bc-structured-field">
                        <span>Aadhaar No.</span>
                        <strong>${escapeBookedCustomerHtml(applicant.aadhaar_no || fallback.aadhaar_no || 'N/A')}</strong>
                    </div>` : ''}
                    <div class="bc-structured-field full">
                        <span>Address</span>
                        <p>${escapeBookedCustomerHtml(applicant.address || 'N/A')}</p>
                    </div>
                    <div class="bc-structured-field">
                        <span>City</span>
                        <strong>${escapeBookedCustomerHtml(applicant.city || 'N/A')}</strong>
                    </div>
                    <div class="bc-structured-field">
                        <span>State</span>
                        <strong>${escapeBookedCustomerHtml(applicant.state || 'N/A')}</strong>
                    </div>
                    <div class="bc-structured-field">
                        <span>Country</span>
                        <strong>${escapeBookedCustomerHtml(applicant.country || 'N/A')}</strong>
                    </div>
                    <div class="bc-structured-field">
                        <span>PIN</span>
                        <strong>${escapeBookedCustomerHtml(applicant.pin || 'N/A')}</strong>
                    </div>
                    <div class="bc-structured-field">
                        <span>Email</span>
                        <strong>${escapeBookedCustomerHtml(applicant.email || 'N/A')}</strong>
                    </div>
                    <div class="bc-structured-field">
                        <span>Tel. No.</span>
                        <strong>${escapeBookedCustomerHtml(applicant.tel_no || 'N/A')}</strong>
                    </div>
                    <div class="bc-structured-field">
                        <span>Mobile No.</span>
                        <strong>${escapeBookedCustomerHtml(applicant.mobile_no || 'N/A')}</strong>
                    </div>
                    <div class="bc-structured-field">
                        <span>Fax No.</span>
                        <strong>${escapeBookedCustomerHtml(applicant.fax_no || 'N/A')}</strong>
                    </div>
                </div>
            </section>
        `;
    }

    function renderBookedCustomerUnitSection(unit) {
        const parking = [];
        if (unit.car_parking_covered) parking.push('Covered');
        if (unit.car_parking_open) parking.push('Open');

        const paymentPlan = [];
        if (unit.payment_plan_construction_linked) paymentPlan.push('Construction Linked');
        if (unit.payment_plan_down_payment) paymentPlan.push('Down Payment');
        if (unit.payment_plan_other) paymentPlan.push(unit.payment_plan_other_text ? `Other: ${unit.payment_plan_other_text}` : 'Other');

        return `
            <section class="bc-structured-section">
                <h4 class="bc-structured-title">Details Of The Unit</h4>
                <div class="bc-structured-grid">
                    <div class="bc-structured-field">
                        <span>Unit No.</span>
                        <strong>${escapeBookedCustomerHtml(unit.unit_no || 'N/A')}</strong>
                    </div>
                    <div class="bc-structured-field">
                        <span>Block / Cluster</span>
                        <strong>${escapeBookedCustomerHtml(unit.block_cluster || 'N/A')}</strong>
                    </div>
                    <div class="bc-structured-field">
                        <span>Floor</span>
                        <strong>${escapeBookedCustomerHtml(unit.floor || 'N/A')}</strong>
                    </div>
                    <div class="bc-structured-field">
                        <span>Carpet Area (sq. mt.)</span>
                        <strong>${escapeBookedCustomerHtml(unit.carpet_area_sq_mt || 'N/A')}</strong>
                    </div>
                    <div class="bc-structured-field">
                        <span>Carpet Area (sq. ft.)</span>
                        <strong>${escapeBookedCustomerHtml(unit.carpet_area_sq_ft || 'N/A')}</strong>
                    </div>
                    <div class="bc-structured-field">
                        <span>Super Area (sq. mt.)</span>
                        <strong>${escapeBookedCustomerHtml(unit.super_area_sq_mt || 'N/A')}</strong>
                    </div>
                    <div class="bc-structured-field">
                        <span>Super Area (sq. ft.)</span>
                        <strong>${escapeBookedCustomerHtml(unit.super_area_sq_ft || 'N/A')}</strong>
                    </div>
                    <div class="bc-structured-field">
                        <span>Basic Sale Price (Rs.)</span>
                        <strong>${escapeBookedCustomerHtml(unit.basic_sale_price || 'N/A')}</strong>
                    </div>
                    <div class="bc-structured-field">
                        <span>PLC Amount (Rs.)</span>
                        <strong>${escapeBookedCustomerHtml(unit.plc_amount || 'N/A')}</strong>
                    </div>
                    <div class="bc-structured-field">
                        <span>Club Membership Charges</span>
                        <strong>${escapeBookedCustomerHtml(unit.club_membership_charges || 'N/A')}</strong>
                    </div>
                    <div class="bc-structured-field full">
                        <span>Car Parking Opted</span>
                        <div class="bc-check-row">${renderBookedCustomerCheckItems(parking)}</div>
                    </div>
                    <div class="bc-structured-field full">
                        <span>Payment Plan Opted</span>
                        <div class="bc-check-row">${renderBookedCustomerCheckItems(paymentPlan)}</div>
                    </div>
                </div>
            </section>
        `;
    }

    function renderBookedCustomerSchemaField(field) {
        const value = field.value;

        if (field.field_type === 'checkbox' && Array.isArray(field.options) && field.options.length > 0) {
            const selected = Array.isArray(value) ? value : [];
            return `
                <div class="bc-structured-field full">
                    <span>${escapeBookedCustomerHtml(field.label)}</span>
                    <div class="bc-check-row">${renderBookedCustomerCheckItems(selected.map((item) => ({ label: item, active: true })))}</div>
                </div>
            `;
        }

        if (field.field_type === 'checkbox') {
            return `
                <div class="bc-structured-field">
                    <span>${escapeBookedCustomerHtml(field.label)}</span>
                    <strong>${value ? 'Yes' : 'No'}</strong>
                </div>
            `;
        }

        if (field.field_type === 'file') {
            const files = Array.isArray(value) ? value : (value ? [value] : []);
            return `
                <div class="bc-structured-field full">
                    <span>${escapeBookedCustomerHtml(field.label)}</span>
                    ${renderBookedCustomerFiles(files, 'No file uploaded.', field.field_key === 'proof_photos' ? 'fa-image' : 'fa-file-lines')}
                </div>
            `;
        }

        return `
            <div class="bc-structured-field">
                <span>${escapeBookedCustomerHtml(field.label)}</span>
                <strong>${escapeBookedCustomerHtml(value || 'N/A')}</strong>
            </div>
        `;
    }

    function renderBookedCustomerSchemaSection(section) {
        return `
            <section class="bc-structured-section">
                <h4>${escapeBookedCustomerHtml(section.label || 'Section')}</h4>
                <div class="bc-structured-grid">
                    ${(section.fields || []).map((field) => renderBookedCustomerSchemaField(field)).join('')}
                </div>
            </section>
        `;
    }

    function openBookedCustomerKyc(id) {
        const customer = bookedCustomerKycMap[id];
        if (!customer) {
            return;
        }

        document.getElementById('bookedCustomerKycTitle').textContent = customer.customer_name || 'KYC Details';
        document.getElementById('bookedCustomerKycSubtitle').textContent = `${customer.project || 'Project not filled'} | ${customer.status || 'KYC details'}`;

        const schema = customer.schema || customer.kyc_schema || @json($kycFormSchema ?? []);

        document.getElementById('bookedCustomerKycBody').innerHTML = `
            ${(schema.sections || []).map((section) => renderBookedCustomerSchemaSection(section)).join('')}
        `;

        const modal = document.getElementById('bookedCustomerKycModal');
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
    }

    function closeBookedCustomerKyc() {
        const modal = document.getElementById('bookedCustomerKycModal');
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
    }
</script>
@endpush

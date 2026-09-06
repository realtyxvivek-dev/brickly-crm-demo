@extends('sales-manager.layout')

@section('title', 'Site Visits - Senior Manager')
@section('page-title', 'Site Visits')

@push('styles')
<style>
    .asm-inline-filter-panel {
        border: 1px solid #cfe8da;
        border-radius: 18px;
        box-shadow: 0 14px 34px rgba(15, 109, 68, 0.06);
        background: linear-gradient(135deg, #ffffff 0%, #f7fcf9 100%);
        padding: 18px;
        margin-bottom: 18px;
    }
    .asm-inline-filters-row {
        display: flex;
        flex-direction: row;
        flex-wrap: nowrap;
        align-items: center;
        gap: 8px;
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
    }
    .asm-inline-filters-row select,
    .asm-inline-filters-row input {
        flex: 1;
        min-width: 0;
        max-width: 100%;
        box-sizing: border-box;
        min-height: 48px;
        padding: 0 14px;
        border: 1px solid #d7e2da;
        border-radius: 12px;
        background: #ffffff;
        color: #063A1C;
        font-size: 0.95rem;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .asm-inline-filters-row select:focus,
    .asm-inline-filters-row input:focus {
        outline: none;
        border-color: #0f6d44;
        box-shadow: 0 0 0 4px rgba(15, 109, 68, 0.12);
    }
    .asm-inline-filter-date {
        display: none;
    }
    .asm-inline-filter-date.show {
        display: block;
    }
    .asm-personal-desk {
        border: 1px solid #cfe8da;
        border-radius: 22px;
        background: linear-gradient(135deg, #ffffff 0%, #f4fbf7 100%);
        box-shadow: 0 18px 42px rgba(15, 109, 68, 0.08);
        padding: 22px 24px;
        margin-bottom: 18px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        overflow: hidden;
        position: relative;
    }
    .asm-personal-desk::after {
        content: '';
        position: absolute;
        right: -42px;
        top: -60px;
        width: 220px;
        height: 220px;
        border-radius: 50%;
        background: rgba(15, 109, 68, 0.08);
        pointer-events: none;
    }
    .asm-personal-desk-copy {
        position: relative;
        z-index: 1;
        min-width: 0;
    }
    .asm-personal-desk-kicker {
        margin: 0 0 8px;
        color: #0f6d44;
        font-size: 12px;
        font-weight: 900;
        letter-spacing: 0.14em;
        text-transform: uppercase;
    }
    .asm-personal-desk-title {
        margin: 0;
        color: #063A1C;
        font-size: clamp(24px, 3vw, 34px);
        font-weight: 900;
        letter-spacing: -0.02em;
        line-height: 1.08;
    }
    .asm-personal-desk-subtitle {
        margin: 8px 0 0;
        color: #4c6757;
        font-size: 14px;
        font-weight: 600;
    }
    .asm-desk-date {
        position: relative;
        z-index: 1;
        border: 1px solid #cfe8da;
        border-radius: 16px;
        background: #ffffff;
        color: #063A1C;
        padding: 12px 18px;
        min-width: 150px;
        text-align: center;
        box-shadow: 0 12px 28px rgba(15, 109, 68, 0.07);
    }
    .asm-desk-date strong {
        display: block;
        font-size: 18px;
        line-height: 1;
    }
    .asm-desk-date span {
        display: block;
        margin-top: 5px;
        color: #6c8172;
        font-size: 12px;
        font-weight: 700;
    }
    .visit-summary-strip {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        margin-bottom: 18px;
    }
    .visit-summary-card {
        border: 1px solid #cfe8da;
        border-radius: 16px;
        background: #ffffff;
        padding: 16px;
        box-shadow: 0 12px 28px rgba(15, 109, 68, 0.06);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        min-height: 96px;
        width: 100%;
        cursor: pointer;
        text-align: left;
        font-family: inherit;
        transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
    }
    .visit-summary-card > div:first-child {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        align-items: center;
        gap: 8px;
        min-width: 0;
        flex: 1 1 auto;
    }
    .visit-summary-card:hover,
    .visit-summary-card.active {
        border-color: #0f6d44;
        box-shadow: 0 18px 36px rgba(15, 109, 68, 0.14);
        transform: translateY(-1px);
    }
    .visit-summary-card.active {
        background: linear-gradient(135deg, #0f6d44 0%, #063A1C 100%);
    }
    .visit-summary-card.active span,
    .visit-summary-card.active strong {
        color: #ffffff;
    }
    .visit-summary-card.active .visit-summary-icon {
        background: rgba(255, 255, 255, 0.18);
        color: #ffffff;
    }
    .sv-card.is-demo {
        border-style: solid;
    }
    .sv-demo-chip {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 9px;
        border-radius: 999px;
        font-size: 0.72rem;
        font-weight: 800;
        background: #e8f6ee;
        color: #0f6d44;
        border: 1px solid #a7d8ba;
    }
    .visit-summary-card span {
        color: #416551;
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        min-width: 0;
        overflow-wrap: anywhere;
    }
    .visit-summary-card strong {
        color: #063A1C;
        font-size: 28px;
        line-height: 1;
        min-width: 1.1em;
        text-align: right;
    }
    .visit-summary-icon {
        width: 40px;
        height: 40px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #0f6d44;
        background: #e8f6ee;
        flex: 0 0 40px;
    }
    .asm-followup-style-layout {
        border: 1px solid #cfe8da;
        border-radius: 22px;
        background: linear-gradient(135deg, #ffffff 0%, #f4fbf7 100%);
        box-shadow: 0 22px 50px rgba(15, 109, 68, 0.08);
        padding: 18px;
        display: grid;
        grid-template-columns: minmax(250px, 300px) minmax(0, 1fr);
        gap: 18px;
    }
    .asm-followup-style-layout .visit-summary-strip {
        grid-template-columns: 1fr;
        gap: 12px;
        margin-bottom: 0;
        align-self: start;
        align-content: start;
        grid-auto-rows: minmax(96px, auto);
    }
    .asm-followup-work-area {
        min-width: 0;
        border: 1px solid #cfe8da;
        border-radius: 18px;
        background: rgba(255, 255, 255, 0.72);
        overflow: hidden;
    }
    .asm-followup-work-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 16px 18px;
        border-bottom: 1px solid #cfe8da;
    }
    .asm-followup-work-head h2 {
        margin: 0;
        color: #063A1C;
        font-size: 1.15rem;
        font-weight: 900;
    }
    .asm-followup-work-head p {
        margin: 4px 0 0;
        color: #4c6757;
        font-size: 0.85rem;
        font-weight: 600;
    }
    .asm-followup-work-body {
        padding: 16px;
    }
    .asm-followup-work-body #visitsContainer,
    .asm-followup-work-body #visitsContainer.list-view {
        grid-template-columns: 1fr;
        gap: 0;
        align-items: stretch;
    }
    .asm-followup-work-body .sv-card--leadrow {
        border-radius: 0;
        border-width: 0 0 1px 4px;
        box-shadow: none;
        background: #ffffff;
        display: grid;
        grid-template-columns: minmax(230px, 0.85fr) minmax(320px, 1.2fr) minmax(190px, 240px);
        gap: 16px;
        align-items: center;
        padding: 16px 18px;
        min-height: 142px;
    }
    .asm-followup-work-body .sv-card--leadrow:first-child {
        border-top-left-radius: 16px;
        border-top-right-radius: 16px;
    }
    .asm-followup-work-body .sv-card--leadrow:last-child {
        border-bottom: 0;
        border-bottom-left-radius: 16px;
        border-bottom-right-radius: 16px;
    }
    .asm-followup-work-body .sv-card--leadrow:hover {
        box-shadow: inset 0 0 0 1px #a7d8ba;
        transform: none;
    }
    .sv-card__leadrow-main {
        min-width: 0;
        display: grid;
        gap: 8px;
    }
    .sv-card__leadrow-actions {
        display: grid;
        gap: 8px;
        align-self: stretch;
        align-content: center;
    }
    .sv-card__leadrow-actions .sv-card__actions {
        margin: 0;
        padding: 0;
        grid-template-columns: 1fr;
        gap: 8px;
    }
    .sv-card__leadrow-actions .sv-btn {
        min-height: 42px;
    }
    @media (max-width: 1024px) {
        .asm-followup-work-body .sv-card--leadrow {
            grid-template-columns: 1fr;
            gap: 12px;
        }
        .sv-card__leadrow-actions {
            align-self: auto;
        }
    }
    .request-kyc-modal {
        width: min(1460px, 96vw);
        max-width: 96vw;
        max-height: 96vh;
        overflow-y: auto;
    }
    .request-kyc-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 18px;
    }
    .request-kyc-head h3 {
        margin: 0;
        font-size: 22px;
        font-weight: 800;
        color: #133025;
    }
    .request-kyc-head p {
        margin: 6px 0 0;
        color: #60786b;
    }
    .request-kyc-note {
        border: 1px solid #dcebe0;
        background: #f7fcf8;
        color: #426254;
        border-radius: 14px;
        padding: 14px 16px;
        margin-bottom: 14px;
        font-size: 0.95rem;
    }
    .request-kyc-note.is-danger {
        border-color: #fecaca;
        background: #fff1f2;
        color: #9f1239;
    }
    .request-kyc-existing-shell {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
        margin-bottom: 16px;
    }
    .request-kyc-existing-group {
        border: 1px solid #e0ebe4;
        background: #fbfdfb;
        border-radius: 16px;
        padding: 14px;
    }
    .request-kyc-existing-title {
        font-size: 0.78rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #315342;
        margin-bottom: 10px;
    }
    .request-kyc-existing-grid {
        display: grid;
        gap: 10px;
    }
    .request-kyc-file-card {
        border: 1px solid #d9e8df;
        border-radius: 14px;
        overflow: hidden;
        background: #fff;
    }
    .request-kyc-file-card img {
        width: 100%;
        height: 160px;
        object-fit: contain;
        background: #f2f7f3;
        display: block;
    }
    .request-kyc-file-card-body {
        padding: 10px 12px 12px;
    }
    .request-kyc-file-link {
        color: #0f6a45;
        font-weight: 700;
        text-decoration: none;
    }
    .request-kyc-form-grid {
        display: grid;
        gap: 16px;
    }
    .request-kyc-section {
        border: 1px solid #d8e7de;
        background: linear-gradient(180deg, #f7fbf8 0%, #f4faf6 100%);
        border-radius: 18px;
        padding: 18px;
    }
    .request-kyc-section-title {
        font-size: 1rem;
        font-weight: 800;
        color: #173427;
        margin-bottom: 12px;
    }
    .request-kyc-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px 16px;
    }
    .request-kyc-grid.form-structured {
        grid-template-columns: 1fr;
        gap: 18px;
    }
    .request-kyc-field.full {
        grid-column: 1 / -1;
    }
    .request-kyc-row {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px 16px;
    }
    .request-kyc-row.cols-4 {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
    .request-kyc-group {
        display: grid;
        gap: 10px;
        padding: 14px;
        border: 1px solid #dbe7df;
        border-radius: 16px;
        background: linear-gradient(180deg, #fbfdfc 0%, #f5faf7 100%);
    }
    .request-kyc-group-title {
        font-size: 0.8rem;
        font-weight: 800;
        color: #325342;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }
    .request-kyc-check-grid.inline-chips {
        gap: 8px;
    }
    .request-kyc-check-grid.inline-chips .request-kyc-check-item {
        min-height: 42px;
        border-radius: 999px;
        padding: 10px 14px;
    }
    .request-kyc-field label {
        display: block;
        font-size: 0.84rem;
        font-weight: 700;
        color: #325342;
        margin-bottom: 8px;
    }
    .request-kyc-input,
    .request-kyc-select,
    .request-kyc-textarea,
    .request-kyc-file {
        width: 100%;
        min-height: 48px;
        padding: 12px 14px;
        border: 1px solid #d5e4da;
        border-radius: 14px;
        background: #fff;
        color: #173427;
        font-size: 0.95rem;
    }
    .request-kyc-textarea {
        min-height: 110px;
        resize: vertical;
    }
    .request-kyc-input:focus,
    .request-kyc-select:focus,
    .request-kyc-textarea:focus,
    .request-kyc-file:focus {
        outline: none;
        border-color: #1d6a49;
        box-shadow: 0 0 0 4px rgba(29, 106, 73, 0.12);
    }
    .request-kyc-check-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }
    .request-kyc-check-item {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 12px;
        border: 1px solid #d5e4da;
        border-radius: 12px;
        background: #fff;
        color: #173427;
        font-weight: 600;
    }
    .request-kyc-help {
        margin-top: 6px;
        font-size: 0.78rem;
        color: #6b7f73;
    }
    .request-kyc-selected-files {
        display: grid;
        gap: 8px;
        margin-top: 10px;
    }
    .request-kyc-selected-file {
        border: 1px dashed #c6dace;
        border-radius: 12px;
        padding: 10px 12px;
        background: #fdfefd;
        color: #305141;
        font-size: 0.86rem;
        font-weight: 600;
    }
    .request-kyc-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 18px;
    }
    #requestCloserModal { z-index: 2200; }
    .request-kyc-actions .btn[hidden] { display: none !important; }
    .request-kyc-stepper {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
        margin: 14px 0 6px;
    }
    .request-kyc-stepper[hidden] { display: none; }
    .request-kyc-step-pill {
        border: 1px solid #d8e7de;
        background: #f7fbf8;
        border-radius: 14px;
        padding: 12px 14px;
        display: grid;
        gap: 2px;
        text-align: left;
        cursor: pointer;
        transition: border-color 0.18s ease, background 0.18s ease, box-shadow 0.18s ease;
    }
    .request-kyc-step-pill.is-active {
        border-color: #1d6a49;
        background: #eef8f2;
        box-shadow: 0 0 0 3px rgba(29, 106, 73, 0.10);
    }
    .request-kyc-step-pill-index {
        font-size: 0.7rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #6b7f73;
    }
    .request-kyc-step-pill-label {
        font-size: 0.86rem;
        font-weight: 800;
        color: #173427;
        line-height: 1.3;
    }
    .request-kyc-step-caption {
        font-size: 0.82rem;
        color: #6b7f73;
        margin-bottom: 10px;
    }
    .request-kyc-step-panel[hidden] { display: none; }
    .request-kyc-step-panel {
        width: 100%;
    }
    .request-kyc-form-grid,
    .request-kyc-section {
        width: 100%;
    }
    .request-kyc-combo {
        display: grid;
        grid-template-columns: 150px minmax(0, 1fr);
        gap: 10px;
    }
    @media (max-width: 900px) {
        .request-kyc-existing-shell,
        .request-kyc-grid,
        .request-kyc-stepper,
        .request-kyc-combo,
        .request-kyc-row,
        .request-kyc-row.cols-4 {
            grid-template-columns: 1fr;
        }
        .request-kyc-modal {
            width: 100%;
            max-width: 100%;
            max-height: calc(100dvh - 12px - env(safe-area-inset-bottom, 0px));
            margin: auto 6px 0;
            padding-bottom: 0;
        }
        .request-kyc-actions {
            position: sticky;
            bottom: 0;
            z-index: 5;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            padding: 10px 0 calc(10px + env(safe-area-inset-bottom, 0px));
            background: #fff;
            border-top: 1px solid #dcebe0;
        }
        .request-kyc-actions .btn { width: 100%; }
    }
    /* ====== Site Visit Cards — professional CRM design ====== */
    #visitsContainer {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
        align-items: stretch;
    }
    #visitsContainer.list-view {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }
    @media (min-width: 1600px) {
        #visitsContainer.list-view { grid-template-columns: 1fr; max-width: 1100px; }
    }

    .sv-card {
        background: linear-gradient(180deg, #ffffff 0%, #f8fcff 100%);
        border: 1px solid #cfe8da;
        border-left: 4px solid #0f6d44;
        border-radius: 18px;
        padding: 14px;
        display: flex;
        flex-direction: column;
        gap: 10px;
        min-height: 100%;
        box-shadow: 0 16px 38px rgba(15, 109, 68, 0.07);
        transition: box-shadow .18s ease, border-color .18s ease, transform .18s ease;
        position: relative;
    }
    .sv-card:hover {
        box-shadow: 0 22px 46px rgba(15, 109, 68, 0.11);
        border-color: #a7d8ba;
    }
    .sv-card[data-status="cancelled"] { background: #fdfafa; }

    .sv-card__topstrip { display: flex; flex-wrap: wrap; gap: 5px; }
    .sv-status {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 8px;
        border-radius: 6px;
        font-size: 10.5px;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        line-height: 1.2;
    }
    .sv-status i { font-size: 9.5px; }
    .sv-status--scheduled { background: #dbeafe; color: #1d4ed8; }
    .sv-status--completed { background: #dcfce7; color: #166534; }
    .sv-status--cancelled { background: #fee2e2; color: #991b1b; }
    .sv-status--pending { background: #fef3c7; color: #92400e; }
    .sv-status--verified { background: #d1fae5; color: #065f46; }
    .sv-status--rejected { background: #fee2e2; color: #b91c1c; }
    .sv-status--awaiting { background: #fff7ed; color: #c2410c; }
    .sv-status--closer { background: #ede9fe; color: #5b21b6; }

    .sv-card__head { display: flex; align-items: center; gap: 10px; }
    .sv-card__avatar {
        flex: none;
        width: 38px; height: 38px;
        border-radius: 10px;
        display: grid; place-items: center;
        background: linear-gradient(135deg, #0f6d44 0%, #063A1C 100%);
        color: #ffffff;
        font-weight: 800;
        font-size: 0.82rem;
        border: 1px solid #0f6d44;
    }
    .sv-card__identity { min-width: 0; flex: 1; }
    .sv-card__name {
        font-size: 0.98rem;
        font-weight: 700;
        color: #063A1C;
        letter-spacing: -0.01em;
        line-height: 1.2;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .sv-card__phone {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        color: #4b5563;
        font-size: 0.76rem;
        font-weight: 600;
        text-decoration: none;
        margin-top: 1px;
    }
    .sv-card__phone:hover { color: #0f6d44; }
    .sv-card__phone i { font-size: 10px; color: #0f6d44; }
    .sv-card__more {
        flex: none;
        width: 30px; height: 30px;
        border-radius: 8px;
        border: 1px solid #e4ebe7;
        background: #fff;
        color: #6b7280;
        cursor: pointer;
        transition: all .15s;
        display: grid; place-items: center;
    }
    .sv-card__more:hover { background: #f6f9f7; color: #111; border-color: #d3dedb; }

    .sv-card__meta {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 6px 10px;
        padding: 9px 10px;
        margin: 0;
        background: #f7fbff;
        border: 1px solid #dcecf6;
        border-radius: 10px;
    }
    .sv-meta {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        min-width: 0;
        font-size: 0.77rem;
        color: #374151;
        font-weight: 600;
    }
    .sv-meta i { color: #0f6d44; width: 12px; text-align: center; font-size: 0.8rem; flex: none; }
    .sv-meta span {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .sv-card__owner { display: flex; flex-wrap: wrap; gap: 5px; }
    .sv-chip {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 9px;
        border-radius: 999px;
        font-size: 0.72rem;
        font-weight: 700;
        background: #e8f6ee;
        color: #0f6d44;
        border: 1px solid #a7d8ba;
    }
    .sv-chip i { font-size: 9.5px; }
    .sv-chip--warn { background: #fff7ed; color: #c2410c; border-color: #fed7aa; }
    .sv-chip--danger { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
    .sv-chip--neutral { background: #f3f4f6; color: #4b5563; border-color: #e5e7eb; }

    .sv-card__remark {
        margin: 0;
        padding: 9px 11px;
        background: #f7fbff;
        border: 1px solid #dcecf6;
        border-left: 3px solid #0f6d44;
        border-radius: 10px;
        font-size: 0.815rem;
        color: #374151;
        line-height: 1.45;
    }
    .sv-card__remark-label {
        font-size: 0.62rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: #8a9790;
        margin-bottom: 3px;
    }
    .sv-rejection-box {
        margin: 0;
        padding: 10px 12px;
        background: #fff1f2;
        border: 1px solid #fecdd3;
        border-left: 3px solid #e11d48;
        border-radius: 10px;
        color: #881337;
    }
    .sv-rejection-box__head {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        flex-wrap: wrap;
        font-size: 0.68rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        margin-bottom: 4px;
    }
    .sv-rejection-box p {
        margin: 0;
        font-size: 0.82rem;
        line-height: 1.45;
    }

    .sv-card__actions {
        margin-top: auto;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 6px;
        padding-top: 2px;
    }
    .sv-card__actions--single { grid-template-columns: 1fr; }
    .sv-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: 8px 10px;
        border-radius: 9px;
        font-size: 0.78rem;
        font-weight: 700;
        text-decoration: none;
        cursor: pointer;
        border: 1px solid transparent;
        transition: background .15s, color .15s, border-color .15s;
        white-space: nowrap;
        min-height: 36px;
        box-sizing: border-box;
    }
    .sv-btn i { font-size: 0.78rem; }
    .sv-btn--ghost {
        background: #e8f6ee;
        color: #0f6d44;
        border-color: #a7d8ba;
    }
    .sv-btn--ghost:hover { background: #dff0fa; }
    .sv-btn--primary {
        background: linear-gradient(135deg, #0f6d44 0%, #063A1C 100%);
        color: #fff;
        box-shadow: 0 4px 10px rgba(15, 109, 68, 0.22);
    }
    .sv-btn--primary:hover { filter: brightness(1.08); color: #fff; }
    .sv-btn--outline {
        background: #fff;
        color: #0b2e24;
        border-color: #dbe4df;
    }
    .sv-btn--outline:hover { background: #f6f9f7; }
    .sv-btn--action {
        width: 100%;
        background: linear-gradient(135deg, #0f6d44 0%, #063A1C 100%);
        color: #fff;
        box-shadow: 0 6px 14px rgba(15, 109, 68, 0.22);
        min-height: 40px;
    }
    .sv-btn--action:hover { filter: brightness(1.08); color: #fff; }

    .sv-card__actionmenu { position: relative; }
    .sv-card__actionfull { width: 100%; }
    .sv-card__actiondropdown--full {
        left: 0;
        right: 0;
        min-width: 100%;
    }
    .card-action-dropdown, .sv-card__actiondropdown {
        position: absolute;
        top: calc(100% + 6px);
        right: 0;
        z-index: 25;
        display: none;
        flex-direction: column;
        gap: 2px;
        padding: 6px;
        border-radius: 12px;
        background: #fff;
        border: 1px solid #e4ecea;
        box-shadow: 0 16px 32px rgba(15, 109, 68, 0.14);
        min-width: 220px;
    }
    .card-action-dropdown.show, .sv-card__actiondropdown.show { display: flex; }
    .sv-dropitem {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 9px 10px;
        border-radius: 8px;
        font-size: 0.82rem;
        font-weight: 600;
        color: #111827;
        cursor: pointer;
        border: none;
        background: transparent;
        text-align: left;
        text-decoration: none;
        width: 100%;
    }
    .sv-dropitem:hover { background: #f3f6f4; }
    .sv-dropitem i { width: 14px; text-align: center; color: #0f6d44; flex: none; }
    .sv-dropitem.sv-danger { color: #b91c1c; }
    .sv-dropitem.sv-danger i { color: #b91c1c; }
    .sv-dropitem.sv-warn { color: #c2410c; }
    .sv-dropitem.sv-warn i { color: #c2410c; }
    .sv-dropitem.sv-primary i { color: #1d4ed8; }
    .badge {
        display: inline-flex;
        align-items: center;
        padding: 6px 12px;
        border-radius: 999px;
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 0.01em;
    }
    .badge-scheduled {
        background: #dcfce7;
        color: #166534;
    }
    .badge-completed {
        background: #d1fae5;
        color: #065f46;
    }
    .badge-cancelled {
        background: #fee2e2;
        color: #991b1b;
    }
    .badge-pending {
        background: #fef3c7;
        color: #92400e;
    }
    .badge-verified {
        background: #d1fae5;
        color: #065f46;
    }
    .badge-closer-pending {
        background: #ecfdf3;
        color: #166534;
    }
    .badge-closer-verified {
        background: #10b981;
        color: white;
    }
    .badge-awaiting {
        background: #fff7ed;
        color: #c2410c;
    }
    .badge-closing-pending {
        background: #ecfdf3;
        color: #166534;
    }
    .badge-closing-rejected {
        background: #fee2e2;
        color: #b91c1c;
    }
    .btn {
        padding: 10px 14px;
        border: none;
        border-radius: 12px;
        cursor: pointer;
        font-size: 0.88rem;
        font-weight: 700;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    .btn-primary {
        background: linear-gradient(135deg, #0f6d44 0%, #063A1C 100%);
        color: white;
        box-shadow: 0 10px 22px rgba(15, 109, 68, 0.22);
    }
    .btn-primary:hover {
        background: linear-gradient(135deg, #00669e 0%, #004d78 100%);
        transform: translateY(-1px);
        box-shadow: 0 14px 28px rgba(15, 109, 68, 0.28);
    }
    .btn-call {
        background: #e8f6ee;
        color: #0f6d44;
        border: 1px solid #a7d8ba;
        box-shadow: none;
    }
    .btn-call:hover {
        background: #dff0fa;
        transform: translateY(-1px);
        box-shadow: 0 10px 20px rgba(15, 109, 68, 0.12);
    }
    .btn-success {
        background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
        color: white;
        box-shadow: 0 10px 22px rgba(22, 163, 74, 0.18);
    }
    .btn-danger {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white;
    }
    .btn-warning {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: white;
        box-shadow: 0 10px 22px rgba(245, 158, 11, 0.18);
    }
    .view-toggle-group {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: #fff;
        padding: 4px;
        border-radius: 12px;
        box-shadow: 0 8px 18px rgba(16, 24, 20, 0.07);
        border: 1px solid #e4e0d7;
    }
    .asm-page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        gap: 12px;
        flex-wrap: wrap;
    }
    .asm-page-title {
        font-size: 1.85rem;
        font-weight: 800;
        color: #063A1C;
        letter-spacing: -0.02em;
        margin: 0;
    }
    .asm-page-toolbar {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    .view-toggle-btn {
        border: none;
        background: transparent;
        color: #6b7280;
        font-size: 0.78rem;
        font-weight: 700;
        border-radius: 9px;
        padding: 7px 10px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .view-toggle-btn.active {
        background: linear-gradient(135deg, #0f6d44 0%, #063A1C 100%);
        color: #fff;
        box-shadow: 0 10px 18px rgba(15, 109, 68, 0.18);
    }
    /* list-view: wider 2-col at md, single-col only on very large */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #9ca3af;
    }
    .filters {
        background: white;
        padding: 16px;
        border-radius: 16px;
        margin-bottom: 20px;
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        align-items: center;
    }
    .filters .filter-select,
    .filters .filter-btn {
        flex: 1;
        min-width: 0;
        box-sizing: border-box;
    }
    .filter-select,
    .filters input[type="date"] {
        padding: 10px 12px;
        border: 1px solid #d5dfeb;
        border-radius: 12px;
        font-size: 14px;
    }
    .mobile-text {
        display: none;
    }
    .desktop-text {
        display: inline;
    }
    @media (max-width: 767px) {
        .empty-state {
            display: none !important;
        }
    }
    @media (min-width: 769px) and (max-width: 1280px) {
        #visitsContainer {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
    @media (max-width: 768px) {
        .asm-inline-filter-panel {
            padding: 14px;
            border-radius: 14px;
        }
        .asm-inline-filters-row {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 8px;
        }
        .asm-inline-filters-row select,
        .asm-inline-filters-row input {
            min-height: 44px;
            padding: 0 12px;
            font-size: 12px;
        }
        .asm-inline-filter-date {
            display: none !important;
        }
        .asm-inline-filter-date.show {
            display: block !important;
            grid-column: 1 / -1;
        }
        .asm-page-header {
            flex-direction: column;
            align-items: stretch;
            gap: 10px;
            margin-bottom: 14px;
        }
        .asm-personal-desk {
            align-items: stretch;
            flex-direction: column;
            padding: 18px;
            border-radius: 18px;
            margin-bottom: 14px;
        }
        .asm-personal-desk-title {
            font-size: 1.55rem;
        }
        .asm-desk-date {
            width: 100%;
            justify-content: center;
        }
        .visit-summary-strip {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
        }
        .asm-followup-style-layout {
            grid-template-columns: 1fr;
            padding: 12px;
            border-radius: 18px;
        }
        .asm-followup-style-layout .visit-summary-strip {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .asm-followup-work-head {
            padding: 14px;
        }
        .asm-followup-work-body {
            padding: 12px;
        }
        .asm-followup-work-body #visitsContainer,
        .asm-followup-work-body #visitsContainer.list-view {
            grid-template-columns: 1fr;
        }
        .visit-summary-card {
            padding: 12px;
            border-radius: 14px;
            min-height: 82px;
        }
        .visit-summary-card strong {
            font-size: 22px;
        }
        .visit-summary-card span {
            font-size: 10px;
        }
        .asm-page-title {
            text-align: center;
            font-size: 1.75rem;
        }
        .asm-page-toolbar {
            width: 100%;
            flex-direction: column;
            align-items: stretch;
            gap: 10px;
        }
        .view-toggle-group {
            display: none;
        }
        #visitsContainer,
        #visitsContainer.list-view {
            grid-template-columns: 1fr;
            gap: 12px;
            max-width: 480px;
            margin: 0 auto;
        }
        .sv-card { padding: 12px; }
        .sv-card__meta { grid-template-columns: 1fr; }
        .sv-card__actions { grid-template-columns: 1fr 1fr; }
        .sv-card__actions--single { grid-template-columns: 1fr; }
        .card-action-dropdown,
        .sv-card__actiondropdown {
            position: static;
            min-width: 100%;
            right: auto;
            margin-top: 6px;
        }
        .view-toggle-btn { justify-content: center; }
        .filters {
            flex-direction: row;
            flex-wrap: wrap;
            gap: 10px;
            border: 1px solid #dbe6f3;
            border-radius: 16px;
            background: linear-gradient(180deg, #ffffff 0%, #f6faff 100%);
            box-shadow: 0 8px 24px rgba(30, 64, 175, 0.08);
        }
        .filters .filter-select,
        .filters .filter-btn {
            width: calc(50% - 5px);
            flex: 1 1 calc(50% - 5px);
            padding: 10px 12px;
            font-size: 13px;
            box-sizing: border-box;
        }
        .filters .filter-btn.btn.btn-primary {
            display: flex !important;
            width: 100%;
            flex: 1 1 100%;
            min-height: 44px;
        }
        .filters .filter-closer {
            width: 100%;
            flex: 1 1 100%;
        }
        .mobile-text {
            display: inline;
        }
        .desktop-text {
            display: inline;
        }
        .modal-content {
            width: 95% !important;
            max-width: 95% !important;
            padding: 16px !important;
        }
    }
</style>
@endpush

  @section('content')
  @php($showSeniorManagerUserFilter = auth()->check() && auth()->user()->isSeniorManager())
  @include('sales-manager.partials.pipeline-tabs')
  <div class="mb-6">
    <section class="asm-personal-desk">
        <div class="asm-personal-desk-copy">
            <p class="asm-personal-desk-kicker">Site Visit Desk</p>
            <h1 class="asm-personal-desk-title">Site visit execution for {{ auth()->user()->name ?? 'your desk' }}</h1>
            <p class="asm-personal-desk-subtitle">Track scheduled visits, field proof, verification, and closer movement from one focused queue.</p>
        </div>
        <div class="asm-desk-date">
            <strong>{{ now()->format('h:i A') }}</strong>
            <span>{{ now()->format('d M Y') }}</span>
        </div>
    </section>

    <div class="asm-page-header">
        <div class="asm-page-toolbar">
            <div class="view-toggle-group" aria-label="Site visit view toggle">
                <button type="button" class="view-toggle-btn" data-view="list" onclick="setVisitsView('list')">
                    <i class="fas fa-list"></i> List
                </button>
                <button type="button" class="view-toggle-btn" data-view="card" onclick="setVisitsView('card')">
                    <i class="fas fa-grip"></i> Cards
                </button>
            </div>
        </div>
    </div>

    <div class="asm-inline-filter-panel">
        <div class="asm-inline-filters-row">
            <select id="statusFilter" onchange="loadSiteVisits()">
                <option value="">All Status</option>
                <option value="scheduled">Scheduled</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
            </select>
            <select id="verificationFilter" onchange="loadSiteVisits()">
                <option value="">All Verification</option>
                <option value="pending">Pending</option>
                <option value="verified">Verified</option>
                <option value="rejected">Rejected</option>
            </select>
            <select id="dateFilter" onchange="toggleCustomDate(); loadSiteVisits();">
                <option value="">All Dates</option>
                <option value="today" selected>Today</option>
                <option value="this_week">This Week</option>
                <option value="this_month">This Month</option>
                <option value="this_year">This Year</option>
                <option value="custom">Custom Date</option>
            </select>
            @if($showSeniorManagerUserFilter)
                <select id="assignedToFilter" onchange="persistSiteVisitFilter('assigned_to', this.value); loadSiteVisits();">
                    <option value="">All Users</option>
                    @foreach(($filterUsers ?? collect()) as $filterUser)
                        <option value="{{ $filterUser->id }}">{{ $filterUser->name }}</option>
                    @endforeach
                </select>
            @endif
            <input type="date" id="dateFrom" class="asm-inline-filter-date" onchange="loadSiteVisits()">
            <input type="date" id="dateTo" class="asm-inline-filter-date" onchange="loadSiteVisits()">
        </div>
    </div>

    <div class="asm-followup-style-layout">
        <aside class="visit-summary-strip" id="visitSummaryStrip">
            <button type="button" class="visit-summary-card active" data-visit-summary="today" onclick="applyVisitSummaryFilter('today')">
                <div>
                    <span>Today Visits</span>
                    <strong id="visitSummaryToday">0</strong>
                </div>
                <div class="visit-summary-icon"><i class="fas fa-location-dot"></i></div>
            </button>
            <button type="button" class="visit-summary-card" data-visit-summary="future" onclick="applyVisitSummaryFilter('future')">
                <div>
                    <span>Future Scheduled</span>
                    <strong id="visitSummaryScheduled">0</strong>
                </div>
                <div class="visit-summary-icon"><i class="fas fa-calendar-check"></i></div>
            </button>
            <button type="button" class="visit-summary-card" data-visit-summary="completed" onclick="applyVisitSummaryFilter('completed')">
                <div>
                    <span>Completed Visits</span>
                    <strong id="visitSummaryCompleted">0</strong>
                </div>
                <div class="visit-summary-icon"><i class="fas fa-circle-check"></i></div>
            </button>
            <button type="button" class="visit-summary-card" data-visit-summary="closer" onclick="applyVisitSummaryFilter('closer')">
                <div>
                    <span>Closer Ready</span>
                    <strong id="visitSummaryCloser">0</strong>
                </div>
                <div class="visit-summary-icon"><i class="fas fa-key"></i></div>
            </button>
        </aside>

        <section class="asm-followup-work-area">
            <div class="asm-followup-work-head">
                <div>
                    <h2>Site Visit Queue</h2>
                    <p>Personal visit execution, customer proof, verification, and closer handoff.</p>
                </div>
            </div>
            <div class="asm-followup-work-body">
                <div id="visitsContainer">
                    <div class="empty-state">
                        <i class="fas fa-spinner fa-spin"></i>
                        <p>Loading site visits...</p>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <div id="markDeadModal" class="modal mark-dead-modal">
        <div class="modal-content mark-dead-modal-card">
            <div class="mark-dead-modal-head">
                <div>
                    <h3>Mark as Dead</h3>
                    <p>This will remove the lead from the active queue and move it to Other Leads.</p>
                </div>
                <button type="button" class="mark-dead-close" onclick="closeMarkDeadModal()" aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="mark-dead-modal-alert">
                <i class="fas fa-triangle-exclamation"></i>
                <div>
                    <strong>Lead and linked site visit will be marked dead.</strong>
                    <span>Use a clear reason so CRM/Admin can understand why this moved out of the active queue.</span>
                </div>
            </div>

            <div class="form-group mark-dead-form-group">
                <label for="deadReason">Reason <span style="color: #ef4444;">*</span></label>
                <textarea id="deadReason" rows="5" placeholder="Enter reason for marking as dead..." required></textarea>
            </div>

            <div class="mark-dead-modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeMarkDeadModal()">Cancel</button>
                <button type="button" id="markDeadSubmitBtn" class="btn btn-danger" onclick="submitMarkDead()">Mark as Dead</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const ALLOW_PRIVILEGED_PAST_SCHEDULING = @json((bool) (auth()->user()?->isAdmin() || auth()->user()?->isCrm()));
    const API_BASE_URL = '{{ url("/api/sales-manager") }}';
    const SITE_VISITS_DEFAULT_KYC_SCHEMA = @json($kycFormSchema ?? ['sections' => []]);
    const DEFAULT_VISIT_PROJECTS = ['Eldeco La Vida', 'Omaxe Heights', 'Godrej Reserve', 'ATS Pristine', 'Shalimar Mannat', 'ACE Divino'];
    const ASM_SECTION_VIEW_PREFERENCES = @json($sectionViewPreferences ?? []);
    const ASM_SECTION_VIEW_SAVE_URL = @json(route('sales-manager.settings.update'));
    const SITE_VISITS_FILTER_STORAGE_KEY = 'asm_site_visits_filters';

    function getAsmPreferredView(sectionKey, fallbackView) {
        const preferred = ASM_SECTION_VIEW_PREFERENCES?.[sectionKey];
        return preferred === 'list' || preferred === 'card' ? preferred : fallbackView;
    }

    async function persistAsmSectionViewPreference(sectionKey, view) {
        try {
            ASM_SECTION_VIEW_PREFERENCES[sectionKey] = view;
            await fetch(ASM_SECTION_VIEW_SAVE_URL, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    section_view_preferences: {
                        [sectionKey]: view
                    }
                })
            });
        } catch (error) {
            console.error('Failed to persist section view preference:', error);
        }
    }
    
    function getToken() {
        return window.getManagerApiToken ? window.getManagerApiToken() : '{{ session("api_token") }}';
    }

    function getStoredSiteVisitFilters() {
        try {
            return JSON.parse(localStorage.getItem(SITE_VISITS_FILTER_STORAGE_KEY) || '{}') || {};
        } catch (error) {
            return {};
        }
    }

    function persistSiteVisitFilter(key, value) {
        try {
            const current = getStoredSiteVisitFilters();
            current[key] = value || '';
            localStorage.setItem(SITE_VISITS_FILTER_STORAGE_KEY, JSON.stringify(current));
        } catch (error) {
            console.error('Failed to persist site visit filter', error);
        }
    }

    function formatPhoneForDialer(phoneNumber) {
        const cleanPhone = String(phoneNumber || '').replace(/[^0-9]/g, '');
        if (cleanPhone.length < 10) {
            return '';
        }
        if (cleanPhone.length === 10) {
            return `+91${cleanPhone}`;
        }
        if (cleanPhone.length === 12 && cleanPhone.startsWith('91')) {
            return `+${cleanPhone}`;
        }
        if (cleanPhone.length === 11 && cleanPhone.startsWith('0')) {
            return `+91${cleanPhone.slice(1)}`;
        }
        return cleanPhone.startsWith('+') ? cleanPhone : `+${cleanPhone}`;
    }

    function callSiteVisitLead(phoneNumber) {
        const dialerPhone = formatPhoneForDialer(phoneNumber);
        if (!dialerPhone) {
            alert('Phone number not available');
            return;
        }
        window.location.href = `tel:${dialerPhone}`;
    }

    async function apiCall(endpoint, options = {}) {
        const token = getToken();
        if (!token) {
            window.location.href = '{{ route("login") }}';
            return null;
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        
        const defaultOptions = {
            headers: window.getManagerAuthHeaders
                ? window.getManagerAuthHeaders({
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                })
                : {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${token}`,
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
        };

        try {
            const response = await fetch(`${API_BASE_URL}${endpoint}`, {
                ...defaultOptions,
                ...options,
                headers: { ...defaultOptions.headers, ...options.headers },
                credentials: 'same-origin',
            });

            if (response.status === 401 || response.status === 403) {
                if (window.handleManagerAuthFailure) {
                    window.handleManagerAuthFailure('site visits');
                } else {
                    window.location.href = '{{ route("login") }}';
                }
                return null;
            }

            if (!response.ok) {
                const errorText = await response.text();
                try {
                    return JSON.parse(errorText);
                } catch (e) {
                    return { success: false, message: errorText };
                }
            }

            return await response.json();
        } catch (error) {
            console.error('API Call Error:', error);
            return { success: false, message: error.message };
        }
    }

    function toggleCustomDate() {
        const dateFilter = document.getElementById('dateFilter').value;
        const dateFromInput = document.getElementById('dateFrom');
        const dateToInput = document.getElementById('dateTo');
        if (dateFilter === 'custom') {
            dateFromInput?.classList.add('show');
            dateToInput?.classList.add('show');
        } else {
            dateFromInput?.classList.remove('show');
            dateToInput?.classList.remove('show');
        }
    }

    // Helper function to format budget from lead
    function formatBudget(budget) {
        if (!budget) return 'N/A';
        const budgetNum = parseFloat(budget);
        if (isNaN(budgetNum)) return budget;
        
        const budgetInLacs = budgetNum / 100000;
        if (budgetInLacs < 50) return 'Under 50 Lac';
        if (budgetInLacs < 100) return '50 Lac – 1 Cr';
        if (budgetInLacs < 200) return '1 Cr – 2 Cr';
        if (budgetInLacs < 300) return '2 Cr – 3 Cr';
        return 'Above 3 Cr';
    }

    function getVisitRemark(visit) {
        return visit.visit_notes || visit.notes || visit.feedback || visit.property_address || visit.project || 'No remark added yet.';
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function truncateText(value, maxLength) {
        const text = String(value || '');
        return text.length > maxLength ? text.slice(0, maxLength).trim() + '...' : text;
    }

    function setVisitsView(view, shouldPersist = true) {
        const container = document.getElementById('visitsContainer');
        if (!container) return;
        container.classList.toggle('list-view', view === 'list');
        document.querySelectorAll('.view-toggle-btn[data-view]').forEach((btn) => {
            btn.classList.toggle('active', btn.getAttribute('data-view') === view);
        });
        try {
            localStorage.setItem('asm_visits_view', view);
        } catch (e) {}
        if (shouldPersist) {
            persistAsmSectionViewPreference('site_visits', view);
        }
    }

    function getVisitSummaryCounts(list) {
        const todayIso = new Date().toISOString().slice(0, 10);
        const closerStatuses = ['draft', 'pending_crm', 'correction_required', 'rejected', 'approved', 'verified'];
        return (Array.isArray(list) ? list : []).reduce((acc, visit) => {
            const scheduledIso = visit.scheduled_at ? new Date(visit.scheduled_at).toISOString().slice(0, 10) : '';
            if (scheduledIso === todayIso) acc.today += 1;
            if (visit.status === 'scheduled') acc.scheduled += 1;
            if (visit.status === 'completed') acc.completed += 1;
            if (closerStatuses.includes(visit.closer_status || '')) acc.closer += 1;
            return acc;
        }, { today: 0, scheduled: 0, completed: 0, closer: 0 });
    }

    function updateVisitSummary(visits) {
        const currentCounts = getVisitSummaryCounts(visits);
        updateVisitSummaryFromCounts(currentCounts);
    }

    function updateVisitSummaryFromCounts(counts) {
        const setText = (id, value) => {
            const element = document.getElementById(id);
            if (element) element.textContent = value;
        };
        setText('visitSummaryToday', counts?.today || 0);
        setText('visitSummaryScheduled', counts?.scheduled || 0);
        setText('visitSummaryCompleted', counts?.completed || 0);
        setText('visitSummaryCloser', counts?.closer || 0);
    }

    async function loadVisitSummaryCounts(fallbackVisits = []) {
        try {
            const assignedTo = document.getElementById('assignedToFilter')?.value || '';
            let summaryUrl = '/site-visits?summary=1&active_queue=1';
            if (assignedTo) summaryUrl += `&assigned_to=${assignedTo}`;
            const response = await apiCall(summaryUrl);
            const summary = response?.summary || response;
            updateVisitSummaryFromCounts(summary || getVisitSummaryCounts(fallbackVisits));
        } catch (error) {
            console.warn('Error loading site visit summary:', error);
            updateVisitSummary(fallbackVisits);
        }
    }

    function setVisitSummaryActive(type) {
        document.querySelectorAll('[data-visit-summary]').forEach(card => {
            card.classList.toggle('active', card.getAttribute('data-visit-summary') === type);
        });
    }

    function applyVisitSummaryFilter(type) {
        const statusEl = document.getElementById('statusFilter');
        const verificationEl = document.getElementById('verificationFilter');
        const dateEl = document.getElementById('dateFilter');
        if (!statusEl || !verificationEl || !dateEl) return;

        statusEl.value = '';
        verificationEl.value = '';
        dateEl.value = '';

        if (type === 'today') {
            dateEl.value = 'today';
        } else if (type === 'future') {
            statusEl.value = 'scheduled';
        } else if (type === 'completed') {
            statusEl.value = 'completed';
        } else if (type === 'closer') {
            statusEl.value = 'completed';
            verificationEl.value = 'verified';
        }

        setVisitSummaryActive(type);
        toggleCustomDate();
        loadSiteVisits();
    }

    function buildDemoVisits(activeType, currentCount = 0) {
        return [];
    }

    async function loadSiteVisits() {
        const container = document.getElementById('visitsContainer');
        container.innerHTML = '<div class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>Loading...</p></div>';

        try {
            const status = document.getElementById('statusFilter').value;
            const verification = document.getElementById('verificationFilter').value;
            const assignedTo = document.getElementById('assignedToFilter')?.value || '';
            const dateFilter = document.getElementById('dateFilter').value;
            
            let url = '/site-visits?';
            if (status && status !== 'all') url += `status=${status}&`;
            if (!status || status === 'all') url += 'active_queue=1&';
            if (verification && verification !== 'all') url += `verification_status=${verification}&`;
            if (assignedTo) url += `assigned_to=${assignedTo}&`;
            if (dateFilter && dateFilter !== 'all') {
                url += `date_filter=${dateFilter}&`;
                if (dateFilter === 'custom') {
                    const dateFrom = document.getElementById('dateFrom').value;
                    const dateTo = document.getElementById('dateTo').value;
                    if (dateFrom) url += `date_from=${dateFrom}&`;
                    if (dateTo) url += `date_to=${dateTo}&`;
                }
            }

            const response = await apiCall(url);
            
            // Handle paginated response
            let visits = [];
            if (response) {
                if (Array.isArray(response.data)) {
                    visits = response.data;
                } else if (Array.isArray(response)) {
                    visits = response;
                } else if (response.visits && Array.isArray(response.visits)) {
                    visits = response.visits;
                }
            }
            
            console.log('Site Visits Response:', { response, visitsCount: visits.length, visits });
            const activeSummary = document.querySelector('[data-visit-summary].active')?.getAttribute('data-visit-summary') || '';
            Object.keys(siteVisitListById).forEach((key) => delete siteVisitListById[key]);
            visits.forEach((visit) => {
                if (visit && !visit._demo && visit.id) {
                    siteVisitListById[String(visit.id)] = visit;
                }
            });
            loadVisitSummaryCounts(visits);

            if (!visits || visits.length === 0) {
                // Hide empty state on mobile - show nothing
                container.innerHTML = '';
                return;
            }

            const html = visits.map(visit => {
                const statusClass = visit.status === 'completed' ? 'completed' : 
                                  visit.status === 'cancelled' ? 'cancelled' : 
                                  visit.verification_status === 'pending' && visit.status === 'completed' ? 'pending-verification' : '';
                
                const statusBadge = visit.status === 'scheduled' ? 'badge-scheduled' :
                                  visit.status === 'completed' ? 'badge-completed' :
                                  'badge-cancelled';
                
                const verificationBadge = visit.verification_status === 'verified' ? 'badge-verified' :
                                        visit.verification_status === 'pending' ? 'badge-pending' :
                                        visit.verification_status === 'rejected' ? 'badge-cancelled' : '';
                
                const closerBadge = (visit.closer_status === 'approved' || visit.closer_status === 'verified') ? 'badge-closer-verified' :
                                  (visit.closer_status === 'pending' || visit.closer_status === 'pending_crm') ? 'badge-closer-pending' : '';

                // Field fallback logic: site visit → lead → 'N/A'
                const customerName = visit.customer_name || (visit.lead && visit.lead.name) || visit.property_name || 'N/A';
                const phone = visit.phone || (visit.lead && visit.lead.phone) || 'N/A';
                const budget = visit.budget_range || (visit.lead && visit.lead.budget ? formatBudget(visit.lead.budget) : null) || 'N/A';
                const propertyType = visit.property_type || (visit.lead && visit.lead.property_type) || 'N/A';
                const project = visit.property_name || visit.project || (visit.lead && visit.lead.preferred_projects) || 'N/A';
                const ownerName = (visit.lead && visit.lead.active_assignments && visit.lead.active_assignments.length && visit.lead.active_assignments[0].assigned_to && visit.lead.active_assignments[0].assigned_to.name)
                    || (visit.lead && visit.lead.activeAssignments && visit.lead.activeAssignments.length && visit.lead.activeAssignments[0].assigned_to && visit.lead.activeAssignments[0].assigned_to.name)
                    || visit.assigned_to_name
                    || '';
                const remark = truncateText(getVisitRemark(visit), 120);
                const isScheduled = visit.status === 'scheduled';
                const isCompleted = visit.status === 'completed';
                const isPendingVerification = isCompleted && visit.verification_status === 'pending';
                const isVerified = visit.verification_status === 'verified';
                const isRejected = isCompleted && visit.verification_status === 'rejected';
                const hasCloserState = !!visit.closer_status;
                const hasClosingState = !!visit.closing_verification_status;
                const isCloserSection = ['closer', 'closed', 'closed_clients'].includes(activeSummary);

                const visitActionItems = [];

                if (!visit._demo && isScheduled) {
                    visitActionItems.push(`
                        <button class="sv-dropitem" onclick="closeAllSiteVisitActionMenus(); showCompleteSiteVisitModal(${visit.id});">
                            <i class="fas fa-check"></i>Mark Complete
                        </button>
                    `);
                    visitActionItems.push(`
                        <button class="sv-dropitem sv-warn" onclick="closeAllSiteVisitActionMenus(); showRescheduleSiteVisitModal(${visit.id});">
                            <i class="fas fa-calendar-plus"></i>Reschedule
                        </button>
                    `);
                    visitActionItems.push(`
                        <button class="sv-dropitem sv-danger" onclick="closeAllSiteVisitActionMenus(); showMarkDeadModal('site-visit', ${visit.id});">
                            <i class="fas fa-skull"></i>Mark as Dead
                        </button>
                    `);
                }

                if (!visit._demo && isVerified && !hasCloserState && !hasClosingState) {
                    visitActionItems.push(`
                        <button class="sv-dropitem sv-primary" onclick="closeAllSiteVisitActionMenus(); requestCloseFromVisit(${visit.id});">
                            <i class="fas fa-circle-check"></i>Mark as Closed
                        </button>
                    `);
                }

                if (!visit._demo && isCloserSection && ['draft', 'pending_crm', 'correction_required', 'rejected'].includes(visit.closer_status || '')) {
                    const closerLabel = visit.closer_status === 'draft'
                        ? 'Fill KYC Form'
                        : (visit.closer_status === 'pending_crm' ? 'Edit KYC Form' : 'Resubmit Closer Form');
                    visitActionItems.unshift(`
                        <button class="sv-dropitem sv-primary" onclick="closeAllSiteVisitActionMenus(); showRequestCloserModal(${visit.id});">
                            <i class="fas fa-id-card"></i>${closerLabel}
                        </button>
                    `);
                }

                if (!visit._demo && isCloserSection && ['approved', 'verified'].includes(visit.closer_status || '')) {
                    visitActionItems.unshift(`
                        <button class="sv-dropitem sv-primary" onclick="closeAllSiteVisitActionMenus(); showRequestCloserModal(${visit.id}, true);">
                            <i class="fas fa-eye"></i>View KYC
                        </button>
                    `);
                }

                if (!visit._demo && isCompleted) {
                    visitActionItems.push(`
                        <button class="sv-dropitem sv-warn" onclick="closeAllSiteVisitActionMenus(); showScheduleRevisitModal(${visit.id});">
                            <i class="fas fa-calendar-plus"></i>Schedule Revisit
                        </button>
                    `);
                    visitActionItems.push(`
                        <button class="sv-dropitem sv-danger" onclick="closeAllSiteVisitActionMenus(); showMarkDeadModal('site-visit', ${visit.id});">
                            <i class="fas fa-skull"></i>Mark as Dead
                        </button>
                    `);
                }

                if (!visit._demo && isRejected) {
                    visitActionItems.unshift(`
                        <button class="sv-dropitem sv-primary" onclick="closeAllSiteVisitActionMenus(); openResubmitSiteVisitModal(${visit.id});">
                            <i class="fas fa-rotate-right"></i>Resubmit
                        </button>
                    `);
                }

                if (!visit._demo && visit.lead_id) {
                    visitActionItems.push(`
                        <a href="/leads/${visit.lead_id}" target="_blank" class="sv-dropitem sv-primary" onclick="closeAllSiteVisitActionMenus()">
                            <i class="fas fa-eye"></i>View Detail
                        </a>
                    `);
                }

                // Initials for avatar
                const initials = String(customerName || '?').trim().split(/\s+/)
                    .map(p => p.charAt(0)).filter(Boolean).slice(0, 2).join('').toUpperCase() || '?';

                // Formatted scheduled date
                const scheduledAt = visit.scheduled_at
                    ? new Date(visit.scheduled_at).toLocaleString('en-IN', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
                    : 'Date TBD';

                // Status strip chips
                const stripChips = [];
                stripChips.push(`<span class="sv-status sv-status--${visit.status}"><i class="fas fa-circle" style="font-size:6px;"></i>${visit.status}</span>`);
                if (visit.verification_status) {
                    stripChips.push(`<span class="sv-status sv-status--${visit.verification_status}">${visit.verification_status}</span>`);
                }
                if (visit.closer_status) {
                    const closerToneMap = { approved: 'verified', verified: 'verified', pending: 'pending', pending_crm: 'pending', rejected: 'rejected' };
                    const closerTone = closerToneMap[visit.closer_status] || 'closer';
                    stripChips.push(`<span class="sv-status sv-status--${closerTone}">Closer: ${escapeHtml(visit.closer_status)}</span>`);
                }
                if (visit.closing_verification_status && visit.closing_verification_status !== visit.closer_status) {
                    const closingToneMap = { verified: 'verified', approved: 'verified', pending: 'pending', rejected: 'rejected' };
                    const closingTone = closingToneMap[visit.closing_verification_status] || 'pending';
                    stripChips.push(`<span class="sv-status sv-status--${closingTone}">Closing: ${escapeHtml(visit.closing_verification_status)}</span>`);
                }

                // Direct footer buttons (Call + Open Lead)
                const footerBtns = [];
                if (phone !== 'N/A') {
                    footerBtns.push(`<button type="button" class="sv-btn sv-btn--ghost" onclick="callSiteVisitLead('${escapeHtml(phone)}')"><i class="fas fa-phone"></i> Call</button>`);
                }
                if (!visit._demo && visit.lead_id) {
                    footerBtns.push(`<a href="/leads/${visit.lead_id}" class="sv-btn sv-btn--primary"><i class="fas fa-up-right-from-square"></i> Open Lead</a>`);
                }
                const actionsModClass = footerBtns.length === 1 ? 'sv-card__actions--single' : '';

                // Phone display
                const phoneHtml = phone !== 'N/A'
                    ? `<a class="sv-card__phone" href="tel:${escapeHtml(formatPhoneForDialer(phone) || phone)}"><i class="fas fa-phone"></i>${escapeHtml(phone)}</a>`
                    : `<span class="sv-card__phone" style="color:#9ca3af;"><i class="fas fa-phone-slash"></i>No phone</span>`;

                const remarkBlock = (remark && remark !== 'No remark added yet.')
                    ? `<blockquote class="sv-card__remark"><div class="sv-card__remark-label">Remark</div>${escapeHtml(remark)}</blockquote>`
                    : '';
                const rejectionReason = (visit.rejection_reason || '').trim();
                const resubmissionCount = Number(visit.resubmission_count || 0);
                const rejectionBlock = isRejected && rejectionReason ? `
                    <section class="sv-rejection-box">
                        <div class="sv-rejection-box__head">
                            <span><i class="fas fa-circle-exclamation"></i> Rejected Reason</span>
                            <span>Resubmits: ${resubmissionCount}</span>
                        </div>
                        <p>${escapeHtml(rejectionReason)}</p>
                    </section>
                ` : (isRejected ? `
                    <section class="sv-rejection-box">
                        <div class="sv-rejection-box__head">
                            <span><i class="fas fa-circle-exclamation"></i> Rejected</span>
                            <span>Resubmits: ${resubmissionCount}</span>
                        </div>
                    </section>
                ` : '');

                const ownerChips = [];
                if (ownerName) ownerChips.push(`<span class="sv-chip"><i class="fas fa-user"></i>${escapeHtml(ownerName)}</span>`);
                if (isPendingVerification) ownerChips.push(`<span class="sv-chip sv-chip--warn"><i class="fas fa-hourglass-half"></i>Awaiting Verification</span>`);

                // Full-width Action button (with dropdown)
                const actionBtnBlock = visitActionItems.length ? `
                    <div class="sv-card__actionmenu sv-card__actionfull" data-site-visit-action-menu="${visit.id}">
                        <button type="button" class="sv-btn sv-btn--action" onclick="toggleSiteVisitActionMenu(${visit.id}, event)">
                            <i class="fas fa-bolt"></i> Action
                        </button>
                        <div class="card-action-dropdown sv-card__actiondropdown sv-card__actiondropdown--full" id="siteVisitActionDropdown${visit.id}">
                            ${visitActionItems.join('')}
                        </div>
                    </div>
                ` : '';

                return `
                    <article class="sv-card sv-card--leadrow ${visit._demo ? 'is-demo' : ''}" data-status="${visit.status}">
                        <div class="sv-card__leadrow-main">
                            <header class="sv-card__head">
                                <div class="sv-card__avatar">${escapeHtml(initials)}</div>
                                <div class="sv-card__identity">
                                    <div class="sv-card__name">${escapeHtml(customerName)}</div>
                                    ${phoneHtml}
                                </div>
                            </header>
                            <div class="sv-card__topstrip">${stripChips.join('')}</div>
                            ${ownerChips.length ? `<div class="sv-card__owner">${ownerChips.join('')}</div>` : ''}
                            ${remarkBlock}
                            ${rejectionBlock}
                        </div>

                        <dl class="sv-card__meta">
                            <div class="sv-meta"><i class="fas fa-calendar"></i><span>${escapeHtml(scheduledAt)}</span></div>
                            ${project !== 'N/A' ? `<div class="sv-meta"><i class="fas fa-location-dot"></i><span>${escapeHtml(project)}</span></div>` : ''}
                            ${propertyType !== 'N/A' ? `<div class="sv-meta"><i class="fas fa-building"></i><span>${escapeHtml(propertyType)}</span></div>` : ''}
                            ${budget !== 'N/A' ? `<div class="sv-meta"><i class="fas fa-wallet"></i><span>${escapeHtml(budget)}</span></div>` : ''}
                        </dl>

                        <div class="sv-card__leadrow-actions">
                            ${footerBtns.length ? `<footer class="sv-card__actions ${actionsModClass}">${footerBtns.join('')}</footer>` : ''}
                            ${actionBtnBlock}
                        </div>
                    </article>
                `;
            }).join('');

            container.innerHTML = html;
        } catch (error) {
            console.error('Error loading site visits:', error);
            container.innerHTML = '<div class="empty-state"><i class="fas fa-exclamation-triangle"></i><p>Error loading site visits</p></div>';
        }
    }

    let currentSiteVisitId = null;
    let currentSiteVisitModalMode = 'complete';
    const siteVisitListById = {};
    const currentSiteVisitModalState = {
        existingProofPhotos: [],
    };

    function closeAllSiteVisitActionMenus() {
        document.querySelectorAll('.card-action-dropdown.show').forEach(menu => {
            menu.classList.remove('show');
        });
    }

    function toggleSiteVisitActionMenu(id, event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }

        const targetMenu = document.getElementById(`siteVisitActionDropdown${id}`);
        if (!targetMenu) return;

        const shouldOpen = !targetMenu.classList.contains('show');
        closeAllSiteVisitActionMenus();

        if (shouldOpen) {
            targetMenu.classList.add('show');
        }
    }

    document.addEventListener('click', function(event) {
        if (!event.target.closest('[data-site-visit-action-menu]')) {
            closeAllSiteVisitActionMenus();
        }
    });

    async function showCompleteSiteVisitModal(id) {
        currentSiteVisitId = id;
        currentSiteVisitModalMode = 'complete';
        document.getElementById('rescheduleSiteVisitModal')?.classList.remove('show');
        
        try {
            // Fetch site visit data to get existing project
            // Use direct fetch since apiResource route is at /api/site-visits
            const token = getToken();
            const fetchResponse = await fetch(`/api/site-visits/${id}`, {
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            
            if (!fetchResponse.ok) {
                throw new Error('Failed to fetch site visit data');
            }
            
            const payload = await fetchResponse.json();
            const siteVisit = payload?.data || payload;
            
            resetCompleteSiteVisitModal();
            applyCompleteSiteVisitModalMode(siteVisit, 'complete');
            
            // Clear and initialize project tags
            const container = document.getElementById('completeProjectTagsContainer');
            const hiddenInput = document.getElementById('completeProjectHidden');
            const input = document.getElementById('completeProjectInput');
            
            if (container) container.innerHTML = '';
            if (hiddenInput) hiddenInput.value = '';
            if (input) input.value = '';
            
            // Initialize project tags from existing project field
            if (siteVisit && siteVisit.project) {
                const projects = siteVisit.project.split(',').map(p => p.trim()).filter(p => p);
                projects.forEach(projectName => {
                    addCompleteProjectTag(projectName);
                });
            }
            
            // Setup project input handler
            setupCompleteProjectInput();
            
            document.getElementById('completeSiteVisitModal').classList.add('show');
        } catch (error) {
            console.error('Error loading site visit data:', error);
            resetCompleteSiteVisitModal();
            applyCompleteSiteVisitModalMode(null, 'complete');
            document.getElementById('completeSiteVisitModal').classList.add('show');
            setupCompleteProjectInput();
        }
    }

    function closeCompleteSiteVisitModal() {
        document.getElementById('completeSiteVisitModal').classList.remove('show');
        resetCompleteSiteVisitModal();
        currentSiteVisitModalMode = 'complete';
        currentSiteVisitModalState.existingProofPhotos = [];
        currentSiteVisitId = null;
    }

    function resetCompleteSiteVisitModal() {
        document.getElementById('siteVisitProofPhotosInput').value = '';
        document.getElementById('siteVisitProofPhotosInput').required = true;
        document.getElementById('siteVisitProofPhotosPreview').innerHTML = '';
        document.getElementById('siteVisitFeedback').value = '';
        document.getElementById('siteVisitRating').value = '';
        document.getElementById('siteVisitNotes').value = '';
        document.getElementById('completeTentativeClosingTime').value = '';
        document.getElementById('completeProjectTagsContainer').innerHTML = '';
        document.getElementById('completeProjectHidden').value = '';
        document.getElementById('completeProjectInput').value = '';
        document.querySelectorAll('.completeVisitedPropertyType').forEach((input) => { input.checked = false; });
        document.getElementById('existingSiteVisitProofPhotos').innerHTML = '';
        document.getElementById('siteVisitRejectReasonBox').style.display = 'none';
        document.getElementById('siteVisitRejectReasonText').textContent = '';
        document.getElementById('completeSiteVisitSubmitBtn').textContent = 'Submit';
        document.getElementById('completeSiteVisitModalTitle').textContent = 'Complete Site Visit';
        document.getElementById('completeSiteVisitModalCopy').textContent = 'Upload proof, capture projects, and record visit outcome before submitting.';
        document.getElementById('completeSiteVisitBadge').textContent = 'Required';
        document.getElementById('existingSiteVisitProofPhotosWrap').style.display = 'none';
        document.getElementById('completeSiteVisitProofHint').textContent = 'Add at least one clear site visit photo. Max 5MB per image.';
    }

    function applyCompleteSiteVisitModalMode(siteVisit, mode) {
        currentSiteVisitModalMode = mode;

        if (mode === 'resubmit') {
            document.getElementById('completeSiteVisitModalTitle').textContent = 'Resubmit Site Visit';
            document.getElementById('completeSiteVisitModalCopy').textContent = 'Rejected visit ko same completion form se correct karke dubara verification me bhejo.';
            document.getElementById('completeSiteVisitBadge').textContent = 'Resubmit';
            document.getElementById('completeSiteVisitSubmitBtn').textContent = 'Resubmit For Verification';
            document.getElementById('siteVisitRejectReasonBox').style.display = 'flex';
            document.getElementById('siteVisitRejectReasonText').textContent = siteVisit?.rejection_reason || 'No rejection reason recorded.';
            document.getElementById('siteVisitProofPhotosInput').required = false;
            document.getElementById('existingSiteVisitProofPhotosWrap').style.display = 'block';
            document.getElementById('completeSiteVisitProofHint').textContent = 'Retain existing proof photos or upload new ones. Final submission must include at least one proof photo.';
            currentSiteVisitModalState.existingProofPhotos = Array.isArray(siteVisit?.completion_proof_photos) ? [...siteVisit.completion_proof_photos] : [];
            renderExistingCompleteProofPhotos();
        } else {
            currentSiteVisitModalState.existingProofPhotos = [];
            renderExistingCompleteProofPhotos();
        }

        if (siteVisit?.feedback) document.getElementById('siteVisitFeedback').value = siteVisit.feedback;
        if (siteVisit?.rating) document.getElementById('siteVisitRating').value = siteVisit.rating;
        if (siteVisit?.visit_notes) document.getElementById('siteVisitNotes').value = siteVisit.visit_notes;
        if (siteVisit?.tentative_closing_time) document.getElementById('completeTentativeClosingTime').value = siteVisit.tentative_closing_time;
        const selectedPropertyTypes = Array.isArray(siteVisit?.visited_property_types) ? siteVisit.visited_property_types : [];
        document.querySelectorAll('.completeVisitedPropertyType').forEach((input) => {
            input.checked = selectedPropertyTypes.includes(input.value);
        });

        const visitedProjects = siteVisit?.visited_projects || siteVisit?.project || '';
        if (visitedProjects) {
            visitedProjects.split(',').map(p => p.trim()).filter(Boolean).forEach(projectName => addCompleteProjectTag(projectName));
        }
    }

    function renderExistingCompleteProofPhotos() {
        const container = document.getElementById('existingSiteVisitProofPhotos');
        if (!container) return;

        if (!currentSiteVisitModalState.existingProofPhotos.length) {
            container.innerHTML = '';
            return;
        }

        container.innerHTML = currentSiteVisitModalState.existingProofPhotos.map((path, index) => `
            <div class="resubmit-proof-card">
                <img src="/storage/${path}" alt="Existing proof ${index + 1}">
                <button type="button" class="resubmit-proof-remove" onclick="removeExistingCompleteProofPhoto(${index})">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `).join('');
    }

    function removeExistingCompleteProofPhoto(index) {
        currentSiteVisitModalState.existingProofPhotos.splice(index, 1);
        renderExistingCompleteProofPhotos();
    }

    const resubmitSiteVisitState = {
        siteVisitId: null,
        existingProofPhotos: [],
    };

    function getApiPayloadData(payload) {
        return payload && typeof payload === 'object' && payload.data ? payload.data : payload;
    }

    async function fetchSiteVisitForModal(id) {
        const token = getToken();
        const response = await fetch(`/api/site-visits/${id}`, {
            headers: {
                'Authorization': `Bearer ${token}`,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error('Failed to fetch site visit data');
        }

        return getApiPayloadData(await response.json());
    }

    async function openResubmitSiteVisitModal(id) {
        currentSiteVisitId = id;
        try {
            const siteVisit = await fetchSiteVisitForModal(id);
            resetCompleteSiteVisitModal();
            applyCompleteSiteVisitModalMode(siteVisit, 'resubmit');
            setupCompleteProjectInput();
            document.getElementById('completeSiteVisitModal').classList.add('show');
        } catch (error) {
            console.error('Error loading rejected site visit:', error);
            alert('Failed to load rejected site visit details. Please try again.');
        }
    }

    function closeResubmitSiteVisitModal() {
        document.getElementById('resubmitSiteVisitModal').classList.remove('show');
        document.getElementById('resubmitSiteVisitForm')?.reset();
        document.getElementById('resubmitProjectChoiceWrap').innerHTML = '';
        document.getElementById('resubmitProofPhotosPreview').innerHTML = '';
        document.getElementById('resubmitExistingProofs').innerHTML = '';
        document.getElementById('resubmitPhotosPreview').innerHTML = '';
        document.getElementById('resubmitRejectReason').textContent = '';
        document.getElementById('resubmitResubmissionCount').textContent = '0';
        document.getElementById('resubmitScheduledAt').value = '';
        resubmitSiteVisitState.siteVisitId = null;
        resubmitSiteVisitState.existingProofPhotos = [];
        currentSiteVisitId = null;
    }

    function initResubmitProjectChoices(prefilledProject) {
        const wrap = document.getElementById('resubmitProjectChoiceWrap');
        const input = document.getElementById('resubmitProject');
        const propertyNameInput = document.getElementById('resubmitPropertyName');
        if (!wrap || !input) return;

        const options = [...new Set([prefilledProject, ...DEFAULT_VISIT_PROJECTS].filter(Boolean))];
        wrap.innerHTML = options.map(project => `<button type="button" class="project-choice" data-project="${escapeHtml(project)}">${escapeHtml(project)}</button>`).join('');

        function applySelectedProject(value) {
            input.value = value || '';
            if (propertyNameInput && !propertyNameInput.value) {
                propertyNameInput.value = value || '';
            }
            Array.from(wrap.querySelectorAll('.project-choice')).forEach(button => {
                button.classList.toggle('active', button.dataset.project === value);
            });
        }

        wrap.onclick = function(event) {
            const button = event.target.closest('.project-choice');
            if (!button) return;
            applySelectedProject(button.dataset.project || '');
        };

        input.oninput = function() {
            Array.from(wrap.querySelectorAll('.project-choice')).forEach(button => {
                button.classList.toggle('active', button.dataset.project === input.value.trim());
            });
        };

        applySelectedProject(input.value || prefilledProject || '');
    }

    function populateResubmitSiteVisitForm(visit) {
        const scheduledAt = visit?.scheduled_at ? new Date(visit.scheduled_at) : null;
        document.getElementById('resubmitCustomerName').value = visit?.customer_name || '';
        document.getElementById('resubmitPhone').value = visit?.phone || '';
        document.getElementById('resubmitEmployee').value = visit?.employee || '';
        document.getElementById('resubmitOccupation').value = visit?.occupation || '';
        document.getElementById('resubmitDateOfVisit').value = visit?.date_of_visit ? String(visit.date_of_visit).split('T')[0] : '';
        document.getElementById('resubmitVisitTime').value = scheduledAt ? scheduledAt.toISOString().slice(11, 16) : '';
        document.getElementById('resubmitPropertyAddress').value = visit?.property_address || '';
        document.getElementById('resubmitVisitNotes').value = visit?.visit_notes || '';
        document.getElementById('resubmitProject').value = visit?.project || '';
        document.getElementById('resubmitPropertyName').value = visit?.property_name || '';
        document.getElementById('resubmitBudgetRange').value = visit?.budget_range || '';
        document.getElementById('resubmitTeamLeader').value = visit?.team_leader || '';
        document.getElementById('resubmitPropertyType').value = visit?.property_type || '';
        document.getElementById('resubmitPaymentMode').value = visit?.payment_mode || '';
        document.getElementById('resubmitTentativePeriod').value = visit?.tentative_period || '';
        document.getElementById('resubmitLeadType').value = visit?.lead_type || '';
        document.getElementById('resubmitVisitSequence').value = visit?.visit_sequence || '';
        document.getElementById('resubmitFeedback').value = visit?.feedback || '';
        document.getElementById('resubmitRating').value = visit?.rating || '';
        document.getElementById('resubmitRejectReason').textContent = visit?.rejection_reason || 'No rejection reason recorded.';
        document.getElementById('resubmitResubmissionCount').textContent = String(visit?.resubmission_count || 0);
        document.getElementById('resubmitPhotosInput').value = '';
        document.getElementById('resubmitProofPhotosInput').value = '';

        if (scheduledAt) {
            document.getElementById('resubmitDateOfVisit').value = scheduledAt.toISOString().slice(0, 10);
            document.getElementById('resubmitScheduledAt').value = scheduledAt.toISOString().slice(0, 16);
        } else {
            document.getElementById('resubmitScheduledAt').value = '';
        }

        resubmitSiteVisitState.existingProofPhotos = Array.isArray(visit?.completion_proof_photos)
            ? [...visit.completion_proof_photos]
            : [];
        renderExistingResubmitProofPhotos();
        renderSelectedFilesPreview('resubmitPhotosInput', 'resubmitPhotosPreview', false);
        renderSelectedFilesPreview('resubmitProofPhotosInput', 'resubmitProofPhotosPreview', true);
        initResubmitProjectChoices(visit?.project || '');
    }

    function syncResubmitScheduledAtValue() {
        const dateValue = document.getElementById('resubmitDateOfVisit').value;
        const timeValue = document.getElementById('resubmitVisitTime').value;
        document.getElementById('resubmitScheduledAt').value = dateValue && timeValue ? `${dateValue}T${timeValue}` : '';
    }

    function renderExistingResubmitProofPhotos() {
        const container = document.getElementById('resubmitExistingProofs');
        if (!container) return;

        if (!resubmitSiteVisitState.existingProofPhotos.length) {
            container.innerHTML = '<p class="resubmit-proof-empty">No retained proof photos.</p>';
            return;
        }

        container.innerHTML = resubmitSiteVisitState.existingProofPhotos.map((path, index) => `
            <div class="resubmit-proof-card">
                <img src="/storage/${path}" alt="Existing proof ${index + 1}">
                <button type="button" class="resubmit-proof-remove" onclick="removeExistingResubmitProofPhoto(${index})">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `).join('');
    }

    function removeExistingResubmitProofPhoto(index) {
        resubmitSiteVisitState.existingProofPhotos.splice(index, 1);
        renderExistingResubmitProofPhotos();
    }

    function removeSelectedFile(inputId, index, isImagePreview = false) {
        const input = document.getElementById(inputId);
        if (!input) return;

        const transfer = new DataTransfer();
        Array.from(input.files || []).forEach((file, fileIndex) => {
            if (fileIndex !== index) {
                transfer.items.add(file);
            }
        });

        input.files = transfer.files;
        renderSelectedFilesPreview(inputId, isImagePreview ? 'resubmitProofPhotosPreview' : 'resubmitPhotosPreview', isImagePreview);
    }

    function renderSelectedFilesPreview(inputId, previewId, imageOnly = false) {
        const input = document.getElementById(inputId);
        const preview = document.getElementById(previewId);
        if (!input || !preview) return;

        const files = Array.from(input.files || []);
        preview.innerHTML = '';

        files.forEach((file, index) => {
            const item = document.createElement('div');
            item.className = imageOnly ? 'resubmit-proof-card' : 'photo-preview-item';

            if (imageOnly) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    item.innerHTML = `
                        <img src="${event.target.result}" alt="${escapeHtml(file.name)}">
                        <button type="button" class="resubmit-proof-remove" onclick="removeSelectedFile('${inputId}', ${index}, true)">
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                };
                reader.readAsDataURL(file);
            } else {
                const reader = new FileReader();
                reader.onload = function(event) {
                    item.innerHTML = `
                        <img src="${event.target.result}" alt="${escapeHtml(file.name)}">
                        <button type="button" class="remove-photo" onclick="removeSelectedFile('${inputId}', ${index}, false)">
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                };
                reader.readAsDataURL(file);
            }

            preview.appendChild(item);
        });
    }

    async function submitResubmitSiteVisit() {
        if (!resubmitSiteVisitState.siteVisitId) return;

        syncResubmitScheduledAtValue();
        const form = document.getElementById('resubmitSiteVisitForm');
        const formData = new FormData(form);
        const scheduledAt = document.getElementById('resubmitScheduledAt').value;
        if (scheduledAt) {
            formData.set('scheduled_at', scheduledAt);
        }

        formData.delete('existing_completion_proof_photos[]');
        resubmitSiteVisitState.existingProofPhotos.forEach((path) => {
            formData.append('existing_completion_proof_photos[]', path);
        });

        const proofFiles = document.getElementById('resubmitProofPhotosInput').files || [];
        for (let i = 0; i < proofFiles.length; i += 1) {
            formData.append('proof_photos[]', proofFiles[i]);
        }

        const extraPhotos = document.getElementById('resubmitPhotosInput').files || [];
        for (let i = 0; i < extraPhotos.length; i += 1) {
            formData.append('photos[]', extraPhotos[i]);
        }

        try {
            const token = getToken();
            const response = await fetch(`${API_BASE_URL}/site-visits/${resubmitSiteVisitState.siteVisitId}/resubmit`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
            });

            const result = await response.json();
            if (response.ok && result.success) {
                closeResubmitSiteVisitModal();
                showSiteVisitSuccessModal(result.message || 'Site visit resubmitted successfully.');
                loadSiteVisits();
                return;
            }

            const validationMessage = result?.errors
                ? Object.values(result.errors).flat().join('\n')
                : (result.message || 'Failed to resubmit site visit');
            alert(validationMessage);
        } catch (error) {
            console.error('Error resubmitting site visit:', error);
            alert('Network error. Please try again.');
        }
    }
    
    // Project Tags Functions for Complete Site Visit
    function setupCompleteProjectInput() {
        const input = document.getElementById('completeProjectInput');
        if (input) {
            // Remove any existing event listeners by cloning the input
            const newInput = input.cloneNode(true);
            input.parentNode.replaceChild(newInput, input);
            
            newInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const value = this.value.trim();
                    if (value) {
                        addCompleteProjectTag(value);
                        this.value = '';
                    }
                }
            });
        }
    }
    
    function addCompleteProjectTag(tagName) {
        const container = document.getElementById('completeProjectTagsContainer');
        const hiddenInput = document.getElementById('completeProjectHidden');
        
        if (!container || !hiddenInput) return;
        
        // Check if tag already exists
        const existingTags = Array.from(container.querySelectorAll('.complete-project-tag-text'));
        const tagExists = existingTags.some(tag => tag.textContent.trim() === tagName.trim());
        
        if (tagExists) {
            return; // Don't add duplicate
        }
        
        // Create tag element
        const tagElement = document.createElement('span');
        tagElement.className = 'complete-project-tag';
        tagElement.innerHTML = `
            <span class="complete-project-tag-text">${escapeHtml(tagName)}</span>
            <span class="complete-project-tag-remove" onclick="removeCompleteProjectTag(this)">×</span>
        `;
        
        container.appendChild(tagElement);
        
        // Update hidden input with comma-separated values
        updateCompleteProjectHiddenInput();
    }
    
    function removeCompleteProjectTag(element) {
        const tagElement = element.closest('.complete-project-tag');
        if (tagElement) {
            tagElement.remove();
            updateCompleteProjectHiddenInput();
        }
    }
    
    function updateCompleteProjectHiddenInput() {
        const container = document.getElementById('completeProjectTagsContainer');
        const hiddenInput = document.getElementById('completeProjectHidden');
        
        if (!container || !hiddenInput) return;
        
        const tags = Array.from(container.querySelectorAll('.complete-project-tag-text'));
        const projectNames = tags.map(tag => tag.textContent.trim()).filter(name => name);
        hiddenInput.value = projectNames.join(',');
    }
    
    // Escape HTML helper
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function handleSiteVisitProofPhotosChange(event) {
        const files = event.target.files;
        const preview = document.getElementById('siteVisitProofPhotosPreview');
        preview.innerHTML = '';
        
        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.createElement('img');
                img.src = e.target.result;
                img.style.width = '100px';
                img.style.height = '100px';
                img.style.objectFit = 'cover';
                img.style.borderRadius = '8px';
                img.style.margin = '5px';
                preview.appendChild(img);
            };
            reader.readAsDataURL(file);
        }
    }

    function parseJsonFromResponseText(responseText) {
        if (!responseText) return null;

        const trimmed = responseText.trim();
        try {
            return JSON.parse(trimmed);
        } catch (error) {
            const firstBrace = trimmed.indexOf('{');
            const lastBrace = trimmed.lastIndexOf('}');
            if (firstBrace === -1 || lastBrace === -1 || lastBrace <= firstBrace) {
                return null;
            }
            const jsonCandidate = trimmed.slice(firstBrace, lastBrace + 1);
            try {
                return JSON.parse(jsonCandidate);
            } catch (parseError) {
                return null;
            }
        }
    }

    function showSiteVisitSuccessModal(message) {
        const modal = document.getElementById('siteVisitSuccessModal');
        const messageElement = document.getElementById('siteVisitSuccessMessage');
        if (messageElement) {
            messageElement.textContent = message;
        }
        if (modal) {
            modal.classList.add('show');
        }
    }

    function closeSiteVisitSuccessModal() {
        const modal = document.getElementById('siteVisitSuccessModal');
        if (modal) {
            modal.classList.remove('show');
        }
    }

    // Close modal on backdrop click
    document.getElementById('siteVisitSuccessModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeSiteVisitSuccessModal();
        }
    });

    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modal = document.getElementById('siteVisitSuccessModal');
            if (modal && modal.classList.contains('show')) {
                closeSiteVisitSuccessModal();
            }
        }
    });

    async function submitCompleteSiteVisit() {
        if (!currentSiteVisitId) return;

        const formData = new FormData();
        const photosInput = document.getElementById('siteVisitProofPhotosInput');

        const newPhotoCount = photosInput.files ? photosInput.files.length : 0;
        const retainedPhotoCount = currentSiteVisitModalMode === 'resubmit'
            ? currentSiteVisitModalState.existingProofPhotos.length
            : 0;

        if ((newPhotoCount + retainedPhotoCount) === 0) {
            alert('Please upload at least one proof photo');
            return;
        }

        for (let i = 0; i < newPhotoCount; i++) {
            formData.append('proof_photos[]', photosInput.files[i]);
        }

        if (currentSiteVisitModalMode === 'resubmit') {
            currentSiteVisitModalState.existingProofPhotos.forEach((path) => {
                formData.append('existing_completion_proof_photos[]', path);
            });
        }

        const feedback = document.getElementById('siteVisitFeedback').value;
        const rating = document.getElementById('siteVisitRating').value;
        const notes = document.getElementById('siteVisitNotes').value;
        const visitedProjects = document.getElementById('completeProjectHidden').value;
        const visitedPropertyTypes = Array.from(document.querySelectorAll('.completeVisitedPropertyType:checked')).map((input) => input.value);
        const tentativeClosingTime = document.getElementById('completeTentativeClosingTime').value;

        if (feedback) formData.append('feedback', feedback);
        if (rating) formData.append('rating', rating);
        if (notes) formData.append('visit_notes', notes);
        if (visitedProjects) formData.append('visited_projects', visitedProjects);
        visitedPropertyTypes.forEach((type) => formData.append('visited_property_types[]', type));
        if (tentativeClosingTime) formData.append('tentative_closing_time', tentativeClosingTime);

        try {
            const token = getToken();
            const endpoint = currentSiteVisitModalMode === 'resubmit'
                ? `${API_BASE_URL}/site-visits/${currentSiteVisitId}/resubmit`
                : `${API_BASE_URL}/site-visits/${currentSiteVisitId}/complete`;
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Accept': 'application/json',
                },
                body: formData,
            });

            // Read response as text first (can only read once)
            let responseText;
            let result;

            try {
                responseText = await response.text();
                result = parseJsonFromResponseText(responseText);
                if (!result) {
                    console.error('Invalid JSON response:', responseText.substring(0, 500));
                    alert('Server returned invalid JSON. Please try again.');
                    return;
                }
            } catch (textError) {
                console.error('Error reading response:', textError);
                alert('Network error. Please try again.');
                return;
            }

            if (response.ok && result && result.success) {
                const successMessage = currentSiteVisitModalMode === 'resubmit'
                    ? (result.message || 'Site visit resubmitted successfully. Awaiting verification.')
                    : 'Site visit completed with proof photos! Awaiting verification.';
                showSiteVisitSuccessModal(successMessage);
                closeCompleteSiteVisitModal();
                loadSiteVisits();
            } else {
                // Handle validation errors or other errors
                let errorMessage = result.message || (currentSiteVisitModalMode === 'resubmit'
                    ? 'Failed to resubmit site visit'
                    : 'Failed to complete site visit');
                
                if (result.errors) {
                    console.error('Validation errors:', result.errors);
                    // Format validation errors
                    const errorMessages = [];
                    if (result.errors.proof_photos) {
                        errorMessages.push('Proof photos: ' + (Array.isArray(result.errors.proof_photos) ? result.errors.proof_photos.join(', ') : result.errors.proof_photos));
                    }
                    if (result.errors.feedback) {
                        errorMessages.push('Feedback: ' + (Array.isArray(result.errors.feedback) ? result.errors.feedback.join(', ') : result.errors.feedback));
                    }
                    if (result.errors.rating) {
                        errorMessages.push('Rating: ' + (Array.isArray(result.errors.rating) ? result.errors.rating.join(', ') : result.errors.rating));
                    }
                    if (result.errors.visit_notes) {
                        errorMessages.push('Notes: ' + (Array.isArray(result.errors.visit_notes) ? result.errors.visit_notes.join(', ') : result.errors.visit_notes));
                    }
                    if (result.errors.visited_projects) {
                        errorMessages.push('Visited Projects: ' + (Array.isArray(result.errors.visited_projects) ? result.errors.visited_projects.join(', ') : result.errors.visited_projects));
                    }
                    if (result.errors.tentative_closing_time) {
                        errorMessages.push('Tentative Closing Time: ' + (Array.isArray(result.errors.tentative_closing_time) ? result.errors.tentative_closing_time.join(', ') : result.errors.tentative_closing_time));
                    }
                    
                    if (errorMessages.length > 0) {
                        errorMessage = errorMessages.join('\n');
                    }
                }
                
                alert(errorMessage);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Network error. Please try again.');
        }
    }

    async function requestCloseFromVisit(id) {
        try {
            const token = getToken();
            const response = await fetch(`${API_BASE_URL}/site-visits/${id}/request-close`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Accept': 'application/json',
                },
            });

            const result = await response.json();
            if (response.ok && result.success) {
                showSiteVisitSuccessModal(result.message || 'Close request submitted successfully.');
                loadSiteVisits();
                return;
            }

            alert(result.message || 'Failed to mark as closed');
        } catch (error) {
            console.error('Error requesting close:', error);
            alert('Network error. Please try again.');
        }
    }

    const requestCloserState = {
        schema: SITE_VISITS_DEFAULT_KYC_SCHEMA,
        existingKycDocuments: [],
        existingProofPhotos: [],
        visit: null,
        currentStep: 0,
        readonly: false,
    };
    const REQUEST_CLOSER_TITLES = ['Mr.', 'Mrs.', 'Ms.', 'M/s.', 'Dr.'];

    function requestCloserInputId(fieldKey) {
        return `requestCloser_${fieldKey}`;
    }

    function isRequestCloserTitleField(fieldKey) {
        return ['primary_applicant_name', 'joint_applicant_name'].includes(fieldKey);
    }

    function isRequestCloserApplicantSection(sectionLabel) {
        return ['1. Sole / First Applicant', '2. Second / Joint Applicant / Nominee'].includes(String(sectionLabel || ''));
    }

    function getRequestCloserApplicantPrefix(sectionLabel) {
        return String(sectionLabel || '').startsWith('1.') ? 'primary_applicant_' : 'joint_applicant_';
    }

    function splitRequestCloserTitleValue(value) {
        const raw = String(value || '').trim();
        if (!raw) {
            return { title: '', name: '' };
        }

        const matchedTitle = REQUEST_CLOSER_TITLES.find((title) => raw === title || raw.startsWith(`${title} `));
        if (!matchedTitle) {
            return { title: '', name: raw };
        }

        return {
            title: matchedTitle,
            name: raw === matchedTitle ? '' : raw.slice(matchedTitle.length).trim(),
        };
    }

    function getRequestCloserTitleCompositeValue(inputId) {
        const title = document.getElementById(`${inputId}__title`)?.value || '';
        const name = (document.getElementById(`${inputId}__name`)?.value || '').trim();
        if (!name) {
            return '';
        }

        return `${title ? `${title} ` : ''}${name}`.trim();
    }

    function syncRequestCloserTitleValue(fieldKey) {
        const inputId = requestCloserInputId(fieldKey);
        const hidden = document.getElementById(inputId);
        if (hidden) {
            hidden.value = getRequestCloserTitleCompositeValue(inputId);
        }
    }

    function getRequestCloserSteps(schema) {
        const sections = Array.isArray(schema?.sections) ? schema.sections : [];
        if (sections.length === 0) {
            return [];
        }

        const normalize = (value) => String(value || '').trim().toLowerCase();
        const byLabel = new Map(sections.map((section) => [normalize(section.label), section]));
        const first = byLabel.get(normalize('1. Sole / First Applicant')) || sections[0] || null;
        const joint = byLabel.get(normalize('2. Second / Joint Applicant / Nominee')) || null;
        const unit = byLabel.get(normalize('Details Of The Unit')) || null;
        const docs = byLabel.get(normalize('Documents')) || null;

        const ordered = [
            first ? { label: '1. Sole / First Applicant', sections: [first], isDocuments: false } : null,
            joint ? { label: '2. Second / Joint Applicant / Nominee', sections: [joint], isDocuments: false } : null,
            unit ? { label: 'Details Of The Unit', sections: [unit], isDocuments: false } : null,
            docs ? { label: 'Documents & Proof Upload', sections: [docs], isDocuments: true } : null,
        ].filter(Boolean);

        const used = new Set(ordered.flatMap((step) => step.sections));
        const leftovers = sections.filter((section) => !used.has(section));
        leftovers.forEach((section) => {
            ordered.push({ label: section.label || 'Additional Section', sections: [section], isDocuments: false });
        });

        return ordered;
    }

    function syncRequestCloserWizardUi() {
        const steps = getRequestCloserSteps(requestCloserState.schema);
        if (!steps.length) {
            return;
        }

        requestCloserState.currentStep = Math.min(Math.max(requestCloserState.currentStep || 0, 0), steps.length - 1);
        const currentIndex = requestCloserState.currentStep;

        document.querySelectorAll('[data-request-closer-step]').forEach((element) => {
            const stepIndex = Number(element.dataset.requestCloserStep || '0');
            element.hidden = stepIndex !== currentIndex;
        });

        document.querySelectorAll('[data-request-closer-step-pill]').forEach((element) => {
            const stepIndex = Number(element.dataset.requestCloserStepPill || '0');
            element.classList.toggle('is-active', stepIndex === currentIndex);
        });

        const caption = document.getElementById('requestCloserStepCaption');
        if (caption) {
            caption.hidden = false;
            caption.textContent = `Step ${currentIndex + 1} of ${steps.length}`;
        }

        const existingPanel = document.getElementById('requestCloserExistingFilesPanel');
        const existingNote = document.getElementById('requestCloserExistingFiles');
        const isDocumentsStep = !!steps[currentIndex]?.isDocuments;
        if (existingPanel) {
            const hasFiles = requestCloserState.existingKycDocuments.length > 0 || requestCloserState.existingProofPhotos.length > 0;
            existingPanel.hidden = !isDocumentsStep || !hasFiles;
        }
        if (existingNote) {
            const hasExistingSummary = existingNote.textContent && existingNote.textContent.trim() !== '';
            existingNote.hidden = !isDocumentsStep || !hasExistingSummary;
        }

        const backButton = document.getElementById('requestCloserBackButton');
        const nextButton = document.getElementById('requestCloserNextButton');
        const saveButton = document.getElementById('requestCloserSaveDraftButton');
        const submitButton = document.getElementById('requestCloserSubmitButton');

        if (backButton) {
            backButton.hidden = currentIndex === 0;
        }
        if (nextButton) {
            nextButton.hidden = currentIndex >= steps.length - 1;
        }
        if (submitButton) {
            submitButton.hidden = requestCloserState.readonly || currentIndex < steps.length - 1;
        }
        if (saveButton) {
            saveButton.hidden = requestCloserState.readonly;
        }
    }

    function applyRequestCloserReadonlyState() {
        const form = document.getElementById('closingRequestForm');
        if (!form) return;

        form.querySelectorAll('input, select, textarea, button').forEach((element) => {
            element.disabled = !!requestCloserState.readonly;
        });
    }

    function setRequestCloserStep(stepIndex) {
        requestCloserState.currentStep = stepIndex;
        syncRequestCloserWizardUi();
    }

    function changeRequestCloserStep(delta) {
        const steps = getRequestCloserSteps(requestCloserState.schema);
        if (!steps.length) {
            return;
        }

        setRequestCloserStep(Math.min(Math.max((requestCloserState.currentStep || 0) + delta, 0), steps.length - 1));
    }

    function renderRequestCloserSelectedFiles(inputId, previewId) {
        const input = document.getElementById(inputId);
        const preview = document.getElementById(previewId);
        if (!preview) {
            return;
        }

        const files = Array.from(input?.files || []);
        preview.innerHTML = files.map((file) => `
            <div class="request-kyc-selected-file">${escapeHtml(file.name)}</div>
        `).join('');
    }

    function renderRequestCloserExistingFiles() {
        const kycGrid = document.getElementById('requestCloserExistingKycDocs');
        const proofGrid = document.getElementById('requestCloserExistingProofPhotos');
        const panel = document.getElementById('requestCloserExistingFilesPanel');
        const note = document.getElementById('requestCloserExistingFiles');

        const renderCards = (paths) => (paths || []).map((path) => {
            const safePath = String(path || '');
            const label = safePath.split('/').pop() || 'Open file';
            const url = `/storage/${safePath}`;
            const isImage = /\.(jpg|jpeg|png|webp)$/i.test(label);

            return `
                <div class="request-kyc-file-card">
                    ${isImage ? `<img src="${escapeHtml(url)}" alt="${escapeHtml(label)}">` : ''}
                    <div class="request-kyc-file-card-body">
                        <div style="font-size:0.82rem;color:#4d6357;margin-bottom:6px;">${escapeHtml(label)}</div>
                        <a class="request-kyc-file-link" href="${escapeHtml(url)}" target="_blank" rel="noopener">Open file</a>
                    </div>
                </div>
            `;
        }).join('');

        if (kycGrid) {
            kycGrid.innerHTML = renderCards(requestCloserState.existingKycDocuments);
        }
        if (proofGrid) {
            proofGrid.innerHTML = renderCards(requestCloserState.existingProofPhotos);
        }

        const existingBits = [];
        if (requestCloserState.existingKycDocuments.length > 0) {
            existingBits.push(`${requestCloserState.existingKycDocuments.length} existing KYC docs`);
        }
        if (requestCloserState.existingProofPhotos.length > 0) {
            existingBits.push(`${requestCloserState.existingProofPhotos.length} existing proof photos`);
        }

        if (panel) {
            panel.hidden = existingBits.length === 0;
        }
        if (note) {
            note.hidden = existingBits.length === 0;
            note.textContent = existingBits.length
                ? `${existingBits.join(' • ')}. Existing files retain rahenge; new upload bhi add kar sakte ho.`
                : '';
        }
    }

    function renderRequestCloserField(field, value) {
        const fieldKey = field.field_key;
        const inputId = requestCloserInputId(fieldKey);
        const requiredMark = field.required ? ' <span style="color:#dc2626;">*</span>' : '';
        const helpText = field.help_text ? `<div class="request-kyc-help">${escapeHtml(field.help_text)}</div>` : '';
        const isFullWidth = field.field_type === 'textarea'
            || field.field_type === 'radio'
            || (field.field_type === 'checkbox' && Array.isArray(field.options) && field.options.length > 0)
            || field.field_type === 'file';

        if (isRequestCloserTitleField(fieldKey)) {
            const splitValue = splitRequestCloserTitleValue(value);
            const optionHtml = [`<option value="">Title</option>`]
                .concat(REQUEST_CLOSER_TITLES.map((option) => `<option value="${escapeHtml(option)}" ${splitValue.title === option ? 'selected' : ''}>${escapeHtml(option)}</option>`))
                .join('');

            return `
                <div class="request-kyc-field">
                    <label for="${inputId}__name">${escapeHtml(field.label)}${requiredMark}</label>
                    <div class="request-kyc-combo">
                        <select id="${inputId}__title" class="request-kyc-select" onchange="syncRequestCloserTitleValue('${escapeHtml(fieldKey)}')">${optionHtml}</select>
                        <input id="${inputId}__name" class="request-kyc-input" type="text" value="${escapeHtml(splitValue.name || '')}" placeholder="Enter full name" oninput="syncRequestCloserTitleValue('${escapeHtml(fieldKey)}')">
                    </div>
                    <input id="${inputId}" type="hidden" value="${escapeHtml(value || '')}">
                    ${helpText}
                </div>
            `;
        }

        if (field.field_type === 'textarea') {
            return `
                <div class="request-kyc-field ${isFullWidth ? 'full' : ''}">
                    <label for="${inputId}">${escapeHtml(field.label)}${requiredMark}</label>
                    <textarea id="${inputId}" class="request-kyc-textarea" rows="3" placeholder="${escapeHtml(field.placeholder || '')}">${escapeHtml(value || '')}</textarea>
                    ${helpText}
                </div>
            `;
        }

        if (field.field_type === 'select') {
            const options = Array.isArray(field.options) ? field.options : [];
            const optionHtml = [`<option value="">Select ${escapeHtml(field.label)}</option>`]
                .concat(options.map((option) => `<option value="${escapeHtml(option)}" ${String(value || '') === String(option) ? 'selected' : ''}>${escapeHtml(option)}</option>`))
                .join('');

            return `
                <div class="request-kyc-field">
                    <label for="${inputId}">${escapeHtml(field.label)}${requiredMark}</label>
                    <select id="${inputId}" class="request-kyc-select">${optionHtml}</select>
                    ${helpText}
                </div>
            `;
        }

        if (field.field_type === 'radio') {
            const options = Array.isArray(field.options) ? field.options : [];
            return `
                <div class="request-kyc-field full">
                    <label>${escapeHtml(field.label)}${requiredMark}</label>
                    <div class="request-kyc-check-grid">
                        ${options.map((option) => `
                            <label class="request-kyc-check-item">
                                <input type="radio" name="${escapeHtml(fieldKey)}" value="${escapeHtml(option)}" ${String(value || '') === String(option) ? 'checked' : ''}>
                                <span>${escapeHtml(option)}</span>
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
                <div class="request-kyc-field full">
                    <label>${escapeHtml(field.label)}${requiredMark}</label>
                    <div class="request-kyc-check-grid">
                        ${field.options.map((option) => `
                            <label class="request-kyc-check-item">
                                <input type="checkbox" data-array-checkbox="true" data-field-key="${escapeHtml(fieldKey)}" value="${escapeHtml(option)}" ${selectedValues.includes(String(option)) ? 'checked' : ''}>
                                <span>${escapeHtml(option)}</span>
                            </label>
                        `).join('')}
                    </div>
                    ${helpText}
                </div>
            `;
        }

        if (field.field_type === 'checkbox') {
            return `
                <div class="request-kyc-field">
                    <label class="request-kyc-check-item" style="margin-top:28px;">
                        <input id="${inputId}" type="checkbox" ${value ? 'checked' : ''}>
                        <span>${escapeHtml(field.label)}${requiredMark}</span>
                    </label>
                    ${helpText}
                </div>
            `;
        }

        if (field.field_type === 'file') {
            const multiple = field.field_key === 'kyc_documents' || field.field_key === 'proof_photos' ? ' multiple' : '';
            const accept = field.field_key === 'proof_photos'
                ? ' accept="image/*,.webp"'
                : (field.field_key === 'kyc_documents' ? ' accept="image/*,.pdf"' : '');
            const previewId = `${inputId}_preview`;
            return `
                <div class="request-kyc-field full">
                    <label for="${inputId}">${escapeHtml(field.label)}${requiredMark}</label>
                    <input id="${inputId}" class="request-kyc-file" type="file"${multiple}${accept}>
                    <div id="${previewId}" class="request-kyc-selected-files"></div>
                    ${helpText}
                </div>
            `;
        }

        const type = ['date', 'email', 'number'].includes(field.field_type) ? field.field_type : 'text';
        const dateBounds = field.field_key.endsWith('date_of_birth') ? ` min="1900-01-01" max="{{ now()->toDateString() }}"` : '';
        return `
            <div class="request-kyc-field ${isFullWidth ? 'full' : ''}">
                <label for="${inputId}">${escapeHtml(field.label)}${requiredMark}</label>
                <input id="${inputId}" class="request-kyc-input" type="${type}" value="${escapeHtml(value || '')}" placeholder="${escapeHtml(field.placeholder || '')}"${dateBounds}>
                ${helpText}
            </div>
        `;
    }

    function renderRequestCloserStructuredApplicantSection(section) {
        const prefix = getRequestCloserApplicantPrefix(section.label);
        const byKey = new Map((section.fields || []).map((field) => [field.field_key, field]));
        const render = (key) => byKey.has(key) ? renderRequestCloserField(byKey.get(key), byKey.get(key).value) : '';
        const renderGroup = (title, keys, extra = '') => {
            const html = keys.map((key) => render(key)).filter(Boolean).join('');
            if (!html && !extra) {
                return '';
            }
            return `
                <div class="request-kyc-group">
                    <div class="request-kyc-group-title">${escapeHtml(title)}</div>
                    <div class="request-kyc-check-grid inline-chips">${html}</div>
                    ${extra}
                </div>
            `;
        };

        const occupationExtra = render(`${prefix}occupation_any_other`);
        const residentialExtra = render(`${prefix}resident_other`);

        return `
            <div class="request-kyc-section">
                <div class="request-kyc-section-title">${escapeHtml(section.label || 'Section')}</div>
                <div class="request-kyc-grid form-structured">
                    <div class="request-kyc-row">
                        ${render(`${prefix}name`)}
                        ${render(`${prefix}relation_name`)}
                    </div>
                    <div class="request-kyc-row">
                        ${render(`${prefix}date_of_birth`)}
                        ${render(`${prefix}nationality`)}
                    </div>
                    ${renderGroup('Occupation', [
                        `${prefix}occupation_service`,
                        `${prefix}occupation_professional`,
                        `${prefix}occupation_housewife`,
                        `${prefix}occupation_business`,
                    ], occupationExtra)}
                    ${renderGroup('Residential Status', [
                        `${prefix}resident_indian`,
                        `${prefix}resident_non_resident`,
                        `${prefix}resident_foreign_national`,
                    ], residentialExtra)}
                    ${renderGroup('Marital Status', [
                        `${prefix}marital_status_married`,
                        `${prefix}marital_status_unmarried`,
                    ])}
                    <div class="request-kyc-row">
                        ${render(`${prefix}pan_no`)}
                        ${prefix === 'primary_applicant_' ? render(`${prefix}aadhaar_no`) : '<div></div>'}
                    </div>
                    ${prefix !== 'primary_applicant_' ? render(`${prefix}aadhaar_no`) : ''}
                    ${render(`${prefix}address`)}
                    <div class="request-kyc-row cols-4">
                        ${render(`${prefix}city`)}
                        ${render(`${prefix}state`)}
                        ${render(`${prefix}country`)}
                        ${render(`${prefix}pin`)}
                    </div>
                    <div class="request-kyc-row cols-4">
                        ${render(`${prefix}email`)}
                        ${render(`${prefix}tel_no`)}
                        ${render(`${prefix}mobile_no`)}
                        ${render(`${prefix}fax_no`)}
                    </div>
                </div>
            </div>
        `;
    }

    function requestCloserFieldInputValue(field) {
        const inputId = requestCloserInputId(field.field_key);

        if (field.field_key === 'kyc_documents' || field.field_key === 'proof_photos') {
            return null;
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

        if (isRequestCloserTitleField(field.field_key)) {
            const composite = getRequestCloserTitleCompositeValue(inputId);
            const hidden = document.getElementById(inputId);
            if (hidden) {
                hidden.value = composite;
            }
            return composite || hidden?.value || '';
        }

        return document.getElementById(inputId)?.value || '';
    }

    function validateRequestCloserSchema(mode = 'submit') {
        if (mode !== 'submit') {
            return true;
        }

        const schema = requestCloserState.schema || { sections: [] };

        for (const section of schema.sections || []) {
            for (const field of section.fields || []) {
                if (!field.required) {
                    continue;
                }

                if (isRequestCloserTitleField(field.field_key)) {
                    const inputId = requestCloserInputId(field.field_key);
                    const nameValue = (document.getElementById(`${inputId}__name`)?.value || '').trim();
                    const titleValue = document.getElementById(`${inputId}__title`)?.value || '';
                    const combined = nameValue ? `${titleValue ? `${titleValue} ` : ''}${nameValue}`.trim() : '';
                    const hidden = document.getElementById(inputId);
                    if (hidden) {
                        hidden.value = combined;
                    }
                    if (!combined) {
                        alert(`${field.label} is required before submission.`);
                        return false;
                    }
                    continue;
                }

                if (field.field_key === 'kyc_documents') {
                    const files = document.getElementById(requestCloserInputId(field.field_key))?.files || [];
                    if (!requestCloserState.existingKycDocuments.length && !files.length) {
                        alert(`${field.label} is required before submission.`);
                        return false;
                    }
                    continue;
                }

                if (field.field_key === 'proof_photos') {
                    const files = document.getElementById(requestCloserInputId(field.field_key))?.files || [];
                    if (!requestCloserState.existingProofPhotos.length && !files.length) {
                        alert(`${field.label} is required before submission.`);
                        return false;
                    }
                    continue;
                }

                const value = requestCloserFieldInputValue(field);
                if (Array.isArray(value) && value.length === 0) {
                    alert(`${field.label} is required before submission.`);
                    return false;
                }
                if (value instanceof FileList && value.length === 0) {
                    alert(`${field.label} is required before submission.`);
                    return false;
                }
                if (!Array.isArray(value) && !(value instanceof FileList) && String(value || '').trim() === '') {
                    alert(`${field.label} is required before submission.`);
                    return false;
                }
            }
        }

        return true;
    }

    function renderRequestCloserForm(visit) {
        const schema = visit?.kyc_schema || SITE_VISITS_DEFAULT_KYC_SCHEMA || { sections: [] };
        requestCloserState.schema = schema;
        const steps = getRequestCloserSteps(schema);

        const form = document.getElementById('closingRequestForm');
        const stepper = document.getElementById('requestCloserStepper');
        if (!form) {
            return;
        }

        if (stepper) {
            stepper.hidden = steps.length === 0;
            stepper.innerHTML = steps.map((step, index) => `
                <button type="button" class="request-kyc-step-pill ${index === requestCloserState.currentStep ? 'is-active' : ''}" data-request-closer-step-pill="${index}" onclick="setRequestCloserStep(${index})">
                    <span class="request-kyc-step-pill-index">Step ${index + 1}</span>
                    <span class="request-kyc-step-pill-label">${escapeHtml(step.label || `Step ${index + 1}`)}</span>
                </button>
            `).join('');
        }

        form.innerHTML = steps.map((step, stepIndex) => `
            <div class="request-kyc-step-panel" data-request-closer-step="${stepIndex}" ${stepIndex === requestCloserState.currentStep ? '' : 'hidden'}>
                ${step.sections.map((section) => (
                    isRequestCloserApplicantSection(section.label)
                        ? renderRequestCloserStructuredApplicantSection(section)
                        : `
                            <div class="request-kyc-section">
                                <div class="request-kyc-section-title">${escapeHtml(section.label || 'Section')}</div>
                                <div class="request-kyc-grid">
                                    ${(section.fields || []).map((field) => renderRequestCloserField(field, field.value)).join('')}
                                </div>
                            </div>
                        `
                )).join('')}
            </div>
        `).join('');

        (schema.sections || []).forEach((section) => {
            (section.fields || []).forEach((field) => {
                if (field.field_type !== 'file') {
                    return;
                }

                const inputId = requestCloserInputId(field.field_key);
                const previewId = `${inputId}_preview`;
                document.getElementById(inputId)?.addEventListener('change', () => {
                    renderRequestCloserSelectedFiles(inputId, previewId);
                });
            });
        });

        syncRequestCloserWizardUi();
    }

    async function fetchRequestCloserVisit(siteVisitId) {
        const token = getToken();
        const response = await fetch(`${API_BASE_URL}/site-visits/${siteVisitId}`, {
            headers: {
                'Authorization': `Bearer ${token}`,
                'Accept': 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error('Failed to load KYC details');
        }

        return await response.json();
    }

    async function showRequestCloserModal(id, readonly = false) {
        currentSiteVisitId = id;
        requestCloserState.currentStep = 0;
        requestCloserState.readonly = !!readonly;

        try {
            const visit = await fetchRequestCloserVisit(id);
            requestCloserState.visit = visit;
            requestCloserState.existingKycDocuments = Array.isArray(visit.kyc_documents) ? [...visit.kyc_documents] : [];
            requestCloserState.existingProofPhotos = Array.isArray(visit.closer_request_proof_photos) ? [...visit.closer_request_proof_photos] : [];
            renderRequestCloserForm(visit);
        } catch (error) {
            console.error('Failed to load request closer schema:', error);
            requestCloserState.visit = null;
            requestCloserState.existingKycDocuments = [];
            requestCloserState.existingProofPhotos = [];
            renderRequestCloserForm({ kyc_schema: SITE_VISITS_DEFAULT_KYC_SCHEMA });
        }

        const title = document.getElementById('requestCloserModalTitle');
        const subtitle = document.getElementById('requestCloserModalSubtitle');
        const note = document.getElementById('requestCloserModalNote');
        const submitButton = document.getElementById('requestCloserSubmitButton');
        const visit = requestCloserState.visit;
        requestCloserState.readonly = requestCloserState.readonly || ['approved', 'verified'].includes(visit?.closer_status || '');
        const isPendingEdit = visit?.closer_status === 'pending_crm';
        const isResubmission = ['correction_required', 'rejected'].includes(visit?.closer_status);

        if (requestCloserState.readonly) {
            if (note) note.classList.remove('is-danger');
            if (title) title.textContent = 'View KYC Form';
            if (subtitle) subtitle.textContent = 'CRM verified KYC locked hai. ASM/Sr. Manager ab isko edit nahi kar sakte.';
            if (note) note.textContent = 'Ye KYC read-only mode me open hai.';
            if (submitButton) submitButton.textContent = 'Submit KYC';
        } else if (isResubmission) {
            if (note) note.classList.add('is-danger');
            if (title) title.textContent = 'Resubmit Closer Form';
            if (subtitle) subtitle.textContent = 'Previous KYC prefilled hai. Required changes karke dubara CRM review me bhejo.';
            if (note) {
                const rejectionReason = visit?.closer_review_remark || visit?.closer_rejection_reason || visit?.closing_rejection_reason || 'CRM ne resubmission request bheji hai. Same KYC update karke resubmit karo.';
                note.textContent = `Closer reject remark: ${rejectionReason}`;
            }
            if (submitButton) submitButton.textContent = 'Resubmit To CRM';
        } else if (isPendingEdit) {
            if (note) note.classList.remove('is-danger');
            if (title) title.textContent = 'Edit KYC Form';
            if (subtitle) subtitle.textContent = 'KYC CRM verification pending hai. Changes save/submit kar sakte ho jab tak CRM verify nahi karta.';
            if (note) note.textContent = 'Update karne ke baad Submit KYC dabao. Verified hone ke baad form locked ho jayega.';
            if (submitButton) submitButton.textContent = 'Update KYC';
        } else {
            if (note) note.classList.remove('is-danger');
            if (title) title.textContent = 'Fill KYC Form';
            if (subtitle) subtitle.textContent = 'Close verification ke baad full KYC yahin se submit karo.';
            if (note) note.textContent = 'Customer, nominee, identity details, KYC documents, aur proof photos required hain. Submit hone ke baad CRM review start hoga.';
            if (submitButton) submitButton.textContent = 'Submit KYC';
        }

        renderRequestCloserExistingFiles();
        applyRequestCloserReadonlyState();
        syncRequestCloserWizardUi();
        document.getElementById('requestCloserModal').classList.add('show');
    }

    function showRequestIncentiveModal(id) {
        currentSiteVisitId = id;
        // Show a simple prompt or modal for incentive request
        const amount = prompt('Enter incentive amount:');
        if (amount && parseFloat(amount) > 0) {
            requestIncentive(id, parseFloat(amount));
        }
    }

    async function requestIncentive(siteVisitId, amount) {
        try {
            const token = getToken();
            const response = await fetch(`${API_BASE_URL}/site-visits/${siteVisitId}/request-incentive`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    type: 'closer',
                    amount: amount,
                }),
            });

            const result = await response.json();
            if (result && result.success) {
                showSiteVisitSuccessModal('Incentive request submitted! Awaiting Finance Manager approval.');
                loadSiteVisits();
            } else {
                alert(result.message || 'Failed to request incentive');
            }
        } catch (error) {
            console.error('Error requesting incentive:', error);
            alert('Network error. Please try again.');
        }
    }

    function closeRequestCloserModal() {
        document.getElementById('requestCloserModal').classList.remove('show');
        document.getElementById('closingRequestForm').innerHTML = '';
        requestCloserState.existingKycDocuments = [];
        requestCloserState.existingProofPhotos = [];
        requestCloserState.schema = SITE_VISITS_DEFAULT_KYC_SCHEMA;
        requestCloserState.visit = null;
        requestCloserState.currentStep = 0;
        requestCloserState.readonly = false;
        currentSiteVisitId = null;
    }

    async function submitRequestCloser(mode = 'submit') {
        if (!currentSiteVisitId) return;
        if (requestCloserState.readonly) return;

        (requestCloserState.schema?.sections || []).forEach((section) => {
            (section.fields || []).forEach((field) => {
                if (isRequestCloserTitleField(field.field_key)) {
                    syncRequestCloserTitleValue(field.field_key);
                }
            });
        });

        if (!validateRequestCloserSchema(mode)) {
            return;
        }

        const formData = new FormData();

        if (requestCloserState.schema?.form_id) {
            formData.append('kyc_form_id', requestCloserState.schema.form_id);
        }

        (requestCloserState.schema?.sections || []).forEach((section) => {
            (section.fields || []).forEach((field) => {
                const value = requestCloserFieldInputValue(field);

                if (field.field_key === 'kyc_documents' || field.field_key === 'proof_photos') {
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

        requestCloserState.existingKycDocuments.forEach((path) => formData.append('existing_kyc_documents[]', path));
        requestCloserState.existingProofPhotos.forEach((path) => formData.append('existing_proof_photos[]', path));

        const kycDocs = document.getElementById(requestCloserInputId('kyc_documents'))?.files || [];
        const proofPhotos = document.getElementById(requestCloserInputId('proof_photos'))?.files || [];
        for (let i = 0; i < kycDocs.length; i += 1) formData.append('kyc_documents[]', kycDocs[i]);
        for (let i = 0; i < proofPhotos.length; i += 1) formData.append('proof_photos[]', proofPhotos[i]);

        try {
            const token = getToken();
            const visit = requestCloserState.visit;
            const isResubmission = ['correction_required', 'rejected'].includes(visit?.closer_status);
            const endpoint = mode === 'draft'
                ? `${API_BASE_URL}/site-visits/${currentSiteVisitId}/kyc/draft`
                : (isResubmission
                    ? `${API_BASE_URL}/site-visits/${currentSiteVisitId}/closer/resubmit`
                    : `${API_BASE_URL}/site-visits/${currentSiteVisitId}/request-closer`);
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Accept': 'application/json',
                },
                body: formData,
            });

            const result = await response.json();

            if (result && result.success) {
                showSiteVisitSuccessModal(mode === 'draft'
                    ? 'KYC draft saved successfully.'
                    : (isResubmission
                        ? 'KYC resubmitted successfully! Awaiting CRM verification.'
                        : 'Closing request submitted with KYC details! Awaiting CRM verification.'));
                closeRequestCloserModal();
                loadSiteVisits();
            } else {
                alert(result.message || 'Failed to request closer');
                if (result.errors) {
                    console.error('Validation errors:', result.errors);
                }
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Network error. Please try again.');
        }
    }

    function showMarkDeadModal(type, id) {
        currentSiteVisitId = id;
        const reasonField = document.getElementById('deadReason');
        const modal = document.getElementById('markDeadModal');
        const submitButton = document.getElementById('markDeadSubmitBtn');
        if (reasonField) {
            reasonField.value = '';
            reasonField.focus();
        }
        if (submitButton) {
            submitButton.disabled = false;
            submitButton.textContent = 'Mark as Dead';
        }
        if (modal) {
            modal.classList.add('show');
        }
    }

    function closeMarkDeadModal() {
        const modal = document.getElementById('markDeadModal');
        const reasonField = document.getElementById('deadReason');
        const submitButton = document.getElementById('markDeadSubmitBtn');
        if (modal) {
            modal.classList.remove('show');
        }
        if (reasonField) {
            reasonField.value = '';
        }
        if (submitButton) {
            submitButton.disabled = false;
            submitButton.textContent = 'Mark as Dead';
        }
        currentSiteVisitId = null;
    }

    async function submitMarkDead() {
        if (!currentSiteVisitId) return;

        const reasonField = document.getElementById('deadReason');
        const submitButton = document.getElementById('markDeadSubmitBtn');
        const reason = reasonField ? reasonField.value.trim() : '';
        if (!reason) {
            alert('Please provide a reason for marking as dead');
            reasonField?.focus();
            return;
        }

        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = 'Processing...';
        }

        const result = await apiCall(`/site-visits/${currentSiteVisitId}/mark-dead`, {
            method: 'POST',
            body: JSON.stringify({ reason }),
        });

        if (result && result.success) {
            closeMarkDeadModal();
            if (window.location.pathname !== '/sales-manager/site-visits') {
                window.location.replace('/sales-manager/site-visits');
                return;
            }
            showSiteVisitSuccessModal('Site visit marked as dead. Lead moved to Other Leads.');
            loadSiteVisits();
        } else {
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.textContent = 'Mark as Dead';
            }
            alert(result.message || 'Failed to mark as dead');
        }
    }

    document.getElementById('markDeadModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeMarkDeadModal();
        }
    });

    // Reschedule Site Visit
    function showRescheduleSiteVisitModal(id) {
        currentSiteVisitId = id;
        document.getElementById('completeSiteVisitModal')?.classList.remove('show');
        resetCompleteSiteVisitModal();
        // Use existing site visit data from the list or fetch if needed
        const minDateTime = new Date();
        minDateTime.setDate(minDateTime.getDate() + 1);
        minDateTime.setHours(0, 0, 0, 0);

        document.getElementById('rescheduleSiteVisitScheduledAt').value = '';
        document.getElementById('rescheduleSiteVisitReason').value = '';
        document.getElementById('rescheduleSiteVisitModalTitle').textContent = 'Reschedule Site Visit';
        document.getElementById('rescheduleSiteVisitModalType').value = 'site-visit';
        document.getElementById('rescheduleSiteVisitModalId').value = id;
        if (ALLOW_PRIVILEGED_PAST_SCHEDULING) {
            document.getElementById('rescheduleSiteVisitScheduledAt').removeAttribute('min');
        } else {
            document.getElementById('rescheduleSiteVisitScheduledAt').min = minDateTime.toISOString().slice(0, 16);
        }
        document.getElementById('rescheduleSiteVisitModal').classList.add('show');
    }

    function showScheduleRevisitModal(id) {
        currentSiteVisitId = id;
        document.getElementById('completeSiteVisitModal')?.classList.remove('show');
        resetCompleteSiteVisitModal();
        const minDateTime = new Date();
        minDateTime.setDate(minDateTime.getDate() + 1);
        minDateTime.setHours(0, 0, 0, 0);

        document.getElementById('rescheduleSiteVisitScheduledAt').value = '';
        document.getElementById('rescheduleSiteVisitReason').value = '';
        document.getElementById('rescheduleSiteVisitModalTitle').textContent = 'Schedule Revisit';
        document.getElementById('rescheduleSiteVisitModalType').value = 'revisit';
        document.getElementById('rescheduleSiteVisitModalId').value = id;
        if (ALLOW_PRIVILEGED_PAST_SCHEDULING) {
            document.getElementById('rescheduleSiteVisitScheduledAt').removeAttribute('min');
        } else {
            document.getElementById('rescheduleSiteVisitScheduledAt').min = minDateTime.toISOString().slice(0, 16);
        }
        document.getElementById('rescheduleSiteVisitModal').classList.add('show');
    }

    function closeRescheduleSiteVisitModal() {
        document.getElementById('rescheduleSiteVisitModal').classList.remove('show');
        document.getElementById('rescheduleSiteVisitScheduledAt').value = '';
        document.getElementById('rescheduleSiteVisitReason').value = '';
        currentSiteVisitId = null;
    }

    async function submitRescheduleSiteVisit() {
        const type = document.getElementById('rescheduleSiteVisitModalType').value;
        const id = document.getElementById('rescheduleSiteVisitModalId').value;
        const scheduledAt = document.getElementById('rescheduleSiteVisitScheduledAt').value;
        const reason = document.getElementById('rescheduleSiteVisitReason').value.trim();

        if (!scheduledAt) {
            alert('Please select a new scheduled date and time');
            return;
        }

        if (!reason) {
            alert('Please provide a reason for rescheduling');
            return;
        }

        try {
            const endpoint = type === 'revisit'
                ? '/site-visits'
                : `/${type === 'meeting' ? 'meetings' : 'site-visits'}/${id}/reschedule`;
            const payload = type === 'revisit'
                ? buildScheduleRevisitPayload(id, scheduledAt, reason)
                : { scheduled_at: scheduledAt, reason: reason };
            const result = await apiCall(endpoint, {
                method: 'POST',
                body: JSON.stringify(payload),
            });

            if (result && result.success) {
                const successMessage = result.message || (type === 'revisit'
                    ? 'Revisit scheduled successfully!'
                    : 'Rescheduled successfully! Verification required.');
                if (typeof showNotification === 'function') {
                    showNotification(successMessage, 'success', 3000);
                } else {
                    alert(successMessage);
                }
                closeRescheduleSiteVisitModal();
                loadSiteVisits();
            } else {
                alert(result.message || 'Failed to reschedule');
                if (result.errors) {
                    console.error('Validation errors:', result.errors);
                }
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Network error. Please try again.');
        }
    }

    function buildScheduleRevisitPayload(id, scheduledAt, reason) {
        const sourceVisit = siteVisitListById[String(id)] || {};
        const lead = sourceVisit.lead || {};
        const allowedPropertyTypes = ['Plot/Villa', 'Flat', 'Commercial', 'Just Exploring'];
        const rawPropertyType = sourceVisit.property_type || lead.property_type || '';
        const propertyType = allowedPropertyTypes.includes(rawPropertyType) ? rawPropertyType : '';
        const budgetRange = sourceVisit.budget_range || (lead.budget ? formatBudget(lead.budget) : '');
        return {
            lead_id: sourceVisit.lead_id || lead.id || null,
            assigned_to: sourceVisit.assigned_to || null,
            customer_name: sourceVisit.customer_name || lead.name || '',
            phone: sourceVisit.phone || lead.phone || '',
            property_name: sourceVisit.property_name || sourceVisit.project || lead.preferred_projects || 'Revisit',
            project: sourceVisit.project || sourceVisit.property_name || lead.preferred_projects || 'Revisit',
            property_address: sourceVisit.property_address || '',
            scheduled_at: scheduledAt,
            visit_notes: reason,
            budget_range: budgetRange,
            property_type: propertyType,
            lead_type: 'Revisited',
            visit_sequence: sourceVisit.visit_sequence === '2nd_visit' ? '3rd_visit' : '2nd_visit',
        };
    }

    // Initialize
    (function() {
        const pageParams = new URLSearchParams(window.location.search);
        const requestedDateFilter = pageParams.get('date_filter');
        const requestedAction = pageParams.get('action');
        const requestedSiteVisitId = Number(pageParams.get('site_visit_id') || pageParams.get('visit_id') || 0) || null;
        const dateFilterEl = document.getElementById('dateFilter');
        const dateFromEl = document.getElementById('dateFrom');
        const dateToEl = document.getElementById('dateTo');
        const assignedToFilterEl = document.getElementById('assignedToFilter');
        const storedFilters = getStoredSiteVisitFilters();
        const savedView = (() => {
            try { return localStorage.getItem('asm_visits_view') || 'list'; } catch (e) { return 'list'; }
        })();
        setVisitsView(getAsmPreferredView('site_visits', savedView), false);

        if (dateFilterEl && requestedDateFilter && ['today', 'this_week', 'this_month', 'this_year', 'custom'].includes(requestedDateFilter)) {
            dateFilterEl.value = requestedDateFilter;
            if (requestedDateFilter === 'custom') {
                if (dateFromEl && pageParams.get('date_from')) dateFromEl.value = pageParams.get('date_from');
                if (dateToEl && pageParams.get('date_to')) dateToEl.value = pageParams.get('date_to');
            }
            toggleCustomDate();
        }

        if (assignedToFilterEl) {
            assignedToFilterEl.value = pageParams.get('assigned_to') || storedFilters.assigned_to || '';
        }

        loadSiteVisits().then(() => {
            if (requestedAction === 'complete' && requestedSiteVisitId) {
                setTimeout(() => showCompleteSiteVisitModal(requestedSiteVisitId), 300);
            }
        });
    })();
</script>

<!-- Reschedule Site Visit Modal -->
<div id="rescheduleSiteVisitModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <h3 id="rescheduleSiteVisitModalTitle" style="font-size: 20px; font-weight: 600; margin-bottom: 20px;">Reschedule Site Visit</h3>
        <input type="hidden" id="rescheduleSiteVisitModalType" value="site-visit">
        <input type="hidden" id="rescheduleSiteVisitModalId" value="">
        
        <div class="form-group">
            <label>New Scheduled Date & Time <span style="color: #ef4444;">*</span></label>
            <input type="datetime-local" id="rescheduleSiteVisitScheduledAt" required
                style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px;">
            <small style="color: #6b7280;">Select a future date and time</small>
        </div>

        <div class="form-group">
            <label>Reason for Rescheduling <span style="color: #ef4444;">*</span></label>
            <textarea id="rescheduleSiteVisitReason" rows="4" placeholder="Enter reason for rescheduling..." required
                style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px;"></textarea>
        </div>

        <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
            <button type="button" class="btn btn-secondary" onclick="closeRescheduleSiteVisitModal()">Cancel</button>
            <button type="button" class="btn" style="background: #f59e0b; color: white;" onclick="submitRescheduleSiteVisit()">Reschedule</button>
        </div>
    </div>
</div>

<!-- Complete Site Visit Modal -->
<div id="completeSiteVisitModal" class="modal">
    <div class="modal-content complete-site-visit-modal-card" style="max-width: 760px; padding: 0; overflow: hidden;">
        <div class="complete-site-visit-modal-head">
            <div>
                <h3 id="completeSiteVisitModalTitle">Complete Site Visit</h3>
                <p id="completeSiteVisitModalCopy">Upload proof, capture projects, and record visit outcome before submitting.</p>
            </div>
            <span id="completeSiteVisitBadge" class="complete-site-visit-badge">Required</span>
        </div>
        <div class="complete-site-visit-modal-body">
            <div id="siteVisitRejectReasonBox" class="sv-rejection-box" style="display: none;">
                <div class="sv-rejection-box__head">
                    <span><i class="fas fa-circle-exclamation"></i> Latest Rejection</span>
                </div>
                <p id="siteVisitRejectReasonText"></p>
            </div>
            <div class="complete-site-visit-alert">
                <i class="fas fa-camera"></i>
                <div>
                    <strong>Proof photos are mandatory.</strong>
                    <span id="completeSiteVisitProofHint">Add at least one clear site visit photo. Max 5MB per image.</span>
                </div>
            </div>

        <div id="existingSiteVisitProofPhotosWrap" class="complete-site-visit-upload" style="display: none;">
            <label>Existing Proof Photos</label>
            <small style="display: block; color: #6b7280; margin-bottom: 8px;">Remove any photo you do not want to keep in this resubmission.</small>
            <div id="existingSiteVisitProofPhotos" class="resubmit-proof-grid"></div>
        </div>
        
        <div class="complete-site-visit-upload">
            <label for="siteVisitProofPhotosInput">Proof Photos <span class="req">*</span></label>
            <input type="file" id="siteVisitProofPhotosInput" multiple accept="image/*" onchange="handleSiteVisitProofPhotosChange(event)" required>
            <div id="siteVisitProofPhotosPreview" class="complete-site-visit-preview"></div>
        </div>

        <div class="complete-site-visit-grid">
            <div class="form-group complete-site-visit-field complete-site-visit-field-wide">
                <label for="completeProjectInput">Visited Project</label>
                <div id="completeProjectTagsContainer" class="complete-site-visit-tags"></div>
                <input type="text" id="completeProjectInput" placeholder="Type project name and press Enter">
                <input type="hidden" id="completeProjectHidden" name="visited_projects">
            <small style="color: #6b7280;">Type project name and press Enter to add. Click × to remove.</small>
        </div>

        <div class="form-group complete-site-visit-field complete-site-visit-field-wide">
            <label>Property Type</label>
            <div class="complete-site-visit-checks">
                <label><input type="checkbox" class="completeVisitedPropertyType" value="plot"> Plot</label>
                <label><input type="checkbox" class="completeVisitedPropertyType" value="villa"> Villa</label>
                <label><input type="checkbox" class="completeVisitedPropertyType" value="apartment"> Apartments</label>
                <label><input type="checkbox" class="completeVisitedPropertyType" value="commercial"> Commercial</label>
                <label><input type="checkbox" class="completeVisitedPropertyType" value="other"> Other</label>
            </div>
            <small>Multiple property types select kar sakte hain.</small>
        </div>

        <div class="form-group complete-site-visit-field">
            <label for="completeTentativeClosingTime">Tentative Closing Time</label>
            <select id="completeTentativeClosingTime" name="tentative_closing_time">
                <option value="">Select an option</option>
                <option value="within_3_days">Within 3 Days</option>
                <option value="tomorrow">Tomorrow</option>
                <option value="this_week">This Week</option>
                <option value="this_month">This Month</option>
                <option value="it_will_take_time">It Will Take Time</option>
            </select>
        </div>

        <div class="form-group complete-site-visit-field complete-site-visit-field-wide">
            <label for="siteVisitFeedback">Feedback</label>
            <textarea id="siteVisitFeedback" rows="4" placeholder="Summarize site visit response, interest level, and objections..."></textarea>
        </div>

        <div class="form-group complete-site-visit-field">
            <label for="siteVisitRating">Rating</label>
            <select id="siteVisitRating">
                <option value="">Select rating</option>
                <option value="1">1 - Poor</option>
                <option value="2">2 - Fair</option>
                <option value="3">3 - Good</option>
                <option value="4">4 - Very Good</option>
                <option value="5">5 - Excellent</option>
            </select>
        </div>

        <div class="form-group complete-site-visit-field complete-site-visit-field-wide">
            <label for="siteVisitNotes">Notes</label>
            <textarea id="siteVisitNotes" rows="4" placeholder="Add internal notes, next steps, or commercial details..."></textarea>
        </div>

        </div>
        <div class="complete-site-visit-actions">
            <button type="button" class="btn btn-secondary" onclick="closeCompleteSiteVisitModal()">Cancel</button>
            <button id="completeSiteVisitSubmitBtn" type="button" class="btn btn-success" onclick="submitCompleteSiteVisit()">Submit</button>
        </div>
        </div>
</div>
</div>

<!-- Resubmit Rejected Site Visit Modal -->
<div id="resubmitSiteVisitModal" class="modal">
    <div class="modal-content resubmit-site-visit-modal-card">
        <div class="complete-site-visit-modal-head">
            <div>
                <h3>Resubmit Site Visit</h3>
                <p>Rejected visit ko same record par edit karke dubara verification me bhejo.</p>
            </div>
            <span class="complete-site-visit-badge">Resubmit</span>
        </div>
        <div class="complete-site-visit-modal-body">
            <div class="sv-rejection-box">
                <div class="sv-rejection-box__head">
                    <span><i class="fas fa-circle-exclamation"></i> Latest Rejection</span>
                    <span>Resubmits: <strong id="resubmitResubmissionCount">0</strong></span>
                </div>
                <p id="resubmitRejectReason">No rejection reason recorded.</p>
            </div>

            <form id="resubmitSiteVisitForm" onsubmit="event.preventDefault(); submitResubmitSiteVisit();">
                <div class="resubmit-form-grid">
                    <div class="form-group">
                        <label for="resubmitDateOfVisit">Visit date <span class="required">*</span></label>
                        <input type="date" id="resubmitDateOfVisit" name="date_of_visit" onchange="syncResubmitScheduledAtValue()" required>
                    </div>
                    <div class="form-group">
                        <label for="resubmitVisitTime">Visit time <span class="required">*</span></label>
                        <input type="time" id="resubmitVisitTime" onchange="syncResubmitScheduledAtValue()" required>
                    </div>
                    <div class="form-group form-wide">
                        <label for="resubmitProject">Select projects to visit <span class="required">*</span></label>
                        <div class="project-choice-wrap" id="resubmitProjectChoiceWrap"></div>
                        <input type="text" id="resubmitProject" name="project" placeholder="Selected project or custom project name" required>
                    </div>
                    <div class="form-group form-wide">
                        <label for="resubmitPropertyAddress">Visit location</label>
                        <input type="text" id="resubmitPropertyAddress" name="property_address" placeholder="Project site address or landmark">
                    </div>
                    <div class="form-group form-wide">
                        <label for="resubmitVisitNotes">Remark</label>
                        <textarea id="resubmitVisitNotes" name="visit_notes" rows="4" placeholder="Any notes about this visit..."></textarea>
                    </div>
                    <div class="form-group form-wide">
                        <label for="resubmitPhotosInput">Photos (optional)</label>
                        <input type="file" id="resubmitPhotosInput" multiple accept="image/jpeg,image/jpg,image/png,image/webp" onchange="renderSelectedFilesPreview('resubmitPhotosInput', 'resubmitPhotosPreview', false)">
                        <div id="resubmitPhotosPreview" class="photo-preview"></div>
                    </div>

                    <div class="form-group">
                        <label for="resubmitCustomerName">Customer Name <span class="required">*</span></label>
                        <input type="text" id="resubmitCustomerName" name="customer_name" required>
                    </div>
                    <div class="form-group">
                        <label for="resubmitPhone">Phone <span class="required">*</span></label>
                        <input type="tel" id="resubmitPhone" name="phone" maxlength="16" required>
                    </div>
                    <div class="form-group">
                        <label for="resubmitEmployee">Employee</label>
                        <input type="text" id="resubmitEmployee" name="employee">
                    </div>
                    <div class="form-group">
                        <label for="resubmitOccupation">Occupation</label>
                        <input type="text" id="resubmitOccupation" name="occupation">
                    </div>
                    <div class="form-group">
                        <label for="resubmitBudgetRange">Budget Range <span class="required">*</span></label>
                        <select id="resubmitBudgetRange" name="budget_range" required>
                            <option value="">Select Budget Range</option>
                            <option value="Under 50 Lac">Under 50 Lac</option>
                            <option value="50 Lac - 1 Cr">50 Lac - 1 Cr</option>
                            <option value="1 Cr - 2 Cr">1 Cr - 2 Cr</option>
                            <option value="2 Cr - 3 Cr">2 Cr - 3 Cr</option>
                            <option value="Above 3 Cr">Above 3 Cr</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="resubmitTeamLeader">Select TL <span class="required">*</span></label>
                        <select id="resubmitTeamLeader" name="team_leader" required>
                            <option value="">Select Team Leader</option>
                            <option value="Admin">Admin</option>
                            <option value="Alpish">Alpish</option>
                            <option value="Akash">Akash</option>
                            <option value="Omkar">Omkar</option>
                            <option value="Shushank">Shushank</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="resubmitPropertyType">Property Type <span class="required">*</span></label>
                        <select id="resubmitPropertyType" name="property_type" required>
                            <option value="">Select Property Type</option>
                            <option value="Plot/Villa">Plot/Villa</option>
                            <option value="Flat">Flat</option>
                            <option value="Commercial">Commercial</option>
                            <option value="Just Exploring">Just Exploring</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="resubmitPaymentMode">Payment Mode <span class="required">*</span></label>
                        <select id="resubmitPaymentMode" name="payment_mode" required>
                            <option value="">Select Payment Mode</option>
                            <option value="Self Fund">Self Fund</option>
                            <option value="Loan">Loan</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="resubmitTentativePeriod">Tentative Finalisation Period <span class="required">*</span></label>
                        <select id="resubmitTentativePeriod" name="tentative_period" required>
                            <option value="">Select Period</option>
                            <option value="Within 1 Month">Within 1 Month</option>
                            <option value="Within 3 Months">Within 3 Months</option>
                            <option value="Within 6 Months">Within 6 Months</option>
                            <option value="More than 6 Months">More than 6 Months</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="resubmitLeadType">Lead Type <span class="required">*</span></label>
                        <select id="resubmitLeadType" name="lead_type" required>
                            <option value="">Select Lead Type</option>
                            <option value="New Visit">New Visit</option>
                            <option value="Revisited">Revisited</option>
                            <option value="Meeting">Meeting</option>
                            <option value="Prospect">Prospect</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="resubmitVisitSequence">Visit Sequence</label>
                        <select id="resubmitVisitSequence" name="visit_sequence">
                            <option value="">Select Visit Sequence</option>
                            <option value="fresh_visit">Fresh Visit</option>
                            <option value="2nd_visit">2nd Visit</option>
                            <option value="3rd_visit">3rd Visit</option>
                        </select>
                    </div>
                    <div class="form-group form-wide">
                        <label for="resubmitPropertyName">Property Name</label>
                        <input type="text" id="resubmitPropertyName" name="property_name" placeholder="Property name">
                    </div>

                    <div class="form-group form-wide">
                        <label>Existing Proof Photos</label>
                        <div id="resubmitExistingProofs" class="resubmit-proof-grid"></div>
                    </div>
                    <div class="form-group form-wide">
                        <label for="resubmitProofPhotosInput">Add Proof Photos</label>
                        <input type="file" id="resubmitProofPhotosInput" multiple accept="image/jpeg,image/jpg,image/png,image/webp" onchange="renderSelectedFilesPreview('resubmitProofPhotosInput', 'resubmitProofPhotosPreview', true)">
                        <small class="form-meta-note">At least one retained or newly uploaded proof photo is required.</small>
                        <div id="resubmitProofPhotosPreview" class="resubmit-proof-grid"></div>
                    </div>

                    <div class="form-group">
                        <label for="resubmitFeedback">Feedback</label>
                        <textarea id="resubmitFeedback" name="feedback" rows="3" placeholder="Summarize site visit response..."></textarea>
                    </div>
                    <div class="form-group">
                        <label for="resubmitRating">Rating</label>
                        <select id="resubmitRating" name="rating">
                            <option value="">Select rating</option>
                            <option value="1">1 - Poor</option>
                            <option value="2">2 - Fair</option>
                            <option value="3">3 - Good</option>
                            <option value="4">4 - Very Good</option>
                            <option value="5">5 - Excellent</option>
                        </select>
                    </div>
                </div>

                <input type="hidden" id="resubmitScheduledAt" name="scheduled_at">

                <div class="complete-site-visit-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeResubmitSiteVisitModal()">Cancel</button>
                    <button type="submit" class="btn btn-success">Resubmit For Verification</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Request Closer Modal -->
<div id="requestCloserModal" class="modal">
    <div class="modal-content request-kyc-modal">
        <div class="request-kyc-head">
            <div>
                <h3 id="requestCloserModalTitle">Fill KYC Form</h3>
                <p id="requestCloserModalSubtitle">Close verification ke baad full KYC yahin se submit karo.</p>
            </div>
            <button type="button" class="btn btn-secondary" onclick="closeRequestCloserModal()">Close</button>
        </div>

        <div id="requestCloserModalNote" class="request-kyc-note">
            Customer, nominee, identity details, KYC documents, aur proof photos required hain. Submit hone ke baad CRM review start hoga.
        </div>

        <div id="requestCloserExistingFiles" class="request-kyc-note" hidden></div>
        <div id="requestCloserStepper" class="request-kyc-stepper" hidden></div>
        <div id="requestCloserStepCaption" class="request-kyc-step-caption" hidden></div>

        <div class="request-kyc-existing-shell" id="requestCloserExistingFilesPanel">
            <div class="request-kyc-existing-group">
                <div class="request-kyc-existing-title">Existing KYC Documents</div>
                <div id="requestCloserExistingKycDocs" class="request-kyc-existing-grid"></div>
            </div>
            <div class="request-kyc-existing-group">
                <div class="request-kyc-existing-title">Existing Proof Photos</div>
                <div id="requestCloserExistingProofPhotos" class="request-kyc-existing-grid"></div>
            </div>
        </div>

        <form id="closingRequestForm" class="request-kyc-form-grid"></form>

        <div class="request-kyc-actions">
            <button type="button" class="btn btn-secondary" id="requestCloserBackButton" onclick="changeRequestCloserStep(-1)">Back</button>
            <button type="button" class="btn btn-secondary" onclick="closeRequestCloserModal()">Cancel</button>
            <button type="button" class="btn btn-secondary" id="requestCloserSaveDraftButton" onclick="submitRequestCloser('draft')">Save Draft</button>
            <button type="button" class="btn btn-success" id="requestCloserNextButton" onclick="changeRequestCloserStep(1)">Next</button>
            <button type="button" class="btn btn-success" id="requestCloserSubmitButton" onclick="submitRequestCloser('submit')">Submit KYC</button>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div id="siteVisitSuccessModal" class="modal">
    <div class="modal-content" style="max-width: 400px; text-align: center; padding: 40px 30px;">
        <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);">
            <i class="fas fa-check" style="font-size: 40px; color: white; font-weight: bold;"></i>
        </div>
        <h3 style="font-size: 20px; font-weight: 600; color: #333; margin-bottom: 12px;">Success!</h3>
        <p id="siteVisitSuccessMessage" style="font-size: 16px; color: #666; margin-bottom: 30px; line-height: 1.5;"></p>
        <button onclick="closeSiteVisitSuccessModal()" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border: none; padding: 12px 32px; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3); transition: all 0.2s;" onmouseover="this.style.transform='scale(1.05)'; this.style.boxShadow='0 6px 16px rgba(16, 185, 129, 0.4)';" onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='0 4px 12px rgba(16, 185, 129, 0.3)';">
            <i class="fas fa-check mr-2"></i>OK
        </button>
    </div>
</div>

<style>
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}
.modal.show {
    display: flex;
    overflow-y: auto;
    padding: 18px 0;
}
.modal-content {
    background: white;
    padding: 24px;
    border-radius: 12px;
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
}
.mark-dead-modal .modal-content {
    padding: 0;
    overflow: hidden;
}
.mark-dead-modal-card {
    width: min(520px, 92vw);
    border: 1px solid #f3d0d0;
    border-radius: 24px;
    box-shadow: 0 28px 60px rgba(15, 23, 42, 0.22);
    background: linear-gradient(180deg, #ffffff 0%, #fffafa 100%);
}
.mark-dead-modal-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    padding: 24px 24px 10px;
}
.mark-dead-modal-head h3 {
    margin: 0;
    color: #111827;
    font-size: 32px;
    line-height: 1.05;
    letter-spacing: -0.03em;
}
.mark-dead-modal-head p {
    margin: 10px 0 0;
    color: #6b7280;
    font-size: 14px;
    line-height: 1.5;
}
.mark-dead-close {
    width: 40px;
    height: 40px;
    border: none;
    border-radius: 999px;
    background: #f8fafc;
    color: #6b7280;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.mark-dead-modal-alert {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    margin: 0 24px 20px;
    padding: 14px 16px;
    border: 1px solid #fecaca;
    border-radius: 18px;
    background: linear-gradient(135deg, #fff4f4 0%, #fff8f8 100%);
}
.mark-dead-modal-alert i {
    color: #dc2626;
    font-size: 18px;
    margin-top: 1px;
}
.mark-dead-modal-alert strong,
.mark-dead-modal-alert span {
    display: block;
}
.mark-dead-modal-alert strong {
    color: #b91c1c;
    margin-bottom: 4px;
}
.mark-dead-modal-alert span {
    color: #7f1d1d;
    font-size: 13px;
    line-height: 1.45;
}
.mark-dead-form-group {
    padding: 0 24px 8px;
    margin-bottom: 0;
}
.mark-dead-form-group label {
    display: block;
    margin-bottom: 10px;
    font-size: 15px;
    font-weight: 700;
    color: #111827;
}
.mark-dead-form-group textarea {
    width: 100%;
    min-height: 132px;
    padding: 14px 16px;
    border: 1px solid #f3c2c2;
    border-radius: 18px;
    background: #fff;
    color: #111827;
    resize: vertical;
    font-size: 14px;
    line-height: 1.45;
}
.mark-dead-form-group textarea:focus {
    outline: none;
    border-color: #ef4444;
    box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.12);
}
.mark-dead-modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding: 20px 24px 24px;
}
.mark-dead-modal-actions .btn {
    min-width: 140px;
    min-height: 48px;
    border-radius: 14px;
}
.mark-dead-modal-actions .btn:disabled {
    opacity: 0.7;
    cursor: wait;
}
.form-group {
    margin-bottom: 16px;
}
.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #333;
}
.complete-site-visit-modal-card {
    display: flex;
    flex-direction: column;
    width: min(760px, 92vw);
    max-height: min(88vh, 920px);
    background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
    border: 1px solid #dce9e2;
    box-shadow: 0 24px 48px rgba(23, 97, 168, 0.16);
}
.complete-site-visit-modal-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    padding: 24px 28px 18px;
    background: linear-gradient(135deg, #f7fcf9 0%, #edf5ff 100%);
    border-bottom: 1px solid #d7e5f6;
}
.complete-site-visit-modal-head h3 {
    margin: 0;
    font-size: 28px;
    line-height: 1.1;
    color: #0f3d67;
}
.complete-site-visit-modal-head p {
    margin: 8px 0 0;
    color: #62778b;
    font-size: 14px;
}
.complete-site-visit-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 8px 14px;
    border-radius: 999px;
    background: #e7f0ff;
    color: #1761A8;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}
.complete-site-visit-modal-body {
    flex: 1 1 auto;
    overflow-y: auto;
    padding: 24px 28px 12px;
}
.complete-site-visit-alert {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 14px 16px;
    margin-bottom: 20px;
    border: 1px solid #ffd4d4;
    border-radius: 16px;
    background: linear-gradient(135deg, #fff5f5 0%, #fffafa 100%);
}
.complete-site-visit-alert i {
    font-size: 20px;
    color: #dc2626;
    margin-top: 2px;
}
.complete-site-visit-alert strong,
.complete-site-visit-alert span {
    display: block;
}
.complete-site-visit-alert strong {
    color: #b42318;
    margin-bottom: 3px;
}
.complete-site-visit-alert span {
    color: #7a5c5c;
    font-size: 13px;
}
.complete-site-visit-upload {
    padding: 18px;
    margin-bottom: 20px;
    border: 1px solid #dce9e2;
    border-radius: 18px;
    background: #f9fcff;
}
.complete-site-visit-upload label,
.complete-site-visit-field label {
    display: block;
    margin-bottom: 10px;
    font-size: 15px;
    font-weight: 700;
    color: #123f68;
}
.complete-site-visit-upload input[type="file"] {
    width: 100%;
    padding: 12px;
    border: 1px dashed #8eb5dc;
    border-radius: 14px;
    background: #fff;
}
.complete-site-visit-preview {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 14px;
}
.complete-site-visit-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(220px, 0.52fr);
    gap: 18px;
}
.complete-site-visit-field {
    margin-bottom: 0;
}
.complete-site-visit-field-wide {
    grid-column: 1 / -1;
}
.complete-site-visit-field input[type="text"],
.complete-site-visit-field textarea,
.complete-site-visit-field select {
    width: 100%;
    border: 1px solid #d4dfeb;
    border-radius: 14px;
    padding: 14px 15px;
    color: #183c2c;
    background: #fff;
}
.complete-site-visit-field textarea {
    min-height: 120px;
    resize: vertical;
}
.complete-site-visit-field small {
    display: block;
    margin-top: 8px;
    color: #6b7280;
}
.complete-site-visit-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding: 10px;
    min-height: 52px;
    margin-bottom: 8px;
    border: 1px solid #d4dfeb;
    border-radius: 14px;
    background: #fff;
}
.complete-site-visit-checks {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    padding: 12px;
    border: 1px solid #d4dfeb;
    border-radius: 14px;
    background: #fff;
}
.complete-site-visit-checks label {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 13px;
    border: 1px solid #d7e5de;
    border-radius: 999px;
    background: #f9fffb;
    color: #143d2b;
    font-weight: 700;
}
.complete-site-visit-upload input[type="file"]:focus,
.complete-site-visit-field input[type="text"]:focus,
.complete-site-visit-field textarea:focus,
.complete-site-visit-field select:focus {
    outline: none;
    border-color: #1761A8;
    box-shadow: 0 0 0 3px rgba(23, 97, 168, 0.12);
}
.complete-site-visit-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding: 18px 28px 24px;
    border-top: 1px solid #e4edf6;
    background: #ffffff;
}
.complete-site-visit-actions .btn {
    min-width: 120px;
    min-height: 46px;
    border-radius: 12px;
    font-weight: 700;
}
.resubmit-site-visit-modal-card {
    display: flex;
    flex-direction: column;
    width: min(980px, 94vw);
    max-height: min(90vh, 980px);
    background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
    border: 1px solid #dce9e2;
    box-shadow: 0 24px 48px rgba(23, 97, 168, 0.16);
}
.resubmit-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px 20px;
    margin-top: 18px;
}
.resubmit-form-grid .form-wide {
    grid-column: 1 / -1;
}
.resubmit-form-grid input,
.resubmit-form-grid select,
.resubmit-form-grid textarea {
    width: 100%;
    padding: 14px 16px;
    border: 1px solid #d5dfdb;
    border-radius: 14px;
    font-size: 15px;
    background: #fff;
    color: #1f2937;
}
.resubmit-form-grid input:focus,
.resubmit-form-grid select:focus,
.resubmit-form-grid textarea:focus {
    outline: none;
    border-color: #0f6d44;
    box-shadow: 0 0 0 4px rgba(15, 109, 68, 0.08);
}
.resubmit-proof-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 12px;
}
.resubmit-proof-card {
    position: relative;
    width: 110px;
    height: 110px;
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid #d8e3dc;
    background: #fff;
}
.resubmit-proof-card img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.resubmit-proof-remove {
    position: absolute;
    top: 6px;
    right: 6px;
    width: 28px;
    height: 28px;
    border: none;
    border-radius: 999px;
    background: rgba(190, 24, 93, 0.92);
    color: #fff;
    cursor: pointer;
}
.resubmit-proof-empty {
    margin: 0;
    color: #6b7280;
    font-size: 0.88rem;
}
@media (max-width: 768px) {
    .mark-dead-modal-card {
        width: min(520px, 94vw);
        border-radius: 22px;
    }
    .mark-dead-modal-head,
    .mark-dead-form-group,
    .mark-dead-modal-actions {
        padding-left: 18px;
        padding-right: 18px;
    }
    .mark-dead-modal-head {
        padding-top: 20px;
    }
    .mark-dead-modal-head h3 {
        font-size: 24px;
    }
    .mark-dead-modal-alert {
        margin-left: 18px;
        margin-right: 18px;
    }
    .mark-dead-modal-actions {
        flex-direction: column-reverse;
    }
    .mark-dead-modal-actions .btn {
        width: 100%;
    }
    .complete-site-visit-modal-card {
        width: min(760px, 95vw);
        max-height: 90vh;
    }
    .complete-site-visit-modal-head,
    .complete-site-visit-modal-body,
    .complete-site-visit-actions {
        padding-left: 18px;
        padding-right: 18px;
    }
    .complete-site-visit-modal-head {
        flex-direction: column;
        align-items: stretch;
    }
    .complete-site-visit-grid,
    .resubmit-form-grid {
        grid-template-columns: 1fr;
    }
    .complete-site-visit-actions {
        flex-direction: column-reverse;
    }
    .complete-site-visit-actions .btn {
        width: 100%;
    }
}
.btn-secondary {
    background: #6b7280;
    color: white;
}
.btn-secondary:hover {
    background: linear-gradient(135deg, #15803d 0%, #166534 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    background: #4b5563;
}

/* Project Tags Styling */
.complete-project-tags-container {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding: 8px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    min-height: 42px;
    background: white;
    margin-bottom: 8px;
}

.complete-project-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 500;
}

.complete-project-tag-text {
    user-select: none;
}

.complete-project-tag-remove {
    cursor: pointer;
    font-weight: bold;
    font-size: 16px;
    line-height: 1;
    margin-left: 4px;
    opacity: 0.9;
    transition: opacity 0.2s;
}

.complete-project-tag-remove:hover {
    opacity: 1;
}
</style>
@endpush

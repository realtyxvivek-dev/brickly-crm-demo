@extends('sales-manager.layout')

@section('title', 'Meetings - Senior Manager')
@section('page-title', 'Meetings')

@push('styles')
<style>
    .asm-inline-filter-panel {
        border: 1px solid #d7e9f5;
        border-radius: 18px;
        box-shadow: 0 14px 34px rgba(0, 73, 112, 0.06);
        background: linear-gradient(135deg, #ffffff 0%, #f6fbff 100%);
        padding: 18px;
        margin-bottom: 18px;
    }
    .asm-personal-desk {
        border: 1px solid #d7e9f5;
        border-radius: 22px;
        background: linear-gradient(135deg, #ffffff 0%, #f2f9ff 100%);
        box-shadow: 0 18px 42px rgba(0, 73, 112, 0.08);
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
        background: rgba(0, 115, 177, 0.08);
        pointer-events: none;
    }
    .asm-personal-desk-copy {
        position: relative;
        z-index: 1;
        min-width: 0;
    }
    .asm-personal-desk-kicker {
        margin: 0 0 8px;
        color: #0073b1;
        font-size: 12px;
        font-weight: 900;
        letter-spacing: 0.14em;
        text-transform: uppercase;
    }
    .asm-personal-desk-title {
        margin: 0;
        color: #003b5c;
        font-size: clamp(24px, 3vw, 34px);
        font-weight: 900;
        letter-spacing: -0.02em;
        line-height: 1.08;
    }
    .asm-personal-desk-subtitle {
        margin: 8px 0 0;
        color: #567083;
        font-size: 14px;
        font-weight: 600;
    }
    .asm-personal-desk-actions {
        position: relative;
        z-index: 1;
        display: flex;
        align-items: center;
        gap: 10px;
        flex: 0 0 auto;
    }
    .asm-desk-date {
        border: 1px solid #d7e9f5;
        border-radius: 16px;
        background: #ffffff;
        color: #003b5c;
        padding: 12px 18px;
        min-width: 150px;
        text-align: center;
        box-shadow: 0 12px 28px rgba(0, 73, 112, 0.07);
    }
    .asm-desk-date strong {
        display: block;
        font-size: 18px;
        line-height: 1;
    }
    .asm-desk-date span {
        display: block;
        margin-top: 5px;
        color: #6b8797;
        font-size: 12px;
        font-weight: 700;
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
        color: #003b5c;
        font-size: 0.95rem;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .asm-inline-filters-row select:focus,
    .asm-inline-filters-row input:focus {
        outline: none;
        border-color: #0073b1;
        box-shadow: 0 0 0 4px rgba(0, 115, 177, 0.12);
    }
    .asm-inline-filter-date {
        display: none;
    }
    .asm-inline-filter-date.show {
        display: block;
    }
    #meetingsContainer {
        display: grid;
        grid-template-columns: repeat(3, minmax(300px, 1fr));
        gap: 18px;
        align-items: stretch;
    }
    #meetingsContainer.list-view {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    .meeting-card {
        background: linear-gradient(180deg, #ffffff 0%, #f8fcff 100%);
        padding: 18px;
        border-radius: 20px;
        box-shadow: 0 18px 45px rgba(0, 73, 112, 0.08);
        border: 1px solid #d7e9f5;
        border-left: 4px solid #0073b1;
        display: flex;
        flex-direction: column;
        min-height: 100%;
        transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
    }
    .meeting-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 24px 52px rgba(0, 73, 112, 0.12);
    }
    .meeting-card.completed {
        border-left-color: #10b981;
    }
    .meeting-card.cancelled {
        border-left-color: #ef4444;
    }
    .meeting-card.pending-verification {
        border-left-color: #f59e0b;
    }
    .meeting-header {
        display: flex;
        flex-direction: column;
        margin-bottom: 10px;
    }
    .meeting-topline {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
    }
    .meeting-actions {
        margin-top: auto;
        display: grid;
        grid-template-columns: 1fr;
        gap: 8px;
    }
    .meeting-direct-actions {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 6px;
    }
    .meeting-direct-actions .btn {
        width: 100%;
        min-height: 40px;
    }
    .meeting-actions .btn {
        width: 100%;
        margin-top: 0;
        margin-left: 0;
    }
    .card-action-menu {
        position: relative;
        width: 100%;
    }
    .card-action-trigger {
        width: 100%;
    }
    .card-action-dropdown {
        position: absolute;
        top: calc(100% + 8px);
        left: 0;
        right: 0;
        z-index: 20;
        display: none;
        flex-direction: column;
        gap: 8px;
        padding: 10px;
        border-radius: 16px;
        background: #ffffff;
        border: 1px solid #d7e9f5;
        box-shadow: 0 20px 40px rgba(0, 73, 112, 0.14);
        min-width: 100%;
    }
    .card-action-dropdown.show {
        display: flex;
    }
    .card-action-item {
        width: 100%;
        border: none;
        border-radius: 12px;
        padding: 10px 12px;
        font-size: 0.86rem;
        font-weight: 700;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: flex-start;
        gap: 8px;
        cursor: pointer;
    }
    .card-action-item.btn-success,
    .card-action-item.btn-danger,
    .card-action-item.btn-primary,
    .card-action-item.btn-warning {
        width: 100%;
        margin: 0;
    }
    .btn-call {
        background: #e8f4fb;
        color: #0073b1;
        border: 1px solid #b7dff4;
        box-shadow: none;
    }
    .btn-call:hover {
        background: #dff0fa;
        transform: translateY(-1px);
        box-shadow: 0 10px 20px rgba(0, 115, 177, 0.12);
    }
    .meeting-info h3 {
        font-size: 1.2rem;
        font-weight: 700;
        color: #003b5c;
        letter-spacing: -0.02em;
        margin-bottom: 4px;
    }
    .meeting-subtitle {
        color: #64748b;
        font-size: 0.9rem;
        margin-bottom: 10px;
    }
    .meeting-remark {
        color: #475569;
        font-size: 0.88rem;
        line-height: 1.45;
        background: #f7fbff;
        border: 1px solid #dcecf6;
        border-radius: 12px;
        padding: 9px 11px;
        min-height: 0;
        margin-bottom: 10px;
    }
    .meeting-remark strong {
        display: block;
        font-size: 0.74rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #94a3b8;
        margin-bottom: 6px;
    }
    .meeting-secondary {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        color: #64748b;
        font-size: 0.82rem;
        margin-bottom: 10px;
    }
    .meeting-secondary span {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .meeting-status-row {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 0;
        margin-bottom: 4px;
    }
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
        background: #dbeafe;
        color: #1e40af;
    }
    .badge-completed {
        background: #e8f4fb;
        color: #0073b1;
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
        background: #e8f4fb;
        color: #0073b1;
    }
    .badge-confirmed {
        background: #e8f4fb;
        color: #0073b1;
    }
    .badge-pending-conf {
        background: #fef3c7;
        color: #92400e;
    }
    .badge-cancelled-conf {
        background: #fee2e2;
        color: #991b1b;
    }
    .badge-awaiting {
        background: #fff7ed;
        color: #c2410c;
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
        background: linear-gradient(135deg, #0073b1 0%, #005f91 100%);
        color: white;
        box-shadow: 0 10px 22px rgba(0, 115, 177, 0.22);
    }
    .btn-primary:hover {
        background: linear-gradient(135deg, #00669e 0%, #004d78 100%);
        transform: translateY(-1px);
        box-shadow: 0 14px 28px rgba(0, 115, 177, 0.28);
    }
    .btn-success {
        background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
        color: white;
        box-shadow: 0 10px 22px rgba(22, 163, 74, 0.18);
    }
    .btn-success:hover {
        background: linear-gradient(135deg, #15803d 0%, #166534 100%);
        transform: translateY(-1px);
        box-shadow: 0 14px 28px rgba(22, 163, 74, 0.24);
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
        color: #0f172a;
        letter-spacing: -0.02em;
        margin: 0;
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
        background: linear-gradient(135deg, #0073b1 0%, #005f91 100%);
        color: #fff;
        box-shadow: 0 10px 18px rgba(0, 115, 177, 0.18);
    }
    #meetingsContainer.list-view .meeting-card {
        flex-direction: row;
        align-items: flex-start;
        gap: 14px;
        padding: 14px 16px;
    }
    #meetingsContainer.list-view .meeting-header {
        flex: 1 1 auto;
        margin-bottom: 0;
    }
    #meetingsContainer.list-view .meeting-actions {
        flex: 0 0 170px;
        margin-top: 0;
    }
    #meetingsContainer.list-view .meeting-direct-actions {
        grid-template-columns: 1fr;
    }
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
        flex-wrap: nowrap;
        align-items: center;
    }
    .filters .filter-select,
    .filters .filter-btn {
        flex: 1;
        width: 25%;
        min-width: 0;
        box-sizing: border-box;
    }
    .filter-select,
    .filters input[type="date"] {
        padding: 10px 12px;
        border: 1px solid #d7e0d9;
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
        #meetingsContainer {
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
            grid-template-columns: repeat(3, minmax(0, 1fr));
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
        .asm-page-title {
            text-align: center;
            font-size: 1.75rem;
        }
        #meetingsContainer {
            grid-template-columns: 1fr;
            gap: 1rem;
            max-width: 420px;
            margin: 0 auto;
        }
        .meeting-card {
            padding: 14px;
            border-radius: 16px;
        }
        .meeting-info h3 {
            font-size: 1.1rem;
        }
        .meeting-subtitle,
        .meeting-secondary,
        .meeting-remark {
            font-size: 0.84rem;
        }
        .meeting-actions {
            gap: 8px;
        }
        .card-action-dropdown {
            position: static;
            margin-top: 8px;
        }
        .view-toggle-group {
            width: auto;
            justify-content: center;
            margin: 0 auto;
        }
        .view-toggle-btn {
            justify-content: center;
        }
        #meetingsContainer.list-view .meeting-card {
            flex-direction: column;
            align-items: stretch;
        }
        #meetingsContainer.list-view .meeting-actions {
            flex: 1 1 auto;
        }
        .meeting-direct-actions {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .meeting-remark {
            margin-bottom: 8px;
        }
        .filters {
            flex-direction: row;
            flex-wrap: wrap;
            gap: 10px;
            border: 1px solid #dfe7e2;
            border-radius: 16px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fcfa 100%);
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
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
        .form-group {
            margin-bottom: 14px;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            font-size: 14px;
        }
        .form-group > div[style*="display: flex"] {
            flex-direction: column;
            gap: 10px;
        }
        .form-group > div[style*="display: flex"] button {
            width: 100%;
        }
        .complete-meeting-modal-head,
        .complete-meeting-modal-body,
        .complete-meeting-actions {
            padding-left: 18px;
            padding-right: 18px;
        }
        .complete-meeting-modal-head {
            flex-direction: column;
            align-items: stretch;
        }
        .complete-meeting-grid {
            grid-template-columns: 1fr;
        }
        .complete-meeting-actions {
            flex-direction: column-reverse;
        }
        .complete-meeting-actions .btn {
            width: 100%;
        }
    }

    .meeting-summary-strip {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        margin-bottom: 18px;
    }
    .meeting-summary-card {
        border: 1px solid #d7e9f5;
        border-radius: 16px;
        background: #ffffff;
        padding: 16px;
        box-shadow: 0 12px 28px rgba(0, 73, 112, 0.06);
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
    .meeting-summary-card:hover,
    .meeting-summary-card.active {
        border-color: #0073b1;
        box-shadow: 0 18px 36px rgba(0, 115, 177, 0.14);
        transform: translateY(-1px);
    }
    .meeting-summary-card.active {
        background: linear-gradient(135deg, #0073b1 0%, #005f91 100%);
    }
    .meeting-summary-card.active span,
    .meeting-summary-card.active strong {
        color: #ffffff;
    }
    .meeting-summary-card.active .meeting-summary-icon {
        background: rgba(255, 255, 255, 0.18);
        color: #ffffff;
    }
    .meeting-card.is-demo {
        border-style: solid;
    }
    .meeting-demo-chip {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 5px 10px;
        border-radius: 999px;
        background: #e8f4fb;
        color: #0073b1;
        font-size: 0.72rem;
        font-weight: 800;
    }
    .meeting-summary-card span {
        color: #4b6f82;
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }
    .meeting-summary-card strong {
        color: #003b5c;
        font-size: 28px;
        line-height: 1;
    }
    .meeting-summary-icon {
        width: 40px;
        height: 40px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #0073b1;
        background: #e8f4fb;
    }
    .meeting-card-shell {
        display: grid;
        gap: 12px;
    }
    .asm-followup-style-layout {
        border: 1px solid #d7e9f5;
        border-radius: 22px;
        background: linear-gradient(135deg, #ffffff 0%, #f2f9ff 100%);
        box-shadow: 0 22px 50px rgba(0, 73, 112, 0.08);
        padding: 18px;
        display: grid;
        grid-template-columns: minmax(250px, 300px) minmax(0, 1fr);
        gap: 18px;
    }
    .asm-followup-style-layout .meeting-summary-strip {
        grid-template-columns: 1fr;
        gap: 12px;
        margin-bottom: 0;
        align-self: start;
        align-content: start;
        grid-auto-rows: minmax(96px, auto);
    }
    .asm-followup-work-area {
        min-width: 0;
        border: 1px solid #d7e9f5;
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
        border-bottom: 1px solid #d7e9f5;
    }
    .asm-followup-work-head h2 {
        margin: 0;
        color: #003b5c;
        font-size: 1.15rem;
        font-weight: 900;
    }
    .asm-followup-work-head p {
        margin: 4px 0 0;
        color: #567083;
        font-size: 0.85rem;
        font-weight: 600;
    }
    .asm-followup-work-body {
        padding: 16px;
    }
    .asm-followup-work-body #meetingsContainer,
    .asm-followup-work-body #meetingsContainer.list-view {
        grid-template-columns: 1fr;
        gap: 0;
        align-items: stretch;
    }
    .asm-followup-work-body .meeting-card--leadrow {
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
    .asm-followup-work-body .meeting-card--leadrow:first-child {
        border-top-left-radius: 16px;
        border-top-right-radius: 16px;
    }
    .asm-followup-work-body .meeting-card--leadrow:last-child {
        border-bottom: 0;
        border-bottom-left-radius: 16px;
        border-bottom-right-radius: 16px;
    }
    .asm-followup-work-body .meeting-card--leadrow:hover {
        box-shadow: inset 0 0 0 1px #b7dff4;
        transform: none;
    }
    .meeting-card__leadrow-main {
        min-width: 0;
        display: grid;
        gap: 8px;
    }
    .meeting-card__leadrow-actions {
        display: grid;
        gap: 8px;
        align-self: stretch;
        align-content: center;
    }
    .meeting-card__leadrow-actions .meeting-actions {
        margin: 0;
        grid-template-columns: 1fr;
        gap: 8px;
    }
    .meeting-card__leadrow-actions .meeting-direct-actions {
        grid-template-columns: 1fr;
        gap: 8px;
    }
    .meeting-card__leadrow-actions .btn {
        min-height: 42px;
    }
    .meeting-topline h3 {
        margin-bottom: 6px;
    }
    .meeting-avatar {
        width: 44px;
        height: 44px;
        border-radius: 16px;
        background: linear-gradient(135deg, #0073b1 0%, #005f91 100%);
        color: #ffffff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        flex: 0 0 44px;
        box-shadow: 0 12px 22px rgba(0, 115, 177, 0.16);
    }
    .meeting-primary-row {
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }
    .meeting-phone-link {
        color: #567083;
        font-size: 0.9rem;
        font-weight: 700;
    }
    .meeting-secondary i {
        color: #0073b1;
    }
    .card-action-trigger {
        min-height: 40px;
    }
    @media (min-width: 769px) {
        .meeting-actions {
            grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
            align-items: stretch;
        }
        .meeting-direct-actions:has(+ .card-action-menu) {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
    @media (max-width: 768px) {
        .asm-personal-desk {
            align-items: stretch;
            flex-direction: column;
            padding: 18px;
            border-radius: 18px;
        }
        .asm-personal-desk-actions {
            width: 100%;
        }
        .asm-desk-date {
            width: 100%;
        }
        .meeting-summary-strip {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
        }
        .asm-followup-style-layout {
            grid-template-columns: 1fr;
            padding: 12px;
            border-radius: 18px;
        }
        .asm-followup-style-layout .meeting-summary-strip {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .asm-followup-work-head {
            padding: 14px;
        }
        .asm-followup-work-body {
            padding: 12px;
        }
        .asm-followup-work-body #meetingsContainer {
            grid-template-columns: 1fr;
        }
        .asm-followup-work-body .meeting-card--leadrow {
            grid-template-columns: 1fr;
            gap: 12px;
        }
        .meeting-summary-card {
            padding: 12px;
            border-radius: 14px;
            min-height: 82px;
        }
        .meeting-summary-card strong {
            font-size: 22px;
        }
        .meeting-summary-card span {
            font-size: 10px;
        }
        .meeting-avatar {
            width: 38px;
            height: 38px;
            border-radius: 14px;
        }
    }
</style>
@endpush

@section('content')
@include('sales-manager.partials.pipeline-tabs')
<div class="mb-6">
    <section class="asm-personal-desk">
        <div class="asm-personal-desk-copy">
            <p class="asm-personal-desk-kicker">Meeting Desk</p>
            <h1 class="asm-personal-desk-title">Meeting follow-through for {{ auth()->user()->name ?? 'your desk' }}</h1>
            <p class="asm-personal-desk-subtitle">Confirm, call, complete, or move verified meetings into site visits from one focused queue.</p>
        </div>
        <div class="asm-personal-desk-actions">
            <div class="asm-desk-date">
                <strong>{{ now()->format('h:i A') }}</strong>
                <span>{{ now()->format('d M Y') }}</span>
            </div>
        </div>
    </section>

    <div class="asm-inline-filter-panel">
        <div class="asm-inline-filters-row">
            <select id="statusFilter" onchange="loadMeetings()">
                <option value="">All Status</option>
                <option value="scheduled">Scheduled</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
            </select>
            <select id="verificationFilter" onchange="loadMeetings()">
                <option value="">All Verification</option>
                <option value="pending">Pending</option>
                <option value="verified">Verified</option>
                <option value="rejected">Rejected</option>
            </select>
            <select id="dateFilter" onchange="toggleCustomDate(); loadMeetings();">
                <option value="">All Dates</option>
                <option value="today" selected>Today</option>
                <option value="this_week">This Week</option>
                <option value="this_month">This Month</option>
                <option value="this_year">This Year</option>
                <option value="custom">Custom Date</option>
            </select>
            <input type="date" id="dateFrom" class="asm-inline-filter-date" onchange="loadMeetings()">
            <input type="date" id="dateTo" class="asm-inline-filter-date" onchange="loadMeetings()">
        </div>
    </div>

    <div class="asm-followup-style-layout">
        <aside class="meeting-summary-strip" id="meetingSummaryStrip">
            <button type="button" class="meeting-summary-card active" data-meeting-summary="today" onclick="applyMeetingSummaryFilter('today')">
                <div>
                    <span>Today Meetings</span>
                    <strong id="meetingSummaryToday">0</strong>
                </div>
                <div class="meeting-summary-icon"><i class="fas fa-calendar-day"></i></div>
            </button>
            <button type="button" class="meeting-summary-card" data-meeting-summary="future" onclick="applyMeetingSummaryFilter('future')">
                <div>
                    <span>Future Scheduled</span>
                    <strong id="meetingSummaryScheduled">0</strong>
                </div>
                <div class="meeting-summary-icon"><i class="fas fa-clock"></i></div>
            </button>
            <button type="button" class="meeting-summary-card" data-meeting-summary="completed" onclick="applyMeetingSummaryFilter('completed')">
                <div>
                    <span>Completed Meetings</span>
                    <strong id="meetingSummaryCompleted">0</strong>
                </div>
                <div class="meeting-summary-icon"><i class="fas fa-circle-check"></i></div>
            </button>
            <button type="button" class="meeting-summary-card" data-meeting-summary="pending" onclick="applyMeetingSummaryFilter('pending')">
                <div>
                    <span>Pending Verification</span>
                    <strong id="meetingSummaryPending">0</strong>
                </div>
                <div class="meeting-summary-icon"><i class="fas fa-hourglass-half"></i></div>
            </button>
        </aside>

        <section class="asm-followup-work-area">
            <div class="asm-followup-work-head">
                <div>
                    <h2>Meeting Queue</h2>
                    <p>Personal meeting follow-through, call action, and site visit movement.</p>
                </div>
            </div>
            <div class="asm-followup-work-body">
                <div id="meetingsContainer">
                    <div class="empty-state">
                        <i class="fas fa-spinner fa-spin"></i>
                        <p>Loading meetings...</p>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const API_BASE_URL = '{{ url("/api/sales-manager") }}';
    const ASM_SECTION_VIEW_PREFERENCES = @json($sectionViewPreferences ?? []);
    const ASM_SECTION_VIEW_SAVE_URL = @json(route('sales-manager.settings.update'));
    const ALLOW_PRIVILEGED_PAST_SCHEDULING = @json((bool) (auth()->user()?->isAdmin() || auth()->user()?->isCrm()));

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

    function formatPhoneForDialer(phoneNumber) {
        if (!phoneNumber) return '';
        const cleanPhone = String(phoneNumber).replace(/[^\d+]/g, '');
        return cleanPhone.startsWith('+') ? cleanPhone : `+${cleanPhone}`;
    }

    function callMeetingLead(phoneNumber) {
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
                    window.handleManagerAuthFailure('meetings');
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

    let leadOptionsCache = [];
    let isLeadOptionsLoaded = false;

    function setDefaultQuickMeetingTime() {
        const scheduledInput = document.getElementById('quickScheduledAt');
        if (!scheduledInput) return;
        const minDate = new Date();
        minDate.setMinutes(minDate.getMinutes() + 30);
        const iso = minDate.toISOString().slice(0, 16);
        if (ALLOW_PRIVILEGED_PAST_SCHEDULING) {
            scheduledInput.removeAttribute('min');
        } else {
            scheduledInput.min = iso;
        }
        if (!scheduledInput.value) {
            scheduledInput.value = iso;
        }
    }

    function toggleQuickMeetingModeFields() {
        const form = document.getElementById('quickMeetingForm');
        if (!form) return;
        
        const mode = form.querySelector('input[name="meeting_mode"]:checked')?.value;
        const onlineFields = document.getElementById('quickOnlineFields');
        const offlineFields = document.getElementById('quickOfflineFields');
        const locationInput = document.getElementById('quickLocationInput');
        const meetingLinkInput = form.querySelector('input[name="meeting_link"]');
        
        if (mode === 'online') {
            if (onlineFields) onlineFields.style.display = 'block';
            if (offlineFields) offlineFields.style.display = 'none';
            if (locationInput) locationInput.removeAttribute('required');
            if (meetingLinkInput) {
                meetingLinkInput.removeAttribute('required');
            }
        } else {
            if (onlineFields) onlineFields.style.display = 'none';
            if (offlineFields) offlineFields.style.display = 'block';
            if (locationInput) locationInput.setAttribute('required', 'required');
            if (meetingLinkInput) {
                meetingLinkInput.removeAttribute('required');
            }
        }
    }

    async function openQuickMeetingModal() {
        setDefaultQuickMeetingTime();
        document.getElementById('quickMeetingModal').classList.add('show');
        document.getElementById('quickMeetingError').textContent = '';

        if (!isLeadOptionsLoaded) {
            await loadLeadOptions();
            isLeadOptionsLoaded = true;
        }
        
        // Initialize meeting mode fields
        toggleQuickMeetingModeFields();
    }

    function closeQuickMeetingModal() {
        document.getElementById('quickMeetingModal').classList.remove('show');
        const form = document.getElementById('quickMeetingForm');
        if (form) {
            form.reset();
        }
        document.getElementById('leadSelect').value = '';
        document.getElementById('leadSearchInput').value = '';
        document.getElementById('quickMeetingError').textContent = '';
        // Reset to offline mode
        const offlineRadio = document.querySelector('#quickMeetingForm input[name="meeting_mode"][value="offline"]');
        if (offlineRadio) {
            offlineRadio.checked = true;
            toggleQuickMeetingModeFields();
        }
    }

    async function loadLeadOptions(search = '') {
        const query = search ? `?search=${encodeURIComponent(search)}` : '';
        const response = await apiCall(`/meetings/lead-options${query}`);

        if (response?.success) {
            leadOptionsCache = response.data || [];
            renderLeadOptions(leadOptionsCache);
        } else {
            document.getElementById('quickMeetingError').textContent = response?.message || 'Unable to load leads.';
        }
    }

    function renderLeadOptions(list) {
        const select = document.getElementById('leadSelect');
        if (!select) return;
        select.innerHTML = '<option value=\"\">Select lead</option>';
        list.forEach(lead => {
            const option = document.createElement('option');
            option.value = lead.id;
            option.textContent = `${lead.name || 'N/A'} (${lead.phone || 'N/A'})`;
            select.appendChild(option);
        });
    }

    function filterLeadOptions(term) {
        const searchTerm = term.trim().toLowerCase();
        if (!searchTerm) {
            renderLeadOptions(leadOptionsCache);
            return;
        }

        const filtered = leadOptionsCache.filter(lead => {
            const nameMatch = (lead.name || '').toLowerCase().includes(searchTerm);
            const phoneMatch = (lead.phone || '').toLowerCase().includes(searchTerm);
            return nameMatch || phoneMatch;
        });

        renderLeadOptions(filtered);
    }

    async function submitQuickMeeting(event) {
        if (event) {
            event.preventDefault();
        }
        
        const form = document.getElementById('quickMeetingForm');
        if (!form) {
            // Fallback for old button onclick
            const leadId = document.getElementById('leadSelect').value;
            const scheduledAt = document.getElementById('quickScheduledAt').value;
            const errorBox = document.getElementById('quickMeetingError');
            
            if (!leadId) {
                errorBox.textContent = 'Please select a lead.';
                return;
            }
            if (!scheduledAt) {
                errorBox.textContent = 'Please select date and time.';
                return;
            }
            return;
        }
        
        const formData = new FormData(form);
        const errorBox = document.getElementById('quickMeetingError');
        errorBox.textContent = '';

        const leadId = formData.get('lead_id');
        const scheduledAt = formData.get('scheduled_at');
        const meetingMode = formData.get('meeting_mode');
        const location = formData.get('location');
        const meetingLink = formData.get('meeting_link');

        if (!leadId) {
            errorBox.textContent = 'Please select a lead.';
            return;
        }

        if (!scheduledAt) {
            errorBox.textContent = 'Please select date and time.';
            return;
        }

        // Validate location for offline meetings
        if (meetingMode === 'offline' && !location) {
            errorBox.textContent = 'Location is required for offline meetings.';
            return;
        }

        const data = {
            lead_id: parseInt(leadId),
            meeting_sequence: parseInt(formData.get('meeting_sequence')) || 1,
            scheduled_at: new Date(scheduledAt).toISOString(),
            meeting_mode: meetingMode || 'offline',
            meeting_link: meetingLink || null,
            location: location || null,
            reminder_enabled: formData.get('reminder_enabled') === 'on',
            reminder_minutes: 5,
            meeting_notes: formData.get('meeting_notes') || null,
        };

        const result = await apiCall('/sales-manager/meetings/quick-schedule-with-reminder', {
            method: 'POST',
            body: JSON.stringify(data),
        });

        if (result?.success !== false) {
            closeQuickMeetingModal();
            if (typeof showNotification === 'function') {
                const message = 'Meeting scheduled successfully!' + (data.reminder_enabled ? ' You will get a reminder 5 minutes before.' : '');
                showNotification(message, 'success', 2500);
            } else {
                alert('Meeting scheduled successfully!');
            }
            loadMeetings();
        } else {
            errorBox.textContent = result?.message || 'Failed to schedule meeting.';
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

    function getMeetingRemark(meeting) {
        return meeting.meeting_notes || meeting.notes || meeting.feedback || meeting.location || 'No remark added yet.';
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

    function getInitials(name) {
        return String(name || 'M')
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map(part => part.charAt(0).toUpperCase())
            .join('') || 'M';
    }

    let meetingSummaryStableCounts = { today: 0, scheduled: 0, completed: 0, pending: 0 };

    function getMeetingSummaryCounts(list) {
        const todayIso = new Date().toISOString().slice(0, 10);
        return (Array.isArray(list) ? list : []).reduce((acc, meeting) => {
            const scheduledIso = meeting.scheduled_at ? new Date(meeting.scheduled_at).toISOString().slice(0, 10) : '';
            if (scheduledIso === todayIso) acc.today += 1;
            if (meeting.status === 'scheduled' && scheduledIso > todayIso) acc.scheduled += 1;
            if (meeting.status === 'completed') acc.completed += 1;
            if ((meeting.verification_status || '').toLowerCase() === 'pending') acc.pending += 1;
            return acc;
        }, { today: 0, scheduled: 0, completed: 0, pending: 0 });
    }

    function updateMeetingSummary(meetings) {
        const currentCounts = getMeetingSummaryCounts(meetings);
        const demoCounts = {
            today: buildDemoMeetings('today').length,
            scheduled: buildDemoMeetings('future').length,
            completed: buildDemoMeetings('completed').length,
            pending: buildDemoMeetings('pending').length,
        };
        meetingSummaryStableCounts = {
            today: Math.max(meetingSummaryStableCounts.today || 0, currentCounts.today || 0, demoCounts.today || 0),
            scheduled: Math.max(meetingSummaryStableCounts.scheduled || 0, currentCounts.scheduled || 0, demoCounts.scheduled || 0),
            completed: Math.max(meetingSummaryStableCounts.completed || 0, currentCounts.completed || 0, demoCounts.completed || 0),
            pending: Math.max(meetingSummaryStableCounts.pending || 0, currentCounts.pending || 0, demoCounts.pending || 0),
        };

        document.getElementById('meetingSummaryToday').textContent = meetingSummaryStableCounts.today;
        document.getElementById('meetingSummaryScheduled').textContent = meetingSummaryStableCounts.scheduled;
        document.getElementById('meetingSummaryCompleted').textContent = meetingSummaryStableCounts.completed;
        document.getElementById('meetingSummaryPending').textContent = meetingSummaryStableCounts.pending;
    }

    function setMeetingSummaryActive(type) {
        document.querySelectorAll('[data-meeting-summary]').forEach(card => {
            card.classList.toggle('active', card.getAttribute('data-meeting-summary') === type);
        });
    }

    function applyMeetingSummaryFilter(type) {
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
        } else if (type === 'pending') {
            verificationEl.value = 'pending';
        }

        setMeetingSummaryActive(type);
        toggleCustomDate();
        loadMeetings();
    }

    function buildDemoMeetings(activeType) {
        const now = new Date();
        const addDays = (days, hour) => {
            const date = new Date(now);
            date.setDate(date.getDate() + days);
            date.setHours(hour, 30, 0, 0);
            return date.toISOString();
        };
        const demosByType = {
            today: [
                { id: -501, _demo: true, customer_name: 'Riya Sharma Demo', phone: '9000001011', scheduled_at: addDays(0, 11), status: 'scheduled', verification_status: 'pending', customer_confirmation_status: 'pending', meeting_mode: 'offline', property_type: 'Independent Floor', remark: 'Today meeting for Omaxe front street requirement.' },
                { id: -502, _demo: true, customer_name: 'Aarav Sinha Demo', phone: '9000001012', scheduled_at: addDays(0, 13), status: 'scheduled', verification_status: 'pending', customer_confirmation_status: 'confirmed', meeting_mode: 'online', property_type: 'Anaxe Lincoln', remark: 'Customer wants payment plan discussion today.' },
                { id: -503, _demo: true, customer_name: 'Meera Gupta Demo', phone: '9000001013', scheduled_at: addDays(0, 16), status: 'scheduled', verification_status: 'pending', customer_confirmation_status: 'pending', meeting_mode: 'offline', property_type: 'Omaxe Front Street', remark: 'Site preference and budget confirmation meeting.' },
                { id: -504, _demo: true, customer_name: 'Kabir Arora Demo', phone: '9000001014', scheduled_at: addDays(0, 18), status: 'scheduled', verification_status: 'pending', customer_confirmation_status: 'confirmed', meeting_mode: 'offline', property_type: 'Independent Floor', remark: 'End of day meeting for unit shortlist.' },
            ],
            future: [
                { id: -511, _demo: true, customer_name: 'Karan Mehta Demo', phone: '9000001021', scheduled_at: addDays(1, 12), status: 'scheduled', verification_status: 'pending', customer_confirmation_status: 'confirmed', meeting_mode: 'online', property_type: 'Anaxe Lincoln', remark: 'Future meeting scheduled for project discussion.' },
                { id: -512, _demo: true, customer_name: 'Pooja Nair Demo', phone: '9000001022', scheduled_at: addDays(2, 15), status: 'scheduled', verification_status: 'pending', customer_confirmation_status: 'pending', meeting_mode: 'offline', property_type: 'Omaxe Front Street', remark: 'Future appointment for family consultation.' },
                { id: -513, _demo: true, customer_name: 'Sahil Khan Demo', phone: '9000001023', scheduled_at: addDays(3, 11), status: 'scheduled', verification_status: 'pending', customer_confirmation_status: 'confirmed', meeting_mode: 'offline', property_type: 'Independent Floor', remark: 'Price and availability review scheduled.' },
                { id: -514, _demo: true, customer_name: 'Anika Rao Demo', phone: '9000001024', scheduled_at: addDays(4, 17), status: 'scheduled', verification_status: 'pending', customer_confirmation_status: 'pending', meeting_mode: 'online', property_type: 'Anaxe Lincoln', remark: 'Future virtual meeting with senior decision maker.' },
            ],
            completed: [
                { id: -521, _demo: true, customer_name: 'Neha Kapoor Demo', phone: '9000001031', scheduled_at: addDays(-1, 16), status: 'completed', verification_status: 'verified', customer_confirmation_status: 'confirmed', meeting_mode: 'offline', property_type: 'Omaxe Front Street', remark: 'Meeting completed and ready for visit planning.' },
                { id: -522, _demo: true, customer_name: 'Vikram Singh Demo', phone: '9000001032', scheduled_at: addDays(-2, 13), status: 'completed', verification_status: 'verified', customer_confirmation_status: 'confirmed', meeting_mode: 'online', property_type: 'Independent Floor', remark: 'Requirement captured and next visit suggested.' },
                { id: -523, _demo: true, customer_name: 'Isha Malhotra Demo', phone: '9000001033', scheduled_at: addDays(-3, 12), status: 'completed', verification_status: 'verified', customer_confirmation_status: 'confirmed', meeting_mode: 'offline', property_type: 'Anaxe Lincoln', remark: 'Customer shortlisted tower options.' },
                { id: -524, _demo: true, customer_name: 'Rohit Bansal Demo', phone: '9000001034', scheduled_at: addDays(-4, 18), status: 'completed', verification_status: 'verified', customer_confirmation_status: 'confirmed', meeting_mode: 'offline', property_type: 'Omaxe Front Street', remark: 'Completed discussion; follow-up visit can be planned.' },
            ],
            pending: [
                { id: -531, _demo: true, customer_name: 'Amit Verma Demo', phone: '9000001041', scheduled_at: addDays(0, 14), status: 'scheduled', verification_status: 'pending', customer_confirmation_status: 'pending', meeting_mode: 'offline', property_type: 'Independent Floor', remark: 'Pending verification demo meeting for UI testing.' },
                { id: -532, _demo: true, customer_name: 'Sanya Tiwari Demo', phone: '9000001042', scheduled_at: addDays(1, 10), status: 'scheduled', verification_status: 'pending', customer_confirmation_status: 'confirmed', meeting_mode: 'online', property_type: 'Anaxe Lincoln', remark: 'Verification pending before next action.' },
                { id: -533, _demo: true, customer_name: 'Nitin Joshi Demo', phone: '9000001043', scheduled_at: addDays(2, 16), status: 'scheduled', verification_status: 'pending', customer_confirmation_status: 'pending', meeting_mode: 'offline', property_type: 'Omaxe Front Street', remark: 'Customer confirmation pending.' },
                { id: -534, _demo: true, customer_name: 'Priya Saxena Demo', phone: '9000001044', scheduled_at: addDays(3, 12), status: 'scheduled', verification_status: 'pending', customer_confirmation_status: 'confirmed', meeting_mode: 'offline', property_type: 'Independent Floor', remark: 'Waiting for admin verification.' },
            ],
        };
        const demo = [
            { id: -501, _demo: true, customer_name: 'Riya Sharma Demo', phone: '9000001011', scheduled_at: addDays(0, 15), status: 'scheduled', verification_status: 'pending', customer_confirmation_status: 'pending', meeting_mode: 'offline', property_type: 'Independent Floor', remark: 'Customer wants Omaxe front street meeting today.' },
            { id: -502, _demo: true, customer_name: 'Karan Mehta Demo', phone: '9000001012', scheduled_at: addDays(1, 12), status: 'scheduled', verification_status: 'pending', customer_confirmation_status: 'confirmed', meeting_mode: 'online', property_type: 'Anaxe Lincoln', remark: 'Future meeting scheduled for project discussion.' },
            { id: -503, _demo: true, customer_name: 'Neha Kapoor Demo', phone: '9000001013', scheduled_at: addDays(-1, 16), status: 'completed', verification_status: 'verified', customer_confirmation_status: 'confirmed', meeting_mode: 'offline', property_type: 'Omaxe Front Street', remark: 'Meeting completed and ready for visit planning.' },
            { id: -504, _demo: true, customer_name: 'Amit Verma Demo', phone: '9000001014', scheduled_at: addDays(2, 11), status: 'scheduled', verification_status: 'pending', customer_confirmation_status: 'pending', meeting_mode: 'offline', property_type: 'Independent Floor', remark: 'Pending verification demo meeting for UI testing.' },
        ];
        if (demosByType[activeType]) return demosByType[activeType];
        if (activeType === 'today') return demo.filter(item => new Date(item.scheduled_at).toISOString().slice(0, 10) === now.toISOString().slice(0, 10));
        if (activeType === 'future') return demo.filter(item => item.status === 'scheduled' && new Date(item.scheduled_at) > now);
        if (activeType === 'completed') return demo.filter(item => item.status === 'completed');
        if (activeType === 'pending') return demo.filter(item => item.verification_status === 'pending');
        return demo;
    }

    function setMeetingsView(view, shouldPersist = true) {
        const container = document.getElementById('meetingsContainer');
        if (!container) return;
        container.classList.toggle('list-view', view === 'list');
        document.querySelectorAll('.view-toggle-btn[data-view]').forEach((btn) => {
            btn.classList.toggle('active', btn.getAttribute('data-view') === view);
        });
        try {
            localStorage.setItem('asm_meetings_view', view);
        } catch (e) {}
        if (shouldPersist) {
            persistAsmSectionViewPreference('meetings', view);
        }
    }

    async function loadMeetings() {
        const container = document.getElementById('meetingsContainer');
        container.innerHTML = '<div class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>Loading...</p></div>';

        try {
            const status = document.getElementById('statusFilter').value;
            const verification = document.getElementById('verificationFilter').value;
            const dateFilter = document.getElementById('dateFilter').value;
            
            let url = '/meetings?';
            if (status) url += `status=${status}&`;
            if (!status) url += 'active_queue=1&';
            if (verification) url += `verification_status=${verification}&`;
            if (dateFilter) {
                url += `date_filter=${dateFilter}&`;
                if (dateFilter === 'custom') {
                    const dateFrom = document.getElementById('dateFrom').value;
                    const dateTo = document.getElementById('dateTo').value;
                    if (dateFrom) url += `date_from=${dateFrom}&`;
                    if (dateTo) url += `date_to=${dateTo}&`;
                }
            }

            const response = await apiCall(url);
            const realMeetings = response?.data || [];
            const activeSummary = document.querySelector('[data-meeting-summary].active')?.getAttribute('data-meeting-summary') || '';
            const demoMeetings = buildDemoMeetings(activeSummary);
            const meetings = [...realMeetings, ...demoMeetings];
            updateMeetingSummary(meetings);

            if (meetings.length === 0) {
                // Hide empty state on mobile - show nothing
                container.innerHTML = '';
                return;
            }

            const html = meetings.map(meeting => {
                const statusClass = meeting.status === 'completed' ? 'completed' : 
                                  meeting.status === 'cancelled' ? 'cancelled' : 
                                  meeting.verification_status === 'pending' && meeting.status === 'completed' ? 'pending-verification' : '';
                
                const statusBadge = meeting.status === 'scheduled' ? 'badge-scheduled' :
                                  meeting.status === 'completed' ? 'badge-completed' :
                                  'badge-cancelled';
                
                const verificationBadge = meeting.verification_status === 'verified' ? 'badge-verified' :
                                        meeting.verification_status === 'pending' ? 'badge-pending' : '';

                const confirmationStatusBadge = meeting.customer_confirmation_status === 'confirmed' ? 'badge-confirmed' :
                                              meeting.customer_confirmation_status === 'cancelled' ? 'badge-cancelled-conf' : 'badge-pending-conf';
                const confirmationStatusText = meeting.customer_confirmation_status || 'pending';
                const remark = truncateText(getMeetingRemark(meeting), 120);
                const isScheduled = meeting.status === 'scheduled';
                const isCompleted = meeting.status === 'completed';
                const isPendingVerification = isCompleted && meeting.verification_status === 'pending';
                const isVerified = meeting.verification_status === 'verified';

                const meetingActionItems = [];

                if (!meeting._demo && isScheduled && meeting.customer_confirmation_status !== 'cancelled') {
                    meetingActionItems.push(`
                        <button class="card-action-item btn btn-success" onclick="closeAllMeetingActionMenus(); showCompleteMeetingModal(${meeting.id});">
                            <i class="fas fa-check"></i>Complete
                        </button>
                    `);
                    meetingActionItems.push(`
                        <button class="card-action-item btn btn-danger" onclick="closeAllMeetingActionMenus(); showMarkDeadModal('meeting', ${meeting.id});">
                            <i class="fas fa-skull"></i>Mark as Dead
                        </button>
                    `);
                    meetingActionItems.push(`
                        <button class="card-action-item btn btn-warning" onclick="closeAllMeetingActionMenus(); showRescheduleMeetingModal(${meeting.id});">
                            <i class="fas fa-calendar-plus"></i>Reschedule
                        </button>
                    `);
                }

                if (!meeting._demo && isCompleted && isVerified) {
                    meetingActionItems.push(`
                        <button class="card-action-item btn btn-primary" onclick="closeAllMeetingActionMenus(); showConvertToSiteVisitModal(${meeting.id});">
                            <i class="fas fa-exchange-alt"></i>Convert to Site Visit
                        </button>
                    `);
                }

                if (!meeting._demo && isCompleted && meeting.verification_status !== 'pending') {
                    meetingActionItems.push(`
                        <button class="card-action-item btn btn-danger" onclick="closeAllMeetingActionMenus(); showMarkDeadModal('meeting', ${meeting.id});">
                            <i class="fas fa-skull"></i>Mark as Dead
                        </button>
                    `);
                }

                if (!meeting._demo && meeting.lead_id) {
                    meetingActionItems.push(`
                        <a href="/leads/${meeting.lead_id}" class="card-action-item btn btn-primary" onclick="closeAllMeetingActionMenus()">
                            <i class="fas fa-eye"></i>View Detail
                        </a>
                    `);
                }

                const meetingDirectActions = [];
                if (meeting.phone) {
                    meetingDirectActions.push(`
                        <button type="button" class="btn btn-call" onclick="callMeetingLead('${escapeHtml(meeting.phone)}')">
                            <i class="fas fa-phone"></i>Call
                        </button>
                    `);
                }
                if (!meeting._demo && meeting.lead_id) {
                    meetingDirectActions.push(`
                        <a href="/leads/${meeting.lead_id}" class="btn btn-primary">
                            <i class="fas fa-up-right-from-square"></i>Open Lead
                        </a>
                    `);
                }

                return `
                    <div class="meeting-card meeting-card--leadrow ${statusClass} ${meeting._demo ? 'is-demo' : ''}">
                        <div class="meeting-card__leadrow-main">
                            <div class="meeting-info">
                                <div class="meeting-primary-row">
                                    <div class="meeting-avatar">${getInitials(meeting.customer_name)}</div>
                                    <div class="meeting-topline" style="flex: 1; min-width: 0;">
                                        <div style="min-width: 0;">
                                            <h3>${meeting.customer_name || 'N/A'}</h3>
                                            <div class="meeting-phone-link"><i class="fas fa-phone"></i> ${meeting.phone || 'Phone not available'}</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="meeting-status-row">
                                    ${meeting._demo ? `<span class="meeting-demo-chip"><i class="fas fa-flask"></i> UI Demo</span>` : ''}
                                    <span class="badge ${statusBadge}">${meeting.status}</span>
                                    ${meeting.verification_status ? `<span class="badge ${verificationBadge}">${meeting.verification_status}</span>` : ''}
                                    <span class="badge ${confirmationStatusBadge}">${confirmationStatusText}</span>
                                    ${isPendingVerification ? `<span class="badge badge-awaiting">Awaiting Verification</span>` : ''}
                                </div>
                                <div class="meeting-remark">
                                    <strong>Remark</strong>
                                    ${escapeHtml(remark)}
                                </div>
                            </div>
                        </div>

                        <div class="meeting-secondary">
                            <span><i class="fas fa-calendar"></i>${new Date(meeting.scheduled_at).toLocaleString('en-IN', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</span>
                            ${meeting.meeting_mode ? `<span><i class="fas ${meeting.meeting_mode === 'online' ? 'fa-video' : 'fa-map-marker-alt'}"></i>${meeting.meeting_mode === 'online' ? 'Online' : 'Offline'}</span>` : ''}
                            ${meeting.property_type ? `<span><i class="fas fa-building"></i>${meeting.property_type}</span>` : ''}
                        </div>

                        <div class="meeting-card__leadrow-actions">
                            <div class="meeting-actions">
                                ${meetingDirectActions.length ? `<div class="meeting-direct-actions">${meetingDirectActions.join('')}</div>` : ''}
                                ${meetingActionItems.length ? `
                                    <div class="card-action-menu" data-meeting-action-menu="${meeting.id}">
                                        <button class="btn btn-primary card-action-trigger" onclick="toggleMeetingActionMenu(${meeting.id}, event)">
                                            <i class="fas fa-bolt"></i>Action
                                        </button>
                                        <div class="card-action-dropdown" id="meetingActionDropdown${meeting.id}">
                                            ${meetingActionItems.join('')}
                                        </div>
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                `;
            }).join('');

            container.innerHTML = html;
        } catch (error) {
            console.error('Error loading meetings:', error);
            container.innerHTML = '<div class="empty-state"><i class="fas fa-exclamation-triangle"></i><p>Error loading meetings</p></div>';
        }
    }

    let currentMeetingId = null;

    function closeAllMeetingActionMenus() {
        document.querySelectorAll('.card-action-dropdown.show').forEach(menu => {
            menu.classList.remove('show');
        });
    }

    function toggleMeetingActionMenu(id, event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }

        const targetMenu = document.getElementById(`meetingActionDropdown${id}`);
        if (!targetMenu) return;

        const shouldOpen = !targetMenu.classList.contains('show');
        closeAllMeetingActionMenus();

        if (shouldOpen) {
            targetMenu.classList.add('show');
        }
    }

    document.addEventListener('click', function(event) {
        if (!event.target.closest('[data-meeting-action-menu]')) {
            closeAllMeetingActionMenus();
        }
    });

    function showCompleteMeetingModal(id) {
        currentMeetingId = id;
        document.getElementById('completeMeetingModal').classList.add('show');
    }

    function closeCompleteMeetingModal() {
        document.getElementById('completeMeetingModal').classList.remove('show');
        document.getElementById('proofPhotosInput').value = '';
        document.getElementById('proofPhotosPreview').innerHTML = '';
        currentMeetingId = null;
    }

    // Helper function to complete the calling task
    async function completeCallingTask(taskId, taskType) {
        if (!taskId) return true; // No task to complete
        
        const apiToken = window.API_TOKEN || document.querySelector('meta[name="api-token"]')?.content;
        const apiBase = window.API_BASE_URL || '/api';
        
        if (!apiToken) {
            console.warn('API token not found, skipping task completion');
            return false;
        }
        
        try {
            let endpoint;
            if (taskType === 'Task') {
                // For manager tasks (Task model)
                endpoint = `${apiBase}/sales-manager/tasks/${taskId}/complete`;
            } else {
                // For telecaller tasks (TelecallerTask model)
                endpoint = `${apiBase}/telecaller/tasks/${taskId}/complete`;
            }
            
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${apiToken}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                }
            });
            
            if (response.ok) {
                return true;
            } else {
                const result = await response.json();
                console.error('Failed to complete task:', result.message || 'Unknown error');
                return false;
            }
        } catch (error) {
            console.error('Error completing task:', error);
            return false;
        }
    }

    function handleProofPhotosChange(event) {
        const files = event.target.files;
        const preview = document.getElementById('proofPhotosPreview');
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

    function showMeetingSuccessModal(message) {
        const modal = document.getElementById('meetingSuccessModal');
        const messageElement = document.getElementById('meetingSuccessMessage');
        if (messageElement) {
            messageElement.textContent = message;
        }
        if (modal) {
            modal.classList.add('show');
        }
    }

    function closeMeetingSuccessModal() {
        const modal = document.getElementById('meetingSuccessModal');
        if (modal) {
            modal.classList.remove('show');
        }
    }

    // Close modal on backdrop click
    document.getElementById('meetingSuccessModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeMeetingSuccessModal();
        }
    });

    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modal = document.getElementById('meetingSuccessModal');
            if (modal && modal.classList.contains('show')) {
                closeMeetingSuccessModal();
            }
        }
    });

    async function submitCompleteMeeting() {
        if (!currentMeetingId) return;

        const formData = new FormData();
        const photosInput = document.getElementById('proofPhotosInput');
        
        if (!photosInput.files || photosInput.files.length === 0) {
            alert('Please upload at least one proof photo');
            return;
        }

        for (let i = 0; i < photosInput.files.length; i++) {
            formData.append('proof_photos[]', photosInput.files[i]);
        }

        const feedback = document.getElementById('meetingFeedback').value;
        const rating = document.getElementById('meetingRating').value;
        const notes = document.getElementById('meetingNotes').value;

        if (feedback) formData.append('feedback', feedback);
        if (rating) formData.append('rating', rating);
        if (notes) formData.append('meeting_notes', notes);

        try {
            const token = getToken();
            if (!token) {
                alert('Authentication error. Please refresh the page and try again.');
                return;
            }

            const response = await fetch(`${API_BASE_URL}/meetings/${currentMeetingId}/complete`, {
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
                // Complete calling task if pending
                if (window.pendingTaskCompletion) {
                    await completeCallingTask(window.pendingTaskCompletion.taskId, window.pendingTaskCompletion.taskType);
                    window.pendingTaskCompletion = null;
                }
                showMeetingSuccessModal('Meeting completed with proof photos! Awaiting verification.');
                closeCompleteMeetingModal();
                loadMeetings();
            } else {
                // Handle validation errors or other errors
                let errorMessage = result.message || 'Failed to complete meeting';
                
                if (result.errors) {
                    console.error('Validation errors:', result.errors);
                    // Format validation errors
                    const errorMessages = [];
                    if (result.errors.proof_photos) {
                        errorMessages.push('Proof photos: ' + result.errors.proof_photos.join(', '));
                    }
                    if (result.errors.feedback) {
                        errorMessages.push('Feedback: ' + result.errors.feedback.join(', '));
                    }
                    if (result.errors.rating) {
                        errorMessages.push('Rating: ' + result.errors.rating.join(', '));
                    }
                    if (result.errors.meeting_notes) {
                        errorMessages.push('Notes: ' + result.errors.meeting_notes.join(', '));
                    }
                    
                    if (errorMessages.length > 0) {
                        errorMessage = errorMessages.join('\n');
                    }
                }
                
                alert(errorMessage);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Network error. Please try again. ' + (error.message || ''));
        }
    }

    function showMarkDeadModal(type, id) {
        currentMeetingId = id;
        document.getElementById('deadReason').value = '';
        document.getElementById('markDeadModal').classList.add('show');
    }

    function closeMarkDeadModal() {
        document.getElementById('markDeadModal').classList.remove('show');
        document.getElementById('deadReason').value = '';
        currentMeetingId = null;
    }

    async function rescheduleMeeting(id) {
        if (!confirm('This will cancel the current meeting. Do you want to reschedule?')) {
            return;
        }

        const token = getToken();
        if (!token) {
            window.location.href = '{{ route("login") }}';
            return;
        }

        try {
            const response = await fetch(`${API_BASE_URL}/meetings/${id}/cancel`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ reason: 'Rescheduled' })
            });

            const result = await response.json();

            if (response.ok) {
                alert('Meeting cancelled. Please create a new meeting with the updated time.');
                loadMeetings();
            } else {
                alert(result.message || 'Failed to cancel meeting');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('An error occurred while rescheduling the meeting');
        }
    }

    async function cancelMeeting(id) {
        if (!confirm('Are you sure you want to cancel this meeting?')) {
            return;
        }

        const token = getToken();
        if (!token) {
            window.location.href = '{{ route("login") }}';
            return;
        }

        try {
            const response = await fetch(`${API_BASE_URL}/meetings/${id}/cancel`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ reason: 'Cancelled by manager' })
            });

            const result = await response.json();

            if (response.ok) {
                alert('Meeting cancelled successfully');
                loadMeetings();
            } else {
                alert(result.message || 'Failed to cancel meeting');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('An error occurred while cancelling the meeting');
        }
    }

    async function submitMarkDead() {
        if (!currentMeetingId) return;

        const reason = document.getElementById('deadReason').value.trim();
        if (!reason) {
            alert('Please provide a reason for marking as dead');
            return;
        }

        const result = await apiCall(`/meetings/${currentMeetingId}/mark-dead`, {
            method: 'POST',
            body: JSON.stringify({ reason }),
        });

        if (result && result.success) {
            // Complete calling task if pending
            if (window.pendingTaskCompletion) {
                await completeCallingTask(window.pendingTaskCompletion.taskId, window.pendingTaskCompletion.taskType);
                window.pendingTaskCompletion = null;
            }
            alert('Meeting marked as dead successfully');
            closeMarkDeadModal();
            loadMeetings();
        } else {
            alert(result.message || 'Failed to mark as dead');
        }
    }

    async function cancelMeeting(id) {
        if (!confirm('Cancel this meeting?')) return;

        const result = await apiCall(`/meetings/${id}/cancel`, {
            method: 'POST',
        });

        if (result && result.success) {
            // Complete calling task if pending
            if (window.pendingTaskCompletion) {
                await completeCallingTask(window.pendingTaskCompletion.taskId, window.pendingTaskCompletion.taskType);
                window.pendingTaskCompletion = null;
            }
            alert('Meeting cancelled');
            loadMeetings();
        } else {
            alert(result.message || 'Failed to cancel meeting');
        }
    }

    async function showConvertToSiteVisitModal(id) {
        currentMeetingId = id;
        
        // Reset form
        document.getElementById('convertProjectInput').value = '';
        document.getElementById('convertProjectHidden').value = '';
        document.getElementById('convertProjectOptions').innerHTML = '';
        document.getElementById('convertScheduledAt').value = '';
        document.getElementById('convertVisitSequence').value = '';
        
        // Show modal
        document.getElementById('convertToSiteVisitModal').classList.add('show');
        
        try {
            // Load meeting data
            const meeting = await apiCall(`/meetings/${id}`);
            
            await loadConvertProjectOptions(meeting);
            
            // Set default scheduled date (next day from meeting scheduled date or tomorrow)
            const scheduledAtInput = document.getElementById('convertScheduledAt');
            let defaultDate = new Date();
            defaultDate.setDate(defaultDate.getDate() + 1); // Tomorrow
            
            if (meeting && meeting.scheduled_at) {
                const meetingDate = new Date(meeting.scheduled_at);
                meetingDate.setDate(meetingDate.getDate() + 1); // Next day from meeting
                if (meetingDate > new Date()) {
                    defaultDate = meetingDate;
                }
            }
            
            // Set minimum date (must be in future)
            const minDate = new Date();
            minDate.setHours(minDate.getHours() + 1); // At least 1 hour from now
            if (ALLOW_PRIVILEGED_PAST_SCHEDULING) {
                scheduledAtInput.removeAttribute('min');
            } else {
                scheduledAtInput.min = minDate.toISOString().slice(0, 16);
            }
            
            // Set default value
            scheduledAtInput.value = defaultDate.toISOString().slice(0, 16);
            
            setupConvertProjectInput();
            
        } catch (error) {
            console.error('Error loading convert form data:', error);
            alert('Error loading form data. Please try again.');
            closeConvertToSiteVisitModal();
        }
    }

    function closeConvertToSiteVisitModal() {
        document.getElementById('convertToSiteVisitModal').classList.remove('show');
        document.getElementById('convertToSiteVisitForm').reset();
        document.getElementById('convertProjectOptions').innerHTML = '';
        document.getElementById('convertProjectHidden').value = '';
        currentMeetingId = null;
    }
    
    // Project Tags Functions
    function setupConvertProjectInput() {
        const input = document.getElementById('convertProjectInput');
        if (input) {
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const value = this.value.trim();
                    if (value) {
                        addConvertProjectTag(value);
                        this.value = '';
                    }
                }
            });
        }
        
    }
    
    function addConvertProjectTag(tagName) {
        const container = document.getElementById('convertProjectTagsContainer');
        const hiddenInput = document.getElementById('convertProjectHidden');
        
        if (!container || !hiddenInput) return;
        
        // Check if tag already exists
        const existingTags = Array.from(container.querySelectorAll('.site-visit-project-tag-text'));
        const tagExists = existingTags.some(tag => tag.textContent.trim() === tagName.trim());
        
        if (tagExists) {
            return; // Don't add duplicate
        }
        
        // Create tag element
        const tagElement = document.createElement('span');
        tagElement.className = 'site-visit-project-tag';
        tagElement.innerHTML = `
            <span class="site-visit-project-tag-text">${escapeHtml(tagName)}</span>
            <span class="site-visit-project-tag-remove" onclick="removeConvertProjectTag(this)">×</span>
        `;
        
        container.appendChild(tagElement);
        
        // Update hidden input with comma-separated values
        updateConvertProjectHiddenInput();
    }
    
    function removeConvertProjectTag(element) {
        const tagElement = element.closest('.site-visit-project-tag');
        if (tagElement) {
            tagElement.remove();
            updateConvertProjectHiddenInput();
        }
    }
    
    function updateConvertProjectHiddenInput() {
        const container = document.getElementById('convertProjectTagsContainer');
        const hiddenInput = document.getElementById('convertProjectHidden');
        
        if (!container || !hiddenInput) return;
        
        const tags = Array.from(container.querySelectorAll('.site-visit-project-tag-text'));
        const projectNames = tags.map(tag => tag.textContent.trim()).filter(name => name);
        hiddenInput.value = projectNames.join(',');
    }

    let convertProjectOptions = [];

    function setupConvertProjectInput() {
        const input = document.getElementById('convertProjectInput');
        if (!input) {
            return;
        }

        input.oninput = function() {
            filterConvertProjectOptions(this.value);
        };

        input.onkeydown = function(e) {
            if (e.key !== 'Enter') {
                return;
            }

            e.preventDefault();
            const value = this.value.trim();
            if (!value) {
                return;
            }

            if (!convertProjectOptions.includes(value)) {
                convertProjectOptions.unshift(value);
            }

            setConvertProjectSelection(value);
        };
    }

    async function loadConvertProjectOptions(meeting = null) {
        const selectedProject = String(meeting?.project || '').trim();
        let options = [];

        try {
            const response = await fetch('/api/interested-project-names', {
                headers: {
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${getToken()}`,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            const result = await response.json();

            if (response.ok && result?.success && Array.isArray(result.data)) {
                options = result.data
                    .map(project => String(project?.name || '').trim())
                    .filter(Boolean);
            }
        } catch (error) {
            console.warn('Failed to load project options for convert modal:', error);
        }

        if (selectedProject) {
            options.unshift(selectedProject);
        }

        convertProjectOptions = Array.from(new Set(options));
        renderConvertProjectOptions();
        setConvertProjectSelection(selectedProject);
    }

    function renderConvertProjectOptions(filter = '') {
        const container = document.getElementById('convertProjectOptions');
        const hiddenInput = document.getElementById('convertProjectHidden');

        if (!container || !hiddenInput) {
            return;
        }

        const selectedProject = String(hiddenInput.value || '').trim();
        const searchTerm = String(filter || '').trim().toLowerCase();
        const filteredOptions = convertProjectOptions.filter(project => !searchTerm || project.toLowerCase().includes(searchTerm));

        if (!filteredOptions.length) {
            container.innerHTML = '<div class="text-sm text-gray-500 px-1 py-2">No matching project found. Press Enter to use typed project.</div>';
            return;
        }

        container.innerHTML = filteredOptions.map(project => {
            const activeClass = project === selectedProject ? ' active' : '';
            return `<button type="button" class="convert-project-chip${activeClass}" data-project-name="${escapeHtml(project)}">${escapeHtml(project)}</button>`;
        }).join('');
    }

    function filterConvertProjectOptions(filter = '') {
        renderConvertProjectOptions(filter);
    }

    function setConvertProjectSelection(projectName) {
        const hiddenInput = document.getElementById('convertProjectHidden');
        const input = document.getElementById('convertProjectInput');
        const value = String(projectName || '').trim();

        if (hiddenInput) {
            hiddenInput.value = value;
        }

        if (input) {
            input.value = value;
        }

        renderConvertProjectOptions(input?.value || value);
    }
    
    // Escape HTML helper
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    document.addEventListener('click', function(event) {
        const optionButton = event.target.closest('#convertProjectOptions [data-project-name]');
        if (!optionButton) {
            return;
        }

        setConvertProjectSelection(optionButton.getAttribute('data-project-name') || '');
    });

    async function convertToSiteVisit(event) {
        if (event) {
            event.preventDefault();
        }
        
        if (!currentMeetingId) {
            alert('Meeting ID not found');
            return;
        }

        // Get form data
        const form = document.getElementById('convertToSiteVisitForm');
        const formData = new FormData(form);
        const project = formData.get('project') || document.getElementById('convertProjectHidden').value;
        const scheduledAt = formData.get('scheduled_at');
        const visitSequence = formData.get('visit_sequence') || null;

        // Validate
        if (!project || !project.trim()) {
            alert('Please select or type a project');
            return;
        }

        if (!scheduledAt) {
            alert('Please select a scheduled date and time');
            return;
        }

        // Validate scheduled date is in future
        const selectedDate = new Date(scheduledAt);
        if (!ALLOW_PRIVILEGED_PAST_SCHEDULING && selectedDate <= new Date()) {
            alert('Scheduled date and time must be in the future');
            return;
        }

        try {
            const result = await apiCall(`/meetings/${currentMeetingId}/convert-to-site-visit`, {
                method: 'POST',
                body: JSON.stringify({
                    project: project.trim(),
                    scheduled_at: scheduledAt,
                    visit_sequence: visitSequence,
                }),
            });

            if (result && result.success) {
                if (typeof showNotification === 'function') {
                    showNotification(result.message || 'Meeting converted to Site Visit successfully!', 'success', 3000);
                } else {
                    alert(result.message || 'Meeting converted to Site Visit successfully!');
                }
                closeConvertToSiteVisitModal();
                // Reload meetings list to show updated status
                loadMeetings();
            } else {
                alert(result.message || 'Failed to convert meeting');
            }
        } catch (error) {
            console.error('Error converting meeting:', error);
            alert('An error occurred while converting the meeting. Please try again.');
        }
    }

    // Reschedule Meeting
    function showRescheduleMeetingModal(id) {
        currentMeetingId = id;
        // Get meeting details
        apiCall(`/meetings/${id}`).then(meeting => {
            if (meeting && meeting.id) {
                const scheduledDate = new Date(meeting.scheduled_at);
                const minDateTime = new Date();
                minDateTime.setDate(minDateTime.getDate() + 1);
                minDateTime.setHours(0, 0, 0, 0);
                
                document.getElementById('rescheduleScheduledAt').value = '';
                document.getElementById('rescheduleReason').value = '';
                document.getElementById('rescheduleModalTitle').textContent = 'Reschedule Meeting';
                document.getElementById('rescheduleModalType').value = 'meeting';
                document.getElementById('rescheduleModalId').value = id;
                if (ALLOW_PRIVILEGED_PAST_SCHEDULING) {
                    document.getElementById('rescheduleScheduledAt').removeAttribute('min');
                } else {
                    document.getElementById('rescheduleScheduledAt').min = minDateTime.toISOString().slice(0, 16);
                }
                document.getElementById('rescheduleMeetingModal').classList.add('show');
            }
        });
    }

    function closeRescheduleMeetingModal() {
        document.getElementById('rescheduleMeetingModal').classList.remove('show');
        document.getElementById('rescheduleScheduledAt').value = '';
        document.getElementById('rescheduleReason').value = '';
        currentMeetingId = null;
    }

    async function submitRescheduleMeeting() {
        const type = document.getElementById('rescheduleModalType').value;
        const id = document.getElementById('rescheduleModalId').value;
        const scheduledAt = document.getElementById('rescheduleScheduledAt').value;
        const reason = document.getElementById('rescheduleReason').value.trim();

        if (!scheduledAt) {
            alert('Please select a new scheduled date and time');
            return;
        }

        if (!reason) {
            alert('Please provide a reason for rescheduling');
            return;
        }

        try {
            const result = await apiCall(`/${type === 'meeting' ? 'meetings' : 'site-visits'}/${id}/reschedule`, {
                method: 'POST',
                body: JSON.stringify({
                    scheduled_at: scheduledAt,
                    reason: reason,
                }),
            });

            if (result && result.success) {
                // Complete calling task if pending
                if (window.pendingTaskCompletion) {
                    await completeCallingTask(window.pendingTaskCompletion.taskId, window.pendingTaskCompletion.taskType);
                    window.pendingTaskCompletion = null;
                }
                if (typeof showNotification === 'function') {
                    showNotification(result.message || 'Rescheduled successfully! Verification required.', 'success', 3000);
                } else {
                    alert(result.message || 'Rescheduled successfully! Verification required.');
                }
                closeRescheduleMeetingModal();
                loadMeetings();
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

    // Initialize
    (function() {
        const pageParams = new URLSearchParams(window.location.search);
        const requestedDateFilter = pageParams.get('date_filter');
        const requestedAction = pageParams.get('action');
        const requestedMeetingId = Number(pageParams.get('meeting_id') || pageParams.get('meeting') || 0) || null;
        const dateFilterEl = document.getElementById('dateFilter');
        const dateFromEl = document.getElementById('dateFrom');
        const dateToEl = document.getElementById('dateTo');
        const savedView = (() => {
            try { return localStorage.getItem('asm_meetings_view') || 'card'; } catch (e) { return 'card'; }
        })();
        setMeetingsView(getAsmPreferredView('meetings', savedView), false);

        if (dateFilterEl && requestedDateFilter && ['today', 'this_week', 'this_month', 'this_year', 'custom'].includes(requestedDateFilter)) {
            dateFilterEl.value = requestedDateFilter;
            if (requestedDateFilter === 'custom') {
                if (dateFromEl && pageParams.get('date_from')) dateFromEl.value = pageParams.get('date_from');
                if (dateToEl && pageParams.get('date_to')) dateToEl.value = pageParams.get('date_to');
            }
            toggleCustomDate();
        }

        loadMeetings().then(() => {
            if (requestedAction === 'complete' && requestedMeetingId) {
                setTimeout(() => showCompleteMeetingModal(requestedMeetingId), 300);
            }
        });
    })();
</script>

<!-- Quick Schedule Meeting Modal -->
<div id="quickMeetingModal" class="modal">
    <div class="modal-content" style="max-width: 600px; padding: 0; overflow: hidden;">
        <!-- Header with gradient (matching lead page style) -->
        <div class="bg-gradient-to-r from-green-800 to-green-600 px-6 py-5 rounded-t-2xl w-full">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                        <i class="fas fa-calendar-check text-white text-lg"></i>
                    </div>
                    <h3 class="text-xl font-bold text-white">Schedule Meeting</h3>
                </div>
                <button onclick="closeQuickMeetingModal()" class="text-white hover:bg-white hover:bg-opacity-20 rounded-lg p-2 transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>

        <!-- Form Body -->
        <form id="quickMeetingForm" onsubmit="submitQuickMeeting(event)" class="flex flex-col flex-1 min-h-0">
            <div class="px-6 py-5 overflow-y-auto flex-1" style="max-height: 70vh;">
                <div class="space-y-4">
                    <!-- Lead Selection (only in meeting section) -->
                    <div class="form-group">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-user text-green-600 mr-1"></i> Select Lead <span style="color: #ef4444;">*</span>
                        </label>
                        <input type="text" id="leadSearchInput" placeholder="Search by name or phone" oninput="filterLeadOptions(this.value)" 
                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-green-500 mb-2">
                        <select id="leadSelect" name="lead_id" required 
                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-green-500">
                            <option value="">Loading...</option>
                        </select>
                        <small class="text-gray-500">Only leads from your Lead section are listed.</small>
                    </div>

                    <!-- Meeting Type -->
                    <div class="form-group">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-tag text-green-600 mr-1"></i> Meeting Type
                        </label>
                        <select id="meeting_sequence" name="meeting_sequence" required 
                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-green-500 bg-white transition">
                            <option value="1">🎯 Fresh Meeting (1st)</option>
                            <option value="2">🔄 2nd Meeting</option>
                            <option value="3">⭐ 3rd Meeting</option>
                        </select>
                    </div>

                    <!-- Date & Time -->
                    <div class="form-group">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-clock text-green-600 mr-1"></i> Scheduled Date & Time
                        </label>
                        <input type="datetime-local" name="scheduled_at" id="quickScheduledAt" required 
                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                    </div>

                    <!-- Meeting Mode -->
                    <div class="form-group">
                        <label class="block text-sm font-semibold text-gray-700 mb-3">
                            <i class="fas fa-video text-green-600 mr-1"></i> Meeting Mode
                        </label>
                        <div class="flex gap-3">
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="meeting_mode" value="online" class="hidden peer" onchange="toggleQuickMeetingModeFields()">
                                <div class="flex items-center justify-center gap-2 px-4 py-3 border-2 border-gray-200 rounded-xl peer-checked:border-green-600 peer-checked:bg-green-50 peer-checked:text-green-700 transition hover:border-gray-300">
                                    <i class="fas fa-video"></i>
                                    <span class="font-medium">Online</span>
                                </div>
                            </label>
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="meeting_mode" value="offline" checked class="hidden peer" onchange="toggleQuickMeetingModeFields()">
                                <div class="flex items-center justify-center gap-2 px-4 py-3 border-2 border-gray-200 rounded-xl peer-checked:border-green-600 peer-checked:bg-green-50 peer-checked:text-green-700 transition hover:border-gray-300">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span class="font-medium">Offline</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Conditional: Online = Link -->
                    <div id="quickOnlineFields" style="display:none;" class="form-group">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-link text-green-600 mr-1"></i> Meeting Link (Optional)
                        </label>
                        <input type="url" name="meeting_link" placeholder="https://meet.google.com/..." 
                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                    </div>

                    <!-- Conditional: Offline = Location -->
                    <div id="quickOfflineFields" class="form-group">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-map-marker-alt text-green-600 mr-1"></i> Location
                        </label>
                        <input type="text" name="location" id="quickLocationInput" placeholder="Office address, project site, etc." 
                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                    </div>

                    <!-- Reminder Checkbox -->
                    <div class="form-group bg-gradient-to-br from-green-50 to-emerald-50 p-4 rounded-xl border-2 border-green-100">
                        <label class="flex items-start cursor-pointer group">
                            <input type="checkbox" name="reminder_enabled" checked 
                                class="mt-1 mr-3 w-5 h-5 text-green-600 rounded border-2 border-green-300 focus:ring-green-500">
                            <div>
                                <span class="text-sm font-semibold text-green-900 group-hover:text-green-700 transition">
                                    <i class="fas fa-bell text-green-600 mr-1"></i> Remind me before meeting
                                </span>
                                <p class="text-xs text-green-700 mt-1">Get a calling task 5 minutes before the meeting time</p>
                            </div>
                        </label>
                    </div>

                    <!-- Meeting Notes -->
                    <div class="form-group">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-sticky-note text-green-600 mr-1"></i> Meeting Notes (Optional)
                        </label>
                        <textarea name="meeting_notes" id="quickMeetingNotes" rows="3" placeholder="Add any additional notes or agenda..." 
                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition resize-none"></textarea>
                    </div>
                </div>
            </div>

            <!-- Footer with buttons -->
            <div class="bg-gray-50 px-6 py-3 border-t border-gray-200 flex gap-3 shrink-0">
                <button type="button" onclick="closeQuickMeetingModal()" 
                    class="flex-1 h-12 bg-white border-2 border-green-700 rounded-lg text-green-800 hover:bg-green-50 font-semibold transition-all shadow-sm flex items-center justify-center">
                    <i class="fas fa-times mr-2"></i>Cancel
                </button>
                <button type="submit" 
                    class="flex-1 h-12 bg-gradient-to-r from-green-700 to-green-600 text-white rounded-lg hover:from-green-800 hover:to-green-700 font-semibold transition-all shadow-md hover:shadow-lg flex items-center justify-center">
                    <i class="fas fa-calendar-check mr-2"></i>Schedule Meeting
                </button>
            </div>
        </form>

        <p id="quickMeetingError" style="color: #ef4444; min-height: 18px; padding: 0 24px 16px;"></p>
    </div>
</div>

<!-- Complete Meeting Modal -->
<div id="completeMeetingModal" class="modal">
    <div class="modal-content complete-meeting-modal-card" style="max-width: 760px; padding: 0; overflow: hidden;">
        <div class="complete-meeting-modal-head">
            <div>
                <h3>Complete Meeting</h3>
                <p>Upload proof and record the outcome before submitting.</p>
            </div>
            <span class="complete-meeting-badge">Required</span>
        </div>

        <div class="complete-meeting-modal-body">
            <div class="complete-meeting-alert">
                <i class="fas fa-camera"></i>
                <div>
                    <strong>Proof photos are mandatory.</strong>
                    <span>Add at least one clear meeting proof photo. Max 5MB per image.</span>
                </div>
            </div>

            <div class="complete-meeting-upload">
                <label for="proofPhotosInput">Proof Photos <span class="req">*</span></label>
                <input type="file" id="proofPhotosInput" multiple accept="image/*" onchange="handleProofPhotosChange(event)" required>
                <div id="proofPhotosPreview" class="complete-meeting-preview"></div>
            </div>

            <div class="complete-meeting-grid">
                <div class="form-group complete-meeting-field complete-meeting-field-wide">
                    <label for="meetingFeedback">Feedback</label>
                    <textarea id="meetingFeedback" rows="4" placeholder="Summarize discussion, interest level, and next steps..."></textarea>
                </div>

                <div class="form-group complete-meeting-field">
                    <label for="meetingRating">Rating</label>
                    <select id="meetingRating">
                        <option value="">Select rating</option>
                        <option value="1">1 - Poor</option>
                        <option value="2">2 - Fair</option>
                        <option value="3">3 - Good</option>
                        <option value="4">4 - Very Good</option>
                        <option value="5">5 - Excellent</option>
                    </select>
                </div>

                <div class="form-group complete-meeting-field complete-meeting-field-wide">
                    <label for="meetingNotes">Notes</label>
                    <textarea id="meetingNotes" rows="4" placeholder="Add internal notes, objections, or follow-up context..."></textarea>
                </div>
            </div>
        </div>

        <div class="complete-meeting-actions">
            <button type="button" class="btn btn-secondary" onclick="closeCompleteMeetingModal()">Cancel</button>
            <button type="button" class="btn btn-success" onclick="submitCompleteMeeting()">Submit</button>
        </div>
    </div>
</div>

<!-- Reschedule Modal -->
<div id="rescheduleMeetingModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <h3 id="rescheduleModalTitle" style="font-size: 20px; font-weight: 600; margin-bottom: 20px;">Reschedule</h3>
        <input type="hidden" id="rescheduleModalType" value="meeting">
        <input type="hidden" id="rescheduleModalId" value="">
        
        <div class="form-group">
            <label>New Scheduled Date & Time <span style="color: #ef4444;">*</span></label>
            <input type="datetime-local" id="rescheduleScheduledAt" required
                class="form-group input" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px;">
            <small style="color: #6b7280;">Select a future date and time</small>
        </div>

        <div class="form-group">
            <label>Reason for Rescheduling <span style="color: #ef4444;">*</span></label>
            <textarea id="rescheduleReason" rows="4" placeholder="Enter reason for rescheduling..." required
                class="form-group textarea" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px;"></textarea>
        </div>

        <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
            <button type="button" class="btn btn-secondary" onclick="closeRescheduleMeetingModal()">Cancel</button>
            <button type="button" class="btn" style="background: #f59e0b; color: white;" onclick="submitRescheduleMeeting()">Reschedule</button>
        </div>
    </div>
</div>

<!-- Mark as Dead Modal -->
<div id="markDeadModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <h3 style="font-size: 20px; font-weight: 600; margin-bottom: 20px;">Mark as Dead</h3>
        <p style="color: #ef4444; margin-bottom: 16px;">This will mark the meeting and associated lead as dead. This action cannot be undone.</p>
        
        <div class="form-group">
            <label>Reason <span style="color: #ef4444;">*</span></label>
            <textarea id="deadReason" rows="4" placeholder="Enter reason for marking as dead..." required></textarea>
        </div>

        <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
            <button type="button" class="btn btn-secondary" onclick="closeMarkDeadModal()">Cancel</button>
            <button type="button" class="btn btn-danger" onclick="submitMarkDead()">Mark as Dead</button>
        </div>
    </div>
</div>

<!-- Convert to Site Visit Modal -->
<div id="convertToSiteVisitModal" class="modal">
    @include('components.site-visit-form-card', [
        'title' => 'Convert to Site Visit',
        'icon' => 'fas fa-exchange-alt',
        'closeHandler' => 'closeConvertToSiteVisitModal()',
        'formId' => 'convertToSiteVisitForm',
        'submitHandler' => 'convertToSiteVisit(event)',
        'projectInputId' => 'convertProjectInput',
        'projectOptionsId' => 'convertProjectOptions',
        'projectHiddenId' => 'convertProjectHidden',
        'scheduledAtId' => 'convertScheduledAt',
        'visitSequenceId' => 'convertVisitSequence',
        'submitLabel' => 'Convert',
        'submitIcon' => 'fas fa-exchange-alt',
    ])
</div>

<!-- Success Modal -->
<div id="meetingSuccessModal" class="modal">
    <div class="modal-content" style="max-width: 400px; text-align: center; padding: 40px 30px;">
        <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #0073b1 0%, #005f91 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; box-shadow: 0 4px 12px rgba(0, 115, 177, 0.3);">
            <i class="fas fa-check" style="font-size: 40px; color: white; font-weight: bold;"></i>
        </div>
        <h3 style="font-size: 20px; font-weight: 600; color: #333; margin-bottom: 12px;">Success!</h3>
        <p id="meetingSuccessMessage" style="font-size: 16px; color: #666; margin-bottom: 30px; line-height: 1.5;"></p>
        <button onclick="closeMeetingSuccessModal()" style="background: linear-gradient(135deg, #0073b1 0%, #005f91 100%); color: white; border: none; padding: 12px 32px; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; box-shadow: 0 4px 12px rgba(0, 115, 177, 0.3); transition: all 0.2s;" onmouseover="this.style.transform='scale(1.05)'; this.style.boxShadow='0 6px 16px rgba(0, 115, 177, 0.4)';" onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='0 4px 12px rgba(0, 115, 177, 0.3)';">
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
.form-group {
    margin-bottom: 16px;
}
.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #333;
}
.form-group input[type="file"],
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 12px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 14px;
}
.complete-meeting-modal-card {
    display: flex;
    flex-direction: column;
    width: min(760px, 92vw);
    max-height: min(88vh, 920px);
    background: linear-gradient(180deg, #ffffff 0%, #fbfdfc 100%);
    border: 1px solid #dce9e2;
    box-shadow: 0 24px 48px rgba(6, 58, 28, 0.14);
}
.complete-meeting-modal-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    padding: 24px 28px 18px;
    background: linear-gradient(135deg, #f5fbf7 0%, #eef7f2 100%);
    border-bottom: 1px solid #dce9e2;
}
.complete-meeting-modal-head h3 {
    margin: 0;
    font-size: 28px;
    line-height: 1.1;
    color: #0b3d29;
}
.complete-meeting-modal-head p {
    margin: 8px 0 0;
    color: #5e7669;
    font-size: 14px;
}
.complete-meeting-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 8px 14px;
    border-radius: 999px;
    background: #e6f4ea;
    color: #0f6d3c;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}
.complete-meeting-modal-body {
    flex: 1 1 auto;
    overflow-y: auto;
    padding: 24px 28px 12px;
}
.complete-meeting-alert {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 14px 16px;
    margin-bottom: 20px;
    border: 1px solid #ffd4d4;
    border-radius: 16px;
    background: linear-gradient(135deg, #fff5f5 0%, #fffafa 100%);
}
.complete-meeting-alert i {
    font-size: 20px;
    color: #dc2626;
    margin-top: 2px;
}
.complete-meeting-alert strong,
.complete-meeting-alert span {
    display: block;
}
.complete-meeting-alert strong {
    color: #b42318;
    margin-bottom: 3px;
}
.complete-meeting-alert span {
    color: #7a5c5c;
    font-size: 13px;
}
.complete-meeting-upload {
    padding: 18px;
    margin-bottom: 20px;
    border: 1px solid #dce9e2;
    border-radius: 18px;
    background: #f9fcfa;
}
.complete-meeting-upload label {
    display: block;
    margin-bottom: 10px;
    font-size: 15px;
    font-weight: 700;
    color: #103c2b;
}
.complete-meeting-upload input[type="file"] {
    width: 100%;
    padding: 12px;
    border: 1px dashed #9fc2b0;
    border-radius: 14px;
    background: #fff;
}
.complete-meeting-preview {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 14px;
}
.complete-meeting-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(220px, 0.52fr);
    gap: 18px;
}
.complete-meeting-field {
    margin-bottom: 0;
}
.complete-meeting-field-wide {
    grid-column: 1 / -1;
}
.complete-meeting-field label {
    margin-bottom: 8px;
    font-size: 15px;
    font-weight: 700;
    color: #103c2b;
}
.complete-meeting-field textarea,
.complete-meeting-field select {
    border: 1px solid #d6e1db;
    border-radius: 14px;
    padding: 14px 15px;
    color: #183c2c;
    background: #fff;
}
.complete-meeting-field textarea {
    min-height: 120px;
    resize: vertical;
}
.complete-meeting-field textarea:focus,
.complete-meeting-field select:focus,
.complete-meeting-upload input[type="file"]:focus {
    outline: none;
    border-color: #205A44;
    box-shadow: 0 0 0 3px rgba(32, 90, 68, 0.12);
}
.complete-meeting-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding: 18px 28px 24px;
    border-top: 1px solid #e4ede8;
    background: #ffffff;
}
.complete-meeting-actions .btn {
    min-width: 120px;
    min-height: 46px;
    border-radius: 12px;
    font-weight: 700;
}
.btn-secondary {
    background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
    color: white;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}
.btn-secondary:hover {
    background: linear-gradient(135deg, #15803d 0%, #166534 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}
/* Project Tags Styling */
.site-visit-project-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background-color: #3b82f6;
    color: white;
    border-radius: 9999px;
    font-size: 14px;
    font-weight: 500;
}
.site-visit-project-tag-remove {
    cursor: pointer;
    margin-left: 4px;
    opacity: 0.8;
    font-size: 12px;
}
.site-visit-project-tag-remove:hover {
    opacity: 1;
}
.convert-project-dropdown-panel {
    border: 2px solid #e5e7eb;
    border-radius: 16px;
    background: #fff;
    padding: 14px;
}
.convert-project-search-wrap {
    margin-bottom: 10px;
}
.convert-project-options {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    max-height: 220px;
    overflow-y: auto;
}
.convert-project-chip {
    border: 1px solid #d1d5db;
    background: #fff;
    color: #4b5563;
    border-radius: 9999px;
    padding: 8px 14px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}
.convert-project-chip:hover {
    border-color: #205A44;
    color: #205A44;
}
.convert-project-chip.active {
    background: linear-gradient(135deg, #063A1C 0%, #205A44 100%);
    border-color: #063A1C;
    color: #fff;
    box-shadow: 0 10px 24px rgba(15, 109, 68, 0.18);
}
</style>
@endpush

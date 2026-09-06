@extends('sales-manager.layout')

@section('title', 'All Leads - Senior Manager')
@section('page-title', 'All Leads')

@push('styles')
<style>
    .lead-view-toggle {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        padding: 0.25rem;
        background: #edf2f7;
        border: 1px solid #dbe4ee;
        border-radius: 9999px;
    }

    .lead-view-toggle button {
        border: 0;
        background: transparent;
        color: #5f6c7b;
        padding: 0.7rem 1rem;
        border-radius: 9999px;
        font-size: 0.875rem;
        font-weight: 600;
        transition: all 0.2s ease;
    }

    .lead-view-toggle button.active {
        background: linear-gradient(135deg, #063A1C, #205A44);
        color: #fff;
        box-shadow: 0 8px 18px rgba(6, 58, 28, 0.18);
    }

    #leadsGrid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1.5rem;
    }

    .leads-list-shell {
        border: 1px solid #e5e7eb;
        border-radius: 1.25rem;
        overflow: hidden;
        background: #ffffff;
    }

    .leads-list-table {
        width: 100%;
        border-collapse: collapse;
    }

    .leads-list-table thead th {
        background: #f8fafc;
        color: #475467;
        font-size: 0.74rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        padding: 0.95rem 1rem;
        text-align: left;
        border-bottom: 1px solid #e5e7eb;
    }

    .leads-list-table tbody td {
        padding: 1rem;
        border-bottom: 1px solid #eef2f7;
        vertical-align: top;
    }

    .leads-list-table tbody tr:hover {
        background: #fcfdfd;
    }

    .lead-list-row {
        cursor: pointer;
    }

    .lead-list-name {
        color: #101828;
        font-weight: 700;
        font-size: 0.9rem;
        line-height: 1.2;
    }

    .lead-list-sub {
        color: #667085;
        font-size: 0.77rem;
        margin-top: 0.1rem;
    }

    .lead-remark-text {
        color: #344054;
        font-size: 0.85rem;
        line-height: 1.45;
        max-width: 320px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .lead-next-action-cell {
        min-width: 150px;
    }

    .lead-next-action-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        border-radius: 999px;
        padding: 0.24rem 0.6rem;
        font-size: 0.68rem;
        font-weight: 800;
        line-height: 1;
        white-space: nowrap;
    }

    .lead-next-action-badge.overdue {
        background: #fee2e2;
        color: #b91c1c;
    }

    .lead-next-action-badge.today {
        background: #dcfce7;
        color: #047857;
    }

    .lead-next-action-badge.upcoming {
        background: #e0e7ff;
        color: #3730a3;
    }

    .lead-next-action-badge.none {
        background: #f1f5f9;
        color: #64748b;
    }

    .lead-next-action-time {
        display: block;
        margin-top: 0.42rem;
        color: #667085;
        font-size: 0.76rem;
        line-height: 1.25;
        max-width: 170px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .lead-list-actions {
        display: flex;
        gap: 0.35rem;
        min-width: 190px;
    }

    .lead-list-actions a,
    .lead-list-actions button {
        flex: 1 1 0;
        border-radius: 0.75rem;
        padding: 0.58rem 0.65rem;
        font-size: 0.72rem;
        font-weight: 700;
        white-space: nowrap;
    }

    .realtyx-lead-sheet-panel {
        border-radius: 18px;
        border: 1px solid #d7e5dc;
        background: linear-gradient(180deg, #ffffff 0%, #fbfdfc 100%);
        box-shadow: 0 16px 38px rgba(15, 23, 42, 0.08);
        width: 100%;
        max-width: none;
        box-sizing: border-box;
    }

    .realtyx-lead-sheet .leads-list-shell {
        border-radius: 14px;
        border: 1px solid #d7e5dc;
        overflow-y: auto;
        overflow-x: hidden;
        max-height: calc(100vh - 250px);
        width: 100%;
    }

    .realtyx-lead-sheet .leads-list-table {
        width: 100%;
        min-width: 0;
        table-layout: fixed;
    }

    .realtyx-lead-sheet .leads-list-table thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: #f4f8f6;
        border-bottom: 1px solid #cddbd2;
    }

    .realtyx-lead-sheet .leads-list-table tbody td {
        padding: 0.82rem 1rem;
        border-right: 1px solid #eef2f0;
    }

    .realtyx-lead-sheet .leads-list-table tbody tr:hover {
        background: #f8fbfa;
    }

    .realtyx-lead-sheet .leads-list-table th:nth-child(1) { width: 22%; }
    .realtyx-lead-sheet .leads-list-table th:nth-child(2) { width: 10%; }
    .realtyx-lead-sheet .leads-list-table th:nth-child(3) { width: 13%; }
    .realtyx-lead-sheet .leads-list-table th:nth-child(4) { width: 22%; }
    .realtyx-lead-sheet .leads-list-table th:nth-child(5) { width: 11%; }
    .realtyx-lead-sheet .leads-list-table th:nth-child(6) { width: 10%; }
    .realtyx-lead-sheet .leads-list-table th:nth-child(7) { width: 12%; }

    .realtyx-lead-sheet .lead-list-actions {
        min-width: 0;
        gap: 0.32rem;
    }

    .realtyx-lead-sheet .lead-list-actions a,
    .realtyx-lead-sheet .lead-list-actions button {
        padding: 0.56rem 0.48rem;
        font-size: 0.68rem;
    }

    .realtyx-lead-sheet .lead-remark-text,
    .realtyx-lead-sheet .lead-next-action-time,
    .realtyx-lead-sheet .lead-list-sub {
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .realtyx-strip {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr)) auto auto auto;
        align-items: stretch;
        border: 1px solid #d7e5dc;
        border-radius: 14px;
        overflow: hidden;
        background: #ffffff;
        margin-bottom: 14px;
    }

    .realtyx-strip.is-hidden .realtyx-strip-stat {
        display: none;
    }

    .realtyx-strip-stat {
        padding: 12px 16px;
        border-right: 1px solid #e6eee9;
        min-width: 0;
    }

    .realtyx-strip-label {
        color: #667085;
        font-size: 0.7rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .realtyx-strip-value {
        margin-top: 4px;
        color: #062b1d;
        font-size: 1.2rem;
        line-height: 1;
        font-weight: 900;
    }

    .realtyx-strip-action,
    .realtyx-strip-toggle,
    .realtyx-filter-toggle {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 118px;
        padding: 0 16px;
        border: 0;
        border-left: 1px solid #e6eee9;
        background: #ffffff;
        color: #0b3d29;
        font-size: 0.82rem;
        font-weight: 800;
        text-decoration: none;
        white-space: nowrap;
        cursor: pointer;
    }

    .realtyx-strip-action {
        background: linear-gradient(135deg, #063A1C, #205A44);
        color: #ffffff;
        gap: 8px;
    }

    .realtyx-strip-toggle {
        min-width: 84px;
    }

    .realtyx-filter-toggle {
        min-width: 46px;
        font-size: 0.9rem;
    }

    .realtyx-filter-toggle.is-active {
        background: #ecfdf5;
        color: #047857;
    }

    .realtyx-strip.is-hidden {
        grid-template-columns: 1fr auto auto;
    }

    .realtyx-strip.is-hidden .realtyx-strip-toggle {
        grid-column: 1;
        justify-content: flex-start;
        border-left: 0;
    }

    .realtyx-strip.is-hidden .realtyx-strip-action {
        grid-column: 3;
    }

    .realtyx-strip.is-hidden .realtyx-filter-toggle {
        grid-column: 2;
    }

    @media (min-width: 769px) {
        .realtyx-filters-hidden .lead-filters-row {
            display: none !important;
        }

        .realtyx-filters-hidden > .flex.items-center.justify-between.mb-6 {
            margin-bottom: 0 !important;
        }

        body.realtyx-leads-fullscreen #mainContent .container {
            width: 100% !important;
            max-width: none !important;
            padding-left: 16px !important;
            padding-right: 16px !important;
        }
    }

    .lead-mobile-add-only {
        display: none !important;
    }

    @media (max-width: 768px) {
        .lead-mobile-add-only {
            display: flex !important;
        }

        .realtyx-strip {
            display: none !important;
        }

        .realtyx-filters-hidden .lead-filters-row {
            display: flex !important;
        }
    }
    
    /* Lead card container - ensure proper sizing */
    .bg-white.rounded-lg.shadow-md {
        display: flex;
        flex-direction: column;
        min-width: 0;
        overflow: visible;
        box-sizing: border-box;
    }
    
    /* Professional Verified Badge Styling - Compact */
    .verified-badge {
        position: relative;
        z-index: 10;
        white-space: nowrap;
        letter-spacing: 0.01em;
        text-transform: uppercase;
        font-weight: 500;
        box-shadow: 0 1px 2px rgba(16, 185, 129, 0.15);
        transition: all 0.2s ease;
        line-height: 1.2;
    }
    
    .verified-badge:hover {
        transform: translateY(-0.5px);
        box-shadow: 0 2px 3px rgba(16, 185, 129, 0.2);
    }
    
    .verified-badge i {
        filter: drop-shadow(0 0.5px 0.5px rgba(0, 0, 0, 0.1));
    }
    
    /* Button container in cards */
    .bg-white.rounded-lg.shadow-md .lead-card-action-row {
        width: 100%;
        box-sizing: border-box;
        overflow: visible;
        margin-top: 1rem;
    }
    
    /* Buttons in lead cards */
    .bg-white.rounded-lg.shadow-md .lead-card-action-row a,
    .bg-white.rounded-lg.shadow-md .lead-card-action-row button {
        flex: 1 1 0;
        min-width: 0;
        max-width: calc(50% - 4px);
        padding: 10px 8px;
        font-size: 12px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        box-sizing: border-box;
    }

    .lead-mobile-shell,
    .lead-mobile-meta,
    .lead-mobile-remark,
    .lead-mobile-top,
    .lead-mobile-status-wrap,
    .lead-mobile-view-btn,
    .lead-mobile-actions {
        display: none;
    }

    .asm-leads-panel {
        border: 1px solid #d9e5dd;
        border-radius: 16px;
        box-shadow: 0 14px 34px rgba(6, 58, 28, 0.08);
        background: linear-gradient(180deg, #ffffff 0%, #fbfcfb 100%);
    }

    .asm-lead-card {
        border: 1px solid #d9e5dd;
        border-radius: 16px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.07);
        background: linear-gradient(180deg, #ffffff 0%, #fbfcfb 100%);
        padding: 12px;
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    }

    .asm-lead-card:hover {
        border-color: #bfd5c8;
        box-shadow: 0 12px 28px rgba(6, 58, 28, 0.12);
        transform: translateY(-1px);
    }

    .asm-lead-card-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 8px;
        margin-bottom: 8px;
    }

    .asm-lead-title {
        font-size: 15px;
        line-height: 1.25;
        font-weight: 800;
        color: #0b3d29;
        margin: 0 0 2px;
    }

    .asm-lead-meta-line {
        color: #557165;
        font-size: 11px;
        line-height: 1.3;
    }

    .asm-lead-meta-line strong {
        color: #205A44;
        font-weight: 700;
    }

    .asm-lead-detail-grid {
        display: grid;
        gap: 5px;
    }

    .asm-lead-detail-row {
        display: flex;
        align-items: center;
        gap: 6px;
        color: #475467;
        font-size: 12px;
    }

    .asm-lead-detail-row i {
        width: 14px;
        color: #8a9b91;
        flex-shrink: 0;
    }

    .asm-lead-actions {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 6px;
        margin-top: 10px;
    }

    .asm-lead-action-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        min-height: 34px;
        border-radius: 10px;
        padding: 7px 8px;
        font-size: 11px;
        font-weight: 800;
        text-decoration: none;
        white-space: nowrap;
    }

    .asm-lead-action-btn.call {
        background: #edf6f2;
        color: #0f5132;
        border: 1px solid #cfe4d9;
    }

    .asm-lead-action-btn.open {
        background: linear-gradient(135deg, #063A1C 0%, #205A44 100%);
        color: #fff;
        border: 1px solid #0f5132;
        box-shadow: 0 10px 20px rgba(6, 58, 28, 0.16);
    }

    .asm-lead-action-btn.task {
        background: #eff4ff;
        color: #1d4ed8;
        border: 1px solid #d5defa;
    }

    .favorite-lead-btn {
        border: 1px solid #d1d5db;
        background: #ffffff;
        color: #6b7280;
        flex: 0 0 auto !important;
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    }

    .lead-list-actions .favorite-lead-btn {
        flex: 0 0 auto !important;
    }

    .favorite-lead-btn:hover {
        border-color: #f59e0b;
        color: #b45309;
    }

    .favorite-lead-btn.active {
        background: #fef3c7;
        border-color: #f59e0b;
        color: #b45309;
    }

    @media (max-width: 1024px) {
        #leadsGrid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    /* Filters layout - desktop: one line, mobile: stacked */
    .lead-filters-row {
        display: flex;
        flex-direction: row;
        flex-wrap: nowrap;
        align-items: center;
        gap: 8px;
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
    }
    
    .lead-filters-row input,
    .lead-filters-row select {
        flex: 1;
        min-width: 0;
        max-width: 100%;
        box-sizing: border-box;
    }

    .lead-filters-row .lead-date-filter {
        display: none;
    }

    .lead-filters-row.show-date-filters .lead-clear-filter-btn,
    .lead-filters-row.show-custom-date-range .lead-date-filter,
    .lead-filters-row.show-date-filters .lead-clear-filter-btn {
        display: block;
    }

    .lead-custom-date-range {
        display: none;
        flex: 0 0 auto;
        align-items: center;
        gap: 8px;
        min-width: 0;
    }

    .lead-filters-row.show-custom-date-range .lead-custom-date-range {
        display: flex;
    }

    .lead-clear-filter-btn {
        display: none;
        flex: 0 0 auto;
        white-space: nowrap;
    }
    
    @media (max-width: 768px) {
        #leadsGrid {
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        .lead-view-toggle {
            width: 100%;
            justify-content: stretch;
        }

        .lead-view-toggle button {
            flex: 1 1 0;
        }

        .leads-list-shell {
            overflow-x: hidden;
            border-radius: 1rem;
            border-color: #dfe5e2;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
            background: transparent;
            border: 0;
            box-shadow: none;
        }

        .leads-list-table {
            min-width: 100%;
            table-layout: fixed;
        }

        .leads-list-table thead th {
            display: none;
        }

        .leads-list-table thead th:nth-child(2),
        .leads-list-table thead th:nth-child(3),
        .leads-list-table thead th:nth-child(4),
        .leads-list-table thead th:nth-child(5),
        .leads-list-table thead th:nth-child(6),
        .leads-list-table thead th:nth-child(7),
        .leads-list-table tbody td:nth-child(2),
        .leads-list-table tbody td:nth-child(3),
        .leads-list-table tbody td:nth-child(4),
        .leads-list-table tbody td:nth-child(5),
        .leads-list-table tbody td:nth-child(6),
        .leads-list-table tbody td:nth-child(7) {
            display: none;
        }

        .leads-list-table tbody,
        .leads-list-table tbody tr,
        .leads-list-table tbody td {
            display: block;
            width: 100%;
        }

        .leads-list-table tbody td {
            padding: 0;
            border: 0;
        }

        .leads-list-table tbody tr {
            transition: background-color 0.2s ease;
            margin-bottom: 0.95rem;
            border: 1px solid #dde7e1;
            border-radius: 1rem;
            background: #ffffff;
            box-shadow: 0 10px 26px rgba(15, 23, 42, 0.05);
            overflow: hidden;
        }

        .leads-list-table tbody tr:active {
            background: #f2f7f4;
        }

        .lead-list-name,
        .lead-list-sub,
        .lead-remark-text,
        .lead-list-actions {
            display: none;
        }

        .lead-mobile-shell {
            display: block;
            padding: 0.65rem 0.75rem 0.7rem;
        }

        .lead-mobile-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.55rem;
            margin-bottom: 0.45rem;
        }

        .lead-mobile-name {
            color: #0f172a;
            font-size: 0.88rem;
            line-height: 1.2;
            font-weight: 800;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .lead-mobile-status-wrap {
            display: flex;
            align-items: flex-start;
            justify-content: flex-end;
            min-width: 72px;
            flex-shrink: 0;
        }

        .lead-mobile-status-wrap .lead-mobile-status {
            display: flex;
            justify-content: flex-end;
            width: 100%;
        }

        .lead-mobile-meta {
            display: grid;
            gap: 0.28rem;
        }

        .lead-mobile-meta-line {
            display: flex;
            align-items: flex-start;
            gap: 0.42rem;
            color: #475467;
            font-size: 0.76rem;
            line-height: 1.25;
        }

        .lead-mobile-meta-line i {
            width: 14px;
            color: #98a2b3;
            margin-top: 0.15rem;
            flex-shrink: 0;
        }

        .lead-mobile-meta-text {
            min-width: 0;
            overflow-wrap: anywhere;
        }

        .lead-mobile-remark {
            display: block;
            margin-top: 0.45rem;
            padding-top: 0.42rem;
            border-top: 1px solid #edf2f7;
        }

        .lead-mobile-remark-text {
            color: #334155;
            font-size: 0.74rem;
            line-height: 1.25;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .lead-mobile-actions {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 6px;
            margin-top: 0.55rem;
        }

        .lead-mobile-action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            min-height: 31px;
            border-radius: 9px;
            font-size: 10px;
            font-weight: 800;
            text-decoration: none;
            padding: 6px 5px;
        }

        .lead-mobile-action-btn.call {
            background: #edf6f2;
            color: #0f5132;
            border: 1px solid #cfe4d9;
        }

        .lead-mobile-action-btn.open {
            background: linear-gradient(135deg, #063A1C 0%, #205A44 100%);
            color: #fff;
        }

        .lead-mobile-action-btn.task {
            background: #eff4ff;
            color: #1d4ed8;
            border: 1px solid #d5defa;
        }
        
        /* Search and filter controls */
        .flex.items-center.justify-between {
            flex-direction: column;
            align-items: flex-start;
            gap: 12px;
        }
        
        .lead-filters-row {
            width: 100%;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
            align-items: stretch;
        }

        .lead-filters-row > input,
        .lead-filters-row > select,
        .lead-filters-row > button,
        .lead-filters-row > .lead-custom-date-range {
            width: 100% !important;
            max-width: none !important;
            min-width: 0 !important;
            flex: initial !important;
        }

        .lead-filters-row input,
        .lead-filters-row select,
        .lead-filters-row button {
            padding: 10px 8px;
            min-width: 0;
            font-size: 12px;
            box-sizing: border-box;
            max-width: 100% !important;
        }

        .lead-custom-date-range {
            display: none;
            grid-column: 1 / -1;
            width: 100%;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
        }

        .lead-filters-row.show-custom-date-range .lead-custom-date-range {
            display: grid;
        }

        #fromDateFilter,
        #toDateFilter {
            width: 100%;
        }
        
        .lead-filters-row button {
            grid-column: 1 / -1;
            width: 100%;
            max-width: 100% !important;
            padding: 10px 8px;
            font-size: 12px;
        }
        
        /* Modal responsive */
        #editLeadModal .max-w-2xl {
            max-width: 95% !important;
            width: 100% !important;
            margin: 10px;
            padding: 16px !important;
        }
        
        #editLeadModal h3 {
            font-size: 18px !important;
        }
        
        /* Lead cards responsive */
        .bg-white.rounded-lg.shadow {
            padding: 16px !important;
        }
        
        /* Ensure buttons don't overflow in cards */
        .bg-white.rounded-lg.shadow .lead-card-action-row {
            width: 100%;
            box-sizing: border-box;
            overflow: visible;
        }
        
        .bg-white.rounded-lg.shadow .lead-card-action-row a,
        .bg-white.rounded-lg.shadow .lead-card-action-row button {
            flex: 1 1 0;
            min-width: 0;
            max-width: 50%;
            padding: 8px 6px;
            font-size: 11px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        /* Verified badge responsive - even smaller on mobile */
        .verified-badge {
            font-size: 9px;
            padding: 4px 8px;
        }
        
        .verified-badge i {
            font-size: 8px;
            margin-right: 3px;
        }
        
        /* Pagination responsive */
        #pagination {
            flex-direction: column;
            gap: 12px;
            align-items: center;
        }
        
        /* Hide empty state on mobile */
        .empty-state-mobile {
            display: none !important;
        }
    }
</style>
@endpush

@push('styles')
<style>
    .asm-compact-lead-ui {
        background: #f7faf7 !important;
        border-color: #dce8e0 !important;
        border-radius: 22px !important;
        box-shadow: 0 14px 34px rgba(10, 55, 32, 0.10) !important;
        padding: 8px !important;
    }

    .asm-compact-top {
        display: none;
    }

    .asm-compact-lead-ui #leadsGrid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 7px;
    }

    .asm-compact-lead-ui .asm-compact-top {
        display: block;
        border: 0;
        border-radius: 0;
        overflow: visible;
        background: transparent;
        margin-bottom: 10px;
        padding: 0;
        box-shadow: none;
    }

    .asm-compact-lead-ui .asm-compact-summary {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 6px;
        margin-bottom: 6px;
    }

    .asm-compact-lead-ui .asm-compact-tabs-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 36px 36px;
        gap: 6px;
        align-items: center;
    }

    .asm-compact-lead-ui .realtyx-strip-stat {
        min-height: 43px;
        padding: 7px 8px;
        border: 1px solid rgba(15, 107, 67, 0.10);
        border-radius: 12px;
        background: #fff;
        box-shadow: 0 5px 13px rgba(6, 66, 38, 0.045);
    }

    .asm-compact-lead-ui #realtyxStripToggle {
        display: none !important;
    }

    .asm-compact-lead-ui .asm-compact-tabs {
        display: flex;
        align-items: center;
        gap: 5px;
        min-height: 36px;
        padding: 3px;
        border: 1px solid #dce8e0;
        border-radius: 999px;
        background: #fff;
        overflow-x: auto;
        scrollbar-width: none;
    }

    .asm-compact-lead-ui .asm-compact-tabs::-webkit-scrollbar {
        display: none;
    }

    .asm-compact-lead-ui .asm-compact-tab {
        flex: 0 0 auto;
        border: 0;
        border-radius: 999px;
        padding: 8px 10px;
        background: transparent;
        color: #335445;
        font-size: 10.5px;
        line-height: 1;
        font-weight: 700;
        white-space: nowrap;
    }

    .asm-compact-lead-ui .asm-compact-tab.is-active {
        color: #fff;
        background: linear-gradient(135deg, #0f6b43 0%, #168451 100%);
        box-shadow: 0 8px 18px rgba(15, 107, 67, 0.22);
    }

    .asm-compact-lead-ui .realtyx-strip-label {
        margin-top: 3px;
        color: #64766c;
        font-size: 8.8px;
        line-height: 1.1;
        font-weight: 650;
        letter-spacing: 0;
    }

    .asm-compact-lead-ui .realtyx-strip-value {
        color: #064226;
        font-size: 15px;
        line-height: 1;
        font-weight: 760;
    }

    .asm-compact-lead-ui .realtyx-filter-toggle,
    .asm-compact-lead-ui .realtyx-strip-action {
        width: 36px;
        height: 36px;
        min-width: 36px;
        border-radius: 13px;
        display: inline-grid;
        place-items: center;
        padding: 0;
        font-size: 0;
        box-shadow: 0 8px 18px rgba(6, 66, 38, 0.08);
    }

    .asm-compact-lead-ui .realtyx-filter-toggle i,
    .asm-compact-lead-ui .realtyx-strip-action i {
        margin: 0 !important;
        font-size: 14px;
        line-height: 1;
    }

    .asm-compact-lead-ui .realtyx-strip-action {
        background: linear-gradient(135deg, #0f6b43 0%, #0b4d31 100%);
        color: #fff;
        border: 0;
        text-decoration: none;
    }

    .asm-compact-lead-ui .realtyx-strip-action::after {
        content: "";
    }

    .asm-compact-lead-ui.realtyx-filters-hidden > .flex.items-center.justify-between.mb-6 {
        display: none !important;
    }

    .asm-compact-lead-ui:not(.realtyx-filters-hidden) > .flex.items-center.justify-between.mb-6 {
        display: flex !important;
        margin: 8px 0 10px !important;
    }

    .asm-compact-lead-ui:not(.realtyx-filters-hidden) .lead-filters-row {
        display: grid !important;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 7px !important;
        overflow: visible !important;
    }

    .asm-compact-lead-ui:not(.realtyx-filters-hidden) .lead-filters-row input,
    .asm-compact-lead-ui:not(.realtyx-filters-hidden) .lead-filters-row select,
    .asm-compact-lead-ui:not(.realtyx-filters-hidden) .lead-filters-row button {
        width: 100% !important;
        max-width: none !important;
        min-width: 0 !important;
        height: 42px;
        border-radius: 12px;
        font-size: 12px;
        padding: 0 10px;
    }

    .asm-compact-lead-ui:not(.realtyx-filters-hidden) .lead-filters-row .lead-clear-filter-btn {
        grid-column: 1 / -1;
    }

    .asm-compact-lead-ui .asm-lead-card {
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(15, 107, 67, 0.12);
        border-radius: 16px;
        background: #fff;
        box-shadow: 0 6px 16px rgba(10, 55, 32, 0.07);
    }

    .asm-compact-lead-ui .asm-lead-card::before {
        content: "";
        position: absolute;
        inset: 0 auto 0 0;
        width: 3px;
        background: #0f6b43;
    }

    .asm-compact-lead-ui .asm-lead-card.follow::before { background: #2563eb; }
    .asm-compact-lead-ui .asm-lead-card.cnp::before { background: #b45309; }
    .asm-compact-lead-ui .asm-lead-card.meeting::before { background: #7c3aed; }
    .asm-compact-lead-ui .asm-lead-card.cold::before { background: #c2410c; }

    .asm-compact-lead-ui .asm-card-content {
        padding: 9px 10px 7px 13px;
    }

    .asm-compact-lead-ui .asm-card-head {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 6px;
        align-items: start;
    }

    .asm-compact-lead-ui .asm-card-identity {
        display: grid;
        grid-template-columns: 30px minmax(0, 1fr);
        gap: 7px;
        min-width: 0;
    }

    .asm-compact-lead-ui .asm-card-avatar {
        width: 30px;
        height: 30px;
        border-radius: 10px;
        display: grid;
        place-items: center;
        background: linear-gradient(135deg, #0f6b43 0%, #0a4e31 100%);
        color: #fff;
        font-size: 12px;
        font-weight: 760;
        box-shadow: 0 8px 16px rgba(15, 107, 67, 0.20);
    }

    .asm-compact-lead-ui .asm-card-name {
        overflow: hidden;
        color: #062817;
        font-size: 13px;
        line-height: 1.15;
        font-weight: 720;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .asm-compact-lead-ui .asm-card-source {
        display: flex;
        align-items: center;
        gap: 5px;
        margin-top: 2px;
        color: #64766c;
        font-size: 9.8px;
        font-weight: 560;
    }

    .asm-compact-lead-ui .asm-stage-wrap {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 5px;
        flex-wrap: wrap;
    }

    .asm-compact-lead-ui .asm-stage,
    .asm-compact-lead-ui .asm-quality {
        min-height: 24px;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        white-space: nowrap;
        line-height: 1;
        font-size: 9.8px;
        font-weight: 650;
    }

    .asm-compact-lead-ui .asm-stage {
        border: 1px solid rgba(15, 107, 67, 0.14);
        padding: 0 8px;
        background: #e7f6ee;
        color: #064226;
    }

    .asm-compact-lead-ui .asm-stage.follow { background: #edf5ff; color: #1743a6; border-color: rgba(37, 99, 235, 0.14); }
    .asm-compact-lead-ui .asm-stage.cnp { background: #fff7e6; color: #b45309; border-color: rgba(180, 83, 9, 0.16); }
    .asm-compact-lead-ui .asm-stage.meeting { background: #f3edff; color: #6d28d9; border-color: rgba(124, 58, 237, 0.16); }

    .asm-compact-lead-ui .asm-quality {
        padding: 0 7px;
        background: #eff8f3;
        color: #064226;
        border: 1px solid rgba(15, 107, 67, 0.12);
    }

    .asm-compact-lead-ui .asm-card-meta {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 5px;
        margin-top: 7px;
    }

    .asm-compact-lead-ui .asm-card-meta-item {
        min-width: 0;
        display: flex;
        align-items: center;
        gap: 4px;
        padding: 5px 7px;
        border-radius: 8px;
        background: #f8fbf9;
        color: #355446;
        font-size: 9.8px;
        line-height: 1.15;
        font-weight: 560;
    }

    .asm-compact-lead-ui .asm-card-meta-item span {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-weight: 620;
    }

    .asm-compact-lead-ui .asm-card-meta-item.owner {
        grid-column: 1 / -1;
    }

    .asm-compact-lead-ui .asm-card-note {
        display: grid;
        grid-template-columns: 16px minmax(0, 1fr);
        gap: 5px;
        margin-top: 7px;
        padding: 7px 8px;
        border: 1px solid rgba(15, 107, 67, 0.08);
        border-radius: 9px;
        background: linear-gradient(180deg, #f4faf6 0%, #eef6f1 100%);
        color: #173d2a;
        font-size: 10.3px;
        line-height: 1.28;
        font-weight: 500;
    }

    .asm-compact-lead-ui .asm-card-note span,
    .asm-compact-lead-ui .asm-card-note div {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        word-break: break-word;
    }

    .asm-compact-lead-ui .asm-card-activity {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 5px;
        margin-top: 7px;
    }

    .asm-compact-lead-ui .asm-activity-pill {
        min-width: 0;
        border: 1px solid rgba(15, 107, 67, 0.09);
        border-radius: 9px;
        background: #fbfdfb;
        padding: 6px 7px;
    }

    .asm-compact-lead-ui .asm-activity-pill span {
        display: block;
        color: #64766c;
        font-size: 8.6px;
        line-height: 1;
        font-weight: 650;
        text-transform: uppercase;
    }

    .asm-compact-lead-ui .asm-activity-pill strong {
        display: block;
        overflow: hidden;
        margin-top: 3px;
        color: #064226;
        font-size: 10.2px;
        line-height: 1.1;
        font-weight: 620;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .asm-compact-lead-ui .asm-card-actions {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 6px;
        padding: 7px 10px 9px 13px;
        background: linear-gradient(180deg, rgba(248, 251, 249, 0) 0%, #f8fbf9 100%);
    }

    .asm-compact-lead-ui .asm-card-action {
        height: 30px;
        border: 1px solid rgba(15, 107, 67, 0.10);
        border-radius: 9px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        background: #fff;
        color: #064226;
        font-size: 10px;
        font-weight: 680;
        box-shadow: 0 6px 14px rgba(6, 66, 38, 0.06);
        text-decoration: none;
    }

    .asm-compact-lead-ui .asm-card-action.primary {
        background: #0f6b43;
        color: #fff;
        border-color: #0f6b43;
    }

    .asm-compact-lead-ui .asm-card-action.whatsapp {
        color: #128c4b;
    }

    body.asm-compact-phone-active .asm-pipeline-tabs-shell,
    body.asm-compact-phone-active .lead-mobile-add-only {
        display: none !important;
    }
</style>
@endpush

@push('styles')
<style>
    .lead-task-option-card {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 14px 16px;
        border: 1px solid #dbe3ef;
        border-radius: 16px;
        background: #ffffff;
        cursor: pointer;
    }

    .lead-task-option-card input[type="radio"] {
        margin-top: 4px;
        flex-shrink: 0;
    }

    .lead-task-option-copy {
        display: flex;
        flex-direction: column;
        gap: 4px;
        min-width: 0;
    }

    .lead-task-option-copy strong {
        color: #0f172a;
        font-size: 14px;
        line-height: 1.4;
    }

    .lead-task-option-copy small {
        color: #64748b;
        font-size: 12px;
        line-height: 1.4;
    }

    #leadTaskDetailModal {
        background: transparent;
    }

    .lead-task-detail-viewport {
        width: min(calc(100vw - 24px), 460px);
        height: 560px;
        max-width: 100%;
        transition: width 0.18s ease, height 0.18s ease;
        background: transparent;
        box-shadow: none;
    }

    .lead-task-detail-frame {
        display: block;
        width: 100%;
        height: 100%;
        min-height: 0;
        border: 0;
        background: transparent;
        border-radius: 28px;
    }

    @media (max-width: 768px) {
        .lead-task-detail-viewport {
            width: min(calc(100vw - 16px), 460px);
            height: 560px;
        }
    }
</style>
@endpush

@section('content')
  @php($showSeniorManagerUserFilter = auth()->check() && auth()->user()->isSeniorManager())
  @php($showAsmDateFilters = auth()->check() && (auth()->user()->isAssistantSalesManager() || auth()->user()->isSeniorManager()))
  @php($isLeadSheetUser = auth()->check())
  @php($hasAsmCompactLeadPhoneView = auth()->check())
@include('sales-manager.partials.pipeline-tabs')
  <div class="flex justify-end mb-4 {{ $isLeadSheetUser ? 'lead-mobile-add-only' : '' }}">
      <a href="{{ route('sales-manager.leads.create') }}"
         class="inline-flex items-center gap-2 px-5 py-3 rounded-xl text-white font-semibold shadow"
         style="background:#064e3b;text-decoration:none;">
          <i class="fas fa-plus"></i>
          Add Lead
      </a>
  </div>
  <div id="realtyxLeadPanel" class="bg-white rounded-lg shadow p-6 mb-6 asm-leads-panel {{ $isLeadSheetUser ? 'realtyx-lead-sheet-panel realtyx-filters-hidden' : '' }} {{ $hasAsmCompactLeadPhoneView ? 'asm-compact-phone-enabled' : '' }}">
      @if($hasAsmCompactLeadPhoneView)
          <div id="asmCompactLeadStrip" class="asm-compact-top">
              <div class="asm-compact-summary">
                  <div class="realtyx-strip-stat">
                      <div class="realtyx-strip-value" id="asmCompactStripFresh">0</div>
                      <div class="realtyx-strip-label">Fresh</div>
                  </div>
                  <div class="realtyx-strip-stat">
                      <div class="realtyx-strip-value" id="asmCompactStripFollowUp">0</div>
                      <div class="realtyx-strip-label">Follow Up</div>
                  </div>
                  <div class="realtyx-strip-stat">
                      <div class="realtyx-strip-value" id="asmCompactStripMeetings">0</div>
                      <div class="realtyx-strip-label">Meeting</div>
                  </div>
                  <div class="realtyx-strip-stat">
                      <div class="realtyx-strip-value" id="asmCompactStripVisits">0</div>
                      <div class="realtyx-strip-label">Site Visit</div>
                  </div>
                  <div class="realtyx-strip-stat">
                      <div class="realtyx-strip-value" id="asmCompactStripTotal">0</div>
                      <div class="realtyx-strip-label">All Leads</div>
                  </div>
              </div>
              <div class="asm-compact-tabs-row">
                  <nav class="asm-compact-tabs" aria-label="Lead filters">
                      <button type="button" class="asm-compact-tab" data-pipeline-status="fresh" onclick="applyAsmLeadPipelineFilter('fresh')">Fresh</button>
                      <button type="button" class="asm-compact-tab" data-pipeline-status="follow_up" onclick="applyAsmLeadPipelineFilter('follow_up')">Follow Up</button>
                      <button type="button" class="asm-compact-tab" data-pipeline-status="meeting" onclick="applyAsmLeadPipelineFilter('meeting')">Meeting</button>
                      <button type="button" class="asm-compact-tab" data-pipeline-status="visit" onclick="applyAsmLeadPipelineFilter('visit')">Site Visit</button>
                      <button type="button" class="asm-compact-tab" data-pipeline-status="cnp" onclick="applyAsmLeadPipelineFilter('cnp')">CNP</button>
                      <button type="button" class="asm-compact-tab is-active" data-pipeline-status="" onclick="applyAsmLeadPipelineFilter('')">All Lead</button>
                  </nav>
                  <button type="button" id="asmCompactFilterToggle" class="realtyx-filter-toggle" onclick="toggleRealtyxLeadFilters()" title="Show filters">
                      <i class="fas fa-filter"></i>
                  </button>
                  <a href="{{ route('sales-manager.leads.create') }}" class="realtyx-strip-action" title="Add Lead">
                      <i class="fas fa-plus"></i>
                  </a>
              </div>
          </div>
      @endif
      @if($isLeadSheetUser)
          <div id="realtyxLeadStrip" class="realtyx-strip">
              <div class="realtyx-strip-stat">
                  <div class="realtyx-strip-label">Total Leads</div>
                  <div class="realtyx-strip-value" id="realtyxStripTotal">0</div>
              </div>
              <div class="realtyx-strip-stat">
                  <div class="realtyx-strip-label">Verified</div>
                  <div class="realtyx-strip-value" id="realtyxStripVerified">0</div>
              </div>
              <div class="realtyx-strip-stat">
                  <div class="realtyx-strip-label">Follow-up</div>
                  <div class="realtyx-strip-value" id="realtyxStripFollowUp">0</div>
              </div>
              <div class="realtyx-strip-stat">
                  <div class="realtyx-strip-label">Visits</div>
                  <div class="realtyx-strip-value" id="realtyxStripVisits">0</div>
              </div>
              <div class="realtyx-strip-stat">
                  <div class="realtyx-strip-label">Meetings</div>
                  <div class="realtyx-strip-value" id="realtyxStripMeetings">0</div>
              </div>
              <button type="button" id="realtyxStripToggle" class="realtyx-strip-toggle" onclick="toggleRealtyxLeadStrip()">Hide</button>
              <button type="button" id="realtyxFilterToggle" class="realtyx-filter-toggle" onclick="toggleRealtyxLeadFilters()" title="Show filters">
                  <i class="fas fa-filter"></i>
              </button>
              <a href="{{ route('sales-manager.leads.create') }}" class="realtyx-strip-action">
                  <i class="fas fa-plus"></i>
                  Add Lead
              </a>
          </div>
      @endif
      <div class="flex items-center justify-between mb-6" style="flex-wrap: wrap; gap: 12px;">
          <div class="lead-filters-row flex gap-2 {{ $showAsmDateFilters ? 'show-date-filters' : '' }}" style="flex-wrap: nowrap; align-items: center; width: 100%; max-width: 100%; box-sizing: border-box; overflow: hidden;">
            <input 
                type="text" 
                id="searchInput"
                placeholder="Search leads..." 
                class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                style="flex: 1; min-width: 0; max-width: 25%; box-sizing: border-box;"
                onkeyup="handleSearch()"
            >
            <select 
                id="statusFilter"
                class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                style="flex: 1; min-width: 0; max-width: 25%; box-sizing: border-box;"
                onchange="loadLeads()"
            >
                <option value="">All</option>
                <option value="fresh">Fresh</option>
                <option value="new_reenquiry">New / Re-enquiry</option>
                <option value="new">New Only</option>
                <option value="prospect">Prospect</option>
                <option value="follow_up">Follow Up</option>
                <option value="cnp">CNP</option>
                <option value="visit">Visit</option>
                <option value="meeting">Meeting</option>
                <option value="closer">Closer</option>
                <option value="reenquiry">Re-enquiry</option>
                <option value="connected">Connected</option>
                <option value="on_hold">On Hold</option>
                <option value="not_interested">Not Interested</option>
                <option value="junk">Junk</option>
                <option value="dead">Dead</option>
                <option value="fresh_transfer">Fresh Transfer</option>
                <option value="meeting_scheduled">Meeting Scheduled</option>
                <option value="meeting_completed">Meeting Completed</option>
                <option value="visit_scheduled">Visit Scheduled</option>
                <option value="visit_done">Visit Done</option>
                <option value="revisited_scheduled">Revisit Scheduled</option>
                <option value="revisited_completed">Revisit Completed</option>
            </select>
              @if($showSeniorManagerUserFilter)
                  <select 
                    id="userFilter"
                      class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                      style="flex: 1; min-width: 0; max-width: 18%; box-sizing: border-box;"
                      onchange="loadLeads()"
                  >
                      <option value="">All Users</option>
                      <!-- Options will be populated dynamically -->
                  </select>
              @endif
            @if($showAsmDateFilters)
                <select
                    id="datePresetFilter"
                    class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                    style="flex: 1; min-width: 0; max-width: 18%; box-sizing: border-box;"
                    onchange="handleLeadDatePresetChange()"
                >
                    <option value="">All Dates</option>
                    <option value="today">Today</option>
                    <option value="yesterday">Yesterday</option>
                    <option value="this_week">This Week</option>
                    <option value="this_month">This Month</option>
                    <option value="this_year">This Year</option>
                    <option value="custom">Custom Range</option>
                </select>
                <div class="lead-custom-date-range">
                    <input
                        type="date"
                        id="fromDateFilter"
                        class="lead-date-filter px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                        style="flex: 1; min-width: 0; max-width: 14%; box-sizing: border-box;"
                        onchange="loadLeads()"
                    >
                    <input
                        type="date"
                        id="toDateFilter"
                        class="lead-date-filter px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                        style="flex: 1; min-width: 0; max-width: 14%; box-sizing: border-box;"
                        onchange="loadLeads()"
                    >
                </div>
            @endif
            <button
                type="button"
                class="lead-apply-filter-btn px-4 py-2 rounded-lg text-sm font-semibold text-white"
                style="background: linear-gradient(135deg, #063A1C, #0f6b46); white-space: nowrap;"
                onclick="applyLeadFilters()"
            >
                Apply
            </button>
            <button
                type="button"
                class="lead-clear-filter-btn px-4 py-2 border border-gray-300 rounded-lg text-sm font-semibold text-gray-700 hover:bg-gray-50"
                onclick="clearLeadFilters()"
            >
                Clear
            </button>
        </div>
    </div>
    <div id="leadFilterNotice" class="mb-4 px-4 py-3 rounded-lg border border-green-200 bg-green-50 text-sm text-green-800" style="display: none;"></div>

    <!-- Loading State -->
    <div id="loadingState" class="text-center py-12">
        <i class="fas fa-spinner fa-spin text-gray-400 text-4xl mb-4"></i>
        <p class="text-gray-500">Loading leads...</p>
    </div>

    <!-- Empty State -->
    <div id="emptyState" class="text-center py-12 empty-state-mobile" style="display: none;">
        <i class="fas fa-user-friends text-gray-300 text-6xl mb-4"></i>
        <h3 class="text-xl font-semibold text-gray-700 mb-2">No Leads Found</h3>
        <p class="text-gray-500">No leads match your current filters.</p>
    </div>

    <!-- Leads Cards -->
    <div id="leadsCards" style="display: none;">
        <div id="leadsGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <!-- Leads will be loaded here -->
        </div>
        
        <!-- Pagination -->
        <div id="pagination" class="mt-6 flex items-center justify-between">
            <!-- Pagination will be loaded here -->
        </div>
    </div>

    <div id="leadsList" class="{{ $isLeadSheetUser ? 'realtyx-lead-sheet' : '' }}" style="display: none;">
        <div class="leads-list-shell">
            <table class="leads-list-table">
                <thead>
                    <tr>
                        <th>Lead</th>
                        <th>Status</th>
                        @if($isLeadSheetUser)
                            <th>Next Action</th>
                        @endif
                        <th>Remark</th>
                        <th>Assigned</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="leadsListBody">
                    <!-- Leads list rows -->
                </tbody>
            </table>
        </div>

        <div id="paginationList" class="mt-6 flex items-center justify-between">
            <!-- Pagination will be loaded here -->
        </div>
    </div>
</div>

<!-- Edit Lead Modal -->
<div id="editLeadModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full p-6 max-h-[90vh] overflow-y-auto">
            <h3 class="text-xl font-bold text-gray-900 mb-4">Edit Lead</h3>
            <div id="editLeadModalContent">
                <!-- Content will be loaded here -->
            </div>
            <div class="mt-6 flex gap-2">
                <button 
                    onclick="closeEditLeadModal()" 
                    class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300"
                >
                    Cancel
                </button>
                <button 
                    onclick="submitUpdateLead()" 
                    class="px-4 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg hover:from-[#205A44] hover:to-[#15803d]"
                >
                    Update Lead
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Short Details Modal -->
<div id="shortDetailsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Short Details</h3>
                <button onclick="closeShortDetailsModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div id="shortDetailsContent" class="text-gray-700">
                <!-- Lead details will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Lead Task Menu Modal -->
<div id="leadTaskMenuModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-xl max-w-sm w-full p-5 border border-gray-200">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">Lead Actions</h3>
                    <p class="text-sm text-gray-500">Choose what you want to do next.</p>
                </div>
                <button type="button" onclick="closeLeadTaskMenu()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="space-y-3">
                <button type="button" onclick="openLeadTaskMeeting()" class="w-full px-4 py-3 rounded-xl bg-green-600 text-white font-semibold hover:bg-green-700 transition-colors text-left">
                    <i class="fas fa-handshake mr-2"></i>Schedule Meeting
                </button>
                <button type="button" onclick="openLeadTaskSiteVisit()" class="w-full px-4 py-3 rounded-xl bg-blue-600 text-white font-semibold hover:bg-blue-700 transition-colors text-left">
                    <i class="fas fa-map-marker-alt mr-2"></i>Schedule Site Visit
                </button>
                <button type="button" onclick="openLeadTaskEdit()" class="w-full px-4 py-3 rounded-xl border border-gray-300 text-gray-700 font-semibold hover:bg-gray-50 transition-colors text-left">
                    <i class="fas fa-pen mr-2"></i>Edit Lead
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Schedule Meeting Modal -->
<div id="scheduleMeetingModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full p-6 max-h-[90vh] overflow-y-auto">
            <h3 class="text-xl font-bold text-gray-900 mb-4">Schedule Meeting</h3>
            <div id="scheduleMeetingModalContent">
                <!-- Content will be loaded here -->
            </div>
            <div class="mt-6 flex gap-2">
                <button 
                    onclick="closeScheduleMeetingModal()" 
                    class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300"
                >
                    Cancel
                </button>
                <button 
                    onclick="submitCreateMeeting()" 
                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700"
                >
                    Schedule Meeting
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Schedule Site Visit Modal -->
<div id="scheduleSiteVisitModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-lg w-full p-6">
            <h3 class="text-xl font-bold text-gray-900 mb-4">Schedule Site Visit</h3>
            <div id="scheduleSiteVisitModalContent">
                <!-- Content will be loaded here -->
            </div>
            <div class="mt-6 flex gap-2">
                <button 
                    onclick="closeScheduleSiteVisitModal()" 
                    class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300"
                >
                    Cancel
                </button>
                <button 
                    onclick="submitCreateSiteVisit()" 
                    class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700"
                >
                    Schedule Site Visit
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add Lead Modal -->
<div id="addLeadModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
            <h3 class="text-xl font-bold text-gray-900 mb-4">Add New Lead</h3>
            <form id="addLeadForm" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                    <input 
                        type="text" 
                        id="addLeadName" 
                        required
                        placeholder="Enter lead name"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                    >
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone <span class="text-red-500">*</span></label>
                    <input 
                        type="tel" 
                        id="addLeadPhone" 
                        required
                        placeholder="Enter phone number"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                    >
                </div>
                <div id="addLeadError" class="text-red-500 text-sm" style="display: none;"></div>
            </form>
            <div class="mt-6 flex gap-2">
                <button 
                    onclick="closeAddLeadModal()" 
                    class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300"
                >
                    Cancel
                </button>
                <button 
                    onclick="submitAddLead()" 
                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700"
                >
                    Add Lead
                </button>
            </div>
        </div>
    </div>
</div>

<div id="completeAsmTaskModal" class="fixed inset-0 bg-black/40 hidden overflow-y-auto h-full w-full z-50">
    <div class="min-h-full flex items-center justify-center p-4">
        <div class="w-full max-w-3xl rounded-[28px] bg-white shadow-2xl overflow-hidden border border-slate-200">
            <div class="flex items-center justify-between px-6 py-5 border-b border-slate-200">
                <div>
                    <h3 class="text-xl font-bold text-slate-900">Select Task</h3>
                    <p id="leadTaskSelectionSubtitle" class="mt-1 text-sm text-slate-500">Select which open task should be handled from this lead.</p>
                </div>
                <button type="button" onclick="closeCompleteAsmTaskModal()" class="text-slate-400 hover:text-slate-600 text-xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="completeAsmTaskForm" onsubmit="submitCompleteAsmTask(event)" class="p-6 md:p-8">
                <div id="leadTaskSelectionOptions" class="space-y-3 max-h-[60vh] overflow-y-auto pr-1"></div>
                <div id="leadTaskSelectionEmpty" class="hidden rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                    No open task found for this lead.
                </div>
                <div class="flex justify-end gap-3 pt-6">
                    <button type="button" onclick="closeCompleteAsmTaskModal()" class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-50">
                        Cancel
                    </button>
                    <button id="leadTaskSelectionSubmitBtn" type="submit" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white">
                        Continue
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="leadTaskDetailModal" class="fixed inset-0 bg-black/50 z-[60] hidden">
    <div class="flex items-center justify-center min-h-screen p-3 md:p-5">
        <div id="leadTaskDetailViewport" class="lead-task-detail-viewport relative overflow-hidden">
            <iframe id="leadTaskDetailFrame" class="lead-task-detail-frame" src="about:blank" loading="lazy"></iframe>
        </div>
    </div>
</div>
@include('partials.lead-cloud-call-menu')
@endsection

@push('scripts')
<script>
    const API_BASE_URL = '{{ url("/api") }}';
    const SALES_MANAGER_API_URL = '{{ url("/api/sales-manager") }}';
    const API_TOKEN = '{{ $api_token }}';
    const MANAGER_LEAD_TASK_PARITY_ENABLED = @json(optional(auth()->user())->isAssistantSalesManager() || optional(auth()->user())->isSeniorManager());
    const ASM_SECTION_VIEW_PREFERENCES = @json($sectionViewPreferences ?? []);
    const ASM_SECTION_VIEW_SAVE_URL = @json(route('sales-manager.settings.update'));
    const LOGGED_IN_USER_NAME = '{{ auth()->user()->name }}';
    const LOGGED_IN_USER_ID = {{ auth()->user()->id }};
    const IS_REALTYX_LEAD_SHEET_USER = @json($isLeadSheetUser);
    const HAS_ASM_COMPACT_LEAD_PHONE_VIEW = @json($hasAsmCompactLeadPhoneView);
    const IS_SENIOR_MANAGER = @json(optional(auth()->user())->isSeniorManager());
    const MANAGER_NAME = @if(auth()->user()->manager_id && auth()->user()->manager) '{{ auth()->user()->manager->name }}' @else '{{ auth()->user()->name }}' @endif;
    let searchTimeout = null;
    let allLeads = [];
    let currentLeadId = null;
    let activeLeadTaskLeadId = null;
    let teamMembers = [];
    let currentUser = null;
    let currentLeadView = 'list';
    let currentLeadPage = 1;
    let favoriteLeadIds = new Set();

    function isDesktopLeadSheet() {
        return IS_REALTYX_LEAD_SHEET_USER && window.matchMedia('(min-width: 769px)').matches;
    }

    function isAsmCompactLeadMode() {
        return HAS_ASM_COMPACT_LEAD_PHONE_VIEW && window.matchMedia('(max-width: 768px)').matches;
    }

    function usesToggleableLeadFilters() {
        return isDesktopLeadSheet() || isAsmCompactLeadMode();
    }

    function applyAsmCompactLeadModeState() {
        const isCompact = isAsmCompactLeadMode();
        const panel = document.getElementById('realtyxLeadPanel');
        panel?.classList.toggle('asm-compact-lead-ui', isCompact);
        document.body.classList.toggle('asm-compact-phone-active', isCompact);
        return isCompact;
    }

    if (isDesktopLeadSheet()) {
        document.body.classList.add('realtyx-leads-fullscreen');
    }
    const leadPageParams = new URLSearchParams(window.location.search);
    const leadPageFlags = {
        freshToday: leadPageParams.get('fresh_today') === '1',
    };
    const leadFilterStorageKey = 'salesManagerLeadsFilters';
    const leadPerPageStorageKey = 'salesManagerLeadsPerPage';
    const realtyxLeadStripStorageKey = 'realtyxLeadStripHidden';
    const realtyxLeadFiltersStorageKey = 'realtyxLeadFiltersVisible';
    const leadPerPageOptions = [15, 50, 100, 200, 500];

    function getStoredLeadFilters() {
        try {
            return JSON.parse(localStorage.getItem(leadFilterStorageKey) || '{}');
        } catch (e) {
            return {};
        }
    }

    function persistLeadFilters(filters) {
        try {
            localStorage.setItem(leadFilterStorageKey, JSON.stringify(filters));
        } catch (e) {
            console.error('Failed to persist lead filters:', e);
        }
    }

    function formatDateFilterValue(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    function resolveLeadDateRangeFromPreset(preset) {
        const now = new Date();
        const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        const makeRange = (start, end) => ({
            from: formatDateFilterValue(start),
            to: formatDateFilterValue(end),
        });

        switch (preset) {
            case 'today':
                return makeRange(today, today);
            case 'yesterday': {
                const yesterday = new Date(today);
                yesterday.setDate(yesterday.getDate() - 1);
                return makeRange(yesterday, yesterday);
            }
            case 'this_week': {
                const start = new Date(today);
                const day = start.getDay();
                const diff = day === 0 ? 6 : day - 1;
                start.setDate(start.getDate() - diff);
                return makeRange(start, today);
            }
            case 'this_month': {
                const start = new Date(today.getFullYear(), today.getMonth(), 1);
                return makeRange(start, today);
            }
            case 'this_year': {
                const start = new Date(today.getFullYear(), 0, 1);
                return makeRange(start, today);
            }
            default:
                return { from: '', to: '' };
        }
    }

    function syncLeadDateFilterVisibility() {
        const filterRow = document.querySelector('.lead-filters-row');
        const datePresetFilter = document.getElementById('datePresetFilter');
        if (!filterRow || !datePresetFilter) {
            return;
        }

        filterRow.classList.toggle('show-custom-date-range', datePresetFilter.value === 'custom');
    }

    function handleLeadDatePresetChange() {
        const datePresetFilter = document.getElementById('datePresetFilter');
        const fromDateFilter = document.getElementById('fromDateFilter');
        const toDateFilter = document.getElementById('toDateFilter');

        if (!datePresetFilter) {
            return;
        }

        const preset = datePresetFilter.value || '';
        syncLeadDateFilterVisibility();

        if (preset && preset !== 'custom') {
            const range = resolveLeadDateRangeFromPreset(preset);
            if (fromDateFilter) {
                fromDateFilter.value = range.from;
            }
            if (toDateFilter) {
                toDateFilter.value = range.to;
            }
        } else if (preset === '') {
            if (fromDateFilter) {
                fromDateFilter.value = '';
            }
            if (toDateFilter) {
                toDateFilter.value = '';
            }
        }

        loadLeads(1);
    }

    // Get auth headers with Bearer token
    function getAuthHeaders() {
        return {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${API_TOKEN}`,
        };
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

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

    function setLeadView(view, shouldPersist = true) {
        if (isDesktopLeadSheet()) {
            view = 'list';
            shouldPersist = false;
        }

        if (isAsmCompactLeadMode()) {
            view = 'cards';
            shouldPersist = false;
        }

        currentLeadView = view === 'list' ? 'list' : 'cards';
        document.getElementById('leadCardsViewBtn')?.classList.toggle('active', currentLeadView === 'cards');
        document.getElementById('leadListViewBtn')?.classList.toggle('active', currentLeadView === 'list');
        document.getElementById('leadsCards').style.display = currentLeadView === 'cards' && allLeads.length ? 'block' : 'none';
        document.getElementById('leadsList').style.display = currentLeadView === 'list' && allLeads.length ? 'block' : 'none';
        if (shouldPersist) {
            persistAsmSectionViewPreference('leads', currentLeadView);
        }
    }

    function applyLeadQueryFilters() {
        const searchInput = document.getElementById('searchInput');
        const statusFilter = document.getElementById('statusFilter');
        const userFilter = document.getElementById('userFilter');
        const datePresetFilter = document.getElementById('datePresetFilter');
        const fromDateFilter = document.getElementById('fromDateFilter');
        const toDateFilter = document.getElementById('toDateFilter');
        const notice = document.getElementById('leadFilterNotice');

        const storedFilters = getStoredLeadFilters();
        const search = leadPageParams.get('search') ?? storedFilters.search ?? '';
        const status = leadPageParams.get('status') ?? storedFilters.status ?? '';
        const assignedTo = leadPageParams.get('assigned_to') ?? storedFilters.assigned_to ?? '';
        const datePreset = leadPageParams.get('date_preset') ?? storedFilters.date_preset ?? '';
        const fromDate = leadPageParams.get('from_date') ?? storedFilters.from_date ?? '';
        const toDate = leadPageParams.get('to_date') ?? storedFilters.to_date ?? '';
        const perPage = resolveLeadPerPage(
            leadPageParams.get('per_page')
            ?? storedFilters.per_page
            ?? localStorage.getItem(leadPerPageStorageKey)
            ?? 15
        );

        if (searchInput && search) searchInput.value = search;
        if (statusFilter && status) statusFilter.value = status;
        if (userFilter && assignedTo) userFilter.value = assignedTo;
        if (datePresetFilter) {
            if (datePreset) {
                datePresetFilter.value = datePreset;
            } else if (fromDate || toDate) {
                datePresetFilter.value = 'custom';
            }
        }
        if (fromDateFilter && fromDate) fromDateFilter.value = fromDate;
        if (toDateFilter && toDate) toDateFilter.value = toDate;
        syncLeadDateFilterVisibility();
        try {
            localStorage.setItem(leadPerPageStorageKey, String(perPage));
        } catch (e) {
            console.error('Failed to persist lead per-page filter:', e);
        }

        if (notice) {
            if (leadPageFlags.freshToday) {
                notice.textContent = 'Showing fresh leads assigned to you today.';
                notice.style.display = 'block';
            } else {
                notice.style.display = 'none';
            }
        }
    }

    function updateAsmLeadPipelineActiveState(status) {
        const normalizedStatus = String(status || '').trim().toLowerCase();
        document.querySelectorAll('[data-pipeline-status]').forEach((button) => {
            const buttonStatus = String(button.dataset.pipelineStatus || '').trim().toLowerCase();
            button.classList.toggle('is-active', buttonStatus === normalizedStatus);
        });
    }

    function updateAsmLeadPipelineCounts(counts = {}) {
        const normalizedCounts = {
            all: Number(counts.all || 0),
            fresh: Number(counts.fresh ?? counts.new ?? 0),
            prospect: Number(counts.prospect || 0),
            follow_up: Number(counts.follow_up || 0),
            meeting: Number(counts.meeting || 0),
            visit: Number(counts.visit || 0),
            cnp: Number(counts.cnp || 0),
        };

        const bindings = {
            asmLeadPipelineCountAll: normalizedCounts.all,
            asmLeadPipelineCountFresh: normalizedCounts.fresh,
            asmLeadPipelineCountFollowUp: normalizedCounts.follow_up,
            asmLeadPipelineCountMeeting: normalizedCounts.meeting,
            asmLeadPipelineCountVisit: normalizedCounts.visit,
            asmLeadPipelineCountCnp: normalizedCounts.cnp,
        };

        Object.entries(bindings).forEach(([id, value]) => {
            const node = document.getElementById(id);
            if (node) {
                node.textContent = String(value);
            }
        });

        const stripBindings = {
            realtyxStripTotal: normalizedCounts.all,
            realtyxStripVerified: normalizedCounts.prospect,
            realtyxStripFollowUp: normalizedCounts.follow_up,
            realtyxStripVisits: normalizedCounts.visit,
            realtyxStripMeetings: normalizedCounts.meeting,
            asmCompactStripFresh: normalizedCounts.fresh,
            asmCompactStripTotal: normalizedCounts.all,
            asmCompactStripFollowUp: normalizedCounts.follow_up,
            asmCompactStripMeetings: normalizedCounts.meeting,
            asmCompactStripVisits: normalizedCounts.visit,
        };

        Object.entries(stripBindings).forEach(([id, value]) => {
            const node = document.getElementById(id);
            if (node) {
                node.textContent = String(value);
            }
        });
    }

    function applyRealtyxLeadStripState() {
        if (!isDesktopLeadSheet()) {
            return;
        }

        const strip = document.getElementById('realtyxLeadStrip');
        const toggle = document.getElementById('realtyxStripToggle');
        if (!strip || !toggle) {
            return;
        }

        const isHidden = localStorage.getItem(realtyxLeadStripStorageKey) === '1';
        strip.classList.toggle('is-hidden', isHidden);
        toggle.textContent = isHidden ? 'Show Summary' : 'Hide';
    }

    function toggleRealtyxLeadStrip() {
        if (!isDesktopLeadSheet()) {
            return;
        }

        const strip = document.getElementById('realtyxLeadStrip');
        const shouldHide = !strip?.classList.contains('is-hidden');
        localStorage.setItem(realtyxLeadStripStorageKey, shouldHide ? '1' : '0');
        applyRealtyxLeadStripState();
    }

    function applyRealtyxLeadFiltersState() {
        if (!usesToggleableLeadFilters()) {
            return;
        }

        const panel = document.getElementById('realtyxLeadPanel');
        const toggle = document.getElementById(isAsmCompactLeadMode() ? 'asmCompactFilterToggle' : 'realtyxFilterToggle');
        if (!panel || !toggle) {
            return;
        }

        const storageKey = isAsmCompactLeadMode() ? `${realtyxLeadFiltersStorageKey}:compact` : realtyxLeadFiltersStorageKey;
        const isVisible = localStorage.getItem(storageKey) === '1';
        panel.classList.toggle('realtyx-filters-hidden', !isVisible);
        toggle.classList.toggle('is-active', isVisible);
        toggle.title = isVisible ? 'Hide filters' : 'Show filters';
    }

    function toggleRealtyxLeadFilters() {
        if (!usesToggleableLeadFilters()) {
            return;
        }

        const panel = document.getElementById('realtyxLeadPanel');
        const shouldShow = panel?.classList.contains('realtyx-filters-hidden');
        const storageKey = isAsmCompactLeadMode() ? `${realtyxLeadFiltersStorageKey}:compact` : realtyxLeadFiltersStorageKey;
        localStorage.setItem(storageKey, shouldShow ? '1' : '0');
        applyRealtyxLeadFiltersState();
    }

    function applyAsmLeadPipelineFilter(status) {
        const statusFilter = document.getElementById('statusFilter');
        if (!statusFilter) {
            return;
        }

        statusFilter.value = status || '';
        updateAsmLeadPipelineActiveState(statusFilter.value);
        loadLeads(1);
    }

    window.applyAsmLeadPipelineFilter = applyAsmLeadPipelineFilter;

    function resolveLeadPerPage(value) {
        const numericValue = Number(value);
        return leadPerPageOptions.includes(numericValue) ? numericValue : 15;
    }

    function getLeadPerPage() {
        return resolveLeadPerPage(localStorage.getItem(leadPerPageStorageKey) || 15);
    }

    function handleLeadPerPageChange(nextValue) {
        const perPage = resolveLeadPerPage(nextValue);
        try {
            localStorage.setItem(leadPerPageStorageKey, String(perPage));
        } catch (e) {
            console.error('Failed to persist lead per-page selection:', e);
        }
        loadLeads(1);
    }

    function renderLeadPerPageControl(selectedPerPage) {
        const options = leadPerPageOptions.map((option) => `
            <option value="${option}" ${option === selectedPerPage ? 'selected' : ''}>${option}</option>
        `).join('');

        return `
            <label class="flex items-center gap-2 text-sm text-gray-600 whitespace-nowrap">
                <span>Show</span>
                <select
                    class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 bg-white text-sm"
                    onchange="handleLeadPerPageChange(this.value);"
                >
                    ${options}
                </select>
            </label>
        `;
    }

    function getLeadRemark(lead) {
        const isRawMetaRemark = (value) => {
            const text = String(value || '').toLowerCase();
            const markers = [
                'inbox_url:',
                'location_preffered:',
                'location_preferred:',
                'apartment_budet:',
                'apartment_budget:',
                'exact_status_or_remarks',
                'property_type:',
                'https://business.facebook.com/',
            ];
            const hits = markers.filter((marker) => text.includes(marker)).length;
            return hits >= 2 || text.includes('https://business.facebook.com/');
        };

        const cleanRemark = (value) => {
            let text = String(value || '').trim();
            if (!text) {
                return '';
            }

            text = text
                .replace(/^\[\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}(?::\d{2})?)?\]\s*/i, '')
                .replace(/^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}(?::\d{2})?)?\s*[-|:]\s*/i, '')
                .replace(/^(?:ASM|SM|CRM|Manager)\s+outcome:\s*/i, '')
                .trim();

            return isRawMetaRemark(text) ? '' : text;
        };

        if (typeof lead.latest_remark === 'string' && lead.latest_remark.trim()) {
            const cleanedLatestRemark = cleanRemark(lead.latest_remark);
            if (cleanedLatestRemark) {
                return cleanedLatestRemark;
            }
        }

        const formValues = lead.form_values || lead.formFields || {};
        const candidates = [
            lead.manager_remark,
            lead.remark,
            lead.notes,
            lead.requirements,
            formValues.manager_remark,
            formValues.remark,
        ];

        for (const item of candidates) {
            if (typeof item !== 'string' || !item.trim()) {
                continue;
            }

            const cleaned = cleanRemark(item);
            if (cleaned) {
                return cleaned;
            }
        }

        return 'No remark added';
    }

    function compactLeadRemarkText(remark, maxLength = 115) {
        const text = String(remark || '').replace(/\s+/g, ' ').trim();
        if (text.length <= maxLength) {
            return text;
        }

        return `${text.slice(0, maxLength - 1).trim()}...`;
    }

    function getLeadNextActionSummary(lead) {
        if (typeof lead.next_action_summary === 'string' && lead.next_action_summary.trim()) {
            return lead.next_action_summary.trim();
        }

        return 'No next action';
    }

    function getLeadNextActionPayload(lead) {
        const payload = lead && typeof lead.next_action === 'object' && lead.next_action !== null
            ? lead.next_action
            : {};

        return {
            type: payload.type || null,
            label: payload.label || lead.next_action_label || 'No Action',
            scheduled_at: payload.scheduled_at || lead.next_action_at || null,
            summary: payload.summary || getLeadNextActionSummary(lead),
            is_today: Boolean(payload.is_today),
            is_overdue: Boolean(payload.is_overdue),
            url: payload.url || null,
        };
    }

    function getLeadNextActionTone(action) {
        if (action.is_overdue) return 'overdue';
        if (action.is_today) return 'today';
        if (action.scheduled_at) return 'upcoming';
        return 'none';
    }

    function createLeadNextActionCell(lead) {
        const action = getLeadNextActionPayload(lead);
        const label = action.label || 'No Action';
        const summary = action.summary || 'No next action';
        const tone = getLeadNextActionTone(action);
        const icon = tone === 'overdue'
            ? 'fa-triangle-exclamation'
            : (tone === 'today' ? 'fa-calendar-check' : (tone === 'upcoming' ? 'fa-clock' : 'fa-minus'));

        return `
            <td class="lead-next-action-cell">
                <div title="${escapeHtml(summary)}">
                    <span class="lead-next-action-badge ${tone}">
                        <i class="fas ${icon}"></i>
                        ${escapeHtml(label)}
                    </span>
                    <span class="lead-next-action-time">${escapeHtml(summary)}</span>
                </div>
            </td>
        `;
    }

    function formatLeadStatus(status) {
        const normalized = String(status || 'new').toLowerCase();
        const specialLabels = {
            fresh_transfer: 'Fresh Transfer',
            follow_up: 'Follow Up',
            cnp: 'CNP',
        };

        return specialLabels[normalized] || String(status || 'new')
            .replace(/_/g, ' ')
            .replace(/\b\w/g, (char) => char.toUpperCase());
    }

    function formatLeadSource(source) {
        return String(source || 'direct')
            .replace(/_/g, ' ')
            .replace(/\b\w/g, (char) => char.toUpperCase());
    }

    function getImportChannelBadge(tag) {
        if (!tag) {
            return '';
        }

        return `<span class="inline-flex items-center rounded-full border border-indigo-200 bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.08em] text-indigo-700">${escapeHtml(tag)}</span>`;
    }

    function isFavoriteLead(lead) {
        return Boolean(lead && (lead.is_favorite || favoriteLeadIds.has(Number(lead.id))));
    }

    let activeLeadTaskId = null;

    function extractLeadTaskErrorMessage(result, fallback = 'Failed to load lead task') {
        const firstValidationError = result?.errors
            ? Object.values(result.errors).flat().find(Boolean)
            : null;

        return firstValidationError || result?.message || result?.error || fallback;
    }

    function showLeadTaskMessage(message, type = 'success', duration = 3000) {
        if (typeof showNotification === 'function') {
            showNotification(message, type, duration);
            return;
        }

        alert(message);
    }

    function buildLeadTaskSelectionOptions(tasks, leadId) {
        const container = document.getElementById('leadTaskSelectionOptions');
        const emptyState = document.getElementById('leadTaskSelectionEmpty');
        const submitButton = document.getElementById('leadTaskSelectionSubmitBtn');

        if (!container || !emptyState || !submitButton) {
            return;
        }

        if (!Array.isArray(tasks) || tasks.length === 0) {
            container.innerHTML = '';
            emptyState.classList.remove('hidden');
            submitButton.disabled = true;
            return;
        }

        emptyState.classList.add('hidden');
        submitButton.disabled = false;

        container.innerHTML = tasks.map((task, index) => {
            const scheduledAt = task.scheduled_at
                ? new Date(task.scheduled_at).toLocaleString('en-IN', { dateStyle: 'medium', timeStyle: 'short' })
                : 'No schedule';

            return `
                <label class="lead-task-option-card">
                    <input
                        type="radio"
                        name="task_id"
                        value="${task.id}"
                        ${index === 0 ? 'checked' : ''}
                        data-task-id="${task.id}"
                        data-task-lead-id="${task.lead_id || leadId}"
                        data-task-model-type="${escapeHtml(task.model_type || 'task')}"
                        data-task-category="${escapeHtml(task.category || 'other')}"
                        data-task-title="${escapeHtml(task.title || '')}"
                        data-task-notes="${escapeHtml(task.notes || '')}"
                        data-task-description="${escapeHtml(task.description || '')}"
                        data-task-scheduled-at="${escapeHtml(task.scheduled_at || '')}"
                        data-task-meeting-id="${escapeHtml(task.meeting_id || '')}"
                        data-task-site-visit-id="${escapeHtml(task.site_visit_id || '')}"
                        data-task-follow-up-id="${escapeHtml(task.follow_up_id || '')}"
                    >
                    <div class="lead-task-option-copy">
                        <strong>${escapeHtml(task.title || 'Open Task')}</strong>
                        <small>${escapeHtml(scheduledAt)}</small>
                    </div>
                </label>
            `;
        }).join('');
    }

    function buildLeadTaskDetailUrl(leadId, task) {
        const url = new URL(`/leads/${leadId}`, window.location.origin);
        url.searchParams.set('open_task', task.id);
        url.searchParams.set('task_model', task.model_type || 'task');
        url.searchParams.set('task_category', task.category || 'other');

        if (task.title) url.searchParams.set('task_title', task.title);
        if (task.notes) url.searchParams.set('task_notes', task.notes);
        if (task.description) url.searchParams.set('task_description', task.description);
        if (task.scheduled_at) url.searchParams.set('task_scheduled_at', task.scheduled_at);
        if (task.meeting_id) url.searchParams.set('meeting_id', task.meeting_id);
        if (task.site_visit_id) url.searchParams.set('site_visit_id', task.site_visit_id);
        if (task.follow_up_id) url.searchParams.set('follow_up_id', task.follow_up_id);
        url.searchParams.set('back', '/sales-manager/leads');
        url.searchParams.set('embed_task_flow', '1');

        return url.toString();
    }

    function openLeadTaskDetailModal(url) {
        const modal = document.getElementById('leadTaskDetailModal');
        const frame = document.getElementById('leadTaskDetailFrame');
        const viewport = document.getElementById('leadTaskDetailViewport');

        if (!modal || !frame) {
            window.location.href = url;
            return;
        }

        if (viewport) {
            viewport.style.width = 'min(calc(100vw - 24px), 460px)';
            viewport.style.height = '560px';
        }
        frame.src = url;
        modal.classList.remove('hidden');
    }

    function closeLeadTaskDetailModal() {
        const modal = document.getElementById('leadTaskDetailModal');
        const frame = document.getElementById('leadTaskDetailFrame');
        const viewport = document.getElementById('leadTaskDetailViewport');
        const wasOpen = modal && !modal.classList.contains('hidden');

        if (frame) {
            frame.src = 'about:blank';
        }

        if (viewport) {
            viewport.style.width = '';
            viewport.style.height = '';
        }

        modal?.classList.add('hidden');
        activeLeadTaskLeadId = null;
        if (wasOpen) {
            loadLeads(currentLeadPage);
        }
    }

    function openLeadTaskFlow(task, leadId) {
        const numericLeadId = Number(leadId || task?.lead_id);
        if (!numericLeadId || !task?.id) {
            showLeadTaskMessage('No open task found for this lead.', 'warning');
            return;
        }

        activeLeadTaskLeadId = numericLeadId;
        closeCompleteAsmTaskModal();
        openLeadTaskDetailModal(buildLeadTaskDetailUrl(numericLeadId, task));
    }

    async function openLeadTask(leadId) {
        const numericLeadId = Number(leadId);
        if (!numericLeadId) return;

        try {
            const response = await fetch(`${SALES_MANAGER_API_URL}/leads/${numericLeadId}/open-task`, {
                headers: getAuthHeaders(),
                credentials: 'same-origin',
            });

            const result = await response.json();
            if (!response.ok || result.success === false) {
                throw new Error(extractLeadTaskErrorMessage(result));
            }

            const tasks = Array.isArray(result.tasks)
                ? result.tasks.filter((task) => task && task.id)
                : (result.data && result.data.id ? [result.data] : []);

            if (!tasks.length) {
                showLeadTaskMessage('No open task found for this lead.', 'warning');
                return;
            }

            if (!MANAGER_LEAD_TASK_PARITY_ENABLED) {
                window.location.href = `/sales-manager/tasks?task=${tasks[0].id}`;
                return;
            }

            const lead = allLeads.find((item) => Number(item.id) === numericLeadId);
            document.getElementById('leadTaskSelectionSubtitle').textContent = lead?.name
                ? `Select which open task should be handled for ${lead.name}.`
                : 'Select which open task should be handled from this lead.';
            buildLeadTaskSelectionOptions(tasks, numericLeadId);
            activeLeadTaskLeadId = numericLeadId;
            openCompleteAsmTaskModal();
        } catch (error) {
            console.error('Error opening lead task:', error);
            showLeadTaskMessage(error.message || 'Unable to open task right now. Please try again.', 'error');
        }
    }

    function openLeadTaskMenu(leadId) {
        activeLeadTaskId = Number(leadId);
        document.getElementById('leadTaskMenuModal')?.classList.remove('hidden');
    }

    function closeLeadTaskMenu() {
        activeLeadTaskId = null;
        document.getElementById('leadTaskMenuModal')?.classList.add('hidden');
    }

    function openLeadTaskMeeting() {
        if (!activeLeadTaskId) return;
        const leadId = activeLeadTaskId;
        closeLeadTaskMenu();
        openScheduleMeetingModal(leadId);
    }

    function openLeadTaskSiteVisit() {
        if (!activeLeadTaskId) return;
        const leadId = activeLeadTaskId;
        closeLeadTaskMenu();
        openScheduleSiteVisitModal(leadId);
    }

    function openLeadTaskEdit() {
        if (!activeLeadTaskId) return;
        const leadId = activeLeadTaskId;
        closeLeadTaskMenu();
        openEditLeadModal(leadId);
    }

    function openCompleteAsmTaskModal() {
        document.getElementById('completeAsmTaskModal')?.classList.remove('hidden');
    }

    function closeCompleteAsmTaskModal() {
        document.getElementById('completeAsmTaskModal')?.classList.add('hidden');
    }

    async function submitCompleteAsmTask(event) {
        event.preventDefault();

        const selectedTask = event.target.querySelector('input[name="task_id"]:checked');
        if (!selectedTask) {
            showLeadTaskMessage('Please select a task to continue.', 'warning');
            return;
        }

        openLeadTaskFlow({
            id: selectedTask.dataset.taskId || selectedTask.value,
            lead_id: selectedTask.dataset.taskLeadId || activeLeadTaskLeadId,
            model_type: selectedTask.dataset.taskModelType || 'task',
            category: selectedTask.dataset.taskCategory || 'other',
            title: selectedTask.dataset.taskTitle || '',
            notes: selectedTask.dataset.taskNotes || '',
            description: selectedTask.dataset.taskDescription || '',
            scheduled_at: selectedTask.dataset.taskScheduledAt || '',
            meeting_id: selectedTask.dataset.taskMeetingId || '',
            site_visit_id: selectedTask.dataset.taskSiteVisitId || '',
            follow_up_id: selectedTask.dataset.taskFollowUpId || '',
        }, selectedTask.dataset.taskLeadId || activeLeadTaskLeadId);
    }

    function createFavoriteLeadButton(leadId, isFavorite, extraClass = '') {
        const activeClass = isFavorite ? 'active' : '';
        const iconClass = isFavorite ? 'fas fa-star' : 'far fa-star';
        const title = isFavorite ? 'Remove from Favorites' : 'Add to Favorites';
        const className = ['favorite-lead-btn', activeClass, extraClass].filter(Boolean).join(' ');

        return `
            <button type="button"
                class="${className}"
                title="${title}"
                data-favorite-lead-id="${leadId}"
                data-favorite-state="${isFavorite ? '1' : '0'}"
                onclick="toggleLeadFavorite(${leadId})">
                <i class="${iconClass}"></i>
            </button>
        `;
    }

    function formatPhoneForTel(phone) {
        let digits = String(phone || '').replace(/\D/g, '');

        if (digits.startsWith('00')) {
            digits = digits.slice(2);
        }

        if (digits.length === 11 && digits.startsWith('0')) {
            digits = digits.slice(1);
        }

        if (digits.length === 10) {
            digits = `91${digits}`;
        }

        return digits ? `+${digits}` : '';
    }

    function getLeadTelHref(phone) {
        const formattedPhone = formatPhoneForTel(phone);
        return formattedPhone ? `tel:${formattedPhone}` : 'javascript:void(0)';
    }

    function updateFavoriteButtonsState(leadId, isFavorite) {
        const buttons = document.querySelectorAll(`[data-favorite-lead-id="${leadId}"]`);
        buttons.forEach((button) => {
            button.dataset.favoriteState = isFavorite ? '1' : '0';
            button.classList.toggle('active', isFavorite);
            button.title = isFavorite ? 'Remove from Favorites' : 'Add to Favorites';
            const icon = button.querySelector('i');
            if (icon) {
                icon.className = isFavorite ? 'fas fa-star' : 'far fa-star';
            }
        });
    }

    async function refreshFavoriteLeadsFromApi() {
        try {
            const response = await fetch(`${SALES_MANAGER_API_URL}/favorite-leads`, {
                headers: getAuthHeaders(),
                credentials: 'same-origin',
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();
            const favorites = Array.isArray(data?.data) ? data.data : [];
            favoriteLeadIds = new Set(favorites.map(item => Number(item.lead_id)).filter(Number.isFinite));
        } catch (error) {
            console.error('Error refreshing favorite leads:', error);
        }
    }

    async function toggleLeadFavorite(leadId) {
        const numericLeadId = Number(leadId);
        const isFavorite = favoriteLeadIds.has(numericLeadId);
        const method = isFavorite ? 'DELETE' : 'POST';

        try {
            const response = await fetch(`${SALES_MANAGER_API_URL}/leads/${numericLeadId}/favorite`, {
                method,
                headers: getAuthHeaders(),
                credentials: 'same-origin',
            });

            const data = await response.json();
            if (!response.ok || data.success === false) {
                throw new Error(data.message || 'Failed to update favorite lead');
            }

            if (isFavorite) {
                favoriteLeadIds.delete(numericLeadId);
            } else {
                favoriteLeadIds.add(numericLeadId);
            }

            allLeads = allLeads.map((lead) => {
                if (Number(lead.id) === numericLeadId) {
                    return { ...lead, is_favorite: !isFavorite };
                }
                return lead;
            });

            updateFavoriteButtonsState(numericLeadId, !isFavorite);
        } catch (error) {
            console.error('Error toggling favorite lead:', error);
            alert('Failed to update favorite lead. Please try again.');
        }
    }

    function createLeadListRow(lead) {
        const leadId = Number(lead.id);
        const firstAssignment = Array.isArray(lead.active_assignments) && lead.active_assignments.length > 0
            ? lead.active_assignments[0]
            : null;
        const assignedTo = firstAssignment?.assigned_to?.name || 'Unassigned';
        const createdAt = new Date(lead.created_at).toLocaleDateString('en-IN', {
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        });
        const remark = getLeadRemark(lead);
        const sourceLabel = formatLeadSource(lead.source);
        const displayStatus = lead.display_status || lead.status;
        const locationLabel = lead.preferred_location || lead.city || 'No location';
        const nextActionSummary = getLeadNextActionSummary(lead);
        const desktopNextActionCell = IS_REALTYX_LEAD_SHEET_USER ? createLeadNextActionCell(lead) : '';
        const desktopStatusSubline = IS_REALTYX_LEAD_SHEET_USER ? '' : `<div class="lead-list-sub">${escapeHtml(nextActionSummary)}</div>`;
        const importChannelBadge = getImportChannelBadge(lead.import_channel_tag);
        const telHref = getLeadTelHref(lead.phone);

        return `
            <tr class="lead-list-row" data-lead-url="/leads/${lead.id}">
                <td>
                    <div class="lead-list-name">${escapeHtml(lead.name || 'N/A')}</div>
                    <div class="lead-list-sub"><i class="fas fa-phone mr-2 text-gray-400"></i>${escapeHtml(lead.phone || 'N/A')}</div>
                    ${lead.email ? `<div class="lead-list-sub"><i class="fas fa-envelope mr-2 text-gray-400"></i>${escapeHtml(lead.email)}</div>` : ''}
                    <div class="lead-list-sub"><i class="fas fa-layer-group mr-2 text-gray-400"></i>${escapeHtml(locationLabel)} | ${escapeHtml(sourceLabel)} ${importChannelBadge}</div>
                    <div class="lead-mobile-shell">
                        <div class="lead-mobile-top">
                            <div class="min-w-0 flex-1">
                                <div class="lead-mobile-name">${escapeHtml(lead.name || 'N/A')}</div>
                                <div class="asm-lead-meta-line mt-1">${escapeHtml(locationLabel)} | <strong>${escapeHtml(sourceLabel)}</strong> ${importChannelBadge}</div>
                            </div>
                            <div class="lead-mobile-status-wrap">
                                <div class="lead-mobile-status">${getStatusBadge(displayStatus)}</div>
                            </div>
                        </div>
                        <div class="lead-mobile-meta">
                            <div class="lead-mobile-meta-line">
                                <i class="fas fa-phone"></i>
                                <span class="lead-mobile-meta-text">${escapeHtml(lead.phone || 'N/A')}</span>
                            </div>
                            <div class="lead-mobile-meta-line">
                                <i class="fas fa-calendar"></i>
                                <span class="lead-mobile-meta-text">${escapeHtml(createdAt)}</span>
                            </div>
                        </div>
                        <div class="lead-mobile-remark">
                            <div class="lead-mobile-remark-text" title="${escapeHtml(remark)}">${escapeHtml(remark)}</div>
                            <div class="lead-mobile-remark-text mt-1 text-gray-500">${escapeHtml(nextActionSummary)}</div>
                        </div>
                        <div class="lead-mobile-actions">
                            <button type="button" data-lead-call-trigger data-lead-id="${leadId}" data-lead-phone="${escapeHtml(lead.phone || '')}" class="lead-mobile-action-btn call js-no-row-nav"><i class="fas fa-phone"></i><span>Call</span></button>
                            <a href="/leads/${lead.id}" class="lead-mobile-action-btn open js-no-row-nav"><i class="fas fa-up-right-from-square"></i><span>Open Lead</span></a>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="mb-2">${getStatusBadge(displayStatus)}</div>
                    ${desktopStatusSubline}
                </td>
                ${desktopNextActionCell}
                <td>
                    <div class="lead-remark-text" title="${escapeHtml(remark)}">${escapeHtml(remark)}</div>
                </td>
                <td>
                    <div class="text-sm font-semibold text-gray-800">${escapeHtml(assignedTo)}</div>
                    <div class="lead-list-sub">${escapeHtml(lead.preferred_location || 'No location')}</div>
                </td>
                <td>
                    <div class="text-sm font-semibold text-gray-800">${escapeHtml(createdAt)}</div>
                    <div class="lead-list-sub">${escapeHtml(lead.budget || 'Budget not set')}</div>
                </td>
                <td>
                    <div class="lead-list-actions">
                        <button type="button" data-lead-call-trigger data-lead-id="${leadId}" data-lead-phone="${escapeHtml(lead.phone || '')}" class="js-no-row-nav flex items-center justify-center bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 transition-all duration-200 shadow-sm">
                            <i class="fas fa-phone mr-2"></i>Call
                        </button>
                        <a href="/leads/${lead.id}" class="js-no-row-nav flex items-center justify-center bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white hover:from-[#205A44] hover:to-[#15803d] transition-all duration-200 shadow-md">
                            <i class="fas fa-up-right-from-square mr-2"></i>Open Lead
                        </a>
                    </div>
                </td>
            </tr>
        `;
    }

    function compactLeadInitials(name) {
        const words = String(name || 'Lead').trim().split(/\s+/).filter(Boolean);
        return (words[0]?.[0] || 'L') + (words[1]?.[0] || '');
    }

    function compactLeadStage(status) {
        const normalized = String(status || 'new');
        if (['follow_up'].includes(normalized)) {
            return { label: 'Follow Up', className: 'follow' };
        }
        if (['cnp'].includes(normalized)) {
            return { label: 'CNP', className: 'cnp' };
        }
        if (['meeting_scheduled', 'meeting_completed'].includes(normalized)) {
            return { label: normalized === 'meeting_completed' ? 'Meeting Done' : 'Meeting', className: 'meeting' };
        }
        if (['visit_scheduled', 'visit_done', 'revisited_scheduled', 'revisited_completed'].includes(normalized)) {
            return { label: normalized.includes('done') || normalized.includes('completed') ? 'Visit Done' : 'Visit Schedule', className: '' };
        }
        if (['verified_prospect', 'connected', 'interested'].includes(normalized)) {
            return { label: 'Connected', className: 'follow' };
        }
        if (['dead', 'junk', 'not_interested'].includes(normalized)) {
            return { label: normalized.replace(/_/g, ' ').replace(/\b\w/g, letter => letter.toUpperCase()), className: 'cnp' };
        }
        return { label: normalized.replace(/_/g, ' ').replace(/\b\w/g, letter => letter.toUpperCase()) || 'New', className: '' };
    }

    function compactLeadQuality(status) {
        const normalized = String(status || 'new');
        if (['meeting_scheduled', 'meeting_completed', 'visit_scheduled', 'visit_done', 'revisited_scheduled', 'revisited_completed', 'closed'].includes(normalized)) {
            return { label: 'Hot', className: 'hot' };
        }
        if (['follow_up', 'verified_prospect', 'connected', 'interested'].includes(normalized)) {
            return { label: 'Warm', className: 'warm' };
        }
        if (['cnp', 'dead', 'junk', 'not_interested'].includes(normalized)) {
            return { label: 'Cold', className: 'cold' };
        }
        return { label: 'New', className: 'new' };
    }

    function compactLeadCreatedDate(lead) {
        if (!lead.created_at) {
            return 'Created -';
        }

        return `Created ${new Date(lead.created_at).toLocaleDateString('en-IN', {
            day: '2-digit',
            month: 'short'
        })}`;
    }

    function compactLeadNextActivity(lead) {
        if (lead.next_action_summary) {
            return String(lead.next_action_summary);
        }
        if (lead.next_action_label) {
            return String(lead.next_action_label);
        }
        if (lead.next_followup_at) {
            return `Follow Up ${new Date(lead.next_followup_at).toLocaleDateString('en-IN', { day: '2-digit', month: 'short' })}`;
        }

        return 'No next action';
    }

    function compactLeadLastActivity(lead) {
        const status = compactLeadStage(lead.display_status || lead.status).label;
        const updatedAt = lead.updated_at
            ? new Date(lead.updated_at).toLocaleDateString('en-IN', { day: '2-digit', month: 'short' })
            : '';

        return updatedAt ? `${status} - ${updatedAt}` : status;
    }

    function compactLeadWhatsappHref(phone) {
        const digits = formatPhoneForTel(phone).replace(/\D/g, '');
        return digits ? `https://wa.me/${digits}` : 'javascript:void(0)';
    }

    function createCompactAsmLeadCard(lead) {
        const card = document.createElement('div');
        const displayStatus = lead.display_status || lead.status || 'new';
        const stage = compactLeadStage(displayStatus);
        const quality = compactLeadQuality(displayStatus);
        const sourceLabel = formatLeadSource(lead.source);
        const remark = getLeadRemark(lead);
        const compactRemark = compactLeadRemarkText(remark);
        const telHref = getLeadTelHref(lead.phone);
        const whatsappHref = compactLeadWhatsappHref(lead.phone);
        const firstAssignment = Array.isArray(lead.active_assignments) && lead.active_assignments.length > 0
            ? lead.active_assignments[0]
            : null;
        const assignedTo = firstAssignment?.assigned_to?.name || 'Unassigned';
        const ownerMeta = IS_SENIOR_MANAGER
            ? `<div class="asm-card-meta-item owner"><i class="fas fa-user-check"></i><span>Assigned: ${escapeHtml(assignedTo)}</span></div>`
            : '';

        card.className = ['asm-lead-card', stage.className, quality.className].filter(Boolean).join(' ');
        card.innerHTML = `
            <div class="asm-card-content">
                <div class="asm-card-head">
                    <div class="asm-card-identity">
                        <div class="asm-card-avatar">${escapeHtml(compactLeadInitials(lead.name).toUpperCase())}</div>
                        <div class="min-w-0">
                            <div class="asm-card-name">${escapeHtml(lead.name || 'N/A')}</div>
                            <div class="asm-card-source"><i class="fas fa-layer-group"></i>${escapeHtml(sourceLabel || 'Lead')}</div>
                        </div>
                    </div>
                    <div class="asm-stage-wrap">
                        <span class="asm-stage ${escapeHtml(stage.className)}">${escapeHtml(stage.label)}</span>
                        <span class="asm-quality">${escapeHtml(quality.label)}</span>
                    </div>
                </div>

                <div class="asm-card-meta">
                    <div class="asm-card-meta-item"><i class="fas fa-phone"></i><span>${escapeHtml(lead.phone || 'N/A')}</span></div>
                    <div class="asm-card-meta-item"><i class="fas fa-calendar"></i><span>${escapeHtml(compactLeadCreatedDate(lead))}</span></div>
                    ${ownerMeta}
                </div>

                <div class="asm-card-note">
                    <i class="fas fa-note-sticky"></i>
                    <div title="${escapeHtml(remark)}">${escapeHtml(compactRemark)}</div>
                </div>

                <div class="asm-card-activity">
                    <div class="asm-activity-pill"><span>Last Activity</span><strong>${escapeHtml(compactLeadLastActivity(lead))}</strong></div>
                    <div class="asm-activity-pill"><span>Next Activity</span><strong>${escapeHtml(compactLeadNextActivity(lead))}</strong></div>
                </div>
            </div>

            <div class="asm-card-actions">
                <button type="button" data-lead-call-trigger data-lead-id="${Number(lead.id)}" data-lead-phone="${escapeHtml(lead.phone || '')}" class="asm-card-action primary js-no-row-nav"><i class="fas fa-phone"></i>Call</button>
                <a href="${escapeHtml(whatsappHref)}" target="_blank" rel="noopener" class="asm-card-action whatsapp js-no-row-nav"><i class="fab fa-whatsapp"></i>WhatsApp</a>
                <a href="/leads/${Number(lead.id)}" class="asm-card-action js-no-row-nav"><i class="fas fa-up-right-from-square"></i>Detail</a>
            </div>
        `;

        return card;
    }

    let teamMembersLoadPromise = null;
    let leadsLoadPromise = null;
    let lastLeadsLoadKey = null;
    let lastLeadsLoadAt = 0;
    let leadsPageBooted = false;

    // Load team members for filter
    async function loadTeamMembers() {
        if (teamMembersLoadPromise) {
            return teamMembersLoadPromise;
        }

        teamMembersLoadPromise = (async () => {
        try {
            console.log('Loading team members...');
            const response = await fetch(`${API_BASE_URL}/sales-manager/profile`, {
                headers: getAuthHeaders(),
                credentials: 'same-origin',
            });
            
            if (response.ok) {
                const data = await response.json();
                console.log('Team members data:', data);
                currentUser = data.user;
                teamMembers = data.team_members || [];
                
                // Populate user filter dropdown
                const userFilter = document.getElementById('userFilter');
                if (userFilter) {
                    // Clear existing options
                    userFilter.innerHTML = '<option value="">All Users</option>';
                    
                    // Add current user (manager)
                    if (currentUser && currentUser.id) {
                        const option = document.createElement('option');
                        option.value = currentUser.id;
                        option.textContent = `${currentUser.name} (Me)`;
                        userFilter.appendChild(option);
                        console.log('Added current user:', currentUser.name);
                    }
                    
                    // Add team members
                    if (teamMembers && teamMembers.length > 0) {
                        teamMembers.forEach(member => {
                            if (member && member.id && member.name) {
                                const option = document.createElement('option');
                                option.value = member.id;
                                option.textContent = member.name;
                                userFilter.appendChild(option);
                                console.log('Added team member:', member.name);
                            }
                        });
                    } else {
                        console.warn('No team members found in response');
                    }
                }

                await refreshFavoriteLeadsFromApi();
            } else {
                console.error('Failed to load team members. Status:', response.status);
                const errorText = await response.text();
                console.error('Error response:', errorText);
            }
        } catch (error) {
            console.error('Error loading team members:', error);
        } finally {
            teamMembersLoadPromise = null;
        }
        })();

        return teamMembersLoadPromise;
    }

    // Load leads
    async function loadLeads(page = 1) {
        currentLeadPage = page;
        const loadingState = document.getElementById('loadingState');
        const emptyState = document.getElementById('emptyState');
        const leadsCards = document.getElementById('leadsCards');
        const leadsGrid = document.getElementById('leadsGrid');
        const leadsList = document.getElementById('leadsList');
        const leadsListBody = document.getElementById('leadsListBody');
        
        loadingState.style.display = 'block';
        emptyState.style.display = 'none';
        leadsCards.style.display = 'none';
        leadsList.style.display = 'none';

        try {
            const status = document.getElementById('statusFilter').value;
            const search = document.getElementById('searchInput').value;
            const assignedTo = document.getElementById('userFilter')?.value || '';
            const datePreset = document.getElementById('datePresetFilter')?.value || '';
            const fromDate = document.getElementById('fromDateFilter')?.value || '';
            const toDate = document.getElementById('toDateFilter')?.value || '';
            const perPage = getLeadPerPage();
            
            const params = new URLSearchParams({
                page: page,
                per_page: perPage,
            });
            
            if (status) {
                params.append('status', status);
            }
            
            if (search) {
                params.append('search', search);
            }
            
            if (assignedTo) {
                params.append('assigned_to', assignedTo);
            }

            if (datePreset) {
                params.append('date_preset', datePreset);
            }

            if (fromDate) {
                params.append('from_date', fromDate);
            }

            if (toDate) {
                params.append('to_date', toDate);
            }

            persistLeadFilters({
                search,
                status,
                assigned_to: assignedTo,
                date_preset: datePreset,
                from_date: fromDate,
                to_date: toDate,
                per_page: perPage,
            });

            if (leadPageFlags.freshToday) {
                params.append('fresh_today', '1');
            }

            const requestKey = params.toString();
            const nowMs = Date.now();
            if (leadsLoadPromise && requestKey === lastLeadsLoadKey) {
                return leadsLoadPromise;
            }
            if (!leadsLoadPromise && requestKey === lastLeadsLoadKey && (nowMs - lastLeadsLoadAt) < 1200) {
                return;
            }
            lastLeadsLoadKey = requestKey;
            lastLeadsLoadAt = nowMs;

            leadsLoadPromise = fetch(`${API_BASE_URL}/leads?${params}`, {
                headers: getAuthHeaders(),
                credentials: 'same-origin',
            });
            const response = await leadsLoadPromise;
            leadsLoadPromise = null;

            if (!response.ok) {
                throw new Error('Failed to load leads');
            }

            const data = await response.json();
            updateAsmLeadPipelineCounts(data.status_counts || {});
            updateAsmLeadPipelineActiveState(status);

            if (data.data && data.data.length > 0) {
                allLeads = data.data.map((lead) => ({
                    ...lead,
                    is_favorite: favoriteLeadIds.has(Number(lead.id)),
                }));
                leadsGrid.innerHTML = '';
                leadsListBody.innerHTML = '';
                allLeads.forEach(lead => {
                    const card = createLeadCard(lead);
                    leadsGrid.appendChild(card);
                    leadsListBody.insertAdjacentHTML('beforeend', createLeadListRow(lead));
                });
                
                renderPagination(data);
                emptyState.style.display = 'none';
                setLeadView(currentLeadView, false);
            } else {
                allLeads = [];
                leadsCards.style.display = 'none';
                leadsList.style.display = 'none';
                emptyState.style.display = 'block';
            }
        } catch (error) {
            leadsLoadPromise = null;
            console.error('Error loading leads:', error);
            alert('Failed to load leads. Please try again.');
        } finally {
            leadsLoadPromise = null;
            loadingState.style.display = 'none';
        }
    }

    // Create lead card
    function createLeadCard(lead) {
        if (isAsmCompactLeadMode()) {
            return createCompactAsmLeadCard(lead);
        }

        const card = document.createElement('div');
        card.className = 'asm-lead-card';
        
        const leadId = Number(lead.id);
        const displayStatus = lead.display_status || lead.status;
        const statusBadge = getStatusBadge(displayStatus);
        const assignedTo = lead.active_assignments && lead.active_assignments.length > 0 
            ? lead.active_assignments[0].assigned_to.name 
            : 'Unassigned';
        const createdAt = new Date(lead.created_at).toLocaleDateString('en-IN', {
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        });
        const sourceLabel = formatLeadSource(lead.source);
        const locationLabel = lead.preferred_location || lead.city || 'No location';
        const budgetLabel = lead.budget
            ? (typeof lead.budget === 'string' ? lead.budget : ('Rs ' + parseFloat(lead.budget).toLocaleString('en-IN')))
            : 'Budget not set';
        const remark = getLeadRemark(lead);
        const nextActionSummary = getLeadNextActionSummary(lead);
        const importChannelBadge = getImportChannelBadge(lead.import_channel_tag);
        const telHref = getLeadTelHref(lead.phone);

        card.innerHTML = `
            <div class="asm-lead-card-head">
                <div class="flex-1 min-w-0">
                    <h3 class="asm-lead-title">${lead.name || 'N/A'}</h3>
                    <div class="asm-lead-meta-line">${locationLabel} | <strong>${sourceLabel}</strong> ${importChannelBadge}</div>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    ${statusBadge}
                </div>
            </div>
            
            <div class="asm-lead-detail-grid">
                <div class="asm-lead-detail-row">
                    <i class="fas fa-phone"></i>
                    <span>${lead.phone || 'N/A'}</span>
                </div>
                <div class="asm-lead-detail-row">
                    <i class="fas fa-wallet"></i>
                    <span>${budgetLabel}</span>
                </div>
                <div class="asm-lead-detail-row">
                    <i class="fas fa-calendar"></i>
                    <span>${createdAt}</span>
                </div>
                <div class="asm-lead-detail-row">
                    <i class="fas fa-clock"></i>
                    <span>${nextActionSummary}</span>
                </div>
                <div class="asm-lead-detail-row">
                    <i class="fas fa-note-sticky"></i>
                    <span>${remark}</span>
                </div>
            </div>

            <div class="asm-lead-actions">
                <button type="button" data-lead-call-trigger data-lead-id="${leadId}" data-lead-phone="${escapeHtml(lead.phone || '')}" class="asm-lead-action-btn call">
                    <i class="fas fa-phone"></i>
                    <span>Call</span>
                </button>
                <a href="/leads/${lead.id}" class="asm-lead-action-btn open">
                    <i class="fas fa-up-right-from-square"></i>
                    <span>Open Lead</span>
                </a>
            </div>
        </div>
        
        <!-- Expandable Details Section -->
        <div 
            id="details-${lead.id}" 
            class="hidden border-t border-gray-200 bg-gray-50 p-5"
            style="transition: all 0.3s ease;"
        >
            <div class="space-y-3 mb-4">
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <span class="text-gray-500">Name:</span>
                        <span class="font-medium text-gray-900 ml-2">${lead.name || 'N/A'}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Phone:</span>
                        <span class="font-medium text-gray-900 ml-2">${lead.phone || 'N/A'}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Email:</span>
                        <span class="font-medium text-gray-900 ml-2">${lead.email || 'N/A'}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Status:</span>
                        <span class="font-medium text-gray-900 ml-2">${displayStatus || 'N/A'}</span>
                    </div>
                    ${lead.address ? `
                    <div class="col-span-2">
                        <span class="text-gray-500">Address:</span>
                        <span class="font-medium text-gray-900 ml-2">${lead.address}</span>
                    </div>
                    ` : ''}
                    ${lead.city || lead.state || lead.pincode ? `
                    <div>
                        <span class="text-gray-500">City/State:</span>
                        <span class="font-medium text-gray-900 ml-2">${[lead.city, lead.state, lead.pincode].filter(Boolean).join(', ') || 'N/A'}</span>
                    </div>
                    ` : ''}
                    <div>
                        <span class="text-gray-500">Source:</span>
                        <span class="font-medium text-gray-900 ml-2">${escapeHtml(sourceLabel)}</span>
                        ${importChannelBadge}
                    </div>
                    ${lead.preferred_location ? `
                    <div>
                        <span class="text-gray-500">Preferred Location:</span>
                        <span class="font-medium text-gray-900 ml-2">${lead.preferred_location}</span>
                    </div>
                    ` : ''}
                    ${lead.preferred_size ? `
                    <div>
                        <span class="text-gray-500">Preferred Size:</span>
                        <span class="font-medium text-gray-900 ml-2">${lead.preferred_size}</span>
                    </div>
                    ` : ''}
                    ${lead.property_type ? `
                    <div>
                        <span class="text-gray-500">Property Type:</span>
                        <span class="font-medium text-gray-900 ml-2">${lead.property_type}</span>
                    </div>
                    ` : ''}
                    ${lead.use_end_use ? `
                    <div>
                        <span class="text-gray-500">Use:</span>
                        <span class="font-medium text-gray-900 ml-2">${lead.use_end_use}</span>
                    </div>
                    ` : ''}
                </div>
                ${lead.notes ? `
                <div class="mt-3 p-3 bg-white rounded border border-gray-200">
                    <span class="text-xs font-medium text-gray-500 uppercase">Notes:</span>
                    <p class="text-sm text-gray-700 mt-1">${lead.notes}</p>
                </div>
                ` : ''}
                ${lead.requirements ? `
                <div class="mt-3 p-3 bg-white rounded border border-gray-200">
                    <span class="text-xs font-medium text-gray-500 uppercase">Requirements:</span>
                    <p class="text-sm text-gray-700 mt-1">${lead.requirements}</p>
                </div>
                ` : ''}
            </div>
            
            <div class="flex gap-2 mt-4">
                <button 
                    onclick="openEditLeadModal(${lead.id})" 
                    class="flex-1 px-4 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg hover:from-[#205A44] hover:to-[#15803d] transition-colors text-sm font-medium"
                >
                    <i class="fas fa-edit mr-2"></i>
                    Edit Lead
                </button>
                <button 
                    onclick="openScheduleMeetingModal(${lead.id})" 
                    class="flex-1 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors text-sm font-medium"
                >
                    <i class="fas fa-handshake mr-2"></i>
                    Schedule Meeting
                </button>
                <button 
                    onclick="openScheduleSiteVisitModal(${lead.id})" 
                    class="flex-1 px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors text-sm font-medium"
                >
                    <i class="fas fa-map-marker-alt mr-2"></i>
                    Schedule Site Visit
                </button>
            </div>
        </div>
        `;
        
        return card;
    }

    // Get status badge
    function getStatusBadge(status) {
        const badges = {
            'new': '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">New</span>',
            'fresh_transfer': '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-amber-100 text-amber-800">Fresh Transfer</span>',
            'contacted': '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Contacted</span>',
            'connected': '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">Connected</span>',
            'verified_prospect': '<span class="verified-badge inline-flex items-center px-1.5 py-0.5 text-[10px] font-medium rounded bg-gradient-to-r from-emerald-500 to-green-600 text-white shadow-sm border border-emerald-400/30"><i class="fas fa-check-circle mr-1 text-[9px]"></i>Verified</span>',
            'meeting_scheduled': '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-indigo-100 text-indigo-800">Meeting Scheduled</span>',
            'meeting_completed': '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-cyan-100 text-cyan-800">Meeting Completed</span>',
            'visit_scheduled': '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-violet-100 text-violet-800">Visit Scheduled</span>',
            'visit_done': '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-pink-100 text-pink-800">Visit Done</span>',
            'revisited_scheduled': '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-fuchsia-100 text-fuchsia-800">Revisit Scheduled</span>',
            'revisited_completed': '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-rose-100 text-rose-800">Revisit Completed</span>',
            'follow_up': '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-sky-100 text-sky-800">Follow Up</span>',
            'cnp': '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-amber-100 text-amber-800">CNP</span>',
            'closed': '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Closed</span>',
            'dead': '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Dead</span>',
            'on_hold': '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">On Hold</span>',
        };
        return badges[status] || '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">' + status + '</span>';
    }

    // Render pagination
    function renderPagination(data) {
        const paginations = [
            document.getElementById('pagination'),
            document.getElementById('paginationList'),
        ];
        const selectedPerPage = resolveLeadPerPage(data.per_page || getLeadPerPage());
        if (data.last_page <= 1) {
            paginations.forEach((pagination) => {
                if (pagination) {
                    pagination.innerHTML = `
                        <div class="w-full flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div class="text-sm text-gray-500">Showing ${data.from || 0} to ${data.to || 0} of ${data.total || 0} leads</div>
                            ${renderLeadPerPageControl(selectedPerPage)}
                        </div>
                    `;
                }
            });
            return;
        }

        let html = '<div class="w-full flex flex-col gap-3 md:flex-row md:items-center md:justify-between">';
        html += '<div class="flex items-center gap-2 flex-wrap">';
        
        // Previous button
        if (data.current_page > 1) {
            html += `<button onclick="loadLeads(${data.current_page - 1})" class="px-3 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">Previous</button>`;
        }
        
        // Page numbers
        for (let i = 1; i <= data.last_page; i++) {
            if (i === 1 || i === data.last_page || (i >= data.current_page - 2 && i <= data.current_page + 2)) {
                html += `<button onclick="loadLeads(${i})" class="px-3 py-2 border border-gray-300 rounded-lg ${i === data.current_page ? 'bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white' : 'hover:bg-gray-50'}">${i}</button>`;
            } else if (i === data.current_page - 3 || i === data.current_page + 3) {
                html += `<span class="px-3 py-2">...</span>`;
            }
        }
        
        // Next button
        if (data.current_page < data.last_page) {
            html += `<button onclick="loadLeads(${data.current_page + 1})" class="px-3 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">Next</button>`;
        }
        
        html += '</div>';
        html += `<div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-end md:gap-4">
            <div class="text-sm text-gray-500">Showing ${data.from} to ${data.to} of ${data.total} leads</div>
            ${renderLeadPerPageControl(selectedPerPage)}
        </div>`;
        html += '</div>';
        
        paginations.forEach((pagination) => {
            if (pagination) {
                pagination.innerHTML = html;
            }
        });
    }

    // Toggle lead details
    function toggleLeadDetails(leadId) {
        const detailsDiv = document.getElementById(`details-${leadId}`);
        const chevron = document.getElementById(`chevron-${leadId}`);
        const btn = document.getElementById(`viewDetailsBtn-${leadId}`);
        
        if (detailsDiv.classList.contains('hidden')) {
            detailsDiv.classList.remove('hidden');
            chevron.classList.remove('fa-chevron-down');
            chevron.classList.add('fa-chevron-up');
            btn.innerHTML = `<i class="fas fa-chevron-up mr-2" id="chevron-${leadId}"></i> Hide Details`;
        } else {
            detailsDiv.classList.add('hidden');
            chevron.classList.remove('fa-chevron-up');
            chevron.classList.add('fa-chevron-down');
            btn.innerHTML = `<i class="fas fa-chevron-down mr-2" id="chevron-${leadId}"></i> View Details`;
        }
    }


    // Open Edit Lead Modal
    async function openEditLeadModal(leadId) {
        currentLeadId = leadId;
        const modal = document.getElementById('editLeadModal');
        const content = document.getElementById('editLeadModalContent');
        
        // Find lead in current list or fetch from API
        let lead = allLeads.find(l => l.id === leadId);
        
        if (!lead) {
            try {
                const response = await fetch(`${API_BASE_URL}/leads/${leadId}`, {
                    headers: getAuthHeaders(),
                });
                const data = await response.json();
                lead = data;
            } catch (error) {
                console.error('Error loading lead:', error);
                alert('Failed to load lead details');
                return;
            }
        }

        content.innerHTML = `
            <form id="editLeadForm" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                        <input type="text" id="editName" value="${lead.name || ''}" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone *</label>
                        <input type="text" id="editPhone" value="${lead.phone || ''}" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" id="editEmail" value="${lead.email || ''}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select id="editStatus" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="new" ${lead.status === 'new' ? 'selected' : ''}>New</option>
                            <option value="contacted" ${lead.status === 'contacted' ? 'selected' : ''}>Contacted</option>
                            <option value="connected" ${lead.status === 'connected' ? 'selected' : ''}>Connected</option>
                            <option value="verified_prospect" ${lead.status === 'verified_prospect' ? 'selected' : ''}>Verified Prospect</option>
                            <option value="meeting_scheduled" ${lead.status === 'meeting_scheduled' ? 'selected' : ''}>Meeting Scheduled</option>
                            <option value="meeting_completed" ${lead.status === 'meeting_completed' ? 'selected' : ''}>Meeting Completed</option>
                            <option value="visit_scheduled" ${lead.status === 'visit_scheduled' ? 'selected' : ''}>Visit Scheduled</option>
                            <option value="visit_done" ${lead.status === 'visit_done' ? 'selected' : ''}>Visit Done</option>
                            <option value="revisited_scheduled" ${lead.status === 'revisited_scheduled' ? 'selected' : ''}>Revisit Scheduled</option>
                            <option value="revisited_completed" ${lead.status === 'revisited_completed' ? 'selected' : ''}>Revisit Completed</option>
                            <option value="closed" ${lead.status === 'closed' ? 'selected' : ''}>Closed</option>
                            <option value="dead" ${lead.status === 'dead' ? 'selected' : ''}>Dead</option>
                            <option value="on_hold" ${lead.status === 'on_hold' ? 'selected' : ''}>On Hold</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                        <input type="text" id="editCity" value="${lead.city || ''}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">State</label>
                        <input type="text" id="editState" value="${lead.state || ''}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Pincode</label>
                        <input type="text" id="editPincode" value="${lead.pincode || ''}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Preferred Location</label>
                        <input type="text" id="editPreferredLocation" value="${lead.preferred_location || ''}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Budget</label>
                        <input type="number" id="editBudget" value="${lead.budget || ''}" step="0.01"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Property Type</label>
                        <select id="editPropertyType" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select</option>
                            <option value="apartment" ${lead.property_type === 'apartment' ? 'selected' : ''}>Apartment</option>
                            <option value="villa" ${lead.property_type === 'villa' ? 'selected' : ''}>Villa</option>
                            <option value="plot" ${lead.property_type === 'plot' ? 'selected' : ''}>Plot</option>
                            <option value="commercial" ${lead.property_type === 'commercial' ? 'selected' : ''}>Commercial</option>
                            <option value="other" ${lead.property_type === 'other' ? 'selected' : ''}>Other</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                    <textarea id="editAddress" rows="2"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">${lead.address || ''}</textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Requirements</label>
                    <textarea id="editRequirements" rows="3"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">${lead.requirements || ''}</textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea id="editNotes" rows="3"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">${lead.notes || ''}</textarea>
                </div>
            </form>
        `;
        
        modal.classList.remove('hidden');
    }

    // Close Edit Lead Modal
    function closeEditLeadModal() {
        document.getElementById('editLeadModal').classList.add('hidden');
        currentLeadId = null;
    }

    // Submit Update Lead
    async function submitUpdateLead() {
        if (!currentLeadId) return;
        
        const form = document.getElementById('editLeadForm');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        
        const updateData = {
            name: document.getElementById('editName').value,
            phone: document.getElementById('editPhone').value,
            email: document.getElementById('editEmail').value || null,
            city: document.getElementById('editCity').value || null,
            state: document.getElementById('editState').value || null,
            pincode: document.getElementById('editPincode').value || null,
            address: document.getElementById('editAddress').value || null,
            preferred_location: document.getElementById('editPreferredLocation').value || null,
            budget: document.getElementById('editBudget').value || null,
            property_type: document.getElementById('editPropertyType').value || null,
            requirements: document.getElementById('editRequirements').value || null,
            notes: document.getElementById('editNotes').value || null,
            status: document.getElementById('editStatus').value,
        };
        
        try {
            const response = await fetch(`${API_BASE_URL}/leads/${currentLeadId}`, {
                method: 'PUT',
                headers: getAuthHeaders(),
                body: JSON.stringify(updateData),
            });

            const data = await response.json();
            
            if (response.ok) {
                if (typeof showNotification === 'function') {
                    showNotification('Lead updated successfully!', 'success', 3000);
                } else {
                    alert('Lead updated successfully!');
                }
                closeEditLeadModal();
                loadLeads();
            } else {
                const errorMsg = data.message || data.errors || 'Failed to update lead';
                alert(typeof errorMsg === 'string' ? errorMsg : JSON.stringify(errorMsg));
            }
        } catch (error) {
            console.error('Error updating lead:', error);
            alert('Failed to update lead. Please try again.');
        }
    }

    // Open Schedule Meeting Modal
    async function openScheduleMeetingModal(leadId) {
        currentLeadId = leadId;
        const modal = document.getElementById('scheduleMeetingModal');
        const content = document.getElementById('scheduleMeetingModalContent');
        
        // Find lead
        let lead = allLeads.find(l => l.id === leadId);
        if (!lead) {
            try {
                const response = await fetch(`${API_BASE_URL}/leads/${leadId}`, {
                    headers: getAuthHeaders(),
                });
                lead = await response.json();
            } catch (error) {
                console.error('Error loading lead:', error);
                alert('Failed to load lead details');
                return;
            }
        }

        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        const minDateTime = tomorrow.toISOString().slice(0, 16);

        content.innerHTML = `
            <form id="meetingForm" class="space-y-4">
                <input type="hidden" id="meetingLeadId" value="${leadId}">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Customer Name *</label>
                        <input type="text" id="meetingCustomerName" value="${lead.name || ''}" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone *</label>
                        <input type="text" id="meetingPhone" value="${lead.phone || ''}" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Employee</label>
                        <input type="text" id="meetingEmployee" value="${LOGGED_IN_USER_NAME}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Occupation</label>
                        <input type="text" id="meetingOccupation"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                    <input type="hidden" id="meetingDateOfVisit" value="">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Project</label>
                        <input type="text" id="meetingProject"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Budget Range *</label>
                        <select id="meetingBudgetRange" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                            <option value="">Select</option>
                            <option value="Under 50 Lac">Under 50 Lac</option>
                            <option value="50 Lac – 1 Cr">50 Lac – 1 Cr</option>
                            <option value="1 Cr – 2 Cr">1 Cr – 2 Cr</option>
                            <option value="2 Cr – 3 Cr">2 Cr – 3 Cr</option>
                            <option value="Above 3 Cr">Above 3 Cr</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Team Leader</label>
                        <input type="text" id="meetingTeamLeader" value="${MANAGER_NAME}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500" readonly>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Property Type *</label>
                        <select id="meetingPropertyType" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                            <option value="">Select</option>
                            <option value="Plot/Villa">Plot/Villa</option>
                            <option value="Flat">Flat</option>
                            <option value="Commercial">Commercial</option>
                            <option value="Just Exploring">Just Exploring</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Payment Mode *</label>
                        <select id="meetingPaymentMode" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                            <option value="">Select</option>
                            <option value="Self Fund">Self Fund</option>
                            <option value="Loan">Loan</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tentative Period *</label>
                        <select id="meetingTentativePeriod" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                            <option value="">Select</option>
                            <option value="Within 1 Month">Within 1 Month</option>
                            <option value="Within 3 Months">Within 3 Months</option>
                            <option value="Within 6 Months">Within 6 Months</option>
                            <option value="More than 6 Months">More than 6 Months</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Lead Type *</label>
                        <select id="meetingLeadType" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                            <option value="">Select</option>
                            <option value="New Visit">New Visit</option>
                            <option value="Revisited">Revisited</option>
                            <option value="Meeting" selected>Meeting</option>
                            <option value="Prospect">Prospect</option>
                        </select>
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Scheduled At *</label>
                        <input type="datetime-local" id="meetingScheduledAt" required min="${minDateTime}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Meeting Notes</label>
                        <textarea id="meetingNotes" rows="3"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"></textarea>
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Photos (Optional)</label>
                        <input type="file" id="meetingPhotos" multiple accept="image/*"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                        <p class="text-xs text-gray-500 mt-1">You can select multiple images (Max 5MB each)</p>
                    </div>
                </div>
            </form>
        `;
        
        modal.classList.remove('hidden');
        
        // Auto-fill date_of_visit from scheduled_at when scheduled_at changes
        const scheduledAtInput = document.getElementById('meetingScheduledAt');
        const dateOfVisitInput = document.getElementById('meetingDateOfVisit');
        
        if (scheduledAtInput && dateOfVisitInput) {
            scheduledAtInput.addEventListener('change', function() {
                const scheduledDate = new Date(this.value);
                if (scheduledDate && !isNaN(scheduledDate.getTime())) {
                    // Extract date part (YYYY-MM-DD)
                    const dateOnly = scheduledDate.toISOString().split('T')[0];
                    dateOfVisitInput.value = dateOnly;
                }
            });
        }
    }

    // Close Schedule Meeting Modal
    function closeScheduleMeetingModal() {
        document.getElementById('scheduleMeetingModal').classList.add('hidden');
        currentLeadId = null;
    }

    // Submit Create Meeting
    async function submitCreateMeeting() {
        if (!currentLeadId) return;
        
        const form = document.getElementById('meetingForm');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        
        // Ensure date_of_visit is filled from scheduled_at if empty
        const scheduledAt = document.getElementById('meetingScheduledAt').value;
        const dateOfVisitInput = document.getElementById('meetingDateOfVisit');
        if (!dateOfVisitInput.value && scheduledAt) {
            const scheduledDate = new Date(scheduledAt);
            if (scheduledDate && !isNaN(scheduledDate.getTime())) {
                dateOfVisitInput.value = scheduledDate.toISOString().split('T')[0];
            }
        }
        
        const formData = new FormData();
        formData.append('lead_id', currentLeadId);
        formData.append('customer_name', document.getElementById('meetingCustomerName').value);
        formData.append('phone', document.getElementById('meetingPhone').value);
        formData.append('employee', document.getElementById('meetingEmployee').value || '');
        formData.append('occupation', document.getElementById('meetingOccupation').value || '');
        formData.append('date_of_visit', dateOfVisitInput.value || '');
        formData.append('project', document.getElementById('meetingProject').value || '');
        formData.append('budget_range', document.getElementById('meetingBudgetRange').value);
        formData.append('team_leader', document.getElementById('meetingTeamLeader').value || '');
        formData.append('property_type', document.getElementById('meetingPropertyType').value);
        formData.append('payment_mode', document.getElementById('meetingPaymentMode').value);
        formData.append('tentative_period', document.getElementById('meetingTentativePeriod').value);
        formData.append('lead_type', document.getElementById('meetingLeadType').value);
        formData.append('scheduled_at', document.getElementById('meetingScheduledAt').value);
        formData.append('meeting_notes', document.getElementById('meetingNotes').value || '');
        
        const photos = document.getElementById('meetingPhotos').files;
        for (let i = 0; i < photos.length; i++) {
            formData.append('photos[]', photos[i]);
        }
        
        try {
            const response = await fetch(`${API_BASE_URL}/sales-manager/meetings`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${API_TOKEN}`,
                    'Accept': 'application/json',
                },
                body: formData,
            });

            const data = await response.json();
            
            if (response.ok && data.success !== false) {
                if (typeof showNotification === 'function') {
                    showNotification('Meeting scheduled successfully!', 'success', 3000);
                } else {
                    alert('Meeting scheduled successfully!');
                }
                closeScheduleMeetingModal();
                loadLeads();
            } else {
                const errorMsg = data.message || data.errors || 'Failed to schedule meeting';
                alert(typeof errorMsg === 'string' ? errorMsg : JSON.stringify(errorMsg));
            }
        } catch (error) {
            console.error('Error scheduling meeting:', error);
            alert('Failed to schedule meeting. Please try again.');
        }
    }

    // Open Schedule Site Visit Modal
    async function openScheduleSiteVisitModal(leadId) {
        currentLeadId = leadId;
        const modal = document.getElementById('scheduleSiteVisitModal');
        const content = document.getElementById('scheduleSiteVisitModalContent');
        
        // Load team members if not loaded
        if (teamMembers.length === 0) {
            await loadTeamMembers();
        }
        
        // Find lead
        let lead = allLeads.find(l => l.id === leadId);
        if (!lead) {
            try {
                const response = await fetch(`${API_BASE_URL}/leads/${leadId}`, {
                    headers: getAuthHeaders(),
                });
                lead = await response.json();
            } catch (error) {
                console.error('Error loading lead:', error);
                alert('Failed to load lead details');
                return;
            }
        }

        // Check if lead has existing site visits
        let hasExistingSiteVisits = false;
        try {
            const siteVisitResponse = await fetch(`${API_BASE_URL}/site-visits?lead_id=${leadId}`, {
                headers: getAuthHeaders(),
            });
            if (siteVisitResponse.ok) {
                const siteVisitData = await siteVisitResponse.json();
                hasExistingSiteVisits = siteVisitData.data && siteVisitData.data.length > 0;
            }
        } catch (error) {
            console.error('Error checking site visits:', error);
        }

        // Determine Lead Type: "New Visit" if no existing visits, "Revisited" if visits exist
        const leadTypeValue = hasExistingSiteVisits ? 'Revisited' : 'New Visit';

        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        const minDateTime = tomorrow.toISOString().slice(0, 16);

        const teamMembersOptions = teamMembers.map(member => 
            `<option value="${member.id}">${member.name}</option>`
        ).join('');

        content.innerHTML = `
            <form id="siteVisitForm" class="space-y-4">
                <input type="hidden" id="siteVisitLeadId" value="${leadId}">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Property Name</label>
                    <input type="text" id="siteVisitPropertyName"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Scheduled At *</label>
                    <input type="datetime-local" id="siteVisitScheduledAt" required min="${minDateTime}"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Employee</label>
                    <input type="text" id="siteVisitEmployee" value="${LOGGED_IN_USER_NAME}"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Lead Type *</label>
                    <select id="siteVisitLeadType" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                        <option value="">Select</option>
                        <option value="New Visit" ${leadTypeValue === 'New Visit' ? 'selected' : ''}>New Visit</option>
                        <option value="Revisited" ${leadTypeValue === 'Revisited' ? 'selected' : ''}>Revisited</option>
                        <option value="Meeting">Meeting</option>
                        <option value="Prospect">Prospect</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Assign To</label>
                    <select id="siteVisitAssignedTo"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                        <option value="">Select Team Member</option>
                        ${teamMembersOptions}
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Visit Notes</label>
                    <textarea id="siteVisitNotes" rows="3"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"></textarea>
                </div>
            </form>
        `;
        
        modal.classList.remove('hidden');
    }

    // Close Schedule Site Visit Modal
    function closeScheduleSiteVisitModal() {
        document.getElementById('scheduleSiteVisitModal').classList.add('hidden');
        currentLeadId = null;
    }

    // Submit Create Site Visit
    async function submitCreateSiteVisit() {
        if (!currentLeadId) return;
        
        const form = document.getElementById('siteVisitForm');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        
        const visitData = {
            lead_id: currentLeadId,
            property_name: document.getElementById('siteVisitPropertyName').value || null,
            scheduled_at: document.getElementById('siteVisitScheduledAt').value,
            assigned_to: document.getElementById('siteVisitAssignedTo').value || null,
            employee: document.getElementById('siteVisitEmployee').value || null,
            lead_type: document.getElementById('siteVisitLeadType').value || null,
            visit_notes: document.getElementById('siteVisitNotes').value || null,
        };
        
        try {
            const response = await fetch(`${API_BASE_URL}/site-visits`, {
                method: 'POST',
                headers: getAuthHeaders(),
                body: JSON.stringify(visitData),
            });

            const data = await response.json();
            
            if (response.ok && data.success !== false) {
                if (typeof showNotification === 'function') {
                    showNotification('Site visit scheduled successfully!', 'success', 3000);
                } else {
                    alert('Site visit scheduled successfully!');
                }
                closeScheduleSiteVisitModal();
                loadLeads();
            } else {
                const errorMsg = data.message || data.errors || 'Failed to schedule site visit';
                alert(typeof errorMsg === 'string' ? errorMsg : JSON.stringify(errorMsg));
            }
        } catch (error) {
            console.error('Error scheduling site visit:', error);
            alert('Failed to schedule site visit. Please try again.');
        }
    }

    // Handle search with debounce
    function handleSearch() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            loadLeads(1);
        }, 500);
    }

    function applyLeadFilters() {
        loadLeads(1);
    }

    window.applyLeadFilters = applyLeadFilters;

    function clearLeadDateFilters() {
        const datePresetFilter = document.getElementById('datePresetFilter');
        const fromDateFilter = document.getElementById('fromDateFilter');
        const toDateFilter = document.getElementById('toDateFilter');

        if (datePresetFilter) {
            datePresetFilter.value = '';
        }

        if (fromDateFilter) {
            fromDateFilter.value = '';
        }

        if (toDateFilter) {
            toDateFilter.value = '';
        }

        syncLeadDateFilterVisibility();
        loadLeads(1);
    }

    function clearLeadFilters() {
        const searchInput = document.getElementById('searchInput');
        const statusFilter = document.getElementById('statusFilter');
        const userFilter = document.getElementById('userFilter');
        const datePresetFilter = document.getElementById('datePresetFilter');
        const fromDateFilter = document.getElementById('fromDateFilter');
        const toDateFilter = document.getElementById('toDateFilter');

        if (searchInput) searchInput.value = '';
        if (statusFilter) statusFilter.value = '';
        if (userFilter) userFilter.value = '';
        if (datePresetFilter) datePresetFilter.value = '';
        if (fromDateFilter) fromDateFilter.value = '';
        if (toDateFilter) toDateFilter.value = '';

        try {
            localStorage.removeItem(leadFiltersStorageKey);
        } catch (e) {
            console.error('Failed to clear lead filters:', e);
        }

        syncLeadDateFilterVisibility();
        updateAsmLeadPipelineActiveState('');
        loadLeads(1);
    }

    window.clearLeadFilters = clearLeadFilters;

    // Open Add Lead Modal
    function openAddLeadModal() {
        const modal = document.getElementById('addLeadModal');
        const form = document.getElementById('addLeadForm');
        const errorDiv = document.getElementById('addLeadError');
        
        // Reset form
        form.reset();
        errorDiv.style.display = 'none';
        errorDiv.textContent = '';
        
        modal.classList.remove('hidden');
    }

    // Close Add Lead Modal
    function closeAddLeadModal() {
        const modal = document.getElementById('addLeadModal');
        const form = document.getElementById('addLeadForm');
        const errorDiv = document.getElementById('addLeadError');
        
        modal.classList.add('hidden');
        form.reset();
        errorDiv.style.display = 'none';
        errorDiv.textContent = '';
    }

    // Submit Add Lead
    async function submitAddLead() {
        const form = document.getElementById('addLeadForm');
        const errorDiv = document.getElementById('addLeadError');
        const nameInput = document.getElementById('addLeadName');
        const phoneInput = document.getElementById('addLeadPhone');
        
        // Reset error
        errorDiv.style.display = 'none';
        errorDiv.textContent = '';
        
        // Validate form
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        
        const leadData = {
            name: nameInput.value.trim(),
            phone: phoneInput.value.trim(),
        };
        
        try {
            const response = await fetch(`${API_BASE_URL}/leads`, {
                method: 'POST',
                headers: getAuthHeaders(),
                body: JSON.stringify(leadData),
            });
            
            const data = await response.json();
            
            if (response.ok) {
                if (typeof showNotification === 'function') {
                    showNotification('Lead added successfully!', 'success', 3000);
                } else {
                    alert('Lead added successfully!');
                }
                closeAddLeadModal();
                loadLeads(1); // Reload leads list
            } else {
                const errorMsg = data.message || (data.errors ? JSON.stringify(data.errors) : 'Failed to add lead');
                errorDiv.textContent = typeof errorMsg === 'string' ? errorMsg : JSON.stringify(errorMsg);
                errorDiv.style.display = 'block';
            }
        } catch (error) {
            console.error('Error adding lead:', error);
            errorDiv.textContent = 'Failed to add lead. Please try again.';
            errorDiv.style.display = 'block';
        }
    }

    // View short details modal
    async function viewShortDetails(leadId) {
        const modal = document.getElementById('shortDetailsModal');
        const content = document.getElementById('shortDetailsContent');
        
        // Show loading state
        content.innerHTML = '<div class="text-center py-8"><div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600 mx-auto"></div><p class="mt-4 text-gray-600">Loading lead details...</p></div>';
        modal.classList.remove('hidden');
        
        try {
            const response = await fetch(`${API_BASE_URL}/leads/${leadId}`, {
                headers: getAuthHeaders(),
            });
            
            if (!response.ok) {
                throw new Error('Failed to load lead details');
            }
            
            const data = await response.json();
            const lead = data.data || data;
            
            // Render lead details
                const statusLabels = {
                    'new': 'New',
                    'fresh_transfer': 'Fresh Transfer',
                    'contacted': 'Contacted',
                'connected': 'Connected',
                'verified_prospect': 'Verified Prospect',
                'meeting_scheduled': 'Meeting Scheduled',
                'meeting_completed': 'Meeting Completed',
                'visit_scheduled': 'Visit Scheduled',
                'visit_done': 'Visit Done',
                'revisited_scheduled': 'Revisit Scheduled',
                'revisited_completed': 'Revisit Completed',
                'follow_up': 'Follow Up',
                'cnp': 'CNP',
                'closed': 'Closed',
                'dead': 'Dead',
                'on_hold': 'On Hold',
            };
            
            const displayStatus = lead.display_status || lead.status;
            const statusLabel = statusLabels[displayStatus] || displayStatus;
            const createdDate = new Date(lead.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
            
            content.innerHTML = `
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-500">Name</label>
                            <p class="mt-1 text-sm text-gray-900">${lead.name || 'N/A'}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500">Phone</label>
                            <p class="mt-1 text-sm text-gray-900">${lead.phone || 'N/A'}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500">Email</label>
                            <p class="mt-1 text-sm text-gray-900">${lead.email || 'N/A'}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500">Status</label>
                            <p class="mt-1 text-sm text-gray-900">${statusLabel}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500">Location</label>
                            <p class="mt-1 text-sm text-gray-900">${lead.city || ''}${lead.city && lead.state ? ', ' : ''}${lead.state || ''}${lead.pincode ? ' - ' + lead.pincode : ''}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500">Budget</label>
                            <p class="mt-1 text-sm text-gray-900">${lead.budget || 'N/A'}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500">Preferred Location</label>
                            <p class="mt-1 text-sm text-gray-900">${lead.preferred_location || 'N/A'}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500">Created</label>
                            <p class="mt-1 text-sm text-gray-900">${createdDate}</p>
                        </div>
                    </div>
                    ${lead.requirements ? `
                        <div>
                            <label class="block text-sm font-medium text-gray-500">Requirements</label>
                            <p class="mt-1 text-sm text-gray-900">${lead.requirements}</p>
                        </div>
                    ` : ''}
                    ${lead.notes ? `
                        <div>
                            <label class="block text-sm font-medium text-gray-500">Notes</label>
                            <p class="mt-1 text-sm text-gray-900">${lead.notes}</p>
                        </div>
                    ` : ''}
                    ${lead.form_fields && Object.keys(lead.form_fields).length > 0 ? `
                        <div class="pt-4 border-t">
                            <h4 class="text-sm font-semibold text-gray-700 mb-3">Form Data</h4>
                            <div class="grid grid-cols-2 gap-4">
                                ${Object.entries(lead.form_fields).map(([key, value]) => `
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500">${key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}</label>
                                        <p class="mt-1 text-sm text-gray-900">${value || 'N/A'}</p>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    ` : ''}
                    <div class="pt-4 border-t">
                        <a href="/leads/${leadId}" class="block w-full text-center px-4 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg hover:from-[#205A44] hover:to-[#15803d] transition-colors duration-200 text-sm font-medium">
                            View Full Details
                        </a>
                    </div>
                </div>
            `;
        } catch (error) {
            content.innerHTML = `
                <div class="text-center py-8">
                    <p class="text-red-600">Failed to load lead details. Please try again.</p>
                    <button onclick="viewShortDetails(${leadId})" class="mt-4 px-4 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg hover:from-[#205A44] hover:to-[#15803d]">
                        Retry
                    </button>
                </div>
            `;
        }
    }

    function closeShortDetailsModal() {
        document.getElementById('shortDetailsModal').classList.add('hidden');
    }

    // Close modal when clicking outside
    document.getElementById('shortDetailsModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeShortDetailsModal();
        }
    });

    document.getElementById('leadTaskMenuModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeLeadTaskMenu();
        }
    });

    document.getElementById('completeAsmTaskModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeCompleteAsmTaskModal();
        }
    });

    document.getElementById('leadTaskDetailModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeLeadTaskDetailModal();
        }
    });
    
    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeShortDetailsModal();
            closeLeadTaskMenu();
            closeCompleteAsmTaskModal();
            closeLeadTaskDetailModal();
        }
    });

    window.addEventListener('message', function(event) {
        if (event.origin !== window.location.origin || !event.data || event.data.type !== 'lead-task-embed-size') {
            return;
        }

        const viewport = document.getElementById('leadTaskDetailViewport');
        if (!viewport) {
            return;
        }

        const requestedWidth = Number(event.data.width) || 460;
        const requestedHeight = Number(event.data.height) || 560;
        const maxWidth = Math.max(320, window.innerWidth - 16);
        const maxHeight = Math.max(320, window.innerHeight - 16);

        viewport.style.width = `${Math.min(Math.max(requestedWidth, 320), maxWidth)}px`;
        viewport.style.height = `${Math.min(Math.max(requestedHeight, 320), maxHeight)}px`;
    });

    function initializeSalesManagerLeadsPage() {
        if (leadsPageBooted) {
            return;
        }

        leadsPageBooted = true;
        applyAsmCompactLeadModeState();
        currentLeadView = 'list';
        if (!IS_REALTYX_LEAD_SHEET_USER) {
            persistAsmSectionViewPreference('leads', currentLeadView);
        }
        applyRealtyxLeadStripState();
        applyRealtyxLeadFiltersState();
        const leadsListBody = document.getElementById('leadsListBody');
        if (leadsListBody) {
            leadsListBody.addEventListener('click', function (event) {
                const ignoreTarget = event.target.closest('.js-no-row-nav');
                if (ignoreTarget) return;

                const row = event.target.closest('tr.lead-list-row[data-lead-url]');
                if (!row) return;

                const url = row.getAttribute('data-lead-url');
                if (url) {
                    window.location.href = url;
                }
            });
        }

        loadTeamMembers().then(() => {
            applyLeadQueryFilters();
            loadLeads();
        });
    }

    // Load leads on page load
    document.addEventListener('DOMContentLoaded', initializeSalesManagerLeadsPage);
    if (document.readyState !== 'loading') {
        setTimeout(initializeSalesManagerLeadsPage, 0);
    }

    window.matchMedia('(max-width: 768px)').addEventListener('change', function () {
        applyAsmCompactLeadModeState();
        applyRealtyxLeadFiltersState();
        if (allLeads.length) {
            loadLeads(currentLeadPage || 1);
        }
    });
</script>
@endpush



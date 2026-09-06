@extends('sales-manager.layout')

@section('title', 'Dashboard - Assistant Sales Manager')
@section('page-title', 'Dashboard')

@push('styles')
<style>
    .chart-container {
        position: relative;
        height: 300px;
        margin: 20px 0;
    }
    .achievement-card {
        background: white;
        padding: 24px;
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .achievement-title {
        font-size: 18px;
        font-weight: 600;
        color: var(--text-color);
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .achievement-stats {
        display: flex;
        justify-content: space-around;
        margin-top: 16px;
    }
    .stat-item {
        text-align: center;
    }
    .stat-value {
        font-size: 24px;
        font-weight: 700;
        color: var(--link-color);
    }
    .stat-label {
        font-size: 12px;
        color: #6b7280;
        margin-top: 4px;
    }
    .pending-badge {
        display: inline-block;
        padding: 4px 12px;
        background: #fef3c7;
        color: #92400e;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 500;
    }
    .header {
        display: none;
    }
    .asm-hero-kicker {
        margin: 0 0 22px;
        font-size: 22px;
        font-weight: 700;
        color: #002B45;
        line-height: 1.1;
        font-family: 'Playfair Display', serif;
    }
    .asm-dashboard-mobile-menu {
        display: none;
        width: 36px;
        height: 36px;
        border-radius: 10px;
        border: 1px solid #B7E0F4;
        background: #ffffff;
        color: #003B5C;
        align-items: center;
        justify-content: center;
        margin-bottom: 8px;
    }
    .asm-dashboard-filter-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
        margin: 16px 0 0;
        padding: 14px 16px;
        background: rgba(255,255,255,0.78);
        border: 1px solid #B7E0F4;
        border-radius: 18px;
    }
    .asm-hero-mobile-top {
        display: contents;
    }
    .asm-dashboard-filter-copy strong {
        display: block;
        color: #002B45;
        font-size: 14px;
        font-weight: 700;
    }
    .asm-dashboard-filter-copy span {
        display: block;
        color: #557084;
        font-size: 12px;
        margin-top: 2px;
    }
    .asm-dashboard-filter-copy span,
    .mobile-task-panel-subtitle,
    .asm-panel-subtitle,
    .sm-mobile-section-title strong {
        display: none !important;
    }
    .asm-dashboard-filter-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }
    .asm-dashboard-filter-tools {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-left: auto;
    }
    .asm-dashboard-filter-tools .global-notification-center {
        display: inline-flex;
    }
    .asm-dashboard-filter-mobile {
        display: none;
        width: 100%;
    }
    .asm-dashboard-filter-mobile-wrap {
        display: flex;
        align-items: center;
        gap: 10px;
        width: 100%;
    }
    .asm-dashboard-filter-select {
        width: 100%;
        min-height: 42px;
        border: 1px solid #B7E0F4;
        border-radius: 12px;
        padding: 0 14px;
        font-size: 13px;
        font-weight: 600;
        color: #004D73;
        background: #fff;
    }
    .asm-dashboard-cache-btn {
        border: 1px solid #B7E0F4;
        background: #fff;
        color: #004D73;
        border-radius: 12px;
        padding: 0 14px;
        min-height: 42px;
        font-size: 12px;
        font-weight: 700;
        white-space: nowrap;
        transition: all 0.2s ease;
    }
    .asm-dashboard-cache-btn:hover {
        background: #F0F9FF;
        border-color: #7DD3FC;
    }
    .asm-dashboard-cache-btn:disabled {
        opacity: 0.7;
        cursor: not-allowed;
    }
    .asm-dashboard-filter-chip {
        border: 1px solid #B7E0F4;
        background: #fff;
        color: #004D73;
        border-radius: 999px;
        padding: 9px 14px;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .asm-dashboard-filter-chip.active {
        background: linear-gradient(135deg, #002B45 0%, #006BA6 100%);
        color: #fff;
        border-color: transparent;
        box-shadow: 0 10px 18px rgba(0, 107, 166, 0.18);
    }
    .asm-dashboard-filter-chip.asm-dashboard-cache-btn {
        padding: 9px 14px;
    }
    .asm-dashboard-custom-range {
        display: none;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        width: 100%;
    }
    .asm-dashboard-custom-range.active {
        display: flex;
    }
    .asm-dashboard-date-input {
        border: 1px solid #B7E0F4;
        border-radius: 12px;
        padding: 10px 12px;
        font-size: 12px;
        color: #1f2937;
        background: #fff;
        min-width: 150px;
    }
    .asm-dashboard-apply-btn {
        border: none;
        border-radius: 12px;
        background: #006BA6;
        color: #fff;
        font-size: 12px;
        font-weight: 700;
        padding: 10px 14px;
        cursor: pointer;
    }
    .asm-dashboard-filter-status {
        color: #557084;
        font-size: 12px;
        font-weight: 600;
    }
    .asm-productivity-card {
        margin: 18px 0 0;
        background: #fff;
        border: 1px solid #B7E0F4;
        border-radius: 18px;
        padding: 16px;
        box-shadow: 0 10px 24px rgba(0, 107, 166, 0.08);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        flex-wrap: wrap;
    }
    .asm-productivity-card strong {
        display: block;
        color: #002B45;
        font-size: 14px;
        font-weight: 800;
    }
    .asm-productivity-card span {
        display: block;
        color: #557084;
        font-size: 12px;
        margin-top: 3px;
    }
    .asm-productivity-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 34px;
        border-radius: 999px;
        padding: 8px 14px;
        font-size: 12px;
        font-weight: 900;
        letter-spacing: .06em;
        text-transform: uppercase;
    }
    .asm-productivity-badge.pd { background: #EAF6FD; color: #006BA6; }
    .asm-productivity-badge.pending { background: #fff6da; color: #a15c07; }
    .asm-productivity-badge.npd { background: #f3f5f7; color: #475467; }
    
    /* Responsive Styles */
    @media (max-width: 767px) {
        .modal {
            padding: 12px;
            align-items: flex-start;
            padding-top: 28px;
        }
        .modal-content {
            width: 100%;
            max-width: 100%;
            border-radius: 18px;
            max-height: calc(100vh - 40px);
        }
        .modal-header {
            padding: 18px 18px 14px;
        }
        .modal-body {
            padding: 16px 16px 18px;
        }
        #managerLeadRequirementFormModal .modal-content {
            width: 100%;
            max-width: 100%;
            min-height: calc(100vh - 40px);
        }
        #managerLeadRequirementFormModal .modal-body {
            max-height: none;
            padding: 12px;
        }
        .asm-hero-mobile-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 10px;
        }
        .asm-hero-copy {
            display: flex;
            flex-direction: column;
        }
        .asm-dashboard-filter-bar {
            margin: 0;
            margin-left: auto;
            padding: 0;
            background: transparent;
            border: none;
            border-radius: 0;
            width: auto;
            min-width: 128px;
            flex: 0 0 auto;
        }
        .asm-dashboard-filter-copy {
            display: none;
        }
        .asm-dashboard-filter-bar {
            gap: 0;
        }
        .asm-dashboard-filter-actions {
            display: none;
        }
        .asm-dashboard-filter-tools {
            margin-left: 0;
            width: auto;
            justify-content: flex-start;
        }
        .asm-dashboard-filter-bar {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            align-items: center;
            column-gap: 8px;
            row-gap: 0;
        }
        .asm-dashboard-filter-tools {
            display: inline-flex;
            flex: 0 0 auto;
        }
        .asm-dashboard-filter-tools .global-notification-bell {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            box-shadow: 0 6px 18px rgba(0, 107, 166, 0.08);
        }
        .asm-dashboard-filter-tools .global-notification-badge {
            min-width: 18px;
            height: 18px;
            padding: 0 5px;
            font-size: 9px;
            top: -4px;
            right: -4px;
        }
        .asm-dashboard-filter-mobile {
            display: block;
            width: 100%;
        }
        .asm-dashboard-filter-mobile-wrap {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: center;
            gap: 8px;
            width: 100%;
        }
        .asm-dashboard-filter-select {
            min-height: 38px;
            border-radius: 12px;
            padding: 0 34px 0 12px;
            font-size: 12px;
            min-width: 108px;
            box-shadow: 0 6px 18px rgba(0, 107, 166, 0.08);
        }
        .asm-dashboard-filter-mobile-wrap .asm-dashboard-filter-select {
            flex: 1 1 auto;
            min-width: 0;
        }
        .asm-dashboard-cache-btn {
            min-height: 38px;
            padding: 0 12px;
            border-radius: 12px;
            box-shadow: 0 6px 18px rgba(0, 107, 166, 0.08);
        }
        .asm-dashboard-custom-range {
            width: 100%;
            margin-top: 10px;
            flex-direction: column;
            align-items: stretch;
        }
        .asm-dashboard-date-input,
        .asm-dashboard-apply-btn {
            width: 100%;
        }
        .asm-dashboard-filter-status {
            font-size: 11px;
        }
        .chart-container {
            height: 250px;
            margin: 16px 0;
        }
        
        .achievement-card {
            padding: 16px;
        }
        
        .achievement-title {
            font-size: 16px;
            flex-direction: column;
            align-items: flex-start;
            gap: 8px;
        }
        
        .achievement-stats {
            flex-direction: column;
            gap: 12px;
        }
        
        .stat-value {
            font-size: 20px;
        }
        
        /* Stats cards responsive - 2 columns on mobile */
        .stats-grid {
            grid-template-columns: repeat(2, 1fr) !important;
            gap: 12px !important;
        }
        
        .stats-grid > div {
            padding: 16px !important;
        }
        
        .stats-grid > div h3 {
            font-size: 20px !important;
        }
        
        .stats-grid > div p {
            font-size: 12px !important;
        }
        
        /* Hide Team Members card on mobile */
        .team-members-card {
            display: none !important;
        }
        
        /* Hide Pending Tasks card on mobile (keep only 4 cards: Leads, Prospects, Pending Verifications, Over Due) */
        .stats-grid > div:nth-child(6) {
            display: none !important;
        }
        
        /* Green Gradient Background for All Dashboard Cards on Mobile (like chatbot button) */
        .stats-grid > div,
        .stats-grid > div.bg-white,
        .stats-grid > .dashboard-card {
            background: linear-gradient(135deg, #002B45 0%, #006BA6 100%) !important;
            background-color: transparent !important;
            color: white !important;
            border: none !important;
            box-shadow: 0 4px 12px rgba(0, 107, 166, 0.4), 0 2px 4px rgba(0, 0, 0, 0.1) !important;
            position: relative;
            overflow: visible;
        }
        
        /* Remove any white background or overlay completely */
        .stats-grid > div.bg-white,
        .stats-grid > div[class*="bg-white"] {
            background: linear-gradient(135deg, #002B45 0%, #006BA6 100%) !important;
            background-color: transparent !important;
        }
        
        /* Remove any overlay pseudo-elements completely */
        .stats-grid > div::before,
        .stats-grid > div::after,
        .stats-grid > div > div::before,
        .stats-grid > div > div::after,
        .stats-grid > div.bg-white::before,
        .stats-grid > div.bg-white::after {
            display: none !important;
            content: none !important;
            background: none !important;
            opacity: 0 !important;
        }
        
        /* Show flex container with full opacity - no white overlay */
        .stats-grid > div > div.flex.items-center.justify-between {
            opacity: 1 !important;
            visibility: visible !important;
            background: transparent !important;
            position: relative;
            z-index: 1;
        }
        
        /* Ensure no white background on any child elements */
        .stats-grid > div > * {
            background: transparent !important;
        }
        
        /* Force remove white background from bg-white class */
        .stats-grid > div.bg-white {
            background: linear-gradient(135deg, #002B45 0%, #006BA6 100%) !important;
            background-color: transparent !important;
            background-image: none !important;
        }
        
        /* Ensure all text is visible and white on blue background */
        .stats-grid > div p,
        .stats-grid > div p.text-gray-500,
        .stats-grid > div .text-gray-500 {
            color: rgba(255, 255, 255, 0.95) !important;
            font-weight: 500 !important;
            opacity: 1 !important;
            visibility: visible !important;
        }
        
        .stats-grid > div h3,
        .stats-grid > div h3.text-gray-900,
        .stats-grid > div .text-gray-900 {
            color: white !important;
            font-weight: 700 !important;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2) !important;
            opacity: 1 !important;
            visibility: visible !important;
        }
        
        /* Ensure flex container and all child divs are visible */
        .stats-grid > div > div.flex.items-center.justify-between {
            opacity: 1 !important;
            visibility: visible !important;
            display: flex !important;
        }
        
        .stats-grid > div > div.flex.items-center.justify-between > div {
            opacity: 1 !important;
            visibility: visible !important;
            display: block !important;
        }
        
        /* Hide icon boxes completely on mobile - only hide icon divs with bg-* classes */
        .stats-grid > div .bg-indigo-100,
        .stats-grid > div .bg-sky-100,
        .stats-grid > div .bg-yellow-100,
        .stats-grid > div .bg-red-100,
        .stats-grid > div .bg-blue-100,
        .stats-grid > div .bg-orange-100 {
            display: none !important;
            visibility: hidden !important;
        }
        
        /* Improve card hover effect */
        .stats-grid > div {
            transition: all 0.3s ease !important;
        }
        
        .stats-grid > div:active {
            transform: scale(0.98) !important;
            box-shadow: 0 2px 6px rgba(0, 107, 166, 0.3) !important;
        }
        
        /* Table responsive */
        .overflow-x-auto {
            -webkit-overflow-scrolling: touch;
        }
        
        .overflow-x-auto table {
            min-width: 600px;
        }
        
        .overflow-x-auto th,
        .overflow-x-auto td {
            padding: 8px 12px;
            font-size: 12px;
        }
        
        /* Quick actions buttons */
        .grid.grid-cols-1.md\:grid-cols-2 > a {
            padding: 16px;
        }
        
        .grid.grid-cols-1.md\:grid-cols-2 > a i {
            font-size: 24px !important;
        }
        
        /* Hide Quick Actions on Mobile */
        .quick-actions-section {
            display: none !important;
        }
    }
    
    /* Mobile task digest */
    .recent-tasks-mobile {
        display: none;
        margin-bottom: 18px;
    }
    .mobile-task-stack {
        display: grid;
        gap: 16px;
    }
    .mobile-task-panel {
        background: #fff;
        border: 1px solid #e7ece9;
        border-radius: 22px;
        box-shadow: 0 12px 30px rgba(15, 45, 34, 0.08);
        overflow: hidden;
    }
    .modal {
        position: fixed;
        inset: 0;
        z-index: 1200;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 18px;
        background: rgba(15, 23, 42, 0.56);
        backdrop-filter: blur(3px);
    }
    .modal.active {
        display: flex !important;
    }
    .modal-content {
        width: min(100%, 520px);
        max-width: 520px;
        max-height: calc(100vh - 36px);
        overflow-y: auto;
        background: #ffffff;
        border-radius: 20px;
        box-shadow: 0 24px 70px rgba(15, 23, 42, 0.22);
        padding: 0;
    }
    .modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 22px 24px 16px;
        border-bottom: 1px solid #ecf0ed;
    }
    .modal-header h3 {
        margin: 0;
        font-size: 16px;
        font-weight: 800;
        color: #004D73;
    }
    .close-modal {
        width: 36px;
        height: 36px;
        border: 0;
        border-radius: 999px;
        background: #f4f7f5;
        color: #7b8c84;
        font-size: 24px;
        line-height: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }
    .modal-body {
        padding: 18px 22px 22px;
    }
    #managerLeadRequirementFormModal .modal-content {
        width: min(100%, 980px);
        max-width: 980px;
        padding: 0;
        overflow: hidden;
    }
    #managerLeadRequirementFormModal .modal-body {
        padding: 18px;
        max-height: calc(100vh - 120px);
        overflow-y: auto;
    }
    .mobile-task-panel.pending-panel {
        border-top: 4px solid #d8b4fe;
    }
    .mobile-task-panel.overdue-panel {
        border-top: 4px solid #fb923c;
    }
    .mobile-task-panel-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 16px 16px 10px;
    }
    .mobile-task-panel-title {
        margin: 0;
        font-size: 18px;
        font-weight: 700;
        color: #003B5C;
    }
    .mobile-task-panel-subtitle {
        margin-top: 2px;
        font-size: 12px;
        color: #75877d;
    }
    .mobile-task-panel-count {
        min-width: 42px;
        height: 42px;
        border-radius: 14px;
        padding: 0 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        font-weight: 800;
    }
    .pending-panel .mobile-task-panel-count {
        background: #f3e8ff;
        color: #7c3aed;
    }
    .overdue-panel .mobile-task-panel-count {
        background: #fff1e8;
        color: #ea580c;
    }
    .mobile-task-list {
        display: grid;
        gap: 10px;
        padding: 0 12px 6px;
    }
    .overdue-panel .mobile-task-list {
        height: 278px;
        overflow-y: auto;
        align-content: start;
        padding-bottom: 12px;
    }
    .mobile-task-card {
        display: flex;
        gap: 10px;
        padding: 12px;
        border-radius: 16px;
        background: linear-gradient(180deg, #fbfcfc 0%, #f4f8f6 100%);
        border: 1px solid #ebf0ed;
        text-decoration: none;
        color: inherit;
    }
    .mobile-task-accent {
        width: 4px;
        flex: 0 0 4px;
        border-radius: 999px;
        background: #d8b4fe;
    }
    .overdue-panel .mobile-task-accent {
        background: #fb923c;
    }
    .mobile-task-body {
        min-width: 0;
        flex: 1 1 auto;
    }
    .mobile-task-row {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 10px;
    }
    .mobile-task-title {
        margin: 0;
        font-size: 12px;
        font-weight: 700;
        line-height: 1.45;
        color: #004D73;
        display: -webkit-box;
        -webkit-line-clamp: 1;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .mobile-task-time {
        font-size: 11px;
        font-weight: 700;
        color: #5c6d64;
        white-space: nowrap;
    }
    .mobile-task-meta {
        margin-top: 6px;
        font-size: 12px;
        color: #354d42;
        line-height: 1.45;
        display: -webkit-box;
        -webkit-line-clamp: 1;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .mobile-task-age {
        margin-top: 6px;
        font-size: 11px;
        font-weight: 700;
    }
    .pending-panel .mobile-task-age {
        color: #6d28d9;
    }
    .overdue-panel .mobile-task-age {
        color: #c2410c;
    }
    .mobile-task-actions {
        display: flex;
        gap: 8px;
        margin-top: 10px;
    }
    .mobile-task-action-btn {
        flex: 1 1 50%;
        min-height: 38px;
        border-radius: 12px;
        border: 1px solid #d8e4dd;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        font-size: 12px;
        font-weight: 700;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .mobile-task-action-btn.call-btn {
        background: #006BA6;
        color: #fff;
        border-color: #006BA6;
    }
    .mobile-task-action-btn.complete-btn {
        background: #F7FBFF;
        color: #004D73;
    }
    .mobile-task-action-btn.complete-btn:hover {
        background: #eef5f1;
    }
    .asm-outcome-modal {
        border-radius: 22px !important;
        overflow: hidden;
        border: 1px solid rgba(0, 115, 177, 0.12);
        box-shadow: 0 28px 70px rgba(15, 23, 42, 0.24);
    }
    .asm-outcome-modal .modal-header {
        align-items: flex-start;
        gap: 16px;
        padding: 24px 28px 18px;
        border-bottom: 1px solid #e6eef4;
        background:
            radial-gradient(circle at top right, rgba(0, 115, 177, 0.12), transparent 36%),
            linear-gradient(180deg, #ffffff 0%, #f7fbfe 100%);
    }
    .asm-outcome-modal .modal-header h3 {
        margin: 0;
        color: #003b5c;
        font-size: 22px;
        font-weight: 800;
        line-height: 1.15;
    }
    .asm-outcome-subtitle {
        margin: 7px 0 0;
        color: #657786;
        font-size: 13px;
        font-weight: 600;
        line-height: 1.35;
    }
    .asm-outcome-modal .close-modal {
        width: 38px;
        height: 38px;
        border-radius: 12px;
        background: #eef6fb;
        color: #5d7180;
        font-size: 24px;
        line-height: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: background .18s ease, color .18s ease, transform .18s ease;
    }
    .asm-outcome-modal .close-modal:hover {
        background: #dff0fa;
        color: #003b5c;
        transform: translateY(-1px);
    }
    .asm-outcome-modal .modal-body {
        padding: 22px 28px 28px;
        background: #ffffff;
    }
    .asm-outcome-modal-body {
        padding: 0;
    }
    .asm-outcome-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }
    .asm-outcome-btn {
        position: relative;
        overflow: hidden;
        border: 0;
        border-radius: 16px;
        min-height: 64px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 9px;
        color: #fff;
        font-size: 15px;
        font-weight: 900;
        cursor: pointer;
        box-shadow: 0 12px 22px rgba(15, 23, 42, 0.11);
        transition: transform .18s ease, box-shadow .18s ease, filter .18s ease;
    }
    .asm-outcome-btn::after {
        content: "";
        position: absolute;
        inset: 0;
        background: linear-gradient(135deg, rgba(255,255,255,.18), transparent 48%);
        pointer-events: none;
    }
    .asm-outcome-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 16px 26px rgba(15, 23, 42, 0.15);
        filter: saturate(1.04);
    }
    .asm-outcome-btn i,
    .asm-outcome-btn span {
        position: relative;
        z-index: 1;
    }
    .asm-outcome-btn i {
        font-size: 17px;
    }
    .asm-outcome-btn-full {
        grid-column: 1 / -1;
    }
    .asm-outcome-btn-green { background: linear-gradient(135deg, #0073b1 0%, #005f91 100%); }
    .asm-outcome-btn-slate { background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%); }
    .asm-outcome-btn-blue { background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); }
    .asm-outcome-btn-amber { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
    .asm-outcome-btn-red { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); }
    @media (max-width: 560px) {
        .asm-outcome-modal {
            width: calc(100vw - 24px) !important;
            border-radius: 18px !important;
        }
        .asm-outcome-modal .modal-header {
            padding: 20px 22px 15px;
        }
        .asm-outcome-modal .modal-header h3 {
            font-size: 20px;
        }
        .asm-outcome-subtitle {
            font-size: 12px;
        }
        .asm-outcome-modal .modal-body {
            padding: 18px 22px 22px;
        }
        .asm-outcome-grid {
            gap: 10px;
        }
        .asm-outcome-btn {
            min-height: 58px;
            border-radius: 14px;
            font-size: 13px;
        }
        .asm-outcome-btn i {
            font-size: 15px;
        }
    }
    .btn-cancel,
    .btn-reject {
        min-height: 42px;
        padding: 0 18px;
        border-radius: 10px;
        font-weight: 700;
        border: 1px solid #d7ddd9;
    }
    .btn-cancel { background:#fff; color:#334155; }
    .btn-reject { background:#006BA6; color:#fff; border-color:#006BA6; }
    .mobile-task-footer {
        padding: 6px 16px 16px;
        display: flex;
        justify-content: center;
    }
    .mobile-task-more {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 13px;
        font-weight: 700;
        color: #2563eb;
        text-decoration: none;
    }
    .mobile-task-empty {
        padding: 8px 16px 18px;
        font-size: 13px;
        color: #6b7d73;
        text-align: center;
    }
    
    /* Desktop: Hide Recent Tasks, Show Quick Actions */
    @media (min-width: 768px) {
        .recent-tasks-mobile {
            display: none !important;
        }
        .quick-actions-section {
            display: block;
        }
        
        /* OMAXE Blue Gradient Background for All Dashboard Cards on Desktop */
        .stats-grid > div,
        .stats-grid > div.bg-white,
        .stats-grid > .dashboard-card {
            background: linear-gradient(135deg, #002B45 0%, #006BA6 100%) !important;
            background-color: transparent !important;
            color: white !important;
            border: none !important;
            box-shadow: 0 4px 12px rgba(0, 107, 166, 0.4), 0 2px 4px rgba(0, 0, 0, 0.1) !important;
        }
        
        /* Ensure all text is visible and white on blue background */
        .stats-grid > div p,
        .stats-grid > div p.text-gray-500,
        .stats-grid > div .text-gray-500 {
            color: rgba(255, 255, 255, 0.95) !important;
            font-weight: 500 !important;
        }
        
        .stats-grid > div h3,
        .stats-grid > div h3.text-gray-900,
        .stats-grid > div .text-gray-900 {
            color: white !important;
            font-weight: 700 !important;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2) !important;
        }
        
        /* Card hover effect */
        .stats-grid > div {
            transition: all 0.3s ease !important;
        }
        
        .stats-grid > div:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 16px rgba(0, 107, 166, 0.5), 0 2px 4px rgba(0, 0, 0, 0.1) !important;
        }
    }
    
    /* Mobile: Show Recent Tasks, Hide Quick Actions */
    @media (max-width: 767px) {
        .recent-tasks-mobile {
            display: block;
        }
        .quick-actions-section {
            display: none !important;
        }
        
        /* OMAXE Blue Background for All Dashboard Cards on Mobile */
        .stats-grid > div {
            background: #006BA6 !important; /* OMAXE blue color */
            color: white !important;
            border: none !important;
        }
        
        .stats-grid > div p {
            color: rgba(255, 255, 255, 0.9) !important;
        }
        
        .stats-grid > div h3 {
            color: white !important;
        }
        
        /* Icon background - scope to icon chip only (avoid white overlay layer on card) */
        .stats-grid > div .stat-icon {
            background: rgba(255, 255, 255, 0.22) !important;
            color: #ffffff !important;
        }
        
        .stats-grid > div .stat-icon i {
            color: #ffffff !important;
        }
    }
    
    @media (min-width: 768px) and (max-width: 1023px) {
        .chart-container {
            height: 280px;
        }
    }
    
    /* Target Cards Styling */
    .target-card-manager,
    .target-card-team {
        position: relative;
        overflow: hidden;
    }
    
    .target-card-manager::before,
    .target-card-team::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        opacity: 0.1;
        background: radial-gradient(circle at top right, rgba(255, 255, 255, 0.3), transparent 70%);
        pointer-events: none;
    }
    
    .target-card-manager h2,
    .target-card-team h2 {
        position: relative;
        z-index: 1;
    }
    
    .target-card-manager .space-y-5 > div,
    .target-card-team .space-y-5 > div {
        position: relative;
        z-index: 1;
    }
    
    .target-card-manager p,
    .target-card-team p {
        position: relative;
        z-index: 1;
    }
    
    /* Progress bar styling for target cards */
    .target-card-manager .bg-gray-300,
    .target-card-team .bg-gray-300 {
        background-color: rgba(255, 255, 255, 0.3) !important;
    }
    
    .target-card-manager .bg-white,
    .target-card-team .bg-white {
        background-color: rgba(255, 255, 255, 0.95) !important;
    }
    
    /* Responsive adjustments for target cards */
    @media (max-width: 767px) {
        .target-card-manager,
        .target-card-team {
            padding: 20px !important;
        }
        
        .target-card-manager h2,
        .target-card-team h2 {
            font-size: 18px !important;
            margin-bottom: 20px !important;
        }
        
        .target-card-manager .space-y-5 > div,
        .target-card-team .space-y-5 > div {
            margin-bottom: 16px !important;
        }
    }
    .asm-hero {
        background: linear-gradient(135deg, #ffffff 0%, #f6f3ec 100%);
        border: 1px solid #e4e0d7;
        border-radius: 24px;
        padding: 24px;
        box-shadow: 0 18px 44px rgba(16, 24, 20, 0.06);
        margin-bottom: 20px;
        display: grid;
        grid-template-columns: 1.35fr 1fr;
        gap: 18px;
    }
    .asm-hero-copy h2 {
        font-family: 'Fraunces', Georgia, serif;
        font-size: 30px;
        line-height: 1.1;
        color: #102A3A;
        margin-bottom: 10px;
    }
    .asm-hero-copy h2 span {
        color: #006BA6;
    }
    .asm-hero-copy p {
        color: #667068;
        font-size: 14px;
        max-width: 580px;
        line-height: 1.6;
    }
    .asm-action-row {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 18px;
    }
    .asm-action-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 14px;
        border-radius: 12px;
        border: 1px solid #d8d3c8;
        background: #fff;
        color: #102A3A;
        font-size: 13px;
        font-weight: 600;
    }
    .asm-action-btn.primary {
        background: linear-gradient(135deg, #006BA6 0%, #006BA6 100%);
        border-color: #006BA6;
        color: #fff;
    }
    .asm-focus-panel {
        background: linear-gradient(180deg, #002B45 0%, #003F63 100%);
        color: #fff;
        border-radius: 20px;
        padding: 20px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .asm-focus-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }
    .asm-focus-dialer-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-height: 36px;
        border: 1px solid rgba(255,255,255,0.22);
        border-radius: 999px;
        padding: 0 13px;
        background: rgba(255,255,255,0.12);
        color: #ffffff;
        font-size: 12px;
        font-weight: 700;
        line-height: 1;
        cursor: pointer;
        transition: background 0.18s ease, transform 0.18s ease;
    }
    .asm-focus-dialer-btn:hover {
        background: rgba(255,255,255,0.18);
        transform: translateY(-1px);
    }
    .asm-hero-side {
        display: grid;
        grid-template-columns: 1fr;
        gap: 14px;
        align-items: stretch;
    }
    .asm-favorites-panel {
        background: linear-gradient(180deg, #ffffff 0%, #f7fbf9 100%);
        border: 1px solid #d7e4de;
        border-radius: 18px;
        padding: 18px;
        display: flex;
        flex-direction: column;
        min-height: 100%;
        box-shadow: 0 18px 42px rgba(6, 58, 28, 0.08);
    }
    .asm-favorites-panel .eyebrow {
        font-size: 11px;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: #557084;
        margin-bottom: 8px;
    }
    .asm-favorites-panel h3 {
        font-size: 18px;
        font-weight: 700;
        color: #102A3A;
        margin-bottom: 4px;
    }
    .asm-favorites-subtitle {
        font-size: 12px;
        color: #64756d;
        margin-bottom: 12px;
    }
    .favorite-leads-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin-top: 4px;
        overflow: hidden;
    }
    .favorite-lead-item {
        border: 1px solid #dfe9e4;
        border-radius: 12px;
        padding: 12px;
        background: rgba(255, 255, 255, 0.92);
        box-shadow: 0 10px 24px rgba(16, 42, 58, 0.05);
        transition: border-color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
    }
    .favorite-lead-item:hover {
        border-color: #b9d7ca;
        box-shadow: 0 14px 28px rgba(6, 58, 28, 0.09);
        transform: translateY(-1px);
    }
    .favorite-lead-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 10px;
    }
    .favorite-lead-name {
        font-size: 13px;
        font-weight: 700;
        color: #174966;
        margin-bottom: 2px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .favorite-lead-status {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        background: #eaf7f1;
        color: #0b6b48;
        font-size: 10px;
        font-weight: 800;
        padding: 4px 8px;
        text-transform: uppercase;
        white-space: nowrap;
    }
    .favorite-lead-phone {
        font-size: 11px;
        color: #697c73;
        margin-bottom: 6px;
    }
    .favorite-lead-remark {
        font-size: 12px;
        color: #5b6d63;
        line-height: 1.45;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        word-break: break-word;
    }
    .favorite-lead-actions {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 8px;
        margin-top: 10px;
    }
    .favorite-lead-action {
        border: 1px solid #d5e2dc;
        border-radius: 8px;
        padding: 7px 8px;
        font-size: 11px;
        font-weight: 800;
        text-align: center;
        color: #0f4b35;
        background: #ffffff;
        cursor: pointer;
        transition: background 0.16s ease, color 0.16s ease, border-color 0.16s ease;
    }
    .favorite-lead-action:hover {
        background: #eef8f3;
        border-color: #99c7b3;
    }
    .favorite-lead-action.primary {
        background: #063A1C;
        border-color: #063A1C;
        color: #ffffff;
    }
    .favorite-lead-action.danger {
        color: #9f2d2d;
    }
    .favorite-lead-action:disabled {
        opacity: 0.55;
        cursor: not-allowed;
    }
    .favorite-leads-empty {
        border: 1px dashed #ced9d3;
        border-radius: 12px;
        padding: 14px;
        font-size: 13px;
        color: #6b7f74;
        background: #fbfdfc;
    }
    .asm-focus-panel .eyebrow {
        font-size: 11px;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: rgba(255,255,255,0.58);
        margin-bottom: 0;
    }
    .asm-focus-panel h3,
    .asm-focus-panel p {
        display: none;
    }
    .asm-mini-metrics {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 10px;
        margin-top: 18px;
    }
    .asm-mini-metrics > div,
    .asm-mini-metrics > a {
        background: rgba(255,255,255,0.08);
        border: 1px solid rgba(255,255,255,0.08);
        border-radius: 14px;
        padding: 12px;
        min-width: 0;
    }
    .asm-mini-metric-link {
        display: block;
        color: inherit;
        text-decoration: none;
        transition: transform 0.18s ease, border-color 0.18s ease, background 0.18s ease;
    }
    .asm-mini-metric-link:hover,
    .asm-mini-metric-link:focus-visible {
        background: rgba(255,255,255,0.12);
        border-color: rgba(255,255,255,0.18);
        transform: translateY(-1px);
        text-decoration: none;
        color: inherit;
    }
    .asm-mini-metrics strong {
        display: block;
        font-size: 18px;
        margin-bottom: 4px;
        line-height: 1.1;
        word-break: break-word;
    }
    .asm-mini-metrics span {
        font-size: 11px;
        color: rgba(255,255,255,0.64);
    }
    @media (max-width: 1100px) {
        .asm-mini-metrics {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }
    @media (max-width: 640px) {
        .asm-focus-panel {
            padding: 16px;
        }

        .asm-focus-head {
            display: flex !important;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            min-height: 38px;
            width: 100%;
        }

        .asm-focus-head .eyebrow {
            flex: 1 1 auto;
            min-width: 0;
            color: rgba(255,255,255,0.76);
            font-size: 12px;
            line-height: 1;
        }

        .asm-focus-dialer-btn {
            display: inline-flex !important;
            flex: 0 0 auto;
            min-width: 92px;
            min-height: 38px;
            padding: 0 12px;
            border-color: rgba(255,255,255,0.34);
            background: #ffffff;
            color: #003F63;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.14);
        }

        .asm-mini-metrics {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            margin-top: 14px;
        }
    }
    .asm-stats-grid {
        gap: 14px !important;
        margin-bottom: 18px !important;
    }
    .asm-stat-card {
        border-radius: 18px;
        border: 1px solid rgba(255,255,255,0.08);
        background: linear-gradient(135deg, #002B45 0%, #006BA6 52%, #0EA5E9 100%) !important;
        box-shadow: 0 18px 36px rgba(0, 107, 166, 0.18) !important;
        color: #fff !important;
        position: relative;
        overflow: hidden;
    }
    .asm-stat-card::before {
        content: '';
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at top right, rgba(255,255,255,0.14), transparent 42%);
        pointer-events: none;
    }
    .asm-stat-card .stat-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        position: relative;
        z-index: 1;
    }
    .asm-stat-card .stat-kicker {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: rgba(255,255,255,0.68) !important;
        margin-bottom: 8px;
    }
    .asm-stat-card .stat-kicker {
        display: none !important;
    }
    .asm-stat-card .stat-value {
        font-size: 32px;
        font-weight: 700;
        color: #ffffff !important;
        line-height: 1;
        text-shadow: 0 1px 2px rgba(0,0,0,0.16);
    }
    .asm-stat-card .stat-label {
        margin-top: 6px;
        font-size: 13px;
        color: rgba(255,255,255,0.92) !important;
    }
    .attendance-widget--sales-manager-dashboard .attendance-widget-copy,
    .attendance-widget--sales-manager-dashboard .attendance-widget-compact-copy {
        display: none !important;
    }
    .asm-stat-card .stat-icon {
        width: 44px;
        height: 44px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(255,255,255,0.92);
        color: #006BA6;
        font-size: 16px;
        box-shadow: 0 10px 22px rgba(0,0,0,0.12);
    }
    .asm-stat-link {
        display: block;
        text-decoration: none;
        color: inherit;
        transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
    }
    .asm-stat-link:hover,
    .asm-stat-link:focus-visible {
        color: inherit;
        text-decoration: none;
        transform: translateY(-2px);
        box-shadow: 0 20px 40px rgba(0, 107, 166, 0.22) !important;
        border-color: rgba(255,255,255,0.16);
    }
    .asm-stat-card.is-hidden {
        display: none !important;
    }
    .asm-stat-card.total-leads-card {
        background: linear-gradient(135deg, #002B45 0%, #006BA6 52%, #0EA5E9 100%) !important;
    }
    .asm-panel {
        background: #fff;
        border: 1px solid #e4e0d7;
        border-radius: 20px;
        box-shadow: 0 16px 36px rgba(16, 24, 20, 0.05);
    }
    .asm-panel-title {
        font-family: 'Fraunces', Georgia, serif;
        font-size: 24px;
        color: #102A3A;
        margin-bottom: 6px;
    }
    .asm-panel-subtitle {
        color: #667068;
        font-size: 13px;
    }
    @media (max-width: 767px) {
        .asm-hero {
            grid-template-columns: 1fr;
            padding: 18px;
            border-radius: 18px;
        }
        .asm-hero-side {
            grid-template-columns: 1fr;
        }
        .asm-hero-copy h2 {
            font-size: 22px;
            font-family: 'Outfit', sans-serif;
            margin-bottom: 0;
        }
        .asm-hero-kicker {
            font-size: 18px;
            margin-bottom: 0;
        }
        .asm-stats-grid {
            grid-template-columns: repeat(2, 1fr) !important;
        }
        .asm-stat-card.total-leads-card {
            grid-column: 1 / -1;
        }
        .asm-stat-card {
            padding: 16px !important;
        }
        .asm-stat-card.total-leads-card .stat-value {
            font-size: 38px;
        }
        .asm-stat-card .stat-value {
            font-size: 24px;
        }
    }
    .asm-hero-copy p,
    .asm-action-row {
        display: none;
    }

    @media (max-width: 767px) {
        body.app-webview-mode #mainContent .container {
            padding-top: 12px !important;
        }
        .asm-hero {
            overflow: hidden;
        }
        .asm-hero-mobile-top {
            display: grid;
            grid-template-columns: minmax(150px, 1fr) auto;
            align-items: center;
            gap: 8px;
            width: 100%;
        }
        body.app-webview-mode .asm-hero-mobile-top {
            align-items: start;
            margin-top: 8px;
            padding-top: 6px;
        }
        .asm-hero-kicker {
            margin: 0;
            min-width: 0;
            font-family: 'Playfair Display', serif;
            font-size: 22px;
            line-height: 1.05;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        body.app-webview-mode .asm-hero-kicker {
            font-size: clamp(28px, 7.5vw, 30px);
            line-height: 1.05;
            padding-top: 4px;
            margin-bottom: 14px;
        }
        .asm-dashboard-filter-bar {
            width: auto;
            min-width: 0;
            max-width: 100%;
            display: grid;
            grid-template-columns: auto auto;
            align-items: center;
            gap: 8px;
        }
        .asm-dashboard-filter-tools {
            min-width: 0;
        }
        body.app-webview-mode .asm-dashboard-filter-tools {
            transform: translateY(12px);
        }
        .asm-dashboard-filter-mobile {
            min-width: 0;
            width: 100%;
        }
        .asm-dashboard-filter-mobile-wrap {
            display: block;
            min-width: 0;
            width: 100%;
        }
        .asm-dashboard-filter-select {
            width: 92px;
            min-width: 92px;
            max-width: 92px;
            min-height: 38px;
            padding: 0 28px 0 12px;
            font-size: 12px;
        }
        .asm-dashboard-filter-mobile .asm-dashboard-cache-btn {
            display: none !important;
        }
        .asm-dashboard-filter-tools .global-notification-center {
            flex: 0 0 auto;
        }
        .asm-dashboard-filter-tools .global-notification-bell {
            width: 38px;
            height: 38px;
            min-width: 38px;
        }
        .asm-hero-copy h2 {
            font-size: clamp(24px, 7vw, 32px);
            line-height: 1.15;
            margin-top: 14px;
        }
        body,
        .main-content,
        .asm-dashboard-page {
            overflow-x: hidden;
        }
    }

    .asm-dialer-launch {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 9px;
        min-height: 46px;
        border: 1px solid rgba(0, 107, 166, 0.18);
        border-radius: 999px;
        padding: 0 18px;
        color: #ffffff;
        background: #006BA6;
        font-size: 0.92rem;
        font-weight: 700;
        line-height: 1;
        cursor: pointer;
        box-shadow: 0 12px 26px rgba(0, 107, 166, 0.18);
        transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
    }

    .asm-dialer-launch:hover {
        background: #005983;
        box-shadow: 0 16px 30px rgba(0, 107, 166, 0.22);
        transform: translateY(-1px);
    }

    .asm-dialer-mobile-launch {
        display: none;
        position: fixed;
        right: 18px;
        bottom: 84px;
        z-index: 45;
        width: 56px;
        height: 56px;
        border: 0;
        border-radius: 999px;
        color: #ffffff;
        background: #006BA6;
        box-shadow: 0 18px 34px rgba(0, 107, 166, 0.28);
    }

    .asm-dialer-card {
        width: min(420px, calc(100vw - 28px));
        border-radius: 24px;
        overflow: hidden;
    }

    .asm-dialer-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        padding: 18px 20px;
        border-bottom: 1px solid #E2EEF3;
        background: linear-gradient(135deg, #ffffff 0%, #F0F9FF 100%);
    }

    .asm-dialer-head h3 {
        margin: 0;
        color: #002B45;
        font-size: 1.12rem;
        font-weight: 750;
    }

    .asm-dialer-head p {
        margin: 4px 0 0;
        color: #557084;
        font-size: 0.82rem;
        font-weight: 500;
    }

    .asm-dialer-close {
        width: 36px;
        height: 36px;
        border: 1px solid #D8E6EC;
        border-radius: 999px;
        background: #ffffff;
        color: #002B45;
        cursor: pointer;
    }

    .asm-dialer-body {
        padding: 20px;
    }

    .asm-dialer-display {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px 14px;
        border: 1px solid #D8E6EC;
        border-radius: 16px;
        background: #F8FBFC;
    }

    .asm-dialer-prefix {
        color: #557084;
        font-size: 1rem;
        font-weight: 650;
    }

    .asm-dialer-input {
        width: 100%;
        border: 0;
        outline: 0;
        background: transparent;
        color: #002B45;
        font-size: 1.6rem;
        font-weight: 650;
        letter-spacing: 0;
    }

    .asm-dialer-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
        margin-top: 16px;
    }

    .asm-dialer-key {
        min-height: 54px;
        border: 1px solid #D8E6EC;
        border-radius: 16px;
        background: #ffffff;
        color: #002B45;
        font-size: 1.22rem;
        font-weight: 650;
        cursor: pointer;
        transition: background 0.16s ease, border-color 0.16s ease;
    }

    .asm-dialer-key:hover {
        border-color: rgba(0, 107, 166, 0.28);
        background: #F0F9FF;
    }

    .asm-dialer-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        margin-top: 16px;
    }

    .asm-dialer-action {
        min-height: 46px;
        border-radius: 14px;
        border: 1px solid #D8E6EC;
        background: #ffffff;
        color: #002B45;
        font-size: 0.9rem;
        font-weight: 650;
        cursor: pointer;
    }

    .asm-dialer-call {
        border-color: #8BE7B8;
        color: #ffffff;
        background: #007A4D;
        box-shadow: 0 12px 22px rgba(0, 122, 77, 0.18);
    }

    .asm-dialer-call:disabled,
    .asm-dialer-action:disabled {
        cursor: not-allowed;
        opacity: 0.7;
    }

    .asm-dialer-status {
        min-height: 22px;
        margin-top: 12px;
        color: #557084;
        font-size: 0.84rem;
        font-weight: 550;
    }

    .asm-dialer-status.success {
        color: #007A4D;
    }

    .asm-dialer-status.error {
        color: #B42318;
    }

    .asm-dialer-recent {
        margin-top: 16px;
        padding-top: 14px;
        border-top: 1px solid #EEF3F6;
    }

    .asm-dialer-recent-title {
        margin-bottom: 8px;
        color: #557084;
        font-size: 0.76rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .asm-dialer-recent-list {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .asm-dialer-recent-chip {
        border: 1px solid #D8E6EC;
        border-radius: 999px;
        padding: 7px 10px;
        background: #ffffff;
        color: #002B45;
        font-size: 0.82rem;
        font-weight: 600;
        cursor: pointer;
    }

    .sm-dashboard-mode-toggle {
        display: inline-flex;
        gap: 8px;
        padding: 6px;
        border-radius: 999px;
        background: #E0F2FE;
        border: 1px solid rgba(0, 107, 166, 0.12);
    }

    .sm-dashboard-mode-btn {
        min-width: 96px;
        border: 0;
        border-radius: 999px;
        padding: 10px 20px;
        color: #006BA6;
        background: transparent;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.18s ease;
    }

    .sm-dashboard-mode-btn.is-active {
        color: #ffffff;
        background: #006BA6;
        box-shadow: 0 12px 24px rgba(0, 107, 166, 0.22);
    }

    .sm-mobile-section-title,
    .sm-mobile-quick-actions {
        display: none;
    }

    .sm-mobile-section-title {
        margin: 18px 2px 10px;
    }

    .sm-mobile-section-title span {
        display: block;
        color: #557084;
        font-size: 0.72rem;
        font-weight: 850;
        letter-spacing: 0.14em;
        text-transform: uppercase;
    }

    .sm-mobile-section-title strong {
        display: block;
        margin-top: 2px;
        color: #002B45;
        font-size: 1.08rem;
        line-height: 1.15;
        font-weight: 850;
    }

    .sm-mobile-quick-actions {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 8px;
        margin: 16px 0 18px;
    }

    .sm-mobile-quick-actions a {
        min-width: 0;
        border: 1px solid rgba(0, 107, 166, 0.10);
        border-radius: 16px;
        padding: 12px 8px;
        background: #ffffff;
        color: #006BA6;
        text-align: center;
        text-decoration: none;
        box-shadow: 0 10px 22px rgba(0, 107, 166, 0.07);
    }

    .sm-mobile-quick-actions i {
        display: block;
        margin-bottom: 6px;
        color: #006BA6;
        font-size: 1rem;
    }

    .sm-mobile-quick-actions span {
        display: block;
        overflow: hidden;
        font-size: 0.72rem;
        font-weight: 800;
        line-height: 1.1;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .sm-dashboard-tab-panel[hidden] {
        display: none !important;
    }

    .sm-team-dashboard {
        margin-top: 18px;
    }

    .sm-team-dashboard-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        padding: 14px 16px;
        border: 1px solid #b9cec0;
        border-radius: 6px;
        background: #edf7f0;
        box-shadow: none;
    }

    .sm-team-dashboard-header h2 {
        margin: 0;
        color: #123d2a;
        font-size: 1.18rem;
        font-weight: 750;
    }

    .sm-team-dashboard-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
        flex-wrap: wrap;
    }

    .sm-team-dashboard-header .sm-dashboard-mode-toggle,
    .asm-dashboard-filter-tools .sm-dashboard-mode-toggle {
        gap: 0;
        padding: 3px;
        border: 1px solid #8fbea0;
        border-radius: 4px;
        background: #ffffff;
    }

    .sm-team-dashboard-header .sm-dashboard-mode-btn,
    .asm-dashboard-filter-tools .sm-dashboard-mode-btn {
        min-width: 68px;
        border-radius: 3px;
        padding: 8px 14px;
        box-shadow: none;
    }

    .sm-team-custom-range {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .sm-team-custom-range[hidden] {
        display: none !important;
    }

    .sm-team-date-field {
        display: grid;
        gap: 2px;
    }

    .sm-team-date-field span {
        color: #567164;
        font-size: 0.62rem;
        font-weight: 750;
        text-transform: uppercase;
    }

    .sm-team-date-input {
        min-height: 42px;
        border: 1px solid #8fbea0;
        border-radius: 4px;
        padding: 0 9px;
        background: #ffffff;
        color: #17633c;
        font: inherit;
        font-size: 0.76rem;
        font-weight: 650;
    }

    .sm-team-date-input:focus {
        border-color: #217346;
        outline: 2px solid rgba(33, 115, 70, 0.14);
    }

    .sm-team-dashboard-subtitle {
        margin: 4px 0 0;
        color: #567164;
        font-size: 0.78rem;
        font-weight: 650;
    }

    .sm-team-range-label,
    .sm-team-range-select,
    .sm-team-refresh-btn {
        min-height: 42px;
        border: 1px solid #8fbea0;
        border-radius: 4px;
        background: #ffffff;
        color: #17633c;
        font-size: 0.8rem;
        font-weight: 700;
    }

    .sm-team-range-select {
        padding: 0 36px 0 14px;
    }

    .sm-team-refresh-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 0 16px;
        color: #ffffff;
        border-color: #217346;
        background: #217346;
        box-shadow: none;
    }

    .sm-team-dashboard-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 18px;
        margin-top: 18px;
    }

    .sm-team-kpi-grid {
        display: grid;
        grid-template-columns: repeat(5, minmax(150px, 1fr));
        gap: 0;
        margin-top: 12px;
        overflow: hidden;
        border: 1px solid #b9cec0;
        border-radius: 6px;
    }

    .sm-team-kpi-card {
        min-width: 0;
        border: 0;
        border-right: 1px solid #d5e2d9;
        border-radius: 0;
        padding: 12px 14px;
        background: #ffffff;
        box-shadow: none;
    }

    .sm-team-kpi-card:last-child { border-right: 0; }

    .sm-team-kpi-card span {
        display: block;
        color: #5d6b64;
        font-size: 0.7rem;
        font-weight: 850;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    .sm-team-kpi-card strong {
        display: block;
        margin-top: 6px;
        color: #17633c;
        font-size: 1.45rem;
        font-weight: 900;
        line-height: 1;
    }

    .sm-team-kpi-card small {
        display: block;
        margin-top: 5px;
        color: #6f7b74;
        font-size: 0.78rem;
        font-weight: 650;
    }

    .sm-team-card {
        min-width: 0;
        border: 1px solid rgba(0, 107, 166, 0.12);
        border-radius: 24px;
        background: #ffffff;
        box-shadow: 0 18px 40px rgba(0, 107, 166, 0.06);
        overflow: hidden;
    }

    .sm-team-card-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 18px 20px;
        border-bottom: 1px solid rgba(0, 107, 166, 0.08);
    }

    .sm-team-card-head h3 {
        margin: 0;
        color: #002B45;
        font-size: 1.08rem;
        font-weight: 750;
    }

    .sm-team-table-wrap {
        max-height: 420px;
        overflow: auto;
        scrollbar-gutter: stable;
    }

    .sm-team-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 760px;
        table-layout: auto;
    }

    .sm-team-performance-table {
        min-width: 1260px;
        table-layout: fixed;
    }

    .sm-team-table th,
    .sm-team-table td {
        padding: 12px 14px;
        border-bottom: 1px solid rgba(0, 107, 166, 0.08);
        text-align: left;
        vertical-align: middle;
        white-space: normal;
    }

    .sm-team-table th {
        position: sticky;
        top: 0;
        z-index: 1;
        color: #5d6b64;
        background: #f6faf7;
        font-size: 0.7rem;
        font-weight: 650;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .sm-team-table td {
        color: #002B45;
        font-weight: 500;
    }

    .sm-team-table tbody tr:hover {
        background: #f9fcfa;
    }

    /* Spreadsheet treatment for the manager's daily allocation tracker. */
    .sm-team-response-card {
        border-color: #b9cec0;
        border-radius: 6px;
        box-shadow: none;
    }

    .sm-team-response-card .sm-team-card-head {
        padding: 12px 14px;
        border-bottom-color: #b9cec0;
        background: #edf7f0;
    }

    .sm-team-response-card .sm-team-card-head h3 {
        font-size: 0.96rem;
    }

    .sm-team-response-table {
        min-width: 980px;
        table-layout: fixed;
    }

    .sm-team-response-table th,
    .sm-team-response-table td {
        border: 1px solid #d5e2d9;
        padding: 10px 12px;
    }

    .sm-team-response-table th {
        color: #ffffff;
        background: #217346;
        border-color: #5b9571;
        font-size: 0.67rem;
        font-weight: 750;
        letter-spacing: 0.04em;
    }

    .sm-team-response-table td {
        background: #ffffff;
    }

    .sm-team-response-table tbody tr:nth-child(even) td {
        background: #f7fbf8;
    }

    .sm-team-response-table tbody tr:hover td {
        background: #e8f3eb;
    }

    .sm-team-response-table th:nth-child(1),
    .sm-team-response-table td:nth-child(1) { width: 260px; }
    .sm-team-response-table th:nth-child(2),
    .sm-team-response-table td:nth-child(2) { width: 175px; text-align: center; }
    .sm-team-response-table th:nth-child(3),
    .sm-team-response-table td:nth-child(3),
    .sm-team-response-table th:nth-child(4),
    .sm-team-response-table td:nth-child(4) { width: 145px; text-align: center; }
    .sm-team-response-table th:nth-child(5),
    .sm-team-response-table td:nth-child(5) { width: 170px; text-align: center; }
    .sm-team-response-table th:nth-child(6),
    .sm-team-response-table td:nth-child(6) { width: 125px; text-align: center; }

    .sm-team-response-table .sm-team-avatar {
        border-radius: 4px;
        background: #217346;
    }

    .sm-team-response-table .sm-team-metric-pill {
        min-width: 42px;
        min-height: 28px;
        border: 1px solid #c9ddd0;
        border-radius: 3px;
        padding: 3px 8px;
        background: #f1f8f3;
        color: #17633c;
        font-size: 0.82rem;
    }

    .sm-team-response-table .sm-team-metric-pill.warning {
        border-color: #f1d4b4;
        background: #fff8ef;
        color: #bd4c09;
    }

    .sm-team-response-table .sm-team-action-link {
        justify-content: center;
        min-height: 28px;
        border-color: #8fbea0;
        border-radius: 3px;
        padding: 4px 8px;
        color: #17633c;
        background: #ffffff;
    }

    .sm-team-performance-table td:not(:first-child),
    .sm-team-performance-table th:not(:first-child) {
        text-align: center;
    }

    .sm-team-performance-table th:first-child,
    .sm-team-performance-table td:first-child {
        position: sticky;
        left: 0;
        width: 230px;
        min-width: 230px;
        max-width: 230px;
        background: #ffffff;
        box-shadow: 10px 0 18px rgba(15, 42, 31, 0.04);
        z-index: 2;
    }

    .sm-team-performance-table th:first-child {
        z-index: 4;
        background: #f6faf7;
    }

    .sm-team-performance-table th:nth-child(2),
    .sm-team-performance-table td:nth-child(2) {
        width: 92px;
    }

    .sm-team-performance-table th:nth-child(3),
    .sm-team-performance-table td:nth-child(3),
    .sm-team-performance-table th:nth-child(4),
    .sm-team-performance-table td:nth-child(4) {
        width: 104px;
    }

    .sm-team-performance-table th:nth-child(5),
    .sm-team-performance-table td:nth-child(5),
    .sm-team-performance-table th:nth-child(6),
    .sm-team-performance-table td:nth-child(6),
    .sm-team-performance-table th:nth-child(7),
    .sm-team-performance-table td:nth-child(7),
    .sm-team-performance-table th:nth-child(8),
    .sm-team-performance-table td:nth-child(8) {
        width: 86px;
    }

    .sm-team-performance-table th:nth-child(9),
    .sm-team-performance-table td:nth-child(9) {
        width: 124px;
    }

    .sm-team-performance-table th:last-child,
    .sm-team-performance-table td:last-child {
        width: 250px;
        text-align: left;
    }

    .sm-team-member-cell {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .sm-team-member-meta {
        min-width: 0;
    }

    .sm-team-member-meta strong,
    .sm-team-member-meta span {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .sm-team-member-meta strong {
        color: #002B45;
        font-size: 0.9rem;
        font-weight: 700;
    }

    .sm-team-member-meta span {
        margin-top: 2px;
        color: #6f7b74;
        font-size: 0.74rem;
        font-weight: 550;
    }

    .sm-team-avatar {
        width: 34px;
        height: 34px;
        display: inline-grid;
        place-items: center;
        flex: 0 0 34px;
        border-radius: 12px;
        color: #ffffff;
        background: #006BA6;
        font-size: 0.82rem;
        font-weight: 700;
    }

    .sm-team-empty-cell {
        padding: 34px 18px !important;
        color: #7a8780 !important;
        text-align: center !important;
    }

    .sm-team-metric-pill {
        display: inline-flex;
        min-width: 38px;
        min-height: 34px;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        padding: 5px 10px;
        background: #f2f8f5;
        color: #002B45;
        font-size: 0.86rem;
        font-weight: 700;
    }

    .sm-team-metric-pill.warning {
        background: #fff7ed;
        color: #c2410c;
    }

    .sm-team-metric-pill.good {
        background: #ecfdf5;
        color: #047857;
    }

    .sm-team-metric-button {
        border: 0;
        cursor: pointer;
    }

    .sm-team-action-link {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        border: 1px solid rgba(0, 107, 166, 0.14);
        border-radius: 999px;
        padding: 7px 11px;
        color: #006BA6;
        background: #ffffff;
        font-size: 0.76rem;
        font-weight: 700;
        text-decoration: none;
        white-space: nowrap;
    }

    .sm-team-action-list {
        display: flex;
        flex-wrap: wrap;
        gap: 7px;
    }

    .sm-team-action-link.sm-team-action-button {
        cursor: pointer;
    }

    .sm-team-performance-card {
        border-color: #b9cec0;
        border-radius: 6px;
        box-shadow: none;
    }

    .sm-team-performance-card .sm-team-card-head {
        padding: 12px 14px;
        border-bottom-color: #b9cec0;
        background: #edf7f0;
    }

    .sm-team-performance-card .sm-team-card-head h3 {
        font-size: 0.96rem;
    }

    .sm-team-performance-table th,
    .sm-team-performance-table td {
        border: 1px solid #d5e2d9;
        padding: 10px 12px;
    }

    .sm-team-performance-table th {
        color: #ffffff;
        background: #217346;
        border-color: #5b9571;
        font-size: 0.67rem;
        font-weight: 750;
        letter-spacing: 0.04em;
    }

    .sm-team-performance-table td {
        background: #ffffff;
    }

    .sm-team-performance-table tbody tr:nth-child(even) td {
        background: #f7fbf8;
    }

    .sm-team-performance-table tbody tr:hover td {
        background: #e8f3eb;
    }

    .sm-team-performance-table th:first-child {
        background: #217346;
        box-shadow: 1px 0 0 #5b9571;
    }

    .sm-team-performance-table td:first-child {
        background: #ffffff;
        box-shadow: 1px 0 0 #d5e2d9;
    }

    .sm-team-performance-table tbody tr:nth-child(even) td:first-child {
        background: #f7fbf8;
    }

    .sm-team-performance-table tbody tr:hover td:first-child {
        background: #e8f3eb;
    }

    .sm-team-performance-table .sm-team-avatar {
        border-radius: 4px;
        background: #217346;
    }

    .sm-team-performance-table .sm-team-metric-pill {
        min-width: 38px;
        min-height: 28px;
        border: 1px solid #c9ddd0;
        border-radius: 3px;
        padding: 3px 7px;
        background: #f1f8f3;
        color: #17633c;
        font-size: 0.8rem;
    }

    .sm-team-performance-table .sm-team-metric-pill.warning {
        border-color: #f1d4b4;
        background: #fff8ef;
        color: #bd4c09;
    }

    .sm-team-performance-table .sm-team-stage-chip {
        border-color: #c9ddd0;
        border-radius: 3px;
        padding: 4px 6px;
        color: #24533a;
        background: #ffffff;
        font-size: 0.68rem;
    }

    .sm-team-performance-table .sm-team-fresh-toggle {
        gap: 7px;
        border: 1px solid #c9ddd0;
        cursor: pointer;
    }

    .sm-team-performance-table .sm-team-fresh-toggle i {
        font-size: 0.62rem;
        transition: transform 160ms ease;
    }

    .sm-team-performance-table .sm-team-fresh-toggle.is-open i {
        transform: rotate(180deg);
    }

    .sm-team-performance-table .sm-team-fresh-toggle:disabled {
        cursor: default;
        opacity: 0.72;
    }

    .sm-team-performance-table .sm-team-fresh-detail-row > td,
    .sm-team-performance-table .sm-team-fresh-detail-row > td:first-child {
        position: static;
        width: auto;
        min-width: 0;
        max-width: none;
        padding: 0;
        text-align: left;
        background: #f4f9f6;
        box-shadow: none;
    }

    .sm-team-fresh-detail-content {
        padding: 14px;
        border-left: 3px solid #217346;
    }

    .sm-team-fresh-detail-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 10px;
    }

    .sm-team-fresh-detail-head div > strong,
    .sm-team-fresh-detail-head div > span {
        display: block;
    }

    .sm-team-fresh-detail-head strong {
        font-size: 0.84rem;
    }

    .sm-team-fresh-detail-head span {
        color: #63766b;
        font-size: 0.72rem;
    }

    .sm-team-fresh-lead-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 8px;
        max-height: 280px;
        overflow: auto;
    }

    .sm-team-fresh-lead {
        display: grid;
        grid-template-columns: 32px minmax(0, 1fr) auto 14px;
        align-items: center;
        gap: 9px;
        min-height: 58px;
        padding: 8px 10px;
        border: 1px solid #cfe0d5;
        border-radius: 5px;
        background: #ffffff;
        color: #163f2d;
        text-decoration: none;
    }

    .sm-team-fresh-lead:hover {
        border-color: #8fbea0;
        background: #f9fcfa;
    }

    .sm-team-fresh-lead-avatar {
        width: 32px;
        height: 32px;
        display: grid;
        place-items: center;
        border-radius: 4px;
        background: #e4f2e9;
        color: #17633c;
        font-size: 0.68rem;
        font-weight: 800;
    }

    .sm-team-fresh-lead-copy {
        min-width: 0;
    }

    .sm-team-fresh-lead-copy strong,
    .sm-team-fresh-lead-copy small {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .sm-team-fresh-lead-copy strong {
        font-size: 0.78rem;
    }

    .sm-team-fresh-lead-copy small {
        margin-top: 3px;
        color: #63766b;
        font-size: 0.7rem;
    }

    .sm-team-fresh-lead-copy small i {
        margin-right: 5px;
    }

    .sm-team-fresh-lead-status {
        padding: 3px 6px;
        border: 1px solid #c9ddd0;
        border-radius: 3px;
        background: #f1f8f3;
        font-size: 0.64rem;
        font-weight: 700;
        white-space: nowrap;
    }

    .sm-team-fresh-lead > i {
        color: #638070;
        font-size: 0.64rem;
    }

    .sm-team-fresh-loading,
    .sm-team-fresh-empty {
        padding: 20px;
        color: #63766b;
        text-align: center;
        font-size: 0.78rem;
    }

    .sm-team-fresh-view-all {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        margin-top: 10px;
        color: #17633c;
        font-size: 0.75rem;
        font-weight: 700;
    }

    @media (max-width: 900px) {
        .sm-team-fresh-lead-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 640px) {
        .sm-team-fresh-lead-grid {
            grid-template-columns: 1fr;
        }
    }

    .sm-team-insights-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
    }

    .sm-team-insight-card {
        min-width: 0;
        border: 1px solid #b9cec0;
        border-radius: 6px;
        background: #ffffff;
        box-shadow: none;
        overflow: hidden;
    }

    .sm-team-insight-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 12px 14px;
        border-bottom: 1px solid #b9cec0;
        background: #edf7f0;
    }

    .sm-team-insight-head h3,
    .sm-team-insight-head span { margin: 0; }
    .sm-team-insight-head h3 { color: #002B45; font-size: 0.96rem; font-weight: 750; }
    .sm-team-insight-head span { color: #557084; font-size: 0.76rem; font-weight: 650; }

    .sm-team-source-content {
        display: grid;
        grid-template-columns: 174px minmax(0, 1fr);
        align-items: center;
        gap: 18px;
        min-height: 230px;
        padding: 18px;
    }

    .sm-team-source-donut {
        position: relative;
        width: 150px;
        height: 150px;
        display: grid;
        place-items: center;
        margin: auto;
        border-radius: 50%;
    }

    .sm-team-source-donut::after {
        content: '';
        position: absolute;
        inset: 34px;
        border-radius: 50%;
        background: #ffffff;
    }

    .sm-team-source-total {
        position: relative;
        z-index: 1;
        color: #002B45;
        text-align: center;
    }

    .sm-team-source-total strong,
    .sm-team-source-total span { display: block; }
    .sm-team-source-total strong { font-size: 1.4rem; font-weight: 800; }
    .sm-team-source-total span { color: #63766a; font-size: 0.68rem; font-weight: 700; text-transform: uppercase; }

    .sm-team-source-list { display: grid; gap: 7px; }
    .sm-team-source-row {
        display: grid;
        grid-template-columns: 9px minmax(0, 1fr) auto;
        align-items: center;
        gap: 8px;
        padding: 7px 0;
        border-bottom: 1px solid #e4eee7;
    }
    .sm-team-source-row:last-child { border-bottom: 0; }
    .sm-team-source-dot { width: 9px; height: 9px; border-radius: 50%; }
    .sm-team-source-label { overflow: hidden; color: #315044; font-size: 0.8rem; font-weight: 650; text-overflow: ellipsis; white-space: nowrap; }
    .sm-team-source-count { color: #002B45; font-size: 0.82rem; font-weight: 800; }

    .sm-team-availability-list { max-height: 230px; overflow: auto; }
    .sm-team-availability-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        align-items: center;
        gap: 12px;
        padding: 10px 14px;
        border-bottom: 1px solid #d5e2d9;
    }
    .sm-team-availability-row:nth-child(even) { background: #f7fbf8; }
    .sm-team-availability-name { color: #002B45; font-size: 0.84rem; font-weight: 750; }
    .sm-team-availability-role { margin-top: 2px; color: #6f7b74; font-size: 0.7rem; font-weight: 600; }
    .sm-team-availability-control {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border: 1px solid #b8d4c2;
        border-radius: 4px;
        padding: 7px 10px;
        background: #f4fbf6;
        cursor: pointer;
    }
    .sm-team-availability-control:hover { background: #e8f6ed; }
    .sm-team-availability-control:disabled { cursor: wait; opacity: 0.6; }
    .sm-team-availability-label { min-width: 54px; color: #17633c; font-size: 0.72rem; font-weight: 750; text-align: right; }
    .sm-team-availability-label.is-off { color: #bd4c09; }
    .sm-team-availability-toggle {
        appearance: none;
        width: 34px;
        height: 18px;
        margin: 0;
        border: 1px solid #a7c9b3;
        border-radius: 2px;
        background: #217346;
        cursor: pointer;
    }
    .sm-team-availability-toggle::before {
        content: '';
        display: block;
        width: 12px;
        height: 12px;
        margin: 2px 2px 2px auto;
        border-radius: 1px;
        background: #ffffff;
    }
    .sm-team-availability-toggle:checked { border-color: #e5b278; background: #c76b16; }
    .sm-team-availability-toggle:checked::before { margin: 2px auto 2px 2px; }
    .sm-team-availability-toggle:disabled { cursor: wait; opacity: 0.6; }

    .sm-lead-availability-modal {
        position: fixed;
        inset: 0;
        z-index: 1090;
        display: none;
        place-items: center;
        padding: 18px;
        background: rgba(15, 23, 42, 0.48);
    }
    .sm-lead-availability-modal.is-open { display: grid; }
    .sm-lead-availability-dialog {
        width: min(100%, 480px);
        overflow: hidden;
        border: 1px solid #cbdad0;
        border-radius: 8px;
        background: #ffffff;
        box-shadow: 0 24px 60px rgba(15, 23, 42, 0.22);
    }
    .sm-lead-availability-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        padding: 20px 22px 16px;
        border-bottom: 1px solid #dce8df;
    }
    .sm-lead-availability-head h3 { margin: 0; color: #002b45; font-size: 1.05rem; font-weight: 800; }
    .sm-lead-availability-head p { margin: 5px 0 0; color: #64756a; font-size: 0.8rem; }
    .sm-lead-availability-close {
        width: 34px;
        height: 34px;
        border: 1px solid #d8e3dc;
        border-radius: 4px;
        background: #f7faf8;
        color: #345245;
        cursor: pointer;
    }
    .sm-lead-availability-form { display: grid; gap: 16px; padding: 20px 22px 22px; }
    .sm-lead-availability-form [hidden] { display: none !important; }
    .sm-lead-availability-field { display: grid; gap: 7px; }
    .sm-lead-availability-field label { color: #29483a; font-size: 0.76rem; font-weight: 750; }
    .sm-lead-availability-field select,
    .sm-lead-availability-field input,
    .sm-lead-availability-field textarea {
        width: 100%;
        border: 1px solid #b9ccc0;
        border-radius: 4px;
        background: #ffffff;
        color: #173b2b;
        font: inherit;
    }
    .sm-lead-availability-field select { min-height: 42px; padding: 0 12px; }
    .sm-lead-availability-field input { min-height: 42px; padding: 0 12px; }
    .sm-lead-availability-field textarea { min-height: 92px; padding: 11px 12px; resize: vertical; }
    .sm-lead-availability-field select:focus,
    .sm-lead-availability-field input:focus,
    .sm-lead-availability-field textarea:focus { border-color: #217346; outline: 2px solid rgba(33, 115, 70, 0.14); }
    .sm-lead-availability-window { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .sm-lead-availability-note {
        margin: 0;
        border-left: 3px solid #217346;
        padding: 9px 11px;
        background: #f2f9f4;
        color: #405b4d;
        font-size: 0.76rem;
        line-height: 1.45;
    }
    .sm-lead-availability-error {
        margin: 0;
        border: 1px solid #fecaca;
        border-radius: 4px;
        padding: 9px 11px;
        background: #fff7f7;
        color: #b42318;
        font-size: 0.76rem;
        font-weight: 650;
    }
    .sm-lead-availability-actions { display: flex; justify-content: flex-end; gap: 9px; padding-top: 2px; }
    .sm-lead-availability-actions button {
        min-height: 40px;
        border-radius: 4px;
        padding: 0 16px;
        font-size: 0.8rem;
        font-weight: 750;
        cursor: pointer;
    }
    .sm-lead-availability-cancel { border: 1px solid #cbd8cf; background: #ffffff; color: #395246; }
    .sm-lead-availability-save { border: 1px solid #145f36; background: #217346; color: #ffffff; }
    .sm-lead-availability-save.is-on { border-color: #166534; background: #166534; }
    .sm-lead-availability-save:disabled { cursor: wait; opacity: 0.65; }

    @media (max-width: 900px) {
        .sm-team-insights-grid { grid-template-columns: 1fr; }
    }

    @media (max-width: 480px) {
        .sm-team-source-content { grid-template-columns: 1fr; }
    }

    .sm-team-action-link.is-pod {
        border-color: #9ed8c1;
        color: #047857;
        background: #f0fdf4;
    }

    .sm-team-action-link.is-overdue {
        border-color: #fed7aa;
        color: #c2410c;
        background: #fff7ed;
    }

    .sm-team-workload-modal {
        position: fixed;
        inset: 0;
        z-index: 1080;
        display: none;
        align-items: stretch;
        justify-content: flex-end;
        background: rgba(2, 43, 69, 0.26);
    }

    .sm-team-workload-modal.is-open { display: flex; }

    .sm-team-workload-panel {
        width: min(490px, 100%);
        height: 100%;
        overflow: auto;
        padding: 24px;
        background: #ffffff;
        box-shadow: -18px 0 44px rgba(2, 43, 69, 0.17);
    }

    .sm-team-workload-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        padding-bottom: 18px;
        border-bottom: 1px solid #e5ece8;
    }

    .sm-team-workload-head h3,
    .sm-team-workload-head p { margin: 0; }
    .sm-team-workload-head h3 { color: #002B45; font-size: 1.18rem; font-weight: 800; }
    .sm-team-workload-head p { margin-top: 5px; color: #68746d; font-size: 0.82rem; font-weight: 650; }

    .sm-team-workload-close {
        width: 36px;
        height: 36px;
        flex: 0 0 36px;
        border: 1px solid #dce7e1;
        border-radius: 10px;
        color: #355064;
        background: #ffffff;
        cursor: pointer;
    }

    .sm-team-workload-section { padding: 20px 0; border-bottom: 1px solid #e5ece8; }
    .sm-team-workload-section h4 { margin: 0 0 12px; color: #002B45; font-size: 0.82rem; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; }
    .sm-team-workload-summary { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
    .sm-team-workload-stat { padding: 12px; border: 1px solid #e5ece8; border-radius: 10px; background: #fbfdfb; }
    .sm-team-workload-stat span,
    .sm-team-workload-stat strong { display: block; }
    .sm-team-workload-stat span { color: #68746d; font-size: 0.68rem; font-weight: 750; text-transform: uppercase; }
    .sm-team-workload-stat strong { margin-top: 5px; color: #002B45; font-size: 1.05rem; font-weight: 800; }
    .sm-team-workload-list { display: grid; gap: 9px; }
    .sm-team-workload-item { display: block; padding: 12px; border: 1px solid #e5ece8; border-radius: 10px; color: inherit; background: #ffffff; text-decoration: none; }
    .sm-team-workload-item strong,
    .sm-team-workload-item span,
    .sm-team-workload-item small { display: block; }
    .sm-team-workload-item strong { color: #002B45; font-size: 0.88rem; }
    .sm-team-workload-item span { margin-top: 4px; color: #30556f; font-size: 0.78rem; font-weight: 650; }
    .sm-team-workload-item small { margin-top: 4px; color: #75827b; font-size: 0.74rem; }
    .sm-team-workload-empty { color: #75827b; font-size: 0.86rem; font-weight: 650; }

    .sm-team-stage-list {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-start;
        gap: 6px;
        min-width: 0;
    }

    .sm-team-stage-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: 1px solid rgba(18, 73, 49, 0.12);
        border-radius: 999px;
        padding: 5px 8px;
        color: #082c1d;
        background: #ffffff;
        font-size: 0.72rem;
        font-weight: 600;
        white-space: nowrap;
    }

    .sm-team-stage-chip strong {
        color: #0b5f3a;
        font-weight: 750;
    }

    .sm-team-temperature-card {
        grid-column: 1 / -1;
    }

    .sm-temperature-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 14px;
        padding: 18px 20px 20px;
    }

    .sm-temperature-user-card,
    .sm-team-empty-card {
        border: 1px solid rgba(0, 107, 166, 0.1);
        border-radius: 18px;
        background: #fbfdfb;
    }

    .sm-temperature-user-card {
        padding: 14px;
    }

    .sm-team-empty-card {
        grid-column: 1 / -1;
        padding: 28px 16px;
        color: #7a8780;
        text-align: center;
        font-weight: 600;
    }

    .sm-temperature-user-head {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 12px;
    }

    .sm-temperature-user-head strong {
        display: block;
        color: #002B45;
        font-size: 0.98rem;
        font-weight: 800;
    }

    .sm-temperature-user-head small {
        color: #6f7b74;
        font-size: 0.78rem;
        font-weight: 650;
    }

    .sm-temperature-metrics {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 8px;
    }

    .sm-temp-pill {
        min-width: 0;
        border: 1px solid #e5ece8;
        border-radius: 14px;
        padding: 10px;
        background: #ffffff;
    }

    .sm-temp-pill span {
        display: block;
        color: #68746d;
        font-size: 0.68rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .sm-temp-pill strong {
        display: block;
        margin-top: 3px;
        color: #002B45;
        font-size: 1.35rem;
        font-weight: 850;
        line-height: 1;
    }

    .sm-temp-hot {
        border-color: #fed7aa;
        background: #fff7ed;
    }

    .sm-temp-warm {
        border-color: #fde68a;
        background: #fffbeb;
    }

    .sm-temp-cold {
        border-color: #bfdbfe;
        background: #eff6ff;
    }

    @media (max-width: 640px) {
        .sm-temperature-metrics {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 1180px) {
        .sm-team-kpi-grid {
            grid-template-columns: repeat(3, minmax(150px, 1fr));
        }

        .sm-team-dashboard-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 767px) {
        #sm-dashboard-team-panel {
            display: none !important;
        }

        .asm-dialer-mobile-launch {
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .sm-mobile-section-title {
            display: block;
        }

        .sm-mobile-quick-actions {
            display: grid;
        }

        body.sm-dashboard-mobile-inline-team #sm-dashboard-team-panel {
            display: block !important;
            margin-top: 18px;
        }

        body.sm-dashboard-mobile-inline-team #sm-dashboard-team-panel .sm-team-dashboard-header {
            padding: 16px;
            border-radius: 6px;
            align-items: flex-start;
            flex-direction: column;
        }

        body.sm-dashboard-mobile-inline-team #sm-dashboard-team-panel .sm-team-dashboard-actions {
            width: 100%;
            justify-content: stretch;
        }

        body.sm-dashboard-mobile-inline-team #sm-dashboard-team-panel .sm-team-kpi-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        body.sm-dashboard-mobile-inline-team #sm-dashboard-team-panel .sm-team-range-select,
        body.sm-dashboard-mobile-inline-team #sm-dashboard-team-panel .sm-team-refresh-btn {
            flex: 1 1 0;
        }

        body.sm-dashboard-mobile-inline-team #sm-dashboard-team-panel .sm-team-custom-range {
            width: 100%;
        }

        body.sm-dashboard-mobile-inline-team #sm-dashboard-team-panel .sm-team-date-field {
            flex: 1 1 0;
        }

        body.sm-dashboard-mobile-inline-team #sm-dashboard-team-panel .sm-team-date-input {
            width: 100%;
        }
    }

    /* CRM green theme overrides for Senior Manager dashboard. */
    :root {
        --sm-crm-dark: #063A1C;
        --sm-crm: #205A44;
        --sm-crm-mid: #007A4D;
        --sm-crm-soft: #ECFDF3;
        --sm-crm-border: #D7E4DC;
        --sm-crm-muted: #4A6457;
    }

    .asm-hero-kicker,
    .asm-dashboard-filter-copy strong,
    .achievement-title,
    .sm-temp-pill strong,
    .text-\[\#002B45\],
    .text-\[\#003B5C\] {
        color: var(--sm-crm-dark) !important;
    }

    .stat-value,
    .asm-dashboard-filter-select,
    .asm-dashboard-cache-btn,
    .asm-dashboard-filter-chip,
    .asm-dashboard-filter-status,
    .asm-productivity-badge.pd,
    .text-\[\#006BA6\],
    .text-\[\#004D73\] {
        color: var(--sm-crm) !important;
    }

    .asm-dashboard-mobile-menu,
    .asm-dashboard-filter-bar,
    .asm-dashboard-filter-select,
    .asm-dashboard-cache-btn,
    .asm-dashboard-filter-chip,
    .asm-dashboard-date-input {
        border-color: var(--sm-crm-border) !important;
    }

    .asm-dashboard-cache-btn:hover,
    .asm-productivity-badge.pd,
    .bg-blue-50,
    .bg-blue-100,
    .bg-sky-50,
    .bg-\[\#006BA6\]\/10,
    .bg-\[\#E0F2FE\] {
        background: var(--sm-crm-soft) !important;
    }

    .asm-dashboard-filter-chip.active,
    .asm-dashboard-apply-btn,
    .btn-reject,
    .bg-\[\#002B45\],
    .bg-\[\#006BA6\],
    .bg-blue-600,
    .bg-sky-600,
    .target-card-manager,
    .target-card-team,
    [style*="#002B45"][style*="#006BA6"],
    [style*="#006BA6"][style*="background"],
    [style*="#0073b1"][style*="background"] {
        background: linear-gradient(135deg, var(--sm-crm-dark) 0%, var(--sm-crm) 100%) !important;
        border-color: var(--sm-crm) !important;
    }

    .from-\[\#002B45\],
    .from-\[\#006BA6\],
    .from-sky-600 {
        --tw-gradient-from: var(--sm-crm-dark) var(--tw-gradient-from-position) !important;
    }

    .via-\[\#006BA6\] {
        --tw-gradient-via: var(--sm-crm) var(--tw-gradient-via-position) !important;
    }

    .to-\[\#0EA5E9\],
    .to-\[\#006BA6\] {
        --tw-gradient-to: #2E7D5F var(--tw-gradient-to-position) !important;
    }

    .border-blue-200,
    .border-sky-200,
    [style*="#B7E0F4"],
    [style*="#CDE5F5"] {
        border-color: var(--sm-crm-border) !important;
    }

    .asm-outcome-btn-green,
    .asm-outcome-btn-blue {
        background: linear-gradient(135deg, var(--sm-crm-dark) 0%, var(--sm-crm) 100%) !important;
    }

    .hover\:bg-\[\#006BA6\]:hover,
    .hover\:bg-blue-700:hover {
        background: var(--sm-crm) !important;
    }

    .asm-hero-copy h2,
    .asm-hero-copy h2 span,
    .asm-productivity-card strong,
    .sm-team-kpi-card strong {
        color: var(--sm-crm-dark) !important;
    }

    .asm-focus-panel,
    .asm-stat-card,
    .asm-stat-card.total-leads-card,
    .stats-grid > .dashboard-card,
    .target-card-manager,
    .target-card-team,
    .sm-team-member-card,
    [style*="linear-gradient(135deg, #002B45"],
    [style*="linear-gradient(180deg, #002B45"],
    [style*="background: linear-gradient(135deg, #002B45"],
    [style*="background:${cardGradient}"] {
        background: linear-gradient(135deg, var(--sm-crm-dark) 0%, var(--sm-crm) 58%, #2E7D5F 100%) !important;
        border-color: rgba(46, 125, 95, .35) !important;
        box-shadow: 0 18px 36px rgba(6, 58, 28, .14) !important;
    }

    .asm-focus-panel .bg-white\/10,
    .asm-stat-card .stat-icon,
    .dashboard-card .stat-icon {
        background: rgba(255, 255, 255, .16) !important;
        color: #ffffff !important;
    }

    .sm-dashboard-mode-toggle {
        background: rgba(255, 255, 255, .68) !important;
        border-color: rgba(32, 90, 68, .16) !important;
    }

    .sm-dashboard-mode-btn {
        color: var(--sm-crm-dark) !important;
    }

    .sm-dashboard-mode-btn.is-active {
        background: linear-gradient(135deg, var(--sm-crm-dark) 0%, var(--sm-crm) 100%) !important;
        color: #ffffff !important;
        box-shadow: 0 12px 24px rgba(6, 58, 28, .18) !important;
    }

    .sm-dashboard-view-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 12px;
        padding: 12px 16px;
        border: 1px solid #8fbea0;
        border-radius: 6px;
        background: #edf7f0;
    }

    .sm-dashboard-view-toolbar strong {
        display: block;
        color: var(--sm-crm-dark);
        font-size: 1rem;
        font-weight: 800;
    }

    .sm-dashboard-view-toolbar span {
        display: block;
        margin-top: 2px;
        color: var(--sm-crm-muted);
        font-size: .76rem;
        font-weight: 650;
    }

    .sm-dashboard-view-toolbar .sm-dashboard-mode-toggle {
        flex: 0 0 auto;
        padding: 3px;
        border: 1px solid #8fbea0 !important;
        border-radius: 4px;
        background: #fff !important;
    }

    .sm-my-excel-panel .asm-hero {
        grid-template-columns: minmax(0, 1fr) minmax(260px, 34%);
        gap: 0;
        padding: 0;
        overflow: hidden;
        border: 1px solid #b9cec0;
        border-radius: 6px;
        background: #fff;
        box-shadow: none;
    }

    .sm-my-excel-panel .asm-hero-copy,
    .sm-my-excel-panel .asm-hero-side {
        padding: 16px;
    }

    .sm-my-excel-panel .asm-hero-side {
        border-left: 1px solid #d5e2d9;
        background: #f8fbf9;
    }

    .sm-my-excel-panel .asm-dashboard-filter-bar,
    .sm-my-excel-panel .asm-focus-panel,
    .sm-my-excel-panel .asm-favorites-panel,
    .sm-my-excel-panel .asm-productivity-card {
        border: 1px solid #b9cec0 !important;
        border-radius: 4px !important;
        background: #fff !important;
        box-shadow: none !important;
    }

    .sm-my-excel-panel .asm-focus-panel {
        padding: 0;
        overflow: hidden;
        color: var(--sm-crm-dark);
    }

    .sm-my-excel-panel .asm-focus-head {
        min-height: 44px;
        padding: 8px 12px;
        border-bottom: 1px solid #b9cec0;
        background: #217346;
    }

    .sm-my-excel-panel .asm-focus-panel .eyebrow {
        color: #fff !important;
        font-weight: 800;
    }

    .sm-my-excel-panel .asm-focus-dialer-btn {
        min-height: 30px;
        border-radius: 3px;
    }

    .sm-my-excel-panel .asm-mini-metrics {
        grid-template-columns: repeat(6, minmax(0, 1fr));
        gap: 0;
        margin: 0;
    }

    .sm-my-excel-panel .asm-mini-metrics > a {
        min-height: 74px;
        padding: 12px;
        border: 0;
        border-right: 1px solid #d5e2d9;
        border-radius: 0;
        background: #fff;
    }

    .sm-my-excel-panel .asm-mini-metrics > a:last-child {
        border-right: 0;
    }

    .sm-my-excel-panel .asm-mini-metrics strong {
        color: #17633c;
        font-size: 1.25rem;
    }

    .sm-my-excel-panel .asm-mini-metrics span {
        color: #567164;
        font-size: .72rem;
        font-weight: 700;
    }

    .sm-my-excel-panel .asm-mini-metric-link:hover,
    .sm-my-excel-panel .asm-mini-metric-link:focus-visible {
        background: #edf7f0;
        transform: none;
    }

    .sm-my-excel-panel .asm-favorites-panel {
        min-height: 100%;
        padding: 14px;
    }

    .sm-my-excel-panel .stats-grid {
        gap: 0 !important;
        overflow: hidden;
        border: 1px solid #b9cec0;
        border-radius: 6px;
        background: #fff;
    }

    .sm-my-excel-panel .stats-grid > .dashboard-card {
        min-height: 112px;
        border: 0 !important;
        border-right: 1px solid #d5e2d9 !important;
        border-radius: 0 !important;
        background: #fff !important;
        box-shadow: none !important;
        color: var(--sm-crm-dark) !important;
    }

    .sm-my-excel-panel .stats-grid .stat-kicker,
    .sm-my-excel-panel .stats-grid .stat-label {
        color: #567164 !important;
    }

    .sm-my-excel-panel .stats-grid .stat-value {
        color: #17633c !important;
    }

    .sm-my-excel-panel .stats-grid .stat-icon {
        background: #edf7f0 !important;
        color: #217346 !important;
    }

    @media (max-width: 1100px) {
        .sm-my-excel-panel .asm-mini-metrics {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @media (max-width: 767px) {
        .sm-dashboard-view-toolbar {
            position: sticky;
            top: 0;
            z-index: 35;
            padding: 9px 10px;
        }

        .sm-dashboard-view-toolbar span {
            display: none;
        }

        .sm-my-excel-panel .asm-hero {
            grid-template-columns: 1fr;
        }

        .sm-my-excel-panel .asm-hero-side {
            border-top: 1px solid #d5e2d9;
            border-left: 0;
        }

        .sm-my-excel-panel .asm-mini-metrics {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    .asm-action-btn.primary,
    .asm-dashboard-filter-chip.active,
    .asm-dashboard-apply-btn,
    .asm-focus-dialer-btn {
        background: linear-gradient(135deg, var(--sm-crm-dark) 0%, var(--sm-crm) 100%) !important;
        border-color: var(--sm-crm) !important;
        color: #ffffff !important;
    }

    .asm-dashboard-cache-btn:hover,
    .asm-productivity-card {
        background: #ffffff !important;
        border-color: var(--sm-crm-border) !important;
        box-shadow: 0 10px 24px rgba(6, 58, 28, .08) !important;
    }

    .asm-dashboard-filter-chip,
    .asm-dashboard-filter-select,
    .asm-dashboard-date-input {
        color: var(--sm-crm-dark) !important;
    }
</style>
@endpush

@section('content')
@php($isSeniorManagerDashboard = auth()->user()?->isSeniorManager())
@php($canUseAsmDialer = auth()->user()?->isAssistantSalesManager() || auth()->user()?->isSeniorManager())
@if($canUseAsmDialer)
    <button type="button" class="asm-dialer-mobile-launch" data-asm-dialer-open aria-label="Open calling mode">
        <i class="fas fa-phone-alt"></i>
    </button>
@endif

@if($isSeniorManagerDashboard)
    <div class="sm-dashboard-view-toolbar">
        <div>
            <strong>Performance Dashboard</strong>
            <span id="smDashboardViewLabel">My performance</span>
        </div>
        <div class="sm-dashboard-mode-toggle" role="tablist" aria-label="Dashboard mode">
            <button type="button" class="sm-dashboard-mode-btn is-active" data-sm-dashboard-tab="my" aria-selected="true">My</button>
            <button type="button" class="sm-dashboard-mode-btn" data-sm-dashboard-tab="team" aria-selected="false">Team</button>
        </div>
    </div>
@endif

<div id="sm-dashboard-my-panel" class="sm-dashboard-tab-panel sm-my-excel-panel" data-sm-dashboard-panel="my">
<section class="asm-hero">
    <div class="asm-hero-copy">
        <div class="asm-hero-mobile-top">
            <div class="asm-hero-kicker">Dashboard</div>
            <div class="asm-dashboard-filter-bar">
                <div class="asm-dashboard-filter-copy">
                    <strong>Dashboard Date Filter</strong>
                    <span>Ye range top cards aur niche ke sections sab par apply hogi.</span>
                </div>
                <div class="asm-dashboard-filter-tools">
                    @include('components.global-notification-center')
                    <div class="asm-dashboard-filter-actions">
                        <button type="button" class="asm-dashboard-filter-chip" data-dashboard-filter="today">Today</button>
                        <button type="button" class="asm-dashboard-filter-chip" data-dashboard-filter="this_week">This Week</button>
                        <button type="button" class="asm-dashboard-filter-chip" data-dashboard-filter="this_month">This Month</button>
                        <button type="button" class="asm-dashboard-filter-chip" data-dashboard-filter="custom">Custom</button>
                        <button type="button" class="asm-dashboard-filter-chip asm-dashboard-cache-btn" data-dashboard-clear-cache>Clear Cache</button>
                    </div>
                </div>
                <div class="asm-dashboard-filter-mobile">
                    <div class="asm-dashboard-filter-mobile-wrap">
                        <select id="asmDashboardFilterSelect" class="asm-dashboard-filter-select">
                            <option value="today">Today</option>
                            <option value="this_week">This Week</option>
                            <option value="this_month">This Month</option>
                            <option value="custom">Custom Range</option>
                        </select>
                        <button type="button" class="asm-dashboard-cache-btn" data-dashboard-clear-cache>Clear Cache</button>
                    </div>
                </div>
            </div>
        </div>
        <h2>{{ $greeting }}, <span>{{ auth()->user()->name }}</span></h2>
        <div class="asm-dashboard-custom-range" id="asmDashboardCustomRange">
            <input type="date" id="asmDashboardStartDate" class="asm-dashboard-date-input">
            <input type="date" id="asmDashboardEndDate" class="asm-dashboard-date-input">
            <button type="button" class="asm-dashboard-apply-btn" id="asmDashboardApplyRange">Apply Range</button>
            <span class="asm-dashboard-filter-status" id="asmDashboardFilterStatus"></span>
        </div>
        <div class="asm-focus-panel" id="asmTodayFocusPanel" style="margin-top: 22px;">
            <div class="asm-focus-head">
                <div class="eyebrow">My Work</div>
                @if($canUseAsmDialer)
                    <button type="button" class="asm-focus-dialer-btn" data-asm-dialer-open>
                        <i class="fas fa-phone-alt"></i>
                        <span>Dialer</span>
                    </button>
                @endif
            </div>
            <div class="asm-mini-metrics">
                <a href="{{ route('sales-manager.leads', ['fresh_today' => 1]) }}" class="asm-mini-metric-link" id="asmTodayFocusFreshLeadsCard">
                    <strong id="freshLeadsHero">0</strong><span>Fresh Leads</span>
                </a>
                <a href="{{ route('sales-manager.tasks', ['status' => 'overdue']) }}" class="asm-mini-metric-link" id="asmTodayFocusOverdueCard">
                    <strong id="overdueTasksHero">0</strong><span>Overdue</span>
                </a>
                <a href="{{ route('sales-manager.tasks', ['date_filter' => 'custom', 'custom_date' => now()->subDay()->format('Y-m-d')]) }}" class="asm-mini-metric-link" id="asmTodayFocusPreviousOverdueCard">
                    <strong id="previousDayOverdueHero">0</strong><span>Prev Day Overdue</span>
                </a>
                <a href="{{ route('sales-manager.meetings', ['date_filter' => 'today']) }}" class="asm-mini-metric-link" id="asmTodayFocusMeetingsCard">
                    <strong id="todayMeetingsHero">0</strong>
                    <span>Meetings</span>
                </a>
                <a href="{{ route('sales-manager.site-visits', ['date_filter' => 'today']) }}" class="asm-mini-metric-link" id="asmTodayFocusVisitsCard">
                    <strong id="todayVisitsHero">0</strong>
                    <span>Site Visits</span>
                </a>
                <a href="{{ route('sales-manager.tasks', ['focus' => 'followups', 'date_filter' => 'today']) }}" class="asm-mini-metric-link" id="asmTodayFocusFollowupsCard">
                    <strong id="todayFollowupsHero">0</strong>
                    <span>Follow-ups</span>
                </a>
            </div>
        </div>
        @if($todayProductivity)
            <div class="asm-productivity-card" title="{{ $todayProductivity['title'] }}">
                <div>
                    <strong>Today Productivity</strong>
                    <span>Assigned Visits: {{ $todayProductivity['assigned'] }} | Verified Visits: {{ $todayProductivity['verified'] }}</span>
                </div>
                <div class="asm-productivity-badge {{ $todayProductivity['status'] }}">{{ $todayProductivity['label'] }}</div>
            </div>
        @endif
    </div>
    <div class="asm-hero-side" id="asmFavoritesPanelWrap">
        <div class="asm-favorites-panel" id="asmFavoritesPanel">
            <div>
                <div class="eyebrow">Favorites</div>
                <h3>Favorite Leads</h3>
                <div class="asm-favorites-subtitle">Quick access for important leads</div>
            </div>
            <div id="favoriteLeadsList" class="favorite-leads-list">
                <div class="favorite-leads-empty">No favorite leads yet</div>
            </div>
        </div>
    </div>
</section>
@if(auth()->user()?->hasAttendanceRolloutEnabled())
    @include('attendance._widget', ['attendanceWidgetMode' => 'sales_manager_dashboard'])
@endif
@if($isSeniorManagerDashboard)
    <div class="sm-mobile-section-title">
        <span>Action Required</span>
        <strong>Pending work aur team queue</strong>
    </div>
@endif
<div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-6 mb-6 stats-grid asm-stats-grid" id="asmStatsGrid">
    <!-- Stats Cards - Reordered for mobile: Leads Received, Today Prospects, Pending Verifications, Over Due Task, Team Members -->

    <!-- 1. Total Leads -->
    <a href="{{ route('sales-manager.leads') }}" class="rounded-lg shadow p-6 dashboard-card asm-stat-card asm-stat-link total-leads-card" id="asmStatLeadsReceived">
        <div class="stat-head">
            <div>
                <p class="stat-kicker">Portfolio</p>
                <h3 class="stat-value mt-1" id="assignedLeads">0</h3>
                <p class="stat-label">Total Leads</p>
            </div>
            <span class="stat-icon"><i class="fas fa-user-friends"></i></span>
        </div>
    </a>

    <!-- 2. Today's Prospects -->
    <a href="{{ route('sales-manager.prospects', ['created_today' => 1]) }}" class="rounded-lg shadow p-6 dashboard-card asm-stat-card asm-stat-link" id="asmStatTodaysProspects">
        <div class="stat-head">
            <div>
                <p class="stat-kicker">Today</p>
                <h3 class="stat-value mt-1" id="todayProspects">0</h3>
                <p class="stat-label">Today's Prospects</p>
            </div>
            <span class="stat-icon"><i class="fas fa-star"></i></span>
        </div>
    </a>

    <!-- 3. Pending Verifications -->
    <a href="{{ route('sales-manager.prospects', ['verification_status' => 'pending_verification']) }}" class="rounded-lg shadow p-6 dashboard-card asm-stat-card asm-stat-link" id="asmStatPendingVerifications">
        <div class="stat-head">
            <div>
                <p class="stat-kicker">Approval Queue</p>
                <h3 class="stat-value mt-1" id="pendingVerifications">0</h3>
                <p class="stat-label">Pending Verifications</p>
            </div>
            <span class="stat-icon"><i class="fas fa-shield-alt"></i></span>
        </div>
    </a>

    <!-- 4. Over Due Task -->
    <a href="{{ route('sales-manager.tasks', ['status' => 'overdue']) }}" class="rounded-lg shadow p-6 dashboard-card asm-stat-card asm-stat-link" id="asmStatOverdueTasks">
        <div class="stat-head">
            <div>
                <p class="stat-kicker">Attention</p>
                <h3 class="stat-value mt-1" id="overdueTasks">0</h3>
                <p class="stat-label">Overdue Tasks</p>
            </div>
            <span class="stat-icon"><i class="fas fa-clock"></i></span>
        </div>
    </a>

    <a href="{{ route('sales-manager.tasks', ['date_filter' => 'custom', 'custom_date' => now()->subDay()->format('Y-m-d')]) }}" class="rounded-lg shadow p-6 dashboard-card asm-stat-card asm-stat-link" id="asmStatPreviousDayOverdueTasks">
        <div class="stat-head">
            <div>
                <p class="stat-kicker">Carry Forward</p>
                <h3 class="stat-value mt-1" id="previousDayOverdueTasks">0</h3>
                <p class="stat-label">Previous Day Overdue</p>
            </div>
            <span class="stat-icon"><i class="fas fa-history"></i></span>
        </div>
    </a>

    <!-- 5. Team Members (hidden on mobile) -->
    <a href="{{ route('sales-manager.team') }}" class="rounded-lg shadow p-6 dashboard-card team-members-card asm-stat-card asm-stat-link" id="asmStatTeamMembers">
        <div class="stat-head">
            <div>
                <p class="stat-kicker">Coverage</p>
                <h3 class="stat-value mt-1" id="teamMembersCount">0</h3>
                <p class="stat-label">Team Members</p>
            </div>
            <span class="stat-icon"><i class="fas fa-users"></i></span>
        </div>
    </a>

    <!-- 6. Pending Tasks (kept for desktop) -->
    <a href="{{ route('sales-manager.tasks', ['status' => 'pending']) }}" class="rounded-lg shadow p-6 dashboard-card asm-stat-card asm-stat-link is-hidden" id="asmStatPendingTasks">
        <div class="stat-head">
            <div>
                <p class="stat-kicker">Backlog</p>
                <h3 class="stat-value mt-1" id="pendingTasks">0</h3>
                <p class="stat-label">Pending Tasks</p>
            </div>
            <span class="stat-icon"><i class="fas fa-list-check"></i></span>
        </div>
    </a>

    <!-- 7. No response yet -->
</div>

<section class="recent-tasks-mobile" id="asmMobileTaskDigest">
    <div class="mobile-task-stack">
        <article class="mobile-task-panel pending-panel">
            <div class="mobile-task-panel-head">
                <div>
                    <h2 class="mobile-task-panel-title">Pending tasks</h2>
                    <div class="mobile-task-panel-subtitle">Due soon and today&apos;s queue</div>
                </div>
                <span class="mobile-task-panel-count" id="asmPendingMobileCount">0</span>
            </div>
            <div class="mobile-task-list" id="asmPendingMobileList">
                <div class="mobile-task-empty">Loading pending tasks...</div>
            </div>
            <div class="mobile-task-footer">
                <a href="{{ route('sales-manager.tasks', ['status' => 'pending']) }}" class="mobile-task-more" id="asmPendingMobileMore">
                    <span>View all pending</span>
                    <i class="fas fa-chevron-down text-[11px]"></i>
                </a>
            </div>
        </article>

        <article class="mobile-task-panel overdue-panel">
            <div class="mobile-task-panel-head">
                <div>
                    <h2 class="mobile-task-panel-title">Overdue tasks</h2>
                    <div class="mobile-task-panel-subtitle">Oldest tasks needing action now</div>
                </div>
                <span class="mobile-task-panel-count" id="asmOverdueMobileCount">0</span>
            </div>
            <div class="mobile-task-list" id="asmOverdueMobileList">
                <div class="mobile-task-empty">Loading overdue tasks...</div>
            </div>
        </article>
    </div>
</section>

<!-- Leads allocated – no response yet (SM/ASM) -->
@if($isSeniorManagerDashboard)
    <nav class="sm-mobile-quick-actions" aria-label="Senior Manager quick actions">
        <a href="{{ route('sales-manager.leads') }}"><i class="fas fa-address-book"></i><span>Leads</span></a>
        <a href="{{ route('sales-manager.tasks') }}"><i class="fas fa-list-check"></i><span>Tasks</span></a>
        <a href="{{ route('sales-manager.meetings') }}"><i class="fas fa-handshake"></i><span>Meetings</span></a>
        <a href="{{ route('sales-manager.site-visits') }}"><i class="fas fa-location-dot"></i><span>Visits</span></a>
        <a href="{{ route('sales-manager.prospects') }}"><i class="fas fa-star"></i><span>Prospects</span></a>
        <a href="{{ route('sales-manager.team') }}"><i class="fas fa-users"></i><span>Team</span></a>
    </nav>
@endif

@if($canUseAsmDialer)
<div id="asmDialerModal" class="modal" style="display:none;">
    <div class="modal-content asm-dialer-card">
        <div class="asm-dialer-head">
            <div>
                <h3>Calling Mode</h3>
                <p>MCube cloud call ke liye customer number dial karein.</p>
            </div>
            <button type="button" class="asm-dialer-close" data-asm-dialer-close aria-label="Close calling mode">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="asm-dialer-body">
            <div class="asm-dialer-display">
                <span class="asm-dialer-prefix">+91</span>
                <input id="asmDialerPhoneInput" class="asm-dialer-input" type="text" inputmode="none" maxlength="10" autocomplete="off" placeholder="9876543210" readonly aria-label="Dialed phone number">
            </div>
            <div class="asm-dialer-grid" aria-label="Dial pad">
                @foreach([1,2,3,4,5,6,7,8,9] as $digit)
                    <button type="button" class="asm-dialer-key" data-asm-dialer-key="{{ $digit }}">{{ $digit }}</button>
                @endforeach
                <button type="button" class="asm-dialer-key" data-asm-dialer-clear>Clear</button>
                <button type="button" class="asm-dialer-key" data-asm-dialer-key="0">0</button>
                <button type="button" class="asm-dialer-key" data-asm-dialer-backspace><i class="fas fa-backspace"></i></button>
            </div>
            <div class="asm-dialer-actions">
                <button type="button" class="asm-dialer-action" data-asm-dialer-close>Cancel</button>
                <button type="button" class="asm-dialer-action asm-dialer-call" id="asmDialerCallButton">
                    <i class="fas fa-phone-alt"></i>
                    <span>Call Now</span>
                </button>
            </div>
            <div class="asm-dialer-status" id="asmDialerStatus"></div>
            <div class="asm-dialer-recent" id="asmDialerRecentWrap" hidden>
                <div class="asm-dialer-recent-title">Recent dialed</div>
                <div class="asm-dialer-recent-list" id="asmDialerRecentList"></div>
            </div>
        </div>
    </div>
</div>
@endif

<div id="dashboardTaskOutcomeModal" class="modal" style="display:none;">
    <div class="modal-content asm-outcome-modal" style="max-width: 500px;">
        <div class="modal-header">
            <div>
                <h3>Call Outcome</h3>
                <p class="asm-outcome-subtitle">Choose the result of this customer call.</p>
            </div>
            <button class="close-modal" onclick="closeDashboardTaskOutcomeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="asm-outcome-modal-body">
                <div class="asm-outcome-grid">
                    <button type="button" class="asm-outcome-btn asm-outcome-btn-green" onclick="dashboardSelectTaskOutcome('interested')"><i class="fas fa-thumbs-up"></i><span>Interested</span></button>
                    <button type="button" class="asm-outcome-btn asm-outcome-btn-slate" onclick="dashboardSelectTaskOutcome('not_interested')"><i class="fas fa-user-slash"></i><span>Not Interested</span></button>
                    <button type="button" class="asm-outcome-btn asm-outcome-btn-blue" onclick="dashboardSelectTaskOutcome('follow_up')"><i class="fas fa-clock"></i><span>Follow Up</span></button>
                    <button type="button" class="asm-outcome-btn asm-outcome-btn-amber" onclick="dashboardSelectTaskOutcome('cnp')"><i class="fas fa-phone-slash"></i><span>CNP</span></button>
                    <button type="button" class="asm-outcome-btn asm-outcome-btn-red asm-outcome-btn-full" onclick="dashboardSelectTaskOutcome('junk')"><i class="fas fa-trash"></i><span>Junk</span></button>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="dashboardTaskRemarkModal" class="modal" style="display:none;">
    <div class="modal-content" style="max-width: 520px;">
        <div class="modal-header">
            <h3 id="dashboardTaskRemarkTitle">Add Remark</h3>
            <button class="close-modal" onclick="closeDashboardTaskRemarkModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label for="dashboardTaskRemarkInput">Remark <span style="color:#6b7280; font-weight:400;">(optional)</span></label>
                <textarea id="dashboardTaskRemarkInput" rows="4" placeholder="Add context..." style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;"></textarea>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:16px;">
                <button type="button" class="btn-cancel" onclick="closeDashboardTaskRemarkModal()">Cancel</button>
                <button type="button" class="btn-reject" onclick="submitDashboardTaskRemark()">Save</button>
            </div>
        </div>
    </div>
</div>

<div id="dashboardTaskDateTimeModal" class="modal" style="display:none;">
    <div class="modal-content" style="max-width: 520px;">
        <div class="modal-header">
            <h3 id="dashboardTaskDateTimeTitle">Schedule Follow Up</h3>
            <button class="close-modal" onclick="closeDashboardTaskDateTimeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div style="padding: 20px;">
                <p id="dashboardTaskDateTimeText" style="font-size: 14px; color: #666; margin-bottom: 20px;">Choose when the next call should happen:</p>
                <div style="display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; margin-bottom:20px;">
                    <button type="button" class="time-option-btn" onclick="selectDashboardTaskPreset(15, event)" style="padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; background: white;">15 Minutes</button>
                    <button type="button" class="time-option-btn" onclick="selectDashboardTaskPreset(30, event)" style="padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; background: white;">30 Minutes</button>
                    <button type="button" class="time-option-btn" onclick="selectDashboardTaskPreset(60, event)" style="padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; background: white;">1 Hour</button>
                    <button type="button" class="time-option-btn" onclick="selectDashboardTaskPreset(120, event)" style="padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; background: white;">2 Hours</button>
                </div>
                <button type="button" onclick="showDashboardTaskCustomPicker()" style="width:100%; padding:12px 16px; border:2px solid #006BA6; border-radius:8px; background:#E0F2FE; color:#006BA6; font-weight:700; margin-bottom:20px;">Custom Date & Time</button>
                <div id="dashboardTaskCustomPicker" style="display:none; margin-bottom:20px; padding:16px; background:#F7FBFF; border-radius:10px;">
                    <div style="display:grid; gap:14px;">
                        <div>
                            <label style="display:block; font-size:14px; font-weight:600; margin-bottom:6px;">Date</label>
                            <input type="date" id="dashboardTaskDateInput" style="width:100%; padding:10px 14px; border:1px solid #ddd; border-radius:6px;">
                        </div>
                        <div>
                            <label style="display:block; font-size:14px; font-weight:600; margin-bottom:6px;">Time</label>
                            <input type="time" id="dashboardTaskTimeInput" style="width:100%; padding:10px 14px; border:1px solid #ddd; border-radius:6px;">
                        </div>
                    </div>
                </div>
                <div>
                    <label style="display:block; font-size:14px; font-weight:600; margin-bottom:6px;">Remark <span style="color:#6b7280; font-weight:400;">(optional)</span></label>
                    <textarea id="dashboardTaskDateTimeRemark" rows="3" placeholder="Add follow-up or CNP context..." style="width:100%; padding:10px 14px; border:1px solid #ddd; border-radius:6px;"></textarea>
                </div>
            </div>
        </div>
        <div class="modal-footer" style="display:flex; gap:12px; justify-content:flex-end; padding:16px 20px; border-top:1px solid #e0e0e0;">
            <button type="button" onclick="closeDashboardTaskDateTimeModal()" style="padding: 10px 20px; border: 1px solid #ddd; border-radius: 6px; background: white;">Cancel</button>
            <button type="button" id="dashboardTaskDateTimeConfirmBtn" onclick="submitDashboardTaskDateTime()" style="padding:10px 20px; border:none; border-radius:6px; background:#2563eb; color:white; font-weight:700;">Confirm</button>
        </div>
    </div>
</div>

<div id="managerLeadRequirementFormModal" class="modal manager-lead-modal" style="display:none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Lead Form</h3>
            <button class="close-modal" onclick="cancelManagerLeadRequirementForm()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="managerLeadFormContainer">
                <div style="text-align: center; padding: 40px;">
                    <div class="spinner" style="display: inline-block;"></div>
                    <p style="margin-top: 15px; color: #666;">Loading form...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="smNoResponseSection" class="bg-white rounded-lg shadow p-6 mb-6 asm-panel" style="display: none !important;">
    <h2 class="asm-panel-title">
        <i class="fas fa-inbox mr-2 text-[#002B45]"></i>Leads allocated – no response yet
    </h2>
    <p class="asm-panel-subtitle mb-4">Leads assigned to you on which you haven't responded yet.</p>
    <div class="text-sm font-medium text-gray-600 mb-4" id="smLeadsPendingRangeLabel">Current filter: Today</div>
    <div class="overflow-x-auto rounded-lg border border-gray-200">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Lead name</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Phone</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Assigned at</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Action</th>
                </tr>
            </thead>
            <tbody id="sm-leads-pending-response-tbody" class="bg-white divide-y divide-gray-200">
                <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">Loading...</td></tr>
            </tbody>
        </table>
    </div>
</div>

@if($isSeniorManagerDashboard)
    <div class="sm-mobile-section-title">
        <span>Targets + Incentive</span>
        <strong>Goals, achievement aur payout status</strong>
    </div>
@endif
<!-- Target vs Achievements Section -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Manager's Own Targets -->
    <div class="rounded-xl shadow-lg p-6 target-card-manager" id="managerTargetsSection" style="background: linear-gradient(135deg, #002B45 0%, #006BA6 100%);">
        <h2 class="text-xl font-bold text-white mb-6 flex items-center" id="managerTargetsSectionTitle">
            <i class="fas fa-bullseye mr-3 text-white"></i>My Targets vs Achievements
        </h2>
        <div class="space-y-5">
            <!-- Meetings -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center">
                        <i class="fas fa-calendar-alt mr-3 text-white text-lg"></i>
                        <span class="text-sm font-medium text-white" id="managerMeetingsLabel">Meetings</span>
                    </div>
                    <span class="text-sm font-semibold text-white" id="managerMeetingsProgress">0 / 0 (0%)</span>
                </div>
                <div class="w-full bg-gray-300 bg-opacity-30 rounded-full h-2 mt-2">
                    <div class="bg-white h-2 rounded-full transition-all" id="managerMeetingsBar" style="width: 0%"></div>
                </div>
            </div>
            <!-- Visits -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center">
                        <i class="fas fa-map-marker-alt mr-3 text-white text-lg"></i>
                        <span class="text-sm font-medium text-white" id="managerVisitsLabel">Site Visits</span>
                    </div>
                    <span class="text-sm font-semibold text-white" id="managerVisitsProgress">0 / 0 (0%)</span>
                </div>
                <div class="w-full bg-gray-300 bg-opacity-30 rounded-full h-2 mt-2">
                    <div class="bg-white h-2 rounded-full transition-all" id="managerVisitsBar" style="width: 0%"></div>
                </div>
            </div>
            <!-- Closers -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center">
                        <i class="fas fa-bullseye mr-3 text-white text-lg"></i>
                        <span class="text-sm font-medium text-white" id="managerClosersLabel">Closers</span>
                    </div>
                    <span class="text-sm font-semibold text-white" id="managerClosersProgress">0 / 0 (0%)</span>
                </div>
                <div class="w-full bg-gray-300 bg-opacity-30 rounded-full h-2 mt-2">
                    <div class="bg-white h-2 rounded-full transition-all" id="managerClosersBar" style="width: 0%"></div>
                </div>
            </div>
        </div>
        <p class="text-center text-white text-sm mt-6 opacity-90">Set your goals and start tracking!</p>
    </div>

    <!-- Team Targets -->
    <div class="rounded-xl shadow-lg p-6 target-card-team" id="teamTargetsSection" style="background: linear-gradient(135deg, #002B45 0%, #006BA6 100%);">
        <h2 class="text-xl font-bold text-white mb-6 flex items-center" id="teamTargetsSectionTitle">
            <i class="fas fa-users mr-3 text-white"></i>Team Targets vs Achievements
        </h2>
        <div class="space-y-5">
            <!-- Team Meetings -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center">
                        <i class="fas fa-calendar-alt mr-3 text-white text-lg"></i>
                        <span class="text-sm font-medium text-white" id="teamMeetingsLabel">Meetings</span>
                    </div>
                    <span class="text-sm font-semibold text-white" id="teamMeetingsProgress">0 / 0 (0%)</span>
                </div>
                <div class="w-full bg-gray-300 bg-opacity-30 rounded-full h-2 mt-2">
                    <div class="bg-white h-2 rounded-full transition-all" id="teamMeetingsBar" style="width: 0%"></div>
                </div>
            </div>
            <!-- Team Visits -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center">
                        <i class="fas fa-map-marker-alt mr-3 text-white text-lg"></i>
                        <span class="text-sm font-medium text-white" id="teamVisitsLabel">Site Visits</span>
                    </div>
                    <span class="text-sm font-semibold text-white" id="teamVisitsProgress">0 / 0 (0%)</span>
                </div>
                <div class="w-full bg-gray-300 bg-opacity-30 rounded-full h-2 mt-2">
                    <div class="bg-white h-2 rounded-full transition-all" id="teamVisitsBar" style="width: 0%"></div>
                </div>
            </div>
            <!-- Team Closers -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center">
                        <i class="fas fa-bullseye mr-3 text-white text-lg"></i>
                        <span class="text-sm font-medium text-white" id="teamClosersLabel">Closers</span>
                    </div>
                    <span class="text-sm font-semibold text-white" id="teamClosersProgress">0 / 0 (0%)</span>
                </div>
                <div class="w-full bg-gray-300 bg-opacity-30 rounded-full h-2 mt-2">
                    <div class="bg-white h-2 rounded-full transition-all" id="teamClosersBar" style="width: 0%"></div>
                </div>
            </div>
        </div>
        <p class="text-center text-white text-sm mt-6 opacity-90">Set your goals and start tracking!</p>
    </div>
</div>

<!-- Individual Team Member Cards Section -->
<div class="mb-6" id="teamMembersCardsSection">
    <h2 class="text-xl font-bold text-gray-900 mb-4" id="teamMembersSectionTitle">
        <i class="fas fa-users mr-2 text-indigo-600"></i>Team Members Targets vs Achievements
    </h2>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="teamMembersCardsContainer">
        <!-- Individual team member cards will be loaded here -->
        <div class="text-center py-8 text-gray-500">
            <i class="fas fa-spinner fa-spin text-2xl mb-2"></i>
            <p>Loading team members...</p>
        </div>
    </div>
</div>

<!-- Quick Actions (Hidden on Mobile) -->
@if(!auth()->user()->isAssistantSalesManager() && !auth()->user()->isSeniorManager())
<div class="bg-white rounded-lg shadow p-6 quick-actions-section">
    <h2 class="text-xl font-bold text-gray-900 mb-4">Quick Actions</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <a href="{{ route('sales-manager.leads') }}" class="p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-indigo-500 hover:bg-indigo-50 transition-all text-center">
            <i class="fas fa-briefcase text-3xl text-indigo-600 mb-2"></i>
            <p class="font-semibold text-gray-900">View Leads</p>
        </a>
        <a href="{{ route('sales-manager.tasks') }}" class="p-4 border-2 border-dashed border-gray-300 rounded-lg hover:border-orange-500 hover:bg-orange-50 transition-all text-center">
            <i class="fas fa-tasks text-3xl text-orange-600 mb-2"></i>
            <p class="font-semibold text-gray-900">View Tasks</p>
        </a>
    </div>
</div>
@endif

<!-- Incentives Section -->
<div class="relative overflow-hidden rounded-2xl shadow-[0_24px_55px_rgba(0,107,166,0.14)] border border-sky-100/80 mb-6 bg-white" id="incentivesSection">
    <!-- Gradient Header -->
    <div class="relative bg-gradient-to-r from-[#002B45] via-[#006BA6] to-[#0EA5E9] px-6 py-5">
        <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,0.32) 1px, transparent 0); background-size: 22px 22px;"></div>
        <div class="absolute -right-8 -top-8 w-32 h-32 rounded-full bg-[#7de2bc]/15"></div>
        <div class="absolute -right-16 top-4 w-24 h-24 rounded-full bg-white/8"></div>
        <div class="relative flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-white/14 backdrop-blur flex items-center justify-center border border-white/20 shadow-lg">
                    <i class="fas fa-coins text-white text-xl"></i>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-white tracking-tight leading-none">Earn Incentive</h2>
                    <p class="text-xs text-sky-50/85 mt-1">Track your rewards &amp; payouts in real-time</p>
                </div>
            </div>
            <div class="hidden sm:flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-white/12 backdrop-blur border border-white/15 text-white text-[11px] font-semibold">
                    <span class="w-1.5 h-1.5 rounded-full bg-sky-200 animate-pulse"></span> Live
                </span>
            </div>
        </div>
    </div>

    <div class="p-5 sm:p-6 bg-gradient-to-b from-[#E0F2FE] via-[#fbfdfb] to-white">
        <!-- Summary Stat Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4 mb-6">
            <!-- Potential -->
            <div class="relative overflow-hidden rounded-xl p-4 bg-gradient-to-br from-[#E0F2FE] via-[#F0F9FF] to-white border border-sky-200/80 shadow-sm hover:shadow-md transition">
                <div class="absolute -right-4 -top-4 w-20 h-20 rounded-full bg-sky-200/45"></div>
                <div class="absolute right-3 top-3 w-9 h-9 rounded-lg bg-sky-500/10 flex items-center justify-center">
                    <i class="fas fa-bullseye text-sky-700"></i>
                </div>
                <div class="relative">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-sky-700">Potential</span>
                    <div class="text-2xl font-extrabold text-sky-900 mt-1" id="incentivePotential">₹0</div>
                    <p class="text-[11px] text-sky-700/80 mt-1 font-medium" id="incentivePotentialDetails">Target: 0 Closers × ₹0</p>
                </div>
            </div>
            <!-- Pending -->
            <div class="relative overflow-hidden rounded-xl p-4 bg-gradient-to-br from-[#E0F2FE] via-[#F8FBFF] to-white border border-sky-200/80 shadow-sm hover:shadow-md transition">
                <div class="absolute -right-4 -top-4 w-20 h-20 rounded-full bg-sky-200/35"></div>
                <div class="absolute right-3 top-3 w-9 h-9 rounded-lg bg-[#006BA6]/10 flex items-center justify-center">
                    <i class="fas fa-hourglass-half text-[#006BA6]"></i>
                </div>
                <div class="relative">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-[#006BA6]">Pending</span>
                    <div class="text-2xl font-extrabold text-[#002B45] mt-1" id="totalPendingIncentives">₹0</div>
                    <p class="text-[11px] text-[#006BA6]/80 mt-1 font-medium">
                        <span id="pendingIncentivesCount">0</span> awaiting approval
                    </p>
                </div>
            </div>
            <!-- Earned -->
            <div class="relative overflow-hidden rounded-xl p-4 bg-gradient-to-br from-[#E0F2FE] via-[#F0F9FF] to-white border border-sky-200/80 shadow-sm hover:shadow-md transition">
                <div class="absolute -right-4 -top-4 w-20 h-20 rounded-full bg-sky-200/45"></div>
                <div class="absolute right-3 top-3 w-9 h-9 rounded-lg bg-sky-500/10 flex items-center justify-center">
                    <i class="fas fa-wallet text-sky-700"></i>
                </div>
                <div class="relative">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-sky-700">Total Earned</span>
                    <div class="text-2xl font-extrabold text-sky-900 mt-1" id="totalEarnedIncentives">₹0</div>
                    <p class="text-[11px] text-sky-700/80 mt-1 font-medium">
                        <span id="verifiedIncentivesCount">0</span> verified payouts
                    </p>
                </div>
            </div>
        </div>

        <!-- Two-column Lists -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <!-- Pending List -->
            <div class="rounded-xl border border-sky-100 bg-white overflow-hidden shadow-sm">
                <div class="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-[#E0F2FE] to-[#F8FBFF] border-b border-sky-100">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-[#006BA6] animate-pulse"></span>
                        <h3 class="text-sm font-bold text-gray-800">Pending Incentives</h3>
                    </div>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-[#006BA6] text-white" id="pendingIncentivesBadge">0</span>
                </div>
                <div id="pendingIncentivesList" class="p-3 space-y-2 max-h-80 overflow-y-auto">
                    <div class="py-10 text-center">
                        <i class="fas fa-inbox text-3xl text-gray-300 mb-2"></i>
                        <p class="text-gray-400 text-sm">No pending incentives</p>
                    </div>
                </div>
            </div>

            <!-- Earned List -->
            <div class="rounded-xl border border-sky-100 bg-white overflow-hidden shadow-sm">
                <div class="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-[#E0F2FE] to-[#F8FBFF] border-b border-sky-100">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-sky-500"></span>
                        <h3 class="text-sm font-bold text-gray-800">Earned Incentives</h3>
                    </div>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-sky-500 text-white" id="verifiedIncentivesBadge">0</span>
                </div>
                <div id="verifiedIncentivesList" class="p-3 space-y-2 max-h-80 overflow-y-auto">
                    <div class="py-10 text-center">
                        <i class="fas fa-trophy text-3xl text-gray-300 mb-2"></i>
                        <p class="text-gray-400 text-sm">No verified incentives yet</p>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-rose-100 bg-white overflow-hidden shadow-sm">
                <div class="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-rose-50 to-white border-b border-rose-100">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                        <h3 class="text-sm font-bold text-gray-800">Rejected Incentives</h3>
                    </div>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-rose-500 text-white" id="rejectedIncentivesBadge">0</span>
                </div>
                <div id="rejectedIncentivesList" class="p-3 space-y-2 max-h-80 overflow-y-auto">
                    <div class="py-10 text-center">
                        <i class="fas fa-triangle-exclamation text-3xl text-gray-300 mb-2"></i>
                        <p class="text-gray-400 text-sm">No rejected incentives</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

@if($isSeniorManagerDashboard)
    <div class="sm-mobile-section-title">
        <span>Team Performance</span>
        <strong>Response, activity aur lead quality</strong>
    </div>
@endif
<section id="sm-dashboard-team-panel" class="sm-dashboard-tab-panel sm-team-dashboard" data-sm-dashboard-panel="team" hidden>
    <div class="sm-team-dashboard-header">
        <div>
            <h2>Team Performance</h2>
            <p class="sm-team-dashboard-subtitle">Direct team ka workload, response aur lead quality ek jagah.</p>
        </div>
        <div class="sm-team-dashboard-actions">
            <select id="smTeamDashboardRange" class="sm-team-range-select" aria-label="Team dashboard range">
                <option value="today">Today</option>
                <option value="this_week">This Week</option>
                <option value="this_month" selected>This Month</option>
                <option value="custom">Custom Range</option>
            </select>
            <div id="smTeamCustomRange" class="sm-team-custom-range" hidden>
                <label class="sm-team-date-field">
                    <span>From</span>
                    <input type="date" id="smTeamStartDate" class="sm-team-date-input">
                </label>
                <label class="sm-team-date-field">
                    <span>To</span>
                    <input type="date" id="smTeamEndDate" class="sm-team-date-input">
                </label>
            </div>
            <button type="button" id="smTeamDashboardRefresh" class="sm-team-refresh-btn">
                <i class="fas fa-check"></i> Apply
            </button>
        </div>
    </div>

    <div class="sm-team-kpi-grid" id="smTeamKpiGrid">
        <div class="sm-team-kpi-card">
            <span>Team Members</span>
            <strong id="smTeamKpiMembers">-</strong>
            <small>Direct active users</small>
        </div>
        <div class="sm-team-kpi-card">
            <span>Total Assigned</span>
            <strong id="smTeamKpiAssigned">-</strong>
            <small>Selected range</small>
        </div>
        <div class="sm-team-kpi-card">
            <span>Pending Fresh</span>
            <strong id="smTeamKpiPending">-</strong>
            <small>New leads not completed</small>
        </div>
        <div class="sm-team-kpi-card">
            <span>Follow Ups</span>
            <strong id="smTeamKpiFollowups">-</strong>
            <small>Connected/on hold</small>
        </div>
        <div class="sm-team-kpi-card">
            <span>Closers</span>
            <strong id="smTeamKpiClosers">-</strong>
            <small>Closed leads</small>
        </div>
    </div>

    <div class="sm-team-dashboard-grid">
        <article class="sm-team-card sm-team-response-card">
            <div class="sm-team-card-head">
                <h3>Leads Allocated And Average Response</h3>
            </div>
            <div class="sm-team-table-wrap">
                <table class="sm-team-table sm-team-response-table">
                    <thead>
                        <tr>
                            <th>User Name</th>
                            <th>New Leads Not Completed</th>
                            <th>Previous Overdue</th>
                            <th>Today Overdue</th>
                            <th>Avg Response Time</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="smTeamResponseBody">
                        <tr><td colspan="6" class="sm-team-empty-cell">Team data load ho raha hai...</td></tr>
                    </tbody>
                </table>
            </div>
        </article>

        <article class="sm-team-card sm-team-performance-card">
            <div class="sm-team-card-head">
                <h3>Sales Executive Performance</h3>
            </div>
            <div class="sm-team-table-wrap">
                <table class="sm-team-table sm-team-performance-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Assigned</th>
                            <th>Fresh / New</th>
                            <th>Follow Up</th>
                            <th>Meeting</th>
                            <th>Visit</th>
                            <th>Closer</th>
                            <th>Junk</th>
                            <th>Not Interested</th>
                            <th>Stage Breakdown</th>
                        </tr>
                    </thead>
                    <tbody id="smTeamPerformanceBody">
                        <tr><td colspan="10" class="sm-team-empty-cell">Team data load ho raha hai...</td></tr>
                    </tbody>
                </table>
            </div>
        </article>

        <div class="sm-team-insights-grid">
            <article class="sm-team-insight-card">
                <div class="sm-team-insight-head">
                    <h3>Team Lead Sources</h3>
                    <span id="smTeamSourceRange">Selected range</span>
                </div>
                <div id="smTeamSourceBody" class="sm-team-source-content">
                    <div class="sm-team-empty-card">Source data load ho raha hai...</div>
                </div>
            </article>
            <article class="sm-team-insight-card">
                <div class="sm-team-insight-head">
                    <h3>Team Lead Availability</h3>
                    <span>Lead On / Off</span>
                </div>
                <div id="smTeamAvailabilityBody" class="sm-team-availability-list">
                    <div class="sm-team-empty-card">Team availability load ho rahi hai...</div>
                </div>
            </article>
        </div>
    </div>
</section>

<div id="smLeadAvailabilityModal" class="sm-lead-availability-modal" role="dialog" aria-modal="true" aria-labelledby="smLeadAvailabilityTitle">
    <div class="sm-lead-availability-dialog">
        <div class="sm-lead-availability-head">
            <div>
                <h3 id="smLeadAvailabilityTitle">Update Lead Availability</h3>
                <p id="smLeadAvailabilitySubtitle">Team member</p>
            </div>
            <button type="button" class="sm-lead-availability-close" aria-label="Close" onclick="closeSmLeadAvailabilityModal()"><i class="fas fa-times"></i></button>
        </div>
        <form id="smLeadAvailabilityForm" class="sm-lead-availability-form">
            <input type="hidden" id="smLeadAvailabilityUserId">
            <input type="hidden" id="smLeadAvailabilityIsOff">
            <div id="smLeadAvailabilityOffFields" style="display:grid;gap:16px;">
                <div class="sm-lead-availability-field">
                    <label for="smLeadAvailabilityFallback">Send new leads to *</label>
                    <select id="smLeadAvailabilityFallback" required>
                        <option value="">Select fallback user</option>
                    </select>
                </div>
                <div class="sm-lead-availability-field">
                    <label for="smLeadAvailabilityReason">Lead Off reason *</label>
                    <textarea id="smLeadAvailabilityReason" maxlength="500" required placeholder="Enter reason for audit"></textarea>
                </div>
                <div class="sm-lead-availability-field">
                    <label for="smLeadAvailabilityDuration">Lead Off duration *</label>
                    <select id="smLeadAvailabilityDuration">
                        <option value="manual">Until manually enabled</option>
                        <option value="window">Set From and To</option>
                    </select>
                </div>
                <div id="smLeadAvailabilityWindow" class="sm-lead-availability-window" hidden>
                    <div class="sm-lead-availability-field">
                        <label for="smLeadAvailabilityStart">From *</label>
                        <input type="datetime-local" id="smLeadAvailabilityStart">
                    </div>
                    <div class="sm-lead-availability-field">
                        <label for="smLeadAvailabilityEnd">To *</label>
                        <input type="datetime-local" id="smLeadAvailabilityEnd">
                    </div>
                </div>
                <p class="sm-lead-availability-note">While this user is Lead Off, new automatic leads will go to the selected fallback user.</p>
            </div>
            <p id="smLeadAvailabilityOnMessage" class="sm-lead-availability-note" hidden>This user will start receiving new automatic leads again.</p>
            <p id="smLeadAvailabilityError" class="sm-lead-availability-error" role="alert" hidden></p>
            <div class="sm-lead-availability-actions">
                <button type="button" class="sm-lead-availability-cancel" onclick="closeSmLeadAvailabilityModal()">Cancel</button>
                <button type="submit" id="smLeadAvailabilitySave" class="sm-lead-availability-save">Confirm Lead Off</button>
            </div>
        </form>
    </div>
</div>

<div id="smTeamWorkloadModal" class="sm-team-workload-modal" role="dialog" aria-modal="true" aria-labelledby="smTeamWorkloadTitle">
    <aside class="sm-team-workload-panel">
        <div class="sm-team-workload-head">
            <div>
                <h3 id="smTeamWorkloadTitle">Team activity</h3>
                <p id="smTeamWorkloadSubtitle">Direct team member</p>
            </div>
            <button type="button" class="sm-team-workload-close" aria-label="Close" onclick="closeSmTeamWorkloadModal()"><i class="fas fa-times"></i></button>
        </div>
        <div id="smTeamWorkloadContent" style="padding-top:20px;"></div>
    </aside>
</div>
@endsection

@push('scripts')
<script>
    const API_BASE_URL = '{{ url("/api/sales-manager") }}';
    const API_TOKEN = '{{ $api_token ?? session("api_token") ?? "" }}';
    const IS_SENIOR_MANAGER_DASHBOARD = @json($isSeniorManagerDashboard);
    const CAN_USE_ASM_DIALER = @json($canUseAsmDialer);
    const SM_TEAM_LEADS_URL = @json(route('sales-manager.leads'));
    const SM_TEAM_TASKS_URL = @json(route('sales-manager.tasks'));
    const SM_DASHBOARD_TAB_STORAGE_KEY = 'senior-manager-dashboard-tab';
    let smTeamDashboardLoaded = false;
    let smTeamDashboardData = {
        responseRows: [],
        performanceRows: [],
        temperatureRows: [],
        sourceRows: [],
        leadOffRows: [],
        teamCount: 0,
    };

    function bindSeniorManagerDashboardTabs() {
        const buttons = document.querySelectorAll('[data-sm-dashboard-tab]');
        const panels = document.querySelectorAll('[data-sm-dashboard-panel]');

        if (!buttons.length || !panels.length) {
            return;
        }

        const getStoredMode = () => {
            try {
                return localStorage.getItem(SM_DASHBOARD_TAB_STORAGE_KEY) === 'team' ? 'team' : 'my';
            } catch (error) {
                return 'my';
            }
        };

        const storeMode = (mode) => {
            try {
                localStorage.setItem(SM_DASHBOARD_TAB_STORAGE_KEY, mode === 'team' ? 'team' : 'my');
            } catch (error) {}
        };

        const activate = (mode, persist = true) => {
            const safeMode = mode === 'team' ? 'team' : 'my';
            const viewLabel = document.getElementById('smDashboardViewLabel');

            if (persist) {
                storeMode(safeMode);
            }

            if (viewLabel) {
                viewLabel.textContent = safeMode === 'team' ? 'Team performance' : 'My performance';
            }

            buttons.forEach((button) => {
                const isActive = button.dataset.smDashboardTab === safeMode;
                button.classList.toggle('is-active', isActive);
                button.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });

            panels.forEach((panel) => {
                panel.hidden = panel.dataset.smDashboardPanel !== safeMode;
            });

            if (safeMode === 'team') {
                loadSeniorManagerTeamDashboard();
            }
        };

        buttons.forEach((button) => {
            button.addEventListener('click', () => activate(button.dataset.smDashboardTab));
        });

        window.addEventListener('resize', () => {
            if (window.innerWidth < 768) {
                activate('my', false);
            }
            syncSeniorManagerMobileDashboard();
        });

        activate(getStoredMode(), false);
        syncSeniorManagerMobileDashboard();
    }

    function syncSeniorManagerMobileDashboard() {
        if (!IS_SENIOR_MANAGER_DASHBOARD) {
            return;
        }

        const isMobile = window.innerWidth <= 767;
        const myPanel = document.getElementById('sm-dashboard-my-panel');
        const teamPanel = document.getElementById('sm-dashboard-team-panel');

        document.body.classList.toggle('sm-dashboard-mobile-inline-team', isMobile);

        if (isMobile) {
            if (myPanel) myPanel.hidden = false;
            if (teamPanel) {
                teamPanel.hidden = false;
                loadSeniorManagerTeamDashboard();
            }
            return;
        }

        if (teamPanel && !document.querySelector('[data-sm-dashboard-tab="team"]')?.classList.contains('is-active')) {
            teamPanel.hidden = true;
        }
    }

    function smTeamEscape(value) {
        return String(value ?? '').replace(/[&<>"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        }[char]));
    }

    function smTeamEmptyRow(colspan, message) {
        return `<tr><td colspan="${colspan}" class="sm-team-empty-cell">${smTeamEscape(message)}</td></tr>`;
    }

    function smTeamEmptyCard(message) {
        return `<div class="sm-team-empty-card">${smTeamEscape(message)}</div>`;
    }

    function smTeamInitials(name) {
        return String(name || 'U')
            .trim()
            .split(/\s+/)
            .slice(0, 2)
            .map((part) => part.charAt(0).toUpperCase())
            .join('') || 'U';
    }

    function smTeamMemberLink(baseUrl, userId) {
        const url = new URL(baseUrl, window.location.origin);
        url.searchParams.set('assigned_to', userId);
        return url.pathname + url.search;
    }

    function renderSmTeamKpis(result) {
        const performanceRows = Array.isArray(result.performance_rows) ? result.performance_rows : [];
        const responseRows = Array.isArray(result.response_rows) ? result.response_rows : [];
        const sum = (rows, key) => rows.reduce((total, row) => total + Number(row[key] || 0), 0);
        const setText = (id, value) => {
            const node = document.getElementById(id);
            if (node) node.textContent = Number(value || 0).toLocaleString('en-IN');
        };

        setText('smTeamKpiMembers', result.team_count || performanceRows.length);
        setText('smTeamKpiAssigned', sum(performanceRows, 'assigned'));
        setText('smTeamKpiPending', sum(performanceRows, 'fresh') || sum(responseRows, 'new_leads_not_completed'));
        setText('smTeamKpiFollowups', sum(performanceRows, 'follow_up'));
        setText('smTeamKpiClosers', sum(performanceRows, 'closer'));
    }

    function renderSeniorManagerTeamDashboard() {
        const responseRows = smTeamDashboardData.responseRows || [];
        const performanceRows = smTeamDashboardData.performanceRows || [];

        renderSmTeamResponseRows(responseRows);
        renderSmTeamPerformanceRows(performanceRows);
        renderSmTeamSourceRows(smTeamDashboardData.sourceRows);
        renderSmTeamAvailabilityRows(smTeamDashboardData.leadOffRows);
    }

    function renderSmTeamResponseRows(rows) {
        const body = document.getElementById('smTeamResponseBody');
        if (!body) return;

        if (!Array.isArray(rows) || rows.length === 0) {
            body.innerHTML = smTeamEmptyRow(6, 'Direct team member nahi mila.');
            return;
        }

        body.innerHTML = rows.map((row) => `
            <tr>
                <td>
                    <div class="sm-team-member-cell">
                        <span class="sm-team-avatar">${smTeamEscape(smTeamInitials(row.user_name))}</span>
                        <div class="sm-team-member-meta">
                            <strong>${smTeamEscape(row.user_name)}</strong>
                            <span>${smTeamEscape(row.role || 'Team member')}</span>
                        </div>
                    </div>
                </td>
                <td><span class="sm-team-metric-pill ${Number(row.new_leads_not_completed || 0) > 0 ? 'warning' : 'good'}">${Number(row.new_leads_not_completed || 0)}</span></td>
                <td><button type="button" class="sm-team-metric-pill sm-team-metric-button ${Number(row.previous_overdue || 0) > 0 ? 'warning' : 'good'}" data-sm-workload="previous-overdue" data-user-id="${Number(row.user_id)}" data-user-name="${smTeamEscape(row.user_name)}" title="View previous overdue tasks">${Number(row.previous_overdue || 0)}</button></td>
                <td><button type="button" class="sm-team-metric-pill sm-team-metric-button ${Number(row.today_overdue || 0) > 0 ? 'warning' : 'good'}" data-sm-workload="overdue" data-user-id="${Number(row.user_id)}" data-user-name="${smTeamEscape(row.user_name)}" title="View today's overdue tasks">${Number(row.today_overdue || 0)}</button></td>
                <td>${smTeamEscape(row.avg_response_time || '-')}</td>
                <td>
                    <div class="sm-team-action-list">
                        <a class="sm-team-action-link" href="${smTeamMemberLink(SM_TEAM_LEADS_URL, row.user_id)}"><i class="fas fa-arrow-up-right-from-square"></i> Leads</a>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    function closeSmTeamWorkloadModal() {
        document.getElementById('smTeamWorkloadModal')?.classList.remove('is-open');
        document.body.style.overflow = '';
    }

    function smTeamWorkloadItem(item, detail) {
        const href = item.view_url ? ` href="${smTeamEscape(item.view_url)}" target="_blank" rel="noopener"` : '';
        const project = item.project ? `<span>${smTeamEscape(item.project)}</span>` : '';
        return `<a class="sm-team-workload-item"${href}><strong>${smTeamEscape(item.customer || item.title || 'Unknown lead')}</strong>${project}<small>${smTeamEscape(detail)}</small></a>`;
    }

    async function openSmTeamWorkloadModal(memberId, memberName, mode) {
        const modal = document.getElementById('smTeamWorkloadModal');
        const content = document.getElementById('smTeamWorkloadContent');
        if (!modal || !content || !memberId) return;

        modal.classList.add('is-open');
        document.body.style.overflow = 'hidden';
        content.innerHTML = '<div class="sm-team-workload-empty">Loading today\'s team data...</div>';

        const result = await apiCall(`/dashboard/team-member/${encodeURIComponent(memberId)}/workload`);
        if (!result?.success) {
            content.innerHTML = `<div class="sm-team-workload-empty">${smTeamEscape(result?.message || 'Data load nahi ho paya.')}</div>`;
            return;
        }

        const previousOverdue = result.previous_overdue || {};
        const overdue = result.today_overdue || {};
        const previousOverdueItems = Array.isArray(previousOverdue.items) ? previousOverdue.items : [];
        const overdueItems = Array.isArray(overdue.items) ? overdue.items : [];
        const previousOverdueList = previousOverdueItems.length
            ? previousOverdueItems.map((item) => smTeamWorkloadItem(item, `${item.type || 'Task'} | ${item.scheduled_at || 'No schedule'} | ${item.status || 'pending'}`)).join('')
            : '<div class="sm-team-workload-empty">Koi previous overdue task nahi hai.</div>';
        const overdueList = overdueItems.length
            ? overdueItems.map((item) => smTeamWorkloadItem(item, `${item.type || 'Task'} | ${item.scheduled_at || 'No schedule'} | ${item.status || 'pending'}`)).join('')
            : '<div class="sm-team-workload-empty">Aaj koi overdue task nahi hai.</div>';

        content.innerHTML = `
            <div class="sm-team-workload-section">
                <h4>Previous Overdue (${Number(previousOverdue.count || 0)})</h4>
                <div class="sm-team-workload-list">${previousOverdueList}</div>
            </div>
            <div class="sm-team-workload-section">
                <h4>Today Overdue (${Number(overdue.count || 0)})</h4>
                <div class="sm-team-workload-list">${overdueList}</div>
            </div>
        `;

        document.getElementById('smTeamWorkloadTitle').textContent = `${result.member?.name || memberName || 'Team member'}: ${mode === 'overdue' ? 'Today Overdue' : 'Previous Overdue'}`;
        document.getElementById('smTeamWorkloadSubtitle').textContent = result.member?.role || 'Direct team member';
    }

    function renderSmTeamPerformanceRows(rows) {
        const body = document.getElementById('smTeamPerformanceBody');
        if (!body) return;

        if (!Array.isArray(rows) || rows.length === 0) {
            body.innerHTML = smTeamEmptyRow(10, 'Direct team performance nahi mila.');
            return;
        }

        const renderStageBreakdown = (breakdown) => {
            if (!Array.isArray(breakdown) || breakdown.length === 0) {
                return '<span class="sm-team-metric-pill">-</span>';
            }

            return `<div class="sm-team-stage-list">${breakdown.map((item) => `
                <span class="sm-team-stage-chip">${smTeamEscape(item.label || item.stage || 'Unknown')} <strong>${Number(item.count || 0)}</strong></span>
            `).join('')}</div>`;
        };

        body.innerHTML = rows.map((row) => `
            <tr class="sm-team-performance-main-row" data-sm-performance-row="${Number(row.user_id)}">
                <td>
                    <div class="sm-team-member-cell">
                        <span class="sm-team-avatar">${smTeamEscape(smTeamInitials(row.user_name))}</span>
                        <div class="sm-team-member-meta">
                            <strong>${smTeamEscape(row.user_name)}</strong>
                            <span>Assigned ${Number(row.assigned || 0)} leads</span>
                        </div>
                    </div>
                </td>
                <td><span class="sm-team-metric-pill">${Number(row.assigned || 0)}</span></td>
                <td><button type="button" class="sm-team-metric-pill sm-team-fresh-toggle ${Number(row.fresh || 0) > 0 ? 'warning' : 'good'}" data-sm-fresh-toggle data-user-id="${Number(row.user_id)}" data-user-name="${smTeamEscape(row.user_name)}" aria-expanded="false" aria-controls="smTeamFreshDetail${Number(row.user_id)}" ${Number(row.fresh || 0) === 0 ? 'disabled' : ''} title="View fresh and new leads"><span>${Number(row.fresh || 0)}</span><i class="fas fa-chevron-down" aria-hidden="true"></i></button></td>
                <td><span class="sm-team-metric-pill warning">${Number(row.follow_up || 0)}</span></td>
                <td><span class="sm-team-metric-pill">${Number(row.meeting || 0)}</span></td>
                <td><span class="sm-team-metric-pill">${Number(row.visit || 0)}</span></td>
                <td><span class="sm-team-metric-pill good">${Number(row.closer || 0)}</span></td>
                <td><span class="sm-team-metric-pill">${Number(row.junk || 0)}</span></td>
                <td><span class="sm-team-metric-pill">${Number(row.not_interested || 0)}</span></td>
                <td>${renderStageBreakdown(row.stage_breakdown)}</td>
            </tr>
            <tr id="smTeamFreshDetail${Number(row.user_id)}" class="sm-team-fresh-detail-row" data-sm-fresh-detail="${Number(row.user_id)}" hidden>
                <td colspan="10"><div class="sm-team-fresh-detail-content">Select kiye gaye user ki Fresh / New leads load ho rahi hain...</div></td>
            </tr>
        `).join('');
    }

    function smTeamFreshLeadQuery() {
        const range = document.getElementById('smTeamDashboardRange')?.value || 'this_month';
        const query = new URLSearchParams({ date_filter: range });
        if (range === 'custom') {
            query.set('start_date', document.getElementById('smTeamStartDate')?.value || '');
            query.set('end_date', document.getElementById('smTeamEndDate')?.value || '');
        }
        return query;
    }

    function closeSmTeamFreshDetails(exceptUserId = null) {
        document.querySelectorAll('[data-sm-fresh-detail]').forEach((row) => {
            if (String(row.dataset.smFreshDetail) !== String(exceptUserId ?? '')) row.hidden = true;
        });
        document.querySelectorAll('[data-sm-fresh-toggle]').forEach((button) => {
            if (String(button.dataset.userId) !== String(exceptUserId ?? '')) {
                button.setAttribute('aria-expanded', 'false');
                button.classList.remove('is-open');
            }
        });
    }

    async function toggleSmTeamFreshDetails(button) {
        const userId = button.dataset.userId;
        const detailRow = document.querySelector(`[data-sm-fresh-detail="${CSS.escape(userId)}"]`);
        const content = detailRow?.querySelector('.sm-team-fresh-detail-content');
        if (!detailRow || !content) return;

        const willOpen = detailRow.hidden;
        closeSmTeamFreshDetails(willOpen ? userId : null);
        detailRow.hidden = !willOpen;
        button.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        button.classList.toggle('is-open', willOpen);
        if (!willOpen) return;

        content.innerHTML = '<div class="sm-team-fresh-loading"><i class="fas fa-spinner fa-spin"></i> Fresh / New leads load ho rahi hain...</div>';
        const result = await apiCall(`/dashboard/team-member/${encodeURIComponent(userId)}/fresh-leads?${smTeamFreshLeadQuery().toString()}`);
        if (!result?.success) {
            content.innerHTML = `<div class="sm-team-fresh-empty">${smTeamEscape(result?.message || 'Lead data load nahi ho paya.')}</div>`;
            return;
        }

        const items = Array.isArray(result.items) ? result.items : [];
        const leadList = items.length ? items.map((lead) => `
            <a class="sm-team-fresh-lead" href="${smTeamEscape(lead.view_url)}">
                <span class="sm-team-fresh-lead-avatar">${smTeamEscape(smTeamInitials(lead.name))}</span>
                <span class="sm-team-fresh-lead-copy"><strong>${smTeamEscape(lead.name)}</strong><small><i class="fas fa-phone-alt"></i>${smTeamEscape(lead.phone || 'Not provided')}</small></span>
                <span class="sm-team-fresh-lead-status">${smTeamEscape(lead.status || 'New')}</span>
                <i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i>
            </a>
        `).join('') : '<div class="sm-team-fresh-empty">Is range mein koi Fresh / New lead nahi mili.</div>';
        const showing = Number(result.showing || items.length);
        const total = Number(result.total || items.length);
        const overflowNote = total > showing ? `<span>First ${showing} of ${total} leads</span>` : `<span>${total} lead${total === 1 ? '' : 's'}</span>`;
        content.innerHTML = `
            <div class="sm-team-fresh-detail-head"><div><strong>${smTeamEscape(result.member?.name || button.dataset.userName || 'Team member')}</strong><span>Fresh / New Leads</span></div>${overflowNote}</div>
            <div class="sm-team-fresh-lead-grid">${leadList}</div>
            ${total > showing ? `<a class="sm-team-fresh-view-all" href="${smTeamMemberLink(SM_TEAM_LEADS_URL, userId)}">View all assigned leads <i class="fas fa-arrow-right"></i></a>` : ''}
        `;
    }

    function renderSmTeamSourceRows(rows) {
        const body = document.getElementById('smTeamSourceBody');
        if (!body) return;

        if (!Array.isArray(rows) || rows.length === 0) {
            body.innerHTML = smTeamEmptyCard('Selected range me source-wise lead data nahi mila.');
            return;
        }

        const colors = ['#217346', '#1f77b4', '#e38b23', '#7f56d9', '#cf4c3f', '#16806d', '#6d7f90'];
        const total = rows.reduce((sum, row) => sum + Number(row.count || 0), 0);
        let offset = 0;
        const segments = rows.map((row, index) => {
            const start = total > 0 ? (offset / total) * 100 : 0;
            offset += Number(row.count || 0);
            const end = total > 0 ? (offset / total) * 100 : 100;
            return `${colors[index % colors.length]} ${start}% ${end}%`;
        });
        const rangeText = document.getElementById('smTeamDashboardRange')?.selectedOptions?.[0]?.textContent || 'Selected range';
        const rangeLabel = document.getElementById('smTeamSourceRange');
        if (rangeLabel) rangeLabel.textContent = rangeText;

        body.innerHTML = `
            <div class="sm-team-source-donut" style="background:conic-gradient(${segments.join(', ')});">
                <div class="sm-team-source-total"><strong>${Number(total || 0).toLocaleString('en-IN')}</strong><span>Leads</span></div>
            </div>
            <div class="sm-team-source-list">
                ${rows.map((row, index) => `
                    <div class="sm-team-source-row">
                        <span class="sm-team-source-dot" style="background:${colors[index % colors.length]};"></span>
                        <span class="sm-team-source-label" title="${smTeamEscape(row.label || row.source || 'Other')}">${smTeamEscape(row.label || row.source || 'Other')}</span>
                        <span class="sm-team-source-count">${Number(row.count || 0)}</span>
                    </div>
                `).join('')}
            </div>
        `;
    }

    function renderSmTeamAvailabilityRows(rows) {
        const body = document.getElementById('smTeamAvailabilityBody');
        if (!body) return;

        if (!Array.isArray(rows) || rows.length === 0) {
            body.innerHTML = smTeamEmptyCard('Direct team member nahi mila.');
            return;
        }

        body.innerHTML = rows.map((row) => {
            const isOff = row.is_lead_off === true;
            const isScheduled = row.has_scheduled_lead_off === true;
            const isEnabled = row.lead_off_enabled === true || isOff || isScheduled;
            const statusLabel = isScheduled ? 'Scheduled' : (isOff ? 'Lead Off' : 'Lead On');
            return `
                <div class="sm-team-availability-row">
                    <div>
                        <div class="sm-team-availability-name">${smTeamEscape(row.user_name)}</div>
                        <div class="sm-team-availability-role">${smTeamEscape(row.role || 'Team member')}</div>
                    </div>
                    <button type="button" class="sm-team-availability-control" data-team-lead-availability="1" data-user-id="${Number(row.user_id)}" data-user-name="${smTeamEscape(row.user_name)}" data-is-lead-off="${isEnabled ? '1' : '0'}" title="${isEnabled ? 'Turn Lead On' : 'Turn Lead Off'}">
                        <span class="sm-team-availability-label ${isEnabled ? 'is-off' : ''}">${statusLabel}</span>
                    </button>
                </div>
            `;
        }).join('');
    }

    function openSmLeadAvailabilityModal(button) {
        const modal = document.getElementById('smLeadAvailabilityModal');
        const userId = Number(button.dataset.userId);
        const userName = button.dataset.userName || 'Team member';
        const isLeadOff = button.dataset.isLeadOff !== '1';
        const offFields = document.getElementById('smLeadAvailabilityOffFields');
        const onMessage = document.getElementById('smLeadAvailabilityOnMessage');
        const fallback = document.getElementById('smLeadAvailabilityFallback');
        const reason = document.getElementById('smLeadAvailabilityReason');
        const duration = document.getElementById('smLeadAvailabilityDuration');
        const windowFields = document.getElementById('smLeadAvailabilityWindow');
        const start = document.getElementById('smLeadAvailabilityStart');
        const end = document.getElementById('smLeadAvailabilityEnd');
        const save = document.getElementById('smLeadAvailabilitySave');
        const error = document.getElementById('smLeadAvailabilityError');

        document.getElementById('smLeadAvailabilityUserId').value = userId;
        document.getElementById('smLeadAvailabilityIsOff').value = isLeadOff ? '1' : '0';
        document.getElementById('smLeadAvailabilitySubtitle').textContent = userName;
        document.getElementById('smLeadAvailabilityTitle').textContent = isLeadOff ? 'Turn Lead Off' : 'Turn Lead On';
        offFields.hidden = !isLeadOff;
        onMessage.hidden = isLeadOff;
        fallback.required = isLeadOff;
        reason.required = isLeadOff;
        reason.value = '';
        duration.value = 'manual';
        windowFields.hidden = true;
        start.required = false;
        end.required = false;
        start.value = '';
        end.value = '';
        error.textContent = '';
        error.hidden = true;
        fallback.innerHTML = '<option value="">Select fallback user</option>' + (smTeamDashboardData.leadOffRows || [])
            .filter((row) => Number(row.user_id) !== userId && row.is_lead_off !== true)
            .map((row) => `<option value="${Number(row.user_id)}">${smTeamEscape(row.user_name)}</option>`)
            .join('');
        save.textContent = isLeadOff ? 'Confirm Lead Off' : 'Confirm Lead On';
        save.classList.toggle('is-on', !isLeadOff);
        modal.classList.add('is-open');
        document.body.style.overflow = 'hidden';
        (isLeadOff ? fallback : save).focus();
    }

    function closeSmLeadAvailabilityModal() {
        document.getElementById('smLeadAvailabilityModal')?.classList.remove('is-open');
        document.body.style.overflow = '';
    }

    async function submitSmLeadAvailability(event) {
        event.preventDefault();
        const userId = Number(document.getElementById('smLeadAvailabilityUserId').value);
        const isLeadOff = document.getElementById('smLeadAvailabilityIsOff').value === '1';
        const fallbackId = document.getElementById('smLeadAvailabilityFallback').value;
        const reason = document.getElementById('smLeadAvailabilityReason').value.trim();
        const durationMode = document.getElementById('smLeadAvailabilityDuration').value;
        const start = document.getElementById('smLeadAvailabilityStart').value;
        const end = document.getElementById('smLeadAvailabilityEnd').value;
        const save = document.getElementById('smLeadAvailabilitySave');
        const error = document.getElementById('smLeadAvailabilityError');
        const payload = { is_lead_off: isLeadOff };

        if (isLeadOff) {
            payload.fallback_user_id = Number(fallbackId);
            payload.reason = reason;
            payload.duration_mode = durationMode;
            if (durationMode === 'window') {
                payload.lead_off_start_at = start;
                payload.lead_off_end_at = end;
            }
        }

        save.disabled = true;
        error.hidden = true;
        const result = await apiCall(`/dashboard/team-member/${encodeURIComponent(userId)}/lead-availability`, {
            method: 'PUT',
            body: JSON.stringify(payload),
        });
        save.disabled = false;

        if (!result?.success) {
            error.textContent = result?.message || 'Lead availability update nahi ho paya.';
            error.hidden = false;
            return;
        }

        smTeamDashboardData.leadOffRows = (smTeamDashboardData.leadOffRows || []).map((row) => (
            Number(row.user_id) === userId
                ? {
                    ...row,
                    is_lead_off: result.member?.is_lead_off === true,
                    lead_off_enabled: result.member?.lead_off_enabled === true,
                    has_scheduled_lead_off: result.member?.has_scheduled_lead_off === true,
                    fallback_user_id: result.member?.fallback_user_id || null,
                    fallback_user_name: result.member?.fallback_user_name || null,
                    lead_off_reason: result.member?.lead_off_reason || null,
                    lead_off_start_at: result.member?.lead_off_start_at || null,
                    lead_off_end_at: result.member?.lead_off_end_at || null,
                }
                : row
        ));
        renderSmTeamAvailabilityRows(smTeamDashboardData.leadOffRows);
        closeSmLeadAvailabilityModal();
    }

    function renderSmTeamTemperatureRows(rows) {
        const body = document.getElementById('smTeamTemperatureBody');
        if (!body) return;

        if (!Array.isArray(rows) || rows.length === 0) {
            body.innerHTML = smTeamEmptyCard('Direct team lead temperature data nahi mila.');
            return;
        }

        body.innerHTML = rows.map((row) => {
            const total = Number(row.total || 0);
            return `
                <div class="sm-temperature-user-card">
                    <div class="sm-temperature-user-head">
                        <span class="sm-team-avatar">${smTeamEscape(smTeamInitials(row.user_name))}</span>
                        <div>
                            <strong>${smTeamEscape(row.user_name)}</strong>
                            <small>${total} classified leads</small>
                        </div>
                        <a class="sm-team-action-link" href="${smTeamMemberLink(SM_TEAM_TASKS_URL, row.user_id)}"><i class="fas fa-list-check"></i> Tasks</a>
                    </div>
                    <div class="sm-temperature-metrics">
                        <div class="sm-temp-pill sm-temp-hot">
                            <span>Hot</span>
                            <strong>${Number(row.hot || 0)}</strong>
                        </div>
                        <div class="sm-temp-pill sm-temp-warm">
                            <span>Warm</span>
                            <strong>${Number(row.warm || 0)}</strong>
                        </div>
                        <div class="sm-temp-pill sm-temp-cold">
                            <span>Cold</span>
                            <strong>${Number(row.cold || 0)}</strong>
                        </div>
                        <div class="sm-temp-pill">
                            <span>Other</span>
                            <strong>${Number(row.other || 0)}</strong>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    async function loadSeniorManagerTeamDashboard(force = false) {
        const responseBody = document.getElementById('smTeamResponseBody');
        const performanceBody = document.getElementById('smTeamPerformanceBody');
        const sourceBody = document.getElementById('smTeamSourceBody');
        const availabilityBody = document.getElementById('smTeamAvailabilityBody');
        const rangeSelect = document.getElementById('smTeamDashboardRange');
        const rangeLabel = document.getElementById('smTeamDashboardRangeLabel');
        const startDate = document.getElementById('smTeamStartDate')?.value;
        const endDate = document.getElementById('smTeamEndDate')?.value;

        if (!responseBody || !performanceBody || !sourceBody || !availabilityBody) {
            return;
        }

        if (smTeamDashboardLoaded && !force) {
            return;
        }

        responseBody.innerHTML = smTeamEmptyRow(6, 'Team data load ho raha hai...');
        performanceBody.innerHTML = smTeamEmptyRow(10, 'Team data load ho raha hai...');
        sourceBody.innerHTML = smTeamEmptyCard('Source data load ho raha hai...');
        availabilityBody.innerHTML = smTeamEmptyCard('Team availability load ho rahi hai...');

        const dateFilter = rangeSelect?.value || 'this_month';
        if (dateFilter === 'custom' && (!startDate || !endDate)) {
            return;
        }

        const query = new URLSearchParams({ date_filter: dateFilter });
        if (dateFilter === 'custom') {
            query.set('start_date', startDate);
            query.set('end_date', endDate);
        }
        const result = await apiCall(`/dashboard/team-overview?${query.toString()}`);

        if (!result || !result.success) {
            const message = result?.message || 'Team data load nahi ho paya.';
            responseBody.innerHTML = smTeamEmptyRow(6, message);
            performanceBody.innerHTML = smTeamEmptyRow(10, message);
            sourceBody.innerHTML = smTeamEmptyCard(message);
            availabilityBody.innerHTML = smTeamEmptyCard(message);
            return;
        }

        smTeamDashboardData = {
            responseRows: result.response_rows || [],
            performanceRows: result.performance_rows || [],
            temperatureRows: result.temperature_rows || [],
            sourceRows: result.source_rows || [],
            leadOffRows: result.lead_off_rows || [],
            teamCount: Number(result.team_count || 0),
        };

        renderSmTeamKpis(result);
        renderSeniorManagerTeamDashboard();

        if (rangeLabel) {
            rangeLabel.textContent = result.range?.label || rangeSelect?.selectedOptions?.[0]?.textContent || 'This Month';
        }
        const sourceRangeLabel = document.getElementById('smTeamSourceRange');
        if (sourceRangeLabel) {
            sourceRangeLabel.textContent = result.range?.label || rangeSelect?.selectedOptions?.[0]?.textContent || 'This Month';
        }

        smTeamDashboardLoaded = true;
    }

    function bindSeniorManagerTeamDashboardControls() {
        const rangeSelect = document.getElementById('smTeamDashboardRange');
        const refreshButton = document.getElementById('smTeamDashboardRefresh');
        const customRange = document.getElementById('smTeamCustomRange');
        const startDate = document.getElementById('smTeamStartDate');
        const endDate = document.getElementById('smTeamEndDate');

        const formatLocalDate = (date) => {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        };

        const syncCustomRange = () => {
            const isCustom = rangeSelect?.value === 'custom';
            if (customRange) customRange.hidden = !isCustom;
            if (!isCustom || !startDate || !endDate) return;

            const today = new Date();
            if (!startDate.value) startDate.value = formatLocalDate(new Date(today.getFullYear(), today.getMonth(), 1));
            if (!endDate.value) endDate.value = formatLocalDate(today);
        };

        rangeSelect?.addEventListener('change', () => {
            syncCustomRange();
        });

        refreshButton?.addEventListener('click', () => {
            smTeamDashboardLoaded = false;
            loadSeniorManagerTeamDashboard(true);
        });

        syncCustomRange();

        document.addEventListener('click', (event) => {
            const toggle = event.target.closest('[data-sm-fresh-toggle]');
            if (!toggle || toggle.disabled) return;
            toggleSmTeamFreshDetails(toggle);
        });

        document.addEventListener('click', (event) => {
            const action = event.target.closest('[data-sm-workload]');
            if (!action) return;

            openSmTeamWorkloadModal(action.dataset.userId, action.dataset.userName, action.dataset.smWorkload);
        });

        document.addEventListener('click', (event) => {
            const toggle = event.target.closest('[data-team-lead-availability]');
            if (!toggle) return;
            openSmLeadAvailabilityModal(toggle);
        });

        document.getElementById('smLeadAvailabilityForm')?.addEventListener('submit', submitSmLeadAvailability);
        document.getElementById('smLeadAvailabilityDuration')?.addEventListener('change', (event) => {
            const isWindow = event.target.value === 'window';
            const windowFields = document.getElementById('smLeadAvailabilityWindow');
            const start = document.getElementById('smLeadAvailabilityStart');
            const end = document.getElementById('smLeadAvailabilityEnd');
            windowFields.hidden = !isWindow;
            start.required = isWindow;
            end.required = isWindow;
        });
        document.getElementById('smLeadAvailabilityModal')?.addEventListener('click', (event) => {
            if (event.target.id === 'smLeadAvailabilityModal') closeSmLeadAvailabilityModal();
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') closeSmLeadAvailabilityModal();
        });
    }
    
    function getToken() {
        return API_TOKEN
            || (typeof window.getManagerApiToken === 'function' ? window.getManagerApiToken() : '')
            || '{{ session("api_token") ?? "" }}';
    }

    function getDashboardManagerAuthHeaders(extraHeaders = {}) {
        if (typeof window.getManagerAuthHeaders === 'function') {
            return window.getManagerAuthHeaders(extraHeaders);
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const token = getToken();
        const headers = {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
        };

        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }

        return { ...headers, ...extraHeaders };
    }

    async function apiCall(endpoint, options = {}) {
        const token = getToken();
        if (!token) {
            console.error('No API token available');
            window.location.href = '{{ route("login") }}';
            return null;
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        const defaultOptions = {
            headers: {
                ...getDashboardManagerAuthHeaders({
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`,
                    'X-CSRF-TOKEN': csrfToken,
                }),
            },
        };

        try {
            console.log(`API Call: ${API_BASE_URL}${endpoint}`);
            const response = await fetch(`${API_BASE_URL}${endpoint}`, {
                ...defaultOptions,
                ...options,
                headers: { ...defaultOptions.headers, ...options.headers },
                credentials: 'same-origin',
            });

            console.log(`API Response Status: ${response.status} for ${endpoint}`);

            if (response.status === 401 || response.status === 403) {
                console.error('Unauthorized - token invalid');
                window.handleManagerAuthFailure('dashboard');
                window.location.href = '{{ route("login") }}';
                return null;
            }

            if (!response.ok) {
                const errorText = await response.text();
                console.error(`API Error (${response.status}):`, errorText);
                try {
                    return JSON.parse(errorText);
                } catch (e) {
                    return { success: false, message: errorText };
                }
            }

            const data = await response.json();
            console.log(`API Success for ${endpoint}:`, data);
            return data;
        } catch (error) {
            console.error('API Call Error:', error);
            console.error('Error details:', error.message, error.stack);
            return { success: false, message: error.message };
        }
    }

    const dashboardUsesUnifiedCnpScheduling = @json(optional(auth()->user())->isAssistantSalesManager() || optional(auth()->user())->isSeniorManager());
    let dashboardCurrentTaskId = null;
    let dashboardCurrentTaskCategory = 'other';
    let dashboardSelectedTaskOutcome = null;
    let dashboardSelectedTaskMinutes = null;
    let dashboardIsCustomTaskTime = false;

    function showDashboardTaskMessage(message, type = 'info') {
        if (!message) {
            return;
        }
        if (typeof window.showAlert === 'function') {
            window.showAlert(message, type);
            return;
        }
        window.alert(message);
    }

    function getAsmDialerPhone() {
        const input = document.getElementById('asmDialerPhoneInput');
        return input ? input.value.replace(/\D+/g, '').slice(0, 10) : '';
    }

    function setAsmDialerPhone(value) {
        const input = document.getElementById('asmDialerPhoneInput');
        if (!input) {
            return;
        }
        input.value = String(value || '').replace(/\D+/g, '').slice(0, 10);
        updateAsmDialerCallState();
    }

    function setAsmDialerStatus(message = '', type = 'info') {
        const status = document.getElementById('asmDialerStatus');
        if (!status) {
            return;
        }
        status.textContent = message;
        status.classList.toggle('success', type === 'success');
        status.classList.toggle('error', type === 'error');
    }

    function updateAsmDialerCallState(isLoading = false) {
        const button = document.getElementById('asmDialerCallButton');
        if (!button) {
            return;
        }
        const phone = getAsmDialerPhone();
        button.disabled = isLoading || phone.length !== 10;
        button.querySelector('span').textContent = isLoading ? 'Calling...' : 'Call Now';
    }

    function getAsmDialerRecentNumbers() {
        try {
            return JSON.parse(sessionStorage.getItem('asm-dialer-recent-numbers') || '[]')
                .filter((phone) => /^\d{10}$/.test(String(phone)))
                .slice(0, 5);
        } catch (error) {
            return [];
        }
    }

    function storeAsmDialerRecentNumber(phone) {
        const normalized = String(phone || '').replace(/\D+/g, '').slice(0, 10);
        if (!/^\d{10}$/.test(normalized)) {
            return;
        }
        const next = [normalized, ...getAsmDialerRecentNumbers().filter((item) => item !== normalized)].slice(0, 5);
        try {
            sessionStorage.setItem('asm-dialer-recent-numbers', JSON.stringify(next));
        } catch (error) {}
        renderAsmDialerRecentNumbers();
    }

    function renderAsmDialerRecentNumbers() {
        const wrap = document.getElementById('asmDialerRecentWrap');
        const list = document.getElementById('asmDialerRecentList');
        if (!wrap || !list) {
            return;
        }
        const recent = getAsmDialerRecentNumbers();
        wrap.hidden = recent.length === 0;
        list.innerHTML = recent.map((phone) => (
            `<button type="button" class="asm-dialer-recent-chip" data-asm-dialer-recent="${phone}">+91 ${phone}</button>`
        )).join('');
    }

    function openAsmDialerModal() {
        if (!CAN_USE_ASM_DIALER) {
            return;
        }
        const modal = document.getElementById('asmDialerModal');
        if (!modal) {
            return;
        }
        modal.style.display = 'flex';
        modal.classList.add('active');
        setAsmDialerStatus('');
        renderAsmDialerRecentNumbers();
        updateAsmDialerCallState();
    }

    function closeAsmDialerModal() {
        const modal = document.getElementById('asmDialerModal');
        if (!modal) {
            return;
        }
        modal.style.display = 'none';
        modal.classList.remove('active');
        setAsmDialerStatus('');
        updateAsmDialerCallState();
    }

    async function submitAsmDialerCall() {
        const phone = getAsmDialerPhone();
        if (!/^[6-9]\d{9}$/.test(phone)) {
            setAsmDialerStatus('Valid 10 digit Indian number enter karein.', 'error');
            return;
        }

        updateAsmDialerCallState(true);
        setAsmDialerStatus('MCube call initiate ho rahi hai...');

        const result = await apiCall('/dialer/call', {
            method: 'POST',
            body: JSON.stringify({ phone }),
        });

        updateAsmDialerCallState(false);

        if (result?.success) {
            storeAsmDialerRecentNumber(phone);
            setAsmDialerStatus(result.message || 'Call initiated via MCube.', 'success');
            return;
        }

        const firstValidationError = result?.errors ? Object.values(result.errors).flat().find(Boolean) : null;
        setAsmDialerStatus(firstValidationError || result?.message || 'Call initiate nahi ho payi.', 'error');
    }

    function bindAsmDialerControls() {
        if (!CAN_USE_ASM_DIALER) {
            return;
        }

        document.querySelectorAll('[data-asm-dialer-open]').forEach((button) => {
            button.addEventListener('click', openAsmDialerModal);
        });

        document.querySelectorAll('[data-asm-dialer-close]').forEach((button) => {
            button.addEventListener('click', closeAsmDialerModal);
        });

        document.querySelectorAll('[data-asm-dialer-key]').forEach((button) => {
            button.addEventListener('click', () => {
                setAsmDialerPhone(getAsmDialerPhone() + button.dataset.asmDialerKey);
                setAsmDialerStatus('');
            });
        });

        document.querySelector('[data-asm-dialer-backspace]')?.addEventListener('click', () => {
            setAsmDialerPhone(getAsmDialerPhone().slice(0, -1));
            setAsmDialerStatus('');
        });

        document.querySelector('[data-asm-dialer-clear]')?.addEventListener('click', () => {
            setAsmDialerPhone('');
            setAsmDialerStatus('');
        });

        document.getElementById('asmDialerPhoneInput')?.addEventListener('input', (event) => {
            setAsmDialerPhone(event.target.value);
            setAsmDialerStatus('');
        });

        document.getElementById('asmDialerCallButton')?.addEventListener('click', submitAsmDialerCall);

        document.getElementById('asmDialerRecentList')?.addEventListener('click', (event) => {
            const button = event.target.closest('[data-asm-dialer-recent]');
            if (!button) {
                return;
            }
            setAsmDialerPhone(button.dataset.asmDialerRecent);
            setAsmDialerStatus('');
        });

        document.addEventListener('keydown', (event) => {
            const modal = document.getElementById('asmDialerModal');
            if (event.key === 'Escape' && modal?.classList.contains('active')) {
                closeAsmDialerModal();
            }
        });

        updateAsmDialerCallState();
    }

    function formatDashboardTaskDateForApi(date) {
        const yyyy = date.getFullYear();
        const mm = String(date.getMonth() + 1).padStart(2, '0');
        const dd = String(date.getDate()).padStart(2, '0');
        const hh = String(date.getHours()).padStart(2, '0');
        const mi = String(date.getMinutes()).padStart(2, '0');
        const ss = String(date.getSeconds()).padStart(2, '0');
        return `${yyyy}-${mm}-${dd} ${hh}:${mi}:${ss}`;
    }

    function resetDashboardTaskModalState() {
        dashboardSelectedTaskOutcome = null;
        dashboardSelectedTaskMinutes = null;
        dashboardIsCustomTaskTime = false;
        document.querySelectorAll('#dashboardTaskDateTimeModal .time-option-btn').forEach(function(button) {
            button.classList.remove('selected');
        });
        document.getElementById('dashboardTaskCustomPicker').style.display = 'none';
        document.getElementById('dashboardTaskDateInput').value = '';
        document.getElementById('dashboardTaskTimeInput').value = '';
        document.getElementById('dashboardTaskDateTimeRemark').value = '';
        document.getElementById('dashboardTaskRemarkInput').value = '';
    }

    function openDashboardTaskCompletion(taskId, taskCategory = 'other') {
        if (!taskId) {
            return;
        }
        dashboardCurrentTaskId = taskId;
        dashboardCurrentTaskCategory = taskCategory || 'other';
        resetDashboardTaskModalState();
        document.querySelector('#dashboardTaskOutcomeModal [onclick="dashboardSelectTaskOutcome(\'follow_up\')"] span').textContent = dashboardCurrentTaskCategory === 'fresh_lead' ? 'Call Later' : 'Follow Up';
        const modal = document.getElementById('dashboardTaskOutcomeModal');
        if (modal) {
            modal.style.display = 'flex';
            modal.classList.add('active');
        }
    }

    function closeDashboardTaskOutcomeModal() {
        const modal = document.getElementById('dashboardTaskOutcomeModal');
        if (modal) {
            modal.style.display = 'none';
            modal.classList.remove('active');
        }
    }

    function closeDashboardTaskRemarkModal() {
        const modal = document.getElementById('dashboardTaskRemarkModal');
        if (modal) {
            modal.style.display = 'none';
            modal.classList.remove('active');
        }
        document.getElementById('dashboardTaskRemarkInput').value = '';
    }

    function closeDashboardTaskDateTimeModal() {
        const modal = document.getElementById('dashboardTaskDateTimeModal');
        if (modal) {
            modal.style.display = 'none';
            modal.classList.remove('active');
        }
        resetDashboardTaskModalState();
    }

    function showDashboardTaskCustomPicker() {
        dashboardIsCustomTaskTime = true;
        dashboardSelectedTaskMinutes = null;
        document.querySelectorAll('#dashboardTaskDateTimeModal .time-option-btn').forEach(function(button) {
            button.classList.remove('selected');
        });
        document.getElementById('dashboardTaskCustomPicker').style.display = 'block';
    }

    function selectDashboardTaskPreset(minutes, event) {
        dashboardIsCustomTaskTime = false;
        dashboardSelectedTaskMinutes = minutes;
        document.getElementById('dashboardTaskCustomPicker').style.display = 'none';
        document.querySelectorAll('#dashboardTaskDateTimeModal .time-option-btn').forEach(function(button) {
            button.classList.remove('selected');
        });
        event?.currentTarget?.classList.add('selected');
    }

    function openDashboardTaskRemarkModal(outcome) {
        dashboardSelectedTaskOutcome = outcome;
        const modal = document.getElementById('dashboardTaskRemarkModal');
        const title = document.getElementById('dashboardTaskRemarkTitle');
        const textarea = document.getElementById('dashboardTaskRemarkInput');
        title.textContent = outcome === 'junk' ? 'Mark Lead as Junk' : outcome === 'not_interested' ? 'Mark Lead as Not Interested' : 'Add CNP Remark';
        textarea.placeholder = outcome === 'junk' ? 'Add junk reason or context...' : outcome === 'not_interested' ? 'Add not interested context...' : 'Add CNP context...';
        if (modal) {
            modal.style.display = 'flex';
            modal.classList.add('active');
        }
    }

    function openDashboardTaskDateTimeModal(outcome) {
        dashboardSelectedTaskOutcome = outcome;
        const modal = document.getElementById('dashboardTaskDateTimeModal');
        const title = document.getElementById('dashboardTaskDateTimeTitle');
        const text = document.getElementById('dashboardTaskDateTimeText');
        const button = document.getElementById('dashboardTaskDateTimeConfirmBtn');
        const isCallLater = outcome === 'follow_up' && dashboardCurrentTaskCategory === 'fresh_lead';
        title.textContent = isCallLater ? 'Schedule Call Later' : outcome === 'follow_up' ? 'Schedule Follow Up' : 'Select Retry Time for CNP';
        text.textContent = isCallLater ? 'Choose the next call date and time' : outcome === 'follow_up' ? 'Choose when the next follow-up call should happen:' : 'Choose when to retry this call:';
        button.textContent = isCallLater ? 'Schedule Call' : outcome === 'follow_up' ? 'Schedule Follow Up' : 'Confirm CNP';
        button.style.background = outcome === 'follow_up' ? '#2563eb' : '#f59e0b';
        if (modal) {
            modal.style.display = 'flex';
            modal.classList.add('active');
        }
    }

    async function dashboardSelectTaskOutcome(outcome) {
        closeDashboardTaskOutcomeModal();

        if (!dashboardCurrentTaskId) {
            showDashboardTaskMessage('Task ID not found', 'error');
            return;
        }

        if (outcome === 'interested') {
            await openManagerLeadRequirementFormModal(dashboardCurrentTaskId);
            return;
        }

        if (outcome === 'not_interested') {
            openDashboardTaskRemarkModal('not_interested');
            return;
        }

        if (outcome === 'junk') {
            openDashboardTaskRemarkModal('junk');
            return;
        }

        if (outcome === 'follow_up' || outcome === 'cnp') {
            if (outcome === 'cnp' && !dashboardUsesUnifiedCnpScheduling && dashboardCurrentTaskCategory === 'fresh_lead') {
                openDashboardTaskRemarkModal('cnp');
                return;
            }
            openDashboardTaskDateTimeModal(outcome);
        }
    }

    async function submitDashboardTaskOutcome(outcome, extraData = {}) {
        const result = await apiCall(`/tasks/${dashboardCurrentTaskId}/outcome`, {
            method: 'POST',
            body: JSON.stringify({
                outcome,
                ...extraData
            })
        });

        if (!result || !result.success) {
            const firstValidationError = result?.errors ? Object.values(result.errors).flat().find(Boolean) : null;
            showDashboardTaskMessage(firstValidationError || result?.message || result?.error || 'Failed to update task outcome', 'error');
            return result;
        }

        closeDashboardTaskOutcomeModal();
        closeDashboardTaskRemarkModal();
        closeDashboardTaskDateTimeModal();
        dashboardCurrentTaskId = null;
        dashboardCurrentTaskCategory = 'other';
        await loadDashboardData();
        showDashboardTaskMessage(result.message || 'Outcome submitted successfully', 'success');
        return result;
    }

    async function submitDashboardTaskRemark() {
        const remark = document.getElementById('dashboardTaskRemarkInput').value.trim();
        const outcome = dashboardSelectedTaskOutcome || 'junk';
        await submitDashboardTaskOutcome(outcome, { remark });
    }

    async function submitDashboardTaskDateTime() {
        let nextDateTime = null;

        if (dashboardIsCustomTaskTime) {
            const date = document.getElementById('dashboardTaskDateInput').value;
            const time = document.getElementById('dashboardTaskTimeInput').value;
            if (!date || !time) {
                showDashboardTaskMessage('Please select both date and time', 'warning');
                return;
            }
            const selectedDateTime = new Date(`${date}T${time}`);
            if (selectedDateTime <= new Date()) {
                showDashboardTaskMessage('Please select a future date and time', 'warning');
                return;
            }
            nextDateTime = formatDashboardTaskDateForApi(selectedDateTime);
        } else if (dashboardSelectedTaskMinutes !== null) {
            nextDateTime = formatDashboardTaskDateForApi(new Date(Date.now() + (dashboardSelectedTaskMinutes * 60 * 1000)));
        } else {
            showDashboardTaskMessage('Please select a time option', 'warning');
            return;
        }

        await submitDashboardTaskOutcome(dashboardSelectedTaskOutcome, {
            next_datetime: nextDateTime,
            remark: document.getElementById('dashboardTaskDateTimeRemark').value.trim() || ''
        });
    }

    async function openManagerLeadRequirementFormModal(taskId) {
        const modal = document.getElementById('managerLeadRequirementFormModal');
        const container = document.getElementById('managerLeadFormContainer');
        if (!modal || !container || !taskId) {
            showDashboardTaskMessage('Unable to open lead form', 'error');
            return;
        }

        window.currentTaskId = taskId;
        window.managerTaskOutcomeContext = { outcome: 'interested' };
        container.dataset.formContext = 'task';
        container.innerHTML = '<div style="text-align:center; padding:40px;"><div class="spinner" style="display:inline-block;"></div><p style="margin-top:15px; color:#666;">Loading form...</p></div>';
        modal.style.display = 'flex';
        modal.classList.add('active');

        try {
            const result = await apiCall(`/tasks/${taskId}/lead-requirement-form`);
            if (result?.success && result?.data) {
                if (typeof window.renderManagerLeadForm === 'function') {
                    window.renderManagerLeadForm(result.data);
                } else {
                    throw new Error('Lead form renderer unavailable');
                }
            } else {
                throw new Error(result?.message || result?.error || 'Failed to load form');
            }
        } catch (error) {
            console.error('Error loading manager lead form on dashboard:', error);
            showDashboardTaskMessage(error.message || 'Failed to load form', 'error');
            closeManagerLeadRequirementFormModal();
        }
    }

    function closeManagerLeadRequirementFormModal() {
        const modal = document.getElementById('managerLeadRequirementFormModal');
        if (modal) {
            modal.style.display = 'none';
            modal.classList.remove('active');
        }
        const container = document.getElementById('managerLeadFormContainer');
        if (container) {
            container.innerHTML = '';
        }
        window.currentTaskId = null;
        window.managerTaskOutcomeContext = null;
    }

    function cancelManagerLeadRequirementForm() {
        closeManagerLeadRequirementFormModal();
    }

    const DASHBOARD_FILTER_DEFAULTS = {
        date_filter: 'today',
        start_date: '',
        end_date: ''
    };
    let asmDashboardDateFilter = { ...DASHBOARD_FILTER_DEFAULTS };
    const asmDashboardFilterStorageKey = 'salesManagerDashboardDateFilter';

    function persistDashboardFilterToStorage() {
        try {
            localStorage.setItem(asmDashboardFilterStorageKey, JSON.stringify(asmDashboardDateFilter));
        } catch (error) {
            console.error('Failed to persist dashboard filter:', error);
        }
    }

    function getStoredDashboardFilter() {
        try {
            const stored = JSON.parse(localStorage.getItem(asmDashboardFilterStorageKey) || '{}');
            return {
                date_filter: ['today', 'this_week', 'this_month', 'custom'].includes(stored.date_filter) ? stored.date_filter : 'today',
                start_date: stored.start_date || '',
                end_date: stored.end_date || ''
            };
        } catch (error) {
            return { ...DASHBOARD_FILTER_DEFAULTS };
        }
    }

    function getDashboardFilterParams() {
        const params = new URLSearchParams();
        params.set('date_filter', asmDashboardDateFilter.date_filter || 'today');
        if (asmDashboardDateFilter.date_filter === 'custom') {
            if (asmDashboardDateFilter.start_date) {
                params.set('start_date', asmDashboardDateFilter.start_date);
            }
            if (asmDashboardDateFilter.end_date) {
                params.set('end_date', asmDashboardDateFilter.end_date);
            }
        }
        return params;
    }

    function getDashboardFilterLabel() {
        switch (asmDashboardDateFilter.date_filter) {
            case 'this_week':
                return 'This Week';
            case 'this_month':
                return 'This Month';
            case 'custom':
                return asmDashboardDateFilter.start_date && asmDashboardDateFilter.end_date
                    ? `${asmDashboardDateFilter.start_date} to ${asmDashboardDateFilter.end_date}`
                    : 'Custom Range';
            case 'today':
            default:
                return 'Today';
        }
    }

    function syncDashboardFilterUi() {
        document.querySelectorAll('[data-dashboard-filter]').forEach(function (button) {
            button.classList.toggle('active', button.dataset.dashboardFilter === asmDashboardDateFilter.date_filter);
        });

        const customRange = document.getElementById('asmDashboardCustomRange');
        const startInput = document.getElementById('asmDashboardStartDate');
        const endInput = document.getElementById('asmDashboardEndDate');
        const status = document.getElementById('asmDashboardFilterStatus');
        const mobileSelect = document.getElementById('asmDashboardFilterSelect');
        const rangeLabel = getDashboardFilterLabel();

        if (customRange) {
            customRange.classList.toggle('active', asmDashboardDateFilter.date_filter === 'custom');
        }
        if (startInput) {
            startInput.value = asmDashboardDateFilter.start_date || '';
        }
        if (endInput) {
            endInput.value = asmDashboardDateFilter.end_date || '';
        }
        if (status) {
            status.textContent = `Current: ${rangeLabel}`;
        }
        if (mobileSelect) {
            mobileSelect.value = asmDashboardDateFilter.date_filter || 'today';
        }

    }

    function hydrateDashboardFilterFromUrl() {
        const params = new URLSearchParams(window.location.search);
        const storedFilter = getStoredDashboardFilter();
        const dateFilter = params.get('date_filter') || storedFilter.date_filter || 'today';
        asmDashboardDateFilter = {
            date_filter: ['today', 'this_week', 'this_month', 'custom'].includes(dateFilter) ? dateFilter : 'today',
            start_date: params.get('start_date') || storedFilter.start_date || '',
            end_date: params.get('end_date') || storedFilter.end_date || ''
        };
        persistDashboardFilterToStorage();
        syncDashboardFilterUi();
    }

    function persistDashboardFilterToUrl() {
        const params = getDashboardFilterParams();
        const nextUrl = `${window.location.pathname}?${params.toString()}`;
        window.history.replaceState({}, '', nextUrl);
    }

    async function applyDashboardFilter(nextFilter) {
        asmDashboardDateFilter = {
            ...asmDashboardDateFilter,
            ...nextFilter
        };

        if (asmDashboardDateFilter.date_filter !== 'custom') {
            asmDashboardDateFilter.start_date = '';
            asmDashboardDateFilter.end_date = '';
        }

        syncDashboardFilterUi();
        persistDashboardFilterToStorage();
        persistDashboardFilterToUrl();
        await loadDashboardData();
    }

    function bindDashboardFilterControls() {
        document.querySelectorAll('[data-dashboard-filter]').forEach(function (button) {
            button.addEventListener('click', function () {
                const nextFilter = button.dataset.dashboardFilter;
                if (nextFilter === 'custom') {
                    asmDashboardDateFilter.date_filter = 'custom';
                    syncDashboardFilterUi();
                    return;
                }
                applyDashboardFilter({ date_filter: nextFilter });
            });
        });

        document.getElementById('asmDashboardFilterSelect')?.addEventListener('change', function () {
            const nextFilter = this.value || 'today';
            if (nextFilter === 'custom') {
                asmDashboardDateFilter.date_filter = 'custom';
                syncDashboardFilterUi();
                return;
            }
            applyDashboardFilter({ date_filter: nextFilter });
        });

        document.getElementById('asmDashboardApplyRange')?.addEventListener('click', function () {
            const startDate = document.getElementById('asmDashboardStartDate')?.value || '';
            const endDate = document.getElementById('asmDashboardEndDate')?.value || '';
            if (!startDate || !endDate) {
                alert('Custom range ke liye start aur end date dono select karo.');
                return;
            }
            applyDashboardFilter({
                date_filter: 'custom',
                start_date: startDate,
                end_date: endDate
            });
        });

        document.querySelectorAll('[data-dashboard-clear-cache]').forEach(function (button) {
            button.addEventListener('click', clearAsmDashboardCache);
        });
    }

    async function clearAsmDashboardCache() {
        const buttons = document.querySelectorAll('[data-dashboard-clear-cache]');
        const status = document.getElementById('asmDashboardFilterStatus');

        buttons.forEach(function (button) {
            button.disabled = true;
            button.dataset.originalLabel = button.dataset.originalLabel || button.textContent.trim();
            button.textContent = 'Clearing...';
        });

        if (status) {
            status.textContent = 'Clearing dashboard cache...';
        }

        try {
            const response = await apiCall('/dashboard/clear-cache', { method: 'POST' });

            if (!response || response.success === false) {
                throw new Error(response?.message || 'Failed to clear dashboard cache.');
            }

            if (status) {
                status.textContent = response.message || 'Dashboard cache cleared successfully.';
            }

            await loadDashboardData();
        } catch (error) {
            if (status) {
                status.textContent = error.message || 'Failed to clear dashboard cache.';
            }
        } finally {
            buttons.forEach(function (button) {
                button.disabled = false;
                button.textContent = button.dataset.originalLabel || 'Clear Cache';
            });
        }
    }

    async function loadSmLeadsPendingResponse() {
        const tbody = document.getElementById('sm-leads-pending-response-tbody');
        const countEl = document.getElementById('smNoResponseYetCount');
        if (tbody) tbody.innerHTML = '<tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">Loading...</td></tr>';
        try {
            const data = await apiCall('/leads-pending-response?' + getDashboardFilterParams().toString());
            if (!data || data.error) {
                if (countEl) countEl.textContent = '0';
                if (tbody) tbody.innerHTML = '<tr><td colspan="4" class="px-4 py-6 text-center text-red-600">Error loading data.</td></tr>';
                return;
            }
            const count = data.pending_count ?? 0;
            const leads = data.leads || [];
            if (countEl) countEl.textContent = count;
            const leadShowBase = '{{ url("/leads") }}';
            if (!tbody) return;
            if (leads.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">No leads pending response.</td></tr>';
                return;
            }
            tbody.innerHTML = leads.map(function(l) {
                const name = (l.name || '—').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                const phone = (l.phone || '—');
                const masked = phone.length > 4 ? phone.slice(0, 2) + '****' + phone.slice(-4) : phone;
                const assignedAt = l.assigned_at ? new Date(l.assigned_at).toLocaleString('en-IN', { dateStyle: 'medium', timeStyle: 'short' }) : '—';
                return '<tr><td class="px-4 py-3 text-sm text-gray-900">' + name + '</td><td class="px-4 py-3 text-sm text-gray-600">' + masked + '</td><td class="px-4 py-3 text-sm text-gray-600">' + assignedAt + '</td><td class="px-4 py-3"><a href="' + leadShowBase + '/' + (l.lead_id || '') + '" class="text-[#002B45] font-medium hover:underline">View</a></td></tr>';
            }).join('');
        } catch (err) {
            console.error('Error loading leads pending response:', err);
            if (countEl) countEl.textContent = '0';
            if (tbody) tbody.innerHTML = '<tr><td colspan="4" class="px-4 py-6 text-center text-red-600">Error loading data.</td></tr>';
        }
    }

    async function loadDashboardData() {
        try {
            console.log('Loading dashboard data...');
            console.log('API Token available:', !!getToken());
            const dashboardParams = getDashboardFilterParams();
            
            // Load profile for team stats first
            const profile = await apiCall('/profile?' + dashboardParams.toString());
            console.log('Profile API response:', profile);
            
            if (profile && profile.team_stats) {
                document.getElementById('teamMembersCount').textContent = profile.team_stats.total_members || 0;
                document.getElementById('todayProspects').textContent = profile.team_stats.today_prospects || 0;
                // Get pending verifications from team_stats
                document.getElementById('pendingVerifications').textContent = profile.team_stats.pending_verifications || 0;
                // Get assigned leads count
                document.getElementById('assignedLeads').textContent = profile.team_stats.assigned_leads || 0;
                // Get pending tasks count
                document.getElementById('pendingTasks').textContent = profile.team_stats.pending_tasks || 0;
                // Get overdue tasks count
                document.getElementById('overdueTasks').textContent = profile.team_stats.overdue_tasks || 0;
                document.getElementById('previousDayOverdueTasks').textContent = profile.team_stats.previous_day_overdue_tasks || 0;
                const freshLeadsHero = document.getElementById('freshLeadsHero');
                const overdueTasksHero = document.getElementById('overdueTasksHero');
                const previousDayOverdueHero = document.getElementById('previousDayOverdueHero');
                const todayMeetingsHero = document.getElementById('todayMeetingsHero');
                const todayVisitsHero = document.getElementById('todayVisitsHero');
                const todayFollowupsHero = document.getElementById('todayFollowupsHero');
                const todayMeetings = profile.team_stats.today_meetings_count || 0;
                const todayVisits = profile.team_stats.today_visits_count || 0;
                const todayFollowups = profile.team_stats.today_followups_count || 0;
                if (freshLeadsHero) freshLeadsHero.textContent = profile.team_stats.fresh_leads_today || 0;
                if (overdueTasksHero) overdueTasksHero.textContent = profile.team_stats.overdue_tasks || 0;
                if (previousDayOverdueHero) previousDayOverdueHero.textContent = profile.team_stats.previous_day_overdue_tasks || 0;
                if (todayMeetingsHero) todayMeetingsHero.textContent = todayMeetings;
                if (todayVisitsHero) todayVisitsHero.textContent = todayVisits;
                if (todayFollowupsHero) todayFollowupsHero.textContent = todayFollowups;
                renderFavoriteLeads(profile.favorite_leads || []);
                console.log('Team stats updated:', profile.team_stats);
            } else {
                console.error('Profile API failed or no team_stats:', profile);
            }

            await loadRecentTasks();

            // Load dashboard data for incentives and targets
            try {
                const dashboardData = await fetch(`{{ url("/api/dashboard") }}?${dashboardParams.toString()}`, {
                    headers: {
                        ...getDashboardManagerAuthHeaders({
                            'Authorization': `Bearer ${getToken()}`,
                        }),
                    }
                });

                if (dashboardData.ok) {
                    const data = await dashboardData.json();
                    console.log('Dashboard data:', data);
                    console.log('Manager targets data:', data.manager_targets);

                    // Load incentives
                    if (data.incentives) {
                        loadIncentives(data.incentives);
                    }

                    // Load incentive potential
                    if (data.incentive_potential) {
                        loadIncentivePotential(data.incentive_potential);
                    }

                    // Load manager targets
                    if (data.manager_targets) {
                        console.log('Loading manager targets:', data.manager_targets);
                        loadManagerTargets(data.manager_targets);
                    } else {
                        console.warn('No manager_targets data found in response');
                    }

                    // Load team targets
                    if (data.team_targets && data.team_targets.team_totals) {
                        loadTeamTargets(data.team_targets.team_totals);
                    }

                    // Load individual team member cards
                    if (data.team_targets && data.team_targets.team_members) {
                        loadTeamMemberCards(data.team_targets.team_members);
                    }
                } else {
                    console.error('Dashboard API error:', dashboardData.status, dashboardData.statusText);
                    const errorText = await dashboardData.text();
                    console.error('Error response:', errorText);
                }
            } catch (error) {
                console.error('Error fetching dashboard data:', error);
            }

        } catch (error) {
            console.error('Error loading dashboard data:', error);
            console.error('Error details:', error.message, error.stack);
        }
    }

    function renderFavoriteLeads(favoriteLeads) {
        const container = document.getElementById('favoriteLeadsList');
        if (!container) {
            return;
        }

        if (!Array.isArray(favoriteLeads) || favoriteLeads.length === 0) {
            container.innerHTML = '<div class="favorite-leads-empty">No favorite leads yet</div>';
            return;
        }

        container.innerHTML = favoriteLeads.slice(0, 5).map(function(item) {
            const leadId = Number(item.lead_id) || null;
            const name = escapeDashboardHtml(item.name || 'Unknown Lead');
            const remark = escapeDashboardHtml(cleanFavoriteLeadRemark(item.remark || 'No remark added'));
            const status = escapeDashboardHtml(formatFavoriteLeadStatus(item.status || 'new'));
            const phone = String(item.phone || '').replace(/\D/g, '');
            const phoneLabel = phone ? escapeDashboardHtml(item.phone || phone) : 'Phone not available';
            const href = leadId ? `{{ url('/leads') }}/${leadId}` : '#';
            const callButton = phone
                ? `<a href="tel:${phone}" class="favorite-lead-action">Call</a>`
                : `<button type="button" class="favorite-lead-action" disabled>Call</button>`;
            const removeButton = leadId
                ? `<button type="button" class="favorite-lead-action danger" onclick="removeFavoriteLeadFromDashboard(${leadId}, this)">Remove</button>`
                : `<button type="button" class="favorite-lead-action danger" disabled>Remove</button>`;

            return `
                <article class="favorite-lead-item">
                    <div class="favorite-lead-top">
                        <div style="min-width: 0;">
                            <div class="favorite-lead-name">${name}</div>
                            <div class="favorite-lead-phone">${phoneLabel}</div>
                        </div>
                        <span class="favorite-lead-status">${status}</span>
                    </div>
                    <div class="favorite-lead-remark">${remark}</div>
                    <div class="favorite-lead-actions">
                        <a href="${href}" class="favorite-lead-action primary">Open</a>
                        ${callButton}
                        ${removeButton}
                    </div>
                </article>
            `;
        }).join('');
    }

    function cleanFavoriteLeadRemark(remark) {
        return String(remark || 'No remark added')
            .replace(/\[[^\]]+\]\s*/g, '')
            .replace(/\s+/g, ' ')
            .trim()
            .slice(0, 150) || 'No remark added';
    }

    function formatFavoriteLeadStatus(status) {
        return String(status || 'new')
            .replace(/_/g, ' ')
            .replace(/\b\w/g, (char) => char.toUpperCase());
    }

    function escapeDashboardHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    async function removeFavoriteLeadFromDashboard(leadId, button) {
        if (!leadId || !button) {
            return;
        }

        const originalText = button.textContent;
        button.disabled = true;
        button.textContent = 'Removing';

        try {
            const response = await fetch(`${API_BASE_URL}/leads/${leadId}/favorite`, {
                method: 'DELETE',
                headers: getDashboardManagerAuthHeaders(),
                credentials: 'same-origin',
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok || data.success === false) {
                throw new Error(data.message || 'Failed to remove favorite lead');
            }

            if (Array.isArray(data.favorites)) {
                renderFavoriteLeads(data.favorites);
            } else {
                await loadDashboardData();
            }
        } catch (error) {
            console.error('Error removing favorite lead:', error);
            button.disabled = false;
            button.textContent = originalText;
            alert(error.message || 'Failed to remove favorite lead. Please try again.');
        }
    }

    window.removeFavoriteLeadFromDashboard = removeFavoriteLeadFromDashboard;

    const ASM_DASHBOARD_DEFAULTS = {
        today_focus_panel: true,
        today_focus_fresh_leads: true,
        today_focus_overdue: true,
        today_focus_meetings: true,
        today_focus_site_visits: true,
        today_focus_follow_ups: true,
        favorites_panel: true,
        stat_leads_received: true,
        stat_todays_prospects: true,
        stat_pending_verifications: true,
        stat_overdue_tasks: true,
        stat_team_members: true,
        stat_pending_tasks: true,
        manager_targets_section: true,
        team_targets_section: true,
        team_members_cards_section: true,
        incentives_section: true
    };
    const INITIAL_ASM_DASHBOARD_VISIBILITY = @json($dashboardVisibility ?? []);

    const ASM_DASHBOARD_WIDGET_MAP = {
        today_focus_panel: 'asmTodayFocusPanel',
        today_focus_fresh_leads: 'asmTodayFocusFreshLeadsCard',
        today_focus_overdue: 'asmTodayFocusOverdueCard',
        today_focus_meetings: 'asmTodayFocusMeetingsCard',
        today_focus_site_visits: 'asmTodayFocusVisitsCard',
        today_focus_follow_ups: 'asmTodayFocusFollowupsCard',
        favorites_panel: 'asmFavoritesPanelWrap',
        stat_leads_received: 'asmStatLeadsReceived',
        stat_todays_prospects: 'asmStatTodaysProspects',
        stat_pending_verifications: 'asmStatPendingVerifications',
        stat_overdue_tasks: 'asmStatOverdueTasks',
        stat_team_members: 'asmStatTeamMembers',
        stat_pending_tasks: 'asmStatPendingTasks',
        manager_targets_section: 'managerTargetsSection',
        team_targets_section: 'teamTargetsSection',
        team_members_cards_section: 'teamMembersCardsSection',
        incentives_section: 'incentivesSection'
    };

    const ASM_STAT_CARD_KEYS = [
        'stat_leads_received',
        'stat_todays_prospects',
        'stat_pending_verifications',
        'stat_overdue_tasks',
        'stat_team_members',
        'stat_pending_tasks',
    ];

    const ASM_TODAY_FOCUS_CHILD_KEYS = [
        'today_focus_fresh_leads',
        'today_focus_overdue',
        'today_focus_meetings',
        'today_focus_site_visits',
        'today_focus_follow_ups'
    ];

    const IS_ASSISTANT_SALES_MANAGER = @json(auth()->user()->isAssistantSalesManager());
    let asmDashboardVisibility = {
        ...ASM_DASHBOARD_DEFAULTS,
        ...INITIAL_ASM_DASHBOARD_VISIBILITY
    };

    function setAsmWidgetVisibility(id, shouldShow, displayValue = '') {
        const element = document.getElementById(id);
        if (!element) {
            return;
        }

        if (shouldShow) {
            element.style.display = displayValue;
            element.hidden = false;
            element.removeAttribute('aria-hidden');
            return;
        }

        element.style.display = 'none';
        element.hidden = true;
        element.setAttribute('aria-hidden', 'true');
    }

    function collapseAsmStatsGridIfNeeded() {
        const statsGrid = document.getElementById('asmStatsGrid');
        if (!statsGrid) {
            return;
        }

        const hasVisibleCard = ASM_STAT_CARD_KEYS.some(function (key) {
            const element = document.getElementById(ASM_DASHBOARD_WIDGET_MAP[key]);
            return element && element.style.display !== 'none';
        });

        statsGrid.style.display = hasVisibleCard ? '' : 'none';
    }

    function syncAsmTodayFocusVisibility(settings) {
        const panelVisible = !!settings.today_focus_panel;
        setAsmWidgetVisibility('asmTodayFocusPanel', panelVisible);

        if (!panelVisible) {
            return;
        }

        let hasVisibleChild = false;
        ASM_TODAY_FOCUS_CHILD_KEYS.forEach(function (key) {
            const visible = !!settings[key];
            setAsmWidgetVisibility(ASM_DASHBOARD_WIDGET_MAP[key], visible);
            if (visible) {
                hasVisibleChild = true;
            }
        });

        setAsmWidgetVisibility('asmTodayFocusPanel', hasVisibleChild);
    }

    function applyAsmDashboardVisibility(settings) {
        asmDashboardVisibility = { ...ASM_DASHBOARD_DEFAULTS, ...(settings || {}) };

        syncAsmTodayFocusVisibility(asmDashboardVisibility);

        Object.entries(ASM_DASHBOARD_WIDGET_MAP).forEach(function ([key, id]) {
            if (key.startsWith('today_focus_')) {
                return;
            }

            setAsmWidgetVisibility(id, !!asmDashboardVisibility[key]);
        });

        collapseAsmStatsGridIfNeeded();
    }

    async function loadAsmDashboardVisibility() {
        applyAsmDashboardVisibility(asmDashboardVisibility);

        if (!IS_ASSISTANT_SALES_MANAGER) {
            applyAsmDashboardVisibility(ASM_DASHBOARD_DEFAULTS);
            return ASM_DASHBOARD_DEFAULTS;
        }

        try {
            const response = await fetch('/api/sales-manager/dashboard-settings', {
                headers: {
                    ...getDashboardManagerAuthHeaders({
                        'Authorization': `Bearer ${getToken()}`,
                    }),
                },
                credentials: 'same-origin'
            });

            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Failed to load dashboard settings.');
            }

            applyAsmDashboardVisibility(result.dashboard_visibility || ASM_DASHBOARD_DEFAULTS);
            return asmDashboardVisibility;
        } catch (error) {
            console.error('Error loading ASM dashboard visibility:', error);
            applyAsmDashboardVisibility(ASM_DASHBOARD_DEFAULTS);
            return ASM_DASHBOARD_DEFAULTS;
        }
    }

    function loadIncentives(incentives) {
        const pending = Array.isArray(incentives.pending) ? incentives.pending : [];
        const verified = Array.isArray(incentives.verified) ? incentives.verified : [];
        const rejected = Array.isArray(incentives.rejected) ? incentives.rejected : [];

        const pendingTotal = pending.reduce((sum, inc) => sum + parseFloat(inc.amount || 0), 0);
        const totalEarned = parseFloat(incentives.total_earned || 0);

        const setText = (id, value) => {
            const el = document.getElementById(id);
            if (el) el.textContent = value;
        };

        setText('pendingIncentivesCount', pending.length);
        setText('verifiedIncentivesCount', verified.length);
        setText('pendingIncentivesBadge', pending.length);
        setText('verifiedIncentivesBadge', verified.length);
        setText('rejectedIncentivesBadge', rejected.length);
        setText('totalPendingIncentives', `₹${pendingTotal.toFixed(2)}`);
        setText('totalEarnedIncentives', `₹${totalEarned.toFixed(2)}`);

        const initials = (name) => {
            if (!name) return 'NA';
            const parts = String(name).trim().split(/\s+/).filter(Boolean);
            if (parts.length === 0) return 'NA';
            return (parts[0][0] + (parts[1] ? parts[1][0] : '')).toUpperCase();
        };

        const escapeHtml = (str) => String(str ?? '').replace(/[&<>"']/g, (c) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        })[c]);

        const pendingList = document.getElementById('pendingIncentivesList');
        if (pendingList) {
            if (pending.length > 0) {
                pendingList.innerHTML = pending.map(inc => {
                    const customer = inc.site_visit?.customer_name || 'N/A';
                    const statusLabel = inc.status === 'pending_sales_head'
                        ? 'Awaiting Sales Head'
                        : (inc.status === 'pending_crm' ? 'Awaiting CRM' : 'Awaiting Finance');
                    const amount = parseFloat(inc.amount || 0).toFixed(2);
                    return `
                    <div class="group flex items-center gap-3 p-3 bg-white rounded-lg border border-sky-100 hover:border-sky-300 hover:shadow-md transition-all">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-[#006BA6] to-[#0EA5E9] flex items-center justify-center text-white text-xs font-bold flex-shrink-0 shadow-sm">
                            ${escapeHtml(initials(customer))}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-900 truncate">${escapeHtml(customer)}</p>
                            <div class="flex items-center gap-1.5 mt-1">
                                <span class="inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-0.5 rounded-full bg-sky-50 text-[#006BA6] border border-sky-200">
                                    <i class="fas fa-clock text-[8px]"></i> ${escapeHtml(statusLabel)}
                                </span>
                            </div>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <span class="text-base font-extrabold text-[#006BA6]">₹${amount}</span>
                        </div>
                    </div>`;
                }).join('');
            } else {
                pendingList.innerHTML = `
                    <div class="py-10 text-center">
                        <i class="fas fa-inbox text-3xl text-gray-300 mb-2"></i>
                        <p class="text-gray-400 text-sm">No pending incentives</p>
                    </div>`;
            }
        }

        const verifiedList = document.getElementById('verifiedIncentivesList');
        if (verifiedList) {
            if (verified.length > 0) {
                verifiedList.innerHTML = verified.slice(0, 8).map(inc => {
                    const customer = inc.site_visit?.customer_name || 'N/A';
                    const verifiedAt = inc.finance_manager_verified_at || inc.crm_verified_at || inc.sales_head_verified_at;
                    const date = verifiedAt
                        ? new Date(verifiedAt).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })
                        : 'N/A';
                    const amount = parseFloat(inc.amount || 0).toFixed(2);
                    return `
                    <div class="group flex items-center gap-3 p-3 bg-white rounded-lg border border-sky-100 hover:border-sky-300 hover:shadow-md transition-all">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-sky-600 to-[#006BA6] flex items-center justify-center text-white text-xs font-bold flex-shrink-0 shadow-sm">
                            ${escapeHtml(initials(customer))}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-900 truncate">${escapeHtml(customer)}</p>
                            <div class="flex items-center gap-1.5 mt-1">
                                <span class="inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-0.5 rounded-full bg-sky-50 text-sky-700 border border-sky-200">
                                    <i class="fas fa-check-circle text-[8px]"></i> Verified ${escapeHtml(date)}
                                </span>
                            </div>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <span class="text-base font-extrabold text-sky-700">₹${amount}</span>
                        </div>
                    </div>`;
                }).join('');
            } else {
                verifiedList.innerHTML = `
                    <div class="py-10 text-center">
                        <i class="fas fa-trophy text-3xl text-gray-300 mb-2"></i>
                        <p class="text-gray-400 text-sm">No verified incentives yet</p>
                    </div>`;
            }
        }

        const rejectedList = document.getElementById('rejectedIncentivesList');
        if (rejectedList) {
            if (rejected.length > 0) {
                rejectedList.innerHTML = rejected.slice(0, 8).map(inc => {
                    const customer = inc.site_visit?.customer_name || 'N/A';
                    const date = inc.created_at
                        ? new Date(inc.created_at).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })
                        : 'N/A';
                    const amount = parseFloat(inc.amount || 0).toFixed(2);
                    const remark = escapeHtml(inc.rejection_reason || 'No remark shared.');
                    return `
                    <div class="group p-3 bg-rose-50 rounded-lg border border-rose-200 hover:border-rose-300 hover:shadow-md transition-all">
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900 truncate">${escapeHtml(customer)}</p>
                                <div class="flex items-center gap-1.5 mt-1">
                                    <span class="inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-0.5 rounded-full bg-white text-rose-700 border border-rose-200">
                                        <i class="fas fa-ban text-[8px]"></i> Rejected ${escapeHtml(date)}
                                    </span>
                                </div>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <span class="text-base font-extrabold text-rose-700">â‚¹${amount}</span>
                            </div>
                        </div>
                        <p class="mt-2 text-xs text-rose-800"><strong>Remark:</strong> ${remark}</p>
                        <p class="mt-2 text-[11px] text-gray-600">Resubmit from the closed leads incentive form after correcting the amount.</p>
                    </div>`;
                }).join('');
            } else {
                rejectedList.innerHTML = `
                    <div class="py-10 text-center">
                        <i class="fas fa-triangle-exclamation text-3xl text-gray-300 mb-2"></i>
                        <p class="text-gray-400 text-sm">No rejected incentives</p>
                    </div>`;
            }
        }
    }

    function loadIncentivePotential(potential) {
        const potentialAmount = parseFloat(potential.potential || 0);
        const perCloser = parseFloat(potential.incentive_per_closer || 0);
        const targetClosers = potential.target_closers || 0;
        const potentialEl = document.getElementById('incentivePotential');
        const detailsEl = document.getElementById('incentivePotentialDetails');
        if (potentialEl) potentialEl.textContent = `₹${potentialAmount.toFixed(2)}`;
        if (detailsEl) detailsEl.textContent = `${targetClosers} Closers × ₹${perCloser.toFixed(2)}`;
    }

    function getDashboardMetricDisplay(metric) {
        const resolvedMetric = metric || {};
        const mode = resolvedMetric.mode || 'target_based';
        const achieved = Number(resolvedMetric.achieved || 0);
        const target = resolvedMetric.target;
        const percentage = Number(resolvedMetric.percentage || 0);
        const hasTarget = !!resolvedMetric.has_target && target !== null && target !== undefined;
        const isActivityMode = mode === 'activity_based';

        return {
            mode,
            label: resolvedMetric.label || 'Metric',
            subtitle: resolvedMetric.subtitle || (isActivityMode ? 'Completed' : 'Target not set'),
            progressText: isActivityMode
                ? `${achieved} • ${resolvedMetric.subtitle || 'Completed'}`
                : (hasTarget ? `${achieved} / ${target} (${percentage}%)` : `${achieved} • Target not set`),
            percentage: isActivityMode || !hasTarget ? 0 : percentage,
            showProgress: !isActivityMode && hasTarget,
        };
    }

    function renderDashboardMetric(metric, options) {
        const display = getDashboardMetricDisplay(metric);
        const progressNode = document.getElementById(options.progressId);
        const barNode = document.getElementById(options.barId);
        const labelNode = document.getElementById(options.labelId);

        if (labelNode) {
            labelNode.textContent = display.label;
        }

        if (progressNode) {
            progressNode.textContent = display.progressText;
        }

        if (barNode) {
            const container = barNode.parentElement;
            barNode.style.width = `${Math.max(0, Math.min(100, display.percentage))}%`;
            barNode.style.opacity = display.showProgress ? '1' : '0.18';
            if (container) {
                container.style.opacity = display.showProgress ? '1' : '0.55';
            }
        }

        return display;
    }

    function loadManagerTargets(targets) {
        const meetings = renderDashboardMetric(targets.meetings, {
            labelId: 'managerMeetingsLabel',
            progressId: 'managerMeetingsProgress',
            barId: 'managerMeetingsBar',
        });
        renderDashboardMetric(targets.visits, {
            labelId: 'managerVisitsLabel',
            progressId: 'managerVisitsProgress',
            barId: 'managerVisitsBar',
        });
        renderDashboardMetric(targets.closers, {
            labelId: 'managerClosersLabel',
            progressId: 'managerClosersProgress',
            barId: 'managerClosersBar',
        });

        const titleNode = document.getElementById('managerTargetsSectionTitle');
        if (titleNode) {
            titleNode.innerHTML = meetings.mode === 'activity_based'
                ? '<i class="fas fa-bullseye mr-3 text-white"></i>My Activity Overview'
                : '<i class="fas fa-bullseye mr-3 text-white"></i>My Targets vs Achievements';
        }
    }

    function loadTeamTargets(teamTotals) {
        const meetings = renderDashboardMetric(teamTotals.meetings, {
            labelId: 'teamMeetingsLabel',
            progressId: 'teamMeetingsProgress',
            barId: 'teamMeetingsBar',
        });
        renderDashboardMetric(teamTotals.visits, {
            labelId: 'teamVisitsLabel',
            progressId: 'teamVisitsProgress',
            barId: 'teamVisitsBar',
        });
        renderDashboardMetric(teamTotals.closers, {
            labelId: 'teamClosersLabel',
            progressId: 'teamClosersProgress',
            barId: 'teamClosersBar',
        });

        const titleNode = document.getElementById('teamTargetsSectionTitle');
        if (titleNode) {
            titleNode.innerHTML = meetings.mode === 'activity_based'
                ? '<i class="fas fa-users mr-3 text-white"></i>Team Activity Overview'
                : '<i class="fas fa-users mr-3 text-white"></i>Team Targets vs Achievements';
        }
    }

    function loadTeamMemberCards(teamMembers) {
        const container = document.getElementById('teamMembersCardsContainer');
        const sectionTitle = document.getElementById('teamMembersSectionTitle');
        const isActivityMode = !!teamMembers && teamMembers.length > 0 && (teamMembers[0].targets?.meetings?.mode === 'activity_based');
        
        if (!teamMembers || teamMembers.length === 0) {
            if (sectionTitle) {
                sectionTitle.innerHTML = isActivityMode
                    ? '<i class="fas fa-users mr-2 text-indigo-600"></i>Team Members Activity Overview'
                    : '<i class="fas fa-users mr-2 text-indigo-600"></i>Team Members Targets vs Achievements';
            }
            container.innerHTML = `<div class="text-center py-8 text-gray-500 col-span-full"><p>${isActivityMode ? 'No activity found for this range.' : 'No team members found.'}</p></div>`;
            return;
        }

        if (sectionTitle) {
            sectionTitle.innerHTML = isActivityMode
                ? '<i class="fas fa-users mr-2 text-indigo-600"></i>Team Members Activity Overview'
                : '<i class="fas fa-users mr-2 text-indigo-600"></i>Team Members Targets vs Achievements';
        }

        // Generate cards for each team member
        container.innerHTML = teamMembers.map(member => {
            const meetings = getDashboardMetricDisplay(member.targets.meetings);
            const visits = getDashboardMetricDisplay(member.targets.visits);
            const closers = getDashboardMetricDisplay(member.targets.closers);
            
            // Determine card color based on role
            let cardGradient = 'linear-gradient(135deg, #063A1C 0%, #205A44 100%)';
            if (member.user_role === 'telecaller') {
                cardGradient = 'linear-gradient(135deg, #0B4B30 0%, #15803d 100%)';
            } else if (member.user_role === 'sales_executive') {
                cardGradient = 'linear-gradient(135deg, #205A44 0%, #2E7D5F 100%)';
            }

            return `
                <div class="rounded-xl shadow-lg p-6" style="background: ${cardGradient};">
                    <h3 class="text-lg font-bold text-white mb-4 flex items-center">
                        <i class="fas fa-user mr-2 text-white"></i>${member.user_name}
                    </h3>
                    <p class="text-xs text-white opacity-80 mb-4 uppercase">${member.user_role_name}</p>
                    
                    <div class="space-y-4">
                        <!-- Meetings -->
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center">
                                    <i class="fas fa-calendar-alt mr-2 text-white text-sm"></i>
                                    <span class="text-xs font-medium text-white">${meetings.label}</span>
                                </div>
                                <span class="text-xs font-semibold text-white">${meetings.progressText}</span>
                            </div>
                            <div class="w-full bg-gray-300 bg-opacity-30 rounded-full h-1.5 mt-1">
                                <div class="bg-white h-1.5 rounded-full transition-all" style="width: ${meetings.percentage}%; opacity: ${meetings.showProgress ? '1' : '0.18'}"></div>
                            </div>
                        </div>
                        
                        <!-- Visits -->
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center">
                                    <i class="fas fa-map-marker-alt mr-2 text-white text-sm"></i>
                                    <span class="text-xs font-medium text-white">${visits.label}</span>
                                </div>
                                <span class="text-xs font-semibold text-white">${visits.progressText}</span>
                            </div>
                            <div class="w-full bg-gray-300 bg-opacity-30 rounded-full h-1.5 mt-1">
                                <div class="bg-white h-1.5 rounded-full transition-all" style="width: ${visits.percentage}%; opacity: ${visits.showProgress ? '1' : '0.18'}"></div>
                            </div>
                        </div>
                        
                        <!-- Closers -->
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center">
                                    <i class="fas fa-bullseye mr-2 text-white text-sm"></i>
                                    <span class="text-xs font-medium text-white">${closers.label}</span>
                                </div>
                                <span class="text-xs font-semibold text-white">${closers.progressText}</span>
                            </div>
                            <div class="w-full bg-gray-300 bg-opacity-30 rounded-full h-1.5 mt-1">
                                <div class="bg-white h-1.5 rounded-full transition-all" style="width: ${closers.percentage}%; opacity: ${closers.showProgress ? '1' : '0.18'}"></div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    function formatMobileTaskRelativeLabel(task, isOverdue) {
        const scheduledAt = task?.scheduled_at ? new Date(task.scheduled_at) : null;
        if (!scheduledAt || Number.isNaN(scheduledAt.getTime())) {
            return isOverdue ? 'Needs attention' : 'Scheduled task';
        }

        const diffMs = scheduledAt.getTime() - Date.now();
        const minutes = Math.max(1, Math.round(Math.abs(diffMs) / 60000));
        const hours = Math.floor(minutes / 60);
        const remainingMinutes = minutes % 60;

        let duration = '';
        if (hours > 0) {
            duration = `${hours}h`;
            if (remainingMinutes > 0) {
                duration += ` ${remainingMinutes}m`;
            }
        } else {
            duration = `${minutes}m`;
        }

        return isOverdue ? `Overdue by ${duration}` : `Due in ${duration}`;
    }

    function syncMobileTaskDigestLinks() {
        const params = getDashboardFilterParams();
        const pendingLink = document.getElementById('asmPendingMobileMore');
        const pendingUrl = new URL(`{{ route('sales-manager.tasks', ['status' => 'pending']) }}`, window.location.origin);

        params.forEach(function(value, key) {
            pendingUrl.searchParams.set(key, value);
        });

        if (pendingLink) {
            pendingLink.href = pendingUrl.toString();
        }
    }

    function renderMobileTaskDigestList(containerId, tasks, emptyText, isOverdue) {
        const container = document.getElementById(containerId);
        if (!container) {
            return;
        }

        if (!Array.isArray(tasks) || tasks.length === 0) {
            container.innerHTML = `<div class="mobile-task-empty">${emptyText}</div>`;
            return;
        }

        container.innerHTML = tasks.map(function(task) {
            const lead = task.lead || {};
            const displayTitle = String(task.display_title || task.title || lead.name || 'Task')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
            const leadName = String(lead.name || 'Lead')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
            const projectName = String(lead.project_name || lead.project || task.project_name || task.property_name || 'Opportunity')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
            const scheduledText = task.scheduled_at
                ? new Date(task.scheduled_at).toLocaleString('en-IN', {
                    day: '2-digit',
                    month: 'short',
                    hour: '2-digit',
                    minute: '2-digit'
                })
                : 'Not scheduled';
            const cleanPhone = String(lead.phone || '').replace(/[^0-9]/g, '');
            const callHref = cleanPhone ? `tel:${cleanPhone}` : `{{ route('sales-manager.tasks') }}?task=${task.id}`;
            const taskCategory = String(task.category || 'other')
                .replace(/&/g, '&amp;')
                .replace(/'/g, '&#039;');
            const completeAction = isOverdue
                ? `onclick="openDashboardTaskCompletion(${Number(task.id) || 0}, '${taskCategory}')"`
                : '';

            return `
                <div class="mobile-task-card">
                    <span class="mobile-task-accent"></span>
                    <div class="mobile-task-body">
                        <div class="mobile-task-row">
                            <p class="mobile-task-title">${displayTitle}</p>
                            <span class="mobile-task-time">${scheduledText}</span>
                        </div>
                        <div class="mobile-task-meta">${leadName} - ${projectName}</div>
                        <div class="mobile-task-age">${formatMobileTaskRelativeLabel(task, isOverdue)}</div>
                        <div class="mobile-task-actions">
                            <a href="${callHref}" class="mobile-task-action-btn call-btn">
                                <i class="fas fa-phone-alt"></i>
                                <span>Call</span>
                            </a>
                            ${isOverdue ? `
                            <button type="button" class="mobile-task-action-btn complete-btn" ${completeAction}>
                                <i class="fas fa-check-circle"></i>
                                <span>Mark Complete</span>
                            </button>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    // Load recent tasks for mobile view
    async function loadRecentTasks() {
        try {
            const isMobile = window.innerWidth <= 767;
            const recentTasksSection = document.querySelector('.recent-tasks-mobile');
            const pendingCount = document.getElementById('asmPendingMobileCount');
            const overdueCount = document.getElementById('asmOverdueMobileCount');
            const taskFilterQuery = getDashboardFilterParams().toString();
            const overdueParams = new URLSearchParams();
            overdueParams.set('status', 'overdue');
            overdueParams.set('date_filter', 'pod');
            
            if (!recentTasksSection) return;
            
            if (!isMobile) {
                recentTasksSection.style.display = 'none';
                return;
            }
            
            recentTasksSection.style.display = 'block';
            syncMobileTaskDigestLinks();

            const [pendingResult, overdueResult] = await Promise.all([
                apiCall(`/tasks?status=pending&${taskFilterQuery}`),
                apiCall(`/tasks?${overdueParams.toString()}`)
            ]);

            const pendingTasks = pendingResult?.success && Array.isArray(pendingResult.data) ? pendingResult.data : [];
            const overdueTasks = overdueResult?.success && Array.isArray(overdueResult.data) ? overdueResult.data : [];
            const visiblePendingTasks = pendingTasks.filter(function(task) {
                return task?.is_overdue !== true;
            });

            if (pendingCount) pendingCount.textContent = String(visiblePendingTasks.length || 0);
            if (overdueCount) overdueCount.textContent = String(overdueTasks.length || 0);

            renderMobileTaskDigestList('asmPendingMobileList', visiblePendingTasks.slice(0, 3), 'No pending tasks right now.', false);
            renderMobileTaskDigestList('asmOverdueMobileList', overdueTasks.slice(0, 20), 'No overdue tasks. Good control.', true);
        } catch (error) {
            console.error('Error loading recent tasks:', error);
            renderMobileTaskDigestList('asmPendingMobileList', [], 'Unable to load pending tasks.', false);
            renderMobileTaskDigestList('asmOverdueMobileList', [], 'Unable to load overdue tasks.', true);
        }
    }

    // Handle task call button click
    function handleTaskCall(taskId, leadId) {
        // Navigate to tasks page - the task will be highlighted/opened there
        window.location.href = `{{ url('/sales-manager/tasks') }}?task=${taskId}`;
    }

    // Initialize on page load
    (async function() {
        bindSeniorManagerDashboardTabs();
        bindSeniorManagerTeamDashboardControls();
        bindAsmDialerControls();
        hydrateDashboardFilterFromUrl();
        bindDashboardFilterControls();
        await loadAsmDashboardVisibility();
        await loadDashboardData();
    })();
</script>
<script src="{{ asset('js/manager-lead-form.js') }}?v={{ @filemtime(public_path('js/manager-lead-form.js')) ?: time() }}"></script>
@endpush

@extends('sales-executive.layout')

@section('title', 'Tasks - Sales Executive')
@section('page-title', 'Tasks')

@push('styles')
<style>
    .tasks-container {
        background: white;
        padding: 24px;
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        width: 100%;
        box-sizing: border-box;
        max-width: 100%;
        overflow-x: hidden;
    }
    .filter-bar {
        display: flex;
        gap: 12px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }
    .filter-btn {
        padding: 10px 20px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        background: white;
        color: #063A1C;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.3s;
    }
    .filter-btn:hover {
        border-color: #205A44;
        color: #205A44;
    }
    .filter-btn.active {
        background: #205A44;
        color: white;
        border-color: #205A44;
    }
    .filter-btn-type {
        padding: 10px 20px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        background: white;
        color: #063A1C;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.3s;
    }
    .filter-btn-type:hover {
        border-color: #10b981;
        color: #10b981;
    }
    .filter-btn-type.active {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        border-color: #10b981;
    }
    .tasks-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }
    
    /* Form buttons - Always in one line */
    .form-buttons-container {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        align-items: stretch !important;
        justify-content: flex-end !important;
        gap: 12px !important;
    }
    
    .form-cancel-btn,
    .form-submit-btn {
        flex-shrink: 0 !important;
    }
    
    /* List View Styles */
    .tasks-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin-top: 20px;
    }
    
    .tasks-list .task-card {
        display: flex;
        flex-direction: row;
        align-items: center;
        padding: 10px 12px;
        min-height: 50px;
        width: 100%;
        box-sizing: border-box;
        gap: 8px;
    }
    
    /* Hide all other elements in list view - only show name and actions */
    .tasks-list .task-card .task-avatar,
    .tasks-list .task-card .task-content,
    .tasks-list .task-card .task-info,
    .tasks-list .task-card .task-footer,
    .tasks-list .task-card .task-header {
        display: none !important;
    }
    
    .tasks-list .task-name-list {
        flex: 0 0 38%;
        min-width: 0;
        font-size: 14px;
        font-weight: 600;
        color: #063A1C;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        display: block;
        box-sizing: border-box;
        padding-right: 8px;
    }
    
    .tasks-list .task-list-actions {
        display: flex;
        flex: 1 1 auto;
        align-items: center;
        gap: 8px;
        min-width: 0;
        flex-wrap: wrap;
    }
    
    .tasks-list .btn-call-task {
        flex: 1 1 auto;
        min-width: 70px;
        padding: 10px 12px;
        font-size: 13px;
        text-align: center;
        box-sizing: border-box;
    }
    
    .tasks-list .btn-view-lead {
        flex: 1 1 auto;
        min-width: 70px;
        padding: 10px 12px;
        font-size: 12px;
        text-align: center;
        box-sizing: border-box;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        background: #e5e7eb;
        color: #374151;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.2s;
    }
    .tasks-list .btn-view-lead:hover {
        background: #d1d5db;
        color: #063A1C;
        border-color: #205A44;
    }
    
    .tasks-list .task-completed-text {
        flex: 0 0 auto;
        color: #999;
        font-size: 13px;
        text-align: center;
        box-sizing: border-box;
        padding-right: 8px;
    }
    
    /* Toggle Button Styles */
    .view-toggle-btn:hover {
        background: #f0f9f4 !important;
        border-color: #063A1C !important;
    }
    
    .view-toggle-btn:active {
        background: #e0f2e8 !important;
    }

    /* Task filter dropdowns (Status, Type, View) - one line, all screens */
    .task-filters-row {
        flex-wrap: nowrap;
    }
    .task-filters-row .task-filter-select {
        min-width: 140px;
        padding: 10px 36px 10px 14px;
        border: 2px solid #205A44;
        border-radius: 8px;
        background: white;
        color: #063A1C;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23063A1C' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 12px center;
    }
    .task-filters-row .task-filter-select:focus {
        outline: none;
        border-color: #063A1C;
        box-shadow: 0 0 0 3px rgba(6, 58, 28, 0.1);
    }

    @media (max-width: 768px) {
        .tasks-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }
        
        .task-card {
            width: 100%;
            min-width: 0;
            box-sizing: border-box;
            padding: 12px !important;
            border-radius: 10px !important;
        }
        
        /* Improve card appearance - less dense */
        .task-card.overdue {
            border-width: 2px !important;
            border-color: #fca5a5 !important;
            background: #fff5f5 !important;
        }
        
        /* Task header - more compact */
        .task-header {
            margin-bottom: 10px !important;
            padding-bottom: 10px !important;
        }
        
        .task-avatar {
            width: 40px !important;
            height: 40px !important;
            font-size: 16px !important;
            margin-right: 8px !important;
        }
        
        /* List view - name + Call + View Lead Details on mobile */
        .tasks-list .task-card .task-avatar,
        .tasks-list .task-card .task-content,
        .tasks-list .task-card .task-info,
        .tasks-list .task-card .task-footer,
        .tasks-list .task-card .task-header {
            display: none !important;
        }
        
        .tasks-list .task-name-list {
            flex: 0 0 35% !important;
            min-width: 0 !important;
        }
        
        .tasks-list .task-list-actions {
            flex: 1 1 auto !important;
            min-width: 0 !important;
        }
        
        .tasks-list .btn-call-task,
        .tasks-list .btn-view-lead {
            flex: 1 1 auto !important;
            min-width: 60px !important;
            font-size: 12px !important;
            padding: 8px 10px !important;
        }
        
        .tasks-list .btn-view-lead .fa-user {
            display: none;
        }
        
        .tasks-list .task-completed-text {
            flex: 0 0 auto !important;
        }
        
        /* Form buttons - Always in one line, 50%-50% in phone view */
        .form-buttons-container {
            flex-direction: row !important;
            flex-wrap: nowrap !important;
            justify-content: space-between !important;
            gap: 8px !important;
            align-items: stretch !important;
            width: 100% !important;
        }
        
        .form-cancel-btn,
        .form-submit-btn {
            flex: 0 0 50% !important;
            width: 50% !important;
            max-width: 50% !important;
            padding: 12px 16px !important;
            font-size: 13px !important;
            min-height: 44px !important;
            box-sizing: border-box !important;
            flex-shrink: 0 !important;
        }
        
        .form-submit-btn i {
            margin-right: 6px !important;
            font-size: 12px !important;
        }
        
        .task-name {
            font-size: 14px !important;
            font-weight: 600 !important;
            line-height: 1.3 !important;
        }
        
        /* Badges - smaller and cleaner */
        .overdue-badge {
            padding: 4px 8px !important;
            font-size: 9px !important;
            margin-bottom: 4px !important;
            display: inline-block !important;
        }
        
        .status-badge {
            padding: 3px 8px !important;
            font-size: 9px !important;
            margin-top: 4px !important;
        }
        
        /* Task info - more compact */
        .task-info {
            margin-bottom: 10px !important;
        }
        
        .task-info-row {
            font-size: 11px !important;
            margin-bottom: 6px !important;
            gap: 6px !important;
        }
        
        .task-info-row i {
            width: 12px !important;
            font-size: 11px !important;
        }
        
        /* Action buttons - larger and more touch-friendly */
        .task-actions {
            margin-top: 10px !important;
            padding-top: 10px !important;
            gap: 6px !important;
            flex-direction: column !important;
        }
        
        .task-action-btn {
            width: 100% !important;
            padding: 10px 8px !important;
            font-size: 11px !important;
            border-radius: 6px !important;
            min-height: 36px !important;
        }
        
        .task-action-btn i {
            font-size: 12px !important;
        }
        
        .tasks-container {
            padding: 12px !important;
            margin-bottom: 100px !important; /* Extra space for footer */
        }
        .filter-bar {
            flex-direction: row;
            gap: 8px;
            align-items: center;
        }
        
        /* One horizontal line, each dropdown 33.33% - Status, Type, View bagal mein */
        .task-filters-row {
            flex-wrap: nowrap !important;
            gap: 8px;
        }
        .task-filters-row .filter-group {
            flex: 0 0 calc((100% - 16px) / 3) !important;
            min-width: 0 !important;
            max-width: none;
            box-sizing: border-box;
            display: flex;
            align-items: center;
            gap: 0;
        }
        .task-filters-row .filter-group label {
            display: none !important;
        }
        .task-filters-row .task-filter-select {
            flex: 1 1 100% !important;
            min-width: 0 !important;
            width: 100% !important;
        }
        
        .view-toggle-icon-btn {
            flex-shrink: 0;
        }
        
        .view-toggle-icon-btn:hover {
            background: #f0f9f4 !important;
            border-color: #063A1C !important;
        }
        
        .view-toggle-icon-btn:active {
            background: #e0f2e8 !important;
        }

        /* Hide list/grid toggle in mobile view */
        #listViewToggle,
        #listViewToggle i#toggleIcon {
            display: none !important;
        }

        .task-filter-select,
        .date-filter-select {
            flex: 1;
            width: 33.33%;
            padding: 10px 16px;
            border: 2px solid #205A44;
            border-radius: 8px;
            background: white;
            color: #063A1C;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23063A1C' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 40px;
        }
        
        .task-filter-select:focus,
        .date-filter-select:focus {
            outline: none;
            border-color: #063A1C;
            box-shadow: 0 0 0 3px rgba(6, 58, 28, 0.1);
        }
        
        /* Custom date picker on mobile */
        #customDatePicker {
            width: 100%;
            margin-top: 8px;
        }
        
    }
    
    @media (min-width: 769px) {
        /* Desktop date filter styles */
        .date-filter-desktop {
            display: block;
        }
        
        .date-filter-select {
            padding: 10px 16px;
            border: 2px solid #205A44;
            border-radius: 8px;
            background: white;
            color: #063A1C;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23063A1C' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 40px;
            min-width: 150px;
        }
        
        .date-filter-select:focus {
            outline: none;
            border-color: #063A1C;
            box-shadow: 0 0 0 3px rgba(6, 58, 28, 0.1);
        }
        
        #customDatePicker {
            margin-left: 8px;
            padding: 8px 12px;
            border: 2px solid #205A44;
            border-radius: 8px;
            font-size: 14px;
        }
    }
    .task-card {
        background: white;
        border: 2px solid #e0e0e0;
        border-radius: 12px;
        padding: 20px;
        transition: all 0.3s;
    }
    .task-card:hover {
        border-color: #205A44;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
        transform: translateY(-2px);
    }
    .task-card.overdue {
        border-color: #ef4444;
        border-width: 3px;
        background: #fef2f2;
    }
    .task-actions {
        display: flex;
        gap: 8px;
        margin-top: 16px;
        padding-top: 16px;
        border-top: 2px solid #f0f0f0;
    }
    .task-action-btn {
        flex: 1;
        padding: 10px;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }
    .task-action-btn i {
        font-size: 14px;
    }
    .btn-call {
        background: linear-gradient(135deg, #205A44 0%, #063A1C 100%);
        color: white;
    }
    .btn-call:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(32, 90, 68, 0.3);
    }
    .task-header {
        display: flex;
        align-items: center;
        margin-bottom: 16px;
        padding-bottom: 16px;
        border-bottom: 2px solid #f0f0f0;
    }
    .task-avatar {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: linear-gradient(135deg, #205A44 0%, #063A1C 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 20px;
        font-weight: 700;
        margin-right: 12px;
    }
    .task-name {
        font-size: 18px;
        font-weight: 600;
        color: #063A1C;
        margin: 0;
    }
    .task-info {
        margin-bottom: 12px;
    }
    .task-info-row {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 8px;
        font-size: 14px;
        color: #063A1C;
    }
    .task-info-row i {
        color: #205A44;
        width: 16px;
    }
    .task-footer {
        margin-top: 16px;
        padding-top: 16px;
        border-top: 2px solid #f0f0f0;
    }
    .btn-call-task {
        width: 100%;
        padding: 12px;
        background: linear-gradient(135deg, #205A44 0%, #063A1C 100%);
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    .btn-call-task:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }
    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 500;
        margin-top: 8px;
    }
    .status-pending { background: #fef3c7; color: #92400e; }
    .status-in_progress { background: #dbeafe; color: #1e40af; }
    .status-completed { background: #d1fae5; color: #065f46; }
    .status-rescheduled { background: #e9d5ff; color: #6b21a8; }
    .overdue-badge {
        display: inline-block;
        padding: 6px 14px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        background: #ef4444;
        color: white;
        margin-top: 8px;
        box-shadow: 0 2px 4px rgba(239, 68, 68, 0.3);
    }
    .task-card.overdue {
        border-color: #ef4444;
        border-width: 3px;
    }
    .loading-state, .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #B3B5B4;
    }
    .loading-state i, .empty-state i {
        font-size: 48px;
        color: #205A44;
        margin-bottom: 16px;
    }
    .empty-state h3 {
        font-size: 24px;
        margin-bottom: 8px;
        color: #063A1C;
    }
    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        align-items: center;
        justify-content: center;
        overflow-y: auto;
    }
    .modal.active {
        display: flex;
    }
    .modal-content {
        background: white;
        border-radius: 12px;
        padding: 30px;
        max-width: 600px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        margin: auto;
        position: relative;
    }
    
    /* Confirmation modal should be above other modals */
    #confirmModal {
        z-index: 10001;
    }
    
    #confirmModal .modal-content {
        z-index: 10002;
    }
    
    /* Ensure modal is centered on all screen sizes */
    @media (max-width: 768px) {
        .modal-content {
            width: 95%;
            padding: 20px;
            padding-bottom: 100px !important; /* Extra space for footer */
            margin: 20px auto;
            margin-bottom: 100px !important; /* Prevent footer overlap */
            max-height: calc(100vh - 150px) !important; /* Account for footer */
            overflow-y: auto;
        }
        
        /* Add spacing to form buttons on mobile */
        .modal-content button[type="submit"],
        .modal-content .btn,
        .modal-footer {
            margin-bottom: 80px !important; /* Space above footer */
        }
    }
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
        padding-bottom: 16px;
        border-bottom: 2px solid #f0f0f0;
    }
    .modal-header h3 {
        margin: 0;
        font-size: 24px;
        color: #063A1C;
    }
    .close-modal {
        background: none;
        border: none;
        font-size: 28px;
        color: #B3B5B4;
        cursor: pointer;
        padding: 0;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .close-modal:hover {
        color: #063A1C;
    }
    .outcome-buttons {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
        margin-top: 20px;
    }
    .outcome-btn {
        padding: 16px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        background: white;
        cursor: pointer;
        font-size: 16px;
        font-weight: 600;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    .outcome-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    .btn-interested { border-color: #10b981; color: #10b981; }
    .btn-interested:hover { background: #10b981; color: white; }
    .btn-not-interested { border-color: #ef4444; color: #ef4444; }
    .btn-not-interested:hover { background: #ef4444; color: white; }
    .btn-cnp { border-color: #f59e0b; color: #f59e0b; }
    .btn-cnp:hover { background: #f59e0b; color: white; }
    .salesExecutive-time-option-btn {
        transition: all 0.2s ease;
    }
    .salesExecutive-time-option-btn:hover {
        border-color: #f59e0b !important;
        background: #fff9e6 !important;
        transform: translateY(-1px);
    }
    .salesExecutive-time-option-btn.selected {
        border-color: #f59e0b !important;
        background: #f59e0b !important;
        color: white !important;
        font-weight: 600;
    }
    .btn-call-again { border-color: #3b82f6; color: #3b82f6; }
    .btn-call-again:hover { background: linear-gradient(135deg, #16a34a 0%, #15803d 100%); color: white; }
    .btn-block { border-color: #dc2626; color: #dc2626; }
    .btn-block:hover { background: #dc2626; color: white; }
    .form-group {
        margin-bottom: 20px;
    }
    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #063A1C;
    }
    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 12px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 16px;
        background: #ffffff;
        color: #063A1C;
    }
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #205A44;
    }
    .form-group input[readonly] {
        background: #f5f5f5;
        cursor: not-allowed;
    }
    .form-footer {
        display: flex;
        gap: 12px;
        margin-top: 24px;
        padding-top: 24px;
        border-top: 2px solid #f0f0f0;
    }
    .btn-whatsapp-form {
        flex: 1;
        padding: 12px;
        background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
        color: white;
        border: none;
        box-shadow: 0 2px 4px rgba(21, 128, 61, 0.3);
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    .btn-whatsapp-form:hover {
        background: linear-gradient(135deg, #15803d 0%, #166534 100%);
        box-shadow: 0 4px 8px rgba(21, 128, 61, 0.4);
        transform: translateY(-1px);
    }
    .btn-save {
        flex: 1;
        padding: 12px;
        background: linear-gradient(135deg, #205A44 0%, #063A1C 100%);
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
    }
    .btn-save:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }
    
    /* View Switcher Styles */
    .view-switcher {
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .view-btn {
        white-space: nowrap;
    }
    .view-btn:hover {
        background: #F7F6F3 !important;
    }
    .view-btn.active {
        background: #205A44 !important;
        color: white !important;
    }
    .view-btn i {
        margin-right: 5px;
    }
    
    /* Calendar View Styles */
    #calendarContainer {
        width: 100%;
        min-height: 700px;
    }
    
    /* FullCalendar Custom Styles */
    .fc {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
    }
    
    .fc-header-toolbar {
        margin-bottom: 20px !important;
        padding: 15px !important;
        background: #F7F6F3 !important;
        border-radius: 8px !important;
        flex-wrap: wrap !important;
    }
    
    .fc-toolbar-chunk {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .fc-button {
        background: #205A44 !important;
        border-color: #205A44 !important;
        color: white !important;
        padding: 8px 16px !important;
        border-radius: 6px !important;
        font-weight: 500 !important;
        font-size: 14px !important;
        height: auto !important;
        line-height: normal !important;
    }
    
    .fc-button:hover {
        background: #063A1C !important;
        border-color: #063A1C !important;
    }
    
    .fc-button-active {
        background: #063A1C !important;
        border-color: #063A1C !important;
    }
    
    .fc-today-button {
        background: #205A44 !important;
        border-color: #205A44 !important;
        margin: 0 5px !important;
    }
    
    .fc-toolbar-title {
        font-size: 24px !important;
        font-weight: 700 !important;
        color: #063A1C !important;
        margin: 0 10px !important;
    }
    
    .fc-daygrid-day {
        border-color: #E5DED4 !important;
        min-height: 100px !important;
    }
    
    .fc-daygrid-day-number {
        padding: 8px !important;
        color: #063A1C !important;
        font-weight: 500 !important;
        font-size: 14px !important;
        display: inline-block !important;
        width: auto !important;
        min-width: 24px !important;
        text-align: left !important;
    }
    
    .fc-day-today {
        background: #F7F6F3 !important;
    }
    
    .fc-day-today .fc-daygrid-day-number {
        color: #205A44 !important;
        font-weight: 700 !important;
        font-size: 16px !important;
    }
    
    .fc-daygrid-day-frame {
        padding: 8px !important;
    }
    
    .fc-daygrid-day-top {
        flex-direction: row !important;
        justify-content: flex-start !important;
        padding: 4px 8px !important;
    }
    
    .fc-daygrid-day {
        padding: 4px !important;
        position: relative !important;
    }
    
    .fc-scrollgrid-sync-table {
        width: 100% !important;
        table-layout: fixed !important;
    }
    
    .fc-col-header-cell,
    .fc-daygrid-day {
        width: 14.28% !important;
    }
    
    .fc-daygrid-day-events {
        margin-top: 4px !important;
    }
    
    .fc-daygrid-event-harness {
        margin: 2px 4px !important;
    }
    
    .fc-col-header-cell {
        background: #F7F6F3 !important;
        padding: 12px 0 !important;
        border-color: #E5DED4 !important;
    }
    
    .fc-col-header-cell-cushion {
        color: #063A1C !important;
        font-weight: 600 !important;
        font-size: 14px !important;
        text-decoration: none !important;
    }
    
    .fc-event {
        border-radius: 4px !important;
        padding: 4px 6px !important;
        cursor: pointer !important;
        border: none !important;
        margin: 2px 0 !important;
    }
    
    .fc-event:hover {
        opacity: 0.9 !important;
        transform: scale(1.02) !important;
    }
    
    .fc-event-title {
        font-size: 12px !important;
        font-weight: 600 !important;
        padding: 2px 4px !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
    }
    
    .fc-more-link {
        color: #205A44 !important;
        font-weight: 600 !important;
        font-size: 12px !important;
        margin-top: 4px !important;
    }
    
    .fc-more-link:hover {
        color: #063A1C !important;
    }
    
    .fc-daygrid-event {
        white-space: nowrap !important;
        overflow: hidden !important;
    }
    
    /* Kanban View Styles */
    .kanban-column {
        min-width: 300px;
        background: #F7F6F3;
        border-radius: 12px;
        padding: 16px;
    }
    .kanban-column-header {
        font-size: 16px;
        font-weight: 600;
        color: #063A1C;
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 2px solid #E5DED4;
    }
    .kanban-task-card {
        background: white;
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 12px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        cursor: move;
        transition: all 0.3s;
    }
    .kanban-task-card:hover {
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        transform: translateY(-2px);
    }
    .kanban-task-card.overdue {
        border-left: 4px solid #ef4444;
    }
    
    /* Responsive View Switcher */
    @media (max-width: 768px) {
        /* Hide View Switcher on mobile */
        .view-switcher-container {
            display: none !important;
        }
        
        .view-switcher {
            width: 100%;
        }
        .view-btn {
            flex: 1;
            padding: 8px 12px !important;
            font-size: 12px !important;
        }
        .view-btn i {
            display: none;
        }
        .filter-bar {
            width: 100%;
        }
        
        /* Hide Kanban and Calendar views on mobile - only show List view */
        #kanban-view-container,
        #calendar-view-container {
            display: none !important;
        }
    }
    
    @media (max-width: 480px) {
        .view-btn {
            padding: 6px 8px !important;
            font-size: 11px !important;
        }
    }
</style>
@endpush

@section('content')
    <div class="tasks-container">
        <!-- Filter and View: Dropdowns (Status, Type, View) -->
        <div class="task-filters-row" style="display: flex; align-items: center; margin-bottom: 20px; gap: 12px;">
            <div class="filter-group" style="display: flex; align-items: center; gap: 8px;">
                <label style="font-size: 14px; color: #063A1C; font-weight: 500; white-space: nowrap;">Status:</label>
                <select id="taskStatusFilterDropdown" class="task-filter-select">
                    <option value="pending">Pending</option>
                    <option value="completed">Completed</option>
                    <option value="rescheduled">Rescheduled</option>
                    <option value="all">All</option>
                </select>
            </div>
            <div class="filter-group" style="display: flex; align-items: center; gap: 8px;">
                <label style="font-size: 14px; color: #063A1C; font-weight: 500; white-space: nowrap;">Type:</label>
                <select id="taskTypeFilterDropdown" class="task-filter-select">
                    <option value="all">All</option>
                    <option value="calling">Calling</option>
                    <option value="pre_meeting_reminder">Meeting</option>
                    <option value="follow_up">Follow Up</option>
                    <option value="cnp_retry">CNP Retry</option>
                    <option value="call_again">Call Again</option>
                </select>
            </div>
            <div class="filter-group" style="display: flex; align-items: center; gap: 8px;">
                <label style="font-size: 14px; color: #063A1C; font-weight: 500; white-space: nowrap;">View:</label>
                <select id="taskViewDropdown" class="task-filter-select">
                    <option value="list">List</option>
                    <option value="kanban">Kanban</option>
                    <option value="calendar">Calendar</option>
                </select>
            </div>
            <button id="listViewToggle" onclick="toggleListView()" class="view-toggle-icon-btn list-view-toggle-mobile-hide" style="width: 48px; height: 48px; padding: 0; border: 2px solid #205A44; border-radius: 8px; background: white; color: #063A1C; font-size: 18px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.3s; flex-shrink: 0;" title="Toggle list/grid">
                <i class="fas fa-th" id="toggleIcon"></i>
            </button>
        </div>

        <!-- Task Views Container -->
        <div id="list-view-container">
            <div id="tasksContent" class="tasks-grid">
                <div class="loading-state">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Loading tasks...</p>
                </div>
            </div>
        </div>
        
        <!-- Kanban View Container (Hidden by default) -->
        <div id="kanban-view-container" style="display: none;">
            <div id="kanbanBoard" style="display: flex; gap: 20px; overflow-x: auto; padding-bottom: 20px;">
                <!-- Columns will be added dynamically -->
            </div>
        </div>
        
        <!-- Calendar View Container (Hidden by default) -->
        <div id="calendar-view-container" style="display: none; width: 100%; overflow-x: auto;">
            <div id="calendarContainer" style="width: 100%; min-height: 700px; background: white; border-radius: 12px;"></div>
        </div>
    </div>

    <!-- Post-Call Popup Modal -->
    <div id="postCallModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="postCallTitle">Call Outcome</h3>
                <button class="close-modal" onclick="closePostCallModal()">&times;</button>
            </div>
            <div id="postCallBody">
                <p>Select the outcome of this call:</p>
                <div class="outcome-buttons">
                    <button class="outcome-btn btn-interested" onclick="handleInterested()">
                        <i class="fas fa-check-circle"></i>
                        Interested
                    </button>
                    <button class="outcome-btn btn-not-interested" onclick="handleNotInterested()">
                        <i class="fas fa-times-circle"></i>
                        Not Interested
                    </button>
                    <button class="outcome-btn btn-cnp" onclick="handleCNP()">
                        <i class="fas fa-phone-slash"></i>
                        CNP
                    </button>
                    <button class="outcome-btn btn-call-again" onclick="handleCallAgain()">
                        <i class="fas fa-redo"></i>
                        Call Again
                    </button>
                    <button class="outcome-btn btn-block" onclick="handleBlock()">
                        <i class="fas fa-ban"></i>
                        Block
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="salesExecutiveOutcomeRemarkModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3 id="salesExecutiveOutcomeRemarkTitle">Add Remark</h3>
                <button class="close-modal" onclick="closeSalesExecutiveOutcomeRemarkModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="salesExecutiveOutcomeRemarkInput">Remark <span style="color:#6b7280; font-weight:400;">(optional)</span></label>
                    <textarea id="salesExecutiveOutcomeRemarkInput" rows="4" placeholder="Add context for this outcome..."></textarea>
                </div>
                <div class="form-footer">
                    <button type="button" class="btn-cancel" onclick="closeSalesExecutiveOutcomeRemarkModal()">Cancel</button>
                    <button type="button" class="btn-save" id="salesExecutiveOutcomeRemarkSubmitBtn" onclick="submitSalesExecutiveOutcomeRemark()">Continue</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Prospect Form Modal -->
    <div id="prospectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Prospect Details</h3>
                <button class="close-modal" onclick="closeProspectModal()">&times;</button>
            </div>
            <form id="prospectForm">
                <div class="form-group">
                    <label>Customer Name *</label>
                    <input type="text" id="customerName" name="customer_name" required>
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" id="prospectPhone" name="phone" readonly>
                </div>
                <div class="form-group">
                    <label>Budget</label>
                    <input type="text" id="budget" name="budget">
                </div>
                <div class="form-group">
                    <label>Preferred Location</label>
                    <input type="text" id="preferredLocation" name="preferred_location">
                </div>
                <div class="form-group">
                    <label>Size</label>
                    <input type="text" id="size" name="size">
                </div>
                <div class="form-group">
                    <label>Purpose *</label>
                    <select id="purpose" name="purpose" required>
                        <option value="end_user">End User</option>
                        <option value="investment">Investment</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Possession</label>
                    <input type="text" id="possession" name="possession">
                </div>
                <div class="form-group">
                    <label>Assign To</label>
                    <input type="text" id="assignTo" name="assign_to" readonly>
                </div>
                <div class="form-group">
                    <label>Lead Score (Rating) *</label>
                    <div class="star-rating-container" style="display: flex; align-items: center; gap: 8px; margin-top: 8px;">
                        <div class="star-rating" id="starRating" style="display: flex; gap: 4px; font-size: 28px; cursor: pointer;">
                            <span class="star" data-rating="1" style="color: #d1d5db;">☆</span>
                            <span class="star" data-rating="2" style="color: #d1d5db;">☆</span>
                            <span class="star" data-rating="3" style="color: #d1d5db;">☆</span>
                            <span class="star" data-rating="4" style="color: #d1d5db;">☆</span>
                            <span class="star" data-rating="5" style="color: #d1d5db;">☆</span>
                        </div>
                        <span id="ratingText" style="color: #6b7280; font-size: 14px; margin-left: 8px;"></span>
                    </div>
                    <input type="hidden" id="leadScore" name="lead_score" required>
                    <small style="color: #6b7280; font-size: 12px; display: block; margin-top: 4px;">Click on stars to rate the lead quality (1 = lowest, 5 = highest)</small>
                </div>
                <div class="form-group">
                    <label>Remark *</label>
                    <textarea id="remark" name="remark" rows="4" required></textarea>
                </div>
                <div class="form-footer">
                    <button type="button" class="btn-whatsapp-form" onclick="openWhatsAppFromForm()">
                        <i class="fab fa-whatsapp"></i>
                        WhatsApp
                    </button>
                    <button type="submit" class="btn-save">Save To Manager</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Call Again DateTime Picker Modal -->
    <div id="rescheduleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Reschedule Call</h3>
                <button class="close-modal" onclick="closeRescheduleModal()">&times;</button>
            </div>
            <div class="form-group">
                <label>Select Date & Time *</label>
                <input type="datetime-local" id="rescheduleDateTime" required>
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea id="rescheduleNotes" rows="3"></textarea>
            </div>
            <div class="form-footer">
                <button type="button" class="btn-save" onclick="saveReschedule()" style="width: 100%;">
                    Schedule Call
                </button>
            </div>
        </div>
    </div>

    <!-- CNP Time Selection Modal -->
    <div id="SalesExecutiveCnpTimeSelectionModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3 id="salesExecutiveCnpModalTitle">Select Retry Time for CNP</h3>
                <button class="close-modal" onclick="cancelSalesExecutiveCnpTimeSelection()">&times;</button>
            </div>
            <div class="modal-body">
                <div style="padding: 20px;">
                    <p id="salesExecutiveCnpModalText" style="font-size: 14px; color: #666; margin-bottom: 20px;">
                        Choose when to retry this call:
                    </p>
                    
                    <!-- Quick Time Options -->
                    <div id="salesExecutiveQuickTimeOptions" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 20px;">
                        <button type="button" class="salesExecutive-time-option-btn" onclick="selectSalesExecutiveCnpTime(15)" data-minutes="15" style="padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; background: white; color: #333; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.2s;">
                            15 Minutes
                        </button>
                        <button type="button" class="salesExecutive-time-option-btn" onclick="selectSalesExecutiveCnpTime(30)" data-minutes="30" style="padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; background: white; color: #333; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.2s;">
                            30 Minutes
                        </button>
                        <button type="button" class="salesExecutive-time-option-btn" onclick="selectSalesExecutiveCnpTime(60)" data-minutes="60" style="padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; background: white; color: #333; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.2s;">
                            1 Hour
                        </button>
                        <button type="button" class="salesExecutive-time-option-btn" onclick="selectSalesExecutiveCnpTime(120)" data-minutes="120" style="padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; background: white; color: #333; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.2s;">
                            2 Hours
                        </button>
                    </div>
                    
                    <!-- Custom Option -->
                    <div id="salesExecutiveCustomOptionWrap" style="margin-bottom: 20px;">
                        <button type="button" class="salesExecutive-time-option-btn" onclick="showSalesExecutiveCustomTimePicker()" id="salesExecutiveCustomTimeOptionBtn" style="width: 100%; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; background: white; color: #333; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.2s;">
                            Custom Date & Time
                        </button>
                    </div>
                    
                    <!-- Custom Date-Time Picker (hidden by default) -->
                    <div id="salesExecutiveCustomTimePickerContainer" style="display: none; padding: 16px; background: #f8f9fa; border-radius: 8px; margin-bottom: 20px;">
                        <div style="margin-bottom: 12px;">
                            <label style="display: block; font-size: 14px; font-weight: 500; color: #333; margin-bottom: 6px;">
                                <strong>Date</strong> <span style="color: #d32f2f;">*</span>
                            </label>
                            <input type="date" 
                                   id="SalesExecutiveCnpCustomDate" 
                                   min=""
                                   style="width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 14px; font-weight: 500; color: #333; margin-bottom: 6px;">
                                <strong>Time</strong> <span style="color: #d32f2f;">*</span>
                            </label>
                            <input type="time" 
                                   id="SalesExecutiveCnpCustomTime"
                                   style="width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        </div>
                        <small style="display: block; margin-top: 8px; color: #666; font-size: 12px;">
                            Select a future date and time for the retry call
                        </small>
                    </div>
                    
                    <!-- Selected Time Display -->
                    <div id="salesExecutiveSelectedTimeDisplay" style="padding: 12px; background: #e8f5e9; border-radius: 6px; margin-bottom: 20px; display: none;">
                        <p style="font-size: 14px; color: #2e7d32; margin: 0;">
                            <strong>Selected:</strong> <span id="salesExecutiveSelectedTimeText"></span>
                        </p>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="salesExecutiveOutcomeRemark">Remark <span style="color:#6b7280; font-weight:400;">(optional)</span></label>
                        <textarea id="salesExecutiveOutcomeRemark" rows="3" placeholder="Add CNP context..." style="width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="display: flex; gap: 12px; justify-content: flex-end; padding: 16px 20px; border-top: 1px solid #e0e0e0;">
                <button type="button" onclick="cancelSalesExecutiveCnpTimeSelection()" style="padding: 10px 20px; border: 1px solid #ddd; border-radius: 6px; background: white; color: #333; cursor: pointer; font-size: 14px; font-weight: 500;">
                    Cancel
                </button>
                <button type="button" id="salesExecutiveCnpConfirmBtn" onclick="confirmSalesExecutiveCnpTimeSelection()" style="padding: 10px 20px; background: #f59e0b; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500;">
                    Confirm
                </button>
            </div>
        </div>
    </div>

    <!-- CNP Time Selection Modal -->
    <div id="SalesExecutiveCnpTimeSelectionModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3>Select Retry Time for CNP</h3>
                <button class="close-modal" onclick="cancelSalesExecutiveCnpTimeSelection()">&times;</button>
            </div>
            <div class="modal-body">
                <div style="padding: 20px;">
                    <p style="font-size: 14px; color: #666; margin-bottom: 20px;">
                        Choose when to retry this call:
                    </p>
                    
                    <!-- Quick Time Options -->
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 20px;">
                        <button type="button" class="salesExecutive-time-option-btn" onclick="selectSalesExecutiveCnpTime(15)" data-minutes="15" style="padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; background: white; color: #333; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.2s;">
                            15 Minutes
                        </button>
                        <button type="button" class="salesExecutive-time-option-btn" onclick="selectSalesExecutiveCnpTime(30)" data-minutes="30" style="padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; background: white; color: #333; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.2s;">
                            30 Minutes
                        </button>
                        <button type="button" class="salesExecutive-time-option-btn" onclick="selectSalesExecutiveCnpTime(60)" data-minutes="60" style="padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; background: white; color: #333; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.2s;">
                            1 Hour
                        </button>
                        <button type="button" class="salesExecutive-time-option-btn" onclick="selectSalesExecutiveCnpTime(120)" data-minutes="120" style="padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; background: white; color: #333; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.2s;">
                            2 Hours
                        </button>
                    </div>
                    
                    <!-- Custom Option -->
                    <div style="margin-bottom: 20px;">
                        <button type="button" class="salesExecutive-time-option-btn" onclick="showSalesExecutiveCustomTimePicker()" id="salesExecutiveCustomTimeOptionBtn" style="width: 100%; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; background: white; color: #333; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.2s;">
                            Custom Date & Time
                        </button>
                    </div>
                    
                    <!-- Custom Date-Time Picker (hidden by default) -->
                    <div id="salesExecutiveCustomTimePickerContainer" style="display: none; padding: 16px; background: #f8f9fa; border-radius: 8px; margin-bottom: 20px;">
                        <div style="margin-bottom: 12px;">
                            <label style="display: block; font-size: 14px; font-weight: 500; color: #333; margin-bottom: 6px;">
                                <strong>Date</strong> <span style="color: #d32f2f;">*</span>
                            </label>
                            <input type="date" 
                                   id="SalesExecutiveCnpCustomDate" 
                                   min=""
                                   style="width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 14px; font-weight: 500; color: #333; margin-bottom: 6px;">
                                <strong>Time</strong> <span style="color: #d32f2f;">*</span>
                            </label>
                            <input type="time" 
                                   id="SalesExecutiveCnpCustomTime"
                                   style="width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        </div>
                        <small style="display: block; margin-top: 8px; color: #666; font-size: 12px;">
                            Select a future date and time for the retry call
                        </small>
                    </div>
                    
                    <!-- Selected Time Display -->
                    <div id="salesExecutiveSelectedTimeDisplay" style="padding: 12px; background: #e8f5e9; border-radius: 6px; margin-bottom: 20px; display: none;">
                        <p style="font-size: 14px; color: #2e7d32; margin: 0;">
                            <strong>Selected:</strong> <span id="salesExecutiveSelectedTimeText"></span>
                        </p>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="display: flex; gap: 12px; justify-content: flex-end; padding: 16px 20px; border-top: 1px solid #e0e0e0;">
                <button type="button" onclick="cancelSalesExecutiveCnpTimeSelection()" style="padding: 10px 20px; border: 1px solid #ddd; border-radius: 6px; background: white; color: #333; cursor: pointer; font-size: 14px; font-weight: 500;">
                    Cancel
                </button>
                <button type="button" onclick="confirmSalesExecutiveCnpTimeSelection()" style="padding: 10px 20px; background: #f59e0b; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500;">
                    Confirm
                </button>
            </div>
        </div>
    </div>

    <!-- Custom Confirmation Modal -->
    <div id="confirmModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3 id="confirmModalTitle">Confirm Action</h3>
                <button class="close-modal" onclick="closeConfirmModal()">&times;</button>
            </div>
            <div style="padding: 20px;">
                <p id="confirmModalMessage" style="font-size: 16px; color: #063A1C; margin-bottom: 24px; line-height: 1.6;"></p>
                <div class="form-footer" style="justify-content: flex-end;">
                    <button type="button" onclick="closeConfirmModal()" style="padding: 12px 24px; border: 2px solid #e0e0e0; border-radius: 8px; background: white; color: #063A1C; font-size: 16px; font-weight: 600; cursor: pointer; margin-right: 12px;">
                        Cancel
                    </button>
                    <button type="button" id="confirmModalOkBtn" onclick="executeConfirmAction()" style="padding: 12px 24px; border: none; border-radius: 8px; background: linear-gradient(135deg, #205A44 0%, #063A1C 100%); color: white; font-size: 16px; font-weight: 600; cursor: pointer;">
                        OK
                    </button>
                </div>
            </div>
        </div>
</div>
@include('partials.lead-cloud-call-menu')
@endsection

@push('scripts')
<!-- FullCalendar JS -->
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.5/main.min.js'></script>
<script>
    var API_BASE_URL = '{{ url("/api/telecaller") }}';
    let currentTaskId = null;
    let currentLeadData = null;
    let currentStatus = 'pending';
    let currentTaskType = null; // Track current task type filter (null = all types)
    let currentTasksArray = []; // Store tasks array globally for button clicks
    let calendarInstance = null; // FullCalendar instance
    let isListView = localStorage.getItem('salesExecutive_task_list_view') === 'true'; // Track list/grid view

    // Helper function to show notifications (fallback to alert if custom notification not available)
    function showAlert(message, type = 'success', duration = 2500) {
        if (typeof showNotification === 'function') {
            showNotification(message, type, duration);
        } else {
            alert(message);
        }
    }

    function getToken() {
        var token = localStorage.getItem('sales-executive_token');
        if (token) return token;
        var meta = document.querySelector('meta[name="api-token"]');
        if (meta && meta.getAttribute('content')) {
            token = meta.getAttribute('content').trim();
            if (token) {
                localStorage.setItem('sales-executive_token', token);
                return token;
            }
        }
        var sessionToken = '{{ session("sales_executive_api_token") ?? session("telecaller_api_token") ?? session("api_token") ?? "" }}';
        if (sessionToken) {
            localStorage.setItem('sales-executive_token', sessionToken);
            return sessionToken;
        }
        return null;
    }

    async function apiCall(endpoint, options = {}) {
        const token = getToken();
        if (!token) {
            console.error('No token found, redirecting to login');
            setTimeout(() => {
                window.location.href = '{{ route("login") }}';
            }, 3000);
            return { success: false, message: 'Authentication required. Redirecting to login...' };
        }

        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': `Bearer ${token}`,
            },
        };

        const url = `${API_BASE_URL}${endpoint}`;
        console.log('Making API call to:', url);

        try {
            const fetchOptions = {
                ...defaultOptions,
                ...options,
                headers: { ...defaultOptions.headers, ...options.headers },
            };
            
            // Set method
            fetchOptions.method = options.method || 'GET';
            
            // Set body for POST/PUT/PATCH
            if (options.body && (fetchOptions.method === 'POST' || fetchOptions.method === 'PUT' || fetchOptions.method === 'PATCH')) {
                if (typeof options.body === 'string') {
                    fetchOptions.body = options.body;
                } else {
                    fetchOptions.body = JSON.stringify(options.body);
                    // Ensure Content-Type is set to JSON
                    if (!fetchOptions.headers['Content-Type'] && !fetchOptions.headers['content-type']) {
                        fetchOptions.headers['Content-Type'] = 'application/json';
                    }
                }
            }
            
            const response = await fetch(url, fetchOptions);

            console.log('Response status:', response.status);

            // Only logout on 401 (Unauthorized), not on 419 (CSRF token)
            if (response.status === 401) {
                console.error('Unauthorized - clearing token');
                localStorage.removeItem('sales-executive_token');
                localStorage.removeItem('salesExecutive_user');
                setTimeout(() => {
                    window.location.href = '{{ route("login") }}';
                }, 2000);
                return { success: false, message: 'Session expired. Redirecting to login...' };
            }

            // For 419 (CSRF token mismatch), don't logout - just return error with detailed debug info
            if (response.status === 419) {
                const errorText = await response.text();
                console.error('=== CSRF TOKEN MISMATCH (419) ERROR DEBUG ===');
                console.error('Request URL:', url);
                console.error('Request Method:', fetchOptions.method);
                console.error('Request Headers:', fetchOptions.headers);
                console.error('Request Body:', fetchOptions.body);
                console.error('Response Status:', response.status);
                console.error('Response Headers:', Object.fromEntries(response.headers.entries()));
                console.error('Response Text:', errorText);
                console.error('Has Bearer Token:', !!getToken());
                console.error('Bearer Token Preview:', getToken() ? getToken().substring(0, 30) + '...' : 'None');
                console.error('Is API Route:', url.includes('/api/'));
                console.error('===========================================');
                
                try {
                    const errorJson = JSON.parse(errorText);
                    return { 
                        success: false, 
                        message: errorJson.message || 'CSRF token mismatch. Please refresh the page and try again.',
                        error: errorJson,
                        debug_info: {
                            url: url,
                            method: fetchOptions.method,
                            has_bearer_token: !!getToken(),
                            is_api_route: url.includes('/api/')
                        }
                    };
                } catch (e) {
                    return { 
                        success: false, 
                        message: 'CSRF token mismatch. Please refresh the page and try again.',
                        raw_response: errorText.substring(0, 500),
                        debug_info: {
                            url: url,
                            method: fetchOptions.method,
                            has_bearer_token: !!getToken(),
                            is_api_route: url.includes('/api/')
                        }
                    };
                }
            }

            if (!response.ok) {
                const errorText = await response.text();
                console.error('API Error Response (Status ' + response.status + '):', errorText);
                try {
                    const errorJson = JSON.parse(errorText);
                    // Preserve full error response for duplicate errors (409 Conflict) and other structured errors
                    if (response.status === 409 || errorJson.error === 'duplicate' || errorJson.existing_prospect) {
                        // Include full error details for duplicate prospect errors
                        return { success: false, ...errorJson, status_code: response.status };
                    }
                    // For other errors, return structured response
                    return { success: false, message: errorJson.message || errorJson.error || errorText, ...errorJson };
                } catch (e) {
                    return { success: false, message: errorText || `HTTP ${response.status}: ${response.statusText}`, status_code: response.status };
                }
            }

            const data = await response.json();
            console.log('API Success Response:', data);
            return data;
        } catch (error) {
            console.error('API Call Error:', error);
            return { success: false, message: error.message || 'Network error. Please check your connection.' };
        }
    }

    async function loadTasks(status = 'pending', taskType = null) {
        currentStatus = status;
        currentTaskType = taskType || null;
        const contentDiv = document.getElementById('tasksContent');
        if (!contentDiv) {
            console.error('tasksContent element not found');
            return;
        }
        // Apply correct view class (list or grid)
        applyListView();
        contentDiv.innerHTML = '<div class="loading-state"><i class="fas fa-spinner fa-spin"></i><p>Loading tasks...</p></div>';

        // Read date_range from URL (header dropdown). Default 'all' so all pending tasks show; user can filter by Today.
        const urlParams = new URLSearchParams(window.location.search);
        const dateRange = urlParams.get('date_range') || 'all';
        const startDate = urlParams.get('start_date') || '';
        const endDate = urlParams.get('end_date') || '';

        let endpoint = `/tasks?status=${status}&per_page=50&date_range=${encodeURIComponent(dateRange)}`;
        if (startDate) endpoint += `&start_date=${encodeURIComponent(startDate)}`;
        if (endDate) endpoint += `&end_date=${encodeURIComponent(endDate)}`;
        // Add task_type filter if provided
        if (taskType && taskType !== 'all' && taskType !== null) {
            endpoint += `&task_type=${taskType}`;
        }
        console.log('Loading tasks from:', API_BASE_URL + endpoint);
        console.log('Token exists:', !!getToken());

        const TASKS_LOAD_TIMEOUT_MS = 20000;
        let result;
        try {
            result = await Promise.race([
                apiCall(endpoint),
                new Promise((_, reject) => setTimeout(() => reject(new Error('Request timeout. Server took too long to respond.')), TASKS_LOAD_TIMEOUT_MS))
            ]);
            console.log('API Result:', result);

            if (!result) {
                contentDiv.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-exclamation-triangle"></i>
                        <h3>Error Loading Tasks</h3>
                        <p>No response from server. Please check your connection.</p>
                        <button onclick="loadTasks('${status}')" style="margin-top: 16px; padding: 10px 20px; background: #205A44; color: white; border: none; border-radius: 8px; cursor: pointer;">Retry</button>
                    </div>
                `;
                return;
            }

            if (!result.success) {
                const errorMessage = result.message || result.error || 'Failed to load tasks. Please try again.';
                console.error('API returned error:', errorMessage, result);
                contentDiv.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-exclamation-triangle"></i>
                        <h3>Error Loading Tasks</h3>
                        <p>${errorMessage}</p>
                        <p style="font-size: 12px; color: #999; margin-top: 8px;">Check browser console (F12) for details.</p>
                        <button onclick="loadTasks('${status}')" style="margin-top: 16px; padding: 10px 20px; background: #205A44; color: white; border: none; border-radius: 8px; cursor: pointer;">Retry</button>
                    </div>
                `;
                return;
            }

            // Handle both direct data array and paginated response
            let tasks = [];
            if (Array.isArray(result.data)) {
                tasks = result.data;
            } else if (result.data && Array.isArray(result.data.data)) {
                // Paginated response with nested data
                tasks = result.data.data;
            } else if (result.data && typeof result.data === 'object') {
                // Try to extract array from data object
                tasks = result.data.tasks || result.data.items || [];
            }

            currentTasksArray = tasks; // Store tasks for later use in button clicks

            if (tasks.length === 0) {
                contentDiv.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-tasks"></i>
                        <h3>No Tasks Found</h3>
                        <p>You don't have any ${status === 'all' ? '' : status} tasks at the moment.</p>
                    </div>
                `;
                return;
            }

            let cardsHTML = '';
            tasks.forEach(task => {
            const scheduledDate = task.scheduled_at ? formatDate(task.scheduled_at) : '-';
            const statusClass = `status-${task.status}`;
            const initial = task.lead_name ? task.lead_name.charAt(0).toUpperCase() : 'T';
            
            // Check if task is overdue
            const isOverdue = task.scheduled_at && new Date(task.scheduled_at) < new Date() && task.status !== 'completed';
            const overdueClass = isOverdue ? 'overdue' : '';
            
            // Check if task is related to a meeting or visit
            const isMeetingTask = task.task_type === 'pre_meeting_reminder' || task.meeting_id;
            const isVisitTask = task.notes && (
                task.notes.toLowerCase().includes('site visit') || 
                task.notes.toLowerCase().includes('visit scheduled') ||
                task.notes.toLowerCase().includes('10 min before site visit')
            );

            // Render different HTML for list view vs grid view
            if (isListView) {
                const leadId = task.lead_id;
                const leadDetailsUrl = leadId ? (window.BASE_URL || window.location.origin) + '/leads/' + leadId : '#';
                const callBtnHtml = task.status === 'pending' || task.status === 'rescheduled' ? `
                    <button class="btn-call-task" 
                            data-task-id="${task.id}" 
                            data-phone="${task.lead_phone || ''}">
                        <i class="fas fa-phone"></i> Call
                    </button>
                ` : '<span class="task-completed-text">Completed</span>';
                const viewDetailsHtml = leadId ? `<a href="${leadDetailsUrl}" class="btn-view-lead" target="_self"><i class="fas fa-user"></i> View Lead Details</a>` : '';
                cardsHTML += `
                    <div id="task-card-${task.id}" class="task-card ${overdueClass}">
                        <span class="task-name-list">${task.lead_name || 'Unknown'}</span>
                        <span class="task-list-actions">
                            ${callBtnHtml}
                            ${viewDetailsHtml}
                        </span>
                    </div>
                `;
            } else {
                // Full grid view card
                cardsHTML += `
                    <div id="task-card-${task.id}" class="task-card ${overdueClass}">
                        <div class="task-avatar">${initial}</div>
                        <div class="task-content">
                            <div class="task-header">
                                <h3 class="task-name">${task.lead_name || '-'}</h3>
                            </div>
                            <div class="task-info">
                                <div class="task-info-row">
                                    <i class="fas fa-phone"></i>
                                    <span>${task.lead_phone || '-'}</span>
                                </div>
                                <div class="task-info-row">
                                    <i class="fas fa-calendar"></i>
                                    <span>Scheduled: ${scheduledDate}</span>
                                </div>
                                <div style="display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px;">
                                    ${isOverdue ? '<span class="overdue-badge">OVERDUE</span>' : ''}
                                    ${task.status === 'pending' && isMeetingTask ? '<span class="meeting-tag" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; min-width: 24px; height: 24px;">M</span>' : ''}
                                    ${task.status === 'pending' && isVisitTask ? '<span class="visit-tag" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white; padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; min-width: 24px; height: 24px;">V</span>' : ''}
                                    <span class="status-badge ${statusClass}">${formatStatus(task.status)}</span>
                                </div>
                            </div>
                            <div class="task-footer">
                                ${task.status === 'pending' || task.status === 'rescheduled' ? `
                                    <button class="btn-call-task" 
                                            data-task-id="${task.id}" 
                                            data-phone="${task.lead_phone || ''}">
                                        <i class="fas fa-phone"></i>
                                        Call
                                    </button>
                                    ${task.task_type === 'cnp_retry' ? '<p style="text-align: center; color: #f59e0b; font-size: 12px; margin-top: 8px;">CNP Retry Call</p>' : ''}
                                ` : '<p style="text-align: center; color: #999; font-size: 14px;">Task Completed</p>'}
                            </div>
                        </div>
                    </div>
                `;
            }
            });

            contentDiv.innerHTML = cardsHTML;
            
            // Add event listeners for call buttons using event delegation
            contentDiv.querySelectorAll('.btn-call-task').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const taskId = this.getAttribute('data-task-id'); // may be numeric or composite e.g. mt_123
                    const phoneNumber = this.getAttribute('data-phone') || '';
                    
                    // Find task data from stored array
                    const taskData = currentTasksArray.find(t => t.id == taskId || String(t.id) === taskId) || {
                        id: taskId,
                        lead_phone: phoneNumber,
                        lead_name: 'Lead',
                        task_type: 'calling',
                        status: 'pending'
                    };
                    
                    initiateCall(taskId, phoneNumber, taskData);
                });
            });
        } catch (error) {
            console.error('Error in loadTasks:', error);
            const errorMessage = error.message || 'An unexpected error occurred while loading tasks.';
            contentDiv.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h3>Error Loading Tasks</h3>
                    <p>${errorMessage}</p>
                    <p style="font-size: 12px; color: #999; margin-top: 8px;">Check browser console (F12) for details.</p>
                    <button onclick="loadTasks('${status}')" style="margin-top: 16px; padding: 10px 20px; background: #205A44; color: white; border: none; border-radius: 8px; cursor: pointer;">Retry</button>
                </div>
            `;
        }
    }

    function formatDate(dateString) {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    }

    function formatStatus(status) {
        const statusMap = {
            'pending': 'Pending',
            'in_progress': 'In Progress',
            'completed': 'Completed',
            'rescheduled': 'Rescheduled',
        };
        return statusMap[status] || status;
    }

    function filterTasks(status, event = null) {
        currentStatus = status;
        
        const filterDropdown = document.getElementById('taskStatusFilterDropdown');
        if (filterDropdown) filterDropdown.value = status;
        
        // Reload current view (on mobile, always show list view)
        if (window.innerWidth <= 768 || currentView === 'list') {
            loadTasks(status, currentTaskType || null);
        } else if (currentView === 'kanban') {
            loadKanbanView();
        } else if (currentView === 'calendar') {
            loadCalendarView();
        }
    }

    function filterTaskType(taskType, event = null) {
        currentTaskType = taskType;
        
        const typeFilterDropdown = document.getElementById('taskTypeFilterDropdown');
        if (typeFilterDropdown) typeFilterDropdown.value = taskType;
        
        // Reload current view
        if (window.innerWidth <= 768 || currentView === 'list') {
            loadTasks(currentStatus, taskType || null);
        } else if (currentView === 'kanban') {
            loadKanbanView();
        } else if (currentView === 'calendar') {
            loadCalendarView();
        }
    }

    function initiateCall(taskId, phoneNumber, taskData) {
        currentTaskId = taskId;
        currentLeadData = taskData;

        const afterCall = () => {
            if (taskData.task_type === 'pre_meeting_reminder' && taskData.meeting_id) {
                if (typeof showPostCallPopup === 'function') {
                    showPostCallPopup(taskData.meeting_id, taskData);
                } else {
                    console.error('Meeting post-call popup not available');
                    document.getElementById('postCallTitle').textContent = `Call Outcome - ${taskData.lead_name || 'Lead'}`;
                    document.getElementById('postCallModal').classList.add('active');
                }
            } else {
                document.getElementById('postCallTitle').textContent = `Call Outcome - ${taskData.lead_name || 'Lead'}`;
                document.getElementById('postCallModal').classList.add('active');
            }
        };

        if (typeof openLeadCallMenu === 'function') {
            openLeadCallMenu(taskData.lead_id || taskData.lead?.id || null, phoneNumber, taskId, { afterCall });
            return;
        }

        if (phoneNumber && phoneNumber !== '-') {
            window.location.href = `tel:${phoneNumber}`;
        }
        afterCall();
    }

    function closePostCallModal() {
        document.getElementById('postCallModal').classList.remove('active');
    }

    let salesExecutivePendingOutcome = null;
    let salesExecutiveCnpRequiresRetry = true;

    function openSalesExecutiveOutcomeRemarkModal(outcome) {
        salesExecutivePendingOutcome = outcome;
        const modal = document.getElementById('salesExecutiveOutcomeRemarkModal');
        const title = document.getElementById('salesExecutiveOutcomeRemarkTitle');
        const input = document.getElementById('salesExecutiveOutcomeRemarkInput');
        const submit = document.getElementById('salesExecutiveOutcomeRemarkSubmitBtn');

        if (title) {
            title.textContent = outcome === 'not_interested' ? 'Mark Lead as Not Interested' : 'Add Remark';
        }
        if (input) {
            input.value = '';
            input.placeholder = outcome === 'not_interested'
                ? 'Add not interested context...'
                : 'Add context...';
        }
        if (submit) {
            submit.textContent = outcome === 'not_interested' ? 'Continue' : 'Save';
        }

        modal?.classList.add('active');
    }

    function closeSalesExecutiveOutcomeRemarkModal() {
        document.getElementById('salesExecutiveOutcomeRemarkModal')?.classList.remove('active');
        const input = document.getElementById('salesExecutiveOutcomeRemarkInput');
        if (input) {
            input.value = '';
        }
        salesExecutivePendingOutcome = null;
    }

    async function submitSalesExecutiveOutcomeRemark() {
        const notes = document.getElementById('salesExecutiveOutcomeRemarkInput')?.value?.trim() || '';
        const outcome = salesExecutivePendingOutcome;

        if (outcome === 'not_interested') {
            closeSalesExecutiveOutcomeRemarkModal();
            await executeNotInterested(notes);
            return;
        }

        closeSalesExecutiveOutcomeRemarkModal();
    }

    async function handleInterested() {
        closePostCallModal();
        
        if (!currentTaskId) {
            if (typeof showAlert === 'function') {
                showAlert('Error: Task ID not found', 'error', 3000);
            } else {
                alert('Error: Task ID not found');
            }
            return;
        }
        
        try {
            // First, mark the task as interested (complete the call outcome)
            const response = await apiCall(`/tasks/${currentTaskId}/call-outcome`, {
                method: 'POST',
                body: JSON.stringify({ outcome: 'interested' })
            });
            
            if (response && response.success) {
                // Show success message
                if (typeof showAlert === 'function') {
                    showAlert(response.message || 'Lead marked as interested. Opening form...', 'success', 2000);
                }
                
                // Open the lead requirement form modal instead of redirecting
                if (typeof openLeadRequirementFormModal === 'function') {
                    // Use task_id from response or currentTaskId
                    const taskId = response.task_id || currentTaskId;
                    openLeadRequirementFormModal(taskId);
                } else {
                    // Fallback: if modal function not available, show error
                    if (typeof showAlert === 'function') {
                        showAlert('Form modal not available. Please refresh the page.', 'error', 3000);
                    } else {
                        alert('Form modal not available. Please refresh the page.');
                    }
                }
            } else {
                const errorMsg = response?.error || response?.message || 'Failed to mark as interested';
                if (typeof showAlert === 'function') {
                    showAlert(errorMsg, 'error', 3000);
                } else {
                    alert('Error: ' + errorMsg);
                }
            }
        } catch (error) {
            console.error('Error marking as interested:', error);
            const errorMsg = 'Error: Failed to mark as interested. Please try again.';
            if (typeof showAlert === 'function') {
                showAlert(errorMsg, 'error', 3000);
            } else {
                alert(errorMsg);
            }
        }
    }

    function closeProspectModal() {
        document.getElementById('prospectModal').classList.remove('active');
        document.getElementById('prospectForm').reset();
        if (typeof initializeStarRating === 'function') {
            initializeStarRating();
        }
    }

    async function handleNotInterested() {
        closePostCallModal();
        openSalesExecutiveOutcomeRemarkModal('not_interested');
    }
    
    async function executeNotInterested(notes = '') {
        closeConfirmModal();
        
        const result = await apiCall(`/tasks/${currentTaskId}/outcome`, {
            method: 'POST',
            body: { outcome: 'not_interested', notes },
        });

        if (result && result.success) {
            // Remove task card immediately from DOM
            const taskCard = document.getElementById(`task-card-${currentTaskId}`);
            if (taskCard) {
                taskCard.style.transition = 'opacity 0.3s, transform 0.3s';
                taskCard.style.opacity = '0';
                taskCard.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    taskCard.remove();
                    // Reload tasks after card removal to ensure consistency
                    if (currentStatus === 'pending' || currentStatus === 'rescheduled') {
                        loadTasks(currentStatus, currentTaskType);
                    }
                }, 300);
            } else {
                // Fallback if card not found by ID
                if (currentStatus === 'pending' || currentStatus === 'rescheduled') {
                    loadTasks(currentStatus, currentTaskType);
                }
            }
            
            showAlert('Lead marked as Not Interested', 'success', 2500);
            closePostCallModal();
        } else {
            showAlert(result?.message || 'Failed to update', 'error', 3000);
        }
    }

    // CNP Time Selection Variables for Sales Executive
    let selectedSalesExecutiveCnpMinutes = null;
    let selectedSalesExecutiveCnpDateTime = null;
    let isSalesExecutiveCustomTimeSelected = false;
    let currentCnpCount = 0;
    
    async function handleCNP() {
        closePostCallModal();
        currentCnpCount = currentLeadData?.cnp_count || 0;
        openSalesExecutiveCnpTimeSelectionModal(currentCnpCount < 2);
    }
    
    // Open Sales Executive CNP Time Selection Modal
    function openSalesExecutiveCnpTimeSelectionModal(requiresRetry = true) {
        const modal = document.getElementById('SalesExecutiveCnpTimeSelectionModal');
        if (modal) {
            salesExecutiveCnpRequiresRetry = requiresRetry;
            modal.classList.add('active');
            const title = document.getElementById('salesExecutiveCnpModalTitle');
            const text = document.getElementById('salesExecutiveCnpModalText');
            const quickOptions = document.getElementById('salesExecutiveQuickTimeOptions');
            const customOptionWrap = document.getElementById('salesExecutiveCustomOptionWrap');
            const confirmBtn = document.getElementById('salesExecutiveCnpConfirmBtn');

            if (title) {
                title.textContent = requiresRetry ? 'Select Retry Time for CNP' : 'Confirm CNP';
            }
            if (text) {
                text.textContent = requiresRetry
                    ? 'Choose when to retry this call:'
                    : 'This will complete the task after the CNP limit. You can add an optional remark below.';
            }
            if (quickOptions) {
                quickOptions.style.display = requiresRetry ? 'grid' : 'none';
            }
            if (customOptionWrap) {
                customOptionWrap.style.display = requiresRetry ? 'block' : 'none';
            }
            if (confirmBtn) {
                confirmBtn.textContent = requiresRetry ? 'Confirm' : 'Mark CNP';
            }
            // Reset selections
            selectedSalesExecutiveCnpMinutes = null;
            selectedSalesExecutiveCnpDateTime = null;
            isSalesExecutiveCustomTimeSelected = false;
            document.getElementById('salesExecutiveCustomTimePickerContainer').style.display = 'none';
            document.getElementById('salesExecutiveSelectedTimeDisplay').style.display = 'none';
            const remarkInput = document.getElementById('salesExecutiveOutcomeRemark');
            if (remarkInput) {
                remarkInput.value = '';
            }
            // Clear button selections
            document.querySelectorAll('.salesExecutive-time-option-btn').forEach(btn => {
                btn.classList.remove('selected');
            });
            // Set minimum date to today
            const today = new Date().toISOString().split('T')[0];
            const dateInput = document.getElementById('SalesExecutiveCnpCustomDate');
            if (dateInput) {
                dateInput.min = today;
                dateInput.value = '';
            }
            const timeInput = document.getElementById('SalesExecutiveCnpCustomTime');
            if (timeInput) {
                timeInput.value = '';
            }
        }
    }
    
    // Select quick time option for Sales Executive (15 min, 30 min, 1 hr, 2 hr)
    function selectSalesExecutiveCnpTime(minutes) {
        selectedSalesExecutiveCnpMinutes = minutes;
        selectedSalesExecutiveCnpDateTime = null;
        isSalesExecutiveCustomTimeSelected = false;
        
        // Hide custom picker
        document.getElementById('salesExecutiveCustomTimePickerContainer').style.display = 'none';
        
        // Clear custom inputs
        document.getElementById('SalesExecutiveCnpCustomDate').value = '';
        document.getElementById('SalesExecutiveCnpCustomTime').value = '';
        
        // Remove selected class from all buttons
        document.querySelectorAll('.salesExecutive-time-option-btn').forEach(btn => {
            btn.classList.remove('selected');
        });
        
        // Add selected class to clicked button
        event.target.classList.add('selected');
        
        // Calculate and display selected time
        const now = new Date();
        const retryTime = new Date(now.getTime() + minutes * 60000);
        const formattedTime = retryTime.toLocaleString('en-IN', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
        });
        
        document.getElementById('salesExecutiveSelectedTimeText').textContent = `Retry call in ${minutes} minutes (${formattedTime})`;
        document.getElementById('salesExecutiveSelectedTimeDisplay').style.display = 'block';
    }
    
    // Show custom date-time picker for Sales Executive
    function showSalesExecutiveCustomTimePicker() {
        isSalesExecutiveCustomTimeSelected = true;
        selectedSalesExecutiveCnpMinutes = null;
        
        // Remove selected class from quick option buttons
        document.querySelectorAll('.salesExecutive-time-option-btn[data-minutes]').forEach(btn => {
            btn.classList.remove('selected');
        });
        
        // Show custom picker container
        const customContainer = document.getElementById('salesExecutiveCustomTimePickerContainer');
        customContainer.style.display = 'block';
        
        // Hide selected time display initially
        document.getElementById('salesExecutiveSelectedTimeDisplay').style.display = 'none';
        
        // Set minimum date to today and default time to next hour
        const today = new Date().toISOString().split('T')[0];
        const dateInput = document.getElementById('SalesExecutiveCnpCustomDate');
        const timeInput = document.getElementById('SalesExecutiveCnpCustomTime');
        
        if (dateInput) {
            dateInput.min = today;
            if (!dateInput.value) {
                dateInput.value = today;
            }
        }
        
        if (timeInput && !timeInput.value) {
            const nextHour = new Date();
            nextHour.setHours(nextHour.getHours() + 1);
            nextHour.setMinutes(0);
            const timeStr = nextHour.toTimeString().slice(0, 5); // HH:MM format
            timeInput.value = timeStr;
        }
        
        // Add change listeners to update selected time display
        if (dateInput && timeInput) {
            const updateSalesExecutiveCustomTimeDisplay = () => {
                const date = dateInput.value;
                const time = timeInput.value;
                if (date && time) {
                    const dateTime = new Date(`${date}T${time}`);
                    if (dateTime > new Date()) {
                        const formattedTime = dateTime.toLocaleString('en-IN', {
                            year: 'numeric',
                            month: 'short',
                            day: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit',
                            hour12: true
                        });
                        document.getElementById('salesExecutiveSelectedTimeText').textContent = `Retry call on ${formattedTime}`;
                        document.getElementById('salesExecutiveSelectedTimeDisplay').style.display = 'block';
                        selectedSalesExecutiveCnpDateTime = dateTime.toISOString();
                    } else {
                        document.getElementById('salesExecutiveSelectedTimeDisplay').style.display = 'none';
                    }
                } else {
                    document.getElementById('salesExecutiveSelectedTimeDisplay').style.display = 'none';
                }
            };
            
            // Remove existing listeners and add new ones
            dateInput.removeEventListener('change', updateSalesExecutiveCustomTimeDisplay);
            timeInput.removeEventListener('change', updateSalesExecutiveCustomTimeDisplay);
            dateInput.addEventListener('change', updateSalesExecutiveCustomTimeDisplay);
            timeInput.addEventListener('change', updateSalesExecutiveCustomTimeDisplay);
            
            // Initial update
            updateSalesExecutiveCustomTimeDisplay();
        }
    }
    
    // Confirm Sales Executive CNP time selection and submit
    async function confirmSalesExecutiveCnpTimeSelection() {
        if (!currentTaskId) {
            showAlert('Task ID not found', 'error');
            return;
        }

        const notes = document.getElementById('salesExecutiveOutcomeRemark')?.value?.trim() || '';

        if (!salesExecutiveCnpRequiresRetry) {
            executeSalesExecutiveCNP(null, null, currentCnpCount, notes);
            return;
        }
        
        let retryAt = null;
        let retryMinutes = null;
        
        // Validate selection
        if (isSalesExecutiveCustomTimeSelected) {
            const dateInput = document.getElementById('SalesExecutiveCnpCustomDate');
            const timeInput = document.getElementById('SalesExecutiveCnpCustomTime');
            const date = dateInput.value;
            const time = timeInput.value;
            
            if (!date || !time) {
                showAlert('Please select both date and time for custom option', 'warning');
                return;
            }
            
            const selectedDateTime = new Date(`${date}T${time}`);
            const now = new Date();
            
            if (selectedDateTime <= now) {
                showAlert('Please select a future date and time', 'warning');
                return;
            }
            
            retryAt = selectedDateTime.toISOString();
        } else if (selectedSalesExecutiveCnpMinutes !== null) {
            retryMinutes = selectedSalesExecutiveCnpMinutes;
        } else {
            showAlert('Please select a retry time option', 'warning');
            return;
        }
        
        executeSalesExecutiveCNP(retryAt, retryMinutes, currentCnpCount, notes);
    }
    
    // Execute Sales Executive CNP with selected time
    async function executeSalesExecutiveCNP(retryAt, retryMinutes, cnpCount, notes = '') {
        closeSalesExecutiveCnpTimeSelectionModal();
        closeConfirmModal();
        
        if (!currentTaskId) {
            showAlert('Task ID not found', 'error');
            return;
        }
        
        try {
            const requestBody = { outcome: 'cnp' };
            if (retryAt) {
                requestBody.retry_at = retryAt;
            } else if (retryMinutes !== null) {
                requestBody.retry_minutes = retryMinutes;
            }
            if (notes) {
                requestBody.notes = notes;
            }
            
            const result = await apiCall(`/tasks/${currentTaskId}/outcome`, {
                method: 'POST',
                body: JSON.stringify(requestBody),
            });

            if (result && result.success) {
                // Check if task was completed (CNP count >= 2) or rescheduled
                const taskStatus = result.task?.status || 'pending';
                
                // Remove task card immediately from DOM
                const taskCard = document.getElementById(`task-card-${currentTaskId}`);
                if (taskCard) {
                    taskCard.style.transition = 'opacity 0.3s, transform 0.3s';
                    taskCard.style.opacity = '0';
                    taskCard.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        taskCard.remove();
                        // Reload tasks after card removal to ensure consistency
                        if (taskStatus === 'completed' && (currentStatus === 'pending' || currentStatus === 'rescheduled')) {
                            loadTasks(currentStatus, currentTaskType);
                        } else if (taskStatus === 'rescheduled' && currentStatus === 'pending') {
                            loadTasks(currentStatus, currentTaskType);
                        } else if (taskStatus === 'pending' && currentStatus === 'rescheduled') {
                            loadTasks(currentStatus, currentTaskType);
                        }
                    }, 300);
                } else {
                    // Fallback if card not found by ID
                    if (taskStatus === 'completed' && (currentStatus === 'pending' || currentStatus === 'rescheduled')) {
                        loadTasks(currentStatus, currentTaskType);
                    } else if (taskStatus === 'rescheduled' && currentStatus === 'pending') {
                        loadTasks(currentStatus, currentTaskType);
                    } else if (taskStatus === 'pending' && currentStatus === 'rescheduled') {
                        loadTasks(currentStatus, currentTaskType);
                    }
                }
                
                if (cnpCount >= 1) {
                    showAlert('CNP recorded. Task completed automatically after 2 CNP attempts.', 'success', 3000);
                } else {
                    const timeMsg = retryAt 
                        ? 'New calling task created for selected time.'
                        : (retryMinutes ? `New calling task created for ${retryMinutes} minutes later.` : 'New calling task created for tomorrow.');
                    showAlert('CNP recorded. ' + timeMsg, 'success', 3000);
                }
                closePostCallModal();
            } else {
                showAlert(result?.message || 'Failed to update', 'error', 3000);
            }
        } catch (error) {
            console.error('Error executing CNP:', error);
            showAlert('Error: ' + error.message, 'error', 3000);
        }
    }
    
    // Cancel Sales Executive CNP time selection
    function cancelSalesExecutiveCnpTimeSelection() {
        closeSalesExecutiveCnpTimeSelectionModal();
    }
    
    // Close Sales Executive CNP time selection modal
    function closeSalesExecutiveCnpTimeSelectionModal() {
        const modal = document.getElementById('SalesExecutiveCnpTimeSelectionModal');
        if (modal) {
            modal.classList.remove('active');
            // Reset selections
            selectedSalesExecutiveCnpMinutes = null;
            selectedSalesExecutiveCnpDateTime = null;
            isSalesExecutiveCustomTimeSelected = false;
            document.getElementById('salesExecutiveCustomTimePickerContainer').style.display = 'none';
            document.getElementById('salesExecutiveSelectedTimeDisplay').style.display = 'none';
            document.getElementById('SalesExecutiveCnpCustomDate').value = '';
            document.getElementById('SalesExecutiveCnpCustomTime').value = '';
            const remarkInput = document.getElementById('salesExecutiveOutcomeRemark');
            if (remarkInput) {
                remarkInput.value = '';
            }
            document.querySelectorAll('.salesExecutive-time-option-btn').forEach(btn => {
                btn.classList.remove('selected');
            });
        }
    }

    function handleCallAgain() {
        closePostCallModal();
        document.getElementById('rescheduleModal').classList.add('active');
    }

    function closeRescheduleModal() {
        document.getElementById('rescheduleModal').classList.remove('active');
        document.getElementById('rescheduleDateTime').value = '';
        document.getElementById('rescheduleNotes').value = '';
    }

    async function saveReschedule() {
        const datetime = document.getElementById('rescheduleDateTime').value;
        if (!datetime) {
            showAlert('Please select date and time', 'warning', 2500);
            return;
        }

        const result = await apiCall(`/tasks/${currentTaskId}/outcome`, {
            method: 'POST',
            body: {
                outcome: 'call_again',
                scheduled_at: datetime,
                notes: document.getElementById('rescheduleNotes').value,
            },
        });

        if (result && result.success) {
            // Check if task was rescheduled
            const taskStatus = result.task?.status || 'rescheduled';
            
            // Remove task card immediately from DOM
            const taskCard = document.getElementById(`task-card-${currentTaskId}`);
            if (taskCard) {
                taskCard.style.transition = 'opacity 0.3s, transform 0.3s';
                taskCard.style.opacity = '0';
                taskCard.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    taskCard.remove();
                    // Reload tasks after card removal to ensure consistency
                    // If task is rescheduled and we're on pending view, reload to remove it
                    if (taskStatus === 'rescheduled' && currentStatus === 'pending') {
                        loadTasks(currentStatus, currentTaskType);
                    }
                }, 300);
            } else {
                // Fallback if card not found by ID
                if (taskStatus === 'rescheduled' && currentStatus === 'pending') {
                    loadTasks(currentStatus, currentTaskType);
                }
            }
            
            showAlert('Call rescheduled successfully', 'success', 2500);
            closeRescheduleModal();
        } else {
            showAlert(result?.message || 'Failed to reschedule', 'error', 3000);
        }
    }

    async function handleBlock() {
        showConfirmModalWithInput(
            'Block this lead? This action cannot be undone.',
            'Reason for blocking (optional):',
            function(notes) {
                executeBlock(notes);
            }
        );
    }
    
    async function executeBlock(notes) {
        closeConfirmModal();
        
        const result = await apiCall(`/tasks/${currentTaskId}/outcome`, {
            method: 'POST',
            body: {
                outcome: 'block',
                notes: notes || 'Blocked by Sales Executive',
            },
        });

        if (result && result.success) {
            // Remove task card immediately from DOM
            const taskCard = document.getElementById(`task-card-${currentTaskId}`);
            if (taskCard) {
                taskCard.style.transition = 'opacity 0.3s, transform 0.3s';
                taskCard.style.opacity = '0';
                taskCard.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    taskCard.remove();
                    // Reload tasks after card removal to ensure consistency
                    if (currentStatus === 'pending' || currentStatus === 'rescheduled') {
                        loadTasks(currentStatus, currentTaskType);
                    }
                }, 300);
            } else {
                // Fallback if card not found by ID
                if (currentStatus === 'pending' || currentStatus === 'rescheduled') {
                    loadTasks(currentStatus, currentTaskType);
                }
            }
            
            showAlert('Lead blocked successfully', 'success', 2500);
            closePostCallModal();
        } else {
            showAlert(result?.message || 'Failed to block', 'error', 3000);
        }
    }
    
    // Custom Confirmation Modal Functions
    let confirmCallback = null;
    let confirmInputValue = null;
    
    function showConfirmModal(message, callback) {
        document.getElementById('confirmModalTitle').textContent = 'Confirm Action';
        document.getElementById('confirmModalMessage').textContent = message;
        document.getElementById('confirmModalMessage').innerHTML = message.replace(/\n/g, '<br>');
        confirmCallback = callback;
        confirmInputValue = null;
        
        // Hide input field if exists
        const inputField = document.getElementById('confirmModalInput');
        if (inputField) {
            inputField.style.display = 'none';
            inputField.parentElement.style.display = 'none';
        }
        
        document.getElementById('confirmModal').classList.add('active');
    }
    
    function showConfirmModalWithInput(message, inputLabel, callback) {
        document.getElementById('confirmModalTitle').textContent = 'Confirm Action';
        document.getElementById('confirmModalMessage').textContent = message;
        confirmCallback = callback;
        
        // Show input field
        let inputContainer = document.getElementById('confirmModalInputContainer');
        if (!inputContainer) {
            const messageEl = document.getElementById('confirmModalMessage');
            inputContainer = document.createElement('div');
            inputContainer.id = 'confirmModalInputContainer';
            inputContainer.style.marginTop = '20px';
            inputContainer.innerHTML = `
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #063A1C;">${inputLabel}</label>
                <input type="text" id="confirmModalInput" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px;">
            `;
            messageEl.parentElement.insertBefore(inputContainer, messageEl.nextSibling);
        }
        inputContainer.style.display = 'block';
        document.getElementById('confirmModalInput').style.display = 'block';
        document.getElementById('confirmModalInput').value = '';
        
        document.getElementById('confirmModal').classList.add('active');
        
        // Focus on input
        setTimeout(() => {
            document.getElementById('confirmModalInput').focus();
        }, 100);
    }
    
    function closeConfirmModal() {
        document.getElementById('confirmModal').classList.remove('active');
        confirmCallback = null;
        confirmInputValue = null;
        
        const inputField = document.getElementById('confirmModalInput');
        if (inputField) {
            inputField.value = '';
        }
    }
    
    function executeConfirmAction() {
        const inputField = document.getElementById('confirmModalInput');
        if (inputField && inputField.style.display !== 'none') {
            confirmInputValue = inputField.value;
        }
        
        if (confirmCallback) {
            confirmCallback(confirmInputValue);
        }
        
        closeConfirmModal();
    }
    
    // Close modal on backdrop click
    document.getElementById('confirmModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeConfirmModal();
        }
    });
    
    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && document.getElementById('confirmModal').classList.contains('active')) {
            closeConfirmModal();
        }
    });

    function openWhatsAppFromForm() {
        const phone = document.getElementById('prospectPhone').value;
        if (phone && phone !== '-') {
            const cleanPhone = phone.replace(/[^\d+]/g, '');
            window.open(`https://wa.me/${cleanPhone}`, '_blank');
        } else {
            showAlert('Phone number not available', 'warning', 2500);
        }
    }

    // Star Rating Functionality
    let selectedRating = 0;
    const stars = document.querySelectorAll('.star');
    const ratingText = document.getElementById('ratingText');
    const leadScoreInput = document.getElementById('leadScore');

    // Initialize star rating when modal opens
    function initializeStarRating() {
        selectedRating = 0;
        stars.forEach((star, index) => {
            star.textContent = '☆';
            star.style.color = '#d1d5db';
        });
        if (ratingText) ratingText.textContent = '';
        if (leadScoreInput) leadScoreInput.value = '';
    }

    // Handle star click
    if (stars.length > 0) {
        stars.forEach((star, index) => {
            star.addEventListener('click', function() {
                const rating = parseInt(this.getAttribute('data-rating'));
                selectedRating = rating;
                
                // Update stars display
                stars.forEach((s, i) => {
                    if (i < rating) {
                        s.textContent = '★';
                        s.style.color = '#fbbf24';
                    } else {
                        s.textContent = '☆';
                        s.style.color = '#d1d5db';
                    }
                });
                
                // Update hidden input
                if (leadScoreInput) {
                    leadScoreInput.value = rating;
                }
                
                // Update rating text
                if (ratingText) {
                    const labels = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];
                    ratingText.textContent = labels[rating] || '';
                }
            });
            
            // Hover effect
            star.addEventListener('mouseenter', function() {
                const rating = parseInt(this.getAttribute('data-rating'));
                stars.forEach((s, i) => {
                    if (i < rating) {
                        s.style.color = '#fbbf24';
                    } else {
                        s.style.color = '#d1d5db';
                    }
                });
            });
        });
        
        // Reset on mouse leave if no rating selected
        document.getElementById('starRating').addEventListener('mouseleave', function() {
            if (selectedRating === 0) {
                stars.forEach(star => {
                    star.textContent = '☆';
                    star.style.color = '#d1d5db';
                });
            } else {
                stars.forEach((star, index) => {
                    if (index < selectedRating) {
                        star.textContent = '★';
                        star.style.color = '#fbbf24';
                    } else {
                        star.textContent = '☆';
                        star.style.color = '#d1d5db';
                    }
                });
            }
        });
    }

    document.getElementById('prospectForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        if (!currentLeadData) {
            showAlert('Lead data not found', 'error', 2500);
            return;
        }

        if (!currentTaskId) {
            showAlert('Task ID not found', 'error', 2500);
            return;
        }

        const formData = {
            lead_id: currentLeadData.lead_id,
            task_id: currentTaskId,
            customer_name: document.getElementById('customerName').value || currentLeadData.lead_name,
            phone: document.getElementById('prospectPhone').value || currentLeadData.lead_phone,
            budget: document.getElementById('budget').value || '',
            preferred_location: document.getElementById('preferredLocation').value || '',
            size: document.getElementById('size').value || '',
            purpose: document.getElementById('purpose').value,
            possession: document.getElementById('possession').value || '',
            remark: document.getElementById('remark').value || '',
            lead_score: document.getElementById('leadScore').value || '',
        };

        // Validate required fields
        if (!formData.customer_name || !formData.phone || !formData.purpose || !formData.remark) {
            showAlert('Please fill all required fields (Name, Phone, Purpose, Remark)', 'warning', 3000);
            return;
        }
        
        // Validate lead score
        if (!formData.lead_score || formData.lead_score < 1 || formData.lead_score > 5) {
            showAlert('Please select a lead score rating (1-5 stars)', 'warning', 3000);
            return;
        }

        try {
            console.log('=== PROSPECT CREATION REQUEST DEBUG ===');
            console.log('Form Data:', formData);
            console.log('Current Task ID:', currentTaskId);
            console.log('Current Lead Data:', currentLeadData);
            console.log('Token Exists:', !!getToken());
            
            const result = await apiCall('/prospects/create', {
                method: 'POST',
                body: formData,
            });

            console.log('=== PROSPECT CREATION RESPONSE ===');
            console.log('Result:', result);

            if (result && result.success) {
                showAlert('Prospect created successfully!', 'success', 3000);
                closeProspectModal();
                loadTasks(currentStatus, currentTaskType);
            } else {
                const errorMsg = result?.message || result?.errors || 'Failed to create prospect';
                console.error('Prospect creation failed:', result);
                
                // Show detailed error message
                let alertMsg = typeof errorMsg === 'string' ? errorMsg : JSON.stringify(errorMsg);
                showAlert(alertMsg, 'error', 4000);
            }
        } catch (error) {
            console.error('=== PROSPECT CREATION EXCEPTION ===');
            console.error('Error:', error);
            console.error('Error Message:', error.message);
            console.error('Error Stack:', error.stack);
            const errorMsg = 'Error: ' + (error.message || 'Failed to create prospect');
            showAlert(errorMsg, 'error', 4000);
        }
    });

    // View switching functionality
    let currentView = localStorage.getItem('salesExecutive_task_view') || 'list';
    
    function switchView(view) {
        // Force list view on mobile
        if (window.innerWidth <= 768) {
            view = 'list';
        }
        
        currentView = view;
        localStorage.setItem('salesExecutive_task_view', view);
        
        const viewDropdown = document.getElementById('taskViewDropdown');
        if (viewDropdown) viewDropdown.value = view;
        
        // Show/hide view containers
        const listView = document.getElementById('list-view-container');
        const kanbanView = document.getElementById('kanban-view-container');
        const calendarView = document.getElementById('calendar-view-container');
        
        if (listView) listView.style.display = view === 'list' ? 'block' : 'none';
        if (kanbanView) kanbanView.style.display = view === 'kanban' ? 'block' : 'none';
        if (calendarView) calendarView.style.display = view === 'calendar' ? 'block' : 'none';
        
        // Load data for the selected view
        if (view === 'list') {
            // Already loaded in loadTasks
        } else if (view === 'kanban') {
            setTimeout(() => loadKanbanView(), 100);
        } else if (view === 'calendar') {
            setTimeout(() => loadCalendarView(), 100);
        }
    }
    
    // Toggle between list and grid view (mobile only)
    function toggleListView() {
        isListView = !isListView;
        localStorage.setItem('salesExecutive_task_list_view', isListView);
        applyListView();
    }
    
    // Apply list or grid view class
    function applyListView() {
        const contentDiv = document.getElementById('tasksContent');
        if (!contentDiv) return;
        
        const toggleIcon = document.getElementById('toggleIcon');
        
        if (isListView) {
            contentDiv.classList.remove('tasks-grid');
            contentDiv.classList.add('tasks-list');
            if (toggleIcon) toggleIcon.className = 'fas fa-th';
        } else {
            contentDiv.classList.remove('tasks-list');
            contentDiv.classList.add('tasks-grid');
            if (toggleIcon) toggleIcon.className = 'fas fa-list';
        }
    }
    
    function loadKanbanView() {
        const kanbanBoard = document.getElementById('kanbanBoard');
        if (!kanbanBoard) return;
        
        kanbanBoard.innerHTML = '<div style="text-align: center; padding: 40px; color: #B3B5B4;"><i class="fas fa-spinner fa-spin" style="font-size: 32px; margin-bottom: 12px;"></i><p>Loading Kanban board...</p></div>';
        
        // Load tasks and organize by status
        let endpoint = `/tasks?status=${currentStatus}&per_page=100`;
        if (currentTaskType && currentTaskType !== null) {
            endpoint += `&task_type=${currentTaskType}`;
        }
        apiCall(endpoint).then(result => {
            if (result && result.success) {
                const tasks = result.data || [];
                renderKanbanBoard(tasks);
            } else {
                kanbanBoard.innerHTML = '<div style="text-align: center; padding: 40px; color: #B3B5B4;"><p>Failed to load tasks</p></div>';
            }
        });
    }
    
    function renderKanbanBoard(tasks) {
        const kanbanBoard = document.getElementById('kanbanBoard');
        const statuses = [
            { key: 'pending', label: 'Pending', color: '#fef3c7' },
            { key: 'in_progress', label: 'In Progress', color: '#dbeafe' },
            { key: 'completed', label: 'Completed', color: '#d1fae5' }
        ];
        
        let html = '';
        statuses.forEach(status => {
            const statusTasks = tasks.filter(t => t.status === status.key);
            html += `
                <div class="kanban-column" style="background: ${status.color};">
                    <div class="kanban-column-header">
                        ${status.label} (${statusTasks.length})
                    </div>
                    <div id="kanban-${status.key}">
                        ${statusTasks.map(task => createKanbanCard(task)).join('')}
                    </div>
                </div>
            `;
        });
        
        kanbanBoard.innerHTML = html;
        
        // Add click handlers to cards
        kanbanBoard.querySelectorAll('.kanban-task-card .btn-call-task').forEach(btn => {
            btn.addEventListener('click', function() {
                const taskId = this.getAttribute('data-task-id'); // may be numeric or composite e.g. mt_123
                const phoneNumber = this.getAttribute('data-phone') || '';
                const taskData = tasks.find(t => t.id == taskId || String(t.id) === taskId);
                if (taskData) {
                    initiateCall(taskId, phoneNumber, taskData);
                }
            });
        });
    }
    
    function createKanbanCard(task) {
        const scheduledDate = task.scheduled_at ? formatDate(task.scheduled_at) : '-';
        const initial = task.lead_name ? task.lead_name.charAt(0).toUpperCase() : 'T';
        const isOverdue = task.scheduled_at && new Date(task.scheduled_at) < new Date() && task.status !== 'completed';
        const overdueClass = isOverdue ? 'overdue' : '';
        const isMeetingTask = task.task_type === 'pre_meeting_reminder' || task.meeting_id;
        const isVisitTask = task.notes && (
            task.notes.toLowerCase().includes('site visit') || 
            task.notes.toLowerCase().includes('visit scheduled') ||
            task.notes.toLowerCase().includes('10 min before site visit')
        );

        return `
            <div class="kanban-task-card ${overdueClass}">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                    <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #205A44 0%, #063A1C 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 16px;">
                        ${initial}
                    </div>
                    <div style="flex: 1;">
                        <h4 style="margin: 0; font-size: 14px; font-weight: 600; color: #063A1C;">${task.lead_name || '-'}</h4>
                        <p style="margin: 4px 0 0 0; font-size: 12px; color: #666;">${task.lead_phone || '-'}</p>
                    </div>
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px;">
                    ${isOverdue ? '<span style="background: #fee2e2; color: #dc2626; padding: 3px 8px; border-radius: 8px; font-size: 10px; font-weight: 600;">OVERDUE</span>' : ''}
                    ${task.status === 'pending' && isMeetingTask ? '<span class="meeting-tag" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; min-width: 24px; height: 24px;">M</span>' : ''}
                    ${task.status === 'pending' && isVisitTask ? '<span class="visit-tag" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white; padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; min-width: 24px; height: 24px;">V</span>' : ''}
                </div>
                <div style="font-size: 12px; color: #666; margin-bottom: 12px;">
                    <i class="fas fa-calendar" style="margin-right: 5px;"></i>${scheduledDate}
                </div>
                ${task.status === 'pending' || task.status === 'rescheduled' ? `
                    <button class="btn-call-task" data-task-id="${task.id}" data-phone="${task.lead_phone || ''}" style="width: 100%; padding: 10px; background: linear-gradient(135deg, #205A44 0%, #063A1C 100%); color: white; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer;">
                        <i class="fas fa-phone"></i> Call
                    </button>
                ` : ''}
            </div>
        `;
    }
    
    function loadCalendarView() {
        const calendarContainer = document.getElementById('calendarContainer');
        if (!calendarContainer) return;
        
        calendarContainer.innerHTML = '<div style="text-align: center; padding: 40px; color: #B3B5B4;"><i class="fas fa-spinner fa-spin" style="font-size: 32px; margin-bottom: 12px;"></i><p>Loading calendar...</p></div>';
        
        // Load tasks and render FullCalendar
        let endpoint = `/tasks?status=${currentStatus}&per_page=100`;
        if (currentTaskType && currentTaskType !== null) {
            endpoint += `&task_type=${currentTaskType}`;
        }
        apiCall(endpoint).then(result => {
            if (result && result.success) {
                const tasks = result.data || [];
                renderFullCalendar(tasks);
            } else {
                calendarContainer.innerHTML = '<div style="text-align: center; padding: 40px; color: #B3B5B4;"><p>Failed to load tasks</p></div>';
            }
        });
    }
    
    function renderFullCalendar(tasks) {
        const calendarContainer = document.getElementById('calendarContainer');
        calendarContainer.innerHTML = ''; // Clear loading message
        
        // Destroy existing calendar if any
        if (calendarInstance) {
            calendarInstance.destroy();
            calendarInstance = null;
        }
        
        // Prepare events for FullCalendar
        const events = tasks
            .filter(task => task.scheduled_at)
            .map(task => {
                const scheduledDate = new Date(task.scheduled_at);
                const isOverdue = scheduledDate < new Date() && task.status !== 'completed';
                
                // Determine color based on status and overdue
                let backgroundColor = '#205A44'; // Default green
                if (isOverdue) {
                    backgroundColor = '#ef4444'; // Red for overdue
                } else if (task.status === 'completed') {
                    backgroundColor = '#10b981'; // Green for completed
                } else if (task.status === 'in_progress') {
                    backgroundColor = '#3b82f6'; // Blue for in progress
                }
                
                return {
                    id: task.id,
                    title: `${task.lead_name || 'Lead'} (${task.lead_phone || '-'})`,
                    start: task.scheduled_at,
                    backgroundColor: backgroundColor,
                    borderColor: backgroundColor,
                    textColor: '#ffffff',
                    extendedProps: {
                        taskId: task.id,
                        leadName: task.lead_name,
                        leadPhone: task.lead_phone,
                        status: task.status,
                        isOverdue: isOverdue,
                        taskData: task
                    }
                };
            });
        
        // Initialize FullCalendar
        calendarInstance = new FullCalendar.Calendar(calendarContainer, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
            },
            events: events,
            eventClick: function(info) {
                // Show task details or initiate call
                const taskData = info.event.extendedProps.taskData;
                if (taskData && (taskData.status === 'pending' || taskData.status === 'rescheduled')) {
                    initiateCall(taskData.id, taskData.lead_phone, taskData);
                } else {
                    showAlert(`Task: ${info.event.extendedProps.leadName}\nPhone: ${info.event.extendedProps.leadPhone}\nStatus: ${info.event.extendedProps.status}`, 'info', 3000);
                }
            },
            eventDisplay: 'block',
            height: 'auto',
            dayMaxEvents: 3,
            moreLinkClick: 'popover',
            eventTimeFormat: {
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            },
            locale: 'en',
            firstDay: 1, // Monday
            buttonText: {
                today: 'Today',
                month: 'Month',
                week: 'Week',
                day: 'Day',
                list: 'List'
            },
            dayHeaderFormat: { weekday: 'short' },
            eventContent: function(arg) {
                // Custom event rendering
                const taskData = arg.event.extendedProps;
                const isOverdue = taskData.isOverdue;
                const title = arg.event.title.length > 30 ? arg.event.title.substring(0, 30) + '...' : arg.event.title;
                
                return {
                    html: `
                        <div style="padding: 6px 8px; font-size: 12px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; line-height: 1.3;">
                            ${isOverdue ? '<span style="display: inline-block; width: 6px; height: 6px; background: #fff; border-radius: 50%; margin-right: 4px; vertical-align: middle;"></span>' : ''}
                            <span style="vertical-align: middle;">${title}</span>
                        </div>
                    `
                };
            },
            datesSet: function(dateInfo) {
                // Optionally reload tasks when view changes
                // This allows filtering by visible date range
            },
            validRange: function(nowDate) {
                // Allow viewing past and future dates
                return null;
            }
        });
        
        calendarInstance.render();
        
        // Force calendar to recalculate size after render
        setTimeout(function() {
            if (calendarInstance) {
                calendarInstance.updateSize();
            }
        }, 200);
        
        // Force calendar to recalculate size
        setTimeout(function() {
            if (calendarInstance) {
                calendarInstance.updateSize();
            }
        }, 100);
    }

    // Initialize on page load
    function initializeTasks() {
        console.log('Tasks page loaded, initializing...');
        console.log('API_BASE_URL:', API_BASE_URL);
        console.log('Token:', getToken() ? 'Exists' : 'Missing');
        
        // Set initial view (but force list view on mobile)
        if (window.innerWidth <= 768) {
            currentView = 'list';
            switchView('list');
            // Initialize list/grid view toggle
            applyListView();
        } else {
            switchView(currentView);
        }
        
        // Set up mobile dropdown filters
        const statusFilterDropdown = document.getElementById('taskStatusFilterDropdown');
        if (statusFilterDropdown) {
            statusFilterDropdown.addEventListener('change', function(e) {
                const status = this.value;
                filterTasks(status, e);
            });
            statusFilterDropdown.value = currentStatus;
        }

        const typeFilterDropdown = document.getElementById('taskTypeFilterDropdown');
        if (typeFilterDropdown) {
            typeFilterDropdown.addEventListener('change', function(e) {
                const taskType = this.value;
                filterTaskType(taskType, e);
            });
            typeFilterDropdown.value = (currentTaskType && currentTaskType !== 'all') ? currentTaskType : 'all';
        }

        const viewDropdown = document.getElementById('taskViewDropdown');
        if (viewDropdown) {
            viewDropdown.value = currentView;
            viewDropdown.addEventListener('change', function() {
                switchView(this.value);
            });
        }
        
        // Initialize toggle button state
        applyListView();
        
        const contentDiv = document.getElementById('tasksContent');
        if (contentDiv) {
            loadTasks(currentStatus || 'pending', currentTaskType || null);
        } else {
            console.error('tasksContent element not found, retrying...');
            setTimeout(initializeTasks, 200);
        }
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeTasks);
    } else {
        // DOM already loaded
        initializeTasks();
    }
</script>
@endpush

@include('sales-executive.modals.lead-requirement-form')
@push('scripts')
<script>
// Lead Requirement Form Modal Scripts
// These need to be after apiCall function is defined
(function() {
    // Ensure functions are in global scope
    window.currentLeadTaskId = null;
    window.currentLeadId = null;

    window.openLeadRequirementFormModal = async function(taskId) {
        window.currentLeadTaskId = taskId;
        
        // Show modal
        const modal = document.getElementById('leadRequirementFormModal');
        if (!modal) {
            console.error('Modal element not found');
            if (typeof showAlert === 'function') {
                showAlert('Modal not found. Please refresh the page.', 'error', 3000);
            }
            return;
        }
        
        modal.classList.add('active');
        
        // Reset form container
        const container = document.getElementById('leadFormContainer');
        if (!container) {
            console.error('Form container not found');
            return;
        }
        
        container.innerHTML = '<div style="text-align: center; padding: 40px;"><div class="spinner" style="display: inline-block;"></div><p style="margin-top: 15px; color: #666;">Loading form...</p></div>';
        
        try {
            // Check if apiCall function is available
            if (typeof apiCall !== 'function') {
                throw new Error('API call function not available. Please refresh the page.');
            }
            
            // Fetch lead form data
            const endpoint = `/tasks/${taskId}/lead-form`;
            console.log('Fetching lead form from endpoint:', endpoint);
            console.log('API_BASE_URL:', typeof API_BASE_URL !== 'undefined' ? API_BASE_URL : 'not defined');
            
            const response = await apiCall(endpoint, {
                method: 'GET'
            });
            
            console.log('Lead form API response:', response);
            
            if (response && response.success) {
                window.currentLeadId = response.lead_id;
                if (typeof renderLeadForm === 'function') {
                    renderLeadForm(response);
                } else {
                    console.error('renderLeadForm function not found');
                    container.innerHTML = '<div style="padding: 20px; text-align: center; color: #d32f2f;"><p>Error: Form render function not available. Please refresh the page.</p></div>';
                }
            } else {
                const errorMsg = response?.error || response?.message || 'Unknown error';
                console.error('Error in response:', errorMsg, response);
                container.innerHTML = '<div style="padding: 20px; text-align: center; color: #d32f2f;"><p>Error loading form: ' + errorMsg + '</p><p style="font-size: 12px; color: #666; margin-top: 10px;">Please check the browser console for more details.</p></div>';
            }
        } catch (error) {
            console.error('Error loading lead form:', error);
            const errorMessage = error.message || 'Please try again';
            container.innerHTML = '<div style="padding: 20px; text-align: center; color: #d32f2f;"><p>Error loading form: ' + errorMessage + '</p><p style="font-size: 12px; color: #666; margin-top: 10px;">Please check the browser console (F12) for more details.</p></div>';
        }
    };

    window.closeLeadRequirementFormModal = function() {
        const modal = document.getElementById('leadRequirementFormModal');
        if (modal) {
            modal.classList.remove('active');
            window.currentLeadTaskId = null;
            window.currentLeadId = null;
            const container = document.getElementById('leadFormContainer');
            if (container) {
                container.innerHTML = '';
            }
        }
    };

    // Render lead form dynamically
    window.renderLeadForm = async function(data) {
        const container = document.getElementById('leadFormContainer');
        if (!container) {
            console.error('Form container not found');
            return;
        }
        
        // Debug logging
        console.log('Form data received:', data);
        console.log('Form fields:', data.form_fields);
        console.log('Form fields type:', typeof data.form_fields);
        console.log('Form fields is array:', Array.isArray(data.form_fields));
        console.log('Form fields length:', data.form_fields?.length);
        
        // Properly check if form_fields exists and is non-empty array
        let formFields = [];
        if (data.form_fields && Array.isArray(data.form_fields) && data.form_fields.length > 0) {
            formFields = data.form_fields;
            console.log('Using API form fields:', formFields.length);
        } else {
            // Fallback to hardcoded fields
            console.warn('API form_fields empty or missing, using fallback fields');
            formFields = [
                { key: 'category', label: 'Category', type: 'select', required: true, options: ['Residential', 'Commercial', 'Both', 'N.A'] },
                { key: 'preferred_location', label: 'Preferred Location', type: 'select', required: true, options: ['Inside City', 'Sitapur Road', 'Hardoi Road', 'Faizabad Road', 'Sultanpur Road', 'Shaheed Path', 'Raebareily Road', 'Kanpur Road', 'Outer Ring Road', 'Bijnor Road', 'Deva Road', 'Sushant Golf City', 'Vrindavan Yojana', 'N.A'] },
                { key: 'type', label: 'Type', type: 'select', required: true, options: ['Plots & Villas', 'Apartments', 'Retail Shops', 'Office Space', 'Studio', 'Farmhouse', 'Agricultural', 'Others', 'N.A'], dependent_field: 'category', dependent_conditions: null },
                { key: 'purpose', label: 'Purpose', type: 'select', required: true, options: ['End Use', 'Short Term Investment', 'Long Term Investment', 'Rental Income', 'Investment + End Use', 'N.A'] },
                { key: 'possession', label: 'Possession', type: 'select', required: true, options: ['Under Construction', 'Ready To Move', 'Pre Launch', 'Both', 'N.A'] },
                { key: 'budget', label: 'Budget', type: 'select', required: true, options: ['Below 50 Lacs', '50-75 Lacs', '75 Lacs-1 Cr', 'Above 1 Cr', 'Above 2 Cr', 'N.A'] },
            ];
        }
        
        let formHTML = `
            <form id="leadRequirementForm" onsubmit="submitLeadRequirementForm(event); return false;">
                <input type="hidden" name="task_id" value="${window.currentLeadTaskId}">
                
                <div style="margin-bottom: 24px;">
                    <h3 style="font-size: 16px; font-weight: 600; color: #333; margin-bottom: 16px; padding-bottom: 8px; border-bottom: 1px solid #e0e0e0;">Basic Information</h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div>
                            <label style="display: block; font-size: 14px; font-weight: 500; color: #333; margin-bottom: 6px;">
                                Name <span style="color: #d32f2f;">*</span>
                            </label>
                            <input type="text" 
                                   name="name" 
                                   id="form_lead_name"
                                   value="${data.lead_name || ''}"
                                   required
                                   placeholder="Enter lead name"
                                   style="width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        </div>
                        
                        <div>
                            <label style="display: block; font-size: 14px; font-weight: 500; color: #333; margin-bottom: 6px;">
                                Mobile Number <span style="color: #d32f2f;">*</span>
                            </label>
                            <input type="tel" 
                                   name="phone" 
                                   id="form_lead_phone"
                                   value="${data.lead_phone || ''}"
                                   required
                                   placeholder="Enter phone number"
                                   style="width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                        </div>
                    </div>
                </div>
                
                <div style="margin-bottom: 24px;">
                    <h3 style="font-size: 16px; font-weight: 600; color: #333; margin-bottom: 16px; padding-bottom: 8px; border-bottom: 1px solid #e0e0e0;">Sales Executive Fields</h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
        `;
        
        // Render form fields
        formFields.forEach(field => {
            const fieldKey = field.key || field.field_key;
            const fieldLabel = field.label || field.field_label;
            const fieldType = field.type || field.field_type;
            const isRequired = field.required !== undefined ? field.required : field.is_required;
            const fieldOptions = field.options || [];
            const dependentField = field.dependent_field;
            const dependentConditions = field.dependent_conditions;
            
            const existingValue = data.form_values && data.form_values[fieldKey] ? data.form_values[fieldKey] : '';
            
            formHTML += `
                <div>
                    <label style="display: block; font-size: 14px; font-weight: 500; color: #333; margin-bottom: 6px;">
                        ${fieldLabel} ${isRequired ? '<span style="color: #d32f2f;">*</span>' : ''}
                    </label>
            `;
            
            if (fieldType === 'select') {
                const dependentAttr = dependentField ? `data-dependent-field="${dependentField}"` : '';
                formHTML += `<select name="${fieldKey}" id="form_${fieldKey}" ${isRequired ? 'required' : ''} 
                             style="width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;"
                             ${dependentAttr}
                             onchange="handleFormFieldChange('${fieldKey}', this.value${dependentField ? `, '${dependentField}', ${JSON.stringify(dependentConditions || {})}` : ''})">`;
                formHTML += `<option value="">-- Select ${fieldLabel} --</option>`;
                fieldOptions.forEach(option => {
                    formHTML += `<option value="${option}" ${existingValue === option ? 'selected' : ''}>${option}</option>`;
                });
                formHTML += `</select>`;
            } else {
                formHTML += `<input type="${fieldType}" 
                             name="${fieldKey}" 
                             id="form_${fieldKey}"
                             value="${existingValue}"
                             ${isRequired ? 'required' : ''}
                             placeholder="${field.placeholder || `Enter ${fieldLabel}`}"
                             style="width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">`;
            }
            
            formHTML += `</div>`;
        });
        
        formHTML += `
                    </div>
                </div>
                
                <div class="form-buttons-container" style="display: flex; flex-direction: row; justify-content: flex-end; align-items: stretch; gap: 12px; padding-top: 20px; border-top: 1px solid #e0e0e0; margin-top: 24px; width: 100%;">
                    <button type="button" 
                            onclick="closeLeadRequirementFormModal()" 
                            class="form-cancel-btn"
                            style="padding: 12px 24px; border: 1px solid #ddd; border-radius: 8px; background: white; color: #333; cursor: pointer; font-size: 14px; font-weight: 600; min-height: 44px; display: flex; align-items: center; justify-content: center; transition: all 0.2s; flex-shrink: 0;">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="form-submit-btn"
                            style="padding: 12px 24px; background: linear-gradient(135deg, #205A44 0%, #063A1C 100%); color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600; min-height: 44px; display: flex; align-items: center; justify-content: center; transition: all 0.2s; box-shadow: 0 2px 4px rgba(32, 90, 68, 0.2); flex-shrink: 0;">
                        <i class="fas fa-check-circle" style="margin-right: 8px;"></i>
                        Save Requirements
                    </button>
                </div>
            </form>
        `;
        
        container.innerHTML = formHTML;
        
        // Initialize dependent fields (category -> type)
        const categorySelect = document.getElementById('form_category');
        const typeSelect = document.getElementById('form_type');
        
        if (categorySelect && typeSelect) {
            categorySelect.addEventListener('change', function() {
                updateTypeOptions(this.value, typeSelect);
            });
            
            // Initialize on load
            if (categorySelect.value) {
                updateTypeOptions(categorySelect.value, typeSelect);
            }
        }
    };

    // Update type options based on category
    window.updateTypeOptions = function(category, typeSelect) {
        const typeOptions = {
            'Residential': ['Plots & Villas', 'Apartments', 'Studio', 'Farmhouse', 'N.A'],
            'Commercial': ['Retail Shops', 'Office Space', 'Studio', 'N.A'],
            'Both': ['Plots & Villas', 'Apartments', 'Retail Shops', 'Office Space', 'Studio', 'Farmhouse', 'Agricultural', 'Others', 'N.A'],
            'N.A': ['N.A']
        };
        
        const currentValue = typeSelect.value;
        const options = (typeOptions[category] || typeOptions['Both']).filter((option, index, arr) => {
            const normalized = String(option || '').trim().toLowerCase();
            return normalized && arr.findIndex(item => String(item || '').trim().toLowerCase() === normalized) === index;
        });
        
        typeSelect.innerHTML = '<option value="">-- Select Type --</option>';
        options.forEach(option => {
            const selected = option === currentValue ? 'selected' : '';
            typeSelect.innerHTML += `<option value="${option}" ${selected}>${option}</option>`;
        });
    };

    // Handle form field changes
    window.handleFormFieldChange = function(fieldKey, value, dependentField = null, dependentConditions = null) {
        // Handle dependent field updates (e.g., category -> type)
        if (dependentField && dependentConditions) {
            const dependentSelect = document.getElementById(`form_${dependentField}`);
            if (dependentSelect && dependentConditions[value]) {
                const currentValue = dependentSelect.value;
                const newOptions = dependentConditions[value];
                
                dependentSelect.innerHTML = '<option value="">-- Select Type --</option>';
                newOptions.forEach(option => {
                    const selected = option === currentValue ? 'selected' : '';
                    dependentSelect.innerHTML += `<option value="${option}" ${selected}>${option}</option>`;
                });
            }
        }
        
        // Special handling for category -> type dependency
        if (fieldKey === 'category') {
            const typeSelect = document.getElementById('form_type');
            if (typeSelect) {
                updateTypeOptions(value, typeSelect);
            }
        }
    };

    // Submit lead requirement form
    window.submitLeadRequirementForm = async function(event) {
        event.preventDefault();
        
        const taskId = window.currentLeadTaskId;
        if (!taskId) {
            const errorMsg = 'Error: Task ID not found';
            console.error(errorMsg);
            if (typeof showAlert === 'function') {
                showAlert(errorMsg, 'error', 3000);
            } else {
                alert(errorMsg);
            }
            return;
        }
        
        const form = event.target;
        const formData = new FormData(form);
        
        // Convert FormData to object
        const data = {};
        formData.forEach((value, key) => {
            data[key] = value;
        });
        
        try {
            // Check if apiCall function is available
            if (typeof apiCall !== 'function') {
                throw new Error('API call function not available. Please refresh the page.');
            }
            
            // Note: apiCall already uses API_BASE_URL which is /api/telecaller
            const endpoint = `/tasks/${taskId}/submit-for-verification`;
            console.log('Submitting form to endpoint:', endpoint, data);
            
            const response = await apiCall(endpoint, {
                method: 'POST',
                body: JSON.stringify(data)
            });
            
            console.log('Submit form API response:', response);
            
            if (response && response.success) {
                const successMsg = response.message || 'Lead requirements saved successfully';
                if (typeof showAlert === 'function') {
                    showAlert(successMsg, 'success', 3000);
                } else {
                    alert(successMsg);
                }
                
                closeLeadRequirementFormModal();
                
                // Remove task card immediately from DOM
                const taskCard = document.getElementById(`task-card-${taskId}`);
                if (taskCard) {
                    taskCard.style.transition = 'opacity 0.3s, transform 0.3s';
                    taskCard.style.opacity = '0';
                    taskCard.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        taskCard.remove();
                        // Reload tasks after card removal to ensure consistency
                        if (currentStatus === 'pending' || currentStatus === 'rescheduled') {
                            loadTasks(currentStatus, currentTaskType);
                        }
                    }, 300);
                } else {
                    // Fallback if card not found by ID
                    if (currentStatus === 'pending' || currentStatus === 'rescheduled') {
                        setTimeout(() => {
                            loadTasks(currentStatus, currentTaskType);
                        }, 300);
                    }
                }
                
                // Refresh stats
                if (typeof loadStats === 'function') {
                    loadStats();
                }
            } else {
                // Check for duplicate prospect error
                if (response?.error === 'duplicate' || response?.existing_prospect) {
                    const existingProspect = response.existing_prospect || {};
                    const statusMsg = existingProspect.verification_status === 'pending_verification' 
                        ? 'This prospect is already pending verification' 
                        : existingProspect.verification_status === 'verified'
                        ? 'This prospect has already been verified'
                        : existingProspect.verification_status === 'rejected'
                        ? 'This prospect was previously rejected'
                        : 'A prospect already exists for this lead';
                    
                    const duplicateMsg = `${response?.message || statusMsg}\n\n` +
                        `Existing Prospect Details:\n` +
                        `Name: ${existingProspect.customer_name || 'N/A'}\n` +
                        `Phone: ${existingProspect.phone || 'N/A'}\n` +
                        `Status: ${existingProspect.verification_status || 'N/A'}\n` +
                        (existingProspect.created_at ? `Created: ${existingProspect.created_at}` : '');
                    
                    // Format message with HTML line breaks for better display
                    const formattedMsg = duplicateMsg.replace(/\n/g, '<br>');
                    
                    if (typeof showAlert === 'function') {
                        // Show longer alert for duplicate error with HTML formatting
                        // Use a modal-like alert for better visibility
                        const alertContainer = document.createElement('div');
                        alertContainer.className = 'alert alert-error';
                        alertContainer.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 10000; padding: 20px; background: #fee; border: 2px solid #f44; border-radius: 8px; max-width: 400px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);';
                        alertContainer.innerHTML = `
                            <div style="font-weight: 600; color: #c00; margin-bottom: 10px; font-size: 16px;">Duplicate Prospect Error</div>
                            <div style="color: #333; font-size: 14px; line-height: 1.6;">${formattedMsg}</div>
                            <button onclick="this.parentElement.remove()" style="margin-top: 15px; padding: 8px 16px; background: #f44; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 500;">Close</button>
                        `;
                        document.body.appendChild(alertContainer);
                        
                        // Auto-remove after 8 seconds
                        setTimeout(() => {
                            if (alertContainer.parentElement) {
                                alertContainer.remove();
                            }
                        }, 8000);
                    } else {
                        alert('Duplicate Error:\n\n' + duplicateMsg);
                    }
                    
                    // Close the modal on duplicate error
                    closeLeadRequirementFormModal();
                } else {
                    // Regular error handling
                    const errorMsg = response?.error || response?.message || 'Failed to submit form';
                    if (typeof showAlert === 'function') {
                        showAlert(errorMsg, 'error', 3000);
                    } else {
                        alert('Error: ' + errorMsg);
                    }
                }
            }
        } catch (error) {
            console.error('Error submitting lead form:', error);
            const errorMsg = 'Error: Failed to submit form. ' + (error.message || 'Please try again.');
            if (typeof showAlert === 'function') {
                showAlert(errorMsg, 'error', 3000);
            } else {
                alert(errorMsg);
            }
        }
    };

    // Close modal on outside click
    document.addEventListener('click', function(event) {
        const modal = document.getElementById('leadRequirementFormModal');
        if (event.target === modal) {
            closeLeadRequirementFormModal();
        }
    });
})();
</script>
@endpush

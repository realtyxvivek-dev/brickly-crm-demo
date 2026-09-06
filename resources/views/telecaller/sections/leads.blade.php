@extends('telecaller.layout')

@section('title', 'Leads - Telecaller')
@section('page-title', 'Leads')

@push('styles')
<style>
    .leads-container {
        background: white;
        padding: 24px;
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        width: 100%;
        box-sizing: border-box;
    }
    
    /* Mobile container padding */
    @media (max-width: 768px) {
        .leads-container {
            padding: 12px;
        }
    }
    
    @media (max-width: 480px) {
        .leads-container {
            padding: 8px;
        }
    }
    .search-filter-bar {
        display: flex;
        gap: 12px;
        margin-bottom: 20px;
        flex-wrap: nowrap;
        width: 100%;
    }
    .search-input {
        flex: 0 0 50%;
        width: 50%;
        padding: 12px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 16px;
        background: #ffffff;
        box-sizing: border-box;
    }
    .search-input:focus {
        outline: none;
        border-color: #205A44;
    }
    .status-filter {
        flex: 0 0 50%;
        width: 50%;
        padding: 12px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 16px;
        background: #ffffff;
        box-sizing: border-box;
    }
    .leads-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-top: 20px;
        width: 100%;
        box-sizing: border-box;
    }
    
    /* Tablet view - 2 columns */
    @media (max-width: 1024px) {
        .leads-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    
    /* Mobile/Phone view - 2 columns (50%-50%) */
    @media (max-width: 768px) {
        .leads-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
            width: 100%;
            padding: 0;
            margin-left: 0;
            margin-right: 0;
        }
        .lead-card {
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            padding: 12px;
            margin: 0;
            min-width: 0;
        }
        .lead-card-header {
            margin-bottom: 10px;
            padding-bottom: 10px;
        }
        .lead-avatar {
            width: 40px;
            height: 40px;
            font-size: 16px;
            margin-right: 8px;
        }
        .lead-name {
            font-size: 14px;
        }
        .lead-info-row {
            font-size: 12px;
            margin-bottom: 6px;
        }
        .lead-card-footer {
            margin-top: 12px;
            padding-top: 12px;
            gap: 6px;
        }
        .lead-card-btn {
            padding: 8px 6px;
            font-size: 11px;
            gap: 4px;
        }
        .lead-card-btn i {
            font-size: 12px;
        }
    }
    
    /* Small mobile view - 2 columns (50%-50%) */
    @media (max-width: 480px) {
        .leads-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 6px;
            width: 100%;
            padding: 0;
        }
        .lead-card {
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            padding: 10px;
            margin: 0;
            min-width: 0;
        }
        .lead-card-header {
            margin-bottom: 8px;
            padding-bottom: 8px;
        }
        .lead-avatar {
            width: 35px;
            height: 35px;
            font-size: 14px;
            margin-right: 6px;
        }
        .lead-name {
            font-size: 13px;
        }
        .lead-info-row {
            font-size: 11px;
            margin-bottom: 5px;
        }
        .lead-card-footer {
            margin-top: 10px;
            padding-top: 10px;
            gap: 4px;
        }
        .lead-card-btn {
            padding: 7px 4px;
            font-size: 10px;
            gap: 3px;
        }
        .lead-card-btn i {
            font-size: 11px;
        }
    }
    .lead-card {
        background: white;
        border: 2px solid #e0e0e0;
        border-radius: 12px;
        padding: 20px;
        transition: all 0.3s;
        cursor: pointer;
    }
    .lead-card:hover {
        border-color: #205A44;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
        transform: translateY(-2px);
    }
    .lead-card-header {
        display: flex;
        align-items: center;
        margin-bottom: 16px;
        padding-bottom: 16px;
        border-bottom: 2px solid #f0f0f0;
    }
    .lead-avatar {
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
    .lead-name {
        font-size: 18px;
        font-weight: 600;
        color: #063A1C;
        margin: 0;
    }
    .lead-info {
        margin-bottom: 12px;
    }
    .lead-info-label {
        font-size: 12px;
        color: #B3B5B4;
        text-transform: uppercase;
        margin-bottom: 4px;
    }
    .lead-info-value {
        font-size: 14px;
        color: #063A1C;
        font-weight: 500;
    }
    .lead-info-row {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 8px;
    }
    .lead-info-row i {
        color: #205A44;
        width: 16px;
    }
    .status-badge {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 500;
        margin-top: 12px;
    }
    .status-new { background: #dbeafe; color: #1e40af; }
    .status-contacted { background: #fef3c7; color: #92400e; }
    .status-qualified { background: #e9d5ff; color: #6b21a8; }
    .status-site_visit_scheduled { background: #ddd6fe; color: #5b21b6; }
    .status-site_visit_completed { background: #fce7f3; color: #9f1239; }
    .status-negotiation { background: #fed7aa; color: #9a3412; }
    .status-closed_won { background: #d1fae5; color: #065f46; }
    .status-closed_lost { background: #fee2e2; color: #991b1b; }
    .status-on_hold { background: #f3f4f6; color: #374151; }
    .lead-card-footer {
        margin-top: 16px;
        padding-top: 16px;
        border-top: 2px solid #f0f0f0;
        display: flex;
        gap: 8px;
        width: 100%;
    }
    .lead-card-btn {
        flex: 0 0 33.33%;
        width: 33.33%;
        padding: 10px 12px;
        border: none;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        box-sizing: border-box;
    }
    .lead-card-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    }
    .btn-call {
        background: linear-gradient(135deg, #205A44 0%, #063A1C 100%);
        color: white;
    }
    .btn-call:hover {
        background: linear-gradient(135deg, #5568d3 0%, #653a8f 100%);
    }
    .btn-whatsapp {
        background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
        color: white;
        box-shadow: 0 2px 4px rgba(21, 128, 61, 0.3);
    }
    .btn-whatsapp:hover {
        background: linear-gradient(135deg, #15803d 0%, #166534 100%);
        box-shadow: 0 4px 8px rgba(21, 128, 61, 0.4);
        transform: translateY(-1px);
    }
    .btn-view-detail {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: white;
        text-decoration: none;
        box-shadow: 0 2px 4px rgba(37, 99, 235, 0.3);
    }
    .btn-view-detail:hover {
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        box-shadow: 0 4px 8px rgba(37, 99, 235, 0.4);
        transform: translateY(-1px);
        color: white;
        text-decoration: none;
    }
    
    /* New Card Structure Styles */
    .lead-card-header-new {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 12px;
        padding-bottom: 12px;
        border-bottom: 1px solid #f0f0f0;
    }
    .lead-name-new {
        font-size: 16px;
        font-weight: 600;
        color: #063A1C;
        margin: 0;
        flex: 1;
    }
    .status-badge-new {
        padding: 3px 8px;
        border-radius: 10px;
        font-size: 9px;
        font-weight: 600;
        display: inline-block;
    }
    .status-badge-new.status-pending {
        background: #fef3c7;
        color: #92400e;
    }
    .status-badge-new.status-rejected {
        background: #fee2e2;
        color: #991b1b;
    }
    .status-badge-new.status-verified_prospect {
        background: #d1fae5;
        color: #065f46;
    }
    .lead-info-new {
        margin-bottom: 16px;
    }
    .lead-info-row-new {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 8px;
        font-size: 13px;
        color: #063A1C;
    }
    .lead-info-row-new i {
        color: #205A44;
        width: 16px;
        font-size: 14px;
    }
    .lead-info-label {
        font-weight: 500;
        color: #666;
    }
    .lead-info-value-new {
        color: #063A1C;
    }
    .lead-card-footer-new {
        display: flex;
        flex-direction: column;
        gap: 8px;
        margin-top: 16px;
        padding-top: 16px;
        border-top: 1px solid #f0f0f0;
    }
    .lead-card-btn-new {
        width: 100%;
        padding: 10px 16px;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        text-decoration: none;
        box-sizing: border-box;
    }
    .btn-view-detail-new {
        background: linear-gradient(135deg, #205A44 0%, #063A1C 100%);
        color: white;
    }
    .btn-view-detail-new:hover {
        background: linear-gradient(135deg, #063A1C 0%, #205A44 100%);
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(32, 90, 68, 0.3);
        color: white;
        text-decoration: none;
    }
    .btn-short-detail-new {
        background: linear-gradient(135deg, #205A44 0%, #063A1C 100%);
        color: white;
    }
    .btn-short-detail-new:hover {
        background: linear-gradient(135deg, #063A1C 0%, #205A44 100%);
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(32, 90, 68, 0.3);
        color: white;
        text-decoration: none;
    }
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #B3B5B4;
        grid-column: 1 / -1;
    }
    .empty-state i {
        font-size: 64px;
        color: #d1d5db;
        margin-bottom: 16px;
    }
    .empty-state h3 {
        font-size: 20px;
        font-weight: 600;
        color: #374151;
        margin-bottom: 8px;
    }
    .loading-state {
        text-align: center;
        padding: 40px;
        color: #B3B5B4;
        grid-column: 1 / -1;
    }
    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 8px;
        margin-top: 20px;
        grid-column: 1 / -1;
    }
    .pagination button {
        padding: 8px 16px;
        border: 1px solid #e0e0e0;
        border-radius: 6px;
        background: white;
        color: #B3B5B4;
        cursor: pointer;
        font-size: 14px;
    }
    .pagination button:hover:not(:disabled) {
        background: #f0f0f0;
        border-color: #205A44;
    }
    .pagination button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    .pagination .page-info {
        padding: 8px 16px;
        color: #B3B5B4;
        font-size: 14px;
    }
    .leads-container {
        border: 1px solid rgba(32, 90, 68, 0.10);
        box-shadow: 0 14px 34px rgba(6, 58, 28, 0.08);
    }
    .search-filter-bar {
        align-items: stretch;
    }
    .telecaller-lead-request-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        padding: 14px 16px;
        margin-bottom: 14px;
        border: 1px solid #d9e4dd;
        border-radius: 16px;
        background: linear-gradient(135deg, #f7fbf8 0%, #ffffff 100%);
    }
    .telecaller-lead-request-title {
        color: #063A1C;
        font-size: 15px;
        font-weight: 800;
        line-height: 1.25;
    }
    .telecaller-lead-request-copy {
        color: #6f7d75;
        font-size: 12px;
        line-height: 1.35;
        margin-top: 3px;
    }
    .telecaller-lead-request-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-height: 42px;
        padding: 10px 14px;
        border-radius: 12px;
        background: linear-gradient(135deg, #063A1C 0%, #0b6b48 100%);
        color: #ffffff;
        font-size: 13px;
        font-weight: 800;
        text-decoration: none;
        white-space: nowrap;
        box-shadow: 0 10px 22px rgba(6, 58, 28, 0.16);
    }
    .telecaller-lead-request-btn:hover {
        color: #ffffff;
        transform: translateY(-1px);
        box-shadow: 0 14px 28px rgba(6, 58, 28, 0.20);
    }
    .telecaller-flash {
        padding: 12px 14px;
        margin-bottom: 12px;
        border-radius: 14px;
        font-size: 13px;
        font-weight: 700;
    }
    .telecaller-flash-success {
        color: #065f46;
        background: #dcfce7;
        border: 1px solid #86efac;
    }
    .telecaller-flash-error {
        color: #991b1b;
        background: #fee2e2;
        border: 1px solid #fecaca;
    }
    .lead-request-modal {
        position: fixed;
        inset: 0;
        z-index: 70;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 18px;
        background: rgba(15, 23, 42, 0.50);
    }
    .lead-request-modal.is-open {
        display: flex;
    }
    .lead-request-dialog {
        width: min(420px, 100%);
        border-radius: 20px;
        background: #ffffff;
        box-shadow: 0 24px 70px rgba(6, 58, 28, 0.28);
        overflow: hidden;
    }
    .lead-request-dialog-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 18px 18px 12px;
        border-bottom: 1px solid #e5eee8;
    }
    .lead-request-dialog-title {
        margin: 0;
        color: #063A1C;
        font-size: 18px;
        font-weight: 900;
    }
    .lead-request-close {
        width: 38px;
        height: 38px;
        border: 1px solid #d9e4dd;
        border-radius: 12px;
        background: #f8faf9;
        color: #063A1C;
        cursor: pointer;
    }
    .lead-request-body {
        padding: 18px;
    }
    .lead-request-label {
        display: block;
        margin-bottom: 10px;
        color: #31453b;
        font-size: 13px;
        font-weight: 800;
    }
    .lead-request-quick {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 8px;
        margin-bottom: 12px;
    }
    .lead-request-quick button {
        min-height: 42px;
        border: 1px solid #d9e4dd;
        border-radius: 12px;
        background: #f8faf9;
        color: #063A1C;
        font-weight: 900;
        cursor: pointer;
    }
    .lead-request-quick button.is-selected {
        background: #063A1C;
        color: #ffffff;
        border-color: #063A1C;
    }
    .lead-request-input {
        width: 100%;
        height: 48px;
        padding: 10px 12px;
        border: 1.5px solid #d9e4dd;
        border-radius: 14px;
        color: #10231b;
        font-size: 16px;
        font-weight: 800;
        box-sizing: border-box;
    }
    .lead-request-submit {
        width: 100%;
        min-height: 48px;
        margin-top: 14px;
        border: none;
        border-radius: 14px;
        background: linear-gradient(135deg, #063A1C 0%, #0b6b48 100%);
        color: #ffffff;
        font-size: 15px;
        font-weight: 900;
        cursor: pointer;
    }
    .search-input,
    .status-filter {
        min-width: 0;
        height: 46px;
        border: 1.5px solid #d9e4dd;
        border-radius: 12px;
        color: #10231b;
        font-weight: 600;
        box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
    }
    .search-input::placeholder {
        color: #92a09a;
    }
    .empty-state {
        min-height: 360px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        border: 1px dashed #d7e4dc;
        border-radius: 18px;
        background: linear-gradient(180deg, #fbfdfb 0%, #f7faf8 100%);
    }
    .empty-state i {
        font-size: 52px;
        color: #cbd5d1;
    }
    .empty-state h3 {
        color: #1f352b;
        font-size: 22px;
        font-weight: 800;
    }
    .empty-state p {
        max-width: 320px;
        line-height: 1.45;
        color: #8a9691;
    }
    @media (max-width: 768px) {
        .leads-container {
            padding: 0 !important;
            border: 0 !important;
            border-radius: 0 !important;
            background: transparent !important;
            box-shadow: none !important;
        }
        .search-filter-bar {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: 10px;
            margin-bottom: 12px;
            padding: 10px;
            background: #ffffff;
            border: 1px solid #dfe8e2;
            border-radius: 18px;
            box-shadow: 0 12px 26px rgba(6, 58, 28, 0.08);
        }
        .telecaller-lead-request-bar {
            padding: 12px;
            margin-bottom: 10px;
            border-radius: 18px;
            box-shadow: 0 12px 26px rgba(6, 58, 28, 0.08);
        }
        .telecaller-lead-request-copy {
            display: none;
        }
        .telecaller-lead-request-btn {
            min-width: 132px;
            min-height: 44px;
            padding: 10px 12px;
            font-size: 13px;
        }
        .search-input,
        .status-filter {
            flex: none !important;
            width: 100% !important;
            height: 44px;
            padding: 10px 12px;
            font-size: 14px;
        }
        .search-input {
            text-overflow: ellipsis;
        }
        .leads-grid {
            display: block;
            margin-top: 0;
        }
        .lead-card {
            border-radius: 16px;
            border-width: 1px;
            box-shadow: 0 10px 24px rgba(6, 58, 28, 0.07);
            margin-bottom: 10px;
        }
        .empty-state {
            min-height: 430px;
            padding: 42px 18px;
            border: 1px solid #dfe8e2 !important;
            border-radius: 20px !important;
            background: #ffffff !important;
            box-shadow: 0 14px 30px rgba(6, 58, 28, 0.08);
        }
        .empty-state i {
            font-size: 58px;
        }
        .empty-state h3 {
            font-size: 21px;
            margin-bottom: 10px;
        }
    }
</style>
@endpush

@section('content')
    <div class="leads-container">
        @if(session('success'))
            <div class="telecaller-flash telecaller-flash-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="telecaller-flash telecaller-flash-error">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="telecaller-flash telecaller-flash-error">{{ $errors->first() }}</div>
        @endif

        <div class="telecaller-lead-request-bar">
            <div>
                <div class="telecaller-lead-request-title">Need more leads?</div>
                <div class="telecaller-lead-request-copy">Sirf quantity select karo. Approval ke baad queue me allocate hogi.</div>
            </div>
            <button type="button" class="telecaller-lead-request-btn" onclick="openLeadRequestModal()">
                <i class="fas fa-plus"></i>
                Request Leads
            </button>
        </div>

        <!-- Search and Filter Bar -->
        <div class="search-filter-bar">
            <input type="text" id="searchInput" class="search-input" placeholder="Search lead" onkeyup="handleSearch()">
            <select id="statusFilter" class="status-filter" onchange="loadLeads()">
                <option value="">All Status</option>
                <option value="new">New</option>
                <option value="contacted">Contacted</option>
                <option value="qualified">Qualified</option>
                <option value="site_visit_scheduled">Site Visit Scheduled</option>
                <option value="site_visit_completed">Site Visit Completed</option>
                <option value="negotiation">Negotiation</option>
                <option value="closed_won">Closed Won</option>
                <option value="closed_lost">Closed Lost</option>
                <option value="on_hold">On Hold</option>
            </select>
        </div>

        <!-- Leads Grid -->
        <div id="leadsContent" class="leads-grid">
            <div class="loading-state">
                <i class="fas fa-spinner fa-spin"></i>
                <p>Loading leads...</p>
            </div>
        </div>
    </div>

<div id="leadRequestModal" class="lead-request-modal" aria-hidden="true">
    <div class="lead-request-dialog" role="dialog" aria-modal="true" aria-labelledby="leadRequestTitle">
        <div class="lead-request-dialog-header">
            <h3 id="leadRequestTitle" class="lead-request-dialog-title">Kitni leads chahiye?</h3>
            <button type="button" class="lead-request-close" onclick="closeLeadRequestModal()" aria-label="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" action="{{ route('lead-bank.requests.store') }}" class="lead-request-body">
            @csrf
            <input type="hidden" name="expiry_days" value="7">
            <input type="hidden" name="reason" value="Telecaller requested leads from lead section.">
            <label for="leadRequestQuantity" class="lead-request-label">Quantity select karo</label>
            <div class="lead-request-quick" aria-label="Quick quantity">
                <button type="button" onclick="setLeadRequestQuantity(25, this)">25</button>
                <button type="button" onclick="setLeadRequestQuantity(50, this)">50</button>
                <button type="button" onclick="setLeadRequestQuantity(100, this)" class="is-selected">100</button>
                <button type="button" onclick="setLeadRequestQuantity(200, this)">200</button>
            </div>
            <input id="leadRequestQuantity" name="quantity" class="lead-request-input" type="number" min="1" max="5000" value="100" required>
            <button type="submit" class="lead-request-submit">
                <i class="fas fa-paper-plane"></i>
                Submit Request
            </button>
        </form>
    </div>
</div>

<!-- Short Details Modal -->
<div id="shortDetailsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Short Details</h3>
                <button onclick="closeShortDetailsModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="shortDetailsContent" class="text-gray-700">
                <!-- Lead details will be loaded here -->
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Override API_BASE_URL for telecaller-specific endpoints
    API_BASE_URL = '{{ url("/api/telecaller") }}';
    let currentPage = 1;
    let searchTimeout = null;

    function openLeadRequestModal() {
        const modal = document.getElementById('leadRequestModal');
        if (!modal) return;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        setTimeout(() => document.getElementById('leadRequestQuantity')?.focus(), 50);
    }

    function closeLeadRequestModal() {
        const modal = document.getElementById('leadRequestModal');
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
    }

    function setLeadRequestQuantity(quantity, button) {
        const input = document.getElementById('leadRequestQuantity');
        if (input) input.value = quantity;
        document.querySelectorAll('.lead-request-quick button').forEach((item) => item.classList.remove('is-selected'));
        button?.classList.add('is-selected');
    }

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeLeadRequestModal();
        }
    });

    document.addEventListener('click', function(event) {
        const modal = document.getElementById('leadRequestModal');
        if (modal && event.target === modal) {
            closeLeadRequestModal();
        }
    });

    // Get token: localStorage first, then meta tag, then session (and persist to localStorage)
    function getToken() {
        var token = localStorage.getItem('telecaller_token');
        if (token) return token;
        var meta = document.querySelector('meta[name="api-token"]');
        if (meta && meta.getAttribute('content')) {
            token = meta.getAttribute('content').trim();
            if (token) {
                localStorage.setItem('telecaller_token', token);
                return token;
            }
        }
        var sessionToken = '{{ session("telecaller_api_token") ?? session("api_token") ?? "" }}';
        if (sessionToken) {
            localStorage.setItem('telecaller_token', sessionToken);
            return sessionToken;
        }
        return null;
    }

    // API call helper
    async function apiCall(endpoint, options = {}) {
        const token = getToken();
        if (!token) {
            console.error('No token found, redirecting to login');
            setTimeout(() => {
                window.location.href = '{{ route("login") }}';
            }, 3000);
            return { success: false, message: 'Authentication required. Please login again.' };
        }

        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': `Bearer ${token}`,
            },
        };

        try {
            const fullUrl = `${API_BASE_URL}${endpoint}`;
            console.log('Making API call to:', fullUrl);
            console.log('Token present:', !!token);
            
            const controller = new AbortController();
            const timeoutId = setTimeout(() => {
                console.error('Request timeout after 30 seconds');
                controller.abort();
            }, 30000); // 30 second timeout

            const response = await fetch(fullUrl, {
                ...defaultOptions,
                ...options,
                headers: { ...defaultOptions.headers, ...options.headers },
                signal: controller.signal,
            });

            clearTimeout(timeoutId);

            console.log('API Response status:', response.status);
            console.log('API Response headers:', Object.fromEntries(response.headers.entries()));

            if (response.status === 401) {
                console.error('Unauthorized - clearing token and redirecting');
                localStorage.removeItem('telecaller_token');
                localStorage.removeItem('telecaller_user');
                window.location.href = '{{ route("login") }}';
                return null;
            }

            let responseData;
            const contentType = response.headers.get('content-type');
            
            if (contentType && contentType.includes('application/json')) {
                try {
                    responseData = await response.json();
                } catch (e) {
                    console.error('Failed to parse JSON response:', e);
                    const text = await response.text();
                    console.error('Response text:', text);
                    return { success: false, message: 'Invalid JSON response from server' };
                }
            } else {
                const text = await response.text();
                console.error('Non-JSON response:', text);
                return { success: false, message: text || `HTTP ${response.status}` };
            }

            if (!response.ok) {
                console.error('API Error Response:', responseData);
                return { 
                    success: false, 
                    message: responseData.message || responseData.error || `HTTP ${response.status}`,
                    errors: responseData.errors || null
                };
            }

            console.log('API Response data:', responseData); // Debug log
            return responseData;
        } catch (error) {
            console.error('API Call Error:', error);
            console.error('Error stack:', error.stack);
            if (error.name === 'AbortError') {
                return { success: false, message: 'Request timeout. Please try again.' };
            }
            if (error.message.includes('Failed to fetch')) {
                return { success: false, message: 'Network error: Unable to connect to server. Please check your internet connection.' };
            }
            return { success: false, message: error.message || 'Network error occurred' };
        }
    }

    // Format status for display
    function formatStatus(status) {
        const statusMap = {
            'new': 'New',
            'contacted': 'Contacted',
            'qualified': 'Qualified',
            'site_visit_scheduled': 'Site Visit Scheduled',
            'site_visit_completed': 'Site Visit Completed',
            'negotiation': 'Negotiation',
            'closed_won': 'Closed Won',
            'closed_lost': 'Closed Lost',
            'on_hold': 'On Hold',
            'verified_prospect': 'Verified',
            'pending': 'Pending',
            'rejected': 'Rejected',
        };
        return statusMap[status] || status;
    }

    // Format date
    function formatDate(dateString) {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
    }

    // Load leads
    async function loadLeads(page = 1) {
        currentPage = page;
        const contentDiv = document.getElementById('leadsContent');
        if (!contentDiv) {
            console.error('leadsContent element not found!');
            return;
        }
        
        contentDiv.className = 'leads-grid';
        
        // Check token first before showing loading
        const token = getToken();
        if (!token) {
            console.error('No token found! User needs to login.');
            contentDiv.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-lock"></i>
                    <h3>Authentication Required</h3>
                    <p>You need to login to view leads.</p>
                    <p style="font-size: 12px; color: #999; margin-top: 8px;">Redirecting to login page...</p>
                    <a href="{{ route('login') }}" style="margin-top: 10px; padding: 8px 16px; background: linear-gradient(135deg, #16a34a 0%, #15803d 100%); color: white; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block;">Go to Login</a>
                </div>
            `;
            setTimeout(() => {
                window.location.href = '{{ route("login") }}';
            }, 2000);
            return;
        }
        
        contentDiv.innerHTML = '<div class="loading-state"><i class="fas fa-spinner fa-spin"></i><p>Loading leads...</p></div>';

        const searchInput = document.getElementById('searchInput');
        const statusFilter = document.getElementById('statusFilter');
        const search = searchInput ? searchInput.value : '';
        const status = statusFilter ? statusFilter.value : '';

        // Read date_range from URL (header dropdown) so Today filter shows only today's leads
        const urlParams = new URLSearchParams(window.location.search);
        const dateRange = urlParams.get('date_range') || 'today';
        const startDate = urlParams.get('start_date') || '';
        const endDate = urlParams.get('end_date') || '';

        let endpoint = `/leads?per_page=50&page=${page}&date_range=${encodeURIComponent(dateRange)}`;
        if (startDate) endpoint += `&start_date=${encodeURIComponent(startDate)}`;
        if (endDate) endpoint += `&end_date=${encodeURIComponent(endDate)}`;
        if (search) {
            endpoint += `&search=${encodeURIComponent(search)}`;
        }
        if (status) {
            endpoint += `&status=${encodeURIComponent(status)}`;
        }

        console.log('=== LOADING LEADS ===');
        console.log('Endpoint:', endpoint);
        console.log('API Base URL:', API_BASE_URL);
        console.log('Full URL:', `${API_BASE_URL}${endpoint}`);
        console.log('Token exists:', !!token);
        console.log('Token length:', token ? token.length : 0);
        
        // Add a timeout fallback
        const timeoutId = setTimeout(() => {
            console.error('=== TIMEOUT: API call took too long ===');
            if (contentDiv.innerHTML.includes('Loading leads...')) {
                contentDiv.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-exclamation-triangle"></i>
                        <h3>Request Timeout</h3>
                        <p>The request is taking too long. Please check your connection and try again.</p>
                        <button onclick="loadLeads(${page})" style="margin-top: 10px; padding: 8px 16px; background: linear-gradient(135deg, #16a34a 0%, #15803d 100%); color: white; border: none; border-radius: 4px; cursor: pointer;">Retry</button>
                    </div>
                `;
            }
        }, 30000); // 30 second timeout
        
        let result;
        try {
            console.log('Calling apiCall...');
            result = await apiCall(endpoint);
            clearTimeout(timeoutId);
            console.log('=== API CALL COMPLETED ===');
            console.log('Result:', result);
            console.log('Result type:', typeof result);
            console.log('Result success:', result?.success);
            console.log('Result data:', result?.data);
        } catch (error) {
            clearTimeout(timeoutId);
            console.error('=== ERROR IN LOADLEADS ===');
            console.error('Error:', error);
            console.error('Error message:', error.message);
            console.error('Error stack:', error.stack);
            contentDiv.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h3>Error Loading Leads</h3>
                    <p>An error occurred: ${error.message}</p>
                    <p style="font-size: 12px; color: #999; margin-top: 8px;">Please check the browser console (F12) for details.</p>
                    <button onclick="loadLeads(${page})" style="margin-top: 10px; padding: 8px 16px; background: linear-gradient(135deg, #16a34a 0%, #15803d 100%); color: white; border: none; border-radius: 4px; cursor: pointer;">Retry</button>
                </div>
            `;
            return;
        }

        if (!result) {
            console.error('=== NO RESULT RETURNED ===');
            console.error('Result is null or undefined');
            // Check if token exists
            const token = getToken();
            if (!token) {
                contentDiv.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-lock"></i>
                        <h3>Authentication Required</h3>
                        <p>You need to login to view leads.</p>
                        <p style="font-size: 12px; color: #999; margin-top: 8px;">Redirecting to login page...</p>
                        <a href="{{ route('login') }}" style="margin-top: 10px; padding: 8px 16px; background: linear-gradient(135deg, #16a34a 0%, #15803d 100%); color: white; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block;">Go to Login</a>
                    </div>
                `;
                setTimeout(() => {
                    window.location.href = '{{ route("login") }}';
                }, 2000);
            } else {
                contentDiv.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-exclamation-triangle"></i>
                        <h3>Error Loading Leads</h3>
                        <p>No response from server. Please check your connection.</p>
                        <p style="font-size: 12px; color: #999; margin-top: 8px;">Check browser console (F12) for details.</p>
                        <button onclick="loadLeads(${page})" style="margin-top: 10px; padding: 8px 16px; background: linear-gradient(135deg, #16a34a 0%, #15803d 100%); color: white; border: none; border-radius: 4px; cursor: pointer;">Retry</button>
                    </div>
                `;
            }
            return;
        }

        console.log('=== CHECKING RESULT ===');
        console.log('Result success:', result.success);
        console.log('Result data:', result.data);
        console.log('Result message:', result.message);

        if (result.success === false || !result.success) {
            console.error('=== API RETURNED ERROR ===');
            console.error('Error result:', result);
            const errorMsg = result.message || result.error || 'Failed to load leads. Please try again.';
            contentDiv.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h3>Error Loading Leads</h3>
                    <p>${errorMsg}</p>
                    <p style="font-size: 12px; color: #999; margin-top: 8px;">Response: ${JSON.stringify(result).substring(0, 200)}...</p>
                    <button onclick="loadLeads(${page})" style="margin-top: 10px; padding: 8px 16px; background: linear-gradient(135deg, #16a34a 0%, #15803d 100%); color: white; border: none; border-radius: 4px; cursor: pointer;">Retry</button>
                </div>
            `;
            return;
        }

        const leads = result.data || [];
        const pagination = result.pagination || {};

        console.log('Leads count:', leads.length); // Debug log
        console.log('Leads data:', leads); // Debug log
        console.log('Pagination:', pagination); // Debug log
        console.log('Full result:', result); // Debug log

        if (!Array.isArray(leads)) {
            console.error('Leads is not an array:', leads);
            contentDiv.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h3>Data Format Error</h3>
                    <p>Invalid response format. Check console for details.</p>
                    <p style="font-size: 12px; color: #999; margin-top: 8px;">Response type: ${typeof leads}</p>
                </div>
            `;
            return;
        }

        if (leads.length === 0) {
            contentDiv.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-user-friends"></i>
                    <h3>No Leads Found</h3>
                    <p>You don't have any assigned leads at the moment.</p>
                    <p style="font-size: 12px; color: #999; margin-top: 8px;">Total in system: ${pagination.total || 0}</p>
                </div>
            `;
            return;
        }

        // Build cards
        let cardsHTML = '';

        leads.forEach(lead => {
            const assignedDate = lead.assigned_at ? formatDate(lead.assigned_at) : '-';
            const statusClass = `status-${lead.status}`;
            
            // Format date and time for display
            const dateTime = lead.assigned_at ? new Date(lead.assigned_at) : (lead.created_at ? new Date(lead.created_at) : null);
            const formattedDateTime = dateTime ? dateTime.toLocaleString('en-IN', { 
                day: 'numeric', 
                month: 'short', 
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            }) : '-';

            cardsHTML += `
                <div class="lead-card">
                    <div class="lead-card-header-new">
                        <h3 class="lead-name-new">${lead.name || '-'}</h3>
                        <span class="status-badge-new ${statusClass}">${formatStatus(lead.status)}</span>
                    </div>
                    <div class="lead-info-new">
                        <div class="lead-info-row-new">
                            <span class="lead-info-label">Assigned to:</span>
                            <span class="lead-info-value-new">${lead.assigned_to_name || 'Not Assigned'}</span>
                        </div>
                        <div class="lead-info-row-new">
                            <i class="fas fa-phone"></i>
                            <span class="lead-info-value-new">${lead.phone || '-'}</span>
                        </div>
                        <div class="lead-info-row-new">
                            <i class="fas fa-calendar"></i>
                            <span class="lead-info-value-new">${formattedDateTime}</span>
                        </div>
                    </div>
                    <div class="lead-card-footer-new">
                        <a href="/leads/${lead.id}" class="lead-card-btn-new btn-view-detail-new">
                            <i class="fas fa-eye"></i>
                            View Detail
                        </a>
                        <a href="/leads/${lead.id}/short-details" class="lead-card-btn-new btn-short-detail-new" onclick="event.preventDefault(); viewShortDetails(${lead.id}); return false;">
                            <i class="fas fa-info-circle"></i>
                            Short Detail
                        </a>
                    </div>
                </div>
            `;
        });

        // Add pagination
        if (pagination.last_page > 1) {
            cardsHTML += `
                <div class="pagination">
                    <button onclick="loadLeads(${pagination.current_page - 1})" ${pagination.current_page === 1 ? 'disabled' : ''}>
                        <i class="fas fa-chevron-left"></i> Previous
                    </button>
                    <div class="page-info">
                        Page ${pagination.current_page} of ${pagination.last_page} (${pagination.total} total)
                    </div>
                    <button onclick="loadLeads(${pagination.current_page + 1})" ${pagination.current_page === pagination.last_page ? 'disabled' : ''}>
                        Next <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            `;
        }

        contentDiv.innerHTML = cardsHTML;
    }

    // Handle search with debounce
    function handleSearch() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            loadLeads(1);
        }, 500);
    }

    // View short details modal
    async function viewShortDetails(leadId) {
        const modal = document.getElementById('shortDetailsModal');
        const content = document.getElementById('shortDetailsContent');
        
        // Show loading state
        content.innerHTML = '<div class="text-center py-8"><div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600 mx-auto"></div><p class="mt-4 text-gray-600">Loading lead details...</p></div>';
        modal.classList.remove('hidden');
        
        try {
            const response = await fetch(`/leads/${leadId}/short-details`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                throw new Error('Failed to load lead details');
            }
            
            const data = await response.json();
            const lead = data.data || data;
            
            // Render lead details
            const statusLabels = {
                'new': 'New',
                'contacted': 'Contacted',
                'connected': 'Connected',
                'verified_prospect': 'Verified Prospect',
                'pending': 'Pending',
                'rejected': 'Rejected',
                'meeting_scheduled': 'Meeting Scheduled',
                'meeting_completed': 'Meeting Completed',
                'visit_scheduled': 'Visit Scheduled',
                'visit_done': 'Visit Done',
                'closed': 'Closed',
                'dead': 'Dead',
                'on_hold': 'On Hold',
            };
            
            const statusLabel = statusLabels[lead.status] || lead.status;
            const createdDate = lead.created_at ? new Date(lead.created_at).toLocaleDateString('en-IN', { year: 'numeric', month: 'long', day: 'numeric' }) : '-';
            
            content.innerHTML = `
                <div class="space-y-4">
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-2">${lead.name || '-'}</h4>
                        <p class="text-sm text-gray-600">Status: <span class="font-medium">${statusLabel}</span></p>
                    </div>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        ${lead.phone ? `
                        <div>
                            <span class="text-gray-500">Phone:</span>
                            <span class="font-medium text-gray-900 ml-2">${lead.phone}</span>
                        </div>
                        ` : ''}
                        ${lead.email ? `
                        <div>
                            <span class="text-gray-500">Email:</span>
                            <span class="font-medium text-gray-900 ml-2">${lead.email}</span>
                        </div>
                        ` : ''}
                        ${lead.city ? `
                        <div>
                            <span class="text-gray-500">City:</span>
                            <span class="font-medium text-gray-900 ml-2">${lead.city}${lead.state ? ', ' + lead.state : ''}</span>
                        </div>
                        ` : ''}
                        ${lead.budget ? `
                        <div>
                            <span class="text-gray-500">Budget:</span>
                            <span class="font-medium text-gray-900 ml-2">₹${parseFloat(lead.budget).toLocaleString('en-IN')}</span>
                        </div>
                        ` : ''}
                        ${lead.preferred_location ? `
                        <div>
                            <span class="text-gray-500">Location:</span>
                            <span class="font-medium text-gray-900 ml-2">${lead.preferred_location}</span>
                        </div>
                        ` : ''}
                        ${createdDate !== '-' ? `
                        <div>
                            <span class="text-gray-500">Created:</span>
                            <span class="font-medium text-gray-900 ml-2">${createdDate}</span>
                        </div>
                        ` : ''}
                    </div>
                    ${lead.notes ? `
                    <div class="pt-2 border-t">
                        <span class="text-gray-500 text-sm">Notes:</span>
                        <p class="text-gray-900 mt-1">${lead.notes}</p>
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
    
    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeShortDetailsModal();
        }
    });

    function formatPhoneForDialer(phoneNumber) {
        const cleaned = String(phoneNumber || '').replace(/[^\d+]/g, '');

        if (!cleaned) {
            return '';
        }

        if (cleaned.startsWith('+')) {
            return cleaned;
        }

        if (cleaned.length === 10) {
            return `+91${cleaned}`;
        }

        if (cleaned.length === 12 && cleaned.startsWith('91')) {
            return `+${cleaned}`;
        }

        return `+${cleaned}`;
    }

    // Call button functions
    function initiateCall(leadId, phoneNumber) {
        console.log('Initiating call for lead:', leadId, 'Phone:', phoneNumber);
        if (phoneNumber && phoneNumber !== '-') {
            const dialNumber = formatPhoneForDialer(phoneNumber);
            window.location.href = `tel:${dialNumber}`;
            // You can also add API call here to log the call initiation
            // apiCall(`/leads/${leadId}/call`, { method: 'POST' });
        } else {
            alert('Phone number not available for this lead.');
        }
    }

    function openWhatsApp(leadId, phoneNumber) {
        if (window.CRM_PHONE_MASKED && typeof window.openProtectedLeadWhatsApp === 'function') {
            window.openProtectedLeadWhatsApp(leadId);
            return;
        }
        console.log('Opening WhatsApp for lead:', leadId, 'Phone:', phoneNumber);
        if (phoneNumber && phoneNumber !== '-') {
            // Remove any non-digit characters except + for international format
            const cleanPhone = phoneNumber.replace(/[^\d+]/g, '');
            // Open WhatsApp with the phone number
            const whatsappUrl = `https://wa.me/${cleanPhone}`;
            window.open(whatsappUrl, '_blank');
        } else {
            alert('Phone number not available for this lead.');
        }
    }

    // Initialize on page load
    console.log('Leads script loaded');
    
    function initializeLeads() {
        console.log('Initializing leads page...');
        const contentDiv = document.getElementById('leadsContent');
        if (!contentDiv) {
            console.error('leadsContent element not found, retrying...');
            setTimeout(initializeLeads, 100);
            return;
        }
        console.log('leadsContent found, loading leads...');
        loadLeads();
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeLeads);
    } else {
        // DOM already loaded
        setTimeout(initializeLeads, 100);
    }
</script>
@endpush

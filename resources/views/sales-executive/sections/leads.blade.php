@extends('sales-executive.layout')

@section('title', 'Leads - Sales Executive')
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
    .btn-call-new {
        background: #ecfdf5;
        color: #047857;
        border: 1px solid #a7f3d0;
    }
    .btn-call-new:hover {
        background: #d1fae5;
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(4, 120, 87, 0.18);
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
</style>
@endpush

@section('content')
    <div class="leads-container">
        <!-- Search and Filter Bar -->
        <div class="search-filter-bar">
            <input type="text" id="searchInput" class="search-input" placeholder="Search by name, phone, email, or city..." onkeyup="handleSearch()">
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
@include('partials.lead-cloud-call-menu')
@endsection

@push('scripts')
<script>
    // Override API_BASE_URL for Sales Executive-specific endpoints
    API_BASE_URL = '{{ url("/api/telecaller") }}';
    let currentPage = 1;
    let searchTimeout = null;

    // Get token: localStorage first, then meta tag, then session (and persist to localStorage)
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
                localStorage.removeItem('sales-executive_token');
                localStorage.removeItem('sales-executive_user');
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
                        <button type="button" data-lead-call-trigger data-lead-id="${lead.id}" data-lead-phone="${String(lead.phone || '').replace(/"/g, '&quot;')}" class="lead-card-btn-new btn-call-new">
                            <i class="fas fa-phone"></i>
                            Call
                        </button>
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

    // Call button functions
    function initiateCall(leadId, phoneNumber) {
        if (typeof openLeadCallMenu === 'function') {
            openLeadCallMenu(leadId, phoneNumber);
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

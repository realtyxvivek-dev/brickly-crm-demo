@extends('sales-executive.layout')

@section('title', 'Verification Pending - Sales Executive')
@section('page-title', 'Verification Pending')

@push('styles')
<style>
    .verification-container {
        background: white;
        padding: 24px;
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .filter-row {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 20px;
        flex-wrap: nowrap;
        width: 100%;
        box-sizing: border-box;
    }
    .filter-row .filter-col {
        flex: 0 0 33.33%;
        max-width: 33.33%;
        min-width: 0;
        box-sizing: border-box;
    }
    .filter-row .filter-col-search {
        flex: 0 0 33.34%;
        max-width: 33.34%;
        min-width: 0;
        box-sizing: border-box;
    }
    @media (max-width: 768px) {
        .filter-row {
            gap: 6px;
        }
        .filter-row .filter-col,
        .filter-row .filter-col-search {
            flex: 0 0 33.33%;
            max-width: 33.33%;
        }
        .filter-row .filter-col-search {
            flex: 0 0 33.34%;
            max-width: 33.34%;
        }
        .filter-row select {
            padding: 6px 10px;
            font-size: 13px;
            height: 38px;
            line-height: 1.3;
            box-sizing: border-box;
        }
        .search-filter-btn {
            padding: 0 10px;
            font-size: 13px;
            height: 38px;
            line-height: 1.3;
            display: flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
        }
        .search-filter-btn i {
            margin-right: 6px;
        }
    }
    .filter-row select {
        width: 100%;
        padding: 10px 16px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        background: white;
        color: #063A1C;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s;
    }
    .filter-row select:hover {
        border-color: #205A44;
    }
    .filter-row select:focus {
        outline: none;
        border-color: #205A44;
    }
    .search-filter-btn {
        width: 100%;
        padding: 10px 20px;
        border: none;
        border-radius: 8px;
        background: #205A44;
        color: white;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        box-sizing: border-box;
    }
    .search-filter-btn:hover {
        background: #063A1C;
    }
    .search-filter-btn i {
        margin-right: 8px;
    }
    .date-col-wrap {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .custom-date-inputs {
        display: none;
        align-items: center;
        gap: 10px;
        margin-top: 8px;
        flex-wrap: wrap;
    }
    .custom-date-inputs.show {
        display: flex;
    }
    .custom-date-inputs .date-input {
        padding: 8px 12px;
        border: 2px solid #E5DED4;
        border-radius: 8px;
        font-size: 14px;
    }
    .prospects-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-top: 20px;
    }
    @media (max-width: 1024px) {
        .prospects-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    @media (max-width: 768px) {
        .prospects-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }
    }
    @media (max-width: 480px) {
        .prospects-grid {
            grid-template-columns: 1fr;
        }
    }
    .prospect-card {
        background: white;
        border: 2px solid #e0e0e0;
        border-radius: 12px;
        padding: 20px;
        transition: all 0.3s;
    }
    .prospect-card:hover {
        border-color: #205A44;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
        transform: translateY(-2px);
    }
    .prospect-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 16px;
        padding-bottom: 16px;
        border-bottom: 2px solid #f0f0f0;
    }
    .prospect-name {
        font-size: 18px;
        font-weight: 600;
        color: #063A1C;
        margin: 0;
    }
    .prospect-info {
        margin-bottom: 12px;
    }
    .prospect-info-row {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 8px;
        font-size: 14px;
        color: #063A1C;
    }
    .prospect-info-row i {
        color: #205A44;
        width: 16px;
    }
    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 500;
        margin-top: 8px;
    }
    .status-pending {
        background: #fef3c7;
        color: #92400e;
    }
    .status-approved {
        background: #d1fae5;
        color: #065f46;
    }
    .status-rejected {
        background: #fee2e2;
        color: #991b1b;
    }
    .prospect-footer {
        margin-top: 16px;
        padding-top: 16px;
        border-top: 2px solid #f0f0f0;
        font-size: 12px;
        color: #B3B5B4;
    }
    .prospect-footer-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 4px;
    }
    .btn-whatsapp-prospect {
        width: 100%;
        padding: 8px;
        background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
        color: white;
        border: none;
        box-shadow: 0 2px 4px rgba(21, 128, 61, 0.3);
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        margin-top: 12px;
        transition: all 0.3s;
    }
    .btn-whatsapp-prospect:hover {
        background: linear-gradient(135deg, #15803d 0%, #166534 100%);
        box-shadow: 0 4px 8px rgba(21, 128, 61, 0.4);
        transform: translateY(-1px);
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
    .rejection-reason {
        margin-top: 12px;
        padding: 12px;
        background: #fee2e2;
        border-left: 4px solid #ef4444;
        border-radius: 4px;
        font-size: 13px;
        color: #991b1b;
    }
</style>
@endpush

@section('content')
    <div class="verification-container">
        <div class="filter-row">
            <div class="filter-col">
                <select id="status-filter" class="filter-select">
                    <option value="pending" selected>Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                    <option value="all">All</option>
                </select>
            </div>
            <div class="filter-col">
                <div class="date-col-wrap">
                    <select id="date-filter" class="filter-select">
                        <option value="today" selected>Today</option>
                        <option value="this_week">This Week</option>
                        <option value="this_month">This Month</option>
                        <option value="custom">Custom Date</option>
                    </select>
                    <div class="custom-date-inputs" id="custom-date-inputs">
                        <input type="date" id="start-date" class="date-input">
                        <span style="color: #B3B5B4;">to</span>
                        <input type="date" id="end-date" class="date-input">
                    </div>
                </div>
            </div>
            <div class="filter-col-search">
                <button type="button" id="search-filter-btn" class="search-filter-btn"><i class="fas fa-search mr-2"></i>Search</button>
            </div>
        </div>

        <div id="pendingSummaryStrip" class="pending-summary-strip" style="display: none; margin-bottom: 16px; padding: 12px 16px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; font-size: 14px; color: #063A1C;"></div>

        <div id="prospectsContent" class="prospects-grid">
            <div class="loading-state">
                <i class="fas fa-spinner fa-spin"></i>
                <p>Loading prospects...</p>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    var API_BASE_URL = '{{ url("/api/telecaller") }}';
    let currentStatus = 'pending';
    let currentDateFilter = 'today';
    let customStartDate = '';
    let customEndDate = '';

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
            
            fetchOptions.method = options.method || 'GET';
            
            if (options.body && (fetchOptions.method === 'POST' || fetchOptions.method === 'PUT' || fetchOptions.method === 'PATCH')) {
                fetchOptions.body = typeof options.body === 'string' ? options.body : JSON.stringify(options.body);
            }
            
            const response = await fetch(url, fetchOptions);

            console.log('Response status:', response.status);

            if (response.status === 401) {
                console.error('Unauthorized - clearing token');
                localStorage.removeItem('sales-executive_token');
                localStorage.removeItem('Sales Executive_user');
                setTimeout(() => {
                    window.location.href = '{{ route("login") }}';
                }, 2000);
                return { success: false, message: 'Session expired. Redirecting to login...' };
            }

            if (!response.ok) {
                const errorText = await response.text();
                console.error('API Error Response:', errorText);
                try {
                    const errorJson = JSON.parse(errorText);
                    return { success: false, message: errorJson.message || errorText };
                } catch (e) {
                    return { success: false, message: errorText || `HTTP ${response.status}: ${response.statusText}` };
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

    async function loadProspects(status = 'pending') {
        currentStatus = status;
        const contentDiv = document.getElementById('prospectsContent');
        if (!contentDiv) {
            console.error('prospectsContent element not found');
            return;
        }
        contentDiv.className = 'prospects-grid';
        contentDiv.innerHTML = '<div class="loading-state"><i class="fas fa-spinner fa-spin"></i><p>Loading prospects...</p></div>';

        let endpoint = `/prospects?status=${status}&per_page=20&date_range=${currentDateFilter}`;
        if (currentDateFilter === 'custom' && customStartDate && customEndDate) {
            endpoint += `&start_date=${customStartDate}&end_date=${customEndDate}`;
        }
        console.log('Loading prospects from:', API_BASE_URL + endpoint);
        
        const result = await apiCall(endpoint);
        console.log('API Result:', result);

        if (!result) {
            contentDiv.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h3>Error Loading Prospects</h3>
                    <p>No response from server. Please check your connection.</p>
                    <button onclick="loadProspects('${status}')" style="margin-top: 16px; padding: 10px 20px; background: #205A44; color: white; border: none; border-radius: 8px; cursor: pointer;">Retry</button>
                </div>
            `;
            return;
        }

        if (!result.success) {
            contentDiv.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h3>Error Loading Prospects</h3>
                    <p>${result.message || 'Failed to load prospects'}</p>
                    <button onclick="loadProspects('${status}')" style="margin-top: 16px; padding: 10px 20px; background: #205A44; color: white; border: none; border-radius: 8px; cursor: pointer;">Retry</button>
                </div>
            `;
            return;
        }

        const prospects = result.data || [];
        const pendingSummary = result.pending_summary || [];

        const summaryStrip = document.getElementById('pendingSummaryStrip');
        if (summaryStrip) {
            if (pendingSummary.length > 0 && (currentStatus === 'pending' || currentStatus === 'all')) {
                const parts = pendingSummary.map(function(item) {
                    return item.name + ' (' + item.role_display + '): ' + item.count;
                });
                summaryStrip.textContent = 'Pending at: ' + parts.join(' | ');
                summaryStrip.style.display = 'block';
            } else {
                summaryStrip.style.display = 'none';
            }
        }

        if (prospects.length === 0) {
            contentDiv.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-clipboard-check"></i>
                    <h3>No Prospects Found</h3>
                    <p>You don't have any ${status === 'all' ? '' : status} prospects at the moment.</p>
                </div>
            `;
            return;
        }

        let cardsHTML = '';
        prospects.forEach(prospect => {
            const statusClass = `status-${prospect.verification_status}`;
            const statusText = formatStatus(prospect.verification_status);
            const createdDate = formatDate(prospect.created_at);
            const verifiedDate = prospect.verified_at ? formatDate(prospect.verified_at) : null;

            cardsHTML += `
                <div class="prospect-card">
                    <div class="prospect-header">
                        <h3 class="prospect-name">${prospect.customer_name || '-'}</h3>
                        <span class="status-badge ${statusClass}">${statusText}</span>
                    </div>
                    <div class="prospect-info">
                        <div class="prospect-info-row">
                            <i class="fas fa-phone"></i>
                            <span>${prospect.phone || '-'}</span>
                        </div>
                        ${prospect.budget ? `
                        <div class="prospect-info-row">
                            <i class="fas fa-rupee-sign"></i>
                            <span>Budget: ${prospect.budget}</span>
                        </div>
                        ` : ''}
                        ${prospect.preferred_location ? `
                        <div class="prospect-info-row">
                            <i class="fas fa-map-marker-alt"></i>
                            <span>Location: ${prospect.preferred_location}</span>
                        </div>
                        ` : ''}
                        ${prospect.size ? `
                        <div class="prospect-info-row">
                            <i class="fas fa-ruler-combined"></i>
                            <span>Size: ${prospect.size}</span>
                        </div>
                        ` : ''}
                        <div class="prospect-info-row">
                            <i class="fas fa-tag"></i>
                            <span>Purpose: ${prospect.purpose === 'end_user' ? 'End User' : 'Investment'}</span>
                        </div>
                        ${prospect.remark ? `
                        <div class="prospect-info-row" style="margin-top: 12px;">
                            <i class="fas fa-comment"></i>
                            <span style="font-style: italic; color: #B3B5B4;">${prospect.remark}</span>
                        </div>
                        ` : ''}
                        ${prospect.manager_remark ? `
                        <div class="prospect-info-row" style="margin-top: 12px; padding: 12px; background: #d1fae5; border-left: 4px solid #10b981; border-radius: 4px;">
                            <i class="fas fa-user-tie" style="color: #065f46;"></i>
                            <div>
                                <strong style="color: #065f46; display: block; margin-bottom: 4px;">Manager Remark:</strong>
                                <span style="color: #065f46;">${prospect.manager_remark}</span>
                            </div>
                        </div>
                        ` : ''}
                    </div>
                    <div class="prospect-footer">
                        ${(prospect.verification_status === 'pending' || prospect.verification_status === 'pending_verification') ? `
                            <div class="prospect-footer-row">
                                <span><i class="fas fa-user-tie"></i> Pending at:</span>
                                <span style="font-weight: 600;">${prospect.manager_name || 'Not Assigned'} (${prospect.verifier_level || 'Not Assigned'})</span>
                            </div>
                            <div class="prospect-footer-row">
                                <span><i class="fas fa-calendar"></i> Sent on:</span>
                                <span>${createdDate}</span>
                            </div>
                        ` : ''}
                        ${prospect.verification_status === 'approved' ? `
                            <div class="prospect-footer-row">
                                <span><i class="fas fa-check-circle"></i> Verified by:</span>
                                <span style="font-weight: 600; color: #10b981;">${prospect.verified_by_name || 'Manager'}</span>
                            </div>
                            <div class="prospect-footer-row">
                                <span><i class="fas fa-calendar"></i> Verified on:</span>
                                <span>${verifiedDate || '-'}</span>
                            </div>
                        ` : ''}
                        ${prospect.verification_status === 'rejected' ? `
                            <div class="prospect-footer-row">
                                <span><i class="fas fa-times-circle"></i> Rejected by:</span>
                                <span style="font-weight: 600; color: #ef4444;">${prospect.verified_by_name || 'Manager'}</span>
                            </div>
                            <div class="prospect-footer-row">
                                <span><i class="fas fa-calendar"></i> Rejected on:</span>
                                <span>${verifiedDate || '-'}</span>
                            </div>
                            ${prospect.rejection_reason ? `
                            <div class="rejection-reason">
                                <strong>Reason:</strong> ${prospect.rejection_reason}
                            </div>
                            ` : ''}
                        ` : ''}
                    </div>
                    <button class="btn-whatsapp-prospect" onclick="openWhatsApp('${prospect.phone || ''}')">
                        <i class="fab fa-whatsapp"></i>
                        WhatsApp
                    </button>
                </div>
            `;
        });

        contentDiv.innerHTML = cardsHTML;
    }

    function formatDate(dateString) {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    }

    function formatStatus(status) {
        const statusMap = {
            'pending': 'Pending Verification',
            'pending_verification': 'Pending Verification',
            'approved': 'Approved',
            'verified': 'Verified',
            'rejected': 'Rejected',
        };
        return statusMap[status] || status;
    }

    function applyFilters() {
        const statusEl = document.getElementById('status-filter');
        const dateEl = document.getElementById('date-filter');
        if (!statusEl || !dateEl) return;

        currentStatus = statusEl.value;
        currentDateFilter = dateEl.value;

        if (currentDateFilter === 'custom') {
            customStartDate = document.getElementById('start-date').value || '';
            customEndDate = document.getElementById('end-date').value || '';
            if (!customStartDate || !customEndDate) {
                alert('Please select both start and end dates for Custom Date.');
                return;
            }
            if (new Date(customStartDate) > new Date(customEndDate)) {
                alert('Start date cannot be after end date');
                return;
            }
        } else {
            customStartDate = '';
            customEndDate = '';
        }

        loadProspects(currentStatus);
    }

    function onDateFilterChange() {
        const dateEl = document.getElementById('date-filter');
        const customInputs = document.getElementById('custom-date-inputs');
        if (!dateEl || !customInputs) return;
        currentDateFilter = dateEl.value;
        if (currentDateFilter === 'custom') {
            customInputs.classList.add('show');
        } else {
            customInputs.classList.remove('show');
            document.getElementById('start-date').value = '';
            document.getElementById('end-date').value = '';
            customStartDate = '';
            customEndDate = '';
        }
    }

    function onStatusFilterChange() {
        const statusEl = document.getElementById('status-filter');
        if (statusEl) currentStatus = statusEl.value;
    }

    function openWhatsApp(phoneNumber) {
        if (!phoneNumber || phoneNumber === '-') {
            alert('Phone number not available for this prospect.');
            return;
        }
        const cleanedPhoneNumber = phoneNumber.replace(/[^\d+]/g, '');
        if (!cleanedPhoneNumber) {
            alert('Invalid phone number for WhatsApp.');
            return;
        }
        window.open(`https://wa.me/${cleanedPhoneNumber}`, '_blank');
    }

    // Initialize on page load
    function initializeProspects() {
        console.log('Verification Pending page loaded, initializing...');
        console.log('API_BASE_URL:', API_BASE_URL);
        console.log('Token:', getToken() ? 'Exists' : 'Missing');
        
        const statusEl = document.getElementById('status-filter');
        const dateEl = document.getElementById('date-filter');
        const searchBtn = document.getElementById('search-filter-btn');
        if (statusEl) statusEl.addEventListener('change', onStatusFilterChange);
        if (dateEl) dateEl.addEventListener('change', onDateFilterChange);
        if (searchBtn) searchBtn.addEventListener('click', applyFilters);
        
        const contentDiv = document.getElementById('prospectsContent');
        if (contentDiv) {
            loadProspects('pending');
        } else {
            console.error('prospectsContent element not found, retrying...');
            setTimeout(initializeProspects, 200);
        }
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeProspects);
    } else {
        // DOM already loaded
        initializeProspects();
    }
</script>
@endpush


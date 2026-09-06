// CRM Dashboard JavaScript
// API Base URL
const API_BASE = '/api/crm';
let authToken = null;
let previousOverPodRows = [];

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Try to get auth token - will use session-based auth if not available
    authToken = localStorage.getItem('crm_token');
    
    // Initialize dashboard
    initDashboard();
});

// Initialize dashboard
function initDashboard() {
    // Set up event listeners first so perf dropdowns work
    setupEventListeners();
    // Load performance filter roles then table
    loadPerformanceFilterRoles().then(() => loadTelecallerStats());
    loadSourceDistribution();
    loadLeadOperationsSummary();
    loadPreviousOverPod();
    loadRecentLeads();
    loadFreshLeadCnpSummary();
    loadUsers(); // For dropdowns
}

async function loadSourceDistribution() {
    const container = document.getElementById('crm-source-distribution');
    if (!container) return;

    try {
        const params = getSourceDateParams();
        let url = '/dashboard/source-distribution?date_range=' + encodeURIComponent(params.dateRange);

        if (params.dateRange === 'custom') {
            if (params.start) url += '&start_date=' + encodeURIComponent(params.start);
            if (params.end) url += '&end_date=' + encodeURIComponent(params.end);
        }

        const raw = await apiRequest(url);
        const data = normalizeDataArray(raw);
        renderSourceDistribution(container, data);
    } catch (error) {
        console.error('Error loading source distribution:', error);
        container.innerHTML = '<tr><td colspan="2" class="text-center text-danger py-4">Error loading lead sources.</td></tr>';
    }
}

function renderSourceDistribution(container, data) {
    if (!container) return;

    if (!data || data.length === 0) {
        container.innerHTML = '<tr><td colspan="2" class="text-center text-muted py-4">No leads found for the selected date range.</td></tr>';
        return;
    }

    container.innerHTML = data.map(function(item) {
        return `
            <tr>
                <td>${escapeHtmlPending(item.source || 'Other')}</td>
                <td class="text-end fw-semibold">${item.value ?? 0}</td>
            </tr>
        `;
    }).join('');
}

// Setup event listeners
function setupEventListeners() {
    document.querySelectorAll('[data-dashboard-clear-cache]').forEach(function(button) {
        button.addEventListener('click', handleDashboardCacheClear);
    });

    // Page header date range (if present)
    const dateRangeFilter = document.getElementById('date-range-filter');
    const customDateWrap = document.getElementById('custom-date-wrap');
    dateRangeFilter?.addEventListener('change', function() {
        const isCustom = this.value === 'custom';
        if (customDateWrap) customDateWrap.classList.toggle('d-none', !isCustom);
    });
    if (customDateWrap && dateRangeFilter) {
        customDateWrap.classList.toggle('d-none', dateRangeFilter.value !== 'custom');
    }
    document.getElementById('date-range-start')?.addEventListener('change', () => {});
    document.getElementById('date-range-end')?.addEventListener('change', () => {});

    // Sales Executive Performance: role + date dropdowns
    const perfRoleFilter = document.getElementById('perf-role-filter');
    const perfDateRange = document.getElementById('perf-date-range');
    const perfCustomWrap = document.getElementById('perf-custom-date-wrap');
    const sourceDateRange = document.getElementById('source-date-range');
    const sourceCustomWrap = document.getElementById('source-custom-date-wrap');
    perfRoleFilter?.addEventListener('change', () => { loadTelecallerStats(); loadFreshLeadCnpSummary(); });
    perfDateRange?.addEventListener('change', function() {
        const isCustom = this.value === 'custom';
        if (perfCustomWrap) perfCustomWrap.classList.toggle('d-none', !isCustom);
        loadTelecallerStats();
        loadSourceDistribution();
        loadLeadOperationsSummary();
        loadRecentLeads();
        loadFreshLeadCnpSummary();
    });
    if (perfCustomWrap && perfDateRange) {
        perfCustomWrap.classList.toggle('d-none', perfDateRange.value !== 'custom');
    }
    document.getElementById('perf-date-start')?.addEventListener('change', () => { if (perfDateRange?.value === 'custom') { loadTelecallerStats(); loadSourceDistribution(); loadLeadOperationsSummary(); loadRecentLeads(); loadFreshLeadCnpSummary(); } });
    document.getElementById('perf-date-end')?.addEventListener('change', () => { if (perfDateRange?.value === 'custom') { loadTelecallerStats(); loadSourceDistribution(); loadLeadOperationsSummary(); loadRecentLeads(); loadFreshLeadCnpSummary(); } });

    // Leads Allocated section: date filter (filters both Leads Allocated and Average Response; syncs with perf)
    const leadsAllocDate = document.getElementById('leads-allocated-date-range');
    const leadsAllocCustomWrap = document.getElementById('leads-allocated-custom-date-wrap');
    if (leadsAllocDate && perfDateRange) {
        leadsAllocDate.value = perfDateRange.value;
        leadsAllocDate.addEventListener('change', function() {
            perfDateRange.value = this.value;
            if (perfCustomWrap) perfCustomWrap.classList.toggle('d-none', this.value !== 'custom');
            if (leadsAllocCustomWrap) leadsAllocCustomWrap.classList.toggle('d-none', this.value !== 'custom');
            loadSourceDistribution();
            loadLeadOperationsSummary();
            loadRecentLeads();
            loadFreshLeadCnpSummary();
        });
    }
    if (leadsAllocCustomWrap && leadsAllocDate) {
        leadsAllocCustomWrap.classList.toggle('d-none', leadsAllocDate.value !== 'custom');
    }
    document.getElementById('leads-allocated-date-start')?.addEventListener('change', function() {
        var perfStart = document.getElementById('perf-date-start');
        if (perfStart) perfStart.value = this.value;
        loadSourceDistribution();
        loadLeadOperationsSummary();
        loadRecentLeads();
        loadFreshLeadCnpSummary();
    });
    document.getElementById('leads-allocated-date-end')?.addEventListener('change', function() {
        var perfEnd = document.getElementById('perf-date-end');
        if (perfEnd) perfEnd.value = this.value;
        loadSourceDistribution();
        loadLeadOperationsSummary();
        loadRecentLeads();
        loadFreshLeadCnpSummary();
    });
    perfDateRange?.addEventListener('change', function syncPerfToLeadsAlloc() {
        if (leadsAllocDate) {
            leadsAllocDate.value = this.value;
            if (leadsAllocCustomWrap) leadsAllocCustomWrap.classList.toggle('d-none', this.value !== 'custom');
        }
    });

    if (sourceDateRange) {
        sourceDateRange.addEventListener('change', function() {
            if (sourceCustomWrap) sourceCustomWrap.classList.toggle('d-none', this.value !== 'custom');
            loadSourceDistribution();
        });
    }
    if (sourceCustomWrap && sourceDateRange) {
        sourceCustomWrap.classList.toggle('d-none', sourceDateRange.value !== 'custom');
    }
    document.getElementById('source-date-start')?.addEventListener('change', function() {
        if (sourceDateRange?.value === 'custom') {
            loadSourceDistribution();
        }
    });
    document.getElementById('source-date-end')?.addEventListener('change', function() {
        if (sourceDateRange?.value === 'custom') {
            loadSourceDistribution();
        }
    });

    document.getElementById('previous-over-pod-search')?.addEventListener('input', renderPreviousOverPodTable);
    document.getElementById('previous-over-pod-refresh')?.addEventListener('click', function() {
        loadPreviousOverPod();
    });

    // Form submissions
    document.getElementById('csv-upload-form')?.addEventListener('submit', handleCsvUpload);
    document.getElementById('add-lead-form')?.addEventListener('submit', handleAddLead);
    document.getElementById('blacklist-add-form')?.addEventListener('submit', handleAddBlacklist);
    document.getElementById('create-user-form')?.addEventListener('submit', handleCreateUser);
    document.getElementById('edit-user-form')?.addEventListener('submit', handleUpdateUser);
    document.getElementById('transfer-leads-form')?.addEventListener('submit', handleTransferLeads);
    document.getElementById('target-form')?.addEventListener('submit', handleCreateTarget);
}

async function handleDashboardCacheClear(event) {
    const buttons = document.querySelectorAll('[data-dashboard-clear-cache]');
    const triggerButton = event?.currentTarget || null;

    buttons.forEach(function(button) {
        button.disabled = true;
        button.dataset.originalLabel = button.dataset.originalLabel || button.textContent.trim();
        button.textContent = 'Clearing...';
    });

    try {
        const response = await apiRequest('/dashboard/clear-cache', {
            method: 'POST'
        });

        showNotification(response?.message || 'Dashboard cache cleared successfully.', 'success');
        initDashboard();
    } catch (error) {
        console.error('Error clearing dashboard cache:', error);
        if (triggerButton) {
            triggerButton.blur();
        }
    } finally {
        buttons.forEach(function(button) {
            button.disabled = false;
            button.textContent = button.dataset.originalLabel || 'Clear Cache';
        });
    }
}

// API Helper Functions
async function apiRequest(url, options = {}) {
    const headers = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...options.headers
    };
    
    // Add CSRF token if available
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    if (csrfToken) {
        headers['X-CSRF-TOKEN'] = csrfToken;
    }
    
    // Add auth token if available
    if (authToken) {
        headers['Authorization'] = `Bearer ${authToken}`;
    }
    
    // Include credentials for session-based auth
    const fetchOptions = {
        ...options,
        headers,
        credentials: 'same-origin'
    };
    
    try {
        const response = await fetch(API_BASE + url, fetchOptions);
        
        if (!response.ok) {
            const error = await response.json().catch(() => ({ message: 'Request failed' }));
            throw new Error(error.message || 'Request failed');
        }
        
        return await response.json();
    } catch (error) {
        console.error('API Error:', error);
        showNotification('Error: ' + error.message, 'danger');
        throw error;
    }
}

function normalizeDataArray(raw) {
    if (Array.isArray(raw)) {
        return raw;
    }

    if (raw && Array.isArray(raw.data)) {
        return raw.data;
    }

    if (raw && Array.isArray(raw.roles)) {
        return raw.roles;
    }

    if (raw && typeof raw === 'object') {
        const values = Object.values(raw);
        if (values.length === 1 && Array.isArray(values[0])) {
            return values[0];
        }
    }

    if (raw && Array.isArray(raw.items)) {
        return raw.items;
    }

    return [];
}

// Load roles for Sales Executive Performance role dropdown
async function loadPerformanceFilterRoles() {
    const select = document.getElementById('perf-role-filter');
    if (!select) return;
    let roles = [];
    try {
        const data = await apiRequest('/dashboard/filter-roles');
        roles = normalizeDataArray(data);
    } catch (e) {
        console.warn('filter-roles failed, trying /roles', e);
        try {
            const data = await apiRequest('/roles');
            const raw = normalizeDataArray(data);
            roles = raw.filter(r => r.slug && !['admin', 'crm'].includes(r.slug));
        } catch (e2) {
            console.error('Error loading performance filter roles:', e2);
        }
    }
    const currentValue = select.value;
    try {
        const options = (roles || []).map(r => {
            const slug = (r.slug != null ? r.slug : '').toString().replace(/"/g, '&quot;');
            const label = (r.name != null ? r.name : r.slug != null ? r.slug : '').toString().replace(/</g, '&lt;').replace(/"/g, '&quot;');
            return `<option value="${slug}">${label}</option>`;
        }).join('');
        select.innerHTML = '<option value="all">All</option>' + options;
    } catch (err) {
        console.error('Error building role options:', err);
    }
    if (currentValue) select.value = currentValue;
}

// Load Telecaller Stats (Sales Executive Performance table)
async function loadTelecallerStats() {
    const container = document.getElementById('telecaller-stats-container');
    if (!container) return;
    
    try {
        const dateRange = document.getElementById('perf-date-range')?.value || document.getElementById('date-range-filter')?.value || 'this_month';
        const roleSlug = document.getElementById('perf-role-filter')?.value || 'all';
        let url = `/dashboard/telecaller-stats?date_range=${encodeURIComponent(dateRange)}&role_slug=${encodeURIComponent(roleSlug)}`;
        if (dateRange === 'custom') {
            const start = document.getElementById('perf-date-start')?.value;
            const end = document.getElementById('perf-date-end')?.value;
            if (start) url += '&start_date=' + encodeURIComponent(start);
            if (end) url += '&end_date=' + encodeURIComponent(end);
        }
        const data = await apiRequest(url);
        
        if (!data || data.length === 0) {
            container.innerHTML = '<p class="text-muted text-center py-4">No sales executives found.</p>';
            return;
        }
        
        function cell(val) {
            if (val === undefined || val === null || val === '') return '0';
            const n = Number(val);
            return isNaN(n) ? '0' : n;
        }
        
        const thead = `
            <thead><tr>
                <th>User</th>
                <th>assigned</th>
                <th>Follow up</th>
                <th>Meeting</th>
                <th>Visit</th>
                <th>Closer</th>
                <th>Junk</th>
                <th>Not Interested</th>
            </tr></thead>`;
        const tbody = '<tbody>' + data.map(tc => `
            <tr>
                <td>${tc.telecaller_name || tc.username || 'Unknown'}</td>
                <td>${cell(tc.assigned)}</td>
                <td>${cell(tc.follow_up)}</td>
                <td>${cell(tc.meetings)}</td>
                <td>${cell(tc.visits)}</td>
                <td>${cell(tc.closer)}</td>
                <td>${cell(tc.junk)}</td>
                <td>${cell(tc.not_interested)}</td>
            </tr>
        `).join('') + '</tbody>';
        
        container.innerHTML = '<div class="table-responsive crm-perf-table-wrap"><table class="table table-bordered table-hover mb-0 crm-perf-table">' + thead + tbody + '</table></div>';
    } catch (error) {
        console.error('Error loading telecaller stats:', error);
        container.innerHTML = '<p class="text-danger text-center py-4">Error: ' + (error.message || 'Failed to load sales executive stats') + '</p>';
    }
}

// Leads Pending Response (no call outcome yet)
function formatAssignedAtPending(isoString, nowIso) {
    if (!isoString) return '—';
    const d = new Date(isoString);
    if (isNaN(d.getTime())) return isoString;
    const now = nowIso ? new Date(nowIso) : new Date();
    const diffMs = now - d;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);
    if (diffMins < 60) return diffMins <= 1 ? '1m ago' : diffMins + 'm ago';
    if (diffHours < 24) return diffHours === 1 ? '1h ago' : diffHours + 'h ago';
    if (diffDays < 7) return diffDays === 1 ? '1d ago' : diffDays + 'd ago';
    return d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function formatAssignedAtFullPending(isoString) {
    if (!isoString) return '—';
    const d = new Date(isoString);
    if (isNaN(d.getTime())) return isoString;
    return d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function maskPhonePending(phone) {
    if (!phone || typeof phone !== 'string') return '—';
    const digits = phone.replace(/\D/g, '');
    if (digits.length < 4) return '****';
    return digits.slice(0, 2) + '****' + digits.slice(-4);
}

function escapeHtmlPending(text) {
    if (text == null) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function toggleLeadsPendingDetail(detailId, rowId) {
    const detailRow = document.getElementById(detailId);
    const chevron = document.getElementById('chevron-' + rowId);
    if (!detailRow || !chevron) return;
    const isHidden = detailRow.style.display === 'none' || detailRow.style.display === '';
    detailRow.style.display = isHidden ? 'block' : 'none';
    chevron.className = isHidden ? 'fas fa-chevron-down' : 'fas fa-chevron-right';
}

function formatAvgResponseTime(avgResponseMinutes) {
    if (avgResponseMinutes == null || avgResponseMinutes === 0 || isNaN(avgResponseMinutes)) return '0 min';
    var m = Math.round(Number(avgResponseMinutes));
    if (m < 60) return m + ' min';
    var h = Math.floor(m / 60);
    var min = m % 60;
    return min > 0 ? (h + 'h ' + min + 'm') : (h + 'h');
}

function openFilteredLeadsForUser(userId) {
    if (!userId) return;
    var url = new URL(window.location.origin + '/leads');
    url.searchParams.set('assigned_to', String(userId));
    url.searchParams.set('status', 'new');
    url.searchParams.set('view', 'list');
    window.location.assign(url.toString());
}

function openLeadDetailPage(leadId) {
    if (!leadId) return;
    window.location.assign(window.location.origin + '/leads/' + leadId);
}

function renderAverageResponseTime(list) {
    var panel = document.getElementById('average-response-time-panel');
    if (!panel) return;
    var html = '<table class="table table-sm table-bordered mb-0"><thead><tr><th class="text-start">User Name</th><th class="text-end">Avg Time</th></tr></thead><tbody>';
    if (!list || list.length === 0) {
        html += '<tr><td colspan="2" class="text-center text-muted py-2">No users in this role.</td></tr>';
    } else {
        list.forEach(function(row) {
            var name = escapeHtmlPending(row.user_name || '');
            var timeStr = formatAvgResponseTime(row.avg_response_minutes);
            html += '<tr><td>' + name + '</td><td class="text-end fw-semibold">' + timeStr + '</td></tr>';
        });
    }
    html += '</tbody></table>';
    panel.innerHTML = html;
}

function getLeadsAllocatedDateParams() {
    var rangeEl = document.getElementById('leads-allocated-date-range') || document.getElementById('perf-date-range');
    var startEl = document.getElementById('leads-allocated-date-start') || document.getElementById('perf-date-start');
    var endEl = document.getElementById('leads-allocated-date-end') || document.getElementById('perf-date-end');
    return {
        dateRange: rangeEl?.value || 'this_month',
        start: startEl?.value,
        end: endEl?.value
    };
}

function getSourceDateParams() {
    var rangeEl = document.getElementById('source-date-range');
    var startEl = document.getElementById('source-date-start');
    var endEl = document.getElementById('source-date-end');

    return {
        dateRange: rangeEl?.value || 'this_month',
        start: startEl?.value,
        end: endEl?.value
    };
}

async function loadRecentLeads() {
    const tbody = document.getElementById('recent-leads-tbody');
    if (!tbody) return;

    try {
        const params = getLeadsAllocatedDateParams();
        let url = '/dashboard/recent-leads?date_range=' + encodeURIComponent(params.dateRange);
        if (params.dateRange === 'custom') {
            if (params.start) url += '&start_date=' + encodeURIComponent(params.start);
            if (params.end) url += '&end_date=' + encodeURIComponent(params.end);
        }

        const raw = await apiRequest(url);
        const data = normalizeDataArray(raw);

        if (!data || data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">No recent leads found.</td></tr>';
            return;
        }

        tbody.innerHTML = data.map(function(row) {
            const leadId = Number(row.lead_id || 0);
            const detailHref = '/leads/' + leadId;
            return `
                <tr class="crm-recent-click-row" onclick="openLeadDetailPage(${leadId})">
                    <td class="text-start fw-semibold"><a href="${detailHref}" class="crm-click-cell text-start" onclick="event.preventDefault(); openLeadDetailPage(${leadId})">${escapeHtmlPending(row.lead_name || 'Untitled Lead')}</a></td>
                    <td class="text-start"><a href="${detailHref}" class="crm-click-cell text-start" onclick="event.preventDefault(); openLeadDetailPage(${leadId})">${escapeHtmlPending(row.owner_name || 'Unassigned')}</a></td>
                    <td class="text-center"><a href="${detailHref}" class="crm-click-cell text-center" onclick="event.preventDefault(); openLeadDetailPage(${leadId})">${escapeHtmlPending(row.status_label || row.status || 'Unknown')}</a></td>
                    <td class="text-start"><a href="${detailHref}" class="crm-click-cell text-start" onclick="event.preventDefault(); openLeadDetailPage(${leadId})">${escapeHtmlPending(row.outcome_label || row.outcome || 'No Outcome')}</a></td>
                </tr>
            `;
        }).join('');
    } catch (error) {
        console.error('Error loading recent leads:', error);
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger py-4">Error loading recent leads.</td></tr>';
    }
}

async function loadFreshLeadCnpSummary() {
    const tbody = document.getElementById('fresh-lead-cnp-tbody');
    if (!tbody) return;

    try {
        const params = getLeadsAllocatedDateParams();
        const roleFilter = document.getElementById('perf-role-filter');
        let url = '/dashboard/fresh-lead-cnp-summary?date_range=' + encodeURIComponent(params.dateRange);

        if (roleFilter?.value) {
            url += '&role_slug=' + encodeURIComponent(roleFilter.value);
        }

        if (params.dateRange === 'custom') {
            if (params.start) url += '&start_date=' + encodeURIComponent(params.start);
            if (params.end) url += '&end_date=' + encodeURIComponent(params.end);
        }

        const raw = await apiRequest(url);
        const data = normalizeDataArray(raw);

        if (!data || data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">No fresh lead CNP data found for this period.</td></tr>';
            return;
        }

        const leadShowBase = window.location.origin + '/leads';
        let html = '';

        data.forEach(function(row) {
            const leads = row.leads || [];
            const rowId = 'cnp-row-' + row.user_id;
            const detailId = 'cnp-detail-' + row.user_id;

            html += '<tr class="leads-pending-user-row" data-user-id="' + row.user_id + '">' +
                '<td><button type="button" class="btn btn-link p-0 text-decoration-none" onclick="event.stopPropagation(); toggleLeadsPendingDetail(\'' + detailId + '\', \'' + rowId + '\')"><i class="fas fa-chevron-right" id="chevron-' + rowId + '"></i></button></td>' +
                '<td class="text-start fw-semibold">' + escapeHtmlPending(row.user_name || 'Unknown') + '</td>' +
                '<td class="text-center">' + (row.cnp_total || 0) + '</td>' +
                '<td class="text-start">' + formatAssignedAtFullPending(row.last_cnp_at) + '</td>' +
                '</tr>' +
                '<tr id="' + detailId + '" class="leads-pending-detail-row" style="display: none;">' +
                '<td colspan="4" style="padding: 0; background: #f8f9fa;">' +
                '<div class="crm-detail-panel">' +
                (leads.length === 0
                    ? '<div class="text-center text-muted py-2">No fresh lead CNP data found for this user.</div>'
                    : '<div class="crm-detail-grid crm-detail-head crm-cnp-detail-head"><div>Lead</div><div>CNP No.</div><div>Last CNP</div><div>Current Owner</div><div>Open</div></div>' +
                      leads.map(function(lead) {
                          return '<div class="crm-detail-grid crm-detail-item crm-cnp-detail-item">' +
                              '<div><div class="crm-detail-lead-name">' + escapeHtmlPending(lead.lead_name || '-') + '</div><div class="crm-detail-lead-phone">' + maskPhonePending(lead.phone) + '</div></div>' +
                              '<div>' + escapeHtmlPending(String(lead.cnp_number || 0)) + '</div>' +
                              '<div>' + formatAssignedAtFullPending(lead.last_cnp_at) + '</div>' +
                              '<div>' + escapeHtmlPending(lead.current_owner_name || 'Unassigned') + '</div>' +
                              '<div><a href="' + leadShowBase + '/' + lead.lead_id + '" class="crm-detail-link">View</a></div>' +
                          '</div>';
                      }).join('')) +
                '</div></td></tr>';
        });

        tbody.innerHTML = html;
    } catch (error) {
        console.error('Error loading fresh lead CNP summary:', error);
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger py-4">Error loading fresh lead CNP data.</td></tr>';
    }
}

async function loadLeadOperationsSummary() {
    const tbody = document.getElementById('lead-operations-summary-tbody');
    if (!tbody) return;

    try {
        var params = getLeadsAllocatedDateParams();
        let url = '/dashboard/lead-operations-summary?date_range=' + encodeURIComponent(params.dateRange);
        if (params.dateRange === 'custom') {
            if (params.start) url += '&start_date=' + encodeURIComponent(params.start);
            if (params.end) url += '&end_date=' + encodeURIComponent(params.end);
        }

        const raw = await apiRequest(url);
        const serverNow = raw && raw.server_now ? raw.server_now : null;
        const data = normalizeDataArray(raw);

        if (!data || data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">No new leads pending completion.</td></tr>';
            return;
        }

        const leadShowBase = window.location.origin + '/leads';
        let html = '';

        data.forEach(function(row) {
            const leads = row.leads || [];
            const rowId = 'ops-row-' + row.user_id;
            const detailId = 'ops-detail-' + row.user_id;
            const rowHref = '/leads?assigned_to=' + row.user_id + '&status=new&view=list';

            html += '<tr class="leads-pending-user-row crm-click-row" data-user-id="' + row.user_id + '">' +
                '<td><button type="button" class="btn btn-link p-0 text-decoration-none" onclick="event.stopPropagation(); toggleLeadsPendingDetail(\'' + detailId + '\', \'' + rowId + '\')"><i class="fas fa-chevron-right" id="chevron-' + rowId + '"></i></button></td>' +
                '<td class="text-start"><a class="crm-click-cell text-start" href="' + rowHref + '" onclick="event.preventDefault(); openFilteredLeadsForUser(' + row.user_id + ')">' + escapeHtmlPending(row.user_name || '') + '</a></td>' +
                '<td class="text-center"><a class="crm-click-cell text-center" href="' + rowHref + '" onclick="event.preventDefault(); openFilteredLeadsForUser(' + row.user_id + ')">' + (row.pending_new_count || 0) + '</a></td>' +
                '<td class="text-end fw-semibold"><a class="crm-click-cell text-end" href="' + rowHref + '" onclick="event.preventDefault(); openFilteredLeadsForUser(' + row.user_id + ')">' + formatAvgResponseTime(row.avg_response_minutes) + '</a></td>' +
                '</tr>' +
                '<tr id="' + detailId + '" class="leads-pending-detail-row" style="display: none;">' +
                '<td colspan="4" style="padding: 0; background: #f8f9fa;">' +
                '<div class="crm-detail-panel">' +
                (leads.length === 0
                    ? '<div class="text-center text-muted py-2">No new leads pending completion.</div>'
                    : '<div class="crm-detail-grid crm-detail-head"><div>Lead</div><div>Assigned At</div><div>Open</div></div>' +
                      leads.map(function(lead) {
                          return '<div class="crm-detail-grid crm-detail-item">' +
                              '<div><div class="crm-detail-lead-name">' + escapeHtmlPending(lead.name || '-') + '</div><div class="crm-detail-lead-phone">' + maskPhonePending(lead.phone) + '</div></div>' +
                              '<div>' + formatAssignedAtFullPending(lead.assigned_at) + '</div>' +
                              '<div><a href="' + leadShowBase + '/' + lead.lead_id + '" class="crm-detail-link">View</a></div>' +
                          '</div>';
                      }).join('')) +
                '</div></td></tr>';
        });

        tbody.innerHTML = html;
    } catch (error) {
        console.error('Error loading lead operations summary:', error);
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger py-4">Error loading data.</td></tr>';
    }
}

async function loadPreviousOverPod() {
    const tbody = document.getElementById('previous-over-pod-tbody');
    const totalUsers = document.getElementById('previous-over-pod-total-users');
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-5">Loading...</td></tr>';
    if (totalUsers) {
        totalUsers.textContent = 'Total Users: 0';
    }

    try {
        const raw = await apiRequest('/dashboard/previous-over-pod');
        previousOverPodRows = normalizeDataArray(raw);
        renderPreviousOverPodTable();
    } catch (error) {
        console.error('Error loading previous over POD:', error);
        previousOverPodRows = [];
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger py-4">Error loading previous over POD.</td></tr>';
    }
}

function renderPreviousOverPodTable() {
    const tbody = document.getElementById('previous-over-pod-tbody');
    const searchInput = document.getElementById('previous-over-pod-search');
    const totalUsers = document.getElementById('previous-over-pod-total-users');
    if (!tbody) return;

    const searchTerm = (searchInput?.value || '').trim().toLowerCase();
    const rows = previousOverPodRows.filter(function(row) {
        if (!searchTerm) return true;
        return (row.user_name || '').toLowerCase().includes(searchTerm);
    });

    if (totalUsers) {
        totalUsers.textContent = 'Total Users: ' + rows.length;
    }

    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">No previous over POD found.</td></tr>';
        return;
    }

    const leadShowBase = window.location.origin + '/leads';
    let html = '';

    rows.forEach(function(row) {
        const items = row.items || [];
        const rowId = 'previous-pod-row-' + row.user_id;
        const detailId = 'previous-pod-detail-' + row.user_id;

        html += '<tr class="leads-pending-user-row" data-user-id="' + row.user_id + '" style="cursor: pointer;" onclick="toggleLeadsPendingDetail(\'' + detailId + '\', \'' + rowId + '\')">' +
            '<td><i class="fas fa-chevron-right" id="chevron-' + rowId + '"></i></td>' +
            '<td>' + escapeHtmlPending(row.user_name || '') + '</td>' +
            '<td class="text-center"><span class="crm-pod-count">' + (row.previous_over_pod_count || 0) + '</span></td>' +
            '<td>' + formatAssignedAtFullPending(row.oldest_pending_at) + '</td>' +
            '</tr>' +
            '<tr id="' + detailId + '" class="leads-pending-detail-row" style="display: none;">' +
            '<td colspan="4" style="padding: 0; background: #f8f9fa;">' +
            '<div class="crm-detail-panel">' +
            (items.length === 0
                ? '<div class="text-center text-muted py-2">No previous over POD items.</div>'
                : '<div class="crm-detail-grid crm-detail-head"><div>Lead / Task Name</div><div>Assigned At</div><div>Open</div></div>' +
                  items.map(function(item) {
                      return '<div class="crm-detail-grid crm-detail-item">' +
                          '<div><div class="crm-detail-lead-name">' + escapeHtmlPending(item.name || '-') + '</div><div class="crm-detail-lead-phone">' + maskPhonePending(item.phone) + '</div></div>' +
                          '<div>' + formatAssignedAtFullPending(item.assigned_at) + '</div>' +
                          '<div><a href="' + leadShowBase + '/' + item.lead_id + '" class="crm-detail-link">View</a></div>' +
                      '</div>';
                  }).join('')) +
            '</div></td></tr>';
    });

    tbody.innerHTML = html;
}

async function loadAverageResponseTime() {
    var panel = document.getElementById('average-response-time-panel');
    if (!panel) return;
    try {
        var params = getLeadsAllocatedDateParams();
        var url = '/dashboard/average-response-time?date_range=' + encodeURIComponent(params.dateRange);
        if (params.dateRange === 'custom') {
            if (params.start) url += '&start_date=' + encodeURIComponent(params.start);
            if (params.end) url += '&end_date=' + encodeURIComponent(params.end);
        }
        var raw = await apiRequest(url);
        var data = Array.isArray(raw) ? raw : (raw && raw.data ? raw.data : []);
        renderAverageResponseTime(data);
    } catch (error) {
        console.error('Error loading average response time:', error);
        panel.innerHTML = '<p class="text-muted small mb-0">Error loading data.</p>';
    }
}

async function loadLeadsPendingResponse() {
    const tbody = document.getElementById('leads-pending-response-tbody');
    if (!tbody) return;
    try {
        var params = getLeadsAllocatedDateParams();
        let url = '/dashboard/leads-pending-response?date_range=' + encodeURIComponent(params.dateRange);
        if (params.dateRange === 'custom') {
            if (params.start) url += '&start_date=' + encodeURIComponent(params.start);
            if (params.end) url += '&end_date=' + encodeURIComponent(params.end);
        }
        const raw = await apiRequest(url);
        const serverNow = raw && raw.server_now ? raw.server_now : null;
        const data = normalizeDataArray(raw);
        if (!data || data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">No leads pending response.</td></tr>';
            return;
        }
        const leadShowBase = window.location.origin + '/leads';
        let html = '';
        data.forEach(function(row) {
            const leads = row.leads || [];
            const oldestAssignedAt = leads.length > 0
                ? leads.reduce(function(min, l) { return (!l.assigned_at ? min : (!min || l.assigned_at < min ? l.assigned_at : min)); }, null)
                : null;
            const oldestAssign = oldestAssignedAt ? formatAssignedAtPending(oldestAssignedAt, serverNow) : '—';
            const rowId = 'pending-row-' + row.user_id;
            const detailId = 'pending-detail-' + row.user_id;
            html += '<tr class="leads-pending-user-row" data-user-id="' + row.user_id + '" style="cursor: pointer;" onclick="toggleLeadsPendingDetail(\'' + detailId + '\', \'' + rowId + '\')">' +
                '<td><i class="fas fa-chevron-right" id="chevron-' + rowId + '"></i></td>' +
                '<td>' + escapeHtmlPending(row.user_name || '') + '</td>' +
                '<td class="text-center">' + (row.pending_count || 0) + '</td>' +
                '<td>' + oldestAssign + '</td>' +
                '</tr>' +
                '<tr id="' + detailId + '" class="leads-pending-detail-row" style="display: none;">' +
                '<td colspan="4" style="padding: 0; background: #f8f9fa;">' +
                '<div class="p-3 ps-5">' +
                '<table class="table table-sm table-bordered mb-0">' +
                '<thead><tr><th>Lead Name</th><th>Phone</th><th>Assigned At</th><th></th></tr></thead>' +
                '<tbody>' +
                (leads.length === 0 ? '<tr><td colspan="4" class="text-center text-muted py-2">No pending leads.</td></tr>' : leads.map(function(lead) {
                    return '<tr><td>' + escapeHtmlPending(lead.name || '—') + '</td><td>' + maskPhonePending(lead.phone) + '</td><td>' + formatAssignedAtFullPending(lead.assigned_at) + '</td><td><a href="' + leadShowBase + '/' + lead.lead_id + '" class="text-primary small">View</a></td></tr>';
                }).join('')) +
                '</tbody></table></div></td></tr>';
        });
        tbody.innerHTML = html;
    } catch (error) {
        console.error('Error loading leads pending response:', error);
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger py-4">Error loading data.</td></tr>';
    }
}

async function loadNewLeadsNotCompleted() {
    const tbody = document.getElementById('new-leads-not-completed-tbody');
    if (!tbody) return;

    try {
        var params = getLeadsAllocatedDateParams();
        let url = '/dashboard/new-leads-not-completed?date_range=' + encodeURIComponent(params.dateRange);
        if (params.dateRange === 'custom') {
            if (params.start) url += '&start_date=' + encodeURIComponent(params.start);
            if (params.end) url += '&end_date=' + encodeURIComponent(params.end);
        }

        const raw = await apiRequest(url);
        const serverNow = raw && raw.server_now ? raw.server_now : null;
        const data = Array.isArray(raw) ? raw : (raw && raw.data ? raw.data : []);

        if (!data || data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">No new leads pending completion.</td></tr>';
            return;
        }

        const leadShowBase = window.location.origin + '/leads';
        let html = '';

        data.forEach(function(row) {
            const leads = row.leads || [];
            const oldestAssign = row.oldest_assigned_at ? formatAssignedAtPending(row.oldest_assigned_at, serverNow) : '—';
            const rowId = 'new-pending-row-' + row.user_id;
            const detailId = 'new-pending-detail-' + row.user_id;

            html += '<tr class="leads-pending-user-row" data-user-id="' + row.user_id + '" style="cursor: pointer;" onclick="toggleLeadsPendingDetail(\'' + detailId + '\', \'' + rowId + '\')">' +
                '<td><i class="fas fa-chevron-right" id="chevron-' + rowId + '"></i></td>' +
                '<td>' + escapeHtmlPending(row.user_name || '') + '</td>' +
                '<td class="text-center">' + (row.pending_new_count || 0) + '</td>' +
                '<td>' + oldestAssign + '</td>' +
                '</tr>' +
                '<tr id="' + detailId + '" class="leads-pending-detail-row" style="display: none;">' +
                '<td colspan="4" style="padding: 0; background: #f8f9fa;">' +
                '<div class="p-3 ps-5">' +
                '<table class="table table-sm table-bordered mb-0">' +
                '<thead><tr><th>Lead Name</th><th>Phone</th><th>Assigned At</th><th></th></tr></thead>' +
                '<tbody>' +
                (leads.length === 0 ? '<tr><td colspan="4" class="text-center text-muted py-2">No new leads pending completion.</td></tr>' : leads.map(function(lead) {
                    return '<tr><td>' + escapeHtmlPending(lead.name || '—') + '</td><td>' + maskPhonePending(lead.phone) + '</td><td>' + formatAssignedAtFullPending(lead.assigned_at) + '</td><td><a href="' + leadShowBase + '/' + lead.lead_id + '" class="text-primary small">View</a></td></tr>';
                }).join('')) +
                '</tbody></table></div></td></tr>';
        });

        tbody.innerHTML = html;
    } catch (error) {
        console.error('Error loading new leads not completed:', error);
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger py-4">Unable to load new leads right now.</td></tr>';
    }
}

// Load Daily Prospects
let currentProspectPage = 1;
let currentViewMode = localStorage.getItem('prospect-view-mode') || 'card';

async function loadDailyProspects(page = 1) {
    try {
        currentProspectPage = page;
        const dateRange = document.getElementById('prospects-date-range')?.value || 'this_month';
        const userId = document.getElementById('prospects-user-filter')?.value || 'all';
        const verificationStatus = document.getElementById('prospects-verification-filter')?.value || 'all';
        const perPage = document.getElementById('prospects-per-page')?.value || 50;
        
        const params = new URLSearchParams({
            date_range: dateRange,
            user_id: userId,
            verification_status: verificationStatus,
            page: page,
            per_page: perPage
        });
        
        const data = await apiRequest(`/dashboard/daily-prospects?${params}`);
        
        // Update stats
        updateProspectStats(data.stats);
        
        // Render prospects
        renderProspects(data.data, currentViewMode);
        
        // Render pagination
        renderPagination(data.pagination);
        
        // Update user filter dropdown
        updateUserFilterDropdown(data.stats_by_user);
    } catch (error) {
        console.error('Error loading prospects:', error);
    }
}

function updateProspectStats(stats) {
    const container = document.getElementById('prospect-stats-container');
    if (!container) return;
    
    container.innerHTML = `
        <div class="col-md-3 mb-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2">Total Prospects</h6>
                    <h2 class="card-title">${stats.total || 0}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2">Pending Verification</h6>
                    <h2 class="card-title">${stats.pending_verification || 0}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2">Verified</h6>
                    <h2 class="card-title">${stats.verified || 0}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2">Rejected</h6>
                    <h2 class="card-title">${stats.rejected || 0}</h2>
                </div>
            </div>
        </div>
    `;
}

function renderProspects(prospects, viewMode) {
    const container = document.getElementById('prospects-container');
    if (!container) return;
    
    if (prospects.length === 0) {
        container.innerHTML = '<p class="text-muted text-center py-4">No prospects found.</p>';
        return;
    }
    
    if (viewMode === 'card') {
        container.innerHTML = prospects.map(p => `
            <div class="prospect-card">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h6 class="mb-0 fw-bold">${p.customer_name}</h6>
                    <span class="badge ${getVerificationBadgeClass(p.verification_status)}">${p.verification_status}</span>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6"><small class="text-muted">Phone:</small> ${p.phone}</div>
                    <div class="col-6"><small class="text-muted">Budget:</small> ${p.budget ? formatCurrency(p.budget) : 'N/A'}</div>
                    <div class="col-6"><small class="text-muted">Location:</small> ${p.preferred_location || 'N/A'}</div>
                    <div class="col-6"><small class="text-muted">Purpose:</small> ${p.purpose || 'N/A'}</div>
                    <div class="col-6"><small class="text-muted">Date:</small> ${formatDate(p.created_at)}</div>
                    ${p.response_time ? `<div class="col-6"><small class="text-muted">Response Time:</small> ${p.response_time}</div>` : ''}
                </div>
                <div class="mt-2">
                    <span class="badge bg-secondary">Created By: ${p.created_by_name || 'Unknown'}</span>
                </div>
                <div class="mt-2">
                    <button class="btn btn-sm btn-primary" onclick="showProspectDetails(${p.id})">
                        View Details
                    </button>
                </div>
            </div>
        `).join('');
    } else {
        container.innerHTML = `
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Customer Name</th>
                            <th>Phone</th>
                            <th>Budget</th>
                            <th>Location</th>
                            <th>Purpose</th>
                            <th>Created By</th>
                            <th>Verification Status</th>
                            <th>Response Time</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${prospects.map(p => `
                            <tr>
                                <td>${formatDate(p.created_at)}</td>
                                <td>${p.customer_name}</td>
                                <td>${p.phone}</td>
                                <td>${p.budget ? formatCurrency(p.budget) : 'N/A'}</td>
                                <td>${p.preferred_location || 'N/A'}</td>
                                <td>${p.purpose || 'N/A'}</td>
                                <td>${p.created_by_name || 'Unknown'}</td>
                                <td><span class="badge ${getVerificationBadgeClass(p.verification_status)}">${p.verification_status}</span></td>
                                <td>${p.response_time || 'N/A'}</td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="showProspectDetails(${p.id})">
                                        View Details
                                    </button>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }
}

function renderPagination(pagination) {
    const container = document.getElementById('prospects-pagination');
    if (!container || !pagination) return;
    
    if (pagination.last_page <= 1) {
        container.innerHTML = '';
        return;
    }
    
    let html = '<ul class="pagination justify-content-center">';
    
    // Previous
    html += `<li class="page-item ${pagination.current_page === 1 ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="loadDailyProspects(${pagination.current_page - 1}); return false;">Previous</a>
    </li>`;
    
    // Page numbers
    for (let i = 1; i <= pagination.last_page; i++) {
        if (i === 1 || i === pagination.last_page || (i >= pagination.current_page - 2 && i <= pagination.current_page + 2)) {
            html += `<li class="page-item ${i === pagination.current_page ? 'active' : ''}">
                <a class="page-link" href="#" onclick="loadDailyProspects(${i}); return false;">${i}</a>
            </li>`;
        } else if (i === pagination.current_page - 3 || i === pagination.current_page + 3) {
            html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }
    
    // Next
    html += `<li class="page-item ${pagination.current_page === pagination.last_page ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="loadDailyProspects(${pagination.current_page + 1}); return false;">Next</a>
    </li>`;
    
    html += '</ul>';
    html += `<div class="text-center mt-2"><small class="text-muted">Showing ${pagination.from} to ${pagination.to} of ${pagination.total} records</small></div>`;
    
    container.innerHTML = html;
}

// Toggle View Mode
function toggleViewMode(mode) {
    currentViewMode = mode;
    localStorage.setItem('prospect-view-mode', mode);
    
    // Update button states
    document.querySelectorAll('.view-toggle-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.dataset.view === mode) {
            btn.classList.add('active');
        }
    });
    
    // Reload prospects with new view mode
    loadDailyProspects(currentProspectPage);
}

// Handle Stat Card Click
function handleStatCardClick(filter) {
    // Switch to prospects tab
    const prospectsTab = document.getElementById('prospects-tab');
    if (prospectsTab) {
        prospectsTab.click();
    }
    
    // Set filter
    const verificationFilter = document.getElementById('prospects-verification-filter');
    if (verificationFilter) {
        if (filter === 'all') {
            verificationFilter.value = 'all';
        } else if (filter === 'called') {
            // Filter by called status - this would need backend support
            verificationFilter.value = 'all';
        } else if (filter === 'called_not_interested') {
            verificationFilter.value = 'rejected'; // Approximate mapping
        } else if (filter === 'called_interested') {
            verificationFilter.value = 'verified'; // Approximate mapping
        }
    }
    
    // Reload prospects
    loadDailyProspects(1);
}

// Filter by Telecaller
function filterProspectsByTelecaller(telecallerId) {
    // Switch to prospects tab
    const prospectsTab = document.getElementById('prospects-tab');
    if (prospectsTab) {
        prospectsTab.click();
    }
    
    // Set user filter
    const userFilter = document.getElementById('prospects-user-filter');
    if (userFilter) {
        userFilter.value = telecallerId;
        loadDailyProspects(1);
    }
}

// Load Users
async function loadUsers() {
    try {
        const data = await apiRequest('/users');
        
        // Populate dropdowns
        updateTelecallerDropdowns(data.filter(u => u.role?.slug === 'sales_executive'));
        updateUserDropdowns(data);
        
        // Load roles for user management
        loadRoles();
    } catch (error) {
        console.error('Error loading users:', error);
    }
}

// Load Roles
async function loadRoles() {
    try {
        const raw = await apiRequest('/roles');
        const roles = normalizeDataArray(raw);
        
        // Update role dropdowns
        const userRoleSelect = document.getElementById('user-role');
        const editUserRoleSelect = document.getElementById('edit-user-role');
        
        if (userRoleSelect) {
            userRoleSelect.innerHTML = '<option value="">Select Role...</option>' + 
                roles.map(r => `<option value="${r.id}">${r.name}</option>`).join('');
        }
        
        if (editUserRoleSelect) {
            editUserRoleSelect.innerHTML = '<option value="">Select Role...</option>' + 
                roles.map(r => `<option value="${r.id}">${r.name}</option>`).join('');
        }
    } catch (error) {
        console.error('Error loading roles:', error);
    }
}

function updateTelecallerDropdowns(telecallers) {
    const selects = ['csv-telecaller-select', 'add-lead-assign-to', 'transfer-from-telecaller', 'transfer-to-telecaller'];
    selects.forEach(selectId => {
        const select = document.getElementById(selectId);
        if (select) {
            const currentValue = select.value;
            select.innerHTML = '<option value="">Select Sales Executive...</option>' + 
                telecallers.map(t => `<option value="${t.id}">${t.name}</option>`).join('');
            select.value = currentValue;
        }
    });
}

function updateUserDropdowns(users) {
    // User management dropdown
    const targetUserSelect = document.getElementById('target-user-select');
    if (targetUserSelect) {
        targetUserSelect.innerHTML = '<option value="">Select User...</option>' + 
            users.map(u => `<option value="${u.id}">${u.name} (${u.role?.name || 'N/A'})</option>`).join('');
    }
}

function updateUserFilterDropdown(statsByUser) {
    const select = document.getElementById('prospects-user-filter');
    if (select) {
        const currentValue = select.value;
        select.innerHTML = '<option value="all">All Users</option>' + 
            statsByUser.map(u => `<option value="${u.user_id}">${u.username} (${u.count})</option>`).join('');
        select.value = currentValue;
    }
}

// User Management Modal
function showUserManagementModal() {
    const modal = new bootstrap.Modal(document.getElementById('userManagementModal'));
    modal.show();
    loadUsersList();
}

async function loadUsersList() {
    try {
        const data = await apiRequest('/users');
        const container = document.getElementById('users-list-container');
        if (!container) return;
        
        container.innerHTML = `
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Manager</th>
                            <th>Independent</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${data.map(u => `
                            <tr>
                                <td>${u.name}</td>
                                <td>${u.manager?.name || 'N/A'}</td>
                                <td>${u.independent ? 'Yes' : 'No'}</td>
                                <td>${formatDate(u.created_at)}</td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="editUser(${u.id})">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteUser(${u.id})">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    } catch (error) {
        console.error('Error loading users list:', error);
    }
}

function showCreateUserForm() {
    document.getElementById('create-user-form-container').classList.remove('d-none');
}

function cancelCreateUser() {
    document.getElementById('create-user-form-container').classList.add('d-none');
    document.getElementById('create-user-form').reset();
}

async function handleCreateUser(e) {
    e.preventDefault();
    try {
        const roleId = document.getElementById('user-role').value;
        const roleSelect = document.getElementById('user-role');
        const selectedOption = roleSelect.options[roleSelect.selectedIndex];
        const roleName = selectedOption ? selectedOption.text.toLowerCase() : '';
        
        // Client-side validation: Check if CRM user is trying to create Admin or CRM role
        // Note: Backend also validates this, but this provides immediate feedback
        if (roleName.includes('admin') || roleName.includes('crm')) {
            // Check if this is a restricted role by checking the role slug or name
            // Since roles are already filtered by backend, if Admin/CRM appear, it means user is Admin
            // But for safety, we'll let backend handle the validation and show error message
        }
        
        const formData = {
            name: document.getElementById('user-username').value,
            email: document.getElementById('user-email').value,
            password: document.getElementById('user-password').value || '123456',
            role_id: roleId,
            manager_id: document.getElementById('user-manager')?.value || null,
        };
        
        await apiRequest('/users', {
            method: 'POST',
            body: JSON.stringify(formData)
        });
        
        showNotification('User created successfully', 'success');
        cancelCreateUser();
        loadUsersList();
        loadUsers(); // Reload for dropdowns
    } catch (error) {
        console.error('Error creating user:', error);
        const errorMessage = error.message || 'Failed to create user. Please check if you have permission to create this role.';
        showNotification(errorMessage, 'error');
    }
}

async function editUser(userId) {
    // Load user data and show edit modal
    try {
        const data = await apiRequest(`/users/${userId}`);
        document.getElementById('edit-user-id').value = data.id || userId;
        document.getElementById('edit-user-username').value = data.name || '';
        document.getElementById('edit-user-email').value = data.email || '';
        document.getElementById('edit-user-password').value = '';
        document.getElementById('edit-user-role').value = data.role_id || data.role?.id || '';
        document.getElementById('edit-user-manager').value = data.manager_id || '';
        document.getElementById('edit-user-active').checked = data.is_active !== false;
        const modal = new bootstrap.Modal(document.getElementById('userManagementModal'));
        modal.show();
    } catch (err) {
        console.error('Error loading user:', err);
        showNotification('Failed to load user', 'danger');
    }
}

async function handleUpdateUser(e) {
    e.preventDefault();
    const userId = document.getElementById('edit-user-id')?.value;
    if (!userId) return;
    try {
        const formData = {
            name: document.getElementById('edit-user-username')?.value,
            email: document.getElementById('edit-user-email')?.value,
            role_id: document.getElementById('edit-user-role')?.value || null,
            manager_id: document.getElementById('edit-user-manager')?.value || null,
            is_active: document.getElementById('edit-user-active')?.checked ?? true,
        };
        const password = document.getElementById('edit-user-password')?.value;
        if (password && password.trim()) formData.password = password.trim();
        await apiRequest(`/users/${userId}`, {
            method: 'PUT',
            body: JSON.stringify(formData),
        });
        showNotification('User updated successfully', 'success');
        bootstrap.Modal.getInstance(document.getElementById('userManagementModal'))?.hide();
        loadUsersList();
        loadUsers();
    } catch (error) {
        console.error('Error updating user:', error);
        showNotification(error.message || 'Failed to update user', 'danger');
    }
}

async function deleteUser(userId) {
    if (!confirm('Are you sure you want to delete this user?')) return;
    
    try {
        await apiRequest(`/users/${userId}`, { method: 'DELETE' });
        showNotification('User deleted successfully', 'success');
        loadUsersList();
        loadUsers();
    } catch (error) {
        console.error('Error deleting user:', error);
        const errorMessage = error.message || 'Failed to delete user. Only administrators can delete users.';
        showNotification(errorMessage, 'error');
    }
}

// Transfer Leads Modal
function showTransferLeadsModal() {
    const modal = new bootstrap.Modal(document.getElementById('transferLeadsModal'));
    modal.show();
}

async function handleTransferLeads(e) {
    e.preventDefault();
    try {
        const formData = {
            from_telecaller_id: document.getElementById('transfer-from-telecaller').value,
            to_telecaller_id: document.getElementById('transfer-to-telecaller').value,
            transfer_not_interested: document.getElementById('transfer-not-interested').checked,
            transfer_cnp: document.getElementById('transfer-cnp').checked,
            transfer_reason: document.getElementById('transfer-reason').value.trim(),
        };
        if (!formData.transfer_reason) {
            showNotification('Please enter transfer reason', 'error');
            return;
        }
        
        await apiRequest('/transfer-leads', {
            method: 'POST',
            body: JSON.stringify(formData)
        });
        
        showNotification('Leads transferred successfully', 'success');
        bootstrap.Modal.getInstance(document.getElementById('transferLeadsModal')).hide();
        loadTelecallerStats();
    } catch (error) {
        console.error('Error transferring leads:', error);
    }
}

// Prospect Details
async function showProspectDetails(prospectId) {
    const modal = new bootstrap.Modal(document.getElementById('prospectDetailsModal'));
    modal.show();
    
    // Load prospect details
    // This would need an API endpoint to get single prospect
    // For now, we'll show a placeholder
    const container = document.getElementById('prospect-details-content');
    container.innerHTML = '<p class="text-muted">Prospect details loading...</p>';
}

// Blacklist Functions
async function loadBlacklist() {
    try {
        const data = await apiRequest('/blacklist');
        renderBlacklist(data);
    } catch (error) {
        console.error('Error loading blacklist:', error);
    }
}

function renderBlacklist(blacklisted) {
    const container = document.getElementById('blacklist-list-container');
    if (!container) return;
    
    if (blacklisted.length === 0) {
        container.innerHTML = '<p class="text-muted text-center py-4">No blacklisted numbers.</p>';
        return;
    }
    
    container.innerHTML = `
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Phone</th>
                        <th>Reason</th>
                        <th>Blacklisted By</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    ${blacklisted.map(b => `
                        <tr>
                            <td>${b.phone}</td>
                            <td>${b.reason}</td>
                            <td>${b.blacklisted_by?.name || 'Unknown'}</td>
                            <td>${formatDate(b.blacklisted_at)}</td>
                            <td>
                                <button class="btn btn-sm btn-danger" onclick="removeFromBlacklist(${b.id})">
                                    Remove
                                </button>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
}

async function handleAddBlacklist(e) {
    e.preventDefault();
    try {
        const formData = {
            phone: document.getElementById('blacklist-phone').value,
            reason: document.getElementById('blacklist-reason').value,
        };
        
        await apiRequest('/blacklist', {
            method: 'POST',
            body: JSON.stringify(formData)
        });
        
        showNotification('Number added to blacklist', 'success');
        document.getElementById('blacklist-add-form').reset();
        loadBlacklist();
    } catch (error) {
        console.error('Error adding to blacklist:', error);
    }
}

async function removeFromBlacklist(id) {
    if (!confirm('Remove this number from blacklist?')) return;
    
    try {
        await apiRequest(`/blacklist/${id}`, { method: 'DELETE' });
        showNotification('Number removed from blacklist', 'success');
        loadBlacklist();
    } catch (error) {
        console.error('Error removing from blacklist:', error);
    }
}

// Imported Leads
async function loadImportedLeads() {
    // Implementation needed
}

async function assignSelectedLeads() {
    // Implementation needed
}

// CSV Upload
async function handleCsvUpload(e) {
    e.preventDefault();
    // Implementation needed
}

// Add Lead
async function handleAddLead(e) {
    e.preventDefault();
    try {
        const formData = {
            customer_name: document.getElementById('add-lead-name').value,
            phone: document.getElementById('add-lead-phone').value,
            notes: document.getElementById('add-lead-notes').value,
            assigned_to: document.getElementById('add-lead-assign-to').value,
        };
        
        await apiRequest('/add-lead', {
            method: 'POST',
            body: JSON.stringify(formData)
        });
        
        showNotification('Lead added successfully', 'success');
        clearAddLeadForm();
        loadTelecallerStats();
    } catch (error) {
        console.error('Error adding lead:', error);
    }
}

function clearAddLeadForm() {
    document.getElementById('add-lead-form').reset();
}

// Target Management
async function handleCreateTarget(e) {
    e.preventDefault();
    try {
        const formData = {
            user_id: document.getElementById('target-user-select').value,
            target_month: document.getElementById('target-month').value,
            target_visits: document.getElementById('target-visits').value,
            target_meetings: document.getElementById('target-meetings').value,
            target_closers: document.getElementById('target-closers').value,
        };
        
        await apiRequest('/targets', {
            method: 'POST',
            body: JSON.stringify(formData)
        });
        
        showNotification('Target saved successfully', 'success');
        document.getElementById('target-form').reset();
        loadTargets();
    } catch (error) {
        console.error('Error creating target:', error);
    }
}

async function loadTargets() {
    // Implementation needed
}

// Pending Verifications
async function loadPendingVerifications() {
    try {
        const type = document.getElementById('verification-type-filter')?.value || 'all';
        const employeeType = document.getElementById('verification-employee-filter')?.value || 'all';
        
        const params = new URLSearchParams({
            type: type,
            employee_type: employeeType
        });
        
        const data = await apiRequest(`/pending-verifications?${params}`);
        renderPendingVerifications(data.data);
        
        // Update badge
        const badge = document.getElementById('pending-verification-count-badge');
        if (badge) {
            badge.textContent = data.total || 0;
        }
    } catch (error) {
        console.error('Error loading pending verifications:', error);
    }
}

function renderPendingVerifications(prospects) {
    const container = document.getElementById('pending-verifications-container');
    if (!container) return;
    
    if (!prospects || prospects.length === 0) {
        container.innerHTML = '<p class="text-muted text-center py-4">No pending verifications.</p>';
        return;
    }
    
    container.innerHTML = `
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Customer Name</th>
                        <th>Phone</th>
                        <th>Employee</th>
                        <th>Employee Type</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${prospects.map(p => `
                        <tr>
                            <td>${p.type || 'N/A'}</td>
                            <td>${p.customer_name}</td>
                            <td>${p.phone}</td>
                            <td>${p.created_by_name || 'Unknown'}</td>
                            <td>${p.employee_type || 'N/A'}</td>
                            <td>${formatDate(p.created_at)}</td>
                            <td>
                                <button class="btn btn-sm btn-primary" onclick="showProspectDetails(${p.id})">
                                    View
                                </button>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
}

// Utility Functions
function showNotification(message, type = 'success') {
    const alert = document.getElementById('notification-alert');
    const messageEl = document.getElementById('notification-message');
    
    if (alert && messageEl) {
        alert.className = `alert alert-${type} alert-dismissible fade show`;
        messageEl.textContent = message;
    }
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        if (alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }
    }, 5000);
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 0
    }).format(amount);
}

function getVerificationBadgeClass(status) {
    switch(status) {
        case 'verified': return 'badge-verified';
        case 'rejected': return 'badge-rejected';
        case 'pending_verification': return 'badge-pending';
        default: return 'badge bg-secondary';
    }
}

async function handleRoleChange() {
    const roleSelect = document.getElementById('user-role');
    const managerContainer = document.getElementById('user-manager-container');
    const independentContainer = document.getElementById('user-independent-container');
    
    if (!roleSelect.value) {
        managerContainer.style.display = 'none';
        independentContainer.style.display = 'none';
        return;
    }
    
    // Get role slug to determine what fields to show
    try {
        const raw = await apiRequest('/roles');
        const roles = normalizeDataArray(raw);
        const selectedRole = roles.find(r => r.id == roleSelect.value);
        
        if (selectedRole) {
            // Show manager dropdown for sales executive role
            if (selectedRole.slug === 'sales_executive') {
                managerContainer.style.display = 'block';
                independentContainer.style.display = 'none';
            } else if (selectedRole.slug === 'assistant_sales_manager') {
                managerContainer.style.display = 'none';
                independentContainer.style.display = 'block';
            } else {
                managerContainer.style.display = 'none';
                independentContainer.style.display = 'none';
            }
        }
    } catch (error) {
        console.error('Error checking role:', error);
    }
}

function showAddSheetModal() {
    // Implementation needed - redirect to lead import page or show modal
    alert('Add Google Sheet functionality - to be implemented');
}

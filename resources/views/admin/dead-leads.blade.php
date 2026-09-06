@extends('layouts.app')

@section('title', auth()->user()->isCrm() ? 'Other Leads - CRM' : 'Dead Leads - Admin')
@section('page-title', auth()->user()->isCrm() ? 'Other Leads' : 'Dead Leads / Trash')

@push('styles')
<style>
    .dead-lead-card {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        margin-bottom: 16px;
        border-left: 4px solid #ef4444;
    }
    .dead-lead-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 12px;
    }
    .dead-lead-info h3 {
        font-size: 18px;
        font-weight: 600;
        color: #063A1C;
        margin-bottom: 8px;
    }
    .dead-lead-info p {
        color: #6b7280;
        font-size: 14px;
        margin: 4px 0;
    }
    .badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 500;
        margin-right: 8px;
    }
    .badge-dead {
        background: #fee2e2;
        color: #991b1b;
    }
    .badge-stage {
        background: #dbeafe;
        color: #1e40af;
    }
    .tabs {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
        border-bottom: 2px solid #e5e7eb;
    }
    .tab {
        padding: 12px 24px;
        background: none;
        border: none;
        cursor: pointer;
        font-size: 16px;
        font-weight: 500;
        color: #6b7280;
        border-bottom: 2px solid transparent;
        margin-bottom: -2px;
    }
    .tab.active {
        color: #205A44;
        border-bottom-color: #205A44;
    }
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #9ca3af;
    }
    .filters {
        background: white;
        padding: 16px;
        border-radius: 12px;
        margin-bottom: 20px;
        display: flex;
        gap: 16px;
        align-items: center;
    }
    .filters select {
        padding: 8px 12px;
        border: 2px solid #e0e0e0;
        border-radius: 6px;
        font-size: 14px;
    }
</style>
@endpush

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="tabs">
        <button class="tab active" onclick="switchTab('leads')">
            <i class="fas fa-user-times mr-2"></i>{{ auth()->user()->isCrm() ? 'Other Leads' : 'Dead Leads' }}
            <span class="badge badge-dead" id="leadsCount">0</span>
        </button>
        <button class="tab" onclick="switchTab('meetings')">
            <i class="fas fa-handshake mr-2"></i>Dead Meetings
            <span class="badge badge-dead" id="meetingsCount">0</span>
        </button>
        <button class="tab" onclick="switchTab('site-visits')">
            <i class="fas fa-map-marker-alt mr-2"></i>Dead Site Visits
            <span class="badge badge-dead" id="visitsCount">0</span>
        </button>
    </div>

    <div class="filters">
        <label>Stage:</label>
        <select id="stageFilter" onchange="loadDeadItems()">
            <option value="">All Stages</option>
            <option value="meeting">Meeting</option>
            <option value="site_visit">Site Visit</option>
            <option value="closer">Closer</option>
        </select>
    </div>

    <!-- Dead Leads Tab -->
    <div id="leadsTab" class="tab-content">
        <div id="leadsContainer">
            <div class="empty-state">
                <i class="fas fa-spinner fa-spin"></i>
                <p>{{ auth()->user()->isCrm() ? 'Loading other leads...' : 'Loading dead leads...' }}</p>
            </div>
        </div>
    </div>

    <!-- Dead Meetings Tab -->
    <div id="meetingsTab" class="tab-content" style="display: none;">
        <div id="meetingsContainer">
            <div class="empty-state">
                <i class="fas fa-spinner fa-spin"></i>
                <p>Loading dead meetings...</p>
            </div>
        </div>
    </div>

    <!-- Dead Site Visits Tab -->
    <div id="siteVisitsTab" class="tab-content" style="display: none;">
        <div id="siteVisitsContainer">
            <div class="empty-state">
                <i class="fas fa-spinner fa-spin"></i>
                <p>Loading dead site visits...</p>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const API_BASE_URL = '{{ url("/api") }}';
    let currentType = 'leads';
    
    function getToken() {
        // Prioritize token from Blade, then localStorage
        const bladeToken = '{{ $api_token ?? session("api_token") ?? "" }}';
        return bladeToken || localStorage.getItem('admin_token') || '';
    }

    async function apiCall(endpoint, options = {}) {
        const token = getToken();
        if (!token) {
            // Don't redirect immediately, try to continue - token might be in session
            console.warn('No API token found, request may fail');
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': token ? `Bearer ${token}` : '',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
        };

        // Remove empty Authorization header if no token
        if (!token) {
            delete defaultOptions.headers.Authorization;
        }

        try {
            const response = await fetch(`${API_BASE_URL}${endpoint}`, {
                ...defaultOptions,
                ...options,
                headers: { ...defaultOptions.headers, ...options.headers },
                credentials: 'same-origin',
            });

            // Only redirect to login on actual authentication failures (401), not on CSRF (419)
            if (response.status === 401) {
                // Check if it's a true auth failure or just a missing token
                const errorData = await response.json().catch(() => null);
                if (errorData?.message?.includes('Unauthenticated')) {
                    window.location.href = '{{ route("login") }}';
                    return null;
                }
            }

            // Handle CSRF errors (419) - don't logout
            if (response.status === 419) {
                return { 
                    success: false, 
                    message: 'CSRF token mismatch. Please refresh the page and try again.',
                    error: 'csrf_mismatch'
                };
            }

            if (!response.ok) {
                const errorText = await response.text();
                try {
                    const errorJson = JSON.parse(errorText);
                    return { success: false, ...errorJson };
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

    function switchTab(tab) {
        currentType = tab;
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.getElementById('leadsTab').style.display = tab === 'leads' ? 'block' : 'none';
        document.getElementById('meetingsTab').style.display = tab === 'meetings' ? 'block' : 'none';
        document.getElementById('siteVisitsTab').style.display = tab === 'site-visits' ? 'block' : 'none';
        
        event.target.closest('.tab').classList.add('active');
        loadDeadItems();
    }

    async function loadDeadItems() {
        const stage = document.getElementById('stageFilter').value;
        
        if (currentType === 'leads') {
            await loadDeadLeads(stage);
        } else if (currentType === 'meetings') {
            await loadDeadMeetings(stage);
        } else {
            await loadDeadSiteVisits(stage);
        }
    }

    async function loadDeadLeads(stage = '') {
        const container = document.getElementById('leadsContainer');
        container.innerHTML = '<div class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>Loading...</p></div>';

        try {
            let url = '/admin/dead-leads?';
            if (stage) url += `dead_at_stage=${stage}&`;

            const response = await apiCall(url, { method: 'GET' });
            // Handle paginated response
            const leads = response?.data?.data || response?.data || [];

            document.getElementById('leadsCount').textContent = Array.isArray(leads) ? leads.length : 0;

            if (leads.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <h3 style="font-size: 18px; font-weight: 600; color: #333; margin: 16px 0 8px;">{{ auth()->user()->isCrm() ? 'No Other Leads Found' : 'No Dead Leads Found' }}</h3>
                        <p>{{ auth()->user()->isCrm() ? 'No other leads found.' : 'No dead leads in trash.' }}</p>
                    </div>
                `;
                return;
            }

            const html = leads.map(lead => `
                <div class="dead-lead-card">
                    <div class="dead-lead-header">
                        <div class="dead-lead-info">
                            <h3>${lead.name || 'N/A'}</h3>
                            <p><i class="fas fa-phone mr-2"></i>${lead.phone || 'N/A'}</p>
                            <p><i class="fas fa-envelope mr-2"></i>${lead.email || 'N/A'}</p>
                            <p><i class="fas fa-calendar-times mr-2"></i>Marked Dead: ${lead.marked_dead_at ? new Date(lead.marked_dead_at).toLocaleString() : 'N/A'}</p>
                            <p><i class="fas fa-user mr-2"></i>Marked By: ${lead.markedDeadBy?.name || 'N/A'}</p>
                            <p><i class="fas fa-comment mr-2"></i>Reason: ${lead.dead_reason || 'N/A'}</p>
                            <div style="margin-top: 8px;">
                                <span class="badge badge-dead">Dead</span>
                                ${lead.dead_at_stage ? `<span class="badge badge-stage">Stage: ${lead.dead_at_stage}</span>` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');

            container.innerHTML = html;
        } catch (error) {
            console.error('Error loading dead leads:', error);
            container.innerHTML = '<div class="empty-state"><i class="fas fa-exclamation-triangle"></i><p>Error loading dead leads</p></div>';
        }
    }

    async function loadDeadMeetings(stage = '') {
        const container = document.getElementById('meetingsContainer');
        container.innerHTML = '<div class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>Loading...</p></div>';

        try {
            let url = '/admin/dead-meetings?';
            if (stage) url += `stage=${stage}&`;

            const response = await apiCall(url, { method: 'GET' });
            // Handle paginated response  
            const meetings = response?.data?.data || response?.data || [];

            document.getElementById('meetingsCount').textContent = Array.isArray(meetings) ? meetings.length : 0;

            if (meetings.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <h3 style="font-size: 18px; font-weight: 600; color: #333; margin: 16px 0 8px;">No Dead Meetings Found</h3>
                        <p>No dead meetings in trash.</p>
                    </div>
                `;
                return;
            }

            const html = meetings.map(meeting => `
                <div class="dead-lead-card">
                    <div class="dead-lead-header">
                        <div class="dead-lead-info">
                            <h3>${meeting.customer_name || 'N/A'}</h3>
                            <p><i class="fas fa-phone mr-2"></i>${meeting.phone || 'N/A'}</p>
                            <p><i class="fas fa-calendar-times mr-2"></i>Marked Dead: ${meeting.marked_dead_at ? new Date(meeting.marked_dead_at).toLocaleString() : 'N/A'}</p>
                            <p><i class="fas fa-user mr-2"></i>Marked By: ${meeting.markedDeadBy?.name || 'N/A'}</p>
                            <p><i class="fas fa-comment mr-2"></i>Reason: ${meeting.dead_reason || 'N/A'}</p>
                            <div style="margin-top: 8px;">
                                <span class="badge badge-dead">Dead</span>
                                <span class="badge badge-stage">Stage: Meeting</span>
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');

            container.innerHTML = html;
        } catch (error) {
            console.error('Error loading dead meetings:', error);
            container.innerHTML = '<div class="empty-state"><i class="fas fa-exclamation-triangle"></i><p>Error loading dead meetings</p></div>';
        }
    }

    async function loadDeadSiteVisits(stage = '') {
        const container = document.getElementById('siteVisitsContainer');
        container.innerHTML = '<div class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>Loading...</p></div>';

        try {
            let url = '/admin/dead-site-visits?';
            if (stage) url += `stage=${stage}&`;

            const response = await apiCall(url, { method: 'GET' });
            // Handle paginated response
            const visits = response?.data?.data || response?.data || [];

            document.getElementById('visitsCount').textContent = Array.isArray(visits) ? visits.length : 0;

            if (visits.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <h3 style="font-size: 18px; font-weight: 600; color: #333; margin: 16px 0 8px;">No Dead Site Visits Found</h3>
                        <p>No dead site visits in trash.</p>
                    </div>
                `;
                return;
            }

            const html = visits.map(visit => `
                <div class="dead-lead-card">
                    <div class="dead-lead-header">
                        <div class="dead-lead-info">
                            <h3>${visit.customer_name || visit.property_name || 'N/A'}</h3>
                            <p><i class="fas fa-phone mr-2"></i>${visit.phone || 'N/A'}</p>
                            <p><i class="fas fa-calendar-times mr-2"></i>Marked Dead: ${visit.marked_dead_at ? new Date(visit.marked_dead_at).toLocaleString() : 'N/A'}</p>
                            <p><i class="fas fa-user mr-2"></i>Marked By: ${visit.markedDeadBy?.name || 'N/A'}</p>
                            <p><i class="fas fa-comment mr-2"></i>Reason: ${visit.dead_reason || 'N/A'}</p>
                            <div style="margin-top: 8px;">
                                <span class="badge badge-dead">Dead</span>
                                <span class="badge badge-stage">Stage: Site Visit</span>
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');

            container.innerHTML = html;
        } catch (error) {
            console.error('Error loading dead site visits:', error);
            container.innerHTML = '<div class="empty-state"><i class="fas fa-exclamation-triangle"></i><p>Error loading dead site visits</p></div>';
        }
    }

    // Initialize
    (function() {
        loadDeadItems();
    })();
</script>
@endpush

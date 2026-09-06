@extends('layouts.app')

@section('title', 'Dashboard - ' . brand_name())
@section('page-title', 'Dashboard')
@section('page-subtitle', 'Welcome, ' . (auth()->user()->name ?? 'User') . ' (' . (auth()->user()->getDisplayRoleName() ?? 'No Role') . ')')

@section('content')
    <div id="notification" class="fixed top-6 right-6 text-white px-5 py-3 rounded-lg shadow-lg z-50 hidden transform transition-all duration-300 translate-x-full" style="background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));">
        <span id="notification-message"></span>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white p-6 rounded-xl shadow-sm border border-[#E5DED4]">
            <h3 class="text-sm font-medium text-[#B3B5B4] mb-2">Total Leads</h3>
            <div class="text-3xl font-bold text-brand-primary" id="total-leads">-</div>
        </div>
        <div class="bg-white p-6 rounded-xl shadow-sm border border-[#E5DED4]">
            <h3 class="text-sm font-medium text-[#B3B5B4] mb-2">New Leads</h3>
            <div class="text-3xl font-bold text-brand-primary" id="new-leads">-</div>
        </div>
        <div class="bg-white p-6 rounded-xl shadow-sm border border-[#E5DED4]">
            <h3 class="text-sm font-medium text-[#B3B5B4] mb-2">Qualified Leads</h3>
            <div class="text-3xl font-bold text-brand-primary" id="qualified-leads">-</div>
        </div>
        <div class="bg-white p-6 rounded-xl shadow-sm border border-[#E5DED4]">
            <h3 class="text-sm font-medium text-[#B3B5B4] mb-2">Closed Won</h3>
            <div class="text-3xl font-bold text-brand-primary" id="closed-won">-</div>
        </div>
    </div>

    <!-- Target Progress Section (for Sales Executives) -->
    <div id="target-progress-section" class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6 mb-6 hidden">
        <h2 class="text-xl font-semibold text-brand-primary mb-4">Monthly Targets Progress</h2>
        <div id="target-progress-content">
            <p class="text-gray-600">Loading targets...</p>
        </div>
    </div>

    <!-- Team Targets Section (for Managers) -->
    <div id="team-targets-section" class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6 mb-6 hidden">
        <h2 class="text-xl font-semibold text-brand-primary mb-4">Team Targets Progress</h2>
        <div id="team-targets-content">
            <p class="text-gray-600">Loading team targets...</p>
        </div>
    </div>

    <!-- Target Overview Section (for Admin/CRM) -->
    <div id="target-overview-section" class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6 mb-6 hidden">
        <h2 class="text-xl font-semibold text-brand-primary mb-4">System Targets Overview</h2>
        <div id="target-overview-content">
            <p class="text-gray-600">Loading overview...</p>
        </div>
    </div>

    <!-- Recent Leads Section -->
    <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6 mb-6">
        <h2 class="text-xl font-semibold text-brand-primary mb-4">Recent Leads</h2>
        <div id="recent-leads">
            <p class="text-gray-600">Loading...</p>
        </div>
    </div>

    <!-- Upcoming Follow-ups Section -->
    <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6">
        <h2 class="text-xl font-semibold text-brand-primary mb-4">Upcoming Follow-ups</h2>
        <div id="upcoming-followups">
            <p class="text-gray-600">Loading...</p>
        </div>
    </div>
@endsection

@push('styles')
<style>
    .badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 500;
    }
    .badge-new { background: #dbeafe; color: #1e40af; }
    .badge-connected { background: #bfdbfe; color: #1e3a8a; }
    .badge-verified-prospect { background: #e9d5ff; color: #6b21a8; }
    .badge-meeting-scheduled { background: #ddd6fe; color: #5b21b6; }
    .badge-meeting-completed { background: #cffafe; color: #155e75; }
    .badge-visit-scheduled { background: #ede9fe; color: #5b21b6; }
    .badge-visit-done { background: #fce7f3; color: #9f1239; }
    .badge-revisited-scheduled { background: #fce7f3; color: #9f1239; }
    .badge-revisited-completed { background: #fecdd3; color: #881337; }
    .badge-closed { background: #d1fae5; color: #065f46; }
    .badge-dead { background: #fee2e2; color: #991b1b; }
    .badge-on-hold { background: #f3f4f6; color: #374151; }
    .badge-contacted { background: #fef3c7; color: #92400e; }
    .badge-default { background: #f3f4f6; color: #6b7280; }
    .badge-qualified { background: #e9d5ff; color: #6b21a8; } /* Backward compatibility */
    .progress-bar {
        width: 100%;
        height: 24px;
        background: #E5DED4;
        border-radius: 12px;
        overflow: hidden;
        margin-top: 8px;
    }
    .progress-fill {
        height: 100%;
        background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
        transition: width 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 12px;
        font-weight: 600;
    }
    .progress-fill.warning {
        background: linear-gradient(90deg, #f59e0b 0%, #d97706 100%);
    }
    .progress-fill.danger {
        background: linear-gradient(90deg, #ef4444 0%, #dc2626 100%);
    }
    .target-card {
        padding: 16px;
        border: 1px solid #E5DED4;
        border-radius: 8px;
        margin-bottom: 16px;
    }
    .target-card h4 {
        font-size: 14px;
        font-weight: 600;
        color: var(--text-color);
        margin-bottom: 8px;
    }
    .target-stats {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 8px;
    }
    .target-stats span {
        font-size: 14px;
        color: #B3B5B4;
    }
    table {
        width: 100%;
        border-collapse: collapse;
    }
    th, td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #E5DED4;
    }
    th {
        background: #F7F6F3;
        font-weight: 600;
        color: var(--text-color);
        font-size: 14px;
    }
    td {
        color: var(--text-color);
        font-size: 14px;
    }
</style>
@endpush

@push('scripts')
<script>
    const token = localStorage.getItem('auth_token');
    const apiBase = '/api';

    // Initialize Pusher for real-time updates
    const pusher = new Pusher('{{ env("PUSHER_APP_KEY") }}', {
        cluster: '{{ env("PUSHER_APP_CLUSTER", "mt1") }}',
        encrypted: true,
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                'Authorization': 'Bearer ' + token
            }
        }
    });

    // Subscribe to user channel for notifications (popup is shown by layout; here we only refresh data)
    const userId = {{ auth()->id() ?? 'null' }};
    if (userId) {
        const channel = pusher.subscribe('private-user.' + userId);
        
        channel.bind('lead.assigned', function(data) {
            if (typeof showLeadAssignedPopup === 'function') {
                const lead = data.lead || {};
                const viewUrl = '{{ (auth()->user() && (auth()->user()->isTelecaller() || auth()->user()->isSalesExecutive())) ? route("telecaller.tasks")."?status=pending" : route("leads.index") }}';
                showLeadAssignedPopup({
                    title: 'New lead assigned',
                    message: 'You have 1 new lead assigned: ' + (lead.name || 'Lead') + '. View leads to see details and call.',
                    viewUrl: viewUrl,
                    leadPhone: lead.phone || '',
                    leadName: lead.name || 'Lead'
                });
            }
            loadDashboard();
        });

        channel.bind('lead.status.updated', function(data) {
            showNotification('Lead status updated: ' + data.lead.name);
            loadDashboard();
        });

        channel.bind('site-visit.created', function(data) {
            showNotification('New site visit scheduled');
            loadDashboard();
        });
    }

    function showNotification(message) {
        const notification = document.getElementById('notification');
        const messageEl = document.getElementById('notification-message');
        if (notification && messageEl) {
            messageEl.textContent = message;
            notification.classList.remove('hidden', 'translate-x-full');
            notification.classList.add('translate-x-0');
            setTimeout(() => {
                notification.classList.remove('translate-x-0');
                notification.classList.add('translate-x-full');
                setTimeout(() => {
                    notification.classList.add('hidden');
                }, 300);
            }, 3000);
        }
    }

    function loadDashboard() {
        axios.get(apiBase + '/dashboard', {
            headers: { 'Authorization': 'Bearer ' + token }
        })
        .then(response => {
            const data = response.data;
            
            // Update stats
            document.getElementById('total-leads').textContent = data.stats.total_leads;
            document.getElementById('new-leads').textContent = data.stats.new_leads;
            document.getElementById('qualified-leads').textContent = data.stats.qualified_leads;
            document.getElementById('closed-won').textContent = data.stats.closed_won;

            // Status mapping function
            function getStatusDisplay(status) {
                const statusMap = {
                    'new': { label: 'New', class: 'badge-new' },
                    'connected': { label: 'Connected', class: 'badge-connected' },
                    'verified_prospect': { label: 'Verified Prospect', class: 'badge-verified-prospect' },
                    'meeting_scheduled': { label: 'Meeting Scheduled', class: 'badge-meeting-scheduled' },
                    'meeting_completed': { label: 'Meeting Completed', class: 'badge-meeting-completed' },
                    'visit_scheduled': { label: 'Visit Scheduled', class: 'badge-visit-scheduled' },
                    'visit_done': { label: 'Visit Done', class: 'badge-visit-done' },
                    'revisited_scheduled': { label: 'Revisit Scheduled', class: 'badge-revisited-scheduled' },
                    'revisited_completed': { label: 'Revisit Completed', class: 'badge-revisited-completed' },
                    'closed': { label: 'Closed', class: 'badge-closed' },
                    'dead': { label: 'Dead', class: 'badge-dead' },
                    'on_hold': { label: 'On Hold', class: 'badge-on-hold' },
                    // Old statuses (backward compatibility)
                    'contacted': { label: 'Contacted', class: 'badge-contacted' },
                    'qualified': { label: 'Verified Prospect', class: 'badge-verified-prospect' },
                    'site_visit_scheduled': { label: 'Visit Scheduled', class: 'badge-visit-scheduled' },
                    'site_visit_completed': { label: 'Visit Done', class: 'badge-visit-done' },
                    'closed_won': { label: 'Closed', class: 'badge-closed' },
                    'closed_lost': { label: 'Dead', class: 'badge-dead' },
                };
                return statusMap[status] || { label: status || 'N/A', class: 'badge-default' };
            }

            // Update recent leads
            const leadsHtml = data.recent_leads.length > 0 
                ? '<table><thead><tr><th>Name</th><th>Phone</th><th>Status</th><th>Created</th></tr></thead><tbody>' +
                  data.recent_leads.map(lead => {
                      const statusInfo = getStatusDisplay(lead.status);
                      return '<tr><td>' + lead.name + '</td><td>' + lead.phone + '</td><td><span class="badge ' + statusInfo.class + '">' + statusInfo.label + '</span></td><td>' + new Date(lead.created_at).toLocaleDateString() + '</td></tr>';
                  }).join('') + '</tbody></table>'
                : '<p class="text-gray-600">No leads found</p>';
            document.getElementById('recent-leads').innerHTML = leadsHtml;

            // Update follow-ups
            const followUpsHtml = data.upcoming_followups.length > 0
                ? '<table><thead><tr><th>Lead</th><th>Type</th><th>Scheduled At</th></tr></thead><tbody>' +
                  data.upcoming_followups.map(fu => 
                      '<tr><td>' + (fu.lead ? fu.lead.name : 'N/A') + '</td><td>' + fu.type + '</td><td>' + new Date(fu.scheduled_at).toLocaleString() + '</td></tr>'
                  ).join('') + '</tbody></table>'
                : '<p class="text-gray-600">No upcoming follow-ups</p>';
            document.getElementById('upcoming-followups').innerHTML = followUpsHtml;

            // Update target progress for telecallers
            if (data.targets && data.targets.target) {
                const targetSection = document.getElementById('target-progress-section');
                const targetContent = document.getElementById('target-progress-content');
                if (targetSection && targetContent) {
                    targetSection.classList.remove('hidden');
                    const progress = data.targets.progress;
                    targetContent.innerHTML = `
                        <div class="target-card">
                            <h4>Prospects to Extract</h4>
                            <div class="progress-bar">
                                <div class="progress-fill ${getProgressClass(progress.prospects_extract.percentage)}" style="width: ${Math.min(100, progress.prospects_extract.percentage)}%">
                                    ${Math.round(progress.prospects_extract.percentage)}%
                                </div>
                            </div>
                            <div class="target-stats">
                                <span>${progress.prospects_extract.actual} / ${progress.prospects_extract.target}</span>
                            </div>
                        </div>
                        <div class="target-card">
                            <h4>Prospects Verified</h4>
                            <div class="progress-bar">
                                <div class="progress-fill ${getProgressClass(progress.prospects_verified.percentage)}" style="width: ${Math.min(100, progress.prospects_verified.percentage)}%">
                                    ${Math.round(progress.prospects_verified.percentage)}%
                                </div>
                            </div>
                            <div class="target-stats">
                                <span>${progress.prospects_verified.actual} / ${progress.prospects_verified.target}</span>
                            </div>
                        </div>
                        <div class="target-card">
                            <h4>Calls Completed</h4>
                            <div class="progress-bar">
                                <div class="progress-fill ${getProgressClass(progress.calls.percentage)}" style="width: ${Math.min(100, progress.calls.percentage)}%">
                                    ${Math.round(progress.calls.percentage)}%
                                </div>
                            </div>
                            <div class="target-stats">
                                <span>${progress.calls.actual} / ${progress.calls.target}</span>
                            </div>
                        </div>
                    `;
                }
            }

            // Update team targets for managers
            if (data.team_targets && data.team_targets.length > 0) {
                const teamSection = document.getElementById('team-targets-section');
                const teamContent = document.getElementById('team-targets-content');
                if (teamSection && teamContent) {
                    teamSection.classList.remove('hidden');
                    teamContent.innerHTML = '<table><thead><tr><th>Sales Executive</th><th>Prospects Extract</th><th>Prospects Verified</th><th>Calls</th></tr></thead><tbody>' +
                        data.team_targets.map(member => {
                            const p = member.progress;
                            return `<tr>
                                <td><strong>${member.user.name}</strong></td>
                                <td>${p.prospects_extract.actual} / ${p.prospects_extract.target} (${Math.round(p.prospects_extract.percentage)}%)</td>
                                <td>${p.prospects_verified.actual} / ${p.prospects_verified.target} (${Math.round(p.prospects_verified.percentage)}%)</td>
                                <td>${p.calls.actual} / ${p.calls.target} (${Math.round(p.calls.percentage)}%)</td>
                            </tr>`;
                        }).join('') + '</tbody></table>';
                }
            }

            // Update target overview for admin/CRM
            if (data.target_overview) {
                const overviewSection = document.getElementById('target-overview-section');
                const overviewContent = document.getElementById('target-overview-content');
                if (overviewSection && overviewContent) {
                    overviewSection.classList.remove('hidden');
                    const ov = data.target_overview;
                    overviewContent.innerHTML = `
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <div class="target-card">
                                <h4>Total Prospects Extract</h4>
                                <div class="text-2xl font-bold text-gray-700">${ov.actuals.prospects_extract} / ${ov.targets.prospects_extract}</div>
                                <div class="text-sm text-gray-600">${Math.round(ov.percentages.prospects_extract)}% Complete</div>
                            </div>
                            <div class="target-card">
                                <h4>Total Prospects Verified</h4>
                                <div class="text-2xl font-bold text-gray-700">${ov.actuals.prospects_verified} / ${ov.targets.prospects_verified}</div>
                                <div class="text-sm text-gray-600">${Math.round(ov.percentages.prospects_verified)}% Complete</div>
                            </div>
                            <div class="target-card">
                                <h4>Total Calls</h4>
                                <div class="text-2xl font-bold text-gray-700">${ov.actuals.calls} / ${ov.targets.calls}</div>
                                <div class="text-sm text-gray-600">${Math.round(ov.percentages.calls)}% Complete</div>
                            </div>
                        </div>
                        <p class="text-sm text-gray-600">Total Users: ${ov.total_users} | Month: ${ov.month}</p>
                    `;
                }
            }
        })
        .catch(error => {
            console.error('Error loading dashboard:', error);
        });
    }

    function getProgressClass(percentage) {
        if (percentage >= 100) return '';
        if (percentage >= 50) return 'warning';
        return 'danger';
    }

    // Initial load
    loadDashboard();

    // On load: show lead-assigned popup if user has unread new_lead notifications
    if (token && userId && typeof showLeadAssignedPopup === 'function') {
        var viewUrlDefault = (document.getElementById('lead-assigned-view-btn') && document.getElementById('lead-assigned-view-btn').getAttribute('href')) || '';
        axios.get(apiBase + '/notifications/unread', { headers: { 'Authorization': 'Bearer ' + token } })
            .then(function(res) {
                if (res.data.data && res.data.data.length) {
                    var newLead = res.data.data.find(function(n) { return n.type === 'new_lead'; });
                    if (newLead) {
                        var data = newLead.data || {};
                        var count = (data.lead_count || 1);
                        var name = data.last_lead_name || newLead.message || 'Lead';
                        showLeadAssignedPopup({
                            title: count > 1 ? 'You have new leads assigned' : 'New lead assigned',
                            message: count > 1 ? ('You have ' + count + ' new leads assigned. View leads to see details and call.') : ('You have 1 new lead assigned: ' + name + '. View leads to see details and call.'),
                            viewUrl: viewUrlDefault,
                            leadPhone: data.last_lead_phone || (newLead.lead && newLead.lead.phone ? newLead.lead.phone : ''),
                            leadName: name
                        });
                    }
                }
            })
            .catch(function() {});
    }

    // Auto-refresh every 30 seconds
    setInterval(loadDashboard, 30000);
</script>
@endpush


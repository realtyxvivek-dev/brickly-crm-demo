@extends('sales-manager.layout')

@section('title', 'My Team - Senior Manager')
@section('page-title', 'My Team')

@push('styles')
<style>
    .team-member-card {
        display: flex;
        align-items: center;
        padding: 16px;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        margin-bottom: 12px;
        transition: all 0.3s;
        background: white;
    }
    .team-member-card:hover {
        border-color: #205A44;
        box-shadow: 0 4px 12px rgba(32, 90, 68, 0.1);
    }
    .team-member-avatar {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: linear-gradient(135deg, #205A44 0%, #063A1C 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 20px;
        font-weight: 600;
        margin-right: 16px;
        flex-shrink: 0;
    }
    .team-member-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 50%;
    }
    .team-member-info {
        flex: 1;
    }
    .team-member-name {
        font-weight: 600;
        color: #063A1C;
        font-size: 16px;
        margin-bottom: 4px;
    }
    .team-member-role {
        font-size: 13px;
        color: #9ca3af;
        margin-bottom: 4px;
    }
    .team-member-email {
        font-size: 13px;
        color: #6b7280;
    }
    .team-member-status {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 12px;
        font-size: 13px;
        font-weight: 500;
    }
    .status-available {
        background: #d1fae5;
        color: #065f46;
    }
    .status-absent {
        background: #fee2e2;
        color: #991b1b;
    }
    .stat-badge {
        display: inline-block;
        padding: 4px 10px;
        background: #f3f4f6;
        border-radius: 6px;
        font-size: 12px;
        color: #4b5563;
        margin-top: 8px;
    }
    .loading {
        text-align: center;
        padding: 40px;
        color: #9ca3af;
    }
    
    /* Hide empty/loading states on mobile */
    @media (max-width: 767px) {
        .loading {
            display: none !important;
        }
        .empty-state {
            display: none !important;
        }
    }
    
    @media (max-width: 768px) {
        .grid.grid-cols-1.md\:grid-cols-4 {
            grid-template-columns: 1fr;
            gap: 12px;
        }
        
        .grid.grid-cols-1.md\:grid-cols-4 > div {
            padding: 16px !important;
        }
        
        .grid.grid-cols-1.md\:grid-cols-4 > div .text-3xl {
            font-size: 24px !important;
        }
        
        .team-member-card {
            flex-direction: column;
            align-items: flex-start;
            padding: 16px;
        }
        
        .team-member-avatar {
            margin-right: 0;
            margin-bottom: 12px;
        }
        
        .team-member-info {
            width: 100%;
            margin-bottom: 12px;
        }
        
        .team-member-card > div:last-child {
            width: 100%;
            text-align: left;
        }
        
        .team-member-status {
            display: block;
            width: fit-content;
            margin-bottom: 8px;
        }
        
        .stat-badge {
            display: block;
            width: fit-content;
        }
    }
</style>
@endpush

@section('content')
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-lg shadow p-6">
        <div class="text-sm text-gray-500 mb-1">Total Members</div>
        <div class="text-3xl font-bold text-gray-900" id="totalMembers">0</div>
    </div>
    <div class="bg-white rounded-lg shadow p-6">
        <div class="text-sm text-gray-500 mb-1">Available</div>
        <div class="text-3xl font-bold text-green-600" id="availableMembers">0</div>
    </div>
    <div class="bg-white rounded-lg shadow p-6">
        <div class="text-sm text-gray-500 mb-1">Absent</div>
        <div class="text-3xl font-bold text-red-600" id="absentMembers">0</div>
    </div>
    <div class="bg-white rounded-lg shadow p-6">
        <div class="text-sm text-gray-500 mb-1">Today's Prospects</div>
        <div class="text-3xl font-bold text-blue-600" id="todayProspects">0</div>
    </div>
</div>

<div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-xl font-bold text-gray-900 mb-6">Team Members</h2>
    
    <div id="teamMembersContainer">
        <div class="loading">
            <i class="fas fa-spinner fa-spin text-4xl mb-2"></i>
            <p>Loading team members...</p>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const API_BASE_URL = '{{ url("/api/sales-manager") }}';
    const API_TOKEN = '{{ $api_token ?? session("api_token") ?? "" }}';
    
    function getToken() {
        return typeof window.getManagerApiToken === 'function'
            ? window.getManagerApiToken()
            : (API_TOKEN || document.querySelector('meta[name="api-token"]')?.content || '{{ session("api_token") ?? "" }}');
    }

    // API call helper
    async function apiCall(endpoint, options = {}) {
        const token = getToken();
        if (!token) {
            console.error('No API token available for endpoint:', endpoint);
            window.location.href = '{{ route("login") }}';
            return null;
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        
        const defaultOptions = {
            headers: {
                ...window.getManagerAuthHeaders({
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
                window.handleManagerAuthFailure('team');
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

    // Load team data
    async function loadTeamData() {
        try {
            console.log('Loading team data...');
            console.log('API Token available:', !!getToken());
            
            const data = await apiCall('/profile');
            console.log('Profile API response:', data);
            
            if (!data) {
                console.error('Failed to load team data - no response');
                showError();
                return;
            }

            // Update statistics
            if (data.team_stats) {
                console.log('Team stats:', data.team_stats);
                document.getElementById('totalMembers').textContent = data.team_stats.total_members || 0;
                document.getElementById('availableMembers').textContent = data.team_stats.available_members || 0;
                document.getElementById('absentMembers').textContent = (data.team_stats.total_members - data.team_stats.available_members) || 0;
                document.getElementById('todayProspects').textContent = data.team_stats.today_prospects || 0;
            } else {
                console.warn('No team_stats in response');
            }

            // Display team members
            console.log('Team members data:', data.team_members); // Debug log
            if (data.team_members && Array.isArray(data.team_members) && data.team_members.length > 0) {
                console.log('Found', data.team_members.length, 'team members');
                displayTeamMembers(data.team_members);
            } else {
                console.warn('No team members found or empty array');
                // Hide empty state on mobile
                if (window.innerWidth <= 767) {
                    document.getElementById('teamMembersContainer').innerHTML = '';
                } else {
                    showNoMembers();
                }
            }
        } catch (error) {
            console.error('Error loading team data:', error);
            console.error('Error details:', error.message, error.stack);
            showError();
        }
    }

    // Display team members
    function displayTeamMembers(teamMembers) {
        const container = document.getElementById('teamMembersContainer');
        
        if (!teamMembers || teamMembers.length === 0) {
            // Hide empty state on mobile
            if (window.innerWidth <= 767) {
                container.innerHTML = '';
                return;
            }
            showNoMembers();
            return;
        }

        const html = teamMembers.map(member => `
            <div class="team-member-card">
                <div class="team-member-avatar">
                    ${member.profile_picture ? 
                        `<img src="${member.profile_picture}" alt="${member.name}">` : 
                        member.name.charAt(0).toUpperCase()
                    }
                </div>
                <div class="team-member-info">
                    <div class="team-member-name">${member.name}</div>
                    <div class="team-member-role">${member.role}</div>
                    <div class="team-member-email">
                        <i class="fas fa-envelope" style="margin-right: 4px;"></i>${member.email}
                    </div>
                    ${member.phone ? `
                        <div class="team-member-email" style="margin-top: 2px;">
                            <i class="fas fa-phone" style="margin-right: 4px;"></i>${member.phone}
                        </div>
                    ` : ''}
                </div>
                <div style="text-align: right;">
                    <span class="team-member-status ${member.is_absent ? 'status-absent' : 'status-available'}">
                        <i class="fas fa-circle" style="font-size: 8px; margin-right: 4px;"></i>
                        ${member.is_absent ? 'Absent' : 'Available'}
                    </span>
                    ${member.is_absent && member.absent_reason ? `
                        <div style="font-size: 11px; color: #9ca3af; margin-top: 4px;">
                            ${member.absent_reason}
                        </div>
                    ` : ''}
                    ${member.today_prospects !== undefined ? `
                        <div class="stat-badge">
                            <i class="fas fa-star" style="font-size: 10px; margin-right: 4px;"></i>
                            Today: ${member.today_prospects} prospects
                        </div>
                    ` : ''}
                    <div style="font-size: 11px; color: #9ca3af; margin-top: 8px;">
                        <i class="fas fa-calendar" style="margin-right: 4px;"></i>
                        Joined ${member.joined_at}
                    </div>
                </div>
            </div>
        `).join('');
        
        container.innerHTML = html;
    }

    // Show no members message
    function showNoMembers() {
        const container = document.getElementById('teamMembersContainer');
        // Hide empty state on mobile
        if (window.innerWidth <= 767) {
            container.innerHTML = '';
            return;
        }
        container.innerHTML = `
            <div class="text-center py-12 empty-state">
                <i class="fas fa-users text-gray-300 text-6xl mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-700 mb-2">No Team Members</h3>
                <p class="text-gray-500">You don't have any team members assigned yet.</p>
                <p class="text-sm text-gray-400 mt-4">Contact your administrator to assign team members to you.</p>
            </div>
        `;
    }

    // Show error message
    function showError() {
        const container = document.getElementById('teamMembersContainer');
        // Hide error on mobile - show nothing
        if (window.innerWidth <= 767) {
            container.innerHTML = '';
            return;
        }
        container.innerHTML = `
            <div class="text-center py-12 empty-state">
                <i class="fas fa-exclamation-triangle text-red-300 text-6xl mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-700 mb-2">Error Loading Team</h3>
                <p class="text-gray-500">Unable to load team data. Please try refreshing the page.</p>
                <button onclick="loadTeamData()" class="mt-4 px-4 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg hover:from-[#205A44] hover:to-[#15803d]">
                    <i class="fas fa-sync-alt mr-2"></i>Retry
                </button>
            </div>
        `;
    }

    // Initialize on page load
    (function() {
        loadTeamData();
    })();
</script>
@endpush

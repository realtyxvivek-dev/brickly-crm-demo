@extends('sales-manager.layout')

@section('title', 'Prospects - Senior Manager')
@section('page-title', 'Prospects')

@push('styles')
<style>
    .asm-filter-shell {
        border: 1px solid #d9e5dd;
        border-radius: 16px;
        box-shadow: 0 14px 34px rgba(6, 58, 28, 0.08);
        background: linear-gradient(180deg, #ffffff 0%, #fbfcfb 100%);
        padding: 18px;
        margin-bottom: 20px;
    }
    .asm-filter-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.4fr) repeat(2, minmax(160px, 0.8fr));
        gap: 10px;
        align-items: end;
    }
    .asm-filter-grid.asm-filter-grid--three {
        grid-template-columns: minmax(0, 1.4fr) repeat(2, minmax(160px, 0.8fr));
    }
    .asm-filter-field {
        display: flex;
        flex-direction: column;
        gap: 8px;
        min-width: 0;
    }
    .asm-filter-field label {
        font-size: 0.78rem;
        font-weight: 800;
        color: #587060;
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }
    .asm-filter-input,
    .asm-filter-select {
        width: 100%;
        min-height: 48px;
        border-radius: 14px;
        border: 1px solid #d7e2da;
        background: #ffffff;
        padding: 0 14px;
        font-size: 0.95rem;
        color: #173427;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .asm-filter-input::placeholder {
        color: #8b9a90;
    }
    .asm-filter-input:focus,
    .asm-filter-select:focus {
        outline: none;
        border-color: #205A44;
        box-shadow: 0 0 0 4px rgba(32, 90, 68, 0.10);
    }

    .prospect-view-toggle {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        padding: 0.25rem;
        background: #edf2f7;
        border: 1px solid #dbe4ee;
        border-radius: 9999px;
    }

    .prospect-view-toggle button {
        border: 0;
        background: transparent;
        color: #5f6c7b;
        padding: 0.7rem 1rem;
        border-radius: 9999px;
        font-size: 0.875rem;
        font-weight: 600;
        transition: all 0.2s ease;
    }

    .prospect-view-toggle button.active {
        background: linear-gradient(135deg, #063A1C, #205A44);
        color: #fff;
        box-shadow: 0 8px 18px rgba(6, 58, 28, 0.18);
    }

    /* Base container styles - prevent overflow */
    .bg-white.rounded-lg.shadow.p-6.mb-6 {
        box-sizing: border-box;
        max-width: 100%;
        overflow-x: hidden;
    }
    
    #prospectsCards {
        box-sizing: border-box;
        max-width: 100%;
        overflow-x: hidden;
    }
    
    #prospectsGrid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1.5rem;
        box-sizing: border-box;
        max-width: 100%;
    }
    
    @media (max-width: 1024px) {
        #prospectsGrid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    
    @media (max-width: 768px) {
        .asm-filter-shell {
            padding: 12px;
            border-radius: 14px;
        }
        .asm-filter-grid,
        .asm-filter-grid.asm-filter-grid--three {
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
            align-items: stretch;
        }
        .asm-filter-field {
            gap: 0;
        }
        .asm-filter-field label {
            display: none;
        }
        .asm-filter-input,
        .asm-filter-select {
            min-height: 44px;
            padding: 0 12px;
            font-size: 12px;
        }
        /* Container overflow fix */
        .bg-white.rounded-lg.shadow.p-6.mb-6 {
            overflow-x: hidden;
            max-width: 100%;
            box-sizing: border-box;
            padding: 12px !important;
            margin: 0;
        }
        
        #prospectsCards {
            overflow-x: hidden;
            max-width: 100%;
            box-sizing: border-box;
        }
        
        #prospectsGrid {
            grid-template-columns: repeat(2, 1fr) !important;
            gap: 0.5rem !important;
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
            padding: 0 !important;
            margin: 0 !important;
        }
        
        /* Prospect cards - prevent overflow and ensure 50% width */
        #prospectsGrid > div {
            max-width: 100% !important;
            width: 100% !important;
            box-sizing: border-box !important;
            min-width: 0 !important;
            overflow: hidden !important;
        }
        
        #prospectsGrid > div > .p-5 {
            padding: 12px !important;
            max-width: 100%;
            box-sizing: border-box;
            overflow: hidden;
            word-wrap: break-word;
        }
        
        /* Ensure all text elements don't overflow */
        #prospectsGrid > div * {
            max-width: 100%;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        
        /* Prospect cards responsive */
        .bg-white.rounded-lg.shadow {
            padding: 16px !important;
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
    
    @media (max-width: 480px) {
        /* Extra small screens - keep 2 columns for 50%-50% layout */
        #prospectsGrid {
            grid-template-columns: repeat(2, 1fr) !important;
            gap: 0.5rem !important;
        }
        
        .bg-white.rounded-lg.shadow.p-6.mb-6 {
            padding: 10px !important;
        }
    }
    
    .prospect-card {
        background: linear-gradient(180deg, #ffffff 0%, #fbfdfb 100%);
        border-radius: 20px;
        box-shadow: 0 18px 45px rgba(6, 58, 28, 0.08);
        border: 1px solid rgba(32, 90, 68, 0.10);
        border-left: 4px solid #205A44;
        display: flex;
        flex-direction: column;
        min-height: 100%;
        transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
    }
    
    .prospect-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 24px 52px rgba(6, 58, 28, 0.12);
    }

    .prospect-card .prospect-card-body {
        padding: 16px;
        display: flex;
        flex-direction: column;
        min-height: 100%;
    }
    
    /* Prospect action buttons - matching task section style */
    .prospect-actions {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin-top: 16px;
        padding-top: 16px;
        border-top: 2px solid #f0f0f0;
    }
    
    .prospect-action-btn {
        width: 100%;
        padding: 12px;
        border: none;
        border-radius: 12px;
        font-size: 0.88rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        text-decoration: none;
    }
    
    .prospect-action-btn i {
        font-size: 14px;
    }
    
    .btn-short-detail {
        background: linear-gradient(135deg, #25603F 0%, #063A1C 100%);
        color: #ffffff;
        box-shadow: 0 4px 10px rgba(6, 58, 28, 0.25);
    }
    
    .btn-short-detail:hover {
        background: linear-gradient(135deg, #1e4d32 0%, #043118 100%);
        transform: translateY(-1px);
        box-shadow: 0 6px 14px rgba(6, 58, 28, 0.35);
    }
    
    .btn-full-detail {
        background: linear-gradient(135deg, #25603F 0%, #063A1C 100%);
        color: white;
        box-shadow: 0 4px 10px rgba(6, 58, 28, 0.25);
    }
    
    .btn-full-detail:hover {
        background: linear-gradient(135deg, #1e4d32 0%, #043118 100%);
        transform: translateY(-1px);
        box-shadow: 0 6px 14px rgba(6, 58, 28, 0.35);
    }
    
    /* Status pill */
    .prospect-status {
        padding: 6px 12px;
        border-radius: 999px;
        font-size: 0.78rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #e8f0fe;
        color: #1a56db;
        border: 1px solid #cdd9f6;
    }
    .prospect-status.pending {
        background: #fef3c7;
        color: #92400e;
        border-color: #fcd34d;
    }
    .prospect-status.verified,
    .prospect-status.connected {
        background: #e0f7ef;
        color: #0b3a2d;
        border-color: #b1e5d5;
    }
    .prospect-status.rejected {
        background: #fee2e2;
        color: #b91c1c;
        border-color: #fecdd3;
    }

    .prospects-list-shell {
        border: 1px solid #e5e7eb;
        border-radius: 1.25rem;
        overflow: hidden;
        background: #ffffff;
    }

    .prospects-list-table {
        width: 100%;
        border-collapse: collapse;
    }

    .prospects-list-table thead th {
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

    .prospects-list-table tbody td {
        padding: 1rem;
        border-bottom: 1px solid #eef2f7;
        vertical-align: top;
    }

    .prospects-list-table tbody tr:hover {
        background: #fcfdfd;
    }

    .prospect-list-name {
        color: #101828;
        font-weight: 700;
        font-size: 0.96rem;
    }

    .prospect-list-sub {
        color: #667085;
        font-size: 0.84rem;
        margin-top: 0.2rem;
    }

    .prospect-remark-text {
        color: #344054;
        font-size: 0.85rem;
        line-height: 1.45;
        max-width: 320px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .prospect-list-actions {
        display: flex;
        gap: 0.5rem;
        min-width: 190px;
    }

    .prospect-list-actions a,
    .prospect-list-actions button {
        flex: 1 1 0;
        border-radius: 0.85rem;
        padding: 0.72rem 0.85rem;
        font-size: 0.78rem;
        font-weight: 700;
        white-space: nowrap;
    }

    @media (max-width: 768px) {
        .prospect-view-toggle {
            width: 100%;
            justify-content: stretch;
        }

        .prospect-view-toggle button {
            flex: 1 1 0;
        }

        .prospects-list-shell {
            overflow-x: auto;
        }

        .prospects-list-table {
            min-width: 900px;
        }
    }
</style>
@endpush

@section('content')
  @php($showSeniorManagerUserFilter = auth()->check() && auth()->user()->isSeniorManager())
  <div class="bg-white rounded-lg shadow p-6 mb-6">
    <div class="asm-filter-shell">
        <div class="asm-filter-grid {{ $showSeniorManagerUserFilter ? 'asm-filter-grid--three' : '' }}">
            <div class="asm-filter-field">
                <label for="searchInput">Search</label>
                <input 
                    type="text" 
                    id="searchInput"
                    placeholder="Search prospects..."
                    class="asm-filter-input"
                    onkeyup="handleSearch()"
                >
            </div>
            <div class="asm-filter-field">
                <label for="statusFilter">Verification</label>
                <select 
                    id="statusFilter"
                    class="asm-filter-select"
                    onchange="loadProspects()"
                >
                    <option value="all">All Status</option>
                    <option value="pending_verification">Pending Verification</option>
                    <option value="verified">Verified</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
            @if($showSeniorManagerUserFilter)
                <div class="asm-filter-field">
                    <label for="userFilter">Owner</label>
                    <select 
                        id="userFilter"
                        class="asm-filter-select"
                        onchange="loadProspects()"
                    >
                        <option value="">All Users</option>
                        <!-- Options will be populated dynamically -->
                    </select>
                </div>
            @endif
        </div>
    </div>
    <div id="prospectFilterNotice" class="mb-4 px-4 py-3 rounded-lg border border-green-200 bg-green-50 text-sm text-green-800" style="display: none;"></div>

    <!-- Loading State -->
    <div id="loadingState" class="text-center py-12">
        <i class="fas fa-spinner fa-spin text-gray-400 text-4xl mb-4"></i>
        <p class="text-gray-500">Loading prospects...</p>
    </div>

    <!-- Empty State -->
    <div id="emptyState" class="text-center py-12 empty-state-mobile" style="display: none;">
        <i class="fas fa-star text-gray-300 text-6xl mb-4"></i>
        <h3 class="text-xl font-semibold text-gray-700 mb-2">No Prospects Found</h3>
        <p class="text-gray-500">No prospects match your current filters.</p>
    </div>

    <!-- Prospects Cards -->
    <div id="prospectsCards" style="display: none;">
        <div id="prospectsGrid">
            <!-- Prospects will be loaded here -->
        </div>
        
        <!-- Pagination -->
        <div id="pagination" class="mt-6 flex items-center justify-between">
            <!-- Pagination will be loaded here -->
        </div>
    </div>

    <div id="prospectsList" style="display: none;">
        <div class="prospects-list-shell">
            <table class="prospects-list-table">
                <thead>
                    <tr>
                        <th>Prospect</th>
                        <th>Status</th>
                        <th>Remark</th>
                        <th>Created By</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="prospectsListBody">
                    <!-- Prospect rows -->
                </tbody>
            </table>
        </div>

        <div id="paginationList" class="mt-6 flex items-center justify-between">
            <!-- Pagination will be loaded here -->
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const API_BASE_URL = '{{ url("/api/sales-manager") }}';
    const API_TOKEN = '{{ $api_token }}';
    const ASM_SECTION_VIEW_PREFERENCES = @json($sectionViewPreferences ?? []);
    const ASM_SECTION_VIEW_SAVE_URL = @json(route('sales-manager.settings.update'));
    const prospectPageParams = new URLSearchParams(window.location.search);
    const prospectPageFlags = {
        createdToday: prospectPageParams.get('created_today') === '1',
    };
    const prospectFilterStorageKey = 'salesManagerProspectFilters';

    function getStoredProspectFilters() {
        try {
            return JSON.parse(localStorage.getItem(prospectFilterStorageKey) || '{}');
        } catch (e) {
            return {};
        }
    }

    function persistProspectFilters(filters) {
        try {
            localStorage.setItem(prospectFilterStorageKey, JSON.stringify(filters));
        } catch (e) {
            console.error('Failed to persist prospect filters:', e);
        }
    }

    let searchTimeout = null;
    let teamMembers = [];
    let currentUser = null;
    let currentProspectView = 'cards';

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

    function setProspectView(view, shouldPersist = true) {
        currentProspectView = view === 'list' ? 'list' : 'cards';
        document.getElementById('prospectCardsViewBtn')?.classList.toggle('active', currentProspectView === 'cards');
        document.getElementById('prospectListViewBtn')?.classList.toggle('active', currentProspectView === 'list');
        document.getElementById('prospectsCards').style.display = currentProspectView === 'cards' && allProspects.length ? 'block' : 'none';
        document.getElementById('prospectsList').style.display = currentProspectView === 'list' && allProspects.length ? 'block' : 'none';
        if (shouldPersist) {
            persistAsmSectionViewPreference('prospects', currentProspectView);
        }
    }

    function applyProspectQueryFilters() {
        const searchInput = document.getElementById('searchInput');
        const statusFilter = document.getElementById('statusFilter');
        const userFilter = document.getElementById('userFilter');
        const notice = document.getElementById('prospectFilterNotice');

        const storedFilters = getStoredProspectFilters();
        const search = prospectPageParams.get('search') ?? storedFilters.search ?? '';
        const verificationStatus = prospectPageParams.get('verification_status') ?? storedFilters.verification_status ?? '';
        const assignedTo = prospectPageParams.get('assigned_to') ?? storedFilters.assigned_to ?? '';
        if (searchInput && search) searchInput.value = search;
        if (statusFilter && verificationStatus) statusFilter.value = verificationStatus;
        if (userFilter && assignedTo) userFilter.value = assignedTo;

        if (notice) {
            if (prospectPageFlags.createdToday) {
                notice.textContent = 'Showing prospects created today.';
                notice.style.display = 'block';
            } else {
                notice.style.display = 'none';
            }
        }
    }

    function getProspectRemark(prospect) {
        const candidates = [
            prospect.manager_remark,
            prospect.remark,
            prospect.employee_remark,
            prospect.rejection_reason,
        ];
        const remark = candidates.find(item => typeof item === 'string' && item.trim());
        return remark ? remark.trim() : 'No remark added';
    }

    function createProspectListRow(prospect) {
        const createdBy = prospect.telecaller ? prospect.telecaller.name : (prospect.created_by ? prospect.created_by.name : 'N/A');
        const createdAt = new Date(prospect.created_at).toLocaleString('en-IN', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
        });
        const statusBadge = getProspectStatusBadge(prospect);
        const remark = getProspectRemark(prospect);

        return `
            <tr>
                <td>
                    <div class="prospect-list-name">${escapeHtml(prospect.customer_name || 'N/A')}</div>
                    <div class="prospect-list-sub"><i class="fas fa-phone mr-2 text-gray-400"></i>${escapeHtml(prospect.phone || 'N/A')}</div>
                    ${prospect.preferred_location ? `<div class="prospect-list-sub"><i class="fas fa-map-marker-alt mr-2 text-gray-400"></i>${escapeHtml(prospect.preferred_location)}</div>` : ''}
                </td>
                <td>
                    ${statusBadge ? `<span class="prospect-status ${statusBadge.className}">${statusBadge.label}</span>` : '<span class="prospect-status">Unknown</span>'}
                    ${prospect.lead_status ? `<div class="prospect-list-sub mt-2">${escapeHtml(getLeadStatusLabel(prospect.lead_status))}</div>` : ''}
                </td>
                <td>
                    <div class="prospect-remark-text" title="${escapeHtml(remark)}">${escapeHtml(remark)}</div>
                </td>
                <td>
                    <div class="text-sm font-semibold text-gray-800">${escapeHtml(createdBy)}</div>
                    <div class="prospect-list-sub">${escapeHtml(prospect.purpose === 'end_user' ? 'End User' : (prospect.purpose === 'investment' ? 'Investment' : (prospect.purpose || 'No purpose')))}</div>
                </td>
                <td>
                    <div class="text-sm font-semibold text-gray-800">${escapeHtml(createdAt)}</div>
                    <div class="prospect-list-sub">${escapeHtml(prospect.budget || 'Budget not set')}</div>
                </td>
                <td>
                    <div class="prospect-list-actions">
                        <a href="/sales-manager/prospects/${prospect.id}" class="flex items-center justify-center bg-gradient-to-r from-[#25603F] to-[#063A1C] text-white hover:from-[#1e4d32] hover:to-[#043118] transition-all duration-200 shadow-md">
                            <i class="fas fa-eye mr-2"></i>View
                        </a>
                        <button type="button" onclick="openShortDetailModal(${prospect.id})" class="flex items-center justify-center bg-gradient-to-r from-[#25603F] to-[#063A1C] text-white hover:from-[#1e4d32] hover:to-[#043118] transition-all duration-200 shadow-md">
                            <i class="fas fa-info-circle mr-2"></i>Short
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }

    // Load team members for filter
    async function loadTeamMembers() {
        try {
            const response = await fetch(`${API_BASE_URL}/profile`, {
                headers: getAuthHeaders(),
                credentials: 'same-origin',
            });
            
            if (response.ok) {
                const data = await response.json();
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
                    }
                    
                    // Add team members
                    if (teamMembers && teamMembers.length > 0) {
                        teamMembers.forEach(member => {
                            if (member && member.id && member.name) {
                                const option = document.createElement('option');
                                option.value = member.id;
                                option.textContent = member.name;
                                userFilter.appendChild(option);
                            }
                        });
                    }
                }
            }
        } catch (error) {
            console.error('Error loading team members:', error);
        }
    }

    // Store all prospects data
    let allProspects = [];

    // Load prospects
    async function loadProspects(page = 1) {
        const loadingState = document.getElementById('loadingState');
        const emptyState = document.getElementById('emptyState');
        const prospectsCards = document.getElementById('prospectsCards');
        const prospectsGrid = document.getElementById('prospectsGrid');
        const prospectsList = document.getElementById('prospectsList');
        const prospectsListBody = document.getElementById('prospectsListBody');
        
        loadingState.style.display = 'block';
        emptyState.style.display = 'none';
        prospectsCards.style.display = 'none';
        prospectsList.style.display = 'none';

        try {
            const status = document.getElementById('statusFilter').value;
            const search = document.getElementById('searchInput').value;
            const assignedTo = document.getElementById('userFilter')?.value || '';
            const params = new URLSearchParams({
                page: page,
                per_page: 15,
            });
            
            if (status !== 'all') {
                params.append('verification_status', status);
            }
            
            if (search) {
                params.append('search', search);
            }
            
            if (assignedTo) {
                params.append('assigned_to', assignedTo);
            }

            persistProspectFilters({
                search,
                verification_status: status,
                assigned_to: assignedTo,
            });

            if (prospectPageFlags.createdToday) {
                params.append('created_today', '1');
            }

            const response = await fetch(`${API_BASE_URL}/prospects?${params}`, {
                headers: getAuthHeaders(),
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error('Failed to load prospects');
            }

            const data = await response.json();

            if (data.data && data.data.length > 0) {
                allProspects = data.data;
                prospectsGrid.innerHTML = '';
                prospectsListBody.innerHTML = '';
                data.data.forEach(prospect => {
                    const card = createProspectCard(prospect);
                    prospectsGrid.appendChild(card);
                    prospectsListBody.insertAdjacentHTML('beforeend', createProspectListRow(prospect));
                });
                
                renderPagination(data);
                emptyState.style.display = 'none';
                setProspectView(currentProspectView, false);
            } else {
                allProspects = [];
                prospectsCards.style.display = 'none';
                prospectsList.style.display = 'none';
                emptyState.style.display = 'block';
            }
        } catch (error) {
            console.error('Error loading prospects:', error);
            alert('Failed to load prospects. Please try again.');
        } finally {
            loadingState.style.display = 'none';
        }
    }

    // Create prospect card
    function createProspectCard(prospect) {
        const card = document.createElement('div');
        card.className = 'prospect-card';
        card.id = `prospect-card-${prospect.id}`;
        card.style.cssText = 'max-width: 100%; width: 100%; box-sizing: border-box; min-width: 0; overflow: hidden;';
        
        const createdBy = prospect.telecaller ? prospect.telecaller.name : (prospect.created_by ? prospect.created_by.name : 'N/A');
        const createdAt = new Date(prospect.created_at).toLocaleString('en-IN', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
        });

        const statusBadge = getProspectStatusBadge(prospect);

        card.innerHTML = `
            <div class="prospect-card-body" style="max-width: 100%; box-sizing: border-box; overflow: hidden; word-wrap: break-word;">
                <div class="flex items-start justify-between mb-3" style="gap: 8px; min-width: 0;">
                    <div class="flex-1" style="min-width: 0; overflow: hidden;">
                        <h3 class="text-lg font-semibold text-gray-900 mb-1" style="word-wrap: break-word; overflow-wrap: break-word;">${prospect.customer_name || 'N/A'}</h3>
                    </div>
                    ${statusBadge ? `<span class="prospect-status ${statusBadge.className}" style="flex-shrink: 0;">${statusBadge.label}</span>` : ''}
                </div>
                
                <div class="space-y-2 mb-4">
                    ${prospect.phone ? `
                    <div class="flex items-center text-sm text-gray-600">
                        <i class="fas fa-phone w-5 text-gray-400"></i>
                        <span>${prospect.phone}</span>
                    </div>
                    ` : ''}
                    <div class="flex items-center text-sm text-gray-500">
                        <i class="fas fa-calendar w-5 text-gray-400"></i>
                        <span>${createdAt}</span>
                    </div>
                </div>

                <div class="prospect-actions">
                    <a 
                        href="/sales-manager/prospects/${prospect.id}" 
                        class="prospect-action-btn btn-full-detail"
                        title="Full Detail"
                    >
                        <i class="fas fa-eye"></i>
                        <span>View Detail</span>
                    </a>
                    <button 
                        class="prospect-action-btn btn-short-detail" 
                        onclick="openShortDetailModal(${prospect.id})"
                        title="Short Detail"
                    >
                        <i class="fas fa-info-circle"></i>
                        <span>Short Detail</span>
                    </button>
                </div>
            </div>
            
            <!-- Expandable Details Section -->
            <div 
                id="details-${prospect.id}" 
                class="hidden border-t border-gray-200 bg-gray-50 p-5"
                style="transition: all 0.3s ease;"
            >
                <div class="space-y-3">
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        ${prospect.lead_score ? `
                        <div class="col-span-2">
                            <span class="text-gray-500">Lead Score:</span>
                            <span class="font-medium text-gray-900 ml-2">${renderStarRating(prospect.lead_score)} <span class="text-gray-500 text-xs">(${prospect.lead_score}/5)</span></span>
                        </div>
                        ` : ''}
                        <div>
                            <span class="text-gray-500">Phone:</span>
                            <span class="font-medium text-gray-900 ml-2">${prospect.phone || 'N/A'}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">Budget:</span>
                            <span class="font-medium text-gray-900 ml-2">${prospect.budget ? '₹' + parseFloat(prospect.budget).toLocaleString('en-IN') : 'N/A'}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">Location:</span>
                            <span class="font-medium text-gray-900 ml-2">${prospect.preferred_location || 'N/A'}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">Size:</span>
                            <span class="font-medium text-gray-900 ml-2">${prospect.size || 'N/A'}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">Purpose:</span>
                            <span class="font-medium text-gray-900 ml-2">${prospect.purpose === 'end_user' ? 'End User' : (prospect.purpose === 'investment' ? 'Investment' : 'N/A')}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">Possession:</span>
                            <span class="font-medium text-gray-900 ml-2">${prospect.possession || 'N/A'}</span>
                        </div>
                    </div>
                    ${prospect.remark ? `
                    <div class="mt-3 p-3 bg-white rounded border border-gray-200">
                        <span class="text-xs font-medium text-gray-500 uppercase">Remark:</span>
                        <p class="text-sm text-gray-700 mt-1">${prospect.remark}</p>
                    </div>
                    ` : ''}
                    ${prospect.manager_remark ? `
                    <div class="mt-3 p-3 bg-green-50 rounded border border-green-200">
                        <span class="text-xs font-medium text-green-700 uppercase">Manager Remark:</span>
                        <p class="text-sm text-green-800 mt-1">${prospect.manager_remark}</p>
                    </div>
                    ` : ''}
                    ${prospect.lead_status ? `
                    <div class="mt-3 p-3 bg-blue-50 rounded border border-blue-200">
                        <span class="text-xs font-medium text-blue-700 uppercase">Lead Status:</span>
                        <span class="ml-2 inline-block px-3 py-1 rounded-full text-xs font-semibold ${getLeadStatusBadgeClass(prospect.lead_status)}">${getLeadStatusLabel(prospect.lead_status)}</span>
                    </div>
                    ` : ''}
                    ${prospect.rejection_reason ? `
                    <div class="mt-3 p-3 bg-red-50 rounded border border-red-200">
                        <span class="text-xs font-medium text-red-700 uppercase">Rejection Reason:</span>
                        <p class="text-sm text-red-800 mt-1">${prospect.rejection_reason}</p>
                    </div>
                    ` : ''}
                </div>
            </div>
        `;
        
        return card;
    }

    function getLeadStatusLabel(status) {
        const labels = {
            'hot': 'Hot',
            'warm': 'Warm',
            'cold': 'Cold',
            'junk': 'Junk'
        };
        return labels[status] || status;
    }
    
    function getLeadStatusBadgeClass(status) {
        const classes = {
            'hot': 'bg-red-100 text-red-800 border-red-200',
            'warm': 'bg-orange-100 text-orange-800 border-orange-200',
            'cold': 'bg-blue-100 text-blue-800 border-blue-200',
            'junk': 'bg-gray-100 text-gray-800 border-gray-200'
        };
        return classes[status] || 'bg-gray-100 text-gray-800 border-gray-200';
    }
    
    // Render star rating
    function renderStarRating(rating) {
        if (!rating || rating < 1 || rating > 5) {
            return '<span class="text-gray-400">No rating</span>';
        }
        let stars = '';
        for (let i = 1; i <= 5; i++) {
            if (i <= rating) {
                stars += '<span style="color: #fbbf24; font-size: 16px;">★</span>';
            } else {
                stars += '<span style="color: #d1d5db; font-size: 16px;">☆</span>';
            }
        }
        return stars;
    }
    
    // Get status badge
    function getStatusBadge(status) {
        const badges = {
            'pending_verification': '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Pending</span>',
            'pending': '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Pending</span>',
            'verified': '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Verified</span>',
            'rejected': '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Rejected</span>',
        };
        return badges[status] || '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">' + status + '</span>';
    }

    // Prospect card status pill mapping
    function getProspectStatusBadge(prospect) {
        const status = (prospect.call_status || prospect.verification_status || 'pending').toLowerCase();
        const map = {
            'pending_verification': { label: 'Pending', className: 'pending' },
            'pending': { label: 'Pending', className: 'pending' },
            'connected': { label: 'Connected', className: 'connected' },
            'verified': { label: 'Verified', className: 'verified' },
            'rejected': { label: 'Rejected', className: 'rejected' }
        };
        return map[status] || { label: status.charAt(0).toUpperCase() + status.slice(1), className: 'pending' };
    }

    // Render pagination
    function renderPagination(data) {
        const paginations = [
            document.getElementById('pagination'),
            document.getElementById('paginationList'),
        ];
        if (data.last_page <= 1) {
            paginations.forEach((pagination) => {
                if (pagination) {
                    pagination.innerHTML = '';
                }
            });
            return;
        }

        let html = '<div class="flex items-center gap-2">';
        
        // Previous button
        if (data.current_page > 1) {
            html += `<button onclick="loadProspects(${data.current_page - 1})" class="px-3 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">Previous</button>`;
        }
        
        // Page numbers
        for (let i = 1; i <= data.last_page; i++) {
            if (i === 1 || i === data.last_page || (i >= data.current_page - 2 && i <= data.current_page + 2)) {
                html += `<button onclick="loadProspects(${i})" class="px-3 py-2 border border-gray-300 rounded-lg ${i === data.current_page ? 'bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white' : 'hover:bg-gray-50'}">${i}</button>`;
            } else if (i === data.current_page - 3 || i === data.current_page + 3) {
                html += `<span class="px-3 py-2">...</span>`;
            }
        }
        
        // Next button
        if (data.current_page < data.last_page) {
            html += `<button onclick="loadProspects(${data.current_page + 1})" class="px-3 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">Next</button>`;
        }
        
        html += '</div>';
        html += `<div class="text-sm text-gray-500">Showing ${data.from} to ${data.to} of ${data.total} prospects</div>`;
        
        paginations.forEach((pagination) => {
            if (pagination) {
                pagination.innerHTML = html;
            }
        });
    }

    // Toggle details section
    function toggleDetails(prospectId) {
        const detailsDiv = document.getElementById(`details-${prospectId}`);
        const chevron = document.getElementById(`chevron-${prospectId}`);
        const btn = document.getElementById(`viewDetailsBtn-${prospectId}`);
        
        if (detailsDiv.classList.contains('hidden')) {
            detailsDiv.classList.remove('hidden');
            chevron.classList.remove('fa-chevron-down');
            chevron.classList.add('fa-chevron-up');
            btn.innerHTML = `<i class="fas fa-chevron-up mr-2" id="chevron-${prospectId}"></i> Hide Details`;
        } else {
            detailsDiv.classList.add('hidden');
            chevron.classList.remove('fa-chevron-up');
            chevron.classList.add('fa-chevron-down');
            btn.innerHTML = `<i class="fas fa-chevron-down mr-2" id="chevron-${prospectId}"></i> View Details`;
        }
    }

    // Open WhatsApp
    function openWhatsApp(phone) {
        if (!phone || phone === 'N/A') {
            alert('Phone number not available');
            return;
        }
        const cleanedPhone = phone.replace(/[^\d+]/g, '');
        if (!cleanedPhone) {
            alert('Invalid phone number');
            return;
        }
        window.open(`https://wa.me/${cleanedPhone}`, '_blank');
    }

    // Make call
    function makeCall(phone) {
        if (!phone || phone === 'N/A') {
            alert('Phone number not available');
            return;
        }
        const cleanedPhone = phone.replace(/[^\d+]/g, '');
        if (!cleanedPhone) {
            alert('Invalid phone number');
            return;
        }
        window.location.href = `tel:${cleanedPhone}`;
    }

    // Handle search with debounce
    function handleSearch() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            loadProspects(1);
        }, 500);
    }

    // Load prospects on page load
    document.addEventListener('DOMContentLoaded', function() {
        currentProspectView = getAsmPreferredView('prospects', currentProspectView);
        loadTeamMembers().then(() => {
            applyProspectQueryFilters();
            loadProspects();
        });
    });

    // Short Detail Modal Functions
    function openShortDetailModal(prospectId) {
        const prospect = allProspects.find(p => p.id === prospectId);
        if (!prospect) return;
        
        const modal = document.getElementById('shortDetailModal');
        const content = document.getElementById('shortDetailContent');
        
        content.innerHTML = `
            <div class="space-y-3">
                <div class="grid grid-cols-2 gap-3 text-sm">
                    ${prospect.budget ? `
                    <div>
                        <span class="text-gray-500">Budget:</span>
                        <span class="font-medium text-gray-900 ml-2">₹${parseFloat(prospect.budget).toLocaleString('en-IN')}</span>
                    </div>
                    ` : ''}
                    ${prospect.preferred_location ? `
                    <div>
                        <span class="text-gray-500">Location:</span>
                        <span class="font-medium text-gray-900 ml-2">${prospect.preferred_location}</span>
                    </div>
                    ` : ''}
                    ${prospect.size ? `
                    <div>
                        <span class="text-gray-500">Size:</span>
                        <span class="font-medium text-gray-900 ml-2">${prospect.size}</span>
                    </div>
                    ` : ''}
                    ${prospect.purpose ? `
                    <div>
                        <span class="text-gray-500">Purpose:</span>
                        <span class="font-medium text-gray-900 ml-2">${prospect.purpose === 'end_user' ? 'End User' : (prospect.purpose === 'investment' ? 'Investment' : prospect.purpose)}</span>
                    </div>
                    ` : ''}
                    ${prospect.possession ? `
                    <div>
                        <span class="text-gray-500">Possession:</span>
                        <span class="font-medium text-gray-900 ml-2">${prospect.possession}</span>
                    </div>
                    ` : ''}
                    ${prospect.lead_score ? `
                    <div class="col-span-2">
                        <span class="text-gray-500">Lead Score:</span>
                        <span class="font-medium text-gray-900 ml-2">${renderStarRating(prospect.lead_score)} <span class="text-gray-500 text-xs">(${prospect.lead_score}/5)</span></span>
                    </div>
                    ` : ''}
                </div>
                ${prospect.remark || prospect.employee_remark ? `
                <div class="mt-3 p-3 bg-gray-50 rounded border border-gray-200">
                    <span class="text-xs font-medium text-gray-500 uppercase">Remark:</span>
                    <p class="text-sm text-gray-700 mt-1">${prospect.remark || prospect.employee_remark || 'N/A'}</p>
                </div>
                ` : ''}
            </div>
        `;
        
        modal.style.display = 'block';
    }

    function closeShortDetailModal() {
        document.getElementById('shortDetailModal').style.display = 'none';
    }

    // Close modal on outside click
    window.onclick = function(event) {
        const modal = document.getElementById('shortDetailModal');
        if (event.target === modal) {
            closeShortDetailModal();
        }
    }
</script>

<!-- Short Detail Modal -->
<div id="shortDetailModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4);">
    <div class="modal-content" style="background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 90%; max-width: 600px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #e0e0e0;">
            <h2 style="margin: 0; font-size: 20px; font-weight: 600; color: #063A1C;">Short Detail</h2>
            <button class="close-modal" onclick="closeShortDetailModal()" style="background: none; border: none; font-size: 28px; font-weight: bold; color: #aaa; cursor: pointer; padding: 0; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">&times;</button>
        </div>
        <div id="shortDetailContent" class="modal-body" style="max-height: 70vh; overflow-y: auto;">
            <!-- Content will be populated dynamically -->
        </div>
    </div>
</div>
@endpush

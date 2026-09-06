@extends('layouts.app')

@section('title', 'Prospects - ' . brand_name())
@section('page-title', 'Prospects')

@push('styles')
<style>
    #prospectsGrid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1.5rem;
    }
    
    @media (max-width: 1024px) {
        #prospectsGrid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    
    @media (max-width: 768px) {
        #prospectsGrid {
            grid-template-columns: 1fr;
        }
    }
    
    .prospect-card {
        transition: all 0.3s ease;
    }
    
    .prospect-card:hover {
        transform: translateY(-2px);
    }
</style>
@endpush

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
        <div class="flex flex-wrap items-center gap-4">
            <!-- Status Filter -->
            <div class="flex items-center gap-2">
                <label class="text-sm font-medium text-gray-700">Status:</label>
                <div class="flex gap-2">
                    <button onclick="setStatusFilter('all')" 
                           id="statusAll"
                           class="px-4 py-2 rounded-lg text-sm font-medium transition-colors bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white">
                        All (<span id="statusAllCount">0</span>)
                    </button>
                    <button onclick="setStatusFilter('verified')" 
                           id="statusVerified"
                           class="px-4 py-2 rounded-lg text-sm font-medium transition-colors bg-gray-100 text-gray-700 hover:bg-gray-200">
                        Verified (<span id="statusVerifiedCount">0</span>)
                    </button>
                </div>
            </div>

            <!-- Search -->
            <div class="flex-1 min-w-[200px]">
                <div class="flex gap-2">
                    <input type="text" 
                           id="searchInput"
                           placeholder="Search by name, phone, or project..."
                           class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                           onkeyup="handleSearch()">
                    <button onclick="loadProspects()" class="px-6 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg hover:from-[#205A44] hover:to-[#15803d] transition-colors duration-200 font-medium">
                        <i class="fas fa-search"></i>
                    </button>
                    <button onclick="clearSearch()" id="clearSearchBtn" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors duration-200" style="display: none;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-bold text-gray-900">Team Prospects</h2>
        </div>

        <!-- Loading State -->
        <div id="loadingState" class="text-center py-12">
            <i class="fas fa-spinner fa-spin text-gray-400 text-4xl mb-4"></i>
            <p class="text-gray-500">Loading prospects...</p>
        </div>

        <!-- Empty State -->
        <div id="emptyState" class="text-center py-12" style="display: none;">
            <i class="fas fa-star text-gray-300 text-6xl mb-4"></i>
            <h3 class="text-xl font-semibold text-gray-700 mb-2">No Prospects Found</h3>
            <p class="text-gray-500">No prospects match your current filters.</p>
        </div>

        <!-- Prospects Cards -->
        <div id="prospectsCards" style="display: none;">
            <div id="prospectsGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Prospects will be loaded here -->
            </div>
            
            <!-- Pagination -->
            <div id="pagination" class="mt-6 flex items-center justify-between">
                <!-- Pagination will be loaded here -->
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    const API_BASE_URL = '{{ url("/api/sales-manager") }}';
    const API_TOKEN = '{{ $api_token ?? "" }}';
    let currentProspectId = null;
    let searchTimeout = null;

    // Get auth headers with Bearer token
    function getAuthHeaders() {
        const token = window.getManagerApiToken ? window.getManagerApiToken() : API_TOKEN;
        if (!token) {
            window.location.href = '{{ route("login") }}';
            return {};
        }
        if (window.getManagerAuthHeaders) {
            return window.getManagerAuthHeaders({
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            });
        }
        return {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`,
        };
    }

    // Store all prospects data
    let allProspects = [];
    let currentStatusFilter = 'all';

    function setStatusFilter(status) {
        currentStatusFilter = status;
        updateStatusButtons();
        loadProspects(1);
    }

    function updateStatusButtons() {
        // Reset all status buttons
        ['All', 'Verified'].forEach(status => {
            const btn = document.getElementById(`status${status}`);
            if (btn) {
                btn.className = 'px-4 py-2 rounded-lg text-sm font-medium transition-colors bg-gray-100 text-gray-700 hover:bg-gray-200';
            }
        });
        
        // Set active button
        let activeStatus = 'All';
        if (currentStatusFilter === 'verified') activeStatus = 'Verified';
        
        const activeBtn = document.getElementById(`status${activeStatus}`);
        if (activeBtn) {
            activeBtn.className = 'px-4 py-2 rounded-lg text-sm font-medium transition-colors bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white';
        }
    }

    function handleSearch() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            loadProspects(1);
        }, 500);
        const searchInput = document.getElementById('searchInput');
        const clearBtn = document.getElementById('clearSearchBtn');
        if (searchInput && clearBtn) {
            clearBtn.style.display = searchInput.value ? 'block' : 'none';
        }
    }

    function clearSearch() {
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.value = '';
            document.getElementById('clearSearchBtn').style.display = 'none';
            loadProspects(1);
        }
    }

    // Load prospects
    async function loadProspects(page = 1) {
        const loadingState = document.getElementById('loadingState');
        const emptyState = document.getElementById('emptyState');
        const prospectsCards = document.getElementById('prospectsCards');
        const prospectsGrid = document.getElementById('prospectsGrid');
        
        loadingState.style.display = 'block';
        emptyState.style.display = 'none';
        prospectsCards.style.display = 'none';

        try {
            const search = document.getElementById('searchInput')?.value || '';
            
            const params = new URLSearchParams({
                page: page,
                per_page: 15,
            });
            
            if (currentStatusFilter && currentStatusFilter !== 'all') {
                params.append('verification_status', currentStatusFilter);
            }
            
            if (search) {
                params.append('search', search);
            }

            const response = await fetch(`${API_BASE_URL}/prospects?${params}`, {
                headers: getAuthHeaders(),
                credentials: 'same-origin',
            });

            if (!response.ok) {
                if (response.status === 401 || response.status === 403) {
                    if (window.handleManagerAuthFailure) {
                        window.handleManagerAuthFailure('prospects');
                    } else {
                        window.location.href = '{{ route("login") }}';
                    }
                    return;
                }
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.message || 'Failed to load prospects');
            }

            const data = await response.json();
            
            if (!data || !data.data) {
                throw new Error('Invalid response from server');
            }

            // Update counts if available
            if (data.counts) {
                document.getElementById('statusAllCount').textContent = data.counts.all || 0;
                document.getElementById('statusVerifiedCount').textContent = data.counts.verified || 0;
            }

            if (data.data && data.data.length > 0) {
                allProspects = data.data;
                prospectsGrid.innerHTML = '';
                data.data.forEach(prospect => {
                    const card = createProspectCard(prospect);
                    prospectsGrid.appendChild(card);
                });
                
                renderPagination(data);
                loadingState.style.display = 'none';
                prospectsCards.style.display = 'block';
                emptyState.style.display = 'none';
            } else {
                loadingState.style.display = 'none';
                prospectsCards.style.display = 'none';
                emptyState.style.display = 'block';
            }
        } catch (error) {
            console.error('Error loading prospects:', error);
            loadingState.style.display = 'none';
            emptyState.style.display = 'block';
            emptyState.innerHTML = `
                <i class="fas fa-exclamation-triangle text-red-400 text-6xl mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-700 mb-2">Error Loading Prospects</h3>
                <p class="text-gray-500">${error.message || 'Failed to load prospects. Please try again.'}</p>
            `;
        }
    }

    // Edit prospect
    function editProspect(id) {
        window.location.href = `/sales-manager/prospects/${id}/edit`;
    }

    // Delete prospect
    function deleteProspect(id, name) {
        if (!confirm(`"${name}" ko delete karo? Ye action undo nahi ho sakta.`)) return;
        fetch(`/sales-manager/prospects/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            }
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const card = document.getElementById(`prospect-card-${id}`);
                if (card) card.remove();
                // Count update karo
                loadProspects(currentStatus);
            } else {
                alert('Delete failed: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(e => alert('Error: ' + e.message));
    }

    function editProspect(id) {
        window.location.href = `/sales-manager/prospects/${id}/edit`;
    }

    function deleteProspect(id) {
        if (!confirm('Is prospect ko delete karo? Ye undo nahi hoga.')) return;
        fetch(`/sales-manager/prospects/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            }
        }).then(r => r.json()).then(data => {
            if (data.success) {
                document.getElementById(`prospect-card-${id}`)?.remove();
                loadProspects(currentStatus);
            } else {
                alert('Error: ' + (data.message || 'Delete failed'));
            }
        }).catch(e => alert('Error: ' + e.message));
    }

    // Create prospect card
    function createProspectCard(prospect) {
        const card = document.createElement('div');
        card.className = 'bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow border border-gray-200';
        card.id = `prospect-card-${prospect.id}`;
        
        const statusBadge = getStatusBadge(prospect.verification_status);
        const createdBy = prospect.telecaller ? prospect.telecaller.name : (prospect.created_by ? prospect.created_by.name : 'N/A');
        const createdAt = new Date(prospect.created_at).toLocaleDateString('en-IN', {
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        });

        card.innerHTML = `
            <div class="p-5">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex-1">
                        <h3 class="text-lg font-semibold text-gray-900 mb-1">${prospect.customer_name || 'N/A'}</h3>
                        <p class="text-sm text-gray-500">${createdBy}</p>
                    </div>
                    ${statusBadge}
                </div>
                
                <div class="space-y-2 mb-4">
                    <div class="flex items-center text-sm text-gray-600">
                        <i class="fas fa-phone w-5 text-gray-400"></i>
                        <span>${prospect.phone || 'N/A'}</span>
                    </div>
                    ${prospect.budget ? `
                    <div class="flex items-center text-sm text-gray-600">
                        <i class="fas fa-rupee-sign w-5 text-gray-400"></i>
                        <span>₹${parseFloat(prospect.budget).toLocaleString('en-IN')}</span>
                    </div>
                    ` : ''}
                    ${prospect.preferred_location ? `
                    <div class="flex items-center text-sm text-gray-600">
                        <i class="fas fa-map-marker-alt w-5 text-gray-400"></i>
                        <span>${prospect.preferred_location}</span>
                    </div>
                    ` : ''}
                    <div class="flex items-center text-sm text-gray-500">
                        <i class="fas fa-calendar w-5 text-gray-400"></i>
                        <span>${createdAt}</span>
                    </div>
                </div>

                <button 
                    onclick="toggleDetails(${prospect.id})" 
                    class="w-full mt-4 px-4 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition-colors text-sm font-medium"
                    id="viewDetailsBtn-${prospect.id}"
                >
                    <i class="fas fa-chevron-down mr-2" id="chevron-${prospect.id}"></i>
                    View Details
                </button>
            </div>
                <div style="display:flex;gap:8px;margin-top:8px;">
                    <button onclick="editProspect(${prospect.id})" style="flex:1;padding:8px;background:#f0fdf4;color:#065f46;border:1.5px solid #bbf7d0;border-radius:8px;font-size:12.5px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:5px;"><i class="fas fa-pen"></i> Edit</button>
                    <button onclick="deleteProspect(${prospect.id})" style="flex:1;padding:8px;background:#fff1f2;color:#be123c;border:1.5px solid #fecdd3;border-radius:8px;font-size:12.5px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:5px;"><i class="fas fa-trash"></i> Delete</button>
                </div>
            
            <!-- Expandable Details Section -->
            <div 
                id="details-${prospect.id}" 
                class="hidden border-t border-gray-200 bg-gray-50 p-5"
                style="transition: all 0.3s ease;"
            >
                <div class="space-y-3 mb-4">
                    <div class="grid grid-cols-2 gap-3 text-sm">
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
                
                <div class="flex gap-2 mt-4">
                    <button 
                        onclick="openWhatsApp('${prospect.phone || ''}')" 
                        class="flex-1 px-4 py-2 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-lg hover:from-green-700 hover:to-green-800 transition-all duration-200 text-sm font-medium shadow-md"
                    >
                        <i class="fab fa-whatsapp mr-2"></i>
                        WhatsApp
                    </button>
                </div>
            </div>
        `;
        
        return card;
    }

    // Get status badge
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
    
    function getStatusBadge(status) {
        const badges = {
            'verified': '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Verified</span>',
            'approved': '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Verified</span>',
            'pending_verification': '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Verified</span>',
            'pending': '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Verified</span>',
            'rejected': '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Verified</span>',
        };
        return badges[status] || '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">' + status + '</span>';
    }

    // Render pagination
    function renderPagination(data) {
        const pagination = document.getElementById('pagination');
        if (data.last_page <= 1) {
            pagination.innerHTML = '';
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
        
        pagination.innerHTML = html;
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

    // Open Verify Modal
    async function openVerifyModal(prospectId) {
        currentProspectId = prospectId;
        const modal = document.getElementById('verifyModal');
        const content = document.getElementById('verifyModalContent');
        
        const prospect = allProspects.find(p => p.id === prospectId);
        if (!prospect) {
            // Try to load from API if not in current page
            try {
                const response = await fetch(`${API_BASE_URL}/prospects/pending?per_page=1000`, {
                    headers: getAuthHeaders(),
                });
                const data = await response.json();
                const found = data.data.find(p => p.id === prospectId);
                if (found) {
                    displayVerifyModal(found, content, modal);
                } else {
                    alert('Prospect not found');
                }
            } catch (error) {
                console.error('Error loading prospect:', error);
                alert('Failed to load prospect details');
            }
        } else {
            displayVerifyModal(prospect, content, modal);
        }
    }

    function displayVerifyModal(prospect, content, modal) {
        content.innerHTML = `
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-gray-500">Customer Name:</span>
                        <p class="font-medium text-gray-900 mt-1">${prospect.customer_name || 'N/A'}</p>
                    </div>
                    <div>
                        <span class="text-gray-500">Phone:</span>
                        <p class="font-medium text-gray-900 mt-1">${prospect.phone || 'N/A'}</p>
                    </div>
                    <div>
                        <span class="text-gray-500">Budget:</span>
                        <p class="font-medium text-gray-900 mt-1">${prospect.budget ? '₹' + parseFloat(prospect.budget).toLocaleString('en-IN') : 'N/A'}</p>
                    </div>
                    <div>
                        <span class="text-gray-500">Location:</span>
                        <p class="font-medium text-gray-900 mt-1">${prospect.preferred_location || 'N/A'}</p>
                    </div>
                    <div>
                        <span class="text-gray-500">Size:</span>
                        <p class="font-medium text-gray-900 mt-1">${prospect.size || 'N/A'}</p>
                    </div>
                    <div>
                        <span class="text-gray-500">Purpose:</span>
                        <p class="font-medium text-gray-900 mt-1">${prospect.purpose === 'end_user' ? 'End User' : (prospect.purpose === 'investment' ? 'Investment' : 'N/A')}</p>
                    </div>
                </div>
                ${prospect.remark ? `
                <div class="p-3 bg-gray-50 rounded border">
                    <span class="text-xs font-medium text-gray-500 uppercase">Remark:</span>
                    <p class="text-sm text-gray-700 mt-1">${prospect.remark}</p>
                </div>
                ` : ''}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Manager Remark *</label>
                    <textarea 
                        id="managerRemark" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" 
                        rows="4" 
                        placeholder="Enter your remarks about this prospect..."
                        required
                    ></textarea>
                    <p class="text-xs text-gray-500 mt-1">Add any additional details or comments about this prospect.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Lead Status *</label>
                    <select 
                        id="leadStatus" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        required
                    >
                        <option value="">Select Lead Status</option>
                        <option value="hot">Hot</option>
                        <option value="warm">Warm</option>
                        <option value="cold">Cold</option>
                        <option value="junk">Junk</option>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Classify this lead based on their interest level.</p>
                </div>
            </div>
        `;
        modal.classList.remove('hidden');
    }

    // Close Verify Modal
    function closeVerifyModal() {
        document.getElementById('verifyModal').classList.add('hidden');
        currentProspectId = null;
    }

    // Submit Verify
    async function submitVerify() {
        if (!currentProspectId) return;
        
        const managerRemark = document.getElementById('managerRemark').value.trim();
        const leadStatus = document.getElementById('leadStatus').value;
        
        if (!managerRemark) {
            alert('Please enter a manager remark');
            return;
        }
        
        if (!leadStatus) {
            alert('Please select a lead status');
            return;
        }
        
        try {
            const response = await fetch(`${API_BASE_URL}/prospects/${currentProspectId}/verify`, {
                method: 'POST',
                headers: getAuthHeaders(),
                body: JSON.stringify({
                    manager_remark: managerRemark,
                    lead_status: leadStatus
                }),
            });

            const data = await response.json();
            
            if (data.success) {
                if (typeof showNotification === 'function') {
                    showNotification('Prospect verified successfully!', 'success', 3000);
                } else {
                    alert('Prospect verified successfully!');
                }
                closeVerifyModal();
                loadProspects();
            } else {
                alert(data.message || 'Failed to verify prospect');
            }
        } catch (error) {
            console.error('Error verifying prospect:', error);
            alert('Failed to verify prospect. Please try again.');
        }
    }

    // Open Reject Modal
    async function openRejectModal(prospectId) {
        currentProspectId = prospectId;
        const modal = document.getElementById('rejectModal');
        const content = document.getElementById('rejectModalContent');
        
        const prospect = allProspects.find(p => p.id === prospectId);
        if (!prospect) {
            try {
                const response = await fetch(`${API_BASE_URL}/prospects/pending?per_page=1000`, {
                    headers: getAuthHeaders(),
                });
                const data = await response.json();
                const found = data.data.find(p => p.id === prospectId);
                if (found) {
                    displayRejectModal(found, content, modal);
                } else {
                    alert('Prospect not found');
                }
            } catch (error) {
                console.error('Error loading prospect:', error);
                alert('Failed to load prospect details');
            }
        } else {
            displayRejectModal(prospect, content, modal);
        }
    }

    function displayRejectModal(prospect, content, modal) {
        content.innerHTML = `
            <div class="space-y-4">
                <div class="p-3 bg-yellow-50 border border-yellow-200 rounded">
                    <p class="text-sm text-yellow-800">
                        <strong>Prospect:</strong> ${prospect.customer_name || 'N/A'}<br>
                        <strong>Phone:</strong> ${prospect.phone || 'N/A'}
                    </p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Rejection Reason *</label>
                    <textarea 
                        id="rejectReason" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500" 
                        rows="4" 
                        placeholder="Enter the reason for rejecting this prospect..."
                        required
                    ></textarea>
                    <p class="text-xs text-gray-500 mt-1">This reason will be shown to the sales executive.</p>
                </div>
            </div>
        `;
        modal.classList.remove('hidden');
    }

    // Close Reject Modal
    function closeRejectModal() {
        document.getElementById('rejectModal').classList.add('hidden');
        currentProspectId = null;
    }

    // Submit Reject
    async function submitReject() {
        if (!currentProspectId) return;
        
        const reason = document.getElementById('rejectReason').value.trim();
        if (!reason) {
            alert('Please enter a rejection reason');
            return;
        }
        
        if (reason.length < 10) {
            alert('Please provide a more detailed reason (at least 10 characters)');
            return;
        }
        
        try {
            const response = await fetch(`${API_BASE_URL}/prospects/${currentProspectId}/reject`, {
                method: 'POST',
                headers: getAuthHeaders(),
                body: JSON.stringify({
                    reason: reason
                }),
            });

            const data = await response.json();
            
            if (data.success) {
                if (typeof showNotification === 'function') {
                    showNotification('Prospect rejected successfully!', 'success', 3000);
                } else {
                    alert('Prospect rejected successfully!');
                }
                closeRejectModal();
                loadProspects();
            } else {
                alert(data.message || 'Failed to reject prospect');
            }
        } catch (error) {
            console.error('Error rejecting prospect:', error);
            alert('Failed to reject prospect. Please try again.');
        }
    }

    // Load prospects on page load
    document.addEventListener('DOMContentLoaded', function() {
        updateStatusButtons();
        loadProspects();
    });
</script>
@endpush

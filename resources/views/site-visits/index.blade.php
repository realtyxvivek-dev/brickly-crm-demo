@extends('layouts.app')

@section('title', 'Site Visits - ' . brand_name())
@section('page-title', 'Site Visits')

@push('styles')
<style>
    .visit-card {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        margin-bottom: 16px;
        border-left: 4px solid #3b82f6;
    }
    .visit-card.completed {
        border-left-color: #10b981;
    }
    .visit-card.cancelled {
        border-left-color: #ef4444;
    }
    .visit-card.pending-verification {
        border-left-color: #f59e0b;
    }
    .visit-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 12px;
    }
    .visit-info h3 {
        font-size: 18px;
        font-weight: 600;
        color: #063A1C;
        margin-bottom: 8px;
    }
    .visit-info p {
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
    .badge-scheduled {
        background: #dbeafe;
        color: #1e40af;
    }
    .badge-completed {
        background: #d1fae5;
        color: #065f46;
    }
    .badge-cancelled {
        background: #fee2e2;
        color: #991b1b;
    }
    .badge-pending {
        background: #fef3c7;
        color: #92400e;
    }
    .badge-verified {
        background: #d1fae5;
        color: #065f46;
    }
    .badge-closer-pending {
        background: #fef3c7;
        color: #92400e;
    }
    .badge-closer-verified {
        background: #10b981;
        color: white;
    }
    .btn {
        padding: 8px 16px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-block;
        margin-left: 8px;
    }
    .btn-primary {
        background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
        color: white;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        background: #205A44;
        color: white;
    }
    .btn-success {
        background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
        color: white;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        background: #10b981;
        color: white;
    }
    .btn-danger {
        background: #ef4444;
        color: white;
    }
    .btn:hover {
        opacity: 0.9;
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
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 class="text-2xl font-bold" style="color: #063A1C;">Site Visits</h2>
        <a href="{{ route('site-visits.create') }}" class="btn btn-primary">
            <i class="fas fa-plus mr-2"></i>Schedule Site Visit
        </a>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
        <div class="flex flex-wrap items-center gap-4">
            <!-- Status Filter -->
            <div class="flex items-center gap-2">
                <label class="text-sm font-medium text-gray-700">Status:</label>
                <div class="flex gap-2">
                    <button onclick="setStatusFilter('')" 
                           id="statusAll"
                           class="px-4 py-2 rounded-lg text-sm font-medium transition-colors bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white">
                        All (<span id="statusAllCount">0</span>)
                    </button>
                    <button onclick="setStatusFilter('scheduled')" 
                           id="statusScheduled"
                           class="px-4 py-2 rounded-lg text-sm font-medium transition-colors bg-gray-100 text-gray-700 hover:bg-gray-200">
                        Scheduled (<span id="statusScheduledCount">0</span>)
                    </button>
                    <button onclick="setStatusFilter('completed')" 
                           id="statusCompleted"
                           class="px-4 py-2 rounded-lg text-sm font-medium transition-colors bg-gray-100 text-gray-700 hover:bg-gray-200">
                        Completed (<span id="statusCompletedCount">0</span>)
                    </button>
                    <button onclick="setStatusFilter('pending')"
                           id="statusPending"
                           class="px-4 py-2 rounded-lg text-sm font-medium transition-colors bg-gray-100 text-gray-700 hover:bg-gray-200">
                        Pending
                    </button>
                    <button onclick="setStatusFilter('cancelled')" 
                           id="statusCancelled"
                           class="px-4 py-2 rounded-lg text-sm font-medium transition-colors bg-gray-100 text-gray-700 hover:bg-gray-200">
                        Cancelled (<span id="statusCancelledCount">0</span>)
                    </button>
                </div>
            </div>

            <!-- Verification Filter -->
            <div class="flex items-center gap-2">
                <label class="text-sm font-medium text-gray-700">Verification:</label>
                <div class="flex gap-2">
                    <button onclick="setVerificationFilter('')" 
                           id="verificationAll"
                           class="px-4 py-2 rounded-lg text-sm font-medium transition-colors bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white">
                        All (<span id="verificationAllCount">0</span>)
                    </button>
                    <button onclick="setVerificationFilter('pending')" 
                           id="verificationPending"
                           class="px-4 py-2 rounded-lg text-sm font-medium transition-colors bg-gray-100 text-gray-700 hover:bg-gray-200">
                        Pending (<span id="verificationPendingCount">0</span>)
                    </button>
                    <button onclick="setVerificationFilter('verified')" 
                           id="verificationVerified"
                           class="px-4 py-2 rounded-lg text-sm font-medium transition-colors bg-gray-100 text-gray-700 hover:bg-gray-200">
                        Verified (<span id="verificationVerifiedCount">0</span>)
                    </button>
                    <button onclick="setVerificationFilter('rejected')" 
                           id="verificationRejected"
                           class="px-4 py-2 rounded-lg text-sm font-medium transition-colors bg-gray-100 text-gray-700 hover:bg-gray-200">
                        Rejected (<span id="verificationRejectedCount">0</span>)
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
                    <button onclick="loadSiteVisits()" class="px-6 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg hover:from-[#205A44] hover:to-[#15803d] transition-colors duration-200 font-medium">
                        <i class="fas fa-search"></i>
                    </button>
                    <button onclick="clearSearch()" id="clearSearchBtn" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors duration-200" style="display: none;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="visitsContainer">
        <div class="empty-state">
            <i class="fas fa-spinner fa-spin"></i>
            <p>Loading site visits...</p>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const API_BASE_URL = '{{ url("/api/sales-manager") }}';
    
    function getToken() {
        return typeof window.getManagerApiToken === 'function' ? window.getManagerApiToken() : '{{ $api_token ?? session("api_token") ?? "" }}';
    }

    async function apiCall(endpoint, options = {}) {
        const token = getToken();
        if (!token) {
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
            const response = await fetch(`${API_BASE_URL}${endpoint}`, {
                ...defaultOptions,
                ...options,
                headers: { ...defaultOptions.headers, ...options.headers },
                credentials: 'same-origin',
            });

            if (response.status === 401 || response.status === 403) {
                window.handleManagerAuthFailure('site visits');
                window.location.href = '{{ route("login") }}';
                return null;
            }

            if (!response.ok) {
                const errorText = await response.text();
                try {
                    return JSON.parse(errorText);
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

    const dashboardQuery = new URLSearchParams(window.location.search);
    let currentStatusFilter = dashboardQuery.get('status') || (dashboardQuery.get('activity_state') === 'pending' ? 'pending' : '');
    const dashboardDateFilter = dashboardQuery.get('date_filter') || '';
    const dashboardDateFrom = dashboardQuery.get('date_from') || '';
    const dashboardDateTo = dashboardQuery.get('date_to') || '';
    let currentVerificationFilter = '';
    let searchTimeout = null;

    function setStatusFilter(status) {
        currentStatusFilter = status;
        updateStatusButtons();
        loadSiteVisits();
    }

    function setVerificationFilter(verification) {
        currentVerificationFilter = verification;
        updateVerificationButtons();
        loadSiteVisits();
    }

    function updateStatusButtons() {
        ['All', 'Scheduled', 'Completed', 'Pending', 'Cancelled'].forEach(status => {
            const btn = document.getElementById(`status${status}`);
            if (btn) {
                btn.className = 'px-4 py-2 rounded-lg text-sm font-medium transition-colors bg-gray-100 text-gray-700 hover:bg-gray-200';
            }
        });
        const activeStatus = currentStatusFilter ? currentStatusFilter.charAt(0).toUpperCase() + currentStatusFilter.slice(1) : 'All';
        const activeBtn = document.getElementById(`status${activeStatus}`);
        if (activeBtn) {
            activeBtn.className = 'px-4 py-2 rounded-lg text-sm font-medium transition-colors bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white';
        }
    }

    function updateVerificationButtons() {
        ['All', 'Pending', 'Verified', 'Rejected'].forEach(verification => {
            const btn = document.getElementById(`verification${verification}`);
            if (btn) {
                btn.className = 'px-4 py-2 rounded-lg text-sm font-medium transition-colors bg-gray-100 text-gray-700 hover:bg-gray-200';
            }
        });
        const activeVerification = currentVerificationFilter ? currentVerificationFilter.charAt(0).toUpperCase() + currentVerificationFilter.slice(1) : 'All';
        const activeBtn = document.getElementById(`verification${activeVerification}`);
        if (activeBtn) {
            activeBtn.className = 'px-4 py-2 rounded-lg text-sm font-medium transition-colors bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white';
        }
    }

    function handleSearch() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            loadSiteVisits();
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
            loadSiteVisits();
        }
    }

    async function loadSiteVisits() {
        const container = document.getElementById('visitsContainer');
        container.innerHTML = '<div class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>Loading...</p></div>';

        try {
            const search = document.getElementById('searchInput')?.value || '';
            
            let url = '/site-visits?';
            if (currentStatusFilter === 'pending') url += 'activity_state=pending&';
            else if (currentStatusFilter) url += `status=${currentStatusFilter}&`;
            if (currentVerificationFilter) url += `verification_status=${currentVerificationFilter}&`;
            if (search) url += `search=${encodeURIComponent(search)}&`;
            if (dashboardDateFilter) url += `date_filter=${encodeURIComponent(dashboardDateFilter)}&`;
            if (dashboardDateFrom) url += `date_from=${encodeURIComponent(dashboardDateFrom)}&`;
            if (dashboardDateTo) url += `date_to=${encodeURIComponent(dashboardDateTo)}&`;

            const response = await apiCall(url);
            const visits = response?.data || [];

            if (visits.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-map-marker-alt"></i>
                        <h3 style="font-size: 18px; font-weight: 600; color: #333; margin: 16px 0 8px;">No Site Visits Found</h3>
                        <p>Schedule your first site visit to get started.</p>
                    </div>
                `;
                return;
            }

            const html = visits.map(visit => {
                const statusClass = visit.status === 'completed' ? 'completed' : 
                                  visit.status === 'cancelled' ? 'cancelled' : 
                                  visit.verification_status === 'pending' && visit.status === 'completed' ? 'pending-verification' : '';
                
                const statusBadge = visit.status === 'scheduled' ? 'badge-scheduled' :
                                  visit.status === 'completed' ? 'badge-completed' :
                                  'badge-cancelled';
                
                const verificationBadge = visit.verification_status === 'verified' ? 'badge-verified' :
                                        visit.verification_status === 'pending' ? 'badge-pending' : '';

                return `
                    <div class="visit-card ${statusClass}">
                        <div class="visit-header">
                            <div class="visit-info">
                                <h3>${visit.customer_name || visit.property_name || 'N/A'}</h3>
                                <p><i class="fas fa-phone mr-2"></i>${visit.phone || 'N/A'}</p>
                                <p><i class="fas fa-calendar mr-2"></i>Scheduled: ${new Date(visit.scheduled_at).toLocaleString()}</p>
                                ${visit.completed_at ? `<p><i class="fas fa-check-circle mr-2"></i>Completed: ${new Date(visit.completed_at).toLocaleString()}</p>` : ''}
                                <p><i class="fas fa-tag mr-2"></i>Budget: ${visit.budget_range || 'N/A'}</p>
                                <p><i class="fas fa-building mr-2"></i>Property: ${visit.property_type || 'N/A'}</p>
                                <div style="margin-top: 8px;">
                                    <span class="badge ${statusBadge}">${visit.status}</span>
                                    ${visit.verification_status ? `<span class="badge ${verificationBadge}">${visit.verification_status}</span>` : ''}
                                </div>
                            </div>
                            <div>
                                ${visit.status === 'scheduled' ? `
                                    <button class="btn btn-success" onclick="showCompleteSiteVisitModal(${visit.id})">
                                        <i class="fas fa-check mr-2"></i>Complete
                                    </button>
                                    <button class="btn" style="background: #f59e0b; color: white; margin-top: 8px;" onclick="showRescheduleSiteVisitModal(${visit.id})">
                                        <i class="fas fa-calendar-alt mr-2"></i>Reschedule
                                    </button>
                                    <button class="btn btn-danger" onclick="showMarkDeadModal('site-visit', ${visit.id})" style="margin-top: 8px;">
                                        <i class="fas fa-skull mr-2"></i>Mark as Dead
                                    </button>
                                ` : ''}
                                ${visit.status === 'completed' && visit.verification_status === 'pending' ? `
                                    <span class="badge badge-pending">Awaiting Verification</span>
                                ` : ''}
                                ${visit.status === 'completed' ? `
                                    <button class="btn btn-danger" onclick="showMarkDeadModal('site-visit', ${visit.id})" style="margin-top: 8px;">
                                        <i class="fas fa-skull mr-2"></i>Mark as Dead
                                    </button>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                `;
            }).join('');

            container.innerHTML = html;
        } catch (error) {
            console.error('Error loading site visits:', error);
            container.innerHTML = '<div class="empty-state"><i class="fas fa-exclamation-triangle"></i><p>Error loading site visits</p></div>';
        }
    }

    let currentSiteVisitId = null;

    function showCompleteSiteVisitModal(id) {
        currentSiteVisitId = id;
        document.getElementById('completeSiteVisitModal').classList.add('show');
    }

    function closeCompleteSiteVisitModal() {
        document.getElementById('completeSiteVisitModal').classList.remove('show');
        document.getElementById('siteVisitProofPhotosInput').value = '';
        document.getElementById('siteVisitProofPhotosPreview').innerHTML = '';
        document.querySelectorAll('.siteVisitPropertyType').forEach((input) => {
            input.checked = false;
        });
        currentSiteVisitId = null;
    }

    function handleSiteVisitProofPhotosChange(event) {
        const files = event.target.files;
        const preview = document.getElementById('siteVisitProofPhotosPreview');
        preview.innerHTML = '';
        
        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.createElement('img');
                img.src = e.target.result;
                img.style.width = '100px';
                img.style.height = '100px';
                img.style.objectFit = 'cover';
                img.style.borderRadius = '8px';
                img.style.margin = '5px';
                preview.appendChild(img);
            };
            reader.readAsDataURL(file);
        }
    }

    async function submitCompleteSiteVisit() {
        if (!currentSiteVisitId) return;

        const formData = new FormData();
        const photosInput = document.getElementById('siteVisitProofPhotosInput');
        
        if (!photosInput.files || photosInput.files.length === 0) {
            alert('Please upload at least one proof photo');
            return;
        }

        for (let i = 0; i < photosInput.files.length; i++) {
            formData.append('proof_photos[]', photosInput.files[i]);
        }

        const feedback = document.getElementById('siteVisitFeedback').value;
        const rating = document.getElementById('siteVisitRating').value;
        const notes = document.getElementById('siteVisitNotes').value;
        const visitedPropertyTypes = Array.from(document.querySelectorAll('.siteVisitPropertyType:checked'))
            .map((input) => input.value);

        if (feedback) formData.append('feedback', feedback);
        if (rating) formData.append('rating', rating);
        if (notes) formData.append('visit_notes', notes);
        visitedPropertyTypes.forEach((type) => formData.append('visited_property_types[]', type));

        try {
            const token = getToken();
            const response = await fetch(`${API_BASE_URL}/site-visits/${currentSiteVisitId}/complete`, {
                method: 'POST',
                headers: window.getManagerAuthHeaders({
                    'Authorization': `Bearer ${token}`,
                }),
                body: formData,
            });

            const result = await response.json();

            if (result && result.success) {
                alert('Site visit completed with proof photos! Awaiting verification.');
                closeCompleteSiteVisitModal();
                loadSiteVisits();
            } else {
                alert(result.message || 'Failed to complete site visit');
                if (result.errors) {
                    console.error('Validation errors:', result.errors);
                }
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Network error. Please try again.');
        }
    }

    function showMarkDeadModal(type, id) {
        currentSiteVisitId = id;
        document.getElementById('deadReason').value = '';
        document.getElementById('markDeadModal').classList.add('show');
    }

    function closeMarkDeadModal() {
        document.getElementById('markDeadModal').classList.remove('show');
        document.getElementById('deadReason').value = '';
        currentSiteVisitId = null;
    }

    async function submitMarkDead() {
        if (!currentSiteVisitId) return;

        const reason = document.getElementById('deadReason').value.trim();
        if (!reason) {
            alert('Please provide a reason for marking as dead');
            return;
        }

        const result = await apiCall(`/site-visits/${currentSiteVisitId}/mark-dead`, {
            method: 'POST',
            body: JSON.stringify({ reason }),
        });

        if (result && result.success) {
            alert('Site visit marked as dead successfully');
            closeMarkDeadModal();
            loadSiteVisits();
        } else {
            alert(result.message || 'Failed to mark as dead');
        }
    }

    function showRescheduleSiteVisitModal(id) {
        currentSiteVisitId = id;
        const minDateTime = new Date();
        minDateTime.setDate(minDateTime.getDate() + 1);
        minDateTime.setHours(0, 0, 0, 0);
        
        document.getElementById('rescheduleSiteVisitScheduledAt').value = '';
        document.getElementById('rescheduleSiteVisitReason').value = '';
        document.getElementById('rescheduleSiteVisitModalTitle').textContent = 'Reschedule Site Visit';
        document.getElementById('rescheduleSiteVisitModalType').value = 'site-visit';
        document.getElementById('rescheduleSiteVisitModalId').value = id;
        document.getElementById('rescheduleSiteVisitScheduledAt').min = minDateTime.toISOString().slice(0, 16);
        document.getElementById('rescheduleSiteVisitModal').classList.add('show');
    }

    function closeRescheduleSiteVisitModal() {
        document.getElementById('rescheduleSiteVisitModal').classList.remove('show');
        document.getElementById('rescheduleSiteVisitScheduledAt').value = '';
        document.getElementById('rescheduleSiteVisitReason').value = '';
        currentSiteVisitId = null;
    }

    async function submitRescheduleSiteVisit() {
        const type = document.getElementById('rescheduleSiteVisitModalType').value;
        const id = document.getElementById('rescheduleSiteVisitModalId').value;
        const scheduledAt = document.getElementById('rescheduleSiteVisitScheduledAt').value;
        const reason = document.getElementById('rescheduleSiteVisitReason').value.trim();

        if (!scheduledAt) {
            alert('Please select a new scheduled date and time');
            return;
        }

        if (!reason) {
            alert('Please provide a reason for rescheduling');
            return;
        }

        try {
            const result = await apiCall(`/${type === 'meeting' ? 'meetings' : 'site-visits'}/${id}/reschedule`, {
                method: 'POST',
                body: JSON.stringify({
                    scheduled_at: scheduledAt,
                    reason: reason,
                }),
            });

            if (result && result.success) {
                if (typeof showNotification === 'function') {
                    showNotification(result.message || 'Rescheduled successfully! Verification required.', 'success', 3000);
                } else {
                    alert(result.message || 'Rescheduled successfully! Verification required.');
                }
                closeRescheduleSiteVisitModal();
                loadSiteVisits();
            } else {
                alert(result.message || 'Failed to reschedule');
                if (result.errors) {
                    console.error('Validation errors:', result.errors);
                }
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Network error. Please try again.');
        }
    }

    // Initialize
    (function() {
        updateStatusButtons();
        updateVerificationButtons();
        loadSiteVisits();
    })();
</script>

<!-- Complete Site Visit Modal -->
<div id="completeSiteVisitModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <h3 style="font-size: 20px; font-weight: 600; margin-bottom: 20px;">Complete Site Visit</h3>
        <p style="color: #ef4444; margin-bottom: 16px;"><strong>Proof photos are required to complete the site visit.</strong></p>
        
        <div class="form-group">
            <label>Proof Photos <span style="color: #ef4444;">*</span></label>
            <input type="file" id="siteVisitProofPhotosInput" multiple accept="image/*" onchange="handleSiteVisitProofPhotosChange(event)" required>
            <div id="siteVisitProofPhotosPreview" style="display: flex; flex-wrap: wrap; margin-top: 10px;"></div>
            <small style="color: #6b7280;">Upload at least one photo as proof. Max 5MB per image.</small>
        </div>

        <div class="form-group">
            <label>Feedback</label>
            <textarea id="siteVisitFeedback" rows="3" placeholder="Site visit feedback..."></textarea>
        </div>

        <div class="form-group">
            <label>Rating</label>
            <select id="siteVisitRating">
                <option value="">Select rating</option>
                <option value="1">1 - Poor</option>
                <option value="2">2 - Fair</option>
                <option value="3">3 - Good</option>
                <option value="4">4 - Very Good</option>
                <option value="5">5 - Excellent</option>
            </select>
        </div>

        <div class="form-group">
            <label>Notes</label>
            <textarea id="siteVisitNotes" rows="3" placeholder="Additional notes..."></textarea>
        </div>

        <div class="form-group">
            <label>Property Type</label>
            <div class="site-visit-property-type-grid">
                <label><input type="checkbox" class="siteVisitPropertyType" value="plot"> Plot</label>
                <label><input type="checkbox" class="siteVisitPropertyType" value="villa"> Villa</label>
                <label><input type="checkbox" class="siteVisitPropertyType" value="apartment"> Apartments</label>
                <label><input type="checkbox" class="siteVisitPropertyType" value="commercial"> Commercial</label>
                <label><input type="checkbox" class="siteVisitPropertyType" value="other"> Other</label>
            </div>
            <small style="color: #6b7280;">Multiple property types select kar sakte hain.</small>
        </div>

        <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
            <button type="button" class="btn btn-secondary" onclick="closeCompleteSiteVisitModal()">Cancel</button>
            <button type="button" class="btn btn-success" onclick="submitCompleteSiteVisit()">Submit</button>
        </div>
    </div>
</div>

<!-- Reschedule Site Visit Modal -->
<div id="rescheduleSiteVisitModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <h3 id="rescheduleSiteVisitModalTitle" style="font-size: 20px; font-weight: 600; margin-bottom: 20px;">Reschedule Site Visit</h3>
        <input type="hidden" id="rescheduleSiteVisitModalType" value="site-visit">
        <input type="hidden" id="rescheduleSiteVisitModalId" value="">
        
        <div class="form-group">
            <label>New Scheduled Date & Time <span style="color: #ef4444;">*</span></label>
            <input type="datetime-local" id="rescheduleSiteVisitScheduledAt" required
                style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px;">
            <small style="color: #6b7280;">Select a future date and time</small>
        </div>

        <div class="form-group">
            <label>Reason for Rescheduling <span style="color: #ef4444;">*</span></label>
            <textarea id="rescheduleSiteVisitReason" rows="4" placeholder="Enter reason for rescheduling..." required
                style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px;"></textarea>
        </div>

        <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
            <button type="button" class="btn btn-secondary" onclick="closeRescheduleSiteVisitModal()">Cancel</button>
            <button type="button" class="btn" style="background: #f59e0b; color: white;" onclick="submitRescheduleSiteVisit()">Reschedule</button>
        </div>
    </div>
</div>

<!-- Mark as Dead Modal -->
<div id="markDeadModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <h3 style="font-size: 20px; font-weight: 600; margin-bottom: 20px;">Mark as Dead</h3>
        <p style="color: #ef4444; margin-bottom: 16px;">This will mark the site visit and associated lead as dead. This action cannot be undone.</p>
        
        <div class="form-group">
            <label>Reason <span style="color: #ef4444;">*</span></label>
            <textarea id="deadReason" rows="4" placeholder="Enter reason for marking as dead..." required></textarea>
        </div>

        <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
            <button type="button" class="btn btn-secondary" onclick="closeMarkDeadModal()">Cancel</button>
            <button type="button" class="btn btn-danger" onclick="submitMarkDead()">Mark as Dead</button>
        </div>
    </div>
</div>

<style>
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}
.modal.show {
    display: flex;
}
.modal-content {
    background: white;
    padding: 24px;
    border-radius: 12px;
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
}
.form-group {
    margin-bottom: 16px;
}
.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #333;
}
.form-group input[type="file"],
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 12px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 14px;
}
.site-visit-property-type-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding: 10px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    background: #fff;
}
.site-visit-property-type-grid label {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin: 0;
    padding: 8px 11px;
    border: 1px solid #d7e5de;
    border-radius: 999px;
    background: #f6fffa;
    color: #143d2b;
    font-size: 13px;
}
.btn-secondary {
    background: #6b7280;
    color: white;
}
.btn-secondary:hover {
    background: #4b5563;
}
</style>
@endpush

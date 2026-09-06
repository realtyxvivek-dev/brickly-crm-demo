@extends('layouts.app')

@section('title', 'Meetings - ' . brand_name())
@section('page-title', 'Meetings')

@push('styles')
<style>
    .meeting-card {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        margin-bottom: 16px;
        border-left: 4px solid #3b82f6;
    }
    .meeting-card.completed {
        border-left-color: #10b981;
    }
    .meeting-card.cancelled {
        border-left-color: #ef4444;
    }
    .meeting-card.pending-verification {
        border-left-color: #f59e0b;
    }
    .meeting-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 12px;
    }
    .meeting-info h3 {
        font-size: 18px;
        font-weight: 600;
        color: #063A1C;
        margin-bottom: 8px;
    }
    .meeting-info p {
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
    }
    .btn-primary {
        background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
        color: white;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    .btn-primary:hover {
        background: linear-gradient(135deg, #15803d 0%, #166534 100%);
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }
    .btn-success {
        background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
        color: white;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    .btn-success:hover {
        background: linear-gradient(135deg, #15803d 0%, #166534 100%);
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }
    .btn-danger {
        background: #ef4444;
        color: white;
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
        gap: 12px;
        flex-wrap: wrap;
    }
    .filter-select {
        padding: 8px 12px;
        border: 2px solid #e0e0e0;
        border-radius: 6px;
        font-size: 14px;
    }
</style>
@endpush

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="mb-6">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 class="text-2xl font-bold text-gray-900">My Meetings</h2>
            <a href="{{ route('meetings.create') }}" class="btn btn-primary">
                <i class="fas fa-plus mr-2"></i>Schedule New Meeting
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
                        <button onclick="loadMeetings()" class="px-6 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg hover:from-[#205A44] hover:to-[#15803d] transition-colors duration-200 font-medium">
                            <i class="fas fa-search"></i>
                        </button>
                        <button onclick="clearSearch()" id="clearSearchBtn" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors duration-200" style="display: none;">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div id="meetingsContainer">
            <div class="empty-state">
                <i class="fas fa-spinner fa-spin"></i>
                <p>Loading meetings...</p>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const API_BASE_URL = '{{ url("/api/sales-manager") }}';
    const canVerify = {{ (auth()->user()->isAdmin() || auth()->user()->isCrm() || auth()->user()->isSalesHead() || auth()->user()->isSalesManager()) ? 'true' : 'false' }};
    
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
                window.handleManagerAuthFailure('meetings');
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
        loadMeetings();
    }

    function setVerificationFilter(verification) {
        currentVerificationFilter = verification;
        updateVerificationButtons();
        loadMeetings();
    }

    function updateStatusButtons() {
        // Reset all status buttons
        ['All', 'Scheduled', 'Completed', 'Pending', 'Cancelled'].forEach(status => {
            const btn = document.getElementById(`status${status}`);
            if (btn) {
                btn.className = 'px-4 py-2 rounded-lg text-sm font-medium transition-colors bg-gray-100 text-gray-700 hover:bg-gray-200';
            }
        });
        
        // Set active button
        const activeStatus = currentStatusFilter ? currentStatusFilter.charAt(0).toUpperCase() + currentStatusFilter.slice(1) : 'All';
        const activeBtn = document.getElementById(`status${activeStatus}`);
        if (activeBtn) {
            activeBtn.className = 'px-4 py-2 rounded-lg text-sm font-medium transition-colors bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white';
        }
    }

    function updateVerificationButtons() {
        // Reset all verification buttons
        ['All', 'Pending', 'Verified', 'Rejected'].forEach(verification => {
            const btn = document.getElementById(`verification${verification}`);
            if (btn) {
                btn.className = 'px-4 py-2 rounded-lg text-sm font-medium transition-colors bg-gray-100 text-gray-700 hover:bg-gray-200';
            }
        });
        
        // Set active button
        const activeVerification = currentVerificationFilter ? currentVerificationFilter.charAt(0).toUpperCase() + currentVerificationFilter.slice(1) : 'All';
        const activeBtn = document.getElementById(`verification${activeVerification}`);
        if (activeBtn) {
            activeBtn.className = 'px-4 py-2 rounded-lg text-sm font-medium transition-colors bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white';
        }
    }

    function handleSearch() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            loadMeetings();
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
            loadMeetings();
        }
    }

    async function loadMeetings() {
        const container = document.getElementById('meetingsContainer');
        container.innerHTML = '<div class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>Loading...</p></div>';

        try {
            const search = document.getElementById('searchInput')?.value || '';
            
            let url = '/meetings?';
            if (currentStatusFilter === 'pending') url += 'activity_state=pending&';
            else if (currentStatusFilter) url += `status=${currentStatusFilter}&`;
            if (currentVerificationFilter) url += `verification_status=${currentVerificationFilter}&`;
            if (search) url += `search=${encodeURIComponent(search)}&`;
            if (dashboardDateFilter) url += `date_filter=${encodeURIComponent(dashboardDateFilter)}&`;
            if (dashboardDateFrom) url += `date_from=${encodeURIComponent(dashboardDateFrom)}&`;
            if (dashboardDateTo) url += `date_to=${encodeURIComponent(dashboardDateTo)}&`;

            const response = await apiCall(url);
            const meetings = response?.data || [];

            if (meetings.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <h3 style="font-size: 18px; font-weight: 600; color: #333; margin: 16px 0 8px;">No Meetings Found</h3>
                        <p>Schedule your first meeting to get started.</p>
                    </div>
                `;
                return;
            }

            const html = meetings.map(meeting => {
                const statusClass = meeting.status === 'completed' ? 'completed' : 
                                  meeting.status === 'cancelled' ? 'cancelled' : 
                                  meeting.verification_status === 'pending' && meeting.status === 'completed' ? 'pending-verification' : '';
                
                const statusBadge = meeting.status === 'scheduled' ? 'badge-scheduled' :
                                  meeting.status === 'completed' ? 'badge-completed' :
                                  'badge-cancelled';
                
                const verificationBadge = meeting.verification_status === 'verified' ? 'badge-verified' :
                                        meeting.verification_status === 'pending' ? 'badge-pending' :
                                        meeting.verification_status === 'rejected' ? 'badge-cancelled' : '';

                return `
                    <div class="meeting-card ${statusClass}">
                        <div class="meeting-header">
                            <div class="meeting-info">
                                <h3>${meeting.customer_name || 'N/A'}</h3>
                                <p><i class="fas fa-phone mr-2"></i>${meeting.phone || 'N/A'}</p>
                                <p><i class="fas fa-calendar mr-2"></i>Scheduled: ${new Date(meeting.scheduled_at).toLocaleString()}</p>
                                ${meeting.completed_at ? `<p><i class="fas fa-check-circle mr-2"></i>Completed: ${new Date(meeting.completed_at).toLocaleString()}</p>` : ''}
                                <p><i class="fas fa-tag mr-2"></i>Budget: ${meeting.budget_range || 'N/A'}</p>
                                <p><i class="fas fa-building mr-2"></i>Property: ${meeting.property_type || 'N/A'}</p>
                                ${meeting.completion_proof_photos && meeting.completion_proof_photos.length > 0 ? `
                                    <div style="margin-top: 12px;">
                                        <p style="font-weight: 500; margin-bottom: 8px; color: #374151;">Proof Photos:</p>
                                        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                            ${meeting.completion_proof_photos.map((photo, idx) => `
                                                <img src="/storage/${photo}" 
                                                     alt="Proof ${idx + 1}" 
                                                     style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px; cursor: pointer; border: 2px solid #e5e7eb;"
                                                     onclick="window.open('/storage/${photo}', '_blank')"
                                                     title="Click to view full size">
                                            `).join('')}
                                        </div>
                                    </div>
                                ` : ''}
                                <div style="margin-top: 8px;">
                                    <span class="badge ${statusBadge}">${meeting.status}</span>
                                    ${meeting.verification_status ? `<span class="badge ${verificationBadge}">${meeting.verification_status}</span>` : ''}
                                </div>
                            </div>
                            <div>
                                ${meeting.status === 'scheduled' ? `
                                    <button class="btn btn-success" onclick="showCompleteMeetingModal(${meeting.id})">
                                        <i class="fas fa-check mr-2"></i>Mark as Complete
                                    </button>
                                    <button class="btn" style="background: #f59e0b; color: white; margin-top: 8px;" onclick="showRescheduleMeetingModal(${meeting.id})">
                                        <i class="fas fa-calendar-alt mr-2"></i>Reschedule
                                    </button>
                                    <button class="btn btn-danger" onclick="showMarkDeadModal('meeting', ${meeting.id})" style="margin-top: 8px;">
                                        <i class="fas fa-skull mr-2"></i>Mark as Dead
                                    </button>
                                    <button class="btn btn-danger" onclick="cancelMeeting(${meeting.id})" style="margin-top: 8px;">
                                        <i class="fas fa-times mr-2"></i>Cancel
                                    </button>
                                ` : ''}
                                ${meeting.status === 'completed' ? `
                                    ${meeting.verification_status === 'pending' ? `
                                        ${canVerify ? `
                                            <button class="btn btn-success" onclick="showVerifyMeetingModal(${meeting.id})" style="margin-bottom: 8px;">
                                                <i class="fas fa-check-circle mr-2"></i>Verify
                                            </button>
                                            <button class="btn btn-danger" onclick="showRejectMeetingModal(${meeting.id})" style="margin-bottom: 8px;">
                                                <i class="fas fa-times-circle mr-2"></i>Reject
                                            </button>
                                        ` : `
                                            <div><span class="badge badge-pending">Awaiting Verification</span></div>
                                            ${meeting.pending_verification_with ? `<div class="mt-1 text-xs text-gray-600" style="margin-top: 4px;">Pending with: ${String(meeting.pending_verification_with || '').replace(/</g, '&lt;').replace(/>/g, '&gt;')}</div>` : ''}
                                        `}
                                    ` : ''}
                                    ${meeting.verification_status === 'verified' ? `
                                        <button class="btn btn-primary" onclick="showConvertToSiteVisitModal(${meeting.id})" style="margin-bottom: 8px;">
                                            <i class="fas fa-exchange-alt mr-2"></i>Convert to Site Visit
                                        </button>
                                    ` : ''}
                                    ${meeting.verification_status === 'rejected' ? `
                                        <div><span class="badge badge-cancelled">Rejected</span></div>
                                    ` : ''}
                                    <button class="btn btn-danger" onclick="showMarkDeadModal('meeting', ${meeting.id})" style="margin-top: 8px;">
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
            console.error('Error loading meetings:', error);
            container.innerHTML = '<div class="empty-state"><i class="fas fa-exclamation-triangle"></i><p>Error loading meetings</p></div>';
        }
    }

    let currentMeetingId = null;

    function showCompleteMeetingModal(id) {
        currentMeetingId = id;
        document.getElementById('completeMeetingModal').classList.add('show');
    }

    function closeCompleteMeetingModal() {
        document.getElementById('completeMeetingModal').classList.remove('show');
        document.getElementById('proofPhotosInput').value = '';
        document.getElementById('proofPhotosPreview').innerHTML = '';
        currentMeetingId = null;
    }

    function handleProofPhotosChange(event) {
        const files = event.target.files;
        const preview = document.getElementById('proofPhotosPreview');
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

    async function submitCompleteMeeting() {
        if (!currentMeetingId) return;

        const formData = new FormData();
        const photosInput = document.getElementById('proofPhotosInput');
        
        if (!photosInput.files || photosInput.files.length === 0) {
            alert('Please upload at least one proof photo');
            return;
        }

        for (let i = 0; i < photosInput.files.length; i++) {
            formData.append('proof_photos[]', photosInput.files[i]);
        }

        const feedback = document.getElementById('meetingFeedback').value;
        const rating = document.getElementById('meetingRating').value;
        const notes = document.getElementById('meetingNotes').value;

        if (feedback) formData.append('feedback', feedback);
        if (rating) formData.append('rating', rating);
        if (notes) formData.append('meeting_notes', notes);

        try {
            const token = getToken();
            const response = await fetch(`${API_BASE_URL}/meetings/${currentMeetingId}/complete`, {
                method: 'POST',
                headers: window.getManagerAuthHeaders({
                    'Authorization': `Bearer ${token}`,
                }),
                body: formData,
            });

            const result = await response.json();

            if (result && result.success) {
                alert('Meeting completed with proof photos! Awaiting verification.');
                closeCompleteMeetingModal();
                loadMeetings();
            } else {
                alert(result.message || 'Failed to complete meeting');
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
        currentMeetingId = id;
        document.getElementById('deadReason').value = '';
        document.getElementById('markDeadModal').classList.add('show');
    }

    function closeMarkDeadModal() {
        document.getElementById('markDeadModal').classList.remove('show');
        document.getElementById('deadReason').value = '';
        currentMeetingId = null;
    }

    async function submitMarkDead() {
        if (!currentMeetingId) return;

        const reason = document.getElementById('deadReason').value.trim();
        if (!reason) {
            alert('Please provide a reason for marking as dead');
            return;
        }

        const result = await apiCall(`/meetings/${currentMeetingId}/mark-dead`, {
            method: 'POST',
            body: JSON.stringify({ reason }),
        });

        if (result && result.success) {
            alert('Meeting marked as dead successfully');
            closeMarkDeadModal();
            loadMeetings();
        } else {
            alert(result.message || 'Failed to mark as dead');
        }
    }

    async function cancelMeeting(id) {
        if (!confirm('Cancel this meeting?')) return;

        const result = await apiCall(`/meetings/${id}/cancel`, {
            method: 'POST',
        });

        if (result && result.success) {
            alert('Meeting cancelled');
            loadMeetings();
        } else {
            alert(result.message || 'Failed to cancel meeting');
        }
    }

    function showConvertToSiteVisitModal(id) {
        currentMeetingId = id;
        document.getElementById('convertToSiteVisitModal').classList.add('show');
    }

    function closeConvertToSiteVisitModal() {
        document.getElementById('convertToSiteVisitModal').classList.remove('show');
        currentMeetingId = null;
    }

    async function convertToSiteVisit() {
        if (!currentMeetingId) return;

        closeConvertToSiteVisitModal();

        const result = await apiCall(`/meetings/${currentMeetingId}/convert-to-site-visit`, {
            method: 'POST',
        });

        if (result && result.success) {
            alert(result.message || 'Meeting converted to Site Visit successfully!');
            loadMeetings();
        } else {
            alert(result.message || 'Failed to convert meeting');
        }
    }

    function showRescheduleMeetingModal(id) {
        currentMeetingId = id;
        apiCall(`/meetings/${id}`).then(meeting => {
            if (meeting && meeting.id) {
                const minDateTime = new Date();
                minDateTime.setDate(minDateTime.getDate() + 1);
                minDateTime.setHours(0, 0, 0, 0);
                
                document.getElementById('rescheduleScheduledAt').value = '';
                document.getElementById('rescheduleReason').value = '';
                document.getElementById('rescheduleModalTitle').textContent = 'Reschedule Meeting';
                document.getElementById('rescheduleModalType').value = 'meeting';
                document.getElementById('rescheduleModalId').value = id;
                document.getElementById('rescheduleScheduledAt').min = minDateTime.toISOString().slice(0, 16);
                document.getElementById('rescheduleMeetingModal').classList.add('show');
            }
        });
    }

    function closeRescheduleMeetingModal() {
        document.getElementById('rescheduleMeetingModal').classList.remove('show');
        document.getElementById('rescheduleScheduledAt').value = '';
        document.getElementById('rescheduleReason').value = '';
        currentMeetingId = null;
    }

    async function submitRescheduleMeeting() {
        const type = document.getElementById('rescheduleModalType').value;
        const id = document.getElementById('rescheduleModalId').value;
        const scheduledAt = document.getElementById('rescheduleScheduledAt').value;
        const reason = document.getElementById('rescheduleReason').value.trim();

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
                closeRescheduleMeetingModal();
                loadMeetings();
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

    // Verification Functions
    let currentVerifyMeetingId = null;

    function showVerifyMeetingModal(id) {
        currentVerifyMeetingId = id;
        document.getElementById('verifyMeetingModal').classList.add('show');
    }

    function closeVerifyMeetingModal() {
        document.getElementById('verifyMeetingModal').classList.remove('show');
        document.getElementById('verifyNotes').value = '';
        currentVerifyMeetingId = null;
    }

    async function submitVerifyMeeting() {
        if (!currentVerifyMeetingId) return;

        const notes = document.getElementById('verifyNotes').value.trim();

        const result = await apiCall(`/meetings/${currentVerifyMeetingId}/verify`, {
            method: 'POST',
            body: JSON.stringify({ notes: notes }),
        });

        if (result && result.success) {
            alert('Meeting verified successfully!');
            closeVerifyMeetingModal();
            loadMeetings();
        } else {
            alert(result.message || 'Failed to verify meeting');
        }
    }

    function showRejectMeetingModal(id) {
        currentVerifyMeetingId = id;
        document.getElementById('rejectMeetingModal').classList.add('show');
    }

    function closeRejectMeetingModal() {
        document.getElementById('rejectMeetingModal').classList.remove('show');
        document.getElementById('rejectReason').value = '';
        currentVerifyMeetingId = null;
    }

    async function submitRejectMeeting() {
        if (!currentVerifyMeetingId) return;

        const reason = document.getElementById('rejectReason').value.trim();
        if (!reason) {
            alert('Please provide a reason for rejection');
            return;
        }

        const result = await apiCall(`/meetings/${currentVerifyMeetingId}/reject`, {
            method: 'POST',
            body: JSON.stringify({ reason: reason }),
        });

        if (result && result.success) {
            alert('Meeting rejected');
            closeRejectMeetingModal();
            loadMeetings();
        } else {
            alert(result.message || 'Failed to reject meeting');
        }
    }

    // Initialize
    (function() {
        updateStatusButtons();
        updateVerificationButtons();
        loadMeetings();
    })();
</script>

<!-- Complete Meeting Modal -->
<div id="completeMeetingModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <h3 style="font-size: 20px; font-weight: 600; margin-bottom: 20px;">Complete Meeting</h3>
        <p style="color: #ef4444; margin-bottom: 16px;"><strong>Proof photos are required to complete the meeting.</strong></p>
        
        <div class="form-group">
            <label>Proof Photos <span style="color: #ef4444;">*</span></label>
            <input type="file" id="proofPhotosInput" multiple accept="image/*" onchange="handleProofPhotosChange(event)" required>
            <div id="proofPhotosPreview" style="display: flex; flex-wrap: wrap; margin-top: 10px;"></div>
            <small style="color: #6b7280;">Upload at least one photo as proof. Max 5MB per image.</small>
        </div>

        <div class="form-group">
            <label>Feedback</label>
            <textarea id="meetingFeedback" rows="3" placeholder="Meeting feedback..."></textarea>
        </div>

        <div class="form-group">
            <label>Rating</label>
            <select id="meetingRating">
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
            <textarea id="meetingNotes" rows="3" placeholder="Additional notes..."></textarea>
        </div>

        <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
            <button type="button" class="btn btn-secondary" onclick="closeCompleteMeetingModal()">Cancel</button>
            <button type="button" class="btn btn-success" onclick="submitCompleteMeeting()">Submit</button>
        </div>
    </div>
</div>

<!-- Reschedule Modal -->
<div id="rescheduleMeetingModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <h3 id="rescheduleModalTitle" style="font-size: 20px; font-weight: 600; margin-bottom: 20px;">Reschedule</h3>
        <input type="hidden" id="rescheduleModalType" value="meeting">
        <input type="hidden" id="rescheduleModalId" value="">
        
        <div class="form-group">
            <label>New Scheduled Date & Time <span style="color: #ef4444;">*</span></label>
            <input type="datetime-local" id="rescheduleScheduledAt" required
                class="form-group input" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px;">
            <small style="color: #6b7280;">Select a future date and time</small>
        </div>

        <div class="form-group">
            <label>Reason for Rescheduling <span style="color: #ef4444;">*</span></label>
            <textarea id="rescheduleReason" rows="4" placeholder="Enter reason for rescheduling..." required
                class="form-group textarea" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px;"></textarea>
        </div>

        <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
            <button type="button" class="btn btn-secondary" onclick="closeRescheduleMeetingModal()">Cancel</button>
            <button type="button" class="btn" style="background: #f59e0b; color: white;" onclick="submitRescheduleMeeting()">Reschedule</button>
        </div>
    </div>
</div>

<!-- Mark as Dead Modal -->
<div id="markDeadModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <h3 style="font-size: 20px; font-weight: 600; margin-bottom: 20px;">Mark as Dead</h3>
        <p style="color: #ef4444; margin-bottom: 16px;">This will mark the meeting and associated lead as dead. This action cannot be undone.</p>
        
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

<!-- Verify Meeting Modal -->
<div id="verifyMeetingModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="text-center mb-6">
            <div class="w-20 h-20 mx-auto mb-4 bg-gradient-to-r from-[#10b981] to-[#059669] rounded-full flex items-center justify-center" style="width: 80px; height: 80px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                <i class="fas fa-check-circle text-white" style="font-size: 32px; color: white;"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-900 mb-2" style="font-size: 20px; font-weight: 600; margin-bottom: 8px; color: #111827;">Verify Meeting</h3>
            <p class="text-gray-600" style="color: #6b7280;">Are you sure you want to verify this meeting?</p>
        </div>
        <div class="form-group">
            <label>Verification Notes (Optional)</label>
            <textarea id="verifyNotes" rows="3" placeholder="Add any verification notes..."></textarea>
        </div>
        <div class="flex gap-3 justify-end" style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px;">
            <button type="button" class="btn btn-secondary" onclick="closeVerifyMeetingModal()">Cancel</button>
            <button type="button" class="btn btn-success" onclick="submitVerifyMeeting()">Verify</button>
        </div>
    </div>
</div>

<!-- Reject Meeting Modal -->
<div id="rejectMeetingModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="text-center mb-6">
            <div class="w-20 h-20 mx-auto mb-4 bg-gradient-to-r from-[#ef4444] to-[#dc2626] rounded-full flex items-center justify-center" style="width: 80px; height: 80px; background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                <i class="fas fa-times-circle text-white" style="font-size: 32px; color: white;"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-900 mb-2" style="font-size: 20px; font-weight: 600; margin-bottom: 8px; color: #111827;">Reject Meeting</h3>
            <p class="text-gray-600" style="color: #6b7280;">Please provide a reason for rejecting this meeting.</p>
        </div>
        <div class="form-group">
            <label>Rejection Reason <span style="color: #ef4444;">*</span></label>
            <textarea id="rejectReason" rows="4" placeholder="Enter reason for rejection..." required></textarea>
        </div>
        <div class="flex gap-3 justify-end" style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px;">
            <button type="button" class="btn btn-secondary" onclick="closeRejectMeetingModal()">Cancel</button>
            <button type="button" class="btn btn-danger" onclick="submitRejectMeeting()">Reject</button>
        </div>
    </div>
</div>

<!-- Convert to Site Visit Modal -->
<div id="convertToSiteVisitModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div style="text-align: center; margin-bottom: 24px;">
            <div style="width: 64px; height: 64px; margin: 0 auto 16px; background: linear-gradient(135deg, #063A1C 0%, #205A44 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                <i class="fas fa-exchange-alt" style="font-size: 28px; color: white;"></i>
            </div>
            <h3 style="font-size: 22px; font-weight: 600; margin-bottom: 12px; color: #063A1C;">Convert to Site Visit</h3>
            <p style="color: #6b7280; font-size: 15px; line-height: 1.6;">
                Are you sure you want to convert this meeting to a Site Visit?<br>
                <strong style="color: #374151;">All meeting data will be copied to the site visit.</strong>
            </p>
        </div>

        <div style="background: #f3f4f6; padding: 16px; border-radius: 8px; margin-bottom: 24px;">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                <i class="fas fa-info-circle" style="color: #205A44; font-size: 18px;"></i>
                <span style="color: #374151; font-size: 14px; font-weight: 500;">What will happen:</span>
            </div>
            <ul style="margin: 0; padding-left: 32px; color: #6b7280; font-size: 14px; line-height: 1.8;">
                <li>Meeting details will be copied to a new Site Visit</li>
                <li>The meeting will remain in your meetings list</li>
                <li>You can track the site visit separately</li>
            </ul>
        </div>

        <div style="display: flex; gap: 10px; justify-content: flex-end;">
            <button type="button" class="btn btn-secondary" onclick="closeConvertToSiteVisitModal()" style="min-width: 100px;">
                Cancel
            </button>
            <button type="button" class="btn btn-primary" onclick="convertToSiteVisit()" style="min-width: 100px; background: linear-gradient(135deg, #063A1C 0%, #205A44 100%);">
                <i class="fas fa-check mr-2"></i>Convert
            </button>
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
.btn-secondary {
    background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
    color: white;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}
.btn-secondary:hover {
    background: linear-gradient(135deg, #15803d 0%, #166534 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}
</style>
@endpush

@extends('telecaller.layout')

@section('title', 'Profile - Telecaller')
@section('page-title', 'Profile')

@push('styles')
<style>
    .profile-card {
        background: white;
        padding: 24px;
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }
    .profile-header {
        display: flex;
        align-items: center;
        margin-bottom: 24px;
        padding-bottom: 24px;
        border-bottom: 2px solid #f0f0f0;
    }
    .avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: linear-gradient(135deg, #205A44 0%, #063A1C 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 32px;
        font-weight: 700;
        margin-right: 20px;
        position: relative;
        overflow: hidden;
    }
    .avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 50%;
    }
    .avatar-upload {
        position: relative;
        display: inline-block;
    }
    .avatar-upload-btn {
        position: absolute;
        bottom: 0;
        right: 0;
        background: #205A44;
        color: white;
        border: 2px solid white;
        border-radius: 50%;
        width: 28px;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 14px;
    }
    .avatar-upload-btn:hover {
        background: #5568d3;
    }
    .avatar-upload input[type="file"] {
        display: none;
    }
    .image-preview {
        max-width: 200px;
        max-height: 200px;
        border-radius: 8px;
        margin-top: 10px;
        display: none;
    }
    .image-preview.show {
        display: block;
    }
    .profile-info h2 {
        font-size: 24px;
        font-weight: 700;
        color: #063A1C;
        margin-bottom: 4px;
    }
    .profile-info p {
        color: #B3B5B4;
        font-size: 14px;
    }
    .form-group {
        margin-bottom: 20px;
    }
    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: #333;
        font-size: 14px;
    }
    .form-group input, .form-group select, .form-group textarea {
        width: 100%;
        padding: 12px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 16px;
        transition: border-color 0.3s;
        background: #ffffff;
        color: #063A1C;
    }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
        outline: none;
        border-color: #205A44;
        background: #ffffff;
    }
    .form-group input[readonly] {
        background: #f5f5f5;
        cursor: not-allowed;
    }
    .form-group input::placeholder {
        color: #9ca3af;
    }
    .password-input-wrapper {
        position: relative;
    }
    .password-toggle {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: #B3B5B4;
        cursor: pointer;
        padding: 4px 8px;
        font-size: 16px;
    }
    .password-toggle:hover {
        color: #205A44;
    }
    .form-group input[type="password"],
    .form-group input[type="text"][data-password-field] {
        padding-right: 45px;
    }
    .btn {
        padding: 12px 24px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 16px;
        font-weight: 500;
        transition: all 0.3s;
    }
    .btn-primary {
        background: #205A44;
        color: white;
    }
    .btn-primary:hover {
        background: #5568d3;
    }
    .btn-success {
        background: #10b981;
        color: white;
    }
    .btn-success:hover {
        background: #059669;
    }
    .btn-warning {
        background: #f59e0b;
        color: white;
    }
    .btn-warning:hover {
        background: #d97706;
    }
    .btn-danger {
        background: #dc2626;
        color: white;
    }
    .btn-danger:hover {
        background: #b91c1c;
    }
    .edit-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        align-items: center;
    }
    .status-badge {
        display: inline-block;
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: 500;
        margin-bottom: 16px;
    }
    .status-available {
        background: #d1fae5;
        color: #065f46;
    }
    .status-absent {
        background: #fee2e2;
        color: #991b1b;
    }
    .card-title {
        font-size: 18px;
        font-weight: 600;
        color: #063A1C;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
    }
    .card-title i {
        margin-right: 10px;
        color: #205A44;
    }
    .info-row {
        display: flex;
        padding: 12px 0;
        border-bottom: 1px solid #f0f0f0;
    }
    .info-row:last-child {
        border-bottom: none;
    }
    .info-label {
        width: 150px;
        font-weight: 500;
        color: #B3B5B4;
    }
    .info-value {
        flex: 1;
        color: #063A1C;
    }
    .activity-table {
        width: 100%;
        border-collapse: collapse;
    }
    .activity-table th,
    .activity-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #f0f0f0;
    }
    .activity-table th {
        background: #f8f9fa;
        font-weight: 600;
        color: #B3B5B4;
        font-size: 12px;
        text-transform: uppercase;
    }
    .activity-table td {
        color: #063A1C;
        font-size: 14px;
    }
    .alert {
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 16px;
        font-size: 14px;
    }
    .alert-success {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }
    .alert-error {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }
    .hidden {
        display: none;
    }
    .edit-mode {
        pointer-events: auto;
    }
    .view-mode input:not([type="file"]),
    .view-mode textarea {
        background: #f5f5f5 !important;
        cursor: not-allowed;
        pointer-events: none;
    }
    .edit-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 16px;
        border-bottom: 2px solid #f0f0f0;
    }
    /* Phone: hide edit-header (title + buttons at top), show buttons below profile-header */
    .profile-actions-mobile {
        display: none;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 16px;
        padding-top: 16px;
        border-top: 1px solid #f0f0f0;
    }
    @media (max-width: 767px) {
        .profile-card .edit-header {
            display: none !important;
        }
        .profile-actions-mobile {
            display: flex;
            align-items: center;
        }
    }
    @media (min-width: 768px) {
        .profile-actions-mobile {
            display: none !important;
        }
    }
</style>
@endpush

@section('content')
    <!-- Profile Header -->
    <div class="profile-card">
        <div class="edit-header">
            <h2 style="font-size: 24px; font-weight: 700; color: #063A1C; margin: 0;">Profile</h2>
            <div class="edit-actions">
                <button type="button" id="saveChangesBtn" class="btn btn-success" onclick="saveAllChanges()">
                    <i class="fas fa-save"></i> Save Changes
                </button>
                <button type="button" class="btn btn-danger" onclick="logout()">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </div>
        </div>
        <div class="profile-header">
            <div class="avatar-upload">
                <div class="avatar" id="avatar">
                    <span id="avatarInitial">T</span>
                    <img id="avatarImage" src="" alt="Profile Picture" style="display: none;">
                </div>
                <label for="profilePictureInput" class="avatar-upload-btn" id="profilePictureLabel" title="Upload Profile Picture">
                    <i class="fas fa-camera"></i>
                </label>
                <input type="file" id="profilePictureInput" accept="image/jpeg,image/jpg,image/png">
            </div>
            <div class="profile-info">
                <h2 id="profileName">User</h2>
                <p id="profileEmail">Loading...</p>
            </div>
        </div>
        <!-- Phone view: Save & Logout below profile-header -->
        <div class="profile-actions-mobile">
            <button type="button" class="btn btn-success" onclick="saveAllChanges()">
                <i class="fas fa-save"></i> Save Changes
            </button>
            <button type="button" class="btn btn-danger" onclick="logout()">
                <i class="fas fa-sign-out-alt"></i> Logout
            </button>
        </div>
        <div id="profilePictureAlert"></div>
        <div style="margin-top: 16px;">
            <img id="imagePreview" class="image-preview" alt="Preview">
            <div id="uploadActions" style="display: none; margin-top: 10px;">
                <button type="button" class="btn btn-primary" onclick="uploadProfilePicture()">Upload Picture</button>
                <button type="button" class="btn" style="background: #6b7280; color: white; margin-left: 10px;" onclick="cancelPictureUpload()">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Personal Information Card -->
    <div class="profile-card" id="personalInfoCard">
        <div class="card-title">
            <i class="fas fa-user"></i>
            Personal Information
        </div>
        <div id="personalInfoAlert"></div>
        <form id="personalInfoForm">
            <div class="form-group">
                <label for="profileNameInput">Name *</label>
                <input type="text" id="profileNameInput" name="name" required>
            </div>
            <div class="form-group">
                <label for="profilePhoneInput">Phone</label>
                <input type="text" id="profilePhoneInput" name="phone">
            </div>
            <div class="form-group">
                <label for="profileEmailInput">Email *</label>
                <input type="email" id="profileEmailInput" name="email" required>
            </div>
            <div class="info-row">
                <div class="info-label">Role</div>
                <div class="info-value" id="profileRole">-</div>
            </div>
            <div class="info-row">
                <div class="info-label">Manager</div>
                <div class="info-value" id="profileManager">-</div>
            </div>
            <div class="info-row">
                <div class="info-label">Joining Date</div>
                <div class="info-value" id="profileJoinDate">-</div>
            </div>
            <div id="personalInfoSubmitBtn" style="display: none;">
                <button type="submit" class="btn btn-primary">Update Profile</button>
            </div>
        </form>
    </div>

    <!-- Availability Status Card -->
    <div class="profile-card">
        <div class="card-title">
            <i class="fas fa-clock"></i>
            Availability Status
        </div>
        <div id="availabilityAlert"></div>
        <div id="availabilityStatus">
            <span class="status-badge status-available" id="statusBadge">Available</span>
        </div>
        <form id="availabilityForm" class="hidden">
            <div class="form-group">
                <label for="absentReason">Absent Reason</label>
                <textarea id="absentReason" name="absent_reason" rows="3" placeholder="Enter reason for absence"></textarea>
            </div>
            <div class="form-group">
                <label for="absentUntil">Return Date</label>
                <input type="datetime-local" id="absentUntil" name="absent_until">
            </div>
            <button type="submit" class="btn btn-warning">Mark as Absent</button>
            <button type="button" class="btn" style="background: #6b7280; color: white; margin-left: 10px;" onclick="cancelAbsenceForm()">Cancel</button>
        </form>
        <div id="availabilityActions">
            <button class="btn btn-warning" onclick="showAbsenceForm()">Mark as Absent</button>
        </div>
    </div>

    <!-- Password Change Card -->
    <div class="profile-card" id="passwordCard">
        <div class="card-title">
            <i class="fas fa-lock"></i>
            Change Password
        </div>
        <div id="passwordAlert"></div>
        <form id="passwordForm">
            <div class="form-group">
                <label for="currentPassword">Current Password *</label>
                <div class="password-input-wrapper">
                    <input type="password" id="currentPassword" name="current_password" required>
                    <button type="button" class="password-toggle" onclick="togglePasswordVisibility('currentPassword')" title="Show/Hide Password">
                        <i class="fas fa-eye" id="currentPasswordToggleIcon"></i>
                    </button>
                </div>
            </div>
            <div class="form-group">
                <label for="newPassword">New Password *</label>
                <div class="password-input-wrapper">
                    <input type="password" id="newPassword" name="new_password" required minlength="8">
                    <button type="button" class="password-toggle" onclick="togglePasswordVisibility('newPassword')" title="Show/Hide Password">
                        <i class="fas fa-eye" id="newPasswordToggleIcon"></i>
                    </button>
                </div>
                <small style="color: #B3B5B4; font-size: 12px; margin-top: 4px; display: block;">Minimum 8 characters</small>
            </div>
            <div class="form-group">
                <label for="confirmPassword">Confirm New Password *</label>
                <div class="password-input-wrapper">
                    <input type="password" id="confirmPassword" name="new_password_confirmation" required>
                    <button type="button" class="password-toggle" onclick="togglePasswordVisibility('confirmPassword')" title="Show/Hide Password">
                        <i class="fas fa-eye" id="confirmPasswordToggleIcon"></i>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Activity History Card -->
    <div class="profile-card">
        <div class="card-title">
            <i class="fas fa-history"></i>
            Recent Activity
        </div>
        <div id="activityHistory">
            <p style="text-align: center; color: #B3B5B4; padding: 20px;">Loading...</p>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    // Check if API_BASE_URL is already declared (from layout)
    if (typeof API_BASE_URL === 'undefined') {
        var API_BASE_URL = '{{ url("/api/telecaller") }}';
    } else {
        // Override with telecaller-specific endpoint
        API_BASE_URL = '{{ url("/api/telecaller") }}';
    }
    
    // Get token from localStorage
    function getToken() {
        return localStorage.getItem('telecaller_token');
    }

    // API call helper
    async function apiCall(endpoint, options = {}) {
        const token = getToken();
        if (!token) {
            window.location.href = '{{ route("login") }}';
            return null;
        }

        // Get CSRF token from meta tag
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': `Bearer ${token}`,
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
        };

        try {
            const response = await fetch(`${API_BASE_URL}${endpoint}`, {
                ...defaultOptions,
                ...options,
                headers: { ...defaultOptions.headers, ...options.headers },
                credentials: 'same-origin', // Include cookies for CSRF token
            });

            if (response.status === 401) {
                localStorage.removeItem('telecaller_token');
                localStorage.removeItem('telecaller_user');
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

    // Show alert message
    function showAlert(containerId, message, type = 'success') {
        const container = document.getElementById(containerId);
        container.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
        setTimeout(() => {
            container.innerHTML = '';
        }, 5000);
    }

    // Load profile data
    async function loadProfile() {
        try {
            console.log('Loading profile data...');
            const data = await apiCall('/profile');
            console.log('Profile data received:', data);
            
            if (!data || !data.user) {
                console.error('Failed to load profile - no user data');
                // Set default values instead of leaving "Loading..."
                const profileNameEl = document.getElementById('profileName');
                const profileEmailEl = document.getElementById('profileEmail');
                const avatarInitialEl = document.getElementById('avatarInitial');
                
                if (profileNameEl) profileNameEl.textContent = 'User';
                if (profileEmailEl) profileEmailEl.textContent = 'No email';
                if (avatarInitialEl) avatarInitialEl.textContent = 'U';
                
                // Try to get user from localStorage as fallback
                const userStr = localStorage.getItem('telecaller_user');
                if (userStr) {
                    try {
                        const localUser = JSON.parse(userStr);
                        if (profileNameEl) profileNameEl.textContent = localUser.name || 'User';
                        if (profileEmailEl) profileEmailEl.textContent = localUser.email || 'No email';
                        if (avatarInitialEl) avatarInitialEl.textContent = (localUser.name || 'User').charAt(0).toUpperCase();
                        
                        // Update form fields
                        const nameInput = document.getElementById('profileNameInput');
                        const phoneInput = document.getElementById('profilePhoneInput');
                        const emailInput = document.getElementById('profileEmailInput');
                        
                        if (nameInput) {
                            nameInput.value = localUser.name || '';
                            nameInput.defaultValue = localUser.name || '';
                        }
                        if (phoneInput) {
                            phoneInput.value = localUser.phone || '';
                            phoneInput.defaultValue = localUser.phone || '';
                        }
                        if (emailInput) {
                            emailInput.value = localUser.email || '';
                            emailInput.defaultValue = localUser.email || '';
                        }
                    } catch (e) {
                        console.error('Error parsing localStorage user:', e);
                    }
                }
                return;
            }

            const user = data.user;
            const profile = data.profile || {};

            // Update header
            const profileNameEl = document.getElementById('profileName');
            const profileEmailEl = document.getElementById('profileEmail');
            const avatarInitialEl = document.getElementById('avatarInitial');
            
            if (profileNameEl) profileNameEl.textContent = user.name || 'User';
            if (profileEmailEl) profileEmailEl.textContent = user.email || 'No email';
            if (avatarInitialEl) avatarInitialEl.textContent = (user.name || 'User').charAt(0).toUpperCase();
            
            // Update profile picture
            updateAvatarDisplay(user.profile_picture, user.name);

            // Update personal info form
            const nameInput = document.getElementById('profileNameInput');
            const phoneInput = document.getElementById('profilePhoneInput');
            const emailInput = document.getElementById('profileEmailInput');
            
            if (nameInput) {
                nameInput.value = user.name || '';
                nameInput.defaultValue = user.name || '';
            }
            if (phoneInput) {
                phoneInput.value = user.phone || '';
                phoneInput.defaultValue = user.phone || '';
            }
            if (emailInput) {
                emailInput.value = user.email || '';
                emailInput.defaultValue = user.email || '';
            }
            
            // Update role, manager, and joining date display
            const roleEl = document.getElementById('profileRole');
            const managerEl = document.getElementById('profileManager');
            const joinDateEl = document.getElementById('profileJoinDate');
            
            if (roleEl) roleEl.textContent = user.role || '-';
            if (managerEl) managerEl.textContent = user.manager || 'Not Assigned';
            if (joinDateEl) joinDateEl.textContent = user.created_at || '-';

            // Update availability status
            if (profile) {
                updateAvailabilityDisplay(profile);
            }

            // Load activity history
            loadActivityHistory(data.activity_history || []);
        } catch (error) {
            console.error('Error loading profile:', error);
            // Set fallback values
            const profileNameEl = document.getElementById('profileName');
            const profileEmailEl = document.getElementById('profileEmail');
            const avatarInitialEl = document.getElementById('avatarInitial');
            
            if (profileNameEl) profileNameEl.textContent = 'User';
            if (profileEmailEl) profileEmailEl.textContent = 'No email';
            if (avatarInitialEl) avatarInitialEl.textContent = 'U';
        }
    }

    // Update availability display
    function updateAvailabilityDisplay(profile) {
        const statusBadge = document.getElementById('statusBadge');
        const availabilityActions = document.getElementById('availabilityActions');
        const availabilityForm = document.getElementById('availabilityForm');

        if (profile.is_absent) {
            statusBadge.className = 'status-badge status-absent';
            statusBadge.textContent = 'Absent';
            if (profile.absent_reason) {
                statusBadge.textContent += ` - ${profile.absent_reason}`;
            }
            if (profile.absent_until) {
                statusBadge.textContent += ` (Until: ${new Date(profile.absent_until).toLocaleDateString()})`;
            }
            availabilityActions.innerHTML = '<button class="btn btn-success" onclick="markAsAvailable()">Mark as Available</button>';
            availabilityForm.classList.add('hidden');
        } else {
            statusBadge.className = 'status-badge status-available';
            statusBadge.textContent = 'Available';
            availabilityActions.innerHTML = '<button class="btn btn-warning" onclick="showAbsenceForm()">Mark as Absent</button>';
            availabilityForm.classList.add('hidden');
        }
    }

    // Show absence form
    function showAbsenceForm() {
        document.getElementById('availabilityForm').classList.remove('hidden');
        document.getElementById('availabilityActions').classList.add('hidden');
    }

    // Cancel absence form
    function cancelAbsenceForm() {
        document.getElementById('availabilityForm').classList.add('hidden');
        document.getElementById('availabilityActions').classList.remove('hidden');
        document.getElementById('availabilityForm').reset();
    }

    // Mark as available
    async function markAsAvailable() {
        const result = await apiCall('/profile/availability', {
            method: 'POST',
            body: JSON.stringify({
                is_absent: false,
                absent_reason: null,
                absent_until: null,
            }),
        });

        if (result && result.success) {
            showAlert('availabilityAlert', result.message, 'success');
            loadProfile();
        } else {
            showAlert('availabilityAlert', result.message || 'Failed to update availability', 'error');
        }
    }

    // Load activity history
    function loadActivityHistory(activities) {
        const container = document.getElementById('activityHistory');
        
        if (!container) return;
        
        if (!activities || activities.length === 0) {
            container.innerHTML = '<p style="text-align: center; color: #B3B5B4; padding: 20px;">No activity history found</p>';
            return;
        }

        const table = `
            <table class="activity-table">
                <thead>
                    <tr>
                        <th>Action</th>
                        <th>IP Address</th>
                        <th>Date & Time</th>
                    </tr>
                </thead>
                <tbody>
                    ${activities.map(activity => `
                        <tr>
                            <td>${activity.action}</td>
                            <td>${activity.ip || 'N/A'}</td>
                            <td>${new Date(activity.created_at).toLocaleString()}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
        container.innerHTML = table;
    }

    // Personal Info Form Submit
    document.getElementById('personalInfoForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formData = {
            name: document.getElementById('profileNameInput').value,
            email: document.getElementById('profileEmailInput').value,
            phone: document.getElementById('profilePhoneInput').value,
        };

        const result = await apiCall('/profile', {
            method: 'PUT',
            body: JSON.stringify(formData),
        });

        if (result && result.success) {
            showAlert('personalInfoAlert', result.message, 'success');
            
            // Update header display with new values
            if (result.user) {
                document.getElementById('profileName').textContent = result.user.name;
                document.getElementById('profileEmail').textContent = result.user.email;
                document.getElementById('avatarInitial').textContent = result.user.name.charAt(0).toUpperCase();
                
                // Update role, manager, and joining date if available
                if (result.user.role) {
                    document.getElementById('profileRole').textContent = result.user.role;
                }
                if (result.user.manager) {
                    document.getElementById('profileManager').textContent = result.user.manager;
                }
                if (result.user.created_at) {
                    document.getElementById('profileJoinDate').textContent = result.user.created_at;
                }
            }
            
            // Keep form fields as they are (preserve user input)
            // Form fields already have the updated values, no need to reload
        } else {
            showAlert('personalInfoAlert', result.message || 'Failed to update profile', 'error');
        }
    });

    // Password Form Submit
    document.getElementById('passwordForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const currentPassword = document.getElementById('currentPassword').value;
        const newPassword = document.getElementById('newPassword').value;
        const confirmPassword = document.getElementById('confirmPassword').value;

        if (newPassword !== confirmPassword) {
            showAlert('passwordAlert', 'New passwords do not match', 'error');
            return;
        }

        if (newPassword.length < 8) {
            showAlert('passwordAlert', 'Password must be at least 8 characters', 'error');
            return;
        }

        const result = await apiCall('/profile/password', {
            method: 'POST',
            body: JSON.stringify({
                current_password: currentPassword,
                new_password: newPassword,
                new_password_confirmation: confirmPassword,
            }),
        });

        if (result && result.success) {
            showAlert('passwordAlert', result.message, 'success');
            document.getElementById('passwordForm').reset();
        } else {
            showAlert('passwordAlert', result.message || 'Failed to change password', 'error');
        }
    });

    // Availability Form Submit
    document.getElementById('availabilityForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formData = {
            is_absent: true,
            absent_reason: document.getElementById('absentReason').value,
            absent_until: document.getElementById('absentUntil').value,
        };

        const result = await apiCall('/profile/availability', {
            method: 'POST',
            body: JSON.stringify(formData),
        });

        if (result && result.success) {
            showAlert('availabilityAlert', result.message, 'success');
            loadProfile();
        } else {
            showAlert('availabilityAlert', result.message || 'Failed to update availability', 'error');
        }
    });

    // Update avatar display
    function updateAvatarDisplay(profilePictureUrl, userName) {
        const avatarImage = document.getElementById('avatarImage');
        const avatarInitial = document.getElementById('avatarInitial');
        
        if (profilePictureUrl) {
            avatarImage.src = profilePictureUrl;
            avatarImage.style.display = 'block';
            avatarInitial.style.display = 'none';
        } else {
            avatarImage.style.display = 'none';
            avatarInitial.style.display = 'block';
            avatarInitial.textContent = userName.charAt(0).toUpperCase();
        }
    }

    // Handle profile picture file selection
    let selectedFile = null;
    document.getElementById('profilePictureInput').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) {
            return;
        }

        // Validate file type
        if (!file.type.match('image.*')) {
            showAlert('profilePictureAlert', 'Please select a valid image file', 'error');
            return;
        }

        // Validate file size (2MB)
        if (file.size > 2 * 1024 * 1024) {
            showAlert('profilePictureAlert', 'Image size must be less than 2MB', 'error');
            return;
        }

        selectedFile = file;

        // Show preview
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('imagePreview');
            preview.src = e.target.result;
            preview.classList.add('show');
            document.getElementById('uploadActions').style.display = 'block';
        };
        reader.readAsDataURL(file);
    });

    // Upload profile picture
    async function uploadProfilePicture() {
        if (!selectedFile) {
            showAlert('profilePictureAlert', 'Please select an image first', 'error');
            return;
        }

        const token = getToken();
        if (!token) {
            window.location.href = '{{ route("login") }}';
            return;
        }

        // Show loading state
        const uploadBtn = document.querySelector('#uploadActions button.btn-primary');
        if (!uploadBtn) {
            showAlert('profilePictureAlert', 'Upload button not found', 'error');
            return;
        }
        const originalText = uploadBtn.textContent;
        uploadBtn.disabled = true;
        uploadBtn.textContent = 'Uploading...';

        const formData = new FormData();
        formData.append('profile_picture', selectedFile);

        // Get CSRF token from meta tag
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        
        try {
            const response = await fetch(`${API_BASE_URL}/profile/picture`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin', // Include cookies for CSRF token
                body: formData,
            });

            if (response.status === 401) {
                localStorage.removeItem('telecaller_token');
                localStorage.removeItem('telecaller_user');
                window.location.href = '{{ route("login") }}';
                return;
            }

            let result;
            try {
                result = await response.json();
            } catch (e) {
                throw new Error('Invalid response from server');
            }

            if (!response.ok) {
                const errorMsg = result.message || result.error || 'Failed to upload profile picture';
                const errorDetails = result.errors ? Object.values(result.errors).flat().join(', ') : '';
                showAlert('profilePictureAlert', errorMsg + (errorDetails ? ': ' + errorDetails : ''), 'error');
                uploadBtn.disabled = false;
                uploadBtn.textContent = originalText;
                return;
            }

            if (result.success) {
                showAlert('profilePictureAlert', 'Profile picture saved successfully!', 'success');
                // Update avatar display immediately
                updateAvatarDisplay(result.profile_picture, document.getElementById('profileName').textContent);
                // Reset preview
                cancelPictureUpload();
                // Reset button state
                uploadBtn.disabled = false;
                uploadBtn.textContent = originalText;
                // Update profile data without full reload to preserve form state
                if (result.profile_picture) {
                    // Avatar already updated, no need to reload full profile
                }
            } else {
                showAlert('profilePictureAlert', result.message || 'Failed to upload profile picture', 'error');
                uploadBtn.disabled = false;
                uploadBtn.textContent = originalText;
            }
        } catch (error) {
            console.error('Upload Error:', error);
            showAlert('profilePictureAlert', 'Network error: Unable to upload picture. ' + error.message, 'error');
            // Reset button state
            if (uploadBtn) {
                uploadBtn.disabled = false;
                uploadBtn.textContent = originalText;
            }
        }
    }

    // Cancel picture upload
    function cancelPictureUpload() {
        selectedFile = null;
        document.getElementById('profilePictureInput').value = '';
        document.getElementById('imagePreview').classList.remove('show');
        document.getElementById('uploadActions').style.display = 'none';
    }

    // Toggle password visibility
    function togglePasswordVisibility(fieldId) {
        const field = document.getElementById(fieldId);
        const icon = document.getElementById(fieldId + 'ToggleIcon');
        
        if (field.type === 'password') {
            field.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            field.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }


    // Save all changes
    async function saveAllChanges() {
        try {
            console.log('saveAllChanges called');
            
            // Get current values
            const nameInput = document.getElementById('profileNameInput');
            const emailInput = document.getElementById('profileEmailInput');
            const phoneInput = document.getElementById('profilePhoneInput');
            
            if (!nameInput || !emailInput || !phoneInput) {
                console.error('Input fields not found');
                showAlert('personalInfoAlert', 'Error: Form fields not found', 'error');
                return;
            }
            
            // Check if profile info has changes
            const nameChanged = nameInput.value !== (nameInput.defaultValue || '');
            const emailChanged = emailInput.value !== (emailInput.defaultValue || '');
            const phoneChanged = phoneInput.value !== (phoneInput.defaultValue || '');
            
            // Check if password fields have values
            const currentPassword = document.getElementById('currentPassword')?.value || '';
            const newPassword = document.getElementById('newPassword')?.value || '';
            const confirmPassword = document.getElementById('confirmPassword')?.value || '';
            
            // Check if profile picture is selected
            const profilePictureSelected = typeof selectedFile !== 'undefined' && selectedFile !== null;
            
            let hasChanges = nameChanged || emailChanged || phoneChanged || (newPassword && currentPassword) || profilePictureSelected;
            
            if (!hasChanges) {
                showAlert('personalInfoAlert', 'No changes to save', 'error');
                return;
            }
            
            let allSuccess = true;
            let savedAny = false;
            
            // Save profile info if changed
            if (nameChanged || emailChanged || phoneChanged) {
                savedAny = true;
                const formData = {
                    name: nameInput.value,
                    email: emailInput.value,
                    phone: phoneInput.value,
                };

                console.log('Saving profile info:', formData);
                const result = await apiCall('/profile', {
                    method: 'PUT',
                    body: JSON.stringify(formData),
                });

                console.log('Profile update response:', result);

                if (result && result.success) {
                    showAlert('personalInfoAlert', 'Profile updated successfully', 'success');
                    
                    // Update header immediately
                    const profileNameEl = document.getElementById('profileName');
                    const profileEmailEl = document.getElementById('profileEmail');
                    const avatarInitialEl = document.getElementById('avatarInitial');
                    
                    if (result.user) {
                        if (profileNameEl) profileNameEl.textContent = result.user.name;
                        if (profileEmailEl) profileEmailEl.textContent = result.user.email;
                        if (avatarInitialEl) avatarInitialEl.textContent = result.user.name.charAt(0).toUpperCase();
                        
                        // Update localStorage with new user data
                        const userStr = localStorage.getItem('telecaller_user');
                        if (userStr) {
                            try {
                                const localUser = JSON.parse(userStr);
                                localUser.name = result.user.name;
                                localUser.email = result.user.email;
                                localUser.phone = result.user.phone || localUser.phone;
                                localStorage.setItem('telecaller_user', JSON.stringify(localUser));
                            } catch (e) {
                                console.error('Error updating localStorage:', e);
                            }
                        }
                    }
                    
                    // Update default values to current values (so they don't reset)
                    nameInput.defaultValue = nameInput.value;
                    emailInput.defaultValue = emailInput.value;
                    phoneInput.defaultValue = phoneInput.value;
                } else {
                    const errorMsg = result?.message || result?.error || 'Failed to update profile';
                    console.error('Profile update failed:', errorMsg);
                    showAlert('personalInfoAlert', errorMsg, 'error');
                    allSuccess = false;
                }
            }
            
            // Save password if changed
            if (newPassword && currentPassword) {
                savedAny = true;
                if (newPassword !== confirmPassword) {
                    showAlert('passwordAlert', 'New passwords do not match', 'error');
                    allSuccess = false;
                } else if (newPassword.length < 8) {
                    showAlert('passwordAlert', 'Password must be at least 8 characters', 'error');
                    allSuccess = false;
                } else {
                    console.log('Changing password');
                    const result = await apiCall('/profile/password', {
                        method: 'POST',
                        body: JSON.stringify({
                            current_password: currentPassword,
                            new_password: newPassword,
                            new_password_confirmation: confirmPassword,
                        }),
                    });

                    if (result && result.success) {
                        showAlert('passwordAlert', 'Password changed successfully', 'success');
                        // Clear password fields
                        const currentPassEl = document.getElementById('currentPassword');
                        const newPassEl = document.getElementById('newPassword');
                        const confirmPassEl = document.getElementById('confirmPassword');
                        if (currentPassEl) currentPassEl.value = '';
                        if (newPassEl) newPassEl.value = '';
                        if (confirmPassEl) confirmPassEl.value = '';
                    } else {
                        showAlert('passwordAlert', result?.message || 'Failed to change password', 'error');
                        allSuccess = false;
                    }
                }
            }
            
            // Save profile picture if selected
            if (profilePictureSelected && allSuccess) {
                savedAny = true;
                console.log('Uploading profile picture');
                await uploadProfilePicture();
            }
            
        // Show success message if all successful (but don't reload profile to preserve user input)
        if (allSuccess && savedAny) {
            // Don't reload profile immediately - keep the current values visible
            // The values are already updated in the UI and localStorage
            console.log('All changes saved successfully');
        } else if (!allSuccess) {
            console.error('Some changes failed to save');
        }
        } catch (error) {
            console.error('Error in saveAllChanges:', error);
            showAlert('personalInfoAlert', 'An error occurred while saving: ' + error.message, 'error');
        }
    }

    // Make functions globally accessible immediately
    window.saveAllChanges = saveAllChanges;
    window.togglePasswordVisibility = togglePasswordVisibility;
    window.uploadProfilePicture = uploadProfilePicture;
    window.cancelPictureUpload = cancelPictureUpload;
    
    // Load user data from localStorage immediately
    function loadUserFromLocalStorage() {
        const userStr = localStorage.getItem('telecaller_user');
        if (userStr) {
            try {
                // Handle both string and already-parsed object
                let user;
                if (typeof userStr === 'string') {
                    // Try to parse as JSON
                    try {
                        user = JSON.parse(userStr);
                    } catch (parseError) {
                        // If parsing fails, it might be a string representation of object
                        console.warn('Failed to parse user data as JSON, trying eval:', parseError);
                        return; // Skip if can't parse
                    }
                } else {
                    user = userStr; // Already an object
                }
                
                if (!user || typeof user !== 'object') {
                    console.warn('Invalid user data format in localStorage');
                    return;
                }
                
                const profileNameEl = document.getElementById('profileName');
                const profileEmailEl = document.getElementById('profileEmail');
                const avatarInitialEl = document.getElementById('avatarInitial');
                const nameInput = document.getElementById('profileNameInput');
                const phoneInput = document.getElementById('profilePhoneInput');
                const emailInput = document.getElementById('profileEmailInput');
                
                if (profileNameEl) profileNameEl.textContent = user.name || 'User';
                if (profileEmailEl) profileEmailEl.textContent = user.email || 'No email';
                if (avatarInitialEl) avatarInitialEl.textContent = (user.name || 'User').charAt(0).toUpperCase();
                
                if (nameInput) {
                    nameInput.value = user.name || '';
                    nameInput.defaultValue = user.name || '';
                }
                if (phoneInput) {
                    phoneInput.value = user.phone || '';
                    phoneInput.defaultValue = user.phone || '';
                }
                if (emailInput) {
                    emailInput.value = user.email || '';
                    emailInput.defaultValue = user.email || '';
                }
                
                // Update role if available
                const roleEl = document.getElementById('profileRole');
                if (roleEl && user.role) {
                    const roleName = typeof user.role === 'object' ? (user.role.name || user.role.slug) : user.role;
                    roleEl.textContent = roleName || '-';
                }
                
                // Auto-fill current password from localStorage
                const currentPasswordInput = document.getElementById('currentPassword');
                if (currentPasswordInput) {
                    const storedPassword = localStorage.getItem('user_current_password');
                    if (storedPassword) {
                        currentPasswordInput.value = storedPassword;
                    }
                }
            } catch (e) {
                console.error('Error loading user from localStorage:', e);
            }
        }
    }
    
    // Initialize on page load
    (function() {
        // Load from localStorage first (instant display)
        loadUserFromLocalStorage();
        // Then load from API (for latest data)
        loadProfile();
    })();
</script>
@endpush

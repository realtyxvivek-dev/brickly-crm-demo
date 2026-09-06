@extends('layouts.app')

@section('title', 'Profile - Marketing')
@section('page-title', 'Profile')

@push('styles')
<style>
    .profile-card { background:white; padding:24px; border-radius:12px; box-shadow:0 2px 4px rgba(0,0,0,0.1); margin-bottom:20px; border:1px solid #E5DED4; }
    .profile-header { display:flex; align-items:center; margin-bottom:24px; padding-bottom:24px; border-bottom:2px solid #f0f0f0; }
    .avatar { width:80px; height:80px; border-radius:50%; background:linear-gradient(135deg, #205A44 0%, #063A1C 100%); display:flex; align-items:center; justify-content:center; color:white; font-size:32px; font-weight:700; margin-right:20px; position:relative; overflow:hidden; }
    .avatar img { width:100%; height:100%; object-fit:cover; border-radius:50%; }
    .avatar-upload { position:relative; display:inline-block; }
    .avatar-upload-btn { position:absolute; bottom:0; right:0; background:#205A44; color:white; border:2px solid white; border-radius:50%; width:28px; height:28px; display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:14px; }
    .avatar-upload-btn:hover { background:#063A1C; }
    .avatar-upload input[type="file"] { display:none; }
    .image-preview { max-width:200px; max-height:200px; border-radius:8px; margin-top:10px; display:none; }
    .image-preview.show { display:block; }
    .profile-info h2 { font-size:24px; font-weight:700; color:#063A1C; margin-bottom:4px; }
    .profile-info p { color:#B3B5B4; font-size:14px; }
    .form-group { margin-bottom:20px; }
    .form-group label { display:block; margin-bottom:8px; font-weight:500; color:#333; font-size:14px; }
    .form-group input, .form-group select, .form-group textarea { width:100%; padding:12px; border:2px solid #e0e0e0; border-radius:8px; font-size:16px; transition:border-color 0.3s; background:#ffffff; color:#063A1C; }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline:none; border-color:#205A44; background:#ffffff; }
    .form-group input[readonly] { background:#f5f5f5; cursor:not-allowed; }
    .password-input-wrapper { position:relative; }
    .password-toggle { position:absolute; right:12px; top:50%; transform:translateY(-50%); background:none; border:none; color:#B3B5B4; cursor:pointer; padding:4px 8px; font-size:16px; }
    .password-toggle:hover { color:#205A44; }
    .form-group input[type="password"], .form-group input[type="text"][data-password-field] { padding-right:45px; }
    .btn { padding:12px 24px; border:none; border-radius:8px; cursor:pointer; font-size:16px; font-weight:500; transition:all 0.3s; }
    .btn-primary { background:#205A44; color:white; }
    .btn-primary:hover { background:#063A1C; }
    .btn-success { background:#10b981; color:white; }
    .btn-success:hover { background:#059669; }
    .card-title { font-size:18px; font-weight:600; color:#063A1C; margin-bottom:20px; display:flex; align-items:center; }
    .card-title svg { margin-right:10px; color:#205A44; }
    .info-row { display:flex; padding:12px 0; border-bottom:1px solid #f0f0f0; }
    .info-row:last-child { border-bottom:none; }
    .info-label { width:150px; font-weight:500; color:#B3B5B4; }
    .info-value { flex:1; color:#063A1C; }
    .alert { padding:12px 16px; border-radius:8px; margin-bottom:16px; font-size:14px; }
    .alert-success { background:#d1fae5; color:#065f46; border:1px solid #a7f3d0; }
    .alert-error { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }
    .edit-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; padding-bottom:16px; border-bottom:2px solid #f0f0f0; }
    .edit-actions { display:flex; gap:10px; }
    .marketing-logout-card {
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:14px;
    }
    .marketing-logout-title {
        margin:0 0 4px;
        color:#063A1C;
        font-size:18px;
        font-weight:700;
    }
    .marketing-logout-copy {
        margin:0;
        color:#6b7280;
        font-size:13px;
        line-height:1.45;
    }
    .btn-danger { background:#ef4444; color:#fff; }
    .btn-danger:hover { background:#dc2626; }
    @media (max-width: 768px) {
        .main-header { display: none !important; }
        .profile-card {
            padding: 16px;
            border-radius: 18px;
            margin-bottom: 12px;
            box-shadow: 0 10px 24px rgba(6, 58, 28, 0.06);
        }
        .edit-header {
            margin-bottom: 14px;
            padding-bottom: 12px;
        }
        .edit-header h2 {
            font-size: 22px !important;
        }
        .edit-actions .btn {
            padding: 9px 12px;
            font-size: 13px;
        }
        .profile-header {
            margin-bottom: 14px;
            padding-bottom: 14px;
        }
        .avatar {
            width: 58px;
            height: 58px;
            font-size: 24px;
            margin-right: 12px;
        }
        .avatar-upload-btn {
            width: 24px;
            height: 24px;
            font-size: 12px;
        }
        .profile-info h2 {
            font-size: 18px;
            margin-bottom: 2px;
        }
        .profile-info p {
            font-size: 12px;
        }
        .card-title {
            font-size: 16px;
            margin-bottom: 14px;
        }
        .form-group {
            margin-bottom: 12px;
        }
        .form-group label {
            margin-bottom: 5px;
            font-size: 12px;
            font-weight: 700;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 10px 11px;
            font-size: 14px;
            border-radius: 10px;
        }
        .info-row {
            padding: 9px 0;
            gap: 10px;
        }
        .info-label {
            width: 110px;
            font-size: 12px;
        }
        .info-value {
            font-size: 13px;
        }
        #uploadActions {
            display: none !important;
        }
        .marketing-logout-card {
            align-items:stretch;
            flex-direction:column;
        }
        .marketing-logout-card .btn {
            width:100%;
        }
    }
</style>
@endpush

@section('content')
    <div class="profile-card">
        <div class="edit-header">
            <h2 style="font-size: 24px; font-weight: 700; color: #063A1C; margin: 0;">Profile</h2>
            <div class="edit-actions">
                <button type="button" id="saveChangesBtn" class="btn btn-success" onclick="saveAllChanges()">Save Changes</button>
            </div>
        </div>
        <div class="profile-header">
            <div class="avatar-upload">
                <div class="avatar" id="avatar">
                    <span id="avatarInitial">{{ strtoupper(substr(auth()->user()->name ?? 'M', 0, 1)) }}</span>
                    <img id="avatarImage" src="{{ auth()->user()->profile_picture_url ?? '' }}" alt="Profile Picture" style="display: {{ auth()->user()->profile_picture ? 'block' : 'none' }};">
                </div>
                <label for="profilePictureInput" class="avatar-upload-btn" id="profilePictureLabel" title="Upload Profile Picture">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                </label>
                <input type="file" id="profilePictureInput" accept="image/jpeg,image/jpg,image/png">
            </div>
            <div class="profile-info">
                <h2 id="profileName">{{ auth()->user()->name ?? 'Marketing User' }}</h2>
                <p id="profileEmail">{{ auth()->user()->email ?? 'No email' }}</p>
            </div>
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

    <div class="profile-card" id="personalInfoCard">
        <div class="card-title">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
            </svg>
            Personal Information
        </div>
        <div id="personalInfoAlert"></div>
        <form id="personalInfoForm">
            <div class="form-group">
                <label for="profileNameInput">Name *</label>
                <input type="text" id="profileNameInput" name="name" value="{{ auth()->user()->name }}" required>
            </div>
            <div class="form-group">
                <label for="profilePhoneInput">Phone</label>
                <input type="text" id="profilePhoneInput" name="phone" value="{{ auth()->user()->phone ?? '' }}">
            </div>
            <div class="form-group">
                <label for="profileEmailInput">Email *</label>
                <input type="email" id="profileEmailInput" name="email" value="{{ auth()->user()->email }}" required>
            </div>
            <div class="info-row">
                <div class="info-label">Role</div>
                <div class="info-value">{{ auth()->user()->role->name ?? 'Marketing User' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Account Created</div>
                <div class="info-value">{{ auth()->user()->created_at ? auth()->user()->created_at->format('M d, Y') : '-' }}</div>
            </div>
        </form>
    </div>

    <div class="profile-card" id="passwordCard">
        <div class="card-title">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
            </svg>
            Change Password
        </div>
        <div id="passwordAlert"></div>
        <form id="passwordForm">
            <div class="form-group">
                <label for="currentPassword">Current Password *</label>
                <div class="password-input-wrapper">
                    <input type="password" id="currentPassword" name="current_password" required>
                    <button type="button" class="password-toggle" onclick="togglePasswordVisibility('currentPassword')" title="Show/Hide Password">
                        <svg class="w-5 h-5" id="currentPasswordToggleIcon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                    </button>
                </div>
            </div>
            <div class="form-group">
                <label for="newPassword">New Password *</label>
                <div class="password-input-wrapper">
                    <input type="password" id="newPassword" name="new_password" required minlength="8">
                    <button type="button" class="password-toggle" onclick="togglePasswordVisibility('newPassword')" title="Show/Hide Password">
                        <svg class="w-5 h-5" id="newPasswordToggleIcon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                    </button>
                </div>
                <small style="color: #B3B5B4; font-size: 12px; margin-top: 4px; display: block;">Minimum 8 characters</small>
            </div>
            <div class="form-group">
                <label for="confirmPassword">Confirm New Password *</label>
                <div class="password-input-wrapper">
                    <input type="password" id="confirmPassword" name="new_password_confirmation" required>
                    <button type="button" class="password-toggle" onclick="togglePasswordVisibility('confirmPassword')" title="Show/Hide Password">
                        <svg class="w-5 h-5" id="confirmPasswordToggleIcon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                    </button>
                </div>
            </div>
            <div style="display:flex;justify-content:flex-end;margin-top:18px;">
                <button type="submit" class="btn btn-primary" id="changePasswordBtn">Change Password</button>
            </div>
        </form>
    </div>

    <div class="profile-card marketing-logout-card">
        <div>
            <h3 class="marketing-logout-title">Logout</h3>
            <p class="marketing-logout-copy">Sign out from this device.</p>
        </div>
        <form action="{{ route('logout') }}" method="POST" style="margin:0;">
            @csrf
            <button type="submit" class="btn btn-danger">
                <i class="fas fa-sign-out-alt" style="margin-right:6px;"></i>
                Logout
            </button>
        </form>
    </div>
@endsection

@push('scripts')
<script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    function showAlert(containerId, message, type = 'success') {
        const container = document.getElementById(containerId);
        container.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
        setTimeout(() => container.innerHTML = '', 5000);
    }
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
    let selectedFile = null;
    document.getElementById('profilePictureInput').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        if (!file.type.match('image.*')) { showAlert('profilePictureAlert', 'Please select a valid image file', 'error'); return; }
        if (file.size > 2 * 1024 * 1024) { showAlert('profilePictureAlert', 'Image size must be less than 2MB', 'error'); return; }
        selectedFile = file;
        const reader = new FileReader();
        reader.onload = function(event) {
            const preview = document.getElementById('imagePreview');
            preview.src = event.target.result;
            preview.classList.add('show');
            document.getElementById('uploadActions').style.display = 'block';
        };
        reader.readAsDataURL(file);
    });
    async function uploadProfilePicture() {
        if (!selectedFile) { showAlert('profilePictureAlert', 'Please select an image first', 'error'); return; }
        const uploadBtn = document.querySelector('#uploadActions button.btn-primary');
        const originalText = uploadBtn.textContent;
        uploadBtn.disabled = true;
        uploadBtn.textContent = 'Uploading...';
        const formData = new FormData();
        formData.append('profile_picture', selectedFile);
        formData.append('_token', csrfToken);
        formData.append('_method', 'PUT');
        try {
            const response = await fetch('{{ route("users.update", auth()->user()->id) }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                credentials: 'same-origin',
                body: formData,
            });
            const result = await response.json();
            if (response.ok && result.success !== false) {
                showAlert('profilePictureAlert', 'Profile picture uploaded successfully!', 'success');
                if (result.user && result.user.profile_picture) {
                    updateAvatarDisplay(result.user.profile_picture, document.getElementById('profileName').textContent);
                }
                cancelPictureUpload();
            } else {
                showAlert('profilePictureAlert', result.message || 'Failed to upload profile picture', 'error');
            }
        } catch (error) {
            console.error('Upload Error:', error);
            showAlert('profilePictureAlert', 'Network error: Unable to upload picture', 'error');
        } finally {
            uploadBtn.disabled = false;
            uploadBtn.textContent = originalText;
        }
    }
    function cancelPictureUpload() {
        selectedFile = null;
        document.getElementById('profilePictureInput').value = '';
        document.getElementById('imagePreview').classList.remove('show');
        document.getElementById('uploadActions').style.display = 'none';
    }
    function togglePasswordVisibility(fieldId) {
        const field = document.getElementById(fieldId);
        const icon = document.getElementById(fieldId + 'ToggleIcon');
        if (field.type === 'password') {
            field.type = 'text';
            icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.29 3.29m13.532 13.532l-3.29-3.29M3 3l13.532 13.532"></path>';
        } else {
            field.type = 'password';
            icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>';
        }
    }
    async function saveAllChanges() {
        try {
            const nameInput = document.getElementById('profileNameInput');
            const emailInput = document.getElementById('profileEmailInput');
            const phoneInput = document.getElementById('profilePhoneInput');
            const nameChanged = nameInput.value !== '{{ auth()->user()->name }}';
            const emailChanged = emailInput.value !== '{{ auth()->user()->email }}';
            const phoneChanged = phoneInput.value !== '{{ auth()->user()->phone ?? "" }}';
            const currentPassword = document.getElementById('currentPassword')?.value || '';
            const newPassword = document.getElementById('newPassword')?.value || '';
            const confirmPassword = document.getElementById('confirmPassword')?.value || '';
            const profilePictureSelected = selectedFile !== null;
            const hasChanges = nameChanged || emailChanged || phoneChanged || (newPassword && currentPassword) || profilePictureSelected;
            if (!hasChanges) { showAlert('personalInfoAlert', 'No changes to save', 'error'); return; }
            let allSuccess = true;
            if (nameChanged || emailChanged || phoneChanged) {
                const response = await fetch('{{ route("users.update", auth()->user()->id) }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ name: nameInput.value, email: emailInput.value, phone: phoneInput.value, _token: csrfToken, _method: 'PUT' }),
                });
                const result = await response.json();
                if (response.ok && result.success !== false) {
                    showAlert('personalInfoAlert', 'Profile updated successfully', 'success');
                    document.getElementById('profileName').textContent = result.user?.name || nameInput.value;
                    document.getElementById('profileEmail').textContent = result.user?.email || emailInput.value;
                    document.getElementById('avatarInitial').textContent = (result.user?.name || nameInput.value).charAt(0).toUpperCase();
                } else {
                    showAlert('personalInfoAlert', result.message || 'Failed to update profile', 'error');
                    allSuccess = false;
                }
            }
            if (newPassword && currentPassword) {
                if (newPassword !== confirmPassword) {
                    showAlert('passwordAlert', 'New passwords do not match', 'error');
                    allSuccess = false;
                } else if (newPassword.length < 8) {
                    showAlert('passwordAlert', 'Password must be at least 8 characters', 'error');
                    allSuccess = false;
                } else {
                    const response = await fetch('{{ route("users.update", auth()->user()->id) }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                        credentials: 'same-origin',
                        body: JSON.stringify({ current_password: currentPassword, password: newPassword, password_confirmation: confirmPassword, _token: csrfToken, _method: 'PUT' }),
                    });
                    const result = await response.json();
                    if (response.ok && result.success !== false) {
                        showAlert('passwordAlert', 'Password changed successfully', 'success');
                        document.getElementById('passwordForm').reset();
                    } else {
                        showAlert('passwordAlert', result.message || 'Failed to change password', 'error');
                        allSuccess = false;
                    }
                }
            }
            if (profilePictureSelected && allSuccess) {
                await uploadProfilePicture();
            }
        } catch (error) {
            console.error('Error in saveAllChanges:', error);
            showAlert('personalInfoAlert', 'An error occurred while saving', 'error');
        }
    }
    document.getElementById('passwordForm').addEventListener('submit', function(event) {
        event.preventDefault();
        saveAllChanges();
    });
    window.saveAllChanges = saveAllChanges;
    window.togglePasswordVisibility = togglePasswordVisibility;
    window.uploadProfilePicture = uploadProfilePicture;
    window.cancelPictureUpload = cancelPictureUpload;
</script>
@endpush

@extends('sales-manager.layout')

@section('title', 'Profile - Senior Manager')
@section('page-title', 'Profile')

@php
    $profileUser = auth()->user();
    if ($profileUser && !$profileUser->relationLoaded('role')) {
        $profileUser->load('role', 'manager');
    }
    $profileUserName = $profileUser->name ?? 'User';
    $profileUserEmail = $profileUser->email ?? 'No email';
    $profileUserPhone = $profileUser->phone ?? '';
    $profileUserRole = $profileUser->role->name ?? 'Senior Manager';
    $profileUserManager = $profileUser->manager->name ?? 'Not Assigned';
    $profileUserJoinDate = $profileUser->created_at ? $profileUser->created_at->format('d M Y') : '-';
@endphp

@push('styles')
<style>
    .profile-shell {
        display: flex;
        flex-direction: column;
        gap: 18px;
        max-width: 1180px;
        margin: 0 auto;
    }
    .profile-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.2fr) minmax(320px, 0.8fr);
        gap: 18px;
        align-items: start;
    }
    .profile-main-stack,
    .profile-side-stack {
        display: flex;
        flex-direction: column;
        gap: 18px;
    }
    .profile-hero {
        position: relative;
        overflow: hidden;
        background:
            radial-gradient(circle at top right, rgba(32, 90, 68, 0.12), transparent 32%),
            linear-gradient(135deg, #ffffff 0%, #f5faf7 54%, #eef6f1 100%);
    }
    .profile-card {
        background: linear-gradient(180deg, #ffffff 0%, #fbfdfb 100%);
        padding: 24px;
        border-radius: 20px;
        border: 1px solid #dce8e1;
        box-shadow: 0 14px 40px rgba(12, 41, 30, 0.08);
        margin-bottom: 0;
    }
    .profile-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        margin-bottom: 22px;
        padding-bottom: 22px;
        border-bottom: 1px solid #dce8e1;
    }
    .avatar {
        width: 92px;
        height: 92px;
        border-radius: 50%;
        background: linear-gradient(135deg, #205A44 0%, #063A1C 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 34px;
        font-weight: 700;
        position: relative;
        overflow: hidden;
        box-shadow: 0 12px 28px rgba(32, 90, 68, 0.24);
    }
    .avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 50%;
    }
    .avatar-upload {
        position: relative;
        display: inline-flex;
        align-items: center;
        gap: 16px;
    }
    .avatar-upload-btn {
        position: absolute;
        bottom: 2px;
        right: 2px;
        background: #205A44;
        color: white;
        border: 2px solid white;
        border-radius: 50%;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 14px;
        box-shadow: 0 6px 16px rgba(32, 90, 68, 0.24);
    }
    .avatar-upload-btn:hover {
        background: #184634;
    }
    .avatar-upload input[type="file"] {
        display: none;
    }
    .profile-identity {
        display: flex;
        align-items: center;
        gap: 18px;
        min-width: 0;
        flex: 1;
    }
    .image-preview {
        max-width: 200px;
        max-height: 200px;
        border-radius: 16px;
        margin-top: 12px;
        display: none;
        border: 1px solid #dce8e1;
        box-shadow: 0 10px 24px rgba(15, 60, 42, 0.08);
    }
    .image-preview.show {
        display: block;
    }
    .profile-meta-kicker {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 12px;
        border-radius: 999px;
        background: rgba(32, 90, 68, 0.08);
        color: #205A44;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        margin-bottom: 12px;
    }
    .profile-info h2 {
        font-size: 30px;
        font-weight: 700;
        color: #063A1C;
        margin-bottom: 6px;
    }
    .profile-info p {
        color: #527264;
        font-size: 15px;
        margin: 0;
    }
    .app-version-pill {
        display: none;
        width: fit-content;
        margin-top: 8px;
        padding: 6px 10px;
        border-radius: 999px;
        background: #eef7f2;
        color: #205A44;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.02em;
    }
    .app-update-card {
        display: none;
    }
    .app-update-panel {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        padding: 14px;
        border: 1px solid #dce8e1;
        border-radius: 16px;
        background: #f7fbf8;
    }
    .app-update-copy strong {
        display: block;
        color: #063A1C;
        font-size: 15px;
        margin-bottom: 4px;
    }
    .app-update-copy span {
        color: #527264;
        font-size: 13px;
    }
    body.app-webview-mode .app-update-card {
        display: block;
    }
    .profile-info-copy {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .profile-quick-stats {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
    }
    .quick-stat-card {
        padding: 14px 16px;
        border-radius: 16px;
        border: 1px solid #dce8e1;
        background: rgba(255, 255, 255, 0.84);
    }
    .quick-stat-label {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #6b7f75;
        margin-bottom: 8px;
    }
    .quick-stat-value {
        font-size: 24px;
        font-weight: 800;
        color: #0f3c2a;
        line-height: 1;
    }
    .quick-stat-note {
        display: block;
        margin-top: 6px;
        font-size: 12px;
        color: #6b7f75;
    }
    .form-group {
        margin-bottom: 20px;
    }
    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: #294739;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .form-group input, .form-group select, .form-group textarea {
        width: 100%;
        padding: 14px 16px;
        border: 1px solid #cfe1d8;
        border-radius: 14px;
        font-size: 15px;
        transition: border-color 0.2s, box-shadow 0.2s;
        background: #ffffff;
        color: #063A1C;
    }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
        outline: none;
        border-color: #205A44;
        background: #ffffff;
        box-shadow: 0 0 0 4px rgba(32, 90, 68, 0.12);
    }
    .form-group input[readonly] {
        background: #f5f5f5;
        cursor: not-allowed;
    }
    .profile-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
    }
    .profile-form-grid .form-group.full-width {
        grid-column: 1 / -1;
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
        padding: 12px 20px;
        border: none;
        border-radius: 14px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 700;
        transition: transform 0.2s, box-shadow 0.2s, background 0.2s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    @media (max-width: 768px) {
        .profile-shell {
            gap: 12px;
        }
        .profile-grid {
            grid-template-columns: 1fr;
            gap: 12px;
        }
        .profile-main-stack,
        .profile-side-stack {
            gap: 12px;
        }
        .profile-card {
            padding: 16px;
            border-radius: 18px;
        }
        
        .profile-header {
            flex-direction: column;
            align-items: stretch;
            text-align: center;
            gap: 16px;
        }
        .profile-identity {
            flex-direction: column;
            text-align: center;
            gap: 14px;
        }
        .profile-meta-kicker {
            margin: 0 auto 10px;
        }
        
        .avatar {
            width: 74px;
            height: 74px;
            font-size: 28px;
        }
        
        .profile-info {
            width: 100%;
            text-align: center;
        }
        body.app-webview-mode .app-version-pill.is-visible {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-left: auto;
            margin-right: auto;
        }
        .app-update-panel {
            align-items: stretch;
            flex-direction: column;
        }
        
        .profile-info h2 {
            font-size: 24px;
        }
        .profile-quick-stats {
            grid-template-columns: 1fr;
        }
        .profile-form-grid {
            grid-template-columns: 1fr;
            gap: 0;
        }
        .info-row {
            flex-direction: column;
            align-items: flex-start;
        }
        .info-value {
            text-align: left;
        }
        .logout-panel {
            flex-direction: column;
            align-items: stretch;
        }
        .team-member-card {
            align-items: flex-start;
        }
        .team-member-meta {
            width: 100%;
            text-align: left;
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 10px;
            font-size: 14px;
        }
        
        .btn {
            width: 100%;
            padding: 12px;
            font-size: 14px;
        }
        .save-button {
            width: 100%;
        }
        
        .form-actions {
            flex-direction: column;
            gap: 10px;
        }
        
        .form-actions button {
            width: 100%;
        }
        
        /* Table responsive */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .table-responsive table {
            min-width: 600px;
        }
        
        .table-responsive th,
        .table-responsive td {
            padding: 8px 12px;
            font-size: 12px;
        }
        
        /* Grid responsive */
        .grid {
            grid-template-columns: 1fr !important;
            gap: 16px;
        }
        .team-summary-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
        }
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
        color: white;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        background: #205A44;
        color: white;
    }
    .btn-primary:hover {
        background: linear-gradient(135deg, #15803d 0%, #166534 100%);
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        background: #184634;
    }
    .btn-success {
        background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
        color: white;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        background: #10b981;
        color: white;
    }
    .btn-success:hover {
        background: linear-gradient(135deg, #15803d 0%, #166534 100%);
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        background: #059669;
    }
    .card-title {
        font-size: 18px;
        font-weight: 700;
        color: #063A1C;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }
    .card-title-text {
        display: inline-flex;
        align-items: center;
        gap: 10px;
    }
    .team-summary-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
        margin-bottom: 14px;
    }
    .team-summary-card {
        border-radius: 12px;
        border: 1px solid #dce8e1;
        background: #f5faf7;
        padding: 12px;
    }
    .team-summary-label {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #527264;
        margin-bottom: 6px;
    }
    .team-summary-value {
        font-size: 20px;
        font-weight: 700;
        color: #0f3c2a;
        line-height: 1;
    }
    .card-title i {
        margin-right: 10px;
        color: #205A44;
    }
    .info-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 14px 0;
        border-bottom: 1px solid #eef3ef;
    }
    .info-row:last-child {
        border-bottom: none;
    }
    .info-label {
        width: 150px;
        font-weight: 600;
        color: #6b7f75;
    }
    .info-value {
        flex: 1;
        color: #063A1C;
        text-align: right;
        font-weight: 600;
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
    .edit-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        margin-bottom: 22px;
        padding-bottom: 18px;
        border-bottom: 1px solid #dce8e1;
    }
    .edit-actions {
        display: flex;
        gap: 10px;
    }
    .profile-section-title {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .profile-section-title h2 {
        font-size: 28px;
        font-weight: 800;
        color: #063A1C;
        margin: 0;
    }
    .save-button {
        min-width: 170px;
    }
    .team-member-card {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 16px;
        border: 1px solid #e0e0e0;
        border-radius: 16px;
        margin-bottom: 12px;
        transition: all 0.2s;
        background: #fff;
    }
    .team-member-card:hover {
        border-color: #205A44;
        box-shadow: 0 10px 24px rgba(32, 90, 68, 0.08);
        transform: translateY(-1px);
    }
    .team-member-avatar {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: linear-gradient(135deg, #205A44 0%, #063A1C 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 18px;
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
        min-width: 0;
    }
    .team-member-name {
        font-weight: 600;
        color: #063A1C;
        margin-bottom: 4px;
    }
    .team-member-role {
        font-size: 13px;
        color: #B3B5B4;
    }
    .team-member-status {
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 500;
    }
    .team-member-meta {
        text-align: right;
        min-width: 120px;
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
        padding: 4px 8px;
        background: #f0f0f0;
        border-radius: 999px;
        font-size: 12px;
        color: #666;
        margin-left: 8px;
    }
    .activity-table-wrap {
        overflow-x: auto;
        border: 1px solid #e5efe8;
        border-radius: 16px;
    }
    .empty-state {
        text-align: center;
        color: #6b7f75;
        padding: 28px 16px;
        border: 1px dashed #cfe1d8;
        border-radius: 16px;
        background: #f8fcfa;
    }
    .logout-panel {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        padding: 4px 0;
    }
    .logout-copy h3 {
        margin: 0 0 6px;
        font-size: 18px;
        color: #063A1C;
    }
    .btn-danger {
        background: #b42318;
        color: #fff;
        box-shadow: 0 10px 20px rgba(180, 35, 24, 0.16);
    }
    .btn-danger:hover {
        background: #921d14;
        transform: translateY(-1px);
    }
</style>
@endpush

@section('content')
<div class="profile-shell">
    <!-- Profile Header -->
    <div class="profile-card profile-hero">
        <div class="edit-header">
            <div class="profile-section-title">
                <h2>Profile</h2>
            </div>
            <div class="edit-actions">
                <button type="button" id="saveChangesBtn" class="btn btn-success save-button" onclick="saveAllChanges()">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </div>
        <div class="profile-header">
            <div class="profile-identity">
                <div class="avatar-upload">
                    <div class="avatar" id="avatar">
                        <span id="avatarInitial">S</span>
                        <img id="avatarImage" src="" alt="Profile Picture" style="display: none;">
                    </div>
                    <label for="profilePictureInput" class="avatar-upload-btn" id="profilePictureLabel" title="Upload Profile Picture">
                        <i class="fas fa-camera"></i>
                    </label>
                    <input type="file" id="profilePictureInput" accept="image/jpeg,image/jpg,image/png">
                </div>
                <div class="profile-info">
                    <div class="profile-meta-kicker"><i class="fas fa-shield-alt"></i> ASM Account</div>
                    <div class="profile-info-copy">
                        <h2 id="profileName">{{ $profileUserName }}</h2>
                        <p id="profileEmail">{{ $profileUserEmail }}</p>
                        <div class="app-version-pill" id="appVersionPill">
                            <i class="fas fa-mobile-screen-button"></i>
                            <span>App v<span id="appVersionText">-</span></span>
                        </div>
                    </div>
                </div>
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
        <div class="profile-quick-stats">
            <div class="quick-stat-card">
                <div class="quick-stat-label">Team Members</div>
                <div class="quick-stat-value" id="profileHeroMembers">0</div>
                <span class="quick-stat-note" id="profileHeroMembersNote">Members assigned</span>
            </div>
            <div class="quick-stat-card">
                <div class="quick-stat-label">Available Today</div>
                <div class="quick-stat-value" id="profileHeroAvailable">0</div>
                <span class="quick-stat-note">Currently reachable</span>
            </div>
            <div class="quick-stat-card">
                <div class="quick-stat-label">Today Prospects</div>
                <div class="quick-stat-value" id="profileHeroProspects">0</div>
                <span class="quick-stat-note">Team output today</span>
            </div>
        </div>
    </div>

    <div class="profile-grid">
        <div class="profile-main-stack">
            <!-- Personal Information Card -->
            <div class="profile-card" id="personalInfoCard">
                <div class="card-title">
                    <span class="card-title-text"><i class="fas fa-user"></i> Personal Information</span>
                </div>
                <div id="personalInfoAlert"></div>
                <form id="personalInfoForm">
                    <div class="profile-form-grid">
                        <div class="form-group">
                            <label for="profileNameInput">Name *</label>
                            <input type="text" id="profileNameInput" name="name" value="{{ $profileUserName }}" required>
                        </div>
                        <div class="form-group">
                            <label for="profilePhoneInput">Phone</label>
                              <input type="text" id="profilePhoneInput" name="phone" value="{{ $profileUserPhone }}">
                        </div>
                        <div class="form-group full-width">
                            <label for="profileEmailInput">Email *</label>
                              <input type="email" id="profileEmailInput" name="email" value="{{ $profileUserEmail }}" required>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Role</div>
                        <div class="info-value" id="profileRole">{{ $profileUserRole }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Manager</div>
                        <div class="info-value" id="profileManager">{{ $profileUserManager }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Joining Date</div>
                        <div class="info-value" id="profileJoinDate">{{ $profileUserJoinDate }}</div>
                    </div>
                </form>
            </div>

            <!-- Team Members Card -->
            <div class="profile-card">
                <div class="card-title">
                    <span class="card-title-text"><i class="fas fa-users"></i> My Team</span>
                    <span class="stat-badge" id="teamStatsTotal">0 members</span>
                </div>
                <div class="team-summary-grid">
                    <div class="team-summary-card">
                        <div class="team-summary-label">Total</div>
                        <div class="team-summary-value" id="teamSummaryTotal">0</div>
                    </div>
                    <div class="team-summary-card">
                        <div class="team-summary-label">Available</div>
                        <div class="team-summary-value" id="teamSummaryAvailable">0</div>
                    </div>
                    <div class="team-summary-card">
                        <div class="team-summary-label">Absent</div>
                        <div class="team-summary-value" id="teamSummaryAbsent">0</div>
                    </div>
                    <div class="team-summary-card">
                        <div class="team-summary-label">Today Prospects</div>
                        <div class="team-summary-value" id="teamSummaryToday">0</div>
                    </div>
                </div>
                <div id="teamMembersContainer">
                    <div class="empty-state">Loading team members...</div>
                </div>
            </div>

            <div class="profile-card" id="leadOffCard">
                <div class="card-title">
                    <span class="card-title-text"><i class="fas fa-power-off"></i> Lead Off Mode</span>
                </div>
                <div id="leadOffAlert"></div>
                <div id="leadOffStatus">
                    <span class="team-member-status status-available" id="leadOffBadge">Lead On</span>
                </div>
                <p style="color:#6b7f75;font-size:13px;margin:12px 0 18px;">This only pauses new lead allocation. Already assigned leads remain with you.</p>
                <form id="leadOffForm" class="hidden">
                    <div class="profile-form-grid">
                        <div class="form-group full-width">
                            <label for="leadOffReason">Lead Off Reason</label>
                            <textarea id="leadOffReason" rows="3" placeholder="Enter reason for lead off"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="leadOffStart">Lead Off From</label>
                            <input type="datetime-local" id="leadOffStart">
                        </div>
                        <div class="form-group">
                            <label for="leadOffEnd">Lead Off Until</label>
                            <input type="datetime-local" id="leadOffEnd">
                        </div>
                    </div>
                    <div class="form-actions" style="display:flex;gap:10px;flex-wrap:wrap;">
                        <button type="submit" class="btn btn-primary">Save Lead Off Window</button>
                        <button type="button" class="btn" style="background:#6b7280;color:#fff;" onclick="cancelLeadOffForm()">Cancel</button>
                    </div>
                </form>
                <div id="leadOffActions" style="margin-top: 8px;">
                    <button type="button" class="btn btn-primary" onclick="showLeadOffForm()">Configure Lead Off</button>
                </div>
            </div>
        </div>

        <div class="profile-side-stack">
            <div class="profile-card app-update-card" id="appUpdateCard">
                <div class="card-title">
                    <span class="card-title-text"><i class="fas fa-download"></i> App Update</span>
                </div>
                <div id="appUpdateAlert"></div>
                <div class="app-update-panel">
                    <div class="app-update-copy">
                        <strong>{{ brand_name() }} Android App</strong>
                        <span>Yahan se latest APK check/update kar sakte hain.</span>
                    </div>
                    <button type="button" class="btn btn-primary" id="checkAppUpdateBtn" onclick="checkNativeAppUpdate()">
                        <i class="fas fa-rotate"></i> Check Update
                    </button>
                </div>
            </div>

            <div class="profile-card app-update-card" id="appPermissionHealthCard" style="display:none;">
                <div class="card-title">
                    <span class="card-title-text"><i class="fas fa-stethoscope"></i> App Diagnostics</span>
                </div>
                <div id="appPermissionAlert"></div>
                <div class="app-update-panel">
                    <div class="app-update-copy">
                        <strong>Diagnostics Center</strong>
                        <span>Caller ID, call sync, recording, permissions aur app version ka report admin ko bhejein.</span>
                    </div>
                    <button type="button" class="btn btn-primary" id="checkAppPermissionBtn" onclick="openNativePermissionHealth()">
                        <i class="fas fa-list-check"></i> Open Diagnostics
                    </button>
                </div>
            </div>

            <!-- Password Change Card -->
            <div class="profile-card" id="passwordCard">
        <div class="card-title">
            <span class="card-title-text"><i class="fas fa-lock"></i> Change Password</span>
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
                <small style="color: #6b7f75; font-size: 12px; margin-top: 6px; display: block;">Minimum 8 characters</small>
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
            <span class="card-title-text"><i class="fas fa-history"></i> Recent Activity</span>
        </div>
        <div id="activityHistory">
            <div class="empty-state">Loading...</div>
        </div>
    </div>

    <!-- Logout Card -->
    <div class="profile-card">
        <div class="card-title">
            <span class="card-title-text"><i class="fas fa-sign-out-alt"></i> Account Actions</span>
        </div>
        <div class="logout-panel">
            <div class="logout-copy">
                <h3>Logout</h3>
            </div>
            <form action="{{ route('logout') }}" method="POST" style="margin: 0;">
                @csrf
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </form>
        </div>
    </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const API_BASE_URL = '{{ url("/api/sales-manager") }}';
    
    function getToken() {
        return typeof window.getManagerApiToken === 'function' ? window.getManagerApiToken() : '{{ session("api_token") }}';
    }

    // API call helper
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
                window.handleManagerAuthFailure('profile');
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

    async function checkNativeAppUpdate() {
        const btn = document.getElementById('checkAppUpdateBtn');
        if (!window.flutter_inappwebview?.callHandler) {
            showAlert('appUpdateAlert', 'Update check sirf Android app me available hai.', 'error');
            return;
        }

        const oldText = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking...';
        }

        try {
            const result = await window.flutter_inappwebview.callHandler('nativeCheckAppUpdate');
            if (result?.status === 'latest') {
                updateAppVersionPill({ versionLabel: result.versionLabel });
                showAlert('appUpdateAlert', 'Aapka app already latest hai.', 'success');
            } else if (result?.status === 'opened') {
                showAlert('appUpdateAlert', 'Update download open ho gaya hai.', 'success');
            } else if (result?.status === 'force_update') {
                showAlert('appUpdateAlert', 'Required update available hai. Update screen open ho gayi hai.', 'success');
            } else if (result?.success === false) {
                showAlert('appUpdateAlert', result.message || 'Update check fail hua.', 'error');
            }
        } catch (error) {
            showAlert('appUpdateAlert', 'Update check fail hua. Thodi der baad try karein.', 'error');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = oldText;
            }
        }
    }

    async function openNativePermissionHealth() {
        if (!window.flutter_inappwebview?.callHandler) {
            showAlert('appPermissionAlert', 'Permission check sirf Android app me available hai.', 'error');
            return;
        }

        try {
            await window.flutter_inappwebview.callHandler('nativeOpenPermissionHealth');
        } catch (error) {
            showAlert('appPermissionAlert', 'Permission screen open nahi ho payi. App update karke try karein.', 'error');
        }
    }

    function updateAppVersionPill(meta) {
        const pill = document.getElementById('appVersionPill');
        const text = document.getElementById('appVersionText');
        if (!pill || !text) return;

        const appMeta = meta || window.__BASE_CRM_APP || null;
        const label = appMeta?.versionLabel || (
            appMeta?.versionName && appMeta?.versionCode
                ? `${appMeta.versionName}+${appMeta.versionCode}`
                : ''
        );

        if (!label) return;
        text.textContent = label;
        pill.classList.add('is-visible');
    }

    window.addEventListener('base-crm-app-meta', function(event) {
        updateAppVersionPill(event.detail);
    });
    function updateDiagnosticsVisibility(features) {
        const card = document.getElementById('appPermissionHealthCard');
        if (!card) return;
        const enabled = !!(features || window.__BASE_CRM_MOBILE_FEATURES || {}).diagnosticsEnabled;
        card.style.display = enabled ? '' : 'none';
    }
    window.addEventListener('base-crm-mobile-features', function(event) {
        updateDiagnosticsVisibility(event.detail);
    });
    document.addEventListener('DOMContentLoaded', function() {
        updateAppVersionPill();
        updateDiagnosticsVisibility();
    });

    // Load profile data
    async function loadProfile() {
        try {
            const data = await apiCall('/profile');
            
            if (!data || !data.user) {
                console.error('Failed to load profile');
                return;
            }

            const user = data.user;

            // Update header
            document.getElementById('profileName').textContent = user.name || 'Senior Manager';
            document.getElementById('profileEmail').textContent = user.email || 'No email';
            document.getElementById('avatarInitial').textContent = (user.name || 'S').charAt(0).toUpperCase();
            
            // Update profile picture
            updateAvatarDisplay(user.profile_picture, user.name);

            // Update personal info form
            document.getElementById('profileNameInput').value = user.name || '';
            document.getElementById('profileNameInput').defaultValue = user.name || '';
            document.getElementById('profilePhoneInput').value = user.phone || '';
            document.getElementById('profilePhoneInput').defaultValue = user.phone || '';
            document.getElementById('profileEmailInput').value = user.email || '';
            document.getElementById('profileEmailInput').defaultValue = user.email || '';
            
            // Update role, manager, and joining date
            document.getElementById('profileRole').textContent = user.role || '-';
            document.getElementById('profileManager').textContent = user.manager || 'Not Assigned';
            document.getElementById('profileJoinDate').textContent = user.created_at || '-';

            if (data.profile) {
                updateLeadOffDisplay(data.profile);
            }

            // Load team members
            if (data.team_members) {
                loadTeamMembers(data.team_members, data.team_stats);
            }

            // Load activity history
            if (data.activity_history) {
                loadActivityHistory(data.activity_history);
            }
        } catch (error) {
            console.error('Error loading profile:', error);
        }
    }

    // Load team members
    function loadTeamMembers(teamMembers, teamStats) {
        const container = document.getElementById('teamMembersContainer');
        const statsTotal = document.getElementById('teamStatsTotal');
        const summaryTotal = document.getElementById('teamSummaryTotal');
        const summaryAvailable = document.getElementById('teamSummaryAvailable');
        const summaryAbsent = document.getElementById('teamSummaryAbsent');
        const summaryToday = document.getElementById('teamSummaryToday');
        const heroMembers = document.getElementById('profileHeroMembers');
        const heroMembersNote = document.getElementById('profileHeroMembersNote');
        const heroAvailable = document.getElementById('profileHeroAvailable');
        const heroProspects = document.getElementById('profileHeroProspects');
        
        if (teamStats) {
            statsTotal.textContent = `${teamStats.total_members} members (${teamStats.available_members} available)`;
            if (summaryTotal) summaryTotal.textContent = teamStats.total_members || 0;
            if (summaryAvailable) summaryAvailable.textContent = teamStats.available_members || 0;
            if (summaryAbsent) summaryAbsent.textContent = Math.max((teamStats.total_members || 0) - (teamStats.available_members || 0), 0);
            if (summaryToday) summaryToday.textContent = teamStats.today_prospects || 0;
            if (heroMembers) heroMembers.textContent = teamStats.total_members || 0;
            if (heroMembersNote) heroMembersNote.textContent = `${teamStats.available_members || 0} available right now`;
            if (heroAvailable) heroAvailable.textContent = teamStats.available_members || 0;
            if (heroProspects) heroProspects.textContent = teamStats.today_prospects || 0;
        } else {
            if (summaryTotal) summaryTotal.textContent = 0;
            if (summaryAvailable) summaryAvailable.textContent = 0;
            if (summaryAbsent) summaryAbsent.textContent = 0;
            if (summaryToday) summaryToday.textContent = 0;
            if (heroMembers) heroMembers.textContent = 0;
            if (heroMembersNote) heroMembersNote.textContent = 'Members assigned';
            if (heroAvailable) heroAvailable.textContent = 0;
            if (heroProspects) heroProspects.textContent = 0;
        }
        
        if (!teamMembers || teamMembers.length === 0) {
            container.innerHTML = '<div class="empty-state">No team members found</div>';
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
                    <div class="team-member-role">${member.role} • ${member.email}</div>
                </div>
                <div class="team-member-meta">
                    <span class="team-member-status ${member.is_absent ? 'status-absent' : 'status-available'}">
                        ${member.is_absent ? 'Absent' : 'Available'}
                    </span>
                    ${member.today_prospects ? `<div class="stat-badge" style="margin-top: 4px;">Today: ${member.today_prospects} prospects</div>` : ''}
                </div>
            </div>
        `).join('');
        
        container.innerHTML = html;
    }

    function updateLeadOffDisplay(profile) {
        const card = document.getElementById('leadOffCard');
        const badge = document.getElementById('leadOffBadge');
        const actions = document.getElementById('leadOffActions');
        const form = document.getElementById('leadOffForm');

        if (!card || !badge || !actions || !form) {
            return;
        }

        if (!profile.lead_off_supported) {
            card.style.display = 'none';
            return;
        }

        card.style.display = '';

        if (profile.is_absent) {
            badge.className = 'team-member-status status-absent';
            badge.textContent = 'Lead Off';
            if (profile.absent_reason) {
                badge.textContent += ` - ${profile.absent_reason}`;
            }
            if (profile.lead_off_end_at || profile.absent_until) {
                badge.textContent += ` (Until: ${new Date(profile.lead_off_end_at || profile.absent_until).toLocaleString()})`;
            }
            actions.innerHTML = '<button type="button" class="btn btn-success" onclick="turnLeadOn()">Turn Lead On</button>';
            form.classList.add('hidden');
        } else if (profile.has_scheduled_lead_off) {
            badge.className = 'team-member-status status-absent';
            badge.textContent = `Scheduled from ${new Date(profile.lead_off_start_at).toLocaleString()}`;
            actions.innerHTML = '<button type="button" class="btn btn-success" onclick="turnLeadOn()">Turn Lead On</button>';
            form.classList.add('hidden');
        } else {
            badge.className = 'team-member-status status-available';
            badge.textContent = 'Lead On';
            actions.innerHTML = '<button type="button" class="btn btn-primary" onclick="showLeadOffForm()">Configure Lead Off</button>';
            form.classList.add('hidden');
        }
    }

    function localDateTimeValue(date = new Date()) {
        const tzAdjusted = new Date(date.getTime() - (date.getTimezoneOffset() * 60000));
        return tzAdjusted.toISOString().slice(0, 16);
    }

    function showLeadOffForm() {
        const form = document.getElementById('leadOffForm');
        const actions = document.getElementById('leadOffActions');
        if (!form || !actions) {
            return;
        }

        form.classList.remove('hidden');
        actions.classList.add('hidden');
        document.getElementById('leadOffStart').value = localDateTimeValue();
    }

    function cancelLeadOffForm() {
        const form = document.getElementById('leadOffForm');
        const actions = document.getElementById('leadOffActions');
        if (!form || !actions) {
            return;
        }

        form.classList.add('hidden');
        actions.classList.remove('hidden');
        form.reset();
    }

    async function turnLeadOn() {
        const result = await apiCall('/profile/availability', {
            method: 'POST',
            body: JSON.stringify({
                is_absent: false,
                absent_reason: null,
                absent_until: null,
                lead_off_start_at: null,
                lead_off_end_at: null,
            }),
        });

        if (result && result.success) {
            showAlert('leadOffAlert', result.message, 'success');
            loadProfile();
            return;
        }

        showAlert('leadOffAlert', result?.message || 'Failed to update lead off mode', 'error');
    }

    // Load activity history
    function loadActivityHistory(activities) {
        const container = document.getElementById('activityHistory');
        
        if (!activities || activities.length === 0) {
            container.innerHTML = '<div class="empty-state">No activity history found</div>';
            return;
        }

        const table = `
            <div class="activity-table-wrap">
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
            </div>
        `;
        container.innerHTML = table;
    }

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
        if (!file) return;

        if (!file.type.match('image.*')) {
            showAlert('profilePictureAlert', 'Please select a valid image file', 'error');
            return;
        }

        if (file.size > 2 * 1024 * 1024) {
            showAlert('profilePictureAlert', 'Image size must be less than 2MB', 'error');
            return;
        }

        selectedFile = file;

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

        const uploadBtn = document.querySelector('#uploadActions button.btn-primary');
        const originalText = uploadBtn.textContent;
        uploadBtn.disabled = true;
        uploadBtn.textContent = 'Uploading...';

        const formData = new FormData();
        formData.append('profile_picture', selectedFile);

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        
        try {
            const response = await fetch(`${API_BASE_URL}/profile/picture`, {
                method: 'POST',
                headers: window.getManagerAuthHeaders({
                    'Authorization': `Bearer ${token}`,
                    'X-CSRF-TOKEN': csrfToken,
                }),
                credentials: 'same-origin',
                body: formData,
            });

            if (response.status === 401 || response.status === 403) {
                window.handleManagerAuthFailure('profile picture');
                window.location.href = '{{ route("login") }}';
                return;
            }

            const result = await response.json();

            if (result.success) {
                showAlert('profilePictureAlert', 'Profile picture uploaded successfully!', 'success');
                updateAvatarDisplay(result.profile_picture, document.getElementById('profileName').textContent);
                cancelPictureUpload();
            } else {
                showAlert('profilePictureAlert', result.message || 'Failed to upload profile picture', 'error');
            }

            uploadBtn.disabled = false;
            uploadBtn.textContent = originalText;
        } catch (error) {
            console.error('Upload Error:', error);
            showAlert('profilePictureAlert', 'Network error: Unable to upload picture', 'error');
            uploadBtn.disabled = false;
            uploadBtn.textContent = originalText;
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
            const nameInput = document.getElementById('profileNameInput');
            const emailInput = document.getElementById('profileEmailInput');
            const phoneInput = document.getElementById('profilePhoneInput');
            
            const nameChanged = nameInput.value !== (nameInput.defaultValue || '');
            const emailChanged = emailInput.value !== (emailInput.defaultValue || '');
            const phoneChanged = phoneInput.value !== (phoneInput.defaultValue || '');
            
            const currentPassword = document.getElementById('currentPassword')?.value || '';
            const newPassword = document.getElementById('newPassword')?.value || '';
            const confirmPassword = document.getElementById('confirmPassword')?.value || '';
            
            const profilePictureSelected = selectedFile !== null;
            
            let hasChanges = nameChanged || emailChanged || phoneChanged || (newPassword && currentPassword) || profilePictureSelected;
            
            if (!hasChanges) {
                showAlert('personalInfoAlert', 'No changes to save', 'error');
                return;
            }
            
            let allSuccess = true;
            
            // Save profile info if changed
            if (nameChanged || emailChanged || phoneChanged) {
                const formData = {
                    name: nameInput.value,
                    email: emailInput.value,
                    phone: phoneInput.value,
                };

                const result = await apiCall('/profile', {
                    method: 'PUT',
                    body: JSON.stringify(formData),
                });

                if (result && result.success) {
                    showAlert('personalInfoAlert', 'Profile updated successfully', 'success');
                    
                    if (result.user) {
                        document.getElementById('profileName').textContent = result.user.name;
                        document.getElementById('profileEmail').textContent = result.user.email;
                        document.getElementById('avatarInitial').textContent = result.user.name.charAt(0).toUpperCase();
                    }
                    
                    nameInput.defaultValue = nameInput.value;
                    emailInput.defaultValue = emailInput.value;
                    phoneInput.defaultValue = phoneInput.value;
                } else {
                    showAlert('personalInfoAlert', result?.message || 'Failed to update profile', 'error');
                    allSuccess = false;
                }
            }
            
            // Save password if changed
            if (newPassword && currentPassword) {
                if (newPassword !== confirmPassword) {
                    showAlert('passwordAlert', 'New passwords do not match', 'error');
                    allSuccess = false;
                } else if (newPassword.length < 8) {
                    showAlert('passwordAlert', 'Password must be at least 8 characters', 'error');
                    allSuccess = false;
                } else {
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
                        document.getElementById('currentPassword').value = '';
                        document.getElementById('newPassword').value = '';
                        document.getElementById('confirmPassword').value = '';
                    } else {
                        showAlert('passwordAlert', result?.message || 'Failed to change password', 'error');
                        allSuccess = false;
                    }
                }
            }
            
            // Save profile picture if selected
            if (profilePictureSelected && allSuccess) {
                await uploadProfilePicture();
            }
        } catch (error) {
            console.error('Error in saveAllChanges:', error);
            showAlert('personalInfoAlert', 'An error occurred while saving', 'error');
        }
    }

    // Make functions globally accessible
    window.saveAllChanges = saveAllChanges;
    window.togglePasswordVisibility = togglePasswordVisibility;
    window.uploadProfilePicture = uploadProfilePicture;
    window.cancelPictureUpload = cancelPictureUpload;
    window.showLeadOffForm = showLeadOffForm;
    window.cancelLeadOffForm = cancelLeadOffForm;
    window.turnLeadOn = turnLeadOn;

    const leadOffForm = document.getElementById('leadOffForm');
    if (leadOffForm) {
        leadOffForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const result = await apiCall('/profile/availability', {
                method: 'POST',
                body: JSON.stringify({
                    is_absent: true,
                    absent_reason: document.getElementById('leadOffReason')?.value || '',
                    lead_off_start_at: document.getElementById('leadOffStart')?.value || '',
                    lead_off_end_at: document.getElementById('leadOffEnd')?.value || '',
                }),
            });

            if (result && result.success) {
                showAlert('leadOffAlert', result.message, 'success');
                loadProfile();
                return;
            }

            showAlert('leadOffAlert', result?.message || 'Failed to update lead off mode', 'error');
        });
    }
    
    // Initialize on page load
    (function() {
        loadProfile();
    })();
</script>
@endpush

@extends('layouts.app')

@section('title', 'System Settings - ' . brand_name())
@section('page-title', 'System Settings')

@section('header-actions')
    <a href="{{ route('admin.dashboard') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors duration-200 text-sm font-medium">
        <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
    </a>
@endsection

@push('styles')
<style>
    .section-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        border: 1px solid #E5DED4;
        padding: 24px;
        margin-bottom: 24px;
    }
    .section-title {
        font-size: 18px;
        font-weight: 600;
        color: var(--text-color);
        margin-bottom: 20px;
        padding-bottom: 12px;
        border-bottom: 2px solid #E5DED4;
        display: flex;
        align-items: center;
    }
    .section-title i {
        margin-right: 10px;
        color: var(--gradient-start);
    }
    .toggle-switch {
        position: relative;
        display: inline-block;
        width: 60px;
        height: 34px;
    }
    .toggle-switch input { opacity: 0; width: 0; height: 0; }
    .slider {
        position: absolute;
        cursor: pointer;
        top: 0; left: 0; right: 0; bottom: 0;
        background-color: #ccc;
        transition: .4s;
        border-radius: 34px;
    }
    .slider:before {
        position: absolute;
        content: "";
        height: 26px; width: 26px;
        left: 4px; bottom: 4px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
    }
    input:checked + .slider {
        background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
    }
    input:checked + .slider:before { transform: translateX(26px); }
    .btn-primary {
        background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
        color: white; border: none;
        padding: 10px 20px; border-radius: 8px;
        cursor: pointer; font-weight: 500;
        transition: all 0.3s; font-size: 13px;
        display: inline-flex; align-items: center; gap: 6px;
    }
    .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 8px rgba(0,0,0,0.15); }
    .btn-cache {
        background: #f3f4f6;
        color: #374151; border: 1px solid #e5e7eb;
        padding: 10px 16px; border-radius: 8px;
        cursor: pointer; font-weight: 500;
        transition: all 0.2s; font-size: 13px;
        display: inline-flex; align-items: center; gap: 6px;
    }
    .btn-cache:hover {
        background: #e5e7eb;
        border-color: #9ca3af;
        transform: translateY(-1px);
    }
    .btn-cache:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
    .status-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }
    .status-badge.active  { background: #d1fae5; color: #065f46; }
    .status-badge.inactive { background: #fee2e2; color: #991b1b; }
    .alert { padding: 14px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
    .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #86efac; }
    .alert-error   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
    .alert-info    { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }
    .sound-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 14px; }
    .sound-card { border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px; background: #fff; }
    .sound-card-title { font-size: 14px; font-weight: 700; color: #111827; margin-bottom: 4px; }
    .sound-card-meta { font-size: 12px; color: #6b7280; min-height: 18px; margin-bottom: 10px; overflow-wrap: anywhere; }
    .sound-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
    .settings-form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
    .settings-field label { display: block; font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 6px; }
    .settings-input {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        background: white;
        font-size: 13px;
    }
    .settings-help { font-size: 11px; color: #6b7280; margin-top: 5px; }
    .command-output {
        background: #1e1e1e; color: #d4d4d4;
        padding: 14px; border-radius: 8px;
        font-family: 'Courier New', monospace;
        font-size: 12px; max-height: 300px;
        overflow-y: auto; white-space: pre-wrap;
        word-wrap: break-word; margin-top: 12px;
    }
    .settings-table-wrap {
        overflow-x: auto;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
    }
    .settings-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 680px;
    }
    .settings-table th,
    .settings-table td {
        padding: 12px 14px;
        border-bottom: 1px solid #e5e7eb;
        text-align: left;
        font-size: 13px;
        vertical-align: middle;
    }
    .settings-table th {
        background: #f9fafb;
        color: #374151;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        font-weight: 700;
    }
    .settings-table tr:last-child td {
        border-bottom: none;
    }
    .settings-select {
        width: 100%;
        min-width: 180px;
        padding: 9px 12px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        background: white;
        font-size: 13px;
    }
    .asset-default-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.6fr) minmax(280px, 0.9fr);
        gap: 24px;
    }
    .asset-upload-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
    }
    .asset-upload-card {
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        padding: 16px;
        background: #fcfcfb;
    }
    .asset-preview-wrap {
        position: relative;
        margin-bottom: 12px;
    }
    .asset-preview {
        width: 100%;
        aspect-ratio: 16 / 9;
        object-fit: cover;
        border-radius: 12px;
        border: 1px solid #e5e7eb;
        background: #f3f4f6;
        display: block;
    }
    .asset-skeleton {
        width: 100%;
        aspect-ratio: 16 / 9;
        border-radius: 12px;
        border: 1px solid #e5e7eb;
        background: linear-gradient(110deg, #eef2ef 8%, #f8faf8 18%, #eef2ef 33%);
        background-size: 200% 100%;
        animation: shimmer 1.6s linear infinite;
        display: none;
        position: relative;
        overflow: hidden;
    }
    .asset-skeleton-copy {
        position: absolute;
        inset: auto 14px 14px 14px;
        border-radius: 10px;
        background: rgba(255,255,255,0.92);
        padding: 10px 12px;
        font-size: 11px;
        color: #4b5563;
        border: 1px solid rgba(229,231,235,0.9);
    }
    .asset-upload-card.is-empty .asset-preview {
        display: none;
    }
    .asset-upload-card.is-empty .asset-skeleton {
        display: block;
    }
    .asset-status {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        margin-bottom: 10px;
    }
    .asset-status.custom {
        background: #d9f4e6;
        color: #0f5a37;
    }
    .asset-status.fallback {
        background: #f3f4f6;
        color: #4b5563;
    }
    .asset-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 8px;
    }
    .asset-recommendation {
        font-size: 11px;
        color: #6b7280;
        margin-bottom: 10px;
    }
    .asset-actions {
        display: flex;
        gap: 8px;
        margin-top: 12px;
    }
    .asset-action-btn {
        border: 1px solid #d1d5db;
        border-radius: 10px;
        background: white;
        color: #374151;
        padding: 8px 12px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .asset-action-btn:hover {
        transform: translateY(-1px);
        border-color: #9ca3af;
    }
    .asset-action-btn:disabled {
        cursor: not-allowed;
        opacity: 0.6;
        transform: none;
    }
    .asset-action-btn.reset {
        color: #b91c1c;
        border-color: #fecaca;
        background: #fff7f7;
    }
    .asset-hint {
        font-size: 11px;
        color: #6b7280;
        margin-top: 8px;
    }
    .asset-side-note {
        border: 1px solid #dce7e0;
        border-radius: 14px;
        background: linear-gradient(180deg, #f8fbf9 0%, #f3f7f4 100%);
        padding: 18px;
    }
    .mini-note {
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 12px 14px;
        background: #fff;
        margin-top: 12px;
    }
    @media (max-width: 1024px) {
        .asset-default-grid,
        .asset-upload-grid {
            grid-template-columns: 1fr;
        }
    }
    @keyframes shimmer {
        0% { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }
</style>
@endpush

@section('content')
<div>

    {{-- Alert --}}
    <div id="message-container" class="mb-5" style="display:none;">
        <div id="message-alert" class="alert"></div>
    </div>

    {{-- 1. Maintenance Mode --}}
    <div class="section-card">
        <div class="section-title">
            <i class="fas fa-tools"></i> Maintenance Mode
        </div>

        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-base font-semibold text-gray-900 mb-1">System Maintenance</h3>
                <p class="text-sm text-gray-500">
                    When enabled, all users (except admin) will be logged out and unable to access the system.
                </p>
            </div>
            <div class="flex items-center gap-3 flex-shrink-0 ml-4">
                <span class="status-badge {{ $maintenanceMode ? 'active' : 'inactive' }}" id="maintenance-badge">
                    {{ $maintenanceMode ? 'ENABLED' : 'DISABLED' }}
                </span>
                <label class="toggle-switch">
                    <input type="checkbox" id="maintenance-toggle" {{ $maintenanceMode ? 'checked' : '' }}>
                    <span class="slider"></span>
                </label>
            </div>
        </div>

        <div id="maintenance-message-section" class="mb-4" style="{{ !$maintenanceMode ? 'display:none;' : '' }}">
            <label for="maintenance-message" class="block text-sm font-medium text-gray-700 mb-1">
                Maintenance Message
            </label>
            <textarea id="maintenance-message" rows="2"
                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand focus:border-brand text-sm"
                      placeholder="Enter a custom maintenance message...">{{ $maintenanceMessage }}</textarea>
        </div>

        <button onclick="toggleMaintenanceMode()" class="btn-primary" id="maintenance-btn">
            <i class="fas fa-power-off"></i>
            {{ $maintenanceMode ? 'Disable' : 'Enable' }} Maintenance Mode
        </button>
    </div>

    {{-- 2. User & Email Notifications --}}
    <div class="section-card">
        <div class="section-title">
            <i class="fas fa-user-plus"></i> User & Email Notifications
        </div>
        <p class="text-sm text-gray-500 mb-4">
            When a new user is created, send them a welcome email with credentials and notify admins.
        </p>

        <div class="space-y-4 mb-5">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Send welcome email to new user</h3>
                    <p class="text-xs text-gray-500 mt-0.5">New user receives an email with name, password, position, and login link.</p>
                </div>
                <label class="toggle-switch flex-shrink-0 ml-4">
                    <input type="checkbox" id="send-welcome-email-toggle" {{ $sendWelcomeEmailToNewUser ? 'checked' : '' }}>
                    <span class="slider"></span>
                </label>
            </div>
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Notify admin when a new user is created</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Admin gets an in-app notification with the new user's name and email.</p>
                </div>
                <label class="toggle-switch flex-shrink-0 ml-4">
                    <input type="checkbox" id="notify-admin-toggle" {{ $notifyAdminOnNewUser ? 'checked' : '' }}>
                    <span class="slider"></span>
                </label>
            </div>
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Mute user notifications during quiet hours</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Non-admin users will not receive popup, ringtone, or push notifications in this window.</p>
                </div>
                <label class="toggle-switch flex-shrink-0 ml-4">
                    <input type="checkbox" id="notification-quiet-hours-toggle" {{ $notificationQuietHoursEnabled ? 'checked' : '' }}>
                    <span class="slider"></span>
                </label>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="notification-quiet-start" class="block text-xs font-semibold text-gray-700 mb-1">Quiet hours start</label>
                    <input type="time" id="notification-quiet-start" value="{{ substr((string) $notificationQuietHoursStart, 0, 5) }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand focus:border-brand text-sm">
                </div>
                <div>
                    <label for="notification-quiet-end" class="block text-xs font-semibold text-gray-700 mb-1">Quiet hours end</label>
                    <input type="time" id="notification-quiet-end" value="{{ substr((string) $notificationQuietHoursEnd, 0, 5) }}"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand focus:border-brand text-sm">
                </div>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <button type="button" onclick="saveUserNotificationSettings()" class="btn-primary" id="user-notifications-btn">
                <i class="fas fa-save"></i> Save Settings
            </button>
            <a href="{{ route('admin.system-settings.test-email') }}"
               class="btn-cache">
                <i class="fas fa-envelope"></i> Test Email
            </a>

        </div>
    </div>

    <div class="section-card">
        <div class="section-title">
            <i class="fas fa-envelope-open-text"></i> SMTP Mail Settings
        </div>
        <p class="text-sm text-gray-500 mb-4">
            Configure outgoing email for login OTP, welcome email, and reports. Password blank chhodne par existing password preserve rahega.
        </p>

        <div class="flex items-center justify-between mb-5">
            <div>
                <h3 class="text-sm font-semibold text-gray-900">Use SMTP settings from admin panel</h3>
                <p class="text-xs text-gray-500 mt-0.5">Enabled hone par Laravel mail config DB settings se override hoga.</p>
            </div>
            <label class="toggle-switch flex-shrink-0 ml-4">
                <input type="checkbox" id="mail-smtp-enabled" {{ ($mailSettings['enabled'] ?? false) ? 'checked' : '' }}>
                <span class="slider"></span>
            </label>
        </div>

        <div class="settings-form-grid">
            <div class="settings-field">
                <label for="mail-smtp-host">SMTP Host</label>
                <input id="mail-smtp-host" class="settings-input" value="{{ $mailSettings['host'] ?? '' }}" placeholder="smtp.hostinger.com">
            </div>
            <div class="settings-field">
                <label for="mail-smtp-port">SMTP Port</label>
                <input id="mail-smtp-port" type="number" class="settings-input" value="{{ $mailSettings['port'] ?? 587 }}" placeholder="587">
            </div>
            <div class="settings-field">
                <label for="mail-smtp-encryption">Encryption</label>
                <select id="mail-smtp-encryption" class="settings-input">
                    <option value="tls" {{ ($mailSettings['encryption'] ?? '') === 'tls' ? 'selected' : '' }}>TLS</option>
                    <option value="ssl" {{ ($mailSettings['encryption'] ?? '') === 'ssl' ? 'selected' : '' }}>SSL</option>
                    <option value="" {{ ($mailSettings['encryption'] ?? '') === '' ? 'selected' : '' }}>None</option>
                </select>
            </div>
            <div class="settings-field">
                <label for="mail-smtp-username">Username</label>
                <input id="mail-smtp-username" class="settings-input" value="{{ $mailSettings['username'] ?? '' }}" placeholder="support@crm.bihtech.in">
            </div>
            <div class="settings-field">
                <label for="mail-smtp-password">Password</label>
                <input id="mail-smtp-password" type="password" class="settings-input" value="" placeholder="{{ ($mailSettings['password_set'] ?? false) ? 'Password saved - leave blank to keep' : 'Enter SMTP password' }}">
                <div class="settings-help">{{ ($mailSettings['password_set'] ?? false) ? 'Password already saved.' : 'No SMTP password saved in admin settings.' }}</div>
            </div>
            <div class="settings-field">
                <label for="mail-from-address">From Email</label>
                <input id="mail-from-address" class="settings-input" value="{{ $mailSettings['from_address'] ?? '' }}" placeholder="support@crm.bihtech.in">
            </div>
            <div class="settings-field">
                <label for="mail-from-name">From Name</label>
                <input id="mail-from-name" class="settings-input" value="{{ $mailSettings['from_name'] ?? '' }}" placeholder="{{ brand_name() }}">
            </div>
            <div class="settings-field">
                <label for="mail-test-email">Test Recipient</label>
                <input id="mail-test-email" type="email" class="settings-input" value="" placeholder="user@example.com">
            </div>
        </div>

        <div class="mt-5 flex flex-wrap gap-2">
            <button type="button" onclick="saveMailSettings()" class="btn-primary" id="mail-settings-save-btn">
                <i class="fas fa-save"></i> Save SMTP
            </button>
            <button type="button" onclick="testMailSettings()" class="btn-cache" id="mail-settings-test-btn">
                <i class="fas fa-paper-plane"></i> Send Test Email
            </button>
            <a href="{{ route('admin.system-settings.mail-debug') }}" class="btn-cache">
                <i class="fas fa-bug"></i> Mail Debug
            </a>
        </div>
    </div>

    <div class="section-card">
        <div class="section-title">
            <i class="fas fa-volume-up"></i> Notification Sounds
        </div>
        <p class="text-sm text-gray-500 mb-4">
            Upload ringtones for each notification type. If a type has no custom sound, it uses the default sound.
        </p>

        <div class="sound-grid" id="notification-sounds-grid">
            @foreach ($notificationSoundSettings as $soundKey => $sound)
                <div class="sound-card" id="notification-sound-card-{{ $soundKey }}">
                    <div class="sound-card-title">{{ $sound['label'] }}</div>
                    <div class="sound-card-meta" id="notification-sound-meta-{{ $soundKey }}">
                        {{ $sound['path'] ? basename($sound['path']) : ($sound['uses_default'] ? 'Using default sound' : 'Using system fallback') }}
                    </div>
                    <input type="file"
                           id="notification-sound-file-{{ $soundKey }}"
                           accept="audio/mpeg,audio/wav,audio/ogg,.mp3,.wav,.ogg"
                           class="block w-full text-sm text-gray-700 border border-gray-300 rounded-lg px-3 py-2 bg-white">
                    <div class="sound-actions">
                        <button type="button" class="btn-primary" id="notification-sound-upload-{{ $soundKey }}" onclick="uploadNotificationSound('{{ $soundKey }}')">
                            <i class="fas fa-upload"></i> Upload
                        </button>
                        <button type="button" class="btn-cache" onclick="previewNotificationSound('{{ $soundKey }}')">
                            <i class="fas fa-play"></i> Preview
                        </button>
                        <button type="button" class="btn-cache" onclick="resetNotificationSound('{{ $soundKey }}')">
                            <i class="fas fa-undo"></i> Reset
                        </button>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-5 flex flex-wrap gap-2">
            <button type="button" onclick="applyDefaultNotificationSound()" class="btn-primary" id="notification-sound-apply-default-btn">
                <i class="fas fa-layer-group"></i> Apply Default Sound To All
            </button>
        </div>
    </div>

    <div class="section-card">
        <div class="section-title">
            <i class="fas fa-people-arrows"></i> Meta Review Ownership
        </div>
        <p class="text-sm text-gray-500 mb-4">
            Decide who owns Meta Review stage updates. CRM and Admin can always edit. If Sales Team is selected, only the currently allocated ASM, Senior Manager, or Manager can edit from lead detail.
        </p>

        <div class="grid md:grid-cols-2 gap-4 mb-5">
            <label class="border border-gray-200 rounded-lg p-4 flex items-start gap-3 cursor-pointer">
                <input type="radio" name="meta_review_owner" value="sales_team" class="mt-1" {{ $metaReviewOwner === 'sales_team' ? 'checked' : '' }}>
                <span>
                    <span class="block text-sm font-semibold text-gray-900">Sales Team</span>
                    <span class="block text-xs text-gray-500 mt-1">Allocated ASM, Senior Manager, or Manager can edit. CRM and Admin keep edit access.</span>
                </span>
            </label>
            <label class="border border-gray-200 rounded-lg p-4 flex items-start gap-3 cursor-pointer">
                <input type="radio" name="meta_review_owner" value="crm" class="mt-1" {{ $metaReviewOwner === 'crm' ? 'checked' : '' }}>
                <span>
                    <span class="block text-sm font-semibold text-gray-900">CRM</span>
                    <span class="block text-xs text-gray-500 mt-1">CRM and Admin can edit. Sales-side users stay read-only on the lead detail Meta Review section.</span>
                </span>
            </label>
        </div>

        <button type="button" onclick="saveMetaReviewSettings()" class="btn-primary" id="meta-review-settings-btn">
            <i class="fas fa-save"></i> Save Meta Review Settings
        </button>
    </div>

    <div class="section-card">
        <div class="section-title">
            <i class="fas fa-random"></i> Meta Conversion Mapping
        </div>
        <p class="text-sm text-gray-500 mb-4">
            Map CRM outcomes to Meta stages. This only applies when a lead is linked to Meta and no manual override exists.
        </p>

        <div class="grid md:grid-cols-2 gap-4 mb-5">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">CNP outcome</label>
                <select id="meta-map-cnp" class="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm">
                    @foreach ($metaStageOptions as $option)
                        <option value="{{ $option['value'] }}" {{ ($metaAutoMapping['cnp_stage'] ?? '') === $option['value'] ? 'selected' : '' }}>
                            {{ $option['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Not Interested / Junk</label>
                <select id="meta-map-not-interested" class="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm">
                    @foreach ($metaStageOptions as $option)
                        <option value="{{ $option['value'] }}" {{ ($metaAutoMapping['not_interested_stage'] ?? '') === $option['value'] ? 'selected' : '' }}>
                            {{ $option['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Interested</label>
                <select id="meta-map-interested" class="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm">
                    @foreach ($metaStageOptions as $option)
                        <option value="{{ $option['value'] }}" {{ ($metaAutoMapping['interested_stage'] ?? '') === $option['value'] ? 'selected' : '' }}>
                            {{ $option['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Follow-up (fresh)</label>
                <select id="meta-map-followup-fresh" class="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm">
                    @foreach ($metaStageOptions as $option)
                        <option value="{{ $option['value'] }}" {{ ($metaAutoMapping['fresh_follow_up_stage'] ?? '') === $option['value'] ? 'selected' : '' }}>
                            {{ $option['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Follow-up (after interested)</label>
                <select id="meta-map-followup-interested" class="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm">
                    @foreach ($metaStageOptions as $option)
                        <option value="{{ $option['value'] }}" {{ ($metaAutoMapping['follow_up_after_interested_stage'] ?? '') === $option['value'] ? 'selected' : '' }}>
                            {{ $option['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Visit Scheduled</label>
                <select id="meta-map-visit-scheduled" class="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm">
                    @foreach ($metaStageOptions as $option)
                        <option value="{{ $option['value'] }}" {{ ($metaAutoMapping['visit_scheduled_stage'] ?? '') === $option['value'] ? 'selected' : '' }}>
                            {{ $option['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <button type="button" onclick="saveMetaReviewMapping()" class="btn-primary" id="meta-review-mapping-btn">
            <i class="fas fa-save"></i> Save Meta Mapping
        </button>
    </div>

    <div class="section-card">
        <div class="section-title">
            <i class="fas fa-chart-line"></i> Dashboard KPI Mode
        </div>
        <p class="text-sm text-gray-500 mb-4">
            Decide whether sales dashboards should show target progress or actual work done. Precedence is <strong>User override</strong> > <strong>Role override</strong> > <strong>Global default</strong>.
        </p>

        <div class="mb-5">
            <label for="dashboard-kpi-global-mode" class="block text-sm font-semibold text-gray-900 mb-2">Global Default Mode</label>
            <select id="dashboard-kpi-global-mode" class="settings-select" style="max-width: 280px;">
                <option value="target_based" {{ ($dashboardKpiSettings['global_mode'] ?? 'target_based') === 'target_based' ? 'selected' : '' }}>Target Based</option>
                <option value="activity_based" {{ ($dashboardKpiSettings['global_mode'] ?? '') === 'activity_based' ? 'selected' : '' }}>Activity Based</option>
            </select>
        </div>

        <div class="mb-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-2">Role Overrides</h3>
            <div class="settings-table-wrap">
                <table class="settings-table">
                    <thead>
                        <tr>
                            <th>Role</th>
                            <th>Override Mode</th>
                            <th>Effective When No User Override</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($dashboardKpiRoles as $role)
                            @php $roleMode = $dashboardKpiSettings['role_modes'][$role->slug] ?? ''; @endphp
                            <tr>
                                <td>
                                    <div class="font-semibold text-gray-900">{{ $role->name }}</div>
                                    <div class="text-xs text-gray-500">{{ $role->slug }}</div>
                                </td>
                                <td>
                                    <select class="settings-select" data-role-kpi-mode="{{ $role->slug }}">
                                        <option value="" {{ $roleMode === '' ? 'selected' : '' }}>Inherit Global</option>
                                        <option value="target_based" {{ $roleMode === 'target_based' ? 'selected' : '' }}>Target Based</option>
                                        <option value="activity_based" {{ $roleMode === 'activity_based' ? 'selected' : '' }}>Activity Based</option>
                                    </select>
                                </td>
                                <td class="text-sm text-gray-600">
                                    {{ ucfirst(str_replace('_', ' ', $roleMode ?: ($dashboardKpiSettings['global_mode'] ?? 'target_based'))) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mb-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-2">User Overrides</h3>
            <div class="settings-table-wrap">
                <table class="settings-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th>Override Mode</th>
                            <th>Effective Mode</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($dashboardKpiUsers as $dashboardKpiUser)
                            @php
                                $userMode = $dashboardKpiSettings['user_modes'][(string) $dashboardKpiUser->id] ?? '';
                                $effectiveMode = $dashboardKpiEffectiveUserModes[(string) $dashboardKpiUser->id] ?? ($dashboardKpiSettings['global_mode'] ?? 'target_based');
                            @endphp
                            <tr>
                                <td>
                                    <div class="font-semibold text-gray-900">{{ $dashboardKpiUser->name }}</div>
                                    <div class="text-xs text-gray-500">ID: {{ $dashboardKpiUser->id }}</div>
                                </td>
                                <td class="text-sm text-gray-600">{{ $dashboardKpiUser->role->name ?? 'N/A' }}</td>
                                <td>
                                    <select class="settings-select" data-user-kpi-mode="{{ $dashboardKpiUser->id }}">
                                        <option value="" {{ $userMode === '' ? 'selected' : '' }}>Inherit Role / Global</option>
                                        <option value="target_based" {{ $userMode === 'target_based' ? 'selected' : '' }}>Target Based</option>
                                        <option value="activity_based" {{ $userMode === 'activity_based' ? 'selected' : '' }}>Activity Based</option>
                                    </select>
                                </td>
                                <td class="text-sm text-gray-600">{{ ucfirst(str_replace('_', ' ', $effectiveMode)) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <button type="button" onclick="saveDashboardKpiSettings()" class="btn-primary" id="dashboard-kpi-settings-btn">
            <i class="fas fa-save"></i> Save KPI Mode Settings
        </button>
    </div>

    <div class="section-card">
        <div class="section-title">
            <i class="fas fa-image"></i> Project Public Page Defaults
        </div>
        <p class="text-sm text-gray-500 mb-5">
            Jab project-level image upload na ho, tab ye default visuals auto use honge across public hero, floor plan, gallery, and media previews.
        </p>

        <div class="asset-default-grid">
            <div>
                <form id="public-page-defaults-form" class="asset-upload-grid">
                    @csrf
                    @php
                        $assetLabels = [
                            'hero_image' => 'Default Hero Image',
                            'builder_logo' => 'Default Builder Logo',
                            'floor_plan_image' => 'Default Floor Plan',
                            'amenities_image' => 'Default Amenities Visual',
                            'media_preview' => 'Default Media Preview',
                            'gallery_image' => 'Default Gallery Image',
                        ];
                        $assetSizes = [
                            'hero_image' => 'Recommended: 1600 x 900',
                            'builder_logo' => 'Recommended: 600 x 600',
                            'floor_plan_image' => 'Recommended: 1400 x 900',
                            'amenities_image' => 'Recommended: 1200 x 800',
                            'media_preview' => 'Recommended: 1400 x 900',
                            'gallery_image' => 'Recommended: 1400 x 900',
                        ];
                    @endphp

                    @foreach ($assetLabels as $key => $label)
                        @php $asset = $publicPageDefaultAssets[$key] ?? []; @endphp
                        <div class="asset-upload-card {{ !($asset['is_custom'] ?? false) ? 'is-empty' : '' }}" id="card-{{ $key }}">
                            <div class="asset-meta">
                                <span class="asset-status {{ ($asset['is_custom'] ?? false) ? 'custom' : 'fallback' }}" id="status-{{ $key }}">
                                    {{ ($asset['is_custom'] ?? false) ? 'Custom' : 'Fallback' }}
                                </span>
                            </div>
                            <div class="asset-preview-wrap">
                                <img src="{{ $asset['url'] ?? '' }}"
                                     alt="{{ $label }}"
                                     class="asset-preview"
                                     id="preview-{{ $key }}">
                                <div class="asset-skeleton" id="skeleton-{{ $key }}">
                                    <div class="asset-skeleton-copy">
                                        No custom image uploaded yet. Public page will still use system fallback.
                                    </div>
                                </div>
                            </div>
                            <label for="{{ $key }}" class="block text-sm font-semibold text-gray-900 mb-1">{{ $label }}</label>
                            <p class="asset-recommendation">{{ $assetSizes[$key] }}</p>
                            <input type="file"
                                   id="{{ $key }}"
                                   name="{{ $key }}"
                                   accept="image/*"
                                   class="block w-full text-sm text-gray-700 border border-gray-300 rounded-lg px-3 py-2 bg-white"
                                   onchange="previewPublicDefaultAsset(event, '{{ $key }}')">
                            <div class="asset-actions">
                                <button type="button" class="asset-action-btn" id="save-btn-{{ $key }}" onclick="saveSinglePublicPageDefault('{{ $key }}')">
                                    Save Now
                                </button>
                                <button type="button" class="asset-action-btn reset" id="reset-btn-{{ $key }}" onclick="resetPublicPageDefault('{{ $key }}')" {{ !($asset['is_custom'] ?? false) ? 'disabled' : '' }}>
                                    Reset
                                </button>
                            </div>
                            <div class="asset-hint">Image select karte hi auto-save ho jayega. Reset se admin custom image remove ho jayegi.</div>
                        </div>
                    @endforeach
                </form>

                <div class="mt-5">
                    <button type="button" onclick="savePublicPageDefaults()" class="btn-primary" id="public-page-defaults-btn">
                        <i class="fas fa-save"></i> Save Public Page Defaults
                    </button>
                </div>
            </div>

            <div class="asset-side-note">
                <h3 class="text-base font-semibold text-gray-900 mb-2">How this works</h3>
                <p class="text-sm text-gray-600 mb-4">
                    Public page image fallback order ab ye rahega:
                </p>
                <div class="space-y-3">
                    <div class="mini-note">
                        <div class="text-sm font-semibold text-gray-900">1. Project-specific upload</div>
                        <div class="text-xs text-gray-500 mt-1">Sabse pehle project ya variant ki uploaded image use hogi.</div>
                    </div>
                    <div class="mini-note">
                        <div class="text-sm font-semibold text-gray-900">2. Admin default image</div>
                        <div class="text-xs text-gray-500 mt-1">Agar project image missing ho, to yahan set ki gayi default visual use hogi.</div>
                    </div>
                    <div class="mini-note">
                        <div class="text-sm font-semibold text-gray-900">3. Final config fallback</div>
                        <div class="text-xs text-gray-500 mt-1">Agar admin default bhi set na ho, to system-safe fallback use hota rahega.</div>
                    </div>
                </div>

                <div class="mini-note">
                    <div class="text-sm font-semibold text-gray-900">Covered sections</div>
                    <div class="text-xs text-gray-500 mt-1">Hero, Builder logo, Floor plan, Media preview, Gallery preview, Amenities visual.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="section-card">
        <div class="section-title">
            <i class="fas fa-route"></i> Travel Time Widget
        </div>
        <p class="text-sm text-gray-500 mb-5">
            Ola placeholder setup, API key, aur city-level default origins yahin manage honge. Project-level origins wizard me override kar sakte ho.
        </p>

        <div class="grid md:grid-cols-2 gap-6">
            <div>
                <label for="travel-time-api-key" class="block text-sm font-semibold text-gray-900 mb-2">Ola Maps API Key</label>
                <input id="travel-time-api-key"
                       type="text"
                       value="{{ $travelTimeSettings['ola_maps_api_key'] ?? '' }}"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm"
                       placeholder="Paste Ola Maps API key later">
                <p class="text-xs text-gray-500 mt-2">Key blank rahe to widget safe fallback me rehega. Later yahin update kar dena.</p>
            </div>

            <div class="mini-note" style="margin-top:0;">
                <div class="text-sm font-semibold text-gray-900">Current city-default strategy</div>
                <div class="text-xs text-gray-500 mt-1">Project origins > City defaults > Search only. Lucknow defaults config me already available hain, aur yahan se extend ho sakte hain.</div>
            </div>
        </div>

        <div class="mt-5">
            <label for="travel-time-city-defaults" class="block text-sm font-semibold text-gray-900 mb-2">City Default Origins JSON</label>
            <textarea id="travel-time-city-defaults"
                      rows="14"
                      class="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm font-mono"
                      placeholder='{"lucknow":[{"label":"Hazratganj","latitude":26.8506,"longitude":80.9462,"category":"City Center","display_order":1}]}'>{{ $travelTimeSettings['travel_time_city_defaults'] ?? '{}' }}</textarea>
            <p class="text-xs text-gray-500 mt-2">Normalized city key use karo, for example: <code>lucknow</code>, <code>noida</code>, <code>gurugram</code>.</p>
        </div>

        <div class="mt-5">
            <button type="button" onclick="saveTravelTimeSettings()" class="btn-primary" id="travel-time-settings-btn">
                <i class="fas fa-save"></i> Save Travel Time Settings
            </button>
        </div>
    </div>

    {{-- 3. Cache --}}
    <div class="section-card">
        <div class="section-title">
            <i class="fas fa-broom"></i> Cache Management
        </div>
        <p class="text-sm text-gray-500 mb-4">
            Clear or rebuild Laravel's application cache.
        </p>

        <div class="flex flex-wrap gap-2">
            <button onclick="runCacheCommand('clear-cache')" class="btn-cache" id="btn-clear-cache">
                <i class="fas fa-trash-alt"></i> Clear Cache
            </button>
            <button onclick="runCacheCommand('config-cache')" class="btn-cache" id="btn-config-cache">
                <i class="fas fa-cog"></i> Cache Config
            </button>
            <button onclick="runCacheCommand('route-cache')" class="btn-cache" id="btn-route-cache">
                <i class="fas fa-route"></i> Cache Routes
            </button>
            <button onclick="runCacheCommand('view-cache')" class="btn-cache" id="btn-view-cache">
                <i class="fas fa-eye"></i> Cache Views
            </button>
        </div>

        <div id="cache-output" style="display:none;">
            <div class="command-output" id="cache-output-content"></div>
        </div>
    </div>

</div>

<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
let notificationSoundSettings = @json($notificationSoundSettings);
let notificationSoundPreview = null;

/* ── Maintenance Mode ── */
document.getElementById('maintenance-toggle').addEventListener('change', function () {
    document.getElementById('maintenance-message-section').style.display = this.checked ? 'block' : 'none';
});

function toggleMaintenanceMode() {
    const enabled = document.getElementById('maintenance-toggle').checked;
    const message = document.getElementById('maintenance-message').value;
    const btn = document.getElementById('maintenance-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

    fetch('{{ route("admin.system-settings.maintenance.toggle") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({ enabled, message })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showMessage(data.message, 'success');
            const badge = document.getElementById('maintenance-badge');
            if (data.maintenance_mode) {
                badge.className = 'status-badge active';
                badge.textContent = 'ENABLED';
                btn.innerHTML = '<i class="fas fa-power-off"></i> Disable Maintenance Mode';
            } else {
                badge.className = 'status-badge inactive';
                badge.textContent = 'DISABLED';
                btn.innerHTML = '<i class="fas fa-power-off"></i> Enable Maintenance Mode';
            }
        } else {
            showMessage(data.message || 'Error toggling maintenance mode', 'error');
        }
    })
    .catch(e => showMessage('Error: ' + e.message, 'error'))
    .finally(() => { btn.disabled = false; });
}

/* ── User Notifications ── */
function saveUserNotificationSettings() {
    const sendWelcome = document.getElementById('send-welcome-email-toggle').checked;
    const notifyAdmin = document.getElementById('notify-admin-toggle').checked;
    const quietHoursEnabled = document.getElementById('notification-quiet-hours-toggle').checked;
    const quietHoursStart = document.getElementById('notification-quiet-start').value || '21:00';
    const quietHoursEnd = document.getElementById('notification-quiet-end').value || '08:30';
    const btn = document.getElementById('user-notifications-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    fetch('{{ route("admin.system-settings.user-notifications.update") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({
            send_welcome_email_to_new_user: sendWelcome,
            notify_admin_on_new_user: notifyAdmin,
            notification_quiet_hours_enabled: quietHoursEnabled,
            notification_quiet_hours_start: quietHoursStart,
            notification_quiet_hours_end: quietHoursEnd
        })
    })
    .then(r => r.json())
    .then(data => {
        showMessage(data.success ? data.message : (data.message || 'Error saving settings'), data.success ? 'success' : 'error');
    })
    .catch(e => showMessage('Error: ' + e.message, 'error'))
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Save Settings';
    });
}

function collectMailSettings() {
    return {
        enabled: document.getElementById('mail-smtp-enabled').checked,
        mailer: 'smtp',
        host: document.getElementById('mail-smtp-host').value.trim(),
        port: document.getElementById('mail-smtp-port').value || 587,
        encryption: document.getElementById('mail-smtp-encryption').value,
        username: document.getElementById('mail-smtp-username').value.trim(),
        password: document.getElementById('mail-smtp-password').value,
        from_address: document.getElementById('mail-from-address').value.trim(),
        from_name: document.getElementById('mail-from-name').value.trim()
    };
}

function saveMailSettings() {
    const btn = document.getElementById('mail-settings-save-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    fetch('{{ route("admin.system-settings.mail-settings.update") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
        body: JSON.stringify(collectMailSettings())
    })
    .then(async response => ({ ok: response.ok, data: await response.json() }))
    .then(({ ok, data }) => {
        showMessage(data.message || (ok ? 'SMTP settings saved.' : 'SMTP settings failed.'), ok ? 'success' : 'error');
        if (ok) {
            document.getElementById('mail-smtp-password').value = '';
            document.getElementById('mail-smtp-password').placeholder = 'Password saved - leave blank to keep';
        }
    })
    .catch(e => showMessage('Error: ' + e.message, 'error'))
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Save SMTP';
    });
}

function testMailSettings() {
    const email = document.getElementById('mail-test-email').value.trim();
    if (!email) {
        showMessage('Please enter a test recipient email.', 'error');
        return;
    }

    const btn = document.getElementById('mail-settings-test-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

    fetch('{{ route("admin.system-settings.mail-settings.test") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
        body: JSON.stringify({ email })
    })
    .then(async response => ({ ok: response.ok, data: await response.json() }))
    .then(({ ok, data }) => {
        showMessage(data.message || (ok ? 'Test email sent.' : 'Test email failed.'), ok ? 'success' : 'error');
    })
    .catch(e => showMessage('Error: ' + e.message, 'error'))
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Test Email';
    });
}

function uploadNotificationSound(soundKey) {
    const input = document.getElementById('notification-sound-file-' + soundKey);
    const btn = document.getElementById('notification-sound-upload-' + soundKey);
    if (!input || !input.files || !input.files[0]) {
        showMessage('Please select an audio file first.', 'error');
        return;
    }

    const formData = new FormData();
    formData.append('sound_key', soundKey);
    formData.append('sound_file', input.files[0]);

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';

    fetch('{{ route("admin.system-settings.notification-sounds.upload") }}', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
        body: formData
    })
    .then(async response => ({ ok: response.ok, data: await response.json() }))
    .then(({ ok, data }) => {
        showMessage(data.message || (ok ? 'Sound updated.' : 'Sound upload failed.'), ok ? 'success' : 'error');
        if (ok && data.sounds) {
            notificationSoundSettings = data.sounds;
            updateNotificationSoundCards();
            input.value = '';
        }
    })
    .catch(e => showMessage('Error: ' + e.message, 'error'))
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-upload"></i> Upload';
    });
}

function resetNotificationSound(soundKey) {
    fetch('{{ route("admin.system-settings.notification-sounds.reset") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({ sound_key: soundKey })
    })
    .then(async response => ({ ok: response.ok, data: await response.json() }))
    .then(({ ok, data }) => {
        showMessage(data.message || (ok ? 'Sound reset.' : 'Sound reset failed.'), ok ? 'success' : 'error');
        if (ok && data.sounds) {
            notificationSoundSettings = data.sounds;
            updateNotificationSoundCards();
        }
    })
    .catch(e => showMessage('Error: ' + e.message, 'error'));
}

function applyDefaultNotificationSound() {
    const btn = document.getElementById('notification-sound-apply-default-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Applying...';

    fetch('{{ route("admin.system-settings.notification-sounds.apply-default") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({})
    })
    .then(async response => ({ ok: response.ok, data: await response.json() }))
    .then(({ ok, data }) => {
        showMessage(data.message || (ok ? 'Default applied.' : 'Default apply failed.'), ok ? 'success' : 'error');
        if (ok && data.sounds) {
            notificationSoundSettings = data.sounds;
            updateNotificationSoundCards();
        }
    })
    .catch(e => showMessage('Error: ' + e.message, 'error'))
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-layer-group"></i> Apply Default Sound To All';
    });
}

function previewNotificationSound(soundKey) {
    const input = document.getElementById('notification-sound-file-' + soundKey);
    let url = notificationSoundSettings[soundKey] && notificationSoundSettings[soundKey].effective_url;

    if (input && input.files && input.files[0]) {
        url = URL.createObjectURL(input.files[0]);
    }

    if (!url) {
        url = '/sounds/lead-ringtone.mp3';
    }

    if (notificationSoundPreview) {
        notificationSoundPreview.pause();
        notificationSoundPreview.currentTime = 0;
    }

    notificationSoundPreview = new Audio(url);
    notificationSoundPreview.play().catch(() => showMessage('Browser blocked audio preview. Click again after interacting with the page.', 'error'));
}

function updateNotificationSoundCards() {
    Object.keys(notificationSoundSettings || {}).forEach(function (soundKey) {
        const sound = notificationSoundSettings[soundKey];
        const meta = document.getElementById('notification-sound-meta-' + soundKey);
        if (!meta || !sound) return;
        if (sound.path) {
            meta.textContent = sound.path.split('/').pop();
        } else {
            meta.textContent = sound.uses_default ? 'Using default sound' : 'Using system fallback';
        }
    });
}

/* ── Cache Commands ── */
function saveMetaReviewSettings() {
    const owner = document.querySelector('input[name="meta_review_owner"]:checked')?.value || 'sales_team';
    const btn = document.getElementById('meta-review-settings-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    fetch('{{ route("admin.system-settings.meta-review.update") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({ meta_review_owner: owner })
    })
    .then(r => r.json())
    .then(data => {
        showMessage(data.success ? data.message : (data.message || 'Error saving Meta review settings'), data.success ? 'success' : 'error');
    })
    .catch(e => showMessage('Error: ' + e.message, 'error'))
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Save Meta Review Settings';
    });
}

function saveMetaReviewMapping() {
    const btn = document.getElementById('meta-review-mapping-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    const payload = {
        cnp_stage: document.getElementById('meta-map-cnp').value,
        not_interested_stage: document.getElementById('meta-map-not-interested').value,
        interested_stage: document.getElementById('meta-map-interested').value,
        fresh_follow_up_stage: document.getElementById('meta-map-followup-fresh').value,
        follow_up_after_interested_stage: document.getElementById('meta-map-followup-interested').value,
        visit_scheduled_stage: document.getElementById('meta-map-visit-scheduled').value,
    };

    fetch('{{ route("admin.system-settings.meta-review.mapping.update") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        showMessage(data.success ? data.message : (data.message || 'Error saving mapping'), data.success ? 'success' : 'error');
    })
    .catch(e => showMessage('Error: ' + e.message, 'error'))
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Save Meta Mapping';
    });
}

function saveDashboardKpiSettings() {
    const btn = document.getElementById('dashboard-kpi-settings-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    const roleModes = {};
    const userModes = {};

    document.querySelectorAll('[data-role-kpi-mode]').forEach(function (select) {
        roleModes[select.dataset.roleKpiMode] = select.value;
    });

    document.querySelectorAll('[data-user-kpi-mode]').forEach(function (select) {
        userModes[select.dataset.userKpiMode] = select.value;
    });

    fetch('{{ route("admin.system-settings.dashboard-kpi.update") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({
            global_mode: document.getElementById('dashboard-kpi-global-mode').value,
            role_modes: roleModes,
            user_modes: userModes
        })
    })
    .then(r => r.json())
    .then(data => {
        showMessage(data.success ? data.message : (data.message || 'Error saving KPI settings'), data.success ? 'success' : 'error');
        if (data.success) {
            window.location.reload();
        }
    })
    .catch(e => showMessage('Error: ' + e.message, 'error'))
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Save KPI Mode Settings';
    });
}

function previewPublicDefaultAsset(event, key) {
    const file = event.target.files && event.target.files[0];
    if (!file) {
        return;
    }

    const card = document.getElementById('card-' + key);
    const preview = document.getElementById('preview-' + key);
    if (preview) {
        preview.src = URL.createObjectURL(file);
    }

    if (card) {
        card.classList.remove('is-empty');
    }

    saveSinglePublicPageDefault(key);
}

function savePublicPageDefaults() {
    const btn = document.getElementById('public-page-defaults-btn');
    const form = document.getElementById('public-page-defaults-form');
    const formData = new FormData(form);

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    fetch('{{ route("admin.system-settings.public-page-defaults.update") }}', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken },
        body: formData
    })
    .then(async (response) => {
        const data = await response.json();
        return { ok: response.ok, data };
    })
    .then(({ ok, data }) => {
        showMessage(data.message || (ok ? 'Public page defaults updated.' : 'Error updating defaults.'), ok ? 'success' : 'error');
        if (ok && data.assets) {
            Object.keys(data.assets).forEach(function (key) {
                applyPublicDefaultAssetState(key, data.assets[key]);
            });
        }
    })
    .catch(e => showMessage('Error: ' + e.message, 'error'))
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Save Public Page Defaults';
    });
}

function saveSinglePublicPageDefault(key) {
    const input = document.getElementById(key);
    if (!input || !input.files || !input.files[0]) {
        return;
    }

    const btn = document.getElementById('save-btn-' + key);
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = 'Saving...';

    const formData = new FormData();
    formData.append(key, input.files[0]);

    fetch('{{ route("admin.system-settings.public-page-defaults.update") }}', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken },
        body: formData
    })
    .then(async (response) => {
        const data = await response.json();
        return { ok: response.ok, data };
    })
    .then(({ ok, data }) => {
        showMessage(data.message || (ok ? 'Default image updated.' : 'Error updating image.'), ok ? 'success' : 'error');
        if (ok && data.assets && data.assets[key]) {
            applyPublicDefaultAssetState(key, data.assets[key]);
        }
    })
    .catch(e => showMessage('Error: ' + e.message, 'error'))
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
        input.value = '';
    });
}

function resetPublicPageDefault(key) {
    const btn = document.getElementById('reset-btn-' + key);
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = 'Resetting...';

    const formData = new FormData();
    formData.append('reset_fields[]', key);

    fetch('{{ route("admin.system-settings.public-page-defaults.update") }}', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken },
        body: formData
    })
    .then(async (response) => {
        const data = await response.json();
        return { ok: response.ok, data };
    })
    .then(({ ok, data }) => {
        showMessage(data.message || (ok ? 'Default image reset.' : 'Error resetting image.'), ok ? 'success' : 'error');
        if (ok && data.assets && data.assets[key]) {
            applyPublicDefaultAssetState(key, data.assets[key]);
        }
    })
    .catch(e => showMessage('Error: ' + e.message, 'error'))
    .finally(() => {
        btn.innerHTML = originalHtml;
    });
}

function applyPublicDefaultAssetState(key, asset) {
    const card = document.getElementById('card-' + key);
    const preview = document.getElementById('preview-' + key);
    const status = document.getElementById('status-' + key);
    const resetBtn = document.getElementById('reset-btn-' + key);

    if (!card || !preview || !status || !resetBtn) {
        return;
    }

    if (asset.is_custom && asset.url) {
        preview.src = asset.url;
        card.classList.remove('is-empty');
        status.textContent = 'Custom';
        status.className = 'asset-status custom';
        resetBtn.disabled = false;
    } else {
        preview.removeAttribute('src');
        card.classList.add('is-empty');
        status.textContent = 'Fallback';
        status.className = 'asset-status fallback';
        resetBtn.disabled = true;
    }
}

function saveTravelTimeSettings() {
    const btn = document.getElementById('travel-time-settings-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    fetch('{{ route("admin.system-settings.travel-time.update") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({
            ola_maps_api_key: document.getElementById('travel-time-api-key').value,
            travel_time_city_defaults: document.getElementById('travel-time-city-defaults').value
        })
    })
    .then(async (response) => {
        const data = await response.json();
        return { ok: response.ok, data };
    })
    .then(({ ok, data }) => {
        showMessage(data.message || (ok ? 'Travel time settings saved.' : 'Error saving travel time settings.'), ok ? 'success' : 'error');
        if (ok && data.settings) {
            document.getElementById('travel-time-api-key').value = data.settings.ola_maps_api_key || '';
            document.getElementById('travel-time-city-defaults').value = data.settings.travel_time_city_defaults || '{}';
        }
    })
    .catch(e => showMessage('Error: ' + e.message, 'error'))
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Save Travel Time Settings';
    });
}

function runCacheCommand(command) {
    const btn = document.getElementById('btn-' + command);
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Running...'; }

    fetch('{{ route("admin.system-settings.command.run") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({ command })
    })
    .then(r => r.json())
    .then(data => {
        const outputDiv  = document.getElementById('cache-output');
        const contentDiv = document.getElementById('cache-output-content');
        outputDiv.style.display = 'block';
        contentDiv.textContent  = data.output || (data.success ? 'Done.' : (data.message || 'Error.'));
        showMessage(data.success ? (data.message || 'Done.') : (data.message || 'Error'), data.success ? 'success' : 'error');
    })
    .catch(e => showMessage('Error: ' + e.message, 'error'))
    .finally(() => {
        if (btn) {
            btn.disabled = false;
            // restore original label
            const labels = {
                'clear-cache':  '<i class="fas fa-trash-alt"></i> Clear Cache',
                'config-cache': '<i class="fas fa-cog"></i> Cache Config',
                'route-cache':  '<i class="fas fa-route"></i> Cache Routes',
                'view-cache':   '<i class="fas fa-eye"></i> Cache Views',
            };
            btn.innerHTML = labels[command] || command;
        }
    });
}

/* ── Message Helper ── */
function showMessage(message, type) {
    const container = document.getElementById('message-container');
    const alert     = document.getElementById('message-alert');
    alert.className = `alert alert-${type}`;
    alert.textContent = message;
    container.style.display = 'block';
    setTimeout(() => { container.style.display = 'none'; }, 5000);
}
</script>
@endsection

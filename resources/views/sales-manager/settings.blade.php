@extends('sales-manager.layout')

@php
    $settingsUser = auth()->user();
    $settingsAudience = $settingsUser?->isSeniorManager() ? 'Senior Manager' : 'Assistant Sales Manager';
@endphp

@section('title', 'Settings - ' . $settingsAudience)
@section('page-title', 'Settings')

@push('styles')
<style>
    .asm-settings-shell {
        display: grid;
        gap: 24px;
        padding-bottom: 24px;
    }
    .asm-settings-hero,
    .asm-settings-card {
        background: #fff;
        border: 1px solid #e3ddd4;
        border-radius: 22px;
        box-shadow: 0 14px 28px rgba(15, 45, 34, 0.06);
    }
    .asm-settings-hero {
        padding: 28px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
    }
    .asm-settings-hero h2 {
        margin: 0 0 8px;
        font-size: 28px;
        color: #063A1C;
        font-weight: 800;
    }
    .asm-settings-hero p {
        margin: 0;
        color: #61706a;
        font-size: 15px;
        max-width: 640px;
        line-height: 1.6;
    }
    .asm-settings-hero-badge {
        background: linear-gradient(135deg, #063A1C 0%, #205A44 100%);
        color: #fff;
        padding: 12px 16px;
        border-radius: 16px;
        font-weight: 700;
        white-space: nowrap;
    }
    .asm-settings-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 24px;
    }
    .asm-settings-card {
        padding: 24px;
    }
    .asm-settings-card h3 {
        margin: 0 0 8px;
        color: #063A1C;
        font-size: 20px;
        font-weight: 800;
    }
    .asm-settings-card > p {
        margin: 0 0 18px;
        color: #61706a;
        font-size: 14px;
        line-height: 1.5;
    }
    .asm-toggle-list {
        display: grid;
        gap: 12px;
    }
    .asm-toggle-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 14px 16px;
        border: 1px solid #ebe5dc;
        border-radius: 16px;
        background: #faf8f4;
    }
    .asm-toggle-copy {
        min-width: 0;
    }
    .asm-toggle-copy strong {
        display: block;
        color: #163528;
        font-size: 15px;
        font-weight: 700;
    }
    .asm-toggle-copy span {
        display: block;
        margin-top: 4px;
        color: #72827b;
        font-size: 13px;
        line-height: 1.45;
    }
    .asm-switch {
        position: relative;
        display: inline-flex;
        width: 54px;
        height: 30px;
        flex-shrink: 0;
    }
    .asm-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    .asm-switch-slider {
        position: absolute;
        inset: 0;
        background: #d1d5db;
        border-radius: 999px;
        transition: all 0.2s ease;
        cursor: pointer;
    }
    .asm-switch-slider::before {
        content: '';
        position: absolute;
        width: 24px;
        height: 24px;
        left: 3px;
        top: 3px;
        border-radius: 999px;
        background: #fff;
        transition: transform 0.2s ease;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.18);
    }
    .asm-switch input:checked + .asm-switch-slider {
        background: #205A44;
    }
    .asm-switch input:checked + .asm-switch-slider::before {
        transform: translateX(24px);
    }
    .asm-settings-actions {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
    }
    .asm-settings-btn {
        border: none;
        border-radius: 14px;
        padding: 13px 20px;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
    }
    .asm-settings-btn-secondary {
        background: #edf1ee;
        color: #1f3c31;
    }
    .asm-settings-btn-primary {
        background: linear-gradient(135deg, #063A1C 0%, #205A44 100%);
        color: #fff;
        min-width: 180px;
    }
    .asm-settings-alert {
        display: none;
        padding: 14px 16px;
        border-radius: 14px;
        font-weight: 600;
    }
    .asm-settings-alert.success {
        display: block;
        background: #d1fae5;
        color: #065f46;
    }
    .asm-settings-alert.error {
        display: block;
        background: #fee2e2;
        color: #991b1b;
    }
    @media (max-width: 900px) {
        .asm-settings-grid {
            grid-template-columns: 1fr;
        }
        .asm-settings-hero {
            flex-direction: column;
            align-items: flex-start;
        }
    }
</style>
@endpush

@section('content')
<div class="asm-settings-shell">
    <section class="asm-settings-hero">
        <div>
            <h2>Dashboard Settings</h2>
            <p>Apne dashboard par kya dikhna hai aur kya hide karna hai, yahin se control karo. Ye settings sirf aapke {{ $settingsAudience }} dashboard par apply hongi.</p>
        </div>
        <div class="asm-settings-hero-badge">{{ $settingsAudience }} only</div>
    </section>

    <div id="dashboardSettingsAlert" class="asm-settings-alert"></div>

    <div class="asm-settings-grid">
        <section class="asm-settings-card">
            <h3>Today Focus</h3>
            <p>Top hero panel aur uske mini cards ko control karo.</p>
            <div class="asm-toggle-list">
                @foreach ([
                    'today_focus_panel' => ['Today Focus panel', 'Complete hero summary panel show/hide karega.'],
                    'today_focus_fresh_leads' => ['Fresh Leads', 'Aaj ke fresh assigned leads counter.'],
                    'today_focus_overdue' => ['Overdue', 'Overdue task mini card.'],
                    'today_focus_meetings' => ['Meetings', 'Aaj ke meetings mini card.'],
                    'today_focus_site_visits' => ['Site Visits', 'Aaj ke site visits mini card.'],
                    'today_focus_follow_ups' => ['Follow-ups', 'Aaj ke follow-up mini card.'],
                ] as $key => [$label, $desc])
                    <label class="asm-toggle-item" for="setting_{{ $key }}">
                        <div class="asm-toggle-copy">
                            <strong>{{ $label }}</strong>
                            <span>{{ $desc }}</span>
                        </div>
                        <span class="asm-switch">
                            <input type="checkbox" id="setting_{{ $key }}" data-setting-key="{{ $key }}">
                            <span class="asm-switch-slider"></span>
                        </span>
                    </label>
                @endforeach
            </div>
        </section>

        <section class="asm-settings-card">
            <h3>Top Panels</h3>
            <p>Dashboard ke secondary panels aur stat cards ko control karo.</p>
            <div class="asm-toggle-list">
                @foreach ([
                    'favorites_panel' => ['Favorite Leads', 'Right side favorites panel show/hide karega.'],
                    'stat_leads_received' => ['Leads Received', 'Pipeline card.'],
                    'stat_todays_prospects' => ['Today’s Prospects', 'Today prospects stat card.'],
                    'stat_pending_verifications' => ['Pending Verifications', 'Approval queue card.'],
                    'stat_overdue_tasks' => ['Overdue Tasks', 'Attention card.'],
                    'stat_team_members' => ['Team Members', 'Coverage card.'],
                    'stat_pending_tasks' => ['Pending Tasks', 'Backlog card.'],
                    'stat_no_response_yet' => ['No Response Yet', 'Risk card.'],
                ] as $key => [$label, $desc])
                    <label class="asm-toggle-item" for="setting_{{ $key }}">
                        <div class="asm-toggle-copy">
                            <strong>{{ $label }}</strong>
                            <span>{{ $desc }}</span>
                        </div>
                        <span class="asm-switch">
                            <input type="checkbox" id="setting_{{ $key }}" data-setting-key="{{ $key }}">
                            <span class="asm-switch-slider"></span>
                        </span>
                    </label>
                @endforeach
            </div>
        </section>

        <section class="asm-settings-card">
            <h3>Detailed Sections</h3>
            <p>Dashboard ke lower sections ko apne hisab se visible ya hidden rakho.</p>
            <div class="asm-toggle-list">
                @foreach ([
                    'no_response_section' => ['No Response Yet Section', 'Leads pending response table section.'],
                    'manager_targets_section' => ['My Targets vs Achievements', 'Personal targets panel.'],
                    'team_targets_section' => ['Team Targets vs Achievements', 'Team-level targets panel.'],
                    'team_members_cards_section' => ['Team Members Targets Cards', 'Individual team member cards section.'],
                    'incentives_section' => ['Incentives Section', 'Earn incentive block show/hide karega.'],
                    'chatbot_widget' => ['Chatbot Assistant', 'Floating chatbot widget on/off karega. Default off rahega.'],
                ] as $key => [$label, $desc])
                    <label class="asm-toggle-item" for="setting_{{ $key }}">
                        <div class="asm-toggle-copy">
                            <strong>{{ $label }}</strong>
                            <span>{{ $desc }}</span>
                        </div>
                        <span class="asm-switch">
                            <input type="checkbox" id="setting_{{ $key }}" data-setting-key="{{ $key }}">
                            <span class="asm-switch-slider"></span>
                        </span>
                    </label>
                @endforeach
            </div>
        </section>

        <section class="asm-settings-card">
            <h3>Section View Preferences</h3>
            <p>Har {{ $settingsAudience }} apne working pages ke liye list ya cards view choose kar sakta hai.</p>
            <div class="asm-toggle-list">
                @foreach ([
                    'leads' => ['Leads', 'Phone par default list rehega, lekin aap yahan se cards bhi choose kar sakte ho.'],
                    'prospects' => ['Prospects', 'Prospects section list ya cards me khulega.'],
                    'meetings' => ['Meetings', 'Meetings page ka default view yahin se decide hoga.'],
                    'site_visits' => ['Site Visits', 'Site visits page list ya cards me khulega.'],
                    'tasks' => ['Follow-ups / Tasks', 'Tasks aur follow-up page ka default layout yahin se decide hoga.'],
                ] as $key => [$label, $desc])
                    <div class="asm-toggle-item">
                        <div class="asm-toggle-copy">
                            <strong>{{ $label }}</strong>
                            <span>{{ $desc }}</span>
                        </div>
                        <div style="display:flex; gap:8px; flex-shrink:0;">
                            <button
                                type="button"
                                class="asm-settings-btn asm-settings-btn-secondary asm-view-pref-btn"
                                data-view-setting-key="{{ $key }}"
                                data-view-setting-value="list"
                                style="min-width:86px; padding:10px 14px;"
                            >List</button>
                            <button
                                type="button"
                                class="asm-settings-btn asm-settings-btn-secondary asm-view-pref-btn"
                                data-view-setting-key="{{ $key }}"
                                data-view-setting-value="card"
                                style="min-width:86px; padding:10px 14px;"
                            >Cards</button>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="asm-settings-card">
            <h3>How It Works</h3>
            <p>Ye dashboard sirf aapka personal {{ $settingsAudience }} dashboard customize karega.</p>
            <div class="asm-toggle-list">
                <div class="asm-toggle-item">
                    <div class="asm-toggle-copy">
                        <strong>Personal settings</strong>
                        <span>Aapke changes sirf aap par apply honge. Dusre users ka dashboard alag rahega.</span>
                    </div>
                </div>
                <div class="asm-toggle-item">
                    <div class="asm-toggle-copy">
                        <strong>Default layout safe hai</strong>
                        <span>Agar aap kuch save nahi karte, dashboard current default layout me hi dikhega.</span>
                    </div>
                </div>
                <div class="asm-toggle-item">
                    <div class="asm-toggle-copy">
                        <strong>Layout auto-adjust</strong>
                        <span>Jab koi card ya section hide hoga, dashboard gap ke bina compact tareeke se adjust hoga.</span>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <div class="asm-settings-actions">
        <button type="button" class="asm-settings-btn asm-settings-btn-secondary" id="dashboardSettingsResetBtn">Reset to Default</button>
        <button type="button" class="asm-settings-btn asm-settings-btn-primary" id="dashboardSettingsSaveBtn">Save Dashboard Settings</button>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const ASM_DASHBOARD_DEFAULTS = {
        today_focus_panel: true,
        today_focus_fresh_leads: true,
        today_focus_overdue: true,
        today_focus_meetings: true,
        today_focus_site_visits: true,
        today_focus_follow_ups: true,
        favorites_panel: true,
        stat_leads_received: true,
        stat_todays_prospects: true,
        stat_pending_verifications: true,
        stat_overdue_tasks: true,
        stat_team_members: true,
        stat_pending_tasks: true,
        stat_no_response_yet: true,
        no_response_section: true,
        team_call_stats_section: true,
        manager_targets_section: true,
        team_targets_section: true,
        team_members_cards_section: true,
        incentives_section: true,
        chatbot_widget: false
    };
    const INITIAL_ASM_DASHBOARD_VISIBILITY = @json($dashboardVisibility ?? []);
    const ASM_SECTION_VIEW_DEFAULTS = {
        leads: 'list',
        prospects: 'card',
        meetings: 'card',
        site_visits: 'card',
        tasks: 'card'
    };
    const INITIAL_ASM_SECTION_VIEW_PREFERENCES = @json($sectionViewPreferences ?? []);
    let dashboardSettingsAutosaveTimer = null;
    let dashboardSettingsIsSaving = false;

    const ASM_DASHBOARD_SETTINGS_SAVE_URL = @json(route('sales-manager.settings.update'));

    function settingsHeaders(includeJson = false) {
        const headers = {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        };
        if (includeJson) headers['Content-Type'] = 'application/json';
        return headers;
    }

    function showSettingsAlert(message, type) {
        const alertEl = document.getElementById('dashboardSettingsAlert');
        if (!alertEl) return;
        alertEl.className = `asm-settings-alert ${type}`;
        alertEl.textContent = message;
    }

    function applySettingsToForm(settings) {
        Object.keys(ASM_DASHBOARD_DEFAULTS).forEach(function (key) {
            const input = document.querySelector(`[data-setting-key="${key}"]`);
            if (input) {
                input.checked = Object.prototype.hasOwnProperty.call(settings, key) ? !!settings[key] : ASM_DASHBOARD_DEFAULTS[key];
            }
        });
    }

    function applySectionViewPreferencesToForm(preferences) {
        const merged = {
            ...ASM_SECTION_VIEW_DEFAULTS,
            ...preferences
        };

        document.querySelectorAll('[data-view-setting-key]').forEach(function (button) {
            const key = button.dataset.viewSettingKey;
            const value = button.dataset.viewSettingValue;
            const isActive = merged[key] === value;
            button.classList.toggle('asm-settings-btn-primary', isActive);
            button.classList.toggle('asm-settings-btn-secondary', !isActive);
        });
    }

    function collectSettingsFromForm() {
        const payload = {};
        document.querySelectorAll('[data-setting-key]').forEach(function (input) {
            payload[input.dataset.settingKey] = !!input.checked;
        });
        return payload;
    }

    function collectSectionViewPreferencesFromForm() {
        const payload = {};
        document.querySelectorAll('[data-view-setting-key][data-active="1"]').forEach(function (button) {
            payload[button.dataset.viewSettingKey] = button.dataset.viewSettingValue;
        });
        return payload;
    }

    function setDashboardSaveButtonState(isSaving) {
        const saveBtn = document.getElementById('dashboardSettingsSaveBtn');
        if (!saveBtn) return;
        saveBtn.disabled = isSaving;
        saveBtn.textContent = isSaving ? 'Saving...' : 'Save Dashboard Settings';
    }

    async function loadDashboardSettings() {
        applySettingsToForm({
            ...ASM_DASHBOARD_DEFAULTS,
            ...INITIAL_ASM_DASHBOARD_VISIBILITY
        });
        applySectionViewPreferencesToForm({
            ...ASM_SECTION_VIEW_DEFAULTS,
            ...INITIAL_ASM_SECTION_VIEW_PREFERENCES
        });
        syncSectionViewButtons();
    }

    function syncSectionViewButtons() {
        document.querySelectorAll('[data-view-setting-key]').forEach(function (button) {
            button.dataset.active = button.classList.contains('asm-settings-btn-primary') ? '1' : '0';
        });
    }

    async function saveDashboardSettings() {
        if (dashboardSettingsIsSaving) {
            return;
        }

        try {
            dashboardSettingsIsSaving = true;
            setDashboardSaveButtonState(true);

            const response = await fetch(ASM_DASHBOARD_SETTINGS_SAVE_URL, {
                method: 'POST',
                headers: settingsHeaders(true),
                credentials: 'same-origin',
                body: JSON.stringify({
                    dashboard_visibility: collectSettingsFromForm(),
                    section_view_preferences: collectSectionViewPreferencesFromForm()
                })
            });
            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Failed to save dashboard settings.');
            }

            applySettingsToForm(result.dashboard_visibility || ASM_DASHBOARD_DEFAULTS);
            applySectionViewPreferencesToForm(result.section_view_preferences || ASM_SECTION_VIEW_DEFAULTS);
            syncSectionViewButtons();
            showSettingsAlert(result.message || 'Dashboard settings updated successfully.', 'success');
        } catch (error) {
            showSettingsAlert(error.message || 'Failed to save settings.', 'error');
        } finally {
            dashboardSettingsIsSaving = false;
            setDashboardSaveButtonState(false);
        }
    }

    function queueDashboardSettingsAutosave() {
        clearTimeout(dashboardSettingsAutosaveTimer);
        dashboardSettingsAutosaveTimer = setTimeout(function () {
            saveDashboardSettings();
        }, 350);
    }

    document.getElementById('dashboardSettingsSaveBtn')?.addEventListener('click', saveDashboardSettings);
    document.getElementById('dashboardSettingsResetBtn')?.addEventListener('click', function () {
        applySettingsToForm(ASM_DASHBOARD_DEFAULTS);
        queueDashboardSettingsAutosave();
        showSettingsAlert('Default dashboard layout restored. Saving changes...', 'success');
    });
    document.querySelectorAll('[data-setting-key]').forEach(function (input) {
        input.addEventListener('change', queueDashboardSettingsAutosave);
    });
    document.querySelectorAll('[data-view-setting-key]').forEach(function (button) {
        button.addEventListener('click', function () {
            const key = button.dataset.viewSettingKey;
            const value = button.dataset.viewSettingValue;
            document.querySelectorAll(`[data-view-setting-key="${key}"]`).forEach(function (peer) {
                const isActive = peer.dataset.viewSettingValue === value;
                peer.classList.toggle('asm-settings-btn-primary', isActive);
                peer.classList.toggle('asm-settings-btn-secondary', !isActive);
            });
            syncSectionViewButtons();
            queueDashboardSettingsAutosave();
        });
    });

    loadDashboardSettings();
</script>
@endpush

@extends('layouts.app')

@section('title', 'Lead Automation Rules')

@section('header-actions')
    <div class="flex items-center gap-3">
        @if($asmCnpAvailable ?? false)
            <a href="{{ route('admin.automation.cnp.index') }}"
               class="px-4 py-2 bg-white border border-[#205A44]/20 text-[#063A1C] rounded-lg hover:bg-green-50 transition-colors duration-200 text-sm font-medium">
                <i class="fas fa-robot mr-1.5"></i> ASM CNP Automation
            </a>
        @endif
        <a href="{{ route('admin.automation.create') }}"
           class="px-4 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg hover:from-[#205A44] hover:to-[#15803d] transition-colors duration-200 text-sm font-medium">
            <i class="fas fa-plus mr-1.5"></i> Create Automation
        </a>
    </div>
@endsection

@section('content')

<style>
    .automation-page {
        max-width: 100%;
    }
    .automation-hero {
        display: flex;
        align-items: stretch;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
        padding: 1.25rem;
        border: 1px solid #dbe7df;
        border-radius: 12px;
        background: linear-gradient(135deg, #ffffff 0%, #f3faf6 100%);
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
    }
    .automation-hero-title {
        display: flex;
        align-items: flex-start;
        gap: 0.9rem;
        min-width: 0;
    }
    .automation-hero-icon {
        width: 46px;
        height: 46px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        background: linear-gradient(135deg, #063A1C, #205A44);
        box-shadow: 0 6px 18px rgba(6, 58, 28, 0.22);
        flex-shrink: 0;
    }
    .automation-hero-actions {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        flex-wrap: wrap;
        justify-content: flex-end;
    }
    .automation-primary-btn,
    .automation-secondary-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.45rem;
        min-height: 40px;
        border-radius: 8px;
        padding: 0.65rem 0.95rem;
        font-size: 0.86rem;
        font-weight: 800;
        text-decoration: none;
        transition: all 0.18s ease;
        white-space: nowrap;
    }
    .automation-primary-btn {
        color: #fff;
        background: linear-gradient(135deg, #063A1C, #047857);
        box-shadow: 0 6px 16px rgba(6, 58, 28, 0.22);
    }
    .automation-primary-btn:hover {
        color: #fff;
        transform: translateY(-1px);
        box-shadow: 0 10px 22px rgba(6, 58, 28, 0.25);
    }
    .automation-secondary-btn {
        color: #063A1C;
        background: #fff;
        border: 1px solid #cfe0d6;
    }
    .automation-secondary-btn:hover {
        color: #063A1C;
        background: #f0fdf4;
    }
    .automation-floating-create {
        position: fixed;
        right: 24px;
        bottom: 24px;
        z-index: 1055;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.45rem;
        min-height: 44px;
        border-radius: 999px;
        padding: 0.75rem 1.05rem;
        color: #fff;
        background: linear-gradient(135deg, #063A1C, #047857);
        box-shadow: 0 14px 30px rgba(6, 58, 28, 0.28);
        font-size: 0.88rem;
        font-weight: 900;
        text-decoration: none;
    }
    .automation-floating-create:hover {
        color: #fff;
        transform: translateY(-1px);
        box-shadow: 0 18px 34px rgba(6, 58, 28, 0.34);
    }
    .automation-mini-create {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
        min-height: 32px;
        padding: 0.4rem 0.7rem;
        border-radius: 7px;
        color: #fff;
        background: #063A1C;
        font-size: 0.76rem;
        font-weight: 900;
        text-decoration: none;
        white-space: nowrap;
    }
    .automation-mini-create:hover {
        color: #fff;
        background: #047857;
    }
    .automation-stats {
        display: grid;
        grid-template-columns: repeat(4, minmax(120px, 1fr));
        gap: 0.75rem;
        margin-bottom: 1rem;
    }
    .automation-stat {
        display: flex;
        align-items: center;
        gap: 0.7rem;
        padding: 0.9rem 1rem;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        background: #fff;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.05);
    }
    .automation-stat i {
        width: 34px;
        height: 34px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #ecfdf5;
        color: #047857;
    }
    .automation-shortcuts {
        display: grid;
        grid-template-columns: repeat(6, minmax(130px, 1fr));
        gap: 0.7rem;
        margin-bottom: 1.1rem;
    }
    .automation-shortcut {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        padding: 0.85rem;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        background: #fff;
        color: #111827;
        text-decoration: none;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
        transition: all 0.18s ease;
        min-width: 0;
    }
    .automation-shortcut:hover {
        color: #063A1C;
        border-color: #b7d4c1;
        background: #f7fbf8;
        transform: translateY(-1px);
    }
    .automation-shortcut span {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        flex-shrink: 0;
    }
    .automation-shortcut.facebook span { background: linear-gradient(135deg,#1877f2,#0a56c2); }
    .automation-shortcut.website span { background: linear-gradient(135deg,#0891b2,#0e7490); }
    .automation-shortcut.whatsapp span { background: linear-gradient(135deg,#16a34a,#15803d); }
    .automation-shortcut.calling span { background: linear-gradient(135deg,#7c3aed,#5b21b6); }
    .automation-shortcut.portal span { background: linear-gradient(135deg,#064e3b,#047857); }
    .automation-shortcut.import span { background: linear-gradient(135deg,#475569,#1f2937); }
    .auto-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1.5rem;
        max-width: 100%;
    }
    .automation-folder {
        margin-bottom: 0.75rem;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        background: #ffffff;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
        overflow: hidden;
    }
    .automation-folder[open] {
        box-shadow: 0 4px 14px rgba(15, 23, 42, 0.10);
    }
    .automation-folder-summary {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 1rem 1.1rem;
        cursor: pointer;
        list-style: none;
        background: #f8fafc;
        border-bottom: 1px solid transparent;
    }
    .automation-folder[open] .automation-folder-summary { border-bottom-color: #e5e7eb; }
    .automation-folder-summary:hover { background: #f3f7f5; }
    .automation-folder-summary::-webkit-details-marker { display: none; }
    .automation-folder-title {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        min-width: 0;
    }
    .automation-folder-icon {
        width: 38px;
        height: 38px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        flex-shrink: 0;
        box-shadow: 0 2px 5px rgba(15, 23, 42, 0.16);
    }
    .automation-folder-icon.facebook { background: linear-gradient(135deg,#1877f2,#0a56c2); }
    .automation-folder-icon.website { background: linear-gradient(135deg,#0891b2,#0e7490); }
    .automation-folder-icon.whatsapp { background: linear-gradient(135deg,#16a34a,#15803d); }
    .automation-folder-icon.calling { background: linear-gradient(135deg,#7c3aed,#5b21b6); }
    .automation-folder-icon.portal { background: linear-gradient(135deg,#064e3b,#047857); }
    .automation-folder-icon.system { background: linear-gradient(135deg,#063A1C,#205A44); }
    .automation-folder-icon.other { background: linear-gradient(135deg,#64748b,#334155); }
    .automation-folder-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 2rem;
        height: 1.5rem;
        border-radius: 999px;
        background: #e2e8f0;
        color: #334155;
        font-size: 0.75rem;
        font-weight: 700;
        padding: 0 0.55rem;
    }
    .automation-folder-right {
        display: inline-flex;
        align-items: center;
        gap: 0.75rem;
        flex-shrink: 0;
    }
    .automation-folder-chevron {
        color: #64748b;
        transition: transform 0.18s ease;
    }
    .automation-folder[open] .automation-folder-chevron {
        transform: rotate(180deg);
    }
    .automation-folder-body {
        padding: 1rem;
        background: #f8fafc;
    }
    .automation-folder-body .auto-grid {
        margin-bottom: 0;
    }
    @media (max-width: 640px) {
        .auto-grid { grid-template-columns: 1fr; }
        .automation-folder-summary { align-items: flex-start; }
        .automation-hero { flex-direction: column; }
        .automation-hero-actions { justify-content: flex-start; }
        .automation-stats { grid-template-columns: 1fr 1fr; }
        .automation-shortcuts { grid-template-columns: 1fr 1fr; }
        .automation-floating-create { right: 14px; bottom: 14px; }
        .automation-mini-create { display: none; }
    }
    @media (max-width: 1100px) {
        .automation-shortcuts { grid-template-columns: repeat(3, minmax(130px, 1fr)); }
    }
    @media (max-width: 820px) {
        .automation-stats { grid-template-columns: 1fr 1fr; }
    }

    /* ── Rule Card — mirrors .lead-card exactly ── */
    .rule-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.10);
        border: 1px solid #e5e7eb;
        padding: 1.5rem;
        transition: all 0.3s ease;
        display: flex;
        flex-direction: column;
        min-height: 240px;
    }
    .rule-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        transform: translateY(-2px);
    }
    .rule-card.inactive { opacity: 0.72; }

    .rule-card-header {
        display: flex;
        align-items: center;
        gap: 0.875rem;
        margin-bottom: 0.875rem;
    }

    /* Avatar — same gradient style as lead-card-avatar */
    .rule-avatar {
        width: 48px; height: 48px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.2rem; flex-shrink: 0;
        box-shadow: 0 2px 4px rgba(0,0,0,0.15);
        color: white;
    }
    .rule-avatar.facebook { background: linear-gradient(135deg,#1877f2,#0a56c2); }
    .rule-avatar.pabbly   { background: linear-gradient(135deg,#ff6600,#d45200); }
    .rule-avatar.mcube    { background: linear-gradient(135deg,#7c3aed,#5b21b6); }
    .rule-avatar.all      { background: linear-gradient(135deg,#16a34a,#15803d,#166534); }
    .rule-avatar.other    { background: linear-gradient(135deg,#0891b2,#0e7490); }
    .rule-avatar.cnp      { background: linear-gradient(135deg,#063A1C,#205A44,#15803d); }
    .rule-avatar.outcome  { background: linear-gradient(135deg,#b42318,#dc2626); }
    .rule-avatar.nna      { background: linear-gradient(135deg,#064e3b,#047857); }

    .rule-card-body { flex: 1; margin-bottom: 0.875rem; }

    .rule-meta-item {
        display: flex; align-items: center; gap: 0.4rem;
        font-size: 0.8rem; color: #6b7280;
        margin-bottom: 0.3rem;
    }
    .rule-meta-item i { color: #9ca3af; width: 14px; text-align: center; font-size: 0.72rem; }
    .rule-meta-item.task-on  { color: #16a34a; }
    .rule-meta-item.task-on i { color: #16a34a; }

    .user-chips { display: flex; flex-wrap: wrap; gap: 0.3rem; margin-top: 0.5rem; }
    .user-chip {
        display: inline-flex; align-items: center; gap: 0.25rem;
        background: #f3f4f6; border: 1px solid #e5e7eb;
        border-radius: 99px; padding: 0.15rem 0.55rem;
        font-size: 0.7rem; font-weight: 500; color: #374151;
    }
    .user-chip.more { background: #063A1C; border-color: #063A1C; color: white; }

    /* Footer — mirrors .lead-card-footer */
    .rule-card-footer {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
        margin-top: auto;
        padding-top: 1rem;
        border-top: 1px solid #e5e7eb;
    }
    .rule-card-footer .btn-action {
        display: flex !important;
        align-items: center; justify-content: center;
        gap: 0.35rem;
        padding: 7px 10px;
        border-radius: 8px;
        font-size: 0.75rem; font-weight: 500;
        border: none; cursor: pointer;
        text-decoration: none;
        transition: all 0.2s;
        box-shadow: 0 1px 3px rgba(0,0,0,0.12);
        color: white;
    }
    .rule-card-footer .btn-action.full { grid-column: 1 / -1; }
    .btn-toggle-pause  { background: linear-gradient(to right, #d97706, #b45309); }
    .btn-toggle-pause:hover  { background: linear-gradient(to right, #b45309, #92400e); }
    .btn-toggle-play   { background: linear-gradient(to right, #16a34a, #15803d); }
    .btn-toggle-play:hover   { background: linear-gradient(to right, #15803d, #166534); }
    .btn-edit  { background: linear-gradient(to right, #2563eb, #1d4ed8); }
    .btn-edit:hover  { background: linear-gradient(to right, #1d4ed8, #1e40af); }
    .btn-delete { background: linear-gradient(to right, #dc2626, #b91c1c); }
    .btn-delete:hover { background: linear-gradient(to right, #b91c1c, #991b1b); }
    .btn-power-off { background: #b42318; }
    .btn-power-off:hover { background: #8f1d14; }
    .btn-power-on { background: #15803d; }
    .btn-power-on:hover { background: #166534; }
    .btn-manage { background: linear-gradient(to right, #0369a1, #0284c7); }
    .btn-manage:hover { background: linear-gradient(to right, #075985, #0369a1); }
    .empty-auto-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.10);
        border: 1px solid #e5e7eb;
        padding: 2rem;
        text-align: center;
    }
    .wa-rule-card.expanded {
        grid-column: span 2;
    }
    .wa-automation-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.1fr) minmax(260px, 0.9fr);
        gap: 1rem;
    }
    .wa-pool-list {
        max-height: 240px;
        overflow: auto;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        background: #f9fafb;
        padding: 0.75rem;
    }
    .wa-pool-item {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        padding: 0.75rem;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        margin-bottom: 0.65rem;
    }
    .wa-pool-item:last-child {
        margin-bottom: 0;
    }
    .wa-meta-box {
        background: linear-gradient(135deg, #ecfdf5, #f0fdf4);
        border: 1px solid #bbf7d0;
        border-radius: 12px;
        padding: 1rem;
    }

    @media (max-width: 767px) {
        .rule-card { padding: 1rem; min-height: auto; }
        .rule-card-footer .btn-action { padding: 6px 4px !important; font-size: 11px !important; border-radius: 6px !important; }
        .wa-rule-card.expanded { grid-column: span 1; }
        .wa-automation-grid { grid-template-columns: 1fr; }
    }
</style>

    @if(session('success'))
        <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg flex items-center justify-between">
            <span><i class="fas fa-check-circle mr-2"></i>{{ session('success') }}</span>
            <button onclick="this.parentElement.remove()" class="text-green-700 hover:text-green-900 font-bold">&times;</button>
        </div>
    @endif

    @if(!($asmCnpAvailable ?? false))
        <div class="mb-4 bg-amber-50 border border-amber-300 text-amber-900 px-4 py-3 rounded-lg">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            ASM CNP Automation card hidden hai kyunki uski database tables abhi create nahi hui hain. Run `php artisan migrate`.
        </div>
    @endif

    @php
        $ninetyNineAcresRule = $ninetyNineAcresRule ?? $rules
            ->first(fn($rule) => $rule->source === '99acres'
                && is_null($rule->fb_form_id)
                && is_null($rule->google_sheet_config_id));
        $ninetyNineAcresStats = $ninetyNineAcresStats ?? [
            'is_configured' => (bool) $ninetyNineAcresRule,
            'is_active' => (bool) ($ninetyNineAcresRule?->is_active ?? false),
            'method' => $ninetyNineAcresRule?->assignment_method ?? 'round_robin',
            'users_count' => $ninetyNineAcresRule?->users?->count() ?? 0,
            'today_assigned' => 0,
            'week_assigned' => 0,
        ];
        $displayRules = $rules
            ->reject(fn($rule) => $rule->source === '99acres'
                && is_null($rule->fb_form_id)
                && is_null($rule->google_sheet_config_id))
            ->values();
        $sourceFolders = [
            'facebook' => ['label' => 'Facebook Lead Ads', 'icon' => 'fab fa-facebook', 'tone' => 'facebook'],
            'website' => ['label' => 'Website & Sheets', 'icon' => 'fas fa-table-columns', 'tone' => 'website'],
            'whatsapp' => ['label' => 'WhatsApp', 'icon' => 'fab fa-whatsapp', 'tone' => 'whatsapp'],
            'calling' => ['label' => 'Calling & IVR', 'icon' => 'fas fa-phone-volume', 'tone' => 'calling'],
            'portal' => ['label' => 'Property Portals', 'icon' => 'fas fa-building', 'tone' => 'portal'],
            'system' => ['label' => 'System Automations', 'icon' => 'fas fa-gears', 'tone' => 'system'],
            'other' => ['label' => 'Other Sources', 'icon' => 'fas fa-layer-group', 'tone' => 'other'],
        ];
        $rulesByFolder = $displayRules->groupBy(function ($rule) {
            return match ($rule->source) {
                'facebook_lead_ads' => 'facebook',
                'website', 'google_sheets' => 'website',
                'mcube' => 'calling',
                '99acres' => 'portal',
                'all' => 'system',
                default => 'other',
            };
        });
        $specialCounts = [
            'whatsapp' => 1,
            'calling' => 1,
            'portal' => 1,
            'system' => 2 + ((($asmCnpAvailable ?? false) && $asmCnpConfig) ? 1 : 0),
        ];
        $folderCount = fn (string $key) => ($rulesByFolder->get($key, collect())->count()) + ($specialCounts[$key] ?? 0);
        $activeRulesCount = $displayRules->where('is_active', true)->count()
            + (($ninetyNineAcresStats['is_active'] ?? false) ? 1 : 0)
            + (!empty($whatsAppAutomationSettings['enabled'] ?? false) ? 1 : 0)
            + 1
            + ((($asmCnpAvailable ?? false) && $asmCnpConfig && $asmCnpConfig->is_enabled && $asmCnpConfig->is_active) ? 1 : 0);
        $totalAutomationCount = $displayRules->count()
            + 4
            + ((($asmCnpAvailable ?? false) && $asmCnpConfig) ? 1 : 0);
    @endphp

<div class="automation-page">
    <a href="{{ route('admin.automation.create') }}" class="automation-floating-create">
        <i class="fas fa-plus"></i> Create Automation
    </a>

    <div class="automation-hero">
        <div class="automation-hero-title">
            <span class="automation-hero-icon"><i class="fas fa-wand-magic-sparkles"></i></span>
            <div class="min-w-0">
                <div class="text-xs font-black uppercase tracking-wide text-emerald-700">Lead Automation Center</div>
                <h1 class="text-2xl font-black text-slate-950 mt-1">Create and manage source automation</h1>
                <p class="text-sm text-slate-600 mt-1 max-w-3xl">Facebook, Website, 99acres, IVR, WhatsApp aur manual/import source ke liye same common assignment engine use hota hai.</p>
            </div>
        </div>
        <div class="automation-hero-actions">
            <a href="{{ route('admin.automation.create') }}" class="automation-primary-btn">
                <i class="fas fa-plus"></i> Create Automation
            </a>
            <a href="{{ route('admin.automation.create', ['source_type' => 'facebook_lead_ads']) }}" class="automation-secondary-btn">
                <i class="fab fa-facebook"></i> Facebook Rule
            </a>
        </div>
    </div>

    <div class="automation-stats">
        <div class="automation-stat">
            <i class="fas fa-bolt"></i>
            <div>
                <div class="text-lg font-black text-slate-950">{{ $totalAutomationCount }}</div>
                <div class="text-xs font-semibold text-slate-500">Total Automations</div>
            </div>
        </div>
        <div class="automation-stat">
            <i class="fas fa-circle-check"></i>
            <div>
                <div class="text-lg font-black text-slate-950">{{ $activeRulesCount }}</div>
                <div class="text-xs font-semibold text-slate-500">Active</div>
            </div>
        </div>
        <div class="automation-stat">
            <i class="fas fa-users-gear"></i>
            <div>
                <div class="text-lg font-black text-slate-950">{{ $displayRules->sum(fn($rule) => $rule->assignment_method === 'single_user' ? (int) filled($rule->single_user_id) : $rule->users->count()) }}</div>
                <div class="text-xs font-semibold text-slate-500">Rule Users</div>
            </div>
        </div>
        <div class="automation-stat">
            <i class="fas fa-layer-group"></i>
            <div>
                <div class="text-lg font-black text-slate-950">{{ count($sourceFolders) }}</div>
                <div class="text-xs font-semibold text-slate-500">Source Groups</div>
            </div>
        </div>
    </div>

    <div class="automation-shortcuts">
        <a href="{{ route('admin.automation.create', ['source_type' => 'facebook_lead_ads']) }}" class="automation-shortcut facebook">
            <span><i class="fab fa-facebook"></i></span>
            <div class="min-w-0">
                <div class="text-sm font-black truncate">Facebook</div>
                <div class="text-xs text-slate-500 truncate">Lead Ads form</div>
            </div>
        </a>
        <a href="{{ route('admin.automation.create', ['source_type' => 'website']) }}" class="automation-shortcut website">
            <span><i class="fas fa-globe"></i></span>
            <div class="min-w-0">
                <div class="text-sm font-black truncate">Website</div>
                <div class="text-xs text-slate-500 truncate">Webhook/source</div>
            </div>
        </a>
        <a href="{{ route('admin.automation.create', ['source_type' => '99acres']) }}" class="automation-shortcut portal">
            <span><i class="fas fa-building"></i></span>
            <div class="min-w-0">
                <div class="text-sm font-black truncate">99acres</div>
                <div class="text-xs text-slate-500 truncate">Portal leads</div>
            </div>
        </a>
        <a href="{{ route('admin.automation.create', ['source_type' => 'ivr']) }}" class="automation-shortcut calling">
            <span><i class="fas fa-phone-volume"></i></span>
            <div class="min-w-0">
                <div class="text-sm font-black truncate">IVR</div>
                <div class="text-xs text-slate-500 truncate">Call source</div>
            </div>
        </a>
        <a href="{{ route('admin.automation.create', ['source_type' => 'whatsapp']) }}" class="automation-shortcut whatsapp">
            <span><i class="fab fa-whatsapp"></i></span>
            <div class="min-w-0">
                <div class="text-sm font-black truncate">WhatsApp</div>
                <div class="text-xs text-slate-500 truncate">Chat leads</div>
            </div>
        </a>
        <a href="{{ route('admin.automation.create', ['source_type' => 'manual_import']) }}" class="automation-shortcut import">
            <span><i class="fas fa-file-import"></i></span>
            <div class="min-w-0">
                <div class="text-sm font-black truncate">Manual Import</div>
                <div class="text-xs text-slate-500 truncate">CSV/import leads</div>
            </div>
        </a>
    </div>

    <details class="automation-folder" open>
        <summary class="automation-folder-summary">
            <div class="automation-folder-title">
                <span class="automation-folder-icon portal"><i class="fas fa-building"></i></span>
                <div>
                    <div class="text-sm font-black text-slate-900">Property Portals</div>
                    <div class="text-xs text-slate-500">99acres aur real-estate portal lead distribution</div>
                </div>
            </div>
            <span class="automation-folder-right">
                <a href="{{ route('admin.automation.create') }}" class="automation-mini-create" onclick="event.stopPropagation();">
                    <i class="fas fa-plus"></i> Create
                </a>
                <span class="automation-folder-count">{{ $folderCount('portal') }}</span>
                <i class="fas fa-chevron-down automation-folder-chevron"></i>
            </span>
        </summary>
        <div class="automation-folder-body">
            <div class="auto-grid">
        <div class="rule-card {{ !($ninetyNineAcresStats['is_active'] ?? false) ? 'inactive' : '' }}">
            <div class="rule-card-header">
                <div class="rule-avatar nna">
                    <i class="fas fa-building"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-base font-semibold text-gray-900 truncate">99acres Lead Distribution</h3>
                    <p class="text-xs text-gray-500 mt-0.5">
                        @if($ninetyNineAcresStats['is_active'] ?? false)
                            <span class="inline-flex items-center gap-1 text-green-600 font-medium">
                                <span class="w-1.5 h-1.5 rounded-full bg-green-500 inline-block"></span> Active
                            </span>
                        @elseif($ninetyNineAcresStats['is_configured'] ?? false)
                            <span class="inline-flex items-center gap-1 text-gray-500 font-medium">
                                <span class="w-1.5 h-1.5 rounded-full bg-gray-400 inline-block"></span> Paused
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 text-amber-600 font-medium">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 inline-block"></span> Not configured
                            </span>
                        @endif
                    </p>
                </div>
            </div>

            <div class="rule-card-body">
                <div class="rule-meta-item">
                    <i class="fas fa-random"></i>
                    {{ \App\Models\SourceAutomationRule::getMethodLabel($ninetyNineAcresStats['method'] ?? 'round_robin') }}
                </div>
                <div class="rule-meta-item">
                    <i class="fas fa-users"></i>
                    Pool: {{ $ninetyNineAcresStats['users_count'] ?? 0 }} users
                </div>
                <div class="rule-meta-item">
                    <i class="fas fa-calendar-day"></i>
                    Today assigned: {{ $ninetyNineAcresStats['today_assigned'] ?? 0 }}
                </div>
                <div class="rule-meta-item">
                    <i class="fas fa-calendar-week"></i>
                    This week: {{ $ninetyNineAcresStats['week_assigned'] ?? 0 }}
                </div>
                <div class="rule-meta-item {{ ($ninetyNineAcresRule?->auto_create_task ?? true) ? 'task-on' : '' }}">
                    <i class="fas {{ ($ninetyNineAcresRule?->auto_create_task ?? true) ? 'fa-check-circle' : 'fa-times-circle' }}"></i>
                    Task {{ ($ninetyNineAcresRule?->auto_create_task ?? true) ? 'ON' : 'OFF' }}
                </div>
            </div>

            <div class="rule-card-footer">
                <a href="{{ route('admin.automation.99acres.edit') }}" class="btn-action btn-edit">
                    <i class="fas fa-pen"></i> Edit
                </a>
                @if($ninetyNineAcresRule)
                    <a href="{{ route('admin.automation.history', $ninetyNineAcresRule) }}" class="btn-action btn-manage">
                        <i class="fas fa-history"></i> History
                    </a>
                @else
                    <a href="{{ route('integrations.99acres.index') }}" class="btn-action btn-manage">
                        <i class="fas fa-link"></i> Webhook
                    </a>
                @endif
            </div>
        </div>
        @foreach($rulesByFolder->get('portal', collect()) as $rule)
            @include('admin.automation.partials.rule-card', ['rule' => $rule])
        @endforeach
            </div>
        </div>
    </details>

    <details class="automation-folder" open>
        <summary class="automation-folder-summary">
            <div class="automation-folder-title">
                <span class="automation-folder-icon whatsapp"><i class="fab fa-whatsapp"></i></span>
                <div>
                    <div class="text-sm font-black text-slate-900">WhatsApp</div>
                    <div class="text-xs text-slate-500">WhatsApp source aur conversation lead automation</div>
                </div>
            </div>
            <span class="automation-folder-right">
                <span class="automation-folder-count">{{ $folderCount('whatsapp') }}</span>
                <i class="fas fa-chevron-down automation-folder-chevron"></i>
            </span>
        </summary>
        <div class="automation-folder-body">
            <div class="auto-grid">
        <div class="rule-card wa-rule-card {{ !($whatsAppAutomationSettings['enabled'] ?? false) ? 'inactive' : '' }}" id="whatsappAutomationCard">
            <div class="rule-card-header">
                <div class="rule-avatar other">
                    <i class="fab fa-whatsapp"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-base font-semibold text-gray-900 truncate">WhatsApp Auto Distribution</h3>
                    <p class="text-xs text-gray-500 mt-0.5">
                        @if($whatsAppAutomationSettings['enabled'] ?? false)
                            <span class="inline-flex items-center gap-1 text-green-600 font-medium">
                                <span class="w-1.5 h-1.5 rounded-full bg-green-500 inline-block"></span> Active
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 text-gray-400 font-medium">
                                <span class="w-1.5 h-1.5 rounded-full bg-gray-400 inline-block"></span> Disabled
                            </span>
                        @endif
                    </p>
                </div>
            </div>

            <div class="rule-card-body">
                <div class="rule-meta-item">
                    <i class="fas fa-random"></i>
                    {{ \App\Models\SourceAutomationRule::getMethodLabel($whatsAppAutomationSettings['assignment_method'] ?? 'round_robin') }}
                </div>
                <div class="rule-meta-item {{ !empty($whatsAppAutomationSettings['create_calling_task']) ? 'task-on' : '' }}">
                    <i class="fas {{ !empty($whatsAppAutomationSettings['create_calling_task']) ? 'fa-check-circle' : 'fa-times-circle' }}"></i>
                    Calling Task {{ !empty($whatsAppAutomationSettings['create_calling_task']) ? 'ON' : 'OFF' }}
                </div>
                <div class="rule-meta-item {{ !empty($whatsAppAutomationSettings['notify_assigned_user']) ? 'task-on' : '' }}">
                    <i class="fas {{ !empty($whatsAppAutomationSettings['notify_assigned_user']) ? 'fa-bell' : 'fa-bell-slash' }}"></i>
                    Notify {{ !empty($whatsAppAutomationSettings['notify_assigned_user']) ? 'ON' : 'OFF' }}
                </div>
                <div class="rule-meta-item">
                    <i class="fas fa-users"></i>
                    Pool: {{ count($whatsAppAutomationSettings['user_ids'] ?? []) }} users
                </div>

                @if(($whatsAppAutomationSettings['assignment_method'] ?? '') === 'single_user' && !empty($whatsAppAutomationSettings['single_user_id']))
                    @php $singleAssignedUser = $whatsAppEligibleUsers->firstWhere('id', $whatsAppAutomationSettings['single_user_id']); @endphp
                    @if($singleAssignedUser)
                        <div class="user-chips">
                            <span class="user-chip">
                                <i class="fas fa-user" style="font-size:0.6rem;"></i>
                                {{ $singleAssignedUser->name }}
                            </span>
                        </div>
                    @endif
                @elseif(!empty($whatsAppAutomationSettings['user_ids']))
                    <div class="user-chips">
                        @foreach($whatsAppEligibleUsers->whereIn('id', $whatsAppAutomationSettings['user_ids'])->take(4) as $eligibleUser)
                            <span class="user-chip">
                                {{ $eligibleUser->name }}
                                @if(($whatsAppAutomationSettings['assignment_method'] ?? '') === 'percentage' && !empty($whatsAppAutomationSettings['user_percentages'][$eligibleUser->id]))
                                    <span style="opacity:0.55">{{ rtrim(rtrim(number_format($whatsAppAutomationSettings['user_percentages'][$eligibleUser->id], 2), '0'), '.') }}%</span>
                                @endif
                            </span>
                        @endforeach
                        @if(count($whatsAppAutomationSettings['user_ids']) > 4)
                            <span class="user-chip more">+{{ count($whatsAppAutomationSettings['user_ids']) - 4 }}</span>
                        @endif
                    </div>
                @endif

                <form id="admin-whatsapp-automation-form" class="hidden mt-4 pt-4 border-t border-gray-200">
                    @csrf
                    <div class="wa-automation-grid">
                        <div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-4">
                                <label class="flex items-center gap-3 px-4 py-3 border border-gray-200 rounded-xl bg-gray-50">
                                    <input type="checkbox" name="auto_assign_enabled" value="1" {{ !empty($whatsAppAutomationSettings['enabled']) ? 'checked' : '' }} class="w-4 h-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500">
                                    <span class="text-sm font-medium text-gray-700">Enable</span>
                                </label>
                                <label class="flex items-center gap-3 px-4 py-3 border border-gray-200 rounded-xl bg-gray-50">
                                    <input type="checkbox" name="create_calling_task" value="1" {{ !empty($whatsAppAutomationSettings['create_calling_task']) ? 'checked' : '' }} class="w-4 h-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500">
                                    <span class="text-sm font-medium text-gray-700">Task On Assign</span>
                                </label>
                                <label class="flex items-center gap-3 px-4 py-3 border border-gray-200 rounded-xl bg-gray-50 md:col-span-2">
                                    <input type="checkbox" name="notify_assigned_user" value="1" {{ !empty($whatsAppAutomationSettings['notify_assigned_user']) ? 'checked' : '' }} class="w-4 h-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500">
                                    <span class="text-sm font-medium text-gray-700">Notify assigned user</span>
                                </label>
                            </div>

                            <div class="mb-4">
                                <label for="admin-wa-auto-assign-mode" class="block text-sm font-medium text-gray-700 mb-2">Assignment Method</label>
                                <select id="admin-wa-auto-assign-mode" name="auto_assign_mode" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                    <option value="round_robin" {{ ($whatsAppAutomationSettings['assignment_method'] ?? 'round_robin') === 'round_robin' ? 'selected' : '' }}>Round Robin</option>
                                    <option value="first_available" {{ ($whatsAppAutomationSettings['assignment_method'] ?? '') === 'first_available' ? 'selected' : '' }}>First Available</option>
                                    <option value="percentage" {{ ($whatsAppAutomationSettings['assignment_method'] ?? '') === 'percentage' ? 'selected' : '' }}>Percentage</option>
                                    <option value="single_user" {{ ($whatsAppAutomationSettings['assignment_method'] ?? '') === 'single_user' ? 'selected' : '' }}>Single User</option>
                                </select>
                            </div>

                            <div class="mb-4 {{ ($whatsAppAutomationSettings['assignment_method'] ?? '') === 'single_user' ? '' : 'hidden' }}" id="admin-wa-single-user-wrapper">
                                <label for="admin-wa-single-user" class="block text-sm font-medium text-gray-700 mb-2">Single User</label>
                                <select id="admin-wa-single-user" name="single_user_id" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                    <option value="">Select user</option>
                                    @foreach($whatsAppEligibleUsers as $eligibleUser)
                                        <option value="{{ $eligibleUser->id }}" {{ (int) ($whatsAppAutomationSettings['single_user_id'] ?? 0) === $eligibleUser->id ? 'selected' : '' }}>{{ $eligibleUser->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div>
                            <div class="text-sm font-semibold text-gray-900 mb-2">Eligible Users</div>
                            <div class="wa-pool-list">
                                @forelse($whatsAppEligibleUsers as $eligibleUser)
                                    <label class="wa-pool-item">
                                        <input type="checkbox"
                                               name="eligible_user_ids[]"
                                               value="{{ $eligibleUser->id }}"
                                               {{ in_array($eligibleUser->id, $whatsAppAutomationSettings['user_ids'] ?? [], true) ? 'checked' : '' }}
                                               class="mt-1 w-4 h-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500 admin-wa-user-checkbox">
                                        <div class="min-w-0 flex-1">
                                            <div class="text-sm font-semibold text-gray-900">{{ $eligibleUser->name }}</div>
                                            <div class="text-xs text-gray-500">{{ $eligibleUser->role?->name ?? 'User' }}</div>
                                            <div class="mt-2 {{ ($whatsAppAutomationSettings['assignment_method'] ?? '') === 'percentage' ? '' : 'hidden' }} admin-wa-percentage-row">
                                                <input type="number"
                                                       step="0.01"
                                                       min="0"
                                                       name="user_percentages[{{ $eligibleUser->id }}]"
                                                       value="{{ $whatsAppAutomationSettings['user_percentages'][$eligibleUser->id] ?? '' }}"
                                                       placeholder="Percentage"
                                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                            </div>
                                        </div>
                                    </label>
                                @empty
                                    <div class="text-sm text-gray-500">No eligible users found.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 mt-4">
                        <button type="submit" class="btn-action btn-manage full" style="max-width:260px;">
                            <i class="fas fa-save"></i> Save
                        </button>
                    </div>
                </form>
            </div>

            <div class="rule-card-footer">
                <button type="button" class="btn-action btn-edit admin-wa-toggle">
                    <i class="fas fa-pen"></i> Edit
                </button>
                <a href="{{ route('integrations.whatsapp') }}" class="btn-action btn-manage">
                    <i class="fas fa-cog"></i> Open
                </a>
            </div>
        </div>
            </div>
        </div>
    </details>

    <details class="automation-folder" open>
        <summary class="automation-folder-summary">
            <div class="automation-folder-title">
                <span class="automation-folder-icon system"><i class="fas fa-gears"></i></span>
                <div>
                    <div class="text-sm font-black text-slate-900">System Automations</div>
                    <div class="text-xs text-slate-500">CRM-wide SLA aur lead lifecycle automations</div>
                </div>
            </div>
            <span class="automation-folder-right">
                <span class="automation-folder-count">{{ $folderCount('system') }}</span>
                <i class="fas fa-chevron-down automation-folder-chevron"></i>
            </span>
        </summary>
        <div class="automation-folder-body">
            <div class="auto-grid">
        @if(($asmCnpAvailable ?? false) && $asmCnpConfig)
            <div class="rule-card">
                <div class="rule-card-header">
                    <div class="rule-avatar cnp">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="text-base font-semibold text-gray-900 truncate">ASM CNP Automation</h3>
                        <p class="text-xs text-gray-500 mt-0.5">
                            <span class="inline-flex items-center gap-1 text-green-600 font-medium">
                                <span class="w-1.5 h-1.5 rounded-full bg-green-500 inline-block"></span> Retry Flow Active
                            </span>
                            <span class="ml-2 inline-flex items-center gap-1 {{ $asmCnpConfig->is_active ? 'text-sky-700' : 'text-gray-500' }} font-medium">
                                Auto Transfer {{ $asmCnpConfig->is_active ? 'On' : 'Off' }}
                            </span>
                        </p>
                    </div>
                </div>

                <div class="rule-card-body">
                    <div class="rule-meta-item">
                        <i class="fas fa-clock"></i>
                        Retry Delay: {{ $asmCnpConfig->retry_delay_minutes }} min
                    </div>
                    <div class="rule-meta-item">
                        <i class="fas fa-bolt"></i>
                        Transfer:
                        @if(($asmCnpConfig->transfer_rule_mode ?? 'count_only') === 'count_only')
                            Only CNP count
                        @elseif($asmCnpConfig->transfer_rule_mode === 'count_window_restart')
                            CNP within window (restart)
                        @else
                            CNP within window (reset)
                        @endif
                    </div>
                    <div class="rule-meta-item">
                        <i class="fas fa-phone-slash"></i>
                        Max CNP: {{ $asmCnpConfig->max_cnp_attempts }}
                    </div>
                    <div class="rule-meta-item">
                        <i class="fas fa-hourglass-half"></i>
                        Window: {{ $asmCnpConfig->transfer_window_hours ? $asmCnpConfig->transfer_window_hours . 'h' : 'Anytime' }}
                    </div>
                    <div class="rule-meta-item">
                        <i class="fas fa-redo-alt"></i>
                        Retry Tasks: {{ $asmCnpConfig->create_retry_tasks ? 'On' : 'Off' }}
                    </div>
                    <div class="rule-meta-item">
                        <i class="fas fa-random"></i>
                        Routing: {{ \Illuminate\Support\Str::of($asmCnpConfig->fallback_routing)->replace('_', ' ')->title() }}
                    </div>
                    <div class="rule-meta-item">
                        <i class="fas fa-users"></i>
                        Pool: {{ $asmCnpConfig->pool_users_count }} users
                    </div>
                    @if($asmCnpConfig->overrides_count)
                    <div class="rule-meta-item">
                        <i class="fas fa-route"></i>
                        Overrides: {{ $asmCnpConfig->overrides_count }}
                    </div>
                    @endif
                </div>

                <div class="rule-card-footer">
                    <form method="POST" action="{{ route('admin.automation.cnp.toggle') }}"
                          onsubmit="return confirm('Turn {{ $asmCnpConfig->is_active ? 'off' : 'on' }} ASM CNP auto transfer? CNP marking and retry will remain active.');">
                        @csrf
                        <input type="hidden" name="enabled" value="{{ $asmCnpConfig->is_active ? 0 : 1 }}">
                        <button type="submit" class="btn-action {{ $asmCnpConfig->is_active ? 'btn-power-off' : 'btn-power-on' }}" style="width:100%;">
                            <i class="fas fa-power-off"></i>
                            {{ $asmCnpConfig->is_active ? 'Turn Off Transfer' : 'Turn On Transfer' }}
                        </button>
                    </form>
                    <a href="{{ route('admin.automation.cnp.index') }}" class="btn-action btn-manage">
                        <i class="fas fa-sliders-h"></i> Manage
                    </a>
                </div>
            </div>
        @endif

        <div class="rule-card">
            <div class="rule-card-header">
                <div class="rule-avatar outcome">
                    <i class="fas fa-user-slash"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-base font-semibold text-gray-900 truncate">Junk / Not Interested Cleanup</h3>
                    <p class="text-xs text-gray-500 mt-0.5">
                        <span class="inline-flex items-center gap-1 text-emerald-600 font-medium">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block"></span> Always Active
                        </span>
                    </p>
                </div>
            </div>

            <div class="rule-card-body">
                <div class="rule-meta-item">
                    <i class="fas fa-bolt"></i>
                    Trigger: User marks lead Junk or Not Interested
                </div>
                <div class="rule-meta-item">
                    <i class="fas fa-user-check"></i>
                    Keeps the lead assigned to its current owner
                </div>
                <div class="rule-meta-item">
                    <i class="fas fa-check-circle"></i>
                    Completes the current task and stops follow-up
                </div>
                <div class="rule-meta-item">
                    <i class="fas fa-ban"></i>
                    Stops CNP transfer and automatic reassignment
                </div>
                <div class="rule-meta-item">
                    <i class="fas fa-clock-rotate-left"></i>
                    Preserves reason, user and marked time for audit
                </div>
            </div>

            <div class="rule-card-footer">
                <a href="{{ route('admin.other-leads.index') }}" class="btn-action btn-manage full">
                    <i class="fas fa-box-open"></i> Review Leads
                </a>
            </div>
        </div>

        <div class="rule-card">
            <div class="rule-card-header">
                <div class="rule-avatar all">
                    <i class="fas fa-stopwatch"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-base font-semibold text-gray-900 truncate">New Lead Response SLA</h3>
                    <p class="text-xs text-gray-500 mt-0.5">
                        <span class="inline-flex items-center gap-1 text-emerald-600 font-medium">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block"></span> CRM Automation
                        </span>
                    </p>
                </div>
            </div>

            <div class="rule-card-body">
                <div class="rule-meta-item">
                    <i class="fas fa-random"></i>
                    Reassigns new leads using source-wise round robin
                </div>
                <div class="rule-meta-item">
                    <i class="fas fa-user-clock"></i>
                    Works when no outcome is recorded within configured SLA
                </div>
                <div class="rule-meta-item">
                    <i class="fas fa-envelope-open-text"></i>
                    Escalates after pool users miss the response window
                </div>
                <div class="rule-meta-item">
                    <i class="fas fa-chart-line"></i>
                    Tracks lost leads and transfer audit history
                </div>
            </div>

            <div class="rule-card-footer">
                <a href="{{ route('crm.automation.sla.index') }}" class="btn-action btn-manage full">
                    <i class="fas fa-sliders-h"></i> Manage SLA
                </a>
            </div>
        </div>
        @foreach($rulesByFolder->get('system', collect()) as $rule)
            @include('admin.automation.partials.rule-card', ['rule' => $rule])
        @endforeach
            </div>
        </div>
    </details>

    <details class="automation-folder" open>
        <summary class="automation-folder-summary">
            <div class="automation-folder-title">
                <span class="automation-folder-icon calling"><i class="fas fa-phone-volume"></i></span>
                <div>
                    <div class="text-sm font-black text-slate-900">Calling & IVR</div>
                    <div class="text-xs text-slate-500">MCube, IVR receiver assignment, and call source rules</div>
                </div>
            </div>
            <span class="automation-folder-right">
                <span class="automation-folder-count">{{ $folderCount('calling') }}</span>
                <i class="fas fa-chevron-down automation-folder-chevron"></i>
            </span>
        </summary>
        <div class="automation-folder-body">
            <div class="auto-grid">
        <div class="rule-card">
            <div class="rule-card-header">
                <div class="rule-avatar mcube">
                    <i class="fas fa-phone-volume"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-base font-semibold text-gray-900 truncate">IVR Receiver Assignment</h3>
                    <p class="text-xs text-gray-500 mt-0.5">
                        <span class="inline-flex items-center gap-1 text-sky-700 font-medium">
                            <span class="w-1.5 h-1.5 rounded-full bg-sky-600 inline-block"></span> Editable Automation
                        </span>
                    </p>
                </div>
            </div>

            <div class="rule-card-body">
                <div class="rule-meta-item">
                    <i class="fas fa-user-check"></i>
                    Lead goes to the same user who receives the IVR call
                </div>
                <div class="rule-meta-item">
                    <i class="fas fa-users-cog"></i>
                    Receiver, team, pool, percentage, and fixed-user overrides are editable
                </div>
                <div class="rule-meta-item">
                    <i class="fas fa-clipboard-list"></i>
                    Assignment audit keeps the receiver and final assignee history
                </div>
            </div>

            <div class="rule-card-footer">
                <a href="{{ route('crm.automation.ivr.index') }}" class="btn-action btn-manage full">
                    <i class="fas fa-pen"></i> Edit IVR Rule
                </a>
            </div>
        </div>

        @foreach($rulesByFolder->get('calling', collect()) as $rule)
            @include('admin.automation.partials.rule-card', ['rule' => $rule])
        @endforeach
            </div>
        </div>
    </details>

    @foreach(['facebook', 'website', 'other'] as $folderKey)
        @php
            $folderRules = $rulesByFolder->get($folderKey, collect());
            $folder = $sourceFolders[$folderKey];
            $visibleFolderRules = $folderKey === 'facebook' ? $folderRules->where('is_active', true) : $folderRules;
            $pausedFacebookRules = $folderKey === 'facebook' ? $folderRules->where('is_active', false) : collect();
        @endphp
        @continue($folderRules->isEmpty())

        <details class="automation-folder" open>
            <summary class="automation-folder-summary">
                <div class="automation-folder-title">
                    <span class="automation-folder-icon {{ $folder['tone'] }}"><i class="{{ $folder['icon'] }}"></i></span>
                    <div>
                        <div class="text-sm font-black text-slate-900">{{ $folder['label'] }}</div>
                        <div class="text-xs text-slate-500">{{ $folderKey === 'facebook' ? 'Automation-wise form groups' : 'Source-wise automation rules' }}</div>
                    </div>
                </div>
                <span class="automation-folder-right">
                    <span class="automation-folder-count">{{ $folderRules->count() }}</span>
                    <i class="fas fa-chevron-down automation-folder-chevron"></i>
                </span>
            </summary>
            <div class="automation-folder-body">
                <div class="auto-grid">
                    @foreach($visibleFolderRules as $rule)
                        @include('admin.automation.partials.rule-card', ['rule' => $rule])
                    @endforeach
                </div>
                @if($pausedFacebookRules->isNotEmpty())
                    <details class="mt-4 overflow-hidden rounded-lg border border-slate-200 bg-white">
                        <summary class="flex cursor-pointer items-center justify-between gap-3 px-4 py-3 text-sm font-bold text-slate-700 hover:bg-slate-50">
                            <span><i class="fas fa-box-archive mr-2 text-slate-400"></i>Paused / old automations</span>
                            <span class="automation-folder-count">{{ $pausedFacebookRules->count() }}</span>
                        </summary>
                        <div class="border-t border-slate-200 bg-slate-50 p-4">
                            <div class="auto-grid">
                                @foreach($pausedFacebookRules as $rule)
                                    @include('admin.automation.partials.rule-card', ['rule' => $rule])
                                @endforeach
                            </div>
                        </div>
                    </details>
                @endif
            </div>
        </details>
    @endforeach

</div>

<form id="deleteForm" method="POST" style="display:none;">
    @csrf @method('DELETE')
</form>

@endsection

@push('scripts')
<script>
document.querySelectorAll('.btn-toggle-rule').forEach(btn => {
    btn.addEventListener('click', async function () {
        if (this.disabled) return;

        this.disabled = true;
        try {
            const response = await fetch(this.dataset.url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                }
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error(data.message || Object.values(data.errors || {}).flat()[0] || 'Automation status update failed.');
            }

            const isActive = Boolean(data.is_active);
            const card = this.closest('.rule-card');
            this.dataset.active = isActive ? '1' : '0';
            this.title = isActive ? 'Pause' : 'Resume';
            this.classList.toggle('btn-toggle-pause', isActive);
            this.classList.toggle('btn-toggle-play', !isActive);
            this.innerHTML = `<i class="fas ${isActive ? 'fa-pause' : 'fa-play'}"></i> ${isActive ? 'Pause' : 'Resume'}`;
            card?.classList.toggle('inactive', !isActive);

            const status = card?.querySelector('[data-rule-status]');
            if (status) {
                status.innerHTML = isActive
                    ? '<span class="inline-flex items-center gap-1 text-green-600 font-medium"><span class="w-1.5 h-1.5 rounded-full bg-green-500 inline-block"></span> Active</span>'
                    : '<span class="inline-flex items-center gap-1 text-gray-400 font-medium"><span class="w-1.5 h-1.5 rounded-full bg-gray-400 inline-block"></span> Paused</span>';
            }
        } catch (error) {
            alert(error.message);
        } finally {
            this.disabled = false;
        }
    });
});

document.querySelectorAll('.btn-delete-rule').forEach(btn => {
    btn.addEventListener('click', function () {
        if (!confirm(`"${this.dataset.name}" ko delete karo?`)) return;
        const form = document.getElementById('deleteForm');
        form.action = `/admin/automation/${this.dataset.id}`;
        form.submit();
    });
});

const whatsAppAutomationForm = document.getElementById('admin-whatsapp-automation-form');
if (whatsAppAutomationForm) {
    const whatsAppCard = document.getElementById('whatsappAutomationCard');
    const toggleButton = document.querySelector('.admin-wa-toggle');
    const modeSelect = document.getElementById('admin-wa-auto-assign-mode');
    const singleUserWrapper = document.getElementById('admin-wa-single-user-wrapper');
    const percentageRows = Array.from(document.querySelectorAll('.admin-wa-percentage-row'));

    const syncWhatsAppAutomationMethodUi = () => {
        const currentMode = modeSelect ? modeSelect.value : 'round_robin';

        if (singleUserWrapper) {
            singleUserWrapper.classList.toggle('hidden', currentMode !== 'single_user');
        }

        percentageRows.forEach(row => {
            row.classList.toggle('hidden', currentMode !== 'percentage');
        });
    };

    if (toggleButton && whatsAppCard) {
        toggleButton.addEventListener('click', function () {
            whatsAppAutomationForm.classList.toggle('hidden');
            whatsAppCard.classList.toggle('expanded');
            this.innerHTML = whatsAppAutomationForm.classList.contains('hidden')
                ? '<i class="fas fa-pen"></i> Edit'
                : '<i class="fas fa-times"></i> Close';
        });
    }

    if (modeSelect) {
        modeSelect.addEventListener('change', syncWhatsAppAutomationMethodUi);
        syncWhatsAppAutomationMethodUi();
    }

    whatsAppAutomationForm.addEventListener('submit', function (e) {
        e.preventDefault();

        const formData = new FormData(this);
        formData.set('auto_assign_enabled', this.querySelector('[name="auto_assign_enabled"]').checked ? '1' : '0');
        formData.set('create_calling_task', this.querySelector('[name="create_calling_task"]').checked ? '1' : '0');
        formData.set('notify_assigned_user', this.querySelector('[name="notify_assigned_user"]').checked ? '1' : '0');

        const submitButton = this.querySelector('button[type="submit"]');
        const originalHtml = submitButton.innerHTML;
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';

        fetch('{{ route("integrations.whatsapp.automation") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            },
            body: formData
        })
            .then(async response => {
                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Failed to save WhatsApp automation.');
                }
                alert(data.message || 'WhatsApp automation updated successfully.');
                window.location.reload();
            })
            .catch(error => {
                alert(error.message || 'Failed to save WhatsApp automation.');
            })
            .finally(() => {
                submitButton.disabled = false;
                submitButton.innerHTML = originalHtml;
            });
    });
}
</script>
@endpush

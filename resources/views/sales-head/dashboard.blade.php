@extends('layouts.app')

@section('title', 'Associate Director Dashboard - ' . brand_name())
@section('page-title', 'Associate Director Command Center')
@section('page-subtitle', 'Track your full sales hierarchy, lead movement, target progress, and pending actions from one place.')

@section('header-actions')
    <a href="{{ route('users.index') }}" class="ad-header-btn ad-header-btn-secondary"><i class="fas fa-users"></i> Team</a>
    <a href="{{ route('admin.targets.index') }}" class="ad-header-btn ad-header-btn-secondary"><i class="fas fa-bullseye"></i> Targets</a>
    <a href="{{ route('export.index') }}" class="ad-header-btn ad-header-btn-primary"><i class="fas fa-download"></i> Export</a>
@endsection

@push('styles')
<style>
    .ad-shell{display:flex;flex-direction:column;gap:18px}
    .ad-hero{background:linear-gradient(135deg,#063A1C,#205A44 60%,#2E7D5B);border-radius:24px;padding:28px;color:#fff;box-shadow:0 24px 60px rgba(6,58,28,.18)}
    .ad-hero-grid,.ad-grid-2,.ad-grid-3,.ad-grid-4,.ad-target-grid,.ad-pipeline-grid{display:grid;gap:18px}
    .ad-hero-grid{grid-template-columns:minmax(0,1.45fr) minmax(280px,.95fr)}
    .ad-grid-4{grid-template-columns:repeat(4,minmax(0,1fr))}
    .ad-grid-3{grid-template-columns:repeat(3,minmax(0,1fr))}
    .ad-grid-2{grid-template-columns:repeat(2,minmax(0,1fr))}
    .ad-target-grid{grid-template-columns:repeat(3,minmax(0,1fr))}
    .ad-pipeline-grid{grid-template-columns:repeat(3,minmax(0,1fr))}
    .ad-kicker,.ad-pill,.ad-badge{display:inline-flex;align-items:center;gap:6px;border-radius:999px;font-weight:700}
    .ad-kicker{padding:8px 12px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.18);font-size:12px;letter-spacing:.08em;text-transform:uppercase;margin-bottom:14px}
    .ad-title{font-size:32px;line-height:1.1;font-weight:800;margin-bottom:10px;max-width:12ch}
    .ad-copy{color:rgba(255,255,255,.82);font-size:14px;line-height:1.7;max-width:60ch}
    .ad-hero-quick{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
    .ad-quick-card{background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.14);border-radius:18px;padding:16px}
    .ad-quick-label,.ad-stat-label{font-size:11px;color:#8a978f;text-transform:uppercase;letter-spacing:.08em;font-weight:700}
    .ad-quick-label{color:rgba(255,255,255,.7);margin-bottom:8px}
    .ad-quick-value{font-size:24px;font-weight:800;line-height:1;margin-bottom:6px}
    .ad-quick-copy{font-size:12px;color:rgba(255,255,255,.74);line-height:1.5}
    .ad-header-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:42px;padding:10px 14px;border-radius:12px;text-decoration:none;font-size:13px;font-weight:700}
    .ad-header-btn-primary{color:#fff;background:linear-gradient(135deg,#063A1C,#205A44)}
    .ad-header-btn-secondary{color:#0f5132;background:#fff;border:1px solid rgba(6,58,28,.12)}
    .ad-surface{background:#fff;border:1px solid #E5DED4;border-radius:22px;box-shadow:0 18px 36px rgba(15,23,42,.05);overflow:hidden}
    .ad-surface-header{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding:20px 22px 0}
    .ad-surface-body{padding:20px 22px 22px}
    .ad-surface-title{font-size:18px;font-weight:800;color:#063A1C}
    .ad-surface-copy{font-size:13px;color:#7b8a82;margin-top:4px;line-height:1.6}
    .ad-pill{padding:7px 12px;background:#EFF7F2;color:#205A44;font-size:12px;white-space:nowrap}
    .ad-stat-card{padding:20px}
    .ad-stat-top{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:14px}
    .ad-stat-icon{width:44px;height:44px;border-radius:14px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:17px;background:linear-gradient(135deg,#063A1C,#205A44)}
    .ad-stat-value{font-size:34px;line-height:1;font-weight:800;color:#111827;margin-bottom:8px}
    .ad-stat-copy{font-size:13px;color:#6b7280;line-height:1.6}
    .ad-target-card,.ad-pipeline-card,.ad-list-card,.ad-hierarchy-card{border:1px solid #E8EEEA;border-radius:18px;background:#FCFCFB}
    .ad-target-card{padding:16px}.ad-pipeline-card{padding:14px 16px}.ad-list-card{padding:14px 16px}.ad-hierarchy-card{padding:16px}
    .ad-target-head,.ad-list-row,.ad-hierarchy-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}
    .ad-target-name{font-size:13px;font-weight:800;color:#063A1C;letter-spacing:.05em;text-transform:uppercase}
    .ad-target-progress{font-size:12px;font-weight:800;color:#205A44}
    .ad-progress-track,.ad-pipeline-meter{width:100%;border-radius:999px;overflow:hidden;background:#E7ECE9}
    .ad-progress-track{height:10px;margin-bottom:10px}.ad-pipeline-meter{height:8px}
    .ad-progress-bar,.ad-pipeline-meter>span{display:block;height:100%;border-radius:999px;background:linear-gradient(135deg,#063A1C,#2E7D5B)}
    .ad-target-meta{display:flex;align-items:center;justify-content:space-between;gap:10px;font-size:13px;color:#5f6b65}
    .ad-pipeline-label{font-size:12px;color:#73837b;margin-bottom:8px;text-transform:capitalize}
    .ad-pipeline-value{font-size:24px;font-weight:800;color:#111827;line-height:1;margin-bottom:10px}
    .ad-table-wrap{border:1px solid #EEF2EF;border-radius:18px;overflow:auto}
    .ad-table{width:100%;border-collapse:collapse;min-width:600px}
    .ad-table th,.ad-table td{padding:14px 16px;border-bottom:1px solid #F1F4F2;text-align:left;font-size:13px}
    .ad-table th{background:#FBFCFB;color:#73837b;font-size:11px;text-transform:uppercase;letter-spacing:.08em;font-weight:800}
    .ad-table tbody tr:last-child td{border-bottom:none}
    .ad-link{color:#205A44;text-decoration:none;font-weight:700}.ad-link:hover{text-decoration:underline}
    .ad-rate-good{color:#15803d;font-weight:800}.ad-rate-mid{color:#b45309;font-weight:800}.ad-rate-low{color:#b91c1c;font-weight:800}
    .ad-list{display:grid;gap:12px}.ad-list-title,.ad-hierarchy-name{font-size:14px;font-weight:800;color:#111827}.ad-hierarchy-name{font-size:15px}
    .ad-list-sub,.ad-hierarchy-role{font-size:12px;color:#7a8681;margin-top:4px;line-height:1.6}
    .ad-badge{padding:6px 10px;font-size:11px;white-space:nowrap}.ad-badge-green{background:#DCFCE7;color:#166534}.ad-badge-yellow{background:#FEF3C7;color:#92400E}.ad-badge-blue{background:#DBEAFE;color:#1D4ED8}.ad-badge-slate{background:#E5E7EB;color:#374151}
    .ad-hierarchy-root,.ad-hierarchy-children{display:grid;gap:12px}.ad-hierarchy-children{margin-top:10px;padding-left:14px;border-left:2px solid #E2E8E5}
    .ad-hierarchy-child{display:flex;justify-content:space-between;gap:10px;padding:10px 12px;border-radius:14px;background:#fff;border:1px solid #EEF2EF;font-size:13px;color:#475569}
    .ad-empty{padding:40px 18px;border:1px dashed #D7DFDB;border-radius:16px;text-align:center;color:#7B8A82;background:#FAFBFA}
    @media(max-width:1200px){.ad-grid-4,.ad-grid-3,.ad-grid-2,.ad-target-grid,.ad-pipeline-grid,.ad-hero-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.ad-hero-grid{grid-template-columns:1fr}}
    @media(max-width:767px){.ad-header-btn{width:100%}.ad-hero{padding:22px 18px;border-radius:20px}.ad-title{font-size:28px;max-width:none}.ad-hero-quick,.ad-grid-4,.ad-grid-3,.ad-grid-2,.ad-target-grid,.ad-pipeline-grid{grid-template-columns:1fr}.ad-surface-header,.ad-surface-body{padding-left:16px;padding-right:16px}.ad-stat-card{padding:18px 16px}.ad-stat-value{font-size:28px}.ad-table{min-width:520px}}
</style>
@endpush

@section('content')
<div class="ad-shell">
    @if(auth()->user()?->hasAttendanceRolloutEnabled())
        @include('attendance._widget')
    @endif
    <section class="ad-hero">
        <div class="ad-hero-grid">
            <div>
                <div class="ad-kicker"><i class="fas fa-sitemap"></i> Associate Director Workspace</div>
                <div class="ad-title">Lead and team command center</div>
                <div class="ad-copy">Monitor the full sales chain under you, from managers to executives. Track pipeline movement, target achievement, verifications, and urgent follow-ups without switching across disconnected screens.</div>
            </div>
            <div class="ad-hero-quick">
                <div class="ad-quick-card"><div class="ad-quick-label">Priority</div><div class="ad-quick-value" id="hero-pending-verifications">0</div><div class="ad-quick-copy">Pending verifications waiting for review.</div></div>
                <div class="ad-quick-card"><div class="ad-quick-label">Visits Due</div><div class="ad-quick-value" id="hero-upcoming-visits">0</div><div class="ad-quick-copy">Upcoming site visits across the team.</div></div>
                <div class="ad-quick-card"><div class="ad-quick-label">Follow-ups</div><div class="ad-quick-value" id="hero-followups">0</div><div class="ad-quick-copy">Scheduled follow-ups in the current queue.</div></div>
                <div class="ad-quick-card"><div class="ad-quick-label">Team Size</div><div class="ad-quick-value" id="hero-team-size">0</div><div class="ad-quick-copy">Active people in your full reporting tree.</div></div>
            </div>
        </div>
    </section>

    <div id="ad-loading" class="ad-surface"><div class="ad-surface-body"><div class="ad-empty"><i class="fas fa-spinner fa-spin" style="font-size:24px;margin-bottom:10px;display:block;color:#205A44;"></i>Loading Associate Director dashboard...</div></div></div>
    <div id="ad-dashboard" class="hidden">
        <section class="ad-grid-4">
            <div class="ad-surface ad-stat-card"><div class="ad-stat-top"><div class="ad-stat-label">Active Leads</div><div class="ad-stat-icon"><i class="fas fa-user-friends"></i></div></div><div class="ad-stat-value" id="stat-total-leads">0</div><div class="ad-stat-copy">Live leads currently owned by your full downline.</div></div>
            <div class="ad-surface ad-stat-card"><div class="ad-stat-top"><div class="ad-stat-label">Today's Leads</div><div class="ad-stat-icon"><i class="fas fa-calendar-day"></i></div></div><div class="ad-stat-value" id="stat-today-leads">0</div><div class="ad-stat-copy">Fresh leads that entered the team today.</div></div>
            <div class="ad-surface ad-stat-card"><div class="ad-stat-top"><div class="ad-stat-label">Conversions</div><div class="ad-stat-icon"><i class="fas fa-chart-line"></i></div></div><div class="ad-stat-value" id="stat-conversions">0</div><div class="ad-stat-copy"><span id="stat-conversion-rate">0%</span> conversion rate across the controlled pipeline.</div></div>
            <div class="ad-surface ad-stat-card"><div class="ad-stat-top"><div class="ad-stat-label">Pending Verifications</div><div class="ad-stat-icon"><i class="fas fa-shield-check"></i></div></div><div class="ad-stat-value" id="stat-pending-verifications">0</div><div class="ad-stat-copy">Leads waiting for review or action from the verification flow.</div></div>
        </section>

        <section class="ad-grid-4" style="margin-top:18px;">
            <div class="ad-surface ad-stat-card"><div class="ad-stat-top"><div class="ad-stat-label">Direct Managers</div><div class="ad-stat-icon"><i class="fas fa-user-tie"></i></div></div><div class="ad-stat-value" id="team-direct-managers">0</div><div class="ad-stat-copy">Top-level managers directly reporting to you.</div></div>
            <div class="ad-surface ad-stat-card"><div class="ad-stat-top"><div class="ad-stat-label">Assistant Managers</div><div class="ad-stat-icon"><i class="fas fa-users-gear"></i></div></div><div class="ad-stat-value" id="team-asms">0</div><div class="ad-stat-copy">ASMs coordinating team movement below the manager layer.</div></div>
            <div class="ad-surface ad-stat-card"><div class="ad-stat-top"><div class="ad-stat-label">Executives</div><div class="ad-stat-icon"><i class="fas fa-headset"></i></div></div><div class="ad-stat-value" id="team-executives">0</div><div class="ad-stat-copy">Sales executives and calling-heavy users under your tree.</div></div>
            <div class="ad-surface ad-stat-card"><div class="ad-stat-top"><div class="ad-stat-label">Upcoming Actions</div><div class="ad-stat-icon"><i class="fas fa-bolt"></i></div></div><div class="ad-stat-value" id="team-actions">0</div><div class="ad-stat-copy">Combined upcoming site visits and follow-ups that need coverage.</div></div>
        </section>

        <section class="ad-grid-2" style="margin-top:18px;">
            <div class="ad-surface">
                <div class="ad-surface-header"><div><div class="ad-surface-title">Target Achievement Overview</div><div class="ad-surface-copy">This month’s team progress across meetings, visits, and closers.</div></div><span class="ad-pill"><i class="fas fa-bullseye"></i> <span id="targets-users-count">0</span> users mapped</span></div>
                <div class="ad-surface-body"><div class="ad-target-grid">
                    <div class="ad-target-card"><div class="ad-target-head"><span class="ad-target-name">Meetings</span><span class="ad-target-progress" id="targets-meetings-percent">0%</span></div><div class="ad-progress-track"><div class="ad-progress-bar" id="targets-meetings-bar" style="width:0%"></div></div><div class="ad-target-meta"><span id="targets-meetings-achieved">0 achieved</span><strong id="targets-meetings-target">0 target</strong></div></div>
                    <div class="ad-target-card"><div class="ad-target-head"><span class="ad-target-name">Visits</span><span class="ad-target-progress" id="targets-visits-percent">0%</span></div><div class="ad-progress-track"><div class="ad-progress-bar" id="targets-visits-bar" style="width:0%"></div></div><div class="ad-target-meta"><span id="targets-visits-achieved">0 achieved</span><strong id="targets-visits-target">0 target</strong></div></div>
                    <div class="ad-target-card"><div class="ad-target-head"><span class="ad-target-name">Closers</span><span class="ad-target-progress" id="targets-closers-percent">0%</span></div><div class="ad-progress-track"><div class="ad-progress-bar" id="targets-closers-bar" style="width:0%"></div></div><div class="ad-target-meta"><span id="targets-closers-achieved">0 achieved</span><strong id="targets-closers-target">0 target</strong></div></div>
                </div></div>
            </div>
            <div class="ad-surface">
                <div class="ad-surface-header"><div><div class="ad-surface-title">Lead Pipeline</div><div class="ad-surface-copy">Team lead movement across each core status bucket.</div></div><a href="{{ route('leads.index') }}" class="ad-link">Open leads</a></div>
                <div class="ad-surface-body"><div class="ad-pipeline-grid" id="pipeline-grid"></div></div>
            </div>
        </section>

        <section class="ad-grid-2" style="margin-top:18px;">
            <div class="ad-surface"><div class="ad-surface-header"><div><div class="ad-surface-title">Manager Performance</div><div class="ad-surface-copy">Top-level hierarchy conversion output and team ownership.</div></div><a href="{{ route('users.index') }}" class="ad-link">Open team</a></div><div class="ad-surface-body"><div class="ad-table-wrap"><table class="ad-table"><thead><tr><th>Manager</th><th>Team Size</th><th>Leads</th><th>Converted</th><th>Rate</th></tr></thead><tbody id="managers-performance-body"></tbody></table></div></div></div>
            <div class="ad-surface"><div class="ad-surface-header"><div><div class="ad-surface-title">ASM Performance</div><div class="ad-surface-copy">Mid-layer owners coordinating lead flow and conversion quality.</div></div><span class="ad-pill"><i class="fas fa-layer-group"></i> Downline health</span></div><div class="ad-surface-body"><div class="ad-table-wrap"><table class="ad-table"><thead><tr><th>ASM</th><th>Manager</th><th>Team Size</th><th>Leads</th><th>Rate</th></tr></thead><tbody id="asm-performance-body"></tbody></table></div></div></div>
        </section>

        <section class="ad-grid-2" style="margin-top:18px;">
            <div class="ad-surface"><div class="ad-surface-header"><div><div class="ad-surface-title">Sales Executive Performance</div><div class="ad-surface-copy">Conversion and lead ownership output for the executive layer.</div></div><a href="{{ route('users.index') }}" class="ad-link">See all users</a></div><div class="ad-surface-body"><div class="ad-table-wrap"><table class="ad-table"><thead><tr><th>Executive</th><th>Manager</th><th>Leads</th><th>Converted</th><th>Rate</th></tr></thead><tbody id="executives-performance-body"></tbody></table></div></div></div>
            <div class="ad-surface"><div class="ad-surface-header"><div><div class="ad-surface-title">Calling and Qualification Layer</div><div class="ad-surface-copy">Qualified-lead ratio for calling-heavy users in the full tree.</div></div><a href="{{ route('calls.index') }}" class="ad-link">Team calls</a></div><div class="ad-surface-body"><div class="ad-table-wrap"><table class="ad-table"><thead><tr><th>User</th><th>Manager</th><th>Leads</th><th>Qualified</th><th>Rate</th></tr></thead><tbody id="telecallers-performance-body"></tbody></table></div></div></div>
        </section>

        <section class="ad-grid-3" style="margin-top:18px;">
            <div class="ad-surface"><div class="ad-surface-header"><div><div class="ad-surface-title">Pending Verifications</div><div class="ad-surface-copy">Items currently waiting in the verification queue.</div></div><a href="{{ route('crm.verifications') }}" class="ad-link">Open verifications</a></div><div class="ad-surface-body"><div class="ad-list" id="pending-verifications-list"></div></div></div>
            <div class="ad-surface"><div class="ad-surface-header"><div><div class="ad-surface-title">Recent Leads</div><div class="ad-surface-copy">Newest leads entering your controlled pipeline.</div></div><a href="{{ route('leads.index') }}" class="ad-link">All leads</a></div><div class="ad-surface-body"><div class="ad-list" id="recent-leads-list"></div></div></div>
            <div class="ad-surface"><div class="ad-surface-header"><div><div class="ad-surface-title">Recent Conversions</div><div class="ad-surface-copy">Recently closed wins coming from the sales hierarchy.</div></div><span class="ad-pill"><i class="fas fa-trophy"></i> Win feed</span></div><div class="ad-surface-body"><div class="ad-list" id="recent-conversions-list"></div></div></div>
        </section>

        <section class="ad-grid-2" style="margin-top:18px;">
            <div class="ad-surface"><div class="ad-surface-header"><div><div class="ad-surface-title">Upcoming Follow-ups</div><div class="ad-surface-copy">Scheduled callbacks and follow-ups that need bandwidth coverage.</div></div><span class="ad-pill"><i class="fas fa-clock"></i> Queue watch</span></div><div class="ad-surface-body"><div class="ad-list" id="followups-list"></div></div></div>
            <div class="ad-surface"><div class="ad-surface-header"><div><div class="ad-surface-title">Team Hierarchy Snapshot</div><div class="ad-surface-copy">Quick view of the command chain under the Associate Director.</div></div><a href="{{ route('users.index') }}" class="ad-link">Users / Team</a></div><div class="ad-surface-body"><div id="hierarchy-root" class="ad-hierarchy-root"></div></div></div>
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script>
const AD_DASHBOARD_ENDPOINT = @json(route('sales-head.dashboard.data'));
const adEsc = (v) => String(v ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
const adFmt = (v) => { if (!v) return 'Not scheduled'; const d = new Date(v); return Number.isNaN(d.getTime()) ? 'Not scheduled' : d.toLocaleString('en-IN',{day:'2-digit',month:'short',hour:'2-digit',minute:'2-digit'}); };
const adRate = (n) => Number(n || 0) >= 20 ? 'ad-rate-good' : Number(n || 0) >= 10 ? 'ad-rate-mid' : 'ad-rate-low';
function adSetProgress(prefix, achieved, target, percentage) { document.getElementById(`${prefix}-achieved`).textContent = `${achieved || 0} achieved`; document.getElementById(`${prefix}-target`).textContent = `${target || 0} target`; document.getElementById(`${prefix}-percent`).textContent = `${Math.round(percentage || 0)}%`; document.getElementById(`${prefix}-bar`).style.width = `${Math.min(100, percentage || 0)}%`; }
function adRows(rows, cols, empty) { return rows && rows.length ? rows.join('') : `<tr><td colspan="${cols}" style="text-align:center;color:#7b8a82;">${adEsc(empty)}</td></tr>`; }
function adList(id, items, render, empty) { const el = document.getElementById(id); el.innerHTML = items && items.length ? items.map(render).join('') : `<div class="ad-empty">${adEsc(empty)}</div>`; }
function adHierarchy(node) { const children = Array.isArray(node.children) ? node.children : []; return `<div class="ad-hierarchy-card"><div class="ad-hierarchy-head"><div><div class="ad-hierarchy-name">${adEsc(node.name)}</div><div class="ad-hierarchy-role">${adEsc(node.role)}</div></div><span class="ad-badge ad-badge-slate">${children.length} direct</span></div>${children.length ? `<div class="ad-hierarchy-children">${children.map(child => `<div class="ad-hierarchy-child"><div><strong>${adEsc(child.name)}</strong><div style="margin-top:4px;">${adEsc(child.role)}</div></div><span class="ad-badge ad-badge-blue">${Array.isArray(child.children) ? child.children.length : 0} reports</span></div>`).join('')}</div>` : ''}</div>`; }

async function loadAssociateDirectorDashboard() {
    try {
        const response = await fetch(AD_DASHBOARD_ENDPOINT, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' }, credentials: 'same-origin' });
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || data.error || 'Failed to load Associate Director dashboard.');
        renderAssociateDirectorDashboard(data);
    } catch (error) {
        document.getElementById('ad-loading').innerHTML = `<div class="ad-surface-body"><div class="ad-empty"><i class="fas fa-triangle-exclamation" style="font-size:24px;margin-bottom:10px;display:block;color:#b91c1c;"></i>${adEsc(error.message || 'Dashboard load failed.')}</div></div>`;
    }
}

function renderAssociateDirectorDashboard(data) {
    const stats = data.stats || {}, targets = data.target_overview || {}, pipeline = data.lead_pipeline || {};
    document.getElementById('ad-loading').classList.add('hidden');
    document.getElementById('ad-dashboard').classList.remove('hidden');
    document.getElementById('hero-pending-verifications').textContent = stats.pending_verifications || 0;
    document.getElementById('hero-upcoming-visits').textContent = stats.upcoming_site_visits || 0;
    document.getElementById('hero-followups').textContent = stats.pending_followups || 0;
    document.getElementById('hero-team-size').textContent = stats.total_team_members || 0;
    document.getElementById('stat-total-leads').textContent = stats.total_leads || 0;
    document.getElementById('stat-today-leads').textContent = stats.today_leads || 0;
    document.getElementById('stat-conversions').textContent = stats.closed_won || 0;
    document.getElementById('stat-conversion-rate').textContent = `${stats.conversion_rate || 0}%`;
    document.getElementById('stat-pending-verifications').textContent = stats.pending_verifications || 0;
    document.getElementById('team-direct-managers').textContent = stats.direct_managers || 0;
    document.getElementById('team-asms').textContent = stats.active_asms || 0;
    document.getElementById('team-executives').textContent = stats.active_telecallers || stats.active_sales_executives || 0;
    document.getElementById('team-actions').textContent = (stats.upcoming_site_visits || 0) + (stats.pending_followups || 0);
    document.getElementById('targets-users-count').textContent = targets.users_with_targets || 0;
    adSetProgress('targets-meetings', targets.meetings_achieved, targets.meetings_target, targets.meetings_percentage);
    adSetProgress('targets-visits', targets.visits_achieved, targets.visits_target, targets.visits_percentage);
    adSetProgress('targets-closers', targets.closers_achieved, targets.closers_target, targets.closers_percentage);

    const pipelineTotal = Object.values(pipeline).reduce((sum, value) => sum + Number(value || 0), 0) || 1;
    document.getElementById('pipeline-grid').innerHTML = Object.entries(pipeline).map(([status, count]) => `<div class="ad-pipeline-card"><div class="ad-pipeline-label">${adEsc(status.replaceAll('_', ' '))}</div><div class="ad-pipeline-value">${count || 0}</div><div class="ad-pipeline-meter"><span style="width:${Math.round((Number(count || 0) / pipelineTotal) * 100)}%"></span></div></div>`).join('');

    document.getElementById('managers-performance-body').innerHTML = adRows((data.managers_performance || []).map(row => `<tr><td><a href="{{ route('users.index') }}" class="ad-link">${adEsc(row.name)}</a></td><td>${row.team_size || 0}</td><td>${row.total_leads || 0}</td><td>${row.leads_converted || 0}</td><td class="${adRate(row.conversion_rate)}">${row.conversion_rate || 0}%</td></tr>`), 5, 'No manager data available.');
    document.getElementById('asm-performance-body').innerHTML = adRows((data.asm_performance || []).map(row => `<tr><td><strong>${adEsc(row.name)}</strong></td><td>${adEsc(row.manager_name || 'N/A')}</td><td>${row.team_size || 0}</td><td>${row.total_leads || 0}</td><td class="${adRate(row.conversion_rate)}">${row.conversion_rate || 0}%</td></tr>`), 5, 'No ASM data available.');
    document.getElementById('executives-performance-body').innerHTML = adRows((data.executives_performance || []).map(row => `<tr><td><strong>${adEsc(row.name)}</strong></td><td>${adEsc(row.manager_name || 'N/A')}</td><td>${row.total_leads || 0}</td><td>${row.leads_converted || 0}</td><td class="${adRate(row.conversion_rate)}">${row.conversion_rate || 0}%</td></tr>`), 5, 'No executive data available.');
    document.getElementById('telecallers-performance-body').innerHTML = adRows((data.telecallers_performance || []).map(row => `<tr><td><strong>${adEsc(row.name)}</strong></td><td>${adEsc(row.manager_name || 'N/A')}</td><td>${row.total_leads || 0}</td><td>${row.leads_qualified || 0}</td><td class="${adRate(row.qualification_rate)}">${row.qualification_rate || 0}%</td></tr>`), 5, 'No calling-layer data available.');

    adList('pending-verifications-list', data.pending_verifications || [], (lead) => `<div class="ad-list-card"><div class="ad-list-row"><div><div class="ad-list-title">${adEsc(lead.name)}</div><div class="ad-list-sub">Assigned to ${adEsc(lead.current_assigned_to?.name || 'Unassigned')}</div></div><span class="ad-badge ad-badge-yellow">Review</span></div><div class="ad-list-sub">Requested by ${adEsc(lead.verification_requested_by?.name || 'System')} • ${adEsc(adFmt(lead.verification_requested_at))}</div></div>`, 'No pending verifications.');
    adList('recent-leads-list', data.recent_leads || [], (lead) => `<div class="ad-list-card"><div class="ad-list-row"><div><div class="ad-list-title">${adEsc(lead.name)}</div><div class="ad-list-sub">${adEsc(lead.phone || 'No phone')} • ${adEsc(lead.source || 'Unknown source')}</div></div><span class="ad-badge ad-badge-blue">${adEsc(lead.status || 'new')}</span></div><div class="ad-list-sub">Owner: ${adEsc(lead.assigned_to?.name || 'Unassigned')}</div></div>`, 'No recent leads.');
    adList('recent-conversions-list', data.recent_conversions || [], (lead) => `<div class="ad-list-card"><div class="ad-list-row"><div><div class="ad-list-title">${adEsc(lead.name)}</div><div class="ad-list-sub">${adEsc(lead.phone || 'No phone')}</div></div><span class="ad-badge ad-badge-green">Closed</span></div><div class="ad-list-sub">Updated ${adEsc(adFmt(lead.updated_at))}</div></div>`, 'No recent conversions.');
    adList('followups-list', data.upcoming_followups || [], (followup) => `<div class="ad-list-card"><div class="ad-list-row"><div><div class="ad-list-title">${adEsc(followup.lead_name || 'Lead')}</div><div class="ad-list-sub">${adEsc(followup.notes || 'Scheduled follow-up')}</div></div><span class="ad-badge ad-badge-slate">${adEsc(adFmt(followup.scheduled_at))}</span></div><div class="ad-list-sub">Created by ${adEsc(followup.creator?.name || 'System')}</div></div>`, 'No upcoming follow-ups.');

    document.getElementById('hierarchy-root').innerHTML = data.team_hierarchy ? adHierarchy(data.team_hierarchy) : `<div class="ad-empty">No hierarchy data available.</div>`;
}

document.addEventListener('DOMContentLoaded', loadAssociateDirectorDashboard);
</script>
@endpush

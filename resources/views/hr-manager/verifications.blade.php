@extends('layouts.app')

@section('title', 'HR Verifications')
@section('page-title', 'HR Verifications')

@section('content')
<style>
    .hrv-shell { max-width: none; }
    .hrv-hero { border: 1px solid #e2e8f0; border-radius: 22px; background: linear-gradient(135deg, #ffffff 0%, #f8fafc 55%, #ecfdf5 100%); box-shadow: 0 14px 34px rgba(15, 23, 42, .06); padding: 22px; }
    .hrv-stat { border: 1px solid #e2e8f0; border-radius: 20px; background: #fff; padding: 18px; box-shadow: 0 10px 24px rgba(15, 23, 42, .04); }
    .hrv-list-card { overflow: hidden; border-radius: 22px; }
    .hrv-item { padding: 18px 20px; transition: background .15s ease, transform .15s ease; }
    .hrv-item:hover { background: #f8fafc; }
    .hrv-item-title { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
    .hrv-pill { display: inline-flex; align-items: center; border-radius: 999px; padding: 5px 10px; font-size: 12px; font-weight: 900; }
    .hrv-meta-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; margin-top: 14px; }
    .hrv-meta { border: 1px solid #edf2f7; border-radius: 14px; background: #fff; padding: 10px 12px; min-width: 0; }
    .hrv-meta-label { font-size: 11px; font-weight: 900; letter-spacing: .05em; color: #64748b; text-transform: uppercase; }
    .hrv-meta-value { margin-top: 3px; color: #0f172a; font-size: 13px; font-weight: 800; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .hrv-actions { display: grid; grid-template-columns: 1fr; gap: 8px; min-width: 132px; }
    .hrv-btn { display: inline-flex; align-items: center; justify-content: center; min-height: 40px; border-radius: 13px; padding: 9px 13px; font-size: 13px; font-weight: 900; }
    .hrv-btn-view { border: 1px solid #dbe5ef; background: #fff; color: #334155; }
    .hrv-btn-view:hover { background: #f8fafc; }
    .hrv-btn-verify { background: #047857; color: #fff; }
    .hrv-btn-verify:hover { background: #065f46; }
    .hrv-btn-reject { border: 1px solid #fecaca; background: #fff; color: #dc2626; }
    .hrv-btn-reject:hover { background: #fff1f2; }
    .hrv-detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 12px; }
    .hrv-detail-box { border: 1px solid #e2e8f0; border-radius: 14px; background: #fff; padding: 12px; }
    .hrv-modal { position: fixed; inset: 0; z-index: 9999; display: none; align-items: center; justify-content: center; padding: 20px; background: rgba(15, 23, 42, .55); }
    .hrv-modal.open { display: flex; }
    .hrv-modal-panel { width: min(980px, 96vw); max-height: 88vh; overflow: auto; border-radius: 22px; background: #fff; box-shadow: 0 30px 80px rgba(15, 23, 42, .35); }
    .hrv-proof-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px; }
    .hrv-proof-link { display: block; overflow: hidden; border: 1px solid #e2e8f0; border-radius: 16px; background: #f8fafc; text-decoration: none; }
    .hrv-proof-link img { width: 100%; height: 110px; object-fit: cover; display: block; }
    .hrv-tabs { display: inline-flex; gap: 4px; padding: 4px; border: 1px solid #dbe5ef; border-radius: 14px; background: #f8fafc; }
    .hrv-tab { display: inline-flex; align-items: center; gap: 8px; min-height: 38px; padding: 8px 14px; border-radius: 10px; color: #64748b; font-size: 13px; font-weight: 900; }
    .hrv-tab.active { background: #fff; color: #065f46; box-shadow: 0 1px 4px rgba(15, 23, 42, .12); }
    .hrv-tab-count { display: inline-flex; min-width: 22px; height: 22px; align-items: center; justify-content: center; border-radius: 999px; background: #e2e8f0; padding: 0 7px; font-size: 11px; }
    .hrv-tab.active .hrv-tab-count { background: #d1fae5; color: #065f46; }
    @media (max-width: 1100px) {
        .hrv-meta-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .hrv-actions { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }
    @media (max-width: 640px) {
        .hrv-meta-grid { grid-template-columns: 1fr; }
        .hrv-actions { grid-template-columns: 1fr; }
    }
</style>

<div class="hrv-shell space-y-5">
    <div class="hrv-hero flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Verification Desk</h1>
            <p class="mt-1 text-sm text-slate-500">HR ko routed meetings aur site visits yahan verify/reject karne ke liye milenge.</p>
        </div>
        <button type="button" onclick="loadHrVerifications()" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 shadow-sm hover:bg-slate-50">
            <i class="fas fa-rotate mr-2 text-emerald-700"></i> Refresh
        </button>
    </div>

    <div class="grid gap-4 md:grid-cols-4">
        <div class="hrv-stat">
            <div class="text-xs font-bold uppercase tracking-wide text-slate-500">Pending items</div>
            <div id="hrVerificationTotal" class="mt-2 text-3xl font-black text-slate-900">0</div>
        </div>
        <div class="hrv-stat">
            <div class="text-xs font-bold uppercase tracking-wide text-slate-500">Meetings</div>
            <div id="hrMeetingTotal" class="mt-2 text-3xl font-black text-indigo-700">0</div>
        </div>
        <div class="hrv-stat">
            <div class="text-xs font-bold uppercase tracking-wide text-slate-500">Site visits</div>
            <div id="hrVisitTotal" class="mt-2 text-3xl font-black text-emerald-700">0</div>
        </div>
        <div class="hrv-stat">
            <div class="text-xs font-bold uppercase tracking-wide text-slate-500">Verified by you</div>
            <div id="hrVerifiedTotal" class="mt-2 text-3xl font-black text-emerald-700">0</div>
        </div>
    </div>

    <div id="hrVerificationAlert" class="hidden rounded-xl px-4 py-3 text-sm font-semibold"></div>

    <div class="hrv-tabs" role="tablist" aria-label="Verification status">
        <button id="hrPendingTabButton" type="button" class="hrv-tab active" onclick="switchHrVerificationTab('pending')">
            <i class="fas fa-clock"></i> Pending
            <span id="hrPendingTabCount" class="hrv-tab-count">0</span>
        </button>
        <button id="hrVerifiedTabButton" type="button" class="hrv-tab" onclick="switchHrVerificationTab('verified')">
            <i class="fas fa-circle-check"></i> Verified by you
            <span id="hrVerifiedTabCount" class="hrv-tab-count">0</span>
        </button>
    </div>

    <div id="hrPendingPanel" class="space-y-5">
    <div class="hrv-list-card rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-5 py-4">
            <h2 class="text-lg font-bold text-slate-900">Pending Meeting / Site Visit Verification</h2>
            <p class="text-sm text-slate-500">Approve or reject only if the item is assigned to you by Verification Routing.</p>
        </div>
        <div id="hrVerificationList" class="divide-y divide-slate-100"></div>
        <div id="hrVerificationEmpty" class="hidden p-10 text-center">
            <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-500">
                <i class="fas fa-check"></i>
            </div>
            <div class="font-bold text-slate-900">No pending routed verifications</div>
            <div class="text-sm text-slate-500">New routed meeting/site visit items will appear here.</div>
        </div>
    </div>

    <div class="hrv-list-card rounded-2xl border border-amber-200 bg-white shadow-sm">
        <div class="border-b border-amber-100 bg-amber-50/60 px-5 py-4">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-lg font-bold text-slate-900">Not ready for verification</h2>
                <span id="hrNotReadyTotal" class="rounded-full bg-amber-100 px-3 py-1 text-xs font-black text-amber-800">0</span>
            </div>
            <p class="text-sm text-slate-600">Ye routed items hain, lekin abhi complete/proof-ready nahi hain. Complete hone ke baad verify/reject active hoga.</p>
        </div>
        <div id="hrNotReadyList" class="divide-y divide-slate-100"></div>
        <div id="hrNotReadyEmpty" class="hidden p-8 text-center text-sm font-semibold text-slate-500">No not-ready routed items.</div>
    </div>
    </div>

    <div id="hrVerifiedPanel" class="hidden">
        <div class="hrv-list-card rounded-2xl border border-emerald-200 bg-white shadow-sm">
            <div class="border-b border-emerald-100 bg-emerald-50/60 px-5 py-4">
                <h2 class="text-lg font-bold text-slate-900">Verified Meetings / Site Visits</h2>
                <p class="text-sm text-slate-600">Sirf wahi records jo aapne verify kiye hain. Latest verified record sabse upar hai.</p>
            </div>
            <div id="hrVerifiedList" class="divide-y divide-slate-100"></div>
            <div id="hrVerifiedEmpty" class="hidden p-10 text-center">
                <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-700">
                    <i class="fas fa-check"></i>
                </div>
                <div class="font-bold text-slate-900">No verified records yet</div>
                <div class="text-sm text-slate-500">Aapke verified meeting/site visit records yahan dikhenge.</div>
            </div>
        </div>
    </div>
</div>

<div id="hrVerificationModal" class="hrv-modal" onclick="closeHrDetailModal(event)">
    <div class="hrv-modal-panel" onclick="event.stopPropagation()">
        <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-5">
            <div>
                <div id="hrModalBadge" class="mb-2 inline-flex rounded-full px-3 py-1 text-xs font-black"></div>
                <h3 id="hrModalTitle" class="text-xl font-black text-slate-900"></h3>
                <p id="hrModalSubtitle" class="mt-1 text-sm text-slate-500"></p>
            </div>
            <button type="button" onclick="closeHrDetailModal()" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-bold text-slate-600 hover:bg-slate-50">Close</button>
        </div>
        <div class="space-y-5 p-6">
            <div id="hrModalDetails" class="hrv-detail-grid"></div>
            <div>
                <h4 class="mb-2 text-sm font-black uppercase tracking-wide text-slate-500">Notes</h4>
                <div id="hrModalNotes" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700"></div>
            </div>
            <div>
                <h4 class="mb-2 text-sm font-black uppercase tracking-wide text-slate-500">Proof / Photos</h4>
                <div id="hrModalProofs" class="hrv-proof-grid"></div>
            </div>
        </div>
    </div>
</div>

<script>
const HR_API_TOKEN = @json($api_token);
let HR_VERIFICATION_ITEMS = [];

function hrHeaders() {
    return {
        'Authorization': `Bearer ${HR_API_TOKEN}`,
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
    };
}

function showHrAlert(message, type = 'success') {
    const alert = document.getElementById('hrVerificationAlert');
    alert.className = `rounded-xl px-4 py-3 text-sm font-semibold ${type === 'success' ? 'border border-emerald-200 bg-emerald-50 text-emerald-800' : 'border border-red-200 bg-red-50 text-red-700'}`;
    alert.textContent = message;
    alert.classList.remove('hidden');
    setTimeout(() => alert.classList.add('hidden'), 4000);
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
}

function itemCard(item, notReady = false, verified = false) {
    const isMeeting = item.type === 'meeting';
    const badgeClass = isMeeting ? 'bg-indigo-50 text-indigo-700' : 'bg-emerald-50 text-emerald-700';
    const actionTime = verified ? item.verified_at : (item.completed_at || item.scheduled_at || item.date_of_visit);
    const actionLabel = verified ? 'Verified at' : (item.completed_at ? 'Completed' : 'Scheduled');
    const actions = verified ? `
        <button onclick="openHrDetailModal('${item.type}', ${item.id})" class="hrv-btn hrv-btn-view"><i class="fas fa-eye mr-2"></i> View</button>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-center text-xs font-black text-emerald-700"><i class="fas fa-check mr-1"></i> Verified</div>
    ` : notReady ? `
        <button onclick="openHrDetailModal('${item.type}', ${item.id})" class="hrv-btn hrv-btn-view"><i class="fas fa-eye mr-2"></i> View</button>
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-black text-amber-700">Complete/proof pending</div>
    ` : `
        <button onclick="openHrDetailModal('${item.type}', ${item.id})" class="hrv-btn hrv-btn-view"><i class="fas fa-eye mr-2"></i> View</button>
        <button onclick="verifyHrItem('${item.type}', ${item.id})" class="hrv-btn hrv-btn-verify"><i class="fas fa-check mr-2"></i> Verify</button>
        <button onclick="rejectHrItem('${item.type}', ${item.id})" class="hrv-btn hrv-btn-reject"><i class="fas fa-xmark mr-2"></i> Reject</button>
    `;
    return `
        <div class="hrv-item">
            <div class="grid gap-4 xl:grid-cols-[1fr_150px] xl:items-center">
                <div class="min-w-0">
                    <div class="hrv-item-title">
                        <span class="hrv-pill ${badgeClass}">${isMeeting ? 'Meeting' : 'Site Visit'}</span>
                        ${verified ? '<span class="hrv-pill bg-emerald-50 text-emerald-700">Verified by you</span>' : ''}
                        ${notReady ? '<span class="hrv-pill bg-amber-50 text-amber-700">Not ready</span>' : ''}
                        <div class="text-lg font-black text-slate-900">${escapeHtml(item.customer_name || item.lead?.name || 'Unnamed lead')}</div>
                        <div class="text-sm font-semibold text-slate-500">${escapeHtml(item.phone || item.lead?.phone || '')}</div>
                    </div>
                    <div class="hrv-meta-grid">
                        <div class="hrv-meta"><div class="hrv-meta-label">Creator</div><div class="hrv-meta-value">${escapeHtml(item.creator?.name || 'N/A')}</div></div>
                        <div class="hrv-meta"><div class="hrv-meta-label">Assigned</div><div class="hrv-meta-value">${escapeHtml(item.assignedTo?.name || 'N/A')}</div></div>
                        <div class="hrv-meta"><div class="hrv-meta-label">Project</div><div class="hrv-meta-value" title="${escapeHtml(item.project || item.property_name || 'N/A')}">${escapeHtml(item.project || item.property_name || 'N/A')}</div></div>
                        <div class="hrv-meta"><div class="hrv-meta-label">${actionLabel}</div><div class="hrv-meta-value">${actionTime ? new Date(actionTime).toLocaleString() : 'N/A'}</div></div>
                    </div>
                    ${notReady ? `<div class="mt-3 rounded-xl border border-amber-100 bg-amber-50 px-3 py-2 text-xs font-bold text-amber-800">${escapeHtml(item.ready_note || 'Complete/proof upload ke baad verification allow hoga.')}</div>` : ''}
                </div>
                <div class="hrv-actions">
                    ${actions}
                </div>
            </div>
        </div>
    `;
}

async function loadHrVerifications() {
    const list = document.getElementById('hrVerificationList');
    const empty = document.getElementById('hrVerificationEmpty');
    const notReadyList = document.getElementById('hrNotReadyList');
    const notReadyEmpty = document.getElementById('hrNotReadyEmpty');
    const verifiedList = document.getElementById('hrVerifiedList');
    const verifiedEmpty = document.getElementById('hrVerifiedEmpty');
    list.innerHTML = '<div class="p-8 text-center text-sm font-semibold text-slate-500">Loading...</div>';
    notReadyList.innerHTML = '<div class="p-8 text-center text-sm font-semibold text-slate-500">Loading...</div>';
    verifiedList.innerHTML = '<div class="p-8 text-center text-sm font-semibold text-slate-500">Loading...</div>';
    empty.classList.add('hidden');
    notReadyEmpty.classList.add('hidden');
    verifiedEmpty.classList.add('hidden');

    try {
        const [pendingResponse, verifiedResponse] = await Promise.all([
            fetch('/api/hr-manager/verifications/pending', { headers: hrHeaders() }),
            fetch('/api/hr-manager/verifications/verified', { headers: hrHeaders() }),
        ]);
        const [data, verifiedData] = await Promise.all([pendingResponse.json(), verifiedResponse.json()]);
        if (!pendingResponse.ok) throw new Error(data.message || 'Failed to load pending verifications');
        if (!verifiedResponse.ok) throw new Error(verifiedData.message || 'Failed to load verified records');

        const items = data.data || [];
        const notReadyItems = data.not_ready || [];
        const verifiedItems = verifiedData.data || [];
        HR_VERIFICATION_ITEMS = items.concat(notReadyItems, verifiedItems);
        document.getElementById('hrVerificationTotal').textContent = items.length;
        document.getElementById('hrMeetingTotal').textContent = items.filter(item => item.type === 'meeting').length;
        document.getElementById('hrVisitTotal').textContent = items.filter(item => item.type === 'site_visit').length;
        document.getElementById('hrNotReadyTotal').textContent = notReadyItems.length;
        document.getElementById('hrVerifiedTotal').textContent = verifiedItems.length;
        document.getElementById('hrPendingTabCount').textContent = items.length;
        document.getElementById('hrVerifiedTabCount').textContent = verifiedItems.length;

        list.innerHTML = items.map(itemCard).join('');
        empty.classList.toggle('hidden', items.length > 0);
        notReadyList.innerHTML = notReadyItems.map(item => itemCard(item, true)).join('');
        notReadyEmpty.classList.toggle('hidden', notReadyItems.length > 0);
        verifiedList.innerHTML = verifiedItems.map(item => itemCard(item, false, true)).join('');
        verifiedEmpty.classList.toggle('hidden', verifiedItems.length > 0);
    } catch (error) {
        list.innerHTML = '';
        notReadyList.innerHTML = '';
        verifiedList.innerHTML = '';
        showHrAlert(error.message, 'error');
    }
}

function switchHrVerificationTab(tab) {
    const showVerified = tab === 'verified';
    document.getElementById('hrPendingPanel').classList.toggle('hidden', showVerified);
    document.getElementById('hrVerifiedPanel').classList.toggle('hidden', !showVerified);
    document.getElementById('hrPendingTabButton').classList.toggle('active', !showVerified);
    document.getElementById('hrVerifiedTabButton').classList.toggle('active', showVerified);
}

function openHrDetailModal(type, id) {
    const item = HR_VERIFICATION_ITEMS.find(row => row.type === type && Number(row.id) === Number(id));
    if (!item) return;

    const isMeeting = item.type === 'meeting';
    const badge = document.getElementById('hrModalBadge');
    badge.className = `mb-2 inline-flex rounded-full px-3 py-1 text-xs font-black ${isMeeting ? 'bg-indigo-50 text-indigo-700' : 'bg-emerald-50 text-emerald-700'}`;
    badge.textContent = isMeeting ? 'Meeting' : 'Site Visit';
    document.getElementById('hrModalTitle').textContent = item.customer_name || item.lead?.name || 'Unnamed lead';
    document.getElementById('hrModalSubtitle').textContent = item.phone || item.lead?.phone || '';

    const details = [
        ['Creator', item.creator?.name],
        ['Assigned', item.assignedTo?.name],
        [item.completed_at ? 'Completed' : 'Scheduled', (item.completed_at || item.scheduled_at || item.date_of_visit) ? new Date(item.completed_at || item.scheduled_at || item.date_of_visit).toLocaleString() : null],
        ['Status', item.status],
        ['Verification', item.verification_status],
        ['Verified by', item.verifiedBy?.name],
        ['Verified at', item.verified_at ? new Date(item.verified_at).toLocaleString() : null],
        ['Project', item.project],
        ['Property', item.property_name],
        ['Address', item.property_address],
        ['Budget', item.budget_range],
        ['Property Type', item.property_type],
    ].filter(row => row[1]);

    document.getElementById('hrModalDetails').innerHTML = details.map(([label, value]) => `
        <div class="hrv-detail-box">
            <div class="text-xs font-black uppercase tracking-wide text-slate-500">${escapeHtml(label)}</div>
            <div class="mt-1 text-sm font-bold text-slate-900">${escapeHtml(value)}</div>
        </div>
    `).join('');

    document.getElementById('hrModalNotes').textContent = item.notes || 'No notes added.';
    const proofs = item.proof_photos || [];
    document.getElementById('hrModalProofs').innerHTML = proofs.length
        ? proofs.map((url, index) => `
            <a href="${escapeHtml(url)}" target="_blank" class="hrv-proof-link">
                <img src="${escapeHtml(url)}" alt="Proof ${index + 1}">
                <div class="px-3 py-2 text-xs font-bold text-slate-700">Open proof ${index + 1}</div>
            </a>
        `).join('')
        : '<div class="rounded-2xl border border-dashed border-slate-200 p-5 text-sm font-semibold text-slate-500">No proof photos available.</div>';

    document.getElementById('hrVerificationModal').classList.add('open');
}

function closeHrDetailModal(event) {
    if (event && event.target.id !== 'hrVerificationModal') return;
    document.getElementById('hrVerificationModal').classList.remove('open');
}

async function verifyHrItem(type, id) {
    const notes = prompt('Verification notes (optional):') || '';
    await submitHrAction(type, id, 'verify', { notes });
}

async function rejectHrItem(type, id) {
    const reason = prompt('Reject reason:');
    if (!reason) return;
    await submitHrAction(type, id, 'reject', { reason });
}

async function submitHrAction(type, id, action, payload) {
    const base = type === 'meeting' ? 'meetings' : 'site-visits';
    try {
        const response = await fetch(`/api/hr-manager/${base}/${id}/${action}`, {
            method: 'POST',
            headers: hrHeaders(),
            body: JSON.stringify(payload),
        });
        const data = await response.json();
        if (!response.ok || data.success === false) throw new Error(data.message || 'Action failed');
        showHrAlert(data.message || 'Saved successfully');
        loadHrVerifications();
    } catch (error) {
        showHrAlert(error.message, 'error');
    }
}

document.addEventListener('DOMContentLoaded', loadHrVerifications);
</script>
@endsection

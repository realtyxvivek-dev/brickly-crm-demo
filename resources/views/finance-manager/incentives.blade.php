@extends('finance-manager.layout')

@section('title', 'Incentive Desk')
@section('page_title', 'Incentive Desk')
@section('page_subtitle', 'Approve, reject, and monitor incentive payouts in a cleaner finance review surface with fast filters and queue visibility.')

@push('styles')
<style>
    .fi-grid { display: grid; gap: 24px; }
    .fi-card {
        background: linear-gradient(180deg, #ffffff 0%, #fffdf8 100%);
        border: 1px solid rgba(200, 190, 172, 0.86);
        border-radius: 28px;
        padding: 26px;
        box-shadow: 0 18px 44px rgba(10, 25, 16, 0.08);
    }
    .fi-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        flex-wrap: wrap;
        margin-bottom: 18px;
    }
    .fi-head h2, .fi-head h3 {
        margin: 0;
        font-size: 28px;
        line-height: 1.05;
        font-weight: 800;
        letter-spacing: -0.04em;
        color: #0b2e20;
    }
    .fi-head p {
        margin: 8px 0 0;
        color: #66756f;
        font-size: 14px;
    }
    .fi-filter-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 220px));
        gap: 14px;
    }
    .fi-filter {
        width: 100%;
        padding: 13px 14px;
        border-radius: 16px;
        border: 1px solid #d8d2c6;
        background: #f8f6f0;
        color: #0b2e20;
        font-size: 15px;
        font-family: inherit;
        outline: none;
        transition: border-color .2s ease, box-shadow .2s ease, background .2s ease;
    }
    .fi-filter:focus {
        border-color: #0f6b43;
        background: #fff;
        box-shadow: 0 0 0 4px rgba(15, 107, 67, 0.1);
    }
    .fi-stats {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 16px;
    }
    .fi-stat {
        position: relative;
        overflow: hidden;
        background: #fff;
        border: 1px solid rgba(213, 205, 190, 0.9);
        border-radius: 24px;
        padding: 22px;
        box-shadow: 0 14px 32px rgba(10, 25, 16, 0.07);
    }
    .fi-stat::after {
        content: "";
        position: absolute;
        inset: auto -34px -42px auto;
        width: 120px;
        height: 120px;
        border-radius: 50%;
        background: rgba(15, 107, 67, 0.08);
    }
    .fi-stat.pending::after { background: rgba(245, 158, 11, 0.13); }
    .fi-stat.approved::after { background: rgba(22, 163, 74, 0.12); }
    .fi-stat.rejected::after { background: rgba(239, 68, 68, 0.11); }
    .fi-stat.amount::after { background: rgba(15, 107, 67, 0.13); }
    .fi-stat-icon {
        width: 38px;
        height: 38px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #0f5f3d;
        background: #edf8f2;
        margin-bottom: 14px;
    }
    .fi-stat.pending .fi-stat-icon { color: #a16207; background: #fffbeb; }
    .fi-stat.approved .fi-stat-icon { color: #15803d; background: #ecfdf5; }
    .fi-stat.rejected .fi-stat-icon { color: #b91c1c; background: #fff1f2; }
    .fi-stat-label {
        font-size: 11px;
        letter-spacing: 0.18em;
        text-transform: uppercase;
        color: #75847d;
        font-weight: 800;
    }
    .fi-stat-value {
        margin-top: 12px;
        font-size: 32px;
        line-height: 1;
        font-weight: 800;
        letter-spacing: -0.04em;
        color: #0b2e20;
    }
    .fi-stat-note {
        margin-top: 8px;
        font-size: 13px;
        color: #697771;
    }
    .fi-list {
        display: grid;
        gap: 18px;
    }
    .fi-item {
        position: relative;
        overflow: hidden;
        background: #fff;
        border: 1px solid #e5ded2;
        border-radius: 24px;
        padding: 20px;
        display: grid;
        gap: 18px;
        box-shadow: 0 12px 30px rgba(10, 25, 16, 0.045);
        transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
    }
    .fi-item:hover {
        transform: translateY(-2px);
        border-color: rgba(15, 107, 67, 0.28);
        box-shadow: 0 18px 42px rgba(10, 25, 16, 0.08);
    }
    .fi-item-top {
        display: flex;
        justify-content: space-between;
        gap: 16px;
        align-items: flex-start;
        flex-wrap: wrap;
    }
    .fi-item h3 {
        margin: 0;
        font-size: 23px;
        line-height: 1.08;
        font-weight: 800;
        color: #0b2e20;
    }
    .fi-title-row {
        display: flex;
        gap: 14px;
        align-items: center;
    }
    .fi-avatar {
        width: 48px;
        height: 48px;
        flex: 0 0 auto;
        border-radius: 16px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #e7f6ee, #f9f5eb);
        color: #0f5f3d;
        font-weight: 900;
        border: 1px solid #d7eadf;
    }
    .fi-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        font-size: 13px;
        color: #6b7771;
        margin-top: 8px;
    }
    .fi-meta span {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 9px;
        border-radius: 999px;
        background: #f7f5ee;
        border: 1px solid #ece4d8;
    }
    .fi-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 9px 13px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 800;
    }
    .fi-badge.pending { background: #fff7ed; color: #9a3412; }
    .fi-badge.approved { background: #ecfdf5; color: #166534; }
    .fi-badge.rejected { background: #fff1f2; color: #be123c; }
    .fi-details {
        background: #faf8f2;
        border: 1px solid #ebe4d8;
        border-radius: 20px;
        padding: 0;
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        overflow: hidden;
    }
    .fi-details > div {
        padding: 16px 18px;
        border-right: 1px solid #ebe4d8;
    }
    .fi-details > div:last-child { border-right: 0; }
    .fi-amount {
        color: #0f6b43;
        font-size: 18px;
    }
    .fi-detail-label {
        display: block;
        font-size: 11px;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: #7c8b84;
        font-weight: 800;
        margin-bottom: 6px;
    }
    .fi-detail-value {
        font-size: 15px;
        font-weight: 700;
        color: #0b2e20;
    }
    .fi-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        padding-top: 2px;
    }
    .fi-btn {
        width: 100%;
        padding: 14px 16px;
        border-radius: 16px;
        border: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        font-size: 14px;
        font-weight: 800;
        cursor: pointer;
        transition: transform .18s ease, box-shadow .18s ease, filter .18s ease;
    }
    .fi-btn:hover { transform: translateY(-1px); filter: brightness(1.02); }
    .fi-btn.approve { background: linear-gradient(135deg, #0f5f3d, #16a34a); color: #fff; box-shadow: 0 14px 26px rgba(22, 101, 52, 0.18); }
    .fi-btn.reject { background: linear-gradient(135deg, #bb1f25, #ef4444); color: #fff; box-shadow: 0 14px 26px rgba(185, 28, 28, 0.16); }
    .fi-empty {
        padding: 44px 20px;
        text-align: center;
        color: #6b7771;
        border: 1px dashed #ddd7ca;
        border-radius: 22px;
        background: #fcfbf8;
    }
    .fi-modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(10, 20, 14, 0.42);
        backdrop-filter: blur(4px);
        z-index: 1000;
        align-items: center;
        justify-content: center;
        padding: 24px;
    }
    .fi-modal.show { display: flex; }
    .fi-modal-card {
        width: min(560px, 100%);
        background: #fff;
        border-radius: 26px;
        padding: 24px;
        border: 1px solid #ddd7ca;
        box-shadow: 0 24px 50px rgba(10, 25, 16, 0.16);
    }
    .fi-modal-card h3 {
        margin: 0 0 18px;
        font-size: 24px;
        font-weight: 800;
        color: #0b2e20;
    }
    .fi-textarea {
        width: 100%;
        min-height: 120px;
        padding: 14px;
        border-radius: 16px;
        border: 1px solid #d8d2c6;
        background: #f8f6f0;
        font: inherit;
        color: #0b2e20;
        resize: vertical;
    }
    .fi-modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 16px;
        flex-wrap: wrap;
    }
    @media (max-width: 1100px) {
        .fi-filter-grid, .fi-stats, .fi-details { grid-template-columns: 1fr; }
        .fi-details > div { border-right: 0; border-bottom: 1px solid #ebe4d8; }
        .fi-details > div:last-child { border-bottom: 0; }
    }
    @media (max-width: 700px) {
        .fi-card, .fi-item { padding: 18px; border-radius: 20px; }
        .fi-head h2, .fi-head h3 { font-size: 24px; }
        .fi-actions { grid-template-columns: 1fr; }
        .fi-title-row { align-items: flex-start; }
    }
</style>
@endpush

@section('content')
<div class="fi-grid">
    <section class="fi-card">
        <div class="fi-head">
            <div>
                <h2>Incentive Queue</h2>
                <p>Filter pending, approved, and rejected finance decisions without leaving the desk.</p>
            </div>
        </div>
        <div class="fi-filter-grid">
            <select id="statusFilter" class="fi-filter" onchange="loadIncentives()">
                <option value="">All Status</option>
                <option value="pending_finance_manager">Pending</option>
                <option value="verified">Approved</option>
                <option value="rejected">Rejected</option>
            </select>
            <select id="typeFilter" class="fi-filter" onchange="loadIncentives()">
                <option value="">All Types</option>
                <option value="closer">Closer</option>
                <option value="site_visit">Site Visit</option>
            </select>
        </div>
    </section>

    <section class="fi-stats">
        <div class="fi-stat pending">
            <div class="fi-stat-icon"><i class="fas fa-hourglass-half"></i></div>
            <div class="fi-stat-label">Pending</div>
            <div class="fi-stat-value" id="fiPendingCount">0</div>
            <div class="fi-stat-note">Awaiting finance verification</div>
        </div>
        <div class="fi-stat approved">
            <div class="fi-stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="fi-stat-label">Approved</div>
            <div class="fi-stat-value" id="fiApprovedCount">0</div>
            <div class="fi-stat-note">Verified and cleared</div>
        </div>
        <div class="fi-stat rejected">
            <div class="fi-stat-icon"><i class="fas fa-circle-xmark"></i></div>
            <div class="fi-stat-label">Rejected</div>
            <div class="fi-stat-value" id="fiRejectedCount">0</div>
            <div class="fi-stat-note">Returned to queue with remarks</div>
        </div>
        <div class="fi-stat amount">
            <div class="fi-stat-icon"><i class="fas fa-indian-rupee-sign"></i></div>
            <div class="fi-stat-label">Pending Amount</div>
            <div class="fi-stat-value" id="fiPendingAmount">Rs 0</div>
            <div class="fi-stat-note">Value waiting for decision</div>
        </div>
    </section>

    <section class="fi-card">
        <div class="fi-head">
            <div>
                <h3>Incentive Review List</h3>
                <p>Open queue of closer and site-visit incentives for Finance action.</p>
            </div>
        </div>
        <div id="incentivesContainer" class="fi-list">
            <div class="fi-empty">Loading incentives...</div>
        </div>
    </section>
</div>

<div id="rejectModal" class="fi-modal">
    <div class="fi-modal-card">
        <h3>Reject Incentive</h3>
        <form id="rejectForm">
            <textarea id="rejectionReason" class="fi-textarea" placeholder="Enter rejection reason" required></textarea>
            <div class="fi-modal-actions">
                <button type="button" class="fi-btn" style="background:#eef2f1;color:#47554f;flex:0 0 auto;" onclick="closeRejectModal()">Cancel</button>
                <button type="button" class="fi-btn reject" style="flex:0 0 auto;" onclick="submitReject()">Reject</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    const API_BASE_URL = '/api';
    const token = document.querySelector('meta[name="api-token"]').getAttribute('content');
    let currentRejectIncentiveId = null;

    function getToken() {
        return token;
    }

    function setIncentiveSummary(incentives) {
        const pending = incentives.filter((item) => item.status === 'pending_finance_manager');
        const approved = incentives.filter((item) => item.status === 'verified');
        const rejected = incentives.filter((item) => item.status === 'rejected');
        const pendingAmount = pending.reduce((sum, item) => sum + parseFloat(item.amount || 0), 0);

        document.getElementById('fiPendingCount').textContent = pending.length;
        document.getElementById('fiApprovedCount').textContent = approved.length;
        document.getElementById('fiRejectedCount').textContent = rejected.length;
        document.getElementById('fiPendingAmount').textContent = `Rs ${pendingAmount.toFixed(2)}`;

        const badge = document.getElementById('pendingIncentivesBadge');
        if (badge) {
            badge.textContent = pending.length;
        }
    }

    async function loadIncentives() {
        const container = document.getElementById('incentivesContainer');
        const statusFilter = document.getElementById('statusFilter').value;
        const typeFilter = document.getElementById('typeFilter').value;

        container.innerHTML = '<div class="fi-empty">Loading incentives...</div>';

        try {
            let url = `${API_BASE_URL}/finance-manager/incentives`;
            const params = new URLSearchParams();
            if (statusFilter) params.append('status', statusFilter);
            if (typeFilter) params.append('type', typeFilter);
            if (params.toString()) url += '?' + params.toString();

            const response = await fetch(url, {
                headers: {
                    'Authorization': `Bearer ${getToken()}`,
                    'Accept': 'application/json',
                },
            });
            const result = await response.json();
            const incentives = result?.data || [];

            setIncentiveSummary(incentives);

            if (!incentives.length) {
                container.innerHTML = '<div class="fi-empty">No incentives found for the selected filters.</div>';
                return;
            }

            container.innerHTML = incentives.map((incentive) => {
                const badgeClass = incentive.status === 'pending_finance_manager'
                    ? 'pending'
                    : incentive.status === 'verified'
                        ? 'approved'
                        : 'rejected';
                const statusText = incentive.status === 'pending_finance_manager'
                    ? 'Pending'
                    : incentive.status === 'verified'
                        ? 'Approved'
                        : 'Rejected';
                const leadName = incentive.site_visit?.lead?.name || incentive.site_visit?.customer_name || 'No lead name';
                const leadInitial = leadName.trim().charAt(0).toUpperCase() || 'I';
                const requestedBy = incentive.user?.name || 'User';
                const date = new Date(incentive.created_at).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
                const amount = parseFloat(incentive.amount || 0).toFixed(2);

                return `
                    <article class="fi-item">
                        <div class="fi-item-top">
                            <div class="fi-title-row">
                                <div class="fi-avatar">${leadInitial}</div>
                                <div>
                                    <h3>${leadName}</h3>
                                    <div class="fi-meta">
                                        <span><i class="fas fa-user"></i> ${requestedBy}</span>
                                        <span><i class="fas fa-calendar-day"></i> ${date}</span>
                                    </div>
                                </div>
                            </div>
                            <span class="fi-badge ${badgeClass}">${statusText}</span>
                        </div>
                        <div class="fi-details">
                            <div>
                                <span class="fi-detail-label">Type</span>
                                <div class="fi-detail-value">${incentive.type === 'closer' ? 'Closer' : 'Site Visit'}</div>
                            </div>
                            <div>
                                <span class="fi-detail-label">Amount</span>
                                <div class="fi-detail-value fi-amount">Rs ${amount}</div>
                            </div>
                            <div>
                                <span class="fi-detail-label">Property</span>
                                <div class="fi-detail-value">${incentive.site_visit?.property_name || 'Not linked'}</div>
                            </div>
                        </div>
                        ${incentive.status === 'pending_finance_manager' ? `
                            <div class="fi-actions">
                                <button class="fi-btn approve" onclick="approveIncentive(${incentive.id})"><i class="fas fa-check"></i> Approve</button>
                                <button class="fi-btn reject" onclick="showRejectModal(${incentive.id})"><i class="fas fa-xmark"></i> Reject</button>
                            </div>
                        ` : ''}
                        ${incentive.rejection_reason ? `
                            <div class="fi-details" style="grid-template-columns:1fr;background:#fff1f2;border-color:#fecdd3;">
                                <div>
                                    <span class="fi-detail-label" style="color:#be123c;">Rejection Reason</span>
                                    <div class="fi-detail-value" style="color:#9f1239;">${incentive.rejection_reason}</div>
                                </div>
                            </div>
                        ` : ''}
                    </article>
                `;
            }).join('');
        } catch (error) {
            console.error('Error loading incentives:', error);
            container.innerHTML = '<div class="fi-empty">Error loading incentives.</div>';
        }
    }

    async function approveIncentive(incentiveId) {
        if (!confirm('Approve this incentive?')) return;

        try {
            const response = await fetch(`${API_BASE_URL}/finance-manager/incentives/${incentiveId}/verify`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${getToken()}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
            });

            const result = await response.json();
            if (result && result.success) {
                alert('Incentive approved successfully.');
                loadIncentives();
            } else {
                alert(result.message || 'Failed to approve incentive');
            }
        } catch (error) {
            console.error('Error approving incentive:', error);
            alert('Network error. Please try again.');
        }
    }

    function showRejectModal(incentiveId) {
        currentRejectIncentiveId = incentiveId;
        document.getElementById('rejectModal').classList.add('show');
        document.getElementById('rejectionReason').value = '';
    }

    function closeRejectModal() {
        document.getElementById('rejectModal').classList.remove('show');
        currentRejectIncentiveId = null;
        document.getElementById('rejectionReason').value = '';
    }

    async function submitReject() {
        if (!currentRejectIncentiveId) return;

        const reason = document.getElementById('rejectionReason').value.trim();
        if (!reason) {
            alert('Please enter a rejection reason');
            return;
        }

        try {
            const response = await fetch(`${API_BASE_URL}/finance-manager/incentives/${currentRejectIncentiveId}/reject`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${getToken()}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ reason }),
            });

            const result = await response.json();
            if (result && result.success) {
                closeRejectModal();
                alert('Incentive rejected successfully.');
                loadIncentives();
            } else {
                const errorText = result?.errors?.reason?.[0] || result?.errors?.rejection_reason?.[0] || result?.message || 'Failed to reject incentive';
                alert(errorText);
            }
        } catch (error) {
            console.error('Error rejecting incentive:', error);
            alert('Network error. Please try again.');
        }
    }

    document.getElementById('rejectModal').addEventListener('click', function (event) {
        if (event.target === this) {
            closeRejectModal();
        }
    });

    loadIncentives();
</script>
@endpush
@endsection

@extends('finance-manager.layout')

@section('title', 'Finance Dashboard')
@section('page_title', 'Finance Dashboard')
@section('page_subtitle', 'Monitor incentive queues, payroll readiness, estimated salary exposure, and month-end freeze status without leaving the workspace.')

@push('styles')
<style>
    .fd-grid { display: grid; gap: 22px; }
    .fd-hero {
        background: linear-gradient(135deg, #0b2f22 0%, #0f5b42 48%, #1d7a58 100%);
        border-radius: 28px;
        padding: 28px;
        color: #f5fbf7;
        display: grid;
        grid-template-columns: minmax(0, 1.3fr) minmax(300px, 0.9fr);
        gap: 24px;
        box-shadow: 0 18px 36px rgba(10, 25, 16, 0.16);
    }
    .fd-hero h2 {
        margin: 0;
        font-size: 34px;
        line-height: 1.02;
        font-weight: 800;
        letter-spacing: -0.04em;
    }
    .fd-hero p {
        margin: 14px 0 0;
        color: rgba(245, 251, 247, 0.8);
        font-size: 15px;
        max-width: 620px;
    }
    .fd-hero-actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 24px; }
    .fd-hero-action {
        padding: 13px 18px;
        border-radius: 16px;
        font-size: 14px;
        font-weight: 800;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: 0.2s ease;
    }
    .fd-hero-action.primary { background: #fff; color: #0f5b42; }
    .fd-hero-action.secondary { background: rgba(255,255,255,0.1); color: #fff; border: 1px solid rgba(255,255,255,0.16); }
    .fd-focus-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
    .fd-focus-card {
        background: rgba(255,255,255,0.1);
        border: 1px solid rgba(255,255,255,0.14);
        border-radius: 20px;
        padding: 18px;
    }
    .fd-focus-label {
        font-size: 11px;
        letter-spacing: 0.18em;
        text-transform: uppercase;
        color: rgba(245, 251, 247, 0.64);
        font-weight: 800;
    }
    .fd-focus-value {
        margin-top: 10px;
        font-size: 30px;
        font-weight: 800;
        letter-spacing: -0.04em;
    }
    .fd-focus-note { margin-top: 6px; font-size: 13px; color: rgba(245, 251, 247, 0.78); }
    .fd-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 18px; }
    .fd-stat, .fd-section {
        background: #fff;
        border: 1px solid #ddd7ca;
        border-radius: 24px;
        padding: 24px;
        box-shadow: 0 10px 24px rgba(10, 25, 16, 0.05);
    }
    .fd-stat-label {
        font-size: 11px;
        letter-spacing: 0.18em;
        text-transform: uppercase;
        color: #75847d;
        font-weight: 800;
    }
    .fd-stat-value {
        margin-top: 14px;
        font-size: 34px;
        font-weight: 800;
        letter-spacing: -0.05em;
        color: #0b2e20;
    }
    .fd-stat-note { margin-top: 8px; font-size: 13px; color: #65746d; }
    .fd-section-head {
        display: flex;
        justify-content: space-between;
        gap: 16px;
        align-items: flex-start;
        margin-bottom: 18px;
        flex-wrap: wrap;
    }
    .fd-section-head h3 {
        margin: 0;
        font-size: 28px;
        line-height: 1.05;
        font-weight: 800;
        letter-spacing: -0.04em;
        color: #0b2e20;
    }
    .fd-section-head p { margin: 8px 0 0; color: #66756f; font-size: 14px; }
    .fd-preview-grid, .fd-dual-grid {
        display: grid;
        grid-template-columns: 1.2fr 0.8fr;
        gap: 18px;
    }
    .fd-preview-panel {
        background: #f7f5ee;
        border: 1px solid #e7e0d4;
        border-radius: 20px;
        padding: 18px;
    }
    .fd-preview-list, .fd-activity, .fd-watch-list { display: grid; gap: 12px; }
    .fd-preview-row, .fd-activity-item, .fd-watch-item {
        background: #fff;
        border: 1px solid #ebe5da;
        border-radius: 18px;
        padding: 15px 16px;
    }
    .fd-preview-row, .fd-watch-item {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: center;
    }
    .fd-preview-row strong, .fd-activity-item strong, .fd-watch-item strong {
        display: block;
        font-size: 14px;
        color: #0b2e20;
    }
    .fd-preview-row span, .fd-activity-item span, .fd-watch-item span {
        display: block;
        margin-top: 4px;
        font-size: 12px;
        color: #73827b;
    }
    .fd-pill {
        padding: 8px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 800;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .fd-pill.draft { background: #fff7ed; color: #9a3412; }
    .fd-pill.frozen { background: #ecfdf5; color: #166534; }
    .fd-pill.released { background: #eff6ff; color: #1d4ed8; }
    .fd-watch-metric { text-align: right; font-size: 13px; color: #0f5b42; font-weight: 800; }
    .fd-booked-grid {
        display: grid;
        grid-template-columns: 0.82fr 1.18fr;
        gap: 18px;
    }
    .fd-booked-stat-grid { display: grid; gap: 12px; }
    .fd-booked-stat {
        padding: 16px;
        border-radius: 18px;
        background: #f7f5ee;
        border: 1px solid #e7e0d4;
    }
    .fd-booked-stat strong {
        display: block;
        font-size: 27px;
        line-height: 1;
        color: #0b2e20;
        font-weight: 800;
    }
    .fd-booked-stat span {
        display: block;
        margin-top: 8px;
        color: #65746d;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }
    .fd-booked-list { display: grid; gap: 12px; }
    .fd-booked-item {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 14px;
        padding: 16px;
        border-radius: 18px;
        background: #fff;
        border: 1px solid #ebe5da;
    }
    .fd-booked-item strong {
        display: block;
        font-size: 15px;
        color: #0b2e20;
    }
    .fd-booked-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 10px 14px;
        margin-top: 8px;
        color: #6d7b75;
        font-size: 12px;
    }
    .fd-booked-side {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 8px;
        text-align: right;
    }
    .fd-booked-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }
    .fd-booked-tag {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 12px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 800;
    }
    .fd-booked-tag.ready { background: #ecfdf5; color: #166534; }
    .fd-booked-tag.missing { background: #fff7ed; color: #9a3412; }
    .fd-booked-amount { font-size: 13px; font-weight: 800; color: #0f5b42; }
    .fd-booked-btn {
        border: none;
        border-radius: 12px;
        padding: 10px 14px;
        font-size: 12px;
        font-weight: 800;
        cursor: pointer;
        transition: 0.2s ease;
    }
    .fd-booked-btn.approve {
        background: linear-gradient(135deg, #166534, #16a34a);
        color: #fff;
    }
    .fd-booked-btn:disabled {
        opacity: 0.7;
        cursor: wait;
    }

    @media (max-width: 1100px) {
        .fd-hero, .fd-preview-grid, .fd-stats, .fd-dual-grid, .fd-booked-grid { grid-template-columns: 1fr; }
    }

    @media (max-width: 700px) {
        .fd-hero { padding: 20px; border-radius: 22px; }
        .fd-hero h2 { font-size: 28px; }
        .fd-focus-grid { grid-template-columns: 1fr; }
        .fd-section, .fd-stat { padding: 18px; border-radius: 20px; }
        .fd-section-head h3 { font-size: 24px; }
        .fd-preview-row, .fd-watch-item { align-items: flex-start; }
    }
</style>
@endpush

@section('content')
<div class="fd-grid">
    <section class="fd-hero">
        <div>
            <h2>Finance control for incentives, freeze state, and salary payout readiness.</h2>
            <p>Current month payroll metrics, incentive queue pressure, and freeze status stay visible in one enterprise surface so Finance can close the month without switching modules.</p>
            <div class="fd-hero-actions">
                <a href="{{ route('finance-manager.payroll', ['year' => $year, 'month' => $month]) }}" class="fd-hero-action primary"><i class="fas fa-file-invoice-dollar"></i> Open Payroll</a>
                <a href="{{ route('finance-manager.payslips.index', ['year' => $year, 'month' => $month]) }}" class="fd-hero-action secondary"><i class="fas fa-wallet"></i> Open Payslips</a>
            </div>
        </div>
        <div class="fd-focus-grid">
            <div class="fd-focus-card">
                <div class="fd-focus-label">Payroll Status</div>
                <div class="fd-focus-value">{{ $overview['freeze'] ? ucfirst($overview['freeze']->status) : 'Draft' }}</div>
                <div class="fd-focus-note">Month {{ sprintf('%02d', $month) }}/{{ $year }}</div>
            </div>
            <div class="fd-focus-card">
                <div class="fd-focus-label">Employees</div>
                <div class="fd-focus-value">{{ $overview['all_totals']['employees'] ?? 0 }}</div>
                <div class="fd-focus-note">Attendance-enabled payroll users</div>
            </div>
            <div class="fd-focus-card">
                <div class="fd-focus-label">Payable Days</div>
                <div class="fd-focus-value">{{ number_format($overview['all_totals']['payable_days'] ?? 0, 2) }}</div>
                <div class="fd-focus-note">Processed from monthly rollups</div>
            </div>
            <div class="fd-focus-card">
                <div class="fd-focus-label">Estimated Salary</div>
                <div class="fd-focus-value">Rs {{ number_format($overview['all_totals']['estimated_salary'] ?? 0, 0) }}</div>
                <div class="fd-focus-note">Current month salary exposure</div>
            </div>
        </div>
    </section>

    <section class="fd-stats">
        <div class="fd-stat">
            <div class="fd-stat-label">Pending Incentives</div>
            <div class="fd-stat-value">{{ $incentives['pending_count'] ?? 0 }}</div>
            <div class="fd-stat-note">Waiting for finance action</div>
        </div>
        <div class="fd-stat">
            <div class="fd-stat-label">Approved This Month</div>
            <div class="fd-stat-value">{{ $incentives['approved_count'] ?? 0 }}</div>
            <div class="fd-stat-note">Verified in selected month</div>
        </div>
        <div class="fd-stat">
            <div class="fd-stat-label">Pending Amount</div>
            <div class="fd-stat-value">Rs {{ number_format($incentives['pending_amount'] ?? 0, 0) }}</div>
            <div class="fd-stat-note">Exposure still not cleared</div>
        </div>
        <div class="fd-stat">
            <div class="fd-stat-label">Approved Amount</div>
            <div class="fd-stat-value">Rs {{ number_format($incentives['approved_amount'] ?? 0, 0) }}</div>
            <div class="fd-stat-note">Value already cleared</div>
        </div>
    </section>

    <section class="fd-section">
        <div class="fd-section-head">
            <div>
                <h3>Booked Customers</h3>
                <p>Customers whose closer incentive is already verified and ready for finance visibility.</p>
            </div>
            <span class="fd-pill frozen">{{ $bookedCustomers['count'] ?? 0 }} verified bookings</span>
        </div>
        <div class="fd-booked-grid">
            <div class="fd-preview-panel">
                <div class="fd-booked-stat-grid">
                    <div class="fd-booked-stat">
                        <strong>{{ $bookedCustomers['count'] ?? 0 }}</strong>
                        <span>Verified Bookings</span>
                    </div>
                    <div class="fd-booked-stat">
                        <strong>{{ $bookedCustomers['with_complete_kyc'] ?? 0 }}</strong>
                        <span>KYC Complete Cases</span>
                    </div>
                    <div class="fd-booked-stat">
                        <strong>Rs {{ number_format($bookedCustomers['total_incentive'] ?? 0, 0) }}</strong>
                        <span>Verified Incentive Amount</span>
                    </div>
                </div>
            </div>
            <div class="fd-booked-list">
                @forelse($bookedCustomers['recent'] ?? [] as $customer)
                    <div class="fd-booked-item">
                        <div>
                            <strong>{{ $customer['customer_name'] }}</strong>
                            <div class="fd-booked-meta">
                                <span>{{ $customer['project'] }}</span>
                                <span>{{ $customer['budget_range'] }}</span>
                                <span>{{ $customer['phone'] ?: 'Phone not available' }}</span>
                            </div>
                            <div class="fd-booked-meta">
                                <span>Assigned: {{ $customer['assigned_to'] }}</span>
                                <span>Transferred by: {{ $customer['transferred_by'] }}</span>
                                <span>{{ $customer['finance_transferred_at'] ?: 'Transfer time not available' }}</span>
                                <span>{{ $customer['incentive_verified_at'] ?: 'Incentive verified' }}</span>
                            </div>
                        </div>
                        <div class="fd-booked-side">
                            <span class="fd-booked-tag {{ $customer['has_complete_kyc'] ? 'ready' : 'missing' }}">
                                <i class="fas {{ $customer['has_complete_kyc'] ? 'fa-circle-check' : 'fa-triangle-exclamation' }}"></i>
                                {{ $customer['has_complete_kyc'] ? 'KYC complete' : 'KYC pending' }}
                            </span>
                            @if(!empty($customer['payment_mode']))
                                <div class="fd-booked-amount">{{ $customer['payment_mode'] }}</div>
                            @endif
                            @if(($customer['incentive_amount'] ?? 0) > 0)
                                <div class="fd-booked-amount">Incentive Rs {{ number_format($customer['incentive_amount'], 0) }}</div>
                            @endif
                            <div class="fd-booked-actions">
                                @if(!empty($customer['lead_id']))
                                    <a href="{{ route('leads.show', $customer['lead_id']) }}" class="fd-booked-btn approve" style="text-decoration:none;">
                                        <i class="fas fa-arrow-up-right-from-square"></i> View Lead
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="fd-activity-item">
                        <strong>No verified booked customers</strong>
                        <span>Customers will appear here after their closer incentive is verified.</span>
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="fd-section">
        <div class="fd-section-head">
            <div>
                <h3>Payroll Preview</h3>
                <p>Month {{ sprintf('%02d', $month) }}/{{ $year }} attendance rollup, freeze state, and quick handoff into payroll operations.</p>
            </div>
            <span class="fd-pill {{ $overview['freeze']?->status ?? 'draft' }}">{{ $overview['freeze'] ? ucfirst($overview['freeze']->status) : 'Draft' }}</span>
        </div>
        <div class="fd-preview-grid">
            <div class="fd-preview-panel">
                <div class="fd-preview-list">
                    <div class="fd-preview-row">
                        <div>
                            <strong>Payroll employees</strong>
                            <span>Attendance-linked staff in scope</span>
                        </div>
                        <div>{{ $overview['all_totals']['employees'] ?? 0 }}</div>
                    </div>
                    <div class="fd-preview-row">
                        <div>
                            <strong>Late penalty days</strong>
                            <span>Deduction pressure before freeze</span>
                        </div>
                        <div>{{ number_format($overview['all_totals']['late_penalty_days'] ?? 0, 2) }}</div>
                    </div>
                    <div class="fd-preview-row">
                        <div>
                            <strong>Approved overtime</strong>
                            <span>Minutes payable in current month</span>
                        </div>
                        <div>{{ (int) ($overview['all_totals']['overtime_minutes'] ?? 0) }} min</div>
                    </div>
                    <div class="fd-preview-row">
                        <div>
                            <strong>Frozen rollups</strong>
                            <span>Rollups already locked for payroll</span>
                        </div>
                        <div>{{ $overview['all_totals']['frozen_rollups'] ?? 0 }}</div>
                    </div>
                </div>
            </div>
            <div class="fd-preview-panel">
                <div class="fd-preview-list">
                    <a href="{{ route('finance-manager.payroll', ['year' => $year, 'month' => $month]) }}" class="fd-preview-row" style="text-decoration:none;">
                        <div>
                            <strong>Open payroll desk</strong>
                            <span>Review attendance rollups and freeze control</span>
                        </div>
                        <div><i class="fas fa-arrow-right"></i></div>
                    </a>
                    <a href="{{ route('finance-manager.payslips.index', ['year' => $year, 'month' => $month]) }}" class="fd-preview-row" style="text-decoration:none;">
                        <div>
                            <strong>Open payslips</strong>
                            <span>Generate PDF and export payroll packs</span>
                        </div>
                        <div><i class="fas fa-arrow-right"></i></div>
                    </a>
                    <a href="{{ route('finance-manager.incentives') }}" class="fd-preview-row" style="text-decoration:none;">
                        <div>
                            <strong>Clear incentive queue</strong>
                            <span>Approve or reject pending incentive claims</span>
                        </div>
                        <div><i class="fas fa-arrow-right"></i></div>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="fd-dual-grid">
        <div class="fd-section">
            <div class="fd-section-head">
                <div>
                    <h3>Attention Required</h3>
                    <p>People with visible attendance or overtime exceptions before payroll closure.</p>
                </div>
            </div>
            <div class="fd-watch-list">
                @forelse($overview['attention'] ?? [] as $item)
                    <div class="fd-watch-item">
                        <div>
                            <strong>{{ $item['user'] }}</strong>
                            <span>{{ $item['role'] }} · {{ $item['office'] }}</span>
                        </div>
                        <div class="fd-watch-metric">
                            <div>{{ number_format($item['late_penalty_days'], 2) }} penalty</div>
                            <div>{{ number_format($item['absent_days'], 2) }} absent · {{ $item['overtime_minutes'] }} min OT</div>
                        </div>
                    </div>
                @empty
                    <div class="fd-activity-item">
                        <strong>No major payroll exceptions</strong>
                        <span>The current month does not show late-penalty, absence, or overtime spikes.</span>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="fd-section">
            <div class="fd-section-head">
                <div>
                    <h3>Recent Activity</h3>
                    <p>Latest incentive events flowing into Finance review.</p>
                </div>
            </div>
            <div class="fd-activity">
                @forelse($incentives['recent'] ?? [] as $item)
                    <div class="fd-activity-item">
                        <strong>{{ $item['lead'] }} · Rs {{ number_format($item['amount'], 2) }}</strong>
                        <span>{{ ucfirst($item['status']) }} · {{ $item['user'] }} · {{ $item['created_at'] }}</span>
                    </div>
                @empty
                    <div class="fd-activity-item">
                        <strong>No recent activity</strong>
                        <span>No incentive movement was recorded for this month.</span>
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="fd-section">
        <div class="fd-section-head">
            <div>
                <h3>Office Exposure</h3>
                <p>Highest payroll load by office for the selected month.</p>
            </div>
        </div>
        <div class="fd-preview-list">
            @forelse($overview['highlights']['offices'] ?? [] as $office)
                <div class="fd-preview-row">
                    <div>
                        <strong>{{ $office['office'] }}</strong>
                        <span>{{ $office['employees'] }} employees in payroll scope</span>
                    </div>
                    <div>Rs {{ number_format($office['estimated_salary'], 0) }}</div>
                </div>
            @empty
                <div class="fd-activity-item">
                    <strong>No office summary yet</strong>
                    <span>Run payroll preview once to populate office-level exposure.</span>
                </div>
            @endforelse
        </div>
    </section>
</div>

@endsection

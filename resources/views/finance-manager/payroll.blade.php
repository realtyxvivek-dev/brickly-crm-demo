@extends('finance-manager.layout')

@section('title', 'Payroll Attendance')
@section('page_title', 'Payroll Control')
@section('page_subtitle', 'Preview month-end attendance payroll, freeze the period when numbers are final, and export clean salary-ready attendance summaries.')

@push('styles')
<style>
    .fp-grid { display: grid; gap: 22px; }
    .fp-card {
        background: #fff;
        border: 1px solid #ddd7ca;
        border-radius: 24px;
        padding: 24px;
        box-shadow: 0 10px 24px rgba(10, 25, 16, 0.05);
    }
    .fp-header {
        display: flex;
        justify-content: space-between;
        gap: 18px;
        align-items: flex-start;
        flex-wrap: wrap;
        margin-bottom: 18px;
    }
    .fp-header h2, .fp-header h3 {
        margin: 0;
        font-size: 28px;
        line-height: 1.05;
        font-weight: 800;
        letter-spacing: -0.04em;
        color: #0b2e20;
    }
    .fp-header p {
        margin: 8px 0 0;
        color: #66756f;
        font-size: 14px;
    }
    .fp-form-grid {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr)) auto;
        gap: 14px;
        align-items: end;
    }
    .fp-field label {
        display: block;
        font-size: 11px;
        letter-spacing: 0.18em;
        text-transform: uppercase;
        color: #75847d;
        font-weight: 800;
        margin-bottom: 8px;
    }
    .fp-input {
        width: 100%;
        padding: 13px 14px;
        border-radius: 16px;
        border: 1px solid #d8d2c6;
        background: #f8f6f0;
        color: #0b2e20;
        font-size: 15px;
        font-family: inherit;
    }
    .fp-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }
    .fp-button {
        padding: 13px 18px;
        border-radius: 16px;
        font-size: 14px;
        font-weight: 800;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        text-decoration: none;
        border: none;
        cursor: pointer;
    }
    .fp-button.primary { background: linear-gradient(135deg, #083725, #0f5b42); color: #fff; }
    .fp-button.success { background: linear-gradient(135deg, #166534, #16a34a); color: #fff; }
    .fp-button.soft { background: #fff; color: #0f5b42; border: 1px solid #d8d2c6; }
    .fp-metrics {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 16px;
    }
    .fp-metric {
        background: #fff;
        border: 1px solid #ddd7ca;
        border-radius: 22px;
        padding: 20px 22px;
        box-shadow: 0 10px 24px rgba(10, 25, 16, 0.05);
    }
    .fp-metric-label {
        font-size: 11px;
        letter-spacing: 0.18em;
        text-transform: uppercase;
        color: #75847d;
        font-weight: 800;
    }
    .fp-metric-value {
        margin-top: 12px;
        font-size: 32px;
        line-height: 1;
        font-weight: 800;
        letter-spacing: -0.04em;
        color: #0b2e20;
    }
    .fp-metric-note {
        margin-top: 8px;
        font-size: 13px;
        color: #697771;
    }
    .fp-freeze-grid {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 18px;
        align-items: center;
    }
    .fp-status-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 14px;
        border-radius: 999px;
        font-size: 13px;
        font-weight: 800;
    }
    .fp-status-pill.draft { background: #fff7ed; color: #9a3412; }
    .fp-status-pill.frozen { background: #ecfdf5; color: #166534; }
    .fp-status-pill.released { background: #eff6ff; color: #1d4ed8; }
    .fp-table-wrap {
        overflow-x: auto;
        border: 1px solid #e8e2d7;
        border-radius: 20px;
    }
    .fp-table {
        width: 100%;
        min-width: 1020px;
        border-collapse: collapse;
    }
    .fp-table thead th {
        background: #f8f6f0;
        color: #75847d;
        font-size: 11px;
        letter-spacing: 0.18em;
        text-transform: uppercase;
        font-weight: 800;
        text-align: left;
        padding: 14px 16px;
        border-bottom: 1px solid #e8e2d7;
    }
    .fp-table tbody td {
        padding: 16px;
        border-bottom: 1px solid #f0ece3;
        font-size: 14px;
        color: #153528;
        vertical-align: top;
    }
    .fp-table tbody tr:last-child td { border-bottom: 0; }
    .fp-user strong {
        display: block;
        font-size: 14px;
        color: #0b2e20;
    }
    .fp-user span {
        display: block;
        margin-top: 5px;
        font-size: 12px;
        color: #72807a;
    }
    .fp-dual-grid {
        display: grid;
        grid-template-columns: 1.1fr 0.9fr;
        gap: 18px;
    }
    .fp-mini-list { display: grid; gap: 12px; }
    .fp-mini-item {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 12px;
        padding: 14px 16px;
        border-radius: 18px;
        background: #f8f6f0;
        border: 1px solid #e8e2d7;
    }
    .fp-mini-item strong { display: block; color: #0b2e20; font-size: 14px; }
    .fp-mini-item span { display: block; margin-top: 4px; color: #697771; font-size: 12px; }
    .fp-mini-value { color: #0f5b42; font-weight: 800; font-size: 13px; text-align: right; }
    @media (max-width: 1180px) {
        .fp-form-grid, .fp-metrics, .fp-freeze-grid, .fp-dual-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 700px) {
        .fp-card, .fp-metric { padding: 18px; border-radius: 20px; }
        .fp-header h2, .fp-header h3 { font-size: 24px; }
        .fp-actions { width: 100%; }
        .fp-button { width: 100%; }
    }
</style>
@endpush

@section('content')
<div class="fp-grid">
    <section class="fp-card">
        <div class="fp-header">
            <div>
                <h2>Payroll Attendance</h2>
                <p>Preview the month, narrow by office, and move straight into export or freeze once Finance is satisfied.</p>
            </div>
        </div>
        <form method="GET" action="{{ route('finance-manager.payroll') }}" class="fp-form-grid">
            <div class="fp-field">
                <label>Year</label>
                <input type="number" name="year" value="{{ $year }}" min="2000" max="2100" class="fp-input">
            </div>
            <div class="fp-field">
                <label>Month</label>
                <input type="number" name="month" value="{{ $month }}" min="1" max="12" class="fp-input">
            </div>
            <div class="fp-field">
                <label>Office</label>
                <select name="office_location_id" class="fp-input">
                    <option value="">All Offices</option>
                    @foreach($offices as $office)
                        <option value="{{ $office->id }}" @selected($officeLocationId === $office->id)>{{ $office->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="fp-field">
                <label>Freeze Status</label>
                @php $freezeStatus = $summary['freeze']?->status ?? 'draft'; @endphp
                <div class="fp-status-pill {{ $freezeStatus }}">
                    <i class="fas fa-lock"></i>
                    {{ ucfirst($freezeStatus) }}
                </div>
            </div>
            <div class="fp-field">
                <label>Search</label>
                <input type="text" name="search" value="{{ $search }}" class="fp-input" placeholder="Employee, role, office">
            </div>
            <div class="fp-field">
                <label>Sort</label>
                <select name="sort" class="fp-input">
                    <option value="salary_desc" @selected($sort === 'salary_desc')>Salary high to low</option>
                    <option value="salary_asc" @selected($sort === 'salary_asc')>Salary low to high</option>
                    <option value="penalty_desc" @selected($sort === 'penalty_desc')>Penalty high to low</option>
                    <option value="overtime_desc" @selected($sort === 'overtime_desc')>Overtime high to low</option>
                    <option value="payable_desc" @selected($sort === 'payable_desc')>Payable days high to low</option>
                </select>
            </div>
            <div class="fp-actions">
                <button type="submit" class="fp-button primary"><i class="fas fa-eye"></i> Preview</button>
                <a href="{{ route('finance-manager.payroll.export', ['year' => $year, 'month' => $month, 'office_location_id' => $officeLocationId]) }}" class="fp-button soft"><i class="fas fa-file-csv"></i> Export CSV</a>
                <a href="{{ route('finance-manager.payslips.index', ['year' => $year, 'month' => $month]) }}" class="fp-button success"><i class="fas fa-wallet"></i> Payslips</a>
            </div>
        </form>
    </section>

    <section class="fp-metrics">
        <div class="fp-metric">
            <div class="fp-metric-label">Employees</div>
            <div class="fp-metric-value">{{ $summary['totals']['employees'] }}</div>
            <div class="fp-metric-note">In current payroll scope</div>
        </div>
        <div class="fp-metric">
            <div class="fp-metric-label">Payable Days</div>
            <div class="fp-metric-value">{{ number_format($summary['totals']['payable_days'], 2) }}</div>
            <div class="fp-metric-note">Ready for salary projection</div>
        </div>
        <div class="fp-metric">
            <div class="fp-metric-label">Late Penalty</div>
            <div class="fp-metric-value">{{ number_format($summary['totals']['late_penalty_days'], 2) }}</div>
            <div class="fp-metric-note">Deduction days before freeze</div>
        </div>
        <div class="fp-metric">
            <div class="fp-metric-label">Estimated Salary</div>
            <div class="fp-metric-value">Rs {{ number_format($summary['totals']['estimated_salary'], 0) }}</div>
            <div class="fp-metric-note">Projected payout</div>
        </div>
        <div class="fp-metric">
            <div class="fp-metric-label">Approved Overtime</div>
            <div class="fp-metric-value">{{ (int) ($summary['totals']['overtime_minutes'] ?? 0) }}</div>
            <div class="fp-metric-note">Minutes added to payroll</div>
        </div>
    </section>

    <section class="fp-card">
        <div class="fp-header">
            <div>
                <h3>Freeze Control</h3>
                <p>
                    Current status:
                    <strong>{{ $summary['freeze']?->status ? ucfirst($summary['freeze']->status) : 'Draft' }}</strong>
                    @if($summary['freeze']?->frozen_at)
                        on {{ $summary['freeze']->frozen_at->format('d M Y h:i A') }}
                    @endif
                </p>
            </div>
        </div>
        <div class="fp-freeze-grid">
            <div>
                <div class="fp-status-pill {{ $freezeStatus }}">
                    <i class="fas fa-shield-halved"></i>
                    {{ ucfirst($freezeStatus) }}
                </div>
            </div>
            <div class="fp-actions">
                <form method="POST" action="{{ route('finance-manager.payroll.freeze') }}">
                    @csrf
                    <input type="hidden" name="year" value="{{ $year }}">
                    <input type="hidden" name="month" value="{{ $month }}">
                    <input type="hidden" name="office_location_id" value="{{ $officeLocationId }}">
                    <button type="submit" class="fp-button primary"><i class="fas fa-lock"></i> Freeze Payroll</button>
                </form>
                @if($summary['freeze'] && $summary['freeze']->status === 'frozen')
                    <form method="POST" action="{{ route('finance-manager.payroll.release', $summary['freeze']) }}">
                        @csrf
                        <button type="submit" class="fp-button" style="background:linear-gradient(135deg,#b91c1c,#ef4444);color:#fff;"><i class="fas fa-lock-open"></i> Release Freeze</button>
                    </form>
                @endif
            </div>
        </div>
    </section>

    <section class="fp-dual-grid">
        <div class="fp-card">
            <div class="fp-header">
                <div>
                    <h3>Payroll Exceptions</h3>
                    <p>High-penalty, absence, or overtime records that should be reviewed before freeze.</p>
                </div>
            </div>
            <div class="fp-mini-list">
                @forelse($summary['attention'] as $item)
                    <div class="fp-mini-item">
                        <div>
                            <strong>{{ $item['user'] }}</strong>
                            <span>{{ $item['role'] }} · {{ $item['office'] }}</span>
                        </div>
                        <div class="fp-mini-value">
                            <div>{{ number_format($item['late_penalty_days'], 2) }} penalty</div>
                            <div>{{ number_format($item['absent_days'], 2) }} absent · {{ $item['overtime_minutes'] }} min OT</div>
                        </div>
                    </div>
                @empty
                    <div class="fp-mini-item">
                        <div>
                            <strong>No exception watchlist</strong>
                            <span>Current filtered payroll set looks clean.</span>
                        </div>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="fp-card">
            <div class="fp-header">
                <div>
                    <h3>Office Breakdown</h3>
                    <p>Where payroll exposure is concentrated in the current filtered view.</p>
                </div>
            </div>
            <div class="fp-mini-list">
                @forelse($summary['highlights']['offices'] as $office)
                    <div class="fp-mini-item">
                        <div>
                            <strong>{{ $office['office'] }}</strong>
                            <span>{{ $office['employees'] }} employees</span>
                        </div>
                        <div class="fp-mini-value">Rs {{ number_format($office['estimated_salary'], 0) }}</div>
                    </div>
                @empty
                    <div class="fp-mini-item">
                        <div>
                            <strong>No office summary</strong>
                            <span>Preview the month to load payroll exposure.</span>
                        </div>
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="fp-card">
        <div class="fp-header">
            <div>
                <h3>Salary Preview</h3>
                <p>
                    Attendance rollups translated into payroll-ready salary previews for the selected month.
                    Showing <strong>{{ $summary['totals']['employees'] }}</strong> of <strong>{{ $summary['all_totals']['employees'] }}</strong> employees.
                </p>
            </div>
        </div>
        <div class="fp-table-wrap">
            <table class="fp-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Office</th>
                        <th>Present</th>
                        <th>Half Day</th>
                        <th>Leave</th>
                        <th>Late</th>
                        <th>Penalty</th>
                        <th>Payable Days</th>
                        <th>Overtime</th>
                        <th>Est. Salary</th>
                        <th>Freeze</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($summary['rollups'] as $rollup)
                        <tr>
                            <td>
                                <div class="fp-user">
                                    <strong>{{ $rollup['user']['name'] ?? 'User' }}</strong>
                                    <span>{{ $rollup['user']['role']['name'] ?? 'Role' }}</span>
                                </div>
                            </td>
                            <td>{{ $rollup['office_location']['name'] ?? 'Company' }}</td>
                            <td>{{ $rollup['present_days'] }}</td>
                            <td>{{ $rollup['half_days'] }}</td>
                            <td>{{ number_format(((float) $rollup['paid_leave_days']) + ((float) $rollup['unpaid_leave_days']), 2) }}</td>
                            <td>{{ $rollup['late_count'] }}</td>
                            <td>{{ $rollup['late_penalty_days'] }}</td>
                            <td><strong>{{ $rollup['payable_days'] }}</strong></td>
                            <td>{{ (int) ($rollup['overtime_minutes'] ?? 0) }} min</td>
                            <td>Rs {{ number_format((float) ($rollup['estimated_salary'] ?? 0), 2) }}</td>
                            <td>{{ !empty($rollup['is_frozen']) ? 'Frozen' : 'Open' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11">No payroll rollups available for the selected month. Use Preview to compute them.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection

@extends(auth()->user()?->isFinanceManager() && !auth()->user()?->isAdmin() ? 'finance-manager.layout' : 'layouts.app')

@section('title', 'Advisor Performance Report - ' . brand_name())
@section('page-title', 'Advisor Performance & Revenue Contribution')
@section('page-subtitle', $report['period_label'] . ' advisor-wise Excel-style performance report.')
@section('page_title', 'Advisor Performance & Revenue Contribution')
@section('page_subtitle', $report['period_label'] . ' advisor-wise Excel-style performance report.')

@push('styles')
<style>
    .ap-shell { display:grid; gap:16px; color:#17211d; }
    .ap-panel { background:#fff; border:1px solid #e2e1dc; border-radius:14px; padding:18px; box-shadow:0 8px 20px rgba(15,23,42,.04); }
    .ap-filters { display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:10px; align-items:flex-end; }
    .ap-field.ap-wide { grid-column:span 2; }
    .ap-field { display:grid; gap:6px; }
    .ap-field label { font-size:12px; font-weight:800; color:#5f6b66; text-transform:uppercase; }
    .ap-input { border:1px solid #d8d6ce; border-radius:10px; padding:10px 12px; min-height:42px; background:#fff; }
    .ap-btn { display:inline-flex; align-items:center; gap:8px; border:none; border-radius:10px; padding:11px 14px; font-weight:800; cursor:pointer; text-decoration:none; }
    .ap-btn.primary { background:#0b6b4f; color:#fff; }
    .ap-btn.soft { background:#eef9f5; color:#0b6b4f; border:1px solid #cfe3d8; }
    .ap-table-wrap { width:100%; max-width:100%; overflow-x:auto; overflow-y:hidden; border:1px solid #e2e1dc; border-radius:12px; -webkit-overflow-scrolling:touch; touch-action:pan-x pan-y; }
    .ap-table { width:100%; min-width:1320px; border-collapse:collapse; font-size:13px; }
    .ap-table th { background:#0b6b4f; color:#fff; padding:10px; text-align:right; white-space:nowrap; }
    .ap-table th:first-child, .ap-table td:first-child { text-align:left; position:sticky; left:0; z-index:1; }
    .ap-table th:first-child { background:#084d3a; }
    .ap-table td { padding:10px; border-bottom:1px solid #ece8df; text-align:right; white-space:nowrap; background:#fff; }
    .ap-table tbody tr:hover td { background:#f8fbf9; }
    .ap-table tfoot td { font-weight:900; background:#f3f7f5; border-top:2px solid #b8d8cc; }
    .ap-info-action { background:#fff; color:#0b6b4f; border:1px solid #cfe3d8; }
    .ap-info-action:hover, .ap-info-action:focus { background:#eef9f5; outline:none; }
    .ap-modal-backdrop { position:fixed; inset:0; background:rgba(15,23,42,.38); z-index:1000; display:none; align-items:center; justify-content:center; padding:18px; }
    .ap-modal-backdrop.is-open { display:flex; }
    .ap-modal { width:min(680px, 100%); max-height:86vh; overflow:auto; background:#fff; border:1px solid #d9e5df; border-radius:14px; box-shadow:0 24px 60px rgba(15,23,42,.22); }
    .ap-modal-header { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:16px 18px; border-bottom:1px solid #e8eee9; }
    .ap-modal-header h3 { margin:0; font-size:18px; font-weight:900; color:#0d251d; }
    .ap-modal-close { width:34px; height:34px; border:1px solid #d8e5df; border-radius:10px; background:#f8fbf9; color:#0b6b4f; cursor:pointer; font-weight:900; }
    .ap-metric-list { display:grid; gap:10px; padding:16px 18px 18px; }
    .ap-metric-item { border:1px solid #e4ebe6; border-radius:10px; padding:10px 12px; background:#fbfdfc; }
    .ap-metric-item strong { display:block; margin-bottom:4px; color:#0b6b4f; }
    .ap-metric-item p { margin:0; color:#42524b; line-height:1.4; }
    .ap-success { background:#ecfdf5; color:#166534; border:1px solid #bbf7d0; padding:10px 12px; border-radius:10px; font-weight:700; }
    .ap-section-head { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-bottom:12px; }
    .ap-section-head h3 { margin:0; font-size:18px; font-weight:900; color:#10231d; }
    .ap-section-subtitle { margin:4px 0 0; color:#65736d; font-size:13px; line-height:1.4; }
    .ap-booking-grid { display:grid; gap:10px; margin-top:12px; }
    .ap-booking-card { border:1px solid #e2e1dc; border-radius:10px; padding:14px; display:grid; grid-template-columns:minmax(360px, 1fr) minmax(420px, .9fr); gap:16px; align-items:start; background:#fff; }
    .ap-booking-card:hover { border-color:#cfe3d8; box-shadow:0 8px 18px rgba(15,23,42,.04); }
    .ap-booking-title-row { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
    .ap-booking-title { font-weight:900; color:#111827; font-size:15px; line-height:1.25; }
    .ap-booking-pill { border:1px solid #d8ece4; background:#f0faf6; color:#0b6b4f; border-radius:999px; padding:3px 8px; font-size:11px; font-weight:800; }
    .ap-booking-meta { margin-top:10px; display:grid; grid-template-columns:repeat(3, minmax(110px, 1fr)); gap:8px; }
    .ap-booking-stat { border:1px solid #ece8df; border-radius:8px; padding:8px 10px; background:#fbfcfb; min-width:0; }
    .ap-booking-stat span { display:block; color:#66736d; font-size:10px; font-weight:900; text-transform:uppercase; letter-spacing:.04em; }
    .ap-booking-stat strong { display:block; margin-top:3px; color:#17211d; font-size:12px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .ap-booking-updated { margin-top:10px; color:#64706a; font-size:12px; display:flex; align-items:center; gap:6px; }
    .ap-revenue-form { display:grid; gap:8px; }
    .ap-revenue-row { display:grid; grid-template-columns:minmax(0, 1fr) auto; gap:8px; align-items:end; }
    .ap-revenue-field { display:grid; gap:5px; }
    .ap-revenue-field label { font-size:11px; font-weight:900; color:#5f6b66; text-transform:uppercase; letter-spacing:.04em; }
    .ap-revenue-form .ap-input { min-height:46px; }
    .ap-revenue-form textarea.ap-input { min-height:68px; resize:vertical; line-height:1.4; }
    .ap-note { color:#745b1f; background:#fffaf0; border:1px solid #e7dfc7; border-radius:10px; padding:10px 12px; font-size:13px; }
    @media (max-width: 1100px) { .ap-booking-card { grid-template-columns:1fr; } }
    @media (max-width: 820px) {
        #mainContent {
            height:100dvh !important;
            min-height:0 !important;
            overflow-y:auto !important;
            overflow-x:hidden !important;
            -webkit-overflow-scrolling:touch;
            overscroll-behavior-y:auto;
            touch-action:pan-y;
        }
        .ap-shell { min-width:0; padding-bottom:16px; }
        .ap-panel { min-width:0; padding:12px; border-radius:12px; }
        .ap-filters { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:8px; align-items:end; }
        .ap-field.ap-wide { grid-column:1 / -1; }
        .ap-field { min-width:0; }
        .ap-field label { font-size:10px; line-height:1; letter-spacing:.04em; }
        .ap-input { width:100%; min-width:0; min-height:42px; padding:9px 10px; border-radius:10px; font-size:13px; text-overflow:ellipsis; }
        .ap-btn { justify-content:center; width:100%; min-width:0; min-height:42px; padding:9px 10px; border-radius:10px; font-size:13px; }
        .ap-modal-backdrop { align-items:flex-end; padding:10px; }
        .ap-modal { max-height:90vh; border-radius:12px; }
        .ap-table-wrap {
            width:100%;
            max-width:100%;
            overflow-x:auto;
            overflow-y:hidden;
            overscroll-behavior-x:contain;
            touch-action:pan-x pan-y;
        }
        .ap-booking-card { min-width:0; padding:12px; }
        .ap-booking-meta { grid-template-columns:repeat(2, minmax(0, 1fr)); }
        .ap-revenue-row { grid-template-columns:minmax(0, 1fr) auto; }
    }
</style>
@endpush

@section('content')
@php
    $months = collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => \Carbon\Carbon::create(null, $m, 1)->format('F')]);
    $period = $report['period'];
    $periodQuery = $report['period_query'];
    $exportQuery = array_filter(array_merge($periodQuery, ['advisor_id' => $advisorId]), fn ($value) => $value !== null && $value !== '');
    $fmtMoney = fn ($value) => 'Rs ' . number_format((float) $value, 0);
    $fmtNum = fn ($value) => number_format((float) $value, 2);
    $fmtPct = fn ($value) => number_format(((float) $value) * 100, 2) . '%';
    $metricHelp = [
        'assigned-leads' => [
            'label' => 'Assigned Leads',
            'body' => 'Selected period me advisor ko assigned hui unique leads. Transfer case me same lead multiple advisor rows me count ho sakti hai.',
        ],
        'current-unique-leads' => [
            'label' => 'Current Unique Leads',
            'body' => 'Selected period me created leads me se jo lead currently active owner ke paas hai, wahi advisor row me count hoti hai. Ye All Leads page ke owner + created date filter ke saath match karega.',
        ],
        'visits' => [
            'label' => 'Visits',
            'body' => 'Selected period me completed site visits, assigned advisor basis par.',
        ],
        'f2f' => [
            'label' => 'F2F',
            'body' => 'Selected period me completed meetings, assigned advisor basis par.',
        ],
        'ps-percent' => [
            'label' => 'PS %',
            'body' => '(Visits + F2F) / Assigned Leads. Denominator workload wale Assigned Leads par based hai.',
        ],
        'pp-percent' => [
            'label' => 'PP %',
            'body' => 'Units / Assigned Leads. Denominator workload wale Assigned Leads par based hai.',
        ],
        'units' => [
            'label' => 'Units',
            'body' => 'Revenue-approved bookings count.',
        ],
        'revenue-value' => [
            'label' => 'Revenue Value',
            'body' => 'Finance-approved/verified booking revenue.',
        ],
        'ep' => [
            'label' => 'EP',
            'body' => '(Revenue Value - Justification) / Justification.',
        ],
    ];
@endphp

<div class="ap-shell">
    @if(session('success'))
        <div class="ap-success">{{ session('success') }}</div>
    @endif

    <section class="ap-panel">
        <form method="GET" action="{{ route('data-intelligence.advisor-performance') }}" class="ap-filters" id="advisorPeriodForm">
            <div class="ap-field" data-period-field="all">
                <label for="period_type">Period</label>
                <select id="period_type" name="period_type" class="ap-input">
                    <option value="month" @selected($period['period_type'] === 'month')>Monthly</option>
                    <option value="quarter" @selected($period['period_type'] === 'quarter')>Quarterly</option>
                    <option value="year" @selected($period['period_type'] === 'year')>Yearly</option>
                    <option value="till_date" @selected($period['period_type'] === 'till_date')>Till Date</option>
                    <option value="custom" @selected($period['period_type'] === 'custom')>Custom</option>
                </select>
            </div>
            <div class="ap-field" data-period-field="month">
                <label for="month">Month</label>
                <select id="month" name="month" class="ap-input">
                    @foreach($months as $monthNumber => $monthName)
                        <option value="{{ $monthNumber }}" @selected((int) $period['month'] === (int) $monthNumber)>{{ $monthName }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ap-field" data-period-field="month quarter year">
                <label for="year">Year</label>
                <input id="year" name="year" class="ap-input" type="number" min="2000" max="2100" value="{{ $period['year'] }}">
            </div>
            <div class="ap-field" data-period-field="quarter">
                <label for="quarter">Quarter</label>
                <select id="quarter" name="quarter" class="ap-input">
                    <option value="1" @selected((int) $period['quarter'] === 1)>Q1 Apr-Jun</option>
                    <option value="2" @selected((int) $period['quarter'] === 2)>Q2 Jul-Sep</option>
                    <option value="3" @selected((int) $period['quarter'] === 3)>Q3 Oct-Dec</option>
                    <option value="4" @selected((int) $period['quarter'] === 4)>Q4 Jan-Mar</option>
                </select>
            </div>
            <div class="ap-field" data-period-field="till_date">
                <label for="till_basis">Till Date From</label>
                <select id="till_basis" name="till_basis" class="ap-input">
                    <option value="financial_year" @selected($period['till_basis'] === 'financial_year')>Financial Year</option>
                    <option value="calendar_year" @selected($period['till_basis'] === 'calendar_year')>Calendar Year</option>
                    <option value="system_start" @selected($period['till_basis'] === 'system_start')>System Start</option>
                </select>
            </div>
            <div class="ap-field" data-period-field="custom">
                <label for="start_date">Date From</label>
                <input id="start_date" name="start_date" class="ap-input" type="date" value="{{ $period['start_date'] }}">
            </div>
            <div class="ap-field" data-period-field="custom">
                <label for="end_date">Date To</label>
                <input id="end_date" name="end_date" class="ap-input" type="date" value="{{ $period['end_date'] }}">
            </div>
            <div class="ap-field ap-wide" data-period-field="all">
                <label for="advisor_id">Advisor</label>
                <select id="advisor_id" name="advisor_id" class="ap-input">
                    <option value="">All advisors</option>
                    @foreach($report['advisors'] as $advisor)
                        <option value="{{ $advisor->id }}" @selected((int) $advisorId === (int) $advisor->id)>{{ $advisor->name }}</option>
                    @endforeach
                </select>
            </div>
            <button class="ap-btn primary" type="submit" aria-label="Apply filters" title="Apply filters"><i class="fas fa-filter"></i><span class="ap-btn-text">Apply</span></button>
            <a class="ap-btn soft" aria-label="Export Excel" title="Export Excel" href="{{ route('data-intelligence.advisor-performance.export', $exportQuery) }}">
                <i class="fas fa-file-excel"></i><span class="ap-btn-text">Export Excel</span>
            </a>
            <button class="ap-btn ap-info-action" type="button" id="metricLogicButton" aria-label="Metric Logic" title="Metric Logic">
                <i class="fas fa-info-circle"></i><span class="ap-btn-text">Metric Logic</span>
            </button>
        </form>
    </section>

    <section class="ap-panel">
        <div class="ap-table-wrap">
            <table class="ap-table">
                <thead>
                    <tr>
                        <th>Advisor</th>
                        <th>Assigned Leads</th>
                        <th>Current Unique Leads</th>
                        <th>Incentive</th>
                        <th>Visits</th>
                        <th>F2F</th>
                        <th>PS %</th>
                        <th>PP %</th>
                        <th>Units</th>
                        <th>TV (in Cr)</th>
                        <th>SqFt</th>
                        <th>Salary</th>
                        <th>Justification</th>
                        <th>Revenue Value</th>
                        <th>% Contributed</th>
                        <th>EP</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($report['rows'] as $row)
                        <tr>
                            <td>{{ $row['advisor'] }}</td>
                            <td>{{ number_format($row['leads']) }}</td>
                            <td>{{ number_format($row['current_unique_leads']) }}</td>
                            <td>{{ $fmtMoney($row['incentive']) }}</td>
                            <td>{{ number_format($row['visits']) }}</td>
                            <td>{{ number_format($row['f2f']) }}</td>
                            <td>{{ $fmtPct($row['ps_percent']) }}</td>
                            <td>{{ $fmtPct($row['pp_percent']) }}</td>
                            <td>{{ number_format($row['units']) }}</td>
                            <td>{{ $fmtNum($row['tv_in_cr']) }}</td>
                            <td>{{ number_format($row['sqft'], 0) }}</td>
                            <td>{{ $fmtMoney($row['salary']) }}</td>
                            <td>{{ $fmtMoney($row['justification']) }}</td>
                            <td>{{ $fmtMoney($row['revenue_value']) }}</td>
                            <td>{{ $fmtPct($row['contributed_percent']) }}</td>
                            <td>{{ $fmtPct($row['ep']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="16">No advisor activity found for this period.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        @php($total = $report['totals'])
                        <td>{{ $total['advisor'] }}</td>
                        <td>{{ number_format($total['leads']) }}</td>
                        <td>{{ number_format($total['current_unique_leads']) }}</td>
                        <td>{{ $fmtMoney($total['incentive']) }}</td>
                        <td>{{ number_format($total['visits']) }}</td>
                        <td>{{ number_format($total['f2f']) }}</td>
                        <td>{{ $fmtPct($total['ps_percent']) }}</td>
                        <td>{{ $fmtPct($total['pp_percent']) }}</td>
                        <td>{{ number_format($total['units']) }}</td>
                        <td>{{ $fmtNum($total['tv_in_cr']) }}</td>
                        <td>{{ number_format($total['sqft'], 0) }}</td>
                        <td>{{ $fmtMoney($total['salary']) }}</td>
                        <td>{{ $fmtMoney($total['justification']) }}</td>
                        <td>{{ $fmtMoney($total['revenue_value']) }}</td>
                        <td>{{ $fmtPct($total['contributed_percent']) }}</td>
                        <td>{{ $fmtPct($total['ep']) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>

    <div class="ap-modal-backdrop" id="metricLogicModal" aria-hidden="true">
        <div class="ap-modal" role="dialog" aria-modal="true" aria-labelledby="metricLogicTitle">
            <div class="ap-modal-header">
                <h3 id="metricLogicTitle">Metric Logic</h3>
                <button class="ap-modal-close" type="button" data-close-metric-modal aria-label="Close metric logic">x</button>
            </div>
            <div class="ap-metric-list">
                @foreach($metricHelp as $key => $item)
                    <div class="ap-metric-item" data-metric-item="{{ $key }}">
                        <strong>{{ $item['label'] }}</strong>
                        <p>{{ $item['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <section class="ap-panel">
        <div class="ap-section-head">
            <div>
                <h3>Revenue Booking Controls</h3>
                <p class="ap-section-subtitle">Finance-approved bookings ke revenue value aur note yahan update hote hain.</p>
            </div>
        </div>
        <div class="ap-note">Admin/Finance yahan se selected period ke finance-approved bookings ka revenue overwrite kar sakte hain. Har change audit mein save hota hai.</div>
        <div class="ap-booking-grid">
            @forelse($report['bookings'] as $booking)
                <div class="ap-booking-card">
                    <div>
                        <div class="ap-booking-title-row">
                            <div class="ap-booking-title">{{ $booking['customer'] }}</div>
                            <span class="ap-booking-pill">{{ $booking['advisor'] }}</span>
                        </div>
                        <div class="ap-booking-meta">
                            <div class="ap-booking-stat"><span>Project</span><strong>{{ $booking['project'] }}</strong></div>
                            <div class="ap-booking-stat"><span>Booking</span><strong>{{ $fmtMoney($booking['booking_value']) }}</strong></div>
                            <div class="ap-booking-stat"><span>SqFt</span><strong>{{ number_format($booking['sqft'], 0) }}</strong></div>
                            <div class="ap-booking-stat"><span>Finance</span><strong>{{ $booking['finance_reviewed_at'] ?: 'N/A' }}</strong></div>
                        </div>
                        @if(!empty($booking['updated_by']))
                            <div class="ap-booking-updated"><i class="fas fa-check-circle"></i><span>Revenue updated by {{ $booking['updated_by'] }}</span></div>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('data-intelligence.site-visits.revenue.update', $booking['id']) }}" class="ap-revenue-form">
                        @csrf
                        @foreach($periodQuery as $key => $value)
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endforeach
                        @if($advisorId)<input type="hidden" name="advisor_id" value="{{ $advisorId }}">@endif
                        <div class="ap-revenue-row">
                            <div class="ap-revenue-field">
                                <label for="revenue_value_{{ $booking['id'] }}">Revenue Value</label>
                                <input id="revenue_value_{{ $booking['id'] }}" class="ap-input" type="number" min="0" step="0.01" name="revenue_value" value="{{ $booking['revenue_value'] }}" required>
                            </div>
                            <button class="ap-btn primary" type="submit">Save</button>
                        </div>
                        <div class="ap-revenue-field">
                            <label for="revenue_note_{{ $booking['id'] }}">Revenue Note</label>
                            <textarea id="revenue_note_{{ $booking['id'] }}" class="ap-input" name="revenue_note" rows="2" placeholder="Revenue note">{{ $booking['revenue_note'] }}</textarea>
                        </div>
                    </form>
                </div>
            @empty
                <div class="ap-note">No finance-approved bookings found for this period.</div>
            @endforelse
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('advisorPeriodForm');
        if (!form) return;

        const periodSelect = form.querySelector('[name="period_type"]');
        const fields = form.querySelectorAll('[data-period-field]');

        function syncPeriodFields() {
            const selected = periodSelect.value || 'month';
            fields.forEach(function (field) {
                const modes = (field.dataset.periodField || '').split(' ');
                const visible = modes.includes('all') || modes.includes(selected);
                field.style.display = visible ? '' : 'none';
            });
        }

        periodSelect.addEventListener('change', syncPeriodFields);
        syncPeriodFields();

        const metricModal = document.getElementById('metricLogicModal');
        const metricButton = document.getElementById('metricLogicButton');
        const closeMetricButtons = document.querySelectorAll('[data-close-metric-modal]');

        function openMetricModal() {
            if (!metricModal) return;

            metricModal.classList.add('is-open');
            metricModal.setAttribute('aria-hidden', 'false');
        }

        function closeMetricModal() {
            if (!metricModal) return;

            metricModal.classList.remove('is-open');
            metricModal.setAttribute('aria-hidden', 'true');
        }

        if (metricButton) {
            metricButton.addEventListener('click', openMetricModal);
        }

        closeMetricButtons.forEach(function (button) {
            button.addEventListener('click', closeMetricModal);
        });

        if (metricModal) {
            metricModal.addEventListener('click', function (event) {
                if (event.target === metricModal) {
                    closeMetricModal();
                }
            });
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeMetricModal();
            }
        });
    });
</script>
@endpush

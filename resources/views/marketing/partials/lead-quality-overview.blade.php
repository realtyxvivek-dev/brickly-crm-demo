@php
    $report = $leadQualityReport ?? [];
    $leadQualityActionUrl = $leadQualityActionUrl ?? route('marketing.dashboard');
    $leadQualityResetUrl = $leadQualityResetUrl ?? route('marketing.dashboard', ['source' => 'all']);
    $filters = $report['filters'] ?? [];
    $summary = $report['summary'] ?? [
        'total' => 0,
        'interested' => 0,
        'not_interested' => 0,
        'junk' => 0,
        'cnp' => 0,
        'call_later' => 0,
        'good_leads' => 0,
        'poor_leads' => 0,
        'quality_score' => 0,
        'poor_quality_rate' => 0,
        'actioned_rate' => 0,
    ];
    $sourceBreakdown = collect($report['sourceBreakdown'] ?? []);
    $dailyTrend = collect($report['dailyTrend'] ?? []);
    $ownerBreakdown = collect($report['ownerBreakdown'] ?? [])
        ->reject(fn ($owner) => strcasecmp(trim((string) ($owner['owner'] ?? '')), 'Unassigned') === 0)
        ->values();
    $dailyTrendChartRows = $dailyTrend
        ->map(fn ($day) => [
            'date' => $day['date']->format('d M'),
            'total' => (int) ($day['total'] ?? 0),
            'interested' => (int) ($day['interested'] ?? 0),
            'not_interested' => (int) ($day['not_interested'] ?? 0),
            'junk' => (int) ($day['junk'] ?? 0),
            'cnp' => (int) ($day['cnp'] ?? 0),
            'call_later' => (int) ($day['call_later'] ?? $day['follow_up'] ?? 0),
            'pending' => (int) ($day['pending'] ?? 0),
        ])
        ->values();
    $ownerChartRows = $ownerBreakdown
        ->map(fn ($owner) => [
            'owner' => (string) ($owner['owner'] ?? 'Unassigned'),
            'total' => (int) ($owner['total'] ?? 0),
            'interested' => (int) ($owner['interested'] ?? 0),
            'not_interested' => (int) ($owner['not_interested'] ?? 0),
            'junk' => (int) ($owner['junk'] ?? 0),
            'cnp' => (int) ($owner['cnp'] ?? 0),
            'call_later' => (int) ($owner['call_later'] ?? 0),
            'quality_score' => (float) ($owner['quality_score'] ?? 0),
        ])
        ->values();
    $sourceOptions = $report['sourceOptions'] ?? [];
    $periodOptions = $report['periodOptions'] ?? [];
    $bucketOptions = $report['bucketOptions'] ?? [];
    $users = collect($report['users'] ?? []);
    $pct = function ($value, $total = null) use ($summary) {
        $total = $total ?? ($summary['total'] ?? 0);

        return $total > 0 ? round(((int) $value / $total) * 100, 1) : 0;
    };
    $sourceTotals = [
        'total' => $sourceBreakdown->sum('total'),
        'interested' => $sourceBreakdown->sum('interested'),
        'cnp' => $sourceBreakdown->sum('cnp'),
        'call_later' => $sourceBreakdown->sum('call_later'),
        'junk' => $sourceBreakdown->sum('junk'),
        'not_interested' => $sourceBreakdown->sum('not_interested'),
        'interested_follow_up' => $sourceBreakdown->sum('interested_follow_up'),
        'site_visit' => $sourceBreakdown->sum('site_visit'),
        'site_visit_followup' => $sourceBreakdown->sum('site_visit_followup'),
        'meeting' => $sourceBreakdown->sum('meeting'),
        'closed' => $sourceBreakdown->sum('closed'),
    ];
    $sourceTotals['interested_rate'] = ($sourceTotals['total'] ?? 0) > 0
        ? round(($sourceTotals['interested'] / $sourceTotals['total']) * 100, 1)
        : 0;
@endphp

@once
<style>
    .mql-panel{background:#fff;border:1px solid #c9d8cf;box-shadow:0 10px 24px rgba(6,58,28,.08);overflow:hidden;color:#0f172a;font-family:Arial,"Helvetica Neue",sans-serif;margin-bottom:22px}
    .mql-filter{display:flex;flex-wrap:wrap;gap:10px;align-items:end;padding:12px 18px;background:#f1f8f4;border-bottom:1px solid #c9d8cf}
    .mql-filter label{display:grid;gap:4px;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#345a46}
    .mql-input,.mql-btn{height:34px;border-radius:3px;border:1px solid #b7cabb;background:#fff;padding:0 10px;font-size:12px;color:#0f172a}
    .mql-input{min-width:150px}.mql-btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;font-weight:800;text-decoration:none;cursor:pointer}
    .mql-btn.primary{background:#0b6b34;border-color:#0b6b34;color:#fff}.mql-period{margin-left:auto;color:#486175;font-size:12px;padding-bottom:8px}
    .mql-kpis{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));border-bottom:1px solid #c9d8cf}.mql-kpi{padding:13px 16px;border-right:1px solid #d8e4dc;background:linear-gradient(180deg,#fff 0,#f7fbf8 100%)}.mql-kpi:last-child{border-right:0}
    .mql-kpi span{display:block;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#3f6651}.mql-kpi strong{display:block;margin-top:6px;font-size:25px;line-height:1.05;font-weight:900;color:#111827}.mql-kpi strong.good{color:#047857}.mql-kpi strong.bad{color:#b91c1c}.mql-kpi strong.warn{color:#b45309}.mql-kpi small{display:block;margin-top:5px;color:#64748b;font-size:12px}
    .mql-summary{display:grid;grid-template-columns:1fr 1fr;gap:0;padding:16px 18px;border-bottom:1px solid #c9d8cf}.mql-score{display:flex;align-items:center;gap:18px;min-width:0}.mql-ring{width:98px;height:98px;border-radius:999px;background:conic-gradient(#0f7a42 calc(var(--score)*1%),#d9e1ea 0);display:grid;place-items:center;flex:0 0 auto}.mql-ring span{width:70px;height:70px;border-radius:999px;background:#fff;display:grid;place-items:center;font-size:21px;font-weight:900;border:1px solid #dbe3ee}
    .mql-summary h2,.mql-section-head h2{margin:0;font-size:16px;font-weight:900}.mql-summary p,.mql-section-head p{margin:4px 0 0;color:#64748b;font-size:12px;line-height:1.45}.mql-badges{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px}
    .mql-badge,.mql-count{display:inline-flex;align-items:center;justify-content:center;border-radius:3px;padding:4px 8px;font-size:11px;font-weight:900;border:1px solid transparent;white-space:nowrap}.mql-count{min-width:34px;height:23px;padding:0 8px}
    .mql-badge.good,.mql-count.good{background:#e8f5e9;color:#0f6b35;border-color:#bfe3ca}.mql-badge.bad,.mql-count.bad{background:#fde7e9;color:#b4232e;border-color:#f5c2c7}.mql-badge.warn,.mql-count.warn{background:#fff4ce;color:#8a5600;border-color:#f4d781}.mql-badge.info,.mql-count.info{background:#e7f0ff;color:#1959b3;border-color:#c1d8ff}.mql-count.neutral{background:#edf2f7;color:#111827;border-color:#d9e1ea}
    .mql-note{border:1px solid #bfe3ca;border-left:5px solid #0f7a42;background:#f0fbf4;padding:12px;color:#14532d;font-size:12px;line-height:1.55}.mql-section-head{display:flex;justify-content:space-between;gap:14px;align-items:flex-start;padding:16px 18px 10px}.mql-legend{display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:flex-end}.mql-legend span{display:inline-flex;align-items:center;gap:6px;color:#53657a;font-size:11px;font-weight:800}.mql-legend i{width:8px;height:8px;border-radius:2px;display:inline-block}.mql-legend i.raw{background:#f4b183}.mql-legend i.pipe{background:#70ad47}.mql-legend i.bad{background:#d9534f}
    .mql-table-wrap{overflow-x:auto;padding:0 18px 16px}.mql-table{width:100%;min-width:1320px;border-collapse:separate;border-spacing:0;font-size:12px;line-height:1.35;border:1px solid #cfded5}.mql-table th{background:#eaf4ee;color:#183c2a;text-align:center;font-size:10px;text-transform:uppercase;letter-spacing:.06em;padding:9px 10px;border-right:1px solid #cfded5;border-bottom:1px solid #abc7b5;white-space:nowrap}.mql-table td{padding:9px 10px;border-right:1px solid #e1e7ef;border-bottom:1px solid #e1e7ef;text-align:center;background:#fff;vertical-align:middle}.mql-table th:first-child,.mql-table td:first-child{text-align:left}.mql-table .mql-source{position:sticky;left:0;z-index:1;background:#fff;box-shadow:5px 0 0 rgba(207,216,227,.45)}.mql-table thead .mql-source{background:#eaf4ee;z-index:3}.mql-group .raw{background:#fff3df;color:#7c3d00}.mql-group .pipe{background:#e6f3eb;color:#14532d}.mql-table tfoot td{background:#eaf4ee;font-weight:900;border-top:2px solid #0f7a42}.mql-empty{padding:22px!important;color:#64748b;text-align:center!important}
    .mql-trend{display:grid;grid-template-columns:1fr 1fr;gap:14px;padding:0 18px 18px}.mql-trend-card{border:1px solid #cfded5;background:#fff;min-width:0}.mql-trend-head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 12px;background:#eaf4ee;border-bottom:1px solid #abc7b5}.mql-trend-head h3{margin:0;font-size:12px;font-weight:900;text-transform:uppercase;letter-spacing:.06em;color:#183c2a}.mql-trend-head span{font-size:11px;font-weight:800;color:#53657a}.mql-trend-table{width:100%;border-collapse:collapse;font-size:12px}.mql-trend-table th{background:#f1f8f4;color:#183c2a;text-align:left;font-size:10px;text-transform:uppercase;letter-spacing:.06em;padding:9px;border-right:1px solid #cfded5;border-bottom:1px solid #cfded5;white-space:nowrap}.mql-trend-table td{padding:8px 9px;border-right:1px solid #e1e7ef;border-bottom:1px solid #e1e7ef;white-space:nowrap}.mql-trend-table td:nth-child(3){color:#047857}.mql-trend-table td:nth-child(4),.mql-trend-table td:nth-child(5){color:#b91c1c}.mql-trend-table td:nth-child(6){color:#b45309}.mql-trend-table td:nth-child(7),.mql-trend-table td:nth-child(8){color:#1d4ed8}.mql-chart-wrap{height:355px;padding:12px}.mql-series-toggles{display:flex;flex-wrap:wrap;gap:8px;padding:10px 12px 0}.mql-series-toggles label{display:inline-flex;align-items:center;gap:6px;border:1px solid #d8e4dc;background:#f8fbf9;border-radius:999px;padding:6px 9px;font-size:11px;font-weight:800;color:#334155;cursor:pointer}.mql-series-toggles input{accent-color:#0b6b34}
    .mql-owner-table td:nth-child(3){color:#047857}.mql-owner-table td:nth-child(4),.mql-owner-table td:nth-child(5){color:#b91c1c}.mql-owner-table td:nth-child(6){color:#b45309}.mql-owner-table td:nth-child(7){color:#1d4ed8}
    @media(max-width:960px){.mql-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.mql-summary{grid-template-columns:1fr;gap:14px}.mql-section-head{display:grid}}
    @media(max-width:960px){.mql-trend{grid-template-columns:1fr}.mql-chart-wrap{height:320px}}
    @media(max-width:640px){.mql-filter{display:grid}.mql-input,.mql-btn{width:100%;min-width:0}.mql-period{margin-left:0}.mql-kpis{grid-template-columns:1fr}.mql-kpi{border-right:0;border-bottom:1px solid #d8e4dc}.mql-score{align-items:flex-start}}
</style>
@endonce

<section id="marketing-lead-quality" class="mql-panel">
    <form method="GET" action="{{ $leadQualityActionUrl }}" class="mql-filter">
        <label>Source
            <select name="source" class="mql-input">
                @foreach($sourceOptions as $sourceKey => $sourceName)
                    <option value="{{ $sourceKey }}" @selected(($filters['source'] ?? 'all') === $sourceKey)>{{ $sourceName }}</option>
                @endforeach
            </select>
        </label>
        <label>Quick Period
            <select name="period" class="mql-input">
                @foreach($periodOptions as $periodKey => $periodLabel)
                    <option value="{{ $periodKey }}" @selected(($filters['period'] ?? 'month') === $periodKey)>{{ $periodLabel }}</option>
                @endforeach
            </select>
        </label>
        <label>Month
            <input type="month" name="month" value="{{ $filters['month'] ?? now()->format('Y-m') }}" class="mql-input">
        </label>
        <label>From
            <input type="date" name="from" value="{{ request('from') && isset($filters['from']) ? $filters['from']->toDateString() : '' }}" class="mql-input">
        </label>
        <label>To
            <input type="date" name="to" value="{{ request('to') && isset($filters['to']) ? $filters['to']->toDateString() : '' }}" class="mql-input">
        </label>
        <label>User
            <select name="user_id" class="mql-input">
                <option value="">All Users</option>
                @foreach($users as $user)
                    <option value="{{ $user->id }}" @selected((int) ($filters['user_id'] ?? 0) === (int) $user->id)>{{ $user->name }}</option>
                @endforeach
            </select>
        </label>
        <label>Outcome Bucket
            <select name="bucket" class="mql-input">
                <option value="">All Buckets</option>
                @foreach($bucketOptions as $bucketKey => $bucketLabel)
                    <option value="{{ $bucketKey }}" @selected(($filters['bucket'] ?? '') === $bucketKey)>{{ $bucketLabel }}</option>
                @endforeach
            </select>
        </label>
        <label>Evidence Rows
            <input type="number" name="sample_limit" min="5" max="100" value="{{ $filters['sample_limit'] ?? 25 }}" class="mql-input">
        </label>
        <button class="mql-btn primary" type="submit"><i class="fas fa-filter"></i> Generate Report</button>
        <a href="{{ $leadQualityResetUrl }}#marketing-lead-quality" class="mql-btn"><i class="fas fa-rotate-left"></i> Reset</a>
        <div class="mql-period">Period: <b>{{ isset($filters['from']) ? $filters['from']->format('d M Y') : now()->startOfMonth()->format('d M Y') }}</b> to <b>{{ isset($filters['to']) ? $filters['to']->format('d M Y') : now()->endOfMonth()->format('d M Y') }}</b></div>
    </form>

    <div class="mql-kpis">
        <div class="mql-kpi"><span>Total Leads</span><strong>{{ number_format($summary['total'] ?? 0) }}</strong><small>All source leads</small></div>
        <div class="mql-kpi"><span>Interested</span><strong class="good">{{ number_format($summary['interested'] ?? 0) }}</strong><small>{{ $pct($summary['interested'] ?? 0) }}% of total</small></div>
        <div class="mql-kpi"><span>Not Interested</span><strong class="bad">{{ number_format($summary['not_interested'] ?? 0) }}</strong><small>{{ $pct($summary['not_interested'] ?? 0) }}% of total</small></div>
        <div class="mql-kpi"><span>Junk / Invalid</span><strong class="bad">{{ number_format($summary['junk'] ?? 0) }}</strong><small>{{ $pct($summary['junk'] ?? 0) }}% of total</small></div>
        <div class="mql-kpi"><span>CNP</span><strong class="warn">{{ number_format($summary['cnp'] ?? 0) }}</strong><small>{{ $pct($summary['cnp'] ?? 0) }}% not reachable</small></div>
        <div class="mql-kpi"><span>Quality Score</span><strong class="good">{{ $summary['quality_score'] ?? 0 }}%</strong><small>Interested / qualified only</small></div>
    </div>

    <div class="mql-summary">
        <div class="mql-score">
            <div class="mql-ring" style="--score:{{ $summary['quality_score'] ?? 0 }}"><span>{{ $summary['quality_score'] ?? 0 }}%</span></div>
            <div>
                <h2>Lead Quality Summary</h2>
                <p>Total {{ number_format($summary['total'] ?? 0) }} leads me se {{ number_format($summary['good_leads'] ?? 0) }} usable leads hain, aur {{ number_format($summary['poor_leads'] ?? 0) }} leads vendor review/replacement bucket me aate hain.</p>
                <div class="mql-badges">
                    <span class="mql-badge good">Usable {{ $summary['quality_score'] ?? 0 }}%</span>
                    <span class="mql-badge bad">Poor {{ $summary['poor_quality_rate'] ?? 0 }}%</span>
                    <span class="mql-badge info">Actioned {{ $summary['actioned_rate'] ?? 0 }}%</span>
                </div>
            </div>
        </div>
        <div class="mql-note">
            <b>Vendor note:</b> This report is generated from CRM call outcomes submitted by users. Leads marked Not Interested, Junk/Invalid and repeated CNP should be treated as low-quality enquiries and reviewed for replacement or credit adjustment.
        </div>
    </div>

    <div class="mql-section-head">
        <div>
            <h2>Source Wise Quality</h2>
            <p>Interested % sirf Interested / Qualified leads par based hai; CNP, call later, junk aur not interested exclude hain.</p>
        </div>
        <div class="mql-legend">
            <span><i class="raw"></i> Raw outcome</span>
            <span><i class="pipe"></i> Interested pipeline</span>
            <span><i class="bad"></i> Low quality</span>
        </div>
    </div>

    <div class="mql-table-wrap">
        <table class="mql-table">
            <thead>
                <tr class="mql-group">
                    <th rowspan="2" class="mql-source">Source</th>
                    <th rowspan="2">Total</th>
                    <th colspan="2" class="pipe">Qualified</th>
                    <th colspan="4" class="raw">Raw Outcome</th>
                    <th colspan="5" class="pipe">Interested Pipeline</th>
                </tr>
                <tr>
                    <th>Interested</th><th>Rate</th><th>CNP</th><th>Call Later</th><th>Junk</th><th>Not Interested</th><th>Interested Follow-up</th><th>Site Visit</th><th>Visit Follow-up</th><th>Meeting</th><th>Closed</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sourceBreakdown as $row)
                    <tr>
                        <td class="mql-source"><b>{{ $row['source'] }}</b></td>
                        <td><span class="mql-count neutral">{{ number_format($row['total']) }}</span></td>
                        <td><span class="mql-count good">{{ number_format($row['interested']) }}</span></td>
                        <td><span class="mql-badge {{ $row['interested_rate'] >= 40 ? 'good' : 'warn' }}">{{ $row['interested_rate'] }}%</span></td>
                        <td><span class="mql-count warn">{{ number_format($row['cnp']) }}</span></td>
                        <td><span class="mql-count info">{{ number_format($row['call_later']) }}</span></td>
                        <td><span class="mql-count bad">{{ number_format($row['junk']) }}</span></td>
                        <td><span class="mql-count bad">{{ number_format($row['not_interested']) }}</span></td>
                        <td><span class="mql-count info">{{ number_format($row['interested_follow_up']) }}</span></td>
                        <td><span class="mql-count good">{{ number_format($row['site_visit']) }}</span></td>
                        <td><span class="mql-count info">{{ number_format($row['site_visit_followup']) }}</span></td>
                        <td><span class="mql-count good">{{ number_format($row['meeting']) }}</span></td>
                        <td><span class="mql-count good">{{ number_format($row['closed']) }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="13" class="mql-empty">No source-wise quality data found.</td></tr>
                @endforelse
            </tbody>
            @if($sourceBreakdown->isNotEmpty())
                <tfoot>
                    <tr>
                        <td class="mql-source">Total</td>
                        <td>{{ number_format($sourceTotals['total']) }}</td>
                        <td>{{ number_format($sourceTotals['interested']) }}</td>
                        <td><span class="mql-badge {{ $sourceTotals['interested_rate'] >= 40 ? 'good' : 'warn' }}">{{ $sourceTotals['interested_rate'] }}%</span></td>
                        <td>{{ number_format($sourceTotals['cnp']) }}</td>
                        <td>{{ number_format($sourceTotals['call_later']) }}</td>
                        <td>{{ number_format($sourceTotals['junk']) }}</td>
                        <td>{{ number_format($sourceTotals['not_interested']) }}</td>
                        <td>{{ number_format($sourceTotals['interested_follow_up']) }}</td>
                        <td>{{ number_format($sourceTotals['site_visit']) }}</td>
                        <td>{{ number_format($sourceTotals['site_visit_followup']) }}</td>
                        <td>{{ number_format($sourceTotals['meeting']) }}</td>
                        <td>{{ number_format($sourceTotals['closed']) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

    <div class="mql-section-head">
        <div>
            <h2>Daily Quality Trend</h2>
            <p>Day-wise received leads and selected outcome lines. Choose which lines should appear in the graph.</p>
        </div>
    </div>

    <div class="mql-trend">
        <div class="mql-trend-card">
            <div class="mql-trend-head">
                <h3>Daily Numbers</h3>
                <span>{{ $dailyTrend->count() }} days</span>
            </div>
            <div style="overflow:auto;max-height:420px;">
                <table class="mql-trend-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Total</th>
                            <th>Interested</th>
                            <th>Not Interested</th>
                            <th>Junk</th>
                            <th>CNP</th>
                            <th>Call Later</th>
                            <th>Pending</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($dailyTrend as $day)
                            <tr>
                                <td>{{ $day['date']->format('d M Y') }}</td>
                                <td><b>{{ number_format($day['total'] ?? 0) }}</b></td>
                                <td>{{ number_format($day['interested'] ?? 0) }}</td>
                                <td>{{ number_format($day['not_interested'] ?? 0) }}</td>
                                <td>{{ number_format($day['junk'] ?? 0) }}</td>
                                <td>{{ number_format($day['cnp'] ?? 0) }}</td>
                                <td>{{ number_format($day['call_later'] ?? $day['follow_up'] ?? 0) }}</td>
                                <td>{{ number_format($day['pending'] ?? 0) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="mql-empty">No daily trend data found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mql-trend-card">
            <div class="mql-trend-head">
                <h3>Line Graph</h3>
                <span>Selectable metrics</span>
            </div>
            <div class="mql-series-toggles" data-chart-toggles="mqlDailyQualityChart">
                <label><input type="checkbox" value="total" checked> Total</label>
                <label><input type="checkbox" value="interested" checked> Interested</label>
                <label><input type="checkbox" value="not_interested" checked> Not Interested</label>
                <label><input type="checkbox" value="junk" checked> Junk</label>
                <label><input type="checkbox" value="cnp" checked> CNP</label>
                <label><input type="checkbox" value="call_later"> Call Later</label>
                <label><input type="checkbox" value="pending"> Pending</label>
            </div>
            <div class="mql-chart-wrap">
                <canvas id="mqlDailyQualityChart"></canvas>
            </div>
        </div>
    </div>

    <div class="mql-section-head">
        <div>
            <h2>User Outcome Performance</h2>
            <p>Assigned user wise lead quality summary with selectable graph lines.</p>
        </div>
    </div>

    <div class="mql-trend">
        <div class="mql-trend-card">
            <div class="mql-trend-head">
                <h3>User Outcomes</h3>
                <span>{{ $ownerBreakdown->count() }} users</span>
            </div>
            <div style="overflow:auto;max-height:520px;">
                <table class="mql-trend-table mql-owner-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Total</th>
                            <th>Interested</th>
                            <th>Not Interested</th>
                            <th>Junk</th>
                            <th>CNP</th>
                            <th>Call Later</th>
                            <th>Quality Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($ownerBreakdown as $owner)
                            <tr>
                                <td><b>{{ $owner['owner'] }}</b></td>
                                <td>{{ number_format($owner['total'] ?? 0) }}</td>
                                <td>{{ number_format($owner['interested'] ?? 0) }}</td>
                                <td>{{ number_format($owner['not_interested'] ?? 0) }}</td>
                                <td>{{ number_format($owner['junk'] ?? 0) }}</td>
                                <td>{{ number_format($owner['cnp'] ?? 0) }}</td>
                                <td>{{ number_format($owner['call_later'] ?? 0) }}</td>
                                <td><span class="mql-badge {{ ($owner['quality_score'] ?? 0) >= 50 ? 'good' : 'warn' }}">{{ $owner['quality_score'] ?? 0 }}%</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="mql-empty">No assigned-user data found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mql-trend-card">
            <div class="mql-trend-head">
                <h3>User Graph</h3>
                <span>Selectable metrics</span>
            </div>
            <div class="mql-series-toggles" data-chart-toggles="mqlUserOutcomeChart">
                <label><input type="checkbox" value="total" checked> Total</label>
                <label><input type="checkbox" value="interested" checked> Interested</label>
                <label><input type="checkbox" value="not_interested" checked> Not Interested</label>
                <label><input type="checkbox" value="junk"> Junk</label>
                <label><input type="checkbox" value="cnp" checked> CNP</label>
                <label><input type="checkbox" value="call_later"> Call Later</label>
                <label><input type="checkbox" value="quality_score"> Quality Score</label>
            </div>
            <div class="mql-chart-wrap">
                <canvas id="mqlUserOutcomeChart"></canvas>
            </div>
        </div>
    </div>
</section>

@once
<script>
document.addEventListener('DOMContentLoaded', function () {
    const canvas = document.getElementById('mqlDailyQualityChart');
    if (!canvas || typeof Chart === 'undefined') return;

    const trendRows = @json($dailyTrendChartRows);

    const labels = trendRows.map((row) => row.date);
    const metricConfig = {
        total: { label: 'Total Leads', color: '#2563eb' },
        interested: { label: 'Interested', color: '#047857' },
        not_interested: { label: 'Not Interested', color: '#dc2626' },
        junk: { label: 'Junk', color: '#f97316' },
        cnp: { label: 'CNP', color: '#b45309' },
        call_later: { label: 'Call Later', color: '#7c3aed' },
        pending: { label: 'Pending', color: '#64748b' },
    };
    const toggles = document.querySelectorAll('[data-chart-toggles="mqlDailyQualityChart"] input');
    const checkedKeys = () => Array.from(toggles).filter((input) => input.checked).map((input) => input.value);
    const buildDataset = (key) => ({
        label: metricConfig[key].label,
        data: trendRows.map((row) => row[key] || 0),
        borderColor: metricConfig[key].color,
        backgroundColor: metricConfig[key].color + '22',
        pointBackgroundColor: metricConfig[key].color,
        borderWidth: 2,
        tension: 0.35,
        fill: false,
    });

    const chart = new Chart(canvas, {
        type: 'line',
        data: { labels, datasets: checkedKeys().map(buildDataset) },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'bottom' },
                tooltip: { mode: 'index', intersect: false },
            },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 } },
                x: { grid: { display: false } },
            },
        },
    });

    toggles.forEach((input) => {
        input.addEventListener('change', function () {
            chart.data.datasets = checkedKeys().map(buildDataset);
            chart.update();
        });
    });

    const userCanvas = document.getElementById('mqlUserOutcomeChart');
    if (!userCanvas) return;

    const ownerRows = @json($ownerChartRows);
    const ownerLabels = ownerRows.map((row) => row.owner);
    const ownerMetricConfig = {
        total: { label: 'Total', color: '#2563eb' },
        interested: { label: 'Interested', color: '#047857' },
        not_interested: { label: 'Not Interested', color: '#dc2626' },
        junk: { label: 'Junk', color: '#f97316' },
        cnp: { label: 'CNP', color: '#b45309' },
        call_later: { label: 'Call Later', color: '#7c3aed' },
        quality_score: { label: 'Quality Score %', color: '#0f766e' },
    };
    const userToggles = document.querySelectorAll('[data-chart-toggles="mqlUserOutcomeChart"] input');
    const checkedOwnerKeys = () => Array.from(userToggles).filter((input) => input.checked).map((input) => input.value);
    const buildOwnerDataset = (key) => ({
        label: ownerMetricConfig[key].label,
        data: ownerRows.map((row) => row[key] || 0),
        borderColor: ownerMetricConfig[key].color,
        backgroundColor: ownerMetricConfig[key].color + '22',
        pointBackgroundColor: ownerMetricConfig[key].color,
        borderWidth: 2,
        tension: 0.35,
        fill: false,
    });

    const userChart = new Chart(userCanvas, {
        type: 'line',
        data: { labels: ownerLabels, datasets: checkedOwnerKeys().map(buildOwnerDataset) },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'bottom' },
                tooltip: { mode: 'index', intersect: false },
            },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 } },
                x: { grid: { display: false }, ticks: { maxRotation: 60, minRotation: 0 } },
            },
        },
    });

    userToggles.forEach((input) => {
        input.addEventListener('change', function () {
            userChart.data.datasets = checkedOwnerKeys().map(buildOwnerDataset);
            userChart.update();
        });
    });
});
</script>
@endonce

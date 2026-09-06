@extends('layouts.app')

@section('title', '99acres Lead Quality Report')
@section('page-title', '99acres Lead Quality Report')

@section('content')
@php
    $pct = function ($value, $total = null) use ($summary) {
        $total = $total ?? $summary['total'];

        return $total > 0 ? round(($value / $total) * 100, 1) : 0;
    };
    $fmtDate = fn ($date) => optional($date)->format('d M Y, h:i A') ?: '-';
    $bucketClass = [
        'interested' => 'good',
        'not_interested' => 'bad',
        'junk' => 'bad',
        'cnp' => 'warn',
        'follow_up' => 'info',
        'pending' => 'muted',
    ];
@endphp

<style>
    .ql-page{background:#f6f8fb;min-height:100vh;margin:-1.5rem;padding:1.5rem;color:#0f172a}
    .ql-shell{max-width:1420px;margin:0 auto}
    .ql-hero{background:linear-gradient(135deg,#063a1c,#205a44);border-radius:22px;color:#fff;padding:28px;box-shadow:0 18px 45px rgba(6,58,28,.18)}
    .ql-hero h1{font-size:30px;font-weight:900;margin:0;letter-spacing:0}
    .ql-hero p{margin:8px 0 0;color:rgba(255,255,255,.82);font-size:14px;line-height:1.6}
    .ql-actions{display:flex;flex-wrap:wrap;gap:10px;align-items:center}
    .ql-btn,.ql-input{height:42px;border-radius:12px;border:1px solid #dbe3ef;background:#fff;padding:0 12px;font-size:13px}
    .ql-btn{display:inline-flex;align-items:center;gap:8px;font-weight:800;cursor:pointer;text-decoration:none}
    .ql-btn.primary{background:#0b6b34;border-color:#0b6b34;color:#fff}
    .ql-btn.light{background:rgba(255,255,255,.14);border-color:rgba(255,255,255,.24);color:#fff}
    .ql-panel{background:#fff;border:1px solid #e3e8f0;border-radius:18px;box-shadow:0 10px 26px rgba(15,23,42,.06)}
    .ql-filter{margin-top:16px;padding:16px;display:flex;flex-wrap:wrap;gap:12px;align-items:end}
    .ql-filter label{display:grid;gap:6px;font-size:11px;font-weight:900;text-transform:uppercase;letter-spacing:.12em;color:#64748b}
    .ql-grid{display:grid;gap:14px;margin-top:16px}
    .ql-grid.kpi{grid-template-columns:repeat(6,minmax(0,1fr))}
    .ql-card{padding:18px}
    .ql-kpi-label{font-size:11px;font-weight:900;text-transform:uppercase;letter-spacing:.12em;color:#64748b}
    .ql-kpi-value{font-size:28px;font-weight:950;margin-top:6px}
    .ql-kpi-sub{font-size:12px;color:#64748b;margin-top:4px}
    .ql-score{display:flex;align-items:center;gap:18px}
    .ql-ring{width:116px;height:116px;border-radius:999px;background:conic-gradient(#0b6b34 calc(var(--score)*1%),#e5e7eb 0);display:grid;place-items:center}
    .ql-ring span{width:82px;height:82px;border-radius:999px;background:#fff;display:grid;place-items:center;font-size:24px;font-weight:950}
    .ql-section{margin-top:16px;padding:18px}
    .ql-section-head{display:flex;justify-content:space-between;gap:14px;align-items:flex-start;margin-bottom:14px}
    .ql-section h2{margin:0;font-size:18px;font-weight:950}
    .ql-section p{margin:5px 0 0;color:#64748b;font-size:13px;line-height:1.5}
    .ql-two{display:grid;grid-template-columns:1fr 1fr;gap:16px}
    .ql-table{width:100%;border-collapse:collapse;font-size:13px}
    .ql-table th{background:#f8fafc;color:#475569;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.1em;padding:12px;border-bottom:1px solid #e5e7eb}
    .ql-table td{padding:12px;border-bottom:1px solid #eef2f7;vertical-align:top}
    .ql-table tr:last-child td{border-bottom:0}
    .ql-badge{display:inline-flex;border-radius:999px;padding:5px 10px;font-size:11px;font-weight:900}
    .ql-badge.good{background:#dcfce7;color:#166534}
    .ql-badge.bad{background:#fee2e2;color:#991b1b}
    .ql-badge.warn{background:#fef3c7;color:#92400e}
    .ql-badge.info{background:#dbeafe;color:#1d4ed8}
    .ql-badge.muted{background:#f1f5f9;color:#475569}
    .ql-bar{height:10px;border-radius:99px;background:#e5e7eb;overflow:hidden;min-width:90px}
    .ql-bar span{display:block;height:100%;border-radius:99px;background:#0b6b34;width:var(--w)}
    .ql-bar.bad span{background:#dc2626}.ql-bar.warn span{background:#d97706}.ql-bar.info span{background:#2563eb}
    .ql-note{border-left:4px solid #0b6b34;background:#f0fdf4;border-radius:14px;padding:14px;color:#14532d;font-size:13px;line-height:1.6}
    .ql-list{margin:0;padding-left:18px;color:#334155;font-size:13px;line-height:1.75}
    .ql-print-only{display:none}
    @media(max-width:1100px){.ql-grid.kpi{grid-template-columns:repeat(2,minmax(0,1fr))}.ql-two{grid-template-columns:1fr}}
    @media(max-width:640px){.ql-page{margin:-1rem;padding:1rem}.ql-grid.kpi{grid-template-columns:1fr}.ql-hero h1{font-size:24px}.ql-actions,.ql-filter{display:grid}.ql-btn,.ql-input{width:100%}}
    @media print{
        body{background:#fff!important}.sidebar,.topbar,.ql-filter,.ql-no-print{display:none!important}
        .ql-page{margin:0;padding:0;background:#fff}.ql-shell{max-width:none}.ql-panel,.ql-hero{box-shadow:none;break-inside:avoid}
        .ql-print-only{display:block}.ql-grid.kpi{grid-template-columns:repeat(3,1fr)}
        a[href]:after{content:""}
    }
</style>

<div class="ql-page">
    <div class="ql-shell">
        <section class="ql-hero">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <div class="text-xs font-black uppercase tracking-[0.18em] text-emerald-100">Vendor Evidence Report</div>
                    <h1>99acres Lead Quality Report</h1>
                    <p>
                        CRM me user outcomes ke basis par lead quality analysis:
                        Interested, Not Interested, Junk/Invalid, CNP aur pending leads ka breakdown.
                    </p>
                    <p class="ql-print-only">Generated on {{ $generatedAt->format('d M Y, h:i A') }}</p>
                </div>
                <div class="ql-actions ql-no-print">
                    <button type="button" onclick="window.print()" class="ql-btn light"><i class="fas fa-print"></i> Print / Save PDF</button>
                    <a href="{{ route('integrations.99acres.index') }}" class="ql-btn light"><i class="fas fa-plug"></i> 99acres Setup</a>
                </div>
            </div>
        </section>

        <form method="GET" class="ql-panel ql-filter ql-no-print">
            <label>From
                <input type="date" name="from" value="{{ $from->toDateString() }}" class="ql-input">
            </label>
            <label>To
                <input type="date" name="to" value="{{ $to->toDateString() }}" class="ql-input">
            </label>
            <label>Evidence Rows
                <input type="number" name="sample_limit" min="5" max="100" value="{{ $sampleLimit }}" class="ql-input">
            </label>
            <button class="ql-btn primary" type="submit"><i class="fas fa-filter"></i> Generate Report</button>
            <div class="ml-auto text-sm text-slate-500">Period: <b>{{ $from->format('d M Y') }}</b> to <b>{{ $to->format('d M Y') }}</b></div>
        </form>

        <div class="ql-grid kpi">
            <div class="ql-panel ql-card">
                <div class="ql-kpi-label">Total Leads</div>
                <div class="ql-kpi-value">{{ number_format($summary['total']) }}</div>
                <div class="ql-kpi-sub">99acres source leads</div>
            </div>
            <div class="ql-panel ql-card">
                <div class="ql-kpi-label">Interested</div>
                <div class="ql-kpi-value text-emerald-700">{{ number_format($summary['interested']) }}</div>
                <div class="ql-kpi-sub">{{ $pct($summary['interested']) }}% of total</div>
            </div>
            <div class="ql-panel ql-card">
                <div class="ql-kpi-label">Not Interested</div>
                <div class="ql-kpi-value text-red-700">{{ number_format($summary['not_interested']) }}</div>
                <div class="ql-kpi-sub">{{ $pct($summary['not_interested']) }}% of total</div>
            </div>
            <div class="ql-panel ql-card">
                <div class="ql-kpi-label">Junk / Invalid</div>
                <div class="ql-kpi-value text-red-700">{{ number_format($summary['junk']) }}</div>
                <div class="ql-kpi-sub">{{ $pct($summary['junk']) }}% of total</div>
            </div>
            <div class="ql-panel ql-card">
                <div class="ql-kpi-label">CNP</div>
                <div class="ql-kpi-value text-amber-700">{{ number_format($summary['cnp']) }}</div>
                <div class="ql-kpi-sub">{{ $pct($summary['cnp']) }}% not reachable</div>
            </div>
            <div class="ql-panel ql-card">
                <div class="ql-kpi-label">Quality Score</div>
                <div class="ql-kpi-value text-emerald-800">{{ $summary['quality_score'] }}%</div>
                <div class="ql-kpi-sub">Interested + follow-up</div>
            </div>
        </div>

        <section class="ql-panel ql-section">
            <div class="ql-two">
                <div class="ql-score">
                    <div class="ql-ring" style="--score:{{ $summary['quality_score'] }}"><span>{{ $summary['quality_score'] }}%</span></div>
                    <div>
                        <h2>Lead Quality Summary</h2>
                        <p>Total {{ number_format($summary['total']) }} leads me se {{ number_format($summary['good_leads']) }} usable leads hain, aur {{ number_format($summary['poor_leads']) }} leads vendor review/replacement bucket me aate hain.</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <span class="ql-badge good">Usable {{ $summary['quality_score'] }}%</span>
                            <span class="ql-badge bad">Poor {{ $summary['poor_quality_rate'] }}%</span>
                            <span class="ql-badge info">Actioned {{ $summary['actioned_rate'] }}%</span>
                        </div>
                    </div>
                </div>
                <div class="ql-note">
                    <b>Vendor note:</b> This report is generated from CRM call outcomes submitted by users. Leads marked Not Interested, Junk/Invalid and repeated CNP should be treated as low-quality enquiries and reviewed for replacement or credit adjustment.
                </div>
            </div>
        </section>

        <div class="ql-two">
            <section class="ql-panel ql-section">
                <div class="ql-section-head">
                    <div><h2>Outcome Breakdown</h2><p>User submitted outcomes from telecalling/calling tasks.</p></div>
                </div>
                <table class="ql-table">
                    <thead><tr><th>Outcome</th><th>Count</th><th>%</th><th>Share</th></tr></thead>
                    <tbody>
                    @forelse($outcomes as $label => $count)
                        <tr>
                            <td>{{ $label }}</td>
                            <td><b>{{ number_format($count) }}</b></td>
                            <td>{{ $pct($count) }}%</td>
                            <td><div class="ql-bar"><span style="--w:{{ $pct($count) }}%"></span></div></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-slate-500">No outcome data found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </section>

            <section class="ql-panel ql-section">
                <div class="ql-section-head">
                    <div><h2>CRM Status Breakdown</h2><p>Current lead status in CRM.</p></div>
                </div>
                <table class="ql-table">
                    <thead><tr><th>Status</th><th>Count</th><th>%</th><th>Share</th></tr></thead>
                    <tbody>
                    @forelse($statusBreakdown as $label => $count)
                        <tr>
                            <td>{{ $label }}</td>
                            <td><b>{{ number_format($count) }}</b></td>
                            <td>{{ $pct($count) }}%</td>
                            <td><div class="ql-bar info"><span style="--w:{{ $pct($count) }}%"></span></div></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-slate-500">No status data found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </section>
        </div>

        <section class="ql-panel ql-section">
            <div class="ql-section-head">
                <div><h2>Daily Quality Trend</h2><p>Day-wise received leads and low quality buckets.</p></div>
            </div>
            <table class="ql-table">
                <thead><tr><th>Date</th><th>Total</th><th>Interested</th><th>Not Interested</th><th>Junk</th><th>CNP</th><th>Follow-up</th><th>Pending</th></tr></thead>
                <tbody>
                @foreach($dailyTrend as $day)
                    <tr>
                        <td>{{ $day['date']->format('d M Y') }}</td>
                        <td><b>{{ $day['total'] }}</b></td>
                        <td class="text-emerald-700">{{ $day['interested'] }}</td>
                        <td class="text-red-700">{{ $day['not_interested'] }}</td>
                        <td class="text-red-700">{{ $day['junk'] }}</td>
                        <td class="text-amber-700">{{ $day['cnp'] }}</td>
                        <td class="text-blue-700">{{ $day['follow_up'] }}</td>
                        <td class="text-slate-500">{{ $day['pending'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </section>

        <section class="ql-panel ql-section">
            <div class="ql-section-head">
                <div><h2>User Outcome Performance</h2><p>Assigned user wise lead quality summary.</p></div>
            </div>
            <table class="ql-table">
                <thead><tr><th>User</th><th>Total</th><th>Interested</th><th>Not Interested</th><th>Junk</th><th>CNP</th><th>Quality Score</th></tr></thead>
                <tbody>
                @forelse($ownerBreakdown as $owner)
                    <tr>
                        <td><b>{{ $owner['owner'] }}</b></td>
                        <td>{{ $owner['total'] }}</td>
                        <td class="text-emerald-700">{{ $owner['interested'] }}</td>
                        <td class="text-red-700">{{ $owner['not_interested'] }}</td>
                        <td class="text-red-700">{{ $owner['junk'] }}</td>
                        <td class="text-amber-700">{{ $owner['cnp'] }}</td>
                        <td><span class="ql-badge {{ $owner['quality_score'] >= 50 ? 'good' : 'warn' }}">{{ $owner['quality_score'] }}%</span></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-slate-500">No assigned-user data found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>

        @foreach([
            'interested' => 'Interested / Qualified Evidence',
            'not_interested' => 'Not Interested Evidence',
            'junk' => 'Junk / Invalid Evidence',
            'cnp' => 'CNP / Not Reachable Evidence',
        ] as $bucket => $title)
            <section class="ql-panel ql-section">
                <div class="ql-section-head">
                    <div><h2>{{ $title }}</h2><p>Sample rows for vendor review. Increase evidence rows filter if more examples are needed.</p></div>
                    <span class="ql-badge {{ $bucketClass[$bucket] ?? 'muted' }}">{{ $samples[$bucket]->count() }} rows</span>
                </div>
                <table class="ql-table">
                    <thead>
                        <tr>
                            <th>Lead</th><th>Phone</th><th>Created</th><th>User</th><th>Status</th><th>Outcome</th><th>Remark / Evidence</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($samples[$bucket] as $row)
                        @php($lead = $row['lead'])
                        <tr>
                            <td><b>{{ $lead->name }}</b><div class="text-xs text-slate-500">ID {{ $lead->id }}</div></td>
                            <td>{{ $lead->phone }}</td>
                            <td>{{ $lead->created_at->format('d M Y, h:i A') }}</td>
                            <td>{{ $row['owner'] }}</td>
                            <td>{{ $row['status_label'] }}</td>
                            <td><span class="ql-badge {{ $bucketClass[$row['bucket']] ?? 'muted' }}">{{ $row['outcome_label'] }}</span></td>
                            <td>{{ $row['remarks'] ?: 'No remark captured' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-slate-500">No rows in this bucket.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </section>
        @endforeach

        <section class="ql-panel ql-section">
            <div class="ql-section-head">
                <div><h2>Recommendation for 99acres</h2><p>Points that can be shared with vendor for corrective action.</p></div>
            </div>
            <ul class="ql-list">
                @foreach($recommendations as $recommendation)
                    <li>{{ $recommendation }}</li>
                @endforeach
            </ul>
        </section>
    </div>
</div>
@endsection

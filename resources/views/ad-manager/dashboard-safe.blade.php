@extends('layouts.app')

@section('title', 'Ad Manager Dashboard - ' . brand_name())
@section('page-title', 'Ad Manager Dashboard')
@section('page-subtitle', 'Meta lead quality, Meta health and attendance.')

@section('content')
@php
    $pct = function ($value, $total = null) use ($summary) {
        $total = $total ?? ($summary['total'] ?? 0);
        return $total > 0 ? round(($value / $total) * 100, 1) : 0;
    };
    $attendanceConfigured = (bool) data_get($attendanceToday, 'configured', false);
    $attendanceRecord = data_get($attendanceToday, 'record', []);
@endphp

<style>
    .am-page{margin:-1.5rem;background:#eef5f0;min-height:100vh;padding:22px;color:#0f172a}
    .am-shell{background:#fff;border:1px solid #cbded1;box-shadow:0 14px 30px rgba(6,58,28,.1)}
    .am-hero{background:linear-gradient(135deg,#052e1d,#0b6b34);color:#fff;padding:18px 20px}
    .am-hero h1{font-size:26px;font-weight:800;margin:4px 0}
    .am-hero p{font-size:13px;color:#e7f6ec;margin:0}
    .am-filter{display:flex;flex-wrap:wrap;gap:10px;align-items:end;padding:14px 20px;background:#f4faf6;border-bottom:1px solid #cbded1}
    .am-filter label{display:grid;gap:5px;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#345a46}
    .am-input,.am-btn{height:36px;border:1px solid #b7cabb;border-radius:4px;background:#fff;padding:0 11px;font-size:13px;color:#0f172a}
    .am-btn{display:inline-flex;align-items:center;gap:7px;font-weight:800;text-decoration:none;cursor:pointer}
    .am-btn.primary{background:#0b6b34;border-color:#0b6b34;color:#fff}
    .am-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));border-bottom:1px solid #cbded1}
    .am-card{padding:15px 16px;border-right:1px solid #dbe7df;background:linear-gradient(180deg,#fff,#f8fbf9)}
    .am-card:last-child{border-right:0}
    .am-label{font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#3f6651}
    .am-value{font-size:25px;font-weight:800;margin-top:7px}
    .am-sub{font-size:12px;color:#64748b;margin-top:4px}
    .am-two{display:grid;grid-template-columns:1.1fr .9fr;border-bottom:1px solid #cbded1}
    .am-section{padding:18px 20px;overflow-x:auto}
    .am-two>.am-section:first-child{border-right:1px solid #cbded1}
    .am-section h2{font-size:17px;font-weight:800;margin:0 0 4px}
    .am-section p{font-size:12px;color:#64748b;margin:0 0 12px}
    .am-table{width:100%;border-collapse:collapse;font-size:12px;border:1px solid #d7e3dc}
    .am-table th{background:#eaf4ee;text-align:left;font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:#183c2a;padding:9px;border-bottom:1px solid #b7c9bd}
    .am-table td{padding:9px;border-bottom:1px solid #e5ece8}
    .am-pill{display:inline-flex;border-radius:999px;padding:6px 9px;font-size:11px;font-weight:800;border:1px solid transparent}
    .am-pill.good{background:#e8f5e9;color:#0f6b35;border-color:#bfe3ca}
    .am-pill.warn{background:#fff4ce;color:#8a5600;border-color:#f4d781}
    .am-pill.bad{background:#fde7e9;color:#b4232e;border-color:#f5c2c7}
    .am-att{background:#083344;color:#fff;border-radius:8px;padding:16px}
    .am-att p{color:#cde7f3}
    .am-att-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:12px}
    .am-att-stat{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.14);border-radius:6px;padding:12px}
    .am-actions{display:flex;gap:10px;margin-top:12px}
    .am-actions button{border:0;border-radius:6px;padding:10px 14px;font-weight:800;cursor:pointer}
    .am-actions .primary{background:#fff;color:#083344}
    .am-actions .secondary{background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.18)}
    @media(max-width:1100px){.am-grid{grid-template-columns:repeat(2,1fr)}.am-two{grid-template-columns:1fr}.am-two>.am-section:first-child{border-right:0;border-bottom:1px solid #cbded1}}
    @media(max-width:640px){.am-page{margin:-1rem;padding:12px}.am-grid{grid-template-columns:1fr}.am-filter{display:grid}.am-input,.am-btn{width:100%}.am-att-grid{grid-template-columns:1fr}}
</style>

<div class="am-page">
    <div class="am-shell">
        <section class="am-hero">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <div class="text-xs font-black uppercase tracking-[0.18em] text-emerald-100">Ad Manager</div>
                    <h1>{{ $sourceLabel ?? 'Meta' }} Lead Quality Report</h1>
                    <p>Same Data Intelligent report sections, Meta health status and self attendance in one dashboard.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('ad-manager.meta.index') }}" class="am-btn">Meta Ops</a>
                    <a href="{{ route('ad-manager.meta.facebook-lead-ads.diagnostics') }}" class="am-btn">Diagnostics</a>
                </div>
            </div>
        </section>

        <form method="GET" action="{{ route('ad-manager.dashboard') }}" class="am-filter">
            <label>Source
                <select name="source" class="am-input">
                    @foreach(($sourceOptions ?? []) as $sourceKey => $sourceName)
                        <option value="{{ $sourceKey }}" @selected(($filters['source'] ?? '') === $sourceKey)>{{ $sourceName }}</option>
                    @endforeach
                </select>
            </label>
            <label>Month
                <input type="month" name="month" value="{{ $filters['month'] ?? now()->format('Y-m') }}" class="am-input">
            </label>
            <label>Outcome Bucket
                <select name="bucket" class="am-input">
                    <option value="">All Buckets</option>
                    @foreach(($bucketOptions ?? []) as $bucketKey => $bucketLabel)
                        <option value="{{ $bucketKey }}" @selected(($filters['bucket'] ?? '') === $bucketKey)>{{ $bucketLabel }}</option>
                    @endforeach
                </select>
            </label>
            <button class="am-btn primary" type="submit">Generate Report</button>
        </form>

        <div class="am-grid">
            <div class="am-card"><div class="am-label">Total Leads</div><div class="am-value">{{ number_format($summary['total'] ?? 0) }}</div><div class="am-sub">{{ $sourceLabel ?? 'Selected source' }}</div></div>
            <div class="am-card"><div class="am-label">Interested</div><div class="am-value text-emerald-700">{{ number_format($summary['interested'] ?? 0) }}</div><div class="am-sub">{{ $pct($summary['interested'] ?? 0) }}% of total</div></div>
            <div class="am-card"><div class="am-label">Not Interested</div><div class="am-value text-red-700">{{ number_format($summary['not_interested'] ?? 0) }}</div><div class="am-sub">{{ $pct($summary['not_interested'] ?? 0) }}% of total</div></div>
            <div class="am-card"><div class="am-label">Junk / Invalid</div><div class="am-value text-red-700">{{ number_format($summary['junk'] ?? 0) }}</div><div class="am-sub">{{ $pct($summary['junk'] ?? 0) }}% of total</div></div>
            <div class="am-card"><div class="am-label">CNP</div><div class="am-value text-amber-700">{{ number_format($summary['cnp'] ?? 0) }}</div><div class="am-sub">{{ $pct($summary['cnp'] ?? 0) }}% not reachable</div></div>
            <div class="am-card"><div class="am-label">Quality Score</div><div class="am-value text-emerald-800">{{ $summary['quality_score'] ?? 0 }}%</div><div class="am-sub">Interested / qualified only</div></div>
        </div>

        <div class="am-two">
            <section class="am-section">
                <h2>Meta Health Status</h2>
                <p>Token, mapping, webhook aur CRM handoff monitoring.</p>
                <div class="flex flex-wrap gap-2">
                    @foreach(($metaHealthAlerts ?? []) as $alert)
                        <span class="am-pill {{ $alert['tone'] === 'danger' ? 'bad' : ($alert['tone'] === 'warn' ? 'warn' : 'good') }}">{{ $alert['label'] }}: {{ $alert['display'] }}</span>
                    @endforeach
                </div>
                <table class="am-table mt-4">
                    <tbody>
                        <tr><td>Connected pages</td><td><b>{{ number_format($healthSnapshot['connected_pages'] ?? 0) }}</b></td></tr>
                        <tr><td>Enabled forms</td><td><b>{{ number_format($healthSnapshot['enabled_forms'] ?? 0) }}</b></td></tr>
                        <tr><td>Mapped forms</td><td><b>{{ number_format($healthSnapshot['mapped_enabled_forms'] ?? 0) }}</b></td></tr>
                        <tr><td>Failed webhooks</td><td><b>{{ number_format($healthSnapshot['failed_count'] ?? 0) }}</b></td></tr>
                    </tbody>
                </table>
            </section>
            <section class="am-section">
                <div class="am-att">
                    <h2>Self Attendance</h2>
                    <p id="attMsg">{{ data_get($attendanceToday, 'record.info_line', 'Attendance policy not configured.') }}</p>
                    <div class="am-att-grid">
                        <div class="am-att-stat"><div class="am-label text-white/70">Punch In</div><div id="attIn" class="am-value">{{ data_get($attendanceRecord, 'first_punch_in_at') ? \Illuminate\Support\Carbon::parse(data_get($attendanceRecord, 'first_punch_in_at'))->format('d M, h:i A') : '--' }}</div></div>
                        <div class="am-att-stat"><div class="am-label text-white/70">Punch Out</div><div id="attOut" class="am-value">{{ data_get($attendanceRecord, 'last_punch_out_at') ? \Illuminate\Support\Carbon::parse(data_get($attendanceRecord, 'last_punch_out_at'))->format('d M, h:i A') : '--' }}</div></div>
                    </div>
                    <div class="am-actions">
                        <button id="attInBtn" type="button" class="primary" @disabled(!$attendanceConfigured || data_get($attendanceRecord, 'first_punch_in_at'))>Punch In</button>
                        <button id="attOutBtn" type="button" class="secondary" @disabled(!$attendanceConfigured || !data_get($attendanceRecord, 'first_punch_in_at') || data_get($attendanceRecord, 'last_punch_out_at'))>Punch Out</button>
                    </div>
                </div>
            </section>
        </div>

        <div class="am-two">
            <section class="am-section">
                <h2>Outcome Breakdown</h2>
                <p>User submitted outcomes from telecalling/calling tasks.</p>
                <table class="am-table">
                    <thead><tr><th>Outcome</th><th>Count</th><th>%</th></tr></thead>
                    <tbody>
                    @forelse(($outcomes ?? collect()) as $label => $count)
                        <tr><td>{{ $label }}</td><td><b>{{ number_format($count) }}</b></td><td>{{ $pct($count) }}%</td></tr>
                    @empty
                        <tr><td colspan="3">No outcome data found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </section>
            <section class="am-section">
                <h2>Lead List Status Breakdown</h2>
                <p>Lead list operational status summary.</p>
                <table class="am-table">
                    <thead><tr><th>Status</th><th>Count</th><th>%</th></tr></thead>
                    <tbody>
                    @forelse(($statusBreakdown ?? collect()) as $label => $count)
                        <tr><td>{{ $label }}</td><td><b>{{ number_format($count) }}</b></td><td>{{ $pct($count) }}%</td></tr>
                    @empty
                        <tr><td colspan="3">No status data found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </section>
        </div>

        <section class="am-section">
            <h2>Evidence Rows</h2>
            <p>Filtered rows for vendor review and internal quality checks.</p>
            <table class="am-table">
                <thead><tr><th>Lead</th><th>Phone</th><th>Created</th><th>User</th><th>Status</th><th>Outcome</th><th>Remark</th></tr></thead>
                <tbody>
                @forelse(($evidenceRows ?? collect()) as $row)
                    @php($lead = $row['lead'])
                    <tr>
                        <td><b>{{ $lead->name }}</b><div class="text-xs text-slate-500">ID {{ $lead->id }}</div></td>
                        <td>{{ $lead->phone }}</td>
                        <td>{{ $lead->created_at->format('d M Y, h:i A') }}</td>
                        <td>{{ $row['owner'] }}</td>
                        <td>{{ $row['status_label'] }}</td>
                        <td>{{ $row['outcome_label'] }}</td>
                        <td>{{ $row['remarks'] ?: 'No remark captured' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7">No leads found for selected filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>
    </div>
</div>

<script>
(()=>{const t=document.querySelector('meta[name="csrf-token"]')?.content,c=d=>d?new Intl.DateTimeFormat('en-IN',{day:'2-digit',month:'short',hour:'2-digit',minute:'2-digit'}).format(new Date(d)):'--',g=()=>new Promise(r=>!navigator.geolocation?r({}):navigator.geolocation.getCurrentPosition(p=>r({latitude:p.coords.latitude,longitude:p.coords.longitude}),()=>r({}),{enableHighAccuracy:true,timeout:7000,maximumAge:0})),u=p=>{document.getElementById('attIn').textContent=c(p?.record?.first_punch_in_at);document.getElementById('attOut').textContent=c(p?.record?.last_punch_out_at);document.getElementById('attMsg').textContent=p?.record?.info_line||'Attendance updated.';document.getElementById('attInBtn').disabled=!!p?.record?.first_punch_in_at;document.getElementById('attOutBtn').disabled=!p?.record?.first_punch_in_at||!!p?.record?.last_punch_out_at},h=async e=>{const l=await g(),r=await fetch(e,{method:'POST',headers:{'X-CSRF-TOKEN':t,Accept:'application/json','Content-Type':'application/json'},body:JSON.stringify({...l,source:'ad_manager_dashboard'})}),j=await r.json().catch(()=>({}));if(!r.ok||!j.success)throw new Error(j.message||'Attendance request failed.');u({record:j.data})};document.getElementById('attInBtn')?.addEventListener('click',()=>h('/api/attendance/punch-in'));document.getElementById('attOutBtn')?.addEventListener('click',()=>h('/api/attendance/punch-out'));})();
</script>
@endsection

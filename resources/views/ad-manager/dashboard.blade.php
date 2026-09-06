@extends('layouts.app')

@section('title', 'Ad Manager Dashboard - ' . brand_name())
@section('page-title', 'Ad Manager Dashboard')
@section('page-subtitle', 'Lead quality dashboard and self attendance.')

@section('content')
@php
    $attendanceConfigured = (bool) data_get($attendanceToday, 'configured', false);
    $attendanceRecord = data_get($attendanceToday, 'record', []);
@endphp

<style>
    .ad-manager-page{display:flex;flex-direction:column;gap:16px}
    .ad-manager-top{display:grid;grid-template-columns:minmax(320px,.8fr) minmax(0,1.2fr);gap:16px;align-items:stretch}
    .am-attendance-card{background:linear-gradient(135deg,#082f49 0,#0c628f 100%);color:#fff;border:1px solid #0c628f;border-radius:8px;padding:16px;box-shadow:0 10px 24px rgba(8,47,73,.18)}
    .am-attendance-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}
    .am-attendance-head h2{margin:0;font-size:18px;font-weight:900;color:#fff}
    .am-attendance-head p,.am-attendance-card .sub{margin:4px 0 0;color:rgba(255,255,255,.78);font-size:12px;line-height:1.45}
    .am-pill{display:inline-flex;align-items:center;gap:6px;border-radius:999px;padding:7px 10px;font-size:11px;font-weight:900;white-space:nowrap;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.18);color:#fff}
    .am-attendance-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:14px}
    .am-attendance-stat{background:rgba(255,255,255,.10);border:1px solid rgba(255,255,255,.14);border-radius:8px;padding:12px}
    .am-attendance-stat .label{font-size:10px;font-weight:900;letter-spacing:.08em;text-transform:uppercase;color:rgba(255,255,255,.72)}
    .am-attendance-stat .value{font-size:18px;font-weight:900;margin-top:6px}
    .am-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}
    .am-actions button{border-radius:8px;padding:10px 14px;font-size:12px;font-weight:900;border:0;cursor:pointer}
    .am-actions .primary{background:#fff;color:#082f49}
    .am-actions .secondary{background:rgba(255,255,255,.12);color:#fff;border:1px solid rgba(255,255,255,.18)}
    .am-actions button[disabled]{opacity:.6;cursor:not-allowed}
    .am-shortcuts{border:1px solid #c9d8cf;background:#fff;border-radius:8px;padding:14px;box-shadow:0 10px 24px rgba(6,58,28,.06)}
    .am-shortcuts h2{margin:0 0 10px;font-size:15px;font-weight:900;color:#0f172a}
    .am-shortcut-grid{display:flex;flex-wrap:wrap;gap:8px}
    .am-shortcut-grid a{display:inline-flex;align-items:center;gap:7px;border:1px solid #cfded5;background:#f7fbf8;color:#0f3d2a;text-decoration:none;border-radius:999px;padding:8px 11px;font-size:12px;font-weight:900}
    @media(max-width:900px){.ad-manager-top{grid-template-columns:1fr}.am-attendance-grid{grid-template-columns:1fr}}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="ad-manager-page">
    <div class="ad-manager-top">
        <section class="am-attendance-card" id="attendanceWidget">
            <div class="am-attendance-head">
                <div>
                    <h2>Self Attendance</h2>
                    <p id="attMsg">{{ data_get($attendanceToday, 'record.info_line', $attendanceConfigured ? 'Punch in when you start work.' : 'Attendance policy not configured.') }}</p>
                </div>
                <span id="attStatus" class="am-pill">{{ data_get($attendanceToday, 'record.status_label', $attendanceConfigured ? 'Not Punched' : 'Not Configured') }}</span>
            </div>
            <div class="am-attendance-grid">
                <div class="am-attendance-stat">
                    <div class="label">Punch In</div>
                    <div class="value" id="attIn">{{ data_get($attendanceRecord, 'first_punch_in_at') ? \Illuminate\Support\Carbon::parse(data_get($attendanceRecord, 'first_punch_in_at'))->format('d M, h:i A') : '--' }}</div>
                </div>
                <div class="am-attendance-stat">
                    <div class="label">Punch Out</div>
                    <div class="value" id="attOut">{{ data_get($attendanceRecord, 'last_punch_out_at') ? \Illuminate\Support\Carbon::parse(data_get($attendanceRecord, 'last_punch_out_at'))->format('d M, h:i A') : '--' }}</div>
                </div>
            </div>
            <div class="am-actions">
                <button id="attInBtn" type="button" class="primary" @disabled(!$attendanceConfigured || data_get($attendanceRecord, 'first_punch_in_at'))>Punch In</button>
                <button id="attOutBtn" type="button" class="secondary" @disabled(!$attendanceConfigured || !data_get($attendanceRecord, 'first_punch_in_at') || data_get($attendanceRecord, 'last_punch_out_at'))>Punch Out</button>
            </div>
        </section>

        <section class="am-shortcuts">
            <h2>Ad Manager Actions</h2>
            <div class="am-shortcut-grid">
                @foreach($quickActions as $action)
                    <a href="{{ $action['route'] }}"><i class="{{ $action['icon'] }}"></i>{{ $action['label'] }}</a>
                @endforeach
            </div>
        </section>
    </div>

    @include('marketing.partials.lead-quality-overview', [
        'leadQualityActionUrl' => route('ad-manager.dashboard'),
        'leadQualityResetUrl' => route('ad-manager.dashboard', ['source' => 'all']),
    ])
</div>

<script>
(() => {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    const formatTime = (value) => value ? new Intl.DateTimeFormat('en-IN', {
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit'
    }).format(new Date(value)) : '--';
    const locate = () => new Promise((resolve) => {
        if (!navigator.geolocation) {
            resolve({});
            return;
        }
        navigator.geolocation.getCurrentPosition(
            (position) => resolve({
                latitude: position.coords.latitude,
                longitude: position.coords.longitude
            }),
            () => resolve({}),
            { enableHighAccuracy: true, timeout: 7000, maximumAge: 0 }
        );
    });
    const update = (record) => {
        document.getElementById('attStatus').textContent = record?.status_label || 'Not Punched';
        document.getElementById('attIn').textContent = formatTime(record?.first_punch_in_at);
        document.getElementById('attOut').textContent = formatTime(record?.last_punch_out_at);
        document.getElementById('attMsg').textContent = record?.info_line || 'Attendance updated.';
        document.getElementById('attInBtn').disabled = !!record?.first_punch_in_at;
        document.getElementById('attOutBtn').disabled = !record?.first_punch_in_at || !!record?.last_punch_out_at;
    };
    const punch = async (url) => {
        const button = url.includes('punch-in') ? document.getElementById('attInBtn') : document.getElementById('attOutBtn');
        button.disabled = true;
        try {
            const location = await locate();
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ ...location, source: 'ad_manager_dashboard' })
            });
            const json = await response.json().catch(() => ({}));
            if (!response.ok || !json.success) {
                throw new Error(json.message || 'Attendance request failed.');
            }
            update(json.data);
        } catch (error) {
            document.getElementById('attMsg').textContent = error.message || 'Attendance request failed.';
            button.disabled = false;
        }
    };
    document.getElementById('attInBtn')?.addEventListener('click', () => punch('/api/attendance/punch-in'));
    document.getElementById('attOutBtn')?.addEventListener('click', () => punch('/api/attendance/punch-out'));
})();
</script>
@endsection

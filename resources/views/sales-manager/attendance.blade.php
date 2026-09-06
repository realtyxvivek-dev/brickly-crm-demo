@extends('sales-manager.layout')

@section('title', 'Attendance - Assistant Sales Manager')
@section('page-title', 'Attendance')

@push('styles')
<style>
    .asm-att{--green-900:#0d4a29;--green-500:#4caf82;--green-wash:#f0f7f2;--border:#e2ece3;--border-soft:rgba(13,74,41,.07);--text-900:#162f20;--text-600:#4a6b54;--text-400:#7a9b85;--shadow-card:0 1px 8px rgba(10,61,36,.06);--shadow-hover:0 4px 20px rgba(10,61,36,.10);--ease:cubic-bezier(.22,1,.36,1);box-sizing:border-box;}
    .asm-att *,.asm-att ::before,.asm-att ::after{box-sizing:border-box;}
    .asm-att{font-family:"Instrument Sans",system-ui,sans-serif;color:var(--text-900);display:flex;flex-direction:column;gap:16px;-webkit-font-smoothing:antialiased;}
    .asm-att .card{background:#fff;border:1px solid var(--border);border-radius:20px;box-shadow:var(--shadow-card);padding:18px;}
    .asm-att .hero-card{background:linear-gradient(135deg,#fbfffc 0%,#eef8f1 100%);border-color:#d8eadc;padding:20px;}
    .asm-att .hero-head{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:16px;}
    .asm-att .card-title{font-size:19px;font-weight:800;letter-spacing:-.04em;color:var(--text-900);margin-bottom:4px;}
    .asm-att .card-copy{font-size:13px;color:var(--text-600);margin-bottom:16px;line-height:1.5;}
    .asm-att .chip-grid{display:flex;flex-wrap:wrap;gap:8px;}
    .asm-att .chip{display:inline-flex;align-items:center;gap:8px;padding:10px 15px;border-radius:14px;border:1.5px solid var(--border);background:#fff;color:var(--text-600);font-family:inherit;font-size:13px;font-weight:800;text-decoration:none;cursor:pointer;transition:all .2s var(--ease);}
    .asm-att .chip:hover{background:var(--green-wash);color:var(--green-900);border-color:var(--green-500);}
    .asm-att .chip.primary{background:#0d4a29;color:#fff;border-color:#0d4a29;box-shadow:0 8px 18px rgba(13,74,41,.16);}
    .asm-att .chip.primary:hover{background:#063A1C;color:#fff;}
    .asm-att .history-list{display:flex;flex-direction:column;gap:10px;}
    .asm-att .history-toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px;}
    .asm-att .history-tools{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
    .asm-att .history-select{min-width:220px;padding:10px 14px;border-radius:12px;border:1px solid var(--border);background:#fff;color:var(--text-900);font-size:13px;font-weight:600;}
    .asm-att .view-toggle{display:inline-flex;gap:6px;padding:4px;border:1px solid var(--border);border-radius:14px;background:#fbfdfb;}
    .asm-att .view-toggle-btn{border:0;background:transparent;color:var(--text-600);border-radius:10px;padding:8px 12px;font-family:inherit;font-size:12px;font-weight:900;cursor:pointer;}
    .asm-att .view-toggle-btn.active{background:#0d4a29;color:#fff;box-shadow:0 6px 14px rgba(13,74,41,.16);}
    .asm-att .history-row{display:flex;align-items:center;gap:12px;padding:12px 14px;border-radius:14px;background:var(--green-wash);border:1px solid var(--border-soft);}
    .asm-att .history-date{font-size:13px;font-weight:700;color:var(--text-900);min-width:90px;}
    .asm-att .history-meta{font-size:13px;color:var(--text-600);flex:1;}
    .asm-att .history-status{display:inline-flex;align-items:center;padding:5px 10px;border-radius:999px;font-size:11px;font-weight:700;}
    .asm-att .calendar-view{display:none;}
    .asm-att .calendar-view.active{display:block;}
    .asm-att .history-list.hidden{display:none;}
    .asm-att .attendance-calendar{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:8px;}
    .asm-att .calendar-weekday{padding:8px 6px;text-align:center;font-size:11px;font-weight:900;text-transform:uppercase;letter-spacing:.08em;color:var(--text-400);}
    .asm-att .calendar-day{min-height:112px;border:1px solid var(--border-soft);border-radius:16px;background:#fbfdfb;padding:10px;display:flex;flex-direction:column;gap:7px;}
    .asm-att .calendar-day.blank{background:transparent;border-color:transparent;}
    .asm-att .calendar-day.today{border-color:#0d4a29;box-shadow:inset 0 0 0 1px rgba(13,74,41,.28);}
    .asm-att .calendar-top{display:flex;align-items:center;justify-content:space-between;gap:8px;}
    .asm-att .calendar-date{font-size:16px;font-weight:900;color:var(--text-900);}
    .asm-att .calendar-status{display:inline-flex;align-items:center;justify-content:center;min-height:24px;padding:5px 8px;border-radius:999px;font-size:10px;font-weight:900;white-space:nowrap;}
    .asm-att .calendar-status.present,.asm-att .calendar-status.late{background:#e9f9ef;color:#14532d;}
    .asm-att .calendar-status.half_day{background:#fff6da;color:#a15c07;}
    .asm-att .calendar-status.absent{background:#ffe8e8;color:#b91c1c;}
    .asm-att .calendar-status.week_off,.asm-att .calendar-status.holiday{background:#eef2f7;color:#344054;}
    .asm-att .calendar-status.empty{background:#f3f5f7;color:#667085;}
    .asm-att .calendar-time{font-size:11px;line-height:1.45;color:var(--text-600);font-weight:700;}
    .asm-att .calendar-footer{margin-top:auto;display:flex;justify-content:flex-end;}
    .asm-att .productivity-card{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:14px;border-radius:16px;border:1px solid var(--border-soft);background:var(--green-wash);margin-top:14px;}
    .asm-att .productivity-card strong{display:block;font-size:14px;font-weight:800;color:var(--text-900);}
    .asm-att .productivity-card span{display:block;font-size:12px;color:var(--text-600);margin-top:4px;}
    .asm-att .productivity-badge{display:inline-flex;align-items:center;justify-content:center;min-height:30px;border-radius:999px;padding:7px 12px;font-size:11px;font-weight:900;text-transform:uppercase;letter-spacing:.06em;}
    .asm-att .productivity-badge.pd{background:#e9f9ef;color:#14532d;}
    .asm-att .productivity-badge.pending{background:#fff6da;color:#a15c07;}
    .asm-att .productivity-badge.npd{background:#f3f5f7;color:#475467;}
    .asm-att .empty{font-size:13px;color:var(--text-400);}
    .asm-att .links-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;}
    .asm-att .link-card{display:block;padding:16px;border-radius:18px;border:1px solid var(--border-soft);background:var(--green-wash);text-decoration:none;color:var(--text-900);transition:all .2s var(--ease);}
    .asm-att .link-card:hover{transform:translateY(-2px);box-shadow:var(--shadow-hover);}
    .asm-att .link-card strong{display:block;font-size:14px;font-weight:800;margin-bottom:4px;}
    .asm-att .link-card span{font-size:12px;color:var(--text-600);}
    .asm-att .balance-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-top:14px;}
    .asm-att .balance-card{display:block;text-decoration:none;color:inherit;border:1px solid #dce9df;border-radius:16px;background:#fff;padding:13px 14px;min-height:82px;}
    .asm-att .balance-label{font-size:11px;font-weight:900;text-transform:uppercase;letter-spacing:.08em;color:var(--text-600);}
    .asm-att .balance-value{margin-top:7px;font-size:24px;line-height:1;font-weight:900;color:#0d4a29;}
    .asm-att .balance-note{margin-top:6px;font-size:11px;color:var(--text-400);font-weight:700;}
    .asm-att .section-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;}
    @media(max-width:991px){.asm-att .links-grid{grid-template-columns:1fr;}.asm-att .attendance-calendar{grid-template-columns:repeat(2,minmax(0,1fr));}.asm-att .calendar-weekday,.asm-att .calendar-day.blank{display:none;}}
    @media(max-width:767px){.asm-att .links-grid,.asm-att .balance-grid{grid-template-columns:repeat(2,minmax(0,1fr));}.asm-att .chip{flex:1 1 auto;justify-content:center;}.asm-att .hero-head{display:block;}.asm-att .hero-head .chip-grid{margin-top:14px;}.asm-att .history-tools,.asm-att .history-select{width:100%;}.asm-att .view-toggle{width:100%;}.asm-att .view-toggle-btn{flex:1;}.asm-att .attendance-calendar{grid-template-columns:1fr;}}
</style>
@endpush

@section('content')
<div class="asm-att">
    <div class="card hero-card">
        <div class="hero-head">
            <div>
                <div class="card-title">Attendance</div>
                <div class="card-copy mb-0">Today punch, attendance history, leave requests, regularization and payslips in one place.</div>
            </div>
            <div class="chip-grid">
                <a href="#todayCard" class="chip primary">Today</a>
                <a href="#historySection" class="chip">My History</a>
                <a href="{{ route('attendance.leaves') }}" class="chip">Leaves</a>
                <a href="{{ route('attendance.regularizations') }}" class="chip">Regularization</a>
                <a href="{{ route('attendance.payslips') }}" class="chip">Payslips</a>
            </div>
        </div>
        @if($todayProductivity)
            <div class="productivity-card" title="{{ $todayProductivity['title'] }}">
                <div>
                    <strong>Today Productivity</strong>
                    <span>Assigned Visits: {{ $todayProductivity['assigned'] }} | Verified Visits: {{ $todayProductivity['verified'] }}</span>
                </div>
                <div class="productivity-badge {{ $todayProductivity['status'] }}">{{ $todayProductivity['label'] }}</div>
            </div>
        @endif
    </div>

    <div id="todayCard">
        @include('attendance._widget')
    </div>

    <div class="card" id="historySection">
        <div class="section-head">
            <div>
                <div class="card-title">My Attendance</div>
                <div class="card-copy" id="asmAttendanceHistoryCopy">Current month attendance records will appear here.</div>
            </div>
            <a href="{{ route('attendance.payslips') }}" class="chip">View Payslips</a>
        </div>
        <div class="history-toolbar">
            <div class="history-tools">
                <select id="asmAttendanceMonthSelect" class="history-select">
                    @php
                        $currentMonthDate = now();
                        $previousMonthDate = now()->copy()->subMonthNoOverflow();
                    @endphp
                    <option value="{{ $currentMonthDate->format('Y-m') }}">{{ $currentMonthDate->format('F Y') }}</option>
                    <option value="{{ $previousMonthDate->format('Y-m') }}">{{ $previousMonthDate->format('F Y') }}</option>
                </select>
                <div class="view-toggle" aria-label="Attendance view">
                    <button type="button" class="view-toggle-btn active" data-attendance-view="list">List</button>
                    <button type="button" class="view-toggle-btn" data-attendance-view="calendar">Calendar</button>
                </div>
            </div>
            <div class="chip-grid">
                <a href="{{ route('attendance.leaves') }}" class="chip">Apply Leave</a>
                <a href="{{ route('attendance.regularizations') }}" class="chip">Regularize</a>
            </div>
        </div>
        <div class="history-list" id="asmAttendanceHistoryList">
            <div class="empty">Loading attendance history...</div>
        </div>
        <div class="calendar-view" id="asmAttendanceCalendarView"></div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const historyList = document.getElementById('asmAttendanceHistoryList');
    const calendarView = document.getElementById('asmAttendanceCalendarView');
    const monthSelect = document.getElementById('asmAttendanceMonthSelect');
    const historyCopy = document.getElementById('asmAttendanceHistoryCopy');
    const viewButtons = Array.from(document.querySelectorAll('[data-attendance-view]'));
    if (!historyList || !calendarView || !monthSelect || !historyCopy) return;

    const token = document.querySelector('meta[name="api-token"]')?.content || '';
    const headers = {
        'Accept': 'application/json',
        'Authorization': token ? `Bearer ${token}` : '',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
    };

    function formatTime(value) {
        if (!value) return '--';
        const date = new Date(value);
        return Number.isNaN(date.getTime()) ? '--' : date.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' });
    }

    function statusStyle(status) {
        if (status === 'present' || status === 'late') return 'background:#f0f7f2;color:#0d4a29;';
        if (status === 'half_day') return 'background:#fff8e4;color:#8a6504;';
        return 'background:#f1f5f9;color:#475569;';
    }

    function productivityBadge(productivity) {
        if (!productivity || !productivity.label) return '';
        const status = productivity.status || 'npd';
        const title = productivity.title || '';
        return '<span class="productivity-badge ' + status + '" title="' + title.replace(/"/g, '&quot;') + '">' + productivity.label + '</span>';
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, function (match) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[match];
        });
    }

    function calendarStatusClass(status) {
        return String(status || 'empty').toLowerCase().replace(/[^a-z0-9_]+/g, '_');
    }

    function renderCalendar(rows, year, month) {
        const rowMap = new Map(rows.map(row => [row.attendance_date, row]));
        const firstDate = new Date(year, month - 1, 1);
        const totalDays = new Date(year, month, 0).getDate();
        const startBlankCount = firstDate.getDay();
        const todayKey = new Date().toLocaleDateString('en-CA');
        const weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

        let html = '<div class="attendance-calendar">';
        html += weekdays.map(day => '<div class="calendar-weekday">' + day + '</div>').join('');
        for (let i = 0; i < startBlankCount; i++) {
            html += '<div class="calendar-day blank"></div>';
        }

        for (let day = 1; day <= totalDays; day++) {
            const dateKey = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const row = rowMap.get(dateKey) || {};
            const status = row.status || '';
            const statusLabel = row.status_label || status || 'No Record';
            const statusClass = calendarStatusClass(status);
            const isToday = dateKey === todayKey ? ' today' : '';
            const inTime = formatTime(row.first_punch_in_at);
            const outTime = formatTime(row.last_punch_out_at);

            html += '<div class="calendar-day' + isToday + '">' +
                '<div class="calendar-top">' +
                    '<div class="calendar-date">' + day + '</div>' +
                    '<span class="calendar-status ' + statusClass + '">' + escapeHtml(statusLabel) + '</span>' +
                '</div>' +
                '<div class="calendar-time">In ' + inTime + '<br>Out ' + outTime + '</div>' +
                '<div class="calendar-footer">' + productivityBadge(row.productivity) + '</div>' +
            '</div>';
        }
        html += '</div>';
        calendarView.innerHTML = html;
    }

    function setView(view) {
        const nextView = view === 'calendar' ? 'calendar' : 'list';
        historyList.classList.toggle('hidden', nextView === 'calendar');
        calendarView.classList.toggle('active', nextView === 'calendar');
        viewButtons.forEach(button => button.classList.toggle('active', button.dataset.attendanceView === nextView));
        localStorage.setItem('asmAttendanceView', nextView);
    }

    function selectedMonthParts() {
        const [year, month] = String(monthSelect.value || '').split('-');
        return {
            year: parseInt(year, 10),
            month: parseInt(month, 10),
        };
    }

    function syncHistoryCopy() {
        const selectedText = monthSelect.options[monthSelect.selectedIndex]?.text || 'Selected Month';
        historyCopy.textContent = `${selectedText} ke sabhi attendance records yahan dikhte rahenge.`;
    }

    async function loadHistory() {
        try {
            syncHistoryCopy();
            const { year, month } = selectedMonthParts();
            const response = await fetch(`/api/attendance/history?year=${year}&month=${month}`, { headers, credentials: 'same-origin' });
            const payload = await response.json();

            if (!response.ok || !payload.success) throw new Error(payload.message || 'Unable to load.');

            const rows = Array.isArray(payload.data) ? payload.data : [];
            renderCalendar(rows, year, month);
            if (!rows.length) {
                historyList.innerHTML = '<div class="empty">Current month ke records abhi available nahi hain.</div>';
                return;
            }

            historyList.innerHTML = rows.map(function (row) {
                return '<div class="history-row">' +
                    '<div class="history-date">' + escapeHtml(row.attendance_date || '--') + '</div>' +
                    '<div class="history-meta">In ' + formatTime(row.first_punch_in_at) + ' | Out ' + formatTime(row.last_punch_out_at) + '</div>' +
                    '<span class="history-status" style="' + statusStyle(row.status) + '">' + escapeHtml(row.status_label || row.status || 'Status') + '</span>' +
                    productivityBadge(row.productivity) +
                '</div>';
            }).join('');
        } catch (error) {
            historyList.innerHTML = '<div class="empty">' + (error.message || 'Unable to load.') + '</div>';
        }
    }

    monthSelect.addEventListener('change', loadHistory);
    viewButtons.forEach(button => button.addEventListener('click', () => setView(button.dataset.attendanceView)));
    setView(localStorage.getItem('asmAttendanceView') || 'list');
    loadHistory();
})();
</script>
@endpush

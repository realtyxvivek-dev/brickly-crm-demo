@extends('layouts.app')

@section('title', 'Team Activity Calendar')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.5/main.min.css">
<style>
    html body.sidebar-hidden #mainContent:has(.tac-page) { margin-left:0 !important; width:100% !important; max-width:100% !important; }
    #mainContent > .container:has(.tac-page) { max-width:none !important; margin:0; }
    .tac-page { padding: 10px; color: #082f22; }
    .tac-shell { border: 1px solid #aacbb8; background: #fff; box-shadow: 0 8px 24px rgba(6,58,28,.06); }
    .tac-head { display:flex; align-items:center; justify-content:space-between; gap:16px; padding:14px 16px; border-bottom:1px solid #aacbb8; background:#edf8f1; }
    .tac-head h1 { margin:0; font-size:22px; font-weight:800; letter-spacing:0; }
    .tac-head p { margin:3px 0 0; color:#587064; font-size:13px; }
    .tac-btn { min-height:42px; display:inline-flex; align-items:center; justify-content:center; gap:8px; border:1px solid #83b49a; border-radius:5px; padding:9px 14px; background:#fff; color:#07583a; font-weight:750; cursor:pointer; }
    .tac-btn:hover { background:#edf8f1; }
    .tac-btn.primary { background:#0b6844; border-color:#0b6844; color:#fff; }
    .tac-btn.danger { color:#b42318; border-color:#e7a39e; }
    .tac-summary { display:grid; grid-template-columns:repeat(6,minmax(0,1fr)); border-bottom:1px solid #b9d5c4; }
    .tac-stat { padding:12px 14px; border-right:1px solid #d2e2d8; background:#fbfdfb; }
    .tac-stat:last-child { border-right:0; }
    .tac-stat small { display:block; color:#64766e; font-size:11px; font-weight:800; text-transform:uppercase; }
    .tac-stat strong { display:block; margin-top:3px; font-size:22px; }
    .tac-stat.overdue strong { color:#c43221; }
    .tac-filters { display:grid; grid-template-columns:1.4fr repeat(4,minmax(130px,.7fr)) auto; gap:8px; padding:12px; border-bottom:1px solid #c9ddd1; background:#f7fbf8; }
    .tac-field { min-width:0; }
    .tac-field label { display:block; margin-bottom:4px; color:#496157; font-size:11px; font-weight:800; text-transform:uppercase; }
    .tac-input { width:100%; height:40px; border:1px solid #b8cfc1; border-radius:4px; padding:7px 10px; background:#fff; color:#102a20; font:inherit; }
    .tac-main { display:grid; grid-template-columns:minmax(0,1fr); min-height:660px; }
    .tac-calendar-wrap { min-width:0; padding:12px; border-bottom:1px solid #b9d5c4; }
    #activityCalendar { min-height:620px; }
    .tac-day { min-width:0; background:#fff; }
    .tac-day-head { display:flex; align-items:center; justify-content:space-between; gap:8px; padding:13px 14px; border-bottom:1px solid #c9ddd1; background:#f0f8f3; }
    .tac-day-head h2 { margin:0; font-size:16px; }
    .tac-day-count { min-width:28px; border-radius:50%; padding:4px 8px; background:#0b6844; color:#fff; text-align:center; font-size:12px; font-weight:800; }
    .tac-table-wrap { overflow:auto; max-height:608px; }
    .tac-table { width:100%; min-width:640px; border-collapse:collapse; table-layout:fixed; }
    .tac-table th { position:sticky; top:0; z-index:1; padding:9px 8px; border-right:1px solid #79a88d; background:#0b6844; color:#fff; font-size:10px; text-transform:uppercase; text-align:left; }
    .tac-table td { padding:10px 8px; border-right:1px solid #dce8e1; border-bottom:1px solid #dce8e1; vertical-align:top; font-size:12px; overflow-wrap:anywhere; }
    .tac-table td strong { display:block; font-size:13px; }
    .tac-table td small { display:block; margin-top:3px; color:#667a70; }
    .tac-icon-btn { width:34px; height:34px; border:1px solid #9dc5ad; border-radius:4px; background:#f3faf5; color:#07583a; cursor:pointer; }
    .tac-empty { padding:48px 16px !important; color:#718078; text-align:center; }
    .tac-badge { display:inline-flex; align-items:center; padding:3px 7px; border:1px solid #b8cfc1; border-radius:10px; font-size:10px; font-weight:800; text-transform:uppercase; white-space:nowrap; }
    .tac-badge.overdue { border-color:#f0aca4; color:#b42318; background:#fff3f1; }
    .tac-badge.completed { border-color:#8ed0ad; color:#067647; background:#ecfdf3; }
    .tac-badge.cancelled { color:#667085; background:#f2f4f7; }
    .fc .fc-toolbar-title { font-size:18px; }
    .fc .fc-button-primary { background:#0b6844; border-color:#0b6844; text-transform:capitalize; }
    .fc .fc-button-primary:disabled { background:#7ca890; border-color:#7ca890; }
    .fc-theme-standard td, .fc-theme-standard th, .fc-theme-standard .fc-scrollgrid { border-color:#c9ddd1; }
    .fc .fc-col-header-cell { background:#eaf5ee; }
    .fc .fc-col-header-cell-cushion { padding:8px 4px; color:#164d38; }
    .fc .fc-daygrid-day.fc-day-today { background:#fff8db; box-shadow:inset 0 0 0 2px #e5a900; }
    .fc-event { border:0; border-left:3px solid currentColor; border-radius:2px; padding:1px 3px; cursor:pointer; font-size:11px; }
    .activity-follow_up { background:#fff6dd !important; color:#8a5700 !important; }
    .activity-meeting { background:#eaf2ff !important; color:#175cd3 !important; }
    .activity-site_visit { background:#eafaf0 !important; color:#067647 !important; }
    .occurrence-completed { text-decoration:none; }
    #activityCalendar .fc-event-main { color:inherit; }
    #activityCalendar .fc-timegrid-col.fc-day-today { background:#f6faf7; }
    #activityCalendar .fc-timegrid-slot { height:48px; }
    .tac-event { overflow:hidden; min-width:0; line-height:1.4; }
    .tac-event strong,.tac-event small { display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .tac-event small { font-size:11px; }
    .tac-agenda-event { display:grid; grid-template-columns:minmax(110px,.7fr) minmax(180px,1.5fr) minmax(150px,1fr) auto; gap:16px; align-items:center; color:#173e30; padding:8px; }
    .tac-agenda-event small { display:block; font-size:12px; margin-top:4px; color:#405d50; }
    #activityCalendar .fc-list-event-title { white-space:normal; }
    #activityCalendar .fc-list-event { font-size:13px; }
    #activityCalendar .fc-list-event-time { white-space:nowrap; color:#173e30; }
    #activityCalendar .fc-list-event:hover td { background:#e8f1ec; }
    #activityCalendar .fc-list-event-dot { border-color:currentColor; }
    @media(max-width:720px) { .tac-agenda-event { grid-template-columns:minmax(0,1fr); gap:6px; padding:4px; overflow-wrap:anywhere; }.tac-agenda-event small { margin-top:0; } }
    .is-overdue { background:#fff0ee !important; color:#b42318 !important; }
    dialog.tac-dialog { position:fixed; inset:0; margin:auto; width:min(720px,calc(100vw - 24px)); max-height:90dvh; padding:0; overflow:hidden; border:1px solid #8fb7a1; border-radius:6px; box-shadow:0 24px 70px rgba(7,50,34,.28); color:#102a20; }
    dialog.tac-dialog[open] { display:flex; flex-direction:column; }
    dialog.tac-dialog > form { display:flex; flex-direction:column; min-height:0; max-height:calc(90dvh - 2px); margin:0; overflow:hidden; }
    .tac-dialog-head, .tac-dialog-foot { flex-shrink:0; }
    dialog.tac-dialog::backdrop { background:rgba(3,34,23,.58); }
    .tac-dialog-head { position:sticky; top:0; z-index:2; display:flex; align-items:center; justify-content:space-between; padding:15px 18px; border-bottom:1px solid #bdd4c6; background:#eef8f2; }
    .tac-dialog-head h2 { margin:0; font-size:18px; }
    .tac-dialog-body { flex:1 1 auto; min-height:0; padding:16px 18px; overflow:auto; overscroll-behavior:contain; }
    .tac-form-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; }
    .tac-form-grid .wide { grid-column:1/-1; }
    .tac-form-grid label { display:block; margin-bottom:5px; font-size:12px; font-weight:800; }
    .tac-form-grid input, .tac-form-grid select, .tac-form-grid textarea { width:100%; min-height:42px; border:1px solid #b8cfc1; border-radius:4px; padding:9px 10px; background:#fff; font:inherit; }
    .tac-form-grid textarea { min-height:76px; resize:vertical; }
    .tac-dialog-foot { display:flex; justify-content:flex-end; gap:8px; padding:12px 18px; border-top:1px solid #d0e0d6; background:#fff; }
    .lead-results { max-height:190px; overflow:auto; border:1px solid #bdd4c6; }
    .lead-result { width:100%; display:flex; justify-content:space-between; gap:8px; padding:9px 10px; border:0; border-bottom:1px solid #e1ebe5; background:#fff; text-align:left; cursor:pointer; }
    .lead-result:hover { background:#eef8f2; }
    .detail-section { margin-bottom:14px; }
    .detail-section h3 { margin:0 0 7px; color:#146442; font-size:12px; text-transform:uppercase; }
    .detail-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); border-top:1px solid #d8e5dd; border-left:1px solid #d8e5dd; }
    .detail-cell { padding:9px; border-right:1px solid #d8e5dd; border-bottom:1px solid #d8e5dd; }
    .detail-cell small { display:block; color:#667a70; font-size:10px; text-transform:uppercase; }
    .detail-cell strong { display:block; margin-top:3px; font-size:13px; }
    .tac-alert { display:none; margin-bottom:12px; padding:10px 12px; border:1px solid #f2a9a0; background:#fff2f0; color:#b42318; font-size:13px; }
    .type-fields { display:none; }
    @media (max-width:1180px) { .tac-main { grid-template-columns:1fr; } .tac-calendar-wrap { border-right:0; border-bottom:1px solid #b9d5c4; } .tac-summary { grid-template-columns:repeat(3,1fr); } .tac-filters { grid-template-columns:repeat(3,1fr); } }
    @media (max-width:720px) { .tac-page { padding:0; } .tac-head { align-items:flex-start; } .tac-head p { display:none; } .tac-summary { grid-template-columns:repeat(2,1fr); } .tac-filters { grid-template-columns:1fr 1fr; } .tac-filters .search-field { grid-column:1/-1; } .tac-main { min-height:0; } .tac-calendar-wrap { padding:6px; } .tac-form-grid, .detail-grid { grid-template-columns:1fr; } .tac-form-grid .wide { grid-column:auto; } .fc .fc-toolbar { align-items:flex-start; flex-wrap:wrap; gap:6px; } .fc .fc-toolbar-title { font-size:15px; } .fc .fc-button { padding:.35em .48em; font-size:12px; } .fc .fc-daygrid-day-number { font-size:11px; } .fc-event { font-size:9px; } }
</style>
@endpush

@section('content')
<main class="tac-page">
    <section class="tac-shell">
        <header class="tac-head">
            <div><h1>Team Activity Calendar</h1><p>Follow-ups, meetings aur site visits ka complete schedule</p></div>
            <button class="tac-btn primary" type="button" onclick="openCreateDialog()"><i class="fas fa-plus"></i> New Activity</button>
        </header>

        <div class="tac-summary" aria-label="Calendar summary">
            @foreach(['planned'=>'Planned','completed'=>'Completed','overdue'=>'Overdue','follow_up'=>'Follow-ups','meeting'=>'Meetings','site_visit'=>'Site Visits'] as $key=>$label)
                <div class="tac-stat {{ $key === 'overdue' ? 'overdue' : '' }}"><small>{{ $label }}</small><strong id="stat-{{ $key }}">0</strong></div>
            @endforeach
        </div>

        <div class="tac-filters">
            <div class="tac-field search-field"><label for="filterSearch">Customer / Mobile</label><input id="filterSearch" class="tac-input" placeholder="Search name or number"></div>
            <div class="tac-field"><label for="filterUser">Sales Person</label><select id="filterUser" class="tac-input"><option value="">All Sales Users</option>@foreach($salesUsers as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>@endforeach</select></div>
            <div class="tac-field"><label for="filterType">Activity</label><select id="filterType" class="tac-input"><option value="">All Activities</option><option value="follow_up">Follow-up</option><option value="meeting">Meeting</option><option value="site_visit">Site Visit</option></select></div>
            <div class="tac-field"><label for="filterStatus">Status</label><select id="filterStatus" class="tac-input"><option value="">All Status</option><option value="scheduled">Scheduled</option><option value="completed">Completed</option><option value="overdue">Overdue</option><option value="cancelled">Cancelled</option></select></div>
            <div class="tac-field"><label for="filterProject">Project</label><input id="filterProject" class="tac-input" placeholder="Project name"></div>
            <div class="tac-field"><label>&nbsp;</label><button class="tac-btn" type="button" onclick="refreshCalendar()"><i class="fas fa-rotate"></i> Apply</button></div>
        </div>

        <div class="tac-main">
            <div class="tac-calendar-wrap"><div id="activityCalendar"></div></div>
            <section class="tac-day" aria-labelledby="selectedDayTitle">
                <header class="tac-day-head"><h2 id="selectedDayTitle">Today's Activities</h2><span id="selectedDayCount" class="tac-day-count">0</span></header>
                <div class="tac-table-wrap">
                    <table class="tac-table"><thead><tr><th style="width:90px">Time</th><th>Customer</th><th>Sales Person</th><th style="width:110px">Status</th><th style="width:140px">Action</th></tr></thead><tbody id="dayRows"></tbody></table>
                </div>
            </section>
        </div>
    </section>
</main>

<dialog id="createDialog" class="tac-dialog">
    <form id="createForm" enctype="multipart/form-data">
        <header class="tac-dialog-head"><h2>Create Activity</h2><button type="button" class="tac-icon-btn" onclick="closeDialog('createDialog')" aria-label="Close"><i class="fas fa-xmark"></i></button></header>
        <div class="tac-dialog-body"><div class="tac-alert" id="createAlert"></div><div class="tac-form-grid">
            <div class="wide"><label for="leadSearch">Existing Customer *</label><input id="leadSearch" placeholder="Type customer name or mobile" autocomplete="off"><input type="hidden" name="lead_id" id="leadId"><div id="leadResults" class="lead-results" hidden></div><small id="selectedLead"></small></div>
            <div><label for="createType">Activity Type *</label><select name="type" id="createType" required onchange="toggleCreateFields()"><option value="follow_up">Follow-up</option><option value="meeting">Meeting</option><option value="site_visit">Site Visit</option></select></div>
            <div><label for="createOwner">Sales Person *</label><select name="sales_person_id" id="createOwner" required><option value="">Select salesperson</option>@foreach($salesUsers as $person)<option value="{{ $person->id }}">{{ $person->name }} · {{ $person->role?->name }}</option>@endforeach</select></div>
            <div><label for="createAt">Date & Time *</label><input type="datetime-local" name="scheduled_at" id="createAt" required></div>
            <div id="followTypeWrap"><label for="followUpType">Follow-up Mode *</label><select name="follow_up_type" id="followUpType"><option value="call">Call</option><option value="email">Email</option><option value="other">Other</option></select></div>
            <div class="type-fields common-advanced"><label>Project</label><input name="project"></div>
            <div class="type-fields common-advanced"><label>Team Leader</label><select name="team_leader" data-required="visit"><option value="">Select</option>@foreach(['Admin','Alpish','Akash','Omkar','Shushank'] as $leader)<option value="{{ $leader }}">{{ $leader }}</option>@endforeach</select></div>
            <div class="type-fields common-advanced"><label>Budget *</label><select name="budget_range" data-required="advanced"><option value="">Select</option><option>Under 50 Lac</option><option>50 Lac – 1 Cr</option><option>1 Cr – 2 Cr</option><option>2 Cr – 3 Cr</option><option>Above 3 Cr</option></select></div>
            <div class="type-fields common-advanced"><label>Property Type *</label><select name="property_type" data-required="advanced"><option value="">Select</option><option>Plot/Villa</option><option>Flat</option><option>Commercial</option><option>Just Exploring</option></select></div>
            <div class="type-fields common-advanced"><label>Payment Mode *</label><select name="payment_mode" data-required="advanced"><option value="">Select</option><option>Self Fund</option><option>Loan</option></select></div>
            <div class="type-fields common-advanced"><label>Tentative Period *</label><select name="tentative_period" data-required="advanced"><option value="">Select</option><option>Within 1 Month</option><option>Within 3 Months</option><option>Within 6 Months</option><option>More than 6 Months</option></select></div>
            <div class="type-fields common-advanced"><label>Lead Type *</label><select name="lead_type" data-required="advanced"><option value="">Select</option><option>New Visit</option><option>Revisited</option><option>Meeting</option><option>Prospect</option></select></div>
            <div class="type-fields visit-only"><label>Property Name *</label><input name="property_name" data-required="visit"></div>
            <div class="type-fields meeting-only wide"><label>Meeting Location *</label><textarea name="location" data-required="meeting"></textarea></div>
            <div class="type-fields visit-only wide"><label>Property Address *</label><textarea name="property_address" data-required="visit"></textarea></div>
            <div class="wide"><label for="createRemark">Remark *</label><textarea name="remark" id="createRemark" required></textarea></div>
        </div></div>
        <footer class="tac-dialog-foot"><button class="tac-btn" type="button" onclick="closeDialog('createDialog')">Cancel</button><button class="tac-btn primary" type="submit"><i class="fas fa-floppy-disk"></i> Create Activity</button></footer>
    </form>
</dialog>

<dialog id="manageDialog" class="tac-dialog">
    <form id="manageForm"><header class="tac-dialog-head"><h2>Manage Activity</h2><button type="button" class="tac-icon-btn" onclick="closeDialog('manageDialog')" aria-label="Close"><i class="fas fa-xmark"></i></button></header>
        <div class="tac-dialog-body"><div class="tac-alert" id="manageAlert"></div><div id="manageSummary" class="detail-section"></div><div class="tac-form-grid">
            <div><label>Action *</label><select name="action" id="manageAction" onchange="toggleManageFields()"><option value="reschedule">Reschedule</option><option value="reassign">Change Sales Person</option><option value="remark">Add Remark</option><option value="cancel">Cancel Activity</option></select></div>
            <div id="manageDateWrap"><label>New Date & Time *</label><input type="datetime-local" name="scheduled_at" id="manageDate"></div>
            <div id="manageOwnerWrap" hidden><label>New Sales Person *</label><select name="sales_person_id" id="manageOwner"><option value="">Select salesperson</option>@foreach($salesUsers as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>@endforeach</select></div>
            <div class="wide"><label id="manageReasonLabel">Reason *</label><textarea name="reason" required></textarea></div>
        </div></div><footer class="tac-dialog-foot"><button class="tac-btn" type="button" onclick="closeDialog('manageDialog')">Cancel</button><button class="tac-btn primary" type="submit">Save Change</button></footer>
    </form>
</dialog>

<dialog id="completeDialog" class="tac-dialog">
    <form id="completeForm" enctype="multipart/form-data"><header class="tac-dialog-head"><h2>Complete Activity</h2><button type="button" class="tac-icon-btn" onclick="closeDialog('completeDialog')" aria-label="Close"><i class="fas fa-xmark"></i></button></header>
        <div class="tac-dialog-body"><div class="tac-alert" id="completeAlert"></div><div class="tac-form-grid">
            <div class="wide follow-complete"><label>Outcome / Remark *</label><textarea name="outcome"></textarea></div>
            <div class="wide advanced-complete"><label>Completion Feedback *</label><textarea name="feedback"></textarea></div>
            <div class="advanced-complete"><label>Rating *</label><select name="rating"><option value="">Select</option>@for($i=1;$i<=5;$i++)<option value="{{ $i }}">{{ $i }}</option>@endfor</select></div>
            <div class="advanced-complete"><label>Proof Photos *</label><input type="file" name="proof_photos[]" accept="image/jpeg,image/png,image/webp" multiple></div>
            <div class="visit-complete wide"><label>Visited Projects *</label><input name="visited_projects"></div>
            <div class="visit-complete wide"><label>Visited Property Types *</label><div style="display:flex;flex-wrap:wrap;gap:12px">@foreach(['plot'=>'Plot','villa'=>'Villa','apartment'=>'Apartment','commercial'=>'Commercial','other'=>'Other'] as $value=>$label)<label><input style="width:auto;min-height:auto" type="checkbox" name="visited_property_types[]" value="{{ $value }}"> {{ $label }}</label>@endforeach</div></div>
            <div class="visit-complete"><label>Tentative Closing *</label><select name="tentative_closing_time"><option value="">Select</option><option value="within_3_days">Within 3 Days</option><option value="tomorrow">Tomorrow</option><option value="this_week">This Week</option><option value="this_month">This Month</option><option value="it_will_take_time">It Will Take Time</option></select></div>
        </div></div><footer class="tac-dialog-foot"><button class="tac-btn" type="button" onclick="closeDialog('completeDialog')">Cancel</button><button class="tac-btn primary" type="submit"><i class="fas fa-check"></i> Complete</button></footer>
    </form>
</dialog>

<dialog id="detailDialog" class="tac-dialog"><header class="tac-dialog-head"><h2>Customer & Activity Details</h2><button type="button" class="tac-icon-btn" onclick="closeDialog('detailDialog')" aria-label="Close"><i class="fas fa-xmark"></i></button></header><div id="detailBody" class="tac-dialog-body"></div><footer class="tac-dialog-foot"><button class="tac-btn" type="button" onclick="closeDialog('detailDialog')">Close</button></footer></dialog>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.5/main.min.js"></script>
<script>
const calendarRoutes = {
    events: @json(route('lead-quality-auditor.activity-calendar.events')),
    leads: @json(route('lead-quality-auditor.activity-calendar.leads')),
    store: @json(route('lead-quality-auditor.activity-calendar.store')),
    base: @json(url('/lead-quality-auditor/activity-calendar'))
};
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
let calendar, loadedEvents = [], selectedDate = new Date().toISOString().slice(0,10), activeActivity = null, leadSearchTimer;

document.addEventListener('DOMContentLoaded', () => {
    calendar = new FullCalendar.Calendar(document.getElementById('activityCalendar'), {
        initialView: 'dayGridMonth', height: 'auto', nowIndicator: true, dayMaxEvents: 3,
        headerToolbar: { left:'prev,next today', center:'title', right:'dayGridMonth,timeGridWeek,listDay' },
        buttonText: { today:'Today', month:'Month', week:'Week', day:'Day' },
        views: { listDay: { buttonText:'Day', displayEventEnd:false } },
        defaultTimedEventDuration:'00:30:00',
        slotEventOverlap:false, eventMaxStack:2, displayEventEnd:false,
        eventTimeFormat:{hour:'numeric',minute:'2-digit',meridiem:'short'},
        moreLinkClick(info) { calendar.changeView('listDay',info.date); return 'listDay'; },
        eventContent: renderCalendarEvent,
        eventDidMount(info) {
            const p=info.event.extendedProps;
            info.el.title=[typeLabel(p.type),p.customer_name,p.phone,p.sales_person,p.is_overdue?'Overdue':p.status].filter(Boolean).join(' | ');
        },
        events: fetchEvents,
        dateClick(info) { selectedDate = info.dateStr.slice(0,10); renderSelectedDay(); },
        eventClick(info) { selectedDate = info.event.startStr.slice(0,10); renderSelectedDay(); openDetails(info.event.extendedProps.type,info.event.extendedProps.record_id); },
        datesSet(info) { if(info.view.type==='listDay') selectedDate=info.startStr.slice(0,10); setTimeout(renderSelectedDay, 0); }
    });
    calendar.render();
    let calendarWidth = 0;
    new ResizeObserver(([entry]) => {
        const width = Math.round(entry.contentRect.width);
        if (width !== calendarWidth) {
            calendarWidth = width;
            requestAnimationFrame(() => calendar.updateSize());
        }
    }).observe(document.querySelector('.tac-calendar-wrap'));
    document.getElementById('leadSearch').addEventListener('input', searchLeads);
    document.getElementById('filterSearch').addEventListener('keydown', e => { if (e.key === 'Enter') refreshCalendar(); });
    document.getElementById('filterUser').addEventListener('change', refreshCalendar);
    document.getElementById('filterType').addEventListener('change', refreshCalendar);
    document.getElementById('filterStatus').addEventListener('change', refreshCalendar);
    document.getElementById('createForm').addEventListener('submit', submitCreate);
    document.getElementById('manageForm').addEventListener('submit', submitManage);
    document.getElementById('completeForm').addEventListener('submit', submitComplete);
    toggleCreateFields(); toggleManageFields();
});

async function fetchEvents(info, success, failure) {
    const params = new URLSearchParams({ start:info.startStr, end:info.endStr });
    [['user_id','filterUser'],['type','filterType'],['status','filterStatus'],['project','filterProject'],['search','filterSearch']].forEach(([key,id]) => { const value=document.getElementById(id).value.trim(); if(value) params.set(key,value); });
    try {
        const response = await fetch(calendarRoutes.events+'?'+params, {headers:{Accept:'application/json'}});
        if (!response.ok) throw new Error('Calendar data load nahi hua.');
        const data = await response.json(); loadedEvents=data.events; updateSummary(data.summary); renderSelectedDay(); success(data.events);
    } catch(error) { failure(error); }
}
function renderCalendarEvent(info){
    const p=info.event.extendedProps, status=p.is_overdue?'Overdue':p.status;
    if(info.view.type==='listDay') return {html:`<div class="tac-agenda-event"><strong>${escapeHtml(typeLabel(p.type))}</strong><div><strong>${escapeHtml(p.customer_name)}</strong><small>${escapeHtml(p.phone||'-')}</small></div><div><small>Salesperson</small><strong>${escapeHtml(p.sales_person||'-')}</strong></div><span>${escapeHtml(status)}${p.occurrence==='completed'?' (Done)':''}</span></div>`};
    return {html:`<div class="tac-event"><strong>${escapeHtml(info.timeText)} ${escapeHtml(typeLabel(p.type))}</strong><strong>${escapeHtml(p.customer_name)}</strong><small>${escapeHtml(p.sales_person||'-')} | ${escapeHtml(status)}</small></div>`};
}
function refreshCalendar(){ calendar.refetchEvents(); }
function updateSummary(summary){ Object.entries(summary).forEach(([key,value])=>{ const el=document.getElementById('stat-'+key); if(el) el.textContent=value; }); }
function renderSelectedDay(){
    const date = new Date(selectedDate+'T00:00:00');
    document.getElementById('selectedDayTitle').textContent = date.toLocaleDateString('en-IN',{weekday:'short',day:'2-digit',month:'short',year:'numeric'});
    const rows = loadedEvents.filter(event => event.start.slice(0,10)===selectedDate).sort((a,b)=>a.start.localeCompare(b.start));
    document.getElementById('selectedDayCount').textContent=rows.length;
    document.getElementById('dayRows').innerHTML = rows.length ? rows.map(event => {
        const p=event.extendedProps, status=p.is_overdue?'overdue':p.status;
        const canAct=p.status!=='completed'&&p.status!=='cancelled';
        return `<tr><td>${formatTime(event.start)}</td><td><strong>${escapeHtml(p.customer_name)}</strong><small>${escapeHtml(p.phone||'-')}</small><small>${typeLabel(p.type)} · ${escapeHtml(p.project||'-')} · ${p.occurrence==='completed'?'Done':'Planned'}</small></td><td><strong>${escapeHtml(p.sales_person)}</strong></td><td><span class="tac-badge ${status}">${escapeHtml(status)}</span></td><td><button class="tac-icon-btn" onclick='openDetails(${JSON.stringify(p.type)},${p.record_id})' title="View details" aria-label="View details"><i class="fas fa-eye"></i></button> ${canAct?`<button class="tac-icon-btn" onclick='openManage(${JSON.stringify(p)})' title="Manage activity" aria-label="Manage activity"><i class="fas fa-pen"></i></button> <button class="tac-icon-btn" onclick='openComplete(${JSON.stringify(p)})' title="Complete activity" aria-label="Complete activity"><i class="fas fa-check"></i></button>`:''}</td></tr>`;
    }).join('') : '<tr><td colspan="5" class="tac-empty">Is date par koi activity nahi hai.</td></tr>';
}
function openCreateDialog(){
    document.getElementById('createForm').reset(); document.getElementById('leadId').value=''; document.getElementById('selectedLead').textContent='';
    const date = selectedDate >= new Date().toISOString().slice(0,10) ? selectedDate : new Date().toISOString().slice(0,10);
    document.getElementById('createAt').value=date+'T10:00'; toggleCreateFields(); hideAlert('createAlert'); document.getElementById('createDialog').showModal();
}
function toggleCreateFields(){
    const type=document.getElementById('createType').value, advanced=type!=='follow_up', visit=type==='site_visit', meeting=type==='meeting';
    document.getElementById('followTypeWrap').style.display=advanced?'none':'block';
    document.querySelectorAll('.common-advanced').forEach(el=>el.style.display=advanced?'block':'none');
    document.querySelectorAll('.visit-only').forEach(el=>el.style.display=visit?'block':'none');
    document.querySelectorAll('.meeting-only').forEach(el=>el.style.display=meeting?'block':'none');
    document.querySelectorAll('[data-required="advanced"]').forEach(el=>el.required=advanced);
    document.querySelectorAll('[data-required="visit"]').forEach(el=>el.required=visit);
    document.querySelectorAll('[data-required="meeting"]').forEach(el=>el.required=meeting);
}
function searchLeads(){ clearTimeout(leadSearchTimer); const q=this.value.trim(), box=document.getElementById('leadResults'); if(q.length<2){box.hidden=true;return;} leadSearchTimer=setTimeout(async()=>{
    const response=await fetch(calendarRoutes.leads+'?q='+encodeURIComponent(q),{headers:{Accept:'application/json'}}); const leads=await response.json(); box.hidden=false;
    box.innerHTML=leads.length?leads.map(lead=>`<button type="button" class="lead-result" onclick='selectLead(${JSON.stringify(lead)})'><span><strong>${escapeHtml(lead.name)}</strong><br><small>${escapeHtml(lead.phone||'-')} · ${escapeHtml(lead.status||'-')}</small></span><small>${escapeHtml(lead.owner_name||'Unassigned')}</small></button>`).join(''):'<div class="tac-empty">Customer nahi mila.</div>';
    },300); }
function selectLead(lead){ document.getElementById('leadId').value=lead.id; document.getElementById('leadSearch').value=lead.name; document.getElementById('selectedLead').textContent=`Selected: ${lead.name} · ${lead.phone}`; document.getElementById('leadResults').hidden=true; if(lead.owner_id) document.getElementById('createOwner').value=lead.owner_id; }
async function submitCreate(event){ event.preventDefault(); if(!document.getElementById('leadId').value){showAlert('createAlert','Existing customer select karein.');return;} await submitForm(event.currentTarget,calendarRoutes.store,'POST','createAlert',()=>{closeDialog('createDialog');refreshCalendar();}); }

function openManage(activity){ activeActivity=activity; document.getElementById('manageForm').reset(); document.getElementById('manageSummary').innerHTML=summaryHtml(activity); document.getElementById('manageOwner').value=activity.sales_person_id||''; hideAlert('manageAlert'); toggleManageFields(); document.getElementById('manageDialog').showModal(); }
function toggleManageFields(){ const action=document.getElementById('manageAction').value; document.getElementById('manageDateWrap').hidden=action!=='reschedule'; document.getElementById('manageOwnerWrap').hidden=action!=='reassign'; document.getElementById('manageDate').required=action==='reschedule'; document.getElementById('manageOwner').required=action==='reassign'; document.getElementById('manageReasonLabel').textContent=action==='remark'?'Remark *':'Reason *'; }
async function submitManage(event){ event.preventDefault(); await submitForm(event.currentTarget,`${calendarRoutes.base}/${activeActivity.type}/${activeActivity.record_id}`,'PATCH','manageAlert',()=>{closeDialog('manageDialog');refreshCalendar();}); }
function openComplete(activity){ activeActivity=activity; document.getElementById('completeForm').reset(); const advanced=activity.type!=='follow_up',visit=activity.type==='site_visit'; document.querySelectorAll('.follow-complete').forEach(el=>el.style.display=advanced?'none':'block'); document.querySelectorAll('.advanced-complete').forEach(el=>el.style.display=advanced?'block':'none'); document.querySelectorAll('.visit-complete').forEach(el=>el.style.display=visit?'block':'none'); document.querySelector('[name="outcome"]').required=!advanced; document.querySelector('[name="feedback"]').required=advanced; document.querySelector('[name="rating"]').required=advanced; document.querySelector('[name="proof_photos[]"]').required=advanced; document.querySelector('[name="visited_projects"]').required=visit; document.querySelector('[name="tentative_closing_time"]').required=visit; hideAlert('completeAlert'); document.getElementById('completeDialog').showModal(); }
async function submitComplete(event){ event.preventDefault(); await submitForm(event.currentTarget,`${calendarRoutes.base}/${activeActivity.type}/${activeActivity.record_id}/complete`,'POST','completeAlert',()=>{closeDialog('completeDialog');refreshCalendar();}); }

async function openDetails(type,id){
    const dialog=document.getElementById('detailDialog'), body=document.getElementById('detailBody'); body.innerHTML='<div class="tac-empty"><i class="fas fa-spinner fa-spin"></i> Loading...</div>'; dialog.showModal();
    try { const response=await fetch(`${calendarRoutes.base}/${type}/${id}`,{headers:{Accept:'application/json'}}); if(!response.ok) throw new Error('Details load nahi hui.'); const data=await response.json(),a=data.activity,d=data.lead_details;
        body.innerHTML=`${summaryHtml(a)}${sectionHtml('Contact',[['Customer',a.customer_name],['Mobile',a.phone],['Sales Person',a.sales_person],['Project',a.project]])}${d?renderLeadDetails(d):''}${renderCompletionDetails(data.completion_details)}<div class="detail-section"><h3>Latest Activity Remark</h3><div class="detail-cell"><strong>${escapeHtml(a.remark||'No remark')}</strong></div></div>`;
    } catch(error){body.innerHTML=`<div class="tac-alert" style="display:block">${escapeHtml(error.message)}</div>`;}
}
function renderLeadDetails(details){
    let html=''; [['Customer Profiling',details.profile],['Requirements',details.requirements],['Latest Sales Update',details.sales_update],['Additional Details',details.additional]].forEach(([title,data])=>{ const fields=Object.entries(data||{}).filter(([,value])=>String(value||'').trim()); if(fields.length) html+=sectionHtml(title,fields); });
    if((details.remark_history||[]).length) html+=`<div class="detail-section"><h3>Remark History</h3>${details.remark_history.slice(0,20).map(r=>`<div class="detail-cell"><small>${escapeHtml(r.date||r.created_at||'')} · ${escapeHtml(r.user||r.actor||'')}</small><strong>${escapeHtml(r.remark||r.description||'')}</strong></div>`).join('')}</div>`;
    if((details.timeline||[]).length) html+=`<div class="detail-section"><h3>Lead Timeline</h3>${details.timeline.slice(0,20).map(item=>`<div class="detail-cell"><small>${escapeHtml(item.date||item.created_at||'')} · ${escapeHtml(item.user||item.actor||'')}</small><strong>${escapeHtml(item.title||item.action||item.description||'Activity')}</strong></div>`).join('')}</div>`;
    return html;
}
function renderCompletionDetails(details){
    if(!details||(details.activities||[]).length===0)return '';
    return `<div class="detail-section"><h3>Completed Activity Forms</h3>${details.activities.map(item=>`<div class="detail-cell"><small>${escapeHtml(item.title)} · ${escapeHtml(item.completed_at||'')} · ${escapeHtml(item.completed_by||'')}</small><strong>${Object.entries(item.fields||{}).map(([key,value])=>`${escapeHtml(key)}: ${escapeHtml(value)}`).join('<br>')}</strong></div>`).join('')}</div>`;
}
function summaryHtml(a){return `<div class="detail-grid"><div class="detail-cell"><small>Activity</small><strong>${typeLabel(a.type)}</strong></div><div class="detail-cell"><small>Status</small><strong>${escapeHtml(a.is_overdue?'Overdue':a.status)}</strong></div><div class="detail-cell"><small>Scheduled</small><strong>${formatDateTime(a.scheduled_at)}</strong></div><div class="detail-cell"><small>Completed</small><strong>${formatDateTime(a.completed_at)}</strong></div></div>`;}
function sectionHtml(title,fields){return `<div class="detail-section"><h3>${escapeHtml(title)}</h3><div class="detail-grid">${fields.map(([label,value])=>`<div class="detail-cell"><small>${escapeHtml(label)}</small><strong>${escapeHtml(String(value??'-'))}</strong></div>`).join('')}</div></div>`;}
async function submitForm(form,url,method,alertId,onSuccess){
    hideAlert(alertId); const button=form.querySelector('button[type="submit"]'),old=button.innerHTML; button.disabled=true; button.innerHTML='<i class="fas fa-spinner fa-spin"></i> Saving';
    const data=new FormData(form); if(method==='PATCH') data.append('_method','PATCH');
    try { const response=await fetch(url,{method:'POST',headers:{'X-CSRF-TOKEN':csrfToken,'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},body:data}); const result=await response.json().catch(()=>({})); if(!response.ok){const errors=result.errors?Object.values(result.errors).flat().join(' '):result.message;throw new Error(errors||'Request complete nahi hui.');} onSuccess(result); }
    catch(error){showAlert(alertId,error.message);} finally{button.disabled=false;button.innerHTML=old;}
}
function closeDialog(id){document.getElementById(id).close();}
function showAlert(id,message){const el=document.getElementById(id);el.textContent=message;el.style.display='block';}
function hideAlert(id){const el=document.getElementById(id);el.textContent='';el.style.display='none';}
function typeLabel(type){return ({follow_up:'Follow-up',meeting:'Meeting',site_visit:'Site Visit'})[type]||type;}
function formatTime(value){return new Date(value).toLocaleTimeString('en-IN',{hour:'2-digit',minute:'2-digit'});}
function formatDateTime(value){return value?new Date(value).toLocaleString('en-IN',{dateStyle:'medium',timeStyle:'short'}):'-';}
function escapeHtml(value){return String(value??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'})[c]);}
</script>
@endpush

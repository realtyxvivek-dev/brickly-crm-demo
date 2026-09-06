@php
    $attendanceWidgetMode = $attendanceWidgetMode ?? 'default';
    $isSalesManagerDashboardWidget = $attendanceWidgetMode === 'sales_manager_dashboard';
    $hideOvertimeLink = auth()->check() && (auth()->user()->isAssistantSalesManager() || auth()->user()->isSeniorManager());
    $showAttendanceWidget = auth()->user()?->hasAttendanceRolloutEnabled() ?? false;
@endphp

@if($showAttendanceWidget)
@push('styles')
<style>
    .attendance-widget {
        background: linear-gradient(135deg, #002B45 0%, #006BA6 100%);
        color: #fff;
        border-radius: 18px;
        padding: 18px;
        box-shadow: 0 14px 34px rgba(0, 107, 166, 0.18);
        margin-bottom: 18px;
    }
    .attendance-widget * { box-sizing: border-box; }
    .attendance-widget-top,
    .attendance-widget-stats,
    .attendance-widget-actions {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }
    .attendance-widget-top {
        align-items: flex-start;
        justify-content: space-between;
        margin-bottom: 14px;
    }
    .attendance-widget-title {
        font-size: 20px;
        font-weight: 700;
        margin: 0;
    }
    .attendance-widget-copy {
        color: rgba(255,255,255,.78);
        font-size: 13px;
        margin-top: 4px;
    }
    .attendance-widget-status {
        border-radius: 999px;
        padding: 8px 12px;
        background: rgba(255,255,255,.14);
        font-size: 12px;
        font-weight: 700;
        white-space: nowrap;
    }
    .attendance-widget-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
        margin-bottom: 14px;
    }
    .attendance-widget-card {
        background: rgba(255,255,255,.12);
        border: 1px solid rgba(255,255,255,.14);
        border-radius: 14px;
        padding: 12px;
    }
    .attendance-widget-label {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: rgba(255,255,255,.7);
        margin-bottom: 6px;
    }
    .attendance-widget-value {
        font-size: 18px;
        font-weight: 700;
        line-height: 1.25;
    }
    .attendance-widget-stats {
        margin-bottom: 14px;
    }
    .attendance-widget-stat {
        flex: 1 1 110px;
        min-width: 0;
        background: rgba(255,255,255,.08);
        border-radius: 14px;
        padding: 12px;
    }
    .attendance-widget-stat strong {
        display: block;
        font-size: 22px;
        margin-bottom: 4px;
    }
    .attendance-widget-balance-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
        margin: -2px 0 14px;
    }
    .attendance-widget-balance {
        background: rgba(255,255,255,.10);
        border: 1px solid rgba(255,255,255,.14);
        border-radius: 14px;
        padding: 12px;
        color: #fff;
        text-decoration: none;
    }
    .attendance-widget-balance span {
        display: block;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: rgba(255,255,255,.70);
    }
    .attendance-widget-balance strong {
        display: block;
        margin-top: 6px;
        font-size: 21px;
        line-height: 1;
    }
    .attendance-widget-balance small {
        display: block;
        margin-top: 6px;
        color: rgba(255,255,255,.72);
        font-size: 11px;
        font-weight: 700;
    }
    .attendance-widget-actions {
        align-items: center;
        margin-bottom: 12px;
    }
    .attendance-widget-btn {
        appearance: none;
        border: none;
        border-radius: 12px;
        padding: 12px 16px;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
        min-height: 44px;
    }
    .attendance-widget-btn-primary {
        background: #fff;
        color: #002B45;
    }
    .attendance-widget-btn-secondary {
        background: rgba(255,255,255,.12);
        color: #fff;
        border: 1px solid rgba(255,255,255,.18);
    }
    .attendance-widget-btn[disabled] {
        cursor: not-allowed;
        opacity: .6;
    }
    .attendance-widget-inline {
        display: flex;
        gap: 8px;
        align-items: center;
        flex-wrap: wrap;
    }
    .attendance-widget-note {
        font-size: 12px;
        color: rgba(255,255,255,.78);
    }
    .attendance-widget-compact {
        display: none;
    }
    .attendance-widget-compact-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 16px;
    }
    .attendance-widget-compact-title {
        font-size: 19px;
        font-weight: 700;
        margin: 0;
    }
    .attendance-widget-compact-copy {
        color: rgba(255,255,255,.78);
        font-size: 12px;
        margin-top: 4px;
    }
    .attendance-widget-compact-summary {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
        margin-bottom: 14px;
    }
    .attendance-widget-compact-box {
        background: rgba(255,255,255,.10);
        border: 1px solid rgba(255,255,255,.14);
        border-radius: 14px;
        padding: 12px;
    }
    .attendance-widget-compact-box strong {
        display: block;
        font-size: 16px;
        line-height: 1.3;
    }
    .attendance-widget-compact-box span {
        display: block;
        margin-bottom: 6px;
        font-size: 11px;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: rgba(255,255,255,.7);
    }
    .attendance-widget-compact-links {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 12px;
    }
    .attendance-widget-compact-links a {
        color: #fff;
        font-size: 13px;
        font-weight: 600;
        text-decoration: underline;
        text-underline-offset: 2px;
    }
    .attendance-widget-hidden { display: none !important; }
    .attendance-widget-error {
        background: rgba(239, 68, 68, .18);
        border: 1px solid rgba(254, 202, 202, .26);
        border-radius: 12px;
        padding: 10px 12px;
        font-size: 13px;
        margin-top: 12px;
    }
    .attendance-widget-request {
        margin-top: 12px;
        background: rgba(255,255,255,.12);
        border: 1px solid rgba(255,255,255,.18);
        border-radius: 14px;
        padding: 14px;
    }
    .attendance-widget-request-title {
        font-size: 14px;
        font-weight: 700;
        margin-bottom: 6px;
    }
    .attendance-widget-request-copy {
        color: rgba(255,255,255,.82);
        font-size: 12px;
        line-height: 1.55;
    }
    .attendance-widget-request-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 12px;
    }
    .attendance-widget-request-form {
        margin-top: 12px;
        display: grid;
        gap: 10px;
    }
    .attendance-widget-request-textarea {
        width: 100%;
        min-height: 92px;
        resize: vertical;
        border-radius: 12px;
        border: 1px solid rgba(255,255,255,.18);
        background: rgba(255,255,255,.96);
        color: #0f172a;
        padding: 12px;
        font-size: 14px;
    }
    .attendance-widget-request-meta {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    .attendance-widget-request-pill {
        display: inline-flex;
        align-items: center;
        padding: 6px 10px;
        border-radius: 999px;
        background: rgba(255,255,255,.1);
        border: 1px solid rgba(255,255,255,.14);
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .04em;
    }
    .attendance-camera-modal {
        position: fixed;
        inset: 0;
        background: rgba(0, 43, 69, 0.88);
        z-index: 2000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 16px;
    }
    .attendance-camera-panel {
        width: min(100%, 420px);
        background: #fff;
        border-radius: 18px;
        overflow: hidden;
        box-shadow: 0 18px 44px rgba(0, 0, 0, .24);
    }
    .attendance-camera-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 18px 10px;
        color: #002B45;
    }
    .attendance-camera-close {
        border: none;
        background: transparent;
        color: #002B45;
        font-size: 22px;
        cursor: pointer;
    }
    .attendance-camera-body {
        padding: 0 18px 18px;
    }
    .attendance-camera-video,
    .attendance-camera-preview {
        width: 100%;
        aspect-ratio: 3 / 4;
        border-radius: 16px;
        background: #0f172a;
        object-fit: cover;
        display: block;
    }
    .attendance-camera-actions {
        display: flex;
        gap: 10px;
        margin-top: 14px;
        flex-wrap: wrap;
    }
    .attendance-camera-actions .attendance-widget-btn {
        flex: 1 1 140px;
        justify-content: center;
    }
    .attendance-camera-actions .attendance-widget-btn-primary {
        background: #006BA6;
        color: #fff;
    }
    .attendance-camera-actions .attendance-widget-btn-secondary {
        background: #e2e8f0;
        color: #0f172a;
        border: 1px solid #cbd5e1;
    }
    .attendance-camera-note {
        font-size: 12px;
        color: #64748b;
        margin-top: 10px;
    }
    @media (max-width: 767px) {
        .attendance-widget-grid {
            grid-template-columns: 1fr;
        }
        .attendance-widget-actions {
            flex-direction: column;
            align-items: stretch;
        }
        .attendance-widget-balance-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .attendance-widget-btn {
            width: 100%;
        }
        .attendance-camera-actions .attendance-widget-btn {
            flex-basis: 100%;
        }
        .attendance-widget--sales-manager-dashboard .attendance-widget-full {
            display: none;
        }
        .attendance-widget--sales-manager-dashboard .attendance-widget-compact {
            display: block;
        }
    }
</style>
@endpush

<div class="attendance-widget {{ $isSalesManagerDashboardWidget ? 'attendance-widget--sales-manager-dashboard' : '' }}" id="attendanceWidget">
    <div class="attendance-widget-full">
        <div class="attendance-widget-top">
            <div>
                <h3 class="attendance-widget-title">Attendance</h3>
                <div class="attendance-widget-copy" id="attendanceInfoLine">Loading attendance status...</div>
            </div>
            <div class="attendance-widget-status" id="attendanceStatusBadge">Loading</div>
        </div>

        <div class="attendance-widget-grid">
            <div class="attendance-widget-card">
                <div class="attendance-widget-label">Punch In</div>
                <div class="attendance-widget-value" id="attendancePunchInValue">--</div>
            </div>
            <div class="attendance-widget-card">
                <div class="attendance-widget-label">Punch Out</div>
                <div class="attendance-widget-value" id="attendancePunchOutValue">--</div>
            </div>
        </div>

        <div class="attendance-widget-stats">
            <div class="attendance-widget-stat"><span class="attendance-widget-note">Present</span><strong id="attendancePresentCount">0</strong></div>
            <div class="attendance-widget-stat"><span class="attendance-widget-note">Late</span><strong id="attendanceLateCount">0</strong></div>
            <div class="attendance-widget-stat"><span class="attendance-widget-note">Half Day</span><strong id="attendanceHalfDayCount">0</strong></div>
            <div class="attendance-widget-stat"><span class="attendance-widget-note">Absent</span><strong id="attendanceAbsentCount">0</strong></div>
        </div>

        @if(($leaveBalances ?? collect())->isNotEmpty())
            <div class="attendance-widget-balance-grid" aria-label="Leave balance">
                @foreach($leaveBalances as $balance)
                    <a href="{{ route('attendance.leaves') }}" class="attendance-widget-balance">
                        <span>{{ $balance->leaveType?->code ?: $balance->leaveType?->name }}</span>
                        <strong>{{ rtrim(rtrim(number_format((float) $balance->remaining, 1), '0'), '.') }}</strong>
                        <small>Balance</small>
                    </a>
                @endforeach
            </div>
        @endif

        <div class="attendance-widget-actions">
            <button type="button" class="attendance-widget-btn attendance-widget-btn-primary" id="attendancePunchInBtn">Punch In</button>
            <button type="button" class="attendance-widget-btn attendance-widget-btn-secondary" id="attendancePunchOutBtn">Punch Out</button>
        </div>

        <div class="attendance-widget-inline">
            <span class="attendance-widget-note" id="attendanceOfficeLine">Office: --</span>
            <span class="attendance-widget-note" id="attendancePhotoLine">Photo: --</span>
            <a href="{{ route('attendance.leaves') }}" class="attendance-widget-note" style="text-decoration:underline;color:#fff;">Apply Leave</a>
            <a href="{{ route('attendance.regularizations') }}" class="attendance-widget-note" style="text-decoration:underline;color:#fff;">Regularize</a>
            @unless($hideOvertimeLink)
            <a href="{{ route('attendance.overtimes') }}" class="attendance-widget-note" style="text-decoration:underline;color:#fff;">My Overtime</a>
            @endunless
            <a href="{{ route('attendance.payslips') }}" class="attendance-widget-note" style="text-decoration:underline;color:#fff;">My Payslips</a>
        </div>
    </div>

    <div class="attendance-widget-compact">
        <div class="attendance-widget-compact-top">
            <div>
                <h3 class="attendance-widget-compact-title">Today Attendance</h3>
                <div class="attendance-widget-compact-copy" id="attendanceCompactInfoLine">Loading attendance status...</div>
            </div>
            <div class="attendance-widget-status" id="attendanceCompactStatusBadge">Loading</div>
        </div>

        <div class="attendance-widget-compact-summary">
            <div class="attendance-widget-compact-box">
                <span>Today</span>
                <strong id="attendanceCompactTimeline">Not marked yet</strong>
            </div>
            <div class="attendance-widget-compact-box">
                <span>Office</span>
                <strong id="attendanceCompactOfficeStatus">Office not mapped</strong>
            </div>
        </div>

        <button type="button" class="attendance-widget-btn attendance-widget-btn-primary" id="attendanceCompactActionBtn">Punch In</button>

        <div class="attendance-widget-compact-links">
            <a href="{{ route('attendance.leaves') }}">Leave</a>
            <a href="{{ route('attendance.regularizations') }}">Regularization</a>
        </div>
    </div>

    <div class="attendance-widget-error attendance-widget-hidden" id="attendanceError"></div>
    <div class="attendance-widget-request attendance-widget-hidden" id="attendanceOutsideRequestBox">
        <div class="attendance-widget-request-title" id="attendanceOutsideRequestTitle">Please punch in at office location</div>
        <div class="attendance-widget-request-copy" id="attendanceOutsideRequestInfo"></div>
        <div class="attendance-widget-request-meta" id="attendanceOutsideRequestMeta"></div>
        <div class="attendance-widget-request-actions">
            <button type="button" class="attendance-widget-btn attendance-widget-btn-secondary" id="attendanceOutsideRetryBtn">Try Again</button>
            <button type="button" class="attendance-widget-btn attendance-widget-btn-secondary" id="attendanceTestLocationBtn">Test Location</button>
            <button type="button" class="attendance-widget-btn attendance-widget-btn-primary" id="attendanceOutsideToggleBtn">Outside Request</button>
        </div>
        <div class="attendance-widget-request-form attendance-widget-hidden" id="attendanceOutsideRequestForm">
            <textarea id="attendanceOutsideReason" class="attendance-widget-request-textarea" placeholder="Reason for outside punch"></textarea>
            <button type="button" class="attendance-widget-btn attendance-widget-btn-primary" id="attendanceOutsideSubmitBtn">Submit Outside Request</button>
        </div>
    </div>
</div>

<div class="attendance-camera-modal attendance-widget-hidden" id="attendanceCameraModal">
    <div class="attendance-camera-panel">
        <div class="attendance-camera-head">
            <div>
                <div class="text-lg font-semibold">Capture Attendance Photo</div>
                <div class="attendance-camera-note">Only live camera capture is allowed.</div>
            </div>
            <button type="button" class="attendance-camera-close" id="attendanceCameraClose">&times;</button>
        </div>
        <div class="attendance-camera-body">
            <video id="attendanceCameraVideo" class="attendance-camera-video" autoplay playsinline muted></video>
            <img id="attendanceCameraPreview" class="attendance-camera-preview attendance-widget-hidden" alt="Captured attendance photo preview">
            <canvas id="attendanceCameraCanvas" class="attendance-widget-hidden"></canvas>
            <div class="attendance-camera-actions">
                <button type="button" class="attendance-widget-btn attendance-widget-btn-primary" id="attendanceCameraCaptureBtn">Capture</button>
                <button type="button" class="attendance-widget-btn attendance-widget-btn-secondary attendance-widget-hidden" id="attendanceCameraRetakeBtn">Retake</button>
                <button type="button" class="attendance-widget-btn attendance-widget-btn-secondary" id="attendanceCameraUseBtn" disabled>Punch In Now</button>
            </div>
            <div class="attendance-camera-note" id="attendanceCameraStatus">Camera not opened yet.</div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const widget = document.getElementById('attendanceWidget');
    if (!widget) return;

    const token = document.querySelector('meta[name="api-token"]')?.content || '';
    const headers = {
        'Accept': 'application/json',
        'Authorization': token ? `Bearer ${token}` : '',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
    };

    const els = {
        status: document.getElementById('attendanceStatusBadge'),
        info: document.getElementById('attendanceInfoLine'),
        compactStatus: document.getElementById('attendanceCompactStatusBadge'),
        compactInfo: document.getElementById('attendanceCompactInfoLine'),
        compactTimeline: document.getElementById('attendanceCompactTimeline'),
        compactOfficeStatus: document.getElementById('attendanceCompactOfficeStatus'),
        punchIn: document.getElementById('attendancePunchInValue'),
        punchOut: document.getElementById('attendancePunchOutValue'),
        present: document.getElementById('attendancePresentCount'),
        late: document.getElementById('attendanceLateCount'),
        halfDay: document.getElementById('attendanceHalfDayCount'),
        absent: document.getElementById('attendanceAbsentCount'),
        office: document.getElementById('attendanceOfficeLine'),
        photo: document.getElementById('attendancePhotoLine'),
        error: document.getElementById('attendanceError'),
        outsideRequestBox: document.getElementById('attendanceOutsideRequestBox'),
        outsideRequestTitle: document.getElementById('attendanceOutsideRequestTitle'),
        outsideRequestInfo: document.getElementById('attendanceOutsideRequestInfo'),
        outsideRequestMeta: document.getElementById('attendanceOutsideRequestMeta'),
        outsideRetryBtn: document.getElementById('attendanceOutsideRetryBtn'),
        testLocationBtn: document.getElementById('attendanceTestLocationBtn'),
        outsideToggleBtn: document.getElementById('attendanceOutsideToggleBtn'),
        outsideRequestForm: document.getElementById('attendanceOutsideRequestForm'),
        outsideReason: document.getElementById('attendanceOutsideReason'),
        outsideSubmitBtn: document.getElementById('attendanceOutsideSubmitBtn'),
        punchInBtn: document.getElementById('attendancePunchInBtn'),
        punchOutBtn: document.getElementById('attendancePunchOutBtn'),
        compactActionBtn: document.getElementById('attendanceCompactActionBtn'),
        cameraModal: document.getElementById('attendanceCameraModal'),
        cameraVideo: document.getElementById('attendanceCameraVideo'),
        cameraPreview: document.getElementById('attendanceCameraPreview'),
        cameraCanvas: document.getElementById('attendanceCameraCanvas'),
        cameraClose: document.getElementById('attendanceCameraClose'),
        cameraCaptureBtn: document.getElementById('attendanceCameraCaptureBtn'),
        cameraRetakeBtn: document.getElementById('attendanceCameraRetakeBtn'),
        cameraUseBtn: document.getElementById('attendanceCameraUseBtn'),
        cameraStatus: document.getElementById('attendanceCameraStatus'),
    };

    let todayState = null;
    let cameraStream = null;
    let capturedPhotoBlob = null;
    let outsideRequestContext = null;
    const deviceFingerprint = `${navigator.userAgent || 'unknown-agent'}|${screen.width || 0}x${screen.height || 0}`.slice(0, 240);

    function setError(message) {
        els.error.textContent = message;
        els.error.classList.toggle('attendance-widget-hidden', !message);
    }

    function setCameraStatus(message) {
        if (els.cameraStatus) {
            els.cameraStatus.textContent = message;
        }
    }

    function resolveApiErrorMessage(data, fallback) {
        const flattenedValidationErrors = data?.errors
            ? Object.values(data.errors).flat().filter(Boolean)
            : [];

        return data?.message
            && data.message !== 'The given data was invalid.'
            ? data.message
            : flattenedValidationErrors[0]
            || data?.errors?.attendance?.[0]
            || data?.errors?.photo?.[0]
            || data?.errors?.reason?.[0]
            || fallback;
    }

    function formatDistance(value) {
        if (value === null || value === undefined || value === '') return null;
        const numeric = Number(value);
        if (Number.isNaN(numeric)) return null;
        if (numeric >= 1000) return `${(numeric / 1000).toFixed(2)} km away`;
        return `${Math.round(numeric)} m away`;
    }

    function formatCoordinate(value) {
        const numeric = Number(value);
        if (value === null || value === undefined || value === '' || Number.isNaN(numeric)) {
            return null;
        }

        return numeric.toFixed(6);
    }

    function hideOutsideRequest() {
        outsideRequestContext = null;
        els.outsideRequestBox?.classList.add('attendance-widget-hidden');
        els.outsideRequestForm?.classList.add('attendance-widget-hidden');
        if (els.outsideReason) {
            els.outsideReason.value = '';
        }
    }

    function showOutsideRequest(context) {
        outsideRequestContext = context || null;
        if (!els.outsideRequestBox || !context) return;

        const pieces = [];
        if (Array.isArray(context.debug_pieces)) {
            pieces.push(...context.debug_pieces);
        }
        if (context.office_name) {
            pieces.push(`Office: ${context.office_name}`);
        }
        if (formatDistance(context.geo_distance_meters)) {
            pieces.push(formatDistance(context.geo_distance_meters));
        }
        const latitude = formatCoordinate(context.latitude);
        const longitude = formatCoordinate(context.longitude);
        if (latitude && longitude) {
            pieces.push(`Lat ${latitude}`);
            pieces.push(`Lng ${longitude}`);
        }

        const locationMissing = !!context.location_missing;
        if (els.outsideRequestTitle) {
            els.outsideRequestTitle.textContent = locationMissing
                ? 'Enable live location to punch in'
                : 'Please punch in at office location';
        }

        if (locationMissing) {
            els.outsideRequestInfo.textContent = context.can_request
                ? 'Live location could not be read from this browser/device. Enable location services, allow site location access, then try again. If you still need attendance, send an outside request for HR approval.'
                : 'Live location could not be read from this browser/device. Enable location services and site location access, then try again.';
        } else {
            els.outsideRequestInfo.textContent = context.can_request
                ? 'You are outside the office radius. You can retry from office or send an outside punch request for HR approval.'
                : 'You are outside the office radius and outside requests are disabled for this user.';
        }
        els.outsideRequestMeta.innerHTML = '';
        pieces.forEach((piece) => {
            const pill = document.createElement('span');
            pill.className = 'attendance-widget-request-pill';
            pill.textContent = piece;
            els.outsideRequestMeta.appendChild(pill);
        });
        if (locationMissing) {
            const pill = document.createElement('span');
            pill.className = 'attendance-widget-request-pill';
            pill.textContent = 'Location unavailable';
            els.outsideRequestMeta.appendChild(pill);
            if (context.location_error_code) {
                const errorCodePill = document.createElement('span');
                errorCodePill.className = 'attendance-widget-request-pill';
                errorCodePill.textContent = `Geo error ${context.location_error_code}`;
                els.outsideRequestMeta.appendChild(errorCodePill);
            }
            if (context.location_error_message) {
                const errorMessagePill = document.createElement('span');
                errorMessagePill.className = 'attendance-widget-request-pill';
                errorMessagePill.textContent = String(context.location_error_message).slice(0, 80);
                els.outsideRequestMeta.appendChild(errorMessagePill);
            }
        }
        els.outsideToggleBtn.classList.toggle('attendance-widget-hidden', !!context.diagnostic_only || !context.can_request);
        els.outsideRequestForm.classList.add('attendance-widget-hidden');
        els.outsideRequestBox.classList.remove('attendance-widget-hidden');
    }

    function formatDateTime(value) {
        if (!value) return '--';
        const date = new Date(value);
        return Number.isNaN(date.getTime()) ? '--' : date.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' });
    }

    function compactStatusText(record) {
        if (!record?.first_punch_in_at) return 'Not Punched';
        if (record?.first_punch_in_at && !record?.last_punch_out_at) return 'Punched In';
        if (record?.last_punch_out_at) return 'Punched Out';
        return 'Not Punched';
    }

    function compactTimelineText(record) {
        if (!record?.first_punch_in_at) return 'Not marked yet';
        if (record?.first_punch_in_at && !record?.last_punch_out_at) {
            return `In ${formatDateTime(record.first_punch_in_at)}`;
        }
        if (record?.last_punch_out_at) {
            return `Out ${formatDateTime(record.last_punch_out_at)}`;
        }
        return 'Not marked yet';
    }

    function compactOfficeStatusText(payload) {
        if (!payload?.office?.name) return 'Office not mapped';

        if (payload?.record?.outside_punch_status === 'approved_permission') return 'Outside allowed';
        if (payload?.record?.outside_punch_status === 'approved_request') return 'Outside approved';

        const flags = Array.isArray(payload?.record?.suspicion_flags) ? payload.record.suspicion_flags : [];
        if (flags.includes('outside_radius')) return 'Outside office';
        if (payload?.record?.first_punch_in_at || payload?.record?.last_punch_out_at) return 'Office matched';
        return `Office ready: ${payload.office.name}`;
    }

    function syncCompactAction(record, configured) {
        if (!els.compactActionBtn) return;
        const allowMultiPunchTest = !!todayState?.allow_multi_punch_test;

        if (!configured) {
            els.compactActionBtn.textContent = 'Attendance Not Ready';
            els.compactActionBtn.disabled = true;
            return;
        }

        if (!record?.first_punch_in_at) {
            els.compactActionBtn.textContent = 'Punch In';
            els.compactActionBtn.disabled = false;
            return;
        }

        if (record?.first_punch_in_at && !record?.last_punch_out_at) {
            els.compactActionBtn.textContent = 'Punch Out';
            els.compactActionBtn.disabled = false;
            return;
        }

        if (allowMultiPunchTest) {
            els.compactActionBtn.textContent = 'Punch In Again';
            els.compactActionBtn.disabled = false;
            return;
        }

        els.compactActionBtn.textContent = 'Attendance Complete';
        els.compactActionBtn.disabled = true;
    }

    function render(payload) {
        todayState = payload;
        hideOutsideRequest();
        const record = payload.record || {};
        const summary = payload.month_summary || {};
        const policy = payload.policy || {};
        const allowMultiPunchTest = !!payload.allow_multi_punch_test;

        els.status.textContent = record.status_label || (payload.configured ? 'Not Marked' : 'Not Configured');
        els.info.textContent = record.info_line || (payload.configured ? 'Attendance window active.' : 'Attendance policy not configured.');
        if (els.compactStatus) {
            els.compactStatus.textContent = compactStatusText(record);
        }
        if (els.compactInfo) {
            els.compactInfo.textContent = record.info_line || (payload.configured ? 'Attendance window active.' : 'Attendance policy not configured.');
        }
        if (els.compactTimeline) {
            els.compactTimeline.textContent = compactTimelineText(record);
        }
        if (els.compactOfficeStatus) {
            els.compactOfficeStatus.textContent = compactOfficeStatusText(payload);
        }
        els.punchIn.textContent = formatDateTime(record.first_punch_in_at);
        els.punchOut.textContent = formatDateTime(record.last_punch_out_at);
        els.present.textContent = summary.present || 0;
        els.late.textContent = summary.late || 0;
        els.halfDay.textContent = summary.half_day || 0;
        els.absent.textContent = summary.absent || 0;
        const outsideDistance = formatDistance(record.outside_punch_distance_meters);
        if (record.outside_punch_status === 'approved_permission') {
            els.office.textContent = `Office: Outside allowed${outsideDistance ? ` (${outsideDistance})` : ''}`;
        } else if (record.outside_punch_status === 'approved_request') {
            els.office.textContent = `Office: Outside approved${outsideDistance ? ` (${outsideDistance})` : ''}`;
        } else {
            els.office.textContent = `Office: ${payload.office?.name || 'Not mapped'}`;
        }
        els.photo.textContent = `Photo: ${policy.photo_required ? 'Required on punch-in' : 'Optional'}`;
        els.punchInBtn.disabled = !payload.configured || (!!record.first_punch_in_at && !allowMultiPunchTest);
        els.punchOutBtn.disabled = !payload.configured || !record.first_punch_in_at || (!!record.last_punch_out_at && !allowMultiPunchTest);
        syncCompactAction(record, payload.configured);
    }

    function resetCapturedPhoto() {
        capturedPhotoBlob = null;
        els.cameraPreview.classList.add('attendance-widget-hidden');
        els.cameraPreview.removeAttribute('src');
        els.cameraVideo.classList.remove('attendance-widget-hidden');
        els.cameraCaptureBtn.classList.remove('attendance-widget-hidden');
        els.cameraRetakeBtn.classList.add('attendance-widget-hidden');
        els.cameraUseBtn.disabled = true;
        els.photo.textContent = `Photo: ${todayState?.policy?.photo_required ? 'Required on punch-in' : 'Optional'}`;
    }

    function stopCamera() {
        if (cameraStream) {
            cameraStream.getTracks().forEach((track) => track.stop());
            cameraStream = null;
        }
        els.cameraVideo.srcObject = null;
    }

    async function openCamera() {
        if (!navigator.mediaDevices?.getUserMedia) {
            throw new Error('Camera access is not supported on this device/browser.');
        }

        resetCapturedPhoto();
        setCameraStatus('Opening camera...');
        try {
            cameraStream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: 'user',
                    width: { ideal: 720, max: 1280 },
                    height: { ideal: 960, max: 1280 },
                },
                audio: false,
            });
        } catch (error) {
            cameraStream = await navigator.mediaDevices.getUserMedia({
                video: true,
                audio: false,
            });
        }
        els.cameraVideo.srcObject = cameraStream;
        els.cameraModal.classList.remove('attendance-widget-hidden');
        setCameraStatus('Camera ready. Capture a live photo.');
    }

    function closeCamera() {
        stopCamera();
        els.cameraModal.classList.add('attendance-widget-hidden');
        setCameraStatus('Camera closed.');
    }

    async function capturePhoto() {
        if (!cameraStream) {
            throw new Error('Camera is not active.');
        }

        const video = els.cameraVideo;
        const canvas = els.cameraCanvas;
        const sourceWidth = video.videoWidth || 720;
        const sourceHeight = video.videoHeight || 960;
        const captureMaxHeight = 1280;
        const captureScale = sourceHeight > captureMaxHeight ? (captureMaxHeight / sourceHeight) : 1;
        const width = Math.max(1, Math.round(sourceWidth * captureScale));
        const height = Math.max(1, Math.round(sourceHeight * captureScale));
        canvas.width = width;
        canvas.height = height;
        canvas.getContext('2d').drawImage(video, 0, 0, width, height);

        capturedPhotoBlob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.9));
        if (!capturedPhotoBlob) {
            throw new Error('Unable to capture photo. Please try again.');
        }

        const previewUrl = URL.createObjectURL(capturedPhotoBlob);
        els.cameraPreview.src = previewUrl;
        els.cameraPreview.classList.remove('attendance-widget-hidden');
        els.cameraVideo.classList.add('attendance-widget-hidden');
        els.cameraCaptureBtn.classList.add('attendance-widget-hidden');
        els.cameraRetakeBtn.classList.remove('attendance-widget-hidden');
        els.cameraUseBtn.disabled = false;
        setCameraStatus('Photo captured. Punch In Now to submit attendance.');
    }

    async function loadToday() {
        setError('');
        const response = await fetch('/api/attendance/today', { headers, credentials: 'same-origin' });
        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Failed to load attendance status.');
        }
        render(data.data);
    }

    function getLocation() {
        return new Promise((resolve) => {
            if (!navigator.geolocation) {
                resolve({
                    latitude: null,
                    longitude: null,
                    timedOut: false,
                    errorCode: 'unsupported',
                    errorMessage: 'Geolocation API not supported',
                });
                return;
            }

            let settled = false;
            const finish = (payload) => {
                if (settled) return;
                settled = true;
                resolve(payload);
            };

            const fallbackTimer = window.setTimeout(() => {
                finish({
                    latitude: null,
                    longitude: null,
                    timedOut: true,
                    errorCode: 'timeout',
                    errorMessage: 'Geolocation timed out',
                });
            }, 4500);

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    window.clearTimeout(fallbackTimer);
                    finish({
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude,
                        timedOut: false,
                        errorCode: null,
                        errorMessage: null,
                    });
                },
                (error) => {
                    window.clearTimeout(fallbackTimer);
                    finish({
                        latitude: null,
                        longitude: null,
                        timedOut: false,
                        errorCode: error?.code ?? 'unknown',
                        errorMessage: error?.message ?? 'Unable to read location',
                    });
                },
                { enableHighAccuracy: true, timeout: 4000, maximumAge: 60000 }
            );
        });
    }

    async function getLocationPermissionState() {
        if (!navigator.permissions?.query) {
            return 'unsupported';
        }

        try {
            const result = await navigator.permissions.query({ name: 'geolocation' });
            return result?.state || 'unknown';
        } catch (error) {
            return 'unknown';
        }
    }

    function getClientTimezone() {
        try {
            return Intl.DateTimeFormat().resolvedOptions().timeZone || 'Asia/Kolkata';
        } catch (error) {
            return 'Asia/Kolkata';
        }
    }

    function getLocalDateTimeString(date = new Date()) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        const seconds = String(date.getSeconds()).padStart(2, '0');

        return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
    }

    async function runLocationDiagnostic() {
        const permissionState = await getLocationPermissionState();
        const location = await getLocation();

        showOutsideRequest({
            can_request: !!outsideRequestContext?.can_request,
            punch_type: outsideRequestContext?.punch_type || 'in',
            requested_at: getLocalDateTimeString(),
            client_timezone: getClientTimezone(),
            office_name: todayState?.office?.name || outsideRequestContext?.office_name || null,
            office_location_id: todayState?.office?.id || outsideRequestContext?.office_location_id || null,
            latitude: location.latitude,
            longitude: location.longitude,
            geo_distance_meters: outsideRequestContext?.geo_distance_meters || null,
            location_missing: location.latitude === null || location.longitude === null,
            location_error_code: location.errorCode,
            location_error_message: location.errorMessage,
            debug_pieces: [
                `Permission ${permissionState}`,
                location.latitude !== null && location.longitude !== null
                    ? `Coordinates ${Number(location.latitude).toFixed(6)}, ${Number(location.longitude).toFixed(6)}`
                    : 'Coordinates missing',
            ],
            diagnostic_only: true,
        });

        setError(location.latitude !== null && location.longitude !== null
            ? `Test location success: ${Number(location.latitude).toFixed(6)}, ${Number(location.longitude).toFixed(6)}`
            : 'Test location failed. Browser did not return coordinates.');
    }

    async function submitPunch(endpoint, includePhoto) {
        setError('');
        hideOutsideRequest();
        const location = await getLocation();
        if (includePhoto && location.timedOut && els.cameraStatus) {
            setCameraStatus('Location timed out. Submitting punch-in without live location...');
        }
        if (includePhoto && !capturedPhotoBlob) {
            throw new Error('Live camera photo is required before punch-in.');
        }
        const formData = new FormData();
        formData.append('source', 'web');
        formData.append('device_fingerprint', deviceFingerprint);
        if (location.latitude !== null) formData.append('latitude', location.latitude);
        if (location.longitude !== null) formData.append('longitude', location.longitude);
        if (includePhoto && capturedPhotoBlob) {
            formData.append('photo', capturedPhotoBlob, `attendance-camera-${Date.now()}.jpg`);
            formData.append('photo_capture_mode', 'camera');
        }

        const response = await fetch(endpoint, {
            method: 'POST',
            headers,
            body: formData,
            credentials: 'same-origin',
        });

        let data = {};
        try {
            data = await response.json();
        } catch (error) {
            data = {};
        }
        if (!response.ok || !data.success) {
            const errorMessage = resolveApiErrorMessage(data, 'Attendance action failed.');
            if (data?.code === 'outside_punch_required') {
                closeCamera();
                capturedPhotoBlob = null;
                showOutsideRequest({
                    ...(data.data || {}),
                    latitude: data?.data?.latitude ?? location.latitude,
                    longitude: data?.data?.longitude ?? location.longitude,
                    location_error_code: data?.data?.location_error_code ?? location.errorCode,
                    location_error_message: data?.data?.location_error_message ?? location.errorMessage,
                    requested_at: getLocalDateTimeString(),
                    client_timezone: getClientTimezone(),
                });
            } else if (includePhoto) {
                setCameraStatus(errorMessage);
            }
            throw new Error(errorMessage);
        }

        capturedPhotoBlob = null;
        closeCamera();
        els.photo.textContent = `Photo: ${todayState?.policy?.photo_required ? 'Required' : 'Optional'}`;
        await loadToday();
    }

    async function submitOutsideRequest() {
        if (!outsideRequestContext?.can_request) {
            throw new Error('Outside requests are disabled for this user.');
        }

        const reason = (els.outsideReason?.value || '').trim();
        if (!reason) {
            throw new Error('Reason is required for outside request.');
        }

        const payload = {
            punch_type: outsideRequestContext.punch_type || 'in',
            requested_at: outsideRequestContext.requested_at || getLocalDateTimeString(),
            client_timezone: outsideRequestContext.client_timezone || getClientTimezone(),
            latitude: outsideRequestContext.latitude ?? null,
            longitude: outsideRequestContext.longitude ?? null,
            office_location_id: outsideRequestContext.office_location_id ?? null,
            geo_distance_meters: outsideRequestContext.geo_distance_meters ?? null,
            reason,
        };

        const response = await fetch('/api/attendance/outside-punch-requests', {
            method: 'POST',
            headers: {
                ...headers,
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        });
        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(resolveApiErrorMessage(data, 'Failed to submit outside request.'));
        }

        hideOutsideRequest();
        setError('');
        window.alert(data.message || 'Outside punch request submitted.');
    }

    els.punchInBtn.addEventListener('click', async () => {
        try {
            els.punchInBtn.disabled = true;
            if (todayState?.policy?.photo_required) {
                await openCamera();
                return;
            }

            await submitPunch('/api/attendance/punch-in', false);
        } catch (error) {
            const message = error.message || 'Punch-in failed.';
            setError(message === 'Could not start video source'
                ? 'Camera permission/source issue. Please update app and allow Camera permission.'
                : message);
        } finally {
            if (todayState?.configured && !todayState?.policy?.photo_required) {
                els.punchInBtn.disabled = !!todayState?.record?.first_punch_in_at && !todayState?.allow_multi_punch_test;
            }
        }
    });

    if (els.compactActionBtn) {
        els.compactActionBtn.addEventListener('click', async () => {
            if (!todayState?.configured) return;

            const hasPunchIn = !!todayState?.record?.first_punch_in_at;
            const hasPunchOut = !!todayState?.record?.last_punch_out_at;

            if (!hasPunchIn) {
                els.punchInBtn.click();
                return;
            }

            if (!hasPunchOut) {
                els.punchOutBtn.click();
            }
        });
    }

    els.cameraClose.addEventListener('click', () => {
        closeCamera();
        if (todayState?.configured) {
            els.punchInBtn.disabled = !!todayState?.record?.first_punch_in_at && !todayState?.allow_multi_punch_test;
        }
    });

    els.cameraCaptureBtn.addEventListener('click', async () => {
        try {
            await capturePhoto();
        } catch (error) {
            setError(error.message || 'Unable to capture photo.');
        }
    });

    els.cameraRetakeBtn.addEventListener('click', async () => {
        try {
            resetCapturedPhoto();
            if (!cameraStream) {
                await openCamera();
            } else {
                setCameraStatus('Camera ready. Capture a live photo.');
            }
        } catch (error) {
            setError(error.message || 'Unable to retake photo.');
            setCameraStatus(error.message || 'Unable to retake photo.');
        }
    });

    els.cameraUseBtn.addEventListener('click', () => {
        (async () => {
            try {
                if (!capturedPhotoBlob) {
                    setError('Capture a photo before punch-in.');
                    return;
                }

                els.cameraUseBtn.disabled = true;
                setCameraStatus('Submitting punch-in...');
                await submitPunch('/api/attendance/punch-in', true);
            } catch (error) {
                els.cameraUseBtn.disabled = false;
                setError(error.message || 'Punch-in failed.');
                setCameraStatus(error.message || 'Punch-in failed.');
            }
        })();
    });

    els.cameraModal.addEventListener('click', (event) => {
        if (event.target === els.cameraModal) {
            closeCamera();
            if (todayState?.configured) {
                els.punchInBtn.disabled = !!todayState?.record?.first_punch_in_at && !todayState?.allow_multi_punch_test;
            }
        }
    });

    els.punchOutBtn.addEventListener('click', async () => {
        try {
            els.punchOutBtn.disabled = true;
            await submitPunch('/api/attendance/punch-out', false);
        } catch (error) {
            setError(error.message || 'Punch-out failed.');
        } finally {
            if (todayState?.configured) {
                els.punchOutBtn.disabled = !todayState?.record?.first_punch_in_at || (!!todayState?.record?.last_punch_out_at && !todayState?.allow_multi_punch_test);
            }
        }
    });

    els.outsideRetryBtn?.addEventListener('click', () => {
        hideOutsideRequest();
        setError('');
    });

    els.testLocationBtn?.addEventListener('click', async () => {
        try {
            els.testLocationBtn.disabled = true;
            hideOutsideRequest();
            setError('Testing live location...');
            await runLocationDiagnostic();
        } catch (error) {
            setError(error.message || 'Location test failed.');
        } finally {
            els.testLocationBtn.disabled = false;
        }
    });

    els.outsideToggleBtn?.addEventListener('click', () => {
        els.outsideRequestForm?.classList.toggle('attendance-widget-hidden');
        els.outsideReason?.focus();
    });

    els.outsideSubmitBtn?.addEventListener('click', async () => {
        try {
            els.outsideSubmitBtn.disabled = true;
            await submitOutsideRequest();
        } catch (error) {
            setError(error.message || 'Outside request failed.');
        } finally {
            els.outsideSubmitBtn.disabled = false;
        }
    });

    loadToday().catch((error) => setError(error.message || 'Unable to load attendance widget.'));
})();
</script>
@endpush
@endif

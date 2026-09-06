@extends('layouts.app')
@section('title', 'FCM Diagnostic')
@section('page-title', 'FCM Push Diagnostic')
@section('page-subtitle', 'Complete check of Firebase Cloud Messaging setup')

@section('content')
<style>
    .diag-card { background: #fff; border-radius: 12px; padding: 24px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
    .diag-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #f0f0f0; flex-wrap: wrap; gap: 4px; }
    .diag-row:last-child { border-bottom: none; }
    .diag-label { font-weight: 500; color: #374151; }
    .diag-val { font-family: monospace; font-size: 13px; max-width: 60%; text-align: right; word-break: break-all; }
    .badge-ok { background: #dcfce7; color: #166534; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
    .badge-fail { background: #fee2e2; color: #991b1b; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
    .badge-warn { background: #fef3c7; color: #92400e; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
    .step-title { font-size: 16px; font-weight: 700; margin-bottom: 12px; color: #1f2937; }
    .log-box { background: #1e1e2e; color: #a6e3a1; font-family: monospace; font-size: 12px; padding: 16px; border-radius: 8px; max-height: 400px; overflow-y: auto; white-space: pre-wrap; word-break: break-all; }
    #liveLog { min-height: 100px; }
    .btn-run { background: linear-gradient(135deg, #063A1C, #205A44); color: #fff; border: none; padding: 12px 28px; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 14px; display: inline-block; }
    .btn-run:hover { opacity: 0.9; }
    .btn-run:disabled { opacity: 0.5; cursor: not-allowed; }
    .btn-orange { background: linear-gradient(135deg, #ea580c, #c2410c); }
    .btn-blue { background: linear-gradient(135deg, #2563eb, #1d4ed8); }
    .btn-red { background: linear-gradient(135deg, #dc2626, #b91c1c); }
    .action-bar { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
    @media (max-width: 767px) {
        .diag-val { max-width: 100%; text-align: left; }
        .action-bar { flex-direction: column; }
        .action-bar button, .action-bar .btn-run { width: 100%; text-align: center; }
    }
</style>

@if(session('success'))
<div style="background: #dcfce7; color: #166534; padding: 14px 20px; border-radius: 8px; margin-bottom: 16px; font-weight: 500; white-space: pre-wrap;">{{ session('success') }}</div>
@endif
@if(session('error'))
<div style="background: #fee2e2; color: #991b1b; padding: 14px 20px; border-radius: 8px; margin-bottom: 16px; font-weight: 500; white-space: pre-wrap;">{{ session('error') }}</div>
@endif

{{-- Quick Actions --}}
<div class="diag-card">
    <div class="step-title">Quick Actions</div>
    <div class="action-bar">
        <form method="POST" action="{{ route('test.fcm-generate-sw') }}" style="display:inline;">
            @csrf
            <button type="submit" class="btn-run btn-orange">Generate fcm-sw.js</button>
        </form>
        <button class="btn-run btn-blue" onclick="allowNotification()">Allow Notification Permission</button>
        <button class="btn-run btn-red" onclick="unregisterAllSW()">Unregister All Service Workers</button>
    </div>
    <div id="actionStatus" style="font-size: 13px; color: #6b7280; margin-top: 8px;"></div>
</div>

{{-- Direct FCM Send Test --}}
<div class="diag-card">
    <div class="step-title">Direct FCM Send Test (no queue)</div>
    <p style="color: #6b7280; margin-bottom: 12px;">Send notification directly via Firebase Admin SDK — shows exact success/error response.</p>
    <form method="POST" action="{{ route('test.fcm-direct-send') }}" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
        @csrf
        <input type="hidden" name="user_id" value="{{ auth()->id() }}">
        <button type="submit" class="btn-run">Send FCM to myself ({{ auth()->user()->name }})</button>
    </form>
</div>

{{-- Server-side checks --}}
<div class="diag-card">
    <div class="step-title">1. Server-Side Config</div>
    <div class="diag-row">
        <span class="diag-label">FIREBASE_PROJECT_ID</span>
        <span class="diag-val">
            @if($serverChecks['project_id'])
                <span class="badge-ok">SET</span> {{ $serverChecks['project_id_preview'] }}
            @else
                <span class="badge-fail">MISSING</span>
            @endif
        </span>
    </div>
    <div class="diag-row">
        <span class="diag-label">FIREBASE_WEB_API_KEY</span>
        <span class="diag-val">
            @if($serverChecks['api_key'])
                <span class="badge-ok">SET</span> {{ $serverChecks['api_key_preview'] }}
            @else
                <span class="badge-fail">MISSING</span>
            @endif
        </span>
    </div>
    <div class="diag-row">
        <span class="diag-label">FIREBASE_WEB_MESSAGING_SENDER_ID</span>
        <span class="diag-val">
            @if($serverChecks['sender_id'])
                <span class="badge-ok">SET</span> {{ $serverChecks['sender_id_val'] }}
            @else
                <span class="badge-fail">MISSING</span>
            @endif
        </span>
    </div>
    <div class="diag-row">
        <span class="diag-label">FIREBASE_WEB_APP_ID</span>
        <span class="diag-val">
            @if($serverChecks['app_id'])
                <span class="badge-ok">SET</span> {{ $serverChecks['app_id_preview'] }}
            @else
                <span class="badge-fail">MISSING</span>
            @endif
        </span>
    </div>
    <div class="diag-row">
        <span class="diag-label">FIREBASE_VAPID_KEY</span>
        <span class="diag-val">
            @if($serverChecks['vapid_key'])
                <span class="badge-ok">SET</span> {{ $serverChecks['vapid_key_preview'] }}
            @else
                <span class="badge-fail">MISSING</span>
            @endif
        </span>
    </div>
    <div class="diag-row">
        <span class="diag-label">Service Account JSON</span>
        <span class="diag-val">
            @if($serverChecks['sa_file_exists'])
                <span class="badge-ok">EXISTS</span>
            @else
                <span class="badge-fail">NOT FOUND</span> {{ $serverChecks['sa_file_path'] }}
            @endif
        </span>
    </div>
    <div class="diag-row">
        <span class="diag-label">Service Account Readable</span>
        <span class="diag-val">
            @if($serverChecks['sa_file_readable'])
                <span class="badge-ok">YES</span>
            @else
                <span class="badge-fail">NO</span>
            @endif
        </span>
    </div>
    <div class="diag-row">
        <span class="diag-label">kreait/firebase-php</span>
        <span class="diag-val">
            @if($serverChecks['kreait_installed'])
                <span class="badge-ok">INSTALLED</span>
            @else
                <span class="badge-fail">NOT FOUND</span>
            @endif
        </span>
    </div>
    <div class="diag-row">
        <span class="diag-label">fcm-sw.js (static file)</span>
        <span class="diag-val">
            @if($serverChecks['sw_file_exists'])
                <span class="badge-ok">EXISTS</span>
            @else
                <span class="badge-fail">NOT FOUND</span> — Click "Generate" above
            @endif
        </span>
    </div>
    <div class="diag-row">
        <span class="diag-label">FCM Tokens in DB (total)</span>
        <span class="diag-val">
            @if($serverChecks['fcm_token_count'] > 0)
                <span class="badge-ok">{{ $serverChecks['fcm_token_count'] }}</span>
            @else
                <span class="badge-fail">0</span>
            @endif
        </span>
    </div>
    <div class="diag-row">
        <span class="diag-label">Your FCM Tokens ({{ auth()->user()->name }})</span>
        <span class="diag-val">
            @if($serverChecks['my_fcm_count'] > 0)
                <span class="badge-ok">{{ $serverChecks['my_fcm_count'] }}</span>
            @else
                <span class="badge-fail">0 — Run diagnostic below</span>
            @endif
        </span>
    </div>
    <div class="diag-row">
        <span class="diag-label">API Token (meta tag)</span>
        <span class="diag-val">
            @if($serverChecks['api_token'])
                <span class="badge-ok">SET</span> {{ $serverChecks['api_token_preview'] }}
            @else
                <span class="badge-fail">EMPTY</span>
            @endif
        </span>
    </div>
</div>

{{-- Client-side live diagnostic --}}
<div class="diag-card">
    <div class="step-title">2. Browser-Side Live Test</div>
    <p style="color: #6b7280; margin-bottom: 16px;">Click to run full check: permission → service worker → Firebase init → FCM token → server submit.</p>
    <button id="btnRunTest" class="btn-run" onclick="runFullDiag()">Run Full Diagnostic</button>
    <div style="margin-top: 16px;">
        <div class="log-box" id="liveLog">Waiting for test...</div>
    </div>
</div>

{{-- Step results --}}
<div class="diag-card" id="resultsCard" style="display:none;">
    <div class="step-title">3. Results Summary</div>
    <div id="resultsList"></div>
</div>

<script>
var actionStatus = document.getElementById('actionStatus');

function allowNotification() {
    if (Notification.permission === 'granted') {
        actionStatus.innerHTML = '<span class="badge-ok">Already granted!</span>';
        return;
    }
    if (Notification.permission === 'denied') {
        actionStatus.innerHTML = '<span class="badge-fail">Blocked by browser. Go to browser Settings → Site Settings → Notifications → Allow for this site.</span>';
        return;
    }
    actionStatus.textContent = 'Requesting permission...';
    Notification.requestPermission().then(function(p) {
        if (p === 'granted') {
            actionStatus.innerHTML = '<span class="badge-ok">Permission granted! Now click "Run Full Diagnostic".</span>';
        } else {
            actionStatus.innerHTML = '<span class="badge-fail">Permission ' + p + '. Enable in browser settings.</span>';
        }
    });
}

function unregisterAllSW() {
    actionStatus.textContent = 'Unregistering service workers...';
    navigator.serviceWorker.getRegistrations().then(function(regs) {
        if (regs.length === 0) {
            actionStatus.innerHTML = '<span class="badge-warn">No service workers found.</span>';
            return;
        }
        var count = 0;
        regs.forEach(function(reg) {
            reg.unregister().then(function() {
                count++;
                if (count === regs.length) {
                    actionStatus.innerHTML = '<span class="badge-ok">Unregistered ' + count + ' service worker(s). Now click "Run Full Diagnostic".</span>';
                }
            });
        });
    }).catch(function(e) {
        actionStatus.innerHTML = '<span class="badge-fail">Error: ' + e.message + '</span>';
    });
}
</script>

<script src="https://www.gstatic.com/firebasejs/10.14.1/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.14.1/firebase-messaging-compat.js"></script>
<script>
var logEl = document.getElementById('liveLog');
var results = [];

function log(msg, type) {
    var prefix = type === 'ok' ? '✅ ' : (type === 'fail' ? '❌ ' : (type === 'warn' ? '⚠️ ' : '➡️ '));
    logEl.textContent += prefix + msg + '\n';
    logEl.scrollTop = logEl.scrollHeight;
    if (type === 'ok' || type === 'fail' || type === 'warn') {
        results.push({ msg: msg, type: type });
    }
}

function showResults() {
    var card = document.getElementById('resultsCard');
    var list = document.getElementById('resultsList');
    card.style.display = 'block';
    list.innerHTML = '';
    results.forEach(function(r) {
        var cls = r.type === 'ok' ? 'badge-ok' : (r.type === 'fail' ? 'badge-fail' : 'badge-warn');
        list.innerHTML += '<div class="diag-row"><span class="diag-label">' + r.msg + '</span><span class="' + cls + '">' + r.type.toUpperCase() + '</span></div>';
    });
}

async function runFullDiag() {
    var btn = document.getElementById('btnRunTest');
    btn.disabled = true;
    logEl.textContent = '';
    results = [];

    log('Starting FCM diagnostic...');

    // Check meta tags
    log('Checking meta tags...');
    var configMeta = document.querySelector('meta[name="firebase-config"]');
    var vapidMeta = document.querySelector('meta[name="firebase-vapid-key"]');
    var apiTokenMeta = document.querySelector('meta[name="api-token"]');

    if (!configMeta || !configMeta.content) {
        log('firebase-config meta tag: MISSING or EMPTY', 'fail');
        showResults(); btn.disabled = false; return;
    }
    log('firebase-config meta tag: present', 'ok');

    if (!vapidMeta || !vapidMeta.content) {
        log('firebase-vapid-key meta tag: MISSING or EMPTY', 'fail');
        showResults(); btn.disabled = false; return;
    }
    log('firebase-vapid-key: ' + vapidMeta.content.substring(0, 20) + '...', 'ok');

    var apiToken = apiTokenMeta ? apiTokenMeta.content : '';
    if (!apiToken) {
        log('api-token meta tag: EMPTY — token cannot be sent to server', 'fail');
    } else {
        log('api-token: ' + apiToken.substring(0, 15) + '...', 'ok');
    }

    // Parse config
    var firebaseConfig;
    try {
        firebaseConfig = JSON.parse(configMeta.content);
        log('Firebase config parsed', 'ok');
    } catch(e) {
        log('Firebase config parse error: ' + e.message, 'fail');
        showResults(); btn.disabled = false; return;
    }

    if (!firebaseConfig.api_key) { log('api_key: EMPTY', 'fail'); showResults(); btn.disabled = false; return; }
    log('api_key: ' + firebaseConfig.api_key.substring(0, 15) + '...', 'ok');
    log('project_id: ' + (firebaseConfig.project_id || 'EMPTY'), firebaseConfig.project_id ? 'ok' : 'fail');
    log('messaging_sender_id: ' + (firebaseConfig.messaging_sender_id || 'EMPTY'), firebaseConfig.messaging_sender_id ? 'ok' : 'fail');
    log('app_id: ' + (firebaseConfig.app_id || 'EMPTY'), firebaseConfig.app_id ? 'ok' : 'fail');

    // Notification permission
    log('Checking notification permission...');
    var perm = Notification.permission;
    if (perm === 'granted') {
        log('Notification permission: granted', 'ok');
    } else if (perm === 'default') {
        log('Permission not asked yet. Requesting...', 'warn');
        try {
            perm = await Notification.requestPermission();
            log('Permission result: ' + perm, perm === 'granted' ? 'ok' : 'fail');
            if (perm !== 'granted') { showResults(); btn.disabled = false; return; }
        } catch(e) {
            log('Permission error: ' + e.message, 'fail');
            showResults(); btn.disabled = false; return;
        }
    } else {
        log('Notification DENIED. Enable in browser Settings → Site Settings → Notifications.', 'fail');
        showResults(); btn.disabled = false; return;
    }

    // Check if SW file accessible
    log('Checking fcm-sw.js availability...');
    try {
        var swResp = await fetch('/fcm-sw.js');
        var swType = swResp.headers.get('content-type') || '';
        var swBody = await swResp.text();
        log('SW file HTTP ' + swResp.status + ', Content-Type: ' + swType + ', Size: ' + swBody.length + ' bytes', swResp.ok ? 'ok' : 'fail');
        if (swBody.indexOf('importScripts') === -1) {
            log('SW file does NOT contain importScripts — content might be HTML/error page!', 'fail');
            log('First 200 chars: ' + swBody.substring(0, 200), 'fail');
            showResults(); btn.disabled = false; return;
        }
        if (swBody.indexOf('firebase.initializeApp') !== -1 && swBody.indexOf('"apiKey":""') !== -1) {
            log('SW file has EMPTY apiKey — config not injected!', 'fail');
            showResults(); btn.disabled = false; return;
        }
        log('SW file content looks valid (has importScripts + firebase.initializeApp)', 'ok');
    } catch(e) {
        log('Could not fetch SW file: ' + e.message, 'fail');
    }

    // Service Worker registration
    log('Registering service worker...');
    var swReg;
    try {
        swReg = await navigator.serviceWorker.register('/fcm-sw.js');
        log('Service worker registered! Scope: ' + swReg.scope, 'ok');
    } catch(e) {
        log('Service worker registration FAILED: ' + e.message, 'fail');
        showResults(); btn.disabled = false; return;
    }

    // Firebase init
    log('Initializing Firebase...');
    try {
        if (!firebase.apps.length) {
            firebase.initializeApp({
                apiKey: firebaseConfig.api_key,
                authDomain: firebaseConfig.auth_domain,
                projectId: firebaseConfig.project_id,
                storageBucket: firebaseConfig.storage_bucket,
                messagingSenderId: firebaseConfig.messaging_sender_id,
                appId: firebaseConfig.app_id
            });
        }
        log('Firebase initialized', 'ok');
    } catch(e) {
        log('Firebase init FAILED: ' + e.message, 'fail');
        showResults(); btn.disabled = false; return;
    }

    // FCM token
    log('Getting FCM token (may take a few seconds)...');
    var messaging;
    try { messaging = firebase.messaging(); } catch(e) {
        log('firebase.messaging() FAILED: ' + e.message, 'fail');
        showResults(); btn.disabled = false; return;
    }

    var fcmToken;
    try {
        fcmToken = await messaging.getToken({ vapidKey: vapidMeta.content, serviceWorkerRegistration: swReg });
        if (fcmToken) {
            log('FCM Token: ' + fcmToken.substring(0, 40) + '...', 'ok');
        } else {
            log('FCM Token: null (no token returned)', 'fail');
            showResults(); btn.disabled = false; return;
        }
    } catch(e) {
        log('FCM getToken FAILED: ' + e.message, 'fail');
        log('Error details: ' + JSON.stringify({code: e.code, message: e.message, stack: (e.stack||'').substring(0, 300)}), 'fail');
        showResults(); btn.disabled = false; return;
    }

    // Send to server
    if (!apiToken) {
        log('Skipping server submit — no API token', 'fail');
        showResults(); btn.disabled = false; return;
    }

    log('Sending FCM token to server...');
    try {
        var resp = await fetch(window.location.origin + '/api/fcm-subscription', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': 'Bearer ' + apiToken
            },
            body: JSON.stringify({ fcm_token: fcmToken, device_type: 'web' })
        });
        var respText = await resp.text();
        if (resp.ok) {
            log('Server accepted FCM token (HTTP ' + resp.status + ')', 'ok');
        } else {
            log('Server rejected (HTTP ' + resp.status + '): ' + respText, 'fail');
        }
    } catch(e) {
        log('Server request error: ' + e.message, 'fail');
    }

    log('');
    log('=== Diagnostic complete ===');
    showResults();
    btn.disabled = false;
}
</script>
@endsection

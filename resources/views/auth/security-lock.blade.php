<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Extra Verification - {{ brand_name() ?: 'CRM' }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            padding: 24px;
            background: #edf4f0;
            color: #10251c;
            font-family: Inter, Arial, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .verification-shell { width: min(520px, 100%); }
        .brand { display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 20px; font-size: 20px; font-weight: 800; }
        .brand-mark { width: 42px; height: 42px; border-radius: 50%; background: #0b5738; color: #fff; display: grid; place-items: center; }
        .panel { background: #fff; border: 1px solid #dce7e1; border-radius: 16px; box-shadow: 0 18px 50px rgba(16, 37, 28, .10); padding: 30px; }
        .step { display: inline-flex; align-items: center; gap: 8px; border-radius: 999px; background: #e8f5ee; color: #0b5738; padding: 7px 11px; font-size: 12px; font-weight: 800; }
        h1 { margin: 16px 0 8px; font-size: 27px; line-height: 1.2; }
        .intro { margin: 0 0 22px; color: #64736b; line-height: 1.55; }
        .account { display: flex; justify-content: space-between; gap: 16px; padding: 14px 0; border-top: 1px solid #e5ece8; border-bottom: 1px solid #e5ece8; }
        .account-label { color: #718078; font-size: 13px; }
        .account-value { margin-top: 4px; font-weight: 800; overflow-wrap: anywhere; }
        .timer { color: #0b5738; font-weight: 800; text-align: right; white-space: nowrap; }
        .notice { margin: 18px 0; padding: 12px 14px; border-radius: 10px; background: #f5f8f6; color: #4e5e55; font-size: 14px; line-height: 1.5; }
        .alert { border-radius: 10px; padding: 12px 14px; margin-bottom: 16px; font-size: 14px; }
        .alert-success { background: #e8f7ee; color: #17613d; }
        .alert-error { background: #fff1f1; color: #a11b1b; }
        label { display: block; margin-bottom: 8px; font-size: 14px; font-weight: 800; }
        textarea { width: 100%; min-height: 90px; border: 1px solid #ccd8d1; border-radius: 10px; padding: 12px; resize: vertical; font: inherit; color: #10251c; outline: none; }
        textarea:focus { border-color: #0b5738; box-shadow: 0 0 0 3px rgba(11, 87, 56, .10); }
        .consent-note { margin: 14px 0 10px; color: #64736b; font-size: 13px; line-height: 1.5; }
        .optional-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        button { font: inherit; cursor: pointer; }
        .option-btn { min-height: 44px; border: 1px solid #bfd0c7; background: #fff; color: #174c35; border-radius: 10px; padding: 10px 12px; font-weight: 800; }
        .option-btn.done { background: #e8f5ee; border-color: #8bc7a7; }
        .capture-box { display: none; margin-top: 12px; padding: 12px; border: 1px solid #dce7e1; border-radius: 12px; background: #f8faf9; }
        .capture-box video { display: block; width: 100%; max-height: 260px; border-radius: 9px; background: #17231d; object-fit: cover; }
        .capture-controls { display: flex; gap: 8px; margin-top: 10px; }
        .small-btn { border: 0; border-radius: 8px; background: #163d2c; color: #fff; padding: 9px 13px; font-weight: 800; }
        .small-btn.secondary { background: #e9efec; color: #30483b; }
        .submit { width: 100%; min-height: 48px; margin-top: 18px; border: 0; border-radius: 10px; background: #0b5738; color: #fff; font-weight: 900; }
        .back { display: block; margin-top: 16px; color: #64736b; text-align: center; text-decoration: none; font-size: 14px; font-weight: 700; }
        .privacy { margin: 14px 0 0; color: #7a8881; font-size: 12px; line-height: 1.5; text-align: center; }
        @media (max-width: 560px) {
            body { padding: 14px; align-items: flex-start; }
            .panel { padding: 22px 18px; }
            .optional-actions { grid-template-columns: 1fr; }
            .account { align-items: flex-start; }
        }
    </style>
</head>
<body>
    <main class="verification-shell">
        <div class="brand">
            <span class="brand-mark">{{ strtoupper(substr(brand_name() ?: 'B', 0, 1)) }}</span>
            <span>{{ brand_name() ?: 'Brickly CRM' }}</span>
        </div>

        <section class="panel">
            <span class="step"><i class="fas fa-shield-halved"></i> Step 2 of 2</span>
            <h1>Just one step away from login</h1>
            <p class="intro">We need a quick verification before you can continue.</p>

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if($errors->any())
                <div class="alert alert-error">{{ $errors->first() }}</div>
            @endif

            <div class="account">
                <div>
                    <div class="account-label">Signing in as</div>
                    <div class="account-value">{{ $maskedEmail ?: 'Unknown account' }}</div>
                </div>
                @if($state['admin_locked'] ?? false)
                    <div class="timer">Admin review</div>
                @else
                    <div class="timer" id="waitTimer" data-until="{{ optional($state['lock_until'])->toIso8601String() }}">Please wait</div>
                @endif
            </div>

            <div class="notice">
                A few sign-in details did not match. You can wait for the security pause to end, or send a verification request to the administrator.
            </div>

            <form method="POST" action="{{ route('login.security.request-access') }}" id="urgentAccessForm">
                @csrf
                <input type="hidden" name="email" value="{{ $email }}">
                <input type="hidden" name="latitude" id="latitude">
                <input type="hidden" name="longitude" id="longitude">
                <input type="hidden" name="location_accuracy" id="location_accuracy">
                <input type="hidden" name="selfie_data" id="selfie_data">

                <label for="reason">Why do you need access now?</label>
                <textarea name="reason" id="reason" required maxlength="1000" placeholder="Briefly tell the administrator why this login is urgent">{{ old('reason') }}</textarea>

                <p class="consent-note">
                    You may optionally share your location or a verification photo to help the administrator review faster. Your IP, browser, account and attempt time are included with the request.
                </p>

                <div class="optional-actions">
                    <button type="button" id="locationBtn" class="option-btn">
                        <i class="fas fa-location-dot"></i> Share location
                    </button>
                    <button type="button" id="cameraBtn" class="option-btn">
                        <i class="fas fa-camera"></i> Add verification photo
                    </button>
                </div>

                <div id="captureBox" class="capture-box">
                    <video id="video" autoplay playsinline></video>
                    <canvas id="canvas" width="640" height="480" hidden></canvas>
                    <div class="capture-controls">
                        <button type="button" id="captureBtn" class="small-btn"><i class="fas fa-camera"></i> Use this photo</button>
                        <button type="button" id="cancelCameraBtn" class="small-btn secondary">Cancel</button>
                    </div>
                </div>

                <button type="submit" class="submit">Send verification request</button>
                <a href="{{ route('login') }}" class="back">Back to login</a>
                <p class="privacy">This request does not bypass security or sign you in automatically.</p>
            </form>
        </section>
    </main>

    <script>
        const locationBtn = document.getElementById('locationBtn');
        locationBtn?.addEventListener('click', () => {
            if (!navigator.geolocation) {
                locationBtn.innerHTML = '<i class="fas fa-circle-xmark"></i> Location unavailable';
                return;
            }

            locationBtn.disabled = true;
            locationBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Requesting permission';
            navigator.geolocation.getCurrentPosition((position) => {
                document.getElementById('latitude').value = position.coords.latitude;
                document.getElementById('longitude').value = position.coords.longitude;
                document.getElementById('location_accuracy').value = position.coords.accuracy || '';
                locationBtn.classList.add('done');
                locationBtn.innerHTML = '<i class="fas fa-circle-check"></i> Location added';
                locationBtn.disabled = false;
            }, () => {
                locationBtn.innerHTML = '<i class="fas fa-location-dot"></i> Location not shared';
                locationBtn.disabled = false;
            }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 });
        });

        let stream = null;
        const captureBox = document.getElementById('captureBox');
        const cameraBtn = document.getElementById('cameraBtn');

        function stopCamera() {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                stream = null;
            }
            captureBox.style.display = 'none';
        }

        cameraBtn?.addEventListener('click', async () => {
            try {
                stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
                captureBox.style.display = 'block';
                document.getElementById('video').srcObject = stream;
            } catch (error) {
                cameraBtn.innerHTML = '<i class="fas fa-camera"></i> Camera not shared';
            }
        });

        document.getElementById('captureBtn')?.addEventListener('click', () => {
            const video = document.getElementById('video');
            const canvas = document.getElementById('canvas');
            canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
            document.getElementById('selfie_data').value = canvas.toDataURL('image/jpeg', 0.82);
            stopCamera();
            cameraBtn.classList.add('done');
            cameraBtn.innerHTML = '<i class="fas fa-circle-check"></i> Verification photo added';
        });

        document.getElementById('cancelCameraBtn')?.addEventListener('click', stopCamera);
        window.addEventListener('beforeunload', stopCamera);

        const waitTimer = document.getElementById('waitTimer');
        const lockUntil = waitTimer?.dataset.until ? new Date(waitTimer.dataset.until).getTime() : null;
        if (waitTimer && lockUntil) {
            const updateTimer = () => {
                const seconds = Math.max(0, Math.ceil((lockUntil - Date.now()) / 1000));
                waitTimer.textContent = seconds > 0
                    ? `Try again in ${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`
                    : 'You can try again';
            };
            updateTimer();
            setInterval(updateTimer, 1000);
        }
    </script>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Install App - {{ config('app.name') }}</title>
    <link rel="manifest" href="{{ asset('manifest.json') }}?v={{ filemtime(public_path('manifest.json')) }}">
    <link rel="apple-touch-icon" href="{{ asset('icon-192.png') }}">
    <meta name="theme-color" content="#205A44">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(180deg, #205A44 0%, #063A1C 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px;
            color: #fff;
        }
        .card {
            background: rgba(255,255,255,0.98);
            color: #1f2937;
            border-radius: 20px;
            padding: 32px 24px;
            max-width: 400px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.25);
        }
        .icon-wrap {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, #205A44, #15803d);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
        }
        h1 {
            font-size: 22px;
            margin-bottom: 8px;
            color: #063A1C;
        }
        p {
            font-size: 15px;
            color: #6b7280;
            margin-bottom: 28px;
            line-height: 1.5;
        }
        .btn-install {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 18px 24px;
            font-size: 18px;
            font-weight: 600;
            color: #fff;
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
            border: none;
            border-radius: 14px;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(22, 163, 74, 0.4);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn-install:hover, .btn-install:active {
            transform: scale(0.98);
            box-shadow: 0 2px 10px rgba(22, 163, 74, 0.35);
        }
        .btn-install:disabled {
            opacity: 0.8;
            cursor: not-allowed;
        }
        .status {
            margin-top: 20px;
            font-size: 14px;
            color: #059669;
            min-height: 20px;
        }
        .status.error { color: #dc2626; }
        .btn-login {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 14px 20px;
            margin-top: 12px;
            font-size: 16px;
            font-weight: 600;
            color: #205A44;
            background: #fff;
            border: 2px solid rgba(255,255,255,0.9);
            border-radius: 14px;
            cursor: pointer;
            text-decoration: none;
            transition: transform 0.2s, background 0.2s, color 0.2s;
        }
        .btn-login:hover, .btn-login:active {
            background: rgba(255,255,255,0.95);
            color: #063A1C;
            transform: scale(0.98);
        }
        .footer {
            margin-top: 24px;
            font-size: 13px;
            opacity: 0.9;
        }
        .footer a { color: #fff; text-decoration: underline; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon-wrap">📱</div>
        <h1>Install {{ config('app.name') }}</h1>
        <p>Add to your home screen for quick access and enable notifications for lead updates.</p>
        <button type="button" id="installBtn" class="btn-install">
            <span id="installBtnText">Install App</span>
        </button>
        <div id="status" class="status" role="status" aria-live="polite"></div>
        <a href="{{ url('/login') }}" class="btn-login">Login</a>
    </div>
    <p class="footer">
        <a href="{{ url('/login') }}">Already installed? Open app</a>
    </p>

    <script>
(function() {
    var installBtn = document.getElementById('installBtn');
    var installBtnText = document.getElementById('installBtnText');
    var statusEl = document.getElementById('status');
    var deferredPrompt = null;

    function setStatus(msg, isError) {
        statusEl.textContent = msg || '';
        statusEl.className = 'status' + (isError ? ' error' : '');
    }

    function enableNotifications() {
        if (!('Notification' in window)) return Promise.resolve();
        if (Notification.permission === 'granted') return Promise.resolve();
        if (Notification.permission === 'denied') return Promise.resolve();
        return Notification.requestPermission().then(function(p) {
            if (p === 'granted') setStatus('Notifications enabled.');
            return p;
        }).catch(function() {});
    }

    function doInstall() {
        if (deferredPrompt) {
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then(function(choice) {
                if (choice.outcome === 'accepted') {
                    setStatus('Installing...');
                    installBtn.disabled = true;
                    installBtnText.textContent = 'Installing...';
                }
                deferredPrompt = null;
            });
            return;
        }
        // Android Chrome without beforeinstallprompt: show instructions
        if (/Android/i.test(navigator.userAgent)) {
            setStatus('Use browser menu (⋮) → "Install app" or "Add to Home screen"');
            installBtnText.textContent = 'See instructions above';
        } else {
            setStatus('Use your browser menu to find "Install" or "Add to Home Screen".');
        }
    }

    window.addEventListener('beforeinstallprompt', function(e) {
        e.preventDefault();
        deferredPrompt = e;
        installBtn.onclick = function() {
            enableNotifications().then(function() { doInstall(); });
        };
        setStatus('Ready. Tap "Install App" to add to your home screen.');
    });

    window.addEventListener('appinstalled', function() {
        deferredPrompt = null;
        installBtn.disabled = true;
        installBtnText.textContent = 'Installed';
        setStatus('App installed. Open it from your home screen.');
        setTimeout(function() {
            window.location.href = '{{ url("/login") }}';
        }, 1500);
    });

    if (!deferredPrompt) {
        installBtn.onclick = function() {
            enableNotifications().then(function() { doInstall(); });
        };
        setStatus('Tap "Install App" to add to home screen and enable notifications.');
    }

    // Register service worker (required for PWA + notifications on Android)
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('{{ asset("sw.js") }}?v=' + Date.now())
            .then(function(reg) {
                console.log('SW registered', reg.scope);
            })
            .catch(function(e) {
                console.warn('SW registration failed', e);
            });
    }

    // Request notification permission on load (improves PWA notification delivery on Android)
    if ('Notification' in window && Notification.permission === 'default') {
        installBtn.addEventListener('click', function requestOnce() {
            enableNotifications();
            installBtn.removeEventListener('click', requestOnce);
        }, { once: true });
    }
})();
    </script>
</body>
</html>

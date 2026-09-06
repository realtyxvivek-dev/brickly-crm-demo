<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PWA Notification Test - {{ config('app.name') }}</title>
    <link rel="manifest" href="{{ asset('manifest.json') }}?v={{ filemtime(public_path('manifest.json')) }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('icon-192.png') }}">
    <meta name="theme-color" content="#205A44">
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
            max-width: 420px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.25);
        }
        .icon-wrap {
            width: 72px;
            height: 72px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, #205A44, #15803d);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
        }
        h1 {
            font-size: 20px;
            margin-bottom: 12px;
            color: #063A1C;
        }
        .message {
            font-size: 16px;
            color: #4b5563;
            margin-bottom: 28px;
            line-height: 1.5;
        }
        .btn-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 16px 24px;
            font-size: 16px;
            font-weight: 600;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            text-decoration: none;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn-primary {
            color: #fff;
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
            box-shadow: 0 4px 14px rgba(22, 163, 74, 0.35);
        }
        .btn-primary:hover, .btn-primary:active {
            transform: scale(0.98);
        }
        .btn-secondary {
            color: #205A44;
            background: #fff;
            border: 2px solid #205A44;
        }
        .btn-secondary:hover, .btn-secondary:active {
            background: #f0fdf4;
            transform: scale(0.98);
        }
        .btn-test {
            margin-top: 8px;
            color: #6b7280;
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
        }
        .btn-test:hover { background: #e5e7eb; }
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
        <div class="icon-wrap">&#128276;</div>
        <h1>New lead assigned</h1>
        <p class="message">You have been assigned a new lead.</p>
        <div class="btn-group">
            <a href="{{ url('/leads') }}" class="btn btn-primary">View Lead</a>
            <a href="{{ url('/telecaller/tasks') }}?status=pending" class="btn btn-secondary">See Task</a>
            <button type="button" id="sendTestBtn" class="btn btn-test">Send test notification</button>
        </div>
    </div>
    <p class="footer">
        <a href="{{ url('/') }}">Back to app</a>
    </p>

    <script>
    (function() {
        var sendTestBtn = document.getElementById('sendTestBtn');
        if (!sendTestBtn) return;

        function sendTestNotification() {
            if (!('Notification' in window)) {
                alert('This browser does not support notifications.');
                return;
            }
            if (Notification.permission !== 'granted') {
                Notification.requestPermission().then(function(p) {
                    if (p === 'granted') doSend();
                    else alert('Notification permission denied.');
                });
                return;
            }
            doSend();
        }

        function doSend() {
            var title = 'New Lead Assigned';
            var body = 'You have been assigned a new lead.';
            var url = '{{ url("/telecaller/tasks") }}?status=pending';
            try {
                var n = new Notification(title, {
                    body: body,
                    icon: '{{ asset("icon-192.png") }}',
                    tag: 'pwa-notification-test',
                    requireInteraction: false
                });
                n.onclick = function() {
                    window.focus();
                    window.location.href = url;
                    n.close();
                };
                setTimeout(function() { n.close(); }, 8000);
            } catch (e) {
                alert('Could not show notification: ' + e.message);
            }
        }

        sendTestBtn.addEventListener('click', sendTestNotification);
    })();
    </script>
</body>
</html>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Security Lock Active</title>
    <style>
        :root {
            color-scheme: dark;
            --bg: #05070d;
            --panel: #0b1220;
            --text: #eef7ff;
            --muted: #9fb1c5;
            --border: rgba(45, 212, 191, 0.28);
            --accent: #2dd4bf;
            --accent-soft: rgba(45, 212, 191, 0.12);
            --danger: #fb7185;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, Helvetica, sans-serif;
            background:
                linear-gradient(rgba(45, 212, 191, 0.035) 1px, transparent 1px),
                linear-gradient(90deg, rgba(45, 212, 191, 0.035) 1px, transparent 1px),
                radial-gradient(circle at 18% 8%, rgba(45, 212, 191, 0.16), transparent 26rem),
                radial-gradient(circle at 90% 20%, rgba(251, 113, 133, 0.12), transparent 24rem),
                var(--bg);
            background-size: 42px 42px, 42px 42px, auto, auto, auto;
            color: var(--text);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            overflow: hidden;
        }

        body::after {
            content: "";
            position: fixed;
            inset: 0;
            z-index: 0;
            background:
                linear-gradient(to bottom, rgba(5, 7, 13, 0.12), rgba(5, 7, 13, 0.72)),
                radial-gradient(circle at center, transparent 0, rgba(5, 7, 13, 0.62) 72%);
            pointer-events: none;
        }

        .matrix-rain {
            position: fixed;
            inset: -120vh 0 0;
            z-index: 0;
            display: grid;
            grid-template-columns: repeat(18, 1fr);
            gap: clamp(10px, 1.8vw, 28px);
            padding: 0 clamp(10px, 2vw, 30px);
            color: rgba(34, 255, 124, 0.72);
            font: 700 clamp(14px, 2.1vw, 24px) / 1.18 Consolas, "Courier New", monospace;
            text-shadow: 0 0 10px rgba(34, 255, 124, 0.55);
            opacity: 0.88;
            pointer-events: none;
            user-select: none;
        }

        .matrix-rain span {
            writing-mode: vertical-rl;
            word-break: break-all;
            animation: matrixFall var(--speed, 12s) linear infinite;
            animation-delay: var(--delay, 0s);
            transform: translateY(-25%);
            filter: blur(var(--blur, 0));
        }

        .matrix-rain span:nth-child(2n) {
            color: rgba(83, 255, 153, 0.54);
        }

        .matrix-rain span:nth-child(3n) {
            opacity: 0.52;
        }

        @keyframes matrixFall {
            from {
                transform: translateY(-28%);
            }

            to {
                transform: translateY(128%);
            }
        }

        .lock-card {
            position: relative;
            z-index: 1;
            width: min(100%, 680px);
            background: linear-gradient(145deg, rgba(11, 18, 32, 0.9), rgba(7, 10, 18, 0.96));
            backdrop-filter: blur(10px);
            border: 1px solid var(--border);
            border-radius: 8px;
            box-shadow: 0 24px 70px rgba(0, 0, 0, 0.45), 0 0 42px rgba(45, 212, 191, 0.12);
            padding: clamp(24px, 5vw, 44px);
        }

        .status-row {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 24px;
        }

        .status-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--accent-soft);
            border: 1px solid rgba(45, 212, 191, 0.44);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--accent);
            flex: 0 0 auto;
            box-shadow: 0 0 28px rgba(45, 212, 191, 0.25);
        }

        h1 {
            margin: 0;
            font-size: clamp(28px, 5vw, 40px);
            line-height: 1.08;
            letter-spacing: 0;
        }

        .lead {
            margin: 0 0 18px;
            font-size: clamp(17px, 2.6vw, 20px);
            line-height: 1.55;
            color: var(--text);
        }

        p {
            margin: 0 0 12px;
            font-size: 16px;
            line-height: 1.65;
            color: var(--muted);
        }

        .timer {
            margin-top: 24px;
            padding: 14px 16px;
            border: 1px solid rgba(251, 113, 133, 0.35);
            border-radius: 8px;
            background: rgba(251, 113, 133, 0.08);
            color: #fecdd3;
            font-size: 15px;
            font-weight: 700;
        }

        .countdown {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
            margin-top: 14px;
        }

        .countdown-box {
            min-width: 0;
            padding: 12px 10px;
            border: 1px solid rgba(45, 212, 191, 0.26);
            border-radius: 8px;
            background: rgba(45, 212, 191, 0.07);
            text-align: center;
        }

        .countdown-box strong {
            display: block;
            color: var(--accent);
            font: 700 clamp(28px, 7vw, 42px) / 1 Consolas, "Courier New", monospace;
            text-shadow: 0 0 18px rgba(45, 212, 191, 0.38);
        }

        .countdown-box span {
            display: block;
            margin-top: 6px;
            color: var(--muted);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        @media (max-width: 520px) {
            body {
                align-items: flex-start;
                padding: 18px;
                overflow: auto;
            }

            .lock-card {
                margin-top: 32px;
            }

            .status-row {
                align-items: flex-start;
            }

            .matrix-rain {
                grid-template-columns: repeat(9, 1fr);
                font-size: 15px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .matrix-rain span {
                animation: none;
                transform: translateY(20%);
            }
        }
    </style>
</head>
<body>
    <div class="matrix-rain" aria-hidden="true">
        <span style="--speed: 12s; --delay: -2s;">0101011010010110SECURITYLOCKACTIVE1100101001110010</span>
        <span style="--speed: 16s; --delay: -8s; --blur: .3px;">ACCESSDENIED0100101110010010110101011010</span>
        <span style="--speed: 11s; --delay: -5s;">1011010010110110SERVERWATCH010011010110</span>
        <span style="--speed: 19s; --delay: -12s; --blur: .4px;">AUTHCHECKFAILED11010010110101100101</span>
        <span style="--speed: 13s; --delay: -7s;">001101101001011010010110LOCKED0101</span>
        <span style="--speed: 17s; --delay: -4s;">010011001101SUSPICIOUSACTIVITY101101</span>
        <span style="--speed: 10s; --delay: -1s;">110101001011001011010010110101001</span>
        <span style="--speed: 15s; --delay: -9s; --blur: .2px;">INTEGRITYCHECK001101001011010101</span>
        <span style="--speed: 18s; --delay: -11s;">010101001101011011001010SECURE</span>
        <span style="--speed: 12s; --delay: -6s;">SYSTEMLOCKED101001011011010010</span>
        <span style="--speed: 21s; --delay: -15s; --blur: .5px;">0100101101011010010110100110</span>
        <span style="--speed: 14s; --delay: -3s;">SERVERMONITOR0101101001011010</span>
        <span style="--speed: 20s; --delay: -10s;">011010010110101001011010VERIFY</span>
        <span style="--speed: 13s; --delay: -8s; --blur: .2px;">RESTRICTEDACCESS110010101101</span>
        <span style="--speed: 16s; --delay: -13s;">1010010110100101101001011010</span>
        <span style="--speed: 11s; --delay: -4s;">THREATSCAN0101011010010110</span>
        <span style="--speed: 18s; --delay: -7s; --blur: .4px;">010110100101101001011010</span>
        <span style="--speed: 15s; --delay: -2s;">AUTOLOCK010011010110SECURITY</span>
    </div>
    <main class="lock-card" role="main" aria-labelledby="security-lock-title">
        <div class="status-row">
            <div class="status-icon" aria-hidden="true">
                <svg width="25" height="25" viewBox="0 0 24 24" fill="none">
                    <path d="M7 10V8a5 5 0 0 1 10 0v2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <rect x="5" y="10" width="14" height="10" rx="2" stroke="currentColor" stroke-width="2"/>
                    <path d="M12 14v2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </div>
            <h1 id="security-lock-title">Security Lock Active</h1>
        </div>

        <p class="lead">This system was automatically locked after suspicious activity was detected on the server.</p>
        <p>Due to security concerns, access has been restricted while the owner verifies system integrity.</p>
        <p>If you are an authorized user, please contact the administrator.</p>

        <div class="timer">
            Automatic security lock: access restricted for 2 hours.
            <div class="countdown" aria-label="Security lock countdown">
                <div class="countdown-box">
                    <strong id="lockHours">02</strong>
                    <span>Hours</span>
                </div>
                <div class="countdown-box">
                    <strong id="lockMinutes">00</strong>
                    <span>Minutes</span>
                </div>
                <div class="countdown-box">
                    <strong id="lockSeconds">00</strong>
                    <span>Seconds</span>
                </div>
            </div>
        </div>
    </main>
    <script>
        (function () {
            var expiresAt = new Date(@json($lockExpiresAt ?? now()->addHours(2)->toIso8601String())).getTime();
            var serverNow = new Date(@json($serverNow ?? now()->toIso8601String())).getTime();
            var clientStartedAt = Date.now();
            var hours = document.getElementById('lockHours');
            var minutes = document.getElementById('lockMinutes');
            var seconds = document.getElementById('lockSeconds');

            function pad(value) {
                return String(value).padStart(2, '0');
            }

            function renderCountdown() {
                var estimatedServerNow = serverNow + (Date.now() - clientStartedAt);
                var remaining = Math.ceil((expiresAt - estimatedServerNow) / 1000);
                var safeRemaining = Math.max(remaining, 0);
                hours.textContent = pad(Math.floor(safeRemaining / 3600));
                minutes.textContent = pad(Math.floor((safeRemaining % 3600) / 60));
                seconds.textContent = pad(safeRemaining % 60);
            }

            renderCountdown();
            setInterval(renderCountdown, 1000);
        })();
    </script>
</body>
</html>

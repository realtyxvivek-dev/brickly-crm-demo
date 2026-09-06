<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login - {{ brand_name() }}</title>
    <link rel="manifest" href="{{ asset('manifest.json') }}?v={{ filemtime(public_path('manifest.json')) }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            height: 100vh;
            overflow: hidden;
        }

        .login-container {
            display: flex;
            height: 100vh;
        }

        /* Mobile view - Enable scrolling */
        @media (max-width: 968px) {
            body {
                height: auto;
                min-height: 100vh;
                overflow: auto;
                overflow-x: hidden;
            }

            .login-container {
                height: auto;
                min-height: 100vh;
            }
        }

        /* Left Section - Gradient with Analytics */
        .left-section {
            flex: 0 0 40%;
            background: linear-gradient(135deg, #205A44 0%, #063A1C 100%);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 60px 50px;
            color: white;
        }

        .left-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                radial-gradient(circle at 20% 50%, rgba(255,255,255,0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(255,255,255,0.1) 0%, transparent 50%);
            pointer-events: none;
        }

        .analytics-widgets {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            width: 100%;
            max-width: 500px;
            margin-bottom: 40px;
            z-index: 1;
        }

        .widget-card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .widget-card.large {
            grid-column: 1 / -1;
        }

        .widget-title {
            font-size: 14px;
            font-weight: 500;
            opacity: 0.9;
            margin-bottom: 16px;
        }

        .chart-controls {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
        }

        .chart-btn {
            padding: 6px 12px;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            border-radius: 6px;
            color: white;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .chart-btn.active {
            background: rgba(255, 255, 255, 0.3);
            font-weight: 600;
        }

        .bar-chart {
            display: flex;
            align-items: flex-end;
            gap: 8px;
            height: 120px;
        }

        .bar {
            flex: 1;
            background: rgba(255, 255, 255, 0.4);
            border-radius: 4px 4px 0 0;
            min-height: 20px;
            transition: all 0.3s;
        }

        .bar:nth-child(1) { height: 60%; }
        .bar:nth-child(2) { height: 80%; }
        .bar:nth-child(3) { height: 45%; }
        .bar:nth-child(4) { height: 90%; }
        .bar:nth-child(5) { height: 70%; }
        .bar:nth-child(6) { height: 55%; }
        .bar:nth-child(7) { height: 75%; }

        .bar-labels {
            display: flex;
            justify-content: space-between;
            margin-top: 8px;
            font-size: 11px;
            opacity: 0.8;
        }

        .progress-circle {
            width: 100px;
            height: 100px;
            margin: 0 auto;
            position: relative;
        }

        .circle-bg {
            fill: none;
            stroke: rgba(255, 255, 255, 0.2);
            stroke-width: 8;
        }

        .circle-progress {
            fill: none;
            stroke: white;
            stroke-width: 8;
            stroke-linecap: round;
            stroke-dasharray: 251.2;
            stroke-dashoffset: 145.7;
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
            transition: stroke-dashoffset 0.5s;
        }

        .progress-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 24px;
            font-weight: 700;
        }

        .left-content {
            z-index: 1;
            text-align: center;
        }

        .headline {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 16px;
            line-height: 1.2;
        }

        .subtext {
            font-size: 16px;
            opacity: 0.9;
            line-height: 1.6;
            max-width: 500px;
        }

        /* Right Section - Login Form */
        .right-section {
            flex: 1;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            overflow-y: auto;
        }

        .login-form-container {
            width: 100%;
            max-width: 420px;
        }

        .logo-section {
            text-align: center;
            margin-bottom: 40px;
        }

        .logo-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #205A44 0%, #063A1C 100%);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 16px;
            box-shadow: 0 4px 12px rgba(6, 58, 28, 0.3);
        }

        .logo-text {
            font-size: 24px;
            font-weight: 700;
            color: #063A1C;
            margin-bottom: 8px;
        }

        .welcome-title {
            font-size: 28px;
            font-weight: 700;
            color: #063A1C;
            margin-bottom: 8px;
        }

        .welcome-subtitle {
            font-size: 14px;
            color: #B3B5B4;
            margin-bottom: 32px;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 500;
            color: #2d3748;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
            font-size: 18px;
        }

        .form-group input {
            width: 100%;
            padding: 14px 16px 14px 48px;
            border: 2px solid #E5DED4;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s;
            font-family: 'Inter', sans-serif;
        }

        .form-group input:focus {
            outline: none;
            border-color: #205A44;
            box-shadow: 0 0 0 3px rgba(32, 90, 68, 0.1);
        }

        .password-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #a0aec0;
            cursor: pointer;
            font-size: 18px;
            padding: 4px;
            transition: color 0.3s;
        }

        .password-toggle:hover {
            color: #205A44;
        }

        .btn-signin {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #205A44 0%, #063A1C 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 8px;
            box-shadow: 0 4px 12px rgba(6, 58, 28, 0.4);
        }

        .btn-signin:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(6, 58, 28, 0.5);
        }

        .btn-signin:active {
            transform: translateY(0);
        }

        .btn-install-app {
            width: 100%;
            padding: 12px 14px;
            margin-top: 12px;
            background: transparent;
            color: #205A44;
            border: 2px solid #205A44;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-install-app:hover {
            background: rgba(32, 90, 68, 0.08);
            transform: translateY(-1px);
        }
        .btn-install-app:active { transform: translateY(0); }
        .btn-install-app:disabled { opacity: 0.7; cursor: not-allowed; }
        #installAppStatus { font-size: 13px; color: #205A44; margin-top: 10px; min-height: 18px; line-height: 1.4; padding: 0 4px; }
        #installAppStatus.error { color: #dc2626; }

        .app-downloads {
            margin-top: 16px;
            padding: 14px;
            border: 1px solid #E5DED4;
            border-radius: 14px;
            background: #f8fafc;
        }

        .app-downloads-title {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #063A1C;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .app-download-links {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .app-download-link {
            min-height: 46px;
            padding: 10px 12px;
            border-radius: 12px;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 700;
            transition: all 0.2s ease;
            border: 1.5px solid transparent;
        }

        .app-download-link.android {
            background: #063A1C;
            color: #fff;
            box-shadow: 0 3px 10px rgba(6, 58, 28, 0.22);
        }

        .app-download-link.desktop {
            background: #fff;
            color: #063A1C;
            border-color: #205A44;
        }

        .app-download-link:hover {
            transform: translateY(-1px);
        }

        .app-download-note {
            margin-top: 9px;
            font-size: 11px;
            line-height: 1.4;
            color: #64748b;
        }

        .error-message {
            background: #fee;
            color: #c33;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid #c33;
        }

        .success-message {
            background: #ecfdf5;
            color: #065f46;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid #10b981;
        }

        .step-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background: #f0fdf4;
            color: #065f46;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 18px;
        }

        .step-badge span {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #205A44;
            color: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
        }

        .otp-step-shell {
            margin-top: 10px;
            padding: 18px;
            border: 1px solid #dfe9e3;
            border-radius: 28px;
            background:
                linear-gradient(180deg, rgba(236, 253, 245, 0.78), rgba(255, 255, 255, 0.96) 46%),
                #ffffff;
            box-shadow: 0 22px 55px rgba(6, 58, 28, 0.12);
        }

        .otp-step-shell .step-badge {
            margin-bottom: 14px;
            background: #ffffff;
            box-shadow: 0 6px 18px rgba(6, 58, 28, 0.08);
        }

        .otp-meta {
            background: #ffffff;
            border: 1px solid #dce7e1;
            border-radius: 18px;
            padding: 14px 16px;
            margin-bottom: 16px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
        }

        .otp-meta-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 700;
            color: #063A1C;
            margin-bottom: 4px;
        }

        .otp-meta-title::before {
            content: '\f0e0';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #ecfdf5;
            color: #047857;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
        }

        .otp-meta-copy {
            font-size: 13px;
            color: #6b7280;
            line-height: 1.5;
        }

        .otp-actions {
            display: flex;
            gap: 10px;
            margin-top: 14px;
        }

        .otp-secondary-btn {
            flex: 1;
            padding: 12px 14px;
            border: 1.5px solid #205A44;
            background: #ffffff;
            color: #205A44;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 5px 14px rgba(6, 58, 28, 0.06);
        }

        .otp-secondary-btn:hover {
            background: rgba(32, 90, 68, 0.08);
            transform: translateY(-1px);
        }

        .otp-link-btn {
            background: transparent;
            border: none;
            color: #205A44;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            padding: 0;
            text-decoration: underline;
            text-underline-offset: 2px;
        }

        .otp-link-btn:hover {
            color: #063A1C;
        }

        .animated-otp-card {
            position: relative;
            overflow: hidden;
            border-radius: 26px;
            padding: 20px 18px 18px;
            margin-bottom: 14px;
            background:
                radial-gradient(circle at 15% 10%, rgba(34, 197, 94, 0.18), transparent 32%),
                linear-gradient(145deg, #10241a, #07150f 68%);
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: #ffffff;
            box-shadow: 0 18px 38px rgba(6, 58, 28, 0.22);
        }

        .animated-otp-card::before {
            content: '';
            position: absolute;
            inset: -45%;
            background: radial-gradient(circle at 50% 50%, rgba(34, 197, 94, 0.28), transparent 58%);
            opacity: 0;
            transition: opacity 0.7s ease;
            pointer-events: none;
        }

        .animated-otp-card.is-complete::before {
            opacity: 1;
        }

        .animated-otp-header,
        .animated-otp-inputs,
        .animated-otp-help {
            position: relative;
            z-index: 1;
        }

        .animated-otp-handle {
            width: 44px;
            height: 4px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.22);
            margin: 0 auto 16px;
        }

        .animated-otp-header {
            text-align: center;
            margin-bottom: 20px;
        }

        .animated-otp-title {
            font-size: 19px;
            font-weight: 700;
            letter-spacing: 0;
            margin-bottom: 7px;
        }

        .animated-otp-copy {
            font-size: 13px;
            line-height: 1.45;
            color: rgba(255, 255, 255, 0.68);
        }

        .animated-otp-inputs {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 8px;
            min-height: 50px;
            margin-bottom: 14px;
        }

        .otp-digit-wrapper {
            position: relative;
            height: 50px;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.11);
            overflow: hidden;
            will-change: transform, opacity, box-shadow;
        }

        .otp-digit-wrapper:focus-within {
            background: rgba(32, 90, 68, 0.72);
            box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.16);
        }

        .otp-digit-wrapper::before {
            content: '';
            position: absolute;
            inset: -90%;
            opacity: 0;
            background: conic-gradient(from 0deg, transparent 0%, transparent 58%, #22c55e 85%, #bbf7d0 100%);
            pointer-events: none;
        }

        .animated-otp-card.is-complete .otp-digit-wrapper::before {
            opacity: 1;
            animation: otp-ring-spin 0.7s linear forwards;
        }

        .otp-digit {
            position: absolute;
            inset: 2px;
            z-index: 1;
            width: calc(100% - 4px);
            height: calc(100% - 4px);
            border: none;
            border-radius: 12px;
            background: #14281f;
            color: #ffffff;
            text-align: center;
            font-size: 22px;
            font-weight: 650;
            outline: none;
            font-family: 'Inter', sans-serif;
        }

        .otp-final-check {
            position: absolute;
            z-index: 2;
            top: 50%;
            left: 50%;
            width: 28px;
            height: 28px;
            transform: translate(-50%, -50%) scale(0.55);
            opacity: 0;
            pointer-events: none;
        }

        .animated-otp-card.is-complete .otp-digit {
            color: transparent;
            transition: color 0.18s ease 0.22s;
        }

        .animated-otp-card.is-complete .otp-digit-wrapper:nth-child(1) {
            animation: otp-merge-1 0.58s ease-in-out forwards, otp-success-glow 0.9s ease-in-out 0.58s infinite alternate;
            z-index: 6;
        }

        .animated-otp-card.is-complete .otp-digit-wrapper:nth-child(2) {
            animation: otp-merge-2 0.58s ease-in-out forwards;
            z-index: 5;
        }

        .animated-otp-card.is-complete .otp-digit-wrapper:nth-child(3) {
            animation: otp-merge-3 0.58s ease-in-out forwards;
            z-index: 4;
        }

        .animated-otp-card.is-complete .otp-digit-wrapper:nth-child(4) {
            animation: otp-merge-4 0.58s ease-in-out forwards;
            z-index: 3;
        }

        .animated-otp-card.is-complete .otp-digit-wrapper:nth-child(5) {
            animation: otp-merge-5 0.58s ease-in-out forwards;
            z-index: 2;
        }

        .animated-otp-card.is-complete .otp-digit-wrapper:nth-child(6) {
            animation: otp-merge-6 0.58s ease-in-out forwards;
            z-index: 1;
        }

        .animated-otp-card.is-complete .otp-final-check {
            animation: otp-check-pop 0.38s ease-out 0.45s forwards;
        }

        .animated-otp-help {
            display: flex;
            justify-content: center;
            gap: 6px;
            color: rgba(255, 255, 255, 0.56);
            font-size: 12px;
            text-align: center;
        }

        .otp-hidden-input {
            position: absolute !important;
            width: 1px !important;
            height: 1px !important;
            opacity: 0 !important;
            pointer-events: none !important;
        }

        @keyframes otp-ring-spin {
            to { transform: rotate(360deg); }
        }

        @keyframes otp-merge-1 {
            0% { transform: translateX(0) scale(1); }
            70% { transform: translateX(132px) scale(1.12); }
            100% { transform: translateX(132px) scale(1); }
        }

        @keyframes otp-merge-2 {
            0% { transform: translateX(0) scale(1); opacity: 1; }
            55% { transform: translateX(78px) scale(0.96); opacity: 1; }
            56%, 100% { transform: translateX(78px) scale(0.86); opacity: 0; }
        }

        @keyframes otp-merge-3 {
            0% { transform: translateX(0) scale(1); opacity: 1; }
            55% { transform: translateX(26px) scale(0.96); opacity: 1; }
            56%, 100% { transform: translateX(26px) scale(0.86); opacity: 0; }
        }

        @keyframes otp-merge-4 {
            0% { transform: translateX(0) scale(1); opacity: 1; }
            55% { transform: translateX(-26px) scale(0.96); opacity: 1; }
            56%, 100% { transform: translateX(-26px) scale(0.86); opacity: 0; }
        }

        @keyframes otp-merge-5 {
            0% { transform: translateX(0) scale(1); opacity: 1; }
            55% { transform: translateX(-78px) scale(0.96); opacity: 1; }
            56%, 100% { transform: translateX(-78px) scale(0.86); opacity: 0; }
        }

        @keyframes otp-merge-6 {
            0% { transform: translateX(0) scale(1); opacity: 1; }
            55% { transform: translateX(-132px) scale(0.96); opacity: 1; }
            56%, 100% { transform: translateX(-132px) scale(0.86); opacity: 0; }
        }

        @keyframes otp-check-pop {
            0% { opacity: 0; transform: translate(-50%, -50%) scale(0.55); }
            65% { opacity: 1; transform: translate(-50%, -50%) scale(1.12); }
            100% { opacity: 1; transform: translate(-50%, -50%) scale(1); }
        }

        @keyframes otp-success-glow {
            from { box-shadow: 0 0 10px rgba(34, 197, 94, 0.35); }
            to { box-shadow: 0 0 24px rgba(34, 197, 94, 0.85); }
        }

        .auth-note {
            font-size: 12px;
            color: #6b7280;
            margin-top: 12px;
            line-height: 1.5;
        }

        /* Responsive Design */
        @media (max-width: 968px) {
            .login-container {
                flex-direction: column;
            }

            .left-section {
                flex: 0 0 auto;
                min-height: 40vh;
                padding: 40px 30px;
            }

            .headline {
                font-size: 28px;
            }

            .analytics-widgets {
                max-width: 100%;
            }

            .right-section {
                flex: 1;
                min-height: auto;
            }
        }

        @media (max-width: 640px) {
            .left-section {
                padding: 30px 20px;
            }

            .headline {
                font-size: 24px;
            }

            .subtext {
                font-size: 14px;
            }

            .analytics-widgets {
                grid-template-columns: 1fr;
            }

            .widget-card.large {
                grid-column: 1;
            }

            .right-section {
                padding: 20px;
                min-height: auto;
            }

            .welcome-title {
                font-size: 24px;
            }

            .login-form-container {
                padding-bottom: 40px;
            }

            .app-download-links {
                grid-template-columns: 1fr;
            }

            .animated-otp-card {
                padding: 20px 14px 18px;
                border-radius: 22px;
            }

            .animated-otp-inputs {
                gap: 6px;
            }

            .otp-digit-wrapper {
                height: 48px;
                border-radius: 12px;
            }

            .otp-digit {
                font-size: 21px;
                border-radius: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Left Section - Gradient with Analytics -->
        <div class="left-section">
            <div class="analytics-widgets">
                <div class="widget-card large">
                    <div class="widget-title">Weekly Sales</div>
                    <div class="chart-controls">
                        <button class="chart-btn active">Weekly</button>
                        <button class="chart-btn">Monthly</button>
                        <button class="chart-btn">Yearly</button>
                    </div>
                    <div class="bar-chart">
                        <div class="bar"></div>
                        <div class="bar"></div>
                        <div class="bar"></div>
                        <div class="bar"></div>
                        <div class="bar"></div>
                        <div class="bar"></div>
                        <div class="bar"></div>
                    </div>
                    <div class="bar-labels">
                        <span>MON</span>
                        <span>TUE</span>
                        <span>WED</span>
                        <span>THU</span>
                        <span>FRI</span>
                        <span>SAT</span>
                        <span>SUN</span>
                    </div>
                </div>
                <div class="widget-card">
                    <div class="widget-title">Total Performance</div>
                    <div class="progress-circle">
                        <svg width="100" height="100">
                            <circle class="circle-bg" cx="50" cy="50" r="40"></circle>
                            <circle class="circle-progress" cx="50" cy="50" r="40"></circle>
                        </svg>
                        <div class="progress-text">42%</div>
                    </div>
                </div>
            </div>

            <div class="left-content">
                <h1 class="headline">Effortlessly manage your real estate business</h1>
                <p class="subtext">Manage leads, properties, clients and deals all in one powerful {{ brand_name() }}.</p>
            </div>
        </div>

        <!-- Right Section - Login Form -->
        <div class="right-section">
            <div class="login-form-container">
                <div class="logo-section">
                    <div class="logo-icon">B</div>
                    <div class="logo-text">Brickly CRM</div>
                </div>

                <h2 class="welcome-title">Welcome Back</h2>
                <p class="welcome-subtitle">Login to manage your real estate operations</p>

                @php
                    use App\Models\SystemSettings;
                    $isMaintenanceMode = SystemSettings::isMaintenanceMode();
                    $authFlowPending = session('auth_flow.pending', false);
                    $authFlowMethod = session('auth_flow.method');
                    $authFlowMaskedEmail = session('auth_flow.masked_email');
                    $authFlowCooldown = (int) session('auth_flow.cooldown_remaining', \App\Services\EmailOtpLoginService::RESEND_COOLDOWN_SECONDS);
                    $isOtpStep = $authFlowPending && $authFlowMethod === \App\Models\User::TWO_FACTOR_EMAIL;
                    $isPasswordStep = $authFlowPending && $authFlowMethod === \App\Models\User::TWO_FACTOR_OFF;
                @endphp

                @if($isMaintenanceMode)
                    <div class="maintenance-warning" style="background: #fef3c7; border: 1px solid #f59e0b; color: #92400e; padding: 16px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 20px; margin-right: 12px;"></i>
                        <div>
                            <strong style="display: block; margin-bottom: 4px;">System Under Maintenance</strong>
                            <span style="font-size: 14px;">{{ SystemSettings::get('maintenance_message', 'System is under maintenance. Only admin can login.') }}</span>
                        </div>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="error-message">
                        @foreach ($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                @if (session('success'))
                    <div class="success-message">
                        {{ session('success') }}
                    </div>
                @endif

                @if (!$authFlowPending)
                    <div class="step-badge">
                        <span>1</span> Step 1: Enter your email
                    </div>

                    <form method="POST" action="{{ route('login.email.resolve') }}" id="resolveLoginForm">
                        @csrf

                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <div class="input-wrapper">
                                <i class="fas fa-user input-icon"></i>
                                <input 
                                    type="email" 
                                    id="email" 
                                    name="email" 
                                    value="{{ old('email') }}" 
                                    required 
                                    autofocus
                                    placeholder="Enter your work email"
                                >
                            </div>
                        </div>

                        <button type="submit" class="btn-signin">Continue</button>
                    </form>

                    <div style="display: flex; align-items: center; margin: 16px 0 12px; gap: 12px;">
                        <div style="flex: 1; height: 1px; background: #E5DED4;"></div>
                        <span style="color: #B3B5B4; font-size: 13px; white-space: nowrap;">or</span>
                        <div style="flex: 1; height: 1px; background: #E5DED4;"></div>
                    </div>

                    <button type="button" id="googleSignInBtn" onclick="signInWithGoogle()" style="width: 100%; padding: 12px 14px; background: white; color: #3c4043; border: 2px solid #E5DED4; border-radius: 12px; font-size: 15px; font-weight: 500; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; transition: all 0.3s; font-family: 'Inter', sans-serif;">
                        <svg width="20" height="20" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59a14.5 14.5 0 0 1 0-9.18l-7.98-6.19a24.003 24.003 0 0 0 0 21.56l7.98-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
                        Sign in with Google
                    </button>
                @elseif ($isOtpStep)
                    <div class="otp-step-shell">
                        <div class="step-badge">
                            <span>2</span> Step 2: Verify OTP
                        </div>

                        <div class="otp-meta">
                            <div class="otp-meta-title">OTP Sent</div>
                            <div class="otp-meta-copy">
                                Enter the 6-digit OTP sent to <strong>{{ $authFlowMaskedEmail }}</strong>. The latest OTP stays valid for 90 seconds.
                            </div>
                        </div>

                        <form method="POST" action="{{ route('login.email.verify-otp') }}" id="otpVerifyForm">
                            @csrf
                            <input
                                type="text"
                                id="otp"
                                name="otp"
                                value="{{ old('otp') }}"
                                inputmode="numeric"
                                pattern="[0-9]*"
                                maxlength="6"
                                required
                                class="otp-hidden-input"
                                tabindex="-1"
                                aria-hidden="true"
                            >

                            <div class="animated-otp-card" id="animatedOtpCard">
                                <div class="animated-otp-handle"></div>
                                <div class="animated-otp-header">
                                    <div class="animated-otp-title" id="animatedOtpTitle">Verify secure login</div>
                                    <div class="animated-otp-copy" id="animatedOtpCopy">
                                        Enter the 6 digit code. Paste is supported.
                                    </div>
                                </div>
                                <div class="animated-otp-inputs" id="animatedOtpInputs" aria-label="6 digit OTP">
                                    @for ($i = 0; $i < 6; $i++)
                                        <div class="otp-digit-wrapper">
                                            <input
                                                type="text"
                                                class="otp-digit"
                                                inputmode="numeric"
                                                pattern="[0-9]*"
                                                maxlength="1"
                                                autocomplete="one-time-code"
                                                aria-label="OTP digit {{ $i + 1 }}"
                                                data-otp-index="{{ $i }}"
                                                value="{{ substr((string) old('otp'), $i, 1) }}"
                                                @if ($i === 0) autofocus @endif
                                            >
                                            @if ($i === 0)
                                                <svg class="otp-final-check" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                    <path d="M5 13l4 4L19 7" stroke="#ffffff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path>
                                                </svg>
                                            @endif
                                        </div>
                                    @endfor
                                </div>
                                <div class="animated-otp-help">
                                    <i class="fas fa-shield-alt"></i>
                                    <span>The latest OTP stays valid for 90 seconds.</span>
                                </div>
                            </div>

                            <button type="submit" class="btn-signin" id="otpVerifyButton">Verify OTP</button>
                        </form>

                        <div class="otp-actions">
                            <form method="POST" action="{{ route('login.email.resend-otp') }}" style="flex:1;" id="otpResendForm">
                                @csrf
                                <button type="submit" class="otp-secondary-btn" id="otpResendButton">
                                    Resend OTP
                                </button>
                            </form>
                            <form method="POST" action="{{ route('login.email.reset') }}" style="flex:1;">
                                @csrf
                                <button type="submit" class="otp-secondary-btn">
                                    Use different email
                                </button>
                            </form>
                        </div>

                        <div class="auth-note" id="otpCooldownNotice" data-cooldown="{{ $authFlowCooldown }}">
                            Resend is available in {{ $authFlowCooldown }} seconds.
                        </div>
                    </div>
                @elseif ($isPasswordStep)
                    <div class="step-badge">
                        <span>2</span> Step 2: Enter password
                    </div>

                    <div class="otp-meta">
                        <div class="otp-meta-title">Continue with password</div>
                        <div class="otp-meta-copy">
                            Sign in as <strong>{{ $authFlowMaskedEmail }}</strong>.
                        </div>
                        <form method="POST" action="{{ route('login.email.reset') }}" style="margin-top:10px;">
                            @csrf
                            <button type="submit" class="otp-link-btn">Use different email</button>
                        </form>
                    </div>

                    <form method="POST" action="{{ route('login') }}" id="loginForm">
                        @csrf

                        <div class="form-group">
                            <label for="password">Password</label>
                            <div class="input-wrapper">
                                <i class="fas fa-lock input-icon"></i>
                                <input 
                                    type="password" 
                                    id="password" 
                                    name="password" 
                                    required
                                    autofocus
                                    placeholder="Enter your password"
                                >
                                <button type="button" class="password-toggle" id="togglePassword">
                                    <i class="fas fa-eye" id="eyeIcon"></i>
                                </button>
                            </div>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; font-size: 14px;">
                            <label style="display: flex; align-items: center; cursor: pointer; margin: 0;">
                                <input type="checkbox" name="remember" style="width: auto; margin-right: 8px; cursor: pointer;">
                                <span style="color: #4a5568;">Remember me</span>
                            </label>
                            <a href="{{ route('password.forgot') }}" style="color: #205A44; text-decoration: none;">Forgot password?</a>
                        </div>

                        <button type="submit" class="btn-signin">Login</button>
                    </form>
                @else
                    <div class="otp-meta">
                        <div class="otp-meta-title">Sign in</div>
                        <div class="otp-meta-copy">
                            Start again by entering your email.
                        </div>
                    </div>

                    <form method="POST" action="{{ route('login.email.resolve') }}" id="otpSendForm">
                        @csrf
                        <div class="form-group">
                            <label for="otp-login-email">Email Address</label>
                            <div class="input-wrapper">
                                <i class="fas fa-envelope input-icon"></i>
                                <input
                                    type="email"
                                    id="otp-login-email"
                                    name="email"
                                    value="{{ old('email') }}"
                                    required
                                    placeholder="Enter your email"
                                >
                            </div>
                        </div>

                        <button type="submit" class="btn-signin">Continue</button>
                    </form>
                @endif

                @if ($authFlowPending)
                    <div class="auth-note">
                        You are on a secure 2-step sign-in flow. Only the resolved method for this email is shown here.
                    </div>
                @else
                    @if (request()->getHost() === 'demo.bihtech.in')
                        <a href="{{ route('demo.quick-login') }}" class="btn-signin" style="display:flex;align-items:center;justify-content:center;gap:8px;text-decoration:none;margin-bottom:12px;">
                            <i class="fas fa-bolt"></i> Open Quick Login
                        </a>
                    @endif
                    <button type="button" class="btn-install-app" id="installAppBtn">
                        <i class="fas fa-download"></i> Install App
                    </button>
                    <div id="installAppStatus" role="status" aria-live="polite"></div>

                    <div class="app-downloads">
                        <div class="app-downloads-title">
                            <i class="fas fa-cloud-download-alt"></i>
                            Direct App Downloads
                        </div>
                        <div class="app-download-links">
                            <a class="app-download-link android" href="{{ url('/downloads/base-crm-latest.apk') }}" download>
                                <i class="fab fa-android"></i>
                                Android App
                            </a>
                            <a class="app-download-link desktop" href="{{ url('/downloads/base-crm-desktop-latest.zip') }}" download>
                                <i class="fas fa-desktop"></i>
                                Desktop App
                            </a>
                        </div>
                        <div class="app-download-note">
                            Android ke liye APK install karein. Windows ke liye ZIP extract karke {{ mobile_app_name() }} app open karein.
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script>
        // Setup CSRF token for all AJAX requests
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (csrfToken) {
            // Update form CSRF token
            const csrfInput = document.querySelector('input[name="_token"]');
            if (csrfInput) {
                csrfInput.value = csrfToken;
            }
        }

        // Password toggle functionality
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        const eyeIcon = document.getElementById('eyeIcon');

        if (togglePassword && passwordInput && eyeIcon) {
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                if (type === 'password') {
                    eyeIcon.classList.remove('fa-eye-slash');
                    eyeIcon.classList.add('fa-eye');
                } else {
                    eyeIcon.classList.remove('fa-eye');
                    eyeIcon.classList.add('fa-eye-slash');
                }
            });
        }

        // Refresh CSRF token before form submission to prevent 419 errors
        const form = document.getElementById('loginForm');
        if (form) {
            form.addEventListener('submit', function() {
                const csrfInput = form.querySelector('input[name="_token"]');
                if (csrfInput && csrfToken) {
                    csrfInput.value = csrfToken;
                }
            });
        }

        const otpVerifyForm = document.getElementById('otpVerifyForm');
        const otpHiddenInput = document.getElementById('otp');
        const otpCard = document.getElementById('animatedOtpCard');
        const otpTitle = document.getElementById('animatedOtpTitle');
        const otpCopy = document.getElementById('animatedOtpCopy');
        const otpVerifyButton = document.getElementById('otpVerifyButton');
        const otpDigitInputs = Array.from(document.querySelectorAll('.otp-digit'));

        if (otpVerifyForm && otpHiddenInput && otpDigitInputs.length) {
            let otpSubmitting = false;

            const syncOtpValue = function() {
                otpHiddenInput.value = otpDigitInputs.map(function(input) {
                    return input.value || '';
                }).join('');
            };

            const fillOtpDigits = function(value) {
                const digits = String(value || '').replace(/\D/g, '').slice(0, 6).split('');
                otpDigitInputs.forEach(function(input, index) {
                    input.value = digits[index] || '';
                });
                syncOtpValue();
                const nextIndex = Math.min(digits.length, otpDigitInputs.length - 1);
                otpDigitInputs[nextIndex]?.focus();
            };

            fillOtpDigits(otpHiddenInput.value);

            otpDigitInputs.forEach(function(input, index) {
                input.addEventListener('input', function(event) {
                    const cleanValue = event.target.value.replace(/\D/g, '');

                    if (cleanValue.length > 1) {
                        fillOtpDigits(cleanValue);
                        return;
                    }

                    event.target.value = cleanValue;
                    syncOtpValue();

                    if (cleanValue && index < otpDigitInputs.length - 1) {
                        otpDigitInputs[index + 1].focus();
                    }
                });

                input.addEventListener('keydown', function(event) {
                    if (event.key === 'Backspace' && !input.value && index > 0) {
                        otpDigitInputs[index - 1].focus();
                    }
                });

                input.addEventListener('paste', function(event) {
                    event.preventDefault();
                    fillOtpDigits(event.clipboardData.getData('text'));
                });
            });

            otpVerifyForm.addEventListener('submit', function(event) {
                syncOtpValue();

                if (otpSubmitting) {
                    return;
                }

                if (otpHiddenInput.value.length !== 6) {
                    event.preventDefault();
                    otpDigitInputs.find(function(input) { return !input.value; })?.focus();
                    return;
                }

                event.preventDefault();
                otpSubmitting = true;
                otpCard?.classList.add('is-complete');
                if (otpTitle) otpTitle.textContent = 'Verifying OTP';
                if (otpCopy) otpCopy.textContent = 'Code received. Verifying secure login...';
                if (otpVerifyButton) {
                    otpVerifyButton.disabled = true;
                    otpVerifyButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';
                }

                setTimeout(function() {
                    otpVerifyForm.submit();
                }, 760);
            });
        }

        const resendNotice = document.getElementById('otpCooldownNotice');
        const resendButton = document.getElementById('otpResendButton');

        if (resendNotice && resendButton) {
            let cooldown = parseInt(resendNotice.dataset.cooldown || '0', 10);

            const tickCooldown = function() {
                if (cooldown > 0) {
                    resendButton.disabled = true;
                    resendNotice.textContent = 'Resend is available in ' + cooldown + ' seconds.';
                    cooldown -= 1;
                    setTimeout(tickCooldown, 1000);
                    return;
                }

                resendButton.disabled = false;
                resendNotice.textContent = 'You can request a fresh OTP now.';
            };

            tickCooldown();
        }

        // Chart button interactions
        document.querySelectorAll('.chart-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.chart-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
            });
        });

        // PWA Install – 1-click on login page (NO redirect, install inline only)
        (function() {
            var installBtn = document.getElementById('installAppBtn');
            var statusEl = document.getElementById('installAppStatus');
            var deferredPrompt = null;
            var swReady = false;

            function setStatus(msg, isError) {
                if (!statusEl) return;
                statusEl.textContent = msg || '';
                statusEl.className = isError ? 'error' : '';
                statusEl.id = 'installAppStatus';
            }

            // 1. Register service worker first (required for PWA)
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('/sw.js?v=' + Date.now()).then(function(reg) {
                    swReady = true;
                    console.log('SW registered for PWA install', reg.scope);
                }).catch(function(err) {
                    console.warn('SW registration failed:', err);
                });
            }

            // 2. Capture beforeinstallprompt (Chrome/Edge fires this when PWA is installable)
            window.addEventListener('beforeinstallprompt', function(e) {
                e.preventDefault();
                deferredPrompt = e;
                console.log('PWA install prompt captured');
                setStatus('');
                if (installBtn) {
                    installBtn.innerHTML = '<i class="fas fa-download"></i> Install App';
                    installBtn.disabled = false;
                }
            });

            // 3. App installed
            window.addEventListener('appinstalled', function() {
                deferredPrompt = null;
                if (installBtn) {
                    installBtn.disabled = true;
                    installBtn.innerHTML = '<i class="fas fa-check-circle"></i> Installed!';
                }
                setStatus('App installed successfully! Open it from your home screen.');
            });

            // 4. Click handler – NO redirect, install or show instructions
            if (installBtn) {
                installBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    // If we have the install prompt, trigger it
                    if (deferredPrompt) {
                        deferredPrompt.prompt();
                        deferredPrompt.userChoice.then(function(choice) {
                            if (choice.outcome === 'accepted') {
                                setStatus('Installing app...');
                                installBtn.disabled = true;
                                installBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Installing...';
                            } else {
                                setStatus('Install cancelled. You can try again.');
                            }
                            deferredPrompt = null;
                        });
                        return false;
                    }

                    // No prompt available – show platform-specific instructions
                    var ua = navigator.userAgent || '';
                    if (/Android/i.test(ua)) {
                        setStatus('Tap the browser menu (⋮) at top-right → "Install app" or "Add to Home screen"');
                    } else if (/iPhone|iPad|iPod/i.test(ua)) {
                        setStatus('Tap the Share button (⎋) → "Add to Home Screen"');
                    } else if (/Chrome/i.test(ua)) {
                        setStatus('Click the install icon (⊕) in the address bar, or Menu → "Install app"');
                    } else if (/Edge/i.test(ua)) {
                        setStatus('Click Menu (···) → "Apps" → "Install this site as an app"');
                    } else {
                        setStatus('Use your browser menu to find "Install" or "Add to Home Screen"');
                    }

                    return false;
                });
            }
        })();

        // Firebase Google Sign-In
        (function() {
            var firebaseConfig = {
                apiKey: "{{ config('firebase.web.api_key') }}",
                authDomain: "{{ config('firebase.web.auth_domain') }}",
                projectId: "{{ config('firebase.web.project_id') }}",
                storageBucket: "{{ config('firebase.web.storage_bucket') }}",
                messagingSenderId: "{{ config('firebase.web.messaging_sender_id') }}",
                appId: "{{ config('firebase.web.app_id') }}"
            };
            if (firebaseConfig.apiKey) {
                var s1 = document.createElement('script');
                s1.src = 'https://www.gstatic.com/firebasejs/10.14.1/firebase-app-compat.js';
                s1.onload = function() {
                    var s2 = document.createElement('script');
                    s2.src = 'https://www.gstatic.com/firebasejs/10.14.1/firebase-auth-compat.js';
                    s2.onload = function() {
                        firebase.initializeApp(firebaseConfig);
                        window._firebaseReady = true;
                    };
                    document.head.appendChild(s2);
                };
                document.head.appendChild(s1);
            }
        })();

        function signInWithGoogle() {
            var btn = document.getElementById('googleSignInBtn');
            if (!window._firebaseReady) {
                alert('Firebase not loaded yet. Please wait a moment and try again.');
                return;
            }
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing in...';

            var provider = new firebase.auth.GoogleAuthProvider();
            firebase.auth().signInWithPopup(provider).then(function(result) {
                return result.user.getIdToken();
            }).then(function(idToken) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = '{{ url("/login/firebase") }}';
                var csrf = document.createElement('input');
                csrf.type = 'hidden'; csrf.name = '_token'; csrf.value = '{{ csrf_token() }}';
                form.appendChild(csrf);
                var tokenInput = document.createElement('input');
                tokenInput.type = 'hidden'; tokenInput.name = 'id_token'; tokenInput.value = idToken;
                form.appendChild(tokenInput);
                document.body.appendChild(form);
                form.submit();
            }).catch(function(error) {
                console.error('Google Sign-In error:', error);
                btn.disabled = false;
                btn.innerHTML = '<svg width="20" height="20" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59a14.5 14.5 0 0 1 0-9.18l-7.98-6.19a24.003 24.003 0 0 0 0 21.56l7.98-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg> Sign in with Google';
                if (error.code !== 'auth/popup-closed-by-user') {
                    alert('Sign-in failed: ' + (error.message || 'Unknown error'));
                }
            });
        }
    </script>
</body>
</html>

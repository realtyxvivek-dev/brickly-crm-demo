<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Telecaller - ' . brand_name())</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        if (auth()->check() && !session('api_token')) {
            $__token = auth()->user()->createToken('web-session-token')->plainTextToken;
            session(['api_token' => $__token, 'telecaller_api_token' => $__token]);
        }
    @endphp
    <meta name="api-token" content="{{ session('api_token', '') }}">
    <meta name="notification-user-id" content="{{ auth()->id() }}">
    <script src="{{ asset('js/notification-device-ownership.js') }}"></script>
    <meta name="user-id" content="{{ auth()->check() ? auth()->user()->id : '' }}">
    <meta name="pusher-key" content="{{ config('broadcasting.connections.pusher.key') }}">
    <meta name="pusher-cluster" content="{{ config('broadcasting.connections.pusher.options.cluster', 'mt1') }}">
    <meta name="firebase-config" content="{{ json_encode(config('firebase.web')) }}">
    <meta name="firebase-vapid-key" content="{{ config('firebase.vapid_key') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { width: 100%; overflow-x: hidden; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #F7F6F3; width: 100%; max-width: 100vw; }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; width: 100%; box-sizing: border-box; overflow-x: hidden; }
        .header { 
            background: white; 
            padding: 16px; 
            border-radius: 12px; 
            margin-bottom: 16px; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); 
            display: flex; 
            flex-direction: column;
            gap: 12px;
            width: 100%; 
            box-sizing: border-box; 
            max-width: 100%; 
            overflow-x: hidden; 
        }
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
        }
        .header-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
            width: 100%;
        }
        .header-actions-row {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        /* Mobile Header - Single Line */
        .header-title-mobile {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            font-size: 18px !important;
            font-weight: 600 !important;
            margin: 0;
        }
        
        .header-page-title-desktop {
            flex: 1;
            font-size: 18px;
            font-weight: 600;
            color: #063A1C;
        }
        
        .header-user-info-mobile {
            display: none; /* Hidden by default, shown on mobile */
            flex-direction: column;
            gap: 2px;
            flex: 1;
            min-width: 0;
        }
        
        .header-user-name-mobile {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #063A1C;
            line-height: 1.2;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .header-user-role-mobile {
            display: block;
            font-size: 11px;
            font-weight: 400;
            color: #6b7280;
            line-height: 1.2;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .header-user-name-desktop {
            display: inline; /* Shown on desktop */
        }
        .btn { padding: 12px 24px; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; font-weight: 500; transition: all 0.3s; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #dc2626; }
        .sidebar-link {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            margin-bottom: 8px;
            border-radius: 8px;
            text-decoration: none;
            color: #666;
            transition: all 0.3s;
        }
        
        /* Icon-only sidebar (all views) */
        #sidebar {
            width: 64px !important;
        }
        
        #sidebar nav {
            padding: 0 12px !important;
        }
        
        #sidebar h2,
        #sidebar p {
            display: none !important;
        }
        
        #sidebar .sidebar-link {
            justify-content: center;
            padding: 12px !important;
            font-size: 0 !important;
        }
        
        #sidebar .sidebar-link i {
            margin-right: 0 !important;
            font-size: 18px;
            width: 20px;
            text-align: center;
        }
        .sidebar-link:hover {
            background: #F7F6F3 !important;
            color: #205A44 !important;
        }
        .sidebar-link.active {
            background: #F7F6F3 !important;
            color: #205A44 !important;
            font-weight: 500 !important;
        }
        .coming-soon {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 400px;
            text-align: center;
            padding: 40px;
        }
        .coming-soon-icon {
            font-size: 64px;
            color: #205A44;
            margin-bottom: 20px;
            opacity: 0.7;
        }
        .coming-soon h2 {
            font-size: 28px;
            font-weight: 700;
            color: #063A1C;
            margin-bottom: 12px;
        }
        .coming-soon p {
            font-size: 16px;
            color: #B3B5B4;
            max-width: 500px;
        }
        /* Mobile Footer Navigation - Hidden by default */
        #mobileFooterNav {
            display: none;
        }
        
        /* Mobile responsive styles */
        @media (max-width: 1024px) {
            .container { margin-left: 0 !important; padding: 15px; width: 100% !important; }
            aside { transform: translateX(-100%); transition: transform 0.3s ease; }
            aside.sidebar-open { transform: translateX(0); }
            .header { padding: 15px !important; }
        }
        
        /* Mobile: Single line header with user name and time */
        @media (max-width: 767px) {
            /* Hide sidebar on mobile - completely remove from layout */
            #sidebar {
                display: none !important;
                width: 0 !important;
                height: 0 !important;
                position: absolute !important;
                left: -9999px !important;
                visibility: hidden !important;
            }
            
            /* Hide sidebar overlay on mobile */
            .sidebar-overlay {
                display: none !important;
            }
            
            /* Hide toggle button on mobile */
            .sidebar-toggle {
                display: none !important;
            }
            
            .container { 
                margin-left: 0 !important; 
                padding: 12px; 
                width: 100% !important; 
                padding-bottom: 70px !important; /* Space for footer */
            }
            
            .header {
                padding: 10px 12px;
                margin-bottom: 12px;
                flex-direction: row;
                align-items: center;
                gap: 8px;
            }
            
            .header-top {
                flex: 1;
                min-width: 0;
            }
            
            .header-title-mobile {
                font-size: 16px !important;
                line-height: 1.3;
                width: 100%;
                display: flex;
                align-items: center;
            }
            
            /* Hide page title on mobile */
            .header-page-title-desktop {
                display: none !important;
            }
            
            /* Show user info on mobile */
            .header-user-info-mobile {
                display: flex;
                flex: 1;
                min-width: 0;
            }
            
            .header-user-name-mobile {
                font-size: 14px;
                font-weight: 600;
                color: #063A1C;
            }
            
            .header-user-role-mobile {
                display: none !important;
            }
            
            /* Show date range selector on mobile in header user info area */
            .header-date-range-selector-mobile {
                display: block !important;
            }
            
            /* Hide date range selector below clock on mobile */
            .header-date-range-selector {
                display: none !important;
            }
            
            /* Mobile date range selector styling */
            .header-date-range-selector-mobile select {
                width: 100%;
                max-width: 120px;
            }
            
            .header-actions {
                flex: 0 0 auto;
                width: auto;
            }
            
            .header-actions-row {
                flex-direction: column;
                gap: 4px;
                align-items: flex-end;
            }
            
            #datetimeClock {
                min-width: 100px;
                padding: 6px 10px;
                font-size: 11px;
            }
            
            #clockTime {
                font-size: 12px;
            }
            
            #clockDate {
                font-size: 9px;
            }
            
            .header-user-name-desktop {
                display: none !important;
            }
            
            /* Hide logout button on mobile (should be in profile) */
            .header .btn-danger {
                display: none !important;
            }
            
            /* Hide notification bell on mobile */
            .header-actions-row > div[style*="position: relative"] {
                display: none !important;
            }
            
            /* Main content full width on mobile */
            #mainContent {
                margin-left: 0 !important;
                width: 100% !important;
                padding-bottom: 100px !important; /* Extra space for footer + buttons */
            }
            
            /* Ensure forms and containers have proper spacing */
            main {
                padding-bottom: 100px !important;
            }
            
            /* Add bottom margin to buttons in mobile view */
            .btn, button[type="submit"], input[type="submit"] {
                margin-bottom: 20px !important;
            }
            
            /* Global fix for all modals and forms on mobile */
            .modal, .form-container, form {
                padding-bottom: 100px !important;
            }
            
            /* Ensure modal content doesn't get hidden */
            .modal-content, .modal > div {
                max-height: calc(100vh - 150px) !important;
                overflow-y: auto !important;
                padding-bottom: 100px !important;
            }
            
            /* Specific fix for action buttons at bottom of forms/modals */
            .modal-footer, .form-actions, .button-group {
                margin-bottom: 80px !important;
                padding-bottom: 20px !important;
            }
            
            /* Footer Navigation for Mobile */
            #mobileFooterNav {
                display: flex;
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                width: 100%;
                background: white;
                border-top: 1px solid #e0e0e0;
                box-shadow: 0 -2px 8px rgba(0,0,0,0.1);
                z-index: 1000;
                padding: 8px 0;
                justify-content: space-around;
                align-items: center;
                height: 60px;
            }
            
            .footer-nav-link {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                text-decoration: none;
                color: #666;
                padding: 6px 4px;
                border-radius: 8px;
                transition: all 0.3s;
                flex: 1;
                max-width: 60px;
            }
            
            .footer-nav-link i {
                font-size: 18px;
                margin-bottom: 2px;
                display: block !important;
                color: inherit;
            }
            
            .footer-nav-link span {
                font-size: 9px;
                color: #666;
                text-align: center;
                line-height: 1.2;
                display: block !important;
            }
            
            .footer-nav-link:hover,
            .footer-nav-link.active {
                background: #F7F6F3;
                color: #205A44;
            }
            
            .footer-nav-link.active {
                color: #205A44;
            }
            
            .footer-nav-link.active i {
                color: #205A44 !important;
            }
            
            .footer-nav-link.active span {
                color: #205A44;
            }
        }
        
        /* Desktop - Show Sidebar, Hide Footer */
        @media (min-width: 768px) {
            #mobileFooterNav {
                display: none !important;
            }
            
            #sidebar {
                display: block !important;
            }
            
            #mainContent {
                margin-left: 64px !important;
                width: calc(100% - 64px) !important;
            }
            
            /* Show logout button on desktop */
            .header .btn-danger {
                display: block !important;
            }
            
            /* Show notification bell on desktop */
            .header-actions-row > div[style*="position: relative"] {
                display: block !important;
            }
            
            /* Desktop header layout */
            .header {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
            }
            
            .header-top {
                flex: 0 0 auto;
            }
            
            .header-actions {
                flex: 0 0 auto;
                width: auto;
            }
            
            .header-actions-row {
                flex-direction: row;
                align-items: center;
                gap: 16px;
            }
        }
        
        @media (max-width: 480px) {
            aside { width: 100%; max-width: 300px; }
            .container { padding: 8px !important; }
            .header { padding: 12px !important; }
        }
        
        /* Sidebar toggle button */
        .sidebar-toggle {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1001;
            background: #205A44;
            color: white;
            border: none;
            border-radius: 8px;
            width: 44px;
            height: 44px;
            cursor: pointer;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            transition: all 0.3s;
        }
        
        .sidebar-toggle:hover {
            background: #063A1C;
            transform: scale(1.05);
        }
        
        .sidebar-close-btn:hover {
            background: #e0e0e0;
        }
        
        @media (max-width: 1024px) {
            .sidebar-toggle { display: flex; }
            .sidebar-close-btn { display: flex !important; }
        }
        
        /* Prevent body scroll when sidebar is open on mobile */
        body.sidebar-open-mobile {
            overflow: hidden;
        }
        
        /* Custom Notification Styles */
        .custom-notification {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 10000;
            background: white;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
            padding: 40px 50px;
            min-width: 400px;
            max-width: 500px;
            text-align: center;
            opacity: 0;
            animation: fadeInScale 0.3s ease-out forwards;
        }
        
        @keyframes fadeInScale {
            from {
                opacity: 0;
                transform: translate(-50%, -50%) scale(0.8);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%) scale(1);
            }
        }
        
        .custom-notification.hide {
            animation: fadeOutScale 0.3s ease-in forwards;
        }
        
        @keyframes fadeOutScale {
            from {
                opacity: 1;
                transform: translate(-50%, -50%) scale(1);
            }
            to {
                opacity: 0;
                transform: translate(-50%, -50%) scale(0.8);
            }
        }
        
        .notification-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            opacity: 0;
            animation: fadeIn 0.3s ease-out forwards;
        }
        
        .notification-overlay.hide {
            animation: fadeOut 0.3s ease-in forwards;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
        
        .tick-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            position: relative;
        }
        
        .tick-icon svg {
            width: 100%;
            height: 100%;
        }
        
        .tick-circle {
            fill: #10b981;
            animation: scaleIn 0.4s ease-out;
        }
        
        .tick-path {
            stroke: white;
            stroke-width: 4;
            stroke-linecap: round;
            stroke-linejoin: round;
            fill: none;
            stroke-dasharray: 50;
            stroke-dashoffset: 50;
            animation: drawTick 0.6s ease-out 0.3s forwards;
        }
        
        @keyframes scaleIn {
            from {
                transform: scale(0);
            }
            to {
                transform: scale(1);
            }
        }
        
        @keyframes drawTick {
            to {
                stroke-dashoffset: 0;
            }
        }
        
        .notification-message {
            font-size: 18px;
            font-weight: 600;
            color: #063A1C;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        
        .notification-button {
            background: #205A44;
            color: white;
            border: none;
            padding: 12px 32px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .notification-button:hover {
            background: #063A1C;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(32, 90, 68, 0.3);
        }
        
        .error-notification .tick-circle {
            fill: #ef4444;
        }
        
        .error-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            border-radius: 50%;
            background: #ef4444;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: scaleIn 0.4s ease-out;
        }
        
        .error-icon::before,
        .error-icon::after {
            content: '';
            position: absolute;
            width: 3px;
            height: 40px;
            background: white;
            border-radius: 2px;
        }
        
        .error-icon::before {
            transform: rotate(45deg);
        }
        
        .error-icon::after {
            transform: rotate(-45deg);
        }
        
        .warning-notification .tick-circle {
            fill: #f59e0b;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        /* Telecaller workspace polish */
        body {
            background:
                radial-gradient(circle at 20% 0%, rgba(32, 90, 68, 0.08), transparent 28%),
                linear-gradient(180deg, #f5f8f4 0%, #f7f6f3 100%);
            color: #10231b;
        }
        #mainContent.container {
            max-width: none;
            padding: 24px 28px 96px;
        }
        .header {
            border: 1px solid rgba(32, 90, 68, 0.10);
            border-radius: 18px;
            box-shadow: 0 12px 28px rgba(6, 58, 28, 0.08);
        }
        .header-page-title-desktop {
            font-size: 20px;
            font-weight: 800;
            color: #063A1C;
        }
        #datetimeClock {
            border-radius: 14px !important;
            border-color: #d8e6de !important;
            box-shadow: 0 10px 24px rgba(6, 58, 28, 0.08) !important;
        }
        .header-date-range-selector select,
        .header-date-range-selector-mobile select {
            border-color: #cbdcd2 !important;
            border-radius: 10px !important;
            color: #063A1C !important;
            font-weight: 700;
        }
        #sidebar {
            background: linear-gradient(180deg, #052818 0%, #0b3d27 100%) !important;
            border-right: 0 !important;
            box-shadow: 8px 0 24px rgba(6, 58, 28, 0.14);
        }
        #sidebar .sidebar-link {
            color: rgba(255, 255, 255, 0.72) !important;
            border-radius: 14px !important;
            margin-bottom: 10px !important;
        }
        #sidebar .sidebar-link:hover,
        #sidebar .sidebar-link.active {
            background: rgba(255, 255, 255, 0.13) !important;
            color: #ffffff !important;
        }
        #sidebar .sidebar-link.active {
            box-shadow: inset 3px 0 0 #44d28f;
        }
        .btn {
            border-radius: 12px;
            font-weight: 700;
        }
        @media (max-width: 767px) {
            body {
                background: #f5f7f3;
            }
            #mainContent.container {
                padding: 10px 9px calc(86px + env(safe-area-inset-bottom, 0px)) !important;
            }
            main {
                padding-bottom: 0 !important;
            }
            .header {
                min-height: 128px;
                padding: 18px 16px !important;
                margin-bottom: 14px;
                border-radius: 16px;
                display: grid !important;
                grid-template-columns: minmax(0, 1fr) auto;
                align-items: start;
                gap: 12px;
            }
            .header-top,
            .header-actions,
            .header-actions-row {
                min-width: 0;
                width: auto;
            }
            .header-title-mobile {
                align-items: flex-start !important;
            }
            .header-user-info-mobile {
                gap: 8px;
            }
            .header-user-name-mobile {
                font-size: 17px !important;
                font-weight: 800 !important;
                color: #063A1C !important;
                white-space: normal !important;
            }
            .header-date-range-selector-mobile select {
                width: 150px !important;
                max-width: 150px !important;
                height: 30px !important;
                padding: 4px 10px !important;
                font-size: 12px !important;
            }
            #datetimeClock {
                min-width: 132px !important;
                padding: 11px 12px !important;
                border-radius: 12px !important;
            }
            #clockTime {
                font-size: 17px !important;
                letter-spacing: 0 !important;
            }
            #clockDate {
                font-size: 11px !important;
                color: #7a897f !important;
            }
            #mobileFooterNav {
                height: calc(68px + env(safe-area-inset-bottom, 0px)) !important;
                padding: 8px 8px calc(8px + env(safe-area-inset-bottom, 0px)) !important;
                border-top: 1px solid #dfe7e1 !important;
                box-shadow: 0 -14px 28px rgba(6, 58, 28, 0.10) !important;
                position: fixed !important;
                left: 0 !important;
                right: 0 !important;
                bottom: 0 !important;
                width: 100% !important;
                border-radius: 0 !important;
            }
            .footer-nav-link {
                max-width: none !important;
                min-width: 0;
                height: 52px;
                border-radius: 14px !important;
                gap: 3px;
            }
            .footer-nav-link i {
                font-size: 18px !important;
                margin-bottom: 0 !important;
            }
            .footer-nav-link span {
                font-size: 10px !important;
                font-weight: 700;
                white-space: nowrap;
            }
            .footer-nav-link.active {
                background: #f0f5f1 !important;
                color: #0b6b48 !important;
            }
            .footer-nav-link.active i,
            .footer-nav-link.active span {
                color: #0b6b48 !important;
            }
            .modal,
            .form-container,
            form {
                padding-bottom: 0 !important;
            }
            .modal-content,
            .modal > div {
                padding-bottom: 24px !important;
            }
            .modal-footer,
            .form-actions,
            .button-group {
                margin-bottom: 0 !important;
                padding-bottom: 0 !important;
            }
        }
    </style>
    @stack('styles')
    <style>
        /* Final telecaller overrides: loaded after page styles */
        @media (max-width: 767px) {
            .header {
                background: linear-gradient(135deg, #063A1C 0%, #0f6b46 100%) !important;
                border: 0 !important;
                box-shadow: 0 18px 36px rgba(6, 58, 28, 0.22) !important;
                color: #ffffff !important;
            }
            .header-user-name-mobile {
                color: #ffffff !important;
            }
            .header-date-range-selector-mobile select {
                background: rgba(255, 255, 255, 0.96) !important;
                border: 0 !important;
                color: #063A1C !important;
            }
            #datetimeClock {
                background: rgba(255, 255, 255, 0.14) !important;
                border: 1px solid rgba(255, 255, 255, 0.22) !important;
                box-shadow: none !important;
                color: #ffffff !important;
            }
            #clockTime,
            #clockDate {
                color: #ffffff !important;
            }
            #clockDate {
                opacity: 0.78;
            }
            .header-actions-row > div[style*="position: relative"] {
                display: none !important;
            }
        }
        .telecaller-top-header {
            display: none !important;
        }
        #mainContent.container {
            padding-top: 16px !important;
        }
        @media (max-width: 767px) {
            #mainContent.container {
                padding-top: 10px !important;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar Toggle Button (Mobile) -->
    <button class="sidebar-toggle" id="sidebarToggle" onclick="toggleSidebar()" aria-label="Toggle Sidebar">
        <i class="fas fa-bars" id="sidebarToggleIcon"></i>
    </button>
    
    <!-- Sidebar Overlay (Mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
    
    <!-- Sidebar -->
    <aside id="sidebar" class="fixed left-0 top-0 h-full w-64 bg-[#F7F6F3] border-r border-[#E5DED4] shadow-sm z-30" style="overflow-y: auto;">
        <div style="padding: 20px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center;">
            <h2 style="font-size: 24px; font-weight: 700; color: #063A1C; margin-bottom: 0;">{{ brand_name() }}</h2>
            <button onclick="closeSidebar()" class="sidebar-close-btn" id="sidebarCloseBtn" style="display: none; background: none; border: none; font-size: 24px; color: #063A1C; cursor: pointer; padding: 5px; width: 32px; height: 32px; align-items: center; justify-content: center; border-radius: 4px; transition: all 0.3s;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        @php
            $dateParams = array_filter(request()->only(['date_range', 'start_date', 'end_date']));
            $dateQuery = !empty($dateParams) ? '?' . http_build_query($dateParams) : '';
        @endphp
        <nav style="padding: 0 20px;">
            <a href="{{ route('telecaller.dashboard') }}{{ $dateQuery }}" class="sidebar-link {{ request()->routeIs('telecaller.dashboard') ? 'active' : '' }}" style="display: flex; align-items: center; padding: 12px 16px; margin-bottom: 8px; border-radius: 8px; text-decoration: none; color: {{ request()->routeIs('telecaller.dashboard') ? '#205A44' : '#063A1C' }}; transition: all 0.3s; {{ request()->routeIs('telecaller.dashboard') ? 'background: #F7F6F3; font-weight: 500;' : '' }}">
                <i class="fas fa-home" style="margin-right: 10px; width: 20px;"></i>
                Dashboard
            </a>
            <a href="{{ route('telecaller.tasks') }}{{ $dateQuery }}" class="sidebar-link {{ request()->routeIs('telecaller.tasks') ? 'active' : '' }}" style="display: flex; align-items: center; padding: 12px 16px; margin-bottom: 8px; border-radius: 8px; text-decoration: none; color: {{ request()->routeIs('telecaller.tasks') ? '#205A44' : '#063A1C' }}; transition: all 0.3s; {{ request()->routeIs('telecaller.tasks') ? 'background: #F7F6F3; font-weight: 500;' : '' }}">
                <i class="fas fa-tasks" style="margin-right: 10px; width: 20px;"></i>
                Task
            </a>
            <a href="{{ route('telecaller.leads') }}{{ $dateQuery }}" class="sidebar-link {{ request()->routeIs('telecaller.leads') ? 'active' : '' }}" style="display: flex; align-items: center; padding: 12px 16px; margin-bottom: 8px; border-radius: 8px; text-decoration: none; color: {{ request()->routeIs('telecaller.leads') ? '#205A44' : '#063A1C' }}; transition: all 0.3s; {{ request()->routeIs('telecaller.leads') ? 'background: #F7F6F3; font-weight: 500;' : '' }}">
                <i class="fas fa-user-friends" style="margin-right: 10px; width: 20px;"></i>
                Lead
            </a>
            {{-- Reports section hidden --}}
            {{-- <a href="{{ route('telecaller.reports') }}" class="sidebar-link {{ request()->routeIs('telecaller.reports') ? 'active' : '' }}" style="display: flex; align-items: center; padding: 12px 16px; margin-bottom: 8px; border-radius: 8px; text-decoration: none; color: {{ request()->routeIs('telecaller.reports') ? '#205A44' : '#063A1C' }}; transition: all 0.3s; {{ request()->routeIs('telecaller.reports') ? 'background: #F7F6F3; font-weight: 500;' : '' }}">
                <i class="fas fa-chart-bar" style="margin-right: 10px; width: 20px;"></i>
                Report
            </a> --}}
            <a href="{{ route('telecaller.verification-pending') }}" class="sidebar-link {{ request()->routeIs('telecaller.verification-pending') ? 'active' : '' }}" style="display: flex; align-items: center; padding: 12px 16px; margin-bottom: 8px; border-radius: 8px; text-decoration: none; color: {{ request()->routeIs('telecaller.verification-pending') ? '#205A44' : '#063A1C' }}; transition: all 0.3s; {{ request()->routeIs('telecaller.verification-pending') ? 'background: #F7F6F3; font-weight: 500;' : '' }}">
                <i class="fas fa-clock" style="margin-right: 10px; width: 20px;"></i>
                Verification Pending
            </a>
            <a href="{{ route('telecaller.profile') }}" class="sidebar-link {{ request()->routeIs('telecaller.profile') ? 'active' : '' }}" style="display: flex; align-items: center; padding: 12px 16px; margin-bottom: 8px; border-radius: 8px; text-decoration: none; color: {{ request()->routeIs('telecaller.profile') ? '#205A44' : '#063A1C' }}; transition: all 0.3s; {{ request()->routeIs('telecaller.profile') ? 'background: #F7F6F3; font-weight: 500;' : '' }}">
                <i class="fas fa-user" style="margin-right: 10px; width: 20px;"></i>
                Profile
            </a>
        </nav>
    </aside>
    
    <div class="container" id="mainContent" style="margin-left: 64px;">
        <!-- Header -->
        <div class="header telecaller-top-header" aria-hidden="true">
            <div class="header-top">
                <h1 class="header-title-mobile" style="font-size: 24px; font-weight: 700; color: #063A1C;">
                    <span class="header-page-title-desktop">@yield('page-title', 'Telecaller Dashboard')</span>
                    <div class="header-user-info-mobile">
                        <span class="header-user-name-mobile">{{ auth()->user()->name }}</span>
                        <span class="header-user-role-mobile" style="display: none;">{{ auth()->user()->getDisplayRoleName() ?? 'User' }}</span>
                        <!-- Date Range Selector - Mobile (replaces role) -->
                        <div class="header-date-range-selector-mobile" id="headerDateRangeSelectorMobile" style="display: none;">
                            <select id="headerDateRangeSelectMobile" onchange="handleHeaderDateRangeChange(event)" style="padding: 2px 5px; border: 1px solid #e0e0e0; border-radius: 4px; font-size: 10px; background: white; color: #063A1C; cursor: pointer; outline: none; width: 100%; max-width: 120px; height: 24px; margin-top: 4px;">
                                <option value="today" {{ (request()->get('date_range') ?? 'today') === 'today' ? 'selected' : '' }}>Today</option>
                                <option value="this_week" {{ request()->get('date_range') === 'this_week' ? 'selected' : '' }}>This Week</option>
                                <option value="this_month" {{ request()->get('date_range') === 'this_month' ? 'selected' : '' }}>This Month</option>
                                <option value="all" {{ request()->get('date_range') === 'all' ? 'selected' : '' }}>All</option>
                                <option value="custom" {{ request()->get('date_range') === 'custom' ? 'selected' : '' }}>Custom</option>
                            </select>
                            <div class="header-custom-date-inputs-mobile" id="headerCustomDateInputsMobile" style="display: {{ request()->get('date_range') === 'custom' ? 'flex' : 'none' }}; gap: 3px; margin-top: 3px; flex-direction: column;">
                                <input type="date" id="headerStartDateMobile" value="{{ request()->get('start_date') ?? '' }}" onchange="handleHeaderCustomDateChange()" style="padding: 2px 4px; border: 1px solid #e0e0e0; border-radius: 4px; font-size: 9px; width: 100%; max-width: 120px; height: 22px;">
                                <input type="date" id="headerEndDateMobile" value="{{ request()->get('end_date') ?? '' }}" onchange="handleHeaderCustomDateChange()" style="padding: 2px 4px; border: 1px solid #e0e0e0; border-radius: 4px; font-size: 9px; width: 100%; max-width: 120px; height: 22px;">
                            </div>
                        </div>
                    </div>
                </h1>
            </div>
            <div class="header-actions">
                <div class="header-actions-row">
                    <!-- Date/Time Clock -->
                    <div style="display: flex; flex-direction: column; gap: 6px; align-items: flex-end;">
                        <div id="datetimeClock" style="background: white; border: 1px solid #e0e0e0; border-radius: 8px; padding: 8px 12px; font-family: 'Courier New', monospace; font-weight: 600; font-size: 12px; color: #063A1C; min-width: 160px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                            <div id="clockTime" style="font-size: 16px; color: #205A44;">--:--:--</div>
                            <div id="clockDate" style="font-size: 11px; color: #B3B5B4; margin-top: 2px;">-- -- ----</div>
                        </div>
                        <!-- Date Range Selector - Small, below clock (only on dashboard) -->
                        <div class="header-date-range-selector" id="headerDateRangeSelector" style="display: none;">
                            <select id="headerDateRangeSelect" onchange="handleHeaderDateRangeChange(event)" style="padding: 2px 5px; border: 1px solid #e0e0e0; border-radius: 4px; font-size: 10px; background: white; color: #063A1C; cursor: pointer; outline: none; width: 80px; max-width: 80px; height: 24px;">
                                <option value="today" {{ (request()->get('date_range') ?? 'today') === 'today' ? 'selected' : '' }}>Today</option>
                                <option value="this_week" {{ request()->get('date_range') === 'this_week' ? 'selected' : '' }}>This Week</option>
                                <option value="this_month" {{ request()->get('date_range') === 'this_month' ? 'selected' : '' }}>This Month</option>
                                <option value="all" {{ request()->get('date_range') === 'all' ? 'selected' : '' }}>All</option>
                                <option value="custom" {{ request()->get('date_range') === 'custom' ? 'selected' : '' }}>Custom</option>
                            </select>
                            <div class="header-custom-date-inputs" id="headerCustomDateInputs" style="display: {{ request()->get('date_range') === 'custom' ? 'flex' : 'none' }}; gap: 3px; margin-top: 3px; flex-direction: column;">
                                <input type="date" id="headerStartDate" value="{{ request()->get('start_date') ?? '' }}" onchange="handleHeaderCustomDateChange()" style="padding: 2px 4px; border: 1px solid #e0e0e0; border-radius: 4px; font-size: 9px; width: 80px; height: 22px;">
                                <input type="date" id="headerEndDate" value="{{ request()->get('end_date') ?? '' }}" onchange="handleHeaderCustomDateChange()" style="padding: 2px 4px; border: 1px solid #e0e0e0; border-radius: 4px; font-size: 9px; width: 80px; height: 22px;">
                            </div>
                        </div>
                    </div>
                    <span class="header-user-name-desktop" style="color: #B3B5B4; font-size: 14px; white-space: nowrap;">{{ auth()->user()->name }}</span>
                    @include('components.global-notification-center')
                    <!-- Logout button - hidden on mobile (should be in profile) -->
                    <button onclick="logout()" class="btn btn-danger" style="display: block;">Logout</button>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <main>
            @yield('content')
        </main>
    </div>

    <!-- Mobile Footer Navigation -->
    <nav id="mobileFooterNav">
        <a href="{{ route('telecaller.dashboard') }}{{ $dateQuery ?? '' }}" class="footer-nav-link {{ request()->routeIs('telecaller.dashboard') ? 'active' : '' }}">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        <a href="{{ route('telecaller.tasks') }}{{ $dateQuery ?? '' }}" class="footer-nav-link {{ request()->routeIs('telecaller.tasks*') ? 'active' : '' }}">
            <i class="fas fa-tasks"></i>
            <span>Tasks</span>
        </a>
        <a href="{{ route('telecaller.leads') }}{{ $dateQuery ?? '' }}" class="footer-nav-link {{ request()->routeIs('telecaller.leads*') ? 'active' : '' }}">
            <i class="fas fa-user-friends"></i>
            <span>Leads</span>
        </a>
        <a href="{{ route('telecaller.verification-pending') }}" class="footer-nav-link {{ request()->routeIs('telecaller.verification-pending*') ? 'active' : '' }}">
            <i class="fas fa-clock"></i>
            <span>Verification</span>
        </a>
        <a href="{{ route('telecaller.profile') }}" class="footer-nav-link {{ request()->routeIs('telecaller.profile*') ? 'active' : '' }}">
            <i class="fas fa-user"></i>
            <span>Profile</span>
        </a>
    </nav>

    <!-- Custom Notification Component -->
    <div id="notificationOverlay" class="notification-overlay" style="display: none;"></div>
    <div id="customNotification" class="custom-notification" style="display: none;">
        <div class="tick-icon">
            <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                <circle class="tick-circle" cx="50" cy="50" r="45"/>
                <path class="tick-path" d="M 30 50 L 45 65 L 70 35"/>
            </svg>
        </div>
        <div class="notification-message" id="notificationMessage"></div>
        <button class="notification-button" onclick="closeNotification()">OK</button>
    </div>

    <script>
        var API_BASE_URL = '{{ url("/api") }}';
        
        // Initialize token from session on page load for web-logged-in telecallers
        @if(auth()->check() && auth()->user()->isTelecaller())
            @php
                $token = session('telecaller_api_token') ?? session('api_token');
                $user = auth()->user()->load('role', 'manager');
            @endphp
            @if($token)
                localStorage.setItem('telecaller_token', '{{ $token }}');
                try {
                    const userData = @json($user);
                    localStorage.setItem('telecaller_user', JSON.stringify(userData));
                    // Password auto-fill removed for security — user types current password manually
                    localStorage.removeItem('user_current_password');
                    console.log('Token initialized from session');
                } catch (e) {
                    console.error('Error setting user data in localStorage:', e);
                }
            @endif
        @endif
        
        // Get token from localStorage
        function getToken() {
            return localStorage.getItem('telecaller_token');
        }

        // Load user info
        function loadUserInfo() {
            const userStr = localStorage.getItem('telecaller_user');
            if (userStr) {
                try {
                    // Check if it's already an object or a string
                    let user;
                    if (typeof userStr === 'string') {
                        user = JSON.parse(userStr);
                    } else {
                        user = userStr; // Already an object
                    }
                    const userNameEl = document.getElementById('userName');
                    if (userNameEl && user) {
                        userNameEl.textContent = user.name || 'User';
                    }
                } catch (e) {
                    console.error('Error parsing user data:', e);
                    // Try to get user from session as fallback
                    @if(auth()->check() && auth()->user()->isTelecaller())
                        @php
                            $user = auth()->user();
                        @endphp
                        const userNameEl = document.getElementById('userName');
                        if (userNameEl) {
                            userNameEl.textContent = '{{ $user->name }}';
                        }
                    @endif
                }
            } else {
                // If no user in localStorage but user is logged in via session, use session data
                @if(auth()->check() && auth()->user()->isTelecaller())
                    @php
                        $user = auth()->user();
                    @endphp
                    const userNameEl = document.getElementById('userName');
                    if (userNameEl) {
                        userNameEl.textContent = '{{ $user->name }}';
                    }
                @endif
            }
        }

        // Logout function: clear token, then hit web logout so server session is cleared and redirects to login
        async function logout() {
            if (window.NotificationDeviceOwnership) {
                await window.NotificationDeviceOwnership.unregister();
            }
            try {
                const token = getToken();
                if (token) {
                    try {
                        await fetch(`${API_BASE_URL}/telecaller/logout`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'Authorization': `Bearer ${token}`,
                            },
                        });
                    } catch (error) {
                        console.error('Logout API call failed:', error);
                    }
                }
            } catch (error) {
                console.error('Error during logout:', error);
            } finally {
                localStorage.removeItem('telecaller_token');
                localStorage.removeItem('telecaller_user');
                localStorage.removeItem('user_current_password');
                // Use web logout URL so server session is cleared; then server redirects to login page
                window.location.href = '{{ route("logout.get") }}';
            }
        }

        // Custom Notification Functions
        let notificationTimeout = null;
        
        function showNotification(message, type = 'success', duration = 3000) {
            const overlay = document.getElementById('notificationOverlay');
            const notification = document.getElementById('customNotification');
            const messageEl = document.getElementById('notificationMessage');
            const tickIcon = notification.querySelector('.tick-icon');
            
            // Clear any existing timeout
            if (notificationTimeout) {
                clearTimeout(notificationTimeout);
            }
            
            // Remove previous type classes
            notification.classList.remove('success-notification', 'error-notification', 'warning-notification');
            notification.classList.add(type + '-notification');
            
            // Update message
            messageEl.textContent = message;
            
            // Update icon based on type
            if (type === 'error') {
                tickIcon.innerHTML = `
                    <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                        <circle class="tick-circle" cx="50" cy="50" r="45" fill="#ef4444"/>
                        <path d="M 30 30 L 70 70 M 70 30 L 30 70" stroke="white" stroke-width="6" stroke-linecap="round" stroke-dasharray="50" stroke-dashoffset="50" class="tick-path"/>
                    </svg>
                `;
            } else if (type === 'warning') {
                tickIcon.innerHTML = `
                    <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                        <circle class="tick-circle" cx="50" cy="50" r="45"/>
                        <text x="50" y="70" text-anchor="middle" fill="white" font-size="60" font-weight="bold">!</text>
                    </svg>
                `;
            } else {
                // Success tick
                tickIcon.innerHTML = `
                    <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                        <circle class="tick-circle" cx="50" cy="50" r="45"/>
                        <path class="tick-path" d="M 30 50 L 45 65 L 70 35"/>
                    </svg>
                `;
            }
            
            // Show notification
            overlay.style.display = 'block';
            notification.style.display = 'block';
            overlay.classList.remove('hide');
            notification.classList.remove('hide');
            
            // Auto hide after duration
            if (duration > 0) {
                notificationTimeout = setTimeout(() => {
                    closeNotification();
                }, duration);
            }
        }
        
        function closeNotification() {
            const overlay = document.getElementById('notificationOverlay');
            const notification = document.getElementById('customNotification');
            
            overlay.classList.add('hide');
            notification.classList.add('hide');
            
            setTimeout(() => {
                overlay.style.display = 'none';
                notification.style.display = 'none';
                overlay.classList.remove('hide');
                notification.classList.remove('hide');
            }, 300);
            
            if (notificationTimeout) {
                clearTimeout(notificationTimeout);
                notificationTimeout = null;
            }
        }
        
        // Override browser alert for better UX
        window.customAlert = function(message, type = 'success') {
            showNotification(message, type, 3000);
        };

        // Sidebar toggle functionality
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const toggleIcon = document.getElementById('sidebarToggleIcon');
            const body = document.body;
            
            sidebar.classList.toggle('sidebar-open');
            overlay.classList.toggle('active');
            body.classList.toggle('sidebar-open-mobile');
            
            // Change icon
            if (sidebar.classList.contains('sidebar-open')) {
                toggleIcon.classList.remove('fa-bars');
                toggleIcon.classList.add('fa-times');
            } else {
                toggleIcon.classList.remove('fa-times');
                toggleIcon.classList.add('fa-bars');
            }
        }
        
        function closeSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const toggleIcon = document.getElementById('sidebarToggleIcon');
            const body = document.body;
            
            sidebar.classList.remove('sidebar-open');
            overlay.classList.remove('active');
            body.classList.remove('sidebar-open-mobile');
            
            toggleIcon.classList.remove('fa-times');
            toggleIcon.classList.add('fa-bars');
        }
        
        // Close sidebar when clicking on a link (mobile)
        document.querySelectorAll('.sidebar-link').forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 1024) {
                    setTimeout(closeSidebar, 300);
                }
            });
        });
        
        // Responsive container margin
        function adjustContainerMargin() {
            const container = document.getElementById('mainContent');
            const sidebar = document.getElementById('sidebar');
            
            if (window.innerWidth <= 767) {
                // Mobile: no sidebar, full width
                container.style.marginLeft = '0';
                container.style.width = '100%';
                container.style.paddingBottom = '70px'; // Space for footer
            } else if (window.innerWidth <= 1024) {
                // Tablet: sidebar toggleable
                container.style.marginLeft = '0';
            } else {
                // Desktop: sidebar always visible
                container.style.marginLeft = '64px';
                container.style.paddingBottom = '0';
            }
        }
        
        // Adjust on load and resize
        window.addEventListener('resize', adjustContainerMargin);
        adjustContainerMargin();

        // Initialize on page load
        (function() {
            loadUserInfo();
        })();
    </script>
    
    @stack('scripts')
    
    <!-- FCM Push: Firebase Cloud Messaging for notifications -->
    <script src="https://www.gstatic.com/firebasejs/10.14.1/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/10.14.1/firebase-messaging-compat.js"></script>
    <script>
    (function() {
        var configMeta = document.querySelector('meta[name="firebase-config"]');
        var vapidMeta = document.querySelector('meta[name="firebase-vapid-key"]');
        if (!configMeta || !vapidMeta) return;
        var firebaseConfig;
        try { firebaseConfig = JSON.parse(configMeta.content); } catch(e) { return; }
        if (!firebaseConfig || !firebaseConfig.api_key) return;

        firebase.initializeApp({
            apiKey: firebaseConfig.api_key, authDomain: firebaseConfig.auth_domain,
            projectId: firebaseConfig.project_id, storageBucket: firebaseConfig.storage_bucket,
            messagingSenderId: firebaseConfig.messaging_sender_id, appId: firebaseConfig.app_id
        });
        var messaging = firebase.messaging();

        function getAuthToken() {
            var meta = document.querySelector('meta[name="api-token"]');
            if (meta && meta.content) return meta.content;
            try { return localStorage.getItem('telecaller_token') || localStorage.getItem('auth_token') || ''; } catch(e) { return ''; }
        }
        function sendFcmTokenToServer(fcmToken) {
            if (window.NotificationDeviceOwnership) {
                window.NotificationDeviceOwnership.claimFcm(fcmToken);
                return;
            }
            var authToken = getAuthToken();
            if (!authToken) return;
            fetch(window.location.origin + '/api/fcm-subscription', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'Authorization': 'Bearer ' + authToken },
                body: JSON.stringify({ fcm_token: fcmToken, device_type: 'web' })
            }).catch(function() {});
        }
        function initFcm() {
            navigator.serviceWorker.register('/fcm-sw.js').then(function(reg) {
                messaging.getToken({ vapidKey: vapidMeta.content, serviceWorkerRegistration: reg }).then(function(token) {
                    if (token) sendFcmTokenToServer(token);
                }).catch(function() {});
            }).catch(function() {});
        }
        messaging.onMessage(function(payload) {
            if (!window.NotificationDeviceOwnership || !window.NotificationDeviceOwnership.isForCurrentUser(payload)) return;
            if (typeof window.handleIncomingFcmMessage === 'function' && window.handleIncomingFcmMessage(payload)) {
                return;
            }
            var data = payload.data || {};
            var notification = payload.notification || {};
            var kind = data.kind || '';
            var tag = data.tag || '';
            var title = notification.title || data.title || '';
            if (kind === 'announcement' || String(tag).indexOf('announcement-') === 0) {
                return;
            }
            if (([
                'lead_assigned',
                'followup_reminder',
                'followup_overdue',
                'meeting',
                'meeting_reminder',
                'site_visit',
                'site_visit_reminder',
                'task_overdue',
                'execution_task_due',
                'execution_task_overdue',
                'activity_lifecycle_reminder',
                'manager_task_reminder',
                'telecaller_call_reminder',
                'attendance_reminder'
            ].indexOf(kind) !== -1 || String(tag).indexOf('lead-assigned') === 0 || title === 'New lead assigned') && typeof showLeadAssignedPopup === 'function') {
                var n = payload.notification || payload.data || {};
                showLeadAssignedPopup({
                    title: n.title || title || 'Reminder',
                    message: n.body || data.body || '',
                    viewUrl: data.secondary_action_url || data.url || data.click_action || viewUrlDefault,
                    leadPhone: data.lead_phone || '',
                    primaryActionLabel: data.primary_action_label || '',
                    primaryActionUrl: data.primary_action_url || '',
                    primaryActionMethod: data.primary_action_method || 'GET',
                    secondaryActionLabel: data.secondary_action_label || '',
                    secondaryActionUrl: data.secondary_action_url || data.complete_action_url || '',
                    secondaryActionMethod: data.secondary_action_method || (data.complete_action_url ? 'POST' : 'GET'),
                    dismissLabel: data.dismiss_label || 'Dismiss',
                    soundUrl: data.sound_url || ''
                });
            }
        });
        if (Notification.permission === 'granted') { initFcm(); }
        else if (Notification.permission === 'default') {
            Notification.requestPermission().then(function(p) { if (p === 'granted') initFcm(); });
        }
    })();
    </script>
    
    <!-- Live Clock Functionality -->
    <script>
        function updateClock() {
            const now = new Date();
            const timeElement = document.getElementById('clockTime');
            const dateElement = document.getElementById('clockDate');
            
            if (timeElement && dateElement) {
                // Format time: HH:MM:SS
                const hours = String(now.getHours()).padStart(2, '0');
                const minutes = String(now.getMinutes()).padStart(2, '0');
                const seconds = String(now.getSeconds()).padStart(2, '0');
                timeElement.textContent = `${hours}:${minutes}:${seconds}`;
                
                // Format date: DD MMM YYYY
                const date = now.toLocaleDateString('en-IN', {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric'
                });
                dateElement.textContent = date;
            }
        }
        
        // Update clock immediately and then every second
        updateClock();
        setInterval(updateClock, 1000);

        // Show date range selector on dashboard, tasks, and leads pages (so date filter is preserved when switching)
        const showDateSelector = window.location.pathname.includes('/telecaller/dashboard') ||
            window.location.pathname.includes('/telecaller/tasks') ||
            window.location.pathname.includes('/telecaller/leads');
        if (showDateSelector) {
            const dateRangeSelector = document.getElementById('headerDateRangeSelector');
            if (dateRangeSelector && window.innerWidth >= 768) {
                dateRangeSelector.style.display = 'block';
            }
            const dateRangeSelectorMobile = document.getElementById('headerDateRangeSelectorMobile');
            if (dateRangeSelectorMobile && window.innerWidth < 768) {
                dateRangeSelectorMobile.style.display = 'block';
            }
        }
        
        // Handle window resize
        window.addEventListener('resize', function() {
            const showDateSelector = window.location.pathname.includes('/telecaller/dashboard') ||
                window.location.pathname.includes('/telecaller/tasks') ||
                window.location.pathname.includes('/telecaller/leads');
            if (showDateSelector) {
                const dateRangeSelector = document.getElementById('headerDateRangeSelector');
                const dateRangeSelectorMobile = document.getElementById('headerDateRangeSelectorMobile');
                if (window.innerWidth >= 768) {
                    if (dateRangeSelector) dateRangeSelector.style.display = 'block';
                    if (dateRangeSelectorMobile) dateRangeSelectorMobile.style.display = 'none';
                } else {
                    if (dateRangeSelector) dateRangeSelector.style.display = 'none';
                    if (dateRangeSelectorMobile) dateRangeSelectorMobile.style.display = 'block';
                }
            }
        });

        // Handle header date range change (works for both desktop and mobile)
        function handleHeaderDateRangeChange(ev) {
            // Use the select that was actually changed (so mobile/desktop value is correct after refresh)
            const select = (ev && ev.target && ev.target.id) ? ev.target : (document.getElementById('headerDateRangeSelect') || document.getElementById('headerDateRangeSelectMobile'));
            const customInputs = document.getElementById('headerCustomDateInputs') || document.getElementById('headerCustomDateInputsMobile');
            const dateRange = select.value;

            if (dateRange === 'custom') {
                if (customInputs) {
                    customInputs.style.display = 'flex';
                }
            } else {
                if (customInputs) {
                    customInputs.style.display = 'none';
                }
                // Reload page with new date range
                const url = new URL(window.location.href);
                url.searchParams.set('date_range', dateRange);
                url.searchParams.delete('start_date');
                url.searchParams.delete('end_date');
                window.location.href = url.toString();
            }
        }

        // Handle header custom date change (works for both desktop and mobile)
        function handleHeaderCustomDateChange() {
            const startDate = document.getElementById('headerStartDate')?.value || document.getElementById('headerStartDateMobile')?.value;
            const endDate = document.getElementById('headerEndDate')?.value || document.getElementById('headerEndDateMobile')?.value;

            if (startDate && endDate) {
                // Validate: end date should be >= start date
                if (new Date(endDate) < new Date(startDate)) {
                    alert('End date must be greater than or equal to start date');
                    return;
                }

                // Reload page with custom dates
                const url = new URL(window.location.href);
                url.searchParams.set('date_range', 'custom');
                url.searchParams.set('start_date', startDate);
                url.searchParams.set('end_date', endDate);
                window.location.href = url.toString();
            }
        }
    </script>
    
    @auth
    <!-- Lead assigned modal with ringtone -->
    <style>
        @keyframes bellRing { 0%,100%{transform:rotate(0)} 15%{transform:rotate(14deg)} 30%{transform:rotate(-14deg)} 45%{transform:rotate(10deg)} 60%{transform:rotate(-10deg)} 75%{transform:rotate(4deg)} 90%{transform:rotate(-4deg)} }
        @keyframes pulseGlow { 0%,100%{box-shadow:0 0 0 0 rgba(34,197,94,.4)} 50%{box-shadow:0 0 0 16px rgba(34,197,94,0)} }
        #lead-assigned-overlay:not(.hidden) #lead-ring-bell { animation: bellRing .8s ease-in-out infinite; }
        #lead-assigned-overlay:not(.hidden) #lead-assigned-modal { animation: pulseGlow 2s ease-in-out infinite; }
    </style>
    <div id="lead-assigned-overlay" class="fixed inset-0 bg-black/50 z-[100] flex items-center justify-center p-4 hidden" aria-hidden="true">
        <div id="lead-assigned-modal" class="bg-white rounded-xl shadow-2xl max-w-md w-full p-6 relative" role="dialog" aria-labelledby="lead-assigned-title">
            <button type="button" id="lead-assigned-close-x" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 text-2xl leading-none" aria-label="Close">&times;</button>
            <div class="flex justify-center mb-3">
                <span id="lead-ring-bell" style="font-size:2.5rem;display:inline-block;">&#128276;</span>
            </div>
            <h2 id="lead-assigned-title" class="text-xl font-bold text-gray-900 mb-2 text-center">New lead assigned</h2>
            <p id="lead-assigned-message" class="text-gray-600 mb-6 text-center">You have a new lead assigned. View leads to see details and call.</p>
            <div id="lead-ringtone-timer" class="text-center text-sm text-gray-400 mb-4 hidden">Ringing... <span id="lead-ringtone-countdown">30</span>s</div>
            <div class="flex flex-wrap gap-3 justify-center">
                <a id="lead-assigned-view-btn" href="{{ route('telecaller.tasks') }}?status=pending" class="px-4 py-2 rounded-lg font-semibold text-white bg-[#063A1C] hover:bg-[#205A44] transition">View leads</a>
                <a id="lead-assigned-call-btn" href="#" class="px-4 py-2 rounded-lg font-semibold bg-green-600 text-white hover:bg-green-700 transition hidden">Call</a>
                <button type="button" id="lead-assigned-cancel-btn" class="px-4 py-2 rounded-lg font-semibold bg-gray-200 text-gray-700 hover:bg-gray-300 transition">Cancel</button>
            </div>
        </div>
    </div>
    <script>
    (function() {
        var overlay = document.getElementById('lead-assigned-overlay');
        var titleEl = document.getElementById('lead-assigned-title');
        var messageEl = document.getElementById('lead-assigned-message');
        var viewBtn = document.getElementById('lead-assigned-view-btn');
        var callBtn = document.getElementById('lead-assigned-call-btn');
        var completeBtn = document.getElementById('lead-assigned-cancel-btn');
        var timerEl = document.getElementById('lead-ringtone-timer');
        var countdownEl = document.getElementById('lead-ringtone-countdown');
        var viewUrlDefault = viewBtn ? viewBtn.getAttribute('href') : '';

        var leadRingtone = null;
        var ringtoneTimeout = null;
        var countdownInterval = null;
        var fallbackRingtoneUrl = '/sounds/lead-ringtone.mp3';

        function apiAuthHeaders() {
            var headers = { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
            var csrf = document.querySelector('meta[name="csrf-token"]');
            if (csrf && csrf.content) headers['X-CSRF-TOKEN'] = csrf.content;
            var apiToken = document.querySelector('meta[name="api-token"]');
            var bearer = (apiToken && apiToken.content) || localStorage.getItem('telecaller_token') || localStorage.getItem('auth_token') || '';
            if (bearer) headers['Authorization'] = 'Bearer ' + bearer;
            return headers;
        }

        async function runPopupAction(action) {
            if (!action || !action.url || action.method === 'DISMISS') {
                window.closeLeadAssignedModal();
                return;
            }
            if (action.url.indexOf('tel:') === 0 || action.method === 'GET') {
                window.location.href = action.url;
                return;
            }
            var button = action.button || completeBtn;
            var originalLabel = button ? button.textContent : 'Working...';
            if (button) {
                button.disabled = true;
                button.textContent = action.loadingLabel || 'Working...';
            }
            try {
                var response = await fetch(action.url, {
                    method: action.method,
                    headers: apiAuthHeaders(),
                    credentials: 'same-origin',
                    body: JSON.stringify(action.payload || { outcome: 'completed_from_popup' })
                });
                var result = await response.json().catch(function() { return {}; });
                if (!response.ok || result.success === false) throw new Error(result.message || 'Action failed');
                if (messageEl) messageEl.textContent = result.message || 'Done.';
                setTimeout(function() { window.closeLeadAssignedModal(); }, 700);
            } catch (e) {
                if (messageEl) messageEl.textContent = e.message || 'Action failed. Please open CRM and try again.';
            } finally {
                if (button) {
                    button.disabled = false;
                    button.textContent = originalLabel;
                }
            }
        }

        function stopRingtone() {
            try {
                if (leadRingtone) { leadRingtone.pause(); leadRingtone.currentTime = 0; }
                clearTimeout(ringtoneTimeout);
                clearInterval(countdownInterval);
                if (timerEl) timerEl.classList.add('hidden');
            } catch(e) {}
        }

        function startRingtone(soundUrl) {
            stopRingtone();
            try {
                leadRingtone = new Audio(soundUrl || fallbackRingtoneUrl);
                leadRingtone.loop = true;
                leadRingtone.volume = 1.0;
                leadRingtone.play().catch(function(e) { console.warn('Ringtone autoplay blocked:', e); });

                var seconds = 30;
                if (countdownEl) countdownEl.textContent = seconds;
                if (timerEl) timerEl.classList.remove('hidden');
                countdownInterval = setInterval(function() {
                    seconds--;
                    if (countdownEl) countdownEl.textContent = seconds;
                    if (seconds <= 0) clearInterval(countdownInterval);
                }, 1000);

                ringtoneTimeout = setTimeout(function() { stopRingtone(); }, 30000);
            } catch(e) { console.warn('Ringtone error:', e); }
        }

        document.addEventListener('click', function() {
            if (!window._audioUnlocked) {
                try {
                    var s = new Audio(fallbackRingtoneUrl);
                    s.volume = 0;
                    s.play().then(function() { s.pause(); window._audioUnlocked = true; }).catch(function(){});
                } catch(e) {}
            }
        }, { once: true });

        window.showLeadAssignedPopup = function(options) {
            var o = options || {};
            var primaryUrl = o.primaryActionUrl || o.primary_action_url || o.callUrl || o.call_url || (o.leadPhone ? 'tel:' + (o.leadPhone + '').replace(/\D/g, '') : '') || o.viewUrl || viewUrlDefault;
            var primaryLabel = o.primaryActionLabel || o.primary_action_label || o.callLabel || (o.leadPhone ? 'Call Lead' : 'Open CRM');
            var primaryMethod = (o.primaryActionMethod || o.primary_action_method || 'GET').toUpperCase();
            var secondaryUrl = o.secondaryActionUrl || o.secondary_action_url || o.completeUrl || o.complete_action_url || '';
            var secondaryLabel = o.secondaryActionLabel || o.secondary_action_label || o.completeLabel || 'Dismiss';
            var secondaryMethod = (o.secondaryActionMethod || o.secondary_action_method || (secondaryUrl ? 'GET' : 'DISMISS')).toUpperCase();
            if (titleEl) titleEl.textContent = o.title || 'New lead assigned';
            if (messageEl) messageEl.textContent = o.message || 'You have a new lead assigned. View leads to see details and call.';
            if (viewBtn) {
                viewBtn.href = primaryUrl || viewUrlDefault || '#';
                viewBtn.textContent = primaryLabel;
                viewBtn.onclick = function(e) {
                    if (primaryMethod !== 'GET' || (primaryUrl || '').indexOf('tel:') !== -1) {
                        e.preventDefault();
                        runPopupAction({ url: primaryUrl, method: primaryMethod, button: viewBtn, loadingLabel: 'Working...' });
                    }
                };
            }
            if (callBtn) {
                if (secondaryUrl && secondaryMethod !== 'DISMISS') {
                    callBtn.href = secondaryUrl;
                    callBtn.textContent = secondaryLabel;
                    callBtn.classList.remove('hidden');
                    callBtn.onclick = function(e) {
                        if (secondaryMethod !== 'GET' || secondaryUrl.indexOf('tel:') !== -1) {
                            e.preventDefault();
                            runPopupAction({ url: secondaryUrl, method: secondaryMethod, button: callBtn, loadingLabel: secondaryMethod === 'POST' ? 'Completing...' : 'Working...' });
                        }
                    };
                } else {
                    callBtn.onclick = null;
                    callBtn.classList.add('hidden');
                }
            }
            if (completeBtn) {
                completeBtn.textContent = o.dismissLabel || o.dismiss_label || 'Dismiss';
                completeBtn.onclick = window.closeLeadAssignedModal;
            }
            if (overlay) overlay.classList.remove('hidden');
            startRingtone(o.soundUrl || o.sound_url || fallbackRingtoneUrl);
        };
        window.closeLeadAssignedModal = function() {
            if (overlay) overlay.classList.add('hidden');
            stopRingtone();
        };
        if (document.getElementById('lead-assigned-close-x')) document.getElementById('lead-assigned-close-x').addEventListener('click', closeLeadAssignedModal);
        if (document.getElementById('lead-assigned-cancel-btn')) document.getElementById('lead-assigned-cancel-btn').addEventListener('click', closeLeadAssignedModal);
        if (overlay) overlay.addEventListener('click', function(e) { if (e.target === overlay) closeLeadAssignedModal(); });
        var uid = document.querySelector('meta[name="user-id"]') && document.querySelector('meta[name="user-id"]').getAttribute('content');
        var pk = document.querySelector('meta[name="pusher-key"]') && document.querySelector('meta[name="pusher-key"]').getAttribute('content');
        if (uid && pk && typeof Pusher !== 'undefined') {
            try {
                var pusher = new Pusher(pk, { cluster: (document.querySelector('meta[name="pusher-cluster"]') && document.querySelector('meta[name="pusher-cluster"]').getAttribute('content')) || 'mt1', encrypted: true, authEndpoint: '/broadcasting/auth' });
                pusher.subscribe('private-user.' + uid).bind('lead.assigned', function(data) {
                    var lead = data.lead || {};
                    showLeadAssignedPopup({ title: 'New lead assigned', message: 'You have 1 new lead assigned: ' + (lead.name || 'Lead') + '. View leads to see details and call.', viewUrl: viewUrlDefault, leadPhone: lead.phone || '', leadName: lead.name || 'Lead', secondaryActionLabel: 'Open Lead', secondaryActionUrl: viewUrlDefault, secondaryActionMethod: 'GET', soundUrl: data.sound_url || '' });
                });
            } catch (e) { console.warn('Pusher lead-assigned:', e); }
        }
    })();
    </script>
    @endauth

    <!-- Include Meeting Post-Call Popup Component -->
    @include('components.meeting-post-call-popup')
    <script>
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) { window.location.reload(); }
    });
    </script>
</body>
</html>

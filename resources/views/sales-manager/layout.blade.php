<!DOCTYPE html>
<html lang="en">
<head>
    @php
        $asmMobileWhatsAppUnread = 0;
        $asmCanRaisePo = false;
        if (auth()->check()) {
            try {
                $scopeService = app(\App\Services\WhatsAppConversationScopeService::class);
                $asmMobileWhatsAppUnread = $scopeService->conversationsFor(auth()->user())
                    ->whereHas('messages', function ($query) {
                        $query->where('direction', 'received')
                            ->where('status', '!=', 'read');
                    })
                    ->count();
            } catch (\Throwable $e) {
                $asmMobileWhatsAppUnread = 0;
            }

            try {
                $asmCanRaisePo = !empty(\App\Models\PurchaseOrderAccess::allowedRequestTypes(auth()->user()));
            } catch (\Throwable $e) {
                $asmCanRaisePo = false;
            }
        }
    @endphp
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Assistant Sales Manager - ' . (brand_name() ?: 'Brickly CRM'))</title>
    
    <!-- Cache Control - Prevent Browser Caching -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        if (auth()->check()) {
            $__existingToken = session('api_token');
            $__tokenValid = false;
            if ($__existingToken) {
                $__storedToken = \Laravel\Sanctum\PersonalAccessToken::findToken($__existingToken);
                $__tokenValid = $__storedToken
                    && $__storedToken->tokenable_type === \App\Models\User::class
                    && (int) $__storedToken->tokenable_id === (int) auth()->id();
            }
            if (!$__tokenValid) {
                $__token = auth()->user()->createToken('web-session-token')->plainTextToken;
                session(['api_token' => $__token]);
            }
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
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Fraunces:opsz,wght@9..144,600;9..144,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { 
            width: 100%; 
            overflow-x: hidden;
            margin-left: 0 !important;
            padding-left: 0 !important;
        }
        body { font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #F7F6F3; }
        
        /* Mobile First - Base Styles */
        .container { 
            max-width: 100%; 
            width: 100%; 
            padding: 12px;
            margin-left: 0;
            margin-right: 0;
        }
        .header {
            background: white;
            padding: 16px;
            border-radius: 12px; 
            margin-bottom: 16px; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); 
            display: flex; 
            flex-direction: column;
            gap: 12px;
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
            color: #002B45;
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
            color: #002B45;
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
        
        /* Mobile: Keep desktop header hierarchy, just compress spacing */
        @media (max-width: 767px) {
            .header {
                padding: 12px;
                margin-bottom: 12px;
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }
            
            .header-top {
                width: 100%;
            }
            
            .header-title-mobile {
                font-size: 16px !important;
                line-height: 1.3;
                width: 100%;
                display: flex;
                align-items: flex-start;
                flex-direction: column;
                gap: 6px;
            }
            
            .header-page-title-desktop {
                display: block !important;
                width: 100%;
                font-size: 20px;
            }
            
            .header-user-info-mobile {
                display: flex;
                width: 100%;
                min-width: 0;
            }
            
            .header-user-name-mobile {
                font-size: 14px;
                font-weight: 600;
                color: #002B45;
            }
            
            .header-user-role-mobile {
                font-size: 11px;
                font-weight: 400;
                color: #6b7280;
            }
            
            .header-actions {
                width: 100%;
            }
            
            .header-actions-row {
                flex-direction: row;
                gap: 8px;
                align-items: center;
                justify-content: space-between;
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
                display: inline-flex !important;
                max-width: calc(100% - 112px);
            }
        }
        .btn { 
            padding: 10px 16px; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            font-size: 14px; 
            font-weight: 500; 
            transition: all 0.3s; 
            white-space: nowrap;
        }
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #dc2626; }
        
        /* Sidebar Styles - Always Icon-Only (64px) - Desktop Only */
        #sidebar {
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            width: 64px !important; /* Always icon-only */
            background: white;
            border-right: 1px solid #e0e0e0;
            box-shadow: 2px 0 8px rgba(0,0,0,0.1);
            z-index: 1000;
            overflow-y: auto;
            transition: transform 0.3s ease-in-out;
            transform: translateX(0);
        }
        
        /* Mobile Footer Navigation - Hidden by default */
        #mobileFooterNav {
            display: none;
        }
        
        /* Sidebar Overlay for Mobile */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .sidebar-overlay.active {
            display: block;
            opacity: 1;
        }
        
        /* Force icon-only - remove expanded state */
        #sidebar.sidebar-expanded {
            width: 64px !important; /* Always icon-only */
        }
        
        /* Remove hidden state - always visible */
        #sidebar.sidebar-hidden {
            width: 64px !important; /* Always icon-only, never hidden */
            transform: translateX(0);
        }
        
        /* Sidebar Link Styles - Always Icon-Only */
        .sidebar-link {
            display: flex;
            align-items: center;
            padding: 12px;
            margin-bottom: 8px;
            border-radius: 8px;
            text-decoration: none;
            color: #666;
            transition: all 0.3s;
            justify-content: center !important; /* Always center icons */
        }
        
        .sidebar-link i {
            font-size: 18px;
            width: 20px;
            text-align: center;
            margin-right: 0 !important; /* No margin, always centered */
        }
        
        /* Always hide text labels */
        .sidebar-link span {
            display: none !important; /* Permanently hidden */
            font-size: 14px;
        }
        
        .sidebar-link:hover {
            background: #F7F6F3 !important;
            color: #006BA6 !important;
        }
        
        .sidebar-link.active {
            background: #F7F6F3 !important;
            color: #006BA6 !important;
            font-weight: 500 !important;
        }
        
        /* Desktop hover labels for icon-only nav */
        @media (min-width: 768px) {
            #sidebar .sidebar-link {
                position: relative;
            }
            
            #sidebar .sidebar-link::after {
                content: attr(data-label);
                position: absolute;
                left: 72px;
                top: 50%;
                transform: translateY(-50%) translateX(-6px);
                background: #1f2937;
                color: #fff;
                padding: 6px 10px;
                border-radius: 6px;
                font-size: 12px;
                font-weight: 600;
                white-space: nowrap;
                opacity: 0;
                pointer-events: none;
                transition: opacity 0.15s ease, transform 0.15s ease;
                z-index: 1001;
                box-shadow: 0 6px 18px rgba(0, 0, 0, 0.2);
            }
            
            #sidebar .sidebar-link:hover::after {
                opacity: 1;
                transform: translateY(-50%) translateX(0);
            }
        }
        
        /* Sidebar Logo Area - Always Hidden in Icon-Only Mode */
        #sidebar > div:first-child {
            padding: 20px 12px;
            text-align: center;
            margin-bottom: 20px;
        }
        
        /* Always hide logo text */
        #sidebar > div:first-child h2 {
            display: none !important; /* Permanently hidden */
        }
        
        #sidebar > div:first-child p {
            display: none !important; /* Permanently hidden */
        }
        
        /* Sidebar Navigation */
        #sidebar nav {
            padding: 0 12px;
        }
        
        /* Sidebar Toggle Button */
        .sidebar-toggle {
            display: none;
            position: fixed;
            top: 22px;
            left: 212px;
            width: 42px;
            height: 42px;
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 14px;
            background: linear-gradient(135deg, #12382c 0%, #0b241c 100%);
            color: #fff;
            box-shadow: 0 14px 28px rgba(0,0,0,0.22);
            z-index: 1101;
            align-items: center;
            justify-content: center;
            transition: left 0.25s ease, transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }
        .sidebar-toggle:hover {
            transform: translateY(-1px);
            box-shadow: 0 18px 34px rgba(0,0,0,0.28);
            background: linear-gradient(135deg, #174736 0%, #0d2c22 100%);
        }
        .sidebar-toggle-icon {
            transition: transform 0.25s ease;
        }
        
        /* Main Content - Mobile First: Full width */
        #mainContent {
            margin-left: 0;
            min-height: 100vh;
            width: auto;
            max-width: none;
            min-width: 0;
            background: #F7F6F3;
        }
        
        /* Desktop: 64px margin for icon-only sidebar */
        @media (min-width: 768px) {
            body #mainContent,
            html body #mainContent,
            div#mainContent {
                margin-left: 64px !important;
                width: auto !important;
            }
        }
        
        /* Clock Widget Responsive */
        #datetimeClock {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 8px 12px;
            font-family: 'Courier New', monospace;
            font-weight: 600;
            font-size: 12px;
            color: #002B45;
            min-width: 140px;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        #clockTime {
            font-size: 14px;
            color: #006BA6;
        }
        
        #clockDate {
            font-size: 10px;
            color: #B3B5B4;
            margin-top: 2px;
        }
        
        /* Tablet Styles */
        @media (min-width: 768px) {
            .container { padding: 20px; }
            .header { 
                flex-direction: row; 
                padding: 20px;
                margin-bottom: 20px;
            }
            .header-actions {
                flex-direction: row;
                width: auto;
                align-items: center;
            }
            .header-actions-row {
                flex-wrap: nowrap;
            }
            .btn { 
                padding: 12px 24px; 
                font-size: 16px;
                width: auto;
            }
            .header form {
                width: auto;
            }
            /* Always icon-only (64px) on desktop/tablet */
            #sidebar {
                width: 64px !important;
                display: block !important;
            }
            #sidebar.sidebar-expanded {
                width: 64px !important;
            }
            body #mainContent,
            html body #mainContent,
            div#mainContent {
                margin-left: 64px !important;
                width: auto !important;
                padding-bottom: 0 !important; /* No footer padding on desktop */
            }
            /* Hide mobile footer on desktop */
            #mobileFooterNav {
                display: none !important;
            }
            .sidebar-toggle {
                display: inline-flex !important;
            }
            #datetimeClock {
                font-size: 14px;
                min-width: 160px;
            }
            #clockTime {
                font-size: 16px;
            }
            #clockDate {
                font-size: 11px;
            }
            .sidebar-overlay {
                display: none !important;
            }
        }
        
        /* Desktop Styles */
        @media (min-width: 1024px) {
            .container { padding: 20px; }
        }
        
        /* Mobile Specific - Hide Sidebar, Show Footer */
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
            
            /* Main content full width on mobile - override inline styles with maximum specificity */
            body #mainContent,
            html body #mainContent,
            div#mainContent {
                margin-left: 0 !important;
                width: 100vw !important;
                max-width: 100vw !important;
                padding-bottom: 62px !important; /* Space for compact footer */
                padding-left: 0 !important;
                padding-right: 0 !important;
                left: 0 !important;
                transform: none !important;
                overflow-x: hidden !important;
            }
            
            /* Remove left padding/margin to eliminate blank sidebar area */
            body .container,
            html body .container,
            div.container,
            #mainContent .container {
                padding-left: 12px !important;
                padding-right: 12px !important;
                margin-left: 0 !important;
                max-width: 100% !important;
                width: 100vw !important;
                left: 0 !important;
                overflow-x: hidden !important;
            }
            
            /* Ensure no left spacing from any source */
            body {
                margin-left: 0 !important;
                padding-left: 0 !important;
                overflow-x: hidden;
            }
            
            html {
                margin-left: 0 !important;
                padding-left: 0 !important;
                overflow-x: hidden;
            }
            
            /* Remove any left margin from header or content sections */
            body.asm-shell.asm-ui-classic .header,
            body.asm-shell.asm-ui-classic .tasks-container,
            body.asm-shell.asm-ui-classic .bg-white,
            body.asm-shell.asm-ui-classic .header-top,
            body.asm-shell.asm-ui-classic .header-actions {
                margin-left: 0 !important;
                padding-left: 12px !important;
            }
            
            /* Ensure all content starts from left edge */
            #mainContent > * {
                margin-left: 0 !important;
            }
            
            /* Remove any transform or positioning that might cause offset */
            #mainContent {
                transform: none !important;
                left: 0 !important;
            }
            
            /* Footer Navigation for Mobile */
            #mobileFooterNav {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(54px, 1fr));
                position: fixed;
                bottom: calc(8px + env(safe-area-inset-bottom, 0px));
                left: 18px;
                right: 18px;
                width: auto;
                background: rgba(255, 255, 255, 0.97);
                border: 1px solid rgba(0, 43, 69, 0.08);
                border-radius: 22px;
                box-shadow: 0 16px 34px rgba(0, 43, 69, 0.14);
                backdrop-filter: blur(16px);
                -webkit-backdrop-filter: blur(16px);
                z-index: 1000;
                padding: 12px 12px 10px;
                align-items: center;
                gap: 2px;
                min-height: 72px;
                max-width: 390px;
                margin: 0 auto;
            }
            
            .footer-nav-link {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                text-decoration: none;
                color: #5f6972;
                padding: 9px 4px 7px;
                border-radius: 14px;
                transition: background 0.22s ease, color 0.22s ease, transform 0.22s ease, box-shadow 0.22s ease;
                min-width: 0;
                width: 100%;
                min-height: 50px;
                border: none;
                background: transparent;
                font: inherit;
                cursor: pointer;
                position: relative;
            }

            .footer-nav-link::before {
                content: "";
                position: absolute;
                top: -10px;
                left: 50%;
                width: 34px;
                height: 3px;
                border-radius: 999px;
                background: #0073b1;
                transform: translateX(-50%) scaleX(0);
                opacity: 0;
                transition: transform 0.22s ease, opacity 0.22s ease;
            }
            
            .footer-nav-link i {
                width: 24px;
                height: 24px;
                border-radius: 0;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-size: 17px;
                margin-bottom: 4px;
                line-height: 1;
                transition: background 0.22s ease, color 0.22s ease, transform 0.22s ease;
            }
            
            .footer-nav-link span {
                font-size: 10.8px;
                color: #5f6972;
                text-align: center;
                line-height: 1;
                white-space: nowrap;
                font-weight: 650;
                letter-spacing: 0.01em;
            }
            .footer-nav-badge {
                position: absolute;
                top: 5px;
                right: 10px;
                min-width: 18px;
                height: 18px;
                padding: 0 5px;
                border-radius: 999px;
                background: #ef4444;
                color: #fff;
                font-size: 10px;
                font-weight: 800;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                line-height: 1;
                box-shadow: 0 6px 14px rgba(239, 68, 68, 0.22);
            }
            
            .footer-nav-link:hover,
            .footer-nav-link.active {
                background: transparent;
                color: #006BA6;
                box-shadow: none;
            }
            
            .footer-nav-link.active {
                color: #006BA6;
                transform: translateY(0);
            }

            .footer-nav-link.active::before {
                opacity: 1;
                transform: translateX(-50%) scaleX(1);
            }

            .footer-nav-link.active i {
                background: transparent;
                color: #0073b1;
                box-shadow: none;
                transform: translateY(-1px);
            }
            
            .footer-nav-link.active span {
                color: #006BA6;
            }

            .footer-nav-link.more-trigger {
                appearance: none;
                -webkit-appearance: none;
            }

            .footer-nav-link {
                position: relative;
            }

            .footer-nav-link.more-trigger .fa-ellipsis-h {
                font-size: 15px;
            }

            .asm-more-menu {
                position: fixed;
                left: 16px;
                right: 16px;
                bottom: calc(78px + env(safe-area-inset-bottom, 0px));
                background: #ffffff;
                border: 1px solid #dce8e1;
                border-radius: 20px;
                box-shadow: 0 18px 42px rgba(12, 41, 30, 0.16);
                padding: 12px;
                z-index: 1105;
                opacity: 0;
                pointer-events: none;
                transform: translateY(12px);
                transition: opacity 0.2s ease, transform 0.2s ease;
            }

            .asm-more-menu.open {
                opacity: 1;
                pointer-events: auto;
                transform: translateY(0);
            }

            .asm-more-menu-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                padding: 4px 4px 10px;
                color: #063A1C;
                font-size: 14px;
                font-weight: 700;
            }

            .asm-more-menu-close {
                border: none;
                background: #f3f7f4;
                color: #0b6b4f;
                width: 30px;
                height: 30px;
                border-radius: 999px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
            }

            .asm-more-menu-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
            }

            .asm-more-link {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 12px 14px;
                border-radius: 14px;
                text-decoration: none;
                background: #f8fbf9;
                border: 1px solid #e2ece6;
                color: #294739;
                font-size: 13px;
                font-weight: 600;
            }

            .asm-more-link i {
                width: 18px;
                text-align: center;
                color: #0b6b4f;
            }

            .asm-more-link.active {
                background: #edf5f0;
                border-color: #c6ddd1;
                color: #063A1C;
            }

            .asm-more-link.active i {
                color: #063A1C;
            }

            .asm-more-overlay {
                position: fixed;
                inset: 0;
                background: rgba(7, 24, 18, 0.24);
                z-index: 1104;
                opacity: 0;
                pointer-events: none;
                transition: opacity 0.2s ease;
            }

            .asm-more-overlay.open {
                opacity: 1;
                pointer-events: auto;
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
        }
        
        /* Prevent sidebar flash on page load - only for desktop */
        @media (min-width: 768px) {
            html.sidebar-mobile-collapsed #sidebar {
                width: 64px !important;
            }
            
            html.sidebar-mobile-collapsed body #mainContent,
            html.sidebar-mobile-collapsed html body #mainContent,
            html.sidebar-mobile-collapsed div#mainContent {
                margin-left: 64px !important;
                width: calc(100% - 64px) !important;
            }
        }
        :root {
            --asm-bg: #F3F8F5;
            --asm-surface: #ffffff;
            --asm-surface-soft: #F7FBF8;
            --asm-border: #D7E4DC;
            --asm-text: #063A1C;
            --asm-muted: #4A6457;
            --asm-brand: #205A44;
            --asm-brand-dark: #063A1C;
            --asm-brand-soft: #ECFDF3;
            --asm-shadow: 0 18px 48px rgba(6, 58, 28, 0.08);
        }
        body.asm-shell {
            background: radial-gradient(circle at top right, #F7FBF8 0%, var(--asm-bg) 48%, #ECFDF3 100%);
            color: var(--asm-text);
        }
        body.asm-shell::before {
            content: '';
            position: fixed;
            inset: 0;
            pointer-events: none;
            background: radial-gradient(circle at top right, rgba(32,90,68,0.10), transparent 32%);
        }
        body.asm-shell .container {
            padding: 24px;
        }
        body.asm-shell .header {
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(18px);
            padding: 18px 22px;
            border-radius: 20px;
            margin-bottom: 22px;
            border: 1px solid rgba(228, 224, 215, 0.9);
            box-shadow: 0 12px 34px rgba(17, 24, 20, 0.07);
        }
        body.asm-shell #sidebar,
        body.asm-shell #sidebar.sidebar-expanded,
        body.asm-shell #sidebar.sidebar-hidden {
            width: 232px !important;
            background: linear-gradient(180deg, #063A1C 0%, #0B4B30 100%);
            border-right: 1px solid rgba(255,255,255,0.06);
            box-shadow: 10px 0 30px rgba(0,0,0,0.14);
            transform: translateX(0);
        }
        body.asm-shell #sidebar > div:first-child {
            padding: 20px 18px 18px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            margin-bottom: 10px;
            text-align: left;
        }
        body.asm-shell #sidebar > div:first-child h2,
        body.asm-shell #sidebar > div:first-child p {
            display: block !important;
        }
        body.asm-shell #sidebar > div:first-child h2 {
            font-family: 'Fraunces', Georgia, serif;
            font-size: 22px;
            color: #fff;
        }
        body.asm-shell #sidebar > div:first-child p {
            margin-top: 5px;
            font-size: 11px;
            color: rgba(255,255,255,0.42);
        }
        body.asm-shell #sidebar nav {
            padding: 10px;
        }
        body.asm-shell .sidebar-link {
            justify-content: flex-start !important;
            gap: 12px;
            padding: 12px 16px;
            margin-bottom: 6px;
            border-radius: 12px;
            color: rgba(255,255,255,0.68);
        }
        body.asm-shell .sidebar-link span {
            display: inline !important;
            font-size: 13px;
            font-weight: 500;
        }
        body.asm-shell .sidebar-link i {
            font-size: 15px;
            width: 18px;
        }
        body.asm-shell .sidebar-link:hover {
            background: rgba(255,255,255,0.08) !important;
            color: #fff !important;
        }
        body.asm-shell .sidebar-link.active {
            background: rgba(32,90,68,0.48) !important;
            color: #fff !important;
            box-shadow: inset 0 0 0 1px rgba(215,228,220,0.24);
        }
        body.asm-shell .sidebar-group {
            margin-bottom: 8px;
        }
        body.asm-shell .sidebar-group-toggle {
            width: 100%;
            border: 0;
            background: transparent;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 11px 14px;
            border-radius: 12px;
            color: rgba(255,255,255,0.70);
            cursor: pointer;
            transition: all .22s ease;
            text-align: left;
        }
        body.asm-shell .sidebar-group-toggle:hover,
        body.asm-shell .sidebar-group.open > .sidebar-group-toggle,
        body.asm-shell .sidebar-group.active > .sidebar-group-toggle {
            background: rgba(255,255,255,0.08);
            color: #fff;
        }
        body.asm-shell .sidebar-group.active > .sidebar-group-toggle {
            box-shadow: inset 0 0 0 1px rgba(183,224,244,0.18);
        }
        body.asm-shell .sidebar-group-main {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }
        body.asm-shell .sidebar-group-main i {
            width: 18px;
            text-align: center;
            font-size: 15px;
        }
        body.asm-shell .sidebar-group-title {
            font-size: 13px;
            font-weight: 700;
            white-space: nowrap;
        }
        body.asm-shell .sidebar-group-caret {
            font-size: 11px;
            opacity: .72;
            transition: transform .2s ease;
        }
        body.asm-shell .sidebar-group.open .sidebar-group-caret {
            transform: rotate(180deg);
        }
        body.asm-shell .sidebar-submenu {
            display: none;
            padding: 6px 0 2px 30px;
        }
        body.asm-shell .sidebar-group.open .sidebar-submenu {
            display: block;
        }
        body.asm-shell .sidebar-sub-link {
            display: flex;
            align-items: center;
            gap: 10px;
            min-height: 34px;
            padding: 8px 12px;
            margin: 2px 0;
            border-radius: 10px;
            color: rgba(255,255,255,0.58);
            text-decoration: none;
            font-size: 12.5px;
            font-weight: 600;
            transition: all .2s ease;
        }
        body.asm-shell .sidebar-sub-link i {
            width: 15px;
            text-align: center;
            font-size: 12px;
            opacity: .82;
        }
        body.asm-shell .sidebar-sub-link:hover {
            background: rgba(255,255,255,0.07);
            color: #fff;
        }
        body.asm-shell .sidebar-sub-link.active {
            background: rgba(32,90,68,0.50);
            color: #fff;
            box-shadow: inset 0 0 0 1px rgba(215,228,220,0.24);
        }
        body.asm-shell #mainContent,
        body.asm-shell div#mainContent,
        body.asm-shell html body #mainContent {
            background: transparent !important;
        }
        body.asm-shell.sidebar-collapsed #sidebar,
        body.asm-shell.sidebar-collapsed #sidebar.sidebar-expanded,
        body.asm-shell.sidebar-collapsed #sidebar.sidebar-hidden {
            width: 78px !important;
        }
        body.asm-shell.sidebar-collapsed #sidebar > div:first-child {
            padding: 18px 10px 14px;
            text-align: center;
        }
        body.asm-shell.sidebar-collapsed #sidebar > div:first-child h2,
        body.asm-shell.sidebar-collapsed #sidebar > div:first-child p {
            display: none !important;
        }
        body.asm-shell.sidebar-collapsed #sidebar nav {
            padding: 10px 8px;
        }
        body.asm-shell.sidebar-collapsed .sidebar-link {
            justify-content: center !important;
            padding: 12px 10px;
            gap: 0;
        }
        body.asm-shell.sidebar-collapsed .sidebar-link span {
            display: none !important;
        }
        body.asm-shell.sidebar-collapsed .sidebar-link i {
            margin-right: 0 !important;
            width: 18px;
        }
        body.asm-shell.sidebar-collapsed .sidebar-group {
            position: relative;
        }
        body.asm-shell.sidebar-collapsed .sidebar-group-toggle {
            justify-content: center;
            padding: 12px 10px;
        }
        body.asm-shell.sidebar-collapsed .sidebar-group-title,
        body.asm-shell.sidebar-collapsed .sidebar-group-caret,
        body.asm-shell.sidebar-collapsed .sidebar-submenu {
            display: none !important;
        }
        body.asm-shell.sidebar-collapsed .sidebar-group-main {
            gap: 0;
        }
        body.asm-shell.sidebar-collapsed .sidebar-group-main i {
            width: 18px;
        }
        @media (min-width: 768px) {
            body.asm-shell.sidebar-collapsed .sidebar-group-toggle::after {
                content: attr(data-label);
                position: absolute;
                left: 72px;
                top: 50%;
                transform: translateY(-50%) translateX(-6px);
                background: #1f2937;
                color: #fff;
                padding: 6px 10px;
                border-radius: 6px;
                font-size: 12px;
                font-weight: 600;
                white-space: nowrap;
                opacity: 0;
                pointer-events: none;
                transition: opacity 0.15s ease, transform 0.15s ease;
                z-index: 1001;
                box-shadow: 0 6px 18px rgba(0, 0, 0, 0.2);
            }
            body.asm-shell.sidebar-collapsed .sidebar-group-toggle:hover::after {
                opacity: 1;
                transform: translateY(-50%) translateX(0);
            }
        }
        @media (min-width: 768px) {
            body.asm-shell #mainContent,
            body.asm-shell div#mainContent,
            body.asm-shell html body #mainContent {
                margin-left: 232px !important;
                width: calc(100% - 232px) !important;
            }
            body.asm-shell.sidebar-collapsed #mainContent,
            body.asm-shell.sidebar-collapsed div#mainContent,
            body.asm-shell.sidebar-collapsed html body #mainContent {
                margin-left: 78px !important;
                width: calc(100% - 78px) !important;
            }
        }
        body.asm-shell.sidebar-collapsed .sidebar-toggle {
            left: 58px;
        }
        body.asm-shell.sidebar-collapsed .sidebar-toggle-icon {
            transform: rotate(180deg);
        }
        body.asm-shell #datetimeClock {
            background: linear-gradient(135deg, #063A1C 0%, #205A44 100%);
            color: #fff;
            border: none;
            border-radius: 14px;
            padding: 10px 14px;
            min-width: 132px;
            box-shadow: 0 10px 24px rgba(13,107,79,0.22);
            font-family: 'Outfit', sans-serif;
        }
        body.asm-shell #clockTime,
        body.asm-shell #clockDate {
            color: #fff;
        }
        .asm-brand-mark {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            background: linear-gradient(135deg, #063A1C 0%, #7DB59B 100%);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            font-weight: 800;
            font-size: 14px;
            margin-right: 10px;
            vertical-align: middle;
        }
        .asm-header-title {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 4px;
        }
        .asm-header-title .eyebrow {
            font-size: 11px;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--asm-muted);
            font-weight: 600;
        }
        .asm-header-title .title {
            font-family: 'Fraunces', Georgia, serif;
            font-size: 28px;
            font-weight: 700;
            color: var(--asm-text);
            line-height: 1.1;
        }
        .asm-user-chip {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            border-radius: 999px;
            background: var(--asm-surface-soft);
            border: 1px solid var(--asm-border);
            color: var(--asm-text);
            font-size: 13px;
            font-weight: 500;
        }
        .asm-mobile-nav-quick {
            display: none;
        }
        .asm-user-chip .avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #063A1C 0%, #205A44 100%);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }
        @media (max-width: 767px) {
            body.asm-shell .container {
                padding: 12px;
            }
            body.asm-shell .header {
                padding: 14px;
                border-radius: 16px;
            }
            .asm-header-title .title {
                font-size: 18px;
                font-family: 'Outfit', sans-serif;
            }
            .asm-user-chip {
                display: none;
            }
            body.asm-shell.asm-ui-modern #sidebar {
                display: block !important;
                position: fixed !important;
                left: 0 !important;
                top: 0 !important;
                bottom: 0 !important;
                width: 232px !important;
                height: 100vh !important;
                visibility: visible !important;
                transform: translateX(-100%);
                transition: transform 0.25s ease-in-out;
                z-index: 1102;
            }
            body.asm-shell.asm-ui-modern #sidebar.mobile-open {
                transform: translateX(0);
            }
            body.asm-shell.asm-ui-modern #mobileFooterNav {
                display: grid !important;
            }
            body.asm-shell.asm-ui-modern #mainContent,
            body.asm-shell.asm-ui-modern div#mainContent,
            body.asm-shell.asm-ui-modern html body #mainContent {
                margin-left: 0 !important;
                width: 100vw !important;
                max-width: 100vw !important;
                padding-bottom: 96px !important;
                overflow-x: hidden !important;
            }
            body.asm-shell.asm-ui-modern .sidebar-overlay {
                display: none;
            }
            body.asm-shell.asm-ui-modern .sidebar-overlay.active {
                display: block !important;
                opacity: 1;
                z-index: 1101;
            }
            body.asm-shell.asm-ui-modern.mobile-drawer-open {
                overflow: hidden;
            }
            body.asm-shell.asm-ui-classic #sidebar {
                display: none !important;
            }
            body.asm-shell.asm-ui-classic #mobileFooterNav {
                display: grid !important;
            }
            body.asm-shell.asm-ui-classic #mainContent,
            body.asm-shell.asm-ui-classic div#mainContent,
            body.asm-shell.asm-ui-classic html body #mainContent {
                width: 100vw !important;
                max-width: 100vw !important;
                padding-bottom: 62px !important;
                overflow-x: hidden !important;
            }
            body.asm-shell.asm-ui-modern .container,
            body.asm-shell.asm-ui-classic .container {
                width: 100vw !important;
                max-width: 100vw !important;
                padding-left: 10px !important;
                padding-right: 10px !important;
                overflow-x: hidden !important;
            }
            body.asm-shell.app-webview-mode .container {
                padding-top: 12px !important;
            }
            body.asm-shell.app-webview-mode.chat-page-active .container {
                padding-top: 0 !important;
            }
            #mobileFooterNav {
                display: grid !important;
                grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
                position: fixed !important;
                left: 0 !important;
                right: 0 !important;
                bottom: 0 !important;
                width: 100% !important;
                height: calc(56px + env(safe-area-inset-bottom, 0px)) !important;
                padding: 3px 0 calc(3px + env(safe-area-inset-bottom, 0px)) !important;
                border-radius: 0 !important;
                border: 0 !important;
                border-top: 1px solid #dce8e1 !important;
                background: rgba(255, 255, 255, 0.98) !important;
                box-shadow: 0 -8px 24px rgba(6, 58, 28, 0.12) !important;
                backdrop-filter: blur(14px) !important;
                z-index: 1200 !important;
            }
            .footer-nav-link,
            .footer-nav-link.more-trigger {
                min-width: 0 !important;
                max-width: none !important;
                width: 100% !important;
                height: 46px !important;
                padding: 4px 2px 3px !important;
                border-radius: 0 !important;
                color: #50625a !important;
                background: transparent !important;
                box-shadow: none !important;
                transform: none !important;
                gap: 1px !important;
            }
            .footer-nav-link::before {
                top: auto !important;
                bottom: -3px !important;
                width: 24px !important;
                height: 2px !important;
                background: #0b6b4f !important;
            }
            .footer-nav-link i {
                width: 20px !important;
                height: 20px !important;
                font-size: 16px !important;
                margin-bottom: 0 !important;
            }
            .footer-nav-link span {
                font-size: 9px !important;
                line-height: 1 !important;
                font-weight: 650 !important;
            }
            .footer-nav-link.active,
            .footer-nav-link:hover {
                color: #0b6b4f !important;
                background: transparent !important;
            }
            .footer-nav-link.active i,
            .footer-nav-link.active span {
                color: #0b6b4f !important;
            }
            .asm-more-menu {
                left: 10px !important;
                right: 10px !important;
                bottom: calc(72px + env(safe-area-inset-bottom, 0px)) !important;
            }

            body.asm-shell #mobileFooterNav {
                position: fixed !important;
                left: 0 !important;
                right: 0 !important;
                bottom: 0 !important;
                width: 100vw !important;
                max-width: 100vw !important;
                margin: 0 !important;
                height: calc(54px + env(safe-area-inset-bottom, 0px)) !important;
                min-height: calc(54px + env(safe-area-inset-bottom, 0px)) !important;
                padding: 2px 0 calc(2px + env(safe-area-inset-bottom, 0px)) !important;
                border-radius: 0 !important;
                box-shadow: 0 -6px 18px rgba(6, 58, 28, 0.10) !important;
                transform: translate3d(0, 0, 0) !important;
            }

            body.asm-shell #mainContent,
            body.asm-shell div#mainContent {
                padding-bottom: calc(60px + env(safe-area-inset-bottom, 0px)) !important;
            }

            body.asm-shell .footer-nav-link,
            body.asm-shell .footer-nav-link.more-trigger {
                height: 46px !important;
                min-height: 46px !important;
                padding: 3px 2px 2px !important;
            }

            body.asm-shell .asm-more-menu {
                bottom: calc(60px + env(safe-area-inset-bottom, 0px)) !important;
            }
            .asm-mobile-menu-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 36px;
                height: 36px;
                border-radius: 10px;
                border: 1px solid #d4ddd7;
                background: #ffffff;
                color: #0f2d22;
                flex-shrink: 0;
                margin-bottom: 4px;
            }
            .asm-mobile-menu-btn i {
                font-size: 14px;
            }
            .asm-mobile-nav-quick {
                display: block;
                margin-bottom: 10px;
            }
        }
    </style>
    @stack('styles')
</head>
@php
    $authUser = auth()->user();
    $isAppWebView = str_contains((string) request()->userAgent(), 'BaseCRMAndroidApp');
    $isSeniorManagerLeadDetail = request()->routeIs('leads.show') && $authUser?->isSeniorManager();
    $managerLeadDetailActive = request()->routeIs('sales-manager.leads')
        || (request()->routeIs('leads.show') && $authUser && (
            $authUser->isSalesManager()
            || $authUser->isAssistantSalesManager()
            || $authUser->isSeniorManager()
        ));
    $salesLeadBoardUser = $authUser
        && ($authUser->isAssistantSalesManager() || $authUser->isSeniorManager() || $authUser->isSalesManager());
    $dashboardGroupActive = request()->routeIs('sales-manager.dashboard');
    $salesGroupActive = request()->routeIs('sales-manager.overview*')
        || $managerLeadDetailActive
        || request()->routeIs('sales-manager.prospects')
        || (request()->routeIs('sales-manager.tasks*') && request('focus') === 'followups')
        || request()->routeIs('sales-manager.meetings*')
        || request()->routeIs('sales-manager.site-visits*')
        || request()->routeIs('sales-manager.closed');
    $asmHideExecutionAndWhatsApp = $authUser?->isAssistantSalesManager();
    $asmHideProspectsNav = $authUser?->isAssistantSalesManager();
    $showVerificationNav = $authUser
        ? app(\App\Services\VerificationRoutingService::class)->userHasVerifierAccess($authUser)
        : false;
    $dailyWorkGroupActive = (request()->routeIs('sales-manager.tasks*') && request('focus') !== 'followups')
        || ($showVerificationNav && request()->routeIs('sales-manager.verifications*'))
        || (!$asmHideExecutionAndWhatsApp && request()->routeIs('execution-desk.*'))
        || (!$asmHideExecutionAndWhatsApp && request()->routeIs('chat.*'))
        || request()->routeIs('calling-center.*');
    $leadToolsGroupActive = request()->routeIs('lead-bank.requests.*')
        || request()->routeIs('sales-manager.lead-downloads.*');
    $showAttendanceNav = $authUser?->hasAttendanceRolloutEnabled() ?? false;
    $attendanceGroupActive = $showAttendanceNav && (
        request()->routeIs('sales-manager.attendance')
        || request()->routeIs('attendance.*')
    );
    $requestsGroupActive = request()->routeIs('purchase-orders.*');
    $profileGroupActive = request()->routeIs('sales-manager.profile')
        || request()->routeIs('advisor.profile.*')
        || request()->routeIs('sales-manager.settings');
@endphp
<body class="asm-shell {{ $isAppWebView ? 'app-webview-mode' : '' }} {{ $isSeniorManagerLeadDetail ? 'senior-manager-lead-detail' : '' }}">
    <script>
        // Mobile detection and initial layout - run immediately to override inline styles
        (function() {
            function resolveAsmMobileUiMode() {
                const pathname = window.location.pathname || '';
                const forceClassicTasksUi = pathname.includes('/sales-manager/tasks') || pathname === '/tasks';
                if (forceClassicTasksUi) {
                    try { localStorage.setItem('asm_mobile_ui_mode', 'classic'); } catch (e) {}
                    return 'classic';
                }
                const param = new URLSearchParams(window.location.search).get('asm_mobile_ui');
                if (param === 'classic' || param === 'modern') {
                    try { localStorage.setItem('asm_mobile_ui_mode', param); } catch (e) {}
                    return param;
                }
                try {
                    const saved = localStorage.getItem('asm_mobile_ui_mode');
                    if (saved === 'classic' || saved === 'modern') return saved;
                } catch (e) {}
                return 'modern';
            }

            function applyAsmUiModeClass(mode) {
                const body = document.body;
                if (!body) return;
                body.classList.remove('asm-ui-classic', 'asm-ui-modern');
                body.classList.add(mode === 'classic' ? 'asm-ui-classic' : 'asm-ui-modern');
            }

            function isDesktopSidebarCollapsed() {
                try { return localStorage.getItem('asm_sidebar_collapsed') === '1'; } catch (e) { return false; }
            }

            function fixMobileLayout() {
                const isMobile = window.innerWidth < 768;
                const sidebar = document.getElementById('sidebar');
                const mainContent = document.getElementById('mainContent');
                const sidebarToggle = document.getElementById('sidebarToggle');
                const mobileMenuToggle = document.getElementById('asmMobileMenuToggle');
                const sidebarOverlay = document.getElementById('sidebarOverlay');
                const collapsed = !isMobile && isDesktopSidebarCollapsed();
                const uiMode = resolveAsmMobileUiMode();
                applyAsmUiModeClass(uiMode);
                
                if (isMobile) {
                    // Mobile: switch behavior by UI mode
                    document.body.classList.remove('sidebar-collapsed');
                    if (sidebarToggle) {
                        sidebarToggle.style.display = 'none';
                    }
                    if (mobileMenuToggle) {
                        mobileMenuToggle.style.display = uiMode === 'modern' ? 'inline-flex' : 'none';
                    }
                    if (uiMode === 'classic') {
                        if (sidebar) {
                            sidebar.classList.remove('mobile-open');
                            sidebar.style.display = 'none';
                            sidebar.style.width = '0';
                            sidebar.style.visibility = 'hidden';
                        }
                        if (sidebarOverlay) sidebarOverlay.classList.remove('active');
                        document.body.classList.remove('mobile-drawer-open');
                    } else {
                        if (sidebar) {
                            sidebar.style.display = 'block';
                            sidebar.style.width = '232px';
                            sidebar.style.visibility = 'visible';
                        }
                    }
                    if (mainContent) {
                        mainContent.style.setProperty('margin-left', '0', 'important');
                        mainContent.style.setProperty('width', 'auto', 'important');
                        mainContent.style.setProperty('padding-left', '0', 'important');
                        mainContent.style.setProperty('padding-right', '0', 'important');
                        mainContent.style.setProperty('left', '0', 'important');
                    }
                } else {
                    document.body.classList.toggle('sidebar-collapsed', collapsed);
                    // Desktop: premium collapsible sidebar
                    if (sidebar) {
                        sidebar.classList.remove('sidebar-expanded', 'sidebar-hidden');
                        sidebar.style.width = collapsed ? '78px' : '232px';
                        sidebar.style.display = 'block';
                    }
                    if (sidebarToggle) {
                        sidebarToggle.style.display = 'inline-flex';
                    }
                    if (mobileMenuToggle) {
                        mobileMenuToggle.style.display = 'none';
                    }
                    if (sidebarOverlay) sidebarOverlay.classList.remove('active');
                    document.body.classList.remove('mobile-drawer-open');
                    if (sidebar) sidebar.classList.remove('mobile-open');
                    if (mainContent) {
                        mainContent.style.setProperty('margin-left', collapsed ? '78px' : '232px', 'important');
                        mainContent.style.setProperty('width', 'auto', 'important');
                    }
                }
            }
            
            // Run immediately
            fixMobileLayout();
            
            // Also run when DOM is ready (in case elements load later)
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', fixMobileLayout);
            }
            
            // Run on window load as well
            window.addEventListener('load', fixMobileLayout);
            window.__asmFixMobileLayout = fixMobileLayout;
        })();
    </script>
    
    <!-- Sidebar Overlay (Mobile Only) -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <!-- Sidebar Toggle Button -->
    <button id="sidebarToggle" class="sidebar-toggle" title="Toggle Navigation" aria-label="Toggle Navigation" style="display: none;">
        <i class="fas fa-chevron-left sidebar-toggle-icon" id="sidebarToggleIcon"></i>
    </button>
    
    <!-- Sidebar -->
    <aside id="sidebar">
        <div>
            <h2><span class="asm-brand-mark">{{ strtoupper(substr(brand_name() ?: 'Brickly', 0, 1)) }}</span>{{ brand_name() ?: 'Brickly CRM' }}</h2>
            <p>{{ auth()->user()->getDisplayRoleName() ?? 'Assistant Sales Manager' }}</p>
        </div>
        <nav>
            <div class="sidebar-group {{ $dashboardGroupActive ? 'active open' : 'open' }}" data-sidebar-group="dashboard">
                <button type="button" class="sidebar-group-toggle" data-label="Dashboard" aria-expanded="{{ $dashboardGroupActive ? 'true' : 'false' }}">
                    <span class="sidebar-group-main"><i class="fas fa-home"></i><span class="sidebar-group-title">Dashboard</span></span>
                    <i class="fas fa-chevron-down sidebar-group-caret"></i>
                </button>
                <div class="sidebar-submenu">
                    <a href="{{ route('sales-manager.dashboard') }}" class="sidebar-sub-link {{ request()->routeIs('sales-manager.dashboard') ? 'active' : '' }}">
                        <i class="fas fa-gauge-high"></i><span>Dashboard</span>
                    </a>
                </div>
            </div>

            <div class="sidebar-group {{ $salesGroupActive ? 'active open' : '' }}" data-sidebar-group="sales">
                <button type="button" class="sidebar-group-toggle" data-label="Sales" aria-expanded="{{ $salesGroupActive ? 'true' : 'false' }}">
                    <span class="sidebar-group-main"><i class="fas fa-chart-line"></i><span class="sidebar-group-title">Sales</span></span>
                    <i class="fas fa-chevron-down sidebar-group-caret"></i>
                </button>
                <div class="sidebar-submenu">
                    @if($salesLeadBoardUser)
                        <a href="{{ route('sales-manager.overview') }}" class="sidebar-sub-link {{ request()->routeIs('sales-manager.overview*') ? 'active' : '' }}">
                            <i class="fas fa-table-columns"></i><span>Lead Board</span>
                        </a>
                    @endif
                    <a href="{{ route('sales-manager.leads') }}" class="sidebar-sub-link {{ $managerLeadDetailActive ? 'active' : '' }}">
                        <i class="fas fa-user-friends"></i><span>Leads</span>
                    </a>
                    @unless($asmHideProspectsNav)
                        <a href="{{ route('sales-manager.prospects') }}" class="sidebar-sub-link {{ request()->routeIs('sales-manager.prospects') ? 'active' : '' }}">
                            <i class="fas fa-star"></i><span>Prospects</span>
                        </a>
                    @endunless
                    <a href="{{ route('sales-manager.tasks', ['focus' => 'followups']) }}" class="sidebar-sub-link {{ request()->routeIs('sales-manager.tasks*') && request('focus') === 'followups' ? 'active' : '' }}">
                        <i class="fas fa-phone-volume"></i><span>Followups</span>
                    </a>
                    <a href="{{ route('sales-manager.meetings') }}" class="sidebar-sub-link {{ request()->routeIs('sales-manager.meetings*') ? 'active' : '' }}">
                        <i class="fas fa-handshake"></i><span>Meetings</span>
                    </a>
                    <a href="{{ route('sales-manager.site-visits') }}" class="sidebar-sub-link {{ request()->routeIs('sales-manager.site-visits*') ? 'active' : '' }}">
                        <i class="fas fa-map-marker-alt"></i><span>Site Visits</span>
                    </a>
                    <a href="{{ route('sales-manager.closed') }}" class="sidebar-sub-link {{ request()->routeIs('sales-manager.closed') ? 'active' : '' }}">
                        <i class="fas fa-circle-check"></i><span>Closed</span>
                    </a>
                </div>
            </div>

            <div class="sidebar-group {{ $dailyWorkGroupActive ? 'active open' : '' }}" data-sidebar-group="daily-work">
                <button type="button" class="sidebar-group-toggle" data-label="Daily Work" aria-expanded="{{ $dailyWorkGroupActive ? 'true' : 'false' }}">
                    <span class="sidebar-group-main"><i class="fas fa-briefcase"></i><span class="sidebar-group-title">Daily Work</span></span>
                    <i class="fas fa-chevron-down sidebar-group-caret"></i>
                </button>
                <div class="sidebar-submenu">
                    <a href="{{ route('sales-manager.tasks') }}" class="sidebar-sub-link {{ request()->routeIs('sales-manager.tasks*') && request('focus') !== 'followups' ? 'active' : '' }}">
                        <i class="fas fa-tasks"></i><span>Tasks</span>
                    </a>
                    @if($showVerificationNav)
                    <a href="{{ route('sales-manager.verifications') }}" class="sidebar-sub-link {{ request()->routeIs('sales-manager.verifications*') ? 'active' : '' }}">
                        <i class="fas fa-user-check"></i><span>Verifications</span>
                    </a>
                    @endif
                    @unless($asmHideExecutionAndWhatsApp)
                    <a href="{{ route('execution-desk.index') }}" class="sidebar-sub-link {{ request()->routeIs('execution-desk.*') ? 'active' : '' }}">
                        <i class="fas fa-list-check"></i><span>Execution Desk</span>
                    </a>
                    @endunless
                    @if($authUser?->canUseCallingCenter('calling_center.view'))
                    <a href="{{ $authUser->canUseCallingCenter('calling_center.create_campaign') ? route('calling-center.index') : route('calling-center.queue') }}" class="sidebar-sub-link {{ request()->routeIs('calling-center.*') ? 'active' : '' }}">
                        <i class="fas fa-headset"></i><span>Calling Center</span>
                    </a>
                    @endif
                    @unless($asmHideExecutionAndWhatsApp)
                    <a href="{{ route('chat.index') }}" class="sidebar-sub-link {{ request()->routeIs('chat.*') ? 'active' : '' }}">
                        <i class="fab fa-whatsapp"></i><span>WhatsApp Chat</span>
                    </a>
                    @endunless
                </div>
            </div>

            <div class="sidebar-group {{ $leadToolsGroupActive ? 'active open' : '' }}" data-sidebar-group="lead-tools">
                <button type="button" class="sidebar-group-toggle" data-label="Lead Tools" aria-expanded="{{ $leadToolsGroupActive ? 'true' : 'false' }}">
                    <span class="sidebar-group-main"><i class="fas fa-toolbox"></i><span class="sidebar-group-title">Lead Tools</span></span>
                    <i class="fas fa-chevron-down sidebar-group-caret"></i>
                </button>
                <div class="sidebar-submenu">
                    <a href="{{ route('lead-bank.requests.index') }}" class="sidebar-sub-link {{ request()->routeIs('lead-bank.requests.*') ? 'active' : '' }}">
                        <i class="fas fa-inbox"></i><span>Lead Requests</span>
                    </a>
                    <a href="{{ route('sales-manager.lead-downloads.index') }}" class="sidebar-sub-link {{ request()->routeIs('sales-manager.lead-downloads.*') ? 'active' : '' }}">
                        <i class="fas fa-file-arrow-down"></i><span>Download Leads</span>
                    </a>
                </div>
            </div>

            @if($showAttendanceNav)
            <div class="sidebar-group {{ $attendanceGroupActive ? 'active open' : '' }}" data-sidebar-group="attendance">
                <button type="button" class="sidebar-group-toggle" data-label="Attendance" aria-expanded="{{ $attendanceGroupActive ? 'true' : 'false' }}">
                    <span class="sidebar-group-main"><i class="fas fa-user-check"></i><span class="sidebar-group-title">Attendance</span></span>
                    <i class="fas fa-chevron-down sidebar-group-caret"></i>
                </button>
                <div class="sidebar-submenu">
                    <a href="{{ route('sales-manager.attendance') }}" class="sidebar-sub-link {{ request()->routeIs('sales-manager.attendance') ? 'active' : '' }}">
                        <i class="fas fa-calendar-check"></i><span>Attendance</span>
                    </a>
                    <a href="{{ route('attendance.leaves') }}" class="sidebar-sub-link {{ request()->routeIs('attendance.leaves') ? 'active' : '' }}">
                        <i class="fas fa-leaf"></i><span>Leaves</span>
                    </a>
                    <a href="{{ route('attendance.regularizations') }}" class="sidebar-sub-link {{ request()->routeIs('attendance.regularizations') ? 'active' : '' }}">
                        <i class="fas fa-rotate"></i><span>Regularization</span>
                    </a>
                    <a href="{{ route('attendance.payslips') }}" class="sidebar-sub-link {{ request()->routeIs('attendance.payslips*') ? 'active' : '' }}">
                        <i class="fas fa-file-invoice"></i><span>Payslips</span>
                    </a>
                </div>
            </div>
            @endif

            @if($asmCanRaisePo)
                <div class="sidebar-group {{ $requestsGroupActive ? 'active open' : '' }}" data-sidebar-group="requests">
                    <button type="button" class="sidebar-group-toggle" data-label="Requests" aria-expanded="{{ $requestsGroupActive ? 'true' : 'false' }}">
                        <span class="sidebar-group-main"><i class="fas fa-file-signature"></i><span class="sidebar-group-title">Requests</span></span>
                        <i class="fas fa-chevron-down sidebar-group-caret"></i>
                    </button>
                    <div class="sidebar-submenu">
                        <a href="{{ route('purchase-orders.index') }}" class="sidebar-sub-link {{ request()->routeIs('purchase-orders.*') ? 'active' : '' }}">
                            <i class="fas fa-receipt"></i><span>Purchase Orders</span>
                        </a>
                    </div>
                </div>
            @endif

            <div class="sidebar-group {{ $profileGroupActive ? 'active open' : '' }}" data-sidebar-group="profile-settings">
                <button type="button" class="sidebar-group-toggle" data-label="Profile & Settings" aria-expanded="{{ $profileGroupActive ? 'true' : 'false' }}">
                    <span class="sidebar-group-main"><i class="fas fa-user-gear"></i><span class="sidebar-group-title">Profile & Settings</span></span>
                    <i class="fas fa-chevron-down sidebar-group-caret"></i>
                </button>
                <div class="sidebar-submenu">
                    <a href="{{ route('sales-manager.profile') }}" class="sidebar-sub-link {{ request()->routeIs('sales-manager.profile') ? 'active' : '' }}">
                        <i class="fas fa-user"></i><span>Profile</span>
                    </a>
                    @if(auth()->user()->canUseAdvisorPublicProfile())
                        <a href="{{ route('advisor.profile.show') }}" class="sidebar-sub-link {{ request()->routeIs('advisor.profile.*') ? 'active' : '' }}">
                            <i class="fas fa-id-card"></i><span>My Public Profile</span>
                        </a>
                    @endif
                    @if(auth()->user()->isAssistantSalesManager() || auth()->user()->isSeniorManager())
                        <a href="{{ route('sales-manager.settings') }}" class="sidebar-sub-link {{ request()->routeIs('sales-manager.settings') ? 'active' : '' }}">
                            <i class="fas fa-sliders-h"></i><span>Settings</span>
                        </a>
                    @endif
                </div>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <div id="mainContent" class="main-content-wrapper" style="min-height: 100vh; background: #F7F6F3;">
        <div class="container">
            @if(request()->routeIs('sales-manager.dashboard'))
                <!-- Header (dashboard only) -->
                <div class="header">
                    <div class="header-top">
                        <h1 class="header-title-mobile asm-header-title" style="font-size: 24px; font-weight: 700; color: #002B45;">
                            <span class="header-page-title-desktop title">@yield('page-title', 'Dashboard')</span>
                            <div class="header-user-info-mobile">
                                <span class="header-user-name-mobile">{{ auth()->user()->name }}</span>
                                <span class="header-user-role-mobile">{{ auth()->user()->getDisplayRoleName() ?? 'User' }}</span>
                            </div>
                        </h1>
                    </div>
                    <div class="header-actions">
                        <div class="header-actions-row">
                            @include('components.global-notification-center')
                            <span class="asm-user-chip header-user-name-desktop"><span class="avatar">{{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}</span>{{ auth()->user()->name }}</span>
                        </div>
                    </div>
                </div>
            @else
                <div class="asm-mobile-nav-quick"></div>
            @endif

            <!-- Content -->
            @yield('content')
        </div>
    </div>

    <!-- Mobile Footer Navigation -->
    <nav id="mobileFooterNav">
        <a href="{{ route('sales-manager.dashboard') }}" class="footer-nav-link {{ request()->routeIs('sales-manager.dashboard') ? 'active' : '' }}" data-nav-order="0" data-nav-key="dashboard">
            <i class="fas fa-home"></i>
            <span>Dash</span>
        </a>
        <a href="{{ route('sales-manager.tasks') }}" class="footer-nav-link {{ request()->routeIs('sales-manager.tasks*') && request('focus') !== 'followups' ? 'active' : '' }}" data-nav-order="1" data-nav-key="tasks">
            <i class="fas fa-tasks"></i>
            <span>Tasks</span>
        </a>
        <a href="{{ route('sales-manager.leads') }}" class="footer-nav-link {{ $managerLeadDetailActive ? 'active' : '' }}" data-nav-order="2" data-nav-key="leads">
            <i class="fas fa-user-friends"></i>
            <span>Leads</span>
        </a>
        @unless(auth()->user()->isAssistantSalesManager())
        <a href="{{ route('sales-manager.prospects') }}" class="footer-nav-link {{ request()->routeIs('sales-manager.prospects') ? 'active' : '' }}" data-nav-order="3" data-nav-key="prospects">
            <i class="fas fa-star"></i>
            <span>Prospect</span>
        </a>
        <a href="{{ route('chat.index') }}" class="footer-nav-link {{ request()->routeIs('chat.*') ? 'active' : '' }}" data-nav-order="4" data-nav-key="chat">
            <i class="fab fa-whatsapp"></i>
            <span>WhatsApp</span>
            @if($asmMobileWhatsAppUnread > 0)
                <span class="footer-nav-badge">{{ $asmMobileWhatsAppUnread > 99 ? '99+' : $asmMobileWhatsAppUnread }}</span>
            @endif
        </a>
        @endunless
        <button type="button" id="asmMoreMenuTrigger" class="footer-nav-link more-trigger {{ request()->routeIs('sales-manager.meetings*') || request()->routeIs('sales-manager.site-visits*') || request()->routeIs('sales-manager.closed') || $attendanceGroupActive || request()->routeIs('purchase-orders.*') || request()->routeIs('lead-bank.requests.*') || (!auth()->user()->isAssistantSalesManager() && request()->routeIs('sales-manager.tasks*') && request('focus') === 'followups') || request()->routeIs('sales-manager.profile') || request()->routeIs('sales-manager.settings') ? 'active' : '' }}" data-nav-order="5" data-nav-key="more" aria-expanded="false" aria-controls="asmMoreMenu">
            <i class="fas fa-ellipsis-h"></i>
            <span>More</span>
        </button>
    </nav>

    <div id="asmMoreOverlay" class="asm-more-overlay" hidden></div>
    <div id="asmMoreMenu" class="asm-more-menu" aria-hidden="true" hidden>
        <div class="asm-more-menu-header">
            <span>More</span>
            <button type="button" id="asmMoreMenuClose" class="asm-more-menu-close" aria-label="Close more menu">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="asm-more-menu-grid">
            @if($showAttendanceNav)
            <a href="{{ route('sales-manager.attendance') }}" class="asm-more-link {{ request()->routeIs('sales-manager.attendance') || request()->routeIs('attendance.*') ? 'active' : '' }}" data-nav-order="3.5" data-nav-key="attendance">
                <i class="fas fa-user-check"></i>
                <span>Attendance</span>
            </a>
            @endif
            <a href="{{ route('sales-manager.meetings') }}" class="asm-more-link {{ request()->routeIs('sales-manager.meetings*') ? 'active' : '' }}" data-nav-order="4" data-nav-key="meetings">
                <i class="fas fa-handshake"></i>
                <span>Meetings</span>
            </a>
            <a href="{{ route('sales-manager.site-visits') }}" class="asm-more-link {{ request()->routeIs('sales-manager.site-visits*') ? 'active' : '' }}" data-nav-order="5" data-nav-key="visits">
                <i class="fas fa-map-marker-alt"></i>
                <span>Visits</span>
            </a>
            @unless(auth()->user()->isAssistantSalesManager())
            <a href="{{ route('sales-manager.tasks', ['focus' => 'followups']) }}" class="asm-more-link {{ request()->routeIs('sales-manager.tasks*') && request('focus') === 'followups' ? 'active' : '' }}" data-nav-order="6" data-nav-key="followups">
                <i class="fas fa-phone-volume"></i>
                <span>Follow Up</span>
            </a>
            <a href="{{ route('execution-desk.index') }}" class="asm-more-link {{ request()->routeIs('execution-desk.*') ? 'active' : '' }}" data-nav-order="6.5" data-nav-key="execution-desk">
                <i class="fas fa-list-check"></i>
                <span>Execution Desk</span>
            </a>
            @endunless
            @if($asmCanRaisePo)
            <a href="{{ route('purchase-orders.index') }}" class="asm-more-link {{ request()->routeIs('purchase-orders.*') ? 'active' : '' }}" data-nav-order="6.7" data-nav-key="purchase-orders">
                <i class="fas fa-file-signature"></i>
                <span>PO</span>
            </a>
            @endif
            <a href="{{ route('sales-manager.closed') }}" class="asm-more-link {{ request()->routeIs('sales-manager.closed') ? 'active' : '' }}" data-nav-order="7" data-nav-key="closed">
                <i class="fas fa-circle-check"></i>
                <span>Closed</span>
            </a>
            <a href="{{ route('lead-bank.requests.index') }}" class="asm-more-link {{ request()->routeIs('lead-bank.requests.*') ? 'active' : '' }}" data-nav-order="8.5" data-nav-key="lead-requests">
                <i class="fas fa-inbox"></i>
                <span>Requests</span>
            </a>
            <a href="{{ route('sales-manager.profile') }}" class="asm-more-link {{ request()->routeIs('sales-manager.profile') ? 'active' : '' }}" data-nav-order="9" data-nav-key="profile">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
            @if(auth()->user()->canUseAdvisorPublicProfile())
            <a href="{{ route('advisor.profile.show') }}" class="asm-more-link {{ request()->routeIs('advisor.profile.*') ? 'active' : '' }}" data-nav-order="9.5" data-nav-key="my-public-profile">
                <i class="fas fa-id-card"></i>
                <span>Public Profile</span>
            </a>
            @endif
                @if(auth()->user()->isAssistantSalesManager() || auth()->user()->isSeniorManager())
                    <a href="{{ route('sales-manager.settings') }}" class="asm-more-link {{ request()->routeIs('sales-manager.settings') ? 'active' : '' }}" data-nav-order="10" data-nav-key="settings">
                <i class="fas fa-sliders-h"></i>
                <span>Settings</span>
            </a>
            @endif
        </div>
    </div>

    <!-- Custom Notification System -->
    <div id="notificationOverlay" class="fixed inset-0 z-[9999] pointer-events-none flex items-center justify-center" style="display: none;">
        <div id="customNotification" class="bg-white rounded-lg shadow-2xl p-6 max-w-md w-full mx-4 transform transition-all duration-300 scale-0" style="pointer-events: auto;">
            <div class="flex items-center justify-center mb-4">
                <div class="tick-icon w-16 h-16 rounded-full flex items-center justify-center bg-sky-100">
                    <i class="fas fa-check text-sky-700 text-2xl"></i>
                </div>
            </div>
            <p id="notificationMessage" class="text-center text-gray-800 font-medium text-lg"></p>
        </div>
    </div>

    <style>
        #customNotification.show {
            animation: popIn 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55) forwards;
        }
        
        #customNotification.hide {
            animation: popOut 0.3s ease-in forwards;
        }
        
        @keyframes popIn {
            0% {
                transform: scale(0) translateY(-20px);
                opacity: 0;
            }
            50% {
                transform: scale(1.05) translateY(0);
            }
            100% {
                transform: scale(1) translateY(0);
                opacity: 1;
            }
        }
        
        @keyframes popOut {
            0% {
                transform: scale(1) translateY(0);
                opacity: 1;
            }
            100% {
                transform: scale(0.8) translateY(-20px);
                opacity: 0;
            }
        }
        
        .tick-icon {
            animation: tickAnimation 0.6s ease-in-out;
        }
        
        @keyframes tickAnimation {
            0% {
                transform: scale(0);
            }
            50% {
                transform: scale(1.2);
            }
            100% {
                transform: scale(1);
            }
        }
        
        #customNotification.error .tick-icon {
            background: #fee2e2;
        }
        
        #customNotification.error .tick-icon i {
            color: #dc2626;
        }
        
        #customNotification.error .tick-icon i:before {
            content: '\f00d';
        }
        
        #customNotification.warning .tick-icon {
            background: #fef3c7;
        }
        
        #customNotification.warning .tick-icon i {
            color: #d97706;
        }
        
        #customNotification.warning .tick-icon i:before {
            content: '\f071';
        }
    </style>

    <script>
        function showNotification(message, type = 'success', duration = 3000) {
            const overlay = document.getElementById('notificationOverlay');
            const notification = document.getElementById('customNotification');
            const messageEl = document.getElementById('notificationMessage');
            const tickIcon = notification.querySelector('.tick-icon');
            
            // Remove previous type classes
            notification.classList.remove('success', 'error', 'warning');
            
            // Set message and type
            messageEl.textContent = message;
            notification.classList.add(type);
            
            // Show overlay and notification
            overlay.style.display = 'flex';
            notification.style.transform = 'scale(0)';
            
            // Trigger animation
            setTimeout(() => {
                notification.classList.remove('hide');
                notification.classList.add('show');
            }, 10);
            
            // Hide after duration
            setTimeout(() => {
                notification.classList.remove('show');
                notification.classList.add('hide');
                
                setTimeout(() => {
                    overlay.style.display = 'none';
                    notification.classList.remove('hide');
                }, 300);
            }, duration);
        }
        
        // Auto-close on click
        document.getElementById('customNotification')?.addEventListener('click', function() {
            const overlay = document.getElementById('notificationOverlay');
            const notification = document.getElementById('customNotification');
            notification.classList.remove('show');
            notification.classList.add('hide');
            
            setTimeout(() => {
                overlay.style.display = 'none';
                notification.classList.remove('hide');
            }, 300);
        });
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

        var btn = document.getElementById('btnEnablePush');
        var statusEl = document.getElementById('pushStatus');
        var vapidKey = vapidMeta.content;

        var fbConfig = {
            apiKey: firebaseConfig.api_key,
            authDomain: firebaseConfig.auth_domain,
            projectId: firebaseConfig.project_id,
            storageBucket: firebaseConfig.storage_bucket,
            messagingSenderId: firebaseConfig.messaging_sender_id,
            appId: firebaseConfig.app_id
        };

        firebase.initializeApp(fbConfig);
        var messaging = firebase.messaging();

        window.getManagerApiToken = function() {
            var meta = document.querySelector('meta[name="api-token"]');
            return meta && meta.content ? meta.content : '';
        };

        window.clearLegacyManagerAuthState = function() {
            try {
                localStorage.removeItem('sales_manager_token');
                sessionStorage.removeItem('sales_manager_token');
            } catch (e) {}
        };

        window.isManagerPwaContext = function() {
            try {
                return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
            } catch (e) {
                return false;
            }
        };

        window.getManagerAuthHeaders = function(extraHeaders) {
            var token = window.getManagerApiToken();
            var headers = {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'X-Requested-With': 'XMLHttpRequest'
            };
            if (token) {
                headers['Authorization'] = 'Bearer ' + token;
            }
            return Object.assign(headers, extraHeaders || {});
        };

        window.handleManagerAuthFailure = function(contextLabel) {
            window.clearLegacyManagerAuthState();
            console.error('Manager auth failure', {
                context: contextLabel || 'manager',
                auth_source: 'meta',
                pwa: window.isManagerPwaContext(),
                path: window.location.pathname
            });
            return {
                success: false,
                auth_failed: true,
                message: 'Session expired. Please refresh and sign in again.'
            };
        };

        function getAuthToken() {
            return window.getManagerApiToken();
        }

        function getFcmStorageKey() {
            var userId = '';
            try {
                userId = String(window.currentUserId || window.userId || document.body?.dataset?.userId || '');
            } catch (e) {}
            return 'manager_fcm_token_sent:' + userId;
        }

        function sendFcmTokenToServer(fcmToken) {
            if (window.NotificationDeviceOwnership) {
                window.NotificationDeviceOwnership.claimFcm(fcmToken).then(function(ok) {
                    if (statusEl) statusEl.textContent = ok ? 'Push enabled.' : 'Push registration failed.';
                });
                return;
            }
            var authToken = getAuthToken();
            if (!authToken) { if (statusEl) statusEl.textContent = 'Login again and try.'; return; }

            try {
                var cacheKey = getFcmStorageKey();
                var cachedValue = localStorage.getItem(cacheKey);
                if (cachedValue === fcmToken) {
                    if (statusEl) statusEl.textContent = 'Push already enabled.';
                    return;
                }
            } catch (e) {}

            var url = window.location.origin + '/api/fcm-subscription';
            fetch(url, {
                method: 'POST',
                headers: window.getManagerAuthHeaders({ 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + authToken }),
                body: JSON.stringify({ fcm_token: fcmToken, device_type: 'web' })
            }).then(function(r) {
                if (r.ok) {
                    try {
                        localStorage.setItem(getFcmStorageKey(), fcmToken);
                    } catch (e) {}
                }
                if (statusEl) statusEl.textContent = r.ok ? 'Push enabled.' : 'Failed (' + r.status + ')';
            }).catch(function() { if (statusEl) statusEl.textContent = 'Request failed.'; });
        }

        function initFcm() {
            if (statusEl) statusEl.textContent = 'Registering...';
            navigator.serviceWorker.register('/fcm-sw.js').then(function(reg) {
                messaging.getToken({ vapidKey: vapidKey, serviceWorkerRegistration: reg }).then(function(token) {
                    if (token) sendFcmTokenToServer(token);
                    else if (statusEl) statusEl.textContent = 'No FCM token received.';
                }).catch(function(e) { if (statusEl) statusEl.textContent = 'Token error: ' + (e.message || ''); });
            }).catch(function() { if (statusEl) statusEl.textContent = 'SW error.'; });
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

            if (
                kind === 'lead_assigned'
                || kind === 'followup_reminder'
                || kind === 'followup_overdue'
                || kind === 'meeting'
                || kind === 'meeting_reminder'
                || kind === 'site_visit'
                || kind === 'site_visit_reminder'
                || kind === 'task_overdue'
                || kind === 'execution_task_due'
                || kind === 'execution_task_overdue'
                || kind === 'activity_lifecycle_reminder'
                || kind === 'manager_task_reminder'
                || kind === 'telecaller_call_reminder'
                || kind === 'attendance_reminder'
                || String(tag).indexOf('lead-assigned') === 0
                || String(tag).indexOf('manager-call-reminder-') === 0
                || String(tag).indexOf('telecaller-call-reminder-') === 0
                || title === 'New lead assigned'
                || title === 'Call Now'
                || title === 'Call Reminder'
            ) {
                var n = payload.notification || payload.data || {};
                if (typeof showLeadAssignedPopup === 'function') {
                    showLeadAssignedPopup({
                        title: n.title || title || 'Call Reminder',
                        message: n.body || data.body || '',
                        viewUrl: data.secondary_action_url || data.url || data.click_action || '/leads',
                        leadPhone: data.lead_phone || '',
                        leadName: data.lead_name || '',
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
            }
        });

        function runEnablePush() {
            if (Notification.permission === 'granted') { initFcm(); }
            else if (Notification.permission === 'default') {
                if (statusEl) statusEl.textContent = 'Allow in browser prompt...';
                Notification.requestPermission().then(function(p) { if (p === 'granted') initFcm(); else if (statusEl) statusEl.textContent = 'Denied.'; });
            } else { if (statusEl) statusEl.textContent = 'Notifications blocked. Enable in browser settings.'; }
        }

        if (btn) { btn.style.display = 'inline-block'; btn.onclick = runEnablePush; }
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
        
        // Sidebar and main content setup
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const mobileMenuToggle = document.getElementById('asmMobileMenuToggle');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            const sidebarLinks = document.querySelectorAll('#sidebar .sidebar-link, #sidebar .sidebar-sub-link');
            const sidebarGroups = document.querySelectorAll('#sidebar .sidebar-group');
            const moreMenuTrigger = document.getElementById('asmMoreMenuTrigger');
            const moreMenu = document.getElementById('asmMoreMenu');
            const moreMenuClose = document.getElementById('asmMoreMenuClose');
            const moreOverlay = document.getElementById('asmMoreOverlay');

            function getAsmMobileUiMode() {
                if (document.body.classList.contains('asm-ui-classic')) return 'classic';
                if (document.body.classList.contains('asm-ui-modern')) return 'modern';
                return 'modern';
            }

            function closeMobileDrawer() {
                if (!sidebar || !sidebarOverlay) return;
                sidebar.classList.remove('mobile-open');
                sidebarOverlay.classList.remove('active');
                document.body.classList.remove('mobile-drawer-open');
            }

            function openMobileDrawer() {
                if (!sidebar || !sidebarOverlay) return;
                sidebar.classList.add('mobile-open');
                sidebarOverlay.classList.add('active');
                document.body.classList.add('mobile-drawer-open');
            }

            function closeMoreMenu() {
                if (!moreMenu || !moreOverlay || !moreMenuTrigger) return;
                moreMenu.classList.remove('open');
                moreOverlay.classList.remove('open');
                moreMenu.hidden = true;
                moreOverlay.hidden = true;
                moreMenu.setAttribute('aria-hidden', 'true');
                moreMenuTrigger.setAttribute('aria-expanded', 'false');
            }

            function openMoreMenu() {
                if (!moreMenu || !moreOverlay || !moreMenuTrigger) return;
                moreMenu.hidden = false;
                moreOverlay.hidden = false;
                moreMenu.classList.add('open');
                moreOverlay.classList.add('open');
                moreMenu.setAttribute('aria-hidden', 'false');
                moreMenuTrigger.setAttribute('aria-expanded', 'true');
            }

            function toggleMoreMenu() {
                if (!moreMenu) return;
                if (moreMenu.classList.contains('open')) closeMoreMenu();
                else openMoreMenu();
            }

            function sidebarGroupStorageKey(group) {
                return 'asm_sidebar_group_' + (group?.dataset?.sidebarGroup || 'unknown');
            }

            function setSidebarGroupOpen(group, open, persist) {
                if (!group) return;
                group.classList.toggle('open', open);
                const toggle = group.querySelector('.sidebar-group-toggle');
                if (toggle) {
                    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                }
                if (persist) {
                    try {
                        localStorage.setItem(sidebarGroupStorageKey(group), open ? '1' : '0');
                    } catch (e) {}
                }
            }

            function initializeSidebarTree() {
                sidebarGroups.forEach(function(group) {
                    const isActive = group.classList.contains('active');
                    let shouldOpen = group.classList.contains('open');
                    try {
                        const saved = localStorage.getItem(sidebarGroupStorageKey(group));
                        if (saved === '1') shouldOpen = true;
                        if (saved === '0' && !isActive) shouldOpen = false;
                    } catch (e) {}
                    if (isActive) shouldOpen = true;
                    setSidebarGroupOpen(group, shouldOpen, false);

                    const toggle = group.querySelector('.sidebar-group-toggle');
                    if (toggle) {
                        toggle.addEventListener('click', function() {
                            if (document.body.classList.contains('sidebar-collapsed') && window.innerWidth > 767) {
                                return;
                            }
                            setSidebarGroupOpen(group, !group.classList.contains('open'), true);
                        });
                    }
                });
            }
            
            function updateLayout() {
                const isMobile = window.innerWidth <= 767;
                const isCollapsed = !isMobile && document.body.classList.contains('sidebar-collapsed');
                const uiMode = getAsmMobileUiMode();

                if (!isMobile) {
                    closeMoreMenu();
                }
                
                if (sidebar) {
                    if (isMobile) {
                        if (uiMode === 'classic') {
                            // Classic mobile: hide drawer/sidebar completely
                            sidebar.style.display = 'none';
                            sidebar.style.width = '0';
                            sidebar.style.visibility = 'hidden';
                            closeMobileDrawer();
                        } else {
                            // Modern mobile: keep drawer available
                            sidebar.style.display = 'block';
                            sidebar.style.width = '232px';
                            sidebar.style.visibility = 'visible';
                        }
                    } else {
                        // Desktop: collapsible navigation
                        sidebar.classList.remove('sidebar-expanded', 'sidebar-hidden');
                        sidebar.style.width = isCollapsed ? '78px' : '232px';
                        sidebar.style.display = 'block';
                        closeMoreMenu();
                    }
                }
                if (sidebarToggle) {
                    sidebarToggle.style.display = isMobile ? 'none' : 'inline-flex';
                }
                if (mobileMenuToggle) {
                    mobileMenuToggle.style.display = isMobile && uiMode === 'modern' ? 'inline-flex' : 'none';
                }
                
                if (mainContent) {
                    if (isMobile) {
                        // Mobile: Full width, no left margin - override inline style
                        mainContent.style.setProperty('margin-left', '0', 'important');
                        mainContent.style.setProperty('width', 'auto', 'important');
                        mainContent.style.setProperty('padding-left', '0', 'important');
                        mainContent.style.setProperty('padding-right', '0', 'important');
                        mainContent.style.setProperty('left', '0', 'important');
                    } else {
                        closeMobileDrawer();
                        mainContent.style.setProperty('margin-left', isCollapsed ? '78px' : '232px', 'important');
                        mainContent.style.setProperty('width', 'auto', 'important');
                    }
                }
            }

            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    if (window.innerWidth <= 767) return;
                    const collapsed = !document.body.classList.contains('sidebar-collapsed');
                    document.body.classList.toggle('sidebar-collapsed', collapsed);
                    try {
                        localStorage.setItem('asm_sidebar_collapsed', collapsed ? '1' : '0');
                    } catch (e) {}
                    updateLayout();
                });
            }

            if (mobileMenuToggle) {
                mobileMenuToggle.addEventListener('click', function() {
                    const isMobile = window.innerWidth <= 767;
                    const uiMode = getAsmMobileUiMode();
                    if (!isMobile || uiMode !== 'modern') return;
                    const isOpen = sidebar && sidebar.classList.contains('mobile-open');
                    if (isOpen) closeMobileDrawer();
                    else openMobileDrawer();
                });
            }

            if (sidebarOverlay) {
                sidebarOverlay.addEventListener('click', closeMobileDrawer);
            }

            if (moreMenuTrigger) {
                moreMenuTrigger.addEventListener('click', function() {
                    if (window.innerWidth > 767) return;
                    toggleMoreMenu();
                });
            }

            if (moreMenuClose) {
                moreMenuClose.addEventListener('click', closeMoreMenu);
            }

            if (moreOverlay) {
                moreOverlay.addEventListener('click', closeMoreMenu);
            }

            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    closeMoreMenu();
                }
            });

            document.querySelectorAll('.asm-more-link').forEach(function(link) {
                link.addEventListener('click', closeMoreMenu);
            });

            sidebarLinks.forEach(function(link) {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 767 && getAsmMobileUiMode() === 'modern') {
                        closeMobileDrawer();
                    }
                });
            });

            initializeSidebarTree();

            const swipeState = {
                startX: 0,
                startY: 0,
                target: null,
                navigating: false,
                cooldownUntil: 0
            };

            function isAsmMobileSwipeEnabled() {
                return window.innerWidth <= 767 && ['classic', 'modern'].includes(getAsmMobileUiMode());
            }

            function isTextSelectionActive() {
                const selection = window.getSelection ? window.getSelection() : null;
                return !!(selection && String(selection).trim().length);
            }

            function isWithinIgnoredSwipeArea(node) {
                if (!(node instanceof Element)) return false;

                const ignoredSelector = [
                    'input',
                    'textarea',
                    'select',
                    'option',
                    'button',
                    'label',
                    'a',
                    '[contenteditable=""]',
                    '[contenteditable="true"]',
                    '[role="button"]',
                    '[role="dialog"]',
                    '.modal',
                    '.modal-content',
                    '.sidebar-overlay',
                    '#sidebar',
                    '#mobileFooterNav',
                    '#asmMobileMenuToggle'
                ].join(', ');

                if (node.closest(ignoredSelector)) {
                    return true;
                }

                let current = node;
                while (current && current !== document.body) {
                    if (!(current instanceof HTMLElement)) {
                        current = current.parentElement;
                        continue;
                    }

                    const style = window.getComputedStyle(current);
                    const overflowX = style.overflowX;
                    const canScrollHorizontally = /(auto|scroll)/.test(overflowX) && current.scrollWidth > current.clientWidth + 4;
                    const hasNoSwipeFlag = current.hasAttribute('data-swipe-ignore');

                    if (canScrollHorizontally || hasNoSwipeFlag) {
                        return true;
                    }

                    current = current.parentElement;
                }

                return false;
            }

            function normalizePathname(pathname) {
                if (!pathname) return '/';
                return pathname.length > 1 ? pathname.replace(/\/+$/, '') : pathname;
            }

            function getMobileNavLinks() {
                return Array.from(document.querySelectorAll('[data-nav-order]'))
                    .filter(function(link) {
                        return link instanceof HTMLAnchorElement && !!link.href;
                    })
                    .sort(function(a, b) {
                        return Number(a.dataset.navOrder || 0) - Number(b.dataset.navOrder || 0);
                    });
            }

            function getBestActiveNavIndex(navLinks) {
                if (!navLinks.length) return -1;

                const activeClassIndex = navLinks.findIndex(function(link) {
                    return link.classList.contains('active');
                });
                if (activeClassIndex !== -1) return activeClassIndex;

                const currentUrl = new URL(window.location.href);
                const currentPath = normalizePathname(currentUrl.pathname);
                const currentSearch = currentUrl.search;

                let bestIndex = -1;
                let bestScore = -1;

                navLinks.forEach(function(link, index) {
                    let score = 0;

                    try {
                        const linkUrl = new URL(link.href, window.location.origin);
                        const linkPath = normalizePathname(linkUrl.pathname);

                        if (linkPath === currentPath) {
                            score += 4;
                        } else if (currentPath.indexOf(linkPath + '/') === 0) {
                            score += 2;
                        }

                        if (linkUrl.search && linkUrl.search === currentSearch) {
                            score += 3;
                        } else if (!linkUrl.search && !currentSearch) {
                            score += 1;
                        }
                    } catch (e) {
                        score = 0;
                    }

                    if (score > bestScore) {
                        bestScore = score;
                        bestIndex = index;
                    }
                });

                return bestScore > 0 ? bestIndex : -1;
            }

            function navigateBySwipeDirection(direction) {
                if (!isAsmMobileSwipeEnabled() || swipeState.navigating) return;

                const navLinks = getMobileNavLinks();
                if (!navLinks.length) return;

                const activeIndex = getBestActiveNavIndex(navLinks);
                if (activeIndex === -1) return;

                const targetIndex = activeIndex + direction;
                if (targetIndex < 0 || targetIndex >= navLinks.length) return;

                const targetLink = navLinks[targetIndex];
                if (!(targetLink instanceof HTMLAnchorElement) || !targetLink.href) return;

                swipeState.navigating = true;
                swipeState.cooldownUntil = Date.now() + 700;
                window.location.assign(targetLink.href);
            }

            document.addEventListener('touchstart', function(event) {
                if (!isAsmMobileSwipeEnabled() || event.touches.length !== 1) return;
                if (Date.now() < swipeState.cooldownUntil || isTextSelectionActive()) return;

                const touch = event.touches[0];
                const target = event.target instanceof Element ? event.target : null;
                if (!target || isWithinIgnoredSwipeArea(target)) return;

                swipeState.startX = touch.clientX;
                swipeState.startY = touch.clientY;
                swipeState.target = target;
            }, { passive: true });

            document.addEventListener('touchend', function(event) {
                if (!isAsmMobileSwipeEnabled() || event.changedTouches.length !== 1) return;
                if (!swipeState.target || Date.now() < swipeState.cooldownUntil || isTextSelectionActive()) {
                    swipeState.target = null;
                    return;
                }

                const touch = event.changedTouches[0];
                const deltaX = touch.clientX - swipeState.startX;
                const deltaY = touch.clientY - swipeState.startY;
                const absDeltaX = Math.abs(deltaX);
                const absDeltaY = Math.abs(deltaY);
                const horizontalThreshold = 70;
                const verticalTolerance = 45;

                if (absDeltaX < horizontalThreshold || absDeltaY > verticalTolerance || absDeltaX <= absDeltaY) {
                    swipeState.target = null;
                    return;
                }

                if (isWithinIgnoredSwipeArea(swipeState.target)) {
                    swipeState.target = null;
                    return;
                }

                const direction = deltaX < 0 ? 1 : -1;
                swipeState.target = null;
                navigateBySwipeDirection(direction);
            }, { passive: true });

            document.addEventListener('touchcancel', function() {
                swipeState.target = null;
            }, { passive: true });
            
            // Initial setup
            updateLayout();
            
            // Update on window resize
            let resizeTimer;
            window.addEventListener('resize', function() {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(updateLayout, 100);
            });
        });
        
        // Do NOT unregister Service Worker on production – PWA push notifications need it.
        // (Unregister only on localhost for development cache-disable if needed.)
        
        // Clear browser cache on page load (development mode)
        if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1' || window.location.hostname.includes('local')) {
            // Force reload without cache
            window.addEventListener('pageshow', function(event) {
                if (event.persisted) {
                    window.location.reload();
                }
            });
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
                <a id="lead-assigned-view-btn" href="{{ route('leads.index') }}" class="px-4 py-2 rounded-lg font-semibold text-white bg-[#002B45] hover:bg-[#006BA6] transition">View leads</a>
                <a id="lead-assigned-call-btn" href="#" class="px-4 py-2 rounded-lg font-semibold bg-sky-700 text-white hover:bg-sky-800 transition hidden">Call</a>
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
            var headers = {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            };
            var csrf = document.querySelector('meta[name="csrf-token"]');
            if (csrf && csrf.content) headers['X-CSRF-TOKEN'] = csrf.content;
            var apiToken = document.querySelector('meta[name="api-token"]');
            var bearer = (apiToken && apiToken.content) || localStorage.getItem('sales_manager_token') || localStorage.getItem('telecaller_token') || '';
            if (bearer) headers['Authorization'] = 'Bearer ' + bearer;
            return headers;
        }

        function actionLabel(button, fallback) {
            return (button && button.dataset && button.dataset.defaultLabel) || fallback;
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
            var originalLabel = actionLabel(button, button ? button.textContent : 'Working...');
            if (button) {
                button.disabled = true;
                button.dataset.defaultLabel = originalLabel;
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
                if (!response.ok || result.success === false) {
                    throw new Error(result.message || 'Action failed');
                }
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

        async function completeReminderTask(url) {
            if (!url) return;
            if (completeBtn) {
                completeBtn.disabled = true;
                completeBtn.textContent = 'Completing...';
            }
            try {
                var response = await fetch(url, {
                    method: 'POST',
                    headers: apiAuthHeaders(),
                    credentials: 'same-origin',
                    body: JSON.stringify({ outcome: 'completed_from_popup' })
                });
                var result = await response.json().catch(function() { return {}; });
                if (!response.ok || result.success === false) {
                    throw new Error(result.message || 'Failed to complete task');
                }
                if (messageEl) messageEl.textContent = result.message || 'Task completed successfully.';
                setTimeout(function() { window.closeLeadAssignedModal(); }, 700);
            } catch (e) {
                if (messageEl) messageEl.textContent = e.message || 'Failed to complete task. Please open CRM and try again.';
            } finally {
                if (completeBtn) {
                    completeBtn.disabled = false;
                    completeBtn.textContent = 'Complete Task';
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
                completeBtn.onclick = null;
                completeBtn.textContent = o.dismissLabel || o.dismiss_label || 'Dismiss';
                completeBtn.className = 'px-4 py-2 rounded-lg font-semibold bg-gray-200 text-gray-700 hover:bg-gray-300 transition';
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
        if (completeBtn) completeBtn.onclick = closeLeadAssignedModal;
        if (overlay) overlay.addEventListener('click', function(e) { if (e.target === overlay) closeLeadAssignedModal(); });
        var uid = document.querySelector('meta[name="user-id"]') && document.querySelector('meta[name="user-id"]').getAttribute('content');
        var pk = document.querySelector('meta[name="pusher-key"]') && document.querySelector('meta[name="pusher-key"]').getAttribute('content');
        if (uid && pk && typeof Pusher !== 'undefined') {
            try {
                var pusher = new Pusher(pk, { cluster: (document.querySelector('meta[name="pusher-cluster"]') && document.querySelector('meta[name="pusher-cluster"]').getAttribute('content')) || 'mt1', encrypted: true, authEndpoint: '/broadcasting/auth' });
                pusher.subscribe('private-user.' + uid).bind('lead.assigned', function(data) {
                    if (data && data.suppress_popup) return;
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
    
    <!-- Include Meeting Section Modals (for reschedule, complete, mark dead) -->
    @if(request()->routeIs('sales-manager.meetings') || request()->routeIs('sales-manager.tasks'))
    <!-- Reschedule Meeting Modal -->
    <div id="rescheduleMeetingModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <h3 id="rescheduleModalTitle" style="font-size: 20px; font-weight: 600; margin-bottom: 20px;">Reschedule</h3>
            <input type="hidden" id="rescheduleModalType" value="meeting">
            <input type="hidden" id="rescheduleModalId" value="">
            
            <div class="form-group">
                <label>New Scheduled Date & Time <span style="color: #ef4444;">*</span></label>
                <input type="datetime-local" id="rescheduleScheduledAt" required
                    class="form-group input" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px;">
                <small style="color: #6b7280;">Select a future date and time</small>
            </div>

            <div class="form-group">
                <label>Reason for Rescheduling <span style="color: #ef4444;">*</span></label>
                <textarea id="rescheduleReason" rows="4" placeholder="Enter reason for rescheduling..." required
                    class="form-group textarea" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px;"></textarea>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                <button type="button" class="btn btn-secondary" onclick="closeRescheduleMeetingModal()">Cancel</button>
                <button type="button" class="btn" style="background: #f59e0b; color: white;" onclick="submitRescheduleMeeting()">Reschedule</button>
            </div>
        </div>
    </div>

    <style>
        .complete-meeting-modal-card {
            display: flex;
            flex-direction: column;
            width: min(760px, 92vw);
            max-height: min(88vh, 920px);
            background: linear-gradient(180deg, #ffffff 0%, #fbfdfc 100%);
            border: 1px solid #dce9e2;
            box-shadow: 0 24px 48px rgba(6, 58, 28, 0.14);
        }
        .complete-meeting-modal-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            padding: 24px 28px 18px;
            background: linear-gradient(135deg, #f5fbf7 0%, #eef7f2 100%);
            border-bottom: 1px solid #dce9e2;
        }
        .complete-meeting-modal-head h3 {
            margin: 0;
            font-size: 28px;
            line-height: 1.1;
            color: #0b3d29;
        }
        .complete-meeting-modal-head p {
            margin: 8px 0 0;
            color: #5e7669;
            font-size: 14px;
        }
        .complete-meeting-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 14px;
            border-radius: 999px;
            background: #e6f4ea;
            color: #0f6d3c;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .complete-meeting-modal-body {
            flex: 1 1 auto;
            overflow-y: auto;
            padding: 24px 28px 12px;
        }
        .complete-meeting-alert {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 14px 16px;
            margin-bottom: 20px;
            border: 1px solid #ffd4d4;
            border-radius: 16px;
            background: linear-gradient(135deg, #fff5f5 0%, #fffafa 100%);
        }
        .complete-meeting-alert i {
            font-size: 20px;
            color: #dc2626;
            margin-top: 2px;
        }
        .complete-meeting-alert strong,
        .complete-meeting-alert span {
            display: block;
        }
        .complete-meeting-alert strong {
            color: #b42318;
            margin-bottom: 3px;
        }
        .complete-meeting-alert span {
            color: #7a5c5c;
            font-size: 13px;
        }
        .complete-meeting-upload {
            padding: 18px;
            margin-bottom: 20px;
            border: 1px solid #dce9e2;
            border-radius: 18px;
            background: #f9fcfa;
        }
        .complete-meeting-upload label {
            display: block;
            margin-bottom: 10px;
            font-size: 15px;
            font-weight: 700;
            color: #103c2b;
        }
        .complete-meeting-upload input[type="file"] {
            width: 100%;
            padding: 12px;
            border: 1px dashed #9fc2b0;
            border-radius: 14px;
            background: #fff;
        }
        .complete-meeting-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 14px;
        }
        .complete-meeting-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(220px, 0.52fr);
            gap: 18px;
        }
        .complete-meeting-field {
            margin-bottom: 0;
        }
        .complete-meeting-field-wide {
            grid-column: 1 / -1;
        }
        .complete-meeting-field label {
            margin-bottom: 8px;
            font-size: 15px;
            font-weight: 700;
            color: #103c2b;
        }
        .complete-meeting-field textarea,
        .complete-meeting-field select {
            width: 100%;
            border: 1px solid #d6e1db;
            border-radius: 14px;
            padding: 14px 15px;
            color: #183c2c;
            background: #fff;
        }
        .complete-meeting-field textarea {
            min-height: 120px;
            resize: vertical;
        }
        .complete-meeting-field textarea:focus,
        .complete-meeting-field select:focus,
        .complete-meeting-upload input[type="file"]:focus {
            outline: none;
            border-color: #205A44;
            box-shadow: 0 0 0 3px rgba(32, 90, 68, 0.12);
        }

        body.asm-shell {
            --asm-brand: #205A44;
            --asm-brand-dark: #063A1C;
            --asm-brand-soft: #ECFDF3;
            --asm-border: #D7E4DC;
        }

        body.asm-shell .text-\[\#002B45\],
        body.asm-shell .text-\[\#006BA6\],
        body.asm-shell .text-\[\#003F63\],
        body.asm-shell .text-blue-600,
        body.asm-shell .text-blue-700,
        body.asm-shell .text-blue-800,
        body.asm-shell .text-sky-600,
        body.asm-shell .text-sky-700,
        body.asm-shell .text-sky-800,
        body.asm-shell .text-sky-900,
        body.asm-shell [style*="color:#006BA6"],
        body.asm-shell [style*="color: #006BA6"],
        body.asm-shell [style*="color:#002B45"],
        body.asm-shell [style*="color: #002B45"],
        body.asm-shell [style*="color:#003F63"],
        body.asm-shell [style*="color: #003F63"] {
            color: var(--asm-brand-dark) !important;
        }

        body.asm-shell .bg-\[\#002B45\],
        body.asm-shell .bg-\[\#006BA6\],
        body.asm-shell .bg-blue-600,
        body.asm-shell .bg-blue-700,
        body.asm-shell .bg-blue-800,
        body.asm-shell .bg-sky-600,
        body.asm-shell .bg-sky-700,
        body.asm-shell .bg-sky-800,
        body.asm-shell .hover\:bg-\[\#006BA6\]:hover,
        body.asm-shell .hover\:bg-blue-700:hover,
        body.asm-shell .hover\:bg-sky-800:hover {
            background: var(--asm-brand-dark) !important;
        }

        body.asm-shell .from-\[\#002B45\],
        body.asm-shell .from-\[\#006BA6\],
        body.asm-shell .from-blue-600,
        body.asm-shell .from-sky-600 {
            --tw-gradient-from: var(--asm-brand-dark) var(--tw-gradient-from-position) !important;
        }

        body.asm-shell .via-\[\#006BA6\],
        body.asm-shell .via-blue-600,
        body.asm-shell .via-sky-600 {
            --tw-gradient-via: var(--asm-brand) var(--tw-gradient-via-position) !important;
        }

        body.asm-shell .to-\[\#0EA5E9\],
        body.asm-shell .to-\[\#006BA6\],
        body.asm-shell .to-blue-500,
        body.asm-shell .to-blue-600,
        body.asm-shell .to-sky-500,
        body.asm-shell .to-sky-600 {
            --tw-gradient-to: #2E7D5F var(--tw-gradient-to-position) !important;
        }

        body.asm-shell [style*="background:#006BA6"],
        body.asm-shell [style*="background: #006BA6"],
        body.asm-shell [style*="background:#002B45"],
        body.asm-shell [style*="background: #002B45"],
        body.asm-shell [style*="background:#0EA5E9"],
        body.asm-shell [style*="background: #0EA5E9"],
        body.asm-shell [style*="background: linear-gradient(135deg, #002B45"],
        body.asm-shell [style*="background:linear-gradient(135deg, #002B45"],
        body.asm-shell [style*="background: linear-gradient(135deg, #006BA6"],
        body.asm-shell [style*="background:linear-gradient(135deg, #006BA6"],
        body.asm-shell [style*="background: linear-gradient(180deg, #002B45"],
        body.asm-shell [style*="background:linear-gradient(180deg, #002B45"] {
            background: linear-gradient(135deg, var(--asm-brand-dark) 0%, var(--asm-brand) 100%) !important;
        }

        body.asm-shell .bg-sky-100,
        body.asm-shell .bg-sky-50,
        body.asm-shell .bg-blue-100,
        body.asm-shell .bg-blue-50,
        body.asm-shell .bg-\[\#E0F2FE\],
        body.asm-shell [style*="background:#E0F2FE"],
        body.asm-shell [style*="background: #E0F2FE"],
        body.asm-shell [style*="background:#EAF6FD"],
        body.asm-shell [style*="background: #EAF6FD"] {
            background: var(--asm-brand-soft) !important;
        }

        body.asm-shell .border-sky-100,
        body.asm-shell .border-sky-200,
        body.asm-shell .border-blue-100,
        body.asm-shell .border-blue-200,
        body.asm-shell .focus\:border-blue-500:focus,
        body.asm-shell .focus\:ring-blue-500:focus,
        body.asm-shell [style*="border-color:#006BA6"],
        body.asm-shell [style*="border-color: #006BA6"],
        body.asm-shell [style*="border:2px solid #006BA6"],
        body.asm-shell [style*="border: 2px solid #006BA6"],
        body.asm-shell [style*="border:1px solid #CDE5F5"],
        body.asm-shell [style*="border: 1px solid #CDE5F5"] {
            border-color: var(--asm-border) !important;
        }

        body.asm-shell .asm-outcome-btn-blue,
        body.asm-shell .task-meeting-action-tile-blue .task-meeting-action-icon {
            background: linear-gradient(135deg, var(--asm-brand-dark) 0%, var(--asm-brand) 100%) !important;
            color: #ffffff !important;
        }
        .complete-meeting-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            padding: 18px 28px 24px;
            border-top: 1px solid #e4ede8;
            background: #ffffff;
        }
        .complete-meeting-actions .btn {
            min-width: 120px;
            min-height: 46px;
            border-radius: 12px;
            font-weight: 700;
        }
        @media (max-width: 768px) {
            .complete-meeting-modal-head,
            .complete-meeting-modal-body,
            .complete-meeting-actions {
                padding-left: 18px;
                padding-right: 18px;
            }
            .complete-meeting-modal-head {
                flex-direction: column;
                align-items: stretch;
            }
            .complete-meeting-grid {
                grid-template-columns: 1fr;
            }
            .complete-meeting-actions {
                flex-direction: column-reverse;
            }
            .complete-meeting-actions .btn {
                width: 100%;
            }
        }
    </style>

    <!-- Complete Meeting Modal -->
    <div id="completeMeetingModal" class="modal">
        <div class="modal-content complete-meeting-modal-card" style="max-width: 760px; padding: 0; overflow: hidden;">
            <div class="complete-meeting-modal-head">
                <div>
                    <h3>Complete Meeting</h3>
                    <p>Upload proof and record the outcome before submitting.</p>
                </div>
                <span class="complete-meeting-badge">Required</span>
            </div>

            <div class="complete-meeting-modal-body">
                <div class="complete-meeting-alert">
                    <i class="fas fa-camera"></i>
                    <div>
                        <strong>Proof photos are mandatory.</strong>
                        <span>Add at least one clear meeting proof photo. Max 5MB per image.</span>
                    </div>
                </div>

                <div class="complete-meeting-upload">
                    <label for="proofPhotosInput">Proof Photos <span class="req">*</span></label>
                    <input type="file" id="proofPhotosInput" multiple accept="image/*" onchange="handleProofPhotosChange(event)" required>
                    <div id="proofPhotosPreview" class="complete-meeting-preview"></div>
                </div>

                <div class="complete-meeting-grid">
                    <div class="form-group complete-meeting-field complete-meeting-field-wide">
                        <label for="meetingFeedback">Feedback</label>
                        <textarea id="meetingFeedback" rows="4" placeholder="Summarize discussion, interest level, and next steps..."></textarea>
                    </div>

                    <div class="form-group complete-meeting-field">
                        <label for="meetingRating">Rating</label>
                        <select id="meetingRating">
                            <option value="">Select rating</option>
                            <option value="1">1 - Poor</option>
                            <option value="2">2 - Fair</option>
                            <option value="3">3 - Good</option>
                            <option value="4">4 - Very Good</option>
                            <option value="5">5 - Excellent</option>
                        </select>
                    </div>

                    <div class="form-group complete-meeting-field complete-meeting-field-wide">
                        <label for="meetingNotes">Notes</label>
                        <textarea id="meetingNotes" rows="4" placeholder="Add internal notes, objections, or follow-up context..."></textarea>
                    </div>
                </div>
            </div>

            <div class="complete-meeting-actions">
                <button type="button" class="btn btn-secondary" onclick="closeCompleteMeetingModal()">Cancel</button>
                <button type="button" class="btn btn-success" onclick="submitCompleteMeeting()">Submit</button>
            </div>
        </div>
    </div>

    <!-- Mark as Dead Modal -->
    <div id="markDeadModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <h3 style="font-size: 20px; font-weight: 600; margin-bottom: 20px;">Mark as Dead</h3>
            <p style="color: #ef4444; margin-bottom: 16px;">This will mark the meeting and associated lead as dead. This action cannot be undone.</p>
            
            <div class="form-group">
                <label>Reason <span style="color: #ef4444;">*</span></label>
                <textarea id="deadReason" rows="4" placeholder="Enter reason for marking as dead..." required></textarea>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                <button type="button" class="btn btn-secondary" onclick="closeMarkDeadModal()">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="submitMarkDead()">Mark as Dead</button>
            </div>
        </div>
    </div>
    
    <script>
    // Make meeting modal functions available globally if not already defined
    if (typeof showRescheduleMeetingModal === 'undefined') {
        function showRescheduleMeetingModal(id) {
            if (typeof currentMeetingId !== 'undefined') {
                window.currentMeetingId = id;
            }
            // Get meeting details
            apiCall(`/meetings/${id}`).then(meeting => {
                if (meeting && meeting.id) {
                    const scheduledDate = new Date(meeting.scheduled_at);
                    const minDateTime = new Date();
                    minDateTime.setDate(minDateTime.getDate() + 1);
                    minDateTime.setHours(0, 0, 0, 0);
                    
                    document.getElementById('rescheduleScheduledAt').value = '';
                    document.getElementById('rescheduleReason').value = '';
                    document.getElementById('rescheduleModalTitle').textContent = 'Reschedule Meeting';
                    document.getElementById('rescheduleModalType').value = 'meeting';
                    document.getElementById('rescheduleModalId').value = id;
                    document.getElementById('rescheduleScheduledAt').min = minDateTime.toISOString().slice(0, 16);
                    document.getElementById('rescheduleMeetingModal').classList.add('show');
                }
            });
        }
    }
    
    if (typeof closeRescheduleMeetingModal === 'undefined') {
        function closeRescheduleMeetingModal() {
            document.getElementById('rescheduleMeetingModal').classList.remove('show');
            document.getElementById('rescheduleScheduledAt').value = '';
            document.getElementById('rescheduleReason').value = '';
            if (typeof currentMeetingId !== 'undefined') {
                window.currentMeetingId = null;
            }
        }
    }
    
    if (typeof submitRescheduleMeeting === 'undefined') {
        async function submitRescheduleMeeting() {
            const type = document.getElementById('rescheduleModalType').value;
            const id = document.getElementById('rescheduleModalId').value;
            const scheduledAt = document.getElementById('rescheduleScheduledAt').value;
            const reason = document.getElementById('rescheduleReason').value.trim();

            if (!scheduledAt) {
                alert('Please select a new scheduled date and time');
                return;
            }

            if (!reason) {
                alert('Please provide a reason for rescheduling');
                return;
            }

            try {
                const result = await apiCall(`/${type === 'meeting' ? 'meetings' : 'site-visits'}/${id}/reschedule`, {
                    method: 'POST',
                    body: JSON.stringify({
                        scheduled_at: scheduledAt,
                        reason: reason,
                    }),
                });

                if (result && result.success) {
                    // Complete calling task if pending
                    if (window.pendingTaskCompletion) {
                        await completeCallingTask(window.pendingTaskCompletion.taskId, window.pendingTaskCompletion.taskType);
                        window.pendingTaskCompletion = null;
                    }
                    if (typeof showNotification === 'function') {
                        showNotification(result.message || 'Rescheduled successfully! Verification required.', 'success', 3000);
                    } else {
                        alert(result.message || 'Rescheduled successfully! Verification required.');
                    }
                    closeRescheduleMeetingModal();
                    if (typeof loadMeetings === 'function') {
                        loadMeetings();
                    }
                    if (typeof loadTasks === 'function') {
                        loadTasks();
                    }
                } else {
                    alert(result.message || 'Failed to reschedule');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Network error. Please try again.');
            }
        }
    }
    
    if (typeof showCompleteMeetingModal === 'undefined') {
        function showCompleteMeetingModal(id) {
            if (typeof currentMeetingId !== 'undefined') {
                window.currentMeetingId = id;
            }
            document.getElementById('completeMeetingModal').classList.add('show');
        }
    }
    
    if (typeof closeCompleteMeetingModal === 'undefined') {
        function closeCompleteMeetingModal() {
            document.getElementById('completeMeetingModal').classList.remove('show');
            const photosInput = document.getElementById('proofPhotosInput');
            if (photosInput) {
                photosInput.value = '';
            }
            const preview = document.getElementById('proofPhotosPreview');
            if (preview) {
                preview.innerHTML = '';
            }
            if (typeof currentMeetingId !== 'undefined') {
                window.currentMeetingId = null;
            }
        }
    }
    
    if (typeof handleProofPhotosChange === 'undefined') {
        function handleProofPhotosChange(event) {
            const files = event.target.files;
            const preview = document.getElementById('proofPhotosPreview');
            if (!preview) return;
            preview.innerHTML = '';
            
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.style.width = '100px';
                    img.style.height = '100px';
                    img.style.objectFit = 'cover';
                    img.style.borderRadius = '8px';
                    img.style.margin = '5px';
                    preview.appendChild(img);
                };
                reader.readAsDataURL(file);
            }
        }
    }
    
    if (typeof submitCompleteMeeting === 'undefined') {
        async function submitCompleteMeeting() {
            const meetingId = typeof currentMeetingId !== 'undefined' ? window.currentMeetingId : null;
            if (!meetingId) return;

            const formData = new FormData();
            const photosInput = document.getElementById('proofPhotosInput');
            
            if (!photosInput || !photosInput.files || photosInput.files.length === 0) {
                alert('Please upload at least one proof photo');
                return;
            }

            for (let i = 0; i < photosInput.files.length; i++) {
                formData.append('proof_photos[]', photosInput.files[i]);
            }

            const feedback = document.getElementById('meetingFeedback')?.value;
            const rating = document.getElementById('meetingRating')?.value;
            const notes = document.getElementById('meetingNotes')?.value;

            if (feedback) formData.append('feedback', feedback);
            if (rating) formData.append('rating', rating);
            if (notes) formData.append('meeting_notes', notes);

            try {
                const token = typeof getToken === 'function' ? getToken() : (window.API_TOKEN || document.querySelector('meta[name="api-token"]')?.content);
                const apiBase = window.API_BASE_URL || '/api';
                const response = await fetch(`${apiBase}/meetings/${meetingId}/complete`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json',
                    },
                    body: formData,
                });

                const result = await response.json();

                if (result && result.success) {
                    // Complete calling task if pending
                    if (window.pendingTaskCompletion) {
                        await completeCallingTask(window.pendingTaskCompletion.taskId, window.pendingTaskCompletion.taskType);
                        window.pendingTaskCompletion = null;
                    }
                    alert('Meeting completed with proof photos! Awaiting verification.');
                    closeCompleteMeetingModal();
                    if (typeof loadMeetings === 'function') {
                        loadMeetings();
                    }
                    if (typeof loadTasks === 'function') {
                        loadTasks();
                    }
                } else {
                    alert(result.message || 'Failed to complete meeting');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Network error. Please try again.');
            }
        }
    }
    
    if (typeof showMarkDeadModal === 'undefined') {
        function showMarkDeadModal(type, id) {
            if (typeof currentMeetingId !== 'undefined') {
                window.currentMeetingId = id;
            }
            const deadReasonInput = document.getElementById('deadReason');
            if (deadReasonInput) {
                deadReasonInput.value = '';
            }
            document.getElementById('markDeadModal').classList.add('show');
        }
    }
    
    if (typeof closeMarkDeadModal === 'undefined') {
        function closeMarkDeadModal() {
            document.getElementById('markDeadModal').classList.remove('show');
            const deadReasonInput = document.getElementById('deadReason');
            if (deadReasonInput) {
                deadReasonInput.value = '';
            }
            if (typeof currentMeetingId !== 'undefined') {
                window.currentMeetingId = null;
            }
        }
    }
    
    if (typeof submitMarkDead === 'undefined') {
        async function submitMarkDead() {
            const meetingId = typeof currentMeetingId !== 'undefined' ? window.currentMeetingId : null;
            if (!meetingId) return;

            const reason = document.getElementById('deadReason')?.value.trim();
            if (!reason) {
                alert('Please provide a reason for marking as dead');
                return;
            }

            const result = await apiCall(`/meetings/${meetingId}/mark-dead`, {
                method: 'POST',
                body: JSON.stringify({ reason }),
            });

            if (result && result.success) {
                // Complete calling task if pending
                if (window.pendingTaskCompletion) {
                    await completeCallingTask(window.pendingTaskCompletion.taskId, window.pendingTaskCompletion.taskType);
                    window.pendingTaskCompletion = null;
                }
                alert('Meeting marked as dead successfully');
                closeMarkDeadModal();
                if (typeof loadMeetings === 'function') {
                    loadMeetings();
                }
                if (typeof loadTasks === 'function') {
                    loadTasks();
                }
            } else {
                alert(result.message || 'Failed to mark as dead');
            }
        }
    }
    
    if (typeof cancelMeeting === 'undefined') {
        async function cancelMeeting(id) {
            if (!confirm('Cancel this meeting?')) return;

            const result = await apiCall(`/meetings/${id}/cancel`, {
                method: 'POST',
            });

            if (result && result.success) {
                // Complete calling task if pending
                if (window.pendingTaskCompletion) {
                    await completeCallingTask(window.pendingTaskCompletion.taskId, window.pendingTaskCompletion.taskType);
                    window.pendingTaskCompletion = null;
                }
                alert('Meeting cancelled');
                if (typeof loadMeetings === 'function') {
                    loadMeetings();
                }
                if (typeof loadTasks === 'function') {
                    loadTasks();
                }
            } else {
                alert(result.message || 'Failed to cancel meeting');
            }
        }
    }
    
    if (typeof completeCallingTask === 'undefined') {
        async function completeCallingTask(taskId, taskType) {
            if (!taskId) return true;
            
            const apiToken = window.API_TOKEN || document.querySelector('meta[name="api-token"]')?.content;
            const apiBase = window.API_BASE_URL || '/api';
            
            if (!apiToken) {
                console.warn('API token not found, skipping task completion');
                return false;
            }
            
            try {
                let endpoint;
                if (taskType === 'Task') {
                    endpoint = `${apiBase}/sales-manager/tasks/${taskId}/complete`;
                } else {
                    endpoint = `${apiBase}/telecaller/tasks/${taskId}/complete`;
                }
                
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${apiToken}`,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    }
                });
                
                if (response.ok) {
                    return true;
                } else {
                    const result = await response.json();
                    console.error('Failed to complete task:', result.message || 'Unknown error');
                    return false;
                }
            } catch (error) {
                console.error('Error completing task:', error);
                return false;
            }
        }
    }
    </script>
    @endif
    @include('components.password-change-required-modal')
</body>
</html>

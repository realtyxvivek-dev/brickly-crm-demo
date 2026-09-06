<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>@yield('title', brand_name())</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}?v=brickly-1" sizes="any">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('icon-192.png') }}?v=brickly-1">
    <link rel="apple-touch-icon" href="{{ asset('icon-192.png') }}?v=brickly-1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        // Ensure token exists in session — create once if missing (e.g. old sessions)
        if (auth()->check()) {
            $__poRequestTypes = [];
            try {
                $__poRequestTypes = \App\Models\PurchaseOrderAccess::allowedRequestTypes(auth()->user());
            } catch (\Throwable $e) {
                $__poRequestTypes = [];
            }
            $__canRaisePurchaseOrder = !empty($__poRequestTypes);
            $__canRaiseNeedPurchase = array_key_exists(\App\Models\PurchaseOrder::REQUEST_TYPE_NEED_PURCHASE, $__poRequestTypes);
            $__canRaiseReimbursement = array_key_exists(\App\Models\PurchaseOrder::REQUEST_TYPE_ALREADY_PURCHASED, $__poRequestTypes);

            $__existingToken = session('api_token');
            $__tokenValid = false;
            if ($__existingToken) {
                $__tokenId = explode('|', $__existingToken)[0];
                $__tokenValid = \Laravel\Sanctum\PersonalAccessToken::find($__tokenId) !== null;
            }
            if (!$__tokenValid) {
                // Purana invalid token revoke karo
                if ($__existingToken) {
                    $__tokenId = explode('|', $__existingToken)[0];
                    \Laravel\Sanctum\PersonalAccessToken::find($__tokenId)?->delete();
                }
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
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'Poppins', 'system-ui', 'sans-serif'],
                    },
                    colors: {
                        'primary-dark': 'var(--text-color)',
                        'primary': 'var(--text-color)',
                        'secondary': 'var(--link-color)',
                        'brand-bg': '#F7F6F3',
                        'brand-border': '#E5DED4',
                        'text-muted': '#B3B5B4',
                    },
                },
            },
        }
    </script>
    
    <!-- Additional scripts -->
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: {{ primary_color() }};
            --secondary-color: {{ secondary_color() }};
            --accent-color: {{ accent_color() }};
            --gradient-start: {{ gradient_start_color() }};
            --gradient-end: {{ gradient_end_color() }};
            --text-color: {{ text_color() }};
            --link-color: {{ link_color() }};
            --background-color: {{ background_color() }};
            --text-primary: {{ text_color() }};
            --text-secondary: {{ link_color() }};
            --text-muted: #B3B5B4;
            --border-color: {{ primary_color() }};
            --avatar-bg: {{ gradient_start_color() }};
        }

        /* Brickly CRM admin theme */
        :root {
            --primary-color: #205A44;
            --secondary-color: #063A1C;
            --accent-color: #15803d;
            --gradient-start: #063A1C;
            --gradient-end: #205A44;
            --text-color: #063A1C;
            --link-color: #205A44;
            --text-primary: #063A1C;
            --text-secondary: #205A44;
            --border-color: #205A44;
            --avatar-bg: #063A1C;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100%; margin: 0; padding: 0; overflow: hidden; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #F7F6F3; }
        /* Prevent transition flash on page load */
        body.no-transition *, body.no-transition *::before, body.no-transition *::after { transition: none !important; animation: none !important; }
        /* Transitions only active after page is ready */
        body.sidebar-ready #sidebar { transition: width 0.3s ease-in-out, transform 0.3s ease-in-out; }
        body.sidebar-ready #mainContent { transition: margin-left 0.3s ease-in-out; }
        /* Pre-render: set correct sidebar width on <html> BEFORE body exists */
        html.pre-nav-text #sidebar { width: 256px !important; min-width: 256px !important; }
        html.pre-nav-text #mainContent { margin-left: 256px !important; }
        html.pre-nav-icons #sidebar { width: 64px !important; max-width: 64px !important; }
        html.pre-nav-icons #mainContent { margin-left: 64px !important; }
        .container { max-width: 100%; margin: 0 auto; padding: 20px; width: 100%; box-sizing: border-box; }
        .header { background: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .btn { padding: 12px 24px; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; font-weight: 500; transition: all 0.3s; }
        
        /* Branded Button Classes */
        .btn-brand-primary, .btn-brand-gradient {
            @if(use_gradient())
                background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            @else
                background-color: var(--primary-color);
            @endif
            color: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .btn-brand-primary:hover, .btn-brand-gradient:hover {
            @if(use_gradient())
                background: linear-gradient(135deg, var(--gradient-end), var(--accent-color));
            @else
                background-color: var(--secondary-color);
            @endif
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
        
        .btn-brand-secondary {
            background-color: var(--secondary-color);
            color: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .btn-brand-secondary:hover {
            background-color: var(--primary-color);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
        
        /* Legacy button classes - now use branding */
        .btn-primary, .btn-success, .btn-secondary, .btn-warning {
            @if(use_gradient())
                background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end)) !important;
            @else
                background-color: var(--primary-color) !important;
            @endif
            color: white !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .btn-primary:hover, .btn-success:hover, .btn-secondary:hover, .btn-warning:hover {
            @if(use_gradient())
                background: linear-gradient(135deg, var(--gradient-end), var(--accent-color)) !important;
            @else
                background-color: var(--secondary-color) !important;
            @endif
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
        
        /* Dynamic gradient button class - uses CSS variables */
        .btn-gradient-dynamic {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end)) !important;
            color: white !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .btn-gradient-dynamic:hover {
            background: linear-gradient(135deg, var(--gradient-end), var(--accent-color)) !important;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
        
        /* Override all gradient buttons to use CSS variables - High specificity */
        body a.bg-gradient-to-r,
        body button.bg-gradient-to-r,
        body a[class*="from-[#063A1C]"],
        body button[class*="from-[#063A1C]"],
        body a[class*="from-[#205A44]"],
        body button[class*="from-[#205A44]"] {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end)) !important;
            border: none !important;
        }
        body a.bg-gradient-to-r:hover,
        body button.bg-gradient-to-r:hover,
        body a[class*="from-[#063A1C]"]:hover,
        body button[class*="from-[#063A1C]"]:hover,
        body a[class*="from-[#205A44]"]:hover,
        body button[class*="from-[#205A44]"]:hover {
            background: linear-gradient(135deg, var(--gradient-end), var(--accent-color)) !important;
        }
        
        /* Override hover classes */
        body a[class*="hover:from-[#205A44]"]:hover,
        body button[class*="hover:from-[#205A44]"]:hover,
        body a[class*="hover:to-[#15803d]"]:hover,
        body button[class*="hover:to-[#15803d]"]:hover {
            background: linear-gradient(135deg, var(--gradient-end), var(--accent-color)) !important;
        }
        
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #dc2626; }
        
        /* Branding Utility Classes */
        .text-brand-primary {
            color: var(--text-color) !important;
        }
        .text-brand-secondary {
            color: var(--link-color) !important;
        }
        .text-brand-muted {
            color: var(--text-muted) !important;
        }
        .border-brand {
            border-color: var(--primary-color) !important;
        }
        .border-brand-secondary {
            border-color: var(--secondary-color) !important;
        }
        .bg-brand-avatar {
            @if(use_gradient())
                background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end)) !important;
            @else
                background-color: var(--primary-color) !important;
            @endif
        }
        
        /* Global CSS Override Rules - Force hardcoded Tailwind classes to use CSS variables */
        /* Override hardcoded text colors */
        [class*="text-[#063A1C]"], [class*="text-[#205A44]"] {
            color: var(--text-color) !important;
        }
        
        /* Override hardcoded border colors */
        [class*="border-[#063A1C]"], [class*="border-[#205A44]"] {
            border-color: var(--primary-color) !important;
        }
        
        /* Override hardcoded background colors for avatars and brand elements */
        [class*="bg-[#063A1C]"], [class*="bg-[#205A44]"] {
            @if(use_gradient())
                background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end)) !important;
            @else
                background-color: var(--primary-color) !important;
            @endif
        }
        
        /* Override hardcoded gradient classes for cards */
        [class*="from-[#205A44]"], [class*="from-[#063A1C]"], 
        [class*="to-[#063A1C]"], [class*="to-[#205A44]"],
        [class*="bg-gradient-to-br"][class*="from-[#205A44]"],
        [class*="bg-gradient-to-br"][class*="from-[#063A1C]"] {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end)) !important;
        }
        
        /* Override hover states for hardcoded backgrounds */
        [class*="hover:bg-[#205A44]"], [class*="hover:bg-[#063A1C]"], [class*="hover:bg-[#15803d]"] {
            @if(use_gradient())
                background: linear-gradient(135deg, var(--gradient-end), var(--accent-color)) !important;
            @else
                background-color: var(--secondary-color) !important;
            @endif
        }
        
        /* Override focus ring colors */
        [class*="focus:ring-[#205A44]"], [class*="focus:ring-[#063A1C]"] {
            --tw-ring-color: var(--primary-color) !important;
        }
        
        /* Override focus border colors */
        [class*="focus:border-[#205A44]"], [class*="focus:border-[#063A1C]"] {
            border-color: var(--primary-color) !important;
        }
        
        /* Override hover text colors */
        [class*="hover:text-[#205A44]"], [class*="hover:text-[#063A1C]"] {
            color: var(--link-color) !important;
        }
        
        /* Override ring colors (for focus states) */
        [class*="ring-[#063A1C]"], [class*="ring-[#205A44]"] {
            --tw-ring-color: var(--primary-color) !important;
        }
        
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
        
        /* Sidebar width: controlled here only (no Tailwind w-64) to prevent overlap with main content */
        #sidebar {
            width: 64px;
            min-width: 64px;
            max-width: 64px;
        }
        /* Sidebar nav mode
           - Default is icon mode (body.nav-icons)
           - Toggle to text mode (body.nav-text) using localStorage
        */
        body.nav-icons #sidebar {
            width: 64px !important;
            min-width: 64px !important;
            max-width: 64px !important;
        }
        
        body.nav-icons #sidebar nav {
            padding: 0 12px !important;
        }
        
        body.nav-icons #sidebar h2,
        body.nav-icons #sidebar p {
            display: none !important;
        }
        
        body.nav-icons #sidebar .sidebar-link {
            justify-content: center;
            padding: 12px !important;
            font-size: 0 !important;
        }
        
        body.nav-icons #sidebar .sidebar-link i {
            margin-right: 0 !important;
            font-size: 18px;
            width: 20px;
            text-align: center;
        }
        
        body.nav-icons #leadsMenuIcon,
        body.nav-icons #projectsMenuIcon,
        body.nav-icons #advisorProfilesMenuIcon,
        body.nav-icons #sidebar .menu-chevron {
            display: none !important;
        }
        
        body.nav-icons #leadsSubMenu,
        body.nav-icons #projectsSubMenu,
        body.nav-icons #advisorProfilesSubMenu {
            padding-left: 0 !important;
        }

        /* Keep main content aligned with sidebar width - prevent sidebar overlap */
        #mainContent {
            margin-left: 256px;
            width: auto;
            max-width: none;
            min-width: 0;
            transition: margin-left 0.3s ease-in-out;
        }
        body.nav-icons #mainContent {
            margin-left: 64px !important;
        }
        body.nav-text #sidebar {
            width: 256px !important;
            min-width: 256px !important;
            max-width: 256px !important;
        }
        body.nav-text #mainContent {
            margin-left: 256px !important;
        }
        body.nav-text #mainContent,
        body.nav-text div#mainContent,
        html body.nav-text #mainContent {
            margin-left: 256px !important;
        }
        body.nav-text #mainContent, html.pre-nav-text #mainContent {
            margin-left: 256px !important;
        }
        /* Failsafe: also support mode via sidebar classes */
        #sidebar.sidebar-icons {
            width: 64px !important;
            min-width: 64px !important;
            max-width: 64px !important;
        }
        #sidebar.sidebar-icons nav {
            padding: 0 12px !important;
        }
        #sidebar.sidebar-icons h2,
        #sidebar.sidebar-icons p {
            display: none !important;
        }
        #sidebar.sidebar-icons .sidebar-link {
            justify-content: center !important;
            padding: 12px !important;
            font-size: 0 !important;
        }
        #sidebar.sidebar-icons .sidebar-link i {
            margin-right: 0 !important;
            font-size: 18px;
            width: 20px;
            text-align: center;
        }
        #sidebar.sidebar-icons #leadsMenuIcon,
        #sidebar.sidebar-icons #projectsMenuIcon,
        #sidebar.sidebar-icons #advisorProfilesMenuIcon,
        #sidebar.sidebar-icons .menu-chevron {
            display: none !important;
        }
        #sidebar.sidebar-text {
            width: 256px !important;
            min-width: 256px !important;
            max-width: 256px !important;
        }
        #sidebar.sidebar-text nav {
            padding: 0 20px !important;
        }
        #sidebar.sidebar-text h2,
        #sidebar.sidebar-text p {
            display: block !important;
        }
        #sidebar.sidebar-text .sidebar-link {
            justify-content: flex-start !important;
            padding: 12px 16px !important;
            font-size: 14px !important;
        }
        #sidebar.sidebar-text .sidebar-link i {
            margin-right: 10px !important;
            font-size: 14px !important;
            width: 20px !important;
        }
        #sidebar.sidebar-text #leadsMenuIcon,
        #sidebar.sidebar-text #projectsMenuIcon,
        #sidebar.sidebar-text #advisorProfilesMenuIcon {
            display: inline-block !important;
        }
        /* Final mode guards: body mode always wins, prevents mixed icon/text state */
        body.nav-text #sidebar {
            width: 256px !important;
            min-width: 256px !important;
            max-width: 256px !important;
        }
        body.nav-text #sidebar nav {
            padding: 0 20px !important;
        }
        body.nav-text #sidebar h2,
        body.nav-text #sidebar p {
            display: block !important;
        }
        body.nav-text #sidebar .sidebar-link {
            justify-content: flex-start !important;
            padding: 12px 16px !important;
            font-size: 14px !important;
        }
        body.nav-text #sidebar .sidebar-link i {
            margin-right: 10px !important;
            font-size: 14px !important;
            width: 20px !important;
            text-align: left !important;
        }
        body.nav-text #leadsMenuIcon,
        body.nav-text #projectsMenuIcon,
        body.nav-text #advisorProfilesMenuIcon {
            display: inline-block !important;
        }
        body.nav-icons #sidebar {
            width: 64px !important;
            min-width: 64px !important;
            max-width: 64px !important;
        }
        body.sidebar-hidden #mainContent {
            margin-left: 0 !important;
            width: auto !important;
        }
        .sidebar-link:hover {
            background: #ECFDF3 !important;
            color: #205A44 !important;
        }
        .sidebar-link.active {
            background: #0f7a4f !important;
            color: #ffffff !important;
            font-weight: 500 !important;
        }
        .sidebar-link.active i,
        .sidebar-link.active span,
        .sidebar-link.active .menu-chevron {
            color: #ffffff !important;
        }
        .sidebar-parent-open {
            background: #0f7a4f !important;
            color: #ffffff !important;
        }
        .sidebar-parent-open i,
        .sidebar-parent-open span,
        .sidebar-parent-open .menu-chevron {
            color: #ffffff !important;
        }
        .sidebar-toggle {
            position: fixed;
            top: 26px;
            left: calc(var(--nav-width) - 16px);
            z-index: 55;
            background: #ffffff;
            color: var(--primary-color);
            border: 1px solid rgba(0, 107, 166, 0.16);
            border-radius: 999px;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12);
            transition: all 0.3s;
        }
        .sidebar-toggle:hover {
            background: #e0f2fe;
            transform: translateY(-1px);
        }
        
        /* Custom CSS from branding settings */
        {!! branded_css() !!}
        aside.sidebar-hidden {
            transform: translateX(-100%);
        }
        aside.sidebar-hidden ~ div {
            margin-left: 0 !important;
        }
        .sidebar-toggle-icon {
            font-size: 12px;
            transition: transform 0.3s;
        }
        body.sidebar-hidden #sidebar {
            transform: translateX(calc(-1 * var(--nav-width)));
            pointer-events: none;
        }
        body.sidebar-hidden .sidebar-toggle {
            position: fixed;
            top: 28px;
            left: 12px;
            right: auto;
        }
        .layout-admin #sidebarToggle,
        .layout-crm #sidebarToggle {
            display: inline-flex;
        }
        @media (max-width: 768px) {
            .container { margin-left: 0; padding: 10px; }
            aside.sidebar-hidden ~ div {
                margin-left: 0 !important;
            }
            .sidebar-toggle {
                display: none !important;
            }
        }
        
        /* Mobile: Hide Sidebar, Show Bottom Nav */
        @media (max-width: 767px) {
            #sidebar {
                display: none !important;
                width: 0 !important;
                height: 0 !important;
                position: absolute !important;
                left: -9999px !important;
                visibility: hidden !important;
            }
            #sidebarToggle {
                display: none !important;
            }
            #navModeToggle {
                display: none !important;
            }
            #mainContent {
                margin-left: 0 !important;
                width: auto !important;
                max-width: none !important;
                min-width: 0 !important;
                overflow-x: hidden !important;
                padding-bottom: 70px !important;
            }
            #mainContent .container {
                max-width: 100% !important;
                width: 100% !important;
                padding-left: 12px !important;
                padding-right: 12px !important;
            }
            #mobileFooterNav {
                display: flex !important;
                position: fixed !important;
                left: 0 !important;
                right: 0 !important;
                bottom: 0 !important;
                width: 100% !important;
                height: calc(66px + env(safe-area-inset-bottom, 0px)) !important;
                padding: 7px 6px calc(7px + env(safe-area-inset-bottom, 0px)) !important;
                border-radius: 0 !important;
                border-top: 1px solid #dce8e1 !important;
                background: rgba(255,255,255,.98) !important;
                box-shadow: 0 -8px 24px rgba(6,58,28,.12) !important;
                z-index: 1200 !important;
            }
            /* ADMIN mobile: compact header */
            .layout-admin .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
                padding: 12px !important;
            }
            .layout-admin .header > div:first-child h1 {
                font-size: 20px !important;
            }
            .layout-admin .header > div:first-child p {
                font-size: 12px !important;
            }
            .layout-admin .header > div:last-child {
                width: 100%;
                flex-wrap: wrap;
                gap: 8px;
                justify-content: flex-start;
            }
            .layout-admin.page-users-index .header > div:last-child {
                display: grid;
                grid-template-columns: auto minmax(0, 1fr);
                align-items: start;
            }
            .layout-admin.page-users-index .header > div:last-child .mobile-nav-toggle {
                grid-row: 1 / span 2;
                align-self: start;
            }
            /* Hide clock, username, logout, navToggle in admin header on mobile */
            .layout-admin .header #datetimeClock,
            .layout-admin .header #navModeToggle,
            .layout-admin .header .header-logout-form {
                display: none !important;
            }
            .layout-admin .header span[style*="color: #B3B5B4"] {
                display: none !important;
            }
            /* Make action buttons smaller on mobile */
            .layout-admin .header .btn {
                padding: 7px 12px !important;
                font-size: 13px !important;
            }

            /* CRM: hide header Logout on mobile (same as telecaller; use Profile/Logout in footer) */
            .layout-crm .header .header-logout-form {
                display: none !important;
            }
            /* CRM phone: compact header – hide title and label, only clock + date range */
            .layout-crm .header {
                flex-wrap: wrap;
                align-items: center;
                padding: 10px 12px !important;
                min-height: 50px;
            }
            .layout-crm .header > div:first-child {
                padding: 0;
                margin: 0;
                margin-top: 0 !important;
                flex: 1 1 auto;
                min-width: 0;
            }
            .layout-crm .header h1 {
                display: none !important;
            }
            .layout-crm .header .form-label[for="date-range-filter"],
            .layout-crm .header label[for="date-range-filter"] {
                display: none !important;
            }
            .layout-crm .header [style*="margin-top: 8px"] {
                margin-top: 0 !important;
            }
            .layout-crm .header #date-range-filter {
                max-width: 120px;
                padding: 4px 8px;
                font-size: 13px;
                height: 36px;
            }
            .layout-crm .header #datetimeClock {
                padding: 6px 10px;
                min-width: 120px;
            }
            .layout-crm .header #datetimeClock #clockTime {
                font-size: 14px;
            }
            .layout-crm .header #datetimeClock #clockDate {
                font-size: 10px;
            }
        }
        
        /* Mobile Bottom Navigation Bar */
        #mobileFooterNav {
            display: none;
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
        /* Admin mobile nav: 5 visible (20% each), 4 on scroll; smooth swipe, hidden scrollbar, scroll hint */
        #mobileFooterNav.admin-mobile-nav {
            flex-wrap: nowrap;
            overflow-x: auto;
            overflow-y: hidden;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            -ms-overflow-style: none;
            justify-content: flex-start;
        }
        #mobileFooterNav.admin-mobile-nav::-webkit-scrollbar {
            display: none;
        }
        #mobileFooterNav.admin-mobile-nav .footer-nav-link {
            flex: 0 0 20%;
            min-width: 20%;
            max-width: 20%;
            scroll-snap-align: start;
        }
        #mobileFooterNav.admin-mobile-nav {
            scroll-snap-type: x mandatory;
        }
        /* CRM mobile nav: same scroll UX as admin (5 visible at 20%, rest on scroll) */
        #mobileFooterNav.crm-mobile-nav {
            flex-wrap: nowrap;
            overflow-x: auto;
            overflow-y: hidden;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            -ms-overflow-style: none;
            justify-content: flex-start;
            scroll-snap-type: x mandatory;
        }
        #mobileFooterNav.crm-mobile-nav::-webkit-scrollbar {
            display: none;
        }
        #mobileFooterNav.crm-mobile-nav .footer-nav-link {
            flex: 0 0 20%;
            min-width: 20%;
            max-width: 20%;
            scroll-snap-align: start;
        }
        /* Scroll hint: gradient overlay on right edge so user knows more items on scroll */
        .admin-mobile-nav-scroll-hint {
            position: fixed;
            bottom: 0;
            right: 0;
            width: 32px;
            height: 60px;
            background: linear-gradient(to right, transparent, rgba(255,255,255,0.95) 70%);
            pointer-events: none;
            z-index: 1001;
        }
        @media (min-width: 768px) {
            .admin-mobile-nav-scroll-hint { display: none !important; }
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
            max-width: 72px;
            min-height: 44px;
        }
        .footer-nav-link i {
            font-size: 18px;
            margin-bottom: 2px;
        }
        .footer-nav-link span {
            font-size: 9px;
            color: #666;
            text-align: center;
            line-height: 1.2;
        }
        .footer-nav-link:hover,
        .footer-nav-link.active {
            background: #F7F6F3;
            color: var(--text-color);
        }
        .footer-nav-link.active span {
            color: var(--text-color);
        }
        @media (min-width: 768px) {
            #mobileFooterNav {
                display: none !important;
            }
        }

        /* Sidebar Tooltip Styles */
        .sidebar-tooltip {
            position: fixed;
            background: white;
            padding: 8px 12px;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            z-index: 1000;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.2s ease-in-out;
            font-size: 14px;
            color: #333;
            white-space: nowrap;
            font-weight: 500;
        }
        
        .sidebar-tooltip.show {
            opacity: 1;
        }
        
        .tooltip-arrow {
            position: absolute;
            left: -6px;
            top: 50%;
            transform: translateY(-50%);
            width: 0;
            height: 0;
            border-top: 6px solid transparent;
            border-bottom: 6px solid transparent;
            border-right: 6px solid white;
        }

        :root {
            --nav-shell: #062D1F;
            --nav-shell-border: rgba(255,255,255,.08);
            --nav-text: rgba(255,255,255,.72);
            --nav-text-muted: rgba(255,255,255,.35);
            --nav-pill: rgba(34,197,94,.14);
            --nav-active: #0f7a4f;
            --nav-active-text: #ffffff;
            --nav-accent: #6EE7B7;
            --nav-width: 248px;
        }

        html.pre-nav-text #sidebar,
        body #sidebar {
            width: var(--nav-width) !important;
            min-width: var(--nav-width) !important;
            max-width: var(--nav-width) !important;
            background: var(--nav-shell) !important;
            border-right: 1px solid var(--nav-shell-border) !important;
            box-shadow: none !important;
            color: #fff;
            overflow: hidden !important;
        }

        html.pre-nav-text #mainContent,
        body #mainContent {
            margin-left: var(--nav-width) !important;
        }

        body.nav-icons #sidebar,
        body.nav-text #sidebar,
        #sidebar.sidebar-icons,
        #sidebar.sidebar-text {
            width: var(--nav-width) !important;
            min-width: var(--nav-width) !important;
            max-width: var(--nav-width) !important;
        }

        body.nav-icons #mainContent,
        body.nav-text #mainContent {
            margin-left: var(--nav-width) !important;
        }

        body.sidebar-hidden #mainContent,
        .layout-admin.sidebar-hidden #mainContent,
        .layout-hr-manager.sidebar-hidden #mainContent {
            margin-left: 0 !important;
        }

        body.sidebar-hidden #sidebar,
        .layout-hr-manager.sidebar-hidden #sidebar {
            transform: translateX(calc(-1 * var(--nav-width))) !important;
            pointer-events: none;
        }

        body.sidebar-hidden #sidebarToggle {
            left: 12px !important;
        }

        .app-shell {
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        .app-sidebar {
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            z-index: 45;
        }

        .sidebar-brand {
            position: relative;
            padding: 20px 18px 14px;
            border-bottom: 1px solid var(--nav-shell-border);
        }

        .layout-admin .sidebar-brand {
            padding-right: 54px;
        }

        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: 'Fraunces', 'Poppins', serif;
            font-size: 18px;
            font-weight: 700;
            color: #fff;
        }

        .sidebar-logo-mark {
            width: 32px;
            height: 32px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            border: 1px solid rgba(255,255,255,.35);
            overflow: hidden;
            padding: 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,.18);
        }

        .sidebar-logo-mark img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }

        .sidebar-role {
            margin-top: 6px;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--nav-text-muted);
        }

        .sidebar-scroll {
            flex: 1;
            overflow-y: auto;
            padding: 10px 8px 14px;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .sidebar-scroll::-webkit-scrollbar {
            width: 0;
            height: 0;
            display: none;
        }

        .nav-section-label {
            padding: 12px 10px 6px;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: .11em;
            text-transform: uppercase;
            color: rgba(255,255,255,.24);
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            padding: 10px 12px !important;
            margin-bottom: 2px;
            border-radius: 10px;
            text-decoration: none;
            color: var(--nav-text) !important;
            font-size: 12px !important;
            font-weight: 500;
            background: transparent;
            transition: all .18s ease;
            min-height: 40px;
            white-space: nowrap;
        }

        button.sidebar-link {
            border: 0;
            text-align: left;
            font-family: inherit;
        }

        .sidebar-link span {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .sidebar-link i {
            width: 16px !important;
            margin-right: 0 !important;
            text-align: center;
            color: rgba(255,255,255,.48);
            font-size: 14px !important;
            flex: 0 0 16px;
        }

        .sidebar-link:hover {
            background: var(--nav-pill) !important;
            color: rgba(255,255,255,.92) !important;
        }

        .sidebar-link:hover i,
        .sidebar-link.active i,
        .sidebar-link.sidebar-parent-open i:first-child {
            color: var(--nav-accent);
        }

        .sidebar-link.active,
        .sidebar-link.sidebar-parent-open {
            background: var(--nav-active) !important;
            color: var(--nav-active-text) !important;
            font-weight: 600 !important;
        }

        .sidebar-submenu {
            margin: 2px 0 8px;
            padding-left: 10px !important;
        }

        .sidebar-submenu .sidebar-link {
            padding: 8px 12px !important;
            min-height: 36px;
            font-size: 11.5px !important;
            color: rgba(255,255,255,.62) !important;
        }

        .sidebar-submenu .sidebar-link i {
            font-size: 12px !important;
        }

        .sidebar-link .menu-chevron {
            margin-left: auto;
            color: rgba(255,255,255,.36);
            font-size: 11px !important;
            transition: transform .18s ease;
            flex: 0 0 auto;
        }

        .sidebar-link .menu-badge {
            margin-left: auto;
            padding: 2px 7px;
            border-radius: 999px;
            background: var(--gradient-start);
            color: #fff;
            font-size: 9px;
            font-weight: 700;
        }

        .hr-nav-code {
            width: 22px;
            height: 22px;
            margin-right: 10px;
            border-radius: 7px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,.08);
            color: rgba(255,255,255,.78);
            font-size: 10px;
            font-weight: 900;
            letter-spacing: 0;
            flex: 0 0 22px;
        }

        .sidebar-link.active .hr-nav-code,
        .sidebar-link.sidebar-parent-open .hr-nav-code {
            background: var(--nav-accent);
            color: #062f1d;
        }

        .footer-nav-code {
            width: 22px;
            height: 22px;
            border-radius: 7px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #edf5ef;
            color: #0b4a30;
            font-size: 9px;
            font-weight: 900;
        }

        .footer-nav-link.active .footer-nav-code {
            background: #0b4a30;
            color: #fff;
        }

        .sidebar-footer {
            padding: 14px 16px 16px;
            border-top: 1px solid var(--nav-shell-border);
            background: rgba(255,255,255,.02);
        }

        .sidebar-user-card {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-user-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            flex-shrink: 0;
        }

        .sidebar-user-name {
            font-size: 12px;
            font-weight: 600;
            color: #fff;
        }

        .sidebar-user-role {
            margin-top: 2px;
            font-size: 10px;
            color: var(--nav-text-muted);
        }

        .main-header {
            background: rgba(255,255,255,.82);
            backdrop-filter: blur(14px);
            border: 1px solid #E2E1DC;
            border-radius: 18px;
            box-shadow: 0 1px 3px rgba(0,0,0,.04);
            padding: 18px 20px;
        }

        .mobile-nav-toggle {
            display: none;
            width: 38px;
            height: 38px;
            border: 1px solid #E2E1DC;
            border-radius: 12px;
            background: #fff;
            color: #16161A;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .mobile-nav-toggle i {
            font-size: 15px;
        }

        #sidebarOverlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.42);
            z-index: 40;
        }

        #mobileFooterNav {
            background: rgba(255,255,255,.92);
            backdrop-filter: blur(14px);
            border-top: 1px solid #E2E1DC;
            box-shadow: 0 -6px 20px rgba(0,0,0,.08);
            padding: 8px 6px;
            height: 66px;
        }

        .footer-nav-link {
            border-radius: 12px;
            color: #6A6A63;
            max-width: none;
        }

        .footer-nav-link i {
            font-size: 16px;
            margin-bottom: 4px;
        }

        .footer-nav-link span {
            font-size: 9px;
            font-weight: 600;
            color: inherit;
        }

        .footer-nav-link:hover,
        .footer-nav-link.active {
            background: #F0EFEC;
            color: #0B6B4F;
        }

        @media (max-width: 820px) {
            :root {
                --nav-width: 0px !important;
            }
            .app-shell {
                display: block !important;
                width: 100% !important;
                max-width: 100% !important;
            }
            html.pre-nav-text #mainContent,
            html.pre-nav-icons #mainContent,
            body #mainContent,
            body.nav-icons #mainContent,
            body.nav-text #mainContent,
            #mainContent[style] {
                margin-left: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                min-width: 0 !important;
            }

            body.sidebar-hidden #sidebar {
                transform: translateX(-100%);
            }

            body.sidebar-hidden #mainContent {
                margin-left: 0 !important;
            }

            #sidebar {
                display: flex !important;
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                bottom: 0 !important;
                height: 100dvh !important;
                visibility: visible !important;
                width: min(86vw, 308px) !important;
                min-width: min(86vw, 308px) !important;
                max-width: min(86vw, 308px) !important;
                flex: none !important;
                transform: translateX(-100%);
                transition: transform .25s ease;
            }

            body.mobile-sidebar-open #sidebar {
                transform: translateX(0);
            }

            #mainContent {
                display: block !important;
                width: 100% !important;
                max-width: 100% !important;
                min-width: 0 !important;
                flex: 1 1 100% !important;
                margin-left: 0 !important;
                left: 0 !important;
                padding-bottom: calc(76px + env(safe-area-inset-bottom)) !important;
            }

            #mainContent .container {
                width: 100% !important;
                max-width: 100% !important;
                min-width: 0 !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
                padding-left: 12px !important;
                padding-right: 12px !important;
            }

            #mobileFooterNav {
                display: flex !important;
            }

            #sidebarOverlay {
                display: block;
                opacity: 0;
                pointer-events: none;
                transition: opacity .2s ease;
            }

            body.mobile-sidebar-open #sidebarOverlay {
                opacity: 1;
                pointer-events: auto;
            }

            .mobile-nav-toggle {
                display: inline-flex;
            }

            .main-header {
                padding: 14px 16px;
                border-radius: 16px;
            }

            .layout-admin .header,
            .layout-crm .header {
                gap: 10px;
                align-items: flex-start;
            }

            .layout-admin .header > div:last-child,
            .layout-crm .header > div:last-child {
                width: 100%;
                justify-content: flex-start;
                flex-wrap: wrap;
            }
        }
        
    </style>
    
    <style>
        .layout-crm .page-shell {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }
        .layout-crm .crm-hero {
            position: relative;
            overflow: hidden;
            border-radius: 30px;
            border: 1px solid rgba(6, 58, 28, 0.1);
            background:
                radial-gradient(circle at top left, rgba(191, 230, 216, 0.9), transparent 38%),
                radial-gradient(circle at top right, rgba(237, 245, 225, 0.95), transparent 42%),
                linear-gradient(135deg, #ffffff 0%, #f7fbf8 60%, #eef7f2 100%);
            box-shadow: 0 22px 50px rgba(15, 23, 42, 0.08);
            padding: 28px;
        }
        .layout-crm .crm-hero-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.8fr) minmax(320px, 0.9fr);
            gap: 24px;
            align-items: end;
        }
        .layout-crm .crm-kicker {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 999px;
            background: rgba(16, 122, 84, 0.11);
            color: #0f7a54;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }
        .layout-crm .crm-hero-title {
            margin-top: 18px;
            color: #14213d;
            font-family: Georgia, "Times New Roman", serif;
            font-size: clamp(2rem, 4vw, 3.5rem);
            line-height: 1.03;
            letter-spacing: -0.04em;
        }
        .layout-crm .crm-hero-title strong {
            color: #0f7a54;
        }
        .layout-crm .crm-hero-copy {
            margin-top: 16px;
            max-width: 760px;
            color: #5f6c7b;
            font-size: 16px;
            line-height: 1.8;
        }
        .layout-crm .crm-mini-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }
        .layout-crm .crm-mini-card {
            border-radius: 24px;
            border: 1px solid rgba(6, 58, 28, 0.1);
            background: rgba(255, 255, 255, 0.88);
            padding: 20px;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.06);
        }
        .layout-crm .crm-mini-label {
            color: #7f8b96;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }
        .layout-crm .crm-mini-value {
            margin-top: 10px;
            color: #0f7a54;
            font-size: clamp(1.8rem, 3vw, 2.4rem);
            font-weight: 800;
            line-height: 1;
        }
        .layout-crm .crm-mini-copy {
            margin-top: 8px;
            color: #5f6c7b;
            font-size: 13px;
            line-height: 1.5;
        }
        .layout-crm .crm-surface {
            border-radius: 28px;
            border: 1px solid rgba(6, 58, 28, 0.1);
            background: rgba(255, 255, 255, 0.96);
            box-shadow: 0 18px 44px rgba(15, 23, 42, 0.06);
            padding: 24px;
        }
        .layout-crm .crm-surface + .crm-surface {
            margin-top: 24px;
        }
        .layout-crm .crm-surface-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 18px;
        }
        .layout-crm .crm-section-title {
            color: #14213d;
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -0.03em;
        }
        .layout-crm .crm-section-copy {
            margin-top: 6px;
            color: #6b7280;
            font-size: 14px;
            line-height: 1.7;
        }
        .layout-crm .crm-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 12px;
            border-radius: 999px;
            background: #eff5f1;
            color: #4b5563;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .layout-crm .crm-grid-2 {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 24px;
        }
        .layout-crm .crm-grid-3 {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }
        .layout-crm .crm-grid-4 {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
        }
        .layout-crm .crm-stat-card {
            border-radius: 24px;
            border: 1px solid rgba(6, 58, 28, 0.1);
            background: linear-gradient(180deg, rgba(255,255,255,1), rgba(246,249,247,1));
            padding: 20px;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.05);
        }
        .layout-crm .crm-stat-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .layout-crm .crm-stat-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            border-radius: 16px;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: #fff;
            box-shadow: 0 10px 18px rgba(6, 58, 28, 0.18);
        }
        .layout-crm .crm-stat-value {
            margin-top: 18px;
            color: #14213d;
            font-size: clamp(1.8rem, 2.8vw, 2.45rem);
            font-weight: 800;
            line-height: 1;
        }
        .layout-crm .crm-stat-label {
            margin-top: 8px;
            color: #7f8b96;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }
        .layout-crm .crm-controls {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }
        .layout-crm .crm-control,
        .layout-crm .crm-control-group input,
        .layout-crm .crm-control-group select,
        .layout-crm .crm-control-group textarea,
        .layout-crm .crm-form-control {
            min-height: 46px;
            border-radius: 16px;
            border: 1px solid #d7e0d9;
            background: #fff;
            color: #18202c;
            padding: 0 15px;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.6);
            transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
        }
        .layout-crm .crm-control:focus,
        .layout-crm .crm-control-group input:focus,
        .layout-crm .crm-control-group select:focus,
        .layout-crm .crm-control-group textarea:focus,
        .layout-crm .crm-form-control:focus {
            outline: none;
            border-color: rgba(15, 122, 84, 0.45);
            box-shadow: 0 0 0 4px rgba(15, 122, 84, 0.12);
        }
        .layout-crm .crm-control-group {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }
        .layout-crm .crm-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }
        .layout-crm .crm-field label {
            display: block;
            margin-bottom: 8px;
            color: #344054;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.03em;
        }
        .layout-crm .crm-table-shell {
            border-radius: 24px;
            border: 1px solid rgba(6, 58, 28, 0.09);
            overflow: hidden;
            background: #fbfdfb;
        }
        .layout-crm .crm-table-shell table {
            width: 100%;
            border-collapse: collapse;
        }
        .layout-crm .crm-table-shell th {
            background: #f3f7f4;
            color: #667085;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }
        .layout-crm .crm-table-shell th,
        .layout-crm .crm-table-shell td {
            padding: 14px 16px;
            border-bottom: 1px solid #ebf1ed;
            vertical-align: top;
        }
        .layout-crm .crm-table-shell tr:last-child td {
            border-bottom: none;
        }
        .layout-crm .crm-empty {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 10px;
            min-height: 180px;
            color: #8b95a1;
            text-align: center;
        }
        .layout-crm .crm-empty i {
            font-size: 28px;
            color: #b7c0cb;
        }
        .layout-crm .crm-note {
            border-radius: 22px;
            border: 1px solid rgba(25, 118, 210, 0.12);
            background: linear-gradient(135deg, #eef7ff, #f8fbff);
            padding: 18px 20px;
            color: #365475;
        }
        .layout-crm .crm-note-warning {
            border-color: rgba(245, 158, 11, 0.18);
            background: linear-gradient(135deg, #fff7e6, #fffaf0);
            color: #8a5b00;
        }
        .layout-crm .crm-tabbar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 22px;
        }
        .layout-crm .crm-tabbar .tab {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 16px;
            border-radius: 16px;
            border: 1px solid #dce7df;
            background: #fff;
            color: #5e6977;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 0;
            transition: all 0.2s ease;
        }
        .layout-crm .crm-tabbar .tab.active {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            border-color: transparent;
            color: #fff;
            box-shadow: 0 12px 24px rgba(6, 58, 28, 0.18);
        }
        .layout-crm .crm-badge-soft {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 28px;
            padding: 3px 9px;
            border-radius: 999px;
            background: rgba(255,255,255,0.18);
            font-size: 11px;
            font-weight: 800;
        }
        .layout-crm .crm-list-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }
        .layout-crm .crm-card {
            display: flex;
            flex-direction: column;
            gap: 16px;
            min-height: 100%;
            border-radius: 24px;
            border: 1px solid rgba(6, 58, 28, 0.1);
            background: linear-gradient(180deg, #ffffff, #f8fbf9);
            padding: 20px;
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.05);
        }
        .layout-crm .crm-card h3 {
            color: #17324d;
            font-size: 18px;
            font-weight: 800;
        }
        .layout-crm .crm-card-copy {
            color: #677483;
            font-size: 14px;
            line-height: 1.7;
        }
        .layout-crm .crm-card-divider {
            height: 1px;
            background: #e8efea;
        }
        .layout-crm .crm-inline-stack {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }
        @media (max-width: 1280px) {
            .layout-crm .crm-hero-grid,
            .layout-crm .crm-grid-4,
            .layout-crm .crm-list-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        @media (max-width: 960px) {
            .layout-crm .crm-grid-2,
            .layout-crm .crm-grid-3,
            .layout-crm .crm-grid-4,
            .layout-crm .crm-mini-grid,
            .layout-crm .crm-list-grid,
            .layout-crm .crm-form-grid,
            .layout-crm .crm-hero-grid {
                grid-template-columns: 1fr;
            }
            .layout-crm .crm-surface,
            .layout-crm .crm-hero {
                padding: 20px;
                border-radius: 24px;
            }
            .layout-crm .crm-surface-header {
                flex-direction: column;
            }
        }
    </style>
    @stack('styles')
    {{-- Apply nav mode BEFORE body renders to prevent any flash --}}
    <script>
    (function(){
        try {
            document.documentElement.classList.add('pre-nav-text');
        } catch(e) {}
    })();
    </script>
</head>
<body class="bg-[#F7F6F3] font-sans antialiased @if(auth()->user()->isCrm()) layout-crm @elseif(auth()->user()->isAdmin()) layout-admin @elseif(auth()->user()->isHrManager()) layout-hr-manager @elseif(auth()->user()->isLeadManager()) layout-lead-manager @elseif(auth()->user()->isLeadQualityAuditor()) layout-lead-quality-auditor @endif @if(request()->routeIs('users.index')) page-users-index @endif" style="margin: 0; padding: 0; overflow: hidden;">
@if(session('impersonating_original_id'))
<div style="position:fixed;top:0;left:0;right:0;z-index:99999;background:linear-gradient(135deg,#92400e,#b45309);color:#fff;padding:10px 24px;font-size:13px;font-weight:600;display:flex;align-items:center;justify-content:center;gap:16px;box-shadow:0 3px 12px rgba(0,0,0,0.3);">
    <i class="fas fa-user-secret"></i>
    <span>⚠️ Admin mode: Aap <strong>{{ auth()->user()->name }}</strong> ({{ auth()->user()->getDisplayRoleName() }}) ke roop mein dekh rahe hain</span>
    <a href="/impersonate/stop" style="background:#fff;color:#b45309;border:none;border-radius:8px;padding:6px 16px;font-weight:700;cursor:pointer;font-size:12px;display:flex;align-items:center;gap:6px;text-decoration:none;">
        <i class="fas fa-arrow-left"></i> Wapas Admin
    </a>
</div>
<div style="height:44px;"></div>
@endif
    <script>
    (function(){
        document.body.classList.add('no-transition');
        try {
            document.body.classList.add('nav-text');
        } catch(e) {
            document.body.classList.add('nav-text');
        }
    })();
    </script>
    <div id="sidebarOverlay" onclick="closeMobileSidebar()"></div>
    @if(auth()->user()->isAdmin() || auth()->user()->isCrm() || auth()->user()->isHrManager() || auth()->user()->isLeadManager() || auth()->user()->isLeadQualityAuditor())
    <button type="button" id="sidebarToggle" class="sidebar-toggle" aria-label="Hide navigation" title="Hide navigation">
        <i class="fas fa-chevron-left sidebar-toggle-icon"></i>
    </button>
    @endif

    <div class="app-shell">
        <!-- Sidebar -->
        <aside id="sidebar" class="app-sidebar fixed left-0 top-0 h-full bg-white border-r border-gray-200 shadow-sm z-30">
            <!-- Logo and Role -->
            <div class="sidebar-brand">
                <div class="sidebar-logo">
                    <span class="sidebar-logo-mark" aria-label="{{ brand_name() ?: 'Brickly CRM' }}">{{ strtoupper(substr(brand_name() ?: 'Brickly', 0, 1)) }}</span>
                    <span>{{ brand_name() }}</span>
                </div>
                <div class="sidebar-role">
                    @if(auth()->user()->isAdmin())
                        Admin Dashboard
                    @elseif(auth()->user()->isCrm())
                        CRM Workspace
                    @elseif(auth()->user()->isLeadManager())
                        Lead Manager
                    @elseif(auth()->user()->isLeadQualityAuditor())
                        Lead Quality Auditor
                    @elseif(auth()->user()->isSalesHead())
                        Associate Director
                    @elseif(auth()->user()->isHrManager())
                        HR Hiring
                    @elseif(auth()->user()->isMarketingManager())
                        Marketing Manager
                    @elseif(auth()->user()->isMarketingExecutive())
                        Marketing Executive
                    @elseif(auth()->user()->isSalesManager())
                        Senior Manager
                    @elseif(auth()->user()->isDedicatedTelecaller())
                        Telecaller
                    @elseif(auth()->user()->isTelecaller())
                        Sales Executive
                    @else
                        {{ auth()->user()->getDisplayRoleName() ?? 'User' }}
                    @endif
                </div>
            </div>
            
            <!-- Navigation -->
            <div class="sidebar-scroll">
            <nav>
                @if(auth()->user()->isAdmin())
                @php
                    $adminNavGroups = [
                        [
                            'id' => 'adminNavHome',
                            'label' => 'Home',
                            'icon' => 'fas fa-house',
                            'active' => request()->routeIs('admin.dashboard') || request()->routeIs('data-intelligence.*'),
                            'items' => [
                                ['label' => 'Dashboard', 'route' => route('admin.dashboard'), 'icon' => 'fas fa-gauge-high', 'active' => request()->routeIs('admin.dashboard')],
                                ['label' => 'Data Intelligent', 'route' => route('data-intelligence.index'), 'icon' => 'fas fa-brain', 'active' => request()->routeIs('data-intelligence.*')],
                            ],
                        ],
                        [
                            'id' => 'adminNavCrm',
                            'label' => 'CRM',
                            'icon' => 'fas fa-user-friends',
                            'active' => request()->routeIs('leads.*') || request()->routeIs('admin.lead-board*') || request()->routeIs('admin.other-leads.*') || request()->routeIs('admin.verifications'),
                            'items' => [
                                ['label' => 'All Leads', 'route' => route('leads.index'), 'icon' => 'fas fa-list', 'active' => request()->routeIs('leads.*') && !request()->routeIs('prospects.*') && !request()->routeIs('meetings.*') && !request()->routeIs('site-visits.*') && !request()->routeIs('closers.*')],
                                ['label' => 'Lead Board', 'route' => route('admin.lead-board'), 'icon' => 'fas fa-table-columns', 'active' => request()->routeIs('admin.lead-board*')],
                                ['label' => 'Other Leads', 'route' => route('admin.other-leads.index'), 'icon' => 'fas fa-box-open', 'active' => request()->routeIs('admin.other-leads.*')],
                                ['label' => 'Verifications', 'route' => route('admin.verifications'), 'icon' => 'fas fa-check-circle', 'active' => request()->routeIs('admin.verifications')],
                            ],
                        ],
                        [
                            'id' => 'adminNavLeadControl',
                            'label' => 'Lead Control',
                            'icon' => 'fas fa-sliders',
                            'active' => request()->routeIs('lead-assignment.*') || request()->routeIs('lead-bank.*') || request()->routeIs('lead-import.*') || request()->routeIs('calling-center.*') || request()->routeIs('admin.lead-download-requests.*') || request()->routeIs('export.*'),
                            'items' => [
                                ['label' => 'Lead Assignment', 'route' => route('lead-assignment.index'), 'icon' => 'fas fa-users-cog', 'active' => request()->routeIs('lead-assignment.*') && !request()->routeIs('lead-assignment.calling-tasks.*') && !request()->routeIs('lead-assignment.telecaller-status') && !request()->routeIs('lead-assignment.lead-off-users')],
                                ['label' => 'Calling Center', 'route' => route('calling-center.index'), 'icon' => 'fas fa-headset', 'active' => request()->routeIs('calling-center.*'), 'show' => auth()->user()->canUseCallingCenter('calling_center.view')],
                                ['label' => 'Calling Tasks', 'route' => route('lead-assignment.calling-tasks.index'), 'icon' => 'fas fa-phone-volume', 'active' => request()->routeIs('lead-assignment.calling-tasks.*')],
                                ['label' => 'Lead Off', 'route' => route('lead-assignment.telecaller-status'), 'icon' => 'fas fa-user-slash', 'active' => request()->routeIs('lead-assignment.telecaller-status') || request()->routeIs('lead-assignment.lead-off-users')],
                                ['label' => 'Lead Bank', 'route' => route('lead-bank.index'), 'icon' => 'fas fa-boxes-stacked', 'active' => request()->routeIs('lead-bank.*') && !request()->routeIs('lead-bank.requests.*') && !request()->routeIs('lead-bank.analytics')],
                                ['label' => 'Lead Requests', 'route' => route('lead-bank.requests.index'), 'icon' => 'fas fa-clipboard-list', 'active' => request()->routeIs('lead-bank.requests.*')],
                                ['label' => 'Lead Analytics', 'route' => route('lead-bank.analytics'), 'icon' => 'fas fa-chart-line', 'active' => request()->routeIs('lead-bank.analytics')],
                                ['label' => 'Lead Import', 'route' => route('lead-import.index'), 'icon' => 'fas fa-cloud-upload-alt', 'active' => request()->routeIs('lead-import.*')],
                                ['label' => 'Lead Downloads', 'route' => route('admin.lead-download-requests.index'), 'icon' => 'fas fa-file-arrow-down', 'active' => request()->routeIs('admin.lead-download-requests.*')],
                                ['label' => 'Export', 'route' => route('export.index'), 'icon' => 'fas fa-download', 'active' => request()->routeIs('export.*')],
                            ],
                        ],
                        [
                            'id' => 'adminNavInternalReports',
                            'label' => 'Internal Reports',
                            'icon' => 'fas fa-table',
                            'active' => request()->routeIs('admin.insight-sheet.*'),
                            'items' => [
                                ['label' => 'Insight Sheet', 'route' => route('admin.insight-sheet.index'), 'icon' => 'fas fa-table-cells-large', 'active' => request()->routeIs('admin.insight-sheet.*'), 'show' => auth()->user()->isAdmin() || (method_exists(auth()->user(), 'hasRolePermission') ? auth()->user()->hasRolePermission('insight_sheet.view') : in_array('insight_sheet.view', auth()->user()->role?->permissions ?? [], true))],
                            ],
                        ],
                        [
                            'id' => 'adminNavMarketing',
                            'label' => 'Marketing',
                            'icon' => 'fas fa-bullhorn',
                            'active' => request()->routeIs('crm.meta-review') || request()->routeIs('crm.meta-lead-check.*') || request()->routeIs('whatsapp-control-center.*') || request()->routeIs('chat.*') || request()->routeIs('integrations.meta-waba.*') || request()->routeIs('admin.whatsapp-automation.*') || request()->routeIs('crm.whatsapp-automation.*') || request()->routeIs('admin.instagram-automation.*') || request()->routeIs('crm.instagram-automation.*') || request()->routeIs('calls.*'),
                            'items' => [
                                ['label' => 'Meta Review', 'route' => Route::has('crm.meta-review') ? route('crm.meta-review') : '#', 'icon' => 'fab fa-facebook', 'active' => request()->routeIs('crm.meta-review'), 'show' => Route::has('crm.meta-review')],
                                ['label' => 'Meta Lead Check', 'route' => Route::has('crm.meta-lead-check.index') ? route('crm.meta-lead-check.index') : '#', 'icon' => 'fas fa-magnifying-glass-chart', 'active' => request()->routeIs('crm.meta-lead-check.*'), 'show' => Route::has('crm.meta-lead-check.index')],
                                ['label' => 'WhatsApp Control', 'route' => Route::has('whatsapp-control-center.index') ? route('whatsapp-control-center.index') : '#', 'icon' => 'fab fa-whatsapp', 'active' => request()->routeIs('whatsapp-control-center.*') || request()->routeIs('integrations.meta-waba.*'), 'show' => Route::has('whatsapp-control-center.index')],
                                ['label' => 'WhatsApp Chat', 'route' => Route::has('chat.index') ? route('chat.index') : '#', 'icon' => 'fab fa-whatsapp', 'active' => request()->routeIs('chat.*'), 'show' => Route::has('chat.index')],
                                ['label' => 'WhatsApp Automation', 'route' => Route::has('admin.whatsapp-automation.index') ? route('admin.whatsapp-automation.index') : '#', 'icon' => 'fas fa-bolt', 'active' => request()->routeIs('admin.whatsapp-automation.*') || request()->routeIs('crm.whatsapp-automation.*'), 'show' => Route::has('admin.whatsapp-automation.index')],
                                ['label' => 'Instagram Automation', 'route' => Route::has('admin.instagram-automation.index') ? route('admin.instagram-automation.index') : '#', 'icon' => 'fab fa-instagram', 'active' => request()->routeIs('admin.instagram-automation.*') || request()->routeIs('crm.instagram-automation.*'), 'show' => Route::has('admin.instagram-automation.index')],
                                ['label' => 'All Calls', 'route' => Route::has('calls.index') ? route('calls.index') : '#', 'icon' => 'fas fa-phone', 'active' => request()->routeIs('calls.*'), 'show' => Route::has('calls.index')],
                            ],
                        ],
                        [
                            'id' => 'adminNavProjects',
                            'label' => 'Projects',
                            'icon' => 'fas fa-building',
                            'active' => request()->routeIs('projects.*') || request()->routeIs('builders.*') || request()->routeIs('admin.advisor-profiles.*') || request()->routeIs('admin.builder-logos.*') || request()->routeIs('admin.loan-partners.*'),
                            'items' => [
                                ['label' => 'Projects', 'route' => route('projects.index'), 'icon' => 'fas fa-project-diagram', 'active' => request()->routeIs('projects.*') && !request()->routeIs('builders.*')],
                                ['label' => 'Builders', 'route' => route('builders.index'), 'icon' => 'fas fa-building', 'active' => request()->routeIs('builders.*')],
                                ['label' => 'Advisor Profiles', 'route' => route('admin.advisor-profiles.index'), 'icon' => 'fas fa-id-card', 'active' => request()->routeIs('admin.advisor-profiles.*')],
                                ['label' => 'Builder Logos', 'route' => route('admin.builder-logos.index'), 'icon' => 'fas fa-image', 'active' => request()->routeIs('admin.builder-logos.*')],
                                ['label' => 'Loan Partners', 'route' => route('admin.loan-partners.index'), 'icon' => 'fas fa-building-columns', 'active' => request()->routeIs('admin.loan-partners.*')],
                            ],
                        ],
                        [
                            'id' => 'adminNavHrPeople',
                            'label' => 'HR / People',
                            'icon' => 'fas fa-users',
                            'active' => request()->routeIs('users.*') || request()->routeIs('admin.attendance.*') || request()->routeIs('admin.hr.employees.*') || request()->routeIs('admin.hr.reports.*') || request()->routeIs('admin.payroll-approvals.*'),
                            'items' => [
                                ['label' => 'All Users', 'route' => route('users.index'), 'icon' => 'fas fa-users', 'active' => request()->routeIs('users.*')],
                                ['label' => 'HR Attendance Desk', 'route' => route('admin.attendance.leaves.index'), 'icon' => 'fas fa-user-clock', 'active' => request()->routeIs('admin.attendance.*')],
                                ['label' => 'Employee / HR Setup', 'route' => route('admin.hr.employees.index'), 'icon' => 'fas fa-id-badge', 'active' => request()->routeIs('admin.hr.employees.*')],
                                ['label' => 'HR Reports / Payroll Exports', 'route' => route('admin.hr.reports.index'), 'icon' => 'fas fa-file-export', 'active' => request()->routeIs('admin.hr.reports.*')],
                                ['label' => 'Payroll Approvals', 'route' => route('admin.payroll-approvals.index'), 'icon' => 'fas fa-money-check-dollar', 'active' => request()->routeIs('admin.payroll-approvals.*')],
                            ],
                        ],
                        [
                            'id' => 'adminNavFinance',
                            'label' => 'Finance',
                            'icon' => 'fas fa-receipt',
                            'active' => request()->routeIs('admin.expenses.*') || request()->routeIs('admin.purchase-orders.*') || request()->routeIs('post-sales.*'),
                            'items' => [
                                ['label' => 'Expenses', 'route' => route('admin.expenses.entries.index'), 'icon' => 'fas fa-receipt', 'active' => request()->routeIs('admin.expenses.*')],
                                ['label' => 'Purchase Orders', 'route' => route('admin.purchase-orders.index'), 'icon' => 'fas fa-file-signature', 'active' => request()->routeIs('admin.purchase-orders.*')],
                                ['label' => 'Post Sales & Builder Desk', 'route' => route('post-sales.index'), 'icon' => 'fas fa-table-list', 'active' => request()->routeIs('post-sales.*')],
                            ],
                        ],
                        [
                            'id' => 'adminNavOperations',
                            'label' => 'Operations',
                            'icon' => 'fas fa-list-check',
                            'active' => request()->routeIs('execution-desk.*') || request()->routeIs('admin.broadcast') || request()->routeIs('admin.mail-center.*') || request()->routeIs('knowledge-base.*') || request()->routeIs('admin.knowledge-base.*') || request()->routeIs('admin.forms.*'),
                            'items' => [
                                ['label' => 'Execution Desk', 'route' => route('execution-desk.index'), 'icon' => 'fas fa-list-check', 'active' => request()->routeIs('execution-desk.*')],
                                ['label' => 'Announcements', 'route' => route('admin.broadcast'), 'icon' => 'fas fa-bullhorn', 'active' => request()->routeIs('admin.broadcast')],
                                ['label' => 'Mail Center', 'route' => route('admin.mail-center.index'), 'icon' => 'fas fa-envelope-open-text', 'active' => request()->routeIs('admin.mail-center.*')],
                                ['label' => 'Knowledge Base', 'route' => route('knowledge-base.index'), 'icon' => 'fas fa-book-open-reader', 'active' => request()->routeIs('knowledge-base.*') || request()->routeIs('admin.knowledge-base.*')],
                                ['label' => 'Forms', 'route' => route('admin.forms.index'), 'icon' => 'fas fa-wpforms', 'active' => request()->routeIs('admin.forms.*')],
                            ],
                        ],
                        [
                            'id' => 'adminNavSetup',
                            'label' => 'Setup',
                            'icon' => 'fas fa-gear',
                            'active' => request()->routeIs('admin.targets.*') || request()->routeIs('crm.targets.*') || request()->routeIs('admin.company-settings.*') || request()->routeIs('admin.verification-routing.*') || request()->routeIs('admin.profile'),
                            'items' => [
                                ['label' => 'Target Setting', 'route' => route('admin.targets.index'), 'icon' => 'fas fa-bullseye', 'active' => request()->routeIs('admin.targets.*') || request()->routeIs('crm.targets.*')],
                                ['label' => 'Company Settings', 'route' => route('admin.company-settings.index'), 'icon' => 'fas fa-cog', 'active' => request()->routeIs('admin.company-settings.*')],
                                ['label' => 'Verification Routing', 'route' => route('admin.verification-routing.index'), 'icon' => 'fas fa-route', 'active' => request()->routeIs('admin.verification-routing.*')],
                                ['label' => 'Profile', 'route' => route('admin.profile'), 'icon' => 'fas fa-user', 'active' => request()->routeIs('admin.profile')],
                            ],
                        ],
                        [
                            'id' => 'adminNavTechnical',
                            'label' => 'Support',
                            'icon' => 'fas fa-screwdriver-wrench',
                            'active' => request()->routeIs('admin.support.*') || request()->routeIs('admin.phone-privacy.*') || request()->routeIs('admin.login-security.*') || request()->routeIs('admin.desktop-issue-reports.*') || request()->routeIs('admin.system-settings.*') || request()->routeIs('admin.extensions.*') || request()->routeIs('admin.mobile-app-update.*') || request()->routeIs('admin.storage-manager.*') || request()->routeIs('integrations.*') || request()->routeIs('admin.deploy.*') || request()->routeIs('admin.lead-audit.*') || request()->routeIs('admin.automation.*') || request()->routeIs('crm.automation.*'),
                            'items' => [
                                ['label' => 'Support', 'route' => route('admin.support.index'), 'icon' => 'fas fa-headset', 'active' => request()->routeIs('admin.support.*'), 'badge' => \App\Models\SupportTicket::where('status', 'open')->count()],
                                ['label' => 'Phone Privacy & Dialer', 'route' => route('admin.phone-privacy.index'), 'icon' => 'fas fa-shield-halved', 'active' => request()->routeIs('admin.phone-privacy.*')],
                                ['label' => 'Login Security', 'route' => route('admin.login-security.index'), 'icon' => 'fas fa-shield-halved', 'active' => request()->routeIs('admin.login-security.*'), 'badge' => \App\Models\LoginSecurityEvent::where('event_type', \App\Models\LoginSecurityEvent::TYPE_ACCESS_REQUEST)->where('status', 'pending')->count()],
                                ['label' => 'Desktop Reports', 'route' => route('admin.desktop-issue-reports.index'), 'icon' => 'fas fa-desktop', 'active' => request()->routeIs('admin.desktop-issue-reports.*'), 'badge' => \App\Models\DesktopIssueReport::where('status', 'open')->count()],
                                ['label' => 'Lead Detail Checker', 'route' => route('admin.lead-audit.index'), 'icon' => 'fas fa-magnifying-glass-chart', 'active' => request()->routeIs('admin.lead-audit.*')],
                                ['label' => 'Lead Automation', 'route' => route('admin.automation.index'), 'icon' => 'fas fa-magic', 'active' => request()->routeIs('admin.automation.*') || request()->routeIs('crm.automation.*')],
                                ['label' => 'Meta Old Leads', 'route' => route('integrations.facebook-lead-ads.bulk-recovery.index'), 'icon' => 'fas fa-cloud-arrow-down', 'active' => request()->routeIs('integrations.facebook-lead-ads.bulk-recovery.*')],
                                ['label' => 'System Settings', 'route' => route('admin.system-settings.index'), 'icon' => 'fas fa-server', 'active' => request()->routeIs('admin.system-settings.*')],
                                ['label' => 'Extensions', 'route' => route('admin.extensions.index'), 'icon' => 'fas fa-puzzle-piece', 'active' => request()->routeIs('admin.extensions.*')],
                                ['label' => 'App Update', 'route' => route('admin.mobile-app-update.index'), 'icon' => 'fas fa-mobile-screen-button', 'active' => request()->routeIs('admin.mobile-app-update.*')],
                                ['label' => 'Storage Manager', 'route' => route('admin.storage-manager.index'), 'icon' => 'fas fa-hard-drive', 'active' => request()->routeIs('admin.storage-manager.*')],
                                ['label' => 'Integration', 'route' => route('integrations.index'), 'icon' => 'fas fa-plug', 'active' => request()->routeIs('integrations.*')],
                                ['label' => 'Deployment', 'route' => route('admin.deploy.index'), 'icon' => 'fas fa-rocket', 'active' => request()->routeIs('admin.deploy.*')],
                            ],
                        ],
                    ];
                @endphp
                <div class="nav-section-label">Admin Modules</div>
                @foreach($adminNavGroups as $group)
                    <button type="button"
                            class="sidebar-link {{ $group['active'] ? 'sidebar-parent-open' : '' }}"
                            data-admin-menu-trigger="{{ $group['id'] }}"
                            data-tooltip="{{ $group['label'] }}"
                            title="{{ $group['label'] }}"
                            aria-expanded="{{ $group['active'] ? 'true' : 'false' }}"
                            style="cursor: pointer;">
                        <i class="{{ $group['icon'] }}" style="margin-right: 10px; width: 20px;"></i>
                        <span class="nav-text">{{ $group['label'] }}</span>
                        <i class="fas fa-chevron-down menu-chevron" style="{{ $group['active'] ? 'transform: rotate(180deg);' : '' }}"></i>
                    </button>
                    <div id="{{ $group['id'] }}" class="sidebar-submenu admin-tree-submenu" style="display: {{ $group['active'] ? 'block' : 'none' }};">
                        @foreach($group['items'] as $item)
                            @if($item['show'] ?? true)
                            <a href="{{ $item['route'] }}"
                               class="sidebar-link {{ $item['active'] ? 'active' : '' }}"
                               data-tooltip="{{ $item['label'] }}"
                               title="{{ $item['label'] }}">
                                <i class="{{ $item['icon'] }}" style="margin-right: 10px; width: 20px;"></i>
                                <span class="nav-text">{{ $item['label'] }}</span>
                                @if(($item['badge'] ?? 0) > 0)
                                    <span class="menu-badge">{{ $item['badge'] }}</span>
                                @endif
                            </a>
                            @endif
                        @endforeach
                    </div>
                @endforeach
                @elseif(auth()->user()->isJuniorHr())
                <div class="nav-section-label">Main</div>
                <a href="{{ route('junior-hr.dashboard') }}" class="sidebar-link {{ request()->routeIs('junior-hr.dashboard') ? 'active' : '' }}">
                    <i class="fas fa-home" style="margin-right: 10px; width: 20px;"></i>
                    Dashboard
                </a>
                <a href="{{ route('junior-hr.hiring.index') }}" class="sidebar-link {{ request()->routeIs('junior-hr.hiring.*') ? 'active' : '' }}">
                    <i class="fas fa-user-tie" style="margin-right: 10px; width: 20px;"></i>
                    Hiring Leads
                </a>
                <div class="nav-section-label">Attendance</div>
                <a href="{{ route('hr-manager.attendance.sheet') }}" class="sidebar-link {{ request()->routeIs('hr-manager.attendance.sheet') || request()->routeIs('hr-manager.attendance.index') ? 'active' : '' }}">
                    <i class="fas fa-calendar-check" style="margin-right: 10px; width: 20px;"></i>
                    Monthly Attendance
                </a>
                <a href="{{ route('hr-manager.attendance.leaves') }}" class="sidebar-link {{ request()->routeIs('hr-manager.attendance.leaves*') ? 'active' : '' }}">
                    <i class="fas fa-clipboard-list" style="margin-right: 10px; width: 20px;"></i>
                    Leave Requests
                </a>
                <a href="{{ route('hr-manager.attendance.regularizations') }}" class="sidebar-link {{ request()->routeIs('hr-manager.attendance.regularizations*') ? 'active' : '' }}">
                    <i class="fas fa-edit" style="margin-right: 10px; width: 20px;"></i>
                    Regularization
                </a>
                <a href="{{ route('hr-manager.attendance.outside-punches') }}" class="sidebar-link {{ request()->routeIs('hr-manager.attendance.outside-punches*') ? 'active' : '' }}">
                    <i class="fas fa-map-marker-alt" style="margin-right: 10px; width: 20px;"></i>
                    Manual Punch
                </a>
                <div class="nav-section-label">Account</div>
                <a href="{{ route('junior-hr.profile') }}" class="sidebar-link {{ request()->routeIs('junior-hr.profile') ? 'active' : '' }}">
                    <i class="fas fa-user" style="margin-right: 10px; width: 20px;"></i>
                    Profile
                </a>
                @elseif(auth()->user()->isHrManager())
                @php
                    $hrNavGroups = [
                        [
                            'id' => 'hrNavDashboard',
                            'label' => 'Dashboard',
                            'icon' => 'fas fa-gauge-high',
                            'active' => request()->routeIs('hr-manager.dashboard'),
                            'items' => [
                                ['label' => 'Dashboard', 'route' => route('hr-manager.dashboard'), 'code' => 'DW', 'icon' => 'fas fa-gauge-high', 'active' => request()->routeIs('hr-manager.dashboard')],
                            ],
                        ],
                        [
                            'id' => 'hrNavDailyWork',
                            'label' => 'Daily Work',
                            'icon' => 'fas fa-briefcase',
                            'active' => request()->routeIs('hr-manager.attendance.*') || request()->routeIs('hr-manager.verifications'),
                            'items' => [
                                ['label' => 'Monthly Attendance', 'route' => route('hr-manager.attendance.sheet'), 'code' => 'AT', 'icon' => 'fas fa-calendar-check', 'active' => request()->routeIs('hr-manager.attendance.sheet') || request()->routeIs('hr-manager.attendance.index')],
                                ['label' => 'Leave Requests', 'route' => route('hr-manager.attendance.leaves'), 'code' => 'LV', 'icon' => 'fas fa-clipboard-list', 'active' => request()->routeIs('hr-manager.attendance.leaves*')],
                                ['label' => 'Regularization', 'route' => route('hr-manager.attendance.regularizations'), 'code' => 'RG', 'icon' => 'fas fa-arrows-rotate', 'active' => request()->routeIs('hr-manager.attendance.regularizations*')],
                                ['label' => 'Manual Punch', 'route' => route('hr-manager.attendance.outside-punches'), 'code' => 'MP', 'icon' => 'fas fa-location-dot', 'active' => request()->routeIs('hr-manager.attendance.outside-punches*')],
                                ['label' => 'Verifications', 'route' => route('hr-manager.verifications'), 'code' => 'VF', 'icon' => 'fas fa-user-check', 'active' => request()->routeIs('hr-manager.verifications')],
                            ],
                        ],
                        [
                            'id' => 'hrNavEmployees',
                            'label' => 'Employees',
                            'icon' => 'fas fa-users',
                            'active' => request()->routeIs('hr-manager.settings.hr.employees.*') || request()->routeIs('hr-manager.hiring.*') || request()->routeIs('purchase-orders.*'),
                            'items' => [
                                ['label' => 'All Employees', 'route' => route('hr-manager.settings.hr.employees.index'), 'code' => 'EM', 'icon' => 'fas fa-users', 'active' => request()->routeIs('hr-manager.settings.hr.employees.*') && !request()->routeIs('hr-manager.settings.hr.employees.create')],
                                ['label' => 'Add Employee', 'route' => route('hr-manager.settings.hr.employees.create'), 'code' => 'AD', 'icon' => 'fas fa-user-plus', 'active' => request()->routeIs('hr-manager.settings.hr.employees.create')],
                                ['label' => 'Hiring Leads', 'route' => route('hr-manager.hiring.index'), 'code' => 'HR', 'icon' => 'fas fa-user-tie', 'active' => request()->routeIs('hr-manager.hiring.*')],
                                ['label' => 'Purchase Requests', 'route' => route('purchase-orders.index'), 'code' => 'PR', 'icon' => 'fas fa-file-signature', 'active' => request()->routeIs('purchase-orders.*'), 'show' => $__canRaisePurchaseOrder],
                            ],
                        ],
                        [
                            'id' => 'hrNavPayroll',
                            'label' => 'Salary & Payroll',
                            'icon' => 'fas fa-money-check-dollar',
                            'active' => request()->routeIs('hr-manager.settings.hr.salary-profiles.*') || request()->routeIs('hr-manager.settings.hr.deduction-heads.*') || request()->routeIs('hr-manager.settings.hr.payslip-settings.*') || request()->routeIs('hr-manager.payroll.*') || request()->routeIs('hr-manager.settings.hr.incentives.*') || request()->routeIs('hr-manager.targets.*'),
                            'items' => [
                                ['label' => 'Employee Salary', 'route' => route('hr-manager.settings.hr.salary-profiles.index'), 'code' => 'ES', 'icon' => 'fas fa-indian-rupee-sign', 'active' => request()->routeIs('hr-manager.settings.hr.salary-profiles.*')],
                                ['label' => 'Salary Heads', 'route' => route('hr-manager.settings.hr.deduction-heads.index'), 'code' => 'SH', 'icon' => 'fas fa-list-ul', 'active' => request()->routeIs('hr-manager.settings.hr.deduction-heads.*')],
                                ['label' => 'Payslip Setup', 'route' => route('hr-manager.settings.hr.payslip-settings.index'), 'code' => 'PS', 'icon' => 'fas fa-file-invoice-dollar', 'active' => request()->routeIs('hr-manager.settings.hr.payslip-settings.*')],
                                ['label' => 'Payroll', 'route' => route('hr-manager.payroll.index'), 'code' => 'PY', 'icon' => 'fas fa-wallet', 'active' => request()->routeIs('hr-manager.payroll.*')],
                                ['label' => 'Incentives', 'route' => route('hr-manager.settings.hr.incentives.index'), 'code' => 'IN', 'icon' => 'fas fa-gift', 'active' => request()->routeIs('hr-manager.settings.hr.incentives.*')],
                                ['label' => 'Target Setting', 'route' => route('hr-manager.targets.index'), 'code' => 'TG', 'icon' => 'fas fa-bullseye', 'active' => request()->routeIs('hr-manager.targets.*')],
                            ],
                        ],
                        [
                            'id' => 'hrNavCommunication',
                            'label' => 'Communication',
                            'icon' => 'fas fa-bullhorn',
                            'active' => request()->routeIs('admin.broadcast'),
                            'items' => [
                                ['label' => 'Announcements', 'route' => route('admin.broadcast'), 'code' => 'AN', 'icon' => 'fas fa-bullhorn', 'active' => request()->routeIs('admin.broadcast')],
                            ],
                        ],
                        [
                            'id' => 'hrNavSettings',
                            'label' => 'Settings',
                            'icon' => 'fas fa-gear',
                            'active' => request()->routeIs('hr-manager.settings.attendance.*'),
                            'items' => [
                                ['label' => 'Attendance Setup', 'route' => route('hr-manager.settings.attendance.offices.index'), 'code' => 'ST', 'icon' => 'fas fa-sliders', 'active' => request()->routeIs('hr-manager.settings.attendance.*') && !request()->routeIs('hr-manager.settings.attendance.user-mappings.*') && !request()->routeIs('hr-manager.settings.attendance.offices.*') && !request()->routeIs('hr-manager.settings.attendance.policies.*')],
                                ['label' => 'Assign Users', 'route' => route('hr-manager.settings.attendance.user-mappings.index'), 'code' => 'AU', 'icon' => 'fas fa-user-gear', 'active' => request()->routeIs('hr-manager.settings.attendance.user-mappings.*')],
                                ['label' => 'Offices', 'route' => route('hr-manager.settings.attendance.offices.index'), 'code' => 'OF', 'icon' => 'fas fa-building', 'active' => request()->routeIs('hr-manager.settings.attendance.offices.*')],
                                ['label' => 'Rules', 'route' => route('hr-manager.settings.attendance.policies.index'), 'code' => 'RL', 'icon' => 'fas fa-clipboard-check', 'active' => request()->routeIs('hr-manager.settings.attendance.policies.*')],
                            ],
                        ],
                        [
                            'id' => 'hrNavHelpDesk',
                            'label' => 'Help Desk',
                            'icon' => 'fas fa-headset',
                            'active' => request()->routeIs('execution-desk.*') || request()->routeIs('support.*') || request()->routeIs('knowledge-base.*') || request()->routeIs('admin.knowledge-base.*'),
                            'items' => [
                                ['label' => 'Execution Desk', 'route' => route('execution-desk.index'), 'code' => 'EX', 'icon' => 'fas fa-list-check', 'active' => request()->routeIs('execution-desk.*')],
                                ['label' => 'Support Tickets', 'route' => route('support.index'), 'code' => 'SP', 'icon' => 'fas fa-ticket', 'active' => request()->routeIs('support.*')],
                                ['label' => 'Knowledge Base', 'route' => route('knowledge-base.index'), 'code' => 'KB', 'icon' => 'fas fa-book', 'active' => request()->routeIs('knowledge-base.*') || request()->routeIs('admin.knowledge-base.*')],
                            ],
                        ],
                    ];
                @endphp
                <div class="nav-section-label">HR Modules</div>
                @foreach($hrNavGroups as $group)
                    <button type="button"
                            class="sidebar-link {{ $group['active'] ? 'sidebar-parent-open' : '' }}"
                            data-admin-menu-trigger="{{ $group['id'] }}"
                            data-menu-active="{{ $group['active'] ? '1' : '0' }}"
                            data-tooltip="{{ $group['label'] }}"
                            title="{{ $group['label'] }}"
                            aria-expanded="{{ $group['active'] ? 'true' : 'false' }}"
                            style="cursor: pointer;">
                        <i class="{{ $group['icon'] }}" style="margin-right: 10px; width: 20px;"></i>
                        <span class="nav-text">{{ $group['label'] }}</span>
                        <i class="fas fa-chevron-down menu-chevron" style="{{ $group['active'] ? 'transform: rotate(180deg);' : '' }}"></i>
                    </button>
                    <div id="{{ $group['id'] }}" class="sidebar-submenu admin-tree-submenu" style="display: {{ $group['active'] ? 'block' : 'none' }};">
                        @foreach($group['items'] as $item)
                            @if($item['show'] ?? true)
                                <a href="{{ $item['route'] }}"
                                   class="sidebar-link {{ $item['active'] ? 'active' : '' }}"
                                   data-tooltip="{{ $item['label'] }}"
                                   title="{{ $item['label'] }}">
                                    <span class="hr-nav-code">{{ $item['code'] }}</span>
                                    <span class="nav-text">{{ $item['label'] }}</span>
                                </a>
                            @endif
                        @endforeach
                    </div>
                @endforeach
                @elseif(auth()->user()->isAdManager())
                <div class="nav-section-label">Ad Manager</div>
                <a href="{{ route('ad-manager.dashboard') }}" class="sidebar-link {{ request()->routeIs('ad-manager.dashboard') ? 'active' : '' }}">
                    <i class="fas fa-home" style="margin-right: 10px; width: 20px;"></i>
                    Dashboard
                </a>
                <a href="{{ route('ad-manager.leads.index') }}" class="sidebar-link {{ request()->routeIs('ad-manager.leads.*') ? 'active' : '' }}">
                    <i class="fas fa-magnifying-glass-chart" style="margin-right: 10px; width: 20px;"></i>
                    Lead Monitor
                </a>
                <a href="{{ route('ad-manager.meta.index') }}" class="sidebar-link {{ request()->routeIs('ad-manager.meta.*') || request()->routeIs('ad-manager.meta-waba.*') ? 'active' : '' }}">
                    <i class="fab fa-facebook" style="margin-right: 10px; width: 20px;"></i>
                    Meta Ops
                </a>
                <a href="{{ route('ad-manager.meta.facebook-lead-ads.bulk-recovery.index') }}" class="sidebar-link {{ request()->routeIs('ad-manager.meta.facebook-lead-ads.bulk-recovery.*') ? 'active' : '' }}">
                    <i class="fas fa-cloud-arrow-down" style="margin-right: 10px; width: 20px;"></i>
                    Meta Old Leads
                </a>
                <a href="{{ route('ad-manager.automation.index') }}" class="sidebar-link {{ request()->routeIs('ad-manager.automation.*') ? 'active' : '' }}">
                    <i class="fas fa-bolt" style="margin-right: 10px; width: 20px;"></i>
                    Source Automation
                </a>
                <a href="{{ route('data-intelligence.index') }}" class="sidebar-link {{ request()->routeIs('data-intelligence.*') ? 'active' : '' }}">
                    <i class="fas fa-brain" style="margin-right: 10px; width: 20px;"></i>
                    Data Intelligent
                </a>
                <a href="{{ route('purchase-orders.index') }}" class="sidebar-link {{ request()->routeIs('purchase-orders.*') ? 'active' : '' }}">
                    <i class="fas fa-file-signature" style="margin-right: 10px; width: 20px;"></i>
                    Purchase Orders
                </a>
                <a href="{{ route('dashboard') }}" class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <i class="fas fa-user" style="margin-right: 10px; width: 20px;"></i>
                    Profile
                </a>
                @elseif(auth()->user()->isMarketingUser())
                <div class="nav-section-label">Main</div>
                <a href="{{ route('marketing.dashboard') }}" class="sidebar-link {{ request()->routeIs('marketing.dashboard') ? 'active' : '' }}">
                    <i class="fas fa-gauge-high" style="margin-right: 10px; width: 20px;"></i>
                    Dashboard
                </a>
                <a href="{{ route('attendance.leaves') }}" class="sidebar-link {{ request()->routeIs('attendance.*') ? 'active' : '' }}">
                    <i class="fas fa-clipboard-check" style="margin-right: 10px; width: 20px;"></i>
                    Attendance
                </a>
                <a href="{{ route('execution-desk.index') }}" class="sidebar-link {{ request()->routeIs('execution-desk.*') ? 'active' : '' }}">
                    <i class="fas fa-list-check" style="margin-right: 10px; width: 20px;"></i>
                    Execution Desk
                </a>
                @if($__canRaisePurchaseOrder)
                <a href="{{ route('purchase-orders.index') }}" class="sidebar-link {{ request()->routeIs('purchase-orders.*') ? 'active' : '' }}">
                    <i class="fas {{ $__canRaiseReimbursement && !$__canRaiseNeedPurchase ? 'fa-receipt' : 'fa-file-signature' }}" style="margin-right: 10px; width: 20px;"></i>
                    {{ $__canRaiseReimbursement && !$__canRaiseNeedPurchase ? 'Reimbursement' : 'PO Requests' }}
                </a>
                @endif
                <a href="{{ route('marketing.profile') }}" class="sidebar-link {{ request()->routeIs('marketing.profile') ? 'active' : '' }}">
                    <i class="fas fa-user" style="margin-right: 10px; width: 20px;"></i>
                    Profile
                </a>
                @elseif(auth()->user()->isLeadManager())
                <div class="nav-section-label">Lead Management</div>
                <a href="{{ route('dashboard') }}" class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <i class="fas fa-home" style="margin-right: 10px; width: 20px;"></i>
                    Dashboard
                </a>
                <a href="{{ route('lead-bank.index') }}" class="sidebar-link {{ request()->routeIs('lead-bank.*') && !request()->routeIs('lead-bank.requests.*') && !request()->routeIs('lead-bank.analytics') && !request()->routeIs('lead-bank.import.*') ? 'active' : '' }}">
                    <i class="fas fa-boxes-stacked" style="margin-right: 10px; width: 20px;"></i>
                    Lead Bank
                </a>
                <a href="{{ route('lead-bank.requests.index') }}" class="sidebar-link {{ request()->routeIs('lead-bank.requests.*') ? 'active' : '' }}">
                    <i class="fas fa-clipboard-list" style="margin-right: 10px; width: 20px;"></i>
                    Lead Requests
                </a>
                <a href="{{ route('lead-bank.analytics') }}" class="sidebar-link {{ request()->routeIs('lead-bank.analytics') ? 'active' : '' }}">
                    <i class="fas fa-chart-line" style="margin-right: 10px; width: 20px;"></i>
                    Lead Analytics
                </a>
                <a href="{{ route('lead-bank.import.index') }}" class="sidebar-link {{ request()->routeIs('lead-bank.import.*') ? 'active' : '' }}">
                    <i class="fas fa-cloud-upload-alt" style="margin-right: 10px; width: 20px;"></i>
                    Import Leads
                </a>
                <div class="nav-section-label">Operations</div>
                <a href="{{ route('execution-desk.index') }}" class="sidebar-link {{ request()->routeIs('execution-desk.*') ? 'active' : '' }}">
                    <i class="fas fa-list-check" style="margin-right: 10px; width: 20px;"></i>
                    Execution Desk
                </a>
                <a href="{{ route('attendance.leaves') }}" class="sidebar-link {{ request()->routeIs('attendance.*') ? 'active' : '' }}">
                    <i class="fas fa-clipboard-check" style="margin-right: 10px; width: 20px;"></i>
                    My Attendance
                </a>
                @elseif(auth()->user()->isLeadQualityAuditor())
                <div class="nav-section-label">Dashboard</div>
                <a href="{{ route('lead-quality-auditor.dashboard') }}" class="sidebar-link {{ request()->routeIs('lead-quality-auditor.dashboard') ? 'active' : '' }}">
                    <i class="fas fa-gauge-high" style="margin-right: 10px; width: 20px;"></i>
                    Dashboard
                </a>
                <a href="{{ route('lead-quality-auditor.activity-calendar.index') }}" class="sidebar-link {{ request()->routeIs('lead-quality-auditor.activity-calendar.*') ? 'active' : '' }}">
                    <i class="fas fa-calendar-days" style="margin-right: 10px; width: 20px;"></i>
                    Activity Calendar
                </a>
                <div class="nav-section-label">Quality Review</div>
                <a href="{{ route('admin.insight-sheet.index') }}" class="sidebar-link {{ request()->routeIs('admin.insight-sheet.*') ? 'active' : '' }}">
                    <i class="fas fa-table-cells-large" style="margin-right: 10px; width: 20px;"></i>
                    Insight Sheet
                </a>
                <div class="nav-section-label">Lead Control</div>
                <a href="{{ route('lead-quality-auditor.lead-off.index') }}" class="sidebar-link {{ request()->routeIs('lead-quality-auditor.lead-off.*') ? 'active' : '' }}">
                    <i class="fas fa-user-slash" style="margin-right: 10px; width: 20px;"></i>
                    Detailed Lead Off
                </a>
                <div class="nav-section-label">My Attendance</div>
                <a href="{{ route('attendance.leaves') }}" class="sidebar-link {{ request()->routeIs('attendance.leaves') ? 'active' : '' }}">
                    <i class="fas fa-calendar-plus" style="margin-right: 10px; width: 20px;"></i>
                    Leave Requests
                </a>
                <a href="{{ route('attendance.regularizations') }}" class="sidebar-link {{ request()->routeIs('attendance.regularizations') ? 'active' : '' }}">
                    <i class="fas fa-rotate" style="margin-right: 10px; width: 20px;"></i>
                    Regularization
                </a>
                <a href="{{ route('attendance.payslips') }}" class="sidebar-link {{ request()->routeIs('attendance.payslips*') ? 'active' : '' }}">
                    <i class="fas fa-file-invoice-dollar" style="margin-right: 10px; width: 20px;"></i>
                    My Payslips
                </a>
                <div class="nav-section-label">My Account</div>
                <a href="{{ route('lead-quality-auditor.profile') }}" class="sidebar-link {{ request()->routeIs('lead-quality-auditor.profile') ? 'active' : '' }}">
                    <i class="fas fa-user-circle" style="margin-right: 10px; width: 20px;"></i>
                    Profile
                </a>
                @elseif(auth()->user()->isSalesHead())
                <div class="nav-section-label">Main</div>
                <a href="{{ route('sales-head.dashboard') }}" class="sidebar-link {{ request()->routeIs('sales-head.dashboard') ? 'active' : '' }}">
                    <i class="fas fa-home" style="margin-right: 10px; width: 20px;"></i>
                    Dashboard
                </a>
                <a href="{{ route('users.index') }}" class="sidebar-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                    <i class="fas fa-users" style="margin-right: 10px; width: 20px;"></i>
                    Users / Team
                </a>
                <a href="{{ route('admin.targets.index') }}" class="sidebar-link {{ request()->routeIs('admin.targets.*') ? 'active' : '' }}">
                    <i class="fas fa-bullseye" style="margin-right: 10px; width: 20px;"></i>
                    Target Setting
                </a>
                <div class="nav-section-label">Pipeline</div>
                <div class="sidebar-link {{ request()->routeIs('leads.*') || request()->routeIs('prospects.*') || request()->routeIs('meetings.*') || request()->routeIs('site-visits.*') || request()->routeIs('closers.*') ? 'active' : '' }}" style="cursor: pointer;" onclick="toggleLeadsMenu()">
                    <i class="fas fa-filter" style="margin-right: 10px; width: 20px;"></i>
                    Leads
                    <i class="fas fa-chevron-down ml-auto" id="leadsMenuIcon" style="transition: transform 0.3s;"></i>
                </div>
                <div id="leadsSubMenu" class="pl-8" style="display: {{ request()->routeIs('leads.*') || request()->routeIs('prospects.*') || request()->routeIs('meetings.*') || request()->routeIs('site-visits.*') || request()->routeIs('closers.*') ? 'block' : 'none' }};">
                    <a href="{{ route('leads.index') }}" class="sidebar-link {{ request()->routeIs('leads.*') && !request()->routeIs('prospects.*') && !request()->routeIs('meetings.*') && !request()->routeIs('site-visits.*') && !request()->routeIs('closers.*') ? 'active' : '' }}" style="padding: 8px 16px; font-size: 14px;">
                        <i class="fas fa-list" style="margin-right: 10px; width: 20px;"></i>
                        All Leads
                    </a>
                    <a href="{{ route('prospects.index') }}" class="sidebar-link {{ request()->routeIs('prospects.*') ? 'active' : '' }}" style="padding: 8px 16px; font-size: 14px;">
                        <i class="fas fa-user-check" style="margin-right: 10px; width: 20px;"></i>
                        Prospects
                    </a>
                    <a href="{{ route('meetings.index') }}" class="sidebar-link {{ request()->routeIs('meetings.*') ? 'active' : '' }}" style="padding: 8px 16px; font-size: 14px;">
                        <i class="fas fa-handshake" style="margin-right: 10px; width: 20px;"></i>
                        Meetings
                    </a>
                    <a href="{{ route('site-visits.index') }}" class="sidebar-link {{ request()->routeIs('site-visits.*') ? 'active' : '' }}" style="padding: 8px 16px; font-size: 14px;">
                        <i class="fas fa-map-marker-alt" style="margin-right: 10px; width: 20px;"></i>
                        Visits
                    </a>
                    <a href="{{ route('closers.index') }}" class="sidebar-link {{ request()->routeIs('closers.*') ? 'active' : '' }}" style="padding: 8px 16px; font-size: 14px;">
                        <i class="fas fa-check-circle" style="margin-right: 10px; width: 20px;"></i>
                        Closers
                    </a>
                </div>
                <div class="nav-section-label">Operations</div>
                <a href="{{ route('crm.verifications') }}" class="sidebar-link {{ request()->routeIs('crm.verifications') ? 'active' : '' }}">
                    <i class="fas fa-check-circle" style="margin-right: 10px; width: 20px;"></i>
                    Verifications
                </a>
                @if(Route::has('crm.meta-review'))
                <a href="{{ route('crm.meta-review') }}" class="sidebar-link {{ request()->routeIs('crm.meta-review') ? 'active' : '' }}">
                    <i class="fab fa-facebook" style="margin-right: 10px; width: 20px;"></i>
                    Meta Review
                </a>
                @endif
                @if(Route::has('crm.meta-lead-check.index'))
                <a href="{{ route('crm.meta-lead-check.index') }}" class="sidebar-link {{ request()->routeIs('crm.meta-lead-check.*') ? 'active' : '' }}">
                    <i class="fas fa-magnifying-glass-chart" style="margin-right: 10px; width: 20px;"></i>
                    Meta Lead Check
                </a>
                @endif
                <a href="{{ route('calls.index') }}" class="sidebar-link {{ request()->routeIs('calls.*') ? 'active' : '' }}">
                    <i class="fas fa-phone" style="margin-right: 10px; width: 20px;"></i>
                    Team Calls
                </a>
                <a href="{{ route('export.index') }}" class="sidebar-link {{ request()->routeIs('export.*') ? 'active' : '' }}">
                    <i class="fas fa-download" style="margin-right: 10px; width: 20px;"></i>
                    Export
                </a>
                @else
                <div class="nav-section-label">Main</div>
                <a href="{{ route('dashboard') }}" class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <i class="fas fa-home" style="margin-right: 10px; width: 20px;"></i>
                    Dashboard
                </a>
                @if(auth()->user()->canUseCallingCenter('calling_center.view'))
                <a href="{{ auth()->user()->canUseCallingCenter('calling_center.agent_queue') && !auth()->user()->canUseCallingCenter('calling_center.create_campaign') ? route('calling-center.queue') : route('calling-center.index') }}" class="sidebar-link {{ request()->routeIs('calling-center.*') ? 'active' : '' }}">
                    <i class="fas fa-headset" style="margin-right: 10px; width: 20px;"></i>
                    Calling Center
                </a>
                @endif
                <a href="{{ auth()->user()->isAdmin() ? route('admin.purchase-orders.index') : route('purchase-orders.index') }}" class="sidebar-link {{ request()->routeIs('purchase-orders.*') || request()->routeIs('admin.purchase-orders.*') ? 'active' : '' }}">
                    <i class="fas fa-file-signature" style="margin-right: 10px; width: 20px;"></i>
                    {{ auth()->user()->isAdmin() ? 'PO Approvals' : 'My Purchase Requests' }}
                </a>
                @if(!auth()->user()->isTelecaller())
                <a href="{{ route('users.index') }}" class="sidebar-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                    <i class="fas fa-users" style="margin-right: 10px; width: 20px;"></i>
                    Users
                </a>
                @endif
                @if(auth()->user()->isAdmin() || auth()->user()->isCrm())
                <div class="sidebar-link {{ request()->routeIs('admin.advisor-profiles.*') || request()->routeIs('admin.builder-logos.*') || request()->routeIs('admin.loan-partners.*') ? 'active' : '' }}" style="cursor: pointer;" onclick="toggleAdvisorProfilesMenu()">
                    <i class="fas fa-id-card" style="margin-right: 10px; width: 20px;"></i>
                    <span style="white-space: nowrap;">Advisor Profiles</span>
                    <i class="fas fa-chevron-down ml-auto" id="advisorProfilesMenuIcon" style="transition: transform 0.3s;"></i>
                </div>
                <div id="advisorProfilesSubMenu" class="pl-8" style="display: {{ request()->routeIs('admin.advisor-profiles.*') || request()->routeIs('admin.builder-logos.*') || request()->routeIs('admin.loan-partners.*') ? 'block' : 'none' }};">
                    <a href="{{ route('admin.advisor-profiles.index') }}" class="sidebar-link {{ request()->routeIs('admin.advisor-profiles.*') ? 'active' : '' }}" style="padding: 8px 16px; font-size: 14px;">
                        <i class="fas fa-id-card" style="margin-right: 10px; width: 20px;"></i>
                        <span style="white-space: nowrap;">All Advisor Profiles</span>
                    </a>
                    <a href="{{ route('admin.builder-logos.index') }}" class="sidebar-link {{ request()->routeIs('admin.builder-logos.*') ? 'active' : '' }}" style="padding: 8px 16px; font-size: 14px;">
                        <i class="fas fa-building" style="margin-right: 10px; width: 20px;"></i>
                        <span style="white-space: nowrap;">Builder Logos</span>
                    </a>
                    <a href="{{ route('admin.loan-partners.index') }}" class="sidebar-link {{ request()->routeIs('admin.loan-partners.*') ? 'active' : '' }}" style="padding: 8px 16px; font-size: 14px;">
                        <i class="fas fa-building-columns" style="margin-right: 10px; width: 20px;"></i>
                        <span style="white-space: nowrap;">Loan Partners</span>
                    </a>
                </div>
                @endif
                @if(auth()->user()->isAdmin() || auth()->user()->isCrm())
                <a href="{{ auth()->user()->isCrm() ? route('crm.targets.index') : route('admin.targets.index') }}" class="sidebar-link {{ request()->routeIs('admin.targets.*') || request()->routeIs('crm.targets.*') ? 'active' : '' }}">
                    <i class="fas fa-bullseye" style="margin-right: 10px; width: 20px;"></i>
                    Target Setting
                </a>
                @endif
                @if(auth()->user()->isCrm())
                <a href="{{ route('execution-desk.index') }}"
                   class="sidebar-link {{ request()->routeIs('execution-desk.*') ? 'active' : '' }}"
                   data-tooltip="Execution Desk"
                   title="Execution Desk">
                    <i class="fas fa-list-check" style="margin-right: 10px; width: 20px;"></i>
                    Execution Desk
                </a>
                <a href="{{ route('data-intelligence.index') }}"
                   class="sidebar-link {{ request()->routeIs('data-intelligence.*') ? 'active' : '' }}"
                   data-tooltip="Data Intelligent"
                   title="Data Intelligent">
                    <i class="fas fa-brain" style="margin-right: 10px; width: 20px;"></i>
                    Data Intelligent
                </a>
                @endif
                <div class="nav-section-label">Pipeline</div>
                @if(auth()->user()->isAdmin() || auth()->user()->isCrm() || auth()->user()->isSalesManager() || auth()->user()->isSalesHead())
                <div class="sidebar-link {{ request()->routeIs('leads.*') || request()->routeIs('admin.lead-board*') || request()->routeIs('crm.lead-board*') || request()->routeIs('admin.other-leads.*') || request()->routeIs('admin.dead-leads') || request()->routeIs('prospects.*') || request()->routeIs('meetings.*') || request()->routeIs('site-visits.*') || request()->routeIs('closers.*') ? 'active' : '' }}" style="cursor: pointer;" onclick="toggleLeadsMenu()">
                    <i class="fas fa-filter" style="margin-right: 10px; width: 20px;"></i>
                    Leads
                    <i class="fas fa-chevron-down ml-auto" id="leadsMenuIcon" style="transition: transform 0.3s;"></i>
                </div>
                <div id="leadsSubMenu" class="pl-8" style="display: {{ request()->routeIs('leads.*') || request()->routeIs('admin.lead-board*') || request()->routeIs('crm.lead-board*') || request()->routeIs('admin.other-leads.*') || request()->routeIs('admin.dead-leads') || request()->routeIs('prospects.*') || request()->routeIs('meetings.*') || request()->routeIs('site-visits.*') || request()->routeIs('closers.*') ? 'block' : 'none' }};">
                    <a href="{{ route('leads.index') }}" class="sidebar-link {{ request()->routeIs('leads.*') && !request()->routeIs('prospects.*') && !request()->routeIs('meetings.*') && !request()->routeIs('site-visits.*') && !request()->routeIs('closers.*') ? 'active' : '' }}" style="padding: 8px 16px; font-size: 14px;">
                        <i class="fas fa-list" style="margin-right: 10px; width: 20px;"></i>
                        All Leads
                    </a>
                    @if(auth()->user()->isAdmin() || auth()->user()->isCrm())
                    <a href="{{ auth()->user()->isCrm() ? route('crm.lead-board') : route('admin.lead-board') }}" class="sidebar-link {{ request()->routeIs('admin.lead-board*') || request()->routeIs('crm.lead-board*') ? 'active' : '' }}" style="padding: 8px 16px; font-size: 14px;">
                        <i class="fas fa-table-columns" style="margin-right: 10px; width: 20px;"></i>
                        Lead Board
                    </a>
                    @endif
                    <a href="{{ route('prospects.index') }}" class="sidebar-link {{ request()->routeIs('prospects.*') ? 'active' : '' }}" style="padding: 8px 16px; font-size: 14px;">
                        <i class="fas fa-user-check" style="margin-right: 10px; width: 20px;"></i>
                        Prospects
                    </a>
                    <a href="{{ route('meetings.index') }}" class="sidebar-link {{ request()->routeIs('meetings.*') ? 'active' : '' }}" style="padding: 8px 16px; font-size: 14px;">
                        <i class="fas fa-handshake" style="margin-right: 10px; width: 20px;"></i>
                        Meetings
                    </a>
                    <a href="{{ route('site-visits.index') }}" class="sidebar-link {{ request()->routeIs('site-visits.*') ? 'active' : '' }}" style="padding: 8px 16px; font-size: 14px;">
                        <i class="fas fa-map-marker-alt" style="margin-right: 10px; width: 20px;"></i>
                        Visits
                    </a>
                    <a href="{{ route('closers.index') }}" class="sidebar-link {{ request()->routeIs('closers.*') ? 'active' : '' }}" style="padding: 8px 16px; font-size: 14px;">
                        <i class="fas fa-check-circle" style="margin-right: 10px; width: 20px;"></i>
                        Closers
                    </a>
                    @if(auth()->user()->isAdmin())
                    <a href="{{ route('admin.other-leads.index') }}" class="sidebar-link {{ request()->routeIs('admin.other-leads.*') ? 'active' : '' }}" style="padding: 8px 16px; font-size: 14px;">
                        <i class="fas fa-box-open" style="margin-right: 10px; width: 20px;"></i>
                        Other Leads
                    </a>
                    @elseif(auth()->user()->isCrm())
                    <a href="{{ route('admin.other-leads.index') }}" class="sidebar-link {{ request()->routeIs('admin.other-leads.*') ? 'active' : '' }}" style="padding: 8px 16px; font-size: 14px;">
                        <i class="fas fa-box-open" style="margin-right: 10px; width: 20px;"></i>
                        Other Leads
                    </a>
                    @endif
                </div>
                @else
                <a href="{{ route('leads.index') }}" class="sidebar-link {{ request()->routeIs('leads.*') ? 'active' : '' }}">
                    <i class="fas fa-filter" style="margin-right: 10px; width: 20px;"></i>
                    Leads
                </a>
                @endif
                <div class="sidebar-link {{ request()->routeIs('projects.*') || request()->routeIs('builders.*') ? 'active' : '' }}" style="cursor: pointer;" onclick="toggleProjectsMenu()">
                    <i class="fas fa-project-diagram" style="margin-right: 10px; width: 20px;"></i>
                    Projects
                    <i class="fas fa-chevron-down ml-auto" id="projectsMenuIcon" style="transition: transform 0.3s;"></i>
                </div>
                <div id="projectsSubMenu" class="pl-8" style="display: {{ request()->routeIs('projects.*') || request()->routeIs('builders.*') ? 'block' : 'none' }};">
                    <a href="{{ route('projects.index') }}" class="sidebar-link {{ request()->routeIs('projects.*') && !request()->routeIs('builders.*') ? 'active' : '' }}" style="padding: 8px 16px; font-size: 14px;">
                        <i class="fas fa-list" style="margin-right: 10px; width: 20px;"></i>
                        All Projects
                    </a>
                    @if(auth()->user()->isAdmin() || auth()->user()->isCrm())
                    <a href="{{ route('builders.index') }}" class="sidebar-link {{ request()->routeIs('builders.*') ? 'active' : '' }}" style="padding: 8px 16px; font-size: 14px;">
                        <i class="fas fa-building" style="margin-right: 10px; width: 20px;"></i>
                        Builders
                    </a>
                    @endif
                </div>
                <div class="nav-section-label">Operations</div>
                @if(Route::has('calls.index'))
                <a href="{{ route('calls.index') }}" class="sidebar-link {{ request()->routeIs('calls.*') ? 'active' : '' }}">
                    <i class="fas fa-phone" style="margin-right: 10px; width: 20px;"></i>
                    @if(auth()->user()->isTelecaller() || auth()->user()->isSalesExecutive())
                        My Calls
                    @elseif(auth()->user()->isSalesManager() || auth()->user()->isSalesHead())
                        Team Calls
                    @else
                        All Calls
                    @endif
                </a>
                @endif
                @if((auth()->user()->isCrm() || auth()->user()->isAdmin()) && Route::has('whatsapp-control-center.index'))
                <a href="{{ route('whatsapp-control-center.index') }}" class="sidebar-link {{ request()->routeIs('whatsapp-control-center.*') || request()->routeIs('integrations.meta-waba.*') ? 'active' : '' }}">
                    <i class="fab fa-whatsapp" style="margin-right: 10px; width: 20px;"></i>
                    WhatsApp Control
                </a>
                @endif
                @if(Route::has('chat.index'))
                <a href="{{ route('chat.index') }}" class="sidebar-link {{ request()->routeIs('chat.*') ? 'active' : '' }}">
                    <i class="fab fa-whatsapp" style="margin-right: 10px; width: 20px;"></i>
                    WhatsApp Chat
                </a>
                @endif
                @php
                    $whatsappAutomationRoute = auth()->user()->isCrm() && Route::has('crm.whatsapp-automation.index')
                        ? route('crm.whatsapp-automation.index')
                        : (Route::has('admin.whatsapp-automation.index') ? route('admin.whatsapp-automation.index') : null);
                    $instagramAutomationRoute = auth()->user()->isCrm() && Route::has('crm.instagram-automation.index')
                        ? route('crm.instagram-automation.index')
                        : (Route::has('admin.instagram-automation.index') ? route('admin.instagram-automation.index') : null);
                @endphp
                @if((auth()->user()->isCrm() || auth()->user()->isAdmin()) && $whatsappAutomationRoute)
                <a href="{{ $whatsappAutomationRoute }}" class="sidebar-link {{ request()->routeIs('admin.whatsapp-automation.*') || request()->routeIs('crm.whatsapp-automation.*') ? 'active' : '' }}">
                    <i class="fas fa-bolt" style="margin-right: 10px; width: 20px;"></i>
                    WhatsApp Automation
                </a>
                @endif
                @if((auth()->user()->isCrm() || auth()->user()->isAdmin()) && $instagramAutomationRoute)
                <a href="{{ $instagramAutomationRoute }}" class="sidebar-link {{ request()->routeIs('admin.instagram-automation.*') || request()->routeIs('crm.instagram-automation.*') ? 'active' : '' }}">
                    <i class="fab fa-instagram" style="margin-right: 10px; width: 20px;"></i>
                    Instagram Automation
                </a>
                @endif
                @if(!auth()->user()->isAdmin() && !auth()->user()->isTelecaller() && !auth()->user()->isSalesHead() && !auth()->user()->isCrm())
                <a href="{{ route('lead-assignment.index') }}" class="sidebar-link {{ request()->routeIs('lead-assignment.*') ? 'active' : '' }}">
                    <i class="fas fa-clipboard" style="margin-right: 10px; width: 20px;"></i>
                    Lead Assignment
                </a>
                @endif
                @if(auth()->user()->canManageUsers() && !auth()->user()->isSalesHead())
                <a href="{{ route('lead-bank.index') }}" class="sidebar-link {{ request()->routeIs('lead-bank.*') && !request()->routeIs('lead-bank.requests.*') && !request()->routeIs('lead-bank.analytics') ? 'active' : '' }}">
                    <i class="fas fa-boxes-stacked" style="margin-right: 10px; width: 20px;"></i>
                    Lead Bank
                </a>
                <a href="{{ route('lead-bank.requests.index') }}" class="sidebar-link {{ request()->routeIs('lead-bank.requests.*') ? 'active' : '' }}">
                    <i class="fas fa-clipboard-list" style="margin-right: 10px; width: 20px;"></i>
                    Lead Requests
                </a>
                <a href="{{ route('lead-bank.analytics') }}" class="sidebar-link {{ request()->routeIs('lead-bank.analytics') ? 'active' : '' }}">
                    <i class="fas fa-chart-line" style="margin-right: 10px; width: 20px;"></i>
                    Lead Analytics
                </a>
                <a href="{{ route('lead-import.index') }}" class="sidebar-link {{ request()->routeIs('lead-import.*') ? 'active' : '' }}">
                    <i class="fas fa-cloud-upload-alt" style="margin-right: 10px; width: 20px;"></i>
                    Lead Import
                </a>
                @endif
                @if(auth()->user()->isSalesManager() || auth()->user()->isSeniorManager() || auth()->user()->isAssistantSalesManager())
                <a href="{{ route('lead-bank.requests.index') }}" class="sidebar-link {{ request()->routeIs('lead-bank.requests.*') ? 'active' : '' }}">
                    <i class="fas fa-clipboard-list" style="margin-right: 10px; width: 20px;"></i>
                    Lead Requests
                </a>
                @endif
                @if(auth()->user()->isCrm() || auth()->user()->isAdmin() || auth()->user()->isSalesHead())
                <a href="{{ route('crm.verifications') }}" class="sidebar-link {{ request()->routeIs('crm.verifications') ? 'active' : '' }}">
                    <i class="fas fa-check-circle" style="margin-right: 10px; width: 20px;"></i>
                    Verifications
                </a>
                @endif
                @if(auth()->user()->isCrm() && Route::has('crm.meta-review'))
                <a href="{{ route('crm.meta-review') }}" class="sidebar-link {{ request()->routeIs('crm.meta-review') ? 'active' : '' }}">
                    <i class="fab fa-facebook" style="margin-right: 10px; width: 20px;"></i>
                    Meta Review
                </a>
                @endif
                @if(auth()->user()->isCrm() && Route::has('crm.meta-lead-check.index'))
                <a href="{{ route('crm.meta-lead-check.index') }}" class="sidebar-link {{ request()->routeIs('crm.meta-lead-check.*') ? 'active' : '' }}">
                    <i class="fas fa-magnifying-glass-chart" style="margin-right: 10px; width: 20px;"></i>
                    Meta Lead Check
                </a>
                @endif
                <a href="{{ route('admin.broadcast') }}" class="sidebar-link {{ request()->routeIs('admin.broadcast') ? 'active' : '' }}">
                    <i class="fas fa-bullhorn" style="margin-right: 10px; width: 20px;"></i>
                    Announcements
                </a>
                @if(auth()->user()->isAdmin() || auth()->user()->isCrm() || auth()->user()->isSalesManager() || auth()->user()->isSalesHead())
                <a href="{{ route('export.index') }}" class="sidebar-link {{ request()->routeIs('export.*') ? 'active' : '' }}">
                    <i class="fas fa-download" style="margin-right: 10px; width: 20px;"></i>
                    Export
                </a>
                @endif
                <div class="nav-section-label">System</div>
                @if(auth()->user()->isAdmin())
                <a href="{{ route('admin.mail-center.index') }}"
                   class="sidebar-link {{ request()->routeIs('admin.mail-center.*') ? 'active' : '' }}"
                   data-tooltip="Mail Center"
                   title="Mail Center">
                    <i class="fas fa-envelope-open-text" style="margin-right: 10px; width: 20px;"></i>
                    Mail Center
                </a>
                <a href="{{ route('integrations.index') }}"
                   class="sidebar-link {{ request()->routeIs('integrations.*') ? 'active' : '' }}"
                   data-tooltip="Integration"
                   title="Integration">
                    <i class="fas fa-plug" style="margin-right: 10px; width: 20px;"></i>
                    Integration
                </a>
                <a href="{{ route('integrations.sheet-integration') }}"
                   class="sidebar-link {{ request()->routeIs('integrations.sheet-integration') || request()->routeIs('integrations.sheet-sync') || request()->routeIs('lead-import.*') ? 'active' : '' }}"
                   data-tooltip="Sheet Integration"
                   title="Sheet Integration">
                    <i class="fas fa-table" style="margin-right: 10px; width: 20px;"></i>
                    Sheet Integration
                </a>
                <a href="{{ route('integrations.meta-sheet.index') }}"
                   class="sidebar-link {{ request()->routeIs('integrations.meta-sheet.*') ? 'active' : '' }}"
                   data-tooltip="Meta Sheets"
                   title="Meta Sheets">
                    <i class="fab fa-facebook" style="margin-right: 10px; width: 20px;"></i>
                    Meta Sheets
                </a>
                @endif
                @if(auth()->user()->isCrm())
                <a href="{{ route('lead-assignment.index') }}"
                   class="sidebar-link {{ request()->routeIs('lead-assignment.*') && !request()->routeIs('lead-assignment.calling-tasks.*') && !request()->routeIs('lead-assignment.telecaller-status') && !request()->routeIs('lead-assignment.lead-off-users') ? 'active' : '' }}"
                   data-tooltip="Lead Assignment"
                   title="Lead Assignment">
                    <i class="fas fa-clipboard" style="margin-right: 10px; width: 20px;"></i>
                    Lead Assignment
                </a>
                <a href="{{ route('lead-assignment.calling-tasks.index') }}"
                   class="sidebar-link {{ request()->routeIs('lead-assignment.calling-tasks.*') ? 'active' : '' }}"
                   data-tooltip="Calling Tasks"
                   title="Calling Tasks">
                    <i class="fas fa-phone-volume" style="margin-right: 10px; width: 20px;"></i>
                    Calling Tasks
                </a>
                <a href="{{ route('lead-assignment.telecaller-status') }}"
                   class="sidebar-link {{ request()->routeIs('lead-assignment.telecaller-status') || request()->routeIs('lead-assignment.lead-off-users') ? 'active' : '' }}"
                   data-tooltip="Lead Off"
                   title="Lead Off">
                    <i class="fas fa-user-slash" style="margin-right: 10px; width: 20px;"></i>
                    Lead Off
                </a>
                @endif
                @if(!auth()->user()->isAdmin() && !auth()->user()->isCrm() && auth()->user()->canManageUsers() && !auth()->user()->isSalesHead())
                <a href="{{ route('admin.dead-leads') }}" class="sidebar-link {{ request()->routeIs('admin.dead-leads') ? 'active' : '' }}">
                    <i class="fas fa-trash" style="margin-right: 10px; width: 20px;"></i>
                    Dead Leads / Trash
                </a>
                @endif
                @if(!auth()->user()->isCrm())
                <a href="{{ route('execution-desk.index') }}" class="sidebar-link {{ request()->routeIs('execution-desk.*') ? 'active' : '' }}" data-tooltip="Execution Desk" title="Execution Desk">
                    <i class="fas fa-list-check" style="margin-right: 10px; width: 20px;"></i>
                    Execution Desk
                </a>
                <a href="{{ route('knowledge-base.index') }}" class="sidebar-link {{ request()->routeIs('knowledge-base.*') || request()->routeIs('admin.knowledge-base.*') ? 'active' : '' }}" data-tooltip="Knowledge Base" title="Knowledge Base">
                    <i class="fas fa-book-open-reader" style="margin-right: 10px; width: 20px;"></i>
                    Knowledge Base
                </a>
                @endif
                @endif
                @if(!auth()->user()->isAdmin() && !auth()->user()->isMarketingUser())
                <a href="{{ route('support.index') }}" class="sidebar-link {{ request()->routeIs('support.*') ? 'active' : '' }}" data-tooltip="Support" title="Support">
                    <i class="fas fa-life-ring" style="margin-right: 10px; width: 20px;"></i>
                    <span class="nav-text">Support</span>
                </a>
                <a href="{{ route('knowledge-base.index') }}" class="sidebar-link {{ request()->routeIs('knowledge-base.*') || request()->routeIs('admin.knowledge-base.*') ? 'active' : '' }}" data-tooltip="Knowledge Base" title="Knowledge Base">
                    <i class="fas fa-book-open-reader" style="margin-right: 10px; width: 20px;"></i>
                    <span class="nav-text">Knowledge Base</span>
                </a>
                @endif
            </nav>
            </div>
            <div class="sidebar-footer">
                <div class="sidebar-user-card">
                    <span class="sidebar-user-avatar">{{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}</span>
                    <div>
                        <div class="sidebar-user-name">{{ auth()->user()->name }}</div>
                        <div class="sidebar-user-role">{{ auth()->user()->getDisplayRoleName() ?? 'User' }}</div>
                    </div>
                </div>
            </div>
        </aside>
        
        <!-- Sidebar Tooltip -->
        <div id="sidebarTooltip" class="sidebar-tooltip">
            <span class="tooltip-text"></span>
            <span class="tooltip-arrow"></span>
        </div>
        
        <!-- Main Content -->
        <div id="mainContent" style="flex: 1; min-width: 0; overflow-y: auto; overflow-x: hidden; height: 100vh; background: #F7F6F3;">
            <div class="container" style="padding: 20px; max-width: 100%; width: 100%; min-width: 0; box-sizing: border-box;">
                @if(trim($__env->yieldContent('show-app-header')))
                    <!-- Header -->
                    <div class="header main-header {{ request()->routeIs('admin.dashboard') ? 'main-header-admin-dashboard' : '' }}">
                        <div>
                            <h1 style="font-size: 28px; font-weight: 700; color: #063A1C;">@yield('page-title', 'Dashboard')</h1>
                            @hasSection('page-subtitle')
                                <p style="color: #B3B5B4; font-size: 14px; margin-top: 4px;">@yield('page-subtitle')</p>
                            @endif
                            @hasSection('header-below-title')
                                <div style="margin-top: 8px;">@yield('header-below-title')</div>
                            @endif
                        </div>
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <button type="button" class="mobile-nav-toggle" onclick="openMobileSidebar()" aria-label="Open navigation">
                                <i class="fas fa-bars"></i>
                            </button>
                            @hasSection('header-actions')
                                @yield('header-actions')
                            @endif
                            @if(!auth()->user()->isAdmin() && !auth()->user()->isCrm() && !auth()->user()->isHrManager())
                            <button type="button" id="navModeToggle" class="btn btn-brand-secondary" title="Hide navigation" style="padding: 10px 12px; font-size: 14px;">
                                <i id="navModeToggleIcon" class="fas fa-eye-slash" style="margin-right: 6px;"></i>
                                <span id="navModeToggleLabel">Hide Nav</span>
                            </button>
                            @endif
                            <!-- Date/Time Clock (shown for all including CRM) -->
                            <div id="datetimeClock" style="background: white; border: 1px solid #e0e0e0; border-radius: 8px; padding: 8px 12px; font-family: 'Courier New', monospace; font-weight: 600; font-size: 14px; color: #063A1C; min-width: 160px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                <div id="clockTime" style="font-size: 16px; color: #205A44;">--:--:--</div>
                                <div id="clockDate" style="font-size: 11px; color: #B3B5B4; margin-top: 2px;">-- -- ----</div>
                            </div>
                            @include('components.global-notification-center')
                            @if(!auth()->user()->isCrm())
                            <span style="color: #B3B5B4; font-size: 14px;">{{ auth()->user()->name }}</span>
                            @endif
                            <form action="{{ route('logout') }}" method="POST" class="header-logout-form" style="display: inline;">
                                @csrf
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-sign-out-alt" style="margin-right: 5px;"></i>
                                    Logout
                                </button>
                            </form>
                        </div>
                    </div>
                @endif

                @yield('content')
            </div>
        </div>
    </div>
    
    <!-- Mobile Bottom Navigation (shown on small screens only). Admin/CRM: 5 visible (20% each), rest on scroll. -->
    <nav id="mobileFooterNav" @if(auth()->user()->isAdmin()) class="admin-mobile-nav" @elseif(auth()->user()->isCrm()) class="crm-mobile-nav" @endif>
        @if(auth()->user()->isAdmin())
            <a href="{{ route('admin.dashboard') }}" class="footer-nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                <i class="fas fa-home"></i>
                <span>Home</span>
            </a>
            <a href="{{ route('users.index') }}" class="footer-nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                <i class="fas fa-users"></i>
                <span>Users</span>
            </a>
            <a href="{{ route('admin.verifications') }}" class="footer-nav-link {{ request()->routeIs('admin.verifications') ? 'active' : '' }}">
                <i class="fas fa-check-circle"></i>
                <span>Verify</span>
            </a>
            @if(Route::has('crm.meta-review'))
            <a href="{{ route('crm.meta-review') }}" class="footer-nav-link {{ request()->routeIs('crm.meta-review') ? 'active' : '' }}">
                <i class="fab fa-facebook"></i>
                <span>Meta Review</span>
            </a>
            @endif
            @if(Route::has('crm.meta-lead-check.index'))
            <a href="{{ route('crm.meta-lead-check.index') }}" class="footer-nav-link {{ request()->routeIs('crm.meta-lead-check.*') ? 'active' : '' }}">
                <i class="fas fa-magnifying-glass-chart"></i>
                <span>Meta Check</span>
            </a>
            @endif
            <a href="{{ route('admin.lead-audit.index') }}" class="footer-nav-link {{ request()->routeIs('admin.lead-audit.*') ? 'active' : '' }}">
                <i class="fas fa-magnifying-glass-chart"></i>
                <span>Lead Checker</span>
            </a>
            <a href="{{ route('data-intelligence.index') }}" class="footer-nav-link {{ request()->routeIs('data-intelligence.*') ? 'active' : '' }}">
                <i class="fas fa-brain"></i>
                <span>Data Intel</span>
            </a>
            <a href="{{ route('leads.index') }}" class="footer-nav-link {{ request()->routeIs('leads.*') || request()->routeIs('prospects.*') || request()->routeIs('meetings.*') || request()->routeIs('site-visits.*') || request()->routeIs('closers.*') ? 'active' : '' }}">
                <i class="fas fa-user-friends"></i>
                <span>Leads</span>
            </a>
            <a href="{{ route('admin.lead-board') }}" class="footer-nav-link {{ request()->routeIs('admin.lead-board*') ? 'active' : '' }}">
                <i class="fas fa-table-columns"></i>
                <span>Board</span>
            </a>
            <a href="{{ route('admin.other-leads.index') }}" class="footer-nav-link {{ request()->routeIs('admin.other-leads.*') ? 'active' : '' }}">
                <i class="fas fa-box-open"></i>
                <span>Other Leads</span>
            </a>
            <a href="{{ route('projects.index') }}" class="footer-nav-link {{ request()->routeIs('projects.*') || request()->routeIs('builders.*') ? 'active' : '' }}">
                <i class="fas fa-project-diagram"></i>
                <span>Projects</span>
            </a>
            <a href="{{ route('calls.index') }}" class="footer-nav-link {{ request()->routeIs('calls.*') ? 'active' : '' }}">
                <i class="fas fa-phone"></i>
                <span>Calls</span>
            </a>
            <a href="{{ route('chat.index') }}" class="footer-nav-link {{ request()->routeIs('chat.*') ? 'active' : '' }}">
                <i class="fab fa-whatsapp"></i>
                <span>Chat</span>
            </a>
            <a href="{{ route('admin.mail-center.index') }}" class="footer-nav-link {{ request()->routeIs('admin.mail-center.*') ? 'active' : '' }}">
                <i class="fas fa-envelope-open-text"></i>
                <span>Mail</span>
            </a>
            <a href="{{ route('export.index') }}" class="footer-nav-link {{ request()->routeIs('export.*') ? 'active' : '' }}">
                <i class="fas fa-download"></i>
                <span>Export</span>
            </a>
            <a href="{{ route('lead-assignment.calling-tasks.index') }}" class="footer-nav-link {{ request()->routeIs('lead-assignment.calling-tasks.*') ? 'active' : '' }}">
                <i class="fas fa-phone-volume"></i>
                <span>Call Tasks</span>
            </a>
            <a href="{{ route('execution-desk.index') }}" class="footer-nav-link {{ request()->routeIs('execution-desk.*') ? 'active' : '' }}">
                <i class="fas fa-list-check"></i>
                <span>Exec Desk</span>
            </a>
            <a href="{{ route('admin.company-settings.index') }}" class="footer-nav-link {{ request()->routeIs('admin.company-settings.*') || request()->routeIs('admin.system-settings.*') ? 'active' : '' }}">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
            <a href="{{ route('admin.mobile-app-update.index') }}" class="footer-nav-link {{ request()->routeIs('admin.mobile-app-update.*') ? 'active' : '' }}">
                <i class="fas fa-mobile-screen-button"></i>
                <span>App Update</span>
            </a>
            <a href="{{ route('admin.profile') }}" class="footer-nav-link {{ request()->routeIs('admin.profile') ? 'active' : '' }}">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
        @elseif(auth()->user()->isCrm())
            <a href="{{ route('ad-manager.dashboard') }}" class="footer-nav-link {{ request()->routeIs('ad-manager.dashboard') ? 'active' : '' }}">
                <i class="fas fa-home"></i>
                <span>Home</span>
            </a>
            <a href="{{ route('leads.index') }}" class="footer-nav-link {{ request()->routeIs('leads.*') || request()->routeIs('prospects.*') || request()->routeIs('meetings.*') || request()->routeIs('site-visits.*') || request()->routeIs('closers.*') ? 'active' : '' }}">
                <i class="fas fa-user-friends"></i>
                <span>Leads</span>
            </a>
            <a href="{{ route('crm.lead-board') }}" class="footer-nav-link {{ request()->routeIs('crm.lead-board*') ? 'active' : '' }}">
                <i class="fas fa-table-columns"></i>
                <span>Board</span>
            </a>
            <a href="{{ route('data-intelligence.index') }}" class="footer-nav-link {{ request()->routeIs('data-intelligence.*') ? 'active' : '' }}">
                <i class="fas fa-brain"></i>
                <span>Data</span>
            </a>
            <a href="{{ route('crm.verifications') }}" class="footer-nav-link {{ request()->routeIs('crm.verifications') ? 'active' : '' }}">
                <i class="fas fa-check-circle"></i>
                <span>Verifications</span>
            </a>
            @if(Route::has('crm.meta-review'))
            <a href="{{ route('crm.meta-review') }}" class="footer-nav-link {{ request()->routeIs('crm.meta-review') ? 'active' : '' }}">
                <i class="fab fa-facebook"></i>
                <span>Meta Review</span>
            </a>
            @endif
            @if(Route::has('crm.meta-lead-check.index'))
            <a href="{{ route('crm.meta-lead-check.index') }}" class="footer-nav-link {{ request()->routeIs('crm.meta-lead-check.*') ? 'active' : '' }}">
                <i class="fas fa-magnifying-glass-chart"></i>
                <span>Meta Check</span>
            </a>
            @endif
            <a href="{{ route('lead-import.index') }}" class="footer-nav-link {{ request()->routeIs('lead-import.*') ? 'active' : '' }}">
                <i class="fas fa-cloud-upload-alt"></i>
                <span>Lead Import</span>
            </a>
            <a href="{{ route('lead-assignment.index') }}" class="footer-nav-link {{ request()->routeIs('lead-assignment.*') && !request()->routeIs('lead-assignment.calling-tasks.*') && !request()->routeIs('lead-assignment.telecaller-status') && !request()->routeIs('lead-assignment.lead-off-users') ? 'active' : '' }}">
                <i class="fas fa-clipboard"></i>
                <span>Lead Assign</span>
            </a>
            <a href="{{ route('lead-assignment.calling-tasks.index') }}" class="footer-nav-link {{ request()->routeIs('lead-assignment.calling-tasks.*') ? 'active' : '' }}">
                <i class="fas fa-phone-volume"></i>
                <span>Call Tasks</span>
            </a>
            <a href="{{ route('lead-assignment.telecaller-status') }}" class="footer-nav-link {{ request()->routeIs('lead-assignment.telecaller-status') || request()->routeIs('lead-assignment.lead-off-users') ? 'active' : '' }}">
                <i class="fas fa-user-slash"></i>
                <span>Lead Off</span>
            </a>
            <a href="{{ route('export.index') }}" class="footer-nav-link {{ request()->routeIs('export.*') ? 'active' : '' }}">
                <i class="fas fa-download"></i>
                <span>Export</span>
            </a>
            <a href="{{ route('users.index') }}" class="footer-nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                <i class="fas fa-users"></i>
                <span>Users</span>
            </a>
            <a href="{{ auth()->user()->isCrm() ? route('crm.targets.index') : route('admin.targets.index') }}" class="footer-nav-link {{ request()->routeIs('admin.targets.*') || request()->routeIs('crm.targets.*') ? 'active' : '' }}">
                <i class="fas fa-bullseye"></i>
                <span>Targets</span>
            </a>
            <a href="{{ route('projects.index') }}" class="footer-nav-link {{ request()->routeIs('projects.*') || request()->routeIs('builders.*') ? 'active' : '' }}">
                <i class="fas fa-project-diagram"></i>
                <span>Projects</span>
            </a>
            <a href="{{ route('calls.index') }}" class="footer-nav-link {{ request()->routeIs('calls.*') ? 'active' : '' }}">
                <i class="fas fa-phone"></i>
                <span>Calls</span>
            </a>
            <a href="{{ route('chat.index') }}" class="footer-nav-link {{ request()->routeIs('chat.*') ? 'active' : '' }}">
                <i class="fab fa-whatsapp"></i>
                <span>Chat</span>
            </a>
            <a href="{{ route('admin.other-leads.index') }}" class="footer-nav-link {{ request()->routeIs('admin.other-leads.*') ? 'active' : '' }}">
                <i class="fas fa-box-open"></i>
                <span>Other Leads</span>
            </a>
            <a href="{{ route('dashboard') }}" class="footer-nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
            <a href="{{ route('logout.get') }}" class="footer-nav-link">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        @elseif(auth()->user()->isJuniorHr())
            <a href="{{ route('junior-hr.dashboard') }}" class="footer-nav-link {{ request()->routeIs('junior-hr.dashboard') ? 'active' : '' }}">
                <i class="fas fa-home"></i>
                <span>Home</span>
            </a>
            <a href="{{ route('junior-hr.hiring.index') }}" class="footer-nav-link {{ request()->routeIs('junior-hr.hiring.*') ? 'active' : '' }}">
                <i class="fas fa-user-tie"></i>
                <span>Hiring</span>
            </a>
            <a href="{{ route('hr-manager.attendance.sheet') }}" class="footer-nav-link {{ request()->routeIs('hr-manager.attendance.*') ? 'active' : '' }}">
                <i class="fas fa-calendar-check"></i>
                <span>Attend</span>
            </a>
            <a href="{{ route('junior-hr.profile') }}" class="footer-nav-link {{ request()->routeIs('junior-hr.profile') ? 'active' : '' }}">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
            <a href="{{ route('logout.get') }}" class="footer-nav-link">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        @elseif(auth()->user()->isHrManager())
            <a href="{{ route('hr-manager.dashboard') }}" class="footer-nav-link {{ request()->routeIs('hr-manager.dashboard') ? 'active' : '' }}">
                <span class="footer-nav-code">DW</span>
                <span>Dashboard</span>
            </a>
            <a href="{{ route('hr-manager.attendance.sheet') }}" class="footer-nav-link {{ request()->routeIs('hr-manager.attendance.*') ? 'active' : '' }}">
                <span class="footer-nav-code">AT</span>
                <span>Monthly</span>
            </a>
            <a href="{{ route('hr-manager.settings.hr.employees.index') }}" class="footer-nav-link {{ request()->routeIs('hr-manager.settings.hr.employees.*') || request()->routeIs('hr-manager.hiring.*') ? 'active' : '' }}">
                <span class="footer-nav-code">EM</span>
                <span>Employees</span>
            </a>
            <a href="{{ route('hr-manager.payroll.index') }}" class="footer-nav-link {{ request()->routeIs('hr-manager.payroll.*') ? 'active' : '' }}">
                <span class="footer-nav-code">PY</span>
                <span>Payroll</span>
            </a>
            <a href="{{ route('admin.broadcast') }}" class="footer-nav-link {{ request()->routeIs('admin.broadcast') ? 'active' : '' }}">
                <span class="footer-nav-code">AN</span>
                <span>Announce</span>
            </a>
            <a href="{{ route('logout.get') }}" class="footer-nav-link">
                <span class="footer-nav-code">LO</span>
                <span>Logout</span>
            </a>
        @elseif(auth()->user()->isAdManager())
            <a href="{{ route('dashboard') }}" class="footer-nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i class="fas fa-home"></i>
                <span>Home</span>
            </a>
            <a href="{{ route('ad-manager.leads.index') }}" class="footer-nav-link {{ request()->routeIs('ad-manager.leads.*') ? 'active' : '' }}">
                <i class="fas fa-magnifying-glass-chart"></i>
                <span>Leads</span>
            </a>
            <a href="{{ route('ad-manager.meta.index') }}" class="footer-nav-link {{ request()->routeIs('ad-manager.meta.*') || request()->routeIs('ad-manager.meta-waba.*') ? 'active' : '' }}">
                <i class="fab fa-facebook"></i>
                <span>Meta</span>
            </a>
            <a href="{{ route('ad-manager.automation.index') }}" class="footer-nav-link {{ request()->routeIs('ad-manager.automation.*') ? 'active' : '' }}">
                <i class="fas fa-bolt"></i>
                <span>Auto</span>
            </a>
            <a href="{{ route('data-intelligence.index') }}" class="footer-nav-link {{ request()->routeIs('data-intelligence.*') ? 'active' : '' }}">
                <i class="fas fa-brain"></i>
                <span>Data</span>
            </a>
            <a href="{{ route('purchase-orders.index') }}" class="footer-nav-link {{ request()->routeIs('purchase-orders.*') ? 'active' : '' }}">
                <i class="fas fa-file-signature"></i>
                <span>PO</span>
            </a>
            <a href="{{ route('dashboard') }}" class="footer-nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
        @elseif(auth()->user()->isMarketingUser())
            <a href="{{ route('marketing.dashboard') }}#marketing-dashboard" class="footer-nav-link {{ request()->routeIs('marketing.dashboard') ? 'active' : '' }}">
                <i class="fas fa-gauge-high"></i>
                <span>Dashboard</span>
            </a>
            <a href="{{ route('attendance.leaves') }}" class="footer-nav-link {{ request()->routeIs('attendance.*') ? 'active' : '' }}">
                <i class="fas fa-clipboard-check"></i>
                <span>Attend</span>
            </a>
            <a href="{{ route('execution-desk.index') }}" class="footer-nav-link {{ request()->routeIs('execution-desk.*') ? 'active' : '' }}">
                <i class="fas fa-list-check"></i>
                <span>Tasks</span>
            </a>
            @if($__canRaisePurchaseOrder)
            <a href="{{ route('purchase-orders.index') }}" class="footer-nav-link {{ request()->routeIs('purchase-orders.*') ? 'active' : '' }}">
                <i class="fas {{ $__canRaiseReimbursement && !$__canRaiseNeedPurchase ? 'fa-receipt' : 'fa-file-signature' }}"></i>
                <span>{{ $__canRaiseReimbursement && !$__canRaiseNeedPurchase ? 'Reimburse' : 'PO' }}</span>
            </a>
            @endif
            <a href="{{ route('marketing.dashboard') }}#marketing-todos" class="footer-nav-link">
                <i class="fas fa-note-sticky"></i>
                <span>To-Do</span>
            </a>
            <a href="{{ route('marketing.profile') }}" class="footer-nav-link {{ request()->routeIs('marketing.profile') ? 'active' : '' }}">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
        @elseif(auth()->user()->isLeadQualityAuditor())
            <a href="{{ route('lead-quality-auditor.activity-calendar.index') }}" class="footer-nav-link {{ request()->routeIs('lead-quality-auditor.activity-calendar.*') ? 'active' : '' }}">
                <i class="fas fa-calendar-days"></i>
                <span>Calendar</span>
            </a>
            <a href="{{ route('admin.insight-sheet.index') }}" class="footer-nav-link {{ request()->routeIs('admin.insight-sheet.*') ? 'active' : '' }}">
                <i class="fas fa-table-cells-large"></i>
                <span>Sheet</span>
            </a>
            <a href="{{ route('attendance.leaves') }}" class="footer-nav-link {{ request()->routeIs('attendance.leaves') ? 'active' : '' }}">
                <i class="fas fa-calendar-plus"></i>
                <span>Leave</span>
            </a>
            <a href="{{ route('attendance.regularizations') }}" class="footer-nav-link {{ request()->routeIs('attendance.regularizations') ? 'active' : '' }}">
                <i class="fas fa-rotate"></i>
                <span>Regularize</span>
            </a>
            <a href="{{ route('lead-quality-auditor.profile') }}" class="footer-nav-link {{ request()->routeIs('lead-quality-auditor.profile') ? 'active' : '' }}">
                <i class="fas fa-user-circle"></i>
                <span>Profile</span>
            </a>
        @elseif(auth()->user()->isSalesHead())
            <a href="{{ route('sales-head.dashboard') }}" class="footer-nav-link {{ request()->routeIs('sales-head.dashboard') ? 'active' : '' }}">
                <i class="fas fa-home"></i>
                <span>Home</span>
            </a>
            <a href="{{ route('users.index') }}" class="footer-nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                <i class="fas fa-users"></i>
                <span>Team</span>
            </a>
            <a href="{{ route('leads.index') }}" class="footer-nav-link {{ request()->routeIs('leads.*') || request()->routeIs('prospects.*') || request()->routeIs('meetings.*') || request()->routeIs('site-visits.*') || request()->routeIs('closers.*') ? 'active' : '' }}">
                <i class="fas fa-user-friends"></i>
                <span>Leads</span>
            </a>
            <a href="{{ route('crm.verifications') }}" class="footer-nav-link {{ request()->routeIs('crm.verifications') ? 'active' : '' }}">
                <i class="fas fa-check-circle"></i>
                <span>Verify</span>
            </a>
            <a href="{{ route('admin.targets.index') }}" class="footer-nav-link {{ request()->routeIs('admin.targets.*') ? 'active' : '' }}">
                <i class="fas fa-bullseye"></i>
                <span>Targets</span>
            </a>
            <a href="{{ route('calls.index') }}" class="footer-nav-link {{ request()->routeIs('calls.*') ? 'active' : '' }}">
                <i class="fas fa-phone"></i>
                <span>Calls</span>
            </a>
            <a href="{{ route('export.index') }}" class="footer-nav-link {{ request()->routeIs('export.*') ? 'active' : '' }}">
                <i class="fas fa-download"></i>
                <span>Export</span>
            </a>
            <a href="{{ route('support.index') }}" class="footer-nav-link {{ request()->routeIs('support.*') ? 'active' : '' }}">
                <i class="fas fa-life-ring"></i>
                <span>Support</span>
            </a>
            <a href="{{ route('execution-desk.index') }}" class="footer-nav-link {{ request()->routeIs('execution-desk.*') ? 'active' : '' }}">
                <i class="fas fa-list-check"></i>
                <span>Exec Desk</span>
            </a>
            @if(auth()->user()->isAdmin() || auth()->user()->isCrm())
            <a href="{{ route('admin.broadcast') }}" class="footer-nav-link {{ request()->routeIs('admin.broadcast') ? 'active' : '' }}">
                <i class="fas fa-bullhorn"></i>
                <span>Announce</span>
            </a>
            @endif
        @else
            <a href="{{ route('dashboard') }}" class="footer-nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i class="fas fa-home"></i>
                <span>Home</span>
            </a>
            @if($__canRaisePurchaseOrder)
            <a href="{{ route('purchase-orders.index') }}" class="footer-nav-link {{ request()->routeIs('purchase-orders.*') ? 'active' : '' }}">
                <i class="fas {{ $__canRaiseReimbursement && !$__canRaiseNeedPurchase ? 'fa-receipt' : 'fa-file-signature' }}"></i>
                <span>{{ $__canRaiseReimbursement && !$__canRaiseNeedPurchase ? 'Reimburse' : 'PO' }}</span>
            </a>
            @endif
            @if(!auth()->user()->isTelecaller())
            <a href="{{ route('users.index') }}" class="footer-nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                <i class="fas fa-users"></i>
                <span>Users</span>
            </a>
            @endif
            <a href="{{ route('leads.index') }}" class="footer-nav-link {{ request()->routeIs('leads.*') || request()->routeIs('prospects.*') || request()->routeIs('meetings.*') || request()->routeIs('site-visits.*') || request()->routeIs('closers.*') ? 'active' : '' }}">
                <i class="fas fa-user-friends"></i>
                <span>Leads</span>
            </a>
            <a href="{{ route('execution-desk.index') }}" class="footer-nav-link {{ request()->routeIs('execution-desk.*') ? 'active' : '' }}">
                <i class="fas fa-list-check"></i>
                <span>Exec Desk</span>
            </a>
            @if(auth()->user()->isAdmin() || auth()->user()->isCrm() || auth()->user()->isSalesManager() || auth()->user()->isSalesHead())
            <a href="{{ route('export.index') }}" class="footer-nav-link {{ request()->routeIs('export.*') ? 'active' : '' }}">
                <i class="fas fa-download"></i>
                <span>Export</span>
            </a>
            @endif
            @if(auth()->user()->isAdmin())
            <a href="{{ route('admin.profile') }}" class="footer-nav-link {{ request()->routeIs('admin.profile') ? 'active' : '' }}">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
            @else
            <a href="{{ route('dashboard') }}" class="footer-nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
            @endif
        @endif
    </nav>
    @if(auth()->user()->isAdmin() || auth()->user()->isCrm())
    <div class="admin-mobile-nav-scroll-hint" aria-hidden="true"></div>
    @endif

    @include('components.password-change-required-modal')

    @stack('scripts')
    <script src="{{ asset('js/branding-update.js') }}?v={{ @filemtime(public_path('js/branding-update.js')) ?: time() }}"></script>
    <script>
        // Live Clock Functionality (header + CRM compact clock when present)
        function updateClock() {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            const timeStr = `${hours}:${minutes}:${seconds}`;
            const dateStr = now.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
            const timeElement = document.getElementById('clockTime');
            const dateElement = document.getElementById('clockDate');
            if (timeElement && dateElement) {
                timeElement.textContent = timeStr;
                dateElement.textContent = dateStr;
            }
            const adminTime = document.getElementById('adminClockTime');
            const adminDate = document.getElementById('adminClockDate');
            if (adminTime && adminDate) {
                adminTime.textContent = timeStr;
                adminDate.textContent = dateStr;
            }
            const crmTime = document.getElementById('crmClockTime');
            const crmDate = document.getElementById('crmClockDate');
            if (crmTime && crmDate) {
                crmTime.textContent = timeStr;
                crmDate.textContent = dateStr;
            }
        }
        
        // Update clock immediately and then every second
        updateClock();
        setInterval(updateClock, 1000);
        
        window.openMobileSidebar = function() {
            document.body.classList.add('mobile-sidebar-open');
        };

        window.closeMobileSidebar = function() {
            document.body.classList.remove('mobile-sidebar-open');
        };

        const mobileShellBreakpoint = 820;

        function applyResponsiveShell() {
            const isMobileShell = window.innerWidth <= mobileShellBreakpoint;
            const mainContent = document.getElementById('mainContent');

            if (isMobileShell) {
                document.documentElement.classList.remove('pre-nav-text', 'pre-nav-icons');
                document.body.classList.remove('nav-icons', 'nav-text', 'sidebar-hidden');
                document.documentElement.style.setProperty('--nav-width', '0px');

                if (mainContent) {
                    mainContent.style.setProperty('margin-left', '0', 'important');
                    mainContent.style.setProperty('width', '100%', 'important');
                    mainContent.style.setProperty('max-width', '100%', 'important');
                    mainContent.style.setProperty('min-width', '0', 'important');
                }

                return true;
            }

            document.documentElement.style.removeProperty('--nav-width');
            if (mainContent) {
                mainContent.style.removeProperty('margin-left');
                mainContent.style.removeProperty('width');
                mainContent.style.removeProperty('max-width');
                mainContent.style.removeProperty('min-width');
            }

            return false;
        }

        // Initialize sidebar functionality when DOM is ready
        function initSidebar() {
            if (window._sidebarAlreadyInit) return;
            window._sidebarAlreadyInit = true;
            if (!applyResponsiveShell()) {
                document.body.classList.remove('nav-icons');
                document.body.classList.add('nav-text');
            }

            const navToggle = document.getElementById('navModeToggle');
            const navToggleLabel = document.getElementById('navModeToggleLabel');
            const navToggleIcon = document.getElementById('navModeToggleIcon');
            const edgeSidebarToggle = document.getElementById('sidebarToggle');
            const edgeSidebarToggleIcon = edgeSidebarToggle ? edgeSidebarToggle.querySelector('.sidebar-toggle-icon') : null;

            function applySidebarVisibility() {
                const shouldHide = window.innerWidth > mobileShellBreakpoint && localStorage.getItem('sidebar_hidden') === '1';
                document.body.classList.toggle('sidebar-hidden', shouldHide);
                if (navToggle && navToggleLabel && navToggleIcon) {
                    navToggleLabel.textContent = shouldHide ? 'Show Nav' : 'Hide Nav';
                    navToggle.title = shouldHide ? 'Show navigation' : 'Hide navigation';
                    navToggleIcon.className = shouldHide ? 'fas fa-eye' : 'fas fa-eye-slash';
                }
                if (edgeSidebarToggle && edgeSidebarToggleIcon) {
                    edgeSidebarToggle.setAttribute('aria-label', shouldHide ? 'Show navigation' : 'Hide navigation');
                    edgeSidebarToggle.setAttribute('title', shouldHide ? 'Show navigation' : 'Hide navigation');
                    edgeSidebarToggleIcon.className = shouldHide ? 'fas fa-chevron-right sidebar-toggle-icon' : 'fas fa-chevron-left sidebar-toggle-icon';
                }
            }

            applySidebarVisibility();

            function toggleSidebarVisibility() {
                if (window.innerWidth <= mobileShellBreakpoint) {
                    return;
                }
                const nextHidden = !document.body.classList.contains('sidebar-hidden');
                localStorage.setItem('sidebar_hidden', nextHidden ? '1' : '0');
                applySidebarVisibility();
            }

            if (navToggle) {
                navToggle.addEventListener('click', function() {
                    toggleSidebarVisibility();
                });
            }

            if (edgeSidebarToggle) {
                edgeSidebarToggle.addEventListener('click', function() {
                    if (window.innerWidth <= mobileShellBreakpoint) {
                        return;
                    }
                    toggleSidebarVisibility();
                });
            }

            document.addEventListener('click', function(e) {
                if (window.innerWidth > mobileShellBreakpoint) {
                    return;
                }
                const sidebar = document.getElementById('sidebar');
                if (!document.body.classList.contains('mobile-sidebar-open') || !sidebar) {
                    return;
                }
                if (!sidebar.contains(e.target) && !e.target.closest('.mobile-nav-toggle')) {
                    closeMobileSidebar();
                }
            });

            // Enable transitions after layout is fully set (prevents page-load flicker)
            requestAnimationFrame(function() {
                requestAnimationFrame(function() {
                    document.body.classList.remove('no-transition');
                    document.body.classList.add('sidebar-ready');
                });
            });
        }
        
        // Run when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initSidebar);
        } else {
            // DOM is already ready
            initSidebar();
        }
        window.addEventListener('resize', function() {
            const isMobileShell = applyResponsiveShell();
            if (!isMobileShell) {
                closeMobileSidebar();
            }
            const shouldHide = !isMobileShell && localStorage.getItem('sidebar_hidden') === '1';
            document.body.classList.toggle('sidebar-hidden', shouldHide);
        });

        document.querySelectorAll('[data-admin-menu-trigger]').forEach(function(trigger) {
            const targetId = trigger.getAttribute('data-admin-menu-trigger');
            const subMenu = targetId ? document.getElementById(targetId) : null;
            const icon = trigger.querySelector('.menu-chevron');
            const isActiveGroup = trigger.getAttribute('data-menu-active') === '1' || trigger.classList.contains('sidebar-parent-open');
            const storageKey = targetId ? 'sidebar_tree_' + targetId : null;

            function setAdminMenuState(shouldOpen) {
                if (!subMenu) return;
                subMenu.style.display = shouldOpen ? 'block' : 'none';
                trigger.classList.toggle('sidebar-parent-open', shouldOpen);
                trigger.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
                if (icon) {
                    icon.style.transform = shouldOpen ? 'rotate(180deg)' : 'rotate(0deg)';
                }
            }

            if (subMenu) {
                const persisted = storageKey ? localStorage.getItem(storageKey) : null;
                if (isActiveGroup) {
                    setAdminMenuState(true);
                    if (storageKey) localStorage.setItem(storageKey, '1');
                } else if (persisted === '1' || persisted === '0') {
                    setAdminMenuState(persisted === '1');
                }
            }

            trigger.addEventListener('click', function() {
                if (!subMenu) return;

                const isOpen = window.getComputedStyle(subMenu).display !== 'none';
                setAdminMenuState(!isOpen);
                if (storageKey) {
                    localStorage.setItem(storageKey, isOpen ? '0' : '1');
                }
            });
        });
        
        function toggleAdvisorProfilesMenu() {
            const subMenu = document.getElementById('advisorProfilesSubMenu');
            const icon = document.getElementById('advisorProfilesMenuIcon');
            const trigger = icon ? icon.closest('.sidebar-link') : null;
            if (subMenu && icon) {
                if (subMenu.style.display === 'none') {
                    subMenu.style.display = 'block';
                    icon.style.transform = 'rotate(180deg)';
                    if (trigger) trigger.classList.add('active');
                } else {
                    subMenu.style.display = 'none';
                    icon.style.transform = 'rotate(0deg)';
                    if (trigger) trigger.classList.remove('active');
                }
            }
        }

        // Toggle Projects sub-menu
        function toggleProjectsMenu() {
            const subMenu = document.getElementById('projectsSubMenu');
            const icon = document.getElementById('projectsMenuIcon');
            const trigger = icon ? icon.closest('.sidebar-link') : null;
            if (subMenu && icon) {
                if (subMenu.style.display === 'none') {
                    subMenu.style.display = 'block';
                    icon.style.transform = 'rotate(180deg)';
                    if (trigger) trigger.classList.add('sidebar-parent-open');
                } else {
                    subMenu.style.display = 'none';
                    icon.style.transform = 'rotate(0deg)';
                    if (trigger) trigger.classList.remove('sidebar-parent-open');
                }
            }
        }
        
        function toggleLeadsMenu() {
            const subMenu = document.getElementById('leadsSubMenu');
            const icon = document.getElementById('leadsMenuIcon');
            const trigger = icon ? icon.closest('.sidebar-link') : null;
            if (subMenu && icon) {
                if (subMenu.style.display === 'none') {
                    subMenu.style.display = 'block';
                    icon.style.transform = 'rotate(180deg)';
                    if (trigger) trigger.classList.add('sidebar-parent-open');
                } else {
                    subMenu.style.display = 'none';
                    icon.style.transform = 'rotate(0deg)';
                    if (trigger) trigger.classList.remove('sidebar-parent-open');
                }
            }
        }

    </script>
    
    @auth
    <!-- Lead assigned modal (global, dismissible) -->
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
                <a id="lead-assigned-view-btn" href="{{ (auth()->user() && (auth()->user()->isTelecaller() || auth()->user()->isSalesExecutive())) ? route('telecaller.tasks').'?status=pending' : route('leads.index') }}" class="px-4 py-2 rounded-lg font-semibold text-white transition" style="background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));">View leads</a>
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
        var shownRealtimeNotificationIds = new Set();
        var browserNotificationPermission = false;

        function apiAuthHeaders() {
            var headers = {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            };
            var csrf = document.querySelector('meta[name="csrf-token"]');
            if (csrf && csrf.content) headers['X-CSRF-TOKEN'] = csrf.content;
            var apiToken = document.querySelector('meta[name="api-token"]');
            var bearer = (apiToken && apiToken.content) || localStorage.getItem('telecaller_token') || localStorage.getItem('sales_manager_token') || '';
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

        function ensureBrowserNotificationPermission() {
            if (typeof Notification === 'undefined') return false;
            if (Notification.permission === 'granted') {
                browserNotificationPermission = true;
                return true;
            }
            return false;
        }

        function showDesktopBrowserNotification(options) {
            if (!ensureBrowserNotificationPermission()) return;
            try {
                var notification = new Notification(options.title || 'Reminder', {
                    body: options.message || '',
                    icon: '/favicon.ico',
                    badge: '/favicon.ico',
                    tag: options.tag || 'crm-reminder',
                    data: { url: options.viewUrl || viewUrlDefault || '' },
                    requireInteraction: true
                });
                notification.onclick = function(event) {
                    event.preventDefault();
                    window.focus();
                    if (notification.data && notification.data.url) {
                        window.location.href = notification.data.url;
                    }
                    notification.close();
                };
            } catch (e) {}
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

        if (document.getElementById('lead-assigned-close-x')) {
            document.getElementById('lead-assigned-close-x').addEventListener('click', closeLeadAssignedModal);
        }
        if (completeBtn) completeBtn.onclick = closeLeadAssignedModal;
        if (overlay) {
            overlay.addEventListener('click', function(e) {
                if (e.target === overlay) closeLeadAssignedModal();
            });
        }

        var uid = document.querySelector('meta[name="user-id"]') && document.querySelector('meta[name="user-id"]').getAttribute('content');
        var pk = document.querySelector('meta[name="pusher-key"]') && document.querySelector('meta[name="pusher-key"]').getAttribute('content');
        if (uid && pk && typeof Pusher !== 'undefined') {
            try {
                var pusher = new Pusher(pk, {
                    cluster: (document.querySelector('meta[name="pusher-cluster"]') && document.querySelector('meta[name="pusher-cluster"]').getAttribute('content')) || 'mt1',
                    encrypted: true,
                    authEndpoint: '/broadcasting/auth'
                });
                var ch = pusher.subscribe('private-user.' + uid);
                ch.bind('lead.assigned', function(data) {
                    var lead = data.lead || {};
                    var name = lead.name || 'Lead';
                    var phone = lead.phone || '';
                    showLeadAssignedPopup({
                        title: 'New lead assigned',
                        message: 'You have 1 new lead assigned: ' + name + '. View leads to see details and call.',
                        viewUrl: viewUrlDefault,
                        leadPhone: phone,
                        leadName: name,
                        secondaryActionLabel: 'Open Lead',
                        secondaryActionUrl: viewUrlDefault,
                        secondaryActionMethod: 'GET',
                        soundUrl: data.sound_url || ''
                    });
                });
                ch.bind('notification.new', function(payload) {
                    var notification = payload && payload.notification ? payload.notification : null;
                    if (!notification || shownRealtimeNotificationIds.has(notification.id)) {
                        return;
                    }

                    if ([
                        'call_reminder',
                        'new_lead',
                        'followup_reminder',
                        'followup_overdue',
                        'meeting',
                        'meeting_reminder',
                        'site_visit',
                        'site_visit_reminder',
                        'task_overdue',
                        'execution_task_due',
                        'execution_task_overdue'
                    ].indexOf(notification.type) === -1) {
                        return;
                    }

                    shownRealtimeNotificationIds.add(notification.id);

                    var actionUrl = notification.action_url || viewUrlDefault || '';
                    var data = notification.data || {};
                    showLeadAssignedPopup({
                        title: notification.title || 'Reminder',
                        message: notification.message || '',
                        viewUrl: actionUrl,
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
                    showDesktopBrowserNotification({
                        title: notification.title || 'Reminder',
                        message: notification.message || '',
                        viewUrl: actionUrl,
                        tag: notification.type + '-' + notification.id
                    });
                });
            } catch (e) { console.warn('Pusher lead-assigned:', e); }
        }
    })();
    </script>
    @endauth

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

        function getAuthToken() {
            var meta = document.querySelector('meta[name="api-token"]');
            if (meta && meta.content) return meta.content;
            try { return localStorage.getItem('telecaller_token') || localStorage.getItem('auth_token') || ''; } catch(e) { return ''; }
        }

        function getFcmStorageKey() {
            var userId = '';
            try {
                userId = String(window.currentUserId || window.userId || document.body?.dataset?.userId || '');
            } catch (e) {}
            return 'app_fcm_token_sent:' + userId;
        }

        function sendFcmTokenToServer(fcmToken) {
            if (window.NotificationDeviceOwnership) {
                window.NotificationDeviceOwnership.claimFcm(fcmToken);
                return;
            }
            var authToken = getAuthToken();
            if (!authToken) return;
            try {
                var cacheKey = getFcmStorageKey();
                localStorage.removeItem(cacheKey);
            } catch (e) {}
            var url = window.location.origin + '/api/fcm-subscription';
            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'Authorization': 'Bearer ' + authToken },
                body: JSON.stringify({ fcm_token: fcmToken, device_type: 'web' })
            }).then(function(r) {
                if (r.ok) {
                    try {
                        localStorage.setItem(getFcmStorageKey(), fcmToken);
                    } catch (e) {}
                    if (window.NotificationDeviceOwnership) {
                        window.NotificationDeviceOwnership.claimFcm(fcmToken);
                    }
                }
            }).catch(function() {});
        }

        function initFcm() {
            navigator.serviceWorker.register('/fcm-sw.js').then(function(reg) {
                messaging.getToken({ vapidKey: vapidKey, serviceWorkerRegistration: reg }).then(function(token) {
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
            if ((
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
            ) && typeof showLeadAssignedPopup === 'function') {
                var n = payload.notification || payload.data || {};
                showLeadAssignedPopup({
                    title: n.title || title || 'Call Reminder',
                    message: n.body || data.body || '',
                    viewUrl: data.secondary_action_url || n.url || n.click_action || viewUrlDefault,
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
        });

        if (Notification.permission === 'granted') { initFcm(); }
        else if (Notification.permission === 'default') {
            Notification.requestPermission().then(function(p) { if (p === 'granted') initFcm(); });
        }
    })();
    </script>
    <div id='fcm-banner' style='display:none;position:fixed;bottom:20px;right:20px;background:#1d4ed8;color:#fff;padding:14px 20px;border-radius:10px;z-index:9999;box-shadow:0 4px 12px rgba(0,0,0,0.2);font-size:14px;max-width:320px;'>
        <p style='margin:0 0 8px 0;font-weight:700;font-size:15px;'>🔔 Notifications Enable Karo</p>
        <p style='margin:0 0 12px 0;font-size:13px;opacity:0.9;'>Lead assignments ke liye browser notifications allow karo.</p>
        <button onclick='enableFcmNotifications()' style='background:#fff;color:#1d4ed8;border:none;padding:8px 16px;border-radius:6px;font-weight:600;cursor:pointer;margin-right:8px;'>Allow</button>
        <button onclick='document.getElementById("fcm-banner").style.display="none"' style='background:transparent;color:#fff;border:1px solid rgba(255,255,255,0.5);padding:8px 16px;border-radius:6px;cursor:pointer;'>Dismiss</button>
    </div>
    <script>
    function enableFcmNotifications() {
        Notification.requestPermission().then(function(p) {
            if (p === 'granted') {
                document.getElementById('fcm-banner').style.display = 'none';
                location.reload();
            } else {
                alert('Please allow notifications from browser settings (site settings > notifications).');
            }
        });
    }
    window.addEventListener('load', function() {
        setTimeout(function() {
            if (typeof Notification !== 'undefined' && Notification.permission !== 'granted') {
                document.getElementById('fcm-banner').style.display = 'block';
            }
        }, 2500);
    });
    </script>

    <script>
    // Fix bfcache: if browser shows stale cached page, force fresh reload
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            window.location.reload();
        }
    });
    </script>
    @auth
    <script>
    window.CRM_PHONE_MASKED = @json(app(\App\Services\PhonePrivacyService::class)->shouldMask(auth()->user()));
    window.openProtectedLeadWhatsApp = async function(leadId, message = '') {
        if (!leadId) return false;
        const popup = window.open('about:blank', '_blank');
        try {
            const token = typeof window.getToken === 'function' ? window.getToken() : (window.API_TOKEN || localStorage.getItem('api_token') || '');
            const response = await fetch(`/api/leads/${leadId}/whatsapp-direct`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    ...(token ? { Authorization: `Bearer ${token}` } : {}),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ message }),
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok || !result.success || !result.url) throw new Error(result.message || 'WhatsApp is unavailable.');
            if (popup) popup.location.href = result.url; else window.location.href = result.url;
            return true;
        } catch (error) {
            if (popup) popup.close();
            if (typeof window.showAlert === 'function') window.showAlert(error.message, 'warning'); else alert(error.message);
            return false;
        }
    };
    </script>
    @endauth
</body>
</html>

    <!-- Browser compatibility warning -->
    <script>
    window.addEventListener('load', function() {
        if (navigator.brave && navigator.brave.isBrave) {
            navigator.brave.isBrave().then(function(brave) {
                if (brave) {
                    var div = document.createElement('div');
                    div.innerHTML = '<div style="position:fixed;top:0;left:0;right:0;background:#f59e0b;color:#000;text-align:center;padding:10px;z-index:99999;font-size:13px;font-weight:600;">⚠️ Brave browser mein notifications kaam nahi karti. Please Chrome ya Firefox use karein.</div>';
                    document.body.prepend(div);
                }
            });
        }
    });
    </script>

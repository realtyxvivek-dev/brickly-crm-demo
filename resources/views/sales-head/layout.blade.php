<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Associate Director - ' . brand_name())</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        if (auth()->check() && !session('api_token')) {
            $__token = auth()->user()->createToken('web-session-token')->plainTextToken;
            session(['api_token' => $__token]);
        }
    @endphp
    <meta name="api-token" content="{{ session('api_token', '') }}">
    <meta name="user-id" content="{{ auth()->check() ? auth()->user()->id : '' }}">
    <meta name="pusher-key" content="{{ config('broadcasting.connections.pusher.key') }}">
    <meta name="pusher-cluster" content="{{ config('broadcasting.connections.pusher.options.cluster', 'mt1') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #F7F6F3; }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .header { background: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
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
        @media (max-width: 768px) {
            .container { margin-left: 0; padding: 10px; }
        }
    </style>
    @stack('styles')
</head>
<body>
    <!-- Sidebar -->
    <aside id="sidebar" class="fixed left-0 top-0 h-full w-64 bg-white border-r border-gray-200 shadow-sm z-30" style="overflow-y: auto;">
        <div style="padding: 20px; margin-bottom: 30px;">
            <h2 style="font-size: 24px; font-weight: 700; color: #063A1C; margin-bottom: 10px;">{{ brand_name() }}</h2>
            <p style="font-size: 12px; color: #B3B5B4;">Associate Director</p>
        </div>
        <nav style="padding: 0 20px;">
            <a href="{{ route('sales-head.dashboard') }}" class="sidebar-link {{ request()->routeIs('sales-head.dashboard') ? 'active' : '' }}">
                <i class="fas fa-home" style="margin-right: 10px; width: 20px;"></i>
                Dashboard
            </a>
            <a href="{{ route('users.index') }}" class="sidebar-link">
                <i class="fas fa-users" style="margin-right: 10px; width: 20px;"></i>
                All Users
            </a>
            <a href="{{ route('leads.index') }}" class="sidebar-link">
                <i class="fas fa-user-friends" style="margin-right: 10px; width: 20px;"></i>
                All Leads
            </a>
            <a href="{{ route('crm.verifications') }}" class="sidebar-link">
                <i class="fas fa-check-circle" style="margin-right: 10px; width: 20px;"></i>
                Verifications
            </a>
            <a href="{{ route('admin.targets.index') }}" class="sidebar-link">
                <i class="fas fa-bullseye" style="margin-right: 10px; width: 20px;"></i>
                Targets
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <div style="margin-left: 64px; min-height: 100vh;">
        <div class="container">
            <!-- Header -->
            <div class="header">
                <div>
                    <h1 style="font-size: 28px; font-weight: 700; color: #063A1C;">@yield('page-title', 'Associate Director Dashboard')</h1>
                </div>
                <div style="display: flex; align-items: center; gap: 15px;">
                    <!-- Date/Time Clock -->
                    <div id="datetimeClock" style="background: white; border: 1px solid #e0e0e0; border-radius: 8px; padding: 8px 12px; font-family: 'Courier New', monospace; font-weight: 600; font-size: 14px; color: #063A1C; min-width: 160px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                        <div id="clockTime" style="font-size: 16px; color: #205A44;">--:--:--</div>
                        <div id="clockDate" style="font-size: 11px; color: #B3B5B4; margin-top: 2px;">-- -- ----</div>
                    </div>
                    @include('components.global-notification-center')
                    <span style="color: #B3B5B4; font-size: 14px;">{{ auth()->user()->name }}</span>
                    <form action="{{ route('logout') }}" method="POST" style="display: inline;">
                        @csrf
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-sign-out-alt" style="margin-right: 5px;"></i>
                            Logout
                        </button>
                    </form>
                </div>
            </div>

            @yield('content')
        </div>
    </div>

    <!-- Custom Notification System -->
    <div id="notificationOverlay" class="fixed inset-0 z-[9999] pointer-events-none flex items-center justify-center" style="display: none; background: rgba(0,0,0,0.5);">
        <div id="customNotification" class="bg-white rounded-lg shadow-2xl p-6 max-w-md w-full mx-4 transform transition-all duration-300 scale-0" style="pointer-events: auto;">
            <div class="flex items-center justify-center mb-4">
                <div class="tick-icon w-16 h-16 rounded-full flex items-center justify-center bg-green-100">
                    <i class="fas fa-check text-green-600 text-2xl"></i>
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
            
            // Update icon based on type
            if (type === 'error') {
                tickIcon.innerHTML = '<i class="fas fa-times text-red-600 text-2xl"></i>';
                tickIcon.className = 'tick-icon w-16 h-16 rounded-full flex items-center justify-center bg-red-100';
            } else if (type === 'warning') {
                tickIcon.innerHTML = '<i class="fas fa-exclamation-triangle text-yellow-600 text-2xl"></i>';
                tickIcon.className = 'tick-icon w-16 h-16 rounded-full flex items-center justify-center bg-yellow-100';
            } else {
                tickIcon.innerHTML = '<i class="fas fa-check text-green-600 text-2xl"></i>';
                tickIcon.className = 'tick-icon w-16 h-16 rounded-full flex items-center justify-center bg-green-100';
            }
            
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
    </script>

    @stack('scripts')
    
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
    </script>
</body>
</html>

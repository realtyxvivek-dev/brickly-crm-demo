<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Finance Workspace - ' . brand_name())</title>

    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">

    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        if (auth()->check() && !session('api_token')) {
            $__token = auth()->user()->createToken('web-session-token')->plainTextToken;
            session(['api_token' => $__token]);
        }
    @endphp
    <meta name="api-token" content="{{ session('api_token', '') }}">
    <meta name="user-id" content="{{ auth()->check() ? auth()->user()->id : '' }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            --finance-bg: #f5f2ea;
            --finance-card: #ffffff;
            --finance-text: #0b2e20;
            --finance-muted: #61706a;
            --finance-line: #ddd7ca;
            --finance-panel: #f7f5ee;
            --finance-accent: #0f5b42;
            --finance-accent-dark: #083725;
            --finance-accent-soft: #e8f2ec;
            --finance-danger: #ef4444;
            --finance-warning: #f59e0b;
            --finance-success: #16a34a;
            --finance-shadow: 0 10px 30px rgba(10, 25, 16, 0.07);
        }

        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; min-height: 100%; }
        body {
            font-family: 'Manrope', sans-serif;
            background:
                radial-gradient(circle at top left, rgba(15, 91, 66, 0.08), transparent 28%),
                linear-gradient(180deg, #f7f3eb 0%, var(--finance-bg) 100%);
            color: var(--finance-text);
        }

        .finance-shell {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 280px minmax(0, 1fr);
        }

        .finance-sidebar {
            background: linear-gradient(180deg, #0b2d20 0%, #082118 100%);
            color: #eff8f2;
            padding: 28px 22px;
            position: sticky;
            top: 0;
            height: 100vh;
            display: flex;
            flex-direction: column;
            gap: 22px;
            border-right: 1px solid rgba(255, 255, 255, 0.06);
        }

        .finance-brand {
            display: flex;
            align-items: center;
            gap: 14px;
            padding-bottom: 18px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .finance-brand-mark {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            background: linear-gradient(135deg, #2f855f, #0f5b42);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 18px;
            color: #fff;
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.16);
        }

        .finance-brand h1 {
            margin: 0;
            font-size: 15px;
            font-weight: 800;
            letter-spacing: 0.01em;
        }

        .finance-brand p {
            margin: 5px 0 0;
            font-size: 11px;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: rgba(239, 248, 242, 0.62);
        }

        .finance-sidebar-section {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .finance-sidebar-label {
            font-size: 11px;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: rgba(239, 248, 242, 0.34);
            padding: 0 8px;
        }

        .finance-sidebar-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 16px;
            border-radius: 16px;
            color: rgba(239, 248, 242, 0.9);
            text-decoration: none;
            transition: 0.2s ease;
        }

        .finance-sidebar-link:hover,
        .finance-sidebar-link.is-active {
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
        }

        .finance-sidebar-link-main {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .finance-sidebar-link i {
            width: 18px;
            text-align: center;
            color: #9dd2b8;
        }

        .finance-sidebar-link span {
            font-size: 15px;
            font-weight: 700;
        }

        .finance-sidebar-pill {
            min-width: 28px;
            height: 28px;
            border-radius: 999px;
            background: rgba(239, 68, 68, 0.18);
            color: #ffd5d5;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 800;
            padding: 0 8px;
        }

        .finance-sidebar-footer {
            margin-top: auto;
            padding-top: 18px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            display: grid;
            gap: 12px;
        }

        .finance-sidebar-user {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.06);
            color: #fff;
        }

        .finance-sidebar-user-mark {
            width: 34px;
            height: 34px;
            border-radius: 12px;
            background: rgba(157, 210, 184, 0.18);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 800;
            color: #dff8eb;
            flex: 0 0 auto;
        }

        .finance-sidebar-user-name {
            font-size: 13px;
            line-height: 1.2;
            font-weight: 800;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .finance-sidebar-user-role {
            margin-top: 3px;
            font-size: 11px;
            color: rgba(239, 248, 242, 0.58);
            font-weight: 700;
        }

        .finance-sidebar-logout {
            width: 100%;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 14px;
            padding: 12px 14px;
            background: rgba(239, 68, 68, 0.16);
            color: #ffd5d5;
            font-size: 13px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
        }

        .finance-main {
            min-width: 0;
            padding: 28px;
            display: flex;
            flex-direction: column;
            gap: 22px;
        }

        .finance-header {
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(8px);
            border: 1px solid var(--finance-line);
            border-radius: 26px;
            padding: 24px 28px;
            box-shadow: var(--finance-shadow);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
        }

        .finance-header-kicker {
            margin: 0 0 10px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.2em;
            color: #6f7f79;
            font-weight: 800;
        }

        .finance-header-title {
            margin: 0;
            font-size: 42px;
            line-height: 1;
            font-weight: 800;
            letter-spacing: -0.03em;
            color: var(--finance-text);
        }

        .finance-header-subtitle {
            margin: 10px 0 0;
            font-size: 15px;
            color: var(--finance-muted);
            max-width: 720px;
        }

        .finance-header-actions {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .finance-clock {
            min-width: 170px;
            padding: 16px 18px;
            border-radius: 18px;
            border: 1px solid var(--finance-line);
            background: #fff;
            text-align: center;
            box-shadow: 0 6px 18px rgba(10, 25, 16, 0.06);
        }

        .finance-clock-time {
            font-family: 'IBM Plex Mono', monospace;
            font-size: 28px;
            font-weight: 600;
            color: var(--finance-accent);
            line-height: 1;
        }

        .finance-clock-date {
            margin-top: 8px;
            font-size: 12px;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: #8a968f;
            font-weight: 700;
        }

        .finance-user {
            font-size: 15px;
            color: #92a09a;
            font-weight: 700;
        }

        .finance-btn {
            border: none;
            border-radius: 16px;
            padding: 14px 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-weight: 800;
            font-size: 14px;
            text-decoration: none;
            cursor: pointer;
            transition: 0.2s ease;
            white-space: nowrap;
        }

        .finance-btn:hover { transform: translateY(-1px); }
        .finance-btn-primary { background: linear-gradient(135deg, var(--finance-accent-dark), var(--finance-accent)); color: #fff; }
        .finance-btn-soft { background: #fff; color: var(--finance-accent); border: 1px solid var(--finance-line); }
        .finance-btn-success { background: linear-gradient(135deg, #15803d, #16a34a); color: #fff; }
        .finance-btn-danger { background: linear-gradient(135deg, #b91c1c, #ef4444); color: #fff; }

        .finance-page {
            display: grid;
            gap: 22px;
        }

        .finance-flash {
            padding: 14px 18px;
            border-radius: 16px;
            font-size: 14px;
            font-weight: 700;
            border: 1px solid;
        }

        .finance-flash-success { background: #ecfdf5; color: #166534; border-color: #a7f3d0; }
        .finance-flash-error { background: #fff1f2; color: #be123c; border-color: #fecdd3; }

        @media (max-width: 1180px) {
            .finance-shell { grid-template-columns: 1fr; }
            .finance-sidebar {
                height: auto;
                position: static;
                border-right: none;
                border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            }
            .finance-main { padding: 20px; }
            .finance-header { flex-direction: column; align-items: flex-start; }
            .finance-header-actions { width: 100%; justify-content: space-between; }
        }

        @media (max-width: 700px) {
            .finance-main { padding: 14px; }
            .finance-header { padding: 18px; border-radius: 20px; }
            .finance-header-title { font-size: 30px; }
            .finance-header-subtitle { font-size: 14px; }
            .finance-header-actions { justify-content: stretch; }
            .finance-clock { width: 100%; }
            .finance-btn { width: 100%; }
        }
    </style>
    @stack('styles')
</head>
<body>
    @php
        $currentUser = auth()->user();
        $pendingIncentives = 0;
    @endphp
    <div class="finance-shell">
        <aside class="finance-sidebar">
            <div class="finance-brand">
                <div class="finance-brand-mark">F</div>
                <div>
                    <h1>{{ brand_name() }}</h1>
                    <p>Finance Desk</p>
                </div>
            </div>

            <div class="finance-sidebar-section">
                <div class="finance-sidebar-label">Workspace</div>
                <a href="{{ route('finance-manager.dashboard') }}" class="finance-sidebar-link {{ request()->routeIs('finance-manager.dashboard') ? 'is-active' : '' }}">
                    <div class="finance-sidebar-link-main">
                        <i class="fas fa-grid-2"></i>
                        <span>Dashboard</span>
                    </div>
                </a>
                <a href="{{ route('finance-manager.booked-customers') }}" class="finance-sidebar-link {{ request()->routeIs('finance-manager.booked-customers') ? 'is-active' : '' }}">
                    <div class="finance-sidebar-link-main">
                        <i class="fas fa-user-check"></i>
                        <span>Booked Customers</span>
                    </div>
                </a>
                <a href="{{ route('post-sales.index') }}" class="finance-sidebar-link {{ request()->routeIs('post-sales.*') ? 'is-active' : '' }}">
                    <div class="finance-sidebar-link-main">
                        <i class="fas fa-table-list"></i>
                        <span>Post Sales</span>
                    </div>
                </a>
                <a href="{{ route('finance-manager.direct-closers.create') }}" class="finance-sidebar-link {{ request()->routeIs('finance-manager.direct-closers.*') ? 'is-active' : '' }}">
                    <div class="finance-sidebar-link-main">
                        <i class="fas fa-file-circle-plus"></i>
                        <span>Add Direct Closer</span>
                    </div>
                </a>
                <a href="{{ route('data-intelligence.index') }}" class="finance-sidebar-link {{ request()->routeIs('data-intelligence.*') ? 'is-active' : '' }}">
                    <div class="finance-sidebar-link-main">
                        <i class="fas fa-chart-line"></i>
                        <span>Data Intelligent</span>
                    </div>
                </a>
                <a href="{{ route('finance-manager.incentives') }}" class="finance-sidebar-link {{ request()->routeIs('finance-manager.incentives') ? 'is-active' : '' }}">
                    <div class="finance-sidebar-link-main">
                        <i class="fas fa-sack-dollar"></i>
                        <span>Incentives</span>
                    </div>
                    <span class="finance-sidebar-pill" id="pendingIncentivesBadge">{{ $pendingIncentives }}</span>
                </a>
                <a href="{{ route('finance-manager.expenses.entries.index') }}" class="finance-sidebar-link {{ request()->routeIs('finance-manager.expenses.*') ? 'is-active' : '' }}">
                    <div class="finance-sidebar-link-main">
                        <i class="fas fa-receipt"></i>
                        <span>Expenses</span>
                    </div>
                </a>
                <a href="{{ route('finance-manager.purchase-orders.index') }}" class="finance-sidebar-link {{ request()->routeIs('finance-manager.purchase-orders.*') ? 'is-active' : '' }}">
                    <div class="finance-sidebar-link-main">
                        <i class="fas fa-file-signature"></i>
                        <span>Purchase Orders</span>
                    </div>
                </a>
                <a href="{{ route('finance-manager.payroll') }}" class="finance-sidebar-link {{ request()->routeIs('finance-manager.payroll*') ? 'is-active' : '' }}">
                    <div class="finance-sidebar-link-main">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <span>Payroll</span>
                    </div>
                </a>
                <a href="{{ route('finance-manager.payslips.index') }}" class="finance-sidebar-link {{ request()->routeIs('finance-manager.payslips.*') ? 'is-active' : '' }}">
                    <div class="finance-sidebar-link-main">
                        <i class="fas fa-wallet"></i>
                        <span>Payslips</span>
                    </div>
                </a>
                <a href="{{ route('finance-manager.settings') }}" class="finance-sidebar-link {{ request()->routeIs('finance-manager.settings*') ? 'is-active' : '' }}">
                    <div class="finance-sidebar-link-main">
                        <i class="fas fa-sliders"></i>
                        <span>Settings</span>
                    </div>
                </a>
            </div>

            <div class="finance-sidebar-footer">
                <div class="finance-sidebar-user">
                    <span class="finance-sidebar-user-mark">{{ strtoupper(substr($currentUser?->name ?? 'F', 0, 1)) }}</span>
                    <div style="min-width:0;">
                        <div class="finance-sidebar-user-name">{{ $currentUser?->name ?? 'Fin Manager' }}</div>
                        <div class="finance-sidebar-user-role">Finance Manager</div>
                    </div>
                </div>
                <form method="POST" action="{{ route('logout') }}" style="margin: 0;">
                    @csrf
                    <button type="submit" class="finance-sidebar-logout"><i class="fas fa-right-from-bracket"></i> Logout</button>
                </form>
            </div>
        </aside>

        <main class="finance-main">
            <section class="finance-page">
                @if(session('success'))
                    <div class="finance-flash finance-flash-success">{{ session('success') }}</div>
                @endif
                @if($errors->any())
                    <div class="finance-flash finance-flash-error">{{ $errors->first() }}</div>
                @endif
                @yield('content')
            </section>
        </main>
    </div>

    <script>
        function updateFinanceClock() {
            const now = new Date();
            const timeNode = document.getElementById('financeClockTime');
            const dateNode = document.getElementById('financeClockDate');
            if (!timeNode || !dateNode) return;

            timeNode.textContent = now.toLocaleTimeString('en-IN', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false,
            });
            dateNode.textContent = now.toLocaleDateString('en-IN', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
            }).replace(/ /g, ' ');
        }

        updateFinanceClock();
        setInterval(updateFinanceClock, 1000);
    </script>
    @stack('scripts')
</body>
</html>

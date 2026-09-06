@extends('sales-executive.layout')

@section('title', 'Dashboard - Sales Executive')
@section('page-title', 'Dashboard')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/Sales Executive-dashboard.css') }}">
<style>
    /* Incentive amount modal - centered on mobile and desktop */
    .incentive-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        box-sizing: border-box;
    }
    .incentive-modal-dialog {
        background: white;
        border-radius: 12px;
        padding: 24px;
        max-width: 360px;
        width: 100%;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
    }
    .incentive-modal-title {
        margin: 0 0 12px 0;
        font-size: 18px;
        font-weight: 600;
        color: #1f2937;
    }
    .incentive-modal-input {
        width: 100%;
        padding: 12px 14px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 16px;
        margin-bottom: 6px;
        box-sizing: border-box;
    }
    .incentive-modal-input:focus {
        outline: none;
        border-color: #063A1C;
        box-shadow: 0 0 0 2px rgba(6, 58, 28, 0.2);
    }
    .incentive-modal-hint {
        display: block;
        color: #6b7280;
        font-size: 12px;
        margin-bottom: 16px;
    }
    .incentive-modal-actions {
        display: flex;
        gap: 12px;
        justify-content: flex-end;
    }
    .incentive-modal-btn {
        padding: 10px 20px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        border: none;
    }
    .incentive-modal-btn-cancel {
        background: #f3f4f6;
        color: #374151;
    }
    .incentive-modal-btn-cancel:hover {
        background: #e5e7eb;
    }
    .incentive-modal-btn-ok {
        background: linear-gradient(135deg, #063A1C 0%, #205A44 100%);
        color: white;
    }
    .incentive-modal-btn-ok:hover {
        opacity: 0.95;
    }

    /* Message modal (success/error) - same centered overlay */
    .message-modal-overlay {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .message-modal-dialog {
        background: white;
        border-radius: 12px;
        padding: 24px;
        max-width: 360px;
        width: 100%;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
    }
    .message-modal-title {
        margin: 0 0 12px 0;
        font-size: 18px;
        font-weight: 600;
        color: #1f2937;
    }
    .message-modal-dialog.success .message-modal-title { color: #059669; }
    .message-modal-dialog.error .message-modal-title { color: #dc2626; }
    .message-modal-text {
        margin: 0 0 20px 0;
        font-size: 15px;
        color: #4b5563;
        line-height: 1.5;
    }
    .message-modal-actions {
        display: flex;
        justify-content: flex-end;
    }

    .dashboard-container {
        width: 100%;
        max-width: 100%;
        overflow: visible;
        padding-bottom: 24px;
    }

    /* Dashboard Cards Grid */
    .dashboard-cards-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
        margin-bottom: 20px;
    }

    /* Ensure equal width for cards in phone view */
    .dashboard-cards-grid .dashboard-card {
        width: 100%;
        min-width: 0;
    }

    .dashboard-card {
        background: linear-gradient(135deg, #063A1C 0%, #205A44 100%);
        border-radius: 12px;
        padding: 16px;
        color: white;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        transition: all 0.3s;
        position: relative;
        overflow: hidden;
    }

    .dashboard-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(6, 58, 28, 0.3);
    }

    .dashboard-card-title {
        font-size: 13px;
        font-weight: 500;
        color: rgba(255, 255, 255, 0.9);
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .dashboard-card-value {
        font-size: 28px;
        font-weight: 700;
        color: white;
        line-height: 1.2;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
    }

    .dashboard-card-icon {
        display: none;
    }

    /* Target Card Specific Styles */
    .target-card {
        position: relative;
    }

    .period-badge {
        display: inline-block;
        font-size: 10px;
        font-weight: 600;
        padding: 2px 6px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 4px;
        margin-left: 6px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .progress-bar-container {
        width: 100%;
        height: 6px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 3px;
        margin-top: 8px;
        margin-bottom: 4px;
        overflow: hidden;
    }

    .progress-bar {
        height: 100%;
        background: linear-gradient(90deg, #4ade80 0%, #22c55e 100%);
        border-radius: 3px;
        transition: width 0.3s ease;
    }

    /* Color coding based on percentage */
    .progress-bar[data-percentage] {
        background: linear-gradient(90deg, #4ade80 0%, #22c55e 100%);
    }

    .progress-bar[data-percentage^="0"],
    .progress-bar[data-percentage^="1"],
    .progress-bar[data-percentage^="2"],
    .progress-bar[data-percentage^="3"],
    .progress-bar[data-percentage^="4"] {
        background: linear-gradient(90deg, #ef4444 0%, #dc2626 100%);
    }

    .progress-bar[data-percentage^="5"],
    .progress-bar[data-percentage^="6"],
    .progress-bar[data-percentage^="7"],
    .progress-bar[data-percentage^="8"],
    .progress-bar[data-percentage^="9"] {
        background: linear-gradient(90deg, #fbbf24 0%, #f59e0b 100%);
    }

    .progress-percentage {
        font-size: 12px;
        font-weight: 600;
        color: rgba(255, 255, 255, 0.9);
        margin-top: 4px;
    }

    /* Desktop View */
    @media (min-width: 768px) {
        .dashboard-cards-grid {
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
        }

        .dashboard-card {
            padding: 20px;
        }

        .dashboard-card-value {
            font-size: 32px;
        }

        .time-range-selector {
            justify-content: flex-end;
        }

        .time-range-selector select {
            max-width: 200px;
        }
    }

    /* Consolidated Target Card (Phone View) */
    .consolidated-target-card {
        background: linear-gradient(135deg, #063A1C 0%, #205A44 100%);
        border-radius: 12px;
        padding: 16px;
        color: white;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        margin-bottom: 20px;
        width: 100%;
        box-sizing: border-box;
    }

    .target-filter-buttons {
        display: flex;
        gap: 6px;
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.2);
    }

    .filter-btn {
        flex: 1;
        padding: 6px 8px;
        border: 1.5px solid rgba(255, 255, 255, 0.3);
        border-radius: 6px;
        background: rgba(255, 255, 255, 0.1);
        color: white;
        font-size: 10px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .filter-btn:hover {
        background: rgba(255, 255, 255, 0.2);
        border-color: rgba(255, 255, 255, 0.5);
    }

    .filter-btn.active {
        background: white;
        color: #205A44;
        border-color: white;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    }

    .target-section {
        margin-bottom: 20px;
    }

    .target-section:last-child {
        margin-bottom: 0;
    }

    .target-section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }

    .target-section-title {
        font-size: 13px;
        font-weight: 500;
        color: rgba(255, 255, 255, 0.9);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .target-section-value {
        font-size: 24px;
        font-weight: 700;
        color: white;
        line-height: 1.2;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
        margin-bottom: 8px;
    }

    .desktop-only {
        display: none;
    }

    .mobile-only {
        display: block;
    }

    /* Mobile View - Ensure 2 columns with equal 50%-50% width */
    @media (max-width: 767px) {
        /* Hide month selector in phone view */
        .month-selector-container {
            display: none !important;
        }

        .dashboard-cards-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            width: 100%;
        }

        .dashboard-cards-grid .dashboard-card {
            width: 100%;
            min-width: 0;
            max-width: 100%;
            box-sizing: border-box;
        }

        /* Consolidated target card should be 100% width and span all grid columns */
        .consolidated-target-card {
            grid-column: 1 / -1;
            width: 100%;
            max-width: 100%;
            margin-left: 0;
            margin-right: 0;
        }

        .target-card .dashboard-card-value {
            font-size: 20px;
        }

        .period-badge {
            font-size: 9px;
            padding: 1px 4px;
        }

        .dashboard-card {
            padding: 16px;
        }

        .dashboard-card-value {
            font-size: 24px;
        }

        .time-range-selector {
            flex-direction: column;
            align-items: stretch;
        }

        .time-range-selector select {
            width: 100%;
        }

        .custom-date-inputs {
            flex-direction: column;
        }
    }

    /* Desktop View */
    @media (min-width: 768px) {
        .desktop-only {
            display: block;
        }

        .mobile-only {
            display: none;
        }
    }
</style>
@endpush

@section('content')
<div class="dashboard-container">
    @if(auth()->user()?->hasAttendanceRolloutEnabled())
        @include('attendance._widget')
    @endif
    <!-- Month Selector for Targets -->
    <div class="month-selector-container" style="background: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div>
            <label for="target_month" style="font-weight: 500; margin-right: 10px;">View Targets for Month:</label>
            <input type="month" id="target_month" name="target_month" value="{{ $targetMonth ?? now()->format('Y-m') }}" 
                   onchange="updateTargetMonth(this.value)" 
                   style="padding: 8px 12px; border: 2px solid #e0e0e0; border-radius: 5px; font-size: 14px;">
            @if(($targetMonth ?? now()->format('Y-m')) != now()->format('Y-m'))
                <span style="margin-left: 10px; color: #666; font-size: 12px;">(Current: {{ now()->format('M Y') }})</span>
            @else
                <span style="margin-left: 10px; color: #16a34a; font-size: 12px; font-weight: 600;">Current Month</span>
            @endif
        </div>
    </div>
    
    @php
        $targetCards = isset($cardStats['targets']) ? [
            ['key' => 'calling', 'metric' => $cardStats['targets']['calling'] ?? [], 'fallback_period' => 'daily'],
            ['key' => 'prospect', 'metric' => $cardStats['targets']['prospect'] ?? [], 'fallback_period' => 'daily'],
            ['key' => 'visit', 'metric' => $cardStats['targets']['visit'] ?? [], 'fallback_period' => 'weekly'],
        ] : [];
    @endphp

    <!-- Dashboard Cards Grid -->
    <div class="dashboard-cards-grid">
        <!-- Today Lead Card -->
        <div class="dashboard-card">
            <div class="dashboard-card-title">Today Lead</div>
            <div class="dashboard-card-value">{{ $cardStats['today_leads'] ?? 0 }}</div>
        </div>

        <!-- Remaining Task Card -->
        <div class="dashboard-card">
            <div class="dashboard-card-title">Remaining Task</div>
            <div class="dashboard-card-value">{{ $cardStats['remaining_tasks'] ?? 0 }}</div>
        </div>

        <!-- Over Due Task Card -->
        <div class="dashboard-card">
            <div class="dashboard-card-title">Over Due Task</div>
            <div class="dashboard-card-value">{{ $cardStats['overdue_tasks'] ?? 0 }}</div>
        </div>

        <!-- Prospect Card -->
        <div class="dashboard-card">
            <div class="dashboard-card-title">Prospect</div>
            <div class="dashboard-card-value">{{ $cardStats['prospects'] ?? 0 }}</div>
        </div>

        <!-- No response yet Card -->
        <div class="dashboard-card">
            <div class="dashboard-card-title">No response yet</div>
            <div class="dashboard-card-value">{{ $cardStats['leads_pending_response'] ?? 0 }}</div>
        </div>

        <div class="dashboard-card">
            <div class="dashboard-card-title">This Month Lost Leads</div>
            <div class="dashboard-card-value">{{ $cardStats['this_month_lost_leads'] ?? 0 }}</div>
        </div>

        @if($targetCards)
        @foreach($targetCards as $targetCard)
        @php
            $metric = $targetCard['metric'];
            $isActivityMode = ($metric['mode'] ?? 'target_based') === 'activity_based';
            $period = strtoupper($metric['period'] ?? $targetCard['fallback_period']);
            $achieved = (int) ($metric['achieved'] ?? $metric['actual'] ?? 0);
            $targetValue = $metric['target'] ?? null;
            $percentage = (float) ($metric['percentage'] ?? 0);
            $showProgress = !$isActivityMode && !empty($metric['has_target']);
            $desktopValue = $isActivityMode ? (string) $achieved : ($showProgress ? ($achieved . ' / ' . $targetValue) : (string) $achieved);
        @endphp
        <div class="dashboard-card target-card desktop-only">
            <div class="dashboard-card-title">
                {{ $metric['label'] ?? ucfirst($targetCard['key']) }}
                <span class="period-badge">{{ $period }}</span>
            </div>
            <div class="dashboard-card-value">{{ $desktopValue }}</div>
            @if($showProgress)
            <div class="progress-bar-container">
                <div class="progress-bar"
                     style="width: {{ min(100, $percentage) }}%"
                     data-percentage="{{ $percentage }}"></div>
            </div>
            <div class="progress-percentage">{{ round($percentage, 1) }}%</div>
            @else
            <div class="progress-percentage">{{ $metric['subtitle'] ?? ($isActivityMode ? 'Completed' : 'Target not set') }}</div>
            @endif
        </div>
        @endforeach

            <!-- Phone: Consolidated Target Card -->
        <div class="consolidated-target-card mobile-only">
            <!-- Filter Buttons -->
            <div class="target-filter-buttons">
                <button type="button" 
                        class="filter-btn {{ ($targetFilter ?? 'today') === 'today' ? 'active' : '' }}"
                        onclick="updateTargetFilter('today')">
                    Today
                </button>
                <button type="button" 
                        class="filter-btn {{ ($targetFilter ?? 'today') === 'this_week' ? 'active' : '' }}"
                        onclick="updateTargetFilter('this_week')">
                    This Week
                </button>
                <button type="button" 
                        class="filter-btn {{ ($targetFilter ?? 'today') === 'this_month' ? 'active' : '' }}"
                        onclick="updateTargetFilter('this_month')">
                    This Month
                </button>
            </div>

            @foreach($targetCards as $targetCard)
            @php
                $metric = $targetCard['metric'];
                $isActivityMode = ($metric['mode'] ?? 'target_based') === 'activity_based';
                $period = strtoupper($metric['period'] ?? $targetCard['fallback_period']);
                $achieved = (int) ($metric['achieved'] ?? $metric['actual'] ?? 0);
                $targetValue = $metric['target'] ?? null;
                $percentage = (float) ($metric['percentage'] ?? 0);
                $showProgress = !$isActivityMode && !empty($metric['has_target']);
                $mobileValue = $isActivityMode ? (string) $achieved : ($showProgress ? ($achieved . ' / ' . $targetValue) : (string) $achieved);
            @endphp
            <div class="target-section">
                <div class="target-section-header">
                    <span class="target-section-title">{{ strtoupper($metric['label'] ?? ucfirst($targetCard['key'])) }}</span>
                    <span class="period-badge">{{ $period }}</span>
                </div>
                <div class="target-section-value">{{ $mobileValue }}</div>
                @if($showProgress)
                <div class="progress-bar-container">
                    <div class="progress-bar"
                         style="width: {{ min(100, $percentage) }}%"
                         data-percentage="{{ $percentage }}"></div>
                </div>
                <div class="progress-percentage">{{ round($percentage, 1) }}%</div>
                @else
                <div class="progress-percentage">{{ $metric['subtitle'] ?? ($isActivityMode ? 'Completed' : 'Target not set') }}</div>
                @endif
            </div>
            @endforeach
        </div>
        @endif
    </div>

    <!-- Leads allocated – no response yet -->
    <div class="bg-white rounded-lg shadow p-6 mb-6" style="margin-top: 24px;">
        <h2 class="text-xl font-bold text-gray-900 mb-2">
            <i class="fas fa-inbox mr-2 text-[#063A1C]"></i>Leads allocated – no response yet
        </h2>
        <p class="text-sm text-gray-600 mb-4">Leads assigned to you on which you haven't responded yet.</p>
        <div class="flex flex-wrap items-center gap-2 mb-4">
            <label for="se-leads-pending-date-range" class="text-sm font-medium text-gray-700">Date range:</label>
            <select id="se-leads-pending-date-range" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#063A1C] focus:border-[#063A1C]">
                <option value="this_week">This week</option>
                <option value="this_month" selected>This month</option>
                <option value="all_time">All time</option>
            </select>
        </div>
        <div class="overflow-x-auto rounded-lg border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Lead name</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Phone</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Assigned at</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Action</th>
                    </tr>
                </thead>
                <tbody id="se-leads-pending-response-tbody" class="bg-white divide-y divide-gray-200">
                    <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Incentives Section (below cards - ensure visible on desktop and phone) -->
    <div class="bg-white rounded-lg shadow p-6 mb-6" id="Sales ExecutiveIncentivesSection" style="margin-top: 24px;">
    <h2 class="text-xl font-bold text-gray-900 mb-4">
        <i class="fas fa-money-bill-wave mr-2 text-green-600"></i>Earn Incentive
    </h2>
    
    <!-- Incentive Potential -->
    <div class="mb-6 p-4 bg-gradient-to-r from-green-50 to-emerald-50 rounded-lg border-2 border-green-200">
        <div class="flex items-center justify-between mb-2">
            <span class="text-sm font-semibold text-green-900">Incentive Potential</span>
            <span class="text-2xl font-bold text-green-700" id="Sales ExecutiveIncentivePotential">₹0</span>
        </div>
        <p class="text-xs text-green-700" id="Sales ExecutiveIncentivePotentialDetails">Target: 0 Visits × ₹0 = ₹0</p>
    </div>

    <!-- Eligible Site Visits for Incentive -->
    <div class="mb-4">
        <h3 class="text-lg font-semibold text-gray-800 mb-3">Eligible Site Visits</h3>
        <div id="eligibleSiteVisitsList" class="space-y-2">
            <p class="text-gray-500 text-sm">Loading...</p>
        </div>
    </div>

    <!-- Pending Incentives -->
    <div class="mb-4">
        <h3 class="text-lg font-semibold text-gray-800 mb-3">Pending Incentives</h3>
        <div id="Sales ExecutivePendingIncentivesList" class="space-y-2">
            <p class="text-gray-500 text-sm">No pending incentives</p>
        </div>
    </div>

    <!-- Verified Incentives -->
    <div>
        <h3 class="text-lg font-semibold text-gray-800 mb-3">Earned Incentives</h3>
        <div class="mb-3 p-3 bg-green-50 rounded-lg">
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-700">Total Earned</span>
                <span class="text-xl font-bold text-green-700" id="Sales ExecutiveTotalEarnedIncentives">₹0</span>
            </div>
        </div>
        <div id="Sales ExecutiveVerifiedIncentivesList" class="space-y-2">
            <p class="text-gray-500 text-sm">No verified incentives yet</p>
        </div>
    </div>
    </div>
</div>
<!-- /dashboard-container -->

<!-- Incentive Amount Modal (centered, mobile & desktop) -->
<div id="incentiveAmountModal" class="incentive-modal-overlay" style="display: none;" aria-hidden="true">
    <div class="incentive-modal-dialog" role="dialog" aria-labelledby="incentiveModalTitle" aria-modal="true">
        <h3 id="incentiveModalTitle" class="incentive-modal-title">Enter incentive amount</h3>
        <input type="number" id="incentiveAmountInput" class="incentive-modal-input" step="0.01" min="0" placeholder="e.g. 5000" autocomplete="off">
        <small class="incentive-modal-hint">Enter the amount in ₹ (e.g. 5000)</small>
        <div class="incentive-modal-actions">
            <button type="button" id="incentiveModalCancel" class="incentive-modal-btn incentive-modal-btn-cancel">Cancel</button>
            <button type="button" id="incentiveModalOk" class="incentive-modal-btn incentive-modal-btn-ok">OK</button>
        </div>
    </div>
</div>

<!-- Message modal (success/error - centered, mobile & desktop) -->
<div id="messageModal" class="incentive-modal-overlay message-modal-overlay" style="display: none;" aria-hidden="true">
    <div class="message-modal-dialog" role="alertdialog" aria-labelledby="messageModalTitle">
        <h3 id="messageModalTitle" class="message-modal-title"></h3>
        <p id="messageModalText" class="message-modal-text"></p>
        <div class="message-modal-actions">
            <button type="button" id="messageModalOk" class="incentive-modal-btn incentive-modal-btn-ok">OK</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Date range selector is now in the header
    // Functions are defined in the layout file
    
    function updateTargetMonth(month) {
        const url = new URL(window.location.href);
        url.searchParams.set('target_month', month);
        // Preserve target_filter if it exists
        const currentFilter = url.searchParams.get('target_filter') || 'today';
        url.searchParams.set('target_filter', currentFilter);
        window.location.href = url.toString();
    }

    function updateTargetFilter(filter) {
        const url = new URL(window.location.href);
        url.searchParams.set('target_filter', filter);
        // Preserve target_month if it exists
        const currentMonth = url.searchParams.get('target_month') || '{{ now()->format("Y-m") }}';
        url.searchParams.set('target_month', currentMonth);
        window.location.href = url.toString();
    }

    // Load Sales Executive Incentives and Eligible Site Visits
    async function loadSalesExecutiveIncentives() {
        try {
            const API_BASE_URL = '{{ url("/api/telecaller") }}';
            const token = localStorage.getItem('sales-executive_token') || '{{ session("sales_executive_api_token") ?? session("telecaller_api_token") ?? session("api_token") ?? "" }}';

            // Load dashboard data for incentives
            const dashboardResponse = await fetch('{{ url("/api/dashboard") }}', {
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Accept': 'application/json',
                }
            });

            if (dashboardResponse.ok) {
                const dashboardData = await dashboardResponse.json();
                
                // Load incentives
                if (dashboardData.incentives) {
                    loadSalesExecutiveIncentivesData(dashboardData.incentives);
                }

                // Load incentive potential
                if (dashboardData.incentive_potential) {
                    loadSalesExecutiveIncentivePotential(dashboardData.incentive_potential);
                }
            }

            // Load eligible site visits
            const eligibleResponse = await fetch(`${API_BASE_URL}/site-visits/eligible-for-incentive`, {
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Accept': 'application/json',
                }
            });

            if (eligibleResponse.ok) {
                const eligibleData = await eligibleResponse.json();
                if (eligibleData.success && eligibleData.data) {
                    loadEligibleSiteVisits(eligibleData.data);
                }
            }
        } catch (error) {
            console.error('Error loading Sales Executive incentives:', error);
        }
    }

    function loadSalesExecutiveIncentivesData(incentives) {
        // Pending incentives
        const pendingList = document.getElementById('Sales ExecutivePendingIncentivesList');
        if (incentives.pending && incentives.pending.length > 0) {
            pendingList.innerHTML = incentives.pending.map(inc => `
                <div class="p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="text-sm font-medium text-gray-900">${inc.site_visit?.customer_name || 'N/A'}</p>
                            <p class="text-xs text-gray-600">Status: ${inc.status === 'pending_sales_head' ? 'Awaiting Sales Head' : 'Awaiting CRM'}</p>
                        </div>
                        <span class="text-lg font-bold text-yellow-700">₹${parseFloat(inc.amount).toFixed(2)}</span>
                    </div>
                </div>
            `).join('');
        } else {
            pendingList.innerHTML = '<p class="text-gray-500 text-sm">No pending incentives</p>';
        }

        // Total earned
        document.getElementById('Sales ExecutiveTotalEarnedIncentives').textContent = `₹${parseFloat(incentives.total_earned || 0).toFixed(2)}`;

        // Verified incentives
        const verifiedList = document.getElementById('Sales ExecutiveVerifiedIncentivesList');
        if (incentives.verified && incentives.verified.length > 0) {
            verifiedList.innerHTML = incentives.verified.slice(0, 5).map(inc => `
                <div class="p-3 bg-green-50 border border-green-200 rounded-lg">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="text-sm font-medium text-gray-900">${inc.site_visit?.customer_name || 'N/A'}</p>
                            <p class="text-xs text-gray-600">Verified on ${inc.crm_verified_at ? new Date(inc.crm_verified_at).toLocaleDateString('en-IN') : 'N/A'}</p>
                        </div>
                        <span class="text-lg font-bold text-green-700">₹${parseFloat(inc.amount).toFixed(2)}</span>
                    </div>
                </div>
            `).join('');
        } else {
            verifiedList.innerHTML = '<p class="text-gray-500 text-sm">No verified incentives yet</p>';
        }
    }

    function loadSalesExecutiveIncentivePotential(potential) {
        document.getElementById('Sales ExecutiveIncentivePotential').textContent = `₹${parseFloat(potential.potential || 0).toFixed(2)}`;
        document.getElementById('Sales ExecutiveIncentivePotentialDetails').textContent = 
            `Target: ${potential.target_visits || 0} Visits × ₹${parseFloat(potential.incentive_per_visit || 0).toFixed(2)} = ₹${parseFloat(potential.potential || 0).toFixed(2)}`;
    }

    function loadEligibleSiteVisits(siteVisits) {
        const list = document.getElementById('eligibleSiteVisitsList');
        if (siteVisits && siteVisits.length > 0) {
            list.innerHTML = siteVisits.map(sv => `
                <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg">
                    <div class="flex justify-between items-center mb-2">
                        <div>
                            <p class="text-sm font-medium text-gray-900">${sv.customer_name || 'N/A'}</p>
                            <p class="text-xs text-gray-600">Completed: ${sv.completed_at ? new Date(sv.completed_at).toLocaleDateString('en-IN') : 'N/A'}</p>
                        </div>
                        <button onclick="requestSiteVisitIncentive(${sv.id})" 
                                class="px-4 py-2 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-lg text-sm font-medium hover:from-green-700 hover:to-green-800 transition-all">
                            <i class="fas fa-hand-holding-usd mr-1"></i>Request Incentive
                        </button>
                    </div>
                </div>
            `).join('');
        } else {
            list.innerHTML = '<p class="text-gray-500 text-sm">No eligible site visits for incentive</p>';
        }
    }

    let pendingIncentiveSiteVisitId = null;

    function showMessageModal(message, type) {
        type = type || 'info';
        const overlay = document.getElementById('messageModal');
        const titleEl = document.getElementById('messageModalTitle');
        const textEl = document.getElementById('messageModalText');
        const dialog = overlay ? overlay.querySelector('.message-modal-dialog') : null;
        if (!overlay || !titleEl || !textEl || !dialog) return;
        dialog.classList.remove('success', 'error');
        if (type === 'success') {
            titleEl.textContent = 'Success';
            dialog.classList.add('success');
        } else {
            titleEl.textContent = type === 'error' ? 'Error' : 'Notice';
            if (type === 'error') dialog.classList.add('error');
        }
        textEl.textContent = message;
        overlay.style.display = 'flex';
    }

    function closeMessageModal() {
        const overlay = document.getElementById('messageModal');
        if (overlay) overlay.style.display = 'none';
    }

    function requestSiteVisitIncentive(siteVisitId) {
        pendingIncentiveSiteVisitId = siteVisitId;
        const modal = document.getElementById('incentiveAmountModal');
        const input = document.getElementById('incentiveAmountInput');
        if (modal && input) {
            input.value = '';
            modal.style.display = 'flex';
            input.focus();
        }
    }

    function closeIncentiveModal() {
        const modal = document.getElementById('incentiveAmountModal');
        if (modal) modal.style.display = 'none';
        pendingIncentiveSiteVisitId = null;
    }

    async function submitIncentiveAmount() {
        const amountVal = document.getElementById('incentiveAmountInput').value;
        if (!amountVal || parseFloat(amountVal) <= 0) {
            showMessageModal('Please enter a valid incentive amount.', 'error');
            return;
        }
        const siteVisitId = pendingIncentiveSiteVisitId;
        if (!siteVisitId) return;
        closeIncentiveModal();

        try {
            const API_BASE_URL = '{{ url("/api/telecaller") }}';
            const token = localStorage.getItem('sales-executive_token') || '{{ session("sales_executive_api_token") ?? session("telecaller_api_token") ?? session("api_token") ?? "" }}';

            const response = await fetch(`${API_BASE_URL}/site-visits/${siteVisitId}/request-incentive`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ amount: parseFloat(amountVal) })
            });

            const result = await response.json();

            if (result && result.success) {
                showMessageModal('Incentive requested successfully! Awaiting verification.', 'success');
                loadSalesExecutiveIncentives();
            } else {
                showMessageModal(result.message || 'Failed to request incentive', 'error');
            }
        } catch (error) {
            console.error('Error requesting incentive:', error);
            showMessageModal('Network error. Please try again.', 'error');
        }
    }

    async function loadSeLeadsPendingResponse() {
        const tbody = document.getElementById('se-leads-pending-response-tbody');
        if (!tbody) return;
        const API_BASE_URL = '{{ url("/api/telecaller") }}';
        const token = localStorage.getItem('sales-executive_token') || document.querySelector('meta[name="api-token"]')?.getAttribute('content') || '';
        const dateRange = document.getElementById('se-leads-pending-date-range')?.value || 'this_month';
        try {
            tbody.innerHTML = '<tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">Loading...</td></tr>';
            const url = `${API_BASE_URL}/leads-pending-response?date_range=${encodeURIComponent(dateRange)}`;
            const res = await fetch(url, {
                headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.error || data.message || 'Failed to load');
            const leads = data.leads || [];
            const leadShowBase = '{{ url("/leads") }}';
            if (leads.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">No leads pending response.</td></tr>';
                return;
            }
            tbody.innerHTML = leads.map(function(l) {
                const name = (l.name || '—').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                const phone = (l.phone || '—');
                const masked = phone.length > 4 ? phone.slice(0, 2) + '****' + phone.slice(-4) : phone;
                const assignedAt = l.assigned_at ? new Date(l.assigned_at).toLocaleString('en-IN', { dateStyle: 'medium', timeStyle: 'short' }) : '—';
                return '<tr><td class="px-4 py-3 text-sm text-gray-900">' + name + '</td><td class="px-4 py-3 text-sm text-gray-600">' + masked + '</td><td class="px-4 py-3 text-sm text-gray-600">' + assignedAt + '</td><td class="px-4 py-3"><a href="' + leadShowBase + '/' + (l.lead_id || '') + '" class="text-[#063A1C] font-medium hover:underline">View</a></td></tr>';
            }).join('');
        } catch (err) {
            console.error('Error loading leads pending response:', err);
            tbody.innerHTML = '<tr><td colspan="4" class="px-4 py-6 text-center text-red-600">Error loading data.</td></tr>';
        }
    }

    // Load on page load + bind modal buttons
    document.addEventListener('DOMContentLoaded', function() {
        loadSalesExecutiveIncentives();
        loadSeLeadsPendingResponse();

        const pendingDateRange = document.getElementById('se-leads-pending-date-range');
        if (pendingDateRange) pendingDateRange.addEventListener('change', loadSeLeadsPendingResponse);

        const modal = document.getElementById('incentiveAmountModal');
        const input = document.getElementById('incentiveAmountInput');
        const okBtn = document.getElementById('incentiveModalOk');
        const cancelBtn = document.getElementById('incentiveModalCancel');

        if (cancelBtn) cancelBtn.addEventListener('click', closeIncentiveModal);
        if (okBtn) okBtn.addEventListener('click', submitIncentiveAmount);
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) closeIncentiveModal();
            });
        }
        if (input) {
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') submitIncentiveAmount();
                if (e.key === 'Escape') closeIncentiveModal();
            });
        }

        const messageModal = document.getElementById('messageModal');
        const messageOk = document.getElementById('messageModalOk');
        if (messageOk) messageOk.addEventListener('click', closeMessageModal);
        if (messageModal) {
            messageModal.addEventListener('click', function(e) {
                if (e.target === messageModal) closeMessageModal();
            });
        }
    });
</script>
@endpush

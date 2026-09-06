@extends('layouts.app')

@section('title', 'Call Statistics - ' . brand_name())
@section('page-title', 'Call Statistics')

@section('header-actions')
    <div style="display: flex; gap: 10px;">
        <a href="{{ route('calls.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors duration-200 text-sm font-medium">
            <i class="fas fa-arrow-left mr-2"></i> Back to Calls
        </a>
        <a href="{{ route('calls.export.csv') }}?{{ http_build_query(request()->all()) }}" class="px-4 py-2 btn-brand-gradient text-white rounded-lg transition-colors duration-200 text-sm font-medium">
            <i class="fas fa-download mr-2"></i> Export CSV
        </a>
    </div>
@endsection

@section('content')
    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6 mb-6">
        <form method="GET" action="{{ route('calls.statistics') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label for="date_range" class="block text-sm font-medium text-brand-primary mb-2">Date Range</label>
                <select name="date_range" id="date_range"
                        class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg focus:ring-2 focus:ring-[#205A44] focus:border-[#205A44]">
                    <option value="today" {{ request('date_range', 'today') == 'today' ? 'selected' : '' }}>Today</option>
                    <option value="this_week" {{ request('date_range') == 'this_week' ? 'selected' : '' }}>This Week</option>
                    <option value="this_month" {{ request('date_range') == 'this_month' ? 'selected' : '' }}>This Month</option>
                </select>
            </div>
            @if(isset($users) && count($users) > 0)
            <div>
                <label for="user_id" class="block text-sm font-medium text-brand-primary mb-2">User</label>
                <select name="user_id" id="user_id"
                        class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg focus:ring-2 focus:ring-[#205A44] focus:border-[#205A44]">
                    <option value="">All Users</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>
                            {{ $user->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            @endif
            <div>
                <label for="call_type" class="block text-sm font-medium text-brand-primary mb-2">Call Type</label>
                <select name="call_type" id="call_type"
                        class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg focus:ring-2 focus:ring-[#205A44] focus:border-[#205A44]">
                    <option value="">All Types</option>
                    <option value="incoming" {{ request('call_type') == 'incoming' ? 'selected' : '' }}>Incoming</option>
                    <option value="outgoing" {{ request('call_type') == 'outgoing' ? 'selected' : '' }}>Outgoing</option>
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full px-6 py-2 btn-brand-gradient text-white rounded-lg transition-colors duration-200 font-medium">
                    <i class="fas fa-filter mr-2"></i> Apply Filters
                </button>
            </div>
        </form>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white p-6 rounded-lg shadow border border-[#E5DED4]">
            <div class="text-sm text-[#B3B5B4] mb-1">Total Calls</div>
            <div class="text-3xl font-bold text-brand-primary" id="stats-total-calls">0</div>
        </div>
        <div class="bg-white p-6 rounded-lg shadow border border-[#E5DED4]">
            <div class="text-sm text-[#B3B5B4] mb-1">Total Duration</div>
            <div class="text-3xl font-bold text-brand-primary" id="stats-total-duration">0s</div>
        </div>
        <div class="bg-white p-6 rounded-lg shadow border border-[#E5DED4]">
            <div class="text-sm text-[#B3B5B4] mb-1">Average Duration</div>
            <div class="text-3xl font-bold text-brand-primary" id="stats-avg-duration">0s</div>
        </div>
        <div class="bg-white p-6 rounded-lg shadow border border-[#E5DED4]">
            <div class="text-sm text-[#B3B5B4] mb-1">Connection Rate</div>
            <div class="text-3xl font-bold text-brand-primary" id="stats-connection-rate">0%</div>
        </div>
    </div>

    <!-- Charts -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6">
            <h3 class="text-lg font-semibold text-brand-primary mb-4">Calls Per Day</h3>
            <div class="chart-container" style="position: relative; height: 300px;">
                <canvas id="callsPerDayChart"></canvas>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6">
            <h3 class="text-lg font-semibold text-brand-primary mb-4">Calls Per Hour</h3>
            <div class="chart-container" style="position: relative; height: 300px;">
                <canvas id="callsPerHourChart"></canvas>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6">
            <h3 class="text-lg font-semibold text-brand-primary mb-4">Calls by Type</h3>
            <div class="chart-container" style="position: relative; height: 300px;">
                <canvas id="callsByTypeChart"></canvas>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6">
            <h3 class="text-lg font-semibold text-brand-primary mb-4">Call Outcome Distribution</h3>
            <div class="chart-container" style="position: relative; height: 300px;">
                <canvas id="outcomeChart"></canvas>
            </div>
        </div>
    </div>

    @if(auth()->user()->isSalesManager() || auth()->user()->isAdmin() || auth()->user()->isCrm())
    <!-- Calls by User Chart -->
    <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6 mb-6">
        <h3 class="text-lg font-semibold text-brand-primary mb-4">Calls by User</h3>
        <div class="chart-container" style="position: relative; height: 400px;">
            <canvas id="callsByUserChart"></canvas>
        </div>
    </div>
    @endif
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    // Load statistics via API
    async function loadStatistics() {
        const dateRange = document.getElementById('date_range')?.value || 'today';
        const userId = document.getElementById('user_id')?.value || '';
        const callType = document.getElementById('call_type')?.value || '';
        
        const params = new URLSearchParams();
        params.append('date_range', dateRange);
        if (userId) params.append('user_id', userId);
        if (callType) params.append('call_type', callType);
        
        try {
            const response = await fetch(`{{ route('calls.statistics') }}?${params.toString()}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            
            if (response.ok) {
                const data = await response.json();
                updateStatistics(data);
            }
        } catch (error) {
            console.error('Error loading statistics:', error);
        }
    }
    
    function updateStatistics(data) {
        // Update summary cards
        document.getElementById('stats-total-calls').textContent = data.total_calls || 0;
        document.getElementById('stats-total-duration').textContent = data.formatted_duration || '0s';
        document.getElementById('stats-avg-duration').textContent = data.formatted_average_duration || '0s';
        document.getElementById('stats-connection-rate').textContent = (data.connection_rate || 0).toFixed(1) + '%';
        
        // Update charts (simplified - would need actual data from API)
        // Charts would be updated here with real data
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        loadStatistics();
        
        // Initialize placeholder charts
        const chartOptions = {
            responsive: true,
            maintainAspectRatio: false,
        };
        
        // Calls Per Day Chart
        const callsPerDayCtx = document.getElementById('callsPerDayChart');
        if (callsPerDayCtx) {
            new Chart(callsPerDayCtx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Calls',
                        data: [],
                        borderColor: '#205A44',
                        backgroundColor: 'rgba(32, 90, 68, 0.1)',
                    }]
                },
                options: chartOptions
            });
        }
        
        // Calls Per Hour Chart
        const callsPerHourCtx = document.getElementById('callsPerHourChart');
        if (callsPerHourCtx) {
            new Chart(callsPerHourCtx, {
                type: 'bar',
                data: {
                    labels: Array.from({length: 24}, (_, i) => i + ':00'),
                    datasets: [{
                        label: 'Calls',
                        data: Array(24).fill(0),
                        backgroundColor: 'rgba(32, 90, 68, 0.6)',
                    }]
                },
                options: chartOptions
            });
        }
        
        // Calls by Type Chart
        const callsByTypeCtx = document.getElementById('callsByTypeChart');
        if (callsByTypeCtx) {
            new Chart(callsByTypeCtx, {
                type: 'pie',
                data: {
                    labels: ['Incoming', 'Outgoing'],
                    datasets: [{
                        data: [0, 0],
                        backgroundColor: ['rgba(59, 130, 246, 0.6)', 'rgba(32, 90, 68, 0.6)'],
                    }]
                },
                options: chartOptions
            });
        }
        
        // Outcome Chart
        const outcomeCtx = document.getElementById('outcomeChart');
        if (outcomeCtx) {
            new Chart(outcomeCtx, {
                type: 'pie',
                data: {
                    labels: [],
                    datasets: [{
                        data: [],
                        backgroundColor: [
                            'rgba(32, 90, 68, 0.6)',
                            'rgba(239, 68, 68, 0.6)',
                            'rgba(59, 130, 246, 0.6)',
                        ],
                    }]
                },
                options: chartOptions
            });
        }
    });
</script>
@endpush

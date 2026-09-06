@extends('layouts.app')

@section('title', 'Call Logs - ' . brand_name())
@section('page-title', 'Call Logs')

@section('header-actions')
    <div style="display: flex; gap: 10px;">
        <a href="{{ route('calls.create') }}" class="px-4 py-2 btn-brand-gradient text-white rounded-lg transition-colors duration-200 text-sm font-medium">
            <i class="fas fa-plus mr-2"></i> Add Call
        </a>
        <a href="{{ route('calls.export.csv') }}?{{ http_build_query(request()->all()) }}" class="px-4 py-2 btn-brand-gradient text-white rounded-lg transition-colors duration-200 text-sm font-medium">
            <i class="fas fa-download mr-2"></i> Export CSV
        </a>
    </div>
@endsection

@section('content')
    @if(session('success'))
        <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <!-- Quick Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white p-4 rounded-lg shadow border border-[#E5DED4]">
            <div class="text-sm text-[#B3B5B4] mb-1">Total Calls</div>
            <div class="text-2xl font-bold text-brand-primary">{{ $quickStats['total'] ?? 0 }}</div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow border border-[#E5DED4]">
            <div class="text-sm text-[#B3B5B4] mb-1">Today</div>
            <div class="text-2xl font-bold text-brand-primary">{{ $quickStats['today'] ?? 0 }}</div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow border border-[#E5DED4]">
            <div class="text-sm text-[#B3B5B4] mb-1">This Week</div>
            <div class="text-2xl font-bold text-brand-primary">{{ $quickStats['this_week'] ?? 0 }}</div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow border border-[#E5DED4]">
            <div class="text-sm text-[#B3B5B4] mb-1">This Month</div>
            <div class="text-2xl font-bold text-brand-primary">{{ $quickStats['this_month'] ?? 0 }}</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6 mb-6">
        <form method="GET" action="{{ route('calls.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label for="from_date" class="block text-sm font-medium text-brand-primary mb-2">From Date</label>
                <input type="date" name="from_date" id="from_date" value="{{ request('from_date') }}"
                       class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg focus:ring-2 focus:ring-brand focus:border-brand">
            </div>
            <div>
                <label for="to_date" class="block text-sm font-medium text-brand-primary mb-2">To Date</label>
                <input type="date" name="to_date" id="to_date" value="{{ request('to_date') }}"
                       class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg focus:ring-2 focus:ring-brand focus:border-brand">
            </div>
            @if(isset($users) && count($users) > 0)
            <div>
                <label for="user_id" class="block text-sm font-medium text-brand-primary mb-2">User</label>
                <select name="user_id" id="user_id"
                        class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg focus:ring-2 focus:ring-brand focus:border-brand">
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
                        class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg focus:ring-2 focus:ring-brand focus:border-brand">
                    <option value="">All Types</option>
                    <option value="incoming" {{ request('call_type') == 'incoming' ? 'selected' : '' }}>Incoming</option>
                    <option value="outgoing" {{ request('call_type') == 'outgoing' ? 'selected' : '' }}>Outgoing</option>
                </select>
            </div>
            <div>
                <label for="status" class="block text-sm font-medium text-brand-primary mb-2">Status</label>
                <select name="status" id="status"
                        class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg focus:ring-2 focus:ring-brand focus:border-brand">
                    <option value="">All Status</option>
                    <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Answered</option>
                    <option value="missed" {{ request('status') == 'missed' ? 'selected' : '' }}>Missed / Unanswered</option>
                    <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                    <option value="busy" {{ request('status') == 'busy' ? 'selected' : '' }}>Busy</option>
                </select>
            </div>
            <div>
                <label for="call_outcome" class="block text-sm font-medium text-brand-primary mb-2">Outcome</label>
                <select name="call_outcome" id="call_outcome"
                        class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg focus:ring-2 focus:ring-brand focus:border-brand">
                    <option value="">All Outcomes</option>
                    <option value="interested" {{ request('call_outcome') == 'interested' ? 'selected' : '' }}>Interested</option>
                    <option value="not_interested" {{ request('call_outcome') == 'not_interested' ? 'selected' : '' }}>Not Interested</option>
                    <option value="callback" {{ request('call_outcome') == 'callback' ? 'selected' : '' }}>Callback</option>
                    <option value="no_answer" {{ request('call_outcome') == 'no_answer' ? 'selected' : '' }}>No Answer</option>
                    <option value="busy" {{ request('call_outcome') == 'busy' ? 'selected' : '' }}>Busy</option>
                    <option value="other" {{ request('call_outcome') == 'other' ? 'selected' : '' }}>Other</option>
                </select>
            </div>
            <div class="md:col-span-2">
                <label for="search" class="block text-sm font-medium text-brand-primary mb-2">Search</label>
                <input type="text" name="search" id="search" value="{{ request('search') }}"
                       placeholder="Search by phone number or lead name..."
                       class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg focus:ring-2 focus:ring-brand focus:border-brand">
            </div>
            <div class="md:col-span-4 flex gap-2">
                <button type="submit" class="px-6 py-2 btn-brand-gradient text-white rounded-lg transition-colors duration-200 font-medium">
                    <i class="fas fa-search mr-2"></i> Filter
                </button>
                @if(request()->anyFilled(['from_date', 'to_date', 'user_id', 'call_type', 'status', 'call_outcome', 'search']))
                    <a href="{{ route('calls.index') }}" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors duration-200 font-medium">
                        <i class="fas fa-times mr-2"></i> Clear
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Call Logs Table -->
    <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-[#E5DED4]">
                <thead class="bg-[#F7F6F3]">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-brand-primary uppercase tracking-wider">Phone</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-brand-primary uppercase tracking-wider">Lead</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-brand-primary uppercase tracking-wider">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-brand-primary uppercase tracking-wider">Duration</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-brand-primary uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-brand-primary uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-brand-primary uppercase tracking-wider">Outcome</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-brand-primary uppercase tracking-wider">Date & Time</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-brand-primary uppercase tracking-wider">Recording</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-brand-primary uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-[#E5DED4]">
                    @forelse($callLogs as $callLog)
                        @php
                            $statusClass = match($callLog->status) {
                                'completed' => 'bg-green-100 text-green-800',
                                'rejected' => 'bg-rose-100 text-rose-800',
                                'busy' => 'bg-yellow-100 text-yellow-800',
                                default => $callLog->call_type === 'outgoing'
                                    ? 'bg-amber-100 text-amber-800'
                                    : 'bg-red-100 text-red-800',
                            };
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-brand-primary">
                                {{ $callLog->phone_number }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-brand-primary">
                                @if($callLog->lead)
                                    <a href="{{ route('leads.show', $callLog->lead_id) }}" class="text-brand-secondary hover:underline">
                                        {{ $callLog->lead->name }}
                                    </a>
                                @else
                                    <span class="text-gray-400">N/A</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-brand-primary">
                                {{ $callLog->callerUser->name ?? 'N/A' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-brand-primary">
                                {{ $callLog->formatted_duration }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                    {{ $callLog->call_type == 'incoming' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800' }}">
                                    {{ $callLog->call_type_label }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $statusClass }}">
                                    {{ $callLog->status_label }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-brand-primary">
                                {{ $callLog->call_outcome_label }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-brand-primary">
                                {{ $callLog->start_time->format('M d, Y H:i') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-brand-primary">
                                @if(filled($callLog->recording_url))
                                    <a href="{{ route('calls.recording', $callLog) }}" target="_blank" class="inline-flex items-center rounded-lg bg-emerald-100 px-3 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-200">
                                        <i class="fas fa-play mr-2"></i> Play
                                    </a>
                                @elseif(str_contains(strtolower((string) $callLog->notes), 'recording unavailable'))
                                    <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">Unavailable</span>
                                @else
                                    <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-500">Not enabled</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                @if($callLog->lead_id)
                                    <a href="{{ route('leads.show', $callLog->lead_id) }}" class="inline-flex items-center rounded-lg bg-gradient-to-r from-[#063A1C] to-[#205A44] px-3 py-2 text-xs font-semibold text-white transition-opacity duration-200 hover:opacity-90">
                                        <i class="fas fa-eye mr-2"></i> View
                                    </a>
                                @else
                                    <span class="text-xs text-gray-400">No lead linked</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-6 py-4 text-center text-sm text-gray-500">
                                No call logs found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        @if($callLogs->hasPages())
            <div class="px-6 py-4 border-t border-[#E5DED4]">
                {{ $callLogs->links() }}
            </div>
        @endif
    </div>
@endsection

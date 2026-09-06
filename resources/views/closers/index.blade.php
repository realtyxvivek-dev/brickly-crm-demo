@extends('layouts.app')

@section('title', 'Closers - ' . brand_name())
@section('page-title', 'Closers')
@section('page-subtitle', 'Site Visits Converted to Closers')

@push('styles')
<style>
    #closersGrid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 1.25rem;
    }
    .closer-card {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        border-left: 4px solid #205A44;
        display: flex;
        flex-direction: column;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .closer-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }
    .closer-card .closer-header {
        margin-bottom: 12px;
    }
    .closer-card h3 {
        font-size: 18px;
        font-weight: 600;
        color: #063A1C;
        margin-bottom: 8px;
    }
    .closer-card .closer-info {
        color: #6b7280;
        font-size: 14px;
        margin: 4px 0;
    }
    .closer-card .closer-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 500;
        margin-top: 8px;
    }
    .closer-card .badge-pending {
        background: #fef3c7;
        color: #92400e;
    }
    .closer-card .badge-verified {
        background: #d1fae5;
        color: #065f46;
    }
    .closer-card .badge-rejected {
        background: #fee2e2;
        color: #991b1b;
    }
    .closer-card .closer-actions {
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid #e5e7eb;
    }
    .closer-card .closer-actions a {
        display: inline-block;
        padding: 8px 16px;
        background: #205A44;
        color: white;
        border-radius: 6px;
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.3s;
        text-align: center;
        width: 100%;
    }
    .closer-card .closer-actions a:hover {
        background: #15803d;
    }
</style>
@endpush

@section('content')
    <div>
        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
            <div class="flex flex-wrap items-center gap-4">
                <!-- Status Filter -->
                <div class="flex items-center gap-2">
                    <label class="text-sm font-medium text-gray-700">Status:</label>
                    <div class="flex gap-2">
                        <a href="{{ route('closers.index', array_merge(request()->except('status'), ['status' => ''])) }}" 
                           class="px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ !request()->has('status') ? 'bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                            All ({{ $counts['all'] ?? 0 }})
                        </a>
                        <a href="{{ route('closers.index', array_merge(request()->except('status'), ['status' => 'draft'])) }}" 
                           class="px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->get('status') === 'draft' ? 'bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                            Draft ({{ $counts['draft'] ?? 0 }})
                        </a>
                        <a href="{{ route('closers.index', array_merge(request()->except('status'), ['status' => 'pending_crm'])) }}" 
                           class="px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->get('status') === 'pending_crm' ? 'bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                            Pending CRM ({{ $counts['pending_crm'] ?? 0 }})
                        </a>
                        <a href="{{ route('closers.index', array_merge(request()->except('status'), ['status' => 'approved'])) }}" 
                           class="px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->get('status') === 'approved' ? 'bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                            Approved ({{ $counts['approved'] ?? 0 }})
                        </a>
                        <a href="{{ route('closers.index', array_merge(request()->except('status'), ['status' => 'rejected'])) }}" 
                           class="px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->get('status') === 'rejected' ? 'bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                            Rejected ({{ $counts['rejected'] ?? 0 }})
                        </a>
                    </div>
                </div>

                <!-- Search -->
                <div class="flex-1 min-w-[200px]">
                    <form method="GET" action="{{ route('closers.index') }}" class="flex gap-2">
                        <input type="text" 
                               name="search" 
                               value="{{ request()->get('search') }}" 
                               placeholder="Search by name, phone, or project..."
                               class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        @if(request()->has('status'))
                            <input type="hidden" name="status" value="{{ request()->get('status') }}">
                        @endif
                        <button type="submit" class="px-6 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg hover:from-[#205A44] hover:to-[#15803d] transition-colors duration-200 font-medium">
                            <i class="fas fa-search"></i>
                        </button>
                        @if(request()->has('search'))
                            <a href="{{ route('closers.index', request()->except('search')) }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors duration-200">
                                <i class="fas fa-times"></i>
                            </a>
                        @endif
                    </form>
                </div>
            </div>
        </div>

        <!-- Closers Grid -->
        @if($closers->count() > 0)
            <div id="closersGrid">
                @foreach($closers as $closer)
                    <div class="closer-card">
                        <div class="closer-header">
                            <h3>{{ $closer->customer_name ?? $closer->lead->name ?? 'N/A' }}</h3>
                            <div class="closer-info">
                                <p><i class="fas fa-phone mr-2"></i>{{ $closer->phone ?? $closer->lead->phone ?? 'N/A' }}</p>
                                @if($closer->project)
                                    <p><i class="fas fa-building mr-2"></i>{{ $closer->project }}</p>
                                @endif
                                <p><i class="fas fa-calendar mr-2"></i>Visit: {{ $closer->date_of_visit ? \Carbon\Carbon::parse($closer->date_of_visit)->format('M d, Y') : ($closer->scheduled_at ? $closer->scheduled_at->format('M d, Y') : 'N/A') }}</p>
                                @if($closer->converted_to_closer_at)
                                    <p><i class="fas fa-check-circle mr-2"></i>Converted: {{ $closer->converted_to_closer_at->format('M d, Y') }}</p>
                                @endif
                                @if($closer->closerVerifiedBy)
                                    <p><i class="fas fa-user-check mr-2"></i>Verified By: {{ $closer->closerVerifiedBy->name }}</p>
                                    @if($closer->closer_verified_at)
                                        <p class="text-xs text-gray-500 ml-6">{{ $closer->closer_verified_at->format('M d, Y') }}</p>
                                    @endif
                                @endif
                            </div>
                            <div>
                                @if($closer->closer_status === 'draft')
                                    <span class="closer-badge badge-pending">Draft</span>
                                @elseif($closer->closer_status === 'pending_crm')
                                    <span class="closer-badge badge-pending">Pending</span>
                                @elseif($closer->closer_status === 'approved')
                                    <span class="closer-badge badge-verified">Verified</span>
                                @elseif($closer->closer_status === 'correction_required')
                                    <span class="closer-badge badge-pending">Correction Required</span>
                                @elseif($closer->closer_status === 'rejected')
                                    <span class="closer-badge badge-rejected">Rejected</span>
                                @endif
                            </div>
                        </div>
                        <div class="closer-actions">
                            <a href="{{ route('leads.show', $closer->lead_id ?? '#') }}">
                                <i class="fas fa-eye mr-2"></i>View Lead
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Pagination -->
            <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6 mt-6 rounded-lg">
                {{ $closers->links() }}
            </div>
        @else
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
                <i class="fas fa-inbox text-gray-400 text-4xl mb-4"></i>
                <p class="text-gray-500 text-lg">No closers found</p>
                @if(request()->has('search') || request()->has('status'))
                    <a href="{{ route('closers.index') }}" class="mt-4 inline-block text-indigo-600 hover:text-indigo-900">
                        Clear filters
                    </a>
                @endif
            </div>
        @endif
    </div>
@endsection

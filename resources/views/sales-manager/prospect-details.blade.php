@extends('sales-manager.layout')

@section('title', ($prospect->customer_name ?? 'Prospect') . ' - Prospect Details')
@section('page-title', 'Prospect Details')

@push('styles')
<style>
    .detail-section {
        background: white;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        margin-bottom: 24px;
    }
    .detail-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
    }
    .detail-item {
        display: flex;
        flex-direction: column;
    }
    .detail-label {
        font-size: 12px;
        font-weight: 600;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 4px;
    }
    .detail-value {
        font-size: 14px;
        color: #111827;
        font-weight: 500;
    }
    .badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }
    .badge-pending {
        background: #fef3c7;
        color: #92400e;
    }
    .badge-verified {
        background: #d1fae5;
        color: #065f46;
    }
    .badge-rejected {
        background: #fee2e2;
        color: #991b1b;
    }
    .badge-hot {
        background: #fee2e2;
        color: #991b1b;
    }
    .badge-warm {
        background: #fed7aa;
        color: #9a3412;
    }
    .badge-cold {
        background: #dbeafe;
        color: #1e40af;
    }
    .badge-junk {
        background: #f3f4f6;
        color: #374151;
    }
</style>
@endpush

@section('content')
<div class="space-y-6">
    <!-- Header Section -->
    <div class="detail-section">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 mb-2">{{ $prospect->customer_name ?? 'N/A' }}</h1>
                <div class="flex items-center gap-4">
                    <span class="badge badge-{{ $prospect->verification_status === 'pending_verification' || $prospect->verification_status === 'pending' ? 'pending' : ($prospect->verification_status === 'verified' || $prospect->verification_status === 'approved' ? 'verified' : 'rejected') }}">
                        {{ ucfirst(str_replace('_', ' ', $prospect->verification_status)) }}
                    </span>
                    @if($prospect->lead_status)
                    <span class="badge badge-{{ $prospect->lead_status }}">
                        {{ ucfirst($prospect->lead_status) }}
                    </span>
                    @endif
                    <span class="text-sm text-gray-500">
                        <i class="fas fa-calendar mr-1"></i>
                        Created {{ $prospect->created_at->format('M d, Y') }}
                    </span>
                </div>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('sales-manager.prospects') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i>Back
                </a>
            </div>
        </div>
    </div>

    <!-- Contact Information -->
    <div class="detail-section">
        <h2 class="text-xl font-bold text-gray-900 mb-4">Contact Information</h2>
        <div class="detail-grid">
            <div class="detail-item">
                <span class="detail-label">Phone</span>
                <span class="detail-value">{{ $prospect->phone ?? 'N/A' }}</span>
            </div>
            @if($prospect->preferred_location)
            <div class="detail-item">
                <span class="detail-label">Location</span>
                <span class="detail-value">{{ $prospect->preferred_location }}</span>
            </div>
            @endif
            @if($prospect->telecaller)
            <div class="detail-item">
                <span class="detail-label">Created By</span>
                <span class="detail-value">{{ $prospect->telecaller->name }}</span>
            </div>
            @elseif($prospect->createdBy)
            <div class="detail-item">
                <span class="detail-label">Created By</span>
                <span class="detail-value">{{ $prospect->createdBy->name }}</span>
            </div>
            @endif
            @if($prospect->manager)
            <div class="detail-item">
                <span class="detail-label">Manager</span>
                <span class="detail-value">{{ $prospect->manager->name }}</span>
            </div>
            @endif
        </div>
    </div>

    <!-- Property Details -->
    <div class="detail-section">
        <h2 class="text-xl font-bold text-gray-900 mb-4">Property Details</h2>
        <div class="detail-grid">
            @if($prospect->budget)
            <div class="detail-item">
                <span class="detail-label">Budget</span>
                <span class="detail-value">
                    @if(is_numeric($prospect->budget))
                        ₹{{ number_format($prospect->budget, 2) }}
                    @else
                        {{ $prospect->budget }}
                    @endif
                </span>
            </div>
            @endif
            @if($prospect->size)
            <div class="detail-item">
                <span class="detail-label">Size</span>
                <span class="detail-value">{{ $prospect->size }}</span>
            </div>
            @endif
            @if($prospect->purpose)
            <div class="detail-item">
                <span class="detail-label">Purpose</span>
                <span class="detail-value">{{ $prospect->purpose === 'end_user' ? 'End User' : ($prospect->purpose === 'investment' ? 'Investment' : 'N/A') }}</span>
            </div>
            @endif
            @if($prospect->possession)
            <div class="detail-item">
                <span class="detail-label">Possession</span>
                <span class="detail-value">{{ $prospect->possession }}</span>
            </div>
            @endif
            @if($prospect->lead_score)
            <div class="detail-item">
                <span class="detail-label">Lead Score</span>
                <span class="detail-value">
                    @for($i = 1; $i <= 5; $i++)
                        @if($i <= $prospect->lead_score)
                            <span style="color: #fbbf24;">★</span>
                        @else
                            <span style="color: #d1d5db;">☆</span>
                        @endif
                    @endfor
                    <span class="text-gray-500 text-xs ml-1">({{ $prospect->lead_score }}/5)</span>
                </span>
            </div>
            @endif
        </div>
    </div>

    <!-- Interested Projects -->
    @if($prospect->interestedProjects && $prospect->interestedProjects->count() > 0)
    <div class="detail-section">
        <h2 class="text-xl font-bold text-gray-900 mb-4">Interested Projects</h2>
        <div class="flex flex-wrap gap-2">
            @foreach($prospect->interestedProjects as $project)
            <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-medium">
                {{ $project->name }}
            </span>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Remarks -->
    <div class="detail-section">
        <h2 class="text-xl font-bold text-gray-900 mb-4">Remarks</h2>
        <div class="space-y-4">
            @if($prospect->remark)
            <div>
                <span class="detail-label">Sales Executive Remark</span>
                <p class="mt-2 p-3 bg-gray-50 rounded-lg border border-gray-200 text-sm text-gray-700">
                    {{ $prospect->remark }}
                </p>
            </div>
            @endif
            @if($prospect->manager_remark)
            <div>
                <span class="detail-label">Manager Remark</span>
                <p class="mt-2 p-3 bg-green-50 rounded-lg border border-green-200 text-sm text-green-800">
                    {{ $prospect->manager_remark }}
                </p>
            </div>
            @endif
            @if($prospect->rejection_reason)
            <div>
                <span class="detail-label">Rejection Reason</span>
                <p class="mt-2 p-3 bg-red-50 rounded-lg border border-red-200 text-sm text-red-800">
                    {{ $prospect->rejection_reason }}
                </p>
            </div>
            @endif
        </div>
    </div>

    <!-- Verification Information -->
    @if($prospect->verified_at || $prospect->verifiedBy)
    <div class="detail-section">
        <h2 class="text-xl font-bold text-gray-900 mb-4">Verification Information</h2>
        <div class="detail-grid">
            @if($prospect->verified_at)
            <div class="detail-item">
                <span class="detail-label">Verified At</span>
                <span class="detail-value">{{ $prospect->verified_at->format('M d, Y H:i') }}</span>
            </div>
            @endif
            @if($prospect->verifiedBy)
            <div class="detail-item">
                <span class="detail-label">Verified By</span>
                <span class="detail-value">{{ $prospect->verifiedBy->name }}</span>
            </div>
            @endif
        </div>
    </div>
    @endif

    <!-- Quick Actions -->
    <div class="detail-section">
        <h2 class="text-xl font-bold text-gray-900 mb-4">Quick Actions</h2>
        <div class="flex gap-2 flex-wrap">
            @if($prospect->phone)
            <a href="tel:{{ $prospect->phone }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                <i class="fas fa-phone mr-2"></i>Call
            </a>
            <a href="https://wa.me/{{ preg_replace('/[^\d+]/', '', $prospect->phone) }}" target="_blank" class="px-4 py-2 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-lg hover:from-green-700 hover:to-green-800 transition-all">
                <i class="fab fa-whatsapp mr-2"></i>WhatsApp
            </a>
            @endif
        </div>
    </div>
</div>
@endsection

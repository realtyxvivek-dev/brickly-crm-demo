@extends('layouts.app')

@section('title', 'Call Log Details - ' . brand_name())
@section('page-title', 'Call Log Details')

@section('header-actions')
    <div style="display: flex; gap: 10px;">
        <a href="{{ route('calls.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors duration-200 text-sm font-medium">
            <i class="fas fa-arrow-left mr-2"></i> Back
        </a>
        @if(auth()->user()->id == $callLog->user_id || auth()->user()->isAdmin() || auth()->user()->isCrm())
            <a href="{{ route('calls.edit', $callLog->id) }}" class="px-4 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg hover:from-[#205A44] hover:to-[#15803d] transition-colors duration-200 text-sm font-medium">
                <i class="fas fa-edit mr-2"></i> Edit
            </a>
        @endif
        @if($callLog->lead)
            <a href="{{ route('leads.show', $callLog->lead_id) }}" class="px-4 py-2 btn-brand-gradient text-white rounded-lg transition-colors duration-200 text-sm font-medium">
                <i class="fas fa-user mr-2"></i> View Lead
            </a>
        @endif
    </div>
@endsection

@section('content')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Call Information -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Call Information Card -->
            <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6">
                <h3 class="text-lg font-semibold text-brand-primary mb-4">Call Information</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-medium text-[#B3B5B4]">Phone Number</label>
                        <p class="text-brand-primary font-medium">{{ $callLog->phone_number }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-[#B3B5B4]">Call Type</label>
                        <p>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                {{ $callLog->call_type == 'incoming' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800' }}">
                                {{ $callLog->call_type_label }}
                            </span>
                        </p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-[#B3B5B4]">Status</label>
                        <p>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                {{ $callLog->status == 'completed' ? 'bg-green-100 text-green-800' : 
                                    ($callLog->status == 'missed' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') }}">
                                {{ $callLog->status_label }}
                            </span>
                        </p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-[#B3B5B4]">Outcome</label>
                        <p class="text-brand-primary">{{ $callLog->call_outcome_label }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-[#B3B5B4]">Start Time</label>
                        <p class="text-brand-primary">{{ $callLog->start_time->format('M d, Y H:i:s') }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-[#B3B5B4]">End Time</label>
                        <p class="text-brand-primary">{{ $callLog->end_time ? $callLog->end_time->format('M d, Y H:i:s') : 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-[#B3B5B4]">Duration</label>
                        <p class="text-brand-primary font-semibold">{{ $callLog->formatted_duration }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-[#B3B5B4]">Made By</label>
                        <p class="text-brand-primary">{{ $callLog->callerUser->name ?? 'N/A' }}</p>
                    </div>
                    @if($callLog->next_followup_date)
                    <div>
                        <label class="text-sm font-medium text-[#B3B5B4]">Next Followup</label>
                        <p class="text-brand-primary">{{ $callLog->next_followup_date->format('M d, Y H:i') }}</p>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Call Notes -->
            <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6">
                <h3 class="text-lg font-semibold text-brand-primary mb-4">Call Notes</h3>
                @if($callLog->notes)
                    <div class="prose max-w-none text-brand-primary">
                        {!! nl2br(e($callLog->notes)) !!}
                    </div>
                @else
                    <p class="text-gray-400 italic">No notes added for this call.</p>
                @endif
            </div>

            <!-- Previous Calls to Same Lead -->
            @if(count($previousCalls) > 0)
            <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6">
                <h3 class="text-lg font-semibold text-brand-primary mb-4">Previous Calls to This Lead</h3>
                <div class="space-y-3">
                    @foreach($previousCalls as $prevCall)
                        <div class="border-b border-[#E5DED4] pb-3 last:border-0">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-sm text-brand-primary font-medium">{{ $prevCall->start_time->format('M d, Y H:i') }}</p>
                                    <p class="text-xs text-[#B3B5B4]">{{ $prevCall->formatted_duration }} • {{ $prevCall->call_type_label }}</p>
                                </div>
                                <a href="{{ route('calls.show', $prevCall->id) }}" class="text-brand-secondary hover:text-[#15803d] text-sm">
                                    View <i class="fas fa-arrow-right ml-1"></i>
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Lead Information -->
            @if($callLog->lead)
            <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6">
                <h3 class="text-lg font-semibold text-brand-primary mb-4">Lead Information</h3>
                <div class="space-y-3">
                    <div>
                        <label class="text-sm font-medium text-[#B3B5B4]">Name</label>
                        <p class="text-brand-primary font-medium">{{ $callLog->lead->name }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-[#B3B5B4]">Phone</label>
                        <p class="text-brand-primary">{{ $callLog->lead->phone }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-[#B3B5B4]">Email</label>
                        <p class="text-brand-primary">{{ $callLog->lead->email ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-[#B3B5B4]">Status</label>
                        <p class="text-brand-primary">{{ ucfirst($callLog->lead->status) }}</p>
                    </div>
                    <div class="pt-3 border-t border-[#E5DED4]">
                        <a href="{{ route('leads.show', $callLog->lead_id) }}" class="block w-full text-center px-4 py-2 btn-brand-gradient text-white rounded-lg transition-colors duration-200 text-sm font-medium">
                            View Full Lead Details
                        </a>
                    </div>
                </div>
            </div>
            @endif

            <!-- Related Tasks -->
            @if(count($relatedTasks) > 0)
            <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6">
                <h3 class="text-lg font-semibold text-brand-primary mb-4">Related Tasks</h3>
                <div class="space-y-3">
                    @foreach($relatedTasks as $task)
                        <div class="border-b border-[#E5DED4] pb-3 last:border-0">
                            <p class="text-sm text-brand-primary font-medium">{{ $task->title }}</p>
                            <p class="text-xs text-[#B3B5B4]">{{ ucfirst($task->status) }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Actions -->
            <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6">
                <h3 class="text-lg font-semibold text-brand-primary mb-4">Actions</h3>
                <div class="space-y-2">
                    @if($callLog->lead)
                        <a href="{{ route('leads.show', $callLog->lead_id) }}" class="block w-full text-center px-4 py-2 btn-brand-gradient text-white rounded-lg transition-colors duration-200 text-sm font-medium">
                            <i class="fas fa-user mr-2"></i> View Lead
                        </a>
                    @endif
                    @if(auth()->user()->id == $callLog->user_id || auth()->user()->isAdmin() || auth()->user()->isCrm())
                        <a href="{{ route('calls.edit', $callLog->id) }}" class="block w-full text-center px-4 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg hover:from-[#205A44] hover:to-[#15803d] transition-colors duration-200 text-sm font-medium">
                            <i class="fas fa-edit mr-2"></i> Edit Call
                        </a>
                    @endif
                    @if(auth()->user()->isAdmin() || auth()->user()->isCrm())
                        <form action="{{ route('calls.destroy', $callLog->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this call log?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="w-full px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors duration-200 text-sm font-medium">
                                <i class="fas fa-trash mr-2"></i> Delete Call
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

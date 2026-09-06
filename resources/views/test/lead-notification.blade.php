@extends('layouts.app')

@section('title', 'Test: Lead assigned notification')
@section('page-title', 'Test: Lead assigned notification')
@section('page-subtitle', '1-click test for popup and email')

@section('content')
<div class="max-w-2xl mx-auto">
    @if(session('success'))
        <div class="mb-6 p-4 rounded-lg bg-green-100 text-green-800 border border-green-200">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-6 p-4 rounded-lg bg-red-100 text-red-800 border border-red-200">
            {{ session('error') }}
        </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-2">What this does</h2>
        <p class="text-gray-600 mb-4">
            Click the button below to simulate a lead being assigned to you. This will:
        </p>
        <ul class="list-disc list-inside text-gray-600 mb-6 space-y-1">
            <li>Create a test lead and assign it to your user</li>
            <li>Fire the same event used in production (popup + email)</li>
            <li>If you have <strong>Dashboard</strong> open in another tab, the lead-assigned popup will appear there</li>
            <li>An email will be sent to your user email (check spam if not in inbox)</li>
        </ul>

        <form method="POST" action="{{ route('test.lead-notification.simulate') }}" class="inline">
            @csrf
            <button type="submit" class="px-6 py-3 rounded-lg font-semibold text-white transition shadow-lg hover:opacity-90" style="background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));">
                <i class="fa-solid fa-bell mr-2"></i> Simulate lead assigned to me
            </button>
        </form>
    </div>

    <div class="bg-gray-50 rounded-xl border border-gray-200 p-4 text-sm text-gray-600">
        <strong>Tip:</strong> Open <a href="{{ route('dashboard') }}" class="text-blue-600 hover:underline" target="_blank">Dashboard</a> in another tab, then click the button above. The popup should appear on the Dashboard tab (or on this page after redirect if Pusher is not configured).
    </div>
</div>

@if(session('lead_just_assigned'))
@php $la = session('lead_just_assigned'); $name = $la['name'] ?? 'Lead'; @endphp
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof showLeadAssignedPopup === 'function') {
        showLeadAssignedPopup({
            title: 'New lead assigned',
            message: 'You have 1 new lead assigned: ' + {{ json_encode($name) }} + '. View leads to see details and call.',
            viewUrl: {!! json_encode($la['viewUrl'] ?? '') !!},
            leadPhone: {!! json_encode($la['phone'] ?? '') !!},
            leadName: {!! json_encode($name) !!}
        });
    }
});
</script>
@endpush
@endif
@endsection

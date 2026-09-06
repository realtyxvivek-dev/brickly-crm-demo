@extends('layouts.app')
@section('title', 'Fraud Reviews')
@section('page-title', 'Fraud Reviews')
@section('content')
<div class="w-full space-y-6">
    @include('attendance._flash')
    @include('hr-manager.attendance._nav')
    <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6 overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead><tr class="text-left text-[#6B7280] border-b"><th class="py-2">User</th><th>Photo</th><th>Status</th><th>Remarks</th><th>Action</th></tr></thead>
            <tbody>
            @forelse($reviews as $review)
                <tr class="border-b last:border-0">
                    <td class="py-3">{{ $review->user?->name }}</td>
                    <td>{{ $review->attendancePhoto?->file_hash ? substr($review->attendancePhoto->file_hash, 0, 12) : 'No selfie' }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $review->status)) }}</td>
                    <td>{{ $review->remarks ?: '--' }}</td>
                    <td>
                        <div class="flex gap-2">
                            <form method="POST" action="{{ route('hr-manager.attendance.fraud-reviews.approve', $review) }}">@csrf<button class="px-3 py-2 bg-[#205A44] text-white rounded-lg text-xs">Accept</button></form>
                            <form method="POST" action="{{ route('hr-manager.attendance.fraud-reviews.reject', $review) }}">@csrf<button class="px-3 py-2 bg-rose-600 text-white rounded-lg text-xs">Reject</button></form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-4 text-[#6B7280]">No fraud reviews pending.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

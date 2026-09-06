@extends('layouts.app')

@section('title', 'Login Security Event')
@section('page-title', 'Login Security Event')
@section('page-subtitle', $event->email_normalized)

@section('content')
<div style="padding:24px;">
    @if(session('success'))
        <div style="background:#d1fae5;border:1px solid #6ee7b7;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:20px;">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
        </div>
    @endif

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:18px;">
        <section style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;">
            <h2 style="margin:0 0 16px;color:#111827;">Request Details</h2>
            <dl style="display:grid;grid-template-columns:180px 1fr;gap:12px;margin:0;">
                <dt style="color:#64748b;">User</dt><dd style="margin:0;font-weight:800;">{{ $event->user?->name ?: 'Unknown' }}</dd>
                <dt style="color:#64748b;">Email</dt><dd style="margin:0;">{{ $event->email_normalized }}</dd>
                <dt style="color:#64748b;">Event</dt><dd style="margin:0;">{{ str_replace('_', ' ', $event->event_type) }} / {{ $event->status }}</dd>
                <dt style="color:#64748b;">Method</dt><dd style="margin:0;">{{ $event->login_method ?: '-' }}</dd>
                <dt style="color:#64748b;">Lock Level</dt><dd style="margin:0;">{{ strtoupper(str_replace('_', ' ', $event->lock_level)) }}</dd>
                <dt style="color:#64748b;">Failed Count</dt><dd style="margin:0;">{{ $event->failed_count }}</dd>
                <dt style="color:#64748b;">Lock Until</dt><dd style="margin:0;">{{ $event->lock_until?->format('d M Y, h:i:s A') ?: '-' }}</dd>
                <dt style="color:#64748b;">IP</dt><dd style="margin:0;">{{ $event->ip_address ?: '-' }}</dd>
                <dt style="color:#64748b;">Device</dt><dd style="margin:0;">{{ $event->device_summary ?: '-' }}</dd>
                <dt style="color:#64748b;">User Agent</dt><dd style="margin:0;word-break:break-word;">{{ $event->user_agent ?: '-' }}</dd>
                <dt style="color:#64748b;">Location</dt>
                <dd style="margin:0;">
                    @if($event->latitude && $event->longitude)
                        {{ $event->latitude }}, {{ $event->longitude }} @if($event->location_accuracy) (accuracy {{ $event->location_accuracy }}m) @endif
                        <a href="https://www.google.com/maps?q={{ $event->latitude }},{{ $event->longitude }}" target="_blank" style="color:#0f5132;font-weight:800;margin-left:8px;">Open Map</a>
                    @else
                        Not submitted
                    @endif
                </dd>
                <dt style="color:#64748b;">Reason</dt><dd style="margin:0;white-space:pre-wrap;">{{ $event->reason ?: '-' }}</dd>
            </dl>

            @if($event->selfie_path)
                <div style="margin-top:18px;">
                    <h3 style="margin:0 0 10px;color:#111827;">Verification Selfie</h3>
                    <img src="{{ asset('storage/' . $event->selfie_path) }}" alt="Verification selfie" style="max-width:320px;border-radius:12px;border:1px solid #e5e7eb;">
                </div>
            @endif

            @if($event->event_type === \App\Models\LoginSecurityEvent::TYPE_ACCESS_REQUEST && $event->status === 'pending' && $event->user)
                <div style="display:flex;gap:10px;margin-top:22px;">
                    <form method="POST" action="{{ route('admin.login-security.unlock', $event) }}">
                        @csrf
                        <button type="submit" style="border:0;background:#16a34a;color:#fff;border-radius:8px;padding:10px 14px;font-weight:900;">Approve & Unlock</button>
                    </form>
                    <form method="POST" action="{{ route('admin.login-security.reject', $event) }}">
                        @csrf
                        <button type="submit" style="border:0;background:#b91c1c;color:#fff;border-radius:8px;padding:10px 14px;font-weight:900;">Reject</button>
                    </form>
                </div>
            @elseif($event->user && in_array($event->lock_level, [\App\Models\LoginSecurityEvent::LOCK_TWO_MIN, \App\Models\LoginSecurityEvent::LOCK_FIFTEEN_MIN, \App\Models\LoginSecurityEvent::LOCK_ADMIN], true))
                <form method="POST" action="{{ route('admin.login-security.unlock', $event) }}" style="margin-top:22px;">
                    @csrf
                    <button type="submit" style="border:0;background:#16a34a;color:#fff;border-radius:8px;padding:10px 14px;font-weight:900;">Unlock Account</button>
                </form>
            @endif
        </section>

        <aside style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;">
            <h2 style="margin:0 0 16px;color:#111827;">Recent History</h2>
            @foreach($history as $row)
                <div style="border-bottom:1px solid #f1f5f9;padding:10px 0;">
                    <div style="font-weight:800;color:#111827;">{{ str_replace('_', ' ', $row->event_type) }}</div>
                    <div style="font-size:12px;color:#64748b;">{{ $row->created_at?->format('d M, h:i A') }} / {{ $row->status }}</div>
                    <div style="font-size:12px;color:#64748b;">{{ $row->ip_address ?: '-' }}</div>
                </div>
            @endforeach
        </aside>
    </div>
</div>
@endsection

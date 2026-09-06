@extends('layouts.app')

@section('title', 'Login Security')
@section('page-title', 'Login Security')
@section('page-subtitle', 'Review locked accounts and urgent access requests')

@section('content')
<div style="padding:24px;">
    @if(session('success'))
        <div style="background:#d1fae5;border:1px solid #6ee7b7;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:20px;">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
        </div>
    @endif

    <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;margin-bottom:22px;">
        <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:12px;padding:18px;">
            <div style="font-size:12px;text-transform:uppercase;color:#991b1b;font-weight:800;">Locked Users</div>
            <div style="font-size:30px;font-weight:900;color:#7f1d1d;margin-top:6px;">{{ $lockedUsers->count() }}</div>
        </div>
        <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:12px;padding:18px;">
            <div style="font-size:12px;text-transform:uppercase;color:#9a3412;font-weight:800;">Pending Requests</div>
            <div style="font-size:30px;font-weight:900;color:#7c2d12;margin-top:6px;">{{ \App\Models\LoginSecurityEvent::where('event_type', \App\Models\LoginSecurityEvent::TYPE_ACCESS_REQUEST)->where('status', 'pending')->count() }}</div>
        </div>
        <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:12px;padding:18px;">
            <div style="font-size:12px;text-transform:uppercase;color:#1d4ed8;font-weight:800;">Failed Attempts Today</div>
            <div style="font-size:30px;font-weight:900;color:#1e3a8a;margin-top:6px;">{{ \App\Models\LoginSecurityEvent::where('event_type', \App\Models\LoginSecurityEvent::TYPE_FAILED)->whereDate('created_at', today())->count() }}</div>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.login-security.index') }}" style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;margin-bottom:18px;display:flex;gap:10px;flex-wrap:wrap;">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search email, user, IP..." style="flex:1;min-width:220px;border:1px solid #d1d5db;border-radius:8px;padding:10px;">
        <select name="event_type" style="border:1px solid #d1d5db;border-radius:8px;padding:10px;background:#fff;">
            <option value="">All Events</option>
            <option value="failed_attempt" @selected(request('event_type') === 'failed_attempt')>Failed Attempt</option>
            <option value="locked" @selected(request('event_type') === 'locked')>Locked</option>
            <option value="access_request" @selected(request('event_type') === 'access_request')>Access Request</option>
            <option value="unlocked" @selected(request('event_type') === 'unlocked')>Unlocked</option>
        </select>
        <select name="status" style="border:1px solid #d1d5db;border-radius:8px;padding:10px;background:#fff;">
            <option value="">All Status</option>
            <option value="pending" @selected(request('status') === 'pending')>Pending</option>
            <option value="locked" @selected(request('status') === 'locked')>Locked</option>
            <option value="admin_locked" @selected(request('status') === 'admin_locked')>Admin Locked</option>
            <option value="approved" @selected(request('status') === 'approved')>Approved</option>
            <option value="rejected" @selected(request('status') === 'rejected')>Rejected</option>
        </select>
        <button type="submit" style="background:#0f5132;color:#fff;border:0;border-radius:8px;padding:10px 16px;font-weight:800;">Filter</button>
        <a href="{{ route('admin.login-security.index') }}" style="color:#64748b;text-decoration:none;padding:10px;">Clear</a>
    </form>

    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
        <table style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="background:#f8fafc;border-bottom:1px solid #e5e7eb;">
                    <th style="text-align:left;padding:12px;font-size:12px;color:#64748b;text-transform:uppercase;">Time</th>
                    <th style="text-align:left;padding:12px;font-size:12px;color:#64748b;text-transform:uppercase;">User</th>
                    <th style="text-align:left;padding:12px;font-size:12px;color:#64748b;text-transform:uppercase;">Event</th>
                    <th style="text-align:left;padding:12px;font-size:12px;color:#64748b;text-transform:uppercase;">Lock</th>
                    <th style="text-align:left;padding:12px;font-size:12px;color:#64748b;text-transform:uppercase;">IP / Device</th>
                    <th style="text-align:right;padding:12px;font-size:12px;color:#64748b;text-transform:uppercase;">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($events as $event)
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:12px;color:#475569;">{{ $event->created_at?->format('d M, h:i A') }}</td>
                        <td style="padding:12px;">
                            <div style="font-weight:800;color:#111827;">{{ $event->user?->name ?: 'Unknown' }}</div>
                            <div style="font-size:12px;color:#64748b;">{{ $event->email_normalized }}</div>
                        </td>
                        <td style="padding:12px;">
                            <span style="display:inline-block;background:#f1f5f9;border-radius:999px;padding:5px 9px;font-size:12px;font-weight:800;">{{ str_replace('_', ' ', $event->event_type) }}</span>
                            <div style="font-size:12px;color:#64748b;margin-top:4px;">{{ $event->status }}</div>
                        </td>
                        <td style="padding:12px;color:#475569;">
                            {{ strtoupper(str_replace('_', ' ', $event->lock_level)) }}
                            <div style="font-size:12px;color:#64748b;">failed: {{ $event->failed_count }}</div>
                        </td>
                        <td style="padding:12px;color:#475569;">
                            {{ $event->ip_address ?: '-' }}
                            <div style="font-size:12px;color:#64748b;">{{ $event->device_summary ?: '-' }}</div>
                        </td>
                        <td style="padding:12px;text-align:right;">
                            <a href="{{ route('admin.login-security.show', $event) }}" style="background:#0f5132;color:#fff;text-decoration:none;border-radius:8px;padding:8px 12px;font-weight:800;">Review</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="padding:48px;text-align:center;color:#94a3b8;">No login security events found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:16px;">{{ $events->links() }}</div>
</div>
@endsection

@extends('layouts.app')

@section('title', 'My Support Tickets')
@section('page-title', 'Support')
@section('page-subtitle', 'View and manage your support tickets')

@section('content')
<div style="padding:24px;">

    {{-- Alerts --}}
    @if(session('success'))
    <div style="background:#d1fae5;border:1px solid #6ee7b7;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:20px;display:flex;align-items:center;gap:10px;">
        <i class="fas fa-check-circle"></i> {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div style="background:#fee2e2;border:1px solid #fca5a5;color:#991b1b;padding:12px 16px;border-radius:8px;margin-bottom:20px;display:flex;align-items:center;gap:10px;">
        <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
    </div>
    @endif

    {{-- Header --}}
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
        <div>
            <h2 style="margin:0;font-size:22px;font-weight:700;color:#1e293b;">My Tickets</h2>
            <p style="margin:4px 0 0;color:#64748b;font-size:14px;">Track all your support requests in one place</p>
        </div>
        <a href="{{ route('support.create') }}" style="background:linear-gradient(135deg,#3b82f6,#1d4ed8);color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;display:flex;align-items:center;gap:8px;">
            <i class="fas fa-plus"></i> New Ticket
        </a>
    </div>

    {{-- Filter Bar --}}
    <form method="GET" action="{{ route('support.index') }}" style="margin-bottom:20px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
            @foreach([''=>'All', 'open'=>'Open', 'in_progress'=>'In Progress', 'resolved'=>'Resolved', 'closed'=>'Closed'] as $val=>$label)
            <a href="{{ route('support.index', array_merge(request()->query(), ['status'=>$val])) }}"
               style="padding:6px 14px;border-radius:20px;font-size:13px;font-weight:500;text-decoration:none;border:1px solid {{ request('status')===$val ? '#3b82f6' : '#e2e8f0' }};background:{{ request('status')===$val ? '#dbeafe' : '#fff' }};color:{{ request('status')===$val ? '#1d4ed8' : '#64748b' }};">
                {{ $label }}
            </a>
            @endforeach
        </div>
    </form>

    {{-- Tickets Table --}}
    @if($tickets->isEmpty())
    <div style="text-align:center;padding:64px 24px;background:#fff;border-radius:12px;border:1px solid #e2e8f0;">
        <i class="fas fa-ticket-alt" style="font-size:48px;color:#cbd5e1;margin-bottom:16px;display:block;"></i>
        <h3 style="margin:0 0 8px;color:#475569;font-size:18px;">No tickets yet</h3>
        <p style="color:#94a3b8;margin:0 0 20px;font-size:14px;">Create a ticket to get help from our support team.</p>
        <a href="{{ route('support.create') }}" style="background:#3b82f6;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;">
            <i class="fas fa-plus" style="margin-right:6px;"></i> Create First Ticket
        </a>
    </div>
    @else
    <div style="background:#fff;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden;">
        <table style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0;">
                    <th style="padding:12px 16px;text-align:left;font-size:12px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;">Ticket #</th>
                    <th style="padding:12px 16px;text-align:left;font-size:12px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;">Title</th>
                    <th style="padding:12px 16px;text-align:left;font-size:12px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;">Category</th>
                    <th style="padding:12px 16px;text-align:left;font-size:12px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;">Priority</th>
                    <th style="padding:12px 16px;text-align:left;font-size:12px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;">Status</th>
                    <th style="padding:12px 16px;text-align:left;font-size:12px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;">Created</th>
                    <th style="padding:12px 16px;text-align:center;font-size:12px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($tickets as $ticket)
                <tr style="border-bottom:1px solid #f1f5f9;transition:background 0.15s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                    <td style="padding:14px 16px;">
                        <span style="font-family:monospace;font-weight:700;color:#3b82f6;font-size:13px;">{{ $ticket->ticket_number }}</span>
                    </td>
                    <td style="padding:14px 16px;max-width:280px;">
                        <span style="font-weight:600;color:#1e293b;font-size:14px;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $ticket->title }}</span>
                        <span style="font-size:12px;color:#94a3b8;">{{ $ticket->replies->count() }} {{ Str::plural('reply', $ticket->replies->count()) }}</span>
                    </td>
                    <td style="padding:14px 16px;">
                        <span style="background:#f1f5f9;color:#475569;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:500;">{{ $ticket->category_label }}</span>
                    </td>
                    <td style="padding:14px 16px;">
                        <span style="background:{{ $ticket->priority_bg }};color:{{ $ticket->priority_color }};padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;">{{ ucfirst($ticket->priority) }}</span>
                    </td>
                    <td style="padding:14px 16px;">
                        <span style="background:{{ $ticket->status_bg }};color:{{ $ticket->status_color }};padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;">{{ $ticket->status_label }}</span>
                    </td>
                    <td style="padding:14px 16px;color:#64748b;font-size:13px;white-space:nowrap;">{{ $ticket->created_at->format('d M Y') }}</td>
                    <td style="padding:14px 16px;text-align:center;">
                        <a href="{{ route('support.show', $ticket) }}" style="background:#eff6ff;color:#3b82f6;padding:6px 14px;border-radius:6px;text-decoration:none;font-size:13px;font-weight:500;">
                            <i class="fas fa-eye" style="margin-right:4px;"></i> View
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @if($tickets->hasPages())
        <div style="padding:16px;border-top:1px solid #f1f5f9;">
            {{ $tickets->links() }}
        </div>
        @endif
    </div>
    @endif
</div>
@endsection

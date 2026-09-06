@extends('layouts.app')

@section('title', 'Support Tickets - Admin')
@section('page-title', 'Support Tickets')
@section('page-subtitle', 'Manage all user support requests')

@section('content')
<div style="padding:24px;">

    @if(session('success'))
    <div style="background:#d1fae5;border:1px solid #6ee7b7;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:20px;display:flex;align-items:center;gap:10px;">
        <i class="fas fa-check-circle"></i> {{ session('success') }}
    </div>
    @endif

    {{-- Stats Row --}}
    <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:16px;margin-bottom:24px;">
        @php
        $statItems = [
            ['label'=>'Total','value'=>$stats['total'],'icon'=>'fa-ticket-alt','bg'=>'#eff6ff','color'=>'#3b82f6','iconBg'=>'#dbeafe'],
            ['label'=>'Open','value'=>$stats['open'],'icon'=>'fa-folder-open','bg'=>'#fef9c3','color'=>'#ca8a04','iconBg'=>'#fef08a'],
            ['label'=>'In Progress','value'=>$stats['in_progress'],'icon'=>'fa-spinner','bg'=>'#fff7ed','color'=>'#ea580c','iconBg'=>'#fed7aa'],
            ['label'=>'Resolved','value'=>$stats['resolved'],'icon'=>'fa-check-circle','bg'=>'#f0fdf4','color'=>'#16a34a','iconBg'=>'#bbf7d0'],
            ['label'=>'Urgent','value'=>$stats['urgent'],'icon'=>'fa-exclamation-triangle','bg'=>'#fff1f2','color'=>'#e11d48','iconBg'=>'#fecdd3'],
        ];
        @endphp
        @foreach($statItems as $s)
        <div style="background:{{ $s['bg'] }};border-radius:12px;padding:18px;display:flex;align-items:center;gap:14px;">
            <div style="width:44px;height:44px;background:{{ $s['iconBg'] }};border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fas {{ $s['icon'] }}" style="font-size:18px;color:{{ $s['color'] }};"></i>
            </div>
            <div>
                <div style="font-size:24px;font-weight:800;color:#1e293b;">{{ $s['value'] }}</div>
                <div style="font-size:12px;font-weight:500;color:#64748b;">{{ $s['label'] }}</div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Filter Bar --}}
    <form method="GET" action="{{ route('admin.support.index') }}" style="background:#fff;border-radius:12px;border:1px solid #e2e8f0;padding:16px 20px;margin-bottom:20px;display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search ticket, user..."
            style="flex:1;min-width:180px;padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;outline:none;"
            onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#e2e8f0'">
        <select name="status" style="padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;outline:none;background:#fff;">
            <option value="">All Status</option>
            <option value="open"        {{ request('status')==='open' ? 'selected':'' }}>Open</option>
            <option value="in_progress" {{ request('status')==='in_progress' ? 'selected':'' }}>In Progress</option>
            <option value="resolved"    {{ request('status')==='resolved' ? 'selected':'' }}>Resolved</option>
            <option value="closed"      {{ request('status')==='closed' ? 'selected':'' }}>Closed</option>
        </select>
        <select name="priority" style="padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;outline:none;background:#fff;">
            <option value="">All Priority</option>
            <option value="urgent" {{ request('priority')==='urgent' ? 'selected':'' }}>Urgent</option>
            <option value="high"   {{ request('priority')==='high' ? 'selected':'' }}>High</option>
            <option value="medium" {{ request('priority')==='medium' ? 'selected':'' }}>Medium</option>
            <option value="low"    {{ request('priority')==='low' ? 'selected':'' }}>Low</option>
        </select>
        <select name="category" style="padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;outline:none;background:#fff;">
            <option value="">All Category</option>
            <option value="bug"             {{ request('category')==='bug' ? 'selected':'' }}>Bug</option>
            <option value="feature_request" {{ request('category')==='feature_request' ? 'selected':'' }}>Feature Request</option>
            <option value="question"        {{ request('category')==='question' ? 'selected':'' }}>Question</option>
            <option value="account"         {{ request('category')==='account' ? 'selected':'' }}>Account</option>
            <option value="other"           {{ request('category')==='other' ? 'selected':'' }}>Other</option>
        </select>
        <button type="submit" style="background:#3b82f6;color:#fff;padding:8px 18px;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">
            <i class="fas fa-search" style="margin-right:4px;"></i> Filter
        </button>
        @if(request()->hasAny(['search','status','priority','category']))
        <a href="{{ route('admin.support.index') }}" style="color:#64748b;font-size:13px;text-decoration:none;padding:8px 12px;">Clear</a>
        @endif
    </form>

    {{-- Tickets Table --}}
    @if($tickets->isEmpty())
    <div style="text-align:center;padding:64px 24px;background:#fff;border-radius:12px;border:1px solid #e2e8f0;">
        <i class="fas fa-inbox" style="font-size:48px;color:#cbd5e1;margin-bottom:16px;display:block;"></i>
        <h3 style="margin:0 0 8px;color:#475569;">No tickets found</h3>
        <p style="color:#94a3b8;margin:0;">No support tickets match your current filters.</p>
    </div>
    @else
    <div style="background:#fff;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden;">
        <table style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0;">
                    <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;">Ticket #</th>
                    <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;">User</th>
                    <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;">Title</th>
                    <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;">Category</th>
                    <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;">Priority</th>
                    <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;">Status</th>
                    <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;">Created</th>
                    <th style="padding:12px 16px;text-align:center;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($tickets as $ticket)
                <tr style="border-bottom:1px solid #f1f5f9;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                    <td style="padding:14px 16px;">
                        <span style="font-family:monospace;font-weight:700;color:#3b82f6;font-size:13px;">{{ $ticket->ticket_number }}</span>
                    </td>
                    <td style="padding:14px 16px;">
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div style="width:30px;height:30px;border-radius:50%;background:#dbeafe;color:#1d4ed8;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0;">
                                {{ strtoupper(substr($ticket->user->name, 0, 1)) }}
                            </div>
                            <div>
                                <div style="font-size:13px;font-weight:600;color:#1e293b;">{{ $ticket->user->name }}</div>
                                <div style="font-size:11px;color:#94a3b8;">{{ $ticket->user->role->name ?? 'N/A' }}</div>
                            </div>
                        </div>
                    </td>
                    <td style="padding:14px 16px;max-width:220px;">
                        <span style="font-size:13px;color:#374151;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $ticket->title }}</span>
                    </td>
                    <td style="padding:14px 16px;">
                        <span style="background:#f1f5f9;color:#475569;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:500;">{{ $ticket->category_label }}</span>
                    </td>
                    <td style="padding:14px 16px;">
                        <span style="background:{{ $ticket->priority_bg }};color:{{ $ticket->priority_color }};padding:3px 10px;border-radius:12px;font-size:11px;font-weight:700;">{{ ucfirst($ticket->priority) }}</span>
                    </td>
                    <td style="padding:14px 16px;">
                        <span style="background:{{ $ticket->status_bg }};color:{{ $ticket->status_color }};padding:3px 10px;border-radius:12px;font-size:11px;font-weight:700;">{{ $ticket->status_label }}</span>
                    </td>
                    <td style="padding:14px 16px;color:#64748b;font-size:12px;white-space:nowrap;">{{ $ticket->created_at->format('d M Y') }}</td>
                    <td style="padding:14px 16px;text-align:center;">
                        <a href="{{ route('admin.support.show', $ticket) }}" style="background:#eff6ff;color:#3b82f6;padding:6px 12px;border-radius:6px;text-decoration:none;font-size:12px;font-weight:500;">
                            <i class="fas fa-eye" style="margin-right:4px;"></i> Open
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

@extends('layouts.app')

@section('title', $ticket->ticket_number . ' - Support')
@section('page-title', 'Support')
@section('page-subtitle', 'Ticket ' . $ticket->ticket_number)

@section('content')
<div style="padding:24px;max-width:900px;">

    <div style="margin-bottom:20px;">
        <a href="{{ route('support.index') }}" style="color:#64748b;text-decoration:none;font-size:14px;display:inline-flex;align-items:center;gap:6px;">
            <i class="fas fa-arrow-left"></i> Back to My Tickets
        </a>
    </div>

    @if(session('success'))
    <div style="background:#d1fae5;border:1px solid #6ee7b7;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:20px;display:flex;align-items:center;gap:10px;">
        <i class="fas fa-check-circle"></i> {{ session('success') }}
    </div>
    @endif

    {{-- Ticket Header --}}
    <div style="background:#fff;border-radius:12px;border:1px solid #e2e8f0;padding:24px;margin-bottom:20px;">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;">
            <div style="flex:1;min-width:200px;">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;flex-wrap:wrap;">
                    <span style="font-family:monospace;font-size:14px;font-weight:700;color:#3b82f6;background:#dbeafe;padding:4px 10px;border-radius:6px;">{{ $ticket->ticket_number }}</span>
                    <span style="background:{{ $ticket->status_bg }};color:{{ $ticket->status_color }};padding:4px 12px;border-radius:12px;font-size:12px;font-weight:700;">{{ $ticket->status_label }}</span>
                    <span style="background:{{ $ticket->priority_bg }};color:{{ $ticket->priority_color }};padding:4px 12px;border-radius:12px;font-size:12px;font-weight:700;">{{ ucfirst($ticket->priority) }} Priority</span>
                    <span style="background:#f1f5f9;color:#475569;padding:4px 12px;border-radius:12px;font-size:12px;font-weight:500;">{{ $ticket->category_label }}</span>
                </div>
                <h2 style="margin:0 0 8px;font-size:20px;font-weight:700;color:#1e293b;">{{ $ticket->title }}</h2>
                <p style="margin:0;font-size:12px;color:#94a3b8;">Opened {{ $ticket->created_at->diffForHumans() }} &bull; {{ $ticket->created_at->format('d M Y, h:i A') }}</p>
            </div>
        </div>
    </div>

    {{-- Description --}}
    <div style="background:#fff;border-radius:12px;border:1px solid #e2e8f0;padding:24px;margin-bottom:20px;">
        <h3 style="margin:0 0 14px;font-size:15px;font-weight:700;color:#374151;display:flex;align-items:center;gap:8px;">
            <i class="fas fa-align-left" style="color:#3b82f6;"></i> Description
        </h3>
        <p style="margin:0;color:#475569;font-size:14px;line-height:1.7;white-space:pre-wrap;">{{ $ticket->description }}</p>
    </div>

    {{-- Attachments --}}
    @if($ticket->attachments->count())
    <div style="background:#fff;border-radius:12px;border:1px solid #e2e8f0;padding:24px;margin-bottom:20px;">
        <h3 style="margin:0 0 16px;font-size:15px;font-weight:700;color:#374151;display:flex;align-items:center;gap:8px;">
            <i class="fas fa-paperclip" style="color:#3b82f6;"></i> Attachments ({{ $ticket->attachments->count() }})
        </h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;">
            @foreach($ticket->attachments as $att)
            <div style="border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;background:#f8fafc;">
                @if($att->isImage())
                    <img src="{{ $att->url }}" alt="{{ $att->file_name }}" style="width:100%;height:150px;object-fit:cover;display:block;">
                    <div style="padding:8px 12px;">
                        <p style="margin:0;font-size:12px;color:#475569;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $att->file_name }}</p>
                        <a href="{{ $att->url }}" target="_blank" style="font-size:12px;color:#3b82f6;">View full</a>
                    </div>
                @elseif($att->isVoice())
                    <div style="padding:16px;text-align:center;">
                        <i class="fas fa-microphone" style="font-size:28px;color:#ef4444;margin-bottom:8px;display:block;"></i>
                        <audio controls style="width:100%;"><source src="{{ $att->url }}"></audio>
                        <p style="margin:8px 0 0;font-size:11px;color:#94a3b8;">Voice recording</p>
                    </div>
                @elseif($att->isVideo())
                    <div style="padding:16px;">
                        <video controls style="width:100%;border-radius:6px;background:#000;"><source src="{{ $att->url }}"></video>
                        <p style="margin:8px 0 0;font-size:11px;color:#94a3b8;text-align:center;">Video recording</p>
                    </div>
                @else
                    <div style="padding:16px;text-align:center;">
                        <i class="fas fa-file-alt" style="font-size:32px;color:#6366f1;margin-bottom:8px;display:block;"></i>
                        <p style="margin:0 0 8px;font-size:12px;color:#475569;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $att->file_name }}</p>
                        <a href="{{ $att->url }}" download style="background:#eff6ff;color:#3b82f6;padding:5px 12px;border-radius:6px;font-size:12px;text-decoration:none;">
                            <i class="fas fa-download" style="margin-right:4px;"></i> Download
                        </a>
                    </div>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Replies Thread --}}
    <div style="background:#fff;border-radius:12px;border:1px solid #e2e8f0;padding:24px;margin-bottom:20px;">
        <h3 style="margin:0 0 20px;font-size:15px;font-weight:700;color:#374151;display:flex;align-items:center;gap:8px;">
            <i class="fas fa-comments" style="color:#3b82f6;"></i> Conversation
            <span style="font-size:13px;font-weight:400;color:#94a3b8;">({{ $ticket->replies->count() }} replies)</span>
        </h3>

        @if($ticket->replies->isEmpty())
        <p style="color:#94a3b8;font-size:14px;text-align:center;padding:20px 0;">No replies yet. Our support team will respond shortly.</p>
        @else
        <div style="display:flex;flex-direction:column;gap:16px;">
            @foreach($ticket->replies as $reply)
            @php $isAdmin = $reply->is_admin_reply; @endphp
            <div style="display:flex;gap:12px;{{ $isAdmin ? 'flex-direction:row;' : 'flex-direction:row-reverse;' }}">
                <div style="width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;flex-shrink:0;background:{{ $isAdmin ? '#dbeafe' : '#d1fae5' }};color:{{ $isAdmin ? '#1d4ed8' : '#065f46' }};">
                    {{ strtoupper(substr($reply->user->name, 0, 1)) }}
                </div>
                <div style="max-width:80%;flex:1;">
                    <div style="background:{{ $isAdmin ? '#eff6ff' : '#f0fdf4' }};border:1px solid {{ $isAdmin ? '#bfdbfe' : '#bbf7d0' }};border-radius:10px;padding:14px 16px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                            <span style="font-size:13px;font-weight:700;color:{{ $isAdmin ? '#1d4ed8' : '#065f46' }};">
                                {{ $reply->user->name }} {{ $isAdmin ? '(Support Team)' : '' }}
                            </span>
                            <span style="font-size:11px;color:#94a3b8;">{{ $reply->created_at->diffForHumans() }}</span>
                        </div>
                        <p style="margin:0;font-size:14px;color:#374151;line-height:1.6;white-space:pre-wrap;">{{ $reply->message }}</p>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    {{-- Add Reply (only if not closed) --}}
    @if($ticket->status !== 'closed')
    <div style="background:#fff;border-radius:12px;border:1px solid #e2e8f0;padding:24px;">
        <h3 style="margin:0 0 16px;font-size:15px;font-weight:700;color:#374151;display:flex;align-items:center;gap:8px;">
            <i class="fas fa-reply" style="color:#3b82f6;"></i> Add Reply
        </h3>
        <form method="POST" action="{{ route('support.reply', $ticket) }}">
            @csrf
            <textarea name="message" rows="4" required
                style="width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;color:#1e293b;outline:none;resize:vertical;box-sizing:border-box;margin-bottom:12px;"
                placeholder="Add more details or follow-up information..."
                onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#e2e8f0'">{{ old('message') }}</textarea>
            <button type="submit"
                style="background:linear-gradient(135deg,#3b82f6,#1d4ed8);color:#fff;padding:10px 22px;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;">
                <i class="fas fa-paper-plane" style="margin-right:6px;"></i> Send Reply
            </button>
        </form>
    </div>
    @else
    <div style="background:#f8fafc;border-radius:12px;border:1px solid #e2e8f0;padding:20px;text-align:center;color:#94a3b8;font-size:14px;">
        <i class="fas fa-lock" style="margin-right:6px;"></i> This ticket is closed. Contact support to reopen.
    </div>
    @endif

</div>
@endsection

@extends('layouts.app')

@section('title', $ticket->ticket_number . ' - Admin Support')
@section('page-title', 'Support Tickets')
@section('page-subtitle', 'Ticket ' . $ticket->ticket_number)

@section('content')
<div style="padding:24px;max-width:960px;">

    <div style="margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
        <a href="{{ route('admin.support.index') }}" style="color:#64748b;text-decoration:none;font-size:14px;display:inline-flex;align-items:center;gap:6px;">
            <i class="fas fa-arrow-left"></i> Back to All Tickets
        </a>
        <form method="POST" action="{{ route('admin.support.destroy', $ticket) }}"
            onsubmit="return confirm('Delete this ticket permanently?');" style="display:inline;">
            @csrf @method('DELETE')
            <button type="submit" style="background:#fee2e2;color:#ef4444;border:1px solid #fca5a5;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">
                <i class="fas fa-trash" style="margin-right:4px;"></i> Delete Ticket
            </button>
        </form>
    </div>

    @if(session('success'))
    <div style="background:#d1fae5;border:1px solid #6ee7b7;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:20px;display:flex;align-items:center;gap:10px;">
        <i class="fas fa-check-circle"></i> {{ session('success') }}
    </div>
    @endif

    <div style="display:grid;grid-template-columns:1fr 300px;gap:20px;align-items:start;">

        {{-- Left Column --}}
        <div>
            {{-- Ticket Header --}}
            <div style="background:#fff;border-radius:12px;border:1px solid #e2e8f0;padding:24px;margin-bottom:20px;">
                <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px;">
                    <span style="font-family:monospace;font-size:14px;font-weight:700;color:#3b82f6;background:#dbeafe;padding:4px 10px;border-radius:6px;">{{ $ticket->ticket_number }}</span>
                    <span style="background:{{ $ticket->status_bg }};color:{{ $ticket->status_color }};padding:4px 12px;border-radius:12px;font-size:12px;font-weight:700;">{{ $ticket->status_label }}</span>
                    <span style="background:{{ $ticket->priority_bg }};color:{{ $ticket->priority_color }};padding:4px 12px;border-radius:12px;font-size:12px;font-weight:700;">{{ ucfirst($ticket->priority) }} Priority</span>
                    <span style="background:#f1f5f9;color:#475569;padding:4px 12px;border-radius:12px;font-size:12px;font-weight:500;">{{ $ticket->category_label }}</span>
                </div>
                <h2 style="margin:0 0 8px;font-size:20px;font-weight:700;color:#1e293b;">{{ $ticket->title }}</h2>
                <p style="margin:0;font-size:12px;color:#94a3b8;">Opened {{ $ticket->created_at->diffForHumans() }} &bull; {{ $ticket->created_at->format('d M Y, h:i A') }}</p>
            </div>

            {{-- Description --}}
            <div style="background:#fff;border-radius:12px;border:1px solid #e2e8f0;padding:24px;margin-bottom:20px;">
                <h3 style="margin:0 0 14px;font-size:15px;font-weight:700;color:#374151;display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-align-left" style="color:#3b82f6;"></i> Description
                </h3>
                <p style="margin:0;color:#475569;font-size:14px;line-height:1.7;white-space:pre-wrap;">{{ $ticket->description }}</p>

                @if($ticket->admin_note)
                <div style="margin-top:16px;background:#fef9c3;border:1px solid #fde047;border-radius:8px;padding:12px 16px;">
                    <p style="margin:0 0 4px;font-size:12px;font-weight:700;color:#a16207;text-transform:uppercase;letter-spacing:0.5px;">Internal Note</p>
                    <p style="margin:0;font-size:13px;color:#713f12;">{{ $ticket->admin_note }}</p>
                </div>
                @endif
            </div>

            {{-- Attachments --}}
            @if($ticket->attachments->count())
            <div style="background:#fff;border-radius:12px;border:1px solid #e2e8f0;padding:24px;margin-bottom:20px;">
                <h3 style="margin:0 0 16px;font-size:15px;font-weight:700;color:#374151;display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-paperclip" style="color:#3b82f6;"></i> Attachments ({{ $ticket->attachments->count() }})
                </h3>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px;">
                    @foreach($ticket->attachments as $att)
                    <div style="border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;background:#f8fafc;">
                        @if($att->isImage())
                            <img src="{{ $att->url }}" alt="{{ $att->file_name }}" style="width:100%;height:130px;object-fit:cover;display:block;">
                            <div style="padding:8px 12px;">
                                <a href="{{ $att->url }}" target="_blank" style="font-size:12px;color:#3b82f6;">View full</a>
                            </div>
                        @elseif($att->isVoice())
                            <div style="padding:14px;text-align:center;">
                                <i class="fas fa-microphone" style="font-size:24px;color:#ef4444;margin-bottom:8px;display:block;"></i>
                                <audio controls style="width:100%;"><source src="{{ $att->url }}"></audio>
                            </div>
                        @elseif($att->isVideo())
                            <div style="padding:12px;">
                                <video controls style="width:100%;border-radius:6px;background:#000;"><source src="{{ $att->url }}"></video>
                            </div>
                        @else
                            <div style="padding:16px;text-align:center;">
                                <i class="fas fa-file-alt" style="font-size:28px;color:#6366f1;margin-bottom:8px;display:block;"></i>
                                <p style="margin:0 0 8px;font-size:11px;color:#475569;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $att->file_name }}</p>
                                <a href="{{ $att->url }}" download style="background:#eff6ff;color:#3b82f6;padding:4px 10px;border-radius:6px;font-size:11px;text-decoration:none;">Download</a>
                            </div>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Conversation --}}
            <div style="background:#fff;border-radius:12px;border:1px solid #e2e8f0;padding:24px;margin-bottom:20px;">
                <h3 style="margin:0 0 20px;font-size:15px;font-weight:700;color:#374151;display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-comments" style="color:#3b82f6;"></i> Conversation
                    <span style="font-size:13px;font-weight:400;color:#94a3b8;">({{ $ticket->replies->count() }} replies)</span>
                </h3>

                @if($ticket->replies->isEmpty())
                <p style="color:#94a3b8;font-size:14px;text-align:center;padding:16px 0;">No replies yet. Use the form below to respond.</p>
                @else
                <div style="display:flex;flex-direction:column;gap:16px;margin-bottom:20px;">
                    @foreach($ticket->replies as $reply)
                    @php $isAdmin = $reply->is_admin_reply; @endphp
                    <div style="display:flex;gap:12px;{{ $isAdmin ? '' : 'flex-direction:row-reverse;' }}">
                        <div style="width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;flex-shrink:0;background:{{ $isAdmin ? '#dbeafe' : '#d1fae5' }};color:{{ $isAdmin ? '#1d4ed8' : '#065f46' }};">
                            {{ strtoupper(substr($reply->user->name, 0, 1)) }}
                        </div>
                        <div style="max-width:80%;flex:1;">
                            <div style="background:{{ $isAdmin ? '#eff6ff' : '#f0fdf4' }};border:1px solid {{ $isAdmin ? '#bfdbfe' : '#bbf7d0' }};border-radius:10px;padding:14px 16px;">
                                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                                    <span style="font-size:13px;font-weight:700;color:{{ $isAdmin ? '#1d4ed8' : '#065f46' }};">
                                        {{ $reply->user->name }} {{ $isAdmin ? '(Support Team)' : '(User)' }}
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

                {{-- Admin Reply Form --}}
                <div style="border-top:1px solid #f1f5f9;padding-top:20px;">
                    <h4 style="margin:0 0 14px;font-size:14px;font-weight:700;color:#374151;">Reply to User</h4>
                    <form method="POST" action="{{ route('admin.support.reply', $ticket) }}">
                        @csrf
                        <textarea name="message" rows="4" required
                            style="width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;color:#1e293b;outline:none;resize:vertical;box-sizing:border-box;margin-bottom:12px;"
                            placeholder="Write your response to the user..."
                            onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#e2e8f0'"></textarea>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                            <div>
                                <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:6px;">Change Status</label>
                                <select name="status" style="width:100%;padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;outline:none;background:#fff;">
                                    <option value="">Keep current ({{ $ticket->status_label }})</option>
                                    <option value="open">Open</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="resolved">Resolved</option>
                                    <option value="closed">Closed</option>
                                </select>
                            </div>
                            <div>
                                <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:6px;">Internal Note <span style="font-weight:400;color:#94a3b8;">(not shown to user)</span></label>
                                <input type="text" name="admin_note" value="{{ $ticket->admin_note }}" placeholder="Add internal note..."
                                    style="width:100%;padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;outline:none;box-sizing:border-box;">
                            </div>
                        </div>

                        <button type="submit"
                            style="background:linear-gradient(135deg,#3b82f6,#1d4ed8);color:#fff;padding:10px 22px;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;">
                            <i class="fas fa-paper-plane" style="margin-right:6px;"></i> Send Reply
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Right Sidebar --}}
        <div>
            {{-- User Info --}}
            <div style="background:#fff;border-radius:12px;border:1px solid #e2e8f0;padding:20px;margin-bottom:16px;">
                <h4 style="margin:0 0 14px;font-size:13px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:0.5px;">Submitted By</h4>
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;">
                    <div style="width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,#3b82f6,#1d4ed8);color:#fff;display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:700;flex-shrink:0;">
                        {{ strtoupper(substr($ticket->user->name, 0, 1)) }}
                    </div>
                    <div>
                        <div style="font-size:15px;font-weight:700;color:#1e293b;">{{ $ticket->user->name }}</div>
                        <div style="font-size:12px;color:#94a3b8;">{{ $ticket->user->role->name ?? 'N/A' }}</div>
                    </div>
                </div>
                <div style="font-size:13px;color:#475569;display:flex;flex-direction:column;gap:6px;">
                    <div><i class="fas fa-envelope" style="width:14px;color:#94a3b8;margin-right:6px;"></i>{{ $ticket->user->email }}</div>
                    @if($ticket->user->phone)
                    <div><i class="fas fa-phone" style="width:14px;color:#94a3b8;margin-right:6px;"></i>{{ $ticket->user->phone }}</div>
                    @endif
                </div>
            </div>

            {{-- Quick Status Change --}}
            <div style="background:#fff;border-radius:12px;border:1px solid #e2e8f0;padding:20px;margin-bottom:16px;">
                <h4 style="margin:0 0 14px;font-size:13px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:0.5px;">Quick Actions</h4>
                <div style="display:flex;flex-direction:column;gap:8px;">
                    @foreach(['open'=>'Set Open','in_progress'=>'Mark In Progress','resolved'=>'Mark Resolved','closed'=>'Close Ticket'] as $status=>$label)
                    @if($ticket->status !== $status)
                    <form method="POST" action="{{ route('admin.support.update-status', $ticket) }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="status" value="{{ $status }}">
                        <button type="submit"
                            style="width:100%;background:#f8fafc;border:1px solid #e2e8f0;color:#374151;padding:8px 12px;border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;text-align:left;display:flex;align-items:center;gap:8px;">
                            @php
                            $icons=['open'=>'fa-folder-open text-yellow-500','in_progress'=>'fa-spinner text-orange-500','resolved'=>'fa-check-circle text-green-500','closed'=>'fa-lock text-gray-500'];
                            $colors=['open'=>'#ca8a04','in_progress'=>'#ea580c','resolved'=>'#16a34a','closed'=>'#6b7280'];
                            @endphp
                            <i class="fas {{ ['open'=>'fa-folder-open','in_progress'=>'fa-spinner','resolved'=>'fa-check-circle','closed'=>'fa-lock'][$status] }}" style="color:{{ $colors[$status] }};"></i>
                            {{ $label }}
                        </button>
                    </form>
                    @endif
                    @endforeach
                </div>
            </div>

            {{-- Ticket Meta --}}
            <div style="background:#fff;border-radius:12px;border:1px solid #e2e8f0;padding:20px;">
                <h4 style="margin:0 0 14px;font-size:13px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:0.5px;">Details</h4>
                <div style="display:flex;flex-direction:column;gap:10px;font-size:13px;color:#475569;">
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:#94a3b8;">Created</span>
                        <span>{{ $ticket->created_at->format('d M Y') }}</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:#94a3b8;">Replies</span>
                        <span>{{ $ticket->replies->count() }}</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:#94a3b8;">Attachments</span>
                        <span>{{ $ticket->attachments->count() }}</span>
                    </div>
                    @if($ticket->resolved_at)
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:#94a3b8;">Resolved</span>
                        <span style="color:#16a34a;">{{ $ticket->resolved_at->format('d M Y') }}</span>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

</div>
@endsection

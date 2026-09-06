@extends('layouts.app')

@section('title', 'Lead Download Requests - ' . brand_name())
@section('page-title', 'Lead Download Requests')
@section('page-subtitle', 'Admin approval queue for ASM lead export requests')

@push('styles')
<style>
    .queue-toolbar { display:flex; justify-content:space-between; gap:16px; align-items:center; margin-bottom:22px; flex-wrap:wrap; }
    .queue-filter { display:inline-flex; gap:10px; flex-wrap:wrap; }
    .queue-filter a { text-decoration:none; padding:10px 14px; border-radius:999px; border:1px solid #d7e2db; color:#355045; background:#fff; font-size:13px; font-weight:600; }
    .queue-filter a.active { background:#0f5132; color:#fff; border-color:#0f5132; }
    .queue-grid { display:grid; gap:20px; }
    .queue-card { background:#fff; border:1px solid #e5ece7; border-radius:18px; padding:22px; box-shadow:0 10px 24px rgba(0,0,0,.05); }
    .queue-top { display:flex; justify-content:space-between; gap:16px; align-items:flex-start; margin-bottom:18px; }
    .queue-title { font-size:20px; font-weight:700; color:#0f172a; margin-bottom:6px; }
    .queue-subtitle { font-size:13px; color:#64748b; line-height:1.6; }
    .queue-badge { display:inline-flex; align-items:center; gap:8px; padding:8px 12px; border-radius:999px; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.08em; }
    .queue-badge.pending, .queue-badge.approved, .queue-badge.processing { background:#fff7ed; color:#b45309; }
    .queue-badge.completed { background:#ecfdf3; color:#15803d; }
    .queue-badge.rejected, .queue-badge.expired, .queue-badge.failed { background:#fff1f2; color:#be123c; }
    .queue-meta { display:grid; grid-template-columns:repeat(6,minmax(0,1fr)); gap:14px; margin-bottom:18px; }
    .queue-meta-box { background:#f8faf9; border:1px solid #e4ece6; border-radius:14px; padding:14px; }
    .queue-meta-box strong { display:block; font-size:11px; text-transform:uppercase; letter-spacing:.12em; color:#6b7f75; margin-bottom:8px; }
    .queue-meta-box span { font-size:14px; color:#173128; line-height:1.6; }
    .queue-section { margin-top:18px; }
    .queue-section-title { font-size:14px; font-weight:700; color:#173128; margin-bottom:10px; }
    .queue-chips { display:flex; flex-wrap:wrap; gap:8px; }
    .queue-chip { display:inline-flex; align-items:center; padding:7px 10px; border-radius:999px; background:#f2f7f4; border:1px solid #dbe7e0; color:#385146; font-size:12px; font-weight:600; }
    .queue-actions { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; margin-top:20px; }
    .queue-form { border:1px solid #e4ece6; border-radius:16px; padding:16px; background:#fcfdfc; }
    .queue-form label { display:block; font-size:13px; font-weight:700; color:#173128; margin-bottom:8px; }
    .queue-form textarea { width:100%; min-height:96px; border:1px solid #d7e4dc; border-radius:12px; padding:12px 14px; font-size:14px; margin-bottom:12px; }
    .queue-btn { display:inline-flex; align-items:center; justify-content:center; gap:10px; width:100%; padding:12px 16px; border-radius:12px; border:none; cursor:pointer; font-size:14px; font-weight:700; }
    .queue-btn.approve { background:#0f5132; color:#fff; }
    .queue-btn.reject { background:#fff1f2; color:#be123c; border:1px solid #fecdd3; }
    .queue-note { margin-top:12px; padding:14px; border-radius:14px; background:#f8faf9; border:1px solid #e4ece6; color:#475d53; font-size:13px; white-space:pre-line; line-height:1.6; }
    .queue-alert { padding:14px 16px; border-radius:14px; margin-bottom:18px; font-size:14px; }
    .queue-alert.success { background:#ecfdf3; color:#15803d; border:1px solid #bbf7d0; }
    .queue-alert.error { background:#fff1f2; color:#be123c; border:1px solid #fecdd3; }
    @media (max-width:991px) { .queue-meta, .queue-actions { grid-template-columns:1fr; } .queue-top { flex-direction:column; } }
</style>
@endpush

@section('content')
<div class="container">
    @if(session('success')) <div class="queue-alert success">{{ session('success') }}</div> @endif
    @if(session('error')) <div class="queue-alert error">{{ session('error') }}</div> @endif
    @if($errors->any()) <div class="queue-alert error">{{ $errors->first() }}</div> @endif

    <div class="queue-toolbar">
        <div>
            <div style="font-size:24px;font-weight:700;color:#0f172a;">Lead export approval queue</div>
            <div style="font-size:14px;color:#64748b;margin-top:6px;">Review ASM requests, approve exports, and keep the audit trail intact.</div>
        </div>
        <div class="queue-filter">
            <a href="{{ route('admin.lead-download-requests.index') }}" class="{{ !$currentStatus ? 'active' : '' }}">All</a>
            @foreach($statuses as $status)
                <a href="{{ route('admin.lead-download-requests.index', ['status' => $status]) }}" class="{{ $currentStatus === $status ? 'active' : '' }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</a>
            @endforeach
        </div>
    </div>

    <div class="queue-grid">
        @forelse($requests as $requestItem)
            <article class="queue-card">
                <div class="queue-top">
                    <div>
                        <div class="queue-title">{{ $requestItem->requester->name }} requested {{ strtoupper($requestItem->format) }} export</div>
                        <div class="queue-subtitle">{{ optional($requestItem->requester->role)->name ?? 'ASM User' }} | {{ $requestItem->requester->email }}<br>Requested on {{ $requestItem->created_at->format('d M Y, h:i A') }}</div>
                    </div>
                    <span class="queue-badge {{ $requestItem->status }}">{{ $requestItem->statusLabel() }}</span>
                </div>

                <div class="queue-meta">
                    <div class="queue-meta-box"><strong>Scope</strong><span>{{ ucfirst(str_replace('_', ' ', $requestItem->filters['assigned_scope'] ?? 'my team')) }}</span></div>
                    <div class="queue-meta-box"><strong>Date Range</strong><span>{{ ucfirst(str_replace('_', ' ', $requestItem->filters['date_range'] ?? 'all time')) }}</span></div>
                    <div class="queue-meta-box"><strong>Selected Fields</strong><span>{{ count($requestItem->fields ?? []) }}</span></div>
                    <div class="queue-meta-box"><strong>Exported Records</strong><span>{{ $requestItem->exported_records_count ?? 'Pending' }}</span></div>
                    <div class="queue-meta-box"><strong>Expires</strong><span>{{ $requestItem->expires_at ? $requestItem->expires_at->format('d M Y, h:i A') : 'After approval' }}</span></div>
                    <div class="queue-meta-box"><strong>Downloads</strong><span>{{ (int) $requestItem->download_click_count }} / {{ \App\Models\LeadDownloadRequest::MAX_DOWNLOAD_CLICKS }}{{ $requestItem->last_downloaded_at ? ' | Last: '.$requestItem->last_downloaded_at->format('d M, h:i A') : '' }}</span></div>
                </div>

                <div class="queue-section">
                    <div class="queue-section-title">Fields</div>
                    <div class="queue-chips">
                        @foreach($requestItem->fields ?? [] as $field)
                            <span class="queue-chip">{{ ucfirst(str_replace('_', ' ', $field)) }}</span>
                        @endforeach
                    </div>
                </div>

                <div class="queue-section">
                    <div class="queue-section-title">Applied Filters</div>
                    <div class="queue-chips">
                        @if(!empty($requestItem->filters['status'])) @foreach($requestItem->filters['status'] as $status)<span class="queue-chip">Status: {{ ucfirst(str_replace('_', ' ', $status)) }}</span>@endforeach @endif
                        @if(!empty($requestItem->filters['lead_type'])) @foreach($requestItem->filters['lead_type'] as $leadType)<span class="queue-chip">Type: {{ ucfirst(str_replace('_', ' ', $leadType)) }}</span>@endforeach @endif
                        @if(!empty($requestItem->filters['source'])) @foreach($requestItem->filters['source'] as $source)<span class="queue-chip">Source: {{ ucfirst(str_replace('_', ' ', $source)) }}</span>@endforeach @endif
                        @if(!empty($requestItem->filters['interested_projects'])) <span class="queue-chip">Projects: {{ count($requestItem->filters['interested_projects']) }} selected</span> @endif
                        @if(!empty($requestItem->filters['search'])) <span class="queue-chip">Search: {{ $requestItem->filters['search'] }}</span> @endif
                        @if(empty($requestItem->filters['status']) && empty($requestItem->filters['lead_type']) && empty($requestItem->filters['source']) && empty($requestItem->filters['interested_projects']) && empty($requestItem->filters['search']) && (($requestItem->filters['date_range'] ?? 'all_time') === 'all_time')) <span class="queue-chip">No optional filters</span> @endif
                    </div>
                </div>

                @if(in_array($requestItem->status, ['pending', 'approved', 'processing'], true))
                    <div class="queue-actions">
                        <form method="POST" action="{{ route('admin.lead-download-requests.approve', $requestItem) }}" class="queue-form">
                            @csrf
                            <label for="approve_note_{{ $requestItem->id }}">Admin note</label>
                            <textarea id="approve_note_{{ $requestItem->id }}" name="admin_note" placeholder="Optional review note for requester or audit trail"></textarea>
                            <button type="submit" class="queue-btn approve"><i class="fas fa-check"></i> Approve and Generate</button>
                        </form>
                        <form method="POST" action="{{ route('admin.lead-download-requests.reject', $requestItem) }}" class="queue-form">
                            @csrf
                            <label for="rejection_reason_{{ $requestItem->id }}">Rejection reason</label>
                            <textarea id="rejection_reason_{{ $requestItem->id }}" name="rejection_reason" placeholder="Tell the requester why this export cannot be released yet" required></textarea>
                            <label for="reject_note_{{ $requestItem->id }}">Admin note</label>
                            <textarea id="reject_note_{{ $requestItem->id }}" name="admin_note" placeholder="Optional internal context"></textarea>
                            <button type="submit" class="queue-btn reject"><i class="fas fa-ban"></i> Reject Request</button>
                        </form>
                    </div>
                @endif

                @if($requestItem->rejection_reason)<div class="queue-note"><strong>Rejection Reason:</strong> {{ $requestItem->rejection_reason }}</div>@endif
                @if($requestItem->admin_note)<div class="queue-note"><strong>Admin Note:</strong> {{ $requestItem->admin_note }}</div>@endif
            </article>
        @empty
            <div class="queue-card"><div class="queue-title">No requests found</div><div class="queue-subtitle">New ASM lead download requests will appear here for approval.</div></div>
        @endforelse
    </div>

    <div style="margin-top:22px;">{{ $requests->links() }}</div>
</div>
@endsection

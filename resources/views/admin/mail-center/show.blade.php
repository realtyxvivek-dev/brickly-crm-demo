@extends('layouts.app')

@section('title', 'Mail Log - ' . brand_name())

@section('content')
<style>
    .mail-show { padding: 22px; background:#f5f7f4; min-height:calc(100vh - 80px); }
    .mail-panel { background:#fff; border:1px solid #dbe6df; border-radius:18px; box-shadow:0 12px 30px rgba(15,23,42,.06); overflow:hidden; }
    .mail-head { padding:24px; display:flex; justify-content:space-between; gap:16px; border-bottom:1px solid #e5eee8; }
    .mail-eyebrow { font-size:12px; letter-spacing:2px; color:#5b6f66; text-transform:uppercase; font-weight:900; }
    .mail-title { margin:6px 0 0; font-size:28px; color:#052e1b; font-weight:900; }
    .mail-btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; border:1px solid #cfe0d6; border-radius:12px; padding:11px 15px; font-weight:800; text-decoration:none; color:#052e1b; background:#fff; }
    .mail-btn.primary { background:#0b6b3f; border-color:#0b6b3f; color:#fff; }
    .mail-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; padding:20px; }
    .mail-box { border:1px solid #dbe6df; border-radius:15px; padding:16px; background:#fbfdfb; }
    .mail-label { font-size:12px; letter-spacing:1.5px; text-transform:uppercase; color:#5b6f66; font-weight:900; margin-bottom:7px; }
    .mail-value { color:#052e1b; font-weight:800; overflow-wrap:anywhere; }
    .mail-status { display:inline-flex; border-radius:999px; padding:5px 10px; font-weight:900; font-size:12px; }
    .mail-status.sent { color:#065f46; background:#dff8eb; }
    .mail-status.failed { color:#991b1b; background:#fee2e2; }
    .mail-status.queued { color:#92400e; background:#fef3c7; }
    .mail-status.skipped { color:#334155; background:#e2e8f0; }
    .mail-pre { white-space:pre-wrap; background:#0f172a; color:#e2e8f0; border-radius:14px; padding:16px; overflow:auto; font-size:13px; }
    @media (max-width: 850px) { .mail-head { display:block; } .mail-grid { grid-template-columns:1fr; } }
</style>

<div class="mail-show">
    @if(session('success'))
        <div class="mail-panel" style="padding:14px 18px;color:#065f46;background:#ecfdf5;margin-bottom:14px;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mail-panel" style="padding:14px 18px;color:#991b1b;background:#fef2f2;margin-bottom:14px;">{{ session('error') }}</div>
    @endif

    <div class="mail-panel">
        <div class="mail-head">
            <div>
                <div class="mail-eyebrow">Mail Delivery Log #{{ $mailLog->id }}</div>
                <h1 class="mail-title">{{ $mailLog->subject ?: $mailLog->typeLabel() }}</h1>
            </div>
            <div style="display:flex;gap:10px;align-items:flex-start;flex-wrap:wrap;">
                <a class="mail-btn" href="{{ route('admin.mail-center.index') }}"><i class="fas fa-arrow-left"></i> Back</a>
                @if($mailLog->mail_type === \App\Models\MailDeliveryLog::TYPE_DAILY_ADMIN_REPORT)
                    <form method="POST" action="{{ route('admin.mail-center.resend', $mailLog) }}">
                        @csrf
                        <button class="mail-btn primary" type="submit"><i class="fas fa-paper-plane"></i> Resend</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="mail-grid">
            <div class="mail-box">
                <div class="mail-label">Mail Type</div>
                <div class="mail-value">{{ $mailLog->typeLabel() }}</div>
            </div>
            <div class="mail-box">
                <div class="mail-label">Status</div>
                <div class="mail-value"><span class="mail-status {{ $mailLog->status }}">{{ $mailLog->statusLabel() }}</span></div>
            </div>
            <div class="mail-box">
                <div class="mail-label">Recipient</div>
                <div class="mail-value">{{ $mailLog->recipient_email }} @if($mailLog->recipient)({{ $mailLog->recipient->name }})@endif</div>
            </div>
            <div class="mail-box">
                <div class="mail-label">Sent By</div>
                <div class="mail-value">{{ $mailLog->creator?->name ?? 'System' }}</div>
            </div>
            <div class="mail-box">
                <div class="mail-label">Created</div>
                <div class="mail-value">{{ optional($mailLog->created_at)->format('d M Y h:i A') }}</div>
            </div>
            <div class="mail-box">
                <div class="mail-label">Sent / Failed</div>
                <div class="mail-value">
                    Sent: {{ optional($mailLog->sent_at)->format('d M Y h:i A') ?: '-' }}<br>
                    Failed: {{ optional($mailLog->failed_at)->format('d M Y h:i A') ?: '-' }}
                </div>
            </div>
            @if($mailLog->resendOf)
                <div class="mail-box">
                    <div class="mail-label">Resend Of</div>
                    <div class="mail-value"><a href="{{ route('admin.mail-center.show', $mailLog->resendOf) }}">Mail #{{ $mailLog->resendOf->id }}</a></div>
                </div>
            @endif
            <div class="mail-box">
                <div class="mail-label">Related Record</div>
                <div class="mail-value">{{ $mailLog->related_type ? class_basename($mailLog->related_type) . ' #' . $mailLog->related_id : '-' }}</div>
            </div>
        </div>

        <div style="padding:0 20px 20px;">
            <div class="mail-box" style="margin-bottom:16px;">
                <div class="mail-label">Payload Summary</div>
                <div class="mail-pre">{{ json_encode($mailLog->payload_summary ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</div>
            </div>
            <div class="mail-box">
                <div class="mail-label">Error Reason</div>
                <div class="mail-pre" id="mailError">{{ $mailLog->error_message ?: 'No error recorded.' }}</div>
                @if($mailLog->error_message)
                    <button class="mail-btn" style="margin-top:12px;" type="button" onclick="navigator.clipboard.writeText(document.getElementById('mailError').innerText)">Copy Error</button>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

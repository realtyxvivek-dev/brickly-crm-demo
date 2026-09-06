<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CRM Login Security Alert</title>
</head>
<body style="font-family:Arial,sans-serif;background:#f6f7fb;color:#1f2937;padding:24px;">
    <div style="max-width:680px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:28px;">
        <h1 style="margin:0 0 12px;font-size:22px;color:#b91c1c;">CRM login security alert</h1>
        <p style="margin:0 0 16px;line-height:1.6;">
            @if($audience === 'admin')
                A login security event needs review.
            @else
                Multiple failed login attempts were detected on your CRM account.
            @endif
        </p>
        <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:12px;padding:16px;margin-bottom:18px;">
            <p style="margin:0 0 8px;"><strong>Email:</strong> {{ $event->email_normalized }}</p>
            <p style="margin:0 0 8px;"><strong>Event:</strong> {{ str_replace('_', ' ', $event->event_type) }}</p>
            <p style="margin:0 0 8px;"><strong>Lock level:</strong> {{ str_replace('_', ' ', $event->lock_level) }}</p>
            <p style="margin:0 0 8px;"><strong>Failed attempts:</strong> {{ $event->failed_count }}</p>
            <p style="margin:0 0 8px;"><strong>IP:</strong> {{ $event->ip_address ?: 'Unknown' }}</p>
            <p style="margin:0 0 8px;"><strong>Device:</strong> {{ $event->device_summary ?: 'Unknown' }}</p>
            <p style="margin:0;"><strong>Time:</strong> {{ optional($event->created_at)->format('d M Y, h:i A') }}</p>
        </div>
        @if($audience === 'admin')
            <p style="margin:0 0 18px;">
                <a href="{{ route('admin.login-security.show', $event) }}" style="display:inline-block;background:#b91c1c;color:#fff;text-decoration:none;padding:12px 18px;border-radius:10px;">Review Security Event</a>
            </p>
        @endif
        <p style="margin:0;color:#6b7280;font-size:13px;line-height:1.5;">
            If this was not you, contact the CRM admin immediately.
        </p>
    </div>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Lead Export Ready</title>
</head>
<body style="font-family:Arial,sans-serif;background:#f6f7fb;color:#1f2937;padding:24px;">
    <div style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:16px;padding:28px;">
        <h1 style="margin:0 0 12px;font-size:22px;color:#0f5132;">Lead export ready</h1>
        <p style="margin:0 0 16px;line-height:1.6;">
            Hi {{ $leadDownloadRequest->requester->name }},
            your lead download request has been approved and the file is ready.
        </p>
        <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:12px;padding:16px;margin-bottom:18px;">
            <p style="margin:0 0 8px;"><strong>Requested format:</strong> {{ strtoupper($leadDownloadRequest->format) }}</p>
            <p style="margin:0 0 8px;"><strong>Available file:</strong> {{ strtoupper($leadDownloadRequest->actual_format ?? $leadDownloadRequest->format) }}</p>
            <p style="margin:0 0 8px;"><strong>Records exported:</strong> {{ $leadDownloadRequest->exported_records_count ?? 0 }}</p>
            <p style="margin:0 0 8px;"><strong>Valid until:</strong> {{ optional($leadDownloadRequest->expires_at)->format('d M Y, h:i A') ?? '24 hours after approval' }}</p>
            <p style="margin:0;"><strong>Download limit:</strong> {{ \App\Models\LeadDownloadRequest::MAX_DOWNLOAD_CLICKS }} successful downloads</p>
        </div>
        <p style="margin:0 0 18px;line-height:1.6;">
            Download the file from your ASM portal:
        </p>
        <p style="margin:0 0 18px;">
            <a href="{{ route('sales-manager.lead-downloads.index') }}" style="display:inline-block;background:#0f5132;color:#fff;text-decoration:none;padding:12px 18px;border-radius:10px;">Open Download Center</a>
        </p>
        <p style="margin:0;color:#6b7280;font-size:13px;">
            This secure link is valid for 24 hours after approval and locks after {{ \App\Models\LeadDownloadRequest::MAX_DOWNLOAD_CLICKS }} successful downloads.
        </p>
    </div>
</body>
</html>

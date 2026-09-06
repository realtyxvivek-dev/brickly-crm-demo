<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>New demo request</title>
</head>
<body style="margin:0;background:#f4f6f4;font-family:Arial,sans-serif;color:#17211b">
    <div style="max-width:620px;margin:32px auto;background:#fff;border:1px solid #dfe7e1;border-radius:16px;overflow:hidden">
        <div style="padding:24px 28px;background:#07130d;color:#fff">
            <div style="font-size:12px;letter-spacing:.16em;text-transform:uppercase;color:#c8a95b">{{ brand_name() }}</div>
            <h1 style="margin:8px 0 0;font-size:24px">New demo request</h1>
        </div>
        <div style="padding:28px">
            <p style="margin:0 0 18px;color:#506057">A visitor submitted the landing page form.</p>
            <table role="presentation" style="width:100%;border-collapse:collapse">
                <tr><td style="padding:10px 0;color:#718078;width:120px">Name</td><td style="padding:10px 0;font-weight:700">{{ $demoRequest['name'] }}</td></tr>
                <tr><td style="padding:10px 0;color:#718078">Company</td><td style="padding:10px 0;font-weight:700">{{ $demoRequest['company'] }}</td></tr>
                <tr><td style="padding:10px 0;color:#718078">Email</td><td style="padding:10px 0"><a href="mailto:{{ $demoRequest['email'] }}" style="color:#146c43">{{ $demoRequest['email'] }}</a></td></tr>
                <tr><td style="padding:10px 0;color:#718078">Phone</td><td style="padding:10px 0">{{ $demoRequest['phone'] }}</td></tr>
                <tr><td style="padding:10px 0;color:#718078;vertical-align:top">Message</td><td style="padding:10px 0;white-space:pre-line">{{ $demoRequest['message'] ?: '—' }}</td></tr>
            </table>
        </div>
    </div>
</body>
</html>

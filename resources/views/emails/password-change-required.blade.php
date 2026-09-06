<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Change your CRM password</title>
</head>
<body style="font-family:Arial,sans-serif;background:#f6f7fb;color:#1f2937;padding:24px;">
    <div style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:16px;padding:28px;">
        <h1 style="margin:0 0 12px;font-size:22px;color:#0f5132;">Change your CRM password</h1>
        <p style="margin:0 0 16px;line-height:1.6;">
            Hi {{ $userName }},
        </p>
        <p style="margin:0 0 18px;line-height:1.6;">
            Admin has requested a password update for your CRM account. You can set a new password from the link below. Current password is not required for this request.
        </p>
        <p style="margin:0 0 18px;">
            <a href="{{ $actionUrl }}" style="display:inline-block;background:#0f5132;color:#fff;text-decoration:none;padding:12px 18px;border-radius:10px;">Change Password</a>
        </p>
        <p style="margin:0;color:#6b7280;font-size:13px;line-height:1.5;">
            If the button does not open, copy this link in your browser:<br>
            {{ $actionUrl }}
        </p>
    </div>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login OTP</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f6f9; margin: 0; padding: 0; }
        .wrapper { max-width: 520px; margin: 40px auto; background: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .header { background: linear-gradient(135deg, #063A1C, #205A44); padding: 32px 40px; text-align: center; }
        .header h1 { color: #ffffff; margin: 0; font-size: 22px; font-weight: 700; letter-spacing: 0.5px; }
        .header p { color: rgba(255,255,255,0.8); margin: 6px 0 0; font-size: 13px; }
        .body { padding: 36px 40px; }
        .greeting { font-size: 16px; color: #333; margin-bottom: 16px; }
        .message { font-size: 14px; color: #555; line-height: 1.7; margin-bottom: 28px; }
        .otp-box { background: #f0fdf4; border: 2px dashed #16a34a; border-radius: 10px; text-align: center; padding: 24px; margin-bottom: 28px; }
        .otp-label { font-size: 12px; color: #16a34a; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; }
        .otp-code { font-size: 40px; font-weight: 800; letter-spacing: 12px; color: #063A1C; font-family: 'Courier New', monospace; }
        .otp-expiry { font-size: 12px; color: #888; margin-top: 10px; }
        .warning { background: #fffbeb; border-left: 3px solid #f59e0b; padding: 12px 16px; border-radius: 4px; font-size: 13px; color: #78350f; margin-bottom: 24px; }
        .footer { background: #f8fafc; padding: 20px 40px; text-align: center; border-top: 1px solid #e5e7eb; }
        .footer p { font-size: 12px; color: #9ca3af; margin: 0; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>{{ brand_name() }}</h1>
            <p>Email OTP Login</p>
        </div>
        <div class="body">
            <p class="greeting">Hello, <strong>{{ $userName }}</strong>!</p>
            <p class="message">
                Use the OTP below to sign in to your {{ brand_name() }} account. This OTP is valid for <strong>5 minutes</strong>.
            </p>

            <div class="otp-box">
                <div class="otp-label">Your Login OTP</div>
                <div class="otp-code">{{ $otp }}</div>
                <div class="otp-expiry">Expires in 5 minutes</div>
            </div>

            <div class="warning">
                <strong>Security Notice:</strong> Do not share this OTP with anyone. {{ brand_name() }} staff will never ask for your login OTP.
            </div>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} {{ brand_name() }}. If you did not request this, you can ignore this email.</p>
        </div>
    </div>
</body>
</html>

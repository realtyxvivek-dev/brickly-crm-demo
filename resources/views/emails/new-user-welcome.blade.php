<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to {{ $appName }}</title>
</head>
<body style="margin:0; padding:0; font-family: Arial, Helvetica, sans-serif; background:#eef3f7; color:#0f172a;">
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#eef3f7; padding:28px 12px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="max-width:640px; background:#ffffff; border-radius:18px; overflow:hidden; box-shadow:0 18px 42px rgba(15,23,42,0.12);">
                    <tr>
                        <td style="background:#006BA6; padding:28px 32px;">
                            <div style="font-size:13px; letter-spacing:0.14em; text-transform:uppercase; color:#dff5ff; font-weight:700;">{{ brand_name() ?: 'Brickly CRM' }}</div>
                            <h1 style="margin:10px 0 0; font-size:28px; line-height:1.2; color:#ffffff; font-weight:800;">Welcome to {{ $appName }}</h1>
                            <p style="margin:10px 0 0; font-size:15px; line-height:1.6; color:#e8f7ff;">Your CRM account is ready. Use the details below to sign in.</p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:30px 32px 10px;">
                            <p style="margin:0 0 18px; font-size:16px; line-height:1.6;">Hi <strong>{{ $user->name }}</strong>,</p>
                            <p style="margin:0 0 22px; font-size:15px; line-height:1.7; color:#475569;">Your {{ brand_name() ?: 'Brickly CRM' }} account has been created. Please sign in with this temporary password and change it after your first login.</p>

                            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="border:1px solid #dbe5ee; border-radius:14px; overflow:hidden; font-size:14px;">
                                <tr>
                                    <td style="width:38%; padding:13px 16px; background:#f8fafc; border-bottom:1px solid #e5edf5; color:#64748b; font-weight:700;">Full name</td>
                                    <td style="padding:13px 16px; border-bottom:1px solid #e5edf5; color:#0f172a; font-weight:700;">{{ $user->name }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:13px 16px; background:#f8fafc; border-bottom:1px solid #e5edf5; color:#64748b; font-weight:700;">Email</td>
                                    <td style="padding:13px 16px; border-bottom:1px solid #e5edf5;"><a href="mailto:{{ $user->email }}" style="color:#006BA6; text-decoration:none; font-weight:700;">{{ $user->email }}</a></td>
                                </tr>
                                <tr>
                                    <td style="padding:13px 16px; background:#f8fafc; border-bottom:1px solid #e5edf5; color:#64748b; font-weight:700;">Temporary password</td>
                                    <td style="padding:13px 16px; border-bottom:1px solid #e5edf5;">
                                        <span style="display:inline-block; font-family:Consolas, Monaco, monospace; letter-spacing:0.04em; background:#e0f2fe; color:#002B45; padding:8px 10px; border-radius:8px; font-weight:800;">{{ $plainPassword }}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:13px 16px; background:#f8fafc; border-bottom:1px solid #e5edf5; color:#64748b; font-weight:700;">Position</td>
                                    <td style="padding:13px 16px; border-bottom:1px solid #e5edf5; color:#0f172a;">{{ $roleName }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:13px 16px; background:#f8fafc; border-bottom:1px solid #e5edf5; color:#64748b; font-weight:700;">Reporting manager</td>
                                    <td style="padding:13px 16px; border-bottom:1px solid #e5edf5; color:#0f172a;">{{ $managerName ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:13px 16px; background:#f8fafc; color:#64748b; font-weight:700;">Phone</td>
                                    <td style="padding:13px 16px; color:#0f172a;">{{ $user->phone ?? '-' }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:18px 32px 30px;">
                            <table cellpadding="0" cellspacing="0" role="presentation">
                                <tr>
                                    <td style="padding:0 10px 10px 0;">
                                        <a href="{{ $loginUrl }}" style="display:inline-block; padding:13px 22px; background:#006BA6; color:#ffffff !important; text-decoration:none; border-radius:10px; font-weight:800; font-size:14px;">Log in to {{ brand_name() ?: 'Brickly CRM' }}</a>
                                    </td>
                                    <td style="padding:0 0 10px 0;">
                                        <a href="{{ $installAppUrl ?? url('/install-app') }}" style="display:inline-block; padding:12px 20px; background:#ffffff; color:#006BA6 !important; text-decoration:none; border-radius:10px; border:1px solid #006BA6; font-weight:800; font-size:14px;">Install App</a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:4px 0 0; font-size:13px; line-height:1.6; color:#64748b;">For security, do not share this temporary password. Change it after your first login.</p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:18px 32px; background:#f8fafc; border-top:1px solid #e5edf5; font-size:13px; line-height:1.6; color:#64748b;">
                            Need help? Contact your CRM administrator.<br>
                            <strong style="color:#002B45;">{{ brand_name() ?: 'Brickly CRM' }}</strong>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

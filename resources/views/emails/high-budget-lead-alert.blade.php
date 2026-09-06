<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>High Budget Lead Alert</title>
</head>
<body style="margin:0;padding:0;background:#eef4f1;font-family:Arial,Helvetica,sans-serif;color:#10231c;">
    @php
        $owners = collect($owners ?? []);
        $sourceLabel = str_replace('_', ' ', ucfirst((string) $source));
        $project = $lead->preferred_projects ?: ($related->project ?? 'N/A');
        $generatedAt = now()->format('d M Y h:i A');
    @endphp

    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:#eef4f1;margin:0;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width:680px;background:#ffffff;border:1px solid #d7e4dd;border-radius:18px;overflow:hidden;box-shadow:0 14px 34px rgba(15,23,42,.08);">
                    <tr>
                        <td style="background:#0b3b1d;padding:26px 28px;color:#ffffff;">
                            <div style="font-size:12px;font-weight:800;letter-spacing:1.8px;text-transform:uppercase;color:#b8e7cb;">{{ $isTest ?? false ? 'Test Mail' : 'CRM Alert' }}</div>
                            <h1 style="margin:8px 0 0;font-size:28px;line-height:1.2;font-weight:900;">High Budget Lead Alert</h1>
                            <p style="margin:10px 0 0;color:#e7f6ec;font-size:15px;line-height:1.6;">A customer with budget above 2 Cr has been captured in {{ brand_name() }}.</p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:24px 28px 8px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td style="padding:0 0 14px;">
                                        <div style="font-size:12px;font-weight:900;letter-spacing:1.4px;text-transform:uppercase;color:#607469;">Customer Details</div>
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border:1px solid #dfeae4;border-radius:14px;overflow:hidden;">
                                <tr>
                                    <td style="padding:14px 16px;background:#f7fbf8;border-bottom:1px solid #e5eee8;font-size:13px;color:#5f7468;font-weight:800;">Customer</td>
                                    <td style="padding:14px 16px;border-bottom:1px solid #e5eee8;font-size:15px;font-weight:900;color:#10231c;">{{ $lead->name ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 16px;background:#f7fbf8;border-bottom:1px solid #e5eee8;font-size:13px;color:#5f7468;font-weight:800;">Phone</td>
                                    <td style="padding:14px 16px;border-bottom:1px solid #e5eee8;font-size:15px;font-weight:800;color:#10231c;">{{ $lead->phone ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 16px;background:#f7fbf8;border-bottom:1px solid #e5eee8;font-size:13px;color:#5f7468;font-weight:800;">Budget</td>
                                    <td style="padding:14px 16px;border-bottom:1px solid #e5eee8;font-size:15px;font-weight:900;color:#0b6b3f;">{{ $budget }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 16px;background:#f7fbf8;border-bottom:1px solid #e5eee8;font-size:13px;color:#5f7468;font-weight:800;">Project</td>
                                    <td style="padding:14px 16px;border-bottom:1px solid #e5eee8;font-size:15px;font-weight:800;color:#10231c;">{{ $project }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 16px;background:#f7fbf8;border-bottom:1px solid #e5eee8;font-size:13px;color:#5f7468;font-weight:800;">Source</td>
                                    <td style="padding:14px 16px;border-bottom:1px solid #e5eee8;font-size:15px;font-weight:800;color:#10231c;">{{ $sourceLabel }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 16px;background:#f7fbf8;font-size:13px;color:#5f7468;font-weight:800;">Lead ID</td>
                                    <td style="padding:14px 16px;font-size:15px;font-weight:800;color:#10231c;">#{{ $lead->id }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:18px 28px 8px;">
                            <div style="font-size:12px;font-weight:900;letter-spacing:1.4px;text-transform:uppercase;color:#607469;margin-bottom:12px;">Lead Currently With</div>
                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border:1px solid #dfeae4;border-radius:14px;overflow:hidden;">
                                @forelse($owners as $owner)
                                    <tr>
                                        <td style="padding:15px 16px;{{ !$loop->last ? 'border-bottom:1px solid #e5eee8;' : '' }}">
                                            <div style="font-size:16px;font-weight:900;color:#10231c;">{{ $owner['name'] ?? 'Assigned User' }}</div>
                                            <div style="font-size:13px;color:#5f7468;margin-top:4px;">{{ $owner['role'] ?? 'Team User' }} @if(!empty($owner['email'])) · {{ $owner['email'] }} @endif</div>
                                            @if(!empty($owner['assigned_at']))
                                                <div style="font-size:12px;color:#7a8d84;margin-top:5px;">Assigned at {{ $owner['assigned_at'] }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td style="padding:15px 16px;">
                                            <div style="font-size:16px;font-weight:900;color:#92400e;">Unassigned</div>
                                            <div style="font-size:13px;color:#5f7468;margin-top:4px;">No active owner is currently mapped to this lead.</div>
                                        </td>
                                    </tr>
                                @endforelse
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:22px 28px 26px;">
                            <a href="{{ $leadUrl }}" style="display:inline-block;background:#0b6b3f;color:#ffffff;text-decoration:none;border-radius:12px;padding:13px 22px;font-size:14px;font-weight:900;">Open Lead In CRM</a>
                        </td>
                    </tr>

                    <tr>
                        <td style="background:#f6faf7;border-top:1px solid #e5eee8;padding:16px 28px;color:#64746d;font-size:12px;line-height:1.6;">
                            Generated at {{ $generatedAt }}. Alert source: {{ $sourceLabel }}.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

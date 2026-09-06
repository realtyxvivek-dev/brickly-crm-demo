@php
    $summary = $report['summary'] ?? [];
    $sources = collect($report['lead_sources'] ?? []);
    $statuses = collect($report['lead_status'] ?? []);
    $date = $report['date'];
    $dateParam = $date instanceof \Carbon\Carbon ? $date->toDateString() : \Carbon\Carbon::parse($date)->toDateString();
    $range = $report['range'] ?? 'today';
    $rangeLabel = $report['range_label'] ?? ($date instanceof \Carbon\Carbon ? $date->format('d M Y') : \Carbon\Carbon::parse($date)->format('d M Y'));
    $generatedAt = $report['generated_at'] ?? now();
    $pdfUrl = route('admin.mail-center.daily-report-pdf', ['date' => $dateParam, 'range' => $range]);
    $leadIntake = $report['lead_intake'] ?? [
        'total' => $summary['total_leads'] ?? 0,
        'interested' => $summary['interested'] ?? 0,
        'not_interested' => $summary['not_interested'] ?? 0,
        'cnp' => $summary['cnp'] ?? 0,
        'junk' => 0,
        'new' => 0,
        'other' => 0,
        'high_budget_clients' => $summary['high_budget_clients'] ?? 0,
    ];
    $activitySummary = $report['activity_summary'] ?? [
        'follow_ups' => $summary['follow_up'] ?? 0,
        'meetings_scheduled' => $summary['meetings_scheduled'] ?? 0,
        'meetings_completed' => $summary['meetings_completed'] ?? 0,
        'visits_scheduled' => $summary['visits_scheduled'] ?? 0,
        'visits_completed' => $summary['visits_completed'] ?? 0,
    ];
    $interestedBreakdown = collect($report['interested_breakdown'] ?? []);
    $leadQuality = collect($report['lead_quality'] ?? []);
    $rangeName = match ($range) {
        'today' => 'Today',
        'previous_day' => 'Previous Day',
        'this_week' => 'This Week',
        'previous_week' => 'Previous Week',
        'this_month' => 'This Month',
        'previous_month' => 'Previous Month',
        'all_time' => 'All Time',
        default => $rangeLabel,
    };

    $leadIntakeRows = [
        ['Total Leads', $leadIntake['total'] ?? 0],
        ['Interested', $leadIntake['interested'] ?? 0],
        ['Not Interested', $leadIntake['not_interested'] ?? 0],
        ['CNP', $leadIntake['cnp'] ?? 0],
        ['Junk', $leadIntake['junk'] ?? 0],
        ['New', $leadIntake['new'] ?? 0],
        ['Fresh Transfer', $leadIntake['fresh_transfer'] ?? 0],
        ['Other', $leadIntake['other'] ?? 0],
        ['2 Cr+ Clients', $leadIntake['high_budget_clients'] ?? 0],
    ];

    $activityRows = [
        ['Follow-ups', $activitySummary['follow_ups'] ?? 0],
        ['Meetings Scheduled', $activitySummary['meetings_scheduled'] ?? 0],
        ['Meetings Verified', $activitySummary['meetings_completed'] ?? 0],
        ['Site Visits Scheduled', $activitySummary['visits_scheduled'] ?? 0],
        ['Site Visits Verified', $activitySummary['visits_completed'] ?? 0],
    ];

    $activityBlocks = [
        ['Meetings', $report['meetings'] ?? []],
        ['Site Visits', $report['visits'] ?? []],
        ['Follow-ups', $report['follow_ups'] ?? []],
    ];

    $barWidth = function ($value, $total) {
        return max(2, min(100, round(((int) $value / max(1, (int) $total)) * 100)));
    };
@endphp
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ brand_name() }} Report</title>
</head>
<body style="margin:0;padding:0;background:#eef4ef;font-family:Arial,Helvetica,sans-serif;color:#052e1b;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;background:#eef4ef;">
        <tr>
            <td align="center" style="padding:12px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;max-width:680px;background:#ffffff;border:1px solid #d9e5dd;border-radius:16px;overflow:hidden;">
                    <tr>
                        <td style="background:#0b5a36;color:#ffffff;padding:22px 20px;">
                            <div style="font-size:11px;letter-spacing:1.8px;text-transform:uppercase;font-weight:bold;opacity:.9;">{{ brand_name() }} ERP Report</div>
                            <div style="font-size:26px;line-height:1.15;font-weight:bold;margin-top:8px;">Business Snapshot</div>
                            <div style="font-size:14px;line-height:1.5;margin-top:8px;opacity:.95;">{{ $rangeLabel }} | Generated {{ optional($generatedAt)->format('d M Y h:i A') }}</div>
                            <a href="{{ $pdfUrl }}" style="display:inline-block;margin-top:16px;background:#ffffff;color:#0b5a36;text-decoration:none;border-radius:10px;padding:11px 16px;font-size:14px;font-weight:bold;">Download PDF</a>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:14px 14px 4px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;border:1px solid #dce8e1;border-radius:14px;background:#ffffff;margin-bottom:12px;">
                                <tr>
                                    <td colspan="2" style="padding:16px 16px 4px;font-size:20px;font-weight:bold;">Lead Intake {{ $rangeName }}</td>
                                </tr>
                                <tr>
                                    <td colspan="2" style="padding:0 16px 10px;color:#64746d;font-size:12px;line-height:1.5;">Yeh breakup sirf is range me created leads ka current status hai.</td>
                                </tr>
                                @foreach($leadIntakeRows as $row)
                                    <tr>
                                        <td style="padding:10px 16px;border-top:1px solid #edf3ef;color:#52675e;font-size:13px;font-weight:bold;">{{ $row[0] }}</td>
                                        <td align="right" style="padding:10px 16px;border-top:1px solid #edf3ef;color:#052e1b;font-size:20px;font-weight:bold;">{{ $row[1] }}</td>
                                    </tr>
                                @endforeach
                            </table>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;border:1px solid #dce8e1;border-radius:14px;background:#ffffff;margin-bottom:12px;">
                                <tr>
                                    <td colspan="2" style="padding:16px 16px 4px;font-size:20px;font-weight:bold;">Interested Breakdown {{ $rangeName }}</td>
                                </tr>
                                <tr>
                                    <td colspan="2" style="padding:0 16px 10px;color:#64746d;font-size:12px;line-height:1.5;">Interested total ke andar lead kis active stage me hai.</td>
                                </tr>
                                @forelse($interestedBreakdown as $row)
                                    <tr>
                                        <td style="padding:10px 16px;border-top:1px solid #edf3ef;color:#52675e;font-size:13px;font-weight:bold;">{{ $row['label'] }}</td>
                                        <td align="right" style="padding:10px 16px;border-top:1px solid #edf3ef;color:#052e1b;font-size:20px;font-weight:bold;">{{ $row['count'] }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="2" style="padding:13px 16px;border-top:1px solid #edf3ef;color:#64748b;font-size:14px;">No interested-stage data found.</td></tr>
                                @endforelse
                            </table>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;border:1px solid #dce8e1;border-radius:14px;background:#ffffff;margin-bottom:12px;">
                                <tr>
                                    <td colspan="3" style="padding:16px 16px 4px;font-size:20px;font-weight:bold;">Lead Quality {{ $rangeName }}</td>
                                </tr>
                                <tr>
                                    <td colspan="3" style="padding:0 16px 10px;color:#64746d;font-size:12px;line-height:1.5;">Quick quality view: hot, warm, cold aur pending lead mix.</td>
                                </tr>
                                @forelse($leadQuality as $row)
                                    <tr>
                                        <td style="padding:10px 16px;border-top:1px solid #edf3ef;color:#052e1b;font-size:13px;font-weight:bold;">{{ $row['label'] }}</td>
                                        <td style="padding:10px 16px;border-top:1px solid #edf3ef;color:#64746d;font-size:12px;">{{ $row['note'] }}</td>
                                        <td align="right" style="padding:10px 16px;border-top:1px solid #edf3ef;color:#052e1b;font-size:20px;font-weight:bold;">{{ $row['count'] }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" style="padding:13px 16px;border-top:1px solid #edf3ef;color:#64748b;font-size:14px;">No lead quality data found.</td></tr>
                                @endforelse
                            </table>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;border:1px solid #dce8e1;border-radius:14px;background:#ffffff;margin-bottom:12px;">
                                <tr>
                                    <td colspan="2" style="padding:16px 16px 8px;font-size:20px;font-weight:bold;">Activity {{ $rangeName }}</td>
                                </tr>
                                @foreach($activityRows as $row)
                                    <tr>
                                        <td style="padding:10px 16px;border-top:1px solid #edf3ef;color:#52675e;font-size:13px;font-weight:bold;">{{ $row[0] }}</td>
                                        <td align="right" style="padding:10px 16px;border-top:1px solid #edf3ef;color:#052e1b;font-size:20px;font-weight:bold;">{{ $row[1] }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:4px 14px 0;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;border:1px solid #dce8e1;border-radius:14px;background:#ffffff;margin-bottom:12px;">
                                <tr><td style="padding:16px 16px 6px;font-size:20px;font-weight:bold;">Lead Sources</td></tr>
                                <tr>
                                    <td style="padding:0 16px 14px;">
                                        @php $sourceTotal = max(1, (int) $sources->sum('count')); @endphp
                                        @forelse($sources->take(8) as $row)
                                            <div style="font-size:14px;margin:10px 0 5px;">
                                                <strong>{{ $row['label'] }}</strong>
                                                <span style="float:right;">{{ $row['count'] }}</span>
                                            </div>
                                            <div style="height:8px;background:#e7f0eb;border-radius:99px;overflow:hidden;">
                                                <div style="height:8px;background:#0f6b3f;border-radius:99px;width:{{ $barWidth($row['count'], $sourceTotal) }}%;"></div>
                                            </div>
                                        @empty
                                            <div style="color:#64748b;font-size:14px;padding:8px 0;">No leads found.</div>
                                        @endforelse
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;border:1px solid #dce8e1;border-radius:14px;background:#ffffff;margin-bottom:12px;">
                                <tr><td style="padding:16px 16px 4px;font-size:20px;font-weight:bold;">Lead Status</td></tr>
                                <tr>
                                    <td style="padding:0 16px 10px;color:#64746d;font-size:12px;line-height:1.5;">Same display status logic as Leads filter. New excludes CNP/follow-up leads.</td>
                                </tr>
                                <tr>
                                    <td style="padding:0 16px 14px;">
                                        @php $statusTotal = max(1, (int) $statuses->sum('count')); @endphp
                                        @forelse($statuses->take(8) as $row)
                                            <div style="font-size:14px;margin:10px 0 5px;">
                                                <strong>{{ $row['label'] }}</strong>
                                                <span style="float:right;">{{ $row['count'] }}</span>
                                            </div>
                                            <div style="height:8px;background:#e7f0eb;border-radius:99px;overflow:hidden;">
                                                <div style="height:8px;background:#2563eb;border-radius:99px;width:{{ $barWidth($row['count'], $statusTotal) }}%;"></div>
                                            </div>
                                        @empty
                                            <div style="color:#64748b;font-size:14px;padding:8px 0;">No status data found.</div>
                                        @endforelse
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 14px;">
                            @foreach($activityBlocks as [$title, $block])
                                @php $rate = (float) ($block['completion_rate'] ?? 0); @endphp
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;border:1px solid #dce8e1;border-radius:14px;background:#ffffff;margin-bottom:12px;">
                                    <tr><td colspan="2" style="padding:16px 16px 8px;font-size:20px;font-weight:bold;">{{ $title }}</td></tr>
                                    <tr>
                                        <td style="padding:4px 16px;color:#52675e;font-size:14px;">Scheduled</td>
                                        <td align="right" style="padding:4px 16px;font-size:16px;font-weight:bold;">{{ $block['scheduled'] ?? 0 }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:4px 16px;color:#52675e;font-size:14px;">Verified</td>
                                        <td align="right" style="padding:4px 16px;font-size:16px;font-weight:bold;">{{ $block['verified'] ?? 0 }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:4px 16px;color:#52675e;font-size:14px;">Missed / Pending</td>
                                        <td align="right" style="padding:4px 16px;font-size:16px;font-weight:bold;color:#b45309;">{{ $block['missed'] ?? $block['pending'] ?? 0 }}</td>
                                    </tr>
                                    <tr>
                                        <td colspan="2" style="padding:10px 16px 16px;">
                                            <div style="font-size:13px;color:#52675e;margin-bottom:7px;">Completion {{ $rate }}%</div>
                                            <div style="height:9px;background:#e7f0eb;border-radius:99px;overflow:hidden;">
                                                <div style="height:9px;background:#0f6b3f;border-radius:99px;width:{{ min(100, max(0, $rate)) }}%;"></div>
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            @endforeach
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 14px 14px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;border:1px solid #dce8e1;border-radius:14px;overflow:hidden;background:#ffffff;">
                                <tr><td colspan="7" style="padding:16px;background:#f6faf7;font-size:18px;font-weight:bold;">Top User Activity</td></tr>
                                <tr style="background:#fbfdfb;color:#52675e;font-size:12px;text-transform:uppercase;">
                                    <th align="left" style="padding:9px;">User</th>
                                    <th align="right" style="padding:9px;">Assigned</th>
                                    <th align="right" style="padding:9px;">Follow</th>
                                    <th align="right" style="padding:9px;">Meet Sch.</th>
                                    <th align="right" style="padding:9px;">Meet Ver.</th>
                                    <th align="right" style="padding:9px;">Visit Sch.</th>
                                    <th align="right" style="padding:9px;">Visit Ver.</th>
                                </tr>
                                @forelse($report['user_rows'] ?? [] as $row)
                                    <tr>
                                        <td style="padding:10px 9px;border-top:1px solid #edf3ef;font-weight:bold;font-size:13px;">{{ $row['user'] }}</td>
                                        <td align="right" style="padding:10px 9px;border-top:1px solid #edf3ef;font-size:13px;">{{ $row['assigned'] }}</td>
                                        <td align="right" style="padding:10px 9px;border-top:1px solid #edf3ef;font-size:13px;">{{ $row['followups'] }}</td>
                                        <td align="right" style="padding:10px 9px;border-top:1px solid #edf3ef;font-size:13px;">{{ $row['meeting_scheduled'] ?? $row['meetings'] ?? 0 }}</td>
                                        <td align="right" style="padding:10px 9px;border-top:1px solid #edf3ef;font-size:13px;">{{ $row['meeting_verified'] ?? 0 }}</td>
                                        <td align="right" style="padding:10px 9px;border-top:1px solid #edf3ef;font-size:13px;">{{ $row['visit_scheduled'] ?? $row['visits'] ?? 0 }}</td>
                                        <td align="right" style="padding:10px 9px;border-top:1px solid #edf3ef;font-size:13px;">{{ $row['visit_verified'] ?? 0 }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" style="padding:13px;color:#64748b;font-size:14px;">No user activity found.</td></tr>
                                @endforelse
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 14px 18px;">
                            <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:14px;padding:14px;color:#7c2d12;font-size:13px;line-height:1.6;">
                                <strong>Alerts</strong><br>
                                No remark leads: {{ $report['alerts']['no_remark_leads'] ?? 0 }} |
                                Missed meetings: {{ $report['alerts']['missed_meetings'] ?? 0 }} |
                                Missed visits: {{ $report['alerts']['missed_visits'] ?? 0 }} |
                                Pending verifications: {{ $report['alerts']['pending_verifications'] ?? 0 }}
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

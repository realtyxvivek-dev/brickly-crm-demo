@php
    $period = \Carbon\Carbon::create((int) $payslip->year, (int) $payslip->month, 1);
    $money = fn ($amount) => 'Rs ' . number_format((float) $amount, 2);
    $payableDays = number_format((float) ($attendance['payable_days'] ?? 0), 1);
    $monthDays = $period->daysInMonth;
    $earnings = collect($earnings ?? ($payslip->snapshot_json['earnings'] ?? []));
    $deductions = collect($deductions ?? ($payslip->snapshot_json['deductions'] ?? []));
    $attendanceDetails = collect($attendanceDetails ?? []);
    $statusLabel = function ($record) {
        if (($record->status ?? '') === \App\Models\AttendanceRecord::STATUS_LEAVE) {
            return ((float) ($record->payable_day_fraction ?? 0) > 0) ? 'Paid Leave' : 'Unpaid Leave';
        }
        return ucwords(str_replace('_', ' ', (string) ($record->status ?? '')));
    };
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payroll Preview</title>
</head>
<body style="margin:0;padding:0;background:#f6f7f5;font-family:Arial,sans-serif;color:#10241c;">
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#f6f7f5;padding:24px 0;">
        <tr>
            <td align="center">
                <table width="620" cellpadding="0" cellspacing="0" role="presentation" style="max-width:620px;width:100%;background:#ffffff;border:1px solid #dfe7e1;border-radius:14px;overflow:hidden;">
                    <tr>
                        <td style="padding:22px 24px;background:#0f5b42;color:#ffffff;">
                            <div style="font-size:12px;text-transform:uppercase;letter-spacing:1.4px;font-weight:700;color:#dcefe7;">Payroll Preview</div>
                            <div style="font-size:24px;font-weight:800;margin-top:6px;">{{ $period->format('F Y') }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:22px 24px;">
                            <p style="margin:0 0 12px;font-size:15px;">Hello {{ $employee->name }},</p>
                            <p style="margin:0 0 18px;font-size:15px;line-height:1.6;">
                                Aapka payroll preview ready hai. Please gross salary, deductions, net salary aur payable days check kar lijiye.
                            </p>

                            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="border-collapse:collapse;margin:18px 0;">
                                <tr>
                                    <td style="padding:12px;border:1px solid #e5eee8;background:#fbfdfb;">
                                        <div style="font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#64756d;font-weight:700;">Gross Salary</div>
                                        <div style="font-size:20px;font-weight:800;margin-top:5px;">{{ $money($payslip->gross_pay) }}</div>
                                    </td>
                                    <td style="padding:12px;border:1px solid #e5eee8;background:#fbfdfb;">
                                        <div style="font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#64756d;font-weight:700;">Payable Days</div>
                                        <div style="font-size:20px;font-weight:800;margin-top:5px;">{{ $payableDays }} / {{ $monthDays }}</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:12px;border:1px solid #e5eee8;background:#fbfdfb;">
                                        <div style="font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#64756d;font-weight:700;">Deductions</div>
                                        <div style="font-size:20px;font-weight:800;margin-top:5px;">{{ $money($payslip->total_deductions) }}</div>
                                    </td>
                                    <td style="padding:12px;border:1px solid #e5eee8;background:#fbfdfb;">
                                        <div style="font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#64756d;font-weight:700;">Net Pay</div>
                                        <div style="font-size:20px;font-weight:800;margin-top:5px;">{{ $money($payslip->net_pay) }}</div>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 18px;font-size:14px;line-height:1.6;color:#52645d;">
                                Neeche full salary breakup aur attendance breakup diya gaya hai. Agar koi correction chahiye ho to CRM me payslip open karke correction request submit karein.
                            </p>

                            <a href="{{ $previewUrl }}" style="display:inline-block;background:#0f5b42;color:#ffffff;text-decoration:none;padding:12px 18px;border-radius:10px;font-weight:800;">
                                Open Payroll Preview
                            </a>

                            <h3 style="margin:24px 0 10px;font-size:17px;color:#10241c;">Attendance Calculation</h3>
                            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="border-collapse:collapse;margin:0 0 18px;">
                                @foreach([
                                    'Total Month Days' => $monthDays,
                                    'Payable Days' => $payableDays,
                                    'Present / Late' => number_format((float) ($attendance['present_days'] ?? 0), 1),
                                    'Half Day' => number_format((float) ($attendance['half_days'] ?? 0), 1),
                                    'Absent' => number_format((float) ($attendance['absent_days'] ?? 0), 1),
                                    'Paid Leave' => number_format((float) ($attendance['paid_leave_days'] ?? 0), 1),
                                    'Unpaid Leave' => number_format((float) ($attendance['unpaid_leave_days'] ?? 0), 1),
                                    'Week Off / Holiday' => number_format((float) (($attendance['weekoff_days'] ?? 0) + ($attendance['holiday_days'] ?? 0)), 1),
                                    'Late Count' => (int) ($attendance['late_count'] ?? 0),
                                    'Late Penalty Days' => number_format((float) ($attendance['late_penalty_days'] ?? 0), 1),
                                ] as $label => $value)
                                    <tr>
                                        <td style="padding:8px 10px;border:1px solid #e5eee8;background:#fbfdfb;font-size:12px;color:#64756d;font-weight:700;">{{ $label }}</td>
                                        <td style="padding:8px 10px;border:1px solid #e5eee8;font-size:13px;font-weight:800;text-align:right;">{{ $value }}</td>
                                    </tr>
                                @endforeach
                            </table>

                            <h3 style="margin:24px 0 10px;font-size:17px;color:#10241c;">Date-wise Attendance Impact</h3>
                            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="border-collapse:collapse;margin:0 0 18px;">
                                <tr>
                                    <th align="left" style="padding:9px;border:1px solid #e5eee8;background:#f7faf8;font-size:11px;color:#64756d;">Date</th>
                                    <th align="left" style="padding:9px;border:1px solid #e5eee8;background:#f7faf8;font-size:11px;color:#64756d;">Status</th>
                                    <th align="right" style="padding:9px;border:1px solid #e5eee8;background:#f7faf8;font-size:11px;color:#64756d;">Payable</th>
                                    <th align="left" style="padding:9px;border:1px solid #e5eee8;background:#f7faf8;font-size:11px;color:#64756d;">Reason</th>
                                </tr>
                                @forelse($attendanceDetails as $record)
                                    <tr>
                                        <td style="padding:9px;border:1px solid #e5eee8;font-size:12px;font-weight:700;">{{ $record->attendance_date?->format('d M Y') }}</td>
                                        <td style="padding:9px;border:1px solid #e5eee8;font-size:12px;">{{ $statusLabel($record) }}</td>
                                        <td align="right" style="padding:9px;border:1px solid #e5eee8;font-size:12px;font-weight:700;">{{ number_format((float) ($record->payable_day_fraction ?? 0), 1) }}</td>
                                        <td style="padding:9px;border:1px solid #e5eee8;font-size:12px;">{{ $record->manual_override_reason ?: ucwords(str_replace('_', ' ', (string) ($record->status_source ?? '-'))) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" style="padding:10px;border:1px solid #e5eee8;font-size:12px;color:#64756d;">No salary-impact attendance dates.</td></tr>
                                @endforelse
                            </table>

                            <h3 style="margin:24px 0 10px;font-size:17px;color:#10241c;">Earnings</h3>
                            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="border-collapse:collapse;margin:0 0 18px;">
                                @forelse($earnings as $line)
                                    <tr>
                                        <td style="padding:9px;border:1px solid #e5eee8;font-size:13px;">{{ $line['label'] ?? '-' }}</td>
                                        <td align="right" style="padding:9px;border:1px solid #e5eee8;font-size:13px;font-weight:800;">{{ $money($line['amount'] ?? 0) }}</td>
                                    </tr>
                                @empty
                                    <tr><td style="padding:9px;border:1px solid #e5eee8;">No earnings</td><td align="right" style="padding:9px;border:1px solid #e5eee8;">{{ $money(0) }}</td></tr>
                                @endforelse
                                <tr><td style="padding:9px;border:1px solid #e5eee8;font-weight:800;">Total Earnings</td><td align="right" style="padding:9px;border:1px solid #e5eee8;font-weight:800;">{{ $money($payslip->gross_pay) }}</td></tr>
                            </table>

                            <h3 style="margin:24px 0 10px;font-size:17px;color:#10241c;">Deductions</h3>
                            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="border-collapse:collapse;margin:0 0 18px;">
                                @forelse($deductions as $line)
                                    <tr>
                                        <td style="padding:9px;border:1px solid #e5eee8;font-size:13px;">{{ $line['label'] ?? '-' }}</td>
                                        <td align="right" style="padding:9px;border:1px solid #e5eee8;font-size:13px;font-weight:800;">{{ $money($line['amount'] ?? 0) }}</td>
                                    </tr>
                                @empty
                                    <tr><td style="padding:9px;border:1px solid #e5eee8;">No deductions</td><td align="right" style="padding:9px;border:1px solid #e5eee8;">{{ $money(0) }}</td></tr>
                                @endforelse
                                <tr><td style="padding:9px;border:1px solid #e5eee8;font-weight:800;">Total Deductions</td><td align="right" style="padding:9px;border:1px solid #e5eee8;font-weight:800;">{{ $money($payslip->total_deductions) }}</td></tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

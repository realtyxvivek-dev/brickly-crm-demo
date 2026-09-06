@php
    $period = \Carbon\Carbon::create((int) $payslip->year, (int) $payslip->month, 1);
    $generatedOn = $payslip->generated_at ?? $payslip->created_at ?? now();
    $money = static fn ($amount): string => 'Rs ' . number_format((float) $amount, 2);
    $isFinalPayslip = $payslip->canDownloadFinal();
    $monthDays = $period->daysInMonth;
    $earnings = collect($earnings ?? ($payslip->snapshot_json['earnings'] ?? []));
    $deductions = collect($deductions ?? ($payslip->snapshot_json['deductions'] ?? []));
    $attendanceDetails = collect($attendanceDetails ?? []);
    $statusLabel = static function ($record): string {
        if (($record->status ?? '') === \App\Models\AttendanceRecord::STATUS_LEAVE) {
            return ((float) ($record->payable_day_fraction ?? 0) > 0) ? 'Paid Leave' : 'Unpaid Leave';
        }

        return ucwords(str_replace('_', ' ', (string) ($record->status ?? '')));
    };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip Verification</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: linear-gradient(160deg, #f6faf7 0%, #edf5f0 100%);
            color: #153226;
        }

        .page {
            max-width: 760px;
            margin: 0 auto;
            padding: 32px 16px 48px;
        }

        .card {
            background: #fff;
            border: 1px solid #dbe7df;
            border-radius: 24px;
            box-shadow: 0 18px 40px rgba(25, 62, 47, 0.08);
            overflow: hidden;
        }

        .hero {
            padding: 28px;
            background: linear-gradient(135deg, #184f39, #226749);
            color: #fff;
        }

        .eyebrow {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.18em;
            opacity: 0.8;
        }

        .title {
            margin-top: 10px;
            font-size: 34px;
            font-weight: 700;
        }

        .copy {
            margin-top: 8px;
            font-size: 15px;
            opacity: 0.88;
        }

        .status {
            display: inline-block;
            margin-top: 16px;
            padding: 8px 14px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.16);
            font-size: 13px;
            font-weight: 700;
        }

        .preview-warning {
            margin-top: 18px;
            padding: 14px 16px;
            border-radius: 16px;
            border: 1px dashed #f59e0b;
            background: #fff7ed;
            color: #92400e;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .body {
            padding: 28px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
        }

        .stat {
            border: 1px solid #e1ebe4;
            border-radius: 18px;
            padding: 16px;
            background: #fbfdfc;
        }

        .label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: #6d8478;
            font-weight: 700;
        }

        .value {
            margin-top: 8px;
            font-size: 22px;
            font-weight: 700;
            color: #133427;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 22px;
        }

        .table td {
            padding: 12px 0;
            border-bottom: 1px solid #edf2ee;
            vertical-align: top;
        }

        .table tr:last-child td {
            border-bottom: none;
        }

        .table td:first-child {
            width: 34%;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #70867b;
            font-weight: 700;
        }

        .table td:last-child {
            font-size: 15px;
            font-weight: 600;
        }

        .foot {
            margin-top: 24px;
            font-size: 13px;
            color: #658075;
        }

        .section {
            margin-top: 26px;
            padding-top: 22px;
            border-top: 1px solid #edf2ee;
        }

        .section-title {
            margin: 0 0 12px;
            font-size: 18px;
            font-weight: 800;
            color: #0f2c20;
        }

        .mini-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(145px, 1fr));
            gap: 10px;
        }

        .mini-stat {
            border: 1px solid #e1ebe4;
            border-radius: 14px;
            padding: 12px;
            background: #fbfdfc;
        }

        .mini-stat .value {
            margin-top: 5px;
            font-size: 18px;
        }

        .breakup-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .breakup-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #e4ece7;
            border-radius: 14px;
            overflow: hidden;
        }

        .breakup-table th,
        .breakup-table td {
            padding: 11px 12px;
            border-bottom: 1px solid #edf2ee;
            text-align: left;
            font-size: 14px;
        }

        .breakup-table th {
            background: #f7faf8;
            color: #60776c;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        .breakup-table td:last-child,
        .breakup-table th:last-child {
            text-align: right;
            font-weight: 800;
        }

        .breakup-table tr:last-child td {
            border-bottom: none;
        }

        .impact-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 10px;
        }

        .impact-card {
            border: 1px solid #e1ebe4;
            border-radius: 14px;
            padding: 12px;
            background: #fbfdfc;
        }

        .impact-card .date {
            font-weight: 800;
            color: #0f2c20;
        }

        .impact-card .meta {
            margin-top: 7px;
            font-size: 13px;
            line-height: 1.55;
            color: #52675e;
        }

        .badge {
            display: inline-block;
            margin-left: 8px;
            padding: 4px 8px;
            border-radius: 999px;
            background: #e8f7ef;
            color: #047857;
            font-size: 11px;
            font-weight: 800;
        }

        @media (max-width: 680px) {
            .breakup-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="card">
            <div class="hero">
                <div class="eyebrow">Payslip Verification</div>
                <div class="title">{{ $isFinalPayslip ? 'Valid Document' : 'Preview Payslip' }}</div>
                <div class="copy">
                    {{ $isFinalPayslip ? 'This payslip token is active and matches a generated payroll record in the HRMS.' : 'This preview is for employee review only. Final download opens after Admin approval.' }}
                </div>
                <div class="status">{{ $isFinalPayslip ? 'Verified' : 'Preview' }} on {{ now()->format('d M Y, h:i A') }}</div>
                @unless($isFinalPayslip)
                    <div class="preview-warning">This is not final payslip</div>
                @endunless
            </div>
            <div class="body">
                <div class="grid">
                    <div class="stat">
                        <div class="label">Payslip Number</div>
                        <div class="value">{{ $payslip->payslip_number }}</div>
                    </div>
                    <div class="stat">
                        <div class="label">Payroll Month</div>
                        <div class="value">{{ $period->format('M Y') }}</div>
                    </div>
                    <div class="stat">
                        <div class="label">Net Pay</div>
                        <div class="value">{{ $money($payslip->net_pay) }}</div>
                    </div>
                </div>

                <table class="table">
                    <tr>
                        <td>Employee Name</td>
                        <td>{{ $employee?->name ?: '-' }}</td>
                    </tr>
                    <tr>
                        <td>Employee ID</td>
                        <td>{{ $profile?->employee_code ?: '-' }}</td>
                    </tr>
                    <tr>
                        <td>Department</td>
                        <td>{{ $profile?->department?->name ?: '-' }}</td>
                    </tr>
                    <tr>
                        <td>Designation</td>
                        <td>{{ $profile?->designation?->name ?: ($employee?->role?->name ?: '-') }}</td>
                    </tr>
                    <tr>
                        <td>Generated On</td>
                        <td>{{ $generatedOn->format('d M Y') }}</td>
                    </tr>
                    <tr>
                        <td>Payable Days</td>
                        <td>{{ number_format((float) ($attendance['payable_days'] ?? 0), 1) }} / {{ $monthDays }}</td>
                    </tr>
                </table>

                <div class="section">
                    <h2 class="section-title">Attendance Calculation</h2>
                    <div class="mini-grid">
                        @foreach([
                            'Total Month Days' => $monthDays,
                            'Payable Days' => number_format((float) ($attendance['payable_days'] ?? 0), 1),
                            'Present / Late' => number_format((float) ($attendance['present_days'] ?? 0), 1),
                            'Half Day' => number_format((float) ($attendance['half_days'] ?? 0), 1),
                            'Absent' => number_format((float) ($attendance['absent_days'] ?? 0), 1),
                            'Paid Leave' => number_format((float) ($attendance['paid_leave_days'] ?? 0), 1),
                            'Unpaid Leave' => number_format((float) ($attendance['unpaid_leave_days'] ?? 0), 1),
                            'Week Off / Holiday' => number_format((float) (($attendance['weekoff_days'] ?? 0) + ($attendance['holiday_days'] ?? 0)), 1),
                            'Late Count' => (int) ($attendance['late_count'] ?? 0),
                            'Late Penalty Days' => number_format((float) ($attendance['late_penalty_days'] ?? 0), 1),
                        ] as $label => $value)
                            <div class="mini-stat">
                                <div class="label">{{ $label }}</div>
                                <div class="value">{{ $value }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="section">
                    <h2 class="section-title">Date-wise Attendance Impact</h2>
                    <div class="impact-list">
                        @forelse($attendanceDetails as $record)
                            <div class="impact-card">
                                <div class="date">
                                    {{ $record->attendance_date?->format('d M Y') }}
                                    <span class="badge">{{ $statusLabel($record) }}</span>
                                </div>
                                <div class="meta">
                                    Payable: <strong>{{ number_format((float) ($record->payable_day_fraction ?? 0), 1) }} day</strong><br>
                                    Reason: {{ $record->manual_override_reason ?: ucwords(str_replace('_', ' ', (string) ($record->status_source ?? '-'))) }}
                                </div>
                            </div>
                        @empty
                            <div class="impact-card">
                                <div class="date">No salary-impact dates</div>
                                <div class="meta">Is month me absent, leave, late, week-off ya holiday impact record nahi mila.</div>
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="section">
                    <h2 class="section-title">Salary Breakup</h2>
                    <div class="breakup-grid">
                        <table class="breakup-table">
                            <thead>
                                <tr>
                                    <th>Earning</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($earnings as $line)
                                    <tr>
                                        <td>{{ $line['label'] ?? '-' }}</td>
                                        <td>{{ $money($line['amount'] ?? 0) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td>No earnings</td>
                                        <td>{{ $money(0) }}</td>
                                    </tr>
                                @endforelse
                                <tr>
                                    <td><strong>Total Earnings</strong></td>
                                    <td>{{ $money($payslip->gross_pay) }}</td>
                                </tr>
                            </tbody>
                        </table>

                        <table class="breakup-table">
                            <thead>
                                <tr>
                                    <th>Deduction</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($deductions as $line)
                                    <tr>
                                        <td>{{ $line['label'] ?? '-' }}</td>
                                        <td>{{ $money($line['amount'] ?? 0) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td>No deductions</td>
                                        <td>{{ $money(0) }}</td>
                                    </tr>
                                @endforelse
                                <tr>
                                    <td><strong>Total Deductions</strong></td>
                                    <td>{{ $money($payslip->total_deductions) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="foot">
                    Agar details mismatch ho to HR ya payroll team se immediately verify karein.
                </div>
            </div>
        </div>
    </div>
</body>
</html>

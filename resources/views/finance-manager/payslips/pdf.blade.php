@php
    $earnings = collect($snapshot['earnings'] ?? []);
    $deductions = collect($snapshot['deductions'] ?? []);
    $hasEmployeeProfiles = \Illuminate\Support\Facades\Schema::hasTable('employee_profiles');
    $hasCompanySettings = \Illuminate\Support\Facades\Schema::hasTable('company_settings');
    $hasCompanyFiles = \Illuminate\Support\Facades\Schema::hasTable('company_files');
    $employee = $payslip->user;

    if ($employee) {
        $relations = ['role'];

        if ($hasEmployeeProfiles) {
            $relations[] = 'employeeProfile.department';
            $relations[] = 'employeeProfile.designation';
        }

        $employee->loadMissing($relations);
    }

    $employeeProfile = $hasEmployeeProfiles ? $employee?->employeeProfile : null;
    $attendance = $snapshot['attendance_snapshot'] ?? [];
    $periodDate = \Carbon\Carbon::create((int) $payslip->year, (int) $payslip->month, 1);
    $periodLabel = $periodDate->format('M Y');
    $payDate = $periodDate->copy()->endOfMonth();
    $generatedOn = $payslip->generated_at ?? $payslip->created_at ?? now();
    $daysInMonth = max(1, $periodDate->daysInMonth);

    $money = static fn ($amount): string => 'Rs ' . number_format((float) $amount, 2);
    $show = static fn ($value, string $fallback = '-'): string => filled($value) ? (string) $value : $fallback;
    $maskedAccount = static function (?string $accountNumber): string {
        $accountNumber = preg_replace('/\s+/', '', trim((string) $accountNumber));

        if ($accountNumber === '') {
            return '-';
        }

        return str_repeat('X', max(strlen($accountNumber) - 4, 0)) . substr($accountNumber, -4);
    };

    $verificationUrl = $payslip->verification_token ? route('payslips.verify', $payslip->verification_token) : null;
    $qrImageUrl = $verificationUrl ? 'https://quickchart.io/qr?size=140&margin=1&ecLevel=M&text=' . urlencode($verificationUrl) : null;
    $signatureDataUri = null;
    if (!empty($settings->signatory_image_path)) {
        $signatureFullPath = \Illuminate\Support\Facades\Storage::disk('public')->path($settings->signatory_image_path);
        $signatureMime = \Illuminate\Support\Facades\Storage::disk('public')->mimeType($settings->signatory_image_path);
        if ($signatureMime && str_starts_with((string) $signatureMime, 'image/') && file_exists($signatureFullPath)) {
            $signatureDataUri = 'data:' . $signatureMime . ';base64,' . base64_encode(file_get_contents($signatureFullPath));
        }
    }

    $logoDataUri = null;
    $logoFile = $hasCompanyFiles ? \App\Models\CompanyFile::getActiveFile('logo') : null;
    if ($logoFile && $logoFile->mime_type && str_starts_with((string) $logoFile->mime_type, 'image/') && file_exists($logoFile->full_path)) {
        $logoDataUri = 'data:' . $logoFile->mime_type . ';base64,' . base64_encode(file_get_contents($logoFile->full_path));
    }

    $setting = static fn (string $key, $default = null) => $hasCompanySettings ? \App\Models\CompanySetting::get($key, $default) : $default;

    $companyName = $settings->company_name ?: $setting('company_name', config('app.name', 'Company'));
    $companyPhone = $setting('phone');
    $companyEmail = $setting('email');
    $companyCin = $setting('cin');

    $leftRows = [
        ['label' => 'Employee Name', 'value' => $employee?->name],
        ['label' => 'Department', 'value' => $employeeProfile?->department?->name],
        ['label' => 'Designation', 'value' => $employeeProfile?->designation?->name ?: $employee?->role?->name],
        ['label' => 'Date of Joining', 'value' => optional($employeeProfile?->joining_date)->format('d M Y')],
        ['label' => 'PAN', 'value' => $employeeProfile?->pan_number],
    ];

    $rightRows = [
        ['label' => 'Email', 'value' => $employee?->email],
        ['label' => 'Bank Account No.', 'value' => $maskedAccount($employeeProfile?->bank_account_number)],
        ['label' => 'IFSC Code', 'value' => $employeeProfile?->ifsc_code],
        ['label' => 'Employee ID', 'value' => $employeeProfile?->employee_code],
        ['label' => 'Paid Days / Total Days', 'value' => number_format((float) ($attendance['payable_days'] ?? 0), 1) . ' / ' . $daysInMonth],
    ];
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 24px 26px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 10px;
            line-height: 1.45;
            color: #1d2c25;
            background: #ffffff;
        }

        .sheet {
            border: 1px solid #d8ddd9;
            padding: 16px 16px 12px;
            background: #ffffff;
        }

        .header-table,
        .meta-table,
        .employee-table,
        .breakdown-table,
        .line-table,
        .footer-table,
        .netpay-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td,
        .breakdown-table td,
        .footer-table td,
        .netpay-table td {
            vertical-align: top;
        }

        .brand-col {
            width: 67%;
            padding-right: 18px;
            border-right: 1px solid #cfd7d2;
        }

        .meta-col {
            width: 33%;
            padding-left: 18px;
        }

        .brand-inner {
            width: 100%;
            border-collapse: collapse;
        }

        .brand-inner td {
            vertical-align: top;
        }

        .logo-slot {
            width: 86px;
            padding-right: 14px;
        }

        .logo-box {
            width: 74px;
            height: 92px;
            text-align: center;
        }

        .logo-box img {
            max-width: 72px;
            max-height: 92px;
        }

        .logo-fallback {
            border: 1px solid #d8ddd9;
            padding: 28px 0;
            font-weight: 700;
            font-size: 20px;
            color: #144533;
        }

        .mini-kicker {
            font-size: 8px;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            color: #67766d;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .company-name {
            font-family: Georgia, 'Times New Roman', DejaVu Serif, serif;
            font-size: 19px;
            line-height: 1.1;
            font-weight: 700;
            color: #1f4636;
            margin-bottom: 6px;
        }

        .dept-line {
            font-size: 11px;
            font-weight: 700;
            color: #1f2c26;
            margin-bottom: 10px;
        }

        .office-title {
            font-size: 10px;
            font-weight: 700;
            color: #24332c;
            margin-bottom: 4px;
        }

        .office-copy,
        .brand-contact {
            font-size: 9px;
            color: #5e6d65;
            line-height: 1.55;
        }

        .header-bottom {
            margin-top: 10px;
            padding-top: 8px;
            border-top: 1px solid #cfd7d2;
        }

        .meta-table td {
            padding: 6px 0;
            font-size: 10px;
        }

        .meta-label {
            width: 55%;
            font-weight: 700;
            color: #273730;
            text-transform: uppercase;
        }

        .meta-sep {
            width: 8%;
            text-align: center;
            color: #54645c;
        }

        .meta-value {
            width: 37%;
            text-align: right;
            font-weight: 700;
            color: #22332c;
        }

        .section-title {
            margin: 14px 0 8px;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #214133;
            font-weight: 700;
        }

        .employee-table {
            border: 1px solid #d8ddd9;
        }

        .employee-table td {
            border: 1px solid #dfe4e0;
            padding: 8px 10px;
            font-size: 10px;
        }

        .employee-label {
            width: 19%;
            font-weight: 700;
            color: #24342c;
        }

        .employee-sep {
            width: 4%;
            text-align: center;
            color: #66756d;
        }

        .employee-value {
            width: 27%;
            color: #1f2e27;
            font-weight: 600;
        }

        .breakdown-table {
            margin-top: 12px;
        }

        .breakdown-table td {
            width: 50%;
        }

        .breakdown-table td:first-child {
            padding-right: 8px;
        }

        .breakdown-table td:last-child {
            padding-left: 8px;
        }

        .line-card {
            border: 1px solid #d8ddd9;
        }

        .line-head {
            padding: 11px 14px 9px;
            border-bottom: 1px solid #d8ddd9;
            font-size: 10px;
            font-weight: 700;
            color: #214133;
            text-transform: uppercase;
        }

        .line-table th,
        .line-table td {
            border: 1px solid #e1e5e2;
            padding: 9px 12px;
            font-size: 10px;
        }

        .line-table th {
            text-transform: uppercase;
            text-align: left;
            font-weight: 700;
            color: #25352d;
            background: #ffffff;
        }

        .line-table th.amount,
        .line-table td.amount {
            width: 38%;
            text-align: right;
            white-space: nowrap;
        }

        .line-total td {
            font-weight: 700;
            background: #faf7f3;
        }

        .netpay-wrap {
            margin-top: 16px;
            border: 1px solid #214133;
        }

        .netpay-copy {
            padding: 13px 14px;
        }

        .netpay-title {
            font-size: 11px;
            font-weight: 700;
            color: #22352c;
            text-transform: uppercase;
        }

        .netpay-subtitle {
            margin-top: 5px;
            font-size: 9.5px;
            color: #46574f;
        }

        .netpay-amount {
            width: 28%;
            background: #0f5a41;
            color: #ffffff;
            text-align: center;
            font-size: 22px;
            font-weight: 700;
            padding: 18px 10px;
            vertical-align: middle;
        }

        .footer-table {
            margin-top: 16px;
        }

        .footer-table td:first-child {
            width: 54%;
            padding-right: 8px;
        }

        .footer-table td:last-child {
            width: 46%;
            padding-left: 8px;
        }

        .footer-card {
            border: 1px solid #d8ddd9;
            border-radius: 9px;
            padding: 12px 14px;
            min-height: 138px;
        }

        .note-list {
            margin: 8px 0 0 0;
            padding-left: 14px;
            color: #2f3e37;
        }

        .note-list li {
            margin-bottom: 8px;
            font-size: 10px;
        }

        .sign-copy {
            font-size: 10px;
            color: #304038;
            line-height: 1.55;
        }

        .sign-name {
            margin-top: 12px;
            font-size: 12px;
            font-weight: 700;
            color: #20342a;
        }

        .signature-image {
            display: block;
            max-width: 150px;
            max-height: 52px;
            margin: 8px 0 4px;
        }

        .sign-title {
            font-size: 10px;
            color: #55675f;
            margin-top: 2px;
        }

        .sign-grid {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .sign-grid td {
            vertical-align: top;
        }

        .qr-box {
            width: 98px;
            text-align: center;
        }

        .qr-box img {
            width: 82px;
            height: 82px;
            border: 1px solid #d8ddd9;
            padding: 5px;
            background: #ffffff;
        }

        .qr-note {
            margin-top: 4px;
            font-size: 8.5px;
            color: #5a6a62;
            line-height: 1.4;
        }

        .footer-strip {
            margin-top: 14px;
            padding-top: 8px;
            border-top: 1px solid #d8ddd9;
            text-align: center;
            font-size: 9px;
            color: #43544c;
        }
    </style>
</head>
<body>
    <div class="sheet">
        <table class="header-table">
            <tr>
                <td class="brand-col">
                    <table class="brand-inner">
                        <tr>
                            <td class="logo-slot">
                                <div class="logo-box">
                                    @if($logoDataUri)
                                        <img src="{{ $logoDataUri }}" alt="Company Logo">
                                    @else
                                        <div class="logo-fallback">{{ strtoupper(substr($companyName, 0, 2)) }}</div>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div class="mini-kicker">Payroll Department</div>
                                <div class="company-name">{{ $companyName }}</div>
                                <div class="dept-line">Payroll Department</div>
                                @if($settings->header_text)
                                    <div class="office-title">Registered Office:</div>
                                    <div class="office-copy">{{ $settings->header_text }}</div>
                                @endif
                            </td>
                        </tr>
                    </table>
                    <div class="header-bottom brand-contact">
                        @if($companyCin) CIN: {{ $companyCin }} @endif
                        @if($companyPhone) | Phone: {{ $companyPhone }} @endif
                        @if($companyEmail) | Email: {{ $companyEmail }} @endif
                    </div>
                </td>
                <td class="meta-col">
                    <table class="meta-table">
                        <tr>
                            <td class="meta-label">Payroll Month</td>
                            <td class="meta-sep">:</td>
                            <td class="meta-value">{{ $periodLabel }}</td>
                        </tr>
                        <tr>
                            <td class="meta-label">Pay Day</td>
                            <td class="meta-sep">:</td>
                            <td class="meta-value">{{ $payDate->format('d M Y') }}</td>
                        </tr>
                        <tr>
                            <td class="meta-label">Payslip No.</td>
                            <td class="meta-sep">:</td>
                            <td class="meta-value">{{ $payslip->payslip_number }}</td>
                        </tr>
                        <tr>
                            <td class="meta-label">Employee ID</td>
                            <td class="meta-sep">:</td>
                            <td class="meta-value">{{ $show($employeeProfile?->employee_code) }}</td>
                        </tr>
                        <tr>
                            <td class="meta-label">Generated On</td>
                            <td class="meta-sep">:</td>
                            <td class="meta-value">{{ $generatedOn->format('d M Y') }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <div class="section-title">Employee Information</div>
        <table class="employee-table">
            @for($i = 0; $i < max(count($leftRows), count($rightRows)); $i++)
                @php
                    $left = $leftRows[$i] ?? ['label' => '', 'value' => ''];
                    $right = $rightRows[$i] ?? ['label' => '', 'value' => ''];
                @endphp
                <tr>
                    <td class="employee-label">{{ $left['label'] }}</td>
                    <td class="employee-sep">:</td>
                    <td class="employee-value">{{ $show($left['value']) }}</td>
                    <td class="employee-label">{{ $right['label'] }}</td>
                    <td class="employee-sep">:</td>
                    <td class="employee-value">{{ $show($right['value']) }}</td>
                </tr>
            @endfor
        </table>

        <table class="breakdown-table">
            <tr>
                <td>
                    <div class="section-title">Earnings</div>
                    <div class="line-card">
                        <div class="line-head">Earnings</div>
                        <table class="line-table">
                            <thead>
                                <tr>
                                    <th>Description</th>
                                    <th class="amount">Amount (Rs)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($earnings as $line)
                                    <tr>
                                        <td>{{ $line['label'] ?? '-' }}</td>
                                        <td class="amount">{{ $money($line['amount'] ?? 0) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td>-</td>
                                        <td class="amount">{{ $money(0) }}</td>
                                    </tr>
                                @endforelse
                                <tr class="line-total">
                                    <td>Total Earnings (A)</td>
                                    <td class="amount">{{ $money($payslip->gross_pay) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </td>
                <td>
                    <div class="section-title">Deductions</div>
                    <div class="line-card">
                        <div class="line-head">Deductions</div>
                        <table class="line-table">
                            <thead>
                                <tr>
                                    <th>Description</th>
                                    <th class="amount">Amount (Rs)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($deductions as $line)
                                    <tr>
                                        <td>{{ $line['label'] ?? '-' }}</td>
                                        <td class="amount">{{ $money($line['amount'] ?? 0) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td>No deductions</td>
                                        <td class="amount">{{ $money(0) }}</td>
                                    </tr>
                                @endforelse
                                <tr class="line-total">
                                    <td>Total Deductions (B)</td>
                                    <td class="amount">{{ $money($payslip->total_deductions) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </td>
            </tr>
        </table>

        <div class="netpay-wrap">
            <table class="netpay-table">
                <tr>
                    <td>
                        <div class="netpay-copy">
                            <div class="netpay-title">Net Pay (A - B)</div>
                            <div class="netpay-subtitle">
                                Final take-home salary after deductions.
                            </div>
                        </div>
                    </td>
                    <td class="netpay-amount">{{ $money($payslip->net_pay) }}</td>
                </tr>
            </table>
        </div>

        <table class="footer-table">
            <tr>
                <td>
                    <div class="footer-card">
                        <div class="section-title" style="margin-top: 0;">Notes</div>
                        <ul class="note-list">
                            <li>This is a computer-generated payslip and does not require a physical signature.</li>
                            <li>{{ $settings->default_notes ?: 'Please verify all details and contact payroll team if any correction is required.' }}</li>
                        </ul>
                    </div>
                </td>
                <td>
                    <div class="footer-card">
                        <div class="section-title" style="margin-top: 0;">Authorized Signatory</div>
                        <div class="sign-copy">Digitally signed</div>
                        <table class="sign-grid">
                            <tr>
                                <td>
                                    @if($signatureDataUri)
                                        <img src="{{ $signatureDataUri }}" alt="Authorized signature" class="signature-image">
                                    @endif
                                    <div class="sign-name">{{ $settings->signatory_name ?: 'Payroll Team' }}</div>
                                    <div class="sign-title">{{ $settings->signatory_title ?: 'Authorized Signatory' }}</div>
                                    @if($settings->footer_text)
                                        <div class="sign-copy" style="margin-top: 8px;">{{ $settings->footer_text }}</div>
                                    @endif
                                </td>
                                <td class="qr-box">
                                    @if($qrImageUrl)
                                        <img src="{{ $qrImageUrl }}" alt="Payslip verification QR">
                                        <div class="qr-note">Scan to verify this payslip</div>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </div>
                </td>
            </tr>
        </table>

        <div class="footer-strip">
            Confidential Document | Generated via HRMS on {{ $generatedOn->format('d M Y, h:i A') }}
        </div>
    </div>
</body>
</html>

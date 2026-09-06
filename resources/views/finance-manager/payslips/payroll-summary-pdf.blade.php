<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><style>body{font-family:Arial,sans-serif;font-size:12px;color:#111827}table{width:100%;border-collapse:collapse;margin-top:16px}th,td{border:1px solid #d1d5db;padding:8px;text-align:left}th{background:#f3f4f6}</style></head>
<body>
    <h1>Payroll Summary - {{ sprintf('%02d', $month) }}/{{ $year }}</h1>
    <table>
        <thead><tr><th>Employee</th><th>Office</th><th>Payable Days</th><th>Estimated Salary</th><th>Gross</th><th>Deductions</th><th>Net</th></tr></thead>
        <tbody>
        @foreach($rollups as $rollup)
            @php $payslip = $payslips->get($rollup->user_id); @endphp
            <tr>
                <td>{{ $rollup->user?->name }}</td>
                <td>{{ $rollup->officeLocation?->name ?? 'Company' }}</td>
                <td>{{ number_format((float) $rollup->payable_days, 2) }}</td>
                <td>{{ number_format((float) $rollup->estimated_salary, 2) }}</td>
                <td>{{ $payslip ? number_format((float) $payslip->gross_pay, 2) : '--' }}</td>
                <td>{{ $payslip ? number_format((float) $payslip->total_deductions, 2) : '--' }}</td>
                <td>{{ $payslip ? number_format((float) $payslip->net_pay, 2) : '--' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>

<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><style>body{font-family:Arial,sans-serif;font-size:12px;color:#111827}table{width:100%;border-collapse:collapse;margin-top:16px}th,td{border:1px solid #d1d5db;padding:8px;text-align:left}th{background:#f3f4f6}</style></head>
<body>
    <h1>Attendance Summary - {{ sprintf('%02d', $month) }}/{{ $year }}</h1>
    <table>
        <thead><tr><th>Date</th><th>Employee</th><th>Office</th><th>Status</th><th>Punch In</th><th>Punch Out</th><th>Fraud</th></tr></thead>
        <tbody>
        @foreach($records as $record)
            <tr>
                <td>{{ $record->attendance_date?->format('Y-m-d') }}</td>
                <td>{{ $record->user?->name }}</td>
                <td>{{ $record->officeLocation?->name ?? 'Company' }}</td>
                <td>{{ ucwords(str_replace('_', ' ', $record->status)) }}</td>
                <td>{{ $record->first_punch_in_at?->format('H:i:s') }}</td>
                <td>{{ $record->last_punch_out_at?->format('H:i:s') }}</td>
                <td>{{ ucwords(str_replace('_', ' ', $record->fraud_review_status ?? 'clear')) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>

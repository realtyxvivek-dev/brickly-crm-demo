<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $definition['label'] }} Report</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; color:#111827; font-size:11px; }
        h1 { font-size:20px; margin:0 0 4px; }
        .meta { color:#6b7280; margin-bottom:14px; }
        table { width:100%; border-collapse:collapse; }
        th { background:#f3f4f6; text-align:left; font-size:10px; text-transform:uppercase; }
        th, td { border:1px solid #e5e7eb; padding:7px; vertical-align:top; }
    </style>
</head>
<body>
    <h1>{{ $definition['label'] }} Report</h1>
    <div class="meta">Generated at {{ now()->format('Y-m-d H:i') }}</div>

    <table>
        <thead>
            <tr>
                @foreach($definition['columns'] as $label)
                    <th>{{ $label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr>
                    @foreach($row as $value)
                        <td>{{ $value !== '' ? $value : '-' }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>

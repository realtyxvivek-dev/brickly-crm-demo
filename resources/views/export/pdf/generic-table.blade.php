<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Export' }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; color:#0f172a; font-size:11px; }
        h1 { margin:0 0 12px; font-size:18px; color:#0f5132; }
        table { width:100%; border-collapse:collapse; }
        th, td { border:1px solid #dbe6df; padding:7px 8px; text-align:left; vertical-align:top; }
        th { background:#edf8f2; color:#0f2d22; font-size:10px; text-transform:uppercase; letter-spacing:.04em; }
        td { word-break:break-word; }
    </style>
</head>
<body>
    <h1>{{ $title ?? 'Export' }}</h1>
    <table>
        <thead>
            <tr>
                @foreach($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr>
                    @foreach($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
